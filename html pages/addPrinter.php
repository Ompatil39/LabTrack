<?php
session_start();
if (!isset($_SESSION["logged_in"]) || $_SESSION["logged_in"] !== true) {
    header("Location: login.php");
    exit;
}

include 'db.php';

// Function to generate unique device IDs
function generateDeviceId($type, $conn)
{
    $prefix = ($type === 'Printer') ? 'PRT' : 'DEV';
    $year = date('Y');
    $query = "SELECT device_id FROM devices WHERE device_id LIKE '$prefix-$year-%' ORDER BY device_id DESC LIMIT 1";
    $result = $conn->query($query);

    $newNum = 1;
    if ($result && $result->num_rows > 0) {
        $lastId = $result->fetch_assoc()['device_id'];
        $lastNum = (int) substr($lastId, -4);
        $newNum = $lastNum + 1;
    }
    $serial = str_pad($newNum, 4, '0', STR_PAD_LEFT);
    return "$prefix-$year-$serial";
}

// Fetch active labs for dropdown
$labsQuery = "SELECT lab_id, lab_name FROM labs WHERE status = 'Active'";
$labsResult = $conn->query($labsQuery);
$labs = [];
if ($labsResult && $labsResult->num_rows > 0) {
    while ($row = $labsResult->fetch_assoc()) {
        $labs[] = $row;
    }
}

// Initialize variables
$message = '';
$messageType = '';
$deviceDetails = null;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_printer'])) {
    $lab_id = $_POST['lab_id'] ?? '';
    $device_type = 'Printer'; // Fixed for this page
    $printer_model = $_POST['printer_model'] ?? '';
    $printer_type = $_POST['printer_type'] ?? '';
    $color_capability = $_POST['color_capability'] ?? '';
    $connectivity = $_POST['connectivity'] ?? '';
    $serial_number = $_POST['printer_serial'] ?? '';
    $status = $_POST['printer_status'] ?? 'Active';
    $created_at = date('Y-m-d H:i:s');
    $updated_at = $created_at;

    // Validation
    $errors = [];
    if (empty($lab_id)) $errors[] = "Lab selection is required";
    if (empty($printer_model)) $errors[] = "Printer model is required";
    if (empty($printer_type)) $errors[] = "Printer type is required";
    if (empty($color_capability)) $errors[] = "Color capability is required";
    if (empty($connectivity)) $errors[] = "Connectivity is required";

    if (empty($errors)) {
        try {
            $conn->begin_transaction();

            // Generate unique device ID
            $device_id = generateDeviceId($device_type, $conn);

            // Insert into devices table
            $query = "INSERT INTO devices (device_id, lab_id, device_type, device_name, serial_number, status, created_at, updated_at) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssssssss", $device_id, $lab_id, $device_type, $printer_model, $serial_number, $status, $created_at, $updated_at);
            $stmt->execute();

            // Insert into printers table
            $query = "INSERT INTO printers (device_id, printer_model, printer_type, color_capability, connectivity, serial_number) 
                      VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssssss", $device_id, $printer_model, $printer_type, $color_capability, $connectivity, $serial_number);
            $stmt->execute();

            $conn->commit();

            // Set device details for modal
            $deviceDetails = [
                'device_id' => $device_id,
                'device_name' => $printer_model,
                'device_type' => $device_type,
                'lab_id' => $lab_id,
                'lab_name' => getLabName($lab_id, $conn)
            ];

            $message = "Printer added successfully!";
            $messageType = "success";
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

// Function to get lab name
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
                    <a href="index.php">
                        <i class="fa-solid fa-chart-pie"></i> Dashboard
                    </a>
                </li>
                <li>
                    <a href="labs.php">
                        <i class="fa-solid fa-network-wired"></i> Labs
                    </a>
                </li>
                <li>
                    <a href="addLab.php">
                        <i class="fa-solid fa-plus"></i><span>Add Lab</span>
                    </a>
                </li>
                <li class="active">
                    <a href="addDevice.php">
                        <i class="fa-solid fa-plus"></i><span>Add Devices</span>
                    </a>
                </li>
                <li>
                    <a href="inventory.php">
                        <i class="fa-solid fa-warehouse"></i> Inventory
                    </a>
                </li>
                <li>
                    <a href="grievance.php">
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
                    <span>Add Printer</span>
                </div>
                <div class="user-info">
                    <i class="fa-solid fa-circle-user"></i><span class="font-rale">Administrator</span>
                </div>
            </div>

            <!-- CONTENT START of BELOW HEADER -->
            <div class="lab-management-container">
                <div class="form-wrapper">
                    <div class="form-section" id="lab-details">
                        <div class="section-header">
                            <h2 class="section-title">Register New Printer</h2>
                        </div>

                        <?php if (!empty($message)): ?>
                            <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'error'; ?>">
                                <?php echo $message; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                            <div class="selectLabDevice">
                                <div class="input-group">
                                    <label class="input-label">Select Device <span>*</span></label>
                                    <select class="form-input" required onchange="handleSelection(this.value)">
                                        <option value="">Select Device</option>
                                        <option value="pc">PC</option>
                                        <option value="printer" selected>Printer</option>
                                    </select>
                                </div>
                                <div class="input-group">
                                    <label class="input-label">Select Lab <span>*</span></label>
                                    <select name="lab_id" class="form-input" required>
                                        <option value="">Select Lab</option>
                                        <?php foreach ($labs as $lab): ?>
                                            <option value="<?php echo $lab['lab_id']; ?>"><?php echo $lab['lab_name']  . ' : ' .
                                                                                                $lab['lab_id']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Printer Inputs -->
                            <div class="printerInput" id="printerInput">
                                <div class="tab-header" style="color: #7f8c8d !important; font-weight: 500 !important;">
                                    <h4>Printer</h4>
                                </div>
                                <div class="grid-2col">
                                    <div class="input-group">
                                        <label class="input-label">Printer Model <span>*</span></label>
                                        <input type="text" name="printer_model" class="form-input" placeholder="Enter Printer Model (e.g. HP LaserJet Pro)" required>
                                    </div>
                                    <div class="input-group">
                                        <label class="input-label">Printer Type <span>*</span></label>
                                        <select name="printer_type" class="form-input" required>
                                            <option value="">Select Printer Type</option>
                                            <option>Inkjet</option>
                                            <option>Laser</option>
                                            <option>Dot Matrix</option>
                                        </select>
                                    </div>
                                    <div class="input-group">
                                        <label class="input-label">Color Capability <span>*</span></label>
                                        <select name="color_capability" class="form-input" required>
                                            <option value="">Select Option</option>
                                            <option>Color</option>
                                            <option>Black & White</option>
                                        </select>
                                    </div>
                                    <div class="input-group">
                                        <label class="input-label">Connectivity <span>*</span></label>
                                        <select name="connectivity" class="form-input" required>
                                            <option value="">Select Connectivity</option>
                                            <option>USB</option>
                                            <option>Network</option>
                                            <option>Wireless</option>
                                        </select>
                                    </div>
                                    <div class="input-group">
                                        <label class="input-label">Printer Serial Number</label>
                                        <input type="text" name="printer_serial" class="form-input" placeholder="Enter Printer Serial Number">
                                    </div>
                                    <div class="input-group">
                                        <label class="input-label">Printer Status <span>*</span></label>
                                        <select name="printer_status" class="form-input" required>
                                            <option value="">Status</option>
                                            <option>Active</option>
                                            <option>In-Active</option>
                                            <option>Under Repair</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="form-actions">
                                <button type="submit" name="submit_printer" class="btn btn-primary">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                                        fill="none" stroke="white" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round" class="lucide lucide-circle-plus">
                                        <circle cx="12" cy="12" r="10" />
                                        <path d="M8 12h8" />
                                        <path d="M12 8v8" />
                                    </svg> Add Printer
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success Modal -->
        <div id="device-added-modal" class="modal-overlay" <?php echo $showModal ? 'style="display: flex;"' : ''; ?>>
            <div class="modal">
                <div class="modal-header">
                    <div class="modal-icon icon-green">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            width="20" height="20">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <h2 class="modal-title">Device Added Successfully</h2>
                </div>
                <div class="modal-body">
                    <p class="success-text">The device has been successfully added to the inventory system.</p>
                    <div class="item-details">
                        <div class="detail-row">
                            <div class="detail-label-new">Device Name:</div>
                            <div class="detail-value" id="device-name"><?php echo $deviceDetails['device_name'] ?? 'N/A'; ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label-new">Device ID:</div>
                            <div class="detail-value" id="device-id"><?php echo $deviceDetails['device_id'] ?? 'N/A'; ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label-new">Category:</div>
                            <div class="detail-value" id="device-category"><?php echo $deviceDetails['device_type'] ?? 'N/A'; ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label-new">Location:</div>
                            <div class="detail-value" id="device-location"><?php echo $deviceDetails['lab_name'] ?? 'N/A'; ?></div>
                        </div>
                    </div>
                    <p class="next-steps">You can view and manage this device in the inventory management system. Use the device ID for future reference.</p>
                </div>
                <div class="modal-footer">
                    <button class="btnModal btnModal-primary btnModal-green" id="device-done-btnModal">Done</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function handleSelection(value) {
            if (value === 'pc') {
                window.location.href = 'addDevice.php'; // Redirect to PC form page if needed
            }
            // No action needed for 'printer' as this is the printer page
        }

        const deviceAddedModal = document.getElementById('device-added-modal');
        const deviceAddedDoneBtn = document.getElementById('device-done-btnModal');

        deviceAddedDoneBtn.addEventListener('click', function() {
            deviceAddedModal.style.display = 'none';
        });

        deviceAddedModal.addEventListener('click', function(e) {
            if (e.target === deviceAddedModal) {
                deviceAddedModal.style.display = 'none';
            }
        });
    </script>
</body>

</html>