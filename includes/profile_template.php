<?php
// This file serves as a reusable template for user profiles

// Function to display profile page
function displayProfile($userData, $roleSpecificData, $readOnlyFields = []) {
    $profilePic = $userData['profile_pic'] ? "../uploads/profile/" . $userData['profile_pic'] : "../assets/images/default-profile.png";
    
    $html = '
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">My Profile</h1>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-4">
                    <div class="card card-primary card-outline">
                        <div class="card-body box-profile">
                            <div class="text-center">
                                <img class="profile-user-img img-fluid img-circle" 
                                     src="' . htmlspecialchars($profilePic) . '" 
                                     alt="User profile picture">
                            </div>
                            <h3 class="profile-username text-center">' . htmlspecialchars($userData['username']) . '</h3>
                            <p class="text-muted text-center">' . ucfirst($userData['role']) . '</p>
                            
                            <form action="update_profile_picture.php" method="post" enctype="multipart/form-data" class="mt-3">
                                <div class="form-group">
                                    <div class="custom-file">
                                        <input type="file" class="custom-file-input" id="profile_pic" name="profile_pic" accept="image/*">
                                        <label class="custom-file-label" for="profile_pic">Choose file</label>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary btn-block">Update Profile Picture</button>
                            </form>
                        </div>
                    </div>

                    <div class="card card-primary">
                        <div class="card-header">
                            <h3 class="card-title">About Me</h3>
                        </div>
                        <div class="card-body">';
                        
    foreach ($roleSpecificData as $label => $value) {
        $html .= '
            <strong><i class="fas fa-book mr-1"></i> ' . htmlspecialchars($label) . '</strong>
            <p class="text-muted">' . htmlspecialchars($value) . '</p>
            <hr>';
    }

    $html .= '
                        </div>
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header p-2">
                            <ul class="nav nav-pills">
                                <li class="nav-item">
                                    <a class="nav-link active" href="#account" data-toggle="tab">Account Information</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="#security" data-toggle="tab">Security</a>
                                </li>
                            </ul>
                        </div>
                        <div class="card-body">
                            <div class="tab-content">
                                <div class="active tab-pane" id="account">
                                    <form id="updateProfileForm" action="update_profile.php" method="post">
                                        <div class="form-group">
                                            <label for="username">Username</label>
                                            <input type="text" class="form-control" id="username" name="username" 
                                                value="' . htmlspecialchars($userData['username']) . '"
                                                ' . (in_array('username', $readOnlyFields) ? 'readonly' : '') . '>
                                        </div>
                                        <div class="form-group">
                                            <label for="email">Email</label>
                                            <input type="email" class="form-control" id="email" name="email" 
                                                value="' . htmlspecialchars($userData['email']) . '"
                                                ' . (in_array('email', $readOnlyFields) ? 'readonly' : '') . '>
                                        </div>
                                        <div class="form-group">
                                            <label for="contact_number">Contact Number</label>
                                            <input type="text" class="form-control" id="contact_number" name="contact_number" 
                                                value="' . htmlspecialchars($userData['contact_number']) . '"
                                                ' . (in_array('contact_number', $readOnlyFields) ? 'readonly' : '') . '>
                                        </div>
                                        <button type="submit" class="btn btn-primary">Update Profile</button>
                                    </form>
                                </div>

                                <div class="tab-pane" id="security">
                                    <form id="changePasswordForm">
                                        <div class="form-group">
                                            <label for="currentPassword">Current Password</label>
                                            <input type="password" class="form-control" id="currentPassword" name="current_password" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="newPassword">New Password</label>
                                            <input type="password" class="form-control" id="newPassword" name="new_password" 
                                                required minlength="8">
                                            <small class="form-text text-muted">Password must be at least 8 characters long</small>
                                        </div>
                                        <div class="form-group">
                                            <label for="confirmPassword">Confirm New Password</label>
                                            <input type="password" class="form-control" id="confirmPassword" name="confirm_password" required>
                                        </div>
                                        <button type="submit" class="btn btn-danger">Change Password</button>
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
    document.getElementById("changePasswordForm").addEventListener("submit", function(e) {
        e.preventDefault();
        
        const currentPassword = document.getElementById("currentPassword").value;
        const newPassword = document.getElementById("newPassword").value;
        const confirmPassword = document.getElementById("confirmPassword").value;
        
        fetch("change_password.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
            },
            body: `current_password=${encodeURIComponent(currentPassword)}&new_password=${encodeURIComponent(newPassword)}&confirm_password=${encodeURIComponent(confirmPassword)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert("Password changed successfully");
                document.getElementById("changePasswordForm").reset();
            } else {
                alert(data.message);
            }
        })
        .catch(error => {
            alert("An error occurred while changing password");
            console.error("Error:", error);
        });
    });
    </script>';
    
    return $html;
}
?> 