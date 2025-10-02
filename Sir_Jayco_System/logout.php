<?php
// logout.php — destroys the session and redirects to login
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Unset all session variables
$_SESSION = [];

// If there's a session cookie, delete it
if (ini_get("session.use_cookies")) {
  $params = session_get_cookie_params();
  setcookie(session_name(), '', time() - 42000,
    $params['path'], $params['domain'],
    $params['secure'], $params['httponly']
  );
}

// Finally, destroy the session
session_destroy();

// Prevent caching of the landing page after logout
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

// Redirect to login
header('Location: index.php');
exit;
