<?php

declare(strict_types=1);

namespace LabTrack\Controller;

use LabTrack\Http\Request;
use LabTrack\Http\Response;
use LabTrack\Service\AuditService;
use LabTrack\View\View;

class AuditLogController
{
    private AuditService $auditService;

    public function __construct(?AuditService $auditService = null)
    {
        $this->auditService = $auditService ?? new AuditService();
    }

    /**
     * GET /admin/audit-logs
     */
    public function index(Request $request): Response
    {
        if (function_exists('requireRole')) {
            requireRole('Admin');
        }

        $filters = [
            'action' => trim((string) $request->query('action', '')),
            'entity_type' => trim((string) $request->query('entity_type', '')),
            'entity_id' => trim((string) $request->query('entity_id', '')),
            'username' => trim((string) $request->query('username', '')),
            'start_date' => trim((string) $request->query('start_date', '')),
            'end_date' => trim((string) $request->query('end_date', '')),
            'search' => trim((string) $request->query('search', '')),
        ];

        // Filter out empty strings
        $activeFilters = array_filter($filters, static fn ($v) => $v !== '');

        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(10, min(100, (int) $request->query('per_page', 25)));

        $data = $this->auditService->listLogs($activeFilters, $page, $perPage);

        if ($request->wantsJson()) {
            return Response::json([
                'success' => true,
                'data' => $data['items'],
                'pagination' => [
                    'total' => $data['total'],
                    'page' => $data['page'],
                    'per_page' => $data['per_page'],
                    'total_pages' => $data['total_pages'],
                ],
            ]);
        }

        $html = View::render('admin/audit_logs', [
            'title' => 'Audit Logs',
            'logs' => $data['items'],
            'pagination' => $data,
            'filters' => $filters,
        ]);

        return Response::html($html);
    }
}
