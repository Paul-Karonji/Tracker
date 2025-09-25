<?php
// api/applications/submit.php - Project Application Submission
require_once '../../includes/init.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Validate CSRF token
    if (!Validator::validateCSRF()) {
        throw new Exception('Invalid security token');
    }

    // Validate required fields
    $validator = new Validator($_POST);
    $validator->required('project_name', 'Project name is required')
             ->required('description', 'Project description is required')
             ->required('project_lead_name', 'Project lead name is required')
             ->required('project_lead_email', 'Project lead email is required')
             ->email('project_lead_email')
             ->required('profile_name', 'Profile name is required')
             ->required('password', 'Password is required')
             ->min('password', 8);

    // Validate optional website URL
    if (!empty($_POST['project_website'])) {
        $validator->url('project_website');
    }

    // Handle file upload
    $presentationFile = null;
    if (isset($_FILES['presentation_file']) && $_FILES['presentation_file']['error'] === UPLOAD_ERR_OK) {
        $fileUpload = new FileUpload();
        $uploadResult = $fileUpload->handleUpload($_FILES['presentation_file'], 'presentations');
        if ($uploadResult['success']) {
            $presentationFile = $uploadResult['filename'];
        } else {
            $validator->addError('presentation_file', $uploadResult['message']);
        }
    }

    if (!$validator->isValid()) {
        throw new Exception('Validation failed: ' . implode(', ', array_map(function($errors) {
            return implode(', ', $errors);
        }, $validator->getErrors())));
    }

    // Check for duplicate profile names
    $existingProfile = $database->getRow(
        "SELECT application_id FROM project_applications WHERE profile_name = ?",
        [$_POST['profile_name']]
    );
    
    if ($existingProfile) {
        throw new Exception('Profile name already exists. Please choose a different one.');
    }

    // Prepare application data
    $applicationData = [
        'project_name' => trim($_POST['project_name']),
        'date' => $_POST['date'] ?? date('Y-m-d'),
        'project_email' => trim($_POST['project_email'] ?? ''),
        'project_website' => trim($_POST['project_website'] ?? ''),
        'description' => trim($_POST['description']),
        'project_lead_name' => trim($_POST['project_lead_name']),
        'project_lead_email' => trim($_POST['project_lead_email']),
        'presentation_file' => $presentationFile,
        'profile_name' => trim($_POST['profile_name']),
        'password' => Auth::hashPassword($_POST['password']),
        'status' => APPLICATION_STATUS_PENDING,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
    ];

    // Insert application
    $applicationId = $database->insert('project_applications', $applicationData);

    if (!$applicationId) {
        throw new Exception('Failed to submit application. Please try again.');
    }

    // Log the application submission
    logActivity('system', null, 'application_submitted', 
        "New project application submitted: {$applicationData['project_name']}", 
        null, ['application_id' => $applicationId]);

    // Send confirmation email (if email system is ready)
    if (function_exists('sendEmailNotification')) {
        sendEmailNotification(
            $applicationData['project_lead_email'],
            'Application Received - JHUB AFRICA',
            "Thank you for submitting your project '{$applicationData['project_name']}'. We will review your application and get back to you soon.",
            'application_received'
        );
    }

    echo json_encode([
        'success' => true,
        'message' => 'Application submitted successfully! You will receive an email confirmation shortly.',
        'application_id' => $applicationId
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    
    // Log error
    logActivity('system', null, 'application_error', 
        "Application submission failed: " . $e->getMessage());
}
?>