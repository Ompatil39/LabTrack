<?php
include 'db.php'; // Database connection

// Fetch lab options
$query = "SELECT username FROM users";
$result = $conn->query($query);
$labIncharges = [];
while ($row = $result->fetch_assoc()) {
  $labIncharges[] = ucfirst($row['username']);
}

// Initialize variables
$success = false;
$error = "";
$lab_details = [];

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $labName = $_POST['lab_name'];
  $department = $_POST['department'];
  $labIncharge = $_POST['lab_incharge'];
  $estDate = $_POST['est_date'];
  $labStatus = $_POST['lab_status'];
  $roomCapacity = $_POST['room_capacity'];
  $building = $_POST['building'];
  $room = $_POST['room'];

  function generateLabCode($dept, $estDate, $conn)
  {
    // Extract the year from the establishment date
    $year = date('Y', strtotime($estDate));

    // Get the department short form (first two characters in uppercase)
    $deptShort = strtoupper(substr($dept, 0, 2));

    // Query to find the last lab code for the same department and year
    $query = "SELECT lab_id FROM labs WHERE lab_id LIKE '$deptShort-$year-%' ORDER BY lab_id DESC LIMIT 1";
    $result = $conn->query($query);

    if ($result->num_rows > 0) {
      // If labs exist for the same department and year, increment the serial number
      $lastLabCode = $result->fetch_assoc()['lab_id'];
      $lastSerial = (int) substr($lastLabCode, -3); // Extract the last 3 digits
      $newSerial = $lastSerial + 1;
    } else {
      // If no labs exist for the same department and year, start with 101
      $newSerial = 101;
    }

    // Format the serial number to 3 digits (e.g., 101, 102, ..., 999)
    $serial = str_pad($newSerial, 3, '0', STR_PAD_LEFT);

    // Generate the lab code
    $labCode = "$deptShort-$year-$serial";
    return $labCode;
  }

  $labCode = generateLabCode($department, $estDate, $conn);

  // Insert into database
  $stmt = $conn->prepare("INSERT INTO labs (lab_id, lab_name, department, lab_incharge, establishment_date, status, room_capacity, building, room_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
  $stmt->bind_param("ssssssiss", $labCode, $labName, $department, $labIncharge, $estDate, $labStatus, $roomCapacity, $building, $room);

  if ($stmt->execute()) {
    $success = true;
    $lab_details = [
      'lab_name' => $labName,
      'lab_id' => $labCode,
      'building' => $building,
      'room_capacity' => $roomCapacity
    ];
  } else {
    $error = "Failed to add lab. Please try again.";
  }
}
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
        <li class="active">
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
          <span>Add Lab </span>
        </div>
        <div class="user-info">
          <!-- <img alt="User Avatar" src="https://placehold.co/30x30" /> -->
          <i class="fa-solid fa-circle-user"></i><span class="font-rale"> Administrator
          </span>
        </div>
      </div>
      <!-- CONTENT START of BELOW HEADER -->
      <div class="lab-management-container">
        <div class="form-wrapper">
          <!-- Lab Details Section -->
          <div class="form-section" id="lab-details">
            <div class="section-header">
              <h2 class="section-title">Register New Lab</h2>
            </div>

            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
              <div class="grid-2col">
                <div class="input-group">
                  <label class="input-label">Lab Name <span>*</span></label>
                  <input type="text" name="lab_name" class="form-input" placeholder="Enter lab name (e.g., Computer Lab A)" required>
                </div>

                <div class="input-group">
                  <label class="input-label">Lab Code <span>*</span></label>
                  <input type="text" id="labCode" name="labCode" class="no-hover form-input "
                    value="Lab Code will be Auto-Generated" disabled>
                </div>

                <div class="input-group">
                  <label class="input-label">Department <span>*</span></label>
                  <select name="department" class="form-input" required>
                    <option value="">Select Department</option>
                    <option value="CO">CO - Computer Science</option>
                    <option value="AI">AI - Artificial Intelligence</option>
                    <option value="IT">IT - Information Technology</option>
                    <option value="EE">EE - Electronics Engineering</option>
                  </select>
                </div>

                <div class="input-group">
                  <label class="input-label">Lab In-Charge <span>*</span></label>
                  <select name="lab_incharge" class="form-input" required>
                    <option value="">Select Lab In-Charge</option>
                    <?php foreach ($labIncharges as $incharge): ?>
                      <option value="<?= htmlspecialchars($incharge) ?>"><?= htmlspecialchars($incharge) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="input-group">
                  <label class="input-label">Establishment Date <span>*</span></label>
                  <input type="date" name="est_date" class="form-input" required>
                </div>

                <div class="input-group">
                  <label class="input-label">Lab Status <span>*</span></label>
                  <select name="lab_status" class="form-input" required>
                    <option value="">Select Lab Status</option>
                    <option value="Active">Active</option>
                    <option value="InActive">InActive</option>
                    <option value="In Repair">In Repair</option>
                  </select>
                </div>

                <div class="input-group">
                  <label class="input-label">Room Capacity <span>*</span></label>
                  <input type="number" name="room_capacity" class="form-input" placeholder="Enter total number of PCs in the lab" required>
                </div>

                <div class="input-group">
                  <label class="input-label">Building <span>*</span></label>
                  <input type="text" name="building" class="form-input" placeholder="Enter building name (e.g., Building A)" required>
                </div>

                <div class="input-group">
                  <label class="input-label">Room <span>*</span></label>
                  <input type="text" name="room" class="form-input" placeholder="Enter room number" required>
                </div>
              </div>

              <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                  <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                    stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                    class="lucide lucide-circle-plus">
                    <circle cx="12" cy="12" r="10" />
                    <path d="M8 12h8" />
                    <path d="M12 8v8" />
                  </svg> Add Lab
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
      <!-- CONTENT END of BELOW HEADER  -->
    </div>
    <!-- CONTENT END  -->
    <!-- POP UP MODAL -->
    <?php if (isset($success) && $success): ?>
      <div id="lab-created-modal" class="modal-overlay" style="display: flex;">
        <div class="modal">
          <div class="modal-header">
            <div class="modal-icon icon-blue">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="20"
                height="20">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
              </svg>
            </div>
            <h2 class="modal-title">Computer Lab Created</h2>
          </div>

          <div class="modal-body">
            <p class="success-text">Your computer lab has been successfully added in the system.</p>

            <div class="item-details">
              <div class="detail-row">
                <div class="detail-label-new">Lab Name:</div>
                <div class="detail-value" id="lab-name"><?= $lab_details['lab_name'] ?></div>
              </div>
              <div class="detail-row">
                <div class="detail-label-new">Lab ID:</div>
                <div class="detail-value" id="lab-id"><?= $lab_details['lab_id'] ?></div>
              </div>
              <div class="detail-row">
                <div class="detail-label-new">Building:</div>
                <div class="detail-value" id="lab-building"><?= $lab_details['building'] ?></div>
              </div>
              <div class="detail-row">
                <div class="detail-label-new">Capacity:</div>
                <div class="detail-value" id="lab-capacity"><?= $lab_details['room_capacity'] ?> stations</div>
              </div>
            </div>

            <p class="next-steps">The lab has been added to the Lab Management System. You can add devices to the lab.</p>

            <div class="action-buttons">
              <div class="action-btnModal" id="add-devices-btnModal" onclick="window.location.href='addDevice.php'">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16"
                  height="16">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
                Add Devices
              </div>
              <div class="action-btnModal" id="view-lab-btnModal" onclick="window.location.href='lab-details.php'">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16"
                  height="16">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                View Lab
              </div>
            </div>
          </div>

          <div class="modal-footer">
            <button class="btnModal btnModal-primary btnModal-blue" id="lab-done-btnModal" onclick="window.location.reload()">Done</button>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <script>
    // JavaScript is now only for the 'Done' button in the success modal
    document.addEventListener('DOMContentLoaded', function() {
      const labDoneBtnModal = document.getElementById('lab-done-btnModal');
      const labCreatedModal = document.getElementById('lab-created-modal');
      
      if (labDoneBtnModal) {
        labDoneBtnModal.addEventListener('click', function () {
          if (labCreatedModal) {
            labCreatedModal.style.display = 'none';
          }
        });
      }
      
      // Close modal when clicking outside
      if (labCreatedModal) {
        labCreatedModal.addEventListener('click', function (e) {
          if (e.target === labCreatedModal) {
            labCreatedModal.style.display = 'none';
          }
        });
      }
    });
  </script>
</body>

</html>