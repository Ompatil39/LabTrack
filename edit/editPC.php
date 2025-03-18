<?php
session_start();
if (!isset($_SESSION["logged_in"]) || $_SESSION["logged_in"] !== true) {
    header("Location: ../html pages/login.php");
    exit;
}

include '../html pages/db.php';

// Initialize variables
$message = '';
$messageType = '';
$deviceDetails = null;
$isEditMode = false;
$device_data = null;

// Get id from URL and fetch data
$id = isset($_GET['id']) ? trim($_GET['id']) : '';
if (!empty($id)) {
    $isEditMode = true;

    // Fetch device details
    $query = "SELECT d.*, p.processor, p.ram, p.storage, p.os 
              FROM devices d 
              LEFT JOIN pcs p ON d.device_id = p.device_id 
              WHERE d.device_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $device_data = $result->fetch_assoc();
    } else {
        $message = "Device not found in database!";
        $messageType = "error";
        $isEditMode = false;
    }
} else {
    $message = "No ID provided in URL!";
    $messageType = "error";
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_device'])) {
    $id = trim($_POST['id'] ?? '');
    $device_name = trim($_POST['pc_name'] ?? '');
    $pc_code = trim($_POST['pc_code'] ?? '');
    $serial_number = trim($_POST['serial_number'] ?? '');
    $processor = trim($_POST['processor'] ?? '');
    $ram = trim($_POST['ram'] ?? '');
    $storage = trim($_POST['storage'] ?? '');
    $os = trim($_POST['os'] ?? '');
    $status = trim($_POST['pc_status'] ?? '');
    $updated_at = date('Y-m-d H:i:s');

    // Validation (lab_id is not required since it's disabled)
    $errors = [];
    if (empty($device_name)) $errors[] = "PC name is required";
    if (empty($processor)) $errors[] = "Processor is required";
    if (empty($ram)) $errors[] = "RAM is required";
    if (empty($storage)) $errors[] = "Storage is required";
    if (empty($status)) $errors[] = "PC status is required";
    if (empty($id)) $errors[] = "Device ID is missing";

    if (empty($errors)) {
        try {
            $conn->begin_transaction();

            // Update devices table (lab_id is not updated since it's disabled)
            $query = "UPDATE devices SET 
                      device_name = ?, 
                      serial_number = ?, 
                      status = ?, 
                      updated_at = ? 
                      WHERE device_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sssss", $device_name, $serial_number, $status, $updated_at, $id);
            $stmt->execute();

            // Check if PC record exists and update
            $checkQuery = "SELECT device_id FROM pcs WHERE device_id = ?";
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->bind_param("s", $id);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();

            if ($checkResult->num_rows > 0) {
                $query = "UPDATE pcs SET 
                          processor = ?, 
                          ram = ?, 
                          storage = ?, 
                          os = ? 
                          WHERE device_id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("sssss", $processor, $ram, $storage, $os, $id);
                $stmt->execute();
            }

            $conn->commit();

            $message = "Device updated successfully!";
            $messageType = "success";

            $deviceDetails = [
                'device_id' => $id,
                'device_name' => $device_name,
                'device_type' => 'PC',
                'lab_id' => $device_data['lab_id'], // Retains original lab_id
                'lab_name' => getLabName($device_data['lab_id'], $conn)
            ];
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error: " . $e->getMessage();
            $messageType = "error";
        }
    } else {
        $message = "Please correct the following errors: " . implode(", ", $errors);
        $messageType = "error";
    }
}

function getLabName($lab_id, $conn)
{
    $query = "SELECT lab_name FROM labs WHERE lab_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $lab_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return ($result && $result->num_rows > 0) ? $result->fetch_assoc()['lab_name'] : "Unknown Lab";
}

$showModal = ($messageType === 'success' && $deviceDetails !== null);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Lab Monitoring System</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.min.js"
        integrity="sha512-L0Shl7nXXzIlBSUUPpxrokqq4ojqgZFQczTYlGjzONGTDAcLremjwaWv5A+EDLnxhQzY5xUZPWLOLqYRkY0Cbw=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-sparklines/2.1.2/jquery.sparkline.min.js"></script>
    <link rel="stylesheet" href="../public/css/style.css" />
    <script src="https://kit.fontawesome.com/0319a73572.js" crossorigin="anonymous"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,200,0,200&icon_names=dns" />
    <link
        href="https://fonts.googleapis.com/css2?family=Raleway:ital,wght@0,100..900;1,100..900&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap"
        rel="stylesheet" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Raleway:ital,wght@0,100..900;1,100..900&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap"
        rel="stylesheet" />
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

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
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
                <li>
                    <a href="../html pages/index.php">
                        <i class="fa-solid fa-chart-pie"></i> Dashboard
                    </a>
                </li>
                <li>
                    <a href="../html pages/labs.php">
                        <i class="fa-solid fa-network-wired"></i> Labs
                    </a>
                </li>
                <li>
                    <a href="../html pages/addLab.php">
                        <i class="fa-solid fa-plus"></i><span>Add Lab</span>
                    </a>
                </li>
                <li>
                    <a href="../html pages/addDevice.php">
                        <i class="fa-solid fa-plus"></i><span>Add Devices</span>
                    </a>
                </li>
                <li>
                    <a href="../html pages/inventory.php">
                        <i class="fa-solid fa-warehouse"></i> Inventory
                    </a>
                </li>
                <li>
                    <a href="../html pages/grievance.php">
                        <i class="fa-solid fa-paper-plane"></i> Grievance
                    </a>
                </li>
            </ul>
        </div>

        <!-- Main Content Area -->
        <div class="main-content">
            <!-- Header -->
            <div class="header">
                <div class="sub-heading">
                    <span>Edit Devices</span>
                </div>
                <div class="user-info">
                    <i class="fa-solid fa-circle-user"></i><span class="font-rale">Administrator</span>
                </div>
            </div>

            <!-- CONTENT START -->
            <div class="lab-management-container">
                <div class="form-wrapper">
                    <div class="form-section" id="lab-details">
                        <div class="section-header">
                            <h2 class="section-title">Edit PC Details</h2>
                        </div>

                        <?php if (!empty($message)): ?>
                            <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'error'; ?>">
                                <?php echo $message; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($isEditMode): ?>
                            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                                <input type="hidden" name="id" value="<?php echo htmlspecialchars($device_data['device_id']); ?>">

                                <div class="selectLabDevice" style="margin-bottom: 0rem !important;">
                                    <div class="input-group" style="margin-bottom: 1rem !important;">
                                        <label class="input-label">Lab</label>
                                        <select class="form-input no-hover" disabled>
                                            <option value=""><?php echo htmlspecialchars(getLabName($device_data['lab_id'], $conn)); ?></option>
                                        </select>
                                    </div>
                                </div>

                                <div class="pcInput" id="pcInput">
                                    <div class="title" style="padding-bottom: 1rem !important;">
                                        <div class="sub-heading">PC Details</div>
                                    </div>

                                    <div class="step-content" style="display: block;">
                                        <div class="grid-3col">
                                            <div class="input-group">
                                                <label class="input-label">PC Name <span>*</span></label>
                                                <input type="text" name="pc_name" class="form-input"
                                                    value="<?php echo htmlspecialchars($device_data['device_name'] ?? ''); ?>" required>
                                            </div>
                                            <div class="input-group">
                                                <label class="input-label">ID <span>*</span></label>
                                                <input type="text" class="form-input no-hover"
                                                    value="<?php echo htmlspecialchars($device_data['device_id']); ?>" disabled>
                                            </div>
                                            <div class="input-group">
                                                <label class="input-label">PC Code</label>
                                                <input type="text" name="pc_code" class="form-input no-hover"
                                                    value="<?php echo htmlspecialchars($device_data['device_id']); ?>" disabled>
                                            </div>
                                            <div class="input-group">
                                                <label class="input-label">Serial Number</label>
                                                <input type="text" name="serial_number" class="form-input"
                                                    value="<?php echo htmlspecialchars($device_data['serial_number'] ?? ''); ?>">
                                            </div>
                                            <div class="input-group">
                                                <label class="input-label">Processor <span>*</span></label>
                                                <input type="text" name="processor" class="form-input"
                                                    value="<?php echo htmlspecialchars($device_data['processor'] ?? ''); ?>" required>
                                            </div>
                                            <div class="input-group">
                                                <label class="input-label">RAM <span>*</span></label>
                                                <input type="text" name="ram" class="form-input"
                                                    value="<?php echo htmlspecialchars($device_data['ram'] ?? ''); ?>" required>
                                            </div>
                                            <div class="input-group">
                                                <label class="input-label">Storage (HDD/SSD) <span>*</span></label>
                                                <input type="text" name="storage" class="form-input"
                                                    value="<?php echo htmlspecialchars($device_data['storage'] ?? ''); ?>" required>
                                            </div>
                                            <div class="input-group">
                                                <label class="input-label">Operating System</label>
                                                <input type="text" name="os" class="form-input"
                                                    value="<?php echo htmlspecialchars($device_data['os'] ?? ''); ?>">
                                            </div>
                                            <div class="input-group">
                                                <label class="input-label">PC Status <span>*</span></label>
                                                <select name="pc_status" class="form-input" required>
                                                    <option value="">Status</option>
                                                    <option value="Active" <?php echo ($device_data['status'] ?? '') === 'Active' ? 'selected' : ''; ?>>Active</option>
                                                    <option value="In-Active" <?php echo ($device_data['status'] ?? '') === 'In-Active' ? 'selected' : ''; ?>>In-Active</option>
                                                    <option value="Under Repair" <?php echo ($device_data['status'] ?? '') === 'Under Repair' ? 'selected' : ''; ?>>Under Repair</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Save Device Button -->
                                    <div class="form-actions">
                                        <button type="submit" name="submit_device" class="btn btn-primary">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                                                stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-save">
                                                <path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z" />
                                                <path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7" />
                                                <path d="M7 3v4a1 1 0 0 0 1 1h7" />
                                            </svg>
                                            Save Details
                                        </button>
                                    </div>
                                </div>
                            </form>
                        <?php else: ?>
                            <p>Please provide a valid ID (e.g., PC-2025-0001) to edit device details.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Success Modal -->
            <div id="device-added-modal" class="modal-overlay" <?php echo $showModal ? 'style="display: flex;"' : ''; ?>>
                <div class="modal">
                    <div class="modal-header">
                        <div class="modal-icon icon-green">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="20" height="20">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <h2 class="modal-title">Device Updated Successfully</h2>
                    </div>
                    <div class="modal-body">
                        <p class="success-text">The device has been successfully updated in the inventory system.</p>
                        <div class="item-details">
                            <div class="detail-row">
                                <div class="detail-label-new">Device Name:</div>
                                <div class="detail-value"><?php echo htmlspecialchars($deviceDetails['device_name'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label-new">Device ID:</div>
                                <div class="detail-value"><?php echo htmlspecialchars($deviceDetails['device_id'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label-new">Category:</div>
                                <div class="detail-value"><?php echo htmlspecialchars($deviceDetails['device_type'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label-new">Location:</div>
                                <div class="detail-value"><?php echo htmlspecialchars($deviceDetails['lab_name'] ?? 'N/A'); ?></div>
                            </div>
                        </div>
                        <p class="next-steps">You can view and manage this device in the inventory management system.</p>
                    </div>
                    <div class="modal-footer">
                        <button class="btnModal btnModal-primary btnModal-green" id="device-done-btnModal">Done</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const deviceAddedModal = document.getElementById('device-added-modal');
        const deviceAddedDoneBtn = document.getElementById('device-done-btnModal');

        deviceAddedDoneBtn.addEventListener('click', function() {
            deviceAddedModal.style.display = 'none';
            window.location.href = '../html pages/inventory.php';
        });

        deviceAddedModal.addEventListener('click', function(e) {
            if (e.target === deviceAddedModal) {
                deviceAddedModal.style.display = 'none';
            }
        });
    </script>
</body>

</html>