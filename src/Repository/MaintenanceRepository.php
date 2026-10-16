<?php

declare(strict_types=1);

namespace LabTrack\Repository;

use PDO;
use RuntimeException;

class MaintenanceRepository
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

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $pdo = $this->getPdo();

        $sql = 'INSERT INTO maintenance_records
                (asset_id, workstation_id, lab_id, maintenance_type, title, description,
                 vendor, cost, started_at, completed_at, downtime_hours, next_scheduled_date, performed_by_uid)
                VALUES
                (:asset_id, :workstation_id, :lab_id, :maintenance_type, :title, :description,
                 :vendor, :cost, :started_at, :completed_at, :downtime_hours, :next_scheduled_date, :performed_by_uid)';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':asset_id' => (int) $data['asset_id'],
            ':workstation_id' => isset($data['workstation_id']) && $data['workstation_id'] !== null ? (int) $data['workstation_id'] : null,
            ':lab_id' => (string) $data['lab_id'],
            ':maintenance_type' => (string) $data['maintenance_type'],
            ':title' => (string) $data['title'],
            ':description' => $data['description'] ?? null,
            ':vendor' => $data['vendor'] ?? null,
            ':cost' => isset($data['cost']) ? (float) $data['cost'] : 0.00,
            ':started_at' => (string) $data['started_at'],
            ':completed_at' => $data['completed_at'] ?? null,
            ':downtime_hours' => isset($data['downtime_hours']) ? (float) $data['downtime_hours'] : 0.00,
            ':next_scheduled_date' => $data['next_scheduled_date'] ?? null,
            ':performed_by_uid' => isset($data['performed_by_uid']) && $data['performed_by_uid'] !== null ? (int) $data['performed_by_uid'] : null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildFilterConditions(array $filters): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['lab_id'])) {
            $where[] = 'm.lab_id = :lab_id';
            $params[':lab_id'] = (string) $filters['lab_id'];
        }

        if (!empty($filters['maintenance_type'])) {
            $where[] = 'm.maintenance_type = :type';
            $params[':type'] = (string) $filters['maintenance_type'];
        }

        if (!empty($filters['asset_id'])) {
            $where[] = 'm.asset_id = :asset_id';
            $params[':asset_id'] = (int) $filters['asset_id'];
        }

        if (!empty($filters['workstation_id'])) {
            $where[] = 'm.workstation_id = :workstation_id';
            $params[':workstation_id'] = (int) $filters['workstation_id'];
        }

        return ['WHERE ' . implode(' AND ', $where), $params];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function findAll(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $pdo = $this->getPdo();
        [$whereSql, $params] = $this->buildFilterConditions($filters);

        $limitInt = max(1, min(200, $limit));
        $offsetInt = max(0, $offset);

        $sql = "SELECT m.*, a.asset_tag, a.asset_type, a.brand_model, l.lab_name, w.seat_code, u.username AS performed_by_username
                FROM maintenance_records m
                JOIN assets a ON m.asset_id = a.asset_id
                LEFT JOIN labs l ON m.lab_id = l.lab_id
                LEFT JOIN workstations w ON m.workstation_id = w.workstation_id
                LEFT JOIN users u ON m.performed_by_uid = u.uid
                {$whereSql}
                ORDER BY m.started_at DESC
                LIMIT {$limitInt} OFFSET {$offsetInt}";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function countAll(array $filters = []): int
    {
        $pdo = $this->getPdo();
        [$whereSql, $params] = $this->buildFilterConditions($filters);

        $sql = "SELECT COUNT(*) FROM maintenance_records m {$whereSql}";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $recordId): ?array
    {
        $pdo = $this->getPdo();
        $sql = 'SELECT m.*, a.asset_tag, a.asset_type, a.brand_model, l.lab_name, w.seat_code
                FROM maintenance_records m
                JOIN assets a ON m.asset_id = a.asset_id
                LEFT JOIN labs l ON m.lab_id = l.lab_id
                LEFT JOIN workstations w ON m.workstation_id = w.workstation_id
                WHERE m.record_id = :id
                LIMIT 1';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $recordId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * Return assets whose warranty expires within the given number of days.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getWarrantyAlerts(int $days = 90): array
    {
        $pdo = $this->getPdo();
        $sql = "SELECT a.*, l.lab_name, w.seat_code,
                       DATEDIFF(a.warranty_until, CURDATE()) AS days_left
                FROM assets a
                JOIN labs l ON a.lab_id = l.lab_id
                LEFT JOIN asset_assignments aa ON aa.asset_id = a.asset_id AND aa.removed_at IS NULL
                LEFT JOIN workstations w ON aa.workstation_id = w.workstation_id
                WHERE a.warranty_until IS NOT NULL
                  AND a.status != 'Retired'
                  AND a.warranty_until <= DATE_ADD(CURDATE(), INTERVAL :days DAY)
                ORDER BY a.warranty_until ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':days' => $days]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Return active/in-service assets with financial figures for straight-line depreciation.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAssetsForDepreciation(): array
    {
        $pdo = $this->getPdo();
        $sql = 'SELECT a.asset_id, a.asset_tag, a.asset_type, a.brand_model, a.lab_id, a.status,
                       a.purchase_date, a.purchase_cost, a.salvage_value, a.useful_life_years,
                       l.lab_name
                FROM assets a
                JOIN labs l ON a.lab_id = l.lab_id
                WHERE a.status != \'Retired\'
                ORDER BY a.asset_id ASC';

        $stmt = $pdo->query($sql);
        if ($stmt === false) {
            return [];
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Aggregated maintenance statistics for dashboard and reports.
     *
     * @return array{total_cost: float, total_downtime_hours: float, by_type: array<string, int>, by_lab: array<string, array{cost: float, downtime: float, count: int}>}
     */
    public function getAggregateStats(): array
    {
        $pdo = $this->getPdo();

        $totStmt = $pdo->query('SELECT COALESCE(SUM(cost), 0) AS total_cost, COALESCE(SUM(downtime_hours), 0) AS total_downtime FROM maintenance_records');
        $tot = $totStmt !== false ? $totStmt->fetch(PDO::FETCH_ASSOC) : ['total_cost' => 0, 'total_downtime' => 0];

        $typeStmt = $pdo->query('SELECT maintenance_type, COUNT(*) AS cnt FROM maintenance_records GROUP BY maintenance_type');
        $byType = [];
        if ($typeStmt !== false) {
            while ($row = $typeStmt->fetch(PDO::FETCH_ASSOC)) {
                $byType[(string) $row['maintenance_type']] = (int) $row['cnt'];
            }
        }

        $labStmt = $pdo->query('SELECT lab_id, COALESCE(SUM(cost), 0) AS cost, COALESCE(SUM(downtime_hours), 0) AS downtime, COUNT(*) AS count FROM maintenance_records GROUP BY lab_id');
        $byLab = [];
        if ($labStmt !== false) {
            while ($row = $labStmt->fetch(PDO::FETCH_ASSOC)) {
                $byLab[(string) $row['lab_id']] = [
                    'cost' => (float) $row['cost'],
                    'downtime' => (float) $row['downtime'],
                    'count' => (int) $row['count'],
                ];
            }
        }

        return [
            'total_cost' => (float) ($tot['total_cost'] ?? 0.0),
            'total_downtime_hours' => (float) ($tot['total_downtime'] ?? 0.0),
            'by_type' => $byType,
            'by_lab' => $byLab,
        ];
    }
}
