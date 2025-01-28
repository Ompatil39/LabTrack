// Chart 1: Lab Status (Doughnut with Animation)
const ctx = document.getElementById("myChart").getContext("2d");
new Chart(ctx, {
  type: "doughnut",
  data: {
    labels: ["ACTIVE", "UNDER MAINTENANCE", "INACTIVE"],
    datasets: [
      {
        label: "Lab Status",
        data: [100, 30, 40],
        backgroundColor: ["#f3545d", "#fdaf4b", "#1d7af3"],
        borderColor: ["#ffffff", "#ffffff", "#ffffff"],
        borderWidth: 2,
      },
    ],
  },
  options: {
    responsive: true,
    animation: {
      animateScale: true,
      animateRotate: true,
    },
    plugins: {
      legend: {
        position: "top",
        display: false,
        labels: {
          color: "#19374f",
          font: { size: 14, family: "Poppins" },
        },
      },
      tooltip: {
        backgroundColor: "#d3e3f1",
        titleColor: "#19374f",
        bodyColor: "#19374f",
      },
    },
  },
});

// Chart 2: Maintenance Requests (Bar with Bounce Animation)
const maintenanceRequestsCtx = document
  .getElementById("maintenanceRequests")
  .getContext("2d");
new Chart(maintenanceRequestsCtx, {
  type: "bar",
  data: {
    labels: [
      "Lab 1",
      "Lab 2",
      "Lab 3",
      "Lab 4",
      "Lab 5",
      "Lab 6",
      "Lab 7",
      "Lab 8",
    ],
    datasets: [
      {
        label: "Maintenance Requests",
        data: [7, 3, 9, 4, 6, 3, 4, 2],
        backgroundColor: "#2c4a63",
        borderWidth: 2,
        barThickness: 50,
      },
    ],
  },
  options: {
    responsive: true,
    animation: {
      easing: "easeOutBounce",
      duration: 1000,
    },
    scales: {
      y: {
        beginAtZero: true,
      },
    },
  },
});

// Chart 3: Device Distribution (Bar with Slide Animation)
const deviceDistributionCtx = document
  .getElementById("deviceDistribution")
  .getContext("2d");
new Chart(deviceDistributionCtx, {
  type: "bar",
  data: {
    labels: [
      "Lab 1",
      "Lab 2",
      "Lab 3",
      "Lab 4",
      "Lab 5",
      "Lab 6",
      "Lab 7",
      "Lab 8",
    ],
    datasets: [
      {
        label: "Device Count",
        data: [40, 50, 30, 45, 25, 35, 20, 15],
        backgroundColor: ["#80b1da", "#fdaf4b", "#1d7af3"],
      },
    ],
  },
  options: {
    indexAxis: "y",
    animation: {
      easing: "easeInOutQuart",
      duration: 1500,
    },
    plugins: { legend: { display: true, position: "top" } },
    scales: {
      x: { title: { display: true, text: "Count" } },
      y: { title: { display: true, text: "Labs" } },
    },
  },
});

// Chart 4: Faulty Devices (Bar with Default Animation)
const faultyDevicesCtx = document
  .getElementById("faultyDevices")
  .getContext("2d");
new Chart(faultyDevicesCtx, {
  type: "bar",
  data: {
    labels: [
      "Lab 1",
      "Lab 2",
      "Lab 3",
      "Lab 4",
      "Lab 5",
      "Lab 6",
      "Lab 7",
      "Lab 8",
    ],
    datasets: [
      {
        label: "Faulty PCs",
        data: [5, 8, 3, 6, 4, 2, 7, 1],
        backgroundColor: "#f3545d",
      },
      {
        label: "Faulty Printers",
        data: [2, 4, 1, 3, 2, 1, 4, 2],
        backgroundColor: "#80b1da",
      },
    ],
  },
  options: {
    plugins: { legend: { display: true, position: "top" } },
    scales: {
      x: { title: { display: true, text: "Labs" } },
      y: { title: { display: true, text: "Count" } },
    },
  },
});

// Chart 5: Grievances by Category (Bar with Fade Animation)
const grievancesByCategoryCtx = document
  .getElementById("grievancesByCategory")
  .getContext("2d");
new Chart(grievancesByCategoryCtx, {
  type: "bar",
  data: {
    labels: ["Hardware", "Software", "Internet"],
    datasets: [
      {
        label: "Grievances",
        data: [15, 10, 5],
        backgroundColor: ["#177dff", "#4caf50", "#fdaf4b"],
      },
    ],
  },
  options: {
    animation: {
      easing: "easeInOutCubic",
      duration: 1200,
    },
    plugins: { legend: { display: true, position: "top" } },
    scales: {
      x: { title: { display: true, text: "Categories" } },
      y: { title: { display: true, text: "Count" } },
    },
  },
});

// Chart 6: Grievance Status (Pie Chart with Rotation Animation)
const grievanceStatusCtx = document
  .getElementById("grievanceStatus")
  .getContext("2d");
new Chart(grievanceStatusCtx, {
  type: "pie",
  data: {
    labels: ["Resolved", "Pending", "In Progress"],
    datasets: [
      {
        data: [30, 10, 5],
        backgroundColor: ["#2c4a63", "#fdaf4b", "#f3545d"],
      },
    ],
  },
  options: {
    animation: {
      animateRotate: true,
      animateScale: true,
    },
    plugins: { legend: { display: true, position: "top" } },
  },
});

// Chart 7: Grievances Trend (Line with Default Animation)
const grievancesTrendCtx = document
  .getElementById("grievancesTrend")
  .getContext("2d");
new Chart(grievancesTrendCtx, {
  type: "line",
  data: {
    labels: ["Jan", "Feb", "Mar", "Apr"],
    datasets: [
      {
        label: "Grievances",
        data: [5, 10, 8, 7],
        borderColor: "#2c4a63",
        backgroundColor: "#c2daf0",
        fill: false,
      },
    ],
  },
  options: {
    plugins: { legend: { display: true, position: "top" } },
    scales: {
      x: { title: { display: true, text: "Months" } },
      y: { title: { display: true, text: "Count" } },
    },
  },
});

// Chart 8: Maintenance Status (Doughnut with Scale Animation)
const maintenanceStatusCtx = document
  .getElementById("maintenanceStatus")
  .getContext("2d");
new Chart(maintenanceStatusCtx, {
  type: "doughnut",
  data: {
    labels: ["Completed", "In Progress", "Pending"],
    datasets: [
      {
        data: [50, 20, 30],
        backgroundColor: ["#f44336", "#177dff", "#fdaf4b"],
      },
    ],
  },
  options: {
    animation: {
      animateScale: true,
    },
    plugins: { legend: { display: true, position: "top" } },
  },
});

// Chart 9: Pending Grievances (Bar with Bounce Animation)
const pendingGrievancesCtx = document
  .getElementById("pendingGrievances")
  .getContext("2d");
new Chart(pendingGrievancesCtx, {
  type: "bar",
  data: {
    labels: ["Hardware", "Software", "Internet"],
    datasets: [
      {
        label: "Pending Grievances",
        data: [5, 3, 2], // Example data
        backgroundColor: ["#80b6f4", "#4caf50", "#fdaf4b"],
      },
    ],
  },
  options: {
    indexAxis: "y",
    plugins: { legend: { display: true, position: "top" } },
    scales: {
      x: { title: { display: true, text: "Count" } },
      y: { title: { display: true, text: "Categories" } },
    },
  },
});

// GAUGE CHART
const labOccupancyCtx = document
  .getElementById("labOccupancy")
  .getContext("2d");
new Chart(labOccupancyCtx, {
  type: "doughnut",
  data: {
    labels: ["Active", "Under Maintenance", "In-Active"],
    datasets: [
      {
        data: [7, 1, 0], // Values for the gauge
        backgroundColor: ["#2c4a63", "#fdaf4b", "#f3545d"], // Colors for each segment
      },
    ],
  },
  options: {
    circumference: 180, // Semi-circle (180 degrees)
    rotation: -90, // Start from the bottom
    animation: {
      animateScale: true, // Enable scaling animation
      animateRotate: true, // Enable rotation animation
    },
    cutout: "50%", // Increased thickness of the circular segments (smaller cutout)
    plugins: {
      legend: {
        display: true,
        position: "top", // Legend position
      },
    },
    responsive: true,
    maintainAspectRatio: false, // Allow custom width and height
  },
});

// Chart 10: Average Maintenance Time (Bar with Fade Animation)
const averageMaintenanceTimeCtx = document
  .getElementById("averageMaintenanceTime")
  .getContext("2d");
new Chart(averageMaintenanceTimeCtx, {
  type: "bar",
  data: {
    labels: ["PC", "Printer", "Monitor", "Keyboard"], // Example device types
    datasets: [
      {
        label: "Average Maintenance Time (Hours)",
        data: [2.5, 3.0, 1.5, 1.0], // Example data in hours
        backgroundColor: ["#2c4a63", "#fdaf4b", "#80b6f4", "#1d7af3"],
      },
    ],
  },
  options: {
    plugins: { legend: { display: true, position: "top" } },
    scales: {
      x: { title: { display: true, text: "Device Types" } },
      y: { title: { display: true, text: "Time (Hours)" } },
    },
  },
});
