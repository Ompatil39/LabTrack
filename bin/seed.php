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
    fwrite(STDERR, 'DB connect failed: ' . $conn->connect_error . PHP_EOL);
    exit(1);
}
$conn->set_charset('utf8mb4');

if ($reset) {
    echo 'Resetting tables...' . PHP_EOL;
    $conn->query('SET FOREIGN_KEY_CHECKS = 0');
    foreach (['audit_logs','ticket_comments','ticket_status_history','notification_jobs','maintenance_records','sla_policies','asset_assignments','asset_tag_sequences','assets','workstations','lab_incharges','grievances','devices','pc_details','monitors','keyboards','mice','cpus','printers','labs','users'] as $t) {
        $conn->query("DELETE FROM `{$t}`");
    }
    $conn->query('SET FOREIGN_KEY_CHECKS = 1');
    echo 'Reset complete.' . PHP_EOL;
}

// ----------------------------------------------------------------
// Users
// ----------------------------------------------------------------
echo 'Seeding users...' . PHP_EOL;

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
echo '  Seeded ' . count($users) . ' users.' . PHP_EOL;

// Fetch user UIDs (auto-increment surrogate key added in Phase 2 migration)
$uidMap = []; // user_id => uid
$uidResult = $conn->query('SELECT user_id, uid FROM users');
while ($row = $uidResult->fetch_assoc()) {
    if ($row['uid'] !== null) {
        $uidMap[$row['user_id']] = (int)$row['uid'];
    }
}

// ----------------------------------------------------------------
// Labs
// ----------------------------------------------------------------
echo 'Seeding labs...' . PHP_EOL;

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
echo '  Seeded ' . count($labs) . ' labs.' . PHP_EOL;

// ----------------------------------------------------------------
// lab_incharges backfill from labs.lab_incharge
// ----------------------------------------------------------------
echo 'Seeding lab_incharges...' . PHP_EOL;

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
echo 'Seeding workstations and assets...' . PHP_EOL;

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

        $ramOptions = ['4GB DDR4', '8GB DDR4', '16GB DDR4'];
        $hddOptions = ['256GB SSD', '512GB SSD', '1TB HDD', '256GB SSD + 1TB HDD'];
        $cpuOptions = ['Intel Core i3-10100', 'Intel Core i5-10400', 'Intel Core i7-10700', 'AMD Ryzen 5 3600'];
        $osOptions = ['Windows 10 Pro', 'Ubuntu 22.04 LTS', 'Windows 11 Pro'];
        $resolutions = ['1920x1080', '2560x1440', '1366x768'];
        $kbTypes = ['Wired', 'Mechanical', 'Membrane'];
        $mouseTypes = ['Wired', 'Wireless'];
        $psuOptions = ['450W', '550W', '650W', '750W'];

        $cpuModel = $cpuOptions[array_rand($cpuOptions)];
        $ram = $ramOptions[array_rand($ramOptions)];
        $storage = $hddOptions[array_rand($hddOptions)];
        $resolution = $resolutions[array_rand($resolutions)];

        try {
            $result = WorkstationService::createWorkstationWithParts($conn, [
                'lab_id' => $labId,
                'seat_code' => $seatCode,
                'hostname' => "ws-{$prefix}-" . str_pad((string)$i, 2, '0', STR_PAD_LEFT),
                'status' => 'Active',
            ], [
                [
                    'asset_type' => 'PC',
                    'brand_model' => 'Dell OptiPlex 3080',
                    'status' => 'Active',
                    'specs' => [
                        'processor' => $cpuModel,
                        'ram' => $ram,
                        'storage' => $storage,
                        'operating_system' => $osOptions[array_rand($osOptions)],
                        'ethernet_mac' => sprintf('AA:BB:CC:%02X:%02X:%02X', random_int(0, 255), random_int(0, 255), random_int(0, 255)),
                        'ip_address' => '192.168.' . random_int(1, 5) . '.' . random_int(10, 250),
                    ],
                ],
                [
                    'asset_type' => 'Monitor',
                    'brand_model' => 'LG 22MK600M',
                    'status' => 'Active',
                    'specs' => ['resolution' => $resolution, 'panel_type' => 'IPS'],
                ],
                [
                    'asset_type' => 'Keyboard',
                    'brand_model' => 'Logitech K120',
                    'status' => 'Active',
                    'specs' => ['keyboard_type' => $kbTypes[array_rand($kbTypes)], 'layout' => 'QWERTY'],
                ],
                [
                    'asset_type' => 'Mouse',
                    'brand_model' => 'Logitech M100',
                    'status' => 'Active',
                    'specs' => ['mouse_type' => $mouseTypes[array_rand($mouseTypes)], 'dpi' => '1000'],
                ],
                [
                    'asset_type' => 'CPU',
                    'brand_model' => 'Dell OptiPlex Tower',
                    'status' => 'Active',
                    'specs' => ['processor' => $cpuModel, 'power_supply' => $psuOptions[array_rand($psuOptions)]],
                ],
            ]);
            echo "  Created {$labId}/{$seatCode} workstation#{$result['workstation_id']} with " . count($result['assets']) . ' assets' . PHP_EOL;
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
                'printer_type' => $pd['printer_type'],
                'color_capability' => $pd['color_capability'],
                'connectivity' => $pd['connectivity'],
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
echo 'Seeding historical part swaps...' . PHP_EOL;

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
        $spareSpecs = json_encode(['mouse_type' => 'Wired', 'dpi' => '1200']);
        $conn->query("INSERT INTO assets (asset_tag, asset_type, lab_id, brand_model, status, specs) VALUES ('{$spareTag}', 'Mouse', 'LAB-101', 'HP HID Mouse (Spare)', 'Inactive', '{$spareSpecs}')");
        $spareAssetId = (int)$conn->insert_id;

        // Now swap
        $oldAssetId = (int)$mouseRows[0]['asset_id'];
        $workstationId = (int)$mouseRows[0]['workstation_id'];
        WorkstationService::swapWorkstationPart($conn, $workstationId, $oldAssetId, $spareAssetId, 'Left click button failed — replaced with spare', 'Faulty');
        echo "  Historical swap: Mouse#{$oldAssetId} at workstation#{$workstationId} replaced by spare#{$spareAssetId}" . PHP_EOL;
    } catch (\Throwable $e) {
        echo '  Swap demo error: ' . $e->getMessage() . PHP_EOL;
    }
}

// ----------------------------------------------------------------
// Grievances linked to assets and workstations
// ----------------------------------------------------------------
echo 'Seeding grievances...' . PHP_EOL;

$firstWs = $conn->query("SELECT workstation_id, lab_id FROM workstations WHERE lab_id = 'LAB-101' LIMIT 1")->fetch_assoc();
if ($firstWs) {
    $wsId = (int)$firstWs['workstation_id'];
    $labId = $firstWs['lab_id'];

    // Get a mouse asset at this workstation
    $mouseAtWs = $conn->query(
        "SELECT a.asset_id FROM assets a
         JOIN asset_assignments aa ON aa.asset_id = a.asset_id AND aa.removed_at IS NULL
         WHERE aa.workstation_id = {$wsId} AND a.asset_type = 'Mouse' LIMIT 1"
    );
    $assetId = $mouseAtWs && $mouseAtWs->num_rows > 0 ? (int)$mouseAtWs->fetch_assoc()['asset_id'] : null;

    $stmt = $conn->prepare(
        'INSERT INTO grievances (submitted_by, stud_enrollment, class, lab_id, device_category, device_name, submission_date, status, stud_email, grievance_desc, asset_id, workstation_id, tracking_code, priority, sla_response_due_at, sla_resolve_due_at, assigned_to_uid)
         VALUES (?, ?, ?, ?, ?, ?, NOW(), "Submitted", ?, ?, ?, ?, "TKT-2026-M100AA", "Medium", DATE_ADD(NOW(), INTERVAL 8 HOUR), DATE_ADD(NOW(), INTERVAL 48 HOUR), 2)'
    );
    $submitter = 'John Doe';
    $enrollment = 'EN2024001';
    $class = 'CS-3A';
    $category = 'Mouse';
    $deviceName = 'Logitech M100';
    $email = 'john.doe@student.local';
    $desc = 'The mouse scroll wheel is not working properly at Seat A-01. Scrolling is erratic and skips pages.';
    $stmt->bind_param('ssssssssii', $submitter, $enrollment, $class, $labId, $category, $deviceName, $email, $desc, $assetId, $wsId);
    $stmt->execute();
    $grvId1 = $conn->insert_id;
    $stmt->close();
    echo "  Grievance #{$grvId1} seeded (TKT-2026-M100AA) for workstation#{$wsId} / asset#{$assetId}" . PHP_EOL;

    // Second grievance — PC issue
    $pcAtWs = $conn->query(
        "SELECT a.asset_id FROM assets a
         JOIN asset_assignments aa ON aa.asset_id = a.asset_id AND aa.removed_at IS NULL
         WHERE aa.workstation_id = {$wsId} AND a.asset_type = 'PC' LIMIT 1"
    );
    $pcAssetId = $pcAtWs && $pcAtWs->num_rows > 0 ? (int)$pcAtWs->fetch_assoc()['asset_id'] : null;

    $stmt2 = $conn->prepare(
        'INSERT INTO grievances (submitted_by, stud_enrollment, class, lab_id, device_category, device_name, submission_date, status, stud_email, grievance_desc, asset_id, workstation_id, tracking_code, priority, sla_response_due_at, sla_resolve_due_at, assigned_to_uid)
         VALUES (?, ?, ?, ?, ?, ?, NOW(), "In Progress", ?, ?, ?, ?, "TKT-2026-OPT308", "High", DATE_ADD(NOW(), INTERVAL 4 HOUR), DATE_ADD(NOW(), INTERVAL 24 HOUR), 2)'
    );
    $submitter2 = 'Jane Smith';
    $enrollment2 = 'EN2024002';
    $class2 = 'CS-3B';
    $category2 = 'PC';
    $deviceName2 = 'Dell OptiPlex 3080';
    $email2 = 'jane.smith@student.local';
    $desc2 = 'PC at Seat A-01 freezes after 20 minutes of use. Blue screen error observed twice this week.';
    $stmt2->bind_param('ssssssssii', $submitter2, $enrollment2, $class2, $labId, $category2, $deviceName2, $email2, $desc2, $pcAssetId, $wsId);
    $stmt2->execute();
    $grvId2 = $conn->insert_id;
    $stmt2->close();
    echo "  Grievance #{$grvId2} seeded (TKT-2026-OPT308) for workstation#{$wsId} / PC asset#{$pcAssetId}" . PHP_EOL;

    // Seed comments & status history
    $conn->query("INSERT INTO ticket_comments (grievance_id, author_uid, author_name, is_internal, body) VALUES
        ({$grvId2}, 2, 'Lab Incharge', 0, 'Inspected hardware diagnostics. Running memory stress test.'),
        ({$grvId2}, 2, 'Lab Incharge', 1, 'Internal note: thermal paste may need replacement.')");
    $conn->query("INSERT INTO ticket_status_history (grievance_id, old_status, new_status, changed_by_uid, note) VALUES
        ({$grvId2}, 'Submitted', 'In Progress', 2, 'Diagnosing intermittent freeze issue')");
}

// ----------------------------------------------------------------
// 8. SLA Policies (Phase 5.2)
// ----------------------------------------------------------------
echo 'Seeding SLA Policies...' . PHP_EOL;
$conn->query("INSERT IGNORE INTO sla_policies (priority, response_hours, resolve_hours) VALUES
    ('Critical', 1, 4),
    ('High', 4, 24),
    ('Medium', 8, 48),
    ('Low', 24, 120)");

// ----------------------------------------------------------------
// 9. Update Assets with Financials & Warranties (Phase 5.3)
// ----------------------------------------------------------------
echo 'Updating Assets with warranty dates and purchase costs...' . PHP_EOL;
$conn->query("UPDATE assets SET purchase_cost = 650.00, salvage_value = 50.00, useful_life_years = 5, purchase_date = DATE_SUB(CURDATE(), INTERVAL 1 YEAR), warranty_expiry = DATE_ADD(CURDATE(), INTERVAL 25 DAY) WHERE asset_type = 'PC'");
$conn->query("UPDATE assets SET purchase_cost = 180.00, salvage_value = 20.00, useful_life_years = 4, purchase_date = DATE_SUB(CURDATE(), INTERVAL 2 YEAR), warranty_expiry = DATE_ADD(CURDATE(), INTERVAL 180 DAY) WHERE asset_type = 'Monitor'");
$conn->query("UPDATE assets SET purchase_cost = 25.00, salvage_value = 0.00, useful_life_years = 3, purchase_date = DATE_SUB(CURDATE(), INTERVAL 6 MONTH), warranty_expiry = DATE_ADD(CURDATE(), INTERVAL 45 DAY) WHERE asset_type IN ('Keyboard', 'Mouse')");

// ----------------------------------------------------------------
// 10. Maintenance Records (Phase 5.3)
// ----------------------------------------------------------------
echo 'Seeding Maintenance Records...' . PHP_EOL;
$sampleAsset = $conn->query('SELECT asset_id, lab_id, workstation_id FROM assets a JOIN asset_assignments aa ON aa.asset_id = a.asset_id AND aa.removed_at IS NULL LIMIT 1');
if ($sampleAsset && $sampleAsset->num_rows > 0) {
    $row = $sampleAsset->fetch_assoc();
    $aId = (int)$row['asset_id'];
    $lId = $conn->real_escape_string((string)$row['lab_id']);
    $wId = (int)$row['workstation_id'];

    $conn->query("INSERT INTO maintenance_records (asset_id, workstation_id, lab_id, maintenance_type, title, description, vendor, cost, started_at, completed_at, downtime_hours, next_scheduled_date, performed_by_uid) VALUES
        ({$aId}, {$wId}, '{$lId}', 'Repair', 'RAM Replacement', 'Replaced faulty 8GB DDR4 module due to repeated memory panics.', 'Dell Support Services', 45.00, DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY), 24.50, NULL, 1),
        ({$aId}, {$wId}, '{$lId}', 'Preventive', 'Quarterly Dust & Thermal Inspection', 'Routine internal heatsink cleaning and cable routing.', 'Internal Lab Staff', 0.00, NOW(), NOW(), 0.50, DATE_ADD(CURDATE(), INTERVAL 90 DAY), 2)");
}

// ----------------------------------------------------------------
// 11. Sample Audit Logs (Phase 5.1)
// ----------------------------------------------------------------
echo 'Seeding Sample Audit Logs...' . PHP_EOL;
$conn->query("INSERT INTO audit_logs (user_uid, username, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent) VALUES
    (1, 'admin', 'LOGIN', 'user', '1', NULL, NULL, '127.0.0.1', 'CLI Seeder'),
    (1, 'admin', 'CREATE', 'lab', 'LAB-A', NULL, '{\"lab_name\":\"Computer Science Lab A\"}', '127.0.0.1', 'CLI Seeder'),
    (2, 'incharge_lab_a', 'STATUS_CHANGE', 'ticket', 'TKT-2026-OPT308', '{\"status\":\"Submitted\"}', '{\"status\":\"In Progress\"}', '127.0.0.1', 'CLI Seeder')");

// ----------------------------------------------------------------
// 12. Agent Telemetry, Credentials & Hardware Drift (Phase 6)
// ----------------------------------------------------------------
echo 'Seeding Agent Telemetry, Credentials & Hardware Drift...' . PHP_EOL;

$workstationsQuery = $conn->query('SELECT workstation_id FROM workstations ORDER BY workstation_id ASC LIMIT 3');
$seededWsIds = [];
if ($workstationsQuery) {
    while ($row = $workstationsQuery->fetch_assoc()) {
        $seededWsIds[] = (int) $row['workstation_id'];
    }
}

if (!empty($seededWsIds)) {
    // Clean old phase 6 seed rows
    $wsInList = implode(',', $seededWsIds);
    $conn->query("DELETE FROM hardware_drift_alerts WHERE workstation_id IN ({$wsInList})");
    $conn->query("DELETE FROM workstation_inventory_snapshots WHERE workstation_id IN ({$wsInList})");
    $conn->query("DELETE FROM telemetry_samples WHERE workstation_id IN ({$wsInList})");
    $conn->query("DELETE FROM agent_credentials WHERE workstation_id IN ({$wsInList})");

    $appKey = (string) (
        ($_ENV['AGENT_SECRET_KEY'] ?? null)
        ?: ($_ENV['APP_KEY'] ?? null)
        ?: 'secret-key-for-labtrack-telemetry-dev-32b'
    );

    foreach ($seededWsIds as $idx => $wsId) {
        $apiKeyId = \LabTrack\Security\AgentSecurity::generateUuidV4();
        $plainSecret = bin2hex(random_bytes(32));
        $encryptedSecret = \LabTrack\Security\AgentSecurity::encrypt($plainSecret, $appKey);

        $ip = '192.168.1.' . (100 + $wsId);
        $mac = sprintf('00:1A:2B:3C:4D:%02X', $wsId);
        $hostname = 'WS-LAB-A-' . sprintf('%02d', $wsId);

        // 1. Credentials
        $stmtCred = $conn->prepare("INSERT INTO agent_credentials
            (workstation_id, api_key_id, signing_secret_encrypted, agent_version, enrolled_at, last_seen_at, last_ip, is_revoked)
            VALUES (?, ?, ?, '1.0.0', DATE_SUB(NOW(), INTERVAL 2 DAY), NOW(), ?, 0)");
        $stmtCred->bind_param('isss', $wsId, $apiKeyId, $encryptedSecret, $ip);
        $stmtCred->execute();
        $stmtCred->close();

        // 2. Workstation Live Columns
        $cpuPct = 22.5 + ($idx * 8.0);
        $ramPct = 48.0 + ($idx * 6.5);
        $diskPct = 62.0 - ($idx * 4.0);
        $temp = 42.0 + ($idx * 3.5);
        $hasDrift = ($idx < 2) ? 1 : 0;

        $stmtWs = $conn->prepare("UPDATE workstations SET
            mac_address = ?,
            ip_address = ?,
            os_info = 'Ubuntu 22.04 LTS (x86_64)',
            last_seen_at = NOW(),
            logged_in_user = 'student_lab',
            uptime_seconds = 7200,
            latest_cpu_pct = ?,
            latest_ram_pct = ?,
            latest_disk_pct = ?,
            cpu_temp_c = ?,
            smart_health = 'HEALTHY',
            has_hardware_drift = ?
            WHERE workstation_id = ?");
        $stmtWs->bind_param('ssddddii', $mac, $ip, $cpuPct, $ramPct, $diskPct, $temp, $hasDrift, $wsId);
        $stmtWs->execute();
        $stmtWs->close();

        // 3. Telemetry Samples (2 hours of samples: every 5 minutes = 24 samples)
        for ($m = 120; $m >= 0; $m -= 5) {
            $sampleCpu = min(95.0, max(5.0, $cpuPct + (sin($m) * 7.0)));
            $sampleRam = min(95.0, max(20.0, $ramPct + (cos($m) * 4.0)));
            $sampleDisk = $diskPct;
            $sampleTemp = $temp + (sin($m) * 2.0);

            $stmtSample = $conn->prepare("INSERT INTO telemetry_samples
                (workstation_id, sampled_at, cpu_pct, ram_pct, disk_pct, cpu_temp_c, uptime_seconds, logged_in_user, smart_health)
                VALUES (?, DATE_SUB(NOW(), INTERVAL ? MINUTE), ?, ?, ?, ?, 7200, 'student_lab', 'HEALTHY')");
            $stmtSample->bind_param('iidddd', $wsId, $m, $sampleCpu, $sampleRam, $sampleDisk, $sampleTemp);
            $stmtSample->execute();
            $stmtSample->close();
        }

        // 4. Inventory Snapshots
        $hw = [
            'ram_bytes' => ($idx === 0) ? (8 * 1024 * 1024 * 1024) : (16 * 1024 * 1024 * 1024),
            'disk_bytes' => ($idx === 1) ? (256 * 1024 * 1024 * 1024) : (512 * 1024 * 1024 * 1024),
            'cpu_model' => 'Intel Core i7-10700 @ 2.90GHz',
            'mac_address' => $mac,
        ];
        $sw = [
            ['name' => 'Ubuntu Base', 'version' => '22.04.3'],
            ['name' => 'Python', 'version' => '3.10.12'],
            ['name' => 'Google Chrome', 'version' => '120.0.6099'],
            ['name' => 'VS Code', 'version' => '1.85.1'],
        ];

        $hwJson = json_encode($hw);
        $swJson = json_encode($sw);
        $stmtSnap = $conn->prepare('INSERT INTO workstation_inventory_snapshots
            (workstation_id, reported_hardware, installed_software, updated_at)
            VALUES (?, ?, ?, NOW())');
        $stmtSnap->bind_param('iss', $wsId, $hwJson, $swJson);
        $stmtSnap->execute();
        $stmtSnap->close();
    }

    // Ensure registered PC asset specs exist for drift comparison
    $conn->query("UPDATE assets a
                  JOIN asset_assignments aa ON aa.asset_id = a.asset_id AND aa.removed_at IS NULL
                  SET a.specs = '{\"ram_gb\": 16, \"disk_gb\": 512, \"cpu_model\": \"Intel Core i7-10700 @ 2.90GHz\"}'
                  WHERE aa.workstation_id IN ({$wsInList}) AND a.asset_type = 'PC'");

    // 5. Open Hardware Drift Alerts (At least 2 Open alerts)
    // Alert 1: Workstation 1 reported 8 GB RAM vs 16 GB registered
    $conn->query("INSERT INTO hardware_drift_alerts
        (workstation_id, field_name, expected_value, reported_value, status, detected_at)
        VALUES ({$seededWsIds[0]}, 'ram_gb', '16 GB', '8 GB', 'Open', DATE_SUB(NOW(), INTERVAL 1 HOUR))");

    // Alert 2: Workstation 2 reported 256 GB Disk vs 512 GB registered
    if (isset($seededWsIds[1])) {
        $conn->query("INSERT INTO hardware_drift_alerts
            (workstation_id, field_name, expected_value, reported_value, status, detected_at)
            VALUES ({$seededWsIds[1]}, 'disk_gb', '512 GB', '256 GB', 'Open', DATE_SUB(NOW(), INTERVAL 45 MINUTE))");
    }

    echo '  Seeded credentials, telemetry time series, inventory snapshots & 2 Open drift alerts.' . PHP_EOL;
}

echo PHP_EOL . '=== Phase 6 Seed complete! ===' . PHP_EOL;
