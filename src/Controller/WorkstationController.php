<?php

declare(strict_types=1);

namespace LabTrack\Controller;

use InvalidArgumentException;
use LabTrack\Http\Request;
use LabTrack\Http\Response;
use LabTrack\Repository\LabRepository;
use LabTrack\Service\WorkstationAppService;
use LabTrack\Validation\WorkstationRequest;
use LabTrack\View\View;

class WorkstationController
{
    private WorkstationAppService $wsService;
    private LabRepository $labRepository;

    public function __construct(
        ?WorkstationAppService $wsService = null,
        ?LabRepository $labRepository = null
    ) {
        $this->wsService = $wsService ?? new WorkstationAppService();
        $this->labRepository = $labRepository ?? new LabRepository();
    }

    public function index(Request $request, ?string $lab = null): Response
    {
        if (function_exists('requireLogin')) {
            requireLogin();
        }

        $isAdmin = function_exists('isAdmin') ? isAdmin() : false;
        $userUid = function_exists('currentUserUid') ? currentUserUid() : null;
        $username = function_exists('currentUsername') ? currentUsername() : '';
        $fullName = (string) ($_SESSION['full_name'] ?? '');

        $selectedLab = $lab ?? (string) $request->query('lab_id', '');
        $labs = [];

        try {
            $labs = $this->labRepository->findAll($userUid, $isAdmin, $username, $fullName);
            $workstations = $this->wsService->getWorkstations(
                $selectedLab !== '' ? $selectedLab : null,
                $userUid,
                $isAdmin,
                $username,
                $fullName
            );
        } catch (\Throwable $e) {
            $workstations = [];
        }

        $html = View::render('workstations/index', [
            'title' => 'Workstations',
            'workstations' => $workstations,
            'labs' => $labs,
            'selectedLab' => $selectedLab,
            'isAdmin' => $isAdmin,
            'flash' => $this->getAndClearFlash(),
        ]);

        return Response::html($html);
    }

    public function show(Request $request, int|string $id): Response
    {
        if (function_exists('requireLogin')) {
            requireLogin();
        }

        $isAdmin = function_exists('isAdmin') ? isAdmin() : false;
        $userUid = function_exists('currentUserUid') ? currentUserUid() : null;
        $username = function_exists('currentUsername') ? currentUsername() : '';
        $fullName = (string) ($_SESSION['full_name'] ?? '');

        $wsId = (int) $id;
        try {
            $data = $this->wsService->getWorkstationDetails($wsId, $userUid, $isAdmin, $username, $fullName);
        } catch (\Throwable $e) {
            return new Response('403 Forbidden: ' . View::e($e->getMessage()), 403, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        if ($data === null) {
            return new Response('404 Not Found: Workstation not found.', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $html = View::render('workstations/show', [
            'title' => 'Workstation ' . ($data['workstation']['seat_code'] ?? "#{$wsId}"),
            'workstation' => $data['workstation'],
            'currentParts' => $data['current_parts'] ?? [],
            'history' => $data['history'] ?? [],
            'grievances' => $data['grievances'] ?? [],
            'availableSpares' => $data['available_spares'] ?? [],
            'isAdmin' => $isAdmin,
            'flash' => $this->getAndClearFlash(),
        ]);

        return Response::html($html);
    }

    public function create(Request $request, ?string $lab = null): Response
    {
        if (function_exists('requireLogin')) {
            requireLogin();
        }
        if (function_exists('requireRole')) {
            requireRole('Admin');
        }

        $isAdmin = function_exists('isAdmin') ? isAdmin() : true;
        $userUid = function_exists('currentUserUid') ? currentUserUid() : null;
        $username = function_exists('currentUsername') ? currentUsername() : '';
        $fullName = (string) ($_SESSION['full_name'] ?? '');

        $labs = $this->labRepository->findAll($userUid, $isAdmin, $username, $fullName);
        $selectedLab = $lab ?? (string) $request->query('lab_id', '');

        $html = View::render('workstations/create', [
            'title' => 'Add Workstation',
            'labs' => $labs,
            'selectedLab' => $selectedLab,
            'errors' => [],
            'old' => ['lab_id' => $selectedLab],
        ]);

        return Response::html($html);
    }

    public function store(Request $request): Response
    {
        if (function_exists('requireLogin')) {
            requireLogin();
        }
        if (function_exists('requireRole')) {
            requireRole('Admin');
        }

        if (!$request->validateCsrf()) {
            return new Response('403 Forbidden: Invalid or missing CSRF token.', 403, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $validation = WorkstationRequest::validateCreate($request->post());
        if (!$validation['valid']) {
            $labs = $this->labRepository->findAll(null, true);
            $html = View::render('workstations/create', [
                'title' => 'Add Workstation',
                'labs' => $labs,
                'selectedLab' => $request->post('lab_id', ''),
                'errors' => $validation['errors'],
                'old' => $request->post(),
            ]);

            return new Response($html, 422, ['Content-Type' => 'text/html; charset=UTF-8']);
        }

        try {
            $wsData = [
                'lab_id' => $validation['data']['lab_id'],
                'seat_code' => $validation['data']['seat_code'],
                'hostname' => $validation['data']['hostname'],
                'status' => $validation['data']['status'],
            ];

            $result = $this->wsService->createWorkstationWithParts($wsData, $validation['data']['parts']);
            $wsId = (int) ($result['workstation_id'] ?? 0);

            $this->setFlash('success', "Workstation '{$wsData['seat_code']}' and components created successfully.");
            return Response::redirect('/workstations/' . $wsId);
        } catch (InvalidArgumentException $e) {
            $labs = $this->labRepository->findAll(null, true);
            $html = View::render('workstations/create', [
                'title' => 'Add Workstation',
                'labs' => $labs,
                'selectedLab' => $request->post('lab_id', ''),
                'errors' => ['general' => $e->getMessage()],
                'old' => $request->post(),
            ]);

            return new Response($html, 422, ['Content-Type' => 'text/html; charset=UTF-8']);
        } catch (\Throwable $e) {
            return new Response('Internal error creating workstation: ' . View::e($e->getMessage()), 500);
        }
    }

    public function edit(Request $request, int|string $id): Response
    {
        if (function_exists('requireLogin')) {
            requireLogin();
        }
        if (function_exists('requireRole')) {
            requireRole('Admin');
        }

        $wsId = (int) $id;
        $ws = $this->wsService->getWorkstationDetails($wsId, null, true, 'admin');
        if ($ws === null) {
            return new Response('404 Not Found: Workstation not found.', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $html = View::render('workstations/edit', [
            'title' => 'Edit Workstation ' . ($ws['workstation']['seat_code'] ?? "#{$wsId}"),
            'workstation' => $ws['workstation'],
            'errors' => [],
            'old' => $ws['workstation'],
        ]);

        return Response::html($html);
    }

    public function update(Request $request, int|string $id): Response
    {
        if (function_exists('requireLogin')) {
            requireLogin();
        }
        if (function_exists('requireRole')) {
            requireRole('Admin');
        }

        if (!$request->validateCsrf()) {
            return new Response('403 Forbidden: Invalid or missing CSRF token.', 403, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $wsId = (int) $id;
        $validation = WorkstationRequest::validateUpdate($request->post());
        if (!$validation['valid']) {
            $ws = $this->wsService->getWorkstationDetails($wsId, null, true, 'admin');
            $html = View::render('workstations/edit', [
                'title' => 'Edit Workstation #' . $wsId,
                'workstation' => array_merge($ws['workstation'] ?? [], $request->post()),
                'errors' => $validation['errors'],
                'old' => $request->post(),
            ]);

            return new Response($html, 422, ['Content-Type' => 'text/html; charset=UTF-8']);
        }

        try {
            $this->wsService->updateWorkstation($wsId, $validation['data']);
            $this->setFlash('success', 'Workstation updated successfully.');
            return Response::redirect('/workstations/' . $wsId);
        } catch (\Throwable $e) {
            return new Response('Internal error updating workstation: ' . View::e($e->getMessage()), 500);
        }
    }

    public function swap(Request $request, int|string $id): Response
    {
        if (function_exists('requireLogin')) {
            requireLogin();
        }

        $isJson = $request->header('Accept') === 'application/json'
            || $request->header('X-Requested-With') === 'XMLHttpRequest';

        if (!$request->validateCsrf()) {
            if ($isJson) {
                return Response::json(['success' => false, 'message' => 'Invalid or missing CSRF token.'], 403);
            }
            return new Response('403 Forbidden: Invalid or missing CSRF token.', 403, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $wsId = (int) $id;
        $validation = WorkstationRequest::validateSwap($request->post());
        if (!$validation['valid']) {
            $firstError = reset($validation['errors']) ?: 'Invalid swap request data.';
            if ($isJson) {
                return Response::json(['success' => false, 'message' => $firstError, 'errors' => $validation['errors']], 422);
            }
            $this->setFlash('danger', $firstError);
            return Response::redirect('/workstations/' . $wsId);
        }

        $isAdmin = function_exists('isAdmin') ? isAdmin() : false;
        $userUid = function_exists('currentUserUid') ? currentUserUid() : null;
        $username = function_exists('currentUsername') ? currentUsername() : '';
        $fullName = (string) ($_SESSION['full_name'] ?? '');

        try {
            $this->wsService->swapPart(
                $wsId,
                $validation['data']['old_asset_id'],
                $validation['data']['new_asset_id'],
                $validation['data']['reason'],
                $validation['data']['old_status'],
                $userUid,
                $isAdmin,
                $username,
                $fullName
            );

            if ($isJson) {
                return Response::json(['success' => true, 'message' => 'Component swapped successfully.']);
            }

            $this->setFlash('success', 'Component swapped successfully.');
            return Response::redirect('/workstations/' . $wsId);
        } catch (\Throwable $e) {
            if ($isJson) {
                return Response::json(['success' => false, 'message' => 'Swap failed: ' . $e->getMessage()], 422);
            }
            $this->setFlash('danger', 'Swap failed: ' . $e->getMessage());
            return Response::redirect('/workstations/' . $wsId);
        }
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
