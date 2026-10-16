<?php

declare(strict_types=1);

namespace LabTrack\Controller;

use LabTrack\Http\Request;
use LabTrack\Http\Response;
use LabTrack\Repository\GrievanceRepository;
use LabTrack\Repository\AssetRepository;
use LabTrack\View\View;

class NotificationController
{
    private GrievanceRepository $grievanceRepo;
    private AssetRepository $assetRepo;

    public function __construct(
        ?GrievanceRepository $grievanceRepo = null,
        ?AssetRepository $assetRepo = null
    ) {
        $this->grievanceRepo = $grievanceRepo ?? new GrievanceRepository();
        $this->assetRepo = $assetRepo ?? new AssetRepository();
    }

    public function getAssetRepository(): AssetRepository
    {
        return $this->assetRepo;
    }

    /**
     * GET /notifications — requires login.
     * Displays alerts for recent grievances, device repairs, and status updates.
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

        $readIds = $_SESSION['read_notifications'] ?? [];
        if (!is_array($readIds)) {
            $readIds = [];
        }

        // 1. Fetch recent open grievances for the user's scope
        $grievances = [];
        try {
            $grievances = $this->grievanceRepo->recentOpen(25, $userUid, $isAdmin, $username, $fullName);
        } catch (\Throwable) {
        }

        // Format as unified notification items
        $notifications = [];
        foreach ($grievances as $g) {
            $id = (int) $g['grievance_id'];
            $isRead = in_array($id, $readIds, true);
            $notifications[] = [
                'id' => $id,
                'type' => 'grievance',
                'title' => "Grievance #{$id}: " . ($g['submitted_by'] ?? 'Student'),
                'message' => $g['grievance_desc'] ?? '',
                'lab_name' => $g['lab_name'] ?? $g['lab_id'] ?? 'Lab',
                'status' => $g['status'] ?? 'Submitted',
                'timestamp' => $g['submission_date'] ?? date('Y-m-d H:i:s'),
                'link' => "/grievances/{$id}",
                'is_read' => $isRead,
            ];
        }

        $html = View::render('notifications/index', [
            'title' => 'System Notifications & Alerts',
            'notifications' => $notifications,
            'isAdmin' => $isAdmin,
        ]);

        return Response::html($html);
    }

    /**
     * POST /notifications/{id}/read — mark notification as read in session.
     */
    public function markRead(Request $request, int|string $id): Response
    {
        if (function_exists('requireLogin')) {
            requireLogin();
        }

        if (!$request->validateCsrf()) {
            return new Response('403 Forbidden', 403, ['Content-Type' => 'text/plain']);
        }

        $nid = (int) $id;
        if (!isset($_SESSION['read_notifications']) || !is_array($_SESSION['read_notifications'])) {
            $_SESSION['read_notifications'] = [];
        }
        if (!in_array($nid, $_SESSION['read_notifications'], true)) {
            $_SESSION['read_notifications'][] = $nid;
        }

        if ($request->isAjax() || str_contains((string) $request->header('Accept'), 'application/json')) {
            return Response::json(['success' => true, 'marked' => $nid]);
        }

        return Response::redirect('/notifications');
    }
}
