<?php
if (!defined('IS_SUBFOLDER')) {
    define('IS_SUBFOLDER', true);
}
require_once __DIR__ . '/../config.php';
require_role(['RM']);
$page_title = 'RM Loan Command Center';
$page_subtitle = 'Relationship Manager Loan Portfolio, Disbursal Targets & Customer Connect';
$hide_main_header = true;
require_once __DIR__ . '/header.php';


$current_rm = $_SESSION['username'] ?? 'RM';
$current_name = $_SESSION['user_full_name'] ?? $current_rm;
$is_admin = (($_SESSION['role'] ?? '') === 'Admin');

$all_rms = [];
if ($is_admin) {
    $all_rms_stmt = $db->query("SELECT username, name, full_name, role FROM users WHERE (role = 'Staff' OR role = 'RM') AND (is_active IS NULL OR is_active != 0) ORDER BY username ASC");
    $all_rms = $all_rms_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Calculate RM Rank
$rm_rank_info = ['rank' => 0, 'badge' => '', 'title' => 'Unranked', 'bg' => '#f8fafc', 'color' => '#475569', 'border' => '#cbd5e1'];
try {
    $rm_rank_query = "
        SELECT 
            u.username,
            COALESCE(SUM(CASE WHEN LOWER(a.overall_status) IN ('completed','disbursed') THEN a.loan_amount_requested ELSE 0 END), 0) as disbursed_volume,
            COUNT(DISTINCT CASE WHEN LOWER(a.overall_status) IN ('completed','disbursed','sanctioned','phase 4','phase 5') THEN a.id END) as approved_cnt
        FROM users u
        LEFT JOIN employees e ON (e.user_id = u.id OR e.official_email = u.username)
        LEFT JOIN applicants a ON (a.assigned_to = u.username OR a.assigned_to = u.name OR a.assigned_to = e.full_name)
        WHERE (u.role IN ('RM','Staff') OR e.designation LIKE '%RM%' OR e.designation LIKE '%Relationship Manager%')
          AND LOWER(u.username) != 'admin' AND LOWER(u.role) != 'admin'
        GROUP BY u.id
        ORDER BY disbursed_volume DESC, approved_cnt DESC
    ";
    $rm_ranks = $db->query($rm_rank_query)->fetchAll(PDO::FETCH_ASSOC);
    $pos = 1;
    foreach($rm_ranks as $r) {
        if ($r['username'] === $current_rm) {
            $rm_rank_info['rank'] = $pos;
            if ($pos === 1) {
                $rm_rank_info['badge'] = '#1 Rank';
                $rm_rank_info['title'] = 'Gold RM Officer';
                $rm_rank_info['bg'] = '#fef08a'; $rm_rank_info['color'] = '#854d0e'; $rm_rank_info['border'] = '#eab308';
            } else if ($pos === 2) {
                $rm_rank_info['badge'] = '#2 Rank';
                $rm_rank_info['title'] = 'Silver RM Officer';
                $rm_rank_info['bg'] = '#f1f5f9'; $rm_rank_info['color'] = '#475569'; $rm_rank_info['border'] = '#94a3b8';
            } else if ($pos === 3) {
                $rm_rank_info['badge'] = '#3 Rank';
                $rm_rank_info['title'] = 'Bronze RM Officer';
                $rm_rank_info['bg'] = '#ffedd5'; $rm_rank_info['color'] = '#9a3412'; $rm_rank_info['border'] = '#f97316';
            } else {
                $rm_rank_info['badge'] = 'Rank #' . $pos;
                $rm_rank_info['title'] = 'RM Officer';
                $rm_rank_info['bg'] = '#f8fafc'; $rm_rank_info['color'] = '#475569'; $rm_rank_info['border'] = '#cbd5e1';
            }
            break;
        }
        $pos++;
    }
} catch(Exception $e) {}
?>

<style>
/* Modern RM Loan Command Center Styles */
:root {
    --rm-primary: #0f172a;
    --rm-accent: #0284c7;
    --rm-teal: #0f766e;
    --rm-emerald: #10b981;
    --rm-amber: #f59e0b;
    --rm-rose: #f43f5e;
}

.rm-hero-card {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    border-radius: 16px;
    padding: 24px;
    color: #ffffff;
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.2);
    margin-bottom: 24px;
    position: relative;
    overflow: hidden;
}

.rm-hero-card::after {
    content: '';
    position: absolute;
    top: -50px;
    right: -50px;
    width: 200px;
    height: 200px;
    background: radial-gradient(circle, rgba(56, 189, 248, 0.15) 0%, rgba(255,255,255,0) 70%);
    border-radius: 50%;
    pointer-events: none;
}

.rm-target-progress-bar {
    height: 10px;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 10px;
    overflow: hidden;
    position: relative;
    margin: 12px 0 6px 0;
}

.rm-target-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #38bdf8 0%, #10b981 100%);
    border-radius: 10px;
    transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1);
}

.rm-metric-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.rm-stat-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 18px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.03);
    transition: all 0.2s ease;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.rm-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.06);
    border-color: #cbd5e1;
}

.action-trigger-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 14px;
    margin-bottom: 20px;
}

.action-trigger-card {
    background: #ffffff;
    border-radius: 12px;
    padding: 14px 16px;
    border: 1px solid #e2e8f0;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 12px;
    position: relative;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
}

.action-trigger-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(15, 23, 42, 0.05);
    border-color: #cbd5e1;
}

.action-trigger-card.active-filter {
    border: 2px solid #0284c7;
    background: #f0f9ff;
    box-shadow: 0 4px 14px rgba(2, 132, 199, 0.12);
}

.trigger-icon-box {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

/* 5-Stage Funnel Strip */
.loan-funnel-strip {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 8px;
    background: #ffffff;
    padding: 10px;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    margin-bottom: 20px;
}

.funnel-step {
    padding: 10px 8px;
    border-radius: 8px;
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    cursor: pointer;
    transition: all 0.2s ease;
    text-align: center;
}

.funnel-step:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
}

.funnel-step.active {
    background: #0f172a;
    color: #ffffff;
    border-color: #0f172a;
    box-shadow: 0 4px 10px rgba(15, 23, 42, 0.15);
}

.funnel-step.active .funnel-step-name {
    color: #94a3b8;
}

.funnel-step.active .funnel-step-count {
    color: #38bdf8;
}

.funnel-step-count {
    font-size: 20px;
    font-weight: 800;
    color: #0f172a;
    font-family: 'Outfit', sans-serif;
    line-height: 1.1;
}

.funnel-step-name {
    font-size: 11px;
    font-weight: 700;
    color: #64748b;
    margin-top: 3px;
    white-space: nowrap;
}

.badge-priority {
    font-size: 10px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 12px;
    text-transform: uppercase;
}
.priority-urgent { background: #ffe4e6; color: #e11d48; border: 1px solid #fecdd3; }
.priority-high { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
.priority-normal { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }

.table-container {
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    padding: 18px 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
}

.data-table th {
    background: #f8fafc;
    color: #475569;
    font-weight: 700;
    font-size: 11px;
    letter-spacing: 0.3px;
    text-transform: uppercase;
    padding: 10px 12px;
    border-bottom: 1px solid #e2e8f0;
    white-space: nowrap;
}

.data-table td {
    padding: 12px 12px;
    font-size: 12.5px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}

.data-table tr:hover td {
    background: #f8fafc;
}

.dossier-tab-btn {
    padding: 8px 16px;
    font-size: 12.5px;
    font-weight: 700;
    color: #64748b;
    border: none;
    background: none;
    cursor: pointer;
    border-bottom: 2px solid transparent;
}
.dossier-tab-btn.active {
    color: #0284c7;
    border-bottom-color: #0284c7;
}

#rm-recent-activities::-webkit-scrollbar {
    width: 6px;
}
#rm-recent-activities::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 4px;
}
#rm-recent-activities::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}
#rm-recent-activities::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

.activity-tab-btn {
    transition: all 0.15s ease;
}
.activity-tab-btn:hover {
    color: #0f172a !important;
}

/* Executive Tab Navigation */
.executive-tab-strip {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #ffffff;
    padding: 6px;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    margin-bottom: 22px;
    overflow-x: auto;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
}

.executive-tab-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 18px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 700;
    color: #64748b;
    border: none;
    background: transparent;
    cursor: pointer;
    transition: all 0.15s ease;
    white-space: nowrap;
}

.executive-tab-btn:hover {
    color: #0f172a;
    background: #f8fafc;
}

.executive-tab-btn.active {
    background: #0f172a;
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.18);
}

.executive-tab-btn.active .tab-pill-badge {
    background: rgba(255, 255, 255, 0.2);
    color: #ffffff;
}

.tab-pill-badge {
    padding: 2px 7px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 700;
    background: #f1f5f9;
    color: #475569;
    transition: all 0.15s ease;
}

.executive-tab-pane {
    animation: fadeInTab 0.2s ease-in-out;
}

@keyframes fadeInTab {
    from { opacity: 0; transform: translateY(4px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<div class="view-container">
    <!-- Top Header & RM Role Switcher -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
        <div>
            <?php if ($is_admin): ?>
                <h1 style="font-size:22px; font-weight:800; color:#0f172a; margin:0; display:flex; align-items:center; gap:8px;">
                    <i data-lucide="shield-check" style="color:#0284c7; width:26px; height:26px;"></i>
                    RM Operations & Portfolio Command Center
                    <span class="badge" style="background:#0f172a; color:#38bdf8; border:1px solid #1e293b; font-size:11px; padding:3px 8px; font-weight:700;">Executive Oversight</span>
                </h1>
                <p style="font-size:13px; color:#64748b; margin:4px 0 0 0;">Executive portfolio monitoring: officer pipeline tracking, disbursal targets, action priorities & operational activity audit.</p>
            <?php else: ?>
                <h1 style="font-size:22px; font-weight:800; color:#0f172a; margin:0; display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                    <i data-lucide="user-check" style="color:#0284c7; width:24px; height:24px;"></i>
                    Namaste, <?= htmlspecialchars($current_name) ?>
                    <span class="badge" style="background:#e0f2fe; color:#0369a1; border:1px solid #bae6fd; font-size:11px;">Dedicated Relationship Manager</span>
                    <?php if ($rm_rank_info['rank'] > 0): ?>
                        <a href="../staff_ranking.php?tab=rms" class="badge" style="background:<?= $rm_rank_info['bg'] ?>; color:<?= $rm_rank_info['color'] ?>; border:1px solid <?= $rm_rank_info['border'] ?>; font-size:11.5px; text-decoration:none; font-weight:800; display:inline-flex; align-items:center; gap:4px; padding:3px 10px; border-radius:6px;" title="Click to view full Leaderboard">
                            <?= $rm_rank_info['badge'] ?> (<?= $rm_rank_info['title'] ?>)
                        </a>
                    <?php endif; ?>
                </h1>
                <p style="font-size:13px; color:#64748b; margin:4px 0 0 0;">Manage your loan applicants, clear pendencies, record login fees, and drive disbursals.</p>
            <?php endif; ?>
        </div>

        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <?php if ($is_admin): ?>
                <div style="display:flex; align-items:center; background:#ffffff; border:1px solid #cbd5e1; border-radius:8px; padding:6px 14px; box-shadow:0 1px 2px rgba(0,0,0,0.04);">
                    <label for="admin-rm-selector" style="font-size:12px; font-weight:700; color:#475569; margin-right:8px; display:flex; align-items:center; gap:5px; white-space:nowrap;">
                        <i data-lucide="users" style="width:14px; color:#0284c7;"></i> Select Officer:
                    </label>
                    <select id="admin-rm-selector" onchange="onAdminRmSelectChange(this.value)" style="border:none; background:transparent; font-size:13px; font-weight:700; color:#0f172a; outline:none; cursor:pointer;">
                        <option value="ALL" selected>All Officers — Master Portfolio View</option>
                        <?php foreach ($all_rms as $rm): 
                            $disp = !empty($rm['full_name']) ? $rm['full_name'] : (!empty($rm['name']) ? $rm['name'] : $rm['username']);
                        ?>
                        <option value="<?= htmlspecialchars($rm['username']) ?>"><?= htmlspecialchars($disp) ?> (<?= htmlspecialchars($rm['username']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button onclick="loadRmDashboard()" class="btn btn-secondary" style="padding:7px 13px; font-size:12.5px; font-weight:600; background:#ffffff; border:1px solid #cbd5e1; color:#0f172a; display:inline-flex; align-items:center; gap:6px;"><i data-lucide="refresh-cw" style="width:13px;"></i> Refresh</button>
                <button onclick="openRaiseRmQueryModal()" class="btn btn-primary" style="background:#0f172a; color:#ffffff; border:1px solid #1e293b; padding:7px 16px; font-size:12.5px; font-weight:700; display:inline-flex; align-items:center; gap:6px; box-shadow:0 2px 4px rgba(15,23,42,0.15);"><i data-lucide="check-square" style="width:14px; color:#38bdf8;"></i> Assign Task to RM</button>
            <?php else: ?>
                <button onclick="loadRmDashboard()" class="btn btn-secondary" style="padding:7px 13px; font-size:12.5px; font-weight:600; background:#ffffff; border:1px solid #cbd5e1; color:#0f172a; display:inline-flex; align-items:center; gap:6px;"><i data-lucide="refresh-cw" style="width:13px;"></i> Refresh</button>
                <button onclick="openRaiseRmQueryModal()" class="btn btn-secondary" style="padding:7px 13px; font-size:12.5px; font-weight:600; background:#ffffff; border:1px solid #cbd5e1; color:#0f172a; display:inline-flex; align-items:center; gap:6px;"><i data-lucide="help-circle" style="width:14px; color:#d97706;"></i> Raise Query</button>
                <a href="<?php echo $base_path; ?>add_applicant.php" class="btn btn-primary" style="background:#0f172a; color:#ffffff; border:none; padding:7px 16px; font-size:12.5px; font-weight:700; display:inline-flex; align-items:center; gap:6px;"><i data-lucide="plus" style="width:14px;"></i> New Application</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- EXECUTIVE TAB NAVIGATION STRIP -->
    <div class="executive-tab-strip">
        <button type="button" class="executive-tab-btn active" id="tab-btn-overview" onclick="switchExecutiveTab('overview')">
            <i data-lucide="layout-dashboard" style="width:15px;"></i>
            <span>Executive Overview</span>
            <span class="tab-pill-badge" id="tab-badge-overview"><?= $is_admin ? 'Pulse' : 'Active' ?></span>
        </button>
        <button type="button" class="executive-tab-btn" id="tab-btn-pipeline" onclick="switchExecutiveTab('pipeline')">
            <i data-lucide="folder-check" style="width:15px;"></i>
            <span>Loan Pipeline &amp; Workloads</span>
            <span class="tab-pill-badge" id="tab-badge-pipeline">0 Cases</span>
        </button>
        <button type="button" class="executive-tab-btn" id="tab-btn-compliance" onclick="switchExecutiveTab('compliance')">
            <i data-lucide="<?= $is_admin ? 'clipboard-check' : 'help-circle' ?>" style="width:15px;"></i>
            <span><?= $is_admin ? 'Directives &amp; Compliance' : 'Compliance &amp; Query Desk' ?></span>
            <span class="tab-pill-badge" id="tab-badge-compliance" style="background:#e0f2fe; color:#0369a1;">0 <?= $is_admin ? 'Directives' : 'Queries' ?></span>
        </button>
        <button type="button" class="executive-tab-btn" id="tab-btn-audit" onclick="switchExecutiveTab('audit')">
            <i data-lucide="activity" style="width:15px;"></i>
            <span>Operational Audit</span>
            <span class="tab-pill-badge" id="tab-badge-audit">Live</span>
        </button>
        <button type="button" class="executive-tab-btn" id="tab-btn-payouts" onclick="switchExecutiveTab('payouts')">
            <i data-lucide="coins" style="width:15px;"></i>
            <span><?= $is_admin ? 'Team Accruals &amp; Ledger' : 'My Earnings &amp; Wallet' ?></span>
            <span class="tab-pill-badge" id="tab-badge-payouts">Ledger</span>
        </button>
    </div>

    <!-- ==================== TAB 1: EXECUTIVE OVERVIEW ==================== -->
    <div id="tab-pane-overview" class="executive-tab-pane">
        <!-- RM HERO CARD -->
        <div class="rm-hero-card">
        <div style="display:grid; grid-template-columns: 1.35fr 1fr 1fr; gap:20px; align-items:stretch;">
            <div style="display:flex; flex-direction:column; justify-content:space-between;">
                <div style="display:flex; justify-content:space-between; align-items:baseline;">
                    <div>
                        <span style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#94a3b8;" id="hero-target-label">Monthly Disbursal Target</span>
                        <div style="font-size:26px; font-weight:800; font-family:'Outfit', sans-serif; color:#ffffff; margin-top:2px;" id="hero-mtd-disbursed">₹0 <span style="font-size:15px; font-weight:500; color:#94a3b8;">/ <span id="hero-monthly-target">₹1.50 Cr</span></span></div>
                    </div>
                    <div style="text-align:right;">
                        <span style="font-size:18px; font-weight:800; color:#38bdf8;" id="hero-progress-pct">0%</span>
                        <div style="font-size:11px; color:#94a3b8;">Achieved MTD</div>
                    </div>
                </div>

                <div class="rm-target-progress-bar" style="margin:12px 0 8px 0;">
                    <div class="rm-target-progress-fill" id="hero-progress-bar" style="width: 0%;"></div>
                </div>

                <div style="display:flex; justify-content:space-between; font-size:12px; color:#cbd5e1;">
                    <div>Target Gap: <strong style="color:#f87171;" id="hero-target-gap">₹0</strong></div>
                    <div>Disbursed Cases: <strong style="color:#4ade80;" id="hero-disbursed-count">0</strong></div>
                </div>
            </div>

            <!-- Incentive Meter Box -->
            <div style="background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.1); border-radius:12px; padding:16px 20px; display:flex; align-items:center; gap:16px;">
                <div style="width:44px; height:44px; border-radius:10px; background:rgba(16, 185, 129, 0.2); color:#4ade80; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <i data-lucide="wallet" style="width:22px; height:22px;"></i>
                </div>
                <div>
                    <span style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#94a3b8;" id="hero-incentive-label">Est. Incentive Earned (MTD)</span>
                    <div style="font-size:22px; font-weight:800; font-family:'Outfit', sans-serif; color:#4ade80; margin-top:2px;" id="hero-incentive-earned">₹0</div>
                    <div style="font-size:11.5px; color:#cbd5e1; margin-top:2px;">Commission Rate: <strong id="hero-commission-rate">0.20%</strong></div>
                </div>
            </div>

            <!-- Visual Telemetry Gauge Box -->
            <div style="background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.1); border-radius:12px; padding:16px 20px; display:flex; align-items:center; justify-content:space-between; gap:16px;">
                <div style="width:76px; height:76px; position:relative; flex-shrink:0;">
                    <canvas id="heroTargetChart" width="76" height="76"></canvas>
                    <div style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); text-align:center; pointer-events:none;">
                        <div style="font-size:13px; font-weight:800; color:#38bdf8;" id="chart-center-pct">0%</div>
                    </div>
                </div>
                <div style="flex:1; font-size:11.5px; color:#cbd5e1; display:flex; flex-direction:column; gap:6px;">
                    <span style="font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#94a3b8;">Disbursal Telemetry</span>
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="display:flex; align-items:center; gap:5px;"><span style="width:7px; height:7px; border-radius:50%; background:#10b981; display:inline-block;"></span> Disbursed</span>
                        <strong style="color:#ffffff; font-family:'Outfit',sans-serif;" id="chart-disb-text">₹0</strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="display:flex; align-items:center; gap:5px;"><span style="width:7px; height:7px; border-radius:50%; background:#38bdf8; display:inline-block;"></span> Target Gap</span>
                        <strong style="color:#ffffff; font-family:'Outfit',sans-serif;" id="chart-gap-text">₹0</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 4 High-Impact Daily Priority Triggers -->
    <div style="margin-bottom:12px;">
        <h2 style="font-size:14px; font-weight:800; text-transform:uppercase; letter-spacing:0.5px; color:#475569; margin-bottom:12px; display:flex; align-items:center; gap:6px;">
            <i data-lucide="zap" style="width:15px; color:#f59e0b;"></i> Daily Action Priorities
        </h2>
    </div>
    
    <div class="action-trigger-grid">
        <!-- Trigger 1: Calls Due -->
        <div class="action-trigger-card" id="trigger-card-calls" onclick="filterByTrigger('calls')">
            <div class="trigger-icon-box" style="background:#eff6ff; color:#2563eb; border:1px solid #bfdbfe;">
                <i data-lucide="phone-call" style="width:20px;"></i>
            </div>
            <div>
                <div style="font-size:20px; font-weight:800; color:#0f172a;" id="trigger-calls-count">0</div>
                <div style="font-size:12px; font-weight:700; color:#334155;">Calls / Reminders Due</div>
                <div style="font-size:11px; color:#64748b;">Scheduled for follow-up today</div>
            </div>
        </div>

        <!-- Trigger 2: Missing Docs -->
        <div class="action-trigger-card" id="trigger-card-docs" onclick="filterByTrigger('docs')">
            <div class="trigger-icon-box" style="background:#fffbeb; color:#d97706; border:1px solid #fde68a;">
                <i data-lucide="file-warning" style="width:20px;"></i>
            </div>
            <div>
                <div style="font-size:20px; font-weight:800; color:#0f172a;" id="trigger-docs-count">0</div>
                <div style="font-size:12px; font-weight:700; color:#334155;">Doc Pendencies</div>
                <div style="font-size:11px; color:#64748b;">Awaiting borrower KYC & income</div>
            </div>
        </div>

        <!-- Trigger 3: Login Fees Due -->
        <div class="action-trigger-card" id="trigger-card-loginfee" onclick="filterByTrigger('loginfee')">
            <div class="trigger-icon-box" style="background:#f0fdfa; color:#0f766e; border:1px solid #99f6e4;">
                <i data-lucide="credit-card" style="width:20px;"></i>
            </div>
            <div>
                <div style="font-size:20px; font-weight:800; color:#0f172a;" id="trigger-fee-count">0</div>
                <div style="font-size:12px; font-weight:700; color:#334155;">Bank Processing Fees Due</div>
                <div style="font-size:11px; color:#64748b;">Pre-requisite for bank login</div>
            </div>
        </div>

        <!-- Trigger 4: Sanctions Ready -->
        <div class="action-trigger-card" id="trigger-card-sanctions" onclick="filterByTrigger('sanctions')">
            <div class="trigger-icon-box" style="background:#f0fdf4; color:#16a34a; border:1px solid #bbf7d0;">
                <i data-lucide="award" style="width:20px;"></i>
            </div>
            <div>
                <div style="font-size:20px; font-weight:800; color:#0f172a;" id="trigger-sanctions-count">0</div>
                <div style="font-size:12px; font-weight:700; color:#334155;">Sanctioned Approvals</div>
                <div style="font-size:11px; color:#64748b;">Pending agreement & disbursal</div>
            </div>
        </div>
    </div>

    <!-- MASTER RM TEAM PERFORMANCE & CAPACITY MATRIX (Visible in Admin Master View) -->
    <div id="master-rm-matrix-card" style="display:none; margin-bottom:24px; background:#ffffff; border-radius:12px; border:1px solid #e2e8f0; padding:20px; box-shadow:0 2px 6px rgba(0,0,0,0.02);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:12px;">
            <div>
                <h3 style="font-size:16px; font-weight:800; color:#0f172a; margin:0; display:flex; align-items:center; gap:8px;">
                    <i data-lucide="layout-grid" style="width:18px; color:#0284c7;"></i>
                    Officer Performance &amp; Capacity Matrix
                    <span class="badge" style="background:#e0f2fe; color:#0369a1;" id="master-rm-count-badge">0 Officers</span>
                </h3>
                <p style="font-size:12px; color:#64748b; margin:2px 0 0 0;">Live portfolio breakdown, active pipeline volume, MTD disbursal metrics, and operational capacity across officers.</p>
            </div>
        </div>
        <div style="overflow-x:auto;">
            <table class="data-table" style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr>
                        <th>RM / Staff Officer</th>
                        <th>Assigned Leads</th>
                        <th>Workload &amp; Capacity</th>
                        <th>MTD Disbursed</th>
                        <th>Target Progress</th>
                        <th>Operational Actions Due</th>
                        <th>Est. Incentive</th>
                        <th>Status</th>
                        <th style="text-align:right;">Officer Actions</th>
                    </tr>
                </thead>
                <tbody id="master-rm-matrix-tbody">
                    <tr><td colspan="9" style="text-align:center; padding:20px; color:#94a3b8;">Loading RM Performance Matrix...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    </div><!-- /tab-pane-overview -->

    <!-- ==================== TAB 2: LOAN PIPELINE & CASES ==================== -->
    <div id="tab-pane-pipeline" class="executive-tab-pane" style="display:none;">
        <!-- 5-Stage Loan Pipeline Funnel Strip -->
        <div style="margin-bottom:12px;">
        <h2 style="font-size:14px; font-weight:800; text-transform:uppercase; letter-spacing:0.5px; color:#475569; margin-bottom:12px; display:flex; align-items:center; gap:6px;">
            <i data-lucide="layers" style="width:16px; color:#0284c7;"></i> Loan Lifecycle Stage Velocity
        </h2>
    </div>

    <div class="loan-funnel-strip">
        <div class="funnel-step active" id="funnel-all" onclick="filterByPhase('ALL')">
            <div class="funnel-step-count" id="funnel-count-all">0</div>
            <div class="funnel-step-name">All Cases</div>
        </div>
        <div class="funnel-step" id="funnel-phase1" onclick="filterByPhase('Phase 1')">
            <div class="funnel-step-count" id="funnel-count-phase1">0</div>
            <div class="funnel-step-name">1. Registration</div>
        </div>
        <div class="funnel-step" id="funnel-phase2" onclick="filterByPhase('Phase 2')">
            <div class="funnel-step-count" id="funnel-count-phase2">0</div>
            <div class="funnel-step-name">2. Documentation</div>
        </div>
        <div class="funnel-step" id="funnel-phase3" onclick="filterByPhase('Phase 3')">
            <div class="funnel-step-count" id="funnel-count-phase3">0</div>
            <div class="funnel-step-name">3. Bank Login</div>
        </div>
        <div class="funnel-step" id="funnel-phase4" onclick="filterByPhase('Phase 4')">
            <div class="funnel-step-count" id="funnel-count-phase4">0</div>
            <div class="funnel-step-name">4. Sanctioned</div>
        </div>
        <div class="funnel-step" id="funnel-completed" onclick="filterByPhase('Completed')">
            <div class="funnel-step-count" id="funnel-count-completed">0</div>
            <div class="funnel-step-name">5. Disbursed</div>
        </div>
    </div>

    <!-- Main Assigned Loan Files Table -->
    <div class="table-container" id="cases-table-container">
        <!-- Active Triage Filter Alert Banner -->
        <div id="triage-filter-banner" style="display:none; background:#eff6ff; border:1px solid #bfdbfe; border-radius:10px; padding:12px 18px; margin-bottom:16px; align-items:center; justify-content:space-between; box-shadow:0 1px 3px rgba(0,0,0,0.04);">
            <div style="display:flex; align-items:center; gap:10px;">
                <div style="width:28px; height:28px; border-radius:50%; background:#dbeafe; color:#1d4ed8; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <i data-lucide="filter" style="width:14px;"></i>
                </div>
                <div>
                    <div style="font-size:13px; font-weight:800; color:#1e3a8a;" id="triage-banner-title">Active Filter</div>
                    <div style="font-size:11.5px; color:#3b82f6;" id="triage-banner-subtitle">Showing filtered cases</div>
                </div>
            </div>
            <button type="button" onclick="clearTriageFilters()" class="btn btn-secondary" style="padding:5px 12px; font-size:12px; font-weight:700; color:#1e3a8a; background:#ffffff; border:1px solid #bfdbfe; cursor:pointer;"><i data-lucide="x" style="width:12px;"></i> Clear Filter</button>
        </div>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:12px;">
            <div>
                <h3 style="font-size:16px; font-weight:800; color:#0f172a; margin:0; display:flex; align-items:center; gap:8px;" id="cases-table-title">
                    <i data-lucide="folder-check" style="width:18px; color:#0f766e;"></i>
                    Assigned Loan Cases
                    <span class="badge" style="background:#f1f5f9; color:#475569;" id="table-filtered-badge">0 files</span>
                </h3>
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <input type="text" id="rm-search-input" oninput="renderFilesTable()" placeholder="Search applicant name, loan ID, mobile..." style="padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:12.5px; width:240px;">
                <select id="rm-priority-filter" onchange="renderFilesTable()" style="padding:8px 10px; border:1px solid #cbd5e1; border-radius:6px; font-size:12.5px;">
                    <option value="">All Priorities</option>
                    <option value="Urgent">Urgent</option>
                    <option value="High">High</option>
                    <option value="Normal">Normal</option>
                </select>
            </div>
        </div>

        <div style="overflow-x:auto;">
            <table class="data-table" style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr>
                        <th>Applicant Details</th>
                        <th>Loan Type & Amount</th>
                        <th>Current Stage</th>
                        <th>Bank / Sanction</th>
                        <th>Assigned RM</th>
                        <th>TAT Age</th>
                        <th>Priority</th>
                        <th>Next Follow-up</th>
                        <th style="text-align:right;"><?= $is_admin ? 'Supervisory Actions' : 'RM Quick Actions' ?></th>
                    </tr>
                </thead>
                <tbody id="rm-files-tbody">
                    <tr>
                        <td colspan="9" style="text-align:center; padding:30px; color:#94a3b8;">
                            <div class="skeleton skeleton-text" style="width:200px; margin:auto;"></div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    </div><!-- /tab-pane-pipeline -->

    <!-- ==================== TAB 3: COMPLIANCE & DIRECTIVES ==================== -->
    <div id="tab-pane-compliance" class="executive-tab-pane" style="display:none;">
        <!-- ADMIN RM DIRECTIVES & COMPLIANCE DESK PANEL -->
        <div id="admin-rm-query-panel" style="margin-bottom:24px; background:#ffffff; border-radius:12px; border:1px solid #e2e8f0; padding:20px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:10px;">
            <div>
                <h3 style="font-size:15px; font-weight:800; color:#0f172a; margin:0; display:flex; align-items:center; gap:8px;">
                    <i data-lucide="<?= $is_admin ? 'clipboard-check' : 'help-circle' ?>" style="width:18px; color:#0284c7;"></i>
                    <?= $is_admin ? 'Supervisory Directives &amp; Compliance Oversight' : 'Compliance Audit &amp; RM Inquiry Desk' ?>
                    <span class="badge" style="background:#e0f2fe; color:#0369a1; border:1px solid #bae6fd;" id="query-desk-badge">0 <?= $is_admin ? 'Directives' : 'Queries' ?></span>
                </h3>
                <p style="font-size:12px; color:#64748b; margin:2px 0 0 0;"><?= $is_admin ? 'Official operational directives, compliance mandates, and priority instructions dispatched to Relationship Managers with fulfillment tracking.' : 'Official supervisory inquiries dispatched to Relationship Managers regarding stalled cases, documentation gaps, and audit reviews.' ?></p>
            </div>
            <div style="display:flex; gap:8px;">
                <select id="query-filter-status" onchange="loadRmQueriesDesk()" style="padding:6px 10px; border:1px solid #cbd5e1; border-radius:6px; font-size:12px;">
                    <option value="ALL">All <?= $is_admin ? 'Directives' : 'Queries' ?></option>
                    <option value="Open"><?= $is_admin ? 'Pending Officer Action (Open)' : 'Action Required (Open)' ?></option>
                    <option value="Resolved">Resolved / Closed</option>
                </select>
                <?php if ($is_admin): ?>
                    <button onclick="openRaiseRmQueryModal()" class="btn btn-primary" style="padding:6px 14px; font-size:12px; background:#0f172a; border:1px solid #1e293b; color:#fff; font-weight:700; display:inline-flex; align-items:center; gap:5px;"><i data-lucide="send" style="width:13px; color:#38bdf8;"></i> Issue Directive</button>
                <?php endif; ?>
            </div>
        </div>

        <div style="overflow-x:auto;">
            <table class="data-table" style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr>
                        <th><?= $is_admin ? 'Directive Title &amp; Instructions' : 'Query Title &amp; Details' ?></th>
                        <th>Target Officer</th>
                        <th>Loan Case Ref</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th><?= $is_admin ? 'Date Issued' : 'Date Raised' ?></th>
                        <th><?= $is_admin ? 'Officer Response / Compliance Notes' : 'RM Reply / Response' ?></th>
                        <th style="text-align:right;">Action</th>
                    </tr>
                </thead>
                <tbody id="rm-queries-desk-tbody">
                    <tr><td colspan="8" style="text-align:center; padding:20px; color:#94a3b8;">Loading internal <?= $is_admin ? 'directives' : 'queries' ?>...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    </div><!-- /tab-pane-compliance -->

    <!-- ==================== TAB 4: OPERATIONAL AUDIT STREAM ==================== -->
    <div id="tab-pane-audit" class="executive-tab-pane" style="display:none;">
        <!-- Real-Time Operational Activity & Audit Telemetry -->
        <div style="margin-top:24px; background:#ffffff; border-radius:12px; border:1px solid #e2e8f0; padding:20px; box-shadow:0 1px 3px rgba(0,0,0,0.02);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:12px;">
            <div>
                <h3 style="font-size:16px; font-weight:800; color:#0f172a; margin:0; display:flex; align-items:center; gap:8px;">
                    <i data-lucide="activity" style="width:18px; color:#0284c7;"></i>
                    Real-Time Operational Activity &amp; Audit Stream
                    <span class="badge" style="background:#e0f2fe; color:#0369a1; font-weight:700;" id="activity-stream-count-badge">0 Events</span>
                </h3>
                <p style="font-size:12px; color:#64748b; margin:2px 0 0 0;">Live audit timeline of bank file allocations, KYC approvals, payouts, and system operations.</p>
            </div>
            
            <!-- Category Filter Buttons -->
            <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                <div style="display:inline-flex; background:#f1f5f9; padding:3px; border-radius:8px; border:1px solid #e2e8f0;">
                    <button type="button" class="activity-tab-btn active" onclick="filterActivityStream('ALL', this)" style="padding:4px 10px; font-size:11.5px; font-weight:700; border:none; border-radius:6px; cursor:pointer; background:#ffffff; color:#0f172a; box-shadow:0 1px 2px rgba(0,0,0,0.05);">All</button>
                    <button type="button" class="activity-tab-btn" onclick="filterActivityStream('LOAN', this)" style="padding:4px 10px; font-size:11.5px; font-weight:600; border:none; border-radius:6px; cursor:pointer; background:transparent; color:#64748b;">Loans &amp; Bank</button>
                    <button type="button" class="activity-tab-btn" onclick="filterActivityStream('DOCS', this)" style="padding:4px 10px; font-size:11.5px; font-weight:600; border:none; border-radius:6px; cursor:pointer; background:transparent; color:#64748b;">Docs &amp; KYC</button>
                    <button type="button" class="activity-tab-btn" onclick="filterActivityStream('PAYOUT', this)" style="padding:4px 10px; font-size:11.5px; font-weight:600; border:none; border-radius:6px; cursor:pointer; background:transparent; color:#64748b;">Payouts &amp; Fees</button>
                    <button type="button" class="activity-tab-btn" onclick="filterActivityStream('AUTH', this)" style="padding:4px 10px; font-size:11.5px; font-weight:600; border:none; border-radius:6px; cursor:pointer; background:transparent; color:#64748b;">Security &amp; Staff</button>
                </div>
            </div>
        </div>

        <div id="rm-recent-activities" style="max-height:460px; overflow-y:auto; padding-right:6px;">
            <p style="color:#94a3b8; font-size:12.5px; text-align:center; padding:20px;">Loading operational activities...</p>
        </div>
    </div>
    </div><!-- /tab-pane-audit -->

    <!-- ==================== TAB 5: TEAM ACCRUALS & WALLET LEDGER ==================== -->
    <div id="tab-pane-payouts" class="executive-tab-pane" style="display:none;">
        <!-- ===== MY WALLET / PAYOUT SECTION ===== -->
        <div style="margin-top:10px;" id="rm-wallet-section">
            <h2 id="wallet-section-title" style="font-size:14px; font-weight:800; text-transform:uppercase; letter-spacing:0.5px; color:#475569; margin-bottom:14px; display:flex; align-items:center; gap:6px;">
                <i data-lucide="wallet" style="width:16px; color:#10b981;"></i> My Earnings &amp; Wallet
            </h2>

            <!-- Wallet Balance Cards -->
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px,1fr)); gap:14px; margin-bottom:20px;">
                <div style="background:linear-gradient(135deg,#0f172a 0%,#1e293b 100%); border-radius:14px; padding:20px; color:#fff; position:relative; overflow:hidden;">
                    <div style="position:absolute;top:-20px;right:-20px;width:80px;height:80px;background:rgba(16,185,129,0.15);border-radius:50%;"></div>
                    <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#94a3b8; margin-bottom:6px;">Total Commission Earned</div>
                    <div style="font-size:26px; font-weight:900; color:#4ade80; font-family:'Outfit',sans-serif;" id="wallet-total-earned">&#x20B9;0</div>
                    <div style="font-size:11px; color:#64748b; margin-top:4px;">All time (Gross)</div>
                </div>
                <div style="background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:20px; border-left:4px solid #10b981;">
                    <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#64748b; margin-bottom:6px;">Amount Received &#x2705;</div>
                    <div style="font-size:26px; font-weight:900; color:#10b981; font-family:'Outfit',sans-serif;" id="wallet-received">&#x20B9;0</div>
                    <div style="font-size:11px; color:#94a3b8; margin-top:4px;">Credited to account</div>
                </div>
                <div style="background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:20px; border-left:4px solid #f59e0b;">
                    <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#64748b; margin-bottom:6px;">Pending Payout &#x23F3;</div>
                    <div style="font-size:26px; font-weight:900; color:#d97706; font-family:'Outfit',sans-serif;" id="wallet-pending">&#x20B9;0</div>
                    <div style="font-size:11px; color:#94a3b8; margin-top:4px;">Awaiting Admin release</div>
                </div>
                <div style="background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:20px; border-left:4px solid #0284c7;">
                    <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#64748b; margin-bottom:6px;">This Month &#x1F4C5;</div>
                    <div style="font-size:26px; font-weight:900; color:#0284c7; font-family:'Outfit',sans-serif;" id="wallet-this-month">&#x20B9;0</div>
                    <div style="font-size:11px; color:#94a3b8; margin-top:4px;" id="wallet-month-label">Current month earnings</div>
                </div>
            </div>

            <div style="background:#fff; border-radius:12px; border:1px solid #e2e8f0; overflow:hidden;">
                <div style="padding:16px 20px; border-bottom:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center;">
                    <h3 style="font-size:15px; font-weight:800; color:#0f172a; margin:0; display:flex; align-items:center; gap:8px;">
                        <i data-lucide="list" style="width:16px; color:#0284c7;"></i>
                        Wallet Transaction History
                    </h3>
                    <select id="wallet-filter-status" onchange="loadWalletHistory()" style="padding:6px 10px; border:1px solid #cbd5e1; border-radius:6px; font-size:12px;">
                        <option value="">All Transactions</option>
                        <option value="Pending">&#x23F3; Pending</option>
                        <option value="Paid">&#x2705; Paid</option>
                        <option value="Cancelled">&#x274C; Cancelled</option>
                    </select>
                </div>
                <div style="overflow-x:auto;">
                    <table style="width:100%; border-collapse:collapse; font-size:13px;">
                        <thead>
                            <tr style="background:#f8fafc;">
                                <th style="padding:10px 16px; text-align:left; font-size:11px; font-weight:700; text-transform:uppercase; color:#64748b; border-bottom:1px solid #e2e8f0;">Loan / Applicant</th>
                                <th style="padding:10px 16px; text-align:right; font-size:11px; font-weight:700; text-transform:uppercase; color:#64748b; border-bottom:1px solid #e2e8f0;">Loan Amount</th>
                                <th style="padding:10px 16px; text-align:right; font-size:11px; font-weight:700; text-transform:uppercase; color:#64748b; border-bottom:1px solid #e2e8f0;">Gross</th>
                                <th style="padding:10px 16px; text-align:right; font-size:11px; font-weight:700; text-transform:uppercase; color:#64748b; border-bottom:1px solid #e2e8f0;">TDS</th>
                                <th style="padding:10px 16px; text-align:right; font-size:11px; font-weight:700; text-transform:uppercase; color:#64748b; border-bottom:1px solid #e2e8f0;">Net Payable</th>
                                <th style="padding:10px 16px; text-align:center; font-size:11px; font-weight:700; text-transform:uppercase; color:#64748b; border-bottom:1px solid #e2e8f0;">Status</th>
                                <th style="padding:10px 16px; text-align:left; font-size:11px; font-weight:700; text-transform:uppercase; color:#64748b; border-bottom:1px solid #e2e8f0;">Date</th>
                            </tr>
                        </thead>
                        <tbody id="wallet-history-tbody">
                            <tr><td colspan="7" style="text-align:center; padding:30px; color:#94a3b8; font-size:13px;">Loading wallet history...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div id="wallet-pagination" style="padding:12px 16px; border-top:1px solid #f1f5f9; display:flex; justify-content:center; gap:6px;"></div>
            </div>
        </div>
    </div><!-- /tab-pane-payouts -->

</div><!-- /view-container -->

<!-- MODAL 1: ASSIGN TASK & OPERATIONAL DIRECTIVE MODAL -->
<div id="raise-rm-query-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.85); z-index:9999; justify-content:center; align-items:center; backdrop-filter:blur(4px);">
    <div style="background:#fff; border-radius:14px; width:90%; max-width:620px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.3); overflow:hidden; border:1px solid #1e293b;">
        <div style="padding:16px 22px; background:#0f172a; color:#fff; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #1e293b;">
            <div style="display:flex; align-items:center; gap:10px;">
                <div style="width:34px; height:34px; border-radius:8px; background:#0284c7; display:flex; align-items:center; justify-content:center; color:#fff;">
                    <i data-lucide="check-square" style="width:18px;"></i>
                </div>
                <div>
                    <h3 style="font-weight:800; font-size:16px; margin:0; color:#ffffff;"><?= $is_admin ? 'Assign Task &amp; Directive to RM' : 'Raise Query to Management' ?></h3>
                    <p style="font-size:11.5px; color:#94a3b8; margin:2px 0 0 0;"><?= $is_admin ? 'Assign operational tasks, deadlines &amp; directives directly into RM reminders' : 'Submit clarification request regarding loan cases or pendencies' ?></p>
                </div>
            </div>
            <button onclick="closeRaiseRmQueryModal()" style="background:none; border:none; color:#94a3b8; cursor:pointer; font-size:24px; line-height:1; padding:0 4px;">&times;</button>
        </div>
        <form id="raise-rm-query-form" onsubmit="submitRaiseRmQuery(event)" style="padding:22px;">
            <div style="margin-bottom:14px;">
                <label class="required" style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Target Officer / Audience</label>
                <select name="assigned_rm" id="query_target_rm" required style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; box-sizing:border-box; background:#f8fafc;">
                    <?php if ($is_admin): ?>
                        <option value="ALL_RMS" style="font-weight:700; color:#0284c7;">📢 Broadcast to ALL Officers (All Relationship Managers)</option>
                    <?php endif; ?>
                    <option value="">-- Choose Individual Officer --</option>
                    <?php foreach ($all_rms as $rm): 
                        $disp = !empty($rm['full_name']) ? $rm['full_name'] : (!empty($rm['name']) ? $rm['name'] : $rm['username']);
                    ?>
                    <option value="<?= htmlspecialchars($rm['username']) ?>"><?= htmlspecialchars($disp) ?> (<?= htmlspecialchars($rm['username']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Task Quick Chips -->
            <div style="margin-bottom:14px;">
                <label style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px; display:block;">Quick Task Presets</label>
                <div style="display:flex; gap:6px; flex-wrap:wrap;">
                    <button type="button" onclick="applyDirectivePreset('Follow-up', 'Urgent Pipeline Follow-up Order: Contact Pending Borrowers Today')" style="padding:4px 8px; font-size:11px; font-weight:600; border-radius:6px; background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; cursor:pointer;">📞 Follow-up Call Task</button>
                    <button type="button" onclick="applyDirectivePreset('Doc Pendency', 'Document Clearance Mandate: Clear Missing KYC &amp; Income Papers within 24h')" style="padding:4px 8px; font-size:11px; font-weight:600; border-radius:6px; background:#fffbeb; color:#b45309; border:1px solid #fde68a; cursor:pointer;">📄 Doc Pendency Mandate</button>
                    <button type="button" onclick="applyDirectivePreset('Login Fee', 'Bank Login Mandate: Collect Processing Fees &amp; Submit Bank Files')" style="padding:4px 8px; font-size:11px; font-weight:600; border-radius:6px; background:#f0fdfa; color:#0f766e; border:1px solid #99f6e4; cursor:pointer;">💳 Processing Fee Push</button>
                    <button type="button" onclick="applyDirectivePreset('Disbursal', 'Month-End Disbursal Push: Execute Loan Agreements for Sanctioned Files')" style="padding:4px 8px; font-size:11px; font-weight:600; border-radius:6px; background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0; cursor:pointer;">🎯 Disbursal Target Push</button>
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                <div>
                    <label style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Priority Level</label>
                    <select name="priority" id="query_priority" style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; box-sizing:border-box;">
                        <option value="Normal">Routine Task</option>
                        <option value="High" selected>High Priority (24h Action)</option>
                        <option value="Urgent">Urgent Escalation (Immediate Action)</option>
                    </select>
                </div>
                <div>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                        <label style="font-size:12px; font-weight:700; color:#334155;">Completion Deadline</label>
                        <div style="display:inline-flex; gap:4px;">
                            <button type="button" onclick="setTaskDeadlinePreset(4)" style="border:none; background:#f1f5f9; color:#475569; font-size:10px; font-weight:700; padding:1px 5px; border-radius:4px; cursor:pointer;">Today 6PM</button>
                            <button type="button" onclick="setTaskDeadlinePreset(24)" style="border:none; background:#f1f5f9; color:#475569; font-size:10px; font-weight:700; padding:1px 5px; border-radius:4px; cursor:pointer;">Tomorrow</button>
                            <button type="button" onclick="setTaskDeadlinePreset(48)" style="border:none; background:#f1f5f9; color:#475569; font-size:10px; font-weight:700; padding:1px 5px; border-radius:4px; cursor:pointer;">48h</button>
                        </div>
                    </div>
                    <input type="datetime-local" name="due_date" id="query_due_date" style="width:100%; padding:8.5px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; box-sizing:border-box;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 2fr; gap:14px; margin-bottom:14px;">
                <div>
                    <label style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Applicant / Loan ID (Optional)</label>
                    <input type="number" name="applicant_id" id="query_applicant_id" placeholder="e.g. 16" style="width:100%; padding:8.5px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; box-sizing:border-box;">
                </div>
                <div>
                    <label class="required" style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Task Title / Subject</label>
                    <input type="text" name="query_title" id="query_title" required placeholder="e.g. Urgently contact borrower to collect pending bank statements..." style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; box-sizing:border-box;">
                </div>
            </div>

            <div style="margin-bottom:20px;">
                <label class="required" style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Mandatory Instructions &amp; Action Required</label>
                <textarea name="query_description" id="query_description" required rows="4" placeholder="Specify exact orders, required borrower documents, and fulfillment deadline for the officer..." style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; box-sizing:border-box; font-family:inherit;"></textarea>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="closeRaiseRmQueryModal()" class="btn btn-secondary" style="padding:8px 16px;">Cancel</button>
                <button type="submit" id="submit_query_btn" class="btn btn-primary" style="padding:9px 22px; font-weight:700; background:#0f172a; border:1px solid #1e293b; color:#fff; display:inline-flex; align-items:center; gap:6px;"><i data-lucide="check-square" style="width:14px; color:#38bdf8;"></i> <?= $is_admin ? 'Assign Task to RM' : 'Submit Query' ?></button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 2: 360° RM INTELLIGENCE DOSSIER MODAL (Operational Activity Telemetry) -->
<div id="rm-dossier-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.85); z-index:9999; justify-content:center; align-items:center; backdrop-filter:blur(5px);">
    <div style="background:#fff; border-radius:16px; width:95%; max-width:900px; height:90vh; display:flex; flex-direction:column; box-shadow:0 25px 50px -12px rgba(0,0,0,0.3); overflow:hidden;">
        <!-- Dossier Header -->
        <div style="padding:18px 24px; background:#0f172a; color:#fff; display:flex; justify-content:space-between; align-items:center; flex-shrink:0;">
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="width:42px; height:42px; border-radius:10px; background:#0284c7; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:18px;" id="dossier-avatar">RM</div>
                <div>
                    <h2 style="font-size:18px; font-weight:800; margin:0; display:flex; align-items:center; gap:8px;" id="dossier-title">Relationship Manager Dossier</h2>
                    <p style="font-size:12px; color:#94a3b8; margin:2px 0 0 0;" id="dossier-subtitle">360° Activity Audit, Assigned Portfolio & Compliance History</p>
                </div>
            </div>
            <button onclick="closeRmDossierModal()" style="background:none; border:none; color:#fff; cursor:pointer; font-size:24px; line-height:1;">&times;</button>
        </div>

        <!-- Dossier Sub-nav Tabs -->
        <div style="display:flex; background:#f8fafc; border-bottom:1px solid #e2e8f0; padding:0 24px; flex-shrink:0;">
            <button class="dossier-tab-btn active" id="tab-btn-portfolio" onclick="switchDossierTab('portfolio')"><i data-lucide="folder" style="width:14px; vertical-align:-1px;"></i> Assigned Portfolio</button>
            <button class="dossier-tab-btn" id="tab-btn-activities" onclick="switchDossierTab('activities')"><i data-lucide="activity" style="width:14px; vertical-align:-1px;"></i> Operational Activity Audit</button>
            <button class="dossier-tab-btn" id="tab-btn-queries" onclick="switchDossierTab('queries')"><i data-lucide="help-circle" style="width:14px; vertical-align:-1px;"></i> Admin Queries &amp; Compliance</button>
        </div>

        <!-- Dossier Scrollable Content Body -->
        <div style="flex:1; overflow-y:auto; padding:24px;" id="dossier-body-content">
            <div style="text-align:center; padding:40px; color:#94a3b8;">Loading officer intelligence dossier...</div>
        </div>
    </div>
</div>

<!-- MODAL 3: RM QUERY REPLY MODAL -->
<div id="rm-query-reply-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.8); z-index:9999; justify-content:center; align-items:center; backdrop-filter:blur(4px);">
    <div style="background:#fff; border-radius:12px; width:90%; max-width:520px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); overflow:hidden;">
        <div style="padding:16px 20px; background:#0284c7; color:#fff; display:flex; justify-content:space-between; align-items:center;">
            <div style="display:flex; align-items:center; gap:8px;">
                <i data-lucide="message-square" style="width:18px;"></i>
                <span style="font-weight:700; font-size:15px;">Inquiry Response & Resolution</span>
            </div>
            <button onclick="closeRmQueryReplyModal()" style="background:none; border:none; color:#fff; cursor:pointer; font-size:20px; line-height:1;">&times;</button>
        </div>
        <form id="rm-query-reply-form" onsubmit="submitRmQueryReply(event)" style="padding:20px;">
            <input type="hidden" name="query_id" id="reply_query_id" value="">
            
            <div style="background:#f0f9ff; border:1px solid #bae6fd; padding:10px 14px; border-radius:8px; margin-bottom:14px;">
                <div style="font-size:11px; color:#0284c7; font-weight:700; text-transform:uppercase;">Query Subject</div>
                <div id="reply_query_title_display" style="font-size:13.5px; font-weight:700; color:#0f172a; margin-top:2px;">-</div>
            </div>

            <div style="margin-bottom:14px;">
                <label class="required" style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Your Reply / Status Resolution Notes</label>
                <textarea name="rm_reply" id="reply_notes" required rows="4" placeholder="Provide full update or explanation for Admin..." style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; box-sizing:border-box; font-family:inherit;"></textarea>
            </div>

            <div style="margin-bottom:20px;">
                <label style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Set Query Status</label>
                <select name="status" id="reply_status" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px;">
                    <option value="Resolved">Mark as Resolved</option>
                    <option value="In Progress">In Progress / Under Review</option>
                </select>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="closeRmQueryReplyModal()" class="btn btn-secondary" style="padding:8px 16px;">Cancel</button>
                <button type="submit" id="submit_reply_btn" class="btn btn-primary" style="padding:8px 20px; font-weight:700; background:#0284c7; border:none;"><i data-lucide="check" style="width:14px;"></i> Save Reply</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 4: ADMIN REASSIGN LOAN CASE MODAL -->
<div id="reassign-case-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.8); z-index:9999; justify-content:center; align-items:center; backdrop-filter:blur(4px);">
    <div style="background:#fff; border-radius:12px; width:90%; max-width:500px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); overflow:hidden;">
        <div style="padding:16px 20px; background:#4338ca; color:#fff; display:flex; justify-content:space-between; align-items:center;">
            <div style="display:flex; align-items:center; gap:8px;">
                <i data-lucide="user-plus" style="width:18px;"></i>
                <span style="font-weight:700; font-size:15px;">Reassign Loan Case to RM Officer</span>
            </div>
            <button onclick="closeReassignModal()" style="background:none; border:none; color:#fff; cursor:pointer; font-size:20px; line-height:1;">&times;</button>
        </div>
        <form id="reassign-case-form" onsubmit="submitReassignCase(event)" style="padding:20px;">
            <input type="hidden" name="applicant_id" id="reassign_applicant_id" value="">
            
            <div style="background:#eef2ff; border:1px solid #c7d2fe; padding:12px 14px; border-radius:8px; margin-bottom:16px;">
                <div style="font-size:11px; color:#4338ca; font-weight:700; text-transform:uppercase;">Applicant Case</div>
                <div id="reassign_app_name" style="font-size:14px; font-weight:800; color:#0f172a; margin-top:2px;">-</div>
                <div style="font-size:12px; color:#64748b; margin-top:4px;">Currently Assigned: <strong id="reassign_curr_assigned" style="color:#0f172a;">-</strong></div>
            </div>

            <div style="margin-bottom:14px;">
                <label class="required" style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Select Target Relationship Manager (RM)</label>
                <select name="assigned_to" id="reassign_target_rm" required style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px;">
                    <option value="">-- Choose RM Officer --</option>
                    <?php foreach ($all_rms as $rm): 
                        $disp = !empty($rm['full_name']) ? $rm['full_name'] : (!empty($rm['name']) ? $rm['name'] : $rm['username']);
                    ?>
                    <option value="<?= htmlspecialchars($rm['username']) ?>"><?= htmlspecialchars($disp) ?> (<?= htmlspecialchars($rm['username']) ?> - <?= htmlspecialchars($rm['role']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="margin-bottom:20px;">
                <label style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Handover Reason / Instructions (Optional)</label>
                <textarea name="assignment_notes" id="reassign_notes" rows="3" placeholder="Add any specific handover notes or priority instructions..." style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; box-sizing:border-box; font-family:inherit;"></textarea>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="closeReassignModal()" class="btn btn-secondary" style="padding:8px 16px;">Cancel</button>
                <button type="submit" id="submit_reassign_btn" class="btn btn-primary" style="padding:8px 20px; font-weight:700; background:#4338ca; border:none;"><i data-lucide="user-check" style="width:14px;"></i> Confirm Reassignment</button>
            </div>
        </form>
    </div>
</div>

<script>
const basePath = '<?php echo $base_path; ?>';
const isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;
let currentRmUser = isAdmin ? 'ALL' : '<?php echo htmlspecialchars($current_rm); ?>';
let currentRmFilter = null;
let rmDashboardData = null;
let currentPhaseFilter = 'ALL';
let currentTriggerFilter = null;
let activeDossierData = null;
let heroTargetChart = null;
let currentExecutiveTab = 'overview';

function switchExecutiveTab(tabId) {
    currentExecutiveTab = tabId;
    document.querySelectorAll('.executive-tab-pane').forEach(el => {
        el.style.display = 'none';
    });
    document.querySelectorAll('.executive-tab-btn').forEach(b => {
        b.classList.remove('active');
    });

    const targetPane = document.getElementById(`tab-pane-${tabId}`);
    if (targetPane) {
        targetPane.style.display = 'block';
    }

    const targetBtn = document.getElementById(`tab-btn-${tabId}`);
    if (targetBtn) {
        targetBtn.classList.add('active');
    }

    if (tabId === 'compliance') {
        loadRmQueriesDesk();
    } else if (tabId === 'payouts') {
        loadWalletSummary();
        loadWalletHistory();
    } else if (tabId === 'pipeline') {
        renderFilesTable();
    }

    lucide.createIcons();
}

async function loadRmDashboard() {
    try {
        const res = await fetch(`../api.php?api=rm_dashboard_metrics&rm_user=${encodeURIComponent(currentRmUser)}`);
        const data = await res.json();
        
        if (!data || !data.success) {
            showNotification(data.error || 'Failed to load RM metrics', 'error');
            return;
        }

        rmDashboardData = data;
        renderHeroMetrics(data);
        renderTriggers(data);
        renderFunnel(data);
        renderMasterRmMatrix(data.rm_matrix || []);
        renderFilesTable();
        renderRecentActivities(data.recent_activities || []);
        loadRmQueriesDesk();
    } catch (err) {
        console.error("RM Dashboard error:", err);
    }
}

function onAdminRmSelectChange(val) {
    currentRmUser = val;
    currentRmFilter = null;
    currentTriggerFilter = null;
    currentPhaseFilter = 'ALL';
    clearTriageFilters();
    loadRmDashboard();
}

function inspectRmPortfolio(username) {
    const sel = document.getElementById('admin-rm-selector');
    if (sel) {
        sel.value = username;
    }
    currentRmUser = username;
    currentRmFilter = null;
    loadRmDashboard();
    openRmDossierModal(username);
}

function formatIndianCurrency(amount) {
    let x = Math.round(amount || 0).toString();
    let lastThree = x.slice(-3);
    let otherNumbers = x.slice(0, -3);
    if (otherNumbers !== '') lastThree = ',' + lastThree;
    let res = otherNumbers.replace(/\B(?=(\d{2})+(?!\d))/g, ",") + lastThree;
    return '₹' + res;
}

function renderHeroMetrics(d) {
    const target = d.monthly_target || 15000000;
    const mtdDisb = d.mtd_disbursed_val || 0;
    const pct = d.target_progress_pct || 0;
    const gap = Math.max(0, target - mtdDisb);

    const isMaster = d.is_master || currentRmUser === 'ALL';
    const labelEl = document.getElementById('hero-target-label');
    if (labelEl) {
        labelEl.innerText = isMaster ? 'Total Team Disbursal Target (MTD)' : 'Monthly Disbursal Target';
    }

    const incLabel = document.getElementById('hero-incentive-label');
    if (incLabel) {
        incLabel.innerText = isMaster ? 'Est. Team Commission Earned (MTD)' : 'Est. Incentive Earned (MTD)';
    }

    document.getElementById('hero-mtd-disbursed').innerHTML = `${formatIndianCurrency(mtdDisb)} <span style="font-size:15px; font-weight:500; color:#94a3b8;">/ <span id="hero-monthly-target">${formatIndianCurrency(target)}</span></span>`;
    document.getElementById('hero-progress-pct').innerText = `${pct}%`;
    document.getElementById('hero-progress-bar').style.width = `${Math.min(100, pct)}%`;
    document.getElementById('hero-target-gap').innerText = formatIndianCurrency(gap);
    document.getElementById('hero-disbursed-count').innerText = `${d.mtd_disbursed_count || 0} Files`;
    document.getElementById('hero-incentive-earned').innerText = formatIndianCurrency(d.incentive_earned || 0);
    document.getElementById('hero-commission-rate').innerText = isMaster ? 'Team Total' : `${d.commission_rate || 0.20}%`;

    renderHeroChart(d);
}

function renderHeroChart(d) {
    if (typeof Chart === 'undefined') return;
    const target = d.monthly_target || 15000000;
    const mtdDisb = d.mtd_disbursed_val || 0;
    const gap = Math.max(0, target - mtdDisb);
    const pct = d.target_progress_pct || 0;

    const centerEl = document.getElementById('chart-center-pct');
    if (centerEl) centerEl.innerText = `${pct}%`;
    const disbEl = document.getElementById('chart-disb-text');
    if (disbEl) disbEl.innerText = formatIndianCurrency(mtdDisb);
    const gapEl = document.getElementById('chart-gap-text');
    if (gapEl) gapEl.innerText = formatIndianCurrency(gap);

    const canvas = document.getElementById('heroTargetChart');
    if (!canvas) return;

    if (heroTargetChart) {
        heroTargetChart.destroy();
    }

    heroTargetChart = new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: ['Disbursed', 'Target Gap'],
            datasets: [{
                data: [mtdDisb, Math.max(0, gap)],
                backgroundColor: ['#10b981', '#38bdf8'],
                borderWidth: 0,
                hoverOffset: 3
            }]
        },
        options: {
            cutout: '72%',
            responsive: false,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(c) {
                            return ` ${c.label}: ${formatIndianCurrency(c.raw)}`;
                        }
                    }
                }
            }
        }
    });
}

function renderTriggers(d) {
    const t = d.triggers || {};
    document.getElementById('trigger-calls-count').innerText = t.reminders_due || 0;
    document.getElementById('trigger-docs-count').innerText = t.docs_pending || 0;
    document.getElementById('trigger-fee-count').innerText = t.login_fee_due || 0;
    document.getElementById('trigger-sanctions-count').innerText = t.sanctions_pending_disb || 0;
}

function renderMasterRmMatrix(matrix) {
    const card = document.getElementById('master-rm-matrix-card');
    if (!card) return;

    if (!isAdmin || currentRmUser !== 'ALL') {
        card.style.display = 'none';
        return;
    }

    card.style.display = 'block';
    document.getElementById('master-rm-count-badge').innerText = `${matrix.length} Officers`;
    const bOverview = document.getElementById('tab-badge-overview');
    if (bOverview) bOverview.innerText = `${matrix.length} Officers`;

    const tbody = document.getElementById('master-rm-matrix-tbody');
    if (!matrix || matrix.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" style="text-align:center; padding:20px; color:#94a3b8;">No RM team members found.</td></tr>';
        return;
    }

    let html = '';
    matrix.forEach(r => {
        const pct = r.target_progress_pct || 0;
        const roleLabel = r.role === 'RM' ? 'Relationship Manager' : (r.role || 'Officer');
        const capPct = r.capacity_pct || 0;
        const capColor = capPct > 80 ? '#e11d48' : (capPct > 50 ? '#f59e0b' : '#0284c7');

        const actionsTotal = r.actions_due_total || 0;
        const breakdown = [];
        if (r.sanctions_ready > 0) breakdown.push(`${r.sanctions_ready} Sanc`);
        if (r.docs_pending > 0) breakdown.push(`${r.docs_pending} Docs`);
        if (r.fees_due > 0) breakdown.push(`${r.fees_due} Fees`);
        if (r.reminders_due > 0) breakdown.push(`${r.reminders_due} Calls`);
        const subText = breakdown.join(' · ');

        const actionsDueHtml = actionsTotal > 0 
            ? `<div style="white-space:nowrap;">
                <span class="badge" style="background:#fff1f2; color:#be123c; border:1px solid #fecdd3; font-weight:700; font-size:11px; cursor:pointer; display:inline-flex; align-items:center; gap:4px; padding:3px 8px;" onclick="filterCasesByOfficer('${r.username}')" title="Filter ${r.name}'s cases in table">
                    <i data-lucide="alert-circle" style="width:11px;"></i>${actionsTotal} Action${actionsTotal > 1 ? 's' : ''} Due
                </span>
                ${subText ? `<div style="font-size:10.5px; color:#64748b; margin-top:3px; font-weight:600;">${subText}</div>` : ''}
              </div>` 
            : `<span style="font-size:11.5px; color:#15803d; font-weight:700; display:inline-flex; align-items:center; gap:4px; white-space:nowrap;"><i data-lucide="check-circle" style="width:12px;"></i> All Clear</span>`;

        html += `
            <tr style="cursor:pointer;" onclick="inspectRmPortfolio('${r.username}')">
                <td style="white-space:nowrap;">
                    <div style="display:flex; align-items:center; gap:6px;">
                        <span style="font-weight:700; color:#0f172a; font-size:13.5px;">${r.name}</span>
                        <span class="badge" style="background:#f1f5f9; color:#475569; border:1px solid #e2e8f0; font-size:10px; font-weight:700; padding:1px 6px;">${roleLabel}</span>
                    </div>
                    <div style="font-size:11px; color:#64748b; margin-top:2px;">${r.username.toLowerCase()}</div>
                </td>
                <td style="white-space:nowrap;">
                    <span class="badge" style="background:#f8fafc; color:#0f172a; border:1px solid #e2e8f0; font-weight:700; font-size:11.5px; padding:3px 8px;">${r.assigned_leads} ${r.assigned_leads === 1 ? 'Lead' : 'Leads'}</span>
                </td>
                <td style="white-space:nowrap; min-width:150px;">
                    <div style="display:flex; justify-content:space-between; align-items:baseline; margin-bottom:2px;">
                        <span style="font-weight:700; color:#0f172a; font-size:13px;">${formatIndianCurrency(r.active_pipeline_val)}</span>
                        <span style="font-size:11px; font-weight:600; color:#64748b;">${r.active_files} ${r.active_files === 1 ? 'File' : 'Files'}</span>
                    </div>
                    <div style="display:flex; align-items:center; gap:6px;">
                        <div style="flex:1; height:6px; background:#e2e8f0; border-radius:4px; overflow:hidden;">
                            <div style="width:${Math.min(100, capPct)}%; height:100%; background:${capColor}; border-radius:4px;"></div>
                        </div>
                        <span style="font-size:10px; font-weight:700; color:${capColor}; white-space:nowrap;">${r.active_files}/${r.capacity_max || 15} (${capPct}%)</span>
                    </div>
                </td>
                <td style="white-space:nowrap;">
                    <div style="font-weight:800; color:#15803d; font-size:13.5px;">${formatIndianCurrency(r.mtd_disbursed_val)}</div>
                    <div style="font-size:11px; color:#64748b; margin-top:2px;">${r.mtd_disbursed_count} ${r.mtd_disbursed_count === 1 ? 'Case' : 'Cases'} Disbursed</div>
                </td>
                <td style="white-space:nowrap; min-width:155px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; font-size:11px; margin-bottom:3px;">
                        <span style="font-weight:800; color:#0f172a;">${pct}%</span>
                        <span style="color:#64748b; font-weight:500;">Target: ${formatIndianCurrency(r.monthly_target)}</span>
                    </div>
                    <div class="rm-target-progress-bar" style="height:6px; margin:0; background:#e2e8f0; border-radius:4px; overflow:hidden;">
                        <div class="rm-target-progress-fill" style="width:${Math.min(100, pct)}%; height:100%; background:#0284c7; border-radius:4px;"></div>
                    </div>
                </td>
                <td style="white-space:nowrap;">
                    ${actionsDueHtml}
                </td>
                <td style="white-space:nowrap;">
                    <div style="font-weight:800; color:#0f766e; font-size:13px;">${formatIndianCurrency(r.incentive_earned)}</div>
                    <div style="font-size:10.5px; color:#64748b; margin-top:2px;">Comm: ${r.commission_rate}%</div>
                </td>
                <td style="white-space:nowrap;">
                    <span class="badge" style="background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0; font-size:10.5px; font-weight:700; padding:2px 8px; display:inline-flex; align-items:center; gap:3px;"><i data-lucide="check" style="width:10px;"></i> Active</span>
                </td>
                <td style="text-align:right; white-space:nowrap;" onclick="event.stopPropagation();">
                    <div style="display:inline-flex; align-items:center; gap:5px; white-space:nowrap;">
                        <button onclick="filterCasesByOfficer('${r.username}')" class="btn btn-secondary" style="padding:5px 8px; font-size:11px; font-weight:700; color:#0284c7; background:#f0f9ff; border:1px solid #bae6fd; display:inline-flex; align-items:center; gap:4px;" title="View Cases in Table"><i data-lucide="folder-check" style="width:12px;"></i> Files</button>
                        <button onclick="openRaiseRmQueryModal('${r.username}')" class="btn btn-secondary" style="padding:5px 8px; font-size:11px; font-weight:700; color:#0f172a; background:#f8fafc; border:1px solid #cbd5e1; display:inline-flex; align-items:center; gap:4px;" title="Assign Task to ${r.username}"><i data-lucide="check-square" style="width:11px; color:#0284c7;"></i> Assign Task</button>
                        <button onclick="inspectRmPortfolio('${r.username}')" class="btn btn-secondary" style="padding:5px 8px; font-size:11px; font-weight:600; color:#0f172a; background:#ffffff; border:1px solid #cbd5e1; display:inline-flex; align-items:center; gap:4px;" title="Inspect 360° Dossier"><i data-lucide="eye" style="width:12px; color:#0284c7;"></i> Dossier</button>
                    </div>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
    lucide.createIcons();
}

function renderFunnel(d) {
    const p = d.phases || {};
    const total = (p['Phase 1'] || 0) + (p['Phase 2'] || 0) + (p['Phase 3'] || 0) + (p['Phase 4'] || 0) + (p['Completed'] || 0);
    
    document.getElementById('funnel-count-all').innerText = total;
    document.getElementById('funnel-count-phase1').innerText = p['Phase 1'] || 0;
    document.getElementById('funnel-count-phase2').innerText = p['Phase 2'] || 0;
    document.getElementById('funnel-count-phase3').innerText = p['Phase 3'] || 0;
    document.getElementById('funnel-count-phase4').innerText = p['Phase 4'] || 0;
    document.getElementById('funnel-count-completed').innerText = p['Completed'] || 0;
}

function filterByPhase(phase) {
    currentPhaseFilter = phase;
    currentTriggerFilter = null;
    
    document.querySelectorAll('.funnel-step').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.action-trigger-card').forEach(el => el.classList.remove('active-filter'));

    if (phase === 'ALL') {
        document.getElementById('funnel-all').classList.add('active');
    } else if (phase === 'Phase 1') {
        document.getElementById('funnel-phase1').classList.add('active');
    } else if (phase === 'Phase 2') {
        document.getElementById('funnel-phase2').classList.add('active');
    } else if (phase === 'Phase 3') {
        document.getElementById('funnel-phase3').classList.add('active');
    } else if (phase === 'Phase 4') {
        document.getElementById('funnel-phase4').classList.add('active');
    } else if (phase === 'Completed') {
        document.getElementById('funnel-completed').classList.add('active');
    }

    const banner = document.getElementById('triage-filter-banner');
    if (phase !== 'ALL') {
        if (banner) {
            banner.style.display = 'flex';
            document.getElementById('triage-banner-title').innerText = `Lifecycle Stage: ${phase}`;
            document.getElementById('triage-banner-subtitle').innerText = `Showing all loan files currently in ${phase}`;
        }
    } else if (!currentRmFilter) {
        if (banner) banner.style.display = 'none';
    }

    renderFilesTable();
}

function filterByTrigger(trigger) {
    if (currentTriggerFilter === trigger) {
        clearTriageFilters();
        return;
    }

    currentTriggerFilter = trigger;
    currentPhaseFilter = 'ALL';
    currentRmFilter = null;

    document.querySelectorAll('.funnel-step').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.action-trigger-card').forEach(el => el.classList.remove('active-filter'));

    const banner = document.getElementById('triage-filter-banner');
    if (banner) banner.style.display = 'flex';

    if (trigger === 'calls') {
        document.getElementById('trigger-card-calls').classList.add('active-filter');
        if (banner) {
            document.getElementById('triage-banner-title').innerText = 'Priority Action: Overdue Calls & Follow-ups Due';
            document.getElementById('triage-banner-subtitle').innerText = 'Showing applicants scheduled for follow-up today or flagged as Urgent / High priority';
        }
    } else if (trigger === 'docs') {
        document.getElementById('trigger-card-docs').classList.add('active-filter');
        if (banner) {
            document.getElementById('triage-banner-title').innerText = 'Priority Action: Document Pendencies (KYC & Income)';
            document.getElementById('triage-banner-subtitle').innerText = 'Showing Stage 1 & Stage 2 files stalled awaiting borrower documents';
        }
    } else if (trigger === 'loginfee') {
        document.getElementById('trigger-card-loginfee').classList.add('active-filter');
        if (banner) {
            document.getElementById('triage-banner-title').innerText = 'Priority Action: Bank Processing / Login Fees Due';
            document.getElementById('triage-banner-subtitle').innerText = 'Showing files awaiting login fee collection before bank submission';
        }
    } else if (trigger === 'sanctions') {
        document.getElementById('trigger-card-sanctions').classList.add('active-filter');
        if (banner) {
            document.getElementById('triage-banner-title').innerText = 'Priority Action: Sanctioned Approvals (Pending Disbursal)';
            document.getElementById('triage-banner-subtitle').innerText = 'Showing approved loan cases awaiting agreement execution and disbursement';
        }
    }

    switchExecutiveTab('pipeline');
    renderFilesTable();
}

function filterCasesByOfficer(username) {
    currentRmFilter = username;
    currentTriggerFilter = null;
    currentPhaseFilter = 'ALL';
    
    document.querySelectorAll('.funnel-step').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.action-trigger-card').forEach(el => el.classList.remove('active-filter'));
    const fAll = document.getElementById('funnel-all');
    if (fAll) fAll.classList.add('active');

    const banner = document.getElementById('triage-filter-banner');
    if (banner) {
        banner.style.display = 'flex';
        document.getElementById('triage-banner-title').innerText = `Officer Portfolio: ${username}`;
        document.getElementById('triage-banner-subtitle').innerText = `Showing all active loan files assigned to officer ${username}`;
    }

    switchExecutiveTab('pipeline');
    renderFilesTable();
}

function clearTriageFilters() {
    currentTriggerFilter = null;
    currentPhaseFilter = 'ALL';
    currentRmFilter = null;

    document.querySelectorAll('.funnel-step').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.action-trigger-card').forEach(el => el.classList.remove('active-filter'));
    const fAll = document.getElementById('funnel-all');
    if (fAll) fAll.classList.add('active');

    const banner = document.getElementById('triage-filter-banner');
    if (banner) banner.style.display = 'none';

    renderFilesTable();
}

function renderFilesTable() {
    if (!rmDashboardData || !rmDashboardData.assigned_files) return;

    const files = rmDashboardData.assigned_files;
    const query = (document.getElementById('rm-search-input').value || '').toLowerCase().trim();
    const prioFilter = document.getElementById('rm-priority-filter').value;

    const titleEl = document.getElementById('cases-table-title');
    if (titleEl) {
        if (currentRmUser === 'ALL') {
            titleEl.innerHTML = `<i data-lucide="folder-check" style="width:18px; color:#0f766e;"></i> Master Loan Cases Across All Officers <span class="badge" style="background:#f1f5f9; color:#475569;" id="table-filtered-badge">0 files</span>`;
        } else {
            titleEl.innerHTML = `<i data-lucide="folder-check" style="width:18px; color:#0f766e;"></i> Assigned Loan Cases (${currentRmUser}) <span class="badge" style="background:#f1f5f9; color:#475569;" id="table-filtered-badge">0 files</span>`;
        }
    }

    let filtered = files.filter(f => {
        if (currentRmFilter) {
            const assigned = (f.assigned_to || '').toLowerCase();
            if (assigned !== currentRmFilter.toLowerCase()) return false;
        }

        if (query) {
            const str = `${f.customer_name} ${f.loan_id} ${f.mobile} ${f.loan_type} ${f.assigned_to || ''}`.toLowerCase();
            if (!str.includes(query)) return false;
        }

        if (prioFilter && (f.rm_priority || 'Normal') !== prioFilter) {
            return false;
        }

        if (currentPhaseFilter !== 'ALL' && f.overall_status !== currentPhaseFilter) {
            return false;
        }

        if (currentTriggerFilter === 'calls') {
            const todayStr = new Date().toISOString().split('T')[0];
            const hasFollowup = f.rm_next_followup_date && f.rm_next_followup_date <= todayStr;
            const isPriority = f.rm_priority === 'Urgent' || f.rm_priority === 'High';
            if (!hasFollowup && !isPriority) return false;
        } else if (currentTriggerFilter === 'docs') {
            if (f.overall_status !== 'Phase 1' && f.overall_status !== 'Phase 2') return false;
        } else if (currentTriggerFilter === 'loginfee') {
            if ((f.overall_status !== 'Phase 2' && f.overall_status !== 'Phase 3') || f.login_fee_status === 'Paid') return false;
        } else if (currentTriggerFilter === 'sanctions') {
            if (f.overall_status !== 'Phase 4' && f.sanction_status !== 'Sanctioned') return false;
        }

        return true;
    });

    document.getElementById('table-filtered-badge').innerText = `${filtered.length} files`;
    const bPipeline = document.getElementById('tab-badge-pipeline');
    if (bPipeline) bPipeline.innerText = `${filtered.length} Cases`;

    const tbody = document.getElementById('rm-files-tbody');
    if (filtered.length === 0) {
        tbody.innerHTML = `<tr><td colspan="9" style="text-align:center; padding:30px; color:#94a3b8;"><i data-lucide="inbox" style="width:32px; height:32px; margin-bottom:6px;"></i><p>No loan cases found matching current filters.</p></td></tr>`;
        lucide.createIcons();
        return;
    }

    let html = '';
    filtered.forEach(f => {
        const prio = f.rm_priority || 'Normal';
        let prioClass = 'priority-normal';
        if (prio === 'Urgent') prioClass = 'priority-urgent';
        else if (prio === 'High') prioClass = 'priority-high';

        const stageBadge = f.overall_status === 'Completed' 
            ? '<span class="badge" style="background:#dcfce7; color:#15803d; border:1px solid #bbf7d0;">Disbursed</span>'
            : `<span class="badge" style="background:#f1f5f9; color:#334155; border:1px solid #cbd5e1;">${f.overall_status}</span>`;

        const feeBadge = f.login_fee_status === 'Paid'
            ? `<span style="font-size:11px; color:#15803d; font-weight:700;"><i data-lucide="check-circle" style="width:12px; vertical-align:-1px;"></i> Fee Paid</span>`
            : `<span style="font-size:11px; color:#d97706; font-weight:600;"><i data-lucide="clock" style="width:12px; vertical-align:-1px;"></i> Fee Due</span>`;

        const assignedBadge = f.assigned_to 
            ? `<span class="badge" style="background:#f1f5f9; color:#0f172a; border:1px solid #cbd5e1; font-weight:600;"><i data-lucide="user" style="width:11px; vertical-align:-1px;"></i> ${f.assigned_to}</span>` 
            : '<span style="color:#94a3b8; font-size:11px;">Unassigned</span>';

        html += `
            <tr>
                <td style="white-space:nowrap;">
                    <div style="font-weight:700; color:#0f172a; font-size:13.5px;">${f.customer_name}</div>
                    <div style="font-size:11.5px; color:#64748b;">ID: <span style="color:#0284c7; font-weight:600;">${f.loan_id}</span> • Tel: ${f.mobile || '-'}</div>
                </td>
                <td style="white-space:nowrap;">
                    <div style="font-weight:600; color:#334155;">${f.loan_type || 'Loan'}</div>
                    <div style="font-size:13px; font-weight:700; color:#0f172a;">${formatIndianCurrency(f.loan_amount_requested)}</div>
                </td>
                <td style="white-space:nowrap;">
                    ${stageBadge}
                    <div style="margin-top:3px;">${feeBadge}</div>
                </td>
                <td style="white-space:nowrap;">
                    ${f.sanction_bank ? `<div style="font-weight:600; color:#0f172a; font-size:12.5px;">${f.sanction_bank}</div>` : '<span style="color:#94a3b8; font-size:11px;">In appraisal</span>'}
                    ${f.sanctioned_amount ? `<div style="font-size:12px; color:#15803d; font-weight:700;">₹${(f.sanctioned_amount).toLocaleString('en-IN')}</div>` : ''}
                </td>
                <td style="white-space:nowrap;">
                    ${assignedBadge}
                </td>
                <td style="white-space:nowrap;">
                    <span class="badge ${f.tat_days > 7 ? 'badge-danger' : 'badge-info'}" style="font-size:10.5px;">${f.tat_days || 0}d in system</span>
                </td>
                <td style="white-space:nowrap;">
                    <span class="badge-priority ${prioClass}">${prio}</span>
                </td>
                <td style="white-space:nowrap;">
                    ${f.rm_next_followup_date ? `<span style="font-size:11.5px; font-weight:600; color:#0284c7;">${f.rm_next_followup_date}</span>` : '<span style="font-size:11px; color:#94a3b8;">None</span>'}
                </td>
                <td style="text-align:right; white-space:nowrap;">
                    <div style="display:inline-flex; gap:5px; white-space:nowrap; justify-content:flex-end;">
                        <a href="${basePath}search_track.php?id=${f.id}" class="btn btn-secondary" style="padding:4px 8px; font-size:11px; font-weight:600; color:#0f172a; background:#ffffff; border:1px solid #cbd5e1; display:inline-flex; align-items:center; gap:4px;" title="Open Loan Workspace"><i data-lucide="external-link" style="width:12px; color:#64748b;"></i> Workspace</a>
                        <button onclick="openRmLogModal(${f.id}, '${encodeURIComponent(f.customer_name)}')" class="btn btn-secondary" style="padding:4px 8px; font-size:11px; font-weight:600; color:#0f172a; background:#ffffff; border:1px solid #cbd5e1; display:inline-flex; align-items:center; gap:4px;" title="Record Interaction Log"><i data-lucide="phone-call" style="width:12px; color:#0f766e;"></i> Call Log</button>
                        <button onclick="openRmWhatsAppModal('${f.mobile}', '${encodeURIComponent(f.customer_name)}', '${f.loan_id}', '${f.loan_type}')" class="btn btn-secondary" style="padding:4px 8px; font-size:11px; font-weight:600; color:#0f172a; background:#ffffff; border:1px solid #cbd5e1; display:inline-flex; align-items:center; gap:4px;" title="Direct WhatsApp Dispatch"><i data-lucide="message-circle" style="width:12px; color:#16a34a;"></i> WhatsApp</button>
                        ${isAdmin ? `<button onclick="openReassignModal(${f.id}, '${encodeURIComponent(f.customer_name)}', '${encodeURIComponent(f.assigned_to || '')}')" class="btn btn-secondary" style="padding:4px 8px; font-size:11px; font-weight:600; color:#0f172a; background:#ffffff; border:1px solid #cbd5e1; display:inline-flex; align-items:center; gap:4px;" title="Transfer Case Officer"><i data-lucide="user-plus" style="width:12px; color:#4338ca;"></i> Reassign</button>` : ''}
                    </div>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
    lucide.createIcons();
}

let rawRecentActivities = [];
let currentActivityFilter = 'ALL';

function filterActivityStream(cat, btn) {
    currentActivityFilter = cat;
    document.querySelectorAll('.activity-tab-btn').forEach(b => {
        b.classList.remove('active');
        b.style.background = 'transparent';
        b.style.color = '#64748b';
        b.style.boxShadow = 'none';
        b.style.fontWeight = '600';
    });
    if (btn) {
        btn.classList.add('active');
        btn.style.background = '#ffffff';
        btn.style.color = '#0f172a';
        btn.style.boxShadow = '0 1px 2px rgba(0,0,0,0.05)';
        btn.style.fontWeight = '700';
    }
    renderRecentActivities(rawRecentActivities);
}

function parseActivityMeta(desc, username) {
    let raw = desc || '';
    // Strip leading duplicate user tags like [admin] or [System] or [9528836338]
    let clean = raw.replace(/^\[[^\]]+\]\s*/i, '').trim();

    const lower = clean.toLowerCase();
    let cat = 'OTHER';
    let icon = 'activity';
    let bg = '#f8fafc';
    let color = '#475569';
    let border = '#e2e8f0';
    let tag = 'General';

    if (lower.includes('bank') || lower.includes('sanction') || lower.includes('disburs') || lower.includes('assigned file') || lower.includes('pre-lead') || lower.includes('loan')) {
        cat = 'LOAN';
        icon = 'landmark';
        bg = '#eff6ff';
        color = '#0284c7';
        border = '#bfdbfe';
        tag = 'Bank & Loan';
    } else if (lower.includes('document') || lower.includes('upload') || lower.includes('kyc') || lower.includes('approved for')) {
        cat = 'DOCS';
        icon = 'file-check';
        bg = '#f0fdf4';
        color = '#15803d';
        border = '#bbf7d0';
        tag = 'Documents';
    } else if (lower.includes('paid') || lower.includes('payout') || lower.includes('commission') || lower.includes('fee') || lower.includes('wallet') || lower.includes('₹')) {
        cat = 'PAYOUT';
        icon = 'wallet';
        bg = '#ecfdf5';
        color = '#0f766e';
        border = '#a7f3d0';
        tag = 'Payout & Fee';
    } else if (lower.includes('logged in') || lower.includes('login') || lower.includes('otp') || lower.includes('password') || lower.includes('security')) {
        cat = 'AUTH';
        icon = 'shield-check';
        bg = '#f8fafc';
        color = '#334155';
        border = '#cbd5e1';
        tag = 'Security';
    } else if (lower.includes('partner') || lower.includes('staff') || lower.includes('toggled') || lower.includes('employee')) {
        cat = 'AUTH';
        icon = 'user-check';
        bg = '#faf5ff';
        color = '#7e22ce';
        border = '#e9d5ff';
        tag = 'Staff & Partner';
    }

    return { cleanDesc: clean, cat, icon, bg, color, border, tag };
}

function formatActivityTime(dateStr) {
    if (!dateStr) return '';
    try {
        const d = new Date(dateStr.replace(' ', 'T'));
        if (isNaN(d.getTime())) return dateStr;
        const now = new Date();
        const isToday = d.toDateString() === now.toDateString();
        const yesterday = new Date(now);
        yesterday.setDate(now.getDate() - 1);
        const isYesterday = d.toDateString() === yesterday.toDateString();

        const time = d.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit', hour12: false });
        if (isToday) return `Today, ${time}`;
        if (isYesterday) return `Yesterday, ${time}`;

        const month = d.toLocaleDateString('en-IN', { month: 'short', day: 'numeric' });
        return `${month} · ${time}`;
    } catch(e) {
        return dateStr;
    }
}

function renderRecentActivities(acts) {
    if (acts && acts !== rawRecentActivities) {
        rawRecentActivities = acts;
    }
    const box = document.getElementById('rm-recent-activities');
    if (!box) return;

    const countBadge = document.getElementById('activity-stream-count-badge');
    if (countBadge) countBadge.innerText = `${rawRecentActivities.length} Events`;
    const bAudit = document.getElementById('tab-badge-audit');
    if (bAudit) bAudit.innerText = `${rawRecentActivities.length} Events`;

    if (!rawRecentActivities || rawRecentActivities.length === 0) {
        box.innerHTML = '<div style="text-align:center; padding:30px; color:#94a3b8;"><i data-lucide="inbox" style="width:32px; height:32px; margin-bottom:6px;"></i><p>No operational activities recorded yet today.</p></div>';
        lucide.createIcons();
        return;
    }

    const filtered = rawRecentActivities.filter(a => {
        if (currentActivityFilter === 'ALL') return true;
        const meta = parseActivityMeta(a.description, a.username);
        return meta.cat === currentActivityFilter;
    });

    if (filtered.length === 0) {
        box.innerHTML = '<div style="text-align:center; padding:30px; color:#94a3b8;"><i data-lucide="filter" style="width:28px; height:28px; margin-bottom:6px; color:#cbd5e1;"></i><p style="font-size:12.5px;">No activities found under the selected category.</p></div>';
        lucide.createIcons();
        return;
    }

    let html = '<div style="display:flex; flex-direction:column; gap:8px;">';
    filtered.forEach(a => {
        const u = a.username || 'System';
        const isAdm = u.toLowerCase() === 'admin';
        const isSys = u.toLowerCase() === 'system';
        const meta = parseActivityMeta(a.description, u);
        const timeDisplay = formatActivityTime(a.created_at);

        let userBadge = '';
        if (isAdm) {
            userBadge = `<span class="badge" style="background:#0f172a; color:#38bdf8; font-size:10.5px; padding:2px 7px; font-weight:700;"><i data-lucide="shield" style="width:10px; margin-right:3px;"></i>Admin</span>`;
        } else if (isSys) {
            userBadge = `<span class="badge" style="background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0; font-size:10.5px; padding:2px 7px; font-weight:700;"><i data-lucide="cpu" style="width:10px; margin-right:3px;"></i>System</span>`;
        } else {
            userBadge = `<span class="badge" style="background:#e0f2fe; color:#0369a1; border:1px solid #bae6fd; font-size:10.5px; padding:2px 7px; font-weight:700;"><i data-lucide="user" style="width:10px; margin-right:3px;"></i>${u}</span>`;
        }

        let cleanText = meta.cleanDesc
            .replace(/(Ref:\s*[A-Z0-9_\-]+)/gi, '<strong style="color:#0284c7;">$1</strong>')
            .replace(/(₹[0-9,]+)/g, '<strong style="color:#0f766e;">$1</strong>');

        const linkHtml = a.action_link ? `<a href="${a.action_link}" class="btn btn-secondary" style="padding:2px 8px; font-size:11px; font-weight:600; color:#0284c7; background:#ffffff; border:1px solid #bae6fd; text-decoration:none; display:inline-flex; align-items:center; gap:3px;"><i data-lucide="external-link" style="width:11px;"></i> View</a>` : '';

        html += `
            <div style="padding:10px 14px; background:#f8fafc; border-radius:10px; border:1px solid #e2e8f0; display:flex; align-items:center; justify-content:space-between; gap:12px; transition:all 0.15s ease;" onmouseover="this.style.background='#ffffff'; this.style.borderColor='#cbd5e1';" onmouseout="this.style.background='#f8fafc'; this.style.borderColor='#e2e8f0';">
                <div style="display:flex; align-items:center; gap:12px; flex:1; min-width:0;">
                    <div style="width:34px; height:34px; border-radius:8px; background:${meta.bg}; color:${meta.color}; border:1px solid ${meta.border}; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <i data-lucide="${meta.icon}" style="width:16px; height:16px;"></i>
                    </div>
                    <div style="flex:1; min-width:0;">
                        <div style="display:flex; align-items:center; gap:6px; margin-bottom:2px; flex-wrap:wrap;">
                            ${userBadge}
                            <span class="badge" style="background:#ffffff; color:#64748b; border:1px solid #e2e8f0; font-size:10px; font-weight:600; padding:1px 6px;">${meta.tag}</span>
                        </div>
                        <div style="font-size:12.5px; font-weight:600; color:#1e293b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${cleanText}</div>
                    </div>
                </div>
                <div style="display:flex; align-items:center; gap:10px; flex-shrink:0;">
                    ${linkHtml}
                    <span style="font-size:11px; font-weight:600; color:#64748b; background:#ffffff; border:1px solid #e2e8f0; padding:3px 8px; border-radius:6px; white-space:nowrap; display:inline-flex; align-items:center; gap:4px;">
                        <i data-lucide="clock" style="width:11px;"></i> ${timeDisplay}
                    </span>
                </div>
            </div>
        `;
    });
    html += '</div>';
    box.innerHTML = html;
    lucide.createIcons();
}

// ====== ADMIN RM QUERY DESK FUNCTIONS ======
async function loadRmQueriesDesk() {
    const statusFilter = document.getElementById('query-filter-status') ? document.getElementById('query-filter-status').value : 'ALL';
    const tbody = document.getElementById('rm-queries-desk-tbody');
    if (!tbody) return;

    try {
        const res = await fetch(`../api.php?api=get_rm_queries&rm_user=${encodeURIComponent(currentRmUser)}&status=${encodeURIComponent(statusFilter)}`);
        const data = await res.json();
        
        if (!data || !data.success || !data.queries) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:20px; color:#94a3b8;">No internal queries found.</td></tr>';
            return;
        }

        const queries = data.queries;
        const directiveNoun = <?= $is_admin ? "'Directives'" : "'Queries'" ?>;
        document.getElementById('query-desk-badge').innerText = `${queries.length} ${directiveNoun}`;
        const bCompliance = document.getElementById('tab-badge-compliance');
        if (bCompliance) bCompliance.innerText = `${queries.length} ${directiveNoun}`;

        if (queries.length === 0) {
            tbody.innerHTML = `<tr><td colspan="8" style="text-align:center; padding:20px; color:#94a3b8;">No ${directiveNoun.toLowerCase()} found for selected filter.</td></tr>`;
            return;
        }

        let html = '';
        queries.forEach(q => {
            const prioBadge = q.priority === 'Urgent' 
                ? '<span class="badge" style="background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; font-weight:700;"><i data-lucide="alert-triangle" style="width:10px;"></i> Urgent</span>'
                : (q.priority === 'High' ? '<span class="badge" style="background:#fffbeb; color:#b45309; border:1px solid #fde68a; font-weight:700;">High Priority</span>' : '<span class="badge" style="background:#f8fafc; color:#475569; border:1px solid #e2e8f0; font-weight:600;">Routine</span>');

            const statusBadge = q.status === 'Resolved'
                ? '<span class="badge" style="background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0; font-weight:700;"><i data-lucide="check-circle" style="width:10px;"></i> Fulfilled / Closed</span>'
                : '<span class="badge" style="background:#fff7ed; color:#c2410c; border:1px solid #fed7aa; font-weight:700;"><i data-lucide="clock" style="width:10px;"></i> Action Required</span>';

            const replyBox = q.rm_reply 
                ? `<div style="font-size:12px; color:#15803d; background:#f0fdf4; padding:6px 10px; border-radius:6px; border:1px solid #bbf7d0; margin-top:4px;"><strong>Officer Action:</strong> ${q.rm_reply}</div>` 
                : '<span style="color:#94a3b8; font-size:11.5px;">Awaiting compliance</span>';

            html += `
                <tr>
                    <td>
                        <div style="font-weight:700; color:#0f172a; font-size:13.5px;">${q.query_title}</div>
                        <div style="font-size:12px; color:#64748b; margin-top:2px;">${q.query_description}</div>
                    </td>
                    <td style="white-space:nowrap;">
                        <span class="badge" style="background:#f1f5f9; color:#0f172a; border:1px solid #cbd5e1; font-weight:600; font-size:11.5px;"><i data-lucide="user" style="width:11px; vertical-align:-1px;"></i> ${q.assigned_rm}</span>
                    </td>
                    <td style="white-space:nowrap;">
                        ${q.customer_name ? `<div style="font-weight:600; color:#0f172a; font-size:12.5px;">${q.customer_name}</div><div style="font-size:11px; color:#0284c7;">Ref: ${q.loan_id || '-'}</div>` : '<span style="color:#94a3b8; font-size:11.5px;">General Directive</span>'}
                    </td>
                    <td style="white-space:nowrap;">${prioBadge}</td>
                    <td style="white-space:nowrap;">${statusBadge}</td>
                    <td style="font-size:12px; color:#64748b; white-space:nowrap;">${q.created_at}</td>
                    <td>${replyBox}</td>
                    <td style="text-align:right; white-space:nowrap;">
                        <button onclick="openRmQueryReplyModal(${q.id}, '${encodeURIComponent(q.query_title)}')" class="btn btn-secondary" style="padding:4px 9px; font-size:11.5px; font-weight:600; color:#0f172a; background:#ffffff; border:1px solid #cbd5e1; display:inline-flex; align-items:center; gap:4px;"><i data-lucide="message-square" style="width:12px; color:#0284c7;"></i> Update</button>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
        lucide.createIcons();
    } catch(e) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:20px; color:#ef4444;">Failed to load directives.</td></tr>';
    }
}

function applyDirectivePreset(type, title) {
    const tInput = document.getElementById('query_title');
    const dInput = document.getElementById('query_description');
    const pInput = document.getElementById('query_priority');
    if (tInput) tInput.value = title;
    
    let desc = '';
    if (type === 'Follow-up') {
        desc = 'Urgently contact all assigned borrowers with pending calls or overdue follow-ups. Update call logs with clear status by 5:00 PM today.';
        if (pInput) pInput.value = 'High';
    } else if (type === 'Doc Pendency') {
        desc = 'Collect all missing KYC, income proofs, and bank statements for pending loan files. Re-upload cleared files within 24 hours.';
        if (pInput) pInput.value = 'High';
    } else if (type === 'Login Fee') {
        desc = 'Ensure processing and login fees are collected and updated in system immediately so files can be forwarded to partner banks.';
        if (pInput) pInput.value = 'Normal';
    } else if (type === 'Disbursal') {
        desc = 'Coordinate immediately with sanctioned borrowers and partner bank credit teams to execute sanction letter agreements and drive disbursals.';
        if (pInput) pInput.value = 'Urgent';
    }
    if (dInput) dInput.value = desc;
}

function setTaskDeadlinePreset(hoursAhead) {
    const d = new Date(Date.now() + hoursAhead * 3600000);
    const tzOffset = d.getTimezoneOffset() * 60000;
    const localISOTime = (new Date(d.getTime() - tzOffset)).toISOString().slice(0, 16);
    const dInput = document.getElementById('query_due_date');
    if (dInput) dInput.value = localISOTime;
}

function openRaiseRmQueryModal(targetRm) {
    const sel = document.getElementById('query_target_rm');
    if (targetRm) {
        sel.value = targetRm;
    } else if (typeof currentRmUser !== 'undefined' && currentRmUser && currentRmUser !== 'ALL') {
        sel.value = currentRmUser;
    } else {
        sel.value = 'ALL_RMS';
    }

    setTaskDeadlinePreset(4);
    document.getElementById('query_title').value = '';
    document.getElementById('query_description').value = '';
    const appEl = document.getElementById('query_applicant_id');
    if (appEl) appEl.value = '';
    document.getElementById('raise-rm-query-modal').style.display = 'flex';
    lucide.createIcons();
}

function closeRaiseRmQueryModal() {
    document.getElementById('raise-rm-query-modal').style.display = 'none';
}

async function submitRaiseRmQuery(e) {
    e.preventDefault();
    const form = document.getElementById('raise-rm-query-form');
    const formData = new FormData(form);
    const btn = document.getElementById('submit_query_btn');

    btn.disabled = true;
    btn.innerText = 'Assigning Task...';

    try {
        const res = await fetch('../api.php?api=raise_rm_query', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data && data.success) {
            showNotification(data.message, 'success');
            closeRaiseRmQueryModal();
            loadRmQueriesDesk();
        } else {
            showNotification(data.error || 'Failed to assign task', 'error');
        }
    } catch (err) {
        showNotification('Network error', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="check-square" style="width:14px; color:#38bdf8;"></i> Assign Task to RM';
        lucide.createIcons();
    }
}

function openRmQueryReplyModal(qId, title) {
    document.getElementById('reply_query_id').value = qId;
    document.getElementById('reply_query_title_display').innerText = decodeURIComponent(title);
    document.getElementById('reply_notes').value = '';
    document.getElementById('rm-query-reply-modal').style.display = 'flex';
    lucide.createIcons();
}

function closeRmQueryReplyModal() {
    document.getElementById('rm-query-reply-modal').style.display = 'none';
}

async function submitRmQueryReply(e) {
    e.preventDefault();
    const form = document.getElementById('rm-query-reply-form');
    const formData = new FormData(form);
    const btn = document.getElementById('submit_reply_btn');

    btn.disabled = true;
    btn.innerText = 'Saving Reply...';

    try {
        const res = await fetch('../api.php?api=resolve_rm_query', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data && data.success) {
            showNotification(data.message, 'success');
            closeRmQueryReplyModal();
            loadRmQueriesDesk();
        } else {
            showNotification(data.error || 'Failed to submit reply', 'error');
        }
    } catch (err) {
        showNotification('Network error', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="check" style="width:14px;"></i> Save Reply';
        lucide.createIcons();
    }
}

// ====== ADMIN REASSIGN LOAN CASE FUNCTIONS ======
function openReassignModal(appId, custName, currentAssigned) {
    document.getElementById('reassign_applicant_id').value = appId;
    document.getElementById('reassign_app_name').innerText = decodeURIComponent(custName);
    document.getElementById('reassign_curr_assigned').innerText = decodeURIComponent(currentAssigned || 'Unassigned');
    document.getElementById('reassign_notes').value = '';
    const sel = document.getElementById('reassign_target_rm');
    if (sel) sel.value = '';
    document.getElementById('reassign-case-modal').style.display = 'flex';
    lucide.createIcons();
}

function closeReassignModal() {
    document.getElementById('reassign-case-modal').style.display = 'none';
}

async function submitReassignCase(e) {
    e.preventDefault();
    const form = document.getElementById('reassign-case-form');
    const formData = new FormData(form);
    const btn = document.getElementById('submit_reassign_btn');

    btn.disabled = true;
    btn.innerText = 'Reassigning...';

    try {
        const res = await fetch('../api.php?api=assign_applicant_rm', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data && data.success) {
            showNotification(data.message || 'Case successfully reassigned!', 'success');
            closeReassignModal();
            loadRmDashboard();
        } else {
            showNotification(data.error || 'Failed to reassign case', 'error');
        }
    } catch(err) {
        showNotification('Network error while reassigning', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="user-check" style="width:14px;"></i> Confirm Reassignment';
        lucide.createIcons();
    }
}

// ====== 360° RM DOSSIER MODAL (Operational Activity Telemetry) ======
let currentDossierTab = 'portfolio';

async function openRmDossierModal(rmUser) {
    const modal = document.getElementById('rm-dossier-modal');
    modal.style.display = 'flex';
    document.getElementById('dossier-body-content').innerHTML = '<div style="text-align:center; padding:40px; color:#94a3b8;"><i data-lucide="loader-2" class="spin" style="width:32px; height:32px;"></i><p>Loading officer dossier...</p></div>';
    lucide.createIcons();

    try {
        const res = await fetch(`../api.php?api=get_rm_detailed_dossier&rm_user=${encodeURIComponent(rmUser)}`);
        const data = await res.json();

        if (!data || !data.success) {
            showNotification(data.error || 'Failed to load officer dossier', 'error');
            closeRmDossierModal();
            return;
        }

        activeDossierData = data;
        const u = data.rm_info;
        const dispName = u.full_name || u.name || u.username;
        document.getElementById('dossier-avatar').innerText = dispName.charAt(0).toUpperCase();
        document.getElementById('dossier-title').innerHTML = `${dispName} <span class="badge" style="background:#0284c7; color:#fff; font-size:11px;">${u.role}</span>`;
        document.getElementById('dossier-subtitle').innerText = `Username: ${u.username} • Target: ${formatIndianCurrency(u.monthly_target)} • Commission Rate: ${u.commission_rate}%`;

        switchDossierTab('portfolio');
    } catch (e) {
        showNotification('Failed to fetch officer dossier', 'error');
        closeRmDossierModal();
    }
}

function closeRmDossierModal() {
    document.getElementById('rm-dossier-modal').style.display = 'none';
}

function switchDossierTab(tabKey) {
    currentDossierTab = tabKey;
    document.querySelectorAll('.dossier-tab-btn').forEach(btn => btn.classList.remove('active'));
    
    const activeBtn = document.getElementById(`tab-btn-${tabKey}`);
    if (activeBtn) activeBtn.classList.add('active');

    if (!activeDossierData) return;
    const body = document.getElementById('dossier-body-content');

    if (tabKey === 'portfolio') {
        const files = activeDossierData.assigned_files || [];
        if (files.length === 0) {
            body.innerHTML = '<p style="color:#94a3b8; padding:20px; text-align:center;">No loan cases assigned to this officer.</p>';
            return;
        }

        let html = `
            <div style="margin-bottom:16px; font-weight:700; color:#0f172a; font-size:14px;">Assigned Loan Portfolio (${files.length} active files)</div>
            <table class="data-table" style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr>
                        <th>Applicant</th>
                        <th>Loan Type & Amt</th>
                        <th>Stage</th>
                        <th>Sanction Bank</th>
                        <th>Created Date</th>
                        <th style="text-align:right;">Action</th>
                    </tr>
                </thead>
                <tbody>
        `;

        files.forEach(f => {
            html += `
                <tr>
                    <td><strong>${f.customer_name}</strong><br><span style="font-size:11px; color:#64748b;">${f.loan_id}</span></td>
                    <td>${f.loan_type}<br><strong>${formatIndianCurrency(f.loan_amount_requested)}</strong></td>
                    <td><span class="badge" style="background:#f1f5f9; color:#0f172a;">${f.overall_status}</span></td>
                    <td>${f.sanction_bank || '-'}</td>
                    <td style="font-size:12px; color:#64748b;">${f.created_at}</td>
                    <td style="text-align:right;"><a href="${basePath}search_track.php?id=${f.id}" target="_blank" class="btn btn-secondary" style="padding:4px 8px; font-size:11px;"><i data-lucide="external-link" style="width:12px;"></i> View File</a></td>
                </tr>
            `;
        });
        html += 'tbody></table>';
        body.innerHTML = html;
    } else if (tabKey === 'activities') {
        const acts = activeDossierData.activity_log || [];
        if (acts.length === 0) {
            body.innerHTML = '<p style="color:#94a3b8; padding:20px; text-align:center;">No activity audit records logged for this officer yet.</p>';
            return;
        }

        let html = `
            <div style="margin-bottom:16px; font-weight:700; color:#0f172a; font-size:14px;">Live Operational Action Stream</div>
            <div style="display:flex; flex-direction:column; gap:10px;">
        `;

        acts.forEach(a => {
            html += `
                <div style="padding:12px 16px; background:#f8fafc; border-radius:8px; border:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <div style="font-size:13.5px; font-weight:600; color:#0f172a;">${a.description}</div>
                        ${a.action_link ? `<div style="font-size:11px; color:#0284c7; margin-top:2px;">Ref: ${a.action_link}</div>` : ''}
                    </div>
                    <div style="font-size:11.5px; color:#64748b; font-weight:600; white-space:nowrap; margin-left:14px;">${a.created_at}</div>
                </div>
            `;
        });
        html += '</div>';
        body.innerHTML = html;
    } else if (tabKey === 'queries') {
        const qList = activeDossierData.queries || [];
        if (qList.length === 0) {
            body.innerHTML = '<p style="color:#94a3b8; padding:20px; text-align:center;">No compliance queries raised to this officer yet.</p>';
            return;
        }

        let html = `
            <div style="margin-bottom:16px; font-weight:700; color:#0f172a; font-size:14px;">Admin Queries & Compliance Log (${qList.length})</div>
            <div style="display:flex; flex-direction:column; gap:12px;">
        `;

        qList.forEach(q => {
            const isRes = q.status === 'Resolved';
            html += `
                <div style="padding:14px; background:#ffffff; border-radius:8px; border:1px solid #e2e8f0; border-left:4px solid ${isRes ? '#10b981' : '#f59e0b'};">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <div style="font-weight:700; font-size:14px; color:#0f172a;">${q.query_title}</div>
                        <span class="badge" style="background:${isRes ? '#dcfce7' : '#fff7ed'}; color:${isRes ? '#15803d' : '#c2410c'}; font-size:10.5px;">${q.status}</span>
                    </div>
                    <div style="font-size:12.5px; color:#475569; margin:6px 0;">${q.query_description}</div>
                    ${q.rm_reply ? `<div style="background:#f0fdf4; border:1px solid #bbf7d0; padding:8px 12px; border-radius:6px; font-size:12px; color:#15803d; margin-top:8px;"><strong>RM Reply:</strong> ${q.rm_reply} <span style="font-size:10.5px; color:#64748b;">(${q.resolved_at})</span></div>` : '<div style="font-size:11px; color:#d97706; margin-top:4px;">Pending RM reply...</div>'}
                </div>
            `;
        });
        html += '</div>';
        body.innerHTML = html;
    }

    lucide.createIcons();
}

// ====== LOG INTERACTION MODAL ======
function openRmLogModal(appId, custName) {
    document.getElementById('rm_log_applicant_id').value = appId;
    document.getElementById('rm_log_app_title').innerText = decodeURIComponent(custName);
    document.getElementById('rm_log_notes').value = '';
    
    const tmrw = new Date();
    tmrw.setDate(tmrw.getDate() + 1);
    tmrw.setHours(11, 0, 0, 0);
    const pad = (n) => n.toString().padStart(2, '0');
    const formattedDate = `${tmrw.getFullYear()}-${pad(tmrw.getMonth()+1)}-${pad(tmrw.getDate())}T${pad(tmrw.getHours())}:${pad(tmrw.getMinutes())}`;
    document.getElementById('rm_log_followup').value = formattedDate;

    document.getElementById('rm-log-interaction-modal').style.display = 'flex';
    lucide.createIcons();
}

function closeRmLogInteractionModal() {
    document.getElementById('rm-log-interaction-modal').style.display = 'none';
}

async function submitRmLogInteraction(e) {
    e.preventDefault();
    const form = document.getElementById('rm-log-interaction-form');
    const formData = new FormData(form);
    const btn = document.getElementById('rm_log_submit_btn');

    btn.disabled = true;
    btn.innerHTML = 'Saving Log...';

    try {
        const res = await fetch('../api.php?api=log_rm_interaction', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data && data.success) {
            showNotification(data.message, 'success');
            closeRmLogInteractionModal();
            loadRmDashboard();
        } else {
            showNotification(data.error || 'Failed to log call', 'error');
        }
    } catch (err) {
        showNotification('Network error', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="check" style="width:14px;"></i> Save Call & Audit Log';
        lucide.createIcons();
    }
}

// ====== 1-CLICK WHATSAPP DESK ======
let activeRmWhatsAppContext = {};

function openRmWhatsAppModal(mobile, custName, loanId, loanType) {
    activeRmWhatsAppContext = {
        mobile: mobile ? mobile.replace(/\D/g, '') : '',
        name: decodeURIComponent(custName || 'Customer'),
        loanId: loanId || '',
        loanType: loanType || 'Loan'
    };

    if (!activeRmWhatsAppContext.mobile || activeRmWhatsAppContext.mobile.length < 10) {
        showNotification('Customer mobile number is missing or invalid', 'error');
        return;
    }

    document.getElementById('rm_wa_customer_title').innerText = `${activeRmWhatsAppContext.name} (+91 ${activeRmWhatsAppContext.mobile})`;
    loadRmWhatsAppTemplate('welcome');
    document.getElementById('rm-whatsapp-modal').style.display = 'flex';
    lucide.createIcons();
}

function closeRmWhatsAppModal() {
    document.getElementById('rm-whatsapp-modal').style.display = 'none';
}

function loadRmWhatsAppTemplate(templateKey) {
    const c = activeRmWhatsAppContext;
    let text = '';

    if (templateKey === 'welcome') {
        text = `Namaste ${c.name} ji,\n\nMain aapka Dedicated Relationship Manager baat kar raha hoon BFS Loan Services se.\n\nAapka ${c.loanType} application (Ref ID: *${c.loanId}*) hamare system me active process me hai.\n\nKisi bhi jaankari ya file status ke liye aap mujhe is number par direct call ya WhatsApp kar sakte hain.\n\nDhanyawad,\nTeam BFS`;
    } else if (templateKey === 'pendency') {
        text = `Namaste ${c.name} ji,\n\nAapke *${c.loanType}* (ID: *${c.loanId}*) file ko bank me process karne ke liye kuch zaroori documents required hain:\n\n1. Latest 6 Months Bank Statement (PDF format)\n2. Latest 3 Months Salary Slips / ITR with Computation\n3. KYC (Aadhaar & PAN Card)\n4. Property / Business Proof\n\nKripya ye documents jaldi se jaldi share karein taaki hum aapka loan approval fast process kara sakein.\n\nDhanyawad,\nBFS Loan Team`;
    } else if (templateKey === 'login_fee') {
        text = `Namaste ${c.name} ji,\n\nAapki loan application (ID: *${c.loanId}*) documents complete ho chuke hain aur file officially Bank Login ke liye ready hai.\n\nBank processing & verification start karne ke liye official Bank Login Fee pay karni hai.\n\nPayment UPI / QR Code ya Official Bank Account me transfer karke receipt share karein taaki banker verification turant start ho sake.\n\nDhanyawad,\nBFS Team`;
    } else if (templateKey === 'sanction') {
        text = `🎉 *CONGRATULATIONS ${c.name.toUpperCase()} JI!* 🎉\n\nHamein batate hue khushi ho rahi hai ki aapka *${c.loanType}* (ID: *${c.loanId}*) Bank dwara successfully *SANCTION / APPROVE* ho chuka hai!\n\nDisbursement aur final agreement signing ke liye hamari team aapse coordinate kar rahi hai.\n\nDhanyawad,\nBFS Financial Services`;
    }

    document.getElementById('rm_wa_message_content').value = text;
}

function sendRmQuickWhatsApp() {
    const c = activeRmWhatsAppContext;
    const msg = document.getElementById('rm_wa_message_content').value.trim();
    if (!msg) {
        showNotification('Please enter a WhatsApp message', 'error');
        return;
    }

    let phone = c.mobile;
    if (phone.length === 10) phone = '91' + phone;

    const waUrl = `https://api.whatsapp.com/send?phone=${phone}&text=${encodeURIComponent(msg)}`;
    window.open(waUrl, '_blank');
    closeRmWhatsAppModal();
}

// ===== WALLET FUNCTIONS =====
function fmtINR(n) {
    n = parseFloat(n) || 0;
    if (n >= 10000000) return '₹' + (n/10000000).toFixed(2) + ' Cr';
    if (n >= 100000)   return '₹' + (n/100000).toFixed(2) + ' L';
    if (n >= 1000)     return '₹' + (n/1000).toFixed(1) + 'K';
    return '₹' + Math.round(n).toLocaleString('en-IN');
}

async function loadWalletSummary() {
    const titleEl = document.getElementById('wallet-section-title');
    if (titleEl) {
        titleEl.innerHTML = (isAdmin && currentRmUser === 'ALL')
            ? '<i data-lucide="coins" style="width:16px; color:#10b981;"></i> Team Commission &amp; Disbursal Accruals [Audit View]'
            : '<i data-lucide="wallet" style="width:16px; color:#10b981;"></i> Officer Commission &amp; Wallet';
        lucide.createIcons();
    }
    try {
        const res = await fetch('../api.php?api=get_rm_wallet_summary');
        const data = await res.json();
        if (!data.success) return;
        const s = data.summary;
        document.getElementById('wallet-total-earned').textContent = fmtINR(s.total_gross);
        document.getElementById('wallet-received').textContent    = fmtINR(s.total_received);
        document.getElementById('wallet-pending').textContent     = fmtINR(s.total_pending);
        document.getElementById('wallet-this-month').textContent  = fmtINR(s.this_month);
        const bPayouts = document.getElementById('tab-badge-payouts');
        if (bPayouts) bPayouts.innerText = fmtINR(s.total_gross);
        const mn = new Date().toLocaleString('default',{month:'long',year:'numeric'});
        document.getElementById('wallet-month-label').textContent = mn + ' earnings';
    } catch(e) { /* silent */ }
}

let walletPage = 1;
async function loadWalletHistory(page) {
    walletPage = page || 1;
    const status = document.getElementById('wallet-filter-status').value;
    const tbody  = document.getElementById('wallet-history-tbody');
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:24px;color:#94a3b8;">Loading...</td></tr>';
    try {
        const res  = await fetch(`../api.php?api=get_rm_wallet_history&status=${encodeURIComponent(status)}&page=${walletPage}&per_page=10`);
        const data = await res.json();
        if (!data.success || !data.records.length) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:30px;color:#94a3b8;">No transactions found.</td></tr>';
            document.getElementById('wallet-pagination').innerHTML = '';
            return;
        }
        const statusStyles = {
            'Pending':   'background:#fef3c7;color:#d97706;',
            'Paid':      'background:#dcfce7;color:#15803d;',
            'Cancelled': 'background:#fee2e2;color:#dc2626;',
            'Approved':  'background:#dbeafe;color:#1d4ed8;',
            'Hold':      'background:#f1f5f9;color:#475569;'
        };
        tbody.innerHTML = data.records.map(r => {
            const ss = statusStyles[r.status] || 'background:#f1f5f9;color:#475569;';
            const dateStr = r.paid_on ? new Date(r.paid_on).toLocaleDateString('en-IN') : (r.created_at ? new Date(r.created_at).toLocaleDateString('en-IN') : '—');
            const txRef = r.transaction_ref ? `<div style="font-size:10px;color:#64748b;margin-top:2px;">Ref: ${r.transaction_ref}</div>` : '';
            return `<tr style="border-bottom:1px solid #f1f5f9;">
                <td style="padding:12px 16px; white-space:nowrap;">
                    <div style="font-weight:700;color:#0f172a;font-size:13px;">${r.customer_name || '—'}</div>
                    <div style="font-size:11px;color:#64748b;">${r.loan_id || ''} · ${r.loan_type || ''}</div>
                </td>
                <td style="padding:12px 16px;text-align:right;font-weight:600;color:#334155; white-space:nowrap;">${fmtINR(r.total_loan_amount)}</td>
                <td style="padding:12px 16px;text-align:right;color:#0f172a;font-weight:700; white-space:nowrap;">${fmtINR(r.gross_payout)}</td>
                <td style="padding:12px 16px;text-align:right;color:#ef4444; white-space:nowrap;">-${fmtINR(r.tds_deducted)}</td>
                <td style="padding:12px 16px;text-align:right;font-weight:800;color:${r.status==='Paid'?'#10b981':'#d97706'}; white-space:nowrap;">${fmtINR(r.net_payable)}</td>
                <td style="padding:12px 16px;text-align:center; white-space:nowrap;">
                    <span style="padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;${ss}">${r.status}</span>
                    ${txRef}
                </td>
                <td style="padding:12px 16px;color:#64748b;font-size:12px; white-space:nowrap;">${dateStr}</td>
            </tr>`;
        }).join('');

        const totalPages = Math.ceil(data.total / 10);
        let pages = '';
        for(let i = 1; i <= totalPages; i++) {
            const active = i === walletPage ? 'background:#0f172a;color:#fff;' : 'background:#f1f5f9;color:#334155;';
            pages += `<button onclick="loadWalletHistory(${i})" style="padding:5px 10px;border:none;border-radius:6px;cursor:pointer;font-size:12px;font-weight:700;${active}">${i}</button>`;
        }
        document.getElementById('wallet-pagination').innerHTML = pages;
        lucide.createIcons();
    } catch(e) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:24px;color:#ef4444;">Failed to load wallet. Please refresh.</td></tr>';
    }
}

window.addEventListener('DOMContentLoaded', () => {
    loadRmDashboard();
    loadWalletSummary();
    loadWalletHistory(1);
    setInterval(loadRmDashboard, 60000);
    setInterval(loadWalletSummary, 120000);
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
