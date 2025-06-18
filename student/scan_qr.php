<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

$page_title = 'Scan QR Menu';

$additionalStyles = '
<style>
    .camera-container {
        width: 100%;
        max-width: 640px;
        height: 480px;
        margin: 0 auto 20px;
        background: #000;
        position: relative;
        border-radius: 8px;
        overflow: hidden;
    }

    #reader {
        width: 100% !important;
        height: 100% !important;
    }

    #reader video {
        width: 100% !important;
        height: 100% !important;
        object-fit: cover;
    }

    .button-container {
        text-align: center;
        margin: 20px 0;
    }

    .button-container button {
        margin: 0 10px;
        padding: 10px 20px;
        font-size: 16px;
    }

    .instructions {
        margin-top: 20px;
        padding: 15px;
        background-color: #17a2b8;
        color: white;
        border-radius: 8px;
    }

    .instructions h5 {
        color: white;
        margin-bottom: 10px;
    }

    .instructions li {
        margin-bottom: 8px;
    }

    #qr-result {
        margin: 15px 0;
        padding: 10px;
        border-radius: 4px;
    }

    #reader__dashboard_section_swaplink,
    #reader__status_span {
        display: none !important;
    }

    .upload-section {
        text-align: center;
        margin: 20px 0;
        padding: 20px;
        border: 2px dashed #ccc;
        border-radius: 8px;
        background: #f8f9fa;
    }

    .upload-section:hover {
        border-color: #17a2b8;
        background: #e9ecef;
    }

    #qr-image-preview {
        max-width: 300px;
        max-height: 300px;
        margin: 10px auto;
        display: none;
    }
</style>';

$additionalScripts = '
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const html5QrCode = new Html5Qrcode("reader");
    const qrResult = document.getElementById("qr-result");
    const startButton = document.getElementById("start-camera");
    const stopButton = document.getElementById("stop-scanner");
    const restartButton = document.getElementById("restart-scanner");
    const fileInput = document.getElementById("qr-input-file");
    const imagePreview = document.getElementById("qr-image-preview");

    function showMessage(type, message) {
        qrResult.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
        console.log(`${type}: ${message}`); // Debug logging
    }

    // Handle successful QR code detection
    function handleQrResult(decodedText) {
        console.log("QR Code detected, content:", decodedText);

        // Extract vendor ID from URL
        let vendorId = null;
        try {
            const url = new URL(decodedText);
            const params = new URLSearchParams(url.search);
            vendorId = params.get("v");
            console.log("Extracted vendor ID:", vendorId);
        } catch (e) {
            console.error("Error parsing URL:", e);
        }

        if (vendorId) {
            const redirectUrl = `menu.php?vendor_id=${vendorId}`;
            showMessage("success", `QR Code detected! Redirecting to menu... (Vendor ID: ${vendorId})`);
            console.log("Redirecting to:", redirectUrl);
            setTimeout(() => {
                window.location.href = redirectUrl;
            }, 1500);
        } else {
            showMessage("danger", "Invalid QR Code. Please scan a valid vendor menu QR code. (URL format: " + decodedText + ")");
        }
    }

    // File upload handling
    fileInput.addEventListener("change", async (e) => {
        try {
            const file = e.target.files[0];
            if (!file) {
                showMessage("warning", "No file selected");
                return;
            }

            // Show loading message
            showMessage("info", "Processing image...");

            // Display image preview
            imagePreview.style.display = "block";
            imagePreview.src = URL.createObjectURL(file);

            // Create a new instance for file scanning
            const html5QrcodeScanner = new Html5Qrcode("reader");
            
            try {
                showMessage("info", "Scanning QR code from image...");
                const result = await html5QrcodeScanner.scanFile(file, /* showImage */ true);
                console.log("File scan result:", result);
                handleQrResult(result);
            } catch (error) {
                console.error("QR Code scanning error:", error);
                showMessage("danger", "Could not find a valid QR code in the image. Please try another image or ensure the QR code is clearly visible.");
            } finally {
                html5QrcodeScanner.clear();
            }
        } catch (error) {
            console.error("File processing error:", error);
            showMessage("danger", "Error processing the file: " + error.message);
        }
    });

    // Camera scanning functions
    async function startCamera() {
        try {
            showMessage("info", "Starting camera...");

            if (html5QrCode.isScanning) {
                await html5QrCode.stop();
            }

            const devices = await Html5Qrcode.getCameras();
            if (!devices || devices.length === 0) {
                throw new Error("No cameras found on your device.");
            }

            const config = {
                fps: 10,
                qrbox: { width: 250, height: 250 },
                aspectRatio: 1.0
            };

            const cameraId = devices.length > 1 ? devices[1].id : devices[0].id;

            await html5QrCode.start(
                cameraId,
                config,
                handleQrResult,
                onScanFailure
            );

            startButton.style.display = "none";
            stopButton.style.display = "inline-block";
            restartButton.style.display = "inline-block";
            showMessage("info", "Camera started. Point it at a QR code.");

        } catch (err) {
            console.error("Camera Error:", err);
            showMessage("danger", err.message || "Failed to access camera. Please check console for details.");
            startButton.style.display = "inline-block";
            stopButton.style.display = "none";
            restartButton.style.display = "none";
        }
    }

    function onScanFailure(error) {
        // Silent failure is fine for continuous scanning
    }

    // Camera button handlers
    startButton.addEventListener("click", startCamera);

    stopButton.addEventListener("click", async () => {
        if (html5QrCode.isScanning) {
            try {
                await html5QrCode.stop();
                showMessage("info", "Camera stopped.");
                startButton.style.display = "inline-block";
                stopButton.style.display = "none";
                restartButton.style.display = "none";
            } catch (err) {
                showMessage("danger", "Failed to stop scanner: " + err.message);
            }
        }
    });

    restartButton.addEventListener("click", async () => {
        if (html5QrCode.isScanning) {
            await html5QrCode.stop();
        }
        await startCamera();
    });
});
</script>';

ob_start();
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-qrcode"></i> Scan Vendor QR Code
                    </h3>
                </div>
                <div class="card-body">
                    <!-- Upload Section -->
                    <div class="upload-section">
                        <h5><i class="fas fa-upload"></i> Upload QR Code Image</h5>
                        <p class="text-muted">Upload an image containing a QR code</p>
                        <input type="file" id="qr-input-file" accept="image/*" class="form-control-file">
                        <img id="qr-image-preview" class="img-fluid" alt="QR code preview">
                    </div>

                    <!-- QR Result -->
                    <div id="qr-result"></div>

                    <div class="text-center my-4">
                        <h5>- OR -</h5>
                    </div>

                    <!-- Camera Feed -->
                    <div class="camera-container">
                        <div id="reader"></div>
                    </div>

                    <!-- Buttons -->
                    <div class="button-container">
                        <button id="start-camera" class="btn btn-primary btn-lg">
                            <i class="fas fa-camera"></i> Start Camera
                        </button>
                        <button id="stop-scanner" class="btn btn-danger" style="display: none;">
                            <i class="fas fa-stop"></i> Stop Scanner
                        </button>
                        <button id="restart-scanner" class="btn btn-warning" style="display: none;">
                            <i class="fas fa-redo"></i> Restart Scanner
                        </button>
                    </div>

                    <!-- Instructions -->
                    <div class="instructions">
                        <h5><i class="fas fa-info-circle"></i> Instructions:</h5>
                        <ol>
                            <li>Upload a QR code image or use the camera scanner</li>
                            <li>For camera scanning, click "Start Camera" and allow access when prompted</li>
                            <li>Point your camera at a vendor's QR code or wait for the uploaded image to be processed</li>
                            <li>Hold steady until the code is scanned</li>
                            <li>You will be redirected to the vendor's menu</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?>
