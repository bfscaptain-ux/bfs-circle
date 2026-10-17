<?php
require_once '../config.php';
$active_page = 'index';
$page_title = 'Dashboard';

define('IS_SUBFOLDER', true);
require_role(['CA', 'CA/CS']);


$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
// Fetch CA details from referrals table
$stmt_name = $db->prepare("SELECT full_name, account_name FROM referrals WHERE user_id = ?");
$stmt_name->execute([$user_id]);
$ca_info = $stmt_name->fetch(PDO::FETCH_ASSOC);

$ca_name = $username; // Fallback
if ($ca_info) {
    $ca_name = !empty($ca_info['account_name']) ? $ca_info['account_name'] : $ca_info['full_name'];
}
$ca_name = $ca_name ?: 'Partner';

// 1. Fetch Real Data: Payouts/Wallet
// Assuming a structure for payouts; if none exists, we'll mock it temporarily or calculate from applicants
$stmt = $db->prepare("
    SELECT 
        SUM(CASE WHEN status = 'Disbursed' THEN amount ELSE 0 END) as total_earned,
        SUM(CASE WHEN status = 'Pending' THEN amount ELSE 0 END) as pending_payout
    FROM applicant_disbursements 
    WHERE applicant_id IN (SELECT id FROM applicants WHERE added_by = ?)
");
$stmt->execute([$username]);
$earnings = $stmt->fetch(PDO::FETCH_ASSOC);
$total_earned = $earnings['total_earned'] ?? 0;
$pending_payout = $earnings['pending_payout'] ?? 0;

// 2. Fetch Real Data: Pipeline Funnel from applicants
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as app_count,
        SUM(CASE WHEN overall_status IN ('Phase 2', 'Phase 3', 'Phase 4', 'Completed') THEN 1 ELSE 0 END) as login_done,
        SUM(CASE WHEN overall_status IN ('Phase 3', 'Phase 4', 'Completed') THEN 1 ELSE 0 END) as sanctioned,
        SUM(CASE WHEN overall_status = 'Completed' THEN 1 ELSE 0 END) as disbursed,
        SUM(CASE WHEN overall_status = 'Rejected' THEN 1 ELSE 0 END) as rejected
    FROM applicants 
    WHERE added_by = ?
");
$stmt->execute([$username]);
$pipeline = $stmt->fetch(PDO::FETCH_ASSOC);

// Get count from raw leads table
$stmt_leads = $db->prepare("SELECT COUNT(*) FROM leads WHERE added_by = ?");
$stmt_leads->execute([$username]);
$leads_count = $stmt_leads->fetchColumn();

// Merge counts
$pipeline['total_submitted'] = ($pipeline['app_count'] ?? 0) + ($leads_count ?? 0);
$pipeline['login_done'] = $pipeline['login_done'] ?? 0;
$pipeline['sanctioned'] = $pipeline['sanctioned'] ?? 0;
$pipeline['disbursed'] = $pipeline['disbursed'] ?? 0;
$pipeline['rejected'] = $pipeline['rejected'] ?? 0;

// Determine Tier based on Disbursed Count
$disbursed_count = $pipeline['disbursed'] ?? 0;
$tier = 'Silver';
$tier_color = '#94a3b8'; // Silver
if ($disbursed_count >= 20) {
    $tier = 'Platinum';
    $tier_color = '#0284c7'; // Platinum blue
} elseif ($disbursed_count >= 5) {
    $tier = 'Gold';
    $tier_color = '#eab308'; // Gold
}

require_once 'includes/header.php';
?>

<style>
    /* Fresh CA Dashboard CSS */
    .dashboard-container {
        padding: 0 20px 100px 20px;
        max-width: 800px;
        margin: 0 auto;
    }
    
    /* Welcome & Tier */
    .welcome-section {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
    }
    .welcome-text h1 {
        font-size: 20px;
        font-weight: 800;
        color: var(--text-main);
        margin: 0;
    }
    .welcome-text p {
        font-size: 13px;
        color: var(--text-muted);
        margin: 4px 0 0 0;
    }
    .tier-badge {
        background: white;
        border: 2px solid <?php echo $tier_color; ?>;
        color: <?php echo $tier_color; ?>;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 800;
        display: flex;
        align-items: center;
        gap: 6px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.05);
    }

    /* Wallet Hero Card */
    .wallet-card {
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        border-radius: 24px;
        padding: 24px;
        color: white;
        box-shadow: 0 15px 35px rgba(15, 44, 89, 0.2);
        margin-bottom: 30px;
        position: relative;
        overflow: hidden;
    }
    .wallet-card::after {
        content: '';
        position: absolute;
        right: -20px;
        bottom: -20px;
        width: 150px;
        height: 150px;
        background: rgba(255,255,255,0.05);
        border-radius: 50%;
    }
    .wallet-label {
        font-size: 13px;
        font-weight: 600;
        opacity: 0.8;
        margin-bottom: 4px;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    .wallet-amount {
        font-size: 36px;
        font-weight: 800;
        margin-bottom: 20px;
    }
    .wallet-bottom {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        border-top: 1px solid rgba(255,255,255,0.1);
        padding-top: 16px;
    }
    .pending-box .label { font-size: 12px; opacity: 0.8; }
    .pending-box .val { font-size: 18px; font-weight: 700; }
    
    .withdraw-btn {
        background: var(--accent);
        color: white;
        border: none;
        padding: 10px 16px;
        border-radius: 12px;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    /* Section Title */
    .section-title {
        font-size: 16px;
        font-weight: 800;
        color: var(--text-main);
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .section-title a {
        font-size: 13px;
        color: var(--primary);
        font-weight: 600;
        text-decoration: none;
    }

    /* 4-Grid Stats */
    .overview-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
        margin-bottom: 30px;
    }
    .o-card {
        background: white;
        border-radius: 16px;
        padding: 16px;
        border: 1px solid #f1f5f9;
        box-shadow: 0 4px 15px rgba(0,0,0,0.02);
    }
    .o-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 12px;
    }
    .o-val {
        font-size: 24px;
        font-weight: 800;
        color: var(--text-main);
        margin-bottom: 4px;
        line-height: 1;
    }
    .o-label {
        font-size: 12px;
        font-weight: 600;
        color: var(--text-muted);
    }

    /* Live Pipeline Funnel */
    .pipeline-card {
        background: white;
        border-radius: 20px;
        padding: 24px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.03);
        border: 1px solid #f1f5f9;
        margin-bottom: 30px;
    }
    .funnel-steps {
        display: flex;
        flex-direction: column;
        position: relative;
    }
    /* The connecting vertical line */
    .funnel-steps::before {
        content: '';
        position: absolute;
        top: 20px;
        bottom: 20px;
        left: 20px; /* 40px icon width / 2 */
        width: 2px;
        background: #e2e8f0;
        z-index: 1;
    }
    .f-step {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 12px 0;
        position: relative;
        z-index: 2;
    }
    .f-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        border: 4px solid white; /* To cut out the line behind it */
    }
    .f-content {
        flex: 1;
    }
    .f-name { font-size: 14px; font-weight: 700; color: var(--text-main); margin-bottom: 2px; }
    .f-desc { font-size: 12px; color: var(--text-muted); font-weight: 500; }
    .f-count { font-size: 16px; font-weight: 800; color: var(--text-main); background: #f8fafc; padding: 4px 12px; border-radius: 20px; }
    
    .empty-banner {
        background: linear-gradient(135deg, #eff6ff, #dbeafe);
        border: 1px dashed #93c5fd;
        padding: 16px;
        border-radius: 12px;
        text-align: center;
        margin-bottom: 20px;
    }
    .empty-banner p { font-size: 14px; color: #1e40af; font-weight: 700; margin: 0; }
    .empty-banner a { display: inline-block; margin-top: 8px; font-size: 13px; font-weight: 800; color: white; background: #2563eb; padding: 6px 12px; border-radius: 6px; text-decoration: none; }
    
    /* Quick Actions */
    .grid-actions {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
        margin-bottom: 30px;
    }
    .g-action {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 16px;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 12px;
        transition: all 0.2s;
        box-shadow: 0 4px 15px rgba(0,0,0,0.02);
    }
    .g-action:active { transform: scale(0.96); box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
    .g-action.blue { background: linear-gradient(to bottom right, #ffffff, #f0f9ff); border-color: #e0f2fe; }
    .g-action.orange { background: linear-gradient(to bottom right, #ffffff, #fff7ed); border-color: #ffedd5; }
    
    .g-action-icon {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .g-action-text {
        font-size: 14px;
        font-weight: 800;
        color: var(--text-main);
    }


</style>

<div class="dashboard-container">
    
    <!-- Welcome & Tier -->
    <div class="welcome-section">
        <div class="welcome-text">
            <p>Welcome to BFS Financial Services CA Family,</p>
            <h1><?php echo htmlspecialchars($ca_name); ?></h1>
        </div>
        <div class="tier-badge">
            <i data-lucide="award" style="width:16px;"></i> <?php echo $tier; ?>
        </div>
    </div>

    <!-- Wallet Hero Card -->
    <div class="wallet-card">
        <div class="wallet-label">Total Earnings (INR)</div>
        <div class="wallet-amount">₹<?php echo number_format($total_earned); ?></div>
        
        <div class="wallet-bottom">
            <div class="pending-box">
                <div class="label">Pending Payout</div>
                <div class="val">₹<?php echo number_format($pending_payout); ?></div>
            </div>
            <button class="withdraw-btn ripple" onclick="window.location.href='payouts.php'">
                <i data-lucide="arrow-up-right" style="width:16px;"></i> Withdraw
            </button>
        </div>
    </div>

    <!-- Overview Grid -->
    <div class="overview-grid">
        <div class="o-card">
            <div class="o-icon" style="background: #e0f2fe; color: #0284c7;"><i data-lucide="users" style="width:18px;"></i></div>
            <div class="o-val"><?php echo $pipeline['total_submitted'] ?? 0; ?></div>
            <div class="o-label">Total Leads</div>
        </div>
        <div class="o-card">
            <div class="o-icon" style="background: #dcfce7; color: #16a34a;"><i data-lucide="check-circle" style="width:18px;"></i></div>
            <div class="o-val"><?php echo $pipeline['sanctioned'] ?? 0; ?></div>
            <div class="o-label">Approved</div>
        </div>
        <div class="o-card">
            <div class="o-icon" style="background: #ffedd5; color: #ea580c;"><i data-lucide="banknote" style="width:18px;"></i></div>
            <div class="o-val"><?php echo $pipeline['disbursed'] ?? 0; ?></div>
            <div class="o-label">Disbursed</div>
        </div>
        <div class="o-card">
            <div class="o-icon" style="background: #fee2e2; color: #ef4444;"><i data-lucide="x-circle" style="width:18px;"></i></div>
            <div class="o-val"><?php echo $pipeline['rejected'] ?? 0; ?></div>
            <div class="o-label">Rejected</div>
        </div>
    </div>

    <!-- Pipeline Funnel -->
    <div class="section-title">
        Live Pipeline
        <a href="leads.php">View All</a>
    </div>
    
    <?php if ($pipeline['total_submitted'] == 0): ?>
    <div class="empty-banner">
        <p>🚀 Ready to start earning?</p>
        <a href="add_lead.php" class="ripple">Submit your first lead</a>
    </div>
    <?php endif; ?>
    
    <div class="pipeline-card">
        <div class="funnel-steps">
            <!-- Step 1 -->
            <div class="f-step">
                <div class="f-icon" style="background: #eff6ff; color: #3b82f6;"><i data-lucide="file-input"></i></div>
                <div class="f-content">
                    <div class="f-name">Submitted Leads</div>
                    <div class="f-desc">Total applications logged</div>
                </div>
                <div class="f-count"><?php echo $pipeline['total_submitted']; ?></div>
            </div>
            <!-- Step 2 -->
            <div class="f-step">
                <div class="f-icon" style="background: #fef3c7; color: #d97706;"><i data-lucide="clock"></i></div>
                <div class="f-content">
                    <div class="f-name">In Process (Login)</div>
                    <div class="f-desc">Credit appraisal ongoing</div>
                </div>
                <div class="f-count"><?php echo $pipeline['login_done']; ?></div>
            </div>
            <!-- Step 3 -->
            <div class="f-step">
                <div class="f-icon" style="background: #dcfce7; color: #16a34a;"><i data-lucide="check-circle-2"></i></div>
                <div class="f-content">
                    <div class="f-name">Sanctioned</div>
                    <div class="f-desc">Approved by bank</div>
                </div>
                <div class="f-count"><?php echo $pipeline['sanctioned']; ?></div>
            </div>
            <!-- Step 4 -->
            <div class="f-step">
                <div class="f-icon" style="background: #f3e8ff; color: #9333ea;"><i data-lucide="banknote"></i></div>
                <div class="f-content">
                    <div class="f-name">Disbursed</div>
                    <div class="f-desc">Loan amount released</div>
                </div>
                <div class="f-count"><?php echo $pipeline['disbursed']; ?></div>
            </div>
        </div>
    </div>

    <!-- Smart Actions -->
    <div class="section-title">Smart Actions</div>
    <div class="grid-actions">
        <a href="marketing.php" class="g-action blue ripple">
            <div class="g-action-icon" style="background: #e0f2fe; color: #0284c7;"><i data-lucide="qr-code"></i></div>
            <div class="g-action-text">Marketing Tools</div>
        </a>
        <a href="calculators.php" class="g-action orange ripple">
            <div class="g-action-icon" style="background: #ffedd5; color: #ea580c;"><i data-lucide="calculator"></i></div>
            <div class="g-action-text">Calculators</div>
        </a>
    </div>

</div>



<?php require_once 'includes/footer.php'; ?>
