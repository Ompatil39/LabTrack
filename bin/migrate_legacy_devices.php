<?php

declare(strict_types=1);

/**
 * Idempotent legacy device migration script.
 *
 * Migrates devices + type-specific detail tables => assets table,
 * groups PCs + peripherals into workstations, creates asset_assignments,
 * backfills lab_incharges, and links grievances to asset_id/workstation_id.
 *
 * Safe to run multiple times (all inserts use INSERT IGNORE or ON DUPLICATE KEY).
 *
 * Usage:  php bin/migrate_legacy_devices.php
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../config/config.php';

use LabTrack\AssetTagGenerator;

require_once __DIR__ . '/../includes/AssetTagGenerator.php';

$dbConfig = app_config()['db'];
$conn = new mysqli(
    $dbConfig['host'],
    $dbConfig['username'],
    $dbConfig['password'],
    $dbConfig['database'],
    $dbConfig['port']
);

if ($conn->connect_error) {
    fwrite(STDERR, 'DB connect failed: ' . $conn->connect_error . PHP_EOL);
    exit(1);
}

$conn->set_charset('utf8mb4');

echo '=== LabTrack Legacy Device Migration ===' . PHP_EOL;

// ----------------------------------------------------------------
// 1. Migrate devices => assets  (idempotent via legacy_device_id)
// ----------------------------------------------------------------
echo PHP_EOL . 'Step 1: Migrating devices to assets...' . PHP_EOL;

$devices = $conn->query('SELECT * FROM devices ORDER BY created_at ASC');
if (!$devices) {
    fwrite(STDERR, 'Could not query devices: ' . $conn->error . PHP_EOL);
    exit(1);
}

$deviceToAssetId = [];  // device_id => asset_id

while ($dev = $devices->fetch_assoc()) {
    $deviceId = $dev['device_id'];
    $deviceType = $dev['device_type']; // PC, Monitor, Keyboard, Mouse, CPU, Printer

    // Check if already migrated
    $existing = $conn->query("SELECT asset_id FROM assets WHERE legacy_device_id = '" . $conn->real_escape_string($deviceId) . "' LIMIT 1");
    if ($existing && $existing->num_rows > 0) {
        $row = $existing->fetch_assoc();
        $deviceToAssetId[$deviceId] = (int)$row['asset_id'];
        echo "  Skipping {$deviceId} (already in assets as #{$row['asset_id']})" . PHP_EOL;
        continue;
    }

    // Generate asset tag
    $assetTag = AssetTagGenerator::nextTag($conn, $deviceType);

    // Build specs JSON from type-specific details table
    $specs = [];
    $brandModel = $dev['device_name'];

    switch ($deviceType) {
        case 'PC':
            $detail = $conn->query("SELECT * FROM pc_details WHERE device_id = '" . $conn->real_escape_string($deviceId) . "' LIMIT 1");
            if ($detail && $detail->num_rows > 0) {
                $d = $detail->fetch_assoc();
                $specs = [
                    'processor' => $d['processor'],
                    'ram' => $d['ram'],
                    'storage' => $d['storage'],
                    'operating_system' => $d['operating_system'],
                    'ethernet_mac' => $d['ethernet_mac'],
                    'wifi_adapter' => $d['wifi_adapter'],
                    'ip_address' => $d['ip_address'],
                ];
            }
            break;

        case 'Monitor':
            $detail = $conn->query("SELECT * FROM monitors WHERE device_id = '" . $conn->real_escape_string($deviceId) . "' LIMIT 1");
            if ($detail && $detail->num_rows > 0) {
                $d = $detail->fetch_assoc();
                $brandModel = $d['brand_model'] ?? $brandModel;
                $specs = ['resolution' => $d['resolution']];
            }
            break;

        case 'Keyboard':
            $detail = $conn->query("SELECT * FROM keyboards WHERE device_id = '" . $conn->real_escape_string($deviceId) . "' LIMIT 1");
            if ($detail && $detail->num_rows > 0) {
                $d = $detail->fetch_assoc();
                $brandModel = $d['keyboard_name'] ?? $brandModel;
                $specs = ['keyboard_type' => $d['keyboard_type']];
            }
            break;

        case 'Mouse':
            $detail = $conn->query("SELECT * FROM mice WHERE device_id = '" . $conn->real_escape_string($deviceId) . "' LIMIT 1");
            if ($detail && $detail->num_rows > 0) {
                $d = $detail->fetch_assoc();
                $brandModel = $d['mouse_name'] ?? $brandModel;
                $specs = ['mouse_type' => $d['mouse_type']];
            }
            break;

        case 'CPU':
            $detail = $conn->query("SELECT * FROM cpus WHERE device_id = '" . $conn->real_escape_string($deviceId) . "' LIMIT 1");
            if ($detail && $detail->num_rows > 0) {
                $d = $detail->fetch_assoc();
                $brandModel = $d['case_model'] ?? $brandModel;
                $specs = ['power_supply' => $d['power_supply']];
            }
            break;

        case 'Printer':
            $detail = $conn->query("SELECT * FROM printers WHERE device_id = '" . $conn->real_escape_string($deviceId) . "' LIMIT 1");
            if ($detail && $detail->num_rows > 0) {
                $d = $detail->fetch_assoc();
                $brandModel = $d['printer_model'] ?? $brandModel;
                $specs = [
                    'printer_type' => $d['printer_type'],
                    'color_capability' => $d['color_capability'],
                    'connectivity' => $d['connectivity'],
                ];
            }
            break;
    }

    // Normalize status
    $statusMap = [
        'Active' => 'Active',
        'InActive' => 'Inactive',
        'Inactive' => 'Inactive',
        'Under Repair' => 'Under Repair',
        'In Repair' => 'Under Repair',
        'Faulty' => 'Faulty',
        'Retired' => 'Retired',
    ];
    $assetStatus = $statusMap[$dev['status']] ?? 'Active';

    $specsJson = !empty($specs) ? json_encode($specs, JSON_UNESCAPED_UNICODE) : null;
    $serialNumber = $dev['serial_number'] ?? null;

    $stmt = $conn->prepare(
        'INSERT INTO assets (asset_tag, asset_type, lab_id, brand_model, serial_number, status, specs, qr_payload, legacy_device_id, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE asset_id = LAST_INSERT_ID(asset_id)'
    );

    if (!$stmt) {
        fwrite(STDERR, "  Prepare failed for {$deviceId}: " . $conn->error . PHP_EOL);
        continue;
    }

    $qrPayload = $dev['qr_code'] ? substr($dev['qr_code'], 0, 255) : null;
    $stmt->bind_param(
        'sssssssssss',
        $assetTag,
        $deviceType,
        $dev['lab_id'],
        $brandModel,
        $serialNumber,
        $assetStatus,
        $specsJson,
        $qrPayload,
        $deviceId,
        $dev['created_at'],
        $dev['updated_at']
    );
    $stmt->execute();
    $assetId = (int)$conn->insert_id;

    if ($assetId === 0) {
        // ON DUPLICATE KEY hit — fetch existing
        $ex = $conn->query("SELECT asset_id FROM assets WHERE legacy_device_id = '" . $conn->real_escape_string($deviceId) . "' LIMIT 1");
        if ($ex && $ex->num_rows > 0) {
            $assetId = (int)$ex->fetch_assoc()['asset_id'];
        }
    }

    $stmt->close();
    $deviceToAssetId[$deviceId] = $assetId;
    echo "  Migrated {$deviceId} ({$deviceType}) => asset #{$assetId} tag:{$assetTag}" . PHP_EOL;
}

echo '  Done. ' . count($deviceToAssetId) . ' devices in assets table.' . PHP_EOL;

// ----------------------------------------------------------------
// 2. Group PCs and peripherals into workstations
// ----------------------------------------------------------------
echo PHP_EOL . 'Step 2: Creating workstations from PC assets...' . PHP_EOL;

$assetToWorkstation = [];  // asset_id => workstation_id

// Fetch all PC-type assets grouped by lab
$pcs = $conn->query("SELECT a.asset_id, a.lab_id, a.legacy_device_id, a.created_at FROM assets a WHERE a.asset_type = 'PC' ORDER BY a.lab_id, a.created_at");

if ($pcs) {
    $labSeatCounters = [];

    while ($pc = $pcs->fetch_assoc()) {
        $labId = $pc['lab_id'];
        $assetId = (int)$pc['asset_id'];

        // Check if a workstation already exists for this PC asset
        $existingWs = $conn->query(
            "SELECT aa.workstation_id FROM asset_assignments aa WHERE aa.asset_id = {$assetId} AND aa.removed_at IS NULL LIMIT 1"
        );
        if ($existingWs && $existingWs->num_rows > 0) {
            $wsId = (int)$existingWs->fetch_assoc()['workstation_id'];
            $assetToWorkstation[$assetId] = $wsId;
            echo "  Skipping PC asset#{$assetId} (already assigned to workstation#{$wsId})" . PHP_EOL;
            continue;
        }

        // Generate seat code  A-01, A-02, ...
        if (!isset($labSeatCounters[$labId])) {
            // Count existing workstations in this lab to continue numbering
            $cnt = $conn->query("SELECT COUNT(*) as c FROM workstations WHERE lab_id = '" . $conn->real_escape_string($labId) . "'");
            $labSeatCounters[$labId] = $cnt ? (int)$cnt->fetch_assoc()['c'] : 0;
        }
        $labSeatCounters[$labId]++;
        $seatCode = 'S-' . str_pad((string)$labSeatCounters[$labId], 2, '0', STR_PAD_LEFT);

        // Create workstation
        $ws = $conn->prepare('INSERT INTO workstations (lab_id, seat_code, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE workstation_id = LAST_INSERT_ID(workstation_id)');
        if (!$ws) {
            fwrite(STDERR, '  workstations prepare failed: ' . $conn->error . PHP_EOL);
            continue;
        }
        $wsStatus = 'Active';
        $ws->bind_param('sssss', $labId, $seatCode, $wsStatus, $pc['created_at'], $pc['created_at']);
        $ws->execute();
        $workstationId = (int)$conn->insert_id;
        $ws->close();

        if ($workstationId === 0) {
            $ex = $conn->query("SELECT workstation_id FROM workstations WHERE lab_id = '" . $conn->real_escape_string($labId) . "' AND seat_code = '" . $conn->real_escape_string($seatCode) . "' LIMIT 1");
            $workstationId = $ex ? (int)$ex->fetch_assoc()['workstation_id'] : 0;
        }

        $assetToWorkstation[$assetId] = $workstationId;
        echo "  Created workstation#{$workstationId} lab:{$labId} seat:{$seatCode} for PC asset#{$assetId}" . PHP_EOL;
    }
}

// ----------------------------------------------------------------
// 3. Build asset_assignments
// ----------------------------------------------------------------
echo PHP_EOL . 'Step 3: Creating asset_assignments...' . PHP_EOL;

// Map device_ids to their lab_id and create_at for grouping
$deviceMeta = [];
$devResult = $conn->query('SELECT device_id, lab_id, device_type, created_at FROM devices ORDER BY created_at');
while ($row = $devResult->fetch_assoc()) {
    $deviceMeta[$row['device_id']] = $row;
}

// For each workstation we created, find its PC asset, then find matching peripherals in same lab
foreach ($assetToWorkstation as $pcAssetId => $workstationId) {
    if ($workstationId === 0) {
        continue;
    }

    // Get PC device info
    $pcAsset = $conn->query("SELECT * FROM assets WHERE asset_id = {$pcAssetId} LIMIT 1")->fetch_assoc();
    if (!$pcAsset) {
        continue;
    }

    $labId = $pcAsset['lab_id'];
    $pcCreatedAt = $pcAsset['created_at'];

    // Assign PC as root asset at workstation
    assignAssetToWorkstation($conn, $pcAssetId, $workstationId, null, $pcCreatedAt);

    // Find companion peripherals in same lab within a loose time window (legacy data may not be tightly ordered)
    // We match the next available unassigned Monitor, Keyboard, Mouse, CPU in same lab
    foreach (['Monitor', 'Keyboard', 'Mouse', 'CPU'] as $peripheralType) {
        // Find an unassigned peripheral asset in this lab
        $periResult = $conn->query(
            "SELECT a.asset_id FROM assets a
             LEFT JOIN asset_assignments aa ON aa.asset_id = a.asset_id AND aa.removed_at IS NULL
             WHERE a.asset_type = '{$peripheralType}'
               AND a.lab_id = '" . $conn->real_escape_string($labId) . "'
               AND aa.assignment_id IS NULL
             ORDER BY a.created_at ASC
             LIMIT 1"
        );

        if ($periResult && $periResult->num_rows > 0) {
            $periAssetId = (int)$periResult->fetch_assoc()['asset_id'];
            assignAssetToWorkstation($conn, $periAssetId, $workstationId, $pcAssetId, $pcCreatedAt);
            echo "  Assigned {$peripheralType} asset#{$periAssetId} to workstation#{$workstationId} (parent PC#{$pcAssetId})" . PHP_EOL;
        }
    }
}

function assignAssetToWorkstation(\mysqli $conn, int $assetId, int $workstationId, ?int $parentAssetId, string $installedAt): void
{
    // Skip if already assigned
    $ex = $conn->query("SELECT assignment_id FROM asset_assignments WHERE asset_id = {$assetId} AND removed_at IS NULL LIMIT 1");
    if ($ex && $ex->num_rows > 0) {
        return;
    }

    $stmt = $conn->prepare(
        'INSERT INTO asset_assignments (asset_id, workstation_id, parent_asset_id, installed_at) VALUES (?, ?, ?, ?)'
    );
    if (!$stmt) {
        fwrite(STDERR, '  asset_assignments prepare failed: ' . $conn->error . PHP_EOL);
        return;
    }
    $stmt->bind_param('iiis', $assetId, $workstationId, $parentAssetId, $installedAt);
    $stmt->execute();
    $stmt->close();
}

echo '  Done.' . PHP_EOL;

// ----------------------------------------------------------------
// 4. Backfill lab_incharges and labs.incharge_uid
// ----------------------------------------------------------------
echo PHP_EOL . 'Step 4: Backfilling lab_incharges from labs.lab_incharge...' . PHP_EOL;

$labs = $conn->query('SELECT lab_id, lab_incharge FROM labs');
while ($lab = $labs->fetch_assoc()) {
    $labId = $lab['lab_id'];
    $inchargeName = $lab['lab_incharge'];

    // Try to find matching user by username, full_name, or similar
    $userResult = $conn->query(
        "SELECT uid FROM users WHERE username = '" . $conn->real_escape_string($inchargeName) . "'
         OR full_name = '" . $conn->real_escape_string($inchargeName) . "'
         OR LOWER(username) = LOWER('" . $conn->real_escape_string($inchargeName) . "')
         LIMIT 1"
    );

    if ($userResult && $userResult->num_rows > 0) {
        $uid = (int)$userResult->fetch_assoc()['uid'];

        // Update labs.incharge_uid
        $conn->query("UPDATE labs SET incharge_uid = {$uid} WHERE lab_id = '" . $conn->real_escape_string($labId) . "'");

        // Insert into lab_incharges
        $conn->query("INSERT IGNORE INTO lab_incharges (lab_id, uid) VALUES ('" . $conn->real_escape_string($labId) . "', {$uid})");
        echo "  Linked lab '{$labId}' => user uid:{$uid} ({$inchargeName})" . PHP_EOL;
    } else {
        echo "  Could not find user for '{$inchargeName}' (lab: {$labId})" . PHP_EOL;
    }
}

// ----------------------------------------------------------------
// 5. Backfill grievance.asset_id and grievance.workstation_id
// ----------------------------------------------------------------
echo PHP_EOL . 'Step 5: Backfilling grievance.asset_id / workstation_id...' . PHP_EOL;

$grievances = $conn->query('SELECT grievance_id, device_id FROM grievances WHERE device_id IS NOT NULL AND (asset_id IS NULL OR workstation_id IS NULL)');
if ($grievances) {
    while ($g = $grievances->fetch_assoc()) {
        $deviceId = $g['device_id'];
        $grievanceId = (int)$g['grievance_id'];

        if (!isset($deviceToAssetId[$deviceId])) {
            continue;
        }

        $assetId = $deviceToAssetId[$deviceId];

        // Find current (or latest) workstation assignment
        $wsResult = $conn->query(
            "SELECT workstation_id FROM asset_assignments WHERE asset_id = {$assetId} ORDER BY installed_at DESC LIMIT 1"
        );
        $wsId = null;
        if ($wsResult && $wsResult->num_rows > 0) {
            $wsId = (int)$wsResult->fetch_assoc()['workstation_id'];
        }

        $stmt = $conn->prepare('UPDATE grievances SET asset_id = ?, workstation_id = ? WHERE grievance_id = ?');
        if ($stmt) {
            $stmt->bind_param('iii', $assetId, $wsId, $grievanceId);
            $stmt->execute();
            $stmt->close();
            echo "  Grievance#{$grievanceId}: asset_id={$assetId}, workstation_id=" . ($wsId ?? 'NULL') . PHP_EOL;
        }
    }
}

echo PHP_EOL . '=== Legacy migration complete! ===' . PHP_EOL;
