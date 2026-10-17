<?php
require_once 'config.php';
require_role(['Admin'], false);


// Ensure table columns exist
$db->exec("
CREATE TABLE IF NOT EXISTS payout_distributions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    applicant_id INTEGER DEFAULT 0,
    payee_type TEXT DEFAULT 'Employee', 
    payee_user_id INTEGER DEFAULT 0,
    payee_name_override TEXT,
    payee_mobile TEXT,
    payee_bank_name TEXT,
    payee_account_no TEXT,
    payee_ifsc TEXT,
    payee_pan TEXT,
    total_loan_amount REAL DEFAULT 0,
    commission_percentage REAL DEFAULT 0,
    gross_payout REAL DEFAULT 0,
    tds_percentage REAL DEFAULT 5.0,
    tds_deducted REAL DEFAULT 0,
    net_payable REAL DEFAULT 0,
    status TEXT DEFAULT 'Pending', 
    cancellation_reason TEXT,
    transaction_ref TEXT,
    payment_mode TEXT DEFAULT 'NEFT',
    remarks TEXT,
    created_by TEXT,
    paid_on DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
");

// Only Admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit;
}

$page_title = 'Payout Distribution & Commission Hub';
$page_subtitle = 'Manage outgoing payouts to Staff/RMs & track incoming commissions from Banks';
$hide_main_header = true;
require_once 'header.php';

// Fetch dropdown data for Add Payout & Add Invoice Modals
$eligible_applicants = $db->query("SELECT id, customer_name, loan_id, loan_amount_requested, sanction_bank, overall_status FROM applicants ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$eligible_payees = $db->query("SELECT u.id, u.name, u.role, u.username, emp.mobile, emp.bank_name, emp.bank_account_no, emp.bank_ifsc 
                                FROM users u 
                                LEFT JOIN employees emp ON (emp.user_id = u.id OR emp.official_email = u.username)
                                WHERE u.role IN ('Partner', 'Builder', 'CA', 'Agent', 'Staff', 'Admin', 'RM') 
                                ORDER BY u.name ASC")->fetchAll(PDO::FETCH_ASSOC);

$active_tab = $_GET['tab'] ?? 'distribution';
?>

<style>
/* ===== EXECUTIVE ANIMATED POPUPS & SWEETALERT2 CORPORATE THEME ===== */
@keyframes modalScaleIn {
    0% {
        opacity: 0;
        transform: scale(0.92) translateY(14px);
    }
    100% {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

/* Apply smooth pop-in animation to all modal dialog containers */
div[id$="Modal"] > div, .swal2-popup {
    animation: modalScaleIn 0.28s cubic-bezier(0.16, 1, 0.3, 1) forwards !important;
}

/* SweetAlert2 Corporate Theme Overrides */
.swal2-popup {
    border-radius: 16px !important;
    padding: 28px !important;
    font-family: inherit !important;
    box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.3) !important;
    border: 1px solid #e2e8f0 !important;
}
.swal2-title {
    color: #0f172a !important;
    font-weight: 800 !important;
    font-size: 20px !important;
}
.swal2-html-container {
    color: #475569 !important;
    font-size: 13px !important;
    font-weight: 600 !important;
}

/* SweetAlert Buttons Overrides */
.swal2-styled.swal2-confirm {
    background-color: #0f172a !important;
    color: #ffffff !important;
    border-radius: 8px !important;
    font-weight: 800 !important;
    font-size: 13px !important;
    padding: 10px 20px !important;
    box-shadow: none !important;
    border: 1px solid #0f172a !important;
}
.swal2-styled.swal2-confirm:hover {
    background-color: #1e293b !important;
}

.swal2-styled.swal2-cancel {
    background-color: #ffffff !important;
    color: #0f172a !important;
    border: 1.5px solid #cbd5e1 !important;
    border-radius: 8px !important;
    font-weight: 700 !important;
    font-size: 13px !important;
    padding: 10px 18px !important;
    box-shadow: none !important;
}
.swal2-styled.swal2-cancel:hover {
    background-color: #f8fafc !important;
    border-color: #0f172a !important;
}

/* SweetAlert Success Icon Color Override */
.swal2-icon.swal2-success {
    border-color: #22c55e !important;
    color: #22c55e !important;
}
.swal2-icon.swal2-success .swal2-success-ring {
    border-color: rgba(34, 197, 94, 0.3) !important;
}
.swal2-icon.swal2-success [class^='swal2-success-line'] {
    background-color: #22c55e !important;
}

/* ===== BLACK & WHITE CORPORATE MONOCHROME THEME OVERRIDES ===== */
.mono-nav-tab {
    padding: 8px 18px !important;
    font-weight: 700 !important;
    border-radius: 8px !important;
    font-size: 13px !important;
    text-decoration: none !important;
    display: inline-flex !important;
    align-items: center !important;
    transition: all 0.15s ease !important;
}
.mono-nav-tab.active {
    background: #0f172a !important;
    color: #ffffff !important;
    border: 1px solid #0f172a !important;
    box-shadow: 0 4px 6px -1px rgba(15,23,42,0.15) !important;
}
.mono-nav-tab.inactive {
    background: #ffffff !important;
    color: #334155 !important;
    border: 1px solid #cbd5e1 !important;
}
.mono-nav-tab.inactive:hover {
    background: #f1f5f9 !important;
    color: #0f172a !important;
    border-color: #0f172a !important;
}

.mono-stat-card {
    border: 1px solid #e2e8f0 !important;
    border-top: 3px solid #0f172a !important;
    border-radius: 12px !important;
    background: #ffffff !important;
    padding: 16px !important;
    box-shadow: 0 2px 4px rgba(0,0,0,0.02) !important;
}
.mono-stat-label {
    font-size: 11px !important;
    text-transform: uppercase !important;
    font-weight: 800 !important;
    color: #64748b !important;
    letter-spacing: 0.5px !important;
}
.mono-stat-value {
    color: #0f172a !important;
    font-size: 24px !important;
    font-weight: 900 !important;
    margin-top: 4px !important;
    letter-spacing: -0.5px !important;
}

.btn-black-solid {
    background: #0f172a !important;
    color: #ffffff !important;
    border: 1px solid #0f172a !important;
    font-weight: 700 !important;
    border-radius: 8px !important;
    padding: 8px 16px !important;
    font-size: 13px !important;
    cursor: pointer !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 6px !important;
    transition: background 0.15s ease !important;
    text-decoration: none !important;
}
.btn-black-solid:hover {
    background: #1e293b !important;
    border-color: #1e293b !important;
    color: #ffffff !important;
}

.btn-black-outline {
    background: #ffffff !important;
    color: #0f172a !important;
    border: 1px solid #0f172a !important;
    font-weight: 700 !important;
    border-radius: 8px !important;
    padding: 8px 16px !important;
    font-size: 13px !important;
    cursor: pointer !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 6px !important;
    transition: all 0.15s ease !important;
    text-decoration: none !important;
}
.btn-black-outline:hover {
    background: #0f172a !important;
    color: #ffffff !important;
}

.badge-mono-black {
    background: #0f172a !important;
    color: #ffffff !important;
    font-weight: 800 !important;
    padding: 3px 8px !important;
    border-radius: 20px !important;
    font-size: 10px !important;
    display: inline-block !important;
    letter-spacing: 0.3px !important;
    white-space: nowrap !important;
}
.badge-mono-outline {
    background: #ffffff !important;
    color: #0f172a !important;
    border: 1.5px solid #0f172a !important;
    font-weight: 800 !important;
    padding: 3px 8px !important;
    border-radius: 20px !important;
    font-size: 10px !important;
    display: inline-block !important;
    letter-spacing: 0.3px !important;
    white-space: nowrap !important;
}
.badge-mono-bank {
    background: #f8fafc !important;
    color: #0f172a !important;
    border: 1px solid #cbd5e1 !important;
    font-weight: 800 !important;
    padding: 2px 7px !important;
    border-radius: 6px !important;
    font-size: 10px !important;
    display: inline-block !important;
    text-transform: uppercase !important;
    white-space: nowrap !important;
}

.data-table {
    width: 100% !important;
    border-collapse: collapse !important;
    table-layout: auto !important;
}
.data-table thead,
.data-table thead tr {
    background-color: #0f172a !important;
    background: #0f172a !important;
}
.data-table thead th,
.data-table thead tr th,
.data-table th,
table.data-table thead tr th {
    background-color: #0f172a !important;
    background: #0f172a !important;
    color: #ffffff !important;
    font-weight: 800 !important;
    font-size: 10.5px !important;
    text-transform: uppercase !important;
    letter-spacing: 0.3px !important;
    padding: 10px 6px !important;
    border-bottom: 2px solid #0f172a !important;
    white-space: nowrap !important;
}
.data-table tbody td {
    padding: 6px 6px !important;
    font-size: 11.5px !important;
    vertical-align: middle !important;
    border-bottom: 1px solid #e2e8f0 !important;
    background: #ffffff !important;
    color: #0f172a !important;
}
.data-table tbody tr:hover td {
    background: #f8fafc !important;
}
</style>

<div class="view-container" style="padding-bottom: 50px;">

    <!-- Navigation Bar -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 24px; flex-wrap:wrap; gap:12px;">
        <div style="display:flex; gap:8px; background:#f8fafc; padding:4px; border-radius:12px; border:1px solid #e2e8f0;">
            <a href="?tab=distribution" class="mono-nav-tab <?= $active_tab === 'distribution' ? 'active' : 'inactive' ?>">
                <i data-lucide="wallet" style="width:16px; height:16px; margin-right:6px; vertical-align:middle;"></i> Outgoing Payout Hub
            </a>
            <a href="?tab=bank_tracker" class="mono-nav-tab <?= $active_tab === 'bank_tracker' ? 'active' : 'inactive' ?>">
                <i data-lucide="building-2" style="width:16px; height:16px; margin-right:6px; vertical-align:middle;"></i> Incoming Bank Payouts
                <span style="font-size:10px; margin-left:6px; padding:2px 6px; border-radius:10px; background:#0f172a; color:#fff; font-weight:800;">Bank Unpaid Files</span>
            </a>
            <a href="?tab=bank" class="mono-nav-tab <?= $active_tab === 'bank' ? 'active' : 'inactive' ?>">
                <i data-lucide="receipt" style="width:16px; height:16px; margin-right:6px; vertical-align:middle;"></i> Bank Invoices Ledger
            </a>
            <a href="?tab=bank_directory" class="mono-nav-tab <?= $active_tab === 'bank_directory' ? 'active' : 'inactive' ?>">
                <i data-lucide="credit-card" style="width:16px; height:16px; margin-right:6px; vertical-align:middle;"></i> Payee Bank Directory
            </a>
        </div>
        <div>
            <span style="padding:6px 14px; font-size:12px; font-weight:800; border:1.5px solid #0f172a; border-radius:20px; color:#0f172a; background:#fff;"><i data-lucide="shield-check" style="width:14px; vertical-align:middle; margin-right:4px;"></i> TAX AUDIT COMPLIANT (5% TDS)</span>
        </div>
    </div>

    <?php if ($active_tab === 'distribution'): ?>
    <!-- Metrics Cards (5-Column Power Grid) -->
    <div class="stats-grid" style="grid-template-columns: repeat(5, 1fr); gap: 16px; margin-bottom: 24px;">
        <div class="mono-stat-card">
            <div class="stat-card-header"><span class="mono-stat-label">Gross Generated</span></div>
            <div class="mono-stat-value" id="kpi-total-gross">₹0</div>
            <div style="font-size:11px; color:#64748b; margin-top:2px;">Total Pre-TDS Payouts</div>
        </div>
        <div class="mono-stat-card">
            <div class="stat-card-header"><span class="mono-stat-label">TDS Deducted (5%)</span></div>
            <div class="mono-stat-value" id="kpi-total-tds">₹0</div>
            <div style="font-size:11px; color:#64748b; margin-top:2px;">Government Tax Reserve</div>
        </div>
        <div class="mono-stat-card">
            <div class="stat-card-header"><span class="mono-stat-label">Pending Net Disbursal</span></div>
            <div class="mono-stat-value" id="kpi-total-pending">₹0</div>
            <div style="font-size:11px; color:#64748b; margin-top:2px;">Awaiting Bank Transfer</div>
        </div>
        <div class="mono-stat-card">
            <div class="stat-card-header"><span class="mono-stat-label">Total Disbursed (Paid)</span></div>
            <div class="mono-stat-value" id="kpi-total-paid">₹0</div>
            <div style="font-size:11px; color:#64748b; margin-top:2px;">Successfully Credited</div>
        </div>
        <div class="mono-stat-card">
            <div class="stat-card-header"><span class="mono-stat-label">Held / Cancelled</span></div>
            <div class="mono-stat-value" id="kpi-total-cancelled">₹0</div>
            <div style="font-size:11px; color:#64748b; margin-top:2px;">On Hold or Rejected</div>
        </div>
    </div>

    <!-- Filter Bar & Actions Hub -->
    <div class="card" style="margin-bottom: 24px; padding: 20px; border-radius:14px; border:1px solid #e2e8f0; background:#fff;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:12px;">
            <div>
                <h2 style="margin:0; font-size:18px; font-weight:800; color:#0f172a;">Outgoing Payout Disbursal Master Ledger</h2>
                <div style="font-size:12px; color:#64748b;">Search customer loan files, bank status, payee details, and disburse payouts</div>
            </div>
            <div style="display:flex; gap:10px;">
                <button type="button" class="btn-black-outline" onclick="exportPayoutsCSV()">
                    <i data-lucide="download" style="width:15px;"></i> Export Audit CSV
                </button>
                <button type="button" class="btn-black-solid" onclick="openBulkDisburseModal()">
                    <i data-lucide="zap" style="width:15px;"></i> Bulk Disburse Selected
                </button>
                <button class="btn-black-solid" onclick="openAddPayoutModal()">
                    <i data-lucide="plus-circle" style="width:16px;"></i> + Generate New Payout
                </button>
            </div>
        </div>
        
        <form onsubmit="event.preventDefault(); loadPayouts(1);" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
            <div style="flex: 2; min-width: 260px;">
                <input type="text" id="filter_search" placeholder="Search by Customer Name, Loan ID, Bank, Payee, UTR No..." style="width:100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size:13px;" oninput="loadPayouts(1)">
            </div>
            <select id="filter_status" style="padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size:13px; width:140px; font-weight:600;" onchange="loadPayouts(1)">
                <option value="">All Statuses</option>
                <option value="Pending">Pending</option>
                <option value="Approved">Approved</option>
                <option value="Hold">Hold</option>
                <option value="Paid">Paid</option>
                <option value="Cancelled">Cancelled</option>
            </select>
            <select id="filter_payee" style="padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size:13px; width:160px; font-weight:600;" onchange="loadPayouts(1)">
                <option value="">All Payee Types</option>
                <option value="Employee">Staff / RM</option>
                <option value="Partner">Partner (CA/Builder)</option>
                <option value="Referral">External Referral</option>
            </select>
            <button type="submit" class="btn-black-solid" style="padding: 10px 20px;">Filter</button>
            <button type="button" class="btn-black-outline" onclick="resetFilters()" style="padding: 10px 20px;">Reset</button>
        </form>
    </div>

    <!-- Master Data Table -->
    <div class="card" style="width:100%; border-radius:14px; border:1px solid #e2e8f0; padding:0; background:#fff; overflow-x:auto;">
        <table class="data-table" style="width:100%; border-collapse:collapse;">
            <thead>
                <tr style="background-color:#0f172a !important; border-bottom:2px solid #0f172a; text-align:left;">
                    <th style="padding:10px 8px; width:30px; background-color:#0f172a !important; color:#ffffff !important;"><input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll(this)"></th>
                    <th style="padding:10px 8px; background-color:#0f172a !important; color:#ffffff !important;">Voucher / ID</th>
                    <th style="padding:10px 8px; background-color:#0f172a !important; color:#ffffff !important;">Disbursed Customer Case</th>
                    <th style="padding:10px 8px; background-color:#0f172a !important; color:#ffffff !important;">Sanctioned Bank & Loan Amt</th>
                    <th style="padding:10px 8px; background-color:#0f172a !important; color:#ffffff !important;">Payee Details (Recipient)</th>
                    <th style="padding:10px 8px; background-color:#0f172a !important; color:#ffffff !important;">Bank Payout Status</th>
                    <th style="padding:10px 8px; background-color:#0f172a !important; color:#ffffff !important;">Gross</th>
                    <th style="padding:10px 8px; background-color:#0f172a !important; color:#ffffff !important;">TDS 5%</th>
                    <th style="padding:10px 8px; background-color:#0f172a !important; color:#ffffff !important;">Net Payable</th>
                    <th style="padding:10px 8px; background-color:#0f172a !important; color:#ffffff !important;">Disbursal Status</th>
                    <th style="padding:10px 8px; text-align:center; background-color:#0f172a !important; color:#ffffff !important;">Actions Hub</th>
                </tr>
            </thead>
            <tbody id="payout-tbody">
                <!-- Data loaded via JS -->
            </tbody>
        </table>
        
        <div id="pagination-controls" style="display:flex; justify-content:space-between; align-items:center; padding:16px 20px; background:#fafafa; border-top:1px solid #e2e8f0;">
            <div id="pagination-info" style="font-size:13px; color:#64748b; font-weight:600;">Loading...</div>
            <div id="pagination-buttons" style="display:flex; gap:8px;"></div>
        </div>
    </div>

    <?php elseif ($active_tab === 'bank_tracker'): ?>

    <!-- INCOMING BANK PAYOUT TRACKER -->
    <div class="stats-grid" style="grid-template-columns: repeat(3, 1fr); gap:16px; margin-bottom: 24px;">
        <div class="mono-stat-card">
            <div class="stat-card-header"><span class="mono-stat-label">Total Disbursed Cases Volume</span></div>
            <div class="mono-stat-value" id="bt-total-disbursed">₹0</div>
            <div style="font-size:12px; color:#64748b;">Total completed customer loans</div>
        </div>
        <div class="mono-stat-card">
            <div class="stat-card-header"><span class="mono-stat-label">Bank Commission Received</span></div>
            <div class="mono-stat-value" id="bt-total-received">₹0</div>
            <div style="font-size:12px; color:#64748b;">Total bank payout credited to BFS Circle</div>
        </div>
        <div class="mono-stat-card">
            <div class="stat-card-header"><span class="mono-stat-label">UNPAID BANK PAYOUTS (PENDING)</span></div>
            <div class="mono-stat-value" id="bt-total-pending">₹0</div>
            <div style="font-size:12px; color:#64748b; font-weight:700;" id="bt-pending-count">0 files pending payment from banks</div>
        </div>
    </div>

    <!-- Disbursed Files Ledger with Bank Payment Status -->
    <div class="card" style="padding:20px; border-radius:14px; border:1px solid #e2e8f0; background:#fff;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:12px;">
            <div>
                <h3 style="margin:0; font-size:18px; font-weight:800; color:#0f172a;">Disbursed Files & Bank Payout Clearance Tracker</h3>
                <div style="font-size:12px; color:#64748b;">Track which bank has paid commission to BFS Circle, generate tax claims, and escalate via Email & WhatsApp</div>
            </div>
            <div style="display:flex; gap:8px;">
                <button class="btn-black-outline" onclick="openBankSlabsModal()" style="padding:8px 14px; font-size:12px;">
                    <i data-lucide="layers" style="width:14px; vertical-align:middle;"></i> Bank Volume Slabs
                </button>
                <button class="btn-black-outline" onclick="loadBankPayoutTracker()" style="padding:8px 14px; font-size:12px;">
                    <i data-lucide="refresh-cw" style="width:14px; vertical-align:middle;"></i> Refresh List
                </button>
            </div>
        </div>

        <div style="overflow-x:auto; width:100%;">
            <table class="data-table" style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr style="background-color:#0f172a !important; text-align:left; border-bottom:2px solid #0f172a;">
                        <th style="padding:10px 8px; background-color:#0f172a !important; color:#ffffff !important;">Loan Case & Customer</th>
                        <th style="padding:10px 8px; background-color:#0f172a !important; color:#ffffff !important;">Sanctioned Bank</th>
                        <th style="padding:10px 8px; background-color:#0f172a !important; color:#ffffff !important;">Disbursed Amount</th>
                        <th style="padding:10px 8px; background-color:#0f172a !important; color:#ffffff !important;">Assigned RM</th>
                        <th style="padding:10px 8px; background-color:#0f172a !important; color:#ffffff !important;">Est. Bank Commission (%)</th>
                        <th style="padding:10px 8px; background-color:#0f172a !important; color:#ffffff !important;">Bank Payment Status</th>
                        <th style="padding:10px 8px; text-align:center; background-color:#0f172a !important; color:#ffffff !important;">Action & Bank Escalation</th>
                    </tr>
                </thead>
                <tbody id="bank-tracker-tbody">
                    <!-- Loaded via JS -->
                </tbody>
            </table>
        </div>
    </div>

    <?php elseif ($active_tab === 'bank'): ?>
    
    <!-- Bank Commission Invoices Hub -->
    <div class="stats-grid" style="grid-template-columns: repeat(3, 1fr); gap:16px; margin-bottom: 24px;">
        <div class="mono-stat-card">
            <div class="stat-card-header"><span class="mono-stat-label">Total Outstanding Invoices</span></div>
            <div class="mono-stat-value" id="bank-total-outstanding">₹0</div>
            <div style="font-size:12px; color:#64748b;">Claims sent to banks awaiting payment</div>
        </div>
        <div class="mono-stat-card">
            <div class="stat-card-header"><span class="mono-stat-label">Received This Month</span></div>
            <div class="mono-stat-value" id="bank-received-month">₹0</div>
            <div style="font-size:12px; color:#64748b;">Total bank payouts cleared</div>
        </div>
        <div class="mono-stat-card">
            <div class="stat-card-header"><span class="mono-stat-label">Shortfall / Deductions</span></div>
            <div class="mono-stat-value" id="bank-total-shortfall">₹0</div>
            <div style="font-size:12px; color:#64748b;">Bank payout haircut/shortfall tracked</div>
        </div>
    </div>

    <!-- Invoices Ledger Section Header & Actions -->
    <div class="card" style="padding:20px; border-radius:14px; border:1px solid #e2e8f0; margin-bottom:24px; background:#fff;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
            <div>
                <h3 style="margin:0; font-size:18px; font-weight:800; color:#0f172a;">Bank Commission Invoices Ledger</h3>
                <div style="font-size:12px; color:#64748b;">Manage GST Tax Claim Invoices sent to Banks, clear payments & track shortfalls</div>
            </div>
            <div style="display:flex; gap:10px;">
                <button type="button" class="btn-black-outline" style="height:36px; padding:0 14px; font-size:12px; font-weight:700; border-radius:8px; display:inline-flex; align-items:center; gap:6px;" onclick="exportInvoicesCSV()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                    Export Ledger CSV
                </button>
                <button type="button" class="btn-black-solid" style="height:36px; padding:0 16px; font-size:12px; font-weight:800; border-radius:8px; display:inline-flex; align-items:center; gap:6px;" onclick="openCreateInvoiceModal()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    Create Bank Claim Invoice
                </button>
            </div>
        </div>

        <div style="overflow-x:auto; margin-top:16px;">
            <table class="data-table" style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr style="background-color:#0f172a !important; text-align:left; border-bottom:2px solid #0f172a;">
                        <th style="padding:10px 8px; background-color:#0f172a !important; color:#ffffff !important;">Invoice No & Ref</th>
                        <th style="padding:10px 8px; background-color:#0f172a !important; color:#ffffff !important;">Customer Name</th>
                        <th style="padding:10px 8px; background-color:#0f172a !important; color:#ffffff !important;">Sanctioned Bank</th>
                        <th style="padding:10px 8px; background-color:#0f172a !important; color:#ffffff !important;">Disbursed Amt</th>
                        <th style="padding:10px 8px; background-color:#0f172a !important; color:#ffffff !important;">Comm %</th>
                        <th style="padding:10px 8px; background-color:#0f172a !important; color:#ffffff !important;">Net Receivable (Inc GST - TDS)</th>
                        <th style="padding:10px 8px; background-color:#0f172a !important; color:#ffffff !important;">Claim Status</th>
                        <th style="padding:10px 8px; text-align:center; background-color:#0f172a !important; color:#ffffff !important;">Actions Hub</th>
                    </tr>
                </thead>
                <tbody id="bank-invoices-tbody">
                    <!-- Loaded via JS -->
                </tbody>
            </table>
        </div>
    </div>

    <?php else: // Payee Banking Directory Tab ?>

    <div class="card" style="padding:20px; border-radius:14px; border:1px solid #e2e8f0; background:#fff;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <div>
                <h3 style="margin:0; font-size:18px; font-weight:800; color:#0f172a;">Payee Banking Details Directory</h3>
                <div style="font-size:12px; color:#64748b;">Verify and update bank account details for RMs, Channel Partners, CAs, and Staff</div>
            </div>
            <button class="btn-black-outline" onclick="loadPayeeDirectory()" style="padding:8px 14px; font-size:12px;">
                <i data-lucide="refresh-cw" style="width:14px; vertical-align:middle;"></i> Refresh List
            </button>
        </div>

        <div style="overflow-x:auto;">
            <table class="data-table" style="width:100%;">
                <thead>
                    <tr style="background-color:#0f172a !important; text-align:left; border-bottom:2px solid #0f172a;">
                        <th style="padding:12px; background-color:#0f172a !important; color:#ffffff !important;">Payee Name</th>
                        <th style="padding:12px; background-color:#0f172a !important; color:#ffffff !important;">Role</th>
                        <th style="padding:12px; background-color:#0f172a !important; color:#ffffff !important;">Mobile / Email</th>
                        <th style="padding:12px; background-color:#0f172a !important; color:#ffffff !important;">Bank Name</th>
                        <th style="padding:12px; background-color:#0f172a !important; color:#ffffff !important;">Account Number</th>
                        <th style="padding:12px; background-color:#0f172a !important; color:#ffffff !important;">IFSC Code</th>
                        <th style="padding:12px; background-color:#0f172a !important; color:#ffffff !important;">PAN Number</th>
                        <th style="padding:12px; text-align:center; background-color:#0f172a !important; color:#ffffff !important;">Action</th>
                    </tr>
                </thead>
                <tbody id="payee-directory-tbody">
                    <!-- Loaded via JS -->
                </tbody>
            </table>
        </div>
    </div>

    <?php endif; ?>

</div>

<!-- ===== MODALS HUB ===== -->

<!-- Edit Case Bank Commission Rate Modal -->
<div id="editCaseCommModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); backdrop-filter:blur(4px); z-index:9999; justify-content:center; align-items:center;">
    <div style="background:#fff; padding:24px; border-radius:16px; width:450px; max-width:92%; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; border-bottom:1px solid #e2e8f0; padding-bottom:12px;">
            <h3 style="margin:0; font-size:16px; font-weight:800; color:#0f172a;">Modify Bank Commission %</h3>
            <button onclick="document.getElementById('editCaseCommModal').style.display='none'" style="background:none; border:none; font-size:20px; font-weight:700; cursor:pointer;">&times;</button>
        </div>
        <form onsubmit="submitUpdateCaseComm(event)">
            <input type="hidden" id="ecc_applicant_id">
            <div style="font-size:13px; font-weight:700; color:#0f172a; margin-bottom:12px;">
                Customer Case: <span id="ecc_customer_title" style="color:#2563eb;">-</span>
            </div>
            <div style="margin-bottom:16px;">
                <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;">Commission Percentage (%)</label>
                <input type="number" step="0.01" min="0.1" max="15" id="ecc_commission_pct" placeholder="e.g. 1.50" style="width:100%; padding:10px 14px; border:1px solid #cbd5e1; border-radius:8px; font-size:14px; font-weight:800; color:#0f172a;" required>
                <div style="font-size:11px; color:#64748b; margin-top:6px;">
                    ℹ️ Updating this rate recalculates Gross Commission, GST (18%), TDS (2%), and Net Receivable across bank invoices.
                </div>
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button type="button" class="btn-black-outline" onclick="document.getElementById('editCaseCommModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn-black-solid">Save & Update Rate</button>
            </div>
        </form>
    </div>
</div>

<!-- Bank Volume Commission Slabs Master Modal -->
<div id="bankSlabsModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); backdrop-filter:blur(4px); z-index:9999; justify-content:center; align-items:center;">
    <div style="background:#fff; padding:28px; border-radius:16px; width:750px; max-width:94%; max-height:90vh; overflow-y:auto; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; border-bottom:1px solid #e2e8f0; padding-bottom:12px;">
            <div>
                <h3 style="margin:0; font-size:18px; font-weight:800; color:#0f172a;">Bank Volume Commission Rates & Slabs Master</h3>
                <div style="font-size:12px; color:#64748b;">Tiered payout commission rates based on monthly business volume delivered to banks</div>
            </div>
            <button onclick="document.getElementById('bankSlabsModal').style.display='none'" style="background:none; border:none; font-size:22px; font-weight:700; cursor:pointer;">&times;</button>
        </div>

        <!-- Add New Slab Form -->
        <div style="background:#f8fafc; padding:16px; border-radius:10px; border:1px solid #e2e8f0; margin-bottom:20px;">
            <h4 style="margin:0 0 12px 0; font-size:14px; font-weight:800; color:#0f172a;">+ Add / Configure Volume Slab Rule</h4>
            <form onsubmit="submitAddSlab(event)" style="display:grid; grid-template-columns: 1.5fr 1fr 1fr 1fr auto; gap:10px; align-items:end;">
                <div>
                    <label style="font-size:11px; font-weight:700; color:#475569;">Bank Name</label>
                    <input type="text" id="slab_bank_name" placeholder="e.g. HDFC Bank" style="width:100%; padding:8px 10px; border:1px solid #cbd5e1; border-radius:6px; font-size:12px;" required>
                </div>
                <div>
                    <label style="font-size:11px; font-weight:700; color:#475569;">Min Volume (₹)</label>
                    <input type="number" id="slab_min_vol" placeholder="e.g. 2000000" style="width:100%; padding:8px 10px; border:1px solid #cbd5e1; border-radius:6px; font-size:12px;" required>
                </div>
                <div>
                    <label style="font-size:11px; font-weight:700; color:#475569;">Max Volume (₹)</label>
                    <input type="number" id="slab_max_vol" placeholder="e.g. 5000000" style="width:100%; padding:8px 10px; border:1px solid #cbd5e1; border-radius:6px; font-size:12px;" required>
                </div>
                <div>
                    <label style="font-size:11px; font-weight:700; color:#475569;">Comm %</label>
                    <input type="number" step="0.01" id="slab_comm_pct" placeholder="e.g. 1.25" style="width:100%; padding:8px 10px; border:1px solid #cbd5e1; border-radius:6px; font-size:12px;" required>
                </div>
                <div>
                    <button type="submit" class="btn-black-solid" style="padding:8px 14px; font-size:12px; height:36px;">Save Slab</button>
                </div>
            </form>
        </div>

        <!-- Slabs List Table -->
        <table class="data-table" style="width:100%; border-collapse:collapse;">
            <thead>
                <tr style="background:#0f172a; color:#fff; text-align:left;">
                    <th style="padding:10px;">Bank Name</th>
                    <th style="padding:10px;">Min Monthly Volume</th>
                    <th style="padding:10px;">Max Monthly Volume</th>
                    <th style="padding:10px;">Commission Slab %</th>
                </tr>
            </thead>
            <tbody id="bank-slabs-tbody">
                <!-- Loaded via JS -->
            </tbody>
        </table>

        <div style="margin-top:20px; text-align:right;">
            <button type="button" class="btn-black-solid" onclick="document.getElementById('bankSlabsModal').style.display='none'">Close</button>
        </div>
    </div>
</div>

<!-- Bank GST Tax Invoice Printable Modal -->
<div id="taxInvoicePreviewModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); backdrop-filter:blur(4px); z-index:9999; justify-content:center; align-items:center;">
    <div style="background:#fff; padding:32px; border-radius:16px; width:700px; max-width:92%; max-height:90vh; overflow-y:auto; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);">
        
        <div id="tax-invoice-print-area">
            <!-- Header Letterhead -->
            <div style="display:flex; justify-content:space-between; align-items:flex-start; border-bottom:2px solid #0f172a; padding-bottom:16px; margin-bottom:20px;">
                <div>
                    <h1 style="margin:0; font-size:24px; font-weight:900; color:#0f172a; letter-spacing:-0.5px;">BFS FINANCIAL SERVICES PVT LTD</h1>
                    <div style="font-size:12px; color:#64748b; font-weight:600;">Corporate Office: 101-104, BFS Tower, Connaught Place, New Delhi - 110001</div>
                    <div style="font-size:11px; color:#94a3b8;">GSTIN: 07AAACB1234F1Z5 • PAN: AAACB1234F • HSN/SAC: 997159</div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:16px; font-weight:900; color:#3b82f6; text-transform:uppercase;">TAX INVOICE</div>
                    <div id="ti_invoice_no" style="font-size:13px; font-weight:700; color:#0f172a; margin-top:2px;">INV-20260922-L000</div>
                    <div id="ti_date" style="font-size:11px; color:#64748b;">Date: 22 Sep 2026</div>
                </div>
            </div>

            <!-- Bill To Bank & Loan Case Reference -->
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:20px; font-size:13px;">
                <div style="background:#f8fafc; padding:14px; border-radius:10px; border:1px solid #e2e8f0;">
                    <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; margin-bottom:4px;">Billed To (Sanctioned Bank)</div>
                    <div id="ti_bank_name" style="font-weight:800; color:#0f172a; font-size:15px;">HDFC Bank Ltd</div>
                    <div style="color:#64748b; font-size:12px;">Retail Loan Disbursal Hub / Nodal Cell</div>
                    <div style="color:#94a3b8; font-size:11px;">GSTIN: 07HDFC129084A1Z2</div>
                </div>
                <div style="background:#f8fafc; padding:14px; border-radius:10px; border:1px solid #e2e8f0;">
                    <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; margin-bottom:4px;">Disbursed Loan Case Details</div>
                    <div id="ti_customer_name" style="font-weight:800; color:#0f172a;">Customer: -</div>
                    <div id="ti_loan_id" style="color:#3b82f6; font-weight:700;">Loan Ref ID: -</div>
                    <div id="ti_disbursed_amt" style="color:#10b981; font-weight:800; font-size:14px; margin-top:2px;">Disbursed Amount: ₹0</div>
                </div>
            </div>

            <!-- Tax Invoice Financial Table -->
            <table style="width:100%; border-collapse:collapse; margin-bottom:20px; font-size:13px;">
                <thead>
                    <tr style="background:#0f172a; color:#fff; text-align:left;">
                        <th style="padding:10px 12px;">Description of Financial Services</th>
                        <th style="padding:10px 12px; text-align:center;">SAC</th>
                        <th style="padding:10px 12px; text-align:right;">Rate %</th>
                        <th style="padding:10px 12px; text-align:right;">Amount (₹)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr style="border-bottom:1px solid #e2e8f0;">
                        <td style="padding:12px;">
                            <strong>DSA Commission Charges for Loan Sourcing & Disbursal</strong>
                            <div style="font-size:11px; color:#64748b;">Services rendered for customer loan clearance</div>
                        </td>
                        <td style="padding:12px; text-align:center;">997159</td>
                        <td id="ti_comm_pct" style="padding:12px; text-align:right; font-weight:700;">1.50%</td>
                        <td id="ti_gross_amt" style="padding:12px; text-align:right; font-weight:800; color:#0f172a;">₹0.00</td>
                    </tr>
                    <tr style="border-bottom:1px solid #e2e8f0;">
                        <td colspan="3" style="padding:8px 12px; text-align:right; color:#475569;">Add: Integrated GST (IGST @ 18%)</td>
                        <td id="ti_gst_amt" style="padding:8px 12px; text-align:right; font-weight:700; color:#3b82f6;">+₹0.00</td>
                    </tr>
                    <tr style="border-bottom:1px solid #e2e8f0;">
                        <td colspan="3" style="padding:8px 12px; text-align:right; color:#ef4444;">Less: Statutory Tax Deduction at Source (TDS @ 2% Sec 194H)</td>
                        <td id="ti_tds_amt" style="padding:8px 12px; text-align:right; font-weight:700; color:#ef4444;">-₹0.00</td>
                    </tr>
                    <tr style="background:#f0fdf4; border-top:2px solid #10b981;">
                        <td colspan="3" style="padding:12px; font-weight:900; color:#166534; font-size:15px;">NET RECEIVABLE AMOUNT FROM BANK</td>
                        <td id="ti_net_amt" style="padding:12px; text-align:right; font-weight:900; color:#166534; font-size:18px;">₹0.00</td>
                    </tr>
                </tbody>
            </table>

            <!-- Corporate Bank Remittance Details -->
            <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:10px; padding:14px; margin-bottom:20px; font-size:12px;">
                <div style="font-weight:700; color:#1d4ed8; text-transform:uppercase; margin-bottom:6px;">REMITTANCE BANK ACCOUNT (BFS CIRCLE)</div>
                <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:8px;">
                    <div>Account Name: <strong>BFS Financial Services Pvt Ltd</strong></div>
                    <div>Bank Name: <strong>HDFC Bank Ltd</strong></div>
                    <div>Account No: <strong>50200019840291</strong></div>
                    <div>IFSC Code: <strong>HDFC0000123</strong></div>
                    <div>Branch: <strong>Connaught Place, New Delhi</strong></div>
                </div>
            </div>

            <!-- Signatures -->
            <div style="display:flex; justify-content:space-between; margin-top:30px; padding-top:16px; border-top:1px dashed #cbd5e1; font-size:12px; color:#64748b;">
                <div>Computer Generated Tax Invoice</div>
                <div style="text-align:right; font-weight:700; color:#0f172a;">For BFS Financial Services Pvt Ltd</div>
            </div>
        </div>

        <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:24px; border-top:1px solid #f1f5f9; padding-top:16px;">
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('taxInvoicePreviewModal').style.display='none'">Close</button>
            <button type="button" class="btn btn-primary" onclick="printBankTaxInvoice()" style="font-weight:700; display:flex; align-items:center; gap:6px;">
                <i data-lucide="printer" style="width:16px;"></i> Print / Save PDF Invoice
            </button>
        </div>
    </div>
</div>

<!-- Bank Email Escalation & Corporate Email Dispatcher Modal -->
<div id="bankEmailEscalationModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.65); backdrop-filter:blur(6px); z-index:9999; justify-content:center; align-items:center;">
    <div style="background:#fff; padding:28px; border-radius:16px; width:580px; max-width:92%; max-height:92vh; overflow-y:auto; box-shadow:0 25px 50px -12px rgba(15,23,42,0.35);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid #e2e8f0; padding-bottom:14px;">
            <div>
                <h3 style="margin:0; font-size:18px; font-weight:800; color:#0f172a; display:flex; align-items:center; gap:8px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="color:#0f172a;"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                    Corporate Email Dispatcher
                </h3>
                <div style="font-size:12px; color:#64748b; margin-top:2px;">Dispatches encrypted SMTP HTML letterhead with company GSTIN & PAN</div>
            </div>
            <button type="button" onclick="document.getElementById('bankEmailEscalationModal').style.display='none'" style="background:none; border:none; font-size:22px; color:#64748b; cursor:pointer; line-height:1;">&times;</button>
        </div>
        
        <form onsubmit="submitBankEmailEscalation(event)">
            <input type="hidden" id="bee_applicant_id">
            
            <div style="margin-bottom: 14px;">
                <label style="display:block; margin-bottom:4px; font-weight:700; font-size:12.5px; color:#334155;">Select Email Preset Template</label>
                <select id="bee_template_select" onchange="onEmailTemplateChange()" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px; font-weight:700; color:#0f172a; background:#f8fafc;">
                    <option value="escalation">📌 Bank Commission Escalation / Clearance Reminder</option>
                    <option value="invoice">📑 GST Tax Invoice Claim Dispatch (SAC 997159)</option>
                    <option value="acknowledgement">💳 Payment Clearance Confirmation & UTR Inquiry</option>
                    <option value="custom">📝 Custom Executive Email</option>
                </select>
            </div>

            <div style="margin-bottom: 14px;">
                <label style="display:block; margin-bottom:4px; font-weight:700; font-size:12.5px; color:#334155;">Recipient Email Address</label>
                <input type="email" id="bee_email" placeholder="nodal.disbursal@bank.com" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px; font-weight:600;" required>
            </div>

            <div style="margin-bottom: 14px;">
                <label style="display:block; margin-bottom:4px; font-weight:700; font-size:12.5px; color:#334155;">Email Subject</label>
                <input type="text" id="bee_subject" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px; font-weight:700; color:#0f172a;" required>
            </div>

            <div style="margin-bottom: 16px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                    <label style="font-weight:700; font-size:12.5px; color:#334155;">Message Content</label>
                    <span style="font-size:11px; background:#f1f5f9; color:#0f172a; padding:2px 8px; border-radius:10px; font-weight:700;">HTML Letterhead Active</span>
                </div>
                <textarea id="bee_body" rows="7" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; font-size:12.5px; font-family:'Segoe UI', sans-serif; resize:vertical; line-height:1.5; color:#0f172a;" required></textarea>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px; align-items:center; border-top:1px solid #f1f5f9; padding-top:14px;">
                <button type="button" class="btn-black-outline" style="height:36px; padding:0 16px; font-size:13px; font-weight:700; border-radius:8px;" onclick="document.getElementById('bankEmailEscalationModal').style.display='none'">Cancel</button>
                <button type="submit" id="bee_submit_btn" class="btn-black-solid" style="height:36px; padding:0 18px; font-size:13px; font-weight:800; border-radius:8px; display:inline-flex; align-items:center; gap:6px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                    Dispatch Corporate Email
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Generate / Create Bank Invoice Modal -->
<div id="generateInvoiceModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); backdrop-filter:blur(4px); z-index:9999; justify-content:center; align-items:center;">
    <div style="background:#fff; padding:28px; border-radius:16px; width:520px; max-width:94%; max-height:92vh; overflow-y:auto; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; border-bottom:1px solid #e2e8f0; padding-bottom:12px;">
            <div>
                <h3 style="margin:0; font-size:18px; font-weight:800; color:#0f172a;">Generate Bank Claim Tax Invoice</h3>
                <div style="font-size:12px; color:#64748b;">GST Tax Invoice (SAC 997159) for bank commission recovery</div>
            </div>
            <button type="button" onclick="document.getElementById('generateInvoiceModal').style.display='none'" style="background:none; border:none; font-size:22px; color:#64748b; cursor:pointer;">&times;</button>
        </div>
        
        <form onsubmit="saveInvoice(event)">
            
            <!-- Advanced Smart Live Combobox Case Picker (Production Scale Multi-Attribute Search) -->
            <div style="margin-bottom: 16px; position:relative;" id="inv_case_picker_container">
                <label style="display:block; margin-bottom:6px; font-weight:700; font-size:12px; color:#334155;">Select Disbursed Customer Case</label>
                
                <input type="hidden" id="inv_applicant_id" required>
                
                <div style="position:relative;">
                    <input type="text" id="inv_case_search_input" placeholder="🔍 Type Customer Name, Loan ID, Bank, Loan Type, or Amount..." style="width:100%; padding:10px 36px 10px 14px; border:1.5px solid #cbd5e1; border-radius:8px; font-size:13px; font-weight:700; color:#0f172a; background:#fff;" oninput="onInvoiceCaseSearchInput(this.value)" onfocus="onInvoiceCaseSearchFocus()" autocomplete="off">
                    <div style="position:absolute; right:12px; top:50%; transform:translateY(-50%); pointer-events:none; color:#64748b;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"></polyline></svg>
                    </div>
                </div>

                <!-- Live Results Floating Dropdown -->
                <div id="inv_case_results_dropdown" style="display:none; position:absolute; top:100%; left:0; right:0; max-height:240px; overflow-y:auto; background:#fff; border:1.5px solid #0f172a; border-radius:8px; box-shadow:0 15px 30px -5px rgba(0,0,0,0.2); z-index:10000; margin-top:4px;">
                    <!-- Dynamically populated via JS -->
                </div>
            </div>

            <!-- Financial Claim Amounts Inputs -->
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom: 16px;">
                <div>
                    <label style="display:block; margin-bottom:6px; font-weight:700; font-size:12px; color:#334155;">Disbursed Amount (₹)</label>
                    <input type="number" id="inv_disbursed_amount" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px; font-weight:800; color:#0f172a;" oninput="calcInvoice()" required>
                </div>
                <div>
                    <label style="display:block; margin-bottom:6px; font-weight:700; font-size:12px; color:#334155;">Commission Claim (%)</label>
                    <input type="number" step="0.01" min="0.1" max="15" id="inv_commission_pct" value="1.50" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px; font-weight:800; color:#0f172a;" oninput="calcInvoice()" required>
                </div>
            </div>

            <!-- Executive 4-Card Tax Breakdown Summary -->
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:16px; margin-bottom:16px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                    <span style="font-size:11px; font-weight:800; color:#0f172a; text-transform:uppercase; letter-spacing:0.5px;">Tax Claim Breakdown (SAC 997159)</span>
                    <span id="inv_bank_badge" class="badge-mono-bank" style="font-size:10px;">Select Case</span>
                </div>
                
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:10px; font-size:12px;">
                    <div style="background:#fff; padding:10px; border-radius:8px; border:1px solid #e2e8f0;">
                        <div style="color:#64748b; font-size:10px; font-weight:700; text-transform:uppercase;">Gross Base Commission</div>
                        <div id="inv_card_gross" style="font-weight:900; color:#0f172a; font-size:14px; margin-top:2px;">₹0.00</div>
                    </div>
                    <div style="background:#fff; padding:10px; border-radius:8px; border:1px solid #e2e8f0;">
                        <div style="color:#2563eb; font-size:10px; font-weight:700; text-transform:uppercase;">+ GST (18%)</div>
                        <div id="inv_card_gst" style="font-weight:900; color:#2563eb; font-size:14px; margin-top:2px;">+₹0.00</div>
                    </div>
                    <div style="background:#fff; padding:10px; border-radius:8px; border:1px solid #e2e8f0;">
                        <div style="color:#dc2626; font-size:10px; font-weight:700; text-transform:uppercase;">- TDS (2% Sec 194H)</div>
                        <div id="inv_card_tds" style="font-weight:900; color:#dc2626; font-size:14px; margin-top:2px;">-₹0.00</div>
                    </div>
                    <div style="background:#0f172a; padding:10px; border-radius:8px; color:#fff;">
                        <div style="color:#94a3b8; font-size:10px; font-weight:700; text-transform:uppercase;">Net Claim Receivable</div>
                        <div id="inv_card_net" style="font-weight:900; color:#22c55e; font-size:15px; margin-top:2px;">₹0.00</div>
                    </div>
                </div>

                <div id="duplicate_invoice_warning" style="display:none; font-size:11px; font-weight:700; color:#dc2626; background:#fef2f2; padding:8px 10px; border-radius:6px; border:1px solid #fca5a5;">
                    ⚠️ A Bank Claim Invoice already exists for this case! Submitting will update the existing invoice.
                </div>
            </div>

            <!-- Hidden Inputs for Form Serializer Compatibility -->
            <input type="hidden" id="inv_gst">
            <input type="hidden" id="inv_tds">
            <input type="hidden" id="inv_net">

            <div style="margin-bottom: 20px;">
                <label style="display:block; margin-bottom:6px; font-weight:700; font-size:12px; color:#334155;">Claim Notes / Remarks</label>
                <textarea id="inv_notes" rows="2" placeholder="e.g. GST claim invoice submitted to Bank Nodal Officer" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; resize:none; font-size:12px;"></textarea>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="btn-black-outline" onclick="document.getElementById('generateInvoiceModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn-black-solid" id="btn_save_invoice" style="font-weight:800;">Generate Tax Invoice</button>
            </div>
        </form>
    </div>
</div>

<!-- Record Incoming Bank Payment Modal -->
<div id="recordBankPayModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); backdrop-filter:blur(4px); z-index:9999; justify-content:center; align-items:center;">
    <div style="background:#fff; padding:28px; border-radius:16px; width:450px; max-width:90%; box-shadow:0 20px 25px -5px rgba(0,0,0,0.2);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid #f1f5f9; padding-bottom:12px;">
            <h3 style="margin:0; font-size:18px; font-weight:800; color:#0f172a;">Record Incoming Bank Commission</h3>
            <button type="button" onclick="document.getElementById('recordBankPayModal').style.display='none'" style="background:none; border:none; font-size:20px; color:#64748b; cursor:pointer;">&times;</button>
        </div>
        <form onsubmit="submitRecordBankPay(event)">
            <input type="hidden" id="rbp_applicant_id">
            
            <div style="margin-bottom: 16px;">
                <label style="display:block; margin-bottom:6px; font-weight:700; font-size:13px; color:#334155;">Customer / Loan Case</label>
                <input type="text" id="rbp_customer_name" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; background:#f8fafc; font-weight:700;" readonly>
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display:block; margin-bottom:6px; font-weight:700; font-size:13px; color:#334155;">Commission Received Amount (₹) <span style="color:red">*</span></label>
                <input type="number" step="0.01" id="rbp_received_amount" placeholder="e.g. 45000" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; font-size:14px; font-weight:800; color:#10b981;" required>
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display:block; margin-bottom:6px; font-weight:700; font-size:13px; color:#334155;">Bank UTR / Transaction Ref No.</label>
                <input type="text" id="rbp_bank_utr" placeholder="e.g. HDFC129048102" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px;" required>
            </div>

            <div style="margin-bottom: 24px;">
                <label style="display:block; margin-bottom:6px; font-weight:700; font-size:13px; color:#334155;">Payment Received Date</label>
                <input type="date" id="rbp_received_date" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px;" required>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('recordBankPayModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary" style="font-weight:700;">Confirm Receipt</button>
            </div>
        </form>
    </div>
</div>

<!-- Mark Invoice Received Modal -->
<div id="markReceivedModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); backdrop-filter:blur(4px); z-index:9999; justify-content:center; align-items:center;">
    <div style="background:#fff; padding:28px; border-radius:16px; width:450px; max-width:90%; box-shadow:0 20px 25px -5px rgba(0,0,0,0.2);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid #f1f5f9; padding-bottom:12px;">
            <h3 style="margin:0; font-size:18px; font-weight:800; color:#0f172a;">Mark Invoice as Received</h3>
            <button type="button" onclick="document.getElementById('markReceivedModal').style.display='none'" style="background:none; border:none; font-size:20px; color:#64748b; cursor:pointer;">&times;</button>
        </div>
        <form onsubmit="saveReceived(event)">
            <input type="hidden" id="recv_invoice_id">
            <input type="hidden" id="recv_net_receivable">
            
            <div style="margin-bottom: 16px;">
                <label style="display:block; margin-bottom:6px; font-weight:700; font-size:13px; color:#334155;">Expected Claim Amount (₹)</label>
                <input type="text" id="recv_expected" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; background:#f8fafc; font-weight:700;" readonly>
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display:block; margin-bottom:6px; font-weight:700; font-size:13px; color:#334155;">Received Amount (₹)</label>
                <input type="number" step="0.01" id="recv_amount" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; font-size:14px; font-weight:800; color:#10b981;" oninput="calcShortfall()" required>
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display:block; margin-bottom:6px; font-weight:700; font-size:13px; color:#334155;">Shortfall / Haircut Warning</label>
                <div id="recv_shortfall" style="font-size:13px; color:#ef4444; font-weight:700;">No shortfall.</div>
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display:block; margin-bottom:6px; font-weight:700; font-size:13px; color:#334155;">Received Date</label>
                <input type="date" id="recv_date" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px;" required>
            </div>

            <div style="margin-bottom: 24px;">
                <label style="display:block; margin-bottom:6px; font-weight:700; font-size:13px; color:#334155;">Bank Transfer UTR Number</label>
                <input type="text" id="recv_utr" placeholder="e.g. HDFC-UTR-908124" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px;" required>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('markReceivedModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary" id="btn_save_recv" style="font-weight:700;">Confirm Received</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Payee Bank Details Modal -->
<div id="editPayeeBankModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); backdrop-filter:blur(4px); z-index:9999; justify-content:center; align-items:center;">
    <div style="background:#fff; padding:28px; border-radius:16px; width:480px; max-width:90%; box-shadow:0 20px 25px -5px rgba(0,0,0,0.2);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid #f1f5f9; padding-bottom:12px;">
            <h3 style="margin:0; font-size:18px; font-weight:800; color:#0f172a;">Edit Payee Banking Info</h3>
            <button type="button" onclick="document.getElementById('editPayeeBankModal').style.display='none'" style="background:none; border:none; font-size:20px; color:#64748b; cursor:pointer;">&times;</button>
        </div>
        <form onsubmit="savePayeeBankDetails(event)">
            <input type="hidden" id="epb_source_type">
            <input type="hidden" id="epb_user_id">
            <input type="hidden" id="epb_referral_id">
            
            <div style="margin-bottom: 14px;">
                <label style="display:block; margin-bottom:6px; font-weight:700; font-size:13px; color:#334155;">Payee Name</label>
                <input type="text" id="epb_payee_name" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; background:#f8fafc; font-weight:700;" readonly>
            </div>

            <div style="margin-bottom: 14px;">
                <label style="display:block; margin-bottom:6px; font-weight:700; font-size:13px; color:#334155;">Bank Name <span style="color:red">*</span></label>
                <input type="text" id="epb_bank_name" placeholder="e.g. HDFC Bank, ICICI Bank, State Bank of India" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px;" required>
            </div>

            <div style="margin-bottom: 14px;">
                <label style="display:block; margin-bottom:6px; font-weight:700; font-size:13px; color:#334155;">Bank Account Number <span style="color:red">*</span></label>
                <input type="text" id="epb_account_number" placeholder="Enter bank account number" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px;" required>
            </div>

            <div style="margin-bottom: 14px;">
                <label style="display:block; margin-bottom:6px; font-weight:700; font-size:13px; color:#334155;">IFSC Code <span style="color:red">*</span></label>
                <input type="text" id="epb_ifsc_code" placeholder="e.g. HDFC0001234" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px; text-transform:uppercase;" required>
            </div>

            <div style="margin-bottom: 24px;">
                <label style="display:block; margin-bottom:6px; font-weight:700; font-size:13px; color:#334155;">PAN Number</label>
                <input type="text" id="epb_pan_number" placeholder="e.g. ABCDE1234F" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px; text-transform:uppercase;">
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('editPayeeBankModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary" style="font-weight:700;">Save Banking Details</button>
            </div>
        </form>
    </div>
</div>

<!-- Generate / Create New Outgoing Payout Modal -->
<div id="addPayoutModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); backdrop-filter:blur(4px); z-index:9999; justify-content:center; align-items:center;">
    <div style="background:#fff; padding:28px; border-radius:16px; width:520px; max-width:92%; max-height:90vh; overflow-y:auto; box-shadow:0 20px 25px -5px rgba(0,0,0,0.2);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid #f1f5f9; padding-bottom:12px;">
            <h3 style="margin:0; font-size:18px; font-weight:800; color:#0f172a; display:flex; align-items:center; gap:8px;">
                <i data-lucide="plus-circle" style="width:20px; color:#0f172a;"></i> Generate New Payout Voucher
            </h3>
            <button type="button" onclick="document.getElementById('addPayoutModal').style.display='none'" style="background:none; border:none; font-size:20px; color:#64748b; cursor:pointer;">&times;</button>
        </div>
        <form onsubmit="submitAddPayout(event)">
            <div style="margin-bottom: 14px;">
                <label style="display:block; margin-bottom:4px; font-weight:700; font-size:13px; color:#334155;">Select Disbursed Customer Case</label>
                <select id="np_applicant_id" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px;" onchange="autoFillNewPayout(this)" required>
                    <option value="0">-- Direct Payout (No Loan Case Link) --</option>
                    <?php foreach ($eligible_applicants as $ea): ?>
                        <option value="<?= $ea['id'] ?>" data-amount="<?= $ea['loan_amount_requested'] ?>" data-bank="<?= htmlspecialchars($ea['sanction_bank'] ?: '') ?>">
                            <?= htmlspecialchars($ea['customer_name'] . ' [' . $ea['loan_id'] . '] - ₹' . number_format($ea['loan_amount_requested'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="margin-bottom: 14px;">
                <label style="display:block; margin-bottom:4px; font-weight:700; font-size:13px; color:#334155;">Select Recipient Payee (Staff / Partner / Referral)</label>
                <select id="np_payee_user_id" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px;" required>
                    <option value="">-- Choose Payee --</option>
                    <?php foreach ($eligible_payees as $ep): ?>
                        <option value="<?= $ep['id'] ?>">
                            <?= htmlspecialchars(($ep['name'] ?: $ep['username']) . ' [' . $ep['role'] . ']') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom: 14px;">
                <div>
                    <label style="display:block; margin-bottom:4px; font-weight:700; font-size:13px; color:#334155;">Total Loan Amount (₹)</label>
                    <input type="number" id="np_total_loan_amount" placeholder="e.g. 2500000" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px; font-weight:700;" oninput="calcNewPayout()">
                </div>
                <div>
                    <label style="display:block; margin-bottom:4px; font-weight:700; font-size:13px; color:#334155;">Commission Rate (%)</label>
                    <input type="number" step="0.01" id="np_commission_pct" placeholder="e.g. 1.00" value="1.00" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px; font-weight:700;" oninput="calcNewPayout()">
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom: 14px;">
                <div>
                    <label style="display:block; margin-bottom:4px; font-weight:700; font-size:13px; color:#334155;">Gross Payout (₹)</label>
                    <input type="number" step="0.01" id="np_gross_payout" placeholder="e.g. 25000" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px; font-weight:800; color:#0f172a;" oninput="calcNewPayoutTds()" required>
                </div>
                <div>
                    <label style="display:block; margin-bottom:4px; font-weight:700; font-size:13px; color:#334155;">Less TDS (5%)</label>
                    <input type="text" id="np_tds_deducted" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; background:#f8fafc; font-size:13px; color:#dc2626; font-weight:700;" readonly>
                </div>
            </div>

            <div style="margin-bottom: 14px; padding:12px 16px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:10px; display:flex; justify-content:space-between; align-items:center;">
                <span style="font-weight:700; color:#166534; font-size:13px;">Net Payable Disbursal Amount</span>
                <input type="text" id="np_net_payable" style="border:none; background:transparent; font-size:20px; font-weight:900; color:#166534; text-align:right;" readonly>
            </div>

            <div style="margin-bottom: 14px;">
                <label style="display:block; margin-bottom:4px; font-weight:700; font-size:13px; color:#334155;">Payment Mode</label>
                <select id="np_payment_mode" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px;">
                    <option value="NEFT Transfer">NEFT Transfer</option>
                    <option value="IMPS Transfer">IMPS Transfer</option>
                    <option value="RTGS Transfer">RTGS Transfer</option>
                    <option value="UPI Direct">UPI Direct</option>
                    <option value="Bank Cheque">Bank Cheque</option>
                </select>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display:block; margin-bottom:4px; font-weight:700; font-size:13px; color:#334155;">Remarks / Notes</label>
                <textarea id="np_remarks" rows="2" placeholder="e.g. Sourcing payout for HDFC loan disbursal" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; resize:none; font-size:13px;"></textarea>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('addPayoutModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn-black-solid" style="font-weight:700;">Generate Payout Voucher</button>
            </div>
        </form>
    </div>
</div>

<!-- Bulk Disburse Selected Payouts Modal -->
<div id="bulkDisburseModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); backdrop-filter:blur(4px); z-index:9999; justify-content:center; align-items:center;">
    <div style="background:#fff; padding:28px; border-radius:16px; width:460px; max-width:92%; box-shadow:0 20px 25px -5px rgba(0,0,0,0.2);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid #f1f5f9; padding-bottom:12px;">
            <h3 style="margin:0; font-size:18px; font-weight:800; color:#0f172a; display:flex; align-items:center; gap:8px;">
                <i data-lucide="zap" style="width:20px; color:#0f172a;"></i> Bulk Disburse Selected Payouts
            </h3>
            <button type="button" onclick="document.getElementById('bulkDisburseModal').style.display='none'" style="background:none; border:none; font-size:20px; color:#64748b; cursor:pointer;">&times;</button>
        </div>
        <form onsubmit="submitBulkDisburse(event)">
            <div style="padding:12px 16px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; margin-bottom:16px;">
                <div style="font-size:13px; font-weight:700; color:#0f172a;" id="bulk_summary_count">Selected: 0 payouts</div>
                <div style="font-size:15px; font-weight:900; color:#10b981; margin-top:2px;" id="bulk_summary_amount">Total Payable: ₹0.00</div>
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display:block; margin-bottom:6px; font-weight:700; font-size:13px; color:#334155;">Transfer Mode</label>
                <select id="bd_payment_mode" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px;" required>
                    <option value="NEFT Bulk Batch">NEFT Bulk Batch Transfer</option>
                    <option value="RTGS Bulk Transfer">RTGS Corporate Transfer</option>
                    <option value="IMPS Direct">IMPS Instant Batch</option>
                    <option value="Bank Cheque Book">Cheque Book Issue</option>
                </select>
            </div>

            <div style="margin-bottom: 24px;">
                <label style="display:block; margin-bottom:6px; font-weight:700; font-size:13px; color:#334155;">Bank Batch Reference / UTR Ref No. <span style="color:red">*</span></label>
                <input type="text" id="bd_transaction_ref" placeholder="e.g. HDFC-BATCH-20260922-01" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px;" required>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('bulkDisburseModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn-black-solid" style="font-weight:700;">Confirm Bulk Disbursal</button>
            </div>
        </form>
    </div>
</div>

<!-- Payment Voucher Printable Modal -->
<div id="paymentVoucherModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); backdrop-filter:blur(4px); z-index:9999; justify-content:center; align-items:center;">
    <div style="background:#fff; padding:32px; border-radius:16px; width:680px; max-width:92%; max-height:90vh; overflow-y:auto; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);">
        
        <div id="payment-voucher-print-area">
            <!-- Header Letterhead -->
            <div style="display:flex; justify-content:space-between; align-items:flex-start; border-bottom:2px solid #0f172a; padding-bottom:16px; margin-bottom:20px;">
                <div>
                    <h1 style="margin:0; font-size:22px; font-weight:900; color:#0f172a; letter-spacing:-0.5px;">BFS FINANCIAL SERVICES PVT LTD</h1>
                    <div style="font-size:12px; color:#334155; font-weight:600;">Corporate Office: Connaught Place, New Delhi - 110001</div>
                    <div style="font-size:11px; color:#64748b;">GSTIN: 07AAACB1234F1Z5 • PAN: AAACB1234F</div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:16px; font-weight:900; color:#0f172a; text-transform:uppercase;">PAYMENT VOUCHER</div>
                    <div id="pv_voucher_no" style="font-size:13px; font-weight:700; color:#0f172a; margin-top:2px;">#PD-000</div>
                    <div id="pv_date" style="font-size:11px; color:#334155; font-weight:600;">Date: -</div>
                </div>
            </div>

            <!-- Recipient & Loan Case Reference -->
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:20px; font-size:13px;">
                <div style="background:#f8fafc; padding:14px; border-radius:10px; border:1px solid #e2e8f0;">
                    <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; margin-bottom:4px;">Payee Details (Recipient)</div>
                    <div id="pv_payee_name" style="font-weight:800; color:#0f172a; font-size:15px;">-</div>
                    <div id="pv_payee_role" style="color:#334155; font-weight:700; font-size:12px;">Role: -</div>
                    <div id="pv_payee_bank" style="color:#334155; font-size:11px; margin-top:4px;">Bank: -</div>
                    <div id="pv_payee_acc" style="color:#334155; font-size:11px; font-family:monospace;">A/C: -</div>
                </div>
                <div style="background:#f8fafc; padding:14px; border-radius:10px; border:1px solid #e2e8f0;">
                    <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; margin-bottom:4px;">Disbursed Customer Case</div>
                    <div id="pv_customer_name" style="font-weight:800; color:#0f172a;">Customer: -</div>
                    <div id="pv_loan_id" style="color:#0f172a; font-weight:700;">Loan Ref: -</div>
                    <div id="pv_sanction_bank" style="color:#334155; font-weight:700; font-size:12px; margin-top:2px;">Lending Bank: -</div>
                </div>
            </div>

            <!-- Voucher Breakdown Table -->
            <table style="width:100%; border-collapse:collapse; margin-bottom:20px; font-size:13px;">
                <thead>
                    <tr style="background:#0f172a; color:#fff; text-align:left;">
                        <th style="padding:10px 12px; background:#0f172a !important; color:#fff !important;">Description</th>
                        <th style="padding:10px 12px; text-align:right; background:#0f172a !important; color:#fff !important;">Gross Payout</th>
                        <th style="padding:10px 12px; text-align:right; background:#0f172a !important; color:#fff !important;">TDS Deducted (5%)</th>
                        <th style="padding:10px 12px; text-align:right; background:#0f172a !important; color:#fff !important;">Net Disbursed</th>
                    </tr>
                </thead>
                <tbody>
                    <tr style="border-bottom:1px solid #e2e8f0;">
                        <td style="padding:12px;">
                            <strong style="color:#0f172a;">Loan DSA Sourcing & Processing Commission</strong>
                            <div style="font-size:11px; color:#64748b;" id="pv_remarks">Payout disbursement under statutory TDS</div>
                        </td>
                        <td id="pv_gross_payout" style="padding:12px; text-align:right; font-weight:700; color:#0f172a;">₹0.00</td>
                        <td id="pv_tds_deducted" style="padding:12px; text-align:right; font-weight:700; color:#dc2626;">-₹0.00</td>
                        <td id="pv_net_payable" style="padding:12px; text-align:right; font-weight:900; color:#0f172a; font-size:15px;">₹0.00</td>
                    </tr>
                </tbody>
            </table>

            <!-- Payment Bank Mode & UTR -->
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:14px; margin-bottom:20px; font-size:12px;">
                <div style="font-weight:700; color:#0f172a; text-transform:uppercase; margin-bottom:4px;">REMITTANCE TRANSACTION PROOF</div>
                <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:8px;">
                    <div>Payment Mode: <strong id="pv_payment_mode" style="color:#0f172a;">NEFT Transfer</strong></div>
                    <div>Bank UTR / Transaction Ref: <strong id="pv_utr" style="font-family:monospace; color:#0f172a;">-</strong></div>
                    <div>Disbursal Date: <strong id="pv_paid_on" style="color:#0f172a;">-</strong></div>
                </div>
            </div>

            <!-- Attached Payment Proof Screenshot if available -->
            <div id="pv_proof_container" style="display:none; margin-bottom:20px; padding:12px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; text-align:center;">
                <div style="font-size:11px; font-weight:700; color:#0f172a; text-transform:uppercase; margin-bottom:8px;">ATTACHED UPI / BANK PAYMENT SCREENSHOT PROOF</div>
                <img id="pv_proof_img" src="" style="max-height:220px; border-radius:8px; border:1px solid #cbd5e1; object-fit:contain;">
            </div>

            <!-- Signatures -->
            <div style="display:flex; justify-content:space-between; margin-top:30px; padding-top:16px; border-top:1px dashed #cbd5e1; font-size:12px; color:#64748b;">
                <div>Authorized Signatory (Payee)</div>
                <div style="text-align:right; font-weight:700; color:#0f172a;">For BFS Financial Services Pvt Ltd</div>
            </div>
        </div>

        <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:24px; border-top:1px solid #f1f5f9; padding-top:16px;">
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('paymentVoucherModal').style.display='none'">Close</button>
            <button type="button" class="btn-black-solid" onclick="printPaymentVoucher()" style="font-weight:700; display:flex; align-items:center; gap:6px;">
                <i data-lucide="printer" style="width:16px;"></i> Print / Save PDF Voucher
            </button>
        </div>
    </div>
</div>

<!-- Action Modal: Update Outgoing Status -->
<div id="actionModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); backdrop-filter:blur(4px); z-index:9999; justify-content:center; align-items:center;">
    <div style="background:#fff; padding:28px; border-radius:16px; width:450px; max-width:90%; box-shadow:0 20px 25px -5px rgba(0,0,0,0.2);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid #f1f5f9; padding-bottom:12px;">
            <h3 style="margin:0; font-size:18px; font-weight:800; color:#0f172a;">Update Outgoing Payout Status</h3>
            <button type="button" onclick="closeActionModal()" style="background:none; border:none; font-size:20px; color:#64748b; cursor:pointer;">&times;</button>
        </div>
        <form onsubmit="submitActionStatus(event)">
            <input type="hidden" id="modal_payout_id">
            
            <div style="margin-bottom: 16px;">
                <label style="display:block; margin-bottom:6px; font-weight:700; font-size:13px; color:#334155;">Select New Status</label>
                <select id="modal_status" onchange="toggleModalFields()" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px; font-weight:600;" required>
                    <option value="Pending">Pending</option>
                    <option value="Approved">Approved</option>
                    <option value="Hold">Hold</option>
                    <option value="Paid">Paid (Disbursed)</option>
                    <option value="Cancelled">Cancelled</option>
                </select>
            </div>
            
            <div id="div_payment_mode" style="display:none; margin-bottom: 16px;">
                <label style="display:block; margin-bottom:6px; font-weight:700; font-size:13px; color:#334155;">Payment Transfer Mode</label>
                <select id="modal_payment_mode" onchange="toggleModalFields()" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px;">
                    <option value="NEFT Transfer">NEFT (National Electronic Funds Transfer)</option>
                    <option value="IMPS Transfer">IMPS (Instant Payment)</option>
                    <option value="RTGS Transfer">RTGS (Real Time Gross Settlement)</option>
                    <option value="UPI Direct">UPI Direct Credited</option>
                    <option value="Bank Cheque">Bank Cheque Issue</option>
                </select>
            </div>

            <div id="div_transaction" style="display:none; margin-bottom: 16px;">
                <label style="display:block; margin-bottom:6px; font-weight:700; font-size:13px; color:#334155;">Bank UTR / Transaction Ref No. <span style="color:red">*</span></label>
                <input type="text" id="modal_transaction" placeholder="e.g. UTR19840291048 or N1204812" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px;">
            </div>

            <div id="div_payment_proof" style="display:none; margin-bottom: 16px;">
                <label style="display:block; margin-bottom:6px; font-weight:700; font-size:13px; color:#334155;">
                    📷 Upload UPI / Bank Payment Screenshot Proof <span style="font-weight:400; color:#64748b;">(Optional)</span>
                </label>
                <input type="file" id="modal_payment_proof" accept="image/*,.pdf" style="width:100%; padding:8px; border:1.5px dashed #0f172a; border-radius:8px; font-size:12px; background:#f8fafc;" onchange="previewProofImage(this)">
                <div id="existing_proof_box" style="margin-top:8px; font-size:12px; display:none;"></div>
                <img id="modal_proof_img_preview" style="display:none; max-height:120px; border-radius:8px; border:1px solid #cbd5e1; margin-top:8px; object-fit:contain;">
            </div>

            <div id="div_cancel_reason" style="display:none; margin-bottom: 16px;">
                <label style="display:block; margin-bottom:6px; font-weight:700; font-size:13px; color:#334155;">Reason for Hold / Cancellation <span style="color:red">*</span></label>
                <textarea id="modal_cancel_reason" placeholder="State reason..." style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; resize:none; font-size:13px;" rows="3"></textarea>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:24px;">
                <button type="button" class="btn btn-secondary" onclick="closeActionModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" style="font-weight:700;">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Lightbox Modal: View Payment Proof Screenshot -->
<div id="proofLightboxModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.85); backdrop-filter:blur(6px); z-index:99999; justify-content:center; align-items:center;">
    <div style="background:#fff; padding:24px; border-radius:16px; width:650px; max-width:94%; max-height:90vh; overflow-y:auto; box-shadow:0 25px 50px -12px rgba(0,0,0,0.5); position:relative;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; border-bottom:1px solid #e2e8f0; padding-bottom:12px;">
            <h3 style="margin:0; font-size:18px; font-weight:800; color:#0f172a; display:flex; align-items:center; gap:8px;" id="plm_title">
                📷 Payment Transfer Screenshot / Proof
            </h3>
            <button type="button" onclick="document.getElementById('proofLightboxModal').style.display='none'" style="background:none; border:none; font-size:24px; color:#64748b; cursor:pointer;">&times;</button>
        </div>
        <div style="text-align:center; padding:10px;" id="plm_content">
            <!-- Image / PDF loaded dynamically -->
        </div>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:16px; border-top:1px solid #f1f5f9; padding-top:12px;">
            <a id="plm_download_btn" href="" target="_blank" download class="btn-black-outline" style="font-size:12px;">📥 Download Full File</a>
            <button type="button" class="btn-black-solid" onclick="document.getElementById('proofLightboxModal').style.display='none'" style="font-size:12px;">Close</button>
        </div>
    </div>
</div>

<!-- HTML Modal: Dispatch Outgoing Payout Voucher Email -->
<div id="emailPayoutVoucherModal" onclick="if(event.target===this) closeEmailPayoutModal()" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.7); backdrop-filter:blur(4px); z-index:99999; justify-content:center; align-items:center;">
    <div style="background:#ffffff; border-radius:16px; width:460px; max-width:92%; box-shadow:0 25px 50px -12px rgba(0,0,0,0.35); overflow:hidden;">
        <!-- Header Banner -->
        <div style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); padding: 20px 24px; color: #fff; display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid #3b82f6;">
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="background: rgba(255,255,255,0.12); width:40px; height:40px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:20px;">
                    ✉️
                </div>
                <div>
                    <div style="font-size:16px; font-weight:800; color:#ffffff; letter-spacing:-0.2px;">Dispatch Payout Voucher</div>
                    <div style="font-size:11px; color:#94a3b8; margin-top:2px;">BFS Financial Official Email Delivery</div>
                </div>
            </div>
            <div style="display:flex; align-items:center; gap:10px;">
                <span id="epm_voucher_no" style="background: rgba(59,130,246,0.18); border: 1px solid rgba(96,165,250,0.3); color: #60a5fa; font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 20px;">
                    #PD-000
                </span>
                <button type="button" onclick="closeEmailPayoutModal()" title="Close Modal" style="background:rgba(255,255,255,0.18); border:none; color:#ffffff; font-size:22px; width:30px; height:30px; border-radius:50%; cursor:pointer; display:flex; align-items:center; justify-content:center; line-height:1; transition:all 0.15s;" onmouseover="this.style.background='rgba(239,68,68,0.8)'" onmouseout="this.style.background='rgba(255,255,255,0.18)'">&times;</button>
            </div>
        </div>

        <!-- Form Body -->
        <form onsubmit="submitEmailPayoutVoucher(event)" style="margin:0;">
            <input type="hidden" id="epm_payout_id">
            <div style="padding: 20px 24px;">
                <!-- Details Card -->
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px; margin-bottom: 16px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                        <span style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;">Payee Recipient</span>
                        <strong id="epm_payee_name" style="font-size:13px; color:#0f172a; font-weight:800;">-</strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                        <span style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;">Case / Loan Ref</span>
                        <span id="epm_case_ref" style="font-size:12px; color:#334155; font-weight:700;">-</span>
                    </div>
                    <div style="display:flex; justify-content:space-between; align-items:center; padding-top:8px; border-top:1px dashed #cbd5e1;">
                        <span style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;">Net Disbursed Amount</span>
                        <span id="epm_net_payable" style="font-size:15px; font-weight:900; color:#059669; background:#dcfce7; padding:2px 10px; border-radius:6px; border:1px solid #a7f3d0;">₹0.00</span>
                    </div>
                </div>

                <!-- Email Input Box -->
                <div style="margin-bottom: 14px;">
                    <label style="display:block; font-size:11px; font-weight:800; color:#0f172a; margin-bottom:6px; text-transform:uppercase; letter-spacing:0.4px;">
                        📧 Recipient Email Address <span style="color:#ef4444;">*</span>
                    </label>
                    <input type="email" id="epm_email_input" required placeholder="Enter recipient email (e.g. name@domain.com)..." 
                        style="width:100%; box-sizing:border-box; padding:11px 14px; border:1.5px solid #cbd5e1; border-radius:10px; font-size:13px; color:#0f172a; font-weight:600; outline:none;"
                        onfocus="this.style.borderColor='#0f172a'; this.style.boxShadow='0 0 0 3px rgba(15,23,42,0.15)';"
                        onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';"
                    >
                </div>

                <!-- Notice Banner -->
                <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:10px 12px; font-size:11px; color:#1e40af; display:flex; align-items:center; gap:8px; margin-bottom:20px;">
                    <span style="font-size:14px;">ℹ️</span>
                    <span>An official PDF Payout Advice Receipt with statutory TDS breakdown will be emailed automatically.</span>
                </div>

                <!-- Footer Buttons -->
                <div style="display:flex; justify-content:flex-end; gap:10px; border-top:1px solid #f1f5f9; padding-top:16px;">
                    <button type="button" onclick="closeEmailPayoutModal()" style="padding:10px 18px; border:1px solid #cbd5e1; background:#ffffff; color:#475569; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer;">Cancel</button>
                    <button type="submit" id="epm_submit_btn" style="padding:10px 20px; border:none; background:#0f172a; color:#ffffff; border-radius:8px; font-size:13px; font-weight:800; cursor:pointer; display:flex; align-items:center; gap:6px;">⚡ Send Email Voucher</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- HTML Modal: Dispatch Bank Tax Invoice Email -->
<div id="emailBankTaxInvoiceModal" onclick="if(event.target===this) closeEmailBankTaxInvoiceModal()" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.7); backdrop-filter:blur(4px); z-index:99999; justify-content:center; align-items:center;">
    <div style="background:#ffffff; border-radius:16px; width:460px; max-width:92%; box-shadow:0 25px 50px -12px rgba(0,0,0,0.35); overflow:hidden;">
        <!-- Header Banner -->
        <div style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); padding: 20px 24px; color: #fff; display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid #2563eb;">
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="background: rgba(255,255,255,0.12); width:40px; height:40px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:20px;">
                    🏦
                </div>
                <div>
                    <div style="font-size:16px; font-weight:800; color:#ffffff; letter-spacing:-0.2px;">Dispatch Bank Tax Invoice</div>
                    <div style="font-size:11px; color:#94a3b8; margin-top:2px;">Nodal Finance & Accounts Submission</div>
                </div>
            </div>
            <div style="display:flex; align-items:center; gap:10px;">
                <span id="ebim_invoice_no" style="background: rgba(37,99,235,0.2); border: 1px solid rgba(96,165,250,0.3); color: #60a5fa; font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 20px;">
                    INV-000
                </span>
                <button type="button" onclick="closeEmailBankTaxInvoiceModal()" title="Close Modal" style="background:rgba(255,255,255,0.18); border:none; color:#ffffff; font-size:22px; width:30px; height:30px; border-radius:50%; cursor:pointer; display:flex; align-items:center; justify-content:center; line-height:1; transition:all 0.15s;" onmouseover="this.style.background='rgba(239,68,68,0.8)'" onmouseout="this.style.background='rgba(255,255,255,0.18)'">&times;</button>
            </div>
        </div>

        <!-- Form Body -->
        <form onsubmit="submitEmailBankTaxInvoice(event)" style="margin:0;">
            <input type="hidden" id="ebim_invoice_id">
            <div style="padding: 20px 24px;">
                <!-- Details Card -->
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px; margin-bottom: 16px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                        <span style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;">Lending Partner Bank</span>
                        <strong id="ebim_bank_name" style="font-size:13px; color:#0f172a; font-weight:800;">-</strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                        <span style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;">Customer Case</span>
                        <span id="ebim_customer_name" style="font-size:12px; color:#334155; font-weight:700;">-</span>
                    </div>
                    <div style="display:flex; justify-content:space-between; align-items:center; padding-top:8px; border-top:1px dashed #cbd5e1;">
                        <span style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;">Invoice Net Amount</span>
                        <span id="ebim_net_amt" style="font-size:15px; font-weight:900; color:#1d4ed8; background:#eff6ff; padding:2px 10px; border-radius:6px; border:1px solid #bfdbfe;">₹0.00</span>
                    </div>
                </div>

                <!-- Email Input Box -->
                <div style="margin-bottom: 14px;">
                    <label style="display:block; font-size:11px; font-weight:800; color:#0f172a; margin-bottom:6px; text-transform:uppercase; letter-spacing:0.4px;">
                        📧 Bank Nodal Email Address <span style="color:#ef4444;">*</span>
                    </label>
                    <input type="email" id="ebim_email_input" required placeholder="Enter bank nodal email..." 
                        style="width:100%; box-sizing:border-box; padding:11px 14px; border:1.5px solid #cbd5e1; border-radius:10px; font-size:13px; color:#0f172a; font-weight:600; outline:none;"
                        onfocus="this.style.borderColor='#0f172a'; this.style.boxShadow='0 0 0 3px rgba(15,23,42,0.15)';"
                        onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';"
                    >
                </div>

                <!-- Notice Banner -->
                <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:10px 12px; font-size:11px; color:#1e40af; display:flex; align-items:center; gap:8px; margin-bottom:20px;">
                    <span style="font-size:14px;">📄</span>
                    <span>Official GST Tax Invoice (SAC 997159) PDF will be attached to this email.</span>
                </div>

                <!-- Footer Buttons -->
                <div style="display:flex; justify-content:flex-end; gap:10px; border-top:1px solid #f1f5f9; padding-top:16px;">
                    <button type="button" onclick="closeEmailBankTaxInvoiceModal()" style="padding:10px 18px; border:1px solid #cbd5e1; background:#ffffff; color:#475569; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer;">Cancel</button>
                    <button type="submit" id="ebim_submit_btn" style="padding:10px 20px; border:none; background:#0f172a; color:#ffffff; border-radius:8px; font-size:13px; font-weight:800; cursor:pointer; display:flex; align-items:center; gap:6px;">⚡ Send Tax Invoice Email</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- HTML Modal: Fail-Safe Custom Notice Modal -->
<div id="customNoticeModal" onclick="if(event.target===this) closeNoticeModal()" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.65); backdrop-filter:blur(4px); z-index:999999; justify-content:center; align-items:center;">
    <div style="background:#ffffff; border-radius:16px; width:420px; max-width:90%; padding:28px; text-align:center; box-shadow:0 25px 50px -12px rgba(15,23,42,0.35); position:relative;">
        <button type="button" onclick="closeNoticeModal()" title="Close" style="position:absolute; top:14px; right:14px; background:rgba(15,23,42,0.06); border:none; width:30px; height:30px; border-radius:50%; font-size:20px; color:#64748b; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:all 0.15s;" onmouseover="this.style.background='rgba(239,68,68,0.15)'; this.style.color='#ef4444';" onmouseout="this.style.background='rgba(15,23,42,0.06)'; this.style.color='#64748b';">&times;</button>
        <div id="cnm_icon" style="font-size:46px; margin-bottom:12px; line-height:1;">ℹ️</div>
        <h3 id="cnm_title" style="margin:0 0 8px 0; font-size:19px; font-weight:800; color:#0f172a;">Notice</h3>
        <p id="cnm_text" style="margin:0 0 22px 0; font-size:13px; color:#475569; font-weight:600; line-height:1.5;">Notice text...</p>
        <button type="button" onclick="closeNoticeModal()" style="padding:11px 24px; background:#0f172a; color:#ffffff; border:none; border-radius:10px; font-size:13.5px; font-weight:800; cursor:pointer; width:100%; box-shadow:0 4px 6px -1px rgba(15,23,42,0.2); transition:all 0.15s;" onmouseover="this.style.background='#1e293b'" onmouseout="this.style.background='#0f172a'">OK, Understood</button>
    </div>
</div>

<script>
let currentPayoutsData = [];
let currentTrackerData = [];
let currentInvoicesData = [];
let noticeCallback = null;

function showAlert(title, text, icon = 'info') {
    return new Promise((resolve) => {
        let iconSymbol = 'ℹ️';
        if (icon === 'success') iconSymbol = '✅';
        if (icon === 'error') iconSymbol = '❌';
        if (icon === 'warning') iconSymbol = '⚠️';

        document.getElementById('cnm_icon').innerText = iconSymbol;
        document.getElementById('cnm_title').innerText = title || 'Notice';
        document.getElementById('cnm_text').innerText = text || '';
        
        noticeCallback = resolve;
        document.getElementById('customNoticeModal').style.display = 'flex';
    });
}

function closeNoticeModal() {
    document.getElementById('customNoticeModal').style.display = 'none';
    if (noticeCallback) {
        let cb = noticeCallback;
        noticeCallback = null;
        cb({ isConfirmed: true });
    }
}

function formatCurrency(amt) {
    return '₹' + Number(amt || 0).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

// ===== BUTTON CLICK HANDLERS & MODAL ENGINES =====

// 1. Add Payout Modal Helper
function openAddPayoutModal() {
    document.getElementById('addPayoutModal').style.display = 'flex';
}

function autoFillNewPayout(selectEl) {
    let opt = selectEl.options[selectEl.selectedIndex];
    let amt = parseFloat(opt.getAttribute('data-amount')) || 0;
    document.getElementById('np_total_loan_amount').value = amt;
    calcNewPayout();
}

function calcNewPayout() {
    let loanAmt = parseFloat(document.getElementById('np_total_loan_amount').value) || 0;
    let pct = parseFloat(document.getElementById('np_commission_pct').value) || 0;
    let gross = (loanAmt * pct) / 100;
    document.getElementById('np_gross_payout').value = gross ? gross.toFixed(2) : '';
    calcNewPayoutTds();
}

function calcNewPayoutTds() {
    let gross = parseFloat(document.getElementById('np_gross_payout').value) || 0;
    let tds = (gross * 5) / 100;
    let net = gross - tds;
    document.getElementById('np_tds_deducted').value = '₹' + tds.toFixed(2);
    document.getElementById('np_net_payable').value = '₹' + net.toFixed(2);
}

async function submitAddPayout(e) {
    e.preventDefault();
    let fd = new FormData();
    fd.append('applicant_id', document.getElementById('np_applicant_id').value);
    fd.append('payee_user_id', document.getElementById('np_payee_user_id').value);
    fd.append('total_loan_amount', document.getElementById('np_total_loan_amount').value);
    fd.append('commission_percentage', document.getElementById('np_commission_pct').value);
    fd.append('gross_payout', document.getElementById('np_gross_payout').value);
    fd.append('payment_mode', document.getElementById('np_payment_mode').value);
    fd.append('remarks', document.getElementById('np_remarks').value);

    try {
        const res = await fetch('api.php?api=add_payout_distribution', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            document.getElementById('addPayoutModal').style.display = 'none';
            showAlert('Voucher Generated!', data.message, 'success').then(() => {
                loadPayouts(1);
            });
        } else {
            showAlert('Error', data.error || 'Failed to create payout', 'error');
        }
    } catch(err) {
        showAlert('Error', 'Server communication error', 'error');
    }
}

// 2. Checkboxes & Bulk Disburse Helpers
function toggleSelectAll(el) {
    let checked = el.checked;
    document.querySelectorAll('.payout-row-checkbox').forEach(cb => {
        cb.checked = checked;
    });
}

function openBulkDisburseModal() {
    let checkedCbs = document.querySelectorAll('.payout-row-checkbox:checked');
    if (checkedCbs.length === 0) {
        showAlert('No Selection', 'Please check at least one payout row to disburse in bulk.', 'info');
        return;
    }
    let totalAmt = 0;
    checkedCbs.forEach(cb => {
        let pId = parseInt(cb.value);
        let pData = currentPayoutsData.find(x => x.id == pId);
        if (pData) {
            totalAmt += parseFloat(pData.net_payable || 0);
        }
    });
    document.getElementById('bulk_summary_count').innerText = `Selected: ${checkedCbs.length} payouts`;
    document.getElementById('bulk_summary_amount').innerText = `Total Payable: ₹${totalAmt.toLocaleString('en-IN', {minimumFractionDigits:2})}`;
    document.getElementById('bulkDisburseModal').style.display = 'flex';
}

async function submitBulkDisburse(e) {
    e.preventDefault();
    let checkedCbs = document.querySelectorAll('.payout-row-checkbox:checked');
    let ids = Array.from(checkedCbs).map(cb => parseInt(cb.value));

    let fd = new FormData();
    fd.append('ids', JSON.stringify(ids));
    fd.append('payment_mode', document.getElementById('bd_payment_mode').value);
    fd.append('transaction_ref', document.getElementById('bd_transaction_ref').value);

    try {
        const res = await fetch('api.php?api=bulk_disburse_payouts', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            document.getElementById('bulkDisburseModal').style.display = 'none';
            showAlert('Disbursed!', data.message, 'success').then(() => {
                loadPayouts(1);
            });
        } else {
            showAlert('Error', data.error, 'error');
        }
    } catch(err) {
        showAlert('Error', 'Server connection error', 'error');
    }
}

// 3. Export CSV Helper
function exportPayoutsCSV() {
    if (!currentPayoutsData || currentPayoutsData.length === 0) {
        showAlert('No Data', 'No payout records to export.', 'info');
        return;
    }
    let csv = ['Voucher ID,Customer Name,Loan ID,Sanctioned Bank,Loan Amount,Payee Name,Role,Mobile,Gross Payout,TDS 5%,Net Payable,Disbursal Status,Payment Mode,Transaction Ref / UTR,Bank Payout Status'];
    currentPayoutsData.forEach(p => {
        csv.push([
            `"#PD-${String(p.id).padStart(3, '0')}"`,
            `"${p.customer_name || ''}"`,
            `"${p.loan_id || ''}"`,
            `"${p.sanction_bank || ''}"`,
            p.total_loan_amount || 0,
            `"${p.payee_name || ''}"`,
            `"${p.payee_role || ''}"`,
            `"${p.payee_mobile || ''}"`,
            p.gross_payout || 0,
            p.tds_deducted || 0,
            p.net_payable || 0,
            `"${p.status || ''}"`,
            `"${p.payment_mode || ''}"`,
            `"${p.transaction_ref || ''}"`,
            `"${p.bank_payout_status || ''}"`
        ].join(','));
    });

    let blob = new Blob([csv.join('\n')], { type: 'text/csv' });
    let url = URL.createObjectURL(blob);
    let a = document.createElement('a');
    a.href = url;
    a.download = `BFS_Outgoing_Payouts_Master_${new Date().toISOString().slice(0,10)}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

// 4. Action Modal Helpers & Proof Lightbox
function openActionModalById(id) {
    let p = currentPayoutsData.find(x => x.id == id);
    if (!p) return;
    document.getElementById('modal_payout_id').value = p.id;
    document.getElementById('modal_status').value = p.status || 'Pending';
    document.getElementById('modal_payment_mode').value = p.payment_mode || 'NEFT Transfer';
    document.getElementById('modal_transaction').value = p.transaction_ref || '';
    document.getElementById('modal_cancel_reason').value = p.cancellation_reason || '';
    
    // Existing proof preview link
    let epBox = document.getElementById('existing_proof_box');
    if (p.payment_proof_path) {
        epBox.innerHTML = `Existing Proof: <button type="button" onclick="openProofLightbox('${p.payment_proof_path}', 'Voucher #PD-${String(p.id).padStart(3, '0')} Payment Proof')" style="background:none; border:none; color:#0f172a; font-weight:800; text-decoration:underline; cursor:pointer;">📷 View Saved Screenshot</button>`;
        epBox.style.display = 'block';
    } else {
        epBox.style.display = 'none';
    }
    
    document.getElementById('modal_proof_img_preview').style.display = 'none';
    document.getElementById('modal_payment_proof').value = '';
    
    toggleModalFields();
    document.getElementById('actionModal').style.display = 'flex';
}

function closeActionModal() {
    document.getElementById('actionModal').style.display = 'none';
}

function toggleModalFields() {
    let st = document.getElementById('modal_status').value;
    let isPaid = (st === 'Paid');
    document.getElementById('div_payment_mode').style.display = isPaid ? 'block' : 'none';
    document.getElementById('div_transaction').style.display = isPaid ? 'block' : 'none';
    document.getElementById('div_payment_proof').style.display = isPaid ? 'block' : 'none';
    document.getElementById('div_cancel_reason').style.display = (st === 'Hold' || st === 'Cancelled') ? 'block' : 'none';
}

function previewProofImage(input) {
    if (input.files && input.files[0]) {
        let file = input.files[0];
        let img = document.getElementById('modal_proof_img_preview');
        if (file.type.startsWith('image/')) {
            let reader = new FileReader();
            reader.onload = function(e) {
                img.src = e.target.result;
                img.style.display = 'block';
            }
            reader.readAsDataURL(file);
        } else {
            img.style.display = 'none';
        }
    }
}

function openProofLightbox(proofPath, title) {
    document.getElementById('plm_title').innerText = '📷 ' + (title || 'Payment Transfer Proof');
    let container = document.getElementById('plm_content');
    document.getElementById('plm_download_btn').href = proofPath;
    
    if (proofPath.toLowerCase().endsWith('.pdf')) {
        container.innerHTML = `<iframe src="${proofPath}" style="width:100%; height:450px; border:none; border-radius:8px;"></iframe>`;
    } else {
        container.innerHTML = `<img src="${proofPath}" style="max-width:100%; max-height:500px; border-radius:8px; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1); object-fit:contain;">`;
    }
    document.getElementById('proofLightboxModal').style.display = 'flex';
}

async function submitActionStatus(e) {
    e.preventDefault();
    let fd = new FormData();
    fd.append('id', document.getElementById('modal_payout_id').value);
    fd.append('status', document.getElementById('modal_status').value);
    fd.append('payment_mode', document.getElementById('modal_payment_mode').value);
    fd.append('transaction_ref', document.getElementById('modal_transaction').value);
    fd.append('cancellation_reason', document.getElementById('modal_cancel_reason').value);

    let proofInput = document.getElementById('modal_payment_proof');
    if (proofInput.files && proofInput.files[0]) {
        fd.append('payment_proof', proofInput.files[0]);
    }

    try {
        const res = await fetch('api.php?api=update_payout_status', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            closeActionModal();
            Swal.fire('Updated!', data.message, 'success').then(() => {
                loadPayouts(1);
            });
        } else {
            Swal.fire('Error', data.error, 'error');
        }
    } catch(err) {
        Swal.fire('Error', 'Server connection error', 'error');
    }
}

// 5. Payment Voucher Modal Helper
async function openVoucherModal(id) {
    try {
        const res = await fetch(`api.php?api=get_payout_voucher&id=${id}`);
        const data = await res.json();
        if (data.success && data.voucher) {
            let v = data.voucher;
            document.getElementById('pv_voucher_no').innerText = '#PD-' + String(v.id).padStart(3, '0');
            document.getElementById('pv_date').innerText = 'Date: ' + (v.created_at ? v.created_at.slice(0,10) : new Date().toISOString().slice(0,10));
            document.getElementById('pv_payee_name').innerText = v.payee_name_override || v.payee_name || 'Payee Recipient';
            document.getElementById('pv_payee_role').innerText = 'Role: ' + (v.payee_type || 'Staff');
            document.getElementById('pv_payee_bank').innerText = 'Bank: ' + (v.payee_bank_name || 'HDFC Bank');
            document.getElementById('pv_payee_acc').innerText = 'A/C: ' + (v.payee_account_no || 'N/A') + ' (IFSC: ' + (v.payee_ifsc || 'N/A') + ')';
            
            document.getElementById('pv_customer_name').innerText = 'Customer: ' + (v.customer_name || 'Direct Case');
            document.getElementById('pv_loan_id').innerText = 'Loan Ref: ' + (v.loan_id || 'N/A');
            document.getElementById('pv_sanction_bank').innerText = 'Lending Bank: ' + (v.sanction_bank || 'Partner Bank');

            document.getElementById('pv_gross_payout').innerText = formatCurrency(v.gross_payout);
            document.getElementById('pv_tds_deducted').innerText = '-' + formatCurrency(v.tds_deducted);
            document.getElementById('pv_net_payable').innerText = formatCurrency(v.net_payable);
            document.getElementById('pv_remarks').innerText = v.remarks || 'Statutory 5% TDS Deducted Payout Voucher';

            document.getElementById('pv_payment_mode').innerText = v.payment_mode || 'NEFT Transfer';
            document.getElementById('pv_utr').innerText = v.transaction_ref || 'Pending Transfer';
            document.getElementById('pv_paid_on').innerText = v.paid_on || 'Awaiting Disbursal';

            if (v.payment_proof_path) {
                document.getElementById('pv_proof_img').src = v.payment_proof_path;
                document.getElementById('pv_proof_container').style.display = 'block';
            } else {
                document.getElementById('pv_proof_container').style.display = 'none';
            }

            document.getElementById('paymentVoucherModal').style.display = 'flex';
        }
    } catch(err) {
        Swal.fire('Error', 'Could not fetch voucher details', 'error');
    }
}

function printPaymentVoucher() {
    let printContents = document.getElementById('payment-voucher-print-area').innerHTML;
    let originalContents = document.body.innerHTML;
    document.body.innerHTML = `<div style="padding:40px; font-family:sans-serif;">${printContents}</div>`;
    window.print();
    document.body.innerHTML = originalContents;
    window.location.reload();
}

function sendWhatsAppReceipt(id) {
    let p = currentPayoutsData.find(x => x.id == id);
    if (!p || !p.payee_mobile) return;
    let cleanMobile = p.payee_mobile.replace(/[^0-9]/g, '');
    if (cleanMobile.length === 10) cleanMobile = '91' + cleanMobile;

    let msg = `Dear ${p.payee_name},\n\nYour Payout Voucher *#PD-${String(p.id).padStart(3, '0')}* of *${formatCurrency(p.net_payable)}* (Gross ${formatCurrency(p.gross_payout)} less 5% TDS ${formatCurrency(p.tds_deducted)}) for Customer Case *${p.customer_name}* (${p.loan_id}) has been processed via *${p.payment_mode}*.\n\nTransaction UTR: ${p.transaction_ref || 'Under Transfer'}\n\nThank you,\n*BFS Financial Services Pvt Ltd*`;
    
    window.open(`https://api.whatsapp.com/send?phone=${cleanMobile}&text=${encodeURIComponent(msg)}`, '_blank');
}

// 6. Incoming Bank Payout Clearance Helpers
function openRecordBankPayModal(id, customerName, estimatedComm) {
    document.getElementById('rbp_applicant_id').value = id;
    document.getElementById('rbp_customer_name').value = customerName;
    document.getElementById('rbp_received_amount').value = estimatedComm || '';
    document.getElementById('rbp_received_date').value = new Date().toISOString().split('T')[0];
    document.getElementById('rbp_bank_utr').value = '';
    document.getElementById('recordBankPayModal').style.display = 'flex';
}

async function submitRecordBankPay(e) {
    e.preventDefault();
    let fd = new FormData();
    fd.append('applicant_id', document.getElementById('rbp_applicant_id').value);
    fd.append('received_amount', document.getElementById('rbp_received_amount').value);
    fd.append('bank_utr', document.getElementById('rbp_bank_utr').value);
    fd.append('received_date', document.getElementById('rbp_received_date').value);

    try {
        const res = await fetch('api.php?api=record_bank_payout_received', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            document.getElementById('recordBankPayModal').style.display = 'none';
            Swal.fire('Recorded!', data.message, 'success').then(() => {
                loadBankPayoutTracker();
                if (document.getElementById('payout-tbody')) loadPayouts(1);
            });
        } else {
            Swal.fire('Error', data.error, 'error');
        }
    } catch(err) {
        Swal.fire('Error', 'Server connection error', 'error');
    }
}

async function viewBankTaxInvoice(applicant_id) {
    try {
        const res = await fetch(`api.php?api=get_bank_tax_invoice&applicant_id=${applicant_id}`);
        const data = await res.json();
        if (data.success && data.invoice) {
            let inv = data.invoice;
            document.getElementById('ti_invoice_no').innerText = inv.invoice_number || ('INV-' + date('Ymd') + '-' + applicant_id);
            document.getElementById('ti_date').innerText = 'Date: ' + (inv.invoice_date || new Date().toLocaleDateString('en-GB'));
            document.getElementById('ti_bank_name').innerText = inv.sanction_bank || inv.bank_name || 'Partner Bank';
            document.getElementById('ti_customer_name').innerText = 'Customer: ' + inv.customer_name;
            document.getElementById('ti_loan_id').innerText = 'Loan Ref ID: ' + inv.loan_id;
            document.getElementById('ti_disbursed_amt').innerText = 'Disbursed Amount: ₹' + Number(inv.disbursed_amount || 0).toLocaleString('en-IN');

            document.getElementById('ti_comm_pct').innerText = (inv.commission_pct || 1.50) + '%';
            document.getElementById('ti_gross_amt').innerText = formatCurrency(inv.gross_amount || (inv.disbursed_amount * 0.015));
            document.getElementById('ti_gst_amt').innerText = '+' + formatCurrency(inv.gst_amount || ((inv.gross_amount || (inv.disbursed_amount * 0.015)) * 0.18));
            document.getElementById('ti_tds_amt').innerText = '-' + formatCurrency(inv.tds_amount || ((inv.gross_amount || (inv.disbursed_amount * 0.015)) * 0.02));
            document.getElementById('ti_net_amt').innerText = formatCurrency(inv.net_receivable || inv.net_invoice_amount);

            document.getElementById('taxInvoicePreviewModal').style.display = 'flex';
        } else {
            Swal.fire('Info', 'No invoice record found for this loan. Click "+ Create New Bank Claim Invoice" to generate one.', 'info');
        }
    } catch(err) {
        Swal.fire('Error', 'Failed to fetch tax invoice details', 'error');
    }
}

function printBankTaxInvoice() {
    let printContents = document.getElementById('tax-invoice-print-area').innerHTML;
    let originalContents = document.body.innerHTML;
    document.body.innerHTML = `<div style="padding:40px; font-family:sans-serif;">${printContents}</div>`;
    window.print();
    document.body.innerHTML = originalContents;
    window.location.reload();
}

let currentEmailCaseData = null;

function openBankEmailReminder(applicant_id) {
    let f = (currentTrackerData || []).find(x => x.id == applicant_id);
    if (!f) {
        let inv = (currentInvoicesData || []).find(x => x.applicant_id == applicant_id || x.id == applicant_id);
        if (inv) {
            f = {
                id: inv.applicant_id || inv.id,
                customer_name: inv.customer_name || 'Customer',
                loan_id: inv.loan_id || 'L-000',
                loan_type: inv.loan_type || 'Loan',
                sanction_bank: inv.sanction_bank || inv.bank_name || 'Bank',
                loan_amount_requested: inv.disbursed_amount || 0,
                estimated_commission: inv.gross_amount || 0
            };
        }
    }
    
    currentEmailCaseData = f || { id: applicant_id, customer_name: 'Customer', loan_id: 'L-00', sanction_bank: 'Partner Bank', loan_amount_requested: 0, estimated_commission: 0 };
    document.getElementById('bee_applicant_id').value = currentEmailCaseData.id;
    document.getElementById('bee_email').value = `nodal.disbursal@${(currentEmailCaseData.sanction_bank || 'bank').toLowerCase().replace(/[^a-z0-9]/g, '')}.com`;
    document.getElementById('bee_template_select').value = 'escalation';
    onEmailTemplateChange();
    document.getElementById('bankEmailEscalationModal').style.display = 'flex';
}

function onEmailTemplateChange() {
    if (!currentEmailCaseData) return;
    let tmpl = document.getElementById('bee_template_select').value;
    let cName = currentEmailCaseData.customer_name || 'Customer';
    let lId = currentEmailCaseData.loan_id || 'L-00';
    let bName = currentEmailCaseData.sanction_bank || 'Bank';
    let dAmt = Number(currentEmailCaseData.loan_amount_requested || 0).toLocaleString('en-IN');
    let cAmt = Number(currentEmailCaseData.estimated_commission || 0).toLocaleString('en-IN');

    if (tmpl === 'escalation') {
        document.getElementById('bee_subject').value = `URGENT: DSA Commission Payout Clearance — ${cName} [Loan ID: ${lId}]`;
        document.getElementById('bee_body').value = `Dear Nodal Officer,\n\nGreetings from BFS Financial Services Pvt Ltd.\n\nThis is a formal request regarding the expedited clearance of our DSA Commission payout for the following disbursed case:\n\nCustomer Name: ${cName}\nLoan Ref ID: ${lId}\nSanctioned Bank: ${bName}\nDisbursed Amount: ₹${dAmt}\nEstimated Base Commission: ₹${cAmt}\n\nKindly confirm the scheduled credit date or share the bank UTR reference number for our accounts ledger.\n\nThank you for your partnership.\n\nWarm regards,\nAccounts & Payout Cell\nBFS Financial Services Pvt Ltd`;
    } else if (tmpl === 'invoice') {
        document.getElementById('bee_subject').value = `TAX INVOICE SUBMISSION: DSA Commission Claim — ${cName} [Ref: ${lId}]`;
        document.getElementById('bee_body').value = `Dear Finance & Accounts Nodal Team,\n\nPlease find attached the official GST Tax Invoice (SAC 997159) for DSA Commission against customer loan disbursal:\n\nCustomer Name: ${cName}\nLoan ID: ${lId}\nDisbursed Loan Amount: ₹${dAmt}\n\nPlease credit the invoice amount to our registered bank account after applicable TDS (Sec 194H) deduction.\n\nSincerely,\nTaxation & Billing Desk\nBFS Financial Services Pvt Ltd`;
    } else if (tmpl === 'acknowledgement') {
        document.getElementById('bee_subject').value = `PAYMENT ACKNOWLEDGEMENT & UTR QUERY: Loan Case ${lId}`;
        document.getElementById('bee_body').value = `Dear Nodal Officer,\n\nWe are following up regarding the commission credit remittance for Case Ref #${lId} (${cName}).\n\nIf the funds have been dispatched from your backend system, kindly provide the 16-digit Bank UTR number to enable us to reconcile the credit in our bank statement.\n\nBest regards,\nAudit & Accounts Cell\nBFS Financial Services Pvt Ltd`;
    } else {
        document.getElementById('bee_subject').value = `Official Communication regarding Loan Case: ${cName} [Ref: ${lId}]`;
        document.getElementById('bee_body').value = `Dear Sir/Madam,\n\nGreetings from BFS Financial Services Pvt Ltd.\n\nRegarding Loan Case ID ${lId} (${cName}), kindly process the necessary accounts clearance at the earliest.\n\nBest regards,\nBFS Financial Services Pvt Ltd`;
    }
}

async function submitBankEmailEscalation(e) {
    e.preventDefault();
    let btn = document.getElementById('bee_submit_btn');
    btn.disabled = true;
    btn.innerHTML = `Dispatching Email...`;

    let fd = new FormData();
    fd.append('applicant_id', document.getElementById('bee_applicant_id').value);
    fd.append('email', document.getElementById('bee_email').value);
    fd.append('subject', document.getElementById('bee_subject').value);
    fd.append('body', document.getElementById('bee_body').value);

    try {
        const res = await fetch('api.php?api=send_bank_email_escalation', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            document.getElementById('bankEmailEscalationModal').style.display = 'none';
            Swal.fire({
                icon: 'success',
                title: 'Corporate Email Dispatched!',
                text: data.message,
                confirmButtonColor: '#0f172a'
            });
        } else {
            Swal.fire('Email Delivery Error', data.error || 'Could not send email.', 'error');
        }
    } catch(err) {
        Swal.fire('Error', 'Server connection error during email dispatch.', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg> Dispatch Corporate Email`;
    }
}

function emailPayoutVoucher(payout_id) {
    const p = (currentPayoutsData || []).find(x => x.id == payout_id);
    if (!p) return;
    
    const payeeName = (p.payee_name && p.payee_name !== 'Direct Payee') ? p.payee_name : (p.customer_name ? p.customer_name + ' (Payee)' : 'Payee');
    const defaultEmail = p.payee_email || p.u_email || '';
    const voucherNo = '#PD-' + String(payout_id).padStart(3, '0');
    const netPayable = formatCurrency(p.net_payable);
    const loanRef = p.loan_id || 'N/A';
    const customer = p.customer_name || 'Direct Case';

    document.getElementById('epm_payout_id').value = payout_id;
    document.getElementById('epm_voucher_no').innerText = voucherNo;
    document.getElementById('epm_payee_name').innerText = payeeName;
    document.getElementById('epm_case_ref').innerText = `${customer} (${loanRef})`;
    document.getElementById('epm_net_payable').innerText = netPayable;
    document.getElementById('epm_email_input').value = defaultEmail;

    document.getElementById('emailPayoutVoucherModal').style.display = 'flex';
}

function closeEmailPayoutModal() {
    document.getElementById('emailPayoutVoucherModal').style.display = 'none';
}

async function submitEmailPayoutVoucher(e) {
    e.preventDefault();
    const payout_id = document.getElementById('epm_payout_id').value;
    const email = document.getElementById('epm_email_input').value;
    const btn = document.getElementById('epm_submit_btn');

    if (!email || !email.includes('@')) {
        Swal.fire('Invalid Email', 'Please enter a valid recipient email address.', 'warning');
        return;
    }

    btn.disabled = true;
    btn.innerText = 'Dispatching Email...';

    try {
        let fd = new FormData();
        fd.append('payout_id', payout_id);
        fd.append('email', email);
        
        const res = await fetch('api.php?api=send_outgoing_payout_email', { method: 'POST', body: fd });
        const data = await res.json();
        
        closeEmailPayoutModal();

        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Email Voucher Sent!',
                text: data.message,
                confirmButtonColor: '#0f172a'
            });
        } else {
            Swal.fire('Email Delivery Failed', data.error || 'Could not send email.', 'error');
        }
    } catch(err) {
        closeEmailPayoutModal();
        Swal.fire('Error', 'Server connection error during email dispatch.', 'error');
    } finally {
        btn.disabled = false;
        btn.innerText = '⚡ Send Email Voucher';
    }
}

function emailBankTaxInvoice(invoice_id) {
    const inv = (currentInvoicesData || []).find(x => x.id == invoice_id);
    if (!inv) return;
    
    const bankName = inv.sanction_bank || inv.bank_name || 'Bank';
    const defaultEmail = `nodal.disbursal@${bankName.toLowerCase().replace(/[^a-z0-9]/g, '')}.com`;
    const invNo = inv.invoice_number || ('INV-' + invoice_id);
    const netAmt = formatCurrency(inv.net_receivable || inv.net_invoice_amount);
    const customer = inv.customer_name || 'Direct Case';

    document.getElementById('ebim_invoice_id').value = invoice_id;
    document.getElementById('ebim_invoice_no').innerText = invNo;
    document.getElementById('ebim_bank_name').innerText = bankName;
    document.getElementById('ebim_customer_name').innerText = customer;
    document.getElementById('ebim_net_amt').innerText = netAmt;
    document.getElementById('ebim_email_input').value = defaultEmail;

    document.getElementById('emailBankTaxInvoiceModal').style.display = 'flex';
}

function closeEmailBankTaxInvoiceModal() {
    document.getElementById('emailBankTaxInvoiceModal').style.display = 'none';
}

async function submitEmailBankTaxInvoice(e) {
    e.preventDefault();
    const invoice_id = document.getElementById('ebim_invoice_id').value;
    const email = document.getElementById('ebim_email_input').value;
    const btn = document.getElementById('ebim_submit_btn');

    if (!email || !email.includes('@')) {
        Swal.fire('Invalid Email', 'Please enter a valid nodal email address.', 'warning');
        return;
    }

    btn.disabled = true;
    btn.innerText = 'Dispatching Tax Invoice...';

    try {
        let fd = new FormData();
        fd.append('invoice_id', invoice_id);
        fd.append('email', email);
        
        const res = await fetch('api.php?api=send_payout_invoice_email', { method: 'POST', body: fd });
        const data = await res.json();
        
        closeEmailBankTaxInvoiceModal();

        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Tax Invoice Dispatched!',
                text: data.message,
                confirmButtonColor: '#0f172a'
            });
        } else {
            Swal.fire('Invoice Delivery Failed', data.error || 'Could not send invoice email.', 'error');
        }
    } catch(err) {
        closeEmailBankTaxInvoiceModal();
        Swal.fire('Error', 'Server connection error during invoice dispatch.', 'error');
    } finally {
        btn.disabled = false;
        btn.innerText = '⚡ Send Tax Invoice Email';
    }
}

function sendBankWAReminder(applicant_id) {
    let f = currentTrackerData.find(x => x.id == applicant_id);
    if (!f) return;
    let msg = `Respected Sir/Madam,\n\nGreetings from *BFS Financial Services Pvt Ltd*.\n\nRegarding customer loan disbursal for *${f.customer_name}* (Ref: *${f.loan_id}* • ${f.sanction_bank}), our DSA commission payout of *₹${Number(f.estimated_commission).toLocaleString('en-IN')}* is currently pending clearance.\n\nKindly assist with updating the payment credit status/UTR.\n\nThank you,\nBFS Financial Services`;
    window.open(`https://api.whatsapp.com/send?text=${encodeURIComponent(msg)}`, '_blank');
}

function resetFilters() {
    document.getElementById('filter_search').value = '';
    document.getElementById('filter_status').value = '';
    document.getElementById('filter_payee').value = '';
    loadPayouts(1);
}

async function loadPayouts(page = 1) {
    const tbody = document.getElementById('payout-tbody');
    if (!tbody) return;
    
    // Skeleton loader
    let skelHtml = '';
    for(let i=0; i<5; i++) {
        skelHtml += `<tr><td colspan="11" style="padding:12px;"><div style="height:32px; background:#f1f5f9; border-radius:6px; animation:pulse 1.5s infinite;"></div></td></tr>`;
    }
    tbody.innerHTML = skelHtml;

    const search = document.getElementById('filter_search').value;
    const status = document.getElementById('filter_status').value;
    const payee = document.getElementById('filter_payee').value;
    
    try {
        const res = await fetch(`api.php?api=get_payouts&page=${page}&limit=10&search=${encodeURIComponent(search)}&status=${encodeURIComponent(status)}&payee_type=${encodeURIComponent(payee)}`);
        const data = await res.json();
        
        if (data.success) {
            currentPayoutsData = data.payouts || [];

            // Update KPI Banner Cards
            if (data.summary) {
                document.getElementById('kpi-total-gross').innerText = formatCurrency(data.summary.total_gross);
                document.getElementById('kpi-total-tds').innerText = formatCurrency(data.summary.total_tds);
                document.getElementById('kpi-total-pending').innerText = formatCurrency(data.summary.total_pending);
                document.getElementById('kpi-total-paid').innerText = formatCurrency(data.summary.total_paid);
                document.getElementById('kpi-total-cancelled').innerText = formatCurrency(data.summary.total_cancelled);
            }

            if (currentPayoutsData.length > 0) {
                let html = '';
                currentPayoutsData.forEach(p => {
                    let st = (p.status || 'Pending').toLowerCase();
                    let badgeClass = 'badge-mono-outline';
                    let stLabel = 'PENDING';
                    if (st === 'paid') { badgeClass = 'badge-mono-black'; stLabel = 'PAID'; }
                    else if (st === 'approved') { badgeClass = 'badge-mono-outline'; stLabel = 'APPROVED'; }
                    else if (st === 'hold') { badgeClass = 'badge-secondary'; stLabel = 'ON HOLD'; }
                    else if (st === 'cancelled') { badgeClass = 'badge-secondary'; stLabel = 'CANCELLED'; }
                    
                    // Bank Payout Indicator Badge (Bank Paid BFS vs Bank Pending)
                    let bankPaidBadge = p.bank_payout_status === 'Received' ? 
                        `<span class="badge-mono-black">🟢 BANK PAID</span>` : 
                        `<span class="badge-mono-outline">🟡 BANK PENDING</span>`;

                    let loanAmtFormatted = '₹' + Number(p.total_loan_amount || p.loan_amount_requested || 0).toLocaleString('en-IN');
                    let phoneStr = (p.payee_mobile && p.payee_mobile !== 'N/A') ? `<div style="font-size:11px; color:#334155; font-weight:600; margin-top:2px;">📞 ${p.payee_mobile}</div>` : '';

                    html += `<tr>
                        <td style="width:30px;"><input type="checkbox" class="payout-row-checkbox" value="${p.id}"></td>
                        <td>
                            <div style="color:#0f172a; font-weight:800; font-size:12px;">#PD-${String(p.id).padStart(3, '0')}</div>
                            <div style="font-size:11px; color:#334155; font-weight:700; margin-top:2px;">Mode: ${p.payment_mode || 'NEFT'}</div>
                        </td>
                        <td>
                            <div style="color:#0f172a; font-weight:800; font-size:12px;">${p.customer_name}</div>
                            <div style="font-size:11px; color:#334155; font-weight:700; margin-top:2px;">Ref: ${p.loan_id}</div>
                        </td>
                        <td>
                            <div><span class="badge-mono-bank">${p.sanction_bank || 'Bank'}</span></div>
                            <div style="font-size:12px; color:#0f172a; font-weight:800; margin-top:3px;">${loanAmtFormatted}</div>
                        </td>
                        <td>
                            <div style="color:#0f172a; font-weight:800; font-size:12px;">${p.payee_name} <span class="badge-mono-outline" style="font-size:9px; padding:1px 5px; margin-left:2px;">${p.payee_role}</span></div>
                            ${phoneStr}
                        </td>
                        <td>${bankPaidBadge}</td>
                        <td style="font-weight:700; color:#0f172a;">${formatCurrency(p.gross_payout)}</td>
                        <td style="font-weight:700; color:#dc2626;">-${formatCurrency(p.tds_deducted)}</td>
                        <td style="font-weight:900; color:#0f172a; font-size:13px;">${formatCurrency(p.net_payable)}</td>
                        <td>
                            <div><span class="${badgeClass}">${stLabel}</span></div>
                            ${p.transaction_ref ? `<div style="font-size:10px; color:#334155; font-family:monospace; font-weight:700; margin-top:2px;">UTR: ${p.transaction_ref}</div>` : ''}
                        </td>
                        <td style="text-align:center;">
                            <div style="display:inline-flex; align-items:center; justify-content:center; gap:4px; white-space:nowrap;">
                                <button onclick='openActionModalById(${p.id})' style="height:26px; padding:0 8px; font-size:10.5px; font-weight:800; border-radius:6px; background:#0f172a; color:#ffffff; border:1px solid #0f172a; cursor:pointer; display:inline-flex; align-items:center; gap:3.5px;" title="Update Disbursal Status">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                                    Action
                                </button>
                                <button onclick='openVoucherModal(${p.id})' style="height:26px; padding:0 8px; font-size:10.5px; font-weight:700; border-radius:6px; background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; cursor:pointer; display:inline-flex; align-items:center; gap:3.5px;" title="Print Payment Voucher">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                                    Voucher
                                </button>
                                <button onclick="emailPayoutVoucher(${p.id})" style="height:26px; padding:0 8px; font-size:10.5px; font-weight:700; border-radius:6px; background:#fef3c7; color:#b45309; border:1px solid #fde68a; cursor:pointer; display:inline-flex; align-items:center; gap:3.5px;" title="Send Payout Advice Email Receipt">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                                    Email
                                </button>
                                ${p.payment_proof_path ? `<button onclick="openProofLightbox('${p.payment_proof_path}', 'Voucher #PD-${String(p.id).padStart(3, '0')} Payment Proof')" style="height:26px; padding:0 8px; font-size:10.5px; font-weight:700; border-radius:6px; background:#f3e8ff; color:#7e22ce; border:1px solid #e9d5ff; cursor:pointer; display:inline-flex; align-items:center; gap:3.5px;" title="View Payment Screenshot Proof">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                                    Proof
                                </button>` : ''}
                                ${p.payee_mobile && p.payee_mobile !== 'N/A' ? `<button onclick='sendWhatsAppReceipt(${p.id})' style="height:26px; padding:0 8px; font-size:10.5px; font-weight:700; border-radius:6px; background:#dcfce7; color:#15803d; border:1px solid #86efac; cursor:pointer; display:inline-flex; align-items:center; gap:3.5px;" title="Send WhatsApp Receipt Alert">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
                                    WA
                                </button>` : ''}
                            </div>
                        </td>
                    </tr>`;
                });
                tbody.innerHTML = html;
                if(window.lucide) lucide.createIcons();
            } else {
                tbody.innerHTML = `<tr><td colspan="11" style="text-align: center; padding: 30px; color: #334155; font-weight:700;">No payouts found matching criteria.</td></tr>`;
            }
            
            // Pagination info
            document.getElementById('pagination-info').innerText = `Showing page ${data.page} of ${data.total_pages} (${data.total} total records)`;
            
            let btnHtml = '';
            if (data.page > 1) {
                btnHtml += `<button class="btn-black-outline" style="padding:3px 8px; font-size:11px;" onclick="loadPayouts(${data.page - 1})">Prev</button>`;
            }
            if (data.page < data.total_pages) {
                btnHtml += `<button class="btn-black-outline" style="padding:3px 8px; font-size:11px;" onclick="loadPayouts(${data.page + 1})">Next</button>`;
            }
            document.getElementById('pagination-buttons').innerHTML = btnHtml;
        }
    } catch(err) {
        console.error(err);
        tbody.innerHTML = `<tr><td colspan="11" style="text-align: center; padding: 20px; color: red;">Failed to load data.</td></tr>`;
    }
}

// --- Bank Payout Tracker JS ---
async function loadBankPayoutTracker() {
    const tbody = document.getElementById('bank-tracker-tbody');
    if (!tbody) return;

    try {
        const res = await fetch('api.php?api=get_disbursed_bank_payout_tracker');
        const data = await res.json();
        
        if (data.success) {
            currentTrackerData = data.files || [];

            if (data.summary) {
                document.getElementById('bt-total-disbursed').innerText = '₹' + Number(data.summary.total_disbursed).toLocaleString('en-IN');
                document.getElementById('bt-total-received').innerText = '₹' + Number(data.summary.total_bank_received).toLocaleString('en-IN');
                document.getElementById('bt-total-pending').innerText = '₹' + Number(data.summary.total_bank_pending).toLocaleString('en-IN');
                document.getElementById('bt-pending-count').innerText = `${data.summary.count_pending} disbursed files pending payment from banks`;
            }

            if (currentTrackerData.length > 0) {
                let html = '';
                currentTrackerData.forEach(f => {
                    let st = f.bank_status;
                    let isReceived = st === 'Received';
                    
                    let stBadge = isReceived ? 
                        `<span style="display:inline-flex; align-items:center; gap:5px; font-weight:800; font-size:11px; padding:4px 10px; border-radius:6px; background:#0f172a; color:#ffffff;">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            RECEIVED FROM BANK
                         </span>` : 
                        `<span style="display:inline-flex; align-items:center; gap:5px; font-weight:800; font-size:11px; padding:4px 10px; border-radius:6px; background:#fef2f2; color:#dc2626; border:1px solid #fca5a5;">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                            UNPAID BY BANK
                         </span>`;

                    let rateBadge = f.is_override ? 
                        `<button type="button" style="display:inline-flex; align-items:center; gap:5px; background:#0f172a; color:#ffffff; padding:3px 8px; border-radius:6px; font-size:10px; font-weight:800; border:none; cursor:pointer;" onclick='openEditCaseCommModal(${f.id}, "${f.customer_name.replace(/'/g, "\\'")}", ${f.commission_pct})' title="Click to Modify Rate">
                            <span>Modified: ${f.commission_pct}%</span>
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                         </button>` : 
                        `<button type="button" style="display:inline-flex; align-items:center; gap:5px; background:#f8fafc; color:#334155; padding:3px 8px; border-radius:6px; font-size:10px; font-weight:700; border:1px solid #cbd5e1; cursor:pointer;" onclick='openEditCaseCommModal(${f.id}, "${f.customer_name.replace(/'/g, "\\'")}", ${f.commission_pct})' title="Click to Modify Rate">
                            <span>Slab Rate: ${f.commission_pct}%</span>
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                         </button>`;

                    html += `<tr>
                        <td>
                            <div style="color:#0f172a; font-weight:800; font-size:12px;">${f.customer_name}</div>
                            <div style="font-size:11px; color:#334155; font-weight:700; margin-top:2px;">Ref: ${f.loan_id} • ${f.loan_type}</div>
                        </td>
                        <td>
                            <span class="badge-mono-bank">${f.sanction_bank}</span>
                            <div style="font-size:10px; color:#64748b; font-weight:600; margin-top:2px;">Vol: ₹${Number(f.bank_volume || 0).toLocaleString('en-IN')}</div>
                        </td>
                        <td><strong style="color:#0f172a; font-size:12px;">₹${Number(f.loan_amount_requested).toLocaleString('en-IN')}</strong></td>
                        <td style="color:#0f172a; font-weight:700;">${f.rm_display}</td>
                        <td>
                            <div>
                                <strong style="color:#0f172a; font-size:12px;">₹${Number(f.estimated_commission).toLocaleString('en-IN')}</strong>
                                <div style="margin-top:4px;">${rateBadge}</div>
                            </div>
                        </td>
                        <td>${stBadge}</td>
                        <td style="text-align:center;">
                            <div style="display:inline-flex; align-items:center; justify-content:center; gap:4px; white-space:nowrap;">
                                ${!isReceived ? `
                                <button onclick='openRecordBankPayModal(${f.id}, "${f.customer_name.replace(/'/g, "\\'")}", ${f.estimated_commission})' style="height:26px; padding:0 8px; font-size:10.5px; font-weight:800; border-radius:6px; background:#059669; color:#ffffff; border:1px solid #059669; cursor:pointer; display:inline-flex; align-items:center; gap:3.5px;" title="Record Incoming Bank Payment">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                                    Received
                                </button>` : ''}
                                
                                <button onclick="viewBankTaxInvoice(${f.id})" style="height:26px; padding:0 8px; font-size:10.5px; font-weight:700; border-radius:6px; background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; cursor:pointer; display:inline-flex; align-items:center; gap:3.5px;" title="Generate GST Tax Invoice">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                                    Invoice
                                </button>

                                ${!isReceived ? `
                                <button onclick="openBankEmailReminder(${f.id})" style="height:26px; padding:0 8px; font-size:10.5px; font-weight:700; border-radius:6px; background:#fef3c7; color:#b45309; border:1px solid #fde68a; cursor:pointer; display:inline-flex; align-items:center; gap:3.5px;" title="Send Formal Email Escalation to Bank">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                                    Mail
                                </button>
                                
                                <button onclick='sendBankWAReminder(${f.id})' style="height:26px; padding:0 8px; font-size:10.5px; font-weight:700; border-radius:6px; background:#dcfce7; color:#15803d; border:1px solid #86efac; cursor:pointer; display:inline-flex; align-items:center; gap:3.5px;" title="Send WhatsApp Escalation to Banker">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
                                    WA
                                </button>` : ''}
                            </div>
                        </td>
                    </tr>`;
                });
                tbody.innerHTML = html;
            } else {
                tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:30px; color:#334155; font-weight:700;">No disbursed loan cases found.</td></tr>`;
            }
        }
    } catch(e) { console.error(e); }
}

function openEditCaseCommModal(id, customerName, currentPct) {
    document.getElementById('ecc_applicant_id').value = id;
    document.getElementById('ecc_customer_title').innerText = customerName;
    document.getElementById('ecc_commission_pct').value = currentPct || 1.50;
    document.getElementById('editCaseCommModal').style.display = 'flex';
}

async function submitUpdateCaseComm(e) {
    e.preventDefault();
    const id = document.getElementById('ecc_applicant_id').value;
    const commPct = document.getElementById('ecc_commission_pct').value;

    const fd = new FormData();
    fd.append('applicant_id', id);
    fd.append('commission_pct', commPct);

    try {
        const res = await fetch('api.php?api=update_case_bank_commission', { method: 'POST', body: fd });
        const data = await res.json();
        if(data.success) {
            alert(data.message);
            document.getElementById('editCaseCommModal').style.display = 'none';
            loadBankPayoutTracker();
            if (typeof loadBankInvoices === 'function') loadBankInvoices();
        } else {
            alert('Error: ' + (data.error || 'Failed to update commission rate'));
        }
    } catch(err) {
        alert('Server error: ' + err.message);
    }
}

async function openBankSlabsModal() {
    document.getElementById('bankSlabsModal').style.display = 'flex';
    loadBankSlabsTable();
}

async function loadBankSlabsTable() {
    const tbody = document.getElementById('bank-slabs-tbody');
    if (!tbody) return;
    try {
        const res = await fetch('api.php?api=get_bank_commission_slabs');
        const data = await res.json();
        if (data.success && data.slabs) {
            let html = '';
            data.slabs.forEach(s => {
                let minL = (s.min_volume / 100000).toFixed(1) + ' Lakhs';
                let maxL = s.max_volume >= 999999999 ? '∞ (No Cap)' : (s.max_volume / 100000).toFixed(1) + ' Lakhs';
                html += `<tr>
                    <td style="font-weight:800; color:#0f172a;">${s.bank_name}</td>
                    <td style="color:#475569; font-weight:600;">₹${Number(s.min_volume).toLocaleString('en-IN')} (${minL})</td>
                    <td style="color:#475569; font-weight:600;">₹${Number(s.max_volume).toLocaleString('en-IN')} (${maxL})</td>
                    <td style="font-weight:900; color:#0f172a; font-size:14px;">${s.commission_pct}%</td>
                </tr>`;
            });
            tbody.innerHTML = html;
        }
    } catch(e) { console.error(e); }
}

async function submitAddSlab(e) {
    e.preventDefault();
    const bankName = document.getElementById('slab_bank_name').value;
    const minVol = document.getElementById('slab_min_vol').value;
    const maxVol = document.getElementById('slab_max_vol').value;
    const commPct = document.getElementById('slab_comm_pct').value;

    const fd = new FormData();
    fd.append('bank_name', bankName);
    fd.append('min_volume', minVol);
    fd.append('max_volume', maxVol);
    fd.append('commission_pct', commPct);

    try {
        const res = await fetch('api.php?api=save_bank_commission_slab', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            alert(data.message);
            loadBankSlabsTable();
            loadBankPayoutTracker();
        } else {
            alert('Error: ' + data.error);
        }
    } catch(err) { alert('Server error: ' + err.message); }
}

window.eligibleCasesData = <?= json_encode(array_values(array_map(function($ea) {
    return [
        'id' => intval($ea['id']),
        'customer_name' => $ea['customer_name'],
        'loan_id' => $ea['loan_id'],
        'loan_type' => $ea['loan_type'] ?? 'Loan',
        'sanction_bank' => $ea['sanction_bank'] ?: 'Partner Bank',
        'loan_amount_requested' => floatval($ea['loan_amount_requested']),
        'bank_commission_pct' => floatval($ea['bank_commission_pct'] ?? 1.50),
        'assigned_to' => $ea['assigned_to'] ?? ''
    ];
}, $eligible_applicants))) ?>;

function openCreateInvoiceModal() {
    document.getElementById('generateInvoiceModal').style.display = 'flex';
    document.getElementById('inv_case_search_input').focus();
}

function onInvoiceCaseSearchFocus() {
    onInvoiceCaseSearchInput(document.getElementById('inv_case_search_input').value);
}

function onInvoiceCaseSearchInput(query) {
    const dropdown = document.getElementById('inv_case_results_dropdown');
    if (!dropdown) return;

    const q = (query || '').toLowerCase().trim();
    const cases = window.eligibleCasesData || [];

    const filtered = cases.filter(c => {
        if (!q) return true;
        const name = (c.customer_name || '').toLowerCase();
        const lid = (c.loan_id || '').toLowerCase();
        const bank = (c.sanction_bank || '').toLowerCase();
        const type = (c.loan_type || '').toLowerCase();
        const amtStr = String(c.loan_amount_requested);
        return name.includes(q) || lid.includes(q) || bank.includes(q) || type.includes(q) || amtStr.includes(q);
    });

    if (filtered.length === 0) {
        dropdown.innerHTML = `<div style="padding:14px; font-size:12px; color:#64748b; text-align:center; font-weight:600;">No matching disbursed loan cases found.</div>`;
    } else {
        let html = '';
        filtered.forEach(c => {
            let amtFormatted = '₹' + Number(c.loan_amount_requested).toLocaleString('en-IN');
            html += `<div class="combobox-result-row" style="padding:10px 14px; cursor:pointer; border-bottom:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center;" onclick="selectInvoiceCaseItem(${c.id})">
                <div>
                    <div style="font-weight:800; color:#0f172a; font-size:13px;">${c.customer_name} <span style="font-weight:700; color:#2563eb; font-size:11px;">[Ref: ${c.loan_id}]</span></div>
                    <div style="font-size:11px; color:#64748b; font-weight:600; margin-top:2px;">
                        <span class="badge-mono-bank" style="font-size:9px; padding:1px 5px;">${c.sanction_bank}</span> • ${c.loan_type}
                    </div>
                </div>
                <div style="text-align:right;">
                    <div style="font-weight:900; color:#0f172a; font-size:13px;">${amtFormatted}</div>
                    <div style="font-size:10px; color:#10b981; font-weight:700;">Rate: ${c.bank_commission_pct}%</div>
                </div>
            </div>`;
        });
        dropdown.innerHTML = html;
    }
    dropdown.style.display = 'block';
}

function selectInvoiceCaseItem(id) {
    const cases = window.eligibleCasesData || [];
    const item = cases.find(c => String(c.id) === String(id));
    const dropdown = document.getElementById('inv_case_results_dropdown');
    if (dropdown) dropdown.style.display = 'none';

    if (item) {
        document.getElementById('inv_applicant_id').value = item.id;
        document.getElementById('inv_case_search_input').value = `${item.customer_name} [${item.loan_id}] • ${item.sanction_bank} - ₹${Number(item.loan_amount_requested).toLocaleString('en-IN')}`;
        document.getElementById('inv_disbursed_amount').value = item.loan_amount_requested;
        document.getElementById('inv_commission_pct').value = item.bank_commission_pct;
        if(document.getElementById('inv_bank_badge')) document.getElementById('inv_bank_badge').innerText = item.sanction_bank;

        // Check duplicate invoice warning
        let existingInv = (window.currentInvoicesData || []).find(x => String(x.applicant_id) === String(item.id));
        let warnEl = document.getElementById('duplicate_invoice_warning');
        if (warnEl) {
            if (existingInv) {
                warnEl.innerText = `⚠️ A Bank Claim Invoice (${existingInv.invoice_number}) already exists for this case! Submitting will update the claim.`;
                warnEl.style.display = 'block';
            } else {
                warnEl.style.display = 'none';
            }
        }

        calcInvoice();
    }
}

document.addEventListener('click', function(e) {
    const container = document.getElementById('inv_case_picker_container');
    const dropdown = document.getElementById('inv_case_results_dropdown');
    if (container && dropdown && !container.contains(e.target)) {
        dropdown.style.display = 'none';
    }
});

function autoFillInvoiceDisbursed(selectEl) {
    let opt = selectEl.options[selectEl.selectedIndex];
    if (!opt || !opt.value) {
        document.getElementById('inv_disbursed_amount').value = '';
        if(document.getElementById('inv_bank_badge')) document.getElementById('inv_bank_badge').innerText = 'Select Case';
        if(document.getElementById('duplicate_invoice_warning')) document.getElementById('duplicate_invoice_warning').style.display = 'none';
        calcInvoice();
        return;
    }
    let amt = parseFloat(opt.getAttribute('data-amount')) || 0;
    let bank = opt.getAttribute('data-bank') || 'Bank';
    let comm = parseFloat(opt.getAttribute('data-comm')) || 1.50;

    document.getElementById('inv_disbursed_amount').value = amt;
    document.getElementById('inv_commission_pct').value = comm;
    if(document.getElementById('inv_bank_badge')) document.getElementById('inv_bank_badge').innerText = bank;

    // Check duplicate invoice warning
    let appID = opt.value;
    let existingInv = (window.currentInvoicesData || []).find(x => String(x.applicant_id) === String(appID));
    let warnEl = document.getElementById('duplicate_invoice_warning');
    if (warnEl) {
        if (existingInv) {
            warnEl.innerText = `⚠️ A Bank Claim Invoice (${existingInv.invoice_number}) already exists for this case! Submitting will update the claim.`;
            warnEl.style.display = 'block';
        } else {
            warnEl.style.display = 'none';
        }
    }

    calcInvoice();
}

function calcInvoice() {
    let disbursed = parseFloat(document.getElementById('inv_disbursed_amount').value) || 0;
    let pct = parseFloat(document.getElementById('inv_commission_pct').value) || 0;
    let gross = (disbursed * pct) / 100;
    let gst = (gross * 18) / 100;
    let tds = (gross * 2) / 100;
    let net = gross + gst - tds;

    if(document.getElementById('inv_gst')) document.getElementById('inv_gst').value = '₹' + gst.toFixed(2);
    if(document.getElementById('inv_tds')) document.getElementById('inv_tds').value = '₹' + tds.toFixed(2);
    if(document.getElementById('inv_net')) document.getElementById('inv_net').value = '₹' + net.toFixed(2);

    // Update Executive Financial Summary Breakdown Card
    if (document.getElementById('inv_card_gross')) {
        document.getElementById('inv_card_gross').innerText = '₹' + gross.toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2});
        document.getElementById('inv_card_gst').innerText = '+₹' + gst.toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2});
        document.getElementById('inv_card_tds').innerText = '-₹' + tds.toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2});
        document.getElementById('inv_card_net').innerText = '₹' + net.toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2});
    }
}

async function saveInvoice(e) {
    e.preventDefault();
    let fd = new FormData();
    fd.append('applicant_id', document.getElementById('inv_applicant_id').value);
    fd.append('disbursed_amount', document.getElementById('inv_disbursed_amount').value);
    fd.append('commission_pct', document.getElementById('inv_commission_pct').value);
    fd.append('notes', document.getElementById('inv_notes').value);

    try {
        const res = await fetch('api.php?api=save_payout_invoice', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            document.getElementById('generateInvoiceModal').style.display = 'none';
            Swal.fire({
                title: 'Tax Invoice Generated!',
                text: `${data.invoice_number} created successfully.`,
                icon: 'success',
                showCancelButton: true,
                confirmButtonText: '📄 View & Print Invoice PDF',
                cancelButtonText: 'Close'
            }).then((result) => {
                if (result.isConfirmed) {
                    viewBankTaxInvoice(document.getElementById('inv_applicant_id').value);
                }
                loadInvoices();
            });
        } else {
            Swal.fire('Error', data.error || 'Failed to generate invoice', 'error');
        }
    } catch(err) {
        Swal.fire('Error', 'Server connection error', 'error');
    }
}

async function loadInvoices() {
    const tbody = document.getElementById('bank-invoices-tbody');
    if (!tbody) return;

    try {
        const res = await fetch('api.php?api=get_payout_invoices');
        const data = await res.json();
        
        if (data.success && data.invoices) {
            currentInvoicesData = data.invoices;
            let html = '';
            let totalOutstanding = 0;
            let receivedMonth = 0;
            let shortfallPending = 0;
            let curMonth = new Date().getMonth();
            
            currentInvoicesData.forEach(i => {
                let st = i.status;
                let stBadgeHtml = '';
                if (st === 'Received') {
                    stBadgeHtml = `<span style="display:inline-flex; align-items:center; gap:5px; font-weight:800; font-size:11px; padding:4px 10px; border-radius:6px; background:#0f172a; color:#ffffff;">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        Received
                    </span>`;
                } else if (st === 'Sent') {
                    stBadgeHtml = `<span style="display:inline-flex; align-items:center; gap:5px; font-weight:800; font-size:11px; padding:4px 10px; border-radius:6px; background:#f8fafc; color:#0f172a; border:1px solid #cbd5e1;">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#0f172a" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                        Claim Sent
                    </span>`;
                } else {
                    stBadgeHtml = `<span style="display:inline-flex; align-items:center; gap:5px; font-weight:800; font-size:11px; padding:4px 10px; border-radius:6px; background:#fef2f2; color:#dc2626; border:1px solid #fca5a5;">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line></svg>
                        ${st}
                    </span>`;
                }
                
                let netAmt = parseFloat(i.net_receivable || i.net_invoice_amount || 0);
                let recAmt = parseFloat(i.received_amount || 0);
                let shAmt = parseFloat(i.shortfall_amount || i.shortfall || 0);

                if (st === 'Sent' || st === 'Partial') totalOutstanding += (netAmt - recAmt);
                if (st === 'Partial' || shAmt > 0) shortfallPending += shAmt;
                
                if (i.received_date) {
                    let rDate = new Date(i.received_date);
                    if (rDate.getMonth() === curMonth) receivedMonth += recAmt;
                }
                
                html += `<tr>
                    <td>
                        <div style="color:#0f172a; font-weight:800; font-size:12px;">${i.invoice_number}</div>
                        <div style="font-size:11px; color:#334155; font-weight:700; margin-top:2px;">Ref: ${i.loan_id}</div>
                    </td>
                    <td><strong style="color:#0f172a; font-size:12px;">${i.customer_name}</strong></td>
                    <td><span class="badge-mono-bank">${i.sanction_bank || i.bank_name || 'Bank'}</span></td>
                    <td><strong style="color:#0f172a; font-size:12px;">₹${parseFloat(i.disbursed_amount).toLocaleString('en-IN')}</strong></td>
                    <td style="font-weight:700; color:#0f172a;">${i.commission_pct}%</td>
                    <td><strong style="color:#0f172a; font-size:13px;">₹${netAmt.toLocaleString('en-IN', {minimumFractionDigits:2})}</strong></td>
                    <td>
                        <div>${stBadgeHtml}</div>
                        ${shAmt > 0 ? `<div style="font-size:11px; color:#dc2626; font-weight:700; margin-top:2px;">Shortfall: ₹${shAmt.toLocaleString()}</div>` : ''}
                    </td>
                    <td style="text-align:center;">
                        <div style="display:inline-flex; align-items:center; justify-content:center; gap:4px; white-space:nowrap;">
                            <button onclick="viewBankTaxInvoiceByInvId(${i.id})" style="height:26px; padding:0 8px; font-size:10.5px; font-weight:700; border-radius:6px; background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; cursor:pointer; display:inline-flex; align-items:center; gap:3.5px;" title="Print Tax Invoice">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                                Invoice
                            </button>
                            <button onclick="emailBankTaxInvoice(${i.id})" style="height:26px; padding:0 8px; font-size:10.5px; font-weight:700; border-radius:6px; background:#fef3c7; color:#b45309; border:1px solid #fde68a; cursor:pointer; display:inline-flex; align-items:center; gap:3.5px;" title="Dispatch Tax Invoice Email">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                                Email
                            </button>
                            ${st !== 'Received' ? `<button onclick="openMarkReceived(${i.id}, ${netAmt - recAmt})" style="height:26px; padding:0 8px; font-size:10.5px; font-weight:800; border-radius:6px; background:#059669; color:#ffffff; border:1px solid #059669; cursor:pointer; display:inline-flex; align-items:center; gap:3.5px;">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                                Received
                            </button>` : ''}
                            ${st !== 'Received' ? `<button onclick="openBankEmailReminder(${i.applicant_id})" style="height:26px; padding:0 8px; font-size:10.5px; font-weight:700; border-radius:6px; background:#f3e8ff; color:#7e22ce; border:1px solid #e9d5ff; cursor:pointer; display:inline-flex; align-items:center; gap:3.5px;" title="Mail Escalation">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                                Mail
                            </button>` : ''}
                        </div>
                    </td>
                </tr>`;
            });
            
            tbody.innerHTML = html || '<tr><td colspan="8" style="text-align:center; padding:30px; color:#334155; font-weight:700;">No bank invoices found. Click "+ Create New Bank Claim Invoice" to generate one.</td></tr>';
            
            document.getElementById('bank-total-outstanding').innerText = '₹' + Number(totalOutstanding).toLocaleString('en-IN');
            document.getElementById('bank-received-month').innerText = '₹' + Number(receivedMonth).toLocaleString('en-IN');
            document.getElementById('bank-total-shortfall').innerText = '₹' + Number(shortfallPending).toLocaleString('en-IN');
            if (window.lucide) lucide.createIcons();
        }
    } catch(e) { console.error(e); }
}

async function viewBankTaxInvoiceByInvId(invoice_id) {
    try {
        const res = await fetch(`api.php?api=get_bank_tax_invoice&invoice_id=${invoice_id}`);
        const data = await res.json();
        if (data.success && data.invoice) {
            let inv = data.invoice;
            document.getElementById('ti_invoice_no').innerText = inv.invoice_number;
            document.getElementById('ti_date').innerText = 'Date: ' + (inv.invoice_date || new Date().toLocaleDateString('en-GB'));
            document.getElementById('ti_bank_name').innerText = inv.sanction_bank || inv.bank_name || 'Partner Bank';
            document.getElementById('ti_customer_name').innerText = 'Customer: ' + inv.customer_name;
            document.getElementById('ti_loan_id').innerText = 'Loan Ref ID: ' + inv.loan_id;
            document.getElementById('ti_disbursed_amt').innerText = 'Disbursed Amount: ₹' + Number(inv.disbursed_amount || 0).toLocaleString('en-IN');

            document.getElementById('ti_comm_pct').innerText = (inv.commission_pct || 1.50) + '%';
            document.getElementById('ti_gross_amt').innerText = formatCurrency(inv.gross_amount || (inv.disbursed_amount * (inv.commission_pct/100)));
            document.getElementById('ti_gst_amt').innerText = '+' + formatCurrency(inv.gst_amount);
            document.getElementById('ti_tds_amt').innerText = '-' + formatCurrency(inv.tds_amount);
            document.getElementById('ti_net_amt').innerText = formatCurrency(inv.net_receivable || inv.net_invoice_amount);

            document.getElementById('taxInvoicePreviewModal').style.display = 'flex';
        }
    } catch(e) { console.error(e); }
}

function exportInvoicesCSV() {
    if (currentInvoicesData.length === 0) {
        Swal.fire('No Data', 'No bank invoices to export.', 'info');
        return;
    }
    let csv = ['Invoice Number,Loan ID,Customer Name,Bank Name,Disbursed Amount,Comm %,Gross Amount,GST 18%,TDS 2%,Net Receivable,Status,Received Date,Bank UTR,Shortfall'];
    currentInvoicesData.forEach(i => {
        csv.push([
            `"${i.invoice_number}"`,
            `"${i.loan_id}"`,
            `"${i.customer_name}"`,
            `"${i.sanction_bank || i.bank_name}"`,
            i.disbursed_amount,
            i.commission_pct,
            i.gross_amount || 0,
            i.gst_amount || 0,
            i.tds_amount || 0,
            i.net_receivable || i.net_invoice_amount || 0,
            i.status,
            `"${i.received_date || ''}"`,
            `"${i.bank_utr || ''}"`,
            i.shortfall_amount || i.shortfall || 0
        ].join(','));
    });

    let blob = new Blob([csv.join('\n')], { type: 'text/csv' });
    let url = URL.createObjectURL(blob);
    let a = document.createElement('a');
    a.href = url;
    a.download = `BFS_Bank_Invoices_Ledger_${new Date().toISOString().slice(0,10)}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

function openMarkReceived(id, expected) {
    document.getElementById('recv_invoice_id').value = id;
    document.getElementById('recv_net_receivable').value = expected;
    document.getElementById('recv_expected').value = '₹' + expected.toLocaleString('en-IN');
    document.getElementById('recv_amount').value = expected;
    document.getElementById('recv_date').value = new Date().toISOString().split('T')[0];
    document.getElementById('recv_utr').value = '';
    calcShortfall();
    document.getElementById('markReceivedModal').style.display = 'flex';
}

function calcShortfall() {
    let exp = parseFloat(document.getElementById('recv_net_receivable').value) || 0;
    let rec = parseFloat(document.getElementById('recv_amount').value) || 0;
    let sf = exp - rec;
    let el = document.getElementById('recv_shortfall');
    if(sf > 0) {
        el.innerText = 'Shortfall of ₹' + sf.toLocaleString('en-IN') + ' will be recorded.';
        el.style.color = '#ef4444';
    } else {
        el.innerText = 'No shortfall. Fully cleared!';
        el.style.color = '#10b981';
    }
}

function saveReceived(e) {
    e.preventDefault();
    let fd = new FormData();
    fd.append('id', document.getElementById('recv_invoice_id').value);
    fd.append('received_amount', document.getElementById('recv_amount').value);
    fd.append('received_date', document.getElementById('recv_date').value);
    fd.append('bank_utr', document.getElementById('recv_utr').value);

    fetch('api.php?api=mark_payout_received', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if(res.success) {
                document.getElementById('markReceivedModal').style.display = 'none';
                Swal.fire('Success', 'Invoice marked as received!', 'success').then(() => {
                    loadInvoices();
                    if(document.getElementById('bank-tracker-tbody')) loadBankPayoutTracker();
                });
            } else {
                Swal.fire('Error', res.error, 'error');
            }
        });
}

// ===== PAYEE BANKING DIRECTORY ENGINE =====
let currentPayeeData = [];

async function loadPayeeDirectory() {
    let tbody = document.getElementById('payee-directory-tbody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:30px; color:#334155; font-weight:700;"><i data-lucide="loader" class="spin"></i> Loading payee banking directory...</td></tr>';
    
    try {
        const res = await fetch('api.php?api=get_payees_directory');
        const data = await res.json();
        if (data.success && data.payees) {
            currentPayeeData = data.payees;
            let html = '';
            data.payees.forEach(p => {
                let displayName = p.payee_name || p.name || p.username || 'Payee';

                html += `
                <tr style="border-bottom:1px solid #e2e8f0; font-size:13px;">
                    <td style="padding:12px; font-weight:800; color:#0f172a;">${displayName}</td>
                    <td style="padding:12px;"><span class="badge-mono-outline" style="font-size:10px; padding:2px 7px;">${p.role}</span></td>
                    <td style="padding:12px; color:#0f172a; font-weight:700;">${p.mobile_email || 'N/A'}</td>
                    <td style="padding:12px; font-weight:700;">${p.bank_name ? `<span class="badge-mono-bank">${p.bank_name}</span>` : '<span style="color:#dc2626; font-size:11px; font-weight:700;">⚠️ Not Added</span>'}</td>
                    <td style="padding:12px; font-family:monospace; font-weight:800; color:#0f172a;">${p.account_number ? p.account_number : '<span style="color:#64748b;">-</span>'}</td>
                    <td style="padding:12px; font-family:monospace; font-weight:800; color:#0f172a;">${p.ifsc_code ? p.ifsc_code : '<span style="color:#64748b;">-</span>'}</td>
                    <td style="padding:12px; font-family:monospace; font-weight:800; color:#0f172a; text-transform:uppercase;">${p.pan_number ? p.pan_number : '<span style="color:#64748b;">-</span>'}</td>
                    <td style="padding:12px; text-align:center;">
                        <button type="button" class="btn-black-solid" style="padding:4px 10px; font-size:11px;" onclick="openEditPayeeModal('${p.source_type}', ${p.user_id}, ${p.referral_id}, '${escapeJsString(displayName)}', '${escapeJsString(p.bank_name)}', '${escapeJsString(p.account_number)}', '${escapeJsString(p.ifsc_code)}', '${escapeJsString(p.pan_number)}')">
                            <i data-lucide="edit-3" style="width:13px; height:13px; vertical-align:middle; margin-right:4px;"></i> Edit Bank
                        </button>
                    </td>
                </tr>`;
            });

            tbody.innerHTML = html || '<tr><td colspan="8" style="text-align:center; padding:30px; color:#334155; font-weight:700;">No payees found in directory.</td></tr>';
            if (window.lucide) lucide.createIcons();
        }
    } catch(e) { console.error(e); }
}

function escapeJsString(str) {
    return (str || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
}

function openEditPayeeModal(source_type, user_id, referral_id, name, bank_name, account_number, ifsc_code, pan_number) {
    document.getElementById('epb_source_type').value = source_type;
    document.getElementById('epb_user_id').value = user_id;
    document.getElementById('epb_referral_id').value = referral_id;
    document.getElementById('epb_payee_name').value = name;
    document.getElementById('epb_bank_name').value = bank_name;
    document.getElementById('epb_account_number').value = account_number;
    document.getElementById('epb_ifsc_code').value = ifsc_code;
    document.getElementById('epb_pan_number').value = pan_number;
    document.getElementById('editPayeeBankModal').style.display = 'flex';
}

function savePayeeBankDetails(e) {
    e.preventDefault();
    let fd = new FormData();
    fd.append('source_type', document.getElementById('epb_source_type').value);
    fd.append('user_id', document.getElementById('epb_user_id').value);
    fd.append('referral_id', document.getElementById('epb_referral_id').value);
    fd.append('bank_name', document.getElementById('epb_bank_name').value);
    fd.append('account_number', document.getElementById('epb_account_number').value);
    fd.append('ifsc_code', document.getElementById('epb_ifsc_code').value);
    fd.append('pan_number', document.getElementById('epb_pan_number').value);

    fetch('api.php?api=update_payee_bank_details', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if(res.success) {
                document.getElementById('editPayeeBankModal').style.display = 'none';
                Swal.fire('Updated', res.message, 'success').then(() => {
                    loadPayeeDirectory();
                });
            } else {
                Swal.fire('Error', res.error, 'error');
            }
        });
}

// Global Tab Initializer
document.addEventListener('DOMContentLoaded', () => {
    loadPayouts(1);
    if (document.getElementById('bank-tracker-tbody')) loadBankPayoutTracker();
    if (document.getElementById('bank-invoices-tbody')) loadInvoices();
    if (document.getElementById('payee-directory-tbody')) loadPayeeDirectory();
});
</script>

<?php require_once 'footer.php'; ?>
