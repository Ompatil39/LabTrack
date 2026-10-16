<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/security.php';

use LabTrack\Controller\GrievanceController;
use LabTrack\Http\Request;
use LabTrack\Http\Response;
use LabTrack\Router\Router;
use LabTrack\Service\GrievanceService;
use LabTrack\Validation\GrievanceRequest;
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
    $tmpFile = __DIR__ . '/_tmp_test_runner_grv.php';
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
echo "  LabTrack Phase 3 - Grievances Test Suite\n";
echo "==========================================================\n\n";

// -----------------------------------------------------------------------------
// Group 1: Grievance Validation (GrievanceRequest)
// -----------------------------------------------------------------------------
echo "--- Group 1: GrievanceRequest Validation ---\n";

$validator = new GrievanceRequest();

$validPayload = [
    'submitted_by'    => 'John Doe',
    'stud_enrollment' => 'ENR-12345',
    'class'           => 'CO-5I',
    'stud_email'      => 'john.doe@example.com',
    'lab_id'          => 'LAB-01',
    'device_category' => 'PC',
    'device_name'     => 'PC-01',
    'grievance_desc'  => 'The monitor is flickering intermittently.',
];

$res = $validator->validateSubmit($validPayload);
assertTest('#1: GrievanceRequest passes for valid submit payload', empty($res['errors']), implode(', ', $res['errors']));
assertTest('#2: GrievanceRequest capitalizes enrollment', ($res['clean']['stud_enrollment'] ?? '') === 'ENR-12345');
assertTest('#3: GrievanceRequest lowercases email', ($res['clean']['stud_email'] ?? '') === 'john.doe@example.com');
assertTest('#4: GrievanceRequest captures category and description', ($res['clean']['device_category'] ?? '') === 'PC' && ($res['clean']['grievance_desc'] ?? '') === 'The monitor is flickering intermittently.');

// Validation error checks
$invalidPayload = [
    'submitted_by'    => '',
    'stud_enrollment' => 'bad!chars$$',
    'class'           => '',
    'stud_email'      => 'not-an-email',
    'lab_id'          => '',
    'device_category' => 'InvalidCategory',
    'grievance_desc'  => '',
];
$resInv = $validator->validateSubmit($invalidPayload);
assertTest('#5: GrievanceRequest rejects empty submitted_by', in_array('Your name is required.', $resInv['errors'], true));
assertTest('#6: GrievanceRequest rejects invalid enrollment chars', in_array('Enrollment number must be 3–20 alphanumeric characters (hyphens/slashes allowed).', $resInv['errors'], true));
assertTest('#7: GrievanceRequest rejects empty class', in_array('Class is required.', $resInv['errors'], true));
assertTest('#8: GrievanceRequest rejects invalid email', in_array('A valid email address is required.', $resInv['errors'], true));
assertTest('#9: GrievanceRequest rejects empty lab_id', in_array('Please select a lab.', $resInv['errors'], true));
assertTest('#10: GrievanceRequest rejects invalid device_category', in_array('Please select a valid device category (PC, Printer, Mouse, Keyboard, Monitor, CPU, Other).', $resInv['errors'], true));
assertTest('#11: GrievanceRequest rejects empty grievance_desc', in_array('Grievance description is required.', $resInv['errors'], true));

// Status update validation
$validStatus = $validator->validateStatusUpdate(['status' => 'In Progress', 'admin_note' => 'Investigating hardware']);
assertTest('#12: GrievanceRequest::validateStatusUpdate passes for valid status', empty($validStatus['errors']));
assertTest('#13: GrievanceRequest::validateStatusUpdate captures admin note', ($validStatus['clean']['admin_note'] ?? '') === 'Investigating hardware');

$invalidStatus = $validator->validateStatusUpdate(['status' => 'BogusStatus']);
assertTest('#14: GrievanceRequest::validateStatusUpdate rejects invalid status', !empty($invalidStatus['errors']));

// -----------------------------------------------------------------------------
// Group 2: Router Route Integration
// -----------------------------------------------------------------------------
echo "\n--- Group 2: Router Route Integration ---\n";

$router = new Router();
$router->get('/grievances/submit', [GrievanceController::class, 'submit']);
$router->post('/grievances', [GrievanceController::class, 'store']);
$router->get('/grievances', [GrievanceController::class, 'index']);
$router->get('/grievances/{id}', [GrievanceController::class, 'show']);
$router->post('/grievances/{id}/status', [GrievanceController::class, 'updateStatus']);
$router->post('/grievances/{id}/delete', [GrievanceController::class, 'destroy']);

// Public submit form route
$reqSubmit = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/grievances/submit']);
$resSubmit = $router->dispatch($reqSubmit);
assertTest('#15: Route GET /grievances/submit dispatches and returns 200 HTML', $resSubmit->getStatusCode() === 200 && str_contains($resSubmit->getContent(), 'Submit a Grievance'));

// Route parameter matching
$reqShow = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/grievances/123']);
assertTest('#16: Route GET /grievances/{id} matches pattern correctly', $router !== null);

// -----------------------------------------------------------------------------
// Group 3: Security & CSRF Enforcement
// -----------------------------------------------------------------------------
echo "\n--- Group 3: Security & CSRF Enforcement ---\n";

// POST /grievances without CSRF token must return 403
$reqPostNoCsrf = new Request([], ['submitted_by' => 'Test'], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/grievances']);
$controller = new GrievanceController();
$resStoreNoCsrf = $controller->store($reqPostNoCsrf);
assertTest('#17: POST /grievances without CSRF returns 403 Forbidden', $resStoreNoCsrf->getStatusCode() === 403);

// POST /grievances with invalid form data (with simulated CSRF) returns 422
$reqPostInvalid = new Request([], [
    'csrf_token'   => 'valid-token',
    'submitted_by' => '',
], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/grievances']);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION['csrf_token'] = 'valid-token';
$resStoreInvalid = $controller->store($reqPostInvalid);
assertTest('#18: POST /grievances with invalid data returns 422 Unprocessable', $resStoreInvalid->getStatusCode() === 422);

// Unauthenticated staff routes redirect to login
$codeIndexUnauth = runIsolatedPhp(<<<'PHP'
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/security.php';
use LabTrack\Controller\GrievanceController;
use LabTrack\Http\Request;
$_SESSION = [];
$c = new GrievanceController();
$req = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/grievances']);
$c->index($req);
PHP
);
assertTest('#19: Unauthenticated access to GrievanceController@index triggers login redirection', str_contains($codeIndexUnauth, 'CODE:302') || str_contains($codeIndexUnauth, 'Location: /login') || str_contains($codeIndexUnauth, 'login.php'));

$codeShowUnauth = runIsolatedPhp(<<<'PHP'
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/security.php';
use LabTrack\Controller\GrievanceController;
use LabTrack\Http\Request;
$_SESSION = [];
$c = new GrievanceController();
$req = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/grievances/1']);
$c->show($req, 1);
PHP
);
assertTest('#20: Unauthenticated access to GrievanceController@show triggers login redirection', str_contains($codeShowUnauth, 'CODE:302') || str_contains($codeShowUnauth, 'login.php'));

// Incharge cannot delete grievances (Admin only)
$codeInchargeDelete = runIsolatedPhp(<<<'PHP'
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/security.php';
use LabTrack\Controller\GrievanceController;
use LabTrack\Http\Request;
startSecureSession();
$_SESSION = [
    'logged_in'  => true,
    'user_type'  => 'Incharge',
    'role'       => 'Incharge',
    'user_id'    => 2,
    'full_name'  => 'Lab Incharge',
    'username'   => 'incharge',
    'csrf_token' => 'token123',
];
$c = new GrievanceController();
$req = new Request([], ['csrf_token' => 'token123'], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/grievances/1/delete']);
$c->destroy($req, 1);
PHP
);
assertTest('#21: Incharge attempting to delete a grievance is blocked with 403 Forbidden', str_contains($codeInchargeDelete, 'CODE:403') || str_contains($codeInchargeDelete, '403 Forbidden'));

// -----------------------------------------------------------------------------
// Group 4: GrievanceService & Status Transitions
// -----------------------------------------------------------------------------
echo "\n--- Group 4: GrievanceService & Status Transitions ---\n";

assertTest('#22: GrievanceService class exists', class_exists(GrievanceService::class));
assertTest('#23: GrievanceRepository class exists', class_exists(\LabTrack\Repository\GrievanceRepository::class));

$service = new GrievanceService();
$invalidStatusCaught = false;
try {
    $service->updateStatus(99999, 'InvalidStatusValue');
} catch (\InvalidArgumentException $e) {
    $invalidStatusCaught = true;
}
assertTest('#24: GrievanceService::updateStatus rejects illegal status value with InvalidArgumentException', $invalidStatusCaught);

// -----------------------------------------------------------------------------
// Group 5: Lab Access Scoping & RBAC
// -----------------------------------------------------------------------------
echo "\n--- Group 5: Lab Access Scoping & RBAC ---\n";

// Incharge attempting to view a grievance for a lab they don't manage gets 403
$codeInchargeShowForbidden = runIsolatedPhp(<<<'PHP'
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/security.php';
use LabTrack\Controller\GrievanceController;
use LabTrack\Http\Request;
use LabTrack\Service\GrievanceService;
use LabTrack\Repository\LabRepository;
startSecureSession();
$_SESSION = [
    'logged_in' => true,
    'user_type' => 'Incharge',
    'role'      => 'Incharge',
    'user_id'   => 5,
    'username'  => 'incharge_bob',
    'full_name' => 'Bob Incharge',
];
// Mock service returning a grievance in lab 'LAB-99'
$mockService = new class extends GrievanceService {
    public function __construct() {}
    public function getGrievance(int $id): ?array {
        return [
            'grievance_id'    => $id,
            'lab_id'          => 'LAB-99',
            'submitted_by'    => 'Student X',
            'stud_enrollment' => 'ENR-01',
            'device_category' => 'PC',
            'status'          => 'Submitted',
            'grievance_desc'  => 'Test',
            'submission_date' => '2026-10-15 10:00:00',
        ];
    }
};
// Mock lab repo where Bob cannot access LAB-99
$mockLabRepo = new class extends LabRepository {
    public function __construct() {}
    public function canUserAccessLab(string|int $labId, ?int $userUid = null, bool $isAdmin = false, string $username = '', string $fullName = ''): bool {
        return false;
    }
};
$ctrl = new GrievanceController($mockService, $mockLabRepo);
$req = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/grievances/1']);
$res = $ctrl->show($req, 1);
echo "STATUS:" . $res->getStatusCode();
PHP
);
assertTest('#25: Incharge cannot view grievance in unassigned lab (403 Forbidden)', str_contains($codeInchargeShowForbidden, 'STATUS:403'));

// Incharge attempting to update status for an unassigned lab gets 403
$codeInchargeStatusForbidden = runIsolatedPhp(<<<'PHP'
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/security.php';
use LabTrack\Controller\GrievanceController;
use LabTrack\Http\Request;
use LabTrack\Service\GrievanceService;
use LabTrack\Repository\LabRepository;
startSecureSession();
$_SESSION = [
    'logged_in'  => true,
    'user_type'  => 'Incharge',
    'role'       => 'Incharge',
    'user_id'    => 5,
    'username'   => 'incharge_bob',
    'full_name'  => 'Bob Incharge',
    'csrf_token' => 'valid_csrf_123',
];
$mockService = new class extends GrievanceService {
    public function __construct() {}
    public function getGrievance(int $id): ?array {
        return [
            'grievance_id' => $id,
            'lab_id'       => 'LAB-99',
            'status'       => 'Submitted',
        ];
    }
};
$mockLabRepo = new class extends LabRepository {
    public function __construct() {}
    public function canUserAccessLab(string|int $labId, ?int $userUid = null, bool $isAdmin = false, string $username = '', string $fullName = ''): bool {
        return false;
    }
};
$ctrl = new GrievanceController($mockService, $mockLabRepo);
$req = new Request([], ['csrf_token' => 'valid_csrf_123', 'status' => 'In Progress'], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/grievances/1/status']);
$res = $ctrl->updateStatus($req, 1);
echo "STATUS:" . $res->getStatusCode();
PHP
);
assertTest('#26: Incharge cannot update status for grievance in unassigned lab (403 Forbidden)', str_contains($codeInchargeStatusForbidden, 'STATUS:403'));

// Incharge allowed to view grievance in their assigned lab
$codeInchargeShowAllowed = runIsolatedPhp(<<<'PHP'
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/security.php';
use LabTrack\Controller\GrievanceController;
use LabTrack\Http\Request;
use LabTrack\Service\GrievanceService;
use LabTrack\Repository\LabRepository;
startSecureSession();
$_SESSION = [
    'logged_in' => true,
    'user_type' => 'Incharge',
    'role'      => 'Incharge',
    'user_id'   => 5,
    'username'  => 'incharge_bob',
    'full_name' => 'Bob Incharge',
];
$mockService = new class extends GrievanceService {
    public function __construct() {}
    public function getGrievance(int $id): ?array {
        return [
            'grievance_id'    => $id,
            'lab_id'          => 'LAB-01',
            'lab_name'        => 'Computer Lab 1',
            'submitted_by'    => 'Student X',
            'stud_enrollment' => 'ENR-01',
            'class'           => 'CO-5I',
            'stud_email'      => 'student@example.com',
            'device_category' => 'PC',
            'device_name'     => 'PC-01',
            'status'          => 'Submitted',
            'grievance_desc'  => 'Test problem',
            'submission_date' => '2026-10-15 10:00:00',
            'asset_tag'       => null,
            'admin_note'      => null,
        ];
    }
};
$mockLabRepo = new class extends LabRepository {
    public function __construct() {}
    public function canUserAccessLab(string|int $labId, ?int $userUid = null, bool $isAdmin = false, string $username = '', string $fullName = ''): bool {
        return true;
    }
};
$ctrl = new GrievanceController($mockService, $mockLabRepo);
$req = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/grievances/1']);
$res = $ctrl->show($req, 1);
echo "STATUS:" . $res->getStatusCode() . " HAS_STUDENT:" . (str_contains($res->getContent(), 'Student X') ? 'YES' : 'NO');
PHP
);
assertTest('#27: Incharge can view grievance in assigned lab (200 OK + rendered content)', str_contains($codeInchargeShowAllowed, 'STATUS:200') && str_contains($codeInchargeShowAllowed, 'HAS_STUDENT:YES'));

// -----------------------------------------------------------------------------
// Group 6: View Templates Compilation & Rendering
// -----------------------------------------------------------------------------
echo "\n--- Group 6: View Templates Compilation & Rendering ---\n";

$templates = [
    'templates/grievances/submit.php',
    'templates/grievances/index.php',
    'templates/grievances/view.php',
];

$allTemplatesLint = true;
foreach ($templates as $tmpl) {
    $out = shell_exec('php -l "' . __DIR__ . '/../' . $tmpl . '" 2>&1');
    if (!str_contains((string)$out, 'No syntax errors detected')) {
        $allTemplatesLint = false;
        echo "Template lint error in {$tmpl}: {$out}\n";
    }
}
assertTest('#28: All Grievance templates pass PHP syntax linting', $allTemplatesLint);

// Test rendering index template with View::render
$htmlIndex = View::render('grievances/index', [
    'title'      => 'Grievances Test',
    'grievances' => [
        [
            'grievance_id'    => 101,
            'submitted_by'    => '<script>alert("xss")</script>Student',
            'stud_enrollment' => 'ENR-99',
            'lab_id'          => 'LAB-01',
            'lab_name'        => 'Main Lab',
            'device_category' => 'PC',
            'status'          => 'Submitted',
            'submission_date' => '2026-10-15 12:00:00',
        ],
    ],
    'labs'       => [['lab_id' => 'LAB-01', 'lab_name' => 'Main Lab']],
    'filters'    => ['lab_id' => '', 'status' => '', 'search' => ''],
    'isAdmin'    => false,
    'page'       => 1,
    'totalCount' => 1,
    'limit'      => 25,
    'flash'      => null,
]);
assertTest('#29: Grievance index template renders and escapes user output', str_contains($htmlIndex, '&lt;script&gt;') && !str_contains($htmlIndex, '<script>alert("xss")</script>'));

echo "\n==========================================================\n";
echo "  Results: {$totalPassed} Passed, {$totalFailed} Failed\n";
echo "==========================================================\n";

if ($totalFailed > 0) {
    exit(1);
}
exit(0);

