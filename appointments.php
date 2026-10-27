<?php
require_once __DIR__ . '/config.php';
require_role(['Admin', 'RM', 'Staff']);

$page_title = "Website Appointments";
$page_subtitle = "Scheduled consultations & doorstep meetings from website visitors";
$hide_main_header = true;
require_once __DIR__ . '/header.php';

// Handle AJAX Status / Assignment updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    
    if ($action === 'update_status') {
        $appt_id = (int)($_POST['id'] ?? 0);
        $new_status = trim($_POST['status'] ?? '');
        $allowed = ['Scheduled', 'Confirmed', 'Completed', 'Rescheduled', 'Cancelled'];
        if ($appt_id > 0 && in_array($new_status, $allowed)) {
            $stmt = $db->prepare("UPDATE website_appointments SET status = ?, updated_at = datetime('now', 'localtime') WHERE id = ?");
            $stmt->execute([$new_status, $appt_id]);
            echo json_encode(['success' => true]);
            exit;
        }
    }
    
    if ($action === 'assign_rm') {
        $appt_id = (int)($_POST['id'] ?? 0);
        $rm = trim($_POST['assigned_to'] ?? '');
        if ($appt_id > 0) {
            $stmt = $db->prepare("UPDATE website_appointments SET assigned_to = ?, updated_at = datetime('now', 'localtime') WHERE id = ?");
            $stmt->execute([$rm, $appt_id]);
            echo json_encode(['success' => true]);
            exit;
        }
    }
    
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

// Fetch Staff / RMs for assignment
$staff_list = $db->query("SELECT username, full_name, role FROM users WHERE is_active=1 ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);

// Base query for Appointments
$filter_status = $_GET['status'] ?? 'all';
$filter_date = $_GET['date_range'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$sql = "SELECT * FROM website_appointments WHERE 1=1";
$params = [];

if ($filter_status !== 'all' && !empty($filter_status)) {
    $sql .= " AND status = ?";
    $params[] = $filter_status;
}

if ($filter_date === 'today') {
    $sql .= " AND (slot_date = date('now', 'localtime') OR slot_date LIKE '%" . date('d M') . "%')";
} elseif ($filter_date === 'upcoming') {
    $sql .= " AND status IN ('Scheduled', 'Confirmed')";
} elseif ($filter_date === 'completed') {
    $sql .= " AND status = 'Completed'";
}

if (!empty($search)) {
    $sql .= " AND (name LIKE ? OR phone LIKE ? OR email LIKE ? OR city LIKE ?)";
    $s_term = "%$search%";
    $params[] = $s_term;
    $params[] = $s_term;
    $params[] = $s_term;
    $params[] = $s_term;
}

$sql .= " ORDER BY id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Metrics
$total_count = (int)$db->query("SELECT COUNT(*) FROM website_appointments")->fetchColumn();
$scheduled_count = (int)$db->query("SELECT COUNT(*) FROM website_appointments WHERE status IN ('Scheduled', 'Confirmed')")->fetchColumn();
$today_count = (int)$db->query("SELECT COUNT(*) FROM website_appointments WHERE (slot_date = date('now', 'localtime') OR slot_date LIKE '%" . date('d M') . "%')")->fetchColumn();
$completed_count = (int)$db->query("SELECT COUNT(*) FROM website_appointments WHERE status = 'Completed'")->fetchColumn();
?>

<style>
.appt-hub { display: flex; flex-direction: column; gap: 20px; }

/* KPI CARDS */
.appt-kpis { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; }
@media (max-width: 992px) { .appt-kpis { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 576px) { .appt-kpis { grid-template-columns: 1fr; } }

.appt-kpi {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 16px 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.02);
}
.appt-kpi-icon {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.appt-kpi-val { font-size: 26px; font-weight: 800; font-family: 'Outfit', sans-serif; line-height: 1; }
.appt-kpi-lbl { font-size: 12px; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 4px; }

/* FILTER BAR */
.appt-filter-bar {
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
.appt-search {
    flex: 1;
    min-width: 250px;
    position: relative;
    display: flex;
    align-items: center;
}
.appt-search input {
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
.appt-search input:focus { border-color: var(--primary); }
.appt-search i { position: absolute; left: 12px; color: var(--text-muted); width: 16px; height: 16px; }

.appt-selects { display: flex; gap: 10px; flex-wrap: wrap; }
.appt-selects select {
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

/* APPOINTMENT CARDS / TABLE */
.appt-table-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 4px 16px rgba(0,0,0,0.02);
}
.appt-table { width: 100%; border-collapse: collapse; text-align: left; }
.appt-table th {
    background: rgba(0,0,0,0.02);
    padding: 14px 18px;
    font-size: 12px;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.6px;
    border-bottom: 1px solid var(--border);
}
.appt-table td {
    padding: 16px 18px;
    font-size: 13.5px;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
}
.appt-table tr:hover td { background: rgba(0,0,0,0.015); }
.appt-table tr:last-child td { border-bottom: none; }

.client-name { font-weight: 700; color: var(--text-primary); font-size: 14.5px; }
.client-meta { font-size: 12px; color: var(--text-muted); display: flex; gap: 8px; align-items: center; margin-top: 3px; }

.slot-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 10px;
    background: #f0fdf4;
    color: #166534;
    border: 1px solid #bbf7d0;
    border-radius: 8px;
    font-weight: 700;
    font-size: 12.5px;
}
.dark .slot-badge { background: #064e3b; color: #a7f3d0; border-color: #047857; }

.status-pill {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    display: inline-block;
}
.status-Scheduled   { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
.status-Confirmed   { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
.status-Completed   { background: #f8fafc; color: #475569; border: 1px solid #cbd5e1; }
.status-Rescheduled { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
.status-Cancelled   { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

.appt-actions { display: flex; gap: 8px; align-items: center; }
.btn-action {
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
.btn-action:hover { transform: translateY(-2px); border-color: var(--primary); }
.btn-wa { background: #25d366; color: white !important; border-color: #25d366; }
.btn-call { background: #0f172a; color: white !important; }
</style>

<div class="appt-hub">
    <!-- TOP HEADER -->
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
        <div>
            <h1 style="font-size:24px; font-weight:800; font-family:'Outfit',sans-serif; margin:0; display:flex; align-items:center; gap:10px;">
                <i data-lucide="calendar" style="color:#0f172a; width:26px; height:26px;"></i>
                Website Appointments Desk
            </h1>
            <p style="font-size:13px; color:var(--text-muted); margin:4px 0 0;">
                All live doorstep visits, phone consultations & meeting slots booked from <code>bfsfin.com/appointment</code>
            </p>
        </div>
        <div style="display:flex; gap:10px;">
            <a href="leads.php" class="btn btn-outline" style="display:inline-flex; align-items:center; gap:6px; font-size:13px; padding:8px 14px;">
                <i data-lucide="target" style="width:15px; height:15px;"></i> All Leads Desk
            </a>
            <a href="reminders.php" class="btn btn-outline" style="display:inline-flex; align-items:center; gap:6px; font-size:13px; padding:8px 14px;">
                <i data-lucide="bell" style="width:15px; height:15px;"></i> Reminders
            </a>
        </div>
    </div>

    <!-- KPIS -->
    <div class="appt-kpis">
        <div class="appt-kpi">
            <div class="appt-kpi-icon" style="background:#eff6ff; color:#2563eb;">
                <i data-lucide="calendar" style="width:24px; height:24px;"></i>
            </div>
            <div>
                <div class="appt-kpi-val"><?php echo $total_count; ?></div>
                <div class="appt-kpi-lbl">Total Appointments</div>
            </div>
        </div>
        <div class="appt-kpi">
            <div class="appt-kpi-icon" style="background:#fef3c7; color:#d97706;">
                <i data-lucide="clock" style="width:24px; height:24px;"></i>
            </div>
            <div>
                <div class="appt-kpi-val" style="color:#d97706;"><?php echo $today_count; ?></div>
                <div class="appt-kpi-lbl">Today's Schedule</div>
            </div>
        </div>
        <div class="appt-kpi">
            <div class="appt-kpi-icon" style="background:#f0fdf4; color:#16a34a;">
                <i data-lucide="check-circle" style="width:24px; height:24px;"></i>
            </div>
            <div>
                <div class="appt-kpi-val" style="color:#16a34a;"><?php echo $scheduled_count; ?></div>
                <div class="appt-kpi-lbl">Active / Pending</div>
            </div>
        </div>
        <div class="appt-kpi">
            <div class="appt-kpi-icon" style="background:#f8fafc; color:#64748b;">
                <i data-lucide="folder-check" style="width:24px; height:24px;"></i>
            </div>
            <div>
                <div class="appt-kpi-val"><?php echo $completed_count; ?></div>
                <div class="appt-kpi-lbl">Completed</div>
            </div>
        </div>
    </div>

    <!-- FILTER BAR -->
    <form method="GET" class="appt-filter-bar">
        <div class="appt-search">
            <i data-lucide="search"></i>
            <input type="text" name="search" placeholder="Search by applicant name, mobile, email or city..." value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <div class="appt-selects">
            <select name="date_range" onchange="this.form.submit()">
                <option value="all" <?php echo $filter_date === 'all' ? 'selected' : ''; ?>>All Dates</option>
                <option value="today" <?php echo $filter_date === 'today' ? 'selected' : ''; ?>>Today's Appointments</option>
                <option value="upcoming" <?php echo $filter_date === 'upcoming' ? 'selected' : ''; ?>>Active / Upcoming</option>
                <option value="completed" <?php echo $filter_date === 'completed' ? 'selected' : ''; ?>>Completed</option>
            </select>
            <select name="status" onchange="this.form.submit()">
                <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                <option value="Scheduled" <?php echo $filter_status === 'Scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                <option value="Confirmed" <?php echo $filter_status === 'Confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                <option value="Completed" <?php echo $filter_status === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                <option value="Cancelled" <?php echo $filter_status === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
            </select>
            <?php if (!empty($search) || $filter_status !== 'all' || $filter_date !== 'all'): ?>
            <a href="appointments.php" class="btn btn-outline" style="padding:8px 12px; font-size:12.5px;">Clear</a>
            <?php endif; ?>
        </div>
    </form>

    <!-- APPOINTMENTS TABLE -->
    <div class="appt-table-card">
        <?php if (empty($appointments)): ?>
        <div style="padding:48px 20px; text-align:center; color:var(--text-muted);">
            <i data-lucide="calendar-x" style="width:48px; height:48px; stroke-width:1.5; margin:0 auto 12px; display:block; opacity:0.6;"></i>
            <h3 style="font-size:16px; font-weight:700; color:var(--text-primary); margin:0 0 6px;">No Appointments Found</h3>
            <p style="font-size:13px; margin:0;">No bookings matched your filter criteria. When visitors schedule from the website, they appear here instantly.</p>
        </div>
        <?php else: ?>
        <div style="overflow-x:auto;">
            <table class="appt-table">
                <thead>
                    <tr>
                        <th>Slot & Date</th>
                        <th>Applicant</th>
                        <th>Product / Purpose</th>
                        <th>City / Location</th>
                        <th>Status</th>
                        <th>Assigned RM</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($appointments as $app): 
                        $raw_phone = preg_replace('/[^0-9]/', '', $app['phone']);
                        $wa_msg = urlencode("Hi " . $app['name'] . ", this is regarding your appointment with Bhardwaj Financial Services on " . ($app['slot_date'] ?: 'your scheduled date') . ". How can we assist you today?");
                    ?>
                    <tr>
                        <td>
                            <div class="slot-badge">
                                <i data-lucide="clock" style="width:13px; height:13px;"></i>
                                <span><?php echo htmlspecialchars($app['slot_date'] ?: 'TBD'); ?></span>
                                <?php if (!empty($app['slot_time'])): ?>
                                <span style="opacity:0.75;">• <?php echo htmlspecialchars($app['slot_time']); ?></span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size:11px; color:var(--text-muted); margin-top:4px;">
                                Booked: <?php echo date('d M, h:i A', strtotime($app['created_at'])); ?>
                            </div>
                        </td>
                        <td>
                            <div class="client-name"><?php echo htmlspecialchars($app['name']); ?></div>
                            <div class="client-meta">
                                <span><i data-lucide="phone" style="width:11px; height:11px;"></i> <?php echo htmlspecialchars($app['phone']); ?></span>
                                <?php if (!empty($app['email']) && !str_contains($app['email'], 'no-email')): ?>
                                <span>• <?php echo htmlspecialchars($app['email']); ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div style="font-weight:700; color:var(--text-primary);">
                                <?php echo htmlspecialchars($app['product_type'] ?: 'Finance'); ?>
                            </div>
                            <div style="font-size:12px; color:var(--text-muted);">
                                <?php echo htmlspecialchars($app['sub_type'] ?: 'Consultation'); ?>
                            </div>
                        </td>
                        <td>
                            <span style="font-weight:600; color:var(--text-primary);">
                                <?php echo htmlspecialchars($app['city'] ?: 'Agra'); ?>
                            </span>
                        </td>
                        <td>
                            <select onchange="updateApptStatus(<?php echo $app['id']; ?>, this.value)" class="status-pill status-<?php echo htmlspecialchars($app['status']); ?>" style="cursor:pointer; outline:none; border:none;">
                                <option value="Scheduled" <?php echo $app['status'] === 'Scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                <option value="Confirmed" <?php echo $app['status'] === 'Confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="Completed" <?php echo $app['status'] === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="Rescheduled" <?php echo $app['status'] === 'Rescheduled' ? 'selected' : ''; ?>>Rescheduled</option>
                                <option value="Cancelled" <?php echo $app['status'] === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </td>
                        <td>
                            <select onchange="assignApptRM(<?php echo $app['id']; ?>, this.value)" style="padding:4px 8px; border-radius:6px; font-size:12px; border:1px solid var(--border); background:var(--bg-main); color:var(--text-primary); cursor:pointer;">
                                <option value="">Unassigned</option>
                                <?php foreach ($staff_list as $st): ?>
                                <option value="<?php echo htmlspecialchars($st['username']); ?>" <?php echo $app['assigned_to'] === $st['username'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($st['full_name'] ?: $st['username']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td style="text-align:right;">
                            <div class="appt-actions" style="justify-content:flex-end;">
                                <?php if (!empty($raw_phone)): ?>
                                <a href="tel:<?php echo $raw_phone; ?>" class="btn-action btn-call" title="Call Applicant">
                                    <i data-lucide="phone" style="width:14px; height:14px;"></i>
                                </a>
                                <a href="https://wa.me/91<?php echo substr($raw_phone, -10); ?>?text=<?php echo $wa_msg; ?>" target="_blank" class="btn-action btn-wa" title="Open WhatsApp Chat">
                                    <i data-lucide="message-circle" style="width:14px; height:14px;"></i>
                                </a>
                                <?php endif; ?>
                                <?php if (!empty($app['lead_id'])): ?>
                                <a href="leads.php?edit_lead=<?php echo (int)$app['lead_id']; ?>" class="btn-action" title="View Full CRM Lead Profile">
                                    <i data-lucide="external-link" style="width:14px; height:14px;"></i>
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
function updateApptStatus(id, status) {
    const formData = new FormData();
    formData.append('action', 'update_status');
    formData.append('id', id);
    formData.append('status', status);

    fetch('appointments.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Failed to update status.');
            }
        }).catch(() => alert('Network error.'));
}

function assignApptRM(id, rm) {
    const formData = new FormData();
    formData.append('action', 'assign_rm');
    formData.append('id', id);
    formData.append('assigned_to', rm);

    fetch('appointments.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                // assigned
            } else {
                alert('Failed to assign RM.');
            }
        }).catch(() => alert('Network error.'));
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
