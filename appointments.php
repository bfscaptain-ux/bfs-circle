<?php
require_once __DIR__ . '/config.php';
require_role(['Admin', 'RM', 'Staff']);

// Handle AJAX Status / Assignment updates FIRST before any HTML output
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

            // Auto-dispatch confirmation email to client when Scheduled or Confirmed
            $email_sent = false;
            if ($new_status === 'Confirmed' || $new_status === 'Scheduled') {
                $chk_app = $db->prepare("SELECT * FROM website_appointments WHERE id = ?");
                $chk_app->execute([$appt_id]);
                $app_data = $chk_app->fetch(PDO::FETCH_ASSOC);
                if ($app_data && !empty($app_data['email']) && filter_var($app_data['email'], FILTER_VALIDATE_EMAIL)) {
                    $m_res = @sendAppointmentConfirmationEmail($app_data);
                    $email_sent = !empty($m_res['success']);
                }
            }

            echo json_encode(['success' => true, 'email_sent' => $email_sent]);
            exit;
        }
    }
    
    if ($action === 'assign_rm') {
        $appt_id = (int)($_POST['id'] ?? 0);
        $rm = trim($_POST['assigned_to'] ?? '');
        if ($appt_id > 0) {
            // Strictly enforce: only legitimate in-office RM / Staff / Admin can be assigned
            if (!empty($rm)) {
                $chk_rm = $db->prepare("SELECT id FROM users WHERE username = ? AND is_active = 1 AND role IN ('RM', 'Staff', 'Admin') AND role NOT IN ('Partner', 'Agent', 'Builder', 'CA', 'DSA')");
                $chk_rm->execute([$rm]);
                if (!$chk_rm->fetch()) {
                    echo json_encode(['success' => false, 'error' => 'Only authorized In-Office Relationship Managers can be assigned.']);
                    exit;
                }
            }
            $stmt = $db->prepare("UPDATE website_appointments SET assigned_to = ?, updated_at = datetime('now', 'localtime') WHERE id = ?");
            $stmt->execute([$rm, $appt_id]);
            echo json_encode(['success' => true]);
            exit;
        }
    }
    
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$page_title = "Appointments Desk";
$page_subtitle = "Doorstep visits, phone consultations & meeting slots booked from website";
$hide_main_header = true;
require_once __DIR__ . '/header.php';

// Fetch ONLY legitimate In-Office Relationship Managers & Staff for assignment
$staff_list = $db->query("
    SELECT u.id, u.username, 
           COALESCE(NULLIF(e.full_name, ''), NULLIF(u.full_name, ''), u.name, u.username) AS display_name, 
           u.role, 
           COALESCE(e.designation, u.role) AS designation
    FROM users u
    LEFT JOIN employees e ON (e.user_id = u.id OR e.official_email = u.username)
    WHERE u.is_active = 1
      AND u.role IN ('RM', 'Staff', 'Admin')
      AND u.role NOT IN ('Partner', 'Agent', 'Builder', 'CA', 'DSA')
      AND u.username NOT LIKE 'apex_dsa_%'
      AND u.username NOT LIKE 'BFS-FAM-%'
    ORDER BY (CASE WHEN u.role = 'RM' THEN 1 WHEN u.role = 'Staff' THEN 2 ELSE 3 END), display_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Pagination & Filter Parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

$per_page_param = isset($_GET['per_page']) ? trim($_GET['per_page']) : '10';
$allowed_per_page = ['10', '15', '25', '50', '100', 'all'];
if (!in_array($per_page_param, $allowed_per_page)) {
    $per_page_param = '10';
}

$filter_status = $_GET['status'] ?? 'all';
$filter_date = $_GET['date_range'] ?? 'all';
$filter_prod = $_GET['product'] ?? 'all';
$filter_rm = $_GET['rm'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$where_sql = "";
$params = [];

if ($filter_status !== 'all' && !empty($filter_status)) {
    $where_sql .= " AND status = ?";
    $params[] = $filter_status;
}

if ($filter_date === 'today') {
    $where_sql .= " AND (slot_date = date('now', 'localtime') OR slot_date LIKE '%" . date('Y-m-d') . "%' OR slot_date LIKE '%" . date('d M') . "%')";
} elseif ($filter_date === 'tomorrow') {
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $where_sql .= " AND (slot_date = ? OR slot_date LIKE '%Tomorrow%' OR slot_date LIKE '%" . date('d M', strtotime('+1 day')) . "%')";
    $params[] = $tomorrow;
} elseif ($filter_date === 'upcoming') {
    $where_sql .= " AND status IN ('Scheduled', 'Confirmed')";
} elseif ($filter_date === 'completed') {
    $where_sql .= " AND status = 'Completed'";
}

if ($filter_prod !== 'all' && !empty($filter_prod)) {
    $where_sql .= " AND (product_type LIKE ? OR sub_type LIKE ?)";
    $params[] = "%$filter_prod%";
    $params[] = "%$filter_prod%";
}

if ($filter_rm !== 'all' && !empty($filter_rm)) {
    if ($filter_rm === 'unassigned') {
        $where_sql .= " AND (assigned_to IS NULL OR assigned_to = '')";
    } else {
        $where_sql .= " AND assigned_to = ?";
        $params[] = $filter_rm;
    }
}

if (!empty($search)) {
    $where_sql .= " AND (name LIKE ? OR phone LIKE ? OR email LIKE ? OR city LIKE ? OR sub_type LIKE ?)";
    $s_term = "%$search%";
    $params[] = $s_term;
    $params[] = $s_term;
    $params[] = $s_term;
    $params[] = $s_term;
    $params[] = $s_term;
}

// Count total matching items
$count_stmt = $db->prepare("SELECT COUNT(*) FROM website_appointments WHERE 1=1" . $where_sql);
$count_stmt->execute($params);
$total_items = (int)$count_stmt->fetchColumn();

// Pagination Calculation
if ($per_page_param === 'all') {
    $per_page = max(1, $total_items);
    $total_pages = 1;
    $offset = 0;
    $start_item = ($total_items > 0) ? 1 : 0;
    $end_item = $total_items;
} else {
    $per_page = (int)$per_page_param;
    if ($per_page < 1) $per_page = 10;
    $total_pages = (int)ceil($total_items / $per_page);
    if ($total_pages < 1) $total_pages = 1;
    if ($page > $total_pages) $page = $total_pages;

    $offset = ($page - 1) * $per_page;
    $start_item = ($total_items > 0) ? ($offset + 1) : 0;
    $end_item = min($offset + $per_page, $total_items);
}

$sql = "SELECT * FROM website_appointments WHERE 1=1" . $where_sql . " ORDER BY id DESC";
if ($per_page_param !== 'all') {
    $sql .= " LIMIT " . (int)$per_page . " OFFSET " . (int)$offset;
}
$stmt = $db->prepare($sql);
$stmt->execute($params);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Metrics
$total_count = (int)$db->query("SELECT COUNT(*) FROM website_appointments")->fetchColumn();
$scheduled_count = (int)$db->query("SELECT COUNT(*) FROM website_appointments WHERE status IN ('Scheduled', 'Confirmed')")->fetchColumn();
$today_count = (int)$db->query("SELECT COUNT(*) FROM website_appointments WHERE (slot_date = date('now', 'localtime') OR slot_date LIKE '%" . date('Y-m-d') . "%' OR slot_date LIKE '%" . date('d M') . "%')")->fetchColumn();
$completed_count = (int)$db->query("SELECT COUNT(*) FROM website_appointments WHERE status = 'Completed'")->fetchColumn();

// Helper to generate Google Calendar Link
function get_google_calendar_url($app) {
    $title = urlencode("BFS Consultation: " . $app['name'] . " (" . ($app['sub_type'] ?: 'Loan Advisory') . ")");
    
    // Parse Date and Time
    $date_str = $app['slot_date'] ?: date('Y-m-d');
    $time_str = $app['slot_time'] ?: '11:00 AM';
    
    // Attempt parse
    $start_ts = strtotime($date_str . ' ' . explode('–', explode('-', $time_str)[0])[0]);
    if ($start_ts === false || $start_ts < 0) {
        $start_ts = strtotime('+1 day 11:00 AM');
    }
    $end_ts = $start_ts + (60 * 60); // 1 hour duration
    
    $dates = gmdate('Ymd\THis\Z', $start_ts) . '/' . gmdate('Ymd\THis\Z', $end_ts);
    $location = urlencode(($app['city'] ?: 'Agra') . ', India');
    $details = urlencode("Applicant: " . $app['name'] . "\nPhone: " . $app['phone'] . "\nEmail: " . $app['email'] . "\nProduct: " . $app['product_type'] . " - " . $app['sub_type'] . "\nCity: " . $app['city'] . "\nNotes: " . $app['message']);
    
    return "https://calendar.google.com/calendar/render?action=TEMPLATE&text={$title}&dates={$dates}&details={$details}&location={$location}";
}

if (!function_exists('getApptPageUrl')) {
    function getApptPageUrl($p) {
        $params = $_GET;
        $params['page'] = $p;
        return '?' . http_build_query($params);
    }
}
?>

<style>
/* ====== BFS CIRCLE EDITORIAL BLACK & WHITE APPOINTMENTS THEME ====== */
:root {
    --appt-black: #09090b;
    --appt-dark: #18181b;
    --appt-zinc: #27272a;
    --appt-muted: #71717a;
    --appt-light: #f4f4f5;
    --appt-border: #e4e4e7;
    --appt-card-bg: #ffffff;
    --appt-text: #09090b;
}

.appt-container {
    display: flex;
    flex-direction: column;
    gap: 18px;
    font-family: -apple-system, BlinkMacSystemFont, "Inter", "Segoe UI", Roboto, sans-serif;
    color: var(--appt-text);
}

/* TOP HEADER */
.appt-topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}
.appt-title-group h1 {
    font-size: 22px;
    font-weight: 800;
    font-family: 'Outfit', sans-serif;
    color: var(--appt-black);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
    letter-spacing: -0.4px;
}
.appt-title-group p {
    font-size: 13px;
    color: var(--appt-muted);
    margin: 3px 0 0;
}
.appt-title-group code {
    background: var(--appt-light);
    color: var(--appt-black);
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 12px;
    border: 1px solid var(--appt-border);
}

.appt-top-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12.5px;
    padding: 8px 14px;
    border-radius: 8px;
    background: #ffffff;
    border: 1px solid var(--appt-border);
    color: var(--appt-black);
    text-decoration: none;
    font-weight: 600;
    transition: all 0.15s ease;
}
.appt-top-btn:hover {
    background: var(--appt-black);
    color: #ffffff;
    border-color: var(--appt-black);
}

/* KPI CARDS - MONOCHROME EDITORIAL */
.appt-kpis {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
}
@media (max-width: 992px) { .appt-kpis { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 576px) { .appt-kpis { grid-template-columns: 1fr; } }

.appt-kpi {
    background: #ffffff;
    border: 1px solid var(--appt-border);
    border-radius: 12px;
    padding: 16px 20px;
    display: flex;
    align-items: center;
    gap: 14px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
    transition: transform 0.15s, box-shadow 0.15s, border-color 0.15s;
}
.appt-kpi:hover {
    transform: translateY(-2px);
    border-color: var(--appt-black);
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.06);
}
.appt-kpi-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    background: var(--appt-light);
    color: var(--appt-black);
    border: 1px solid var(--appt-border);
}
.appt-kpi-icon.icon-solid-black {
    background: var(--appt-black);
    color: #ffffff;
    border-color: var(--appt-black);
}
.appt-kpi-val {
    font-size: 26px;
    font-weight: 800;
    font-family: 'Outfit', sans-serif;
    color: var(--appt-black);
    line-height: 1;
}
.appt-kpi-lbl {
    font-size: 11px;
    color: var(--appt-muted);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    margin-top: 4px;
}

/* QUICK STATUS TABS - BLACK & WHITE */
.appt-quick-tabs {
    display: flex;
    gap: 8px;
    overflow-x: auto;
    padding-bottom: 2px;
}
.appt-tab-btn {
    padding: 7px 14px;
    border-radius: 20px;
    font-size: 12.5px;
    font-weight: 600;
    color: var(--appt-muted);
    background: #ffffff;
    border: 1px solid var(--appt-border);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    transition: all 0.15s ease;
    white-space: nowrap;
}
.appt-tab-btn:hover {
    color: var(--appt-black);
    border-color: var(--appt-black);
    background: var(--appt-light);
}
.appt-tab-btn.active {
    background: var(--appt-black);
    color: #ffffff;
    border-color: var(--appt-black);
    font-weight: 700;
}
.appt-tab-badge {
    padding: 1px 7px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 700;
    background: var(--appt-light);
    color: var(--appt-black);
}
.appt-tab-btn.active .appt-tab-badge {
    background: rgba(255, 255, 255, 0.22);
    color: #ffffff;
}

/* UNIFIED IN-LINE FILTER BAR - BLACK & WHITE */
.appt-filter-row {
    background: #ffffff;
    border: 1px solid var(--appt-border);
    border-radius: 12px;
    padding: 10px 14px;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: nowrap;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
    overflow-x: auto;
}
@media (max-width: 900px) {
    .appt-filter-row {
        flex-wrap: wrap;
    }
}
.appt-search-field {
    flex: 2;
    min-width: 220px;
    position: relative;
    display: flex;
    align-items: center;
}
.appt-search-field input {
    width: 100%;
    padding: 8px 12px 8px 36px;
    border: 1px solid var(--appt-border);
    border-radius: 8px;
    background: #ffffff;
    color: var(--appt-black);
    font-size: 13px;
    outline: none;
    transition: all 0.2s;
}
.appt-search-field input:focus {
    border-color: var(--appt-black);
    box-shadow: 0 0 0 2px rgba(9, 9, 11, 0.08);
}
.appt-search-field i {
    position: absolute;
    left: 11px;
    color: var(--appt-muted);
    width: 15px;
    height: 15px;
}

.appt-select-field {
    flex: 1;
    min-width: 140px;
}
.appt-select-field select {
    width: 100%;
    padding: 8px 24px 8px 10px;
    border: 1px solid var(--appt-border);
    border-radius: 8px;
    background: #ffffff;
    color: var(--appt-black);
    font-size: 12.5px;
    font-weight: 600;
    outline: none;
    cursor: pointer;
    transition: all 0.2s;
}
.appt-select-field select:hover { border-color: var(--appt-black); }
.appt-select-field select:focus {
    border-color: var(--appt-black);
    box-shadow: 0 0 0 2px rgba(9, 9, 11, 0.08);
}

.btn-filter-clear {
    padding: 8px 14px;
    border-radius: 8px;
    border: 1px solid var(--appt-border);
    background: #ffffff;
    color: var(--appt-muted);
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    transition: all 0.15s;
}
.btn-filter-clear:hover {
    background: var(--appt-black);
    color: #ffffff;
    border-color: var(--appt-black);
}

/* DATA TABLE CARD - MONOCHROME EDITORIAL */
.appt-card-wrapper {
    background: #ffffff;
    border: 1px solid var(--appt-border);
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
}
.appt-table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
}
.appt-table th {
    background: #fafafa;
    padding: 12px 16px;
    font-size: 11px;
    font-weight: 700;
    color: var(--appt-muted);
    text-transform: uppercase;
    letter-spacing: 0.7px;
    border-bottom: 1px solid var(--appt-border);
}
.appt-table td {
    padding: 14px 16px;
    font-size: 13px;
    border-bottom: 1px solid var(--appt-light);
    vertical-align: middle;
}
.appt-table tr:hover td { background: #fafafa; }
.appt-table tr:last-child td { border-bottom: none; }

/* SLOT TIME CAPSULE - HIGH CONTRAST MONOCHROME */
.slot-capsule {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 10px;
    background: var(--appt-light);
    border: 1px solid var(--appt-border);
    border-radius: 6px;
    color: var(--appt-black);
    font-weight: 700;
    font-size: 12px;
}
.slot-capsule.slot-doorstep {
    background: var(--appt-black);
    border-color: var(--appt-black);
    color: #ffffff;
}
.slot-date-text { font-weight: 800; }
.slot-time-text { font-weight: 600; opacity: 0.85; }

/* STATUS SELECT PILL - BLACK & WHITE */
.appt-status-select {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    cursor: pointer;
    outline: none;
    border: 1px solid var(--appt-border);
    transition: all 0.15s;
}
.appt-status-Scheduled {
    background: #ffffff;
    color: var(--appt-black);
    border-color: var(--appt-black);
}
.appt-status-Confirmed {
    background: var(--appt-black);
    color: #ffffff;
    border-color: var(--appt-black);
}
.appt-status-Completed {
    background: var(--appt-light);
    color: var(--appt-muted);
    border-color: var(--appt-border);
}
.appt-status-Rescheduled {
    background: #ffffff;
    color: var(--appt-black);
    border-style: dashed;
    border-color: var(--appt-black);
}
.appt-status-Cancelled {
    background: #fafafa;
    color: #a1a1aa;
    border-color: var(--appt-border);
    text-decoration: line-through;
}

/* ASSIGNED RM DROPDOWN */
.appt-rm-select {
    padding: 5px 10px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    border: 1px solid var(--appt-border);
    background: #ffffff;
    color: var(--appt-black);
    cursor: pointer;
    outline: none;
    transition: all 0.15s;
    max-width: 210px;
}
.appt-rm-select:hover, .appt-rm-select:focus {
    border-color: var(--appt-black);
}

/* ACTION BUTTONS - MONOCHROME INVERT ON HOVER */
.appt-actions-bar {
    display: flex;
    gap: 6px;
    align-items: center;
    justify-content: flex-end;
}
.btn-appt-tool {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--appt-border);
    background: #ffffff;
    color: var(--appt-black) !important;
    cursor: pointer;
    transition: all 0.15s ease;
    text-decoration: none;
}
.btn-appt-tool:hover {
    background: var(--appt-black) !important;
    border-color: var(--appt-black) !important;
    color: #ffffff !important;
    transform: translateY(-1px);
    box-shadow: 0 3px 8px rgba(0, 0, 0, 0.15);
}

/* EXECUTIVE PAGINATION & PAGE MANAGEMENT DECK (BLACK & WHITE) */
.pagination-deck {
    display: flex;
    flex-direction: row;
    justify-content: space-between;
    align-items: center;
    flex-wrap: nowrap;
    gap: 12px;
    padding: 12px 18px;
    background: #ffffff;
    border-top: 1px solid var(--appt-border);
    box-sizing: border-box;
    overflow-x: auto;
}
.pagination-info {
    display: inline-flex;
    align-items: center;
    gap: 14px;
    font-size: 12px;
    font-weight: 500;
    color: var(--appt-muted);
    white-space: nowrap;
    flex-shrink: 0;
}
.pagination-info b {
    color: var(--appt-black);
    font-weight: 800;
}
.page-size-selector {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 11.5px;
    color: var(--appt-muted);
    border-left: 1px solid var(--appt-border);
    padding-left: 14px;
    white-space: nowrap;
    flex-shrink: 0;
}
.page-size-selector select {
    height: 28px;
    padding: 0 8px;
    border: 1px solid var(--appt-border);
    border-radius: 6px;
    background: #ffffff;
    color: var(--appt-black);
    font-size: 11.5px;
    font-weight: 700;
    cursor: pointer;
    outline: none;
    transition: all 0.15s;
}
.page-size-selector select:focus, .page-size-selector select:hover {
    border-color: var(--appt-black);
}
.pagination-nav {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    white-space: nowrap;
    flex-shrink: 0;
}
.pag-btn {
    width: 28px;
    height: 28px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--appt-border);
    border-radius: 6px;
    background: #ffffff;
    color: var(--appt-black);
    font-size: 11.5px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.15s ease;
    padding: 0;
    flex-shrink: 0;
    text-decoration: none;
}
.pag-btn svg {
    width: 13px;
    height: 13px;
}
.pag-btn:hover:not(.disabled) {
    background: var(--appt-black);
    color: #ffffff;
    border-color: var(--appt-black);
    transform: translateY(-1px);
}
.pag-btn.disabled {
    opacity: 0.35;
    cursor: not-allowed;
    background: var(--appt-light);
    border-color: var(--appt-border);
    pointer-events: none;
}
.pag-numbers {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.pag-num-btn {
    min-width: 28px;
    height: 28px;
    padding: 0 7px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--appt-border);
    border-radius: 6px;
    background: #ffffff;
    color: var(--appt-black);
    font-size: 11.5px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.15s ease;
    text-decoration: none;
}
.pag-num-btn:hover {
    background: var(--appt-light);
    border-color: var(--appt-black);
}
.pag-num-btn.active {
    background: var(--appt-black);
    color: #ffffff;
    border-color: var(--appt-black);
}
.pag-ellipsis {
    padding: 0 4px;
    font-size: 12px;
    font-weight: 800;
    color: var(--appt-muted);
}
.pag-jump {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 11.5px;
    color: var(--appt-muted);
    margin-left: 8px;
    padding-left: 12px;
    border-left: 1px solid var(--appt-border);
    white-space: nowrap;
    flex-shrink: 0;
}
.pag-jump input {
    width: 44px;
    height: 26px;
    padding: 0 4px;
    text-align: center;
    border: 1px solid var(--appt-border);
    border-radius: 5px;
    background: #ffffff;
    color: var(--appt-black);
    font-size: 11.5px;
    font-weight: 700;
    outline: none;
    transition: all 0.15s;
}
.pag-jump input:focus {
    border-color: var(--appt-black);
}
</style>

<div class="appt-container">
    <!-- 1. TOP HEADER -->
    <div class="appt-topbar">
        <div class="appt-title-group">
            <h1>
                <i data-lucide="calendar" style="width:24px; height:24px; color:var(--appt-black);"></i>
                Website Appointments Desk
            </h1>
            <p>
                All consultation meetings & doorstep visit requests booked from <code>bfsfin.com/appointment</code>
            </p>
        </div>
        <div style="display:flex; gap:10px; align-items:center;">
            <a href="leads.php" class="appt-top-btn">
                <i data-lucide="target" style="width:14px; height:14px;"></i> Leads Desk
            </a>
            <a href="reminders.php" class="appt-top-btn">
                <i data-lucide="bell" style="width:14px; height:14px;"></i> Reminders
            </a>
        </div>
    </div>

    <!-- 2. HIGH-SIGNAL KPIS (BLACK & WHITE EDITORIAL) -->
    <div class="appt-kpis">
        <div class="appt-kpi">
            <div class="appt-kpi-icon icon-solid-black">
                <i data-lucide="calendar-days" style="width:22px; height:22px;"></i>
            </div>
            <div>
                <div class="appt-kpi-val"><?php echo $total_count; ?></div>
                <div class="appt-kpi-lbl">Total Bookings</div>
            </div>
        </div>
        <div class="appt-kpi">
            <div class="appt-kpi-icon">
                <i data-lucide="clock" style="width:22px; height:22px;"></i>
            </div>
            <div>
                <div class="appt-kpi-val"><?php echo $today_count; ?></div>
                <div class="appt-kpi-lbl">Today's Schedule</div>
            </div>
        </div>
        <div class="appt-kpi">
            <div class="appt-kpi-icon icon-solid-black">
                <i data-lucide="calendar-check" style="width:22px; height:22px;"></i>
            </div>
            <div>
                <div class="appt-kpi-val"><?php echo $scheduled_count; ?></div>
                <div class="appt-kpi-lbl">Active & Pending</div>
            </div>
        </div>
        <div class="appt-kpi">
            <div class="appt-kpi-icon">
                <i data-lucide="check-check" style="width:22px; height:22px;"></i>
            </div>
            <div>
                <div class="appt-kpi-val"><?php echo $completed_count; ?></div>
                <div class="appt-kpi-lbl">Completed</div>
            </div>
        </div>
    </div>

    <!-- 3. QUICK STATUS TABS (BLACK & WHITE) -->
    <div class="appt-quick-tabs">
        <a href="appointments.php" class="appt-tab-btn <?php echo ($filter_status === 'all' && $filter_date === 'all') ? 'active' : ''; ?>">
            <span>All Bookings</span>
            <span class="appt-tab-badge"><?php echo $total_count; ?></span>
        </a>
        <a href="appointments.php?date_range=today" class="appt-tab-btn <?php echo ($filter_date === 'today') ? 'active' : ''; ?>">
            <span>Today's</span>
            <span class="appt-tab-badge"><?php echo $today_count; ?></span>
        </a>
        <a href="appointments.php?date_range=tomorrow" class="appt-tab-btn <?php echo ($filter_date === 'tomorrow') ? 'active' : ''; ?>">
            <span>Tomorrow</span>
        </a>
        <a href="appointments.php?date_range=upcoming" class="appt-tab-btn <?php echo ($filter_date === 'upcoming') ? 'active' : ''; ?>">
            <span>Active / Pending</span>
            <span class="appt-tab-badge"><?php echo $scheduled_count; ?></span>
        </a>
        <a href="appointments.php?status=Completed" class="appt-tab-btn <?php echo ($filter_status === 'Completed') ? 'active' : ''; ?>">
            <span>Completed</span>
            <span class="appt-tab-badge"><?php echo $completed_count; ?></span>
        </a>
    </div>

    <!-- 4. PERFECT IN-LINE FILTER BAR (BLACK & WHITE) -->
    <form method="GET" class="appt-filter-row">
        <!-- Search Input -->
        <div class="appt-search-field">
            <i data-lucide="search"></i>
            <input type="text" name="search" placeholder="Search applicant, phone, email, city..." value="<?php echo htmlspecialchars($search); ?>">
        </div>

        <!-- Date Range Filter -->
        <div class="appt-select-field">
            <select name="date_range" onchange="this.form.submit()">
                <option value="all" <?php echo $filter_date === 'all' ? 'selected' : ''; ?>>All Dates</option>
                <option value="today" <?php echo $filter_date === 'today' ? 'selected' : ''; ?>>Today</option>
                <option value="tomorrow" <?php echo $filter_date === 'tomorrow' ? 'selected' : ''; ?>>Tomorrow</option>
                <option value="upcoming" <?php echo $filter_date === 'upcoming' ? 'selected' : ''; ?>>Active Upcoming</option>
                <option value="completed" <?php echo $filter_date === 'completed' ? 'selected' : ''; ?>>Past / Completed</option>
            </select>
        </div>

        <!-- Status Filter -->
        <div class="appt-select-field">
            <select name="status" onchange="this.form.submit()">
                <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                <option value="Scheduled" <?php echo $filter_status === 'Scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                <option value="Confirmed" <?php echo $filter_status === 'Confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                <option value="Completed" <?php echo $filter_status === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                <option value="Rescheduled" <?php echo $filter_status === 'Rescheduled' ? 'selected' : ''; ?>>Rescheduled</option>
                <option value="Cancelled" <?php echo $filter_status === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
            </select>
        </div>

        <!-- Product Filter -->
        <div class="appt-select-field">
            <select name="product" onchange="this.form.submit()">
                <option value="all" <?php echo $filter_prod === 'all' ? 'selected' : ''; ?>>All Products</option>
                <option value="Home Loan" <?php echo $filter_prod === 'Home Loan' ? 'selected' : ''; ?>>Home Loan</option>
                <option value="LAP" <?php echo $filter_prod === 'LAP' ? 'selected' : ''; ?>>Loan Against Property</option>
                <option value="Business" <?php echo $filter_prod === 'Business' ? 'selected' : ''; ?>>Business Loan</option>
                <option value="Doctor" <?php echo $filter_prod === 'Doctor' ? 'selected' : ''; ?>>Doctor Loan</option>
                <option value="Insurance" <?php echo $filter_prod === 'Insurance' ? 'selected' : ''; ?>>Insurance</option>
            </select>
        </div>

        <!-- In-Office RM Filter -->
        <div class="appt-select-field">
            <select name="rm" onchange="this.form.submit()">
                <option value="all" <?php echo $filter_rm === 'all' ? 'selected' : ''; ?>>All In-Office RMs</option>
                <option value="unassigned" <?php echo $filter_rm === 'unassigned' ? 'selected' : ''; ?>>Unassigned Only</option>
                <?php foreach ($staff_list as $st): ?>
                <option value="<?php echo htmlspecialchars($st['username']); ?>" <?php echo $filter_rm === $st['username'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($st['display_name'] . ($st['role'] === 'RM' ? ' (In-Office RM)' : ' (' . $st['role'] . ')')); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Reset Button -->
        <?php if (!empty($search) || $filter_status !== 'all' || $filter_date !== 'all' || $filter_prod !== 'all' || $filter_rm !== 'all'): ?>
        <a href="appointments.php" class="btn-filter-clear">
            <i data-lucide="x" style="width:13px; height:13px;"></i> Clear
        </a>
        <?php endif; ?>
    </form>

    <!-- 5. APPOINTMENTS TABLE (BLACK & WHITE EDITORIAL) -->
    <div class="appt-card-wrapper">
        <?php if (empty($appointments)): ?>
        <div style="padding:48px 20px; text-align:center; color:var(--appt-muted);">
            <i data-lucide="calendar-x" style="width:44px; height:44px; stroke-width:1.5; margin:0 auto 10px; display:block; opacity:0.35;"></i>
            <h3 style="font-size:16px; font-weight:700; color:var(--appt-black); margin:0 0 5px;">No Appointments Found</h3>
            <p style="font-size:13px; margin:0;">No bookings matched your filter selection. Try adjusting the inline filters above.</p>
        </div>
        <?php else: ?>
        <div style="overflow-x:auto;">
            <table class="appt-table">
                <thead>
                    <tr>
                        <th style="min-width:160px;">Slot & Date</th>
                        <th style="min-width:200px;">Applicant Profile</th>
                        <th style="min-width:180px;">Product / Requirement</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Assigned RM (In-Office)</th>
                        <th style="text-align:right; min-width:140px;">Quick Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($appointments as $app): 
                        $raw_phone = preg_replace('/[^0-9]/', '', $app['phone']);
                        $wa_msg = urlencode("Hi " . $app['name'] . ", this is regarding your consultation appointment with Bhardwaj Financial Services scheduled for " . ($app['slot_date'] ?: 'your booking') . " (" . ($app['slot_time'] ?: '') . "). Please let us know if this time works for you!");
                        $is_home_visit = (stripos($app['source'] ?? '', 'Home Visit') !== false || stripos($app['message'] ?? '', 'Doorstep') !== false);
                        $gcal_url = get_google_calendar_url($app);
                    ?>
                    <tr>
                        <!-- Slot & Date -->
                        <td>
                            <div class="slot-capsule <?php echo $is_home_visit ? 'slot-doorstep' : ''; ?>">
                                <i data-lucide="<?php echo $is_home_visit ? 'home' : 'clock'; ?>" style="width:12px; height:12px;"></i>
                                <span class="slot-date-text"><?php echo htmlspecialchars($app['slot_date'] ?: 'TBD'); ?></span>
                                <?php if (!empty($app['slot_time'])): ?>
                                <span class="slot-time-text">• <?php echo htmlspecialchars($app['slot_time']); ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if ($is_home_visit): ?>
                            <div style="font-size:11px; font-weight:800; color:var(--appt-black); margin-top:3px;">
                                • Doorstep Visit
                            </div>
                            <?php endif; ?>
                            <div style="font-size:10.5px; color:var(--appt-muted); margin-top:3px;">
                                Booked: <?php echo date('d M, h:i A', strtotime($app['created_at'])); ?>
                            </div>
                        </td>

                        <!-- Applicant Profile -->
                        <td>
                            <div style="font-weight:700; color:var(--appt-black); font-size:14px;">
                                <?php echo htmlspecialchars($app['name']); ?>
                            </div>
                            <div style="font-size:12px; color:var(--appt-muted); display:flex; gap:6px; align-items:center; margin-top:2px;">
                                <span><i data-lucide="phone" style="width:11px; height:11px;"></i> <?php echo htmlspecialchars($app['phone']); ?></span>
                                <?php if (!empty($app['email']) && !str_contains($app['email'], 'no-email')): ?>
                                <span>• <?php echo htmlspecialchars($app['email']); ?></span>
                                <?php endif; ?>
                            </div>
                        </td>

                        <!-- Product & Purpose -->
                        <td>
                            <div style="font-weight:700; color:var(--appt-black);">
                                <?php echo htmlspecialchars($app['product_type'] ?: 'Finance'); ?>
                            </div>
                            <div style="font-size:11.5px; color:var(--appt-muted); margin-top:2px;">
                                <?php echo htmlspecialchars($app['sub_type'] ?: 'Consultation'); ?>
                            </div>
                            <?php if (!empty($app['message']) && stripos($app['message'], 'DPDP') === false): ?>
                            <div style="font-size:11px; color:var(--appt-muted); margin-top:3px; max-width:260px; line-height:1.3; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?php echo htmlspecialchars($app['message']); ?>">
                                "<?php echo htmlspecialchars($app['message']); ?>"
                            </div>
                            <?php endif; ?>
                        </td>

                        <!-- Location -->
                        <td>
                            <span style="font-weight:600; color:var(--appt-black);">
                                <?php echo htmlspecialchars($app['city'] ?: 'Agra'); ?>
                            </span>
                        </td>

                        <!-- Status (Black & White Select) -->
                        <td>
                            <select onchange="updateApptStatus(<?php echo $app['id']; ?>, this.value)" class="appt-status-select appt-status-<?php echo htmlspecialchars($app['status']); ?>">
                                <option value="Scheduled" <?php echo $app['status'] === 'Scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                <option value="Confirmed" <?php echo $app['status'] === 'Confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="Completed" <?php echo $app['status'] === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="Rescheduled" <?php echo $app['status'] === 'Rescheduled' ? 'selected' : ''; ?>>Rescheduled</option>
                                <option value="Cancelled" <?php echo $app['status'] === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </td>

                        <!-- Assigned RM (Strictly In-Office Staff Only) -->
                        <td>
                            <select onchange="assignApptRM(<?php echo $app['id']; ?>, this.value)" class="appt-rm-select">
                                <option value="">Unassigned</option>
                                <?php foreach ($staff_list as $st): ?>
                                <option value="<?php echo htmlspecialchars($st['username']); ?>" <?php echo $app['assigned_to'] === $st['username'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($st['display_name'] . ($st['role'] === 'RM' ? ' (In-Office RM)' : ' (' . $st['role'] . ')')); ?>
                                </option>
                                <?php endforeach; ?>
                                <?php if (!empty($app['assigned_to']) && !in_array($app['assigned_to'], array_column($staff_list, 'username'))): ?>
                                <option value="<?php echo htmlspecialchars($app['assigned_to']); ?>" selected>
                                    <?php echo htmlspecialchars($app['assigned_to']); ?>
                                </option>
                                <?php endif; ?>
                            </select>
                        </td>

                        <!-- Quick Actions (Monochrome Invert on Hover) -->
                        <td style="text-align:right;">
                            <div class="appt-actions-bar">
                                <!-- 1. Add to Google Calendar -->
                                <a href="<?php echo $gcal_url; ?>" target="_blank" class="btn-appt-tool" title="Sync / Add to Google Calendar">
                                    <i data-lucide="calendar-plus" style="width:14px; height:14px;"></i>
                                </a>

                                <?php if (!empty($raw_phone)): ?>
                                <!-- 2. Direct Call -->
                                <a href="tel:<?php echo $raw_phone; ?>" class="btn-appt-tool" title="Call Applicant">
                                    <i data-lucide="phone" style="width:13px; height:13px;"></i>
                                </a>
                                <!-- 3. Direct WhatsApp -->
                                <a href="https://wa.me/91<?php echo substr($raw_phone, -10); ?>?text=<?php echo $wa_msg; ?>" target="_blank" class="btn-appt-tool" title="WhatsApp Appointment Confirmation">
                                    <i data-lucide="message-circle" style="width:14px; height:14px;"></i>
                                </a>
                                <?php endif; ?>

                                <?php if (!empty($app['lead_id'])): ?>
                                <!-- 4. CRM Lead Profile -->
                                <a href="leads.php?edit_lead=<?php echo (int)$app['lead_id']; ?>" class="btn-appt-tool" title="Open Full Lead File">
                                    <i data-lucide="external-link" style="width:13px; height:13px;"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Executive Pagination & Page Management Deck (Appointments) -->
        <div class="pagination-deck">
            <!-- Left Info & Per Page Selector -->
            <div class="pagination-info">
                <span>Showing <b><?php echo $start_item; ?></b> to <b><?php echo $end_item; ?></b> of <b><?php echo $total_items; ?></b> appointments</span>
                <div class="page-size-selector">
                    <span>Per page:</span>
                    <select id="apptPerPageSelect" onchange="changeApptPerPage(this.value)">
                        <option value="10" <?php echo $per_page_param==='10'?'selected':''; ?>>10</option>
                        <option value="15" <?php echo $per_page_param==='15'?'selected':''; ?>>15</option>
                        <option value="25" <?php echo $per_page_param==='25'?'selected':''; ?>>25</option>
                        <option value="50" <?php echo $per_page_param==='50'?'selected':''; ?>>50</option>
                        <option value="100" <?php echo $per_page_param==='100'?'selected':''; ?>>100</option>
                        <option value="all" <?php echo $per_page_param==='all'?'selected':''; ?>>All</option>
                    </select>
                </div>
            </div>

            <!-- Right Controls -->
            <div class="pagination-nav">
                <?php
                $is_first = ($page <= 1);
                $is_last = ($page >= $total_pages);
                ?>
                <a href="<?php echo $is_first ? 'javascript:void(0)' : getApptPageUrl(1); ?>" 
                   class="pag-btn <?php echo $is_first ? 'disabled' : ''; ?>" 
                   title="First Page">
                    <i data-lucide="chevrons-left"></i>
                </a>
                <a href="<?php echo $is_first ? 'javascript:void(0)' : getApptPageUrl($page - 1); ?>" 
                   class="pag-btn <?php echo $is_first ? 'disabled' : ''; ?>" 
                   title="Previous Page">
                    <i data-lucide="chevron-left"></i>
                </a>

                <!-- Numbered Page Buttons -->
                <div class="pag-numbers">
                    <?php
                    $range = 2;
                    $start_p = max(1, $page - $range);
                    $end_p = min($total_pages, $page + $range);

                    if ($start_p > 1): ?>
                        <a href="<?php echo getApptPageUrl(1); ?>" class="pag-num-btn">1</a>
                        <?php if ($start_p > 2): ?><span class="pag-ellipsis">…</span><?php endif; ?>
                    <?php endif;

                    for ($i = $start_p; $i <= $end_p; $i++): ?>
                        <a href="<?php echo getApptPageUrl($i); ?>" class="pag-num-btn <?php echo ($i === $page) ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor;

                    if ($end_p < $total_pages): ?>
                        <?php if ($end_p < $total_pages - 1): ?><span class="pag-ellipsis">…</span><?php endif; ?>
                        <a href="<?php echo getApptPageUrl($total_pages); ?>" class="pag-num-btn"><?php echo $total_pages; ?></a>
                    <?php endif; ?>
                </div>

                <a href="<?php echo $is_last ? 'javascript:void(0)' : getApptPageUrl($page + 1); ?>" 
                   class="pag-btn <?php echo $is_last ? 'disabled' : ''; ?>" 
                   title="Next Page">
                    <i data-lucide="chevron-right"></i>
                </a>
                <a href="<?php echo $is_last ? 'javascript:void(0)' : getApptPageUrl($total_pages); ?>" 
                   class="pag-btn <?php echo $is_last ? 'disabled' : ''; ?>" 
                   title="Last Page">
                    <i data-lucide="chevrons-right"></i>
                </a>

                <!-- Jump to Page Input -->
                <div class="pag-jump">
                    <span>Page</span>
                    <input type="number" 
                           min="1" 
                           max="<?php echo $total_pages; ?>" 
                           value="<?php echo $page; ?>" 
                           title="Press Enter to jump" 
                           onkeydown="if(event.key==='Enter') jumpToApptPage(this.value)">
                    <span>of <b><?php echo $total_pages; ?></b></span>
                </div>
            </div>
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
                if (data.email_sent) {
                    alert('✓ Slot status updated to ' + status + ' and confirmation email dispatched to client.');
                }
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
                location.reload();
            } else {
                alert('Failed to assign RM: ' + (data.error || 'Unknown error'));
            }
        }).catch(() => alert('Network error.'));
}

function changeApptPerPage(val) {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', val);
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
}

function jumpToApptPage(val) {
    let p = parseInt(val, 10);
    if (isNaN(p) || p < 1) p = 1;
    const url = new URL(window.location.href);
    url.searchParams.set('page', p);
    window.location.href = url.toString();
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
