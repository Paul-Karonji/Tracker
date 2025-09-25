<?php
// api/comments/index.php - Comments API Endpoint
require_once '../../includes/init.php';

header('Content-Type: application/json');

if (!$auth->isValidSession()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            getComments();
            break;
            
        case 'POST':
            createComment();
            break;
            
        case 'PUT':
            updateComment();
            break;
            
        case 'DELETE':
            deleteComment();
            break;
            
        default:
            throw new Exception('Method not allowed', 405);
    }
    
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Get comments for a project
 */
function getComments() {
    global $database, $auth;
    
    $projectId = $_GET['project_id'] ?? null;
    $parentId = $_GET['parent_id'] ?? null;
    $limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
    $offset = max(0, (int)($_GET['offset'] ?? 0));
    
    if (!$projectId) {
        throw new Exception('Project ID is required');
    }
    
    // Check access permissions
    if (!hasProjectAccess($projectId)) {
        throw new Exception('Access denied', 403);
    }
    
    // Build query
    $whereConditions = ['project_id = ?', 'is_deleted = 0'];
    $params = [$projectId];
    
    if ($parentId) {
        $whereConditions[] = 'parent_comment_id = ?';
        $params[] = $parentId;
    } else {
        $whereConditions[] = 'parent_comment_id IS NULL';
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // Get comments with author information
    $comments = $database->getRows("
        SELECT c.*, 
               CASE 
                   WHEN c.commenter_type = 'mentor' THEN m.name
                   WHEN c.commenter_type = 'admin' THEN a.admin_name
                   ELSE c.commenter_name
               END as display_name,
               CASE 
                   WHEN c.commenter_type = 'mentor' THEN m.email
                   WHEN c.commenter_type = 'admin' THEN 'admin@jhubafrica.com'
                   ELSE c.commenter_email
               END as display_email,
               CASE 
                   WHEN c.commenter_type = 'mentor' THEN m.area_of_expertise
                   ELSE NULL
               END as author_expertise
        FROM comments c
        LEFT JOIN mentors m ON c.commenter_type = 'mentor' AND c.commenter_id = m.mentor_id
        LEFT JOIN admins a ON c.commenter_type = 'admin' AND c.commenter_id = a.admin_id
        WHERE {$whereClause}
        ORDER BY c.created_at DESC
        LIMIT ? OFFSET ?
    ", array_merge($params, [$limit, $offset]));
    
    // Get reply counts for parent comments
    if (!$parentId) {
        foreach ($comments as &$comment) {
            $comment['reply_count'] = $database->count(
                'comments', 
                'parent_comment_id = ? AND is_deleted = 0', 
                [$comment['comment_id']]
            );
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $comments,
        'total' => $database->count('comments', $whereClause, $params),
        'limit' => $limit,
        'offset' => $offset
    ]);
}

/**
 * Create a new comment
 */
function createComment() {
    global $database, $auth;
    
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
    $validator->required('project_id', 'Project ID is required')
             ->required('comment_text', 'Comment text is required')
             ->min('comment_text', 1, 'Comment cannot be empty')
             ->max('comment_text', 2000, 'Comment is too long (max 2000 characters)');
    
    if (!$validator->isValid()) {
        throw new Exception('Validation failed: ' . implode(', ', array_map(function($errors) {
            return implode(', ', $errors);
        }, $validator->getErrors())));
    }
    
    $projectId = (int)$input['project_id'];
    $parentCommentId = !empty($input['parent_comment_id']) ? (int)$input['parent_comment_id'] : null;
    
    // Check access permissions
    if (!hasProjectAccess($projectId)) {
        throw new Exception('Access denied to this project', 403);
    }
    
    // Validate parent comment if provided
    if ($parentCommentId) {
        $parentComment = $database->getRow(
            "SELECT comment_id FROM comments WHERE comment_id = ? AND project_id = ? AND is_deleted = 0",
            [$parentCommentId, $projectId]
        );
        
        if (!$parentComment) {
            throw new Exception('Parent comment not found');
        }
    }
    
    // Get commenter information based on user type
    $commenterInfo = getCommenterInfo();
    
    // Prepare comment data
    $commentData = [
        'project_id' => $projectId,
        'commenter_type' => $commenterInfo['type'],
        'commenter_name' => $commenterInfo['name'],
        'commenter_email' => $commenterInfo['email'],
        'commenter_id' => $commenterInfo['id'],
        'comment_text' => trim($input['comment_text']),
        'parent_comment_id' => $parentCommentId,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ];
    
    // Insert comment
    $commentId = $database->insert('comments', $commentData);
    
    if (!$commentId) {
        throw new Exception('Failed to create comment');
    }
    
    // Log activity
    logActivity(
        $auth->getUserType(),
        $auth->getUserId(),
        'comment_created',
        $parentCommentId ? "Reply posted on project" : "Comment posted on project",
        $projectId,
        ['comment_id' => $commentId, 'is_reply' => !empty($parentCommentId)]
    );
    
    // Get the created comment with display information
    $createdComment = $database->getRow("
        SELECT c.*, 
               CASE 
                   WHEN c.commenter_type = 'mentor' THEN m.name
                   WHEN c.commenter_type = 'admin' THEN a.admin_name
                   ELSE c.commenter_name
               END as display_name,
               CASE 
                   WHEN c.commenter_type = 'mentor' THEN m.email
                   WHEN c.commenter_type = 'admin' THEN 'admin@jhubafrica.com'
                   ELSE c.commenter_email
               END as display_email
        FROM comments c
        LEFT JOIN mentors m ON c.commenter_type = 'mentor' AND c.commenter_id = m.mentor_id
        LEFT JOIN admins a ON c.commenter_type = 'admin' AND c.commenter_id = a.admin_id
        WHERE c.comment_id = ?
    ", [$commentId]);
    
    echo json_encode([
        'success' => true,
        'message' => $parentCommentId ? 'Reply posted successfully' : 'Comment posted successfully',
        'comment_id' => $commentId,
        'data' => $createdComment
    ]);
}

/**
 * Update an existing comment
 */
function updateComment() {
    global $database, $auth;
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    // Validate CSRF token
    if (!isset($input['csrf_token']) || !$auth->validateCSRFToken($input['csrf_token'])) {
        throw new Exception('Invalid security token');
    }
    
    $commentId = (int)($input['comment_id'] ?? 0);
    $newText = trim($input['comment_text'] ?? '');
    
    if (!$commentId || empty($newText)) {
        throw new Exception('Comment ID and text are required');
    }
    
    // Get comment and verify ownership
    $comment = $database->getRow(
        "SELECT * FROM comments WHERE comment_id = ? AND is_deleted = 0",
        [$commentId]
    );
    
    if (!$comment) {
        throw new Exception('Comment not found', 404);
    }
    
    // Check if user can edit this comment
    if (!canEditComment($comment)) {
        throw new Exception('You can only edit your own comments', 403);
    }
    
    // Update comment
    $updated = $database->update(
        'comments',
        [
            'comment_text' => $newText,
            'is_edited' => 1,
            'updated_at' => date('Y-m-d H:i:s')
        ],
        'comment_id = ?',
        [$commentId]
    );
    
    if (!$updated) {
        throw new Exception('Failed to update comment');
    }
    
    // Log activity
    logActivity(
        $auth->getUserType(),
        $auth->getUserId(),
        'comment_edited',
        "Comment edited on project",
        $comment['project_id'],
        ['comment_id' => $commentId]
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'Comment updated successfully'
    ]);
}

/**
 * Delete a comment (soft delete)
 */
function deleteComment() {
    global $database, $auth;
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    // Validate CSRF token
    if (!isset($input['csrf_token']) || !$auth->validateCSRFToken($input['csrf_token'])) {
        throw new Exception('Invalid security token');
    }
    
    $commentId = (int)($input['comment_id'] ?? 0);
    
    if (!$commentId) {
        throw new Exception('Comment ID is required');
    }
    
    // Get comment and verify permissions
    $comment = $database->getRow(
        "SELECT * FROM comments WHERE comment_id = ? AND is_deleted = 0",
        [$commentId]
    );
    
    if (!$comment) {
        throw new Exception('Comment not found', 404);
    }
    
    // Check if user can delete this comment
    if (!canDeleteComment($comment)) {
        throw new Exception('Insufficient permissions to delete this comment', 403);
    }
    
    // Soft delete comment
    $deleted = $database->update(
        'comments',
        [
            'is_deleted' => 1,
            'updated_at' => date('Y-m-d H:i:s')
        ],
        'comment_id = ?',
        [$commentId]
    );
    
    if (!$deleted) {
        throw new Exception('Failed to delete comment');
    }
    
    // Log activity
    logActivity(
        $auth->getUserType(),
        $auth->getUserId(),
        'comment_deleted',
        "Comment deleted on project",
        $comment['project_id'],
        ['comment_id' => $commentId]
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'Comment deleted successfully'
    ]);
}

/**
 * Check if user has access to project
 */
function hasProjectAccess($projectId) {
    global $auth, $database;
    
    $userType = $auth->getUserType();
    $userId = $auth->getUserId();
    
    switch ($userType) {
        case USER_TYPE_ADMIN:
            return true; // Admins can access all projects
            
        case USER_TYPE_PROJECT:
            return $userId == $projectId; // Projects can access their own
            
        case USER_TYPE_MENTOR:
            // Mentors can access projects they're assigned to
            $assignment = $database->getRow(
                "SELECT pm_id FROM project_mentors WHERE project_id = ? AND mentor_id = ? AND is_active = 1",
                [$projectId, $userId]
            );
            return !empty($assignment);
            
        default:
            return false;
    }
}

/**
 * Get commenter information based on current user
 */
function getCommenterInfo() {
    global $auth, $database;
    
    $userType = $auth->getUserType();
    $userId = $auth->getUserId();
    $userIdentifier = $auth->getUserIdentifier();
    
    switch ($userType) {
        case USER_TYPE_ADMIN:
            return [
                'type' => 'admin',
                'id' => $userId,
                'name' => $userIdentifier,
                'email' => 'admin@jhubafrica.com'
            ];
            
        case USER_TYPE_MENTOR:
            $mentor = $database->getRow("SELECT name, email FROM mentors WHERE mentor_id = ?", [$userId]);
            return [
                'type' => 'mentor',
                'id' => $userId,
                'name' => $mentor['name'] ?? $userIdentifier,
                'email' => $mentor['email'] ?? $userIdentifier
            ];
            
        case USER_TYPE_PROJECT:
            $project = $database->getRow("SELECT project_lead_name, project_lead_email FROM projects WHERE project_id = ?", [$userId]);
            return [
                'type' => 'project',
                'id' => $userId,
                'name' => $project['project_lead_name'] ?? $userIdentifier,
                'email' => $project['project_lead_email'] ?? $userIdentifier
            ];
            
        default:
            throw new Exception('Invalid user type');
    }
}

/**
 * Check if user can edit a comment
 */
function canEditComment($comment) {
    global $auth;
    
    $userType = $auth->getUserType();
    $userId = $auth->getUserId();
    
    // Admins can edit any comment
    if ($userType === USER_TYPE_ADMIN) {
        return true;
    }
    
    // Users can edit their own comments
    return ($comment['commenter_type'] === $userType && $comment['commenter_id'] == $userId);
}

/**
 * Check if user can delete a comment
 */
function canDeleteComment($comment) {
    global $auth;
    
    $userType = $auth->getUserType();
    $userId = $auth->getUserId();
    
    // Admins can delete any comment
    if ($userType === USER_TYPE_ADMIN) {
        return true;
    }
    
    // Project leads can delete comments on their project
    if ($userType === USER_TYPE_PROJECT && $userId == $comment['project_id']) {
        return true;
    }
    
    // Users can delete their own comments
    return ($comment['commenter_type'] === $userType && $comment['commenter_id'] == $userId);
}
?>