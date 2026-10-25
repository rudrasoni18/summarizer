<?php
/**
 * DocuMind — logout.php (Session Destruction & Redirect)
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';

// Unset all session keys
$_SESSION = [];

// Destroy session cookie if present
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

// Destroy session data on server
session_destroy();

// Redirect back to login
header('Location: index.php?msg=logged_out');
exit;
