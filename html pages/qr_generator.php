<?php
require_once 'db.php';
require_once __DIR__ . '/../vendor/autoload.php';

use chillerlan\QRCode\{QRCode, QROptions};

// Function to generate QR code for a device
function generateQRCode($device_id, $device_name, $lab_id)
{
    // Create QR code data with device information
    $qr_data = json_encode([
        'device_id' => $device_id,
        'device_name' => $device_name,
        'lab_id' => $lab_id,
        'timestamp' => time()
    ]);

    try {
        // Check if GD extension is available
        if (!extension_loaded('gd')) {
            // Fallback: Use a reliable online QR code service
            $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($qr_data);
            return $qr_url;
        }

        // Configure QR code options for local generation
        $options = new QROptions([
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'eccLevel'   => QRCode::ECC_L,
            'imageBase64' => true,
            'scale'      => 5,
            'imageTransparent' => false,
            'bgColor'    => [255, 255, 255],
            'moduleValues' => [
                // finder
                1024 => [0, 0, 0], // dark (true)
                512  => [255, 255, 255], // light (false), white is the transparency color and is ignored
                // alignment
                256  => [0, 0, 0],
                128  => [255, 255, 255],
                // timing
                64   => [0, 0, 0],
                32   => [255, 255, 255],
                // format
                16   => [0, 0, 0],
                8    => [255, 255, 255],
                // version
                4    => [0, 0, 0],
                2    => [255, 255, 255],
                // data
                1    => [0, 0, 0],
            ],
        ]);

        // Generate QR code
        $qrcode = new QRCode($options);
        $qr_code_data_uri = $qrcode->render($qr_data); // Returns data URI

        return $qr_code_data_uri;
    } catch (Exception $e) {
        // Fallback to online service if local generation fails
        $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($qr_data);
        return $qr_url;
    }
}

// Function to update device QR code in database
function updateDeviceQRCode($conn, $device_id, $qr_code_data_uri)
{
    $query = "UPDATE devices SET qr_code = ? WHERE device_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $qr_code_data_uri, $device_id);
    return $stmt->execute();
}

// Function to generate QR codes for all existing devices
function generateQRForAllDevices($conn)
{
    $query = "SELECT device_id, device_name, lab_id FROM devices WHERE qr_code IS NULL OR qr_code = ''";
    $result = $conn->query($query);

    $updated_count = 0;
    while ($row = $result->fetch_assoc()) {
        $qr_code_data_uri = generateQRCode($row['device_id'], $row['device_name'], $row['lab_id']);
        if (updateDeviceQRCode($conn, $row['device_id'], $qr_code_data_uri)) {
            $updated_count++;
        }
    }

    return $updated_count;
}

// Function to get device by QR code data
function getDeviceByQRData($conn, $qr_data)
{
    $data = json_decode($qr_data, true);

    if (!$data || !isset($data['device_id'])) {
        return null;
    }

    $device_id = $data['device_id'];

    $query = "SELECT d.device_id, d.device_name, d.device_type, d.status, d.lab_id, l.lab_name, 
                     CONCAT(l.building, ' - Room ', l.room_number) AS lab_location
              FROM devices d
              LEFT JOIN labs l ON d.lab_id = l.lab_id
              WHERE d.device_id = ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $device_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }

    return null;
}

// Handle AJAX requests
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'generate_all_qr':
                $count = generateQRForAllDevices($conn);
                echo json_encode(['success' => true, 'updated_count' => $count]);
                break;

            case 'generate_qr':
                if (isset($_POST['device_id'])) {
                    $device_id = $_POST['device_id'];

                    // Get device info
                    $query = "SELECT device_id, device_name, lab_id FROM devices WHERE device_id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("s", $device_id);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows > 0) {
                        $device = $result->fetch_assoc();
                        $qr_code_data_uri = generateQRCode($device['device_id'], $device['device_name'], $device['lab_id']);

                        if (updateDeviceQRCode($conn, $device_id, $qr_code_data_uri)) {
                            echo json_encode(['success' => true, 'qr_code_url' => $qr_code_data_uri]);
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Failed to update QR code in database']);
                        }
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Device not found']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Device ID required']);
                }
                break;

            case 'lookup_device':
                if (isset($_POST['qr_data'])) {
                    $device = getDeviceByQRData($conn, $_POST['qr_data']);
                    if ($device) {
                        echo json_encode(['success' => true, 'device' => $device]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Device not found']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'QR data required']);
                }
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'No action specified']);
    }

    $conn->close();
    exit;
}
