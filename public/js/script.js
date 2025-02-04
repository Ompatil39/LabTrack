// Chart 1: Lab Status (Doughnut with Animation)
const ctx = document.getElementById("myChart").getContext("2d");
new Chart(ctx, {
  type: "doughnut",
  data: {
    labels: ["Active", "Under Maintenance", "In-Active"],
    datasets: [
      {
        label: "Lab Status",
        data: [100, 30, 40],
        // Updated bluish light colors
        backgroundColor: ["#a2d2ff", "#89c2d9", "#70a1ff"],
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
        align: "center", // Ensure legends are in one row
        display: true,
        labels: {
          boxWidth: 15, // Smaller colored box for the legend
          font: { size: 10 },
        },
      },
      usePointStyle: true,
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
        // Cycle through bluish hues
        backgroundColor: [
          "#a2d2ff",
          "#89c2d9",
          "#70a1ff",
          "#a2d2ff",
          "#89c2d9",
          "#70a1ff",
          "#a2d2ff",
          "#89c2d9",
        ],
        borderWidth: 2,
        borderRadius: 8,
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
        // Repeating bluish hues for consistency
        backgroundColor: [
          "#a2d2ff",
          "#89c2d9",
          "#70a1ff",
          "#a2d2ff",
          "#89c2d9",
          "#70a1ff",
          "#a2d2ff",
          "#89c2d9",
        ],
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
        // Updated to a bluish tone
        backgroundColor: "#70a1ff",
      },
      {
        label: "Faulty Printers",
        data: [2, 4, 1, 3, 2, 1, 4, 2],
        backgroundColor: "#a2d2ff",
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
        // Updated bluish shades for each category
        backgroundColor: ["#a2d2ff", "#89c2d9", "#70a1ff"],
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
        // Updated to bluish light tones
        backgroundColor: ["#a2d2ff", "#89c2d9", "#70a1ff"],
      },
    ],
  },
  options: {
    animation: {
      animateRotate: true,
      animateScale: true,
    },
    plugins: {
      legend: {
        position: "top",
        align: "center",
        display: true,
        labels: {
          boxWidth: 15, // Smaller colored box
          font: { size: 10 },
        },
      },
    },
  },
});

// Chart 7: Grievances Trend (Line with Default Animation)
const grievancesTrendCtx = document
  .getElementById("grievancesTrend")
  .getContext("2d");
new Chart(grievancesTrendCtx, {
  type: "line",
  data: {
    labels: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug"],
    datasets: [
      {
        label: "Grievances",
        data: [3, 8, 7, 12, 6, 9, 7, 10],
        // Updated to a bluish palette
        borderColor: "#70a1ff",
        backgroundColor: "#d0e9ff",
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
        // Updated to bluish tones
        backgroundColor: ["#a2d2ff", "#89c2d9", "#70a1ff"],
      },
    ],
  },
  options: {
    animation: {
      animateScale: true,
    },
    plugins: {
      legend: {
        position: "top",
        align: "center",
        display: true,
        labels: {
          boxWidth: 15, // Smaller legend boxes for circular charts
          // Text font remains the same
        },
      },
    },
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
        data: [5, 3, 2],
        // Updated to bluish shades
        backgroundColor: ["#a2d2ff", "#89c2d9", "#70a1ff"],
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

// GAUGE CHART: Lab Occupancy (Doughnut with Custom Animation)
const labOccupancyCtx = document
  .getElementById("labOccupancy")
  .getContext("2d");
new Chart(labOccupancyCtx, {
  type: "doughnut",
  data: {
    labels: ["Active", "Under Maintenance", "In-Active"],
    datasets: [
      {
        data: [7, 1, 0],
        // Updated to bluish shades
        backgroundColor: ["#a2d2ff", "#89c2d9", "#70a1ff"],
      },
    ],
  },
  options: {
    circumference: 180, // Semi-circle (180 degrees)
    rotation: -90, // Start from the bottom
    animation: {
      animateScale: true,
      animateRotate: true,
    },
    cutout: "50%",
    plugins: {
      legend: {
        position: "top",
        align: "center",
        display: true,
        labels: {
          boxWidth: 15, // Smaller box size for the legend
          font: { size: 10 },
        },
      },
    },
    responsive: true,
    maintainAspectRatio: true,
  },
});

// Chart 10: Average Maintenance Time (Bar with Fade Animation)
const averageMaintenanceTimeCtx = document
  .getElementById("averageMaintenanceTime")
  .getContext("2d");
new Chart(averageMaintenanceTimeCtx, {
  type: "bar",
  data: {
    labels: ["PC", "Printer", "Monitor", "Keyboard"],
    datasets: [
      {
        label: "Average Maintenance Time (Hours)",
        data: [2.5, 3.0, 1.5, 1.0],
        // Updated to bluish tones (using a couple of repeating shades)
        backgroundColor: ["#a2d2ff", "#89c2d9", "#70a1ff", "#89c2d9"],
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
