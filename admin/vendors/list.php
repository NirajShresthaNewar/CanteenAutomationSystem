<?php
$page_title = 'Vendor Management';
ob_start();
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Vendor Management</h1>
            </div>
            <div class="col-sm-6">
                <button type="button" class="btn btn-primary float-right" data-toggle="modal" data-target="#addVendorModal">
                    <i class="bi bi-plus-circle"></i> Add New Vendor
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
                <h3 class="card-title">Vendor List</h3>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Contact</th>
                            <th>Status</th>
                            <th>Rating</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="7" class="text-center">No vendors found</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Vendor Modal -->
<div class="modal fade" id="addVendorModal" tabindex="-1" role="dialog" aria-labelledby="addVendorModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addVendorModalLabel">Add New Vendor</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="addVendorForm">
                    <div class="form-group">
                        <label for="vendorName">Business Name</label>
                        <input type="text" class="form-control" id="vendorName" required>
                    </div>
                    <div class="form-group">
                        <label for="vendorEmail">Email</label>
                        <input type="email" class="form-control" id="vendorEmail" required>
                    </div>
                    <div class="form-group">
                        <label for="vendorContact">Contact Number</label>
                        <input type="tel" class="form-control" id="vendorContact" required>
                    </div>
                    <div class="form-group">
                        <label for="vendorAddress">Address</label>
                        <textarea class="form-control" id="vendorAddress" rows="3" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="vendorType">Vendor Type</label>
                        <select class="form-control" id="vendorType" required>
                            <option value="">Select Type</option>
                            <option value="Food">Food</option>
                            <option value="Beverages">Beverages</option>
                            <option value="Snacks">Snacks</option>
                            <option value="Desserts">Desserts</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="vendorPassword">Password</label>
                        <input type="password" class="form-control" id="vendorPassword" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="saveVendor">Save Vendor</button>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once '../../includes/layout.php';
?> 