<?php
session_start();
if (!isset($_SESSION["logged_in"]) || $_SESSION["logged_in"] !== true) {
    header("Location: ../html pages/login.php");
    exit;
}

include '../html pages/db.php';
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

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

    // Fetch device and monitor details
    $query = "SELECT d.*, m.brand_model, m.resolution, m.serial_number AS monitor_serial, m.status AS monitor_status 
              FROM devices d 
              LEFT JOIN monitors m ON d.device_id = m.device_id 
              WHERE d.device_id = ?";
    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        die("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $device_data = $result->fetch_assoc();
    } else {
        $message = "Device not found in database for ID: " . htmlspecialchars($id);
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
    $device_name = trim($_POST['monitor_name'] ?? '');
    $brand_model = trim($_POST['brand_model'] ?? '');
    $resolution = trim($_POST['resolution'] ?? '');
    $serial_number = trim($_POST['monitor_serial'] ?? '');
    $status = trim($_POST['monitor_status'] ?? '');
    $updated_at = date('Y-m-d H:i:s');

    // Validation
    $errors = [];
    if (empty($device_name)) $errors[] = "Monitor name is required";
    if (empty($brand_model)) $errors[] = "Brand & model is required";
    if (empty($resolution)) $errors[] = "Resolution is required";
    if (empty($status)) $errors[] = "Monitor status is required";
    if (empty($id)) $errors[] = "Device ID is missing";

    if (empty($errors)) {
        try {
            $conn->begin_transaction();

            // Update devices table
            $query = "UPDATE devices SET 
                      device_name = ?, 
                      serial_number = ?, 
                      status = ?, 
                      updated_at = ? 
                      WHERE device_id = ?";
            $stmt = $conn->prepare($query);
            if ($stmt === false) {
                die("Prepare failed: " . $conn->error);
            }
            $device_status = ($status === 'Active') ? 'Active' : (($status === 'In Repair') ? 'Under Repair' : 'InActive');
            $stmt->bind_param("sssss", $device_name, $serial_number, $device_status, $updated_at, $id);
            $stmt->execute();

            // Check and update monitors table
            $checkQuery = "SELECT device_id FROM monitors WHERE device_id = ?";
            $checkStmt = $conn->prepare($checkQuery);
            if ($checkStmt === false) {
                die("Prepare failed: " . $conn->error);
            }
            $checkStmt->bind_param("s", $id);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();

            if ($checkResult->num_rows > 0) {
                $query = "UPDATE monitors SET 
                          brand_model = ?, 
                          resolution = ?, 
                          serial_number = ?, 
                          status = ? 
                          WHERE device_id = ?";
                $stmt = $conn->prepare($query);
                if ($stmt === false) {
                    die("Prepare failed: " . $conn->error);
                }
                $stmt->bind_param("sssss", $brand_model, $resolution, $serial_number, $status, $id);
                $stmt->execute();
            } else {
                // Insert if it doesn't exist
                $query = "INSERT INTO monitors (device_id, brand_model, resolution, serial_number, status) 
                          VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                if ($stmt === false) {
                    die("Prepare failed: " . $conn->error);
                }
                $stmt->bind_param("sssss", $id, $brand_model, $resolution, $serial_number, $status);
                $stmt->execute();
            }

            $conn->commit();

            $message = "Monitor updated successfully!";
            $messageType = "success";

            $deviceDetails = [
                'device_id' => $id,
                'device_name' => $device_name,
                'device_type' => 'Monitor',
                'lab_id' => $device_data['lab_id'],
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
    if ($stmt === false) {
        die("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("s", $lab_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return ($result && $result->num_rows > 0) ? $result->fetch_assoc()['lab_name'] : "Unknown Lab";
}

$showModal = ($messageType === 'success' && $deviceDetails !== null);
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
                        <?php if (!empty($message)): ?>
                            <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'error'; ?>">
                                <?php echo $message; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($isEditMode && $device_data !== null): ?>
                            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?id=' . urlencode($id)); ?>">
                                <input type="hidden" name="id" value="<?php echo htmlspecialchars($device_data['device_id']); ?>">

                                <div class="selectLabDevice" style="margin-bottom: 0rem !important;">
                                    <div class="input-group" style="margin-bottom: 1rem !important;">
                                        <label class="input-label">Lab</label>
                                        <select class="form-input no-hover" disabled>
                                            <option value=""><?php echo htmlspecialchars(getLabName($device_data['lab_id'], $conn)); ?></option>
                                        </select>
                                    </div>
                                </div>

                                <div class="pcInput">
                                    <div class="title" style="padding-bottom: 1rem !important;">
                                        <div class="sub-heading">Monitor Details</div>
                                    </div>

                                    <div class="step-content" style="display: block;">
                                        <div class="grid-3col">
                                            <div class="input-group">
                                                <label class="input-label">ID <span>*</span></label>
                                                <input type="text" class="form-input no-hover"
                                                    value="<?php echo htmlspecialchars($device_data['device_id']); ?>" disabled>
                                            </div>
                                            <div class="input-group">
                                                <label class="input-label">Monitor Name <span>*</span></label>
                                                <input type="text" name="monitor_name" class="form-input"
                                                    value="<?php echo htmlspecialchars($device_data['device_name'] ?? ''); ?>" required>
                                            </div>
                                            <div class="input-group">
                                                <label class="input-label">Brand & Model <span>*</span></label>
                                                <input type="text" name="brand_model" class="form-input"
                                                    value="<?php echo htmlspecialchars($device_data['brand_model'] ?? ''); ?>" required>
                                            </div>
                                            <div class="input-group">
                                                <label class="input-label">Resolution <span>*</span></label>
                                                <input type="text" name="resolution" class="form-input"
                                                    value="<?php echo htmlspecialchars($device_data['resolution'] ?? ''); ?>" required>
                                            </div>
                                            <div class="input-group">
                                                <label class="input-label">Serial Number</label>
                                                <input type="text" name="monitor_serial" class="form-input"
                                                    value="<?php echo htmlspecialchars($device_data['monitor_serial'] ?? $device_data['serial_number'] ?? ''); ?>">
                                            </div>
                                            <div class="input-group">
                                                <label class="input-label">Monitor Status <span>*</span></label>
                                                <select name="monitor_status" class="form-input" required>
                                                    <option value="">Select Status</option>
                                                    <option value="Active" <?php echo ($device_data['monitor_status'] ?? '') === 'Active' ? 'selected' : ''; ?>>Active</option>
                                                    <option value="In Repair" <?php echo ($device_data['monitor_status'] ?? '') === 'In Repair' ? 'selected' : ''; ?>>In Repair</option>
                                                    <option value="Faulty" <?php echo ($device_data['monitor_status'] ?? '') === 'Faulty' ? 'selected' : ''; ?>>Faulty</option>
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
                            <p>Please provide a valid ID (e.g., MON-2025-0001) to edit device details.</p>
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

        // Redirect to inventory.php when Done button is clicked
        if (deviceAddedDoneBtn) {
            deviceAddedDoneBtn.addEventListener('click', function() {
                deviceAddedModal.style.display = 'none';
                window.location.href = '../html pages/inventory.php';
            });
        }

        // Close modal and redirect if clicked outside
        if (deviceAddedModal) {
            deviceAddedModal.addEventListener('click', function(e) {
                if (e.target === deviceAddedModal) {
                    deviceAddedModal.style.display = 'none';
                    window.location.href = '../html pages/inventory.php';
                }
            });
        }
    </script>
</body>

</html>

<?php
$conn->close();
?>