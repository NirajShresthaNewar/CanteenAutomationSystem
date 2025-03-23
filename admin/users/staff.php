<?php
$page_title = 'Staff Management';
ob_start();
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Staff Management</h1>
            </div>
            <div class="col-sm-6">
                <button type="button" class="btn btn-primary float-right" data-toggle="modal" data-target="#addStaffModal">
                    <i class="bi bi-plus-circle"></i> Add New Staff
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<div class="content">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Staff List</h3>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Department</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="6" class="text-center">No staff members found</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Staff Modal -->
<div class="modal fade" id="addStaffModal" tabindex="-1" role="dialog" aria-labelledby="addStaffModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addStaffModalLabel">Add New Staff</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="addStaffForm">
                    <div class="form-group">
                        <label for="staffName">Full Name</label>
                        <input type="text" class="form-control" id="staffName" required>
                    </div>
                    <div class="form-group">
                        <label for="staffEmail">Email</label>
                        <input type="email" class="form-control" id="staffEmail" required>
                    </div>
                    <div class="form-group">
                        <label for="staffDepartment">Department</label>
                        <select class="form-control" id="staffDepartment" required>
                            <option value="">Select Department</option>
                            <option value="IT">IT</option>
                            <option value="HR">HR</option>
                            <option value="Finance">Finance</option>
                            <option value="Marketing">Marketing</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="staffPassword">Password</label>
                        <input type="password" class="form-control" id="staffPassword" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="saveStaff">Save Staff</button>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once '../../includes/layout.php';
?> 