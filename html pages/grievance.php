<?php
session_start();

if (isset($_SESSION["logged_in"]) !== true) {
    header("Location: login.php");
}

include 'db.php';

// Initialize filter variables
$search = isset($_GET['search']) ? $_GET['search'] : '';
$lab_filter = isset($_GET['lab']) ? $_GET['lab'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'submission_date';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// Pagination parameters
$items_per_page = 10; // Set to 10 rows per page
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;

// Get counts for the card view
$new_count = $conn->query("SELECT COUNT(*) as count FROM grievances WHERE status = 'Submitted'")->fetch_assoc()['count'];
$resolved_count = $conn->query("SELECT COUNT(*) as count FROM grievances WHERE status = 'Resolved'")->fetch_assoc()['count'];
$in_progress_count = $conn->query("SELECT COUNT(*) as count FROM grievances WHERE status IN ('In Progress', 'Under Review')")->fetch_assoc()['count'];
$unsolved_count = $conn->query("SELECT COUNT(*) as count FROM grievances WHERE status NOT IN ('Resolved', 'Closed')")->fetch_assoc()['count'];

// Build base query with filters
$base_query = "SELECT g.*, l.lab_name 
              FROM grievances g
              LEFT JOIN labs l ON g.lab_id = l.lab_id
              WHERE 1=1";

$count_query = "SELECT COUNT(*) as total 
               FROM grievances g
               LEFT JOIN labs l ON g.lab_id = l.lab_id
               WHERE 1=1";

if (!empty($search)) {
    $search_condition = " AND (g.grievance_id LIKE '%$search%' 
                          OR g.submitted_by LIKE '%$search%' 
                          OR g.stud_enrollment LIKE '%$search%')";
    $base_query .= $search_condition;
    $count_query .= $search_condition;
}

if (!empty($lab_filter)) {
    $base_query .= " AND g.lab_id = '$lab_filter'";
    $count_query .= " AND g.lab_id = '$lab_filter'";
}

if (!empty($status_filter)) {
    $base_query .= " AND g.status = '$status_filter'";
    $count_query .= " AND g.status = '$status_filter'";
}

if (!empty($category_filter)) {
    $base_query .= " AND g.device_category = '$category_filter'";
    $count_query .= " AND g.device_category = '$category_filter'";
}

// Get total count for pagination
$count_result = $conn->query($count_query);
$total_items = $count_result->fetch_assoc()['total'];

// Add sorting and pagination to main query
$valid_columns = ['grievance_id', 'submitted_by', 'device_category', 'status', 'submission_date'];
$valid_orders = ['ASC', 'DESC'];

$sort_column = in_array($sort_column, $valid_columns) ? $sort_column : 'submission_date';
$sort_order = in_array($sort_order, $valid_orders) ? $sort_order : 'DESC';

$base_query .= " ORDER BY $sort_column $sort_order LIMIT $offset, $items_per_page";
$result = $conn->query($base_query);

// Pagination function
function generatePagination($total_items, $items_per_page, $current_page, $base_url)
{
    $total_pages = ceil($total_items / $items_per_page);
    $pagination = '<div class="pagination">';

    // Previous link
    if ($current_page > 1) {
        $pagination .= '<a href="' . $base_url . '&page=' . ($current_page - 1) . '" class="pagination-link">Previous</a>';
    }

    // Page numbers
    for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++) {
        $pagination .= '<a href="' . $base_url . '&page=' . $i . '"';
        $pagination .= ($i == $current_page) ? ' class="pagination-link active"' : ' class="pagination-link"';
        $pagination .= '>' . $i . '</a>';
    }

    // Next link
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
$labs_query = "SELECT lab_id, lab_name FROM labs ORDER BY lab_name";
$labs_result = $conn->query($labs_query);

// Handle delete grievance
if (isset($_POST['delete_grievance'])) {
    $grievance_id = $_POST['grievance_id'];
    $reason = $_POST['delete_reason'];

    // First update the admin note with the deletion reason
    $update_query = "UPDATE grievances SET admin_note = CONCAT(IFNULL(admin_note, ''), '\n[DELETION REASON: $reason]') WHERE grievance_id = $grievance_id";
    $conn->query($update_query);

    // Then delete the grievance
    $delete_query = "DELETE FROM grievances WHERE grievance_id = $grievance_id";
    if ($conn->query($delete_query) === TRUE) {
        $delete_message = "Grievance deleted successfully";
    } else {
        $delete_error = "Error deleting grievance: " . $conn->error;
    }

    // Redirect to avoid resubmission
    header("Location: grievance.php?deleted=true");
    exit();
}
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
                    <a href="notification.php" class="none"><i class="fa-solid fa-bell" style="color: #3498db; margin-right: 1.1rem;"></i></a>
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
                            <?php while ($lab = $labs_result->fetch_assoc()): ?>
                                <option value="<?php echo $lab['lab_id']; ?>" <?php echo ($lab_filter == $lab['lab_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($lab['lab_name']); ?>
                                </option>
                            <?php endwhile; ?>
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
                        if ($result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
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
                                    <td>#GRV<?php echo str_pad($row['grievance_id'], 3, '0', STR_PAD_LEFT); ?></td>
                                    <td><?php echo htmlspecialchars($row['submitted_by']); ?> (<?php echo htmlspecialchars($row['stud_enrollment']); ?>)</td>
                                    <td><?php echo htmlspecialchars($row['device_category']); ?> <?php echo !empty($row['device_name']) ? '- ' . htmlspecialchars($row['device_name']) : ''; ?></td>
                                    <td><span class="status-badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
                                    <td><?php echo date('Y-m-d', strtotime($row['submission_date'])); ?></td>
                                    <td>
                                        <a href="viewGrievance.php?id=<?php echo $row['grievance_id']; ?>" class="none">
                                            <button class="btn-icon view-btn"><i class="fas fa-eye"></i></button>
                                        </a>
                                        <button class="btn-icon delete-btn delete-trigger" data-id="<?php echo $row['grievance_id']; ?>"
                                            data-name="<?php echo htmlspecialchars($row['device_category'] . ' - #GRV' . str_pad($row['grievance_id'], 3, '0', STR_PAD_LEFT)); ?>">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php
                            }
                        } else {
                            ?>
                            <tr>
                                <td colspan="6" style="text-align: center;">No grievances found matching your criteria.</td>
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