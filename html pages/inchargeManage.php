<?php
session_start();
if (isset($_SESSION["logged_in"]) !== true) {
    header("Location: login.php");
    exit();
}

include 'db.php';

// Function to sanitize input data
function sanitize_input($data)
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Function to validate Indian phone number (10 digits, may start with +91)
function validate_indian_phone($phone)
{
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) == 10) {
        return true;
    } elseif (strlen($phone) == 12 && substr($phone, 0, 2) == '91') {
        return true;
    }
    return false;
}

// Function to check if username already exists
function is_username_unique($conn, $username)
{
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
    if ($stmt === false) {
        return false; // Handle error gracefully
    }
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();
    $is_unique = $stmt->num_rows === 0;
    $stmt->close();
    return $is_unique;
}

// Function to check if email already exists
function is_email_unique($conn, $email)
{
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    if ($stmt === false) {
        return false; // Handle error gracefully
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    $is_unique = $stmt->num_rows === 0;
    $stmt->close();
    return $is_unique;
}

// Get all lab in-charge users
$incharge_users = [];
$sql = "SELECT user_id, username, full_name, email, contact_number 
        FROM users 
        WHERE user_type = 'Incharge'";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $incharge_users[] = $row;
    }
}

// Add new lab in-charge
$errors = [];
$success_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add_incharge') {
    $username = sanitize_input($_POST['username']);
    $password = $_POST['password']; // Will be hashed
    $full_name = sanitize_input($_POST['fullName']);
    $email = sanitize_input($_POST['email']);
    $contact_number = sanitize_input($_POST['contactNumber']);

    // Validate inputs
    if (empty($username) || strlen($username) < 4) {
        $errors[] = "Username must be at least 4 characters long";
    } elseif (!is_username_unique($conn, $username)) {
        $errors[] = "Username already exists. Please choose another one.";
    }

    if (empty($password) || strlen($password) < 8 || !preg_match('/[0-9]/', $password) || !preg_match('/[a-zA-Z]/', $password)) {
        $errors[] = "Password must be at least 8 characters and contain both letters and numbers.";
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    } elseif (!is_email_unique($conn, $email)) {
        $errors[] = "Email address already in use. Please use another one.";
    }

    if (empty($contact_number) || !validate_indian_phone($contact_number)) {
        $errors[] = "Please enter a valid Indian phone number (10 digits).";
    }

    // If no errors, proceed with insertion
    if (empty($errors)) {
        // Generate a random user_id
        $user_id = 'INC_' . rand(10000, 99999);

        // Hash the password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        try {
            // Check connection
            if ($conn->connect_error) {
                throw new Exception("Database connection lost: " . $conn->connect_error);
            }
            if (!$conn->ping()) {
                throw new Exception("Database server not responding: " . $conn->error);
            }

            // Insert into users table
            $query = "INSERT INTO users (user_id, username, password, email, user_type, full_name, contact_number) VALUES (?, ?, ?, ?, 'Incharge', ?, ?)";
            error_log("Executing query: $query with user_id=$user_id, username=$username, email=$email");

            $stmt = $conn->prepare($query);
            if ($stmt === false) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("ssssss", $user_id, $username, $hashed_password, $email, $full_name, $contact_number);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }

            $success_message = "Lab In-charge added successfully!";
            header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
            exit();
        } catch (Exception $e) {
            $errors[] = "Error: " . $e->getMessage();
        }
    }
}

// Delete an in-charge
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'delete_incharge') {
    $user_id = sanitize_input($_POST['user_id']);

    try {
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        if ($stmt === false) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("s", $user_id);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        header("Location: " . $_SERVER['PHP_SELF'] . "?deleted=1");
        exit();
    } catch (Exception $e) {
        $errors[] = "Error: " . $e->getMessage();
    }
}

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
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['success']) || !empty($success_message)): ?>
                    <div class="alert alert-success">
                        Lab In-charge added successfully!
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['deleted'])): ?>
                    <div class="alert alert-success">
                        Lab In-charge deleted successfully!
                    </div>
                <?php endif; ?>

                <!-- Lab In-charge Management Content -->
                <div class="p-card">
                    <div class="p-card-header">
                        <h2 class="p-card-title">Lab In-charge Management</h2>
                        <button class="p-button p-button-primary" id="addNewBtn">
                            <i class="fas fa-plus"></i> Add New
                        </button>
                    </div>

                    <div class="p-search-container">
                        <!-- Search functionality could be added here -->
                    </div>

                    <div class="p-table-container">
                        <table class="p-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Contact</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($incharge_users)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center">No lab in-charge found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($incharge_users as $user): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td><?php echo htmlspecialchars($user['contact_number']); ?></td>
                                            <td>
                                                <div class="p-action-buttons">
                                                    <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this in-charge?');">
                                                        <input type="hidden" name="action" value="delete_incharge">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                        <button type="submit" class="p-action-button p-delete-btn">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Add/Edit Lab In-charge Form -->
                <div class="p-card p-form-card" id="inchargeFormCard" <?php echo (!empty($errors)) ? 'class="active"' : ''; ?>>
                    <div class="p-card-header">
                        <h2 class="p-card-title" id="formTitle">Add Lab In-charge</h2>
                        <button class="p-button p-button-outline" id="closeFormBtn">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>

                    <form id="inchargeForm" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                        <input type="hidden" name="action" value="add_incharge">

                        <div class="p-form-row">
                            <div class="p-form-column">
                                <div class="p-form-group">
                                    <label for="username">Username</label>
                                    <input type="text" id="username" name="username" placeholder="Enter username" required minlength="4">
                                    <div id="usernameValidation" class="p-validation-message"></div>
                                </div>
                            </div>
                            <div class="p-form-column">
                                <div class="p-form-group">
                                    <label for="password">Password</label>
                                    <input type="password" id="password" name="password" placeholder="Enter password" required minlength="8">
                                    <div id="passwordValidation" class="p-validation-message"></div>
                                </div>
                            </div>
                        </div>

                        <div class="p-form-row">
                            <div class="p-form-column">
                                <div class="p-form-group">
                                    <label for="fullName">Full Name</label>
                                    <input type="text" id="fullName" name="fullName" placeholder="Enter full name" required>
                                </div>
                            </div>
                            <div class="p-form-column">
                                <div class="p-form-group">
                                    <label for="email">Email</label>
                                    <input type="email" id="email" name="email" placeholder="Enter email address" required>
                                    <div id="emailValidation" class="p-validation-message"></div>
                                </div>
                            </div>
                        </div>

                        <div class="p-form-row">
                            <div class="p-form-column">
                                <div class="p-form-group">
                                    <label for="contactNumber">Contact Number</label>
                                    <input type="tel" id="contactNumber" name="contactNumber" placeholder="Enter contact number" required>
                                    <div id="contactValidation" class="p-validation-message"></div>
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

                // Contact validation function
                function validateContact() {
                    const contact = document.getElementById('contactNumber').value;
                    const contactValidation = document.getElementById('contactValidation');
                    const contactRegex = /^(\+91[\-\s]?)?[0]?(91)?[789]\d{9}$/;

                    if (!contactRegex.test(contact)) {
                        contactValidation.style.display = 'block';
                        contactValidation.textContent = 'Please enter a valid Indian phone number.';
                        return false;
                    }

                    contactValidation.style.display = 'none';
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

                // Add event listeners for validation
                document.getElementById('password').addEventListener('blur', validatePassword);
                document.getElementById('contactNumber').addEventListener('blur', validateContact);

                // Form submission
                document.getElementById('inchargeForm').addEventListener('submit', function(e) {
                    if (!(validatePassword() && validateContact())) {
                        e.preventDefault();
                    }
                });
            </script>
            <!-- CONTENT END of BELOW HEADER  -->
        </div>
    </div>
</body>

</html>