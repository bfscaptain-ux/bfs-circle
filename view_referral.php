<?php
require_once 'config.php';
require_role(['Admin'], false);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $db->prepare("SELECT * FROM referrals WHERE id = ?");
$stmt->execute([$id]);
$referral = $stmt->fetch(PDO::FETCH_ASSOC);

$is_user_fallback = false;
if (!$referral) {
    $stmt_u = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt_u->execute([$id]);
    $user_prof = $stmt_u->fetch(PDO::FETCH_ASSOC);
    
    if ($user_prof) {
        $is_user_fallback = true;
        $referral = [
            'id' => $user_prof['id'],
            'referral_id' => $user_prof['username'],
            'referrer_type' => $user_prof['role'],
            'full_name' => $user_prof['name'],
            'mobile' => $user_prof['mobile'] ?? 'N/A',
            'email' => $user_prof['email'] ?? 'N/A',
            'city_state' => 'N/A',
            'commission_rate' => '1.5%',
            'status' => (!empty($user_prof) && isset($user_prof['is_active']) && $user_prof['is_active'] == 0) ? 'Inactive' : 'Active',
            'created_at' => $user_prof['created_at'] ?? date('Y-m-d H:i:s'),
        ];
    } else {
        echo "Referral partner or User not found.";
        exit;
    }
}

$page_title = 'Partner Intelligence 360°: ' . htmlspecialchars($referral['full_name']);
$page_subtitle = ' Comprehensive 360-degree performance metrics, sourced leads timeline & commission payout ledger';
$ref_code = $referral['referral_id'] ?? '';
$is_partner_active = (strtolower($referral['status'] ?? 'active') === 'active');

// Calculate Sourced Leads (leads + applicants)
$leads_stmt = $db->prepare("
    SELECT 'lead' as record_type, id, lead_name as customer_name, mobile, email, requirement as loan_type, 
           loan_amount, stage as status, created_at, assigned_to
    FROM leads 
    WHERE added_by = ? OR added_by = ?
    UNION ALL
    SELECT 'applicant' as record_type, id, customer_name, mobile, email, loan_type, 
           loan_amount_requested as loan_amount, overall_status as status, created_at, assigned_to
    FROM applicants 
    WHERE referral_id = ? OR added_by = ?
    ORDER BY created_at DESC
");
$leads_stmt->execute([$ref_code, $referral['full_name'], $ref_code, $ref_code]);
$sourced_leads = $leads_stmt->fetchAll(PDO::FETCH_ASSOC);

$total_sourced = count($sourced_leads);
$converted_count = 0;
$total_disbursed_volume = 0;

foreach ($sourced_leads as $item) {
    $st = strtolower($item['status'] ?? '');
    if (in_array($st, ['converted', 'disbursed', 'completed', 'phase 4', 'phase 5'])) {
        $converted_count++;
        $total_disbursed_volume += floatval($item['loan_amount'] ?? 0);
    }
}

// Calculate Commission
$rate_str = $referral['commission_rate'] ?? '1.5%';
$rate_num = floatval(preg_replace('/[^0-9.]/', '', $rate_str));
if ($rate_num <= 0) $rate_num = 1.5;

$total_commission_earned = round(($total_disbursed_volume * $rate_num) / 100, 2);

// Fetch Paid Payouts Ledger
$payout_stmt = $db->prepare("SELECT *, COALESCE(amount, net_payable) as amount, COALESCE(utr_ref, transaction_ref) as utr_ref, COALESCE(payment_date, paid_on) as payment_date FROM payout_distributions WHERE referral_id = ? OR recipient_id = ? ORDER BY created_at DESC");
$payout_stmt->execute([$ref_code, $ref_code]);
$payouts_list = $payout_stmt->fetchAll(PDO::FETCH_ASSOC);

$total_paid = 0;
foreach ($payouts_list as $p) {
    if (strtolower($p['status'] ?? '') === 'paid' || empty($p['status'])) {
        $total_paid += floatval($p['amount'] ?? 0);
    }
}

$pending_payout = max(0, $total_commission_earned - $total_paid);

// Activity Feed
$act_stmt = $db->prepare("SELECT * FROM activities WHERE description LIKE ? OR target_user = ? ORDER BY created_at DESC LIMIT 30");
$act_stmt->execute(["%$ref_code%", $ref_code]);
$activities_list = $act_stmt->fetchAll(PDO::FETCH_ASSOC);

$hide_main_header = true;
require_once 'header.php';
?>

<style>
    .partner-360-wrapper {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        width: 100%;
        color: #0f172a;
    }
    
    .partner-header-card {
        background: #ffffff;
        border-radius: 12px;
        padding: 24px 28px;
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
        border: 1px solid #e2e8f0;
        flex-wrap: wrap;
        gap: 16px;
    }
    
    .partner-avatar {
        width: 56px;
        height: 56px;
        background: #0f172a;
        color: #ffffff;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        font-weight: 700;
        letter-spacing: -0.5px;
    }

    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }

    .kpi-card {
        background: #ffffff;
        border-radius: 10px;
        padding: 20px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        transition: border-color 0.2s;
    }
    .kpi-card:hover {
        border-color: #cbd5e1;
    }

    .kpi-card .kpi-title {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        color: #64748b;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .kpi-card .kpi-value {
        font-size: 26px;
        font-weight: 800;
        color: #0f172a;
        font-family: 'Outfit', sans-serif;
        letter-spacing: -0.5px;
    }

    .partner-tabs-bar {
        display: flex;
        gap: 6px;
        border-bottom: 1px solid #e2e8f0;
        margin-bottom: 20px;
        background: #ffffff;
        padding: 4px 8px 0 8px;
        border-radius: 10px 10px 0 0;
        overflow-x: auto;
    }

    .partner-tab-btn {
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

    .partner-tab-btn:hover {
        color: #0f172a;
        background: #f8fafc;
    }

    .partner-tab-btn.active {
        color: #0f172a;
        border-bottom-color: #0f172a;
        background: #ffffff;
        font-weight: 700;
    }

    .partner-tab-content {
        display: none;
        background: #ffffff;
        border-radius: 12px;
        padding: 24px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }

    .partner-tab-content.active {
        display: block;
    }

    .table-custom {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }

    .table-custom th {
        background: #0f172a;
        color: #ffffff;
        padding: 12px 16px;
        text-align: left;
        font-weight: 600;
        font-size: 11.5px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .table-custom td {
        padding: 13px 16px;
        border-bottom: 1px solid #f1f5f9;
        color: #334155;
    }

    .table-custom tr:hover {
        background: #f8fafc;
    }

    .btn-dark-slate {
        background: #0f172a;
        color: #ffffff;
        border: none;
        padding: 8px 16px;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: background 0.15s;
    }
    .btn-dark-slate:hover {
        background: #1e293b;
        color: #ffffff;
    }
</style>

<div class="view-container partner-360-wrapper">

    <!-- Top Navigation Action Bar -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:12px;">
        <div style="display:flex; align-items:center; gap:12px; white-space:nowrap;">
            <a href="referrals_list.php" class="btn btn-secondary" style="background:#fff; border:1px solid #cbd5e1; color:#0f172a; font-weight:600; font-size:13px; padding:8px 14px; white-space:nowrap; display:inline-flex; align-items:center; gap:6px; height:38px; box-sizing:border-box;"><i data-lucide="arrow-left" style="width:15px;"></i> Partner Directory</a>
            <h2 style="font-size:18px; font-weight:700; margin:0; color:#0f172a; white-space:nowrap;">Partner Intelligence 360° &bull; <?php echo htmlspecialchars($referral['full_name']); ?></h2>
        </div>
        <div style="display:flex; gap:10px; align-items:center; white-space:nowrap;">
            <?php if (($_SESSION['role'] ?? '') === 'Admin'): ?>
            <button onclick="openRecordPayoutModal('<?php echo htmlspecialchars($ref_code); ?>')" class="btn-dark-slate" style="height:38px; padding:0 16px; white-space:nowrap; display:inline-flex; align-items:center; gap:6px; box-sizing:border-box;">
                <i data-lucide="plus-circle" style="width:15px;"></i> Record Payout
            </button>
            <?php if (empty($is_user_fallback)): ?>
            <button type="button" 
                    id="profile-toggle-btn"
                    onclick="togglePartnerStatus(<?php echo $referral['id']; ?>, '<?php echo addslashes(htmlspecialchars($referral['full_name'])); ?>', '<?php echo $is_partner_active ? 'Active' : 'Inactive'; ?>')" 
                    class="btn" 
                    style="height:38px; padding:0 14px; font-weight:700; font-size:13px; white-space:nowrap; display:inline-flex; align-items:center; gap:6px; box-sizing:border-box; border:1.5px solid <?php echo $is_partner_active ? '#fca5a5' : '#86efac'; ?>; background:<?php echo $is_partner_active ? '#fef2f2' : '#ecfdf5'; ?>; color:<?php echo $is_partner_active ? '#dc2626' : '#059669'; ?>; cursor:pointer; transition:all 0.2s;">
                <i data-lucide="<?php echo $is_partner_active ? 'user-x' : 'user-check'; ?>" id="profile-toggle-icon" style="width:15px;"></i> 
                <span id="profile-toggle-text"><?php echo $is_partner_active ? 'Deactivate Partner' : 'Activate Partner'; ?></span>
            </button>
            <a href="edit_referral.php?id=<?php echo $referral['id']; ?>" class="btn btn-secondary" style="background:#f1f5f9; color:#475569; border:1px solid #e2e8f0; font-weight:600; font-size:13px; padding:0 14px; height:38px; white-space:nowrap; display:inline-flex; align-items:center; gap:6px; box-sizing:border-box;">
                <i data-lucide="edit" style="width:15px;"></i> Edit Partner
            </a>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Hero Profile Header Card -->
    <div class="partner-header-card">
        <div style="display:flex; align-items:center; gap:18px;">
            <div class="partner-avatar">
                <?php echo strtoupper(substr($referral['full_name'] ?? 'P', 0, 1)); ?>
            </div>
            <div>
                <div style="display:flex; align-items:center; gap:10px;">
                    <h1 style="font-size:22px; font-weight:800; margin:0; color:#0f172a; letter-spacing:-0.4px;"><?php echo htmlspecialchars($referral['full_name']); ?></h1>
                    <span style="background:#f1f5f9; border:1px solid #cbd5e1; color:#334155; font-size:11px; font-weight:700; padding:2px 8px; border-radius:4px; font-family:monospace;"><?php echo htmlspecialchars($ref_code); ?></span>
                </div>
                <div style="display:flex; gap:16px; align-items:center; margin-top:6px; color:#64748b; font-size:13px;">
                    <span><i data-lucide="briefcase" style="width:13px; display:inline; vertical-align:middle;"></i> <strong><?php echo htmlspecialchars($referral['referrer_type'] ?? 'Partner'); ?></strong></span>
                    <span>&bull;</span>
                    <span><i data-lucide="phone" style="width:13px; display:inline; vertical-align:middle;"></i> <?php echo htmlspecialchars($referral['mobile'] ?? ''); ?></span>
                    <a href="https://wa.me/91<?php echo preg_replace('/[^0-9]/', '', $referral['mobile']); ?>?text=<?php echo urlencode('Hello ' . $referral['full_name'] . ', reaching out regarding BFS partner operations.'); ?>" target="_blank" style="color:#059669; text-decoration:none; font-weight:600; display:inline-flex; align-items:center; gap:4px;" title="Send WhatsApp Message">
                        <i data-lucide="message-square" style="width:13px;"></i> WhatsApp
                    </a>
                    <span>&bull;</span>
                    <span><i data-lucide="map-pin" style="width:13px; display:inline; vertical-align:middle;"></i> <?php echo htmlspecialchars($referral['city_state'] ?? 'India'); ?></span>
                </div>
            </div>
        </div>
        <div style="text-align:right;">
            <div style="font-size:11px; color:#64748b; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">Commission Structure</div>
            <div style="font-size:20px; font-weight:800; color:#0f172a; font-family:'Outfit', sans-serif;"><?php echo htmlspecialchars($referral['commission_rate'] ?? '1.5%'); ?></div>
            <div style="margin-top:5px;">
                <span id="profile-status-badge" style="font-size:11px; font-weight:800; padding:4px 12px; border-radius:20px; border:1.5px solid <?php echo $is_partner_active ? '#10b981' : '#ef4444'; ?>; background:<?php echo $is_partner_active ? '#ecfdf5' : '#fef2f2'; ?>; color:<?php echo $is_partner_active ? '#065f46' : '#991b1b'; ?>; display:inline-flex; align-items:center; gap:6px; letter-spacing:0.02em;">
                    <span id="profile-status-dot" style="width:7px; height:7px; border-radius:50%; background:<?php echo $is_partner_active ? '#10b981' : '#ef4444'; ?>; display:inline-block; box-shadow:0 0 6px <?php echo $is_partner_active ? '#10b981' : '#ef4444'; ?>;"></span>
                    <span id="profile-status-label"><?php echo $is_partner_active ? 'ACTIVE PARTNER' : 'DEACTIVATED'; ?></span>
                </span>
            </div>
        </div>
    </div>

    <!-- 4 High Impact KPI Cards -->
    <div class="kpi-grid">
        <div class="kpi-card" style="border-left:4px solid #0f172a;">
            <div class="kpi-title"><i data-lucide="bar-chart-2" style="width:14px;"></i> Total Sourced Leads</div>
            <div class="kpi-value"><?php echo $total_sourced; ?> <span style="font-size:13px; color:#64748b; font-weight:500;">Leads</span></div>
            <div style="font-size:11px; color:#64748b; margin-top:4px;">Total client leads referred</div>
        </div>
        <div class="kpi-card" style="border-left:4px solid #059669;">
            <div class="kpi-title"><i data-lucide="award" style="width:14px; color:#059669;"></i> Disbursed / Passed Loans</div>
            <div class="kpi-value" style="color:#059669;"><?php echo $converted_count; ?> <span style="font-size:13px; color:#64748b; font-weight:500;">Cases</span></div>
            <div style="font-size:11px; color:#059669; font-weight:600; margin-top:4px;"><?php echo $total_sourced > 0 ? round(($converted_count / $total_sourced) * 100, 1) : 0; ?>% Success Conversion</div>
        </div>
        <div class="kpi-card" style="border-left:4px solid #2563eb;">
            <div class="kpi-title"><i data-lucide="dollar-sign" style="width:14px; color:#2563eb;"></i> Disbursed Loan Volume</div>
            <div class="kpi-value" style="color:#2563eb;">₹ <?php echo number_format($total_disbursed_volume); ?></div>
            <div style="font-size:11px; color:#64748b; margin-top:4px;">Total funded loan amount</div>
        </div>
        <div class="kpi-card" style="border-left:4px solid #d97706;">
            <div class="kpi-title"><i data-lucide="credit-card" style="width:14px; color:#d97706;"></i> Net Payout Balance</div>
            <div class="kpi-value" style="color:#d97706;">₹ <?php echo number_format($pending_payout); ?> <span style="font-size:12px; color:#64748b; font-weight:500;">Pending</span></div>
            <div style="font-size:11.5px; color:#475569; font-weight:600; margin-top:4px;">
                Earned: ₹<?php echo number_format($total_commission_earned); ?> &bull; Paid: ₹<?php echo number_format($total_paid); ?>
            </div>
        </div>
    </div>

    <!-- 4 Tabs Navigation -->
    <div class="partner-tabs-bar">
        <button class="partner-tab-btn active" onclick="switchPartnerTab('tab-leads', this)">
            <i data-lucide="file-text" style="width:15px;"></i> Sourced Leads & Applications (<?php echo $total_sourced; ?>)
        </button>
        <button class="partner-tab-btn" onclick="switchPartnerTab('tab-payouts', this)">
            <i data-lucide="credit-card" style="width:15px;"></i> Commission & Payout Ledger (<?php echo count($payouts_list); ?>)
        </button>
        <button class="partner-tab-btn" onclick="switchPartnerTab('tab-activities', this)">
            <i data-lucide="activity" style="width:15px;"></i> Audit Activity Feed (<?php echo count($activities_list); ?>)
        </button>
        <button class="partner-tab-btn" onclick="switchPartnerTab('tab-bank-kyc', this)">
            <i data-lucide="landmark" style="width:15px;"></i> Banking & KYC Documents
        </button>
    </div>

    <!-- TAB 1: Sourced Leads & Applications -->
    <div id="tab-leads" class="partner-tab-content active">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:12px;">
            <div>
                <h3 style="font-size:15px; font-weight:700; margin:0; color:#0f172a;">Sourced Leads & Applications Directory</h3>
                <div style="font-size:12px; color:#64748b;">Complete list of leads and applications submitted by <?php echo htmlspecialchars($referral['full_name']); ?></div>
            </div>
            <input type="text" id="partner-search-leads" onkeyup="filterPartnerLeads()" placeholder="Filter by customer name or phone..." style="padding:6px 12px; font-size:12.5px; border:1px solid #cbd5e1; border-radius:6px; width:260px;">
        </div>

        <?php if (empty($sourced_leads)): ?>
            <div style="padding:40px; text-align:center; color:#64748b; font-weight:500;">
                <i data-lucide="inbox" style="width:36px; height:36px; color:#cbd5e1; margin-bottom:8px;"></i>
                <br>No leads or loan applications submitted by this partner yet.
            </div>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="table-custom" id="partner-leads-table">
                    <thead>
                        <tr>
                            <th>Customer Name</th>
                            <th>Contact Info</th>
                            <th>Loan Requirement</th>
                            <th>Requested Amount</th>
                            <th>Stage / Status</th>
                            <th>Assigned RM</th>
                            <th>Date Sourced</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sourced_leads as $l): 
                            $st = $l['status'] ?? 'New Lead';
                            $st_lower = strtolower($st);
                            $badge_bg = '#f1f5f9'; $badge_col = '#475569';
                            if (strpos($st_lower, 'new') !== false) { $badge_bg = '#eff6ff'; $badge_col = '#1d4ed8'; }
                            elseif (strpos($st_lower, 'convert') !== false || strpos($st_lower, 'disbursed') !== false || strpos($st_lower, 'completed') !== false) { $badge_bg = '#dcfce7'; $badge_col = '#15803d'; }
                            elseif (strpos($st_lower, 'reject') !== false || strpos($st_lower, 'lost') !== false) { $badge_bg = '#fee2e2'; $badge_col = '#b91c1c'; }
                            elseif (strpos($st_lower, 'interested') !== false || strpos($st_lower, 'schedule') !== false) { $badge_bg = '#fef3c7'; $badge_col = '#b45309'; }
                        ?>
                            <tr>
                                <td>
                                    <div style="font-weight:700; color:#0f172a;"><?php echo htmlspecialchars($l['customer_name']); ?></div>
                                    <span style="font-size:10.5px; color:#64748b; font-weight:600; text-transform:uppercase; letter-spacing:0.5px;"><?php echo htmlspecialchars($l['record_type']); ?></span>
                                </td>
                                <td>
                                    <div><i data-lucide="phone" style="width:12px; display:inline; color:#64748b;"></i> <?php echo htmlspecialchars($l['mobile'] ?? 'N/A'); ?></div>
                                    <?php if (!empty($l['email'])): ?><div style="font-size:11px; color:#64748b; margin-top:2px;"><?php echo htmlspecialchars($l['email']); ?></div><?php endif; ?>
                                </td>
                                <td style="font-weight:600; color:#334155;"><?php echo htmlspecialchars($l['loan_type'] ?? 'Loan'); ?></td>
                                <td style="font-weight:700; color:#0f172a;">
                                    <?php echo !empty($l['loan_amount']) ? '₹ ' . number_format(floatval($l['loan_amount'])) : '—'; ?>
                                </td>
                                <td>
                                    <span style="padding:4px 10px; border-radius:20px; font-size:11px; font-weight:700; display:inline-block; background:<?php echo $badge_bg; ?>; color:<?php echo $badge_col; ?>;">
                                        <?php echo htmlspecialchars($st); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-size:12px; font-weight:600; color:#475569;"><?php echo htmlspecialchars($l['assigned_to'] ?: 'Unassigned'); ?></div>
                                </td>
                                <td style="font-size:12px; color:#64748b;">
                                    <?php echo date('d M Y, h:i A', strtotime($l['created_at'])); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- TAB 2: Commission & Payout History Ledger -->
    <div id="tab-payouts" class="partner-tab-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:12px;">
            <div>
                <h3 style="font-size:15px; font-weight:700; margin:0; color:#0f172a;">Commission & Payout History Ledger</h3>
                <div style="font-size:12px; color:#64748b;">Complete financial tracking of commission payouts paid to this partner with UTR transaction references.</div>
            </div>
            <?php if (($_SESSION['role'] ?? '') === 'Admin'): ?>
            <button onclick="openRecordPayoutModal('<?php echo htmlspecialchars($ref_code); ?>')" class="btn-dark-slate" style="font-size:12px; padding:6px 14px;">
                <i data-lucide="plus" style="width:14px;"></i> Record Payment
            </button>
            <?php endif; ?>
        </div>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:16px; margin-bottom:20px; background:#f8fafc; padding:16px; border-radius:10px; border:1px solid #e2e8f0;">
            <div>
                <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;">Total Commission Earned</div>
                <div style="font-size:18px; font-weight:800; color:#0f172a; font-family:'Outfit', sans-serif;">₹ <?php echo number_format($total_commission_earned, 2); ?></div>
            </div>
            <div>
                <div style="font-size:11px; font-weight:700; color:#059669; text-transform:uppercase; letter-spacing:0.5px;">Total Payout Paid</div>
                <div style="font-size:18px; font-weight:800; color:#059669; font-family:'Outfit', sans-serif;">₹ <?php echo number_format($total_paid, 2); ?></div>
            </div>
            <div>
                <div style="font-size:11px; font-weight:700; color:#d97706; text-transform:uppercase; letter-spacing:0.5px;">Net Pending Payout</div>
                <div style="font-size:18px; font-weight:800; color:#d97706; font-family:'Outfit', sans-serif;">₹ <?php echo number_format($pending_payout, 2); ?></div>
            </div>
        </div>

        <?php if (empty($payouts_list)): ?>
            <div style="padding:40px; text-align:center; color:#64748b; font-weight:500;">
                <i data-lucide="credit-card" style="width:36px; height:36px; color:#cbd5e1; margin-bottom:8px;"></i>
                <br>No payout transactions logged for this partner yet. Click "+ Record Payout" to add a payment.
            </div>
        <?php else: ?>
            <table class="table-custom">
                <thead>
                    <tr>
                        <th>Date Paid</th>
                        <th>Amount (₹)</th>
                        <th>Payment Mode</th>
                        <th>UTR / Transaction Ref</th>
                        <th>Status</th>
                        <th>Remarks</th>
                        <th>Recorded By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payouts_list as $p): ?>
                        <tr>
                            <td style="font-weight:600;"><?php echo !empty($p['payment_date']) ? date('d M Y', strtotime($p['payment_date'])) : date('d M Y', strtotime($p['created_at'])); ?></td>
                            <td style="font-weight:800; color:#059669; font-size:14px;">₹ <?php echo number_format(floatval($p['amount']), 2); ?></td>
                            <td style="font-weight:600; color:#334155;"><?php echo htmlspecialchars($p['payment_mode'] ?: 'Bank Transfer'); ?></td>
                            <td style="font-family:monospace; font-weight:700; color:#0f172a;"><?php echo htmlspecialchars($p['utr_ref'] ?: '—'); ?></td>
                            <td><span style="background:#dcfce7; color:#15803d; font-size:11px; font-weight:700; padding:3px 8px; border-radius:12px;">Paid</span></td>
                            <td style="font-size:12px; color:#475569;"><?php echo htmlspecialchars($p['remarks'] ?: 'N/A'); ?></td>
                            <td style="font-size:11px; color:#64748b; font-weight:600;"><?php echo htmlspecialchars($p['created_by'] ?: 'Admin'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- TAB 3: Audit Activity Feed -->
    <div id="tab-activities" class="partner-tab-content">
        <h3 style="font-size:15px; font-weight:700; margin:0 0 16px 0; color:#0f172a;">Audit Activity Feed</h3>
        <?php if (empty($activities_list)): ?>
            <div style="padding:40px; text-align:center; color:#64748b;">No recent logged activity events recorded.</div>
        <?php else: ?>
            <div style="display:flex; flex-direction:column; gap:10px;">
                <?php foreach ($activities_list as $act): ?>
                    <div style="display:flex; gap:14px; align-items:center; padding:12px 16px; background:#f8fafc; border-radius:8px; border:1px solid #e2e8f0;">
                        <div style="background:#0f172a; color:#fff; width:28px; height:28px; border-radius:50%; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                            <i data-lucide="check" style="width:14px;"></i>
                        </div>
                        <div style="flex:1;">
                            <div style="font-weight:600; color:#0f172a; font-size:13px;"><?php echo htmlspecialchars($act['description']); ?></div>
                            <div style="font-size:11px; color:#64748b; margin-top:2px;">By: <strong><?php echo htmlspecialchars($act['username'] ?: 'System'); ?></strong> &bull; <?php echo date('d M Y, h:i A', strtotime($act['created_at'])); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- TAB 4: Banking & KYC Documents -->
    <div id="tab-bank-kyc" class="partner-tab-content">
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap:24px;">
            <div>
                <h3 style="font-size:15px; font-weight:700; margin:0 0 16px 0; color:#0f172a;">Banking Account Details</h3>
                <div style="display:flex; flex-direction:column; gap:14px; background:#f8fafc; padding:20px; border-radius:10px; border:1px solid #e2e8f0;">
                    <div>
                        <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;">Account Name & Bank</div>
                        <div style="font-size:14.5px; font-weight:700; color:#0f172a; margin-top:2px;"><?php echo htmlspecialchars($referral['account_name'] ?: 'N/A'); ?> &bull; <?php echo htmlspecialchars($referral['bank_name'] ?: 'N/A'); ?></div>
                    </div>
                    <div style="display:flex; gap:20px;">
                        <div style="flex:1;">
                            <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;">Account Number</div>
                            <div style="font-family:monospace; font-size:14.5px; font-weight:700; color:#0f172a; margin-top:2px;"><?php echo htmlspecialchars($referral['account_number'] ?: 'N/A'); ?></div>
                        </div>
                        <div style="flex:1;">
                            <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;">IFSC Code</div>
                            <div style="font-family:monospace; font-size:14.5px; font-weight:700; color:#0f172a; margin-top:2px;"><?php echo htmlspecialchars($referral['ifsc_code'] ?: 'N/A'); ?></div>
                        </div>
                    </div>
                    <div>
                        <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;">UPI ID</div>
                        <div style="font-size:14px; font-weight:700; color:#2563eb; margin-top:2px;"><?php echo htmlspecialchars($referral['upi_id'] ?: 'N/A'); ?></div>
                    </div>
                </div>
            </div>

            <div>
                <h3 style="font-size:15px; font-weight:700; margin:0 0 16px 0; color:#0f172a;">Uploaded KYC Documents</h3>
                <div style="display:flex; flex-direction:column; gap:12px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; background:#f8fafc; padding:14px 18px; border-radius:10px; border:1px solid #e2e8f0;">
                        <div>
                            <div style="font-weight:700; color:#0f172a; font-size:13px;">PAN Card</div>
                            <div style="font-family:monospace; font-size:13px; color:#64748b; font-weight:600;"><?php echo htmlspecialchars($referral['pan_number'] ?: 'N/A'); ?></div>
                        </div>
                        <?php if (!empty($referral['pan_document_path'])): ?>
                            <a href="<?php echo htmlspecialchars($referral['pan_document_path']); ?>" target="_blank" class="btn btn-secondary btn-sm" style="padding:6px 12px; font-size:12px;"><i data-lucide="eye" style="width:14px;"></i> View Document</a>
                        <?php else: ?>
                            <span style="font-size:11px; color:#94a3b8;">Not Uploaded</span>
                        <?php endif; ?>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center; background:#f8fafc; padding:14px 18px; border-radius:10px; border:1px solid #e2e8f0;">
                        <div>
                            <div style="font-weight:700; color:#0f172a; font-size:13px;">Aadhaar Card</div>
                            <div style="font-family:monospace; font-size:13px; color:#64748b; font-weight:600;"><?php echo htmlspecialchars($referral['aadhar_number'] ?: 'N/A'); ?></div>
                        </div>
                        <?php if (!empty($referral['aadhar_document_path'])): ?>
                            <a href="<?php echo htmlspecialchars($referral['aadhar_document_path']); ?>" target="_blank" class="btn btn-secondary btn-sm" style="padding:6px 12px; font-size:12px;"><i data-lucide="eye" style="width:14px;"></i> View Document</a>
                        <?php else: ?>
                            <span style="font-size:11px; color:#94a3b8;">Not Uploaded</span>
                        <?php endif; ?>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center; background:#f8fafc; padding:14px 18px; border-radius:10px; border:1px solid #e2e8f0;">
                        <div>
                            <div style="font-weight:700; color:#0f172a; font-size:13px;">Bank Proof (Cheque / Passbook)</div>
                            <div style="font-size:12px; color:#64748b;">Bank Account Verification Document</div>
                        </div>
                        <?php if (!empty($referral['bank_document_path'])): ?>
                            <a href="<?php echo htmlspecialchars($referral['bank_document_path']); ?>" target="_blank" class="btn btn-secondary btn-sm" style="padding:6px 12px; font-size:12px;"><i data-lucide="eye" style="width:14px;"></i> View Document</a>
                        <?php else: ?>
                            <span style="font-size:11px; color:#94a3b8;">Not Uploaded</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
function switchPartnerTab(tabId, btn) {
    document.querySelectorAll('.partner-tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.partner-tab-btn').forEach(el => el.classList.remove('active'));
    
    document.getElementById(tabId)?.classList.add('active');
    btn.classList.add('active');
}

function filterPartnerLeads() {
    const query = document.getElementById('partner-search-leads').value.toLowerCase();
    const rows = document.querySelectorAll('#partner-leads-table tbody tr');
    rows.forEach(r => {
        const text = r.innerText.toLowerCase();
        r.style.display = text.includes(query) ? '' : 'none';
    });
}

function openRecordPayoutModal(refCode) {
    Swal.fire({
        title: 'Record Partner Payout',
        html: `
            <div style="text-align:left; font-size:13px;">
                <label style="font-weight:600; display:block; margin-bottom:4px;">Payout Amount (₹)*</label>
                <input type="number" id="payout-amount" class="swal2-input" style="width:100%; margin:0 0 12px 0;" placeholder="e.g. 10000" min="1" step="any">
                
                <label style="font-weight:600; display:block; margin-bottom:4px;">Payment Mode*</label>
                <select id="payout-mode" class="swal2-input" style="width:100%; margin:0 0 12px 0;">
                    <option value="Bank Transfer">Bank Transfer (NEFT/RTGS/IMPS)</option>
                    <option value="UPI">UPI Payment</option>
                    <option value="Cheque">Cheque</option>
                    <option value="Cash">Cash</option>
                </select>

                <label style="font-weight:600; display:block; margin-bottom:4px;">UTR / Transaction Reference No.*</label>
                <input type="text" id="payout-utr" class="swal2-input" style="width:100%; margin:0 0 12px 0;" placeholder="e.g. UTR123456789">

                <label style="font-weight:600; display:block; margin-bottom:4px;">Payment Date</label>
                <input type="date" id="payout-date" class="swal2-input" style="width:100%; margin:0 0 12px 0;" value="${new Date().toISOString().split('T')[0]}">

                <label style="font-weight:600; display:block; margin-bottom:4px;">Remarks / Notes</label>
                <textarea id="payout-remarks" class="swal2-textarea" style="width:100%; margin:0;" placeholder="Optional payout remarks..."></textarea>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Record Payment',
        confirmButtonColor: '#0f172a',
        cancelButtonColor: '#64748b',
        focusConfirm: false,
        preConfirm: () => {
            const amount = document.getElementById('payout-amount').value;
            const mode = document.getElementById('payout-mode').value;
            const utr = document.getElementById('payout-utr').value;
            const pdate = document.getElementById('payout-date').value;
            const remarks = document.getElementById('payout-remarks').value;

            if (!amount || parseFloat(amount) <= 0) {
                Swal.showValidationMessage('Please enter a valid payout amount.');
                return false;
            }
            if (!utr) {
                Swal.showValidationMessage('Please enter UTR or Transaction reference number.');
                return false;
            }

            return { amount, mode, utr, pdate, remarks };
        }
    }).then(async (result) => {
        if (result.isConfirmed) {
            const data = result.value;
            const fd = new FormData();
            fd.append('referral_id', refCode);
            fd.append('amount', data.amount);
            fd.append('payment_mode', data.mode);
            fd.append('utr_ref', data.utr);
            fd.append('payment_date', data.pdate);
            fd.append('remarks', data.remarks);

            try {
                const res = await fetch('api.php?api=record_referral_payout', { method: 'POST', body: fd });
                const json = await res.json();
                if (json.success) {
                    Swal.fire('Payout Recorded!', json.message, 'success').then(() => window.location.reload());
                } else {
                    Swal.fire('Error', json.error || 'Failed to record payout', 'error');
                }
            } catch(e) {
                Swal.fire('Error', 'Network error while recording payout', 'error');
            }
        }
    });
}

async function togglePartnerStatus(id, name, currentStatus) {
    const isCurrentlyActive = (currentStatus.toLowerCase() === 'active');
    const actionWord = isCurrentlyActive ? 'Deactivate' : 'Activate';
    const actionColor = isCurrentlyActive ? '#dc2626' : '#10b981';

    const result = await Swal.fire({
        title: `${actionWord} Partner?`,
        html: isCurrentlyActive 
            ? `Are you sure you want to <b>Deactivate</b> partner <strong>${name}</strong>?<br><br><span style="font-size:13px; color:#dc2626; display:inline-block; background:#fef2f2; padding:8px 14px; border-radius:8px; border:1px solid #fecaca; font-weight:600;">🚫 This partner will NOT be able to log in to their dashboard.</span>`
            : `Are you sure you want to <b>Activate</b> partner <strong>${name}</strong>?<br><br><span style="font-size:13px; color:#059669; display:inline-block; background:#ecfdf5; padding:8px 14px; border-radius:8px; border:1px solid #a7f3d0; font-weight:600;">✓ Partner dashboard login access will be restored immediately.</span>`,
        icon: isCurrentlyActive ? 'warning' : 'question',
        showCancelButton: true,
        confirmButtonColor: actionColor,
        cancelButtonColor: '#64748b',
        confirmButtonText: `Yes, ${actionWord}`,
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
                title: `${actionWord}d!`,
                text: data.message,
                timer: 2000,
                showConfirmButton: false
            });

            // Dynamically update UI
            const newIsActive = (data.new_status === 'Active');
            const toggleBtn = document.getElementById('profile-toggle-btn');
            const statusBadge = document.getElementById('profile-status-badge');
            const statusDot = document.getElementById('profile-status-dot');
            const statusLabel = document.getElementById('profile-status-label');

            if (toggleBtn) {
                toggleBtn.style.borderColor = newIsActive ? '#fca5a5' : '#86efac';
                toggleBtn.style.background = newIsActive ? '#fef2f2' : '#ecfdf5';
                toggleBtn.style.color = newIsActive ? '#dc2626' : '#059669';
                toggleBtn.setAttribute('onclick', `togglePartnerStatus(${id}, '${name}', '${data.new_status}')`);
                toggleBtn.innerHTML = `<i data-lucide="${newIsActive ? 'user-x' : 'user-check'}" style="width:15px;"></i> <span>${newIsActive ? 'Deactivate Partner' : 'Activate Partner'}</span>`;
            }
            if (statusBadge) {
                statusBadge.style.borderColor = newIsActive ? '#10b981' : '#ef4444';
                statusBadge.style.background = newIsActive ? '#ecfdf5' : '#fef2f2';
                statusBadge.style.color = newIsActive ? '#065f46' : '#991b1b';
            }
            if (statusDot) {
                statusDot.style.background = newIsActive ? '#10b981' : '#ef4444';
                statusDot.style.boxShadow = `0 0 6px ${newIsActive ? '#10b981' : '#ef4444'}`;
            }
            if (statusLabel) {
                statusLabel.innerText = newIsActive ? 'ACTIVE PARTNER' : 'DEACTIVATED';
            }
            if (window.lucide) lucide.createIcons();
        } else {
            Swal.fire('Error', data.error || 'Failed to update partner status.', 'error');
        }
    } catch (err) {
        Swal.fire('Network Error', 'Unable to reach the server. Please check your connection.', 'error');
    }
}
</script>

<?php require_once 'footer.php'; ?>
