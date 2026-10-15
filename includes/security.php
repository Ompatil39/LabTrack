<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (!function_exists('startSecureSession')) {
    function startSecureSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            enforceSessionIdleTimeout();
            return;
        }

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
        enforceSessionIdleTimeout();

        if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }
}

if (!function_exists('enforceSessionIdleTimeout')) {
    function enforceSessionIdleTimeout(int $timeoutSeconds = 1800): void
    {
        $now = time();
        if (isset($_SESSION['last_activity']) && ($now - (int) $_SESSION['last_activity']) > $timeoutSeconds) {
            $_SESSION = [];
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }
            header('Location: login.php?timeout=1');
            exit;
        }

        $_SESSION['last_activity'] = $now;
    }
}

if (!function_exists('requireLogin')) {
    function requireLogin(bool $json = false): void
    {
        startSecureSession();

        if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
            if ($json) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Authentication required.']);
            } else {
                header('Location: login.php');
            }
            exit;
        }
    }
}

if (!function_exists('currentUserRole')) {
    function currentUserRole(): string
    {
        return (string) ($_SESSION['user_type'] ?? $_SESSION['role'] ?? '');
    }
}

if (!function_exists('isAdmin')) {
    function isAdmin(): bool
    {
        return strcasecmp(currentUserRole(), 'Admin') === 0;
    }
}

if (!function_exists('requireRole')) {
    function requireRole(string|array $roles, bool $json = false): void
    {
        $allowedRoles = is_array($roles) ? $roles : [$roles];
        $currentRole = currentUserRole();

        foreach ($allowedRoles as $allowedRole) {
            if (strcasecmp($currentRole, (string) $allowedRole) === 0) {
                return;
            }
        }

        http_response_code(403);
        if ($json) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Forbidden']);
        } else {
            echo '403 Forbidden';
        }
        exit;
    }
}

if (!function_exists('currentUsername')) {
    function currentUsername(): string
    {
        return (string) ($_SESSION['username'] ?? '');
    }
}

if (!function_exists('inchargeDisplayName')) {
    function inchargeDisplayName(): string
    {
        $username = currentUsername();
        return ucfirst(strtolower($username));
    }
}

if (!function_exists('restrictLabSql')) {
    function restrictLabSql(string $labColumn = 'lab_id', string $labAlias = ''): array
    {
        if (isAdmin()) {
            return ['clause' => '', 'types' => '', 'params' => []];
        }

        $column = $labAlias !== '' ? $labAlias . '.' . $labColumn : $labColumn;
        $sql = " {$column} IN (SELECT lab_id FROM labs WHERE lab_incharge = ? OR lab_incharge = ? OR lab_incharge = ?)";

        return [
            'clause' => $sql,
            'types' => 'sss',
            'params' => [inchargeDisplayName(), currentUsername(), (string) ($_SESSION['full_name'] ?? '')],
        ];
    }
}

if (!function_exists('canAccessLab')) {
    function canAccessLab(mysqli $conn, string $labId): bool
    {
        if (isAdmin()) {
            return true;
        }

        $username = currentUsername();
        $displayName = inchargeDisplayName();
        $fullName = (string) ($_SESSION['full_name'] ?? '');

        $row = db_fetch_one(
            $conn,
            'SELECT 1 FROM labs WHERE lab_id = ? AND (lab_incharge = ? OR lab_incharge = ? OR lab_incharge = ?) LIMIT 1',
            'ssss',
            [$labId, $username, $displayName, $fullName]
        );

        return $row !== null;
    }
}

if (!function_exists('requireLabAccess')) {
    function requireLabAccess(mysqli $conn, string $labId, bool $json = false): void
    {
        if (!canAccessLab($conn, $labId)) {
            http_response_code(403);
            if ($json) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Forbidden: Lab access denied.']);
            } else {
                echo '403 Forbidden: Lab access denied.';
            }
            exit;
        }
    }
}

if (!function_exists('generateCsrfToken')) {
    function generateCsrfToken(): string
    {
        startSecureSession();
        if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrfInputField')) {
    function csrfInputField(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('verifyCsrfToken')) {
    function verifyCsrfToken(?string $token): bool
    {
        if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || $token === null) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('enforceCsrfProtection')) {
    function enforceCsrfProtection(bool $json = false): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!verifyCsrfToken(is_string($token) ? $token : null)) {
            http_response_code(419);
            if ($json) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
            } else {
                echo 'Invalid CSRF token.';
            }
            exit;
        }
    }
}

if (!function_exists('injectCsrfFieldToForms')) {
    function injectCsrfFieldToForms(string $buffer): string
    {
        return preg_replace_callback(
            '/<form\b([^>]*)>/i',
            static function (array $matches): string {
                $attributes = $matches[1] ?? '';
                if (!preg_match('/method\s*=\s*["\']?post["\']?/i', $attributes)) {
                    return $matches[0];
                }

                if (stripos($matches[0], 'csrf_token') !== false) {
                    return $matches[0];
                }

                return $matches[0] . "\n" . csrfInputField();
            },
            $buffer
        ) ?? $buffer;
    }
}

if (!function_exists('startCsrfOutputBuffer')) {
    function startCsrfOutputBuffer(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        ob_start('injectCsrfFieldToForms');
    }
}

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('db_stmt')) {
    function db_stmt(mysqli $conn, string $sql, string $types = '', array $params = []): ?mysqli_stmt
    {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            app_logger()->error('db_prepare_failed', ['error' => $conn->error, 'sql' => $sql]);
            return null;
        }

        if ($types !== '' && !empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        if (!$stmt->execute()) {
            app_logger()->error('db_execute_failed', ['error' => $stmt->error, 'sql' => $sql]);
            $stmt->close();
            return null;
        }

        return $stmt;
    }
}

if (!function_exists('db_fetch_all')) {
    function db_fetch_all(mysqli $conn, string $sql, string $types = '', array $params = []): array
    {
        $stmt = db_stmt($conn, $sql, $types, $params);
        if (!$stmt) {
            return [];
        }

        $result = $stmt->get_result();
        $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();

        return $rows;
    }
}

if (!function_exists('db_fetch_one')) {
    function db_fetch_one(mysqli $conn, string $sql, string $types = '', array $params = []): ?array
    {
        $stmt = db_stmt($conn, $sql, $types, $params);
        if (!$stmt) {
            return null;
        }

        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        return $row ?: null;
    }
}

if (!function_exists('db_execute')) {
    function db_execute(mysqli $conn, string $sql, string $types = '', array $params = []): bool
    {
        $stmt = db_stmt($conn, $sql, $types, $params);
        if (!$stmt) {
            return false;
        }

        $stmt->close();
        return true;
    }
}

if (!function_exists('login_attempt_state_path')) {
    function login_attempt_state_path(): string
    {
        return sys_get_temp_dir() . '/labtrack_login_attempts.json';
    }
}

if (!function_exists('read_login_attempt_state')) {
    function read_login_attempt_state(): array
    {
        $path = login_attempt_state_path();
        if (!file_exists($path)) {
            return [];
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('write_login_attempt_state')) {
    function write_login_attempt_state(array $state): void
    {
        file_put_contents(login_attempt_state_path(), json_encode($state));
    }
}

if (!function_exists('login_attempt_key')) {
    function login_attempt_key(string $username, string $ip): string
    {
        return hash('sha256', strtolower(trim($username)) . '|' . $ip);
    }
}

if (!function_exists('isLoginLocked')) {
    function isLoginLocked(string $username, string $ip = '', int &$remainingSeconds = 0): bool
    {
        $ip = $ip !== '' ? $ip : (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $state = read_login_attempt_state();
        $key = login_attempt_key($username, $ip);
        $entry = $state[$key] ?? null;
        if (!is_array($entry)) {
            return false;
        }

        $lockedUntil = (int) ($entry['locked_until'] ?? 0);
        $remainingSeconds = max(0, $lockedUntil - time());

        return $remainingSeconds > 0;
    }
}

if (!function_exists('recordFailedLogin')) {
    function recordFailedLogin(string $username, string $ip = ''): void
    {
        $ip = $ip !== '' ? $ip : (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $state = read_login_attempt_state();
        $key = login_attempt_key($username, $ip);
        $entry = $state[$key] ?? ['count' => 0, 'first_attempt' => time(), 'locked_until' => 0];

        $now = time();
        if (($now - (int) ($entry['first_attempt'] ?? $now)) > 900) {
            $entry['count'] = 0;
            $entry['first_attempt'] = $now;
        }

        $entry['count'] = ((int) ($entry['count'] ?? 0)) + 1;
        if ($entry['count'] >= 5) {
            $entry['locked_until'] = $now + 900;
            $entry['count'] = 0;
            $entry['first_attempt'] = $now;
        }

        $state[$key] = $entry;
        write_login_attempt_state($state);
    }
}

if (!function_exists('clearFailedLogin')) {
    function clearFailedLogin(string $username, string $ip = ''): void
    {
        $ip = $ip !== '' ? $ip : (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $state = read_login_attempt_state();
        $key = login_attempt_key($username, $ip);
        unset($state[$key]);
        write_login_attempt_state($state);
    }
}
