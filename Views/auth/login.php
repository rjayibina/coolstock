<?php
/**
 * Views/auth/login.php
 * Expects: $error (?string) - 'invalid' or 'forbidden'
 * Standalone page - deliberately doesn't pull in partials/header.php or
 * sidebar.php since there's no signed-in user yet to show a nav for.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - CoolStock</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <style>
        <?php
        // Inlined via filesystem path, same approach as partials/header.php,
        // so this page still looks right even opened before any other page.
        $cssPath = __DIR__ . '/../../assets/css/style.css';
        if (file_exists($cssPath)) {
            echo file_get_contents($cssPath);
        }
        ?>
        .login-shell {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--page-bg);
            padding: 24px;
        }
        .login-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 36px 34px;
            width: 100%;
            max-width: 380px;
        }
        .login-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            font-size: 17px;
            color: var(--text-dark);
            margin-bottom: 26px;
        }
        .login-brand-mark {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            background: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .login-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 4px;
        }
        .login-subtitle {
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 22px;
        }
    </style>
</head>
<body>
    <div class="login-shell">
        <div class="login-card">
            <div class="login-brand">
                <span class="login-brand-mark">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                </span>
                CoolStock
            </div>
            <div class="login-title">Sign in</div>
            <div class="login-subtitle">Mister Aircon inventory system</div>

            <?php if ($error === 'invalid'): ?>
                <div class="alert alert-warning">Incorrect email or password, or the account is inactive.</div>
            <?php elseif ($error === 'forbidden'): ?>
                <div class="alert alert-warning">Please sign in to continue.</div>
            <?php endif; ?>

            <form method="POST" action="index.php?module=auth&action=login">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required autofocus>

                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>

                <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">Sign In</button>
            </form>
        </div>
    </div>
</body>
</html>
