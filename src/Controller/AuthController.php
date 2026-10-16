<?php

declare(strict_types=1);

namespace LabTrack\Controller;

use LabTrack\Http\Request;
use LabTrack\Http\Response;
use LabTrack\Service\AuthService;
use LabTrack\View\View;

class AuthController
{
    private AuthService $authService;

    public function __construct(?AuthService $authService = null)
    {
        $this->authService = $authService ?? new AuthService();
    }

    /**
     * GET /login
     */
    public function showLogin(Request $request): Response
    {
        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['logged_in'])) {
            return Response::redirect('/dashboard');
        }

        $captcha = $this->authService->generateCaptcha();

        $html = View::render('auth/login', [
            'title' => 'Sign In',
            'error' => null,
            'oldUsername' => '',
            'captcha' => $captcha,
        ], 'layout/auth');

        return Response::html($html);
    }

    /**
     * POST /login
     */
    public function login(Request $request): Response
    {
        if (!$request->validateCsrf()) {
            return new Response(
                '403 Forbidden: Invalid or missing CSRF token.',
                403,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        }

        $post = $request->post();
        $username = trim((string) ($post['username'] ?? ''));
        $password = (string) ($post['password'] ?? '');
        $captchaInput = trim((string) ($post['captcha'] ?? ''));
        $clientIp = $request->getClientIp();

        // Check captcha if provided
        if ($captchaInput !== '' && !$this->authService->verifyCaptcha($captchaInput)) {
            $newCaptcha = $this->authService->generateCaptcha();
            $html = View::render('auth/login', [
                'title' => 'Sign In',
                'error' => 'Incorrect security verification code. Please try again.',
                'oldUsername' => $username,
                'captcha' => $newCaptcha,
            ], 'layout/auth');
            return Response::html($html, 422);
        }

        $result = $this->authService->attemptLogin($username, $password, $clientIp);

        if (!$result['success']) {
            $newCaptcha = $this->authService->generateCaptcha();
            $html = View::render('auth/login', [
                'title' => 'Sign In',
                'error' => $result['error'] ?? 'Invalid username or password.',
                'oldUsername' => $username,
                'captcha' => $newCaptcha,
            ], 'layout/auth');

            return Response::html($html, 401);
        }

        if ($result['must_change_password']) {
            return Response::redirect('/profile?force_password_change=1');
        }

        return Response::redirect('/dashboard');
    }

    /**
     * POST /logout
     */
    public function logout(Request $request): Response
    {
        if (!$request->validateCsrf()) {
            return new Response(
                '403 Forbidden: Invalid or missing CSRF token.',
                403,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        }

        $this->authService->logout();
        return Response::redirect('/login');
    }

    /**
     * GET /auth/captcha — JSON endpoint for refreshing captcha
     */
    public function captcha(Request $request): Response
    {
        $question = $this->authService->generateCaptcha();
        return Response::json(['success' => true, 'captcha' => $question]);
    }
}
