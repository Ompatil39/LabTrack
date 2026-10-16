<?php

declare(strict_types=1);

namespace LabTrack\Repository;

use PDO;
use RuntimeException;

class WorkstationRepository
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
     * @return array<int, array<string, mixed>>
     */
    public function findAll(?string $labId = null, ?int $userUid = null, bool $isAdmin = false, string $username = '', string $fullName = ''): array
    {
        $pdo = $this->getPdo();
        $conditions = [];
        $params = [];

        if ($labId !== null && $labId !== '') {
            $conditions[] = 'w.lab_id = :lab_id';
            $params[':lab_id'] = $labId;
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

        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = "SELECT w.*, l.lab_name, l.department,
                       COUNT(DISTINCT CASE WHEN aa.removed_at IS NULL THEN aa.asset_id END) AS active_parts_count
                FROM workstations w
                JOIN labs l ON l.lab_id = w.lab_id
                LEFT JOIN lab_incharges li ON li.lab_id = l.lab_id
                LEFT JOIN asset_assignments aa ON aa.workstation_id = w.workstation_id
                {$whereClause}
                GROUP BY w.workstation_id
                ORDER BY w.lab_id ASC, w.seat_code ASC";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $type = is_int($val) ? PDO::PARAM_INT : (is_null($val) ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue($key, $val, $type);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $workstationId): ?array
    {
        $pdo = $this->getPdo();
        $sql = 'SELECT w.*, l.lab_name, l.department, l.incharge_uid, l.lab_incharge
                FROM workstations w
                JOIN labs l ON l.lab_id = w.lab_id
                WHERE w.workstation_id = :id
                LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $workstationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByLabAndSeat(string $labId, string $seatCode): ?array
    {
        $pdo = $this->getPdo();
        $sql = 'SELECT * FROM workstations WHERE lab_id = :lab_id AND seat_code = :seat_code LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':lab_id' => $labId, ':seat_code' => $seatCode]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $pdo = $this->getPdo();
        $sql = 'INSERT INTO workstations (lab_id, seat_code, hostname, status) VALUES (:lab_id, :seat_code, :hostname, :status)';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':lab_id' => $data['lab_id'],
            ':seat_code' => $data['seat_code'],
            ':hostname' => $data['hostname'] ?? null,
            ':status' => $data['status'] ?? 'Active',
        ]);
        return (int) $pdo->lastInsertId();
    }

    public function update(int $workstationId, array $data): bool
    {
        $pdo = $this->getPdo();
        $sql = 'UPDATE workstations
                SET seat_code = :seat_code,
                    hostname = :hostname,
                    status = :status
                WHERE workstation_id = :id';
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            ':id' => $workstationId,
            ':seat_code' => $data['seat_code'],
            ':hostname' => $data['hostname'] ?? null,
            ':status' => $data['status'] ?? 'Active',
        ]);
    }

    public function delete(int $workstationId): bool
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare('DELETE FROM workstations WHERE workstation_id = :id');
        return $stmt->execute([':id' => $workstationId]);
    }

    /**
     * Finds active/spare assets in the same lab not currently installed at any workstation.
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function getAvailableSparesForWorkstation(int $workstationId, string $labId): array
    {
        $pdo = $this->getPdo();
        $sql = 'SELECT a.asset_id, a.asset_tag, a.asset_type, a.brand_model, a.serial_number, a.status
                FROM assets a
                LEFT JOIN asset_assignments aa ON aa.asset_id = a.asset_id AND aa.removed_at IS NULL
                WHERE a.lab_id = :lab_id
                  AND aa.assignment_id IS NULL
                  AND a.status IN (\'Active\', \'Inactive\')
                ORDER BY a.asset_type ASC, a.asset_tag ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':lab_id' => $labId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sparesByType = [];
        foreach ($rows as $row) {
            $sparesByType[$row['asset_type']][] = $row;
        }

        return $sparesByType;
    }
}
