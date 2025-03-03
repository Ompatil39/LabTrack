<?php
session_start();
if (!isset($_SESSION["logged_in"]) || $_SESSION["logged_in"] !== true) {
  header("Location: login.php");
  exit;
}

require_once 'db.php';

// Fetch dashboard statistics with error checking
function fetchSingleValue($conn, $query, $description)
{
  $result = mysqli_query($conn, $query);
  if ($result === false) {
    die("Query failed ($description): " . mysqli_error($conn));
  }
  $row = mysqli_fetch_row($result);
  return $row ? $row[0] : 0;
}

function fetchArray($conn, $query, $description)
{
  $result = mysqli_query($conn, $query);
  if ($result === false) {
    die("Query failed ($description): " . mysqli_error($conn));
  }
  $data = [];
  while ($row = mysqli_fetch_assoc($result)) {
    $data[] = $row;
  }
  return $data;
}

$total_labs = fetchSingleValue($conn, "SELECT COUNT(*) FROM labs", "Total Labs");
$active_devices = fetchSingleValue($conn, "SELECT COUNT(*) FROM devices WHERE status = 'Active'", "Active Devices");
$resolved_grievances = fetchSingleValue($conn, "SELECT COUNT(*) FROM grievances WHERE status = 'Resolved'", "Resolved Grievances");
$pending_grievances = fetchSingleValue($conn, "SELECT COUNT(*) FROM grievances WHERE status IN ('Submitted', 'In Progress', 'Under Review')", "Pending Grievances");

$total_devices = fetchSingleValue($conn, "SELECT COUNT(*) FROM devices", "Total Devices");
$active_percentage = $total_devices > 0 ? round(($active_devices / $total_devices) * 100, 1) : 0;

$device_status = fetchArray($conn, "SELECT status, COUNT(*) as count FROM devices GROUP BY status", "Device Status");
$grievance_trend = fetchArray($conn, "SELECT DATE_FORMAT(submission_date, '%b') as month, COUNT(*) as count FROM grievances GROUP BY MONTH(submission_date) ORDER BY submission_date LIMIT 8", "Grievance Trend");
$grievance_requests = fetchArray($conn, "SELECT labs.lab_name, COUNT(*) as count FROM grievances JOIN labs ON grievances.lab_id = labs.lab_id GROUP BY labs.lab_id, labs.lab_name LIMIT 8", "Grievance Requests");
$lab_occupancy = fetchArray($conn, "SELECT status, COUNT(*) as count FROM labs GROUP BY status", "Lab Occupancy");
$device_distribution = fetchArray($conn, "SELECT labs.lab_name, COUNT(*) as count FROM devices JOIN labs ON devices.lab_id = labs.lab_id GROUP BY labs.lab_id, labs.lab_name LIMIT 8", "Device Distribution");
$faulty_devices = fetchArray($conn, "SELECT labs.lab_name, devices.device_type, COUNT(*) as count FROM devices JOIN labs ON devices.lab_id = labs.lab_id WHERE devices.status = 'Faulty' GROUP BY labs.lab_id, devices.device_type LIMIT 16", "Faulty Devices");

$grievances_by_category = fetchArray($conn, "SELECT device_category, COUNT(*) as count FROM grievances GROUP BY device_category", "Grievances by Category");
$grievance_status = fetchArray($conn, "SELECT status, COUNT(*) as count FROM grievances GROUP BY status", "Grievance Status");
$device_status_for_chart = fetchArray($conn, "SELECT status, COUNT(*) as count FROM devices GROUP BY status", "Device Status for Chart");
$pending_grievances_by_category = fetchArray($conn, "SELECT device_category, COUNT(*) as count FROM grievances WHERE status IN ('Submitted', 'In Progress', 'Under Review') GROUP BY device_category", "Pending Grievances by Category");
$avg_grievance_time = fetchArray($conn, "SELECT device_type, COUNT(*) as count FROM devices WHERE status = 'In Repair' GROUP BY device_type LIMIT 4", "Average Grievance Time");
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Lab Monitoring System</title>

  <link rel="stylesheet" href="../public/css/style.css" />
  <script src="https://kit.fontawesome.com/0319a73572.js" crossorigin="anonymous"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Raleway:ital,wght@0,100..900;1,100..900&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Raleway:ital,wght@0,100..900;1,100..900&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet" />

  <!-- Load Chart.js UMD version from unpkg -->
  <script src="https://unpkg.com/chart.js@4.4.1/dist/chart.umd.js" defer></script>
  <script src="https://code.jquery.com/jquery-3.6.4.min.js" defer></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-sparklines/2.1.2/jquery.sparkline.min.js" defer></script>

  <!-- Fallback for Chart.js -->
  <script>
    window.addEventListener('load', function() {
      if (typeof Chart === 'undefined') {
        console.warn('CDN failed, attempting to load local Chart.js');
        var script = document.createElement('script');
        script.src = '../public/js/chart.umd.js'; // Place chart.umd.js in your public/js folder
        script.onload = function() {
          console.log('Local Chart.js UMD loaded successfully');
          initializeCharts();
        };
        script.onerror = function() {
          console.error('Failed to load local Chart.js fallback');
        };
        document.head.appendChild(script);
      } else {
        console.log('Chart.js CDN loaded successfully');
        initializeCharts();
      }
    });
  </script>
</head>

<body>
  <div class="container">
    <div class="sidebar">
      <div class="logo">
        <a href="index.php" class="none">
          <span><i class="fa-brands fa-watchman-monitoring colour"></i>LabTrack</span>
        </a>
      </div>
      <hr class="solid" />
      <ul class="menu">
        <li class="menu-title">Menu</li>
        <li class="active">
          <a href="index.php"><i class="fa-solid fa-chart-pie"></i><span>Dashboard</span></a>
        </li>
        <li><a href="labs.php"><i class="fa-solid fa-network-wired"></i> Labs</a></li>
        <li><a href="addLab.php"><i class="fa-solid fa-plus"></i><span>Add Lab</span></a></li>
        <li><a href="addDevice.php"><i class="fa-solid fa-plus"></i><span>Add Devices</span></a></li>
        <li><a href="inventory.php"><i class="fa-solid fa-warehouse"></i> Inventory</a></li>
        <li><a href="grievance.php"><i class="fa-solid fa-paper-plane"></i> Grievance</a></li>
      </ul>
      <div class="log-out">
        <a href="logout.php" class="none">
          <span><i class="fa-solid fa-arrow-right-from-bracket"></i></span> Logout
        </a>
      </div>
    </div>

    <div class="main-content">
      <div class="header">
        <div class="sub-heading"><span>Overview</span></div>
        <div class="user-info">
          <i class="fa-solid fa-circle-user"></i>
          <span class="font-rale"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Administrator'); ?></span>
        </div>
      </div>
      <div class="card-container">
        <div class="items item-1 blue-left">
          <span class="font-rale">Total Labs</span>
          <div class="mid">
            <div class="font-number value"><?php echo $total_labs; ?></div>
          </div>
          <span class="highlight"><i class="fa-solid fa-arrow-trend-up"></i> <?php echo $total_labs; ?> labs monitored</span>
        </div>
        <div class="items item-3 green-left">
          <span class="font-rale">Active Devices</span>
          <div class="font-number value"><?php echo $active_devices; ?></div>
          <span class="highlight"><i class="fa-solid fa-chart-line"></i> <?php echo $active_percentage; ?>% active</span>
        </div>
        <div class="items item-4 yellow-left">
          <span class="font-rale">Resolved Grievances</span>
          <div class="font-number value"><?php echo $resolved_grievances; ?></div>
          <span class="highlight"><i class="fa-regular fa-square-check"></i> Resolved</span>
        </div>
        <div class="items item-2 red-left">
          <span class="font-rale">Pending Grievances</span>
          <div class="font-number value"><?php echo $pending_grievances; ?></div>
          <div class="sub-text">
            <span class="highlight"><i class="fa-regular fa-circle-check"></i> <?php echo $resolved_grievances; ?> Resolved</span>
            <span class="highlight-red"><i class="fa-solid fa-spinner"></i> <?php echo $pending_grievances; ?> Pending</span>
          </div>
        </div>
      </div>

      <div class="ex">
        <div class="items small">
          <span class="font-rale">Active Devices</span>
          <div class="chart1"><canvas id="myChart"></canvas></div>
        </div>
        <div class="items large">
          <span class="font-rale">Grievance Requests</span>
          <div class="chart1"><canvas id="grievanceRequests"></canvas></div>
        </div>
        <div class="items small">
          <span class="font-rale">Lab Occupancy</span>
          <div class="chart1"><canvas id="labOccupancy"></canvas></div>
        </div>
      </div>

      <div class="ex2">
        <div class="items">
          <span class="font-rale">Faulty Devices</span>
          <div><canvas id="faultyDevices"></canvas></div>
        </div>
        <div class="items">
          <span class="font-rale">Grievances Trend</span>
          <div><canvas id="grievancesTrend"></canvas></div>
        </div>
      </div>

      <div class="ex">
        <div class="items">
          <span class="font-rale">Device Status</span>
          <div class="chart1"><canvas id="deviceStatus"></canvas></div>
        </div>
        <div class="items large">
          <span class="font-rale">Device Distribution</span>
          <div class="chart1"><canvas id="deviceDistribution"></canvas></div>
        </div>
        <div class="items">
          <span class="font-rale">Grievance Status</span>
          <div><canvas id="grievanceStatus"></canvas></div>
        </div>
      </div>

      <div class="ex3">
        <div class="items">
          <span class="font-rale">Pending Grievances</span>
          <div><canvas id="pendingGrievances"></canvas></div>
        </div>
        <div class="items">
          <span class="font-rale">Grievances by Category</span>
          <div><canvas id="grievancesByCategory"></canvas></div>
        </div>
        <div class="items">
          <span class="font-rale">Average Grievance Time (Hours)</span>
          <div><canvas id="averageGrievanceTime"></canvas></div>
        </div>
      </div>
    </div>
  </div>

  <script>
    function initializeCharts() {
      if (typeof Chart === 'undefined') {
        console.error('Chart.js failed to load completely. Charts will not be rendered.');
        const chartContainers = document.querySelectorAll('.chart1, .ex2 canvas, .ex3 canvas');
        chartContainers.forEach(container => {
          container.innerHTML = '<p style="color: red; text-align: center;">Failed to load charts. Please check your internet connection and refresh the page.</p>';
        });
        return;
      }

      const colors = {
        primaryLight: "#5dade2",
        primary: "#3498db",
        primaryDark: "#2e86c1",
        secondaryLight: "#7fb3d5",
        secondary: "#5d8aa8",
        secondaryDark: "#4a708b",
        accent: "#ff6f61",
        warning: "#ff3b30",
        background: "#ffffff",
        border: "#d1d1d6",
        textPrimary: "#2c3e50",
        textSecondary: "#6d6d72",
      };

      // Chart 1: Active Devices (Pie)
      new Chart(document.getElementById("myChart").getContext("2d"), {
        type: "pie",
        data: {
          labels: <?php echo json_encode(array_column($device_status, 'status')); ?>,
          datasets: [{
            label: "Device Status",
            data: <?php echo json_encode(array_column($device_status, 'count')); ?>,
            backgroundColor: ["#2e86c1", "#5d8aa8", "#ff6f61"],
            borderColor: "#ffffff",
            borderWidth: 2,
          }]
        },
        options: {
          responsive: true,
          plugins: {
            legend: {
              position: "top",
              labels: {
                font: {
                  color: "#2c3e50"
                }
              }
            },
            tooltip: {
              backgroundColor: "#ffffff",
              titleColor: "#2c3e50",
              bodyColor: "#6d6d72"
            }
          }
        }
      });

      // Chart 2: Grievance Requests (Bar)
      new Chart(document.getElementById("grievanceRequests").getContext("2d"), {
        type: "bar",
        data: {
          labels: <?php echo json_encode(array_column($grievance_requests, 'lab_name')); ?>,
          datasets: [{
            label: "Grievance Requests",
            data: <?php echo json_encode(array_column($grievance_requests, 'count')); ?>,
            backgroundColor: function(context) {
              const chart = context.chart;
              const {
                ctx,
                chartArea
              } = chart;
              if (!chartArea) return "#3498db";
              const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
              gradient.addColorStop(0, "#5dade2");
              gradient.addColorStop(0.6, "#3498db");
              gradient.addColorStop(1, "#2e86c1");
              return gradient;
            },
            borderColor: "#ffffff",
            borderWidth: 2,
            borderRadius: 8,
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: true,
          layout: {
            padding: {
              top: 20,
              bottom: 10
            }
          },
          scales: {
            y: {
              beginAtZero: true,
              grid: {
                color: "#d1d1d6"
              }
            },
            x: {
              grid: {
                color: "#d1d1d6"
              }
            }
          },
          plugins: {
            legend: {
              labels: {
                font: {
                  color: "#2c3e50"
                }
              }
            }
          }
        }
      });

      // Chart 3: Lab Occupancy (Doughnut)
      new Chart(document.getElementById("labOccupancy").getContext("2d"), {
        type: "doughnut",
        data: {
          labels: <?php echo json_encode(array_column($lab_occupancy, 'status')); ?>,
          datasets: [{
            data: <?php echo json_encode(array_column($lab_occupancy, 'count')); ?>,
            backgroundColor: ["#3498db", "#5d8aa8", "#ff6f61"]
          }]
        },
        options: {
          circumference: 180,
          rotation: -90,
          animation: {
            animateScale: true,
            animateRotate: true
          },
          cutout: "50%",
          plugins: {
            legend: {
              position: "top",
              align: "center",
              labels: {}
            }
          },
          responsive: true,
          maintainAspectRatio: true
        }
      });

      // Chart 4: Device Distribution (Horizontal Bar)
      new Chart(document.getElementById("deviceDistribution").getContext("2d"), {
        type: "bar",
        data: {
          labels: <?php echo json_encode(array_column($device_distribution, 'lab_name')); ?>,
          datasets: [{
            label: "Device Count",
            data: <?php echo json_encode(array_column($device_distribution, 'count')); ?>,
            backgroundColor: function(context) {
              const chart = context.chart;
              const {
                ctx,
                chartArea
              } = chart;
              if (!chartArea) return "#3498db";
              const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
              gradient.addColorStop(0, "#5dade2");
              gradient.addColorStop(0.6, "#3498db");
              gradient.addColorStop(1, "#2e86c1");
              return gradient;
            },
            borderColor: "#ffffff",
            borderWidth: 2
          }]
        },
        options: {
          indexAxis: "y",
          responsive: true,
          scales: {
            x: {
              title: {
                display: true,
                text: "Count",
                color: "#2c3e50"
              },
              grid: {
                color: "#d1d1d6"
              }
            }
          },
          plugins: {
            legend: {
              labels: {
                font: {
                  color: "#2c3e50"
                }
              }
            }
          }
        }
      });

      // Chart 5: Faulty Devices (Bar)
      new Chart(document.getElementById("faultyDevices").getContext("2d"), {
        type: "bar",
        data: {
          labels: <?php echo json_encode(array_map(function ($item) {
                    return $item['lab_name'] . ' - ' . $item['device_type'];
                  }, $faulty_devices)); ?>,
          datasets: [{
            label: "Faulty Devices Count",
            data: <?php echo json_encode(array_column($faulty_devices, 'count')); ?>,
            backgroundColor: function(context) {
              const chart = context.chart;
              const {
                ctx,
                chartArea
              } = chart;
              if (!chartArea) return "#3498db";
              const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
              gradient.addColorStop(0, "#5dade2");
              gradient.addColorStop(0.6, "#3498db");
              gradient.addColorStop(1, "#2e86c1");
              return gradient;
            },
            borderColor: "#ffffff",
            borderWidth: 2
          }]
        },
        options: {
          responsive: true,
          scales: {
            x: {
              title: {
                display: true,
                text: "Lab - Device Type",
                color: "#2c3e50"
              },
              grid: {
                color: "#d1d1d6"
              },
              ticks: {
                autoSkip: false,
                maxRotation: 50,
                minRotation: 50
              }
            },
            y: {
              title: {
                display: true,
                text: "Count",
                color: "#2c3e50"
              },
              grid: {
                color: "#d1d1d6"
              },
              beginAtZero: true
            }
          },
          plugins: {
            legend: {
              labels: {
                font: {
                  color: "#2c3e50"
                }
              }
            }
          }
        }
      });

      // Chart 6: Grievances Trend (Line)
      new Chart(document.getElementById("grievancesTrend").getContext("2d"), {
        type: "line",
        data: {
          labels: <?php echo json_encode(array_column($grievance_trend, 'month')); ?>,
          datasets: [{
            label: "Grievance Count",
            data: <?php echo json_encode(array_column($grievance_trend, 'count')); ?>,
            borderColor: "#3498db",
            backgroundColor: "rgba(52, 152, 219, 0.2)",
            borderWidth: 2,
            pointBackgroundColor: "#3498db",
            pointBorderColor: "#ffffff",
            pointRadius: 5,
            pointHoverRadius: 7
          }]
        },
        options: {
          responsive: true,
          scales: {
            y: {
              title: {
                display: true,
                text: "Count",
                color: "#2c3e50"
              },
              grid: {
                color: "#d1d1d6"
              },
              beginAtZero: true
            },
            x: {
              title: {
                display: true,
                text: "Month",
                color: "#2c3e50"
              },
              grid: {
                color: "#d1d1d6"
              }
            }
          },
          plugins: {
            legend: {
              labels: {
                font: {
                  color: "#2c3e50"
                }
              }
            }
          }
        }
      });

      // Chart 7: Device Status (Pie)
      new Chart(document.getElementById("deviceStatus").getContext("2d"), {
        type: "pie",
        data: {
          labels: <?php echo json_encode(array_column($device_status_for_chart, 'status')); ?>,
          datasets: [{
            label: "Device Status",
            data: <?php echo json_encode(array_column($device_status_for_chart, 'count')); ?>,
            backgroundColor: ["#2e86c1", "#5d8aa8", "#ff6f61"],
            borderColor: "#ffffff",
            borderWidth: 2,
          }]
        },
        options: {
          responsive: true,
          plugins: {
            legend: {
              position: "top",
              labels: {
                font: {
                  color: "#2c3e50"
                }
              }
            },
            tooltip: {
              backgroundColor: "#ffffff",
              titleColor: "#2c3e50",
              bodyColor: "#6d6d72"
            }
          }
        }
      });

      // Chart 8: Grievance Status (Doughnut)
      new Chart(document.getElementById("grievanceStatus").getContext("2d"), {
        type: "doughnut",
        data: {
          labels: <?php echo json_encode(array_column($grievance_status, 'status')); ?>,
          datasets: [{
            data: <?php echo json_encode(array_column($grievance_status, 'count')); ?>,
            backgroundColor: ["#3498db", "#5d8aa8", "#ff6f61", "#f39c12"]
          }]
        },
        options: {
          circumference: 180,
          rotation: -90,
          animation: {
            animateScale: true,
            animateRotate: true
          },
          cutout: "50%",
          plugins: {
            legend: {
              position: "top",
              align: "center",
              labels: {}
            }
          },
          responsive: true,
          maintainAspectRatio: true
        }
      });

      // Chart 9: Pending Grievances by Category (Bar)
      new Chart(document.getElementById("pendingGrievances").getContext("2d"), {
        type: "bar",
        data: {
          labels: <?php echo json_encode(array_column($pending_grievances_by_category, 'device_category')); ?>,
          datasets: [{
            label: "Pending Grievances Count",
            data: <?php echo json_encode(array_column($pending_grievances_by_category, 'count')); ?>,
            backgroundColor: function(context) {
              const chart = context.chart;
              const {
                ctx,
                chartArea
              } = chart;
              if (!chartArea) return "#3498db";
              const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
              gradient.addColorStop(0, "#5dade2");
              gradient.addColorStop(0.6, "#3498db");
              gradient.addColorStop(1, "#2e86c1");
              return gradient;
            },
            borderColor: "#ffffff",
            borderWidth: 2
          }]
        },
        options: {
          responsive: true,
          scales: {
            x: {
              title: {
                display: true,
                text: "Device Category",
                color: "#2c3e50"
              },
              grid: {
                color: "#d1d1d6"
              }
            },
            y: {
              title: {
                display: true,
                text: "Count",
                color: "#2c3e50"
              },
              grid: {
                color: "#d1d1d6"
              },
              beginAtZero: true
            }
          },
          plugins: {
            legend: {
              labels: {
                font: {
                  color: "#2c3e50"
                }
              }
            }
          }
        }
      });

      // Chart 10: Grievances by Category (Bar)
      new Chart(document.getElementById("grievancesByCategory").getContext("2d"), {
        type: "bar",
        data: {
          labels: <?php echo json_encode(array_column($grievances_by_category, 'device_category')); ?>,
          datasets: [{
            label: "Grievance Count",
            data: <?php echo json_encode(array_column($grievances_by_category, 'count')); ?>,
            backgroundColor: function(context) {
              const chart = context.chart;
              const {
                ctx,
                chartArea
              } = chart;
              if (!chartArea) return "#3498db";
              const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
              gradient.addColorStop(0, "#5dade2");
              gradient.addColorStop(0.6, "#3498db");
              gradient.addColorStop(1, "#2e86c1");
              return gradient;
            },
            borderColor: "#ffffff",
            borderWidth: 2
          }]
        },
        options: {
          responsive: true,
          scales: {
            x: {
              title: {
                display: true,
                text: "Device Category",
                color: "#2c3e50"
              },
              grid: {
                color: "#d1d1d6"
              }
            },
            y: {
              title: {
                display: true,
                text: "Count",
                color: "#2c3e50"
              },
              grid: {
                color: "#d1d1d6"
              },
              beginAtZero: true
            }
          },
          plugins: {
            legend: {
              labels: {
                font: {
                  color: "#2c3e50"
                }
              }
            }
          }
        }
      });

      // Chart 11: Average Grievance Time (Hours) - Placeholder Data
      new Chart(document.getElementById("averageGrievanceTime").getContext("2d"), {
        type: "bar",
        data: {
          labels: <?php echo json_encode(array_column($avg_grievance_time, 'device_type')); ?>,
          datasets: [{
            label: "Device Count",
            data: <?php echo json_encode(array_column($avg_grievance_time, 'count')); ?>,
            backgroundColor: function(context) {
              const chart = context.chart;
              const {
                ctx,
                chartArea
              } = chart;
              if (!chartArea) return "#3498db";
              const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
              gradient.addColorStop(0, "#5dade2");
              gradient.addColorStop(0.6, "#3498db");
              gradient.addColorStop(1, "#2e86c1");
              return gradient;
            },
            borderColor: "#ffffff",
            borderWidth: 2
          }]
        },
        options: {
          responsive: true,
          scales: {
            x: {
              title: {
                display: true,
                text: "Device Type",
                color: "#2c3e50"
              },
              grid: {
                color: "#d1d1d6"
              }
            },
            y: {
              title: {
                display: true,
                text: "Count",
                color: "#2c3e50"
              },
              grid: {
                color: "#d1d1d6"
              },
              beginAtZero: true
            }
          },
          plugins: {
            legend: {
              labels: {
                font: {
                  color: "#2c3e50"
                }
              }
            }
          }
        }
      });
    }
  </script>
</body>

</html>