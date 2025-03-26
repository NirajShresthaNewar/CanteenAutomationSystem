<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Canteen Automation System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php
session_start();

// Clear the form data after displaying it
if (isset($_SESSION['form_data'])) {
    unset($_SESSION['form_data']);
}

error_reporting(E_ALL);
ini_set('display_errors', 1);
?>

<div class="container">
    <div class="auth-container">
        <div class="auth-header">
            <h1><i class="fas fa-utensils me-2"></i>Canteen Automation System</h1>
        </div>

        <ul class="nav nav-tabs" id="authTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="login-tab" data-bs-toggle="tab" data-bs-target="#login" type="button">Login</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="signup-tab" data-bs-toggle="tab" data-bs-target="#signup" type="button">Sign Up</button>
            </li>
        </ul>

        <div class="tab-content form-container">
            <!-- Login Form -->
            <div class="tab-pane fade show active" id="login" role="tabpanel">
                <form id="loginForm" action="auth/login_handler.php" method="POST" style="height: 100%; display: flex; flex-direction: column;">
                    <?php if (isset($_SESSION['success_message'])): ?>
                        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                            <?php echo $_SESSION['success_message']; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['success_message']); ?>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['login_errors'])): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($_SESSION['login_errors'] as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php unset($_SESSION['login_errors']); ?>
                    <?php endif; ?>

                    <div>
                        <div class="mb-4 position-relative">
                            <label class="form-label">Email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" name="email" class="form-control" value="<?php echo isset($_SESSION['login_email']) ? htmlspecialchars($_SESSION['login_email']) : ''; ?>" required>
                            </div>
                        </div>

                        <div class="mb-4 position-relative">
                            <label class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" name="password" class="form-control" id="loginPassword" required>
                                <i class="fas fa-eye password-toggle" id="eyeIcon" onclick="togglePassword('loginPassword')"></i>
                            </div>
                            <div id="loginPasswordErrors" class="text-danger mt-1" style="display: none;">
                                <ul class="mb-0 ps-3">
                                    <li id="loginPasswordLength">Password must be at least 8 characters long</li>
                                    <li id="loginPasswordUpperCase">Must contain at least one uppercase letter</li>
                                    <li id="loginPasswordLowerCase">Must contain at least one lowercase letter</li>
                                    <li id="loginPasswordNumber">Must contain at least one number</li>
                                    <li id="loginPasswordSpecial">Must contain at least one special character</li>
                                </ul>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="rememberMe" name="remember">
                                <label class="form-check-label" for="rememberMe">Remember me</label>
                            </div>
                            <a href="#forgot-password" class="text-decoration-none" style="color: var(--secondary-color);">Forgot Password?</a>
                        </div>
                    </div>

                    <div class="mt-auto">
                        <button type="submit" class="btn btn-primary w-100 mb-3">Login</button>
                        
                        <div class="login-extras">
                            <div class="features-list">
                                <div class="feature-item">
                                    <i class="fas fa-clock"></i>
                                    <span>24/7 Canteen Access</span>
                                </div>
                                <div class="feature-item">
                                    <i class="fas fa-wallet"></i>
                                    <span>Digital Wallet Integration</span>
                                </div>
                                <div class="feature-item">
                                    <i class="fas fa-bell"></i>
                                    <span>Meal Order Notifications</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Signup Form -->
            <div class="tab-pane fade" id="signup" role="tabpanel">
                <div class="signup-form" id="signupForm">
                    <h2>Create Account</h2>
                    <form action="auth/signup_handler.php" method="POST" id="signupFormElement">
                        <?php if (isset($_SESSION['signup_errors'])): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($_SESSION['signup_errors'] as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php unset($_SESSION['signup_errors']); ?>
                        <?php endif; ?>

                        <!-- Common Fields -->
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Username</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" name="username" class="form-control" value="<?php echo isset($_SESSION['form_data']['username']) ? htmlspecialchars($_SESSION['form_data']['username']) : ''; ?>" required>
                                </div>
                                <div class="invalid-feedback">Please enter a username.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" name="email" class="form-control" value="<?php echo isset($_SESSION['form_data']['email']) ? htmlspecialchars($_SESSION['form_data']['email']) : ''; ?>" required>
                                </div>
                                <div class="invalid-feedback">Please enter a valid email address.</div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Contact Number</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                    <input type="tel" name="contact_number" class="form-control" value="<?php echo isset($_SESSION['form_data']['contact_number']) ? htmlspecialchars($_SESSION['form_data']['contact_number']) : ''; ?>" required>
                                </div>
                                <div class="invalid-feedback">Please enter a contact number.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Role</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user-tag"></i></span>
                                    <select name="role" class="form-select" id="roleSelect" required>
                                        <option value="">Select Role</option>
                                        <option value="vendor" <?php echo (isset($_SESSION['form_data']['role']) && $_SESSION['form_data']['role'] === 'vendor') ? 'selected' : ''; ?>>Vendor</option>
                                        <option value="worker" <?php echo (isset($_SESSION['form_data']['role']) && $_SESSION['form_data']['role'] === 'worker') ? 'selected' : ''; ?>>Worker</option>
                                        <option value="student" <?php echo (isset($_SESSION['form_data']['role']) && $_SESSION['form_data']['role'] === 'student') ? 'selected' : ''; ?>>Student</option>
                                        <option value="staff" <?php echo (isset($_SESSION['form_data']['role']) && $_SESSION['form_data']['role'] === 'staff') ? 'selected' : ''; ?>>Staff</option>
                                    </select>
                                </div>
                                <div class="invalid-feedback">Please select a role.</div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">School</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-school"></i></span>
                                    <!-- Text input for vendors -->
                                    <input type="text" name="school" class="form-control" id="schoolTextInput" required disabled>
                                    <!-- Dropdown for other roles -->
                                    <select name="school" class="form-select" id="schoolDropdown" style="display: none;" required disabled>
                                        <option value="">Select School</option>
                                        <?php
                                        require_once 'connection/db_connection.php';
                                        try {
                                            $stmt = $conn->query("SELECT school_id, name FROM schools ORDER BY name");
                                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                                echo "<option value='" . htmlspecialchars($row['school_id']) . "'>" . htmlspecialchars($row['name']) . "</option>";
                                            }
                                        } catch (PDOException $e) {
                                            echo "<option value=''>Error loading schools</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="invalid-feedback">Please select or enter your school.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Address</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                                    <textarea name="address" class="form-control" required style="height: 38px; resize: none;"><?php echo isset($_SESSION['form_data']['address']) ? htmlspecialchars($_SESSION['form_data']['address']) : ''; ?></textarea>
                                </div>
                                <div class="invalid-feedback">Please enter your address.</div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" name="password" class="form-control" id="signupPassword" required>
                                    <span class="password-toggle-icon" onclick="togglePassword('signupPassword')">
                                        <i class="fas fa-eye"></i>
                                    </span>
                                </div>
                                <div class="password-requirements mt-1" id="passwordRequirements" style="display: none;">
                                    <small class="text-muted">Password must contain:</small>
                                    <ul class="list-unstyled mb-0">
                                        <li id="lengthCheck"><i class="fas fa-times text-danger"></i> At least 8 characters</li>
                                        <li id="uppercaseCheck"><i class="fas fa-times text-danger"></i> One uppercase letter</li>
                                        <li id="lowercaseCheck"><i class="fas fa-times text-danger"></i> One lowercase letter</li>
                                        <li id="numberCheck"><i class="fas fa-times text-danger"></i> One number</li>
                                        <li id="specialCheck"><i class="fas fa-times text-danger"></i> One special character</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Confirm Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" name="confirm_password" class="form-control" id="confirmPassword" required>
                                    <span class="password-toggle-icon" onclick="togglePassword('confirmPassword')">
                                        <i class="fas fa-eye"></i>
                                    </span>
                                </div>
                                <div class="invalid-feedback">Passwords do not match.</div>
                            </div>
                        </div>

                        <!-- Vendor Fields -->
                        <div id="vendorFields" class="role-specific-fields" style="display: none;">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Location</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-map-pin"></i></span>
                                        <input type="text" name="location" class="form-control" value="<?php echo isset($_SESSION['form_data']['location']) ? htmlspecialchars($_SESSION['form_data']['location']) : ''; ?>">
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">License Number (Optional)</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                        <input type="text" name="license_number" class="form-control" value="<?php echo isset($_SESSION['form_data']['license_number']) ? htmlspecialchars($_SESSION['form_data']['license_number']) : ''; ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Worker Fields -->
                        <div id="workerFields" class="role-specific-fields" style="display: none;">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Position</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-briefcase"></i></span>
                                        <select name="position" class="form-select" required>
                                            <option value="">Select Position</option>
                                            <option value="kitchen_staff">Kitchen Staff</option>
                                            <option value="waiter">Waiter</option>
                                        </select>
                                    </div>
                                    <div class="invalid-feedback">Please select a position.</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Vendor ID</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-store"></i></span>
                                        <input type="number" name="vendor_id" class="form-control" min="1" required>
                                    </div>
                                    <div class="invalid-feedback">Please enter a valid vendor ID.</div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Shift Schedule</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-clock"></i></span>
                                        <input type="text" name="shift_schedule" class="form-control" placeholder="e.g., Morning (8AM-4PM)" required>
                                    </div>
                                    <div class="invalid-feedback">Please enter a shift schedule.</div>
                                </div>
                            </div>
                        </div>

                        <!-- Student Fields -->
                        <div id="studentFields" class="role-specific-fields" style="display: none;">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Student Number</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-id-badge"></i></span>
                                        <input type="text" name="student_number" class="form-control" value="<?php echo isset($_SESSION['form_data']['student_number']) ? htmlspecialchars($_SESSION['form_data']['student_number']) : ''; ?>">
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Program</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-graduation-cap"></i></span>
                                        <input type="text" name="program" class="form-control" value="<?php echo isset($_SESSION['form_data']['program']) ? htmlspecialchars($_SESSION['form_data']['program']) : ''; ?>">
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Year of Study</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                        <input type="number" name="year_of_study" class="form-control" value="<?php echo isset($_SESSION['form_data']['year_of_study']) ? htmlspecialchars($_SESSION['form_data']['year_of_study']) : ''; ?>" min="1" max="4">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Staff Fields -->
                        <div id="staffFields" class="role-specific-fields" style="display: none;">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Employee Number</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                        <input type="text" name="employee_number" class="form-control" value="<?php echo isset($_SESSION['form_data']['employee_number']) ? htmlspecialchars($_SESSION['form_data']['employee_number']) : ''; ?>">
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Department</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-building"></i></span>
                                        <input type="text" name="department" class="form-control" value="<?php echo isset($_SESSION['form_data']['department']) ? htmlspecialchars($_SESSION['form_data']['department']) : ''; ?>">
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Office Location</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                                        <input type="text" name="office_location" class="form-control" value="<?php echo isset($_SESSION['form_data']['office_location']) ? htmlspecialchars($_SESSION['form_data']['office_location']) : ''; ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" required id="termsCheck">
                            <label class="form-check-label" for="termsCheck">
                                I agree to the <a href="#" class="text-decoration-none">Terms & Conditions</a>
                            </label>
                            <div class="invalid-feedback">You must agree to the terms and conditions.</div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Sign Up</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/script.js"></script>
<script>
// Password toggle function
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const iconContainer = input.nextElementSibling;
    const icon = iconContainer.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
        iconContainer.classList.add('active');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
        iconContainer.classList.remove('active');
    }
}

// Update the role-specific fields toggle
document.getElementById('roleSelect').addEventListener('change', function() {
    const selectedRole = this.value;
    const allFields = document.querySelectorAll('.role-specific-fields');
    const schoolTextInput = document.getElementById('schoolTextInput');
    const schoolDropdown = document.getElementById('schoolDropdown');

    // Hide all role-specific fields first
    allFields.forEach(field => {
        field.style.display = 'none';
        // Disable all required inputs in hidden fields
        field.querySelectorAll('[required]').forEach(input => {
            input.disabled = true;
        });
    });

    if (selectedRole) {
        const fieldsToShow = document.getElementById(selectedRole + 'Fields');
        if (fieldsToShow) {
            fieldsToShow.style.display = 'block';
            // Enable all required inputs in visible fields
            fieldsToShow.querySelectorAll('[required]').forEach(input => {
                input.disabled = false;
            });
        }

        // Handle school input visibility
        if (selectedRole === 'vendor') {
            schoolTextInput.style.display = 'block';
            schoolDropdown.style.display = 'none';
            schoolTextInput.disabled = false;
            schoolDropdown.disabled = true;
        } else {
            schoolTextInput.style.display = 'none';
            schoolDropdown.style.display = 'block';
            schoolTextInput.disabled = true;
            schoolDropdown.disabled = false;
        }
    } else {
        schoolTextInput.disabled = true;
        schoolDropdown.disabled = true;
    }
});

// Form validation
const form = document.getElementById('signupFormElement');
if (form) {
    form.addEventListener('submit', function(e) {
        if (!this.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
        }
        this.classList.add('was-validated');
    });
}

// Password validation
const signupPassword = document.getElementById('signupPassword');
if (signupPassword) {
    signupPassword.addEventListener('input', function() {
        const requirementsDiv = document.getElementById('passwordRequirements');
        if (requirementsDiv) {
            requirementsDiv.style.display = this.value.length > 0 ? 'block' : 'none';
            // Length check
            document.getElementById('lengthCheck').innerHTML = 
                `<i class="fas fa-${this.value.length >= 8 ? 'check text-success' : 'times text-danger'}"></i> At least 8 characters`;
            
            // Uppercase check
            document.getElementById('uppercaseCheck').innerHTML = 
                `<i class="fas fa-${/[A-Z]/.test(this.value) ? 'check text-success' : 'times text-danger'}"></i> One uppercase letter`;
            
            // Lowercase check
            document.getElementById('lowercaseCheck').innerHTML = 
                `<i class="fas fa-${/[a-z]/.test(this.value) ? 'check text-success' : 'times text-danger'}"></i> One lowercase letter`;
            
            // Number check
            document.getElementById('numberCheck').innerHTML = 
                `<i class="fas fa-${/[0-9]/.test(this.value) ? 'check text-success' : 'times text-danger'}"></i> One number`;
            
            // Special character check
            document.getElementById('specialCheck').innerHTML = 
                `<i class="fas fa-${/[!@#$%^&*(),.?":{}|<>]/.test(this.value) ? 'check text-success' : 'times text-danger'}"></i> One special character`;
        }
    });
}

// Password match validation
document.getElementById('confirmPassword').addEventListener('input', function() {
    const password = document.getElementById('signupPassword').value;
    const confirmPassword = this.value;
    const feedback = this.nextElementSibling;
    
    if (password !== confirmPassword) {
        this.classList.add('is-invalid');
        feedback.style.display = 'block';
    } else {
        this.classList.remove('is-invalid');
        feedback.style.display = 'none';
    }
});
</script>

<style>
.password-toggle-icon {
    display: flex;
    align-items: center;
    padding: 0 10px;
    cursor: pointer;
    color: #6c757d;
    transition: color 0.2s ease-in-out;
    border-left: 1px solid #ced4da;
}
.password-toggle-icon:hover {
    color: #0d6efd;
}
.password-toggle-icon i {
    font-size: 1rem;
}
</style>
</body>
</html>