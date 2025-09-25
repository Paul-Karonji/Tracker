<?php
// dashboards/mentor/available-projects.php - Browse Available Projects for Mentoring
require_once '../../includes/init.php';

$auth->requireUserType(USER_TYPE_MENTOR);

$mentorId = $auth->getUserId();

// Get mentor information
$mentor = $database->getRow("SELECT * FROM mentors WHERE mentor_id = ?", [$mentorId]);

// Get filter parameters
$stage = $_GET['stage'] ?? '';
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'newest';

// Build query for available projects (not assigned to this mentor)
$whereConditions = ["p.status = 'active'"];
$params = [];

// Exclude projects this mentor is already assigned to
$whereConditions[] = "p.project_id NOT IN (
    SELECT project_id FROM project_mentors 
    WHERE mentor_id = ? AND is_active = 1
)";
$params[] = $mentorId;

if (!empty($stage)) {
    $whereConditions[] = "p.current_stage = ?";
    $params[] = $stage;
}

if (!empty($search)) {
    $whereConditions[] = "(p.project_name LIKE ? OR p.description LIKE ? OR p.target_market LIKE ?)";
    $searchParam = "%{$search}%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
}

$whereClause = implode(' AND ', $whereConditions);

// Define sorting options
$sortOptions = [
    'newest' => 'p.created_at DESC',
    'oldest' => 'p.created_at ASC',
    'name' => 'p.project_name ASC',
    'stage' => 'p.current_stage ASC'
];
$orderBy = $sortOptions[$sort] ?? $sortOptions['newest'];

// Get available projects with additional info
$availableProjects = $database->getRows("
    SELECT p.*,
           COUNT(DISTINCT pi.pi_id) as team_count,
           COUNT(DISTINCT pm.mentor_id) as mentor_count,
           GROUP_CONCAT(DISTINCT LEFT(pi.role, 20) ORDER BY pi.role SEPARATOR ', ') as team_roles
    FROM projects p
    LEFT JOIN project_innovators pi ON p.project_id = pi.project_id AND pi.is_active = 1
    LEFT JOIN project_mentors pm ON p.project_id = pm.project_id AND pm.is_active = 1
    WHERE {$whereClause}
    GROUP BY p.project_id
    ORDER BY {$orderBy}
", $params);

// Get statistics
$stats = [
    'total_available' => count($availableProjects),
    'by_stage' => array_count_values(array_column($availableProjects, 'current_stage')),
    'my_projects' => $database->count('project_mentors', 'mentor_id = ? AND is_active = 1', [$mentorId])
];

$pageTitle = "Available Projects";
$additionalCSS = ['/assets/css/mentor.css'];
include '../../templates/header.php';
?>

<div class="available-projects">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-2">
                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Available Projects</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0">Available Projects</h1>
            <p class="text-muted">Browse and join projects that match your expertise</p>
        </div>
        <div>
            <span class="badge bg-info me-2">
                <?php echo $stats['total_available']; ?> Available
            </span>
            <span class="badge bg-success">
                <?php echo $stats['my_projects']; ?> My Projects
            </span>
        </div>
    </div>

    <!-- Expertise Match Info -->
    <div class="alert alert-info">
        <i class="fas fa-lightbulb me-2"></i>
        <strong>Your Expertise:</strong> <?php echo e($mentor['area_of_expertise']); ?> - 
        Projects matching your expertise are highlighted below!
    </div>

    <!-- Filters and Search -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="GET" class="row align-items-end">
                <div class="col-md-3 mb-2">
                    <label for="stage" class="form-label">Stage</label>
                    <select class="form-control" id="stage" name="stage">
                        <option value="">All Stages</option>
                        <?php for ($i = 1; $i <= 6; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo $stage == $i ? 'selected' : ''; ?>>
                                Stage <?php echo $i; ?> - <?php echo getStageName($i); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="col-md-4 mb-2">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Search projects, descriptions, or markets...">
                </div>
                
                <div class="col-md-3 mb-2">
                    <label for="sort" class="form-label">Sort By</label>
                    <select class="form-control" id="sort" name="sort">
                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                        <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Name A-Z</option>
                        <option value="stage" <?php echo $sort === 'stage' ? 'selected' : ''; ?>>By Stage</option>
                    </select>
                </div>
                
                <div class="col-md-2 mb-2">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search me-1"></i> Filter
                    </button>
                    <a href="available-projects.php" class="btn btn-outline-secondary">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Stage Statistics -->
    <?php if (!empty($stats['by_stage'])): ?>
    <div class="row mb-4">
        <?php for ($stage = 1; $stage <= 6; $stage++): ?>
            <div class="col-md-2 mb-2">
                <div class="card text-center">
                    <div class="card-body py-2">
                        <h6 class="card-title mb-1">Stage <?php echo $stage; ?></h6>
                        <h4 class="text-primary mb-0"><?php echo $stats['by_stage'][$stage] ?? 0; ?></h4>
                        <small class="text-muted"><?php echo getStageName($stage); ?></small>
                    </div>
                </div>
            </div>
        <?php endfor; ?>
    </div>
    <?php endif; ?>

    <!-- Projects Grid -->
    <?php if (empty($availableProjects)): ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-search fa-4x text-muted mb-3"></i>
                <h4>No Available Projects Found</h4>
                <p class="text-muted">
                    <?php if (!empty($search) || !empty($stage)): ?>
                        Try adjusting your search criteria or filters.
                    <?php else: ?>
                        All projects already have mentors assigned or you're already mentoring all available projects.
                    <?php endif; ?>
                </p>
                <?php if (!empty($search) || !empty($stage)): ?>
                    <a href="available-projects.php" class="btn btn-primary">View All Projects</a>
                <?php else: ?>
                    <a href="my-projects.php" class="btn btn-success">View My Projects</a>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($availableProjects as $project): ?>
                <?php
                // Check if project matches mentor's expertise
                $isExpertiseMatch = stripos($project['description'] . ' ' . $project['target_market'], 
                                           $mentor['area_of_expertise']) !== false;
                ?>
                <div class="col-lg-6 col-xl-4 mb-4">
                    <div class="card project-card h-100 <?php echo $isExpertiseMatch ? 'expertise-match' : ''; ?>">
                        <?php if ($isExpertiseMatch): ?>
                            <div class="card-header bg-success text-white py-2">
                                <small><i class="fas fa-star me-1"></i>Matches Your Expertise</small>
                            </div>
                        <?php endif; ?>
                        
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="card-title mb-1"><?php echo e($project['project_name']); ?></h5>
                                <span class="badge bg-primary">Stage <?php echo $project['current_stage']; ?></span>
                            </div>
                            
                            <p class="text-muted mb-2">
                                <i class="fas fa-user me-1"></i><?php echo e($project['project_lead_name']); ?>
                            </p>
                            
                            <p class="card-text"><?php echo e(truncateText($project['description'], 120)); ?></p>
                            
                            <div class="project-meta mb-3">
                                <?php if ($project['target_market']): ?>
                                    <div class="mb-1">
                                        <small class="text-muted">
                                            <i class="fas fa-bullseye me-1"></i>
                                            <strong>Target Market:</strong> <?php echo e($project['target_market']); ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($project['project_website']): ?>
                                    <div class="mb-1">
                                        <small>
                                            <a href="<?php echo e($project['project_website']); ?>" target="_blank" 
                                               class="text-decoration-none">
                                                <i class="fas fa-external-link-alt me-1"></i>
                                                Project Website
                                            </a>
                                        </small>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="d-flex justify-content-between mt-2">
                                    <small class="text-muted">
                                        <i class="fas fa-users me-1"></i>
                                        <?php echo $project['team_count']; ?> Team Members
                                    </small>
                                    <small class="text-muted">
                                        <i class="fas fa-user-tie me-1"></i>
                                        <?php echo $project['mentor_count']; ?> Mentors
                                    </small>
                                </div>
                                
                                <?php if ($project['team_roles']): ?>
                                    <div class="mt-1">
                                        <small class="text-muted">
                                            <strong>Team Roles:</strong> <?php echo e($project['team_roles']); ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="card-footer bg-light">
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    Created: <?php echo formatDate($project['created_at']); ?>
                                </small>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-info" 
                                            onclick="viewProjectDetails(<?php echo $project['project_id']; ?>)">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <button class="btn btn-success btn-join-project" 
                                            data-project-id="<?php echo $project['project_id']; ?>"
                                            data-project-name="<?php echo e($project['project_name']); ?>">
                                        <i class="fas fa-handshake"></i> Join
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Project Details Modal -->
<div class="modal fade" id="projectDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Project Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="projectDetailsContent">
                <!-- Dynamic content loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success" id="joinFromModal">
                    <i class="fas fa-handshake me-1"></i>Join as Mentor
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // Join project buttons
    document.querySelectorAll('.btn-join-project').forEach(btn => {
        btn.addEventListener('click', function() {
            const projectId = this.dataset.projectId;
            const projectName = this.dataset.projectName;
            joinProject(projectId, projectName);
        });
    });
    
    function joinProject(projectId, projectName) {
        if (confirm(`Are you sure you want to join "${projectName}" as a mentor?`)) {
            
            const button = document.querySelector(`[data-project-id="${projectId}"]`);
            const originalText = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Joining...';
            
            fetch(`/api/projects/${projectId}/mentors`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.JHUB?.csrfToken || 'demo'
                },
                body: JSON.stringify({
                    action: 'join'
                })
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    showAlert(result.message, 'success');
                    // Remove the project card or refresh the page
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(result.message, 'danger');
                    button.disabled = false;
                    button.innerHTML = originalText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('An error occurred while joining the project.', 'danger');
                button.disabled = false;
                button.innerHTML = originalText;
            });
        }
    }
    
    window.joinProject = joinProject; // Make globally available
});

function viewProjectDetails(projectId) {
    fetch(`/api/projects/${projectId}`)
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                displayProjectDetails(result.data);
            } else {
                alert('Error loading project details: ' + result.message);
            }
        });
}

function displayProjectDetails(project) {
    const content = document.getElementById('projectDetailsContent');
    
    content.innerHTML = `
        <div class="row">
            <div class="col-md-8">
                <h6>Project Information</h6>
                <table class="table table-sm">
                    <tr><th width="30%">Name:</th><td>${project.project_name}</td></tr>
                    <tr><th>Lead:</th><td>${project.project_lead_name} (${project.project_lead_email})</td></tr>
                    <tr><th>Current Stage:</th><td>Stage ${project.current_stage} - ${getStageName(project.current_stage)}</td></tr>
                    <tr><th>Status:</th><td><span class="badge bg-success">${project.status.toUpperCase()}</span></td></tr>
                    ${project.target_market ? `<tr><th>Target Market:</th><td>${project.target_market}</td></tr>` : ''}
                    ${project.project_website ? `<tr><th>Website:</th><td><a href="${project.project_website}" target="_blank">${project.project_website}</a></td></tr>` : ''}
                </table>
                
                <h6>Description</h6>
                <p class="border p-2">${project.description}</p>
                
                ${project.business_model ? `
                    <h6>Business Model</h6>
                    <p class="border p-2">${project.business_model}</p>
                ` : ''}
            </div>
            <div class="col-md-4">
                <h6>Team Members (${project.team_members.length})</h6>
                <div class="list-group list-group-flush">
                    ${project.team_members.map(member => `
                        <div class="list-group-item p-2">
                            <div class="d-flex align-items-center">
                                <img src="${getGravatar(member.email, 30)}" class="rounded-circle me-2">
                                <div>
                                    <div class="fw-bold">${member.name}</div>
                                    <small class="text-muted">${member.role}</small>
                                </div>
                            </div>
                        </div>
                    `).join('')}
                </div>
                
                <h6 class="mt-3">Current Mentors (${project.mentors.length})</h6>
                ${project.mentors.length ? `
                    <div class="list-group list-group-flush">
                        ${project.mentors.map(mentor => `
                            <div class="list-group-item p-2">
                                <div class="fw-bold">${mentor.name}</div>
                                <small class="text-muted">${mentor.area_of_expertise}</small>
                            </div>
                        `).join('')}
                    </div>
                ` : '<p class="text-muted">No mentors assigned yet</p>'}
            </div>
        </div>
    `;
    
    // Update join button
    const joinButton = document.getElementById('joinFromModal');
    joinButton.onclick = () => {
        bootstrap.Modal.getInstance(document.getElementById('projectDetailsModal')).hide();
        joinProject(project.project_id, project.project_name);
    };
    
    new bootstrap.Modal(document.getElementById('projectDetailsModal')).show();
}

function getStageName(stage) {
    const stages = {
        1: 'Project Creation',
        2: 'Mentorship',
        3: 'Assessment',
        4: 'Learning and Development',
        5: 'Progress Tracking',
        6: 'Showcase and Integration'
    };
    return stages[stage] || 'Unknown';
}

function getGravatar(email, size) {
    // Simple gravatar function for frontend
    return `https://www.gravatar.com/avatar/${btoa(email)}?s=${size}&d=identicon`;
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

<style>
.expertise-match {
    border: 2px solid #28a745 !important;
    box-shadow: 0 0 10px rgba(40, 167, 69, 0.3) !important;
}

.project-card {
    transition: all 0.3s ease;
}

.project-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}
</style>

<?php include '../../templates/footer.php'; ?>