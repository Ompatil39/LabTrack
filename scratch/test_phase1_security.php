<?php
// Phase 1 Security Verification Test

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/DeviceStatus.php';

use LabTrack\DeviceStatus;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$testsPassed = 0;
$totalTests = 0;

function assertTest(string $name, bool $condition, string $detail = '') {
    global $testsPassed, $totalTests;
    $totalTests++;
    if ($condition) {
        $testsPassed++;
        echo " [PASS] $name\n";
    } else {
        echo " [FAIL] $name: $detail\n";
    }
}

echo "=== RUNNING PHASE 1 SECURITY VERIFICATION TESTS ===\n\n";

// 1. Test DeviceStatus canonical constants
assertTest(
    "DeviceStatus constants",
    DeviceStatus::ACTIVE === 'Active' &&
    DeviceStatus::UNDER_REPAIR === 'Under Repair' &&
    DeviceStatus::INACTIVE === 'Inactive' &&
    DeviceStatus::FAULTY === 'Faulty' &&
    DeviceStatus::RETIRED === 'Retired',
    "Status constants mismatch"
);

// 2. Test CSRF token generation and validation
$token = generateCsrfToken();
assertTest("CSRF Token Generated", !empty($token) && strlen($token) === 64, "Token: " . $token);
assertTest("CSRF Token Verification with Valid Token", verifyCsrfToken($token) === true, "Token failed validation");
assertTest("CSRF Token Verification with Invalid Token", verifyCsrfToken('invalid_token_123') === false, "Invalid token passed");
assertTest("CSRF Token Verification with Empty Token", verifyCsrfToken('') === false, "Empty token passed");

// 3. Test Incharge vs Admin Lab Access Scoping
$_SESSION['logged_in'] = true;
$_SESSION['username'] = 'incharge_user';
$_SESSION['full_name'] = 'Prof. Sharma';
$_SESSION['user_type'] = 'Incharge';
$_SESSION['role'] = 'Incharge';

assertTest("isAdmin() returns false for Incharge", isAdmin() === false, "Returned true for Incharge");

// Test restrictLabSql for Incharge
$restrict = restrictLabSql('lab_id');
assertTest(
    "restrictLabSql produces where clause for Incharge",
    !empty($restrict['clause']) && strpos($restrict['clause'], 'lab_incharge') !== false,
    "Clause: " . $restrict['clause']
);

// Switch to Admin
$_SESSION['user_type'] = 'Admin';
$_SESSION['role'] = 'Admin';
assertTest("isAdmin() returns true for Admin", isAdmin() === true, "Returned false for Admin");
$adminRestrict = restrictLabSql('lab_id');
assertTest("restrictLabSql produces empty clause for Admin", empty($adminRestrict['clause']), "Clause not empty");

// 4. Test Lockout / Rate Limiting
$_SERVER['REMOTE_ADDR'] = '192.168.1.100';
clearFailedLogin('test_user');
assertTest("isLoginLocked initially false", isLoginLocked('test_user') === false, "Initially locked");

for ($i = 0; $i < 5; $i++) {
    recordFailedLogin('test_user');
}
assertTest("isLoginLocked true after 5 failed attempts", isLoginLocked('test_user') === true, "Not locked after 5 fails");

clearFailedLogin('test_user');
assertTest("clearFailedLogin resets lockout", isLoginLocked('test_user') === false, "Still locked after clear");

function runIsolatedPhp(string $code): string {
    $tmpFile = __DIR__ . '/_tmp_test_runner.php';
    $runnerCode = "<?php\n" .
        "register_shutdown_function(function() {\n" .
        "    echo \"\\nCODE:\" . http_response_code();\n" .
        "});\n" .
        $code;
    file_put_contents($tmpFile, $runnerCode);
    $out = shell_exec('php "' . $tmpFile . '" 2>&1');
    @unlink($tmpFile);
    return trim((string)$out);
}

// 5. Test Endpoint Execution via CLI Simulation (HTTP Status / Exit Codes)
echo "\n--- Endpoint Security Gating via CLI Subprocesses ---\n";

// A. Unauthenticated request to requireLogin (should return 401 Unauthorized)
$codeA = '
require_once "' . addslashes(dirname(__DIR__) . '/includes/security.php') . '";
requireLogin();
';
$outA = runIsolatedPhp($codeA);
assertTest("requireLogin blocks unauthenticated user with 401", strpos($outA, 'Authentication required') !== false || strpos($outA, 'CODE:401') !== false || strpos($outA, 'login.php') !== false, "Output: '$outA'");

// B. Incharge accessing Admin route via requireRole (should receive 403 Forbidden)
$codeC = '
session_start();
$_SESSION["logged_in"] = true;
$_SESSION["user_type"] = "Incharge";
$_SESSION["role"] = "Incharge";
$_SESSION["username"] = "prof_test";
require_once "' . addslashes(dirname(__DIR__) . '/includes/security.php') . '";
requireRole("Admin");
';
$outC = runIsolatedPhp($codeC);
assertTest("requireRole gives 403 Forbidden to Incharge", strpos($outC, '403 Forbidden') !== false || strpos($outC, 'CODE:403') !== false, "Output: '$outC'");

echo "\n============================================\n";
echo "SUMMARY: Passed $testsPassed of $totalTests tests.\n";
if ($testsPassed === $totalTests) {
    echo "ALL PHASE 1 SECURITY CHECKS PASSED SUCCESSFULLY!\n";
    exit(0);
} else {
    echo "SOME CHECKS FAILED!\n";
    exit(1);
}
