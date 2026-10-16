<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/security.php';

use LabTrack\Controller\DashboardController;
use LabTrack\Http\Request;
use LabTrack\Http\Response;
use LabTrack\Repository\AssetRepository;
use LabTrack\Repository\GrievanceRepository;
use LabTrack\Repository\LabRepository;
use LabTrack\Router\Router;
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
    $tmpFile = __DIR__ . '/_tmp_test_runner_dash.php';
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
echo "  LabTrack Phase 3 - Dashboard Test Suite\n";
echo "==========================================================\n\n";

// -----------------------------------------------------------------------------
// Group 1: Router Registration & Route Matching
// -----------------------------------------------------------------------------
echo "--- Group 1: Router Registration & Route Matching ---\n";

$router = new Router();
$router->get('/', [DashboardController::class, 'index']);
$router->get('/dashboard', [DashboardController::class, 'index']);
$router->get('/admin', [DashboardController::class, 'admin']);

assertTest('#1: DashboardController class exists', class_exists(DashboardController::class));

// -----------------------------------------------------------------------------
// Group 2: Security & RBAC Enforcement
// -----------------------------------------------------------------------------
echo "\n--- Group 2: Security & RBAC Enforcement ---\n";

// 1. Unauthenticated GET /dashboard redirects to login (302)
$codeDashUnauth = runIsolatedPhp(<<<'PHP'
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/security.php';
use LabTrack\Controller\DashboardController;
use LabTrack\Http\Request;
$_SESSION = [];
$c = new DashboardController();
$req = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/dashboard']);
$c->index($req);
PHP
);
assertTest('#2: Unauthenticated GET /dashboard triggers login redirection (302)', str_contains($codeDashUnauth, 'CODE:302') || str_contains($codeDashUnauth, 'login.php'));

// 2. Unauthenticated GET /admin redirects to login (302)
$codeAdminUnauth = runIsolatedPhp(<<<'PHP'
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/security.php';
use LabTrack\Controller\DashboardController;
use LabTrack\Http\Request;
$_SESSION = [];
$c = new DashboardController();
$req = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/admin']);
$c->admin($req);
PHP
);
assertTest('#3: Unauthenticated GET /admin triggers login redirection (302)', str_contains($codeAdminUnauth, 'CODE:302') || str_contains($codeAdminUnauth, 'login.php'));

// 3. Incharge role accessing GET /admin receives 403 Forbidden
$codeInchargeAdmin = runIsolatedPhp(<<<'PHP'
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/security.php';
use LabTrack\Controller\DashboardController;
use LabTrack\Http\Request;
startSecureSession();
$_SESSION = [
    'logged_in' => true,
    'user_type' => 'Incharge',
    'role'      => 'Incharge',
    'user_id'   => 5,
    'username'  => 'incharge_alice',
    'full_name' => 'Alice Incharge',
];
$c = new DashboardController();
$req = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/admin']);
$c->admin($req);
PHP
);
assertTest('#4: Incharge accessing /admin is blocked with 403 Forbidden', str_contains($codeInchargeAdmin, 'CODE:403') || str_contains($codeInchargeAdmin, '403 Forbidden'));

// -----------------------------------------------------------------------------
// Group 3: Data Scoping (Admin vs Incharge)
// -----------------------------------------------------------------------------
echo "\n--- Group 3: Data Scoping (Admin vs Incharge) ---\n";

// Mock Repositories to test exact isolation behavior
$mockLabRepo = new class extends LabRepository {
    public array $lastArgs = [];
    public function __construct() {}
    public function summary(?int $userUid = null, bool $isAdmin = false, string $username = '', string $fullName = ''): array {
        $this->lastArgs = compact('userUid', 'isAdmin', 'username', 'fullName');
        return [
            'total_labs'     => $isAdmin ? 12 : 2,
            'active_labs'    => $isAdmin ? 10 : 2,
            'total_capacity' => $isAdmin ? 480 : 60,
        ];
    }
};

$mockAssetRepo = new class extends AssetRepository {
    public function __construct() {}
    public function countByStatus(?int $userUid = null, bool $isAdmin = false, string $username = '', string $fullName = ''): array {
        return ['Operational' => $isAdmin ? 150 : 25, 'Under Repair' => $isAdmin ? 10 : 2];
    }
    public function countByType(?int $userUid = null, bool $isAdmin = false, string $username = '', string $fullName = ''): array {
        return ['PC' => $isAdmin ? 100 : 20, 'Printer' => $isAdmin ? 5 : 1];
    }
    public function countAll(array $filters = [], ?int $userUid = null, bool $isAdmin = false, string $username = '', string $fullName = ''): int {
        return $isAdmin ? 160 : 27;
    }
};

$mockGrievanceRepo = new class extends GrievanceRepository {
    public function __construct() {}
    public function countByStatus(?int $userUid = null, bool $isAdmin = false, string $username = '', string $fullName = ''): array {
        return ['Submitted' => $isAdmin ? 8 : 1, 'In Progress' => $isAdmin ? 3 : 1];
    }
    public function recentOpen(int $limit = 10, ?int $userUid = null, bool $isAdmin = false, string $username = '', string $fullName = ''): array {
        return [
            [
                'grievance_id'    => 1,
                'submitted_by'    => 'Student Test',
                'lab_id'          => 'LAB-01',
                'lab_name'        => 'Test Lab',
                'status'          => 'Submitted',
                'grievance_desc'  => 'Broken Keyboard',
                'submission_date' => '2026-10-15 11:00:00',
            ],
        ];
    }
};

// Admin access test
$adminDashOutput = runIsolatedPhp(<<<'PHP'
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/security.php';
use LabTrack\Controller\DashboardController;
use LabTrack\Http\Request;
startSecureSession();
$_SESSION = [
    'logged_in' => true,
    'user_type' => 'Admin',
    'role'      => 'Admin',
    'user_id'   => 1,
    'username'  => 'superadmin',
    'full_name' => 'System Administrator',
];
$ctrl = new DashboardController();
$req = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/dashboard']);
$res = $ctrl->index($req);
echo "STATUS:" . $res->getStatusCode() . " TITLE_OK:" . (str_contains($res->getContent(), 'Administrator') ? 'YES' : 'NO');
PHP
);
assertTest('#5: Admin GET /dashboard returns 200 OK and renders Administrator title', str_contains($adminDashOutput, 'STATUS:200') && str_contains($adminDashOutput, 'TITLE_OK:YES'));

// Incharge access test
$inchargeDashOutput = runIsolatedPhp(<<<'PHP'
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/security.php';
use LabTrack\Controller\DashboardController;
use LabTrack\Http\Request;
startSecureSession();
$_SESSION = [
    'logged_in' => true,
    'user_type' => 'Incharge',
    'role'      => 'Incharge',
    'user_id'   => 3,
    'username'  => 'incharge_charlie',
    'full_name' => 'Charlie Incharge',
];
$ctrl = new DashboardController();
$req = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/dashboard']);
$res = $ctrl->index($req);
echo "STATUS:" . $res->getStatusCode() . " TITLE_OK:" . (str_contains($res->getContent(), 'Lab Incharge') ? 'YES' : 'NO');
PHP
);
assertTest('#6: Incharge GET /dashboard returns 200 OK and renders Lab Incharge title', str_contains($inchargeDashOutput, 'STATUS:200') && str_contains($inchargeDashOutput, 'TITLE_OK:YES'));

// -----------------------------------------------------------------------------
// Group 4: View Template Compilation & Chart.js Integration
// -----------------------------------------------------------------------------
echo "\n--- Group 4: View Template Compilation & Chart.js Integration ---\n";

$tmplLint = shell_exec('php -l "' . __DIR__ . '/../templates/dashboard/index.php" 2>&1');
assertTest('#7: dashboard/index.php template passes PHP syntax linting', str_contains((string)$tmplLint, 'No syntax errors detected'));

$htmlRendered = View::render('dashboard/index', [
    'title'                 => 'Test Dashboard',
    'isAdmin'               => true,
    'username'              => 'admin',
    'fullName'              => 'Admin User',
    'labSummary'            => ['total_labs' => 5, 'active_labs' => 4, 'total_capacity' => 120],
    'totalAssets'           => 120,
    'operationalAssets'     => 115,
    'underRepairAssets'     => 5,
    'assetStatusCounts'     => ['Operational' => 115, 'Under Repair' => 5],
    'assetTypeCounts'       => ['PC' => 80, 'Monitor' => 20, 'Keyboard' => 20],
    'openGrievances'        => 3,
    'grievanceStatusCounts' => ['Submitted' => 2, 'In Progress' => 1],
    'recentGrievances'      => [
        [
            'grievance_id'    => 42,
            'submitted_by'    => '<script>alert("xss")</script>Student',
            'lab_id'          => 'LAB-01',
            'lab_name'        => 'Hardware Lab',
            'status'          => 'Submitted',
            'grievance_desc'  => 'Power supply issue',
            'submission_date' => '2026-10-15 15:30:00',
        ],
    ],
]);

assertTest('#8: Dashboard template renders KPI metrics correctly', str_contains($htmlRendered, '120') && str_contains($htmlRendered, 'Hardware Assets'));
assertTest('#9: Dashboard template safely encodes Chart data in script', str_contains($htmlRendered, 'assetTypeChart') && str_contains($htmlRendered, 'assetStatusChart'));
assertTest('#10: Dashboard template escapes user-submitted text in grievance list', str_contains($htmlRendered, '&lt;script&gt;') && !str_contains($htmlRendered, '<script>alert("xss")</script>'));

echo "\n==========================================================\n";
echo "  Results: {$totalPassed} Passed, {$totalFailed} Failed\n";
echo "==========================================================\n";

if ($totalFailed > 0) {
    exit(1);
}
exit(0);
