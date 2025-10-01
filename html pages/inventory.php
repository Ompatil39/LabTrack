<?php
session_start();
if (isset($_SESSION["logged_in"]) !== true) {
    header("Location: login.php");
}
include 'db.php';

// Pagination parameters
$items_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $items_per_page;

// Get filter parameters from GET (for URL persistence)
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$lab_filter = isset($_GET['lab']) ? $conn->real_escape_string($_GET['lab']) : '';
$status_filter = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';
$category_filter = isset($_GET['category']) ? $conn->real_escape_string($_GET['category']) : '';

// Build the WHERE clause for filtering
$where = "WHERE 1=1"; // Base condition that’s always true
if (!empty($search)) {
    $where .= " AND (d.device_id LIKE '%$search%' OR d.device_name LIKE '%$search%')";
}
if (!empty($lab_filter)) {
    $where .= " AND l.lab_name = '$lab_filter'";
}
if (!empty($status_filter)) {
    $where .= " AND d.status = '$status_filter'";
}
if (!empty($category_filter)) {
    $where .= " AND d.device_type = '$category_filter'";
}

// Get total counts for cards
$total_labs = $conn->query("SELECT COUNT(*) as count FROM labs")->fetch_assoc()['count'];
$total_devices = $conn->query("SELECT COUNT(*) as count FROM devices")->fetch_assoc()['count'];
$in_repair = $conn->query("SELECT COUNT(*) as count FROM devices WHERE status = 'In Repair'")->fetch_assoc()['count'];
$faulty_devices = $conn->query("SELECT COUNT(*) as count FROM devices WHERE status = 'Faulty'")->fetch_assoc()['count'];

// Get total items for pagination
$total_items = $conn->query("SELECT COUNT(*) as count FROM devices d LEFT JOIN labs l ON d.lab_id = l.lab_id $where")->fetch_assoc()['count'];

// Function to generate pagination links
function generatePagination($total_items, $items_per_page, $current_page, $base_url)
{
    $total_pages = ceil($total_items / $items_per_page);
    $pagination = '<div class="pagination">';

    $params = $_GET;
    unset($params['page']); // Remove page parameter to rebuild it

    if ($current_page > 1) {
        $params['page'] = $current_page - 1;
        $pagination .= '<a href="' . $base_url . '?' . http_build_query($params) . '" class="pagination-link">Previous</a>';
    }

    for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++) {
        $params['page'] = $i;
        $pagination .= '<a href="' . $base_url . '?' . http_build_query($params) . '"';
        if ($i == $current_page) $pagination .= ' class="pagination-link active"';
        else $pagination .= ' class="pagination-link"';
        $pagination .= '>' . $i . '</a>';
    }

    if ($current_page < $total_pages) {
        $params['page'] = $current_page + 1;
        $pagination .= '<a href="' . $base_url . '?' . http_build_query($params) . '" class="pagination-link">Next</a>';
    }

    $pagination .= '</div>';
    return $pagination;
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
                <li>
                    <a href="index.php"><i class="fa-solid fa-chart-pie"></i> Dashboard</a>
                </li>
                <li>
                    <a href="labs.php"><i class="fa-solid fa-network-wired"></i> Labs</a>
                </li>
                <li>
                    <a href="addLab.php"><i class="fa-solid fa-plus"></i> <span>Add Lab</span></a>
                </li>
                <li>
                    <a href="addDevice.php"><i class="fa-solid fa-plus"></i> <span>Add Devices</span></a>
                </li>
                <li class="active">
                    <a href="inventory.php"><i class="fa-solid fa-warehouse"></i> <span>Inventory</span></a>
                </li>
                <li>
                    <a href="grievance.php"><i class="fa-solid fa-paper-plane"></i> Grievance</a>
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
            <div class="header">
                <div class="sub-heading"><span>Overview</span></div>
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <div class="user-info" onclick="window.location.href = 'profileManage.php';" style="margin-right: 0.5rem;">
                        <i class="fa-solid fa-circle-user"></i>
                        <span class="font-rale"><?php echo htmlspecialchars(strtoupper($_SESSION['username']) ?? 'User');  ?></span>
                    </div>
                </div>
            </div>

            <!-- Card View -->
            <div class="card-view">
                <div class="card blue-top">
                    <h3>Total Labs</h3>
                    <p class="font-number"><?php echo $total_labs; ?></p>
                    <i class="fa-solid fa-server blue-hover"></i>
                </div>
                <div class="card green-top">
                    <h3>Total Devices</h3>
                    <p class="font-number"><?php echo $total_devices; ?></p>
                    <i class="fa-solid fa-computer green-hover"></i>
                </div>
                <div class="card yellow-top">
                    <h3>In Repair</h3>
                    <p class="font-number"><?php echo $in_repair; ?></p>
                    <i class="fas fa-tools yellow-hover"></i>
                </div>
                <div class="card red-top">
                    <h3>Faulty Devices</h3>
                    <p class="font-number"><?php echo $faulty_devices; ?></p>
                    <i class="fas fa-triangle-exclamation red-hover"></i>
                </div>
            </div>

            <!-- Table View -->
            <div class="items margin-bottom">
                <div class="sub-heading">
                    <span>Lab Devices Inventory</span>
                </div>

                <div class="header1">
                    <div class="filters">
                        <form id="filterForm" method="GET" action="inventory.php">
                            <input class="search-input" type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search using ID, Name..." style="margin-right: auto;">
                            <select id="labFilter" name="lab" class="float-right">
                                <option value="">All Labs</option>
                                <?php
                                $labs = $conn->query("SELECT DISTINCT lab_name FROM labs ORDER BY lab_name");
                                while ($lab = $labs->fetch_assoc()) {
                                    $selected = ($lab_filter === $lab['lab_name']) ? 'selected' : '';
                                    echo "<option value='" . htmlspecialchars($lab['lab_name']) . "' $selected>" . htmlspecialchars($lab['lab_name']) . "</option>";
                                }
                                ?>
                            </select>
                            <select id="statusFilter" name="status">
                                <option value="">All Status</option>
                                <option value="Active" <?php echo $status_filter === 'Active' ? 'selected' : ''; ?>>Active</option>
                                <option value="In Repair" <?php echo $status_filter === 'Under Repair' ? 'selected' : ''; ?>>Under Repair</option>
                                <option value="Faulty" <?php echo $status_filter === 'InActive' ? 'selected' : ''; ?>>InActive</option>
                            </select>
                            <select id="categoryFilter" name="category">
                                <option value="">All Categories</option>
                                <option value="PC" <?php echo $category_filter === 'PC' ? 'selected' : ''; ?>>PC</option>
                                <option value="Printer" <?php echo $category_filter === 'Printer' ? 'selected' : ''; ?>>Printer</option>
                                <option value="Mouse" <?php echo $category_filter === 'Mouse' ? 'selected' : ''; ?>>Mouse</option>
                                <option value="Keyboard" <?php echo $category_filter === 'Keyboard' ? 'selected' : ''; ?>>Keyboard</option>
                            </select>
                        </form>
                    </div>
                </div>

                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Device ID <i class="fas fa-sort"></i></th>
                                <th>Device Name <i class="fas fa-sort"></i></th>
                                <th>Category <i class="fas fa-sort"></i></th>
                                <th>Lab <i class="fas fa-sort"></i></th>
                                <th>PC <i class="fas fa-sort"></i></th>
                                <th>Status <i class="fas fa-sort"></i></th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sql = "SELECT d.device_id, d.device_name, d.device_type, l.lab_name, 
                    COALESCE(p.pc_id, 'N/A') as pc_id, d.status, 'Working' as remarks
                    FROM devices d
                    LEFT JOIN labs l ON d.lab_id = l.lab_id
                    LEFT JOIN pc_details p ON d.device_id = p.device_id
                    $where
                    LIMIT $offset, $items_per_page";
                            $result = $conn->query($sql);

                            if ($result && $result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($row['device_id']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['device_name']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['device_type']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['lab_name']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['pc_id']) . "</td>";
                                    echo "<td><span class='status status-" . strtolower(str_replace(' ', '-', $row['status'])) . "'>" . htmlspecialchars($row['status']) . "</span></td>";

                                    echo "<td>
                            <a href='viewDevice.php?id=" . $row['device_id'] . "' class='none'>
                                <button class='btn-icon view-btn'><i class='fas fa-eye'></i></button>
                            </a>
                            <a href='../edit/deviceRouter.php?id=" . $row['device_id'] . "' class='none'>
                                <button class='btn-icon edit-btn'><i class='fa-solid fa-pen'></i></button>
                            </a>
                            <button class='btn-icon qr-btn' onclick='showQRCode(\"" . $row['device_id'] . "\")' title='View QR Code'><i class='fa-solid fa-qrcode'></i></button>
                            <button class='btn-icon delete-btn delete-trigger' data-id='" . $row['device_id'] . "' data-name='" . $row['device_name'] . "'><i class='fa-solid fa-trash'></i></button>
                          </td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='8'>No devices found</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <?php echo generatePagination($total_items, $items_per_page, $page, "inventory.php"); ?>
            </div>
        </div>
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
                <div class="item-name" id="item-name"></div>
                <p class="delete-warning">This action cannot be undone. The item will be permanently removed from the system.</p>
            </div>
            <div class="popupDelte-footer">
                <button class="btnPopup btnPopup-cancel" id="cancel-btnPopup">Cancel</button>
                <button class="btnPopup btnPopup-delete" id="confirm-delete-btnPopup">Delete</button>
            </div>
        </div>
    </div>

    <!-- QR Code Modal -->
    <div id="qr-code-modal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <div class="modal-icon icon-blue">
                    <i class="fa-solid fa-qrcode"></i>
                </div>
                <h2 class="modal-title">Device QR Code</h2>
            </div>
            <div class="modal-body">
                <div id="qr-code-container" style="text-align: center;">
                    <div id="qr-loading" style="display: none;">
                        <i class="fa-solid fa-spinner fa-spin"></i> Loading QR code...
                    </div>
                    <div id="qr-code-image" style="display: none;"></div>
                    <div id="qr-error" style="display: none; color: #e74c3c;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btnModal btnModal-secondary" onclick="closeQRModal()">Close</button>
                <button class="btnModal btnModal-primary btnModal-blue" onclick="downloadQRCode()" id="download-qr-btn" style="display: none;">Download QR Code</button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Filter handling
            const filterForm = document.getElementById('filterForm');
            const filterInputs = filterForm.querySelectorAll('input, select');
            filterInputs.forEach(input => {
                input.addEventListener('change', function() {
                    filterForm.submit(); // Submit form on filter change
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

            // Restore scroll position on page load
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
                            // Find the row by searching for the device ID in the first <td>
                            const deviceRow = document.querySelector(`tr td:first-child:contains("${currentDeviceId}")`)?.parentElement;
                            if (deviceRow) {
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

        // QR Code functionality
        let currentQRCodeUrl = '';

        function showQRCode(deviceId) {
            const modal = document.getElementById('qr-code-modal');
            const loading = document.getElementById('qr-loading');
            const image = document.getElementById('qr-code-image');
            const error = document.getElementById('qr-error');
            const downloadBtn = document.getElementById('download-qr-btn');

            // Reset modal state
            loading.style.display = 'block';
            image.style.display = 'none';
            error.style.display = 'none';
            downloadBtn.style.display = 'none';
            modal.style.display = 'flex';

            // Fetch QR code
            const formData = new FormData();
            formData.append('action', 'generate_qr');
            formData.append('device_id', deviceId);

            fetch('qr_generator.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    loading.style.display = 'none';

                    if (data.success && data.qr_code_url) {
                        currentQRCodeUrl = data.qr_code_url;
                        image.innerHTML = `
                        <img src="${data.qr_code_url}" alt="Device QR Code" style="max-width: 300px; border: 1px solid #ddd; border-radius: 0.5rem;">
                        <p style="margin-top: 1rem; color: #7f8c8d;">Scan this QR code to access device details</p>
                    `;
                        image.style.display = 'block';
                        downloadBtn.style.display = 'inline-block';
                    } else {
                        error.textContent = 'Error generating QR code: ' + (data.message || 'Unknown error');
                        error.style.display = 'block';
                    }
                })
                .catch(error => {
                    loading.style.display = 'none';
                    error.textContent = 'Error loading QR code: ' + error.message;
                    error.style.display = 'block';
                });
        }

        function closeQRModal() {
            document.getElementById('qr-code-modal').style.display = 'none';
            currentQRCodeUrl = '';
        }

        function downloadQRCode() {
            if (currentQRCodeUrl) {
                const link = document.createElement('a');
                link.href = currentQRCodeUrl;
                link.download = 'device-qr-code.png';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        }

        // Close QR modal when clicking outside
        document.getElementById('qr-code-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeQRModal();
            }
        });
    </script>

    <style>
        .qr-btn {
            color: #3498db !important;
        }

        .qr-btn:hover {
            background-color: rgba(52, 152, 219, 0.1);
        }
    </style>
</body>

</html>