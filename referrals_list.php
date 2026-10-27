<?php
require_once 'config.php';
require_role(['Admin'], false);
// Active Tab ('network' or 'inquiries')
$active_tab = $_GET['tab'] ?? 'network';

// Handle AJAX Actions for Partner Inquiries Tab
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    if ($action === 'update_partner_status') {
        $req_id = (int)($_POST['id'] ?? 0);
        $new_status = trim($_POST['status'] ?? '');
        $allowed = ['Pending Review', 'Contacted', 'Approved', 'Rejected'];
        if ($req_id > 0 && in_array($new_status, $allowed)) {
            $stmt = $db->prepare("UPDATE partner_requests SET status = ?, updated_at = datetime('now', 'localtime') WHERE id = ?");
            $stmt->execute([$new_status, $req_id]);
            echo json_encode(['success' => true]);
            exit;
        }
    }

    if ($action === 'approve_dsa') {
        $req_id = (int)($_POST['id'] ?? 0);
        $stmt_r = $db->prepare("SELECT * FROM partner_requests WHERE id = ?");
        $stmt_r->execute([$req_id]);
        $req = $stmt_r->fetch(PDO::FETCH_ASSOC);

        if ($req) {
            $dsa_code = 'DSA-' . strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $req['name']), 0, 3)) . rand(100, 999);
            $ref_type = 'DSA';
            $p_lower = strtolower($req['profession'] ?? '');
            if (strpos($p_lower, 'ca') !== false || strpos($p_lower, 'chartered') !== false) $ref_type = 'CA';
            elseif (strpos($p_lower, 'builder') !== false) $ref_type = 'Builder';
            elseif (strpos($p_lower, 'broker') !== false || strpos($p_lower, 'real estate') !== false) $ref_type = 'Agent';

            $ins = $db->prepare("INSERT INTO referrals (
                referral_id, referrer_type, full_name, mobile, email, 
                city_state, status, commission_rate, payout_frequency, created_at, extra_details
            ) VALUES (?, ?, ?, ?, ?, ?, 'Active', '0.40%', 'Monthly', datetime('now', 'localtime'), ?)");

            $extra = "Onboarded from Website Partner Inquiries. Profession: " . ($req['profession'] ?? '') . " | Monthly Volume: " . ($req['monthly_volume'] ?? '');
            $city_state = trim(($req['city'] ?? '') . ', ' . ($req['state'] ?? ''));

            $ins->execute([
                $dsa_code,
                $ref_type,
                $req['name'],
                $req['phone'],
                $req['email'],
                $city_state,
                $extra
            ]);

            $upd = $db->prepare("UPDATE partner_requests SET status = 'Approved', dsa_code = ?, updated_at = datetime('now', 'localtime') WHERE id = ?");
            $upd->execute([$dsa_code, $req_id]);

            log_activity("Approved Website Partner {$req['name']} as DSA ({$dsa_code})", "referrals_list.php");

            // Dispatch automated welcome & onboarding email
            $email_sent = false;
            if (function_exists('sendPartnerOnboardingEmail')) {
                $m_res = @sendPartnerOnboardingEmail($req, $dsa_code);
                $email_sent = !empty($m_res['success']);
            }

            echo json_encode(['success' => true, 'dsa_code' => $dsa_code, 'email_sent' => $email_sent]);
            exit;
        }
    }

    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}

$page_title = 'Referrals & DSA Directory';
$page_subtitle = ' Manage external referral partners, DSAs, and sub-agents';
$hide_main_header = true;
require_once 'header.php';

$pending_inquiries_cnt = (int)$db->query("SELECT COUNT(*) FROM partner_requests WHERE status = 'Pending Review'")->fetchColumn();
$total_inquiries_cnt = (int)$db->query("SELECT COUNT(*) FROM partner_requests")->fetchColumn();


// Pagination and Filter Setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

$per_page_param = isset($_GET['per_page']) ? trim($_GET['per_page']) : '10';
$allowed_per_page = ['10', '15', '25', '50', '100', 'all'];
if (!in_array($per_page_param, $allowed_per_page)) {
    $per_page_param = '10';
}

$search = isset($_GET['search']) ? strtolower(trim($_GET['search'])) : '';
$type_filter = isset($_GET['type']) ? trim($_GET['type']) : '';
$completion_filter = isset($_GET['completion']) ? trim($_GET['completion']) : '';
$status_filter = isset($_GET['status']) ? strtolower(trim($_GET['status'])) : '';

// Fetch all referrals
$stmt = $db->query("SELECT * FROM referrals ORDER BY created_at DESC");
$all_referrals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// High-signal KPIs calculation
$total_registered_partners = count($all_referrals);
$active_count = 0;

// Filter in PHP
$filtered_referrals = [];
foreach ($all_referrals as $ref) {
    $is_act = (strtolower($ref['status'] ?? 'active') === 'active');
    if ($is_act) {
        $active_count++;
    }

    // Status Filter
    if ($status_filter === 'active' && !$is_act) continue;
    if ($status_filter === 'inactive' && $is_act) continue;

    // Search Filter
    $matches_search = true;
    if ($search !== '') {
        $search_content = strtolower(($ref['full_name'] ?? '') . ' ' . ($ref['referral_id'] ?? '') . ' ' . ($ref['mobile'] ?? '') . ' ' . ($ref['email'] ?? '') . ' ' . ($ref['city_state'] ?? ''));
        if (strpos($search_content, $search) === false) {
            $matches_search = false;
        }
    }
    
    // Type Filter
    $matches_type = true;
    if ($type_filter !== '' && ($ref['referrer_type'] ?? '') !== $type_filter) {
        $matches_type = false;
    }
    
    // Calculate Completion
    $fields = [
        $ref['mobile'] ?? '', $ref['dob'] ?? '', $ref['email'] ?? '',
        $ref['account_number'] ?? '', $ref['ifsc_code'] ?? '', 
        $ref['pan_number'] ?? '', $ref['aadhar_number'] ?? '', 
        $ref['commission_rate'] ?? '',
        $ref['bank_document_path'] ?? '', $ref['pan_document_path'] ?? ''
    ];
    $filled = 0;
    foreach($fields as $f) { if(!empty($f)) $filled++; }
    $completion = round(($filled / count($fields)) * 100);
    $ref['calculated_completion'] = $completion;
    
    // Completion Filter
    $matches_completion = true;
    if ($completion_filter === 'incomplete' && $completion === 100) {
        $matches_completion = false;
    } else if ($completion_filter === 'complete' && $completion < 100) {
        $matches_completion = false;
    }
    
    if ($matches_search && $matches_type && $matches_completion) {
        $filtered_referrals[] = $ref;
    }
}

$inactive_count = $total_registered_partners - $active_count;

// Quick overall high-signal metrics
$overall_leads_cnt = (int)$db->query("SELECT COUNT(*) FROM leads WHERE added_by IS NOT NULL AND added_by != ''")->fetchColumn();
$overall_apps_cnt = (int)$db->query("SELECT COUNT(*) FROM applicants WHERE referral_id IS NOT NULL AND referral_id != ''")->fetchColumn();
$total_all_leads = $overall_leads_cnt + $overall_apps_cnt;

$overall_disb_leads = $db->query("SELECT COUNT(*) as cnt, SUM(COALESCE(loan_amount, 0)) as vol FROM leads WHERE added_by IS NOT NULL AND added_by != '' AND LOWER(stage) IN ('converted','disbursed','completed')")->fetch(PDO::FETCH_ASSOC);
$overall_disb_apps = $db->query("SELECT COUNT(*) as cnt, SUM(COALESCE(loan_amount_requested, 0)) as vol FROM applicants WHERE referral_id IS NOT NULL AND referral_id != '' AND LOWER(overall_status) IN ('converted','disbursed','completed','phase 4','phase 5')")->fetch(PDO::FETCH_ASSOC);

$total_all_disbursed = (int)($overall_disb_leads['cnt'] ?? 0) + (int)($overall_disb_apps['cnt'] ?? 0);
$total_all_volume = (float)($overall_disb_leads['vol'] ?? 0) + (float)($overall_disb_apps['vol'] ?? 0);
$total_all_paid = (float)$db->query("SELECT SUM(COALESCE(amount, net_payable, 0)) FROM payout_distributions WHERE referral_id IS NOT NULL AND referral_id != ''")->fetchColumn();

// Pagination Calculation
$total_items = count($filtered_referrals);
if ($per_page_param === 'all') {
    $per_page = max(1, $total_items);
    $total_pages = 1;
    $offset = 0;
    $paginated_referrals = $filtered_referrals;
    $start_item = ($total_items > 0) ? 1 : 0;
    $end_item = $total_items;
} else {
    $per_page = (int)$per_page_param;
    if ($per_page < 1) $per_page = 10;
    $total_pages = (int)ceil($total_items / $per_page);
    if ($total_pages < 1) $total_pages = 1;
    if ($page > $total_pages) $page = $total_pages;

    $offset = ($page - 1) * $per_page;
    $paginated_referrals = array_slice($filtered_referrals, $offset, $per_page);
    $start_item = ($total_items > 0) ? ($offset + 1) : 0;
    $end_item = min($offset + count($paginated_referrals), $total_items);
}
?>

<style>
/* Modern Luxury Design System for Referrals List */
.partner-view-wrapper {
    padding: 0;
}

/* Executive Header */
.partner-page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
    flex-wrap: wrap;
    gap: 12px;
}
.partner-page-title h1 {
    font-size: 21px;
    font-weight: 800;
    color: #0f172a;
    margin: 0;
    letter-spacing: -0.4px;
    font-family: 'Outfit', sans-serif;
    display: flex;
    align-items: center;
    gap: 10px;
}
.partner-page-title p {
    font-size: 12.5px;
    color: #64748b;
    margin: 2px 0 0 0;
}

/* Add Partner CTA Button */
.btn-add-partner {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    color: #ffffff !important;
    padding: 8px 16px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 13px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    border: 1px solid #0f172a;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.18);
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.btn-add-partner:hover {
    background: linear-gradient(135deg, #000000 0%, #0f172a 100%);
    box-shadow: 0 6px 18px rgba(15, 23, 42, 0.28);
    transform: translateY(-1px);
}

/* KPI Summary Cards Grid */
.partner-kpi-grid {
    display: grid !important;
    grid-template-columns: repeat(4, 1fr) !important;
    gap: 14px !important;
    margin-bottom: 16px !important;
}
@media (max-width: 1200px) {
    .partner-kpi-grid {
        grid-template-columns: repeat(2, 1fr) !important;
    }
}
@media (max-width: 640px) {
    .partner-kpi-grid {
        grid-template-columns: 1fr !important;
    }
}
.partner-kpi-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 14px 16px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
    display: flex;
    align-items: center;
    gap: 12px;
    transition: all 0.2s ease;
}
.partner-kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px -2px rgba(15, 23, 42, 0.08);
    border-color: #cbd5e1;
}
.partner-kpi-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    color: #0f172a;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.partner-kpi-card:hover .partner-kpi-icon {
    background: #0f172a;
    color: #ffffff;
    border-color: #0f172a;
}

/* Theme-Aligned Category Icon Box */
.partner-cat-iconbox {
    width: 28px;
    height: 28px;
    border-radius: 7px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    color: #0f172a;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.partner-table tr:hover .partner-cat-iconbox {
    background: #0f172a;
    color: #ffffff;
    border-color: #0f172a;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.2);
}
.partner-kpi-title {
    font-size: 11px;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 1px;
}
.partner-kpi-val {
    font-size: 20px;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.2;
    font-family: 'Outfit', sans-serif;
}
.partner-kpi-sub {
    font-size: 11px;
    color: #94a3b8;
    margin-top: 1px;
}

/* STRICT SINGLE-LINE FILTER BAR */
.partner-filter-bar {
    display: flex !important;
    flex-direction: row !important;
    flex-wrap: nowrap !important;
    align-items: center !important;
    gap: 8px !important;
    background: #ffffff !important;
    border: 1px solid #e2e8f0 !important;
    border-radius: 10px !important;
    padding: 8px 12px !important;
    margin-bottom: 16px !important;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03) !important;
    box-sizing: border-box !important;
    width: 100% !important;
    overflow: hidden !important;
}

.partner-search-box {
    flex: 1 1 auto !important;
    min-width: 180px !important;
    position: relative !important;
    display: flex !important;
    align-items: center !important;
}
.partner-search-box svg {
    position: absolute !important;
    left: 12px !important;
    color: #94a3b8 !important;
    pointer-events: none !important;
}
.partner-search-box input {
    width: 100% !important;
    height: 36px !important;
    padding: 0 12px 0 36px !important;
    border: 1px solid #cbd5e1 !important;
    border-radius: 7px !important;
    font-size: 13px !important;
    color: #0f172a !important;
    background: #f8fafc !important;
    box-sizing: border-box !important;
    margin: 0 !important;
    outline: none !important;
    transition: all 0.2s !important;
}
.partner-search-box input:focus {
    border-color: #0f172a !important;
    background: #ffffff !important;
    box-shadow: 0 0 0 3px rgba(15, 23, 42, 0.08) !important;
}

.partner-select {
    flex: 0 0 auto !important;
    width: auto !important;
    min-width: unset !important;
    max-width: 160px !important;
    height: 36px !important;
    padding: 0 28px 0 10px !important;
    border: 1px solid #cbd5e1 !important;
    border-radius: 7px !important;
    font-size: 12.5px !important;
    font-weight: 600 !important;
    color: #334155 !important;
    background-color: #f8fafc !important;
    box-sizing: border-box !important;
    margin: 0 !important;
    cursor: pointer !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    outline: none !important;
    appearance: none !important;
    background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e") !important;
    background-repeat: no-repeat !important;
    background-position: right 8px center !important;
    background-size: 14px !important;
}
.partner-select:focus {
    border-color: #0f172a !important;
    background-color: #ffffff !important;
    box-shadow: 0 0 0 3px rgba(15, 23, 42, 0.08) !important;
}

.btn-reset-filters {
    flex: 0 0 auto !important;
    height: 36px !important;
    padding: 0 12px !important;
    border: 1px solid #fecaca !important;
    border-radius: 7px !important;
    background: #fef2f2 !important;
    color: #dc2626 !important;
    font-size: 12px !important;
    font-weight: 700 !important;
    text-decoration: none !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 4px !important;
    box-sizing: border-box !important;
    white-space: nowrap !important;
    margin: 0 !important;
    transition: all 0.2s !important;
}
.btn-reset-filters:hover {
    background: #fee2e2 !important;
    color: #b91c1c !important;
}

/* Master Table Design - Clean & High Density */
.partner-table-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 2px 8px -1px rgba(15, 23, 42, 0.04);
    overflow: hidden;
}
.partner-table {
    width: 100%;
    border-collapse: collapse;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
}
.partner-table thead th {
    background: #f8fafc;
    color: #64748b;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 11px 14px;
    border-bottom: 1.5px solid #e2e8f0;
    white-space: nowrap;
}
.partner-table tbody tr {
    transition: background-color 0.15s ease;
    border-bottom: 1px solid #f1f5f9;
}
.partner-table tbody tr:hover {
    background-color: #f8fafc;
}
.partner-table tbody td {
    padding: 10px 14px !important;
    vertical-align: middle !important;
    border-bottom: 1px solid #f1f5f9 !important;
    color: #334155;
    font-size: 12.5px;
}

/* Compact Partner Avatar */
.partner-avatar-circle {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: linear-gradient(135deg, #0f172a 0%, #334155 100%);
    color: #ffffff;
    font-weight: 800;
    font-size: 13px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 1px 4px rgba(15, 23, 42, 0.15);
}

/* Status Pill Button */
.partner-status-btn {
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 10.5px;
    font-weight: 800;
    letter-spacing: 0.03em;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
    outline: none;
    white-space: nowrap;
}
.partner-status-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);
}

/* Action Buttons Toolbar */
.partner-action-btn {
    width: 30px;
    height: 30px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #475569;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.15s ease;
    box-sizing: border-box;
    padding: 0;
}
.partner-action-btn:hover {
    background: #f8fafc;
    color: #0f172a;
    border-color: #94a3b8;
    transform: translateY(-1px);
}
.partner-action-btn-view {
    background: #0f172a !important;
    color: #ffffff !important;
    border-color: #0f172a !important;
}
.partner-action-btn-view:hover {
    background: #000000 !important;
    box-shadow: 0 3px 8px rgba(0, 0, 0, 0.2);
}
.partner-action-btn-delete {
    color: #dc2626 !important;
    border-color: #fca5a5 !important;
    background: #ffffff !important;
}
.partner-action-btn-delete:hover {
    background: #fef2f2 !important;
    border-color: #ef4444 !important;
    color: #b91c1c !important;
}

/* Modern Interactive ON/OFF Switch Toggle Button */
.partner-onoff-toggle {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    background: transparent !important;
    border: none !important;
    padding: 0 !important;
    margin: 0 !important;
    cursor: pointer !important;
    outline: none !important;
    box-shadow: none !important;
    vertical-align: middle !important;
    height: 30px !important;
}

.onoff-switch-track {
    width: 44px;
    height: 22px;
    border-radius: 20px;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 5px;
    box-sizing: border-box;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.15);
}

/* ON State */
.partner-onoff-toggle.is-on .onoff-switch-track {
    background: #10b981; /* emerald green */
    border: 1px solid #059669;
    box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.12), 0 0 6px rgba(16, 185, 129, 0.25);
}

/* OFF State */
.partner-onoff-toggle.is-off .onoff-switch-track {
    background: #e2e8f0; /* slate soft gray */
    border: 1px solid #cbd5e1;
    box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.08);
}

.onoff-switch-thumb {
    width: 16px;
    height: 16px;
    background: #ffffff;
    border-radius: 50%;
    position: absolute;
    top: 2px;
    left: 2px;
    transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 1px 4px rgba(15, 23, 42, 0.3);
}

.partner-onoff-toggle.is-on .onoff-switch-thumb {
    transform: translateX(22px);
}

.partner-onoff-toggle.is-off .onoff-switch-thumb {
    transform: translateX(0px);
}

.onoff-switch-label {
    font-size: 8.5px;
    font-weight: 800;
    letter-spacing: 0.03em;
    user-select: none;
    line-height: 1;
    transition: opacity 0.2s ease;
}

.onoff-switch-label.label-on {
    color: #ffffff;
    padding-left: 1px;
}

.onoff-switch-label.label-off {
    color: #64748b;
    padding-right: 1px;
}

.partner-onoff-toggle.is-on .onoff-switch-label.label-off {
    opacity: 0;
    pointer-events: none;
}

.partner-onoff-toggle.is-off .onoff-switch-label.label-on {
    opacity: 0;
    pointer-events: none;
}

.partner-onoff-toggle:hover .onoff-switch-track {
    filter: brightness(0.96);
    transform: scale(1.04);
}

.partner-onoff-toggle:active .onoff-switch-track {
    transform: scale(0.96);
}

/* Executive Pagination & Page Management Deck */
.pagination-deck {
    display: flex !important;
    flex-direction: row !important;
    justify-content: space-between !important;
    align-items: center !important;
    flex-wrap: nowrap !important;
    gap: 12px !important;
    padding: 10px 18px !important;
    background: #ffffff !important;
    border-top: 1px solid #e2e8f0 !important;
    box-sizing: border-box !important;
    overflow-x: auto !important;
}
.pagination-info {
    display: inline-flex !important;
    align-items: center !important;
    gap: 14px !important;
    font-size: 12px !important;
    font-weight: 500 !important;
    color: #475569 !important;
    white-space: nowrap !important;
    flex-shrink: 0 !important;
}
.pagination-info b {
    color: #0f172a !important;
    font-weight: 800 !important;
}
.page-size-selector {
    display: inline-flex !important;
    align-items: center !important;
    gap: 6px !important;
    font-size: 11.5px !important;
    color: #64748b !important;
    border-left: 1px solid #e2e8f0 !important;
    padding-left: 14px !important;
    white-space: nowrap !important;
    flex-shrink: 0 !important;
}
.page-size-selector select {
    height: 28px !important;
    padding: 0 8px !important;
    border: 1px solid #cbd5e1 !important;
    border-radius: 6px !important;
    background: #ffffff !important;
    color: #0f172a !important;
    font-size: 11.5px !important;
    font-weight: 700 !important;
    cursor: pointer !important;
    outline: none !important;
    transition: all 0.2s !important;
}
.page-size-selector select:focus {
    border-color: #0f172a !important;
}
.pagination-nav {
    display: inline-flex !important;
    align-items: center !important;
    gap: 4px !important;
    white-space: nowrap !important;
    flex-shrink: 0 !important;
}
.pag-btn {
    width: 28px !important;
    height: 28px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    border: 1px solid #cbd5e1 !important;
    border-radius: 6px !important;
    background: #ffffff !important;
    color: #0f172a !important;
    font-size: 11.5px !important;
    font-weight: 700 !important;
    cursor: pointer !important;
    transition: all 0.15s ease !important;
    padding: 0 !important;
    flex-shrink: 0 !important;
    text-decoration: none !important;
}
.pag-btn svg {
    width: 13px !important;
    height: 13px !important;
}
.pag-btn:hover:not(.disabled) {
    background: #0f172a !important;
    color: #ffffff !important;
    border-color: #0f172a !important;
    transform: translateY(-1px) !important;
}
.pag-btn.disabled {
    opacity: 0.35 !important;
    cursor: not-allowed !important;
    background: #f8fafc !important;
    border-color: #e2e8f0 !important;
    pointer-events: none !important;
}
.pag-numbers {
    display: inline-flex !important;
    align-items: center !important;
    gap: 4px !important;
}
.pag-num-btn {
    min-width: 28px !important;
    height: 28px !important;
    padding: 0 7px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    border: 1px solid #cbd5e1 !important;
    border-radius: 6px !important;
    background: #ffffff !important;
    color: #0f172a !important;
    font-size: 11.5px !important;
    font-weight: 700 !important;
    cursor: pointer !important;
    transition: all 0.15s ease !important;
    text-decoration: none !important;
}
.pag-num-btn:hover {
    background: #f1f5f9 !important;
    border-color: #0f172a !important;
}
.pag-num-btn.active {
    background: #0f172a !important;
    color: #ffffff !important;
    border-color: #0f172a !important;
    box-shadow: 0 2px 5px rgba(15, 23, 42, 0.25) !important;
}
.pag-ellipsis {
    padding: 0 4px !important;
    font-size: 12px !important;
    font-weight: 800 !important;
    color: #94a3b8 !important;
}
.pag-jump {
    display: inline-flex !important;
    align-items: center !important;
    gap: 5px !important;
    font-size: 11.5px !important;
    color: #64748b !important;
    margin-left: 8px !important;
    padding-left: 12px !important;
    border-left: 1px solid #e2e8f0 !important;
    white-space: nowrap !important;
    flex-shrink: 0 !important;
}
.pag-jump input {
    width: 40px !important;
    height: 28px !important;
    text-align: center !important;
    border: 1px solid #cbd5e1 !important;
    border-radius: 6px !important;
    font-size: 12px !important;
    font-weight: 700 !important;
    color: #0f172a !important;
    outline: none !important;
    background: #ffffff !important;
    transition: all 0.2s !important;
    padding: 0 2px !important;
}
.pag-jump input:focus {
    border-color: #0f172a !important;
    box-shadow: 0 0 0 2px rgba(15, 23, 42, 0.08) !important;
}

/* Segmented Luxury Tab Switcher */
.partner-luxury-tabs-bar {
    display: flex;
    gap: 8px;
    margin-bottom: 16px;
    border-bottom: 2px solid #e2e8f0;
    padding-bottom: 8px;
    align-items: center;
}
.partner-luxury-tab {
    padding: 8px 18px;
    border-radius: 10px;
    font-size: 13.5px;
    font-weight: 700;
    color: #64748b;
    background: transparent;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    border: 1px solid transparent;
}
.partner-luxury-tab:hover {
    color: #0f172a;
    background: #f1f5f9;
}
.partner-luxury-tab.active {
    background: #0f172a;
    color: #ffffff !important;
    border-color: #0f172a;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.15);
}
.partner-luxury-pill {
    padding: 2px 7px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 800;
    background: rgba(0,0,0,0.08);
}
.partner-luxury-tab.active .partner-luxury-pill {
    background: rgba(255,255,255,0.22);
    color: #ffffff;
}
.partner-luxury-pill.pill-alert {
    background: #f59e0b !important;
    color: #ffffff !important;
}

/* In-line Filter Bar for Inquiries Tab */
.inq-filter-row {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 10px 14px;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: nowrap;
    margin-bottom: 16px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
}
@media (max-width: 850px) {
    .inq-filter-row { flex-wrap: wrap; }
}
.inq-search-field {
    flex: 2;
    min-width: 200px;
    position: relative;
    display: flex;
    align-items: center;
}
.inq-search-field input {
    width: 100%;
    padding: 8px 12px 8px 36px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #f8fafc;
    color: #0f172a;
    font-size: 13px;
    outline: none;
    transition: all 0.2s;
}
.inq-search-field input:focus {
    background: #ffffff;
    border-color: #0f172a;
}
.inq-search-field i {
    position: absolute;
    left: 11px;
    color: #94a3b8;
    width: 15px;
    height: 15px;
}
.inq-select-field {
    flex: 1;
    min-width: 140px;
}
.inq-select-field select {
    width: 100%;
    padding: 8px 24px 8px 10px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #f8fafc;
    color: #0f172a;
    font-size: 12.5px;
    font-weight: 500;
    outline: none;
    cursor: pointer;
}
.inq-select-field select:focus {
    background: #ffffff;
    border-color: #0f172a;
}
.btn-approve-dsa-luxury {
    background: #0f172a;
    color: #ffffff !important;
    border: none;
    border-radius: 7px;
    padding: 6px 12px;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
}
.btn-approve-dsa-luxury:hover {
    background: #1e293b;
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(15, 23, 42, 0.2);
}
.pr-badge-prof {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    background: #f1f5f9;
    color: #334155;
    border: 1px solid #e2e8f0;
}
.pr-status-pill {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.4px;
    text-transform: uppercase;
    display: inline-block;
    outline: none;
    cursor: pointer;
}
.pr-status-PendingReview { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
.pr-status-Contacted     { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
.pr-status-Approved      { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
.pr-status-Rejected      { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
</style>


<div class="view-container partner-view-wrapper">

    <!-- Segmented Tab Navigation Bar -->
    <div class="partner-luxury-tabs-bar">
        <a href="referrals_list.php?tab=network" class="partner-luxury-tab <?php echo $active_tab === 'network' ? 'active' : ''; ?>">
            <i data-lucide="briefcase" style="width:16px; height:16px;"></i>
            <span>Active DSA Network</span>
            <span class="partner-luxury-pill"><?php echo $total_registered_partners; ?></span>
        </a>
        <a href="referrals_list.php?tab=inquiries" class="partner-luxury-tab <?php echo $active_tab === 'inquiries' ? 'active' : ''; ?>">
            <i data-lucide="handshake" style="width:16px; height:16px;"></i>
            <span>Website Partner Inquiries</span>
            <?php if ($pending_inquiries_cnt > 0): ?>
            <span class="partner-luxury-pill pill-alert"><?php echo $pending_inquiries_cnt; ?> New</span>
            <?php else: ?>
            <span class="partner-luxury-pill"><?php echo $total_inquiries_cnt; ?></span>
            <?php endif; ?>
        </a>
    </div>


<?php if ($active_tab === 'inquiries'): 
    // Inquiries logic & Pagination setup
    $inq_page = isset($_GET['inq_page']) ? (int)$_GET['inq_page'] : 1;
    if ($inq_page < 1) $inq_page = 1;
    $inq_per_page_param = isset($_GET['inq_per_page']) ? trim($_GET['inq_per_page']) : '10';
    $allowed_inq_per_page = ['10', '15', '25', '50', '100', 'all'];
    if (!in_array($inq_per_page_param, $allowed_inq_per_page)) {
        $inq_per_page_param = '10';
    }

    $filter_inq_prof = $_GET['prof'] ?? 'all';
    $filter_inq_status = $_GET['inq_status'] ?? 'all';
    $inq_search = trim($_GET['inq_search'] ?? '');

    $where_inq = "";
    $inq_params = [];

    if ($filter_inq_status !== 'all' && !empty($filter_inq_status)) {
        $where_inq .= " AND status = ?";
        $inq_params[] = $filter_inq_status;
    }
    if ($filter_inq_prof !== 'all' && !empty($filter_inq_prof)) {
        $where_inq .= " AND profession LIKE ?";
        $inq_params[] = "%$filter_inq_prof%";
    }
    if (!empty($inq_search)) {
        $where_inq .= " AND (name LIKE ? OR phone LIKE ? OR email LIKE ? OR city LIKE ? OR state LIKE ?)";
        $s = "%$inq_search%";
        $inq_params[] = $s; $inq_params[] = $s; $inq_params[] = $s; $inq_params[] = $s; $inq_params[] = $s;
    }

    // Total Count for Inquiries
    $stmt_cnt = $db->prepare("SELECT COUNT(*) FROM partner_requests WHERE 1=1" . $where_inq);
    $stmt_cnt->execute($inq_params);
    $total_inq_items = (int)$stmt_cnt->fetchColumn();

    // Pagination Calculation
    if ($inq_per_page_param === 'all') {
        $inq_per_page = max(1, $total_inq_items);
        $total_inq_pages = 1;
        $inq_offset = 0;
        $inq_start_item = ($total_inq_items > 0) ? 1 : 0;
        $inq_end_item = $total_inq_items;
    } else {
        $inq_per_page = (int)$inq_per_page_param;
        if ($inq_per_page < 1) $inq_per_page = 10;
        $total_inq_pages = (int)ceil($total_inq_items / $inq_per_page);
        if ($total_inq_pages < 1) $total_inq_pages = 1;
        if ($inq_page > $total_inq_pages) $inq_page = $total_inq_pages;

        $inq_offset = ($inq_page - 1) * $inq_per_page;
        $inq_start_item = ($total_inq_items > 0) ? ($inq_offset + 1) : 0;
        $inq_end_item = min($inq_offset + $inq_per_page, $total_inq_items);
    }

    $inq_sql = "SELECT * FROM partner_requests WHERE 1=1" . $where_inq . " ORDER BY id DESC";
    if ($inq_per_page_param !== 'all') {
        $inq_sql .= " LIMIT " . (int)$inq_per_page . " OFFSET " . (int)$inq_offset;
    }
    $stmt_inq = $db->prepare($inq_sql);
    $stmt_inq->execute($inq_params);
    $inquiry_records = $stmt_inq->fetchAll(PDO::FETCH_ASSOC);

    if (!function_exists('getInqPageUrl')) {
        function getInqPageUrl($p) {
            $params = $_GET;
            $params['tab'] = 'inquiries';
            $params['inq_page'] = $p;
            return '?' . http_build_query($params);
        }
    }

    $appr_cnt = (int)$db->query("SELECT COUNT(*) FROM partner_requests WHERE status = 'Approved'")->fetchColumn();
    $cont_cnt = (int)$db->query("SELECT COUNT(*) FROM partner_requests WHERE status = 'Contacted'")->fetchColumn();
?>
    <!-- Inquiries Header -->
    <div class="partner-page-header">
        <div class="partner-page-title">
            <h1>
                <i data-lucide="handshake" style="width:22px; height:22px; color:#0f172a;"></i>
                Website Partner Inquiries Desk
                <?php if ($pending_inquiries_cnt > 0): ?>
                <span style="font-size:11.5px; font-weight:700; background:#fef3c7; color:#b45309; border:1px solid #fde68a; padding:2px 8px; border-radius:10px;">
                    <?php echo $pending_inquiries_cnt; ?> Pending
                </span>
                <?php endif; ?>
            </h1>
            <p>B2B Channel Partners, CAs, Builders &amp; DSA Onboarding Requests submitted from <code>bfsfin.com/partner</code></p>
        </div>
        <div>
            <?php if (($_SESSION['role'] ?? '') === 'Admin'): ?>
            <a href="add_referral.php" class="btn-add-partner">
                <i data-lucide="plus-circle" style="width:16px; height:16px;"></i> Add Manual Partner
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Inquiries KPIs -->
    <div class="partner-kpi-grid">
        <div class="partner-kpi-card">
            <div class="partner-kpi-icon" style="background:#eff6ff; color:#2563eb; border-color:#bfdbfe;">
                <i data-lucide="users" style="width:18px; height:18px;"></i>
            </div>
            <div>
                <div class="partner-kpi-value"><?php echo $total_inquiries_cnt; ?></div>
                <div class="partner-kpi-title">Total Requests</div>
            </div>
        </div>
        <div class="partner-kpi-card">
            <div class="partner-kpi-icon" style="background:#fffbeb; color:#d97706; border-color:#fde68a;">
                <i data-lucide="clock" style="width:18px; height:18px;"></i>
            </div>
            <div>
                <div class="partner-kpi-value" style="color:#d97706;"><?php echo $pending_inquiries_cnt; ?></div>
                <div class="partner-kpi-title">Pending Review</div>
            </div>
        </div>
        <div class="partner-kpi-card">
            <div class="partner-kpi-icon" style="background:#f0fdf4; color:#16a34a; border-color:#bbf7d0;">
                <i data-lucide="check-circle-2" style="width:18px; height:18px;"></i>
            </div>
            <div>
                <div class="partner-kpi-value" style="color:#16a34a;"><?php echo $appr_cnt; ?></div>
                <div class="partner-kpi-title">Approved as DSA</div>
            </div>
        </div>
        <div class="partner-kpi-card">
            <div class="partner-kpi-icon" style="background:#f8fafc; color:#64748b; border-color:#e2e8f0;">
                <i data-lucide="phone-call" style="width:18px; height:18px;"></i>
            </div>
            <div>
                <div class="partner-kpi-value"><?php echo $cont_cnt; ?></div>
                <div class="partner-kpi-title">Contacted</div>
            </div>
        </div>
    </div>

    <!-- Unified In-line Filter Bar for Inquiries -->
    <form method="GET" class="inq-filter-row">
        <input type="hidden" name="tab" value="inquiries">
        
        <div class="inq-search-field">
            <i data-lucide="search"></i>
            <input type="text" name="inq_search" placeholder="Search partner name, mobile, email, city..." value="<?php echo htmlspecialchars($inq_search); ?>">
        </div>

        <div class="inq-select-field">
            <select name="prof" onchange="this.form.submit()">
                <option value="all" <?php echo $filter_inq_prof === 'all' ? 'selected' : ''; ?>>All Professions</option>
                <option value="Chartered Accountant" <?php echo $filter_inq_prof === 'Chartered Accountant' ? 'selected' : ''; ?>>CA / Tax Consultant</option>
                <option value="Builder" <?php echo $filter_inq_prof === 'Builder' ? 'selected' : ''; ?>>Builder / Developer</option>
                <option value="Real Estate" <?php echo $filter_inq_prof === 'Real Estate' ? 'selected' : ''; ?>>Real Estate Broker</option>
                <option value="DSA" <?php echo $filter_inq_prof === 'DSA' ? 'selected' : ''; ?>>Direct Selling Agent (DSA)</option>
                <option value="Financial Advisor" <?php echo $filter_inq_prof === 'Financial Advisor' ? 'selected' : ''; ?>>Financial Advisor</option>
            </select>
        </div>

        <div class="inq-select-field">
            <select name="inq_status" onchange="this.form.submit()">
                <option value="all" <?php echo $filter_inq_status === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                <option value="Pending Review" <?php echo $filter_inq_status === 'Pending Review' ? 'selected' : ''; ?>>Pending Review</option>
                <option value="Contacted" <?php echo $filter_inq_status === 'Contacted' ? 'selected' : ''; ?>>Contacted</option>
                <option value="Approved" <?php echo $filter_inq_status === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                <option value="Rejected" <?php echo $filter_inq_status === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
            </select>
        </div>

        <?php if (!empty($inq_search) || $filter_inq_status !== 'all' || $filter_inq_prof !== 'all'): ?>
        <a href="referrals_list.php?tab=inquiries" class="btn-filter-clear">
            <i data-lucide="x" style="width:13px; height:13px;"></i> Clear
        </a>
        <?php endif; ?>
    </form>

    <!-- Inquiries Table Card -->
    <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; box-shadow:0 1px 3px rgba(15,23,42,0.03);">
        <?php if (empty($inquiry_records)): ?>
        <div style="padding:48px 20px; text-align:center; color:#64748b;">
            <i data-lucide="user-x" style="width:44px; height:44px; margin:0 auto 10px; display:block; opacity:0.5;"></i>
            <h3 style="font-size:16px; font-weight:700; color:#0f172a; margin:0 0 4px;">No Partner Requests Found</h3>
            <p style="font-size:13px; margin:0;">No website inquiries match your filter criteria.</p>
        </div>
        <?php else: ?>
        <div style="overflow-x:auto;">
            <table class="partner-table">
                <thead>
                    <tr>
                        <th style="min-width:200px;">Partner Profile</th>
                        <th style="min-width:140px;">Profession</th>
                        <th>Monthly Volume</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Onboarding Action</th>
                        <th style="text-align:right; min-width:110px;">Quick Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inquiry_records as $pr): 
                        $raw_p = preg_replace('/[^0-9]/', '', $pr['phone']);
                        $wa_txt = urlencode("Hi " . $pr['name'] . ", thanks for your interest in joining Bhardwaj Financial Services as a Channel Partner (" . ($pr['profession'] ?: 'DSA') . "). When can we connect for an onboarding call?");
                        $st_cls = str_replace(' ', '', $pr['status']);
                    ?>
                    <tr>
                        <td>
                            <div style="font-weight:700; color:#0f172a; font-size:14px;">
                                <?php echo htmlspecialchars($pr['name']); ?>
                            </div>
                            <div style="font-size:12px; color:#64748b; display:flex; gap:6px; align-items:center; margin-top:2px;">
                                <span><i data-lucide="phone" style="width:11px; height:11px;"></i> <?php echo htmlspecialchars($pr['phone']); ?></span>
                                <?php if (!empty($pr['email']) && !str_contains($pr['email'], 'partner-')): ?>
                                <span>• <?php echo htmlspecialchars($pr['email']); ?></span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size:10.5px; color:#94a3b8; margin-top:2px;">
                                Applied: <?php echo date('d M Y, h:i A', strtotime($pr['created_at'])); ?>
                            </div>
                        </td>
                        <td>
                            <span class="pr-badge-prof"><?php echo htmlspecialchars($pr['profession'] ?: 'Partner'); ?></span>
                            <?php if (!empty($pr['experience'])): ?>
                            <div style="font-size:11px; color:#64748b; margin-top:2px;">Exp: <?php echo htmlspecialchars($pr['experience']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span style="font-weight:700; color:#10b981; font-size:13px;">
                                <?php echo htmlspecialchars($pr['monthly_volume'] ?: 'N/A'); ?>
                            </span>
                        </td>
                        <td>
                            <div style="font-weight:600; color:#0f172a; font-size:13px;"><?php echo htmlspecialchars($pr['city'] ?: 'Agra'); ?></div>
                            <div style="font-size:11px; color:#64748b;"><?php echo htmlspecialchars($pr['state'] ?: 'UP'); ?></div>
                        </td>
                        <td>
                            <select onchange="updateInqStatus(<?php echo $pr['id']; ?>, this.value)" class="pr-status-pill pr-status-<?php echo htmlspecialchars($st_cls); ?>">
                                <option value="Pending Review" <?php echo $pr['status'] === 'Pending Review' ? 'selected' : ''; ?>>Pending Review</option>
                                <option value="Contacted" <?php echo $pr['status'] === 'Contacted' ? 'selected' : ''; ?>>Contacted</option>
                                <option value="Approved" <?php echo $pr['status'] === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="Rejected" <?php echo $pr['status'] === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </td>
                        <td>
                            <?php if (!empty($pr['dsa_code'])): ?>
                            <a href="referrals_list.php?tab=network&search=<?php echo urlencode($pr['dsa_code']); ?>" style="display:inline-flex; align-items:center; gap:4px; font-weight:800; font-size:12px; color:#15803d; text-decoration:none;">
                                <i data-lucide="check-circle" style="width:13px; height:13px;"></i>
                                <?php echo htmlspecialchars($pr['dsa_code']); ?>
                            </a>
                            <?php elseif ($pr['status'] !== 'Rejected'): ?>
                            <button type="button" onclick="approveDSAInq(<?php echo $pr['id']; ?>, '<?php echo htmlspecialchars(addslashes($pr['name'])); ?>')" class="btn-approve-dsa-luxury">
                                <i data-lucide="user-check" style="width:13px; height:13px;"></i>
                                Approve &amp; Onboard DSA
                            </button>
                            <?php else: ?>
                            <span style="font-size:11px; color:#ef4444; font-weight:600;">Rejected</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:right;">
                            <div style="display:flex; gap:6px; justify-content:flex-end;">
                                <?php if (!empty($raw_p)): ?>
                                <a href="tel:<?php echo $raw_p; ?>" class="partner-action-btn" title="Call Partner">
                                    <i data-lucide="phone" style="width:13px; height:13px;"></i>
                                </a>
                                <a href="https://wa.me/91<?php echo substr($raw_p, -10); ?>?text=<?php echo $wa_txt; ?>" target="_blank" class="partner-action-btn" style="background:#25d366 !important; color:#ffffff !important; border-color:#25d366 !important;" title="WhatsApp Partner">
                                    <i data-lucide="message-circle" style="width:13px; height:13px;"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Executive Pagination & Page Management Deck for Partner Inquiries -->
        <div class="pagination-deck">
            <!-- Left Info & Per Page Selector -->
            <div class="pagination-info">
                <span>Showing <b><?php echo $inq_start_item; ?></b> to <b><?php echo $inq_end_item; ?></b> of <b><?php echo $total_inq_items; ?></b> inquiries</span>
                <div class="page-size-selector">
                    <span>Per page:</span>
                    <select id="inqPerPageSelect" onchange="changeInqPerPage(this.value)">
                        <option value="10" <?php echo $inq_per_page_param==='10'?'selected':''; ?>>10</option>
                        <option value="15" <?php echo $inq_per_page_param==='15'?'selected':''; ?>>15</option>
                        <option value="25" <?php echo $inq_per_page_param==='25'?'selected':''; ?>>25</option>
                        <option value="50" <?php echo $inq_per_page_param==='50'?'selected':''; ?>>50</option>
                        <option value="100" <?php echo $inq_per_page_param==='100'?'selected':''; ?>>100</option>
                        <option value="all" <?php echo $inq_per_page_param==='all'?'selected':''; ?>>All</option>
                    </select>
                </div>
            </div>

            <!-- Right Controls -->
            <div class="pagination-nav">
                <?php
                $is_first_inq = ($inq_page <= 1);
                $is_last_inq = ($inq_page >= $total_inq_pages);
                ?>
                <a href="<?php echo $is_first_inq ? 'javascript:void(0)' : getInqPageUrl(1); ?>" 
                   class="pag-btn <?php echo $is_first_inq ? 'disabled' : ''; ?>" 
                   title="First Page">
                    <i data-lucide="chevrons-left"></i>
                </a>
                <a href="<?php echo $is_first_inq ? 'javascript:void(0)' : getInqPageUrl($inq_page - 1); ?>" 
                   class="pag-btn <?php echo $is_first_inq ? 'disabled' : ''; ?>" 
                   title="Previous Page">
                    <i data-lucide="chevron-left"></i>
                </a>

                <!-- Numbered Page Buttons -->
                <div class="pag-numbers">
                    <?php
                    $range = 2;
                    $start_p = max(1, $inq_page - $range);
                    $end_p = min($total_inq_pages, $inq_page + $range);

                    if ($start_p > 1): ?>
                        <a href="<?php echo getInqPageUrl(1); ?>" class="pag-num-btn">1</a>
                        <?php if ($start_p > 2): ?><span class="pag-ellipsis">…</span><?php endif; ?>
                    <?php endif;

                    for ($i = $start_p; $i <= $end_p; $i++): ?>
                        <a href="<?php echo getInqPageUrl($i); ?>" class="pag-num-btn <?php echo ($i === $inq_page) ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor;

                    if ($end_p < $total_inq_pages): ?>
                        <?php if ($end_p < $total_inq_pages - 1): ?><span class="pag-ellipsis">…</span><?php endif; ?>
                        <a href="<?php echo getInqPageUrl($total_inq_pages); ?>" class="pag-num-btn"><?php echo $total_inq_pages; ?></a>
                    <?php endif; ?>
                </div>

                <a href="<?php echo $is_last_inq ? 'javascript:void(0)' : getInqPageUrl($inq_page + 1); ?>" 
                   class="pag-btn <?php echo $is_last_inq ? 'disabled' : ''; ?>" 
                   title="Next Page">
                    <i data-lucide="chevron-right"></i>
                </a>
                <a href="<?php echo $is_last_inq ? 'javascript:void(0)' : getInqPageUrl($total_inq_pages); ?>" 
                   class="pag-btn <?php echo $is_last_inq ? 'disabled' : ''; ?>" 
                   title="Last Page">
                    <i data-lucide="chevrons-right"></i>
                </a>

                <!-- Jump to Page Input -->
                <div class="pag-jump">
                    <span>Page</span>
                    <input type="number" 
                           min="1" 
                           max="<?php echo $total_inq_pages; ?>" 
                           value="<?php echo $inq_page; ?>" 
                           title="Press Enter to jump" 
                           onkeydown="if(event.key==='Enter') jumpToInqPage(this.value)">
                    <span>of <b><?php echo $total_inq_pages; ?></b></span>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
    function updateInqStatus(id, status) {
        const fd = new FormData();
        fd.append('action', 'update_partner_status');
        fd.append('id', id);
        fd.append('status', status);
        fetch('referrals_list.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => { if (d.success) location.reload(); else alert('Failed to update status.'); })
            .catch(() => alert('Network error.'));
    }

    function changeInqPerPage(val) {
        const url = new URL(window.location.href);
        url.searchParams.set('inq_per_page', val);
        url.searchParams.set('inq_page', '1');
        window.location.href = url.toString();
    }

    function jumpToInqPage(val) {
        let p = parseInt(val, 10);
        if (isNaN(p) || p < 1) p = 1;
        const url = new URL(window.location.href);
        url.searchParams.set('inq_page', p);
        window.location.href = url.toString();
    }

    function approveDSAInq(id, name) {
        if (!confirm('Approve ' + name + ' as an official DSA partner? This will generate their DSA Code and create their active profile in the Active DSA Network tab.')) return;
        const fd = new FormData();
        fd.append('action', 'approve_dsa');
        fd.append('id', id);
        fetch('referrals_list.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    let msg = '🎉 Partner onboarded successfully with DSA Code: ' + d.dsa_code;
                    if (d.email_sent) {
                        msg += '\n📧 Official DSA Welcome & Commission Agreement email dispatched successfully!';
                    }
                    alert(msg);
                    location.href = 'referrals_list.php?tab=network&search=' + encodeURIComponent(d.dsa_code);
                } else {
                    alert('Approval failed: ' + (d.error || 'Error'));
                }
            }).catch(() => alert('Network error.'));
    }
    </script>

<?php else: ?>

    <!-- Top Page Header & CTA -->
    <div class="partner-page-header">
        <div class="partner-page-title">
            <h1>
                <i data-lucide="handshake" style="width:22px; height:22px; color:#0f172a;"></i>
                Partner &amp; DSA Directory
                <span style="font-size:11.5px; font-weight:700; background:#f1f5f9; color:#475569; border:1px solid #cbd5e1; padding:2px 8px; border-radius:10px; font-family:'Inter', sans-serif;">
                    <?php echo $total_items; ?> Total
                </span>
            </h1>
            <p>Comprehensive ecosystem of external DSAs, financial advisors, agents, builders &amp; CAs with live access controls</p>
        </div>
        <div>
            <?php if (($_SESSION['role'] ?? '') === 'Admin'): ?>
            <a href="add_referral.php" class="btn-add-partner">
                <i data-lucide="plus-circle" style="width:16px; height:16px;"></i> Add New Partner
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- 4 High-Signal Executive KPI Summary Cards -->
    <div class="partner-kpi-grid">
        <!-- 1. Registered Partners -->
        <div class="partner-kpi-card">
            <div class="partner-kpi-icon">
                <i data-lucide="users" style="width:18px; height:18px;"></i>
            </div>
            <div>
                <div class="partner-kpi-title">Registered Partners</div>
                <div class="partner-kpi-val">
                    <?php echo $total_registered_partners; ?> 
                    <span style="font-size:11px; font-weight:700; color:#0f172a; background:#f1f5f9; border:1px solid #cbd5e1; padding:1px 7px; border-radius:10px; vertical-align:middle;">
                        <?php echo $active_count; ?> Active
                    </span>
                </div>
                <div class="partner-kpi-sub"><?php echo $inactive_count; ?> Deactivated Accounts</div>
            </div>
        </div>

        <!-- 2. Total Sourced Leads -->
        <div class="partner-kpi-card">
            <div class="partner-kpi-icon">
                <i data-lucide="compass" style="width:18px; height:18px;"></i>
            </div>
            <div>
                <div class="partner-kpi-title">Total Sourced Leads</div>
                <div class="partner-kpi-val">
                    <?php echo $total_all_leads; ?> <span style="font-size:12px; font-weight:500; color:#64748b;">Leads</span>
                </div>
                <div class="partner-kpi-sub" style="color:#64748b; font-weight:500;">Customer leads &amp; applications</div>
            </div>
        </div>

        <!-- 3. Disbursed Loan Cases -->
        <div class="partner-kpi-card">
            <div class="partner-kpi-icon">
                <i data-lucide="award" style="width:18px; height:18px;"></i>
            </div>
            <div>
                <div class="partner-kpi-title">Passed / Disbursed Loans</div>
                <div class="partner-kpi-val">
                    <?php echo $total_all_disbursed; ?> <span style="font-size:12px; font-weight:500; color:#64748b;">Cases</span>
                </div>
                <div class="partner-kpi-sub" style="color:#64748b; font-weight:500;">
                    <strong style="color:#0f172a;"><?php echo $total_all_leads > 0 ? round(($total_all_disbursed / $total_all_leads) * 100, 1) : 0; ?>%</strong> Conversion Rate
                </div>
            </div>
        </div>

        <!-- 4. Disbursed Loan Volume & Payouts -->
        <div class="partner-kpi-card">
            <div class="partner-kpi-icon">
                <i data-lucide="wallet" style="width:18px; height:18px;"></i>
            </div>
            <div>
                <div class="partner-kpi-title">Disbursed Volume &amp; Payout</div>
                <div class="partner-kpi-val">
                    ₹ <?php echo number_format($total_all_volume); ?>
                </div>
                <div class="partner-kpi-sub" style="color:#64748b; font-weight:500;">
                    Total Paid Out: <strong style="color:#0f172a;">₹ <?php echo number_format($total_all_paid); ?></strong>
                </div>
            </div>
        </div>
    </div>

    <!-- STRICT SINGLE-LINE FILTER BAR -->
    <form method="GET" action="referrals_list.php" class="partner-filter-bar">
        <div class="partner-search-box">
            <i data-lucide="search" style="width:15px; height:15px;"></i>
            <input type="text" name="search" placeholder="Search partner name, ID code, phone, or city..." value="<?php echo htmlspecialchars($search); ?>">
        </div>

        <select name="type" class="partner-select" onchange="this.form.submit()">
            <option value="">All Categories</option>
            <option value="Builder / Real Estate" <?php if($type_filter=='Builder / Real Estate') echo 'selected'; ?>>Builder / Real Estate</option>
            <option value="Chartered Accountant (CA)" <?php if($type_filter=='Chartered Accountant (CA)') echo 'selected'; ?>>Chartered Accountant (CA)</option>
            <option value="Financial Advisor / DSA" <?php if($type_filter=='Financial Advisor / DSA') echo 'selected'; ?>>Financial Advisor / DSA</option>
            <option value="Individual Agent" <?php if($type_filter=='Individual Agent') echo 'selected'; ?>>Individual Agent</option>
            <option value="PROPERTY DEALER" <?php if($type_filter=='PROPERTY DEALER') echo 'selected'; ?>>Property Dealer</option>
        </select>

        <select name="status" class="partner-select" onchange="this.form.submit()">
            <option value="">All Statuses</option>
            <option value="active" <?php if($status_filter=='active') echo 'selected'; ?>>Active Only</option>
            <option value="inactive" <?php if($status_filter=='inactive') echo 'selected'; ?>>Deactivated Only</option>
        </select>

        <select name="completion" class="partner-select" onchange="this.form.submit()">
            <option value="">All KYC Statuses</option>
            <option value="complete" <?php if($completion_filter=='complete') echo 'selected'; ?>>100% Verified</option>
            <option value="incomplete" <?php if($completion_filter=='incomplete') echo 'selected'; ?>>Pending Documents</option>
        </select>

        <?php if ($search !== '' || $type_filter !== '' || $completion_filter !== '' || $status_filter !== ''): ?>
        <a href="referrals_list.php" class="btn-reset-filters" title="Reset all applied filters">
            <i data-lucide="x" style="width:13px; height:13px;"></i> Clear
        </a>
        <?php endif; ?>

        <button type="submit" style="display:none;">Search</button>
    </form>

    <!-- Master Partners Table Card -->
    <div class="partner-table-card">
        <div style="overflow-x:auto;">
            <table class="partner-table">
                <thead>
                    <tr>
                        <th style="width:120px;">Partner ID</th>
                        <th>Partner Profile</th>
                        <th>Category</th>
                        <th style="text-align:center;">Sourced</th>
                        <th style="text-align:center;">Passed</th>
                        <th>Volume</th>
                        <th>Payout</th>
                        <th style="text-align:center; width:170px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($paginated_referrals)): ?>
                        <tr>
                            <td colspan="8" style="text-align:center; padding:45px 20px;">
                                <div style="display:inline-flex; flex-direction:column; align-items:center; gap:8px;">
                                    <div style="width:44px; height:44px; border-radius:50%; background:#f1f5f9; display:flex; align-items:center; justify-content:center; color:#94a3b8;">
                                        <i data-lucide="search-x" style="width:22px; height:22px;"></i>
                                    </div>
                                    <div style="font-size:14px; font-weight:700; color:#0f172a;">No Referral Partners Found</div>
                                    <div style="font-size:12.5px; color:#64748b;">No partners matched the search criteria or filter options.</div>
                                    <a href="referrals_list.php" class="btn-reset-filters" style="margin-top:4px;">View All Partners</a>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($paginated_referrals as $ref): 
                            $r_code = $ref['referral_id'] ?? '';
                            $r_name = $ref['full_name'] ?? '';

                            // Calculate individual partner metrics
                            $leads_cnt = (int)$db->query("SELECT COUNT(*) FROM leads WHERE added_by = " . $db->quote($r_code) . " OR added_by = " . $db->quote($r_name))->fetchColumn();
                            $apps_cnt = (int)$db->query("SELECT COUNT(*) FROM applicants WHERE referral_id = " . $db->quote($r_code) . " OR added_by = " . $db->quote($r_code))->fetchColumn();
                            $total_leads_given = $leads_cnt + $apps_cnt;

                            $disb_data = $db->query("SELECT SUM(COALESCE(loan_amount, 0)) as vol, COUNT(*) as cnt FROM leads WHERE (added_by = " . $db->quote($r_code) . " OR added_by = " . $db->quote($r_name) . ") AND LOWER(stage) IN ('converted','disbursed','completed')")->fetch(PDO::FETCH_ASSOC);
                            $disb_app_data = $db->query("SELECT SUM(COALESCE(loan_amount_requested, 0)) as vol, COUNT(*) as cnt FROM applicants WHERE (referral_id = " . $db->quote($r_code) . " OR added_by = " . $db->quote($r_code) . ") AND LOWER(overall_status) IN ('converted','disbursed','completed','phase 4','phase 5')")->fetch(PDO::FETCH_ASSOC);

                            $total_disbursed_count = ($disb_data['cnt'] ?? 0) + ($disb_app_data['cnt'] ?? 0);
                            $total_disbursed_vol = ($disb_data['vol'] ?? 0) + ($disb_app_data['vol'] ?? 0);
                            $paid_amt = $db->query("SELECT SUM(COALESCE(amount, net_payable, 0)) FROM payout_distributions WHERE referral_id = " . $db->quote($r_code) . " OR recipient_id = " . $db->quote($r_code))->fetchColumn() ?: 0;

                            $is_active = (strtolower($ref['status'] ?? 'active') === 'active');

                            // Professional Executive Category Styling
                            $raw_type = trim($ref['referrer_type'] ?? 'Partner');
                            $clean_type = ucwords(strtolower($raw_type));

                            if (stripos($raw_type, 'dsa') !== false || stripos($raw_type, 'financial') !== false) {
                                $display_type = 'Financial Advisor';
                                $type_sub = 'DSA Channel Partner';
                                $type_icon = 'briefcase';
                            } elseif (stripos($raw_type, 'builder') !== false) {
                                $display_type = 'Builder & Developer';
                                $type_sub = 'Project Channel Partner';
                                $type_icon = 'building';
                            } elseif (stripos($raw_type, 'property') !== false || stripos($raw_type, 'dealer') !== false || stripos($raw_type, 'real estate') !== false) {
                                $display_type = 'Property Dealer';
                                $type_sub = 'Real Estate Sourcing';
                                $type_icon = 'building-2';
                            } elseif (stripos($raw_type, 'ca') !== false || stripos($raw_type, 'chartered') !== false || stripos($raw_type, 'accountant') !== false) {
                                $display_type = 'Chartered Accountant';
                                $type_sub = 'Tax & Compliance Audit';
                                $type_icon = 'file-text';
                            } else {
                                $display_type = 'Individual Agent';
                                $type_sub = 'Direct Sourcing Agent';
                                $type_icon = 'user-check';
                            }

                            // Rank badge - Clean Executive Obsidian / Slate style (no toy emojis)
                            $rank_badge_html = '';
                            if ($total_disbursed_count >= 5) {
                                $rank_badge_html = '<span style="font-size:9.5px; font-weight:800; background:#0f172a; color:#ffffff; padding:1px 6px; border-radius:4px; margin-left:4px; letter-spacing:0.04em; text-transform:uppercase;">Platinum</span>';
                            } elseif ($total_disbursed_count >= 2) {
                                $rank_badge_html = '<span style="font-size:9.5px; font-weight:800; background:#334155; color:#ffffff; padding:1px 6px; border-radius:4px; margin-left:4px; letter-spacing:0.04em; text-transform:uppercase;">Gold</span>';
                            } elseif ($total_disbursed_count >= 1) {
                                $rank_badge_html = '<span style="font-size:9.5px; font-weight:700; background:#f1f5f9; color:#475569; border:1px solid #cbd5e1; padding:1px 6px; border-radius:4px; margin-left:4px; letter-spacing:0.04em; text-transform:uppercase;">Silver</span>';
                            }
                        ?>
                            <tr style="cursor:pointer;" onclick="window.location.href='view_referral.php?id=<?php echo $ref['id']; ?>'">
                                <!-- 1. Referral Code & Date -->
                                <td style="white-space:nowrap;">
                                    <div style="font-family:'SFMono-Regular', Consolas, monospace; font-size:11.5px; font-weight:800; color:#0f172a; letter-spacing:0.02em;">
                                        <?php echo htmlspecialchars($ref['referral_id'] ?? 'REF-N/A'); ?>
                                    </div>
                                    <div style="font-size:10.5px; color:#94a3b8; margin-top:2px;">
                                        <?php echo !empty($ref['created_at']) ? date('d M Y', strtotime($ref['created_at'])) : 'Recent'; ?>
                                    </div>
                                </td>

                                <!-- 2. Partner Profile (Avatar, Name, Compact Contact & KYC Badge) -->
                                <td>
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <div class="partner-avatar-circle">
                                            <?php echo strtoupper(substr($ref['full_name'] ?? 'P', 0, 1)); ?>
                                        </div>
                                        <div style="display:flex; flex-direction:column; gap:2px; min-width:0;">
                                            <!-- Line 1: Name + Rank + Inline KYC Tag -->
                                            <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                                                <span style="font-size:13.5px; font-weight:700; color:#0f172a; white-space:nowrap;"><?php echo htmlspecialchars($ref['full_name'] ?? 'Unnamed Partner'); ?></span>
                                                <?php echo $rank_badge_html; ?>
                                                <?php if ($ref['calculated_completion'] >= 100): ?>
                                                    <span style="font-size:10px; font-weight:700; color:#059669; background:#ecfdf5; border:1px solid #a7f3d0; padding:1px 6px; border-radius:4px; display:inline-flex; align-items:center; gap:3px;" title="100% KYC Verified">
                                                        <i data-lucide="check" style="width:10px; height:10px;"></i> KYC
                                                    </span>
                                                <?php else: ?>
                                                    <span style="font-size:10px; font-weight:700; color:#d97706; background:#fffbeb; border:1px solid #fde68a; padding:1px 6px; border-radius:4px; display:inline-flex; align-items:center; gap:3px;" title="KYC Docs Pending">
                                                        <?php echo $ref['calculated_completion']; ?>% Docs
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <!-- Line 2: Contact Details (Mobile, WhatsApp, City) -->
                                            <div style="display:flex; align-items:center; gap:6px; font-size:11.5px; color:#64748b;">
                                                <?php if (!empty($ref['mobile'])): ?>
                                                    <span><?php echo htmlspecialchars($ref['mobile']); ?></span>
                                                    <a href="https://wa.me/91<?php echo preg_replace('/[^0-9]/', '', $ref['mobile']); ?>" target="_blank" onclick="event.stopPropagation();" title="WhatsApp Chat" style="color:#059669; display:inline-flex; align-items:center;">
                                                        <i data-lucide="message-square" style="width:11px; height:11px;"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if (!empty($ref['city_state']) && $ref['city_state'] !== 'N/A'): ?>
                                                    <span>&bull;</span>
                                                    <span style="color:#94a3b8;"><?php echo htmlspecialchars($ref['city_state']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- 3. Clean Executive Category -->
                                <td style="white-space:nowrap;">
                                    <div style="display:inline-flex; align-items:center; gap:9px;">
                                        <div class="partner-cat-iconbox">
                                            <i data-lucide="<?php echo $type_icon; ?>" style="width:14px; height:14px;"></i>
                                        </div>
                                        <div style="display:flex; flex-direction:column; line-height:1.25;">
                                            <span style="font-size:12.5px; font-weight:700; color:#0f172a; white-space:nowrap;"><?php echo htmlspecialchars($display_type); ?></span>
                                            <span style="font-size:10.5px; color:#64748b; font-weight:500; white-space:nowrap;"><?php echo htmlspecialchars($type_sub); ?></span>
                                        </div>
                                    </div>
                                </td>

                                <!-- 4. Sourced Leads -->
                                <td style="text-align:center; white-space:nowrap;">
                                    <span style="font-size:12.5px; font-weight:700; color:#0f172a;">
                                        <?php echo $total_leads_given; ?>
                                    </span>
                                    <span style="font-size:11px; color:#64748b;">leads</span>
                                </td>

                                <!-- 5. Disbursed Loan Cases -->
                                <td style="text-align:center; white-space:nowrap;">
                                    <span style="font-size:12.5px; font-weight:800; color:<?php echo $total_disbursed_count > 0 ? '#059669' : '#64748b'; ?>;">
                                        <?php echo $total_disbursed_count; ?> passed
                                    </span>
                                    <?php if ($total_leads_given > 0 && $total_disbursed_count > 0): ?>
                                        <span style="font-size:10px; font-weight:700; color:#059669; margin-left:2px;">
                                            (<?php echo round(($total_disbursed_count / $total_leads_given) * 100); ?>%)
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- 6. Total Loan Volume -->
                                <td style="white-space:nowrap;">
                                    <span style="font-size:13px; font-weight:800; color:#0f172a; font-family:'Outfit', sans-serif;">
                                        ₹ <?php echo number_format($total_disbursed_vol); ?>
                                    </span>
                                </td>

                                <!-- 7. Commission Payout & Slab Rate -->
                                <td style="white-space:nowrap;">
                                    <div style="font-size:12px; color:#0f172a; font-weight:700;">
                                        Paid: ₹<?php echo number_format($paid_amt); ?>
                                    </div>
                                    <div style="font-size:10.5px; color:#64748b;">
                                        Rate: <?php echo htmlspecialchars($ref['commission_rate'] ?? '1.5%'); ?>
                                    </div>
                                </td>

                                <!-- 8. Compact Action Buttons Toolbar with ON/OFF Access Switch -->
                                <td style="text-align:center; white-space:nowrap;" onclick="event.stopPropagation();">
                                    <div style="display:inline-flex; gap:5px; align-items:center;">
                                        <!-- View Partner Intelligence 360° Profile -->
                                        <a href="view_referral.php?id=<?php echo $ref['id']; ?>" class="partner-action-btn partner-action-btn-view" title="Partner Intelligence 360° Profile">
                                            <i data-lucide="eye" style="width:14px; height:14px;"></i>
                                        </a>

                                        <?php if (($_SESSION['role'] ?? '') === 'Admin'): ?>
                                        <!-- Quick Status ON/OFF Toggle Switch -->
                                        <button type="button" 
                                                class="partner-onoff-toggle <?php echo $is_active ? 'is-on' : 'is-off'; ?>" 
                                                id="toggle-action-btn-<?php echo $ref['id']; ?>"
                                                title="<?php echo $is_active ? 'Partner Status: ON (Click to Deactivate)' : 'Partner Status: OFF (Click to Activate)'; ?>" 
                                                onclick="togglePartnerStatus(<?php echo $ref['id']; ?>, '<?php echo addslashes(htmlspecialchars($ref['full_name'])); ?>', '<?php echo $is_active ? 'Active' : 'Inactive'; ?>')">
                                            <span class="onoff-switch-track">
                                                <span class="onoff-switch-label label-on">ON</span>
                                                <span class="onoff-switch-thumb"></span>
                                                <span class="onoff-switch-label label-off">OFF</span>
                                            </span>
                                        </button>

                                        <!-- Set Reminder -->
                                        <button type="button" class="partner-action-btn" title="Set Followup Reminder" onclick="openReminderModal('Referral', <?php echo $ref['id']; ?>, '<?php echo addslashes(htmlspecialchars($ref['full_name'])); ?>')">
                                            <i data-lucide="bell" style="width:14px; height:14px;"></i>
                                        </button>

                                        <!-- Edit Partner Profile -->
                                        <a href="edit_referral.php?id=<?php echo $ref['id']; ?>" class="partner-action-btn" title="Edit Partner Details">
                                            <i data-lucide="edit-3" style="width:14px; height:14px;"></i>
                                        </a>

                                        <!-- Delete Partner -->
                                        <button type="button" onclick="deleteReferral(<?php echo $ref['id']; ?>, '<?php echo addslashes(htmlspecialchars($ref['full_name'])); ?>')" class="partner-action-btn partner-action-btn-delete" title="Delete Profile">
                                            <i data-lucide="trash-2" style="width:14px; height:14px;"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Executive Pagination & Page Management Deck -->
        <div class="pagination-deck">
            <!-- Left Info & Per Page Selector -->
            <div class="pagination-info">
                <span>Showing <b><?php echo $start_item; ?></b> to <b><?php echo $end_item; ?></b> of <b><?php echo $total_items; ?></b> partners</span>
                <div class="page-size-selector">
                    <span>Per page:</span>
                    <select id="partnerPerPageSelect" onchange="changePartnerPerPage(this.value)">
                        <option value="10" <?php echo $per_page_param==='10'?'selected':''; ?>>10</option>
                        <option value="15" <?php echo $per_page_param==='15'?'selected':''; ?>>15</option>
                        <option value="25" <?php echo $per_page_param==='25'?'selected':''; ?>>25</option>
                        <option value="50" <?php echo $per_page_param==='50'?'selected':''; ?>>50</option>
                        <option value="100" <?php echo $per_page_param==='100'?'selected':''; ?>>100</option>
                        <option value="all" <?php echo $per_page_param==='all'?'selected':''; ?>>All</option>
                    </select>
                </div>
            </div>

            <!-- Right Controls -->
            <div class="pagination-nav">
                <?php
                if (!function_exists('getPartnerPageUrl')) {
                    function getPartnerPageUrl($p) {
                        $params = $_GET;
                        $params['page'] = $p;
                        return '?' . http_build_query($params);
                    }
                }
                $is_first = ($page <= 1);
                $is_last = ($page >= $total_pages);
                ?>
                <a href="<?php echo $is_first ? 'javascript:void(0)' : getPartnerPageUrl(1); ?>" 
                   class="pag-btn <?php echo $is_first ? 'disabled' : ''; ?>" 
                   title="First Page">
                    <i data-lucide="chevrons-left"></i>
                </a>
                <a href="<?php echo $is_first ? 'javascript:void(0)' : getPartnerPageUrl($page - 1); ?>" 
                   class="pag-btn <?php echo $is_first ? 'disabled' : ''; ?>" 
                   title="Previous Page">
                    <i data-lucide="chevron-left"></i>
                </a>

                <!-- Numbered Page Buttons -->
                <div class="pag-numbers">
                    <?php
                    $range = 2;
                    $start_p = max(1, $page - $range);
                    $end_p = min($total_pages, $page + $range);

                    if ($start_p > 1): ?>
                        <a href="<?php echo getPartnerPageUrl(1); ?>" class="pag-num-btn">1</a>
                        <?php if ($start_p > 2): ?><span class="pag-ellipsis">…</span><?php endif; ?>
                    <?php endif;

                    for ($i = $start_p; $i <= $end_p; $i++): ?>
                        <a href="<?php echo getPartnerPageUrl($i); ?>" class="pag-num-btn <?php echo ($i === $page) ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor;

                    if ($end_p < $total_pages): ?>
                        <?php if ($end_p < $total_pages - 1): ?><span class="pag-ellipsis">…</span><?php endif; ?>
                        <a href="<?php echo getPartnerPageUrl($total_pages); ?>" class="pag-num-btn"><?php echo $total_pages; ?></a>
                    <?php endif; ?>
                </div>

                <a href="<?php echo $is_last ? 'javascript:void(0)' : getPartnerPageUrl($page + 1); ?>" 
                   class="pag-btn <?php echo $is_last ? 'disabled' : ''; ?>" 
                   title="Next Page">
                    <i data-lucide="chevron-right"></i>
                </a>
                <a href="<?php echo $is_last ? 'javascript:void(0)' : getPartnerPageUrl($total_pages); ?>" 
                   class="pag-btn <?php echo $is_last ? 'disabled' : ''; ?>" 
                   title="Last Page">
                    <i data-lucide="chevrons-right"></i>
                </a>

                <!-- Jump to Page Input -->
                <div class="pag-jump">
                    <span>Page</span>
                    <input type="number" 
                           min="1" 
                           max="<?php echo $total_pages; ?>" 
                           value="<?php echo $page; ?>" 
                           title="Press Enter to jump" 
                           onkeydown="if(event.key==='Enter') jumpToPartnerPage(this.value)">
                    <span>of <b><?php echo $total_pages; ?></b></span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Executive Page Management & Dynamic Pagination
function changePartnerPerPage(val) {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', val);
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
}

function jumpToPartnerPage(val) {
    let p = parseInt(val, 10);
    if (isNaN(p) || p < 1) p = 1;
    const maxP = <?php echo $total_pages; ?>;
    if (p > maxP) p = maxP;
    const url = new URL(window.location.href);
    url.searchParams.set('page', p);
    window.location.href = url.toString();
}

// 1-Click Interactive Status Toggle with Luxury SweetAlert Dialog
async function togglePartnerStatus(id, name, currentStatus) {
    const isCurrentlyActive = (currentStatus.toLowerCase() === 'active');
    const actionWord = isCurrentlyActive ? 'Deactivate' : 'Activate';
    const actionColor = isCurrentlyActive ? '#dc2626' : '#10b981';

    const result = await Swal.fire({
        title: `${actionWord} Partner?`,
        html: isCurrentlyActive 
            ? `Are you sure you want to <b>Deactivate</b> partner <strong>${name}</strong>?<br><br><span style="font-size:13px; color:#dc2626; display:inline-block; background:#fef2f2; padding:10px 16px; border-radius:10px; border:1px solid #fecaca; font-weight:600; text-align:left; line-height:1.5;">🚫 <strong>Dashboard Access Blocked:</strong><br>This partner will not be able to log in to their dashboard and any active session will be terminated immediately.</span>`
            : `Are you sure you want to <b>Activate</b> partner <strong>${name}</strong>?<br><br><span style="font-size:13px; color:#059669; display:inline-block; background:#ecfdf5; padding:10px 16px; border-radius:10px; border:1px solid #a7f3d0; font-weight:600; text-align:left; line-height:1.5;">✓ <strong>Login Access Restored:</strong><br>Partner dashboard credentials and login access will be activated immediately.</span>`,
        icon: isCurrentlyActive ? 'warning' : 'question',
        showCancelButton: true,
        confirmButtonColor: actionColor,
        cancelButtonColor: '#64748b',
        confirmButtonText: `Yes, ${actionWord} Access`,
        cancelButtonText: 'Cancel',
        reverseButtons: true
    });

    if (!result.isConfirmed) return;

    try {
        const fd = new FormData();
        fd.append('id', id);
        fd.append('status', isCurrentlyActive ? 'Inactive' : 'Active');

        const res = await fetch('api.php?api=toggle_referral_status', {
            method: 'POST',
            body: fd
        });
        const data = await res.json();

        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: `${actionWord}d Successfully!`,
                text: data.message,
                timer: 2000,
                showConfirmButton: true,
                confirmButtonText: 'OK'
            });

            // Dynamically update UI without reloading
            const newIsActive = (data.new_status === 'Active');
            const actionBtn = document.getElementById(`toggle-action-btn-${id}`);
            if (actionBtn) {
                if (newIsActive) {
                    actionBtn.className = 'partner-onoff-toggle is-on';
                    actionBtn.setAttribute('title', 'Partner Status: ON (Click to Deactivate)');
                } else {
                    actionBtn.className = 'partner-onoff-toggle is-off';
                    actionBtn.setAttribute('title', 'Partner Status: OFF (Click to Activate)');
                }
                actionBtn.setAttribute('onclick', `togglePartnerStatus(${id}, '${name}', '${data.new_status}')`);
            }
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Action Failed',
                text: data.error || 'Failed to update partner status.',
                confirmButtonText: 'OK'
            });
        }
    } catch (err) {
        Swal.fire({
            icon: 'error',
            title: 'Network Error',
            text: 'Unable to reach the server. Please check your connection.',
            confirmButtonText: 'OK'
        });
    }
}

// Beautiful SweetAlert Delete Confirmation
async function deleteReferral(id, name = 'this referral partner') {
    const result = await Swal.fire({
        title: 'Delete Referral Partner?',
        html: `Are you sure you want to permanently delete partner <strong>${name}</strong>?<br><br><span style="font-size:12.5px; color:#dc2626; background:#fef2f2; border:1px solid #fecaca; padding:8px 14px; border-radius:8px; display:inline-block; line-height:1.5;">⚠️ <strong>Warning:</strong> All linked partner payouts, commission records, and profiles will be affected. This action cannot be reversed.</span>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, Delete Partner',
        cancelButtonText: 'Keep Profile',
        reverseButtons: true
    });

    if (!result.isConfirmed) return;

    try {
        const fd = new FormData();
        fd.append('id', id);
        const res = await fetch('api.php?api=delete_referral', { method: 'POST', body: fd });
        const data = await res.json();
        if(data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Deleted!',
                text: data.message,
                timer: 1800,
                showConfirmButton: false
            }).then(() => window.location.reload());
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Cannot Delete',
                text: data.error || 'Failed to delete partner',
                confirmButtonText: 'OK'
            });
        }
    } catch(err) {
        Swal.fire({
            icon: 'error',
            title: 'Network Error',
            text: 'Unable to connect to server.',
            confirmButtonText: 'OK'
        });
    }
}
<?php endif; // End of tab check ?>

</script>

<?php require_once 'footer.php'; ?>
