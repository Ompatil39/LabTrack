<?php

declare(strict_types=1);

namespace LabTrack\Repository;

use PDO;
use RuntimeException;

class LabRepository
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
    public function findAll(?int $userUid = null, bool $isAdmin = false, string $username = '', string $fullName = ''): array
    {
        $pdo = $this->getPdo();

        if ($isAdmin) {
            $sql = 'SELECT l.*, u.full_name AS incharge_user_full_name, u.email AS incharge_email
                    FROM labs l
                    LEFT JOIN users u ON (l.incharge_uid IS NOT NULL AND l.incharge_uid = u.uid)
                                      OR (l.incharge_uid IS NULL AND l.lab_incharge = u.username)
                    ORDER BY l.lab_name ASC';
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $displayName = ucfirst(strtolower($username));
        $sql = 'SELECT DISTINCT l.*, u.full_name AS incharge_user_full_name, u.email AS incharge_email
                FROM labs l
                LEFT JOIN users u ON (l.incharge_uid IS NOT NULL AND l.incharge_uid = u.uid)
                                  OR (l.incharge_uid IS NULL AND l.lab_incharge = u.username)
                LEFT JOIN lab_incharges li ON li.lab_id = l.lab_id
                WHERE (
                    (:uid1 IS NOT NULL AND (li.uid = :uid2 OR l.incharge_uid = :uid3))
                    OR l.lab_incharge = :username
                    OR l.lab_incharge = :display_name
                    OR l.lab_incharge = :full_name
                )
                ORDER BY l.lab_name ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':uid1', $userUid, $userUid === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':uid2', $userUid, $userUid === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':uid3', $userUid, $userUid === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':username', $username, PDO::PARAM_STR);
        $stmt->bindValue(':display_name', $displayName, PDO::PARAM_STR);
        $stmt->bindValue(':full_name', $fullName, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(string $labId): ?array
    {
        $pdo = $this->getPdo();
        $sql = 'SELECT l.*, u.full_name AS incharge_user_full_name, u.email AS incharge_email, u.contact_number AS incharge_contact
                FROM labs l
                LEFT JOIN users u ON (l.incharge_uid IS NOT NULL AND l.incharge_uid = u.uid)
                                  OR (l.incharge_uid IS NULL AND l.lab_incharge = u.username)
                WHERE l.lab_id = :lab_id
                LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':lab_id' => $labId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function canUserAccessLab(string $labId, ?int $userUid, bool $isAdmin, string $username, string $fullName = ''): bool
    {
        if ($isAdmin) {
            return true;
        }

        $pdo = $this->getPdo();
        $displayName = ucfirst(strtolower($username));

        $sql = 'SELECT 1
                FROM labs l
                LEFT JOIN lab_incharges li ON li.lab_id = l.lab_id
                WHERE l.lab_id = :lab_id
                  AND (
                    (:uid1 IS NOT NULL AND (li.uid = :uid2 OR l.incharge_uid = :uid3))
                    OR l.lab_incharge = :username
                    OR l.lab_incharge = :display_name
                    OR l.lab_incharge = :full_name
                  )
                LIMIT 1';

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':lab_id', $labId, PDO::PARAM_STR);
        $stmt->bindValue(':uid1', $userUid, $userUid === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':uid2', $userUid, $userUid === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':uid3', $userUid, $userUid === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':username', $username, PDO::PARAM_STR);
        $stmt->bindValue(':display_name', $displayName, PDO::PARAM_STR);
        $stmt->bindValue(':full_name', $fullName, PDO::PARAM_STR);
        $stmt->execute();

        return (bool) $stmt->fetchColumn();
    }

    public function exists(string $labId): bool
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare('SELECT 1 FROM labs WHERE lab_id = :lab_id LIMIT 1');
        $stmt->execute([':lab_id' => $labId]);
        return (bool) $stmt->fetchColumn();
    }

    public function create(array $data): bool
    {
        $pdo = $this->getPdo();

        // Check if incharge_uid column exists in schema or if running against legacy schema
        $hasInchargeUidCol = $this->hasColumn('labs', 'incharge_uid');

        if ($hasInchargeUidCol) {
            $sql = 'INSERT INTO labs (lab_id, lab_name, status, department, lab_incharge, incharge_uid, establishment_date, room_capacity, building, room_number)
                    VALUES (:lab_id, :lab_name, :status, :department, :lab_incharge, :incharge_uid, :establishment_date, :room_capacity, :building, :room_number)';
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([
                ':lab_id' => $data['lab_id'],
                ':lab_name' => $data['lab_name'],
                ':status' => $data['status'],
                ':department' => $data['department'],
                ':lab_incharge' => $data['lab_incharge'],
                ':incharge_uid' => $data['incharge_uid'] ?? null,
                ':establishment_date' => $data['establishment_date'],
                ':room_capacity' => $data['room_capacity'],
                ':building' => $data['building'] ?? null,
                ':room_number' => $data['room_number'],
            ]);
        } else {
            $sql = 'INSERT INTO labs (lab_id, lab_name, status, department, lab_incharge, establishment_date, room_capacity, building, room_number)
                    VALUES (:lab_id, :lab_name, :status, :department, :lab_incharge, :establishment_date, :room_capacity, :building, :room_number)';
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([
                ':lab_id' => $data['lab_id'],
                ':lab_name' => $data['lab_name'],
                ':status' => $data['status'],
                ':department' => $data['department'],
                ':lab_incharge' => $data['lab_incharge'],
                ':establishment_date' => $data['establishment_date'],
                ':room_capacity' => $data['room_capacity'],
                ':building' => $data['building'] ?? null,
                ':room_number' => $data['room_number'],
            ]);
        }

        // Relational sync to lab_incharges if table exists
        if ($result && !empty($data['incharge_uid']) && $this->hasTable('lab_incharges')) {
            try {
                $liStmt = $pdo->prepare('INSERT INTO lab_incharges (lab_id, uid) VALUES (:lab_id, :uid) ON DUPLICATE KEY UPDATE uid = VALUES(uid)');
                $liStmt->execute([':lab_id' => $data['lab_id'], ':uid' => (int) $data['incharge_uid']]);
            } catch (\Throwable) {
                // Non-fatal if table not yet migrated
            }
        }

        return $result;
    }

    public function update(string $labId, array $data): bool
    {
        $pdo = $this->getPdo();
        $hasInchargeUidCol = $this->hasColumn('labs', 'incharge_uid');

        if ($hasInchargeUidCol) {
            $sql = 'UPDATE labs
                    SET lab_name = :lab_name,
                        status = :status,
                        department = :department,
                        lab_incharge = :lab_incharge,
                        incharge_uid = :incharge_uid,
                        establishment_date = :establishment_date,
                        room_capacity = :room_capacity,
                        building = :building,
                        room_number = :room_number
                    WHERE lab_id = :lab_id';
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([
                ':lab_id' => $labId,
                ':lab_name' => $data['lab_name'],
                ':status' => $data['status'],
                ':department' => $data['department'],
                ':lab_incharge' => $data['lab_incharge'],
                ':incharge_uid' => $data['incharge_uid'] ?? null,
                ':establishment_date' => $data['establishment_date'],
                ':room_capacity' => $data['room_capacity'],
                ':building' => $data['building'] ?? null,
                ':room_number' => $data['room_number'],
            ]);
        } else {
            $sql = 'UPDATE labs
                    SET lab_name = :lab_name,
                        status = :status,
                        department = :department,
                        lab_incharge = :lab_incharge,
                        establishment_date = :establishment_date,
                        room_capacity = :room_capacity,
                        building = :building,
                        room_number = :room_number
                    WHERE lab_id = :lab_id';
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([
                ':lab_id' => $labId,
                ':lab_name' => $data['lab_name'],
                ':status' => $data['status'],
                ':department' => $data['department'],
                ':lab_incharge' => $data['lab_incharge'],
                ':establishment_date' => $data['establishment_date'],
                ':room_capacity' => $data['room_capacity'],
                ':building' => $data['building'] ?? null,
                ':room_number' => $data['room_number'],
            ]);
        }

        if ($result && !empty($data['incharge_uid']) && $this->hasTable('lab_incharges')) {
            try {
                $liStmt = $pdo->prepare('INSERT INTO lab_incharges (lab_id, uid) VALUES (:lab_id, :uid) ON DUPLICATE KEY UPDATE uid = VALUES(uid)');
                $liStmt->execute([':lab_id' => $labId, ':uid' => (int) $data['incharge_uid']]);
            } catch (\Throwable) {
                // Non-fatal if table not yet migrated
            }
        }

        return $result;
    }

    public function getAvailableIncharges(): array
    {
        $pdo = $this->getPdo();
        $hasUid = $this->hasColumn('users', 'uid');

        if ($hasUid) {
            $sql = 'SELECT uid, user_id, username, full_name, email, user_type
                    FROM users
                    WHERE user_type IN (\'Incharge\', \'Admin\')
                    ORDER BY full_name ASC';
        } else {
            $sql = 'SELECT NULL as uid, user_id, username, full_name, email, user_type
                    FROM users
                    WHERE user_type IN (\'Incharge\', \'Admin\')
                    ORDER BY full_name ASC';
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLabDeviceStats(string $labId): array
    {
        $pdo = $this->getPdo();
        $stats = [
            'total_devices' => 0,
            'active_devices' => 0,
            'repair_devices' => 0,
            'workstations_count' => 0,
        ];

        // Legacy devices count
        if ($this->hasTable('devices')) {
            try {
                $stmt = $pdo->prepare('SELECT COUNT(*) AS total,
                                              SUM(CASE WHEN status = \'Active\' THEN 1 ELSE 0 END) AS active,
                                              SUM(CASE WHEN status IN (\'Under Repair\', \'In Repair\') THEN 1 ELSE 0 END) AS in_repair
                                       FROM devices WHERE lab_id = :lab_id');
                $stmt->execute([':lab_id' => $labId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $stats['total_devices'] = (int) ($row['total'] ?? 0);
                    $stats['active_devices'] = (int) ($row['active'] ?? 0);
                    $stats['repair_devices'] = (int) ($row['in_repair'] ?? 0);
                }
            } catch (\Throwable) {
                // Ignore
            }
        }

        // Workstations count if Phase 2 workstations table exists
        if ($this->hasTable('workstations')) {
            try {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM workstations WHERE lab_id = :lab_id');
                $stmt->execute([':lab_id' => $labId]);
                $stats['workstations_count'] = (int) $stmt->fetchColumn();
            } catch (\Throwable) {
                // Ignore
            }
        }

        return $stats;
    }

    public function summary(?int $userUid = null, bool $isAdmin = false, string $username = '', string $fullName = ''): array
    {
        $pdo = $this->getPdo();
        $where = [];
        $params = [];

        if (!$isAdmin) {
            $hasInchargeUidCol = $this->hasColumn('labs', 'incharge_uid');
            if ($hasInchargeUidCol) {
                $where[] = '(l.incharge_uid = :uid OR LOWER(l.lab_incharge) = LOWER(:username) OR LOWER(l.lab_incharge) = LOWER(:fullname))';
                $params[':uid'] = $userUid;
                $params[':username'] = $username;
                $params[':fullname'] = $fullName;
            } else {
                $where[] = '(LOWER(l.lab_incharge) = LOWER(:username) OR LOWER(l.lab_incharge) = LOWER(:fullname))';
                $params[':username'] = $username;
                $params[':fullname'] = $fullName;
            }
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT 
                    COUNT(*) AS total_labs,
                    SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) AS active_labs,
                    COALESCE(SUM(room_capacity), 0) AS total_capacity
                FROM labs l
                {$whereClause}";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total_labs' => (int) ($res['total_labs'] ?? 0),
            'active_labs' => (int) ($res['active_labs'] ?? 0),
            'total_capacity' => (int) ($res['total_capacity'] ?? 0),
        ];
    }

    private function hasColumn(string $table, string $column): bool
    {
        try {
            $stmt = $this->getPdo()->prepare('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '` LIKE :col');
            $stmt->execute([':col' => $column]);
            return (bool) $stmt->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    private function hasTable(string $table): bool
    {
        try {
            $stmt = $this->getPdo()->prepare('SHOW TABLES LIKE :tbl');
            $stmt->execute([':tbl' => $table]);
            return (bool) $stmt->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }
}
