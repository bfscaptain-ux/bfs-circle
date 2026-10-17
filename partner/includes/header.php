<?php
define('IS_SUBFOLDER', true);
require_once __DIR__ . '/../../config.php';
require_role(['Partner', 'DSA', 'Connector', 'Individual', 'Financial Advisor / DSA']);


$partner_id = $_SESSION['user_id'];
$partner_username = $_SESSION['username'];

// Fetch partner user details & photo
$stmt_header = $db->prepare("SELECT name, full_name, profile_photo FROM users WHERE id = ?");
$stmt_header->execute([$partner_id]);
$u_header = $stmt_header->fetch(PDO::FETCH_ASSOC);

$partner_name = !empty($u_header['name']) ? $u_header['name'] : (!empty($u_header['full_name']) ? $u_header['full_name'] : $partner_username);
$partner_photo = $u_header['profile_photo'] ?? '';

// Check referrals if not in users
if (empty($partner_photo)) {
    $stmt_ref_photo = $db->prepare("SELECT profile_photo, full_name FROM referrals WHERE user_id = ? OR mobile = ? LIMIT 1");
    $stmt_ref_photo->execute([$partner_id, $partner_username]);
    if ($rf = $stmt_ref_photo->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($rf['profile_photo'])) $partner_photo = $rf['profile_photo'];
        if (empty($partner_name) && !empty($rf['full_name'])) $partner_name = $rf['full_name'];
    }
}

$has_custom_photo = false;
$photo_display_path = '';
if (!empty($partner_photo)) {
    $clean_path = ltrim($partner_photo, './');
    if (file_exists(__DIR__ . '/../../' . $clean_path)) {
        $has_custom_photo = true;
        $photo_display_path = '../' . $clean_path;
    }
}

// Count unread notifications
$stmt_notif = $db->prepare("SELECT COUNT(id) FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt_notif->execute([$partner_id]);
$unread_count = $stmt_notif->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Adviser Dashboard - BFS Financial Services</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --primary: #1e40af; /* Deep Blue */
            --primary-hover: #1e3a8a;
            --accent: #10b981; /* Emerald Green */
            --accent-hover: #059669;
            --bg-main: #f8fafc;
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

        /* Top App Bar - Premium Navy Blue */
        .app-bar {
            background: linear-gradient(135deg, #1e40af, #1e3a8a);
            color: white;
            padding: 20px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 10px 25px rgba(15, 23, 42, 0.15);
            border-bottom-left-radius: 30px;
            border-bottom-right-radius: 30px;
            margin-bottom: 24px;
        }

        .app-title {
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 22px;
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
            font-weight: 800;
            font-family: 'Outfit', sans-serif;
            border: 2px solid rgba(255,255,255,0.2);
            color: white;
            text-decoration: none;
            box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3);
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
            color: var(--accent-hover);
        }

        .nav-item.active i {
            stroke-width: 2.5;
            color: var(--accent-hover);
            transform: translateY(-2px);
        }

        /* Floating Action Button for Add Lead */
        .fab-wrapper {
            position: relative;
            top: -28px;
            animation: pulse-gold 2s infinite;
        }

        @keyframes pulse-gold {
            0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4); border-radius: 50%; }
            70% { box-shadow: 0 0 0 15px rgba(16, 185, 129, 0); border-radius: 50%; }
            100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); border-radius: 50%; }
        }

        .fab {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--accent), var(--accent-hover));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.35);
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

        input[type="text"], input[type="tel"], input[type="number"], select {
            width: 100%;
            padding: 14px 16px;
            border: 1.5px solid var(--border);
            border-radius: 12px;
            font-size: 15px;
            background: var(--bg-main);
            color: var(--text-primary);
            transition: all 0.2s;
            font-weight: 500;
        }

        input:focus, select:focus {
            outline: none;
            border-color: var(--primary);
            background: var(--bg-card);
            box-shadow: 0 0 0 4px rgba(15, 23, 42, 0.1);
        }

        .btn {
            display: block;
            width: 100%;
            padding: 16px;
            border: none;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--primary), var(--primary-hover));
            color: white;
            font-size: 16px;
            font-weight: 700;
            text-align: center;
            cursor: pointer;
            transition: opacity 0.2s;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.25);
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
        .badge-pending { background: #fef9c3; color: #a16207; border: 1px solid #fef08a; }
        .badge-approved { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .badge-rejected { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
        
        /* Notifications Modal */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.4); z-index: 200; backdrop-filter: blur(4px); }
        .modal-content { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 90%; max-width: 400px; background: white; border-radius: 20px; padding: 24px; z-index: 201; box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
        .notif-item { padding: 12px 0; border-bottom: 1px solid #f1f5f9; font-size: 13px; color: #475569; }
        .notif-item:last-child { border-bottom: none; }
    </style>
</head>
<body>

<div class="app-bar">
    <div class="app-title">Adviser<span class="app-title-dot"></span></div>
    <div class="app-user">
        <div style="position:relative; cursor:pointer;" onclick="document.getElementById('notif-modal').style.display='block'; markNotificationsRead();">
            <i data-lucide="bell" style="width:20px; color:white;"></i>
            <?php if ($unread_count > 0): ?>
            <span style="position:absolute; top:-5px; right:-5px; background:var(--accent); color:white; font-size:10px; font-weight:bold; border-radius:50%; width:16px; height:16px; display:flex; align-items:center; justify-content:center;" id="notif-badge"><?php echo $unread_count; ?></span>
            <?php endif; ?>
        </div>
        <a href="../login.php?logout=1" style="color:white; text-decoration:none;"><i data-lucide="log-out" style="width:20px;"></i></a>
        <a href="profile.php" class="user-avatar" title="View Profile">
            <?php if ($has_custom_photo): ?>
                <img src="<?php echo htmlspecialchars($photo_display_path); ?>" alt="<?php echo htmlspecialchars($partner_name); ?>" style="width:100%; height:100%; object-fit:cover; display:block;">
            <?php else: ?>
                <?php echo strtoupper(substr($partner_name ?? 'P', 0, 1)); ?>
            <?php endif; ?>
        </a>
    </div>
</div>

<div class="modal-overlay" id="notif-modal">
    <div class="modal-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h3 style="font-family:'Outfit'; font-size:18px; margin:0;">Notifications</h3>
            <i data-lucide="x" style="width:20px; cursor:pointer;" onclick="document.getElementById('notif-modal').style.display='none'"></i>
        </div>
        <div style="max-height: 300px; overflow-y: auto;">
            <?php
            $stmt_all_notif = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
            $stmt_all_notif->execute([$partner_id]);
            $notifs = $stmt_all_notif->fetchAll();
            if (count($notifs) > 0) {
                foreach ($notifs as $n) {
                    echo "<div class='notif-item'>" . htmlspecialchars($n['message']) . "<br><small style='color:#94a3b8; font-size:11px;'>" . date('d M, h:i A', strtotime($n['created_at'])) . "</small></div>";
                }
            } else {
                echo "<p style='font-size:13px; color:#94a3b8; text-align:center;'>No notifications yet.</p>";
            }
            ?>
        </div>
    </div>
</div>

<script>
function markNotificationsRead() {
    let badge = document.getElementById('notif-badge');
    if (badge) badge.style.display = 'none';
    fetch('api_notifications.php', { method: 'POST' });
}
</script>

<div class="container">
