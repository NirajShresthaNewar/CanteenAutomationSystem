<?php
// This file serves as a reusable template for user profiles

// Function to display profile page
function displayProfile($userData, $roleSpecificData = [], $readOnly = []) {
    $userRole = ucfirst($userData['role']);
    
    // Get user profile image or default avatar
    $profileImage = !empty($userData['profile_pic']) ? 
        '../uploads/profile/' . $userData['profile_pic'] : 
        'https://via.placeholder.com/150?text=' . substr($userData['username'], 0, 1);
    
    ob_start();
?>

<style>
/* Profile page image styles */
.profile-user-img {
    width: 120px !important;
    height: 120px !important;
    object-fit: cover !important;
    border: 3px solid #adb5bd !important;
    margin: 0 auto !important;
    padding: 3px !important;
    border-radius: 50% !important;
    background-color: #fff !important;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
    display: block !important;
    position: relative !important;
}

.text-center .profile-user-img {
    margin: 0 auto 15px !important;
}

/* Override any conflicting styles */
.profile-user-img.img-circle,
.profile-user-img.img-fluid {
    width: 120px !important;
    height: 120px !important;
    max-width: 120px !important;
    max-height: 120px !important;
    border-radius: 50% !important;
}
</style>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">My Profile</h1>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        <!-- Alert Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php 
                echo $_SESSION['success'];
                unset($_SESSION['success']);
                ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php 
                echo $_SESSION['error'];
                unset($_SESSION['error']);
                ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-4">
                <!-- Profile Image -->
                <div class="card card-primary card-outline">
                    <div class="card-body box-profile">
                        <div class="text-center">
                            <img class="profile-user-img img-fluid img-circle"
                                src="<?php echo $profileImage; ?>"
                                alt="User profile picture"
                                style="width: 120px !important; height: 120px !important; object-fit: cover !important; border: 3px solid #adb5bd !important; margin: 0 auto !important; padding: 3px !important; border-radius: 50% !important; background-color: #fff !important; box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important; display: block !important;">
                        </div>

                        <h3 class="profile-username text-center"><?php echo htmlspecialchars($userData['username']); ?></h3>

                        <p class="text-muted text-center"><?php echo $userRole; ?></p>

                        <form method="POST" action="update_profile.php" enctype="multipart/form-data">
                            <div class="form-group">
                                <label for="profile_image">Update Profile Picture</label>
                                <div class="input-group">
                                    <div class="custom-file">
                                        <input type="file" class="custom-file-input" id="profile_image" name="profile_image" accept="image/*">
                                        <label class="custom-file-label" for="profile_image">Choose file</label>
                                    </div>
                                    <div class="input-group-append">
                                        <button type="submit" name="update_image" class="btn btn-primary">Upload</button>
                                    </div>
                                </div>
                                <small class="text-muted">Max size: 2MB, Formats: JPG, PNG</small>
                            </div>
                        </form>

                        <ul class="list-group list-group-unbordered mb-3">
                            <li class="list-group-item">
                                <b>Member Since</b> <a class="float-right"><?php echo date('M d, Y', strtotime($userData['created_at'])); ?></a>
                            </li>
                            <li class="list-group-item">
                                <b>Status</b> <a class="float-right">
                                    <span class="badge badge-<?php 
                                        echo $userData['approval_status'] == 'approved' ? 'success' : 
                                            ($userData['approval_status'] == 'rejected' ? 'danger' : 'warning'); 
                                    ?>">
                                        <?php echo ucfirst($userData['approval_status']); ?>
                                    </span>
                                </a>
                            </li>
                            <?php 
                            // Display role-specific information if available
                            if (!empty($roleSpecificData)) {
                                foreach ($roleSpecificData as $label => $value) {
                                    echo '<li class="list-group-item">
                                        <b>' . htmlspecialchars($label) . '</b> <a class="float-right">' . htmlspecialchars($value) . '</a>
                                    </li>';
                                }
                            }
                            ?>
                        </ul>
                    </div>
                    <!-- /.card-body -->
                </div>
                <!-- /.card -->
            </div>
            <!-- /.col -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header p-2">
                        <ul class="nav nav-pills">
                            <li class="nav-item"><a class="nav-link active" href="#settings" data-toggle="tab">Account Information</a></li>
                            <li class="nav-item"><a class="nav-link" href="#security" data-toggle="tab">Security</a></li>
                        </ul>
                    </div><!-- /.card-header -->
                    <div class="card-body">
                        <div class="tab-content">
                            <div class="active tab-pane" id="settings">
                                <form class="form-horizontal" method="POST" action="update_profile.php">
                                    <div class="form-group row">
                                        <label for="username" class="col-sm-3 col-form-label">Name</label>
                                        <div class="col-sm-9">
                                            <input type="text" class="form-control" id="username" name="username" 
                                                value="<?php echo htmlspecialchars($userData['username']); ?>"
                                                <?php echo in_array('username', $readOnly) ? 'readonly' : ''; ?>>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="email" class="col-sm-3 col-form-label">Email</label>
                                        <div class="col-sm-9">
                                            <input type="email" class="form-control" id="email" name="email" 
                                                value="<?php echo htmlspecialchars($userData['email']); ?>"
                                                <?php echo in_array('email', $readOnly) ? 'readonly' : ''; ?>>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="contact_number" class="col-sm-3 col-form-label">Contact Number</label>
                                        <div class="col-sm-9">
                                            <input type="text" class="form-control" id="contact_number" name="contact_number" 
                                                value="<?php echo htmlspecialchars($userData['contact_number']); ?>"
                                                <?php echo in_array('contact_number', $readOnly) ? 'readonly' : ''; ?>>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <div class="offset-sm-3 col-sm-9">
                                            <button type="submit" name="update_profile" class="btn btn-primary">Update Information</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            <!-- /.tab-pane -->
                            <div class="tab-pane" id="security">
                                <form class="form-horizontal" method="POST" action="update_profile.php">
                                    <div class="form-group row">
                                        <label for="current_password" class="col-sm-3 col-form-label">Current Password</label>
                                        <div class="col-sm-9">
                                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="new_password" class="col-sm-3 col-form-label">New Password</label>
                                        <div class="col-sm-9">
                                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                                            <small class="text-muted">Password must be at least 8 characters long</small>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="confirm_password" class="col-sm-3 col-form-label">Confirm New Password</label>
                                        <div class="col-sm-9">
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <div class="offset-sm-3 col-sm-9">
                                            <button type="submit" name="update_password" class="btn btn-danger">Change Password</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            <!-- /.tab-pane -->
                        </div>
                        <!-- /.tab-content -->
                    </div><!-- /.card-body -->
                </div>
                <!-- /.card -->
            </div>
            <!-- /.col -->
        </div>
        <!-- /.row -->
    </div><!-- /.container-fluid -->
</section>

<?php
    return ob_get_clean();
}
?> 