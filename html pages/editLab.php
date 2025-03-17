<?php
session_start();
if (!isset($_SESSION["logged_in"])) {
    header("Location: login.php");
    exit();
}

include 'db.php';
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$lab_id = $_GET['id'] ?? '';
if (empty($lab_id)) {
    header('Location: labs.php');
    exit();
}

$error_message = '';
$success_message = '';

// Fetch lab details
$query = "SELECT * FROM labs WHERE lab_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('s', $lab_id); // lab_id is a string
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: labs.php');
    exit();
}
$lab = $result->fetch_assoc();

// Define valid departments (since there's no departments table)
$departments = [
    ['department_id' => 'CO', 'department_name' => 'Computer Science'],
    ['department_id' => 'AI', 'department_name' => 'Artificial Intelligence'],
    ['department_id' => 'IT', 'department_name' => 'Information Technology'],
    ['department_id' => 'EE', 'department_name' => 'Electronics Engineering']
];

// Fetch in-charges from users table (only Incharge or Admin user_types)
$incharge_query = "SELECT username FROM users WHERE user_type IN ('Admin', 'Incharge')";
$incharge_result = $conn->query($incharge_query);
$incharges = [];
while ($row = $incharge_result->fetch_assoc()) {
    $incharges[] = ['username' => $row['username']];
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lab_name = trim($_POST['lab_name']);
    $department = trim($_POST['department']); // Will be CO, AI, IT, or EE
    $lab_incharge = trim($_POST['lab_incharge']); // Will be a username from users
    $establishment_date = trim($_POST['establishment_date']);
    $room_capacity = filter_var($_POST['room_capacity'], FILTER_VALIDATE_INT);
    $building = trim($_POST['building']);
    $room_number = trim($_POST['room_number']);

    // Validation
    if (
        empty($lab_name) || empty($department) || empty($lab_incharge) ||
        empty($establishment_date) || $room_capacity === false || empty($building) ||
        empty($room_number)
    ) {
        $error_message = "All fields are required and must be valid!";
    } else {
        // Verify department is valid
        $valid_depts = array_column($departments, 'department_id');
        if (!in_array($department, $valid_depts)) {
            $error_message = "Invalid department selected!";
        }
        // Verify lab_incharge exists in users
        $valid_incharges = array_column($incharges, 'username');
        if (!in_array($lab_incharge, $valid_incharges)) {
            $error_message = "Invalid lab in-charge selected!";
        }

        if (empty($error_message)) {
            $update_query = "UPDATE labs SET 
                lab_name = ?, 
                department = ?, 
                lab_incharge = ?, 
                establishment_date = ?, 
                room_capacity = ?, 
                building = ?, 
                room_number = ?,
                updated_at = NOW() 
                WHERE lab_id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param(
                'ssssisss', // s:string, i:integer
                $lab_name,
                $department,    // CO, AI, IT, EE
                $lab_incharge,  // username from users
                $establishment_date,
                $room_capacity, // integer
                $building,
                $room_number,
                $lab_id
            );

            if ($update_stmt->execute()) {
                $success_message = "Lab updated successfully!";
                $stmt->execute();
                $result = $stmt->get_result();
                $lab = $result->fetch_assoc();
            } else {
                $error_message = "Error updating lab: " . $update_stmt->error;
            }
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
    <title>Edit Lab - LabTrack</title>
    <link rel="stylesheet" href="../public/css/style.css" />
    <script src="https://kit.fontawesome.com/0319a73572.js" crossorigin="anonymous"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Raleway:ital,wght@0,100..900;1,100..900&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap"
        rel="stylesheet" />
    <style>
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error-input {
            border: 1px solid #dc3545 !important;
            background-color: #fff8f8 !important;
        }
    </style>
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
                    <span>Edit Lab</span>
                </div>
                <div class="user-info">
                    <i class="fa-solid fa-circle-user"></i><span class="font-rale"> Administrator</span>
                </div>
            </div>

            <!-- CONTENT START of BELOW HEADER -->
            <div class="lab-management-container">
                <div class="form-wrapper">
                    <div class="form-section" id="lab-details">
                        <div class="section-header">
                            <h2 class="section-title">Edit Lab Details</h2>
                            <a href="labs.php" class="float-right none" style="color: #3498db; cursor: pointer;">
                                <i class="fa-solid fa-arrow-left"></i> Back to Labs
                            </a>
                        </div>

                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-danger"><?php echo $error_message; ?></div>
                        <?php endif; ?>
                        <?php if (!empty($success_message)): ?>
                            <div class="alert alert-success"><?php echo $success_message; ?></div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="grid-2col">
                                <div class="input-group">
                                    <label class="input-label">Lab Name <span>*</span></label>
                                    <input type="text" name="lab_name" class="form-input" value="<?php echo htmlspecialchars($lab['lab_name']); ?>" required>
                                </div>

                                <div class="input-group">
                                    <label class="input-label">Lab Code <span>*</span></label>
                                    <input type="text" class="no-hover form-input" value="<?php echo htmlspecialchars($lab['lab_id']); ?>" disabled>
                                </div>

                                <div class="input-group">
                                    <label class="input-label">Department <span>*</span></label>
                                    <select name="department" class="form-input" required>
                                        <option value="">Select Department</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo htmlspecialchars($dept['department_id']); ?>"
                                                <?php if ($dept['department_id'] === $lab['department']) echo 'selected'; ?>>
                                                <?php echo htmlspecialchars($dept['department_id'] . ' - ' . $dept['department_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="input-group">
                                    <label class="input-label">Lab In-Charge <span>*</span></label>
                                    <select name="lab_incharge" class="form-input" required>
                                        <option value="">Select Lab In-Charge</option>
                                        <?php foreach ($incharges as $incharge): ?>
                                            <option value="<?php echo htmlspecialchars($incharge['username']); ?>"
                                                <?php if ($incharge['username'] === $lab['lab_incharge']) echo 'selected'; ?>>
                                                <?php echo htmlspecialchars($incharge['username']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="input-group">
                                    <label class="input-label">Establishment Date <span>*</span></label>
                                    <input type="date" name="establishment_date" class="form-input" value="<?php echo htmlspecialchars($lab['establishment_date']); ?>" required>
                                </div>

                                <div class="input-group">
                                    <label class="input-label">Room Capacity <span>*</span></label>
                                    <input type="number" name="room_capacity" class="form-input" value="<?php echo htmlspecialchars($lab['room_capacity']); ?>" required>
                                </div>

                                <div class="input-group">
                                    <label class="input-label">Building <span>*</span></label>
                                    <input type="text" name="building" class="form-input" value="<?php echo htmlspecialchars($lab['building']); ?>" required>
                                </div>

                                <div class="input-group">
                                    <label class="input-label">Room <span>*</span></label>
                                    <input type="text" name="room_number" class="form-input" value="<?php echo htmlspecialchars($lab['room_number']); ?>" required>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary" id="saveLab">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-save">
                                        <path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z" />
                                        <path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7" />
                                        <path d="M7 3v4a1 1 0 0 0 1 1h7" />
                                    </svg>
                                    Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <!-- CONTENT END of BELOW HEADER  -->
        </div>
        <!-- CONTENT END  -->
    </div>

    <script>
        // Client-side validation
        document.getElementById('saveLab').addEventListener('click', function(event) {
            const requiredFields = document.querySelectorAll('input[required], select[required]');
            let hasEmptyFields = false;

            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('error-input');
                    hasEmptyFields = true;
                } else {
                    field.classList.remove('error-input');
                }
            });

            if (hasEmptyFields) {
                event.preventDefault();
                alert('Please fill all required fields!');
            }
        });
    </script>
</body>

</html>