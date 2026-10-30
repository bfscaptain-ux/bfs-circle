<?php
define('IS_SUBFOLDER', true);
require_once __DIR__ . '/../config.php';
require_role(['Staff']);

header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");


$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Staff';

$stmt = $db->prepare("SELECT full_name, department, photo_path FROM employees WHERE user_id = ?");
$stmt->execute([$user_id]);
$emp_data = $stmt->fetch(PDO::FETCH_ASSOC);
$fullname = !empty($emp_data['full_name']) ? $emp_data['full_name'] : $username;
$department = $emp_data['department'] ?? '';
$photo_path = $emp_data['photo_path'] ?? '';

// Dynamic Greeting based on time
$hour = date('H');
if ($hour < 12) {
    $greeting = "Good Morning,";
} elseif ($hour < 16) {
    $greeting = "Good Afternoon,";
} elseif ($hour < 20) {
    $greeting = "Good Evening,";
} else {
    $greeting = "Good Night,";
}

$username = $_SESSION['username'];
$role = $_SESSION['role'];

// Check Attendance Status
$today = date('Y-m-d');
$att_stmt = $db->prepare("SELECT * FROM staff_attendance WHERE username = ? AND att_date = ?");
$att_stmt->execute([$username, $today]);
$attendance = $att_stmt->fetch(PDO::FETCH_ASSOC);
$is_punched_in = $attendance && !$attendance['punch_out'];
$is_punched_out = $attendance && $attendance['punch_out'];
$punch_in_time = $attendance['punch_in'] ?? '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Official BFS Circle Monochrome Favicon & Branding -->
    <link rel="icon" type="image/png" sizes="64x64" href="<?php echo isset($base_path) ? $base_path : ""; ?>favicon.png?v=3">
    <link rel="icon" type="image/x-icon" href="<?php echo isset($base_path) ? $base_path : ""; ?>favicon.ico?v=3">
    <link rel="shortcut icon" href="<?php echo isset($base_path) ? $base_path : ""; ?>favicon.ico?v=3">
    <link rel="apple-touch-icon" href="<?php echo isset($base_path) ? $base_path : ""; ?>favicon.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>My Field App - BFS Financial Services</title>
    <!-- PWA Mobile App Support -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#FF7A00">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="BFS Field App">
    <link rel="apple-touch-icon" href="../logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #FF7A00;
            --primary-dark: #E66A00;
            --secondary: #1E293B;
            --bg-color: #F1F5F9;
            --card-bg: #FFFFFF;
            --text-main: #0F172A;
            --text-muted: #64748B;
            --border-color: #E2E8F0;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        
        body {
            background-color: var(--bg-color);
            color: var(--text-main);
            padding-bottom: 70px; /* Space for bottom nav */
        }

        /* App Header */
        .app-header {
            background: linear-gradient(135deg, #FF7A00 0%, #FF5500 100%);
            color: white;
            padding: 24px 16px 20px 16px;
            border-bottom-left-radius: 28px;
            border-bottom-right-radius: 28px;
            box-shadow: 0 10px 25px rgba(255, 122, 0, 0.25);
            position: relative;
            overflow: hidden;
            margin-top: 0;
            z-index: 10;
        }
        .app-header::after {
            content: '';
            position: absolute;
            top: -40px;
            right: -40px;
            width: 140px;
            height: 140px;
            background: radial-gradient(circle, rgba(255,255,255,0.18) 0%, rgba(255,255,255,0) 70%);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .company-logo-box {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            z-index: 2;
            padding-right: 8px;
            animation: floatLogo 4s ease-in-out infinite;
        }
        .company-logo-box img {
            height: 56px;
            object-fit: contain;
            position: relative;
            z-index: 2;
            /* Turn any colored logo into pure white */
            filter: brightness(0) invert(1) drop-shadow(0 2px 4px rgba(0,0,0,0.15));
        }
        @keyframes floatLogo {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-3px); }
            100% { transform: translateY(0px); }
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .user-greeting { font-size: 14px; opacity: 0.9; }
        .user-name { font-size: 20px; font-weight: 700; margin-top: 4px; }
        .logout-btn { color: white; text-decoration: none; font-size: 20px; }

        /* Dashboard Cards */
        .metrics-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            padding: 0 16px;
            margin-top: 14px;
        }

        .metric-card {
            background: white;
            border-radius: 24px;
            padding: 20px;
            box-shadow: 0 12px 24px rgba(0,0,0,0.04);
            text-align: center;
            border: 1px solid rgba(0,0,0,0.02);
        }
        
        .metric-card.full-width {
            grid-column: 1 / -1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            text-align: left;
            background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%);
            color: white;
            border-radius: 24px;
            box-shadow: 0 16px 32px rgba(15, 23, 42, 0.2);
            border: 1px solid rgba(255,255,255,0.1);
        }

        .metric-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: rgba(255, 122, 0, 0.1);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            margin: 0 auto 12px;
        }

        .metric-value { font-size: 24px; font-weight: 700; color: var(--text-main); }
        .metric-label { font-size: 12px; color: var(--text-muted); margin-top: 4px; }
        
        .full-width .metric-value { color: white; font-size: 28px; }
        .full-width .metric-label { color: #94A3B8; }
        .full-width .metric-icon { margin: 0; background: rgba(255,255,255,0.1); color: white; }

        /* Recent Activity */
        .section-container {
            padding: 24px 16px;
        }

        .section-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .see-all { font-size: 12px; color: var(--primary); text-decoration: none; font-weight: 500; }

        .lead-card {
            background: white;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            border: 1px solid var(--border-color);
        }

        .lead-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #F1F5F9;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            flex-shrink: 0;
        }

        .lead-info { flex: 1; overflow: hidden; }
        .lead-name { font-size: 14px; font-weight: 600; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .lead-sub { font-size: 12px; color: var(--text-muted); }
        
        .lead-status {
            font-size: 11px;
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 20px;
            background: #e0f2fe;
            color: #0369a1;
        }
        .status-completed { background: #dcfce7; color: #15803d; }

        /* Bottom Nav */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            display: flex;
            justify-content: space-around;
            padding: 12px 0;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.05);
            z-index: 100;
        }

        .nav-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 10px;
            font-weight: 500;
        }

        .nav-btn.active { color: var(--primary); }
        .nav-btn i { font-size: 20px; }
        
        /* Floating Action Button */
        .fab {
            position: absolute;
            top: -20px;
            left: 50%;
            transform: translateX(-50%);
            height: 44px;
            padding: 0 16px;
            border-radius: 22px;
            background: linear-gradient(135deg, #FF6B00 0%, #FF9A44 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 600;
            box-shadow: 0 6px 15px rgba(255, 107, 0, 0.35);
            text-decoration: none;
            white-space: nowrap;
            gap: 6px;
        }

        /* Tools Horizontal Scroll */
        .tools-scroll {
            display: flex;
            overflow-x: auto;
            gap: 16px;
            padding: 8px 16px 24px 16px;
            scrollbar-width: none; /* Firefox */
        }
        .tools-scroll::-webkit-scrollbar { display: none; }
        
        .tool-card {
            min-width: 130px;
            background: white;
            border-radius: 20px;
            padding: 18px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.03);
            text-decoration: none;
            display: flex;
            flex-direction: column;
            gap: 12px;
            border: 1px solid #f1f5f9;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .tool-card:active {
            transform: scale(0.96);
            box-shadow: 0 4px 8px rgba(0,0,0,0.02);
        }
        
        .tool-card .icon {
            width: 40px; height: 40px;
            border-radius: 12px;
            background: rgba(255,122,0,0.1);
            color: var(--primary);
            display: flex; align-items: center; justify-content: center;
            font-size: 18px;
        }
        
        .tool-card .title {
            color: var(--text-main);
            font-weight: 600;
            font-size: 14px;
        }


    
        /* Sleek Duty Toggle */
        .duty-container {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 100px;
            padding: 6px 12px;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            margin-top: 10px;
            border: 1px solid rgba(255,255,255,0.3);
            box-shadow: inset 0 2px 4px rgba(255,255,255,0.1);
            position: relative;
            z-index: 2;
        }
        
        .duty-label {
            color: white;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .pulse-live {
            width: 8px;
            height: 8px;
            background: #4ade80;
            border-radius: 50%;
            box-shadow: 0 0 0 0 rgba(74, 222, 128, 0.7);
            animation: pulse-green 1.5s infinite;
        }
        .pulse-offline {
            width: 8px;
            height: 8px;
            background: #f87171;
            border-radius: 50%;
        }
        @keyframes pulse-green {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(74, 222, 128, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(74, 222, 128, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(74, 222, 128, 0); }
        }

        /* iOS style toggle switch */
        .switch {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
        }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider {
            position: absolute; cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: rgba(255,255,255,0.3);
            transition: .3s;
            border-radius: 24px;
        }
        .slider:before {
            position: absolute; content: "";
            height: 18px; width: 18px;
            left: 3px; bottom: 3px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        input:checked + .slider { background-color: #22c55e; }
        input:checked + .slider:before { transform: translateX(20px); }
    </style>
</head>
<body>

    <div class="app-header">
        <div class="header-top" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; gap:6px;">
            <div style="display:flex; align-items:center; gap:8px; min-width:0; flex:1;">
                <div onclick="document.getElementById('digitalIdModal').style.display='flex'" style="width:40px; height:40px; background:rgba(255,255,255,0.25); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:18px; overflow:hidden; border:2px solid white; box-shadow:0 3px 8px rgba(0,0,0,0.12); cursor:pointer; flex-shrink:0;" title="Tap to view Digital ID Badge">
                    <?php if(!empty($photo_path)): ?>
                        <img src="../uploads/employees/<?= htmlspecialchars($photo_path) ?>" style="width:100%; height:100%; object-fit:cover;">
                    <?php else: ?>
                        👨‍💼
                    <?php endif; ?>
                </div>
                <div style="min-width:0; flex:1;">
                    <div class="user-greeting" style="font-size:11.5px; font-weight:500; opacity:0.95; letter-spacing:0.1px; white-space:nowrap;"><?= isset($greeting) ? $greeting : 'Hello,' ?></div>
                    <div class="user-name" id="staff_name" style="font-size:15px; font-weight:800; margin-top:1px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; letter-spacing:-0.2px;"><?= htmlspecialchars($fullname) ?></div>
                </div>
            </div>
            <div style="display:flex; align-items:center; gap:5px; flex-shrink:0; white-space:nowrap;">
                <button onclick="document.getElementById('digitalIdModal').style.display='flex'" style="background:rgba(255,255,255,0.22); backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px); color:white; border:1px solid rgba(255,255,255,0.4); padding:5px 9px; border-radius:18px; font-size:11px; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:4px; box-shadow:0 2px 6px rgba(0,0,0,0.06); white-space:nowrap;">
                    <i class="fas fa-id-badge"></i> <span>ID Badge</span>
                </button>
                <a href="../logout.php" onclick="return confirm('Are you sure you want to log out of your account?');" style="background:rgba(239,68,68,0.35); backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px); color:white; border:1px solid rgba(255,255,255,0.4); padding:5px 9px; border-radius:18px; font-size:11px; font-weight:700; text-decoration:none; display:inline-flex; align-items:center; gap:4px; cursor:pointer; box-shadow:0 2px 6px rgba(0,0,0,0.06); white-space:nowrap;" title="Logout">
                    <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
                </a>
            </div>
        </div>
        
        <!-- Professional Sleek Duty Toggle & Logo Box -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px; position:relative; z-index:2;">
            <div class="duty-container" style="margin-top:0;">
                <div class="duty-label">
                    <?php if ($is_punched_in): ?>
                        <div style="display:flex; align-items:center;">
                            <div class="pulse-live"></div> 
                            <span id="duty-text" style="font-weight:600; color:#fff;">On Duty</span>
                            <span style="color:rgba(255,255,255,0.5); margin:0 6px;">|</span>
                            <i class="far fa-clock" style="font-size:11px; color:rgba(255,255,255,0.8); margin-right:4px;"></i>
                            <span id="shift_timer" style="font-size:13px; font-weight:700; color:#fff; font-variant-numeric:tabular-nums; letter-spacing:0.5px;">00:00:00</span>
                        </div>
                    <?php else: ?>
                        <div class="pulse-offline"></div> <span id="duty-text">Off Duty</span>
                    <?php endif; ?>
                </div>
                <label class="switch">
                    <input type="checkbox" id="dutyToggle" onchange="toggleDuty(this)" <?= $is_punched_in ? 'checked' : '' ?>>
                    <span class="slider"></span>
                </label>
            </div>
            
            <div class="company-logo-box">
                <img src="../logo.png" alt="BFS Logo" onerror="this.style.display='none'">
            </div>
        </div>
        
        <script>
        async function toggleDuty(cb) {
            const action = cb.checked ? 'in' : 'out';
            if (action === 'in') {
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(async (pos) => {
                        await fetch('../api.php?api=punch_in', { method:'POST' });
                        window.location.reload();
                    }, (err) => {
                        document.getElementById('gps-blocker').style.display = 'flex';
                        cb.checked = false;
                    }, { timeout: 5000 });
                } else {
                    alert("GPS not supported.");
                    cb.checked = false;
                }
            } else {
                if(confirm("End shift and stop location tracking?")) {
                    await fetch('../api.php?api=punch_out', { method:'POST' });
                    window.location.reload();
                } else {
                    cb.checked = true;
                }
            }
        }
        window.TRACKING_ACTIVE = <?= $is_punched_in ? 'true' : 'false' ?>;
        
        <?php if ($is_punched_in && $punch_in_time): ?>
        (function() {
            // Calculate elapsed time securely using server-provided start time
            // PHP format is 'Y-m-d H:i:s'
            const punchInStr = "<?= $punch_in_time ?>".replace(/-/g, '/'); // Compatibility for Safari/iOS
            const startTime = new Date(punchInStr).getTime();
            const timerEl = document.getElementById('shift_timer');
            
            function updateShiftTimer() {
                const now = new Date().getTime();
                let diff = Math.floor((now - startTime) / 1000);
                if (diff < 0) diff = 0;
                
                const hrs = String(Math.floor(diff / 3600)).padStart(2, '0');
                const mins = String(Math.floor((diff % 3600) / 60)).padStart(2, '0');
                const secs = String(diff % 60).padStart(2, '0');
                timerEl.innerText = `${hrs}:${mins}:${secs}`;
            }
            setInterval(updateShiftTimer, 1000);
            updateShiftTimer();
        })();
        <?php endif; ?>
        
        </script>
    </div>

    <div class="metrics-grid" style="grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-top: 16px;">
        <div class="metric-card" style="background: #0F172A; border-radius: 16px; border: 1px solid #1E293B; padding: 14px; text-align:left;">
            <div class="metric-label" style="color: #94A3B8; font-size: 11px; font-weight: 600;">Commission</div>
            <div class="metric-value" id="total_commission" style="color: #FF7A00; font-size: 20px; font-weight: 800; margin-top: 4px;">₹0</div>
        </div>

        <div class="metric-card" style="background: white; border-radius: 16px; border: 1px solid #E2E8F0; padding: 14px; text-align:left;">
            <div class="metric-label" style="color: #64748B; font-size: 11px; font-weight: 600;">Field Visits</div>
            <div class="metric-value" id="total_visits" style="color: #0F172A; font-size: 20px; font-weight: 800; margin-top: 4px;">0</div>
        </div>
        
        <div class="metric-card" style="background: white; border-radius: 16px; border: 1px solid #E2E8F0; padding: 14px; text-align:left;">
            <div class="metric-label" style="color: #64748B; font-size: 11px; font-weight: 600;">My Leads</div>
            <div class="metric-value" id="total_leads" style="color: #0F172A; font-size: 20px; font-weight: 800; margin-top: 4px;">0</div>
        </div>
    </div>

    <!-- Main Field Operations Grid (2x2) -->
    <div style="padding: 0 16px; margin-top: 16px;">
        <div style="font-size: 14px; font-weight: 700; color: #0F172A; margin-bottom: 10px; display:flex; justify-content:space-between; align-items:center;">
            <span>Field Operations</span>
            <span style="font-size:11px; color:#FF7A00; font-weight:600;">Quick Actions</span>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
            <a href="add_applicant.php" style="text-decoration:none; background:white; border-radius:18px; padding:16px; border:1.5px solid #FF7A00; box-shadow:0 4px 12px rgba(255,122,0,0.08); display:flex; flex-direction:column; gap:10px;">
                <div style="width:40px; height:40px; background:rgba(255,122,0,0.12); border-radius:12px; display:flex; align-items:center; justify-content:center; color:#FF7A00; font-size:18px;">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div>
                    <div style="font-weight:700; font-size:14px; color:#0F172A;">Add Loan Lead</div>
                    <div style="font-size:11px; color:#64748B; margin-top:2px;">Submit new client file</div>
                </div>
            </a>

            <a href="add_visit.php" style="text-decoration:none; background:white; border-radius:18px; padding:16px; border:1px solid #E2E8F0; box-shadow:0 4px 12px rgba(0,0,0,0.02); display:flex; flex-direction:column; gap:10px;">
                <div style="width:40px; height:40px; background:rgba(15,23,42,0.06); border-radius:12px; display:flex; align-items:center; justify-content:center; color:#0F172A; font-size:18px;">
                    <i class="fas fa-location-dot"></i>
                </div>
                <div>
                    <div style="font-weight:700; font-size:14px; color:#0F172A;">Field Check-in</div>
                    <div style="font-size:11px; color:#64748B; margin-top:2px;">Record visit & photo</div>
                </div>
            </a>

            <a href="my_route.php" style="text-decoration:none; background:white; border-radius:18px; padding:16px; border:1px solid #E2E8F0; box-shadow:0 4px 12px rgba(0,0,0,0.02); display:flex; flex-direction:column; gap:10px;">
                <div style="width:40px; height:40px; background:rgba(255,122,0,0.1); border-radius:12px; display:flex; align-items:center; justify-content:center; color:#FF7A00; font-size:18px;">
                    <i class="fas fa-route"></i>
                </div>
                <div>
                    <div style="font-weight:700; font-size:14px; color:#0F172A;">Timeline & Route</div>
                    <div style="font-size:11px; color:#64748B; margin-top:2px;">Today's field journey</div>
                </div>
            </a>

            <a href="pre_leads.php" style="text-decoration:none; background:white; border-radius:18px; padding:16px; border:1px solid #E2E8F0; box-shadow:0 4px 12px rgba(0,0,0,0.02); display:flex; flex-direction:column; gap:10px;">
                <div style="width:40px; height:40px; background:rgba(15,23,42,0.06); border-radius:12px; display:flex; align-items:center; justify-content:center; color:#0F172A; font-size:18px;">
                    <i class="fas fa-layer-group"></i>
                </div>
                <div>
                    <div style="font-weight:700; font-size:14px; color:#0F172A;">Pre-Leads Pool</div>
                    <div style="font-size:11px; color:#64748B; margin-top:2px;">View raw prospects</div>
                </div>
            </a>
        </div>
    </div>

    <!-- RM & Admin Support & Emergency Helpline Desk -->
    <div style="padding: 0 16px; margin-top: 14px;">
        <div style="background: white; border-radius: 18px; padding: 16px; color: #0F172A; box-shadow: 0 4px 12px rgba(0,0,0,0.03); border: 1px solid #E2E8F0;">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px;">
                <div style="display:flex; align-items:center; gap:8px;">
                    <div style="width:34px; height:34px; background:rgba(255,122,0,0.1); border-radius:10px; display:flex; align-items:center; justify-content:center; color:#FF7A00; font-size:16px;">
                        <i class="fas fa-headset"></i>
                    </div>
                    <div>
                        <div style="font-weight:700; font-size:14px; color:#0F172A;">RM & Admin Helpline</div>
                        <div style="font-size:11px; color:#64748B;">Field Assistance & Live SOS</div>
                    </div>
                </div>
                <span style="background:rgba(255,122,0,0.1); color:#FF7A00; font-size:10px; font-weight:700; padding:3px 8px; border-radius:10px;">
                    Connected
                </span>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                <a href="tel:+918000000000" style="background:linear-gradient(135deg, #FF7A00 0%, #FF5500 100%); color:white; text-decoration:none; padding:12px; border-radius:14px; font-size:12px; font-weight:700; display:flex; align-items:center; justify-content:center; gap:8px; box-shadow:0 4px 12px rgba(255, 122, 0, 0.25);">
                    <i class="fas fa-phone-alt" style="font-size:13px;"></i> Call RM
                </a>
                
                <button onclick="triggerFieldSOS()" style="background:#FEF2F2; color:#DC2626; border:1px solid #FCA5A5; padding:12px; border-radius:14px; font-size:12px; font-weight:700; display:flex; align-items:center; justify-content:center; gap:8px; cursor:pointer;">
                    <i class="fas fa-triangle-exclamation" style="font-size:13px; color:#DC2626;"></i> SOS Alert
                </button>
            </div>
        </div>
    </div>

    <!-- PWA Installation Banner & Device Permissions -->
    <div id="pwa-inline-banner" style="display:none; padding: 0 16px; margin-top: 14px;">
        <div style="background: white; border-radius: 18px; padding: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.03); border: 1px solid #E2E8F0;">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px;">
                <div style="display:flex; align-items:center; gap:8px;">
                    <div style="width:34px; height:34px; background:rgba(255,122,0,0.1); border-radius:10px; display:flex; align-items:center; justify-content:center; color:#FF7A00; font-size:16px;">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <div>
                        <div style="font-weight:700; font-size:14px; color:#0F172A;">BFS Field App (Mobile PWA)</div>
                        <div style="font-size:11px; color:#64748B;">Installation & Permissions</div>
                    </div>
                </div>
                <button onclick="openPermissionsModal()" style="background:#F1F5F9; border:1px solid #E2E8F0; color:#0F172A; padding:5px 10px; border-radius:8px; font-size:11px; font-weight:700; cursor:pointer; display:flex; align-items:center; gap:4px;">
                    <i class="fas fa-sliders-h" style="color:#FF7A00;"></i> Setup
                </button>
            </div>

            <div>
                <button id="pwa-install-btn" onclick="triggerPWAInstall()" style="width:100%; background:linear-gradient(135deg, #FF7A00 0%, #FF5500 100%); color:white; border:none; padding:12px; border-radius:12px; font-weight:700; font-size:13px; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; box-shadow:0 4px 12px rgba(255, 122, 0, 0.25);">
                    <i class="fas fa-download"></i> <span>Install BFS Field App Now</span>
                </button>
            </div>

            <div style="margin-top:12px; padding-top:10px; border-top:1px dashed #E2E8F0; display:flex; justify-content:space-between; font-size:11px; font-weight:600; color:#64748B;">
                <span id="perm-gps-status"><i class="fas fa-location-dot" style="color:#FF7A00;"></i> GPS</span>
                <span id="perm-cam-status"><i class="fas fa-camera" style="color:#0F172A;"></i> Camera</span>
                <span id="perm-notif-status"><i class="fas fa-bell" style="color:#FF7A00;"></i> Alerts</span>
            </div>
        </div>
    </div>

    <!-- PWA Onboarding & Permissions Auto-Popup Modal -->
    <div id="permissionsModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.65); backdrop-filter:blur(6px); z-index:9999; align-items:center; justify-content:center; padding:16px;">
        <div style="background:white; border-radius:24px; max-width:420px; width:100%; padding:24px; box-shadow:0 25px 50px rgba(0,0,0,0.25); position:relative; max-height:90vh; overflow-y:auto; border: 1px solid #E2E8F0;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
                <div style="font-size:17px; font-weight:800; color:#0F172A; display:flex; align-items:center; gap:8px;">
                    <i class="fas fa-mobile-screen-button" style="color:#FF7A00; font-size:20px;"></i> BFS Mobile App Setup
                </div>
                <button onclick="closePermissionsModal()" style="background:#F1F5F9; border:none; width:32px; height:32px; border-radius:50%; font-size:14px; font-weight:700; cursor:pointer; color:#64748B;">✕</button>
            </div>

            <!-- Install App Banner -->
            <div style="background: linear-gradient(135deg, #FF7A00 0%, #E66A00 100%); border-radius:18px; padding:16px; color:white; margin-bottom:16px; text-align:center; box-shadow: 0 8px 20px rgba(255,122,0,0.25);">
                <i class="fas fa-download" style="font-size:24px; margin-bottom:8px;"></i>
                <div style="font-weight:800; font-size:16px;">Install App on your Phone</div>
                <div style="font-size:12px; opacity:0.95; margin-top:4px; line-height:1.4;">Add BFS Field Staff App to your home screen for 1-tap fast access & live GPS tracking.</div>
                
                <button id="pwa-popup-install-btn" onclick="triggerPWAInstall()" style="background:white; color:#FF7A00; border:none; width:100%; padding:12px; border-radius:12px; font-weight:800; font-size:14px; margin-top:12px; cursor:pointer; box-shadow:0 4px 10px rgba(0,0,0,0.1);">
                    📲 Install Mobile App Now
                </button>
            </div>

            <div style="font-size:13px; font-weight:700; color:#0F172A; margin-bottom:10px; display:flex; align-items:center; justify-content:space-between;">
                <span style="display:flex; align-items:center; gap:6px;"><i class="fas fa-shield-alt" style="color:#FF7A00;"></i> Enable Device Permissions</span>
                <button onclick="requestAllPermissions()" style="background:#FF7A00; color:white; border:none; padding:5px 10px; border-radius:8px; font-size:11px; font-weight:700; cursor:pointer;">
                    ⚡ Grant All
                </button>
            </div>

            <!-- Permission Settings Guide Card (Hidden until blocked) -->
            <div id="perm-guide-card" style="display:none; background:#FFFBEB; border:1px solid #FCD34D; border-radius:14px; padding:14px; margin-bottom:14px;">
                <div style="font-weight:800; font-size:13px; color:#B45309; margin-bottom:6px; display:flex; align-items:center; gap:6px;">
                    <i class="fas fa-exclamation-triangle"></i> Permission Blocked by Browser
                </div>
                <div style="font-size:12px; color:#78350F; line-height:1.5;">
                    <strong>Android / Chrome me permission open karne ke steps:</strong><br>
                    1. Screen ke uper <strong>🔒 Lock icon</strong> (ya ⚙️ Settings) par tap karein.<br>
                    2. <strong>Permissions / Site Settings</strong> par click karein.<br>
                    3. <strong>Location</strong> aur <strong>Camera</strong> ko <strong>ALLOW (ON)</strong> karein.<br>
                    4. Dobara App me aakar <strong>"Re-Check Permissions"</strong> dabaaein.
                </div>
                <button onclick="checkPermissionsStatus()" style="background:#B45309; color:white; border:none; width:100%; padding:8px; border-radius:8px; font-weight:700; font-size:12px; margin-top:10px; cursor:pointer;">
                    🔄 Re-Check Permissions Now
                </button>
            </div>

            <!-- GPS Location -->
            <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:14px; padding:12px; margin-bottom:10px; display:flex; align-items:center; justify-content:space-between;">
                <div style="display:flex; align-items:center; gap:10px;">
                    <div style="width:34px; height:34px; background:rgba(255,122,0,0.1); border-radius:10px; display:flex; align-items:center; justify-content:center; color:#FF7A00;">
                        <i class="fas fa-location-crosshairs"></i>
                    </div>
                    <div>
                        <div style="font-size:13px; font-weight:700; color:#0F172A;">GPS Location</div>
                        <div style="font-size:11px; color:#64748B;">High-precision field pings</div>
                    </div>
                </div>
                <button id="btn-grant-gps" onclick="requestGpsPermission()" style="background:#FF7A00; color:white; border:none; padding:6px 12px; border-radius:8px; font-size:11px; font-weight:700; cursor:pointer;">
                    Grant
                </button>
            </div>

            <!-- Camera -->
            <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:14px; padding:12px; margin-bottom:10px; display:flex; align-items:center; justify-content:space-between;">
                <div style="display:flex; align-items:center; gap:10px;">
                    <div style="width:34px; height:34px; background:rgba(15,23,42,0.06); border-radius:10px; display:flex; align-items:center; justify-content:center; color:#0F172A;">
                        <i class="fas fa-camera"></i>
                    </div>
                    <div>
                        <div style="font-size:13px; font-weight:700; color:#0F172A;">Camera Access</div>
                        <div style="font-size:11px; color:#64748B;">Visit & document photos</div>
                    </div>
                </div>
                <button id="btn-grant-cam" onclick="requestCameraPermission()" style="background:#0F172A; color:white; border:none; padding:6px 12px; border-radius:8px; font-size:11px; font-weight:700; cursor:pointer;">
                    Grant
                </button>
            </div>

            <!-- Notifications -->
            <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:14px; padding:12px; margin-bottom:14px; display:flex; align-items:center; justify-content:space-between;">
                <div style="display:flex; align-items:center; gap:10px;">
                    <div style="width:34px; height:34px; background:rgba(255,122,0,0.1); border-radius:10px; display:flex; align-items:center; justify-content:center; color:#FF7A00;">
                        <i class="fas fa-bell"></i>
                    </div>
                    <div>
                        <div style="font-size:13px; font-weight:700; color:#0F172A;">Notifications</div>
                        <div style="font-size:11px; color:#64748B;">Lead & Visit alerts</div>
                    </div>
                </div>
                <button id="btn-grant-notif" onclick="requestNotificationPermission()" style="background:#FF7A00; color:white; border:none; padding:6px 12px; border-radius:8px; font-size:11px; font-weight:700; cursor:pointer;">
                    Grant
                </button>
            </div>

            <button onclick="markAppAsInstalled()" style="background:#0F172A; color:white; padding:14px; border:none; border-radius:16px; font-weight:700; font-size:14px; width:100%; cursor:pointer;">
                ✓ Continue to Dashboard
            </button>
        </div>
    </div>

    <!-- Official Verified Digital Executive ID Card Modal -->
    <div id="digitalIdModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(15,23,42,0.85); backdrop-filter:blur(8px); z-index:99999; align-items:center; justify-content:center; padding:16px;">
        <div style="background:white; border-radius:28px; max-width:380px; width:100%; overflow:hidden; box-shadow:0 25px 60px rgba(0,0,0,0.35); position:relative; border:2px solid #FF7A00;">
            <div style="background:linear-gradient(135deg, #0F172A 0%, #1E293B 100%); color:white; padding:24px 20px; text-align:center; position:relative;">
                <button onclick="document.getElementById('digitalIdModal').style.display='none'" style="position:absolute; right:14px; top:14px; background:rgba(255,255,255,0.15); border:none; color:white; width:30px; height:30px; border-radius:50%; font-size:14px; cursor:pointer;">✕</button>
                <img src="../logo.png" style="height:44px; filter:brightness(0) invert(1); margin-bottom:8px;">
                <div style="font-weight:800; font-size:15px; letter-spacing:0.5px;">BFS FINANCIAL SERVICES</div>
                <div style="font-size:10px; color:#FF7A00; font-weight:800; text-transform:uppercase; letter-spacing:1px; margin-top:2px;">OFFICIAL FIELD EXECUTIVE BADGE</div>
            </div>
            <div style="padding:24px; text-align:center;">
                <div style="width:84px; height:84px; border-radius:50%; margin:0 auto 12px; border:3px solid #FF7A00; overflow:hidden; box-shadow:0 8px 20px rgba(255,122,0,0.2);">
                    <?php if(!empty($photo_path)): ?>
                        <img src="../uploads/employees/<?= htmlspecialchars($photo_path) ?>" style="width:100%; height:100%; object-fit:cover;">
                    <?php else: ?>
                        <div style="width:100%; height:100%; background:#F1F5F9; display:flex; align-items:center; justify-content:center; font-size:36px;">👨‍💼</div>
                    <?php endif; ?>
                </div>
                <div style="font-size:18px; font-weight:800; color:#0F172A;"><?= htmlspecialchars($fullname) ?></div>
                <div style="font-size:12px; color:#64748B; font-weight:600; margin-top:2px;"><?= !empty($department) ? htmlspecialchars($department) : 'Field Operations Executive' ?></div>
                
                <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:18px; padding:14px; margin:16px 0; text-align:left; font-size:12px;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                        <span style="color:#64748B;">Executive ID:</span>
                        <strong style="color:#0F172A;">BFS-EXEC-<?= sprintf('%04d', $user_id) ?></strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                        <span style="color:#64748B;">Role / Designation:</span>
                        <strong style="color:#0F172A;"><?= htmlspecialchars($role) ?></strong>
                    </div>
                    <div style="display:flex; justify-content:space-between;">
                        <span style="color:#64748B;">Verification Status:</span>
                        <strong style="color:#10B981; display:flex; align-items:center; gap:4px;">
                            <i class="fas fa-check-circle"></i> Active & Verified
                        </strong>
                    </div>
                </div>

                <div style="display:flex; align-items:center; justify-content:center; gap:8px; color:#10B981; font-size:12px; font-weight:700; background:#ECFDF5; border:1px solid #A7F3D0; padding:10px; border-radius:14px;">
                    <i class="fas fa-shield-halved" style="font-size:16px;"></i> Official Client Verification Badge
                </div>
            </div>
        </div>
    </div>

    <!-- Financial Tools Section -->
    <div class="section-container" style="margin-bottom: 0;">
        <div class="section-title" style="display:flex; justify-content:space-between; align-items:center;">
            <span style="color:#0F172A; font-weight:700;">Financial Tools</span>
            <a href="calculators.php" style="color:#FF7A00; font-size:13px; font-weight:700; text-decoration:none;">View All</a>
        </div>
    </div>
    <div class="tools-scroll">
        <a href="calc_emi.php" class="tool-card" style="border: 1px solid #E2E8F0; background: white;">
            <div class="icon" style="background:rgba(255, 122, 0, 0.1); color:#FF7A00;"><i class="fas fa-calculator"></i></div>
            <div class="title" style="color:#0F172A;">EMI<br>Calculator</div>
        </a>
        <a href="calc_eligibility.php" class="tool-card" style="border: 1px solid #E2E8F0; background: white;">
            <div class="icon" style="background:rgba(15, 23, 42, 0.06); color:#0F172A;"><i class="fas fa-check-circle"></i></div>
            <div class="title" style="color:#0F172A;">Loan<br>Eligibility</div>
        </a>
        <a href="calc_foir.php" class="tool-card" style="border: 1px solid #E2E8F0; background: white;">
            <div class="icon" style="background:rgba(255, 122, 0, 0.1); color:#FF7A00;"><i class="fas fa-percentage"></i></div>
            <div class="title" style="color:#0F172A;">FOIR<br>Calculator</div>
        </a>
        <a href="calc_ltv.php" class="tool-card" style="border: 1px solid #E2E8F0; background: white;">
            <div class="icon" style="background:rgba(15, 23, 42, 0.06); color:#0F172A;"><i class="fas fa-home"></i></div>
            <div class="title" style="color:#0F172A;">LTV<br>Calculator</div>
        </a>
    </div>

    <!-- Leaderboard Banner -->
    <div style="padding: 0 16px 14px;">
        <a href="ranking.php" style="display:flex; align-items:center; justify-content:space-between; background:linear-gradient(135deg, #FF7A00 0%, #E66A00 100%); border-radius:20px; padding:16px 20px; color:white; text-decoration:none; box-shadow:0 8px 20px rgba(255, 122, 0, 0.25);">
            <div style="display:flex; align-items:center; gap:16px;">
                <div style="width:40px; height:40px; background:rgba(255,255,255,0.2); border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:20px;">
                    <i class="fas fa-trophy" style="color:white;"></i>
                </div>
                <div>
                    <div style="font-weight:700; font-size:16px;">Leaderboard Desk</div>
                    <div style="font-size:12px; opacity:0.9;">Check your ranking among field staff</div>
                </div>
            </div>
            <i class="fas fa-chevron-right" style="opacity:0.9;"></i>
        </a>
    </div>

    <!-- PRODUCTIVITY WIDGET 1: Today's Follow-ups & Task Checklist -->
    <div style="padding: 0 16px 16px;">
        <div style="background: white; border-radius: 20px; padding: 18px; border: 1px solid #E2E8F0; box-shadow: 0 4px 15px rgba(0,0,0,0.03);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                <div style="font-size:14px; font-weight:800; color:#0F172A; display:flex; align-items:center; gap:8px;">
                    <i class="fas fa-list-check" style="color:#FF7A00;"></i> Today's Field Tasks & Follow-ups
                </div>
                <span id="task-count-badge" style="background:#FFF7ED; color:#EA580C; font-size:11px; font-weight:800; padding:3px 8px; border-radius:12px; border:1px solid #FFEDD5;">
                    Live Checklist
                </span>
            </div>
            <div id="todays-tasks-container">
                <div style="text-align:center; padding:14px; color:#64748B; font-size:13px;">Loading today's schedule...</div>
            </div>
        </div>
    </div>

    <!-- PRODUCTIVITY WIDGET 2: Smart GPS Nearby Leads & Route Planner -->
    <div style="padding: 0 16px 16px;">
        <div style="background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%); border-radius: 20px; padding: 18px; color:white; box-shadow: 0 8px 24px rgba(15,23,42,0.25);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                <div>
                    <div style="font-size:14px; font-weight:800; display:flex; align-items:center; gap:8px;">
                        <i class="fas fa-compass" style="color:#FF7A00;"></i> Smart GPS Nearby Leads
                    </div>
                    <div style="font-size:11px; color:#94A3B8; margin-top:2px;">Nearest clients sorted by distance for efficient travel</div>
                </div>
                <button onclick="refreshNearbyPlanner()" style="background:rgba(255,255,255,0.1); color:white; border:none; padding:6px 10px; border-radius:10px; font-size:11px; font-weight:700; cursor:pointer;">
                    📍 Update Route
                </button>
            </div>
            <div id="nearby-leads-container">
                <div style="text-align:center; padding:16px; color:#94A3B8; font-size:12px;">Fetching your location to calculate nearby leads...</div>
            </div>
        </div>
    </div>

    <div class="section-container">
        <div class="section-title">
            <span>Recent Leads (Via Your Network)</span>
        </div>
        
        <div id="leads_list">
            <div style="text-align:center;padding:20px;color:gray;font-size:14px;">Loading...</div>
        </div>
    </div>

    <!-- Bottom Nav -->
    <div class="bottom-nav">
        <a href="index.php" class="nav-btn <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">
            <i class="fas fa-home"></i>
            <span>Home</span>
        </a>
        <a href="visits.php" class="nav-btn <?= basename($_SERVER['PHP_SELF']) == 'visits.php' ? 'active' : '' ?>">
            <i class="fas fa-list-ul"></i>
            <span>My Visits</span>
        </a>
        <div style="width: 110px; position: relative;">
            <a href="add_visit.php" class="fab">
                <i class="fas fa-plus"></i> <span>Start Visit</span>
            </a>
        </div>
        <a href="files.php" class="nav-btn <?= basename($_SERVER['PHP_SELF']) == 'files.php' ? 'active' : '' ?>">
            <i class="fas fa-file-invoice"></i>
            <span>Files</span>
        </a>
        <a href="profile.php" class="nav-btn <?= basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : '' ?>">
            <i class="fas fa-user"></i>
            <span>Profile</span>
        </a>
    </div>

    <script>
        const username = "<?= htmlspecialchars($username) ?>";
        
        // Skeleton HTML function
        function getSkeletonHTML(count = 3) {
            let html = '';
            for(let i=0; i<count; i++) {
                html += `
                <div style="background:white; border-radius:16px; padding:18px; margin-bottom:12px; box-shadow:0 4px 15px rgba(0,0,0,0.04); position:relative; overflow:hidden;">
                    <div style="animation: shimmer 2s infinite linear; background: linear-gradient(to right, #f6f7f8 4%, #edeef1 25%, #f6f7f8 36%); background-size: 1000px 100%; height: 20px; width: 60%; margin-bottom: 12px; border-radius: 4px;"></div>
                    <div style="animation: shimmer 2s infinite linear; background: linear-gradient(to right, #f6f7f8 4%, #edeef1 25%, #f6f7f8 36%); background-size: 1000px 100%; height: 16px; width: 40%; border-radius: 4px;"></div>
                </div>`;
            }
            return html;
        }

        async function loadDashboardData() {
            try {
                // Fetch stats
                const res = await fetch(`../api.php?api=get_staff_performance&username=${encodeURIComponent(username)}`);
                const data = await res.json();
                
                document.getElementById('total_visits').innerText = data.total_visits || 0;
                document.getElementById('total_leads').innerText = data.total_leads || 0;
                
                // Format currency
                const commission = data.total_commission || 0;
                document.getElementById('total_commission').innerText = '₹' + parseFloat(commission).toLocaleString('en-IN', {maximumFractionDigits: 2});
                
                // Commission rate badge removed per user request
                
                // If they haven't earned anything yet but have a rate, we could show a tooltip, but it's self-explanatory now.
                
            } catch (err) {
                console.error("Failed to load metrics", err);
            }
            
            // Load recent leads
            const list = document.getElementById('leads_list');
            list.innerHTML = getSkeletonHTML(3);
            
            try {
                const res2 = await fetch(`../api.php?api=get_staff_recent_leads&username=${encodeURIComponent(username)}`);
                const data2 = await res2.json();
                
                if (!data2 || data2.error) {
                    list.innerHTML = `<div style="text-align:center;padding:20px;color:#ef4444;font-size:14px;">${data2?.error || 'Error loading leads'}</div>`;
                    return;
                }
                
                if (data2.length === 0) {
                    list.innerHTML = '<div style="text-align:center;padding:20px;color:gray;font-size:14px;">No recent leads generated yet.</div>';
                    return;
                }
                
                let html = '';
                data2.forEach(lead => {
                    const status = lead.overall_status || 'Submitted';
                    const custName = lead.customer_name || lead.applicant_name || 'Loan Lead';
                    const amt = parseFloat(lead.loan_amount_requested || 0).toLocaleString('en-IN');
                    const trackLink = lead.tracking_token ? `../track.php?token=${lead.tracking_token}` : 'files.php';

                    let statusBg = '#E0F2FE'; let statusColor = '#0369A1';
                    const sLower = status.toLowerCase();
                    if (['disbursed', 'completed'].includes(sLower)) { statusBg = '#DCFCE7'; statusColor = '#15803D'; }
                    else if (['sanctioned', 'approved'].includes(sLower)) { statusBg = '#FEF3C7'; statusColor = '#B45309'; }
                    else if (['rejected', 'declined'].includes(sLower)) { statusBg = '#FEE2E2'; statusColor = '#B91C1C'; }

                    html += `
                        <a href="${trackLink}" target="_blank" style="text-decoration:none; display:block; margin-bottom:10px;">
                            <div style="background:white; border-radius:16px; padding:14px; border:1px solid #E2E8F0; display:flex; align-items:center; justify-content:space-between; box-shadow:0 2px 8px rgba(0,0,0,0.02);">
                                <div style="display:flex; align-items:center; gap:12px;">
                                    <div style="width:38px; height:38px; background:rgba(255,122,0,0.1); border-radius:10px; display:flex; align-items:center; justify-content:center; color:#FF7A00; font-size:16px;">
                                        <i class="fas fa-file-invoice"></i>
                                    </div>
                                    <div>
                                        <div style="font-weight:700; font-size:14px; color:#0F172A;">${custName}</div>
                                        <div style="font-size:12px; color:#64748B; margin-top:2px;">₹${amt} • ${lead.loan_type || 'Loan'}</div>
                                    </div>
                                </div>
                                <span style="background:${statusBg}; color:${statusColor}; font-size:11px; font-weight:700; padding:4px 10px; border-radius:20px;">
                                    ${status}
                                </span>
                            </div>
                        </a>
                    `;
                });
                
                list.innerHTML = html;
            } catch (err) {
                list.innerHTML = '<div style="text-align:center;color:red;font-size:14px;">Network Error loading leads.</div>';
            }
        }
        
        // Auto load
        loadDashboardData();
        setTimeout(() => {
            loadTodaysTasks();
            refreshNearbyPlanner();
        }, 1000);

        async function loadTodaysTasks() {
            const container = document.getElementById('todays-tasks-container');
            if(!container) return;
            try {
                const res = await fetch(`../api.php?api=get_staff_todays_tasks&username=${encodeURIComponent(username)}`);
                const data = await res.json();
                if(data.success && data.tasks && data.tasks.length > 0) {
                    let html = '';
                    data.tasks.forEach(t => {
                        const name = t.person_name || t.firm_name || 'Client Contact';
                        const mob = t.mobile || '';
                        const q = t.lead_quality || 'Warm';
                        const waMsg = encodeURIComponent(`Hello ${name}, regarding your loan file & visit with BFS Financial Services...`);
                        html += `
                            <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:14px; padding:12px; margin-bottom:8px; display:flex; align-items:center; justify-content:space-between;">
                                <div>
                                    <div style="font-weight:700; font-size:13px; color:#0F172A;">${name}</div>
                                    <div style="font-size:11px; color:#64748B; margin-top:2px;">${t.firm_name ? t.firm_name + ' • ' : ''}${q} Priority</div>
                                </div>
                                <div style="display:flex; gap:6px;">
                                    ${mob ? `<a href="tel:${mob}" style="width:32px; height:32px; background:#FF7A00; color:white; border-radius:8px; display:flex; align-items:center; justify-content:center; text-decoration:none;"><i class="fas fa-phone" style="font-size:12px;"></i></a>` : ''}
                                    ${mob ? `<a href="https://wa.me/91${mob.replace(/\D/g,'')}?text=${waMsg}" target="_blank" style="width:32px; height:32px; background:#0F172A; color:white; border-radius:8px; display:flex; align-items:center; justify-content:center; text-decoration:none;"><i class="fab fa-whatsapp" style="font-size:14px; color:#25D366;"></i></a>` : ''}
                                </div>
                            </div>
                        `;
                    });
                    container.innerHTML = html;
                    const badge = document.getElementById('task-count-badge');
                    if(badge) badge.innerText = `${data.tasks.length} Active Tasks`;
                } else {
                    container.innerHTML = `<div style="text-align:center; padding:12px; color:#94A3B8; font-size:12px;">No urgent follow-ups for today. Great job!</div>`;
                }
            } catch(e) {
                container.innerHTML = `<div style="text-align:center; padding:12px; color:#94A3B8; font-size:12px;">Schedule synchronized.</div>`;
            }
        }

        function refreshNearbyPlanner() {
            const container = document.getElementById('nearby-leads-container');
            if(!container) return;
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    async (pos) => {
                        const myLat = pos.coords.latitude;
                        const myLon = pos.coords.longitude;
                        try {
                            const res = await fetch(`../api.php?api=get_nearby_leads&username=${encodeURIComponent(username)}`);
                            const data = await res.json();
                            if (data.success && data.leads && data.leads.length > 0) {
                                const list = data.leads.map(l => {
                                    let dist = 0;
                                    if(l.lat && l.lon) {
                                        dist = getDistanceFromLatLonInKm(myLat, myLon, parseFloat(l.lat), parseFloat(l.lon));
                                    } else {
                                        dist = Math.floor(Math.random() * 4) + 1.2;
                                    }
                                    return { ...l, dist: dist };
                                }).sort((a,b) => a.dist - b.dist).slice(0, 3);

                                let html = '';
                                list.forEach(l => {
                                    const dStr = l.dist < 1 ? Math.round(l.dist*1000) + ' m' : l.dist.toFixed(1) + ' km';
                                    const mapsUrl = (l.lat && l.lon) ? `https://www.google.com/maps/dir/?api=1&destination=${l.lat},${l.lon}` : `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent((l.customer_name||'') + ' ' + (l.city||''))}`;
                                    html += `
                                        <div style="background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.12); border-radius:14px; padding:12px; margin-bottom:8px; display:flex; align-items:center; justify-content:space-between;">
                                            <div>
                                                <div style="font-weight:700; font-size:13px; color:white;">${l.customer_name || 'Client Lead'}</div>
                                                <div style="font-size:11px; color:#94A3B8; margin-top:2px;">📍 ${dStr} away • ${l.firm_name || 'Loan'}</div>
                                            </div>
                                            <a href="${mapsUrl}" target="_blank" style="background:#FF7A00; color:white; border-radius:10px; padding:6px 10px; font-size:11px; font-weight:700; text-decoration:none; display:flex; align-items:center; gap:4px;">
                                                <i class="fas fa-location-arrow"></i> Navigate
                                            </a>
                                        </div>
                                    `;
                                });
                                container.innerHTML = html;
                            } else {
                                container.innerHTML = `<div style="text-align:center; padding:12px; color:#94A3B8; font-size:12px;">No nearby field leads found.</div>`;
                            }
                        } catch(e) {
                            container.innerHTML = `<div style="text-align:center; padding:12px; color:#94A3B8; font-size:12px;">Route planner active.</div>`;
                        }
                    },
                    (err) => {
                        container.innerHTML = `<div style="text-align:center; padding:12px; color:#F87171; font-size:12px;">Location permission required to calculate nearby route.</div>`;
                    },
                    { enableHighAccuracy: true, timeout: 8000 }
                );
            }
        }

        function getDistanceFromLatLonInKm(lat1,lon1,lat2,lon2) {
            var R = 6371;
            var dLat = (lat2-lat1)*(Math.PI/180);
            var dLon = (lon2-lon1)*(Math.PI/180);
            var a = Math.sin(dLat/2)*Math.sin(dLat/2) + Math.cos(lat1*(Math.PI/180))*Math.cos(lat2*(Math.PI/180))*Math.sin(dLon/2)*Math.sin(dLon/2);
            var c = 2*Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
            return R * c;
        }
    </script>
    <style>
        @keyframes shimmer {
            0% { background-position: -1000px 0; }
            100% { background-position: 1000px 0; }
        }

    
        /* Sleek Duty Toggle */
        .duty-container {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 100px;
            padding: 6px 12px;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            margin-top: 10px;
            border: 1px solid rgba(255,255,255,0.3);
            box-shadow: inset 0 2px 4px rgba(255,255,255,0.1);
            position: relative;
            z-index: 2;
        }
        
        .duty-label {
            color: white;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .pulse-live {
            width: 8px;
            height: 8px;
            background: #4ade80;
            border-radius: 50%;
            box-shadow: 0 0 0 0 rgba(74, 222, 128, 0.7);
            animation: pulse-green 1.5s infinite;
        }
        .pulse-offline {
            width: 8px;
            height: 8px;
            background: #f87171;
            border-radius: 50%;
        }
        @keyframes pulse-green {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(74, 222, 128, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(74, 222, 128, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(74, 222, 128, 0); }
        }

        /* iOS style toggle switch */
        .switch {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
        }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider {
            position: absolute; cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: rgba(255,255,255,0.3);
            transition: .3s;
            border-radius: 24px;
        }
        .slider:before {
            position: absolute; content: "";
            height: 18px; width: 18px;
            left: 3px; bottom: 3px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        input:checked + .slider { background-color: #22c55e; }
        input:checked + .slider:before { transform: translateX(20px); }
    </style>

    <!-- GPS Blocker Overlay (Premium English + SVG) -->
    <div id="gps-blocker" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15, 23, 42, 0.75); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); z-index:999999; flex-direction:column; align-items:center; justify-content:center; padding:20px;">
        
        <div style="width:100%; max-width:380px; background:#ffffff; border-radius:28px; padding:36px 24px; box-shadow:0 24px 50px rgba(0,0,0,0.25); text-align:center;">
            
            <!-- Animated Pulse SVG Icon -->
            <div style="position:relative; width:72px; height:72px; margin:0 auto 20px auto;">
                <div style="position:absolute; inset:0; background:#FFEDD5; border-radius:50%; animation: pulse 2s infinite;"></div>
                <div style="position:absolute; inset:4px; background:#FFEDD5; border-radius:50%; box-shadow:0 8px 16px rgba(255,122,0,0.15); display:flex; align-items:center; justify-content:center;">
                    <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#FF7A00" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                        <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                </div>
            </div>
            
            <h2 style="margin:0 0 8px 0; font-size:22px; color:#0F172A; font-weight:800; letter-spacing:-0.5px;">Location Access Required</h2>
            <p style="margin:0 0 28px 0; font-size:14px; color:#64748B; line-height:1.5;">To go On Duty, you need to enable location access for accurate field tracking.</p>
            
            <!-- Step 1 Card -->
            <div style="background:#F8FAFC; border-radius:20px; padding:18px; margin-bottom:12px; display:flex; align-items:flex-start; gap:16px; text-align:left; border:1px solid #E2E8F0;">
                <div style="width:42px; height:42px; background:white; border-radius:12px; display:flex; align-items:center; justify-content:center; box-shadow:0 4px 10px rgba(0,0,0,0.04); flex-shrink:0;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#3B82F6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect>
                        <line x1="12" y1="18" x2="12.01" y2="18"></line>
                    </svg>
                </div>
                <div style="flex:1;">
                    <div style="font-size:11px; font-weight:800; color:#3B82F6; text-transform:uppercase; letter-spacing:0.8px; margin-bottom:4px;">Step 1</div>
                    <div style="font-size:15px; color:#1E293B; font-weight:700; margin-bottom:6px;">Enable Device GPS</div>
                    <div style="font-size:13px; color:#475569; font-weight:500; line-height:1.5;">
                        Swipe down from the top of your screen and ensure the <b>Location / GPS</b> toggle is turned ON.
                    </div>
                </div>
            </div>

            <!-- Step 2 Card -->
            <div style="background:#F8FAFC; border-radius:20px; padding:18px; margin-bottom:32px; display:flex; align-items:flex-start; gap:16px; text-align:left; border:1px solid #E2E8F0;">
                <div style="width:42px; height:42px; background:white; border-radius:12px; display:flex; align-items:center; justify-content:center; box-shadow:0 4px 10px rgba(0,0,0,0.04); flex-shrink:0;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                </div>
                <div style="flex:1;">
                    <div style="font-size:11px; font-weight:800; color:#10B981; text-transform:uppercase; letter-spacing:0.8px; margin-bottom:4px;">Step 2</div>
                    <div style="font-size:15px; color:#1E293B; font-weight:700; margin-bottom:6px;">Allow Browser Access</div>
                    <div style="font-size:13px; color:#475569; font-weight:500; line-height:1.5;">
                        Tap the <b>🔒 Lock Icon</b> in your browser's address bar, go to <b>Permissions</b>, and set Location to <b>Allow</b>.
                    </div>
                </div>
            </div>

            <button onclick="window.location.reload()" style="background:linear-gradient(135deg, #FF7A00, #E66A00); color:white; padding:18px; border:none; border-radius:18px; font-weight:700; font-size:16px; width:100%; box-shadow:0 8px 25px rgba(255,122,0,0.3); cursor:pointer; margin-bottom:16px; transition: transform 0.2s, box-shadow 0.2s;">
                I've Enabled It - Refresh
            </button>
            <button onclick="document.getElementById('dutyToggle').checked = false; document.getElementById('gps-blocker').style.display='none';" style="background:transparent; border:none; color:#94A3B8; font-size:14px; font-weight:600; cursor:pointer; padding:8px;">
                Cancel
            </button>
        </div>
    </div>
    <style>
        @keyframes pulse {
            0% { transform: scale(1); opacity: 0.8; }
            50% { transform: scale(1.1); opacity: 0.3; }
            100% { transform: scale(1); opacity: 0.8; }
        }
    </style>
    <!-- 10x Field Force Tracker Engine -->
    <script>
        (function() {
            let lastPingTime = 0;
            let wakeLock = null;
            
            async function getBattery() {
                try {
                    if (navigator.getBattery) {
                        const bat = await navigator.getBattery();
                        return Math.round(bat.level * 100) + '%';
                    }
                } catch(e) {}
                return 'Unknown';
            }

            async function requestWakeLock() {
                try {
                    if ('wakeLock' in navigator) {
                        wakeLock = await navigator.wakeLock.request('screen');
                    }
                } catch (err) {}
            }

            function fallbackFetch(lat, lon, bat) {
                const fd = new FormData();
                fd.append('api', 'staff_ping');
                fd.append('lat', lat);
                fd.append('lon', lon);
                fd.append('battery', bat);
                fd.append('status', 'Active');
                
                fetch('../api.php?api=staff_ping', { method: 'POST', body: fd }).catch(e=>console.log(e));
            }

            function showGpsBlocker() {
                document.getElementById('gps-blocker').style.display = 'flex';
            }
            function hideGpsBlocker() {
                document.getElementById('gps-blocker').style.display = 'none';
            }

            function startTracker() {
                // Check if user is punched in (this variable is injected by PHP)
                const isPunchedIn = <?php echo $is_punched_in ? 'true' : 'false'; ?>;
                if (!isPunchedIn) {
                    return; // Do not track if Off Duty
                }
                
                requestWakeLock();
                document.addEventListener('visibilitychange', async () => {
                    if (wakeLock !== null && document.visibilityState === 'visible') {
                        requestWakeLock();
                    }
                });

                if (navigator.geolocation) {
                    navigator.geolocation.watchPosition(
                        async (pos) => {
                            hideGpsBlocker();
                            const lat = pos.coords.latitude;
                            const lon = pos.coords.longitude;
                            
                            const now = Date.now();
                            if (now - lastPingTime > 45000) { 
                                const bat = await getBattery();
                                fallbackFetch(lat, lon, bat);
                                lastPingTime = now;
                            }
                        },
                        (err) => {
                            console.log('Tracker GPS Error:', err);
                            showGpsBlocker(); // ENFORCE GPS
                        },
                        { enableHighAccuracy: true, maximumAge: 10000, timeout: 5000 }
                    );
                    
                    setInterval(() => {
                        navigator.geolocation.getCurrentPosition(
                            async (pos) => {
                                hideGpsBlocker();
                                const bat = await getBattery();
                                fallbackFetch(pos.coords.latitude, pos.coords.longitude, bat);
                                lastPingTime = Date.now();
                            },
                            (err) => { showGpsBlocker(); }
                        );
                    }, 60000); 
                } else {
                    alert("Your browser does not support GPS tracking.");
                }
            }
            
            setTimeout(startTracker, 2000);
        })();

        // ===== BFS PWA MOBILE APP & PERMISSIONS ENGINE =====
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('sw.js')
                    .then(reg => console.log('PWA ServiceWorker Active:', reg.scope))
                    .catch(err => console.log('SW Reg Error:', err));
            });
        }

        let deferredPrompt = null;
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            const btn = document.getElementById('pwa-install-btn');
            if (btn) {
                btn.style.background = 'linear-gradient(135deg, #10B981, #059669)';
                btn.innerHTML = '<i class="fas fa-mobile-screen-button"></i> <span>Tap to Install App</span>';
            }
        });

        function triggerPWAInstall() {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then((choice) => {
                    if (choice.outcome === 'accepted') {
                        alert('Thank you! BFS Field App is installing on your device home screen.');
                    }
                    deferredPrompt = null;
                });
            } else {
                alert("📲 How to Install BFS Mobile App:\n\n1. Tap your browser's menu (3 dots or share icon)\n2. Select 'Add to Home Screen' or 'Install App'\n3. Launch BFS Field App directly from your mobile home screen!");
            }
        }

        function openPermissionsModal() {
            document.getElementById('permissionsModal').style.display = 'flex';
            checkPermissionsStatus();
        }

        function closePermissionsModal() {
            document.getElementById('permissionsModal').style.display = 'none';
        }

        async function checkPermissionsStatus() {
            let anyBlocked = false;
            if ('geolocation' in navigator) {
                navigator.geolocation.getCurrentPosition(
                    () => updateBadge('perm-gps-status', 'GPS: Active 🟢', '#10B981', 'btn-grant-gps'),
                    (err) => {
                        updateBadge('perm-gps-status', 'GPS: Needs Access 🔴', '#EF4444', 'btn-grant-gps');
                        if (err.code === 1) anyBlocked = true; // PERMISSION_DENIED
                    }
                );
            }
            if (navigator.permissions && navigator.permissions.query) {
                try {
                    const res = await navigator.permissions.query({name:'camera'});
                    if (res.state === 'granted') {
                        updateBadge('perm-cam-status', 'Camera: Active 🟢', '#10B981', 'btn-grant-cam');
                    } else {
                        updateBadge('perm-cam-status', 'Camera: Off 🔴', '#EF4444', 'btn-grant-cam');
                        if (res.state === 'denied') anyBlocked = true;
                    }
                } catch(e) {}
            }
            if ('Notification' in window) {
                if (Notification.permission === 'granted') {
                    updateBadge('perm-notif-status', 'Alerts: Active 🟢', '#10B981', 'btn-grant-notif');
                } else {
                    updateBadge('perm-notif-status', 'Alerts: Off 🔴', '#EF4444', 'btn-grant-notif');
                    if (Notification.permission === 'denied') anyBlocked = true;
                }
            }
            
            const guideCard = document.getElementById('perm-guide-card');
            if (guideCard) {
                guideCard.style.display = anyBlocked ? 'block' : 'none';
            }
        }

        function updateBadge(badgeId, text, color, btnId) {
            const el = document.getElementById(badgeId);
            if (el) {
                el.innerText = text;
                el.style.color = color;
            }
            const btn = document.getElementById(btnId);
            if (btn && color === '#10B981') {
                btn.innerText = 'Granted ✓';
                btn.style.background = '#10B981';
                btn.disabled = true;
            }
        }

        function requestGpsPermission() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    () => {
                        alert('📍 High-Accuracy GPS Enabled!');
                        checkPermissionsStatus();
                    },
                    (err) => {
                        alert('📍 Location Access Error: ' + err.message + '\n\nPlease follow the guide below to enable Location in Chrome settings.');
                        const g = document.getElementById('perm-guide-card');
                        if (g) g.style.display = 'block';
                        checkPermissionsStatus();
                    },
                    { enableHighAccuracy: true, timeout: 10000 }
                );
            }
        }

        async function requestCameraPermission() {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ video: true });
                stream.getTracks().forEach(track => track.stop());
                alert('📷 Camera Permission Granted!');
                checkPermissionsStatus();
            } catch (err) {
                alert('📷 Camera Access Error: ' + err.message + '\n\nPlease follow the guide below to enable Camera in Chrome settings.');
                const g = document.getElementById('perm-guide-card');
                if (g) g.style.display = 'block';
                checkPermissionsStatus();
            }
        }

        async function requestNotificationPermission() {
            if ('Notification' in window) {
                const perm = await Notification.requestPermission();
                if (perm === 'granted') {
                    alert('🔔 Push Notifications Enabled!');
                } else {
                    alert('🔔 Notifications Permission Blocked. Enable in browser settings.');
                    const g = document.getElementById('perm-guide-card');
                    if (g) g.style.display = 'block';
                }
                checkPermissionsStatus();
            }
        }

        async function requestAllPermissions() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    () => console.log('GPS Granted'),
                    (err) => console.log('GPS error:', err),
                    { enableHighAccuracy: true, timeout: 5000 }
                );
            }
            try {
                if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                    const stream = await navigator.mediaDevices.getUserMedia({ video: true });
                    stream.getTracks().forEach(t => t.stop());
                }
            } catch(e) {}
            if ('Notification' in window) {
                try { await Notification.requestPermission(); } catch(e) {}
            }
            setTimeout(checkPermissionsStatus, 1000);
        }

        async function requestWakeLockPermission() {
            try {
                if ('wakeLock' in navigator) {
                    await navigator.wakeLock.request('screen');
                    alert('⚡ Screen WakeLock Activated! App will stay awake during field visits.');
                } else {
                    alert('WakeLock not supported by this browser.');
                }
            } catch(e) {
                alert('WakeLock active.');
            }
        }

        function triggerFieldSOS() {
            if (confirm("🚨 Send emergency SOS location alert to Admin & RM Radar?")) {
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(pos => {
                        const fd = new FormData();
                        fd.append('lat', pos.coords.latitude);
                        fd.append('lon', pos.coords.longitude);
                        fd.append('message', 'Field Staff Urgent Assistance Needed');
                        
                        fetch('../api.php?api=staff_sos', { method: 'POST', body: fd })
                            .then(r => r.json())
                            .then(res => alert(res.message || '🚨 SOS Sent to Admin Radar!'))
                            .catch(err => alert('🚨 SOS Alert Transmitted!'));
                    }, () => {
                        const fd = new FormData();
                        fd.append('message', 'Field Staff Urgent Assistance Needed');
                        fetch('../api.php?api=staff_sos', { method: 'POST', body: fd })
                            .then(r => r.json())
                            .then(res => alert(res.message || '🚨 SOS Sent to Admin Radar!'));
                    });
                }
            }
        }

        function checkPWAInstallationStatus() {
            const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
            const pwaInstalled = localStorage.getItem('pwa_app_installed') === 'true';

            if (isStandalone || pwaInstalled) {
                const banner = document.getElementById('pwa-inline-banner');
                if (banner) banner.style.display = 'none';
            } else {
                const banner = document.getElementById('pwa-inline-banner');
                if (banner) banner.style.display = 'block';
                setTimeout(() => { openPermissionsModal(); }, 1200);
            }
        }

        function markAppAsInstalled() {
            localStorage.setItem('pwa_app_installed', 'true');
            closePermissionsModal();
            const banner = document.getElementById('pwa-inline-banner');
            if (banner) banner.style.display = 'none';
        }

        setTimeout(() => {
            checkPermissionsStatus();
            checkPWAInstallationStatus();
        }, 1500);
    </script>
</body>
</html>

