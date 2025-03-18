<?php
session_start();
if (isset($_SESSION["logged_in"]) !== true) {
    header("Location: login.php");
    exit;
}

include 'db.php';

// Initialize variables
$username = $_SESSION['username'] ?? '';
$message = '';
$error = '';
$successType = '';

// Fetch user details from database
$stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
} else {
    $error = "User not found";
}

// Handle profile update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $fullName = trim($_POST['fullName']);
    $email = trim($_POST['email']);
    $contactNumber = trim($_POST['contactNumber']);
    $org = trim($_POST['org']);

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format";
    }
    // Validate Indian contact number (10 digits, may start with +91)
    elseif (!empty($contactNumber) && !preg_match('/^(\+91[\-\s]?)?[0]?(91)?[6789]\d{9}$/', $contactNumber)) {
        $error = "Invalid Indian contact number format";
    } else {
        // Update user information
        $updateStmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, contact_number = ?, org_name = ? WHERE username = ?");
        $updateStmt->bind_param("sssss", $fullName, $email, $contactNumber, $org, $username);

        if ($updateStmt->execute()) {
            $message = "Profile updated successfully";
            $successType = "profile";

            // Update session variables
            $_SESSION['full_name'] = $fullName;
            $_SESSION['email'] = $email;

        } else {
            $error = "Error updating profile: " . $conn->error;
        }
    }
}

// Handle password update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_password'])) {
    $currentPassword = $_POST['currentPassword'];
    $newPassword = $_POST['newPassword'];
    $confirmPassword = $_POST['confirmPassword'];

    // Validate password
    if (strlen($newPassword) < 8 || !preg_match('/[0-9]/', $newPassword) || !preg_match('/[a-zA-Z]/', $newPassword)) {
        $error = "Password must be at least 8 characters and contain both letters and numbers";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "New passwords do not match";
    } else {
        // Verify current password
        if (password_verify($currentPassword, $user['password'])) {
            // Hash new password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

            // Update password
            $updatePwdStmt = $conn->prepare("UPDATE users SET password = ? WHERE username = ?");
            $updatePwdStmt->bind_param("ss", $hashedPassword, $username);

            if ($updatePwdStmt->execute()) {
                $message = "Password updated successfully";
                $successType = "password";
            } else {
                $error = "Error updating password: " . $conn->error;
            }
        } else {
            $error = "Current password is incorrect";
        }
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
    <link rel="stylesheet" href="../public/css/profile.css" />

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
                    <span>Manage Profile </span>
                </div>
                <div class="user-info" onclick="window.location.href = 'profileManage.php';">
                    <i class="fa-solid fa-circle-user"></i>
                    <span class="font-rale"><?php echo htmlspecialchars(strtoupper($_SESSION['username']) ?? 'User');  ?></span>
                </div>
            </div>
            <!-- CONTENT START of BELOW HEADER -->
            <div class="p-u-profile-container">
                <?php if ($error): ?>
                    <div class="p-u-alert p-u-alert-error">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <div class="p-u-card">
                    <div class="p-u-profile-header">
                        <div class="p-u-profile-title">
                            <h1><?php echo htmlspecialchars($user['full_name'] ?? 'User'); ?></h1>
                            <p><?php echo htmlspecialchars($user['email'] ?? 'email@example.com'); ?></p>
                        </div>
                        <div class="p-u-profile-badge"><?php echo htmlspecialchars($user['user_type'] ?? 'User'); ?></div>
                    </div>
                </div>

                <div class="p-u-card">
                    <div class="p-u-card-header">
                        <h2 class="p-u-card-title">Account Information</h2>
                    </div>

                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                        <div class="p-u-user-info">
                            <div class="p-u-form-group">
                                <label for="username">Username</label>
                                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" class="p-u-disabled-input" disabled>
                                <div class="p-u-info-text">Username cannot be changed</div>
                            </div>
                            <div class="p-u-form-group">
                                <label for="fullName">Full Name</label>
                                <input type="text" id="fullName" name="fullName" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" placeholder="Enter your full name">
                            </div>
                            <div class="p-u-form-group">
                                <label for="org">Organisation</label>
                                <input type="text" id="org" name="org" value="<?php echo htmlspecialchars($user['org_name'] ?? ''); ?>" placeholder="Enter your organisation">
                            </div>
                            <div class="p-u-form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" placeholder="Enter your email">
                            </div>
                            <div class="p-u-form-group">
                                <label for="contactNumber">Contact Number</label>
                                <input type="tel" id="contactNumber" name="contactNumber" value="<?php echo htmlspecialchars($user['contact_number'] ?? ''); ?>" placeholder="Enter your contact number">
                                <div class="p-u-info-text">Format: +91 XXXXXXXXXX or 10 digits</div>
                            </div>
                        </div>
                        <div class="p-u-form-actions">
                            <button type="submit" name="update_profile" class="p-u-btn p-u-btn-primary">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                    <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                    <polyline points="7 3 7 8 15 8"></polyline>
                                </svg>
                                Save Changes
                            </button>
                        </div>
                    </form>
                    <div class="p-u-last-updated">Last updated: <?php echo date('F d, Y, g:i A', strtotime($user['updated_at'] ?? 'now')); ?></div>
                </div>

                <div class="p-u-card">
                    <div class="p-u-card-header">
                        <h2 class="p-u-card-title">Password Management</h2>
                    </div>
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" id="passwordForm">
                        <div class="p-u-password-fields">
                            <div class="p-u-form-group">
                                <label for="currentPassword">Current Password</label>
                                <div class="p-u-password-input-container">
                                    <input type="password" id="currentPassword" name="currentPassword" placeholder="Enter your current password" required>
                                    <button type="button" class="p-u-password-toggle" onclick="togglePasswordVisibility('currentPassword')">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round" class="p-u-password-eye">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <div class="p-u-form-group">
                                <label for="newPassword">New Password</label>
                                <div class="p-u-password-input-container">
                                    <input type="password" id="newPassword" name="newPassword" placeholder="Enter your new password" required>
                                    <button type="button" class="p-u-password-toggle" onclick="togglePasswordVisibility('newPassword')">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round" class="p-u-password-eye">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                    </button>
                                </div>
                                <div class="p-u-password-requirements">Password must be at least 8 characters and contain at least
                                    1 number and 1 character.</div>
                                <div id="password-validation" class="p-u-password-validation"></div>
                            </div>
                            <div class="p-u-form-group">
                                <label for="confirmPassword">Confirm New Password</label>
                                <div class="p-u-password-input-container">
                                    <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Confirm your new password" required>
                                    <button type="button" class="p-u-password-toggle" onclick="togglePasswordVisibility('confirmPassword')">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round" class="p-u-password-eye">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                    </button>
                                </div>
                                <div id="confirm-password-validation" class="p-u-password-validation"></div>
                            </div>
                        </div>
                        <div class="p-u-form-actions">
                            <button type="reset" class="p-u-btn p-u-btn-warning">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M19 12H5"></path>
                                    <path d="M12 19l-7-7 7-7"></path>
                                </svg>
                                Cancel
                            </button>
                            <button type="submit" name="update_password" class="p-u-btn p-u-btn-primary" id="updatePasswordBtn">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                </svg>
                                Update Password
                            </button>
                        </div>
                    </form>
                </div>

                <?php if ($user['user_type'] === 'Admin'): ?>
                    <div class="p-u-card p-u-admin-actions">
                        <div class="p-u-card-header">
                            <h2 class="p-u-card-title">Admin Controls</h2>
                        </div>
                        <div class="p-u-form-actions" style="justify-content: flex-start;">
                            <button type="button" class="p-u-btn p-u-btn-secondary" onclick="window.location.href='inchargeManage.php'">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                </svg>
                                Manage Lab Incharge
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Success Modal for Profile Update -->
            <div class="p-u-modal <?php echo ($successType === 'profile') ? 'show' : ''; ?>" id="profile-modal">
                <div class="p-u-modal-content">
                    <div class="p-u-modal-header">
                        <div class="p-u-modal-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                <polyline points="22 4 12 14.01 9 11.01"></polyline>
                            </svg>
                        </div>
                        <h3 class="p-u-modal-title">Profile Updated</h3>
                    </div>
                    <div class="p-u-modal-body">
                        <p>Your profile information has been successfully updated.</p>
                    </div>
                    <div class="p-u-modal-actions">
                        <button class="p-u-btn p-u-btn-primary" onclick="hideModal('profile-modal')">
                            Got it
                        </button>
                    </div>
                </div>
            </div>

            <!-- Success Modal for Password Update -->
            <div class="p-u-modal <?php echo ($successType === 'password') ? 'show' : ''; ?>" id="password-modal">
                <div class="p-u-modal-content">
                    <div class="p-u-modal-header">
                        <div class="p-u-modal-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                <polyline points="22 4 12 14.01 9 11.01"></polyline>
                            </svg>
                        </div>
                        <h3 class="p-u-modal-title">Password Updated</h3>
                    </div>
                    <div class="p-u-modal-body">
                        <p>Your password has been successfully updated.</p>
                    </div>
                    <div class="p-u-modal-actions">
                        <button class="p-u-btn p-u-btn-primary" onclick="hideModal('password-modal')">
                            Got it
                        </button>
                    </div>
                </div>
            </div>

            <!-- JavaScript for functionality -->
            <script>
                // Modal functions
                function showModal(modalId) {
                    document.getElementById(modalId).classList.add('show');
                }

                function hideModal(modalId) {
                    document.getElementById(modalId).classList.remove('show');
                }

                // Toggle password visibility
                function togglePasswordVisibility(inputId) {
                    const input = document.getElementById(inputId);
                    const eyeIcon = input.nextElementSibling.querySelector('.p-u-password-eye');

                    if (input.type === 'password') {
                        input.type = 'text';
                        eyeIcon.innerHTML = `
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                    <line x1="1" y1="1" x2="23" y2="23"></line>
                `;
                    } else {
                        input.type = 'password';
                        eyeIcon.innerHTML = `
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                `;
                    }
                }

                // Password validation
                document.addEventListener('DOMContentLoaded', function() {
                    const newPasswordInput = document.getElementById('newPassword');
                    const confirmPasswordInput = document.getElementById('confirmPassword');
                    const passwordValidation = document.getElementById('password-validation');
                    const confirmPasswordValidation = document.getElementById('confirm-password-validation');
                    const passwordForm = document.getElementById('passwordForm');

                    function validatePassword() {
                        const password = newPasswordInput.value;
                        const isValid = password.length >= 8 && /\d/.test(password) && /[a-zA-Z]/.test(password);

                        if (!isValid) {
                            passwordValidation.style.display = 'block';
                            passwordValidation.textContent = 'Password must be at least 8 characters and contain both letters and numbers.';
                            return false;
                        }

                        passwordValidation.style.display = 'none';
                        return true;
                    }

                    function validateConfirmPassword() {
                        if (newPasswordInput.value !== confirmPasswordInput.value && confirmPasswordInput.value) {
                            confirmPasswordValidation.style.display = 'block';
                            confirmPasswordValidation.textContent = 'Passwords do not match';
                            return false;
                        } else {
                            confirmPasswordValidation.style.display = 'none';
                            return true;
                        }
                    }

                    newPasswordInput.addEventListener('input', validatePassword);
                    confirmPasswordInput.addEventListener('input', validateConfirmPassword);

                    passwordForm.addEventListener('submit', function(e) {
                        const isPasswordValid = validatePassword();
                        const isConfirmPasswordValid = validateConfirmPassword();

                        if (!isPasswordValid || !isConfirmPasswordValid) {
                            e.preventDefault();
                        }
                    });
                });

                // Add client-side validation for email and phone number
                document.addEventListener('DOMContentLoaded', function() {
                    const emailInput = document.getElementById('email');
                    const contactInput = document.getElementById('contactNumber');

                    emailInput.addEventListener('blur', function() {
                        const email = this.value;
                        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

                        if (email && !emailRegex.test(email)) {
                            alert('Please enter a valid email address');
                        }
                    });

                    contactInput.addEventListener('blur', function() {
                        const contact = this.value;
                        const contactRegex = /^(\+91[\-\s]?)?[0]?(91)?[6789]\d{9}$/;

                        if (contact && !contactRegex.test(contact)) {
                            alert('Please enter a valid Indian contact number');
                        }
                    });
                });
            </script>
            <!-- CONTENT END of BELOW HEADER  -->
        </div>
    </div>
</body>

</html>