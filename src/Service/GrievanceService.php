<?php

declare(strict_types=1);

namespace LabTrack\Service;

use InvalidArgumentException;
use LabTrack\Repository\GrievanceRepository;
use LabTrack\Repository\LabRepository;

/**
 * GrievanceService — orchestrates submission, status transitions, SLA calculations,
 * and queued email notifications.
 */
class GrievanceService
{
    private GrievanceRepository $grievanceRepo;
    private LabRepository $labRepo;
    private QueueService $queueService;

    public function __construct(
        ?GrievanceRepository $grievanceRepo = null,
        ?LabRepository       $labRepo = null,
        ?QueueService        $queueService = null
    ) {
        $this->grievanceRepo = $grievanceRepo ?? new GrievanceRepository();
        $this->labRepo = $labRepo ?? new LabRepository();
        $this->queueService = $queueService ?? new QueueService();
    }

    public function getLabRepository(): LabRepository
    {
        return $this->labRepo;
    }

    public function getQueueService(): QueueService
    {
        return $this->queueService;
    }

    // -----------------------------------------------------------------------
    // Queries
    // -----------------------------------------------------------------------

    /**
     * Return a paginated, filtered list visible to the current user.
     *
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function listGrievances(
        array  $filters = [],
        bool   $isAdmin = false,
        ?int   $userUid = null,
        string $username = '',
        string $fullName = '',
        int    $limit = 50,
        int    $offset = 0
    ): array {
        return $this->grievanceRepo->listFiltered(
            $filters,
            $isAdmin,
            $userUid,
            $username,
            $fullName,
            $limit,
            $offset
        );
    }

    /**
     * Count total matching grievances (for pagination).
     *
     * @param array<string, mixed> $filters
     */
    public function countGrievances(
        array  $filters = [],
        bool   $isAdmin = false,
        ?int   $userUid = null,
        string $username = '',
        string $fullName = ''
    ): int {
        return $this->grievanceRepo->countFiltered(
            $filters,
            $isAdmin,
            $userUid,
            $username,
            $fullName
        );
    }

    /**
     * Return a single grievance by ID. Returns null if not found.
     *
     * @return array<string, mixed>|null
     */
    public function getGrievance(int $grievanceId): ?array
    {
        return $this->grievanceRepo->findById($grievanceId);
    }

    /**
     * Return a single grievance/ticket by tracking code with comments and history.
     *
     * @return array<string, mixed>|null
     */
    public function getTicketByTrackingCode(string $trackingCode): ?array
    {
        $ticket = $this->grievanceRepo->findByTrackingCode($trackingCode);
        if ($ticket === null) {
            return null;
        }

        $id = (int) $ticket['grievance_id'];
        $ticket['comments'] = $this->grievanceRepo->getComments($id, false);
        $ticket['history'] = $this->grievanceRepo->getStatusHistory($id);

        return $ticket;
    }

    /**
     * Return recently open grievances for dashboard widget.
     *
     * @return array<int, array<string, mixed>>
     */
    public function recentOpen(int $limit = 10, ?int $userUid = null, bool $isAdmin = false, string $username = '', string $fullName = ''): array
    {
        return $this->grievanceRepo->recentOpen($limit, $userUid, $isAdmin, $username, $fullName);
    }

    // -----------------------------------------------------------------------
    // Commands
    // -----------------------------------------------------------------------

    /**
     * Create a new grievance/ticket from validated data with SLA and tracking code.
     * Returns the new grievance_id.
     *
     * @param array<string, mixed> $data
     */
    public function submitGrievance(array $data): int
    {
        $data['status'] = 'Submitted';

        // 1. Generate unique tracking code (e.g. TKT-2026-8F3A9C)
        if (empty($data['tracking_code'])) {
            $data['tracking_code'] = sprintf('TKT-%s-%06X', date('Y'), random_int(0, 0xFFFFFF));
        }

        // 2. Determine priority and SLA targets
        $priority = (string) ($data['priority'] ?? 'Medium');
        $validPriorities = ['Low', 'Medium', 'High', 'Critical'];
        if (!in_array($priority, $validPriorities, true)) {
            $priority = 'Medium';
            $data['priority'] = 'Medium';
        }

        $slaPolicy = $this->grievanceRepo->getSlaPolicy($priority);
        $respHours = $slaPolicy !== null ? (int) $slaPolicy['response_hours'] : match ($priority) {
            'Critical' => 1,
            'High' => 4,
            'Low' => 24,
            default => 8,
        };
        $resolveHours = $slaPolicy !== null ? (int) $slaPolicy['resolve_hours'] : match ($priority) {
            'Critical' => 4,
            'High' => 24,
            'Low' => 120,
            default => 48,
        };

        if (empty($data['sla_response_due_at'])) {
            $data['sla_response_due_at'] = date('Y-m-d H:i:s', time() + ($respHours * 3600));
        }
        if (empty($data['sla_resolve_due_at'])) {
            $data['sla_resolve_due_at'] = date('Y-m-d H:i:s', time() + ($resolveHours * 3600));
        }

        // 3. Auto-assign to lab's primary incharge if available
        if (empty($data['assigned_to_uid']) && !empty($data['lab_id'])) {
            $lab = $this->labRepo->findById((string) $data['lab_id']);
            if ($lab !== null && !empty($lab['incharge_uid'])) {
                $data['assigned_to_uid'] = (int) $lab['incharge_uid'];
            }
        }

        $newId = $this->grievanceRepo->create($data);

        // 4. Initial status history
        $this->grievanceRepo->addStatusHistory($newId, null, 'Submitted', null, 'Ticket created');

        // 5. Audit log
        AuditService::record('CREATE', 'ticket', (string) $newId, null, [
            'tracking_code' => $data['tracking_code'],
            'priority' => $priority,
            'lab_id' => $data['lab_id'],
        ]);

        // 6. Queue email notification to submitter
        $appUrl = function_exists('app_config') ? (app_config()['app']['url'] ?? 'http://localhost:8000') : 'http://localhost:8000';
        $trackingUrl = rtrim((string) $appUrl, '/') . '/tickets/track/' . $data['tracking_code'];
        $subject = "Ticket Created: #{$newId} ({$data['tracking_code']})";
        $body = '<p>Hello ' . htmlspecialchars((string) $data['submitted_by']) . ',</p>'
              . "<p>Your ticket has been logged with ID <strong>#{$newId}</strong>.</p>"
              . '<p>Tracking Code: <strong>' . htmlspecialchars((string) $data['tracking_code']) . '</strong></p>'
              . "<p>Priority: <strong>{$priority}</strong> (SLA Due: {$data['sla_resolve_due_at']})</p>"
              . "<p>You can track live status anytime: <a href=\"{$trackingUrl}\">{$trackingUrl}</a></p>";

        $this->queueService->enqueueEmail((string) $data['stud_email'], $subject, $body, [
            'grievance_id' => $newId,
            'tracking_code' => $data['tracking_code'],
        ]);

        return $newId;
    }

    /**
     * Update grievance status and optionally add an admin note.
     * Records history, writes audit log, and queues an email notification.
     *
     * @throws InvalidArgumentException if status is not allowed
     */
    public function updateStatus(int $grievanceId, string $status, ?string $adminNote = null, ?int $changedByUid = null): bool
    {
        $allowed = ['Submitted', 'In Progress', 'Under Review', 'Resolved', 'Closed'];
        if (!in_array($status, $allowed, true)) {
            throw new InvalidArgumentException("Invalid grievance status: {$status}");
        }

        $grievance = $this->grievanceRepo->findById($grievanceId);
        if ($grievance === null) {
            return false;
        }

        $oldStatus = (string) ($grievance['status'] ?? 'Submitted');
        $ok = $this->grievanceRepo->updateStatus($grievanceId, $status, $adminNote);

        if ($ok) {
            $this->grievanceRepo->addStatusHistory($grievanceId, $oldStatus, $status, $changedByUid, $adminNote);

            AuditService::record('STATUS_CHANGE', 'ticket', (string) $grievanceId, [
                'status' => $oldStatus,
            ], [
                'status' => $status,
                'note' => $adminNote,
            ]);

            $message = $adminNote ?: "Your grievance status has been updated to: {$status}.";
            $this->notifySubmitter(
                toEmail: (string) $grievance['stud_email'],
                senderName: 'LabTrack Admin',
                messageText: $message,
                status: $status,
                grievanceId: $grievanceId
            );
        }

        return $ok;
    }

    /**
     * Add a comment to a ticket.
     */
    public function addComment(
        int $grievanceId,
        string $body,
        string $authorName,
        ?int $authorUid = null,
        bool $isInternal = false
    ): int {
        $commentId = $this->grievanceRepo->addComment($grievanceId, $body, $authorName, $authorUid, $isInternal);

        AuditService::record('UPDATE', 'ticket', (string) $grievanceId, null, [
            'comment_id' => $commentId,
            'is_internal' => $isInternal,
            'author' => $authorName,
        ]);

        return $commentId;
    }

    /**
     * Get comments for a grievance.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getComments(int $grievanceId, bool $includeInternal = false): array
    {
        return $this->grievanceRepo->getComments($grievanceId, $includeInternal);
    }

    /**
     * Delete a grievance permanently.
     */
    public function deleteGrievance(int $grievanceId): bool
    {
        $existing = $this->grievanceRepo->findById($grievanceId);
        $ok = $this->grievanceRepo->destroy($grievanceId);
        if ($ok) {
            AuditService::record('DELETE', 'ticket', (string) $grievanceId, $existing, null);
        }
        return $ok;
    }

    /**
     * Check for SLA breaches and escalate overdue tickets to Admin.
     */
    public function escalateBreachedSlas(): int
    {
        $breaches = $this->grievanceRepo->getPendingSlaBreaches();
        $count = 0;

        foreach ($breaches as $ticket) {
            $ticketId = (int) $ticket['grievance_id'];
            $this->grievanceRepo->markEscalated($ticketId);
            $this->grievanceRepo->addStatusHistory(
                $ticketId,
                (string) $ticket['status'],
                (string) $ticket['status'],
                null,
                'Escalated to Admin: SLA resolve deadline breached'
            );

            AuditService::record('UPDATE', 'ticket', (string) $ticketId, null, [
                'escalated_to_admin' => 1,
                'reason' => 'SLA breach',
            ]);

            $appUrl = function_exists('app_config') ? (app_config()['app']['url'] ?? 'http://localhost:8000') : 'http://localhost:8000';
            $subject = "[SLA Breach Alert] Ticket #{$ticketId} ({$ticket['tracking_code']}) Overdue";
            $body = "<p>Ticket <strong>#{$ticketId}</strong> for lab <strong>" . htmlspecialchars((string) ($ticket['lab_name'] ?? $ticket['lab_id'])) . "</strong> has breached its SLA resolve deadline ({$ticket['sla_resolve_due_at']}).</p>"
                  . '<p>Issue: ' . htmlspecialchars((string) $ticket['grievance_desc']) . '</p>'
                  . "<p><a href=\"{$appUrl}/grievances/{$ticketId}\">View Ticket in Admin Console</a></p>";

            $this->queueService->enqueueEmail('admin@labtrack.local', $subject, $body, [
                'ticket_id' => $ticketId,
                'event' => 'sla_breach',
            ]);

            $count++;
        }

        return $count;
    }

    // -----------------------------------------------------------------------
    // Email notification
    // -----------------------------------------------------------------------

    /**
     * Send a status-update email to the grievance submitter via QueueService.
     */
    public function notifySubmitter(
        string $toEmail,
        string $senderName,
        string $messageText,
        string $status,
        int    $grievanceId
    ): bool {
        try {
            $safeStatus = htmlspecialchars($status, ENT_QUOTES, 'UTF-8');
            $safeId = htmlspecialchars((string) $grievanceId, ENT_QUOTES, 'UTF-8');
            $safeMsg = nl2br(htmlspecialchars($messageText, ENT_QUOTES, 'UTF-8'));

            $body = <<<HTML
<!DOCTYPE html>
<html>
<head>
<style>
  body      { font-family: Arial, sans-serif; color: #333; margin: 0; padding: 20px; }
  .container{ max-width: 600px; margin: 0 auto; border: 1px solid #eee; padding: 20px; }
  .status   { padding: 8px 12px; background: #f5f5f5; display: inline-block; border-radius: 4px; }
  .footer   { margin-top: 20px; font-size: 12px; color: #666; }
</style>
</head>
<body>
  <div class="container">
    <h2>Grievance Update</h2>
    <p><strong>Grievance ID:</strong> #{$safeId}</p>
    <p><strong>Status:</strong> <span class="status">{$safeStatus}</span></p>
    <p><strong>Message:</strong></p>
    <p>{$safeMsg}</p>
    <div class="footer">
      <p>This is an automated message from LabTrack. Please do not reply.</p>
    </div>
  </div>
</body>
</html>
HTML;

            $this->queueService->enqueueEmail($toEmail, "Grievance Status Update - #{$grievanceId}", $body, [
                'grievance_id' => $grievanceId,
                'status' => $status,
            ]);

            return true;
        } catch (\Throwable $e) {
            error_log('GrievanceService notifySubmitter error: ' . $e->getMessage());
            return false;
        }
    }
}
