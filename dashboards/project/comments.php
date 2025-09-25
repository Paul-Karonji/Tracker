<?php
// dashboards/project/comments.php - Project Discussion Interface
require_once '../../includes/init.php';

$auth->requireUserType(USER_TYPE_PROJECT);

$projectId = $auth->getUserId();

// Get project information
$project = $database->getRow("
    SELECT * FROM projects 
    WHERE project_id = ?
", [$projectId]);

if (!$project) {
    die("Project not found");
}

// Get comments with pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = COMMENTS_PER_PAGE ?? 20;
$offset = ($page - 1) * $perPage;

// Get main comments (not replies)
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
           END as display_email
    FROM comments c
    LEFT JOIN mentors m ON c.commenter_type = 'mentor' AND c.commenter_id = m.mentor_id
    LEFT JOIN admins a ON c.commenter_type = 'admin' AND c.commenter_id = a.admin_id
    WHERE c.project_id = ? AND c.parent_comment_id IS NULL AND c.is_deleted = 0
    ORDER BY c.created_at DESC
    LIMIT ? OFFSET ?
", [$projectId, $perPage, $offset]);

// Get replies for each comment
foreach ($comments as &$comment) {
    $comment['replies'] = $database->getRows("
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
        WHERE c.project_id = ? AND c.parent_comment_id = ? AND c.is_deleted = 0
        ORDER BY c.created_at ASC
    ", [$projectId, $comment['comment_id']]);
}

// Get comment statistics
$totalComments = $database->count('comments', 'project_id = ? AND is_deleted = 0', [$projectId]);
$totalPages = ceil($totalComments / $perPage);

// Get team members for reference
$teamMembers = $database->getRows("
    SELECT name, email, role FROM project_innovators 
    WHERE project_id = ? AND is_active = 1
    ORDER BY name ASC
", [$projectId]);

$pageTitle = "Project Discussion - " . $project['project_name'];
$additionalCSS = ['/assets/css/project.css', '/assets/css/comments.css'];
$additionalJS = ['/assets/js/comments.js'];
include '../../templates/header.php';
?>

<div class="project-comments">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-2">
                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Discussion</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0">Project Discussion</h1>
            <p class="text-muted">Communicate with your team and mentors</p>
        </div>
        <div>
            <span class="badge bg-info">
                <?php echo $totalComments; ?> Total Comments
            </span>
        </div>
    </div>

    <!-- Project Info Card -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h5 class="mb-1"><?php echo e($project['project_name']); ?></h5>
                    <p class="text-muted mb-1"><?php echo e($project['project_lead_name']); ?> • Stage <?php echo $project['current_stage']; ?></p>
                    <small class="text-muted"><?php echo e(truncateText($project['description'], 100)); ?></small>
                </div>
                <div class="col-md-4">
                    <div class="team-avatars">
                        <small class="text-muted d-block mb-2">Team Members:</small>
                        <?php foreach (array_slice($teamMembers, 0, 5) as $member): ?>
                            <img src="<?php echo getGravatar($member['email'], 32); ?>" 
                                 class="rounded-circle me-1" 
                                 title="<?php echo e($member['name']); ?> (<?php echo e($member['role']); ?>)"
                                 data-bs-toggle="tooltip">
                        <?php endforeach; ?>
                        <?php if (count($teamMembers) > 5): ?>
                            <span class="badge bg-secondary">+<?php echo count($teamMembers) - 5; ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- New Comment Form -->
    <div class="card mb-4">
        <div class="card-header">
            <h6 class="mb-0"><i class="fas fa-comment-alt me-2"></i>Start a New Discussion</h6>
        </div>
        <div class="card-body">
            <form id="newCommentForm">
                <div class="mb-3">
                    <textarea class="form-control" id="commentText" name="comment_text" rows="3" 
                              placeholder="Share updates, ask questions, or provide feedback..." required></textarea>
                    <div class="form-text">
                        <i class="fas fa-info-circle me-1"></i>
                        Your comment will be visible to all team members and mentors assigned to this project.
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <small class="text-muted">
                        Posting as: <strong><?php echo e($auth->getUserIdentifier()); ?></strong> (Project Team)
                    </small>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-1"></i>Post Comment
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Comments List -->
    <?php if (empty($comments)): ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-comments fa-4x text-muted mb-3"></i>
                <h4>No Discussion Yet</h4>
                <p class="text-muted">Start the conversation by posting the first comment!</p>
            </div>
        </div>
    <?php else: ?>
        <div class="comments-container">
            <?php foreach ($comments as $comment): ?>
                <div class="comment-thread mb-4" data-comment-id="<?php echo $comment['comment_id']; ?>">
                    <!-- Main Comment -->
                    <div class="card comment-card">
                        <div class="card-body">
                            <div class="d-flex">
                                <div class="comment-avatar me-3">
                                    <img src="<?php echo getGravatar($comment['display_email'], 48); ?>" 
                                         class="rounded-circle" alt="<?php echo e($comment['display_name']); ?>">
                                </div>
                                <div class="comment-content flex-grow-1">
                                    <div class="comment-header d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <strong class="commenter-name"><?php echo e($comment['display_name']); ?></strong>
                                            <span class="badge bg-<?php echo getCommenterBadgeColor($comment['commenter_type']); ?> ms-2">
                                                <?php echo ucfirst($comment['commenter_type']); ?>
                                            </span>
                                            <div class="comment-meta text-muted">
                                                <small>
                                                    <i class="fas fa-clock me-1"></i>
                                                    <?php echo timeAgo($comment['created_at']); ?>
                                                    <?php if ($comment['is_edited']): ?>
                                                        <span class="text-info">• edited</span>
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                        </div>
                                        <div class="comment-actions">
                                            <button class="btn btn-sm btn-outline-primary btn-reply" 
                                                    data-comment-id="<?php echo $comment['comment_id']; ?>">
                                                <i class="fas fa-reply me-1"></i>Reply
                                            </button>
                                        </div>
                                    </div>
                                    <div class="comment-text">
                                        <?php echo nl2br(e($comment['comment_text'])); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Replies -->
                    <?php if (!empty($comment['replies'])): ?>
                        <div class="replies-container ms-4 mt-3">
                            <?php foreach ($comment['replies'] as $reply): ?>
                                <div class="card reply-card mb-2">
                                    <div class="card-body py-3">
                                        <div class="d-flex">
                                            <div class="reply-avatar me-2">
                                                <img src="<?php echo getGravatar($reply['display_email'], 32); ?>" 
                                                     class="rounded-circle" alt="<?php echo e($reply['display_name']); ?>">
                                            </div>
                                            <div class="reply-content flex-grow-1">
                                                <div class="reply-header mb-1">
                                                    <strong class="reply-author"><?php echo e($reply['display_name']); ?></strong>
                                                    <span class="badge bg-<?php echo getCommenterBadgeColor($reply['commenter_type']); ?> ms-1">
                                                        <?php echo ucfirst($reply['commenter_type']); ?>
                                                    </span>
                                                    <small class="text-muted ms-2">
                                                        <?php echo timeAgo($reply['created_at']); ?>
                                                    </small>
                                                </div>
                                                <div class="reply-text">
                                                    <?php echo nl2br(e($reply['comment_text'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Reply Form (Hidden by default) -->
                    <div class="reply-form-container ms-4 mt-3" style="display: none;" 
                         id="replyForm-<?php echo $comment['comment_id']; ?>">
                        <div class="card">
                            <div class="card-body">
                                <form class="reply-form" data-parent-id="<?php echo $comment['comment_id']; ?>">
                                    <div class="d-flex">
                                        <img src="<?php echo getGravatar($auth->getUserIdentifier() . '@project.local', 32); ?>" 
                                             class="rounded-circle me-2" alt="You">
                                        <div class="flex-grow-1">
                                            <textarea class="form-control mb-2" name="reply_text" rows="2" 
                                                      placeholder="Write your reply..." required></textarea>
                                            <div class="d-flex justify-content-between">
                                                <small class="text-muted">
                                                    Replying as: <strong><?php echo e($auth->getUserIdentifier()); ?></strong>
                                                </small>
                                                <div>
                                                    <button type="button" class="btn btn-sm btn-secondary me-2 btn-cancel-reply">Cancel</button>
                                                    <button type="submit" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-reply me-1"></i>Reply
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="d-flex justify-content-center mt-4">
                <?php echo paginate($page, $totalPages, 'comments.php'); ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Discussion Guidelines -->
    <div class="card mt-4">
        <div class="card-header">
            <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Discussion Guidelines</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6 class="text-success">✓ Best Practices</h6>
                    <ul class="small">
                        <li>Share project updates and milestones</li>
                        <li>Ask specific questions to mentors</li>
                        <li>Provide constructive feedback</li>
                        <li>Tag team members when needed</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h6 class="text-primary">💡 Tips</h6>
                    <ul class="small">
                        <li>Use @mention to notify specific users</li>
                        <li>Include relevant details in questions</li>
                        <li>Follow up on previous discussions</li>
                        <li>Keep conversations professional</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const newCommentForm = document.getElementById('newCommentForm');
    
    // Handle new comment submission
    newCommentForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const data = {
            project_id: <?php echo $projectId; ?>,
            comment_text: formData.get('comment_text')
        };
        
        submitComment(data, this);
    });
    
    // Handle reply buttons
    document.querySelectorAll('.btn-reply').forEach(btn => {
        btn.addEventListener('click', function() {
            const commentId = this.dataset.commentId;
            showReplyForm(commentId);
        });
    });
    
    // Handle reply form submissions
    document.querySelectorAll('.reply-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const data = {
                project_id: <?php echo $projectId; ?>,
                comment_text: formData.get('reply_text'),
                parent_comment_id: this.dataset.parentId
            };
            
            submitComment(data, this, true);
        });
    });
    
    // Handle cancel reply buttons
    document.querySelectorAll('.btn-cancel-reply').forEach(btn => {
        btn.addEventListener('click', function() {
            const replyForm = this.closest('.reply-form-container');
            replyForm.style.display = 'none';
        });
    });
});

function submitComment(data, form, isReply = false) {
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Posting...';
    
    fetch('/api/comments/index.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': window.JHUB?.csrfToken || 'demo'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showAlert('Comment posted successfully!', 'success');
            form.reset();
            
            // Reload page to show new comment
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert(result.message || 'Error posting comment', 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('An error occurred while posting your comment.', 'danger');
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
}

function showReplyForm(commentId) {
    // Hide all other reply forms
    document.querySelectorAll('.reply-form-container').forEach(form => {
        form.style.display = 'none';
    });
    
    // Show the specific reply form
    const replyForm = document.getElementById(`replyForm-${commentId}`);
    replyForm.style.display = 'block';
    
    // Focus on the textarea
    const textarea = replyForm.querySelector('textarea');
    textarea.focus();
}

function showAlert(message, type) {
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    alert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    alert.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(alert);
    
    setTimeout(() => {
        if (alert.parentNode) {
            alert.remove();
        }
    }, 5000);
}
</script>

<?php 
include '../../templates/footer.php';

/**
 * Get badge color for commenter type
 */
function getCommenterBadgeColor($type) {
    $colors = [
        'admin' => 'danger',
        'mentor' => 'success', 
        'project' => 'info',
        'innovator' => 'primary'
    ];
    return $colors[$type] ?? 'secondary';
}
?>