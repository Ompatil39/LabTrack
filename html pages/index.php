<?php
session_start();
if (isset($_SESSION["logged_in"]) !== true) {
  header("Location: login.php");
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
  <link
    href="https://fonts.googleapis.com/css2?family=Raleway:ital,wght@0,100..900;1,100..900&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap"
    rel="stylesheet" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Raleway:ital,wght@0,100..900;1,100..900&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap"
    rel="stylesheet" />
</head>

<body>
  <div class="container">
    <!-- Sidebar -->
    <div class="sidebar">
      <div class="logo">
        <!-- <span><i class="fa-brands fa-watchman-monitoring colour"></i>LabTrack</span> -->
        <a href="index.php" class="none">
          <span><i class="fa-brands fa-watchman-monitoring colour"></i>LabTrack</span>
        </a>

      </div>
      <hr class="solid" />
      <ul class="menu">
        <li class="menu-title">Menu</li>
        <!-- Dashboard Link -->
        <li class="active">
          <a href="index.php">
            <i class="fa-solid fa-chart-pie"></i><span>Dashboard</span>
          </a>
        </li>
        <li>
          <a href="labs.php">
            <i class="fa-solid fa-network-wired"></i> Labs
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
          <span>
            <i class="fa-solid fa-arrow-right-from-bracket"></i>
          </span>
          Logout
        </a>
      </div>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
      <div class="header">
        <!-- User Info Section -->
        <div class="sub-heading">
          <span>Overview</span>
        </div>

        <div class="user-info">
          <!-- <img alt="User Avatar" src="https://placehold.co/30x30" /> -->
          <i class="fa-solid fa-circle-user"></i><span class="font-rale"> Administrator
          </span>
        </div>
      </div>

      <!-- Dashboard CARDS Section 1-->
      <div class="card-container">
        <div class="items item-1 blue-left">
          <span class="font-rale">Total Labs</span>
          <div class="mid">
            <div class="font-number value">8</div>
            <!-- <div id="totalLabs"></div> -->
          </div>
          <span class="highlight"><i class="fa-solid fa-arrow-trend-up"></i> +1 added this
            year</span>
        </div>
        <div class="items item-3 green-left">
          <span class="font-rale">Active Devices</span>
          <div class="font-number value">160</div>
          <span class="highlight"><i class="fa-solid fa-chart-line"></i> 92.5% uptime</span>
        </div>
        <div class="items item-4 yellow-left">
          <span class="font-rale">Resolved Grievances</span>
          <div class="font-number value">45</div>
          <span class="highlight"><i class="fa-regular fa-square-check"></i> +10 resolved this
            week</span>
        </div>
        <div class="items item-2 red-left">
          <span class="font-rale">Maintenance Requests</span>
          <div class="font-number value">15</div>
          <div class="sub-text">
            <span class="highlight"><i class="fa-regular fa-circle-check"></i> 5 Resolved</span>
            <span class="highlight-red"><i class="fa-solid fa-spinner"></i> 4 Pending</span>
          </div>
        </div>
      </div>

      <!-- Dashboard CARDS Section 1 -->
      <div class="ex">
        <!--  -->
        <div class="items small">
          <span class="font-rale">Active Devices</span>
          <div class="chart1">
            <canvas id="myChart"></canvas>
          </div>
        </div>
        <!--  -->
        <div class="items large">
          <span class="font-rale">Maintenance Requests</span>
          <div class="chart1">
            <canvas id="maintenanceRequests"></canvas>
          </div>
        </div>
        <!--  -->
        <div class="items small">
          <span class="font-rale">Lab Occupancy</span>
          <div class="chart1">
            <canvas id="labOccupancy"></canvas>
          </div>
        </div>
      </div>

      <!-- Dashboard CARDS Section 2 -->
      <div class="ex2">
        <div class="items">
          <span class="font-rale">Faulty Devices</span>
          <div>
            <canvas id="faultyDevices"></canvas>
          </div>
        </div>
        <div class="items">
          <span class="font-rale">Grievances Trend</span>
          <div>
            <canvas id="grievancesTrend"></canvas>
          </div>
        </div>
      </div>

      <!-- Additional Charts -->
      <div class="ex">
        <div class="items">
          <span class="font-rale">Maintenance Status</span>
          <div class="chart1">
            <canvas id="maintenanceStatus"></canvas>
          </div>
        </div>
        <div class="items large">
          <span class="font-rale">Device Distribution</span>
          <div class="chart1">
            <canvas id="deviceDistribution"></canvas>
          </div>
        </div>
        <div class="items">
          <span class="font-rale">Grievance Status</span>
          <div>
            <canvas id="grievanceStatus"></canvas>
          </div>
        </div>
      </div>

      <div class="ex3">
        <div class="items">
          <span class="font-rale">Pending Grievances</span>
          <div>
            <canvas id="pendingGrievances"></canvas>
          </div>
        </div>
        <div class="items">
          <span class="font-rale">Grievances by Category</span>
          <div>
            <canvas id="grievancesByCategory"></canvas>
          </div>
        </div>
        <div class="items">
          <span class="font-rale">Average Maintenance Time (Hours)</span>
          <div>
            <canvas id="averageMaintenanceTime"></canvas>
          </div>
        </div>
      </div>
      <!--  -->
    </div>
  </div>
</body>

</html>
<!-- SMALL CHARTS Sparkline  -->
<script>
  $("#totalLabs").sparkline([1, 1, 2, 4], {
    type: "line",
    height: "50",
    width: "100%",
    lineWidth: "2",
    lineColor: "#177dff",
    fillColor: "rgba(23, 125, 255, 0.14)",
  });
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="../public/js/script.js"></script>