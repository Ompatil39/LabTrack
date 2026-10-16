<?php

declare(strict_types=1);

namespace LabTrack\Service;

use InvalidArgumentException;
use LabTrack\Http\Request;
use LabTrack\Repository\TelemetryRepository;
use LabTrack\Repository\WorkstationRepository;
use LabTrack\Security\AgentSecurity;
use RuntimeException;

class TelemetryService
{
    private TelemetryRepository $telemetryRepo;
    private WorkstationRepository $workstationRepo;
    private string $appKey;

    public function __construct(
        ?TelemetryRepository $telemetryRepo = null,
        ?WorkstationRepository $workstationRepo = null,
        ?string $appKey = null
    ) {
        $this->telemetryRepo = $telemetryRepo ?? new TelemetryRepository();
        $this->workstationRepo = $workstationRepo ?? new WorkstationRepository();

        if ($appKey !== null && $appKey !== '') {
            $this->appKey = $appKey;
        } else {
            $this->appKey = (string) (
                ($_ENV['AGENT_SECRET_KEY'] ?? null)
                ?: ($_ENV['APP_KEY'] ?? null)
                ?: (function_exists('app_config') ? (app_config()['app']['key'] ?? null) : null)
                ?: 'labtrack-32-byte-master-secret-key!'
            );
        }
    }

    /**
     * Generate a 24-hour enrollment token for a workstation.
     * Admin only.
     *
     * @return array{token: string, expires_at: string, workstation_id: int}
     */
    public function createEnrollmentToken(int $workstationId, ?int $adminUid = null): array
    {
        $ws = $this->workstationRepo->findById($workstationId);
        if ($ws === null) {
            throw new InvalidArgumentException("Workstation #{$workstationId} not found.");
        }

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + 86400);

        $this->telemetryRepo->createEnrollmentToken($workstationId, $tokenHash, $expiresAt);

        AuditService::record(
            'TOKEN_GENERATE',
            'workstation',
            (string) $workstationId,
            null,
            ['expires_at' => $expiresAt, 'admin_uid' => $adminUid]
        );

        return [
            'token' => $token,
            'expires_at' => $expiresAt,
            'workstation_id' => $workstationId,
        ];
    }

    /**
     * Atomically exchange an enrollment token for an api_key_id and signing_secret.
     *
     * @return array{api_key_id: string, signing_secret: string, workstation_id: int}
     */
    public function enrollAgent(
        string $token,
        string $hostname,
        ?string $macAddress = null,
        ?string $ipAddress = null,
        ?string $agentVersion = null
    ): array {
        $token = trim($token);
        if ($token === '') {
            throw new RuntimeException('Enrollment token is required.');
        }

        $tokenHash = hash('sha256', $token);
        $apiKeyId = AgentSecurity::generateUuidV4();
        $signingSecret = bin2hex(random_bytes(32));
        $encryptedSecret = AgentSecurity::encrypt($signingSecret, $this->appKey);

        $workstationId = $this->telemetryRepo->consumeEnrollmentToken(
            $tokenHash,
            $apiKeyId,
            $encryptedSecret,
            $agentVersion
        );

        if ($workstationId === null) {
            throw new RuntimeException('Invalid or expired enrollment token.');
        }

        // Update network & OS information
        $this->telemetryRepo->updateWorkstationNetworkInfo($workstationId, $macAddress, $ipAddress, $hostname);

        AuditService::record(
            'ENROLL',
            'workstation',
            (string) $workstationId,
            null,
            [
                'api_key_id' => $apiKeyId,
                'hostname' => $hostname,
                'ip' => $ipAddress,
                'mac' => $macAddress,
                'version' => $agentVersion,
            ]
        );

        return [
            'api_key_id' => $apiKeyId,
            'signing_secret' => $signingSecret,
            'workstation_id' => $workstationId,
        ];
    }

    /**
     * Verifies the agent's HMAC request signature, nonces, timestamp, and rate limits.
     * Returns the agent credential record on success, or null on failure.
     *
     * @return array{credential: array<string, mixed>}|null
     */
    public function authenticateAgentRequest(Request $request, string $rawBody): ?array
    {
        $keyId = $request->header('X-Agent-Key-Id') ?? $request->server('HTTP_X_AGENT_KEY_ID');
        $timestamp = $request->header('X-Agent-Timestamp') ?? $request->server('HTTP_X_AGENT_TIMESTAMP');
        $nonce = $request->header('X-Agent-Nonce') ?? $request->server('HTTP_X_AGENT_NONCE');
        $signature = $request->header('X-Agent-Signature') ?? $request->server('HTTP_X_AGENT_SIGNATURE');

        if (!$keyId || !$timestamp || !$nonce || !$signature) {
            return null;
        }

        // Timestamp tolerance: within +/- 300 seconds
        $ts = (int) $timestamp;
        if (abs(time() - $ts) > 300) {
            return null;
        }

        // Nonce validation: 32 hex characters
        if (!preg_match('/^[a-f0-9]{32}$/i', (string) $nonce)) {
            return null;
        }

        // Fetch credential
        $cred = $this->telemetryRepo->findCredentialByKeyId((string) $keyId);
        if ($cred === null || (int) ($cred['is_revoked'] ?? 0) === 1) {
            return null;
        }

        // Replay check
        if ($this->telemetryRepo->isNonceUsed((string) $nonce, (string) $keyId)) {
            return null;
        }

        // Verify HMAC
        $encryptedSecret = (string) ($cred['signing_secret_encrypted'] ?? '');
        try {
            $signingSecret = AgentSecurity::decrypt($encryptedSecret, $this->appKey);
        } catch (\Throwable $e) {
            return null;
        }

        if (!is_string($signingSecret) || $signingSecret === '') {
            return null;
        }

        $method = strtoupper($request->getMethod());
        $path = $request->getPath();

        $canonical = AgentSecurity::buildCanonicalString($method, $path, $ts, (string) $nonce, $rawBody);
        $expectedSig = AgentSecurity::computeHmac($canonical, $signingSecret);

        if (!AgentSecurity::verifyHmac($expectedSig, (string) $signature)) {
            return null;
        }

        // Store nonce and prune old nonces (>10 min)
        $this->telemetryRepo->recordNonce((string) $nonce, (string) $keyId);
        $this->telemetryRepo->pruneOldNonces(10);

        // Update credential last_seen_at and last_ip
        $clientIp = $request->server('REMOTE_ADDR');
        $this->telemetryRepo->updateCredentialLastSeen((int) $cred['credential_id'], $clientIp);

        return ['credential' => $cred];
    }

    /**
     * Check if the workstation has exceeded the rate limit of 12 samples per 60 seconds.
     */
    public function isRateLimited(int $workstationId): bool
    {
        return $this->telemetryRepo->countRecentSamples($workstationId, 60) >= 12;
    }

    /**
     * Record a telemetry heartbeat.
     *
     * @param array<string, mixed> $payload
     */
    public function recordHeartbeat(int $workstationId, array $payload, ?string $clientIp = null): int
    {
        $this->validateHeartbeatPayload($payload);

        return $this->telemetryRepo->recordHeartbeat($workstationId, $payload, $clientIp);
    }

    /**
     * Validate heartbeat payload strictly according to specification.
     *
     * @param array<string, mixed> $payload
     */
    public function validateHeartbeatPayload(array $payload): void
    {
        foreach (['cpu_pct', 'ram_pct', 'disk_pct'] as $pctField) {
            if (!isset($payload[$pctField]) || !is_numeric($payload[$pctField])) {
                throw new InvalidArgumentException("Field '{$pctField}' is required and must be numeric.");
            }
            $val = (float) $payload[$pctField];
            if ($val < 0.0 || $val > 100.0) {
                throw new InvalidArgumentException("Field '{$pctField}' must be between 0 and 100.");
            }
        }

        if (isset($payload['uptime_seconds']) && $payload['uptime_seconds'] !== null) {
            if (!is_numeric($payload['uptime_seconds']) || (int) $payload['uptime_seconds'] < 0) {
                throw new InvalidArgumentException("Field 'uptime_seconds' must be a non-negative integer.");
            }
        }

        if (isset($payload['cpu_temp_c']) && $payload['cpu_temp_c'] !== null) {
            if (!is_numeric($payload['cpu_temp_c'])) {
                throw new InvalidArgumentException("Field 'cpu_temp_c' must be numeric or null.");
            }
        }

        if (isset($payload['smart_health']) && $payload['smart_health'] !== null) {
            $allowed = ['HEALTHY', 'WARNING', 'FAILING', 'UNKNOWN'];
            if (!in_array(strtoupper((string) $payload['smart_health']), $allowed, true)) {
                throw new InvalidArgumentException("Field 'smart_health' must be one of: " . implode(', ', $allowed));
            }
        }
    }

    /**
     * Record inventory snapshot and detect hardware drift against registered specs.
     *
     * @param array<string, mixed> $payload
     * @return array<int, array<string, mixed>> List of open drift alerts for this workstation
     */
    public function recordInventoryAndDetectDrift(int $workstationId, array $payload): array
    {
        $hardware = isset($payload['reported_hardware']) && is_array($payload['reported_hardware'])
            ? $payload['reported_hardware']
            : [];
        $software = isset($payload['installed_software']) && is_array($payload['installed_software'])
            ? $payload['installed_software']
            : [];

        // Save inventory snapshot
        $this->telemetryRepo->upsertInventorySnapshot($workstationId, $hardware, $software);

        // Fetch assigned hardware specifications
        $assigned = $this->telemetryRepo->getAssignedHardwareSpecs($workstationId);

        // Find primary PC asset specs and/or CPU asset specs
        $pcAsset = null;
        $cpuAsset = null;
        foreach ($assigned as $asset) {
            if (strtoupper((string) ($asset['asset_type'] ?? '')) === 'PC' && $pcAsset === null) {
                $pcAsset = $asset;
            } elseif (strtoupper((string) ($asset['asset_type'] ?? '')) === 'CPU' && $cpuAsset === null) {
                $cpuAsset = $asset;
            }
        }

        $registeredSpecs = [];
        $pcAssetId = $pcAsset !== null ? (int) $pcAsset['asset_id'] : null;

        if ($pcAsset !== null && is_array($pcAsset['specs'] ?? null)) {
            $registeredSpecs = array_merge($registeredSpecs, $pcAsset['specs']);
        }
        if ($cpuAsset !== null && is_array($cpuAsset['specs'] ?? null)) {
            // CPU specs might provide cpu_model
            if (isset($cpuAsset['specs']['cpu_model'])) {
                $registeredSpecs['cpu_model'] = $cpuAsset['specs']['cpu_model'];
            }
        }

        // Compare fields if registered specs exist
        // 1. RAM: reported in bytes -> GB rounded to nearest integer
        if (isset($registeredSpecs['ram_gb']) && is_numeric($registeredSpecs['ram_gb'])) {
            $expectedRam = (int) $registeredSpecs['ram_gb'];
            $reportedRam = null;

            if (isset($hardware['ram_bytes']) && is_numeric($hardware['ram_bytes'])) {
                $reportedRam = (int) round(((float) $hardware['ram_bytes']) / (1024 * 1024 * 1024));
            } elseif (isset($hardware['ram_gb']) && is_numeric($hardware['ram_gb'])) {
                $reportedRam = (int) round((float) $hardware['ram_gb']);
            }

            if ($reportedRam !== null && $expectedRam > 0) {
                $diff = abs($reportedRam - $expectedRam);
                if ($diff > 1 && $diff > (0.05 * $expectedRam)) {
                    $this->handleDriftAlert(
                        $workstationId,
                        $pcAssetId,
                        'ram_gb',
                        "{$expectedRam} GB",
                        "{$reportedRam} GB"
                    );
                }
            }
        }

        // 2. Disk: reported in bytes -> GB rounded to nearest integer
        if (isset($registeredSpecs['disk_gb']) && is_numeric($registeredSpecs['disk_gb'])) {
            $expectedDisk = (int) $registeredSpecs['disk_gb'];
            $reportedDisk = null;

            if (isset($hardware['disk_bytes']) && is_numeric($hardware['disk_bytes'])) {
                $reportedDisk = (int) round(((float) $hardware['disk_bytes']) / (1024 * 1024 * 1024));
            } elseif (isset($hardware['disk_gb']) && is_numeric($hardware['disk_gb'])) {
                $reportedDisk = (int) round((float) $hardware['disk_gb']);
            }

            if ($reportedDisk !== null && $expectedDisk > 0) {
                $diff = abs($reportedDisk - $expectedDisk);
                if ($diff > 1 && $diff > (0.05 * $expectedDisk)) {
                    $this->handleDriftAlert(
                        $workstationId,
                        $pcAssetId,
                        'disk_gb',
                        "{$expectedDisk} GB",
                        "{$reportedDisk} GB"
                    );
                }
            }
        }

        // 3. CPU model: case-insensitive after trimming
        if (isset($registeredSpecs['cpu_model']) && is_string($registeredSpecs['cpu_model'])) {
            $expectedCpu = trim($registeredSpecs['cpu_model']);
            $reportedCpu = isset($hardware['cpu_model']) && is_string($hardware['cpu_model'])
                ? trim($hardware['cpu_model'])
                : null;

            if ($expectedCpu !== '' && $reportedCpu !== null && $reportedCpu !== '') {
                if (strcasecmp($expectedCpu, $reportedCpu) !== 0) {
                    $this->handleDriftAlert(
                        $workstationId,
                        $pcAssetId,
                        'cpu_model',
                        $expectedCpu,
                        $reportedCpu
                    );
                }
            }
        }

        // 4. MAC address: if registered on workstation or specs
        $ws = $this->workstationRepo->findById($workstationId);
        $expectedMac = isset($ws['mac_address']) && is_string($ws['mac_address']) && $ws['mac_address'] !== ''
            ? trim($ws['mac_address'])
            : (isset($registeredSpecs['mac_address']) ? trim((string) $registeredSpecs['mac_address']) : '');

        $reportedMac = isset($hardware['mac_address']) && is_string($hardware['mac_address'])
            ? trim($hardware['mac_address'])
            : null;

        if ($expectedMac !== '' && $reportedMac !== null && $reportedMac !== '') {
            if (strcasecmp($expectedMac, $reportedMac) !== 0) {
                $this->handleDriftAlert(
                    $workstationId,
                    $pcAssetId,
                    'mac_address',
                    $expectedMac,
                    $reportedMac
                );
            }
        }

        // Recalculate workstation has_hardware_drift flag
        $hasOpen = $this->telemetryRepo->hasOpenDriftAlerts($workstationId);
        $this->telemetryRepo->setWorkstationDriftFlag($workstationId, $hasOpen);

        return $this->telemetryRepo->getOpenDriftAlerts($workstationId);
    }

    /**
     * Create or update a hardware drift alert.
     */
    private function handleDriftAlert(
        int $workstationId,
        ?int $assetId,
        string $fieldName,
        string $expected,
        string $reported
    ): void {
        $existing = $this->telemetryRepo->findDriftAlert($workstationId, $fieldName, 'Open');

        if ($existing !== null) {
            // Update reported_value if changed
            if ((string) ($existing['reported_value'] ?? '') !== $reported) {
                $this->telemetryRepo->updateDriftReportedValue((int) $existing['drift_id'], $reported);
            }
        } else {
            $this->telemetryRepo->createDriftAlert($workstationId, $assetId, $fieldName, $expected, $reported);
        }
    }

    /**
     * Resolve or acknowledge a drift alert.
     */
    public function resolveDriftAlert(int $driftId, string $action, ?int $userUid = null): bool
    {
        $allowed = ['Acknowledged', 'Resolved'];
        if (!in_array($action, $allowed, true)) {
            throw new InvalidArgumentException("Action must be 'Acknowledged' or 'Resolved'.");
        }

        $alert = $this->telemetryRepo->findDriftById($driftId);
        if ($alert === null) {
            throw new InvalidArgumentException("Drift alert #{$driftId} not found.");
        }

        $workstationId = (int) $alert['workstation_id'];
        $oldStatus = (string) $alert['status'];

        $this->telemetryRepo->updateDriftStatus($driftId, $action);

        // Recalculate has_hardware_drift
        $hasOpen = $this->telemetryRepo->hasOpenDriftAlerts($workstationId);
        $this->telemetryRepo->setWorkstationDriftFlag($workstationId, $hasOpen);

        AuditService::record(
            'STATUS_CHANGE',
            'hardware_drift_alert',
            (string) $driftId,
            ['status' => $oldStatus],
            ['status' => $action, 'user_uid' => $userUid]
        );

        return true;
    }

    /**
     * Get workstation telemetry payload for frontend polling & details view.
     *
     * @return array<string, mixed>
     */
    public function getWorkstationTelemetry(int $workstationId): array
    {
        $ws = $this->workstationRepo->findById($workstationId);
        if ($ws === null) {
            throw new InvalidArgumentException("Workstation #{$workstationId} not found.");
        }

        $lastSeen = $ws['last_seen_at'] ?? null;
        $status = 'Offline';

        if ($lastSeen !== null) {
            $secondsAgo = $this->telemetryRepo->getSecondsSinceLastSeen($workstationId);
            if ($secondsAgo !== null) {
                $status = ($secondsAgo >= 0 && $secondsAgo <= 120) ? 'Online' : 'Offline';
            } else {
                $lastSeenTs = strtotime((string) $lastSeen);
                $diff = abs(time() - $lastSeenTs);
                $status = ($diff <= 120) ? 'Online' : 'Offline';
            }
        }

        $samples = $this->telemetryRepo->getRecentSamples($workstationId, 60);
        $driftAlerts = $this->telemetryRepo->getOpenDriftAlerts($workstationId);

        return [
            'workstation_id' => $workstationId,
            'seat_code' => $ws['seat_code'] ?? null,
            'lab_id' => $ws['lab_id'] ?? null,
            'status' => $status,
            'last_seen_at' => $lastSeen,
            'logged_in_user' => $ws['logged_in_user'] ?? null,
            'uptime_seconds' => isset($ws['uptime_seconds']) ? (int) $ws['uptime_seconds'] : null,
            'ip_address' => $ws['ip_address'] ?? null,
            'mac_address' => $ws['mac_address'] ?? null,
            'latest_cpu_pct' => isset($ws['latest_cpu_pct']) ? (float) $ws['latest_cpu_pct'] : null,
            'latest_ram_pct' => isset($ws['latest_ram_pct']) ? (float) $ws['latest_ram_pct'] : null,
            'latest_disk_pct' => isset($ws['latest_disk_pct']) ? (float) $ws['latest_disk_pct'] : null,
            'cpu_temp_c' => isset($ws['cpu_temp_c']) ? (float) $ws['cpu_temp_c'] : null,
            'smart_health' => $ws['smart_health'] ?? 'UNKNOWN',
            'has_hardware_drift' => (bool) ($ws['has_hardware_drift'] ?? 0),
            'samples' => $samples,
            'drift_alerts' => $driftAlerts,
        ];
    }

    /**
     * Run retention and hourly rollups. Idempotent.
     *
     * @return array{rollups_created: int, samples_purged: int, rollups_purged: int, nonces_purged: int}
     */
    public function runRetentionAndRollups(): array
    {
        $rollups = $this->telemetryRepo->rollUpCompletedHours();
        $samplesPurged = $this->telemetryRepo->purgeOldSamples(30);
        $rollupsPurged = $this->telemetryRepo->purgeOldRollups(365);
        $noncesPurged = $this->telemetryRepo->pruneOldNonces(10);

        return [
            'rollups_created' => $rollups,
            'samples_purged' => $samplesPurged,
            'rollups_purged' => $rollupsPurged,
            'nonces_purged' => $noncesPurged,
        ];
    }
}
