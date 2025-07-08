<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

// Get user data from database
try {
    // Fetch user info with school details
    $stmt = $conn->prepare("
        SELECT 
            u.*,
            ss.school_id,
            s.name as school_name,
            s.address as school_address,
            ss.role as student_role,
            ss.approval_status as student_status
        FROM users u
        JOIN staff_students ss ON u.id = ss.user_id
        JOIN schools s ON ss.school_id = s.id
        WHERE u.id = ? AND u.role = 'student'
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$userData) {
        $_SESSION['error'] = "Student information not found";
        header('Location: dashboard.php');
        exit();
    }
    
    $page_title = 'My Profile';
    ob_start();
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">My Profile</h1>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<div class="content">
    <div class="container-fluid">
        <div class="row">
            <!-- Profile Information -->
            <div class="col-md-4">
                <div class="card card-primary card-outline">
                    <div class="card-body box-profile">
                        <div class="text-center">
                            <img class="profile-user-img img-fluid img-circle" 
                                src="<?php echo $userData['profile_pic'] ? '../uploads/profile/' . $userData['profile_pic'] : '../assets/images/default-profile.png'; ?>" 
                                alt="User profile picture">
                        </div>
                        <h3 class="profile-username text-center"><?php echo htmlspecialchars($userData['username']); ?></h3>
                        <p class="text-muted text-center">Student</p>

                        <ul class="list-group list-group-unbordered mb-3">
                            <li class="list-group-item">
                                <b>School</b> <a class="float-right"><?php echo htmlspecialchars($userData['school_name']); ?></a>
                            </li>
                            <li class="list-group-item">
                                <b>Email</b> <a class="float-right"><?php echo htmlspecialchars($userData['email']); ?></a>
                            </li>
                            <li class="list-group-item">
                                <b>Contact</b> <a class="float-right"><?php echo htmlspecialchars($userData['contact_number']); ?></a>
                            </li>
                            <li class="list-group-item">
                                <b>Status</b> 
                                <span class="float-right badge badge-<?php echo $userData['student_status'] === 'approved' ? 'success' : 'warning'; ?>">
                                    <?php echo ucfirst($userData['student_status']); ?>
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Settings Tabs -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header p-2">
                        <ul class="nav nav-pills">
                            <li class="nav-item">
                                <a class="nav-link active" href="#settings" data-toggle="tab">Settings</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#security" data-toggle="tab">Security</a>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content">
                            <!-- Settings Tab -->
                            <div class="active tab-pane" id="settings">
                                <form class="form-horizontal" id="updateProfileForm" method="POST" action="update_profile.php" enctype="multipart/form-data">
                                    <div class="form-group row">
                                        <label for="username" class="col-sm-2 col-form-label">Username</label>
                                        <div class="col-sm-10">
                                            <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($userData['username']); ?>">
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="email" class="col-sm-2 col-form-label">Email</label>
                                        <div class="col-sm-10">
                                            <input type="email" class="form-control" id="email" value="<?php echo htmlspecialchars($userData['email']); ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="contact" class="col-sm-2 col-form-label">Contact</label>
                                        <div class="col-sm-10">
                                            <input type="text" class="form-control" id="contact" name="contact" value="<?php echo htmlspecialchars($userData['contact_number']); ?>">
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="profile_pic" class="col-sm-2 col-form-label">Profile Picture</label>
                                        <div class="col-sm-10">
                                            <div class="custom-file">
                                                <input type="file" class="custom-file-input" id="profile_pic" name="profile_pic">
                                                <label class="custom-file-label" for="profile_pic">Choose file</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <div class="offset-sm-2 col-sm-10">
                                            <button type="submit" class="btn btn-primary">Update Profile</button>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            <!-- Security Tab -->
                            <div class="tab-pane" id="security">
                                <form class="form-horizontal" id="changePasswordForm">
                                    <div class="form-group row">
                                        <label for="currentPassword" class="col-sm-3 col-form-label">Current Password</label>
                                        <div class="col-sm-9">
                                            <input type="password" class="form-control" id="currentPassword" name="current_password" required>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="newPassword" class="col-sm-3 col-form-label">New Password</label>
                                        <div class="col-sm-9">
                                            <input type="password" class="form-control" id="newPassword" name="new_password" required>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="confirmPassword" class="col-sm-3 col-form-label">Confirm Password</label>
                                        <div class="col-sm-9">
                                            <input type="password" class="form-control" id="confirmPassword" name="confirm_password" required>
                                            <div class="invalid-feedback">Passwords do not match</div>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <div class="offset-sm-3 col-sm-9">
                                            <button type="submit" class="btn btn-primary">Change Password</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

    <script>
        $(document).ready(function() {
    // File input handler
            $(".custom-file-input").on("change", function() {
        var fileName = $(this).val().split("\\").pop();
                $(this).siblings(".custom-file-label").addClass("selected").html(fileName);
            });
            
    // Password change form handler
    $("#changePasswordForm").on("submit", function(e) {
        e.preventDefault();
        
        const currentPassword = $("#currentPassword").val();
        const newPassword = $("#newPassword").val();
        const confirmPassword = $("#confirmPassword").val();
        
        if (newPassword !== confirmPassword) {
            $("#confirmPassword").addClass("is-invalid");
            return;
        }
        
        fetch('change_password.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `current_password=${encodeURIComponent(currentPassword)}&new_password=${encodeURIComponent(newPassword)}&confirm_password=${encodeURIComponent(confirmPassword)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: data.message
                }).then(() => {
                    $("#changePasswordForm")[0].reset();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to change password. Please try again.'
            });
        });
    });

    // Profile update form handler
    $("#updateProfileForm").on("submit", function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        fetch('update_profile.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: data.message
                }).then(() => {
                    window.location.reload();
                });
                } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to update profile. Please try again.'
            });
        });
            });
        });
    </script>
    
<?php
    $content = ob_get_clean();
    require_once '../includes/layout.php';
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    header('Location: dashboard.php');
    exit();
}
?> 