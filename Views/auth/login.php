<?php
/**
 * Views/auth/login.php
 * Expects: $error (?string) - 'invalid', 'inactive', or 'forbidden'
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
        /* Password field + show/hide toggle (see togglePasswordVisibility()
           below). Only this page's password input gets the extra right-side
           padding/positioning - the shared form input[type="password"] rule
           in style.css is left untouched so every other password field in
           the app (e.g. Add/Edit User) is unaffected. */
        .password-field {
            position: relative;
        }
        .password-field input[type="password"],
        .password-field input[type="text"] {
            padding-right: 42px;
        }
        .password-toggle {
            position: absolute;
            top: 0;
            right: 0;
            bottom: 18px;
            width: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: none;
            border: none;
            padding: 0;
            cursor: pointer;
            color: var(--text-muted);
        }
        .password-toggle:hover {
            color: var(--text-dark);
        }
        .password-toggle svg {
            width: 18px;
            height: 18px;
        }
        .password-toggle .icon-eye-off {
            display: none;
        }
        .password-toggle.is-visible .icon-eye {
            display: none;
        }
        .password-toggle.is-visible .icon-eye-off {
            display: block;
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
                <div class="alert alert-warning">Incorrect email or password.</div>
            <?php elseif ($error === 'inactive'): ?>
                <div class="alert alert-warning">Account is inactive.</div>
            <?php elseif ($error === 'forbidden'): ?>
                <div class="alert alert-warning">Please sign in to continue.</div>
            <?php endif; ?>

            <form method="POST" action="index.php?module=auth&action=login">
                <label for="email">Email <span class="required-asterisk">*</span></label>
                <input type="email" id="email" name="email" required autofocus>

                <label for="password">Password <span class="required-asterisk">*</span></label>
                <div class="password-field">
                    <input type="password" id="password" name="password" required>
                    <button type="button" class="password-toggle" onclick="togglePasswordVisibility()" aria-label="Show password" aria-pressed="false">
                        <svg class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.6 21.6 0 0 1 5.06-6.06M9.9 4.24A10.4 10.4 0 0 1 12 4c7 0 11 8 11 8a21.6 21.6 0 0 1-2.61 3.81M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                    </button>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">Sign In</button>
            </form>
        </div>
    </div>
    <script>
        function togglePasswordVisibility() {
            var input = document.getElementById('password');
            var btn = document.querySelector('.password-toggle');
            var showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            btn.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
            btn.setAttribute('aria-pressed', showing ? 'false' : 'true');
            btn.classList.toggle('is-visible', !showing);
        }
    </script>
</body>
</html>
