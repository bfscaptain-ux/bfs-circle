<?php
require_once 'config.php';

// Enforce strict Admin authentication & authorization
require_role(['Admin'], false);

$page_title = 'Executive Overview';
$page_subtitle = '';
$hide_main_header = true;
require_once 'header.php';
?>
<!-- Modern Chart.js UMD Engine -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<style>
/* Clean Dashboard Reset — Eliminate redundant padding and margins */
header.main-header, .main-header, .page-title { display: none !important; height: 0 !important; margin: 0 !important; padding: 0 !important; }
main {
    padding: 16px 22px !important;
    max-width: 100% !important;
    min-height: calc(100vh - 60px) !important;
    background: #f8fafc !important;
}

/* ====== BFS CIRCLE EXECUTIVE DASHBOARD STYLING ====== */
:root {
    --db-dark: #090d16;
    --db-dark-mid: #0f172a;
    --db-dark-card: #1e293b;
    --db-border: #e2e8f0;
    --db-bg: #f8fafc;
    --db-text: #0f172a;
    --db-muted: #64748b;
    --db-accent: #38bdf8;
    --db-emerald: #10b981;
    --db-amber: #f59e0b;
    --db-rose: #f43f5e;
}

.cmd-wrap {
    padding: 0 !important;
    margin: 0 auto;
    width: 100%;
    max-width: 100%;
    background: transparent !important;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}

/* TOP EXECUTIVE TOOLBAR */
.cmd-topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 22px;
    flex-wrap: wrap;
    gap: 16px;
    padding-bottom: 2px;
}
.cmd-title-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 4px;
}
.cmd-title {
    font-size: 21px;
    font-weight: 800;
    color: var(--db-text);
    margin: 0;
    letter-spacing: -0.025em;
    line-height: 1.2;
}
.cmd-badge {
    background: #f1f5f9;
    color: #475569;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 9px;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}
.cmd-subtitle {
    font-size: 13px;
    color: var(--db-muted);
    margin: 0;
    font-weight: 500;
}

.cmd-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

/* LIVE STATUS CAPSULE */
.live-status-pill {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 7px 12px;
    background: #ffffff;
    border: 1px solid var(--db-border);
    border-radius: 8px;
    font-size: 11.5px;
    font-weight: 600;
    color: #475569;
    box-shadow: 0 1px 2px rgba(0,0,0,0.02);
}
.live-dot {
    width: 7px;
    height: 7px;
    background: #10b981;
    border-radius: 50%;
    display: inline-block;
    box-shadow: 0 0 6px rgba(16, 185, 129, 0.6);
    animation: livePulse 2s infinite;
}
@keyframes livePulse {
    0% { transform: scale(0.9); opacity: 0.7; }
    50% { transform: scale(1.2); opacity: 1; }
    100% { transform: scale(0.9); opacity: 0.7; }
}
.live-time-sep {
    color: #cbd5e1;
    font-weight: 400;
}
.live-time {
    color: var(--db-text);
    font-weight: 700;
}

/* UNIFIED EXECUTIVE BUTTONS */
.cmd-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    height: 36px;
    padding: 0 14px;
    border-radius: 8px;
    font-size: 12.5px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.18s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
    border: 1px solid var(--db-border);
    background: #ffffff;
    color: var(--db-text);
    box-shadow: 0 1px 2px rgba(0,0,0,0.02);
}
.cmd-btn:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
    color: #0f172a;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.04);
}
.cmd-btn-dark {
    background: #0f172a;
    color: #ffffff;
    border-color: #0f172a;
    box-shadow: 0 1px 3px rgba(15,23,42,0.15);
}
.cmd-btn-dark:hover {
    background: #1e293b;
    border-color: #1e293b;
    color: #ffffff;
}
.cmd-btn-gold {
    background: #ffffff;
    color: #b45309;
    border-color: #fde68a;
}
.cmd-btn-gold:hover {
    background: #fffbeb;
    border-color: #f59e0b;
    color: #92400e;
}
.cmd-btn-icon {
    width: 36px;
    padding: 0;
    justify-content: center;
    color: #64748b;
}
.cmd-btn-icon:hover {
    color: #0f172a;
}

/* BLOCK 1: FINANCIAL & TARGET COMMAND BAR (DARK HERO) */
.cmd-hero {
    background: linear-gradient(135deg, #090d16 0%, #0f172a 65%, #1e293b 100%);
    border-radius: 18px;
    padding: 24px 28px;
    margin-bottom: 22px;
    color: #ffffff;
    border: 1px solid #1e293b;
    box-shadow: 0 12px 36px rgba(15, 23, 42, 0.2);
    position: relative;
    overflow: hidden;
}
.cmd-hero-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 24px;
    position: relative;
    z-index: 1;
}
@media (max-width: 1250px) {
    .cmd-hero-grid { grid-template-columns: repeat(2, 1fr); gap: 16px; }
    .cmd-hero { padding: 20px 22px; }
}
@media (max-width: 640px) {
    .cmd-hero-grid { grid-template-columns: 1fr; gap: 14px; }
    .cmd-hero { padding: 18px 16px; }
}

.hero-cell {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
.hero-tag {
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: #94a3b8;
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.hero-val {
    font-size: 28px;
    font-weight: 900;
    color: #ffffff;
    letter-spacing: -0.8px;
    line-height: 1.1;
    margin-bottom: 6px;
}
.hero-sub {
    font-size: 12px;
    color: #cbd5e1;
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}
.hero-badge-green {
    background: rgba(16, 185, 129, 0.18);
    color: #34d399;
    padding: 2px 8px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
}
.hero-badge-amber {
    background: rgba(245, 158, 11, 0.18);
    color: #fbbf24;
    padding: 2px 8px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
}

/* BLOCK 2: ACTION REQUIRED & RED ALERT CENTER */
.alert-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 22px;
}
@media (max-width: 1250px) {
    .alert-row { grid-template-columns: repeat(2, 1fr); gap: 14px; }
}
@media (max-width: 640px) {
    .alert-row { grid-template-columns: 1fr; gap: 10px; }
}

.alert-card {
    background: #ffffff;
    border: 1px solid var(--db-border);
    border-radius: 14px;
    padding: 16px 18px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.02);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 105px;
    position: relative;
    overflow: hidden;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
}
.alert-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08);
}
.alert-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
}
.alert-rose::before { background: var(--db-rose); }
.alert-amber::before { background: var(--db-amber); }
.alert-emerald::before { background: var(--db-emerald); }
.alert-sky::before { background: var(--db-accent); }

.alert-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 6px;
}
.alert-num {
    font-size: 24px;
    font-weight: 900;
    color: var(--db-text);
    line-height: 1;
}
.alert-icon-box {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
}
.alert-rose .alert-icon-box { background: #ffe4e6; color: #e11d48; }
.alert-amber .alert-icon-box { background: #fef3c7; color: #d97706; }
.alert-emerald .alert-icon-box { background: #dcfce7; color: #059669; }
.alert-sky .alert-icon-box { background: #e0f2fe; color: #0284c7; }

.alert-lbl {
    font-size: 12px;
    font-weight: 800;
    color: var(--db-text);
    margin-bottom: 4px;
}
.alert-action-link {
    font-size: 11px;
    font-weight: 700;
    color: var(--db-muted);
    display: flex;
    align-items: center;
    gap: 4px;
}
.alert-card:hover .alert-action-link {
    color: #0f172a;
}

/* BLOCK 3: 5-PHASE MASTER FUNNEL BAR */
.funnel-container {
    background: #ffffff;
    border: 1px solid var(--db-border);
    border-radius: 16px;
    padding: 20px 24px;
    margin-bottom: 22px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.02);
}
.funnel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid #f1f5f9;
    flex-wrap: wrap;
    gap: 10px;
}
.funnel-title {
    font-size: 14px;
    font-weight: 900;
    color: var(--db-text);
    display: flex;
    align-items: center;
    gap: 8px;
    text-transform: uppercase;
    letter-spacing: 0.6px;
}
.funnel-badge {
    background: #0f172a;
    color: #ffffff;
    font-size: 10px;
    padding: 3px 8px;
    border-radius: 6px;
    font-weight: 800;
}

.funnel-steps {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 12px;
    position: relative;
}
@media (max-width: 1280px) {
    .funnel-steps { grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; }
}
@media (max-width: 640px) {
    .funnel-steps { grid-template-columns: 1fr; gap: 8px; }
}

.funnel-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 14px 16px;
    cursor: pointer;
    transition: all 0.2s;
    position: relative;
}
.funnel-card:hover {
    background: #ffffff;
    border-color: #0f172a;
    box-shadow: 0 6px 16px rgba(15, 23, 42, 0.08);
    transform: translateY(-2px);
}
.funnel-step-tag {
    font-size: 10px;
    font-weight: 800;
    color: var(--db-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 6px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 4px;
}
.funnel-step-val {
    font-size: 24px;
    font-weight: 900;
    color: var(--db-text);
    line-height: 1;
    margin-bottom: 6px;
}
.funnel-step-sub {
    font-size: 11px;
    color: #475569;
    font-weight: 600;
    line-height: 1.3;
}
.funnel-card.active-phase {
    background: #0f172a;
    border-color: #0f172a;
    color: #ffffff;
}
.funnel-card.active-phase .funnel-step-tag { color: #94a3b8; }
.funnel-card.active-phase .funnel-step-val { color: #ffffff; }
.funnel-card.active-phase .funnel-step-sub { color: #cbd5e1; }

/* BLOCK 3.5: EXECUTIVE CHARTS & PERFORMANCE TRAJECTORY */
.charts-row {
    display: grid;
    grid-template-columns: 1.8fr 1.2fr;
    gap: 20px;
    margin-bottom: 22px;
}
@media (max-width: 1150px) {
    .charts-row { grid-template-columns: 1fr; gap: 16px; }
}

.chart-card {
    background: #ffffff;
    border: 1px solid var(--db-border);
    border-radius: 16px;
    padding: 20px 22px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.02);
    display: flex;
    flex-direction: column;
    min-height: 340px;
}
.chart-hdr {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid #f1f5f9;
}
.chart-title {
    font-size: 13.5px;
    font-weight: 900;
    color: var(--db-text);
    display: flex;
    align-items: center;
    gap: 8px;
    letter-spacing: -0.2px;
}
.chart-subtitle {
    font-size: 11.5px;
    color: var(--db-muted);
    margin-top: 2px;
    font-weight: 500;
}
.chart-pills {
    display: flex;
    gap: 5px;
    background: #f1f5f9;
    padding: 3px;
    border-radius: 8px;
}
.chart-pill {
    font-size: 11px;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 6px;
    cursor: pointer;
    color: var(--db-muted);
    transition: all 0.15s ease;
    user-select: none;
}
.chart-pill:hover {
    color: var(--db-text);
}
.chart-pill.active {
    background: #0f172a;
    color: #ffffff;
    box-shadow: 0 1px 3px rgba(15,23,42,0.15);
}
.chart-body-box {
    position: relative;
    flex: 1;
    min-height: 260px;
    height: 260px;
    width: 100%;
}
.chart-center-overlay {
    position: absolute;
    top: 42%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
    pointer-events: none;
}
.chart-center-val {
    font-size: 22px;
    font-weight: 900;
    color: var(--db-text);
    line-height: 1;
}
.chart-center-lbl {
    font-size: 10px;
    font-weight: 700;
    color: var(--db-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 3px;
}

/* BLOCK 4: THE THREE PERFORMANCE PILLARS (SIDE BY SIDE) */
.pillar-row {
    display: grid;
    grid-template-columns: 1.1fr 1fr 1.1fr;
    gap: 20px;
    margin-bottom: 22px;
}
@media (max-width: 1200px) {
    .pillar-row { grid-template-columns: 1fr; }
}

.p-card {
    background: #ffffff;
    border: 1px solid var(--db-border);
    border-radius: 16px;
    padding: 20px 22px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.02);
    display: flex;
    flex-direction: column;
}
.p-hdr {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid #f1f5f9;
}
.p-title {
    font-size: 13.5px;
    font-weight: 900;
    color: var(--db-text);
    display: flex;
    align-items: center;
    gap: 8px;
    letter-spacing: -0.2px;
}
.p-link {
    font-size: 11.5px;
    font-weight: 700;
    color: #0f172a;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 4px;
    background: #f1f5f9;
    padding: 4px 10px;
    border-radius: 6px;
    transition: all 0.2s;
}
.p-link:hover {
    background: #0f172a;
    color: #ffffff;
}

/* RM Leaderboard Table */
.rm-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #f8fafc;
}
.rm-item:last-child { border-bottom: none; }
.rm-left {
    display: flex;
    align-items: center;
    gap: 10px;
}
.rm-avatar {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: #0f172a;
    color: #ffffff;
    font-size: 12px;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
}
.rm-name {
    font-size: 12.5px;
    font-weight: 800;
    color: var(--db-text);
    line-height: 1.2;
}
.rm-meta {
    font-size: 11px;
    color: var(--db-muted);
}
.rm-right {
    text-align: right;
}
.rm-val {
    font-size: 13px;
    font-weight: 900;
    color: var(--db-text);
}
.rm-sub {
    font-size: 10.5px;
    color: #10b981;
    font-weight: 700;
}

/* Channel Bars */
.chan-row {
    margin-bottom: 12px;
}
.chan-hdr {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    font-weight: 700;
    color: var(--db-text);
    margin-bottom: 5px;
}
.chan-bar-bg {
    height: 8px;
    background: #f1f5f9;
    border-radius: 10px;
    overflow: hidden;
}
.chan-bar-fill {
    height: 100%;
    border-radius: 10px;
    background: #0f172a;
    transition: width 0.8s ease;
}

/* Bank Table */
.bank-row-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #f8fafc;
}
.bank-row-item:last-child { border-bottom: none; }
.bank-title-cell {
    font-size: 12.5px;
    font-weight: 800;
    color: var(--db-text);
    display: flex;
    align-items: center;
    gap: 8px;
}
.bank-badge-stat {
    font-size: 11px;
    font-weight: 700;
    color: var(--db-muted);
}
.bank-right-stat {
    text-align: right;
    font-size: 12.5px;
    font-weight: 800;
    color: var(--db-text);
}

/* BLOCK 5: LIVE AUDIT FEED FOOTER */
.feed-box {
    background: #ffffff;
    border: 1px solid var(--db-border);
    border-radius: 16px;
    padding: 18px 22px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.02);
}
.feed-hdr {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 14px;
    padding-bottom: 10px;
    border-bottom: 1px solid #f1f5f9;
}
.feed-stream {
    max-height: 190px;
    overflow-y: auto;
    padding-right: 6px;
}
.feed-item {
    display: flex;
    gap: 12px;
    padding: 8px 0;
    border-bottom: 1px solid #f8fafc;
    font-size: 12px;
}
.feed-item:last-child { border-bottom: none; }
.feed-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #0f172a;
    margin-top: 6px;
    flex-shrink: 0;
}
</style>

<div class="cmd-wrap">

    <!-- ==================== TOP EXECUTIVE TOOLBAR ==================== -->
    <div class="cmd-topbar">
        <div class="cmd-title-area">
            <div class="cmd-title-row">
                <h1 class="cmd-title">Executive Overview</h1>
                <span class="cmd-badge">HQ Admin</span>
            </div>
            <p class="cmd-subtitle">
                Bhardwaj Financial Services &bull; Multi-Bank Lending Portfolio &amp; Distribution Operations
            </p>
        </div>
        <div class="cmd-actions">
            <div class="live-status-pill">
                <span class="live-dot"></span>
                <span>Live Sync</span>
                <span class="live-time-sep">&bull;</span>
                <span id="cmdSyncTime" class="live-time">Just now</span>
            </div>
            <a href="rm/dashboard.php" class="cmd-btn cmd-btn-dark">
                <i data-lucide="users" style="width:14px; height:14px;"></i> RM Operations
            </a>
            <a href="search_track.php" class="cmd-btn">
                <i data-lucide="search" style="width:14px; height:14px;"></i> LOS Track
            </a>
            <a href="fund_management.php" class="cmd-btn cmd-btn-gold">
                <i class="fas fa-coins" style="font-size:12px;"></i> Fund Flow
            </a>
            <button onclick="loadExecutiveDashboard()" class="cmd-btn cmd-btn-icon" title="Refresh Live Data">
                <i data-lucide="refresh-cw" style="width:14px; height:14px;"></i>
            </button>
        </div>
    </div>

    <!-- ==================== BLOCK 1: FINANCIAL & REVENUE HERO ==================== -->
    <div class="cmd-hero">
        <div class="cmd-hero-grid">
            <!-- Metric 1: Total Disbursed Volume -->
            <div class="hero-cell" onclick="window.location.href='search_track.php?status=Completed'" style="cursor:pointer;">
                <div class="hero-tag">
                    <i data-lucide="check-circle" style="width:13px; height:13px;"></i> Disbursed Volume
                </div>
                <div class="hero-val" id="hero-disbursed-vol">₹—</div>
                <div class="hero-sub">
                    <span class="hero-badge-green" id="hero-mtd-vol">MTD: ₹—</span>
                    <span>Completed Mortgages</span>
                </div>
            </div>

            <!-- Metric 2: Active Pipeline Value -->
            <div class="hero-cell" onclick="window.location.href='search_track.php'" style="cursor:pointer;">
                <div class="hero-tag">
                    <i data-lucide="layers" style="width:13px; height:13px;"></i> Active Pipeline Value
                </div>
                <div class="hero-val" id="hero-pipeline-vol">₹—</div>
                <div class="hero-sub">
                    <span class="hero-badge-amber" id="hero-underwrite-files">— Files</span>
                    <span>In Underwriting & Login</span>
                </div>
            </div>

            <!-- Metric 3: Estimated DSA Commission -->
            <div class="hero-cell" onclick="window.location.href='payout_distribution.php'" style="cursor:pointer;">
                <div class="hero-tag">
                    <i data-lucide="receipt" style="width:13px; height:13px;"></i> DSA Gross Earnings
                </div>
                <div class="hero-val" id="hero-dsa-comm">₹—</div>
                <div class="hero-sub">
                    <span>Est. 1.5% Receivable from Banks</span>
                </div>
            </div>

            <!-- Metric 4: Partner Payouts & Net Profit -->
            <div class="hero-cell" onclick="window.location.href='payout_distribution.php'" style="cursor:pointer;">
                <div class="hero-tag">
                    <i data-lucide="wallet" style="width:13px; height:13px;"></i> Partner Payouts Released
                </div>
                <div class="hero-val" id="hero-payouts-paid">₹—</div>
                <div class="hero-sub">
                    <span class="hero-badge-green" id="hero-pending-vouchers">— Pending Vouchers</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== BLOCK 2: ACTION REQUIRED & RED ALERTS ==================== -->
    <div class="alert-row">
        <!-- Alert 1: Unassigned Fresh Leads -->
        <div class="alert-card alert-rose" onclick="window.location.href='leads.php'">
            <div class="alert-top">
                <div class="alert-num" id="alert-unassigned">—</div>
                <div class="alert-icon-box"><i data-lucide="user-x" style="width:15px; height:15px;"></i></div>
            </div>
            <div>
                <div class="alert-lbl">Unassigned Leads</div>
                <div class="alert-action-link">Open Leads Desk to Assign <i data-lucide="arrow-right" style="width:11px;"></i></div>
            </div>
        </div>

        <!-- Alert 2: Stalled Files in Bank -->
        <div class="alert-card alert-amber" onclick="window.location.href='search_track.php?filter=stalled'">
            <div class="alert-top">
                <div class="alert-num" id="alert-stalled">—</div>
                <div class="alert-icon-box"><i data-lucide="clock" style="width:15px; height:15px;"></i></div>
            </div>
            <div>
                <div class="alert-lbl">Stalled in Bank (>5 Days)</div>
                <div class="alert-action-link">Escalate to Bankers <i data-lucide="arrow-right" style="width:11px;"></i></div>
            </div>
        </div>

        <!-- Alert 3: Sanctions Ready for Disbursal -->
        <div class="alert-card alert-emerald" onclick="window.location.href='search_track.php?phase=4'">
            <div class="alert-top">
                <div class="alert-num" id="alert-sanctioned">—</div>
                <div class="alert-icon-box"><i data-lucide="award" style="width:15px; height:15px;"></i></div>
            </div>
            <div>
                <div class="alert-lbl">Sanctioned Offers Ready</div>
                <div class="alert-action-link">Execute Final Disbursal <i data-lucide="arrow-right" style="width:11px;"></i></div>
            </div>
        </div>

        <!-- Alert 4: Pending Payout Releases -->
        <div class="alert-card alert-sky" onclick="window.location.href='payout_distribution.php?status=Pending'">
            <div class="alert-top">
                <div class="alert-num" id="alert-payouts">—</div>
                <div class="alert-icon-box"><i data-lucide="coins" style="width:15px; height:15px;"></i></div>
            </div>
            <div>
                <div class="alert-lbl">Pending Partner Payouts</div>
                <div class="alert-action-link">Approve & Release Funds <i data-lucide="arrow-right" style="width:11px;"></i></div>
            </div>
        </div>
    </div>

    <!-- ==================== BLOCK 3: 5-PHASE MASTER FUNNEL ==================== -->
    <div class="funnel-container">
        <div class="funnel-header">
            <div class="funnel-title">
                <i data-lucide="git-commit" style="width:16px; height:16px; color:#0f172a;"></i>
                5-Phase Master Loan Lifecycle Funnel
            </div>
            <span class="funnel-badge">Interactive Workflow</span>
        </div>
        <div class="funnel-steps">
            <!-- Stage 1: Enquiries -->
            <div class="funnel-card" onclick="window.location.href='pre_leads.php'">
                <div class="funnel-step-tag"><span>Stage 1</span> <span>Enquiries</span></div>
                <div class="funnel-step-val" id="fn-enquiries">—</div>
                <div class="funnel-step-sub" id="fn-enquiries-sub">Raw Inbound Pre-Leads</div>
            </div>

            <!-- Stage 2: Leads Pipeline -->
            <div class="funnel-card" onclick="window.location.href='leads.php'">
                <div class="funnel-step-tag"><span>Stage 2</span> <span>Sales Leads</span></div>
                <div class="funnel-step-val" id="fn-leads">—</div>
                <div class="funnel-step-sub" id="fn-leads-sub">Qualified Hot Prospects</div>
            </div>

            <!-- Stage 3: Bank Underwriting -->
            <div class="funnel-card active-phase" onclick="window.location.href='search_track.php'">
                <div class="funnel-step-tag"><span>Stage 3</span> <span>Bank Login</span></div>
                <div class="funnel-step-val" id="fn-underwriting">—</div>
                <div class="funnel-step-sub" id="fn-underwriting-sub">In Verification & Login</div>
            </div>

            <!-- Stage 4: Sanctioned -->
            <div class="funnel-card" onclick="window.location.href='search_track.php?phase=4'">
                <div class="funnel-step-tag"><span>Stage 4</span> <span>Sanction Offers</span></div>
                <div class="funnel-step-val" id="fn-sanctions">—</div>
                <div class="funnel-step-sub" id="fn-sanctions-sub">Term Sheets Issued</div>
            </div>

            <!-- Stage 5: Disbursed & Completed -->
            <div class="funnel-card" onclick="window.location.href='search_track.php?status=Completed'">
                <div class="funnel-step-tag"><span>Stage 5</span> <span>Disbursed</span></div>
                <div class="funnel-step-val" id="fn-disbursed">—</div>
                <div class="funnel-step-sub" id="fn-disbursed-sub">Closed & Vault Stored</div>
            </div>
        </div>
    </div>

    <!-- ==================== BLOCK 3.5: EXECUTIVE CHARTS & PERFORMANCE TRAJECTORY ==================== -->
    <div class="charts-row">
        <!-- Chart 1: 6-Month Disbursal & Pipeline Trajectory -->
        <div class="chart-card">
            <div class="chart-hdr">
                <div>
                    <div class="chart-title">
                        <i data-lucide="trending-up" style="width:16px; height:16px; color:#0f172a;"></i>
                        Disbursal &amp; Pipeline Trajectory
                    </div>
                    <div class="chart-subtitle">6-Month Loan Volume &amp; Velocity Growth</div>
                </div>
                <div class="chart-pills">
                    <span class="chart-pill active" id="btnChartVol" onclick="switchChartMode('volume')">Volume (₹)</span>
                    <span class="chart-pill" id="btnChartFiles" onclick="switchChartMode('files')">File Count</span>
                </div>
            </div>
            <div class="chart-body-box">
                <canvas id="disbursalTrendChart"></canvas>
            </div>
        </div>

        <!-- Chart 2: Loan Product Portfolio Mix -->
        <div class="chart-card">
            <div class="chart-hdr">
                <div>
                    <div class="chart-title">
                        <i data-lucide="pie-chart" style="width:16px; height:16px; color:#0f172a;"></i>
                        Loan Product Distribution
                    </div>
                    <div class="chart-subtitle">Active Portfolio Share by Asset Class</div>
                </div>
                <a href="search_track.php" class="p-link">View Portfolio <i data-lucide="arrow-right" style="width:11px;"></i></a>
            </div>
            <div class="chart-body-box">
                <canvas id="loanMixChart"></canvas>
                <div class="chart-center-overlay" id="loanMixOverlay">
                    <div class="chart-center-val" id="loanMixTotalCount">0</div>
                    <div class="chart-center-lbl">Loans</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== BLOCK 4: THREE PERFORMANCE PILLARS ==================== -->
    <div class="pillar-row">
        <!-- Pillar A: RM Workload & Disbursal Leaderboard -->
        <div class="p-card">
            <div class="p-hdr">
                <div class="p-title">
                    <i data-lucide="award" style="width:16px; height:16px; color:#0f172a;"></i>
                    RM Workload & Leaderboard
                </div>
                <a href="rm_leaderboard.php" class="p-link">Full Board <i data-lucide="arrow-right" style="width:11px;"></i></a>
            </div>
            <div id="rmLeaderboardList" style="flex:1;">
                <div style="text-align:center; padding:24px; color:var(--db-muted); font-size:12px;">Loading RM roster...</div>
            </div>
        </div>

        <!-- Pillar B: Sourcing Channels Health (4 Partner Types) -->
        <div class="p-card">
            <div class="p-hdr">
                <div class="p-title">
                    <i data-lucide="pie-chart" style="width:16px; height:16px; color:#0f172a;"></i>
                    Sourcing Channels (Partners)
                </div>
                <a href="referrals_list.php" class="p-link">Partners <i data-lucide="arrow-right" style="width:11px;"></i></a>
            </div>
            <div id="channelBarsList" style="flex:1;">
                <div style="text-align:center; padding:24px; color:var(--db-muted); font-size:12px;">Loading partner mix...</div>
            </div>
        </div>

        <!-- Pillar C: Top Lender / Bank Network Radar -->
        <div class="p-card">
            <div class="p-hdr">
                <div class="p-title">
                    <i data-lucide="building-2" style="width:16px; height:16px; color:#0f172a;"></i>
                    Bank Performance Radar
                </div>
                <a href="bankers_list.php" class="p-link">Directory <i data-lucide="arrow-right" style="width:11px;"></i></a>
            </div>
            <div id="bankRadarList" style="flex:1;">
                <div style="text-align:center; padding:24px; color:var(--db-muted); font-size:12px;">Loading bank metrics...</div>
            </div>
        </div>
    </div>

    <!-- ==================== BLOCK 5: LIVE AUDIT FEED ==================== -->
    <div class="feed-box">
        <div class="feed-hdr">
            <div class="p-title">
                <i data-lucide="activity" style="width:16px; height:16px; color:#0f172a;"></i>
                Live Audit & Activity Stream
            </div>
            <a href="activity_log.php" class="p-link">Full Activity Log <i data-lucide="arrow-right" style="width:11px;"></i></a>
        </div>
        <div class="feed-stream" id="liveActivityFeed">
            <div style="text-align:center; padding:16px; color:var(--db-muted); font-size:12px;">Loading live stream...</div>
        </div>
    </div>

</div><!-- /cmd-wrap -->

<script>
// Format Indian Rupee Currency helper
function fmtINR(n) {
    if (!n || isNaN(n)) return '₹0';
    if (n >= 10000000) return '₹' + (n / 10000000).toFixed(2) + ' Cr';
    if (n >= 100000)   return '₹' + (n / 100000).toFixed(2) + ' L';
    if (n >= 1000)     return '₹' + (n / 1000).toFixed(1) + ' K';
    return '₹' + Math.round(n).toLocaleString('en-IN');
}

// Global Chart State
let trendChartInstance = null;
let mixChartInstance = null;
let chartMode = 'volume';
let globalAnalyticsData = null;

async function loadExecutiveDashboard() {
    try {
        const res = await fetch('api.php?api=dashboard_analytics');
        if (!res.ok) {
            if (res.status === 401) {
                window.location.href = 'login.php';
                return;
            }
        }
        const data = await res.json();
        if (data && data.error) {
            if (data.error === 'SESSION_EXPIRED' || data.error === 'Unauthorized') {
                window.location.href = 'login.php';
            }
            console.error('API error:', data.error);
            return;
        }

        globalAnalyticsData = data;

        // 1. Refresh Timestamp
        const now = new Date();
        document.getElementById('cmdSyncTime').innerText = now.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit', second: '2-digit' });

        // 2. BLOCK 1: Financial & Target Hero
        const exec = data.executive || {};
        document.getElementById('hero-disbursed-vol').innerText = fmtINR(exec.disbursed_total || 0);
        document.getElementById('hero-mtd-vol').innerText = 'MTD: ' + fmtINR(exec.disbursed_this_month || 0);
        
        document.getElementById('hero-pipeline-vol').innerText = fmtINR(exec.pipeline_volume || 0);
        const activeFilesCount = (data.funnel?.phase1 || 0) + (data.funnel?.phase2 || 0) + (data.funnel?.phase3 || 0);
        document.getElementById('hero-underwrite-files').innerText = activeFilesCount + ' Deals';

        document.getElementById('hero-dsa-comm').innerText = fmtINR(exec.dsa_commission_est || 0);
        document.getElementById('hero-payouts-paid').innerText = fmtINR(exec.payouts_released || 0);
        
        const pendingPayoutsCnt = data.alerts?.pending_payouts || 0;
        document.getElementById('hero-pending-vouchers').innerText = pendingPayoutsCnt + ' Pending Approval';

        // 3. BLOCK 2: Action Required / Alerts
        const alerts = data.alerts || {};
        document.getElementById('alert-unassigned').innerText = alerts.unassigned_leads || 0;
        document.getElementById('alert-stalled').innerText    = alerts.stalled_files || 0;
        document.getElementById('alert-sanctioned').innerText = alerts.sanctioned_ready || 0;
        document.getElementById('alert-payouts').innerText    = alerts.pending_payouts || 0;

        // 4. BLOCK 3: 5-Phase Master Funnel
        const fn = data.funnel || {};
        document.getElementById('fn-enquiries').innerText = fn.raw_enquiries || 0;
        document.getElementById('fn-enquiries-sub').innerText = (data.pre_leads?.new || 0) + ' Uncontacted';

        document.getElementById('fn-leads').innerText = fn.active_leads || 0;
        document.getElementById('fn-leads-sub').innerText = (data.leads?.hot || 0) + ' Hot Deals';

        document.getElementById('fn-underwriting').innerText = fn.active_underwriting || 0;
        document.getElementById('fn-underwriting-sub').innerText = (fn.phase3 || 0) + ' in Bank Underwrite';

        document.getElementById('fn-sanctions').innerText = fn.phase4 || 0;
        document.getElementById('fn-sanctions-sub').innerText = 'Sanction Term Sheets';

        document.getElementById('fn-disbursed').innerText = fn.phase5 || 0;
        document.getElementById('fn-disbursed-sub').innerText = fmtINR(exec.disbursed_total || 0) + ' Disbursed';

        // 5. BLOCK 3.5: Executive Charts & Performance Trajectory
        renderDisbursalTrendChart(data.applications?.monthly_growth || []);
        renderLoanMixChart(data.applications?.loan_types || []);

        // 6. BLOCK 4: Three Performance Pillars
        
        // Pillar A: RM Workload
        const rmList = data.rm_workload || [];
        if (rmList.length === 0) {
            document.getElementById('rmLeaderboardList').innerHTML = '<div style="text-align:center; padding:16px; color:var(--db-muted); font-size:12px;">No active RMs registered.</div>';
        } else {
            document.getElementById('rmLeaderboardList').innerHTML = rmList.map(rm => {
                const initial = (rm.name || rm.username || 'R').charAt(0).toUpperCase();
                const displayName = rm.name || rm.username;
                return `
                    <div class="rm-item" onclick="window.location.href='leads.php?assigned_to=${encodeURIComponent(rm.username)}'" style="cursor:pointer;">
                        <div class="rm-left">
                            <div class="rm-avatar">${initial}</div>
                            <div>
                                <div class="rm-name">${escapeHtml(displayName)}</div>
                                <div class="rm-meta">${rm.active_files || 0} active files • ${rm.active_leads || 0} leads</div>
                            </div>
                        </div>
                        <div class="rm-right">
                            <div class="rm-val">${fmtINR(rm.disbursed_vol || 0)}</div>
                            <div class="rm-sub">Disbursed</div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        // Pillar B: Channel Sourcing Breakdown
        const ch = data.channel_mix || {};
        const chTotal = ((ch.partner_leads || 0) + (ch.builder_leads || 0) + (ch.ca_leads || 0) + (ch.agent_leads || 0) + (ch.direct_leads || 0)) || 1;
        const channels = [
            { name: 'Financial Advisors / DSA', cnt: ch.partner_leads || 0, link: 'leads.php?source=Partner+Referral' },
            { name: 'Builders & Real Estate', cnt: ch.builder_leads || 0, link: 'leads.php?source=Partner+Referral' },
            { name: 'Chartered Accountants (CA)', cnt: ch.ca_leads || 0, link: 'leads.php?source=CA+Referral' },
            { name: 'Individual Agents', cnt: ch.agent_leads || 0, link: 'leads.php?source=Agent+Referral' },
            { name: 'Direct / Website Leads', cnt: ch.direct_leads || 0, link: 'leads.php' }
        ];

        document.getElementById('channelBarsList').innerHTML = channels.map(c => {
            const pct = Math.round((c.cnt / chTotal) * 100);
            return `
                <div class="chan-row" onclick="window.location.href='${c.link}'" style="cursor:pointer;">
                    <div class="chan-hdr">
                        <span>${c.name}</span>
                        <span>${c.cnt} Leads (${pct}%)</span>
                    </div>
                    <div class="chan-bar-bg">
                        <div class="chan-bar-fill" style="width: ${pct}%;"></div>
                    </div>
                </div>
            `;
        }).join('');

        // Pillar C: Bank Radar
        const bankList = data.top_banks || [];
        if (bankList.length === 0) {
            document.getElementById('bankRadarList').innerHTML = '<div style="text-align:center; padding:16px; color:var(--db-muted); font-size:12px;">No bank submissions logged yet.</div>';
        } else {
            document.getElementById('bankRadarList').innerHTML = bankList.map(b => {
                return `
                    <div class="bank-row-item" onclick="window.location.href='search_track.php?bank=${encodeURIComponent(b.bank_name)}'" style="cursor:pointer;">
                        <div>
                            <div class="bank-title-cell">
                                <i data-lucide="building" style="width:13px; height:13px; color:#64748b;"></i>
                                ${escapeHtml(b.bank_name)}
                            </div>
                            <div class="bank-badge-stat">${b.total_logins || 0} Total Logins • ${b.approvals || 0} Approved</div>
                        </div>
                        <div class="bank-right-stat">
                            ${fmtINR(b.total_sanctioned || 0)}
                            <div style="font-size:10px; color:#10b981; font-weight:700;">Sanctioned</div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        // 6. BLOCK 5: Activity Log Feed
        loadLiveActivityFeed();

        // Re-initialize Lucide Icons if available
        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }

    } catch (err) {
        console.error('Failed to load Executive Dashboard analytics:', err);
    }
}

// Switch between Volume (₹) and File Count for Trend Chart
function switchChartMode(mode) {
    chartMode = mode;
    const btnVol = document.getElementById('btnChartVol');
    const btnFiles = document.getElementById('btnChartFiles');
    if (btnVol && btnFiles) {
        btnVol.classList.toggle('active', mode === 'volume');
        btnFiles.classList.toggle('active', mode === 'files');
    }
    if (globalAnalyticsData && globalAnalyticsData.applications) {
        renderDisbursalTrendChart(globalAnalyticsData.applications.monthly_growth || []);
    }
}

// 1. Render 6-Month Disbursal & Pipeline Trajectory Chart
function renderDisbursalTrendChart(monthlyGrowth) {
    const canvas = document.getElementById('disbursalTrendChart');
    if (!canvas || !window.Chart) return;
    const ctx = canvas.getContext('2d');

    const labels = monthlyGrowth.map(m => m.month || '');

    // Gradient fills
    const gradDisbursed = ctx.createLinearGradient(0, 0, 0, 240);
    gradDisbursed.addColorStop(0, 'rgba(16, 185, 129, 0.22)');
    gradDisbursed.addColorStop(1, 'rgba(16, 185, 129, 0.00)');

    const gradPipeline = ctx.createLinearGradient(0, 0, 0, 240);
    gradPipeline.addColorStop(0, 'rgba(2, 132, 199, 0.16)');
    gradPipeline.addColorStop(1, 'rgba(2, 132, 199, 0.00)');

    let datasets = [];

    if (chartMode === 'volume') {
        const disbData = monthlyGrowth.map(m => m.disbursed_vol || 0);
        const pipeData = monthlyGrowth.map(m => m.pipeline_vol || 0);

        datasets = [
            {
                label: 'Disbursed Volume (₹)',
                data: disbData,
                borderColor: '#10b981',
                backgroundColor: gradDisbursed,
                borderWidth: 2.5,
                fill: true,
                tension: 0.35,
                pointBackgroundColor: '#10b981',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6
            },
            {
                label: 'Pipeline Logins (₹)',
                data: pipeData,
                borderColor: '#0284c7',
                backgroundColor: gradPipeline,
                borderWidth: 2,
                borderDash: [5, 5],
                fill: true,
                tension: 0.35,
                pointBackgroundColor: '#0284c7',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6
            }
        ];
    } else {
        const countData = monthlyGrowth.map(m => m.count || 0);
        const gradFiles = ctx.createLinearGradient(0, 0, 0, 240);
        gradFiles.addColorStop(0, 'rgba(15, 23, 42, 0.18)');
        gradFiles.addColorStop(1, 'rgba(15, 23, 42, 0.01)');

        datasets = [
            {
                label: 'Files Processed',
                data: countData,
                borderColor: '#0f172a',
                backgroundColor: gradFiles,
                borderWidth: 2.5,
                fill: true,
                tension: 0.3,
                pointBackgroundColor: '#0f172a',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6
            }
        ];
    }

    if (trendChartInstance) {
        trendChartInstance.destroy();
    }

    trendChartInstance = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: datasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                legend: {
                    position: 'top',
                    align: 'end',
                    labels: {
                        boxWidth: 9,
                        boxHeight: 9,
                        usePointStyle: true,
                        font: { size: 11, weight: '600' },
                        color: '#475569',
                        padding: 12
                    }
                },
                tooltip: {
                    backgroundColor: '#0f172a',
                    titleFont: { size: 12, weight: '700' },
                    bodyFont: { size: 12 },
                    padding: 12,
                    cornerRadius: 8,
                    callbacks: {
                        label: function(context) {
                            const val = context.parsed.y;
                            if (chartMode === 'volume') {
                                return ' ' + context.dataset.label + ': ' + fmtINR(val);
                            }
                            return ' ' + context.dataset.label + ': ' + val + ' Deals';
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 11, weight: '600' }, color: '#64748b' }
                },
                y: {
                    grid: { color: '#f1f5f9' },
                    ticks: {
                        font: { size: 11 },
                        color: '#64748b',
                        callback: function(val) {
                            if (chartMode === 'volume') {
                                return fmtINR(val);
                            }
                            return val;
                        }
                    }
                }
            }
        }
    });
}

// 2. Render Loan Product Distribution Doughnut Chart
function renderLoanMixChart(loanTypes) {
    const canvas = document.getElementById('loanMixChart');
    if (!canvas || !window.Chart) return;
    const ctx = canvas.getContext('2d');

    const validTypes = Array.isArray(loanTypes) && loanTypes.length > 0 ? loanTypes : [
        { loan_type: 'Home Loan', cnt: 1 }
    ];

    const labels = validTypes.map(t => t.loan_type || 'Unspecified');
    const counts = validTypes.map(t => parseInt(t.cnt) || 0);
    const totalCount = counts.reduce((a, b) => a + b, 0);

    const totalEl = document.getElementById('loanMixTotalCount');
    if (totalEl) totalEl.innerText = totalCount;

    const brandColors = [
        '#0f172a', // Slate Navy
        '#0284c7', // Sky Blue
        '#10b981', // Emerald
        '#f59e0b', // Amber
        '#8b5cf6', // Violet
        '#ec4899', // Pink
        '#64748b'  // Slate Muted
    ];

    if (mixChartInstance) {
        mixChartInstance.destroy();
    }

    mixChartInstance = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: counts,
                backgroundColor: brandColors.slice(0, labels.length),
                borderWidth: 2,
                borderColor: '#ffffff',
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '72%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 8,
                        boxHeight: 8,
                        usePointStyle: true,
                        font: { size: 11, weight: '600' },
                        color: '#334155',
                        padding: 12
                    }
                },
                tooltip: {
                    backgroundColor: '#0f172a',
                    titleFont: { size: 12, weight: '700' },
                    bodyFont: { size: 12 },
                    padding: 10,
                    cornerRadius: 8,
                    callbacks: {
                        label: function(context) {
                            const val = context.raw || 0;
                            const pct = totalCount > 0 ? Math.round((val / totalCount) * 100) : 0;
                            return ` ${context.label}: ${val} Loans (${pct}%)`;
                        }
                    }
                }
            }
        }
    });
}

async function loadLiveActivityFeed() {
    try {
        const res = await fetch('api.php?api=get_activity_logs&limit=6');
        const data = await res.json();
        const feed = data.logs || (Array.isArray(data) ? data : []);
        const feedEl = document.getElementById('liveActivityFeed');
        if (!feed || feed.length === 0) {
            feedEl.innerHTML = '<div style="text-align:center; padding:16px; color:var(--db-muted); font-size:12px;">No recent activity logged.</div>';
            return;
        }
        feedEl.innerHTML = feed.map(item => `
            <div class="feed-item">
                <div class="feed-dot"></div>
                <div style="flex:1;">
                    <strong style="color:#0f172a;">${escapeHtml(item.username || 'System')}</strong>: ${escapeHtml(item.description || item.action || 'System event')}
                    <div style="font-size:10.5px; color:#94a3b8; margin-top:2px;">${escapeHtml(item.created_at || 'Just now')}</div>
                </div>
            </div>
        `).join('');
    } catch(e) {}
}

function escapeHtml(text) {
    if (!text) return '';
    return String(text)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

// Auto-run on page load
document.addEventListener('DOMContentLoaded', () => {
    loadExecutiveDashboard();
    // Auto-refresh every 60 seconds
    setInterval(loadExecutiveDashboard, 60000);
});
</script>

<?php require_once 'footer.php'; ?>
