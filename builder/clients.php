<?php
require_once 'includes/header.php';

// Get Referral ID
$stmt_ref = $db->prepare("SELECT referral_id FROM referrals WHERE referrer_type = 'Builder' AND full_name = ? LIMIT 1");
$stmt_ref->execute([$builder_name]);
$referral_id = $stmt_ref->fetchColumn() ?: 'BLD-' . $builder_id;

// Handle Demand Letter Upload (Mock for demo)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_demand') {
    $app_id = $_POST['applicant_id'] ?? 0;
    // In a real app we'd handle file upload here and insert into applicant_documents
    echo "<script>alert('Demand Letter uploaded successfully! CRM team notified for disbursement.'); window.location.href='clients.php';</script>";
    exit;
}

// Fetch Clients (Applicants) linked to this builder
$clients = [];
if (!empty($referral_id)) {
    $stmt_clients = $db->prepare("SELECT a.*, p.project_name FROM applicants a LEFT JOIN builder_projects p ON a.project_id = p.id WHERE a.referral_id = ? AND a.referral_id != '' ORDER BY a.created_at DESC");
    $stmt_clients->execute([$referral_id]);
    $clients = $stmt_clients->fetchAll();
}
?>

<div style="margin-bottom: 24px;">
    <h2 style="font-family: 'Outfit'; font-size: 22px; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
        Flat Buyers (Pipeline)
    </h2>
    <p style="color: var(--text-muted); font-size: 13px; margin-top: 4px;">Track Home Loans & Upload Demand Letters</p>
</div>

<!-- Client List -->
<?php if (empty($clients)): ?>
    <div style="text-align: center; padding: 40px 20px; background: var(--card-bg); border-radius: 20px; border: 1px dashed var(--border);">
        <div style="width: 50px; height: 50px; background: rgba(30, 41, 59, 0.05); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
            <i data-lucide="users" style="width: 24px; color: var(--text-muted);"></i>
        </div>
        <h3 style="font-family: 'Outfit'; font-size: 16px; margin-bottom: 8px;">No buyers yet</h3>
        <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 16px;">Share your application link to start getting home loan leads.</p>
        <a href="marketing.php" style="background: var(--accent); color: white; border: none; padding: 8px 16px; border-radius: 8px; font-weight: 600; font-size: 13px; cursor: pointer; text-decoration: none; display: inline-block;">
            Share Link
        </a>
    </div>
<?php else: ?>
    <div style="display: flex; flex-direction: column; gap: 16px;">
        <?php foreach ($clients as $client): 
            $status = $client['overall_status'];
            $bg = '#f1f5f9'; $color = '#475569';
            if (in_array($status, ['Phase 2', 'Phase 3', 'Phase 4'])) { $bg = '#fef9c3'; $color = '#a16207'; }
            if ($status === 'Completed') { $bg = '#dcfce7'; $color = '#166534'; }
            if ($status === 'Rejected') { $bg = '#fee2e2'; $color = '#b91c1c'; }
        ?>
            <div class="card" style="margin-bottom: 0;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                    <div>
                        <h3 style="font-family: 'Outfit'; font-size: 16px; margin-bottom: 4px;"><?php echo htmlspecialchars($client['customer_name']); ?></h3>
                        <div style="font-size: 12px; color: var(--text-muted); display:flex; align-items:center; gap:4px;">
                            <i data-lucide="building" style="width:12px;"></i> <?php echo htmlspecialchars($client['project_name'] ?? 'Unassigned Project'); ?>
                        </div>
                    </div>
                    <span style="background: <?php echo $bg; ?>; color: <?php echo $color; ?>; padding: 4px 8px; border-radius: 6px; font-size: 10px; font-weight: 700; text-transform: uppercase;">
                        <?php echo $status; ?>
                    </span>
                </div>
                
                <div style="background: #f8fafc; padding: 12px; border-radius: 12px; font-size: 12px; color: var(--text-primary); margin-bottom: 12px;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                        <span style="color:var(--text-muted);">Loan Amount:</span>
                        <strong>₹<?php echo number_format($client['loan_amount_requested']); ?></strong>
                    </div>
                    <div style="display:flex; justify-content:space-between;">
                        <span style="color:var(--text-muted);">Date Applied:</span>
                        <strong><?php echo date('d M Y', strtotime($client['created_at'])); ?></strong>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div style="display: flex; gap: 8px;">
                    <button onclick="openDemandModal(<?php echo $client['id']; ?>, '<?php echo htmlspecialchars(addslashes($client['customer_name'])); ?>')" style="flex: 1; background: white; border: 1px solid var(--border); color: var(--primary); padding: 8px; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px;">
                        <i data-lucide="upload-cloud" style="width:14px;"></i> Upload Demand Letter
                    </button>
                    <a href="tel:<?php echo htmlspecialchars($client['mobile']); ?>" style="width: 36px; height: 36px; background: #f1f5f9; color: var(--primary); border-radius: 8px; display: flex; align-items: center; justify-content: center; text-decoration: none;">
                        <i data-lucide="phone" style="width:16px;"></i>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Demand Letter Modal -->
<div id="demandModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.5); z-index: 200; backdrop-filter: blur(4px);">
    <div style="position: absolute; bottom: 0; left: 0; width: 100%; background: white; border-top-left-radius: 24px; border-top-right-radius: 24px; padding: 24px; box-shadow: 0 -10px 40px rgba(0,0,0,0.1); animation: slideUp 0.3s ease-out;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
            <h3 style="font-family: 'Outfit'; font-size: 18px;">Upload Demand Letter</h3>
            <i data-lucide="x" style="width: 20px; cursor: pointer; color: var(--text-muted);" onclick="document.getElementById('demandModal').style.display='none'"></i>
        </div>
        <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 20px;">For: <strong id="demandClientName" style="color:var(--text-primary);"></strong></p>
        
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload_demand">
            <input type="hidden" name="applicant_id" id="demandClientId" value="">
            
            <div style="border: 2px dashed var(--border); border-radius: 12px; padding: 30px 20px; text-align: center; margin-bottom: 20px; background: #f8fafc;">
                <i data-lucide="file-text" style="width: 32px; height: 32px; color: var(--primary); margin-bottom: 12px;"></i>
                <div style="font-size: 13px; font-weight: 600; color: var(--text-primary); margin-bottom: 4px;">Select PDF or Image</div>
                <div style="font-size: 11px; color: var(--text-muted);">Max size: 5MB</div>
                <input type="file" name="demand_letter" accept=".pdf,.jpg,.jpeg,.png" required style="margin-top: 16px; font-size: 12px; width: 100%;">
            </div>
            
            <button type="submit" style="width: 100%; background: var(--primary); color: white; border: none; padding: 14px; border-radius: 12px; font-weight: 700; font-size: 14px; cursor: pointer;">
                Submit for Disbursement
            </button>
        </form>
    </div>
</div>

<script>
function openDemandModal(id, name) {
    document.getElementById('demandClientId').value = id;
    document.getElementById('demandClientName').innerText = name;
    document.getElementById('demandModal').style.display = 'block';
}
</script>

<style>
@keyframes slideUp {
    from { transform: translateY(100%); }
    to { transform: translateY(0); }
}
</style>

<?php require_once 'includes/footer.php'; ?>
