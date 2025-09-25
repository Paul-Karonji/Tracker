<?php
// public/projects.php - Public Project Gallery
require_once '../includes/init.php';

// Get filter parameters
$stage = $_GET['stage'] ?? '';
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'newest';

// Build query for active projects only
$whereConditions = ["status = 'active'"];
$params = [];

if (!empty($stage)) {
    $whereConditions[] = "current_stage = ?";
    $params[] = $stage;
}

if (!empty($search)) {
    $whereConditions[] = "(project_name LIKE ? OR description LIKE ? OR target_market LIKE ?)";
    $searchParam = "%{$search}%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
}

$whereClause = implode(' AND ', $whereConditions);

// Define sorting options
$sortOptions = [
    'newest' => 'created_at DESC',
    'oldest' => 'created_at ASC', 
    'name' => 'project_name ASC',
    'stage' => 'current_stage DESC'
];
$orderBy = $sortOptions[$sort] ?? $sortOptions['newest'];

// Get projects with additional statistics
$projects = $database->getRows("
    SELECT p.*,
           COUNT(DISTINCT pi.pi_id) as team_count,
           COUNT(DISTINCT pm.mentor_id) as mentor_count,
           COUNT(DISTINCT c.comment_id) as comment_count
    FROM projects p
    LEFT JOIN project_innovators pi ON p.project_id = pi.project_id AND pi.is_active = 1
    LEFT JOIN project_mentors pm ON p.project_id = pm.project_id AND pm.is_active = 1
    LEFT JOIN comments c ON p.project_id = c.project_id AND c.is_deleted = 0
    WHERE {$whereClause}
    GROUP BY p.project_id
    ORDER BY {$orderBy}
", $params);

// Get overall statistics
$stats = [
    'total_projects' => count($projects),
    'by_stage' => array_count_values(array_column($projects, 'current_stage')),
    'total_innovators' => array_sum(array_column($projects, 'team_count')),
    'total_mentors' => count(array_unique(array_column($projects, 'mentor_count')))
];

$pageTitle = "Innovation Projects - JHUB AFRICA";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <meta name="description" content="Browse African innovation projects supported by JHUB AFRICA. Discover startups and innovations from across Africa.">
    <meta name="keywords" content="African innovation, startups, technology, entrepreneurship, JHUB AFRICA">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/main.css" rel="stylesheet">
    <link href="../assets/css/public.css" rel="stylesheet">
</head>
<body class="public-body">

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-rocket me-2 text-primary"></i>
                <strong>JHUB AFRICA</strong>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="projects.php">Projects</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="about.php">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contact.php">Contact</a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="../applications/submit.php">
                            <i class="fas fa-plus me-1"></i>Apply
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            Login
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../auth/admin-login.php">Admin</a></li>
                            <li><a class="dropdown-item" href="../auth/mentor-login.php">Mentor</a></li>
                            <li><a class="dropdown-item" href="../auth/project-login.php">Project</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section-small bg-primary text-white py-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="display-5 fw-bold mb-3">African Innovation Projects</h1>
                    <p class="lead">
                        Discover groundbreaking innovations from across Africa. These projects are part of our 
                        comprehensive 6-stage development program, supported by expert mentors and resources.
                    </p>
                </div>
                <div class="col-lg-4">
                    <div class="stats-grid text-center">
                        <div class="stat-item">
                            <h3 class="fw-bold"><?php echo $stats['total_projects']; ?></h3>
                            <small>Active Projects</small>
                        </div>
                        <div class="stat-item">
                            <h3 class="fw-bold"><?php echo $stats['total_innovators']; ?></h3>
                            <small>Innovators</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Filters and Search -->
    <section class="py-4 bg-light">
        <div class="container">
            <form method="GET" class="row align-items-end">
                <div class="col-md-3 mb-2">
                    <label for="stage" class="form-label">Filter by Stage</label>
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
                    <label for="search" class="form-label">Search Projects</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Project name, description, or market...">
                </div>
                
                <div class="col-md-3 mb-2">
                    <label for="sort" class="form-label">Sort By</label>
                    <select class="form-control" id="sort" name="sort">
                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                        <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Name A-Z</option>
                        <option value="stage" <?php echo $sort === 'stage' ? 'selected' : ''; ?>>Advanced Stage First</option>
                    </select>
                </div>
                
                <div class="col-md-2 mb-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-1"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </section>

    <!-- Stage Distribution -->
    <?php if (!empty($stats['by_stage'])): ?>
    <section class="py-3 bg-white border-bottom">
        <div class="container">
            <div class="row text-center">
                <?php for ($stage = 1; $stage <= 6; $stage++): ?>
                    <div class="col-md-2 mb-2">
                        <div class="stage-stat">
                            <div class="stage-number <?php echo ($stats['by_stage'][$stage] ?? 0) > 0 ? 'active' : ''; ?>">
                                <?php echo $stats['by_stage'][$stage] ?? 0; ?>
                            </div>
                            <small class="text-muted">Stage <?php echo $stage; ?></small>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Projects Grid -->
    <main class="py-5">
        <div class="container">
            <?php if (empty($projects)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-search fa-4x text-muted mb-4"></i>
                    <h3>No Projects Found</h3>
                    <p class="text-muted">
                        <?php if (!empty($search) || !empty($stage)): ?>
                            Try adjusting your search criteria or filters.
                        <?php else: ?>
                            No projects are currently available for public viewing.
                        <?php endif; ?>
                    </p>
                    <?php if (!empty($search) || !empty($stage)): ?>
                        <a href="projects.php" class="btn btn-primary">View All Projects</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($projects as $project): ?>
                        <div class="col-lg-4 col-md-6 mb-4">
                            <div class="card project-card h-100">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <span class="badge bg-primary">Stage <?php echo $project['current_stage']; ?></span>
                                    <span class="text-muted">
                                        <?php echo formatDate($project['created_at']); ?>
                                    </span>
                                </div>
                                
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo e($project['project_name']); ?></h5>
                                    
                                    <p class="text-muted mb-2">
                                        <i class="fas fa-user me-1"></i>
                                        Led by <?php echo e($project['project_lead_name']); ?>
                                    </p>
                                    
                                    <p class="card-text"><?php echo e(truncateText($project['description'], 120)); ?></p>
                                    
                                    <?php if ($project['target_market']): ?>
                                        <div class="mb-2">
                                            <small class="text-primary">
                                                <i class="fas fa-bullseye me-1"></i>
                                                <strong>Target:</strong> <?php echo e($project['target_market']); ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($project['project_website']): ?>
                                        <div class="mb-2">
                                            <a href="<?php echo e($project['project_website']); ?>" 
                                               target="_blank" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-external-link-alt me-1"></i>Visit Website
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="card-footer bg-light">
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <div class="stat-item">
                                                <i class="fas fa-users text-info"></i>
                                                <div class="fw-bold"><?php echo $project['team_count']; ?></div>
                                                <small class="text-muted">Team</small>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="stat-item">
                                                <i class="fas fa-user-tie text-success"></i>
                                                <div class="fw-bold"><?php echo $project['mentor_count']; ?></div>
                                                <small class="text-muted">Mentors</small>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="stat-item">
                                                <i class="fas fa-comments text-warning"></i>
                                                <div class="fw-bold"><?php echo $project['comment_count']; ?></div>
                                                <small class="text-muted">Updates</small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="text-center mt-2">
                                        <a href="project-details.php?id=<?php echo $project['project_id']; ?>" 
                                           class="btn btn-primary btn-sm">
                                            <i class="fas fa-eye me-1"></i>View Details
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Load More / Pagination can be added here -->
                
            <?php endif; ?>
        </div>
    </main>

    <!-- Call to Action -->
    <section class="py-5 bg-primary text-white">
        <div class="container text-center">
            <h2 class="mb-3">Have an Innovation Idea?</h2>
            <p class="lead mb-4">Join these amazing projects by applying to our innovation program</p>
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="d-flex justify-content-center gap-3">
                        <a href="../applications/submit.php" class="btn btn-warning btn-lg">
                            <i class="fas fa-paper-plane me-2"></i>Submit Your Project
                        </a>
                        <a href="about.php" class="btn btn-outline-light btn-lg">
                            <i class="fas fa-info-circle me-2"></i>Learn More
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <h5><i class="fas fa-rocket me-2"></i>JHUB AFRICA</h5>
                    <p>Nurturing African innovation through structured mentorship, resources, and community support.</p>
                    <div class="social-links">
                        <a href="#" class="text-white me-3"><i class="fab fa-twitter fa-lg"></i></a>
                        <a href="#" class="text-white me-3"><i class="fab fa-linkedin fa-lg"></i></a>
                        <a href="#" class="text-white me-3"><i class="fab fa-facebook fa-lg"></i></a>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-6 mb-4">
                    <h6>Platform</h6>
                    <ul class="list-unstyled">
                        <li><a href="../index.php" class="text-white-50">Home</a></li>
                        <li><a href="projects.php" class="text-white-50">Projects</a></li>
                        <li><a href="about.php" class="text-white-50">About</a></li>
                        <li><a href="contact.php" class="text-white-50">Contact</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <h6>For Innovators</h6>
                    <ul class="list-unstyled">
                        <li><a href="../applications/submit.php" class="text-white-50">Apply for Program</a></li>
                        <li><a href="../auth/project-login.php" class="text-white-50">Project Login</a></li>
                        <li><a href="#" class="text-white-50">Program Guidelines</a></li>
                        <li><a href="#" class="text-white-50">Success Stories</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-3 mb-4">
                    <h6>For Mentors</h6>
                    <ul class="list-unstyled">
                        <li><a href="../auth/mentor-login.php" class="text-white-50">Mentor Login</a></li>
                        <li><a href="#" class="text-white-50">Become a Mentor</a></li>
                        <li><a href="#" class="text-white-50">Mentor Guidelines</a></li>
                        <li><a href="#" class="text-white-50">Resources</a></li>
                    </ul>
                </div>
            </div>
            
            <hr class="my-4">
            
            <div class="row align-items-center">
                <div class="col-md-6">
                    <small>&copy; <?php echo date('Y'); ?> JHUB AFRICA. All rights reserved.</small>
                </div>
                <div class="col-md-6 text-md-end">
                    <small>
                        <a href="#" class="text-white-50 me-3">Privacy Policy</a>
                        <a href="#" class="text-white-50 me-3">Terms of Service</a>
                        <a href="#" class="text-white-50">Support</a>
                    </small>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <style>
    .hero-section-small {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    
    .stats-grid {
        display: flex;
        gap: 2rem;
        justify-content: center;
    }
    
    .stat-item h3 {
        font-size: 2.5rem;
        margin-bottom: 0;
    }
    
    .project-card {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        border: none;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .project-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }
    
    .stage-stat {
        padding: 1rem;
    }
    
    .stage-number {
        display: inline-block;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: #f8f9fa;
        color: #6c757d;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 1.2rem;
        margin: 0 auto 0.5rem;
    }
    
    .stage-number.active {
        background: #667eea;
        color: white;
    }
    
    .social-links a:hover {
        color: #ffc107 !important;
    }
    
    @media (max-width: 768px) {
        .stats-grid {
            flex-direction: column;
            gap: 1rem;
        }
        
        .stat-item h3 {
            font-size: 2rem;
        }
    }
    </style>
</body>
</html>