<?php
require_once 'config.php';
require_role(['Admin', 'RM']);

$current_page = 'staff_ranking.php';
$page_title = 'Executive Leaderboard & Staff Rankings';
$page_subtitle = 'Live production metrics, conversion tracking & leaderboard rankings for RMs, Partners, and Field Executives';
$hide_main_header = true;
require_once 'header.php';

// Active Category Tab: 'rms' (default), 'agents', 'field'
$active_tab = $_GET['tab'] ?? 'rms';
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'Admin';

// 1. Fetch RM Performance Rankings (Strictly RMs & Staff managing loans, excluding external partners)
$rm_sql = "
    SELECT 
        u.id as user_id,
        COALESCE(e.full_name, u.name, u.full_name, u.username) as rm_name,
        u.username,
        u.role,
        COALESCE(e.designation, u.role, 'Relationship Manager') as designation,
        COALESCE(u.monthly_target, 15000000) as monthly_target,
        COUNT(DISTINCT a.id) as total_assigned,
        COUNT(DISTINCT CASE WHEN LOWER(a.overall_status) IN ('completed','disbursed','sanctioned','phase 4','phase 5') THEN a.id END) as approved_cnt,
        COALESCE(SUM(CASE WHEN LOWER(a.overall_status) IN ('completed','disbursed') THEN a.loan_amount_requested ELSE 0 END), 0) as disbursed_volume
    FROM users u
    LEFT JOIN employees e ON (e.user_id = u.id OR e.official_email = u.username)
    LEFT JOIN applicants a ON (a.assigned_to = u.username OR a.assigned_to = u.name OR a.assigned_to = e.full_name)
    WHERE (
        u.role = 'RM' 
        OR (u.role = 'Staff' AND (e.designation LIKE '%Relationship Manager%' OR e.designation = 'RM' OR e.designation LIKE '%RM%' OR e.designation LIKE '%Executive%'))
    )
    AND u.role NOT IN ('Agent', 'Partner', 'Builder', 'CA', 'DSA', 'Connector')
    AND LOWER(u.username) != 'admin' AND LOWER(u.role) != 'admin'
    GROUP BY u.id
    ORDER BY disbursed_volume DESC, approved_cnt DESC, total_assigned DESC
";
$rm_rankings = $db->query($rm_sql)->fetchAll(PDO::FETCH_ASSOC);

// 2. Fetch Agent & Partner Referral Leaderboard Rankings
$agent_sql = "
    SELECT 
        u.id as user_id,
        COALESCE(r.full_name, u.name, u.full_name, u.username) as agent_name,
        u.username,
        COALESCE(r.referrer_type, u.role, 'Agent / Partner') as partner_type,
        COALESCE(r.mobile, '') as mobile,
        COUNT(DISTINCT l.id) as total_sourced_leads,
        COUNT(DISTINCT CASE WHEN LOWER(l.stage) IN ('converted','disbursed','login','completed') THEN l.id END) + 
        COUNT(DISTINCT CASE WHEN LOWER(a.overall_status) IN ('completed','disbursed','sanctioned','phase 4','phase 5') THEN a.id END) as approved_leads,
        COALESCE(SUM(CASE WHEN LOWER(l.stage) IN ('converted','disbursed','completed') THEN l.loan_amount ELSE 0 END), 0) +
        COALESCE(SUM(CASE WHEN LOWER(a.overall_status) IN ('completed','disbursed') THEN a.loan_amount_requested ELSE 0 END), 0) as total_volume
    FROM users u
    LEFT JOIN referrals r ON (r.user_id = u.id OR r.referral_id = u.username OR r.mobile = u.username)
    LEFT JOIN leads l ON (l.added_by = u.username OR l.added_by = r.referral_id OR l.added_by = r.full_name)
    LEFT JOIN applicants a ON (a.added_by = u.username OR a.referral_id = r.referral_id OR a.added_by = r.referral_id)
    WHERE u.role IN ('Agent','Partner','Builder','CA','DSA','Connector') OR r.id IS NOT NULL
    GROUP BY u.id
    ORDER BY approved_leads DESC, total_sourced_leads DESC, total_volume DESC
";
$agent_rankings = $db->query($agent_sql)->fetchAll(PDO::FETCH_ASSOC);

// 3. Fetch Field Staff Visits Rankings
$visit_stmt = $db->query("
    SELECT 
        executive_name, 
        COUNT(*) as total_visits, 
        COUNT(DISTINCT person_name) as distinct_clients,
        MAX(created_at) as last_activity 
    FROM field_visits 
    WHERE executive_name IS NOT NULL AND executive_name != ''
    GROUP BY executive_name 
    ORDER BY total_visits DESC, last_activity DESC
");
$visit_rankings = $visit_stmt->fetchAll(PDO::FETCH_ASSOC);

// Summary KPI calculations based on active tab
if ($active_tab === 'rms') {
    $top_performer = $rm_rankings[0]['rm_name'] ?? 'None';
    $total_disbursed = array_sum(array_column($rm_rankings, 'disbursed_volume'));
    $total_files = array_sum(array_column($rm_rankings, 'total_assigned'));
    $total_active_personnel = count($rm_rankings);
} elseif ($active_tab === 'agents') {
    $top_performer = $agent_rankings[0]['agent_name'] ?? 'None';
    $total_disbursed = array_sum(array_column($agent_rankings, 'total_volume'));
    $total_files = array_sum(array_column($agent_rankings, 'total_sourced_leads'));
    $total_active_personnel = count($agent_rankings);
} else {
    $top_performer = $visit_rankings[0]['executive_name'] ?? 'None';
    $total_disbursed = 0;
    $total_files = array_sum(array_column($visit_rankings, 'total_visits'));
    $total_active_personnel = count($visit_rankings);
}
?>

<style>
    .rank-wrapper {
        width: 100%;
        margin: 0 auto;
        padding: 6px 4px 32px 4px;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    }

    /* Top Executive Header */
    .rank-head-banner {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 20px 24px;
        margin-bottom: 22px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.02);
    }
    .rank-head-title {
        display: flex;
        align-items: center;
        gap: 14px;
    }
    .rank-head-icon {
        width: 44px;
        height: 44px;
        background: #0f172a;
        color: #ffffff;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 12px rgba(15,23,42,0.15);
        flex-shrink: 0;
    }

    /* Header Action Buttons */
    .btn-head-secondary {
        background: #ffffff;
        color: #334155;
        border: 1px solid #cbd5e1;
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 7px;
        transition: all 0.2s;
        text-decoration: none;
    }
    .btn-head-secondary:hover {
        background: #f8fafc;
        border-color: #0f172a;
        color: #0f172a;
    }

    .btn-head-primary {
        background: #0f172a;
        color: #ffffff;
        border: 1px solid #0f172a;
        padding: 8px 18px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 7px;
        transition: all 0.2s;
        text-decoration: none;
        box-shadow: 0 4px 12px rgba(15,23,42,0.15);
    }
    .btn-head-primary:hover {
        background: #2563eb;
        border-color: #2563eb;
        color: #ffffff;
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(37,99,235,0.25);
    }

    /* Segmented Navigation Bar */
    .tab-nav-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 7px;
        margin-bottom: 22px;
        display: flex;
        align-items: center;
        box-shadow: 0 2px 6px rgba(0,0,0,0.02);
    }
    .segmented-tabs {
        display: flex;
        gap: 6px;
        width: 100%;
        flex-wrap: wrap;
    }
    .tab-pill {
        flex: 1;
        min-width: 190px;
        padding: 9px 14px;
        font-size: 13px;
        font-weight: 700;
        color: #475569;
        border-radius: 8px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transition: all 0.2s;
        border: 1px solid transparent;
        background: transparent;
    }
    .tab-pill:hover {
        background: #f8fafc;
        color: #0f172a;
    }
    .tab-pill.active {
        background: #0f172a;
        color: #ffffff;
        box-shadow: 0 3px 10px rgba(15,23,42,0.18);
    }
    .tab-count {
        font-size: 11px;
        font-weight: 800;
        padding: 2px 7px;
        border-radius: 12px;
        background: #f1f5f9;
        color: #475569;
    }
    .tab-pill.active .tab-count {
        background: rgba(255, 255, 255, 0.2);
        color: #ffffff;
    }

    /* Metric Cards Grid */
    .metric-grid-four {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
        gap: 16px;
        margin-bottom: 22px;
    }
    .v-metric-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 16px 18px;
        display: flex;
        align-items: center;
        gap: 14px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.02);
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .v-metric-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0,0,0,0.05);
    }
    .v-metric-icon {
        width: 44px;
        height: 44px;
        border-radius: 11px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .v-metric-val {
        font-size: 20px;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.15;
        margin-bottom: 3px;
        letter-spacing: -0.02em;
    }
    .v-metric-lbl {
        font-size: 11px;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* Main Table Card */
    .rank-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 18px 20px;
        box-shadow: 0 4px 20px -5px rgba(0,0,0,0.04);
    }

    /* Table Toolbar */
    .rank-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-bottom: 16px;
        flex-wrap: wrap;
    }
    .rank-toolbar-title {
        font-size: 14px;
        font-weight: 800;
        color: #0f172a;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .rank-search-box {
        position: relative;
        width: 240px;
        height: 34px;
        display: inline-flex;
        align-items: center;
    }
    .rank-search-box input {
        width: 100% !important;
        height: 34px !important;
        padding: 0 10px 0 30px !important;
        border: 1px solid #cbd5e1 !important;
        border-radius: 8px !important;
        font-size: 12px !important;
        color: #0f172a !important;
        outline: none;
        background: #ffffff !important;
        box-sizing: border-box !important;
        transition: border-color 0.2s;
    }
    .rank-search-box input:focus {
        border-color: #0f172a !important;
    }
    .rank-search-box i,
    .rank-search-box svg {
        position: absolute !important;
        left: 9px !important;
        top: 50% !important;
        transform: translateY(-50%) !important;
        width: 14px !important;
        height: 14px !important;
        color: #94a3b8 !important;
        pointer-events: none !important;
    }

    /* Responsive Clean Table */
    .table-responsive {
        width: 100%;
        overflow-x: auto;
    }
    .rank-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }
    .rank-table th {
        background: #f8fafc;
        padding: 10px 10px;
        text-align: left;
        font-size: 10.5px;
        font-weight: 700;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        border-bottom: 2px solid #e2e8f0;
        white-space: nowrap;
    }
    .rank-table td {
        padding: 10px 10px;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
        font-size: 12px;
        color: #0f172a;
    }
    .rank-table tbody tr:hover td {
        background: #f8fafc;
    }

    /* Rank Medals & Badges */
    .rank-badge {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 11px;
        flex-shrink: 0;
    }
    .rank-gold {
        background: #fef3c7;
        color: #b45309;
        border: 2px solid #f59e0b;
        box-shadow: 0 2px 5px rgba(245,158,11,0.2);
    }
    .rank-silver {
        background: #f1f5f9;
        color: #475569;
        border: 2px solid #94a3b8;
    }
    .rank-bronze {
        background: #ffedd5;
        color: #c2410c;
        border: 2px solid #f97316;
    }
    .rank-default {
        background: #f8fafc;
        color: #64748b;
        border: 1px solid #cbd5e1;
        font-weight: 700;
    }

    /* Person Info Cell */
    .person-cell {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .person-avatar {
        width: 30px;
        height: 30px;
        border-radius: 7px;
        background: #0f172a;
        color: #ffffff;
        font-size: 10.5px;
        font-weight: 800;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .person-name {
        font-weight: 800;
        color: #0f172a;
        font-size: 12.5px;
        line-height: 1.2;
        display: flex;
        align-items: center;
        gap: 5px;
    }
    .person-sub {
        font-size: 10.5px;
        color: #64748b;
        margin-top: 1px;
        white-space: nowrap;
    }

    /* Tags & Pills */
    .tag-role {
        font-size: 9.5px;
        font-weight: 700;
        padding: 1px 5px;
        border-radius: 4px;
        background: #e2e8f0;
        color: #334155;
    }
    .pill-count {
        font-size: 10.5px;
        font-weight: 700;
        padding: 2px 7px;
        border-radius: 5px;
        background: #f1f5f9;
        color: #334155;
        border: 1px solid #cbd5e1;
        display: inline-flex;
        align-items: center;
        gap: 3px;
        white-space: nowrap;
    }
    .pill-passed {
        font-size: 10.5px;
        font-weight: 800;
        padding: 2px 8px;
        border-radius: 5px;
        background: #0f172a;
        color: #ffffff;
        display: inline-flex;
        align-items: center;
        gap: 3px;
        white-space: nowrap;
    }

    /* Progress Mini Bar */
    .progress-box {
        width: 80px;
    }
    .progress-meta {
        display: flex;
        justify-content: space-between;
        font-size: 10px;
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 2px;
    }
    .progress-track {
        width: 100%;
        height: 5px;
        background: #e2e8f0;
        border-radius: 3px;
        overflow: hidden;
    }
    .progress-fill {
        height: 100%;
        background: #0f172a;
        border-radius: 3px;
    }

    /* Tier Badges */
    .badge-tier-top {
        font-size: 10px;
        font-weight: 800;
        background: #fef3c7;
        color: #92400e;
        border: 1px solid #fde68a;
        padding: 2px 6px;
        border-radius: 5px;
        white-space: nowrap;
        display: inline-flex;
        align-items: center;
        gap: 3px;
    }
    .badge-tier-star {
        font-size: 10px;
        font-weight: 800;
        background: #e0e7ff;
        color: #3730a3;
        border: 1px solid #c7d2fe;
        padding: 2px 6px;
        border-radius: 5px;
        white-space: nowrap;
        display: inline-flex;
        align-items: center;
        gap: 3px;
    }
    .badge-tier-active {
        font-size: 10px;
        font-weight: 700;
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #e2e8f0;
        padding: 2px 6px;
        border-radius: 5px;
        white-space: nowrap;
    }

    /* Row Action Buttons */
    .btn-row-action {
        background: #f8fafc;
        color: #334155;
        border: 1px solid #cbd5e1;
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        text-decoration: none;
        white-space: nowrap;
    }
    .btn-row-action:hover {
        background: #0f172a;
        color: #ffffff;
        border-color: #0f172a;
    }
    .btn-row-action svg,
    .btn-row-action i {
        width: 12px;
        height: 12px;
    }

    .btn-row-primary {
        background: #0f172a;
        color: #ffffff;
        border: 1px solid #0f172a;
        padding: 4px 9px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        text-decoration: none;
        box-shadow: 0 2px 5px rgba(15,23,42,0.1);
        white-space: nowrap;
    }
    .btn-row-primary:hover {
        background: #2563eb;
        border-color: #2563eb;
        color: #ffffff;
        transform: translateY(-1px);
        box-shadow: 0 3px 8px rgba(37,99,235,0.2);
    }
    .btn-row-primary svg,
    .btn-row-primary i {
        width: 12px;
        height: 12px;
    }
</style>

<div class="rank-wrapper">

    <!-- Top Executive Header Banner -->
    <div class="rank-head-banner">
        <div class="rank-head-title">
            <div class="rank-head-icon">
                <i data-lucide="award"></i>
            </div>
            <div>
                <h1 style="font-size: 19px; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.02em;">Executive Leaderboard & Staff Rankings</h1>
                <p style="font-size: 12.5px; color: #64748b; margin: 3px 0 0 0;">Live production performance, pipeline velocity & leaderboard standings</p>
            </div>
        </div>
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <button type="button" class="btn-head-secondary" onclick="window.location.reload()">
                <i data-lucide="refresh-cw" style="width:13px; height:13px;"></i> Refresh
            </button>
            <a href="export_ranking.php?tab=<?= htmlspecialchars($active_tab) ?>" class="btn-head-primary">
                <i data-lucide="download" style="width:13px; height:13px;"></i> Export Report (CSV)
            </a>
        </div>
    </div>

    <!-- Segmented Navigation Bar -->
    <div class="tab-nav-card">
        <div class="segmented-tabs">
            <a href="staff_ranking.php?tab=rms" class="tab-pill <?= $active_tab === 'rms' ? 'active' : '' ?>">
                <i data-lucide="award" style="width:14px; height:14px;"></i>
                <span>RM Performance Ranks</span>
                <span class="tab-count"><?= count($rm_rankings) ?></span>
            </a>
            <a href="staff_ranking.php?tab=agents" class="tab-pill <?= $active_tab === 'agents' ? 'active' : '' ?>">
                <i data-lucide="users" style="width:14px; height:14px;"></i>
                <span>Agent & Partner Ranks</span>
                <span class="tab-count"><?= count($agent_rankings) ?></span>
            </a>
            <a href="staff_ranking.php?tab=field" class="tab-pill <?= $active_tab === 'field' ? 'active' : '' ?>">
                <i data-lucide="map-pin" style="width:14px; height:14px;"></i>
                <span>Field Staff Visits Ranks</span>
                <span class="tab-count"><?= count($visit_rankings) ?></span>
            </a>
        </div>
    </div>

    <!-- 4 KPI Metrics Grid -->
    <div class="metric-grid-four">
        
        <!-- Card 1: Top Performer -->
        <div class="v-metric-card">
            <div class="v-metric-icon" style="background: #fef3c7; color: #b45309;">
                <i data-lucide="trophy"></i>
            </div>
            <div>
                <div class="v-metric-val"><?= htmlspecialchars($top_performer) ?></div>
                <div class="v-metric-lbl">
                    <?php if ($active_tab === 'rms'): ?>
                        Top Ranked RM
                    <?php elseif ($active_tab === 'agents'): ?>
                        Top Referral Partner
                    <?php else: ?>
                        Top Field Executive
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Card 2: Volume / Visits -->
        <div class="v-metric-card">
            <div class="v-metric-icon" style="background: #ecfdf5; color: #059669;">
                <?php if ($active_tab === 'field'): ?>
                    <i data-lucide="map-pin"></i>
                <?php else: ?>
                    <i data-lucide="indian-rupee"></i>
                <?php endif; ?>
            </div>
            <div>
                <div class="v-metric-val">
                    <?php if ($active_tab === 'field'): ?>
                        <?= number_format($total_files) ?>
                    <?php else: ?>
                        ₹<?= number_format($total_disbursed) ?>
                    <?php endif; ?>
                </div>
                <div class="v-metric-lbl">
                    <?php if ($active_tab === 'field'): ?>
                        Total Field Visits
                    <?php else: ?>
                        Total Disbursed Volume
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Card 3: Active Personnel Count -->
        <div class="v-metric-card">
            <div class="v-metric-icon" style="background: #eff6ff; color: #2563eb;">
                <i data-lucide="user-check"></i>
            </div>
            <div>
                <div class="v-metric-val"><?= number_format($total_active_personnel) ?></div>
                <div class="v-metric-lbl">
                    <?php if ($active_tab === 'rms'): ?>
                        RMs Evaluated
                    <?php elseif ($active_tab === 'agents'): ?>
                        Partners Ranked
                    <?php else: ?>
                        Active Field Fleet
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Card 4: Total Files / Leads / Activity -->
        <div class="v-metric-card">
            <div class="v-metric-icon" style="background: #faf5ff; color: #7e22ce;">
                <i data-lucide="folder-check"></i>
            </div>
            <div>
                <div class="v-metric-val">
                    <?php if ($active_tab === 'rms'): ?>
                        <?= number_format($total_files) ?> Files
                    <?php elseif ($active_tab === 'agents'): ?>
                        <?= number_format($total_files) ?> Leads
                    <?php else: ?>
                        <?= count($visit_rankings) ?> Fleet
                    <?php endif; ?>
                </div>
                <div class="v-metric-lbl">
                    <?php if ($active_tab === 'rms'): ?>
                        Assigned Loan Files
                    <?php elseif ($active_tab === 'agents'): ?>
                        Total Sourced Leads
                    <?php else: ?>
                        On-Ground Fleet Team
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

    <!-- Main Leaderboard Card -->
    <div class="rank-card">

        <!-- Toolbar with Live Filter -->
        <div class="rank-toolbar">
            <div class="rank-toolbar-title">
                <i data-lucide="bar-chart-2" style="width: 16px; height: 16px; color: #0f172a;"></i>
                <span>
                    <?php if ($active_tab === 'rms'): ?>
                        Relationship Manager Standings
                    <?php elseif ($active_tab === 'agents'): ?>
                        Agent & Referral Partner Leaderboard
                    <?php else: ?>
                        Field Staff Verification & Visit Rankings
                    <?php endif; ?>
                </span>
            </div>
            <div class="rank-search-box">
                <i data-lucide="search"></i>
                <input type="text" id="leaderboardSearch" placeholder="Search name, phone, role..." onkeyup="filterLeaderboardTable()">
            </div>
        </div>

        <?php if ($active_tab === 'rms'): ?>
        <!-- ======================= RM LEADERBOARD ======================= -->
        <div class="table-responsive">
            <table class="rank-table" id="leaderboardTable">
                <thead>
                    <tr>
                        <th style="width:40px; text-align:center;">Rank</th>
                        <th>Relationship Manager</th>
                        <th style="text-align:center;">Files</th>
                        <th style="text-align:center;">Passed</th>
                        <th>Disbursed Volume</th>
                        <th>Target</th>
                        <th>Status</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rm_rankings)): ?>
                        <tr>
                            <td colspan="8" style="text-align:center; padding: 40px; color:#64748b; font-weight:600;">
                                <i data-lucide="inbox" style="width:32px; height:32px; margin-bottom:8px; display:inline-block; opacity:0.5;"></i>
                                <div>No Relationship Manager performance records available.</div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $rm_idx = 1;
                        foreach ($rm_rankings as $rm):
                            $target = $rm['monthly_target'] > 0 ? $rm['monthly_target'] : 15000000;
                            $pct = min(100, round(($rm['disbursed_volume'] / $target) * 100, 1));
                            
                            $words = preg_split("/[\s,@]+/", trim($rm['rm_name']));
                            $initials = strtoupper(substr($words[0] ?? 'R', 0, 1) . substr($words[1] ?? '', 0, 1));
                            if (strlen($initials) === 1) $initials = strtoupper(substr($rm['rm_name'], 0, 2));
                        ?>
                        <tr class="rank-row">
                            <td style="text-align:center;">
                                <?php if ($rm_idx === 1): ?>
                                    <div class="rank-badge rank-gold" title="Rank 1">
                                        <i data-lucide="trophy" style="width:13px; height:13px;"></i>
                                    </div>
                                <?php elseif ($rm_idx === 2): ?>
                                    <div class="rank-badge rank-silver" title="Rank 2">
                                        <i data-lucide="medal" style="width:13px; height:13px;"></i>
                                    </div>
                                <?php elseif ($rm_idx === 3): ?>
                                    <div class="rank-badge rank-bronze" title="Rank 3">
                                        <i data-lucide="award" style="width:13px; height:13px;"></i>
                                    </div>
                                <?php else: ?>
                                    <div class="rank-badge rank-default">
                                        #<?= $rm_idx ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="person-cell">
                                    <div class="person-avatar"><?= htmlspecialchars($initials) ?></div>
                                    <div>
                                        <div class="person-name">
                                            <?= htmlspecialchars($rm['rm_name']) ?>
                                            <span class="tag-role"><?= htmlspecialchars($rm['designation'] ?: $rm['role']) ?></span>
                                        </div>
                                        <div class="person-sub"><?= htmlspecialchars($rm['username']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td style="text-align:center;">
                                <span class="pill-count">
                                    <i data-lucide="folder" style="width:11px; height:11px; color:#64748b;"></i>
                                    <?= $rm['total_assigned'] ?>
                                </span>
                            </td>
                            <td style="text-align:center;">
                                <span class="pill-passed">
                                    <i data-lucide="check" style="width:11px; height:11px;"></i>
                                    <?= $rm['approved_cnt'] ?>
                                </span>
                            </td>
                            <td style="font-weight:800; color:#0f172a; white-space:nowrap;">
                                ₹ <?= number_format($rm['disbursed_volume']) ?>
                            </td>
                            <td>
                                <div class="progress-box">
                                    <div class="progress-meta">
                                        <span><?= $pct ?>%</span>
                                        <span style="color:#64748b; font-weight:600;">₹<?= round($target / 100000, 1) ?>L</span>
                                    </div>
                                    <div class="progress-track">
                                        <div class="progress-fill" style="width: <?= max(4, $pct) ?>%;"></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if ($rm_idx === 1): ?>
                                    <span class="badge-tier-top"><i data-lucide="award" style="width:11px; height:11px;"></i> Top RM</span>
                                <?php elseif ($rm_idx <= 3): ?>
                                    <span class="badge-tier-star"><i data-lucide="star" style="width:11px; height:11px;"></i> Star RM</span>
                                <?php else: ?>
                                    <span class="badge-tier-active">Active RM</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right; white-space:nowrap;">
                                <div style="display:inline-flex; gap:5px; align-items:center;">
                                    <a href="leads.php?assigned_to=<?= urlencode($rm['username']) ?>" class="btn-row-action" title="View Assigned Leads & Files">
                                        <i data-lucide="folder-open"></i> Portfolio
                                    </a>
                                    <?php if ($is_admin): ?>
                                    <a href="employee_performance_view.php?username=<?= urlencode($rm['username']) ?>" class="btn-row-primary" title="View Detailed Performance Breakdown">
                                        <i data-lucide="bar-chart-2"></i> Report
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php $rm_idx++; endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php elseif ($active_tab === 'agents'): ?>
        <!-- ======================= AGENTS & PARTNERS LEADERBOARD ======================= -->
        <div class="table-responsive">
            <table class="rank-table" id="leaderboardTable">
                <thead>
                    <tr>
                        <th style="width:40px; text-align:center;">Rank</th>
                        <th>Agent / Sourcing Partner</th>
                        <th style="text-align:center;">Sourced</th>
                        <th style="text-align:center;">Passed</th>
                        <th>Disbursed Volume</th>
                        <th>Tier</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($agent_rankings)): ?>
                        <tr>
                            <td colspan="7" style="text-align:center; padding: 40px; color:#64748b; font-weight:600;">
                                <i data-lucide="inbox" style="width:32px; height:32px; margin-bottom:8px; display:inline-block; opacity:0.5;"></i>
                                <div>No agent or referral partner performance records available.</div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $ag_idx = 1;
                        foreach ($agent_rankings as $ag):
                            $words = preg_split("/[\s,@]+/", trim($ag['agent_name']));
                            $initials = strtoupper(substr($words[0] ?? 'A', 0, 1) . substr($words[1] ?? '', 0, 1));
                            if (strlen($initials) === 1) $initials = strtoupper(substr($ag['agent_name'], 0, 2));
                        ?>
                        <tr class="rank-row">
                            <td style="text-align:center;">
                                <?php if ($ag_idx === 1): ?>
                                    <div class="rank-badge rank-gold" title="Rank 1">
                                        <i data-lucide="trophy" style="width:13px; height:13px;"></i>
                                    </div>
                                <?php elseif ($ag_idx === 2): ?>
                                    <div class="rank-badge rank-silver" title="Rank 2">
                                        <i data-lucide="medal" style="width:13px; height:13px;"></i>
                                    </div>
                                <?php elseif ($ag_idx === 3): ?>
                                    <div class="rank-badge rank-bronze" title="Rank 3">
                                        <i data-lucide="award" style="width:13px; height:13px;"></i>
                                    </div>
                                <?php else: ?>
                                    <div class="rank-badge rank-default">
                                        #<?= $ag_idx ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="person-cell">
                                    <div class="person-avatar" style="background:#1e293b;"><?= htmlspecialchars($initials) ?></div>
                                    <div>
                                        <div class="person-name">
                                            <?= htmlspecialchars($ag['agent_name']) ?>
                                            <span class="tag-role"><?= htmlspecialchars($ag['partner_type']) ?></span>
                                        </div>
                                        <div class="person-sub">
                                            <?= htmlspecialchars($ag['username']) ?>
                                            <?= !empty($ag['mobile']) ? ' &bull; ' . htmlspecialchars($ag['mobile']) : '' ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td style="text-align:center;">
                                <span class="pill-count">
                                    <i data-lucide="users" style="width:11px; height:11px; color:#64748b;"></i>
                                    <?= $ag['total_sourced_leads'] ?> Leads
                                </span>
                            </td>
                            <td style="text-align:center;">
                                <span class="pill-passed">
                                    <i data-lucide="check" style="width:11px; height:11px;"></i>
                                    <?= $ag['approved_leads'] ?>
                                </span>
                            </td>
                            <td style="font-weight:800; color:#0f172a; white-space:nowrap;">
                                ₹ <?= number_format($ag['total_volume']) ?>
                            </td>
                            <td>
                                <?php if ($ag_idx === 1): ?>
                                    <span class="badge-tier-top"><i data-lucide="award" style="width:11px; height:11px;"></i> Platinum</span>
                                <?php elseif ($ag_idx <= 3): ?>
                                    <span class="badge-tier-star"><i data-lucide="star" style="width:11px; height:11px;"></i> Star</span>
                                <?php else: ?>
                                    <span class="badge-tier-active">Active</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right; white-space:nowrap;">
                                <div style="display:inline-flex; gap:5px; align-items:center;">
                                    <a href="referrals_list.php?search=<?= urlencode($ag['agent_name']) ?>" class="btn-row-action" title="View Partner Profile">
                                        <i data-lucide="user"></i> Profile
                                    </a>
                                    <a href="leads.php?search=<?= urlencode($ag['agent_name']) ?>" class="btn-row-primary" title="View Referred Leads">
                                        <i data-lucide="list"></i> Sourced
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php $ag_idx++; endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php else: ?>
        <!-- ======================= FIELD STAFF VISITS LEADERBOARD ======================= -->
        <div class="table-responsive">
            <table class="rank-table" id="leaderboardTable">
                <thead>
                    <tr>
                        <th style="width:40px; text-align:center;">Rank</th>
                        <th>Executive Name</th>
                        <th style="text-align:center;">Field Visits</th>
                        <th style="text-align:center;">Borrowers Visited</th>
                        <th>Last Active Visit Date</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($visit_rankings)): ?>
                        <tr>
                            <td colspan="6" style="text-align:center; padding: 40px; color:#64748b; font-weight:600;">
                                <i data-lucide="inbox" style="width:32px; height:32px; margin-bottom:8px; display:inline-block; opacity:0.5;"></i>
                                <div>No field visits recorded yet.</div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $f_idx = 1;
                        foreach ($visit_rankings as $staff):
                            $words = preg_split("/[\s,@]+/", trim($staff['executive_name']));
                            $initials = strtoupper(substr($words[0] ?? 'F', 0, 1) . substr($words[1] ?? '', 0, 1));
                            if (strlen($initials) === 1) $initials = strtoupper(substr($staff['executive_name'], 0, 2));
                        ?>
                        <tr class="rank-row">
                            <td style="text-align:center;">
                                <?php if ($f_idx === 1): ?>
                                    <div class="rank-badge rank-gold" title="Rank 1">
                                        <i data-lucide="trophy" style="width:13px; height:13px;"></i>
                                    </div>
                                <?php elseif ($f_idx === 2): ?>
                                    <div class="rank-badge rank-silver" title="Rank 2">
                                        <i data-lucide="medal" style="width:13px; height:13px;"></i>
                                    </div>
                                <?php elseif ($f_idx === 3): ?>
                                    <div class="rank-badge rank-bronze" title="Rank 3">
                                        <i data-lucide="award" style="width:13px; height:13px;"></i>
                                    </div>
                                <?php else: ?>
                                    <div class="rank-badge rank-default">
                                        #<?= $f_idx ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="person-cell">
                                    <div class="person-avatar" style="background:#0f766e;"><?= htmlspecialchars($initials) ?></div>
                                    <div>
                                        <div class="person-name">
                                            <?= htmlspecialchars($staff['executive_name']) ?>
                                            <span class="tag-role">Field Executive</span>
                                        </div>
                                        <div class="person-sub">Field Fleet Operations</div>
                                    </div>
                                </div>
                            </td>
                            <td style="text-align:center;">
                                <span class="pill-passed">
                                    <i data-lucide="map-pin" style="width:11px; height:11px;"></i>
                                    <?= $staff['total_visits'] ?> Visits
                                </span>
                            </td>
                            <td style="text-align:center;">
                                <span class="pill-count">
                                    <i data-lucide="user-check" style="width:11px; height:11px; color:#64748b;"></i>
                                    <?= $staff['distinct_clients'] ?> Clients
                                </span>
                            </td>
                            <td style="color:#64748b; font-size:12px; font-weight:600; white-space:nowrap;">
                                <i data-lucide="clock" style="width:12px; height:12px; vertical-align:middle; margin-right:3px;"></i>
                                <?= date('d M Y, h:i A', strtotime($staff['last_activity'])) ?>
                            </td>
                            <td style="text-align:right; white-space:nowrap;">
                                <a href="field_visits.php" class="btn-row-action" title="View Full Field Visit Details">
                                    <i data-lucide="map"></i> Visits Log
                                </a>
                            </td>
                        </tr>
                        <?php $f_idx++; endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    </div>

</div>

<script>
    // Live Client-Side Table Search Filter
    function filterLeaderboardTable() {
        const input = document.getElementById('leaderboardSearch');
        if (!input) return;
        const filter = input.value.toLowerCase().trim();
        const rows = document.querySelectorAll('.rank-row');
        
        rows.forEach(row => {
            const text = row.innerText.toLowerCase();
            if (text.includes(filter)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    });
</script>

<?php require_once 'footer.php'; ?>
