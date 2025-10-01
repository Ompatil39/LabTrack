<?php
session_start();
include 'db.php';
$error = '';

$password = "admin123";
$hashed_password = password_hash($password, PASSWORD_BCRYPT);
// echo $hashed_password;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($username) && !empty($password)) {

        $stmt = $conn->prepare("SELECT user_id, username, password, user_type FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password'])) {

                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_type'] = $user['user_type'];
                $_SESSION['logged_in'] = true;

                header("Location: index.php");
                exit();
            } else {
                $error = "Invalid username or password.";
            }
        } else {
            $error = "Invalid username or password.";
        }

        $stmt->close();
    } else {
        $error = "Please fill in both fields.";
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
                    <div class="error-message"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" placeholder="Enter your username" required>
                        <i class="fas fa-user icon"></i>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" placeholder="Enter your password" required>
                        <!-- <i class="fas fa-lock icon"></i> Changed to lock icon -->
                        <i class="fas fa-eye toggle-password" style="position: absolute; right: 12px; top: 50%; transform: translateY(-25%); color: #7f8c8d; cursor: pointer;"></i>
                    </div>

                    <div class="remember-forgot">
                        <div class="forgot">
                            <a href="#">Forgot password?</a>
                        </div>
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