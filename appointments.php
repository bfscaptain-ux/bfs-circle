<?php
require_once __DIR__ . '/config.php';
require_role(['Admin', 'RM', 'Staff']);

// Helper to generate Rich Google Calendar URL with Attendees & Executive Briefing
if (!function_exists('get_google_calendar_url')) {
    function get_google_calendar_url($app, $assigned_officer = null) {
        try {
        $title = urlencode("BFS Consultation: " . $app['name'] . " (" . ($app['sub_type'] ?: 'Loan Advisory') . ")");
        
        // Parse Date and Time
        $date_str = $app['slot_date'] ?: date('Y-m-d');
        $time_str = $app['slot_time'] ?: '11:00 AM';
        
        $time_parts = preg_split('/[-–]/u', (string)$time_str);
        $clean_time = trim($time_parts[0] ?? '11:00 AM');
        $start_ts = strtotime($date_str . ' ' . $clean_time);
        if ($start_ts === false || $start_ts < 0) {
            $start_ts = strtotime('+1 day 11:00 AM');
        }
        $end_ts = $start_ts + (60 * 60); // 1 hour duration
        
        $dates = gmdate('Ymd\THis\Z', $start_ts) . '/' . gmdate('Ymd\THis\Z', $end_ts);
        $location = urlencode(($app['city'] ?: 'Agra') . ', India');

        $rm_name = $assigned_officer['display_name'] ?? ($app['assigned_to'] ?: 'Unassigned');
        $rm_email = $assigned_officer['official_email'] ?? '';
        $rm_phone = $assigned_officer['mobile'] ?? '';

        $details_lines = [
            "🏢 BHARDWAJ FINANCIAL SERVICES (BFS CIRCLE)",
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━",
            "👤 APPLICANT DETAILS:",
            "• Name: " . $app['name'],
            "• Phone: " . $app['phone'],
            "• Email: " . ($app['email'] ?: 'N/A'),
            "• City: " . ($app['city'] ?: 'Agra'),
            "",
            "💼 REQUIREMENT & PRODUCT:",
            "• Product: " . ($app['product_type'] ?: 'Finance') . " - " . ($app['sub_type'] ?: 'Consultation'),
            "• Notes: " . ($app['message'] ?: 'Direct booking from website'),
            "",
            "👔 ASSIGNED IN-OFFICE RM:",
            "• Officer: " . $rm_name,
            (!empty($rm_phone) ? "• RM Mobile: " . $rm_phone : ""),
            (!empty($rm_email) ? "• RM Email: " . $rm_email : ""),
            "",
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━",
            "🚀 Verified by BFS Circle CRM Appointment System"
        ];
        $details = urlencode(implode("\n", array_filter($details_lines)));

        // Automatically invite Applicant & Assigned RM as Calendar Attendees
        $attendees = [];
        if (!empty($app['email']) && filter_var($app['email'], FILTER_VALIDATE_EMAIL) && !str_contains($app['email'], 'no-email')) {
            $attendees[] = $app['email'];
        }
        if (!empty($rm_email) && filter_var($rm_email, FILTER_VALIDATE_EMAIL)) {
            $attendees[] = $rm_email;
        }
        $add_param = !empty($attendees) ? "&add=" . urlencode(implode(',', $attendees)) : "";

        return "https://calendar.google.com/calendar/render?action=TEMPLATE&text={$title}&dates={$dates}&details={$details}&location={$location}{$add_param}";
        } catch (Throwable $e) {
            return '#';
        }
    }
}

// 1. Single Appointment .ICS File Download Action
if (isset($_GET['action']) && $_GET['action'] === 'download_ics') {
    $appt_id = (int)($_GET['id'] ?? 0);
    $stmt = $db->prepare("SELECT * FROM website_appointments WHERE id = ?");
    $stmt->execute([$appt_id]);
    $app = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($app) {
        $date_str = $app['slot_date'] ?: date('Y-m-d');
        $time_str = $app['slot_time'] ?: '11:00 AM';
        $time_parts = preg_split('/[-–]/u', (string)$time_str);
        $clean_time = trim($time_parts[0] ?? '11:00 AM');
        $start_ts = strtotime($date_str . ' ' . $clean_time);
        if ($start_ts === false || $start_ts < 0) $start_ts = strtotime('+1 day 11:00 AM');
        $end_ts = $start_ts + 3600;

        $dtstart = gmdate('Ymd\THis\Z', $start_ts);
        $dtend = gmdate('Ymd\THis\Z', $end_ts);
        $dtstamp = gmdate('Ymd\THis\Z');
        $uid = 'bfs-appt-' . $app['id'] . '-' . $start_ts . '@bfsfinancialservices.com';
        $summary = "BFS Consultation: " . str_replace(["\r", "\n"], ' ', $app['name']) . " (" . ($app['sub_type'] ?: 'Loan Advisory') . ")";
        $location = str_replace(["\r", "\n"], ' ', ($app['city'] ?: 'Agra') . ', India');
        $desc = "Applicant: {$app['name']}\\nPhone: {$app['phone']}\\nEmail: {$app['email']}\\nProduct: {$app['product_type']} - {$app['sub_type']}\\nAssigned RM: {$app['assigned_to']}\\nNotes: {$app['message']}";

        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="BFS_Appointment_' . $app['id'] . '.ics"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        echo "BEGIN:VCALENDAR\r\n";
        echo "VERSION:2.0\r\n";
        echo "PRODID:-//BFS Financial Services//Appointment System//EN\r\n";
        echo "CALSCALE:GREGORIAN\r\n";
        echo "METHOD:PUBLISH\r\n";
        echo "BEGIN:VEVENT\r\n";
        echo "UID:{$uid}\r\n";
        echo "DTSTAMP:{$dtstamp}\r\n";
        echo "DTSTART:{$dtstart}\r\n";
        echo "DTEND:{$dtend}\r\n";
        echo "SUMMARY:{$summary}\r\n";
        echo "DESCRIPTION:{$desc}\r\n";
        echo "LOCATION:{$location}\r\n";
        echo "STATUS:CONFIRMED\r\n";
        echo "END:VEVENT\r\n";
        echo "END:VCALENDAR\r\n";
        exit;
    }
}

// 2. Master .ICS Calendar Feed / Export for All Active Appointments
if (isset($_GET['action']) && ($_GET['action'] === 'download_all_ics' || $_GET['action'] === 'calendar_feed')) {
    $stmt = $db->query("SELECT * FROM website_appointments WHERE status IN ('Scheduled', 'Confirmed') ORDER BY id DESC LIMIT 200");
    $appts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/calendar; charset=utf-8');
    if ($_GET['action'] === 'download_all_ics') {
        header('Content-Disposition: attachment; filename="BFS_All_Active_Appointments.ics"');
    }
    header('Cache-Control: no-cache, no-store, must-revalidate');
    echo "BEGIN:VCALENDAR\r\n";
    echo "VERSION:2.0\r\n";
    echo "PRODID:-//BFS Financial Services//Master Appointments Feed//EN\r\n";
    echo "CALSCALE:GREGORIAN\r\n";
    echo "X-WR-CALNAME:BFS Active Appointments\r\n";
    echo "X-WR-TIMEZONE:Asia/Kolkata\r\n";
    echo "METHOD:PUBLISH\r\n";
    foreach ($appts as $app) {
        $date_str = $app['slot_date'] ?: date('Y-m-d');
        $time_str = $app['slot_time'] ?: '11:00 AM';
        $time_parts = preg_split('/[-–]/u', (string)$time_str);
        $clean_time = trim($time_parts[0] ?? '11:00 AM');
        $start_ts = strtotime($date_str . ' ' . $clean_time);
        if ($start_ts === false || $start_ts < 0) $start_ts = strtotime('+1 day 11:00 AM');
        $end_ts = $start_ts + 3600;

        $dtstart = gmdate('Ymd\THis\Z', $start_ts);
        $dtend = gmdate('Ymd\THis\Z', $end_ts);
        $dtstamp = gmdate('Ymd\THis\Z');
        $uid = 'bfs-appt-' . $app['id'] . '-' . $start_ts . '@bfsfinancialservices.com';
        $summary = "BFS: " . str_replace(["\r", "\n"], ' ', $app['name']) . " (" . ($app['sub_type'] ?: 'Consultation') . ")";
        $location = str_replace(["\r", "\n"], ' ', ($app['city'] ?: 'Agra') . ', India');
        $desc = "Client: {$app['name']}\\nPhone: {$app['phone']}\\nProduct: {$app['product_type']}\\nAssigned RM: {$app['assigned_to']}";

        echo "BEGIN:VEVENT\r\n";
        echo "UID:{$uid}\r\n";
        echo "DTSTAMP:{$dtstamp}\r\n";
        echo "DTSTART:{$dtstart}\r\n";
        echo "DTEND:{$dtend}\r\n";
        echo "SUMMARY:{$summary}\r\n";
        echo "DESCRIPTION:{$desc}\r\n";
        echo "LOCATION:{$location}\r\n";
        echo "STATUS:CONFIRMED\r\n";
        echo "END:VEVENT\r\n";
    }
    echo "END:VCALENDAR\r\n";
    exit;
}

// Handle AJAX Status / Assignment updates FIRST before any HTML output
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action'])) {
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
            $officer_info = null;
            // Strictly enforce: only legitimate in-office RM / Staff / Admin can be assigned
            if (!empty($rm)) {
                $chk_rm = $db->prepare("
                    SELECT u.id, u.username, 
                           COALESCE(NULLIF(e.full_name, ''), NULLIF(u.full_name, ''), u.name, u.username) AS display_name, 
                           u.role, 
                           COALESCE(e.designation, u.role) AS designation,
                           COALESCE(e.official_email, u.email, '') AS official_email,
                           COALESCE(e.mobile, u.mobile, '') AS mobile
                    FROM users u 
                    LEFT JOIN employees e ON (e.user_id = u.id OR e.official_email = u.username)
                    WHERE u.username = ? AND u.is_active = 1 AND u.role IN ('RM', 'Staff', 'Admin') 
                      AND u.role NOT IN ('Partner', 'Agent', 'Builder', 'CA', 'DSA')
                ");
                $chk_rm->execute([$rm]);
                $officer_info = $chk_rm->fetch(PDO::FETCH_ASSOC);
                if (!$officer_info) {
                    echo json_encode(['success' => false, 'error' => 'Only authorized In-Office Relationship Managers can be assigned.']);
                    exit;
                }
            }
            $stmt = $db->prepare("UPDATE website_appointments SET assigned_to = ?, updated_at = datetime('now', 'localtime') WHERE id = ?");
            $stmt->execute([$rm, $appt_id]);

            // Re-fetch updated appointment to generate updated Google Calendar link
            $chk_app = $db->prepare("SELECT * FROM website_appointments WHERE id = ?");
            $chk_app->execute([$appt_id]);
            $updated_app = $chk_app->fetch(PDO::FETCH_ASSOC);
            $new_gcal = get_google_calendar_url($updated_app, $officer_info);

            echo json_encode([
                'success' => true,
                'assigned_to' => $rm,
                'display_name' => $officer_info ? $officer_info['display_name'] : 'Unassigned',
                'role' => $officer_info ? ($officer_info['role'] === 'RM' ? 'In-Office RM' : $officer_info['role']) : '',
                'avatar' => $officer_info ? strtoupper(substr($officer_info['display_name'], 0, 1)) : '',
                'new_gcal_url' => $new_gcal
            ]);
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

// Fetch ONLY legitimate In-Office Relationship Managers & Staff for assignment (with rich officer dossiers)
try {
    $staff_list = $db->query("
    SELECT u.id, u.username, 
           COALESCE(NULLIF(e.full_name, ''), NULLIF(u.full_name, ''), u.name, u.username) AS display_name, 
           u.role, 
           COALESCE(e.designation, u.role) AS designation,
           COALESCE(e.department, 'In-Office Operations') AS department,
           COALESCE(e.emp_id, '') AS emp_id,
           COALESCE(e.photo_path, '') AS photo_path,
           COALESCE(e.official_email, u.email, '') AS official_email,
           COALESCE(e.mobile, u.mobile, '') AS mobile,
           (SELECT COUNT(*) FROM website_appointments wa WHERE wa.assigned_to = u.username AND wa.status IN ('Scheduled', 'Confirmed')) AS active_workload
    FROM users u
    LEFT JOIN employees e ON (e.user_id = u.id OR e.official_email = u.username)
    WHERE u.is_active = 1
      AND u.role IN ('RM', 'Staff', 'Admin')
      AND u.role NOT IN ('Partner', 'Agent', 'Builder', 'CA', 'DSA')
      AND u.username NOT LIKE 'apex_dsa_%'
      AND u.username NOT LIKE 'BFS-FAM-%'
    ORDER BY (CASE WHEN u.role = 'RM' THEN 1 WHEN u.role = 'Staff' THEN 2 ELSE 3 END), display_name ASC
")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $staff_list = [];
}

// Map for fast O(1) lookups by username
$staff_map = [];
foreach ($staff_list as $st) {
    $staff_map[$st['username']] = $st;
}

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
try {
    $count_stmt = $db->prepare("SELECT COUNT(*) FROM website_appointments WHERE 1=1" . $where_sql);
    $count_stmt->execute($params);
    $total_items = (int)$count_stmt->fetchColumn();
} catch (Throwable $e) {
    $total_items = 0;
}

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
try {
    $total_count = (int)$db->query("SELECT COUNT(*) FROM website_appointments")->fetchColumn();
    $scheduled_count = (int)$db->query("SELECT COUNT(*) FROM website_appointments WHERE status IN ('Scheduled', 'Confirmed')")->fetchColumn();
    $today_count = (int)$db->query("SELECT COUNT(*) FROM website_appointments WHERE (slot_date = date('now', 'localtime') OR slot_date LIKE '%" . date('Y-m-d') . "%' OR slot_date LIKE '%" . date('d M') . "%')")->fetchColumn();
    $completed_count = (int)$db->query("SELECT COUNT(*) FROM website_appointments WHERE status = 'Completed'")->fetchColumn();
} catch (Throwable $e) {
    $total_count = 0;
    $scheduled_count = 0;
    $today_count = 0;
    $completed_count = 0;
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

/* SEARCHABLE IN-OFFICE RM PICKER BUTTONS */
.btn-rm-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 12px;
    background: #ffffff;
    border: 1px solid var(--appt-border);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    text-align: left;
    width: 100%;
    max-width: 220px;
    box-sizing: border-box;
    font-family: inherit;
}
.btn-rm-badge:hover {
    border-color: var(--appt-black);
    background: #f8fafc;
    box-shadow: 0 3px 8px rgba(15, 23, 42, 0.08);
    transform: translateY(-1px);
}
.rm-badge-avatar {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: var(--appt-black);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 800;
    font-family: 'Outfit', sans-serif;
    flex-shrink: 0;
}
.rm-badge-meta {
    display: flex;
    flex-direction: column;
    overflow: hidden;
    min-width: 0;
    flex: 1;
}
.rm-badge-name {
    font-size: 12.5px;
    font-weight: 800;
    color: var(--appt-black);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.2;
}
.rm-badge-role {
    font-size: 10.5px;
    font-weight: 600;
    color: var(--appt-muted);
    line-height: 1.2;
}
.btn-rm-unassigned {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 7px 12px;
    background: #f8fafc;
    border: 1px dashed var(--appt-border-dark);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    color: var(--appt-muted);
    font-size: 12px;
    font-weight: 700;
    width: 100%;
    max-width: 175px;
    font-family: inherit;
    box-sizing: border-box;
}
.btn-rm-unassigned:hover {
    border-color: var(--appt-black);
    color: var(--appt-black);
    background: #ffffff;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.06);
}

/* TOP GOOGLE CALENDAR HUB BUTTON */
.btn-gcal-sync-hub {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 7px 14px;
    background: #ffffff;
    border: 1px solid var(--appt-black);
    border-radius: 8px;
    color: var(--appt-black);
    font-size: 12px;
    font-weight: 800;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
}
.btn-gcal-sync-hub:hover {
    background: var(--appt-black);
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.15);
}

/* EXECUTIVE BFS MODAL (BLACK & WHITE THEME) */
.bfs-modal-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(15, 23, 42, 0.65);
    backdrop-filter: blur(4px);
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    box-sizing: border-box;
}
.bfs-modal-container {
    background: #ffffff;
    border-radius: 14px;
    width: 100%;
    max-width: 580px;
    box-shadow: 0 25px 60px -15px rgba(15, 23, 42, 0.3);
    border: 1px solid var(--appt-border);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    max-height: 90vh;
}
.bfs-modal-header {
    padding: 16px 20px;
    background: #ffffff;
    border-bottom: 1px solid var(--appt-border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.bfs-modal-close {
    background: none;
    border: none;
    font-size: 22px;
    color: var(--appt-muted);
    cursor: pointer;
    line-height: 1;
    padding: 4px;
    transition: color 0.15s;
}
.bfs-modal-close:hover {
    color: var(--appt-black);
}
.rm-search-box {
    display: flex;
    align-items: center;
    gap: 10px;
    background: #ffffff;
    border: 1px solid var(--appt-border-dark);
    border-radius: 8px;
    padding: 0 12px;
    height: 38px;
    margin-bottom: 10px;
}
.rm-search-box input {
    border: none;
    outline: none;
    background: transparent;
    font-size: 13px;
    color: var(--appt-black);
    width: 100%;
    font-family: inherit;
}
.rm-filter-tabs {
    display: flex;
    gap: 6px;
    overflow-x: auto;
}
.rm-tab-btn {
    padding: 5px 12px;
    font-size: 11px;
    font-weight: 700;
    border-radius: 6px;
    border: 1px solid var(--appt-border-dark);
    background: #ffffff;
    color: var(--appt-muted);
    cursor: pointer;
    transition: all 0.15s ease;
    white-space: nowrap;
}
.rm-tab-btn:hover {
    color: var(--appt-black);
    border-color: var(--appt-black);
}
.rm-tab-btn.active {
    background: var(--appt-black) !important;
    color: #ffffff !important;
    border-color: var(--appt-black) !important;
}
.btn-rm-unassign-act {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    background: #ffffff;
    border: 1px solid var(--appt-border-dark);
    color: #dc2626;
    font-size: 11.5px;
    font-weight: 700;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.15s;
}
.btn-rm-unassign-act:hover {
    background: #fee2e2;
    border-color: #ef4444;
}
.rm-officers-deck {
    overflow-y: auto;
    max-height: 380px;
    padding: 8px 12px;
}
.rm-officer-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.15s ease;
    border: 1px solid transparent;
    margin-bottom: 4px;
}
.rm-officer-row:hover {
    background: #f8fafc;
    border-color: var(--appt-border-dark);
}
.rm-officer-row.is-current {
    background: #f1f5f9;
    border-color: var(--appt-black);
}
.rm-row-avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: var(--appt-black);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: 800;
    font-family: 'Outfit', sans-serif;
    flex-shrink: 0;
    overflow: hidden;
}
.rm-row-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.rm-row-content {
    flex: 1;
    min-width: 0;
}
.rm-row-name-line {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 2px;
}
.rm-row-name {
    font-size: 13.5px;
    font-weight: 800;
    color: var(--appt-black);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.badge-role-pill {
    font-size: 9.5px;
    font-weight: 800;
    padding: 2px 7px;
    border-radius: 12px;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}
.badge-role-pill.rm {
    background: var(--appt-black);
    color: #ffffff;
}
.badge-role-pill.staff {
    background: #ffffff;
    color: var(--appt-black);
    border: 1px solid var(--appt-border-dark);
}
.badge-role-pill.admin {
    background: #0f172a;
    color: #ffffff;
}
.rm-row-empid {
    font-size: 10px;
    color: var(--appt-muted);
    font-family: 'Outfit', sans-serif;
    font-weight: 700;
    background: #f1f5f9;
    padding: 1px 6px;
    border-radius: 4px;
}
.rm-row-subline {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    color: var(--appt-muted);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.rm-assign-pill {
    padding: 5px 12px;
    font-size: 11px;
    font-weight: 800;
    background: var(--appt-black);
    color: #ffffff;
    border-radius: 6px;
    white-space: nowrap;
    transition: all 0.15s;
}
.rm-officer-row:hover .rm-assign-pill {
    background: #1e293b;
    transform: translateX(2px);
}
.bfs-modal-footer {
    padding: 12px 20px;
    background: #f8fafc;
    border-top: 1px solid var(--appt-border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.btn-modal-cancel {
    padding: 6px 14px;
    border: 1px solid var(--appt-border-dark);
    background: #ffffff;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 700;
    color: var(--appt-black);
    cursor: pointer;
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

    <!-- 3. QUICK STATUS TABS & GOOGLE CALENDAR SYNC HUB -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:10px;">
        <div class="appt-quick-tabs" style="margin-bottom:0;">
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

        <button type="button" onclick="openGCalMasterModal()" class="btn-gcal-sync-hub" title="Google Calendar Sync, Bulk .ICS Export & Live Feed">
            <i data-lucide="calendar" style="width:14px; height:14px;"></i>
            <span>Google Calendar Sync Hub</span>
        </button>
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
                        $assigned_st = !empty($app['assigned_to']) ? ($staff_map[$app['assigned_to']] ?? null) : null;
                        $gcal_url = get_google_calendar_url($app, $assigned_st);
                        $wa_msg = urlencode("Hi " . $app['name'] . ", this is regarding your consultation appointment with Bhardwaj Financial Services scheduled for " . ($app['slot_date'] ?: 'your booking') . " (" . ($app['slot_time'] ?: '') . ").\n\n📅 Save to Google Calendar: " . $gcal_url . "\n\nPlease let us know if this time works for you!");
                        $is_home_visit = (stripos($app['source'] ?? '', 'Home Visit') !== false || stripos($app['message'] ?? '', 'Doorstep') !== false);
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

                        <!-- Assigned RM (Searchable Officer Picker for Fast Filtering) -->
                        <td>
                            <div id="rm-cell-wrap-<?php echo $app['id']; ?>">
                                <?php if (!empty($app['assigned_to'])): 
                                    $assigned_st = $staff_map[$app['assigned_to']] ?? null;
                                    $disp_name = $assigned_st ? $assigned_st['display_name'] : $app['assigned_to'];
                                    $disp_role = $assigned_st ? ($assigned_st['role'] === 'RM' ? 'In-Office RM' : $assigned_st['role']) : 'Staff';
                                    $initial = strtoupper(substr($disp_name, 0, 1));
                                ?>
                                    <button type="button" class="btn-rm-badge" onclick="openRMPickerModal(<?php echo $app['id']; ?>, '<?php echo htmlspecialchars(addslashes($app['name'])); ?>', '<?php echo htmlspecialchars(addslashes($app['assigned_to'])); ?>')" title="Click to change or reassign Relationship Manager">
                                        <span class="rm-badge-avatar"><?php echo $initial; ?></span>
                                        <span class="rm-badge-meta">
                                            <span class="rm-badge-name"><?php echo htmlspecialchars($disp_name); ?></span>
                                            <span class="rm-badge-role"><?php echo htmlspecialchars($disp_role); ?></span>
                                        </span>
                                        <i data-lucide="chevron-down" style="width:12px; height:12px; margin-left:auto; color:var(--appt-muted);"></i>
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn-rm-unassigned" onclick="openRMPickerModal(<?php echo $app['id']; ?>, '<?php echo htmlspecialchars(addslashes($app['name'])); ?>', '')" title="Click to search and assign In-Office RM">
                                        <i data-lucide="user-plus" style="width:13px; height:13px;"></i>
                                        <span>+ Assign RM</span>
                                        <i data-lucide="chevron-down" style="width:12px; height:12px; margin-left:auto; opacity:0.6;"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>

                        <!-- Quick Actions (Monochrome Invert on Hover) -->
                        <td style="text-align:right;">
                            <div class="appt-actions-bar">
                                <!-- 1. Add to Google Calendar (1-Click Direct Sync) -->
                                <a href="<?php echo $gcal_url; ?>" target="_blank" class="btn-appt-tool" title="1-Click Sync / Add to Google Calendar" id="gcal-link-<?php echo $app['id']; ?>">
                                    <i data-lucide="calendar-plus" style="width:14px; height:14px;"></i>
                                </a>

                                <!-- 2. Download .ICS Event (Google / Apple / Outlook) -->
                                <a href="appointments.php?action=download_ics&id=<?php echo $app['id']; ?>" class="btn-appt-tool" title="Download .ICS Event (Google / Apple / Outlook Calendar)">
                                    <i data-lucide="calendar" style="width:13px; height:13px;"></i>
                                </a>

                                <?php if (!empty($raw_phone)): ?>
                                <!-- 3. Direct Call -->
                                <a href="tel:<?php echo $raw_phone; ?>" class="btn-appt-tool" title="Call Applicant">
                                    <i data-lucide="phone" style="width:13px; height:13px;"></i>
                                </a>
                                <!-- 4. Direct WhatsApp -->
                                <a href="https://wa.me/91<?php echo substr($raw_phone, -10); ?>?text=<?php echo $wa_msg; ?>" target="_blank" class="btn-appt-tool" title="WhatsApp Appointment Confirmation with Google Calendar Link">
                                    <i data-lucide="message-circle" style="width:14px; height:14px;"></i>
                                </a>
                                <?php endif; ?>

                                <?php if (!empty($app['lead_id'])): ?>
                                <!-- 5. CRM Lead Profile -->
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

<!-- SEARCHABLE IN-OFFICE RM PICKER MODAL (INSTANT SEARCH FOR 100+ OFFICERS) -->
<div id="rmPickerModal" class="bfs-modal-backdrop" style="display:none;">
    <div class="bfs-modal-container">
        <!-- Modal Header -->
        <div class="bfs-modal-header">
            <div>
                <div style="display:flex; align-items:center; gap:8px;">
                    <span style="background:var(--appt-black); color:#ffffff; font-size:10px; font-weight:800; padding:2px 7px; border-radius:4px; font-family:'Outfit',sans-serif;">BFS CIRCLE</span>
                    <h3 style="margin:0; font-size:16px; font-weight:800; color:var(--appt-black); font-family:'Outfit',sans-serif;">Assign Relationship Manager</h3>
                </div>
                <div id="rmPickerSubtitle" style="font-size:12px; color:var(--appt-muted); margin-top:3px;">
                    Select an authorized In-Office RM or Staff Officer for this appointment.
                </div>
            </div>
            <button type="button" onclick="closeRMPickerModal()" class="bfs-modal-close">&times;</button>
        </div>

        <!-- Live Instant Search Bar -->
        <div style="padding:14px 20px; background:#f8fafc; border-bottom:1px solid var(--appt-border);">
            <div class="rm-search-box">
                <i data-lucide="search" style="width:16px; height:16px; color:var(--appt-muted);"></i>
                <input type="text" id="rmSearchInput" placeholder="Search officer by name, designation, department, ID, mobile..." oninput="filterOfficersList()">
                <button type="button" id="rmSearchClearBtn" onclick="clearOfficerSearch()" style="display:none; background:none; border:none; color:var(--appt-muted); cursor:pointer; font-size:14px;">✕</button>
            </div>
            
            <!-- Quick Role Category Filter Tabs -->
            <div class="rm-filter-tabs">
                <button type="button" class="rm-tab-btn active" data-tab="all" onclick="setOfficerTab('all', this)">All Officers (<?= count($staff_list) ?>)</button>
                <button type="button" class="rm-tab-btn" data-tab="RM" onclick="setOfficerTab('RM', this)">In-Office RMs (<?= count(array_filter($staff_list, fn($s) => $s['role'] === 'RM')) ?>)</button>
                <button type="button" class="rm-tab-btn" data-tab="Staff" onclick="setOfficerTab('Staff', this)">Staff Officers (<?= count(array_filter($staff_list, fn($s) => $s['role'] === 'Staff')) ?>)</button>
                <button type="button" class="rm-tab-btn" data-tab="Admin" onclick="setOfficerTab('Admin', this)">Admins (<?= count(array_filter($staff_list, fn($s) => $s['role'] === 'Admin')) ?>)</button>
            </div>
        </div>

        <!-- Currently Assigned Officer & Unassign Action Banner -->
        <div id="rmCurrentBanner" style="display:none; padding:10px 20px; background:#f1f5f9; border-bottom:1px dashed var(--appt-border); align-items:center; justify-content:space-between;">
            <div style="font-size:12px; color:var(--appt-black);">
                <strong>Currently Assigned:</strong> <span id="rmCurrentName">-</span>
            </div>
            <button type="button" onclick="confirmUnassignRM()" class="btn-rm-unassign-act">
                <i data-lucide="user-x" style="width:12px; height:12px;"></i> Mark as Unassigned
            </button>
        </div>

        <!-- Officers List (Scrollable, High-Density) -->
        <div class="rm-officers-deck" id="rmOfficersDeck">
            <?php foreach ($staff_list as $st): 
                $initial = strtoupper(substr($st['display_name'], 0, 1));
                $search_str = strtolower($st['display_name'] . ' ' . $st['username'] . ' ' . $st['role'] . ' ' . $st['designation'] . ' ' . $st['department'] . ' ' . ($st['emp_id'] ?? '') . ' ' . ($st['mobile'] ?? '') . ' ' . ($st['official_email'] ?? ''));
                $workload = (int)($st['active_workload'] ?? 0);
            ?>
            <div class="rm-officer-row" 
                 data-username="<?= htmlspecialchars($st['username']) ?>"
                 data-name="<?= htmlspecialchars($st['display_name']) ?>"
                 data-role="<?= htmlspecialchars($st['role']) ?>"
                 data-role-label="<?= $st['role'] === 'RM' ? 'In-Office RM' : $st['role'] ?>"
                 data-avatar="<?= $initial ?>"
                 data-search="<?= htmlspecialchars($search_str) ?>"
                 onclick="assignOfficerToActiveAppt('<?= htmlspecialchars(addslashes($st['username'])) ?>', '<?= htmlspecialchars(addslashes($st['display_name'])) ?>', '<?= $st['role'] === 'RM' ? 'In-Office RM' : $st['role'] ?>', '<?= $initial ?>')">
                
                <div class="rm-row-avatar">
                    <?php if (!empty($st['photo_path']) && file_exists(__DIR__ . '/' . $st['photo_path'])): ?>
                        <img src="<?= htmlspecialchars($st['photo_path']) ?>" alt="Avatar">
                    <?php else: ?>
                        <?= $initial ?>
                    <?php endif; ?>
                </div>

                <div class="rm-row-content">
                    <div class="rm-row-name-line">
                        <span class="rm-row-name"><?= htmlspecialchars($st['display_name']) ?></span>
                        <span class="badge-role-pill <?= strtolower($st['role']) ?>"><?= $st['role'] === 'RM' ? 'In-Office RM' : $st['role'] ?></span>
                        <?php if (!empty($st['emp_id'])): ?>
                            <span class="rm-row-empid"><?= htmlspecialchars($st['emp_id']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="rm-row-subline">
                        <span><i data-lucide="building" style="width:11px; height:11px;"></i> <?= htmlspecialchars($st['department']) ?></span>
                        <span>•</span>
                        <span><i data-lucide="briefcase" style="width:11px; height:11px;"></i> <?= $workload ?> Active <?= $workload === 1 ? 'Booking' : 'Bookings' ?></span>
                        <?php if (!empty($st['mobile'])): ?>
                            <span>•</span>
                            <span>📞 <?= htmlspecialchars($st['mobile']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="rm-row-select-btn">
                    <span class="rm-assign-pill">Assign &rarr;</span>
                </div>
            </div>
            <?php endforeach; ?>

            <div id="rmNoResults" style="display:none; padding:35px 20px; text-align:center; color:var(--appt-muted);">
                <i data-lucide="user-x" style="width:32px; height:32px; margin-bottom:8px; opacity:0.4;"></i>
                <div style="font-weight:700; color:var(--appt-black);">No Officers Found</div>
                <div style="font-size:12px; margin-top:2px;">Try searching with another name, department, or employee code.</div>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="bfs-modal-footer">
            <span style="font-size:11.5px; color:var(--appt-muted);">
                Verified internal team • Click any officer to immediately assign & sync Google Calendar
            </span>
            <button type="button" onclick="closeRMPickerModal()" class="btn-modal-cancel">Cancel</button>
        </div>
    </div>
</div>

<!-- GOOGLE CALENDAR & MASTER SYNC HUB MODAL -->
<div id="gcalMasterModal" class="bfs-modal-backdrop" style="display:none;">
    <div class="bfs-modal-container" style="max-width:540px;">
        <div class="bfs-modal-header">
            <div style="display:flex; align-items:center; gap:8px;">
                <span style="background:var(--appt-black); color:#ffffff; font-size:10px; font-weight:800; padding:2px 7px; border-radius:4px; font-family:'Outfit',sans-serif;">GOOGLE CALENDAR</span>
                <h3 style="margin:0; font-size:16px; font-weight:800; color:var(--appt-black); font-family:'Outfit',sans-serif;">Meeting & Calendar Sync Hub</h3>
            </div>
            <button type="button" onclick="closeGCalMasterModal()" class="bfs-modal-close">&times;</button>
        </div>

        <div style="padding:20px;">
            <!-- Card 1: 1-Click Master .ICS Download -->
            <div style="background:#f8fafc; border:1px solid var(--appt-border); border-radius:10px; padding:16px; margin-bottom:16px;">
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:6px;">
                    <i data-lucide="calendar-check" style="width:20px; height:20px; color:var(--appt-black);"></i>
                    <div style="font-weight:800; font-size:14px; color:var(--appt-black);">Bulk Export Upcoming Appointments</div>
                </div>
                <div style="font-size:12px; color:var(--appt-muted); line-height:1.4; margin-bottom:12px;">
                    Download an industry-standard <code>.ICS</code> file containing all active scheduled appointments. Double-click to import into Google Calendar, Apple Calendar, or Microsoft Outlook.
                </div>
                <a href="appointments.php?action=download_all_ics" class="btn-gcal-sync-hub" style="width:100%; justify-content:center; box-sizing:border-box;">
                    <i data-lucide="download" style="width:14px; height:14px;"></i> Download All Active Appointments (.ICS)
                </a>
            </div>

            <!-- Card 2: Live Google Calendar Subscription Feed -->
            <div style="background:#f8fafc; border:1px solid var(--appt-border); border-radius:10px; padding:16px;">
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:6px;">
                    <i data-lucide="refresh-cw" style="width:20px; height:20px; color:var(--appt-black);"></i>
                    <div style="font-weight:800; font-size:14px; color:var(--appt-black);">Live Google Calendar Subscription Feed</div>
                </div>
                <div style="font-size:12px; color:var(--appt-muted); line-height:1.4; margin-bottom:10px;">
                    Add this live URL to your Google Calendar to automatically receive real-time schedule updates on your phone and laptop without manual exporting.
                </div>
                <div style="display:flex; gap:8px;">
                    <input type="text" id="gcalFeedInput" readonly value="<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost:8000') . dirname($_SERVER['SCRIPT_NAME']) . '/appointments.php?action=calendar_feed'; ?>" style="flex:1; background:#ffffff; border:1px solid var(--appt-border-dark); border-radius:6px; padding:6px 10px; font-size:11.5px; color:var(--appt-black); outline:none;">
                    <button type="button" onclick="copyCalendarFeedUrl()" class="btn-gcal-sync-hub" style="padding:6px 12px; font-size:11.5px; flex-shrink:0;">
                        <i data-lucide="copy" style="width:12px; height:12px;"></i> Copy URL
                    </button>
                </div>
                <div style="font-size:11px; color:var(--appt-muted); margin-top:8px;">
                    💡 <em>In Google Calendar: Click "+" beside "Other calendars" &rarr; "From URL" &rarr; Paste this link.</em>
                </div>
            </div>
        </div>

        <div class="bfs-modal-footer">
            <span style="font-size:11.5px; color:var(--appt-muted);">Syncs directly with Google Calendar, Android &amp; iOS</span>
            <button type="button" onclick="closeGCalMasterModal()" class="btn-modal-cancel">Close</button>
        </div>
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

// --- SEARCHABLE RM PICKER & GOOGLE CALENDAR CONTROLLERS ---
let activeApptId = null;
let activeApptName = '';
let activeCurrentRm = '';
let currentRoleTab = 'all';

function openRMPickerModal(apptId, applicantName, currentRm) {
    activeApptId = apptId;
    activeApptName = applicantName;
    activeCurrentRm = currentRm || '';

    document.getElementById('rmPickerSubtitle').innerHTML = 'Select an authorized In-Office RM or Staff Officer for <strong>' + escapeHtml(applicantName) + '</strong>';

    const banner = document.getElementById('rmCurrentBanner');
    const nameSpan = document.getElementById('rmCurrentName');
    if (activeCurrentRm) {
        banner.style.display = 'flex';
        nameSpan.innerText = activeCurrentRm;
    } else {
        banner.style.display = 'none';
    }

    // Highlight currently assigned officer
    document.querySelectorAll('.rm-officer-row').forEach(row => {
        if (row.getAttribute('data-username') === activeCurrentRm) {
            row.classList.add('is-current');
        } else {
            row.classList.remove('is-current');
        }
    });

    // Reset Search & Tab
    document.getElementById('rmSearchInput').value = '';
    currentRoleTab = 'all';
    document.querySelectorAll('.rm-tab-btn').forEach(b => b.classList.toggle('active', b.getAttribute('data-tab') === 'all'));
    filterOfficersList();

    document.getElementById('rmPickerModal').style.display = 'flex';
    document.getElementById('rmSearchInput').focus();
    if (window.lucide) lucide.createIcons();
}

function closeRMPickerModal() {
    document.getElementById('rmPickerModal').style.display = 'none';
    activeApptId = null;
}

function setOfficerTab(tab, btn) {
    currentRoleTab = tab;
    document.querySelectorAll('.rm-tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    filterOfficersList();
}

function clearOfficerSearch() {
    document.getElementById('rmSearchInput').value = '';
    filterOfficersList();
    document.getElementById('rmSearchInput').focus();
}

function filterOfficersList() {
    const q = (document.getElementById('rmSearchInput').value || '').toLowerCase().trim();
    document.getElementById('rmSearchClearBtn').style.display = q ? 'inline-block' : 'none';

    const rows = document.querySelectorAll('.rm-officer-row');
    let visibleCount = 0;

    rows.forEach(row => {
        const searchStr = row.getAttribute('data-search') || '';
        const role = row.getAttribute('data-role') || '';

        let matchQuery = !q || searchStr.includes(q);
        let matchTab = (currentRoleTab === 'all') || (role === currentRoleTab);

        if (matchQuery && matchTab) {
            row.style.display = 'flex';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    document.getElementById('rmNoResults').style.display = visibleCount === 0 ? 'block' : 'none';
}

function assignOfficerToActiveAppt(rmUsername, rmDisplayName, rmRoleLabel, rmInitial) {
    if (!activeApptId) return;

    const formData = new FormData();
    formData.append('action', 'assign_rm');
    formData.append('id', activeApptId);
    formData.append('assigned_to', rmUsername);

    fetch('appointments.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                updateApptRMCell(activeApptId, activeApptName, rmUsername, rmDisplayName, rmRoleLabel, rmInitial);
                
                // Update Google Calendar URL on this row
                if (data.new_gcal_url) {
                    const gcalLink = document.getElementById('gcal-link-' + activeApptId);
                    if (gcalLink) gcalLink.href = data.new_gcal_url;
                }

                closeRMPickerModal();

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Relationship Manager Assigned',
                        html: '<strong>' + escapeHtml(rmDisplayName) + '</strong> (' + escapeHtml(rmRoleLabel) + ') assigned to appointment.<br><span style="font-size:12px; color:#64748b;">Google Calendar invite details updated.</span>',
                        timer: 2400,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end'
                    });
                }
            } else {
                alert('Failed to assign RM: ' + (data.error || 'Unknown error'));
            }
        }).catch(() => alert('Network error occurred.'));
}

function confirmUnassignRM() {
    if (!activeApptId) return;

    const formData = new FormData();
    formData.append('action', 'assign_rm');
    formData.append('id', activeApptId);
    formData.append('assigned_to', '');

    fetch('appointments.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                updateApptRMCell(activeApptId, activeApptName, '', '', '', '');
                closeRMPickerModal();

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'info',
                        title: 'Appointment Unassigned',
                        text: 'This booking has been marked as Unassigned.',
                        timer: 2000,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end'
                    });
                }
            } else {
                alert('Failed to unassign RM: ' + (data.error || 'Unknown error'));
            }
        }).catch(() => alert('Network error occurred.'));
}

function updateApptRMCell(apptId, applicantName, username, displayName, roleLabel, initial) {
    const wrap = document.getElementById('rm-cell-wrap-' + apptId);
    if (!wrap) return;

    if (username) {
        wrap.innerHTML = `
            <button type="button" class="btn-rm-badge" onclick="openRMPickerModal(${apptId}, '${escapeHtml(applicantName)}', '${escapeHtml(username)}')" title="Click to change or reassign Relationship Manager">
                <span class="rm-badge-avatar">${escapeHtml(initial)}</span>
                <span class="rm-badge-meta">
                    <span class="rm-badge-name">${escapeHtml(displayName)}</span>
                    <span class="rm-badge-role">${escapeHtml(roleLabel)}</span>
                </span>
                <i data-lucide="chevron-down" style="width:12px; height:12px; margin-left:auto; color:var(--appt-muted);"></i>
            </button>
        `;
    } else {
        wrap.innerHTML = `
            <button type="button" class="btn-rm-unassigned" onclick="openRMPickerModal(${apptId}, '${escapeHtml(applicantName)}', '')" title="Click to search and assign In-Office RM">
                <i data-lucide="user-plus" style="width:13px; height:13px;"></i>
                <span>+ Assign RM</span>
                <i data-lucide="chevron-down" style="width:12px; height:12px; margin-left:auto; opacity:0.6;"></i>
            </button>
        `;
    }
    if (window.lucide) lucide.createIcons();
}

function openGCalMasterModal() {
    document.getElementById('gcalMasterModal').style.display = 'flex';
    if (window.lucide) lucide.createIcons();
}

function closeGCalMasterModal() {
    document.getElementById('gcalMasterModal').style.display = 'none';
}

function copyCalendarFeedUrl() {
    const input = document.getElementById('gcalFeedInput');
    input.select();
    navigator.clipboard.writeText(input.value).then(() => {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'success',
                title: 'URL Copied to Clipboard!',
                text: 'Paste into Google Calendar -> Add other calendar -> From URL.',
                timer: 2500,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
        } else {
            alert('Calendar Feed URL copied to clipboard!');
        }
    });
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>"']/g, function(m) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
    });
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
