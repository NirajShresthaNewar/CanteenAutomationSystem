<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $name = trim($_POST['name']);
        $address = trim($_POST['address']);

        if (empty($name) || empty($address)) {
            throw new Exception("School name and address are required");
        }

        $stmt = $conn->prepare("INSERT INTO schools (name, address) VALUES (?, ?)");
        $stmt->execute([$name, $address]);

        $_SESSION['success'] = "School added successfully!";
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
}

// Start output buffering
ob_start();
?>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="row">
            <!-- Form Card -->
            <div class="col-md-6">
                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class="card-title">Add New School</h3>
                    </div>

                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show m-3" role="alert">
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
                        <div class="alert alert-danger alert-dismissible fade show m-3" role="alert">
                            <?php 
                            echo $_SESSION['error'];
                            unset($_SESSION['error']);
                            ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="form-horizontal">
                        <div class="card-body">
                            <div class="form-group">
                                <label for="name">School Name *</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       required placeholder="Enter school name">
                            </div>
                            <div class="form-group">
                                <label for="address">School Address *</label>
                                <textarea class="form-control" 
                                          id="address" 
                                          name="address" 
                                          required 
                                          rows="3" 
                                          placeholder="Enter school address"
                                          style="resize: none; /* Prevent manual resizing */
                                                 height: 100px; /* Fixed height */
                                                 overflow-y: auto; /* Add scrollbar when needed */
                                                 padding: 10px; /* Better padding */
                                                 line-height: 1.5; /* Better line spacing */
                                                 font-size: 14px; /* Consistent font size */"
                                          maxlength="255" /* Match database varchar length */
                                          ></textarea>
                                <small class="text-muted">Maximum 255 characters</small>
                            </div>
                        </div>

                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary">Add School</button>
                            <button type="reset" class="btn btn-secondary">Reset</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Schools List Card -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Existing Schools</h3>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Address</th>
                                    <th>Added On</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                try {
                                    $stmt = $conn->query("SELECT * FROM schools ORDER BY created_at DESC");
                                    while ($school = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        echo "<tr>";
                                        echo "<td>" . htmlspecialchars($school['name']) . "</td>";
                                        echo "<td>" . htmlspecialchars($school['address']) . "</td>";
                                        echo "<td>" . date('Y-m-d', strtotime($school['created_at'])) . "</td>";
                                        echo "</tr>";
                                    }
                                } catch (PDOException $e) {
                                    echo "<tr><td colspan='3'>Error loading schools</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
.form-group textarea:focus {
    border-color: #80bdff;
    box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
}

.form-group textarea::placeholder {
    color: #999;
    font-style: italic;
}

/* Custom scrollbar for webkit browsers */
.form-group textarea::-webkit-scrollbar {
    width: 8px;
}

.form-group textarea::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.form-group textarea::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 4px;
}

.form-group textarea::-webkit-scrollbar-thumb:hover {
    background: #555;
}
</style>

<?php
// Get the buffered content
$content = ob_get_clean();

// Add custom styles and scripts
$additionalStyles = '
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" type="text/css" href="../assets/css/school.css">
';

$additionalScripts = '
<script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>
<script type="text/javascript" src="../assets/js/school.js"></script>
';

// Set the page title
$pageTitle = "Manage Schools";

// Include the layout
include '../includes/layout.php';
?> 