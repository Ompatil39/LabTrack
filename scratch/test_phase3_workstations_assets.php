<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/security.php';

use LabTrack\Controller\AssetController;
use LabTrack\Controller\WorkstationController;
use LabTrack\Http\Request;
use LabTrack\Http\Response;
use LabTrack\Router\Router;
use LabTrack\Service\AssetService;
use LabTrack\Service\WorkstationAppService;
use LabTrack\Validation\AssetRequest;
use LabTrack\Validation\WorkstationRequest;
use LabTrack\View\View;

$totalPassed = 0;
$totalFailed = 0;

function assertTest(string $description, bool $condition, string $detail = ''): void
{
    global $totalPassed, $totalFailed;
    if ($condition) {
        echo " [PASS] {$description}\n";
        $totalPassed++;
    } else {
        echo " [FAIL] {$description}" . ($detail !== '' ? " ({$detail})" : '') . "\n";
        $totalFailed++;
    }
}

function runIsolatedPhp(string $code): string {
    $tmpFile = __DIR__ . '/_tmp_test_runner_wsa.php';
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

echo "==========================================================\n";
echo "  LabTrack Phase 3 - Workstations & Assets Test Suite\n";
echo "==========================================================\n\n";

// --- Group 1: Workstation Validation (WorkstationRequest) ---
echo "--- Group 1: Workstation Validation ---\n";

$validWs = [
    'lab_id' => '2',
    'seat_code' => 'WS-01',
    'hostname' => 'pc-lab2-01',
    'pc_model' => 'Dell OptiPlex 7090',
    'pc_serial' => 'SN-PC-01',
    'monitor_model' => 'Dell P2419H',
    'monitor_serial' => 'SN-MON-01',
    'keyboard_model' => 'Dell KB216',
    'keyboard_serial' => 'SN-KB-01',
    'mouse_model' => 'Dell MS116',
    'mouse_serial' => 'SN-MS-01',
    'cpu_model' => 'Intel Core i7-10700',
    'cpu_serial' => 'SN-CPU-01',
    'ram' => '16GB DDR4',
    'storage' => '512GB NVMe SSD',
    'os' => 'Ubuntu 22.04 LTS',
];
$res1 = WorkstationRequest::validateCreate($validWs);
assertTest('#1: WorkstationRequest::validateCreate passes for complete workstation bundle', $res1['valid'] === true);
assertTest('#2: WorkstationRequest normalizes seat_code', $res1['data']['seat_code'] === 'WS-01');
assertTest('#3: WorkstationRequest casts lab_id to integer', $res1['data']['lab_id'] === 2);
assertTest('#4: WorkstationRequest builds PC specs array', is_array($res1['data']['pc_specs']) && $res1['data']['pc_specs']['ram'] === '16GB DDR4');

// Test missing required fields
$missingWs = [
    'lab_id' => '',
    'seat_code' => '',
    'pc_model' => '',
];
$res2 = WorkstationRequest::validateCreate($missingWs);
assertTest('#5: WorkstationRequest rejects empty required fields', $res2['valid'] === false);
assertTest('#6: WorkstationRequest flags lab_id error', isset($res2['errors']['lab_id']));
assertTest('#7: WorkstationRequest flags seat_code error', isset($res2['errors']['seat_code']));
assertTest('#8: WorkstationRequest flags pc_model error', isset($res2['errors']['pc_model']));

// Test workstation update validation
$validUpdate = [
    'seat_code' => 'WS-01-A',
    'hostname' => 'pc-new-hostname',
    'status' => 'Active',
];
$res3 = WorkstationRequest::validateUpdate($validUpdate);
assertTest('#9: WorkstationRequest::validateUpdate passes with valid data', $res3['valid'] === true);

$invalidUpdate = [
    'seat_code' => '',
    'status' => 'NonExistentStatus',
];
$res4 = WorkstationRequest::validateUpdate($invalidUpdate);
assertTest('#10: WorkstationRequest::validateUpdate rejects empty seat_code and invalid status', $res4['valid'] === false && isset($res4['errors']['status']));

// Test workstation swap validation
$validSwap = [
    'old_asset_id' => '10',
    'new_asset_id' => '15',
    'removal_reason' => 'Faulty RAM module detected during lab session',
];
$res5 = WorkstationRequest::validateSwap($validSwap);
assertTest('#11: WorkstationRequest::validateSwap passes with distinct assets and valid reason', $res5['valid'] === true);
assertTest('#12: WorkstationRequest::validateSwap casts IDs to int', $res5['data']['old_asset_id'] === 10 && $res5['data']['new_asset_id'] === 15);

$sameAssetSwap = [
    'old_asset_id' => '10',
    'new_asset_id' => '10',
    'removal_reason' => 'Upgrade',
];
$res6 = WorkstationRequest::validateSwap($sameAssetSwap);
assertTest('#13: WorkstationRequest::validateSwap rejects swapping an asset with itself', $res6['valid'] === false && isset($res6['errors']['new_asset_id']));

$emptyReasonSwap = [
    'old_asset_id' => '10',
    'new_asset_id' => '15',
    'removal_reason' => '',
];
$res7 = WorkstationRequest::validateSwap($emptyReasonSwap);
assertTest('#14: WorkstationRequest::validateSwap requires removal_reason', $res7['valid'] === false && isset($res7['errors']['removal_reason']));

// --- Group 2: Asset Validation (AssetRequest) ---
echo "\n--- Group 2: Asset Validation ---\n";

$validAsset = [
    'asset_type' => 'Printer',
    'lab_id' => '3',
    'brand_model' => 'HP LaserJet Pro M404dn',
    'serial_number' => 'VNB3K12345',
    'status' => 'Spare',
    'purchase_cost' => '18500.50',
    'purchase_date' => '2024-03-15',
    'warranty_until' => '2027-03-15',
    'specifications' => '{"duplex": true, "ppm": 38}',
];
$res8 = AssetRequest::validate($validAsset, false);
assertTest('#15: AssetRequest passes for valid standalone asset creation', $res8['valid'] === true);
assertTest('#16: AssetRequest parses JSON specifications into array', is_array($res8['data']['specifications']) && $res8['data']['specifications']['duplex'] === true);
assertTest('#17: AssetRequest casts cost to float', $res8['data']['purchase_cost'] === 18500.50);

$invalidTypeAsset = [
    'asset_type' => 'Drone',
    'lab_id' => '3',
];
$res9 = AssetRequest::validate($invalidTypeAsset, false);
assertTest('#18: AssetRequest rejects unsupported asset type', $res9['valid'] === false && isset($res9['errors']['asset_type']));

$invalidCostAsset = [
    'asset_type' => 'PC',
    'lab_id' => '1',
    'purchase_cost' => '-500',
    'purchase_date' => 'not-a-date',
    'specifications' => '{invalid-json',
];
$res10 = AssetRequest::validate($invalidCostAsset, false);
assertTest('#19: AssetRequest rejects negative cost', isset($res10['errors']['purchase_cost']));
assertTest('#20: AssetRequest rejects invalid date format', isset($res10['errors']['purchase_date']));
assertTest('#21: AssetRequest rejects malformed JSON specifications', isset($res10['errors']['specifications']));

// --- Group 3: Router Route Coverage ---
echo "\n--- Group 3: Router Route Integration ---\n";

$router = new Router();
$router->get('/workstations', [WorkstationController::class, 'index']);
$router->get('/workstations/create', [WorkstationController::class, 'create']);
$router->post('/workstations', [WorkstationController::class, 'store']);
$router->get('/workstations/{id}', [WorkstationController::class, 'show']);
$router->get('/workstations/{id}/edit', [WorkstationController::class, 'edit']);
$router->post('/workstations/{id}', [WorkstationController::class, 'update']);
$router->post('/workstations/{id}/swap', [WorkstationController::class, 'swap']);
$router->get('/labs/{lab}/workstations', [WorkstationController::class, 'byLab']);

$router->get('/inventory', [AssetController::class, 'index']);
$router->get('/assets', [AssetController::class, 'index']);
$router->get('/assets/create', [AssetController::class, 'create']);
$router->post('/assets', [AssetController::class, 'store']);
$router->get('/assets/{tag}', [AssetController::class, 'show']);
$router->get('/assets/{tag}/edit', [AssetController::class, 'edit']);
$router->post('/assets/{tag}', [AssetController::class, 'update']);
$router->post('/assets/{tag}/delete', [AssetController::class, 'destroy']);
$router->get('/api/assets', [AssetController::class, 'apiSearch']);
$router->get('/api/assets/select', [AssetController::class, 'apiSelect']);

// Test routing patterns
$reqWsShow = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/workstations/42']);
$reqWsShow->setRouteParams(['id' => '42']);
assertTest('#22: Route path /workstations/42 normalizes properly', $reqWsShow->getPath() === '/workstations/42');

$reqAssetShow = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/assets/PC-00123']);
$reqAssetShow->setRouteParams(['tag' => 'PC-00123']);
assertTest('#23: Route path /assets/PC-00123 normalizes properly', $reqAssetShow->getPath() === '/assets/PC-00123');

$reqApiSelect = new Request(['type' => 'Monitor', 'lab_id' => '2'], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/assets/select?type=Monitor&lab_id=2']);
assertTest('#24: Route path /api/assets/select normalizes query params', $reqApiSelect->query('type') === 'Monitor');

// --- Group 4: Security & RBAC Enforcement (Isolated Executions) ---
echo "\n--- Group 4: Security & RBAC Enforcement ---\n";

// Test unauthenticated access to /workstations
$outUnauthWs = runIsolatedPhp('
    require_once dirname(__DIR__) . "/includes/bootstrap.php";
    require_once dirname(__DIR__) . "/includes/security.php";
    use LabTrack\Http\Request;
    use LabTrack\Controller\WorkstationController;
    $_SESSION = [];
    $ctrl = new WorkstationController();
    $res = $ctrl->index(new Request());
');
assertTest('#25: Unauthenticated access to WorkstationController@index triggers login redirection (302)', str_contains($outUnauthWs, 'CODE:302'));

// Test unauthenticated access to /inventory
$outUnauthInv = runIsolatedPhp('
    require_once dirname(__DIR__) . "/includes/bootstrap.php";
    require_once dirname(__DIR__) . "/includes/security.php";
    use LabTrack\Http\Request;
    use LabTrack\Controller\AssetController;
    $_SESSION = [];
    $ctrl = new AssetController();
    $res = $ctrl->index(new Request());
');
assertTest('#26: Unauthenticated access to AssetController@index triggers login redirection (302)', str_contains($outUnauthInv, 'CODE:302'));

// Test non-admin access to workstation creation (requireRole("Admin"))
$outInchargeCreateWs = runIsolatedPhp('
    require_once dirname(__DIR__) . "/includes/bootstrap.php";
    require_once dirname(__DIR__) . "/includes/security.php";
    use LabTrack\Http\Request;
    use LabTrack\Controller\WorkstationController;
    startSecureSession();
    $_SESSION = ["logged_in" => true, "user_type" => "Incharge", "role" => "Incharge", "full_name" => "Lab Incharge", "username" => "incharge"];
    $ctrl = new WorkstationController();
    $res = $ctrl->create(new Request());
');
assertTest('#27: Incharge access to workstation create form is blocked with 403 Forbidden', str_contains($outInchargeCreateWs, 'CODE:403'));

// Test non-admin access to asset registration (requireRole("Admin"))
$outInchargeCreateAsset = runIsolatedPhp('
    require_once dirname(__DIR__) . "/includes/bootstrap.php";
    require_once dirname(__DIR__) . "/includes/security.php";
    use LabTrack\Http\Request;
    use LabTrack\Controller\AssetController;
    startSecureSession();
    $_SESSION = ["logged_in" => true, "user_type" => "Incharge", "role" => "Incharge", "full_name" => "Lab Incharge", "username" => "incharge"];
    $ctrl = new AssetController();
    $res = $ctrl->create(new Request());
');
assertTest('#28: Incharge access to asset create form is blocked with 403 Forbidden', str_contains($outInchargeCreateAsset, 'CODE:403'));

// Test missing CSRF token on POST /workstations
$outCsrfWs = runIsolatedPhp('
    require_once dirname(__DIR__) . "/includes/bootstrap.php";
    require_once dirname(__DIR__) . "/includes/security.php";
    use LabTrack\Http\Request;
    use LabTrack\Controller\WorkstationController;
    startSecureSession();
    $_SESSION = ["logged_in" => true, "user_type" => "Admin", "role" => "Admin", "full_name" => "Administrator", "username" => "admin", "csrf_token" => "valid-token-xyz"];
    $ctrl = new WorkstationController();
    $req = new Request([], ["seat_code" => "WS-01"], ["REQUEST_METHOD" => "POST"]);
    $res = $ctrl->store($req);
    echo "STATUS:" . $res->getStatusCode();
');
assertTest('#29: POST /workstations without CSRF token returns 403 Forbidden', str_contains($outCsrfWs, 'STATUS:403'));

// Test missing CSRF token on POST /workstations/{id}/swap
$outCsrfSwap = runIsolatedPhp('
    require_once dirname(__DIR__) . "/includes/bootstrap.php";
    require_once dirname(__DIR__) . "/includes/security.php";
    use LabTrack\Http\Request;
    use LabTrack\Controller\WorkstationController;
    startSecureSession();
    $_SESSION = ["logged_in" => true, "user_type" => "Admin", "role" => "Admin", "full_name" => "Administrator", "username" => "admin", "csrf_token" => "valid-token-xyz"];
    $ctrl = new WorkstationController();
    $req = new Request([], ["old_asset_id" => 1, "new_asset_id" => 2], ["REQUEST_METHOD" => "POST"]);
    $res = $ctrl->swap($req, 5);
    echo "STATUS:" . $res->getStatusCode();
');
assertTest('#30: POST /workstations/{id}/swap without CSRF token returns 403 Forbidden', str_contains($outCsrfSwap, 'STATUS:403'));

// Test missing CSRF token on POST /assets
$outCsrfAsset = runIsolatedPhp('
    require_once dirname(__DIR__) . "/includes/bootstrap.php";
    require_once dirname(__DIR__) . "/includes/security.php";
    use LabTrack\Http\Request;
    use LabTrack\Controller\AssetController;
    startSecureSession();
    $_SESSION = ["logged_in" => true, "user_type" => "Admin", "role" => "Admin", "full_name" => "Administrator", "username" => "admin", "csrf_token" => "valid-token-xyz"];
    $ctrl = new AssetController();
    $req = new Request([], ["asset_type" => "Printer"], ["REQUEST_METHOD" => "POST"]);
    $res = $ctrl->store($req);
    echo "STATUS:" . $res->getStatusCode();
');
assertTest('#31: POST /assets without CSRF token returns 403 Forbidden', str_contains($outCsrfAsset, 'STATUS:403'));

// Test unauthenticated access to /api/assets
$outUnauthApi = runIsolatedPhp('
    require_once dirname(__DIR__) . "/includes/bootstrap.php";
    require_once dirname(__DIR__) . "/includes/security.php";
    use LabTrack\Http\Request;
    use LabTrack\Controller\AssetController;
    $_SESSION = [];
    $ctrl = new AssetController();
    $res = $ctrl->apiSearch(new Request());
');
assertTest('#32: Unauthenticated access to /api/assets is gated (returns 401 Unauthorized)', str_contains($outUnauthApi, 'CODE:401'));

// --- Group 5: Lab Access Scoping in WorkstationAppService ---
echo "\n--- Group 5: Lab Access Scoping in Service Layer ---\n";

$wsAppService = new WorkstationAppService();

// Admin should always have access
assertTest('#33: WorkstationAppService grants Admin universal access', $wsAppService->canUserAccessLab('LAB01', null, true) === true);

// Incharge without matching assignment should be denied via repository contract
$mockLabRepo = new class extends \LabTrack\Repository\LabRepository {
    public function canUserAccessLab(string $labId, ?int $userUid, bool $isAdmin, string $username, string $fullName = ''): bool {
        if ($isAdmin) {
            return true;
        }
        return false;
    }
};
$wsAppServiceMocked = new WorkstationAppService(null, null, $mockLabRepo);
assertTest('#34: WorkstationAppService denies unassigned Incharge access via repository contract', $wsAppServiceMocked->canUserAccessLab('LAB01', 9999, false, 'incharge1', 'Incharge One') === false);

// --- Group 6: View Templates Compilation ---
echo "\n--- Group 6: View Templates Compilation ---\n";

$templatesToCompile = [
    'templates/workstations/index.php',
    'templates/workstations/show.php',
    'templates/workstations/create.php',
    'templates/workstations/edit.php',
    'templates/assets/index.php',
    'templates/assets/show.php',
    'templates/assets/create.php',
    'templates/assets/edit.php',
    'templates/layout/base.php',
];

$allCompiled = true;
foreach ($templatesToCompile as $tpl) {
    $path = dirname(__DIR__) . '/' . $tpl;
    $syntax = shell_exec('php -l "' . $path . '" 2>&1');
    if (!str_contains((string) $syntax, 'No syntax errors detected')) {
        $allCompiled = false;
        echo " [FAIL] Template {$tpl} syntax check failed: {$syntax}\n";
    }
}
assertTest('#35: All 9 Workstation and Asset templates pass PHP syntax linting', $allCompiled);

echo "\n==========================================================\n";
echo "  Results: {$totalPassed} Passed, {$totalFailed} Failed\n";
echo "==========================================================\n";

if ($totalFailed > 0) {
    exit(1);
}
