<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/security.php';

use LabTrack\Controller\LabController;
use LabTrack\Http\Request;
use LabTrack\Http\Response;
use LabTrack\Repository\LabRepository;
use LabTrack\Router\Router;
use LabTrack\Service\LabService;
use LabTrack\Validation\LabRequest;
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
    $tmpFile = __DIR__ . '/_tmp_test_runner_p3.php';
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
echo "  LabTrack Phase 3 - Labs & Architecture Test Suite\n";
echo "==========================================================\n\n";

// --- Group 1: HTTP Request & Response ---
echo "--- Group 1: HTTP Request & Response ---\n";

$req1 = new Request(['sort' => 'name'], ['title' => 'Test'], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/labs?sort=name']);
assertTest('#1: Request identifies POST method', $req1->getMethod() === 'POST');
assertTest('#2: Request extracts query param', $req1->query('sort') === 'name');
assertTest('#3: Request extracts post body', $req1->post('title') === 'Test');
assertTest('#4: Request extracts normalized path', $req1->getPath() === '/labs');

$reqOverride = new Request([], ['_method' => 'DELETE'], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/labs/12']);
assertTest('#5: Request supports _method spoofing for DELETE', $reqOverride->getMethod() === 'DELETE');

$resp = Response::json(['success' => true], 201);
assertTest('#6: Response json helper sets status code', $resp->getStatusCode() === 201);
assertTest('#7: Response json helper sets Content-Type', ($resp->getHeaders()['Content-Type'] ?? '') === 'application/json; charset=UTF-8');

$redirect = Response::redirect('/labs');
assertTest('#8: Response redirect helper sets 302 and Location', $redirect->getStatusCode() === 302 && ($redirect->getHeaders()['Location'] ?? '') === '/labs');

// --- Group 2: Router Dispatching ---
echo "\n--- Group 2: Router Dispatching ---\n";

$router = new Router();
$router->get('/labs', function (Request $req) {
    return Response::html('labs_index');
});
$router->get('/labs/{id}', function (Request $req, string $id) {
    return Response::html('lab_' . $id);
});
$router->post('/labs', function (Request $req) {
    return Response::html('lab_created', 201);
});

$disp1 = $router->dispatch(new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/labs']));
assertTest('#9: Router dispatches static GET route', $disp1->getContent() === 'labs_index' && $disp1->getStatusCode() === 200);

$disp2 = $router->dispatch(new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/labs/CS-101']));
assertTest('#10: Router extracts named route parameter', $disp2->getContent() === 'lab_CS-101');

$disp405 = $router->dispatch(new Request([], [], ['REQUEST_METHOD' => 'PUT', 'REQUEST_URI' => '/labs']));
assertTest('#11: Router returns 405 for mismatched HTTP method', $disp405->getStatusCode() === 405);

$disp404 = $router->dispatch(new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/nonexistent-route']));
assertTest('#12: Router returns 404 for unknown route', $disp404->getStatusCode() === 404);

// --- Group 3: View Rendering & Escaping ---
echo "\n--- Group 3: View Rendering & Escaping ---\n";

assertTest('#13: View::e escapes special HTML chars', View::e('<script>alert("xss")</script>') === '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;');
assertTest('#14: View::csrf outputs hidden input tag', str_contains(View::csrf(), 'name="csrf_token"'));

// --- Group 4: LabRequest Validation ---
echo "\n--- Group 4: LabRequest Validation ---\n";

$validPayload = [
    'lab_id' => 'LAB-CS-01',
    'lab_name' => 'Computer Networks Lab',
    'status' => 'Active',
    'department' => 'Computer Engineering',
    'lab_incharge' => 'prof_sharma',
    'incharge_uid' => 2,
    'establishment_date' => '2024-01-15',
    'room_capacity' => 35,
    'building' => 'Engineering Block A',
    'room_number' => '204',
];

$val1 = LabRequest::validate($validPayload, false);
assertTest('#15: LabRequest validates correct payload', $val1['valid'] === true && empty($val1['errors']));
assertTest('#16: LabRequest casts room_capacity to integer', $val1['data']['room_capacity'] === 35);

$invalidPayload = [
    'lab_id' => 'L@B$$$',
    'lab_name' => '',
    'status' => 'InvalidStatus',
    'department' => '',
    'lab_incharge' => '',
    'establishment_date' => 'not-a-date',
    'room_capacity' => -5,
    'room_number' => '',
];

$val2 = LabRequest::validate($invalidPayload, false);
assertTest('#17: LabRequest rejects invalid lab_id chars', isset($val2['errors']['lab_id']));
assertTest('#18: LabRequest rejects empty lab_name', isset($val2['errors']['lab_name']));
assertTest('#19: LabRequest rejects invalid status enum', isset($val2['errors']['status']));
assertTest('#20: LabRequest rejects empty department', isset($val2['errors']['department']));
assertTest('#21: LabRequest rejects invalid date format', isset($val2['errors']['establishment_date']));
assertTest('#22: LabRequest rejects negative room_capacity', isset($val2['errors']['room_capacity']));
assertTest('#23: LabRequest rejects empty room_number', isset($val2['errors']['room_number']));

$valUpdate = LabRequest::validate(['lab_name' => 'Updated Lab', 'status' => 'Active', 'department' => 'IT', 'lab_incharge' => 'admin', 'establishment_date' => '2023-01-01', 'room_capacity' => 20, 'room_number' => '101'], true);
assertTest('#24: LabRequest update mode does not require lab_id in input', $valUpdate['valid'] === true);

// --- Group 5: LabRepository & LabService RBAC Contracts ---
echo "\n--- Group 5: LabRepository & LabService RBAC Contracts ---\n";

assertTest('#25: LabRepository class exists', class_exists(LabRepository::class));
assertTest('#26: LabService class exists', class_exists(LabService::class));
assertTest('#27: LabController class exists', class_exists(LabController::class));

$service = new LabService();
assertTest('#28: canAccessLab returns true for Admin role', $service->canAccessLab('ANY-LAB', null, true, 'admin') === true);

// --- Group 6: LabController Security & CSRF Enforcement ---
echo "\n--- Group 6: LabController Security & CSRF Enforcement ---\n";

$codeCsrf = '
    require_once "includes/bootstrap.php";
    require_once "includes/security.php";
    startSecureSession();
    $_SESSION["logged_in"] = true;
    $_SESSION["user_type"] = "Admin";
    $_SESSION["csrf_token"] = "valid_secret_token";
    $controller = new LabTrack\Controller\LabController();
    $req = new LabTrack\Http\Request([], ["csrf_token" => "wrong_token", "lab_id" => "LAB-1"], ["REQUEST_METHOD" => "POST"]);
    $res = $controller->store($req);
    echo "STATUS:" . $res->getStatusCode();
';
$outCsrf = runIsolatedPhp($codeCsrf);
assertTest('#29: LabController::store rejects invalid CSRF token with 403', str_contains($outCsrf, 'STATUS:403'), "output: {$outCsrf}");

$codeRole = '
    require_once "includes/bootstrap.php";
    require_once "includes/security.php";
    startSecureSession();
    $_SESSION["logged_in"] = true;
    $_SESSION["user_type"] = "Incharge";
    $_SESSION["csrf_token"] = "token123";
    $controller = new LabTrack\Controller\LabController();
    $req = new LabTrack\Http\Request([], ["csrf_token" => "token123"], ["REQUEST_METHOD" => "POST"]);
    $res = $controller->store($req);
';
$outRole = runIsolatedPhp($codeRole);
assertTest('#30: LabController::store blocks non-Admin user with 403', str_contains($outRole, 'CODE:403') || str_contains($outRole, '403 Forbidden'), "output: {$outRole}");

$codeCreateRole = '
    require_once "includes/bootstrap.php";
    require_once "includes/security.php";
    startSecureSession();
    $_SESSION["logged_in"] = true;
    $_SESSION["user_type"] = "Incharge";
    $controller = new LabTrack\Controller\LabController();
    $req = new LabTrack\Http\Request([], [], ["REQUEST_METHOD" => "GET"]);
    $res = $controller->create($req);
';
$outCreateRole = runIsolatedPhp($codeCreateRole);
assertTest('#31: LabController::create blocks non-Admin user with 403', str_contains($outCreateRole, 'CODE:403') || str_contains($outCreateRole, '403 Forbidden'), "output: {$outCreateRole}");

$codeAuth = '
    require_once "includes/bootstrap.php";
    require_once "includes/security.php";
    startSecureSession();
    $_SESSION = [];
    $controller = new LabTrack\Controller\LabController();
    $req = new LabTrack\Http\Request([], [], ["REQUEST_METHOD" => "GET"]);
    $res = $controller->index($req);
';
$outAuth = runIsolatedPhp($codeAuth);
assertTest('#32: LabController::index halts unauthenticated user on login redirect', !str_contains($outAuth, 'Computer Labs'));

echo "\n==========================================================\n";
echo "  Results: {$totalPassed} Passed, {$totalFailed} Failed (Total " . ($totalPassed + $totalFailed) . ")\n";
echo "==========================================================\n";

if ($totalFailed > 0) {
    exit(1);
}
