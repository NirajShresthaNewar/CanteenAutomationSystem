<?php
session_start();
require_once '../connection/db_connection.php';
require_once '../vendor/autoload.php';
require_once '../includes/functions.php';
require_once '../config/app_config.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Label\Label;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;

// Check login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    header('Location: ../index.php');
    exit();
}

// Get vendor ID
$stmt = $conn->prepare("SELECT id FROM vendors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vendor) {
    die('Vendor not found');
}

// Get server IP from functions.php
$server_ips = getServerIP();
$wifi_ip = $server_ips[0] ?? '192.168.137.139';

// Handle QR code generation
if (isset($_POST['generate_qr'])) {
    try {
        // Delete previous QR from database
        $stmt = $conn->prepare("DELETE FROM qr_codes WHERE vendor_id = ?");
        $stmt->execute([$vendor['id']]);

        // Prepare URL
        $menu_url = rtrim(BASE_URL, '/') . "/public/menu.php?v=" . $vendor['id'];
        error_log("Generating QR code for URL: " . $menu_url);

        // Ensure folder exists and is writable
        $qr_dir = "../uploads/qr_codes";
        if (!is_dir($qr_dir)) {
            if (!mkdir($qr_dir, 0777, true)) {
                die('Failed to create QR code directory');
            }
        } else {
            if (!is_writable($qr_dir)) {
                if (!chmod($qr_dir, 0777)) {
                    die('QR code directory is not writable');
                }
            }
        }

        // Define QR code path
        $qr_filename = "vendor_" . $vendor['id'] . "_" . time() . ".png"; // Add timestamp to filename
        $qr_path = "uploads/qr_codes/" . $qr_filename;
        $full_path = "../" . $qr_path;

        // Delete any old QR files for this vendor
        $old_files = glob("../uploads/qr_codes/vendor_" . $vendor['id'] . "_*.png");
        foreach ($old_files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        // Create QR code
        $qrCode = QrCode::create($menu_url)
            ->setSize(300)
            ->setMargin(10)
            ->setErrorCorrectionLevel(new ErrorCorrectionLevelHigh());

        $label = Label::create('Scan for Menu')
            ->setTextColor(new Color(0, 0, 0));

        $writer = new PngWriter();
        $result = $writer->write($qrCode, null, $label);

        // Save the QR code
        $result->saveToFile($full_path);

        // Verify file was saved
        if (!file_exists($full_path)) {
            throw new Exception("QR code file not found after generation");
        }

        // Save into DB
        try {
            $stmt = $conn->prepare("INSERT INTO qr_codes (vendor_id, qr_code, url) VALUES (?, ?, ?)");
            $stmt->execute([$vendor['id'], $qr_path, $menu_url]);
        } catch (PDOException $e) {
            if ($e->getCode() == '42S22') {
                $stmt = $conn->prepare("INSERT INTO qr_codes (vendor_id, qr_code) VALUES (?, ?)");
                $stmt->execute([$vendor['id'], $qr_path]);
            } else {
                throw $e;
            }
        }

        $_SESSION['success'] = "QR code generated successfully!";
    } catch (Exception $e) {
        $_SESSION['error'] = "QR code generation failed: " . $e->getMessage();
        error_log("QR generation error: " . $e->getMessage());
    }

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Get existing QR
try {
    $stmt = $conn->prepare("SELECT qr_code, url FROM qr_codes WHERE vendor_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$vendor['id']]);
    $qr_code = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    if ($e->getCode() == '42S22') {
        $stmt = $conn->prepare("SELECT qr_code FROM qr_codes WHERE vendor_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$vendor['id']]);
        $qr_code = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($qr_code) {
            $qr_code['url'] = rtrim(BASE_URL, '/') . "/public/menu.php?v=" . $vendor['id'];
        }
    } else {
        throw $e;
    }
}

$page_title = 'Menu QR Code';
ob_start();
?>  

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title mb-0">Menu QR Code</h3>
                </div>
                <div class="card-body text-center">
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success">
                            <?php 
                            echo $_SESSION['success'];
                            unset($_SESSION['success']);
                            ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger">
                            <?php 
                            echo $_SESSION['error'];
                            unset($_SESSION['error']);
                            ?>
                        </div>
                    <?php endif; ?>

                    <!-- Configuration Instructions -->
                    <div class="alert alert-warning mb-4">
                        <h5><i class="fas fa-info-circle"></i> Important Setup Information</h5>
                        <p>For the QR code to work on mobile devices:</p>
                        <ol>
                            <li>Make sure your phone is connected to the same WiFi network as this computer</li>
                            <li>Your computer's WiFi IP address is: <code>http://<?php echo $wifi_ip; ?></code></li>
                            <li>Current Base URL: <code><?php echo BASE_URL; ?></code></li>
                        </ol>
                        <p>To make it work:</p>
                        <ol>
                            <li>Open <code>config/app_config.php</code></li>
                            <li>Change the BASE_URL to: <code>http://<?php echo $wifi_ip; ?></code></li>
                            <li>Save the file and refresh this page</li>
                            <li>Generate a new QR code</li>
                            <li>Test it with your phone while connected to the same WiFi</li>
                        </ol>
                        <p class="text-danger"><strong>Note:</strong> If you can't access the menu on your phone, check that:</p>
                        <ul>
                            <li>Your phone is on the same WiFi network</li>
                            <li>XAMPP's Apache server is running</li>
                            <li>Your computer's firewall isn't blocking the connection</li>
                        </ul>
                    </div>

                    <?php if ($qr_code): ?>
                        <div class="mb-4">
                            <img src="<?php echo '../' . $qr_code['qr_code']; ?>?t=<?php echo time(); ?>" 
                                 alt="Menu QR Code" 
                                 class="img-fluid" 
                                 style="max-width: 300px;">
                        </div>
                        <div class="mb-4">
                            <p>QR Code URL: <code><?php echo htmlspecialchars($qr_code['url']); ?></code></p>
                            <a href="<?php echo '../' . $qr_code['qr_code']; ?>" 
                               download="menu_qr_code_<?php echo $vendor['id']; ?>.png" 
                               class="btn btn-success">
                                <i class="fas fa-download"></i> Download QR Code
                            </a>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> 
                            <p>Print this QR code and place it on your tables or counter.</p>
                            <p>Test the QR code with your phone before printing to ensure it's accessible.</p>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="mt-3">
                        <button type="submit" 
                                name="generate_qr" 
                                class="btn btn-primary">
                            <?php echo $qr_code ? 'Regenerate' : 'Generate'; ?> QR Code
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?>