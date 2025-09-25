<?php
// api/applications/review.php - Application Review (Approve/Reject)
require_once '../../includes/init.php';

$auth->requireUserType(USER_TYPE_ADMIN);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }

    // Validate CSRF token
    if (!isset($input['csrf_token']) || !$auth->validateCSRFToken($input['csrf_token'])) {
        throw new Exception('Invalid security token');
    }

    // Validate required fields
    if (!isset($input['action']) || !isset($input['application_id'])) {
        throw new Exception('Missing required fields: action and application_id');
    }

    $action = $input['action'];
    $applicationId = (int)$input['application_id'];
    
    if (!in_array($action, ['approve', 'reject'])) {
        throw new Exception('Invalid action. Must be approve or reject.');
    }

    // Get application details
    $application = $database->getRow(
        "SELECT * FROM project_applications WHERE application_id = ?",
        [$applicationId]
    );

    if (!$application) {
        throw new Exception('Application not found');
    }

    if ($application['status'] !== APPLICATION_STATUS_PENDING) {
        throw new Exception('Application has already been processed');
    }

    // Begin transaction
    $database->beginTransaction();

    try {
        $adminId = $auth->getUserId();
        
        if ($action === 'approve') {
            // Approve application and create project
            $result = approveApplication($application, $adminId);
        } else {
            // Reject application
            $rejectionReason = $input['rejection_reason'] ?? '';
            if (empty($rejectionReason)) {
                throw new Exception('Rejection reason is required');
            }
            $result = rejectApplication($application, $adminId, $rejectionReason);
        }

        // Commit transaction
        $database->commit();

        // Log the action
        logActivity(
            USER_TYPE_ADMIN, 
            $adminId, 
            'application_' . $action, 
            "Application {$action}d: {$application['project_name']}", 
            null, 
            ['application_id' => $applicationId]
        );

        echo json_encode($result);

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
        'application_review_error', 
        "Application review failed: " . $e->getMessage()
    );
}

/**
 * Approve application and create project
 */
function approveApplication($application, $adminId) {
    global $database;

    // Update application status
    $database->update(
        'project_applications',
        [
            'status' => APPLICATION_STATUS_APPROVED,
            'reviewed_at' => date('Y-m-d H:i:s'),
            'reviewed_by' => $adminId
        ],
        'application_id = ?',
        [$application['application_id']]
    );

    // Create project from application
    $projectData = [
        'project_name' => $application['project_name'],
        'date' => $application['date'],
        'project_email' => $application['project_email'],
        'project_website' => $application['project_website'],
        'description' => $application['description'],
        'profile_name' => $application['profile_name'],
        'password' => $application['password'], // Already hashed
        'project_lead_name' => $application['project_lead_name'],
        'project_lead_email' => $application['project_lead_email'],
        'current_stage' => 1,
        'status' => PROJECT_STATUS_ACTIVE,
        'created_from_application' => $application['application_id'],
        'created_by_admin' => $adminId
    ];

    $projectId = $database->insert('projects', $projectData);

    if (!$projectId) {
        throw new Exception('Failed to create project');
    }

    // Add project lead as first team member
    $database->insert('project_innovators', [
        'project_id' => $projectId,
        'name' => $application['project_lead_name'],
        'email' => $application['project_lead_email'],
        'role' => 'Project Lead',
        'level_of_experience' => 'Lead',
        'added_by_type' => 'admin',
        'added_by_id' => $adminId
    ]);

    // Send approval email
    if (function_exists('sendEmailNotification')) {
        $emailSubject = "Congratulations! Your JHUB AFRICA Application Has Been Approved";
        $emailMessage = createApprovalEmailMessage($application, $projectId);
        
        sendEmailNotification(
            $application['project_lead_email'],
            $emailSubject,
            $emailMessage,
            NOTIFY_APPLICATION_APPROVED
        );
    }

    return [
        'success' => true,
        'message' => 'Application approved successfully! Project created and confirmation email sent.',
        'project_id' => $projectId
    ];
}

/**
 * Reject application
 */
function rejectApplication($application, $adminId, $rejectionReason) {
    global $database;

    // Update application status
    $database->update(
        'project_applications',
        [
            'status' => APPLICATION_STATUS_REJECTED,
            'reviewed_at' => date('Y-m-d H:i:s'),
            'reviewed_by' => $adminId,
            'rejection_reason' => $rejectionReason
        ],
        'application_id = ?',
        [$application['application_id']]
    );

    // Send rejection email
    if (function_exists('sendEmailNotification')) {
        $emailSubject = "Update on Your JHUB AFRICA Application";
        $emailMessage = createRejectionEmailMessage($application, $rejectionReason);
        
        sendEmailNotification(
            $application['project_lead_email'],
            $emailSubject,
            $emailMessage,
            NOTIFY_APPLICATION_REJECTED
        );
    }

    return [
        'success' => true,
        'message' => 'Application rejected and notification email sent.'
    ];
}

/**
 * Create approval email message
 */
function createApprovalEmailMessage($application, $projectId) {
    $baseUrl = getBaseUrl();
    
    return "
Dear {$application['project_lead_name']},

Congratulations! We are pleased to inform you that your project application '{$application['project_name']}' has been APPROVED for the JHUB AFRICA Innovation Program.

Your project has been accepted into our comprehensive 6-stage development journey, where you'll receive mentorship, resources, and support to bring your innovation to market.

NEXT STEPS:

1. Access Your Project Dashboard:
   - Visit: {$baseUrl}/auth/project-login.php
   - Username: {$application['profile_name']}
   - Use the password you created during application

2. Complete Your Project Profile:
   - Add team members
   - Update project information
   - Upload additional resources

3. Mentor Assignment:
   - Our mentors will review your project
   - Qualified mentors will join your project
   - You'll be notified when mentors are assigned

WHAT'S NEXT:

Stage 1: Project Setup & Team Building
- Complete your project profile
- Add team members
- Prepare for mentor assignment

You will receive further guidance as you progress through each stage of our program.

SUPPORT:
If you have any questions or need assistance, please contact us at support@jhubafrica.com

Welcome to the JHUB AFRICA family! We're excited to support your innovation journey.

Best regards,
The JHUB AFRICA Team
{$baseUrl}
";
}

/**
 * Create rejection email message
 */
function createRejectionEmailMessage($application, $rejectionReason) {
    $baseUrl = getBaseUrl();
    
    return "
Dear {$application['project_lead_name']},

Thank you for your interest in the JHUB AFRICA Innovation Program and for submitting your project '{$application['project_name']}'.

After careful review, we regret to inform you that we are unable to accept your application into our current program cycle.

FEEDBACK:
{$rejectionReason}

WHAT'S NEXT:
While your application was not successful this time, we encourage you to:

1. Review the feedback provided above
2. Develop your project further based on our suggestions
3. Consider reapplying in future program cycles
4. Stay connected with our community for updates and opportunities

We receive many high-quality applications and the selection process is highly competitive. This decision does not reflect the quality of your innovation but rather the specific fit with our current program parameters.

STAY CONNECTED:
- Visit our website: {$baseUrl}
- Follow our updates for future opportunities
- Contact us if you have questions: support@jhubafrica.com

We wish you the best in your innovation journey and hope to potentially work with you in the future.

Best regards,
The JHUB AFRICA Team
{$baseUrl}
";
}
?>