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

// Process form submission
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
            // Redirect to success page or show success message
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
    <link rel="stylesheet" href="../public/css/grievance.css" />
    <title>Student Grievance Submission Form</title>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>Student Grievance Form</h1>
            <p>Submit your device or lab-related issues here</p>
        </div>

        <?php if (isset($successMessage) && $successMessage): ?>
            <div class="success-message" id="successMessage" style="display: block;">
                <h2>Grievance Submitted Successfully!</h2>
                <p>Your grievance has been recorded. We will address your concern as soon as possible.</p>
                <p><a href="view_grievance.php?id=<?php echo $conn->insert_id; ?>">View your grievance</a></p>
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
                                <option value="<?php echo $lab['lab_id']; ?>"
                                    <?php if ($formData['lab_selection'] == $lab['lab_id']) echo 'selected'; ?>>
                                    <?php echo $lab['lab_name'] . ' : ' . $lab['lab_id']; ?>
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
                            <option value="">Select category</option>
                            <option value="PC" <?php if ($formData['device_category'] == 'PC') echo 'selected'; ?>>PC</option>
                            <option value="Keyboard" <?php if ($formData['device_category'] == 'Keyboard') echo 'selected'; ?>>Keyboard</option>
                            <option value="Mouse" <?php if ($formData['device_category'] == 'Mouse') echo 'selected'; ?>>Mouse</option>
                            <option value="Printer" <?php if ($formData['device_category'] == 'Printer') echo 'selected'; ?>>Printer</option>
                            <option value="Monitor" <?php if ($formData['device_category'] == 'Monitor') echo 'selected'; ?>>Monitor</option>
                            <option value="CPU" <?php if ($formData['device_category'] == 'CPU') echo 'selected'; ?>>CPU</option>
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
                        </div>
                        </span>
                    </div>
                </div>
                <button type="submit" class="submit-btn">Submit Grievance</button>
            </form>
    </div>

    </div>

<?php endif; ?>
</div>

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