<?php
// dashboards/admin/applications.php - Admin Application Review Interface
require_once '../../includes/init.php';

$auth->requireUserType(USER_TYPE_ADMIN);

// Get filter parameters
$status = $_GET['status'] ?? 'pending';
$search = $_GET['search'] ?? '';

// Build query
$whereConditions = [];
$params = [];

if ($status !== 'all') {
    $whereConditions[] = "status = ?";
    $params[] = $status;
}

if (!empty($search)) {
    $whereConditions[] = "(project_name LIKE ? OR project_lead_name LIKE ? OR project_lead_email LIKE ?)";
    $searchParam = "%{$search}%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get applications
$applications = $database->getRows("
    SELECT *, 
           CASE 
               WHEN status = 'pending' THEN DATEDIFF(NOW(), applied_at)
               ELSE 0
           END as days_pending
    FROM project_applications 
    {$whereClause}
    ORDER BY 
        CASE status 
            WHEN 'pending' THEN 1 
            WHEN 'approved' THEN 2 
            WHEN 'rejected' THEN 3 
        END,
        applied_at DESC
", $params);

// Get statistics
$stats = [
    'pending' => $database->count('project_applications', 'status = ?', ['pending']),
    'approved' => $database->count('project_applications', 'status = ?', ['approved']),
    'rejected' => $database->count('project_applications', 'status = ?', ['rejected']),
    'total' => $database->count('project_applications')
];

$pageTitle = "Application Management";
$additionalCSS = ['/assets/css/admin.css'];
$additionalJS = ['/assets/js/applications.js'];
include '../../templates/header.php';
?>

<div class="applications-management">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Application Management</h1>
            <p class="text-muted">Review and manage project applications</p>
        </div>
        <div>
            <button class="btn btn-primary" onclick="exportApplications()">
                <i class="fas fa-download me-1"></i> Export Data
            </button>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Pending Review
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo $stats['pending']; ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Approved
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo $stats['approved']; ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                Rejected
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo $stats['rejected']; ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-times-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Total Applications
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo $stats['total']; ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-list fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters and Search -->
    <div class="card shadow mb-4">
        <div class="card-header">
            <div class="row align-items-center">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-filter me-2"></i>Filter Applications
                    </h6>
                </div>
            </div>
        </div>
        <div class="card-body">
            <form method="GET" class="row align-items-end">
                <div class="col-md-3 mb-2">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-control" id="status" name="status">
                        <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                <div class="col-md-6 mb-2">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Search by project name, lead name, or email...">
                </div>
                <div class="col-md-3 mb-2">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search me-1"></i> Search
                    </button>
                    <a href="applications.php" class="btn btn-secondary">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Applications List -->
    <div class="card shadow">
        <div class="card-header">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-list me-2"></i>Applications (<?php echo count($applications); ?> found)
            </h6>
        </div>
        <div class="card-body">
            <?php if (empty($applications)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                    <h5>No Applications Found</h5>
                    <p class="text-muted">
                        <?php if (!empty($search) || $status !== 'pending'): ?>
                            Try adjusting your search criteria or filters.
                        <?php else: ?>
                            No applications have been submitted yet.
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Project</th>
                                <th>Project Lead</th>
                                <th>Applied Date</th>
                                <th>Status</th>
                                <th>Days Pending</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($applications as $app): ?>
                            <tr data-application-id="<?php echo $app['application_id']; ?>">
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($app['project_name']); ?></strong>
                                        <?php if ($app['project_website']): ?>
                                            <br><small>
                                                <a href="<?php echo htmlspecialchars($app['project_website']); ?>" 
                                                   target="_blank" class="text-muted">
                                                    <i class="fas fa-external-link-alt me-1"></i>
                                                    <?php echo htmlspecialchars($app['project_website']); ?>
                                                </a>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <?php echo htmlspecialchars($app['project_lead_name']); ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($app['project_lead_email']); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <?php echo date('M d, Y', strtotime($app['applied_at'])); ?>
                                    <br><small class="text-muted"><?php echo date('g:i A', strtotime($app['applied_at'])); ?></small>
                                </td>
                                <td>
                                    <?php
                                    $statusClasses = [
                                        'pending' => 'warning',
                                        'approved' => 'success',
                                        'rejected' => 'danger'
                                    ];
                                    $statusClass = $statusClasses[$app['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $statusClass; ?>">
                                        <?php echo ucfirst($app['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($app['status'] === 'pending'): ?>
                                        <span class="text-<?php echo $app['days_pending'] > 7 ? 'danger' : ($app['days_pending'] > 3 ? 'warning' : 'success'); ?>">
                                            <?php echo $app['days_pending']; ?> days
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary btn-view-application" 
                                                data-id="<?php echo $app['application_id']; ?>"
                                                title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($app['presentation_file']): ?>
                                            <a href="/assets/uploads/presentations/<?php echo htmlspecialchars($app['presentation_file']); ?>" 
                                               target="_blank" class="btn btn-outline-info" title="View Presentation">
                                                <i class="fas fa-file-pdf"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($app['status'] === 'pending'): ?>
                                            <button class="btn btn-outline-success btn-approve-application" 
                                                    data-id="<?php echo $app['application_id']; ?>"
                                                    title="Approve Application">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button class="btn btn-outline-danger btn-reject-application" 
                                                    data-id="<?php echo $app['application_id']; ?>"
                                                    title="Reject Application">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Application Details Modal -->
<div class="modal fade" id="applicationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Application Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="applicationModalBody">
                <!-- Dynamic content loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <div class="application-actions">
                    <!-- Dynamic action buttons -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Rejection Reason Modal -->
<div class="modal fade" id="rejectionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reject Application</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="rejectionForm">
                    <div class="mb-3">
                        <label for="rejectionReason" class="form-label">Reason for Rejection *</label>
                        <textarea class="form-control" id="rejectionReason" name="rejection_reason" 
                                  rows="4" required placeholder="Please provide a clear reason for rejecting this application..."></textarea>
                    </div>
                    <input type="hidden" id="rejectionApplicationId" name="application_id">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmRejection">
                    <i class="fas fa-times me-1"></i>Reject Application
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // View application details
    document.querySelectorAll('.btn-view-application').forEach(btn => {
        btn.addEventListener('click', function() {
            const applicationId = this.dataset.id;
            viewApplicationDetails(applicationId);
        });
    });
    
    // Approve application
    document.querySelectorAll('.btn-approve-application').forEach(btn => {
        btn.addEventListener('click', function() {
            const applicationId = this.dataset.id;
            approveApplication(applicationId);
        });
    });
    
    // Reject application
    document.querySelectorAll('.btn-reject-application').forEach(btn => {
        btn.addEventListener('click', function() {
            const applicationId = this.dataset.id;
            showRejectionModal(applicationId);
        });
    });
    
    // Confirm rejection
    document.getElementById('confirmRejection').addEventListener('click', function() {
        const form = document.getElementById('rejectionForm');
        const formData = new FormData(form);
        
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return;
        }
        
        rejectApplication(formData.get('application_id'), formData.get('rejection_reason'));
    });
    
    function viewApplicationDetails(applicationId) {
        fetch(`/api/applications/details.php?id=${applicationId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayApplicationModal(data.data);
                } else {
                    alert('Error loading application details: ' + data.message);
                }
            });
    }
    
    function displayApplicationModal(app) {
        const modalBody = document.getElementById('applicationModalBody');
        modalBody.innerHTML = `
            <div class="row">
                <div class="col-md-8">
                    <h6>Project Information</h6>
                    <table class="table table-sm">
                        <tr><th>Name:</th><td>${app.project_name}</td></tr>
                        <tr><th>Date:</th><td>${app.date}</td></tr>
                        <tr><th>Email:</th><td>${app.project_email || 'N/A'}</td></tr>
                        <tr><th>Website:</th><td>${app.project_website ? `<a href="${app.project_website}" target="_blank">${app.project_website}</a>` : 'N/A'}</td></tr>
                    </table>
                    
                    <h6>Project Lead</h6>
                    <table class="table table-sm">
                        <tr><th>Name:</th><td>${app.project_lead_name}</td></tr>
                        <tr><th>Email:</th><td>${app.project_lead_email}</td></tr>
                    </table>
                    
                    <h6>Description</h6>
                    <p class="border p-2">${app.description}</p>
                </div>
                <div class="col-md-4">
                    <h6>Application Status</h6>
                    <p><span class="badge bg-${app.status === 'pending' ? 'warning' : (app.status === 'approved' ? 'success' : 'danger')}">${app.status.toUpperCase()}</span></p>
                    
                    <h6>Submitted</h6>
                    <p>${new Date(app.applied_at).toLocaleString()}</p>
                    
                    <h6>Profile Name</h6>
                    <p><code>${app.profile_name}</code></p>
                    
                    ${app.presentation_file ? `
                        <h6>Presentation</h6>
                        <a href="/assets/uploads/presentations/${app.presentation_file}" target="_blank" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-file-pdf me-1"></i>View File
                        </a>
                    ` : ''}
                </div>
            </div>
        `;
        
        new bootstrap.Modal(document.getElementById('applicationModal')).show();
    }
    
    function approveApplication(applicationId) {
        if (confirm('Are you sure you want to approve this application? This will create a new project and send confirmation email.')) {
            fetch('/api/applications/review.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'approve',
                    application_id: applicationId,
                    csrf_token: window.JHUB?.csrfToken || 'demo'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Application approved successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }
    }
    
    function showRejectionModal(applicationId) {
        document.getElementById('rejectionApplicationId').value = applicationId;
        document.getElementById('rejectionReason').value = '';
        document.getElementById('rejectionForm').classList.remove('was-validated');
        new bootstrap.Modal(document.getElementById('rejectionModal')).show();
    }
    
    function rejectApplication(applicationId, reason) {
        fetch('/api/applications/review.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'reject',
                application_id: applicationId,
                rejection_reason: reason,
                csrf_token: window.JHUB?.csrfToken || 'demo'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('rejectionModal')).hide();
                alert('Application rejected successfully.');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
    
});

function exportApplications() {
    window.open('/api/admin/export-applications.php', '_blank');
}
</script>

<?php include '../../templates/footer.php'; ?>