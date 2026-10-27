<?php
require_once __DIR__ . '/config.php';
require_role(['Admin', 'RM']);

$page_title = "Partner Portal Requests";
$page_subtitle = "B2B Channel Partners, CAs, Builders & DSA Onboarding Requests from Website";
$hide_main_header = true;
require_once __DIR__ . '/header.php';

// Handle AJAX Actions (Approve, Status Update, Assign RM)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    if ($action === 'update_status') {
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
            // Generate unique DSA Referral Code: DSA-XXXX
            $dsa_code = 'DSA-' . strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $req['name']), 0, 3)) . rand(100, 999);
            
            // Map profession to referrer_type
            $ref_type = 'DSA';
            $p_lower = strtolower($req['profession'] ?? '');
            if (strpos($p_lower, 'ca') !== false || strpos($p_lower, 'chartered') !== false) $ref_type = 'CA';
            elseif (strpos($p_lower, 'builder') !== false) $ref_type = 'Builder';
            elseif (strpos($p_lower, 'broker') !== false || strpos($p_lower, 'real estate') !== false) $ref_type = 'Agent';

            // Insert into referrals table
            $ins = $db->prepare("INSERT INTO referrals (
                referral_id, referrer_type, full_name, mobile, email, 
                city_state, status, commission_rate, payout_frequency, created_at, extra_details
            ) VALUES (?, ?, ?, ?, ?, ?, 'Active', '0.40%', 'Monthly', datetime('now', 'localtime'), ?)");

            $extra = "Auto-onboarded from Website Partner Portal. Profession: " . ($req['profession'] ?? '') . " | Monthly Volume: " . ($req['monthly_volume'] ?? '');
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

            // Update partner_requests status
            $upd = $db->prepare("UPDATE partner_requests SET status = 'Approved', dsa_code = ?, updated_at = datetime('now', 'localtime') WHERE id = ?");
            $upd->execute([$dsa_code, $req_id]);

            log_activity("Approved Website Partner {$req['name']} as DSA ({$dsa_code})", "referrals_list.php");

            echo json_encode(['success' => true, 'dsa_code' => $dsa_code]);
            exit;
        }
    }

    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}

// Filter query
$filter_status = $_GET['status'] ?? 'all';
$filter_prof = $_GET['profession'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$sql = "SELECT * FROM partner_requests WHERE 1=1";
$params = [];

if ($filter_status !== 'all' && !empty($filter_status)) {
    $sql .= " AND status = ?";
    $params[] = $filter_status;
}

if ($filter_prof !== 'all' && !empty($filter_prof)) {
    $sql .= " AND profession LIKE ?";
    $params[] = "%$filter_prof%";
}

if (!empty($search)) {
    $sql .= " AND (name LIKE ? OR phone LIKE ? OR email LIKE ? OR city LIKE ? OR state LIKE ?)";
    $s_term = "%$search%";
    $params[] = $s_term;
    $params[] = $s_term;
    $params[] = $s_term;
    $params[] = $s_term;
    $params[] = $s_term;
}

$sql .= " ORDER BY id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$partner_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Metrics
$total_reqs = (int)$db->query("SELECT COUNT(*) FROM partner_requests")->fetchColumn();
$pending_reqs = (int)$db->query("SELECT COUNT(*) FROM partner_requests WHERE status = 'Pending Review'")->fetchColumn();
$approved_reqs = (int)$db->query("SELECT COUNT(*) FROM partner_requests WHERE status = 'Approved'")->fetchColumn();
$contacted_reqs = (int)$db->query("SELECT COUNT(*) FROM partner_requests WHERE status = 'Contacted'")->fetchColumn();
?>

<style>
.pr-hub { display: flex; flex-direction: column; gap: 20px; }

/* KPI CARDS */
.pr-kpis { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; }
@media (max-width: 992px) { .pr-kpis { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 576px) { .pr-kpis { grid-template-columns: 1fr; } }

.pr-kpi {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 16px 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.02);
}
.pr-kpi-icon {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.pr-kpi-val { font-size: 26px; font-weight: 800; font-family: 'Outfit', sans-serif; line-height: 1; }
.pr-kpi-lbl { font-size: 12px; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 4px; }

/* FILTER BAR */
.pr-filter-bar {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 14px 18px;
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: center;
    justify-content: space-between;
}
.pr-search {
    flex: 1;
    min-width: 250px;
    position: relative;
    display: flex;
    align-items: center;
}
.pr-search input {
    width: 100%;
    padding: 9px 14px 9px 38px;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: var(--bg-main);
    color: var(--text-primary);
    font-size: 13.5px;
    outline: none;
    transition: border-color 0.2s;
}
.pr-search input:focus { border-color: var(--primary); }
.pr-search i { position: absolute; left: 12px; color: var(--text-muted); width: 16px; height: 16px; }

.pr-selects { display: flex; gap: 10px; flex-wrap: wrap; }
.pr-selects select {
    padding: 9px 30px 9px 12px;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: var(--bg-main);
    color: var(--text-primary);
    font-size: 13px;
    font-weight: 500;
    outline: none;
    cursor: pointer;
}

/* TABLE */
.pr-table-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 4px 16px rgba(0,0,0,0.02);
}
.pr-table { width: 100%; border-collapse: collapse; text-align: left; }
.pr-table th {
    background: rgba(0,0,0,0.02);
    padding: 14px 18px;
    font-size: 12px;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.6px;
    border-bottom: 1px solid var(--border);
}
.pr-table td {
    padding: 16px 18px;
    font-size: 13.5px;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
}
.pr-table tr:hover td { background: rgba(0,0,0,0.015); }
.pr-table tr:last-child td { border-bottom: none; }

.prof-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 11.5px;
    font-weight: 700;
    background: #f1f5f9;
    color: #334155;
    border: 1px solid #e2e8f0;
}
.dark .prof-badge { background: #1e293b; color: #94a3b8; border-color: #334155; }

.pr-status-pill {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    display: inline-block;
}
.pr-status-PendingReview { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
.pr-status-Contacted     { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
.pr-status-Approved      { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
.pr-status-Rejected      { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

.btn-approve-dsa {
    background: #0f172a;
    color: #ffffff;
    border: none;
    border-radius: 8px;
    padding: 6px 12px;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
}
.btn-approve-dsa:hover { background: #1e293b; transform: translateY(-1px); }

.pr-actions { display: flex; gap: 6px; align-items: center; justify-content: flex-end; }
.btn-pr-action {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--border);
    background: var(--bg-main);
    color: var(--text-primary);
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
}
.btn-pr-action:hover { transform: translateY(-2px); border-color: var(--primary); }
.btn-pr-wa { background: #25d366; color: white !important; border-color: #25d366; }
.btn-pr-call { background: #0f172a; color: white !important; }
</style>

<div class="pr-hub">
    <!-- TOP HEADER -->
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
        <div>
            <h1 style="font-size:24px; font-weight:800; font-family:'Outfit',sans-serif; margin:0; display:flex; align-items:center; gap:10px;">
                <i data-lucide="handshake" style="color:#0f172a; width:26px; height:26px;"></i>
                Partner Portal Requests Desk
            </h1>
            <p style="font-size:13px; color:var(--text-muted); margin:4px 0 0;">
                Channel partner onboarding requests, CAs, Builders & DSA registrations from <code>bfsfin.com/partner</code>
            </p>
        </div>
        <div style="display:flex; gap:10px;">
            <a href="referrals_list.php" class="btn btn-outline" style="display:inline-flex; align-items:center; gap:6px; font-size:13px; padding:8px 14px;">
                <i data-lucide="briefcase" style="width:15px; height:15px;"></i> Active DSA Network
            </a>
            <a href="add_referral.php" class="btn btn-primary" style="display:inline-flex; align-items:center; gap:6px; font-size:13px; padding:8px 14px;">
                <i data-lucide="user-plus" style="width:15px; height:15px;"></i> Add Manual DSA
            </a>
        </div>
    </div>

    <!-- KPIS -->
    <div class="pr-kpis">
        <div class="pr-kpi">
            <div class="pr-kpi-icon" style="background:#eff6ff; color:#2563eb;">
                <i data-lucide="users" style="width:24px; height:24px;"></i>
            </div>
            <div>
                <div class="pr-kpi-val"><?php echo $total_reqs; ?></div>
                <div class="pr-kpi-lbl">Total Requests</div>
            </div>
        </div>
        <div class="pr-kpi">
            <div class="pr-kpi-icon" style="background:#fef3c7; color:#d97706;">
                <i data-lucide="clock" style="width:24px; height:24px;"></i>
            </div>
            <div>
                <div class="pr-kpi-val" style="color:#d97706;"><?php echo $pending_reqs; ?></div>
                <div class="pr-kpi-lbl">Pending Review</div>
            </div>
        </div>
        <div class="pr-kpi">
            <div class="pr-kpi-icon" style="background:#f0fdf4; color:#16a34a;">
                <i data-lucide="check-circle-2" style="width:24px; height:24px;"></i>
            </div>
            <div>
                <div class="pr-kpi-val" style="color:#16a34a;"><?php echo $approved_reqs; ?></div>
                <div class="pr-kpi-lbl">Approved as DSA</div>
            </div>
        </div>
        <div class="pr-kpi">
            <div class="pr-kpi-icon" style="background:#f8fafc; color:#64748b;">
                <i data-lucide="phone-call" style="width:24px; height:24px;"></i>
            </div>
            <div>
                <div class="pr-kpi-val"><?php echo $contacted_reqs; ?></div>
                <div class="pr-kpi-lbl">Contacted</div>
            </div>
        </div>
    </div>

    <!-- FILTER BAR -->
    <form method="GET" class="pr-filter-bar">
        <div class="pr-search">
            <i data-lucide="search"></i>
            <input type="text" name="search" placeholder="Search by partner name, mobile, email, city or state..." value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <div class="pr-selects">
            <select name="profession" onchange="this.form.submit()">
                <option value="all" <?php echo $filter_prof === 'all' ? 'selected' : ''; ?>>All Professions</option>
                <option value="Chartered Accountant" <?php echo $filter_prof === 'Chartered Accountant' ? 'selected' : ''; ?>>CA / Tax Consultant</option>
                <option value="Builder" <?php echo $filter_prof === 'Builder' ? 'selected' : ''; ?>>Builder / Developer</option>
                <option value="Real Estate" <?php echo $filter_prof === 'Real Estate' ? 'selected' : ''; ?>>Real Estate Broker</option>
                <option value="DSA" <?php echo $filter_prof === 'DSA' ? 'selected' : ''; ?>>Direct Selling Agent (DSA)</option>
                <option value="Financial Advisor" <?php echo $filter_prof === 'Financial Advisor' ? 'selected' : ''; ?>>Financial Advisor</option>
            </select>
            <select name="status" onchange="this.form.submit()">
                <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                <option value="Pending Review" <?php echo $filter_status === 'Pending Review' ? 'selected' : ''; ?>>Pending Review</option>
                <option value="Contacted" <?php echo $filter_status === 'Contacted' ? 'selected' : ''; ?>>Contacted</option>
                <option value="Approved" <?php echo $filter_status === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                <option value="Rejected" <?php echo $filter_status === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
            </select>
            <?php if (!empty($search) || $filter_status !== 'all' || $filter_prof !== 'all'): ?>
            <a href="partner_requests.php" class="btn btn-outline" style="padding:8px 12px; font-size:12.5px;">Clear</a>
            <?php endif; ?>
        </div>
    </form>

    <!-- PARTNER REQUESTS TABLE -->
    <div class="pr-table-card">
        <?php if (empty($partner_requests)): ?>
        <div style="padding:48px 20px; text-align:center; color:var(--text-muted);">
            <i data-lucide="user-x" style="width:48px; height:48px; stroke-width:1.5; margin:0 auto 12px; display:block; opacity:0.6;"></i>
            <h3 style="font-size:16px; font-weight:700; color:var(--text-primary); margin:0 0 6px;">No Partner Requests Found</h3>
            <p style="font-size:13px; margin:0;">No partner inquiries matched your search. When partners register at <code>bfsfin.com/partner</code>, they show up here instantly.</p>
        </div>
        <?php else: ?>
        <div style="overflow-x:auto;">
            <table class="pr-table">
                <thead>
                    <tr>
                        <th>Partner Profile</th>
                        <th>Profession</th>
                        <th>Expected Monthly Volume</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Onboarding Action</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($partner_requests as $pr): 
                        $raw_phone = preg_replace('/[^0-9]/', '', $pr['phone']);
                        $wa_msg = urlencode("Hi " . $pr['name'] . ", thanks for expressing interest in joining Bhardwaj Financial Services as a Channel Partner (" . ($pr['profession'] ?: 'DSA') . "). When is a good time for a quick onboarding discussion?");
                        $status_class = str_replace(' ', '', $pr['status']);
                    ?>
                    <tr>
                        <td>
                            <div style="font-weight:700; color:var(--text-primary); font-size:14.5px;">
                                <?php echo htmlspecialchars($pr['name']); ?>
                            </div>
                            <div style="font-size:12px; color:var(--text-muted); display:flex; gap:8px; align-items:center; margin-top:3px;">
                                <span><i data-lucide="phone" style="width:11px; height:11px;"></i> <?php echo htmlspecialchars($pr['phone']); ?></span>
                                <?php if (!empty($pr['email']) && !str_contains($pr['email'], 'partner-')): ?>
                                <span>• <?php echo htmlspecialchars($pr['email']); ?></span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size:11px; color:var(--text-muted); margin-top:2px;">
                                Applied: <?php echo date('d M Y, h:i A', strtotime($pr['created_at'])); ?>
                            </div>
                        </td>
                        <td>
                            <span class="prof-badge"><?php echo htmlspecialchars($pr['profession'] ?: 'Partner'); ?></span>
                            <?php if (!empty($pr['experience'])): ?>
                            <div style="font-size:11.5px; color:var(--text-muted); margin-top:3px;">
                                Exp: <?php echo htmlspecialchars($pr['experience']); ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span style="font-weight:700; color:#10b981; font-size:13.5px;">
                                <?php echo htmlspecialchars($pr['monthly_volume'] ?: 'N/A'); ?>
                            </span>
                        </td>
                        <td>
                            <div style="font-weight:600; color:var(--text-primary);">
                                <?php echo htmlspecialchars($pr['city'] ?: 'Agra'); ?>
                            </div>
                            <div style="font-size:11px; color:var(--text-muted);">
                                <?php echo htmlspecialchars($pr['state'] ?: 'UP'); ?>
                            </div>
                        </td>
                        <td>
                            <select onchange="updatePartnerStatus(<?php echo $pr['id']; ?>, this.value)" class="pr-status-pill pr-status-<?php echo htmlspecialchars($status_class); ?>" style="cursor:pointer; outline:none; border:none;">
                                <option value="Pending Review" <?php echo $pr['status'] === 'Pending Review' ? 'selected' : ''; ?>>Pending Review</option>
                                <option value="Contacted" <?php echo $pr['status'] === 'Contacted' ? 'selected' : ''; ?>>Contacted</option>
                                <option value="Approved" <?php echo $pr['status'] === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="Rejected" <?php echo $pr['status'] === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </td>
                        <td>
                            <?php if (!empty($pr['dsa_code'])): ?>
                            <a href="referrals_list.php?search=<?php echo urlencode($pr['dsa_code']); ?>" style="display:inline-flex; align-items:center; gap:4px; font-weight:800; font-size:12px; color:#15803d; text-decoration:none;">
                                <i data-lucide="check-circle" style="width:13px; height:13px;"></i>
                                <?php echo htmlspecialchars($pr['dsa_code']); ?>
                            </a>
                            <?php elseif ($pr['status'] !== 'Rejected'): ?>
                            <button type="button" onclick="approveDSA(<?php echo $pr['id']; ?>, '<?php echo htmlspecialchars(addslashes($pr['name'])); ?>')" class="btn-approve-dsa">
                                <i data-lucide="user-check" style="width:13px; height:13px;"></i>
                                Approve & Onboard DSA
                            </button>
                            <?php else: ?>
                            <span style="font-size:11px; color:#ef4444; font-weight:600;">Rejected</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:right;">
                            <div class="pr-actions">
                                <?php if (!empty($raw_phone)): ?>
                                <a href="tel:<?php echo $raw_phone; ?>" class="btn-pr-action btn-pr-call" title="Call Partner">
                                    <i data-lucide="phone" style="width:14px; height:14px;"></i>
                                </a>
                                <a href="https://wa.me/91<?php echo substr($raw_phone, -10); ?>?text=<?php echo $wa_msg; ?>" target="_blank" class="btn-pr-action btn-pr-wa" title="WhatsApp Partner">
                                    <i data-lucide="message-circle" style="width:14px; height:14px;"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function updatePartnerStatus(id, status) {
    const formData = new FormData();
    formData.append('action', 'update_status');
    formData.append('id', id);
    formData.append('status', status);

    fetch('partner_requests.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Status update failed.');
            }
        }).catch(() => alert('Network error.'));
}

function approveDSA(id, name) {
    if (!confirm('Approve ' + name + ' as an official DSA partner? This will generate their DSA Code and create their profile in the Active Referrals & DSA Network.')) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'approve_dsa');
    formData.append('id', id);

    fetch('partner_requests.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('Partner onboarded successfully with DSA Code: ' + data.dsa_code);
                location.reload();
            } else {
                alert('Approval failed: ' + (data.error || 'Unknown error'));
            }
        }).catch(() => alert('Network error.'));
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
