<?php

declare(strict_types=1);

namespace LabTrack\Controller;

use LabTrack\Http\Request;
use LabTrack\Http\Response;
use LabTrack\Repository\LabRepository;
use LabTrack\Service\GrievanceService;
use LabTrack\Validation\GrievanceRequest;
use LabTrack\View\View;

class GrievanceController
{
    private GrievanceService $grievanceService;
    private LabRepository    $labRepository;

    public function __construct(
        ?GrievanceService $grievanceService = null,
        ?LabRepository    $labRepository = null
    ) {
        $this->grievanceService = $grievanceService ?? new GrievanceService();
        $this->labRepository = $labRepository ?? new LabRepository();
    }

    // -----------------------------------------------------------------------
    // Public: grievance submission form (GET) + store (POST)
    // -----------------------------------------------------------------------

    /**
     * GET /grievances/submit — public, no authentication required.
     */
    public function submit(Request $request): Response
    {
        $labs = [];
        try {
            $labs = $this->labRepository->findAll(null, true);
        } catch (\Throwable) {
            // DB unavailable (static tests / early bootstrap)
        }

        $prefill = [
            'lab_id' => $request->query('lab_id', ''),
            'asset_tag' => $request->query('asset_tag', ''),
            'workstation_id' => $request->query('workstation_id', ''),
            'device_category' => $request->query('device_category', ''),
        ];

        $html = View::render('grievances/submit', [
            'title' => 'Submit a Grievance',
            'labs' => $labs,
            'errors' => [],
            'old' => $prefill,
        ]);

        return Response::html($html);
    }

    /**
     * POST /grievances — public, CSRF-protected.
     */
    public function store(Request $request): Response
    {
        if (!$request->validateCsrf()) {
            return new Response(
                '403 Forbidden: Invalid or missing CSRF token.',
                403,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        }

        $validator = new GrievanceRequest();
        $result = $validator->validateSubmit($request->post());

        if (!empty($result['errors'])) {
            $labs = [];
            try {
                $labs = $this->labRepository->findAll(null, true);
            } catch (\Throwable) {
            }

            $html = View::render('grievances/submit', [
                'title' => 'Submit a Grievance',
                'labs' => $labs,
                'errors' => $result['errors'],
                'old' => $request->post(),
            ]);

            return Response::html($html, 422);
        }

        try {
            $grievanceId = $this->grievanceService->submitGrievance($result['clean']);
            $this->setFlash('success', "Your grievance has been submitted successfully. Reference: #{$grievanceId}");
            return Response::redirect('/grievances/submit?submitted=1');
        } catch (\Throwable $e) {
            $html = View::render('grievances/submit', [
                'title' => 'Submit a Grievance',
                'labs' => [],
                'errors' => ['Unable to submit grievance. Please try again later.'],
                'old' => $request->post(),
            ]);
            return Response::html($html, 500);
        }
    }

    // -----------------------------------------------------------------------
    // Staff/admin: grievance list
    // -----------------------------------------------------------------------

    /**
     * GET /grievances — requires login.
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
            'lab_id' => $request->query('lab_id', ''),
            'status' => $request->query('status', ''),
            'search' => $request->query('search', ''),
        ];

        $page = max(1, (int) $request->query('page', 1));
        $limit = 25;
        $offset = ($page - 1) * $limit;

        $grievances = [];
        $totalCount = 0;
        try {
            $grievances = $this->grievanceService->listGrievances($filters, $isAdmin, $userUid, $username, $fullName, $limit, $offset);
            $totalCount = $this->grievanceService->countGrievances($filters, $isAdmin, $userUid, $username, $fullName);
        } catch (\Throwable) {
        }

        $labs = [];
        try {
            $labs = $this->labRepository->findAll($userUid, $isAdmin, $username, $fullName);
        } catch (\Throwable) {
        }

        $html = View::render('grievances/index', [
            'title' => 'Grievances',
            'grievances' => $grievances,
            'labs' => $labs,
            'filters' => $filters,
            'isAdmin' => $isAdmin,
            'page' => $page,
            'totalCount' => $totalCount,
            'limit' => $limit,
            'flash' => $this->getAndClearFlash(),
        ]);

        return Response::html($html);
    }

    // -----------------------------------------------------------------------
    // Staff/admin: grievance detail
    // -----------------------------------------------------------------------

    /**
     * GET /grievances/{id} — requires login.
     */
    public function show(Request $request, int|string $id): Response
    {
        if (function_exists('requireLogin')) {
            requireLogin();
        }

        $grievanceId = (int) $id;
        $grievance = null;

        try {
            $grievance = $this->grievanceService->getGrievance($grievanceId);
        } catch (\Throwable) {
        }

        if ($grievance === null) {
            return new Response(
                "404 Not Found: Grievance #{$grievanceId} does not exist.",
                404,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        }

        // Incharge lab-level access: only allow if the grievance belongs to their lab
        $isAdmin = function_exists('isAdmin') ? isAdmin() : false;
        if (!$isAdmin) {
            $userUid = function_exists('currentUserUid') ? currentUserUid() : null;
            $username = function_exists('currentUsername') ? currentUsername() : '';
            $fullName = (string) ($_SESSION['full_name'] ?? '');
            $hasAccess = $this->labRepository->canUserAccessLab(
                $grievance['lab_id'],
                $userUid,
                $isAdmin,
                $username,
                $fullName
            );
            if (!$hasAccess) {
                return new Response(
                    '403 Forbidden: You do not have access to this grievance.',
                    403,
                    ['Content-Type' => 'text/plain; charset=UTF-8']
                );
            }
        }

        $comments = $this->grievanceService->getComments($grievanceId, $isAdmin);
        $trackingCode = (string) ($grievance['tracking_code'] ?? '');
        $ticketDetail = $trackingCode !== '' ? $this->grievanceService->getTicketByTrackingCode($trackingCode) : null;
        $history = $ticketDetail['history'] ?? [];

        $html = View::render('grievances/view', [
            'title' => "Grievance #{$grievanceId}",
            'grievance' => $grievance,
            'isAdmin' => $isAdmin,
            'statuses' => GrievanceRequest::ALLOWED_STATUSES,
            'comments' => $comments,
            'history' => $history,
            'flash' => $this->getAndClearFlash(),
        ]);

        return Response::html($html);
    }

    // -----------------------------------------------------------------------
    // Staff/admin: update status
    // -----------------------------------------------------------------------

    /**
     * POST /grievances/{id}/status — requires login; Admin or incharge of the lab.
     */
    public function updateStatus(Request $request, int|string $id): Response
    {
        if (function_exists('requireLogin')) {
            requireLogin();
        }

        if (!$request->validateCsrf()) {
            return new Response(
                '403 Forbidden: Invalid or missing CSRF token.',
                403,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        }

        $grievanceId = (int) $id;

        $grievance = null;
        try {
            $grievance = $this->grievanceService->getGrievance($grievanceId);
        } catch (\Throwable) {
        }

        if ($grievance === null) {
            return new Response(
                "404 Not Found: Grievance #{$grievanceId} does not exist.",
                404,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        }

        // Incharge may only update grievances within their own labs
        $isAdmin = function_exists('isAdmin') ? isAdmin() : false;
        if (!$isAdmin) {
            $userUid = function_exists('currentUserUid') ? currentUserUid() : null;
            $username = function_exists('currentUsername') ? currentUsername() : '';
            $fullName = (string) ($_SESSION['full_name'] ?? '');
            $hasAccess = $this->labRepository->canUserAccessLab(
                $grievance['lab_id'],
                $userUid,
                $isAdmin,
                $username,
                $fullName
            );
            if (!$hasAccess) {
                return new Response(
                    '403 Forbidden: You do not have access to this grievance.',
                    403,
                    ['Content-Type' => 'text/plain; charset=UTF-8']
                );
            }
        }

        $validator = new GrievanceRequest();
        $result = $validator->validateStatusUpdate($request->post());

        if (!empty($result['errors'])) {
            $this->setFlash('error', implode(' ', $result['errors']));
            return Response::redirect("/grievances/{$grievanceId}");
        }

        try {
            $this->grievanceService->updateStatus(
                $grievanceId,
                $result['clean']['status'],
                $result['clean']['admin_note']
            );
            $this->setFlash('success', 'Grievance status updated successfully.');
        } catch (\Throwable $e) {
            $this->setFlash('error', 'Unable to update status: ' . View::e($e->getMessage()));
        }

        return Response::redirect("/grievances/{$grievanceId}");
    }

    // -----------------------------------------------------------------------
    // Admin only: delete grievance
    // -----------------------------------------------------------------------

    /**
     * POST /grievances/{id}/delete — Admin only.
     */
    public function destroy(Request $request, int|string $id): Response
    {
        if (function_exists('requireLogin')) {
            requireLogin();
        }
        if (function_exists('requireRole')) {
            requireRole('Admin');
        }

        if (!$request->validateCsrf()) {
            return new Response(
                '403 Forbidden: Invalid or missing CSRF token.',
                403,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        }

        $grievanceId = (int) $id;

        try {
            $deleted = $this->grievanceService->deleteGrievance($grievanceId);
            if ($deleted) {
                $this->setFlash('success', "Grievance #{$grievanceId} has been deleted.");
            } else {
                $this->setFlash('error', "Grievance #{$grievanceId} not found or could not be deleted.");
            }
        } catch (\Throwable $e) {
            $this->setFlash('error', 'Unable to delete grievance: ' . View::e($e->getMessage()));
        }

        return Response::redirect('/grievances');
    }

    /**
     * Public ticket tracking: GET /tickets/track or GET /tickets/track/{code}
     */
    public function track(Request $request, ?string $code = null): Response
    {
        $trackingCode = $code ?? trim((string) $request->query('code', (string) $request->post('code', '')));
        $ticket = null;

        if ($trackingCode !== '') {
            $ticket = $this->grievanceService->getTicketByTrackingCode($trackingCode);
        }

        if ($request->wantsJson()) {
            if ($ticket === null) {
                return Response::json(['success' => false, 'error' => 'Ticket not found for tracking code.'], 404);
            }
            return Response::json(['success' => true, 'data' => $ticket]);
        }

        $html = View::render('grievances/track', [
            'title' => 'Track Ticket Status',
            'ticket' => $ticket,
            'code' => $trackingCode,
            'flash' => $this->getAndClearFlash(),
        ], 'layout/base');

        return Response::html($html);
    }

    /**
     * POST /grievances/{id}/comments
     */
    public function addComment(Request $request, int|string $id): Response
    {
        $grievanceId = (int) $id;
        $body = trim((string) $request->post('body', ''));

        if ($body === '') {
            if ($request->wantsJson()) {
                return Response::json(['success' => false, 'error' => 'Comment body cannot be empty.'], 422);
            }
            $this->setFlash('error', 'Comment body cannot be empty.');
            return Response::redirect("/grievances/{$grievanceId}");
        }

        $authorUid = function_exists('currentUserUid') ? currentUserUid() : null;
        $authorName = (string) ($_SESSION['full_name'] ?? $_SESSION['username'] ?? $request->post('author_name', 'Student / User'));
        $isInternal = (bool) $request->post('is_internal', false);

        $this->grievanceService->addComment($grievanceId, $body, $authorName, $authorUid, $isInternal);

        if ($request->wantsJson()) {
            return Response::json(['success' => true, 'message' => 'Comment added successfully.']);
        }

        $this->setFlash('success', 'Comment added successfully.');

        $trackingCode = (string) $request->post('tracking_code', '');
        if ($trackingCode !== '' && (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['logged_in']))) {
            return Response::redirect('/tickets/track/' . urlencode($trackingCode));
        }

        return Response::redirect("/grievances/{$grievanceId}");
    }

    // -----------------------------------------------------------------------
    // Flash helpers (same pattern as other controllers)
    // -----------------------------------------------------------------------

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
