<?php
define('IS_SUBFOLDER', true);
require_once __DIR__ . '/../config.php';
require_role(['CA', 'CA/CS']);


$page_title = 'Live Application Tracker';
$active_page = 'leads';
require_once 'includes/header.php';

$username = $_SESSION['username'];
$stmt = $db->prepare("SELECT id, customer_name, loan_amount_requested, overall_status, loan_type, created_at, 'applicant' as source_table FROM applicants WHERE added_by = ? ORDER BY created_at DESC");
$stmt->execute([$username]);
$apps = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt_leads = $db->prepare("SELECT id, lead_name as customer_name, loan_amount as loan_amount_requested, 'Phase 1' as overall_status, requirement as loan_type, assigned_at as created_at, 'lead' as source_table FROM leads WHERE added_by = ? ORDER BY assigned_at DESC");
$stmt_leads->execute([$username]);
$raw_leads = $stmt_leads->fetchAll(PDO::FETCH_ASSOC);

$applications = array_merge($apps, $raw_leads);
usort($applications, function($a, $b) {
    $timeA = strtotime($a['created_at'] ?? 'now');
    $timeB = strtotime($b['created_at'] ?? 'now');
    return $timeB - $timeA;
});

function getProgressProps($status) {
    $stages = ['Phase 1', 'Phase 2', 'Phase 3', 'Phase 4', 'Completed'];
    $idx = array_search($status, $stages);
    
    if ($status === 'Rejected') {
        return ['percent' => 100, 'color' => '#ef4444', 'text' => 'Rejected', 'step' => -1];
    }
    if ($idx === false) $idx = 0;
    
    $percent = ($idx / 4) * 100;
    $text = '';
    switch($idx) {
        case 0: $text = 'Application Logged In'; break;
        case 1: $text = 'Credit Appraisal'; break;
        case 2: $text = 'Loan Sanctioned'; break;
        case 3: $text = 'Ready for Disbursal'; break;
        case 4: $text = 'Disbursed'; break;
    }
    
    // Color mapping
    $color = '#3b82f6';
    if ($idx >= 2) $color = '#10b981';
    if ($idx == 4) $color = '#8b5cf6';
    
    return ['percent' => $percent, 'color' => $color, 'text' => $text, 'step' => $idx + 1];
}
?>

<style>
    .page-header { 
        display: flex; align-items: center; gap: 12px; margin-bottom: 24px; 
        font-size: 20px; font-weight: 800; color: var(--text-main); 
    }
    .leads-grid { display: flex; flex-direction: column; gap: 20px; padding-bottom: 100px; }
    
    .tracker-card {
        background: white; border-radius: 16px; padding: 20px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        border: 1px solid #e2e8f0;
    }
    
    .tc-header {
        display: flex; justify-content: space-between; align-items: flex-start;
        margin-bottom: 16px;
    }
    .tc-name { font-size: 16px; font-weight: 800; color: var(--text-main); margin-bottom: 4px; }
    .tc-loan { font-size: 13px; color: var(--text-muted); font-weight: 500; }
    .tc-amount { font-size: 18px; font-weight: 800; color: var(--primary); }
    
    /* Swiggy Style Progress */
    .progress-wrapper {
        margin-top: 10px;
        position: relative;
    }
    .progress-text {
        font-size: 13px; font-weight: 700; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;
    }
    .p-track {
        height: 6px; background: #f1f5f9; border-radius: 6px; width: 100%; overflow: hidden;
    }
    .p-fill {
        height: 100%; border-radius: 6px; transition: width 0.5s ease;
    }
    
    .milestones {
        display: flex; justify-content: space-between; margin-top: 8px;
    }
    .m-dot {
        width: 12px; height: 12px; border-radius: 50%; background: #f1f5f9;
        position: relative; z-index: 2; border: 2px solid white;
    }
    .m-dot.active { background: currentColor; }
    
    .no-leads {
        text-align: center; padding: 40px 20px; color: var(--text-muted);
    }
</style>

<div style="padding: 0 20px;">
    <div class="page-header">
        <div style="background: var(--primary); padding: 8px; border-radius: 10px; color: white; display: flex;">
            <i data-lucide="map-pin" style="width:20px; height:20px;"></i>
        </div>
        Live Tracker
    </div>

    <div class="leads-grid">
        <?php if (empty($applications)): ?>
            <div class="no-leads">
                <i data-lucide="ghost" style="width:48px; height:48px; margin-bottom:12px; opacity:0.5;"></i>
                <h3 style="font-weight: 700; color: var(--text-main);">No Applications Yet</h3>
                <p style="font-size: 14px;">Share your QR code to get your first lead.</p>
            </div>
        <?php else: ?>
            <?php foreach ($applications as $app): 
                $prog = getProgressProps($app['overall_status']);
                $date = $app['created_at'] ? date('d M, Y', strtotime($app['created_at'])) : 'Unknown Date';
            ?>
            <div class="tracker-card">
                <div class="tc-header">
                    <div>
                        <div class="tc-name"><?php echo htmlspecialchars($app['customer_name']); ?></div>
                        <div class="tc-loan"><?php echo htmlspecialchars($app['loan_type']); ?> • <?php echo $date; ?></div>
                    </div>
                    <div class="tc-amount">₹<?php echo number_format($app['loan_amount_requested'] ?? 0); ?></div>
                </div>
                
                <div class="progress-wrapper">
                    <div class="progress-text" style="color: <?php echo $prog['color']; ?>">
                        <?php if($prog['step'] === -1): ?>
                            <i data-lucide="x-circle" style="width:16px;"></i>
                        <?php elseif($prog['step'] >= 4): ?>
                            <i data-lucide="party-popper" style="width:16px;"></i>
                        <?php else: ?>
                            <i data-lucide="loader-2" style="width:16px;" class="spin"></i>
                        <?php endif; ?>
                        <?php echo $prog['text']; ?>
                    </div>
                    
                    <div class="p-track">
                        <div class="p-fill" style="width: <?php echo $prog['percent']; ?>%; background: <?php echo $prog['color']; ?>;"></div>
                    </div>
                    
                    <div class="milestones" style="color: <?php echo $prog['color']; ?>">
                        <div class="m-dot <?php echo $prog['step'] >= 1 ? 'active' : ''; ?>"></div>
                        <div class="m-dot <?php echo $prog['step'] >= 2 ? 'active' : ''; ?>"></div>
                        <div class="m-dot <?php echo $prog['step'] >= 3 ? 'active' : ''; ?>"></div>
                        <div class="m-dot <?php echo $prog['step'] >= 4 ? 'active' : ''; ?>"></div>
                        <div class="m-dot <?php echo $prog['step'] >= 5 ? 'active' : ''; ?>"></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<style>
    @keyframes spin { 100% { transform: rotate(360deg); } }
    .spin { animation: spin 2s linear infinite; }
</style>

<?php require_once 'includes/footer.php'; ?>
