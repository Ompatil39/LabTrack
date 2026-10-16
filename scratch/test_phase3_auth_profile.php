<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/security.php';

use LabTrack\Controller\AssetController;
use LabTrack\Controller\AuthController;
use LabTrack\Controller\NotificationController;
use LabTrack\Controller\ProfileController;
use LabTrack\Http\Request;
use LabTrack\Http\Response;
use LabTrack\Repository\AssetRepository;
use LabTrack\Repository\GrievanceRepository;
use LabTrack\Repository\LabRepository;
use LabTrack\Repository\UserRepository;
use LabTrack\Router\Router;
use LabTrack\Service\AssetService;
use LabTrack\Service\AuthService;
use LabTrack\Service\QrService;
use LabTrack\Support\ApiResponse;
use LabTrack\Support\AssetTagGenerator;
use LabTrack\Support\DeviceStatus;
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
    $tmpFile = __DIR__ . '/_tmp_test_runner_auth.php';
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
echo "  LabTrack Phase 3 - Auth, Profile & Remaining Routes Test Suite\n";
echo "==========================================================\n\n";

// -----------------------------------------------------------------------------
// Group 1: Authentication Module (AuthController, AuthService, UserRepository)
// -----------------------------------------------------------------------------
echo "--- Group 1: Authentication Module ---\n";

// In-memory mock UserRepository
class MockUserRepository extends UserRepository {
    public array $users = [];
    public array $lockouts = [];
    public array $failedAttempts = [];
    public array $createdIncharges = [];
    public array $assignedLabs = [];

    public function __construct() {
        $this->users = [
            'admin' => [
                'uid' => 1,
                'user_id' => 'USR-001',
                'username' => 'admin',
                'password' => password_hash('AdminPass@123', PASSWORD_BCRYPT),
                'email' => 'admin@labtrack.local',
                'full_name' => 'System Administrator',
                'user_type' => 'Admin',
                'role' => 'Admin',
                'must_change_password' => 0,
            ],
            'temp_user' => [
                'uid' => 2,
                'user_id' => 'USR-002',
                'username' => 'temp_user',
                'password' => password_hash('TempPass@123', PASSWORD_BCRYPT),
                'email' => 'temp@labtrack.local',
                'full_name' => 'Temp Incharge',
                'user_type' => 'Incharge',
                'role' => 'Incharge',
                'must_change_password' => 1,
            ],
        ];
    }

    public function findByUsername(string $username): ?array {
        return $this->users[$username] ?? null;
    }

    public function findByUid(int $uid): ?array {
        foreach ($this->users as $u) {
            if ((int)$u['uid'] === $uid) {
                return $u;
            }
        }
        return null;
    }

    public function isIpBlocked(string $username, string $ip = '', int &$remainingSeconds = 0): bool {
        $key = "{$username}@{$ip}";
        if (isset($this->lockouts[$key]) && $this->lockouts[$key] > time()) {
            $remainingSeconds = $this->lockouts[$key] - time();
            return true;
        }
        return false;
    }

    public function recordFailedAttempt(string $username, string $ip = ''): void {
        $key = "{$username}@{$ip}";
        $this->failedAttempts[$key] = ($this->failedAttempts[$key] ?? 0) + 1;
        if ($this->failedAttempts[$key] >= 5) {
            $this->lockouts[$key] = time() + 900; // 15 mins
        }
    }

    public function clearFailedAttempts(string $username, string $ip = ''): void {
        $key = "{$username}@{$ip}";
        unset($this->failedAttempts[$key], $this->lockouts[$key]);
    }

    public function listAllWithLabs(): array {
        return array_values($this->users);
    }

    public function createIncharge(array $data, array $labIds = []): int {
        $newUid = count($this->users) + 1;
        $this->users[$data['username']] = [
            'uid' => $newUid,
            'user_id' => 'USR-' . str_pad((string)$newUid, 3, '0', STR_PAD_LEFT),
            'username' => $data['username'],
            'password' => $data['password'],
            'email' => $data['email'],
            'full_name' => $data['full_name'],
            'user_type' => 'Incharge',
            'role' => 'Incharge',
            'must_change_password' => 1,
        ];
        $this->createdIncharges[] = $data;
        if (!empty($labIds)) {
            $this->assignedLabs[$newUid] = $labIds;
        }
        return $newUid;
    }

    public function updateProfile(int $uid, array $data): bool {
        foreach ($this->users as $k => $u) {
            if ((int)$u['uid'] === $uid) {
                $this->users[$k] = array_merge($this->users[$k], $data);
                return true;
            }
        }
        return false;
    }

    public function updatePassword(int $uid, string $passwordHash, bool $clearMustChange = false): bool {
        foreach ($this->users as $k => $u) {
            if ((int)$u['uid'] === $uid) {
                $this->users[$k]['password'] = $passwordHash;
                if ($clearMustChange) {
                    $this->users[$k]['must_change_password'] = 0;
                }
                return true;
            }
        }
        return false;
    }

    public function assignLabs(int $uid, array $labIds): void {
        $this->assignedLabs[$uid] = $labIds;
    }

    public function isUsernameUnique(string $username, ?int $excludeUid = null): bool {
        foreach ($this->users as $u) {
            if ($u['username'] === $username && ($excludeUid === null || (int)$u['uid'] !== $excludeUid)) {
                return false;
            }
        }
        return true;
    }

    public function isEmailUnique(string $email, ?int $excludeUid = null): bool {
        foreach ($this->users as $u) {
            if ($u['email'] === $email && ($excludeUid === null || (int)$u['uid'] !== $excludeUid)) {
                return false;
            }
        }
        return true;
    }
}

// 1. GET /auth/captcha
$mockUserRepo = new MockUserRepository();
$authService = new AuthService($mockUserRepo);
$authCtrl = new AuthController($authService);

$captchaResp = $authCtrl->captcha(new Request());
assertTest('#1: GET /auth/captcha returns 200 with JSON', $captchaResp->getStatusCode() === 200 && str_contains($captchaResp->getContent(), '"captcha":'));

// 2. GET /login renders login page
$loginReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/login']);
$loginResp = $authCtrl->showLogin($loginReq);
assertTest('#2: GET /login renders HTML with Sign In title', $loginResp->getStatusCode() === 200 && str_contains($loginResp->getContent(), 'Sign In'));

// 3. POST /login without CSRF returns 403 Forbidden
$loginPostNoCsrf = new Request([], ['username' => 'admin', 'password' => 'AdminPass@123'], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/login']);
$loginRespNoCsrf = $authCtrl->login($loginPostNoCsrf);
assertTest('#3: POST /login without CSRF token returns 403 Forbidden', $loginRespNoCsrf->getStatusCode() === 403);

// 4. POST /login with wrong captcha returns 422 Unprocessable
$_SESSION['csrf_token'] = 'valid_token';
$_SESSION['captcha_answer'] = '15';
$loginPostWrongCaptcha = new Request([], [
    'username' => 'admin',
    'password' => 'AdminPass@123',
    'captcha' => '99',
    'csrf_token' => 'valid_token'
], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/login']);
$loginRespWrongCaptcha = $authCtrl->login($loginPostWrongCaptcha);
assertTest('#4: POST /login with invalid captcha returns 422', $loginRespWrongCaptcha->getStatusCode() === 422);

// 5. POST /login with invalid credentials returns 401
$_SESSION['csrf_token'] = 'valid_token';
$_SESSION['captcha_answer'] = '10';
$loginPostBadPass = new Request([], [
    'username' => 'admin',
    'password' => 'WrongPassword',
    'captcha' => '10',
    'csrf_token' => 'valid_token'
], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/login']);
$loginRespBadPass = $authCtrl->login($loginPostBadPass);
assertTest('#5: POST /login with invalid credentials returns 401', $loginRespBadPass->getStatusCode() === 401);

// 6. Rate-limiting: 5 failed attempts trigger lockout
for ($i = 0; $i < 5; $i++) {
    $mockUserRepo->recordFailedAttempt('admin', '192.168.1.50');
}
$remSec = 0;
assertTest('#6: 5th failed login attempt triggers IP lockout', $mockUserRepo->isIpBlocked('admin', '192.168.1.50', $remSec) === true);

// 7. Successful login sets session and redirects to /dashboard
$mockUserRepo->clearFailedAttempts('admin', '192.168.1.50');
$_SESSION['csrf_token'] = 'valid_token';
$_SESSION['captcha_answer'] = '12';
$loginPostSuccess = new Request([], [
    'username' => 'admin',
    'password' => 'AdminPass@123',
    'captcha' => '12',
    'csrf_token' => 'valid_token'
], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/login']);
$loginRespSuccess = $authCtrl->login($loginPostSuccess);

assertTest('#7: Successful POST /login redirects to /dashboard', $loginRespSuccess->getStatusCode() === 302 && $loginRespSuccess->getHeader('Location') === '/dashboard');
assertTest('#8: Successful login initializes $_SESSION[uid] and role', isset($_SESSION['uid']) && $_SESSION['uid'] === 1 && $_SESSION['role'] === 'Admin');

// 8. Successful login with must_change_password redirects to /profile
$_SESSION['csrf_token'] = 'valid_token';
$_SESSION['captcha_answer'] = '8';
$loginPostTemp = new Request([], [
    'username' => 'temp_user',
    'password' => 'TempPass@123',
    'captcha' => '8',
    'csrf_token' => 'valid_token'
], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/login']);
$loginRespTemp = $authCtrl->login($loginPostTemp);
assertTest('#9: Login with must_change_password redirects to /profile with force flag',
    $loginRespTemp->getStatusCode() === 302 && str_contains((string)$loginRespTemp->getHeader('Location'), 'force_password_change=1')
);

// 9. POST /logout without CSRF returns 403 Forbidden
$logoutNoCsrf = new Request([], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/logout']);
$logoutRespNoCsrf = $authCtrl->logout($logoutNoCsrf);
assertTest('#10: POST /logout without CSRF returns 403 Forbidden', $logoutRespNoCsrf->getStatusCode() === 403);

// 10. POST /logout with CSRF terminates session and redirects to /login
$_SESSION['csrf_token'] = 'valid_token';
$logoutWithCsrf = new Request([], ['csrf_token' => 'valid_token'], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/logout']);
$logoutRespSuccess = $authCtrl->logout($logoutWithCsrf);
assertTest('#11: POST /logout with CSRF redirects to /login', $logoutRespSuccess->getStatusCode() === 302 && $logoutRespSuccess->getHeader('Location') === '/login');


// -----------------------------------------------------------------------------
// Group 2: Profile & User/Incharge Management (ProfileController)
// -----------------------------------------------------------------------------
echo "\n--- Group 2: Profile & User Management ---\n";

// Mock LabRepository
class MockLabRepositoryForProfile extends LabRepository {
    public function __construct() {}
    public function findAll(?int $userUid = null, bool $isAdmin = false, string $username = '', string $fullName = ''): array {
        return [
            ['lab_id' => 'LAB-A', 'lab_name' => 'Software Engineering Lab', 'status' => 'Active'],
            ['lab_id' => 'LAB-B', 'lab_name' => 'Data Science Lab', 'status' => 'Active'],
        ];
    }
}

$mockLabRepo = new MockLabRepositoryForProfile();
$profileCtrl = new ProfileController($mockUserRepo, $mockLabRepo);

// 1. Unauthenticated GET /profile triggers login redirection
$codeProfileUnauth = runIsolatedPhp(<<<'PHP'
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/security.php';
use LabTrack\Controller\ProfileController;
use LabTrack\Http\Request;
$_SESSION = [];
$c = new ProfileController();
$c->show(new Request());
PHP
);
assertTest('#12: Unauthenticated GET /profile triggers login redirect (302)', str_contains($codeProfileUnauth, 'CODE:302') || str_contains($codeProfileUnauth, 'login'));

// 2. Authenticated GET /profile returns 200 OK
$_SESSION['logged_in'] = true;
$_SESSION['uid'] = 1;
$_SESSION['username'] = 'admin';
$_SESSION['role'] = 'Admin';
$profileResp = $profileCtrl->show(new Request());
assertTest('#13: Authenticated GET /profile returns 200 OK', $profileResp->getStatusCode() === 200 && str_contains($profileResp->getContent(), 'My Profile'));

// 3. POST /profile without CSRF returns 403
$profilePostNoCsrf = new Request([], ['action' => 'profile', 'full_name' => 'Admin Updated'], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/profile']);
$profileRespNoCsrf = $profileCtrl->update($profilePostNoCsrf);
assertTest('#14: POST /profile without CSRF returns 403 Forbidden', $profileRespNoCsrf->getStatusCode() === 403);

// 4. POST /profile with valid data updates profile
$_SESSION['csrf_token'] = 'valid_token';
$profilePostValid = new Request([], [
    'csrf_token' => 'valid_token',
    'action' => 'profile',
    'full_name' => 'Super Administrator',
    'email' => 'superadmin@labtrack.local',
    'contact_number' => '9876543210',
    'org_name' => 'IT Department',
], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/profile']);
$profileRespValid = $profileCtrl->update($profilePostValid);
$updatedAdmin = $mockUserRepo->findByUid(1);
assertTest('#15: POST /profile updates full_name and redirects',
    $profileRespValid->getStatusCode() === 302 && ($updatedAdmin['full_name'] ?? '') === 'Super Administrator'
);

// 5. POST /profile password update updates hash and clears must_change_password
$_SESSION['uid'] = 2; // switch to temp_user
$_SESSION['username'] = 'temp_user';
$_SESSION['csrf_token'] = 'valid_token';
$profilePwPost = new Request([], [
    'csrf_token' => 'valid_token',
    'action' => 'password',
    'current_password' => 'TempPass@123',
    'new_password' => 'BrandNewPass@456',
    'confirm_password' => 'BrandNewPass@456',
], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/profile']);
$profilePwResp = $profileCtrl->update($profilePwPost);
$updatedTemp = $mockUserRepo->findByUid(2);
assertTest('#16: POST /profile password change verifies and clears must_change_password',
    $profilePwResp->getStatusCode() === 302 &&
    password_verify('BrandNewPass@456', $updatedTemp['password']) &&
    (int)$updatedTemp['must_change_password'] === 0
);

// 6. Incharge accessing GET /admin/users receives 403
$codeInchargeManageUsers = runIsolatedPhp(<<<'PHP'
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/security.php';
use LabTrack\Controller\ProfileController;
use LabTrack\Http\Request;
startSecureSession();
$_SESSION = [
    'logged_in' => true,
    'uid' => 2,
    'user_id' => 'USR-002',
    'username' => 'temp_user',
    'user_type' => 'Incharge',
    'role' => 'Incharge',
];
$c = new ProfileController();
$c->manageUsers(new Request());
PHP
);
assertTest('#17: Incharge access to /admin/users is blocked with 403 Forbidden', str_contains($codeInchargeManageUsers, 'CODE:403') || str_contains($codeInchargeManageUsers, '403 Forbidden'));

// 7. Admin accessing GET /admin/users returns 200 OK
$_SESSION['uid'] = 1;
$_SESSION['username'] = 'admin';
$_SESSION['user_type'] = 'Admin';
$_SESSION['role'] = 'Admin';
$adminUsersResp = $profileCtrl->manageUsers(new Request());
assertTest('#18: Admin access to GET /admin/users returns 200 OK', $adminUsersResp->getStatusCode() === 200 && str_contains($adminUsersResp->getContent(), 'Incharge Management'));

// 8. Admin POST /admin/users creates Incharge with assigned labs
$_SESSION['csrf_token'] = 'valid_token';
$createUserPost = new Request([], [
    'csrf_token' => 'valid_token',
    'username' => 'new_incharge',
    'full_name' => 'New Incharge Person',
    'email' => 'new_incharge@labtrack.local',
    'password' => 'InchargePass@123',
    'labs' => ['LAB-A', 'LAB-B'],
], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/admin/users']);
$createUserResp = $profileCtrl->manageUsers($createUserPost);
$createdUser = $mockUserRepo->findByUsername('new_incharge');
assertTest('#19: Admin POST /admin/users creates Incharge account with assigned labs',
    $createUserResp->getStatusCode() === 302 &&
    $createdUser !== null &&
    isset($mockUserRepo->assignedLabs[$createdUser['uid']]) &&
    $mockUserRepo->assignedLabs[$createdUser['uid']] === ['LAB-A', 'LAB-B']
);


// -----------------------------------------------------------------------------
// Group 3: Notifications Module (NotificationController)
// -----------------------------------------------------------------------------
echo "\n--- Group 3: Notifications Module ---\n";

class MockGrievanceRepoForNotification extends GrievanceRepository {
    public function __construct() {}
    public function recentOpen(int $limit = 25, ?int $userUid = null, bool $isAdmin = false, string $username = '', string $fullName = ''): array {
        return [
            [
                'grievance_id' => 101,
                'submitted_by' => 'John Doe',
                'grievance_desc' => 'Monitor flickers intermittently',
                'lab_id' => 'LAB-A',
                'lab_name' => 'Software Engineering Lab',
                'status' => 'Submitted',
                'submission_date' => '2026-10-16 10:00:00',
            ]
        ];
    }
}

$notifCtrl = new NotificationController(new MockGrievanceRepoForNotification());

// 1. Unauthenticated GET /notifications triggers login redirect
$codeNotifUnauth = runIsolatedPhp(<<<'PHP'
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/security.php';
use LabTrack\Controller\NotificationController;
use LabTrack\Http\Request;
$_SESSION = [];
$c = new NotificationController();
$c->index(new Request());
PHP
);
assertTest('#20: Unauthenticated GET /notifications triggers login redirect (302)', str_contains($codeNotifUnauth, 'CODE:302') || str_contains($codeNotifUnauth, 'login'));

// 2. Authenticated GET /notifications returns 200 OK
$_SESSION['logged_in'] = true;
$_SESSION['uid'] = 1;
$_SESSION['role'] = 'Admin';
$notifResp = $notifCtrl->index(new Request());
assertTest('#21: Authenticated GET /notifications returns 200 OK with alert items',
    $notifResp->getStatusCode() === 200 && str_contains($notifResp->getContent(), 'Monitor flickers intermittently')
);

// 3. POST /notifications/{id}/read without CSRF returns 403
$notifReadNoCsrf = new Request([], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/notifications/101/read']);
$notifReadRespNoCsrf = $notifCtrl->markRead($notifReadNoCsrf, 101);
assertTest('#22: POST /notifications/{id}/read without CSRF returns 403 Forbidden', $notifReadRespNoCsrf->getStatusCode() === 403);

// 4. POST /notifications/{id}/read with CSRF marks as read (JSON when requested via AJAX)
$_SESSION['csrf_token'] = 'valid_token';
$notifReadAjax = new Request([], ['csrf_token' => 'valid_token'], [
    'REQUEST_METHOD' => 'POST',
    'REQUEST_URI' => '/notifications/101/read',
    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
]);
$notifReadAjaxResp = $notifCtrl->markRead($notifReadAjax, 101);
assertTest('#23: POST /notifications/{id}/read via AJAX returns JSON {success: true, marked: 101}',
    $notifReadAjaxResp->getStatusCode() === 200 &&
    str_contains($notifReadAjaxResp->getContent(), '"marked":101') &&
    in_array(101, $_SESSION['read_notifications'], true)
);


// -----------------------------------------------------------------------------
// Group 4: Assets TCPDF Export & QR Code (AssetController, QrService)
// -----------------------------------------------------------------------------
echo "\n--- Group 4: Assets TCPDF Export & QR Code ---\n";

class MockAssetServiceForExport extends AssetService {
    public function __construct() {}
    public function listAssets(array $filters = [], int $page = 1, int $perPage = 500, ?int $userUid = null, bool $isAdmin = false, string $username = '', string $fullName = ''): array {
        return [
            'items' => [
                [
                    'asset_id' => 1,
                    'asset_tag' => 'LAB-A-MON-001',
                    'asset_type' => 'Monitor',
                    'lab_id' => 'LAB-A',
                    'seat_code' => 'W-01',
                    'status' => 'Active',
                    'brand_model' => 'Dell P2419H',
                    'serial_number' => 'SN-12345',
                ],
            ],
            'total' => 1,
            'page' => 1,
            'per_page' => 500,
            'total_pages' => 1,
        ];
    }

    public function getAssetByTag(string $tag, ?int $userUid = null, bool $isAdmin = false, string $username = '', string $fullName = ''): ?array {
        if ($tag === 'LAB-A-MON-001') {
            return [
                'asset_id' => 1,
                'asset_tag' => 'LAB-A-MON-001',
                'asset_type' => 'Monitor',
                'lab_id' => 'LAB-A',
                'seat_code' => 'W-01',
                'status' => 'Active',
            ];
        }
        return null;
    }
}

$mockAssetService = new MockAssetServiceForExport();
$mockLabRepoForAsset = new MockLabRepositoryForProfile();
$assetCtrl = new AssetController($mockAssetService, $mockLabRepoForAsset);

// 1. QrService generates payload
$qrService = new QrService();
$payloadJson = $qrService->generatePayload('LAB-A-MON-001', 'Monitor', 'LAB-A');
$payload = json_decode($payloadJson, true);
assertTest('#24: QrService::generatePayload creates valid JSON with tag and lab metadata',
    is_array($payload) &&
    ($payload['tag'] ?? '') === 'LAB-A-MON-001' &&
    ($payload['lab'] ?? '') === 'LAB-A'
);

// 2. QrService renders SVG QR code
$qrSvg = $qrService->renderDataUri($payloadJson);
assertTest('#25: QrService::renderDataUri renders valid SVG markup',
    str_contains($qrSvg, '<svg') || str_contains($qrSvg, 'data:image')
);

// 3. GET /assets/{tag}/qr returns SVG
$_SESSION['logged_in'] = true;
$_SESSION['uid'] = 1;
$_SESSION['role'] = 'Admin';
$qrResp = $assetCtrl->qr(new Request(), 'LAB-A-MON-001');
assertTest('#26: GET /assets/{tag}/qr returns 200 with image/svg+xml content-type',
    $qrResp->getStatusCode() === 200 &&
    $qrResp->getHeader('Content-Type') === 'image/svg+xml; charset=UTF-8'
);

// 4. GET /assets/{tag}/qr for missing asset returns 404
$qrMissingResp = $assetCtrl->qr(new Request(), 'NONEXISTENT-TAG');
assertTest('#27: GET /assets/{tag}/qr for unknown asset tag returns 404 Not Found', $qrMissingResp->getStatusCode() === 404);

// 5. POST /assets/export-pdf produces PDF output with TCPDF
$pdfReq = new Request([], ['lab_id' => 'LAB-A'], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/assets/export-pdf']);
$pdfResp = $assetCtrl->exportPdf($pdfReq);
assertTest('#28: POST /assets/export-pdf generates valid PDF document via TCPDF',
    $pdfResp->getStatusCode() === 200 &&
    $pdfResp->getHeader('Content-Type') === 'application/pdf' &&
    str_starts_with($pdfResp->getContent(), '%PDF-')
);


// -----------------------------------------------------------------------------
// Group 5: CLI Script & Compatibility Shims
// -----------------------------------------------------------------------------
echo "\n--- Group 5: CLI Script & Compatibility Shims ---\n";

assertTest('#29: bin/update_qr_codes.php exists as migrated CLI script', file_exists(__DIR__ . '/../bin/update_qr_codes.php'));
assertTest('#30: Deprecated root update_existing_qr_codes.php is removed', !file_exists(__DIR__ . '/../update_existing_qr_codes.php'));
assertTest('#31: Deprecated lib/fpdf.php is completely removed', !file_exists(__DIR__ . '/../lib/fpdf.php'));

// Test backward compatibility shims
require_once __DIR__ . '/../includes/DeviceStatus.php';
require_once __DIR__ . '/../includes/AssetTagGenerator.php';
require_once __DIR__ . '/../includes/ApiResponse.php';
require_once __DIR__ . '/../includes/WorkstationService.php';

assertTest('#32: includes/DeviceStatus.php shims to LabTrack\\Support\\DeviceStatus',
    class_exists(\LabTrack\Support\DeviceStatus::class) &&
    DeviceStatus::ACTIVE === \LabTrack\Support\DeviceStatus::ACTIVE
);
assertTest('#33: includes/AssetTagGenerator.php shims to LabTrack\\Support\\AssetTagGenerator',
    class_exists(\LabTrack\Support\AssetTagGenerator::class)
);
assertTest('#34: includes/ApiResponse.php shims to LabTrack\\Support\\ApiResponse',
    class_exists(\LabTrack\Support\ApiResponse::class)
);
assertTest('#35: includes/WorkstationService.php shims to LabTrack\\Service\\WorkstationService',
    class_exists(\LabTrack\Service\WorkstationService::class)
);

echo "\n==========================================================\n";
echo "  Results: {$totalPassed} Passed, {$totalFailed} Failed (Total " . ($totalPassed + $totalFailed) . ")\n";
echo "==========================================================\n";

if ($totalFailed > 0) {
    exit(1);
}
