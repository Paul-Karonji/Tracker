<?php
// dashboards/admin/register-mentor.php - Mentor Registration Interface
require_once '../../includes/init.php';

$auth->requireUserType(USER_TYPE_ADMIN);

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Validator::validateCSRF()) {
        $error = 'Invalid security token. Please try again.';
    } else {
        // Validate form input
        $validator = new Validator($_POST);
        $validator->required('name', 'Name is required')
                 ->required('email', 'Email is required')
                 ->email('email')
                 ->required('password', 'Password is required')
                 ->min('password', 8)
                 ->required('bio', 'Bio is required')
                 ->required('area_of_expertise', 'Area of expertise is required');
        
        if ($validator->isValid()) {
            try {
                // Check if email already exists
                $existingMentor = $database->getRow(
                    "SELECT mentor_id FROM mentors WHERE email = ?",
                    [$_POST['email']]
                );
                
                if ($existingMentor) {
                    $error = 'A mentor with this email address already exists.';
                } else {
                    // Insert new mentor
                    $mentorData = [
                        'name' => trim($_POST['name']),
                        'email' => trim($_POST['email']),
                        'password' => Auth::hashPassword($_POST['password']),
                        'bio' => trim($_POST['bio']),
                        'area_of_expertise' => trim($_POST['area_of_expertise']),
                        'created_by' => $auth->getUserId(),
                        'phone' => trim($_POST['phone'] ?? ''),
                        'linkedin_url' => trim($_POST['linkedin_url'] ?? ''),
                        'years_experience' => !empty($_POST['years_experience']) ? (int)$_POST['years_experience'] : null
                    ];
                    
                    $mentorId = $database->insert('mentors', $mentorData);
                    
                    if ($mentorId) {
                        $success = "Mentor '{$mentorData['name']}' has been successfully registered!";
                        
                        // Log the activity
                        logActivity(
                            USER_TYPE_ADMIN,
                            $auth->getUserId(),
                            'mentor_registered',
                            "New mentor registered: {$mentorData['name']} ({$mentorData['email']})",
                            null,
                            ['mentor_id' => $mentorId]
                        );
                        
                        // Send welcome email
                        if (function_exists('sendEmailNotification')) {
                            $emailSubject = "Welcome to JHUB AFRICA - Mentor Account Created";
                            $emailMessage = createMentorWelcomeEmail($mentorData, $_POST['password']);
                            
                            sendEmailNotification(
                                $mentorData['email'],
                                $emailSubject,
                                $emailMessage,
                                'mentor_welcome'
                            );
                        }
                        
                        // Clear form
                        $_POST = [];
                    } else {
                        $error = 'Failed to register mentor. Please try again.';
                    }
                }
            } catch (Exception $e) {
                $error = 'An error occurred: ' . $e->getMessage();
            }
        } else {
            $error = 'Please correct the errors below.';
        }
    }
}

$pageTitle = "Register Mentor";
$additionalCSS = ['/assets/css/admin.css'];
include '../../templates/header.php';
?>

<div class="mentor-registration">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-2">
                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="mentors.php">Mentors</a></li>
                    <li class="breadcrumb-item active">Register Mentor</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0">Register New Mentor</h1>
            <p class="text-muted">Add a new mentor to the JHUB AFRICA program</p>
        </div>
        <div>
            <a href="mentors.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back to Mentors
            </a>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i><?php echo e($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle me-2"></i><?php echo e($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Registration Form -->
    <div class="card shadow">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-user-plus me-2"></i>Mentor Information
            </h5>
        </div>
        <div class="card-body">
            <form method="POST" class="needs-validation" novalidate>
                <?php echo Validator::csrfInput(); ?>
                
                <!-- Personal Information -->
                <div class="form-section mb-4">
                    <h6 class="text-primary mb-3">
                        <i class="fas fa-user me-2"></i>Personal Information
                    </h6>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">Full Name *</label>
                            <input type="text" class="form-control <?php echo isset($validator) && !empty($validator->getError('name')) ? 'is-invalid' : ''; ?>" 
                                   id="name" name="name" value="<?php echo e($_POST['name'] ?? ''); ?>" required>
                            <div class="invalid-feedback">
                                <?php echo $validator->getFirstError('name') ?? 'Please provide a valid name.'; ?>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email Address *</label>
                            <input type="email" class="form-control <?php echo isset($validator) && !empty($validator->getError('email')) ? 'is-invalid' : ''; ?>" 
                                   id="email" name="email" value="<?php echo e($_POST['email'] ?? ''); ?>" required>
                            <div class="invalid-feedback">
                                <?php echo $validator->getFirstError('email') ?? 'Please provide a valid email address.'; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?php echo e($_POST['phone'] ?? ''); ?>" placeholder="+1234567890">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="linkedin_url" class="form-label">LinkedIn Profile</label>
                            <input type="url" class="form-control" id="linkedin_url" name="linkedin_url" 
                                   value="<?php echo e($_POST['linkedin_url'] ?? ''); ?>" placeholder="https://linkedin.com/in/username">
                        </div>
                    </div>
                </div>

                <!-- Professional Information -->
                <div class="form-section mb-4">
                    <h6 class="text-primary mb-3">
                        <i class="fas fa-briefcase me-2"></i>Professional Information
                    </h6>
                    
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="area_of_expertise" class="form-label">Area of Expertise *</label>
                            <input type="text" class="form-control <?php echo isset($validator) && !empty($validator->getError('area_of_expertise')) ? 'is-invalid' : ''; ?>" 
                                   id="area_of_expertise" name="area_of_expertise" 
                                   value="<?php echo e($_POST['area_of_expertise'] ?? ''); ?>" required
                                   placeholder="e.g., Technology, Marketing, Finance, Product Development">
                            <div class="invalid-feedback">
                                <?php echo $validator->getFirstError('area_of_expertise') ?? 'Please specify the area of expertise.'; ?>
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="years_experience" class="form-label">Years of Experience</label>
                            <input type="number" class="form-control" id="years_experience" name="years_experience" 
                                   value="<?php echo e($_POST['years_experience'] ?? ''); ?>" min="0" max="50">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="bio" class="form-label">Bio/Background *</label>
                        <textarea class="form-control <?php echo isset($validator) && !empty($validator->getError('bio')) ? 'is-invalid' : ''; ?>" 
                                  id="bio" name="bio" rows="4" required
                                  placeholder="Provide a detailed background of the mentor's experience, achievements, and expertise..."><?php echo e($_POST['bio'] ?? ''); ?></textarea>
                        <div class="form-text">This will be visible to project teams when the mentor joins their projects.</div>
                        <div class="invalid-feedback">
                            <?php echo $validator->getFirstError('bio') ?? 'Please provide a bio/background.'; ?>
                        </div>
                    </div>
                </div>

                <!-- Account Setup -->
                <div class="form-section mb-4">
                    <h6 class="text-primary mb-3">
                        <i class="fas fa-key me-2"></i>Account Setup
                    </h6>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Password *</label>
                            <div class="input-group">
                                <input type="password" class="form-control <?php echo isset($validator) && !empty($validator->getError('password')) ? 'is-invalid' : ''; ?>" 
                                       id="password" name="password" required minlength="8">
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="form-text">Minimum 8 characters required.</div>
                            <div class="invalid-feedback">
                                <?php echo $validator->getFirstError('password') ?? 'Password must be at least 8 characters long.'; ?>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="password_confirm" class="form-label">Confirm Password *</label>
                            <input type="password" class="form-control" id="password_confirm" name="password_confirm" 
                                   required minlength="8">
                            <div class="invalid-feedback">Passwords do not match.</div>
                        </div>
                    </div>
                </div>

                <!-- Submit Buttons -->
                <div class="d-flex justify-content-between">
                    <a href="mentors.php" class="btn btn-secondary">
                        <i class="fas fa-times me-1"></i>Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-user-plus me-1"></i>Register Mentor
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Quick Registration Tips -->
    <div class="card mt-4">
        <div class="card-header">
            <h6 class="mb-0">
                <i class="fas fa-lightbulb me-2"></i>Registration Tips
            </h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <h6 class="text-success">✓ Best Practices</h6>
                    <ul class="small">
                        <li>Use professional email addresses</li>
                        <li>Provide comprehensive bio information</li>
                        <li>Include LinkedIn profiles when available</li>
                        <li>Specify clear areas of expertise</li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h6 class="text-info">📧 After Registration</h6>
                    <ul class="small">
                        <li>Welcome email sent automatically</li>
                        <li>Login credentials provided</li>
                        <li>Mentor dashboard access granted</li>
                        <li>Can immediately join projects</li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h6 class="text-warning">⚠️ Important Notes</h6>
                    <ul class="small">
                        <li>Mentors can self-assign to projects</li>
                        <li>Bio information is public to projects</li>
                        <li>Account can be deactivated if needed</li>
                        <li>Password can be reset by mentor</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.needs-validation');
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('password_confirm');
    const togglePassword = document.getElementById('togglePassword');
    
    // Password toggle
    togglePassword.addEventListener('click', function() {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        const icon = this.querySelector('i');
        icon.classList.toggle('fa-eye');
        icon.classList.toggle('fa-eye-slash');
    });
    
    // Password confirmation validation
    confirmPasswordInput.addEventListener('input', function() {
        if (passwordInput.value !== confirmPasswordInput.value) {
            confirmPasswordInput.setCustomValidity('Passwords do not match');
        } else {
            confirmPasswordInput.setCustomValidity('');
        }
    });
    
    passwordInput.addEventListener('input', function() {
        if (confirmPasswordInput.value && passwordInput.value !== confirmPasswordInput.value) {
            confirmPasswordInput.setCustomValidity('Passwords do not match');
        } else {
            confirmPasswordInput.setCustomValidity('');
        }
    });
    
    // Form validation
    form.addEventListener('submit', function(event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        form.classList.add('was-validated');
    });
});
</script>

<?php 
include '../../templates/footer.php';

/**
 * Create mentor welcome email message
 */
function createMentorWelcomeEmail($mentor, $password) {
    $baseUrl = getBaseUrl();
    
    return "
<div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
    <h2 style='color: #28a745;'>Welcome to JHUB AFRICA!</h2>
    
    <p>Dear {$mentor['name']},</p>

    <p>Welcome to the JHUB AFRICA Innovation Program! Your mentor account has been successfully created.</p>

    <div style='background: #f8f9fa; padding: 20px; border-left: 4px solid #28a745; margin: 20px 0;'>
        <h3 style='margin-top: 0; color: #28a745;'>Your Mentor Dashboard Access</h3>
        <p><strong>Login URL:</strong> <a href='{$baseUrl}/auth/mentor-login.php'>{$baseUrl}/auth/mentor-login.php</a></p>
        <p><strong>Email:</strong> <code>{$mentor['email']}</code></p>
        <p><strong>Password:</strong> <code>{$password}</code></p>
        <p><small><em>Please change your password after first login for security.</em></small></p>
    </div>

    <h3>As a JHUB AFRICA Mentor, you can:</h3>
    <ul>
        <li><strong>Browse Available Projects</strong> - View projects looking for mentors</li>
        <li><strong>Join Projects</strong> - Self-assign to projects that match your expertise</li>
        <li><strong>Share Resources</strong> - Provide valuable tools and information to projects</li>
        <li><strong>Create Assessments</strong> - Help projects track their progress</li>
        <li><strong>Set Learning Objectives</strong> - Guide innovators in skill development</li>
        <li><strong>Provide Feedback</strong> - Comment and guide projects through their journey</li>
    </ul>

    <h3>Your Expertise: {$mentor['area_of_expertise']}</h3>
    <p>Projects in your area of expertise will be highlighted in your dashboard, making it easier to find the best matches.</p>

    <div style='background: #e8f4f8; padding: 15px; border-radius: 5px; margin: 20px 0;'>
        <p style='margin: 0;'><strong>Need Help?</strong></p>
        <p style='margin: 5px 0 0 0;'>Contact our support team at <a href='mailto:support@jhubafrica.com'>support@jhubafrica.com</a></p>
    </div>

    <p>Thank you for joining our mission to nurture African innovation!</p>

    <p>Best regards,<br>
    The JHUB AFRICA Team</p>
</div>
";
}
?>