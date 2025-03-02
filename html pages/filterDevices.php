<?php
include 'db.php';

$lab_id = $_POST['lab_id'];
$search = isset($_POST['search']) ? $conn->real_escape_string($_POST['search']) : '';
$status = isset($_POST['status']) ? $conn->real_escape_string($_POST['status']) : '';
$category = isset($_POST['category']) ? $conn->real_escape_string($_POST['category']) : '';
$type = isset($_POST['type']) ? $conn->real_escape_string($_POST['type']) : 'All';

$where = "WHERE d.lab_id = '$lab_id'";
if ($type === 'PC') {
    $where .= " AND d.device_type = 'PC'";
} elseif (!empty($category)) {
    $where .= " AND d.device_type = '$category'";
}
if (!empty($search)) {
    $where .= " AND (d.device_id LIKE '%$search%' OR d.device_name LIKE '%$search%')";
}
if (!empty($status)) {
    $where .= " AND d.status = '$status'";
}

$sql = "SELECT d.device_id, d.device_name, d.device_type, 
        CASE 
            WHEN d.device_type = 'PC' THEN (SELECT pc_id FROM pc_details WHERE device_id = d.device_id)
            ELSE 'N/A' 
        END as pc_id, 
        d.status, 'Working' as remarks 
        FROM devices d 
        $where";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['device_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['device_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['device_type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['pc_id']) . "</td>";
        echo "<td><span class='status status-" . strtolower(str_replace(' ', '-', $row['status'])) . "'>" . htmlspecialchars($row['status']) . "</span></td>";
        echo "<td>" . htmlspecialchars($row['remarks']) . "</td>";
        echo "<td>
                <a href='viewDevice.php?id=" . $row['device_id'] . "' class='none'>
                    <button class='btn-icon view-btn'><i class='fas fa-eye'></i></button>
                </a>
                <a href='editDevice.php?id=" . $row['device_id'] . "' class='none'>
                    <button class='btn-icon edit-btn'><i class='fa-solid fa-pen'></i></button>
                </a>
                <button class='btn-icon delete-btn delete-trigger' data-id='" . $row['device_id'] . "' data-name='" . $row['device_name'] . "'><i class='fa-solid fa-trash'></i></button>
              </td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='7'>No devices found</td></tr>";
}

$conn->close();
