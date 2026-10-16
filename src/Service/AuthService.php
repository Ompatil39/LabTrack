<?php

declare(strict_types=1);

namespace LabTrack\Service;

use LabTrack\Repository\UserRepository;

class AuthService
{
    private UserRepository $userRepo;

    public function __construct(?UserRepository $userRepo = null)
    {
        $this->userRepo = $userRepo ?? new UserRepository();
    }

    /**
     * Attempt to authenticate a user.
     *
     * @param string $username
     * @param string $password
     * @param string $ip
     * @return array{success: bool, error: ?string, must_change_password: bool, user: ?array}
     */
    public function attemptLogin(string $username, string $password, string $ip = ''): array
    {
        $username = trim($username);
        $password = trim($password);

        if ($username === '' || $password === '') {
            return [
                'success' => false,
                'error' => 'Please enter both username and password.',
                'must_change_password' => false,
                'user' => null,
            ];
        }

        // 1. Check IP / account lockout
        $remainingSeconds = 0;
        if ($this->userRepo->isIpBlocked($username, $ip, $remainingSeconds)) {
            $mins = max(1, (int) ceil($remainingSeconds / 60));
            return [
                'success' => false,
                'error' => "Too many failed login attempts. Account is locked. Please try again in {$mins} minute(s).",
                'must_change_password' => false,
                'user' => null,
            ];
        }

        // 2. Look up user
        $user = $this->userRepo->findByUsername($username);
        if ($user === null || !password_verify($password, (string) $user['password'])) {
            $this->userRepo->recordFailedAttempt($username, $ip);
            AuditService::record('LOGIN_FAILED', 'user', $username, null, ['ip' => $ip]);
            return [
                'success' => false,
                'error' => 'Invalid username or password.',
                'must_change_password' => false,
                'user' => null,
            ];
        }

        // 3. Clear failed attempts on success
        $this->userRepo->clearFailedAttempts($username, $ip);

        // 4. Session initialization & fixation defense
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $uid = isset($user['uid']) && $user['uid'] !== null ? (int) $user['uid'] : (int) ($user['user_id'] ?? 0);
        $role = (string) ($user['user_type'] ?? 'Incharge');

        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = (string) $user['user_id'];
        $_SESSION['uid'] = $uid;
        $_SESSION['username'] = (string) $user['username'];
        $_SESSION['full_name'] = (string) ($user['full_name'] ?? '');
        $_SESSION['user_type'] = $role;
        $_SESSION['role'] = $role;
        $_SESSION['last_activity'] = time();

        AuditService::record('LOGIN', 'user', (string) $uid, null, ['username' => $username, 'role' => $role]);

        // 5. Must change password check
        $mustChange = isset($user['must_change_password']) && (int) $user['must_change_password'] === 1;
        if ($mustChange) {
            $_SESSION['force_password_change'] = true;
        } else {
            unset($_SESSION['force_password_change']);
        }

        return [
            'success' => true,
            'error' => null,
            'must_change_password' => $mustChange,
            'user' => $user,
        ];
    }

    /**
     * Terminate the user session.
     */
    public function logout(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly']
                );
            }
            session_destroy();
        }
    }

    /**
     * Generate secure math CAPTCHA using random_int() per Blueprint Risk R6.
     * Returns the equation string e.g. "7 + 4 = ?" and stores answer in session.
     */
    public function generateCaptcha(): string
    {
        $a = random_int(1, 9);
        $b = random_int(1, 9);
        $answer = $a + $b;

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['captcha_answer'] = (string) $answer;
        }

        return "{$a} + {$b} = ?";
    }

    /**
     * Verify submitted CAPTCHA.
     */
    public function verifyCaptcha(string $input): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE || !isset($_SESSION['captcha_answer'])) {
            return true; // If captcha was not generated/required for this session, pass
        }

        $expected = (string) $_SESSION['captcha_answer'];
        unset($_SESSION['captcha_answer']);

        return trim($input) === $expected;
    }
}
