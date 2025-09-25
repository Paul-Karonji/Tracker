<?php
// dashboards/project/team.php - Team Management Interface
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

// Get team members
$teamMembers = $database->getRows("
    SELECT * FROM project_innovators 
    WHERE project_id = ? AND is_active = 1
    ORDER BY added_at ASC
", [$projectId]);

// Get team statistics
$teamStats = [
    'total_members' => count($teamMembers),
    'roles' => array_unique(array_column($teamMembers, 'role')),
    'experience_levels' => array_filter(array_unique(array_column($teamMembers, 'level_of_experience')))
];

$pageTitle = "Team Management - " . $project['project_name'];
$additionalCSS = ['/assets/css/project.css'];
$additionalJS = ['/assets/js/team-management.js'];
include '../../templates/header.php';
?>

<div class="team-management">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-2">
                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Team Management</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0">Team Management</h1>
            <p class="text-muted">Manage your project team members</p>
        </div>
        <div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMemberModal">
                <i class="fas fa-user-plus me-1"></i> Add Team Member
            </button>
        </div>
    </div>

    <!-- Team Statistics -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Team Members
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo $teamStats['total_members']; ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Different Roles
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo count($teamStats['roles']); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-tag fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Experience Levels
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo count($teamStats['experience_levels']); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chart-bar fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Team Members List -->
    <div class="card shadow">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-users me-2"></i>Team Members (<?php echo count($teamMembers); ?>)
                </h6>
                <div>
                    <button class="btn btn-sm btn-outline-secondary" onclick="exportTeamData()">
                        <i class="fas fa-download me-1"></i> Export
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($teamMembers)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-users fa-4x text-muted mb-3"></i>
                    <h4>No Team Members Yet</h4>
                    <p class="text-muted">Start building your team by adding members</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMemberModal">
                        <i class="fas fa-plus me-1"></i> Add First Team Member
                    </button>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($teamMembers as $member): ?>
                    <div class="col-lg-6 mb-4">
                        <div class="card team-member-card">
                            <div class="card-body">
                                <div class="d-flex align-items-start">
                                    <div class="avatar-container me-3">
                                        <img src="<?php echo getGravatar($member['email'], 60); ?>" 
                                             class="rounded-circle" alt="<?php echo e($member['name']); ?>">
                                        <?php if ($member['role'] === 'Project Lead'): ?>
                                            <span class="badge bg-warning position-absolute" 
                                                  style="bottom: -5px; right: -5px; font-size: 10px;">LEAD</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1"><?php echo e($member['name']); ?></h6>
                                                <p class="text-primary mb-1"><?php echo e($member['role']); ?></p>
                                                <p class="text-muted mb-2"><?php echo e($member['email']); ?></p>
                                                
                                                <?php if ($member['level_of_experience']): ?>
                                                    <span class="badge bg-secondary me-2">
                                                        <?php echo e($member['level_of_experience']); ?>
                                                    </span>
                                                <?php endif; ?>
                                                
                                                <?php if ($member['phone']): ?>
                                                    <small class="text-muted d-block">
                                                        <i class="fas fa-phone me-1"></i>
                                                        <?php echo e($member['phone']); ?>
                                                    </small>
                                                <?php endif; ?>
                                                
                                                <?php if ($member['linkedin_url']): ?>
                                                    <small class="d-block mt-1">
                                                        <a href="<?php echo e($member['linkedin_url']); ?>" 
                                                           target="_blank" class="text-decoration-none">
                                                            <i class="fab fa-linkedin me-1"></i>LinkedIn Profile
                                                        </a>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                                        type="button" data-bs-toggle="dropdown">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li><a class="dropdown-item" href="#" 
                                                           onclick="editMember(<?php echo $member['pi_id']; ?>)">
                                                        <i class="fas fa-edit me-2"></i>Edit Details
                                                    </a></li>
                                                    <li><a class="dropdown-item" href="#" 
                                                           onclick="viewMemberProfile(<?php echo $member['pi_id']; ?>)">
                                                        <i class="fas fa-user me-2"></i>View Profile
                                                    </a></li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <?php if ($member['role'] !== 'Project Lead'): ?>
                                                    <li><a class="dropdown-item text-danger" href="#" 
                                                           onclick="removeMember(<?php echo $member['pi_id']; ?>, '<?php echo e($member['name']); ?>')">
                                                        <i class="fas fa-trash me-2"></i>Remove Member
                                                    </a></li>
                                                    <?php endif; ?>
                                                </ul>
                                            </div>
                                        </div>
                                        
                                        <?php if ($member['bio']): ?>
                                            <div class="mt-2">
                                                <small class="text-muted">
                                                    <?php echo e(truncateText($member['bio'], 100)); ?>
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                <i class="fas fa-calendar me-1"></i>
                                                Joined: <?php echo formatDate($member['added_at']); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Member Modal -->
<div class="modal fade" id="addMemberModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Team Member</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addMemberForm" class="needs-validation" novalidate>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="memberName" class="form-label">Full Name *</label>
                            <input type="text" class="form-control" id="memberName" name="name" required>
                            <div class="invalid-feedback">Please provide the member's full name.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="memberEmail" class="form-label">Email Address *</label>
                            <input type="email" class="form-control" id="memberEmail" name="email" required>
                            <div class="invalid-feedback">Please provide a valid email address.</div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="memberRole" class="form-label">Role *</label>
                            <input type="text" class="form-control" id="memberRole" name="role" required 
                                   placeholder="e.g., Developer, Designer, Marketing Manager">
                            <div class="invalid-feedback">Please specify the member's role.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="memberExperience" class="form-label">Experience Level</label>
                            <select class="form-control" id="memberExperience" name="level_of_experience">
                                <option value="">Select level...</option>
                                <option value="Beginner">Beginner (0-1 years)</option>
                                <option value="Intermediate">Intermediate (2-4 years)</option>
                                <option value="Advanced">Advanced (5-7 years)</option>
                                <option value="Expert">Expert (8+ years)</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="memberPhone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="memberPhone" name="phone" 
                                   placeholder="+1234567890">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="memberLinkedIn" class="form-label">LinkedIn Profile</label>
                            <input type="url" class="form-control" id="memberLinkedIn" name="linkedin_url" 
                                   placeholder="https://linkedin.com/in/username">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="memberBio" class="form-label">Bio/Description</label>
                        <textarea class="form-control" id="memberBio" name="bio" rows="3" 
                                  placeholder="Brief description of the member's background and expertise..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-user-plus me-1"></i>Add Member
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Member Modal -->
<div class="modal fade" id="editMemberModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Team Member</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editMemberForm" class="needs-validation" novalidate>
                <div class="modal-body" id="editMemberContent">
                    <!-- Dynamic content loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const addMemberForm = document.getElementById('addMemberForm');
    const editMemberForm = document.getElementById('editMemberForm');
    
    // Add member form submission
    addMemberForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (!this.checkValidity()) {
            e.stopPropagation();
            this.classList.add('was-validated');
            return;
        }
        
        const formData = new FormData(this);
        const data = Object.fromEntries(formData.entries());
        
        // Show loading
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Adding...';
        
        fetch(`/api/projects/<?php echo $projectId; ?>/innovators`, {
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
                showAlert(result.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('addMemberModal')).hide();
                setTimeout(() => location.reload(), 1000);
            } else {
                showAlert(result.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('An error occurred while adding the team member.', 'danger');
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });
    });
});

function editMember(memberId) {
    fetch(`/api/projects/<?php echo $projectId; ?>/innovators/${memberId}`)
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                const member = result.data;
                const content = document.getElementById('editMemberContent');
                
                content.innerHTML = `
                    <input type="hidden" name="member_id" value="${member.pi_id}">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Full Name *</label>
                            <input type="text" class="form-control" name="name" value="${member.name}" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email Address *</label>
                            <input type="email" class="form-control" name="email" value="${member.email}" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role *</label>
                            <input type="text" class="form-control" name="role" value="${member.role}" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Experience Level</label>
                            <select class="form-control" name="level_of_experience">
                                <option value="">Select level...</option>
                                <option value="Beginner" ${member.level_of_experience === 'Beginner' ? 'selected' : ''}>Beginner</option>
                                <option value="Intermediate" ${member.level_of_experience === 'Intermediate' ? 'selected' : ''}>Intermediate</option>
                                <option value="Advanced" ${member.level_of_experience === 'Advanced' ? 'selected' : ''}>Advanced</option>
                                <option value="Expert" ${member.level_of_experience === 'Expert' ? 'selected' : ''}>Expert</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" name="phone" value="${member.phone || ''}">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">LinkedIn Profile</label>
                            <input type="url" class="form-control" name="linkedin_url" value="${member.linkedin_url || ''}">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Bio/Description</label>
                        <textarea class="form-control" name="bio" rows="3">${member.bio || ''}</textarea>
                    </div>
                `;
                
                new bootstrap.Modal(document.getElementById('editMemberModal')).show();
            }
        });
}

function removeMember(memberId, memberName) {
    if (confirm(`Are you sure you want to remove ${memberName} from the team?`)) {
        fetch(`/api/projects/<?php echo $projectId; ?>/innovators/${memberId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-Token': window.JHUB?.csrfToken || 'demo'
            }
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                showAlert(result.message, 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showAlert(result.message, 'danger');
            }
        });
    }
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

function exportTeamData() {
    window.open(`/api/projects/<?php echo $projectId; ?>/export-team`, '_blank');
}
</script>

<?php include '../../templates/footer.php'; ?>