<?php
/**
 * Secure Admin Logout
 * Destroys session, unsets cookies, and redirects to login portal.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';

logoutAdmin();

header('Location: /admin-login.php?logged_out=1');
exit;

