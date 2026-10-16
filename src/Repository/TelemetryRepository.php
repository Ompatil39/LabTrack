<?php

declare(strict_types=1);

namespace LabTrack\Repository;

use PDO;
use RuntimeException;

class TelemetryRepository
{
    private ?PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    private function getPdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        if (function_exists('db_pdo')) {
            $this->pdo = db_pdo();
            return $this->pdo;
        }

        throw new RuntimeException('Database connection (PDO) is not configured or available.');
    }

    public function createEnrollmentToken(int $workstationId, string $tokenHash, string $expiresAt): bool
    {
        $pdo = $this->getPdo();
        $sql = 'INSERT INTO agent_credentials (workstation_id, enrollment_token_hash, enrollment_expires_at, is_revoked)
                VALUES (:ws_id, :token_hash, :expires_at, 0)
                ON DUPLICATE KEY UPDATE
                    enrollment_token_hash = VALUES(enrollment_token_hash),
                    enrollment_expires_at = VALUES(enrollment_expires_at),
                    is_revoked = 0';

        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            ':ws_id' => $workstationId,
            ':token_hash' => $tokenHash,
            ':expires_at' => $expiresAt,
        ]);
    }

    /**
     * Atomically consumes the enrollment token and sets credentials.
     * Returns the workstation_id or null if consumption failed.
     */
    public function consumeEnrollmentToken(
        string $tokenHash,
        string $apiKeyId,
        string $encryptedSecret,
        ?string $agentVersion = null,
        ?string $ip = null
    ): ?int {
        $pdo = $this->getPdo();

        // 1. Identify workstation_id for this valid token
        $checkSql = 'SELECT workstation_id FROM agent_credentials
                     WHERE enrollment_token_hash = :hash
                       AND enrollment_expires_at > NOW()
                       AND is_revoked = 0
                     LIMIT 1';
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([':hash' => $tokenHash]);
        $row = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $workstationId = (int) $row['workstation_id'];

        // 2. Consume atomically
        $updateSql = 'UPDATE agent_credentials
                      SET enrollment_token_hash = NULL,
                          enrollment_expires_at = NULL,
                          api_key_id = :api_key_id,
                          signing_secret_encrypted = :secret,
                          agent_version = :version,
                          enrolled_at = NOW(),
                          last_seen_at = NOW(),
                          last_ip = :ip
                      WHERE workstation_id = :ws_id';
        $updateStmt = $pdo->prepare($updateSql);
        $res = $updateStmt->execute([
            ':api_key_id' => $apiKeyId,
            ':secret' => $encryptedSecret,
            ':version' => $agentVersion ?? '1.0.0',
            ':ip' => $ip,
            ':ws_id' => $workstationId,
        ]);

        return $res ? $workstationId : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findCredentialByKeyId(string $apiKeyId): ?array
    {
        $pdo = $this->getPdo();
        $sql = 'SELECT ac.*, w.lab_id, w.seat_code
                FROM agent_credentials ac
                JOIN workstations w ON w.workstation_id = ac.workstation_id
                WHERE ac.api_key_id = :key_id
                LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':key_id' => $apiKeyId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findCredentialByWorkstationId(int $workstationId): ?array
    {
        $pdo = $this->getPdo();
        $sql = 'SELECT * FROM agent_credentials WHERE workstation_id = :id LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $workstationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    public function updateCredentialLastSeen(int $credentialId, ?string $ip): void
    {
        $pdo = $this->getPdo();
        $sql = 'UPDATE agent_credentials SET last_seen_at = NOW(), last_ip = :ip WHERE credential_id = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':ip' => $ip,
            ':id' => $credentialId,
        ]);
    }

    public function isNonceUsed(string $nonce, string $apiKeyId): bool
    {
        $pdo = $this->getPdo();
        $sql = 'SELECT 1 FROM agent_nonces WHERE nonce = :nonce AND api_key_id = :key_id LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':nonce' => $nonce,
            ':key_id' => $apiKeyId,
        ]);
        return (bool) $stmt->fetchColumn();
    }

    public function recordNonce(string $nonce, string $apiKeyId): bool
    {
        $pdo = $this->getPdo();
        $sql = 'INSERT INTO agent_nonces (nonce, api_key_id, created_at) VALUES (:nonce, :key_id, NOW())';
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            ':nonce' => $nonce,
            ':key_id' => $apiKeyId,
        ]);
    }

    public function pruneOldNonces(int $minutes = 10): int
    {
        $pdo = $this->getPdo();
        $sql = 'DELETE FROM agent_nonces WHERE created_at < DATE_SUB(NOW(), INTERVAL :mins MINUTE)';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':mins' => max(1, $minutes)]);
        return $stmt->rowCount();
    }

    public function countRecentSamples(int $workstationId, int $seconds = 60): int
    {
        $pdo = $this->getPdo();
        $sql = 'SELECT COUNT(*) FROM telemetry_samples
                WHERE workstation_id = :ws_id
                  AND sampled_at >= DATE_SUB(NOW(), INTERVAL :sec SECOND)';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':ws_id' => $workstationId,
            ':sec' => max(1, $seconds),
        ]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Records a heartbeat and updates the workstation live metrics in one atomic transaction.
     *
     * @param array<string, mixed> $payload
     */
    public function recordHeartbeat(int $workstationId, array $payload, ?string $clientIp = null): int
    {
        $pdo = $this->getPdo();
        $pdo->beginTransaction();

        try {
            $cpuPct = (float) ($payload['cpu_pct'] ?? 0.0);
            $ramPct = (float) ($payload['ram_pct'] ?? 0.0);
            $diskPct = (float) ($payload['disk_pct'] ?? 0.0);
            $cpuTempC = isset($payload['cpu_temp_c']) && $payload['cpu_temp_c'] !== null
                ? (float) $payload['cpu_temp_c']
                : null;
            $uptimeSeconds = isset($payload['uptime_seconds']) && $payload['uptime_seconds'] !== null
                ? (int) $payload['uptime_seconds']
                : null;
            $loggedInUser = isset($payload['logged_in_user']) && $payload['logged_in_user'] !== ''
                ? substr((string) $payload['logged_in_user'], 0, 100)
                : null;
            $smartHealth = (string) ($payload['smart_health'] ?? 'UNKNOWN');
            $ipAddress = $clientIp ?? ($payload['ip_address'] ?? null);

            // 1. Update Workstation live columns
            $updateWsSql = 'UPDATE workstations
                            SET last_seen_at = NOW(),
                                latest_cpu_pct = :cpu,
                                latest_ram_pct = :ram,
                                latest_disk_pct = :disk,
                                cpu_temp_c = :temp,
                                smart_health = :smart,
                                logged_in_user = :user,
                                uptime_seconds = :uptime,
                                ip_address = COALESCE(:ip, ip_address)
                            WHERE workstation_id = :ws_id';
            $wsStmt = $pdo->prepare($updateWsSql);
            $wsStmt->execute([
                ':cpu' => $cpuPct,
                ':ram' => $ramPct,
                ':disk' => $diskPct,
                ':temp' => $cpuTempC,
                ':smart' => $smartHealth,
                ':user' => $loggedInUser,
                ':uptime' => $uptimeSeconds,
                ':ip' => $ipAddress,
                ':ws_id' => $workstationId,
            ]);

            // 2. Insert telemetry sample
            $sampleSql = 'INSERT INTO telemetry_samples
                            (workstation_id, sampled_at, cpu_pct, ram_pct, disk_pct, cpu_temp_c, uptime_seconds, logged_in_user, smart_health)
                          VALUES
                            (:ws_id, NOW(), :cpu, :ram, :disk, :temp, :uptime, :user, :smart)';
            $sampleStmt = $pdo->prepare($sampleSql);
            $sampleStmt->execute([
                ':ws_id' => $workstationId,
                ':cpu' => $cpuPct,
                ':ram' => $ramPct,
                ':disk' => $diskPct,
                ':temp' => $cpuTempC,
                ':uptime' => $uptimeSeconds,
                ':user' => $loggedInUser,
                ':smart' => $smartHealth,
            ]);

            $sampleId = (int) $pdo->lastInsertId();
            $pdo->commit();
            return $sampleId;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRecentSamples(int $workstationId, int $minutes = 60): array
    {
        $pdo = $this->getPdo();
        $sql = 'SELECT sample_id, workstation_id, sampled_at, cpu_pct, ram_pct, disk_pct, cpu_temp_c, uptime_seconds, logged_in_user, smart_health
                FROM telemetry_samples
                WHERE workstation_id = :ws_id
                  AND sampled_at >= DATE_SUB(NOW(), INTERVAL :mins MINUTE)
                ORDER BY sampled_at ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':ws_id' => $workstationId,
            ':mins' => max(1, $minutes),
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<string, mixed> $hardware
     * @param array<string, mixed>|array<int, mixed> $software
     */
    public function upsertInventorySnapshot(int $workstationId, array $hardware, array $software): bool
    {
        $pdo = $this->getPdo();
        $sql = 'INSERT INTO workstation_inventory_snapshots
                    (workstation_id, reported_hardware, installed_software, updated_at)
                VALUES
                    (:ws_id, :hw, :sw, NOW())
                ON DUPLICATE KEY UPDATE
                    reported_hardware = VALUES(reported_hardware),
                    installed_software = VALUES(installed_software),
                    updated_at = NOW()';
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            ':ws_id' => $workstationId,
            ':hw' => json_encode($hardware, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ':sw' => json_encode($software, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getInventorySnapshot(int $workstationId): ?array
    {
        $pdo = $this->getPdo();
        $sql = 'SELECT * FROM workstation_inventory_snapshots WHERE workstation_id = :ws_id LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':ws_id' => $workstationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        if (isset($row['reported_hardware']) && is_string($row['reported_hardware'])) {
            $row['reported_hardware'] = json_decode($row['reported_hardware'], true) ?: [];
        }
        if (isset($row['installed_software']) && is_string($row['installed_software'])) {
            $row['installed_software'] = json_decode($row['installed_software'], true) ?: [];
        }

        return $row;
    }

    /**
     * Fetch assigned PC and/or CPU assets for a workstation.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAssignedHardwareSpecs(int $workstationId): array
    {
        $pdo = $this->getPdo();
        $sql = 'SELECT a.asset_id, a.asset_type, a.asset_tag, a.brand_model, a.specs
                FROM asset_assignments aa
                JOIN assets a ON a.asset_id = aa.asset_id
                WHERE aa.workstation_id = :ws_id
                  AND aa.removed_at IS NULL
                  AND a.asset_type IN (\'PC\', \'CPU\')';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':ws_id' => $workstationId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            if (isset($r['specs']) && is_string($r['specs'])) {
                $r['specs'] = json_decode($r['specs'], true) ?: [];
            }
        }
        unset($r);

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getOpenDriftAlerts(int $workstationId): array
    {
        $pdo = $this->getPdo();
        $sql = 'SELECT * FROM hardware_drift_alerts
                WHERE workstation_id = :ws_id AND status = \'Open\'
                ORDER BY detected_at DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':ws_id' => $workstationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findDriftAlert(int $workstationId, string $fieldName, string $status = 'Open'): ?array
    {
        $pdo = $this->getPdo();
        $sql = 'SELECT * FROM hardware_drift_alerts
                WHERE workstation_id = :ws_id AND field_name = :field AND status = :status
                LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':ws_id' => $workstationId,
            ':field' => $fieldName,
            ':status' => $status,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findDriftById(int $driftId): ?array
    {
        $pdo = $this->getPdo();
        $sql = 'SELECT * FROM hardware_drift_alerts WHERE drift_id = :id LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $driftId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function createDriftAlert(
        int $workstationId,
        ?int $assetId,
        string $fieldName,
        string $expected,
        string $reported
    ): int {
        $pdo = $this->getPdo();
        $sql = 'INSERT INTO hardware_drift_alerts
                    (workstation_id, asset_id, field_name, expected_value, reported_value, status, detected_at)
                VALUES
                    (:ws_id, :asset_id, :field, :expected, :reported, \'Open\', NOW())';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':ws_id' => $workstationId,
            ':asset_id' => $assetId,
            ':field' => $fieldName,
            ':expected' => $expected,
            ':reported' => $reported,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public function updateDriftReportedValue(int $driftId, string $reported): bool
    {
        $pdo = $this->getPdo();
        $sql = 'UPDATE hardware_drift_alerts SET reported_value = :reported WHERE drift_id = :id';
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            ':reported' => $reported,
            ':id' => $driftId,
        ]);
    }

    public function updateDriftStatus(int $driftId, string $status): bool
    {
        $pdo = $this->getPdo();
        $sql = 'UPDATE hardware_drift_alerts
                SET status = :status,
                    resolved_at = CASE WHEN :status_check = \'Resolved\' THEN NOW() ELSE resolved_at END
                WHERE drift_id = :id';
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            ':status' => $status,
            ':status_check' => $status,
            ':id' => $driftId,
        ]);
    }

    public function hasOpenDriftAlerts(int $workstationId): bool
    {
        $pdo = $this->getPdo();
        $sql = 'SELECT 1 FROM hardware_drift_alerts WHERE workstation_id = :ws_id AND status = \'Open\' LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':ws_id' => $workstationId]);
        return (bool) $stmt->fetchColumn();
    }

    public function setWorkstationDriftFlag(int $workstationId, bool $hasDrift): bool
    {
        $pdo = $this->getPdo();
        $sql = 'UPDATE workstations SET has_hardware_drift = :flag WHERE workstation_id = :ws_id';
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            ':flag' => $hasDrift ? 1 : 0,
            ':ws_id' => $workstationId,
        ]);
    }

    public function getSecondsSinceLastSeen(int $workstationId): ?int
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare('SELECT TIMESTAMPDIFF(SECOND, last_seen_at, NOW()) FROM workstations WHERE workstation_id = :id AND last_seen_at IS NOT NULL');
        $stmt->execute([':id' => $workstationId]);
        $res = $stmt->fetchColumn();
        return $res !== false && $res !== null ? (int) $res : null;
    }

    public function updateWorkstationNetworkInfo(int $workstationId, ?string $mac, ?string $ip, ?string $osInfo = null): bool
    {
        $pdo = $this->getPdo();
        $sql = 'UPDATE workstations
                SET mac_address = COALESCE(:mac, mac_address),
                    ip_address = COALESCE(:ip, ip_address),
                    os_info = COALESCE(:os, os_info)
                WHERE workstation_id = :ws_id';
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            ':mac' => $mac,
            ':ip' => $ip,
            ':os' => $osInfo,
            ':ws_id' => $workstationId,
        ]);
    }

    /**
     * Compute and upsert hourly rollups for all completed hours strictly before current hour.
     */
    public function rollUpCompletedHours(): int
    {
        $pdo = $this->getPdo();
        // Completed hours: sampled_at < DATE_FORMAT(NOW(), '%Y-%m-%d %H:00:00')
        $sql = 'INSERT INTO telemetry_hourly_rollups
                    (workstation_id, bucket_hour, avg_cpu_pct, max_cpu_pct, avg_ram_pct, max_ram_pct, avg_disk_pct, max_temp_c, sample_count)
                SELECT
                    workstation_id,
                    DATE_FORMAT(sampled_at, \'%Y-%m-%d %H:00:00\') AS bucket_hour,
                    ROUND(AVG(cpu_pct), 2) AS avg_cpu_pct,
                    MAX(cpu_pct) AS max_cpu_pct,
                    ROUND(AVG(ram_pct), 2) AS avg_ram_pct,
                    MAX(ram_pct) AS max_ram_pct,
                    ROUND(AVG(disk_pct), 2) AS avg_disk_pct,
                    MAX(cpu_temp_c) AS max_temp_c,
                    COUNT(*) AS sample_count
                FROM telemetry_samples
                WHERE sampled_at < DATE_FORMAT(NOW(), \'%Y-%m-%d %H:00:00\')
                GROUP BY workstation_id, bucket_hour
                ON DUPLICATE KEY UPDATE
                    avg_cpu_pct = VALUES(avg_cpu_pct),
                    max_cpu_pct = VALUES(max_cpu_pct),
                    avg_ram_pct = VALUES(avg_ram_pct),
                    max_ram_pct = VALUES(max_ram_pct),
                    avg_disk_pct = VALUES(avg_disk_pct),
                    max_temp_c = VALUES(max_temp_c),
                    sample_count = VALUES(sample_count)';

        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function purgeOldSamples(int $days = 30): int
    {
        $pdo = $this->getPdo();
        $sql = 'DELETE FROM telemetry_samples WHERE sampled_at < DATE_SUB(NOW(), INTERVAL :days DAY)';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':days' => max(1, $days)]);
        return $stmt->rowCount();
    }

    public function purgeOldRollups(int $days = 365): int
    {
        $pdo = $this->getPdo();
        $sql = 'DELETE FROM telemetry_hourly_rollups WHERE bucket_hour < DATE_SUB(NOW(), INTERVAL :days DAY)';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':days' => max(1, $days)]);
        return $stmt->rowCount();
    }
}
