<?php

declare(strict_types=1);

namespace LabTrack\Repository;

use PDO;
use RuntimeException;

class AssetRepository
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
     * @param array $filters
     * @param int $limit
     * @param int $offset
     * @param int|null $userUid
     * @param bool $isAdmin
     * @param string $username
     * @param string $fullName
     * @return array<int, array<string, mixed>>
     */
    public function findAll(
        array $filters = [],
        int $limit = 50,
        int $offset = 0,
        ?int $userUid = null,
        bool $isAdmin = false,
        string $username = '',
        string $fullName = ''
    ): array {
        $pdo = $this->getPdo();
        [$whereSql, $params] = $this->buildFilterConditions($filters, $userUid, $isAdmin, $username, $fullName);

        $limitInt = max(1, min(200, $limit));
        $offsetInt = max(0, $offset);

        $sql = "SELECT a.*, l.lab_name, l.department,
                       w.workstation_id, w.seat_code, aa.installed_at, aa.assignment_id
                FROM assets a
                JOIN labs l ON l.lab_id = a.lab_id
                LEFT JOIN lab_incharges li ON li.lab_id = l.lab_id
                LEFT JOIN asset_assignments aa ON aa.asset_id = a.asset_id AND aa.removed_at IS NULL
                LEFT JOIN workstations w ON w.workstation_id = aa.workstation_id
                {$whereSql}
                GROUP BY a.asset_id
                ORDER BY a.asset_id DESC
                LIMIT {$limitInt} OFFSET {$offsetInt}";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $type = is_int($v) ? PDO::PARAM_INT : (is_null($v) ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue($k, $v, $type);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countAll(
        array $filters = [],
        ?int $userUid = null,
        bool $isAdmin = false,
        string $username = '',
        string $fullName = ''
    ): int {
        $pdo = $this->getPdo();
        [$whereSql, $params] = $this->buildFilterConditions($filters, $userUid, $isAdmin, $username, $fullName);

        $sql = "SELECT COUNT(DISTINCT a.asset_id)
                FROM assets a
                JOIN labs l ON l.lab_id = a.lab_id
                LEFT JOIN lab_incharges li ON li.lab_id = l.lab_id
                LEFT JOIN asset_assignments aa ON aa.asset_id = a.asset_id AND aa.removed_at IS NULL
                LEFT JOIN workstations w ON w.workstation_id = aa.workstation_id
                {$whereSql}";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $type = is_int($v) ? PDO::PARAM_INT : (is_null($v) ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue($k, $v, $type);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function findByTag(string $assetTag): ?array
    {
        $pdo = $this->getPdo();
        $sql = 'SELECT a.*, l.lab_name, l.department, l.incharge_uid, l.lab_incharge,
                       w.workstation_id, w.seat_code, aa.installed_at, aa.assignment_id, aa.parent_asset_id
                FROM assets a
                JOIN labs l ON l.lab_id = a.lab_id
                LEFT JOIN asset_assignments aa ON aa.asset_id = a.asset_id AND aa.removed_at IS NULL
                LEFT JOIN workstations w ON w.workstation_id = aa.workstation_id
                WHERE a.asset_tag = :tag
                LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':tag' => $assetTag]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findById(int $assetId): ?array
    {
        $pdo = $this->getPdo();
        $sql = 'SELECT a.*, l.lab_name, l.department, l.incharge_uid, l.lab_incharge,
                       w.workstation_id, w.seat_code, aa.installed_at, aa.assignment_id, aa.parent_asset_id
                FROM assets a
                JOIN labs l ON l.lab_id = a.lab_id
                LEFT JOIN asset_assignments aa ON aa.asset_id = a.asset_id AND aa.removed_at IS NULL
                LEFT JOIN workstations w ON w.workstation_id = aa.workstation_id
                WHERE a.asset_id = :id
                LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $assetId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $pdo = $this->getPdo();
        $specsJson = isset($data['specs']) && (is_array($data['specs']) || is_object($data['specs']))
            ? json_encode($data['specs'], JSON_UNESCAPED_UNICODE)
            : (is_string($data['specs'] ?? null) ? $data['specs'] : null);

        $sql = 'INSERT INTO assets (
                    asset_tag, asset_type, lab_id, brand_model, serial_number, status,
                    purchase_date, warranty_until, purchase_cost, specs, qr_payload
                ) VALUES (
                    :asset_tag, :asset_type, :lab_id, :brand_model, :serial_number, :status,
                    :purchase_date, :warranty_until, :purchase_cost, :specs, :qr_payload
                )';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':asset_tag' => $data['asset_tag'],
            ':asset_type' => $data['asset_type'],
            ':lab_id' => $data['lab_id'],
            ':brand_model' => $data['brand_model'] ?? null,
            ':serial_number' => $data['serial_number'] ?? null,
            ':status' => $data['status'] ?? 'Active',
            ':purchase_date' => $data['purchase_date'] ?? null,
            ':warranty_until' => $data['warranty_until'] ?? null,
            ':purchase_cost' => $data['purchase_cost'] ?? null,
            ':specs' => $specsJson,
            ':qr_payload' => $data['qr_payload'] ?? null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public function update(int $assetId, array $data): bool
    {
        $pdo = $this->getPdo();
        $specsJson = isset($data['specs']) && (is_array($data['specs']) || is_object($data['specs']))
            ? json_encode($data['specs'], JSON_UNESCAPED_UNICODE)
            : (is_string($data['specs'] ?? null) ? $data['specs'] : null);

        $sql = 'UPDATE assets
                SET lab_id = :lab_id,
                    brand_model = :brand_model,
                    serial_number = :serial_number,
                    status = :status,
                    purchase_date = :purchase_date,
                    warranty_until = :warranty_until,
                    purchase_cost = :purchase_cost,
                    specs = :specs,
                    qr_payload = :qr_payload
                WHERE asset_id = :id';

        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            ':id' => $assetId,
            ':lab_id' => $data['lab_id'],
            ':brand_model' => $data['brand_model'] ?? null,
            ':serial_number' => $data['serial_number'] ?? null,
            ':status' => $data['status'] ?? 'Active',
            ':purchase_date' => $data['purchase_date'] ?? null,
            ':warranty_until' => $data['warranty_until'] ?? null,
            ':purchase_cost' => $data['purchase_cost'] ?? null,
            ':specs' => $specsJson,
            ':qr_payload' => $data['qr_payload'] ?? null,
        ]);
    }

    public function updateStatus(int $assetId, string $status): bool
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare('UPDATE assets SET status = :status WHERE asset_id = :id');
        return $stmt->execute([':status' => $status, ':id' => $assetId]);
    }

    public function delete(int $assetId): bool
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare('DELETE FROM assets WHERE asset_id = :id');
        return $stmt->execute([':id' => $assetId]);
    }

    public function retire(int $assetId, string $reason): bool
    {
        $pdo = $this->getPdo();
        $pdo->beginTransaction();
        try {
            $stmtAssign = $pdo->prepare(
                'UPDATE asset_assignments SET removed_at = NOW(), removal_reason = :reason WHERE asset_id = :id AND removed_at IS NULL'
            );
            $stmtAssign->execute([
                ':reason' => 'Asset Retired: ' . $reason,
                ':id' => $assetId,
            ]);

            $stmtAsset = $pdo->prepare(
                'UPDATE assets SET status = \'Retired\', retired_at = NOW(), retire_reason = :reason WHERE asset_id = :id'
            );
            $stmtAsset->execute([
                ':reason' => $reason,
                ':id' => $assetId,
            ]);

            $pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function existsTag(string $tag): bool
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare('SELECT 1 FROM assets WHERE asset_tag = :tag LIMIT 1');
        $stmt->execute([':tag' => $tag]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildFilterConditions(
        array $filters,
        ?int $userUid,
        bool $isAdmin,
        string $username,
        string $fullName
    ): array {
        $conditions = [];
        $params = [];

        if (!empty($filters['lab_id'])) {
            $conditions[] = 'a.lab_id = :filter_lab_id';
            $params[':filter_lab_id'] = (string) $filters['lab_id'];
        }

        if (!empty($filters['asset_type'])) {
            $conditions[] = 'a.asset_type = :filter_asset_type';
            $params[':filter_asset_type'] = (string) $filters['asset_type'];
        }

        if (!empty($filters['status'])) {
            $conditions[] = 'a.status = :filter_status';
            $params[':filter_status'] = (string) $filters['status'];
        }

        if (!empty($filters['workstation_id'])) {
            $conditions[] = 'w.workstation_id = :filter_ws_id';
            $params[':filter_ws_id'] = (int) $filters['workstation_id'];
        }

        if (!empty($filters['search'])) {
            $searchTerm = '%' . trim((string) $filters['search']) . '%';
            $conditions[] = '(a.asset_tag LIKE :s1 OR a.brand_model LIKE :s2 OR a.serial_number LIKE :s3 OR w.seat_code LIKE :s4)';
            $params[':s1'] = $searchTerm;
            $params[':s2'] = $searchTerm;
            $params[':s3'] = $searchTerm;
            $params[':s4'] = $searchTerm;
        }

        if (!$isAdmin) {
            $displayName = ucfirst(strtolower($username));
            $conditions[] = '(
                (:uid1 IS NOT NULL AND (li.uid = :uid2 OR l.incharge_uid = :uid3))
                OR l.lab_incharge = :username
                OR l.lab_incharge = :display_name
                OR l.lab_incharge = :full_name
            )';
            $params[':uid1'] = $userUid;
            $params[':uid2'] = $userUid;
            $params[':uid3'] = $userUid;
            $params[':username'] = $username;
            $params[':display_name'] = $displayName;
            $params[':full_name'] = $fullName;
        }

        $whereSql = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
        return [$whereSql, $params];
    }

    public function countByStatus(
        ?int $userUid = null,
        bool $isAdmin = false,
        string $username = '',
        string $fullName = ''
    ): array {
        $pdo = $this->getPdo();
        [$whereSql, $params] = $this->buildFilterConditions([], $userUid, $isAdmin, $username, $fullName);

        $sql = "SELECT a.status, COUNT(DISTINCT a.asset_id) AS total
                FROM assets a
                JOIN labs l ON l.lab_id = a.lab_id
                LEFT JOIN lab_incharges li ON li.lab_id = l.lab_id
                LEFT JOIN asset_assignments aa ON aa.asset_id = a.asset_id AND aa.removed_at IS NULL
                LEFT JOIN workstations w ON w.workstation_id = aa.workstation_id
                {$whereSql}
                GROUP BY a.status";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $type = is_int($v) ? PDO::PARAM_INT : (is_null($v) ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue($k, $v, $type);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        return $rows ?: [];
    }

    public function countByType(
        ?int $userUid = null,
        bool $isAdmin = false,
        string $username = '',
        string $fullName = ''
    ): array {
        $pdo = $this->getPdo();
        [$whereSql, $params] = $this->buildFilterConditions([], $userUid, $isAdmin, $username, $fullName);

        $sql = "SELECT a.asset_type, COUNT(DISTINCT a.asset_id) AS total
                FROM assets a
                JOIN labs l ON l.lab_id = a.lab_id
                LEFT JOIN lab_incharges li ON li.lab_id = l.lab_id
                LEFT JOIN asset_assignments aa ON aa.asset_id = a.asset_id AND aa.removed_at IS NULL
                LEFT JOIN workstations w ON w.workstation_id = aa.workstation_id
                {$whereSql}
                GROUP BY a.asset_type";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $type = is_int($v) ? PDO::PARAM_INT : (is_null($v) ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue($k, $v, $type);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        return $rows ?: [];
    }
}
