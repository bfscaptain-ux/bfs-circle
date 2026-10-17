<?php
require_once 'config.php';

require_role(['Admin'], false);

$target_user = $_GET['username'] ?? '';
if (!$target_user) {
    die("Employee username is required.");
}

// Fetch employee basic info
$stmt = $db->prepare("SELECT e.* FROM employees e JOIN users u ON e.user_id = u.id WHERE u.username = ?");
$stmt->execute([$target_user]);
$emp = $stmt->fetch();

if (!$emp) {
    die("Employee not found.");
}

// Calculate Performance Metrics
$stmt1 = $db->prepare("SELECT COUNT(*) FROM field_visits WHERE executive_name = ?");
$stmt1->execute([$target_user]);
$total_visits = $stmt1->fetchColumn();

$stmt2 = $db->prepare("
    SELECT COUNT(a.id) 
    FROM applicants a 
    LEFT JOIN referrals r ON a.referral_id = r.referral_id 
    WHERE a.added_by = ? OR r.assigned_rm = ?
");
$stmt2->execute([$target_user, $target_user]);
$total_leads = $stmt2->fetchColumn();

$stmt3 = $db->prepare("
    SELECT SUM(a.loan_amount_requested) 
    FROM applicants a 
    LEFT JOIN referrals r ON a.referral_id = r.referral_id 
    WHERE (a.added_by = ? OR r.assigned_rm = ?) AND a.overall_status = 'Completed'
");
$stmt3->execute([$target_user, $target_user]);
$total_disbursed = $stmt3->fetchColumn() ?: 0;

$comm_rate = (float)($emp['commission_rate'] ?? 1.0);
$estimated_commission = $total_disbursed * ($comm_rate / 100);

// Fetch Recent Leads
$stmt4 = $db->prepare("
    SELECT a.* 
    FROM applicants a 
    LEFT JOIN referrals r ON a.referral_id = r.referral_id 
    WHERE a.added_by = ? OR r.assigned_rm = ?
    ORDER BY a.created_at DESC 
    LIMIT 20
");
$stmt4->execute([$target_user, $target_user]);
$recent_leads = $stmt4->fetchAll();

// Fetch Recent Field Visits
$stmt5 = $db->prepare("
    SELECT * 
    FROM field_visits 
    WHERE executive_name = ?
    ORDER BY visit_date DESC 
    LIMIT 20
");
$stmt5->execute([$target_user]);
$recent_visits = $stmt5->fetchAll();

$current_page = 'employees_list.php';
require_once 'header.php';
?>

<div style="padding: 24px; max-width: 1200px; margin: 0 auto;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
        <div>
            <h1 style="font-size:24px; color:var(--text-dark); margin:0;">Performance Report</h1>
            <p style="color:var(--text-muted); margin:4px 0 0 0;">Employee: <strong><?php echo htmlspecialchars($emp['full_name']); ?></strong> (<?php echo htmlspecialchars($target_user); ?>)</p>
        </div>
        <a href="employees_list.php" class="btn btn-secondary"><i data-lucide="arrow-left"></i> Back to Directory</a>
    </div>

    <!-- Metrics Cards -->
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap:20px; margin-bottom:32px;">
        <div style="background:white; border-radius:12px; padding:24px; box-shadow:0 2px 4px rgba(0,0,0,0.05); border:1px solid #e2e8f0; display:flex; align-items:center; gap:20px;">
            <div style="width:50px; height:50px; border-radius:12px; background:#e0e7ff; color:#4f46e5; display:flex; align-items:center; justify-content:center; font-size:24px;">
                <i class="fas fa-map-marker-alt"></i>
            </div>
            <div>
                <p style="margin:0; font-size:13px; color:#64748b; font-weight:600; text-transform:uppercase;">Total Field Visits</p>
                <h2 style="margin:4px 0 0 0; font-size:28px; color:#0f172a;"><?php echo $total_visits; ?></h2>
            </div>
        </div>
        
        <div style="background:white; border-radius:12px; padding:24px; box-shadow:0 2px 4px rgba(0,0,0,0.05); border:1px solid #e2e8f0; display:flex; align-items:center; gap:20px;">
            <div style="width:50px; height:50px; border-radius:12px; background:#dcfce7; color:#16a34a; display:flex; align-items:center; justify-content:center; font-size:24px;">
                <i class="fas fa-users"></i>
            </div>
            <div>
                <p style="margin:0; font-size:13px; color:#64748b; font-weight:600; text-transform:uppercase;">Generated Leads</p>
                <h2 style="margin:4px 0 0 0; font-size:28px; color:#0f172a;"><?php echo $total_leads; ?></h2>
            </div>
        </div>

        <div style="background:linear-gradient(135deg, #FF7A00 0%, #E66A00 100%); border-radius:12px; padding:24px; box-shadow:0 4px 10px rgba(255,122,0,0.2); display:flex; align-items:center; gap:20px; color:white;">
            <div style="width:50px; height:50px; border-radius:12px; background:rgba(255,255,255,0.2); display:flex; align-items:center; justify-content:center; font-size:24px;">
                <i class="fas fa-wallet"></i>
            </div>
            <div>
                <p style="margin:0; font-size:13px; font-weight:600; text-transform:uppercase; color:rgba(255,255,255,0.8);">Estimated Commission</p>
                <h2 style="margin:4px 0 0 0; font-size:28px;">₹<?php echo number_format($estimated_commission, 2); ?></h2>
                <small style="opacity:0.8;">Based on <?php echo $comm_rate; ?>% of completed files (₹<?php echo number_format($total_disbursed); ?>)</small>
            </div>
        </div>
    </div>

    <!-- Leads Table -->
    <div style="background:white; border-radius:12px; box-shadow:0 2px 4px rgba(0,0,0,0.05); border:1px solid #e2e8f0; overflow:hidden;">
        <div style="padding:20px; border-bottom:1px solid #e2e8f0;">
            <h2 style="margin:0; font-size:18px; color:var(--text-dark);">Leads Generated via Network</h2>
        </div>
        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; text-align:left;">
                <thead>
                    <tr style="background:#f8fafc;">
                        <th style="padding:16px 20px; font-size:13px; color:#64748b; font-weight:600; text-transform:uppercase;">Loan ID</th>
                        <th style="padding:16px 20px; font-size:13px; color:#64748b; font-weight:600; text-transform:uppercase;">Customer</th>
                        <th style="padding:16px 20px; font-size:13px; color:#64748b; font-weight:600; text-transform:uppercase;">Loan Details</th>
                        <th style="padding:16px 20px; font-size:13px; color:#64748b; font-weight:600; text-transform:uppercase;">Status</th>
                        <th style="padding:16px 20px; font-size:13px; color:#64748b; font-weight:600; text-transform:uppercase;">Date Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_leads)): ?>
                        <tr><td colspan="5" style="text-align:center; padding:30px; color:#94a3b8;">No leads generated yet.</td></tr>
                    <?php else: ?>
                        <?php foreach($recent_leads as $lead): ?>
                            <tr style="border-bottom:1px solid #e2e8f0;">
                                <td style="padding:16px 20px; font-weight:500;"><?php echo htmlspecialchars($lead['loan_id']); ?></td>
                                <td style="padding:16px 20px;">
                                    <div style="font-weight:500;"><?php echo htmlspecialchars($lead['customer_name']); ?></div>
                                    <div style="font-size:12px; color:#64748b;"><?php echo htmlspecialchars($lead['mobile']); ?></div>
                                </td>
                                <td style="padding:16px 20px;">
                                    <div><?php echo htmlspecialchars($lead['loan_type']); ?></div>
                                    <div style="font-size:12px; font-weight:600; color:var(--primary);">₹<?php echo number_format($lead['loan_amount_requested']); ?></div>
                                </td>
                                <td style="padding:16px 20px;">
                                    <?php 
                                        $s = $lead['overall_status'];
                                        $bg = '#e2e8f0'; $col = '#475569';
                                        if($s === 'Completed') { $bg = '#dcfce7'; $col = '#166534'; }
                                        elseif($s === 'Rejected') { $bg = '#fee2e2'; $col = '#991b1b'; }
                                        else { $bg = '#e0e7ff'; $col = '#4338ca'; }
                                    ?>
                                    <span style="background:<?php echo $bg; ?>; color:<?php echo $col; ?>; padding:4px 10px; border-radius:20px; font-size:12px; font-weight:600;">
                                        <?php echo htmlspecialchars($s); ?>
                                    </span>
                                </td>
                                <td style="padding:16px 20px; font-size:13px; color:#64748b;">
                                    <?php echo date('d M Y', strtotime($lead['created_at'])); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Field Visits Table -->
    <div style="background:white; border-radius:12px; box-shadow:0 2px 4px rgba(0,0,0,0.05); border:1px solid #e2e8f0; overflow:hidden; margin-top:30px;">
        <div style="padding:20px; border-bottom:1px solid #e2e8f0;">
            <h2 style="margin:0; font-size:18px; color:var(--text-dark);">Recent Field Visits</h2>
        </div>
        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; text-align:left;">
                <thead>
                    <tr style="background:#f8fafc;">
                        <th style="padding:16px 20px; font-size:13px; color:#64748b; font-weight:600; text-transform:uppercase;">Date</th>
                        <th style="padding:16px 20px; font-size:13px; color:#64748b; font-weight:600; text-transform:uppercase;">Firm/Person</th>
                        <th style="padding:16px 20px; font-size:13px; color:#64748b; font-weight:600; text-transform:uppercase;">Location</th>
                        <th style="padding:16px 20px; font-size:13px; color:#64748b; font-weight:600; text-transform:uppercase;">Status / Quality</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_visits)): ?>
                        <tr><td colspan="4" style="text-align:center; padding:30px; color:#94a3b8;">No field visits recorded yet.</td></tr>
                    <?php else: ?>
                        <?php foreach($recent_visits as $v): ?>
                            <tr style="border-bottom:1px solid #e2e8f0;">
                                <td style="padding:16px 20px; font-weight:500;">
                                    <?php echo date('d M Y', strtotime($v['visit_date'])); ?>
                                </td>
                                <td style="padding:16px 20px;">
                                    <div style="font-weight:500;"><?php echo htmlspecialchars($v['firm_name']); ?></div>
                                    <div style="font-size:12px; color:#64748b;"><?php echo htmlspecialchars($v['person_name']); ?> (<?php echo htmlspecialchars($v['mobile']); ?>)</div>
                                </td>
                                <td style="padding:16px 20px;">
                                    <div><?php echo htmlspecialchars($v['city'] . ', ' . $v['state']); ?></div>
                                </td>
                                <td style="padding:16px 20px;">
                                    <?php 
                                        $q = $v['lead_quality'];
                                        $bg = '#e2e8f0'; $col = '#475569';
                                        if($q === 'Hot') { $bg = '#fee2e2'; $col = '#991b1b'; }
                                        elseif($q === 'Warm') { $bg = '#fef3c7'; $col = '#92400e'; }
                                        elseif($q === 'Cold') { $bg = '#e0e7ff'; $col = '#4338ca'; }
                                    ?>
                                    <span style="background:<?php echo $bg; ?>; color:<?php echo $col; ?>; padding:4px 10px; border-radius:20px; font-size:12px; font-weight:600;">
                                        <?php echo htmlspecialchars($q); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
