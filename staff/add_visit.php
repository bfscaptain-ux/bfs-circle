<?php
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Staff';
$role = $_SESSION['role'] ?? 'Staff';

$stmt = $db->prepare("SELECT department FROM employees WHERE user_id = ?");
$stmt->execute([$user_id]);
$department = $stmt->fetchColumn() ?: 'Lead Generation Team';

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
    <title>My Field App - BFS Financial Services</title>
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
            background: var(--primary);
            color: white;
            padding: 20px 16px;
            border-bottom-left-radius: 20px;
            border-bottom-right-radius: 20px;
            box-shadow: 0 4px 10px rgba(255, 122, 0, 0.2);
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
            margin-top: -30px;
        }

        .metric-card {
            background: white;
            border-radius: 16px;
            padding: 16px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            text-align: center;
        }
        
        .metric-card.full-width {
            grid-column: 1 / -1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            text-align: left;
            background: linear-gradient(135deg, #1E293B 0%, #0F172A 100%);
            color: white;
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

    </style>
</head>
<body>

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
    <!-- Header -->
    <div style="background:var(--primary); color:white; padding:20px 20px 60px 20px; border-bottom-left-radius:30px; border-bottom-right-radius:30px; position:relative;">
        <h2 style="margin:0; font-size:24px; font-weight:700;">Record Visit</h2>
        <p style="margin:5px 0 0 0; font-size:14px; opacity:0.9;">Capture new leads on the go</p>
    </div>

    <!-- Main Content Form -->
    <div style="padding:0 20px 100px 20px; margin-top:-40px; position:relative; z-index:10;">
        <div id="alert_box" style="display:none; padding:15px; border-radius:12px; margin-bottom:15px; font-weight:500; font-size:14px; text-align:center;"></div>
        
        <form id="fieldVisitForm" onsubmit="submitForm(event)">
            <input type="hidden" id="check_in_time" name="check_in_time">
            <input type="hidden" id="check_out_time" name="check_out_time">
            
            <!-- Smart Check-in System UI -->
            <div id="smart_checkin_container" style="background:white; border-radius:24px; padding:30px 20px; box-shadow:0 12px 30px rgba(0,0,0,0.08); text-align:center; margin-bottom: 20px; border:1px solid #f1f5f9;">
                <div style="width:80px; height:80px; background:#fff3e0; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 20px;">
                    <i class="fas fa-handshake" style="font-size:32px; color:var(--primary);"></i>
                </div>
                <h2 style="font-size:20px; color:#1e293b; margin-bottom:8px;">Start Client Visit</h2>
                <p style="font-size:14px; color:#64748b; margin-bottom:30px;">Check-in securely to start recording your meeting time.</p>
                
                <button type="button" id="start_visit_btn" onclick="startCheckIn()" style="background:linear-gradient(135deg, #FF6B00 0%, #FF9A44 100%); color:white; border:none; width:100%; padding:18px; border-radius:16px; font-size:16px; font-weight:700; box-shadow:0 8px 20px rgba(255, 107, 0, 0.3); cursor:pointer;">
                    <i class="fas fa-sign-in-alt" style="margin-right:8px;"></i> Check-in & Start Visit
                </button>
                
                <div id="meeting_in_progress" style="display:none;">
                    <div style="font-size:13px; color:#10b981; font-weight:600; margin-bottom:8px; display:flex; align-items:center; justify-content:center; gap:6px;">
                        <span style="width:8px; height:8px; background:#10b981; border-radius:50%; display:inline-block; animation: pulse 1.5s infinite;"></span> Meeting in progress...
                    </div>
                    
                    <!-- Voice Recording Indicator -->
                    <div style="margin-bottom:15px; display:flex; align-items:center; justify-content:center; gap:8px; color:#ef4444; font-weight:600; font-size:14px;">
                        <i class="fas fa-microphone" style="animation: pulse 1s infinite;"></i> <span id="recording_status">Recording meeting audio...</span>
                    </div>

                    <div id="visit_timer" style="font-size:36px; font-weight:800; color:#0f172a; margin-bottom:24px; font-variant-numeric: tabular-nums;">00:00:00</div>
                    <button type="button" onclick="endCheckIn()" style="background:#0f172a; color:white; border:none; width:100%; padding:18px; border-radius:16px; font-size:16px; font-weight:700; box-shadow:0 8px 20px rgba(15, 23, 42, 0.2); cursor:pointer;">
                        <i class="fas fa-sign-out-alt" style="margin-right:8px;"></i> End Visit & Fill Form
                    </button>
                </div>
            </div>
            
            <style>
                @keyframes pulse {
                    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
                    70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
                    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
                }
            </style>

            <div id="main_form_container" style="display:none;">
            <!-- Hidden Fields -->
            <input type="hidden" id="visit_date" name="visit_date">
            <input type="hidden" id="executive_name" name="executive_name" value="<?= htmlspecialchars($_SESSION['username'] ?? 'Staff') ?>">

            <!-- Card 1: Basic Details -->
            <div style="background:white; border-radius:20px; padding:20px; box-shadow:0 8px 24px rgba(0,0,0,0.06); margin-bottom:20px;">
                <h3 style="margin:0 0 15px 0; font-size:16px; color:#1e293b; border-bottom:1px solid #f1f5f9; padding-bottom:10px;">
                    <i class="fas fa-user-tie" style="color:var(--primary); margin-right:8px;"></i> Person Details
                </h3>
                
                <div style="margin-bottom:15px;">
                    <label style="display:block; font-size:13px; color:#64748b; margin-bottom:5px; font-weight:500;">Full Name *</label>
                    <input type="text" id="person_name" name="person_name" placeholder="Enter person name" required style="width:100%; padding:12px 15px; border:1px solid #e2e8f0; border-radius:10px; font-size:15px; outline:none; transition:border 0.2s;">
                </div>
                
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:15px;">
                    <div>
                        <label style="display:block; font-size:13px; color:#64748b; margin-bottom:5px; font-weight:500;">Mobile *</label>
                        <input type="tel" id="mobile" name="mobile" placeholder="10-digits" pattern="[0-9]{10}" required style="width:100%; padding:12px 15px; border:1px solid #e2e8f0; border-radius:10px; font-size:15px; outline:none; transition:border 0.2s;">
                    </div>
                    <div>
                        <label style="display:block; font-size:13px; color:#64748b; margin-bottom:5px; font-weight:500;">Alt Mobile</label>
                        <input type="tel" id="alt_mobile" name="alt_mobile" placeholder="Optional" pattern="[0-9]{10}" style="width:100%; padding:12px 15px; border:1px solid #e2e8f0; border-radius:10px; font-size:15px; outline:none; transition:border 0.2s;">
                    </div>
                </div>

                <div style="margin-bottom:15px;">
                    <label style="display:block; font-size:13px; color:#64748b; margin-bottom:5px; font-weight:500;">Profession *</label>
                    <div style="position:relative;">
                        <select id="profession" name="profession" onchange="toggleCustomProfession()" required style="width:100%; padding:12px 15px; border:1px solid #e2e8f0; border-radius:10px; font-size:15px; appearance:none; outline:none; background:#fff;">
                            <option value="">Select Profession</option>
                            <option value="CA">CA</option>
                            <option value="PROPERTY DEALER">Property Dealer</option>
                            <option value="ARCHITECT">Architect</option>
                            <option value="CONTRACTOR">Contractor</option>
                            <option value="BUSINESS OWNER">Business Owner</option>
                            <option value="OTHER">Other</option>
                        </select>
                        <i class="fas fa-chevron-down" style="position:absolute; right:15px; top:15px; color:#94a3b8; pointer-events:none;"></i>
                    </div>
                </div>

                <div id="custom_profession_group" style="display:none; margin-bottom:15px;">
                    <label style="display:block; font-size:13px; color:#64748b; margin-bottom:5px; font-weight:500;">Specify Profession</label>
                    <input type="text" id="custom_profession" name="custom_profession" placeholder="Type here..." style="width:100%; padding:12px 15px; border:1px solid #e2e8f0; border-radius:10px; font-size:15px; outline:none;">
                </div>
            </div>

            <!-- Card 2: Business & Location -->
            <div style="background:white; border-radius:20px; padding:20px; box-shadow:0 8px 24px rgba(0,0,0,0.06); margin-bottom:20px;">
                <h3 style="margin:0 0 15px 0; font-size:16px; color:#1e293b; border-bottom:1px solid #f1f5f9; padding-bottom:10px;">
                    <i class="fas fa-building" style="color:var(--primary); margin-right:8px;"></i> Business Info
                </h3>
                
                <div style="margin-bottom:15px;">
                    <label style="display:block; font-size:13px; color:#64748b; margin-bottom:5px; font-weight:500;">Firm/Company Name *</label>
                    <input type="text" id="firm_name" name="firm_name" placeholder="E.g. Sharma Associates" required style="width:100%; padding:12px 15px; border:1px solid #e2e8f0; border-radius:10px; font-size:15px; outline:none;">
                </div>
                
                <!-- Changed to 1fr instead of 1fr 1fr to fix dropdown UI overflow -->
                <div style="display:grid; grid-template-columns:1fr; gap:15px; margin-bottom:15px;">
                    <div>
                        <label style="display:block; font-size:13px; color:#64748b; margin-bottom:5px; font-weight:500;">State *</label>
                        <div style="position:relative;">
                            <!-- Added max-width and text-overflow for safety -->
                            <select id="state" name="state" required onchange="populateCities()" style="width:100%; max-width:100%; text-overflow:ellipsis; padding:12px 15px; border:1px solid #e2e8f0; border-radius:10px; font-size:15px; appearance:none; outline:none; background:#fff;">
                                <option value="">Select State</option>
                            </select>
                            <i class="fas fa-chevron-down" style="position:absolute; right:15px; top:15px; color:#94a3b8; pointer-events:none;"></i>
                        </div>
                    </div>
                    <div>
                        <label style="display:block; font-size:13px; color:#64748b; margin-bottom:5px; font-weight:500;">City *</label>
                        <div style="position:relative;">
                            <select id="city" name="city" required style="width:100%; max-width:100%; text-overflow:ellipsis; padding:12px 15px; border:1px solid #e2e8f0; border-radius:10px; font-size:15px; appearance:none; outline:none; background:#fff;">
                                <option value="">Select City</option>
                            </select>
                            <i class="fas fa-chevron-down" style="position:absolute; right:15px; top:15px; color:#94a3b8; pointer-events:none;"></i>
                        </div>
                    </div>
                </div>

                <div style="margin-bottom:15px;">
                    <label style="display:block; font-size:13px; color:#64748b; margin-bottom:5px; font-weight:500;">Full Address</label>
                    <textarea id="full_address" name="full_address" rows="2" placeholder="Complete address details..." style="width:100%; padding:12px 15px; border:1px solid #e2e8f0; border-radius:10px; font-size:15px; outline:none; resize:none; font-family:inherit;"></textarea>
                </div>
                
                <div style="margin-bottom:15px;">
                    <label style="display:block; font-size:13px; color:#64748b; margin-bottom:5px; font-weight:500;">Pincode</label>
                    <input type="text" id="pincode" name="pincode" placeholder="6-digit PIN" pattern="[0-9]{6}" style="width:100%; padding:12px 15px; border:1px solid #e2e8f0; border-radius:10px; font-size:15px; outline:none;">
                </div>
            </div>

            <!-- Card 3: Lead Status & Upload -->
            <div style="background:white; border-radius:20px; padding:20px; box-shadow:0 8px 24px rgba(0,0,0,0.06); margin-bottom:20px;">
                <h3 style="margin:0 0 15px 0; font-size:16px; color:#1e293b; border-bottom:1px solid #f1f5f9; padding-bottom:10px;">
                    <i class="fas fa-star" style="color:var(--primary); margin-right:8px;"></i> Visit Outcome
                </h3>
                
                <div style="margin-bottom:15px;">
                    <label style="display:block; font-size:13px; color:#64748b; margin-bottom:5px; font-weight:500;">Lead Quality *</label>
                    <div style="display:flex; gap:10px;">
                        <label style="flex:1; position:relative; cursor:pointer;">
                            <input type="radio" name="lead_quality" value="Hot" required style="position:absolute; opacity:0; width:0; height:0;">
                            <div class="quality-card" style="text-align:center; padding:12px 5px; border:1px solid #e2e8f0; border-radius:10px; font-size:13px; font-weight:600; color:#ef4444; transition:all 0.2s;">
                                🔥 Hot
                            </div>
                        </label>
                        <label style="flex:1; position:relative; cursor:pointer;">
                            <input type="radio" name="lead_quality" value="Warm" required style="position:absolute; opacity:0; width:0; height:0;">
                            <div class="quality-card" style="text-align:center; padding:12px 5px; border:1px solid #e2e8f0; border-radius:10px; font-size:13px; font-weight:600; color:#f59e0b; transition:all 0.2s;">
                                ☀️ Warm
                            </div>
                        </label>
                        <label style="flex:1; position:relative; cursor:pointer;">
                            <input type="radio" name="lead_quality" value="Cold" required style="position:absolute; opacity:0; width:0; height:0;">
                            <div class="quality-card" style="text-align:center; padding:12px 5px; border:1px solid #e2e8f0; border-radius:10px; font-size:13px; font-weight:600; color:#3b82f6; transition:all 0.2s;">
                                ❄️ Cold
                            </div>
                        </label>
                    </div>
                    <style>
                        input[type="radio"]:checked + .quality-card {
                            background: #f8fafc;
                            border-color: currentColor !important;
                            box-shadow: 0 0 0 2px currentColor;
                        }
                    </style>
                </div>
                
                <div style="margin-bottom:15px;">
                    <label style="display:block; font-size:13px; color:#64748b; margin-bottom:5px; font-weight:500;">Remarks / Follow-up Details *</label>
                    <textarea id="remarks" name="remarks" rows="3" placeholder="What was discussed?" required style="width:100%; padding:12px 15px; border:1px solid #e2e8f0; border-radius:10px; font-size:15px; outline:none; resize:none; font-family:inherit;"></textarea>
                </div>
                
                <div style="margin-bottom:20px;">
                    <label style="display:block; font-size:13px; color:#64748b; margin-bottom:5px; font-weight:500;">Next Meeting Date</label>
                    <input type="date" id="next_meeting_date" name="next_meeting_date" style="width:100%; padding:12px 15px; border:1px solid #e2e8f0; border-radius:10px; font-size:15px; outline:none; background:#fff;">
                </div>

                <div style="margin-bottom:15px;">
                    <label style="display:block; font-size:13px; color:#64748b; margin-bottom:5px; font-weight:500;">Location / Card Photo</label>
                    <div style="border:2px dashed #cbd5e1; border-radius:12px; padding:20px; text-align:center; background:#f8fafc; cursor:pointer;" onclick="document.getElementById('photo_input').click()">
                        <i class="fas fa-camera" style="font-size:28px; color:var(--primary); margin-bottom:10px;"></i>
                        <div style="font-size:14px; font-weight:600; color:#1e293b;">Tap to Upload Photo</div>
                        <div style="font-size:12px; color:#94a3b8; margin-top:5px;">Capture shop front or visiting card</div>
                    </div>
                    <input type="file" id="photo_input" name="photo" accept="image/*" capture="environment" onchange="previewImage(this)" style="display:none;">
                    <div id="preview_container" style="display:none; margin-top:15px; border-radius:10px; overflow:hidden; border:1px solid #e2e8f0;">
                        <img id="image_preview" src="#" style="width:100%; display:block;">
                    </div>
                </div>

                <!-- Card 4: Meeting Voice Note Recording -->
                <div style="margin-top:20px; margin-bottom:15px;">
                    <label style="display:block; font-size:13px; color:#64748b; margin-bottom:6px; font-weight:600;">
                        <i class="fas fa-microphone" style="color:#ef4444; margin-right:4px;"></i> Meeting Voice Note / Audio Recording
                    </label>
                    <div id="voice_note_box" style="border:1.5px solid #fed7aa; border-radius:14px; padding:16px; background:#fffaf5; text-align:center;">
                        
                        <!-- Idle State -->
                        <div id="voice_idle_state">
                            <button type="button" id="record_voice_btn" onclick="toggleManualVoiceRecord()" style="background:#EF4444; color:white; border:none; padding:12px 20px; border-radius:30px; font-size:14px; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:8px; box-shadow:0 4px 12px rgba(239,68,68,0.3);">
                                <i class="fas fa-microphone"></i> Tap to Record Voice Note
                            </button>
                            <div style="font-size:11.5px; color:#94a3b8; margin-top:8px;">Record key discussion points or client voice note</div>
                        </div>

                        <!-- Recording State -->
                        <div id="voice_recording_state" style="display:none;">
                            <div style="color:#ef4444; font-weight:700; font-size:14px; margin-bottom:10px; display:flex; align-items:center; justify-content:center; gap:8px;">
                                <span style="width:10px; height:10px; background:#ef4444; border-radius:50%; display:inline-block; animation: pulse 1s infinite;"></span>
                                Recording Voice Note... <span id="manual_voice_timer" style="font-family:monospace; font-size:16px;">00:00</span>
                            </div>
                            <button type="button" onclick="stopManualVoiceRecord()" style="background:#0F172A; color:white; border:none; padding:10px 18px; border-radius:24px; font-size:13px; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:6px;">
                                <i class="fas fa-stop-circle" style="color:#ef4444;"></i> Stop Recording
                            </button>
                        </div>

                        <!-- Recorded State (Audio Preview) -->
                        <div id="voice_preview_state" style="display:none;">
                            <div style="font-size:12px; font-weight:700; color:#0f172a; margin-bottom:8px; display:flex; justify-content:space-between; align-items:center;">
                                <span style="color:#16a34a;"><i class="fas fa-check-circle"></i> Voice Note Recorded</span>
                                <button type="button" onclick="deleteVoiceRecord()" style="background:none; border:none; color:#ef4444; font-size:11px; font-weight:700; cursor:pointer;">
                                    <i class="fas fa-trash"></i> Delete & Re-record
                                </button>
                            </div>
                            <audio id="manual_audio_preview" controls style="width:100%; height:38px; outline:none; border-radius:8px;"></audio>
                        </div>

                    </div>
                </div>
            </div>

            <input type="hidden" name="latitude" id="lat"><input type="hidden" name="longitude" id="lon"><input type="hidden" name="verified_address" id="v_addr">
                        <button type="submit" id="submitBtn" style="width:100%; background:var(--primary); color:white; border:none; padding:16px; border-radius:14px; font-size:16px; font-weight:700; display:flex; align-items:center; justify-content:center; gap:10px; box-shadow:0 4px 12px rgba(255,122,0,0.3); transition:transform 0.1s;">
                <i class="fas fa-paper-plane"></i> Submit Record
            </button>
            </div> <!-- End main_form_container -->
        </form>
    </div>

    <script>
        const indiaData = {
            "Andhra Pradesh": ["Anantapur","Chittoor","East Godavari","Guntur","Krishna","Kurnool","Prakasam","Srikakulam","Sri Potti Sriramulu Nellore","Visakhapatnam","Vizianagaram","West Godavari","YSR District, Kadapa (Cuddapah)"],
            "Arunachal Pradesh": ["Anjaw","Changlang","Dibang Valley","East Kameng","East Siang","Kra Daadi","Kurung Kumey","Lohit","Longding","Lower Dibang Valley","Lower Subansiri","Namsai","Papum Pare","Siang","Tawang","Tirap","Upper Siang","Upper Subansiri","West Kameng","West Siang"],
            "Assam": ["Baksa","Barpeta","Biswanath","Bongaigaon","Cachar","Charaideo","Chirang","Darrang","Dhemaji","Dhubri","Dibrugarh","Dima Hasao (North Cachar Hills)","Goalpara","Golaghat","Hailakandi","Hojai","Jorhat","Kamrup","Kamrup Metropolitan","Karbi Anglong","Karimganj","Kokrajhar","Lakhimpur","Majuli","Morigaon","Nagaon","Nalbari","Sivasagar","Sonitpur","South Salmara-Mankachar","Tinsukia","Udalguri","West Karbi Anglong"],
            "Bihar": ["Araria","Arwal","Aurangabad","Banka","Begusarai","Bhagalpur","Bhojpur","Buxar","Darbhanga","East Champaran (Motihari)","Gaya","Gopalganj","Jamui","Jehanabad","Kaimur (Bhabua)","Katihar","Khagaria","Kishanganj","Lakhisarai","Madhepura","Madhubani","Munger (Monghyr)","Muzaffarpur","Nalanda","Nawada","Patna","Purnia (Purnea)","Rohtas","Saharsa","Samastipur","Saran","Sheikhpura","Sheohar","Sitamarhi","Siwan","Supaul","Vaishali","West Champaran"],
            "Chandigarh (UT)": ["Chandigarh"],
            "Chhattisgarh": ["Balod","Baloda Bazar","Balrampur","Bastar","Bemetara","Bijapur","Bilaspur","Dantewada (South Bastar)","Dhamtari","Durg","Gariyaband","Janjgir-Champa","Jashpur","Kabirdham (Kawardha)","Kanker (North Bastar)","Kondagaon","Korba","Korea (Koriya)","Mahasamund","Mungeli","Narayanpur","Raigarh","Raipur","Rajnandgaon","Sukma","Surajpur  ","Surguja"],
            "Dadra and Nagar Haveli (UT)": ["Dadra & Nagar Haveli"],
            "Daman and Diu (UT)": ["Daman","Diu"],
            "Delhi (NCT)": ["Central Delhi","East Delhi","New Delhi","North Delhi","North East Delhi","North West Delhi","Shahdara","South Delhi","South East Delhi","South West Delhi","West Delhi"],
            "Goa": ["North Goa","South Goa"],
            "Gujarat": ["Ahmedabad","Amreli","Anand","Aravalli","Banaskantha (Palanpur)","Bharuch","Bhavnagar","Botad","Chhota Udepur","Dahod","Dangs (Ahwa)","Devbhoomi Dwarka","Gandhinagar","Gir Somnath","Jamnagar","Junagadh","Kachchh","Kheda (Nadiad)","Mahisagar","Mehsana","Morbi","Narmada (Rajpipla)","Navsari","Panchmahal (Godhra)","Patan","Porbandar","Rajkot","Sabarkantha (Himmatnagar)","Surat","Surendranagar","Tapi (Vyara)","Vadodara","Valsad"],
            "Haryana": ["Ambala","Bhiwani","Charkhi Dadri","Faridabad","Fatehabad","Gurgaon","Hisar","Jhajjar","Jind","Kaithal","Karnal","Kurukshetra","Mahendragarh","Mewat","Palwal","Panchkula","Panipat","Rewari","Rohtak","Sirsa","Sonipat","Yamunanagar"],
            "Himachal Pradesh": ["Bilaspur","Chamba","Hamirpur","Kangra","Kinnaur","Kullu","Lahaul & Spiti","Mandi","Shimla","Sirmaur (Sirmour)","Solan","Una"],
            "Jammu and Kashmir": ["Anantnag","Bandipore","Baramulla","Budgam","Doda","Ganderbal","Jammu","Kargil","Kathua","Kishtwar","Kulgam","Kupwara","Leh","Poonch","Pulwama","Rajouri","Ramban","Reasi","Samba","Shopian","Srinagar","Udhampur"],
            "Jharkhand": ["Bokaro","Chatra","Deoghar","Dhanbad","Dumka","East Singhbhum","Garhwa","Giridih","Godda","Gumla","Hazaribag","Jamtara","Khunti","Koderma","Latehar","Lohardaga","Pakur","Palamu","Ramgarh","Ranchi","Sahibganj","Seraikela-Kharsawan","Simdega","West Singhbhum"],
            "Karnataka": ["Bagalkot","Ballari (Bellary)","Belagavi (Belgaum)","Bengaluru (Bangalore) Rural","Bengaluru (Bangalore) Urban","Bidar","Chamarajanagar","Chikballapur","Chikkamagaluru (Chikmagalur)","Chitradurga","Dakshina Kannada","Davangere","Dharwad","Gadag","Hassan","Haveri","Kalaburagi (Gulbarga)","Kodagu","Kolar","Koppal","Mandya","Mysuru (Mysore)","Raichur","Ramanagara","Shivamogga (Shimoga)","Tumakuru (Tumkur)","Udupi","Uttara Kannada (Karwar)","Vijayapura (Bijapur)","Yadgir"],
            "Kerala": ["Alappuzha","Ernakulam","Idukki","Kannur","Kasaragod","Kollam","Kottayam","Kozhikode","Malappuram","Palakkad","Pathanamthitta","Thiruvananthapuram","Thrissur","Wayanad"],
            "Lakshadweep (UT)": ["Agatti","Amini","Andrott","Bithra","Chethlath","Kavaratti","Kadmath","Kalpeni","Kilthan","Minicoy"],
            "Madhya Pradesh": ["Agar Malwa","Alirajpur","Anuppur","Ashoknagar","Balaghat","Barwani","Betul","Bhind","Bhopal","Burhanpur","Chhatarpur","Chhindwara","Damoh","Datia","Dewas","Dhar","Dindori","Guna","Gwalior","Harda","Hoshangabad","Indore","Jabalpur","Jhabua","Katni","Khandwa","Khargone","Mandla","Mandsaur","Morena","Narsinghpur","Neemuch","Panna","Raisen","Rajgarh","Ratlam","Rewa","Sagar","Satna","Sehore","Seoni","Shahdol","Shajapur","Sheopur","Shivpuri","Sidhi","Singrauli","Tikamgarh","Ujjain","Umaria","Vidisha"],
            "Maharashtra": ["Ahmednagar","Akola","Amravati","Aurangabad","Beed","Bhandara","Buldhana","Chandrapur","Dhule","Gadchiroli","Gondia","Hingoli","Jalgaon","Jalna","Kolhapur","Latur","Mumbai City","Mumbai Suburban","Nagpur","Nanded","Nandurbar","Nashik","Osmanabad","Palghar","Parbhani","Pune","Raigad","Ratnagiri","Sangli","Satara","Sindhudurg","Solapur","Thane","Wardha","Washim","Yavatmal"],
            "Manipur": ["Bishnupur","Chandel","Churachandpur","Imphal East","Imphal West","Jiribam","Kakching","Kamjong","Kangpokpi","Noney","Pherzawl","Senapati","Tamenglong","Tengnoupal","Thoubal","Ukhrul"],
            "Meghalaya": ["East Garo Hills","East Jaintia Hills","East Khasi Hills","North Garo Hills","Ri Bhoi","South Garo Hills","South West Garo Hills ","South West Khasi Hills","West Garo Hills","West Jaintia Hills","West Khasi Hills"],
            "Mizoram": ["Aizawl","Champhai","Kolasib","Lawngtlai","Lunglei","Mamit","Saiha","Serchhip"],
            "Nagaland": ["Dimapur","Kiphire","Kohima","Longleng","Mokokchung","Mon","Peren","Phek","Tuensang","Wokha","Zunheboto"],
            "Odisha": ["Angul","Balangir","Balasore","Bargarh","Bhadrak","Boudh","Cuttack","Deogarh","Dhenkanal","Gajapati","Ganjam","Jagatsinghapur","Jajpur","Jharsuguda","Kalahandi","Kandhamal","Kendrapara","Kendujhar (Keonjhar)","Khordha","Koraput","Malkangiri","Mayurbhanj","Nabarangpur","Nayagarh","Nuapada","Puri","Rayagada","Sambalpur","Sonepur","Sundargarh"],
            "Puducherry (UT)": ["Karaikal","Mahe","Pondicherry","Yanam"],
            "Punjab": ["Amritsar","Barnala","Bathinda","Faridkot","Fatehgarh Sahib","Fazilka","Ferozepur","Gurdaspur","Hoshiarpur","Jalandhar","Kapurthala","Ludhiana","Mansa","Moga","Muktsar","Nawanshahr (Shahid Bhagat Singh Nagar)","Pathankot","Patiala","Rupnagar","Sahibzada Ajit Singh Nagar (Mohali)","Sangrur","Tarn Taran"],
            "Rajasthan": ["Ajmer","Alwar","Banswara","Baran","Barmer","Bharatpur","Bhilwara","Bikaner","Bundi","Chittorgarh","Churu","Dausa","Dholpur","Dungarpur","Hanumangarh","Jaipur","Jaisalmer","Jalore","Jhalawar","Jhunjhunu","Jodhpur","Karauli","Kota","Nagaur","Pali","Pratapgarh","Rajsamand","Sawai Madhopur","Sikar","Sirohi","Sri Ganganagar","Tonk","Udaipur"],
            "Sikkim": ["East Sikkim","North Sikkim","South Sikkim","West Sikkim"],
            "Tamil Nadu": ["Ariyalur","Chennai","Coimbatore","Cuddalore","Dharmapuri","Dindigul","Erode","Kanchipuram","Kanyakumari","Karur","Krishnagiri","Madurai","Nagapattinam","Namakkal","Nilgiris","Perambalur","Pudukkottai","Ramanathapuram","Salem","Sivaganga","Thanjavur","Theni","Thoothukudi (Tuticorin)","Tiruchirappalli","Tirunelveli","Tiruppur","Tiruvallur","Tiruvannamalai","Tiruvarur","Vellore","Viluppuram","Virudhunagar"],
            "Telangana": ["Adilabad","Bhadradri Kothagudem","Hyderabad","Jagtial","Jangaon","Jayashankar Bhoopalpally","Jogulamba Gadwal","Kamareddy","Karimnagar","Khammam","Komaram Bheem Asifabad","Mahabubabad","Mahabubnagar","Mancherial","Medak","Medchal","Nagarkurnool","Nalgonda","Nirmal","Nizamabad","Peddapalli","Rajanna Sircilla","Rangareddy","Sangareddy","Siddipet","Suryapet","Vikarabad","Wanaparthy","Warangal (Rural)","Warangal (Urban)","Yadadri Bhuvanagiri"],
            "Tripura": ["Dhalai","Gomati","Khowai","North Tripura","Sepahijala","South Tripura","Unakoti","West Tripura"],
            "Uttarakhand": ["Almora","Bageshwar","Chamoli","Champawat","Dehradun","Haridwar","Nainital","Pauri Garhwal","Pithoragarh","Rudraprayag","Tehri Garhwal","Udham Singh Nagar","Uttarkashi"],
            "Uttar Pradesh": ["Agra","Aligarh","Allahabad","Ambedkar Nagar","Amethi (Chatrapati Sahuji Mahraj Nagar)","Amroha (J.P. Nagar)","Auraiya","Azamgarh","Baghpat","Bahraich","Ballia","Balrampur","Banda","Barabanki","Bareilly","Basti","Bhadohi","Bijnor","Budaun","Bulandshahr","Chandauli","Chitrakoot","Deoria","Etah","Etawah","Faizabad","Farrukhabad","Fatehpur","Firozabad","Gautam Buddha Nagar","Ghaziabad","Ghazipur","Gonda","Gorakhpur","Hamirpur","Hapur (Panchsheel Nagar)","Hardoi","Hathras","Jalaun","Jaunpur","Jhansi","Kannauj","Kanpur Dehat","Kanpur Nagar","Kanshiram Nagar (Kasganj)","Kaushambi","Kushinagar (Padrauna)","Lakhimpur - Kheri","Lalitpur","Lucknow","Maharajganj","Mahoba","Mainpuri","Mathura","Mau","Meerut","Mirzapur","Moradabad","Muzaffarnagar","Pilibhit","Pratapgarh","RaeBareli","Rampur","Saharanpur","Sambhal (Bhim Nagar)","Sant Kabir Nagar","Shahjahanpur","Shamali (Prabuddh Nagar)","Shravasti","Siddharth Nagar","Sitapur","Sonbhadra","Sultanpur","Unnao","Varanasi"],
            "West Bengal": ["Alipurduar","Bankura","Birbhum","Burdwan (Bardhaman)","Cooch Behar","Dakshin Dinajpur (South Dinajpur)","Darjeeling","Hooghly","Howrah","Jalpaiguri","Kalimpong","Kolkata","Malda","Murshidabad","Nadia","North 24 Parganas","Paschim Medinipur (West Medinipur)","Purba Medinipur (East Medinipur)","Purulia","South 24 Parganas","Uttar Dinajpur (North Dinajpur)"]
        };

        // Populate States
        const stateSelect = document.getElementById('state');
        const citySelect = document.getElementById('city');

        Object.keys(indiaData).sort().forEach(state => {
            let option = document.createElement('option');
            option.value = state;
            option.textContent = state;
            stateSelect.appendChild(option);
        });

        function populateCities() {
            const selectedState = stateSelect.value;
            citySelect.innerHTML = '<option value="">Select City</option>';
            
            if (selectedState && indiaData[selectedState]) {
                indiaData[selectedState].sort().forEach(city => {
                    let option = document.createElement('option');
                    option.value = city;
                    option.textContent = city;
                    citySelect.appendChild(option);
                });
            }
        }

        // Form Setup
        document.getElementById('visit_date').value = new Date().toISOString().split('T')[0];

        // Interaction styles for inputs
        document.querySelectorAll('input:not([type="radio"]), select, textarea').forEach(el => {
            el.addEventListener('focus', () => el.style.borderColor = 'var(--primary)');
            el.addEventListener('blur', () => el.style.borderColor = '#e2e8f0');
        });

        // Button press effect
        const btn = document.getElementById('submitBtn');
        btn.addEventListener('touchstart', () => btn.style.transform = 'scale(0.98)');
        btn.addEventListener('touchend', () => btn.style.transform = 'scale(1)');

        function toggleCustomProfession() {
            const select = document.getElementById('profession');
            const customGroup = document.getElementById('custom_profession_group');
            const customInput = document.getElementById('custom_profession');
            
            if (select.value === 'OTHER') {
                customGroup.style.display = 'block';
                customInput.required = true;
            } else {
                customGroup.style.display = 'none';
                customInput.required = false;
                customInput.value = '';
            }
        }

        function previewImage(input) {
            const container = document.getElementById('preview_container');
            const preview = document.getElementById('image_preview');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    container.style.display = 'block';
                }
                reader.readAsDataURL(input.files[0]);
            } else {
                container.style.display = 'none';
            }
        }

        
        // Pincode Auto-fill Logic
        document.getElementById('pincode').addEventListener('input', async function() {
            if (this.value.length === 6) {
                try {
                    const res = await fetch(`https://api.postalpincode.in/pincode/${this.value}`);
                    const data = await res.json();
                    if (data[0].Status === "Success") {
                        const postOffice = data[0].PostOffice[0];
                        const state = postOffice.State;
                        const district = postOffice.District;
                        
                        const stateSelect = document.getElementById('state');
                        stateSelect.innerHTML = `<option value="${state}" selected>${state}</option>`;
                        
                        const citySelect = document.getElementById('city');
                        citySelect.innerHTML = `<option value="${district}" selected>${district}</option>`;
                    }
                } catch(e) {
                    console.log("Pincode fetch error:", e);
                }
            }
        });

        // Smart Check-in System
        let checkInInterval;
        let checkInTimeObj = null;
        let mediaRecorder = null;
        let audioChunks = [];
        let audioBlob = null;
        let checkInLat = null;
        let checkInLon = null;

        // Haversine distance formula
        function getDistanceFromLatLonInM(lat1, lon1, lat2, lon2) {
          var R = 6371; // Radius of the earth in km
          var dLat = (lat2-lat1) * (Math.PI/180);
          var dLon = (lon2-lon1) * (Math.PI/180); 
          var a = 
            Math.sin(dLat/2) * Math.sin(dLat/2) +
            Math.cos(lat1 * (Math.PI/180)) * Math.cos(lat2 * (Math.PI/180)) * 
            Math.sin(dLon/2) * Math.sin(dLon/2)
            ; 
          var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a)); 
          var d = R * c; // Distance in km
          return Math.round(d * 1000); // Distance in meters
        }

        async function startCheckIn() {
            // Voice Recording Request
            audioChunks = [];
            audioBlob = null;
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                mediaRecorder = new MediaRecorder(stream);
                mediaRecorder.ondataavailable = e => {
                    if (e.data && e.data.size > 0) audioChunks.push(e.data);
                };
                mediaRecorder.start(500);
                document.getElementById('recording_status').innerText = "Recording meeting audio...";
            } catch (err) {
                console.error("Mic error:", err);
                document.getElementById('recording_status').innerHTML = "Mic Access Denied (Recording Disabled)";
                document.getElementById('recording_status').style.color = "#f59e0b";
            }
            document.getElementById('start_visit_btn').style.display = 'none';
            document.getElementById('meeting_in_progress').style.display = 'block';
            
            checkInTimeObj = new Date();
            // Format check-in for DB (MySQL datetime format)
            const pad = n => n<10 ? '0'+n : n;
            const yyyy = checkInTimeObj.getFullYear();
            const mm = pad(checkInTimeObj.getMonth()+1);
            const dd = pad(checkInTimeObj.getDate());
            const hh = pad(checkInTimeObj.getHours());
            const mi = pad(checkInTimeObj.getMinutes());
            const ss = pad(checkInTimeObj.getSeconds());
            
            document.getElementById('check_in_time').value = `${yyyy}-${mm}-${dd} ${hh}:${mi}:${ss}`;
            
            // Start Timer
            checkInInterval = setInterval(() => {
                const now = new Date();
                const diff = Math.floor((now - checkInTimeObj) / 1000);
                const hrs = Math.floor(diff / 3600);
                const mins = Math.floor((diff % 3600) / 60);
                const secs = diff % 60;
                document.getElementById('visit_timer').innerText = 
                    `${pad(hrs)}:${pad(mins)}:${pad(secs)}`;
            }, 1000);
            
            // Try fetching geo
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(pos => {
                    checkInLat = pos.coords.latitude;
                    checkInLon = pos.coords.longitude;
                    document.getElementById('lat').value = checkInLat;
                    document.getElementById('lon').value = checkInLon;
                });
            }
        }

        function endCheckIn() {
            // Stop Voice Recording
            if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                try { mediaRecorder.requestData(); } catch(e) {}
                mediaRecorder.stop();
                if (mediaRecorder.stream) {
                    mediaRecorder.stream.getTracks().forEach(t => t.stop());
                }
            }
            if (audioChunks.length > 0) {
                audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
                const audioUrl = URL.createObjectURL(audioBlob);
                const player = document.getElementById('manual_audio_preview');
                if (player) {
                    player.src = audioUrl;
                    document.getElementById('voice_idle_state').style.display = 'none';
                    document.getElementById('voice_recording_state').style.display = 'none';
                    document.getElementById('voice_preview_state').style.display = 'block';
                }
            }

            // Manual Voice Recording Functions
            let manualRecorder = null;
            let manualAudioChunks = [];
            let manualVoiceTimer = null;
            let manualSeconds = 0;

            async function toggleManualVoiceRecord() {
                manualAudioChunks = [];
                try {
                    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                    manualRecorder = new MediaRecorder(stream);
                    manualRecorder.ondataavailable = e => {
                        if (e.data && e.data.size > 0) manualAudioChunks.push(e.data);
                    };
                    manualRecorder.start(500);
                    
                    document.getElementById('voice_idle_state').style.display = 'none';
                    document.getElementById('voice_recording_state').style.display = 'block';
                    document.getElementById('voice_preview_state').style.display = 'none';
                    
                    manualSeconds = 0;
                    document.getElementById('manual_voice_timer').innerText = '00:00';
                    manualVoiceTimer = setInterval(() => {
                        manualSeconds++;
                        const m = Math.floor(manualSeconds / 60).toString().padStart(2, '0');
                        const s = (manualSeconds % 60).toString().padStart(2, '0');
                        document.getElementById('manual_voice_timer').innerText = `${m}:${s}`;
                    }, 1000);
                } catch (err) {
                    alert('Microphone access denied or not supported on this device.');
                }
            }

            function stopManualVoiceRecord() {
                if (manualVoiceTimer) clearInterval(manualVoiceTimer);
                if (manualRecorder && manualRecorder.state !== 'inactive') {
                    try { manualRecorder.requestData(); } catch(e) {}
                    manualRecorder.stop();
                    if (manualRecorder.stream) {
                        manualRecorder.stream.getTracks().forEach(t => t.stop());
                    }
                }
                setTimeout(() => {
                    if (manualAudioChunks.length > 0) {
                        audioBlob = new Blob(manualAudioChunks, { type: 'audio/webm' });
                        const audioUrl = URL.createObjectURL(audioBlob);
                        const player = document.getElementById('manual_audio_preview');
                        if (player) {
                            player.src = audioUrl;
                            document.getElementById('voice_idle_state').style.display = 'none';
                            document.getElementById('voice_recording_state').style.display = 'none';
                            document.getElementById('voice_preview_state').style.display = 'block';
                        }
                    }
                }, 300);
            }

            function deleteVoiceRecord() {
                audioBlob = null;
                manualAudioChunks = [];
                document.getElementById('voice_idle_state').style.display = 'block';
                document.getElementById('voice_recording_state').style.display = 'none';
                document.getElementById('voice_preview_state').style.display = 'none';
            }

            // Geo-fence check (if checkIn location was captured)
            if (navigator.geolocation && checkInLat !== null) {
                navigator.geolocation.getCurrentPosition(pos => {
                    const checkOutLat = pos.coords.latitude;
                    const checkOutLon = pos.coords.longitude;
                    const distanceMeters = getDistanceFromLatLonInM(checkInLat, checkInLon, checkOutLat, checkOutLon);
                    
                    if (distanceMeters > 150) {
                        const remarksBox = document.getElementById('remarks');
                        remarksBox.value = `[SYSTEM ALERT: Staff checked-out ${distanceMeters}m away from check-in location]\n` + remarksBox.value;
                    }
                });
            }
            clearInterval(checkInInterval);
            
            const now = new Date();
            const pad = n => n<10 ? '0'+n : n;
            const yyyy = now.getFullYear();
            const mm = pad(now.getMonth()+1);
            const dd = pad(now.getDate());
            const hh = pad(now.getHours());
            const mi = pad(now.getMinutes());
            const ss = pad(now.getSeconds());
            
            document.getElementById('check_out_time').value = `${yyyy}-${mm}-${dd} ${hh}:${mi}:${ss}`;
            
            // Hide Checkin UI, Show Real Form
            document.getElementById('smart_checkin_container').style.display = 'none';
            document.getElementById('main_form_container').style.display = 'block';
        }

        async function submitForm(e) {
            e.preventDefault();
            const alertBox = document.getElementById('alert_box');
            const btn = document.querySelector('button[type="submit"]');
            
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
            btn.style.opacity = '0.7';
            btn.disabled = true;

            const form = document.getElementById('fieldVisitForm');
            const formData = new FormData(form);
            if (!audioBlob && audioChunks.length > 0) {
                audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
            }
            if (audioBlob) {
                formData.append('audio_file', audioBlob, 'meeting_recording.webm');
            }
            
            try {
                const response = await fetch('../api.php?api=save_field_visit', {
                    method: 'POST',
                    body: formData
                });
                const res = await response.json();
                
                if (response.ok && res.success) {
                    alertBox.style.background = '#dcfce7';
                    alertBox.style.color = '#166534';
                    alertBox.style.border = '1px solid #bbf7d0';
                    alertBox.innerHTML = '<i class="fas fa-check-circle" style="margin-right:5px;"></i> ' + res.message;
                    alertBox.style.display = 'block';
                    form.reset();
                    document.getElementById('visit_date').value = new Date().toISOString().split('T')[0];
                    document.getElementById('preview_container').style.display = 'none';
                    toggleCustomProfession();
                    window.scrollTo({top: 0, behavior: 'smooth'});
                    
                    // Reset radio cards
                    document.querySelectorAll('input[type="radio"]').forEach(r => r.checked = false);
                    
                    setTimeout(() => {
                        window.location.href = 'visits.php';
                    }, 1500);
                } else {
                    alertBox.style.background = '#fee2e2';
                    alertBox.style.color = '#991b1b';
                    alertBox.style.border = '1px solid #fecaca';
                    alertBox.innerHTML = '<i class="fas fa-exclamation-circle" style="margin-right:5px;"></i> ' + (res.error || 'Failed to save');
                    alertBox.style.display = 'block';
                    window.scrollTo({top: 0, behavior: 'smooth'});
                }
            } catch (err) {
                alertBox.style.background = '#fee2e2';
                alertBox.style.color = '#991b1b';
                alertBox.style.border = '1px solid #fecaca';
                alertBox.innerHTML = '<i class="fas fa-exclamation-circle" style="margin-right:5px;"></i> Network error occurred';
                alertBox.style.display = 'block';
                window.scrollTo({top: 0, behavior: 'smooth'});
            }
            
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Record';
            btn.style.opacity = '1';
            btn.disabled = false;
        }
    </script>
    
    <!-- Leaflet JS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const mapContainer = document.createElement('div');
            mapContainer.id = 'loc-map';
            mapContainer.style.height = '200px';
            mapContainer.style.borderRadius = '8px';
            mapContainer.style.marginTop = '15px';
            mapContainer.style.marginBottom = '15px';
            mapContainer.style.border = '1px solid #cbd5e1';
            
            // Insert map before submit button
            const form = document.querySelector('form');
            const submitBtn = document.querySelector('button[type="submit"]');
            submitBtn.parentNode.insertBefore(mapContainer, submitBtn);
            
            const latInp = document.getElementById('lat');
            const lonInp = document.getElementById('lon');
            
            let map = L.map('loc-map').setView([20.5937, 78.9629], 5); // Default India
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
            let marker;
            
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    async (pos) => {
                        const lat = pos.coords.latitude;
                        const lon = pos.coords.longitude;
                        latInp.value = lat;
                        lonInp.value = lon;
                        
                        map.setView([lat, lon], 15);
                        marker = L.marker([lat, lon]).addTo(map);
                            
                        const info = document.createElement('div');
                        info.style.padding = '12px';
                        info.style.background = '#f0fdf4';
                        info.style.border = '1px solid #bbf7d0';
                        info.style.borderRadius = '8px';
                        info.style.marginBottom = '20px';
                        info.innerHTML = '<div style="color:#15803d; font-weight:600; margin-bottom:4px; display:flex; align-items:center; gap:6px;"><i data-lucide="map-pin" style="width:16px;"></i> Live Location Verified</div><div id="live-address-txt" style="font-size:12px; color:#166534;">Fetching exact address...</div>';
                        mapContainer.parentNode.insertBefore(info, mapContainer.nextSibling);
                        if(typeof lucide !== 'undefined') lucide.createIcons();
                        
                        // 10x Feature: Reverse Geocoding via Nominatim
                        try {
                            const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lon}`);
                            const data = await res.json();
                            const addr = data.display_name || 'Address found';
                            document.getElementById('v_addr').value = addr;
                            document.getElementById('live-address-txt').innerText = addr;
                            marker.bindPopup(`<strong style="color:var(--primary);">Verified Address:</strong><br>${addr}`).openPopup();
                        } catch(e) {
                            document.getElementById('live-address-txt').innerText = 'Location captured, but address decoding failed.';
                            marker.bindPopup('Location Captured').openPopup();
                        }
                    },
                    (err) => {
                        console.error(err);
                        const info = document.createElement('div');
                        info.innerHTML = '<div style="padding:12px; background:#fef2f2; border:1px solid #fecaca; border-radius:8px; margin-bottom:20px; color:#b91c1c; font-weight:600; font-size:13px; display:flex; align-items:center; gap:6px;"><i data-lucide="alert-circle" style="width:16px;"></i> Error: Location access denied. Please enable GPS permissions to save verified visits.</div>';
                        mapContainer.parentNode.insertBefore(info, mapContainer.nextSibling);
                        if(typeof lucide !== 'undefined') lucide.createIcons();
                    },
                    { enableHighAccuracy: true }
                );
            }
        });
    </script>

</script>
</body>
</html>

