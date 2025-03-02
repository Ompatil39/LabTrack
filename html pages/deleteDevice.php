<?php
session_start();
if (isset($_SESSION["logged_in"]) !== true) {
    header("Location: login.php");
    exit;
}

include 'db.php';

// Set header to return JSON response
header('Content-Type: application/json');

// Initialize response array
$response = [
    'success' => false,
    'message' => 'Invalid request'
];

// Validate request method and check if device_id is provided
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['device_id'])) {
    $device_id = $_POST['device_id'];

    // Start transaction
    $conn->begin_transaction();

    try {
        // First, determine the device type to know which tables to clean up
        $deviceQuery = "SELECT device_type FROM devices WHERE device_id = ?";
        $stmt = $conn->prepare($deviceQuery);
        $stmt->bind_param("s", $device_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            throw new Exception("Device not found");
        }

        $device = $result->fetch_assoc();
        $device_type = $device['device_type'];

        // Delete from the specific device type table first
        $typeTableMap = [
            'PC' => 'pc_details',
            'Monitor' => 'monitors',
            'Keyboard' => 'keyboards',
            'Mouse' => 'mice',
            'CPU' => 'cpus',
            'Printer' => 'printers'
        ];

        if (array_key_exists($device_type, $typeTableMap)) {
            $table = $typeTableMap[$device_type];
            $deleteTypeQuery = "DELETE FROM $table WHERE device_id = ?";
            $stmtType = $conn->prepare($deleteTypeQuery);
            $stmtType->bind_param("s", $device_id);
            $stmtType->execute();
        }

        // Then delete from the main devices table
        $deleteDeviceQuery = "DELETE FROM devices WHERE device_id = ?";
        $stmtDevice = $conn->prepare($deleteDeviceQuery);
        $stmtDevice->bind_param("s", $device_id);
        $stmtDevice->execute();

        // If no rows were affected in the devices table, it means the device doesn't exist
        if ($stmtDevice->affected_rows === 0) {
            throw new Exception("Failed to delete device. Device may not exist.");
        }

        // Commit the transaction
        $conn->commit();

        $response = [
            'success' => true,
            'message' => 'Device deleted successfully'
        ];
    } catch (Exception $e) {
        // Rollback the transaction in case of error
        $conn->rollback();

        $response = [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

// Return the JSON response
echo json_encode($response);
$conn->close();
