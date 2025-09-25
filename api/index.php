<?php
// api/index.php - API Router and Entry Point
require_once '../includes/init.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token');

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Get the requested route
    $route = $_GET['route'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];
    
    // Parse route
    $routeParts = array_filter(explode('/', $route));
    
    if (empty($routeParts)) {
        // API Info endpoint
        echo json_encode([
            'success' => true,
            'message' => 'JHUB AFRICA Project Tracker API',
            'version' => SITE_VERSION ?? '1.0.0',
            'timestamp' => time(),
            'endpoints' => [
                'applications' => [
                    'POST /api/applications/submit' => 'Submit project application',
                    'GET /api/applications/details?id={id}' => 'Get application details',
                    'POST /api/applications/review' => 'Review application (admin only)'
                ],
                'projects' => [
                    'GET /api/projects' => 'List projects',
                    'POST /api/projects/create' => 'Create project (admin)',
                    'GET /api/projects/{id}' => 'Get project details',
                    'PUT /api/projects/{id}' => 'Update project'
                ],
                'mentors' => [
                    'GET /api/mentors' => 'List mentors',
                    'POST /api/mentors/register' => 'Register mentor (admin)',
                    'POST /api/projects/{id}/mentors' => 'Join/leave project'
                ],
                'team' => [
                    'GET /api/projects/{id}/innovators' => 'Get team members',
                    'POST /api/projects/{id}/innovators' => 'Add team member',
                    'DELETE /api/projects/{id}/innovators/{member_id}' => 'Remove team member'
                ]
            ]
        ]);
        exit;
    }
    
    // Route to appropriate handler
    $resource = $routeParts[0];
    
    switch ($resource) {
        case 'applications':
            handleApplicationsAPI($routeParts, $method);
            break;
            
        case 'projects':
            handleProjectsAPI($routeParts, $method);
            break;
            
        case 'mentors':
            handleMentorsAPI($routeParts, $method);
            break;
            
        case 'comments':
            handleCommentsAPI($routeParts, $method);
            break;
            
        case 'resources':
            handleResourcesAPI($routeParts, $method);
            break;
            
        case 'admin':
            handleAdminAPI($routeParts, $method);
            break;
            
        default:
            throw new Exception("Unknown API endpoint: $resource");
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => $e->getCode() ?: 400
    ]);
}

/**
 * Handle Applications API
 */
function handleApplicationsAPI($routeParts, $method) {
    if (count($routeParts) > 1) {
        $action = $routeParts[1];
        
        switch ($action) {
            case 'submit':
                if ($method !== 'POST') {
                    throw new Exception('Method not allowed', 405);
                }
                include __DIR__ . '/applications/submit.php';
                return;
                
            case 'review':
                if ($method !== 'POST') {
                    throw new Exception('Method not allowed', 405);
                }
                include __DIR__ . '/applications/review.php';
                return;
                
            case 'details':
                if ($method !== 'GET') {
                    throw new Exception('Method not allowed', 405);
                }
                $applicationId = $_GET['id'] ?? null;
                if (!$applicationId) {
                    throw new Exception('Application ID is required');
                }
                getApplicationDetails($applicationId);
                return;
        }
    }
    
    throw new Exception('Invalid applications endpoint');
}

/**
 * Handle Projects API
 */
function handleProjectsAPI($routeParts, $method) {
    global $auth;
    
    if (count($routeParts) === 1) {
        // /api/projects
        switch ($method) {
            case 'GET':
                listProjects();
                return;
            case 'POST':
                $auth->requireUserType(USER_TYPE_ADMIN);
                createProject();
                return;
        }
    } elseif (count($routeParts) >= 2) {
        $projectId = (int)$routeParts[1];
        
        if (count($routeParts) === 2) {
            // /api/projects/{id}
            switch ($method) {
                case 'GET':
                    getProjectDetails($projectId);
                    return;
                case 'PUT':
                    updateProject($projectId);
                    return;
                case 'DELETE':
                    $auth->requireUserType(USER_TYPE_ADMIN);
                    deleteProject($projectId);
                    return;
            }
        } elseif (count($routeParts) === 3) {
            $resource = $routeParts[2];
            
            switch ($resource) {
                case 'innovators':
                    handleProjectInnovatorsAPI($projectId, $method);
                    return;
                case 'mentors':
                    handleProjectMentorsAPI($projectId, $method);
                    return;
                case 'comments':
                    handleProjectCommentsAPI($projectId, $method);
                    return;
            }
        }
    }
    
    throw new Exception('Invalid projects endpoint');
}

/**
 * Handle Mentors API
 */
function handleMentorsAPI($routeParts, $method) {
    if (count($routeParts) === 1) {
        // /api/mentors
        switch ($method) {
            case 'GET':
                listMentors();
                return;
            case 'POST':
                registerMentor();
                return;
        }
    }
    
    throw new Exception('Invalid mentors endpoint');
}

/**
 * Handle Comments API
 */
function handleCommentsAPI($routeParts, $method) {
    switch ($method) {
        case 'GET':
            listComments();
            return;
        case 'POST':
            createComment();
            return;
    }
    
    throw new Exception('Invalid comments endpoint');
}

/**
 * Handle Resources API
 */
function handleResourcesAPI($routeParts, $method) {
    switch ($method) {
        case 'GET':
            listResources();
            return;
        case 'POST':
            createResource();
            return;
    }
    
    throw new Exception('Invalid resources endpoint');
}

/**
 * Handle Admin API
 */
function handleAdminAPI($routeParts, $method) {
    global $auth;
    $auth->requireUserType(USER_TYPE_ADMIN);
    
    if (count($routeParts) > 1) {
        $action = $routeParts[1];
        
        switch ($action) {
            case 'stats':
                getSystemStats();
                return;
            case 'export-applications':
                exportApplications();
                return;
        }
    }
    
    throw new Exception('Invalid admin endpoint');
}

// Individual API function implementations will follow...

/**
 * Get application details
 */
function getApplicationDetails($applicationId) {
    global $database, $auth;
    
    $auth->requireUserType(USER_TYPE_ADMIN);
    
    $application = $database->getRow(
        "SELECT * FROM project_applications WHERE application_id = ?",
        [$applicationId]
    );
    
    if (!$application) {
        throw new Exception('Application not found', 404);
    }
    
    echo json_encode([
        'success' => true,
        'data' => $application
    ]);
}

/**
 * List projects
 */
function listProjects() {
    global $database, $auth;
    
    if ($auth->getUserType() === USER_TYPE_PROJECT) {
        // Project users can only see their own project
        $projectId = $auth->getUserId();
        $projects = $database->getRows("SELECT * FROM projects WHERE project_id = ?", [$projectId]);
    } elseif ($auth->getUserType() === USER_TYPE_MENTOR) {
        // Mentors can see projects they're assigned to
        $mentorId = $auth->getUserId();
        $projects = $database->getRows("
            SELECT p.* FROM projects p
            INNER JOIN project_mentors pm ON p.project_id = pm.project_id
            WHERE pm.mentor_id = ? AND pm.is_active = 1
        ", [$mentorId]);
    } else {
        // Admins can see all projects
        $projects = $database->getRows("SELECT * FROM projects ORDER BY created_at DESC");
    }
    
    echo json_encode([
        'success' => true,
        'data' => $projects
    ]);
}

/**
 * Get project details
 */
function getProjectDetails($projectId) {
    global $database, $auth;
    
    // Check access permissions
    if ($auth->getUserType() === USER_TYPE_PROJECT && $auth->getUserId() !== $projectId) {
        throw new Exception('Access denied', 403);
    }
    
    if ($auth->getUserType() === USER_TYPE_MENTOR) {
        $mentorId = $auth->getUserId();
        $hasAccess = $database->getRow("
            SELECT pm_id FROM project_mentors 
            WHERE project_id = ? AND mentor_id = ? AND is_active = 1
        ", [$projectId, $mentorId]);
        
        if (!$hasAccess) {
            throw new Exception('Access denied', 403);
        }
    }
    
    $project = $database->getRow("SELECT * FROM projects WHERE project_id = ?", [$projectId]);
    
    if (!$project) {
        throw new Exception('Project not found', 404);
    }
    
    // Get additional project data
    $project['team_members'] = $database->getRows("
        SELECT * FROM project_innovators 
        WHERE project_id = ? AND is_active = 1
    ", [$projectId]);
    
    $project['mentors'] = $database->getRows("
        SELECT m.*, pm.assigned_at FROM project_mentors pm
        INNER JOIN mentors m ON pm.mentor_id = m.mentor_id
        WHERE pm.project_id = ? AND pm.is_active = 1
    ", [$projectId]);
    
    echo json_encode([
        'success' => true,
        'data' => $project
    ]);
}

/**
 * Handle project innovators (team members)
 */
function handleProjectInnovatorsAPI($projectId, $method) {
    global $auth;
    
    switch ($method) {
        case 'GET':
            getProjectInnovators($projectId);
            break;
        case 'POST':
            addProjectInnovator($projectId);
            break;
        case 'DELETE':
            removeProjectInnovator($projectId);
            break;
        default:
            throw new Exception('Method not allowed', 405);
    }
}

/**
 * Get project team members
 */
function getProjectInnovators($projectId) {
    global $database;
    
    $innovators = $database->getRows("
        SELECT * FROM project_innovators 
        WHERE project_id = ? AND is_active = 1
        ORDER BY added_at ASC
    ", [$projectId]);
    
    echo json_encode([
        'success' => true,
        'data' => $innovators
    ]);
}

/**
 * Add project team member
 */
function addProjectInnovator($projectId) {
    global $database, $auth;
    
    // Validate input
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    $validator = new Validator($input);
    $validator->required('name', 'Name is required')
             ->required('email', 'Email is required')
             ->email('email')
             ->required('role', 'Role is required');
    
    if (!$validator->isValid()) {
        throw new Exception('Validation failed: ' . implode(', ', array_map(function($errors) {
            return implode(', ', $errors);
        }, $validator->getErrors())));
    }
    
    // Check if email already exists in this project
    $existing = $database->getRow("
        SELECT pi_id FROM project_innovators 
        WHERE project_id = ? AND email = ? AND is_active = 1
    ", [$projectId, $input['email']]);
    
    if ($existing) {
        throw new Exception('Team member with this email already exists in the project');
    }
    
    $data = [
        'project_id' => $projectId,
        'name' => trim($input['name']),
        'email' => trim($input['email']),
        'role' => trim($input['role']),
        'level_of_experience' => trim($input['level_of_experience'] ?? ''),
        'added_by_type' => $auth->getUserType(),
        'added_by_id' => $auth->getUserId(),
        'phone' => trim($input['phone'] ?? ''),
        'linkedin_url' => trim($input['linkedin_url'] ?? ''),
        'bio' => trim($input['bio'] ?? '')
    ];
    
    $innovatorId = $database->insert('project_innovators', $data);
    
    if (!$innovatorId) {
        throw new Exception('Failed to add team member');
    }
    
    // Log activity
    logActivity(
        $auth->getUserType(),
        $auth->getUserId(),
        'team_member_added',
        "Added team member: {$data['name']} ({$data['role']}) to project",
        $projectId,
        ['innovator_id' => $innovatorId]
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'Team member added successfully',
        'innovator_id' => $innovatorId
    ]);
}

/**
 * Handle project mentors
 */
function handleProjectMentorsAPI($projectId, $method) {
    switch ($method) {
        case 'POST':
            assignMentorToProject($projectId);
            break;
        default:
            throw new Exception('Method not allowed', 405);
    }
}

/**
 * Assign mentor to project (or remove)
 */
function assignMentorToProject($projectId) {
    global $database, $auth;
    
    $auth->requireUserType(USER_TYPE_MENTOR);
    $mentorId = $auth->getUserId();
    
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? 'join';
    
    if ($action === 'join') {
        // Check if already assigned
        $existing = $database->getRow("
            SELECT pm_id FROM project_mentors 
            WHERE project_id = ? AND mentor_id = ? AND is_active = 1
        ", [$projectId, $mentorId]);
        
        if ($existing) {
            throw new Exception('You are already assigned to this project');
        }
        
        // Assign mentor
        $assignmentId = $database->insert('project_mentors', [
            'project_id' => $projectId,
            'mentor_id' => $mentorId,
            'assigned_by_mentor' => true,
            'notes' => $input['notes'] ?? ''
        ]);
        
        if (!$assignmentId) {
            throw new Exception('Failed to join project');
        }
        
        // Log activity
        logActivity(
            USER_TYPE_MENTOR,
            $mentorId,
            'mentor_joined',
            "Mentor joined project",
            $projectId,
            ['assignment_id' => $assignmentId]
        );
        
        echo json_encode([
            'success' => true,
            'message' => 'Successfully joined project as mentor'
        ]);
        
    } elseif ($action === 'leave') {
        // Remove mentor assignment
        $database->update(
            'project_mentors',
            ['is_active' => 0],
            'project_id = ? AND mentor_id = ?',
            [$projectId, $mentorId]
        );
        
        // Log activity
        logActivity(
            USER_TYPE_MENTOR,
            $mentorId,
            'mentor_left',
            "Mentor left project",
            $projectId
        );
        
        echo json_encode([
            'success' => true,
            'message' => 'Successfully left project'
        ]);
        
    } else {
        throw new Exception('Invalid action. Use "join" or "leave"');
    }
}

/**
 * Get system statistics
 */
function getSystemStats() {
    echo json_encode([
        'success' => true,
        'data' => getSystemStatistics()
    ]);
}

/**
 * Create comment
 */
function createComment() {
    global $database, $auth;
    
    if (!$auth->isValidSession()) {
        throw new Exception('Authentication required', 401);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    $validator = new Validator($input);
    $validator->required('project_id', 'Project ID is required')
             ->required('comment_text', 'Comment text is required');
    
    if (!$validator->isValid()) {
        throw new Exception('Validation failed');
    }
    
    $data = [
        'project_id' => (int)$input['project_id'],
        'commenter_type' => $auth->getUserType(),
        'commenter_name' => $auth->getUserIdentifier(),
        'commenter_id' => $auth->getUserId(),
        'comment_text' => trim($input['comment_text']),
        'parent_comment_id' => !empty($input['parent_comment_id']) ? (int)$input['parent_comment_id'] : null,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
    ];
    
    $commentId = $database->insert('comments', $data);
    
    if (!$commentId) {
        throw new Exception('Failed to create comment');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Comment added successfully',
        'comment_id' => $commentId
    ]);
}
?>