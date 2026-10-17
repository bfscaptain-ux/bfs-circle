<?php
define('IS_SUBFOLDER', true);
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'RM'])) {
    die("Access Denied");
}

$hide_main_header = true; // Prevents the old generic header and subtitle from showing
$current_page = 'client_vault';
$page_title = 'Client Vault & Document Repository';

// Handling AJAX Request for Pagination/Search/Filter
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $loan_type = isset($_GET['loan_type']) ? trim($_GET['loan_type']) : '';
    $status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'Completed';
    $time_frame = isset($_GET['time_frame']) ? trim($_GET['time_frame']) : '';
    $sort_by = isset($_GET['sort_by']) ? trim($_GET['sort_by']) : 'newest';
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    $where = "1=1";
    $params = [];
    
    if ($status_filter === 'Completed') {
        $where .= " AND a.overall_status = 'Completed'";
    } elseif ($status_filter === 'active') {
        $where .= " AND a.overall_status IN ('Phase 1', 'Phase 2', 'Phase 3', 'Phase 4')";
    } elseif ($status_filter !== 'all' && !empty($status_filter)) {
        $where .= " AND a.overall_status = ?";
        $params[] = $status_filter;
    }
    
    // Search filter across all key fields
    if ($search !== '') {
        $where .= " AND (a.customer_name LIKE ? OR a.mobile LIKE ? OR a.pan_number LIKE ? OR a.aadhar_number LIKE ? OR a.email LIKE ? OR a.loan_id LIKE ? OR a.city LIKE ?)";
        $search_param = "%$search%";
        $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param, $search_param, $search_param]);
    }
    
    // Loan Type Filter
    if ($loan_type !== '') {
        $where .= " AND a.loan_type = ?";
        $params[] = $loan_type;
    }
    
    // Time Frame Filter
    if ($time_frame !== '') {
        if ($time_frame === '30_days') {
            $where .= " AND a.created_at >= DATE('now', '-30 days')";
        } elseif ($time_frame === '3_months') {
            $where .= " AND a.created_at >= DATE('now', '-3 months')";
        } elseif ($time_frame === '6_months') {
            $where .= " AND a.created_at >= DATE('now', '-6 months') AND a.created_at < DATE('now', '-3 months')";
        } elseif ($time_frame === 'prime') {
            $where .= " AND a.created_at < DATE('now', '-6 months')";
        }
    }
    
    // Sorting logic
    $order_by = "a.created_at DESC";
    if ($sort_by === 'oldest') $order_by = "a.created_at ASC";
    if ($sort_by === 'highest_amount') $order_by = "COALESCE(a.sanctioned_amount, a.loan_amount_requested, 0) DESC";
    if ($sort_by === 'name_asc') $order_by = "a.customer_name ASC";
    
    // Total count
    $stmt_count = $db->prepare("SELECT COUNT(*) FROM applicants a WHERE $where");
    $stmt_count->execute($params);
    $total_records = (int)$stmt_count->fetchColumn();
    $total_pages = ceil($total_records / $limit);
    
    // Fetch clients with doc count and multi-loan counter
    $sql = "SELECT a.*, 
                   (SELECT COUNT(*) FROM applicant_documents WHERE applicant_id = a.id) as doc_count,
                   (SELECT COUNT(*) FROM applicants WHERE (mobile = a.mobile AND a.mobile != '') OR (pan_number = a.pan_number AND a.pan_number != '')) as total_loans_count
            FROM applicants a 
            WHERE $where 
            ORDER BY $order_by LIMIT $limit OFFSET $offset";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode([
        'data' => $clients,
        'total' => $total_records,
        'page' => $page,
        'total_pages' => $total_pages
    ]);
    exit;
}

// Fetch live metrics
$total_vault_clients = (int)$db->query("SELECT COUNT(*) FROM applicants WHERE overall_status = 'Completed'")->fetchColumn();
$total_all_clients = (int)$db->query("SELECT COUNT(*) FROM applicants")->fetchColumn();
$total_volume = (float)$db->query("SELECT SUM(COALESCE(sanctioned_amount, loan_amount_requested, 0)) FROM applicants WHERE overall_status = 'Completed'")->fetchColumn();
$total_docs = (int)$db->query("SELECT COUNT(*) FROM applicant_documents")->fetchColumn();
$repeat_count = (int)$db->query("SELECT COUNT(*) FROM (SELECT mobile FROM applicants WHERE mobile != '' GROUP BY mobile HAVING COUNT(*) > 1)")->fetchColumn();

// Fetch staff for assignment dropdown
$staff_list = $db->query("SELECT username, name, role FROM users WHERE role IN ('Admin', 'RM', 'Staff') AND is_active = 1 ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../header.php';
?>

<style>
    .vault-wrapper { width: 100%; margin: 0 auto; padding: 6px 4px 32px 4px; }
    
    /* Top Executive Header */
    .vault-head-banner {
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
    .vault-head-title {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .vault-head-icon {
        width: 42px;
        height: 42px;
        background: #0f172a;
        color: #ffffff;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 12px rgba(15,23,42,0.15);
    }
    
    /* Metric Cards Grid */
    .metric-grid-four {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
        margin-bottom: 22px;
    }
    .v-metric-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 18px 20px;
        display: flex;
        align-items: center;
        gap: 16px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.02);
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .v-metric-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0,0,0,0.05);
    }
    .v-metric-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        flex-shrink: 0;
    }
    .v-metric-val {
        font-size: 24px;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.1;
        margin-bottom: 4px;
        letter-spacing: -0.02em;
    }
    .v-metric-lbl {
        font-size: 12px;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    /* Main Vault Table Card */
    .vault-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 22px;
        box-shadow: 0 4px 20px -5px rgba(0,0,0,0.04);
    }
    
    /* One-Line Filter Toolbar */
    .vault-filter-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 8px;
        margin-bottom: 20px;
        flex-wrap: nowrap !important;
        width: 100%;
    }
    
    .vault-filter-controls {
        display: flex;
        gap: 6px;
        align-items: center;
        flex-shrink: 0;
        justify-content: flex-end;
        flex-wrap: nowrap !important;
    }

    .vault-filter-select {
        height: 35px !important;
        padding: 0 8px !important;
        border: 1px solid #cbd5e1 !important;
        border-radius: 8px !important;
        font-size: 12px !important;
        font-weight: 600 !important;
        color: #334155 !important;
        background: #ffffff !important;
        cursor: pointer;
        outline: none;
        flex-shrink: 0;
        box-sizing: border-box !important;
    }
    .vault-filter-select:focus {
        border-color: #0f172a !important;
    }

    #filterLoanType { width: 135px !important; }
    #filterTimeFrame { width: 115px !important; }
    #filterSort { width: 135px !important; }

    .vault-search-box {
        position: relative;
        width: 170px;
        min-width: 120px;
        height: 35px;
        display: inline-flex;
        align-items: center;
        flex-shrink: 0;
    }
    .vault-search-box input {
        width: 100% !important;
        height: 35px !important;
        padding: 0 10px 0 28px !important;
        border: 1px solid #cbd5e1 !important;
        border-radius: 8px !important;
        font-size: 12px !important;
        color: #0f172a !important;
        outline: none;
        background: #ffffff !important;
        box-sizing: border-box !important;
    }
    .vault-search-box i,
    .vault-search-box svg {
        position: absolute !important;
        left: 9px !important;
        top: 50% !important;
        transform: translateY(-50%) !important;
        width: 14px !important;
        height: 14px !important;
        color: #94a3b8 !important;
        pointer-events: none !important;
        display: block !important;
        margin: 0 !important;
    }

    /* Segmented Status Tabs */
    .segmented-tabs {
        display: inline-flex;
        background: #f1f5f9;
        padding: 3px;
        border-radius: 9px;
        gap: 3px;
        flex-shrink: 0;
    }
    .segmented-tab {
        padding: 6px 11px;
        font-size: 11.5px;
        font-weight: 700;
        color: #475569;
        border-radius: 7px;
        border: none;
        background: transparent;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 5px;
        white-space: nowrap;
    }
    .segmented-tab:hover {
        color: #0f172a;
    }
    .segmented-tab.active {
        background: #ffffff;
        color: #0f172a;
        box-shadow: 0 2px 6px rgba(0,0,0,0.06);
    }
    
    /* Table Styling */
    .vault-table { width: 100%; border-collapse: separate; border-spacing: 0; }
    .vault-table th {
        background: #f8fafc;
        padding: 14px 16px;
        text-align: left;
        font-size: 11.5px;
        font-weight: 700;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #e2e8f0;
    }
    .vault-table td {
        padding: 14px 16px;
        border-bottom: 1px solid #f1f5f9;
        font-size: 13.5px;
        color: #1e293b;
        vertical-align: middle;
        transition: background 0.15s;
    }
    .vault-table tbody tr:hover td {
        background: #f8fafc;
    }
    
    /* Badges */
    .badge-doc-vault {
        display: inline-flex;
        align-items: center;
        padding: 4px 10px;
        background: #eff6ff;
        color: #1d4ed8;
        border: 1px solid #bfdbfe;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 700;
        gap: 5px;
    }
    .badge-status {
        display: inline-flex;
        align-items: center;
        padding: 4px 9px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 700;
        gap: 4px;
    }
    .badge-multiloan {
        background: #fdf2f8;
        color: #be185d;
        border: 1px solid #fbcfe8;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 800;
        margin-left: 6px;
    }
    
    /* Buttons */
    .btn-action-view {
        background: #f8fafc;
        color: #334155;
        border: 1px solid #cbd5e1;
        padding: 7px 13px;
        border-radius: 7px;
        font-size: 12.5px;
        font-weight: 700;
        cursor: pointer;
        transition: 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        text-decoration: none;
    }
    .btn-action-view:hover {
        background: #0f172a;
        color: #ffffff;
        border-color: #0f172a;
    }
    
    .btn-action-activate {
        background: #0f172a;
        color: #ffffff;
        border: none;
        padding: 7px 15px;
        border-radius: 7px;
        font-size: 12.5px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        box-shadow: 0 2px 8px rgba(15,23,42,0.15);
    }
    .btn-action-activate:hover {
        background: #2563eb;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(37,99,235,0.25);
    }
    
    /* Shimmer Skeleton */
    .sk-bar { height: 14px; background: #e2e8f0; border-radius: 4px; animation: skPulse 1.2s infinite ease-in-out; }
    @keyframes skPulse { 0%, 100% { opacity: 0.4; } 50% { opacity: 0.85; } }
</style>

<div class="vault-wrapper">
    
    <!-- Executive Header Bar (Replaces old duplicate header) -->
    <div class="vault-head-banner">
        <div class="vault-head-title">
            <div class="vault-head-icon">
                <i data-lucide="shield-check" style="width: 22px; height: 22px;"></i>
            </div>
            <div>
                <h1 style="font-size: 22px; font-weight: 800; color: #0f172a; letter-spacing: -0.02em; margin: 0; display: flex; align-items: center; gap: 8px;">
                    Client Vault & Document Repository
                </h1>
                <p style="margin: 3px 0 0 0; color: #64748b; font-size: 13px; font-weight: 500;">
                    Permanent Archive of Verified Borrower Profiles, KYC Documents & Multi-Loan Portfolios
                </p>
            </div>
        </div>
        
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <button onclick="resetFilters()" class="btn-action-view" style="padding: 9px 14px;">
                <i data-lucide="refresh-cw" style="width: 14px; height: 14px;"></i> Refresh Vault
            </button>
            <a href="../add_applicant.php" class="btn-action-activate" style="padding: 9px 16px; text-decoration: none;">
                <i data-lucide="user-plus" style="width: 15px; height: 15px;"></i> New Loan Applicant
            </a>
        </div>
    </div>
    
    <!-- Metric Cards Grid -->
    <div class="metric-grid-four">
        <div class="v-metric-card">
            <div class="v-metric-icon" style="background: #f1f5f9; color: #0f172a;">
                <i data-lucide="folder-check"></i>
            </div>
            <div>
                <div class="v-metric-val"><?= number_format($total_vault_clients) ?></div>
                <div class="v-metric-lbl">Completed Vault Files</div>
            </div>
        </div>
        
        <div class="v-metric-card">
            <div class="v-metric-icon" style="background: #ecfdf5; color: #059669;">
                <i data-lucide="banknote"></i>
            </div>
            <div>
                <div class="v-metric-val">&#8377;<?= number_format($total_volume, 0) ?></div>
                <div class="v-metric-lbl">Total Disbursed Volume</div>
            </div>
        </div>
        
        <div class="v-metric-card">
            <div class="v-metric-icon" style="background: #eff6ff; color: #2563eb;">
                <i data-lucide="files"></i>
            </div>
            <div>
                <div class="v-metric-val"><?= number_format($total_docs) ?></div>
                <div class="v-metric-lbl">Total Documents in Vault</div>
            </div>
        </div>
        
        <div class="v-metric-card">
            <div class="v-metric-icon" style="background: #fdf2f8; color: #db2777;">
                <i data-lucide="repeat"></i>
            </div>
            <div>
                <div class="v-metric-val"><?= number_format($repeat_count) ?></div>
                <div class="v-metric-lbl">Multi-Loan Clients</div>
            </div>
        </div>
    </div>
    
    <!-- Main Vault Card with Filters -->
    <div class="vault-card">
        
        <!-- Controls & Filter Toolbar (All In One Clean Line) -->
        <div class="vault-filter-toolbar">
            
            <!-- Segmented Status Tabs -->
            <div class="segmented-tabs">
                <button type="button" class="segmented-tab active" onclick="setStatusTab('Completed', this)">
                    <i data-lucide="check-circle" style="width: 13px; height: 13px;"></i> Completed Files (Vault)
                </button>
                <button type="button" class="segmented-tab" onclick="setStatusTab('active', this)">
                    <i data-lucide="activity" style="width: 13px; height: 13px;"></i> In-Pipeline Files
                </button>
                <button type="button" class="segmented-tab" onclick="setStatusTab('all', this)">
                    <i data-lucide="layers" style="width: 13px; height: 13px;"></i> All Borrowers
                </button>
            </div>
            
            <!-- Dropdowns & Search (Single Row) -->
            <div class="vault-filter-controls">
                
                <select id="filterLoanType" onchange="debounceSearch()" class="vault-filter-select">
                    <option value="">All Loan Products</option>
                    <option value="Home Loan">Home Loan</option>
                    <option value="Personal Loan">Personal Loan</option>
                    <option value="Business Loan">Business Loan</option>
                    <option value="Mortgage Loan">Mortgage / LAP</option>
                    <option value="Auto Loan">Auto / Vehicle</option>
                    <option value="Education Loan">Education Loan</option>
                    <option value="Credit Card">Credit Card</option>
                </select>
                
                <select id="filterTimeFrame" onchange="debounceSearch()" class="vault-filter-select">
                    <option value="">All Timelines</option>
                    <option value="30_days">Last 30 Days</option>
                    <option value="3_months">Recent (&lt; 3 Months)</option>
                    <option value="6_months">3 - 6 Months</option>
                    <option value="prime">6+ Months (Prime for Repeat Loan)</option>
                </select>
                
                <select id="filterSort" onchange="debounceSearch()" class="vault-filter-select">
                    <option value="newest">Sort: Newest</option>
                    <option value="oldest">Sort: Oldest</option>
                    <option value="highest_amount">Sort: Amount (High)</option>
                    <option value="name_asc">Sort: Name (A-Z)</option>
                </select>
                
                <div class="vault-search-box">
                    <i data-lucide="search"></i>
                    <input type="text" id="searchInput" placeholder="Search Name, Phone, PAN..." oninput="debounceSearch()">
                </div>
            </div>
        </div>
        
        <!-- Table -->
        <div style="overflow-x: auto;">
            <table class="vault-table">
                <thead>
                    <tr>
                        <th>Borrower Profile</th>
                        <th>Loan File & Product</th>
                        <th>Amount & Details</th>
                        <th>Verified KYC Docs</th>
                        <th>Status / Timeline</th>
                        <th style="text-align: right;">Vault Actions</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <!-- Populated by AJAX -->
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; padding-top: 16px; border-top: 1px solid #f1f5f9; flex-wrap: wrap; gap: 12px;">
            <button id="prevBtn" class="btn-action-view" onclick="changePage(-1)">
                <i data-lucide="chevron-left" style="width: 15px; height: 15px;"></i> Previous
            </button>
            <div id="pageInfo" style="font-size: 13px; font-weight: 600; color: #64748b;">Loading records...</div>
            <button id="nextBtn" class="btn-action-view" onclick="changePage(1)">
                Next <i data-lucide="chevron-right" style="width: 15px; height: 15px;"></i>
            </button>
        </div>
        
    </div>
</div>

<!-- ========================================== -->
<!-- ⚡ RE-ACTIVATE FOR NEW LOAN APPLICATION MODAL -->
<!-- ========================================== -->
<div id="activateLoanModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.7); z-index: 999999; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div style="background: #ffffff; width: 100%; max-width: 580px; border-radius: 16px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.35); border: 1px solid #e2e8f0; overflow: hidden; animation: modalPop 0.25s ease-out;">
        
        <!-- Modal Header -->
        <div style="background: #0f172a; color: #ffffff; padding: 20px 24px; display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="width: 34px; height: 34px; background: rgba(255,255,255,0.15); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                    <i data-lucide="zap" style="width: 18px; height: 18px; color: #f59e0b;"></i>
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 17px; font-weight: 800; color: #ffffff; letter-spacing: -0.01em;">
                        Activate New Loan Application
                    </h3>
                    <p style="margin: 2px 0 0 0; font-size: 12px; color: #94a3b8;">
                        Re-activating existing Vault borrower into LOS pipeline
                    </p>
                </div>
            </div>
            <button onclick="closeActivateModal()" style="background: none; border: none; color: #94a3b8; cursor: pointer; padding: 4px;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#94a3b8'">
                <i data-lucide="x" style="width: 20px; height: 20px;"></i>
            </button>
        </div>
        
        <!-- Modal Body / Form -->
        <form id="activateLoanForm" onsubmit="submitActivateLoan(event)" style="padding: 24px;">
            <input type="hidden" name="existing_applicant_id" id="act_applicant_id">
            
            <!-- Borrower Snapshot Banner -->
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 16px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <div style="font-weight: 800; font-size: 15px; color: #0f172a;" id="act_client_name">-</div>
                    <div style="font-size: 12px; color: #64748b; margin-top: 3px; display: flex; gap: 10px;">
                        <span><i data-lucide="phone" style="width: 12px; height: 12px; vertical-align: middle;"></i> <span id="act_client_mobile">-</span></span>
                        <span>•</span>
                        <span>PAN: <strong style="color: #334155;" id="act_client_pan">-</strong></span>
                    </div>
                </div>
                <div style="text-align: right;">
                    <span class="badge-doc-vault" id="act_docs_badge">
                        <i data-lucide="shield" style="width: 12px; height: 12px;"></i> Verified Profile
                    </span>
                </div>
            </div>
            
            <!-- Form Grid -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                        New Loan Product <span style="color: #ef4444;">*</span>
                    </label>
                    <select name="loan_type" id="act_loan_type" required style="width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13.5px; font-weight: 600; color: #0f172a; outline: none; background: #ffffff;">
                        <option value="Personal Loan">Personal Loan</option>
                        <option value="Home Loan">Home Loan</option>
                        <option value="Business Loan">Business Loan</option>
                        <option value="Mortgage Loan">Mortgage Loan / LAP</option>
                        <option value="Auto Loan">Auto / Vehicle Loan</option>
                        <option value="Top-up Loan">Top-up Loan</option>
                        <option value="Education Loan">Education Loan</option>
                        <option value="Credit Card">Credit Card</option>
                    </select>
                </div>
                
                <div>
                    <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                        Loan Sub-Type / Purpose
                    </label>
                    <select name="loan_sub_type" id="act_loan_sub_type" style="width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13.5px; font-weight: 500; color: #0f172a; outline: none; background: #ffffff;">
                        <option value="Fresh Loan">Fresh Loan</option>
                        <option value="Top-up Facility">Top-up Facility</option>
                        <option value="Balance Transfer">Balance Transfer</option>
                        <option value="Working Capital">Working Capital</option>
                        <option value="Home Renovation">Home Renovation</option>
                        <option value="Plot Purchase">Plot Purchase</option>
                    </select>
                </div>
            </div>
            
            <!-- Amount and Tenure -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                        Requested Amount (₹) <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="number" name="loan_amount_requested" id="act_loan_amount" required placeholder="e.g. 500000" min="10000" step="5000" oninput="previewIndianCurrency(this.value)" style="width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; font-weight: 700; color: #0f172a; outline: none; box-sizing: border-box;">
                    <div id="currencyWordsPreview" style="font-size: 11.5px; color: #2563eb; font-weight: 600; margin-top: 4px; min-height: 16px;"></div>
                </div>
                
                <div>
                    <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                        Tenure (Years / Months)
                    </label>
                    <select name="tenure_months" id="act_tenure_months" style="width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13.5px; font-weight: 600; color: #0f172a; outline: none; background: #ffffff;">
                        <option value="12">1 Year (12 Months)</option>
                        <option value="24">2 Years (24 Months)</option>
                        <option value="36" selected>3 Years (36 Months)</option>
                        <option value="48">4 Years (48 Months)</option>
                        <option value="60">5 Years (60 Months)</option>
                        <option value="84">7 Years (84 Months)</option>
                        <option value="120">10 Years (120 Months)</option>
                        <option value="180">15 Years (180 Months)</option>
                        <option value="240">20 Years (240 Months)</option>
                    </select>
                </div>
            </div>
            
            <!-- Staff Assignment -->
            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                    Assign Loan Officer / RM
                </label>
                <select name="assigned_to" id="act_assigned_to" style="width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13.5px; color: #0f172a; outline: none; background: #ffffff;">
                    <option value="">Leave Unassigned (System Queue)</option>
                    <?php foreach($staff_list as $st): ?>
                        <option value="<?= htmlspecialchars($st['username']) ?>">
                            <?= htmlspecialchars($st['name'] ?: $st['username']) ?> (<?= htmlspecialchars($st['role']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Vault Options Box -->
            <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; padding: 14px 16px; margin-bottom: 20px;">
                <div style="font-weight: 700; font-size: 13px; color: #166534; margin-bottom: 10px; display: flex; align-items: center; gap: 6px;">
                    <i data-lucide="check-check" style="width: 16px; height: 16px;"></i> Smart Vault Automation
                </div>
                
                <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #1e293b; font-weight: 600; cursor: pointer; margin-bottom: 8px;">
                    <input type="checkbox" name="clone_documents" value="1" checked style="width: 16px; height: 16px; accent-color: #0f172a; cursor: pointer;">
                    Carry forward verified KYC Documents (PAN, Aadhaar, Statements) from Vault
                </label>
                
                <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #1e293b; font-weight: 600; cursor: pointer;">
                    <input type="checkbox" name="clone_coapplicants" value="1" checked style="width: 16px; height: 16px; accent-color: #0f172a; cursor: pointer;">
                    Carry forward registered Co-Applicants & Guarantors
                </label>
            </div>
            
            <!-- Executive Notes -->
            <div style="margin-bottom: 24px;">
                <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                    Executive Notes / Re-activation Remarks
                </label>
                <textarea name="notes" id="act_notes" rows="2" placeholder="e.g. Existing borrower requested top-up loan. Documents already on file." style="width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; outline: none; resize: vertical; box-sizing: border-box;"></textarea>
            </div>
            
            <!-- Modal Actions -->
            <div style="display: flex; justify-content: flex-end; gap: 12px; align-items: center;">
                <button type="button" onclick="closeActivateModal()" style="padding: 11px 18px; background: #f1f5f9; color: #475569; border: none; border-radius: 8px; font-weight: 700; font-size: 13.5px; cursor: pointer;">
                    Cancel
                </button>
                <button type="submit" id="btnSubmitActivate" style="padding: 11px 22px; background: #0f172a; color: #ffffff; border: none; border-radius: 8px; font-weight: 700; font-size: 13.5px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(15,23,42,0.2);">
                    <i data-lucide="zap" style="width: 16px; height: 16px; color: #f59e0b;"></i>
                    <span id="btnSubmitActivateText">Activate & Enter LOS Pipeline</span>
                </button>
            </div>
        </form>
    </div>
</div>

<style>
@keyframes modalPop {
    from { transform: scale(0.96); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}
</style>

<script>
let currentPage = 1;
let currentStatusTab = 'Completed';
let searchTimeout = null;

function renderSkeleton() {
    const tbody = document.getElementById('tableBody');
    let html = '';
    for(let i=0; i<5; i++) {
        html += `
        <tr>
            <td><div class="sk-bar" style="width:140px; margin-bottom:6px;"></div><div class="sk-bar" style="width:100px;"></div></td>
            <td><div class="sk-bar" style="width:120px; margin-bottom:6px;"></div><div class="sk-bar" style="width:80px;"></div></td>
            <td><div class="sk-bar" style="width:90px; margin-bottom:6px;"></div><div class="sk-bar" style="width:60px;"></div></td>
            <td><div class="sk-bar" style="width:100px;"></div></td>
            <td><div class="sk-bar" style="width:80px;"></div></td>
            <td style="text-align:right;"><div class="sk-bar" style="width:120px; display:inline-block;"></div></td>
        </tr>`;
    }
    tbody.innerHTML = html;
}

function setStatusTab(status, btn) {
    document.querySelectorAll('.segmented-tab').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    currentStatusTab = status;
    currentPage = 1;
    loadVaultData();
}

function calculateMonths(dateStr) {
    if (!dateStr) return 0;
    const d = new Date(dateStr);
    const now = new Date();
    let months = (now.getFullYear() - d.getFullYear()) * 12;
    months -= d.getMonth();
    months += now.getMonth();
    return months <= 0 ? 0 : months;
}

function formatINR(val) {
    if (!val || isNaN(val)) return '0';
    return new Intl.NumberFormat('en-IN').format(val);
}

function previewIndianCurrency(val) {
    const preview = document.getElementById('currencyWordsPreview');
    if (!val || val <= 0) {
        preview.innerText = '';
        return;
    }
    preview.innerText = '₹ ' + formatINR(val);
}

function loadVaultData() {
    renderSkeleton();
    
    const search = encodeURIComponent(document.getElementById('searchInput').value.trim());
    const loanType = encodeURIComponent(document.getElementById('filterLoanType').value);
    const timeFrame = encodeURIComponent(document.getElementById('filterTimeFrame').value);
    const sort = encodeURIComponent(document.getElementById('filterSort').value);
    
    const url = `index.php?ajax=1&page=${currentPage}&status=${currentStatusTab}&search=${search}&loan_type=${loanType}&time_frame=${timeFrame}&sort_by=${sort}`;
    
    fetch(url)
        .then(res => res.json())
        .then(data => {
            const tbody = document.getElementById('tableBody');
            let html = '';
            
            if(!data.data || data.data.length === 0) {
                html = `
                <tr>
                    <td colspan="6" style="text-align:center; padding: 48px 20px; color:#94a3b8;">
                        <i data-lucide="shield-alert" style="width:40px; height:40px; margin-bottom:12px; color:#cbd5e1; display:block; margin:0 auto;"></i>
                        <div style="font-size:15px; font-weight:700; color:#334155; margin-bottom:4px;">No client vault files found</div>
                        <div style="font-size:13px;">Try adjusting your filters or search keywords.</div>
                    </td>
                </tr>`;
            } else {
                data.data.forEach(c => {
                    const months = calculateMonths(c.created_at);
                    let timeBadge = '';
                    if(months >= 6) {
                        timeBadge = `<span class="badge-status" style="background:#dcfce7; color:#166534; border:1px solid #bbf7d0;"><i data-lucide="check-circle-2" style="width:12px; height:12px;"></i> ${months} Mos (Prime)</span>`;
                    } else if(months > 0) {
                        timeBadge = `<span class="badge-status" style="background:#fef3c7; color:#b45309; border:1px solid #fde68a;"><i data-lucide="clock" style="width:12px; height:12px;"></i> ${months} Mos Ago</span>`;
                    } else {
                        timeBadge = `<span class="badge-status" style="background:#f1f5f9; color:#475569; border:1px solid #e2e8f0;"><i data-lucide="sparkles" style="width:12px; height:12px;"></i> Recent</span>`;
                    }
                    
                    let statusBadge = '';
                    if (c.overall_status === 'Completed') {
                        statusBadge = `<span class="badge-status" style="background:#ecfdf5; color:#047857; border:1px solid #a7f3d0;"><i data-lucide="check-check" style="width:12px; height:12px;"></i> Disbursed</span>`;
                    } else if (c.overall_status === 'Rejected') {
                        statusBadge = `<span class="badge-status" style="background:#fef2f2; color:#b91c1c; border:1px solid #fecaca;"><i data-lucide="x-circle" style="width:12px; height:12px;"></i> Rejected</span>`;
                    } else {
                        statusBadge = `<span class="badge-status" style="background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe;"><i data-lucide="layers" style="width:12px; height:12px;"></i> ${c.overall_status}</span>`;
                    }
                    
                    const amountVal = c.sanctioned_amount > 0 ? c.sanctioned_amount : c.loan_amount_requested;
                    const amountStr = formatINR(amountVal);
                    const safeName = (c.customer_name || 'Client').replace(/'/g, "\\'");
                    const safeMobile = (c.mobile || '').replace(/'/g, "\\'");
                    const safePan = (c.pan_number || 'N/A').replace(/'/g, "\\'");
                    const docCount = parseInt(c.doc_count || 0);
                    const totalLoans = parseInt(c.total_loans_count || 1);
                    
                    const multiBadge = totalLoans > 1 ? `<span class="badge-multiloan" title="${totalLoans} total loan files in system">${totalLoans} Loans</span>` : '';
                    
                    html += `
                    <tr>
                        <td>
                            <div style="font-weight: 800; color: #0f172a; font-size: 14.5px; display: flex; align-items: center;">
                                <span>${c.customer_name}</span>
                                ${multiBadge}
                            </div>
                            <div style="font-size: 12.5px; color: #64748b; margin-top: 4px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                <span><i data-lucide="phone" style="width: 12px; height: 12px; vertical-align: middle;"></i> ${c.mobile}</span>
                                <span style="color:#cbd5e1;">•</span>
                                <span>PAN: <strong style="color: #334155; font-family: monospace;">${c.pan_number || 'N/A'}</strong></span>
                                ${c.city ? `<span style="color:#cbd5e1;">•</span><span>${c.city}</span>` : ''}
                            </div>
                        </td>
                        <td>
                            <div style="font-weight: 700; color: #0f172a; font-size: 13.5px;">${c.loan_type || 'Unspecified'}</div>
                            <div style="font-size: 11.5px; font-family: monospace; color: #64748b; margin-top: 2px;">#${c.loan_id}</div>
                        </td>
                        <td>
                            <div style="font-weight: 800; color: #0f172a; font-size: 14.5px;">&#8377;${amountStr}</div>
                            <div style="font-size: 11.5px; color: #64748b; margin-top: 2px;">
                                ${c.sanctioned_amount > 0 ? 'Sanctioned' : 'Requested'}
                            </div>
                        </td>
                        <td>
                            <span class="badge-doc-vault">
                                <i data-lucide="file-check-2" style="width: 13px; height: 13px;"></i> ${docCount} Docs Saved
                            </span>
                        </td>
                        <td>
                            <div>${statusBadge}</div>
                            <div style="margin-top: 4px;">${timeBadge}</div>
                        </td>
                        <td style="text-align: right;">
                            <div style="display: inline-flex; gap: 8px; align-items: center;">
                                <a href="view_client.php?id=${c.id}" class="btn-action-view" title="View complete 360° Profile & Vault Documents">
                                    <i data-lucide="eye" style="width: 13px; height: 13px;"></i> View Vault
                                </a>
                                <button type="button" class="btn-action-activate" onclick="openActivateModal(${c.id}, '${safeName}', '${safeMobile}', '${safePan}', ${docCount}, '${c.loan_type || 'Personal Loan'}', ${amountVal})" title="Activate a new loan application using existing vault documents">
                                    <i data-lucide="zap" style="width: 13px; height: 13px; color: #f59e0b;"></i> Activate New Loan
                                </button>
                            </div>
                        </td>
                    </tr>`;
                });
            }
            
            tbody.innerHTML = html;
            lucide.createIcons();
            
            document.getElementById('pageInfo').innerText = `Showing Page ${data.page} of ${data.total_pages || 1} (${data.total} total files)`;
            document.getElementById('prevBtn').disabled = data.page <= 1;
            document.getElementById('nextBtn').disabled = data.page >= data.total_pages;
        })
        .catch(err => {
            console.error('Vault load error:', err);
            document.getElementById('tableBody').innerHTML = `<tr><td colspan="6" style="text-align:center; padding:30px; color:#dc2626;">Error loading vault records. Please refresh.</td></tr>`;
        });
}

function debounceSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        currentPage = 1;
        loadVaultData();
    }, 400);
}

function resetFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('filterLoanType').value = '';
    document.getElementById('filterTimeFrame').value = '';
    document.getElementById('filterSort').value = 'newest';
    currentPage = 1;
    loadVaultData();
}

function changePage(delta) {
    currentPage += delta;
    loadVaultData();
}

// ==========================================
// MODAL CONTROLS & SUBMISSION
// ==========================================
function openActivateModal(id, name, mobile, pan, docsCount, lastLoanType, lastAmount) {
    document.getElementById('act_applicant_id').value = id;
    document.getElementById('act_client_name').innerText = name;
    document.getElementById('act_client_mobile').innerText = mobile;
    document.getElementById('act_client_pan').innerText = pan;
    document.getElementById('act_docs_badge').innerHTML = `<i data-lucide="shield-check" style="width:12px; height:12px;"></i> ${docsCount} Verified Docs in Vault`;
    
    // Sensible defaults
    const amountInput = document.getElementById('act_loan_amount');
    amountInput.value = lastAmount > 0 ? lastAmount : 500000;
    previewIndianCurrency(amountInput.value);
    
    document.getElementById('activateLoanModal').style.display = 'flex';
    lucide.createIcons();
}

function closeActivateModal() {
    document.getElementById('activateLoanModal').style.display = 'none';
}

function submitActivateLoan(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSubmitActivate');
    const btnText = document.getElementById('btnSubmitActivateText');
    const form = document.getElementById('activateLoanForm');
    const formData = new FormData(form);
    
    btn.disabled = true;
    btnText.innerText = 'Creating LOS Application...';
    
    fetch('../api.php?api=reapply_applicant', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            closeActivateModal();
            
            // Show Executive Toast Notification
            if(typeof showNotification === 'function') {
                showNotification(`New Application #${data.loan_id} successfully activated for ${data.customer_name}! ${data.cloned_docs_count} verified documents carried forward.`, 'success');
            } else {
                alert(`Success! Application #${data.loan_id} created with ${data.cloned_docs_count} documents carried forward.`);
            }
            
            // Seamless redirect into LOS pipeline after 1 second
            setTimeout(() => {
                window.location.href = `../applicant_documents.php?id=${data.id}&activated=1&loan_id=${encodeURIComponent(data.loan_id)}`;
            }, 1000);
        } else {
            alert(data.error || 'Failed to activate loan application. Please try again.');
            btn.disabled = false;
            btnText.innerText = 'Activate & Enter LOS Pipeline';
        }
    })
    .catch(err => {
        console.error('Activation error:', err);
        alert('Server connection error. Please try again.');
        btn.disabled = false;
        btnText.innerText = 'Activate & Enter LOS Pipeline';
    });
}

// Initial Load
document.addEventListener("DOMContentLoaded", () => {
    lucide.createIcons();
    loadVaultData();
});
</script>

<?php require_once __DIR__ . '/../footer.php'; ?>
