<?php
// api/projects/create.php - Direct Project Creation (Admin Only)
require_once '../../includes/init.php';

$auth->requireUserType(USER_TYPE_ADMIN);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Get input data
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    // Validate CSRF token
    if (!isset($input['csrf_token']) || !$auth->validateCSRFToken($input['csrf_token'])) {
        throw new Exception('Invalid security token');
    }

    // Validate required fields
    $validator = new Validator($input);
    $validator->required('project_name', 'Project name is required')
             ->required('description', 'Project description is required')
             ->required('project_lead_name', 'Project lead name is required')
             ->required('project_lead_email', 'Project lead email is required')
             ->email('project_lead_email')
             ->required('profile_name', 'Profile name is required')
             ->required('password', 'Password is required')
             ->min('password', 8);

    // Validate optional website URL
    if (!empty($input['project_website'])) {
        $validator->url('project_website');
    }

    if (!$validator->isValid()) {
        throw new Exception('Validation failed: ' . implode(', ', array_map(function($errors) {
            return implode(', ', $errors);
        }, $validator->getErrors())));
    }

    // Check for duplicate profile names in both projects and applications
    $existingProject = $database->getRow(
        "SELECT project_id FROM projects WHERE profile_name = ?",
        [$input['profile_name']]
    );
    
    $existingApplication = $database->getRow(
        "SELECT application_id FROM project_applications WHERE profile_name = ?",
        [$input['profile_name']]
    );
    
    if ($existingProject || $existingApplication) {
        throw new Exception('Profile name already exists. Please choose a different one.');
    }

    // Begin transaction
    $database->beginTransaction();

    try {
        $adminId = $auth->getUserId();

        // Prepare project data
        $projectData = [
            'project_name' => trim($input['project_name']),
            'date' => $input['date'] ?? date('Y-m-d'),
            'project_email' => trim($input['project_email'] ?? ''),
            'project_website' => trim($input['project_website'] ?? ''),
            'description' => trim($input['description']),
            'profile_name' => trim($input['profile_name']),
            'password' => Auth::hashPassword($input['password']),
            'project_lead_name' => trim($input['project_lead_name']),
            'project_lead_email' => trim($input['project_lead_email']),
            'current_stage' => $input['current_stage'] ?? 1,
            'status' => PROJECT_STATUS_ACTIVE,
            'created_by_admin' => $adminId,
            'target_market' => trim($input['target_market'] ?? ''),
            'business_model' => trim($input['business_model'] ?? ''),
            'funding_amount' => !empty($input['funding_amount']) ? (float)$input['funding_amount'] : null,
            'funding_currency' => $input['funding_currency'] ?? 'USD'
        ];

        // Insert project
        $projectId = $database->insert('projects', $projectData);

        if (!$projectId) {
            throw new Exception('Failed to create project');
        }

        // Add project lead as first team member
        $innovatorData = [
            'project_id' => $projectId,
            'name' => $projectData['project_lead_name'],
            'email' => $projectData['project_lead_email'],
            'role' => 'Project Lead',
            'level_of_experience' => 'Lead',
            'added_by_type' => 'admin',
            'added_by_id' => $adminId
        ];

        $innovatorId = $database->insert('project_innovators', $innovatorData);

        if (!$innovatorId) {
            throw new Exception('Failed to add project lead as team member');
        }

        // Add additional team members if provided
        if (!empty($input['team_members']) && is_array($input['team_members'])) {
            foreach ($input['team_members'] as $member) {
                if (!empty($member['name']) && !empty($member['email']) && !empty($member['role'])) {
                    $memberData = [
                        'project_id' => $projectId,
                        'name' => trim($member['name']),
                        'email' => trim($member['email']),
                        'role' => trim($member['role']),
                        'level_of_experience' => trim($member['level_of_experience'] ?? ''),
                        'added_by_type' => 'admin',
                        'added_by_id' => $adminId,
                        'phone' => trim($member['phone'] ?? ''),
                        'linkedin_url' => trim($member['linkedin_url'] ?? ''),
                        'bio' => trim($member['bio'] ?? '')
                    ];

                    $database->insert('project_innovators', $memberData);
                }
            }
        }

        // Commit transaction
        $database->commit();

        // Log the action
        logActivity(
            USER_TYPE_ADMIN, 
            $adminId, 
            'project_created_direct', 
            "Project created directly by admin: {$projectData['project_name']}", 
            $projectId,
            ['method' => 'direct_creation']
        );

        // Send welcome email if email system is ready
        if (function_exists('sendEmailNotification')) {
            $emailSubject = "Welcome to JHUB AFRICA - Your Project Has Been Created";
            $emailMessage = createProjectWelcomeEmail($projectData, $projectId);
            
            sendEmailNotification(
                $projectData['project_lead_email'],
                $emailSubject,
                $emailMessage,
                'project_created',
                ['project_id' => $projectId]
            );
        }

        echo json_encode([
            'success' => true,
            'message' => 'Project created successfully! Welcome email sent to project lead.',
            'project_id' => $projectId,
            'data' => [
                'project_id' => $projectId,
                'project_name' => $projectData['project_name'],
                'profile_name' => $projectData['profile_name'],
                'project_lead' => $projectData['project_lead_name'],
                'current_stage' => $projectData['current_stage']
            ]
        ]);

    } catch (Exception $e) {
        $database->rollback();
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    
    // Log error
    logActivity(
        USER_TYPE_ADMIN, 
        $auth->getUserId() ?? null, 
        'project_creation_error', 
        "Project creation failed: " . $e->getMessage()
    );
}

/**
 * Create project welcome email message
 */
function createProjectWelcomeEmail($project, $projectId) {
    $baseUrl = getBaseUrl();
    
    return "
<div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
    <h2 style='color: #667eea;'>Welcome to JHUB AFRICA!</h2>
    
    <p>Dear {$project['project_lead_name']},</p>

    <p>Congratulations! Your project '<strong>{$project['project_name']}</strong>' has been successfully created in the JHUB AFRICA Innovation Program.</p>

    <div style='background: #f8f9fa; padding: 20px; border-left: 4px solid #667eea; margin: 20px 0;'>
        <h3 style='margin-top: 0; color: #667eea;'>Your Project Dashboard Access</h3>
        <p><strong>Login URL:</strong> <a href='{$baseUrl}/auth/project-login.php'>{$baseUrl}/auth/project-login.php</a></p>
        <p><strong>Username:</strong> <code>{$project['profile_name']}</code></p>
        <p><strong>Password:</strong> The password you provided during creation</p>
    </div>

    <h3>Next Steps:</h3>
    <ol>
        <li><strong>Log in to your project dashboard</strong> using the credentials above</li>
        <li><strong>Complete your project profile</strong> with additional details</li>
        <li><strong>Add team members</strong> to collaborate on your project</li>
        <li><strong>Wait for mentor assignment</strong> - experienced mentors will review and join your project</li>
    </ol>

    <h3>Your Journey Through Our 6-Stage Framework:</h3>
    <ul>
        <li><strong>Stage 1:</strong> Project Creation & Team Building</li>
        <li><strong>Stage 2:</strong> Mentorship Assignment</li>
        <li><strong>Stage 3:</strong> Assessment & Evaluation</li>
        <li><strong>Stage 4:</strong> Learning & Development</li>
        <li><strong>Stage 5:</strong> Progress Tracking & Feedback</li>
        <li><strong>Stage 6:</strong> Showcase & Integration</li>
    </ul>

    <div style='background: #e8f4f8; padding: 15px; border-radius: 5px; margin: 20px 0;'>
        <p style='margin: 0;'><strong>Need Help?</strong></p>
        <p style='margin: 5px 0 0 0;'>Contact our support team at <a href='mailto:support@jhubafrica.com'>support@jhubafrica.com</a></p>
    </div>

    <p>Welcome to the JHUB AFRICA family! We're excited to support your innovation journey.</p>

    <p>Best regards,<br>
    The JHUB AFRICA Team</p>
</div>
";
}
?>