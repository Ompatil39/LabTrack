<?php

declare(strict_types=1);

namespace LabTrack\Tests\Feature;

use LabTrack\Controller\AssetController;
use LabTrack\Controller\AuditLogController;
use LabTrack\Controller\GrievanceController;
use LabTrack\Http\Request;
use LabTrack\Repository\AssetRepository;
use LabTrack\Repository\AuditLogRepository;
use LabTrack\Repository\GrievanceRepository;
use LabTrack\Repository\LabRepository;
use LabTrack\Repository\MaintenanceRepository;
use LabTrack\Service\AssetService;
use LabTrack\Service\AuditService;
use LabTrack\Service\GrievanceService;
use LabTrack\Service\MaintenanceService;
use LabTrack\Service\QrService;
use LabTrack\Service\QueueService;
use PHPUnit\Framework\TestCase;

class Phase5CoreUpgradesTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION = [];
    }

    // -----------------------------------------------------------------------
    // 5.1 Audit Log Tests
    // -----------------------------------------------------------------------

    public function testAuditServiceRecordsAction(): void
    {
        $mockAuditRepo = $this->createMock(AuditLogRepository::class);
        $mockAuditRepo->expects($this->once())
            ->method('create')
            ->with($this->callback(function (array $data) {
                return $data['action'] === 'CREATE'
                    && $data['entity_type'] === 'asset'
                    && $data['entity_id'] === 'MON-2026-0001'
                    && $data['new_values'] === ['status' => 'Operational'];
            }))
            ->willReturn(1);

        $auditService = new AuditService($mockAuditRepo);
        $auditService->log([
            'action' => 'CREATE',
            'entity_type' => 'asset',
            'entity_id' => 'MON-2026-0001',
            'old_values' => null,
            'new_values' => ['status' => 'Operational'],
        ]);
    }

    public function testAuditLogRouteRequiresAdmin(): void
    {
        $mockAuditRepo = $this->createMock(AuditLogRepository::class);
        $controller = new AuditLogController(new AuditService($mockAuditRepo));

        // Admin session -> 200 OK
        $_SESSION['logged_in'] = true;
        $_SESSION['uid'] = 1;
        $_SESSION['role'] = 'Admin';
        $mockAuditRepo->method('countAll')->willReturn(1);
        $mockAuditRepo->method('findAll')->willReturn([
            [
                'audit_id' => 1,
                'action' => 'LOGIN',
                'entity_type' => 'user',
                'entity_id' => '1',
                'username' => 'admin',
                'old_values' => null,
                'new_values' => null,
                'created_at' => '2026-10-16 12:00:00',
            ],
        ]);

        $req = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/admin/audit-logs']);
        $adminRes = $controller->index($req);
        $this->assertSame(200, $adminRes->getStatusCode());

        // Incharge session -> throws 403 Forbidden
        $_SESSION['uid'] = 2;
        $_SESSION['role'] = 'Incharge';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('403 Forbidden');
        $controller->index($req);
    }

    // -----------------------------------------------------------------------
    // 5.2 SLA Tickets, Queues, Auto-Assignment & Public Tracking Tests
    // -----------------------------------------------------------------------

    public function testTicketCreationWithSlaAndAutoAssignment(): void
    {
        $mockGrievanceRepo = $this->createMock(GrievanceRepository::class);
        $mockLabRepo = $this->createMock(LabRepository::class);
        $mockQueueService = $this->createMock(QueueService::class);

        // Lab incharge lookup via findById
        $mockLabRepo->method('findById')->with('LAB-A')->willReturn([
            'lab_id' => 'LAB-A',
            'incharge_uid' => 5,
        ]);

        // Ticket creation expectation
        $mockGrievanceRepo->expects($this->once())
            ->method('create')
            ->with($this->callback(function (array $data) {
                return isset($data['tracking_code'], $data['priority'], $data['sla_response_due_at'], $data['sla_resolve_due_at'])
                    && $data['assigned_to_uid'] === 5
                    && str_starts_with((string) $data['tracking_code'], 'TKT-')
                    && $data['priority'] === 'High';
            }))
            ->willReturn(101);

        $mockGrievanceRepo->method('findById')->with(101)->willReturn([
            'grievance_id' => 101,
            'tracking_code' => 'TKT-2026-TEST01',
            'submitted_by' => 'Test Student',
            'stud_email' => 'student@college.edu',
            'grievance_desc' => 'PC power supply burnt',
            'lab_id' => 'LAB-A',
            'device_name' => 'Dell PC',
        ]);

        // Email enqueue
        $mockQueueService->expects($this->once())
            ->method('enqueueEmail')
            ->with(
                $this->equalTo('student@college.edu'),
                $this->stringContains('Ticket Created'),
                $this->anything()
            )
            ->willReturn(1);

        $service = new GrievanceService($mockGrievanceRepo, $mockLabRepo, $mockQueueService);
        $ticketId = $service->submitGrievance([
            'submitted_by' => 'Test Student',
            'stud_enrollment' => 'EN999',
            'class' => 'CS-4',
            'stud_email' => 'student@college.edu',
            'lab_id' => 'LAB-A',
            'device_category' => 'PC',
            'device_name' => 'Dell PC',
            'grievance_desc' => 'PC power supply burnt',
            'priority' => 'High',
        ]);

        $this->assertSame(101, $ticketId);
    }

    public function testPublicTicketTrackingByCode(): void
    {
        $mockGrievanceRepo = $this->createMock(GrievanceRepository::class);
        $mockGrievanceRepo->method('findByTrackingCode')
            ->with('TKT-2026-8F3A9C')
            ->willReturn([
                'grievance_id' => 50,
                'tracking_code' => 'TKT-2026-8F3A9C',
                'status' => 'In Progress',
                'priority' => 'Medium',
                'submitted_by' => 'Alice',
                'lab_id' => 'LAB-B',
                'submission_date' => '2026-10-15 10:00:00',
                'sla_resolve_due_at' => '2026-10-17 10:00:00',
            ]);

        $mockGrievanceRepo->method('getStatusHistory')->with(50)->willReturn([]);
        $mockGrievanceRepo->method('getComments')->with(50, false)->willReturn([
            [
                'comment_id' => 1,
                'author_name' => 'Lab Technician',
                'body' => 'Diagnostic started',
                'created_at' => '2026-10-15 11:00:00',
            ],
        ]);

        $service = new GrievanceService($mockGrievanceRepo);
        $controller = new GrievanceController($service);

        $req = new Request(['format' => 'json'], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/tickets/track/TKT-2026-8F3A9C']);
        $res = $controller->track($req, 'TKT-2026-8F3A9C');

        $this->assertSame(200, $res->getStatusCode());
        $body = json_decode($res->getBody(), true);
        $this->assertTrue($body['success']);
        $this->assertSame('TKT-2026-8F3A9C', $body['data']['tracking_code']);
        $this->assertCount(1, $body['data']['comments']);
    }

    public function testSlaEscalationDetectsAndNotifies(): void
    {
        $mockGrievanceRepo = $this->createMock(GrievanceRepository::class);
        $mockQueueService = $this->createMock(QueueService::class);

        $mockGrievanceRepo->method('getPendingSlaBreaches')->willReturn([
            [
                'grievance_id' => 77,
                'tracking_code' => 'TKT-2026-BREACH',
                'priority' => 'Critical',
                'lab_id' => 'LAB-A',
                'grievance_desc' => 'Main Switch Overheating',
                'sla_resolve_due_at' => '2026-10-16 08:00:00',
            ],
        ]);

        $mockGrievanceRepo->expects($this->once())
            ->method('markEscalated')
            ->with(77)
            ->willReturn(true);

        $mockQueueService->expects($this->once())
            ->method('enqueueEmail')
            ->with(
                $this->anything(),
                $this->stringContains('SLA Breach Alert'),
                $this->anything()
            )
            ->willReturn(1);

        $service = new GrievanceService($mockGrievanceRepo, null, $mockQueueService);
        $count = $service->escalateBreachedSlas();
        $this->assertSame(1, $count);
    }

    // -----------------------------------------------------------------------
    // 5.3 Maintenance Lifecycle, Depreciation & Retire Tests
    // -----------------------------------------------------------------------

    public function testMaintenanceLoggingComputesDowntimeAndSchedulesNext(): void
    {
        $mockMaintRepo = $this->createMock(MaintenanceRepository::class);
        $mockAssetRepo = $this->createMock(AssetRepository::class);

        $mockAssetRepo->method('findById')->with(5)->willReturn([
            'asset_id' => 5,
            'asset_tag' => 'PC-005',
            'lab_id' => 'LAB-A',
            'workstation_id' => 2,
            'status' => 'Under Repair',
        ]);

        $mockMaintRepo->expects($this->once())
            ->method('create')
            ->with($this->callback(function (array $data) {
                // Started at 10:00, completed at 16:30 => 6.5 hours downtime
                return (float) $data['downtime_hours'] === 6.5
                    && (float) $data['cost'] === 75.50
                    && $data['next_scheduled_date'] === '2026-12-01'
                    && $data['maintenance_type'] === 'Repair';
            }))
            ->willReturn(10);

        $service = new MaintenanceService($mockMaintRepo, $mockAssetRepo);
        $recordId = $service->logMaintenance([
            'asset_id' => 5,
            'workstation_id' => 2,
            'lab_id' => 'LAB-A',
            'maintenance_type' => 'Repair',
            'title' => 'PSU Replacement',
            'description' => 'Replaced faulty power supply unit',
            'vendor' => 'Dell Care',
            'cost' => 75.50,
            'started_at' => '2026-10-16 10:00:00',
            'completed_at' => '2026-10-16 16:30:00',
            'next_scheduled_date' => '2026-12-01',
        ]);

        $this->assertSame(10, $recordId);
    }

    public function testStraightLineDepreciationCalculation(): void
    {
        $mockMaintRepo = $this->createMock(MaintenanceRepository::class);
        $mockMaintRepo->method('getWarrantyAlerts')->willReturn([]);
        $mockMaintRepo->method('getAssetsForDepreciation')->willReturn([
            [
                'asset_id' => 1,
                'asset_tag' => 'PC-2024-001',
                'asset_type' => 'PC',
                'purchase_cost' => 1000.00,
                'salvage_value' => 100.00,
                'useful_life_years' => 5,
                // Exactly 2 years ago
                'purchase_date' => date('Y-m-d', strtotime('-2 years')),
            ],
        ]);

        $service = new MaintenanceService($mockMaintRepo);
        $reports = $service->getReports();

        $deprec = $reports['depreciation']['assets'][0];
        // Cost: 1000, Salvage: 100 => Depreciable Base: 900
        // Annual Dep: 900 / 5 = 180 / yr
        // Age: 2.0 yrs => Accum: 360
        // Book Value: 1000 - 360 = 640
        $this->assertSame(1000.00, $deprec['purchase_cost']);
        $this->assertSame(180.00, $deprec['annual_depreciation']);
        $this->assertSame(360.00, round($deprec['accumulated_depreciation'], 2));
        $this->assertSame(640.00, round($deprec['book_value'], 2));
    }

    public function testAssetRetireWorkflowClosesAssignment(): void
    {
        $mockAssetRepo = $this->createMock(AssetRepository::class);
        $mockLabRepo = $this->createMock(LabRepository::class);
        $mockLabRepo->method('canUserAccessLab')->willReturn(true);

        $mockAssetRepo->method('findByTag')->with('PC-TEST')->willReturn([
            'asset_id' => 15,
            'asset_tag' => 'PC-TEST',
            'status' => 'Operational',
            'lab_id' => 'LAB-A',
        ]);

        $mockAssetRepo->expects($this->once())
            ->method('retire')
            ->with(15, 'Hardware Obsolete')
            ->willReturn(true);

        $service = new AssetService($mockAssetRepo, $mockLabRepo);
        $retired = $service->retireAsset('PC-TEST', 'Hardware Obsolete', null, true);

        $this->assertTrue($retired);
    }

    public function testAssetRetireEndpointRequiresAdmin(): void
    {
        $mockAssetRepo = $this->createMock(AssetRepository::class);
        $mockLabRepo = $this->createMock(LabRepository::class);
        $mockAssetService = $this->createMock(AssetService::class);
        $controller = new AssetController($mockAssetService, $mockLabRepo);

        // Incharge session -> 403
        $_SESSION['logged_in'] = true;
        $_SESSION['uid'] = 2;
        $_SESSION['role'] = 'Incharge';
        $_SESSION['csrf_token'] = 'token123';

        $req = new Request([], ['csrf_token' => 'token123', 'reason' => 'Damaged'], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/assets/PC-01/retire']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('403 Forbidden');
        $controller->retire($req, 'PC-01');
    }

    public function testAssetRetireEndpointAllowsAdmin(): void
    {
        $mockAssetRepo = $this->createMock(AssetRepository::class);
        $mockLabRepo = $this->createMock(LabRepository::class);
        $mockAssetService = $this->createMock(AssetService::class);
        $controller = new AssetController($mockAssetService, $mockLabRepo);

        $_SESSION['logged_in'] = true;
        $_SESSION['uid'] = 1;
        $_SESSION['role'] = 'Admin';
        $_SESSION['csrf_token'] = 'token123';

        $mockAssetService->method('retireAsset')->with('PC-01', 'Damaged')->willReturn(true);

        $reqJson = new Request(['format' => 'json'], ['csrf_token' => 'token123', 'reason' => 'Damaged'], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/assets/PC-01/retire']);
        $resAdmin = $controller->retire($reqJson, 'PC-01');
        $this->assertSame(200, $resAdmin->getStatusCode());
    }

    // -----------------------------------------------------------------------
    // 5.4 Mobile QR Landing & PWA Tests
    // -----------------------------------------------------------------------

    public function testQrServiceGeneratesShortUrlPayload(): void
    {
        $qrService = new QrService('http://localhost:8000');
        $payload = $qrService->generatePayload('MON-2026-0004');

        $this->assertSame('http://localhost:8000/a/MON-2026-0004', $payload);
    }

    public function testMobileLandingReturnsPrefilledCard(): void
    {
        $mockAssetRepo = $this->createMock(AssetRepository::class);
        $mockLabRepo = $this->createMock(LabRepository::class);
        $mockLabRepo->method('canUserAccessLab')->willReturn(true);

        $mockAssetRepo->method('findByTag')->with('MON-2026-0004')->willReturn([
            'asset_id' => 4,
            'asset_tag' => 'MON-2026-0004',
            'asset_type' => 'Monitor',
            'brand' => 'Dell',
            'model' => 'P2419H',
            'status' => 'Operational',
            'lab_id' => 'LAB-A',
            'workstation_id' => 12,
            'seat_code' => 'A-04',
        ]);

        $assetService = new AssetService($mockAssetRepo, $mockLabRepo);
        $controller = new AssetController($assetService, $mockLabRepo);

        $req = new Request(['format' => 'json'], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/a/MON-2026-0004']);
        $res = $controller->mobileLanding($req, 'MON-2026-0004');

        $this->assertSame(200, $res->getStatusCode());
        $body = json_decode($res->getBody(), true);
        $this->assertTrue($body['success']);
        $this->assertSame('MON-2026-0004', $body['data']['asset']['asset_tag']);
        $this->assertStringContainsString('asset_tag=MON-2026-0004', $body['data']['report_url']);
        $this->assertStringContainsString('workstation_id=12', $body['data']['report_url']);
    }

    public function testPwaManifestAndServiceWorkerFilesExist(): void
    {
        $manifestPath = dirname(__DIR__, 2) . '/public/manifest.json';
        $swPath = dirname(__DIR__, 2) . '/public/sw.js';

        $this->assertFileExists($manifestPath);
        $this->assertFileExists($swPath);

        $manifestContent = file_get_contents($manifestPath);
        $this->assertIsString($manifestContent);
        $manifestJson = json_decode($manifestContent, true);
        $this->assertIsArray($manifestJson);
        $this->assertSame('LabTrack Lab Management', $manifestJson['name']);
        $this->assertSame('standalone', $manifestJson['display']);
    }
}
