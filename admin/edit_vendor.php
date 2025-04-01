<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

// Check if vendor ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Vendor ID is required";
    header('Location: vendors.php');
    exit();
}

$vendor_id = $_GET['id'];

// Fetch vendor data
try {
    $stmt = $conn->prepare("
        SELECT 
            v.id as vendor_id,
            v.user_id,
            v.school_id,
            v.license_number,
            v.approval_status,
            u.username,
            u.email,
            u.contact_number,
            s.name as school_name
        FROM vendors v
        JOIN users u ON v.user_id = u.id
        JOIN schools s ON v.school_id = s.id
        WHERE v.id = ?
    ");
    $stmt->execute([$vendor_id]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$vendor) {
        $_SESSION['error'] = "Vendor not found";
        header('Location: vendors.php');
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching vendor: " . $e->getMessage();
    header('Location: vendors.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $contact_number = trim($_POST['contact_number']);
        $license_number = trim($_POST['license_number']);
        $school_id = $_POST['school_id'];
        
        // Validate inputs
        if (empty($username) || empty($email) || empty($contact_number) || empty($license_number) || empty($school_id)) {
            throw new Exception("All fields are required");
        }
        
        // Check if email is already taken by another user
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $vendor['user_id']]);
        if ($stmt->fetch()) {
            throw new Exception("Email is already in use by another user");
        }
        
        $conn->beginTransaction();
        
        // Update vendor details
        $stmt = $conn->prepare("UPDATE vendors SET license_number = ?, school_id = ? WHERE id = ?");
        $stmt->execute([$license_number, $school_id, $vendor_id]);
        
        // Update user details
        $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, contact_number = ? WHERE id = ?");
        $stmt->execute([$username, $email, $contact_number, $vendor['user_id']]);
        
        $conn->commit();
        $_SESSION['success'] = "Vendor updated successfully";
        header('Location: vendors.php');
        exit();
    } catch (PDOException $e) {
        $conn->rollBack();
        $error = "Database error: " . $e->getMessage();
    } catch (Exception $e) {
        $conn->rollBack();
        $error = $e->getMessage();
    }
}

// Fetch all schools for dropdown
try {
    $stmt = $conn->query("SELECT id, name FROM schools ORDER BY name");
    $schools = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $schools = [];
    $error = "Error fetching schools: " . $e->getMessage();
}

// Start output buffering
ob_start();
?>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $error ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Edit Vendor</h3>
                <div class="card-tools">
                    <a href="vendors.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> Back to Vendors
                    </a>
                </div>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="username">Vendor Name</label>
                        <input type="text" class="form-control" id="username" name="username" value="<?= htmlspecialchars($vendor['username']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($vendor['email']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="contact_number">Contact Number</label>
                        <input type="text" class="form-control" id="contact_number" name="contact_number" value="<?= htmlspecialchars($vendor['contact_number']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="license_number">License Number</label>
                        <input type="text" class="form-control" id="license_number" name="license_number" value="<?= htmlspecialchars($vendor['license_number']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="school_id">School</label>
                        <select class="form-control" id="school_id" name="school_id" required>
                            <option value="">Select School</option>
                            <?php foreach ($schools as $school): ?>
                                <option value="<?= $school['id'] ?>" <?= ($vendor['school_id'] == $school['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($school['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Current Status</label>
                        <div>
                            <?php
                            $statusClass = 'badge-warning';
                            if ($vendor['approval_status'] === 'approved') {
                                $statusClass = 'badge-success';
                            } elseif ($vendor['approval_status'] === 'rejected') {
                                $statusClass = 'badge-danger';
                            }
                            ?>
                            <span class="badge <?= $statusClass ?>"><?= ucfirst($vendor['approval_status']) ?></span>
                        </div>
                        <small class="text-muted">Status can be changed on the vendors page</small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Update Vendor</button>
                </form>
            </div>
        </div>
    </div>
</section>

<?php
// Get the buffered content
$content = ob_get_clean();

// Set the page title
$pageTitle = "Edit Vendor";

// Include the layout
include '../includes/layout.php';
?> 