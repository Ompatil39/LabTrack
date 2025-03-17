<?php
include 'db.php';

// Get parameters from request
$lab_id = isset($_GET['lab_id']) ? $_GET['lab_id'] : '';
$device_type = isset($_GET['device_type']) ? $_GET['device_type'] : '';

// Prepare response array
$devices = [];

if ($lab_id && $device_type) {
    // Prepare SQL statement to get devices
    $sql = "SELECT device_id, device_name FROM devices WHERE lab_id = ? AND device_type = ? AND status = 'Active'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $lab_id, $device_type);
    $stmt->execute();
    $result = $stmt->get_result();

    // Fetch devices
    while ($row = $result->fetch_assoc()) {
        $devices[] = $row;
    }

    $stmt->close();
}

// Close connection
$conn->close();

// Return devices as JSON
header('Content-Type: application/json');
echo json_encode($devices);
