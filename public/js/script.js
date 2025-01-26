// Chart 1
const ctx = document.getElementById("myChart").getContext("2d");
new Chart(ctx, {
  type: "doughnut",
  data: {
    labels: ["ACTIVE", "UNDER MAINTENANCE", "INACTIVE"],
    datasets: [
      {
        label: "Lab Status 2",
        data: [100, 30, 40], // Different data
        backgroundColor: [
          "#2c4a63", // blue-4 2c4a63 
          "#80b1da", // blue-7
          "#c2daf0", // blue-10 c2daf0
        ],
        borderColor: [
          "#ffffff", // White border
          "#ffffff",
          "#ffffff",
        ],
        borderWidth: 2,
      },
    ],
  },
  options: {
    responsive: true,
    plugins: {
      legend: {
        position: "top",
        display: false,
        labels: {
          color: "#19374f", // Text color
          font: {
            size: 14,
            family: "Poppins",
          },
        },
      },
      tooltip: {
        backgroundColor: "#d3e3f1", // Tooltip background
        titleColor: "#19374f", // Tooltip title color
        bodyColor: "#19374f", // Tooltip body color
      },
    },
  },
});

// Chart 2
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
    ], // Example labs
    datasets: [
      {
        label: "Maintenance Requests",
        data: [7, 3, 9, 4, 6, 3, 4, 2], // Example data
        backgroundColor: ["#48abf7"],
        borderWidth: 2,
        barThickness: 50, // Set bar thickness here
        maxBarThickness: 40, // Optionally set max thickness
      },
    ],
  },
  options: {
    responsive: true,
    // maintainAspectRatio: false,
    scales: {
      yAxes: [
        {
          ticks: {
            beginAtZero: true,
          },
        },
      ],
    },
  },
});


// Chart 3
const deviceDistributionCtx = document
  .getElementById("deviceDistribution")
  .getContext("2d");
new Chart(deviceDistributionCtx, {
  type: "bar",
  data: {
    labels: ["Lab A", "Lab B", "Lab C"],
    datasets: [
      {
        label: "Device Count",
        data: [40, 50, 30], // Example data
        backgroundColor: ["#2c4a63", "#80b1da", "#c2daf0"],
      },
    ],
  },
  options: {
    indexAxis: "y",
    plugins: { legend: { display: true, position: "top" } },
    scales: {
      x: { title: { display: true, text: "Count" } },
      y: { title: { display: true, text: "Labs" } },
    },
  },
});

// Chart 4
const faultyDevicesCtx = document
  .getElementById("faultyDevices")
  .getContext("2d");
new Chart(faultyDevicesCtx, {
  type: "bar",
  data: {
    labels: ["Lab A", "Lab B", "Lab C"],
    datasets: [
      {
        label: "Faulty PCs",
        data: [5, 8, 3],
        backgroundColor: "#2c4a63",
      },
      {
        label: "Faulty Printers",
        data: [2, 4, 1],
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

// Chart 5
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
        data: [15, 10, 5], // Example data
        backgroundColor: ["#2c4a63", "#80b1da", "#c2daf0"],
      },
    ],
  },
  options: {
    plugins: { legend: { display: true, position: "top" } },
    scales: {
      x: { title: { display: true, text: "Categories" } },
      y: { title: { display: true, text: "Count" } },
    },
  },
});

// Chart 6
const grievanceStatusCtx = document
  .getElementById("grievanceStatus")
  .getContext("2d");
new Chart(grievanceStatusCtx, {
  type: "doughnut",
  data: {
    labels: ["Resolved", "Pending", "In Progress"],
    datasets: [
      {
        data: [30, 10, 5], // Example data
        backgroundColor: ["#2c4a63", "#80b1da", "#c2daf0"],
      },
    ],
  },
  options: {
    plugins: { legend: { display: true, position: "top" } },
  },
});

// Chart 7
const grievancesTrendCtx = document
  .getElementById("grievancesTrend")
  .getContext("2d");
new Chart(grievancesTrendCtx, {
  type: "line",
  data: {
    labels: ["Jan", "Feb", "Mar", "Apr"], // Example months
    datasets: [
      {
        label: "Grievances",
        data: [5, 10, 8, 7], // Example data
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

// Chart 8
const maintenanceStatusCtx = document
  .getElementById("maintenanceStatus")
  .getContext("2d");
new Chart(maintenanceStatusCtx, {
  type: "doughnut",
  data: {
    labels: ["Completed", "In Progress", "Pending"],
    datasets: [
      {
        data: [50, 20, 30], // Example data
        backgroundColor: ["#2c4a63", "#80b1da", "#c2daf0"],
      },
    ],
  },
  options: {
    plugins: { legend: { display: true, position: "top" } },
  },
});

// Chart 9
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
        backgroundColor: ["#2c4a63", "#80b1da", "#c2daf0"],
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

// Chart 10
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
        backgroundColor: ["#2c4a63", "#80b1da", "#c2daf0", "#d3e3f1"],
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
