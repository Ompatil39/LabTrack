<?php
/** @var string $title */
/** @var string $content */
use LabTrack\View\View;
$pageTitle = isset($title) ? View::e($title) . ' - LabTrack' : 'LabTrack - Computer Lab Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
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
            --danger: #ef4444;
            --success: #10b981;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; }
        body {
            background: linear-gradient(135deg, #f0f4f8 0%, #e2e8f0 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            color: var(--text);
        }
        .auth-container {
            width: 100%;
            max-width: 440px;
        }
        .auth-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08), 0 8px 10px -6px rgba(0, 0, 0, 0.04);
            padding: 2.25rem;
        }
        .auth-brand {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.65rem;
            margin-bottom: 1.75rem;
            text-decoration: none;
            color: var(--primary);
            font-size: 1.5rem;
            font-weight: 700;
        }
        .form-group {
            margin-bottom: 1.25rem;
        }
        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 0.4rem;
            color: #334155;
        }
        .form-control {
            width: 100%;
            padding: 0.65rem 0.85rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 0.95rem;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }
        .btn-submit {
            width: 100%;
            background: var(--primary);
            color: #fff;
            border: none;
            padding: 0.75rem 1rem;
            border-radius: 6px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.15s;
        }
        .btn-submit:hover {
            background: var(--primary-hover);
        }
        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
            padding: 0.75rem 1rem;
            border-radius: 6px;
            margin-bottom: 1.25rem;
            font-size: 0.875rem;
        }
        .auth-footer {
            margin-top: 1.5rem;
            text-align: center;
            font-size: 0.825rem;
            color: var(--muted);
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <a href="/" class="auth-brand">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                <line x1="8" y1="21" x2="16" y2="21"></line>
                <line x1="12" y1="17" x2="12" y2="21"></line>
            </svg>
            LabTrack
        </a>
        <div class="auth-card">
            <?= $content ?>
        </div>
        <div class="auth-footer">
            Institutional Computer Lab &amp; Asset Management &bull; Secure Portal
        </div>
    </div>
</body>
</html>
