<?php
// fund_management.php - Fund Flow System for BFS Circle CRM
// (Paisa Aaya → Kisko Diya → Kis Kaam Me Laga → Proof Kya Hai → Kitna Bacha)
require_once 'config.php';

// Strict Admin Authorization (Zero Bypassing)
require_role(['Admin'], false);


$page_title = 'Fund Flow System';
$hide_main_header = true;
require_once 'header.php';
?>

<style>
/* Fund Module Styling - Modern Financial Governance Theme */
:root {
    --fund-primary: #0f172a;
    --fund-secondary: #1e293b;
    --fund-accent: #f59e0b;
    --fund-emerald: #10b981;
    --fund-emerald-bg: #ecfdf5;
    --fund-blue: #3b82f6;
    --fund-purple: #8b5cf6;
    --fund-rose: #f43f5e;
    --fund-bg: #f8fafc;
    --fund-card-bg: #ffffff;
    --fund-border: #e2e8f0;
    --fund-text: #0f172a;
    --fund-muted: #64748b;
}

.fund-wrap {
    padding: 24px;
    background: var(--fund-bg);
    min-height: 100vh;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}

/* Header & Top Quick Action Bar */
.fund-header-strip {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
}
.fund-header-title h2 {
    font-size: 24px;
    font-weight: 800;
    color: var(--fund-primary);
    margin: 0 0 4px 0;
    display: flex;
    align-items: center;
    gap: 10px;
}
.fund-header-title p {
    font-size: 13px;
    color: var(--fund-muted);
    margin: 0;
}
.fund-action-bar {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.fund-btn {
    padding: 9px 16px;
    border-radius: 8px;
    font-size: 12.5px;
    font-weight: 700;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s ease;
    text-decoration: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}
.fund-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}
.fund-btn-dark { background: #0f172a; color: #ffffff; }
.fund-btn-emerald { background: #10b981; color: #ffffff; }
.fund-btn-blue { background: #3b82f6; color: #ffffff; }
.fund-btn-amber { background: #f59e0b; color: #ffffff; }
.fund-btn-purple { background: #8b5cf6; color: #ffffff; }
.fund-btn-outline { background: #ffffff; color: #334155; border: 1px solid #cbd5e1; }
.fund-btn-outline:hover { background: #f1f5f9; }

/* TASK 2: Quick Action 4 Cards Grid */
.quick-action-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}
@media (max-width: 1024px) { .quick-action-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 640px) { .quick-action-grid { grid-template-columns: 1fr; } }

.quick-action-card {
    background: #ffffff;
    border: 1.5px solid var(--fund-border);
    border-radius: 12px;
    padding: 16px 18px;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    box-shadow: 0 2px 6px rgba(0,0,0,0.03);
    position: relative;
    overflow: hidden;
}
.quick-action-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.08);
}
.quick-action-card.card-inflow { border-top: 4px solid #10b981; }
.quick-action-card.card-allocation { border-top: 4px solid #3b82f6; }
.quick-action-card.card-payment { border-top: 4px solid #8b5cf6; }
.quick-action-card.card-expense { border-top: 4px solid #f59e0b; }

.quick-card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;
}
.quick-card-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}
.card-inflow .quick-card-icon { background: #ecfdf5; color: #10b981; }
.card-allocation .quick-card-icon { background: #eff6ff; color: #3b82f6; }
.card-payment .quick-card-icon { background: #f3e8ff; color: #8b5cf6; }
.card-expense .quick-card-icon { background: #fffbeb; color: #f59e0b; }

.quick-card-title {
    font-size: 14px;
    font-weight: 800;
    color: var(--fund-primary);
    margin: 4px 0 2px 0;
}
.quick-card-desc {
    font-size: 11.5px;
    color: var(--fund-muted);
    line-height: 1.3;
}
.quick-card-btn {
    margin-top: 12px;
    font-size: 11.5px;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

/* TASK 1: 5 Main KPI Cards */
.fund-kpi-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}
@media (max-width: 1200px) { .fund-kpi-grid { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 768px) { .fund-kpi-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 500px) { .fund-kpi-grid { grid-template-columns: 1fr; } }

.fund-kpi-card {
    background: #ffffff;
    border: 1px solid var(--fund-border);
    border-radius: 12px;
    padding: 16px 18px;
    position: relative;
    box-shadow: 0 2px 6px rgba(0,0,0,0.02);
    transition: all 0.2s;
}
.fund-kpi-card:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,0,0,0.06); }
.fund-kpi-card.highlight-emerald {
    background: linear-gradient(135deg, #065f46 0%, #047857 100%);
    color: #ffffff;
    border: 1.5px solid #10b981;
    box-shadow: 0 8px 20px rgba(16,185,129,0.25);
}
.fund-kpi-card.highlight-emerald .fund-kpi-val,
.fund-kpi-card.highlight-emerald .fund-kpi-lbl {
    color: #ffffff !important;
}
.fund-kpi-lbl {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--fund-muted);
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.fund-kpi-val {
    font-size: 21px;
    font-weight: 900;
    color: var(--fund-primary);
    line-height: 1.2;
}
.fund-kpi-sub {
    font-size: 11px;
    margin-top: 6px;
    color: #64748b;
    font-weight: 600;
}

/* Collapsible Advanced Details */
.advanced-details-box {
    display: none;
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 24px;
}
.advanced-details-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
}

/* TASK 5: Attention Required Warning Section */
.attention-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
    margin-bottom: 24px;
}
@media (max-width: 1024px) { .attention-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 640px) { .attention-grid { grid-template-columns: 1fr; } }

.attention-card {
    background: #fffbeb;
    border: 1px solid #fef3c7;
    border-left: 4px solid #f59e0b;
    border-radius: 10px;
    padding: 12px 14px;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 12px;
}
.attention-card:hover {
    background: #fef3c7;
    transform: translateY(-1px);
}
.attention-icon {
    font-size: 20px;
}
.attention-text {
    font-size: 12.5px;
    font-weight: 700;
    color: #92400e;
}
.attention-sub {
    font-size: 11px;
    color: #b45309;
    font-weight: 600;
}

/* TASK 3: Visual Fund Journey Timeline Component */
.fund-journey-container {
    background: #ffffff;
    border: 1px solid var(--fund-border);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 24px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.02);
}
.fund-journey-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
    flex-wrap: wrap;
    gap: 10px;
}
.fund-journey-title {
    font-size: 14px;
    font-weight: 800;
    color: var(--fund-primary);
    display: flex;
    align-items: center;
    gap: 8px;
}
.fund-flow-steps {
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: relative;
    flex-wrap: wrap;
    gap: 10px;
}
.fund-flow-step {
    flex: 1;
    min-width: 140px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 12px 14px;
    text-align: center;
    position: relative;
}
.fund-flow-step.active {
    border-color: #3b82f6;
    background: #eff6ff;
}
.fund-flow-step.completed {
    border-color: #10b981;
    background: #ecfdf5;
}
.step-num {
    font-size: 10px;
    font-weight: 800;
    color: #64748b;
    text-transform: uppercase;
}
.step-name {
    font-size: 13px;
    font-weight: 800;
    color: var(--fund-primary);
    margin: 2px 0 4px;
}
.step-val {
    font-size: 12px;
    font-weight: 700;
    color: #047857;
}

/* Tab Navigation Bar */
.fund-tabs-nav {
    display: flex;
    gap: 4px;
    background: #ffffff;
    border: 1px solid var(--fund-border);
    border-radius: 12px;
    padding: 6px;
    margin-bottom: 24px;
    overflow-x: auto;
    scrollbar-width: thin;
}
.fund-tab-btn {
    padding: 9px 14px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 700;
    color: #475569;
    border: none;
    background: transparent;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 6px;
}
.fund-tab-btn:hover {
    background: #f1f5f9;
    color: #0f172a;
}
.fund-tab-btn.active {
    background: #0f172a;
    color: #fbbf24;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

/* Card Container & Table */
.fund-card {
    background: #ffffff;
    border: 1px solid var(--fund-border);
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.02);
    margin-bottom: 24px;
}
.fund-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
    flex-wrap: wrap;
    gap: 12px;
}
.fund-card-title {
    font-size: 16px;
    font-weight: 800;
    color: var(--fund-primary);
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Table Design */
.fund-table-wrap {
    overflow-x: auto;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}
.fund-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    text-align: left;
}
.fund-table th {
    background: #f8fafc;
    color: #475569;
    font-weight: 700;
    padding: 12px 14px;
    border-bottom: 1.5px solid #e2e8f0;
    white-space: nowrap;
}
.fund-table td {
    padding: 12px 14px;
    border-bottom: 1px solid #f1f5f9;
    color: #1e293b;
    vertical-align: middle;
}
.fund-table tr:hover td { background: #f8fafc; }

/* TASK 6: Color-Coded Status Badges & Friendly Labels */
.badge-status {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.badge-pending { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
.badge-approved { background: #dbeafe; color: #1d4ed8; border: 1px solid #bfdbfe; }
.badge-paid { background: #f3e8ff; color: #7c3aed; border: 1px solid #e9d5ff; }
.badge-util-pending { background: #ffedd5; color: #c2410c; border: 1px solid #fed7aa; }
.badge-verified { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
.badge-closed { background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; }

/* Modal Custom Overlay */
.fund-modal {
    display: none;
    position: fixed;
    top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(4px);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    padding: 16px;
}
.fund-modal.open { display: flex; }
.fund-modal-content {
    background: #ffffff;
    border-radius: 16px;
    max-width: 700px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 40px rgba(0,0,0,0.2);
    border: 1px solid #e2e8f0;
}
.fund-modal-header {
    padding: 18px 24px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f8fafc;
    border-radius: 16px 16px 0 0;
}
.fund-modal-header h3 { font-size: 17px; font-weight: 800; color: #0f172a; margin: 0; }
.fund-modal-close { font-size: 20px; font-weight: 700; color: #64748b; cursor: pointer; border: none; background: transparent; }
.fund-modal-body { padding: 24px; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.form-group-full { grid-column: span 2; }
.form-label { display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 6px; }
.form-ctrl {
    width: 100%;
    padding: 9px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 13px;
    outline: none;
    box-sizing: border-box;
}
.form-ctrl:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
.val-alert {
    background: #fff1f2;
    border: 1px solid #fecdd3;
    color: #e11d48;
    padding: 10px 14px;
    border-radius: 8px;
    font-size: 12.5px;
    font-weight: 700;
    margin-top: 10px;
    display: none;
}
</style>

<div class="fund-wrap">

    <!-- 2 BIG HERO ACTION BUTTONS CARD -->
    <div style="background:#ffffff; border:1.5px solid #e2e8f0; border-radius:16px; padding:20px 24px; margin-bottom:24px; box-shadow:0 4px 14px rgba(0,0,0,0.03);">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
            <div>
                <h2 style="font-size:22px; font-weight:900; color:#0f172a; margin:0 0 4px 0; display:flex; align-items:center; gap:8px;">
                    <i data-lucide="landmark" style="width:24px;height:24px;color:#0f172a;"></i> Enterprise Fund & Cashbook Desk
                </h2>
                <p style="font-size:13px; color:#64748b; margin:0;">Real-time Capital Inflow, Allocation & Outflow Management (BFS Circle)</p>
            </div>
            <div style="display:flex; gap:12px; flex-wrap:wrap;">
                <button class="fund-btn" style="background:#0f172a; color:#ffffff; border:1.5px solid #059669; padding:12px 24px; font-size:14px; font-weight:800; border-radius:10px; box-shadow:0 4px 12px rgba(15,23,42,0.15); cursor:pointer;" onclick="openModal('modalReceipt')">
                    <i data-lucide="arrow-down-right" style="color:#10b981;"></i> + RECEIVE CAPITAL (CREDIT)
                </button>
                <button class="fund-btn" style="background:#0f172a; color:#ffffff; border:1.5px solid #dc2626; padding:12px 24px; font-size:14px; font-weight:800; border-radius:10px; box-shadow:0 4px 12px rgba(15,23,42,0.15); cursor:pointer;" onclick="openModal('modalDisbursement')">
                    <i data-lucide="arrow-up-right" style="color:#ef4444;"></i> - DISBURSE PAYMENT (DEBIT)
                </button>
            </div>
        </div>
    </div>

    <!-- 3 KPI SUMMARY CARDS (Obsidian Theme) -->
    <div class="fund-kpi-grid" style="grid-template-columns: 1fr 1fr 1.2fr; gap:16px; margin-bottom:24px;">
        <div class="fund-kpi-card" style="border-left: 4px solid #059669; background:#ffffff;">
            <div class="fund-kpi-lbl">TOTAL CAPITAL RECEIVED <i data-lucide="arrow-down-right" style="width:16px;height:16px;color:#059669;"></i></div>
            <div class="fund-kpi-val" id="kpiReceived" style="color:#059669;">₹0</div>
            <div class="fund-kpi-sub">Cumulative Inward Capital</div>
        </div>

        <div class="fund-kpi-card" style="border-left: 4px solid #dc2626; background:#ffffff;">
            <div class="fund-kpi-lbl">TOTAL DISBURSED PAYMENTS <i data-lucide="arrow-up-right" style="width:16px;height:16px;color:#dc2626;"></i></div>
            <div class="fund-kpi-val" id="kpiDisbursed" style="color:#dc2626;">₹0</div>
            <div class="fund-kpi-sub">Cumulative Outward Disbursements</div>
        </div>

        <div class="fund-kpi-card" id="cardKpiAvailable" style="background:linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color:#ffffff; border:1.5px solid #334155; box-shadow:0 8px 20px rgba(15,23,42,0.2);">
            <div class="fund-kpi-lbl" style="color:#94a3b8;">AVAILABLE OPERATING BALANCE <i data-lucide="wallet" style="width:16px;height:16px;color:#ffffff;"></i></div>
            <div class="fund-kpi-val" id="kpiAvailable" style="font-size:28px; color:#ffffff;">₹0</div>
            <div class="fund-kpi-sub" id="kpiAvailableSub" style="color:#cbd5e1;">Liquid Cash Balance Available</div>
        </div>
    </div>

    <!-- UNIFIED CASHBOOK TRANSACTIONS TABLE WITH SEARCH & PAGINATION -->
    <div class="fund-card">
        <div class="fund-card-header">
            <div class="fund-card-title"><i data-lucide="list" style="color:#0f172a;"></i> Recent Financial Transactions</div>
            <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                <button class="fund-btn" style="background:#0f172a; color:#ffffff; border:none;" onclick="openModal('modalUtilization')"><i data-lucide="file-text"></i> Upload Bill / Invoice Proof</button>
            </div>
        </div>

        <!-- SEARCH & FILTER TOOLBAR -->
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:12px; background:#f8fafc; padding:12px 16px; border-radius:10px; border:1px solid #e2e8f0;">
            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap; flex:1;">
                <div style="position:relative; min-width:240px; flex:1;">
                    <input type="text" id="cashbookSearchInput" placeholder="🔍 Search Ref ID, Beneficiary, UTR, Date, Notes..." class="form-ctrl" style="padding-left:12px; font-size:12.5px; height:36px;" onkeyup="filterCashbookTable()">
                </div>
                <select id="cashbookTypeFilter" class="form-ctrl" style="width:auto; font-size:12.5px; height:36px; font-weight:700;" onchange="filterCashbookTable()">
                    <option value="ALL">All Transaction Types</option>
                    <option value="Credit">💰 Capital Credit Only</option>
                    <option value="Debit">📤 Payout Debit Only</option>
                </select>
            </div>
            <div style="display:flex; align-items:center; gap:8px; font-size:12px; color:#475569; font-weight:700;">
                <span>Show:</span>
                <select id="cashbookPageSize" class="form-ctrl" style="width:auto; font-size:12px; height:34px; padding:4px 8px;" onchange="changeCashbookPageSize()">
                    <option value="10" selected>10 per page</option>
                    <option value="25">25 per page</option>
                    <option value="50">50 per page</option>
                    <option value="100">100 per page</option>
                </select>
            </div>
        </div>

        <div class="fund-table-wrap">
            <table class="fund-table" id="tableCashbook">
                <thead>
                    <tr>
                        <th>Date & Ref ID</th>
                        <th>Type</th>
                        <th>Party Name & Payment Details</th>
                        <th>Amount (₹)</th>
                        <th>Proof Document</th>
                        <th>Status</th>
                        <th>Actions & Details</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td colspan="7" style="text-align:center; padding:20px; color:#64748b;">Loading recent financial transactions...</td></tr>
                </tbody>
            </table>
        </div>

        <!-- PAGINATION FOOTER BAR -->
        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:16px; flex-wrap:wrap; gap:12px; padding-top:12px; border-top:1px solid #e2e8f0;">
            <div style="font-size:12.5px; color:#64748b; font-weight:600;" id="cashbookPageInfo">
                Showing 0 to 0 of 0 entries
            </div>
            <div style="display:flex; gap:6px; align-items:center;" id="cashbookPaginationNav">
                <!-- Dynamic Page Buttons -->
            </div>
        </div>
    </div>
    </div>



    <!-- TAB 1: EXECUTIVE DASHBOARD OVERVIEW -->
    <div id="tab-dashboard" class="tab-pane">
        <div style="display:grid; grid-template-columns: 1.2fr 1fr; gap:20px;">
            <div class="fund-card">
                <div class="fund-card-header">
                    <div class="fund-card-title"><i data-lucide="activity"></i> Recent Activity Feed</div>
                </div>
                <div id="activityFeedList" style="display:flex; flex-direction:column; gap:10px;">
                    <div style="color:#64748b; font-size:13px;">Loading recent activities...</div>
                </div>
            </div>

            <div class="fund-card">
                <div class="fund-card-header">
                    <div class="fund-card-title"><i data-lucide="bar-chart-2"></i> Fund Inflow vs Outflow</div>
                </div>
                <table class="fund-table">
                    <tr><td>Total Inward Receipts:</td><th id="sumReceived" style="color:#10b981;">₹0</th></tr>
                    <tr><td>Total Approved Allocations:</td><th id="sumAllocated" style="color:#3b82f6;">₹0</th></tr>
                    <tr><td>Total Paid Out Disbursed:</td><th id="sumDisbursed" style="color:#8b5cf6;">₹0</th></tr>
                    <tr><td>Total Verified Expenses:</td><th id="sumUtilized" style="color:#ec4899;">₹0</th></tr>
                    <tr style="background:#ecfdf5;"><td><strong>Liquid Available Balance:</strong></td><th id="sumAvailable" style="color:#047857; font-size:15px;">₹0</th></tr>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 2: FUND SOURCES -->
    <div id="tab-sources" class="tab-pane" style="display:none;">
        <div class="fund-card">
            <div class="fund-card-header">
                <div class="fund-card-title"><i data-lucide="building"></i> Fund Sources & Granting Agencies</div>
                <button class="fund-btn fund-btn-dark" onclick="openModal('modalSource')">+ Add Fund Source</button>
            </div>
            <div class="fund-table-wrap">
                <table class="fund-table" id="tableSources">
                    <thead>
                        <tr>
                            <th>Source ID</th>
                            <th>Source Name</th>
                            <th>Type</th>
                            <th>Organization</th>
                            <th>Department</th>
                            <th>Contact Person</th>
                            <th>Mobile / Email</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 3: SANCTIONED FUNDS -->
    <div id="tab-funds" class="tab-pane" style="display:none;">
        <div class="fund-card">
            <div class="fund-card-header">
                <div class="fund-card-title"><i data-lucide="file-text"></i> Sanctioned Fund Master Register</div>
                <button class="fund-btn fund-btn-dark" onclick="openModal('modalFund')">+ Sanction New Fund</button>
            </div>
            <div class="fund-table-wrap">
                <table class="fund-table" id="tableFunds">
                    <thead>
                        <tr>
                            <th>Fund ID</th>
                            <th>Fund Name</th>
                            <th>Source</th>
                            <th>Sanction No / Date</th>
                            <th>Sanctioned Amt</th>
                            <th>Received</th>
                            <th>Disbursed</th>
                            <th>Available Bal</th>
                            <th>Status</th>
                            <th>Doc</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 4: FUND RECEIPTS -->
    <div id="tab-receipts" class="tab-pane" style="display:none;">
        <div class="fund-card">
            <div class="fund-card-header">
                <div class="fund-card-title"><i data-lucide="download"></i> Inward Fund Receipts</div>
                <button class="fund-btn fund-btn-emerald" onclick="openModal('modalReceipt')">+ Receive Fund</button>
            </div>
            <div class="fund-table-wrap">
                <table class="fund-table" id="tableReceipts">
                    <thead>
                        <tr>
                            <th>Receipt ID</th>
                            <th>Fund / Source</th>
                            <th>Amount Received</th>
                            <th>Received Date</th>
                            <th>Mode / UTR</th>
                            <th>Bank Name & Acc</th>
                            <th>Status</th>
                            <th>Proof</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 5: FUND ALLOCATIONS -->
    <div id="tab-allocations" class="tab-pane" style="display:none;">
        <div class="fund-card">
            <div class="fund-card-header">
                <div class="fund-card-title"><i data-lucide="target"></i> Fund Allocations Desk</div>
                <button class="fund-btn fund-btn-blue" onclick="openModal('modalAllocation')">+ Allocate Fund</button>
            </div>
            <div class="fund-table-wrap">
                <table class="fund-table" id="tableAllocations">
                    <thead>
                        <tr>
                            <th>Allocation ID</th>
                            <th>Fund Master</th>
                            <th>Project / Scheme</th>
                            <th>Receiver</th>
                            <th>Allocated Amt</th>
                            <th>Paid Out</th>
                            <th>Remaining</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 6: DISBURSEMENTS & PAYMENTS -->
    <div id="tab-disbursements" class="tab-pane" style="display:none;">
        <div class="fund-card">
            <div class="fund-card-header">
                <div class="fund-card-title"><i data-lucide="credit-card"></i> Disbursement & Payment Payouts</div>
                <button class="fund-btn fund-btn-purple" onclick="openModal('modalDisbursement')">+ Make Payment</button>
            </div>
            <div class="fund-table-wrap">
                <table class="fund-table" id="tableDisbursements">
                    <thead>
                        <tr>
                            <th>Payment ID</th>
                            <th>Allocation Code</th>
                            <th>Receiver</th>
                            <th>Paid Amount</th>
                            <th>Payment Date</th>
                            <th>Mode / UTR</th>
                            <th>Status</th>
                            <th>Proof</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 7: UTILIZATION & EXPENSES -->
    <div id="tab-utilizations" class="tab-pane" style="display:none;">
        <div class="fund-card">
            <div class="fund-card-header">
                <div class="fund-card-title"><i data-lucide="shopping-bag"></i> Expense Utilizations & Invoices</div>
                <button class="fund-btn fund-btn-amber" onclick="openModal('modalUtilization')">+ Add Expense</button>
            </div>
            <div class="fund-table-wrap">
                <table class="fund-table" id="tableUtilizations">
                    <thead>
                        <tr>
                            <th>Utilization ID</th>
                            <th>Category</th>
                            <th>Expense Amount</th>
                            <th>Vendor / Invoice</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Proof</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TASK 4: TAB 8: PROJECTS & SCHEMES WITH SINGLE SCREEN VIEW -->
    <div id="tab-projects" class="tab-pane" style="display:none;">
        <div class="fund-card">
            <div class="fund-card-header">
                <div class="fund-card-title"><i data-lucide="folder"></i> Projects & Schemes Master</div>
                <button class="fund-btn fund-btn-dark" onclick="openModal('modalProject')">+ Add Project</button>
            </div>
            <div class="fund-table-wrap">
                <table class="fund-table" id="tableProjects">
                    <thead>
                        <tr>
                            <th>Project ID</th>
                            <th>Project Name</th>
                            <th>Code / Scheme</th>
                            <th>Manager</th>
                            <th>Budget</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TASK 4: TAB 9: RECEIVERS & BENEFICIARIES WITH SINGLE SCREEN VIEW -->
    <div id="tab-receivers" class="tab-pane" style="display:none;">
        <div class="fund-card">
            <div class="fund-card-header">
                <div class="fund-card-title"><i data-lucide="users"></i> Receivers & Beneficiaries Master</div>
                <button class="fund-btn fund-btn-dark" onclick="openModal('modalReceiver')">+ Add Receiver</button>
            </div>
            <div class="fund-table-wrap">
                <table class="fund-table" id="tableReceivers">
                    <thead>
                        <tr>
                            <th>Receiver ID</th>
                            <th>Type</th>
                            <th>Name / Org</th>
                            <th>Bank Account & IFSC</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 10: IMMUTABLE FUND LEDGER -->
    <div id="tab-ledger" class="tab-pane" style="display:none;">
        <div class="fund-card">
            <div class="fund-card-header">
                <div class="fund-card-title"><i data-lucide="book-open"></i> Immutable Fund Ledger</div>
                <div>
                    <select id="ledgerFundFilter" class="form-ctrl" style="width:220px;" onchange="loadLedger()">
                        <option value="0">All Funds Ledger</option>
                    </select>
                </div>
            </div>
            <div class="fund-table-wrap">
                <table class="fund-table" id="tableLedger">
                    <thead>
                        <tr>
                            <th>Txn ID</th>
                            <th>Type</th>
                            <th>Reference No</th>
                            <th>Debit (₹)</th>
                            <th>Credit (₹)</th>
                            <th>Running Bal (₹)</th>
                            <th>Date</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 11: REPORTS DESK -->
    <div id="tab-reports" class="tab-pane" style="display:none;">
        <div class="fund-card">
            <div class="fund-card-header">
                <div class="fund-card-title"><i data-lucide="printer"></i> Reports Desk</div>
                <button class="fund-btn fund-btn-dark" onclick="window.print()"><i data-lucide="printer"></i> Print Statement</button>
            </div>
            <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:16px; margin-top:16px;">
                <div style="border:1px solid #e2e8f0; border-radius:10px; padding:16px; background:#f8fafc;">
                    <h4 style="margin:0 0 8px; font-size:14px; font-weight:800;">Utilization Certificate (UC)</h4>
                    <p style="font-size:12px; color:#64748b; margin-bottom:12px;">Official Utilization Certificate with expense breakdown and bill proof logs.</p>
                    <button class="fund-btn fund-btn-emerald" onclick="alert('Exporting Utilization Certificate (UC)...')">Generate UC Report</button>
                </div>
                <div style="border:1px solid #e2e8f0; border-radius:10px; padding:16px; background:#f8fafc;">
                    <h4 style="margin:0 0 8px; font-size:14px; font-weight:800;">Allocation vs Payout Matrix</h4>
                    <p style="font-size:12px; color:#64748b; margin-bottom:12px;">Detailed comparison of sanctioned grants, allocations, and actual payouts.</p>
                    <button class="fund-btn fund-btn-blue" onclick="alert('Generating Matrix...')">Generate Matrix</button>
                </div>
                <div style="border:1px solid #e2e8f0; border-radius:10px; padding:16px; background:#f8fafc;">
                    <h4 style="margin:0 0 8px; font-size:14px; font-weight:800;">Receiver Payout Summary</h4>
                    <p style="font-size:12px; color:#64748b; margin-bottom:12px;">Complete summary of disbursements made to vendors and beneficiaries.</p>
                    <button class="fund-btn fund-btn-purple" onclick="alert('Generating Summary...')">Generate Summary</button>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 12: FINANCIAL AUDIT LOGS -->
    <div id="tab-audit" class="tab-pane" style="display:none;">
        <div class="fund-card">
            <div class="fund-card-header">
                <div class="fund-card-title"><i data-lucide="shield-check"></i> System Financial Audit Trail</div>
            </div>
            <div class="fund-table-wrap">
                <table class="fund-table" id="tableAudit">
                    <thead>
                        <tr>
                            <th>Audit ID</th>
                            <th>Timestamp</th>
                            <th>User</th>
                            <th>Action Performed</th>
                            <th>Reference No</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- ================= MODALS ================= -->

<!-- 1. MODAL FUND SOURCE -->
<div id="modalSource" class="fund-modal">
    <div class="fund-modal-content">
        <div class="fund-modal-header">
            <h3>🏛️ Add / Edit Fund Source</h3>
            <button class="fund-modal-close" onclick="closeModal('modalSource')">&times;</button>
        </div>
        <div class="fund-modal-body">
            <form id="formSource" onsubmit="submitForm(event, 'save_fund_source', 'modalSource')">
                <input type="hidden" name="id" id="src_id">
                <div class="form-grid">
                    <div class="form-group-full">
                        <label class="form-label">Fund Source / Sender Name *</label>
                        <input type="text" name="source_name" class="form-ctrl" required value="Praveen Sir (Head Office)" placeholder="e.g. Praveen Sir / Head Office">
                    </div>
                    <div class="form-group-full">
                        <label class="form-label">Organization / Reference Notes</label>
                        <input type="text" name="organization_name" class="form-ctrl" value="BFS Circle Head Office" placeholder="Head Office / Owner Fund">
                    </div>
                </div>
                <div style="margin-top:20px; text-align:right;">
                    <button type="button" class="fund-btn fund-btn-outline" onclick="closeModal('modalSource')">Cancel</button>
                    <button type="submit" class="fund-btn fund-btn-dark">Save Source</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 2. MODAL SANCTIONED FUND MASTER -->
<div id="modalFund" class="fund-modal">
    <div class="fund-modal-content">
        <div class="fund-modal-header">
            <h3>📜 Sanction New Fund Master</h3>
            <button class="fund-modal-close" onclick="closeModal('modalFund')">&times;</button>
        </div>
        <div class="fund-modal-body">
            <form id="formFund" onsubmit="submitForm(event, 'save_fund_master', 'modalFund')">
                <input type="hidden" name="id" id="fund_master_id">
                <div class="form-grid">
                    <div>
                        <label class="form-label">Fund Name *</label>
                        <input type="text" name="fund_name" class="form-ctrl" required placeholder="e.g. Infrastructure Grant 2026">
                    </div>
                    <div>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                            <label class="form-label" style="margin:0;">Fund Source *</label>
                            <button type="button" onclick="openModal('modalSource')" style="background:none; border:none; color:#3b82f6; font-size:11px; font-weight:700; cursor:pointer; padding:0; text-decoration:underline;">+ Add New Source</button>
                        </div>
                        <select name="source_id" id="selectFundSource" class="form-ctrl" required>
                            <option value="">Select Source</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Sanction Number</label>
                        <input type="text" name="sanction_number" class="form-ctrl" placeholder="Sanction letter ref no.">
                    </div>
                    <div>
                        <label class="form-label">Sanction Date</label>
                        <input type="date" name="sanction_date" class="form-ctrl">
                    </div>
                    <div>
                        <label class="form-label">Sanctioned Amount (₹) *</label>
                        <input type="number" step="0.01" name="sanctioned_amount" class="form-ctrl" required placeholder="0.00">
                    </div>
                </div>
                <div style="margin-top:20px; text-align:right;">
                    <button type="button" class="fund-btn fund-btn-outline" onclick="closeModal('modalFund')">Cancel</button>
                    <button type="submit" class="fund-btn fund-btn-dark">Save Fund Master</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 3. MODAL 1: RECEIVE CAPITAL (CREDIT) -->
<div id="modalReceipt" class="fund-modal">
    <div class="fund-modal-content">
        <div class="fund-modal-header" style="background:#0f172a; border-bottom:1px solid #1e293b;">
            <h3 style="color:#ffffff;"><i data-lucide="arrow-down-right" style="color:#10b981;"></i> 💰 Receive Capital (Inward Credit)</h3>
            <button class="fund-modal-close" style="color:#ffffff;" onclick="closeModal('modalReceipt')">&times;</button>
        </div>
        <div class="fund-modal-body">
            <form id="formReceipt" onsubmit="submitForm(event, 'save_fund_receipt', 'modalReceipt')">
                <!-- Info Banner -->
                <div style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:10px; padding:12px 16px; margin-bottom:20px; font-size:12.5px; color:#0f172a; font-weight:600;">
                    💡 Received Amount will automatically add (+) to your Live Available Operating Balance.
                </div>

                <div class="form-grid">
                    <div class="form-group-full">
                        <label class="form-label" style="font-size:13px; font-weight:800; color:#0f172a;">Capital Amount Received (₹) *</label>
                        <input type="number" step="0.01" name="amount_received" class="form-ctrl" required placeholder="Enter Amount (e.g. 100000)" style="font-size:16px; font-weight:800; color:#059669; padding:12px;">
                    </div>
                    <div>
                        <label class="form-label">Received From (Source) *</label>
                        <select name="source_id" id="selectFundSource" class="form-ctrl" required>
                            <option value="1" selected>Praveen Sir (Head Office)</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Receipt Date *</label>
                        <input type="date" name="received_date" class="form-ctrl" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div>
                        <label class="form-label">Payment Mode *</label>
                        <select name="payment_mode" class="form-ctrl" required>
                            <option value="Bank Transfer">Bank Transfer / NEFT</option>
                            <option value="UPI">UPI / Online Transfer</option>
                            <option value="Cheque">Cheque</option>
                            <option value="Cash">Cash</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">UTR / Ref / Order No.</label>
                        <input type="text" name="utr_number" class="form-ctrl" placeholder="UTR / Reference Number">
                    </div>
                    <div class="form-group-full">
                        <label class="form-label">Purpose / Notes</label>
                        <input type="text" name="description" class="form-ctrl" placeholder="e.g. Monthly Operational Fund from Praveen Sir">
                    </div>
                    <div class="form-group-full">
                        <label class="form-label">Upload Receipt Proof (Optional)</label>
                        <input type="file" name="receipt_proof_file" class="form-ctrl">
                    </div>
                </div>
                <div style="margin-top:24px; text-align:right; border-top:1px solid #e2e8f0; padding-top:16px;">
                    <button type="button" class="fund-btn fund-btn-outline" onclick="closeModal('modalReceipt')">Cancel</button>
                    <button type="submit" class="fund-btn" style="background:#0f172a; color:#ffffff; border:1.5px solid #059669; padding:10px 22px; font-size:14px; font-weight:800; border-radius:10px;">💰 Save & Add to Capital</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 4. MODAL 2: FUND ALLOCATION WITH REAL-TIME CALCULATOR (TASK 2) -->
<div id="modalAllocation" class="fund-modal">
    <div class="fund-modal-content">
        <div class="fund-modal-header">
            <h3>🎯 2. Create Fund Allocation (Fund Dena Hai)</h3>
            <button class="fund-modal-close" onclick="closeModal('modalAllocation')">&times;</button>
        </div>
        <div class="fund-modal-body">
            <form id="formAllocation" onsubmit="submitAllocationForm(event)">
                <div class="form-grid">
                    <div class="form-group-full">
                        <label class="form-label">Select Fund Source / Master *</label>
                        <select name="fund_id" id="selectAllocFund" class="form-ctrl" required onchange="updateAllocFundBalance()">
                            <option value="">Select Fund</option>
                        </select>
                    </div>
                    
                    <!-- Real-Time Calculator Info Box -->
                    <div class="form-group-full" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px;">
                        <div style="display:flex; justify-content:space-between; font-size:12px; margin-bottom:4px;">
                            <span>Available Fund Balance:</span>
                            <strong id="allocAvailableBal" style="color:#047857;">₹0</strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; font-size:12px; margin-bottom:4px;">
                            <span>Allocation Amount:</span>
                            <strong id="allocCalcAmount" style="color:#3b82f6;">₹0</strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; font-size:12px; border-top:1px solid #e2e8f0; padding-top:4px;">
                            <span>Remaining Balance After Allocation:</span>
                            <strong id="allocCalcRemaining" style="color:#0f172a;">₹0</strong>
                        </div>
                    </div>

                    <div>
                        <label class="form-label">Allocation Amount (₹) *</label>
                        <input type="number" step="0.01" name="allocation_amount" id="inputAllocAmount" class="form-ctrl" required placeholder="0.00" oninput="validateAllocAmount()">
                    </div>
                    <div>
                        <label class="form-label">Allocation Date</label>
                        <input type="date" name="allocation_date" class="form-ctrl" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div>
                        <label class="form-label">Project / Scheme</label>
                        <select name="project_id" id="selectAllocProject" class="form-ctrl">
                            <option value="">Select Project</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Receiver / Department</label>
                        <select name="receiver_id" id="selectAllocReceiver" class="form-ctrl">
                            <option value="">Select Receiver</option>
                        </select>
                    </div>
                    <div class="form-group-full">
                        <label class="form-label">Purpose</label>
                        <input type="text" name="purpose" class="form-ctrl" placeholder="Purpose of fund allocation">
                    </div>
                    <div class="form-group-full">
                        <div id="allocValAlert" class="val-alert"></div>
                    </div>
                </div>
                <div style="margin-top:20px; text-align:right;">
                    <button type="button" class="fund-btn fund-btn-outline" onclick="closeModal('modalAllocation')">Cancel</button>
                    <button type="submit" id="btnSubmitAlloc" class="fund-btn fund-btn-blue">Save Allocation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 5. MODAL 2: DISBURSE PAYMENT (DEBIT) -->
<div id="modalDisbursement" class="fund-modal">
    <div class="fund-modal-content">
        <div class="fund-modal-header" style="background:#0f172a; border-bottom:1px solid #1e293b;">
            <h3 style="color:#ffffff;"><i data-lucide="arrow-up-right" style="color:#ef4444;"></i> 💸 Disburse Payment (Outward Debit)</h3>
            <button class="fund-modal-close" style="color:#ffffff;" onclick="closeModal('modalDisbursement')">&times;</button>
        </div>
        <div class="fund-modal-body">
            <form id="formDisbursement" onsubmit="submitDisbursementForm(event)">
                <input type="hidden" name="allocation_id" id="selectDisbAllocation" value="0">
                
                <!-- Info Banner -->
                <div style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:10px; padding:12px 16px; margin-bottom:20px; font-size:12.5px; color:#0f172a; font-weight:600;">
                    💡 Payment Amount will automatically debit (-) from your Live Available Operating Balance (Balance kam hone par bhi payout entry submit ki ja sakti hai).
                </div>

                <div class="form-grid">
                    <div class="form-group-full">
                        <label class="form-label" style="font-size:13px; font-weight:800; color:#0f172a;">Disbursement Amount Paid (₹) *</label>
                        <input type="number" step="0.01" name="payment_amount" id="inputDisbAmount" class="form-ctrl" required placeholder="Enter Amount (e.g. 50000)" style="font-size:16px; font-weight:800; color:#dc2626; padding:12px;" oninput="validateDisbAmount()">
                    </div>
                    <div class="form-group-full">
                        <label class="form-label" style="font-size:13px; font-weight:800; color:#0f172a;">Paid To / Beneficiary Name (Kisko Pese Diye) *</label>
                        <input type="text" name="receiver_name" id="inputDisbReceiverName" class="form-ctrl" required placeholder="Enter Name (e.g. Ramesh Sharma / Praveen Sir / Vendor Name)" style="font-size:14px; font-weight:700; color:#0f172a; padding:11px;" list="receiversList">
                        <datalist id="receiversList"></datalist>
                    </div>
                    <div>
                        <label class="form-label">Receiver Type / Category</label>
                        <select name="receiver_type" class="form-ctrl">
                            <option value="Vendor">Vendor / Supplier</option>
                            <option value="Employee">Staff / Employee Advance</option>
                            <option value="Contractor">Contractor</option>
                            <option value="Beneficiary">Beneficiary</option>
                        </select>
                    </div>
                    <div>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                            <label class="form-label" style="margin:0;">Project / Scheme (Optional)</label>
                            <button type="button" onclick="openModal('modalProject')" style="background:none; border:none; color:#dc2626; font-size:11px; font-weight:700; cursor:pointer; padding:0; text-decoration:underline;">+ Add Project</button>
                        </div>
                        <select name="project_id" id="selectDisbProject" class="form-ctrl">
                            <option value="">Select Project / Purpose</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Payment Date *</label>
                        <input type="date" name="payment_date" class="form-ctrl" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div>
                        <label class="form-label">Payment Mode *</label>
                        <select name="payment_mode" class="form-ctrl" required>
                            <option value="Bank Transfer">Bank Transfer / NEFT / RTGS</option>
                            <option value="UPI">UPI / Online Transfer</option>
                            <option value="Cheque">Cheque</option>
                            <option value="Cash">Cash</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">UTR / Cheque / Ref No.</label>
                        <input type="text" name="utr_number" class="form-ctrl" placeholder="UTR / Reference Number">
                    </div>
                    <div class="form-group-full">
                        <label class="form-label">Payment Purpose / Notes</label>
                        <input type="text" name="payment_purpose" class="form-ctrl" placeholder="e.g. Vendor Payment / Office Expenses / Site Material">
                    </div>
                    <div class="form-group-full">
                        <label class="form-label">Upload Payment Proof (Optional)</label>
                        <input type="file" name="payment_proof_file" class="form-ctrl">
                    </div>
                    <div class="form-group-full">
                        <div id="disbValAlert" class="val-alert"></div>
                    </div>
                </div>
                <div style="margin-top:24px; text-align:right; border-top:1px solid #e2e8f0; padding-top:16px;">
                    <button type="button" class="fund-btn fund-btn-outline" onclick="closeModal('modalDisbursement')">Cancel</button>
                    <button type="submit" id="btnSubmitDisb" class="fund-btn" style="background:#0f172a; color:#ffffff; border:1.5px solid #dc2626; padding:10px 22px; font-size:14px; font-weight:800; border-radius:10px; cursor:pointer;">💸 Save & Debit Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 6. MODAL 4: UTILIZATION / EXPENSE BILL WITH REAL-TIME CALC (TASK 2) -->
<div id="modalUtilization" class="fund-modal">
    <div class="fund-modal-content">
        <div class="fund-modal-header">
            <h3>🧾 4. Add Expense / Utilization (Bill Add Kare)</h3>
            <button class="fund-modal-close" onclick="closeModal('modalUtilization')">&times;</button>
        </div>
        <div class="fund-modal-body">
            <form id="formUtilization" onsubmit="submitForm(event, 'save_fund_utilization', 'modalUtilization')">
                <div class="form-grid">
                    <div class="form-group-full">
                        <label class="form-label">Select Payment / Fund Master *</label>
                        <select name="fund_id" id="selectUtilFund" class="form-ctrl" required>
                            <option value="">Select Fund</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Expense Category *</label>
                        <input type="text" name="expense_category" class="form-ctrl" required placeholder="e.g. Equipment / Operations">
                    </div>
                    <div>
                        <label class="form-label">Amount Used (₹) *</label>
                        <input type="number" step="0.01" name="expense_amount" class="form-ctrl" required placeholder="0.00">
                    </div>
                    <div>
                        <label class="form-label">Date *</label>
                        <input type="date" name="expense_date" class="form-ctrl" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div>
                        <label class="form-label">Invoice / Bill No.</label>
                        <input type="text" name="invoice_number" class="form-ctrl" placeholder="Tax Invoice No.">
                    </div>
                    <div>
                        <label class="form-label">Vendor Name</label>
                        <input type="text" name="vendor_name" class="form-ctrl" placeholder="Vendor / Supplier">
                    </div>
                    <div class="form-group-full">
                        <label class="form-label">Remarks / Description *</label>
                        <textarea name="expense_description" class="form-ctrl" rows="2" required placeholder="Expense details & work done"></textarea>
                    </div>
                    <div class="form-group-full">
                        <label class="form-label">Upload Bill (PDF / JPG / PNG)</label>
                        <input type="file" name="bill_proof_file" class="form-ctrl">
                    </div>
                </div>
                <div style="margin-top:20px; text-align:right;">
                    <button type="button" class="fund-btn fund-btn-outline" onclick="closeModal('modalUtilization')">Cancel</button>
                    <button type="submit" class="fund-btn fund-btn-amber">Save Verified Bill</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 7. MODAL PROJECT -->
<div id="modalProject" class="fund-modal">
    <div class="fund-modal-content">
        <div class="fund-modal-header">
            <h3>📁 Add / Edit Project</h3>
            <button class="fund-modal-close" onclick="closeModal('modalProject')">&times;</button>
        </div>
        <div class="fund-modal-body">
            <form id="formProject" onsubmit="submitForm(event, 'save_project', 'modalProject')">
                <input type="hidden" name="id" id="prj_id">
                <div class="form-grid">
                    <div class="form-group-full">
                        <label class="form-label">Project Name *</label>
                        <input type="text" name="project_name" class="form-ctrl" required placeholder="e.g. Rural Credit Expansion Project">
                    </div>
                    <div>
                        <label class="form-label">Project Code</label>
                        <input type="text" name="project_code" class="form-ctrl" placeholder="Code">
                    </div>
                    <div>
                        <label class="form-label">Budget (₹)</label>
                        <input type="number" step="0.01" name="budget" class="form-ctrl" placeholder="0.00">
                    </div>
                    <div>
                        <label class="form-label">Project Manager</label>
                        <input type="text" name="project_manager" class="form-ctrl">
                    </div>
                </div>
                <div style="margin-top:20px; text-align:right;">
                    <button type="button" class="fund-btn fund-btn-outline" onclick="closeModal('modalProject')">Cancel</button>
                    <button type="submit" class="fund-btn fund-btn-dark">Save Project</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 8. MODAL RECEIVER -->
<div id="modalReceiver" class="fund-modal">
    <div class="fund-modal-content">
        <div class="fund-modal-header">
            <h3>👤 Add / Edit Receiver Beneficiary</h3>
            <button class="fund-modal-close" onclick="closeModal('modalReceiver')">&times;</button>
        </div>
        <div class="fund-modal-body">
            <form id="formReceiver" onsubmit="submitForm(event, 'save_receiver', 'modalReceiver')">
                <input type="hidden" name="id" id="rcv_id">
                <div class="form-grid">
                    <div>
                        <label class="form-label">Receiver Name *</label>
                        <input type="text" name="name" class="form-ctrl" required placeholder="Name of person or vendor">
                    </div>
                    <div>
                        <label class="form-label">Receiver Type</label>
                        <select name="receiver_type" class="form-ctrl">
                            <option value="Vendor">Vendor / Supplier</option>
                            <option value="Beneficiary">Beneficiary</option>
                            <option value="Contractor">Contractor</option>
                            <option value="Employee">Employee</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Bank Name</label>
                        <input type="text" name="bank_name" class="form-ctrl">
                    </div>
                    <div>
                        <label class="form-label">Account Number</label>
                        <input type="text" name="account_number" class="form-ctrl">
                    </div>
                    <div>
                        <label class="form-label">IFSC Code</label>
                        <input type="text" name="ifsc" class="form-ctrl">
                    </div>
                </div>
                <div style="margin-top:20px; text-align:right;">
                    <button type="button" class="fund-btn fund-btn-outline" onclick="closeModal('modalReceiver')">Cancel</button>
                    <button type="submit" class="fund-btn fund-btn-dark">Save Receiver</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- TASK 4: MODAL PROJECT SINGLE SCREEN VIEW -->
<div id="modalProjectView" class="fund-modal">
    <div class="fund-modal-content" style="max-width:900px;">
        <div class="fund-modal-header">
            <h3 id="prjViewTitle">📁 Project Details</h3>
            <button class="fund-modal-close" onclick="closeModal('modalProjectView')">&times;</button>
        </div>
        <div class="fund-modal-body">
            <!-- Project Top Summary Cards -->
            <div style="display:grid; grid-template-columns: repeat(6, 1fr); gap:10px; margin-bottom:20px;">
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:10px; text-align:center;">
                    <div style="font-size:10px; font-weight:700; color:#64748b;">ASSIGNED</div>
                    <div style="font-size:14px; font-weight:900;" id="pvBudget">₹0</div>
                </div>
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:10px; text-align:center;">
                    <div style="font-size:10px; font-weight:700; color:#64748b;">ALLOCATED</div>
                    <div style="font-size:14px; font-weight:900; color:#3b82f6;" id="pvAllocated">₹0</div>
                </div>
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:10px; text-align:center;">
                    <div style="font-size:10px; font-weight:700; color:#64748b;">PAID</div>
                    <div style="font-size:14px; font-weight:900; color:#8b5cf6;" id="pvPaid">₹0</div>
                </div>
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:10px; text-align:center;">
                    <div style="font-size:10px; font-weight:700; color:#64748b;">UTILIZED</div>
                    <div style="font-size:14px; font-weight:900; color:#ec4899;" id="pvUtilized">₹0</div>
                </div>
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:10px; text-align:center;">
                    <div style="font-size:10px; font-weight:700; color:#64748b;">PENDING PROOF</div>
                    <div style="font-size:14px; font-weight:900; color:#f97316;" id="pvPendingProof">₹0</div>
                </div>
                <div style="background:#ecfdf5; border:1px solid #a7f3d0; border-radius:8px; padding:10px; text-align:center;">
                    <div style="font-size:10px; font-weight:700; color:#047857;">BALANCE</div>
                    <div style="font-size:14px; font-weight:900; color:#047857;" id="pvBalance">₹0</div>
                </div>
            </div>

            <!-- Project Sub Tabs -->
            <div style="display:flex; gap:10px; border-bottom:1px solid #e2e8f0; margin-bottom:16px;">
                <button class="fund-btn fund-btn-outline" onclick="switchProjectSubTab('overview')">Overview</button>
                <button class="fund-btn fund-btn-outline" onclick="switchProjectSubTab('payments')">Payments</button>
                <button class="fund-btn fund-btn-outline" onclick="switchProjectSubTab('expenses')">Expenses</button>
                <button class="fund-btn fund-btn-outline" onclick="switchProjectSubTab('documents')">Documents</button>
            </div>

            <div id="prjSubTab-overview">
                <div id="pvOverviewContent" style="font-size:13px; line-height:1.6; color:#334155;">Loading overview...</div>
            </div>
            <div id="prjSubTab-payments" style="display:none;">
                <table class="fund-table" id="pvPaymentsTable">
                    <thead><tr><th>Payment ID</th><th>Receiver</th><th>Amount</th><th>Date</th><th>Status</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
            <div id="prjSubTab-expenses" style="display:none;">
                <table class="fund-table" id="pvExpensesTable">
                    <thead><tr><th>Utilization ID</th><th>Category</th><th>Amount</th><th>Invoice</th><th>Status</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
            <div id="prjSubTab-documents" style="display:none;">
                <div id="pvDocsContent" style="font-size:13px;">No documents attached.</div>
            </div>
        </div>
    </div>
</div>

<!-- TASK 4: MODAL RECEIVER SINGLE SCREEN VIEW -->
<div id="modalReceiverView" class="fund-modal">
    <div class="fund-modal-content" style="max-width:850px;">
        <div class="fund-modal-header">
            <h3 id="rcvViewTitle">👤 Receiver Profile & Transaction History</h3>
            <button class="fund-modal-close" onclick="closeModal('modalReceiverView')">&times;</button>
        </div>
        <div class="fund-modal-body">
            <!-- Receiver Top Summary Cards -->
            <div style="display:grid; grid-template-columns: repeat(5, 1fr); gap:10px; margin-bottom:20px;">
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:10px; text-align:center;">
                    <div style="font-size:10px; font-weight:700; color:#64748b;">ALLOCATED</div>
                    <div style="font-size:14px; font-weight:900; color:#3b82f6;" id="rvAllocated">₹0</div>
                </div>
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:10px; text-align:center;">
                    <div style="font-size:10px; font-weight:700; color:#64748b;">PAID</div>
                    <div style="font-size:14px; font-weight:900; color:#8b5cf6;" id="rvPaid">₹0</div>
                </div>
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:10px; text-align:center;">
                    <div style="font-size:10px; font-weight:700; color:#64748b;">UTILIZED</div>
                    <div style="font-size:14px; font-weight:900; color:#ec4899;" id="rvUtilized">₹0</div>
                </div>
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:10px; text-align:center;">
                    <div style="font-size:10px; font-weight:700; color:#64748b;">PENDING PROOF</div>
                    <div style="font-size:14px; font-weight:900; color:#f97316;" id="rvPending">₹0</div>
                </div>
                <div style="background:#ecfdf5; border:1px solid #a7f3d0; border-radius:8px; padding:10px; text-align:center;">
                    <div style="font-size:10px; font-weight:700; color:#047857;">BALANCE</div>
                    <div style="font-size:14px; font-weight:900; color:#047857;" id="rvBalance">₹0</div>
                </div>
            </div>

            <h4 style="font-size:14px; font-weight:800; color:#0f172a; margin:0 0 12px;">Transaction History</h4>
            <div class="fund-table-wrap">
                <table class="fund-table" id="rvTxnTable">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Project</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ================= JAVASCRIPT LOGIC ================= -->
<script>
let globalFunds = [];
let globalAllocations = [];

// Indian Currency Formatter (supports negative overdraft balances)
function formatINR(amount) {
    amount = parseFloat(amount) || 0;
    const isNeg = amount < 0;
    const formatted = Math.abs(amount).toLocaleString('en-IN', { maximumFractionDigits: 2, minimumFractionDigits: 0 });
    return (isNeg ? '-₹' : '₹') + formatted;
}

// Modal Helpers
function openModal(id) {
    document.getElementById(id).classList.add('open');
}
function closeModal(id) {
    document.getElementById(id).classList.remove('open');
}

// Toggle Advanced Details
function toggleAdvancedDetails() {
    const box = document.getElementById('advancedFinancialDetails');
    const btn = document.getElementById('btnToggleAdvanced');
    if (box.style.display === 'block') {
        box.style.display = 'none';
        btn.innerHTML = '<i data-lucide="sliders"></i> View Advanced Financial Details 🔽';
    } else {
        box.style.display = 'block';
        btn.innerHTML = '<i data-lucide="sliders"></i> Hide Advanced Financial Details 🔼';
    }
    if (window.lucide) lucide.createIcons();
}

function toggleAccountingTabs() {
    const bar = document.getElementById('accountingTabsBar');
    if (bar) {
        bar.style.display = bar.style.display === 'none' || !bar.style.display ? 'flex' : 'none';
    }
}

// Tab Switching Logic
function switchTab(tabName) {
    document.querySelectorAll('.tab-pane').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.fund-tab-btn').forEach(btn => btn.classList.remove('active'));
    
    const targetPane = document.getElementById('tab-' + tabName);
    if (targetPane) targetPane.style.display = 'block';
    
    const activeBtn = Array.from(document.querySelectorAll('.fund-tab-btn')).find(b => b.getAttribute('onclick').includes(`'${tabName}'`));
    if (activeBtn) activeBtn.classList.add('active');

    // Load data
    if(tabName === 'sources') loadSources();
    else if(tabName === 'funds') loadFunds();
    else if(tabName === 'receipts') loadReceipts();
    else if(tabName === 'allocations') loadAllocations();
    else if(tabName === 'disbursements') loadDisbursements();
    else if(tabName === 'utilizations') loadUtilizations();
    else if(tabName === 'projects') loadProjects();
    else if(tabName === 'receivers') loadReceivers();
    else if(tabName === 'ledger') loadLedger();
    else if(tabName === 'audit') loadAudit();
}

// TASK 6: Render Status Badge Helper
function renderStatusBadge(status) {
    status = status || 'Pending';
    let cssClass = 'badge-pending';
    let icon = '🟡';
    
    if (['Approved', 'Active'].includes(status)) { cssClass = 'badge-approved'; icon = '🔵'; }
    else if (['Completed', 'Paid'].includes(status)) { cssClass = 'badge-paid'; icon = '🟣'; }
    else if (['Verified'].includes(status)) { cssClass = 'badge-verified'; icon = '🟢'; }
    else if (['Utilization Pending', 'Pending Proof'].includes(status)) { cssClass = 'badge-util-pending'; icon = '🟠'; }
    else if (['Closed'].includes(status)) { cssClass = 'badge-closed'; icon = '⚫'; }

    return `<span class="badge-status ${cssClass}">${icon} ${status}</span>`;
}

let allCashbookItems = [];
let filteredCashbookItems = [];
let cashbookCurrentPage = 1;
let cashbookPageSize = 10;

function loadCashbookTable() {
    fetch('api.php?api=get_fund_ledger')
    .then(r => r.json())
    .then(data => {
        if (data.success && data.ledger) {
            allCashbookItems = (data.ledger || []).slice().reverse();
            filterCashbookTable();
        }
    });
}

function filterCashbookTable() {
    const query = (document.getElementById('cashbookSearchInput')?.value || '').toLowerCase().trim();
    const typeFilter = document.getElementById('cashbookTypeFilter')?.value || 'ALL';

    filteredCashbookItems = allCashbookItems.filter(item => {
        const matchesType = (typeFilter === 'ALL') || 
            (typeFilter === 'Credit' && item.transaction_type === 'Credit') ||
            (typeFilter === 'Debit' && item.transaction_type === 'Debit');

        if (!matchesType) return false;
        if (!query) return true;

        const ref = (item.reference_no || '').toLowerCase();
        const party = (item.party_name || item.description || '').toLowerCase();
        const mode = (item.payment_mode || '').toLowerCase();
        const utr = (item.utr_number || '').toLowerCase();
        const date = (item.transaction_date || '').toLowerCase();
        const notes = (item.notes || '').toLowerCase();

        return ref.includes(query) || party.includes(query) || mode.includes(query) || utr.includes(query) || date.includes(query) || notes.includes(query);
    });

    cashbookCurrentPage = 1;
    renderCashbookPage();
}

function changeCashbookPageSize() {
    const el = document.getElementById('cashbookPageSize');
    if (el) cashbookPageSize = parseInt(el.value) || 10;
    cashbookCurrentPage = 1;
    renderCashbookPage();
}

function goToCashbookPage(page) {
    const totalPages = Math.ceil(filteredCashbookItems.length / cashbookPageSize) || 1;
    if (page < 1) page = 1;
    if (page > totalPages) page = totalPages;
    cashbookCurrentPage = page;
    renderCashbookPage();
}

function renderCashbookPage() {
    const totalItems = filteredCashbookItems.length;
    const totalPages = Math.ceil(totalItems / cashbookPageSize) || 1;
    if (cashbookCurrentPage > totalPages) cashbookCurrentPage = totalPages;

    const startIdx = (cashbookCurrentPage - 1) * cashbookPageSize;
    const endIdx = Math.min(startIdx + cashbookPageSize, totalItems);
    const pageItems = filteredCashbookItems.slice(startIdx, endIdx);

    let html = '';
    if (pageItems.length > 0) {
        pageItems.forEach(item => {
            const isCredit = item.transaction_type === 'Credit';
            const typeBadge = isCredit 
                ? '<span class="badge-status badge-verified">💰 CAPITAL CREDIT</span>' 
                : '<span class="badge-status badge-util-pending" style="background:#fee2e2; color:#991b1b; border:1px solid #fca5a5;">📤 PAYOUT DEBIT</span>';
            const amtColor = isCredit ? '#10b981' : '#ef4444';
            const amtPrefix = isCredit ? '+' : '-';

            const pName = item.party_name || item.description || 'Capital Transaction';
            const pMode = item.payment_mode || 'Bank Transfer';
            const utrNo = item.utr_number ? ` | UTR: <strong>${item.utr_number}</strong>` : '';
            const notes = item.notes ? ` <br><small style="color:#64748b;">📝 ${item.notes}</small>` : '';

            let proofHtml = '';
            if (item.proof_path) {
                proofHtml = `<a href="${item.proof_path}" target="_blank" class="fund-btn" style="background:#ecfdf5; color:#047857; border:1px solid #a7f3d0; padding:4px 10px; font-size:11px; font-weight:800; border-radius:6px; display:inline-flex; align-items:center; gap:4px; text-decoration:none;"><i data-lucide="file-text" style="width:13px;height:13px;"></i> 📄 View Proof</a>`;
            } else {
                proofHtml = `<span style="font-size:11px; color:#94a3b8; font-weight:600;">No Proof Uploaded</span>`;
            }

            let actionHtml = '';
            if (item.can_modify) {
                actionHtml = `
                    <div style="display:flex; gap:6px; align-items:center; flex-wrap:wrap;">
                        <button class="fund-btn fund-btn-outline" style="padding:4px 8px; font-size:11px; font-weight:700;" onclick="openTxnDetailsModal('${item.reference_no}')">🔍 Details</button>
                        <button class="fund-btn fund-btn-outline" style="padding:4px 8px; font-size:11px; font-weight:700;" onclick="openEditTxnModal('${item.reference_no}', ${item.source_id})">✏️ Edit (${item.hours_remaining}h left)</button>
                        <button class="fund-btn" style="background:#fee2e2; color:#dc2626; border:1px solid #fca5a5; padding:4px 8px; font-size:11px; font-weight:700; border-radius:6px; cursor:pointer;" onclick="deleteTxn('${item.reference_no}', ${item.source_id})">🗑️ Delete</button>
                    </div>
                `;
            } else {
                actionHtml = `
                    <div style="display:flex; gap:6px; align-items:center;">
                        <button class="fund-btn fund-btn-outline" style="padding:4px 8px; font-size:11px; font-weight:700;" onclick="openTxnDetailsModal('${item.reference_no}')">🔍 Details</button>
                        <span style="font-size:11px; color:#64748b; font-weight:700; background:#f1f5f9; padding:4px 8px; border-radius:6px; border:1px solid #cbd5e1; display:inline-flex; align-items:center; gap:4px;">🔒 Locked (>24h)</span>
                    </div>
                `;
            }

            html += `
                <tr>
                    <td>
                        <strong style="color:#0f172a; font-size:13px;">${item.reference_no || '-'}</strong>
                        <div style="font-size:11px; color:#64748b; font-weight:600;">${item.transaction_date || '-'}</div>
                    </td>
                    <td>${typeBadge}</td>
                    <td>
                        <div style="font-weight:800; color:#0f172a; font-size:13.5px;">${pName}</div>
                        <div style="font-size:11.5px; color:#475569; margin-top:2px;">
                            <span style="background:#f1f5f9; border:1px solid #cbd5e1; padding:2px 6px; border-radius:4px; font-weight:700;">💳 ${pMode}</span>${utrNo}
                        </div>
                        ${notes}
                    </td>
                    <td style="font-weight:900; font-size:15px; color:${amtColor};">${amtPrefix}${formatINR(isCredit ? item.credit_amount : item.debit_amount)}</td>
                    <td>${proofHtml}</td>
                    <td><span class="badge-status badge-approved">Approved</span></td>
                    <td>${actionHtml}</td>
                </tr>
            `;
        });
    } else {
        html = '<tr><td colspan="7" style="text-align:center; padding:24px; color:#64748b;">No matching financial transactions found.</td></tr>';
    }

    const tbody = document.querySelector('#tableCashbook tbody');
    if (tbody) tbody.innerHTML = html;
    if (window.lucide) lucide.createIcons();

    // Render Page Info
    const infoText = totalItems > 0 
        ? `Showing <strong>${startIdx + 1}</strong> to <strong>${endIdx}</strong> of <strong>${totalItems}</strong> entries`
        : `Showing 0 to 0 of 0 entries`;
    const elInfo = document.getElementById('cashbookPageInfo');
    if (elInfo) elInfo.innerHTML = infoText;

    // Render Pagination Controls
    let navHtml = '';
    const prevDisabled = cashbookCurrentPage <= 1 ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : '';
    const nextDisabled = cashbookCurrentPage >= totalPages ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : '';

    navHtml += `<button class="fund-btn fund-btn-outline" style="padding:4px 10px; font-size:11.5px; font-weight:700;" ${prevDisabled} onclick="goToCashbookPage(${cashbookCurrentPage - 1})">◀ Previous</button>`;

    let startPage = Math.max(1, cashbookCurrentPage - 2);
    let endPage = Math.min(totalPages, startPage + 4);
    if (endPage - startPage < 4) startPage = Math.max(1, endPage - 4);

    for (let p = startPage; p <= endPage; p++) {
        const isCurrent = p === cashbookCurrentPage;
        const btnStyle = isCurrent 
            ? 'background:#0f172a; color:#ffffff; font-weight:800; border:1.5px solid #0f172a;'
            : 'background:#ffffff; color:#334155; font-weight:700; border:1px solid #cbd5e1;';
        navHtml += `<button class="fund-btn" style="padding:4px 10px; font-size:11.5px; ${btnStyle}" onclick="goToCashbookPage(${p})">${p}</button>`;
    }

    navHtml += `<button class="fund-btn fund-btn-outline" style="padding:4px 10px; font-size:11.5px; font-weight:700;" ${nextDisabled} onclick="goToCashbookPage(${cashbookCurrentPage + 1})">Next ▶</button>`;

    const elNav = document.getElementById('cashbookPaginationNav');
    if (elNav) elNav.innerHTML = navHtml;
}

function openTxnDetailsModal(refNo) {
    fetch('api.php?api=get_fund_ledger')
    .then(r => r.json())
    .then(data => {
        const item = (data.ledger || []).find(l => l.reference_no === refNo);
        if (!item) {
            Swal.fire('Error', 'Transaction details not found', 'error');
            return;
        }

        const isCredit = item.transaction_type === 'Credit';
        const d = item.txn_details || {};
        const proofBtn = item.proof_path 
            ? `<a href="${item.proof_path}" target="_blank" style="background:#10b981; color:#ffffff; padding:9px 18px; border-radius:8px; text-decoration:none; font-weight:800; font-size:13px; display:inline-flex; align-items:center; gap:6px;"><i data-lucide="external-link"></i> 📄 Open & Download Attached Proof Document</a>` 
            : `<span style="color:#94a3b8; font-weight:600;">No Proof Uploaded</span>`;

        Swal.fire({
            title: `<div style="font-size:18px; font-weight:900; color:#0f172a;">📜 Financial Transaction Certificate</div>`,
            html: `
                <div style="text-align:left; font-size:13px; font-family:sans-serif; background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:16px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; padding-bottom:10px; border-bottom:1.5px solid #cbd5e1;">
                        <span style="font-size:15px; font-weight:800; color:#0f172a;">${refNo}</span>
                        <span style="font-size:16px; font-weight:900; color:${isCredit ? '#10b981' : '#ef4444'};">${isCredit ? '+' : '-'}${formatINR(isCredit ? item.credit_amount : item.debit_amount)}</span>
                    </div>

                    <table style="width:100%; border-collapse:collapse; font-size:12.5px;">
                        <tr><td style="padding:6px 0; color:#64748b; font-weight:700;">Transaction Type:</td><td style="font-weight:800; color:#0f172a;">${isCredit ? '💰 Capital Receipt (Credit)' : '📤 Disbursement Payment (Debit)'}</td></tr>
                        <tr><td style="padding:6px 0; color:#64748b; font-weight:700;">Party / Beneficiary:</td><td style="font-weight:800; color:#0f172a;">${item.party_name || '-'}</td></tr>
                        <tr><td style="padding:6px 0; color:#64748b; font-weight:700;">Transaction Date:</td><td style="font-weight:700; color:#0f172a;">${item.transaction_date || '-'}</td></tr>
                        <tr><td style="padding:6px 0; color:#64748b; font-weight:700;">Payment Mode:</td><td style="font-weight:700; color:#0f172a;">${item.payment_mode || 'Bank Transfer'}</td></tr>
                        <tr><td style="padding:6px 0; color:#64748b; font-weight:700;">UTR / Ref / Cheque No:</td><td style="font-weight:700; color:#0f172a;">${item.utr_number || 'N/A'}</td></tr>
                        <tr><td style="padding:6px 0; color:#64748b; font-weight:700;">Bank / Acc Info:</td><td style="font-weight:700; color:#0f172a;">${d.bank_name || ''} ${d.bank_account || ''} ${d.account_number || ''}</td></tr>
                        <tr><td style="padding:6px 0; color:#64748b; font-weight:700;">Purpose / Notes:</td><td style="font-weight:700; color:#0f172a;">${item.notes || 'N/A'}</td></tr>
                        <tr><td style="padding:6px 0; color:#64748b; font-weight:700;">Recorded By:</td><td style="font-weight:700; color:#0f172a;">${d.created_by || item.created_by || 'Admin'}</td></tr>
                        <tr><td style="padding:6px 0; color:#64748b; font-weight:700;">Security 24h Policy:</td><td style="font-weight:700; color:${item.can_modify ? '#d97706' : '#64748b'};">${item.can_modify ? `Editable (${item.hours_remaining} hours left)` : 'Locked (>24h)'}</td></tr>
                    </table>

                    <div style="margin-top:16px; padding-top:12px; border-top:1px solid #cbd5e1; text-align:center;">
                        ${proofBtn}
                    </div>
                </div>
            `,
            confirmButtonText: 'Close Certificate',
            confirmButtonColor: '#0f172a'
        });
        if (window.lucide) lucide.createIcons();
    });
}

function deleteTxn(refNo, sourceId) {
    Swal.fire({
        title: 'Delete Financial Transaction?',
        html: `Are you sure you want to delete <strong>${refNo}</strong>?<br><span style="font-size:12px; color:#dc2626;">This will update your live operating balance automatically. (Permitted within 24 hours of entry)</span>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, Delete Transaction'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('ref_no', refNo);
            formData.append('id', sourceId);
            fetch('api.php?api=delete_fund_transaction', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    Swal.fire('Deleted!', res.message, 'success');
                    loadDashboardStats();
                    loadDropdownData();
                } else {
                    Swal.fire('Error', res.error || 'Failed to delete transaction.', 'error');
                }
            })
            .catch(err => Swal.fire('Error', 'Server request failed.', 'error'));
        }
    });
}

function openEditTxnModal(refNo, sourceId) {
    fetch(`api.php?api=get_fund_ledger`)
    .then(r => r.json())
    .then(data => {
        const item = (data.ledger || []).find(l => l.reference_no === refNo);
        if (!item || !item.can_modify) {
            Swal.fire('Locked', 'Security Policy: Financial transactions cannot be edited after 24 hours.', 'error');
            return;
        }

        const isCredit = item.transaction_type === 'Credit';
        const currentAmt = isCredit ? item.credit_amount : item.debit_amount;
        const details = item.txn_details || {};

        Swal.fire({
            title: `✏️ Edit Transaction (${refNo})`,
            html: `
                <div style="text-align:left; font-size:13px; font-family:sans-serif;">
                    <div style="margin-bottom:12px;">
                        <label style="font-weight:700; display:block; margin-bottom:4px; color:#0f172a;">Amount (₹) *</label>
                        <input id="swalEditAmount" type="number" step="0.01" class="swal2-input" value="${currentAmt}" style="margin:0; width:100%; box-sizing:border-box; font-weight:800; color:#0f172a;">
                    </div>
                    <div style="margin-bottom:12px;">
                        <label style="font-weight:700; display:block; margin-bottom:4px; color:#0f172a;">Date *</label>
                        <input id="swalEditDate" type="date" class="swal2-input" value="${details.received_date || details.payment_date || item.transaction_date || ''}" style="margin:0; width:100%; box-sizing:border-box;">
                    </div>
                    <div style="margin-bottom:12px;">
                        <label style="font-weight:700; display:block; margin-bottom:4px; color:#0f172a;">Payment Mode</label>
                        <input id="swalEditMode" type="text" class="swal2-input" value="${details.payment_mode || 'Bank Transfer'}" style="margin:0; width:100%; box-sizing:border-box;">
                    </div>
                    <div style="margin-bottom:12px;">
                        <label style="font-weight:700; display:block; margin-bottom:4px; color:#0f172a;">UTR / Reference No.</label>
                        <input id="swalEditUtr" type="text" class="swal2-input" value="${details.utr_number || ''}" style="margin:0; width:100%; box-sizing:border-box;">
                    </div>
                    <div style="margin-bottom:12px;">
                        <label style="font-weight:700; display:block; margin-bottom:4px; color:#0f172a;">${isCredit ? 'Purpose / Description' : 'Paid To / Beneficiary Name'}</label>
                        <input id="swalEditDesc" type="text" class="swal2-input" value="${details.receiver_name || details.description || details.payment_purpose || ''}" style="margin:0; width:100%; box-sizing:border-box;">
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Save Changes',
            preConfirm: () => {
                const amt = document.getElementById('swalEditAmount').value;
                if (!amt || parseFloat(amt) <= 0) {
                    Swal.showValidationMessage('Please enter a valid amount (> 0)');
                    return false;
                }
                return {
                    amount: amt,
                    date: document.getElementById('swalEditDate').value,
                    mode: document.getElementById('swalEditMode').value,
                    utr: document.getElementById('swalEditUtr').value,
                    desc: document.getElementById('swalEditDesc').value
                };
            }
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                const formData = new FormData();
                formData.append('ref_no', refNo);
                formData.append('id', sourceId || item.source_id);
                formData.append('amount', result.value.amount);
                formData.append('amount_received', result.value.amount);
                formData.append('payment_amount', result.value.amount);
                formData.append('payment_date', result.value.date);
                formData.append('received_date', result.value.date);
                formData.append('payment_mode', result.value.mode);
                formData.append('utr_number', result.value.utr);
                formData.append('description', result.value.desc);
                formData.append('payment_purpose', result.value.desc);
                formData.append('receiver_name', result.value.desc);

                fetch('api.php?api=update_fund_transaction', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        Swal.fire('Updated!', res.message, 'success');
                        loadDashboardStats();
                        loadDropdownData();
                    } else {
                        Swal.fire('Error', res.error || 'Failed to update transaction.', 'error');
                    }
                });
            }
        });
    });
}

// Load KPI Stats & Recent Activity
function loadDashboardStats() {
    fetch('api.php?api=get_fund_dashboard_stats')
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            const st = data.stats;
            globalAvailableBalance = parseFloat(st.available_balance) || 0;
            const elRec = document.getElementById('kpiReceived');
            const elDisb = document.getElementById('kpiDisbursed');
            const elAvail = document.getElementById('kpiAvailable');
            if (elRec) elRec.innerText = formatINR(st.total_received);
            if (elDisb) elDisb.innerText = formatINR(st.total_disbursed);
            if (elAvail) {
                elAvail.innerText = formatINR(st.available_balance);
                const cardAvail = document.getElementById('cardKpiAvailable');
                const subAvail = document.getElementById('kpiAvailableSub');
                if (st.available_balance < 0) {
                    elAvail.style.color = '#f87171'; // Red highlight for negative balance / overdraft
                    if (cardAvail) cardAvail.style.border = '1.5px solid #ef4444';
                    if (subAvail) {
                        subAvail.innerText = 'Overdraft / Cash Deficit';
                        subAvail.style.color = '#fca5a5';
                    }
                } else {
                    elAvail.style.color = '#ffffff';
                    if (cardAvail) cardAvail.style.border = '1.5px solid #334155';
                    if (subAvail) {
                        subAvail.innerText = 'Liquid Cash Balance Available';
                        subAvail.style.color = '#cbd5e1';
                    }
                }
            }

            const elSRec = document.getElementById('sumReceived');
            const elSAlloc = document.getElementById('sumAllocated');
            const elSDisb = document.getElementById('sumDisbursed');
            const elSUtil = document.getElementById('sumUtilized');
            const elSAvail = document.getElementById('sumAvailable');
            if (elSRec) elSRec.innerText = formatINR(st.total_received);
            if (elSAlloc) elSAlloc.innerText = formatINR(st.total_allocated);
            if (elSDisb) elSDisb.innerText = formatINR(st.total_disbursed);
            if (elSUtil) elSUtil.innerText = formatINR(st.total_utilized);
            if (elSAvail) {
                elSAvail.innerText = formatINR(st.available_balance);
                elSAvail.style.color = (st.available_balance < 0) ? '#dc2626' : '#047857';
            }

            const elSanc = document.getElementById('kpiSanctioned');
            const elAlloc = document.getElementById('kpiAllocated');
            const elUnutil = document.getElementById('kpiUnutilized');
            if (elSanc) elSanc.innerText = formatINR(st.total_sanctioned);
            if (elAlloc) elAlloc.innerText = formatINR(st.total_allocated);
            if (elUnutil) elUnutil.innerText = formatINR(st.unutilized_fund);

            loadCashbookTable();

            // Attention alerts
            if(st.attention_required) {
                const elP = document.getElementById('alertPendingProofCount');
                if (elP) elP.innerText = st.attention_required.payments_awaiting_utilization_proof || 0;
                const elV = document.getElementById('alertPendingVerifyCount');
                if (elV) elV.innerText = st.attention_required.bills_awaiting_verification || 0;
                const elU = document.getElementById('alertUnpaidAllocCount');
                if (elU) elU.innerText = st.attention_required.allocations_awaiting_payment || 0;
                const elL = document.getElementById('alertProjectNearLimitCount');
                if (elL) elL.innerText = st.attention_required.projects_near_budget_limit || 0;
            }

            // Fund Journey Steps
            if(data.fund_journey) {
                const fj = data.fund_journey;
                const elFR = document.getElementById('flowReceived');
                if (elFR) elFR.innerText = formatINR(fj.received);
                const elFA = document.getElementById('flowAllocated');
                if (elFA) elFA.innerText = formatINR(fj.allocated);
                const elFD = document.getElementById('flowDisbursed');
                if (elFD) elFD.innerText = formatINR(fj.disbursed);
                const elFU = document.getElementById('flowUtilized');
                if (elFU) elFU.innerText = formatINR(fj.utilized);
                const elFP = document.getElementById('flowPendingProof');
                if (elFP) elFP.innerText = formatINR(fj.pending_utilization);
            }

            // Activity Feed
            const elFeed = document.getElementById('activityFeedList');
            if (elFeed) {
                if(data.activity_feed && data.activity_feed.length) {
                    let html = '';
                    data.activity_feed.forEach(act => {
                        html += `
                            <div style="display:flex; align-items:center; justify-content:space-between; padding:10px 12px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px;">
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <span style="font-size:18px;">${act.icon}</span>
                                    <div>
                                        <div style="font-size:13px; font-weight:700; color:#0f172a;">${act.text}</div>
                                        <div style="font-size:11px; color:#64748b;">${act.date}</div>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    elFeed.innerHTML = html;
                } else {
                    elFeed.innerHTML = '<div style="color:#64748b; font-size:13px;">No recent activities logged.</div>';
                }
            }
        }
    })
    .catch(err => console.log('Stats error:', err));
}

// Load Fund Journey timeline specifically
function loadFundJourney() {
    const fid = document.getElementById('journeyFundSelect').value;
    fetch(`api.php?api=get_fund_journey&fund_id=${fid}`)
    .then(r => r.json())
    .then(data => {
        if(data.success && data.journey) {
            const j = data.journey;
            document.getElementById('flowReceived').innerText = formatINR(j.received);
            document.getElementById('flowAllocated').innerText = formatINR(j.allocated);
            document.getElementById('flowDisbursed').innerText = formatINR(j.disbursed);
            document.getElementById('flowUtilized').innerText = formatINR(j.utilized);
            document.getElementById('flowPendingProof').innerText = formatINR(j.pending_utilization);
        }
    });
}

// Populate Dropdowns
function loadDropdownData() {
    fetch('api.php?api=get_fund_sources')
    .then(r => r.json())
    .then(data => {
        if(data.sources && data.sources.length > 0) {
            let html = '';
            let praveenSelected = false;
            // First loop to see if Praveen Sir exists
            data.sources.forEach(s => {
                let selectedAttr = '';
                if (!praveenSelected && s.source_name.toLowerCase().includes('praveen')) {
                    selectedAttr = 'selected';
                    praveenSelected = true;
                }
                html += `<option value="${s.id}" ${selectedAttr}>${s.source_name}</option>`;
            });
            // If Praveen wasn't found, add a placeholder option at top
            if (!praveenSelected) {
                html = '<option value="" selected>Select Source</option>' + html;
            }
            document.getElementById('selectFundSource').innerHTML = html;
        } else {
            document.getElementById('selectFundSource').innerHTML = '<option value="1" selected>Praveen Sir (Head Office)</option>';
        }
    });

    fetch('api.php?api=get_funds')
    .then(r => r.json())
    .then(data => {
        if(data.funds) {
            globalFunds = data.funds;
            let html = '<option value="">Select Fund Master</option>';
            let jHtml = '<option value="0">All Combined Fund Flow</option>';
            let lHtml = '<option value="0">All Funds Ledger</option>';
            data.funds.forEach(f => {
                html += `<option value="${f.id}">${f.fund_name} (Avail: ${formatINR(f.available_balance)})</option>`;
                jHtml += `<option value="${f.id}">${f.fund_name}</option>`;
                lHtml += `<option value="${f.id}">${f.fund_name}</option>`;
            });
            document.getElementById('selectReceiptFund').innerHTML = html;
            document.getElementById('selectAllocFund').innerHTML = html;
            document.getElementById('selectUtilFund').innerHTML = html;
            document.getElementById('journeyFundSelect').innerHTML = jHtml;
            document.getElementById('ledgerFundFilter').innerHTML = lHtml;
        }
    });

    fetch('api.php?api=get_projects')
    .then(r => r.json())
    .then(data => {
        if(data.projects) {
            let html = '<option value="">Select Project / Purpose</option>';
            data.projects.forEach(p => html += `<option value="${p.id}">${p.project_name}</option>`);
            if (document.getElementById('selectAllocProject')) document.getElementById('selectAllocProject').innerHTML = html;
            if (document.getElementById('selectDisbProject')) document.getElementById('selectDisbProject').innerHTML = html;
        }
    });

    fetch('api.php?api=get_receivers')
    .then(r => r.json())
    .then(data => {
        if(data.receivers) {
            let html = '<option value="">Select Receiver</option>';
            let listHtml = '';
            data.receivers.forEach(r => {
                html += `<option value="${r.id}">${r.name} (${r.receiver_type})</option>`;
                listHtml += `<option value="${r.name}">${r.name} (${r.receiver_type})</option>`;
            });
            if (document.getElementById('selectAllocReceiver')) document.getElementById('selectAllocReceiver').innerHTML = html;
            if (document.getElementById('selectDisbReceiver')) document.getElementById('selectDisbReceiver').innerHTML = html;
            if (document.getElementById('receiversList')) document.getElementById('receiversList').innerHTML = listHtml;
        }
    });

    fetch('api.php?api=get_fund_allocations')
    .then(r => r.json())
    .then(data => {
        if(data.allocations) {
            globalAllocations = data.allocations;
            let html = '<option value="0" selected>Direct Payment (No Pre-Allocation Required)</option>';
            if (data.allocations && data.allocations.length > 0) {
                data.allocations.forEach(a => {
                    html += `<option value="${a.id}">${a.allocation_id} - ${a.fund_name} (Rem: ${formatINR(a.remaining_allocation)})</option>`;
                });
            }
            document.getElementById('selectDisbAllocation').innerHTML = html;
        }
    });
}

// TASK 2: Real-time Calculator & Validation for Allocation
function updateAllocFundBalance() {
    const fid = document.getElementById('selectAllocFund').value;
    const fund = globalFunds.find(f => f.id == fid);
    const availText = document.getElementById('allocAvailableBal');
    if (fund) {
        availText.innerText = formatINR(fund.available_balance);
    } else {
        availText.innerText = '₹0';
    }
    validateAllocAmount();
}

function validateAllocAmount() {
    const fid = document.getElementById('selectAllocFund').value;
    const amount = parseFloat(document.getElementById('inputAllocAmount').value) || 0;
    const fund = globalFunds.find(f => f.id == fid);
    
    const calcAmt = document.getElementById('allocCalcAmount');
    const calcRem = document.getElementById('allocCalcRemaining');
    const alertBox = document.getElementById('allocValAlert');
    const btn = document.getElementById('btnSubmitAlloc');

    calcAmt.innerText = formatINR(amount);
    btn.disabled = false;
    btn.style.opacity = '1';
    
    if (fund) {
        const remaining = fund.available_balance - amount;
        calcRem.innerText = formatINR(remaining);

        if (amount > fund.available_balance) {
            alertBox.style.display = 'block';
            alertBox.style.background = '#fffbeb';
            alertBox.style.border = '1px solid #f59e0b';
            alertBox.style.color = '#92400e';
            alertBox.innerHTML = `⚠️ <strong>Notice:</strong> Allocation (${formatINR(amount)}) exceeds available balance (${formatINR(fund.available_balance)}). Net remaining will be <strong>${formatINR(remaining)}</strong>. Allocation is allowed.`;
        } else {
            alertBox.style.display = 'none';
        }
    } else {
        calcRem.innerText = '₹0';
        alertBox.style.display = 'none';
    }
}

function submitAllocationForm(e) {
    e.preventDefault();
    validateAllocAmount();
    submitForm(e, 'save_fund_allocation', 'modalAllocation');
}

// TASK 2: Auto-Fill & Real-Time Validation for Disbursement Payment
function updateDisbAllocRemaining() {
    const aid = document.getElementById('selectDisbAllocation').value;
    const alloc = globalAllocations.find(a => a.id == aid);
    if (alloc) {
        document.getElementById('disbTotalApproved').innerText = formatINR(alloc.allocation_amount);
        document.getElementById('disbRemainingInfo').innerText = formatINR(alloc.remaining_allocation);
        if (alloc.receiver_id) {
            document.getElementById('selectDisbReceiver').value = alloc.receiver_id;
        }
    } else {
        document.getElementById('disbTotalApproved').innerText = '₹0';
        document.getElementById('disbRemainingInfo').innerText = '₹0';
    }
    validateDisbAmount();
}

let globalAvailableBalance = 0;

function validateDisbAmount() {
    const aid = document.getElementById('selectDisbAllocation').value;
    const amount = parseFloat(document.getElementById('inputDisbAmount').value) || 0;
    const alloc = globalAllocations.find(a => a.id == aid);
    const alertBox = document.getElementById('disbValAlert');
    const btn = document.getElementById('btnSubmitDisb');

    // Never block submission! Keep button active
    btn.disabled = false;
    btn.style.opacity = '1';

    if (amount <= 0) {
        alertBox.style.display = 'none';
        return;
    }

    if (amount > globalAvailableBalance) {
        const netAfter = globalAvailableBalance - amount;
        alertBox.style.display = 'block';
        alertBox.style.background = '#fffbeb';
        alertBox.style.border = '1px solid #f59e0b';
        alertBox.style.color = '#92400e';
        alertBox.innerHTML = `⚠️ <strong>Balance Notice:</strong> Payment (${formatINR(amount)}) exceeds available balance (${formatINR(globalAvailableBalance)}). Net balance after payment will be <strong>${formatINR(netAfter)}</strong> (Overdraft / Deficit). Entry is permitted.`;
    } else if (alloc && amount > alloc.remaining_allocation) {
        alertBox.style.display = 'block';
        alertBox.style.background = '#fffbeb';
        alertBox.style.border = '1px solid #f59e0b';
        alertBox.style.color = '#92400e';
        alertBox.innerHTML = `ℹ️ <strong>Allocation Notice:</strong> Payment (${formatINR(amount)}) exceeds remaining allocation (${formatINR(alloc.remaining_allocation)}). Entry is permitted.`;
    } else {
        alertBox.style.display = 'none';
    }
}

function submitDisbursementForm(e) {
    e.preventDefault();
    validateDisbAmount();
    submitForm(e, 'save_fund_disbursement', 'modalDisbursement');
}

// Generic Submit Handler via fetch
function submitForm(e, apiAction, modalId) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);

    fetch(`api.php?api=${apiAction}`, {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if(res.success) {
            Swal.fire('Success', res.message || 'Saved successfully.', 'success');
            closeModal(modalId);
            form.reset();
            loadDashboardStats();
            loadDropdownData();
            const activeTabBtn = document.querySelector('.fund-tab-btn.active');
            if (activeTabBtn) {
                const match = activeTabBtn.getAttribute('onclick').match(/'([^']+)'/);
                if (match) switchTab(match[1]);
            }
        } else {
            Swal.fire('Validation Error', res.error || 'Operation failed.', 'error');
        }
    })
    .catch(err => Swal.fire('Error', 'Server request failed.', 'error'));
}

// TASK 4: Single Screen View for Project
function openProjectView(projectId) {
    fetch(`api.php?api=get_project_detail&id=${projectId}`)
    .then(r => r.json())
    .then(res => {
        if(res.success) {
            const p = res.project;
            const s = res.summary;
            document.getElementById('prjViewTitle').innerText = `📁 ${p.project_name} (${p.project_id})`;
            document.getElementById('pvBudget').innerText = formatINR(s.budget);
            document.getElementById('pvAllocated').innerText = formatINR(s.allocated);
            document.getElementById('pvPaid').innerText = formatINR(s.paid);
            document.getElementById('pvUtilized').innerText = formatINR(s.utilized);
            document.getElementById('pvPendingProof').innerText = formatINR(s.pending_proof);
            document.getElementById('pvBalance').innerText = formatINR(s.balance);

            document.getElementById('pvOverviewContent').innerHTML = `
                <p><strong>Code / Scheme:</strong> ${p.project_code || '-'} (${p.scheme || 'N/A'})</p>
                <p><strong>Department:</strong> ${p.department || '-'}</p>
                <p><strong>Manager:</strong> ${p.project_manager || '-'}</p>
                <p><strong>Status:</strong> ${renderStatusBadge(p.status)}</p>
            `;

            let payHtml = '';
            if(res.payments && res.payments.length) {
                res.payments.forEach(pay => {
                    payHtml += `<tr><td>${pay.payment_id}</td><td>${pay.receiver_name}</td><td>${formatINR(pay.payment_amount)}</td><td>${pay.payment_date}</td><td>${renderStatusBadge(pay.status)}</td></tr>`;
                });
            } else payHtml = '<tr><td colspan="5">No payments found.</td></tr>';
            document.querySelector('#pvPaymentsTable tbody').innerHTML = payHtml;

            let expHtml = '';
            if(res.expenses && res.expenses.length) {
                res.expenses.forEach(exp => {
                    expHtml += `<tr><td>${exp.utilization_id}</td><td>${exp.expense_category}</td><td>${formatINR(exp.expense_amount)}</td><td>${exp.invoice_number || '-'}</td><td>${renderStatusBadge(exp.verification_status)}</td></tr>`;
                });
            } else expHtml = '<tr><td colspan="5">No expenses logged.</td></tr>';
            document.querySelector('#pvExpensesTable tbody').innerHTML = expHtml;

            openModal('modalProjectView');
        }
    });
}

function switchProjectSubTab(tab) {
    document.getElementById('prjSubTab-overview').style.display = tab === 'overview' ? 'block' : 'none';
    document.getElementById('prjSubTab-payments').style.display = tab === 'payments' ? 'block' : 'none';
    document.getElementById('prjSubTab-expenses').style.display = tab === 'expenses' ? 'block' : 'none';
    document.getElementById('prjSubTab-documents').style.display = tab === 'documents' ? 'block' : 'none';
}

// TASK 4: Single Screen View for Receiver
function openReceiverView(receiverId) {
    fetch(`api.php?api=get_receiver_detail&id=${receiverId}`)
    .then(r => r.json())
    .then(res => {
        if(res.success) {
            const rcv = res.receiver;
            const s = res.summary;
            document.getElementById('rcvViewTitle').innerText = `👤 ${rcv.name} (${rcv.receiver_type})`;
            document.getElementById('rvAllocated').innerText = formatINR(s.allocated);
            document.getElementById('rvPaid').innerText = formatINR(s.paid);
            document.getElementById('rvUtilized').innerText = formatINR(s.utilized);
            document.getElementById('rvPending').innerText = formatINR(s.pending_proof);
            document.getElementById('rvBalance').innerText = formatINR(s.balance);

            let txnHtml = '';
            if(res.transactions && res.transactions.length) {
                res.transactions.forEach(t => {
                    txnHtml += `<tr>
                        <td>${t.txn_date}</td>
                        <td><strong>${t.type}</strong></td>
                        <td>${t.project_name || '-'}</td>
                        <td>${formatINR(t.amount)}</td>
                        <td>${renderStatusBadge(t.status)}</td>
                    </tr>`;
                });
            } else txnHtml = '<tr><td colspan="5">No transaction history.</td></tr>';
            document.querySelector('#rvTxnTable tbody').innerHTML = txnHtml;

            openModal('modalReceiverView');
        }
    });
}

// Data Loaders
function loadSources() {
    fetch('api.php?api=get_fund_sources')
    .then(r => r.json())
    .then(data => {
        let html = '';
        if(data.sources && data.sources.length) {
            data.sources.forEach(s => {
                html += `<tr>
                    <td><strong>${s.source_id}</strong></td>
                    <td>${s.source_name}</td>
                    <td><span class="badge-status badge-approved">${s.source_type}</span></td>
                    <td>${s.organization_name || '-'}</td>
                    <td>${s.department || '-'}</td>
                    <td>${s.contact_person || '-'}</td>
                    <td>${s.mobile || ''} <br><small>${s.email || ''}</small></td>
                    <td>${renderStatusBadge(s.status)}</td>
                </tr>`;
            });
        } else { html = '<tr><td colspan="8" style="text-align:center;">No sources found.</td></tr>'; }
        document.querySelector('#tableSources tbody').innerHTML = html;
    });
}

function loadFunds() {
    fetch('api.php?api=get_funds')
    .then(r => r.json())
    .then(data => {
        let html = '';
        if(data.funds && data.funds.length) {
            data.funds.forEach(f => {
                const docLink = f.sanction_letter_path ? `<a href="${f.sanction_letter_path}" target="_blank" class="fund-btn fund-btn-outline" style="padding:3px 6px;font-size:10px;">📄 Letter</a>` : '-';
                html += `<tr>
                    <td><strong>${f.fund_id}</strong></td>
                    <td>${f.fund_name}</td>
                    <td>${f.source_name || '-'}</td>
                    <td>${f.sanction_number || '-'} <br><small>${f.sanction_date || ''}</small></td>
                    <td><strong>${formatINR(f.sanctioned_amount)}</strong></td>
                    <td style="color:#10b981;">${formatINR(f.total_received)}</td>
                    <td style="color:#8b5cf6;">${formatINR(f.total_disbursed)}</td>
                    <td><strong style="color:#047857;">${formatINR(f.available_balance)}</strong></td>
                    <td>${renderStatusBadge(f.status)}</td>
                    <td>${docLink}</td>
                </tr>`;
            });
        } else { html = '<tr><td colspan="10" style="text-align:center;">No sanctioned funds found.</td></tr>'; }
        document.querySelector('#tableFunds tbody').innerHTML = html;
    });
}

function loadReceipts() {
    fetch('api.php?api=get_fund_receipts')
    .then(r => r.json())
    .then(data => {
        let html = '';
        if(data.receipts && data.receipts.length) {
            data.receipts.forEach(r => {
                const proof = r.receipt_proof_path ? `<a href="${r.receipt_proof_path}" target="_blank" class="fund-btn fund-btn-outline" style="padding:3px 6px;font-size:10px;">📎 Proof</a>` : '-';
                html += `<tr>
                    <td><strong>${r.receipt_id}</strong></td>
                    <td>${r.fund_name || r.source_name || 'Direct Receipt'}</td>
                    <td><strong style="color:#10b981;">${formatINR(r.amount_received)}</strong></td>
                    <td>${r.received_date}</td>
                    <td>${r.payment_mode} <br><small>${r.utr_number || ''}</small></td>
                    <td>${r.bank_name || '-'} <br><small>${r.bank_account || ''}</small></td>
                    <td>${renderStatusBadge(r.status)}</td>
                    <td>${proof}</td>
                </tr>`;
            });
        } else { html = '<tr><td colspan="8" style="text-align:center;">No fund receipts logged yet.</td></tr>'; }
        document.querySelector('#tableReceipts tbody').innerHTML = html;
    });
}

function loadAllocations() {
    fetch('api.php?api=get_fund_allocations')
    .then(r => r.json())
    .then(data => {
        let html = '';
        if(data.allocations && data.allocations.length) {
            data.allocations.forEach(a => {
                html += `<tr>
                    <td><strong>${a.allocation_id}</strong></td>
                    <td>${a.fund_name}</td>
                    <td>${a.project_name || a.scheme || '-'}</td>
                    <td>${a.receiver_name || '-'}</td>
                    <td><strong>${formatINR(a.allocation_amount)}</strong></td>
                    <td style="color:#8b5cf6;">${formatINR(a.paid_amount)}</td>
                    <td style="color:#b45309;">${formatINR(a.remaining_allocation)}</td>
                    <td>${a.allocation_date}</td>
                    <td>${renderStatusBadge(a.status)}</td>
                </tr>`;
            });
        } else { html = '<tr><td colspan="9" style="text-align:center;">No allocations made yet.</td></tr>'; }
        document.querySelector('#tableAllocations tbody').innerHTML = html;
    });
}

function loadDisbursements() {
    fetch('api.php?api=get_fund_disbursements')
    .then(r => r.json())
    .then(data => {
        let html = '';
        if(data.disbursements && data.disbursements.length) {
            data.disbursements.forEach(d => {
                const proof = d.payment_proof_path ? `<a href="${d.payment_proof_path}" target="_blank" class="fund-btn fund-btn-outline" style="padding:3px 6px;font-size:10px;">📎 Proof</a>` : '-';
                html += `<tr>
                    <td><strong>${d.payment_id}</strong></td>
                    <td>${d.alloc_code || '-'}</td>
                    <td>${d.receiver_name || '-'}</td>
                    <td><strong style="color:#8b5cf6;">${formatINR(d.payment_amount)}</strong></td>
                    <td>${d.payment_date}</td>
                    <td>${d.payment_mode} <br><small>${d.utr_number || ''}</small></td>
                    <td>${renderStatusBadge(d.status)}</td>
                    <td>${proof}</td>
                </tr>`;
            });
        } else { html = '<tr><td colspan="8" style="text-align:center;">No payments recorded yet.</td></tr>'; }
        document.querySelector('#tableDisbursements tbody').innerHTML = html;
    });
}

function loadUtilizations() {
    fetch('api.php?api=get_fund_utilizations')
    .then(r => r.json())
    .then(data => {
        let html = '';
        if(data.utilizations && data.utilizations.length) {
            data.utilizations.forEach(u => {
                const proof = u.bill_proof_path ? `<a href="${u.bill_proof_path}" target="_blank" class="fund-btn fund-btn-outline" style="padding:3px 6px;font-size:10px;">🧾 Bill</a>` : '-';
                html += `<tr>
                    <td><strong>${u.utilization_id}</strong></td>
                    <td><span class="badge-status badge-approved">${u.expense_category}</span></td>
                    <td><strong style="color:#ec4899;">${formatINR(u.expense_amount)}</strong></td>
                    <td>${u.vendor_name || '-'} <br><small>${u.invoice_number || ''}</small></td>
                    <td>${u.expense_date}</td>
                    <td>${renderStatusBadge(u.verification_status)}</td>
                    <td>${proof}</td>
                </tr>`;
            });
        } else { html = '<tr><td colspan="7" style="text-align:center;">No utilization expenses logged yet.</td></tr>'; }
        document.querySelector('#tableUtilizations tbody').innerHTML = html;
    });
}

function loadProjects() {
    fetch('api.php?api=get_projects')
    .then(r => r.json())
    .then(data => {
        let html = '';
        if(data.projects && data.projects.length) {
            data.projects.forEach(p => {
                html += `<tr>
                    <td><strong>${p.project_id}</strong></td>
                    <td><a href="javascript:void(0)" onclick="openProjectView(${p.id})" style="color:#3b82f6; font-weight:700;">${p.project_name}</a></td>
                    <td>${p.project_code || '-'}</td>
                    <td>${p.project_manager || '-'}</td>
                    <td>${formatINR(p.budget)}</td>
                    <td>${renderStatusBadge(p.status)}</td>
                    <td><button class="fund-btn fund-btn-outline" style="padding:3px 8px; font-size:11px;" onclick="openProjectView(${p.id})">🔍 View</button></td>
                </tr>`;
            });
        } else { html = '<tr><td colspan="7" style="text-align:center;">No projects created yet.</td></tr>'; }
        document.querySelector('#tableProjects tbody').innerHTML = html;
    });
}

function loadReceivers() {
    fetch('api.php?api=get_receivers')
    .then(r => r.json())
    .then(data => {
        let html = '';
        if(data.receivers && data.receivers.length) {
            data.receivers.forEach(r => {
                html += `<tr>
                    <td><strong>${r.receiver_id}</strong></td>
                    <td><span class="badge-status badge-approved">${r.receiver_type}</span></td>
                    <td><a href="javascript:void(0)" onclick="openReceiverView(${r.id})" style="color:#3b82f6; font-weight:700;">${r.name}</a></td>
                    <td>${r.bank_name || '-'} <br><small>${r.account_number || ''} (${r.ifsc || ''})</small></td>
                    <td>${renderStatusBadge(r.status)}</td>
                    <td><button class="fund-btn fund-btn-outline" style="padding:3px 8px; font-size:11px;" onclick="openReceiverView(${r.id})">🔍 View</button></td>
                </tr>`;
            });
        } else { html = '<tr><td colspan="6" style="text-align:center;">No receivers registered yet.</td></tr>'; }
        document.querySelector('#tableReceivers tbody').innerHTML = html;
    });
}

function loadLedger() {
    const fid = document.getElementById('ledgerFundFilter').value;
    fetch(`api.php?api=get_fund_ledger&fund_id=${fid}`)
    .then(r => r.json())
    .then(data => {
        let html = '';
        if(data.ledger && data.ledger.length) {
            data.ledger.forEach(l => {
                const isCredit = l.transaction_type === 'Credit';
                html += `<tr>
                    <td><strong>#${l.id}</strong></td>
                    <td><span class="badge-status ${isCredit ? 'badge-paid' : 'badge-pending'}">${l.transaction_type}</span></td>
                    <td>${l.reference_no || '-'}</td>
                    <td style="color:#e11d48;">${l.debit_amount > 0 ? formatINR(l.debit_amount) : '-'}</td>
                    <td style="color:#10b981;">${l.credit_amount > 0 ? formatINR(l.credit_amount) : '-'}</td>
                    <td><strong style="color:#047857;">${formatINR(l.running_balance)}</strong></td>
                    <td>${l.transaction_date}</td>
                    <td>${l.description}</td>
                </tr>`;
            });
        } else { html = '<tr><td colspan="8" style="text-align:center;">No ledger transactions recorded yet.</td></tr>'; }
        document.querySelector('#tableLedger tbody').innerHTML = html;
    });
}

function loadAudit() {
    fetch('api.php?api=get_fund_audit_logs')
    .then(r => r.json())
    .then(data => {
        let html = '';
        if(data.audit_logs && data.audit_logs.length) {
            data.audit_logs.forEach(a => {
                html += `<tr>
                    <td><strong>#${a.id}</strong></td>
                    <td>${a.created_at}</td>
                    <td><strong>${a.username}</strong></td>
                    <td>${a.action}</td>
                    <td>${a.reference_no || '-'}</td>
                    <td>${a.ip_address || '-'}</td>
                </tr>`;
            });
        } else { html = '<tr><td colspan="6" style="text-align:center;">No audit logs generated yet.</td></tr>'; }
        document.querySelector('#tableAudit tbody').innerHTML = html;
    });
}

// Initial Load & URL Tab Parameter Parser
document.addEventListener('DOMContentLoaded', () => {
    loadDashboardStats();
    loadDropdownData();

    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');
    if (tabParam) {
        switchTab(tabParam);
    }
});
</script>

<?php require_once 'footer.php'; ?>
