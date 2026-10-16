<?php

declare(strict_types=1);

namespace LabTrack\Repository;

use PDO;
use RuntimeException;

class GrievanceRepository
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
     * Create a new grievance. Returns the new grievance_id.
     */
    public function create(array $data): int
    {
        $pdo = $this->getPdo();

        $hasTrackingCol = $this->hasColumn('grievances', 'tracking_code');
        $hasAssetCols = $this->hasColumn('grievances', 'asset_id');

        if ($hasTrackingCol) {
            $sql = 'INSERT INTO grievances
                        (tracking_code, priority, assigned_to_uid,
                         sla_response_due_at, sla_resolve_due_at,
                         submitted_by, stud_enrollment, class, lab_id,
                         device_id, device_category, device_name,
                         asset_id, workstation_id,
                         stud_email, grievance_desc, status)
                    VALUES
                        (:tracking_code, :priority, :assigned_to_uid,
                         :sla_response_due_at, :sla_resolve_due_at,
                         :submitted_by, :stud_enrollment, :class, :lab_id,
                         :device_id, :device_category, :device_name,
                         :asset_id, :workstation_id,
                         :stud_email, :grievance_desc, :status)';

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':tracking_code' => $data['tracking_code'] ?? null,
                ':priority' => $data['priority'] ?? 'Medium',
                ':assigned_to_uid' => isset($data['assigned_to_uid']) && $data['assigned_to_uid'] !== null ? (int) $data['assigned_to_uid'] : null,
                ':sla_response_due_at' => $data['sla_response_due_at'] ?? null,
                ':sla_resolve_due_at' => $data['sla_resolve_due_at'] ?? null,
                ':submitted_by' => $data['submitted_by'],
                ':stud_enrollment' => $data['stud_enrollment'],
                ':class' => $data['class'],
                ':lab_id' => $data['lab_id'],
                ':device_id' => $data['device_id'] ?? null,
                ':device_category' => $data['device_category'],
                ':device_name' => $data['device_name'] ?? null,
                ':asset_id' => isset($data['asset_id']) ? (int) $data['asset_id'] : null,
                ':workstation_id' => isset($data['workstation_id']) ? (int) $data['workstation_id'] : null,
                ':stud_email' => $data['stud_email'],
                ':grievance_desc' => $data['grievance_desc'],
                ':status' => $data['status'] ?? 'Submitted',
            ]);
        } elseif ($hasAssetCols) {
            $sql = 'INSERT INTO grievances
                        (submitted_by, stud_enrollment, class, lab_id,
                         device_id, device_category, device_name,
                         asset_id, workstation_id,
                         stud_email, grievance_desc, status)
                    VALUES
                        (:submitted_by, :stud_enrollment, :class, :lab_id,
                         :device_id, :device_category, :device_name,
                         :asset_id, :workstation_id,
                         :stud_email, :grievance_desc, :status)';

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':submitted_by' => $data['submitted_by'],
                ':stud_enrollment' => $data['stud_enrollment'],
                ':class' => $data['class'],
                ':lab_id' => $data['lab_id'],
                ':device_id' => $data['device_id'] ?? null,
                ':device_category' => $data['device_category'],
                ':device_name' => $data['device_name'] ?? null,
                ':asset_id' => isset($data['asset_id']) ? (int) $data['asset_id'] : null,
                ':workstation_id' => isset($data['workstation_id']) ? (int) $data['workstation_id'] : null,
                ':stud_email' => $data['stud_email'],
                ':grievance_desc' => $data['grievance_desc'],
                ':status' => $data['status'] ?? 'Submitted',
            ]);
        } else {
            // Legacy schema without asset_id / workstation_id columns
            $sql = 'INSERT INTO grievances
                        (submitted_by, stud_enrollment, class, lab_id,
                         device_id, device_category, device_name,
                         stud_email, grievance_desc, status)
                    VALUES
                        (:submitted_by, :stud_enrollment, :class, :lab_id,
                         :device_id, :device_category, :device_name,
                         :stud_email, :grievance_desc, :status)';

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':submitted_by' => $data['submitted_by'],
                ':stud_enrollment' => $data['stud_enrollment'],
                ':class' => $data['class'],
                ':lab_id' => $data['lab_id'],
                ':device_id' => $data['device_id'] ?? null,
                ':device_category' => $data['device_category'],
                ':device_name' => $data['device_name'] ?? null,
                ':stud_email' => $data['stud_email'],
                ':grievance_desc' => $data['grievance_desc'],
                ':status' => $data['status'] ?? 'Submitted',
            ]);
        }

        return (int) $pdo->lastInsertId();
    }

    /**
     * Return a paginated, filtered list of grievances.
     * Admins see all; incharges see only their labs.
     */
    public function listFiltered(
        array  $filters = [],
        bool   $isAdmin = false,
        ?int   $userUid = null,
        string $username = '',
        string $fullName = '',
        int    $limit = 50,
        int    $offset = 0
    ): array {
        $pdo = $this->getPdo();
        $where = ['1=1'];
        $params = [];

        if (!$isAdmin) {
            // Incharge: restrict to labs they manage
            $where[] = '(l.incharge_uid = :uid OR LOWER(l.lab_incharge) = LOWER(:username) OR LOWER(l.lab_incharge) = LOWER(:fullname))';
            $params[':uid'] = $userUid;
            $params[':username'] = $username;
            $params[':fullname'] = $fullName;
        }

        if (!empty($filters['lab_id'])) {
            $where[] = 'g.lab_id = :lab_id';
            $params[':lab_id'] = $filters['lab_id'];
        }

        if (!empty($filters['status'])) {
            $where[] = 'g.status = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $where[] = '(g.submitted_by LIKE :search OR g.grievance_desc LIKE :search OR g.stud_enrollment LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $whereSql = implode(' AND ', $where);
        $limitInt = max(1, min(200, $limit));
        $offsetInt = max(0, $offset);

        $hasAssetCols = $this->hasColumn('grievances', 'asset_id');
        $assetJoin = $hasAssetCols
            ? 'LEFT JOIN assets a ON g.asset_id = a.asset_id
               LEFT JOIN workstations w ON g.workstation_id = w.workstation_id'
            : '';
        $assetSelect = $hasAssetCols
            ? ', a.asset_tag, a.asset_type, a.brand_model AS asset_brand_model, w.seat_code'
            : '';

        $sql = "SELECT g.*, l.lab_name{$assetSelect}
                FROM grievances g
                LEFT JOIN labs l ON g.lab_id = l.lab_id
                {$assetJoin}
                WHERE {$whereSql}
                ORDER BY g.submission_date DESC
                LIMIT {$limitInt} OFFSET {$offsetInt}";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count total matching rows (for pagination).
     */
    public function countFiltered(
        array  $filters = [],
        bool   $isAdmin = false,
        ?int   $userUid = null,
        string $username = '',
        string $fullName = ''
    ): int {
        $pdo = $this->getPdo();
        $where = ['1=1'];
        $params = [];

        if (!$isAdmin) {
            $where[] = '(l.incharge_uid = :uid OR LOWER(l.lab_incharge) = LOWER(:username) OR LOWER(l.lab_incharge) = LOWER(:fullname))';
            $params[':uid'] = $userUid;
            $params[':username'] = $username;
            $params[':fullname'] = $fullName;
        }

        if (!empty($filters['lab_id'])) {
            $where[] = 'g.lab_id = :lab_id';
            $params[':lab_id'] = $filters['lab_id'];
        }

        if (!empty($filters['status'])) {
            $where[] = 'g.status = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $where[] = '(g.submitted_by LIKE :search OR g.grievance_desc LIKE :search OR g.stud_enrollment LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $whereSql = implode(' AND ', $where);

        $sql = "SELECT COUNT(g.grievance_id)
                FROM grievances g
                LEFT JOIN labs l ON g.lab_id = l.lab_id
                WHERE {$whereSql}";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Find a single grievance by ID, with joined data.
     */
    public function findById(int $grievanceId): ?array
    {
        $pdo = $this->getPdo();

        $hasAssetCols = $this->hasColumn('grievances', 'asset_id');
        $assetJoin = $hasAssetCols
            ? 'LEFT JOIN assets a ON g.asset_id = a.asset_id
               LEFT JOIN workstations w ON g.workstation_id = w.workstation_id'
            : '';
        $assetSelect = $hasAssetCols
            ? ', a.asset_tag, a.asset_type, a.brand_model AS asset_brand_model, w.seat_code'
            : '';

        $sql = "SELECT g.*, l.lab_name, d.device_name AS legacy_device_name{$assetSelect}
                FROM grievances g
                LEFT JOIN labs l ON g.lab_id = l.lab_id
                LEFT JOIN devices d ON g.device_id = d.device_id
                {$assetJoin}
                WHERE g.grievance_id = :id
                LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $grievanceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Update the status and optional admin_note of a grievance.
     */
    public function updateStatus(int $grievanceId, string $status, ?string $adminNote = null): bool
    {
        $pdo = $this->getPdo();
        $hasSlaCols = $this->hasColumn('grievances', 'first_responded_at');

        $extraSql = '';
        if ($hasSlaCols) {
            if (in_array($status, ['In Progress', 'Under Review'], true)) {
                $extraSql = ', first_responded_at = COALESCE(first_responded_at, NOW())';
            } elseif (in_array($status, ['Resolved', 'Closed'], true)) {
                $extraSql = ', first_responded_at = COALESCE(first_responded_at, NOW()), resolved_at = COALESCE(resolved_at, NOW())';
            }
        }

        if ($adminNote !== null) {
            $stmt = $pdo->prepare(
                "UPDATE grievances SET status = :status, admin_note = :note {$extraSql} WHERE grievance_id = :id"
            );
            return $stmt->execute([':status' => $status, ':note' => $adminNote, ':id' => $grievanceId]);
        }

        $stmt = $pdo->prepare("UPDATE grievances SET status = :status {$extraSql} WHERE grievance_id = :id");
        return $stmt->execute([':status' => $status, ':id' => $grievanceId]);
    }

    public function findByTrackingCode(string $trackingCode): ?array
    {
        $pdo = $this->getPdo();

        $hasAssetCols = $this->hasColumn('grievances', 'asset_id');
        $assetJoin = $hasAssetCols
            ? 'LEFT JOIN assets a ON g.asset_id = a.asset_id
               LEFT JOIN workstations w ON g.workstation_id = w.workstation_id'
            : '';
        $assetSelect = $hasAssetCols
            ? ', a.asset_tag, a.asset_type, a.brand_model AS asset_brand_model, w.seat_code'
            : '';

        $sql = "SELECT g.*, l.lab_name, d.device_name AS legacy_device_name{$assetSelect}
                FROM grievances g
                LEFT JOIN labs l ON g.lab_id = l.lab_id
                LEFT JOIN devices d ON g.device_id = d.device_id
                {$assetJoin}
                WHERE g.tracking_code = :code
                LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':code' => $trackingCode]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function addComment(
        int $grievanceId,
        string $body,
        string $authorName,
        ?int $authorUid = null,
        bool $isInternal = false
    ): int {
        $pdo = $this->getPdo();
        $sql = 'INSERT INTO ticket_comments (grievance_id, author_uid, author_name, is_internal, body)
                VALUES (:gid, :uid, :name, :internal, :body)';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':gid' => $grievanceId,
            ':uid' => $authorUid,
            ':name' => $authorName,
            ':internal' => $isInternal ? 1 : 0,
            ':body' => $body,
        ]);
        return (int) $pdo->lastInsertId();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getComments(int $grievanceId, bool $includeInternal = false): array
    {
        $pdo = $this->getPdo();
        $sql = 'SELECT * FROM ticket_comments WHERE grievance_id = :gid';
        if (!$includeInternal) {
            $sql .= ' AND is_internal = 0';
        }
        $sql .= ' ORDER BY created_at ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':gid' => $grievanceId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addStatusHistory(
        int $grievanceId,
        ?string $oldStatus,
        string $newStatus,
        ?int $changedByUid = null,
        ?string $note = null
    ): int {
        $pdo = $this->getPdo();
        $sql = 'INSERT INTO ticket_status_history (grievance_id, old_status, new_status, changed_by_uid, note)
                VALUES (:gid, :old, :new, :uid, :note)';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':gid' => $grievanceId,
            ':old' => $oldStatus,
            ':new' => $newStatus,
            ':uid' => $changedByUid,
            ':note' => $note,
        ]);
        return (int) $pdo->lastInsertId();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getStatusHistory(int $grievanceId): array
    {
        $pdo = $this->getPdo();
        $sql = 'SELECT tsh.*, u.username, u.full_name
                FROM ticket_status_history tsh
                LEFT JOIN users u ON tsh.changed_by_uid = u.uid
                WHERE tsh.grievance_id = :gid
                ORDER BY tsh.created_at ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':gid' => $grievanceId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getSlaPolicy(string $priority): ?array
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare('SELECT * FROM sla_policies WHERE priority = :p LIMIT 1');
        $stmt->execute([':p' => $priority]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getPendingSlaBreaches(): array
    {
        $pdo = $this->getPdo();
        $sql = "SELECT g.*, l.lab_name
                FROM grievances g
                LEFT JOIN labs l ON g.lab_id = l.lab_id
                WHERE g.status NOT IN ('Resolved', 'Closed')
                  AND g.escalated_to_admin = 0
                  AND g.sla_resolve_due_at IS NOT NULL
                  AND g.sla_resolve_due_at <= NOW()";
        $stmt = $pdo->query($sql);
        if ($stmt === false) {
            return [];
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function markEscalated(int $grievanceId): bool
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare('UPDATE grievances SET escalated_to_admin = 1 WHERE grievance_id = :id');
        return $stmt->execute([':id' => $grievanceId]);
    }

    /**
     * @return array{mttr_hours: float, tickets_per_lab: array<int, array<string, mixed>>, sla_breach_count: int}
     */
    public function getAnalyticsSummary(
        ?int $userUid = null,
        bool $isAdmin = false,
        string $username = '',
        string $fullName = ''
    ): array {
        $pdo = $this->getPdo();

        $where = ['1=1'];
        $params = [];
        if (!$isAdmin) {
            $where[] = '(l.incharge_uid = :uid OR LOWER(l.lab_incharge) = LOWER(:username) OR LOWER(l.lab_incharge) = LOWER(:fullname))';
            $params[':uid'] = $userUid;
            $params[':username'] = $username;
            $params[':fullname'] = $fullName;
        }
        $whereSql = implode(' AND ', $where);

        // 1. Mean Time To Resolution (MTTR)
        $hasResolvedCol = $this->hasColumn('grievances', 'resolved_at');
        $resolvedField = $hasResolvedCol ? 'COALESCE(g.resolved_at, g.submission_date)' : 'g.submission_date';
        $mttrSql = "SELECT AVG(TIMESTAMPDIFF(HOUR, g.submission_date, {$resolvedField})) AS mttr
                    FROM grievances g
                    LEFT JOIN labs l ON g.lab_id = l.lab_id
                    WHERE {$whereSql} AND g.status IN ('Resolved', 'Closed')";
        $stmtMttr = $pdo->prepare($mttrSql);
        $stmtMttr->execute($params);
        $mttrVal = $stmtMttr->fetchColumn();
        $mttr = $mttrVal !== false && $mttrVal !== null ? (float) $mttrVal : 0.0;

        // 2. Tickets per lab
        $tplSql = "SELECT g.lab_id, COALESCE(l.lab_name, g.lab_id) AS lab_name, COUNT(g.grievance_id) AS total
                   FROM grievances g
                   LEFT JOIN labs l ON g.lab_id = l.lab_id
                   WHERE {$whereSql}
                   GROUP BY g.lab_id, l.lab_name";
        $stmtTpl = $pdo->prepare($tplSql);
        $stmtTpl->execute($params);
        $ticketsPerLab = $stmtTpl->fetchAll(PDO::FETCH_ASSOC);

        // 3. SLA breach count
        $hasSlaCol = $this->hasColumn('grievances', 'sla_resolve_due_at');
        $slaBreachCount = 0;
        if ($hasSlaCol) {
            $slaSql = "SELECT COUNT(*) FROM grievances g
                       LEFT JOIN labs l ON g.lab_id = l.lab_id
                       WHERE {$whereSql}
                         AND (g.escalated_to_admin = 1
                              OR (g.status NOT IN ('Resolved', 'Closed') AND g.sla_resolve_due_at IS NOT NULL AND g.sla_resolve_due_at <= NOW()))";
            $stmtSla = $pdo->prepare($slaSql);
            $stmtSla->execute($params);
            $slaBreachCount = (int) $stmtSla->fetchColumn();
        }

        return [
            'mttr_hours' => round($mttr, 1),
            'tickets_per_lab' => $ticketsPerLab,
            'sla_breach_count' => $slaBreachCount,
        ];
    }

    /**
     * Delete a grievance by ID.
     */
    public function destroy(int $grievanceId): bool
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare('DELETE FROM grievances WHERE grievance_id = :id');
        return $stmt->execute([':id' => $grievanceId]);
    }

    /**
     * Return recently submitted open grievances (for dashboard use).
     */
    public function recentOpen(int $limit = 10, ?int $userUid = null, bool $isAdmin = false, string $username = '', string $fullName = ''): array
    {
        $pdo = $this->getPdo();
        $params = [':status' => 'Resolved', ':closed' => 'Closed'];
        $where = 'g.status NOT IN (:status, :closed)';

        if (!$isAdmin) {
            $where .= ' AND (l.incharge_uid = :uid OR LOWER(l.lab_incharge) = LOWER(:username) OR LOWER(l.lab_incharge) = LOWER(:fullname))';
            $params[':uid'] = $userUid;
            $params[':username'] = $username;
            $params[':fullname'] = $fullName;
        }

        $sql = "SELECT g.grievance_id, g.submitted_by, g.lab_id, g.status, g.grievance_desc, g.submission_date, l.lab_name
                FROM grievances g
                LEFT JOIN labs l ON g.lab_id = l.lab_id
                WHERE {$where}
                ORDER BY g.submission_date DESC
                LIMIT " . max(1, min(50, $limit));

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countByStatus(
        ?int $userUid = null,
        bool $isAdmin = false,
        string $username = '',
        string $fullName = ''
    ): array {
        $pdo = $this->getPdo();
        $where = ['1=1'];
        $params = [];

        if (!$isAdmin) {
            $where[] = '(l.incharge_uid = :uid OR LOWER(l.lab_incharge) = LOWER(:username) OR LOWER(l.lab_incharge) = LOWER(:fullname))';
            $params[':uid'] = $userUid;
            $params[':username'] = $username;
            $params[':fullname'] = $fullName;
        }

        $whereSql = implode(' AND ', $where);
        $sql = "SELECT g.status, COUNT(g.grievance_id) AS total
                FROM grievances g
                LEFT JOIN labs l ON g.lab_id = l.lab_id
                WHERE {$whereSql}
                GROUP BY g.status";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        return $rows ?: [];
    }

    private function hasColumn(string $table, string $column): bool
    {
        try {
            $pdo = $this->getPdo();
            $stmt = $pdo->prepare('SHOW COLUMNS FROM `' . $table . '` LIKE :col');
            $stmt->execute([':col' => $column]);
            return (bool) $stmt->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }
}
