<?php
session_start();
include 'db.php';

// Function to sanitize input data
function sanitizeInput($data)
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Initialize variables to store form data and errors
$errors = [];
$formData = [
    'submitted_by' => '',
    'stud_enrollment' => '',
    'stud_email' => '',
    'class' => '',
    'lab_selection' => '',
    'device_category' => '',
    'device_id' => '',
    'grievance_desc' => ''
];

// Function to validate device ID
function validateDeviceId($deviceId, $conn)
{
    $sql = "SELECT * FROM devices WHERE device_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $deviceId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

// Function to validate lab ID
function validateLabId($labId, $conn)
{
    $sql = "SELECT * FROM labs WHERE lab_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $labId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

// Check if the user is checking a grievance status
$grievanceData = null;
if (isset($_GET['check_status']) && isset($_GET['grievance_id'])) {
    $grievanceId = sanitizeInput($_GET['grievance_id']);

    // Fetch grievance data
    $sql = "SELECT g.*, l.lab_name FROM grievances g 
            LEFT JOIN labs l ON g.lab_id = l.lab_id 
            WHERE g.grievance_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $grievanceId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $grievanceData = $result->fetch_assoc();
    }
}

// Process form submission
$insertId = null;
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate name
    if (empty($_POST['submitted_by'])) {
        $errors['submitted_by'] = "Full name is required";
    } else {
        $formData['submitted_by'] = sanitizeInput($_POST['submitted_by']);
        if (!preg_match("/^[a-zA-Z ]*$/", $formData['submitted_by'])) {
            $errors['submitted_by'] = "Only letters and spaces allowed";
        }
    }

    // Validate enrollment number
    if (empty($_POST['stud_enrollment'])) {
        $errors['stud_enrollment'] = "Enrollment number is required";
    } else {
        $formData['stud_enrollment'] = sanitizeInput($_POST['stud_enrollment']);
        if (!preg_match("/^\d{10}$/", $formData['stud_enrollment'])) {
            $errors['stud_enrollment'] = "Enrollment number must be 10 digits";
        }
    }

    // Validate email
    if (empty($_POST['stud_email'])) {
        $errors['stud_email'] = "Email is required";
    } else {
        $formData['stud_email'] = sanitizeInput($_POST['stud_email']);
        if (!filter_var($formData['stud_email'], FILTER_VALIDATE_EMAIL)) {
            $errors['stud_email'] = "Invalid email format";
        }
    }

    // Validate class
    if (empty($_POST['class'])) {
        $errors['class'] = "Class is required";
    } else {
        $formData['class'] = sanitizeInput($_POST['class']);
        if (!preg_match("/^[A-Z]{2}-\d{1,2}[A-Z]$/", $formData['class'])) {
            $errors['class'] = "Class format should be like CO-6I";
        }
    }

    // Validate lab selection
    if (empty($_POST['lab_selection'])) {
        $errors['lab_selection'] = "Please select a lab";
    } else {
        $formData['lab_selection'] = sanitizeInput($_POST['lab_selection']);
        if (!validateLabId($formData['lab_selection'], $conn)) {
            $errors['lab_selection'] = "Invalid lab selected";
        }
    }

    // Validate device category
    if (empty($_POST['device_category'])) {
        $errors['device_category'] = "Please select a device category";
    } else {
        $formData['device_category'] = sanitizeInput($_POST['device_category']);
        $validCategories = ['PC', 'Printer', 'Mouse', 'Keyboard', 'Monitor', 'CPU'];
        if (!in_array($formData['device_category'], $validCategories)) {
            $errors['device_category'] = "Invalid device category";
        }
    }

    // Validate device ID
    if (empty($_POST['device_id'])) {
        $errors['device_id'] = "Device ID is required";
    } else {
        $formData['device_id'] = sanitizeInput($_POST['device_id']);
        if (!validateDeviceId($formData['device_id'], $conn)) {
            $errors['device_id'] = "Invalid device ID";
        }
    }

    // Validate grievance description
    if (empty($_POST['grievance_desc'])) {
        $errors['grievance_desc'] = "Grievance description is required";
    } else {
        $formData['grievance_desc'] = sanitizeInput($_POST['grievance_desc']);
        if (strlen($formData['grievance_desc']) < 20) {
            $errors['grievance_desc'] = "Description must be at least 20 characters";
        }
    }

    // Validate CAPTCHA
    if (empty($_POST['captcha_input'])) {
        $errors['captcha_input'] = "Please enter the CAPTCHA code";
    } else {
        $userCaptcha = sanitizeInput($_POST['captcha_input']);
        if (!isset($_SESSION['captcha']) || $userCaptcha != $_SESSION['captcha']) {
            $errors['captcha_input'] = "CAPTCHA code does not match";
        }
    }

    // If no errors, insert data into database
    if (empty($errors)) {
        $sql = "INSERT INTO grievances (submitted_by, stud_enrollment, class, lab_id, device_id, device_category, stud_email, grievance_desc)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "ssssssss",
            $formData['submitted_by'],
            $formData['stud_enrollment'],
            $formData['class'],
            $formData['lab_selection'],
            $formData['device_id'],
            $formData['device_category'],
            $formData['stud_email'],
            $formData['grievance_desc']
        );

        if ($stmt->execute()) {
            // Get the inserted ID
            $insertId = $conn->insert_id;
            // Set success message
            $successMessage = true;
        } else {
            $errors['db'] = "Error: " . $stmt->error;
        }

        $stmt->close();
    }
}

// Generate CAPTCHA
function generateCaptcha()
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $captcha = '';
    for ($i = 0; $i < 6; $i++) {
        $captcha .= $characters[rand(0, strlen($characters) - 1)];
    }
    $_SESSION['captcha'] = $captcha;
    return $captcha;
}

// Start the session to store CAPTCHA
$captcha = generateCaptcha();

// Fetch labs for dropdown
$labsQuery = "SELECT lab_id, lab_name FROM labs WHERE status = 'Active' ORDER BY lab_name";
$labsResult = $conn->query($labsQuery);
$labs = [];
if ($labsResult->num_rows > 0) {
    while ($row = $labsResult->fetch_assoc()) {
        $labs[] = $row;
    }
}

// Close the database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Grievance System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Inter", Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f0f2f5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        .container {
            background-color: white;
            width: 66dvw;
            max-width: 100%;
            border-radius: 0.75rem;
            box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.08);
            padding: 2.5rem;
            position: relative;
        }

        .header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .header h1 {
            font-size: 1.75rem;
            color: #2185d0;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .header p {
            color: #666;
            font-size: 0.95rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem;
        }

        .form-full-width {
            grid-column: span 2;
        }

        .form-group {
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #555;
            font-size: 0.9rem;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #e1e5eb;
            border-radius: 0.375rem;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            background-color: #fafbfc;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #2185d0;
            outline: none;
            background-color: #fff;
            box-shadow: 0 0 0 3px rgba(33, 133, 208, 0.1);
        }

        .submit-btn {
            background-color: #2185d0;
            color: white;
            border: none;
            border-radius: 0.375rem;
            font-size: 1rem;
            font-weight: 600;
            padding: 0.875rem;
            width: 100%;
            cursor: pointer;
            transition: background-color 0.2s;
            margin-top: 1rem;
        }

        .submit-btn:hover {
            background-color: #1a69a4;
        }

        .field-validation-error {
            color: #e74c3c;
            font-size: 0.75rem;
            display: block;
            margin-top: 0.375rem;
        }

        .error-message {
            color: #e74c3c;
            text-align: center;
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }

        .captcha-container {
            display: flex;
            align-items: center;
            margin-bottom: 0.875rem;
        }

        .captcha-box {
            background-color: #f5f5f5;
            padding: 0.625rem;
            border-radius: 0.375rem;
            font-size: 1.25rem;
            font-weight: bold;
            letter-spacing: 0.25rem;
            color: #333;
            text-align: center;
            width: 10rem;
            user-select: none;
            position: relative;
            overflow: hidden;
        }

        .captcha-box::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg,
                    transparent 49%,
                    #e0e0e0 49%,
                    #e0e0e0 51%,
                    transparent 51%);
            background-size: 4px 4px;
            opacity: 0.5;
        }

        .refresh-captcha {
            margin-left: 0.625rem;
            background: none;
            border: none;
            color: #2185d0;
            cursor: pointer;
            font-size: 1.25rem;
            width: 2.5rem;
            height: 2.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background-color 0.2s;
        }

        .refresh-captcha:hover {
            background-color: rgba(33, 133, 208, 0.1);
        }

        .mandatory-note {
            color: rgb(231, 76, 60);
            font-style: italic;
            margin-bottom: 15px;
        }

        /* Enhanced styles for success message popup */
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.6);
            z-index: 999;
            backdrop-filter: blur(3px);
        }

        .success-popup {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: white;
            width: 90%;
            max-width: 420px;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 0.5rem 2rem rgba(0, 0, 0, 0.2);
            z-index: 1000;
            text-align: center;
            animation: popup-fade 0.3s ease-out;
        }

        @keyframes popup-fade {
            from {
                opacity: 0;
                transform: translate(-50%, -60%);
            }

            to {
                opacity: 1;
                transform: translate(-50%, -50%);
            }
        }

        .success-popup h2 {
            color: #2185d0;
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }

        .success-popup .grievance-id {
            background: linear-gradient(135deg, #4c88d0, #2185d0);
            padding: 1.25rem 1rem;
            border-radius: 0.75rem;
            font-size: 1.75rem;
            font-weight: bold;
            color: white;
            margin: 1.5rem 0;
            display: block;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
            box-shadow: 0 4px 10px rgba(33, 133, 208, 0.3);
        }

        .success-popup p {
            color: #666;
            margin-bottom: 1.5rem;
            line-height: 1.5;
        }

        .success-popup .btn-group {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }

        .success-popup .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            flex: 1;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .success-popup .btn-primary {
            background-color: #2185d0;
            color: white;
            border: none;
            box-shadow: 0 2px 5px rgba(33, 133, 208, 0.3);
        }

        .success-popup .btn-primary:hover {
            background-color: #1a69a4;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(33, 133, 208, 0.4);
        }

        .success-popup .btn-secondary {
            background-color: #f2f3f5;
            color: #333;
            border: 1px solid #ddd;
        }

        .success-popup .btn-secondary:hover {
            background-color: #e4e6eb;
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        /* Enhanced status checker */
        .status-checker {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e1e5eb;
            text-align: center;
        }

        .status-checker h3 {
            color: #2185d0;
            margin-bottom: 1rem;
            font-size: 1.25rem;
        }

        .status-checker .form-inline {
            display: flex;
            justify-content: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .status-checker input {
            padding: 0.75rem 1rem;
            border: 1px solid #e1e5eb;
            border-radius: 0.375rem;
            font-size: 0.95rem;
            width: 180px;
            background-color: #fafbfc;
        }

        .status-checker input:focus {
            border-color: #2185d0;
            outline: none;
            background-color: #fff;
            box-shadow: 0 0 0 3px rgba(33, 133, 208, 0.1);
        }

        .status-details {
            background-color: #f8f9fa;
            border-radius: 0.75rem;
            padding: 1.75rem;
            margin-top: 1.5rem;
            text-align: left;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e9f0;
        }

        /* Add this to your existing CSS */
        .check-status-btn {
            background-color: #2185d0;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .check-status-btn:hover {
            background-color: #1a69a4;
        }

        .status-details h4 {
            color: #2185d0;
            margin-bottom: 1.25rem;
            font-size: 1.25rem;
            border-bottom: 1px solid #e1e5eb;
            padding-bottom: 0.75rem;
        }

        .status-details .detail-item {
            margin-bottom: 1rem;
            display: flex;
            align-items: baseline;
        }

        .status-details .detail-label {
            font-weight: 600;
            width: 140px;
            color: #555;
            font-size: 0.9rem;
        }

        .status-details .detail-value {
            flex: 1;
            color: #333;
            font-size: 0.95rem;
            line-height: 1.4;
        }

        .status-tag {
            display: inline-block;
            padding: 0.3rem 0.75rem;
            border-radius: 0.375rem;
            font-size: 0.85rem;
            font-weight: 600;
            letter-spacing: 0.01rem;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-inprogress {
            background-color: #cce5ff;
            color: #004085;
        }

        .status-resolved {
            background-color: #d4edda;
            color: #155724;
        }

        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }

        .no-results {
            text-align: center;
            color: #e74c3c;
            margin-top: 1.5rem;
            padding: 1.5rem;
            background-color: #fdf3f2;
            border-radius: 0.5rem;
            border: 1px solid #f8d7da;
        }

        .admin-note {
            background-color: #f0f7ff;
            border-left: 4px solid #2185d0;
            padding: 1rem;
            border-radius: 0 0.375rem 0.375rem 0;
            margin-top: 0.5rem;
        }

        @media (max-width: 48rem) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-full-width {
                grid-column: span 1;
            }

            .container {
                padding: 1.5rem;
                width: 90%;
            }

            .success-popup {
                width: 90%;
                padding: 1.5rem;
            }

            .status-details .detail-item {
                flex-direction: column;
            }

            .status-details .detail-label {
                width: 100%;
                margin-bottom: 0.25rem;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>Student Grievance Form</h1>
            <p>Submit your device or lab-related issues here</p>
        </div>

        <?php if (isset($successMessage) && $successMessage && $insertId): ?>
            <div class="overlay"></div>
            <div class="success-popup">
                <h2>Successfully Submitted!</h2>
                <p>Your grievance has been recorded. Please save your grievance ID for future reference:</p>
                <div class="grievance-id"><?php echo $insertId; ?></div>
                <p>You can use this ID to check the status of your grievance anytime.</p>
                <div class="btn-group">
                    <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn btn-secondary">New Submission</a>
                    <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']) . '?check_status=1&grievance_id=' . $insertId; ?>" class="btn btn-primary">Check Status</a>
                </div>
            </div>
        <?php else: ?>
            <?php if (isset($grievanceData)): ?>
                <div class="status-details">
                    <h4>Grievance Status Details</h4>
                    <div class="detail-item">
                        <div class="detail-label">Grievance ID:</div>
                        <div class="detail-value"><strong><?php echo $grievanceData['grievance_id']; ?></strong></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Status:</div>
                        <div class="detail-value">
                            <?php
                            $statusClass = '';
                            switch ($grievanceData['status']) {
                                case 'Pending':
                                    $statusClass = 'status-pending';
                                    break;
                                case 'In Progress':
                                    $statusClass = 'status-inprogress';
                                    break;
                                case 'Resolved':
                                    $statusClass = 'status-resolved';
                                    break;
                                case 'Rejected':
                                    $statusClass = 'status-rejected';
                                    break;
                                default:
                                    $statusClass = 'status-pending';
                            }
                            ?>
                            <span class="status-tag <?php echo $statusClass; ?>"><?php echo $grievanceData['status'] ?? 'Pending'; ?></span>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Submitted By:</div>
                        <div class="detail-value"><?php echo $grievanceData['submitted_by']; ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Submission Date:</div>
                        <div class="detail-value"><?php echo date('F j, Y', strtotime($grievanceData['submission_date'])); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Lab:</div>
                        <div class="detail-value"><?php echo $grievanceData['lab_name']; ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Device:</div>
                        <div class="detail-value"><?php echo $grievanceData['device_category'] . ' - ' . $grievanceData['device_id']; ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Issue:</div>
                        <div class="detail-value"><?php echo $grievanceData['grievance_desc']; ?></div>
                    </div>
                    <?php if (!empty($grievanceData['admin_note'])): ?>
                        <div class="detail-item">
                            <div class="detail-label">Admin Note:</div>
                            <div class="detail-value">
                                <div class="admin-note"><?php echo $grievanceData['admin_note']; ?></div>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($grievanceData['resolution_date']) && $grievanceData['status'] == 'Resolved'): ?>
                        <div class="detail-item">
                            <div class="detail-label">Resolution Date:</div>
                            <div class="detail-value"><?php echo date('F j, Y', strtotime($grievanceData['resolution_date'])); ?></div>
                        </div>
                    <?php endif; ?>
                    <div class="btn-group" style="margin-top: 1.5rem; text-align: center;">
                        <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn btn-secondary">Back to Form</a>
                    </div>
                </div>
            <?php elseif (isset($_GET['check_status']) && !$grievanceData): ?>
                <div class="no-results">
                    <p>No grievance found with the provided ID. Please check the ID and try again.</p>
                    <div class="btn-group" style="margin-top: 1rem; text-align: center;">
                        <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn btn-secondary">Back to Form</a>
                    </div>
                </div>
            <?php else: ?>
                <form id="grievanceForm" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div class="error-message" id="formErrorMessage">
                        <?php if (isset($errors['db'])) echo $errors['db']; ?>
                    </div>

                    <div class="mandatory-note">Note: All fields are mandatory</div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="submitted_by">Full Name *</label>
                            <input type="text" id="submitted_by" name="submitted_by" placeholder="Enter your full name" required
                                value="<?php echo $formData['submitted_by']; ?>">
                            <span class="field-validation-error" id="submitted_by_error">
                                <?php if (isset($errors['submitted_by'])) echo $errors['submitted_by']; ?>
                            </span>
                        </div>

                        <div class="form-group">
                            <label for="stud_enrollment">Enrollment Number *</label>
                            <input type="text" id="stud_enrollment" name="stud_enrollment" placeholder="e.g. 2211520001" required
                                value="<?php echo $formData['stud_enrollment']; ?>">
                            <span class="field-validation-error" id="stud_enrollment_error">
                                <?php if (isset($errors['stud_enrollment'])) echo $errors['stud_enrollment']; ?>
                            </span>
                        </div>

                        <div class="form-group">
                            <label for="stud_email">Email Address *</label>
                            <input type="email" id="stud_email" name="stud_email" placeholder="Your email address" required
                                value="<?php echo $formData['stud_email']; ?>">
                            <span class="field-validation-error" id="stud_email_error">
                                <?php if (isset($errors['stud_email'])) echo $errors['stud_email']; ?>
                            </span>
                        </div>

                        <div class="form-group">
                            <label for="class">Class (COURSE-SEM-SCHEME) *</label>
                            <input type="text" id="class" name="class" placeholder="Your class e.g. CO-6I" required
                                value="<?php echo $formData['class']; ?>">
                            <span class="field-validation-error" id="class_error">
                                <?php if (isset($errors['class'])) echo $errors['class']; ?>
                            </span>
                        </div>

                        <div class="form-group">
                            <label for="lab_selection">Select Lab *</label>
                            <select id="lab_selection" name="lab_selection" required>
                                <option value="">Select lab</option>
                                <?php foreach ($labs as $lab): ?>
                                    <option value="<?php echo $lab['lab_id']; ?>" <?php echo ($formData['lab_selection'] == $lab['lab_id']) ? 'selected' : ''; ?>>
                                        <?php echo $lab['lab_name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="field-validation-error" id="lab_selection_error">
                                <?php if (isset($errors['lab_selection'])) echo $errors['lab_selection']; ?>
                            </span>
                        </div>

                        <div class="form-group">
                            <label for="device_category">Device Category *</label>
                            <select id="device_category" name="device_category" required>
                                <option value="">Select device category</option>
                                <option value="PC" <?php echo ($formData['device_category'] == 'PC') ? 'selected' : ''; ?>>PC</option>
                                <option value="Printer" <?php echo ($formData['device_category'] == 'Printer') ? 'selected' : ''; ?>>Printer</option>
                                <option value="Mouse" <?php echo ($formData['device_category'] == 'Mouse') ? 'selected' : ''; ?>>Mouse</option>
                                <option value="Keyboard" <?php echo ($formData['device_category'] == 'Keyboard') ? 'selected' : ''; ?>>Keyboard</option>
                                <option value="Monitor" <?php echo ($formData['device_category'] == 'Monitor') ? 'selected' : ''; ?>>Monitor</option>
                                <option value="CPU" <?php echo ($formData['device_category'] == 'CPU') ? 'selected' : ''; ?>>CPU</option>
                            </select>
                            <span class="field-validation-error" id="device_category_error">
                                <?php if (isset($errors['device_category'])) echo $errors['device_category']; ?>
                            </span>
                        </div>

                        <div class="form-group">
                            <label for="device_id">Device ID *</label>
                            <input type="text" id="device_id" name="device_id" placeholder="Enter device ID e.g. PC-2025-101" required
                                value="<?php echo $formData['device_id']; ?>">
                            <span class="field-validation-error" id="device_id_error">
                                <?php if (isset($errors['device_id'])) echo $errors['device_id']; ?>
                            </span>
                        </div>

                        <div class="form-group form-full-width">
                            <label for="grievance_desc">Grievance Description *</label>
                            <textarea id="grievance_desc" name="grievance_desc" rows="4" placeholder="Describe your issue in detail..." required><?php echo $formData['grievance_desc']; ?></textarea>
                            <span class="field-validation-error" id="grievance_desc_error">
                                <?php if (isset($errors['grievance_desc'])) echo $errors['grievance_desc']; ?>
                            </span>
                        </div>

                        <div class="form-group form-full-width">
                            <div class="captcha-container">
                                <div class="captcha-box" id="captchaBox"><?php echo $captcha; ?></div>
                                <button type="button" class="refresh-captcha" id="refreshCaptcha" style="margin-right: 1rem;">⟳</button>
                                <input type="text" id="captcha_input" name="captcha_input" placeholder="Enter the CAPTCHA code" required>
                                <span class="field-validation-error" id="captcha_input_error">
                                    <?php if (isset($errors['captcha_input'])) echo $errors['captcha_input']; ?>
                                </span>
                            </div>
                        </div>

                    </div>
                    <button type="submit" class="submit-btn">Submit Grievance</button>
                </form>

                <!-- Status checker section -->
                <div class="status-checker">
                    <h3>Check Your Grievance Status</h3>
                    <form class="form-inline" method="GET" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                        <input type="hidden" name="check_status" value="1">
                        <input type="text" name="grievance_id" placeholder="Enter Grievance ID" required>
                        <button type="submit" class="check-status-btn">Check Status</button>
                    </form>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- PHP file for CAPTCHA refresh -->
    <script>
        // JavaScript for client-side validation and CAPTCHA refresh
        document.addEventListener('DOMContentLoaded', function() {
            // Form validation
            const form = document.getElementById('grievanceForm');
            if (form) {
                form.addEventListener('submit', function(event) {
                    let isValid = true;

                    // Validate name
                    const name = document.getElementById('submitted_by');
                    const nameError = document.getElementById('submitted_by_error');
                    if (!name.value.trim()) {
                        nameError.textContent = 'Full name is required';
                        isValid = false;
                    } else if (!/^[a-zA-Z ]*$/.test(name.value)) {
                        nameError.textContent = 'Only letters and spaces allowed';
                        isValid = false;
                    } else {
                        nameError.textContent = '';
                    }

                    // Validate enrollment
                    const enrollment = document.getElementById('stud_enrollment');
                    const enrollmentError = document.getElementById('stud_enrollment_error');
                    if (!enrollment.value.trim()) {
                        enrollmentError.textContent = 'Enrollment number is required';
                        isValid = false;
                    } else if (!/^\d{10}$/.test(enrollment.value)) {
                        enrollmentError.textContent = 'Enrollment number must be 10 digits';
                        isValid = false;
                    } else {
                        enrollmentError.textContent = '';
                    }

                    // Validate email
                    const email = document.getElementById('stud_email');
                    const emailError = document.getElementById('stud_email_error');
                    if (!email.value.trim()) {
                        emailError.textContent = 'Email is required';
                        isValid = false;
                    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
                        emailError.textContent = 'Invalid email format';
                        isValid = false;
                    } else {
                        emailError.textContent = '';
                    }

                    // Validate class
                    const classField = document.getElementById('class');
                    const classError = document.getElementById('class_error');
                    if (!classField.value.trim()) {
                        classError.textContent = 'Class is required';
                        isValid = false;
                    } else if (!/^[A-Z]{2}-\d{1,2}[A-Z]$/.test(classField.value)) {
                        classError.textContent = 'Class format should be like CO-6I';
                        isValid = false;
                    } else {
                        classError.textContent = '';
                    }

                    // Validate lab selection
                    const lab = document.getElementById('lab_selection');
                    const labError = document.getElementById('lab_selection_error');
                    if (!lab.value) {
                        labError.textContent = 'Please select a lab';
                        isValid = false;
                    } else {
                        labError.textContent = '';
                    }

                    // Validate device category
                    const deviceCategory = document.getElementById('device_category');
                    const deviceCategoryError = document.getElementById('device_category_error');
                    if (!deviceCategory.value) {
                        deviceCategoryError.textContent = 'Please select a device category';
                        isValid = false;
                    } else {
                        deviceCategoryError.textContent = '';
                    }

                    // Validate device ID
                    const deviceId = document.getElementById('device_id');
                    const deviceIdError = document.getElementById('device_id_error');
                    if (!deviceId.value.trim()) {
                        deviceIdError.textContent = 'Device ID is required';
                        isValid = false;
                    } else {
                        deviceIdError.textContent = '';
                    }

                    // Validate description
                    const description = document.getElementById('grievance_desc');
                    const descriptionError = document.getElementById('grievance_desc_error');
                    if (!description.value.trim()) {
                        descriptionError.textContent = 'Grievance description is required';
                        isValid = false;
                    } else if (description.value.length < 20) {
                        descriptionError.textContent = 'Description must be at least 20 characters';
                        isValid = false;
                    } else {
                        descriptionError.textContent = '';
                    }

                    // Validate CAPTCHA
                    const captcha = document.getElementById('captcha_input');
                    const captchaError = document.getElementById('captcha_input_error');
                    if (!captcha.value.trim()) {
                        captchaError.textContent = 'Please enter the CAPTCHA code';
                        isValid = false;
                    } else {
                        captchaError.textContent = '';
                    }

                    if (!isValid) {
                        event.preventDefault();
                    }
                });
            }

            // CAPTCHA refresh
            const refreshButton = document.getElementById('refreshCaptcha');
            if (refreshButton) {
                refreshButton.addEventListener('click', function() {
                    fetch('refresh_captcha.php')
                        .then(response => response.text())
                        .then(data => {
                            document.getElementById('captchaBox').textContent = data;
                        })
                        .catch(error => console.error('Error:', error));
                });
            }

            // Dynamic device ID population based on lab and device category
            const labSelect = document.getElementById('lab_selection');
            const deviceCategorySelect = document.getElementById('device_category');
            const deviceIdInput = document.getElementById('device_id');

            function updateDeviceOptions() {
                if (labSelect.value && deviceCategorySelect.value) {
                    fetch(`get_devices.php?lab_id=${labSelect.value}&device_type=${deviceCategorySelect.value}`)
                        .then(response => response.json())
                        .then(devices => {
                            // Create a datalist for device IDs
                            let datalist = document.getElementById('device_id_list');
                            if (!datalist) {
                                datalist = document.createElement('datalist');
                                datalist.id = 'device_id_list';
                                document.body.appendChild(datalist);
                                deviceIdInput.setAttribute('list', 'device_id_list');
                            }

                            // Clear existing options
                            datalist.innerHTML = '';

                            // Add new options
                            devices.forEach(device => {
                                const option = document.createElement('option');
                                option.value = device.device_id;
                                datalist.appendChild(option);
                            });
                        })
                        .catch(error => console.error('Error:', error));
                }
            }

            if (labSelect && deviceCategorySelect) {
                labSelect.addEventListener('change', updateDeviceOptions);
                deviceCategorySelect.addEventListener('change', updateDeviceOptions);
            }
        });
    </script>
</body>

</html>