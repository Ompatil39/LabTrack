<?php

declare(strict_types=1);

namespace LabTrack\Controller;

use InvalidArgumentException;
use LabTrack\Http\Request;
use LabTrack\Http\Response;
use LabTrack\Service\LabService;
use LabTrack\Validation\LabRequest;
use LabTrack\View\View;

class LabController
{
    private LabService $labService;

    public function __construct(?LabService $labService = null)
    {
        $this->labService = $labService ?? new LabService();
    }

    public function index(Request $request): Response
    {
        if (function_exists('requireLogin')) {
            requireLogin();
        }

        $isAdmin = function_exists('isAdmin') ? isAdmin() : false;
        $userUid = function_exists('currentUserUid') ? currentUserUid() : null;
        $username = function_exists('currentUsername') ? currentUsername() : '';
        $fullName = (string) ($_SESSION['full_name'] ?? '');

        try {
            $labs = $this->labService->getLabsForUser($userUid, $isAdmin, $username, $fullName);
        } catch (\Throwable $e) {
            $labs = [];
        }

        $html = View::render('labs/index', [
            'title' => 'Computer Labs',
            'labs' => $labs,
            'isAdmin' => $isAdmin,
            'currentUser' => [
                'username' => $username,
                'full_name' => $fullName,
                'role' => function_exists('currentUserRole') ? currentUserRole() : '',
            ],
            'flash' => $this->getAndClearFlash(),
        ]);

        return Response::html($html);
    }

    public function show(Request $request, string $id): Response
    {
        if (function_exists('requireLogin')) {
            requireLogin();
        }

        $isAdmin = function_exists('isAdmin') ? isAdmin() : false;
        $userUid = function_exists('currentUserUid') ? currentUserUid() : null;
        $username = function_exists('currentUsername') ? currentUsername() : '';
        $fullName = (string) ($_SESSION['full_name'] ?? '');

        try {
            if (!$this->labService->canAccessLab($id, $userUid, $isAdmin, $username, $fullName)) {
                return new Response('403 Forbidden: Lab access denied.', 403, ['Content-Type' => 'text/plain; charset=UTF-8']);
            }

            $lab = $this->labService->getLabDetails($id, $userUid, $isAdmin, $username, $fullName);
        } catch (\Throwable $e) {
            return new Response('Error loading lab: ' . View::e($e->getMessage()), 500);
        }

        if ($lab === null) {
            return new Response('404 Not Found: Lab not found.', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $html = View::render('labs/show', [
            'title' => 'Lab Details - ' . ($lab['lab_name'] ?? $id),
            'lab' => $lab,
            'isAdmin' => $isAdmin,
            'flash' => $this->getAndClearFlash(),
        ]);

        return Response::html($html);
    }

    public function create(Request $request): Response
    {
        if (function_exists('requireLogin')) {
            requireLogin();
        }
        if (function_exists('requireRole')) {
            requireRole('Admin');
        }

        $incharges = [];
        try {
            $incharges = $this->labService->getAvailableIncharges();
        } catch (\Throwable) {
            // DB might be offline in static test
        }

        $html = View::render('labs/create', [
            'title' => 'Add New Lab',
            'incharges' => $incharges,
            'errors' => [],
            'old' => [],
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

        // Verify CSRF
        if (!$request->validateCsrf()) {
            return new Response('403 Forbidden: Invalid or missing CSRF token.', 403, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $validation = LabRequest::validate($request->post(), false);
        if (!$validation['valid']) {
            $incharges = [];
            try {
                $incharges = $this->labService->getAvailableIncharges();
            } catch (\Throwable) {
            }

            $html = View::render('labs/create', [
                'title' => 'Add New Lab',
                'incharges' => $incharges,
                'errors' => $validation['errors'],
                'old' => $request->post(),
            ]);

            return new Response($html, 422, ['Content-Type' => 'text/html; charset=UTF-8']);
        }

        try {
            $this->labService->createLab($validation['data']);
            $this->setFlash('success', "Lab '{$validation['data']['lab_name']}' created successfully.");
            return Response::redirect('/labs');
        } catch (InvalidArgumentException $e) {
            $incharges = [];
            try {
                $incharges = $this->labService->getAvailableIncharges();
            } catch (\Throwable) {
            }

            $errors = ['general' => $e->getMessage()];
            if (str_contains($e->getMessage(), 'already exists')) {
                $errors['lab_id'] = $e->getMessage();
            }

            $html = View::render('labs/create', [
                'title' => 'Add New Lab',
                'incharges' => $incharges,
                'errors' => $errors,
                'old' => $request->post(),
            ]);

            return new Response($html, 422, ['Content-Type' => 'text/html; charset=UTF-8']);
        } catch (\Throwable $e) {
            return new Response('Internal error creating lab: ' . View::e($e->getMessage()), 500);
        }
    }

    public function edit(Request $request, string $id): Response
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

        try {
            $lab = $this->labService->getLabDetails($id, $userUid, $isAdmin, $username, $fullName);
            $incharges = $this->labService->getAvailableIncharges();
        } catch (\Throwable $e) {
            return new Response('Error loading lab: ' . View::e($e->getMessage()), 500);
        }

        if ($lab === null) {
            return new Response('404 Not Found: Lab not found.', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $html = View::render('labs/edit', [
            'title' => 'Edit Lab - ' . ($lab['lab_name'] ?? $id),
            'lab' => $lab,
            'incharges' => $incharges,
            'errors' => [],
            'old' => $lab,
        ]);

        return Response::html($html);
    }

    public function update(Request $request, string $id): Response
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

        $validation = LabRequest::validate($request->post(), true);
        if (!$validation['valid']) {
            $incharges = [];
            try {
                $incharges = $this->labService->getAvailableIncharges();
            } catch (\Throwable) {
            }

            $lab = array_merge(['lab_id' => $id], $request->post());
            $html = View::render('labs/edit', [
                'title' => 'Edit Lab - ' . $id,
                'lab' => $lab,
                'incharges' => $incharges,
                'errors' => $validation['errors'],
                'old' => $request->post(),
            ]);

            return new Response($html, 422, ['Content-Type' => 'text/html; charset=UTF-8']);
        }

        try {
            $this->labService->updateLab($id, $validation['data']);
            $this->setFlash('success', 'Lab updated successfully.');
            return Response::redirect('/labs/' . urlencode($id));
        } catch (InvalidArgumentException $e) {
            $incharges = [];
            try {
                $incharges = $this->labService->getAvailableIncharges();
            } catch (\Throwable) {
            }

            $lab = array_merge(['lab_id' => $id], $request->post());
            $html = View::render('labs/edit', [
                'title' => 'Edit Lab - ' . $id,
                'lab' => $lab,
                'incharges' => $incharges,
                'errors' => ['general' => $e->getMessage()],
                'old' => $request->post(),
            ]);

            return new Response($html, 422, ['Content-Type' => 'text/html; charset=UTF-8']);
        } catch (\Throwable $e) {
            return new Response('Internal error updating lab: ' . View::e($e->getMessage()), 500);
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
