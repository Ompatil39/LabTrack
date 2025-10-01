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
                    <span>Edit Devices </span>
                </div>
                <div class="user-info">
                    <i class="fa-solid fa-circle-user"></i><span class="font-rale"> Administrator</span>
                </div>
            </div>

            <!-- CONTENT START of BELOW HEADER -->
            <div class="lab-management-container">
                <div class="form-wrapper">
                    <!-- Details Section -->
                    <div class="form-section" id="lab-details">
                        <div class="section-header">
                            <h2 class="section-title">Edit Device Details</h2>
                        </div>

                        <div class="selectLabDevice">
                            <div class="input-group">
                                <label class="input-label">Select Lab <span>*</span></label>
                                <select class="form-input" required>
                                    <option value="">Select Lab</option>
                                    <option selected>COA101</option> <!-- Pre-selected -->
                                    <option>COB102</option>
                                </select>
                            </div>
                            <div class="input-group" style="margin-bottom: 0rem !important;">
                                <label class="input-label">Select Device <span>*</span></label>
                                <select class="form-input" required>
                                    <option value="">Select Device</option>
                                    <option selected onclick="showPC()">PC</option> <!-- Pre-selected -->
                                    <option onclick="showPrinter()">Printer</option>
                                </select>
                            </div>
                        </div>

                        <!-- PC Input Section -->
                        <div class="pcInput" id="pcInput">
                            <div class="stepper">
                                <div class="stepper-item active">1. PC Details</div>
                                <div class="stepper-item">2. Monitor</div>
                                <div class="stepper-item">3. Keyboard</div>
                                <div class="stepper-item">4. Mouse</div>
                                <div class="stepper-item">5. CPU</div>
                                <div class="stepper-item">6. Connectivity</div>
                            </div>

                            <!-- PC Details -->
                            <div class="step-content" id="step-1">
                                <div class="grid-3col">
                                    <div class="input-group">
                                        <label class="input-label">PC Name <span>*</span></label>
                                        <input type="text" class="form-input" value="HP PC" required>
                                        <!-- Pre-filled -->
                                    </div>
                                    <div class="input-group">
                                        <label class="input-label">PC Quantity <span>*</span></label>
                                        <input type="number" class="form-input" value="10" required> <!-- Pre-filled -->
                                    </div>
                                    <div class="input-group">
                                        <label class="input-label">PC Code </label>
                                        <input type="text" id="labCode" class="no-hover form-input" value="DEV-001"
                                            disabled> <!-- Pre-filled -->
                                    </div>
                                    <div class="input-group">
                                        <label class="input-label">Serial Number </label>
                                        <input type="number" class="form-input" value="123456789"> <!-- Pre-filled -->
                                    </div>
                                    <div class="input-group">
                                        <label class="input-label">Processor <span>*</span></label>
                                        <input type="text" class="form-input" value="Intel Core i7" required>
                                        <!-- Pre-filled -->
                                    </div>
                                    <div class="input-group">
                                        <label class="input-label">RAM <span>*</span></label>
                                        <input type="text" class="form-input" value="16GB DDR4" required>
                                        <!-- Pre-filled -->
                                    </div>
                                    <div class="input-group">
                                        <label class="input-label">Storage (HDD/SSD) <span>*</span></label>
                                        <input type="text" class="form-input" value="1TB SSD" required>
                                        <!-- Pre-filled -->
                                    </div>
                                    <div class="input-group">
                                        <label class="input-label">Operating System </label>
                                        <input type="text" class="form-input" value="Windows 11" required>
                                        <!-- Pre-filled -->
                                    </div>
                                    <div class="input-group">
                                        <label class="input-label">PC Status <span>*</span></label>
                                        <select class="form-input" required>
                                            <option value="">Status</option>
                                            <option selected>Active</option> <!-- Pre-selected -->
                                            <option>In-Active</option>
                                            <option>Under Repair</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Monitor -->
                            <div class="step-content" id="step-2" style="display: none;">
                                <div class="grid-3col">
                                    <div class="input-group">
                                        <label class="input-label">Monitor Brand & Model <span>*</span></label>
                                        <input type="text" class="form-input" value="Dell 22-inch LED" required>
                                        <!-- Pre-filled -->
                                    </div>
                                    <div class="input-group">
                                        <label class="input-label">Monitor Resolution <span>*</span></label>
                                        <input type="text" class="form-input" value="1920x1080" required>
                                        <!-- Pre-filled -->
                                    </div>
                                    <div class="input-group">
                                        <label class="input-label">Monitor Serial Number</label>
                                        <input type="text" class="form-input" value="SN-987654"> <!-- Pre-filled -->
                                    </div>
                                    <div class="input-group">
                                        <label class="input-label">Monitor Status <span>*</span></label>
                                        <select class="form-input" required>
                                            <option value="">Select Status</option>
                                            <option selected>Working</option> <!-- Pre-selected -->
                                            <option>Needs Repair</option>
                                            <option>Replaced</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Keyboard -->
                            <div class="step-content" id="step-3" style="display: none;">
                                <div class="grid-3col">
                                    <div class="input-group">
                                        <label class="input-label">Keyboard Name</label>
                                        <input type="text" class="form-input" value="Logitech K120"> <!-- Pre-filled -->
                                    </div>
                                    <div class="input-group">
                                        <label class="input-label">Keyboard Type <span>*</span></label>
                                        <select class="form-input" required>
                                            <option value="">Select Type</option>
                                            <option selected>Wired</option> <!-- Pre-selected -->
                                            <option>Wireless</option>
                                            <option>Mechanical</option>
                                            <option>Membrane</option>
                                        </select>
                                    </div>
                                    <div class="input-group">
                                        <label class="input-label">Keyboard Serial Number</label>
                                        <input type="text" class="form-input" value="SN-KB-123"> <!-- Pre-filled -->
                                    </div>
                                    <div class="input-group">
                                        <label class="input-label">Keyboard Status <span>*</span></label>
                                        <select class="form-input" required>
                                            <option value="">Select Status</option>
                                            <option selected>Working</option> <!-- Pre-selected -->
                                            <option>Needs Repair</option>
                                            <option>Replaced</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Mouse -->
                            <div class="step-content" id="step-4" style="display: none;">
                                <div class="grid-3col">
                                    <div class="input-group">
                                        <label class="input-label">Mouse Name</label>
                                        <input type="text" class="form-input" value="Logitech M100"> <!-- Pre-filled -->
                                    </div>
                                    <div class="input-group">
                                        <label class="input-label">Mouse Type <span>*</span></label>
                                        <select class="form-input" required>
                                            <option value="">Select Type</option>
                                            <option selected>Wired</option> <!-- Pre-selected -->
                                            <option>Wireless</option>
                                        </select>
                                    </div>
                                    <div class="input-group">
                                        <label class="input-label">Mouse Serial Number</label>
                                        <input type="text" class="form-input" value="SN-M-456"> <!-- Pre-filled -->
                                    </div>
                                    <div class="input-group">
                                        <label class="input-label">Mouse Status <span>*</span></label>
                                        <select class="form-input" required>
                                            <option value="">Select Status</option>
                                            <option selected>Working</option> <!-- Pre-selected -->
                                            <option>Needs Repair</option>
                                            <option>Replaced</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- CPU -->
                            <div class="step-content" id="step-5" style="display: none;">
                                <div class="grid-3col">
                                    <div class="input-group">
                                        <label class="input-label">CPU Case Model</label>
                                        <input type="text" class="form-input" value="Cooler Master Mid-Tower">
                                        <!-- Pre-filled -->
                                    </div>
                                    <div class="input-group">
                                        <label class="input-label">CPU Serial Number</label>
                                        <input type="text" class="form-input" value="SN-CPU-789"> <!-- Pre-filled -->
                                    </div>
                                    <div class="input-group">
                                        <label class="input-label">Power Supply Unit (PSU) <span>*</span></label>
                                        <input type="text" class="form-input" value="450W" required> <!-- Pre-filled -->
                                    </div>
                                    <div class="input-group">
                                        <label class="input-label">CPU Status <span>*</span></label>
                                        <select class="form-input" required>
                                            <option value="">Select Status</option>
                                            <option selected>Working</option> <!-- Pre-selected -->
                                            <option>Needs Repair</option>
                                            <option>Replaced</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Connectivity -->
                            <div class="step-content" id="step-6" style="display: none;">
                                <div class="grid-3col">
                                    <div class="input-group">
                                        <label class="input-label">Ethernet MAC Address</label>
                                        <input type="text" class="form-input" value="00:1A:2B:3C:4D:5E">
                                        <!-- Pre-filled -->
                                    </div>
                                    <div class="input-group">
                                        <label class="input-label">WiFi Adapter</label>
                                        <input type="text" class="form-input" value="TP-Link AC600"> <!-- Pre-filled -->
                                    </div>
                                    <div class="input-group">
                                        <label class="input-label">IP Address</label>
                                        <input type="text" class="form-input" value="192.168.1.100"> <!-- Pre-filled -->
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Printer Input Section -->
                        <div class="printerInput" id="printerInput" style="display: none;">
                            <div class="tab-header" style="color: #7f8c8d !important; font-weight: 500 !important;">
                                <h4>Printer</h4>
                            </div>
                            <div class="grid-2col">
                                <div class="input-group">
                                    <label class="input-label">Printer Model <span>*</span></label>
                                    <input type="text" class="form-input" value="HP LaserJet Pro" required>
                                    <!-- Pre-filled -->
                                </div>
                                <div class="input-group">
                                    <label class="input-label">Printer Quantity </label>
                                    <input type="number" class="form-input" value="2"> <!-- Pre-filled -->
                                </div>
                                <div class="input-group">
                                    <label class="input-label">Printer Type <span>*</span></label>
                                    <select class="form-input" required>
                                        <option value="">Select Printer Type</option>
                                        <option selected>Laser</option> <!-- Pre-selected -->
                                        <option>Inkjet</option>
                                        <option>Dot Matrix</option>
                                    </select>
                                </div>
                                <div class="input-group">
                                    <label class="input-label">Color Capability <span>*</span></label>
                                    <select class="form-input" required>
                                        <option value="">Select Option</option>
                                        <option selected>Black & White</option> <!-- Pre-selected -->
                                        <option>Color</option>
                                    </select>
                                </div>
                                <div class="input-group">
                                    <label class="input-label">Connectivity <span>*</span></label>
                                    <select class="form-input" required>
                                        <option value="">Select Connectivity</option>
                                        <option selected>USB</option> <!-- Pre-selected -->
                                        <option>Network</option>
                                        <option>Wireless</option>
                                    </select>
                                </div>
                                <div class="input-group">
                                    <label class="input-label">Printer Serial Number </label>
                                    <input type="text" class="form-input" value="SN-PRT-123"> <!-- Pre-filled -->
                                </div>
                                <div class="input-group">
                                    <label class="input-label">Printer Status <span>*</span></label>
                                    <select class="form-input" required>
                                        <option value="">Status</option>
                                        <option selected>Active</option> <!-- Pre-selected -->
                                        <option>In-Active</option>
                                        <option>Under Repair</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Save Device Button -->
                        <div class="form-actions">
                            <button type="button" class="btn btn-primary" id="addLabBtn">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-save">
                                    <path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z" />
                                    <path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7" />
                                    <path d="M7 3v4a1 1 0 0 0 1 1h7" />
                                </svg>
                                Save Details
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <!-- CONTENT END of BELOW HEADER  -->
        </div>
        <!-- CONTENT END  -->
    </div>
</body>

</html>