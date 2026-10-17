<?php
require_once __DIR__ . '/config.php';
require_role(['Admin', 'RM']);


$page_title = "Reminders & Tasks";
$page_subtitle = "";
$hide_main_header = true;
require_once __DIR__ . '/header.php';

$staff_list = $db->query("SELECT username FROM users WHERE is_active=1 ORDER BY username")->fetchAll(PDO::FETCH_COLUMN);
$current_user = $_SESSION['username'] ?? '';
$is_admin = ($_SESSION['role'] ?? '') === 'Admin';
?>

<style>
/* ===== REMINDERS HUB STYLES ===== */
.rem-hub { display: flex; flex-direction: column; gap: 24px; }

/* KPI CARDS */
.rem-kpi-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; }
.rem-kpi { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; padding: 16px 20px; display: flex; align-items: center; gap: 14px; transition: all 0.2s; cursor: pointer; }
.rem-kpi:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.07); }
.rem-kpi-icon { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.rem-kpi-count { font-size: 26px; font-weight: 800; font-family: 'Outfit', sans-serif; letter-spacing: -1px; line-height: 1; }
.rem-kpi-label { font-size: 12px; color: var(--text-muted); font-weight: 500; margin-top: 2px; }
.kpi-overdue  .rem-kpi-icon { background: #f8fafc; color: var(--text-primary); border: 1px solid var(--border); }
.kpi-overdue  .rem-kpi-count { color: var(--text-primary); }
.kpi-today    .rem-kpi-icon { background: #f8fafc; color: var(--text-primary); border: 1px solid var(--border); }
.kpi-today    .rem-kpi-count { color: var(--text-primary); }
.kpi-upcoming .rem-kpi-icon { background: #f8fafc; color: var(--text-primary); border: 1px solid var(--border); }
.kpi-upcoming .rem-kpi-count { color: var(--text-primary); }
.kpi-done     .rem-kpi-icon { background: #f8fafc; color: var(--text-primary); border: 1px solid var(--border); }
.kpi-done     .rem-kpi-count { color: var(--text-primary); }

/* FILTER BAR */
.rem-filters { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; padding: 12px 16px; display: flex; flex-wrap: nowrap; gap: 10px; align-items: center; overflow-x: auto; }
.search-wrapper { flex: 1; min-width: 220px; display: flex; align-items: center; gap: 10px; background: var(--bg-main); border: 1px solid var(--border); border-radius: 8px; padding: 0 14px; transition: all 0.2s; box-shadow: inset 0 1px 2px rgba(0,0,0,0.02); }
.search-wrapper:focus-within { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(0,0,0,0.04); }
.search-wrapper input { flex: 1; border: none !important; background: transparent !important; padding: 10px 0 !important; font-size: 14px; color: var(--text-primary); outline: none !important; box-shadow: none !important; min-width: 100px; margin: 0 !important; }
.search-wrapper i { color: var(--text-muted); }
.rem-filters select { width: auto; flex-shrink: 0; padding: 8px 30px 8px 12px; border: 1px solid var(--border); border-radius: 8px; font-size: 13px; font-weight: 500; background: var(--bg-main); color: var(--text-primary); outline: none; transition: all 0.2s; cursor: pointer; appearance: auto; }
.rem-filters select:hover { border-color: #cbd5e1; }
.rem-filters select:focus { border-color: var(--primary); }
.rem-filters .rem-btn { flex-shrink: 0; padding: 8px 16px; border-radius: 8px; font-size: 13.5px; font-weight: 600; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 8px; margin-left: auto; }
.rem-filters .rem-btn:hover { opacity: 0.9; transform: translateY(-1px); }

/* COLUMNS */
.rem-columns { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; align-items: start; }
.rem-col-header { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 700; padding: 10px 14px; border-radius: 8px; margin-bottom: 10px; }
.rem-col-header .rem-col-count { margin-left: auto; background: rgba(0,0,0,0.08); border-radius: 20px; padding: 2px 8px; font-size: 11px; }
.col-overdue  .rem-col-header { background: #f8fafc; color: var(--text-primary); border: 1px solid var(--border); }
.col-today    .rem-col-header { background: #f8fafc; color: var(--text-primary); border: 1px solid var(--border); }
.col-upcoming .rem-col-header { background: #f8fafc; color: var(--text-primary); border: 1px solid var(--border); }
.rem-col-body { display: flex; flex-direction: column; gap: 10px; min-height: 80px; }

/* REMINDER CARD */
.rem-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 14px 16px;
    position: relative;
    overflow: hidden;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    animation: slideUpFade 0.35s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
.rem-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,0.08); border-color: #cbd5e1; }
.rem-card::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; border-radius: 12px 0 0 12px; }
.rem-card.pri-High::before   { background: var(--text-primary); }
.rem-card.pri-Medium::before { background: #64748b; }
.rem-card.pri-Low::before    { background: #cbd5e1; }

.rem-card-top { display: flex; align-items: flex-start; gap: 8px; margin-bottom: 8px; }
.rem-card-title { font-size: 14px; font-weight: 700; color: var(--text-primary); flex: 1; line-height: 1.3; }
.rem-card-pri { font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 20px; flex-shrink: 0; text-transform: uppercase; letter-spacing: 0.3px; }
.pri-badge-High   { background: var(--text-primary); color: white; border: 1px solid var(--text-primary); }
.pri-badge-Medium { background: #f8fafc; color: var(--text-primary); border: 1px solid #94a3b8; }
.pri-badge-Low    { background: #f8fafc; color: #64748b; border: 1px solid var(--border); }

.rem-entity-badge { display: inline-flex; align-items: center; gap: 5px; font-size: 11.5px; font-weight: 600; padding: 3px 9px; border-radius: 6px; margin-bottom: 6px; text-decoration: none; transition: opacity 0.2s; }
.rem-entity-badge:hover { opacity: 0.8; }
.entity-Lead     { background: #f8fafc; color: var(--text-primary); border: 1px solid var(--border); }
.entity-Banker   { background: #f8fafc; color: var(--text-primary); border: 1px solid var(--border); }
.entity-Referral { background: #f8fafc; color: var(--text-primary); border: 1px solid var(--border); }
.entity-Pre-Lead { background: #f8fafc; color: var(--text-primary); border: 1px solid var(--border); }
.entity-General  { background: #f8fafc; color: #64748b; border: 1px solid var(--border); }
  .entity-Staff { background: #f8fafc; color: var(--text-primary); border: 1px solid var(--border); }

.rem-card-meta { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; font-size: 11.5px; color: var(--text-muted); margin-bottom: 10px; }
.rem-card-meta span { display: flex; align-items: center; gap: 4px; }
.rem-cat-chip { background: var(--bg-main); border: 1px solid var(--border); border-radius: 6px; padding: 2px 7px; font-size: 11px; font-weight: 600; color: var(--text-primary); }
.rem-time { font-weight: 600; }
.rem-time.overdue { color: var(--text-primary); font-weight: 800; }
.rem-time.today   { color: var(--text-primary); font-weight: 700; }
.rem-time.ok      { color: #64748b; }

.rem-notes { font-size: 12.5px; color: var(--text-muted); background: var(--bg-main); border-radius: 6px; padding: 7px 10px; margin-bottom: 10px; border-left: 3px solid var(--border); font-style: italic; }

.rem-actions { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
.rem-btn { display: inline-flex; align-items: center; gap: 5px; padding: 5px 11px; border-radius: 7px; font-size: 12px; font-weight: 600; border: none; cursor: pointer; transition: all 0.18s; }
.rem-btn-done    { background: #f8fafc; color: var(--text-primary); border: 1px solid var(--border); }
.rem-btn-done:hover    { background: #e2e8f0; }
.rem-btn-snooze  { background: #f8fafc; color: var(--text-primary); border: 1px solid var(--border); position: relative; }
.rem-btn-snooze:hover  { background: #e2e8f0; }
.rem-btn-edit    { background: var(--bg-main); color: var(--text-primary); border: 1px solid var(--border); }
.rem-btn-edit:hover    { background: var(--border); }
.rem-assigned    { margin-left: auto; font-size: 11px; color: var(--text-light); display: flex; align-items: center; gap: 3px; }

/* SNOOZE DROPDOWN */
.snooze-menu { position: absolute; bottom: 40px; left: 0; background: white; border: 1px solid var(--border); border-radius: 8px; box-shadow: 0 8px 24px rgba(0,0,0,0.12); z-index: 100; min-width: 160px; display: none; }
.snooze-menu.open { display: block; }
.snooze-opt { padding: 9px 14px; font-size: 13px; cursor: pointer; display: flex; align-items: center; gap: 8px; color: var(--text-primary); transition: background 0.15s; }
.snooze-opt:hover { background: var(--bg-main); }
.snooze-opt i { width: 14px; height: 14px; }

/* EMPTY STATE */
.rem-empty { text-align: center; padding: 30px 20px; color: var(--text-light); }
.rem-empty i { width: 36px; height: 36px; margin-bottom: 8px; }

/* ANIMATIONS */
@keyframes slideUpFade {
    from { opacity: 0; transform: translateY(12px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* ADD REMINDER MODAL */
.modal-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px); z-index: 9998; display: none; align-items: center; justify-content: center; padding: 20px; }
.modal-overlay.active { display: flex; }
.modal-box { background: white; border-radius: 18px; width: 100%; max-width: 560px; box-shadow: 0 25px 65px rgba(15, 23, 42, 0.28); border: 1px solid #e2e8f0; overflow: hidden; animation: slideUpFade 0.28s cubic-bezier(0.16,1,0.3,1); }
.modal-header { padding: 18px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #0f172a; color: white; }
.modal-header h3 { margin: 0; font-size: 15px; font-weight: 800; letter-spacing: -0.2px; display: flex; align-items: center; gap: 8px; }
.modal-close { background: none; border: none; color: #94a3b8; cursor: pointer; font-size: 20px; line-height: 1; padding: 4px; border-radius: 6px; transition: all 0.15s; }
.modal-close:hover { color: #ffffff; background: rgba(255,255,255,0.1); }
.modal-body { padding: 24px; display: flex; flex-direction: column; gap: 15px; max-height: 75vh; overflow-y: auto; }
.modal-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.field-label { font-size: 11.5px; font-weight: 700; color: #64748b; margin-bottom: 5px; display: block; text-transform: uppercase; letter-spacing: 0.5px; }
.field-input { width: 100%; padding: 10px 13px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13.5px; color: #0f172a; background: white; outline: none; transition: all 0.2s; box-sizing: border-box; }
.field-input:focus { border-color: #0f172a; box-shadow: 0 0 0 3px rgba(15, 23, 42, 0.08); }
.modal-footer { padding: 16px 24px; border-top: 1px solid #e2e8f0; display: flex; gap: 10px; justify-content: flex-end; background: #f8fafc; }
.modal-btn-save { background: #0f172a; color: white; border: 1px solid #0f172a; padding: 10px 22px; border-radius: 8px; font-weight: 700; font-size: 13.5px; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 6px rgba(15,23,42,0.15); display: inline-flex; align-items: center; gap: 6px; }
.modal-btn-save:hover { background: #1e293b; border-color: #1e293b; transform: translateY(-1px); }
.modal-btn-cancel { background: white; color: #475569; border: 1px solid #cbd5e1; padding: 10px 18px; border-radius: 8px; font-weight: 600; font-size: 13.5px; cursor: pointer; transition: all 0.15s; }
.modal-btn-cancel:hover { background: #f1f5f9; color: #0f172a; }

/* Entity search results */
.entity-results { border: 1px solid var(--border); border-radius: 8px; background: white; box-shadow: 0 8px 20px rgba(0,0,0,0.1); max-height: 200px; overflow-y: auto; display: none; position: absolute; z-index: 200; width: 100%; left: 0; top: 100%; }
.entity-result-item { padding: 9px 14px; cursor: pointer; transition: background 0.15s; border-bottom: 1px solid var(--border); }
.entity-result-item:last-child { border-bottom: none; }
.entity-result-item:hover { background: var(--bg-main); }
.entity-result-name { font-size: 13px; font-weight: 600; color: var(--text-primary); }
.entity-result-sub  { font-size: 11px; color: var(--text-muted); margin-top: 1px; }
.entity-search-wrap { position: relative; }

/* Priority pills in modal */
.pri-picker { display: flex; gap: 8px; }
.pri-pill { flex: 1; text-align: center; padding: 8px; border-radius: 8px; font-size: 12px; font-weight: 700; cursor: pointer; border: 2px solid transparent; transition: all 0.2s; }
.pri-pill.high   { background: #fef2f2; color: #ef4444; }
.pri-pill.medium { background: #fffbeb; color: #d97706; }
.pri-pill.low    { background: #f0fdf4; color: #16a34a; }
.pri-pill.active { border-color: currentColor; box-shadow: 0 0 0 3px rgba(0,0,0,0.06); }

/* Category chips in modal */
.cat-chips { display: flex; flex-wrap: wrap; gap: 6px; }
.cat-chip { padding: 5px 11px; border-radius: 20px; font-size: 12px; font-weight: 600; cursor: pointer; border: 1.5px solid var(--border); background: white; color: var(--text-primary); transition: all 0.15s; }
.cat-chip.active { background: var(--primary); color: white; border-color: var(--primary); }
.cat-chip:hover:not(.active) { background: var(--bg-main); }

/* Executive Topbar */
.rem-topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 22px;
    padding-bottom: 4px;
}
.rem-title-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 4px;
}
.rem-title {
    font-size: 21px;
    font-weight: 800;
    color: var(--text-primary);
    margin: 0;
    letter-spacing: -0.025em;
    line-height: 1.2;
}
.rem-badge {
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
.rem-subtitle {
    font-size: 13px;
    color: var(--text-muted);
    margin: 0;
    font-weight: 500;
}
.rem-actions-top {
    display: flex;
    align-items: center;
    gap: 10px;
}
.rem-view-pills {
    display: flex;
    background: #f1f5f9;
    padding: 3px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}
.rem-view-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 700;
    border: none;
    background: transparent;
    color: var(--text-muted);
    cursor: pointer;
    transition: all 0.15s ease;
}
.rem-view-pill:hover {
    color: var(--text-primary);
}
.rem-view-pill.active {
    background: #0f172a;
    color: #ffffff;
    box-shadow: 0 1px 3px rgba(15,23,42,0.15);
}
.rem-btn-header {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    height: 36px;
    padding: 0 14px;
    border-radius: 8px;
    font-size: 12.5px;
    font-weight: 600;
    cursor: pointer;
    border: 1px solid var(--border);
    background: #ffffff;
    color: var(--text-primary);
    transition: all 0.18s ease;
    box-shadow: 0 1px 2px rgba(0,0,0,0.02);
}
.rem-btn-header:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
    transform: translateY(-1px);
}
.rem-btn-header.rem-btn-primary {
    background: #0f172a;
    color: #ffffff;
    border-color: #0f172a;
}
.rem-btn-header.rem-btn-primary:hover {
    background: #1e293b;
    border-color: #1e293b;
}

/* Card Delete & Contact Buttons */
.rem-btn-delete {
    background: #fff1f2 !important;
    color: #e11d48 !important;
    border: 1px solid #fecdd3 !important;
    padding: 5px 8px !important;
    border-radius: 7px;
    cursor: pointer;
    transition: all 0.18s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.rem-btn-delete:hover {
    background: #ffe4e6 !important;
    border-color: #fda4af !important;
    color: #be123c !important;
    transform: translateY(-1px);
}
.rem-quick-contact {
    display: flex;
    gap: 6px;
    margin-top: 6px;
    margin-bottom: 2px;
}
.rem-contact-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.15s ease;
}
.rem-contact-call {
    background: #ecfdf5;
    color: #047857;
    border: 1px solid #a7f3d0;
}
.rem-contact-call:hover {
    background: #d1fae5;
}
.rem-contact-wa {
    background: #f0fdf4;
    color: #15803d;
    border: 1px solid #bbf7d0;
}
.rem-contact-wa:hover {
    background: #dcfce7;
}

/* List View Table */
.rem-list-container {
    background: #ffffff;
    border: 1px solid var(--border);
    border-radius: 14px;
    overflow-x: auto;
    box-shadow: 0 2px 6px rgba(0,0,0,0.02);
}
.rem-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    text-align: left;
}
.rem-table th {
    background: #f8fafc;
    color: var(--text-muted);
    font-weight: 700;
    font-size: 11.5px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 12px 16px;
    border-bottom: 1px solid var(--border);
}
.rem-table td {
    padding: 12px 16px;
    border-bottom: 1px solid #f1f5f9;
    color: var(--text-primary);
    vertical-align: middle;
}
.rem-table tr:last-child td {
    border-bottom: none;
}
.rem-table tr:hover td {
    background: #f8fafc;
}

@media (max-width: 900px) {
    .rem-kpi-row  { grid-template-columns: 1fr 1fr; }
    .rem-columns  { grid-template-columns: 1fr; }
    .modal-row    { grid-template-columns: 1fr; }
}
</style>

<div class="view-container rem-hub">

    <!-- EXECUTIVE TOPBAR -->
    <div class="rem-topbar">
        <div class="rem-title-area">
            <div class="rem-title-row">
                <h1 class="rem-title">Reminders &amp; Tasks</h1>
                <span class="rem-badge">Active Queue</span>
            </div>
            <p class="rem-subtitle">Unified follow-up desk for leads, lender escalations &amp; partner operations</p>
        </div>
        <div class="rem-actions-top">
            <div class="rem-view-pills">
                <button class="rem-view-pill active" id="btnViewBoard" onclick="switchRemView('board')">
                    <i data-lucide="kanban" style="width:13px;height:13px;"></i> Board
                </button>
                <button class="rem-view-pill" id="btnViewList" onclick="switchRemView('list')">
                    <i data-lucide="list" style="width:13px;height:13px;"></i> List
                </button>
            </div>
            <button onclick="loadReminders()" class="rem-btn-header" title="Refresh Queue">
                <i data-lucide="refresh-cw" style="width:13px;height:13px;"></i> Refresh
            </button>
            <button onclick="openAddModal()" class="rem-btn-header rem-btn-primary">
                <i data-lucide="plus" style="width:14px;height:14px;"></i> New Reminder
            </button>
        </div>
    </div>

    <!-- KPI CARDS -->
    <div class="rem-kpi-row" id="rem-kpi-row">
        <div class="rem-kpi kpi-overdue" onclick="filterBySection('overdue')">
            <div class="rem-kpi-icon"><i data-lucide="alert-circle"></i></div>
            <div><div class="rem-kpi-count" id="kpi-overdue">—</div><div class="rem-kpi-label">Overdue</div></div>
        </div>
        <div class="rem-kpi kpi-today" onclick="filterBySection('today')">
            <div class="rem-kpi-icon"><i data-lucide="calendar-clock"></i></div>
            <div><div class="rem-kpi-count" id="kpi-today">—</div><div class="rem-kpi-label">Due Today</div></div>
        </div>
        <div class="rem-kpi kpi-upcoming" onclick="filterBySection('upcoming')">
            <div class="rem-kpi-icon"><i data-lucide="calendar"></i></div>
            <div><div class="rem-kpi-count" id="kpi-upcoming">—</div><div class="rem-kpi-label">Upcoming</div></div>
        </div>
        <div class="rem-kpi kpi-done" onclick="filterBySection('')">
            <div class="rem-kpi-icon"><i data-lucide="check-circle-2"></i></div>
            <div><div class="rem-kpi-count" id="kpi-all">—</div><div class="rem-kpi-label">Total Pending</div></div>
        </div>
    </div>

    <!-- FILTER BAR -->
    <div class="rem-filters">
        <div class="search-wrapper">
            <i data-lucide="search" style="width:18px;height:18px;"></i>
            <input type="text" id="rem-search" placeholder="Search title, notes, person name..." oninput="debounceLoad()">
        </div>
        
        <select id="rem-ref-type" onchange="loadReminders()">
            <option value="">All Types</option>
            <option value="Lead">Lead / Applicant</option>
            <option value="Banker">Banker</option>
            <option value="Referral">Referral Partner</option>
            <option value="Pre-Lead">Pre-Lead</option>
            <option value="Staff">Staff / Employee</option>
            <option value="General">General Task</option>
        </select>
        
        <select id="rem-category" onchange="loadReminders()">
            <option value="">All Categories</option>
            <option value="Call Back">Call Back</option>
            <option value="Bank Visit">Bank Visit</option>
            <option value="Document Chase">Document Chase</option>
            <option value="Payout Follow-up">Payout Follow-up</option>
            <option value="Referral Meeting">Referral Meeting</option>
            <option value="Field Visit">Field Visit</option>
            <option value="Follow-up">Follow-up</option>
        </select>
        
        <select id="rem-priority" onchange="loadReminders()">
            <option value="">All Priority</option>
            <option value="High">High</option>
            <option value="Medium">Medium</option>
            <option value="Low">Low</option>
        </select>
        
        <?php if ($is_admin): ?>
        <select id="rem-staff" onchange="loadReminders()">
            <option value="">All Staff</option>
            <?php foreach ($staff_list as $su): ?>
            <option value="<?= htmlspecialchars($su) ?>"><?= htmlspecialchars($su) ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        
        <button class="rem-btn" onclick="openAddModal()" style="margin-left:auto; background:var(--primary);color:white;border:none;">
            <i data-lucide="plus" style="width:16px;height:16px;"></i> New Reminder
        </button>
    </div>

    <!-- 3-COLUMN REMINDER BOARD -->
    <div class="rem-columns" id="rem-columns">
        <div class="col-overdue">
            <div class="rem-col-header"><i data-lucide="alert-circle" style="width:16px;height:16px;"></i> Overdue <span class="rem-col-count" id="col-count-overdue">0</span></div>
            <div class="rem-col-body" id="col-overdue"></div>
        </div>
        <div class="col-today">
            <div class="rem-col-header"><i data-lucide="sun" style="width:16px;height:16px;"></i> Due Today <span class="rem-col-count" id="col-count-today">0</span></div>
            <div class="rem-col-body" id="col-today"></div>
        </div>
        <div class="col-upcoming">
            <div class="rem-col-header"><i data-lucide="calendar" style="width:16px;height:16px;"></i> Upcoming <span class="rem-col-count" id="col-count-upcoming">0</span></div>
            <div class="rem-col-body" id="col-upcoming"></div>
        </div>
    </div>

    <!-- LIST VIEW TABLE -->
    <div class="rem-list-container" id="rem-list-container" style="display:none;">
        <table class="rem-table">
            <thead>
                <tr>
                    <th>Priority</th>
                    <th>Task / Follow-up</th>
                    <th>Linked Entity</th>
                    <th>Category</th>
                    <th>Assigned</th>
                    <th>Due Time</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody id="rem-list-tbody">
                <!-- Dynamically populated -->
            </tbody>
        </table>
    </div>

</div>

<!-- ADD REMINDER MODAL -->
<div class="modal-overlay" id="add-rem-modal" onclick="closeAddModal(event)">
    <div class="modal-box" onclick="event.stopPropagation()">
        <div class="modal-header">
            <h3><i data-lucide="bell-plus" style="width:18px;height:18px;display:inline;vertical-align:-3px;margin-right:6px;"></i> Add New Reminder / Task</h3>
            <button class="modal-close" onclick="closeAddModal()">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Title -->
            <div>
                <label class="field-label">Task Title *</label>
                <input type="text" id="rem-title" class="field-input" placeholder="e.g. Call HDFC regarding payout...">
            </div>

            <!-- Entity Linking -->
            <div>
                <label class="field-label">Linked To (Related Entity)</label>
                <div class="modal-row" style="margin-bottom:8px;">
                    <select id="rem-entity-type" class="field-input" onchange="onEntityTypeChange()">
                        <option value="General">General Task</option>
                        <option value="Lead">Lead / Applicant</option>
                        <option value="Banker">Banker</option>
                        <option value="Referral">Referral Partner</option>
                        <option value="Pre-Lead">Pre-Lead</option>
                          <option value="Staff">Staff / Employee</option>
                    </select>
                    <div></div>
                </div>
                <div id="entity-search-section" style="display:none;">
                    <div class="entity-search-wrap">
                        <input type="text" id="entity-search-input" class="field-input" placeholder="Type to search..." oninput="searchEntity(this.value)" autocomplete="off">
                        <div class="entity-results" id="entity-results"></div>
                    </div>
                    <div id="entity-selected" style="display:none; margin-top:8px; padding:8px 12px; background:var(--bg-main); border-radius:8px; font-size:13px; font-weight:600; color:var(--primary); border:1.5px solid var(--primary-border); display:flex; align-items:center; gap:8px;">
                        <i data-lucide="link" style="width:14px;height:14px;"></i>
                        <span id="entity-selected-label"></span>
                        <button onclick="clearEntity()" style="margin-left:auto; background:none; border:none; cursor:pointer; color:var(--text-muted); font-size:16px;">&times;</button>
                    </div>
                    <input type="hidden" id="entity-id-val">
                    <input type="hidden" id="entity-label-val">
                </div>
            </div>

            <!-- Date & Time + Assign -->
            <div class="modal-row">
                <div>
                    <label class="field-label">Date & Time *</label>
                    <input type="datetime-local" id="rem-datetime" class="field-input">
                </div>
                <?php if ($is_admin): ?>
                <div>
                    <label class="field-label">Assign To</label>
                    <select id="rem-assign" class="field-input">
                        <option value="<?= htmlspecialchars($current_user) ?>"><?= htmlspecialchars($current_user) ?> (Me)</option>
                        <?php foreach ($staff_list as $su): if ($su === $current_user) continue; ?>
                        <option value="<?= htmlspecialchars($su) ?>"><?= htmlspecialchars($su) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php else: ?>
                <div></div>
                <?php endif; ?>
            </div>

            <!-- Priority -->
            <div>
                <label class="field-label">Priority</label>
                <div class="pri-picker">
                    <div class="pri-pill high" data-pri="High" onclick="selectPriority('High')">High</div>
                    <div class="pri-pill medium active" data-pri="Medium" onclick="selectPriority('Medium')">Medium</div>
                    <div class="pri-pill low" data-pri="Low" onclick="selectPriority('Low')">Low</div>
                </div>
            </div>

            <!-- Category -->
            <div>
                <label class="field-label">Category</label>
                <div class="cat-chips">
                    <span class="cat-chip active" data-cat="Follow-up" onclick="selectCategory(this)">Follow-up</span>
                    <span class="cat-chip" data-cat="Call Back" onclick="selectCategory(this)">Call Back</span>
                    <span class="cat-chip" data-cat="Bank Visit" onclick="selectCategory(this)">Bank Visit</span>
                    <span class="cat-chip" data-cat="Document Chase" onclick="selectCategory(this)">Document Chase</span>
                    <span class="cat-chip" data-cat="Payout Follow-up" onclick="selectCategory(this)">Payout</span>
                    <span class="cat-chip" data-cat="Referral Meeting" onclick="selectCategory(this)">Meeting</span>
                    <span class="cat-chip" data-cat="Field Visit" onclick="selectCategory(this)">Field Visit</span>
                </div>
            </div>

            <!-- Notes -->
            <div>
                <label class="field-label">Notes / Details</label>
                <textarea id="rem-notes" class="field-input" rows="3" placeholder="Add any extra notes here..." style="resize:vertical;"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="modal-btn-cancel" onclick="closeAddModal()">Cancel</button>
            <button class="modal-btn-save" onclick="saveReminder()">
                <i data-lucide="bell" style="width:14px;height:14px;display:inline;vertical-align:-2px;margin-right:5px;"></i>
                Save Reminder
            </button>
        </div>
    </div>
</div>

<script>
let remData = { overdue: [], today: [], upcoming: [] };
let allLoadedReminders = [];
let currentRemView = 'board';
let selectedPriority = 'Medium';
let selectedCategory = 'Follow-up';
let entitySearchTimer = null;

// Safe Date Parser (eliminates Invalid Date bugs across all browsers/SQL datetime)
function parseSafeDate(dateStr) {
    if (!dateStr || dateStr === '0000-00-00 00:00:00' || dateStr === 'null' || dateStr === 'undefined') return null;
    let s = String(dateStr).trim().replace(' ', 'T');
    let d = new Date(s);
    if (isNaN(d.getTime())) {
        d = new Date(dateStr);
    }
    return isNaN(d.getTime()) ? null : d;
}

// Switch View Mode: Kanban Board vs Compact List
function switchRemView(view) {
    currentRemView = view;
    const btnBoard = document.getElementById('btnViewBoard');
    const btnList = document.getElementById('btnViewList');
    if (btnBoard) btnBoard.classList.toggle('active', view === 'board');
    if (btnList) btnList.classList.toggle('active', view === 'list');
    
    const boardEl = document.getElementById('rem-columns');
    const listEl = document.getElementById('rem-list-container');
    if (view === 'board') {
        if (boardEl) boardEl.style.display = 'grid';
        if (listEl) listEl.style.display = 'none';
    } else {
        if (boardEl) boardEl.style.display = 'none';
        if (listEl) listEl.style.display = 'block';
        renderListView(allLoadedReminders);
    }
    if (window.lucide) lucide.createIcons();
}

// ===== LOAD REMINDERS =====
async function loadReminders() {
    const search   = document.getElementById('rem-search')?.value || '';
    const refType  = document.getElementById('rem-ref-type')?.value || '';
    const category = document.getElementById('rem-category')?.value || '';
    const priority = document.getElementById('rem-priority')?.value || '';
    const staff    = document.getElementById('rem-staff')?.value || '';

    // Show skeleton in all cols
    ['overdue','today','upcoming'].forEach(c => {
        const el = document.getElementById('col-'+c);
        if (el) {
            el.innerHTML = Array(2).fill(`
                <div style="background:white;border:1px solid var(--border);border-radius:12px;padding:14px 16px;">
                    <div class="skeleton" style="width:70%;height:16px;border-radius:4px;margin-bottom:10px;"></div>
                    <div class="skeleton" style="width:40%;height:12px;border-radius:4px;margin-bottom:8px;"></div>
                    <div class="skeleton" style="width:90%;height:12px;border-radius:4px;"></div>
                </div>
            `).join('');
        }
    });

    const params = new URLSearchParams({ api: 'get_reminders', search, ref_type: refType, category, priority, assigned_to: staff });
    try {
        const res  = await fetch('api.php?' + params.toString());
        const data = await res.json();

        if (!Array.isArray(data)) { showError('Invalid API response'); return; }

        allLoadedReminders = data;
        const now      = new Date();
        const todayEnd = new Date(); todayEnd.setHours(23,59,59,999);
        remData = { overdue: [], today: [], upcoming: [] };

        data.forEach(r => {
            const dt = parseSafeDate(r.remind_at);
            if (!dt) {
                // If date is unparseable or blank, queue into overdue for immediate review
                remData.overdue.push(r);
            } else if (dt < now) {
                remData.overdue.push(r);
            } else if (dt <= todayEnd) {
                remData.today.push(r);
            } else {
                remData.upcoming.push(r);
            }
        });

        renderColumn('overdue',  remData.overdue);
        renderColumn('today',    remData.today);
        renderColumn('upcoming', remData.upcoming);

        if (currentRemView === 'list') {
            renderListView(allLoadedReminders);
        }

        // Update KPI counts
        document.getElementById('kpi-overdue').textContent  = remData.overdue.length;
        document.getElementById('kpi-today').textContent    = remData.today.length;
        document.getElementById('kpi-upcoming').textContent = remData.upcoming.length;
        document.getElementById('kpi-all').textContent      = data.length;
        document.getElementById('col-count-overdue').textContent  = remData.overdue.length;
        document.getElementById('col-count-today').textContent    = remData.today.length;
        document.getElementById('col-count-upcoming').textContent = remData.upcoming.length;

        if (window.lucide) lucide.createIcons();
    } catch(e) {
        showError(e.message);
    }
}

function renderColumn(col, items) {
    const el = document.getElementById('col-' + col);
    if (!el) return;
    if (!items.length) {
        el.innerHTML = `<div class="rem-empty"><i data-lucide="check-circle" style="width:28px;height:28px;color:#10b981;margin-bottom:6px;display:block;margin-inline:auto;"></i><div style="font-size:13px; font-weight:600; color:#64748b;">All clear!</div></div>`;
        return;
    }
    el.innerHTML = items.map((r, i) => buildCard(r, col, i)).join('');
}

function renderListView(items) {
    const tbody = document.getElementById('rem-list-tbody');
    if (!tbody) return;
    if (!items || items.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:36px; color:var(--text-muted); font-size:13px;">No reminders found in queue.</td></tr>`;
        return;
    }

    const now = new Date();
    const todayEnd = new Date(); todayEnd.setHours(23,59,59,999);

    tbody.innerHTML = items.map(r => {
        const dt = parseSafeDate(r.remind_at);
        const pri = r.priority || 'Medium';
        const cat = r.reminder_category || 'Follow-up';
        const refType = r.reference_type || r.lead_type || 'General';
        const name = r.fetched_name || r.reference_label || (r.lead_id ? 'ID #' + r.lead_id : 'General');
        const mobile = r.fetched_mobile || '';
        const title = r.title || 'Follow up with ' + name;
        const assigned = r.assigned_to || '';

        let timeStr = 'Pending Scheduling', timeBadge = 'ok';
        if (dt) {
            const diffMs = dt - now;
            const diffMins = Math.round(diffMs / 60000);
            if (dt < now) {
                const ago = Math.abs(diffMins);
                if (ago < 60) timeStr = ago + 'm overdue';
                else if (ago < 1440) timeStr = Math.floor(ago/60) + 'h overdue';
                else {
                    const days = Math.floor(ago/1440);
                    timeStr = days + (days === 1 ? ' day overdue' : ' days overdue');
                }
                timeBadge = 'overdue';
            } else if (dt <= todayEnd) {
                timeStr = 'Today ' + dt.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
                timeBadge = 'today';
            } else {
                timeStr = dt.toLocaleDateString('en-IN', {day:'numeric', month:'short'}) + ', ' + dt.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
                timeBadge = 'ok';
            }
        }

        return `
        <tr id="remlist-row-${r.id}">
            <td><span class="rem-card-pri pri-badge-${pri}">${pri}</span></td>
            <td>
                <div style="font-weight:700; color:#0f172a;">${escHtml(title)}</div>
                ${r.notes ? `<div style="font-size:11.5px; color:#64748b; margin-top:2px;">${escHtml(r.notes)}</div>` : ''}
            </td>
            <td>
                <div style="font-weight:600; color:#1e293b; display:flex; align-items:center; gap:6px;">
                    <span style="font-size:10.5px; color:#64748b; background:#f1f5f9; padding:1px 6px; border-radius:4px;">${refType}</span>
                    ${escHtml(name)}
                </div>
                ${mobile ? `
                    <div style="display:flex; gap:6px; margin-top:4px;">
                        <a href="tel:${escHtml(mobile)}" class="rem-contact-pill rem-contact-call"><i data-lucide="phone" style="width:10px;height:10px;"></i> Call</a>
                        <a href="https://wa.me/91${escHtml(mobile).replace(/\D/g,'')}?text=Hello%20${encodeURIComponent(name || '')}" target="_blank" class="rem-contact-pill rem-contact-wa"><i class="fab fa-whatsapp" style="font-size:10px;"></i> WhatsApp</a>
                    </div>
                ` : ''}
            </td>
            <td><span class="rem-cat-chip">${escHtml(cat)}</span></td>
            <td><span style="font-size:12px; color:#64748b;">@${escHtml(assigned)}</span></td>
            <td>
                <span class="rem-time ${timeBadge}">
                    <i data-lucide="clock" style="width:12px;height:12px;"></i> ${timeStr}
                </span>
            </td>
            <td style="text-align:right;">
                <div style="display:inline-flex; gap:6px; align-items:center;">
                    <button class="rem-btn rem-btn-done" onclick="completeReminder(${r.id})" title="Mark Done">
                        <i data-lucide="check" style="width:13px;height:13px;"></i>
                    </button>
                    <button class="rem-btn rem-btn-delete" onclick="deleteReminder(${r.id})" title="Delete Reminder">
                        <i data-lucide="trash-2" style="width:13px;height:13px;"></i>
                    </button>
                </div>
            </td>
        </tr>
        `;
    }).join('');
}

const CATEGORY_ICONS = {};
const ENTITY_ICONS = {};
const ENTITY_URLS  = { 'Lead': 'applicant_bank_assign.php?id=', 'Banker': 'bankers.php?id=', 'Referral': 'referral_partners.php?id=', 'Pre-Lead': 'pre_leads.php?edit_prelead=', 'Staff': 'view_employee.php?id=' };

function buildCard(r, col, idx) {
    const now = new Date();
    const dt  = parseSafeDate(r.remind_at);
    const pri = r.priority || 'Medium';
    const cat = r.reminder_category || 'Follow-up';
    const refType  = r.reference_type || r.lead_type || 'General';
    const name = r.fetched_name || r.reference_label || (r.lead_id ? 'ID #' + r.lead_id : 'General');
    const mobile = r.fetched_mobile ? String(r.fetched_mobile).trim() : '';
    const refLabel = name;
    const refId    = r.reference_id || r.lead_id || '';
    const title    = r.title || 'Follow up with ' + name;
    const notes    = r.notes && r.notes !== title ? r.notes : '';
    const assigned = r.assigned_to || '';

    // Robust Time display
    let timeStr = 'Pending Scheduling', timeClass = 'ok';
    if (dt) {
        const diffMs = dt - now;
        const diffMins = Math.round(diffMs / 60000);
        if (col === 'overdue' || (dt < now && col !== 'today' && col !== 'upcoming')) {
            const ago = Math.abs(diffMins);
            if (ago < 60) {
                timeStr = ago + 'm overdue';
            } else if (ago < 1440) {
                timeStr = Math.floor(ago/60) + 'h overdue';
            } else {
                const days = Math.floor(ago/1440);
                timeStr = days + (days === 1 ? ' day overdue' : ' days overdue');
            }
            timeClass = 'overdue';
        } else if (col === 'today' || (dt <= todayEnd && dt >= now)) {
            timeStr = 'Today, ' + dt.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
            timeClass = 'today';
        } else {
            timeStr = dt.toLocaleDateString('en-IN', {day:'numeric',month:'short'}) + ', ' + dt.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
            timeClass = 'ok';
        }
    }

    // Entity badge link & quick call/whatsapp buttons
    let entityHtml = '';
    if (refType !== 'General' && refLabel) {
        const url = (ENTITY_URLS[refType] && refId) ? ENTITY_URLS[refType] + refId : '#';
        entityHtml = `
        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:8px 10px; margin-bottom:8px;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <a class="rem-entity-badge entity-${refType}" href="${url}" target="_blank" title="Open ${refType}" style="padding:0; margin:0; background:transparent; border:none;">
                    <span style="font-weight:700; color:#0f172a; font-size:12.5px;">${escHtml(refLabel)}</span>
                </a>
                <a href="${url}" target="_blank" style="color:#94a3b8;" title="View file"><i data-lucide="external-link" style="width:13px;height:13px;"></i></a>
            </div>
            ${mobile ? `
                <div class="rem-quick-contact">
                    <a href="tel:${escHtml(mobile)}" class="rem-contact-pill rem-contact-call" title="Call directly">
                        <i data-lucide="phone" style="width:11px; height:11px;"></i> ${escHtml(mobile)}
                    </a>
                    <a href="https://wa.me/91${escHtml(mobile).replace(/\D/g,'')}?text=Hello%20${encodeURIComponent(name || '')}" target="_blank" class="rem-contact-pill rem-contact-wa" title="Send WhatsApp Message">
                        <i class="fab fa-whatsapp" style="font-size:11px;"></i> WhatsApp
                    </a>
                </div>
            ` : ''}
        </div>`;
    }

    return `
    <div class="rem-card pri-${pri}" style="animation-delay:${idx * 0.04}s" id="remcard-${r.id}">
        <div class="rem-card-top" style="margin-bottom:6px;">
            <div class="rem-card-title" style="font-size:13.5px; font-weight:700; color:#0f172a;">${escHtml(title)}</div>
            <span class="rem-card-pri pri-badge-${pri}">${pri}</span>
        </div>
        ${notes ? `<div style="font-size:12px; color:#475569; margin-bottom:8px; line-height:1.4; background:#f8fafc; padding:7px 9px; border-radius:6px; border:1px solid #e2e8f0;">${escHtml(notes)}</div>` : ''}
        ${entityHtml}
        <div class="rem-card-meta">
            <span class="rem-cat-chip">${escHtml(cat)}</span>
            <span class="rem-time ${timeClass}">
                <i data-lucide="clock" style="width:12px;height:12px;"></i>
                ${timeStr}
            </span>
        </div>
        <div class="rem-actions">
            <button class="rem-btn rem-btn-done" onclick="completeReminder(${r.id})">
                <i data-lucide="check" style="width:13px;height:13px;"></i> Done
            </button>
            <div style="position:relative;">
                <button class="rem-btn rem-btn-snooze" onclick="toggleSnooze(event, ${r.id})">
                    <i data-lucide="alarm-clock" style="width:13px;height:13px;"></i> Snooze ▾
                </button>
                <div class="snooze-menu" id="snooze-${r.id}">
                    <div class="snooze-opt" onclick="snooze(${r.id}, 15)"><i data-lucide="timer"></i> +15 Minutes</div>
                    <div class="snooze-opt" onclick="snooze(${r.id}, 60)"><i data-lucide="clock"></i> +1 Hour</div>
                    <div class="snooze-opt" onclick="snooze(${r.id}, 1440)"><i data-lucide="calendar"></i> +1 Day</div>
                    <div class="snooze-opt" onclick="snooze(${r.id}, 4320)"><i data-lucide="calendar-plus"></i> +3 Days</div>
                </div>
            </div>
            <button class="rem-btn rem-btn-delete" onclick="deleteReminder(${r.id})" title="Delete Reminder">
                <i data-lucide="trash-2" style="width:12px;height:12px;"></i>
            </button>
            <span class="rem-assigned" style="margin-left:auto;"><i data-lucide="user" style="width:11px;height:11px;"></i> @${escHtml(assigned)}</span>
        </div>
    </div>`;
}

function escHtml(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ===== ACTIONS =====
async function completeReminder(id) {
    const card = document.getElementById('remcard-' + id);
    const row = document.getElementById('remlist-row-' + id);
    if (card) { card.style.opacity = '0.5'; card.style.pointerEvents = 'none'; }
    if (row) { row.style.opacity = '0.5'; row.style.pointerEvents = 'none'; }
    const fd = new FormData();
    fd.append('api', 'complete_reminder');
    fd.append('id', id);
    const res  = await fetch('api.php?api=complete_reminder', { method: 'POST', body: fd });
    const json = await res.json();
    if (json.success) {
        if (card) { card.style.transform = 'scale(0.95)'; card.style.opacity = '0'; setTimeout(() => { card.remove(); updateCountsFromDOM(); }, 250); }
        if (row) { row.remove(); }
        showNotification('Reminder marked done ✓', 'success');
    } else {
        if (card) { card.style.opacity = '1'; card.style.pointerEvents = ''; }
        if (row) { row.style.opacity = '1'; row.style.pointerEvents = ''; }
        showNotification(json.error || 'Failed', 'error');
    }
}

// Delete / Remove Reminder
async function deleteReminder(id) {
    if (typeof Swal !== 'undefined') {
        const result = await Swal.fire({
            title: 'Delete Reminder?',
            text: 'Are you sure you want to remove this reminder? This cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#0f172a',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'Yes, Delete',
            cancelButtonText: 'Cancel'
        });
        if (!result.isConfirmed) return;
    } else {
        if (!confirm('Are you sure you want to remove this reminder?')) return;
    }

    const card = document.getElementById('remcard-' + id);
    const row = document.getElementById('remlist-row-' + id);
    if (card) { card.style.opacity = '0.4'; card.style.pointerEvents = 'none'; }
    if (row) { row.style.opacity = '0.4'; row.style.pointerEvents = 'none'; }

    try {
        const fd = new FormData();
        fd.append('api', 'delete_reminder');
        fd.append('id', id);
        const res  = await fetch('api.php?api=delete_reminder', { method: 'POST', body: fd });
        const json = await res.json();
        if (json.success) {
            if (card) {
                card.style.transform = 'scale(0.95)';
                card.style.opacity = '0';
                setTimeout(() => { card.remove(); updateCountsFromDOM(); }, 250);
            }
            if (row) {
                row.remove();
            }
            showNotification('Reminder removed 🗑️', 'success');
            setTimeout(loadReminders, 250);
        } else {
            if (card) { card.style.opacity = '1'; card.style.pointerEvents = ''; }
            if (row) { row.style.opacity = '1'; row.style.pointerEvents = ''; }
            showNotification(json.error || 'Failed to delete', 'error');
        }
    } catch(e) {
        if (card) { card.style.opacity = '1'; card.style.pointerEvents = ''; }
        if (row) { row.style.opacity = '1'; row.style.pointerEvents = ''; }
        showNotification('Connection error', 'error');
    }
}

function toggleSnooze(e, id) {
    e.stopPropagation();
    document.querySelectorAll('.snooze-menu.open').forEach(m => { if (m.id !== 'snooze-' + id) m.classList.remove('open'); });
    document.getElementById('snooze-' + id).classList.toggle('open');
}
document.addEventListener('click', () => document.querySelectorAll('.snooze-menu.open').forEach(m => m.classList.remove('open')));

async function snooze(id, minutes) {
    document.getElementById('snooze-' + id).classList.remove('open');
    const fd = new FormData();
    fd.append('api', 'snooze_reminder');
    fd.append('id', id);
    fd.append('minutes', minutes);
    const res  = await fetch('api.php?api=snooze_reminder', { method: 'POST', body: fd });
    const json = await res.json();
    if (json.success) {
        showNotification('Snoozed successfully ⏰', 'success');
        loadReminders();
    } else {
        showNotification(json.error || 'Failed to snooze', 'error');
    }
}

function updateCountsFromDOM() {
    ['overdue','today','upcoming'].forEach(c => {
        const count = document.getElementById('col-'+c).querySelectorAll('.rem-card').length;
        document.getElementById('col-count-'+c).textContent = count;
    });
}

function filterBySection(section) {
    const dateEl = document.getElementById('rem-filter-date');
    // Just reload with implicit filter by clicking the KPI  
    loadReminders();
}

let debounceTimer;
function debounceLoad() { clearTimeout(debounceTimer); debounceTimer = setTimeout(loadReminders, 350); }

// ===== ADD MODAL =====
function openAddModal(prefill = {}) {
    document.getElementById('add-rem-modal').classList.add('active');
    // Set default datetime to now + 1 hour
    const dt = new Date(Date.now() + 3600000);
    const localStr = new Date(dt.getTime() - dt.getTimezoneOffset() * 60000).toISOString().slice(0,16);
    document.getElementById('rem-datetime').value = localStr;
    if (prefill.reference_type) {
        document.getElementById('rem-entity-type').value = prefill.reference_type;
        onEntityTypeChange();
        document.getElementById('entity-id-val').value    = prefill.id || '';
        document.getElementById('entity-label-val').value = prefill.label || '';
        document.getElementById('entity-selected-label').textContent = prefill.label || '';
        if (prefill.label) document.getElementById('entity-selected').style.display = 'flex';
    }
    if (window.lucide) lucide.createIcons();
}
function closeAddModal(e) {
    if (e && e.target !== document.getElementById('add-rem-modal')) return;
    document.getElementById('add-rem-modal').classList.remove('active');
    // Reset form
    document.getElementById('rem-title').value = '';
    document.getElementById('rem-notes').value = '';
    document.getElementById('rem-entity-type').value = 'General';
    clearEntity();
    onEntityTypeChange();
    selectPriority('Medium');
    selectCategory(document.querySelector('.cat-chip[data-cat="Follow-up"]'));
}

function onEntityTypeChange() {
    const type = document.getElementById('rem-entity-type').value;
    const sec  = document.getElementById('entity-search-section');
    if (type === 'General') { sec.style.display = 'none'; clearEntity(); }
    else { sec.style.display = 'block'; document.getElementById('entity-search-input').placeholder = 'Search ' + type + '...'; }
}

function selectPriority(p) {
    selectedPriority = p;
    document.querySelectorAll('.pri-pill').forEach(el => {
        el.classList.toggle('active', el.dataset.pri === p);
    });
}
function selectCategory(el) {
    document.querySelectorAll('.cat-chip').forEach(c => c.classList.remove('active'));
    el.classList.add('active');
    selectedCategory = el.dataset.cat;
}

// ===== LIVE ENTITY SEARCH =====
async function searchEntity(q) {
    clearTimeout(entitySearchTimer);
    if (q.length < 2) { document.getElementById('entity-results').style.display = 'none'; return; }
    entitySearchTimer = setTimeout(async () => {
        const type = document.getElementById('rem-entity-type').value;
        const res  = await fetch(`api.php?api=search_entity&type=${encodeURIComponent(type)}&q=${encodeURIComponent(q)}`);
        const data = await res.json();
        const box  = document.getElementById('entity-results');
        if (!data.length) { box.style.display = 'none'; return; }
        box.innerHTML = data.map(d => `
            <div class="entity-result-item" onclick="selectEntity('${d.id}', '${escHtml(d.label)}')">
                <div class="entity-result-name">${escHtml(d.label)}</div>
                <div class="entity-result-sub">${escHtml(d.sub || '')}</div>
            </div>
        `).join('');
        box.style.display = 'block';
    }, 280);
}
function selectEntity(id, label) {
    document.getElementById('entity-id-val').value    = id;
    document.getElementById('entity-label-val').value = label;
    document.getElementById('entity-search-input').value = '';
    document.getElementById('entity-results').style.display = 'none';
    document.getElementById('entity-selected-label').textContent = label;
    const sel = document.getElementById('entity-selected');
    sel.style.display = 'flex';
    if (window.lucide) lucide.createIcons();
}
function clearEntity() {
    document.getElementById('entity-id-val').value    = '';
    document.getElementById('entity-label-val').value = '';
    document.getElementById('entity-search-input').value = '';
    document.getElementById('entity-results').style.display = 'none';
    document.getElementById('entity-selected').style.display = 'none';
}

// ===== SAVE REMINDER =====
async function saveReminder() {
    const title    = document.getElementById('rem-title').value.trim();
    const dt       = document.getElementById('rem-datetime').value;
    const refType  = document.getElementById('rem-entity-type').value;
    const refId    = document.getElementById('entity-id-val').value;
    const refLabel = document.getElementById('entity-label-val').value;
    const notes    = document.getElementById('rem-notes').value.trim();
    const assign   = document.getElementById('rem-assign')?.value || '<?= $current_user ?>';

    if (!title) { showNotification('Please enter a task title', 'error'); document.getElementById('rem-title').focus(); return; }
    if (!dt)    { showNotification('Please select date & time', 'error'); document.getElementById('rem-datetime').focus(); return; }
    if (refType !== 'General' && !refId) { showNotification('Please search and select the linked ' + refType, 'error'); return; }

    const fd = new FormData();
    fd.append('api',              'save_reminder');
    fd.append('title',            title);
    fd.append('reference_type',   refType);
    fd.append('reference_id',     refId);
    fd.append('reference_label',  refLabel);
    fd.append('remind_at',        dt.replace('T', ' ') + ':00');
    fd.append('notes',            notes);
    fd.append('priority',         selectedPriority);
    fd.append('reminder_category',selectedCategory);
    fd.append('assigned_to',      assign);
    fd.append('lead_type',        refType);
    fd.append('lead_id',          refId || 0);

    const btn = document.querySelector('.modal-btn-save');
    btn.disabled = true; btn.textContent = 'Saving...';
    try {
        const res  = await fetch('api.php?api=save_reminder', { method: 'POST', body: fd });
        const json = await res.json();
        if (json.success) {
            showNotification('Reminder saved! 🔔', 'success');
            document.getElementById('add-rem-modal').classList.remove('active');
            loadReminders();
        } else {
            showNotification(json.error || 'Failed to save', 'error');
        }
    } catch(e) {
        showNotification('Connection error', 'error');
    } finally {
        btn.disabled = false; btn.textContent = 'Save Reminder';
    }
}

function showError(msg) {
    ['overdue','today','upcoming'].forEach(c => {
        document.getElementById('col-'+c).innerHTML = `<div class="rem-empty" style="color:var(--danger);">${msg}</div>`;
    });
}

// Init
document.addEventListener('DOMContentLoaded', () => {
    loadReminders();
    setInterval(loadReminders, 120000); // Auto-refresh every 2 min
    
    // Auto-open modal if URL param
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('new')) {
        let prefill = {};
        if (urlParams.get('type')) prefill.reference_type = urlParams.get('type');
        if (urlParams.get('id')) prefill.id = urlParams.get('id');
        if (urlParams.get('name')) prefill.label = urlParams.get('name');
        openAddModal(prefill);
    }
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
