<?php
session_start();
if (!isset($_SESSION["logged_in"]) || $_SESSION["logged_in"] !== true) {
    header("Location: html pages/login.php");
    exit;
}

require_once 'html pages/db.php';
require_once 'html pages/qr_generator.php'; // Updated to use chillerlan/php-qrcode

$message = '';
$messageType = '';
$updatedCount = 0;
$totalDevices = 0;

// Get total devices count
$countQuery = "SELECT COUNT(*) as total FROM devices";
$countResult = $conn->query($countQuery);
if ($countResult && $countResult->num_rows > 0) {
    $totalDevices = $countResult->fetch_assoc()['total'];
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_all_qr'])) {
    try {
        $query = "SELECT device_id, device_name, lab_id, qr_code FROM devices ORDER BY device_id";
        $result = $conn->query($query);

        if ($result && $result->num_rows > 0) {
            while ($device = $result->fetch_assoc()) {
                $device_id   = $device['device_id'];
                $device_name = $device['device_name'];
                $lab_id      = $device['lab_id'];
                $current_qr  = $device['qr_code'];

                // Update if empty or old Google QR
                $needsUpdate = empty($current_qr) || strpos($current_qr, 'chart.googleapis.com') !== false;

                if ($needsUpdate) {
                    $new_qr_code = generateQRCode($device_id, $device_name, $lab_id);

                    $updateQuery = "UPDATE devices SET qr_code = ? WHERE device_id = ?";
                    $updateStmt = $conn->prepare($updateQuery);
                    $updateStmt->bind_param("ss", $new_qr_code, $device_id);

                    if ($updateStmt->execute()) {
                        $updatedCount++;
                    }
                }
            }

            if ($updatedCount > 0) {
                $message = "Successfully updated QR codes for $updatedCount devices!";
                $messageType = "success";
            } else {
                $message = "All devices already have up-to-date QR codes.";
                $messageType = "info";
            }
        } else {
            $message = "No devices found in the database.";
            $messageType = "info";
        }
    } catch (Exception $e) {
        $message = "Error updating QR codes: " . $e->getMessage();
        $messageType = "error";
    }
}

// Get devices for display
$devices = [];
$displayQuery = "SELECT d.device_id, d.device_name, d.device_type, d.qr_code, l.lab_name 
                 FROM devices d 
                 LEFT JOIN labs l ON d.lab_id = l.lab_id 
                 ORDER BY d.device_id";
$displayResult = $conn->query($displayQuery);
if ($displayResult && $displayResult->num_rows > 0) {
    while ($row = $displayResult->fetch_assoc()) {
        $devices[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Existing QR Codes - LabTrack</title>
    <link rel="stylesheet" href="public/css/style.css" />
    <script src="https://kit.fontawesome.com/0319a73572.js" crossorigin="anonymous"></script>
    <style>
        .alert {
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 4px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .device-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .device-card {
            background: #ffffff;
            border-radius: 0.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            padding: 1rem;
            border-left: 4px solid #3498db;
        }

        .device-card.needs-update {
            border-left-color: #e74c3c;
        }

        .device-card.updated {
            border-left-color: #27ae60;
        }

        .qr-status {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .qr-status.old {
            background: #f8d7da;
            color: #721c24;
        }

        .qr-status.new {
            background: #d4edda;
            color: #155724;
        }

        .qr-status.none {
            background: #fff3cd;
            color: #856404;
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="logo">
                <span><i class="fa-brands fa-watchman-monitoring colour"></i>LabTrack</span>
            </div>
            <hr class="solid" />
            <ul class="menu">
                <li class="menu-title">Menu</li>
                <li><a href="html pages/index.php"><i class="fa-solid fa-chart-pie"></i> Dashboard</a></li>
                <li><a href="html pages/labs.php"><i class="fa-solid fa-network-wired"></i> Labs</a></li>
                <li><a href="html pages/addLab.php"><i class="fa-solid fa-plus"></i> Add Lab</a></li>
                <li><a href="html pages/addDevice.php"><i class="fa-solid fa-plus"></i> Add Devices</a></li>
                <li><a href="html pages/inventory.php"><i class="fa-solid fa-warehouse"></i> Inventory</a></li>
                <li><a href="html pages/grievance.php"><i class="fa-solid fa-paper-plane"></i> Grievance</a></li>
            </ul>
            <div class="log-out">
                <a href="html pages/logout.php" class="none">
                    <span><i class="fa-solid fa-arrow-right-from-bracket"></i></span> Logout
                </a>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="main-content">
            <div class="header">
                <div class="sub-heading"><span>Update Existing QR Codes</span></div>
                <div class="user-info" onclick="window.location.href = 'html pages/profileManage.php';" style="margin-right: 0.5rem;">
                    <i class="fa-solid fa-circle-user"></i>
                    <span class="font-rale"><?php echo htmlspecialchars(strtoupper($_SESSION['username']) ?? 'User'); ?></span>
                </div>
            </div>

            <div class="form-wrapper" style="padding: 1.5rem;">
                <div class="section-header">
                    <h2 class="section-title">Update Existing Device QR Codes</h2>
                </div>

                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <div class="items" style="text-align: center; padding: 2rem;">
                    <div style="margin-bottom: 2rem;">
                        <i class="fa-solid fa-sync-alt" style="font-size: 4rem; color: #3498db; margin-bottom: 1rem;"></i>
                        <h3 style="color: #2c3e50; margin-bottom: 1rem;">Update QR Codes</h3>
                        <p style="color: #7f8c8d; margin-bottom: 2rem;">
                            Update all existing devices with the new QR code generation system.
                            This will replace old Google Charts API links with reliable QR codes.
                        </p>
                    </div>

                    <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 0.5rem; margin-bottom: 2rem;">
                        <h4 style="color: #2c3e50; margin-bottom: 1rem;">Current Status</h4>
                        <div style="display: flex; justify-content: center; align-items: center; gap: 2rem;">
                            <div style="text-align: center;">
                                <div style="font-size: 2rem; font-weight: bold; color: #2c3e50;"><?php echo $totalDevices; ?></div>
                                <div style="color: #7f8c8d;">Total Devices</div>
                            </div>
                        </div>
                    </div>

                    <form method="POST" style="margin-bottom: 2rem;">
                        <button type="submit" name="update_all_qr" class="btn btn-primary" style="font-size: 1.1rem; padding: 1rem 2rem;">
                            <i class="fa-solid fa-sync-alt"></i>
                            Update All QR Codes
                        </button>
                    </form>

                    <div style="background: #e3f2fd; color: #1976d2; padding: 1rem; border-radius: 0.5rem;">
                        <h5 style="margin-bottom: 0.5rem;">What This Does:</h5>
                        <ul style="text-align: left; display: inline-block; margin: 0;">
                            <li>Replaces old Google Charts API QR codes with new reliable ones</li>
                            <li>Generates QR codes for devices that don't have any</li>
                            <li>Uses the same QR code format as new devices</li>
                            <li>Maintains all existing device information</li>
                        </ul>
                    </div>
                </div>

                <?php if (!empty($devices)): ?>
                    <div class="section-header" style="margin-top: 2rem;">
                        <h2 class="section-title">Device QR Code Status</h2>
                    </div>

                    <div class="device-grid">
                        <?php foreach ($devices as $device): ?>
                            <?php
                            $qr_status = 'none';
                            $card_class = 'device-card';

                            if (!empty($device['qr_code'])) {
                                if (strpos($device['qr_code'], 'chart.googleapis.com') !== false) {
                                    $qr_status = 'old';
                                    $card_class .= ' needs-update';
                                } elseif (strpos($device['qr_code'], 'data:image/png;base64,') === 0 || strpos($device['qr_code'], 'https://') === 0) {
                                    $qr_status = 'new';
                                    $card_class .= ' updated';
                                }
                            }
                            ?>
                            <div class="<?php echo $card_class; ?>">
                                <h4 style="margin: 0 0 0.5rem 0; color: #2c3e50;">
                                    <?php echo htmlspecialchars($device['device_name']); ?>
                                </h4>
                                <p style="margin: 0 0 0.5rem 0; color: #7f8c8d; font-size: 0.9rem;">
                                    ID: <?php echo htmlspecialchars($device['device_id']); ?>
                                </p>
                                <p style="margin: 0 0 0.5rem 0; color: #7f8c8d; font-size: 0.9rem;">
                                    Type: <?php echo htmlspecialchars($device['device_type']); ?>
                                </p>
                                <p style="margin: 0 0 0.5rem 0; color: #7f8c8d; font-size: 0.9rem;">
                                    Lab: <?php echo htmlspecialchars($device['lab_name'] ?? 'Unknown'); ?>
                                </p>
                                <div style="margin-top: 0.5rem;">
                                    <span class="qr-status <?php echo $qr_status; ?>">
                                        <?php
                                        switch ($qr_status) {
                                            case 'old':
                                                echo 'Old QR Code';
                                                break;
                                            case 'new':
                                                echo 'Updated QR Code';
                                                break;
                                            case 'none':
                                                echo 'No QR Code';
                                                break;
                                        }
                                        ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>

</html>