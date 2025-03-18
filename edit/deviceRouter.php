<?php
/**
 * Device Router
 * 
 * Redirects to specific edit pages based on device ID prefixes:
 * - MON-YYYY-#### -> editMonitor.php
 * - PC-YYYY-####  -> editPC.php
 * - KEY-YYYY-#### -> editKeyboard.php
 * - MOU-YYYY-#### -> editMouse.php
 * - CPU-YYYY-#### -> editCPU.php
 * etc.
 */

// Get the device ID from the query parameter
$deviceId = isset($_GET['id']) ? $_GET['id'] : '';

// Validate device ID format (basic validation)
if (empty($deviceId) || !preg_match('/^([A-Z]{2,3})-\d{4}-\d{4}$/', $deviceId, $matches)) {
    // Invalid ID format
    header("HTTP/1.1 400 Bad Request");
    echo "Invalid device ID format. Expected format: XXX-YYYY-####";
    exit;
}

// Extract the device type prefix
$deviceType = $matches[1];

// Map device type prefixes to their respective edit pages
$editPages = [
    'MON' => 'editMonitor.php',
    'PC'  => 'editPC.php',
    'KEY' => 'editKeyboard.php',
    'MOU' => 'editMouse.php',
    'CPU' => 'editCPU.php',
    'PRT' => 'editPrinter.php'
    // Add more device types as needed
];

// Check if the device type has a corresponding edit page
if (isset($editPages[$deviceType])) {
    // Redirect to the appropriate edit page with the device ID
    header("Location: " . $editPages[$deviceType] . "?id=" . urlencode($deviceId));
    exit;
} else {
    // Unknown device type
    header("HTTP/1.1 404 Not Found");
    echo "Unknown device type: " . htmlspecialchars($deviceType);
    exit;
}
?>