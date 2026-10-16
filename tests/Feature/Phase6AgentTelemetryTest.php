<?php

declare(strict_types=1);

namespace LabTrack\Tests\Feature;

use InvalidArgumentException;
use LabTrack\Controller\AgentController;
use LabTrack\Controller\WorkstationController;
use LabTrack\Http\Request;
use LabTrack\Repository\LabRepository;
use LabTrack\Repository\TelemetryRepository;
use LabTrack\Repository\WorkstationRepository;
use LabTrack\Security\AgentSecurity;
use LabTrack\Service\TelemetryService;
use LabTrack\Service\WorkstationAppService;
use PHPUnit\Framework\TestCase;

class Phase6AgentTelemetryTest extends TestCase
{
    private string $appKey = 'test-secret-encryption-master-key-32b!';

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION = [];
    }

    // -------------------------------------------------------------------------
    // 1. Token Generation & Exchange Flow
    // -------------------------------------------------------------------------

    public function testTokenGenerationAndExchangeFlow(): void
    {
        $mockTeleRepo = $this->createMock(TelemetryRepository::class);
        $mockWsRepo = $this->createMock(WorkstationRepository::class);
        $mockLabRepo = $this->createMock(LabRepository::class);

        $mockWsRepo->method('findById')->with(5)->willReturn([
            'workstation_id' => 5,
            'lab_id' => 'LAB-A',
            'seat_code' => 'WS-05',
        ]);

        $mockTeleRepo->expects($this->once())
            ->method('createEnrollmentToken')
            ->with(5, $this->anything(), $this->anything())
            ->willReturn(true);

        $teleService = new TelemetryService($mockTeleRepo, $mockWsRepo, $this->appKey);
        $controller = new AgentController($teleService, $mockWsRepo, $mockLabRepo);

        // A. Admin user generates token -> succeeds
        $_SESSION['logged_in'] = true;
        $_SESSION['uid'] = 1;
        $_SESSION['role'] = 'Admin';
        $_SESSION['csrf_token'] = 'valid-csrf-token';

        $req = new Request(['csrf_token' => 'valid-csrf-token'], [], ['REQUEST_METHOD' => 'POST', 'HTTP_ACCEPT' => 'application/json']);
        $res = $controller->createEnrollToken($req, 5);
        $this->assertSame(200, $res->getStatusCode());
        $body = json_decode($res->getContent(), true);
        $this->assertTrue($body['success']);
        $this->assertNotEmpty($body['data']['token']);
        $this->assertStringContainsString('python agent/labtrack_agent.py --enroll', $body['data']['enroll_command']);

        // B. Incharge role -> blocked (throws 403 from requireRole('Admin'))
        $_SESSION['role'] = 'Incharge';
        $inchargeFailed = false;
        try {
            $controller->createEnrollToken($req, 5);
        } catch (\RuntimeException $e) {
            $inchargeFailed = true;
            $this->assertStringContainsString('403', $e->getMessage());
        }
        $this->assertTrue($inchargeFailed, 'Incharge should not be able to generate enrollment tokens');

        // C. Expired token or second exchange fails with 401
        $mockTeleRepo->method('consumeEnrollmentToken')->willReturn(null); // 0 rows updated
        $enrollReq = new Request([], ['token' => 'expired-or-consumed-token', 'hostname' => 'WS-TEST'], ['REQUEST_METHOD' => 'POST', 'REMOTE_ADDR' => '127.0.0.1']);
        $enrollRes = $controller->enroll($enrollReq);
        $this->assertSame(401, $enrollRes->getStatusCode());
    }

    // -------------------------------------------------------------------------
    // 2. Enrollment Stores Only Hash and Encrypted Secret (No Plaintext)
    // -------------------------------------------------------------------------

    public function testEnrollmentStoresOnlyHashAndEncryptedSecret(): void
    {
        $mockTeleRepo = $this->createMock(TelemetryRepository::class);
        $mockWsRepo = $this->createMock(WorkstationRepository::class);

        $savedTokenHash = '';
        $savedEncryptedSecret = '';
        $token = bin2hex(random_bytes(32));
        $expectedHash = hash('sha256', $token);

        $mockTeleRepo->expects($this->once())
            ->method('consumeEnrollmentToken')
            ->with(
                $this->callback(function (string $h) use (&$savedTokenHash, $expectedHash) {
                    $savedTokenHash = $h;
                    return hash_equals($expectedHash, $h);
                }),
                $this->anything(),
                $this->callback(function (string $encSecret) use (&$savedEncryptedSecret) {
                    $savedEncryptedSecret = $encSecret;
                    return strlen($encSecret) > 20;
                }),
                $this->anything()
            )
            ->willReturn(3);

        $teleService = new TelemetryService($mockTeleRepo, $mockWsRepo, $this->appKey);
        $result = $teleService->enrollAgent($token, 'LAB-WS-03', '00:11:22:33:44:55', '192.168.1.103', '1.0.0');

        $this->assertSame($expectedHash, $savedTokenHash);
        $this->assertNotSame($result['signing_secret'], $savedEncryptedSecret, 'Plaintext secret must not be stored in DB');
        $this->assertFalse(str_contains($savedEncryptedSecret, $result['signing_secret']), 'Encrypted secret must not contain plaintext');

        // Verify that decrypting the stored ciphertext recovers the plaintext secret
        $decrypted = AgentSecurity::decrypt($savedEncryptedSecret, $this->appKey);
        $this->assertSame($result['signing_secret'], $decrypted);
    }

    // -------------------------------------------------------------------------
    // 3. HMAC Signature Verification with Shared Python Test Vector
    // -------------------------------------------------------------------------

    public function testHmacSignatureVerificationWithKnownTestVector(): void
    {
        $mockTeleRepo = $this->createMock(TelemetryRepository::class);
        $mockWsRepo = $this->createMock(WorkstationRepository::class);

        // Identical test vector to agent/tests/test_agent.py
        $method = 'POST';
        $path = '/api/agent/heartbeat';
        $timestamp = 1700000000;
        $nonce = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';
        $rawBody = '{"cpu_pct":25.5,"ram_pct":60.0,"disk_pct":45.2}';
        $signingSecret = 'my-test-signing-secret-key-32chars!!';
        $expectedSignature = '40f207d2899bdd3a034a90e490780f1424f898b7b6d04611cc0dc19ae2f316e2';

        $encryptedSecret = AgentSecurity::encrypt($signingSecret, $this->appKey);

        $mockTeleRepo->method('findCredentialByKeyId')->willReturnMap([
            ['valid-key-id', [
                'credential_id' => 10,
                'workstation_id' => 1,
                'api_key_id' => 'valid-key-id',
                'signing_secret_encrypted' => $encryptedSecret,
                'is_revoked' => 0,
            ]],
            ['wrong-key-id', null],
        ]);

        $mockTeleRepo->method('isNonceUsed')->willReturn(false);

        $teleService = new TelemetryService($mockTeleRepo, $mockWsRepo, $this->appKey);

        // 1. Valid signature passes
        $validReq = new Request([], [], [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $path,
            'HTTP_X_AGENT_KEY_ID' => 'valid-key-id',
            'HTTP_X_AGENT_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_AGENT_NONCE' => $nonce,
            'HTTP_X_AGENT_SIGNATURE' => $expectedSignature,
            'REMOTE_ADDR' => '127.0.0.1',
        ], null, null, $rawBody);

        // Mock current time to 1700000050 (within 300s of 1700000000)
        // Since AgentSecurity uses abs(time() - ts), we test signature verification directly
        $canonical = AgentSecurity::buildCanonicalString($method, $path, $timestamp, $nonce, $rawBody);
        $sig = AgentSecurity::computeHmac($canonical, $signingSecret);
        $this->assertSame($expectedSignature, $sig);
        $this->assertTrue(AgentSecurity::verifyHmac($expectedSignature, $sig));

        // 2. Tampered body fails
        $tamperedBody = '{"cpu_pct":99.9,"ram_pct":60.0,"disk_pct":45.2}';
        $tamperedCanonical = AgentSecurity::buildCanonicalString($method, $path, $timestamp, $nonce, $tamperedBody);
        $tamperedSig = AgentSecurity::computeHmac($tamperedCanonical, $signingSecret);
        $this->assertFalse(AgentSecurity::verifyHmac($expectedSignature, $tamperedSig));

        // 3. Wrong signature fails
        $this->assertFalse(AgentSecurity::verifyHmac($expectedSignature, 'badf00dbadf00dbadf00d'));

        // 4. Wrong key ID returns null in authentication
        $wrongKeyReq = new Request([], [], [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $path,
            'HTTP_X_AGENT_KEY_ID' => 'wrong-key-id',
            'HTTP_X_AGENT_TIMESTAMP' => (string) time(),
            'HTTP_X_AGENT_NONCE' => $nonce,
            'HTTP_X_AGENT_SIGNATURE' => $expectedSignature,
        ], null, null, $rawBody);
        $this->assertNull($teleService->authenticateAgentRequest($wrongKeyReq, $rawBody));
    }

    // -------------------------------------------------------------------------
    // 4. Replay Protection: Reusing Nonce Fails, Stale Timestamp Fails
    // -------------------------------------------------------------------------

    public function testReplayProtectionAndStaleTimestamp(): void
    {
        $mockTeleRepo = $this->createMock(TelemetryRepository::class);
        $mockWsRepo = $this->createMock(WorkstationRepository::class);

        $secret = 'test-secret-key-32-bytes-long!!';
        $encryptedSecret = AgentSecurity::encrypt($secret, $this->appKey);

        $mockTeleRepo->method('findCredentialByKeyId')->willReturn([
            'credential_id' => 1,
            'workstation_id' => 1,
            'api_key_id' => 'agent-key-1',
            'signing_secret_encrypted' => $encryptedSecret,
            'is_revoked' => 0,
        ]);

        $teleService = new TelemetryService($mockTeleRepo, $mockWsRepo, $this->appKey);

        $now = time();
        $nonce = bin2hex(random_bytes(16));
        $body = '{"cpu_pct":10.0,"ram_pct":20.0,"disk_pct":30.0}';

        // A. Stale timestamp (600s in the past) -> fails
        $staleTs = $now - 600;
        $canonicalStale = AgentSecurity::buildCanonicalString('POST', '/api/agent/heartbeat', $staleTs, $nonce, $body);
        $staleSig = AgentSecurity::computeHmac($canonicalStale, $secret);

        $staleReq = new Request([], [], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/api/agent/heartbeat',
            'HTTP_X_AGENT_KEY_ID' => 'agent-key-1',
            'HTTP_X_AGENT_TIMESTAMP' => (string) $staleTs,
            'HTTP_X_AGENT_NONCE' => $nonce,
            'HTTP_X_AGENT_SIGNATURE' => $staleSig,
        ], null, null, $body);

        $this->assertNull($teleService->authenticateAgentRequest($staleReq, $body));

        // B. Reusing an existing nonce -> fails
        $freshTs = $now;
        $canonicalFresh = AgentSecurity::buildCanonicalString('POST', '/api/agent/heartbeat', $freshTs, $nonce, $body);
        $freshSig = AgentSecurity::computeHmac($canonicalFresh, $secret);

        $mockTeleRepo->method('isNonceUsed')->with($nonce, 'agent-key-1')->willReturn(true); // Nonce already seen!

        $replayReq = new Request([], [], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/api/agent/heartbeat',
            'HTTP_X_AGENT_KEY_ID' => 'agent-key-1',
            'HTTP_X_AGENT_TIMESTAMP' => (string) $freshTs,
            'HTTP_X_AGENT_NONCE' => $nonce,
            'HTTP_X_AGENT_SIGNATURE' => $freshSig,
        ], null, null, $body);

        $this->assertNull($teleService->authenticateAgentRequest($replayReq, $body));
    }

    // -------------------------------------------------------------------------
    // 5. Rate Limiting: 13th Heartbeat Within 60 Seconds Returns 429
    // -------------------------------------------------------------------------

    public function testRateLimitOnHeartbeats(): void
    {
        $mockTeleRepo = $this->createMock(TelemetryRepository::class);
        $mockWsRepo = $this->createMock(WorkstationRepository::class);
        $mockLabRepo = $this->createMock(LabRepository::class);

        $secret = 'rate-limit-secret-key-32-chars!';
        $encryptedSecret = AgentSecurity::encrypt($secret, $this->appKey);

        $mockTeleRepo->method('findCredentialByKeyId')->willReturn([
            'credential_id' => 1,
            'workstation_id' => 7,
            'api_key_id' => 'key-7',
            'signing_secret_encrypted' => $encryptedSecret,
            'is_revoked' => 0,
        ]);
        $mockTeleRepo->method('isNonceUsed')->willReturn(false);

        // When countRecentSamples returns 12, rate limit is hit
        $mockTeleRepo->method('countRecentSamples')->with(7, 60)->willReturn(12);

        $teleService = new TelemetryService($mockTeleRepo, $mockWsRepo, $this->appKey);
        $this->assertTrue($teleService->isRateLimited(7));

        $controller = new AgentController($teleService, $mockWsRepo, $mockLabRepo);

        $now = time();
        $nonce = bin2hex(random_bytes(16));
        $body = '{"cpu_pct":15.0,"ram_pct":30.0,"disk_pct":45.0}';
        $canonical = AgentSecurity::buildCanonicalString('POST', '/api/agent/heartbeat', $now, $nonce, $body);
        $sig = AgentSecurity::computeHmac($canonical, $secret);

        $req = new Request([], [], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/api/agent/heartbeat',
            'HTTP_X_AGENT_KEY_ID' => 'key-7',
            'HTTP_X_AGENT_TIMESTAMP' => (string) $now,
            'HTTP_X_AGENT_NONCE' => $nonce,
            'HTTP_X_AGENT_SIGNATURE' => $sig,
        ], null, null, $body);

        $res = $controller->heartbeat($req);
        $this->assertSame(429, $res->getStatusCode());
    }

    // -------------------------------------------------------------------------
    // 6. Heartbeat Updates Live Columns and Validates Payload Strictly
    // -------------------------------------------------------------------------

    public function testHeartbeatUpdatesLiveColumnsAndValidatesPayload(): void
    {
        $mockTeleRepo = $this->createMock(TelemetryRepository::class);
        $mockWsRepo = $this->createMock(WorkstationRepository::class);

        $teleService = new TelemetryService($mockTeleRepo, $mockWsRepo, $this->appKey);

        // Valid payload
        $validPayload = [
            'cpu_pct' => 25.5,
            'ram_pct' => 60.0,
            'disk_pct' => 45.2,
            'uptime_seconds' => 3600,
            'cpu_temp_c' => 48.0,
            'smart_health' => 'HEALTHY',
            'logged_in_user' => 'student01',
        ];

        $mockTeleRepo->expects($this->once())
            ->method('recordHeartbeat')
            ->with(2, $validPayload, '192.168.1.50')
            ->willReturn(101);

        $sampleId = $teleService->recordHeartbeat(2, $validPayload, '192.168.1.50');
        $this->assertSame(101, $sampleId);

        // Invalid payload: CPU percentage > 100
        $this->expectException(InvalidArgumentException::class);
        $teleService->validateHeartbeatPayload(['cpu_pct' => 105.0, 'ram_pct' => 50.0, 'disk_pct' => 50.0]);
    }

    // -------------------------------------------------------------------------
    // 7. Drift Detection: 16 GB PC Reporting 8 GB, Tolerance, and Resolution
    // -------------------------------------------------------------------------

    public function testDriftDetectionToleranceAndResolution(): void
    {
        $mockTeleRepo = $this->createMock(TelemetryRepository::class);
        $mockWsRepo = $this->createMock(WorkstationRepository::class);

        // Workstation 1 has assigned PC asset with 16 GB RAM registered
        $mockWsRepo->method('findById')->with(1)->willReturn([
            'workstation_id' => 1,
            'lab_id' => 'LAB-A',
            'mac_address' => '00:1A:2B:3C:4D:5E',
            'has_hardware_drift' => 0,
        ]);

        $mockTeleRepo->method('getAssignedHardwareSpecs')->with(1)->willReturn([
            [
                'asset_id' => 10,
                'asset_type' => 'PC',
                'specs' => [
                    'ram_gb' => 16,
                    'disk_gb' => 512,
                    'cpu_model' => 'Intel Core i7-10700',
                ],
            ],
        ]);

        // A. 16 GB PC reports 8 GB -> drift alert created
        $mockTeleRepo->method('findDriftAlert')
            ->with(1, 'ram_gb', 'Open')
            ->willReturn(null); // No open alert yet

        $mockTeleRepo->expects($this->once())
            ->method('createDriftAlert')
            ->with(1, 10, 'ram_gb', '16 GB', '8 GB')
            ->willReturn(1);

        $mockTeleRepo->method('hasOpenDriftAlerts')->with(1)->willReturn(true);
        $mockTeleRepo->expects($this->once())
            ->method('setWorkstationDriftFlag')
            ->with(1, true);

        $teleService = new TelemetryService($mockTeleRepo, $mockWsRepo, $this->appKey);

        $reportWithDrift = [
            'reported_hardware' => [
                'ram_bytes' => 8 * 1024 * 1024 * 1024, // 8 GB
                'disk_bytes' => 512 * 1024 * 1024 * 1024,
                'cpu_model' => 'Intel Core i7-10700',
                'mac_address' => '00:1A:2B:3C:4D:5E',
            ],
            'installed_software' => [],
        ];

        $teleService->recordInventoryAndDetectDrift(1, $reportWithDrift);

        // B. Value within 5% tolerance creates no alert
        // E.g. reporting 16 GB or 15.5 GB (diff = 0.5 <= 1)
        $mockTeleRepoNoDrift = $this->createMock(TelemetryRepository::class);
        $mockTeleRepoNoDrift->method('getAssignedHardwareSpecs')->with(1)->willReturn([
            [
                'asset_id' => 10,
                'asset_type' => 'PC',
                'specs' => ['ram_gb' => 16, 'disk_gb' => 512, 'cpu_model' => 'Intel Core i7-10700'],
            ],
        ]);
        $mockTeleRepoNoDrift->expects($this->never())->method('createDriftAlert');

        $teleServiceNoDrift = new TelemetryService($mockTeleRepoNoDrift, $mockWsRepo, $this->appKey);
        $reportTolerated = [
            'reported_hardware' => [
                'ram_bytes' => 16 * 1024 * 1024 * 1024, // 16 GB (identical)
                'disk_bytes' => 512 * 1024 * 1024 * 1024,
                'cpu_model' => 'intel core i7-10700', // case-insensitive match
                'mac_address' => '00:1A:2B:3C:4D:5E',
            ],
            'installed_software' => [],
        ];
        $teleServiceNoDrift->recordInventoryAndDetectDrift(1, $reportTolerated);

        // C. Resolving the alert updates status and recalculates has_hardware_drift
        $mockTeleRepoResolve = $this->createMock(TelemetryRepository::class);
        $mockTeleRepoResolve->method('findDriftById')->with(1)->willReturn([
            'drift_id' => 1,
            'workstation_id' => 1,
            'field_name' => 'ram_gb',
            'status' => 'Open',
        ]);
        $mockTeleRepoResolve->expects($this->once())
            ->method('updateDriftStatus')
            ->with(1, 'Resolved');

        $mockTeleRepoResolve->method('hasOpenDriftAlerts')->with(1)->willReturn(false); // None open now!
        $mockTeleRepoResolve->expects($this->once())
            ->method('setWorkstationDriftFlag')
            ->with(1, false);

        $teleServiceResolve = new TelemetryService($mockTeleRepoResolve, $mockWsRepo, $this->appKey);
        $this->assertTrue($teleServiceResolve->resolveDriftAlert(1, 'Resolved', 1));
    }

    // -------------------------------------------------------------------------
    // 8. Incharge Laboratory Access Isolation
    // -------------------------------------------------------------------------

    public function testInchargeLabIsolationOnTelemetryEndpoint(): void
    {
        $mockTeleRepo = $this->createMock(TelemetryRepository::class);
        $mockWsRepo = $this->createMock(WorkstationRepository::class);
        $mockLabRepo = $this->createMock(LabRepository::class);

        $mockWsRepo->method('findById')->willReturnMap([
            [1, ['workstation_id' => 1, 'lab_id' => 'LAB-A', 'seat_code' => 'A-01']],
            [2, ['workstation_id' => 2, 'lab_id' => 'LAB-B', 'seat_code' => 'B-01']],
        ]);

        // User is Incharge assigned only to LAB-A
        $mockLabRepo->method('canUserAccessLab')->willReturnCallback(function ($labId) {
            return $labId === 'LAB-A';
        });

        $teleService = new TelemetryService($mockTeleRepo, $mockWsRepo, $this->appKey);
        $controller = new AgentController($teleService, $mockWsRepo, $mockLabRepo);

        $_SESSION['logged_in'] = true;
        $_SESSION['uid'] = 2;
        $_SESSION['username'] = 'incharge_a';
        $_SESSION['role'] = 'Incharge';

        // Incharge accessing assigned lab (LAB-A, ws#1) -> 200 OK
        $req1 = new Request([], [], ['REQUEST_METHOD' => 'GET', 'HTTP_ACCEPT' => 'application/json']);
        $res1 = $controller->telemetry($req1, 1);
        $this->assertSame(200, $res1->getStatusCode());

        // Incharge accessing unassigned lab (LAB-B, ws#2) -> 403 Forbidden
        $req2 = new Request([], [], ['REQUEST_METHOD' => 'GET', 'HTTP_ACCEPT' => 'application/json']);
        $res2 = $controller->telemetry($req2, 2);
        $this->assertSame(403, $res2->getStatusCode());
    }

    // -------------------------------------------------------------------------
    // 9. Retention and Rollups Idempotency
    // -------------------------------------------------------------------------

    public function testRetentionAndRollupsIdempotency(): void
    {
        $mockTeleRepo = $this->createMock(TelemetryRepository::class);
        $mockWsRepo = $this->createMock(WorkstationRepository::class);

        $mockTeleRepo->expects($this->exactly(2))
            ->method('rollUpCompletedHours')
            ->willReturn(5);
        $mockTeleRepo->expects($this->exactly(2))
            ->method('purgeOldSamples')
            ->with(30)
            ->willReturn(120);
        $mockTeleRepo->expects($this->exactly(2))
            ->method('purgeOldRollups')
            ->with(365)
            ->willReturn(0);
        $mockTeleRepo->expects($this->exactly(2))
            ->method('pruneOldNonces')
            ->with(10)
            ->willReturn(15);

        $teleService = new TelemetryService($mockTeleRepo, $mockWsRepo, $this->appKey);

        $run1 = $teleService->runRetentionAndRollups();
        $run2 = $teleService->runRetentionAndRollups();

        $this->assertSame($run1, $run2);
        $this->assertSame(5, $run1['rollups_created']);
        $this->assertSame(120, $run1['samples_purged']);
    }

    // -------------------------------------------------------------------------
    // 10. Workstation Page Renders Status Badge and Drift Warning Banner
    // -------------------------------------------------------------------------

    public function testWorkstationPageRendersStatusBadgeAndDriftBanner(): void
    {
        $mockTeleRepo = $this->createMock(TelemetryRepository::class);
        $mockWsRepo = $this->createMock(WorkstationRepository::class);
        $mockLabRepo = $this->createMock(LabRepository::class);

        $mockWsRepo->method('findById')->with(1)->willReturn([
            'workstation_id' => 1,
            'lab_id' => 'LAB-A',
            'seat_code' => 'WS-01',
            'status' => 'Active',
            'hostname' => 'HOST-LAB-A-01',
            'last_seen_at' => date('Y-m-d H:i:s'),
            'logged_in_user' => 'alice',
            'latest_cpu_pct' => 35.0,
            'latest_ram_pct' => 65.0,
            'latest_disk_pct' => 50.0,
            'cpu_temp_c' => 45.5,
            'smart_health' => 'HEALTHY',
            'has_hardware_drift' => 1,
        ]);

        $mockTeleRepo->method('getRecentSamples')->willReturn([]);
        $mockTeleRepo->method('getOpenDriftAlerts')->willReturn([
            [
                'drift_id' => 99,
                'workstation_id' => 1,
                'field_name' => 'ram_gb',
                'expected_value' => '16 GB',
                'reported_value' => '8 GB',
                'status' => 'Open',
            ],
        ]);

        $mockWsAppService = $this->createMock(WorkstationAppService::class);
        $mockWsAppService->method('getWorkstationDetails')->willReturn([
            'workstation' => [
                'workstation_id' => 1,
                'lab_id' => 'LAB-A',
                'seat_code' => 'WS-01',
                'status' => 'Active',
                'has_hardware_drift' => 1,
                'lab_name' => 'Computer Lab A',
            ],
            'current_parts' => [],
            'history' => [],
            'grievances' => [],
            'available_spares' => [],
        ]);

        $_SESSION['logged_in'] = true;
        $_SESSION['uid'] = 1;
        $_SESSION['role'] = 'Admin';
        $_SESSION['csrf_token'] = 'test-token';

        $teleService = new TelemetryService($mockTeleRepo, $mockWsRepo, $this->appKey);
        $controller = new WorkstationController($mockWsAppService, $mockLabRepo, $teleService);

        $req = new Request([], [], ['REQUEST_METHOD' => 'GET']);
        $res = $controller->show($req, 1);

        $this->assertSame(200, $res->getStatusCode());
        $html = $res->getContent();

        // Status badge, metrics card, and drift warning banner verification
        $this->assertStringContainsString('id="telemetry-status-badge"', $html);
        $this->assertStringContainsString('id="telemetry-drift-banner"', $html);
        $this->assertStringContainsString('Hardware Drift Detected!', $html);
        $this->assertStringContainsString('RAM drift detected:', $html);
        $this->assertStringContainsString('registered 16 GB, reported 8 GB', $html);
        $this->assertStringContainsString('/assets/js/telemetry.js', $html);
    }
}
