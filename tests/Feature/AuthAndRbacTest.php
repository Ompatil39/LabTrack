<?php

declare(strict_types=1);

namespace LabTrack\Tests\Feature;

use LabTrack\Controller\AuthController;
use LabTrack\Controller\GrievanceController;
use LabTrack\Controller\LabController;
use LabTrack\Controller\WorkstationController;
use LabTrack\Http\Request;
use LabTrack\Repository\GrievanceRepository;
use LabTrack\Repository\LabRepository;
use LabTrack\Repository\UserRepository;
use LabTrack\Repository\WorkstationRepository;
use LabTrack\Service\AuthService;
use PHPUnit\Framework\TestCase;

class AuthAndRbacTest extends TestCase
{
    private function getMockUserRepo(): UserRepository
    {
        return new class () extends UserRepository {
            public array $users = [];
            public array $failedAttempts = [];
            public array $lockouts = [];

            public function __construct()
            {
                $this->users = [
                    'admin' => [
                        'uid' => 1,
                        'user_id' => 'USR-001',
                        'username' => 'admin',
                        'password' => password_hash('AdminPass@123', PASSWORD_BCRYPT),
                        'email' => 'admin@labtrack.local',
                        'full_name' => 'Admin User',
                        'user_type' => 'Admin',
                        'role' => 'Admin',
                        'must_change_password' => 0,
                    ],
                    'incharge_user' => [
                        'uid' => 2,
                        'user_id' => 'USR-002',
                        'username' => 'incharge_user',
                        'password' => password_hash('InchargePass@123', PASSWORD_BCRYPT),
                        'email' => 'incharge@labtrack.local',
                        'full_name' => 'Lab Incharge',
                        'user_type' => 'Incharge',
                        'role' => 'Incharge',
                        'must_change_password' => 0,
                    ],
                ];
            }

            public function findByUsername(string $username): ?array
            {
                return $this->users[$username] ?? null;
            }

            public function findByUid(int $uid): ?array
            {
                foreach ($this->users as $u) {
                    if ((int) $u['uid'] === $uid) {
                        return $u;
                    }
                }
                return null;
            }

            public function isIpBlocked(string $username, string $ip = '', int &$remainingSeconds = 0): bool
            {
                $key = "{$username}@{$ip}";
                if (isset($this->lockouts[$key]) && $this->lockouts[$key] > time()) {
                    $remainingSeconds = $this->lockouts[$key] - time();
                    return true;
                }
                return false;
            }

            public function recordFailedAttempt(string $username, string $ip = ''): void
            {
                $key = "{$username}@{$ip}";
                $this->failedAttempts[$key] = ($this->failedAttempts[$key] ?? 0) + 1;
                if ($this->failedAttempts[$key] >= 5) {
                    $this->lockouts[$key] = time() + 900;
                }
            }

            public function clearFailedAttempts(string $username, string $ip = ''): void
            {
                $key = "{$username}@{$ip}";
                unset($this->failedAttempts[$key], $this->lockouts[$key]);
            }
        };
    }

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION = [];
    }

    public function testSuccessfulLoginEstablishesSessionAndUid(): void
    {
        $userRepo = $this->getMockUserRepo();
        $authService = new AuthService($userRepo);
        $authCtrl = new AuthController($authService);

        $csrfToken = generateCsrfToken();
        $_SESSION['captcha_answer'] = '14';

        $req = new Request([], [
            'username' => 'admin',
            'password' => 'AdminPass@123',
            'captcha' => '14',
            'csrf_token' => $csrfToken,
        ], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/login']);

        $res = $authCtrl->login($req);

        $this->assertSame(302, $res->getStatusCode());
        $this->assertSame('/dashboard', $res->getHeader('Location'));
        $this->assertTrue($_SESSION['logged_in'] ?? false);
        $this->assertSame(1, $_SESSION['uid'] ?? null);
        $this->assertSame('Admin', $_SESSION['role'] ?? null);
    }

    public function testLoginLockoutEnforcementAfter5FailedAttempts(): void
    {
        $userRepo = $this->getMockUserRepo();
        $authService = new AuthService($userRepo);

        for ($i = 0; $i < 5; $i++) {
            $res = $authService->attemptLogin('admin', 'WrongPassword', '10.0.0.1');
            $this->assertFalse($res['success']);
        }

        $blockedRes = $authService->attemptLogin('admin', 'AdminPass@123', '10.0.0.1');
        $this->assertFalse($blockedRes['success']);
        $this->assertStringContainsString('locked', (string) $blockedRes['error']);
    }

    public function testLoginRejectsMissingOrInvalidCsrf(): void
    {
        $userRepo = $this->getMockUserRepo();
        $authService = new AuthService($userRepo);
        $authCtrl = new AuthController($authService);

        generateCsrfToken();

        $req = new Request([], [
            'username' => 'admin',
            'password' => 'AdminPass@123',
            'csrf_token' => 'bad_token',
        ], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/login']);

        $res = $authCtrl->login($req);
        $this->assertSame(403, $res->getStatusCode());
    }

    public function testInchargeRoleCannotCreateLab(): void
    {
        $_SESSION['logged_in'] = true;
        $_SESSION['uid'] = 2;
        $_SESSION['username'] = 'incharge_user';
        $_SESSION['user_type'] = 'Incharge';
        $_SESSION['role'] = 'Incharge';

        $mockLabRepo = $this->createMock(LabRepository::class);
        $labCtrl = new LabController(null, $mockLabRepo);

        $req = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/labs/create']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('403 Forbidden');
        $labCtrl->create($req);
    }

    public function testInchargeRoleCannotCreateWorkstation(): void
    {
        $_SESSION['logged_in'] = true;
        $_SESSION['uid'] = 2;
        $_SESSION['username'] = 'incharge_user';
        $_SESSION['user_type'] = 'Incharge';
        $_SESSION['role'] = 'Incharge';

        $mockWsRepo = $this->createMock(WorkstationRepository::class);
        $mockLabRepo = $this->createMock(LabRepository::class);
        $wsCtrl = new WorkstationController(null, $mockLabRepo, $mockWsRepo);

        $req = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/workstations/create']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('403 Forbidden');
        $wsCtrl->create($req);
    }

    public function testInchargeRoleCannotDeleteGrievance(): void
    {
        $_SESSION['logged_in'] = true;
        $_SESSION['uid'] = 2;
        $_SESSION['username'] = 'incharge_user';
        $_SESSION['user_type'] = 'Incharge';
        $_SESSION['role'] = 'Incharge';

        $csrf = generateCsrfToken();

        $mockGrievanceRepo = $this->createMock(GrievanceRepository::class);
        $mockLabRepo = $this->createMock(LabRepository::class);
        $grievanceCtrl = new GrievanceController(null, $mockLabRepo, $mockGrievanceRepo);

        $req = new Request([], ['csrf_token' => $csrf], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/grievances/10/delete']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('403 Forbidden');
        $grievanceCtrl->destroy($req, 10);
    }
}
