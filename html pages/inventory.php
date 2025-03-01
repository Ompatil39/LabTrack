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
                <li class="active">
                    <a href="inventory.php">
                        <i class="fa-solid fa-warehouse"></i> <span>Inventory</span>
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
                    <span>Inventory Management</span>
                </div>
                <div class="user-info">
                    <!-- <img alt="User Avatar" src="https://placehold.co/30x30" /> -->
                    <i class="fa-solid fa-circle-user"></i><span class="font-rale"> Administrator
                    </span>
                </div>
            </div>

            <!-- CONTENT START BELOW HEADER -->
            <!-- Card View -->
            <div class="card-view">
                <div class="card blue-top">
                    <h3>Total Labs</h3>
                    <p class="font-number">8</p>
                    <i class="fa-solid fa-server blue-hover"></i>
                </div>
                <div class="card green-top">
                    <h3>Total Devices</h3>
                    <p class="font-number">160</p>
                    <i class="fa-solid fa-computer green-hover"></i>
                </div>
                <div class="card yellow-top">
                    <h3>In Repair</h3>
                    <p class="font-number">30</p>
                    <i class="fas fa-tools yellow-hover"></i>
                </div>
                <div class="card red-top">
                    <h3>Faulty Devices</h3>
                    <p class="font-number">40</p>
                    <i class="fas fa-triangle-exclamation red-hover"></i>
                </div>
            </div>
            <!-- CARDs VIEW END -->
            <!-- Table View -->
            <div class="items margin-bottom">
                <div class="sub-heading">
                    <span>Lab Devices Inventory</span>
                </div>

                <div class="header1">
                    <div class="filters">
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
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th onclick="sortTable(0)">Device ID <i class="fas fa-sort"></i></th>
                                <th onclick="sortTable(1)">Device Name <i class="fas fa-sort"></i></th>
                                <th onclick="sortTable(2)">Category <i class="fas fa-sort"></i></th>
                                <th onclick="sortTable(3)">Lab <i class="fas fa-sort"></i></th>
                                <th onclick="sortTable(4)">PC <i class="fas fa-sort"></i></th>
                                <th onclick="sortTable(5)">Status <i class="fas fa-sort"></i></th>
                                <th onclick="sortTable(6)">Remarks <i class="fas fa-sort"></i></th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>1001</td>
                                <td>Dell Monitor</td>
                                <td>Monitor</td>
                                <td>Lab A</td>
                                <td>PC-01</td>
                                <td><span class="status status-active">Active</span></td>
                                <td>Working fine</td>
                                <td>
                                    <a href="viewDevice.php?device_id=123" class="none">
                                        <button class="btn-icon view-btn"><i class="fas fa-eye"></i></button>
                                    </a>

                                    <a href="editDevice.php" class="none">
                                        <button class="btn-icon edit-btn"><i class="fa-solid fa-pen"></i></button>
                                    </a>
                                    <button class="btn-icon delete-btn" id="delete-trigger"><i
                                            class="fa-solid fa-trash"></i></button>
                                </td>
                            </tr>
                            <tr>
                                <td>1002</td>
                                <td>HP Printer</td>
                                <td>Printer</td>
                                <td>Lab B</td>
                                <td>PC-05</td>
                                <td><span class="status status-repair">In Repair</span></td>
                                <td>Paper jam issue</td>
                                <td>
                                    <a href="viewDevice.php" class="none">
                                        <button class="btn-icon view-btn"><i class="fas fa-eye"></i></button>
                                    </a>
                                    <a href="editDevice.php" class="none">
                                        <button class="btn-icon edit-btn"><i class="fa-solid fa-pen"></i></button>
                                    </a>
                                    <button class="btn-icon delete-btn" id="delete-trigger"><i
                                            class="fa-solid fa-trash"></i></button>
                                </td>
                            </tr>
                            <tr>
                                <td>1003</td>
                                <td>Logitech Mouse</td>
                                <td>Mouse</td>
                                <td>Lab C</td>
                                <td>PC-08</td>
                                <td><span class="status status-faulty">Faulty</span></td>
                                <td>Left click not working</td>
                                <td>
                                    <a href="viewDevice.php" class="none">
                                        <button class="btn-icon view-btn"><i class="fas fa-eye"></i></button>
                                    </a>
                                    <a href="editDevice.php" class="none">
                                        <button class="btn-icon edit-btn"><i class="fa-solid fa-pen"></i></button>
                                    </a>
                                    <button class="btn-icon delete-btn" id="delete-trigger"><i
                                            class="fa-solid fa-trash"></i></button>
                                </td>
                            </tr>
                            <tr>
                                <td>1004</td>
                                <td>Dell Keyboard</td>
                                <td>Keyboard</td>
                                <td>Lab A</td>
                                <td>PC-03</td>
                                <td><span class="status status-active">Active</span></td>
                                <td>N/A</td>
                                <td>
                                    <a href="viewDevice.php" class="none">
                                        <button class="btn-icon view-btn"><i class="fas fa-eye"></i></button>
                                    </a>
                                    <a href="editDevice.php" class="none">
                                        <button class="btn-icon edit-btn"><i class="fa-solid fa-pen"></i></button>
                                    </a>
                                    <button class="btn-icon delete-btn" id="delete-trigger"><i
                                            class="fa-solid fa-trash"></i></button>
                                </td>
                            </tr>
                            <tr>
                                <td>1005</td>
                                <td>HP Monitor</td>
                                <td>Monitor</td>
                                <td>Lab B</td>
                                <td>PC-07</td>
                                <td><span class="status status-repair">Repair</span></td>
                                <td>Screen flickering</td>
                                <td>
                                    <a href="viewDevice.php" class="none">
                                        <button class="btn-icon view-btn"><i class="fas fa-eye"></i></button>
                                    </a>
                                    <a href="editDevice.php" class="none">
                                        <button class="btn-icon edit-btn"><i class="fa-solid fa-pen"></i></button>
                                    </a>
                                    <button class="btn-icon delete-btn" id="delete-trigger"><i
                                            class="fa-solid fa-trash"></i></button>
                                </td>
                            </tr>
                            <tr>
                                <td>1006</td>
                                <td>Logitech Mouse</td>
                                <td>Mouse</td>
                                <td>Lab C</td>
                                <td>PC-10</td>
                                <td><span class="status status-active">Active</span></td>
                                <td>N/A</td>
                                <td>
                                    <a href="viewDevice.php" class="none">
                                        <button class="btn-icon view-btn"><i class="fas fa-eye"></i></button>
                                    </a>
                                    <a href="editDevice.php" class="none">
                                        <button class="btn-icon edit-btn"><i class="fa-solid fa-pen"></i></button>
                                    </a>
                                    <button class="btn-icon delete-btn" id="delete-trigger"><i
                                            class="fa-solid fa-trash"></i></button>
                                </td>
                            </tr>
                            <tr>
                                <td>1007</td>
                                <td>Dell Monitor</td>
                                <td>Monitor</td>
                                <td>Lab D</td>
                                <td>PC-12</td>
                                <td><span class="status status-faulty">Faulty</span></td>
                                <td>Dead pixels</td>
                                <td>
                                    <a href="viewDevice.php" class="none">
                                        <button class="btn-icon view-btn"><i class="fas fa-eye"></i></button>
                                    </a>
                                    <a href="editDevice.php" class="none">
                                        <button class="btn-icon edit-btn"><i class="fa-solid fa-pen"></i></button>
                                    </a>
                                    <button class="btn-icon delete-btn" id="delete-trigger"><i
                                            class="fa-solid fa-trash"></i></button>
                                </td>
                            </tr>
                            <tr>
                                <td>1008</td>
                                <td>HP Keyboard</td>
                                <td>Keyboard</td>
                                <td>Lab A</td>
                                <td>PC-04</td>
                                <td><span class="status status-active">Active</span></td>
                                <td>N/A</td>
                                <td>
                                    <a href="viewDevice.php" class="none">
                                        <button class="btn-icon view-btn"><i class="fas fa-eye"></i></button>
                                    </a>
                                    <a href="editDevice.php" class="none">
                                        <button class="btn-icon edit-btn"><i class="fa-solid fa-pen"></i></button>
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
            <!-- TABLE VIEW END -->
            <!-- TABLE 2 -->
            <div class="items">
                <div class="sub-heading">
                    <span>Spare Parts</span>
                </div>
                <div class="table-container">
                    <table id="partsTable" class="display">
                        <thead>
                            <tr>
                                <th>Part ID</th>
                                <th>Part Name</th>
                                <th>Quantity</th>
                                <th>Category</th>
                                <th>Vendor</th>
                                <th>Purchase Date</th>
                                <th>Price (INR)</th>
                                <th>Status</th>
                                <th>Edit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>SP-001</td>
                                <td>8GB DDR4 RAM</td>
                                <td>15</td>
                                <td>Hardware</td>
                                <td>TechSupplies Inc</td>
                                <td>2023-09-15</td>
                                <td>₹3,486</td>
                                <td><span class="in-stock">In Stock</span></td>
                                <td><a href="editDevice.php" class="none">
                                        <button class="btn-icon edit-btn"><i class="fa-solid fa-pen"></i></button>
                                    </a></td>
                            </tr>
                            <tr>
                                <td>SP-002</td>
                                <td>500GB SSD</td>
                                <td>8</td>
                                <td>Storage</td>
                                <td>StorageMasters</td>
                                <td>2023-10-01</td>
                                <td>₹5,603</td>
                                <td><span class="low-stock">Low Stock</span></td>
                                <td><a href="editDevice.php" class="none">
                                        <button class="btn-icon edit-btn"><i class="fa-solid fa-pen"></i></button>
                                    </a></td>
                            </tr>
                            <tr>
                                <td>SP-003</td>
                                <td>USB-C Hub</td>
                                <td>25</td>
                                <td>Peripherals</td>
                                <td>ConnectTech</td>
                                <td>2023-08-22</td>
                                <td>₹2,489</td>
                                <td><span class=" in-stock">In Stock</span></td>
                                <td><a href="editDevice.php" class="none">
                                        <button class="btn-icon edit-btn"><i class="fa-solid fa-pen"></i></button>
                                    </a></td>
                            </tr>
                            <tr>
                                <td>SP-004</td>
                                <td>WiFi Adapter</td>
                                <td>3</td>
                                <td>Networking</td>
                                <td>NetGear Pro</td>
                                <td>2023-11-05</td>
                                <td>₹1,572</td>
                                <td><span class=" out-of-stock">Out of Stock</span></td>
                                <td><a href="editDevice.php" class="none">
                                        <button class="btn-icon edit-btn"><i class="fa-solid fa-pen"></i></button>
                                    </a></td>
                            </tr>
                            <tr>
                                <td>SP-005</td>
                                <td>Keyboard (Wired)</td>
                                <td>12</td>
                                <td>Peripherals</td>
                                <td>InputMasters</td>
                                <td>2023-10-18</td>
                                <td>₹2,074</td>
                                <td><span class=" in-stock">In Stock</span></td>
                                <td><a href="editDevice.php" class="none">
                                        <button class="btn-icon edit-btn"><i class="fa-solid fa-pen"></i></button>
                                    </a></td>
                            </tr>
                            <tr>
                                <td>SP-006</td>
                                <td>24" Monitor</td>
                                <td>5</td>
                                <td>Display</td>
                                <td>DisplayTech</td>
                                <td>2023-09-30</td>
                                <td>₹13,197</td>
                                <td><span class=" low-stock">Low Stock</span></td>
                                <td><a href="editDevice.php" class="none">
                                        <button class="btn-icon edit-btn"><i class="fa-solid fa-pen"></i></button>
                                    </a></td>
                            </tr>
                            <tr>
                                <td>SP-007</td>
                                <td>CPU Cooler</td>
                                <td>7</td>
                                <td>Cooling</td>
                                <td>CoolMaster</td>
                                <td>2023-11-12</td>
                                <td>₹3,735</td>
                                <td><span class=" in-stock">In Stock</span></td>
                                <td><a href="editDevice.php" class="none">
                                        <button class="btn-icon edit-btn"><i class="fa-solid fa-pen"></i></button>
                                    </a></td>
                            </tr>
                            <tr>
                                <td>SP-008</td>
                                <td>HDMI Cables</td>
                                <td>35</td>
                                <td>Cables</td>
                                <td>ConnectTech</td>
                                <td>2023-07-14</td>
                                <td>₹746</td>
                                <td><span class=" in-stock">In Stock</span></td>
                                <td><a href="editDevice.php" class="none">
                                        <button class="btn-icon edit-btn"><i class="fa-solid fa-pen"></i></button>
                                    </a></td>
                            </tr>
                            <tr>
                                <td>SP-009</td>
                                <td>Power Supply 650W</td>
                                <td>4</td>
                                <td>Power</td>
                                <td>PowerEdge</td>
                                <td>2023-10-29</td>
                                <td>₹7,469</td>
                                <td><span class=" low-stock">Low Stock</span></td>
                                <td><a href="editDevice.php" class="none">
                                        <button class="btn-icon edit-btn"><i class="fa-solid fa-pen"></i></button>
                                    </a></td>
                            </tr>
                            <tr>
                                <td>SP-010</td>
                                <td>Webcam 1080p</td>
                                <td>9</td>
                                <td>Peripherals</td>
                                <td>CamTech</td>
                                <td>2023-11-08</td>
                                <td>₹4,146</td>
                                <td><span class=" in-stock">In Stock</span></td>
                                <td><a href="editDevice.php" class="none">
                                        <button class="btn-icon edit-btn"><i class="fa-solid fa-pen"></i></button>
                                    </a></td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="pagination">
                        <a href="#">Previous</a>
                        <a href="#">1</a>
                        <a href="#">2</a>
                        <a href="#">3</a>
                        <a href="#">Next</a>
                    </div>
                </div>
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
                <p class="delete-text">Are you sure you want to delete <span id="item-type">this device</span>?</p>
                <div class="item-name" id="item-name">Abc Device (Lab 000)</div>
                <p class="delete-warning">This action cannot be undone. The item will be permanently removed from the
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
    function toggleMenu(element) {
        // Close all other menus
        document.querySelectorAll('.actions-menu').forEach(menu => {
            if (menu !== element) menu.classList.remove('active');
        });
        // Toggle current menu
        element.classList.toggle('active');
    }
    // Close menu when clicking outside
    document.addEventListener('click', function(e) {
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

    deleteBtnPopup.addEventListener('click', function() {
        popupDelte.style.display = 'flex';
    });

    cancelBtnPopup.addEventListener('click', function() {
        popupDelte.style.display = 'none';
    });

    confirmBtnPopup.addEventListener('click', function() {
        alert('Item deleted successfully!');
        popupDelte.style.display = 'none';
    });

    popupDelte.addEventListener('click', function(e) {
        if (e.target === popupDelte) {
            popupDelte.style.display = 'none';
        }
    });
</script>

</html>