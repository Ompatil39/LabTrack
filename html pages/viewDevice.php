<?php
session_start();
if (isset($_SESSION["logged_in"]) !== true) {
    header("Location: login.php");
}
include 'db.php';

// Get device ID from URL parameter
$device_id = isset($_GET['id']) ? $_GET['id'] : '';

if (empty($device_id)) {
    echo "Device ID not provided";
    exit;
}

// Prepare the query to fetch device details
$device_query = "SELECT d.device_id, d.device_name, d.device_type, d.status, d.lab_id,
                       l.lab_name, CONCAT(l.building, ' - Room ', l.room_number) AS lab_location
                FROM devices d
                LEFT JOIN labs l ON d.lab_id = l.lab_id
                WHERE d.device_id = ?";

// Initialize the prepared statement
$stmt = mysqli_prepare($conn, $device_query);

// Bind parameters and execute
mysqli_stmt_bind_param($stmt, "s", $device_id);
mysqli_stmt_execute($stmt);

// Get the result
$device_result = mysqli_stmt_get_result($stmt);

// Check if device exists
if (mysqli_num_rows($device_result) == 0) {
    echo "Device not found";
    exit;
}

// Fetch device data
$device_data = mysqli_fetch_assoc($device_result);

// Get device type specific details
$type_details = [];
switch ($device_data['device_type']) {
    case 'PC':
        $detail_query = "SELECT p.processor, p.ram, p.storage, p.operating_system, 
                               p.ethernet_mac, p.wifi_adapter, p.ip_address
                        FROM pc_details p
                        WHERE p.device_id = ?";
        break;
    case 'Monitor':
        $detail_query = "SELECT m.brand_model, m.resolution, m.serial_number, m.status
                        FROM monitors m
                        WHERE m.device_id = ?";
        break;
    case 'Keyboard':
        $detail_query = "SELECT k.keyboard_name, k.keyboard_type, k.serial_number, k.status
                        FROM keyboards k
                        WHERE k.device_id = ?";
        break;
    case 'Mouse':
        $detail_query = "SELECT m.mouse_name, m.mouse_type, m.serial_number, m.status
                        FROM mice m
                        WHERE m.device_id = ?";
        break;
    case 'CPU':
        $detail_query = "SELECT c.case_model, c.serial_number, c.power_supply, c.status
                        FROM cpus c
                        WHERE c.device_id = ?";
        break;
    case 'Printer':
        $detail_query = "SELECT p.printer_model, p.printer_type, p.color_capability, 
                               p.connectivity, p.serial_number
                        FROM printers p
                        WHERE p.device_id = ?";
        break;
    default:
        $detail_query = "";
}

// If we have a detail query, fetch the specific device details
if (!empty($detail_query)) {
    $detail_stmt = mysqli_prepare($conn, $detail_query);
    mysqli_stmt_bind_param($detail_stmt, "s", $device_id);
    mysqli_stmt_execute($detail_stmt);
    $detail_result = mysqli_stmt_get_result($detail_stmt);

    if (mysqli_num_rows($detail_result) > 0) {
        $type_details = mysqli_fetch_assoc($detail_result);
    }
}

// Close the database connection
mysqli_close($conn);
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
    <!-- Include jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>

    <!-- Include Sparkline Plugin -->
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
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
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
                <!-- Dashboard Link -->
                <li>
                    <a href="index.php">
                        <i class="fa-solid fa-chart-pie"></i> Dashboard
                    </a>
                </li>
                <li>
                    <a href="labs.php">
                        <i class="fa-solid fa-network-wired"></i>
                        Labs
                    </a>
                </li>
                <li>
                    <a href="addLab.php">
                        <i class="fa-solid fa-plus"></i></i><span>Add Lab</span>
                    </a>
                </li>
                <li>
                    <a href="addDevice.php">
                        <i class="fa-solid fa-plus"></i></i><span>Add Devices</span>
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
                <!-- User Info Section -->
                <div class="sub-heading">
                    <span>View Device </span>
                </div>
                <div class="user-info">
                    <i class="fa-solid fa-circle-user"></i><span class="font-rale"> Administrator
                    </span>
                </div>
            </div>
            <!-- CONTENT START of BELOW HEADER -->
            <div class="view-header">
                <span class="top-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="#3498db" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"
                        class="lucide lucide-computer">
                        <rect width="14" height="8" x="5" y="2" rx="2" />
                        <rect width="20" height="8" x="2" y="14" rx="2" />
                        <path d="M6 18h2" />
                        <path d="M12 18h6" />
                    </svg>
                </span>
                <h2><?php echo htmlspecialchars($device_data['device_name']); ?></h2>
                <span class="highlight1 float-right">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($device_data['status']); ?>
                </span>
            </div>

            <!-- card 1 - Device Overview -->
            <div class="view-card">
                <div class="details-header">
                    <span class="top-icon">
                        <svg class="card-icon" width="23" height="23" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2">
                            <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                            <line x1="8" y1="21" x2="16" y2="21"></line>
                            <line x1="12" y1="17" x2="12" y2="21"></line>
                        </svg>
                    </span>
                    <h3>
                        Device Overview
                    </h3>
                </div>
                <div class="details-content">
                    <div class="input-group">
                        <label class="detail-label">Device ID</label>
                        <div class="label-value"><?php echo htmlspecialchars($device_data['device_id']); ?></div>
                    </div>
                    <div class="input-group">
                        <label class="detail-label">Device Name</label>
                        <div class="label-value"><?php echo htmlspecialchars($device_data['device_name']); ?></div>
                    </div>
                    <div class="input-group">
                        <label class="detail-label">Category</label>
                        <div class="label-value"><?php echo htmlspecialchars($device_data['device_type']); ?></div>
                    </div>
                    <div>
                        <label class="detail-label">Assigned Lab</label>
                        <div class="label-value"><?php echo htmlspecialchars($device_data['lab_id']); ?></div>
                    </div>
                    <div>
                        <label class="detail-label">Lab Location</label>
                        <div class="label-value"><?php echo htmlspecialchars($device_data['lab_location']); ?></div>
                    </div>
                    <div>
                        <label class="detail-label">Status</label>
                        <div class="label-value"><?php echo htmlspecialchars($device_data['status']); ?></div>
                    </div>
                </div>
            </div>

            <!-- card 2 - Technical Specifications -->
            <div class="view-card">
                <div class="details-header">
                    <span class="top-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="#3498db" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-settings">
                            <path
                                d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z" />
                            <circle cx="12" cy="12" r="3" />
                        </svg>
                    </span>
                    <h3>
                        Technical Specifications
                    </h3>
                </div>
                <div class="details-content">
                    <?php
                    // Display different fields based on device type
                    switch ($device_data['device_type']) {
                        case 'PC':
                    ?>
                            <div class="input-group">
                                <label class="detail-label">Serial Number</label>
                                <div class="label-value"><?php echo isset($type_details['serial_number']) ? htmlspecialchars($type_details['serial_number']) : 'NA'; ?></div>
                            </div>
                            <div class="input-group">
                                <label class="detail-label">RAM</label>
                                <div class="label-value"><?php echo isset($type_details['ram']) ? htmlspecialchars($type_details['ram']) : 'NA'; ?></div>
                            </div>
                            <div class="input-group">
                                <label class="detail-label">Processor</label>
                                <div class="label-value"><?php echo isset($type_details['processor']) ? htmlspecialchars($type_details['processor']) : 'NA'; ?></div>
                            </div>
                            <div class="input-group">
                                <label class="detail-label">Operating System</label>
                                <div class="label-value"><?php echo isset($type_details['operating_system']) ? htmlspecialchars($type_details['operating_system']) : 'NA'; ?></div>
                            </div>
                            <div class="input-group">
                                <label class="detail-label">Storage</label>
                                <div class="label-value"><?php echo isset($type_details['storage']) ? htmlspecialchars($type_details['storage']) : 'NA'; ?></div>
                            </div>
                            <div>
                                <label class="detail-label">IP Address</label>
                                <div class="label-value"><?php echo isset($type_details['ip_address']) ? htmlspecialchars($type_details['ip_address']) : 'NA'; ?></div>
                            </div>
                            <div>
                                <label class="detail-label">Ethernet MAC Address</label>
                                <div class="label-value"><?php echo isset($type_details['ethernet_mac']) ? htmlspecialchars($type_details['ethernet_mac']) : 'NA'; ?></div>
                            </div>
                            <div>
                                <label class="detail-label">WiFi Adapter</label>
                                <div class="label-value"><?php echo isset($type_details['wifi_adapter']) ? htmlspecialchars($type_details['wifi_adapter']) : 'NA'; ?></div>
                            </div>
                        <?php
                            break;
                        case 'Monitor':
                        ?>
                            <div class="input-group">
                                <label class="detail-label">Brand/Model</label>
                                <div class="label-value"><?php echo isset($type_details['brand_model']) ? htmlspecialchars($type_details['brand_model']) : 'NA'; ?></div>
                            </div>
                            <div class="input-group">
                                <label class="detail-label">Resolution</label>
                                <div class="label-value"><?php echo isset($type_details['resolution']) ? htmlspecialchars($type_details['resolution']) : 'NA'; ?></div>
                            </div>
                            <div class="input-group">
                                <label class="detail-label">Serial Number</label>
                                <div class="label-value"><?php echo isset($type_details['serial_number']) ? htmlspecialchars($type_details['serial_number']) : 'NA'; ?></div>
                            </div>
                        <?php
                            break;
                        case 'Keyboard':
                        ?>
                            <div class="input-group">
                                <label class="detail-label">Keyboard Name</label>
                                <div class="label-value"><?php echo isset($type_details['keyboard_name']) ? htmlspecialchars($type_details['keyboard_name']) : 'NA'; ?></div>
                            </div>
                            <div class="input-group">
                                <label class="detail-label">Type</label>
                                <div class="label-value"><?php echo isset($type_details['keyboard_type']) ? htmlspecialchars($type_details['keyboard_type']) : 'NA'; ?></div>
                            </div>
                            <div class="input-group">
                                <label class="detail-label">Serial Number</label>
                                <div class="label-value"><?php echo isset($type_details['serial_number']) ? htmlspecialchars($type_details['serial_number']) : 'NA'; ?></div>
                            </div>
                        <?php
                            break;
                        case 'Mouse':
                        ?>
                            <div class="input-group">
                                <label class="detail-label">Mouse Name</label>
                                <div class="label-value"><?php echo isset($type_details['mouse_name']) ? htmlspecialchars($type_details['mouse_name']) : 'NA'; ?></div>
                            </div>
                            <div class="input-group">
                                <label class="detail-label">Type</label>
                                <div class="label-value"><?php echo isset($type_details['mouse_type']) ? htmlspecialchars($type_details['mouse_type']) : 'NA'; ?></div>
                            </div>
                            <div class="input-group">
                                <label class="detail-label">Serial Number</label>
                                <div class="label-value"><?php echo isset($type_details['serial_number']) ? htmlspecialchars($type_details['serial_number']) : 'NA'; ?></div>
                            </div>
                        <?php
                            break;
                        case 'CPU':
                        ?>
                            <div class="input-group">
                                <label class="detail-label">Case Model</label>
                                <div class="label-value"><?php echo isset($type_details['case_model']) ? htmlspecialchars($type_details['case_model']) : 'NA'; ?></div>
                            </div>
                            <div class="input-group">
                                <label class="detail-label">Power Supply</label>
                                <div class="label-value"><?php echo isset($type_details['power_supply']) ? htmlspecialchars($type_details['power_supply']) : 'NA'; ?></div>
                            </div>
                            <div class="input-group">
                                <label class="detail-label">Serial Number</label>
                                <div class="label-value"><?php echo isset($type_details['serial_number']) ? htmlspecialchars($type_details['serial_number']) : 'NA'; ?></div>
                            </div>
                        <?php
                            break;
                        case 'Printer':
                        ?>
                            <div class="input-group">
                                <label class="detail-label">Printer Model</label>
                                <div class="label-value"><?php echo isset($type_details['printer_model']) ? htmlspecialchars($type_details['printer_model']) : 'NA'; ?></div>
                            </div>
                            <div class="input-group">
                                <label class="detail-label">Type</label>
                                <div class="label-value"><?php echo isset($type_details['printer_type']) ? htmlspecialchars($type_details['printer_type']) : 'NA'; ?></div>
                            </div>
                            <div class="input-group">
                                <label class="detail-label">Color Capability</label>
                                <div class="label-value"><?php echo isset($type_details['color_capability']) ? htmlspecialchars($type_details['color_capability']) : 'NA'; ?></div>
                            </div>
                            <div class="input-group">
                                <label class="detail-label">Connectivity</label>
                                <div class="label-value"><?php echo isset($type_details['connectivity']) ? htmlspecialchars($type_details['connectivity']) : 'NA'; ?></div>
                            </div>
                            <div class="input-group">
                                <label class="detail-label">Serial Number</label>
                                <div class="label-value"><?php echo isset($type_details['serial_number']) ? htmlspecialchars($type_details['serial_number']) : 'NA'; ?></div>
                            </div>
                    <?php
                            break;
                        default:
                            echo '<div class="label-value">No specific details available for this device type.</div>';
                    }
                    ?>
                </div>
            </div>

            <!-- card 3 - Quick Actions -->
            <div class="view-card">
                <div class="details-header">
                    <span class="top-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="23" height="23" viewBox="0 0 24 24" fill="none"
                            stroke="#3498db" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-arrow-up-right">
                            <path d="M7 7h10v10" />
                            <path d="M7 17 17 7" />
                        </svg>
                    </span>
                    <h3>
                        Quick Actions
                    </h3>
                </div>
                <div class="action-content">
                    <div class="quick-action">
                        <button class="btnPopup btnPopup-edit btnDetails" onclick="location.href='editDevice.php?id=<?php echo urlencode($device_data['device_id']); ?>'">
                            <span class="top-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                    fill="none" stroke="white" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" class="lucide lucide-pencil">
                                    <path
                                        d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z" />
                                    <path d="m15 5 4 4" />
                                </svg>
                            </span>
                            Edit Details
                        </button>
                    </div>
                    <div class="quick-action">
                        <button class="btnPopup btnPopup-view btnDetails" onclick="location.href='viewGrievance.php?device_id=<?php echo urlencode($device_data['device_id']); ?>'">
                            <span class="top-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                    fill="none" stroke="white" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" class="lucide lucide-eye">
                                    <path
                                        d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0" />
                                    <circle cx="12" cy="12" r="3" />
                                </svg>
                            </span>
                            View Grievance
                        </button>
                    </div>
                    <div class="quick-action">
                        <button class="btnPopup btnPopup-delete btnDetails" onclick="location.href='raiseGrievance.php?device_id=<?php echo urlencode($device_data['device_id']); ?>'">
                            <span class="top-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                    fill="none" stroke="white" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" class="lucide lucide-message-circle-warning">
                                    <path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z" />
                                    <path d="M12 8v4" />
                                    <path d="M12 16h.01" />
                                </svg>
                            </span>
                            Raise
                            Grievance
                        </button>
                    </div>
                </div>
            </div>

            <!-- CONTENT END of BELOW HEADER  -->
        </div>
        <!-- CONTENT END  -->

    </div>
</body>

</html>