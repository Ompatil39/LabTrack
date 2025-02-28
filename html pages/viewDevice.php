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
                <h2>Lenovo PC</h2>
                <span class="highlight1 float-right">
                    <i class="fas fa-check-circle"></i> Active
                </span>
            </div>
            <!-- card 1 -->
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
                        <div class="label-value">PC-2025-001</div>
                    </div>
                    <div class="input-group">
                        <label class="detail-label">Device Name</label>
                        <div class="label-value">Lenevo PC</div>
                    </div>
                    <div class="input-group">
                        <label class="detail-label">Category</label>
                        <div class="label-value">PC</div>
                    </div>
                    <div>
                        <label class="detail-label">Assigned Lab</label>
                        <div class="label-value">CO-2025-101</div>
                    </div>
                    <div>
                        <label class="detail-label">Lab Location</label>
                        <div class="label-value">Building A - Room 310</div>
                    </div>
                    <div>
                        <label class="detail-label">Status</label>
                        <div class="label-value">Active</div>
                    </div>
                </div>
            </div>

            <!-- card 2 -->
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
                    <div class="input-group">
                        <label class="detail-label">Serial Number</label>
                        <div class="label-value">NA</div>
                    </div>
                    <div class="input-group">
                        <label class="detail-label">RAM</label>
                        <div class="label-value">8GB</div>
                    </div>
                    <div class="input-group">
                        <label class="detail-label">Processor</label>
                        <div class="label-value">Intel Core i5</div>
                    </div>
                    <div class="input-group">
                        <label class="detail-label">Operating System</label>
                        <div class="label-value">Windows 10</div>
                    </div>
                    <div class="input-group">
                        <label class="detail-label">Storage</label>
                        <div class="label-value">512GB HDD</div>
                    </div>
                    <div>
                        <label class="detail-label">IP Address</label>
                        <div class="label-value">127.0.0.1</div>
                    </div>
                    <div>
                        <label class="detail-label">Ethernet MAC Address</label>
                        <div class="label-value">NA</div>
                    </div>
                    <div>
                        <label class="detail-label">WiFi Adapter</label>
                        <div class="label-value">NA</div>
                    </div>
                </div>
            </div>

            <!-- card 3 -->
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
                        <button class="btnPopup btnPopup-edit btnDetails" id="confirm-edit-btnPopup">
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
                        <button class="btnPopup btnPopup-view btnDetails" id="confirm-delete-btnPopup">
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
                        <button class="btnPopup btnPopup-delete btnDetails" id="confirm-delete-btnPopup">
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