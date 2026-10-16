<?php

declare(strict_types=1);

namespace LabTrack\Tests\Feature;

use LabTrack\Controller\AssetController;
use LabTrack\Controller\DashboardController;
use LabTrack\Controller\GrievanceController;
use LabTrack\Controller\NotificationController;
use LabTrack\Http\Request;
use LabTrack\Repository\AssetRepository;
use LabTrack\Repository\GrievanceRepository;
use LabTrack\Repository\LabRepository;
use LabTrack\Service\AssetService;
use LabTrack\Service\GrievanceService;
use PHPUnit\Framework\TestCase;

class GrievanceAndDashboardTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION = [];
    }

    public function testPublicGrievanceSubmissionRequiresCsrf(): void
    {
        $mockGrievanceRepo = $this->createMock(GrievanceRepository::class);
        $mockLabRepo = $this->createMock(LabRepository::class);
        $mockGrievanceService = new GrievanceService($mockGrievanceRepo, $mockLabRepo);
        $grievanceCtrl = new GrievanceController($mockGrievanceService, $mockLabRepo);

        // Without CSRF
        $reqNoCsrf = new Request([], [
            'submitted_by' => 'Alice Student',
            'stud_enrollment' => 'EN12345',
            'class' => 'TE-CSE',
            'stud_email' => 'alice@college.edu',
            'lab_id' => 'LAB-A',
            'device_category' => 'Monitor',
            'grievance_desc' => 'Monitor flickers',
        ], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/grievances']);

        $resNoCsrf = $grievanceCtrl->store($reqNoCsrf);
        $this->assertSame(403, $resNoCsrf->getStatusCode());

        // With valid CSRF
        $_SESSION['csrf_token'] = 'valid_token_xyz';
        $mockService = $this->createMock(GrievanceService::class);
        $mockService->expects($this->once())
            ->method('submitGrievance')
            ->willReturn(101);

        $grievanceCtrlWithService = new GrievanceController($mockService, $mockLabRepo);

        $reqValid = new Request([], [
            'csrf_token' => 'valid_token_xyz',
            'submitted_by' => 'Alice Student',
            'stud_enrollment' => 'EN12345',
            'class' => 'TE-CSE',
            'stud_email' => 'alice@college.edu',
            'lab_id' => 'LAB-A',
            'device_category' => 'Monitor',
            'grievance_desc' => 'Monitor flickers intermittently',
        ], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/grievances']);

        $resValid = $grievanceCtrlWithService->store($reqValid);
        $this->assertSame(302, $resValid->getStatusCode());
        $this->assertStringContainsString('submitted=1', (string) $resValid->getHeader('Location'));
    }

    public function testInchargeLabIsolationOnGrievances(): void
    {
        $_SESSION['logged_in'] = true;
        $_SESSION['uid'] = 5;
        $_SESSION['username'] = 'incharge_bob';
        $_SESSION['user_type'] = 'Incharge';
        $_SESSION['role'] = 'Incharge';

        $mockGrievanceRepo = new class () extends GrievanceRepository {
            public function __construct()
            {
            }
            public function findById(int $id): ?array
            {
                return [
                    'grievance_id' => 12,
                    'lab_id' => 'LAB-B', // unassigned lab
                    'submitted_by' => 'Student',
                    'grievance_desc' => 'Broken keyboard',
                    'status' => 'Submitted',
                ];
            }
        };

        $mockLabRepo = new class () extends LabRepository {
            public function __construct()
            {
            }
            public function canUserAccessLab(string $labId, ?int $userUid = null, bool $isAdmin = false, string $username = '', string $fullName = ''): bool
            {
                return $labId === 'LAB-A'; // Only LAB-A is accessible
            }
            public function findAll(?int $userUid = null, bool $isAdmin = false, string $username = '', string $fullName = ''): array
            {
                return [];
            }
        };

        $grievanceService = new GrievanceService($mockGrievanceRepo, $mockLabRepo);
        $grievanceCtrl = new GrievanceController($grievanceService, $mockLabRepo);
        $req = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/grievances/12']);
        $res = $grievanceCtrl->show($req, 12);

        $this->assertSame(403, $res->getStatusCode());
    }

    public function testDashboardKpiIsolationBetweenAdminAndIncharge(): void
    {
        $mockLabRepo = new class () extends LabRepository {
            public bool $wasAdmin = false;
            public function __construct()
            {
            }
            public function summary(?int $userUid = null, bool $isAdmin = false, string $username = '', string $fullName = ''): array
            {
                $this->wasAdmin = $isAdmin;
                return [
                    'total_labs' => $isAdmin ? 10 : 2,
                    'active_labs' => $isAdmin ? 9 : 2,
                    'total_capacity' => $isAdmin ? 300 : 60,
                ];
            }
        };

        $mockAssetRepo = new class () extends AssetRepository {
            public function __construct()
            {
            }
            public function countByStatus(?int $userUid = null, bool $isAdmin = false, string $username = '', string $fullName = ''): array
            {
                return ['Operational' => $isAdmin ? 100 : 20];
            }
            public function countByType(?int $userUid = null, bool $isAdmin = false, string $username = '', string $fullName = ''): array
            {
                return ['Monitor' => $isAdmin ? 50 : 10];
            }
            public function listRecent(int $limit = 10, ?int $userUid = null, bool $isAdmin = false, string $username = '', string $fullName = ''): array
            {
                return [];
            }
        };

        $mockGrievanceRepo = new class () extends GrievanceRepository {
            public function __construct()
            {
            }
            public function countByStatus(?int $userUid = null, bool $isAdmin = false, string $username = '', string $fullName = ''): array
            {
                return ['Pending' => $isAdmin ? 15 : 2];
            }
            public function recentOpen(int $limit = 5, ?int $userUid = null, bool $isAdmin = false, string $username = '', string $fullName = ''): array
            {
                return [];
            }
        };

        // 1. Incharge check
        $_SESSION['logged_in'] = true;
        $_SESSION['uid'] = 3;
        $_SESSION['user_type'] = 'Incharge';
        $_SESSION['role'] = 'Incharge';

        $dashCtrlIncharge = new DashboardController($mockLabRepo, $mockAssetRepo, $mockGrievanceRepo);
        $resIncharge = $dashCtrlIncharge->index(new Request());
        $this->assertSame(200, $resIncharge->getStatusCode());
        $this->assertFalse($mockLabRepo->wasAdmin);
        $this->assertStringContainsString('Lab Incharge', $resIncharge->getContent());

        // 2. Admin check
        $_SESSION['user_type'] = 'Admin';
        $_SESSION['role'] = 'Admin';

        $resAdmin = $dashCtrlIncharge->index(new Request());
        $this->assertSame(200, $resAdmin->getStatusCode());
        $this->assertTrue($mockLabRepo->wasAdmin);
        $this->assertStringContainsString('Administrator', $resAdmin->getContent());
    }

    public function testNotificationsListingAndMarkRead(): void
    {
        $_SESSION['logged_in'] = true;
        $_SESSION['uid'] = 1;
        $_SESSION['role'] = 'Admin';
        $_SESSION['csrf_token'] = 'csrf_notif_123';

        $mockGrievanceRepo = new class () extends GrievanceRepository {
            public function __construct()
            {
            }
            public function recentOpen(int $limit = 25, ?int $userUid = null, bool $isAdmin = false, string $username = '', string $fullName = ''): array
            {
                return [
                    [
                        'grievance_id' => 99,
                        'submitted_by' => 'Bob Smith',
                        'grievance_desc' => 'Broken power plug',
                        'lab_id' => 'LAB-A',
                        'status' => 'Submitted',
                        'submission_date' => '2026-10-16 12:00:00',
                    ],
                ];
            }
        };

        $notifCtrl = new NotificationController($mockGrievanceRepo);
        $resList = $notifCtrl->index(new Request());
        $this->assertSame(200, $resList->getStatusCode());
        $this->assertStringContainsString('Broken power plug', $resList->getContent());

        // Mark read via AJAX
        $reqRead = new Request([], ['csrf_token' => 'csrf_notif_123'], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/notifications/99/read',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);
        $resRead = $notifCtrl->markRead($reqRead, 99);
        $this->assertSame(200, $resRead->getStatusCode());
        $this->assertStringContainsString('"marked":99', $resRead->getContent());
    }

    public function testQrAndPdfExportEndpoints(): void
    {
        $_SESSION['logged_in'] = true;
        $_SESSION['uid'] = 1;
        $_SESSION['role'] = 'Admin';

        $mockAssetService = new class () extends AssetService {
            public function __construct()
            {
            }
            public function getAssetByTag(string $tag, ?int $userUid = null, bool $isAdmin = false, string $username = '', string $fullName = ''): ?array
            {
                return [
                    'asset_id' => 50,
                    'asset_tag' => $tag,
                    'asset_type' => 'Monitor',
                    'lab_id' => 'LAB-A',
                    'seat_code' => 'W-01',
                    'status' => 'Active',
                ];
            }
            public function listAssets(array $filters = [], int $page = 1, int $perPage = 500, ?int $userUid = null, bool $isAdmin = false, string $username = '', string $fullName = ''): array
            {
                return [
                    'items' => [
                        [
                            'asset_id' => 50,
                            'asset_tag' => 'LAB-A-MON-001',
                            'asset_type' => 'Monitor',
                            'lab_id' => 'LAB-A',
                            'seat_code' => 'W-01',
                            'status' => 'Active',
                            'brand_model' => 'Dell P2419H',
                            'serial_number' => 'SN-999',
                        ],
                    ],
                    'total' => 1,
                    'page' => 1,
                    'per_page' => 500,
                    'total_pages' => 1,
                ];
            }
        };

        $mockLabRepo = $this->createMock(LabRepository::class);
        $assetCtrl = new AssetController($mockAssetService, $mockLabRepo);

        // 1. QR Code SVG
        $resQr = $assetCtrl->qr(new Request(), 'LAB-A-MON-001');
        $this->assertSame(200, $resQr->getStatusCode());
        $this->assertSame('image/svg+xml; charset=UTF-8', $resQr->getHeader('Content-Type'));
        $this->assertStringContainsString('data:image/svg+xml;base64,', $resQr->getContent());
        $this->assertStringContainsString('<svg', base64_decode(explode(',', $resQr->getContent())[1] ?? ''));

        // 2. TCPDF PDF Export
        $reqPdf = new Request([], ['lab_id' => 'LAB-A'], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/assets/export-pdf']);
        $resPdf = $assetCtrl->exportPdf($reqPdf);
        $this->assertSame(200, $resPdf->getStatusCode());
        $this->assertSame('application/pdf', $resPdf->getHeader('Content-Type'));
        $this->assertStringStartsWith('%PDF-', $resPdf->getContent());
    }
}
