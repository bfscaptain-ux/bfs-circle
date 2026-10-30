<?php
define('IS_SUBFOLDER', true);
require_once __DIR__ . '/../../config.php';
require_role(['Builder', 'Builder / Real Estate']);


$builder_id = $_SESSION['user_id'];
$builder_username = $_SESSION['username'];

// Fetch builder profile
$stmt_header = $db->prepare("SELECT name, full_name, profile_photo FROM users WHERE id = ?");
$stmt_header->execute([$builder_id]);
$u_header = $stmt_header->fetch(PDO::FETCH_ASSOC);

$builder_name = !empty($u_header['name']) ? $u_header['name'] : (!empty($u_header['full_name']) ? $u_header['full_name'] : $builder_username);
$builder_photo = $u_header['profile_photo'] ?? '';

if (empty($builder_photo)) {
    $stmt_ref_photo = $db->prepare("SELECT profile_photo, full_name FROM referrals WHERE user_id = ? OR mobile = ? LIMIT 1");
    $stmt_ref_photo->execute([$builder_id, $builder_username]);
    if ($rf = $stmt_ref_photo->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($rf['profile_photo'])) $builder_photo = $rf['profile_photo'];
        if (empty($builder_name) && !empty($rf['full_name'])) $builder_name = $rf['full_name'];
    }
}

$has_custom_photo = false;
$photo_display_path = '';
if (!empty($builder_photo)) {
    $clean_path = ltrim($builder_photo, './');
    if (file_exists(__DIR__ . '/../../' . $clean_path)) {
        $has_custom_photo = true;
        $photo_display_path = '../' . $clean_path;
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
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
    <title>Builder Dashboard - BFS Financial Services</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            /* Premium Slate & Green Theme */
            --primary: #1e293b; /* Slate 800 */
            --primary-hover: #0f172a; /* Slate 900 */
            --accent: #10b981; /* Emerald 500 / Green */
            --accent-hover: #059669; /* Emerald 600 */
            --bg-main: #f8fafc;
            --text-primary: #0f172a;
            --text-muted: #64748b;
            --card-bg: #ffffff;
            --border: #e2e8f0;
            --nav-height: 65px;
            --top-bar-height: 60px;
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
            padding-top: var(--top-bar-height);
            padding-bottom: calc(var(--nav-height) + 20px);
            min-height: 100vh;
        }

        /* Top App Bar */
        .app-bar {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: var(--top-bar-height);
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            z-index: 100;
            box-shadow: 0 4px 20px rgba(15, 23, 42, 0.15);
            border-bottom-left-radius: 20px;
            border-bottom-right-radius: 20px;
        }

        .app-title {
            font-family: 'Outfit';
            font-weight: 800;
            font-size: 20px;
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
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: var(--accent);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-family: 'Outfit';
            font-size: 14px;
            text-decoration: none;
            overflow: hidden;
            position: relative;
        }

        /* Main Container */
        .container {
            padding: 20px;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Cards */
        .card {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            margin-bottom: 16px;
            border: 1px solid var(--border);
        }

        /* Bottom Navigation */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            height: var(--nav-height);
            background: white;
            display: flex;
            justify-content: space-around;
            align-items: center;
            box-shadow: 0 -4px 20px rgba(0,0,0,0.05);
            z-index: 100;
            padding: 0 10px;
        }

        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
            text-decoration: none;
            font-size: 11px;
            font-weight: 600;
            gap: 4px;
            width: 60px;
            transition: color 0.2s;
        }

        .nav-item.active {
            color: var(--accent);
        }

        .nav-item i {
            width: 22px;
            height: 22px;
            transition: transform 0.2s;
        }

        .nav-item.active i {
            transform: translateY(-2px);
            stroke-width: 2.5;
        }

        /* Floating Action Button for Adding Leads */
        .fab-container {
            position: relative;
            top: -20px;
        }

        .fab {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, var(--accent), var(--accent-hover));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
            text-decoration: none;
            transition: transform 0.2s;
        }

        .fab:active {
            transform: scale(0.95);
        }
        
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-pending { background: #fef9c3; color: #a16207; }
        .badge-approved { background: #dcfce7; color: #166534; }
        .badge-rejected { background: #fee2e2; color: #b91c1c; }
    </style>
</head>
<body>

<div class="app-bar">
    <div class="app-title">Builder<span class="app-title-dot"></span></div>
    <div class="app-user">
        <a href="../login.php?logout=1" style="color:white; text-decoration:none;"><i data-lucide="log-out" style="width:20px;"></i></a>
        <a href="profile.php" class="user-avatar" title="View Profile">
            <?php if ($has_custom_photo): ?>
                <img src="<?php echo htmlspecialchars($photo_display_path); ?>" alt="<?php echo htmlspecialchars($builder_name); ?>" style="width:100%; height:100%; object-fit:cover; display:block;">
            <?php else: ?>
                <?php echo strtoupper(substr($builder_name ?? 'B', 0, 1)); ?>
            <?php endif; ?>
        </a>
    </div>
</div>

<div class="container">
