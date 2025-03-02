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
    $prefix = '';
    switch ($type) {
        case 'PC':
            $prefix = 'PC';
            break;
        case 'Monitor':
            $prefix = 'MON';
            break;
        case 'Keyboard':
            $prefix = 'KEY';
            break;
        case 'Mouse':
            $prefix = 'MOU';
            break;
        case 'CPU':
            $prefix = 'CPU';
            break;
        case 'Printer':
            $prefix = 'PRT';
            break;
        default:
            $prefix = 'DEV';
            break;
    }

    $year = date('Y');
    $query = "SELECT device_id FROM devices WHERE device_id LIKE '$prefix-$year-%' ORDER BY device_id DESC LIMIT 1";
    $result = $conn->query($query);

    if ($result && $result->num_rows > 0) {
        $lastId = $result->fetch_assoc()['device_id'];
        $lastNum = (int) substr($lastId, -4);
        $newNum = $lastNum + 1;
    } else {
        $newNum = 1;
    }

    $serial = str_pad($newNum, 4, '0', STR_PAD_LEFT);
    return "$prefix-$year-$serial";
}

// Get all active labs
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
$pcData = [];

// Process PC form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_pc'])) {
    // Get common device data
    $lab_id = $_POST['lab_id'] ?? '';
    $device_name = $_POST['pc_name'] ?? '';
    $quantity = (int)($_POST['pc_quantity'] ?? 1);
    $device_status = $_POST['pc_status'] ?? 'Active';
    $serial_number = $_POST['pc_serial_number'] ?? '';

    // Get PC specific data
    $processor = $_POST['processor'] ?? '';
    $ram = $_POST['ram'] ?? '';
    $storage = $_POST['storage'] ?? '';
    $os = $_POST['operating_system'] ?? '';
    $ethernet_mac = $_POST['ethernet_mac'] ?? '';
    $wifi_adapter = $_POST['wifi_adapter'] ?? '';
    $ip_address = $_POST['ip_address'] ?? '';

    // Get monitor data
    $monitor_brand = $_POST['monitor_brand'] ?? '';
    $monitor_resolution = $_POST['monitor_resolution'] ?? '';
    $monitor_serial = $_POST['monitor_serial'] ?? '';
    $monitor_status = $_POST['monitor_status'] ?? 'Active';

    // Get keyboard data
    $keyboard_name = $_POST['keyboard_name'] ?? '';
    $keyboard_type = $_POST['keyboard_type'] ?? '';
    $keyboard_serial = $_POST['keyboard_serial'] ?? '';
    $keyboard_status = $_POST['keyboard_status'] ?? 'Active';

    // Get mouse data
    $mouse_name = $_POST['mouse_name'] ?? '';
    $mouse_type = $_POST['mouse_type'] ?? '';
    $mouse_serial = $_POST['mouse_serial'] ?? '';
    $mouse_status = $_POST['mouse_status'] ?? 'Active';

    // Get CPU data
    $case_model = $_POST['case_model'] ?? '';
    $cpu_serial = $_POST['cpu_serial'] ?? '';
    $power_supply = $_POST['power_supply'] ?? '';
    $cpu_status = $_POST['cpu_status'] ?? 'Active';

    // Validation
    $errors = [];
    if (empty($lab_id)) $errors[] = "Lab selection is required";
    if (empty($device_name)) $errors[] = "PC name is required";
    if (empty($processor)) $errors[] = "Processor information is required";
    if (empty($ram)) $errors[] = "RAM information is required";
    if (empty($storage)) $errors[] = "Storage information is required";

    if (empty($errors)) {
        // Loop for quantity
        for ($i = 0; $i < $quantity; $i++) {
            // Start transaction
            $conn->begin_transaction();

            try {
                // Generate device IDs
                $pc_device_id = generateDeviceId('PC', $conn);
                $monitor_device_id = generateDeviceId('Monitor', $conn);
                $keyboard_device_id = generateDeviceId('Keyboard', $conn);
                $mouse_device_id = generateDeviceId('Mouse', $conn);
                $cpu_device_id = generateDeviceId('CPU', $conn);

                // Insert main PC device
                $query = "INSERT INTO devices (device_id, lab_id, device_type, device_name, serial_number, status, created_at, updated_at) 
                          VALUES (?, ?, 'PC', ?, ?, ?, NOW(), NOW())";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("sssss", $pc_device_id, $lab_id, $device_name, $serial_number, $device_status);
                $stmt->execute();

                // Insert PC details
                $query = "INSERT INTO pc_details (device_id, processor, ram, storage, operating_system, ethernet_mac, wifi_adapter, ip_address) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ssssssss", $pc_device_id, $processor, $ram, $storage, $os, $ethernet_mac, $wifi_adapter, $ip_address);
                $stmt->execute();

                // Insert monitor
                $query = "INSERT INTO devices (device_id, lab_id, device_type, device_name, serial_number, status, created_at, updated_at) 
                          VALUES (?, ?, 'Monitor', ?, ?, ?, NOW(), NOW())";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("sssss", $monitor_device_id, $lab_id, $monitor_brand, $monitor_serial, $monitor_status);
                $stmt->execute();

                // Insert monitor details
                $query = "INSERT INTO monitors (device_id, brand_model, resolution, serial_number, status) 
                          VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("sssss", $monitor_device_id, $monitor_brand, $monitor_resolution, $monitor_serial, $monitor_status);
                $stmt->execute();

                // Insert keyboard
                $query = "INSERT INTO devices (device_id, lab_id, device_type, device_name, serial_number, status, created_at, updated_at) 
                          VALUES (?, ?, 'Keyboard', ?, ?, ?, NOW(), NOW())";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("sssss", $keyboard_device_id, $lab_id, $keyboard_name, $keyboard_serial, $keyboard_status);
                $stmt->execute();

                // Insert keyboard details
                $query = "INSERT INTO keyboards (device_id, keyboard_name, keyboard_type, serial_number, status) 
                          VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("sssss", $keyboard_device_id, $keyboard_name, $keyboard_type, $keyboard_serial, $keyboard_status);
                $stmt->execute();

                // Insert mouse
                $query = "INSERT INTO devices (device_id, lab_id, device_type, device_name, serial_number, status, created_at, updated_at) 
                          VALUES (?, ?, 'Mouse', ?, ?, ?, NOW(), NOW())";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("sssss", $mouse_device_id, $lab_id, $mouse_name, $mouse_serial, $mouse_status);
                $stmt->execute();

                // Insert mouse details
                $query = "INSERT INTO mice (device_id, mouse_name, mouse_type, serial_number, status) 
                          VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("sssss", $mouse_device_id, $mouse_name, $mouse_type, $mouse_serial, $mouse_status);
                $stmt->execute();

                // Insert CPU
                $query = "INSERT INTO devices (device_id, lab_id, device_type, device_name, serial_number, status, created_at, updated_at) 
                          VALUES (?, ?, 'CPU', ?, ?, ?, NOW(), NOW())";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("sssss", $cpu_device_id, $lab_id, $case_model, $cpu_serial, $cpu_status);
                $stmt->execute();

                // Insert CPU details
                $query = "INSERT INTO cpus (device_id, case_model, serial_number, power_supply, status) 
                          VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("sssss", $cpu_device_id, $case_model, $cpu_serial, $power_supply, $cpu_status);
                $stmt->execute();

                // Commit the transaction
                $conn->commit();

                // Store first device details for modal
                if ($i === 0) {
                    $deviceDetails = [
                        'device_id' => $pc_device_id,
                        'device_name' => $device_name,
                        'device_type' => 'PC',
                        'lab_id' => $lab_id
                    ];

                    // Get lab name
                    $labQuery = "SELECT lab_name FROM labs WHERE lab_id = ?";
                    $stmt = $conn->prepare($labQuery);
                    $stmt->bind_param("s", $lab_id);
                    $stmt->execute();
                    $labResult = $stmt->get_result();
                    if ($labResult && $labResult->num_rows > 0) {
                        $deviceDetails['lab_name'] = $labResult->fetch_assoc()['lab_name'];
                    } else {
                        $deviceDetails['lab_name'] = "Unknown Lab";
                    }
                }
            } catch (Exception $e) {
                // Rollback in case of error
                $conn->rollback();
                $message = "Error: " . $e->getMessage();
                $messageType = "error";
                break;
            }
        }

        if ($messageType !== "error") {
            $message = "Successfully added $quantity PC(s) with all peripherals!";
            $messageType = "success";
        }
    } else {
        $message = "Please correct the following errors: " . implode(", ", $errors);
        $messageType = "error";
    }
}

// Process Printer form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_printer'])) {
    $lab_id = $_POST['lab_id'] ?? '';
    $printer_model = $_POST['printer_model'] ?? '';
    $printer_type = $_POST['printer_type'] ?? '';
    $color_capability = $_POST['color_capability'] ?? '';
    $connectivity = $_POST['connectivity'] ?? '';
    $serial_number = $_POST['printer_serial'] ?? '';
    $status = $_POST['printer_status'] ?? 'Active';
    $quantity = (int)($_POST['printer_quantity'] ?? 1);

    // Validation
    $errors = [];
    if (empty($lab_id)) $errors[] = "Lab selection is required";
    if (empty($printer_model)) $errors[] = "Printer model is required";
    if (empty($printer_type)) $errors[] = "Printer type is required";

    if (empty($errors)) {
        // Loop for quantity
        for ($i = 0; $i < $quantity; $i++) {
            // Start transaction
            $conn->begin_transaction();

            try {
                // Generate device ID
                $printer_device_id = generateDeviceId('Printer', $conn);

                // Insert printer device
                $query = "INSERT INTO devices (device_id, lab_id, device_type, device_name, serial_number, status, created_at, updated_at) 
                          VALUES (?, ?, 'Printer', ?, ?, ?, NOW(), NOW())";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("sssss", $printer_device_id, $lab_id, $printer_model, $serial_number, $status);
                $stmt->execute();

                // Insert printer details
                $query = "INSERT INTO printers (device_id, printer_model, printer_type, color_capability, connectivity, serial_number) 
                          VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ssssss", $printer_device_id, $printer_model, $printer_type, $color_capability, $connectivity, $serial_number);
                $stmt->execute();

                // Commit the transaction
                $conn->commit();

                // Store first device details for modal
                if ($i === 0) {
                    $deviceDetails = [
                        'device_id' => $printer_device_id,
                        'device_name' => $printer_model,
                        'device_type' => 'Printer',
                        'lab_id' => $lab_id
                    ];

                    // Get lab name
                    $labQuery = "SELECT lab_name FROM labs WHERE lab_id = ?";
                    $stmt = $conn->prepare($labQuery);
                    $stmt->bind_param("s", $lab_id);
                    $stmt->execute();
                    $labResult = $stmt->get_result();
                    if ($labResult && $labResult->num_rows > 0) {
                        $deviceDetails['lab_name'] = $labResult->fetch_assoc()['lab_name'];
                    } else {
                        $deviceDetails['lab_name'] = "Unknown Lab";
                    }
                }
            } catch (Exception $e) {
                // Rollback in case of error
                $conn->rollback();
                $message = "Error: " . $e->getMessage();
                $messageType = "error";
                break;
            }
        }

        if ($messageType !== "error") {
            $message = "Successfully added $quantity printer(s)!";
            $messageType = "success";
        }
    } else {
        $message = "Please correct the following errors: " . implode(", ", $errors);
        $messageType = "error";
    }
}

// Function to retrieve the last entered PC data for the "Copy Last Entry" feature
function getLastPCEntry($conn)
{
    $query = "SELECT d.device_name, d.lab_id, d.status, pd.processor, pd.ram, pd.storage, pd.operating_system, 
              pd.ethernet_mac, pd.wifi_adapter, pd.ip_address
              FROM devices d
              JOIN pc_details pd ON d.device_id = pd.device_id
              WHERE d.device_type = 'PC'
              ORDER BY d.created_at DESC
              LIMIT 1";

    $result = $conn->query($query);
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return null;
}

// Get last PC entry for Copy Last Entry feature
$lastPCEntry = getLastPCEntry($conn);

// Get last peripherals
function getLastPeripheralData($conn, $type, $detailTable)
{
    $query = "SELECT d.device_name, d.status, d.serial_number, t.*
              FROM devices d
              JOIN $detailTable t ON d.device_id = t.device_id
              WHERE d.device_type = ?
              ORDER BY d.created_at DESC
              LIMIT 1";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $type);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return null;
}

$lastMonitor = getLastPeripheralData($conn, 'Monitor', 'monitors');
$lastKeyboard = getLastPeripheralData($conn, 'Keyboard', 'keyboards');
$lastMouse = getLastPeripheralData($conn, 'Mouse', 'mice');
$lastCPU = getLastPeripheralData($conn, 'CPU', 'cpus');
$lastPrinter = getLastPeripheralData($conn, 'Printer', 'printers');

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
    <style>
        /* Add any custom styles for error/success messages */
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
                <li class="active">
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
                    <span>Add Devices </span>
                </div>
                <div class="user-info">
                    <!-- <img alt="User Avatar" src="https://placehold.co/30x30" /> -->
                    <i class="fa-solid fa-circle-user"></i><span class="font-rale"> Administrator
                    </span>
                </div>
            </div>
            <!-- CONTENT START of BELOW HEADER -->
            <div class="lab-management-container">
                <div class="form-wrapper">
                    <!--  Details Section -->
                    <div class="form-section" id="lab-details">
                        <div class="section-header">
                            <h2 class="section-title">Register New Devices</h2>
                            <div class="copy-btn">
                                <button type="button" class="btn1 btn-secondary1" id="copyLastEntryBtn">
                                    <i class="fa-solid fa-file-import"></i> Copy Last Entry
                                </button>
                            </div>
                        </div>

                        <?php if (!empty($message)): ?>
                            <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'error'; ?>">
                                <?php echo $message; ?>
                            </div>
                        <?php endif; ?>

                        <div class="selectLabDevice">
                            <div class="input-group" style="margin-bottom: 0rem !important;">
                                <label class="input-label">Select Device <span>*</span></label>
                                <select class="form-input" id="deviceTypeSelect" required onchange="handleDeviceSelection(this.value)">
                                    <option value="">Select Device</option>
                                    <option value="pc">PC</option>
                                    <option value="printer">Printer</option>
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

                        <!-- PC FORM -->
                        <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>" id="pcForm" style="display: none;">
                            <input type="hidden" name="lab_id" id="pc_lab_id">

                            <div class="stepper">
                                <div class="stepper-item active" data-step="1">1. PC Details</div>
                                <div class="stepper-item" data-step="2">2. Monitor</div>
                                <div class="stepper-item" data-step="3">3. Keyboard</div>
                                <div class="stepper-item" data-step="4">4. Mouse</div>
                                <div class="stepper-item" data-step="5">5. CPU</div>
                                <div class="stepper-item" data-step="6">6. Connectivity</div>
                            </div>

                            <!-- PC Details -->
                            <div class="step-content" id="step-1">
                                <div class="grid-3col">
                                    <div class="input-group">
                                        <label class="input-label">PC Name <span>*</span></label>
                                        <input type="text" name="pc_name" class="form-input" placeholder="Enter name (e.g., HP PC)" required>
                                    </div>
                                    <div class="input-group">
                                        <label class="input-label">PC Quantity <span>*</span></label>
                                        <input type="number" name="pc_quantity" class="form-input" value="1" min="1" placeholder="Total PCs to add (e.g., 10)" required>
                                    </div>
                                    <div class="input-group">
                                        <label class="input-label">PC Code </label>
                                        <input type="text" class="no-hover form-input" value="Device Code will be Auto-Generated" disabled>
                                    </div>
                                    <div class="input-group">
                                        <label class="input-label">Serial Number </label>
                                        <input type="text" name="pc_serial_number" class="form-input" placeholder="Enter Serial Number">
                                    </div>
                                    <div class="input-group">
                                        <label class="input-label">Processor <span>*</span></label>
                                        <input type="text" name="processor" class="form-input" placeholder="Enter processor (e.g. Intel Core i7)" required>
                                    </div>
                                    <div class="input-group">
                                        <label class="input-label">RAM <span>*</span></label>
                                        <input type="text" name="ram" class="form-input" placeholder="Enter RAM (e.g. 4GB DDR4)" required>
                                    </div>
                                    <div class="input-group">
                                        <label class="input-label">Storage (HDD/SSD) <span>*</span></label>
                                        <input type="text" name="storage" class="form-input" placeholder="Enter Storage (e.g. 1TB HDD)" required>
                                    </div>
                                    <div class="input-group">
                                        <label class="input-label">Operating System </label>
                                        <input type="text" name="operating_system" class="form-input" placeholder="Enter Operating System (e.g. Windows 10)">
                                    </div>
                                    <div class="input-group">
                                        <label class="input-label">PC Status <span>*</span></label>
                                        <select name="pc_status" class="form-input" required>
                                            <option value="Active">Active</option>
                                            <option value="Under Repair">Under Repair</option>
                                            <option value="Inactive">Inactive</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Monitor -->
                            <div class="step-content" id="step-2" style="display: none;">
                                <div class="grid-3col">
                                    <div class="input-group">
                                        <label class="input-label">Monitor Brand & Model <span>*</span></label>
                                        <input type="text" name="monitor_brand" class="form-input" placeholder="Enter Monitor Brand & Model (e.g., Dell 22-inch LED)" required>
                                    </div>
                                    <div class="input-group">
                                        <label class="input-label">Monitor Resolution</label>
                                        <input type="text" name="monitor_resolution" class="form-input" placeholder="Enter Resolution (e.g., 1920x1080)">
                                    </div>
                                    <div class="input-group">
                                        <label class="input-label">Monitor Serial Number</label>
                                        <input type="text" name="monitor_serial" class="form-input" placeholder="Enter Serial Number">
                                    </div>
                                    <div class="input-group">
                                        <label class="input-label">Monitor Status <span>*</span></label>
                                        <select name="monitor_status" class="form-input" required>
                                            <option value="Active">Active</option>
                                            <option value="Under Repair">Under Repair</option>
                                            <option value="Inactive">Inactive</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Keyboard -->
                            <div class="step-content" id="step-3" style="display: none;">
                                <div class="grid-3col">
                                    <div class="input-group">
                                        <label class="input-label">Keyboard Name</label>
                                        <input type="text" name="keyboard_name" class="form-input" placeholder="Enter Keyboard Name">
                                    </div>
                                    <div class="input-group">
                                        <label class="input-label">Keyboard Type <span>*</span></label>
                                        <select name="keyboard_type" class="form-input" required>
                                            <option value="">Select Type</option>
                                            <option>Wired</option>
                                            <option>Wireless</option>
                                            <option>Mechanical</option>
                                            <option>Membrane</option>
                                        </select>
                                    </div>
                                    <div class="input-group">
                                        <label class="input-label">Keyboard Serial Number</label>
                                        <input type="text" name="keyboard_serial" class="form-input" placeholder="Enter Serial Number">
                                    </div>
                                    <div class="input-group">
                                        <label class="input-label">Keyboard Status <span>*</span></label>
                                        <select name="keyboard_status" class="form-input" required>
                                            <option value="Active">Active</option>
                                            <option value="Under Repair">Under Repair</option>
                                            <option value="Inactive">Inactive</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Mouse -->
                            <div class="step-content" id="step-4" style="display: none;">
                                <div class="grid-3col">
                                    <div class="input-group">
                                        <label class="input-label">Mouse Name</label>
                                        <input type="text" name="mouse_name" class="form-input" placeholder="Enter Mouse Name">
                                    </div>
                                    <div class="input-group">
                                        <label class="input-label">Mouse Type <span>*</span></label>
                                        <select name="mouse_type" class="form-input" required>
                                            <option value="">Select Type</option>
                                            <option>Wired</option>
                                            <option>Wireless</option>
                                        </select>
                                    </div>
                                    <div class="input-group">
                                        <label class="input-label">Mouse Serial Number</label>
                                        <input type="text" name="mouse_serial" class="form-input" placeholder="Enter Serial Number">
                                    </div>
                                    <div class="input-group">
                                        <label class="input-label">Mouse Status <span>*</span></label>
                                        <select name="mouse_status" class="form-input" required>
                                            <option value="Active">Active</option>
                                            <option value="Under Repair">Under Repair</option>
                                            <option value="Inactive">Inactive</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- CPU -->
                            <div class="step-content" id="step-5" style="display: none;">
                                <div class="grid-3col">
                                    <div class="input-group">
                                        <label class="input-label">CPU Case Model</label>
                                        <input type="text" name="case_model" class="form-input" placeholder="Enter Case Model (e.g., Cooler Master Mid-Tower)">
                                    </div>
                                    <div class="input-group">
                                        <label class="input-label">CPU Serial Number</label>
                                        <input type="text" name="cpu_serial" class="form-input" placeholder="Enter Serial Number">
                                    </div>
                                    <div class="input-group">
                                        <label class="input-label">Power Supply Unit (PSU) <span>*</span></label>
                                        <input type="text" name="power_supply" class="form-input" placeholder="Enter PSU (e.g., 450W)" required>
                                    </div>
                                    <div class="input-group">
                                        <label class="input-label">CPU Status <span>*</span></label>
                                        <select name="cpu_status" class="form-input" required>
                                            <option value="Active">Active</option>
                                            <option value="Under Repair">Under Repair</option>
                                            <option value="Inactive">Inactive</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Connectivity -->
                            <div class="step-content" id="step-6" style="display: none;">
                                <div class="grid-3col">
                                    <div class="input-group">
                                        <label class="input-label">Ethernet MAC Address</label>
                                        <input type="text" name="ethernet_mac" class="form-input" placeholder="Enter MAC Address">
                                    </div>
                                    <div class="input-group">
                                        <label class="input-label">WiFi Adapter</label>
                                        <input type="text" name="wifi_adapter" class="form-input" placeholder="Enter WiFi Adapter Model (if any)">
                                    </div>
                                    <div class="input-group">
                                        <label class="input-label">IP Address</label>
                                        <input type="text" name="ip_address" class="form-input" placeholder="Enter IP Address (if static)">
                                    </div>
                                </div>
                            </div>

                            <!-- Submit Button Inside Form -->
                            <div class="form-actions">
                                <button type="submit" name="submit_pc" class="btn btn-primary">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                                        fill="none" stroke="white" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round" class="lucide lucide-circle-plus">
                                        <circle cx="12" cy="12" r="10" />
                                        <path d="M8 12h8" />
                                        <path d="M12 8v8" />
                                    </svg> Add PC
                                </button>
                            </div>
                        </form>

                        <!-- Printer Form -->
                        <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>" id="printerForm" style="display: none;">
                            <input type="hidden" name="lab_id" id="printer_lab_id">
                            <div class="printerInput">
                                <h4>Printer Details</h4>
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
                                        <label class="input-label">Serial Number </label>
                                        <input type="text" name="printer_serial" class="form-input" placeholder="Enter Serial Number">
                                    </div>
                                    <div class="input-group">
                                        <label class="input-label">Quantity <span>*</span></label>
                                        <input type="number" name="printer_quantity" class="form-input" value="1" min="1" required>
                                    </div>
                                    <div class="input-group">
                                        <label class="input-label">Printer Status <span>*</span></label>
                                        <select name="printer_status" class="form-input" required>
                                            <option value="Active">Active</option>
                                            <option value="In-Active">In-Active</option>
                                            <option value="Under Repair">Under Repair</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
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
            <!-- CONTENT END of BELOW HEADER -->
        </div>

        <!-- Success Modal -->
        <div id="device-added-modal" class="modal-overlay" style="display: none;">
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
                            <div class="detail-value" id="device-name"></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label-new">Device ID:</div>
                            <div class="detail-value" id="device-id"></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label-new">Category:</div>
                            <div class="detail-value" id="device-category"></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label-new">Location:</div>
                            <div class="detail-value" id="device-location"></div>
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
        // Handle device selection
        function handleDeviceSelection(value) {
            const pcForm = document.getElementById('pcForm');
            const printerForm = document.getElementById('printerForm');
            const labId = document.getElementById('labSelect').value;

            if (value === 'pc') {
                pcForm.style.display = 'block';
                printerForm.style.display = 'none';
                document.getElementById('pc_lab_id').value = labId;
            } else if (value === 'printer') {
                window.location.href = 'addPrinter.php';
            } else {
                pcForm.style.display = 'none';
                printerForm.style.display = 'none';
            }
        }

        // Update hidden lab_id fields when lab selection changes
        document.getElementById('labSelect').addEventListener('change', function() {
            const deviceType = document.getElementById('deviceTypeSelect').value;
            const labId = this.value;

            if (deviceType === 'pc') {
                document.getElementById('pc_lab_id').value = labId;
            } else if (deviceType === 'printer') {
                document.getElementById('printer_lab_id').value = labId;
            }
        });

        // Copy Last Entry functionality
        document.getElementById('copyLastEntryBtn').addEventListener('click', function() {
            const deviceType = document.getElementById('deviceTypeSelect').value;

            if (deviceType === 'pc') {
                <?php if ($lastPCEntry): ?>
                    document.querySelector('[name="pc_name"]').value = '<?php echo addslashes($lastPCEntry['device_name'] ?? ''); ?>';
                    document.querySelector('[name="pc_serial_number"]').value = '<?php echo addslashes($lastPCEntry['serial_number'] ?? ''); ?>';
                    document.querySelector('[name="processor"]').value = '<?php echo addslashes($lastPCEntry['processor'] ?? ''); ?>';
                    document.querySelector('[name="ram"]').value = '<?php echo addslashes($lastPCEntry['ram'] ?? ''); ?>';
                    document.querySelector('[name="storage"]').value = '<?php echo addslashes($lastPCEntry['storage'] ?? ''); ?>';
                    document.querySelector('[name="operating_system"]').value = '<?php echo addslashes($lastPCEntry['operating_system'] ?? ''); ?>';
                    document.querySelector('[name="ethernet_mac"]').value = '<?php echo addslashes($lastPCEntry['ethernet_mac'] ?? ''); ?>';
                    document.querySelector('[name="wifi_adapter"]').value = '<?php echo addslashes($lastPCEntry['wifi_adapter'] ?? ''); ?>';
                    document.querySelector('[name="ip_address"]').value = '<?php echo addslashes($lastPCEntry['ip_address'] ?? ''); ?>';
                    document.querySelector('[name="pc_status"]').value = '<?php echo addslashes($lastPCEntry['status'] ?? ''); ?>';
                <?php endif; ?>

                <?php if ($lastMonitor): ?>
                    document.querySelector('[name="monitor_brand"]').value = '<?php echo addslashes($lastMonitor['brand_model'] ?? ''); ?>';
                    document.querySelector('[name="monitor_resolution"]').value = '<?php echo addslashes($lastMonitor['resolution'] ?? ''); ?>';
                    document.querySelector('[name="monitor_serial"]').value = '<?php echo addslashes($lastMonitor['serial_number'] ?? ''); ?>';
                    document.querySelector('[name="monitor_status"]').value = '<?php echo addslashes($lastMonitor['status'] ?? ''); ?>';
                <?php endif; ?>

                <?php if ($lastKeyboard): ?>
                    document.querySelector('[name="keyboard_name"]').value = '<?php echo addslashes($lastKeyboard['keyboard_name'] ?? ''); ?>';
                    document.querySelector('[name="keyboard_type"]').value = '<?php echo addslashes($lastKeyboard['keyboard_type'] ?? ''); ?>';
                    document.querySelector('[name="keyboard_serial"]').value = '<?php echo addslashes($lastKeyboard['serial_number'] ?? ''); ?>';
                    document.querySelector('[name="keyboard_status"]').value = '<?php echo addslashes($lastKeyboard['status'] ?? ''); ?>';
                <?php endif; ?>

                <?php if ($lastMouse): ?>
                    document.querySelector('[name="mouse_name"]').value = '<?php echo addslashes($lastMouse['mouse_name'] ?? ''); ?>';
                    document.querySelector('[name="mouse_type"]').value = '<?php echo addslashes($lastMouse['mouse_type'] ?? ''); ?>';
                    document.querySelector('[name="mouse_serial"]').value = '<?php echo addslashes($lastMouse['serial_number'] ?? ''); ?>';
                    document.querySelector('[name="mouse_status"]').value = '<?php echo addslashes($lastMouse['status'] ?? ''); ?>';
                <?php endif; ?>

                <?php if ($lastCPU): ?>
                    document.querySelector('[name="case_model"]').value = '<?php echo addslashes($lastCPU['case_model'] ?? ''); ?>';
                    document.querySelector('[name="cpu_serial"]').value = '<?php echo addslashes($lastCPU['serial_number'] ?? ''); ?>';
                    document.querySelector('[name="power_supply"]').value = '<?php echo addslashes($lastCPU['power_supply'] ?? ''); ?>';
                    document.querySelector('[name="cpu_status"]').value = '<?php echo addslashes($lastCPU['status'] ?? ''); ?>';
                <?php endif; ?>
            } else if (deviceType === 'printer') {
                <?php if ($lastPrinter): ?>
                    document.querySelector('[name="printer_model"]').value = '<?php echo addslashes($lastPrinter['printer_model'] ?? ''); ?>';
                    document.querySelector('[name="printer_type"]').value = '<?php echo addslashes($lastPrinter['printer_type'] ?? ''); ?>';
                    document.querySelector('[name="color_capability"]').value = '<?php echo addslashes($lastPrinter['color_capability'] ?? ''); ?>';
                    document.querySelector('[name="connectivity"]').value = '<?php echo addslashes($lastPrinter['connectivity'] ?? ''); ?>';
                    document.querySelector('[name="printer_serial"]').value = '<?php echo addslashes($lastPrinter['serial_number'] ?? ''); ?>';
                    document.querySelector('[name="printer_status"]').value = '<?php echo addslashes($lastPrinter['status'] ?? ''); ?>';
                <?php endif; ?>
            }
        });

        // Modal handling
        const deviceAddedModal = document.getElementById('device-added-modal');
        const deviceAddedDoneBtn = document.getElementById('device-done-btnModal');

        deviceAddedDoneBtn.addEventListener('click', function() {
            deviceAddedModal.style.display = 'none';
        });

        <?php if ($showModal): ?>
            document.addEventListener('DOMContentLoaded', function() {
                document.getElementById('device-name').textContent = '<?php echo addslashes($deviceDetails['device_name']); ?>';
                document.getElementById('device-id').textContent = '<?php echo addslashes($deviceDetails['device_id']); ?>';
                document.getElementById('device-category').textContent = '<?php echo addslashes($deviceDetails['device_type']); ?>';
                document.getElementById('device-location').textContent = '<?php echo addslashes($deviceDetails['lab_name']); ?>';
                deviceAddedModal.style.display = 'flex';
            });
        <?php endif; ?>

        // Stepper navigation
        document.querySelectorAll('.stepper-item').forEach((item, index) => {
            item.addEventListener('click', () => {
                document.querySelectorAll('.step-content').forEach(content => {
                    content.style.display = 'none';
                });
                document.querySelectorAll('.stepper-item').forEach(step => {
                    step.classList.remove('active');
                });
                const contentId = `step-${index + 1}`;
                document.getElementById(contentId).style.display = 'block';
                item.classList.add('active');
            });
        });

        // Initialize first step
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('.stepper-item').click();
        });
    </script>
</body>

</html>