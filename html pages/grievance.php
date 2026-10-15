<?php
require_once __DIR__ . '/../includes/security.php';
requireLogin();
startCsrfOutputBuffer();

include 'db.php';

// Initialize filter variables
$search = trim((string) ($_GET['search'] ?? ''));
$lab_filter = trim((string) ($_GET['lab'] ?? ''));
$status_filter = trim((string) ($_GET['status'] ?? ''));
$category_filter = trim((string) ($_GET['category'] ?? ''));
$sort_column = (string) ($_GET['sort'] ?? 'submission_date');
$sort_order = strtoupper((string) ($_GET['order'] ?? 'DESC'));

$valid_columns = [
    'grievance_id' => 'g.grievance_id',
    'submitted_by' => 'g.submitted_by',
    'device_category' => 'g.device_category',
    'status' => 'g.status',
    'submission_date' => 'g.submission_date'
];
$sort_field = $valid_columns[$sort_column] ?? 'g.submission_date';
$sort_order = ($sort_order === 'ASC') ? 'ASC' : 'DESC';

// Pagination parameters
$items_per_page = 10;
$current_page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($current_page - 1) * $items_per_page;

$scope = restrictLabSql('lab_id', 'g');

// Counts with scope
$countConditions = [];
$countTypes = '';
$countParams = [];
if ($scope['clause'] !== '') {
    $countConditions[] = trim($scope['clause']);
    $countTypes .= $scope['types'];
    $countParams = array_merge($countParams, $scope['params']);
}

$getStatCount = function (string $statusCondition) use ($conn, $countConditions, $countTypes, $countParams): int {
    $conds = array_merge([$statusCondition], $countConditions);
    $sql = "SELECT COUNT(*) as count FROM grievances g WHERE " . implode(' AND ', $conds);
    $row = db_fetch_one($conn, $sql, $countTypes, $countParams);
    return $row ? (int) $row['count'] : 0;
};

$new_count = $getStatCount("g.status = 'Submitted'");
$resolved_count = $getStatCount("g.status = 'Resolved'");
$in_progress_count = $getStatCount("g.status IN ('In Progress', 'Under Review')");
$unsolved_count = $getStatCount("g.status NOT IN ('Resolved', 'Closed')");

// Build main query with filters
$conditions = ['1=1'];
$types = '';
$params = [];

if ($search !== '') {
    $conditions[] = "(g.grievance_id LIKE ? OR g.submitted_by LIKE ? OR g.stud_enrollment LIKE ?)";
    $types .= 'sss';
    $searchWild = '%' . $search . '%';
    $params[] = $searchWild;
    $params[] = $searchWild;
    $params[] = $searchWild;
}

if ($lab_filter !== '') {
    $conditions[] = "g.lab_id = ?";
    $types .= 's';
    $params[] = $lab_filter;
}

if ($status_filter !== '') {
    $conditions[] = "g.status = ?";
    $types .= 's';
    $params[] = $status_filter;
}

if ($category_filter !== '') {
    $conditions[] = "g.device_category = ?";
    $types .= 's';
    $params[] = $category_filter;
}

if ($scope['clause'] !== '') {
    $conditions[] = trim($scope['clause']);
    $types .= $scope['types'];
    $params = array_merge($params, $scope['params']);
}

$whereClause = implode(' AND ', $conditions);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM grievances g LEFT JOIN labs l ON g.lab_id = l.lab_id WHERE " . $whereClause;
$count_result = db_fetch_one($conn, $count_query, $types, $params);
$total_items = $count_result ? (int) $count_result['total'] : 0;

// Fetch page items with prepared statement
$hasAssetsTable = (bool) db_fetch_one($conn, "SHOW TABLES LIKE 'assets'");
$hasWsTable = (bool) db_fetch_one($conn, "SHOW TABLES LIKE 'workstations'");

if ($hasAssetsTable && $hasWsTable) {
    $base_query = "SELECT g.*, l.lab_name, a.asset_tag, w.seat_code, w.workstation_id AS ws_id
                   FROM grievances g 
                   LEFT JOIN labs l ON g.lab_id = l.lab_id 
                   LEFT JOIN assets a ON (g.asset_id IS NOT NULL AND g.asset_id = a.asset_id)
                                      OR (g.asset_id IS NULL AND (a.legacy_device_id = g.device_id OR a.asset_tag = g.device_id))
                   LEFT JOIN workstations w ON g.workstation_id = w.workstation_id
                   WHERE " . $whereClause . " 
                   ORDER BY " . $sort_field . " " . $sort_order . " 
                   LIMIT ?, ?";
} else {
    $base_query = "SELECT g.*, l.lab_name 
                   FROM grievances g 
                   LEFT JOIN labs l ON g.lab_id = l.lab_id 
                   WHERE " . $whereClause . " 
                   ORDER BY " . $sort_field . " " . $sort_order . " 
                   LIMIT ?, ?";
}
$pageTypes = $types . 'ii';
$pageParams = array_merge($params, [$offset, $items_per_page]);
$grievanceRows = db_fetch_all($conn, $base_query, $pageTypes, $pageParams);

// Pagination function
function generatePagination($total_items, $items_per_page, $current_page, $base_url)
{
    $total_pages = (int) ceil($total_items / $items_per_page);
    $pagination = '<div class="pagination">';

    if ($current_page > 1) {
        $pagination .= '<a href="' . $base_url . '&page=' . ($current_page - 1) . '" class="pagination-link">Previous</a>';
    }

    for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++) {
        $pagination .= '<a href="' . $base_url . '&page=' . $i . '"';
        $pagination .= ($i == $current_page) ? ' class="pagination-link active"' : ' class="pagination-link"';
        $pagination .= '>' . $i . '</a>';
    }

    if ($current_page < $total_pages) {
        $pagination .= '<a href="' . $base_url . '&page=' . ($current_page + 1) . '" class="pagination-link">Next</a>';
    }

    $pagination .= '</div>';
    return $pagination;
}

// Build base URL for pagination
$query_params = $_GET;
unset($query_params['page']);
$base_url = 'grievance.php?' . http_build_query($query_params);
$pagination_html = generatePagination($total_items, $items_per_page, $current_page, $base_url);

// Get labs for filter dropdown
$labFilterScope = restrictLabSql('lab_id');
$labs_query = "SELECT lab_id, lab_name FROM labs";
if ($labFilterScope['clause'] !== '') {
    $labs_query .= " WHERE " . trim($labFilterScope['clause']);
}
$labs_query .= " ORDER BY lab_name";
$labs_result_rows = db_fetch_all($conn, $labs_query, $labFilterScope['types'], $labFilterScope['params']);

// Handle delete grievance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_grievance'])) {
    enforceCsrfProtection();
    $grievance_id = (int) ($_POST['grievance_id'] ?? 0);
    $reason = trim((string) ($_POST['delete_reason'] ?? ''));

    // Verify grievance exists and user has lab access
    $existing = db_fetch_one($conn, "SELECT grievance_id, lab_id FROM grievances WHERE grievance_id = ? LIMIT 1", "i", [$grievance_id]);
    if ($existing) {
        requireLabAccess($conn, (string) $existing['lab_id']);

        $deletionNote = "\n[DELETION REASON: " . $reason . "]";
        db_execute(
            $conn,
            "UPDATE grievances SET admin_note = CONCAT(IFNULL(admin_note, ''), ?) WHERE grievance_id = ?",
            "si",
            [$deletionNote, $grievance_id]
        );

        $deleted = db_execute($conn, "DELETE FROM grievances WHERE grievance_id = ?", "i", [$grievance_id]);
        if (!$deleted) {
            app_logger()->error('delete_grievance_error', ['grievance_id' => $grievance_id]);
        }
    }

    header("Location: grievance.php?deleted=true");
    exit();
}
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
        /* Filter button styles */
        .filter-action-btn {
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
            margin-left: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .filter-apply {
            background-color: #0070cc !important;
            color: white !important;
        }

        .filter-apply:hover {
            background-color: #3a5be0;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .filter-reset {
            background-color: #f1f1f1;
            color: #555;
            border: 1px solid #ddd;
        }

        .filter-reset:hover {
            background-color: #e5e5e5;
        }

        /* You can add these icons to the buttons */
        .filter-apply i,
        .filter-reset i {
            margin-right: 4px;
            font-size: 0.8rem;
        }

        /* Additional CSS for status badges */
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            white-space: nowrap;
        }

        /* Sort indicators */
        th {
            position: relative;
            cursor: pointer;
        }

        .sort-active {
            background-color: #f5f5f5;
        }

        .sort-asc::after {
            content: ' ↑';
            font-size: 0.8em;
        }

        .sort-desc::after {
            content: ' ↓';
            font-size: 0.8em;
        }

        /* Notification styles */
        .notification {
            padding: 10px 15px;
            margin-bottom: 15px;
            border-radius: 5px;
            font-weight: 500;
        }

        .notification-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .notification-error {
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
                <li class="active">
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

            <?php if (isset($_GET['deleted']) && $_GET['deleted'] == 'true'): ?>
                <div class="notification notification-success">
                    <i class="fas fa-check-circle"></i> Grievance has been successfully deleted.
                </div>
            <?php endif; ?>

            <!-- Card View -->
            <div class="card-view">
                <div class="card blue-top">
                    <h3>New Grievance</h3>
                    <p class="font-number"><?php echo $new_count; ?></p>
                    <i class="fa-solid fa-envelope-open-text blue-hover"></i>
                </div>
                <div class="card green-top">
                    <h3>Resolved</h3>
                    <p class="font-number"><?php echo $resolved_count; ?></p>
                    <i class="fa-solid fa-check-circle green-hover"></i>
                </div>
                <div class="card yellow-top">
                    <h3>In Progress</h3>
                    <p class="font-number"><?php echo $in_progress_count; ?></p>
                    <i class="fa-solid fa-spinner yellow-hover"></i>
                </div>
                <div class="card red-top">
                    <h3>Unsolved Grievance</h3>
                    <p class="font-number"><?php echo $unsolved_count; ?></p>
                    <i class="fa-solid fa-exclamation-circle red-hover"></i>
                </div>
            </div>

            <!-- CARDs VIEW END -->
            <div class="items">
                <!-- Filters and Search Bar -->
                <div class="sub-heading">
                    <span>Latest Grievances</span>
                </div>
                <div class="header1">
                    <form action="grievance.php" method="GET" class="filters font-rale">
                        <input class="search-input" type="text" id="search" name="search" placeholder="Search using ID, Name..." value="<?php echo htmlspecialchars($search); ?>">
                        <select id="labFilter" name="lab">
                            <option value="">All Labs</option>
                            <?php foreach ($labs_result_rows as $lab): ?>
                                <option value="<?php echo htmlspecialchars($lab['lab_id']); ?>" <?php echo ($lab_filter === $lab['lab_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($lab['lab_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select id="statusFilter" name="status">
                            <option value="">All Status</option>
                            <option value="Submitted" <?php echo ($status_filter == 'Submitted') ? 'selected' : ''; ?>>Submitted</option>
                            <option value="In Progress" <?php echo ($status_filter == 'In Progress') ? 'selected' : ''; ?>>In Progress</option>
                            <option value="Under Review" <?php echo ($status_filter == 'Under Review') ? 'selected' : ''; ?>>Under Review</option>
                            <option value="Resolved" <?php echo ($status_filter == 'Resolved') ? 'selected' : ''; ?>>Resolved</option>
                            <option value="Closed" <?php echo ($status_filter == 'Closed') ? 'selected' : ''; ?>>Closed</option>
                        </select>
                        <select id="categoryFilter" name="category">
                            <option value="">All Categories</option>
                            <option value="PC" <?php echo ($category_filter == 'PC') ? 'selected' : ''; ?>>PC</option>
                            <option value="Printer" <?php echo ($category_filter == 'Printer') ? 'selected' : ''; ?>>Printer</option>
                            <option value="Mouse" <?php echo ($category_filter == 'Mouse') ? 'selected' : ''; ?>>Mouse</option>
                            <option value="Keyboard" <?php echo ($category_filter == 'Keyboard') ? 'selected' : ''; ?>>Keyboard</option>
                            <option value="Monitor" <?php echo ($category_filter == 'Monitor') ? 'selected' : ''; ?>>Monitor</option>
                            <option value="CPU" <?php echo ($category_filter == 'CPU') ? 'selected' : ''; ?>>CPU</option>
                        </select>
                        <button type="submit" class="filter-action-btn filter-apply">
                            <i class="fas fa-filter"></i> Apply
                        </button>
                        <a href="grievance.php" class="filter-action-btn filter-reset none">
                            <i class="fas fa-undo"></i> Reset
                        </a>
                    </form>
                </div>

                <!-- Grievance Table --><!-- Grievance Table -->
                <table class="grievance-table">
                    <thead>
                        <tr>
                            <th class="<?php echo $sort_column == 'grievance_id' ? 'sort-' . strtolower($sort_order) : ''; ?>">
                                <a class="none" href="grievance.php?sort=grievance_id&order=<?php echo ($sort_column == 'grievance_id' && $sort_order == 'ASC') ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($search); ?>&lab=<?php echo urlencode($lab_filter); ?>&status=<?php echo urlencode($status_filter); ?>&category=<?php echo urlencode($category_filter); ?>">
                                    Grievance ID
                                </a>
                            </th>
                            <th class="<?php echo $sort_column == 'submitted_by' ? 'sort-' . strtolower($sort_order) : ''; ?>">
                                <a class="none" href="grievance.php?sort=submitted_by&order=<?php echo ($sort_column == 'submitted_by' && $sort_order == 'ASC') ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($search); ?>&lab=<?php echo urlencode($lab_filter); ?>&status=<?php echo urlencode($status_filter); ?>&category=<?php echo urlencode($category_filter); ?>">
                                    Student Name
                                </a>
                            </th>
                            <th class="<?php echo $sort_column == 'device_category' ? 'sort-' . strtolower($sort_order) : ''; ?>">
                                <a class="none" href="grievance.php?sort=device_category&order=<?php echo ($sort_column == 'device_category' && $sort_order == 'ASC') ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($search); ?>&lab=<?php echo urlencode($lab_filter); ?>&status=<?php echo urlencode($status_filter); ?>&category=<?php echo urlencode($category_filter); ?>">
                                    Device/Category
                                </a>
                            </th>
                            <th>Workstation / Asset</th>
                            <th class="<?php echo $sort_column == 'status' ? 'sort-' . strtolower($sort_order) : ''; ?>">
                                <a class="none" href="grievance.php?sort=status&order=<?php echo ($sort_column == 'status' && $sort_order == 'ASC') ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($search); ?>&lab=<?php echo urlencode($lab_filter); ?>&status=<?php echo urlencode($status_filter); ?>&category=<?php echo urlencode($category_filter); ?>">
                                    Status
                                </a>
                            </th>
                            <th class="<?php echo $sort_column == 'submission_date' ? 'sort-' . strtolower($sort_order) : ''; ?>">
                                <a href="grievance.php?sort=submission_date&order=<?php echo ($sort_column == 'submission_date' && $sort_order == 'ASC') ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($search); ?>&lab=<?php echo urlencode($lab_filter); ?>&status=<?php echo urlencode($status_filter); ?>&category=<?php echo urlencode($category_filter); ?>" class="none">
                                    Submission Date
                                </a>
                            </th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (!empty($grievanceRows)) {
                            foreach ($grievanceRows as $row) {
                                // Determine status badge class
                                $status_class = '';
                                switch ($row['status']) {
                                    case 'Submitted':
                                        $status_class = 'status-active';
                                        break;
                                    case 'In Progress':
                                        $status_class = 'in-progress';
                                        break;
                                    case 'Under Review':
                                        $status_class = 'in-progress';
                                        break;
                                    case 'Resolved':
                                        $status_class = 'resolved';
                                        break;
                                    case 'Closed':
                                        $status_class = 'pending';
                                        break;
                                }
                        ?>
                                <tr>
                                    <td>#GRV<?php echo str_pad((string) $row['grievance_id'], 3, '0', STR_PAD_LEFT); ?></td>
                                    <td><?php echo htmlspecialchars($row['submitted_by'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars($row['stud_enrollment'], ENT_QUOTES, 'UTF-8'); ?>)</td>
                                    <td><?php echo htmlspecialchars($row['device_category'], ENT_QUOTES, 'UTF-8'); ?> <?php echo !empty($row['device_name']) ? '- ' . htmlspecialchars($row['device_name'], ENT_QUOTES, 'UTF-8') : ''; ?></td>
                                    <td>
                                        <?php if (!empty($row['ws_id'])): ?>
                                            <div>
                                                <a href="workstation.php?id=<?php echo urlencode((string)$row['ws_id']); ?>" style="color: #4f46e5; font-weight: 600; text-decoration: none;">
                                                    <i class="fa-solid fa-desktop"></i> Seat <?php echo htmlspecialchars((string)($row['seat_code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($row['asset_tag'])): ?>
                                            <span style="font-family: monospace; font-size: 0.78rem; background: #eef2ff; color: #4338ca; padding: 1px 6px; border-radius: 3px;">
                                                <?php echo htmlspecialchars((string)$row['asset_tag'], ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        <?php elseif (empty($row['ws_id'])): ?>
                                            <span style="color: #9ca3af;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="status-badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                    <td><?php echo date('Y-m-d', strtotime($row['submission_date'])); ?></td>
                                    <td>
                                        <?php if (!empty($row['ws_id'])): ?>
                                            <a href="workstation.php?id=<?php echo urlencode((string)$row['ws_id']); ?>" class="none" title="View Workstation Seat <?php echo htmlspecialchars((string)($row['seat_code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                                <button class="btn-icon" style="color:#4f46e5;"><i class="fas fa-desktop"></i></button>
                                            </a>
                                        <?php endif; ?>
                                        <a href="viewGrievance.php?id=<?php echo (int) $row['grievance_id']; ?>" class="none">
                                             <button class="btn-icon view-btn"><i class="fas fa-eye"></i></button>
                                        </a>
                                        <button class="btn-icon delete-btn delete-trigger" data-id="<?php echo (int) $row['grievance_id']; ?>"
                                            data-name="<?php echo htmlspecialchars($row['device_category'] . ' - #GRV' . str_pad((string) $row['grievance_id'], 3, '0', STR_PAD_LEFT), ENT_QUOTES, 'UTF-8'); ?>">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php
                            }
                        } else {
                            ?>
                            <tr>
                                <td colspan="7" style="text-align: center;">No grievances found matching your criteria.</td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div class="pagination">
                    <?php echo $pagination_html; ?>
                </div>
            </div>
        </div>
        <!-- CONTENT END  -->
    </div>

    <!-- DELETE CONFIRMATION POPUP -->
    <div id="delete-popup" class="popupDelte-up-overlay">
        <div class="popupDelte">
            <div class="popupDelte-header">
                <div class="popupDelte-icon">
                    <i class="fa-solid fa-trash"></i>
                </div>
                <h2 class="popupDelte-title">Confirm Deletion</h2>
            </div>

            <div class="popupDelte-body">
                <p class="delete-text">Are you sure you want to delete this grievance?</p>
                <div class="item-name" id="item-name"></div>

                <p class="delete-warning padding-bottom-1">This action cannot be undone. The grievance will be permanently removed from the system.</p>

                <form id="delete-form" method="POST" action="grievance.php">
                    <?= csrfInputField() ?>
                    <input type="hidden" name="grievance_id" id="grievance_id_input">
                    <input type="hidden" name="delete_grievance" value="1">
                    <textarea name="delete_reason" placeholder="Enter the reason here (this will be shared with the student)" class="form-input" required></textarea>
                </form>
            </div>

            <div class="popupDelte-footer">
                <button class="btnPopup btnPopup-cancel" id="cancel-btnPopup">Cancel</button>
                <button class="btnPopup btnPopup-delete" id="confirm-delete-btnPopup">Delete</button>
            </div>
        </div>
    </div>

    <script>
        // Delete popup functionality with dynamic item information
        const deleteButtons = document.querySelectorAll('.delete-trigger');
        const popupDelte = document.getElementById('delete-popup');
        const cancelBtnPopup = document.getElementById('cancel-btnPopup');
        const confirmBtnPopup = document.getElementById('confirm-delete-btnPopup');
        const itemNameElement = document.getElementById('item-name');
        const grievanceIdInput = document.getElementById('grievance_id_input');
        const deleteForm = document.getElementById('delete-form');

        deleteButtons.forEach(button => {
            button.addEventListener('click', function() {
                const grievanceId = this.getAttribute('data-id');
                const grievanceName = this.getAttribute('data-name');

                itemNameElement.textContent = grievanceName;
                grievanceIdInput.value = grievanceId;
                popupDelte.style.display = 'flex';
            });
        });

        cancelBtnPopup.addEventListener('click', function() {
            popupDelte.style.display = 'none';
        });

        confirmBtnPopup.addEventListener('click', function() {
            deleteForm.submit();
        });

        popupDelte.addEventListener('click', function(e) {
            if (e.target === popupDelte) {
                popupDelte.style.display = 'none';
            }
        });

        // Auto-submit form when filters change
        const filterSelects = document.querySelectorAll('.filters select');
        filterSelects.forEach(select => {
            select.addEventListener('change', function() {
                this.form.submit();
            });
        });
    </script>
</body>

</html>
<?php
// Close the database connection
$conn->close();
?>