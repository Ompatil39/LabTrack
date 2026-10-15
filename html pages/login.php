<?php
require_once __DIR__ . '/../includes/security.php';
startSecureSession();

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: index.php');
    exit;
}

include 'db.php';
$error = '';
$clientIp = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforceCsrfProtection();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = trim((string) ($_POST['password'] ?? ''));

    if ($username === '' || $password === '') {
        $error = 'Please fill in both fields.';
    } else {
        $remaining = 0;
        if (isLoginLocked($username, $clientIp, $remaining)) {
            $error = 'Too many failed login attempts. Try again in ' . max(1, (int) ceil($remaining / 60)) . ' minute(s).';
        } else {
            $hasUidCol = (bool) db_fetch_one($conn, "SHOW COLUMNS FROM users LIKE 'uid'");
            $loginSql = $hasUidCol
                ? 'SELECT user_id, username, password, user_type, full_name, uid FROM users WHERE username = ? LIMIT 1'
                : 'SELECT user_id, username, password, user_type, full_name FROM users WHERE username = ? LIMIT 1';

            $user = db_fetch_one(
                $conn,
                $loginSql,
                's',
                [$username]
            );

            if ($user && password_verify($password, (string) $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (string) $user['user_id'];
                $_SESSION['uid'] = isset($user['uid']) && $user['uid'] !== null ? (int) $user['uid'] : null;
                $_SESSION['full_name'] = (string) ($user['full_name'] ?? '');
                $_SESSION['username'] = (string) $user['username'];
                $_SESSION['user_type'] = (string) $user['user_type'];
                $_SESSION['logged_in'] = true;
                $_SESSION['last_activity'] = time();

                clearFailedLogin($username, $clientIp);

                $mustChangePassword = false;
                $columnCheck = db_fetch_one($conn, "SHOW COLUMNS FROM users LIKE 'must_change_password'");
                if ($columnCheck) {
                    $mustChangeRow = db_fetch_one(
                        $conn,
                        'SELECT must_change_password FROM users WHERE user_id = ? LIMIT 1',
                        's',
                        [(string) $user['user_id']]
                    );
                    $mustChangePassword = isset($mustChangeRow['must_change_password']) && (int) $mustChangeRow['must_change_password'] === 1;
                }

                if ($mustChangePassword) {
                    $_SESSION['force_password_change'] = true;
                    header('Location: profileManage.php?force_password_change=1');
                    exit;
                }

                header('Location: index.php');
                exit;
            }

            recordFailedLogin($username, $clientIp);
            $error = 'Invalid username or password.';
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="../public/images/logo.svg" />
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LabTrack - Login</title>
    <link rel="stylesheet" href="../public/css/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .error-message {
            color: #e74c3c;
            margin-bottom: 1rem;
            text-align: center;
            font-size: 0.875rem;
        }
    </style>
</head>

<body>
    <div class="container-login">
        <div class="left-panel">
            <div class="logo-login">
                <i class="fa-brands fa-watchman-monitoring"></i>
                <div class="logo-login-text">LabTrack</div>
            </div>
            <h1 class="welcome-text">Welcome Back</h1>
            <p class="description">Access your lab management dashboard to monitor device status, handle grievance requests, and resolve student grievances.</p>
        </div>

        <div class="right-panel">
            <div class="login-form">
                <h2>Sign in to your account</h2>

                <?php if ($error): ?>
                    <div class="error-message"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <?= csrfInputField() ?>
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" placeholder="Enter your username" required>
                        <i class="fas fa-user icon"></i>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" placeholder="Enter your password" required>
                        <i class="fas fa-eye toggle-password" style="position: absolute; right: 12px; top: 50%; transform: translateY(-25%); color: #7f8c8d; cursor: pointer;"></i>
                    </div>

                    <button type="submit" class="sign-in-btn">Sign In</button>
                </form>

                <div class="divider">
                    <div class="divider-line"></div>
                </div>

                <div class="student-grievance">
                    <button class="grievance-button" onclick="window.location.href = 'submitGrievance.php'; return false;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                        </svg>
                        Student Grievance
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.querySelector('.toggle-password').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);

            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    </script>
</body>

</html>
