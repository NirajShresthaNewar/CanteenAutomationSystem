<?php
$page_title = 'Worker Management';
ob_start();
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Worker Management</h1>
            </div>
            <div class="col-sm-6">
                <button type="button" class="btn btn-primary float-right" data-toggle="modal" data-target="#addWorkerModal">
                    <i class="bi bi-plus-circle"></i> Add New Worker
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
                <h3 class="card-title">Worker List</h3>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Position</th>
                            <th>Shift</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="7" class="text-center">No workers found</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Worker Modal -->
<div class="modal fade" id="addWorkerModal" tabindex="-1" role="dialog" aria-labelledby="addWorkerModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addWorkerModalLabel">Add New Worker</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="addWorkerForm">
                    <div class="form-group">
                        <label for="workerName">Full Name</label>
                        <input type="text" class="form-control" id="workerName" required>
                    </div>
                    <div class="form-group">
                        <label for="workerEmail">Email</label>
                        <input type="email" class="form-control" id="workerEmail" required>
                    </div>
                    <div class="form-group">
                        <label for="workerPosition">Position</label>
                        <select class="form-control" id="workerPosition" required>
                            <option value="">Select Position</option>
                            <option value="Cook">Cook</option>
                            <option value="Cashier">Cashier</option>
                            <option value="Server">Server</option>
                            <option value="Dishwasher">Dishwasher</option>
                            <option value="Cleaner">Cleaner</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="workerShift">Shift</label>
                        <select class="form-control" id="workerShift" required>
                            <option value="">Select Shift</option>
                            <option value="Morning">Morning (6AM - 2PM)</option>
                            <option value="Afternoon">Afternoon (2PM - 10PM)</option>
                            <option value="Night">Night (10PM - 6AM)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="workerPassword">Password</label>
                        <input type="password" class="form-control" id="workerPassword" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="saveWorker">Save Worker</button>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once '../../includes/layout.php';
?> 