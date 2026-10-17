<?php
require_once 'includes/header.php';

// Get partner details
$stmt = $db->prepare("SELECT * FROM referrals WHERE user_id = ?");
$stmt->execute([$partner_id]);
$partner = $stmt->fetch();
$referral_id = $partner ? $partner['referral_id'] : '';
$partner_full_name = $partner ? $partner['full_name'] : $partner_name;

// Fetch all clients referred by this partner
$clients = [];
if (!empty($referral_id)) {
    $stmt_app = $db->prepare("SELECT a.*, (SELECT rejection_reason FROM applicant_bank_assignments aba WHERE aba.applicant_id = a.id AND aba.status = 'Rejected' ORDER BY aba.id DESC LIMIT 1) as rejection_reason FROM applicants a WHERE a.referral_id = ? AND a.referral_id != '' ORDER BY a.created_at DESC");
    $stmt_app->execute([$referral_id]);
    $clients = $stmt_app->fetchAll(PDO::FETCH_ASSOC);
}

// Also fetch simple leads added directly
$raw_leads = [];
if (!empty($partner) && (!empty($partner_full_name) || !empty($partner_username))) {
    $stmt_leads = $db->prepare("SELECT * FROM leads WHERE (added_by = ? AND added_by != '') OR (added_by = ? AND added_by != '') ORDER BY created_at DESC");
    $stmt_leads->execute([$partner_full_name, $partner_username]);
    $raw_leads = $stmt_leads->fetchAll(PDO::FETCH_ASSOC);
}

// Combine and sort (Simple array merge for UI)
$all_clients = [];
foreach($clients as $c) {
    $c['type'] = 'applicant';
    $c['name'] = !empty($c['customer_name']) ? $c['customer_name'] : 'Unknown Client';
    $c['phone'] = !empty($c['mobile']) ? $c['mobile'] : 'No Phone';
    $c['loan_type'] = !empty($c['loan_type']) ? $c['loan_type'] : 'Unknown Loan';
    $all_clients[] = $c;
}
foreach($raw_leads as $l) {
    $l['type'] = 'lead';
    $l['name'] = !empty($l['lead_name']) ? $l['lead_name'] : 'Unknown Client';
    $l['phone'] = !empty($l['mobile']) ? $l['mobile'] : 'No Phone';
    $l['loan_type'] = !empty($l['requirement']) ? $l['requirement'] : 'Unknown Loan';
    $l['overall_status'] = !empty($l['status']) ? $l['status'] : 'Pending';
    $all_clients[] = $l;
}

// Simple descending sort by created_at
usort($all_clients, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

?>

<div style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center;">
    <div>
        <h2 style="font-family: 'Outfit'; font-size: 24px; color: var(--text-primary);">Portfolio</h2>
        <p style="color: var(--text-muted); font-size: 14px;">Track your submitted clients</p>
    </div>
    <a href="add_client.php" style="background: var(--accent); color: #0f172a; border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; text-decoration: none; box-shadow: 0 4px 10px rgba(234, 179, 8, 0.3);">
        <i data-lucide="plus" style="width: 20px;"></i>
    </a>
</div>

<!-- Search Bar -->
<div class="card" style="padding: 12px 16px; display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
    <i data-lucide="search" style="color: var(--text-muted); width: 20px;"></i>
    <input type="text" id="searchInput" placeholder="Search by client name..." style="border: none; background: transparent; padding: 0; outline: none; width: 100%; font-size: 15px; color: var(--text-primary); font-family: 'Inter'; font-weight:500;">
</div>

<div id="clientsList">
    <?php if (count($all_clients) > 0): ?>
        <?php foreach ($all_clients as $item): 
            $status_class = 'badge-pending';
            $status = $item['overall_status'] ?: 'Pending';
            
            if (in_array($status, ['Phase 2', 'Phase 3', 'Phase 4', 'Completed'])) $status_class = 'badge-approved';
            if ($status == 'Rejected') $status_class = 'badge-rejected';
            if ($status == 'Approved') $status_class = 'badge-approved'; // For leads
            
            $date = date('d M, Y', strtotime($item['created_at']));
            $loan_type = isset($item['loan_type']) ? $item['loan_type'] : 'Unknown Loan';
        ?>
            <div class="card client-card" data-name="<?php echo strtolower($item['name']); ?>" style="padding: 16px; margin-bottom: 12px;">
                <div style="display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 12px;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div style="width: 44px; height: 44px; border-radius: 12px; background: linear-gradient(135deg, rgba(15, 23, 42, 0.05), rgba(15, 23, 42, 0.1)); display: flex; align-items: center; justify-content: center; font-weight: 700; color: var(--primary); font-family: 'Outfit'; font-size: 18px;">
                            <?php echo strtoupper(substr($item['name'], 0, 1)); ?>
                        </div>
                        <div>
                            <div style="font-weight: 700; color: var(--text-primary); font-size: 15px;"><?php echo htmlspecialchars($item['name']); ?></div>
                            <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px; font-weight:500;">
                                <i data-lucide="phone" style="width:12px; height:12px; display:inline; vertical-align:middle;"></i> <?php echo htmlspecialchars($item['phone']); ?>
                            </div>
                        </div>
                    </div>
                    <span class="badge <?php echo $status_class; ?>"><?php echo $status; ?></span>
                </div>
                
                <div style="background: var(--bg-main); padding: 12px; border-radius: 12px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div style="font-size: 11px; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Product</div>
                        <div style="font-size: 13px; font-weight: 600; color: var(--text-primary); margin-top: 2px;"><?php echo $loan_type; ?></div>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-size: 11px; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Date Added</div>
                        <div style="font-size: 13px; font-weight: 600; color: var(--text-primary); margin-top: 2px;"><?php echo $date; ?></div>
                    </div>
                </div>
                <?php if ($status == 'Rejected' && !empty($item['rejection_reason'])): ?>
                <div style="margin-top: 10px; padding: 10px; background: #fef2f2; border-left: 3px solid #ef4444; border-radius: 4px; font-size: 12px;">
                    <strong style="color: #ef4444;">Reason for Rejection:</strong><br>
                    <span style="color: #7f1d1d;"><?php echo htmlspecialchars($item['rejection_reason']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="card" style="text-align: center; padding: 40px 20px;">
            <div style="width: 64px; height: 64px; background: rgba(234, 179, 8, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px auto;">
                <i data-lucide="folder-open" style="width: 32px; height: 32px; color: var(--accent);"></i>
            </div>
            <h4 style="font-family: 'Outfit'; font-size: 18px; color: var(--text-primary); margin-bottom: 8px;">Portfolio is Empty</h4>
            <p style="font-size: 14px; color: var(--text-muted); margin-bottom: 20px;">You haven't submitted any clients yet.</p>
            <a href="add_client.php" class="btn" style="width: auto; display: inline-block; padding: 12px 24px; text-decoration: none;">Add Your First Client</a>
        </div>
    <?php endif; ?>
</div>

<script>
document.getElementById('searchInput').addEventListener('input', function(e) {
    const term = e.target.value.toLowerCase();
    const cards = document.querySelectorAll('.client-card');
    cards.forEach(card => {
        if (card.dataset.name.includes(term)) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
