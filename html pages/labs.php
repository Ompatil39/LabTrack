<?php
session_start();
if (!isset($_SESSION["logged_in"]) || $_SESSION["logged_in"] !== true) {
  header("Location: login.php");
  exit;
}

include 'db.php';

// First, get all labs
$labsQuery = "SELECT * FROM labs";
$labsResult = mysqli_query($conn, $labsQuery);

if (!$labsResult) {
  die("Query failed: " . mysqli_error($conn));
}

$labs = mysqli_fetch_all($labsResult, MYSQLI_ASSOC);
mysqli_free_result($labsResult);

// For each lab, count devices and devices under repair
foreach ($labs as $key => $lab) {
  // Count total devices in this lab
  $deviceCountQuery = "SELECT COUNT(*) as total FROM devices 
                        WHERE lab_id = '" . mysqli_real_escape_string($conn, $lab['lab_id']) . "' 
                        AND device_id LIKE 'PC-%'";
  $deviceResult = mysqli_query($conn, $deviceCountQuery);

  if ($deviceResult) {
    $deviceCount = mysqli_fetch_assoc($deviceResult);
    $labs[$key]['total_devices'] = $deviceCount['total'];
    mysqli_free_result($deviceResult);
  } else {
    $labs[$key]['total_devices'] = 0;
  }

  // Count devices under repair in this lab
  $repairCountQuery = "SELECT COUNT(*) as repair_count FROM devices 
                        WHERE lab_id = '" . mysqli_real_escape_string($conn, $lab['lab_id']) . "' 
                        AND status = 'Under Repair'";
  $repairResult = mysqli_query($conn, $repairCountQuery);

  if ($repairResult) {
    $repairCount = mysqli_fetch_assoc($repairResult);
    $labs[$key]['under_repair_count'] = $repairCount['repair_count'];
    mysqli_free_result($repairResult);
  } else {
    $labs[$key]['under_repair_count'] = 0;
  }
}

// Close database connection
mysqli_close($conn);
?>

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
  <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-sparklines/2.1.2/jquery.sparkline.min.js"></script>
  <link rel="stylesheet" href="../public/css/style.css" />
  <script src="https://kit.fontawesome.com/0319a73572.js" crossorigin="anonymous"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,200,0,200&icon_names=dns" />
  <link href="https://fonts.googleapis.com/css2?family=Raleway:ital,wght@0,100..900;1,100..900&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Raleway:ital,wght@0,100..900;1,100..900&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet" />
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
        <li>
          <a href="index.php">
            <i class="fa-solid fa-chart-pie"></i> Dashboard
          </a>
        </li>
        <li class="active">
          <a href="labs.php">
            <i class="fa-solid fa-network-wired"></i>
            <span>Labs</span>
          </a>
        </li>
        <li>
          <a href="addLab.php">
            <i class="fa-solid fa-plus"></i><span>Add Lab</span>
          </a>
        </li>
        <li>
          <a href="addDevice.php">
            <i class="fa-solid fa-plus"></i><span>Add Devices</span>
          </a>
        </li>
        <li>
          <a href="inventory.php"> <i class="fa-solid fa-warehouse"></i> Inventory </a>
        </li>
        <li>
          <a href="grievance.php"> <i class="fa-solid fa-paper-plane"></i> Grievance </a>
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
      <div class="header">
        <div class="sub-heading"><span>Overview</span></div>
        <div class="user-info" onclick="window.location.href = 'profileManage.php';" style="margin-right: 0.5rem;">
          <i class="fa-solid fa-circle-user"></i>
          <span class="font-rale"><?php echo htmlspecialchars(strtoupper($_SESSION['username']) ?? 'User');  ?></span>
        </div>
      </div>

      <!-- LABS -->
      <div class="lab-container">
        <?php if (empty($labs)): ?>
          <p>No labs found in the database.</p>
        <?php else: ?>
          <?php foreach ($labs as $lab): ?>
            <a href="lab-details.php?id=<?php echo htmlspecialchars($lab['lab_id']); ?>" class="lab-card none">
              <div class="lab-header">
                <h4><?php echo htmlspecialchars($lab['lab_id']); ?></h4>
                <div class="status-chip">
                  <?php if ($lab['status'] == 'Active'): ?>
                    <span class="highlight1">
                      <i class="fas fa-check-circle"></i> Active
                    </span>
                  <?php elseif ($lab['status'] == 'In Repair'): ?>
                    <span class="highlight1-red">
                      <i class="fas fa-times-circle"></i> In Repair
                    </span>
                  <?php else: ?>
                    <span class="highlight1-red">
                      <i class="fas fa-exclamation-circle"></i> InActive
                    </span>
                  <?php endif; ?>
                </div>
              </div>
              <div class="lab-stats">
                <div class="stat">
                  <span class="stat-value"><?php echo (int)$lab['total_devices']; ?></span>
                  <span class="stat-label">Devices</span>
                  <i class="fas fa-users"></i>
                </div>
                <div class="stat">
                  <span class="stat-value"><?php echo (int)$lab['under_repair_count']; ?></span>
                  <span class="stat-label">Under Repair</span>
                  <i class="fa-solid fa-screwdriver-wrench"></i>
                </div>
              </div>
              <button class="view-lab-button font-number">View Lab <i class="fa-solid fa-angles-right"></i></button>
            </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- Floating Action Button -->
      <a href="addLab.php" class="fab font-number">
        <i class="fas fa-plus"></i> Add New Lab
      </a>
    </div>
  </div>
</body>

</html>