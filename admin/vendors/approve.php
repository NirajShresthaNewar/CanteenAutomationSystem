<?php
$page_title = 'Vendor Approval';
ob_start();
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Vendor Approval</h1>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<div class="content">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Pending Vendor Approvals</h3>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Business Name</th>
                            <th>Email</th>
                            <th>Contact</th>
                            <th>Type</th>
                            <th>Submitted Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="7" class="text-center">No pending vendor approvals</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- View Vendor Details Modal -->
<div class="modal fade" id="viewVendorModal" tabindex="-1" role="dialog" aria-labelledby="viewVendorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewVendorModalLabel">Vendor Details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Business Information</h6>
                        <p><strong>Business Name:</strong> <span id="vendorName"></span></p>
                        <p><strong>Email:</strong> <span id="vendorEmail"></span></p>
                        <p><strong>Contact:</strong> <span id="vendorContact"></span></p>
                        <p><strong>Type:</strong> <span id="vendorType"></span></p>
                    </div>
                    <div class="col-md-6">
                        <h6>Address</h6>
                        <p id="vendorAddress"></p>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Documents</h6>
                        <div id="vendorDocuments">
                            <!-- Document links will be added here -->
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger" id="rejectVendor">Reject</button>
                <button type="button" class="btn btn-success" id="approveVendor">Approve</button>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once '../../includes/layout.php';
?> 