<?php
$page_title = 'Transaction Management';
ob_start();
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Transaction Management</h1>
            </div>
            <div class="col-sm-6">
                <div class="float-right">
                    <button type="button" class="btn btn-success mr-2" id="exportTransactions">
                        <i class="bi bi-download"></i> Export
                    </button>
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#filterModal">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<div class="content">
    <div class="container-fluid">
        <!-- Summary Cards -->
        <div class="row">
            <div class="col-lg-3 col-6">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3>₱0.00</h3>
                        <p>Today's Revenue</p>
                    </div>
                    <div class="icon">
                        <i class="bi bi-cash"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3>0</h3>
                        <p>Today's Orders</p>
                    </div>
                    <div class="icon">
                        <i class="bi bi-cart"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3>₱0.00</h3>
                        <p>Pending Payments</p>
                    </div>
                    <div class="icon">
                        <i class="bi bi-clock"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h3>₱0.00</h3>
                        <p>Refunds</p>
                    </div>
                    <div class="icon">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transactions Table -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Transaction History</h3>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Transaction ID</th>
                            <th>Date</th>
                            <th>User</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="7" class="text-center">No transactions found</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Filter Modal -->
<div class="modal fade" id="filterModal" tabindex="-1" role="dialog" aria-labelledby="filterModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="filterModalLabel">Filter Transactions</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="filterForm">
                    <div class="form-group">
                        <label for="dateRange">Date Range</label>
                        <div class="input-group">
                            <input type="date" class="form-control" id="startDate">
                            <div class="input-group-append">
                                <span class="input-group-text">to</span>
                            </div>
                            <input type="date" class="form-control" id="endDate">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="transactionType">Transaction Type</label>
                        <select class="form-control" id="transactionType">
                            <option value="">All Types</option>
                            <option value="order">Order</option>
                            <option value="refund">Refund</option>
                            <option value="wallet">Wallet</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="transactionStatus">Status</label>
                        <select class="form-control" id="transactionStatus">
                            <option value="">All Status</option>
                            <option value="completed">Completed</option>
                            <option value="pending">Pending</option>
                            <option value="failed">Failed</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="applyFilter">Apply Filter</button>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once '../../includes/layout.php';
?> 