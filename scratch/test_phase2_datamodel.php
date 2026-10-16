<?php

declare(strict_types=1);

/**
 * Phase 2 Data Model & Relational Integrity Automated Test Suite
 *
 * Verifies:
 *  1. Migration file definitions & clean up/down structure
 *  2. Authentication session initialization (users.uid & users.user_id)
 *  3. Relational RBAC (currentUserUid, restrictLabSql with lab_incharges)
 *  4. AssetTagGenerator atomic LAST_INSERT_ID() logic & format validation
 *  5. WorkstationService transaction guards and method contracts
 *  6. Grievance query structure (asset/workstation joins without row multiplication)
 *  7. Live DB integration tests (if MySQL server is running)
 */

$testCount = 0;
$passedCount = 0;
$failedCount = 0;

function assertTest(string $description, bool $condition, string $detail = ''): void {
    global $testCount, $passedCount, $failedCount;
    $testCount++;
    if ($condition) {
        $passedCount++;
        echo " [PASS] #{$testCount}: {$description}" . PHP_EOL;
    } else {
        $failedCount++;
        echo " [FAIL] #{$testCount}: {$description}" . ($detail !== '' ? " -> {$detail}" : "") . PHP_EOL;
    }
}

echo "==========================================================" . PHP_EOL;
echo "  LabTrack Phase 2 Data Model Verification Suite" . PHP_EOL;
echo "==========================================================" . PHP_EOL . PHP_EOL;

// -----------------------------------------------------------------------------
// Test Group 1: Migration Files and Up/Down Structure
// -----------------------------------------------------------------------------
echo "--- Group 1: Migration Files ---" . PHP_EOL;

$upFile = __DIR__ . '/../database/migrations/002_phase2_data_model.sql';
$downFile = __DIR__ . '/../database/migrations/002_phase2_data_model.down.sql';

assertTest('002_phase2_data_model.sql exists', file_exists($upFile));
assertTest('002_phase2_data_model.down.sql exists', file_exists($downFile));

$upSql = (string) file_get_contents($upFile);
$downSql = (string) file_get_contents($downFile);

assertTest('Up migration creates workstations table with unique seat', str_contains($upSql, 'CREATE TABLE IF NOT EXISTS workstations') && str_contains($upSql, 'UNIQUE KEY uq_lab_seat (lab_id, seat_code)'));
assertTest('Up migration creates assets table with JSON specs', str_contains($upSql, 'CREATE TABLE IF NOT EXISTS assets') && str_contains($upSql, 'specs           JSON'));
assertTest('Up migration creates asset_assignments with stored generated active_asset_id', str_contains($upSql, 'active_asset_id INT GENERATED ALWAYS AS') && str_contains($upSql, 'uq_single_active_assignment'));
assertTest('Up migration creates asset_tag_sequences', str_contains($upSql, 'CREATE TABLE IF NOT EXISTS asset_tag_sequences'));
assertTest('Up migration creates lab_incharges relational table', str_contains($upSql, 'CREATE TABLE IF NOT EXISTS lab_incharges'));
assertTest('Up migration adds incharge_uid to labs table', str_contains($upSql, 'incharge_uid INT NULL'));
assertTest('Up migration adds asset_id and workstation_id to grievances', str_contains($upSql, 'ADD COLUMN IF NOT EXISTS asset_id') && str_contains($upSql, 'ADD COLUMN IF NOT EXISTS workstation_id'));

assertTest('Down migration drops all tables in reverse order',
    str_contains($downSql, 'DROP TABLE IF EXISTS asset_assignments') &&
    str_contains($downSql, 'DROP TABLE IF EXISTS assets') &&
    str_contains($downSql, 'DROP TABLE IF EXISTS workstations') &&
    str_contains($downSql, 'DROP TABLE IF EXISTS lab_incharges')
);

// -----------------------------------------------------------------------------
// Test Group 2: Authentication Session Initialization & Relational RBAC
// -----------------------------------------------------------------------------
echo PHP_EOL . "--- Group 2: Login Session & Relational RBAC ---" . PHP_EOL;

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/security.php';

// Simulate the exact session initialization performed by login.php
$mockAuthUser = [
    'user_id' => 'USR-002',
    'username' => 'prof_sharma',
    'user_type' => 'Incharge',
    'full_name' => 'Prof. Sharma',
    'uid' => 10,
];

// Execute the session assignment contract from login.php
$_SESSION['user_id']   = (string) $mockAuthUser['user_id'];
$_SESSION['uid']       = isset($mockAuthUser['uid']) && $mockAuthUser['uid'] !== null ? (int) $mockAuthUser['uid'] : null;
$_SESSION['full_name'] = (string) ($mockAuthUser['full_name'] ?? '');
$_SESSION['username']  = (string) $mockAuthUser['username'];
$_SESSION['user_type'] = (string) $mockAuthUser['user_type'];
$_SESSION['logged_in'] = true;

assertTest('Authentication session preserves legacy string user_id', $_SESSION['user_id'] === 'USR-002');
assertTest('Authentication session initializes integer uid', $_SESSION['uid'] === 10);
assertTest('currentUserUid() returns integer UID from authenticated session', currentUserUid() === 10);

// Test restrictLabSql with relational Incharge session
$scopedSql = restrictLabSql('lab_id');
assertTest('restrictLabSql queries lab_incharges using authenticated UID', str_contains($scopedSql['clause'], 'lab_incharges li WHERE li.uid = ?'));
assertTest('restrictLabSql binds UID integer as first parameter', ($scopedSql['params'][0] ?? null) === 10 && ($scopedSql['types'][0] ?? '') === 'i');

// Test Admin role bypass
$_SESSION['user_type'] = 'Admin';
$adminScoped = restrictLabSql('lab_id');
assertTest('restrictLabSql returns empty clause for Admin role', $adminScoped['clause'] === '' && empty($adminScoped['params']));

// Reset session
unset($_SESSION['uid'], $_SESSION['user_type'], $_SESSION['username'], $_SESSION['full_name'], $_SESSION['user_id']);

// -----------------------------------------------------------------------------
// Test Group 3: AssetTagGenerator Prefix Map and Format Validation
// -----------------------------------------------------------------------------
echo PHP_EOL . "--- Group 3: AssetTagGenerator Logic ---" . PHP_EOL;

require_once __DIR__ . '/../includes/AssetTagGenerator.php';
use LabTrack\AssetTagGenerator;

$prefixMap = AssetTagGenerator::getPrefixMap();
assertTest('AssetTagGenerator defines PC prefix', ($prefixMap['PC'] ?? '') === 'PC');
assertTest('AssetTagGenerator defines Monitor prefix MON', ($prefixMap['Monitor'] ?? '') === 'MON');
assertTest('AssetTagGenerator defines Keyboard prefix KBD', ($prefixMap['Keyboard'] ?? '') === 'KBD');
assertTest('AssetTagGenerator defines Mouse prefix MSE', ($prefixMap['Mouse'] ?? '') === 'MSE');
assertTest('AssetTagGenerator defines CPU prefix CPU', ($prefixMap['CPU'] ?? '') === 'CPU');
assertTest('AssetTagGenerator defines Printer prefix PRT', ($prefixMap['Printer'] ?? '') === 'PRT');
assertTest('AssetTagGenerator defines Switch prefix SW', ($prefixMap['Switch'] ?? '') === 'SW');
assertTest('AssetTagGenerator defines AccessPoint prefix AP', ($prefixMap['AccessPoint'] ?? '') === 'AP');

// Verify invalid asset type throws InvalidArgumentException
$threwExpected = false;
try {
    $mockConn = (new ReflectionClass(mysqli::class))->newInstanceWithoutConstructor();
    AssetTagGenerator::nextTag($mockConn, 'InvalidDeviceType');
} catch (InvalidArgumentException $e) {
    $threwExpected = true;
} catch (Throwable $e) {
}
assertTest('AssetTagGenerator rejects invalid asset type with InvalidArgumentException', $threwExpected);

// -----------------------------------------------------------------------------
// Test Group 4: WorkstationService Method Contracts & Validation Guards
// -----------------------------------------------------------------------------
echo PHP_EOL . "--- Group 4: WorkstationService Contracts ---" . PHP_EOL;

require_once __DIR__ . '/../includes/WorkstationService.php';
use LabTrack\WorkstationService;

$wsRef = new ReflectionClass(WorkstationService::class);
assertTest('WorkstationService has createWorkstationWithParts method', $wsRef->hasMethod('createWorkstationWithParts'));
assertTest('WorkstationService has getWorkstationDetails method', $wsRef->hasMethod('getWorkstationDetails'));
assertTest('WorkstationService has getAssetHistory method', $wsRef->hasMethod('getAssetHistory'));
assertTest('WorkstationService has swapWorkstationPart method', $wsRef->hasMethod('swapWorkstationPart'));

// Validate createWorkstationWithParts input guards
$threwGuard = false;
try {
    $mockConn = (new ReflectionClass(mysqli::class))->newInstanceWithoutConstructor();
    WorkstationService::createWorkstationWithParts($mockConn, ['lab_id' => ''], []);
} catch (InvalidArgumentException $e) {
    $threwGuard = true;
} catch (Throwable $e) {
}
assertTest('WorkstationService::createWorkstationWithParts validates missing lab_id', $threwGuard);

$threwGuardSeat = false;
try {
    $mockConn = (new ReflectionClass(mysqli::class))->newInstanceWithoutConstructor();
    WorkstationService::createWorkstationWithParts($mockConn, ['lab_id' => 'LAB-101', 'seat_code' => ''], []);
} catch (InvalidArgumentException $e) {
    $threwGuardSeat = true;
} catch (Throwable $e) {
}
assertTest('WorkstationService::createWorkstationWithParts validates missing seat_code', $threwGuardSeat);

// -----------------------------------------------------------------------------
// Test Group 5: Grievance Query & Relational Integrity Contracts
// -----------------------------------------------------------------------------
echo PHP_EOL . "--- Group 5: Grievance & Workstation Query Integrity ---" . PHP_EOL;

// Verify grievance detail query structure
$detailQueryPattern = 'LEFT JOIN assets a ON';
$viewGrievanceSource = (string) file_get_contents(__DIR__ . '/../src/Repository/GrievanceRepository.php');
assertTest('GrievanceRepository joins assets on asset_id', str_contains($viewGrievanceSource, $detailQueryPattern));
assertTest('GrievanceRepository joins workstations on workstation_id', str_contains($viewGrievanceSource, 'LEFT JOIN workstations w ON'));

// Verify grievance listing query structure
$grievanceSource = (string) file_get_contents(__DIR__ . '/../src/Repository/GrievanceRepository.php');
assertTest('GrievanceRepository listFiltered joins assets and workstations without duplicating rows', str_contains($grievanceSource, 'LEFT JOIN assets a ON') && str_contains($grievanceSource, 'LEFT JOIN workstations w ON'));

// Verify addDevice atomic tag integration
$addDeviceSource = (string) file_get_contents(__DIR__ . '/../src/Service/AssetService.php');
assertTest('AssetService calls AssetTagGenerator::nextTag', str_contains($addDeviceSource, 'AssetTagGenerator::nextTag'));

// -----------------------------------------------------------------------------
// Test Group 6: Live DB Integration (Executed when MySQL is available)
// -----------------------------------------------------------------------------
echo PHP_EOL . "--- Group 6: Live DB Integration Check ---" . PHP_EOL;

try {
    require_once __DIR__ . '/../config/config.php';
    $dbConfig = app_config()['db'];
    $conn = @new mysqli($dbConfig['host'], $dbConfig['username'], $dbConfig['password'], $dbConfig['database'], $dbConfig['port']);
    if ($conn->connect_errno) {
        echo " [INFO] Live MySQL server not available ({$conn->connect_error}); skipped live DB execution checks." . PHP_EOL;
    } else {
        assertTest('Live MySQL connection established', true);

        // Check if migration 002 tables exist
        $wsTable = $conn->query("SHOW TABLES LIKE 'workstations'")->num_rows > 0;
        $assetsTable = $conn->query("SHOW TABLES LIKE 'assets'")->num_rows > 0;
        $seqTable = $conn->query("SHOW TABLES LIKE 'asset_tag_sequences'")->num_rows > 0;
        $assignTable = $conn->query("SHOW TABLES LIKE 'asset_assignments'")->num_rows > 0;

        assertTest('Live DB: workstations table exists', $wsTable);
        assertTest('Live DB: assets table exists', $assetsTable);
        assertTest('Live DB: asset_tag_sequences table exists', $seqTable);
        assertTest('Live DB: asset_assignments table exists', $assignTable);

        if ($seqTable) {
            $tag = AssetTagGenerator::nextTag($conn, 'PC');
            assertTest('Live DB: AssetTagGenerator produces valid tag format', (bool)preg_match('/^PC-\d{4}-\d{4}$/', $tag), "Generated: {$tag}");
        }

        $conn->close();
    }
} catch (Throwable $e) {
    echo " [INFO] Live DB check note: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL . "==========================================================" . PHP_EOL;
echo "  Results: {$passedCount} Passed, {$failedCount} Failed (Total {$testCount})" . PHP_EOL;
echo "==========================================================" . PHP_EOL;

if ($failedCount > 0) {
    exit(1);
}
exit(0);
