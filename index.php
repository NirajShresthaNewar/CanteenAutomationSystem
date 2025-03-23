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
                <form id="signupForm" action="auth/signup_handler.php" method="POST">
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

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label class="form-label">Full Name</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" name="name" class="form-control" value="<?php echo isset($_SESSION['signup_data']['name']) ? htmlspecialchars($_SESSION['signup_data']['name']) : ''; ?>" required>
                            </div>
                        </div>

                        <div class="col-md-6 mb-4">
                            <label class="form-label">Email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" name="email" class="form-control" value="<?php echo isset($_SESSION['signup_data']['email']) ? htmlspecialchars($_SESSION['signup_data']['email']) : ''; ?>" required>
                            </div>
                        </div>
                    </div>

                    <!-- School Selection -->
                    <div class="mb-4">
                        <label class="form-label">Select School</label>
                        <div class="select-wrapper">
                            <select name="school" class="form-select" required>
                                <option value="">Choose your school</option>
                                <option value="green_valley" <?php echo isset($_SESSION['signup_data']['school']) && $_SESSION['signup_data']['school'] === 'green_valley' ? 'selected' : ''; ?>>Green Valley High School</option>
                                <option value="sunrise" <?php echo isset($_SESSION['signup_data']['school']) && $_SESSION['signup_data']['school'] === 'sunrise' ? 'selected' : ''; ?>>Sunrise Public Academy</option>
                                <option value="mountain_view" <?php echo isset($_SESSION['signup_data']['school']) && $_SESSION['signup_data']['school'] === 'mountain_view' ? 'selected' : ''; ?>>Mountain View College</option>
                                <option value="riverdale" <?php echo isset($_SESSION['signup_data']['school']) && $_SESSION['signup_data']['school'] === 'riverdale' ? 'selected' : ''; ?>>Riverdale Technical Institute</option>
                                <option value="westside" <?php echo isset($_SESSION['signup_data']['school']) && $_SESSION['signup_data']['school'] === 'westside' ? 'selected' : ''; ?>>Westside International School</option>
                            </select>
                            <i class="fas fa-school"></i>
                        </div>
                    </div>

                    <!-- Role Selection -->
                    <div class="mb-4">
                        <label class="form-label">Select Role</label>
                        <select class="form-select" id="mainRole" name="role" required onchange="toggleWorkerRoles()">
                            <option value="">Choose Role</option>
                            <!-- Admin role is not available for signup as it's managed separately -->
                            <option value="staff" <?php echo isset($_SESSION['signup_data']['role']) && $_SESSION['signup_data']['role'] === 'staff' ? 'selected' : ''; ?>>Staff</option>
                            <option value="student" <?php echo isset($_SESSION['signup_data']['role']) && $_SESSION['signup_data']['role'] === 'student' ? 'selected' : ''; ?>>Student</option>
                            <option value="worker" <?php echo isset($_SESSION['signup_data']['role']) && $_SESSION['signup_data']['role'] === 'worker' ? 'selected' : ''; ?>>Worker</option>
                        </select>
                    </div>

                    <div class="mb-4 d-none" id="workerRolesSection">
                        <label class="form-label">Worker Position</label>
                        <select class="form-select" name="worker_position">
                            <option value="kitchen_staff" <?php echo isset($_SESSION['signup_data']['worker_position']) && $_SESSION['signup_data']['worker_position'] === 'kitchen_staff' ? 'selected' : ''; ?>>Kitchen Staff</option>
                            <option value="waiter" <?php echo isset($_SESSION['signup_data']['worker_position']) && $_SESSION['signup_data']['worker_position'] === 'waiter' ? 'selected' : ''; ?>>Waiter</option>
                        </select>
                    </div>

                    <div class="mb-4 position-relative">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" name="password" class="form-control" id="signupPassword" required>
                            <i class="fas fa-eye password-toggle" onclick="togglePassword('signupPassword')"></i>
                        </div>
                        <div id="signupPasswordErrors" class="text-danger mt-1" style="display: none;">
                            <ul class="mb-0 ps-3">
                                <li id="signupPasswordLength">Password must be at least 8 characters long</li>
                                <li id="signupPasswordUpperCase">Must contain at least one uppercase letter</li>
                                <li id="signupPasswordLowerCase">Must contain at least one lowercase letter</li>
                                <li id="signupPasswordNumber">Must contain at least one number</li>
                                <li id="signupPasswordSpecial">Must contain at least one special character</li>
                            </ul>
                        </div>
                    </div>

                    <div class="mb-4 position-relative">
                        <label class="form-label">Confirm Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" name="confirm_password" class="form-control" id="confirmPassword" required>
                        </div>
                        <div id="confirmPasswordError" class="text-danger mt-1" style="display: none;">
                            Passwords do not match
                        </div>
                    </div>

                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" required>
                        <label class="form-check-label">I agree to the Terms & Conditions</label>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Create Account</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/script.js"></script>
</body>
</html>