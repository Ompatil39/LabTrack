const colors = {
  primaryLight: "#5dade2", // Lighter blue (top of gradient)
  primary: "#3498db", // Base blue (middle of gradient)
  primaryDark: "#2e86c1", // Darker blue (bottom of gradient)
  secondaryLight: "#7fb3d5", // Lighter muted blue
  secondary: "#5d8aa8", // Base muted blue
  secondaryDark: "#4a708b", // Darker muted blue
  accent: "#ff6f61", // Bold coral for accents
  warning: "#ff3b30", // Bright red for warnings
  background: "#ffffff", // White
  border: "#d1d1d6", // Medium gray
  textPrimary: "#2c3e50", // Dark blue-gray
  textSecondary: "#6d6d72", // Medium gray
};

// Chart 1: Lab Status (Doughnut)
new Chart(document.getElementById("myChart").getContext("2d"), {
  type: "pie",
  data: {
    labels: ["Active", "Under Maintenance", "In-Active"],
    datasets: [
      {
        label: "Lab Status",
        data: [100, 30, 40],
        backgroundColor: ["#2e86c1", "#5d8aa8", "#ff6f61"],
        borderColor: "#ffffff",
        borderWidth: 2,
      },
    ],
  },
  options: {
    responsive: true,
    plugins: {
      legend: {
        position: "top",
        labels: { font: { color: "#2c3e50" } },
      },
      tooltip: {
        backgroundColor: "#ffffff",
        titleColor: "#2c3e50",
        bodyColor: "#6d6d72",
      },
    },
  },
});

const ctx = document.getElementById("maintenanceRequests").getContext("2d");
const maintenanceChart = new Chart(ctx, {
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
        backgroundColor: function (context) {
          const chart = context.chart;
          const { ctx, chartArea } = chart;
          if (!chartArea) return "#3498db"; // Ensure chart area exists
          // Create gradient inside the chart context
          const gradient = ctx.createLinearGradient(
            0,
            chartArea.top,
            0,
            chartArea.bottom
          );
          gradient.addColorStop(0, "#5dade2"); // Lighter blue (top)
          gradient.addColorStop(0.6, "#3498db"); // Base blue (middle)
          gradient.addColorStop(1, "#2e86c1"); // Darker blue (bottom)

          return gradient;
        },
        borderColor: "#ffffff",
        borderWidth: 2,
        borderRadius: 8,
      },
    ],
  },
  options: {
    responsive: true,
    maintainAspectRatio: true, // Adjust for flexible size
    layout: {
      padding: {
        top: 20, // Add padding to the top
        bottom: 10, // Add padding to the bottom
      },
    },
    scales: {
      y: { beginAtZero: true, grid: { color: "#d1d1d6" } },
      x: { grid: { color: "#d1d1d6" } },
    },
    plugins: {
      legend: { labels: { font: { color: "#2c3e50" } } },
    },
  },
});

// GAUGE CHART: Lab Occupancy (Doughnut with Custom Animation)
const labOccupancyCtx = document
  .getElementById("labOccupancy")
  .getContext("2d");
// Create the chart
new Chart(labOccupancyCtx, {
  type: "doughnut",
  data: {
    labels: ["Active", "Under Maintenance", "In-Active"],
    datasets: [
      {
        data: [7, 1, 0],
        // Updated to use colors from the `colors` object
        backgroundColor: [
          "#3498db", // Active
          "#5d8aa8", // Under Maintenance
          "#ff6f61", // In-Active
        ],
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

// Chart 3: Device Distribution (Horizontal Bar)
new Chart(document.getElementById("deviceDistribution").getContext("2d"), {
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
        backgroundColor: function (context) {
          const chart = context.chart;
          const { ctx, chartArea } = chart;
          if (!chartArea) return "#3498db"; // Ensure chart area exists
          // Create gradient inside the chart context
          const gradient = ctx.createLinearGradient(
            0,
            chartArea.top,
            0,
            chartArea.bottom
          );
          gradient.addColorStop(0, "#5dade2"); // Lighter blue (top)
          gradient.addColorStop(0.6, "#3498db"); // Base blue (middle)
          gradient.addColorStop(1, "#2e86c1"); // Darker blue (bottom)

          return gradient;
        },
        borderColor: "#ffffff",
        borderWidth: 2,
      },
    ],
  },
  options: {
    indexAxis: "y",
    responsive: true,
    scales: {
      x: {
        title: { display: true, text: "Count", color: "#2c3e50" },
        grid: { color: "#d1d1d6" },
      },
    },
    plugins: {
      legend: { labels: { font: { color: "#2c3e50" } } },
    },
  },
});

// Chart 4: Faulty Devices (Bar)
new Chart(document.getElementById("faultyDevices").getContext("2d"), {
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
        backgroundColor: function (context) {
          const chart = context.chart;
          const { ctx, chartArea } = chart;
          if (!chartArea) return "#3498db"; // Fallback color

          const gradient = ctx.createLinearGradient(
            0,
            chartArea.top,
            0,
            chartArea.bottom
          );
          gradient.addColorStop(0, "#a0d0e8"); // Lighter shade of #6baed6 (Top)
          gradient.addColorStop(0.4, "#6baed6"); // Medium blue
          gradient.addColorStop(1, "#2b7ba3"); // Darker blue (Bottom)
          return gradient;
        },
      },
      {
        label: "Faulty Printers",
        data: [2, 4, 1, 3, 2, 1, 4, 2],
        backgroundColor: function (context) {
          const chart = context.chart;
          const { ctx, chartArea } = chart;
          if (!chartArea) return "#5d8aa8"; // Fallback color

          const gradient = ctx.createLinearGradient(
            0,
            chartArea.top,
            0,
            chartArea.bottom
          );
          gradient.addColorStop(0, "#c2e3f0"); // Much lighter shade of #9ecae1 (Top)
          gradient.addColorStop(0.4, "#9ecae1"); // Medium light blue
          gradient.addColorStop(1, "#4f94c4"); // Stronger blue (Bottom)
          return gradient;
        },
      },
    ],
  },
  options: {
    responsive: true,
    scales: {
      x: {
        title: { display: true, text: "Labs", color: "#2c3e50" },
        grid: { color: "#d1d1d6" },
      },
      y: {
        title: { display: true, text: "Count", color: "#2c3e50" },
        grid: { color: "#d1d1d6" },
      },
    },
    plugins: {
      legend: { labels: { font: { color: "#2c3e50" } } },
    },
  },
});

// Chart 5: Grievances by Category (Bar)
new Chart(document.getElementById("grievancesByCategory").getContext("2d"), {
  type: "bar",
  data: {
    labels: ["Hardware", "Software", "Internet"],
    datasets: [
      {
        label: "Grievances",
        data: [15, 10, 5],
        backgroundColor: function (context) {
          const chart = context.chart;
          const { ctx, chartArea } = chart;
          if (!chartArea) return "#3498db"; // Fallback color

          const gradient = ctx.createLinearGradient(
            0,
            chartArea.top,
            0,
            chartArea.bottom
          );
          if (context.dataIndex === 0) {
            // Hardware
            gradient.addColorStop(0, "#5dade2"); // Lighter blue (top)
            gradient.addColorStop(0.6, "#3498db"); // Base blue (middle)
            gradient.addColorStop(1, "#2e86c1"); // Darker blue (bottom)
          } else if (context.dataIndex === 1) {
            // Software
            gradient.addColorStop(0, "#7fb3d5"); // Lighter muted blue (top)
            gradient.addColorStop(0.6, "#5d8aa8"); // Base muted blue (middle)
            gradient.addColorStop(1, "#4a708b"); // Darker muted blue (bottom)
          } else if (context.dataIndex === 2) {
            // Internet
            gradient.addColorStop(0, "#ff8f7a"); // Lighter coral (top)
            gradient.addColorStop(0.6, "#ff6f61"); // Base coral (middle)
            gradient.addColorStop(1, "#e65a50"); // Darker coral (bottom)
          }
          return gradient;
        },
        borderColor: "#ffffff",
        borderWidth: 2,
      },
    ],
  },
  options: {
    responsive: true,
    scales: {
      x: {
        title: { display: true, text: "Categories", color: "#2c3e50" },
        grid: { color: "#d1d1d6" },
      },
      y: {
        title: { display: true, text: "Count", color: "#2c3e50" },
        grid: { color: "#d1d1d6" },
      },
    },
    plugins: {
      legend: { labels: { font: { color: "#2c3e50" } } },
    },
  },
});

// Chart 6: Grievance Status (Pie)
new Chart(document.getElementById("grievanceStatus").getContext("2d"), {
  type: "pie",
  data: {
    labels: ["Resolved", "Pending", "In Progress"],
    datasets: [
      {
        data: [30, 10, 5],
        backgroundColor: ["#3498db", "#5d8aa8", "#ff6f61"],
      },
    ],
  },
  options: {
    responsive: true,
    plugins: {
      legend: {
        position: "top",
        labels: { font: { color: "#2c3e50" } },
      },
    },
  },
});

// Chart 7: Grievances Trend (Line)
new Chart(document.getElementById("grievancesTrend").getContext("2d"), {
  type: "line",
  data: {
    labels: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug"],
    datasets: [
      {
        label: "Grievances",
        data: [3, 8, 7, 12, 6, 9, 7, 10],
        borderColor: "#3182bd",
        backgroundColor: "rgba(49, 130, 189, 0.2)", // Light blue with transparency
        fill: true,
        tension: 0.1,
      },
    ],
  },
  options: {
    responsive: true,
    scales: {
      x: {
        title: { display: true, text: "Months", color: "#2c3e50" },
        grid: { color: "#d1d1d6" },
      },
      y: {
        title: { display: true, text: "Count", color: "#2c3e50" },
        grid: { color: "#d1d1d6" },
      },
    },
    plugins: {
      legend: { labels: { font: { color: "#2c3e50" } } },
    },
  },
});

// Chart 8: Maintenance Status (Doughnut)
new Chart(document.getElementById("maintenanceStatus").getContext("2d"), {
  type: "doughnut",
  data: {
    labels: ["Completed", "In Progress", "Pending"],
    datasets: [
      {
        data: [50, 20, 30],
        backgroundColor: ["#3498db", "#5d8aa8", "#ff6f61"],
      },
    ],
  },
  options: {
    cutout: "58%",
    responsive: true,
    plugins: {
      legend: {
        position: "top",
        labels: { font: { color: "#2c3e50" } },
      },
    },
  },
});

// Chart 9: Pending Grievances (Horizontal Bar)
new Chart(document.getElementById("pendingGrievances").getContext("2d"), {
  type: "bar",
  data: {
    labels: ["Hardware", "Software", "Internet"],
    datasets: [
      {
        label: "Pending Grievances",
        data: [5, 3, 2],
        backgroundColor: function (context) {
          const chart = context.chart;
          const { ctx, chartArea } = chart;
          if (!chartArea) return "#3498db"; // Fallback color

          const gradient = ctx.createLinearGradient(
            0,
            chartArea.top,
            0,
            chartArea.bottom
          );
          gradient.addColorStop(0, "#a0d0e8"); // Lighter shade of #6baed6 (Top)
          gradient.addColorStop(0.1, "#6baed6"); // Medium blue
          gradient.addColorStop(1, "#2b7ba3"); // Darker blue (Bottom)

          return gradient;
        },
        borderColor: "#ffffff",
        borderWidth: 2,
      },
    ],
  },
  options: {
    indexAxis: "y",
    responsive: true,
    scales: {
      x: {
        title: { display: true, text: "Count", color: "#2c3e50" },
        grid: { color: "#d1d1d6" },
      },
    },
    plugins: {
      legend: { labels: { font: { color: "#2c3e50" } } },
    },
  },
});

// Chart 10: Average Maintenance Time (Bar)
new Chart(document.getElementById("averageMaintenanceTime").getContext("2d"), {
  type: "bar",
  data: {
    labels: ["PC", "Printer", "Monitor", "Keyboard"],
    datasets: [
      {
        label: "Average Maintenance Time (Hours)",
        data: [2.5, 3.0, 1.5, 1.0],
        backgroundColor: function (context) {
          const chart = context.chart;
          const { ctx, chartArea } = chart;
          if (!chartArea) return "#3498db"; // Fallback color

          const gradient = ctx.createLinearGradient(
            0,
            chartArea.top,
            0,
            chartArea.bottom
          );
          if (context.dataIndex === 0) {
            // PC
            gradient.addColorStop(0, "#5dade2"); // Lighter blue (top)
            gradient.addColorStop(0.6, "#3498db"); // Base blue (middle)
            gradient.addColorStop(1, "#2e86c1"); // Darker blue (bottom)
          } else if (context.dataIndex === 1) {
            // Printer
            gradient.addColorStop(0, "#7fb3d5"); // Lighter muted blue (top)
            gradient.addColorStop(0.6, "#5d8aa8"); // Base muted blue (middle)
            gradient.addColorStop(1, "#4a708b"); // Darker muted blue (bottom)
          } else if (context.dataIndex === 2) {
            // Monitor
            gradient.addColorStop(0, "#ff8f7a"); // Lighter coral (top)
            gradient.addColorStop(0.6, "#ff6f61"); // Base coral (middle)
            gradient.addColorStop(1, "#e65a50"); // Darker coral (bottom)
          } else if (context.dataIndex === 3) {
            // Keyboard
            gradient.addColorStop(0, "#5dade2"); // Lighter blue (top)
            gradient.addColorStop(0.6, "#3498db"); // Base blue (middle)
            gradient.addColorStop(1, "#2e86c1"); // Darker blue (bottom)
          }
          return gradient;
        },
        borderColor: "#ffffff",
        borderWidth: 2,
      },
    ],
  },
  options: {
    responsive: true,
    maintainAspectRatio: true,
    scales: {
      x: {
        title: { display: true, text: "Device Types", color: "#2c3e50" },
        grid: { color: "#d1d1d6" },
      },
      y: {
        title: { display: true, text: "Time (Hours)", color: "#2c3e50" },
        grid: { color: "#d1d1d6" },
      },
    },
    plugins: {
      legend: { labels: { font: { color: "#2c3e50" } } },
    },
  },
});
