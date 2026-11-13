<?php
/**
 * Dedicated Staff & Kitchen Admin Login Portal
 * Rate-Limited with Cryptographic CSRF Protection & Password Hashing
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';

// If already authenticated, redirect straight to kitchen dashboard
if (isAdminLoggedIn()) {
    header('Location: /kitchen.php');
    exit;
}

$clientIp = getClientIp();
$rateLimit = checkLoginRateLimit($clientIp);
$errorMessage = '';
$successMessage = '';

if (isset($_GET['logged_out'])) {
    $successMessage = 'You have been securely logged out.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Check Rate Limit Lockout
    if (!$rateLimit['allowed']) {
        $minutes = ceil($rateLimit['remaining_seconds'] / 60);
        $errorMessage = "Too many failed login attempts. Access is locked. Please wait {$minutes} minute(s) before trying again.";
    } else {
        // 2. Validate CSRF Token
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!verifyCsrfToken($csrfToken)) {
            $errorMessage = 'Invalid or expired security token. Please refresh the page and try again.';
        } else {
            // 3. Process Login Credentials
            $username = trim((string)($_POST['username'] ?? ''));
            $password = (string)($_POST['password'] ?? '');

            if ($username === '' || $password === '') {
                $errorMessage = 'Please enter both username and password.';
            } else {
                $pdo = getDBConnection();
                $stmt = $pdo->prepare('SELECT id, username, password_hash, full_name, role FROM admins WHERE username = ? LIMIT 1');
                $stmt->execute([$username]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password_hash'])) {
                    // Successful login: reset failed attempts counter & elevate session
                    resetFailedLogins($clientIp);
                    loginAdmin(
                        (int)$user['id'],
                        $user['username'],
                        $user['full_name'],
                        $user['role']
                    );

                    header('Location: /kitchen.php');
                    exit;
                } else {
                    // Record failed attempt for rate limiting
                    recordFailedLogin($clientIp);
                    // Re-check rate limit status after recording failure
                    $rateLimit = checkLoginRateLimit($clientIp);

                    if (!$rateLimit['allowed']) {
                        $errorMessage = 'Too many failed login attempts. Access locked for 15 minutes.';
                    } else {
                        $remainingAttempts = 5 - $rateLimit['attempts'];
                        $errorMessage = "Invalid username or password. ({$remainingAttempts} attempt(s) remaining before temporary lockout).";
                    }
                }
            }
        }
    }
}

$csrfToken = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff & Kitchen Portal Login | Campus Bistro</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .login-body {
            background-color: var(--bg-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .login-card {
            background: var(--bg-card);
            width: 100%;
            max-width: 440px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-color);
            padding: 2.5rem 2rem;
            animation: fadeIn 0.3s ease-in-out;
        }
        .login-brand {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-brand .brand-badge {
            display: inline-block;
            background: #FEF3C7;
            color: #92400E;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 0.35rem 0.85rem;
            border-radius: 9999px;
            margin-bottom: 0.75rem;
        }
        .login-brand h1 {
            font-size: 1.6rem;
            color: var(--text-primary);
            margin: 0 0 0.4rem 0;
            font-weight: 800;
        }
        .login-brand p {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin: 0;
        }
        .form-group {
            margin-bottom: 1.25rem;
        }
        .form-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.4rem;
        }
        .form-input-wrap {
            position: relative;
        }
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 0.95rem;
            color: var(--text-primary);
            background: #FFF;
            transition: all 0.2s ease;
            box-sizing: border-box;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--color-orange);
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.15);
        }
        .password-toggle-btn {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            padding: 0.25rem;
        }
        .password-toggle-btn:hover {
            color: var(--text-primary);
        }
        .btn-login {
            width: 100%;
            padding: 0.85rem;
            background: var(--color-orange);
            color: #FFF;
            border: none;
            border-radius: var(--radius-md);
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 0.5rem;
            box-shadow: 0 2px 4px rgba(249, 115, 22, 0.2);
        }
        .btn-login:hover:not(:disabled) {
            background: var(--color-orange-hover);
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(249, 115, 22, 0.3);
        }
        .btn-login:disabled {
            background: #94A3B8;
            cursor: not-allowed;
            transform: none;
        }
        .alert {
            padding: 0.85rem 1rem;
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            margin-bottom: 1.25rem;
            line-height: 1.4;
        }
        .alert:empty {
            display: none !important;
        }
        .alert-danger {
            background: #FEE2E2;
            color: #991B1B;
            border: 1px solid #FCA5A5;
        }
        .alert-success {
            background: #D1FAE5;
            color: #065F46;
            border: 1px solid #6EE7B7;
        }
        .alert-warning {
            background: #FEF3C7;
            color: #92400E;
            border: 1px solid #FCD34D;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="login-body">

    <div class="login-card">
        <div class="login-brand">
            <span class="brand-badge">Restricted Access</span>
            <h1>🍳 Kitchen & Staff Portal</h1>
            <p>Campus Canteen Order Dispatch & Inventory</p>
        </div>

        <?php if ($successMessage): ?>
            <div class="alert alert-success">
                <?= e($successMessage) ?>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="alert alert-danger">
                <?= e($errorMessage) ?>
            </div>
        <?php endif; ?>

        <?php if (!$rateLimit['allowed']): ?>
            <div class="alert alert-warning">
                <strong>Access Temporarily Blocked:</strong> Rate limit exceeded from IP <?= e($clientIp) ?>. Try again in <?= ceil($rateLimit['remaining_seconds'] / 60) ?> minute(s).
            </div>
        <?php endif; ?>

        <form method="POST" action="admin-login.php" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

            <div class="form-group">
                <label for="username">Staff Username</label>
                <input type="text" 
                       id="username" 
                       name="username" 
                       class="form-control" 
                       placeholder="e.g. admin" 
                       required 
                       autofocus
                       <?= !$rateLimit['allowed'] ? 'disabled' : '' ?>> 
            </div>

            <div class="form-group">
                <label for="password">Security Password</label>
                <div class="form-input-wrap">
                    <input type="password" 
                           id="password" 
                           name="password" 
                           class="form-control" 
                           placeholder="••••••••••••" 
                           required
                           <?= !$rateLimit['allowed'] ? 'disabled' : '' ?>>
                    <button type="button" class="password-toggle-btn" id="togglePassword">Show</button>
                </div>
            </div>

            <button type="submit" class="btn-login" <?= !$rateLimit['allowed'] ? 'disabled' : '' ?>>
                Sign In to Kitchen Dashboard
            </button>
        </form>
    </div>

    <script>
        // Password toggle helper
        const toggleBtn = document.getElementById('togglePassword');
        const passInput = document.getElementById('password');
        if (toggleBtn && passInput) {
            toggleBtn.addEventListener('click', () => {
                const isPassword = passInput.getAttribute('type') === 'password';
                passInput.setAttribute('type', isPassword ? 'text' : 'password');
                toggleBtn.textContent = isPassword ? 'Hide' : 'Show';
            });
        }
    </script>
</body>
</html>

