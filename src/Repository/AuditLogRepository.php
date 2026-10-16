<?php

declare(strict_types=1);

namespace LabTrack\Repository;

use PDO;
use RuntimeException;

class AuditLogRepository
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

        $sql = 'INSERT INTO audit_logs
                (user_uid, username, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                VALUES
                (:user_uid, :username, :action, :entity_type, :entity_id, :old_values, :new_values, :ip_address, :user_agent)';

        $oldValuesJson = isset($data['old_values']) && is_array($data['old_values'])
            ? json_encode($data['old_values'], JSON_UNESCAPED_UNICODE)
            : ($data['old_values'] ?? null);

        $newValuesJson = isset($data['new_values']) && is_array($data['new_values'])
            ? json_encode($data['new_values'], JSON_UNESCAPED_UNICODE)
            : ($data['new_values'] ?? null);

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':user_uid' => isset($data['user_uid']) && $data['user_uid'] !== null ? (int) $data['user_uid'] : null,
            ':username' => $data['username'] ?? null,
            ':action' => (string) $data['action'],
            ':entity_type' => (string) $data['entity_type'],
            ':entity_id' => (string) $data['entity_id'],
            ':old_values' => $oldValuesJson,
            ':new_values' => $newValuesJson,
            ':ip_address' => $data['ip_address'] ?? null,
            ':user_agent' => isset($data['user_agent']) ? substr((string) $data['user_agent'], 0, 255) : null,
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

        if (!empty($filters['action'])) {
            $where[] = 'action = :action';
            $params[':action'] = (string) $filters['action'];
        }

        if (!empty($filters['entity_type'])) {
            $where[] = 'entity_type = :entity_type';
            $params[':entity_type'] = (string) $filters['entity_type'];
        }

        if (!empty($filters['entity_id'])) {
            $where[] = 'entity_id = :entity_id';
            $params[':entity_id'] = (string) $filters['entity_id'];
        }

        if (!empty($filters['username'])) {
            $where[] = 'username LIKE :username';
            $params[':username'] = '%' . (string) $filters['username'] . '%';
        }

        if (!empty($filters['start_date'])) {
            $where[] = 'created_at >= :start_date';
            $params[':start_date'] = (string) $filters['start_date'] . ' 00:00:00';
        }

        if (!empty($filters['end_date'])) {
            $where[] = 'created_at <= :end_date';
            $params[':end_date'] = (string) $filters['end_date'] . ' 23:59:59';
        }

        if (!empty($filters['search'])) {
            $where[] = '(entity_id LIKE :search OR username LIKE :search OR action LIKE :search OR entity_type LIKE :search)';
            $params[':search'] = '%' . (string) $filters['search'] . '%';
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

        $sql = "SELECT * FROM audit_logs {$whereSql} ORDER BY audit_id DESC LIMIT {$limitInt} OFFSET {$offsetInt}";
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

        $sql = "SELECT COUNT(*) FROM audit_logs {$whereSql}";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }
}
