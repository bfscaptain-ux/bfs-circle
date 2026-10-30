<?php
define('IS_SUBFOLDER', true);
require_once __DIR__ . '/../../config.php';
require_role(['Agent', 'Individual Agent']);


$agent_id = $_SESSION['user_id'];
$agent_username = $_SESSION['username'];

// Fetch agent details and profile_photo
$stmt_header = $db->prepare("SELECT name, full_name, profile_photo FROM users WHERE id = ?");
$stmt_header->execute([$agent_id]);
$u_header = $stmt_header->fetch(PDO::FETCH_ASSOC);

$agent_name = !empty($u_header['name']) ? $u_header['name'] : (!empty($u_header['full_name']) ? $u_header['full_name'] : $agent_username);
$agent_photo = $u_header['profile_photo'] ?? '';

if (empty($agent_photo)) {
    $stmt_ref_photo = $db->prepare("SELECT profile_photo, full_name FROM referrals WHERE user_id = ? OR mobile = ? LIMIT 1");
    $stmt_ref_photo->execute([$agent_id, $agent_username]);
    if ($rf = $stmt_ref_photo->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($rf['profile_photo'])) $agent_photo = $rf['profile_photo'];
        if (empty($agent_name) && !empty($rf['full_name'])) $agent_name = $rf['full_name'];
    }
}

$has_custom_photo = false;
$photo_display_path = '';
if (!empty($agent_photo)) {
    $clean_path = ltrim($agent_photo, './');
    if (file_exists(__DIR__ . '/../../' . $clean_path)) {
        $has_custom_photo = true;
        $photo_display_path = '../' . $clean_path;
    }
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Agent Dashboard - BFS Financial Services</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@500;600;700&display=swap" rel="stylesheet">
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --primary: #059669; /* Emerald Green */
            --primary-hover: #047857;
            --accent: #f97316; /* Vibrant Orange */
            --accent-hover: #ea580c;
            --bg-main: #f4f7f6;
            --bg-card: #ffffff;
            --text-primary: #0f172a;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --nav-height: 65px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            background-color: var(--bg-main);
            color: var(--text-primary);
            padding-bottom: calc(var(--nav-height) + 20px);
            overflow-x: hidden;
        }

        /* Top App Bar - Premium Dark Green */
        .app-bar {
            background: linear-gradient(135deg, #064e3b, #065f46);
            color: white;
            padding: 20px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 10px 25px rgba(6, 78, 59, 0.15);
            border-bottom-left-radius: 30px;
            border-bottom-right-radius: 30px;
            margin-bottom: 24px;
        }

        .app-title {
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 24px;
            letter-spacing: -0.5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .app-title-dot {
            width: 8px;
            height: 8px;
            background-color: var(--accent);
            border-radius: 50%;
            display: inline-block;
        }

        .app-user {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .user-avatar {
            width: 42px;
            height: 42px;
            background: linear-gradient(135deg, var(--accent), var(--accent-hover));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-family: 'Outfit', sans-serif;
            border: 2px solid rgba(255,255,255,0.2);
            color: white;
            text-decoration: none;
            box-shadow: 0 4px 10px rgba(249, 115, 22, 0.3);
            font-size: 18px;
            overflow: hidden;
            position: relative;
        }
        
        .user-avatar:active {
            transform: scale(0.95);
        }

        /* Container */
        .container {
            padding: 0 20px 20px 20px;
        }

        /* Bottom Navigation */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            height: var(--nav-height);
            background: var(--bg-card);
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: space-around;
            align-items: center;
            z-index: 100;
            box-shadow: 0 -10px 30px rgba(0,0,0,0.04);
            padding-bottom: env(safe-area-inset-bottom);
        }

        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
            text-decoration: none;
            gap: 6px;
            width: 64px;
            transition: all 0.2s;
        }

        .nav-item i {
            width: 22px;
            height: 22px;
            transition: all 0.2s;
        }

        .nav-item span {
            font-size: 11px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .nav-item.active {
            color: var(--primary);
        }

        .nav-item.active i {
            stroke-width: 2.5;
            color: var(--primary);
            transform: translateY(-2px);
        }

        /* Floating Action Button for Add Lead */
        .fab-wrapper {
            position: relative;
            top: -28px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(5, 150, 105, 0.4); border-radius: 50%; }
            70% { box-shadow: 0 0 0 15px rgba(5, 150, 105, 0); border-radius: 50%; }
            100% { box-shadow: 0 0 0 0 rgba(5, 150, 105, 0); border-radius: 50%; }
        }

        .fab {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary), var(--primary-hover));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            box-shadow: 0 8px 25px rgba(5, 150, 105, 0.35);
            text-decoration: none;
            transition: transform 0.2s;
            border: 4px solid var(--bg-main);
        }

        .fab i {
            width: 28px;
            height: 28px;
        }

        .fab:active {
            transform: scale(0.92);
        }

        /* Card Styles */
        .card {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.03);
            margin-bottom: 16px;
            border: 1px solid rgba(0,0,0,0.02);
            transition: transform 0.2s;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 8px;
        }

        input[type="text"], input[type="tel"], input[type="email"], input[type="number"], input[type="password"], select, textarea {
            width: 100%;
            padding: 14px 16px;
            border: 1.5px solid var(--border);
            border-radius: 12px;
            font-size: 15px;
            background: var(--bg-main);
            color: var(--text-primary);
            transition: all 0.2s;
            font-weight: 500;
            box-sizing: border-box;
        }

        select {
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23059669' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            background-size: 16px;
            padding-right: 40px !important;
            cursor: pointer;
        }

        option {
            padding: 12px;
            font-size: 14px;
            font-weight: 600;
            background: #ffffff;
            color: #0f172a;
        }

        input:focus, select:focus {
            outline: none;
            border-color: var(--primary);
            background: var(--bg-card);
            box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.1);
        }

        .btn {
            display: block;
            width: 100%;
            padding: 16px;
            border: none;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--accent), var(--accent-hover));
            color: white;
            font-size: 16px;
            font-weight: 700;
            text-align: center;
            cursor: pointer;
            transition: opacity 0.2s;
            box-shadow: 0 8px 20px rgba(249, 115, 22, 0.25);
            letter-spacing: 0.5px;
        }

        .btn:active {
            opacity: 0.9;
            transform: scale(0.98);
        }
        
        .badge {
            padding: 6px 10px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }
        .badge-pending { background: #fff7ed; color: #ea580c; border: 1px solid #ffedd5; }
        .badge-approved { background: #ecfdf5; color: #059669; border: 1px solid #d1fae5; }
        .badge-rejected { background: #fef2f2; color: #dc2626; border: 1px solid #fee2e2; }
    </style>
</head>
<body>

<div class="app-bar">
    <div class="app-title">BFS Financial Services</div>
    <div class="app-user">
        <a href="../login.php?logout=1" style="color:white; text-decoration:none;"><i data-lucide="log-out" style="width:20px;"></i></a>
        <a href="profile.php" class="user-avatar" title="View Profile">
            <?php if ($has_custom_photo): ?>
                <img src="<?php echo htmlspecialchars($photo_display_path); ?>" alt="<?php echo htmlspecialchars($agent_name); ?>" style="width:100%; height:100%; object-fit:cover; display:block;">
            <?php else: ?>
                <?php echo strtoupper(substr($agent_name ?? 'A', 0, 1)); ?>
            <?php endif; ?>
        </a>
    </div>
</div>

<div class="container">
