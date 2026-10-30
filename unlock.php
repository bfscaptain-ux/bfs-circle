<?php
require_once 'config.php';

$status = "error";
$msg = "";

try {
    // 1. Clear all rate limits & lockouts
    $db->exec("DELETE FROM login_rate_limits");
    
    // 2. Set Admin password to BFSaNuJ@070123
    $admin_pwd = 'BFSaNuJ@070123';
    $admin_hash = password_hash($admin_pwd, PASSWORD_DEFAULT);
    $stmt = $db->prepare("UPDATE users SET password_hash = ?, plain_password = ?, email = 'bfscaptain@gmail.com' WHERE LOWER(username) = 'admin' OR role = 'Admin'");
    $stmt->execute([$admin_hash, $admin_pwd]);
    
    // Fetch updated admin
    $admin_data = $db->query("SELECT id, username, email, role, plain_password FROM users WHERE LOWER(username) = 'admin' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    
    $status = "success";
    $msg = "Lockout cleared! Admin password locked to BFSaNuJ@070123";
} catch (Exception $e) {
    $status = "error";
    $msg = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Official BFS Circle Monochrome Favicon & Branding -->
    <link rel="icon" type="image/png" sizes="64x64" href="<?php echo isset($base_path) ? $base_path : ""; ?>favicon.png?v=4">
    <link rel="icon" type="image/x-icon" href="<?php echo isset($base_path) ? $base_path : ""; ?>favicon.ico?v=4">
    <link rel="shortcut icon" href="<?php echo isset($base_path) ? $base_path : ""; ?>favicon.ico?v=4">
    <link rel="apple-touch-icon" href="<?php echo isset($base_path) ? $base_path : ""; ?>favicon.png?v=4">
    <meta charset="UTF-8">
    <title>CRM Security Unlock & Password Sync</title>
    <meta http-equiv="refresh" content="3;url=login.php">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #0b0f19; color: #fff; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .card { background: #131c2e; padding: 36px 42px; border-radius: 20px; border: 1px solid #1e293b; text-align: center; max-width: 460px; box-shadow: 0 20px 40px rgba(0,0,0,0.6); }
        .icon { width: 56px; height: 56px; border-radius: 16px; background: rgba(16, 185, 129, 0.15); color: #10b981; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; font-size: 26px; }
        h2 { color: #10b981; margin: 0 0 10px 0; font-size: 22px; font-weight: 800; }
        p { color: #94a3b8; font-size: 14px; line-height: 1.6; margin: 0 0 16px 0; }
        .badge { background: #064e3b; color: #6ee7b7; padding: 8px 16px; border-radius: 10px; font-weight: 700; font-family: monospace; font-size: 14px; display: inline-block; margin: 4px; border: 1px solid #059669; }
        .btn { display: inline-block; background: #2563eb; color: #fff; padding: 12px 28px; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 14px; margin-top: 20px; transition: 0.2s; }
        .btn:hover { background: #1d4ed8; }
    </style>
</head>
<body>
<div class="card">
    <div class="icon">🔓</div>
    <h2>Security Lockout Cleared!</h2>
    <p>Aapka 26-minute ka lockout hat gaya hai aur Admin credentials sync ho gaye hain:</p>
    <div>
        <span class="badge">User: admin</span>
        <span class="badge">Pass: BFSaNuJ@070123</span>
    </div>
    <p style="color: #cbd5e1; font-size: 12.5px; margin-top: 14px;">
        💡 Login karne par 6-digit OTP aapke email <b>bfscaptain@gmail.com</b> par aayega.
    </p>
    <p style="color: #64748b; font-size: 12px; margin-bottom: 0;">3 seconds mein Login page par redirect ho raha hai...</p>
    <a href="login.php" class="btn">Go to Login Page Now</a>
</div>
</body>
</html>
