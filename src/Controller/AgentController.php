<?php

declare(strict_types=1);

namespace LabTrack\Controller;

use InvalidArgumentException;
use LabTrack\Http\Request;
use LabTrack\Http\Response;
use LabTrack\Repository\LabRepository;
use LabTrack\Repository\WorkstationRepository;
use LabTrack\Service\TelemetryService;
use Throwable;

class AgentController
{
    private TelemetryService $telemetryService;
    private WorkstationRepository $workstationRepo;
    private LabRepository $labRepo;

    public function __construct(
        ?TelemetryService $telemetryService = null,
        ?WorkstationRepository $workstationRepo = null,
        ?LabRepository $labRepo = null
    ) {
        $this->telemetryService = $telemetryService ?? new TelemetryService();
        $this->workstationRepo = $workstationRepo ?? new WorkstationRepository();
        $this->labRepo = $labRepo ?? new LabRepository();
    }

    /**
     * POST /workstations/{id}/enroll-token (admin, CSRF protected)
     */
    public function createEnrollToken(Request $request, int|string $id): Response
    {
        if (function_exists('requireLogin')) {
            requireLogin();
        }
        if (function_exists('requireRole')) {
            requireRole('Admin');
        }

        $csrfToken = $request->input('csrf_token') ?? $request->header('X-CSRF-Token');
        if (!function_exists('verifyCsrfToken') || !verifyCsrfToken(is_string($csrfToken) ? $csrfToken : null)) {
            return $this->errorResponse('Invalid or missing CSRF token.', 419);
        }

        $wsId = (int) $id;
        $ws = $this->workstationRepo->findById($wsId);
        if ($ws === null) {
            return $this->errorResponse("Workstation #{$wsId} not found.", 404);
        }

        $adminUid = function_exists('currentUserUid') ? currentUserUid() : null;

        try {
            $tokenData = $this->telemetryService->createEnrollmentToken($wsId, $adminUid);
        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }

        $serverUrl = $this->determineServerUrl($request);
        $command = "python agent/labtrack_agent.py --enroll --server {$serverUrl} --token {$tokenData['token']}";

        $tokenData['enroll_command'] = $command;

        // Check if JSON requested
        if ($this->wantsJson($request)) {
            return $this->successResponse($tokenData);
        }

        // Set flash message for web UI redirect
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['flash_message'] = [
                'type' => 'success',
                'message' => 'Enrollment token generated successfully.',
                'enroll_token' => $tokenData['token'],
                'enroll_command' => $command,
                'enroll_expires_at' => $tokenData['expires_at'],
            ];
        }

        return Response::redirect("/workstations/{$wsId}");
    }

    /**
     * POST /api/agent/enroll (no session; rate limited by IP, 10 per minute)
     */
    public function enroll(Request $request): Response
    {
        $clientIp = (string) ($request->server('REMOTE_ADDR') ?? '127.0.0.1');

        if ($this->isEnrollRateLimited($clientIp)) {
            return $this->errorResponse('Too Many Requests: enrollment rate limit exceeded.', 429);
        }

        $rawBody = $this->getRawBody($request);
        $payload = [];
        if ($rawBody !== '') {
            $decoded = json_decode($rawBody, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }
        if (empty($payload)) {
            $payload = $request->all();
        }

        $token = (string) ($payload['token'] ?? '');
        $hostname = (string) ($payload['hostname'] ?? 'Unknown-Host');
        $mac = isset($payload['mac_address']) && is_string($payload['mac_address']) ? $payload['mac_address'] : null;
        $ip = isset($payload['ip_address']) && is_string($payload['ip_address']) ? $payload['ip_address'] : $clientIp;
        $agentVersion = isset($payload['agent_version']) && is_string($payload['agent_version']) ? $payload['agent_version'] : null;

        if ($token === '') {
            return $this->errorResponse('Enrollment token is required.', 401);
        }

        try {
            $result = $this->telemetryService->enrollAgent($token, $hostname, $mac, $ip, $agentVersion);
            return $this->successResponse($result, 200);
        } catch (Throwable $e) {
            return $this->errorResponse('Unauthorized: invalid or expired enrollment token.', 401);
        }
    }

    /**
     * POST /api/agent/heartbeat (HMAC-authenticated)
     */
    public function heartbeat(Request $request): Response
    {
        $rawBody = $this->getRawBody($request);

        $auth = $this->telemetryService->authenticateAgentRequest($request, $rawBody);
        if ($auth === null) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $workstationId = (int) $auth['credential']['workstation_id'];

        if ($this->telemetryService->isRateLimited($workstationId)) {
            return $this->errorResponse('Rate limit exceeded (max 12 samples per minute).', 429);
        }

        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            return $this->errorResponse('Invalid JSON body.', 422);
        }

        try {
            $clientIp = $request->server('REMOTE_ADDR');
            $this->telemetryService->recordHeartbeat($workstationId, $payload, $clientIp);
            return $this->successResponse(['message' => 'Heartbeat recorded', 'workstation_id' => $workstationId]);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->errorResponse('Failed to record heartbeat.', 500);
        }
    }

    /**
     * POST /api/agent/inventory (HMAC-authenticated)
     */
    public function inventory(Request $request): Response
    {
        $rawBody = $this->getRawBody($request);

        $auth = $this->telemetryService->authenticateAgentRequest($request, $rawBody);
        if ($auth === null) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $workstationId = (int) $auth['credential']['workstation_id'];

        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            return $this->errorResponse('Invalid JSON body.', 422);
        }

        try {
            $openDriftAlerts = $this->telemetryService->recordInventoryAndDetectDrift($workstationId, $payload);
            return $this->successResponse([
                'message' => 'Inventory recorded',
                'workstation_id' => $workstationId,
                'open_drift_alerts' => $openDriftAlerts,
            ]);
        } catch (Throwable $e) {
            return $this->errorResponse('Failed to process inventory: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/workstations/{id}/telemetry (staff session, authorization check)
     */
    public function telemetry(Request $request, int|string $id): Response
    {
        if (function_exists('requireLogin')) {
            requireLogin();
        }

        $wsId = (int) $id;
        $ws = $this->workstationRepo->findById($wsId);
        if ($ws === null) {
            return $this->errorResponse("Workstation #{$wsId} not found.", 404);
        }

        $isAdmin = function_exists('isAdmin') ? isAdmin() : false;
        $userUid = function_exists('currentUserUid') ? currentUserUid() : null;
        $username = function_exists('currentUsername') ? currentUsername() : '';
        $fullName = (string) ($_SESSION['full_name'] ?? '');

        $labId = (string) ($ws['lab_id'] ?? '');
        if (!$isAdmin && !$this->labRepo->canUserAccessLab($labId, $userUid, false, $username, $fullName)) {
            return $this->errorResponse('Access denied to workstation telemetry.', 403);
        }

        try {
            $data = $this->telemetryService->getWorkstationTelemetry($wsId);
            return $this->successResponse($data);
        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * POST /workstations/{id}/drift/{driftId}/resolve (admin or assigned incharge, CSRF)
     */
    public function resolveDrift(Request $request, int|string $id, int|string $driftId): Response
    {
        if (function_exists('requireLogin')) {
            requireLogin();
        }

        $csrfToken = $request->input('csrf_token') ?? $request->header('X-CSRF-Token');
        if (!function_exists('verifyCsrfToken') || !verifyCsrfToken(is_string($csrfToken) ? $csrfToken : null)) {
            return $this->errorResponse('Invalid or missing CSRF token.', 419);
        }

        $wsId = (int) $id;
        $ws = $this->workstationRepo->findById($wsId);
        if ($ws === null) {
            return $this->errorResponse("Workstation #{$wsId} not found.", 404);
        }

        $isAdmin = function_exists('isAdmin') ? isAdmin() : false;
        $userUid = function_exists('currentUserUid') ? currentUserUid() : null;
        $username = function_exists('currentUsername') ? currentUsername() : '';
        $fullName = (string) ($_SESSION['full_name'] ?? '');

        $labId = (string) ($ws['lab_id'] ?? '');
        if (!$isAdmin && !$this->labRepo->canUserAccessLab($labId, $userUid, false, $username, $fullName)) {
            return $this->errorResponse('Access denied.', 403);
        }

        $action = (string) ($request->input('action') ?? 'Resolved');
        if (!in_array($action, ['Acknowledged', 'Resolved'], true)) {
            $action = 'Resolved';
        }

        try {
            $this->telemetryService->resolveDriftAlert((int) $driftId, $action, $userUid);

            if ($this->wantsJson($request)) {
                return $this->successResponse(['message' => "Drift alert marked as {$action}."]);
            }

            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['flash_message'] = [
                    'type' => 'success',
                    'message' => "Hardware drift alert successfully marked as {$action}.",
                ];
            }

            return Response::redirect("/workstations/{$wsId}");
        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * Check rate limiting for enrollment: 10 per minute per IP.
     */
    private function isEnrollRateLimited(string $ip): bool
    {
        $hash = md5($ip);
        $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'labtrack_enroll_' . $hash . '.json';
        $now = time();
        $cutoff = $now - 60;
        $timestamps = [];

        if (file_exists($file)) {
            $content = @file_get_contents($file);
            if ($content !== false) {
                $decoded = json_decode($content, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $t) {
                        if (is_int($t) && $t >= $cutoff) {
                            $timestamps[] = $t;
                        }
                    }
                }
            }
        }

        if (count($timestamps) >= 10) {
            return true;
        }

        $timestamps[] = $now;
        @file_put_contents($file, json_encode(array_values($timestamps)));
        return false;
    }

    private function getRawBody(Request $request): string
    {
        if (method_exists($request, 'getRawBody')) {
            $raw = $request->getRawBody();
            if (is_string($raw) && $raw !== '') {
                return $raw;
            }
        }

        if (method_exists($request, 'rawBody')) {
            $raw = $request->rawBody();
            if (is_string($raw) && $raw !== '') {
                return $raw;
            }
        }

        $body = @file_get_contents('php://input');
        return is_string($body) ? $body : '';
    }

    private function wantsJson(Request $request): bool
    {
        $accept = (string) ($request->header('Accept') ?? '');
        $xReq = (string) ($request->header('X-Requested-With') ?? '');
        return str_contains($accept, 'application/json') || strcasecmp($xReq, 'xmlhttprequest') === 0;
    }

    private function determineServerUrl(Request $request): string
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
        return "{$scheme}://{$host}";
    }

    private function successResponse(mixed $data = null, int $statusCode = 200): Response
    {
        $payload = [
            'success' => true,
            'timestamp' => gmdate('c'),
            'data' => $data,
            'error' => null,
        ];
        return Response::json($payload, $statusCode);
    }

    /**
     * @param array<string, mixed>|null $details
     */
    private function errorResponse(string $message, int $statusCode, ?array $details = null): Response
    {
        $payload = [
            'success' => false,
            'timestamp' => gmdate('c'),
            'data' => null,
            'error' => [
                'message' => $message,
                'details' => $details,
            ],
        ];
        return Response::json($payload, $statusCode);
    }
}
