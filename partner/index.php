<?php
require_once 'includes/header.php';

// Get partner details
$stmt = $db->prepare("SELECT * FROM referrals WHERE user_id = ?");
$stmt->execute([$partner_id]);
$partner = $stmt->fetch();

if (!$partner) {
    // Auto-heal: Try matching by mobile/username or full_name
    $stmt_fb = $db->prepare("SELECT * FROM referrals WHERE mobile = ? OR full_name = ? LIMIT 1");
    $stmt_fb->execute([$partner_username, $partner_name]);
    $partner = $stmt_fb->fetch();
    if ($partner) {
        $db->prepare("UPDATE referrals SET user_id = ? WHERE id = ?")->execute([$partner_id, $partner['id']]);
    }
}

if (!$partner) {
    echo "<div style='text-align:center; padding:50px 20px;'><i data-lucide='alert-triangle' style='width:48px; height:48px; color:var(--text-muted); margin-bottom:12px;'></i><h3 style='font-family:Outfit;'>Profile Not Found</h3><p style='color:var(--text-muted);'>Please contact admin to link your DSA profile.</p></div>";
    require_once 'includes/footer.php';
    exit;
}

$referral_id = $partner['referral_id'];

// Date Filtering Logic
$filter = $_GET['filter'] ?? 'this_month';
$start_date = '';
$end_date = '';

if ($filter === 'this_week') {
    $start_date = date('Y-m-d', strtotime('monday this week'));
    $end_date = date('Y-m-d', strtotime('sunday this week'));
} elseif ($filter === 'this_month') {
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-t');
} elseif ($filter === 'this_year') {
    $start_date = date('Y-01-01');
    $end_date = date('Y-12-31');
} elseif ($filter === 'custom') {
    $start_date = $_GET['start'] ?? date('Y-m-01');
    $end_date = $_GET['end'] ?? date('Y-m-t');
}

$date_condition_app = "";
$date_condition_lead = "";
$params_app = [$referral_id];
$params_lead = [$partner['full_name']];

if ($start_date && $end_date) {
    $date_condition_app = " AND date(created_at) BETWEEN ? AND ?";
    $date_condition_lead = " AND date(created_at) BETWEEN ? AND ?";
    $params_app[] = $start_date;
    $params_app[] = $end_date;
    $params_lead[] = $start_date;
    $params_lead[] = $end_date;
}

// Get Stats (Leads submitted by this partner)
$stmt_stats = $db->prepare("
    SELECT 
        COUNT(id) as total_referrals,
        SUM(CASE WHEN overall_status IN ('Phase 2', 'Phase 3', 'Phase 4', 'Completed') THEN 1 ELSE 0 END) as approved_cases,
        SUM(CASE WHEN overall_status = 'Completed' THEN 1 ELSE 0 END) as disbursed_cases,
        SUM(CASE WHEN overall_status = 'Rejected' THEN 1 ELSE 0 END) as rejected_cases
    FROM applicants 
    WHERE referral_id = ? $date_condition_app
");
$stmt_stats->execute($params_app);
$stats = $stmt_stats->fetch();

// Also count leads added directly
$stmt_leads = $db->prepare("SELECT COUNT(id) as total_leads FROM leads WHERE added_by = ? $date_condition_lead");
$stmt_leads->execute($params_lead);
$leads_stat = $stmt_leads->fetch();

$total_submissions = $stats['total_referrals'] + $leads_stat['total_leads'];
$approved = (int)$stats['approved_cases'];
$disbursed = (int)$stats['disbursed_cases'];
$rejected = (int)$stats['rejected_cases'];

// Calculate total disbursed amount
$stmt_disbursed_amt = $db->prepare("SELECT SUM(loan_amount_requested) as amt FROM applicants WHERE referral_id = ? AND overall_status = 'Completed' $date_condition_app");
$stmt_disbursed_amt->execute($params_app);
$disbursed_amount = $stmt_disbursed_amt->fetchColumn() ?: 0;
$target_amount = 10000000; // 1 Crore target
$progress_pct = min(100, ($disbursed_amount / $target_amount) * 100);

// Calculate estimated commission (Mock logic for demo)
$stmt_p = $db->prepare("SELECT SUM(net_payable) FROM payout_distributions WHERE payee_user_id = ?");
$stmt_p->execute([$_SESSION['user_id']]);
$estimated_payout = $stmt_p->fetchColumn() ?: 0;
$apply_link = "http://" . $_SERVER['HTTP_HOST'] . "/apply.php?ref=" . $referral_id;
$wa_text = urlencode("Hi! You can apply for a loan through my official link here: " . $apply_link);
?>

<!-- Welcome Section -->
<div style="margin-bottom: 24px;">
    <h2 style="font-family: 'Outfit'; font-size: 24px; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
        Hello, <?php echo htmlspecialchars(explode(' ', $partner['full_name'])[0]); ?> 👋
    </h2>
    <p style="color: var(--text-muted); font-size: 14px; margin-top: 4px;">Welcome to the BFS Financial Services DSA Family!</p>
</div>

<!-- Share Link Banner -->
<a href="https://wa.me/?text=<?php echo $wa_text; ?>" target="_blank" style="display: flex; align-items: center; justify-content: space-between; background: linear-gradient(135deg, #25D366, #128C7E); color: white; padding: 16px 20px; border-radius: 20px; text-decoration: none; box-shadow: 0 10px 25px rgba(37,211,102,0.25); margin-bottom: 24px; transition: transform 0.2s;">
    <div style="display: flex; align-items: center; gap: 14px;">
        <div style="background: rgba(255,255,255,0.2); width: 44px; height: 44px; border-radius: 14px; display: flex; align-items: center; justify-content: center;">
            <i data-lucide="share-2" style="width: 22px; color: white;"></i>
        </div>
        <div>
            <div style="font-family: 'Outfit'; font-weight: 700; font-size: 17px; margin-bottom: 2px;">Share Application Link</div>
            <div style="font-size: 13px; opacity: 0.9; font-weight: 500;">Get leads directly on WhatsApp</div>
        </div>
    </div>
    <i data-lucide="chevron-right" style="width: 20px; opacity: 0.8;"></i>
</a>

<style>
.custom-select-wrapper { position: relative; user-select: none; }
.custom-select { background: rgba(255,255,255,0.15); padding: 6px 14px; border-radius: 14px; font-size: 12px; font-weight: 600; color: white; border: 1px solid rgba(255,255,255,0.2); cursor: pointer; display: flex; align-items: center; gap: 6px; transition: all 0.2s; }
.custom-select:hover { background: rgba(255,255,255,0.25); }
.custom-options { position: absolute; top: 100%; right: 0; margin-top: 8px; background: white; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); overflow: hidden; display: none; flex-direction: column; min-width: 140px; z-index: 50; border: 1px solid var(--border); }
.custom-options.open { display: flex; }
.custom-option { padding: 12px 16px; font-size: 13px; font-weight: 500; color: #1e293b; cursor: pointer; transition: background 0.2s; text-align: left; }
.custom-option:hover { background: #f1f5f9; color: var(--primary); }
.custom-option.selected { background: #f8fafc; color: var(--primary); font-weight: 700; }
</style>

<!-- Commission Card -->
<div class="card" style="background: linear-gradient(135deg, var(--primary), var(--primary-hover)); color: white; margin-bottom: 24px; border: none; box-shadow: 0 10px 25px rgba(15, 23, 42, 0.2); position: relative; z-index: 10;">
    <div style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 16px;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div style="font-size: 13px; font-weight: 500; color: #cbd5e1; display:flex; align-items:center; gap:6px;">
                <i data-lucide="wallet" style="width:16px;"></i> Total Earnings
            </div>
            <form method="GET" id="filterForm" style="margin:0; display:flex; flex-direction:column; align-items:flex-end; gap:8px;">
                <input type="hidden" name="filter" id="filterInput" value="<?php echo htmlspecialchars($filter); ?>">
                
                <div class="custom-select-wrapper">
                    <div class="custom-select" onclick="document.getElementById('customOpts').classList.toggle('open')">
                        <?php 
                        $labels = ['this_week'=>'This Week', 'this_month'=>'This Month', 'this_year'=>'This Year', 'custom'=>'Custom...'];
                        echo $labels[$filter] ?? 'This Month';
                        ?>
                        <i data-lucide="chevron-down" style="width:14px;"></i>
                    </div>
                    <div class="custom-options" id="customOpts">
                        <div class="custom-option <?php echo $filter=='this_week'?'selected':''; ?>" onclick="setFilter('this_week')">This Week</div>
                        <div class="custom-option <?php echo $filter=='this_month'?'selected':''; ?>" onclick="setFilter('this_month')">This Month</div>
                        <div class="custom-option <?php echo $filter=='this_year'?'selected':''; ?>" onclick="setFilter('this_year')">This Year</div>
                        <div class="custom-option <?php echo $filter=='custom'?'selected':''; ?>" onclick="setFilter('custom')">Custom...</div>
                    </div>
                </div>
        </div>
        
        <div id="customDates" style="display: <?php echo $filter=='custom' ? 'flex' : 'none'; ?>; gap:6px; align-items:center; flex-wrap:nowrap; justify-content:flex-end; width:100%;">
            <input type="date" name="start" value="<?php echo htmlspecialchars($start_date); ?>" style="padding:6px; font-size:11px; border-radius:6px; border:none; outline:none; color:#0f172a; flex:1; max-width:130px;">
            <span style="color:white; font-size:11px; opacity:0.8;">to</span>
            <input type="date" name="end" value="<?php echo htmlspecialchars($end_date); ?>" style="padding:6px; font-size:11px; border-radius:6px; border:none; outline:none; color:#0f172a; flex:1; max-width:130px;">
            <button type="submit" style="background:white; color:var(--primary); border:none; border-radius:6px; padding:6px 12px; font-size:11px; font-weight:800; cursor:pointer;">Go</button>
        </div>
        </form>
    </div>

        <script>
        function setFilter(val) {
            document.getElementById('customOpts').classList.remove('open');
            if(val === 'custom') {
                document.getElementById('customDates').style.display='flex';
                document.getElementById('filterInput').value = 'custom';
                document.querySelector('.custom-select').innerHTML = 'Custom... <i data-lucide="chevron-down" style="width:14px;"></i>';
                lucide.createIcons();
            } else {
                document.getElementById('filterInput').value = val;
                document.getElementById('filterForm').submit();
            }
        }
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.custom-select-wrapper')) {
                const opts = document.getElementById('customOpts');
                if(opts) opts.classList.remove('open');
            }
        });
        </script>
    </div>
    <div style="font-family: 'Outfit'; font-size: 32px; font-weight: 800; color: var(--accent); margin-bottom: 4px;">
        ₹<?php echo number_format($estimated_payout); ?>
    </div>
    <div style="display: flex; justify-content: space-between; align-items: flex-end;">
        <div style="font-size: 12px; color: #94a3b8; font-weight: 500;">
            From <?php echo $disbursed; ?> disbursed clients
        </div>
        <?php if ($estimated_payout > 0): ?>
        <form method="POST" action="request_payout.php" style="margin:0;">
            <input type="hidden" name="amount" value="<?php echo $estimated_payout; ?>">
            <button type="submit" style="background: var(--accent); color: white; border: none; padding: 6px 12px; border-radius: 8px; font-weight: 600; font-size: 11px; cursor: pointer;">Request Payout</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<!-- Target Tracker -->
<div class="card" style="margin-bottom: 24px; padding: 20px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
        <div style="font-family:'Outfit'; font-weight:700; color:var(--text-primary); font-size:16px;">Monthly Target</div>
        <div style="font-size:12px; color:var(--text-muted); font-weight:600;">₹1 Crore</div>
    </div>
    <div style="background: #f1f5f9; height: 10px; border-radius: 5px; overflow: hidden; margin-bottom: 8px;">
        <div style="background: var(--accent); height: 100%; width: <?php echo $progress_pct; ?>%; border-radius: 5px;"></div>
    </div>
    <div style="font-size:12px; color:var(--text-muted);">
        <strong>₹<?php echo number_format($disbursed_amount); ?></strong> disbursed so far. 
        <?php if ($progress_pct >= 100): ?>
            <span style="color:var(--accent); font-weight:bold;">Target Achieved! 🎉</span>
        <?php else: ?>
            Keep going for 2% extra bonus!
        <?php endif; ?>
    </div>
</div>

<!-- Stats Grid -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px;">
    <!-- Card 1 -->
    <div class="card" style="margin-bottom: 0;">
        <div style="width: 38px; height: 38px; border-radius: 10px; background: rgba(15, 23, 42, 0.05); color: var(--primary); display: flex; align-items: center; justify-content: center; margin-bottom: 12px;">
            <i data-lucide="users" style="width: 20px; height: 20px;"></i>
        </div>
        <div style="font-size: 28px; font-weight: 800; color: var(--text-primary); font-family: 'Outfit';"><?php echo $total_submissions; ?></div>
        <div style="font-size: 13px; color: var(--text-muted); font-weight: 600; margin-top: 4px;">Portfolio Size</div>
    </div>
    
    <!-- Card 2 -->
    <div class="card" style="margin-bottom: 0;">
        <div style="width: 38px; height: 38px; border-radius: 10px; background: rgba(234, 179, 8, 0.15); color: #a16207; display: flex; align-items: center; justify-content: center; margin-bottom: 12px;">
            <i data-lucide="check-circle" style="width: 20px; height: 20px;"></i>
        </div>
        <div style="font-size: 28px; font-weight: 800; color: var(--text-primary); font-family: 'Outfit';"><?php echo $approved; ?></div>
        <div style="font-size: 13px; color: var(--text-muted); font-weight: 600; margin-top: 4px;">Approved</div>
    </div>
</div>

<!-- Performance Funnel Chart -->
<div class="card" style="margin-bottom: 24px; padding: 24px;">
    <h3 style="font-family: 'Outfit'; font-size: 18px; color: var(--text-primary); margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
        <i data-lucide="bar-chart-2" style="color: var(--primary); width: 20px;"></i> Conversion Funnel
    </h3>
    <div style="height: 220px; width: 100%;">
        <canvas id="funnelChart"></canvas>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const ctx = document.getElementById('funnelChart').getContext('2d');
    
    let gradient = ctx.createLinearGradient(0, 0, 300, 0);
    gradient.addColorStop(0, '#1e40af'); // Blue
    gradient.addColorStop(1, '#10b981'); // Green

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Total', 'Approved', 'Disbursed'],
            datasets: [{
                label: 'Clients',
                data: [<?php echo max(0, $total_submissions); ?>, <?php echo max(0, $approved); ?>, <?php echo max(0, $disbursed); ?>],
                backgroundColor: gradient,
                borderRadius: 6,
                barThickness: 24
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { backgroundColor: '#0f172a', titleFont: { family: 'Inter', size: 13 }, bodyFont: { family: 'Inter', size: 14, weight: 'bold' }, padding: 12, cornerRadius: 8, displayColors: false }
            },
            scales: {
                x: { beginAtZero: true, grid: { display: true, drawBorder: false, color: '#f1f5f9' }, ticks: { stepSize: 1, font: { family: 'Inter', size: 11 } }, suggestedMax: <?php echo $total_submissions == 0 ? 5 : $total_submissions + 2; ?> },
                y: { grid: { display: false, drawBorder: false }, ticks: { font: { family: 'Inter', weight: '600' }, color: '#64748b' } }
            }
        }
    });
});
</script>

<!-- Recent Clients -->
<h3 style="font-family: 'Outfit'; font-size: 18px; color: var(--text-primary); margin-bottom: 12px; display:flex; justify-content:space-between; align-items:center;">
    Recent Updates
    <a href="clients.php" style="font-size:12px; color:var(--primary); text-decoration:none; font-weight:600; font-family:'Inter';">View All</a>
</h3>

<?php
// Fetch recent applicants for this partner
$stmt_recent = $db->prepare("SELECT id, customer_name, created_at, overall_status, loan_type FROM applicants WHERE referral_id = ? ORDER BY created_at DESC LIMIT 3");
$stmt_recent->execute([$referral_id]);
$recent = $stmt_recent->fetchAll();

if (count($recent) > 0) {
    foreach ($recent as $item) {
        $status_class = 'badge-pending';
        if (in_array($item['overall_status'], ['Phase 2', 'Phase 3', 'Phase 4', 'Completed'])) $status_class = 'badge-approved';
        if ($item['overall_status'] == 'Rejected') $status_class = 'badge-rejected';
        
        $date = date('d M, Y', strtotime($item['created_at']));
        ?>
        <div class="card" style="padding: 16px; margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--bg-main); display: flex; align-items: center; justify-content: center; font-weight: 700; color: var(--primary);">
                    <?php echo strtoupper(substr($item['customer_name'] ?? 'U', 0, 1)); ?>
                </div>
                <div>
                    <div style="font-weight: 600; color: var(--text-primary); font-size: 14px;"><?php echo htmlspecialchars($item['customer_name'] ?? 'Unknown'); ?></div>
                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;"><?php echo $item['loan_type'] ?: 'Unknown Loan'; ?> • <?php echo $date; ?></div>
                </div>
            </div>
            <div>
                <span class="badge <?php echo $status_class; ?>"><?php echo $item['overall_status'] ?: 'Pending'; ?></span>
            </div>
        </div>
        <?php
    }
} else {
    ?>
    <div class="card" style="text-align: center; padding: 30px 20px;">
        <i data-lucide="file-text" style="width: 40px; height: 40px; color: var(--border); margin-bottom: 12px;"></i>
        <h4 style="font-size: 15px; color: var(--text-primary); margin-bottom: 4px;">No Clients Yet</h4>
        <p style="font-size: 13px; color: var(--text-muted);">Start adding clients to track your portfolio.</p>
    </div>
    <?php
}
?>

<?php require_once 'includes/footer.php'; ?>
