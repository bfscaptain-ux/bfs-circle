<?php
// Shared header for CA Portal
define('IS_SUBFOLDER', true);
require_once __DIR__ . '/../../config.php';
require_role(['CA', 'CA/CS', 'Chartered Accountant (CA)']);

$ca_id = $_SESSION['user_id'];
$ca_username = $_SESSION['username'];

$stmt_ca_h = $db->prepare("SELECT name, full_name, profile_photo FROM users WHERE id = ?");
$stmt_ca_h->execute([$ca_id]);
$u_ca = $stmt_ca_h->fetch(PDO::FETCH_ASSOC);

$ca_name = !empty($u_ca['name']) ? $u_ca['name'] : (!empty($u_ca['full_name']) ? $u_ca['full_name'] : $ca_username);
$ca_photo = $u_ca['profile_photo'] ?? '';

if (empty($ca_photo)) {
    $stmt_ref_photo = $db->prepare("SELECT profile_photo, full_name FROM referrals WHERE user_id = ? OR mobile = ? LIMIT 1");
    $stmt_ref_photo->execute([$ca_id, $ca_username]);
    if ($rf = $stmt_ref_photo->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($rf['profile_photo'])) $ca_photo = $rf['profile_photo'];
        if (empty($ca_name) && !empty($rf['full_name'])) $ca_name = $rf['full_name'];
    }
}

$has_ca_photo = false;
$ca_photo_path = '';
if (!empty($ca_photo)) {
    $clean_path = ltrim($ca_photo, './');
    if (file_exists(__DIR__ . '/../../' . $clean_path)) {
        $has_ca_photo = true;
        $ca_photo_path = '../' . $clean_path;
    }
}

if (!isset($page_title)) $page_title = 'CA Premium Portal';
if (!isset($active_page)) $active_page = 'index';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="manifest" href="../manifest.json">
    <meta name="theme-color" content="#4318FF">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('../sw.js').then(() => {
                console.log('Service Worker Registered');
            });
        }
        
        function vibrateAction() {
            if (navigator.vibrate) navigator.vibrate(50);
        }
    </script>
    <style>
        :root {
            --primary: #0F2C59; /* Deep Navy Blue */
            --accent: #F97316; /* Bright Professional Orange */
            --accent-light: #FFEDD5;
            --primary-light: #E2E8F0;
            --secondary: #334155;
            --bg: #F8FAFC;
            --surface: #FFFFFF;
            --text-main: #0F172A;
            --text-muted: #64748B;
            --danger: #EF4444;
            --success: #10B981;
            --warning: #F59E0B;
            --border: #E2E8F0;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background-color: var(--bg); color: var(--text-main); -webkit-tap-highlight-color: transparent; }

        .app-container { display: flex; min-height: 100vh; }
        
        .sidebar {
            width: 280px;
            background: var(--surface);
            display: flex; flex-direction: column;
            position: fixed; top: 0; bottom: 0; left: 0; z-index: 100;
            border-right: 1px solid var(--border);
        }

        .sidebar-header {
            padding: 32px 30px; font-size: 24px; font-weight: 800;
            color: var(--primary); display: flex; align-items: center; gap: 12px;
        }
        .sidebar-header span { color: var(--accent); }

        .nav-links { padding: 10px 20px; flex: 1; display: flex; flex-direction: column; gap: 4px; }
        .nav-link {
            display: flex; align-items: center; gap: 14px;
            padding: 12px 16px; color: var(--text-muted); text-decoration: none;
            font-weight: 600; border-radius: 8px; transition: all 0.2s ease;
        }
        .nav-link:hover { color: var(--primary); background: var(--bg); }
        .nav-link.active { background: var(--primary); color: white; }

        .main-content { flex: 1; margin-left: 280px; padding: 40px; padding-bottom: 100px; }

        .top-header {
            display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px;
        }
        .greeting { font-size: 28px; font-weight: 800; color: var(--primary); letter-spacing: -0.5px; }
        .greeting span { color: var(--text-muted); font-size: 15px; display: block; margin-top: 4px; font-weight: 500; }

        .header-actions { display: flex; align-items: center; gap: 16px; }
        .icon-btn {
            width: 40px; height: 40px; border-radius: 50%; background: var(--surface);
            display: flex; align-items: center; justify-content: center;
            color: var(--text-muted); border: 1px solid var(--border); text-decoration: none;
            transition: all 0.2s; position: relative;
        }
        .icon-btn:hover { color: var(--primary); border-color: var(--primary); }
        .icon-btn.logout:hover { color: var(--danger); border-color: var(--danger); }
        .icon-btn .badge {
            position: absolute; top: 8px; right: 10px; width: 8px; height: 8px;
            border-radius: 50%; background: var(--accent);
        }

        .user-profile {
            display: flex; align-items: center; gap: 12px; background: var(--surface);
            padding: 4px 12px 4px 4px; border-radius: 30px; border: 1px solid var(--border);
        }
        .avatar {
            width: 34px; height: 34px; background: var(--primary);
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            color: white; font-weight: 700; font-size: 14px;
            overflow: hidden; position: relative;
        }
        .user-name { font-weight: 600; font-size: 14px; color: var(--text-main); }

        /* Shared Components */
        .hero-card {
            background: var(--primary);
            border-radius: 16px;
            padding: 32px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
        }
        .hero-content h2 { font-size: 14px; font-weight: 500; color: #cbd5e1; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
        .hero-content .amount { font-size: 42px; font-weight: 800; letter-spacing: -1px; color: white; }
        .hero-action { z-index: 10; }
        .btn-white {
            background: var(--accent); color: white;
            padding: 12px 24px; border-radius: 8px;
            font-weight: 600; text-decoration: none;
            display: inline-flex; align-items: center; gap: 8px;
            transition: background 0.2s; border: none;
        }
        .btn-white:hover { background: #EA580C; }

        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 32px; }
        .stat-card {
            background: var(--surface); padding: 20px; border-radius: 12px;
            border: 1px solid var(--border); display: flex; align-items: center; gap: 16px;
        }
        .stat-icon {
            width: 48px; height: 48px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
        }
        .stat-icon svg { width: 24px; height: 24px; }
        .stat-info .title { font-size: 13px; color: var(--text-muted); font-weight: 600; margin-bottom: 2px; }
        .stat-info .value { font-size: 24px; font-weight: 800; color: var(--primary); }

        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
        .section-header h3 { font-size: 18px; font-weight: 700; color: var(--primary); }
        .view-all { color: var(--accent); font-weight: 600; text-decoration: none; font-size: 14px; }

        .leads-list {
            background: var(--surface); border-radius: 12px; padding: 0 20px;
            border: 1px solid var(--border);
        }
        .lead-item {
            display: flex; justify-content: space-between; align-items: center;
            padding: 16px 0; border-bottom: 1px solid var(--border); text-decoration: none; color: inherit;
        }
        .lead-item:last-child { border-bottom: none; }
        .lead-left { display: flex; align-items: center; gap: 14px; }
        .lead-avatar {
            width: 40px; height: 40px; border-radius: 50%; background: var(--bg);
            display: flex; align-items: center; justify-content: center;
            color: var(--secondary); font-weight: 700; font-size: 14px; border: 1px solid var(--border);
        }
        .lead-name { font-weight: 700; font-size: 15px; color: var(--text-main); }
        .lead-date { font-size: 12px; color: var(--text-muted); font-weight: 500; margin-top: 2px; }

        .lead-status { padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 700; }
        .status-new { background: var(--bg); color: var(--secondary); border: 1px solid var(--border); }
        .status-process { background: #FFF7ED; color: var(--accent); border: 1px solid #FED7AA; }
        .status-success { background: #F0FDF4; color: var(--success); border: 1px solid #BBF7D0; }

        .skeleton {
            background: linear-gradient(90deg, #F1F5F9 25%, #F8FAFC 50%, #F1F5F9 75%);
            background-size: 200% 100%; animation: loading 1.5s infinite; border-radius: 6px;
        }
        @keyframes loading { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

        .ripple { position: relative; overflow: hidden; transform: translate3d(0, 0, 0); }
        .ripple:after {
            content: ""; display: block; position: absolute; width: 100%; height: 100%; top: 0; left: 0;
            pointer-events: none; background-image: radial-gradient(circle, #fff 10%, transparent 10.01%);
            background-repeat: no-repeat; background-position: 50%; transform: scale(10, 10);
            opacity: 0; transition: transform .5s, opacity 1s;
        }
        .ripple:active:after { transform: scale(0, 0); opacity: .3; transition: 0s; }

        .bottom-nav {
            display: none; position: fixed; bottom: 0; left: 0; right: 0;
            background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px); border-top: 1px solid var(--border);
            padding-bottom: env(safe-area-inset-bottom); z-index: 1000;
        }
        .bottom-nav-inner { display: flex; justify-content: space-around; padding: 8px 10px; }
        .b-nav-item {
            display: flex; flex-direction: column; align-items: center; gap: 4px;
            color: var(--text-muted); text-decoration: none; font-size: 11px; font-weight: 600; position: relative;
        }
        .b-nav-item svg { width: 20px; height: 20px; transition: all 0.2s; stroke-width: 2; }
        .b-nav-item.active { color: var(--accent); }
        
        .b-nav-item.add-action { margin-top: -20px; }
        .b-nav-item.add-action .add-btn-inner {
            width: 44px; height: 44px; background: var(--accent);
            border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white;
            box-shadow: 0 4px 10px rgba(249, 115, 22, 0.3); border: 3px solid var(--surface); transition: transform 0.2s;
        }
        .b-nav-item.add-action svg { stroke: white; width: 22px; height: 22px; }
        .b-nav-item.add-action.active .add-btn-inner { background: var(--primary); box-shadow: 0 4px 10px rgba(15, 44, 89, 0.3); }
        .b-nav-item.add-action:active .add-btn-inner { transform: scale(0.95); }

        .mobile-only { display: none; }
        .mobile-header-left { display: none; }

        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main-content { margin-left: 0; padding: 16px; padding-top: 0; padding-bottom: 100px; }
            .bottom-nav { display: block; }
            .mobile-only { display: flex; }
            
            .top-header { 
                flex-direction: row; justify-content: space-between; align-items: center;
                margin-bottom: 20px; padding: 12px 16px; margin-left: -16px; margin-right: -16px;
                background: rgba(255, 255, 255, 0.98); position: sticky; top: 0; z-index: 100;
                border-bottom: 1px solid var(--border);
            }
            .greeting { display: none; }
            .mobile-header-left { display: flex; flex-direction: column; }
            .mobile-greeting { font-size: 18px; font-weight: 800; color: var(--primary); letter-spacing: -0.5px; }
            .mobile-subtitle { font-size: 12px; color: var(--text-muted); font-weight: 500; }

            .user-profile { display: flex; padding: 4px; border-radius: 50%; border: none; background: transparent; }
            .user-profile .user-name { display: none; }
            .user-profile .avatar { width: 36px; height: 36px; font-size: 14px; background: var(--primary); }
            .icon-btn { width: 36px; height: 36px; border: none; background: transparent; }
            .icon-btn.logout { display: none; }
            
            .hero-card { flex-direction: column; align-items: flex-start; gap: 20px; padding: 24px; border-radius: 12px; }
            .hero-content .amount { font-size: 36px; }
            
            .stats-grid { display: flex; overflow-x: auto; padding-bottom: 10px; margin-left: -16px; margin-right: -16px; padding-left: 16px; padding-right: 16px; scroll-snap-type: x mandatory; }
            .stat-card { min-width: 200px; scroll-snap-align: start; }
        }
    </style>
</head>
<body>

    <div class="app-container">
        <!-- Desktop Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <i data-lucide="shield-check" style="color:var(--primary); width:32px; height:32px;"></i>
                Aura<span>CRM</span>
            </div>
            <nav class="nav-links">
                <a href="index.php" class="nav-link <?php echo $active_page === 'index' ? 'active' : ''; ?>"><i data-lucide="layout-grid"></i> Dashboard</a>
                <a href="add_lead.php" class="nav-link <?php echo $active_page === 'add_lead' ? 'active' : ''; ?>"><i data-lucide="plus-square"></i> Submit Lead</a>
                <a href="leads.php" class="nav-link <?php echo $active_page === 'leads' ? 'active' : ''; ?>"><i data-lucide="users"></i> My Leads</a>
                <a href="payouts.php" class="nav-link <?php echo $active_page === 'payouts' ? 'active' : ''; ?>"><i data-lucide="wallet"></i> Earnings</a>
                <a href="../activity_log.php" class="nav-link"><i data-lucide="activity"></i> Activity Logs</a>
                <a href="../config.php?logout=1" class="nav-link" style="margin-top:auto; color:var(--danger);"><i data-lucide="log-out"></i> Logout</a>
            </nav>
        </aside>

        <!-- Mobile Navigation -->
        <nav class="bottom-nav">
            <div class="bottom-nav-inner">
                <a href="index.php" class="b-nav-item <?php echo $active_page=='index'?'active':''; ?>">
                    <i data-lucide="home"></i>
                    <span>Home</span>
                </a>
                <a href="leads.php" class="b-nav-item <?php echo $active_page=='leads'?'active':''; ?>">
                    <i data-lucide="list"></i>
                    <span>Leads</span>
                </a>
                <a href="add_lead.php" class="b-nav-item add-action <?php echo $active_page=='add_lead'?'active':''; ?>">
                    <div class="add-btn-inner ripple">
                        <i data-lucide="plus"></i>
                    </div>
                </a>
                <a href="calculators.php" class="b-nav-item <?php echo $active_page=='calculators'?'active':''; ?>">
                    <i data-lucide="calculator"></i>
                    <span>Tools</span>
                </a>
                <a href="../activity_log.php" class="b-nav-item">
                    <i data-lucide="activity"></i>
                    <span>Logs</span>
                </a>
                <a href="profile.php" class="b-nav-item <?php echo $active_page=='profile'?'active':''; ?>">
                    <i data-lucide="user"></i>
                    <span>Profile</span>
                </a>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
            <header class="top-header">
                <!-- Desktop Left -->
                <div class="greeting">
                    Hello, <?php echo htmlspecialchars($_SESSION['username']); ?>!
                    <span>Welcome back to your partner portal.</span>
                </div>
                
                <!-- Mobile Left: Branding -->
                <div class="mobile-header-left">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <div style="background:var(--primary); width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center;">
                            <i data-lucide="shield-check" style="color:white; width:20px;"></i>
                        </div>
                        <div style="font-size:18px; font-weight:800; letter-spacing:-0.5px;">
                            <span style="color:var(--primary);">Aura</span><span style="color:var(--accent);">CRM</span>
                        </div>
                    </div>
                </div>
                
                <!-- Actions Right -->
                <div class="header-actions">
                    <!-- Help Icon -->
                    <a href="mailto:support@bfsBFS Financial Services.in" class="icon-btn ripple" title="Help & Support">
                        <i data-lucide="headphones" style="width:20px; height:20px;"></i>
                    </a>
                    
                    <!-- Notification Bell -->
                    <a href="#" class="icon-btn ripple" title="Notifications" onclick="alert('No new notifications')">
                        <i data-lucide="bell" style="width:20px; height:20px;"></i>
                        <span class="badge"></span>
                    </a>
                    
                    <!-- Profile Avatar -->
                    <a href="profile.php" class="user-profile ripple" style="text-decoration:none;">
                        <div class="avatar">
                            <?php if ($has_ca_photo): ?>
                                <img src="<?php echo htmlspecialchars($ca_photo_path); ?>" alt="<?php echo htmlspecialchars($ca_name); ?>" style="width:100%; height:100%; object-fit:cover; display:block;">
                            <?php else: ?>
                                <?php echo strtoupper(substr($ca_name ?? 'C', 0, 1)); ?>
                            <?php endif; ?>
                        </div>
                        <div class="user-name"><?php echo htmlspecialchars($ca_name); ?></div>
                        <i data-lucide="chevron-down" style="color:var(--text-muted); width:16px; margin-right:8px;"></i>
                    </a>
                    
                    <a href="../config.php?logout=1" class="icon-btn logout mobile-only ripple" title="Logout">
                        <i data-lucide="log-out" style="width:20px; height:20px;"></i>
                    </a>
                </div>
            </header>
