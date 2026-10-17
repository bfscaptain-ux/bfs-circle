<?php
/**
 * BFS Financial Services - Global Router
 * Routes authenticated requests to the Dashboard and guest requests to Login.
 */

require_once __DIR__ . '/config.php';

// Check active session
if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Route user directly to their dedicated role portal
$target_dashboard = get_role_dashboard_url($_SESSION['role'] ?? 'Staff', false);
header("Location: " . $target_dashboard);
exit;

