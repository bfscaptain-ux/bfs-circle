<?php
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Staff';
$role = $_SESSION['role'] ?? 'Staff';

$stmt = $db->prepare("SELECT full_name, department FROM employees WHERE user_id = ?");
$stmt->execute([$user_id]);
$emp_data = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$fullname = !empty($emp_data['full_name']) ? $emp_data['full_name'] : $username;
$department = $emp_data['department'] ?? 'Lead Generation Team';

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

    <div class="app-header">
        <div class="header-top">
            <div>
                <div class="user-greeting">Welcome back,</div>
                <div class="user-name" id="staff_name"><?= htmlspecialchars($fullname) ?></div>
            </div>
            <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i></a>
        </div>
        <div style="height: 40px;"></div> <!-- Spacer for overlapping cards -->
    </div>

    <div style="padding:20px; padding-bottom:80px; z-index:1; padding-top:20px;">
        <h2 style="font-size:18px; margin-bottom:15px; color:#1e293b;">My Leads & Files</h2>
        <div id="files_list" style="display:flex; flex-direction:column; gap:12px;">
            <div style="text-align:center;padding:20px;color:gray;font-size:14px;">Loading files...</div>
        </div>
    </div>

    <script>
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

        document.addEventListener('DOMContentLoaded', async () => {
            const list = document.getElementById('files_list');
            list.innerHTML = getSkeletonHTML(4);
            
            try {
                // Get username from PHP context or assume it's set
                const username = "<?= htmlspecialchars($username) ?>";
                const res = await fetch(`../api.php?api=get_staff_recent_leads&username=${encodeURIComponent(username)}`);
                const data = await res.json();
                
                if (data.error) {
                    list.innerHTML = `<div style="text-align:center;padding:20px;color:red;font-size:14px;">${data.error}</div>`;
                    return;
                }
                
                if (data.length === 0) {
                    list.innerHTML = '<div style="text-align:center;padding:20px;color:gray;font-size:14px;">No files/leads assigned yet.</div>';
                    return;
                }
                
                let html = '';
                data.forEach(lead => {
                    const status = lead.overall_status || 'Submitted';
                    const custName = lead.customer_name || lead.applicant_name || 'Loan Applicant';
                    const loanId = lead.loan_id ? `(#${lead.loan_id})` : '';
                    const bankInfo = lead.sanction_bank ? ` • ${lead.sanction_bank}` : '';
                    const amt = parseFloat(lead.loan_amount_requested || 0).toLocaleString('en-IN');
                    
                    let statusBg = '#E0F2FE'; let statusColor = '#0369A1';
                    const sLower = status.toLowerCase();
                    if (['disbursed', 'completed'].includes(sLower)) { statusBg = '#DCFCE7'; statusColor = '#15803D'; }
                    else if (['sanctioned', 'approved'].includes(sLower)) { statusBg = '#FEF3C7'; statusColor = '#B45309'; }
                    else if (['rejected', 'declined'].includes(sLower)) { statusBg = '#FEE2E2'; statusColor = '#B91C1C'; }

                    const trackLink = lead.tracking_token ? `../track.php?token=${lead.tracking_token}` : '#';

                    html += `
                        <div style="background:white; border-radius:18px; padding:16px; margin-bottom:12px; box-shadow:0 4px 12px rgba(0,0,0,0.03); border:1px solid #E2E8F0;">
                            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px;">
                                <div>
                                    <div style="font-weight:800; font-size:15px; color:#0F172A;">${custName} <span style="font-size:11px; color:#64748B; font-weight:600;">${loanId}</span></div>
                                    <div style="font-size:12px; color:#64748B; margin-top:2px;">₹${amt} • ${lead.loan_type || 'Loan'}${bankInfo}</div>
                                </div>
                                <span style="background:${statusBg}; color:${statusColor}; font-size:11px; font-weight:800; padding:4px 10px; border-radius:20px;">
                                    ${status}
                                </span>
                            </div>
                            
                            <div style="background:#F8FAFC; border:1px solid #F1F5F9; border-radius:12px; padding:10px 12px; font-size:11px; color:#475569; display:flex; justify-content:space-between; align-items:center;">
                                <span><i class="fas fa-calendar-alt" style="color:#FF7A00; margin-right:4px;"></i> Date: ${new Date(lead.created_at).toLocaleDateString('en-IN')}</span>
                                ${lead.tracking_token ? `<a href="${trackLink}" target="_blank" style="color:#FF7A00; text-decoration:none; font-weight:800; display:flex; align-items:center; gap:4px;">Live Process Tracker <i class="fas fa-arrow-right"></i></a>` : '<span style="color:#94A3B8;">Processing</span>'}
                            </div>
                        </div>
                    `;
                });
                
                list.innerHTML = html;
            } catch (err) {
                console.error(err);
                list.innerHTML = '<div style="text-align:center;padding:20px;color:red;font-size:14px;">Network Error loading files.</div>';
            }
        });
    </script>
    <style>
        @keyframes shimmer {
            0% { background-position: -1000px 0; }
            100% { background-position: 1000px 0; }
        }
    </style>

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

</body>
</html>
