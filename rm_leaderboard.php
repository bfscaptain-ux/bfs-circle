<?php
require_once __DIR__ . '/config.php';
require_role(['Admin', 'RM']);

$current_page = 'rm_leaderboard.php';
$page_title = 'Executive Performance Hub & Leaderboard';
$page_subtitle = 'Live production metrics, target velocity & celebration hub for Relationship Managers, DSAs, and Field Force';
$hide_main_header = true;
require_once __DIR__ . '/header.php';

// Active Category Tab: 'rms' (default), 'agents', 'field'
$active_type = $_GET['type'] ?? ($_GET['tab'] ?? 'rms');
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'Admin';

// 1. RM Performance Rankings (Strictly RMs & Staff managing loans, excluding external partners)
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

// 2. Agent & Partner Sourcing Rankings
$agent_sql = "
    SELECT 
        u.id as user_id,
        COALESCE(r.full_name, u.name, u.full_name, u.username) as agent_name,
        u.username,
        COALESCE(r.referrer_type, u.role, 'Agent / DSA') as partner_type,
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

// 3. Field Staff Visits Rankings
$visit_sql = "
    SELECT 
        fv.executive_name as field_name,
        COUNT(*) as total_visits,
        COUNT(DISTINCT fv.person_name) as clients_visited,
        MAX(fv.created_at) as last_activity
    FROM field_visits fv
    WHERE fv.executive_name IS NOT NULL AND fv.executive_name != ''
    GROUP BY fv.executive_name
    ORDER BY total_visits DESC, last_activity DESC
";
$visit_rankings = $visit_stmt = $db->query($visit_sql)->fetchAll(PDO::FETCH_ASSOC);

// Currency Formatter
function formatINR_Leaderboard($n) {
    $n = floatval($n);
    if ($n >= 10000000) return '₹' . number_format($n / 10000000, 2) . ' Cr';
    if ($n >= 100000)   return '₹' . number_format($n / 100000, 2) . ' L';
    if ($n >= 1000)     return '₹' . number_format($n / 1000, 1) . 'K';
    return '₹' . number_format($n, 0);
}

// Days remaining in month
$days_in_month = date('t');
$current_day = date('j');
$days_left = max(0, $days_in_month - $current_day);
?>

<!-- Canvas Confetti Library -->
<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>

<style>
    .leaderboard-wrapper {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        color: #0f172a;
        padding: 6px 4px 40px 4px;
        width: 100%;
        margin: 0 auto;
    }

    /* Executive Top Header Banner */
    .lead-head-banner {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 20px 24px;
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.02);
    }
    .lead-head-title {
        display: flex;
        align-items: center;
        gap: 14px;
    }
    .lead-head-icon {
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

    /* Race Pill & Celebrate Button */
    .badge-race {
        background: #f8fafc;
        border: 1px solid #cbd5e1;
        color: #334155;
        font-size: 12px;
        font-weight: 700;
        padding: 6px 12px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .btn-head-celebrate {
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
        box-shadow: 0 4px 12px rgba(15,23,42,0.15);
    }
    .btn-head-celebrate:hover {
        background: #1e293b;
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(15,23,42,0.22);
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
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        color: #0f172a;
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

    /* Executive Top Performers Spotlight Card */
    .spotlight-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 20px 22px;
        margin-bottom: 22px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.02);
    }
    .spotlight-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 1px solid #f1f5f9;
    }
    .spotlight-title {
        font-size: 13.5px;
        font-weight: 800;
        color: #0f172a;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .spotlight-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
    }
    @media (max-width: 900px) {
        .spotlight-grid { grid-template-columns: 1fr; }
    }

    .performer-box {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 16px;
        transition: all 0.2s;
        cursor: pointer;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .performer-box:hover {
        background: #ffffff;
        border-color: #0f172a;
        transform: translateY(-2px);
        box-shadow: 0 4px 14px rgba(15,23,42,0.06);
    }

    .performer-top-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
    }

    .rank-pill {
        font-size: 11px;
        font-weight: 800;
        padding: 3px 9px;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .rank-pill-1 {
        background: #0f172a;
        color: #ffffff;
    }
    .rank-pill-2 {
        background: #e2e8f0;
        color: #334155;
    }
    .rank-pill-3 {
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #cbd5e1;
    }

    .performer-avatar {
        width: 34px;
        height: 34px;
        border-radius: 8px;
        background: #0f172a;
        color: #ffffff;
        font-size: 11.5px;
        font-weight: 800;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .performer-name {
        font-size: 15px;
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 2px;
    }
    .performer-role {
        font-size: 11.5px;
        color: #64748b;
        margin-bottom: 12px;
    }
    .performer-metric {
        font-size: 20px;
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 4px;
        letter-spacing: -0.02em;
    }
    .performer-sub {
        font-size: 11.5px;
        font-weight: 600;
        color: #64748b;
    }

    /* Main Table Card */
    .table-card {
        background: #ffffff;
        border-radius: 14px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 20px -5px rgba(15, 23, 42, 0.04);
        padding: 18px 20px;
    }

    .table-header-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-bottom: 16px;
        flex-wrap: wrap;
    }
    .table-title {
        font-size: 14px;
        font-weight: 800;
        color: #0f172a;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .search-box-wrap {
        position: relative;
        width: 240px;
        height: 34px;
        display: inline-flex;
        align-items: center;
    }
    .search-box-wrap input {
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
    .search-box-wrap input:focus {
        border-color: #0f172a !important;
    }
    .search-box-wrap i,
    .search-box-wrap svg {
        position: absolute !important;
        left: 9px !important;
        top: 50% !important;
        transform: translateY(-50%) !important;
        width: 14px !important;
        height: 14px !important;
        color: #94a3b8 !important;
        pointer-events: none !important;
    }

    /* Table Styles */
    .table-responsive {
        width: 100%;
        overflow-x: auto;
    }
    .rm-rank-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }
    .rm-rank-table th {
        background: #f8fafc;
        padding: 9px 8px;
        text-align: left;
        font-size: 10.5px;
        font-weight: 700;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        border-bottom: 2px solid #e2e8f0;
        white-space: nowrap;
    }
    .rm-rank-table td {
        padding: 9px 8px;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
        font-size: 12px;
        color: #0f172a;
    }
    .rm-rank-table tbody tr:hover td {
        background: #f8fafc;
    }

    /* Rank Badges */
    .rank-num-badge {
        width: 26px;
        height: 26px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 11px;
        flex-shrink: 0;
        background: #f8fafc;
        border: 1px solid #cbd5e1;
        color: #475569;
    }
    .rank-badge-1 {
        background: #0f172a;
        color: #ffffff;
        border-color: #0f172a;
    }

    /* Person Info Cell */
    .person-cell {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .person-avatar {
        width: 28px;
        height: 28px;
        border-radius: 6px;
        background: #0f172a;
        color: #ffffff;
        font-size: 10px;
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

    /* Target Progress */
    .progress-box {
        width: 75px;
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

    /* Action Buttons */
    .btn-action-outline {
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
    .btn-action-outline:hover {
        background: #0f172a;
        color: #ffffff;
        border-color: #0f172a;
    }

    .btn-cheer {
        background: #0f172a;
        color: #ffffff;
        border: 1px solid #0f172a;
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        white-space: nowrap;
    }
    .btn-cheer:hover {
        background: #1e293b;
        transform: translateY(-1px);
        box-shadow: 0 2px 6px rgba(15,23,42,0.15);
    }

    /* Floating Interactive Toast */
    .cheer-toast-container {
        position: fixed;
        bottom: 28px;
        right: 28px;
        z-index: 9999;
        display: flex;
        flex-direction: column;
        gap: 10px;
        pointer-events: none;
    }
    .cheer-toast {
        background: #0f172a;
        color: #ffffff;
        border: 1px solid #334155;
        padding: 12px 18px;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 700;
        box-shadow: 0 10px 25px rgba(0,0,0,0.25);
        display: flex;
        align-items: center;
        gap: 10px;
        pointer-events: auto;
        animation: toastSlideIn 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    @keyframes toastSlideIn {
        from { transform: translateY(20px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
</style>

<div class="leaderboard-wrapper">

    <!-- Top Executive Header Banner -->
    <div class="lead-head-banner">
        <div class="lead-head-title">
            <div class="lead-head-icon">
                <i data-lucide="award"></i>
            </div>
            <div>
                <h1 style="font-size: 19px; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.02em;">RM Disbursal Champions Leaderboard</h1>
                <p style="font-size: 12.5px; color: #64748b; margin: 3px 0 0 0;">Production velocity, sanctioned files &amp; month-to-date target tracking for Relationship Managers</p>
            </div>
        </div>
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <span class="badge-race">
                <i data-lucide="clock" style="width:13px; height:13px; color:#475569;"></i>
                <?= date('F Y') ?> Race &bull; <?= $days_left ?> Days Left
            </span>
            <button type="button" onclick="triggerGrandCelebration('RM Fleet')" class="btn-head-celebrate">
                <i data-lucide="sparkles" style="width:13px; height:13px;"></i> Celebrate Performance
            </button>
        </div>
    </div>

    <!-- 3 Category Navigation Tabs -->
    <div class="tab-nav-card">
        <div class="segmented-tabs">
            <a href="rm_leaderboard.php?type=rms" class="tab-pill <?= $active_type === 'rms' ? 'active' : '' ?>">
                <i data-lucide="award" style="width:14px; height:14px;"></i>
                <span>Relationship Managers (RM)</span>
                <span class="tab-count"><?= count($rm_rankings) ?></span>
            </a>
            <a href="rm_leaderboard.php?type=agents" class="tab-pill <?= $active_type === 'agents' ? 'active' : '' ?>">
                <i data-lucide="users" style="width:14px; height:14px;"></i>
                <span>Agents &amp; Referral DSAs</span>
                <span class="tab-count"><?= count($agent_rankings) ?></span>
            </a>
            <a href="rm_leaderboard.php?type=field" class="tab-pill <?= $active_type === 'field' ? 'active' : '' ?>">
                <i data-lucide="map-pin" style="width:14px; height:14px;"></i>
                <span>Field Staff Visits</span>
                <span class="tab-count"><?= count($visit_rankings) ?></span>
            </a>
        </div>
    </div>

    <?php if ($active_type === 'rms'): ?>
    <!-- ================================================================= -->
    <!-- CATEGORY 1: RELATIONSHIP MANAGERS (RM) LEADERBOARD               -->
    <!-- ================================================================= -->
    <?php
    $total_disbursed = array_sum(array_column($rm_rankings, 'disbursed_volume'));
    $total_approved = array_sum(array_column($rm_rankings, 'approved_cnt'));
    $top_rm_champion = $rm_rankings[0] ?? null;
    ?>

    <!-- RM KPIs (Monochrome Corporate Clean) -->
    <div class="metric-grid-four">
        <div class="v-metric-card">
            <div class="v-metric-icon">
                <i data-lucide="indian-rupee"></i>
            </div>
            <div>
                <div class="v-metric-lbl">Total RM Disbursed</div>
                <div class="v-metric-val"><?= formatINR_Leaderboard($total_disbursed) ?></div>
            </div>
        </div>
        <div class="v-metric-card">
            <div class="v-metric-icon">
                <i data-lucide="check-circle-2"></i>
            </div>
            <div>
                <div class="v-metric-lbl">Sanctioned Files</div>
                <div class="v-metric-val"><?= number_format($total_approved) ?> Cases</div>
            </div>
        </div>
        <div class="v-metric-card">
            <div class="v-metric-icon">
                <i data-lucide="trophy"></i>
            </div>
            <div>
                <div class="v-metric-lbl">Current #1 Champion</div>
                <div class="v-metric-val"><?= htmlspecialchars($top_rm_champion['rm_name'] ?? 'N/A') ?></div>
            </div>
        </div>
        <div class="v-metric-card">
            <div class="v-metric-icon">
                <i data-lucide="user-check"></i>
            </div>
            <div>
                <div class="v-metric-lbl">Ranked RM Officers</div>
                <div class="v-metric-val"><?= count($rm_rankings) ?> Officers</div>
            </div>
        </div>
    </div>

    <!-- RM Spotlight (Top 3 Executive Cards) -->
    <div class="spotlight-card">
        <div class="spotlight-head">
            <div class="spotlight-title">
                <i data-lucide="award" style="width:16px; height:16px; color:#0f172a;"></i>
                <span>Executive RM Spotlight &bull; Month-to-Date Leaders</span>
            </div>
            <span style="font-size:11.5px; font-weight:600; color:#64748b;">Sorted by Total Disbursed Volume</span>
        </div>

        <div class="spotlight-grid">
            <?php 
            $r1 = $rm_rankings[0] ?? null; 
            $r2 = $rm_rankings[1] ?? null; 
            $r3 = $rm_rankings[2] ?? null; 
            ?>
            
            <!-- #1 Champion Performer -->
            <div class="performer-box" onclick="cheerPerformer('<?= htmlspecialchars($r1['rm_name'] ?? 'Champion RM') ?>')">
                <div>
                    <div class="performer-top-row">
                        <span class="rank-pill rank-pill-1"><i data-lucide="crown" style="width:12px; height:12px;"></i> #1 Champion RM</span>
                        <div class="performer-avatar"><?= strtoupper(substr($r1['rm_name'] ?? 'RM', 0, 2)) ?></div>
                    </div>
                    <div class="performer-name"><?= htmlspecialchars($r1['rm_name'] ?? 'Awaiting Disbursals') ?></div>
                    <div class="performer-role"><?= htmlspecialchars($r1['designation'] ?? 'Senior RM') ?></div>
                </div>
                <div>
                    <div class="performer-metric"><?= formatINR_Leaderboard($r1['disbursed_volume'] ?? 0) ?></div>
                    <div class="performer-sub"><?= intval($r1['approved_cnt'] ?? 0) ?> Sanctioned Files &bull; 70% Target</div>
                </div>
            </div>

            <!-- #2 Silver Performer -->
            <div class="performer-box" onclick="cheerPerformer('<?= htmlspecialchars($r2['rm_name'] ?? 'Silver RM') ?>')">
                <div>
                    <div class="performer-top-row">
                        <span class="rank-pill rank-pill-2"><i data-lucide="medal" style="width:12px; height:12px;"></i> #2 Silver RM</span>
                        <div class="performer-avatar" style="background:#334155;"><?= strtoupper(substr($r2['rm_name'] ?? 'RM', 0, 2)) ?></div>
                    </div>
                    <div class="performer-name"><?= htmlspecialchars($r2['rm_name'] ?? 'Awaiting Disbursals') ?></div>
                    <div class="performer-role"><?= htmlspecialchars($r2['designation'] ?? 'Relationship Manager') ?></div>
                </div>
                <div>
                    <div class="performer-metric"><?= formatINR_Leaderboard($r2['disbursed_volume'] ?? 0) ?></div>
                    <div class="performer-sub"><?= intval($r2['approved_cnt'] ?? 0) ?> Sanctioned Files &bull; 60% Target</div>
                </div>
            </div>

            <!-- #3 Bronze Performer -->
            <div class="performer-box" onclick="cheerPerformer('<?= htmlspecialchars($r3['rm_name'] ?? 'Bronze RM') ?>')">
                <div>
                    <div class="performer-top-row">
                        <span class="rank-pill rank-pill-3"><i data-lucide="award" style="width:12px; height:12px;"></i> #3 Bronze RM</span>
                        <div class="performer-avatar" style="background:#475569;"><?= strtoupper(substr($r3['rm_name'] ?? 'RM', 0, 2)) ?></div>
                    </div>
                    <div class="performer-name"><?= htmlspecialchars($r3['rm_name'] ?? 'Awaiting Disbursals') ?></div>
                    <div class="performer-role"><?= htmlspecialchars($r3['designation'] ?? 'Field Executive') ?></div>
                </div>
                <div>
                    <div class="performer-metric"><?= formatINR_Leaderboard($r3['disbursed_volume'] ?? 0) ?></div>
                    <div class="performer-sub"><?= intval($r3['approved_cnt'] ?? 0) ?> Sanctioned File &bull; In Pipeline</div>
                </div>
            </div>
        </div>
    </div>

    <!-- RM Table -->
    <div class="table-card">
        <div class="table-header-row">
            <div class="table-title">
                <i data-lucide="bar-chart-2" style="width:16px; height:16px; color:#0f172a;"></i>
                <span>Relationship Manager Standings Table</span>
            </div>
            <div class="search-box-wrap">
                <i data-lucide="search"></i>
                <input type="text" id="rm-search" oninput="filterTable('rm-search','.rm-row')" placeholder="Search RM officer...">
            </div>
        </div>
        <div class="table-responsive">
            <table class="rm-rank-table" id="rmTable">
                <thead>
                    <tr>
                        <th style="width:38px; text-align:center;">Rank</th>
                        <th>RM Officer</th>
                        <th>Disbursed</th>
                        <th>Target</th>
                        <th style="text-align:center;">Passed</th>
                        <th style="text-align:center;">Assigned</th>
                        <th>Incentive</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $idx = 1;
                    foreach ($rm_rankings as $rm):
                        $disb = floatval($rm['disbursed_volume']);
                        $tgt = floatval($rm['monthly_target']) > 0 ? floatval($rm['monthly_target']) : 15000000;
                        $pct = min(100, round(($disb / $tgt) * 100, 1));
                        
                        $words = preg_split("/[\s,@]+/", trim($rm['rm_name']));
                        $initials = strtoupper(substr($words[0] ?? 'R', 0, 1) . substr($words[1] ?? '', 0, 1));
                        if (strlen($initials) === 1) $initials = strtoupper(substr($rm['rm_name'], 0, 2));
                    ?>
                    <tr class="rm-row">
                        <td style="text-align:center;">
                            <div class="rank-num-badge <?= $idx === 1 ? 'rank-badge-1' : '' ?>">
                                #<?= $idx ?>
                            </div>
                        </td>
                        <td>
                            <div class="person-cell">
                                <div class="person-avatar"><?= htmlspecialchars($initials) ?></div>
                                <div>
                                    <div class="person-name">
                                        <?= htmlspecialchars($rm['rm_name']) ?>
                                        <span class="tag-role"><?= htmlspecialchars($rm['designation']) ?></span>
                                    </div>
                                    <div class="person-sub"><?= htmlspecialchars($rm['username']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td style="font-weight:800; color:#0f172a; white-space:nowrap;">
                            <?= formatINR_Leaderboard($disb) ?>
                        </td>
                        <td>
                            <div class="progress-box">
                                <div class="progress-meta">
                                    <span><?= $pct ?>%</span>
                                    <span style="color:#64748b;">₹<?= round($tgt / 100000, 1) ?>L</span>
                                </div>
                                <div class="progress-track">
                                    <div class="progress-fill" style="width: <?= max(4, $pct) ?>%;"></div>
                                </div>
                            </div>
                        </td>
                        <td style="text-align:center;">
                            <span class="pill-passed">
                                <i data-lucide="check" style="width:11px; height:11px;"></i>
                                <?= intval($rm['approved_cnt']) ?>
                            </span>
                        </td>
                        <td style="text-align:center;">
                            <span class="pill-count">
                                <i data-lucide="folder" style="width:11px; height:11px; color:#64748b;"></i>
                                <?= intval($rm['total_assigned']) ?>
                            </span>
                        </td>
                        <td style="font-weight:700; color:#0f172a; white-space:nowrap;">
                            <?= formatINR_Leaderboard($disb * 0.002) ?>
                        </td>
                        <td style="text-align:right; white-space:nowrap;">
                            <div style="display:inline-flex; gap:5px; align-items:center;">
                                <a href="leads.php?assigned_to=<?= urlencode($rm['username']) ?>" class="btn-action-outline" title="View RM Pipeline">
                                    <i data-lucide="folder-open" style="width:11px; height:11px;"></i> Portfolio
                                </a>
                                <button type="button" onclick="cheerPerformer('<?= htmlspecialchars($rm['rm_name']) ?>')" class="btn-cheer" title="Cheer for this RM">
                                    <i data-lucide="sparkles" style="width:11px; height:11px;"></i> Cheer
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php $idx++; endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php elseif ($active_type === 'agents'): ?>
    <!-- ================================================================= -->
    <!-- CATEGORY 2: AGENTS & REFERRAL DSAS LEADERBOARD                    -->
    <!-- ================================================================= -->
    <?php
    $total_agent_leads = array_sum(array_column($agent_rankings, 'total_sourced_leads'));
    $total_agent_approved = array_sum(array_column($agent_rankings, 'approved_leads'));
    $total_agent_volume = array_sum(array_column($agent_rankings, 'total_volume'));
    $top_agent = $agent_rankings[0] ?? null;
    ?>

    <!-- AGENT KPIs -->
    <div class="metric-grid-four">
        <div class="v-metric-card">
            <div class="v-metric-icon">
                <i data-lucide="indian-rupee"></i>
            </div>
            <div>
                <div class="v-metric-lbl">Total Sourced Volume</div>
                <div class="v-metric-val"><?= formatINR_Leaderboard($total_agent_volume) ?></div>
            </div>
        </div>
        <div class="v-metric-card">
            <div class="v-metric-icon">
                <i data-lucide="target"></i>
            </div>
            <div>
                <div class="v-metric-lbl">Total Sourced Leads</div>
                <div class="v-metric-val"><?= number_format($total_agent_leads) ?> Leads</div>
            </div>
        </div>
        <div class="v-metric-card">
            <div class="v-metric-icon">
                <i data-lucide="trophy"></i>
            </div>
            <div>
                <div class="v-metric-lbl">Top Sourcing Partner</div>
                <div class="v-metric-val"><?= htmlspecialchars($top_agent['agent_name'] ?? 'N/A') ?></div>
            </div>
        </div>
        <div class="v-metric-card">
            <div class="v-metric-icon">
                <i data-lucide="briefcase"></i>
            </div>
            <div>
                <div class="v-metric-lbl">Active Partners</div>
                <div class="v-metric-val"><?= count($agent_rankings) ?> Partners</div>
            </div>
        </div>
    </div>

    <!-- Agent Spotlight -->
    <div class="spotlight-card">
        <div class="spotlight-head">
            <div class="spotlight-title">
                <i data-lucide="trophy" style="width:16px; height:16px; color:#0f172a;"></i>
                <span>Top Sourcing Partners &bull; Referral Network Leaders</span>
            </div>
            <span style="font-size:11.5px; font-weight:600; color:#64748b;">Sorted by Converted Leads &amp; Volume</span>
        </div>

        <div class="spotlight-grid">
            <?php 
            $a1 = $agent_rankings[0] ?? null; 
            $a2 = $agent_rankings[1] ?? null; 
            $a3 = $agent_rankings[2] ?? null; 
            ?>
            
            <div class="performer-box" onclick="cheerPerformer('<?= htmlspecialchars($a1['agent_name'] ?? 'Champion Partner') ?>')">
                <div>
                    <div class="performer-top-row">
                        <span class="rank-pill rank-pill-1"><i data-lucide="crown" style="width:12px; height:12px;"></i> #1 Top Partner</span>
                        <div class="performer-avatar"><?= strtoupper(substr($a1['agent_name'] ?? 'AG', 0, 2)) ?></div>
                    </div>
                    <div class="performer-name"><?= htmlspecialchars($a1['agent_name'] ?? 'Awaiting Sourcing') ?></div>
                    <div class="performer-role"><?= htmlspecialchars($a1['partner_type'] ?? 'DSA Partner') ?></div>
                </div>
                <div>
                    <div class="performer-metric"><?= formatINR_Leaderboard($a1['total_volume'] ?? 0) ?></div>
                    <div class="performer-sub"><?= intval($a1['approved_leads'] ?? 0) ?> Converted &bull; <?= intval($a1['total_sourced_leads'] ?? 0) ?> Sourced</div>
                </div>
            </div>

            <div class="performer-box" onclick="cheerPerformer('<?= htmlspecialchars($a2['agent_name'] ?? 'Silver Partner') ?>')">
                <div>
                    <div class="performer-top-row">
                        <span class="rank-pill rank-pill-2"><i data-lucide="medal" style="width:12px; height:12px;"></i> #2 Partner</span>
                        <div class="performer-avatar" style="background:#334155;"><?= strtoupper(substr($a2['agent_name'] ?? 'AG', 0, 2)) ?></div>
                    </div>
                    <div class="performer-name"><?= htmlspecialchars($a2['agent_name'] ?? 'Awaiting Sourcing') ?></div>
                    <div class="performer-role"><?= htmlspecialchars($a2['partner_type'] ?? 'Agent') ?></div>
                </div>
                <div>
                    <div class="performer-metric"><?= formatINR_Leaderboard($a2['total_volume'] ?? 0) ?></div>
                    <div class="performer-sub"><?= intval($a2['approved_leads'] ?? 0) ?> Converted &bull; <?= intval($a2['total_sourced_leads'] ?? 0) ?> Sourced</div>
                </div>
            </div>

            <div class="performer-box" onclick="cheerPerformer('<?= htmlspecialchars($a3['agent_name'] ?? 'Bronze Partner') ?>')">
                <div>
                    <div class="performer-top-row">
                        <span class="rank-pill rank-pill-3"><i data-lucide="award" style="width:12px; height:12px;"></i> #3 Partner</span>
                        <div class="performer-avatar" style="background:#475569;"><?= strtoupper(substr($a3['agent_name'] ?? 'AG', 0, 2)) ?></div>
                    </div>
                    <div class="performer-name"><?= htmlspecialchars($a3['agent_name'] ?? 'Awaiting Sourcing') ?></div>
                    <div class="performer-role"><?= htmlspecialchars($a3['partner_type'] ?? 'Agent') ?></div>
                </div>
                <div>
                    <div class="performer-metric"><?= formatINR_Leaderboard($a3['total_volume'] ?? 0) ?></div>
                    <div class="performer-sub"><?= intval($a3['approved_leads'] ?? 0) ?> Converted &bull; <?= intval($a3['total_sourced_leads'] ?? 0) ?> Sourced</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Agent Table -->
    <div class="table-card">
        <div class="table-header-row">
            <div class="table-title">
                <i data-lucide="users" style="width:16px; height:16px; color:#0f172a;"></i>
                <span>Agent &amp; DSA Referral Performance Table</span>
            </div>
            <div class="search-box-wrap">
                <i data-lucide="search"></i>
                <input type="text" id="agent-search" oninput="filterTable('agent-search','.agent-row')" placeholder="Search partner name...">
            </div>
        </div>
        <div class="table-responsive">
            <table class="rm-rank-table" id="agentTable">
                <thead>
                    <tr>
                        <th style="width:38px; text-align:center;">Rank</th>
                        <th>Partner Name</th>
                        <th style="text-align:center;">Sourced</th>
                        <th style="text-align:center;">Approved</th>
                        <th>Total Volume (₹)</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $idx = 1;
                    foreach ($agent_rankings as $ag):
                        $words = preg_split("/[\s,@]+/", trim($ag['agent_name']));
                        $initials = strtoupper(substr($words[0] ?? 'A', 0, 1) . substr($words[1] ?? '', 0, 1));
                        if (strlen($initials) === 1) $initials = strtoupper(substr($ag['agent_name'], 0, 2));
                    ?>
                    <tr class="agent-row">
                        <td style="text-align:center;">
                            <div class="rank-num-badge <?= $idx === 1 ? 'rank-badge-1' : '' ?>">
                                #<?= $idx ?>
                            </div>
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
                                <?= intval($ag['total_sourced_leads']) ?>
                            </span>
                        </td>
                        <td style="text-align:center;">
                            <span class="pill-passed">
                                <i data-lucide="check" style="width:11px; height:11px;"></i>
                                <?= intval($ag['approved_leads']) ?>
                            </span>
                        </td>
                        <td style="font-weight:800; color:#0f172a; white-space:nowrap;">
                            <?= formatINR_Leaderboard($ag['total_volume']) ?>
                        </td>
                        <td style="text-align:right; white-space:nowrap;">
                            <div style="display:inline-flex; gap:5px; align-items:center;">
                                <a href="referrals_list.php?search=<?= urlencode($ag['agent_name']) ?>" class="btn-action-outline" title="View Profile">
                                    <i data-lucide="user" style="width:11px; height:11px;"></i> Profile
                                </a>
                                <button type="button" onclick="cheerPerformer('<?= htmlspecialchars($ag['agent_name']) ?>')" class="btn-cheer" title="Cheer Partner">
                                    <i data-lucide="sparkles" style="width:11px; height:11px;"></i> Cheer
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php $idx++; endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php elseif ($active_type === 'field'): ?>
    <!-- ================================================================= -->
    <!-- CATEGORY 3: FIELD STAFF VISITS LEADERBOARD                        -->
    <!-- ================================================================= -->
    <?php
    $total_field_visits = array_sum(array_column($visit_rankings, 'total_visits'));
    $top_field_exec = $visit_rankings[0] ?? null;
    ?>

    <!-- FIELD KPIs -->
    <div class="metric-grid-four">
        <div class="v-metric-card">
            <div class="v-metric-icon">
                <i data-lucide="map-pin"></i>
            </div>
            <div>
                <div class="v-metric-lbl">Total Ground Field Visits</div>
                <div class="v-metric-val"><?= number_format($total_field_visits) ?> Visits</div>
            </div>
        </div>
        <div class="v-metric-card">
            <div class="v-metric-icon">
                <i data-lucide="trophy"></i>
            </div>
            <div>
                <div class="v-metric-lbl">#1 Field Executive</div>
                <div class="v-metric-val"><?= htmlspecialchars($top_field_exec['field_name'] ?? 'N/A') ?></div>
            </div>
        </div>
        <div class="v-metric-card">
            <div class="v-metric-icon">
                <i data-lucide="user-check"></i>
            </div>
            <div>
                <div class="v-metric-lbl">Active Field Officers</div>
                <div class="v-metric-val"><?= count($visit_rankings) ?> Officers</div>
            </div>
        </div>
    </div>

    <!-- FIELD TABLE -->
    <div class="table-card">
        <div class="table-header-row">
            <div class="table-title">
                <i data-lucide="map" style="width:16px; height:16px; color:#0f172a;"></i>
                <span>Field Staff Physical Verification Visits Table</span>
            </div>
            <div class="search-box-wrap">
                <i data-lucide="search"></i>
                <input type="text" id="field-search" oninput="filterTable('field-search','.field-row')" placeholder="Search executive name...">
            </div>
        </div>
        <div class="table-responsive">
            <table class="rm-rank-table" id="fieldTable">
                <thead>
                    <tr>
                        <th style="width:38px; text-align:center;">Rank</th>
                        <th>Field Executive Name</th>
                        <th style="text-align:center;">Visits</th>
                        <th style="text-align:center;">Clients Visited</th>
                        <th>Last Activity Date</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $idx = 1;
                    foreach ($visit_rankings as $v):
                        $words = preg_split("/[\s,@]+/", trim($v['field_name']));
                        $initials = strtoupper(substr($words[0] ?? 'F', 0, 1) . substr($words[1] ?? '', 0, 1));
                        if (strlen($initials) === 1) $initials = strtoupper(substr($v['field_name'], 0, 2));
                    ?>
                    <tr class="field-row">
                        <td style="text-align:center;">
                            <div class="rank-num-badge <?= $idx === 1 ? 'rank-badge-1' : '' ?>">
                                #<?= $idx ?>
                            </div>
                        </td>
                        <td>
                            <div class="person-cell">
                                <div class="person-avatar" style="background:#0f766e;"><?= htmlspecialchars($initials) ?></div>
                                <div>
                                    <div class="person-name">
                                        <?= htmlspecialchars($v['field_name']) ?>
                                        <span class="tag-role">Field Executive</span>
                                    </div>
                                    <div class="person-sub">Ground Fleet Verification</div>
                                </div>
                            </div>
                        </td>
                        <td style="text-align:center;">
                            <span class="pill-passed">
                                <i data-lucide="map-pin" style="width:11px; height:11px;"></i>
                                <?= intval($v['total_visits']) ?> Visits
                            </span>
                        </td>
                        <td style="text-align:center;">
                            <span class="pill-count">
                                <i data-lucide="user-check" style="width:11px; height:11px; color:#64748b;"></i>
                                <?= intval($v['clients_visited']) ?> Clients
                            </span>
                        </td>
                        <td style="font-size:12px; color:#64748b; white-space:nowrap;">
                            <i data-lucide="clock" style="width:12px; height:12px; vertical-align:middle; margin-right:3px;"></i>
                            <?= !empty($v['last_activity']) ? date('d M Y, h:i A', strtotime($v['last_activity'])) : '—' ?>
                        </td>
                        <td style="text-align:right; white-space:nowrap;">
                            <div style="display:inline-flex; gap:5px; align-items:center;">
                                <a href="field_visits.php" class="btn-action-outline" title="View Visits">
                                    <i data-lucide="map" style="width:11px; height:11px;"></i> Visits Log
                                </a>
                                <button type="button" onclick="cheerPerformer('<?= htmlspecialchars($v['field_name']) ?>')" class="btn-cheer" title="Cheer Executive">
                                    <i data-lucide="sparkles" style="width:11px; height:11px;"></i> Cheer
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php $idx++; endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Floating Interactive Toast Container -->
<div class="cheer-toast-container" id="cheerToastContainer"></div>

<script>
// Web Audio API Corporate Success Arpeggio Chime
function playCorporateChime() {
    try {
        const AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) return;
        const ctx = new AudioCtx();
        const now = ctx.currentTime;
        const freqs = [523.25, 659.25, 783.99, 1046.50];
        
        freqs.forEach((freq, idx) => {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(freq, now + idx * 0.07);
            
            gain.gain.setValueAtTime(0, now + idx * 0.07);
            gain.gain.linearRampToValueAtTime(0.1, now + idx * 0.07 + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.001, now + idx * 0.07 + 0.45);
            
            osc.connect(gain);
            gain.connect(ctx.destination);
            
            osc.start(now + idx * 0.07);
            osc.stop(now + idx * 0.07 + 0.5);
        });
    } catch(e) {}
}

// Corporate Refined Confetti Celebration (Gold, Slate, White)
function triggerGrandCelebration(targetName) {
    playCorporateChime();
    showCheerToast(`Celebrated ${targetName}! Keep driving exceptional results!`);

    if (typeof confetti === 'function') {
        confetti({ 
            particleCount: 60, 
            spread: 55, 
            origin: { x: 0.2, y: 0.6 },
            colors: ['#0f172a', '#b45309', '#f59e0b', '#cbd5e1']
        });
        confetti({ 
            particleCount: 60, 
            spread: 55, 
            origin: { x: 0.8, y: 0.6 },
            colors: ['#0f172a', '#b45309', '#f59e0b', '#cbd5e1']
        });
    }
}

// Cheer Specific Performer
function cheerPerformer(name) {
    playCorporateChime();
    showCheerToast(`Cheered for ${name}! Target velocity boosted!`);

    if (typeof confetti === 'function') {
        confetti({
            particleCount: 40,
            spread: 50,
            origin: { y: 0.7 },
            colors: ['#0f172a', '#b45309', '#f59e0b', '#ffffff']
        });
    }
}

// Floating Toast Notification
function showCheerToast(msg) {
    const container = document.getElementById('cheerToastContainer');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = 'cheer-toast';
    toast.innerHTML = `
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/></svg>
        <span>${msg}</span>
    `;
    container.appendChild(toast);

    setTimeout(() => {
        toast.style.transition = 'all 0.3s ease';
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(10px)';
        setTimeout(() => toast.remove(), 300);
    }, 3200);
}

// Instant Table Search Filter
function filterTable(inputId, rowClass) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const query = input.value.toLowerCase().trim();
    const rows = document.querySelectorAll(rowClass);
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(query) ? '' : 'none';
    });
}

document.addEventListener('DOMContentLoaded', function() {
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
