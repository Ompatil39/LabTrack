<?php

declare(strict_types=1);

namespace LabTrack\Controller;

use LabTrack\Http\Request;
use LabTrack\Http\Response;
use LabTrack\Repository\LabRepository;
use LabTrack\Service\MaintenanceService;
use LabTrack\Validation\MaintenanceRequest;
use LabTrack\View\View;

class MaintenanceController
{
    private MaintenanceService $maintenanceService;
    private LabRepository $labRepo;

    public function __construct(
        ?MaintenanceService $maintenanceService = null,
        ?LabRepository $labRepo = null
    ) {
        $this->maintenanceService = $maintenanceService ?? new MaintenanceService();
        $this->labRepo = $labRepo ?? new LabRepository();
    }

    /**
     * GET /maintenance — list maintenance logs.
     */
    public function index(Request $request): Response
    {
        if (function_exists('requireLogin')) {
            requireLogin();
        }

        $isAdmin = function_exists('isAdmin') ? isAdmin() : false;
        $userUid = function_exists('currentUserUid') ? currentUserUid() : null;
        $username = function_exists('currentUsername') ? currentUsername() : '';
        $fullName = (string) ($_SESSION['full_name'] ?? '');

        $filters = [
            'lab_id' => trim((string) $request->query('lab_id', '')),
            'maintenance_type' => trim((string) $request->query('maintenance_type', '')),
            'asset_id' => trim((string) $request->query('asset_id', '')),
        ];
        $activeFilters = array_filter($filters, static fn ($v) => $v !== '');

        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(10, min(100, (int) $request->query('per_page', 25)));

        $data = $this->maintenanceService->listMaintenance($activeFilters, $page, $perPage);
        $labs = $this->labRepo->findAll($userUid, $isAdmin, $username, $fullName);

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

        $html = View::render('maintenance/index', [
            'title' => 'Asset Maintenance & Lifecycle',
            'records' => $data['items'],
            'pagination' => $data,
            'filters' => $filters,
            'labs' => $labs,
            'isAdmin' => $isAdmin,
            'flash' => $this->getAndClearFlash(),
        ]);

        return Response::html($html);
    }

    /**
     * POST /maintenance — log maintenance record.
     */
    public function store(Request $request): Response
    {
        if (function_exists('requireLogin')) {
            requireLogin();
        }

        if (!$request->validateCsrf()) {
            return new Response('403 Forbidden: Invalid or missing CSRF token.', 403, ['Content-Type' => 'text/plain']);
        }

        $validator = new MaintenanceRequest();
        $validation = $validator->validate($request->post());

        if (!$validation['valid']) {
            if ($request->wantsJson()) {
                return Response::json(['success' => false, 'errors' => $validation['errors']], 422);
            }
            $this->setFlash('error', implode(' ', $validation['errors']));
            return Response::redirect('/maintenance');
        }

        $userUid = function_exists('currentUserUid') ? currentUserUid() : null;

        try {
            $recordId = $this->maintenanceService->logMaintenance($validation['data'], $userUid);
            if ($request->wantsJson()) {
                return Response::json(['success' => true, 'record_id' => $recordId]);
            }
            $this->setFlash('success', "Maintenance record #{$recordId} logged successfully.");
        } catch (\Throwable $e) {
            if ($request->wantsJson()) {
                return Response::json(['success' => false, 'error' => $e->getMessage()], 500);
            }
            $this->setFlash('error', 'Unable to log maintenance: ' . View::e($e->getMessage()));
        }

        return Response::redirect('/maintenance');
    }

    /**
     * GET /maintenance/reports — warranty alerts and straight-line depreciation report.
     */
    public function reports(Request $request): Response
    {
        if (function_exists('requireLogin')) {
            requireLogin();
        }

        $reports = $this->maintenanceService->getReports();

        if ($request->wantsJson()) {
            return Response::json([
                'success' => true,
                'data' => $reports,
            ]);
        }

        $html = View::render('maintenance/reports', [
            'title' => 'Warranty Alerts & Depreciation Report',
            'reports' => $reports,
            'flash' => $this->getAndClearFlash(),
        ]);

        return Response::html($html);
    }

    private function setFlash(string $type, string $message): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['flash_message'] = [
                'type' => $type,
                'message' => $message,
            ];
        }
    }

    private function getAndClearFlash(): ?array
    {
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['flash_message'])) {
            $flash = $_SESSION['flash_message'];
            unset($_SESSION['flash_message']);
            return is_array($flash) ? $flash : null;
        }
        return null;
    }
}
