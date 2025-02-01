// Initialize all charts
var myChart = echarts.init(document.getElementById("myChart"));
var maintenanceRequests = echarts.init(
  document.getElementById("maintenanceRequests")
);
var labOccupancy = echarts.init(document.getElementById("labOccupancy"));
var faultyDevices = echarts.init(document.getElementById("faultyDevices"));
var grievancesTrend = echarts.init(document.getElementById("grievancesTrend"));
var maintenanceStatus = echarts.init(
  document.getElementById("maintenanceStatus")
);
var deviceDistribution = echarts.init(
  document.getElementById("deviceDistribution")
);
var grievanceStatus = echarts.init(document.getElementById("grievanceStatus"));
var pendingGrievances = echarts.init(
  document.getElementById("pendingGrievances")
);
var grievancesByCategory = echarts.init(
  document.getElementById("grievancesByCategory")
);
var averageMaintenanceTime = echarts.init(
  document.getElementById("averageMaintenanceTime")
);
// ------------------------------
// Chart 1: Lab Status (Doughnut with Animation)
// ------------------------------
var chart1 = echarts.init(document.getElementById("myChart"));
var option1 = {
  tooltip: {
    trigger: "item",
    backgroundColor: "#d3e3f1",
    textStyle: { color: "#19374f" },
  },
  legend: {
    top: "5%",
    left: "center",
    textStyle: { fontSize: 10 },
  },
  series: [
    {
      name: "Lab Status",
      type: "pie",
      radius: ["40%", "70%"], // doughnut: inner and outer radii
      avoidLabelOverlap: false,
      label: { show: false },
      itemStyle: {
        borderColor: "#ffffff",
        borderWidth: 2,
      },
      data: [
        { value: 100, name: "Active", itemStyle: { color: "#2c4a63" } },
        {
          value: 30,
          name: "Under Maintenance",
          itemStyle: { color: "#fdaf4b" },
        },
        { value: 40, name: "In-Active", itemStyle: { color: "#f3545d" } },
      ],
    },
  ],
  animationDuration: 1000, // approximate duration
  animationEasing: "cubicOut",
};
chart1.setOption(option1);

// ------------------------------
// Chart 2: Maintenance Requests (Bar with Bounce Animation)
// ------------------------------
var chart2 = echarts.init(document.getElementById("maintenanceRequests"));
var option2 = {
  tooltip: { trigger: "axis" },
  xAxis: {
    type: "category",
    data: [
      "Lab 1",
      "Lab 2",
      "Lab 3",
      "Lab 4",
      "Lab 5",
      "Lab 6",
      "Lab 7",
      "Lab 8",
    ],
  },
  yAxis: {
    type: "value",
    min: 0,
  },
  series: [
    {
      name: "Maintenance Requests",
      type: "bar",
      data: [7, 3, 9, 4, 6, 3, 4, 2],
      barWidth: 50,
      itemStyle: {
        // Cycle through the three provided colors:
        color: function (params) {
          var colors = ["#80b1da", "#fdaf4b", "#1d7af3"];
          return colors[params.dataIndex % colors.length];
        },
      },
      // (Border styling on bars is not as straightforward in ECharts; using emphasis here as an example)
      emphasis: {
        itemStyle: {
          borderColor: "#ffffff",
          borderWidth: 2,
        },
      },
    },
  ],
  animationDuration: 1000,
  animationEasing: "bounceOut",
};
chart2.setOption(option2);

// ------------------------------
// Chart 3: Device Distribution (Horizontal Bar with Slide Animation)
// ------------------------------
var chart3 = echarts.init(document.getElementById("deviceDistribution"));
var option3 = {
  tooltip: { trigger: "axis" },
  legend: {
    data: ["Device Count"],
    top: "top",
  },
  xAxis: {
    type: "value",
    name: "Count",
  },
  yAxis: {
    type: "category",
    name: "Labs",
    data: [
      "Lab 1",
      "Lab 2",
      "Lab 3",
      "Lab 4",
      "Lab 5",
      "Lab 6",
      "Lab 7",
      "Lab 8",
    ],
  },
  series: [
    {
      name: "Device Count",
      type: "bar",
      data: [40, 50, 30, 45, 25, 35, 20, 15],
      itemStyle: {
        color: function (params) {
          var colors = ["#80b1da", "#fdaf4b", "#1d7af3"];
          return colors[params.dataIndex % colors.length];
        },
      },
    },
  ],
  animationDuration: 1500,
  animationEasing: "cubicInOut",
};
chart3.setOption(option3);

// ------------------------------
// Chart 4: Faulty Devices (Bar with Two Series)
// ------------------------------
var chart4 = echarts.init(document.getElementById("faultyDevices"));
var option4 = {
  tooltip: { trigger: "axis" },
  legend: {
    data: ["Faulty PCs", "Faulty Printers"],
    top: "top",
  },
  xAxis: {
    type: "category",
    name: "Labs",
    data: [
      "Lab 1",
      "Lab 2",
      "Lab 3",
      "Lab 4",
      "Lab 5",
      "Lab 6",
      "Lab 7",
      "Lab 8",
    ],
  },
  yAxis: {
    type: "value",
    name: "Count",
  },
  series: [
    {
      name: "Faulty PCs",
      type: "bar",
      data: [5, 8, 3, 6, 4, 2, 7, 1],
      itemStyle: { color: "#f3545d" },
    },
    {
      name: "Faulty Printers",
      type: "bar",
      data: [2, 4, 1, 3, 2, 1, 4, 2],
      itemStyle: { color: "#80b1da" },
    },
  ],
};
chart4.setOption(option4);

// ------------------------------
// Chart 5: Grievances by Category (Bar with Fade Animation)
// ------------------------------
var chart5 = echarts.init(document.getElementById("grievancesByCategory"));
var option5 = {
  tooltip: { trigger: "axis" },
  legend: {
    data: ["Grievances"],
    top: "top",
  },
  xAxis: {
    type: "category",
    name: "Categories",
    data: ["Hardware", "Software", "Internet"],
  },
  yAxis: {
    type: "value",
    name: "Count",
  },
  series: [
    {
      name: "Grievances",
      type: "bar",
      data: [15, 10, 5],
      itemStyle: {
        color: function (params) {
          var colors = ["#177dff", "#4caf50", "#fdaf4b"];
          return colors[params.dataIndex];
        },
      },
    },
  ],
  animationDuration: 1200,
  animationEasing: "cubicOut",
};
chart5.setOption(option5);

// ------------------------------
// Chart 6: Grievance Status (Pie Chart with Rotation Animation)
// ------------------------------
var chart6 = echarts.init(document.getElementById("grievanceStatus"));
var option6 = {
  tooltip: { trigger: "item" },
  legend: { top: "top" },
  series: [
    {
      name: "Grievance Status",
      type: "pie",
      radius: "70%",
      itemStyle: {
        borderColor: "#ffffff",
        borderWidth: 2,
      },
      data: [
        { value: 30, name: "Resolved", itemStyle: { color: "#2c4a63" } },
        { value: 10, name: "Pending", itemStyle: { color: "#fdaf4b" } },
        { value: 5, name: "In Progress", itemStyle: { color: "#f3545d" } },
      ],
    },
  ],
  animationDuration: 1000,
  animationEasing: "cubicOut",
};
chart6.setOption(option6);

// ------------------------------
// Chart 7: Grievances Trend (Line with Default Animation)
// ------------------------------
var chart7 = echarts.init(document.getElementById("grievancesTrend"));
var option7 = {
  tooltip: { trigger: "axis" },
  legend: { data: ["Grievances"], top: "top" },
  xAxis: {
    type: "category",
    name: "Months",
    data: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug"],
  },
  yAxis: {
    type: "value",
    name: "Count",
  },
  series: [
    {
      name: "Grievances",
      type: "line",
      data: [3, 8, 7, 12, 6, 9, 7, 10],
      lineStyle: { color: "#2c4a63" },
      itemStyle: { color: "#2c4a63" },
      // No areaStyle since fill: false in Chart.js
    },
  ],
};
chart7.setOption(option7);

// ------------------------------
// Chart 8: Maintenance Status (Doughnut with Scale Animation)
// ------------------------------
var chart8 = echarts.init(document.getElementById("maintenanceStatus"));
var option8 = {
  tooltip: { trigger: "item" },
  legend: { top: "top" },
  series: [
    {
      name: "Maintenance Status",
      type: "pie",
      radius: ["40%", "70%"],
      itemStyle: {
        borderColor: "#ffffff",
        borderWidth: 2,
      },
      data: [
        { value: 50, name: "Completed", itemStyle: { color: "#2c4a63" } },
        { value: 20, name: "In Progress", itemStyle: { color: "#fdaf4b" } },
        { value: 30, name: "Pending", itemStyle: { color: "#f3545d" } },
      ],
    },
  ],
  animationDuration: 1000,
  animationEasing: "cubicOut",
};
chart8.setOption(option8);

// ------------------------------
// Chart 9: Pending Grievances (Horizontal Bar with Bounce Animation)
// ------------------------------
var chart9 = echarts.init(document.getElementById("pendingGrievances"));
var option9 = {
  tooltip: { trigger: "axis" },
  legend: { data: ["Pending Grievances"], top: "top" },
  xAxis: {
    type: "value",
    name: "Count",
  },
  yAxis: {
    type: "category",
    name: "Categories",
    data: ["Hardware", "Software", "Internet"],
  },
  series: [
    {
      name: "Pending Grievances",
      type: "bar",
      data: [5, 3, 2],
      itemStyle: {
        color: function (params) {
          var colors = ["#80b6f4", "#4caf50", "#fdaf4b"];
          return colors[params.dataIndex];
        },
      },
    },
  ],
  animationDuration: 1000,
  animationEasing: "bounceOut",
};
chart9.setOption(option9);

// ------------------------------
// GAUGE CHART: Lab Occupancy (Semi-Circular Doughnut Gauge)
// ------------------------------
var chartGauge = echarts.init(document.getElementById("labOccupancy"));
/*
  To simulate a half-doughnut (semi-circular gauge) in ECharts,
  we add an extra (invisible) data item to “complete” the circle.
  Here the actual values (7, 1, 0) sum to 8.
*/
var optionGauge = {
  tooltip: { trigger: "item" },
  legend: { top: "top" },
  series: [
    {
      name: "Lab Occupancy",
      type: "pie",
      radius: ["50%", "100%"],
      center: ["50%", "75%"], // push the chart downward to show a semi-circle
      startAngle: 180,
      label: { show: false },
      data: [
        { value: 7, name: "Active", itemStyle: { color: "#2c4a63" } },
        {
          value: 1,
          name: "Under Maintenance",
          itemStyle: { color: "#fdaf4b" },
        },
        { value: 0, name: "In-Active", itemStyle: { color: "#f3545d" } },
        // Invisible data item to fill the bottom half of the circle
        {
          value: 8,
          name: "",
          itemStyle: { color: "transparent" },
          tooltip: { show: false },
          label: { show: false },
        },
      ],
    },
  ],
  animationDuration: 1000,
  animationEasing: "cubicOut",
};
chartGauge.setOption(optionGauge);

// ------------------------------
// Chart 10: Average Maintenance Time (Bar with Fade Animation)
// ------------------------------
var chart10 = echarts.init(document.getElementById("averageMaintenanceTime"));
var option10 = {
  tooltip: { trigger: "axis" },
  legend: {
    data: ["Average Maintenance Time (Hours)"],
    top: "top",
  },
  xAxis: {
    type: "category",
    name: "Device Types",
    data: ["PC", "Printer", "Monitor", "Keyboard"],
  },
  yAxis: {
    type: "value",
    name: "Time (Hours)",
  },
  series: [
    {
      name: "Average Maintenance Time (Hours)",
      type: "bar",
      data: [2.5, 3.0, 1.5, 1.0],
      itemStyle: {
        color: function (params) {
          var colors = ["#2c4a63", "#fdaf4b", "#80b6f4", "#1d7af3"];
          return colors[params.dataIndex];
        },
      },
    },
  ],
  animationDuration: 1000,
  animationEasing: "cubicOut",
};
chart10.setOption(option10);

// Make charts responsive
window.addEventListener("resize", function () {
  myChart.resize();
  maintenanceRequests.resize();
  labOccupancy.resize();
  faultyDevices.resize();
  grievancesTrend.resize();
  maintenanceStatus.resize();
  deviceDistribution.resize();
  grievanceStatus.resize();
  pendingGrievances.resize();
  grievancesByCategory.resize();
  averageMaintenanceTime.resize();
});
