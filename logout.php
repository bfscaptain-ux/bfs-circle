<?php
require_once __DIR__ . '/config.php';

// Update user status to Offline
if (isset($_SESSION['username']) && isset($db)) {
    try {
        $u_upd = $db->prepare("UPDATE users SET current_status = 'Offline' WHERE username = ?");
        $u_upd->execute([$_SESSION['username']]);
    } catch (Exception $e) {}
}

// Clear all session variables
$_SESSION = array();

// Expire session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        '/',
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy session
@session_unset();
@session_destroy();

// Send anti-caching HTTP headers
header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

header("Location: login.php");
exit;
