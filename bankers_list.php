<?php
require_once 'config.php';
require_role(['Admin', 'RM']);

$page_title = 'Bankers Directory';
$page_subtitle = 'View and manage external bank relationship managers';
$hide_main_header = true;
require_once 'header.php';

// Pagination and Filter Setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$per_page = 10;

$search = isset($_GET['search']) ? strtolower(trim($_GET['search'])) : '';
$bank_filter = isset($_GET['bank']) ? trim($_GET['bank']) : '';
$bank_type_filter = isset($_GET['bank_type']) ? trim($_GET['bank_type']) : '';
$state_filter = isset($_GET['state']) ? trim($_GET['state']) : '';
$city_filter = isset($_GET['city']) ? trim($_GET['city']) : '';
$category_filter = isset($_GET['category']) ? trim($_GET['category']) : '';
$amount_req = isset($_GET['amount']) && $_GET['amount'] !== '' ? (float)$_GET['amount'] : 0;
$roi_filter = isset($_GET['roi']) && $_GET['roi'] !== '' ? (float)$_GET['roi'] : 0;

// Fetch all first to allow array filtering
$stmt = $db->query("SELECT * FROM bankers ORDER BY created_at DESC");
$all_bankers = $stmt->fetchAll();

// Extract ONLY banks that exist in system (bankers, bank logins, invoices)
$system_banks_stmt = $db->query("
    SELECT DISTINCT bank_name FROM (
        SELECT bank_name FROM bankers WHERE bank_name IS NOT NULL AND bank_name != ''
        UNION
        SELECT bank_name FROM applicant_bank_logins WHERE bank_name IS NOT NULL AND bank_name != ''
        UNION
        SELECT bank_name FROM bank_payout_invoices WHERE bank_name IS NOT NULL AND bank_name != ''
    ) WHERE bank_name != 'OTHER_CUSTOM' AND bank_name != 'Other (Manual Entry)' ORDER BY bank_name ASC
");
$saved_system_banks = array_values(array_unique(array_filter($system_banks_stmt->fetchAll(PDO::FETCH_COLUMN))));

// Currency Formatter Helper
if (!function_exists('formatINR_Compact')) {
    function formatINR_Compact($n) {
        $n = floatval($n);
        if ($n >= 10000000) return '₹' . number_format($n / 10000000, 2) . ' Cr';
        if ($n >= 100000)   return '₹' . number_format($n / 100000, 2) . ' L';
        if ($n >= 1000)     return '₹' . number_format($n / 1000, 1) . 'K';
        return '₹' . number_format($n, 0);
    }
}

// Master Lending Analytics (For Compact Analytics Section)
$all_banks_perf_stmt = $db->query("
    SELECT DISTINCT bank_name FROM (
        SELECT bank_name FROM bankers WHERE bank_name IS NOT NULL AND bank_name != ''
        UNION
        SELECT bank_name FROM applicant_bank_logins WHERE bank_name IS NOT NULL AND bank_name != ''
        UNION
        SELECT sanction_bank as bank_name FROM applicants WHERE sanction_bank IS NOT NULL AND sanction_bank != ''
        UNION
        SELECT disbursing_bank as bank_name FROM applicants WHERE disbursing_bank IS NOT NULL AND disbursing_bank != ''
    ) WHERE bank_name != '' AND bank_name != 'OTHER_CUSTOM' AND bank_name != 'Other (Manual Entry)' ORDER BY bank_name ASC
");
$perf_bank_names = array_values(array_unique(array_filter($all_banks_perf_stmt->fetchAll(PDO::FETCH_COLUMN))));

$bank_perf_list = [];
foreach ($perf_bank_names as $b) {
    $app_stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_files,
            COUNT(CASE WHEN LOWER(overall_status) IN ('completed','disbursed','sanctioned','phase 4','phase 5') THEN 1 END) as sanctioned_files,
            COALESCE(SUM(CASE WHEN LOWER(overall_status) IN ('completed','disbursed') THEN COALESCE(disbursed_amount, loan_amount_requested, 0) ELSE 0 END), 0) as disbursed_amt,
            COALESCE(SUM(CASE WHEN LOWER(overall_status) IN ('sanctioned') THEN COALESCE(sanctioned_amount, loan_amount_requested, 0) ELSE 0 END), 0) as sanctioned_amt
        FROM applicants 
        WHERE (sanction_bank LIKE ? OR disbursing_bank LIKE ? OR login_fee_bank LIKE ?)
    ");
    $app_stmt->execute(["%$b%", "%$b%", "%$b%"]);
    $apps = $app_stmt->fetch(PDO::FETCH_ASSOC);

    $inv_stmt = $db->prepare("SELECT COALESCE(SUM(net_invoice_amount), 0), COALESCE(SUM(CASE WHEN status='Received' THEN net_invoice_amount ELSE 0 END), 0) FROM bank_payout_invoices WHERE bank_name LIKE ?");
    $inv_stmt->execute(["%$b%"]);
    $inv = $inv_stmt->fetch(PDO::FETCH_NUM);

    $disb = floatval($apps['disbursed_amt'] ?? 0);
    $sanc = floatval($apps['sanctioned_amt'] ?? 0);
    $tot_vol = $disb > 0 ? $disb : $sanc;
    $files = intval($apps['total_files'] ?? 0);

    $bank_perf_list[] = [
        'bank_name' => $b,
        'files' => $files,
        'sanctioned' => intval($apps['sanctioned_files'] ?? 0),
        'disbursed_volume' => $disb,
        'total_volume' => $tot_vol,
        'invoiced' => floatval($inv[0] ?? 0),
        'received' => floatval($inv[1] ?? 0)
    ];
}

usort($bank_perf_list, function($a, $b) {
    if ($b['total_volume'] != $a['total_volume']) return $b['total_volume'] <=> $a['total_volume'];
    return $b['files'] <=> $a['files'];
});

$net_banks_cnt = count($bank_perf_list);
$net_files_cnt = array_sum(array_column($bank_perf_list, 'files'));
$net_disb_total = array_sum(array_column($bank_perf_list, 'disbursed_volume'));
$net_inv_total = array_sum(array_column($bank_perf_list, 'invoiced'));
$net_rec_total = array_sum(array_column($bank_perf_list, 'received'));

$chart_bar_labels = [];
$chart_bar_vols = [];
$chart_donut_labels = [];
$chart_donut_vols = [];

foreach ($bank_perf_list as $bp) {
    $short = str_replace(' - Commercial Mortgages', ' (Comm)', $bp['bank_name']);
    $vol_lakhs = round($bp['total_volume'] / 100000, 2);
    $chart_bar_labels[] = $short;
    $chart_bar_vols[] = $vol_lakhs;
    if ($vol_lakhs > 0) {
        $chart_donut_labels[] = $short;
        $chart_donut_vols[] = $vol_lakhs;
    }
}
if (empty($chart_donut_vols)) {
    $chart_donut_labels = ['No Volume Data'];
    $chart_donut_vols = [1];
}

// Unique extracts for dropdowns (ONLY system-saved values)
$unique_banks = array_values(array_unique(array_filter(array_column($all_bankers, 'bank_name'))));
sort($unique_banks);

$unique_types = array_values(array_unique(array_filter(array_column($all_bankers, 'bank_type'))));
sort($unique_types);

$unique_states = array_values(array_unique(array_filter(array_column($all_bankers, 'state'))));
sort($unique_states);

$unique_cities = array_values(array_unique(array_filter(array_column($all_bankers, 'city'))));
sort($unique_cities);

$saved_state_city_map = [];
foreach ($all_bankers as $b) {
    if (!empty($b['state']) && !empty($b['city'])) {
        $st = trim($b['state']);
        $ct = trim($b['city']);
        if ($st !== '' && $ct !== '') {
            if (!isset($saved_state_city_map[$st])) {
                $saved_state_city_map[$st] = [];
            }
            if (!in_array($ct, $saved_state_city_map[$st])) {
                $saved_state_city_map[$st][] = $ct;
            }
        }
    }
}
foreach ($saved_state_city_map as $st => &$cities) {
    sort($cities);
}

// Filter in PHP
$filtered_bankers = [];
foreach ($all_bankers as $banker) {
    // Search Filter
    $matches_search = true;
    if ($search !== '') {
        $search_content = strtolower($banker['full_name'] . ' ' . $banker['bank_name'] . ' ' . $banker['contact_number'] . ' ' . $banker['loan_category'] . ' ' . $banker['city'] . ' ' . $banker['state']);
        if (strpos($search_content, $search) === false) {
            $matches_search = false;
        }
    }
    
    // Dropdown Filters
    $matches_dropdowns = true;
    if ($bank_filter !== '' && strcasecmp(trim($banker['bank_name'] ?? ''), $bank_filter) !== 0) $matches_dropdowns = false;
    if ($bank_type_filter !== '' && strcasecmp(trim($banker['bank_type'] ?? ''), $bank_type_filter) !== 0) $matches_dropdowns = false;
    if ($state_filter !== '' && strcasecmp(trim($banker['state'] ?? ''), $state_filter) !== 0) $matches_dropdowns = false;
    if ($city_filter !== '' && strcasecmp(trim($banker['city'] ?? ''), $city_filter) !== 0) $matches_dropdowns = false;
    
    // Amount Recommendation Engine
    $matches_amount = true;
    if ($amount_req > 0) {
        $min = (float)$banker['min_loan_limit'];
        $max = (float)$banker['max_loan_limit'];
        if ($amount_req < $min || ($max > 0 && $amount_req > $max)) {
            $matches_amount = false;
        }
    }

    // ROI Filter
    $matches_roi = true;
    if ($roi_filter > 0) {
        $minR = (float)($banker['min_roi'] ?? 0);
        if ($minR > $roi_filter && $minR > 0) {
            $matches_roi = false;
        }
    }
    
    // Category Filter
    $matches_category = true;
    if ($category_filter !== '') {
        if (stripos($banker['loan_category'] ?? '', $category_filter) === false) {
            $matches_category = false;
        }
    }
    
    if ($matches_search && $matches_dropdowns && $matches_amount && $matches_category && $matches_roi) {
        $filtered_bankers[] = $banker;
    }
}

$total_items = count($filtered_bankers);
$total_pages = ceil($total_items / $per_page);
if ($total_pages == 0) $total_pages = 1;
if ($page > $total_pages) $page = $total_pages;

$offset = ($page - 1) * $per_page;
$paginated_bankers = array_slice($filtered_bankers, $offset, $per_page);

$is_filtered = (!empty($search) || !empty($bank_filter) || !empty($bank_type_filter) || !empty($state_filter) || !empty($city_filter) || !empty($category_filter) || $amount_req > 0 || $roi_filter > 0);

// Bank Logo & Brand Badge Generator (Crisp SVG & Corporate Theme, Zero Emojis)
function render_bank_logo_badge($bank_name_raw) {
    $bank_name = trim($bank_name_raw ?: 'N/A');
    $lower = strtolower($bank_name);
    
    // Split sub-department/branch if hyphenated
    $main_name = $bank_name;
    $sub_name = '';
    if (strpos($bank_name, ' - ') !== false) {
        $parts = explode(' - ', $bank_name, 2);
        $main_name = trim($parts[0]);
        $sub_name = trim($parts[1]);
    }

    if (strpos($lower, 'hdfc') !== false) {
        $avatar_html = '
        <span class="bank-avatar-box" style="background:#004c8f;" title="HDFC Bank">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none">
                <rect x="3" y="9.5" width="18" height="5" fill="#ffffff"/>
                <rect x="9.5" y="3" width="5" height="18" fill="#ffffff"/>
                <rect x="9" y="9" width="6" height="6" fill="#ed232a"/>
            </svg>
        </span>';
    } elseif (strpos($lower, 'state bank') !== false || strpos($lower, 'sbi') !== false) {
        $avatar_html = '
        <span class="bank-avatar-box" style="background:#0072bc;" title="State Bank of India">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none">
                <circle cx="12" cy="12" r="9" fill="#ffffff"/>
                <circle cx="12" cy="9.8" r="3.2" fill="#0072bc"/>
                <rect x="10.8" y="9.8" width="2.4" height="7.2" fill="#0072bc"/>
            </svg>
        </span>';
    } elseif (strpos($lower, 'icici') !== false) {
        $avatar_html = '
        <span class="bank-avatar-box" style="background:#c84815;" title="ICICI Bank">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none">
                <circle cx="12" cy="6.5" r="2.2" fill="#ffffff"/>
                <path d="M10 10.5h4v7h-4z" fill="#ffffff"/>
            </svg>
        </span>';
    } elseif (strpos($lower, 'axis') !== false) {
        $avatar_html = '
        <span class="bank-avatar-box" style="background:#97144d;" title="Axis Bank">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none">
                <path d="M12 4.5l5.5 14h-3.6l-1.9-5-1.9 5H6.5l5.5-14z" fill="#ffffff"/>
            </svg>
        </span>';
    } elseif (strpos($lower, 'kotak') !== false) {
        $avatar_html = '
        <span class="bank-avatar-box" style="background:#ed1c24;" title="Kotak Mahindra Bank">
            <span style="color:#ffffff; font-weight:900; font-size:13px; font-family:\'Inter\',sans-serif; line-height:1;">K</span>
        </span>';
    } elseif (strpos($lower, 'baroda') !== false || strpos($lower, 'bob') !== false) {
        $avatar_html = '
        <span class="bank-avatar-box" style="background:#f26522;" title="Bank of Baroda">
            <span style="color:#ffffff; font-weight:900; font-size:9.5px; font-family:\'Inter\',sans-serif; letter-spacing:-0.5px; line-height:1;">BOB</span>
        </span>';
    } elseif (strpos($lower, 'punjab') !== false || strpos($lower, 'pnb') !== false) {
        $avatar_html = '
        <span class="bank-avatar-box" style="background:#a20034;" title="PNB">
            <span style="color:#ffffff; font-weight:900; font-size:9.5px; font-family:\'Inter\',sans-serif; line-height:1;">PNB</span>
        </span>';
    } else {
        $avatar_html = '
        <span class="bank-avatar-box" style="background:#0f172a;" title="Bank Entity">
            <i data-lucide="landmark" style="width:14px; height:14px; color:#ffffff;"></i>
        </span>';
    }

    $url = 'view_bank.php?bank_name=' . urlencode($bank_name);
    
    $html = '<a href="' . htmlspecialchars($url) . '" class="bank-entry-link" onclick="event.stopPropagation();" title="View ' . htmlspecialchars($bank_name) . ' 360° Portal">';
    $html .= $avatar_html;
    $html .= '<div class="bank-text-group">';
    $html .= '<span class="bank-main-name">' . htmlspecialchars($main_name) . '</span>';
    if ($sub_name) {
        $html .= '<span class="bank-sub-name">' . htmlspecialchars($sub_name) . '</span>';
    }
    $html .= '</div>';
    $html .= '<i data-lucide="arrow-up-right" class="bank-arrow-icon"></i>';
    $html .= '</a>';
    
    return $html;
}
?>

<style>
/* One-Line Streamlined Filter Toolbar */
.bankers-filter-toolbar {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 8px 10px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
    box-sizing: border-box;
    width: 100%;
    overflow-x: auto;
    white-space: nowrap;
}
.bankers-filter-toolbar::-webkit-scrollbar {
    height: 4px;
}
.bankers-filter-toolbar::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}

.filter-item-search {
    position: relative;
    flex: 2;
    min-width: 190px;
}
.filter-item-search .search-icon {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    width: 14px;
    height: 14px;
    color: #64748b;
    pointer-events: none;
}
.filter-item-search input {
    width: 100% !important;
    height: 38px !important;
    padding: 0 10px 0 32px !important;
    border: 1px solid #cbd5e1 !important;
    border-radius: 7px !important;
    font-size: 13px !important;
    color: #0f172a !important;
    background: #ffffff !important;
    font-weight: 500 !important;
    outline: none !important;
    box-sizing: border-box !important;
    transition: all 0.15s ease !important;
}
.filter-item-search input:focus {
    border-color: #0f172a !important;
    box-shadow: 0 0 0 2px rgba(15,23,42,0.08) !important;
}

.filter-item-select {
    flex: 1.2;
    min-width: 125px;
}
.filter-item-select select {
    width: 100% !important;
    height: 38px !important;
    padding: 0 8px !important;
    border: 1px solid #cbd5e1 !important;
    border-radius: 7px !important;
    font-size: 12.5px !important;
    color: #0f172a !important;
    background: #ffffff !important;
    font-weight: 600 !important;
    outline: none !important;
    cursor: pointer !important;
    box-sizing: border-box !important;
    transition: all 0.15s ease !important;
}
.filter-item-select select:focus {
    border-color: #0f172a !important;
    box-shadow: 0 0 0 2px rgba(15,23,42,0.08) !important;
}

.filter-item-number {
    flex: 0.8;
    min-width: 88px;
}
.filter-item-number input {
    width: 100% !important;
    height: 38px !important;
    padding: 0 8px !important;
    border: 1px solid #cbd5e1 !important;
    border-radius: 7px !important;
    font-size: 12.5px !important;
    color: #0f172a !important;
    background: #ffffff !important;
    font-weight: 500 !important;
    outline: none !important;
    box-sizing: border-box !important;
    transition: all 0.15s ease !important;
}
.filter-item-number input:focus {
    border-color: #0f172a !important;
    box-shadow: 0 0 0 2px rgba(15,23,42,0.08) !important;
}

.btn-filter-reset {
    height: 38px;
    padding: 0 12px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    color: #475569;
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
    flex-shrink: 0;
    transition: all 0.15s;
    box-sizing: border-box;
}
.btn-filter-reset:hover {
    background: #f1f5f9;
    color: #0f172a;
    border-color: #0f172a;
}

/* Executive Bank Brand Badge (No Raw Emojis) */
.bank-entry-link {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    text-decoration: none;
    padding: 5px 11px 5px 6px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 1px 2px rgba(0,0,0,0.02);
}
.bank-entry-link:hover {
    background: #ffffff;
    border-color: #0f172a;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
    transform: translateY(-1px);
}
.bank-avatar-box {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.12);
}
.bank-text-group {
    display: flex;
    flex-direction: column;
    text-align: left;
    line-height: 1.25;
}
.bank-main-name {
    font-size: 13px;
    font-weight: 700;
    color: #0f172a;
    letter-spacing: -0.01em;
}
.bank-sub-name {
    font-size: 10.5px;
    font-weight: 600;
    color: #64748b;
}
.bank-arrow-icon {
    width: 12px;
    height: 12px;
    color: #94a3b8;
    margin-left: 2px;
    transition: all 0.2s;
    flex-shrink: 0;
}
.bank-entry-link:hover .bank-arrow-icon {
    transform: translate(1px, -1px);
    color: #0f172a;
}

.data-table thead th {
    background: #0f172a !important;
    color: #ffffff !important;
    font-size: 11px !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.5px !important;
    padding: 12px 14px !important;
    border: none !important;
}

/* Animated Executive View Button */
.btn-bank-view {
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background: #0f172a;
    color: #ffffff !important;
    border: 1px solid #0f172a;
    border-radius: 7px;
    font-size: 11.5px;
    font-weight: 700;
    text-decoration: none !important;
    cursor: pointer;
    overflow: hidden;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.15);
    white-space: nowrap;
}

/* Shimmer Sweep Animation on Hover */
.btn-bank-view::before {
    content: '';
    position: absolute;
    top: 0;
    left: -120%;
    width: 60%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.28), transparent);
    transform: skewX(-25deg);
    transition: none;
}

.btn-bank-view:hover::before {
    left: 150%;
    transition: left 0.65s ease-in-out;
}

.btn-bank-view:hover {
    background: #1e293b;
    border-color: #334155;
    transform: translateY(-2px);
    box-shadow: 0 5px 14px rgba(15, 23, 42, 0.25);
}

.btn-bank-view:active {
    transform: translateY(0);
}

/* Eye Icon Animation */
.btn-bank-view .eye-icon-box {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.btn-bank-view:hover .eye-icon-box {
    transform: scale(1.25);
}

/* Micro Arrow Glide */
.btn-bank-view .view-micro-arrow {
    width: 11px;
    height: 11px;
    opacity: 0.65;
    margin-left: -2px;
    transition: all 0.2s ease;
}

.btn-bank-view:hover .view-micro-arrow {
    opacity: 1;
    transform: translate(2px, -2px);
    color: #38bdf8;
}

/* Secondary Action Icon Buttons (Bell, Edit) */
.btn-action-icon {
    padding: 6px 9px;
    background: #f8fafc;
    color: #0f172a;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    cursor: pointer;
    text-decoration: none;
}

.btn-action-icon:hover {
    background: #ffffff;
    border-color: #0f172a;
    transform: translateY(-1px);
    box-shadow: 0 3px 8px rgba(15, 23, 42, 0.1);
}

.pagination-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 0 0 0;
    border-top: 1px solid #e4e4e7;
    margin-top: 20px;
}
.pagination-btn {
    padding: 8px 16px;
    border: 1px solid #18181b;
    border-radius: 6px;
    background: #ffffff;
    color: #09090b;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.2s;
}
.pagination-btn:hover:not(.disabled) {
    background: #09090b;
    color: #ffffff;
}
.pagination-btn.disabled {
    opacity: 0.4;
    cursor: not-allowed;
    pointer-events: none;
}

/* Expandable Row Styles */
.expandable-row {
    cursor: pointer;
    transition: background-color 0.2s;
}
.expandable-row:hover {
    background-color: #f4f4f5 !important;
}
.expandable-row td {
    padding: 14px 16px !important;
    vertical-align: middle;
}
.details-row {
    display: none;
    background: #f4f4f5;
}
.details-row.open {
    display: table-row;
    animation: fadeInDown 0.3s ease-out;
}
.details-content {
    padding: 20px !important;
    border-top: 1px dashed #cbd5e1;
}
.details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}
.detail-item label {
    display: block;
    font-size: 11px;
    text-transform: uppercase;
    color: #71717a;
    letter-spacing: 0.5px;
    font-weight: 700;
}
@keyframes fadeInDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
.expand-icon {
    transition: transform 0.3s;
}
.expandable-row.open .expand-icon {
    transform: rotate(180deg);
}

/* Compact Bank Performance Intelligence Section */
.bank-analytics-compact-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 14px 16px;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    transition: all 0.3s ease;
}
.compact-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    padding-bottom: 10px;
    border-bottom: 1px solid #e2e8f0;
}
.compact-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 12px;
}
@media (max-width: 992px) {
    .compact-kpi-grid { grid-template-columns: repeat(2, 1fr); }
}
.kpi-mini-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 10px 14px;
    display: flex;
    align-items: center;
    gap: 12px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.02);
}
.kpi-mini-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: #0f172a;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.kpi-mini-val {
    font-size: 16.5px;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.15;
}
.kpi-mini-lbl {
    font-size: 11px;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    margin-top: 1px;
}
.kpi-mini-sub {
    font-size: 10.5px;
    font-weight: 600;
    color: #059669;
}
.compact-charts-grid {
    display: grid;
    grid-template-columns: 1.38fr 1fr;
    gap: 12px;
}
@media (max-width: 900px) {
    .compact-charts-grid { grid-template-columns: 1fr; }
}
.compact-chart-box {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 12px 14px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.02);
}
.compact-chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}
.compact-chart-title {
    font-size: 12.5px;
    font-weight: 700;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 6px;
}
.compact-chart-tag {
    font-size: 10px;
    font-weight: 700;
    color: #475569;
    background: #f1f5f9;
    padding: 2px 7px;
    border-radius: 4px;
}
</style>

<div id="view-bankers" class="view-container">
    <div class="card" style="border: 1px solid #e4e4e7; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); background:#ffffff;">
        
        <!-- Card Executive Header Bar -->
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e4e4e7; padding-bottom:18px; margin-bottom:20px; flex-wrap:wrap; gap:14px;">
            <div>
                <div style="display:flex; align-items:center; gap:12px;">
                    <h2 style="margin:0; font-size:22px; font-weight:800; color:#09090b; letter-spacing:-0.03em;">Bankers Directory</h2>
                    <span style="background:#09090b; color:#ffffff; font-size:12px; font-weight:700; padding:4px 12px; border-radius:20px;">
                        <?php echo $total_items; ?> Contacts
                    </span>
                </div>
                <p style="margin:4px 0 0 0; font-size:13px; color:#71717a; font-weight:500;">View, search, and filter external bank relationship managers across India</p>
            </div>
            
            <div style="display:flex; align-items:center; gap:10px;">
                <button type="button" class="btn" id="btn-toggle-analytics" onclick="toggleBankAnalytics()" style="background:#ffffff; color:#09090b; border:1px solid #cbd5e1; font-weight:700; font-size:13px; padding:9px 15px; border-radius:8px; display:inline-flex; align-items:center; gap:8px; cursor:pointer; transition:all 0.2s;" title="Toggle Bank Network Performance & Graphs">
                    <i data-lucide="bar-chart-3" style="width:16px; height:16px;"></i>
                    <span id="txt-toggle-analytics">Hide Analytics</span>
                    <i data-lucide="chevron-up" id="icon-toggle-analytics" style="width:14px; height:14px;"></i>
                </button>
                <?php if (($_SESSION['role'] ?? '') === 'Admin'): ?>
                <a href="add_banker.php" class="btn" style="background:#09090b; color:#ffffff; font-weight:700; font-size:13px; padding:9px 18px; border-radius:8px; display:inline-flex; align-items:center; gap:8px; text-decoration:none; box-shadow:0 2px 8px rgba(0,0,0,0.15); transition:all 0.2s;">
                    <i data-lucide="plus" style="width:16px; height:16px;"></i> Add Bank Contact
                </a>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Compact Bank Performance & Portfolio Analytics Panel -->
        <div id="bank-analytics-panel" class="bank-analytics-compact-card">
            <!-- Compact Panel Header Bar -->
            <div class="compact-section-header">
                <div style="display:flex; align-items:center; gap:8px;">
                    <div style="width:26px; height:26px; border-radius:6px; background:#0f172a; color:#ffffff; display:flex; align-items:center; justify-content:center;">
                        <i data-lucide="bar-chart-3" style="width:14px; height:14px;"></i>
                    </div>
                    <div>
                        <span style="font-size:13px; font-weight:800; color:#0f172a; letter-spacing:-0.2px;">Bank Network Performance & Lending Intelligence</span>
                        <span style="font-size:11.5px; color:#64748b; font-weight:500; margin-left:6px;">• Overall partner performance, volume velocity & portfolio distribution</span>
                    </div>
                </div>
                <div style="display:flex; align-items:center; gap:8px;">
                    <span style="font-size:11.5px; font-weight:700; color:#059669; background:#ecfdf5; border:1px solid #a7f3d0; padding:3px 9px; border-radius:6px; display:inline-flex; align-items:center; gap:5px;">
                        <span style="width:6px; height:6px; background:#10b981; border-radius:50%; display:inline-block;"></span> Live Performance Feed
                    </span>
                </div>
            </div>

            <!-- 4 Compact KPI Mini-Cards -->
            <div class="compact-kpi-grid">
                <div class="kpi-mini-card">
                    <div class="kpi-mini-icon">
                        <i data-lucide="landmark" style="width:18px; height:18px;"></i>
                    </div>
                    <div>
                        <div class="kpi-mini-val"><?= $net_banks_cnt ?> Banks</div>
                        <div class="kpi-mini-lbl">Active Partner Banks</div>
                        <div class="kpi-mini-sub">100% Operational Network</div>
                    </div>
                </div>
                <div class="kpi-mini-card">
                    <div class="kpi-mini-icon">
                        <i data-lucide="folder-check" style="width:18px; height:18px;"></i>
                    </div>
                    <div>
                        <div class="kpi-mini-val"><?= $net_files_cnt ?> Files</div>
                        <div class="kpi-mini-lbl">Total Files Logged</div>
                        <div class="kpi-mini-sub">Across All Lending Partners</div>
                    </div>
                </div>
                <div class="kpi-mini-card">
                    <div class="kpi-mini-icon">
                        <i data-lucide="trending-up" style="width:18px; height:18px;"></i>
                    </div>
                    <div>
                        <div class="kpi-mini-val"><?= formatINR_Compact($net_disb_total) ?></div>
                        <div class="kpi-mini-lbl">Total Capital Disbursed</div>
                        <div class="kpi-mini-sub">Approved & Funded Volume</div>
                    </div>
                </div>
                <div class="kpi-mini-card">
                    <div class="kpi-mini-icon">
                        <i data-lucide="receipt" style="width:18px; height:18px;"></i>
                    </div>
                    <div>
                        <div class="kpi-mini-val"><?= formatINR_Compact($net_inv_total) ?></div>
                        <div class="kpi-mini-lbl">Bank Commission Ledger</div>
                        <div class="kpi-mini-sub">Received: <?= formatINR_Compact($net_rec_total) ?></div>
                    </div>
                </div>
            </div>

            <!-- 2 Compact Responsive Graphs -->
            <div class="compact-charts-grid">
                <!-- Chart 1: Bar Chart -->
                <div class="compact-chart-box">
                    <div class="compact-chart-header">
                        <span class="compact-chart-title">
                            <i data-lucide="bar-chart-2" style="width:14px; height:14px; color:#0f172a;"></i>
                            Bank-wise Disbursed Loan Volume (₹ in Lakhs)
                        </span>
                        <span class="compact-chart-tag">Volume Velocity</span>
                    </div>
                    <div style="height: 165px; position: relative;">
                        <canvas id="compactBankVolumeChart"></canvas>
                    </div>
                </div>

                <!-- Chart 2: Donut Chart -->
                <div class="compact-chart-box">
                    <div class="compact-chart-header">
                        <span class="compact-chart-title">
                            <i data-lucide="pie-chart" style="width:14px; height:14px; color:#0f172a;"></i>
                            Lending Portfolio Share (%)
                        </span>
                        <span class="compact-chart-tag">Market Share</span>
                    </div>
                    <div style="height: 165px; position: relative;">
                        <canvas id="compactBankShareChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- One-Line Responsive Filter Toolbar -->
        <form method="GET" action="bankers_list.php" class="bankers-filter-toolbar" id="filter-form">
            <!-- 1. Search Keyword -->
            <div class="filter-item-search">
                <i data-lucide="search" class="search-icon"></i>
                <input type="text" name="search" placeholder="Search RM, Bank, City..." value="<?php echo htmlspecialchars($search); ?>" onkeydown="if(event.key==='Enter'){ event.preventDefault(); this.form.submit(); }">
            </div>

            <!-- 2. Bank Name -->
            <div class="filter-item-select">
                <select name="bank" id="filter_bank_name">
                    <option value="">All Banks</option>
                </select>
            </div>

            <!-- 3. Loan Category -->
            <div class="filter-item-select">
                <select name="category" onchange="this.form.submit()">
                    <option value="">All Categories</option>
                    <?php 
                    $opts = ['Home Loan', 'Personal Loan', 'Business Loan', 'Mortgage Loan', 'Education Loan', 'Vehicle Loan'];
                    foreach($opts as $opt): 
                    ?>
                        <option value="<?php echo htmlspecialchars($opt); ?>" <?php if($category_filter==$opt) echo 'selected'; ?>><?php echo htmlspecialchars($opt); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- 4. Bank Type -->
            <div class="filter-item-select">
                <select name="bank_type" id="filter_bank_type">
                    <option value="">All Types</option>
                </select>
            </div>

            <!-- 5. State -->
            <div class="filter-item-select">
                <select name="state" id="filter_state">
                    <option value="">All States</option>
                </select>
            </div>

            <!-- 6. City -->
            <div class="filter-item-select">
                <select name="city" id="filter_city">
                    <option value="">All Cities</option>
                </select>
            </div>

            <!-- 7. Loan Amount (₹) -->
            <div class="filter-item-number">
                <input type="number" name="amount" placeholder="Min ₹" value="<?php echo $amount_req > 0 ? $amount_req : ''; ?>" onchange="this.form.submit()" title="Filter by Minimum Loan Limit">
            </div>

            <!-- 8. Max ROI (%) -->
            <div class="filter-item-number">
                <input type="number" step="0.01" name="roi" placeholder="Max %" value="<?php echo $roi_filter > 0 ? $roi_filter : ''; ?>" onchange="this.form.submit()" title="Filter by Maximum ROI / Interest Rate">
            </div>

            <!-- Reset All Filters -->
            <?php if ($is_filtered): ?>
                <a href="bankers_list.php" class="btn-filter-reset" title="Reset all filters">
                    <i data-lucide="rotate-ccw" style="width:13px; height:13px;"></i> Reset
                </a>
            <?php endif; ?>

            <button type="submit" style="display:none;">Search</button>
        </form>

        <script src="assets/js/locations.js"></script>
        <script src="assets/js/banks_directory.js"></script>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // PHP selected values
            const selType = "<?php echo addslashes($bank_type_filter); ?>";
            const selBank = "<?php echo addslashes($bank_filter); ?>";
            const selState = "<?php echo addslashes($state_filter); ?>";
            const selCity = "<?php echo addslashes($city_filter); ?>";

            const savedSystemBanks = <?php echo json_encode($saved_system_banks); ?>;
            const savedTypes = <?php echo json_encode($unique_types); ?>;
            const savedStates = <?php echo json_encode($unique_states); ?>;
            const savedCities = <?php echo json_encode($unique_cities); ?>;
            const savedStateCityMap = <?php echo json_encode($saved_state_city_map); ?>;

            const typeSelect = document.getElementById('filter_bank_type');
            const bankSelect = document.getElementById('filter_bank_name');
            const stateSelect = document.getElementById('filter_state');
            const citySelect = document.getElementById('filter_city');

            // Populate Bank Types (ONLY System-Saved Types)
            savedTypes.forEach(t => {
                const opt = document.createElement('option');
                opt.value = t;
                opt.textContent = t;
                typeSelect.appendChild(opt);
            });
            if (selType) typeSelect.value = selType;

            // Populate Banks (ONLY System-Saved Banks)
            function updateBanks() {
                const selectedType = typeSelect.value;
                bankSelect.innerHTML = '<option value="">All Banks</option>';
                
                savedSystemBanks.forEach(b => {
                    // If a Bank Type filter is selected, check if this bank matches that category
                    if (selectedType && typeof BANK_DIRECTORY !== 'undefined' && BANK_DIRECTORY[selectedType]) {
                        const belongsToType = BANK_DIRECTORY[selectedType].some(dirBank => 
                            dirBank.toUpperCase() === b.toUpperCase() || b.toUpperCase().includes(dirBank.toUpperCase()) || dirBank.toUpperCase().includes(b.toUpperCase())
                        );
                        if (!belongsToType) return;
                    }

                    const opt = document.createElement('option');
                    opt.value = b;
                    opt.textContent = b;
                    bankSelect.appendChild(opt);
                });
            }
            
            // Initial bank pop
            updateBanks();
            if (selBank) bankSelect.value = selBank;

            // Populate States (ONLY System-Saved States)
            savedStates.forEach(s => {
                const opt = document.createElement('option');
                opt.value = s;
                opt.textContent = s;
                stateSelect.appendChild(opt);
            });
            if (selState) stateSelect.value = selState;

            // Populate Cities (ONLY AFTER State is Selected)
            function updateCities() {
                const s = stateSelect.value;
                if (s && savedStateCityMap[s]) {
                    citySelect.disabled = false;
                    citySelect.innerHTML = '<option value="">All Cities</option>';
                    savedStateCityMap[s].forEach(c => {
                        const opt = document.createElement('option');
                        opt.value = c;
                        opt.textContent = c;
                        citySelect.appendChild(opt);
                    });
                } else {
                    citySelect.disabled = true;
                    citySelect.innerHTML = '<option value="">Select State First</option>';
                }
            }
            
            updateCities();
            if (selCity && stateSelect.value) citySelect.value = selCity;
            
            // Bind changes AFTER initial population
            typeSelect.addEventListener('change', function() {
                updateBanks(); 
                document.getElementById('filter-form').submit();
            });
            bankSelect.addEventListener('change', function() {
                document.getElementById('filter-form').submit();
            });
            stateSelect.addEventListener('change', function() {
                updateCities();
                document.getElementById('filter-form').submit();
            });
            citySelect.addEventListener('change', function() {
                document.getElementById('filter-form').submit();
            });
        });
        </script>

        <!-- Bankers Data Table -->
        <div class="table-responsive">
            <table class="data-table" style="border:1px solid #e4e4e7;">
                <thead style="background:#09090b; color:#ffffff;">
                    <tr>
                        <th style="width:40px; color:#ffffff;"></th>
                        <th style="color:#ffffff;">Banker Name</th>
                        <th style="color:#ffffff;">Bank Name</th>
                        <th style="color:#ffffff; text-align:center;">Submitted Files</th>
                        <th style="color:#ffffff;">Disbursed Volume</th>
                        <th style="color:#ffffff;">Limits & ROI</th>
                        <th style="color:#ffffff;">Status</th>
                        <th style="text-align:right; color:#ffffff;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($paginated_bankers)): ?>
                        <tr><td colspan="8" class="text-center" style="padding:40px; color:#71717a;">No bank contacts found matching the selected criteria.</td></tr>
                    <?php else: ?>
                        <?php foreach ($paginated_bankers as $banker): 
                            $status = $banker['status'] ?? 'Active';
                            $bName = $banker['bank_name'] ?? '';

                            $b_logins = $db->query("SELECT COUNT(*), SUM(COALESCE(sanctioned_amount, 0)) FROM applicant_bank_logins WHERE bank_name LIKE " . $db->quote("%$bName%"))->fetch(PDO::FETCH_NUM);
                            $b_file_cnt = $b_logins[0] ?? 0;
                            $b_disb_vol = $b_logins[1] ?? 0;
                        ?>
                            <tr class="expandable-row" onclick="toggleRow('details-<?php echo $banker['id']; ?>', this)">
                                <td><i data-lucide="chevron-down" class="expand-icon" style="color:#71717a; width:18px; height:18px;"></i></td>
                                <td>
                                    <strong style="color:#09090b; font-size:14px;"><?php echo htmlspecialchars($banker['full_name']); ?></strong>
                                    <br><span style="font-size: 12px; color: #71717a; font-weight:500;"><?php echo htmlspecialchars($banker['designation']); ?></span>
                                </td>
                                <td>
                                    <?php echo render_bank_logo_badge($bName); ?>
                                </td>
                                <td style="text-align:center;">
                                    <span style="font-size:12px; font-weight:700; background:#09090b; color:#ffffff; padding:3px 10px; border-radius:12px; display:inline-block;"><?php echo $b_file_cnt; ?> Files</span>
                                </td>
                                <td style="font-weight:700; color:#09090b; font-size:13px;">
                                    ₹ <?php echo number_format($b_disb_vol); ?>
                                </td>
                                <td>
                                    <div style="font-size:12px; font-weight:600; color:#09090b;">
                                        ₹<?php echo number_format((float)($banker['min_loan_limit'] ?? 0)); ?> - ₹<?php echo number_format((float)($banker['max_loan_limit'] ?? 0)); ?>
                                    </div>
                                    <?php if(!empty($banker['min_roi']) || !empty($banker['max_roi'])): ?>
                                    <div style="font-size:11px; font-weight:700; color:#15803d; margin-top:2px;">
                                        ROI: <?php echo ((float)($banker['min_roi'] ?? 0)) . '% - ' . ((float)($banker['max_roi'] ?? 0)) . '%'; ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($status === 'Active'): ?>
                                        <span style="background:#09090b; color:#ffffff; font-size:11px; font-weight:700; padding:3px 10px; border-radius:12px; display:inline-block;">ACTIVE</span>
                                    <?php else: ?>
                                        <span style="background:#fef2f2; color:#ef4444; border:1px solid #fca5a5; font-size:11px; font-weight:700; padding:3px 10px; border-radius:12px; display:inline-block;">INACTIVE</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:right;" onclick="event.stopPropagation();">
                                    <div style="display:flex; gap: 6px; justify-content:flex-end; align-items:center;">
                                        <button class="btn-action-icon" title="Set Reminder" onclick="event.stopPropagation(); openReminderModal('Banker', <?php echo $banker['id']; ?>, '<?php echo addslashes(htmlspecialchars($banker['full_name'])); ?>')">
                                            <i data-lucide="bell" style="width:14px;height:14px;"></i>
                                        </button>
                                        <a href="view_bank.php?bank_name=<?php echo urlencode($bName); ?>" class="btn-bank-view" title="View Bank 360° Portal" onclick="event.stopPropagation();">
                                            <span class="eye-icon-box">
                                                <i data-lucide="eye" style="width:13px;height:13px;"></i>
                                            </span>
                                            <span>View</span>
                                            <i data-lucide="arrow-up-right" class="view-micro-arrow"></i>
                                        </a>
                                        <?php if (($_SESSION['role'] ?? '') === 'Admin'): ?>
                                        <a href="edit_banker.php?id=<?php echo $banker['id']; ?>" class="btn-action-icon" title="Edit Contact" onclick="event.stopPropagation();">
                                            <i data-lucide="edit" style="width:14px;height:14px;"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <tr id="details-<?php echo $banker['id']; ?>" class="details-row">
                                <td colspan="8" class="details-content">
                                    <div class="details-grid">
                                        <div class="detail-item">
                                            <label>Email Address</label>
                                            <div class="value"><?php echo htmlspecialchars($banker['official_email'] ?: 'N/A'); ?></div>
                                        </div>
                                        <div class="detail-item">
                                            <label>Location</label>
                                            <div class="value"><?php echo htmlspecialchars(($banker['city'] ?? 'N/A') . ', ' . ($banker['state'] ?? 'N/A')); ?></div>
                                        </div>
                                        <div class="detail-item">
                                            <label>IFSC Code</label>
                                            <div class="value" style="text-transform:uppercase; font-family:monospace;"><?php echo htmlspecialchars($banker['ifsc_code'] ?? 'N/A'); ?></div>
                                        </div>
                                        <div class="detail-item">
                                            <label>Loan Categories</label>
                                            <div style="display:flex; flex-wrap:wrap; gap:4px; margin-top:4px;">
                                                <?php 
                                                $cats = explode(',', $banker['loan_category'] ?? '');
                                                $has_cat = false;
                                                foreach($cats as $c) {
                                                    $c = trim($c);
                                                    if($c) {
                                                        echo "<span style='background:#09090b; color:#ffffff; font-size:11px; padding:3px 8px; border-radius:4px; font-weight:600;'>".htmlspecialchars($c)."</span>";
                                                        $has_cat = true;
                                                    }
                                                }
                                                if(!$has_cat) echo "<span style='color:#71717a; font-size:13px;'>Not Specified</span>";
                                                ?>
                                            </div>
                                        </div>
                                        <div class="detail-item">
                                            <label>Coverage Type</label>
                                            <div class="value"><?php echo htmlspecialchars($banker['coverage_type'] ?: 'Not Specified'); ?></div>
                                        </div>
                                        <div class="detail-item">
                                            <label>Serviceable Area</label>
                                            <div class="value"><?php echo htmlspecialchars($banker['coverage_details'] ?: 'N/A'); ?></div>
                                        </div>
                                        <div class="detail-item">
                                            <label>Interest Rate (ROI)</label>
                                            <div class="value" style="font-weight:700; color:#15803d;">
                                                <?php 
                                                $mnR = (float)($banker['min_roi'] ?? 0);
                                                $mxR = (float)($banker['max_roi'] ?? 0);
                                                if($mnR > 0 && $mxR > 0) echo $mnR . "% - " . $mxR . "% p.a.";
                                                elseif($mnR > 0) echo "From " . $mnR . "% p.a.";
                                                elseif($mxR > 0) echo "Up to " . $mxR . "% p.a.";
                                                else echo "N/A";
                                                ?>
                                            </div>
                                        </div>
                                        <div class="detail-item" style="grid-column: 1 / -1;">
                                            <label>Branch Street Address</label>
                                            <div class="value" style="font-size:13px; color:#475569;"><?php echo nl2br(htmlspecialchars($banker['address'] ?: 'N/A')); ?></div>
                                        </div>
                                        <div class="detail-item" style="grid-column: 1 / -1; margin-top:10px; padding-top:14px; border-top:1px dashed #e4e4e7; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                                            <div style="font-size:12px; font-weight:600; color:#09090b; display:flex; align-items:center; gap:6px;">
                                                <i data-lucide="info" style="width:15px; height:15px; color:#71717a;"></i>
                                                View all submitted loan files, payout claims, and performance metrics for <strong><?php echo htmlspecialchars($bName); ?></strong>:
                                            </div>
                                            <a href="view_bank.php?bank_name=<?php echo urlencode($bName); ?>" class="btn btn-sm" style="background:#09090b; color:#ffffff; font-weight:700; font-size:12px; padding:7px 14px; border-radius:6px; text-decoration:none; display:inline-flex; align-items:center; gap:6px;" onclick="event.stopPropagation();">
                                                <i data-lucide="landmark" style="width:14px; height:14px;"></i> Open <?php echo htmlspecialchars($bName); ?> 360° Portal &rarr;
                                            </a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if($total_pages > 1): ?>
        <div class="pagination-controls">
            <?php 
                $q = $_GET; 
            ?>
            
            <?php $q['page'] = $page - 1; ?>
            <a href="?<?php echo http_build_query($q); ?>" class="pagination-btn <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                &laquo; Previous
            </a>
            
            <div style="font-size: 13px; color: #71717a; font-weight:600;">
                Page <strong style="color:#09090b;"><?php echo $page; ?></strong> of <strong style="color:#09090b;"><?php echo $total_pages; ?></strong>
            </div>
            
            <?php $q['page'] = $page + 1; ?>
            <a href="?<?php echo http_build_query($q); ?>" class="pagination-btn <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                Next &raquo;
            </a>
        </div>
        <?php endif; ?>
        
    </div>
</div>

<?php require_once 'footer.php'; ?>

<script>
// Compact Bank Charts Initialization
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Chart === 'undefined') return;

    // 1. Compact Bank Volume Bar Chart
    const barEl = document.getElementById('compactBankVolumeChart');
    if (barEl) {
        new Chart(barEl.getContext('2d'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($chart_bar_labels) ?>,
                datasets: [{
                    label: 'Disbursed (₹ Lakhs)',
                    data: <?= json_encode($chart_bar_vols) ?>,
                    backgroundColor: '#0f172a',
                    hoverBackgroundColor: '#0284c7',
                    borderRadius: 4,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                return ' Capital Funded: ₹ ' + ctx.raw.toLocaleString('en-IN') + ' Lakhs';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f1f5f9' },
                        ticks: {
                            callback: function(v) { return '₹' + v + 'L'; },
                            font: { size: 10, family: 'Inter' }
                        }
                    },
                    x: {
                        grid: { display: false },
                        ticks: {
                            maxRotation: 25,
                            minRotation: 0,
                            font: { size: 10, family: 'Inter' }
                        }
                    }
                }
            }
        });
    }

    // 2. Compact Portfolio Share Donut Chart
    const donutEl = document.getElementById('compactBankShareChart');
    if (donutEl) {
        new Chart(donutEl.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($chart_donut_labels) ?>,
                datasets: [{
                    data: <?= json_encode($chart_donut_vols) ?>,
                    backgroundColor: [
                        '#0f172a',
                        '#0284c7',
                        '#059669',
                        '#d97706',
                        '#7c3aed',
                        '#e11d48',
                        '#475569',
                        '#94a3b8'
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            boxWidth: 10,
                            padding: 6,
                            font: { size: 10, family: 'Inter', weight: 600 }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                return ' ' + ctx.label + ': ₹' + ctx.raw.toLocaleString('en-IN') + ' L';
                            }
                        }
                    }
                },
                cutout: '66%'
            }
        });
    }
});

// Toggle Bank Analytics Panel
function toggleBankAnalytics() {
    const panel = document.getElementById('bank-analytics-panel');
    const txt = document.getElementById('txt-toggle-analytics');
    const icon = document.getElementById('icon-toggle-analytics');
    if (!panel) return;

    const isHidden = (panel.style.display === 'none');
    if (isHidden) {
        panel.style.display = 'block';
        if (txt) txt.textContent = 'Hide Analytics';
        if (icon) icon.setAttribute('data-lucide', 'chevron-up');
        localStorage.setItem('bankers_analytics_collapsed', '0');
    } else {
        panel.style.display = 'none';
        if (txt) txt.textContent = 'Show Analytics';
        if (icon) icon.setAttribute('data-lucide', 'chevron-down');
        localStorage.setItem('bankers_analytics_collapsed', '1');
    }
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

// Restore saved collapse preference
document.addEventListener('DOMContentLoaded', function() {
    if (localStorage.getItem('bankers_analytics_collapsed') === '1') {
        const panel = document.getElementById('bank-analytics-panel');
        const txt = document.getElementById('txt-toggle-analytics');
        const icon = document.getElementById('icon-toggle-analytics');
        if (panel) panel.style.display = 'none';
        if (txt) txt.textContent = 'Show Analytics';
        if (icon) icon.setAttribute('data-lucide', 'chevron-down');
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }
});

function toggleRow(detailsId, rowElement) {
    const detailsRow = document.getElementById(detailsId);
    
    // Check if currently open
    const isOpen = rowElement.classList.contains('open');
    
    // Close all other rows first (optional, for accordion behavior)
    document.querySelectorAll('.expandable-row').forEach(el => el.classList.remove('open'));
    document.querySelectorAll('.details-row').forEach(el => el.classList.remove('open'));
    
    // Toggle the clicked row
    if (!isOpen) {
        rowElement.classList.add('open');
        detailsRow.classList.add('open');
    }
}
</script>
