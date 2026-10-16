<?php
/** @var string $title */
/** @var string $content */
/** @var array|null $flash */
use LabTrack\View\View;
$pageTitle = isset($title) ? View::e($title) . ' - LabTrack' : 'LabTrack Computer Lab Management';
$role = function_exists('currentUserRole') ? currentUserRole() : '';
$username = function_exists('currentUsername') ? currentUsername() : '';
$isAdmin = function_exists('isAdmin') ? isAdmin() : false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#2563eb">
    <link rel="stylesheet" href="/public/css/style.css" onerror="this.onerror=null;this.href='../public/css/style.css';">
    <style>
        :root {
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --bg: #f8fafc;
            --surface: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --border: #e2e8f0;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; }
        body { background-color: var(--bg); color: var(--text); min-height: 100vh; display: flex; flex-direction: column; }
        .navbar { background: var(--surface); border-bottom: 1px solid var(--border); padding: 0.875rem 1.5rem; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 50; }
        .nav-brand { font-size: 1.25rem; font-weight: 700; color: var(--primary); text-decoration: none; display: flex; align-items: center; gap: 0.5rem; }
        .nav-links { display: flex; gap: 1.25rem; align-items: center; }
        .nav-link { color: var(--muted); text-decoration: none; font-weight: 500; font-size: 0.95rem; padding: 0.35rem 0.65rem; border-radius: 6px; transition: all 0.15s; }
        .nav-link:hover, .nav-link.active { color: var(--primary); background: #eff6ff; }
        .nav-user { display: flex; align-items: center; gap: 0.75rem; font-size: 0.875rem; }
        .role-badge { padding: 0.2rem 0.6rem; border-radius: 9999px; font-weight: 600; font-size: 0.75rem; text-transform: uppercase; }
        .role-admin { background: #dbeafe; color: #1e40af; }
        .role-incharge { background: #dcfce7; color: #166534; }
        .btn-logout { background: transparent; border: 1px solid var(--border); padding: 0.35rem 0.75rem; border-radius: 6px; cursor: pointer; font-size: 0.85rem; color: var(--muted); }
        .btn-logout:hover { background: #fee2e2; border-color: #fca5a5; color: var(--danger); }
        .main-container { flex: 1; max-width: 1200px; width: 100%; margin: 1.5rem auto; padding: 0 1.25rem; }
        .flash-alert { padding: 0.875rem 1.25rem; border-radius: 8px; margin-bottom: 1.5rem; font-weight: 500; }
        .flash-success { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
        .flash-error, .flash-danger { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 1.5rem; }
        .btn { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.5rem 1rem; border-radius: 6px; font-weight: 500; font-size: 0.9rem; text-decoration: none; cursor: pointer; border: none; transition: background 0.15s; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-hover); }
        .btn-secondary { background: #f1f5f9; color: var(--text); border: 1px solid var(--border); }
        .btn-secondary:hover { background: #e2e8f0; }
        .table { width: 100%; border-collapse: collapse; text-align: left; }
        .table th { background: #f8fafc; padding: 0.75rem 1rem; font-size: 0.8rem; text-transform: uppercase; color: var(--muted); border-bottom: 1px solid var(--border); }
        .table td { padding: 0.875rem 1rem; border-bottom: 1px solid var(--border); font-size: 0.9rem; }
        .status-badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
        .status-active { background: #ecfdf5; color: #047857; }
        .status-inactive { background: #f1f5f9; color: #64748b; }
        .status-in-repair { background: #fffbeb; color: #b45309; }
        .form-group { margin-bottom: 1.25rem; }
        .form-label { display: block; font-weight: 600; font-size: 0.875rem; margin-bottom: 0.35rem; color: #334155; }
        .form-control { width: 100%; padding: 0.6rem 0.85rem; border: 1px solid var(--border); border-radius: 6px; font-size: 0.95rem; }
        .form-control:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(37,99,235,0.15); }
        .form-error { color: var(--danger); font-size: 0.8rem; margin-top: 0.25rem; }
        footer { border-top: 1px solid var(--border); padding: 1.25rem; text-align: center; color: var(--muted); font-size: 0.85rem; background: var(--surface); }
    </style>
</head>
<body>
    <header class="navbar">
        <a href="/labs" class="nav-brand">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
            LabTrack
        </a>
        <?php 
            $currentUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        ?>
        <nav class="nav-links">
            <a href="/labs" class="nav-link <?= str_starts_with($currentUri, '/labs') ? 'active' : '' ?>">Labs</a>
            <a href="/workstations" class="nav-link <?= str_starts_with($currentUri, '/workstations') ? 'active' : '' ?>">Workstations</a>
            <a href="/inventory" class="nav-link <?= (str_starts_with($currentUri, '/inventory') || str_starts_with($currentUri, '/assets')) ? 'active' : '' ?>">Inventory</a>
            <a href="/grievances" class="nav-link" onerror="this.href='html%20pages/grievance.php'">Grievances</a>
            <?php if ($isAdmin): ?>
                <a href="/admin/users" class="nav-link" onerror="this.href='html%20pages/inchargeManage.php'">Staff</a>
            <?php endif; ?>
        </nav>
        <div class="nav-user">
            <?php if ($username !== ''): ?>
                <span><?= View::e($username) ?></span>
                <span class="role-badge <?= $isAdmin ? 'role-admin' : 'role-incharge' ?>"><?= View::e($role ?: 'User') ?></span>
                <form action="/logout" method="POST" style="display:inline;">
                    <?= View::csrf() ?>
                    <button type="submit" class="btn-logout">Logout</button>
                </form>
            <?php else: ?>
                <a href="/login" class="btn btn-primary" style="padding:0.35rem 0.85rem;font-size:0.85rem;">Login</a>
            <?php endif; ?>
        </div>
    </header>

    <main class="main-container">
        <?php if (!empty($flash)): ?>
            <div class="flash-alert flash-<?= View::e($flash['type'] ?? 'info') ?>">
                <?= View::e($flash['message'] ?? '') ?>
            </div>
        <?php endif; ?>

        <?= $content ?>
    </main>

    <footer>
        LabTrack &mdash; Institutional Computer Lab Asset &amp; Grievance Management System
    </footer>
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js').catch(() => {});
            });
        }
    </script>
</body>
</html>
