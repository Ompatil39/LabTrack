<?php
session_start();
if (isset($_SESSION["logged_in"]) !== true) {
    header("Location: login.php");
}
include 'db.php';

// Get lab ID from URL parameter
$lab_id = isset($_GET['id']) ? $_GET['id'] : '';
$lab_exists = false;

// Pagination and filter parameters
$items_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $items_per_page;

// Initialize variables with default values
$lab = array(
    'lab_id' => 'N/A',
    'lab_name' => 'N/A',
    'status' => 'N/A',
    'department' => 'N/A',
    'lab_incharge' => 'N/A',
    'establishment_date' => 'N/A',
    'room_capacity' => 'N/A',
    'building' => 'N/A',
    'room_number' => 'N/A'
);
$totalPCs = 0;
$availablePCs = 0;
$totalDevices = 0;
$availableDevices = 0;

// Fetch lab details if lab_id is provided
if (!empty($lab_id)) {
    $sql = "SELECT * FROM labs WHERE lab_id = '$lab_id'";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        $lab = $result->fetch_assoc();
        $lab_exists = true;

        // Get device stats
        $totalPCsql = "SELECT COUNT(*) as count FROM devices WHERE lab_id = '$lab_id' AND device_type = 'PC'";
        $availablePCsql = "SELECT COUNT(*) as count FROM devices WHERE lab_id = '$lab_id' AND device_type = 'PC' AND status = 'Active'";
        $totalDevicesSql = "SELECT COUNT(*) as count FROM devices WHERE lab_id = '$lab_id'";
        $availableDevicesSql = "SELECT COUNT(*) as count FROM devices WHERE lab_id = '$lab_id' AND status = 'Active'";

        $totalPCs = $conn->query($totalPCsql)->fetch_assoc()['count'];
        $availablePCs = $conn->query($availablePCsql)->fetch_assoc()['count'];
        $totalDevices = $conn->query($totalDevicesSql)->fetch_assoc()['count'];
        $availableDevices = $conn->query($availableDevicesSql)->fetch_assoc()['count'];
    }
}

// Handle activate/deactivate action after lab data is fetched
$statusMessage = "";
if (isset($_POST['toggle_status']) && !empty($lab_id)) {
    $newStatus = $lab['status'] === 'Active' ? 'Inactive' : 'Active';
    $updateSql = "UPDATE labs SET status = '$newStatus' WHERE lab_id = '$lab_id'";
    if ($conn->query($updateSql) === TRUE) {
        $statusMessage = "<div class='success-message'>Lab status updated successfully!</div>";
        // Refresh lab data after status change
        $sql = "SELECT * FROM labs WHERE lab_id = '$lab_id'";
        $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            $lab = $result->fetch_assoc();
        }
    } else {
        $statusMessage = "<div class='error-message'>Error updating lab status: " . $conn->error . "</div>";
    }
}

// Get total grievances count for pagination
$grievance_count_sql = "SELECT COUNT(*) as total FROM grievances WHERE lab_id = '$lab_id'";
$grievance_count_result = $conn->query($grievance_count_sql);
$total_grievances = ($grievance_count_result && $grievance_count_result->num_rows > 0)
    ? $grievance_count_result->fetch_assoc()['total']
    : 0;
function generatePagination($total_items, $items_per_page, $current_page, $base_url, $tab)
{
    $total_pages = ceil($total_items / $items_per_page);
    $pagination = '<div class="pagination">';

    if ($current_page > 1) {
        $pagination .= '<a href="' . $base_url . '&tab=' . $tab . '&page=' . ($current_page - 1) . '" class="pagination-link">Previous</a>';
    }

    for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++) {
        $pagination .= '<a href="' . $base_url . '&tab=' . $tab . '&page=' . $i . '"';
        if ($i == $current_page) $pagination .= ' class="pagination-link active"';
        else $pagination .= ' class="pagination-link"';
        $pagination .= '>' . $i . '</a>';
    }

    if ($current_page < $total_pages) {
        $pagination .= '<a href="' . $base_url . '&tab=' . $tab . '&page=' . ($current_page + 1) . '" class="pagination-link">Next</a>';
    }

    $pagination .= '</div>';
    return $pagination;
}

// Get total counts for pagination
$total_pcs = $conn->query("SELECT COUNT(*) as count FROM devices WHERE lab_id = '$lab_id' AND device_type = 'PC'")->fetch_assoc()['count'];
$total_devices = $conn->query("SELECT COUNT(*) as count FROM devices WHERE lab_id = '$lab_id' AND device_type != 'PC'")->fetch_assoc()['count'];


?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="../public/images/logo.svg" />
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
                <li class="active">
                    <a href="labs.php">
                        <i class="fa-solid fa-network-wired"></i>
                        <span>Lab Details</span>
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
            <div class="log-out">
                <a href="logout.php" class="none">
                    <span><i class="fa-solid fa-arrow-right-from-bracket"></i></span> Logout
                </a>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="main-content">
            <!-- Header -->
            <div class="header">
                <div class="sub-heading"><span>Overview</span></div>
                <div class="user-info" onclick="window.location.href = 'profileManage.php';" style="margin-right: 0.5rem;">
                    <i class="fa-solid fa-circle-user"></i>
                    <span class="font-rale"><?php echo htmlspecialchars(strtoupper($_SESSION['username']) ?? 'User');  ?></span>
                </div>
            </div>

            <!-- CONTENT START BELOW HEADER -->
            <!-- Display message if lab doesn't exist -->
            <?php if (!empty($lab_id) && !$lab_exists): ?>
                <div class="error-message">Lab with ID <?php echo htmlspecialchars($lab_id); ?> not found!</div>
            <?php elseif (empty($lab_id)): ?>
                <div class="error-message">No lab ID specified!</div>
            <?php else: ?>

                <!-- Status message for deactivation -->
                <?php echo $statusMessage; ?>

                <!-- LAB SUMMARY CARD -->
                <section class="lab-cards">
                    <div class="detail-card">
                        <header class="card-header">
                            <h2 class="card-title font-number"><?php echo htmlspecialchars($lab['lab_id']); ?></h2>
                            <span class="status-chip lab-status <?php echo $lab['status'] == 'Active' ? 'highlight1' : 'highlight1-red'; ?>">
                                <i class="fas <?php echo $lab['status'] == 'Active' ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                                <?php echo htmlspecialchars($lab['status']); ?>
                            </span>
                            <div class="location">
                                <i class="fa-solid fa-location-dot"></i>
                                <span><?php echo htmlspecialchars($lab['building']) . ' - Room ' . htmlspecialchars($lab['room_number']); ?></span>
                            </div>
                        </header>

                        <hr class="card-divider">

                        <section class="lab-summary">
                            <h3 class="section-title1">Lab Overview</h3>
                            <div class="stats-grid">
                                <div class="stat-card">
                                    <div class="stat-value font-number"><?php echo $totalPCs; ?></div>
                                    <div class="stat-label">Total PC</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-value font-number"><?php echo $availablePCs; ?></div>
                                    <div class="stat-label">Available PC</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-value font-number"><?php echo $totalDevices; ?></div>
                                    <div class="stat-label">Total Devices</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-value font-number"><?php echo $availableDevices; ?></div>
                                    <div class="stat-label">Available Devices</div>
                                </div>
                            </div>
                        </section>

                        <!-- <section class="grievance-summary margin-bottom">
                            <h3 class="section-title1">Grievance Overview</h3>
                            <div class="grievance-stats">
                                <div class="grievance-stat resolved">
                                    <span class="stat-value font-number"><?php echo $resolvedGrievances; ?></span>
                                    <i class="fas fa-check-circle stat-icon"></i>
                                    <span class="stat-label">Resolved</span>
                                </div>
                                <div class="grievance-stat in-progress">
                                    <span class="stat-value font-number"><?php echo $inProgressGrievances; ?></span>
                                    <i class="fa-solid fa-spinner stat-icon"></i>
                                    <span class="stat-label">In Progress</span>
                                </div>
                                <div class="grievance-stat pending">
                                    <span class="stat-value font-number"><?php echo $pendingGrievances; ?></span>
                                    <i class="fas fa-exclamation-circle stat-icon"></i>
                                    <span class="stat-label">Pending</span>
                                </div>
                            </div>
                        </section> -->

                        <section class="general-summary">
                            <h3 class="section-title1" style="margin-bottom: 1rem !important;">Lab Details</h3>
                            <div class="details-content">
                                <div class="input-group">
                                    <label class="detail-label">Lab Name</label>
                                    <div class="label-value"><?php echo htmlspecialchars($lab['lab_name']); ?></div>
                                </div>

                                <div class="input-group">
                                    <label class="detail-label">Lab Code</label>
                                    <div class="label-value"><?php echo htmlspecialchars($lab['lab_id']); ?></div>
                                </div>

                                <div class="input-group">
                                    <label class="detail-label">Department</label>
                                    <div class="label-value"><?php echo htmlspecialchars($lab['department']); ?></div>
                                </div>

                                <div class="input-group">
                                    <label class="detail-label">Lab In-Charge</label>
                                    <div class="label-value"><?php echo htmlspecialchars($lab['lab_incharge']); ?></div>
                                </div>

                                <div class="input-group">
                                    <label class="detail-label">Establishment Date</label>
                                    <div class="label-value"><?php echo date('F j, Y', strtotime($lab['establishment_date'])); ?></div>
                                </div>

                                <div class="input-group">
                                    <label class="detail-label">Room Capacity</label>
                                    <div class="label-value"><?php echo htmlspecialchars($lab['room_capacity']); ?></div>
                                </div>
                            </div>
                        </section>
                    </div>

                    <!-- TABBED MENU -->
                    <section class="lab-tabs">
                        <div class="tabs-container">
                            <!-- Tab Navigation -->
                            <nav class="tabs-nav">
                                <button class="tab-link <?php echo $active_tab === 'pcs' ? 'active' : ''; ?>" data-tab="pcs">Workstations</button>
                                <button class="tab-link <?php echo $active_tab === 'devices' ? 'active' : ''; ?>" data-tab="devices">Devices</button>
                                <button class="tab-link <?php echo $active_tab === 'grievance' ? 'active' : ''; ?>" data-tab="grievance">Grievance</button>
                            </nav>

                            <!-- Tab 1 Content - Workstations -->
                            <div class="tab-content <?php echo $active_tab === 'pcs' ? 'active' : ''; ?>" id="pcs">
                                <div class="tab-header">
                                    <h3>PC List</h3>
                                </div>
                                <div class="header1">
                                    <div class="filters">
                                        <input class="search-input" type="text" id="pcSearch" placeholder="Search using ID, Name...">
                                        <select id="pcStatusFilter">
                                            <option value="">All Status</option>
                                            <option value="Active">Active</option>
                                            <option value="Under Repair">Under Repair</option>
                                            <option value="InActive">InActive</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="table-container">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Device ID</th>
                                                <th>Device Name</th>
                                                <th>Category</th>
                                                <th>PC</th>
                                                <th>Status</th>
                                                <th>Remarks</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="pcTableBody">
                                            <?php
                                            if ($lab_exists) {
                                                $pcSql = "SELECT d.device_id, d.device_name, d.device_type, 
                            COALESCE(p.pc_id, 'N/A') as pc_id, d.status, 'Working' as remarks 
                            FROM devices d 
                            LEFT JOIN pc_details p ON d.device_id = p.device_id 
                            WHERE d.lab_id = '$lab_id' AND d.device_type = 'PC' 
                            LIMIT $offset, $items_per_page";
                                                $pcResult = $conn->query($pcSql);

                                                if ($pcResult && $pcResult->num_rows > 0) {
                                                    while ($row = $pcResult->fetch_assoc()) {
                                                        echo "<tr>";
                                                        echo "<td>" . htmlspecialchars($row['device_id']) . "</td>";
                                                        echo "<td>" . htmlspecialchars($row['device_name']) . "</td>";
                                                        echo "<td>" . htmlspecialchars($row['device_type']) . "</td>";
                                                        echo "<td>" . htmlspecialchars($row['pc_id']) . "</td>";
                                                        echo "<td><span class='status status-" . strtolower(str_replace(' ', '-', $row['status'])) . "'>" . htmlspecialchars($row['status']) . "</span></td>";
                                                        echo "<td>" . htmlspecialchars($row['remarks']) . "</td>";
                                                        echo "<td>
                                    <a href='viewDevice.php?id=" . $row['device_id'] . "' class='none'>
                                        <button class='btn-icon view-btn'><i class='fas fa-eye'></i></button>
                                    </a>
                                    <a href='../edit/deviceRouter.php?id=" . $row['device_id'] . "' class='none'>
                                        <button class='btn-icon edit-btn'><i class='fa-solid fa-pen'></i></button>
                                    </a>
                                    <button class='btn-icon delete-btn delete-trigger' data-id='" . $row['device_id'] . "' data-name='" . $row['device_name'] . "'><i class='fa-solid fa-trash'></i></button>
                                  </td>";
                                                        echo "</tr>";
                                                    }
                                                } else {
                                                    echo "<tr><td colspan='7'>No PCs found for this lab</td></tr>";
                                                }
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php echo generatePagination($total_pcs, $items_per_page, $page, "?id=$lab_id", "pcs"); ?>
                            </div>

                            <!-- Replace the Devices tab content with: -->
                            <div class="tab-content <?php echo $active_tab === 'devices' ? 'active' : ''; ?>" id="devices">
                                <!-- Previous device grid content remains the same -->
                                <div class="header1">
                                    <div class="filters">
                                        <input class="search-input" type="text" id="deviceSearch" placeholder="Search using ID, Name...">
                                        <select id="deviceStatusFilter">
                                            <option value="">All Status</option>
                                            <option value="Active">Active</option>
                                            <option value="Under Repair">Under Repair</option>
                                            <option value="InActive">InActive</option>
                                        </select>
                                        <select id="categoryFilter">
                                            <option value="">All Categories</option>
                                            <option value="Monitor">Monitor</option>
                                            <option value="Printer">Printer</option>
                                            <option value="Mouse">Mouse</option>
                                            <option value="Keyboard">Keyboard</option>
                                            <option value="CPU">CPU</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="table-container">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Device ID</th>
                                                <th>Device Name</th>
                                                <th>Category</th>
                                                <th>PC</th>
                                                <th>Status</th>
                                                <th>Remarks</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="deviceTableBody">
                                            <?php
                                            if ($lab_exists) {
                                                $deviceSql = "SELECT d.device_id, d.device_name, d.device_type, 
                                CASE 
                                    WHEN d.device_type = 'PC' THEN (SELECT pc_id FROM pc_details WHERE device_id = d.device_id)
                                    ELSE 'N/A' 
                                END as pc_id, 
                                d.status, 'Working' as remarks 
                                FROM devices d 
                                WHERE d.lab_id = '$lab_id' 
                                LIMIT $offset, $items_per_page";
                                                $deviceResult = $conn->query($deviceSql);

                                                if ($deviceResult && $deviceResult->num_rows > 0) {
                                                    while ($row = $deviceResult->fetch_assoc()) {
                                                        echo "<tr>";
                                                        echo "<td>" . htmlspecialchars($row['device_id']) . "</td>";
                                                        echo "<td>" . htmlspecialchars($row['device_name']) . "</td>";
                                                        echo "<td>" . htmlspecialchars($row['device_type']) . "</td>";
                                                        echo "<td>" . htmlspecialchars($row['pc_id']) . "</td>";
                                                        echo "<td><span class='status status-" . strtolower(str_replace(' ', '-', $row['status'])) . "'>" . htmlspecialchars($row['status']) . "</span></td>";
                                                        echo "<td>" . htmlspecialchars($row['remarks']) . "</td>";
                                                        echo "<td>
        <a href='viewDevice.php?id=" . $row['device_id'] . "' class='none'>
            <button class='btn-icon view-btn'><i class='fas fa-eye'></i></button>
        </a>
        <a href='../edit/deviceRouter.php?id=" . $row['device_id'] . "' class='none'>
            <button class='btn-icon edit-btn'><i class='fa-solid fa-pen'></i></button>
        </a>
        <button class='btn-icon delete-btn delete-trigger' data-id='" . $row['device_id'] . "' data-name='" . $row['device_name'] . "'><i class='fa-solid fa-trash'></i></button>
      </td>";
                                                        echo "</tr>";
                                                    }
                                                } else {
                                                    echo "<tr><td colspan='7'>No devices found for this lab</td></tr>";
                                                }
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php echo generatePagination($total_pcs, $items_per_page, $page, "?id=$lab_id", "pcs"); ?>
                            </div>

                            <!-- Tab 3 Content - Grievance  -->
                            <!-- Grievance tab content -->
                            <div class="tab-content <?php echo $active_tab === 'grievance' ? 'active' : ''; ?>" id="grievance">
                                <div class="tab-header">
                                    <h3>Grievances</h3>
                                </div>
                                <div class="header1">
                                    <div class="filters font-rale">
                                        <input class="search-input" type="text" id="grievanceSearch" placeholder="Search using ID, Name...">
                                        <select id="grievanceStatusFilter">
                                            <option value="">All Status</option>
                                            <option value="Submitted">Submitted</option>
                                            <option value="In Progress">In Progress</option>
                                            <option value="Under Review">Under Review</option>
                                            <option value="Resolved">Resolved</option>
                                            <option value="Closed">Closed</option>
                                        </select>
                                        <select id="grievanceCategoryFilter">
                                            <option value="">All Categories</option>
                                            <option value="PC">PC</option>
                                            <option value="Printer">Printer</option>
                                            <option value="Mouse">Mouse</option>
                                            <option value="Keyboard">Keyboard</option>
                                            <option value="Monitor">Monitor</option>
                                            <option value="CPU">CPU</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="table-container">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Grievance ID</th>
                                                <th>Student Name</th>
                                                <th>Enrollment</th>
                                                <th>Device Category</th>
                                                <th>Device ID</th>
                                                <th>Status</th>
                                                <th>Submission Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="grievanceTableBody">
                                            <?php
                                            if ($lab_exists) {
                                                $grievanceSql = "SELECT g.grievance_id, g.submitted_by, g.stud_enrollment, 
                                       g.device_category, g.device_id, g.status, g.submission_date 
                                FROM grievances g 
                                WHERE g.lab_id = '$lab_id' 
                                LIMIT $offset, $items_per_page";
                                                $grievanceResult = $conn->query($grievanceSql);

                                                if ($grievanceResult && $grievanceResult->num_rows > 0) {
                                                    while ($row = $grievanceResult->fetch_assoc()) {
                                                        echo "<tr>";
                                                        echo "<td>#GRV" . sprintf('%03d', $row['grievance_id']) . "</td>";
                                                        echo "<td>" . htmlspecialchars($row['submitted_by']) . "</td>";
                                                        echo "<td>" . htmlspecialchars($row['stud_enrollment']) . "</td>";
                                                        echo "<td>" . htmlspecialchars($row['device_category']) . "</td>";
                                                        echo "<td>" . ($row['device_id'] ? htmlspecialchars($row['device_id']) : 'N/A') . "</td>";
                                                        echo "<td><span class='status-badge " . strtolower(str_replace(' ', '-', $row['status'])) . "'>" . htmlspecialchars($row['status']) . "</span></td>";
                                                        echo "<td>" . date('Y-m-d', strtotime($row['submission_date'])) . "</td>";
                                                        echo "<td>
                                <a href='viewGrievance.php?id=" . $row['grievance_id'] . "' class='none'>
                                    <button class='btn-icon view-btn'><i class='fas fa-eye'></i></button>
                                </a>
                              </td>";
                                                        echo "</tr>";
                                                    }
                                                } else {
                                                    echo "<tr><td colspan='8'>No grievances found for this lab</td></tr>";
                                                }
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php echo generatePagination($total_grievances, $items_per_page, $page, "?id=$lab_id", "grievance"); ?>
                            </div>
                        </div>
                    </section>

                    <!-- QUICK Actions -->
                    <div class="items">
                        <div class="sub-heading">
                            <span>Quick Actions</span>
                        </div>
                        <div class="quick-actions-bar">
                            <div class="action-group">
                                <a href="addLab.php" class="none">
                                    <button class="action-item">
                                        <i class="fas fa-plus"></i>
                                        <span>Add New Lab</span>
                                    </button>
                                </a>
                                <a href="editLab.php?id=<?php echo $lab_id; ?>" class="none">
                                    <button class="action-item">
                                        <i class="fas fa-pen"></i>
                                        <span>Edit Lab</span>
                                    </button>
                                </a>
                                <a href="inventory.php?lab_id=<?php echo $lab_id; ?>" class="none">
                                    <button class="action-item">
                                        <i class="fas fa-boxes"></i>
                                        <span>Inventory</span>
                                    </button>
                                </a>
                                <div class="vertical-divider"></div>
                                <form method="post" id="toggleStatusForm">
                                    <button type="submit" name="toggle_status" class="action-item admin-action">
                                        <i class="fas <?php echo $lab['status'] === 'Active' ? 'fa-ban' : 'fa-check'; ?>"></i>
                                        <span><?php echo $lab['status'] === 'Active' ? 'Deactivate' : 'Activate'; ?></span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </section>
            <?php endif; ?>
        </div>

        <!-- Delete Confirmation Modal -->
        <div id="delete-popup" class="popupDelte-up-overlay">
            <div class="popupDelte">
                <div class="popupDelte-header">
                    <div class="popupDelte-icon">
                        <i class="fa-solid fa-trash"></i>
                    </div>
                    <h2 class="popupDelte-title">Confirm Deletion</h2>
                </div>

                <div class="popupDelte-body">
                    <p class="delete-text">Are you sure you want to delete <span id="item-type">this device</span>?</p>
                    <div class="item-name" id="item-name">Device Name (Lab ID)</div>
                    <p class="delete-warning">This action cannot be undone. The item will be permanently removed from the system.</p>
                </div>

                <div class="popupDelte-footer">
                    <button class="btnPopup btnPopup-cancel" id="cancel-btnPopup">Cancel</button>
                    <button class="btnPopup btnPopup-delete" id="confirm-delete-btnPopup">Delete</button>
                </div>
            </div>
        </div>

        <a href="exportBarcodes.php?id=<?php echo $lab_id; ?>" target="_blank" class="fab font-number">
            <i class="fa-solid fa-download"></i>
            Export Barcodes
        </a>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                function filterTable(tableBodyId, searchId, statusId, categoryId = null) {
                    const searchInput = document.getElementById(searchId);
                    const statusFilter = document.getElementById(statusId);
                    const categoryFilter = categoryId ? document.getElementById(categoryId) : null;

                    [searchInput, statusFilter, categoryFilter].forEach(element => {
                        if (element) {
                            element.addEventListener('change', function() {
                                const searchTerm = searchInput.value.toLowerCase();
                                const statusValue = statusFilter.value;
                                const categoryValue = categoryFilter ? categoryFilter.value : '';

                                fetch('filterDevices.php', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/x-www-form-urlencoded',
                                        },
                                        body: `lab_id=<?php echo $lab_id; ?>&search=${encodeURIComponent(searchTerm)}&status=${encodeURIComponent(statusValue)}&category=${encodeURIComponent(categoryValue)}&type=${tableBodyId === 'pcTableBody' ? 'PC' : 'All'}`
                                    })
                                    .then(response => response.text())
                                    .then(data => {
                                        document.getElementById(tableBodyId).innerHTML = data;
                                    })
                                    .catch(error => console.error('Error:', error));
                            });
                        }
                    });
                }

                // Initialize filters
                filterTable('pcTableBody', 'pcSearch', 'pcStatusFilter');
                filterTable('deviceTableBody', 'deviceSearch', 'deviceStatusFilter', 'categoryFilter');

                // Tab switching functionality
                document.querySelectorAll('.tab-link').forEach(link => {
                    link.addEventListener('click', (e) => {
                        document.querySelectorAll('.tab-link, .tab-content').forEach(el => {
                            el.classList.remove('active');
                        });

                        e.target.classList.add('active');
                        const tabId = e.target.dataset.tab;
                        document.getElementById(tabId).classList.add('active');

                        // Update URL without reloading
                        const url = new URL(window.location);
                        url.searchParams.set('tab', tabId);
                        window.history.pushState({}, '', url);
                    });
                });

                // Pagination handling to prevent jumping to top
                document.querySelectorAll('.pagination-link').forEach(link => {
                    link.addEventListener('click', function(e) {
                        e.preventDefault(); // Prevent default anchor behavior
                        const href = this.getAttribute('href');
                        const currentScroll = window.scrollY; // Store current scroll position

                        // Store scroll position before navigation
                        sessionStorage.setItem('scrollPos', currentScroll);

                        // Navigate to new page
                        window.location.href = href;
                    });
                });

                // Restore scroll position and tab state on page load
                const urlParams = new URLSearchParams(window.location.search);
                const tab = urlParams.get('tab') || 'pcs';

                // Set active tab
                document.querySelectorAll('.tab-link, .tab-content').forEach(el => {
                    el.classList.remove('active');
                });
                const activeTabLink = document.querySelector(`.tab-link[data-tab="${tab}"]`);
                const activeTabContent = document.getElementById(tab);
                if (activeTabLink && activeTabContent) {
                    activeTabLink.classList.add('active');
                    activeTabContent.classList.add('active');
                }

                // Restore scroll position
                const scrollPos = sessionStorage.getItem('scrollPos');
                if (scrollPos) {
                    window.scrollTo(0, parseInt(scrollPos));
                    sessionStorage.removeItem('scrollPos');
                }

                // Delete popup functionality
                const deleteButtons = document.querySelectorAll('.delete-trigger');
                const deletePopup = document.getElementById('delete-popup');
                const cancelBtn = document.getElementById('cancel-btnPopup');
                const confirmBtn = document.getElementById('confirm-delete-btnPopup');
                const itemNameElement = document.getElementById('item-name');
                let currentDeviceId = '';

                deleteButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        currentDeviceId = this.getAttribute('data-id');
                        const deviceName = this.getAttribute('data-name');
                        itemNameElement.textContent = deviceName + " (ID: " + currentDeviceId + ")";
                        deletePopup.style.display = 'flex';
                    });
                });

                cancelBtn.addEventListener('click', function() {
                    deletePopup.style.display = 'none';
                });

                confirmBtn.addEventListener('click', function() {
                    confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
                    confirmBtn.disabled = true;

                    const formData = new FormData();
                    formData.append('device_id', currentDeviceId);

                    fetch('deleteDevice.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(`HTTP error! Status: ${response.status}`);
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.success) {
                                alert(data.message || 'Device deleted successfully!');
                                // Use jQuery to find and remove the row
                                const deviceRow = $(`tr td:first-child:contains("${currentDeviceId}")`).parent('tr');
                                if (deviceRow.length) {
                                    deviceRow.remove();
                                } else {
                                    location.reload();
                                }
                            } else {
                                alert('Error: ' + (data.message || 'Unknown error occurred'));
                            }
                        })
                        .catch(error => {
                            console.error('Fetch Error:', error);
                            alert('An error occurred while deleting the device: ' + error.message);
                        })
                        .finally(() => {
                            confirmBtn.innerHTML = 'Delete';
                            confirmBtn.disabled = false;
                            deletePopup.style.display = 'none';
                        });
                });

                deletePopup.addEventListener('click', function(e) {
                    if (e.target === deletePopup) {
                        deletePopup.style.display = 'none';
                    }
                });
            });
        </script>
</body>

</html>
<?php $conn->close(); ?>