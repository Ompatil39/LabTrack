<?php

declare(strict_types=1);

/**
 * LabTrack Phase 2 Seed Script
 *
 * Seeds: users, labs, workstations, assets (with JSON specs),
 *        asset_assignments, lab_incharges, historical swaps, and grievances.
 *
 * Usage:  php bin/seed.php
 *         php bin/seed.php --reset   (drops all rows first)
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/AssetTagGenerator.php';
require_once __DIR__ . '/../includes/WorkstationService.php';

use LabTrack\AssetTagGenerator;
use LabTrack\WorkstationService;

$reset = in_array('--reset', $argv ?? [], true);

$dbConfig = app_config()['db'];
$conn = new mysqli(
    $dbConfig['host'],
    $dbConfig['username'],
    $dbConfig['password'],
    $dbConfig['database'],
    $dbConfig['port']
);

if ($conn->connect_error) {
    fwrite(STDERR, "DB connect failed: " . $conn->connect_error . PHP_EOL);
    exit(1);
}
$conn->set_charset('utf8mb4');

if ($reset) {
    echo "Resetting tables..." . PHP_EOL;
    $conn->query('SET FOREIGN_KEY_CHECKS = 0');
    foreach (['asset_assignments','asset_tag_sequences','assets','workstations','lab_incharges','grievances','devices','pc_details','monitors','keyboards','mice','cpus','printers','labs','users'] as $t) {
        $conn->query("DELETE FROM `{$t}`");
    }
    $conn->query('SET FOREIGN_KEY_CHECKS = 1');
    echo "Reset complete." . PHP_EOL;
}

// ----------------------------------------------------------------
// Users
// ----------------------------------------------------------------
echo "Seeding users..." . PHP_EOL;

$users = [
    ['user_id' => 'USR-001', 'username' => 'admin',        'full_name' => 'Admin User',        'email' => 'admin@labtrack.local',      'user_type' => 'Admin'],
    ['user_id' => 'USR-002', 'username' => 'prof_sharma',  'full_name' => 'Prof. Sharma',       'email' => 'sharma@labtrack.local',     'user_type' => 'Incharge'],
    ['user_id' => 'USR-003', 'username' => 'prof_mehta',   'full_name' => 'Prof. Mehta',        'email' => 'mehta@labtrack.local',      'user_type' => 'Incharge'],
    ['user_id' => 'USR-004', 'username' => 'prof_joshi',   'full_name' => 'Prof. Joshi',        'email' => 'joshi@labtrack.local',      'user_type' => 'Incharge'],
];

$pwHash = password_hash('admin1234', PASSWORD_BCRYPT);
foreach ($users as $u) {
    $stmt = $conn->prepare(
        'INSERT INTO users (user_id, username, password, email, user_type, full_name)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE full_name = VALUES(full_name)'
    );
    $stmt->bind_param('ssssss', $u['user_id'], $u['username'], $pwHash, $u['email'], $u['user_type'], $u['full_name']);
    $stmt->execute();
    $stmt->close();
}
echo "  Seeded " . count($users) . " users." . PHP_EOL;

// Fetch user UIDs (auto-increment surrogate key added in Phase 2 migration)
$uidMap = []; // user_id => uid
$uidResult = $conn->query("SELECT user_id, uid FROM users");
while ($row = $uidResult->fetch_assoc()) {
    if ($row['uid'] !== null) {
        $uidMap[$row['user_id']] = (int)$row['uid'];
    }
}

// ----------------------------------------------------------------
// Labs
// ----------------------------------------------------------------
echo "Seeding labs..." . PHP_EOL;

$labs = [
    ['lab_id' => 'LAB-101', 'lab_name' => 'Computer Science Lab A', 'department' => 'Computer Science', 'lab_incharge' => 'prof_sharma',  'room_number' => '101', 'building' => 'Block A', 'room_capacity' => 30, 'establishment_date' => '2020-06-01'],
    ['lab_id' => 'LAB-102', 'lab_name' => 'Computer Science Lab B', 'department' => 'Computer Science', 'lab_incharge' => 'prof_mehta',   'room_number' => '102', 'building' => 'Block A', 'room_capacity' => 25, 'establishment_date' => '2021-01-15'],
    ['lab_id' => 'LAB-201', 'lab_name' => 'IT Lab',                 'department' => 'Information Tech', 'lab_incharge' => 'prof_joshi',   'room_number' => '201', 'building' => 'Block B', 'room_capacity' => 20, 'establishment_date' => '2019-08-20'],
];

foreach ($labs as $l) {
    $stmt = $conn->prepare(
        'INSERT INTO labs (lab_id, lab_name, status, department, lab_incharge, establishment_date, room_capacity, building, room_number)
         VALUES (?, ?, "Active", ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE lab_name = VALUES(lab_name)'
    );
    $stmt->bind_param('ssssssss', $l['lab_id'], $l['lab_name'], $l['department'], $l['lab_incharge'], $l['establishment_date'], $l['room_capacity'], $l['building'], $l['room_number']);
    $stmt->execute();
    $stmt->close();
}
echo "  Seeded " . count($labs) . " labs." . PHP_EOL;

// ----------------------------------------------------------------
// lab_incharges backfill from labs.lab_incharge
// ----------------------------------------------------------------
echo "Seeding lab_incharges..." . PHP_EOL;

$labInchargeMap = [
    'LAB-101' => 'USR-002',
    'LAB-102' => 'USR-003',
    'LAB-201' => 'USR-004',
];

foreach ($labInchargeMap as $labId => $userId) {
    if (!isset($uidMap[$userId])) {
        continue;
    }
    $uid = $uidMap[$userId];
    $conn->query("UPDATE labs SET incharge_uid = {$uid} WHERE lab_id = '" . $conn->real_escape_string($labId) . "'");
    $conn->query("INSERT IGNORE INTO lab_incharges (lab_id, uid) VALUES ('" . $conn->real_escape_string($labId) . "', {$uid})");
    echo "  Linked {$labId} => uid:{$uid}" . PHP_EOL;
}

// ----------------------------------------------------------------
// Workstations + Assets (Phase 2 flow via WorkstationService)
// ----------------------------------------------------------------
echo "Seeding workstations and assets..." . PHP_EOL;

$labWorkstationCount = ['LAB-101' => 10, 'LAB-102' => 10, 'LAB-201' => 10];

$printers = [
    ['brand_model' => 'HP LaserJet Pro M404n', 'printer_type' => 'Laser',  'color_capability' => 'Black & White', 'connectivity' => 'Network'],
    ['brand_model' => 'Canon PIXMA G3010',     'printer_type' => 'Inkjet', 'color_capability' => 'Color',         'connectivity' => 'Wireless'],
    ['brand_model' => 'Epson L3150',           'printer_type' => 'Inkjet', 'color_capability' => 'Color',         'connectivity' => 'USB'],
];

$printerIndex = 0;

foreach ($labWorkstationCount as $labId => $count) {
    $prefix = str_replace('LAB-', '', $labId);

    for ($i = 1; $i <= $count; $i++) {
        $seatCode = 'A-' . str_pad((string)$i, 2, '0', STR_PAD_LEFT);

        // Skip if seat already exists
        $ex = $conn->query("SELECT workstation_id FROM workstations WHERE lab_id = '{$labId}' AND seat_code = '{$seatCode}' LIMIT 1");
        if ($ex && $ex->num_rows > 0) {
            $wsId = (int)$ex->fetch_assoc()['workstation_id'];
            echo "  Skipping {$labId}/{$seatCode} (workstation#{$wsId} exists)" . PHP_EOL;
            continue;
        }

        $ramOptions  = ['4GB DDR4', '8GB DDR4', '16GB DDR4'];
        $hddOptions  = ['256GB SSD', '512GB SSD', '1TB HDD', '256GB SSD + 1TB HDD'];
        $cpuOptions  = ['Intel Core i3-10100', 'Intel Core i5-10400', 'Intel Core i7-10700', 'AMD Ryzen 5 3600'];
        $osOptions   = ['Windows 10 Pro', 'Ubuntu 22.04 LTS', 'Windows 11 Pro'];
        $resolutions = ['1920x1080', '2560x1440', '1366x768'];
        $kbTypes     = ['Wired', 'Mechanical', 'Membrane'];
        $mouseTypes  = ['Wired', 'Wireless'];
        $psuOptions  = ['450W', '550W', '650W', '750W'];

        $cpuModel = $cpuOptions[array_rand($cpuOptions)];
        $ram = $ramOptions[array_rand($ramOptions)];
        $storage = $hddOptions[array_rand($hddOptions)];
        $resolution = $resolutions[array_rand($resolutions)];

        try {
            $result = WorkstationService::createWorkstationWithParts($conn, [
                'lab_id'    => $labId,
                'seat_code' => $seatCode,
                'hostname'  => "ws-{$prefix}-" . str_pad((string)$i, 2, '0', STR_PAD_LEFT),
                'status'    => 'Active',
            ], [
                [
                    'asset_type'  => 'PC',
                    'brand_model' => 'Dell OptiPlex 3080',
                    'status'      => 'Active',
                    'specs'       => [
                        'processor'        => $cpuModel,
                        'ram'              => $ram,
                        'storage'          => $storage,
                        'operating_system' => $osOptions[array_rand($osOptions)],
                        'ethernet_mac'     => sprintf('AA:BB:CC:%02X:%02X:%02X', random_int(0,255), random_int(0,255), random_int(0,255)),
                        'ip_address'       => '192.168.' . random_int(1,5) . '.' . random_int(10, 250),
                    ],
                ],
                [
                    'asset_type'  => 'Monitor',
                    'brand_model' => 'LG 22MK600M',
                    'status'      => 'Active',
                    'specs'       => ['resolution' => $resolution, 'panel_type' => 'IPS'],
                ],
                [
                    'asset_type'  => 'Keyboard',
                    'brand_model' => 'Logitech K120',
                    'status'      => 'Active',
                    'specs'       => ['keyboard_type' => $kbTypes[array_rand($kbTypes)], 'layout' => 'QWERTY'],
                ],
                [
                    'asset_type'  => 'Mouse',
                    'brand_model' => 'Logitech M100',
                    'status'      => 'Active',
                    'specs'       => ['mouse_type' => $mouseTypes[array_rand($mouseTypes)], 'dpi' => '1000'],
                ],
                [
                    'asset_type'  => 'CPU',
                    'brand_model' => 'Dell OptiPlex Tower',
                    'status'      => 'Active',
                    'specs'       => ['processor' => $cpuModel, 'power_supply' => $psuOptions[array_rand($psuOptions)]],
                ],
            ]);
            echo "  Created {$labId}/{$seatCode} workstation#{$result['workstation_id']} with " . count($result['assets']) . " assets" . PHP_EOL;
        } catch (\Throwable $e) {
            echo "  ERROR creating {$labId}/{$seatCode}: " . $e->getMessage() . PHP_EOL;
        }
    }

    // Add 2 printers per lab
    for ($p = 0; $p < 2; $p++) {
        $pd = $printers[$printerIndex % count($printers)];
        $printerIndex++;
        try {
            $pTag = AssetTagGenerator::nextTag($conn, 'Printer');
            $specsJson = json_encode([
                'printer_type'      => $pd['printer_type'],
                'color_capability'  => $pd['color_capability'],
                'connectivity'      => $pd['connectivity'],
            ]);
            $pStatus = 'Active';
            $stmt = $conn->prepare('INSERT INTO assets (asset_tag, asset_type, lab_id, brand_model, status, specs) VALUES (?, "Printer", ?, ?, ?, ?)');
            $stmt->bind_param('sssss', $pTag, $labId, $pd['brand_model'], $pStatus, $specsJson);
            $stmt->execute();
            $stmt->close();
            echo "  Added printer {$pTag} to {$labId}" . PHP_EOL;
        } catch (\Throwable $e) {
            echo "  ERROR adding printer to {$labId}: " . $e->getMessage() . PHP_EOL;
        }
    }
}

// ----------------------------------------------------------------
// Historical Part Swaps (for demo data)
// ----------------------------------------------------------------
echo "Seeding historical part swaps..." . PHP_EOL;

// Find 2 mouse assets from LAB-101 — close one assignment, insert a swap
$mouseAssets = $conn->query(
    "SELECT a.asset_id, aa.assignment_id, aa.workstation_id
     FROM assets a
     JOIN asset_assignments aa ON aa.asset_id = a.asset_id AND aa.removed_at IS NULL
     WHERE a.asset_type = 'Mouse' AND a.lab_id = 'LAB-101'
     LIMIT 2"
);

$mouseRows = $mouseAssets ? $mouseAssets->fetch_all(MYSQLI_ASSOC) : [];

if (count($mouseRows) >= 2) {
    // Simulate: first mouse is faulty, swapped with second mouse's spare
    // For this demo, we create a spare mouse, close the first, assign the spare
    try {
        $spareTag = AssetTagGenerator::nextTag($conn, 'Mouse');
        $spareStatus = 'Active';
        $spareSpecs  = json_encode(['mouse_type' => 'Wired', 'dpi' => '1200']);
        $conn->query("INSERT INTO assets (asset_tag, asset_type, lab_id, brand_model, status, specs) VALUES ('{$spareTag}', 'Mouse', 'LAB-101', 'HP HID Mouse (Spare)', 'Inactive', '{$spareSpecs}')");
        $spareAssetId = (int)$conn->insert_id;

        // Now swap
        $oldAssetId     = (int)$mouseRows[0]['asset_id'];
        $workstationId  = (int)$mouseRows[0]['workstation_id'];
        WorkstationService::swapWorkstationPart($conn, $workstationId, $oldAssetId, $spareAssetId, 'Left click button failed — replaced with spare', 'Faulty');
        echo "  Historical swap: Mouse#{$oldAssetId} at workstation#{$workstationId} replaced by spare#{$spareAssetId}" . PHP_EOL;
    } catch (\Throwable $e) {
        echo "  Swap demo error: " . $e->getMessage() . PHP_EOL;
    }
}

// ----------------------------------------------------------------
// Grievances linked to assets and workstations
// ----------------------------------------------------------------
echo "Seeding grievances..." . PHP_EOL;

$firstWs = $conn->query("SELECT workstation_id, lab_id FROM workstations WHERE lab_id = 'LAB-101' LIMIT 1")->fetch_assoc();
if ($firstWs) {
    $wsId  = (int)$firstWs['workstation_id'];
    $labId = $firstWs['lab_id'];

    // Get a mouse asset at this workstation
    $mouseAtWs = $conn->query(
        "SELECT a.asset_id FROM assets a
         JOIN asset_assignments aa ON aa.asset_id = a.asset_id AND aa.removed_at IS NULL
         WHERE aa.workstation_id = {$wsId} AND a.asset_type = 'Mouse' LIMIT 1"
    );
    $assetId = $mouseAtWs && $mouseAtWs->num_rows > 0 ? (int)$mouseAtWs->fetch_assoc()['asset_id'] : null;

    $stmt = $conn->prepare(
        'INSERT INTO grievances (submitted_by, stud_enrollment, class, lab_id, device_category, device_name, submission_date, status, stud_email, grievance_desc, asset_id, workstation_id)
         VALUES (?, ?, ?, ?, ?, ?, NOW(), "Submitted", ?, ?, ?, ?)'
    );
    $submitter   = 'John Doe';
    $enrollment  = 'EN2024001';
    $class       = 'CS-3A';
    $category    = 'Mouse';
    $deviceName  = 'Logitech M100';
    $email       = 'john.doe@student.local';
    $desc        = 'The mouse scroll wheel is not working properly at Seat A-01. Scrolling is erratic and skips pages.';
    $stmt->bind_param('sssssssssii', $submitter, $enrollment, $class, $labId, $category, $deviceName, $email, $desc, $assetId, $wsId);
    $stmt->execute();
    $stmt->close();
    echo "  Grievance #1 seeded for workstation#{$wsId} / asset#{$assetId}" . PHP_EOL;

    // Second grievance — PC issue
    $pcAtWs = $conn->query(
        "SELECT a.asset_id FROM assets a
         JOIN asset_assignments aa ON aa.asset_id = a.asset_id AND aa.removed_at IS NULL
         WHERE aa.workstation_id = {$wsId} AND a.asset_type = 'PC' LIMIT 1"
    );
    $pcAssetId = $pcAtWs && $pcAtWs->num_rows > 0 ? (int)$pcAtWs->fetch_assoc()['asset_id'] : null;

    $stmt2 = $conn->prepare(
        'INSERT INTO grievances (submitted_by, stud_enrollment, class, lab_id, device_category, device_name, submission_date, status, stud_email, grievance_desc, asset_id, workstation_id)
         VALUES (?, ?, ?, ?, ?, ?, NOW(), "In Progress", ?, ?, ?, ?)'
    );
    $submitter2  = 'Jane Smith';
    $enrollment2 = 'EN2024002';
    $class2      = 'CS-3B';
    $category2   = 'PC';
    $deviceName2 = 'Dell OptiPlex 3080';
    $email2      = 'jane.smith@student.local';
    $desc2       = 'PC at Seat A-01 freezes after 20 minutes of use. Blue screen error observed twice this week.';
    $stmt2->bind_param('sssssssssii', $submitter2, $enrollment2, $class2, $labId, $category2, $deviceName2, $email2, $desc2, $pcAssetId, $wsId);
    $stmt2->execute();
    $stmt2->close();
    echo "  Grievance #2 seeded for workstation#{$wsId} / PC asset#{$pcAssetId}" . PHP_EOL;
}

echo PHP_EOL . "=== Phase 2 Seed complete! ===" . PHP_EOL;
