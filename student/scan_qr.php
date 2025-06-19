<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Scan QR Menu - Campus Dining</title>

    <!-- Core CSS -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    
    <style>
        .camera-container {
            width: 100%;
            max-width: 640px;
            height: 480px;
            margin: 0 auto 20px;
            background: #f8f9fa;
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

        #start-camera {
            margin: 20px auto;
            display: block;
        }

        .back-button {
            margin: 20px;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini">
    <!-- Back button -->
    <div class="back-button">
        <a href="dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

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

                        <!-- Start Camera Button -->
                        <button id="start-camera" class="btn btn-primary">
                            <i class="fas fa-camera"></i> Start Camera
                        </button>

                        <!-- Instructions -->
                        <div class="instructions">
                            <h5><i class="fas fa-info-circle"></i> Instructions:</h5>
                            <ol>
                                <li>Upload a QR code image or click "Start Camera" to use the scanner</li>
                                <li>Allow camera access when prompted</li>
                                <li>Point your camera at a vendor's QR code</li>
                                <li>Hold steady until the code is scanned</li>
                                <li>You will be redirected to the vendor's menu</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Required Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/html5-qrcode"></script>
    
    <script>
    // Wait for the DOM to be fully loaded
    document.addEventListener("DOMContentLoaded", function() {
        const html5QrCode = new Html5Qrcode("reader");
        const qrResult = document.getElementById("qr-result");
        const fileInput = document.getElementById("qr-input-file");
        const imagePreview = document.getElementById("qr-image-preview");
        const startButton = document.getElementById("start-camera");
        let isScanning = false;

        function showMessage(type, message) {
            qrResult.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
            console.log(`${type}: ${message}`);
        }

        function handleQrResult(decodedText) {
            console.log("Raw QR Code content:", decodedText);
            
            // First, check if it's a complete URL
            let vendorId = null;
            
            try {
                // Try to extract vendor ID from URL
                const url = new URL(decodedText);
                vendorId = url.searchParams.get('v') || url.searchParams.get('vendor_id');
                
                if (!vendorId) {
                    // Try to find vendor ID in the path
                    const pathMatch = url.pathname.match(/vendor[/_-]?(\d+)/i);
                    if (pathMatch) {
                        vendorId = pathMatch[1];
                    }
                }
            } catch (e) {
                console.log("Not a URL, trying direct vendor ID extraction");
                // Try to find a vendor ID pattern in the raw text
                const directMatch = decodedText.match(/vendor[/_-]?(\d+)/i);
                if (directMatch) {
                    vendorId = directMatch[1];
                }
            }

            if (vendorId) {
                showMessage("success", `QR Code detected! Redirecting to vendor ${vendorId}'s menu...`);
                console.log("Redirecting to vendor:", vendorId);
                setTimeout(() => {
                    window.location.href = `menu.php?vendor_id=${vendorId}`;
                }, 1500);
            } else {
                console.log("Could not extract vendor ID from:", decodedText);
                showMessage("danger", "Invalid QR Code format. The QR code should contain a vendor menu link.");
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

                // Validate file type
                if (!file.type.startsWith('image/')) {
                    showMessage("danger", "Please select a valid image file");
                    return;
                }

                // Show loading message
                showMessage("info", "Processing image...");
                
                // Display image preview
                imagePreview.style.display = "block";
                imagePreview.src = URL.createObjectURL(file);

                try {
                    // Simple direct scan attempt
                    const result = await html5QrCode.scanFile(file, /* verbose= */ true);
                    console.log("Scan successful, result:", result);
                    handleQrResult(result);
                } catch (firstError) {
                    console.log("First scan attempt failed:", firstError);
                    
                    // Try alternative scanning method
                    try {
                        await html5QrCode.clear();
                        const config = {
                            experimentalFeatures: {
                                useBarCodeDetectorIfSupported: true
                            }
                        };
                        const result = await html5QrCode.scanFileV2(file, config);
                        if (result?.decodedText) {
                            console.log("Alternative scan successful:", result);
                            handleQrResult(result.decodedText);
                        } else {
                            throw new Error("No QR code found in image");
                        }
                    } catch (secondError) {
                        console.error("All scanning attempts failed:", secondError);
                        showMessage("danger", `Could not read QR code. Error: ${secondError.message || 'Unknown error'}`);
                        
                        // Clear the preview
                        imagePreview.style.display = "none";
                        imagePreview.src = "";
                    }
                }
            } catch (error) {
                console.error("File processing error:", error);
                showMessage("danger", `Error processing file: ${error.message}`);
                
                // Clear the preview
                imagePreview.style.display = "none";
                imagePreview.src = "";
            }
        });

        // Start camera button click handler
        startButton.addEventListener("click", function() {
            if (isScanning) {
                html5QrCode.stop()
                    .then(() => {
                        isScanning = false;
                        startButton.innerHTML = '<i class="fas fa-camera"></i> Start Camera';
                        showMessage("info", "Camera stopped");
                    })
                    .catch(err => {
                        console.error("Error stopping camera:", err);
                    });
            } else {
                Html5Qrcode.getCameras()
                    .then(devices => {
                        if (devices && devices.length) {
                            showMessage("info", "Starting camera...");
                            const cameraId = devices[0].id;
                            html5QrCode.start(
                                cameraId,
                                {
                                    fps: 10,
                                    qrbox: { width: 250, height: 250 }
                                },
                                handleQrResult,
                                (error) => {
                                    // Silent failure for continuous scanning
                                }
                            )
                            .then(() => {
                                isScanning = true;
                                startButton.innerHTML = '<i class="fas fa-stop"></i> Stop Camera';
                                showMessage("success", "Camera started successfully");
                            })
                            .catch((err) => {
                                showMessage("danger", "Error starting camera: " + err);
                            });
                        } else {
                            showMessage("danger", "No cameras found on your device");
                        }
                    })
                    .catch(err => {
                        showMessage("danger", "Error accessing camera: " + err);
                    });
            }
        });
    });
    </script>
</body>
</html>
