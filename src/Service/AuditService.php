<?php

declare(strict_types=1);

namespace LabTrack\Service;

use LabTrack\Repository\AuditLogRepository;
use Throwable;

class AuditService
{
    private AuditLogRepository $auditRepo;
    private static ?AuditService $instance = null;

    public function __construct(?AuditLogRepository $auditRepo = null)
    {
        $this->auditRepo = $auditRepo ?? new AuditLogRepository();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function setInstance(?self $instance): void
    {
        self::$instance = $instance;
    }

    /**
     * Record an audit log event statically.
     *
     * @param string $action CREATE, UPDATE, DELETE, SWAP, RETIRE, LOGIN, LOGIN_FAILED, STATUS_CHANGE
     * @param string $entityType asset, workstation, lab, ticket, maintenance, user
     * @param string $entityId ID or unique tag of the entity
     * @param array<string, mixed>|null $oldValues
     * @param array<string, mixed>|null $newValues
     */
    public static function record(
        string $action,
        string $entityType,
        string $entityId,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        try {
            $userUid = null;
            $username = null;

            if (function_exists('currentUserUid')) {
                $userUid = currentUserUid();
            } elseif (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['uid'])) {
                $userUid = (int) $_SESSION['uid'];
            }

            if (function_exists('currentUsername')) {
                $username = currentUsername();
            } elseif (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['username'])) {
                $username = (string) $_SESSION['username'];
            }

            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

            self::getInstance()->log([
                'user_uid' => $userUid,
                'username' => $username,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);
        } catch (Throwable $e) {
            error_log('AuditService::record failed: ' . $e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public function log(array $data): int
    {
        return $this->auditRepo->create($data);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, total_pages: int}
     */
    public function listLogs(array $filters = [], int $page = 1, int $perPage = 30): array
    {
        $page = max(1, $page);
        $perPage = max(5, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $total = $this->auditRepo->countAll($filters);
        $items = $this->auditRepo->findAll($filters, $perPage, $offset);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
        ];
    }
}
