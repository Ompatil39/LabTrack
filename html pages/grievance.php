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
                <li class="active">
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
                    <span>Grievance Management</span>
                </div>
                <div class="user-info">
                    <!-- <img alt="User Avatar" src="https://placehold.co/30x30" /> -->
                    <i class="fa-solid fa-circle-user"></i><span class="font-rale"> Administrator
                    </span>
                </div>
            </div>

            <!-- Card View -->
            <div class="card-view">
                <div class="card blue-top">
                    <h3>New Grievance</h3>
                    <p class="font-number">8</p>
                    <i class="fa-solid fa-envelope-open-text blue-hover"></i>
                </div>
                <div class="card green-top">
                    <h3>Resolved</h3>
                    <p class="font-number">40</p>
                    <i class="fa-solid fa-check-circle green-hover"></i>
                </div>
                <div class="card yellow-top">
                    <h3>In Progress</h3>
                    <p class="font-number">24</p>
                    <i class="fa-solid fa-spinner yellow-hover"></i>
                </div>
                <div class="card red-top">
                    <h3>Unsolved Grievance</h3>
                    <p class="font-number">7</p>
                    <i class="fa-solid fa-exclamation-circle red-hover"></i>
                </div>
            </div>

            <!-- CARDs VIEW END -->
            <!-- Add after card-view section -->
            <div class="items">
                <!-- Filters and Search Bar -->
                <div class="sub-heading">
                    <span>Latest Grievances</span>
                </div>
                <div class="header1 ">
                    <div class="filters font-rale">
                        <input class="search-input" type="text" id="search" placeholder="Search using ID, Name...">
                        <select id="labFilter">
                            <option value="">All Labs</option>
                            <option value="Lab A">Lab A</option>
                            <option value="Lab B">Lab B</option>
                            <option value="Lab C">Lab C</option>
                        </select>
                        <select id="statusFilter">
                            <option value="">All Status</option>
                            <option value="Active">Active</option>
                            <option value="In Repair">In Repair</option>
                            <option value="Faulty">Faulty</option>
                            <!-- <option value="Decommissioned">Decommissioned</option> -->
                        </select>
                        <select id="categoryFilter">
                            <option value="">All Categories</option>
                            <option value="PC">PC</option>
                            <option value="Printer">Printer</option>
                            <option value="Mouse">Mouse</option>
                            <option value="Keyboard">Keyboard</option>
                        </select>
                    </div>
                </div>
                <!-- <div class="filter-bar">
                    <div class="search-group">
                        <input type="text" placeholder="Search by name or ID..." class="search-input">
                        <select class="filter-select">
                            <option>All Statuses</option>
                            <option>Pending</option>
                            <option>In Progress</option>
                            <option>Resolved</option>
                            <option>Rejected</option>
                        </select>
                    </div>
                    <div class="action-group">
                        <button class="btn-secondary"><i class="fas fa-download"></i> Export</button>
                    </div>
                </div> -->

                <!-- Grievance Table -->
                <table class="grievance-table">
                    <thead>
                        <tr>
                            <th>Grievance ID <i class="fas fa-sort"></i></th>
                            <th>Student Name <i class="fas fa-sort"></i></th>
                            <th>Device/Category <i class="fas fa-sort"></i></th>
                            <th>Status <i class="fas fa-sort"></i></th>
                            <th>Submission Date <i class="fas fa-sort"></i></th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>#GRV001</td>
                            <td>Xyx</td>
                            <td>Computer 12</td>
                            <td><span class="status-badge pending">Pending</span></td>
                            <td>2024-03-15</td>
                            <td>
                                <a href="viewGrievance.php" class="none">
                                    <button class="btn-icon view-btn"><i class="fas fa-eye"></i></button>
                                </a>
                                <button class="btn-icon delete-btn" id="delete-trigger"><i class="fa-solid fa-trash"></i></button>
                            </td>
                        </tr>
                        <tr>
                            <td>#GRV002</td>
                            <td>Xyx</td>
                            <td>Computer 13</td>
                            <td><span class="status-badge pending">Pending</span></td>
                            <td>2024-03-16</td>
                            <td>
                                <button class="btn-icon view-btn"><i class="fas fa-eye"></i></button>
                                <button class="btn-icon delete-btn" id="delete-trigger"><i class="fa-solid fa-trash"></i></button>
                            </td>
                        </tr>
                        <tr>
                            <td>#GRV003</td>
                            <td>Xyx</td>
                            <td>Computer 14</td>
                            <td><span class="status-badge pending">Pending</span></td>
                            <td>2024-03-17</td>
                            <td>
                                <button class="btn-icon view-btn"><i class="fas fa-eye"></i></button>
                                <button class="btn-icon delete-btn" id="delete-trigger"><i class="fa-solid fa-trash"></i></button>
                            </td>
                        </tr>
                        <tr>
                            <td>#GRV004</td>
                            <td>Xyx</td>
                            <td>Computer 15</td>
                            <td><span class="status-badge pending">Pending</span></td>
                            <td>2024-03-18</td>
                            <td>
                                <button class="btn-icon view-btn"><i class="fas fa-eye"></i></button>
                                <button class="btn-icon delete-btn" id="delete-trigger"><i class="fa-solid fa-trash"></i></button>
                            </td>
                        </tr>
                        <tr>
                            <td>#GRV005</td>
                            <td>Xyx</td>
                            <td>Computer 16</td>
                            <td><span class="status-badge pending">Pending</span></td>
                            <td>2024-03-19</td>
                            <td>
                                <button class="btn-icon view-btn"><i class="fas fa-eye"></i></button>
                                <button class="btn-icon delete-btn" id="delete-trigger"><i class="fa-solid fa-trash"></i></button>
                            </td>
                        </tr>
                        <tr>
                            <td>#GRV006</td>
                            <td>Xyx</td>
                            <td>Computer 17</td>
                            <td><span class="status-badge pending">Pending</span></td>
                            <td>2024-03-20</td>
                            <td>
                                <button class="btn-icon view-btn"><i class="fas fa-eye"></i></button>
                                <button class="btn-icon delete-btn" id="delete-trigger"><i class="fa-solid fa-trash"></i></button>
                            </td>
                        </tr>
                        <tr>
                            <td>#GRV007</td>
                            <td>Xyx</td>
                            <td>Printer 1</td>
                            <td><span class="status-badge pending">Pending</span></td>
                            <td>2024-03-21</td>
                            <td>
                                <button class="btn-icon view-btn"><i class="fas fa-eye"></i></button>
                                <button class="btn-icon delete-btn" id="delete-trigger"><i class="fa-solid fa-trash"></i></button>
                            </td>
                        </tr>
                        <tr>
                            <td>#GRV008</td>
                            <td>Xyx</td>
                            <td>Keyboard 2</td>
                            <td><span class="status-badge pending">Pending</span></td>
                            <td>2024-03-22</td>
                            <td>
                                <button class="btn-icon view-btn"><i class="fas fa-eye"></i></button>
                                <button class="btn-icon delete-btn" id="delete-trigger"><i class="fa-solid fa-trash"></i></button>
                            </td>
                        </tr>
                        <tr>
                            <td>#GRV009</td>
                            <td>Xyx</td>
                            <td>Mouse 3</td>
                            <td><span class="status-badge pending">Pending</span></td>
                            <td>2024-03-23</td>
                            <td>
                                <button class="btn-icon view-btn"><i class="fas fa-eye"></i></button>
                                <button class="btn-icon delete-btn" id="delete-trigger"><i class="fa-solid fa-trash"></i></button>
                            </td>
                        </tr>
                        <tr>
                            <td>#GRV010</td>
                            <td>Xyx</td>
                            <td>Monitor 4</td>
                            <td><span class="status-badge pending">Pending</span></td>
                            <td>2024-03-24</td>
                            <td>
                                <button class="btn-icon view-btn"><i class="fas fa-eye"></i></button>
                                <button class="btn-icon delete-btn" id="delete-trigger"><i class="fa-solid fa-trash"></i></button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <!-- CONTENT END  -->
    </div>

    <!-- POP UP FOR DELTE -->
    <div id="delete-popup" class="popupDelte-up-overlay">
        <div class="popupDelte">
            <div class="popupDelte-header">
                <div class="popupDelte-icon">
                    <i class="fa-solid fa-trash"></i>
                </div>
                <h2 class="popupDelte-title">Confirm Deletion</h2>
            </div>
    
            <div class="popupDelte-body">
                <p class="delete-text">Are you sure you want to delete this grievance ?</p>
                <div class="item-name" id="item-name">Lenovo PC - GRV-2024-001</div>
    
                <p class="delete-warning padding-bottom-1">This action cannot be undone. The grievance will be
                    permanently removed
                    from
                    the
                    system.</p>
                <textarea name="reason" id="" placeholder="Enter the reason here (this will be shared with the student)"
                    class="form-input"></textarea>
            </div>
    
            <div class="popupDelte-footer">
                <button class="btnPopup btnPopup-cancel" id="cancel-btnPopup">Cancel</button>
                <button class="btnPopup btnPopup-delete" id="confirm-delete-btnPopup">Delete</button>
            </div>
        </div>
    </div>

    </div>
    <script>
        // POP UP DELETE CONFIRMATION
        const deleteBtnPopup = document.getElementById('delete-trigger');
        const popupDelte = document.getElementById('delete-popup');
        const cancelBtnPopup = document.getElementById('cancel-btnPopup');
        const confirmBtnPopup = document.getElementById('confirm-delete-btnPopup');

        deleteBtnPopup.addEventListener('click', function () {
            popupDelte.style.display = 'flex';
        });

        cancelBtnPopup.addEventListener('click', function () {
            popupDelte.style.display = 'none';
        });

        confirmBtnPopup.addEventListener('click', function () {
            alert('Item deleted successfully!');
            popupDelte.style.display = 'none';
        });

        popupDelte.addEventListener('click', function (e) {
            if (e.target === popupDelte) {
                popupDelte.style.display = 'none';
            }
        });
    </script>
</body>

</html>