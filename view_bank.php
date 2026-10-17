<?php
require_once 'config.php';
require_role(['Admin', 'RM']);

$requested_bank = trim($_GET['bank_name'] ?? '');
$requested_id   = intval($_GET['id'] ?? 0);

// If an ID was passed, lookup the bank name
if (empty($requested_bank) && $requested_id > 0) {
    $b_stmt = $db->prepare("SELECT bank_name FROM bankers WHERE id = ?");
    $b_stmt->execute([$requested_id]);
    $requested_bank = $b_stmt->fetchColumn();
}

// Fetch all available unique bank names in system for dropdown selector
$all_banks_stmt = $db->query("
    SELECT DISTINCT bank_name FROM (
        SELECT bank_name FROM bankers WHERE bank_name IS NOT NULL AND bank_name != ''
        UNION
        SELECT bank_name FROM applicant_bank_logins WHERE bank_name IS NOT NULL AND bank_name != ''
        UNION
        SELECT bank_name FROM bank_payout_invoices WHERE bank_name IS NOT NULL AND bank_name != ''
        UNION
        SELECT sanction_bank as bank_name FROM applicants WHERE sanction_bank IS NOT NULL AND sanction_bank != ''
        UNION
        SELECT disbursing_bank as bank_name FROM applicants WHERE disbursing_bank IS NOT NULL AND disbursing_bank != ''
    ) WHERE bank_name != '' AND bank_name != 'OTHER_CUSTOM' AND bank_name != 'Other (Manual Entry)' ORDER BY bank_name ASC
");
$all_bank_names = array_values(array_unique(array_filter($all_banks_stmt->fetchAll(PDO::FETCH_COLUMN))));

// Determine mode: All Banks Network Dashboard vs Individual Bank 360°
$is_all_banks_view = (empty($requested_bank) || strtolower($requested_bank) === 'all');

// If no individual bank is requested, redirect to Bankers Directory which now houses the consolidated network analytics
if ($is_all_banks_view) {
    header("Location: bankers_list.php");
    exit;
}

$current_bank = $requested_bank;

// Currency Formatter Helper
function formatINR_Bank($n) {
    $n = floatval($n);
    if ($n >= 10000000) return '₹' . number_format($n / 10000000, 2) . ' Cr';
    if ($n >= 100000)   return '₹' . number_format($n / 100000, 2) . ' L';
    if ($n >= 1000)     return '₹' . number_format($n / 1000, 1) . 'K';
    return '₹' . number_format($n, 0);
}

// =====================================================================
// INDIVIDUAL BANK 360° PORTAL
// =====================================================================
$page_title = 'Bank Intelligence 360°: ' . htmlspecialchars($current_bank);
$page_subtitle = 'Comprehensive 360-degree performance metrics, submitted loan files, bank RMs & payout invoices for ' . htmlspecialchars($current_bank);

    // 1. Registered Bankers for this Bank
    $b_list_stmt = $db->prepare("SELECT * FROM bankers WHERE bank_name LIKE ? ORDER BY created_at DESC");
    $b_list_stmt->execute(["%$current_bank%"]);
    $bankers_list = $b_list_stmt->fetchAll(PDO::FETCH_ASSOC);

    $is_bank_active = true;
    $bank_status_txt = 'Active';
    $bank_dsa_code = '';
    $bank_drive_link = '';
    $bank_undertaking_doc = '';
    $bank_official_email_val = '';

    foreach ($bankers_list as $b_rec) {
        if (!empty($b_rec['dsa_code'])) $bank_dsa_code = $b_rec['dsa_code'];
        if (!empty($b_rec['dsa_documents_drive_link'])) $bank_drive_link = $b_rec['dsa_documents_drive_link'];
        if (!empty($b_rec['dsa_undertaking_file'])) $bank_undertaking_doc = $b_rec['dsa_undertaking_file'];
        if (!empty($b_rec['bank_official_email'])) $bank_official_email_val = $b_rec['bank_official_email'];
        if (isset($b_rec['status']) && (strtolower($b_rec['status']) === 'inactive' || strtolower($b_rec['status']) === 'deactivated')) {
            $is_bank_active = false;
            $bank_status_txt = 'Inactive';
        }
    }

    // 2. Submitted Loan Files to this Bank (applicants + applicant_bank_logins)
    $logins_stmt = $db->prepare("
        SELECT 
            COALESCE(l.id, a.id) as record_id,
            a.id as applicant_id,
            a.customer_name,
            a.mobile,
            a.email,
            a.loan_type,
            a.loan_amount_requested,
            COALESCE(a.disbursed_amount, a.sanctioned_amount, l.sanctioned_amount, 0) as sanctioned_amount,
            COALESCE(l.status, a.overall_status, 'File Submitted') as status,
            a.assigned_to,
            COALESCE(l.login_ref_no, a.sanction_ref_no, '') as login_ref_no,
            COALESCE(l.login_date, a.sanction_date, a.created_at) as login_date
        FROM applicants a
        LEFT JOIN applicant_bank_logins l ON l.applicant_id = a.id
        WHERE (a.sanction_bank LIKE ? OR a.disbursing_bank LIKE ? OR a.login_fee_bank LIKE ? OR l.bank_name LIKE ?)
        ORDER BY a.created_at DESC
    ");
    $logins_stmt->execute(["%$current_bank%", "%$current_bank%", "%$current_bank%", "%$current_bank%"]);
    $submitted_files = $logins_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Metrics for this bank
    $total_files = count($submitted_files);
    $sanctioned_count = 0;
    $total_disbursed_volume = 0;

    foreach ($submitted_files as $f) {
        $st = strtolower($f['status'] ?? '');
        if (in_array($st, ['sanctioned', 'disbursed', 'completed', 'phase 4', 'phase 5'])) {
            $sanctioned_count++;
            $disb_amt = floatval($f['sanctioned_amount'] ?? 0);
            if ($disb_amt <= 0) $disb_amt = floatval($f['loan_amount_requested'] ?? 0);
            $total_disbursed_volume += $disb_amt;
        }
    }

    // 3. Bank Queries Raised
    $queries_stmt = $db->prepare("SELECT q.*, a.customer_name FROM bank_queries q LEFT JOIN applicants a ON a.id = q.applicant_id WHERE q.bank_name LIKE ? ORDER BY q.created_at DESC");
    $queries_stmt->execute(["%$current_bank%"]);
    $queries_list = $queries_stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Bank Payout Invoices
    $payout_stmt = $db->prepare("SELECT * FROM bank_payout_invoices WHERE bank_name LIKE ? ORDER BY created_at DESC");
    $payout_stmt->execute(["%$current_bank%"]);
    $invoices_list = $payout_stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_invoiced = 0;
    $total_received = 0;
    foreach ($invoices_list as $inv) {
        $total_invoiced += floatval($inv['net_invoice_amount'] ?? 0);
        if (strtolower($inv['status'] ?? '') === 'received') {
            $total_received += floatval($inv['received_amount'] ?? $inv['net_invoice_amount'] ?? 0);
        }
    }
    $pending_payout = max(0, $total_invoiced - $total_received);

$hide_main_header = true;
require_once 'header.php';
?>

<style>
    .bank-analytics-wrapper {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        width: 100%;
        color: #0f172a;
    }

    /* Executive Top Navigation Bar */
    .bank-top-nav-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 18px 22px;
        margin-bottom: 22px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    }
    .bank-title-box {
        display: flex;
        align-items: center;
        gap: 14px;
    }
    .bank-head-icon {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        background: #0f172a;
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        box-shadow: 0 4px 12px rgba(15,23,42,0.15);
    }

    /* Portal Selector Dropdown */
    .portal-selector-box {
        display: inline-flex;
        align-items: center;
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        height: 40px;
        padding: 0 12px;
        box-sizing: border-box;
        transition: all 0.15s;
    }
    .portal-selector-box:focus-within {
        border-color: #0f172a;
        box-shadow: 0 0 0 2px rgba(15,23,42,0.08);
    }
    .portal-selector-box select {
        border: none;
        outline: none;
        background: transparent;
        font-size: 13px;
        font-weight: 700;
        color: #0f172a;
        cursor: pointer;
        padding: 0 4px;
        box-shadow: none;
    }

    /* 4-Column Executive KPI Cards */
    .kpi-grid-four {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(215px, 1fr));
        gap: 16px;
        margin-bottom: 22px;
    }
    .kpi-stat-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 18px 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        display: flex;
        align-items: center;
        gap: 16px;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .kpi-stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0,0,0,0.05);
    }
    .kpi-stat-icon {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        color: #0f172a;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .kpi-stat-val {
        font-size: 20px;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.15;
        letter-spacing: -0.02em;
        margin-bottom: 2px;
    }
    .kpi-stat-lbl {
        font-size: 11px;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .kpi-stat-sub {
        font-size: 11px;
        color: #059669;
        font-weight: 600;
        margin-top: 2px;
    }

    /* Analytics Chart Grid */
    .chart-dual-grid {
        display: grid;
        grid-template-columns: 1.5fr 1fr;
        gap: 18px;
        margin-bottom: 24px;
    }
    @media (max-width: 1024px) {
        .chart-dual-grid {
            grid-template-columns: 1fr;
        }
    }
    .chart-container-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 20px 22px;
        box-shadow: 0 1px 4px rgba(0,0,0,0.02);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .chart-head-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 1px solid #f1f5f9;
    }
    .chart-head-title {
        font-size: 13.5px;
        font-weight: 800;
        color: #0f172a;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    /* All Banks Performance Table */
    .perf-table-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 22px;
        margin-bottom: 24px;
        box-shadow: 0 1px 4px rgba(0,0,0,0.02);
    }
    .perf-table-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
        flex-wrap: wrap;
        gap: 12px;
    }
    .perf-data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }
    .perf-data-table th {
        background: #0f172a;
        color: #ffffff;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 12px 14px;
        text-align: left;
    }
    .perf-data-table td {
        padding: 12px 14px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
        color: #0f172a;
    }
    .perf-data-table tbody tr:hover td {
        background: #f8fafc;
    }

    /* Animated View Button */
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
    .btn-bank-view .eye-icon-box {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    .btn-bank-view:hover .eye-icon-box {
        transform: scale(1.25);
    }
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

    /* Individual Bank View Tabs */
    .bank-tabs-bar {
        display: flex;
        gap: 6px;
        border-bottom: 1px solid #e2e8f0;
        margin-bottom: 20px;
        background: #ffffff;
        padding: 4px 8px 0 8px;
        border-radius: 10px 10px 0 0;
        overflow-x: auto;
    }
    .bank-tab-btn {
        padding: 11px 18px;
        font-size: 13px;
        font-weight: 600;
        color: #64748b;
        background: transparent;
        border: none;
        border-bottom: 2px solid transparent;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.15s;
        white-space: nowrap;
    }
    .bank-tab-btn:hover {
        color: #0f172a;
        background: #f8fafc;
    }
    .bank-tab-btn.active {
        color: #0f172a;
        border-bottom-color: #0f172a;
        background: #ffffff;
        font-weight: 700;
    }
    .bank-tab-content {
        display: none;
        background: #ffffff;
        border-radius: 12px;
        padding: 24px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }
    .bank-tab-content.active {
        display: block;
    }
</style>

<div class="view-container bank-analytics-wrapper">

    <!-- Top Executive Action Header & Portal Selector -->
    <div class="bank-top-nav-card">
        <div class="bank-title-box">
            <div class="bank-head-icon">
                <i data-lucide="<?= $is_all_banks_view ? 'bar-chart-3' : 'landmark' ?>" style="width:22px; height:22px;"></i>
            </div>
            <div>
                <h2 style="margin:0; font-size:20px; font-weight:800; color:#0f172a; letter-spacing:-0.03em;">
                    <?= $is_all_banks_view ? 'Bank Network Performance & Intelligence Hub' : 'Bank 360°: ' . htmlspecialchars($current_bank) ?>
                </h2>
                <p style="margin:3px 0 0 0; font-size:12.5px; color:#64748b; font-weight:500;">
                    <?= $is_all_banks_view ? 'Consolidated lending metrics, portfolio volume distribution & partner comparison across all Indian banks' : 'Detailed performance, submitted files, queries & commission invoices for ' . htmlspecialchars($current_bank) ?>
                </p>
            </div>
        </div>

        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
            <!-- Portal Selector Dropdown -->
            <div class="portal-selector-box">
                <i data-lucide="layers" style="width:14px; height:14px; color:#64748b; margin-right:6px; flex-shrink:0;"></i>
                <select id="select-bank-portal" onchange="window.location.href = (this.value === 'all') ? 'bankers_list.php' : ('view_bank.php?bank_name=' + encodeURIComponent(this.value))">
                    <option value="all" <?= $is_all_banks_view ? 'selected' : '' ?>>🌐 All Partner Banks (Directory & Analytics)</option>
                    <optgroup label="Individual Bank 360° Portals">
                        <?php foreach ($all_bank_names as $bName): ?>
                            <option value="<?= htmlspecialchars($bName) ?>" <?= (!$is_all_banks_view && $bName === $current_bank) ? 'selected' : '' ?>>
                                🏛️ <?= htmlspecialchars($bName) ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                </select>
            </div>

            <!-- Bankers Directory Button -->
            <a href="bankers_list.php" style="background:#ffffff; color:#0f172a; border:1px solid #cbd5e1; font-weight:700; font-size:12.5px; padding:9px 14px; border-radius:8px; text-decoration:none; display:inline-flex; align-items:center; gap:6px; height:40px; box-sizing:border-box;">
                <i data-lucide="arrow-left" style="width:14px; height:14px;"></i> Bankers Directory
            </a>

            <?php if (($_SESSION['role'] ?? '') === 'Admin'): ?>
            <button onclick="openRecordBankPayoutModal('<?= htmlspecialchars($is_all_banks_view ? ($all_bank_names[0] ?? 'HDFC Bank') : $current_bank) ?>')" class="btn" style="background:#0f172a; color:#ffffff; height:40px; padding:0 16px; border-radius:8px; font-size:12.5px; font-weight:700; border:none; cursor:pointer; display:inline-flex; align-items:center; gap:7px; box-shadow:0 2px 8px rgba(15,23,42,0.18);">
                <i data-lucide="plus-circle" style="width:15px; height:15px;"></i> Record Bank Payout
            </button>
            <?php endif; ?>
        </div>
    </div>

        <!-- ============================================================= -->
        <!-- INDIVIDUAL BANK 360° PORTAL                                   -->
        <!-- ============================================================= -->

        <!-- Hero Bank Header Card -->
        <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:22px 26px; margin-bottom:22px; box-shadow:0 1px 4px rgba(0,0,0,0.02);">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
                <div style="display:flex; align-items:center; gap:16px;">
                    <div style="width:54px; height:54px; background:#0f172a; color:#ffffff; border-radius:12px; display:flex; align-items:center; justify-content:center; box-shadow:0 4px 12px rgba(15,23,42,0.15);">
                        <i data-lucide="landmark" style="width:28px; height:28px;"></i>
                    </div>
                    <div>
                        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                            <h1 style="font-size:22px; font-weight:800; margin:0; color:#0f172a; letter-spacing:-0.4px;">
                                <?= htmlspecialchars($current_bank) ?>
                            </h1>
                            <?php if ($is_bank_active): ?>
                                <span style="background:#0f172a; color:#ffffff; font-size:11.5px; font-weight:700; padding:4px 12px; border-radius:20px; display:inline-flex; align-items:center; gap:6px;">
                                    <span style="width:6px; height:6px; background:#22c55e; border-radius:50%; display:inline-block;"></span> Active Lending Partner
                                </span>
                            <?php else: ?>
                                <span style="background:#fef2f2; color:#ef4444; border:1px solid #fca5a5; font-size:11.5px; font-weight:700; padding:4px 12px; border-radius:20px;">
                                    Deactivated
                                </span>
                            <?php endif; ?>

                            <?php if (!empty($bank_dsa_code)): ?>
                                <span style="background:#f8fafc; color:#0f172a; border:1px solid #cbd5e1; font-size:11.5px; font-weight:700; padding:4px 12px; border-radius:20px; display:inline-flex; align-items:center; gap:5px;">
                                    <i data-lucide="shield-check" style="width:13px; height:13px;"></i> DSA Code: <?= htmlspecialchars($bank_dsa_code) ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div style="display:flex; gap:14px; align-items:center; margin-top:8px; color:#64748b; font-size:12.5px; flex-wrap:wrap; font-weight:500;">
                            <span><i data-lucide="users" style="width:13px; height:13px; display:inline; color:#0f172a;"></i> Registered RMs: <strong style="color:#0f172a;"><?= count($bankers_list) ?> Contacts</strong></span>
                            <span>&bull;</span>
                            <span><i data-lucide="file-text" style="width:13px; height:13px; display:inline; color:#0f172a;"></i> Files Logged: <strong style="color:#0f172a;"><?= $total_files ?> Files</strong></span>
                            <?php if(!empty($bank_official_email_val)): ?>
                                <span>&bull;</span>
                                <span><i data-lucide="mail" style="width:13px; height:13px; display:inline; color:#0f172a;"></i> Official Email: <strong style="color:#0f172a;"><?= htmlspecialchars($bank_official_email_val) ?></strong></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                    <?php if (($_SESSION['role'] ?? '') === 'Admin'): ?>
                        <button onclick="toggleBankStatus('<?= addslashes(htmlspecialchars($current_bank)) ?>', '<?= $is_bank_active ? 'Active' : 'Inactive' ?>')" class="btn btn-sm" style="background:#ffffff; color:<?= $is_bank_active ? '#ef4444' : '#15803d' ?>; border:1px solid <?= $is_bank_active ? '#fca5a5' : '#86efac' ?>; font-weight:700; padding:8px 14px; border-radius:7px; font-size:12px; cursor:pointer;">
                            <i data-lucide="power" style="width:13px; height:13px;"></i> <?= $is_bank_active ? 'Deactivate Bank' : 'Reactivate Bank' ?>
                        </button>
                        <button onclick="openSendDsaDocsModal('<?= addslashes(htmlspecialchars($current_bank)) ?>', '<?= addslashes(htmlspecialchars($bank_drive_link)) ?>')" class="btn btn-sm" style="background:#0f172a; color:#ffffff; border:none; font-weight:700; padding:8px 14px; border-radius:7px; font-size:12px; cursor:pointer; display:inline-flex; align-items:center; gap:6px;">
                            <i data-lucide="send" style="width:13px; height:13px;"></i> Send DSA Docs
                        </button>
                    <?php endif; ?>
                    <a href="bankers_list.php" style="background:#f8fafc; color:#0f172a; border:1px solid #cbd5e1; font-weight:700; padding:8px 14px; border-radius:7px; font-size:12px; text-decoration:none; display:inline-flex; align-items:center; gap:6px;" title="Back to Bankers Directory & Analytics">
                        <i data-lucide="bar-chart-3" style="width:13px; height:13px;"></i> Bankers Directory & Analytics
                    </a>
                </div>
            </div>
        </div>

        <!-- 4 KPI Cards for This Bank -->
        <div class="kpi-grid-four">
            <div class="kpi-stat-card">
                <div class="kpi-stat-icon"><i data-lucide="layers" style="width:20px; height:20px;"></i></div>
                <div>
                    <div class="kpi-stat-val"><?= $total_files ?> Files</div>
                    <div class="kpi-stat-lbl">Total Files Logged</div>
                    <div class="kpi-stat-sub">Submitted to <?= htmlspecialchars($current_bank) ?></div>
                </div>
            </div>

            <div class="kpi-stat-card">
                <div class="kpi-stat-icon"><i data-lucide="check-circle" style="width:20px; height:20px;"></i></div>
                <div>
                    <div class="kpi-stat-val"><?= $sanctioned_count ?> Cases</div>
                    <div class="kpi-stat-lbl">Sanctioned / Approved</div>
                    <div class="kpi-stat-sub"><?= $total_files > 0 ? round(($sanctioned_count / $total_files) * 100, 1) : 0 ?>% Sanction Rate</div>
                </div>
            </div>

            <div class="kpi-stat-card">
                <div class="kpi-stat-icon"><i data-lucide="trending-up" style="width:20px; height:20px;"></i></div>
                <div>
                    <div class="kpi-stat-val"><?= formatINR_Bank($total_disbursed_volume) ?></div>
                    <div class="kpi-stat-lbl">Disbursed Volume</div>
                    <div class="kpi-stat-sub">Capital Funded via Partner</div>
                </div>
            </div>

            <div class="kpi-stat-card">
                <div class="kpi-stat-icon"><i data-lucide="receipt" style="width:20px; height:20px;"></i></div>
                <div>
                    <div class="kpi-stat-val"><?= formatINR_Bank($total_received) ?></div>
                    <div class="kpi-stat-lbl">Commission Received</div>
                    <div class="kpi-stat-sub">Invoiced: <?= formatINR_Bank($total_invoiced) ?></div>
                </div>
            </div>
        </div>

        <!-- Tabbed Navigation Bar -->
        <div class="bank-tabs-bar">
            <button class="bank-tab-btn active" onclick="switchBankTab('tab-submitted-files', this)">
                <i data-lucide="file-text" style="width:15px; height:15px;"></i> Submitted Loan Files (<?= count($submitted_files) ?>)
            </button>
            <button class="bank-tab-btn" onclick="switchBankTab('tab-bankers', this)">
                <i data-lucide="users" style="width:15px; height:15px;"></i> Bank Relationship Managers (<?= count($bankers_list) ?>)
            </button>
            <button class="bank-tab-btn" onclick="switchBankTab('tab-queries', this)">
                <i data-lucide="help-circle" style="width:15px; height:15px;"></i> Bank Queries (<?= count($queries_list) ?>)
            </button>
            <button class="bank-tab-btn" onclick="switchBankTab('tab-invoices', this)">
                <i data-lucide="receipt" style="width:15px; height:15px;"></i> Bank Payout Invoices (<?= count($invoices_list) ?>)
            </button>
        </div>

        <!-- TAB 1: Submitted Loan Files -->
        <div id="tab-submitted-files" class="bank-tab-content active">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:12px;">
                <h3 style="font-size:15px; font-weight:800; margin:0; color:#0f172a;">Loan Applications Logged with <?= htmlspecialchars($current_bank) ?></h3>
                <input type="text" id="bank-search-files" onkeyup="filterBankFiles()" placeholder="Filter applicant name or phone..." style="padding:6px 12px; font-size:12.5px; border:1px solid #cbd5e1; border-radius:6px; width:250px;">
            </div>

            <?php if (empty($submitted_files)): ?>
                <div style="padding:40px; text-align:center; color:#64748b;">
                    <i data-lucide="inbox" style="width:36px; height:36px; color:#cbd5e1; margin-bottom:8px;"></i>
                    <br>No loan applications logged with <?= htmlspecialchars($current_bank) ?> yet.
                </div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="perf-data-table" id="bank-files-table">
                        <thead>
                            <tr>
                                <th>Applicant Details</th>
                                <th>Contact Info</th>
                                <th>Loan Type</th>
                                <th>Requested Amount</th>
                                <th>Sanctioned Amount</th>
                                <th>Bank Status</th>
                                <th>Assigned RM</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($submitted_files as $f): 
                                $st = $f['status'] ?? 'File Submitted';
                                $st_lower = strtolower($st);
                                $is_sanc = in_array($st_lower, ['sanctioned', 'disbursed', 'completed', 'phase 4', 'phase 5']);
                            ?>
                                <tr>
                                    <td>
                                        <div style="font-weight:700; color:#0f172a;"><?= htmlspecialchars($f['customer_name'] ?: 'Applicant #' . $f['applicant_id']) ?></div>
                                        <?php if (!empty($f['login_ref_no'])): ?><div style="font-size:11px; font-family:monospace; color:#64748b;">Ref: <?= htmlspecialchars($f['login_ref_no']) ?></div><?php endif; ?>
                                    </td>
                                    <td>
                                        <div><i data-lucide="phone" style="width:12px; height:12px; display:inline; color:#64748b;"></i> <?= htmlspecialchars($f['mobile'] ?? 'N/A') ?></div>
                                    </td>
                                    <td style="font-weight:600; color:#334155;"><?= htmlspecialchars($f['loan_type'] ?? 'Loan') ?></td>
                                    <td style="font-weight:700; color:#0f172a;">
                                        <?= !empty($f['loan_amount_requested']) ? formatINR_Bank($f['loan_amount_requested']) : '—' ?>
                                    </td>
                                    <td style="font-weight:800; color:<?= $is_sanc ? '#059669' : '#0f172a' ?>;">
                                        <?= !empty($f['sanctioned_amount']) ? formatINR_Bank($f['sanctioned_amount']) : '—' ?>
                                    </td>
                                    <td>
                                        <span style="padding:3px 9px; border-radius:12px; font-size:11px; font-weight:700; background:<?= $is_sanc ? '#dcfce7' : '#f1f5f9' ?>; color:<?= $is_sanc ? '#15803d' : '#475569' ?>;">
                                            <?= htmlspecialchars($st) ?>
                                        </span>
                                    </td>
                                    <td style="font-size:12px; font-weight:600; color:#475569;"><?= htmlspecialchars($f['assigned_to'] ?: 'Unassigned') ?></td>
                                    <td style="font-size:12px; color:#64748b;">
                                        <?= !empty($f['login_date']) ? date('d M Y', strtotime($f['login_date'])) : '—' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- TAB 2: Bank Relationship Managers -->
        <div id="tab-bankers" class="bank-tab-content">
            <h3 style="font-size:15px; font-weight:800; margin:0 0 16px 0; color:#0f172a;">Registered Bank Relationship Managers for <?= htmlspecialchars($current_bank) ?></h3>
            <?php if (empty($bankers_list)): ?>
                <div style="padding:40px; text-align:center; color:#64748b;">No bank contacts registered for <?= htmlspecialchars($current_bank) ?> yet.</div>
            <?php else: ?>
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap:16px;">
                    <?php foreach ($bankers_list as $b): ?>
                        <div style="background:#f8fafc; border-radius:10px; padding:18px; border:1px solid #e2e8f0; display:flex; flex-direction:column; justify-content:space-between;">
                            <div>
                                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                                    <div>
                                        <div style="font-size:15px; font-weight:800; color:#0f172a;"><?= htmlspecialchars($b['full_name']) ?></div>
                                        <div style="font-size:12px; color:#64748b; font-weight:600;"><?= htmlspecialchars($b['designation'] ?: 'Relationship Manager') ?></div>
                                    </div>
                                    <span style="background:#0f172a; color:#ffffff; font-size:10.5px; font-weight:700; padding:2px 8px; border-radius:4px;">
                                        <?= htmlspecialchars($b['bank_name']) ?>
                                    </span>
                                </div>
                                
                                <div style="margin-top:14px; font-size:12.5px; color:#334155; display:flex; flex-direction:column; gap:6px;">
                                    <div><i data-lucide="phone" style="width:13px; height:13px; display:inline;"></i> <strong><?= htmlspecialchars($b['contact_number']) ?></strong>
                                        <a href="https://wa.me/91<?= preg_replace('/[^0-9]/', '', $b['contact_number']) ?>" target="_blank" style="color:#059669; font-weight:700; margin-left:8px; text-decoration:none;">💬 WhatsApp</a>
                                    </div>
                                    <div><i data-lucide="mail" style="width:13px; height:13px; display:inline;"></i> <?= htmlspecialchars($b['official_email'] ?: 'N/A') ?></div>
                                    <div><i data-lucide="map-pin" style="width:13px; height:13px; display:inline;"></i> <?= htmlspecialchars(($b['city'] ?? '') . ', ' . ($b['state'] ?? '')) ?></div>
                                    <div style="font-size:11.5px; color:#64748b; margin-top:4px;">
                                        Limits: <?= formatINR_Bank($b['min_loan_limit'] ?? 0) ?> - <?= formatINR_Bank($b['max_loan_limit'] ?? 0) ?>
                                        <?php if (!empty($b['min_roi']) || !empty($b['max_roi'])): ?>
                                            &bull; <span style="color:#059669; font-weight:700;">ROI: <?= floatval($b['min_roi'] ?? 0) ?>% - <?= floatval($b['max_roi'] ?? 0) ?>%</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- TAB 3: Bank Queries -->
        <div id="tab-queries" class="bank-tab-content">
            <h3 style="font-size:15px; font-weight:800; margin:0 0 16px 0; color:#0f172a;">Queries Raised by <?= htmlspecialchars($current_bank) ?></h3>
            <?php if (empty($queries_list)): ?>
                <div style="padding:40px; text-align:center; color:#64748b;">No queries raised by <?= htmlspecialchars($current_bank) ?>.</div>
            <?php else: ?>
                <table class="perf-data-table">
                    <thead>
                        <tr>
                            <th>Applicant</th>
                            <th>Query Category</th>
                            <th>Description</th>
                            <th>Date Raised</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($queries_list as $q): 
                            $st = strtolower($q['status'] ?? 'open');
                            $badge = $st === 'resolved' ? 'background:#dcfce7; color:#15803d;' : 'background:#fef3c7; color:#b45309;';
                        ?>
                            <tr>
                                <td style="font-weight:700;"><?= htmlspecialchars($q['customer_name'] ?: 'Applicant #' . $q['applicant_id']) ?></td>
                                <td style="font-weight:600; color:#0f172a;"><?= htmlspecialchars($q['query_type'] ?: 'Document') ?></td>
                                <td><?= htmlspecialchars($q['query_description']) ?></td>
                                <td style="font-size:12px; color:#64748b;"><?= htmlspecialchars($q['raised_date'] ?: date('d M Y', strtotime($q['created_at']))) ?></td>
                                <td><span style="padding:3px 8px; border-radius:12px; font-size:11px; font-weight:700; <?= $badge ?>"><?= htmlspecialchars($q['status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- TAB 4: Bank Payout Invoices -->
        <div id="tab-invoices" class="bank-tab-content">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h3 style="font-size:15px; font-weight:800; margin:0; color:#0f172a;">Payout Invoices & Commission Claims</h3>
                <?php if (($_SESSION['role'] ?? '') === 'Admin'): ?>
                <button onclick="openRecordBankPayoutModal('<?= addslashes(htmlspecialchars($current_bank)) ?>')" class="btn btn-sm" style="background:#0f172a; color:#ffffff; font-weight:700; border:none; padding:7px 14px; border-radius:6px; font-size:12px; cursor:pointer;">
                    <i data-lucide="plus" style="width:13px; height:13px;"></i> Record Payout
                </button>
                <?php endif; ?>
            </div>

            <?php if (empty($invoices_list)): ?>
                <div style="padding:40px; text-align:center; color:#64748b;">No payout invoices logged for <?= htmlspecialchars($current_bank) ?> yet.</div>
            <?php else: ?>
                <table class="perf-data-table">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Disbursed Vol</th>
                            <th>Net Invoice</th>
                            <th>Received Date</th>
                            <th>Bank UTR / Ref</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices_list as $inv): 
                            $st = strtolower($inv['status'] ?? 'pending');
                            $badge = $st === 'received' ? 'background:#dcfce7; color:#15803d;' : 'background:#fef3c7; color:#b45309;';
                        ?>
                            <tr>
                                <td style="font-weight:700; font-family:monospace;"><?= htmlspecialchars($inv['invoice_number'] ?: ('INV-' . $inv['id'])) ?></td>
                                <td><?= formatINR_Bank($inv['disbursed_amount'] ?? 0) ?></td>
                                <td style="font-weight:800; color:#0f172a;"><?= formatINR_Bank($inv['net_invoice_amount'] ?? 0) ?></td>
                                <td style="font-size:12px; color:#64748b;"><?= !empty($inv['received_date']) ? date('d M Y', strtotime($inv['received_date'])) : '—' ?></td>
                                <td style="font-family:monospace; font-size:12px;"><?= htmlspecialchars($inv['bank_utr'] ?? '—') ?></td>
                                <td><span style="padding:3px 8px; border-radius:12px; font-size:11px; font-weight:700; <?= $badge ?>"><?= htmlspecialchars(strtoupper($inv['status'])) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>


</div>

<script>
// Tab Switching for Individual Bank 360 View
function switchBankTab(tabId, btn) {
    document.querySelectorAll('.bank-tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.bank-tab-btn').forEach(el => el.classList.remove('active'));
    
    document.getElementById(tabId)?.classList.add('active');
    btn?.classList.add('active');
}

// Auto activate tab if hash is present
document.addEventListener('DOMContentLoaded', function() {
    if (window.location.hash) {
        const hashTab = window.location.hash.replace('#', '');
        const targetBtn = document.querySelector(`[onclick*="${hashTab}"]`);
        if (targetBtn) {
            switchBankTab(hashTab, targetBtn);
        }
    }
});

// Filter Table for All Banks Master View
function openRecordBankPayoutModal(bankName) {
    if (typeof Swal === 'undefined') return;
    Swal.fire({
        title: `Record Bank Payout: ${bankName}`,
        html: `
            <div style="text-align:left; font-size:13px;">
                <label style="font-weight:600; display:block; margin-bottom:4px;">Received Payout Amount (₹)*</label>
                <input type="number" id="bank-received-amt" class="swal2-input" style="width:100%; margin:0 0 12px 0;" placeholder="e.g. 50000">

                <label style="font-weight:600; display:block; margin-bottom:4px;">Disbursed Amount (₹)</label>
                <input type="number" id="bank-disbursed-amt" class="swal2-input" style="width:100%; margin:0 0 12px 0;" placeholder="Optional">

                <label style="font-weight:600; display:block; margin-bottom:4px;">Bank UTR / Transaction Ref No.*</label>
                <input type="text" id="bank-utr" class="swal2-input" style="width:100%; margin:0 0 12px 0;" placeholder="e.g. HDFC123456789">

                <label style="font-weight:600; display:block; margin-bottom:4px;">Received Date</label>
                <input type="date" id="bank-date" class="swal2-input" style="width:100%; margin:0 0 12px 0;" value="${new Date().toISOString().split('T')[0]}">

                <label style="font-weight:600; display:block; margin-bottom:4px;">Notes / Remarks</label>
                <textarea id="bank-notes" class="swal2-textarea" style="width:100%; margin:0;" placeholder="Optional remarks..."></textarea>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Record Bank Payout',
        confirmButtonColor: '#0f172a',
        cancelButtonColor: '#64748b',
        focusConfirm: false,
        preConfirm: () => {
            const rAmt = document.getElementById('bank-received-amt').value;
            const dAmt = document.getElementById('bank-disbursed-amt').value;
            const utr  = document.getElementById('bank-utr').value;
            const bdate = document.getElementById('bank-date').value;
            const notes = document.getElementById('bank-notes').value;

            if (!rAmt || parseFloat(rAmt) <= 0) {
                Swal.showValidationMessage('Please enter valid received payout amount.');
                return false;
            }
            if (!utr) {
                Swal.showValidationMessage('Please enter Bank UTR reference number.');
                return false;
            }

            return { rAmt, dAmt, utr, bdate, notes };
        }
    }).then(async (result) => {
        if (result.isConfirmed) {
            const data = result.value;
            const fd = new FormData();
            fd.append('bank_name', bankName);
            fd.append('disbursed_amount', data.dAmt || 0);
            fd.append('received_amount', data.rAmt);
            fd.append('bank_utr', data.utr);
            fd.append('received_date', data.bdate);
            fd.append('notes', data.notes);

            try {
                const res = await fetch('api.php?api=record_banker_payout_invoice', { method: 'POST', body: fd });
                const json = await res.json();
                if (json.success) {
                    Swal.fire('Bank Payout Recorded!', json.message, 'success').then(() => window.location.reload());
                } else {
                    Swal.fire('Error', json.error || 'Failed to record bank payout', 'error');
                }
            } catch(e) {
                Swal.fire('Error', 'Network error while recording payout', 'error');
            }
        }
    });
}

function toggleBankStatus(bankName, currentStatus) {
    if (typeof Swal === 'undefined') return;
    const isAct = (currentStatus === 'Active');
    const newStatus = isAct ? 'Inactive' : 'Active';
    const actionTxt = isAct ? 'Deactivate' : 'Reactivate';

    Swal.fire({
        title: `${actionTxt} ${bankName}?`,
        html: `
            <div style="text-align:left; font-size:13px;">
                <p>Are you sure you want to <strong>${actionTxt.toLowerCase()}</strong> <strong>${bankName}</strong>?</p>
                <label style="font-weight:600; display:block; margin:12px 0 4px 0;">Reason / Remarks:</label>
                <textarea id="deactivation-reason" class="swal2-textarea" style="width:100%; margin:0; font-size:13px;" placeholder="Optional remarks..."></textarea>
            </div>
        `,
        icon: isAct ? 'warning' : 'question',
        showCancelButton: true,
        confirmButtonText: `Yes, ${actionTxt}`,
        confirmButtonColor: isAct ? '#ef4444' : '#15803d',
        cancelButtonColor: '#64748b',
        preConfirm: () => document.getElementById('deactivation-reason').value
    }).then(async (result) => {
        if (result.isConfirmed) {
            const reason = result.value;
            const fd = new FormData();
            fd.append('bank_name', bankName);
            fd.append('status', newStatus);
            fd.append('reason', reason);

            try {
                const res = await fetch('api.php?api=toggle_banker_status', { method: 'POST', body: fd });
                const json = await res.json();
                if (json.success) {
                    Swal.fire('Status Updated!', json.message, 'success').then(() => window.location.reload());
                } else {
                    Swal.fire('Error', json.error || 'Failed to update status', 'error');
                }
            } catch(e) {
                Swal.fire('Error', 'Network error while updating status', 'error');
            }
        }
    });
}

function openSendDsaDocsModal(bankName, defaultDriveLink) {
    if (typeof Swal === 'undefined') return;
    Swal.fire({
        title: `Send DSA Onboarding Docs to ${bankName}`,
        html: `
            <div style="text-align:left; font-size:13px;">
                <label style="font-weight:600; display:block; margin:10px 0 4px 0;">Company Google Drive Folder Link:*</label>
                <input type="url" id="dsa-drive-link-input" class="swal2-input" style="width:100%; margin:0 0 12px 0; font-size:13px;" value="${defaultDriveLink || ''}" placeholder="https://drive.google.com/drive/folders/...">

                <label style="font-weight:600; display:block; margin:10px 0 4px 0;">Additional Notes / Remarks:</label>
                <textarea id="dsa-mail-notes" class="swal2-textarea" style="width:100%; margin:0; font-size:13px;" placeholder="Optional notes..."></textarea>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Send Onboarding Docs',
        confirmButtonColor: '#0f172a',
        cancelButtonColor: '#64748b',
        preConfirm: () => {
            const link = document.getElementById('dsa-drive-link-input').value;
            const notes = document.getElementById('dsa-mail-notes').value;
            if (!link) {
                Swal.showValidationMessage('Please provide Google Drive Folder link.');
                return false;
            }
            return { link, notes };
        }
    }).then(async (result) => {
        if (result.isConfirmed) {
            const data = result.value;
            const fd = new FormData();
            fd.append('bank_name', bankName);
            fd.append('dsa_documents_drive_link', data.link);
            fd.append('notes', data.notes);

            try {
                const res = await fetch('api.php?api=send_dsa_onboarding_mail', { method: 'POST', body: fd });
                const json = await res.json();
                if (json.success) {
                    Swal.fire('Email Dispatched!', json.message, 'success');
                } else {
                    Swal.fire('Error', json.error || 'Failed to send onboarding email', 'error');
                }
            } catch(e) {
                Swal.fire('Error', 'Network error while sending onboarding email', 'error');
            }
        }
    });
}
</script>

<?php require_once 'footer.php'; ?>
