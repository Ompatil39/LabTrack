<?php
session_start();
if (isset($_SESSION["logged_in"]) !== true) {
    header("Location: login.php");
}
include 'db.php';

$conn->close();
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
    <link rel="stylesheet" href="../public/css/incharge.css" />

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
                <!-- User Info Section -->
                <div class="sub-heading">
                    <span>Manage Lab In-Charge </span>
                </div>
                <div class="user-info" onclick="window.location.href = 'profileManage.php';">
                    <i class="fa-solid fa-circle-user"></i>
                    <span class="font-rale"><?php echo htmlspecialchars(strtoupper($_SESSION['username']) ?? 'User');  ?></span>
                </div>
            </div>
            <!-- CONTENT START of BELOW HEADER -->
            <div class="p-container">
                <!-- Lab In-charge Management Content -->
                <div class="p-card">
                    <div class="p-card-header">
                        <h2 class="p-card-title">Lab In-charge Management</h2>
                        <button class="p-button p-button-primary" id="addNewBtn">
                            <i class="fas fa-plus"></i> Add New
                        </button>
                    </div>

                    <div class="p-search-container">
                        <!-- <div class="p-search-input">
                <input type="text" placeholder="Search by name, lab or email...">
                <button class="p-search-button">
                    <i class="fas fa-search"></i>
                </button>
            </div> -->
                    </div>

                    <div class="p-table-container">
                        <table class="p-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Lab</th>
                                    <th>Email</th>
                                    <th>Contact</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Jane Smith</td>
                                    <td>Programming Lab 101</td>
                                    <td>jane.smith@example.com</td>
                                    <td>+1 (555) 234-5678</td>
                                    <td>
                                        <div class="p-action-buttons">
                                            <button class="p-action-button p-delete-btn" data-id="1">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Robert Johnson</td>
                                    <td>Networking Lab 202</td>
                                    <td>robert.johnson@example.com</td>
                                    <td>+1 (555) 345-6789</td>
                                    <td>
                                        <div class="p-action-buttons">
                                            <button class="p-action-button p-delete-btn" data-id="2">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Lisa Zhang</td>
                                    <td>Cybersecurity Lab 301</td>
                                    <td>lisa.zhang@example.com</td>
                                    <td>+1 (555) 456-7890</td>
                                    <td>
                                        <div class="p-action-buttons">
                                            <button class="p-action-button p-delete-btn" data-id="3">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Add/Edit Lab In-charge Form -->
                <div class="p-card p-form-card" id="inchargeFormCard">
                    <div class="p-card-header">
                        <h2 class="p-card-title" id="formTitle">Add Lab In-charge</h2>
                        <button class="p-button p-button-outline" id="closeFormBtn">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>

                    <form id="inchargeForm">
                        <div class="p-form-row">
                            <div class="p-form-column">
                                <div class="p-form-group">
                                    <label for="username">Username</label>
                                    <input type="text" id="username" placeholder="Enter username" required>
                                </div>
                            </div>
                            <div class="p-form-column">
                                <div class="p-form-group">
                                    <label for="password">Password</label>
                                    <input type="password" id="password" placeholder="Enter password" required>
                                    <div id="passwordValidation" class="p-validation-message"></div>
                                </div>
                            </div>
                        </div>

                        <div class="p-form-row">
                            <div class="p-form-column">
                                <div class="p-form-group">
                                    <label for="fullName">Full Name</label>
                                    <input type="text" id="fullName" placeholder="Enter full name" required>
                                </div>
                            </div>
                            <div class="p-form-column">
                                <div class="p-form-group">
                                    <label for="email">Email</label>
                                    <input type="email" id="email" placeholder="Enter email address" required>
                                </div>
                            </div>
                        </div>

                        <div class="p-form-row">
                            <div class="p-form-column">
                                <div class="p-form-group">
                                    <label for="contactNumber">Contact Number</label>
                                    <input type="tel" id="contactNumber" placeholder="Enter contact number" required>
                                </div>
                            </div>
                            <div class="p-form-column">
                                <div class="p-form-group">
                                    <label for="assignedLab">Assigned Lab</label>
                                    <select id="assignedLab" required>
                                        <option value="">Select a lab</option>
                                        <option value="lab101">Programming Lab 101</option>
                                        <option value="lab202">Networking Lab 202</option>
                                        <option value="lab301">Cybersecurity Lab 301</option>
                                        <option value="lab401">AI/ML Lab 401</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="p-actions">
                            <button type="button" class="p-button p-button-outline" id="cancelBtn">Cancel</button>
                            <button type="submit" class="p-button p-button-primary">Save</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- JavaScript for functionality -->
            <script>
                // Password validation function
                function validatePassword() {
                    const password = document.getElementById('password').value;
                    const passwordValidation = document.getElementById('passwordValidation');
                    const isValid = password.length >= 8 && /\d/.test(password) && /[a-zA-Z]/.test(password);

                    if (!isValid) {
                        passwordValidation.style.display = 'block';
                        passwordValidation.textContent = 'Password must be at least 8 characters and contain both letters and numbers.';
                        return false;
                    }

                    passwordValidation.style.display = 'none';
                    return true;
                }

                // Show/hide form functionality with smooth scroll
                document.getElementById('addNewBtn').addEventListener('click', function() {
                    document.getElementById('inchargeFormCard').classList.add('active');
                    document.getElementById('formTitle').textContent = 'Add Lab In-charge';
                    document.getElementById('inchargeForm').reset();
                    document.getElementById('inchargeFormCard').scrollIntoView({
                        behavior: 'smooth'
                    });
                });

                document.getElementById('closeFormBtn').addEventListener('click', function() {
                    document.getElementById('inchargeFormCard').classList.remove('active');
                });

                document.getElementById('cancelBtn').addEventListener('click', function() {
                    document.getElementById('inchargeFormCard').classList.remove('active');
                });

                // Delete functionality
                document.querySelectorAll('.p-delete-btn').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        const id = this.getAttribute('data-id');
                        if (confirm('Are you sure you want to delete this in-charge?')) {
                            // Here you would typically send an API request to delete the user
                            alert('User deleted successfully!');
                            // Then remove the row from the table
                            this.closest('tr').remove();
                        }
                    });
                });

                // Form submission
                document.getElementById('inchargeForm').addEventListener('submit', function(e) {
                    e.preventDefault();
                    if (validatePassword()) {
                        // Here you would typically send an API request to save the user data
                        alert('Lab In-charge saved successfully!');
                        document.getElementById('inchargeFormCard').classList.remove('active');
                    }
                });
            </script>
            <!-- CONTENT END of BELOW HEADER  -->
        </div>
    </div>
</body>

</html>