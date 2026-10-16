<?php

declare(strict_types=1);

namespace LabTrack\Controller;

use LabTrack\Http\Request;
use LabTrack\Http\Response;
use LabTrack\Repository\LabRepository;
use LabTrack\Repository\UserRepository;
use LabTrack\View\View;

class ProfileController
{
    private UserRepository $userRepo;
    private LabRepository $labRepo;

    public function __construct(
        ?UserRepository $userRepo = null,
        ?LabRepository $labRepo = null
    ) {
        $this->userRepo = $userRepo ?? new UserRepository();
        $this->labRepo = $labRepo ?? new LabRepository();
    }

    /**
     * GET /profile — requires login.
     */
    public function show(Request $request): Response
    {
        if (function_exists('requireLogin')) {
            requireLogin();
        }

        $uid = function_exists('currentUserUid') ? currentUserUid() : null;
        $username = function_exists('currentUsername') ? currentUsername() : '';

        $user = null;
        if ($uid !== null) {
            $user = $this->userRepo->findByUid($uid);
        }
        if ($user === null && $username !== '') {
            $user = $this->userRepo->findByUsername($username);
        }

        $forcePasswordChange = !empty($_SESSION['force_password_change'])
            || (isset($user['must_change_password']) && (int) $user['must_change_password'] === 1);

        $html = View::render('profile/show', [
            'title' => 'My Profile & Account Settings',
            'user' => $user ?? [],
            'forcePasswordChange' => $forcePasswordChange,
            'flash' => $this->getAndClearFlash(),
        ]);

        return Response::html($html);
    }

    /**
     * POST /profile — requires login, CSRF-protected.
     */
    public function update(Request $request): Response
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

        $uid = function_exists('currentUserUid') ? currentUserUid() : null;
        $username = function_exists('currentUsername') ? currentUsername() : '';
        $user = $uid !== null ? $this->userRepo->findByUid($uid) : $this->userRepo->findByUsername($username);

        if (!$user) {
            $this->setFlash('error', 'User record not found.');
            return Response::redirect('/profile');
        }

        $targetUid = isset($user['uid']) && $user['uid'] !== null ? (int) $user['uid'] : (int) ($user['user_id'] ?? 0);
        $post = $request->post();
        $action = $post['action'] ?? 'profile';

        // 1. Password change action
        if ($action === 'password') {
            $currentPassword = (string) ($post['current_password'] ?? '');
            $newPassword = (string) ($post['new_password'] ?? '');
            $confirmPassword = (string) ($post['confirm_password'] ?? '');

            if ($currentPassword === '' || $newPassword === '') {
                $this->setFlash('error', 'Please enter your current and new password.');
                return Response::redirect('/profile');
            }

            if (!password_verify($currentPassword, (string) $user['password'])) {
                $this->setFlash('error', 'Current password is incorrect.');
                return Response::redirect('/profile');
            }

            if (strlen($newPassword) < 6) {
                $this->setFlash('error', 'New password must be at least 6 characters long.');
                return Response::redirect('/profile');
            }

            if ($newPassword !== $confirmPassword) {
                $this->setFlash('error', 'New password and confirmation do not match.');
                return Response::redirect('/profile');
            }

            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $this->userRepo->updatePassword($targetUid, $newHash, true);
            unset($_SESSION['force_password_change']);

            $this->setFlash('success', 'Your password has been changed successfully.');
            return Response::redirect('/profile');
        }

        // 2. Profile update action
        $fullName = trim((string) ($post['full_name'] ?? ''));
        $email = strtolower(trim((string) ($post['email'] ?? '')));
        $contact = trim((string) ($post['contact_number'] ?? ''));
        $org = trim((string) ($post['org_name'] ?? ''));

        if ($fullName === '') {
            $this->setFlash('error', 'Full Name is required.');
            return Response::redirect('/profile');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->setFlash('error', 'A valid email address is required.');
            return Response::redirect('/profile');
        }

        if (!$this->userRepo->isEmailUnique($email, $targetUid)) {
            $this->setFlash('error', 'This email address is already registered by another user.');
            return Response::redirect('/profile');
        }

        $this->userRepo->updateProfile($targetUid, [
            'full_name' => $fullName,
            'email' => $email,
            'contact_number' => $contact,
            'org_name' => $org,
        ]);

        $_SESSION['full_name'] = $fullName;
        $this->setFlash('success', 'Your profile details have been updated successfully.');

        return Response::redirect('/profile');
    }

    /**
     * GET /admin/users & POST /admin/users — requires Admin role.
     */
    public function manageUsers(Request $request): Response
    {
        if (function_exists('requireLogin')) {
            requireLogin();
        }

        if (function_exists('requireRole')) {
            requireRole('Admin');
        }

        if ($request->isPost()) {
            if (!$request->validateCsrf()) {
                return new Response(
                    '403 Forbidden: Invalid or missing CSRF token.',
                    403,
                    ['Content-Type' => 'text/plain; charset=UTF-8']
                );
            }

            $post = $request->post();
            $username = trim((string) ($post['username'] ?? ''));
            $fullName = trim((string) ($post['full_name'] ?? ''));
            $email = strtolower(trim((string) ($post['email'] ?? '')));
            $password = (string) ($post['password'] ?? '');
            $contact = trim((string) ($post['contact_number'] ?? ''));
            $org = trim((string) ($post['org_name'] ?? ''));
            $selectedLabs = is_array($post['labs'] ?? null) ? $post['labs'] : [];

            if ($username === '' || $fullName === '' || $email === '' || $password === '') {
                $this->setFlash('error', 'Username, full name, email, and password are required.');
                return Response::redirect('/admin/users');
            }

            if (!preg_match('/^[a-zA-Z0-9_\.]{3,30}$/', $username)) {
                $this->setFlash('error', 'Username must be 3–30 alphanumeric characters.');
                return Response::redirect('/admin/users');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->setFlash('error', 'Invalid email format.');
                return Response::redirect('/admin/users');
            }

            if (strlen($password) < 6) {
                $this->setFlash('error', 'Password must be at least 6 characters.');
                return Response::redirect('/admin/users');
            }

            if (!$this->userRepo->isUsernameUnique($username)) {
                $this->setFlash('error', "Username '{$username}' is already taken.");
                return Response::redirect('/admin/users');
            }

            if (!$this->userRepo->isEmailUnique($email)) {
                $this->setFlash('error', "Email '{$email}' is already in use.");
                return Response::redirect('/admin/users');
            }

            try {
                $newUid = $this->userRepo->createIncharge([
                    'username' => $username,
                    'full_name' => $fullName,
                    'email' => $email,
                    'password' => $password,
                    'contact_number' => $contact,
                    'org_name' => $org,
                    'must_change_password' => 1,
                ], $selectedLabs);

                $this->setFlash('success', "Lab Incharge '{$username}' created successfully with temporary password.");
            } catch (\Throwable $e) {
                $this->setFlash('error', 'Error creating user: ' . $e->getMessage());
            }

            return Response::redirect('/admin/users');
        }

        // GET request — list users
        $users = [];
        $labs = [];
        try {
            $users = $this->userRepo->listAllWithLabs();
            $labs = $this->labRepo->findAll(null, true);
        } catch (\Throwable) {
        }

        $html = View::render('admin/users', [
            'title' => 'Staff & Incharge Management',
            'users' => $users,
            'labs' => $labs,
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
