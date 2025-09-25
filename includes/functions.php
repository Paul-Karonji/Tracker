<?php
// includes/functions.php
// Common Functions

/**
 * Get stage name
 */
function getStageName($stage) {
    global $STAGE_NAMES;
    return isset($STAGE_NAMES[$stage]) ? $STAGE_NAMES[$stage] : 'Unknown Stage';
}

/**
 * Get stage description
 */
function getStageDescription($stage) {
    global $STAGE_DESCRIPTIONS;
    return isset($STAGE_DESCRIPTIONS[$stage]) ? $STAGE_DESCRIPTIONS[$stage] : '';
}

/**
 * Get stage progress percentage
 */
function getStageProgress($stage) {
    $progressMap = [
        1 => 16.67, // ~17%
        2 => 33.33, // ~33%
        3 => 50.00, // 50%
        4 => 66.67, // ~67%
        5 => 83.33, // ~83%
        6 => 100.00 // 100%
    ];
    
    return isset($progressMap[$stage]) ? $progressMap[$stage] : 0;
}

/**
 * Get all projects for a user
 */
function getUserProjects($userType, $userId) {
    $database = Database::getInstance();
    
    switch ($userType) {
        case 'admin':
            return $database->getRows("
                SELECT p.*, 
                       COUNT(DISTINCT pi.pi_id) as innovator_count,
                       COUNT(DISTINCT pm.mentor_id) as mentor_count
                FROM projects p
                LEFT JOIN project_innovators pi ON p.project_id = pi.project_id AND pi.is_active = 1
                LEFT JOIN project_mentors pm ON p.project_id = pm.project_id AND pm.is_active = 1
                GROUP BY p.project_id
                ORDER BY p.created_at DESC
            ");
            
        case 'mentor':
            return $database->getRows("
                SELECT p.*, pm.assigned_at,
                       COUNT(DISTINCT pi.pi_id) as innovator_count
                FROM projects p
                INNER JOIN project_mentors pm ON p.project_id = pm.project_id
                LEFT JOIN project_innovators pi ON p.project_id = pi.project_id AND pi.is_active = 1
                WHERE pm.mentor_id = ? AND pm.is_active = 1 AND p.status != 'terminated'
                GROUP BY p.project_id
                ORDER BY pm.assigned_at DESC
            ", [$userId]);
            
        case 'project':
            return $database->getRows("
                SELECT p.*,
                       COUNT(DISTINCT pi.pi_id) as innovator_count,
                       COUNT(DISTINCT pm.mentor_id) as mentor_count
                FROM projects p
                LEFT JOIN project_innovators pi ON p.project_id = pi.project_id AND pi.is_active = 1
                LEFT JOIN project_mentors pm ON p.project_id = pm.project_id AND pm.is_active = 1
                WHERE p.project_id = ?
                GROUP BY p.project_id
            ", [$userId]);
    }
    
    return [];
}

/**
 * Get project team members
 */
function getProjectTeam($projectId) {
    $database = Database::getInstance();
    return $database->getRows("
        SELECT * FROM project_innovators 
        WHERE project_id = ? AND is_active = 1 
        ORDER BY added_at ASC
    ", [$projectId]);
}

/**
 * Get project mentors
 */
function getProjectMentors($projectId) {
    $database = Database::getInstance();
    return $database->getRows("
        SELECT m.*, pm.assigned_at, pm.notes
        FROM project_mentors pm
        INNER JOIN mentors m ON pm.mentor_id = m.mentor_id
        WHERE pm.project_id = ? AND pm.is_active = 1 AND m.is_active = 1
        ORDER BY pm.assigned_at ASC
    ", [$projectId]);
}

/**
 * Get project comments
 */
function getProjectComments($projectId, $parentId = null, $limit = 50) {
    $database = Database::getInstance();
    
    $sql = "
        SELECT * FROM comments 
        WHERE project_id = ? AND parent_comment_id " . ($parentId ? "= ?" : "IS NULL") . " 
        AND is_deleted = 0
        ORDER BY created_at ASC
    ";
    
    $params = $parentId ? [$projectId, $parentId] : [$projectId];
    
    if ($limit) {
        $sql .= " LIMIT " . intval($limit);
    }
    
    return $database->getRows($sql, $params);
}

/**
 * Check if mentor is assigned to project
 */
function isMentorAssignedToProject($mentorId, $projectId) {
    $database = Database::getInstance();
    $result = $database->getRow("
        SELECT pm_id FROM project_mentors 
        WHERE mentor_id = ? AND project_id = ? AND is_active = 1
    ", [$mentorId, $projectId]);
    
    return !empty($result);
}

/**
 * Get available projects for mentor assignment
 */
function getAvailableProjectsForMentor($mentorId) {
    $database = Database::getInstance();
    return $database->getRows("
        SELECT p.*, 
               COUNT(DISTINCT pi.pi_id) as innovator_count,
               COUNT(DISTINCT pm.mentor_id) as mentor_count
        FROM projects p
        LEFT JOIN project_innovators pi ON p.project_id = pi.project_id AND pi.is_active = 1
        LEFT JOIN project_mentors pm ON p.project_id = pm.project_id AND pm.is_active = 1
        WHERE p.status = 'active' 
        AND p.project_id NOT IN (
            SELECT project_id FROM project_mentors 
            WHERE mentor_id = ? AND is_active = 1
        )
        GROUP BY p.project_id
        ORDER BY p.created_at DESC
    ", [$mentorId]);
}

/**
 * Get system statistics for admin dashboard
 */
function getSystemStatistics() {
    $database = Database::getInstance();
    
    $stats = [];
    
    // Total projects
    $stats['total_projects'] = $database->count('projects');
    $stats['active_projects'] = $database->count('projects', 'status = ?', ['active']);
    $stats['completed_projects'] = $database->count('projects', 'status = ?', ['completed']);
    $stats['terminated_projects'] = $database->count('projects', 'status = ?', ['terminated']);
    
    // Applications
    $stats['pending_applications'] = $database->count('project_applications', 'status = ?', ['pending']);
    $stats['total_applications'] = $database->count('project_applications');
    
    // Users
    $stats['total_mentors'] = $database->count('mentors', 'is_active = 1');
    $stats['total_innovators'] = $database->count('project_innovators', 'is_active = 1');
    
    // Recent activity
    $stats['projects_this_month'] = $database->count('projects', 'created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)');
    $stats['applications_this_week'] = $database->count('project_applications', 'applied_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)');
    
    return $stats;
}

/**
 * Log activity
 */
function logActivity($userType, $userId, $action, $description, $projectId = null, $additionalData = null) {
    $database = Database::getInstance();
    
    $data = [
        'user_type' => $userType,
        'user_id' => $userId,
        'action' => $action,
        'description' => $description,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ];
    
    if ($projectId) {
        $data['project_id'] = $projectId;
    }
    
    if ($additionalData) {
        $data['additional_data'] = json_encode($additionalData);
    }
    
    return $database->insert('activity_logs', $data);
}

/**
 * Send email notification (placeholder for later implementation)
 */
function sendEmailNotification($to, $subject, $message, $type = 'general') {
    // This will be implemented when we add the email system
    // For now, we'll just log the notification
    
    $database = Database::getInstance();
    return $database->insert('email_notifications', [
        'recipient_email' => $to,
        'subject' => $subject,
        'message_body' => $message,
        'notification_type' => $type,
        'status' => 'pending'
    ]);
}

?>