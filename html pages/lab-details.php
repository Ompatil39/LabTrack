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
        </div>

        <!-- Main Content Area -->
        <div class="main-content">
            <!-- Header -->
            <div class="header">
                <div class="sub-heading">
                    <span>Lab Management</span>
                </div>
                <div class="user-info">
                    <i class="fa-solid fa-circle-user"></i><span class="font-rale"> Administrator
                    </span>
                </div>
            </div>

            <!-- CONTENT START BELOW HEADER -->
            <!-- LAB SUMMARY CARD -->
            <section class="lab-cards">
                <div class="detail-card">
                    <header class="card-header">
                        <h2 class="card-title font-number">CO-2025-101</h2>
                        <span class="status-chip lab-status highlight1">
                            <i class="fas fa-check-circle"></i> Active
                        </span>
                        <div class="location">
                            <i class="fa-solid fa-location-dot"></i>
                            <span>Building A - Room 201</span>
                        </div>
                    </header>

                    <hr class="card-divider">

                    <section class="lab-summary">
                        <h3 class="section-title1">Lab Overview</h3>
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-value font-number">25</div>
                                <div class="stat-label">Total PC</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value font-number">20</div>
                                <div class="stat-label">Available PC</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value font-number">104</div>
                                <div class="stat-label">Total Devices</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value font-number">80</div>
                                <div class="stat-label">Available Devices</div>
                            </div>
                        </div>
                    </section>

                    <section class="grievance-summary margin-bottom">
                        <h3 class="section-title1">Grievance Overview</h3>
                        <div class="grievance-stats">
                            <div class="grievance-stat resolved">
                                <span class="stat-value font-number">7</span>
                                <i class="fas fa-check-circle stat-icon"></i>
                                <span class="stat-label">Resolved</span>
                            </div>
                            <div class="grievance-stat in-progress">
                                <span class="stat-value font-number">3</span>
                                <i class="fa-solid fa-spinner stat-icon"></i>
                                <span class="stat-label">In Progress</span>
                            </div>
                            <div class="grievance-stat pending">
                                <span class="stat-value font-number">5</span>
                                <i class="fas fa-exclamation-circle stat-icon"></i>
                                <span class="stat-label">Pending</span>
                            </div>
                        </div>
                    </section>

                    <section class="general-summary">
                        <h3 class="section-title1" style="margin-bottom: 1rem !important;">Lab Details</h3>
                        <div class="details-content">
                            <div class="input-group">
                                <label class="detail-label">Lab Name</label>
                                <div class="label-value">Computer Lab A</div>
                            </div>

                            <div class="input-group">
                                <label class="detail-label">Lab Code</label>
                                <div class="label-value">CO-2025-101</div>
                            </div>

                            <div class="input-group">
                                <label class="detail-label">Department</label>
                                <div class="label-value">CO - Computer Science</div>
                            </div>

                            <div class="input-group">
                                <label class="detail-label">Lab In-Charge</label>
                                <div class="label-value">Admin 1</div>
                            </div>

                            <div class="input-group">
                                <label class="detail-label">Establishment Date</label>
                                <div class="label-value">January 15, 2020</div>
                            </div>

                            <div class="input-group">
                                <label class="detail-label">Room Capacity</label>
                                <div class="label-value">30</div>
                            </div>
                        </div>

                    </section>

                </div>
                <!-- TABBED MENU -->
                <section class="lab-tabs">
                    <div class="tabs-container">
                        <!-- Tab Navigation -->
                        <nav class="tabs-nav">
                            <button class="tab-link active" data-tab="pcs">Workstations</button>
                            <button class="tab-link" data-tab="devices">Devices</button>
                            <button class="tab-link" data-tab="grievance">Grievance</button>
                        </nav>

                        <!-- Tab 1 Content  -->
                        <div class="tab-content active" id="pcs">
                            <div class="tab-header">
                                <h3>PC List</h3>
                            </div>
                            <div class="header1">
                                <div class="filters">
                                    <input class="search-input" type="text" id="search"
                                        placeholder="Search using ID, Name...">

                                    <select id="statusFilter">
                                        <option value="">All Status</option>
                                        <option value="Active">Active</option>
                                        <option value="In Repair">In Repair</option>
                                        <option value="Faulty">Faulty</option>
                                        <!-- <option value="Decommissioned">Decommissioned</option> -->
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
                                    <tbody>
                                        <!-- 10 Rows -->
                                        <tr>
                                            <td>1001</td>
                                            <td>Dell PC</td>
                                            <td>PC</td>
                                            <td>PC-01</td>
                                            <td><span class="status status-active">Active</span></td>
                                            <td>Working fine</td>
                                            <td>

                                                <a href="viewDevice.php" class="none">
                                                    <button class="btn-icon view-btn"><i
                                                            class="fas fa-eye"></i></button>
                                                </a>
                                                <a href="editDevice.php" class="none">
                                                    <button class="btn-icon edit-btn"><i
                                                            class="fa-solid fa-pen"></i></button>
                                                </a>
                                                <button class="btn-icon delete-btn" id="delete-trigger"><i
                                                        class="fa-solid fa-trash"></i></button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>1002</td>
                                            <td>Dell PC</td>
                                            <td>PC</td>
                                            <td>PC-02</td>
                                            <td><span class="status status-active">Active</span></td>
                                            <td>N/A</td>
                                            <td>
                                                <a href="viewDevice.php" class="none">
                                                    <button class="btn-icon view-btn"><i
                                                            class="fas fa-eye"></i></button>
                                                </a>
                                                <a href="editDevice.php" class="none">
                                                    <button class="btn-icon edit-btn"><i
                                                            class="fa-solid fa-pen"></i></button>
                                                </a>
                                                <button class="btn-icon delete-btn" id="delete-trigger"><i
                                                        class="fa-solid fa-trash"></i></button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>1003</td>
                                            <td>Dell PC</td>
                                            <td>PC</td>
                                            <td>PC-03</td>
                                            <td><span class="status status-repair">In Repair</span></td>
                                            <td>Hardware issue</td>
                                            <td>
                                                <a href="viewDevice.php" class="none">
                                                    <button class="btn-icon view-btn"><i
                                                            class="fas fa-eye"></i></button>
                                                </a>
                                                <a href="editDevice.php" class="none">
                                                    <button class="btn-icon edit-btn"><i
                                                            class="fa-solid fa-pen"></i></button>
                                                </a>
                                                <button class="btn-icon delete-btn" id="delete-trigger"><i
                                                        class="fa-solid fa-trash"></i></button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>1004</td>
                                            <td>Dell PC</td>
                                            <td>PC</td>
                                            <td>PC-04</td>
                                            <td><span class="status status-active">Active</span></td>
                                            <td>N/A</td>
                                            <td>
                                                <a href="viewDevice.php" class="none">
                                                    <button class="btn-icon view-btn"><i
                                                            class="fas fa-eye"></i></button>
                                                </a>
                                                <a href="editDevice.php" class="none">
                                                    <button class="btn-icon edit-btn"><i
                                                            class="fa-solid fa-pen"></i></button>
                                                </a>
                                                <button class="btn-icon delete-btn" id="delete-trigger"><i
                                                        class="fa-solid fa-trash"></i></button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>1005</td>
                                            <td>Dell PC</td>
                                            <td>PC</td>
                                            <td>PC-05</td>
                                            <td><span class="status status-faulty">Faulty</span></td>
                                            <td>Power supply issue</td>
                                            <td>
                                                <a href="viewDevice.php" class="none">
                                                    <button class="btn-icon view-btn"><i
                                                            class="fas fa-eye"></i></button>
                                                </a>
                                                <a href="editDevice.php" class="none">
                                                    <button class="btn-icon edit-btn"><i
                                                            class="fa-solid fa-pen"></i></button>
                                                </a>
                                                <button class="btn-icon delete-btn" id="delete-trigger"><i
                                                        class="fa-solid fa-trash"></i></button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>1006</td>
                                            <td>HP PC</td>
                                            <td>PC</td>
                                            <td>PC-06</td>
                                            <td><span class="status status-active">Active</span></td>
                                            <td>N/A</td>
                                            <td>
                                                <a href="viewDevice.php" class="none">
                                                    <button class="btn-icon view-btn"><i
                                                            class="fas fa-eye"></i></button>
                                                </a>
                                                <a href="editDevice.php" class="none">
                                                    <button class="btn-icon edit-btn"><i
                                                            class="fa-solid fa-pen"></i></button>
                                                </a>
                                                <button class="btn-icon delete-btn" id="delete-trigger"><i
                                                        class="fa-solid fa-trash"></i></button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>1007</td>
                                            <td>Dell PC</td>
                                            <td>PC</td>
                                            <td>PC-07</td>
                                            <td><span class="status status-active">Active</span></td>
                                            <td>N/A</td>
                                            <td>
                                                <a href="viewDevice.php" class="none">
                                                    <button class="btn-icon view-btn"><i
                                                            class="fas fa-eye"></i></button>
                                                </a>
                                                <a href="editDevice.php" class="none">
                                                    <button class="btn-icon edit-btn"><i
                                                            class="fa-solid fa-pen"></i></button>
                                                </a>
                                                <button class="btn-icon delete-btn" id="delete-trigger"><i
                                                        class="fa-solid fa-trash"></i></button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>1008</td>
                                            <td>Dell PC</td>
                                            <td>PC</td>
                                            <td>PC-08</td>
                                            <td><span class="status status-active">Active</span></td>
                                            <td>N/A</td>
                                            <td>
                                                <a href="viewDevice.php" class="none">
                                                    <button class="btn-icon view-btn"><i
                                                            class="fas fa-eye"></i></button>
                                                </a>
                                                <a href="editDevice.php" class="none">
                                                    <button class="btn-icon edit-btn"><i
                                                            class="fa-solid fa-pen"></i></button>
                                                </a>
                                                <button class="btn-icon delete-btn" id="delete-trigger"><i
                                                        class="fa-solid fa-trash"></i></button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>1009</td>
                                            <td>Dell PC</td>
                                            <td>PC</td>
                                            <td>PC-09</td>
                                            <td><span class="status status-repair">In Repair</span></td>
                                            <td>Software issue</td>
                                            <td>
                                                <a href="viewDevice.php" class="none">
                                                    <button class="btn-icon view-btn"><i
                                                            class="fas fa-eye"></i></button>
                                                </a>
                                                <a href="editDevice.php" class="none">
                                                    <button class="btn-icon edit-btn"><i
                                                            class="fa-solid fa-pen"></i></button>
                                                </a>
                                                <button class="btn-icon delete-btn" id="delete-trigger"><i
                                                        class="fa-solid fa-trash"></i></button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>1010</td>
                                            <td>Lenovo PC</td>
                                            <td>PC</td>
                                            <td>PC-10</td>
                                            <td><span class="status status-active">Active</span></td>
                                            <td>N/A</td>
                                            <td>
                                                <a href="viewDevice.php" class="none">
                                                    <button class="btn-icon view-btn"><i
                                                            class="fas fa-eye"></i></button>
                                                </a>
                                                <a href="editDevice.php" class="none">
                                                    <button class="btn-icon edit-btn"><i
                                                            class="fa-solid fa-pen"></i></button>
                                                </a>
                                                <button class="btn-icon delete-btn" id="delete-trigger"><i
                                                        class="fa-solid fa-trash"></i></button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>

                            </div>
                            <div class="pagination">
                                <a href="#">Previous</a>
                                <a href="#">1</a>
                                <a href="#">2</a>
                                <a href="#">3</a>
                                <a href="#">Next</a>
                            </div>
                        </div>


                        <!-- Tab 2 Content  -->
                        <div class="tab-content" id="devices">
                            <div class="tab-header">
                                <h3>Device Management</h3>
                            </div>

                            <div class="device-grid">
                                <div class="device-card">
                                    <i class="fa-solid fa-desktop"></i>
                                    <div class="device-info">
                                        <span class="device-name">Monitor</span>
                                        <span class="device-status available">Available</span>
                                    </div>
                                </div>
                                <div class="device-card">
                                    <i class="fa-solid fa-computer-mouse"></i>
                                    <div class="device-info">
                                        <span class="device-name">Mouse</span>
                                        <span class="device-status available">Available</span>
                                    </div>
                                </div>
                                <div class="device-card">
                                    <i class="fa-solid fa-microchip"></i>
                                    <div class="device-info">
                                        <span class="device-name">CPU</span>
                                        <span class="device-status available">Available</span>
                                    </div>
                                </div>
                                <div class="device-card">
                                    <i class="fa-solid fa-keyboard"></i>
                                    <div class="device-info">
                                        <span class="device-name">Keyboard</span>
                                        <span class="device-status available">Available</span>
                                    </div>
                                </div>
                                <div class="device-card">
                                    <i class="fas fa-print"></i>
                                    <div class="device-info">
                                        <span class="device-name">Printer</span>
                                        <span class="device-status available">Available</span>
                                    </div>
                                </div>
                                <!-- Add more device cards -->
                            </div>
                            <div class="header1">
                                <div class="filters">
                                    <input class="search-input" type="text" id="search"
                                        placeholder="Search using ID, Name...">

                                    <select id="statusFilter">
                                        <option value="">All Status</option>
                                        <option value="Active">Active</option>
                                        <option value="In Repair">In Repair</option>
                                        <option value="Faulty">Faulty</option>
                                        <!-- <option value="Decommissioned">Decommissioned</option> -->
                                    </select>
                                    <select id="categoryFilter">
                                        <option value="">All Categories</option>
                                        <option value="Monitor">Monitor</option>
                                        <option value="Printer">Printer</option>
                                        <option value="Mouse">Mouse</option>
                                        <option value="Keyboard">Keyboard</option>
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
                                    <tbody>
                                        <tr>
                                            <td>1001</td>
                                            <td>Dell PC</td>
                                            <td>PC</td>
                                            <td>PC-01</td>
                                            <td><span class="status status-active">Active</span></td>
                                            <td>Working fine</td>
                                            <td>
                                                <a href="viewDevice.php" class="none">
                                                    <button class="btn-icon view-btn"><i
                                                            class="fas fa-eye"></i></button>
                                                </a>
                                                <a href="editDevice.php" class="none">
                                                    <button class="btn-icon edit-btn"><i
                                                            class="fa-solid fa-pen"></i></button>
                                                </a>
                                                <button class="btn-icon delete-btn" id="delete-trigger"><i
                                                        class="fa-solid fa-trash"></i></button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>1002</td>
                                            <td>HP Monitor</td>
                                            <td>Monitor</td>
                                            <td>PC-02</td>
                                            <td><span class="status status-active">Active</span></td>
                                            <td>N/A</td>
                                            <td>
                                                <a href="viewDevice.php" class="none">
                                                    <button class="btn-icon view-btn"><i
                                                            class="fas fa-eye"></i></button>
                                                </a>
                                                <a href="editDevice.php" class="none">
                                                    <button class="btn-icon edit-btn"><i
                                                            class="fa-solid fa-pen"></i></button>
                                                </a>
                                                <button class="btn-icon delete-btn" id="delete-trigger"><i
                                                        class="fa-solid fa-trash"></i></button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>1003</td>
                                            <td>Logitech Keyboard</td>
                                            <td>Keyboard</td>
                                            <td>PC-03</td>
                                            <td><span class="status status-repair">In Repair</span></td>
                                            <td>Keys not working</td>
                                            <td>
                                                <a href="viewDevice.php" class="none">
                                                    <button class="btn-icon view-btn"><i
                                                            class="fas fa-eye"></i></button>
                                                </a>
                                                <a href="editDevice.php" class="none">
                                                    <button class="btn-icon edit-btn"><i
                                                            class="fa-solid fa-pen"></i></button>
                                                </a>
                                                <button class="btn-icon delete-btn" id="delete-trigger"><i
                                                        class="fa-solid fa-trash"></i></button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>1004</td>
                                            <td>HP Mouse</td>
                                            <td>Mouse</td>
                                            <td>PC-04</td>
                                            <td><span class="status status-active">Active</span></td>
                                            <td>N/A</td>
                                            <td>
                                                <a href="viewDevice.php" class="none">
                                                    <button class="btn-icon view-btn"><i
                                                            class="fas fa-eye"></i></button>
                                                </a>
                                                <a href="editDevice.php" class="none">
                                                    <button class="btn-icon edit-btn"><i
                                                            class="fa-solid fa-pen"></i></button>
                                                </a>
                                                <button class="btn-icon delete-btn" id="delete-trigger"><i
                                                        class="fa-solid fa-trash"></i></button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>1005</td>
                                            <td>Canon Printer</td>
                                            <td>Printer</td>
                                            <td>PC-05</td>
                                            <td><span class="status status-faulty">Faulty</span></td>
                                            <td>Paper jam</td>
                                            <td>
                                                <a href="viewDevice.php" class="none">
                                                    <button class="btn-icon view-btn"><i
                                                            class="fas fa-eye"></i></button>
                                                </a>
                                                <a href="editDevice.php" class="none">
                                                    <button class="btn-icon edit-btn"><i
                                                            class="fa-solid fa-pen"></i></button>
                                                </a>
                                                <button class="btn-icon delete-btn" id="delete-trigger"><i
                                                        class="fa-solid fa-trash"></i></button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>1006</td>
                                            <td>Intel CPU</td>
                                            <td>CPU</td>
                                            <td>PC-06</td>
                                            <td><span class="status status-active">Active</span></td>
                                            <td>N/A</td>
                                            <td>
                                                <a href="viewDevice.php" class="none">
                                                    <button class="btn-icon view-btn"><i
                                                            class="fas fa-eye"></i></button>
                                                </a>
                                                <a href="editDevice.php" class="none">
                                                    <button class="btn-icon edit-btn"><i
                                                            class="fa-solid fa-pen"></i></button>
                                                </a>
                                                <button class="btn-icon delete-btn" id="delete-trigger"><i
                                                        class="fa-solid fa-trash"></i></button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>1007</td>
                                            <td>LG Monitor</td>
                                            <td>Monitor</td>
                                            <td>PC-07</td>
                                            <td><span class="status status-active">Active</span></td>
                                            <td>N/A</td>
                                            <td>
                                                <a href="viewDevice.php" class="none">
                                                    <button class="btn-icon view-btn"><i
                                                            class="fas fa-eye"></i></button>
                                                </a>
                                                <a href="editDevice.php" class="none">
                                                    <button class="btn-icon edit-btn"><i
                                                            class="fa-solid fa-pen"></i></button>
                                                </a>
                                                <button class="btn-icon delete-btn" id="delete-trigger"><i
                                                        class="fa-solid fa-trash"></i></button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>1008</td>
                                            <td>Razer Keyboard</td>
                                            <td>Keyboard</td>
                                            <td>PC-08</td>
                                            <td><span class="status status-active">Active</span></td>
                                            <td>N/A</td>
                                            <td>
                                                <a href="viewDevice.php" class="none">
                                                    <button class="btn-icon view-btn"><i
                                                            class="fas fa-eye"></i></button>
                                                </a>
                                                <a href="editDevice.php" class="none">
                                                    <button class="btn-icon edit-btn"><i
                                                            class="fa-solid fa-pen"></i></button>
                                                </a>
                                                <button class="btn-icon delete-btn" id="delete-trigger"><i
                                                        class="fa-solid fa-trash"></i></button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>1009</td>
                                            <td>Logitech Mouse</td>
                                            <td>Mouse</td>
                                            <td>PC-09</td>
                                            <td><span class="status status-repair">In Repair</span></td>
                                            <td>Sensor issue</td>
                                            <td>
                                                <a href="viewDevice.php" class="none">
                                                    <button class="btn-icon view-btn"><i
                                                            class="fas fa-eye"></i></button>
                                                </a>
                                                <a href="editDevice.php" class="none">
                                                    <button class="btn-icon edit-btn"><i
                                                            class="fa-solid fa-pen"></i></button>
                                                </a>
                                                <button class="btn-icon delete-btn" id="delete-trigger"><i
                                                        class="fa-solid fa-trash"></i></button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>1010</td>
                                            <td>Brother Printer</td>
                                            <td>Printer</td>
                                            <td>PC-10</td>
                                            <td><span class="status status-active">Active</span></td>
                                            <td>N/A</td>
                                            <td>
                                                <a href="viewDevice.php" class="none">
                                                    <button class="btn-icon view-btn"><i
                                                            class="fas fa-eye"></i></button>
                                                </a>
                                                <a href="editDevice.php" class="none">
                                                    <button class="btn-icon edit-btn"><i
                                                            class="fa-solid fa-pen"></i></button>
                                                </a>
                                                <button class="btn-icon delete-btn" id="delete-trigger"><i
                                                        class="fa-solid fa-trash"></i></button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>

                            </div>
                            <div class="pagination">
                                <a href="#">Previous</a>
                                <a href="#">1</a>
                                <a href="#">2</a>
                                <a href="#">3</a>
                                <a href="#">Next</a>
                            </div>
                        </div>

                        <!-- Tab 3 Content  -->
                        <div class="tab-content" id="grievance">

                            <div class="tab-header">
                                <h3>Grievances</h3>
                            </div>
                            <div class="header1 ">
                                <div class="filters font-rale">
                                    <input class="search-input" type="text" id="search"
                                        placeholder="Search using ID, Name...">
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
                                                    <a href="viewGrievance.php" class="none">
                                                        <button class="btn-icon view-btn"><i
                                                                class="fas fa-eye"></i></button>
                                                    </a>
                                                </a>
                                                <button class="btn-icon edit-btn"><i class="fa-solid fa-pen"></i>
                                                    <button class="btn-icon delete-btn"><i
                                                            class="fa-solid fa-trash"></i></button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>#GRV002</td>
                                            <td>Xyx</td>
                                            <td>Computer 13</td>
                                            <td><span class="status-badge pending">Pending</span></td>
                                            <td>2024-03-16</td>
                                            <td>
                                                <a href="viewGrievance.php" class="none">
                                                    <button class="btn-icon view-btn"><i
                                                            class="fas fa-eye"></i></button>
                                                </a>
                                                <button class="btn-icon edit-btn"><i class="fa-solid fa-pen"></i>
                                                    <button class="btn-icon delete-btn"><i
                                                            class="fa-solid fa-trash"></i></button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>#GRV003</td>
                                            <td>Xyx</td>
                                            <td>Computer 14</td>
                                            <td><span class="status-badge pending">Pending</span></td>
                                            <td>2024-03-17</td>
                                            <td>
                                                <a href="viewGrievance.php" class="none">
                                                    <button class="btn-icon view-btn"><i
                                                            class="fas fa-eye"></i></button>
                                                </a>
                                                <button class="btn-icon edit-btn"><i class="fa-solid fa-pen"></i>
                                                    <button class="btn-icon delete-btn"><i
                                                            class="fa-solid fa-trash"></i></button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>#GRV004</td>
                                            <td>Xyx</td>
                                            <td>Computer 15</td>
                                            <td><span class="status-badge pending">Pending</span></td>
                                            <td>2024-03-18</td>
                                            <td>
                                                <a href="viewGrievance.php" class="none">
                                                    <button class="btn-icon view-btn"><i
                                                            class="fas fa-eye"></i></button>
                                                </a>
                                                <button class="btn-icon edit-btn"><i class="fa-solid fa-pen"></i>
                                                    <button class="btn-icon delete-btn"><i
                                                            class="fa-solid fa-trash"></i></button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>#GRV005</td>
                                            <td>Xyx</td>
                                            <td>Computer 16</td>
                                            <td><span class="status-badge pending">Pending</span></td>
                                            <td>2024-03-19</td>
                                            <td>
                                                <a href="viewGrievance.php" class="none">
                                                    <button class="btn-icon view-btn"><i
                                                            class="fas fa-eye"></i></button>
                                                </a>
                                                <button class="btn-icon edit-btn"><i class="fa-solid fa-pen"></i>
                                                    <button class="btn-icon delete-btn"><i
                                                            class="fa-solid fa-trash"></i></button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>#GRV006</td>
                                            <td>Xyx</td>
                                            <td>Computer 17</td>
                                            <td><span class="status-badge pending">Pending</span></td>
                                            <td>2024-03-20</td>
                                            <td>
                                                <a href="viewGrievance.php" class="none">
                                                    <button class="btn-icon view-btn"><i
                                                            class="fas fa-eye"></i></button>
                                                </a>
                                                <button class="btn-icon edit-btn"><i class="fa-solid fa-pen"></i>
                                                    <button class="btn-icon delete-btn"><i
                                                            class="fa-solid fa-trash"></i></button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>#GRV007</td>
                                            <td>Xyx</td>
                                            <td>Printer 1</td>
                                            <td><span class="status-badge pending">Pending</span></td>
                                            <td>2024-03-21</td>
                                            <td>
                                                <a href="viewGrievance.php" class="none">
                                                    <button class="btn-icon view-btn"><i
                                                            class="fas fa-eye"></i></button>
                                                </a>
                                                <button class="btn-icon edit-btn"><i class="fa-solid fa-pen"></i>
                                                    <button class="btn-icon delete-btn"><i
                                                            class="fa-solid fa-trash"></i></button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>#GRV008</td>
                                            <td>Xyx</td>
                                            <td>Keyboard 2</td>
                                            <td><span class="status-badge pending">Pending</span></td>
                                            <td>2024-03-22</td>
                                            <td>
                                                <a href="viewGrievance.php" class="none">
                                                    <button class="btn-icon view-btn"><i
                                                            class="fas fa-eye"></i></button>
                                                </a>
                                                <button class="btn-icon edit-btn"><i class="fa-solid fa-pen"></i>
                                                    <button class="btn-icon delete-btn"><i
                                                            class="fa-solid fa-trash"></i></button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>#GRV009</td>
                                            <td>Xyx</td>
                                            <td>Mouse 3</td>
                                            <td><span class="status-badge pending">Pending</span></td>
                                            <td>2024-03-23</td>
                                            <td>
                                                <a href="viewGrievance.php" class="none">
                                                    <button class="btn-icon view-btn"><i
                                                            class="fas fa-eye"></i></button>
                                                </a>
                                                <button class="btn-icon edit-btn"><i class="fa-solid fa-pen"></i>
                                                    <button class="btn-icon delete-btn"><i
                                                            class="fa-solid fa-trash"></i></button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>#GRV010</td>
                                            <td>Xyx</td>
                                            <td>Monitor 4</td>
                                            <td><span class="status-badge pending">Pending</span></td>
                                            <td>2024-03-24</td>
                                            <td>
                                                <a href="viewGrievance.php" class="none">
                                                    <button class="btn-icon view-btn"><i
                                                            class="fas fa-eye"></i></button>
                                                </a>
                                                <button class="btn-icon edit-btn"><i class="fa-solid fa-pen"></i>
                                                    <button class="btn-icon delete-btn"><i
                                                            class="fa-solid fa-trash"></i></button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>

                            </div>
                        </div>
                </section>
                <!-- TABBED MENU END -->
                <!-- LAB SUMMARY CARD END -->

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
                            <a href="editLab.php" class="none">
                                <button class="action-item">
                                    <i class="fas fa-pen"></i>
                                    <span>Edit Lab</span>
                                </button>
                            </a>
                            <a href="inventory.php" class="none">
                                <button class="action-item">
                                    <i class="fas fa-boxes"></i>
                                    <span>Inventory</span>
                                </button>
                            </a>

                            <div class="vertical-divider"></div>
                            <!-- <button class="action-item">
                                <i class="fas fa-file-export"></i>
                                <span>Export</span>
                            </button> -->
                            <button class="action-item admin-action">
                                <i class="fas fa-ban"></i>
                                <span>Deactivate</span>
                            </button>
                        </div>
                    </div>
                </div>
            </section>
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
                    <p class="delete-text">Are you sure you want to delete <span id="item-type">this device</span>?</p>
                    <div class="item-name" id="item-name">Abc Device (Lab 000)</div>
                    <p class="delete-warning">This action cannot be undone. The item will be permanently removed from
                        the
                        system.</p>
                </div>

                <div class="popupDelte-footer">
                    <button class="btnPopup btnPopup-cancel" id="cancel-btnPopup">Cancel</button>
                    <button class="btnPopup btnPopup-delete" id="confirm-delete-btnPopup">Delete</button>
                </div>
            </div>
        </div>

    </div>
</body>
<script>
    // Add this script at the end of the body
    document.querySelectorAll('.tab-link').forEach(link => {
        link.addEventListener('click', (e) => {
            // Remove active class from all tabs and content
            document.querySelectorAll('.tab-link, .tab-content').forEach(el => {
                el.classList.remove('active');
            });

            // Add active class to clicked tab and corresponding content
            e.target.classList.add('active');
            const tabId = e.target.dataset.tab;
            document.getElementById(tabId).classList.add('active');
        });
    });

    function toggleMenu(element) {
        // Close all other menus
        document.querySelectorAll('.actions-menu').forEach(menu => {
            if (menu !== element) menu.classList.remove('active');
        });
        // Toggle current menu
        element.classList.toggle('active');
    }
    // Close menu when clicking outside
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.actions-menu')) {
            document.querySelectorAll('.actions-menu').forEach(menu => {
                menu.classList.remove('active');
            });
        }
    });

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

</html>