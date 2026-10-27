<?php
date_default_timezone_set('Asia/Kolkata');
@ini_set('memory_limit', '512M');
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}


if (!function_exists('is_subfolder_context')) {
    function is_subfolder_context() {
        if (defined('IS_SUBFOLDER') && IS_SUBFOLDER) return true;
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $subfolders = ['/rm/', '/staff/', '/partner/', '/builder/', '/agent/', '/ca/', '/client_vault/'];
        foreach ($subfolders as $sf) {
            if (strpos($script, $sf) !== false) return true;
        }
        return false;
    }
}

if (!function_exists('get_client_ip_address')) {
    function get_client_ip_address() {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return trim($_SERVER['HTTP_CF_CONNECTING_IP']);
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}

if (!function_exists('get_role_dashboard_url')) {
    function get_role_dashboard_url($role = null, $force_subfolder_prefix = null) {
        if ($role === null) {
            $role = $_SESSION['role'] ?? '';
        }
        $in_sub = ($force_subfolder_prefix !== null) ? $force_subfolder_prefix : is_subfolder_context();
        $prefix = $in_sub ? '../' : '';

        switch ($role) {
            case 'Admin':
                return $prefix . 'dashboard.php';
            case 'RM':
                return $prefix . 'rm/dashboard.php';
            case 'Staff':
                return $prefix . 'staff/index.php';
            case 'Partner':
            case 'DSA':
            case 'Connector':
            case 'Individual':
            case 'Financial Advisor / DSA':
                return $prefix . 'partner/index.php';
            case 'Builder':
            case 'Builder / Real Estate':
                return $prefix . 'builder/index.php';
            case 'Agent':
            case 'Individual Agent':
                return $prefix . 'agent/index.php';
            case 'CA':
            case 'CA/CS':
            case 'Chartered Accountant (CA)':
                return $prefix . 'ca/index.php';
            default:
                return $prefix . 'login.php';
        }
    }
}

if (!function_exists('require_auth')) {
    function require_auth($redirect_url = null) {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $prefix = is_subfolder_context() ? '../' : '';
        if (empty($_SESSION['user_id'])) {
            if ($redirect_url === null) {
                $redirect_url = $prefix . 'login.php';
            }
            header("Location: " . $redirect_url);
            exit;
        }

        // Live Security Check: verify that the user account is still active and not deactivated
        global $db;
        if (isset($db) && !empty($_SESSION['user_id'])) {
            $chk = $db->prepare("SELECT is_active, role, username FROM users WHERE id = ?");
            $chk->execute([$_SESSION['user_id']]);
            $u_curr = $chk->fetch(PDO::FETCH_ASSOC);
            if ($u_curr) {
                if (isset($u_curr['is_active']) && (int)$u_curr['is_active'] === 0) {
                    session_destroy();
                    header("Location: " . $prefix . "login.php?deactivated=1");
                    exit;
                }
                // If partner role, also verify referral status
                if (in_array($u_curr['role'], ['Partner', 'DSA', 'Connector', 'Individual', 'Financial Advisor / DSA', 'Builder', 'Agent', 'CA'])) {
                    $chk_ref = $db->prepare("SELECT status FROM referrals WHERE user_id = ? OR LOWER(referral_id) = LOWER(?) OR mobile = ? LIMIT 1");
                    $chk_ref->execute([$_SESSION['user_id'], $u_curr['username'], $u_curr['username']]);
                    $r_curr = $chk_ref->fetch(PDO::FETCH_ASSOC);
                    if ($r_curr && in_array(strtolower($r_curr['status'] ?? ''), ['inactive', 'deactive', 'deactivated', 'blocked', 'suspended'])) {
                        session_destroy();
                        header("Location: " . $prefix . "login.php?deactivated=1");
                        exit;
                    }
                }
            }
        }
    }
}

if (!function_exists('require_role')) {
    function require_role($allowed_roles, $allow_admin = true) {
        require_auth();
        
        if (!is_array($allowed_roles)) {
            $allowed_roles = [$allowed_roles];
        }

        $user_role = $_SESSION['role'] ?? '';

        // If admin is allowed and current user is Admin
        if ($allow_admin && $user_role === 'Admin') {
            return true;
        }

        // Check if role is in allowed list
        if (in_array($user_role, $allowed_roles, true)) {
            return true;
        }

        // Bypassing attempted: Redirect to user's authorized role dashboard
        $dest = get_role_dashboard_url($user_role);
        header("Location: " . $dest);
        exit;
    }
}

if (!function_exists('is_admin')) {
    function is_admin() {
        return (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin');
    }
}

if (!function_exists('render_bank_logo_badge')) {
    function render_bank_logo_badge($bank_name_raw) {
        $bank_name = trim($bank_name_raw ?: 'N/A');
        $lower = strtolower($bank_name);
        
        $main_name = $bank_name;
        $sub_name = '';
        if (strpos($bank_name, ' - ') !== false) {
            $parts = explode(' - ', $bank_name, 2);
            $main_name = trim($parts[0]);
            $sub_name = trim($parts[1]);
        }

        if (strpos($lower, 'hdfc') !== false) {
            $avatar_html = '
            <span class="bank-avatar-box" style="background:#004c8f;" title="HDFC Bank">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none">
                    <rect x="3" y="9.5" width="18" height="5" fill="#ffffff"/>
                    <rect x="9.5" y="3" width="5" height="18" fill="#ffffff"/>
                    <rect x="9" y="9" width="6" height="6" fill="#ed232a"/>
                </svg>
            </span>';
        } elseif (strpos($lower, 'state bank') !== false || strpos($lower, 'sbi') !== false) {
            $avatar_html = '
            <span class="bank-avatar-box" style="background:#0072bc;" title="State Bank of India">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none">
                    <circle cx="12" cy="12" r="9" fill="#ffffff"/>
                    <circle cx="12" cy="9.8" r="3.2" fill="#0072bc"/>
                    <rect x="10.8" y="9.8" width="2.4" height="7.2" fill="#0072bc"/>
                </svg>
            </span>';
        } elseif (strpos($lower, 'icici') !== false) {
            $avatar_html = '
            <span class="bank-avatar-box" style="background:#c84815;" title="ICICI Bank">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none">
                    <circle cx="12" cy="6.5" r="2.2" fill="#ffffff"/>
                    <path d="M10 10.5h4v7h-4z" fill="#ffffff"/>
                </svg>
            </span>';
        } elseif (strpos($lower, 'axis') !== false) {
            $avatar_html = '
            <span class="bank-avatar-box" style="background:#97144d;" title="Axis Bank">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none">
                    <path d="M12 4.5l5.5 14h-3.6l-1.9-5-1.9 5H6.5l5.5-14z" fill="#ffffff"/>
                </svg>
            </span>';
        } elseif (strpos($lower, 'kotak') !== false) {
            $avatar_html = '
            <span class="bank-avatar-box" style="background:#ed1c24;" title="Kotak Mahindra Bank">
                <span style="color:#ffffff; font-weight:900; font-size:13px; font-family:\'Inter\',sans-serif; line-height:1;">K</span>
            </span>';
        } elseif (strpos($lower, 'baroda') !== false || strpos($lower, 'bob') !== false) {
            $avatar_html = '
            <span class="bank-avatar-box" style="background:#f26522;" title="Bank of Baroda">
                <span style="color:#ffffff; font-weight:900; font-size:9.5px; font-family:\'Inter\',sans-serif; letter-spacing:-0.5px; line-height:1;">BOB</span>
            </span>';
        } elseif (strpos($lower, 'punjab') !== false || strpos($lower, 'pnb') !== false) {
            $avatar_html = '
            <span class="bank-avatar-box" style="background:#a20034;" title="PNB">
                <span style="color:#ffffff; font-weight:900; font-size:9.5px; font-family:\'Inter\',sans-serif; line-height:1;">PNB</span>
            </span>';
        } else {
            $avatar_html = '
            <span class="bank-avatar-box" style="background:#0f172a;" title="Bank Entity">
                <i data-lucide="landmark" style="width:14px; height:14px; color:#ffffff;"></i>
            </span>';
        }

        $url = 'view_bank.php?bank_name=' . urlencode($bank_name);
        
        $html = '<a href="' . htmlspecialchars($url) . '" class="bank-entry-link" onclick="event.stopPropagation();" title="View ' . htmlspecialchars($bank_name) . ' 360° Portal">';
        $html .= $avatar_html;
        $html .= '<div class="bank-text-group">';
        $html .= '<span class="bank-main-name">' . htmlspecialchars($main_name) . '</span>';
        if ($sub_name) {
            $html .= '<span class="bank-sub-name">' . htmlspecialchars($sub_name) . '</span>';
        }
        $html .= '</div>';
        $html .= '<i data-lucide="arrow-up-right" class="bank-arrow-icon"></i>';
        $html .= '</a>';
        
        return $html;
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    $_SESSION = [];
    $redirect_url = is_subfolder_context() ? '../login.php' : 'login.php';
    header("Location: " . $redirect_url);
    exit;
}

// Database path configuration
$db_file = __DIR__ . '/crm.db';
if (!defined('DB_FILE')) define('DB_FILE', $db_file);
$uploads_dir = __DIR__ . '/uploads';

// Initialize directory for attachments
if (!file_exists($uploads_dir)) {
    mkdir($uploads_dir, 0777, true);
}
if (!file_exists($uploads_dir . '/bankers')) {
    mkdir($uploads_dir . '/bankers', 0777, true);
}
if (!file_exists($uploads_dir . '/referrals')) {
    mkdir($uploads_dir . '/referrals', 0777, true);
}
if (!file_exists($uploads_dir . '/applicants')) {
    mkdir($uploads_dir . '/applicants', 0777, true);
}
if (!file_exists($uploads_dir . '/employees')) {
    mkdir($uploads_dir . '/employees', 0777, true);
}
if (!file_exists($uploads_dir . '/field_visits')) {
    mkdir($uploads_dir . '/field_visits', 0777, true);
}
if (!file_exists($uploads_dir . '/login_fees')) {
    mkdir($uploads_dir . '/login_fees', 0777, true);
}
if (!file_exists($uploads_dir . '/sanctions')) {
    mkdir($uploads_dir . '/sanctions', 0777, true);
}
if (!file_exists($uploads_dir . '/branding')) {
    mkdir($uploads_dir . '/branding', 0777, true);
}

try {
    $db = new PDO("sqlite:" . $db_file);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec("PRAGMA busy_timeout = 5000;");
    $db->exec("PRAGMA journal_mode = WAL;");
    
    // Core database schema initialization
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        name TEXT,
        password_hash TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'Staff', -- 'Admin', 'Staff'
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    
    try { $db->exec("ALTER TABLE users ADD COLUMN staff_type TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE users ADD COLUMN has_dashboard INTEGER DEFAULT 1"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE users ADD COLUMN plain_password TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE users ADD COLUMN full_name TEXT"); } catch (Exception $e) {}
// Create default admin if no users exist
    $user_count = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($user_count == 0) {
        $default_hash = password_hash('admin123', PASSWORD_DEFAULT);
        $db->exec("INSERT INTO users (username, password_hash, role) VALUES ('admin', '$default_hash', 'Admin')");
    }

    try {
        $db->exec("ALTER TABLE users ADD COLUMN is_active INTEGER DEFAULT 1");
    } catch (Exception $e) {}
    
    try {
        $db->exec("ALTER TABLE users ADD COLUMN session_token TEXT");
    } catch (Exception $e) {}

    try {
        $db->exec("ALTER TABLE users ADD COLUMN last_active DATETIME");
    } catch (Exception $e) {}

    try {
        $db->exec("ALTER TABLE users ADD COLUMN last_ip TEXT");
    } catch (Exception $e) {}

    try {
        $db->exec("ALTER TABLE users ADD COLUMN mobile TEXT");
    } catch (Exception $e) {}

    try { $db->exec("ALTER TABLE users ADD COLUMN last_lat REAL"); } catch(Exception $e) {}
    try { $db->exec("ALTER TABLE users ADD COLUMN last_lng REAL"); } catch(Exception $e) {}
    try { $db->exec("ALTER TABLE users ADD COLUMN current_battery TEXT"); } catch(Exception $e) {}
    try { $db->exec("ALTER TABLE users ADD COLUMN last_ping DATETIME"); } catch(Exception $e) {}
    try { $db->exec("ALTER TABLE applicant_documents ADD COLUMN qa_status TEXT DEFAULT 'Under Review'"); } catch(Exception $e) {}
    try { $db->exec("ALTER TABLE applicant_documents ADD COLUMN rejection_reason TEXT"); } catch(Exception $e) {}
    try { $db->exec("ALTER TABLE applicant_documents ADD COLUMN reupload_requested INTEGER DEFAULT 0"); } catch(Exception $e) {}

    $db->exec("CREATE TABLE IF NOT EXISTS email_templates (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        template_name TEXT NOT NULL,
        type TEXT NOT NULL,
        subject TEXT NOT NULL,
        body TEXT NOT NULL,
        attachment_name TEXT,
        delete_requested INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    $db->exec("CREATE TABLE IF NOT EXISTS bank_payout_invoices (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        applicant_id INTEGER NOT NULL,
        invoice_number TEXT NOT NULL UNIQUE,
        commission_pct REAL NOT NULL,
        disbursed_amount REAL NOT NULL,
        gst_amount REAL DEFAULT 0,
        tds_amount REAL DEFAULT 0,
        net_receivable REAL NOT NULL,
        received_amount REAL DEFAULT 0,
        shortfall_amount REAL DEFAULT 0,
        bank_utr TEXT,
        received_date DATETIME,
        status TEXT DEFAULT 'Draft',
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    $db->exec("CREATE TABLE IF NOT EXISTS presentations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        original_name TEXT NOT NULL,
        filename TEXT NOT NULL,
        delete_requested INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    try { $db->exec("ALTER TABLE email_templates ADD COLUMN delete_requested INTEGER DEFAULT 0"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE presentations ADD COLUMN delete_requested INTEGER DEFAULT 0"); } catch (Exception $e) {}

    $db->exec("CREATE TABLE IF NOT EXISTS leads (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        lead_name TEXT NOT NULL,
        company_name TEXT,
        mobile TEXT NOT NULL,
        email TEXT,
        lead_source TEXT DEFAULT 'Cold Call',
        assigned_to TEXT,
        priority TEXT DEFAULT 'Warm',
        stage TEXT DEFAULT 'New Lead',
        notes TEXT,
        location TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    try { $db->exec("ALTER TABLE leads ADD COLUMN requirement TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE leads ADD COLUMN loan_amount REAL"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE leads ADD COLUMN secondary_mobile TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE leads ADD COLUMN source_data TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE leads ADD COLUMN added_by TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE leads ADD COLUMN photo TEXT"); } catch (Exception $e) {}

    try { $db->exec("ALTER TABLE leads ADD COLUMN location TEXT"); } catch (Exception $e) {}

    $db->exec("CREATE TABLE IF NOT EXISTS pre_leads (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        company_name TEXT,
        mobile TEXT NOT NULL,
        email TEXT,
        source TEXT DEFAULT 'Unknown',
        status TEXT DEFAULT 'Not Contacted',
        assigned_to TEXT,
        location TEXT,
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    try { $db->exec("ALTER TABLE pre_leads ADD COLUMN assigned_to TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE pre_leads ADD COLUMN assigned_at DATETIME"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE pre_leads ADD COLUMN segment_name TEXT DEFAULT 'General Pool'"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE pre_leads ADD COLUMN city TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE pre_leads ADD COLUMN monthly_income REAL"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE pre_leads ADD COLUMN loan_amount_requested REAL"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE pre_leads ADD COLUMN loan_type TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE pre_leads ADD COLUMN call_count INTEGER DEFAULT 0"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE pre_leads ADD COLUMN last_call_at DATETIME"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE pre_leads ADD COLUMN last_call_outcome TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE pre_leads ADD COLUMN secondary_mobile TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE pre_leads ADD COLUMN profession TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE pre_leads ADD COLUMN disposition TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE pre_leads ADD COLUMN vendor_name TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE pre_leads ADD COLUMN purchase_batch_id TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE pre_leads ADD COLUMN purchase_date TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE pre_leads ADD COLUMN callback_time DATETIME"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE pre_leads ADD COLUMN promoted_to_lead_id INTEGER"); } catch (Exception $e) {}
    
    // Purchased Data Batches Table
    $db->exec("CREATE TABLE IF NOT EXISTS purchased_data_batches (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        batch_id TEXT UNIQUE NOT NULL,
        batch_name TEXT NOT NULL,
        vendor_name TEXT NOT NULL,
        purchase_amount REAL DEFAULT 0,
        records_bought INTEGER DEFAULT 0,
        valid_records INTEGER DEFAULT 0,
        duplicate_records INTEGER DEFAULT 0,
        invalid_records INTEGER DEFAULT 0,
        assigned_to_rm TEXT,
        segment_name TEXT DEFAULT 'Purchased Data',
        created_by TEXT NOT NULL,
        purchase_date DATE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Pre-Lead Activity Timeline & Call Audit Log
    $db->exec("CREATE TABLE IF NOT EXISTS pre_lead_activities (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        pre_lead_id INTEGER NOT NULL,
        activity_type TEXT NOT NULL,
        caller TEXT,
        outcome TEXT,
        notes TEXT,
        call_duration_secs INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(pre_lead_id) REFERENCES pre_leads(id)
    )");

    try { $db->exec("ALTER TABLE leads ADD COLUMN assigned_to TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE leads ADD COLUMN assigned_at DATETIME"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE leads ADD COLUMN location TEXT"); } catch (Exception $e) {}

    $db->exec("CREATE TABLE IF NOT EXISTS clients (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        company_name TEXT UNIQUE NOT NULL,
        business_type TEXT NOT NULL,
        industry_sector TEXT,
        gstin TEXT,
        pan TEXT,
        website TEXT,
        turnover TEXT,
        employees INTEGER,
        contact_name TEXT NOT NULL,
        designation TEXT,
        mobile TEXT NOT NULL,
        whatsapp TEXT,
        email TEXT NOT NULL,
        alternate_email TEXT,
        linkedin TEXT,
        address_line1 TEXT NOT NULL,
        address_line2 TEXT,
        city TEXT NOT NULL,
        state TEXT NOT NULL,
        pincode TEXT NOT NULL,
        country TEXT NOT NULL,
        bank_name TEXT,
        account_number TEXT,
        ifsc_code TEXT,
        lead_source TEXT NOT NULL,
        priority TEXT NOT NULL, -- 'Hot', 'Warm', 'Cold'
        added_by TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        remarks TEXT,
        overall_status TEXT DEFAULT 'New', -- 'New', 'Contacted', 'In Negotiation', 'Closed Won', 'Closed Lost'
        assigned_to TEXT
    )");
    
    try { $db->exec("ALTER TABLE clients ADD COLUMN assigned_to TEXT"); } catch (Exception $e) {}

    $db->exec("CREATE TABLE IF NOT EXISTS communications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        client_id INTEGER,
        type TEXT NOT NULL, -- 'Pitch', 'PPT', 'Custom Mail', 'Quotation'
        subject TEXT NOT NULL,
        body TEXT NOT NULL,
        cc TEXT,
        attachment_name TEXT,
        sent_by TEXT NOT NULL,
        sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(client_id) REFERENCES clients(id)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS quotations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        client_id INTEGER,
        quotation_number TEXT UNIQUE NOT NULL,
        status TEXT DEFAULT 'Pending', -- 'Pending', 'Approved', 'Rejected'
        rejection_reason TEXT,
        subtotal REAL NOT NULL,
        gst_amount REAL NOT NULL,
        total_amount REAL NOT NULL,
        items_json TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(client_id) REFERENCES clients(id)
    )");
    
    try { $db->exec("ALTER TABLE quotations ADD COLUMN rejection_reason TEXT"); } catch (Exception $e) {}

    
    $db->exec("CREATE TABLE IF NOT EXISTS applicant_pd_reports (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        applicant_id INTEGER UNIQUE,
        pd_conducted_by TEXT,
        pd_date TEXT,
        pd_mode TEXT,
        business_board_seen TEXT,
        stock_status TEXT,
        business_stability TEXT,
        monthly_turnover REAL,
        residence_type TEXT,
        years_at_address REAL,
        locality_classification TEXT,
        neighbor_feedback TEXT,
        consumer_durables TEXT,
        lifestyle_score TEXT,
        positive_triggers TEXT,
        negative_triggers TEXT,
        recommended_loan_amount REAL,
        final_pd_status TEXT,
        pd_report_path TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (applicant_id) REFERENCES applicants(id)
    )");

    try { $db->exec("ALTER TABLE applicant_documents ADD COLUMN owner_type TEXT DEFAULT 'Applicant'"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicant_documents ADD COLUMN owner_id INTEGER DEFAULT NULL"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicant_documents ADD COLUMN status TEXT DEFAULT 'Pending'"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicant_documents ADD COLUMN notes TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicant_documents ADD COLUMN file_type TEXT"); } catch (Exception $e) {}

    $db->exec("CREATE TABLE IF NOT EXISTS activities (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT,
        description TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    try { $db->exec("ALTER TABLE activities ADD COLUMN username TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE activities ADD COLUMN action_link TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE activities ADD COLUMN target_user TEXT"); } catch (Exception $e) {}

    $db->exec("CREATE TABLE IF NOT EXISTS reminders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        lead_type TEXT NOT NULL,
        lead_id INTEGER NOT NULL,
        assigned_to TEXT NOT NULL,
        remind_at DATETIME NOT NULL,
        notes TEXT,
        status TEXT DEFAULT 'Pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    // Advanced reminder columns - safe migration
    try { $db->exec("ALTER TABLE reminders ADD COLUMN title TEXT DEFAULT ''"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE reminders ADD COLUMN priority TEXT DEFAULT 'Medium'"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE reminders ADD COLUMN reference_type TEXT DEFAULT 'Lead'"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE reminders ADD COLUMN reference_id TEXT DEFAULT ''"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE reminders ADD COLUMN reference_label TEXT DEFAULT ''"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE reminders ADD COLUMN reminder_category TEXT DEFAULT 'Follow-up'"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE reminders ADD COLUMN recurrence TEXT DEFAULT 'None'"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE reminders ADD COLUMN snoozed_until DATETIME"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE reminders ADD COLUMN completed_at DATETIME"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE reminders ADD COLUMN completed_by TEXT"); } catch (Exception $e) {}
    // Migrate old reminders - set reference_type and reference_id from lead_type/lead_id
    try { $db->exec("UPDATE reminders SET reference_type = lead_type, reference_id = CAST(lead_id AS TEXT) WHERE (reference_type IS NULL OR reference_type = '') AND lead_id > 0"); } catch (Exception $e) {}

    $db->exec("CREATE TABLE IF NOT EXISTS call_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        lead_type TEXT NOT NULL,
        lead_id INTEGER NOT NULL,
        caller TEXT NOT NULL,
        response TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS company_profile (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        company_name TEXT DEFAULT 'BFS Financial Services Solutions',
        address_line1 TEXT DEFAULT 'Plot 12, Hitech Lane, Bandra West',
        address_line2 TEXT DEFAULT '',
        city TEXT DEFAULT 'Mumbai',
        state TEXT DEFAULT 'Maharashtra',
        pincode TEXT DEFAULT '400050',
        country TEXT DEFAULT 'India',
        gstin TEXT DEFAULT '27AABCA9087A1Z0',
        email TEXT DEFAULT 'info@bfsBFS Financial Services.com',
        mobile TEXT DEFAULT '9876543210',
        contact_person TEXT DEFAULT 'Rahul Sharma',
        bank_name TEXT DEFAULT 'HDFC Bank',
        account_number TEXT DEFAULT '50100987654321',
        ifsc_code TEXT DEFAULT 'HDFC0001234'
    )");

    $prof_count = $db->query("SELECT COUNT(*) FROM company_profile")->fetchColumn();
    if ($prof_count == 0) {
        $db->exec("INSERT INTO company_profile (company_name, address_line1, address_line2, city, state, pincode, country, gstin, email, mobile, contact_person, bank_name, account_number, ifsc_code) 
                   VALUES ('BFS Financial Services Solutions', 'Plot 12, Hitech Lane, Bandra West', '', 'Mumbai', 'Maharashtra', '400050', 'India', '27AABCA9087A1Z0', 'info@bfsBFS Financial Services.com', '9876543210', 'Rahul Sharma', 'HDFC Bank', '50100987654321', 'HDFC0001234')");
    }

    // Safe SQLite Alter Table for SMTP and Branding fields
    try { $db->exec("ALTER TABLE company_profile ADD COLUMN smtp_host TEXT DEFAULT ''"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE company_profile ADD COLUMN smtp_port TEXT DEFAULT ''"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE company_profile ADD COLUMN smtp_username TEXT DEFAULT ''"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE company_profile ADD COLUMN smtp_password TEXT DEFAULT ''"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE company_profile ADD COLUMN smtp_encryption TEXT DEFAULT ''"); } catch (Exception $e) {}

    // Master Corporate Branding & Identity Assets
    try { $db->exec("ALTER TABLE company_profile ADD COLUMN logo_dark TEXT DEFAULT ''"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE company_profile ADD COLUMN logo_light TEXT DEFAULT ''"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE company_profile ADD COLUMN stamp_signature TEXT DEFAULT ''"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE company_profile ADD COLUMN primary_color TEXT DEFAULT '#0f172a'"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE company_profile ADD COLUMN accent_color TEXT DEFAULT '#0284c7'"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE company_profile ADD COLUMN msme_reg_no TEXT DEFAULT ''"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE company_profile ADD COLUMN rbi_dsa_reg_no TEXT DEFAULT ''"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE company_profile ADD COLUMN website_url TEXT DEFAULT ''"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE company_profile ADD COLUMN maps_url TEXT DEFAULT ''"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE company_profile ADD COLUMN whatsapp_number TEXT DEFAULT ''"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE company_profile ADD COLUMN linkedin_url TEXT DEFAULT ''"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE company_profile ADD COLUMN instagram_url TEXT DEFAULT ''"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE company_profile ADD COLUMN facebook_url TEXT DEFAULT ''"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE company_profile ADD COLUMN bank_branch TEXT DEFAULT ''"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE company_profile ADD COLUMN invoice_footer_note TEXT DEFAULT 'Thank you for doing business with BFS Circle. Payment is due within 15 days.'"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE company_profile ADD COLUMN legal_disclaimer TEXT DEFAULT 'BFS Circle is an authorized Direct Sales Agent (DSA) partner for leading banks and NBFCs in India.'"); } catch (Exception $e) {}

    // Bankers table
    $db->exec("CREATE TABLE IF NOT EXISTS bankers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        full_name TEXT NOT NULL,
        bank_name TEXT NOT NULL,
        designation TEXT,
        state TEXT,
        city TEXT,
        address TEXT,
        ifsc_code TEXT,
        contact_number TEXT NOT NULL,
        official_email TEXT,
        loan_category TEXT,
        min_loan_limit REAL DEFAULT 0,
        max_loan_limit REAL DEFAULT 0,
        coverage_type TEXT,
        coverage_details TEXT,
        employee_id TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    try { $db->exec("ALTER TABLE bankers ADD COLUMN coverage_type TEXT"); } catch (Exception $e) {}

    try { $db->exec("ALTER TABLE bankers ADD COLUMN bank_type TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE bankers ADD COLUMN pincode TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE bankers ADD COLUMN dsa_code TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE bankers ADD COLUMN bank_official_email TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE bankers ADD COLUMN dsa_documents_drive_link TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE bankers ADD COLUMN dsa_undertaking_file TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE bankers ADD COLUMN status TEXT DEFAULT 'Active'"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE bankers ADD COLUMN deactivated_at DATETIME"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE bankers ADD COLUMN deactivated_by TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE bankers ADD COLUMN deactivation_reason TEXT"); } catch (Exception $e) {}
    
    $db->exec("CREATE TABLE IF NOT EXISTS ifsc_master (
        ifsc TEXT PRIMARY KEY,
        bank TEXT,
        branch TEXT,
        address TEXT,
        city TEXT,
        state TEXT
    )");

    try { $db->exec("ALTER TABLE bankers ADD COLUMN coverage_details TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE bankers ADD COLUMN min_roi REAL DEFAULT 0.0"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE bankers ADD COLUMN max_roi REAL DEFAULT 0.0"); } catch (Exception $e) {}

    // Employees / HRMS table
    $db->exec("CREATE TABLE IF NOT EXISTS employees (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        emp_id TEXT UNIQUE NOT NULL,
        full_name TEXT NOT NULL,
        official_email TEXT UNIQUE,
        personal_email TEXT,
        mobile TEXT NOT NULL,
        emergency_contact_name TEXT,
        emergency_relation TEXT,
        emergency_phone TEXT,
        current_address TEXT,
        permanent_address TEXT,
        department TEXT NOT NULL,
        designation TEXT NOT NULL,
        reporting_manager TEXT,
        doj TEXT,
        access_role TEXT NOT NULL,
        work_mode TEXT,
        team_specific_data TEXT, -- JSON for conditional fields
        pan_number TEXT,
        aadhar_number TEXT,
        bank_holder_name TEXT,
        bank_account_no TEXT,
        bank_name TEXT,
        bank_ifsc TEXT,
        photo_path TEXT,
        aadhar_path TEXT,
        pan_path TEXT,
        commission_rate REAL DEFAULT 1.0,
        marksheet_path TEXT,
        relieving_letter_path TEXT,
        cheque_path TEXT,
        monthly_base_salary REAL DEFAULT 0,
        employment_status TEXT DEFAULT 'Active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(user_id) REFERENCES users(id)
    )");

    try { $db->exec("ALTER TABLE employees ADD COLUMN monthly_base_salary REAL DEFAULT 0"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE employees ADD COLUMN employment_status TEXT DEFAULT 'Active'"); } catch (Exception $e) {}

    // HRMS Employee Attendance Table
    $db->exec("CREATE TABLE IF NOT EXISTS employee_attendance (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        employee_id INTEGER NOT NULL,
        emp_username TEXT NOT NULL,
        attendance_date DATE NOT NULL,
        status TEXT DEFAULT 'Present', -- 'Present', 'Absent', 'Half Day', 'On Leave', 'Paid Leave'
        check_in_time DATETIME,
        check_out_time DATETIME,
        total_minutes_worked INTEGER DEFAULT 0,
        punch_method TEXT DEFAULT 'Web Self-Punch',
        check_in_selfie TEXT,
        check_out_selfie TEXT,
        location_coords TEXT,
        ip_address TEXT,
        notes TEXT,
        logged_by TEXT DEFAULT 'Admin',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(employee_id) REFERENCES employees(id)
    )");

    try { $db->exec("ALTER TABLE employee_attendance ADD COLUMN check_in_time DATETIME"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE employee_attendance ADD COLUMN check_out_time DATETIME"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE employee_attendance ADD COLUMN total_minutes_worked INTEGER DEFAULT 0"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE employee_attendance ADD COLUMN punch_method TEXT DEFAULT 'Web Self-Punch'"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE employee_attendance ADD COLUMN check_in_selfie TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE employee_attendance ADD COLUMN check_out_selfie TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE employee_attendance ADD COLUMN location_coords TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE employee_attendance ADD COLUMN ip_address TEXT"); } catch (Exception $e) {}

    // HRMS Employee Salary & Incentive Payout Ledger
    $db->exec("CREATE TABLE IF NOT EXISTS employee_salary_payments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        employee_id INTEGER NOT NULL,
        emp_username TEXT NOT NULL,
        payment_month TEXT NOT NULL, -- e.g. '2026-09'
        base_salary REAL DEFAULT 0,
        incentive_amount REAL DEFAULT 0,
        bonus_amount REAL DEFAULT 0,
        deductions_amount REAL DEFAULT 0,
        net_salary_paid REAL NOT NULL,
        payment_date DATE NOT NULL,
        payment_mode TEXT DEFAULT 'Bank Transfer',
        transaction_ref TEXT,
        status TEXT DEFAULT 'Paid',
        remarks TEXT,
        created_by TEXT DEFAULT 'Admin',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(employee_id) REFERENCES employees(id)
    )");

    // Referrals table
    try {
        $db->exec("ALTER TABLE referrals ADD COLUMN aadhar_document_path TEXT");
    } catch (Exception $e) {}

    $db->exec("CREATE TABLE IF NOT EXISTS referrals (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        referral_id TEXT UNIQUE NOT NULL,
        referrer_type TEXT NOT NULL,
        full_name TEXT NOT NULL,
        dob TEXT,
        mobile TEXT NOT NULL,
        email TEXT,
        city_state TEXT,
        account_name TEXT,
        bank_name TEXT,
        account_number TEXT,
        ifsc_code TEXT,
        upi_id TEXT,
        commission_rate TEXT,
        payout_frequency TEXT,
        pan_number TEXT,
        aadhar_number TEXT,
        bank_document_path TEXT,
        pan_document_path TEXT,
        mapped_branch TEXT,
        assigned_rm TEXT,
        status TEXT DEFAULT 'Active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Customer Referrals (Refer & Earn for Existing Customers)
    $db->exec("CREATE TABLE IF NOT EXISTS customer_referrals (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        referrer_applicant_id INTEGER NOT NULL,
        referrer_name TEXT,
        referrer_mobile TEXT,
        referred_lead_id INTEGER DEFAULT 0,
        friend_name TEXT NOT NULL,
        friend_mobile TEXT NOT NULL,
        loan_requirement TEXT,
        status TEXT DEFAULT 'Lead Received',
        reward_amount REAL DEFAULT 2000.0,
        reward_status TEXT DEFAULT 'Pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Applicants (Phase 1)
    $db->exec("CREATE TABLE IF NOT EXISTS applicants (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        loan_id TEXT UNIQUE NOT NULL,
        customer_name TEXT NOT NULL,
        mobile TEXT NOT NULL,
        email TEXT,
        address TEXT,
        city TEXT,
        state TEXT,
        pincode TEXT,
        pan_number TEXT,
        aadhar_number TEXT,
        employment_type TEXT,
        monthly_income REAL,
        cibil_score INTEGER,
        loan_type TEXT,
        loan_sub_type TEXT,
        loan_amount_requested REAL,
        interest_rate REAL,
        tenure_months INTEGER,
        emi REAL,
        lead_source TEXT,
        referral_id TEXT,
        employee_id TEXT,
        overall_status TEXT DEFAULT 'Phase 1', -- 'Phase 1', 'Phase 2', 'Phase 3', 'Phase 4', 'Completed', 'Rejected'
        added_by TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    try { $db->exec("ALTER TABLE applicants ADD COLUMN organization_name TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicants ADD COLUMN organization_address TEXT"); } catch (Exception $e) {}

    try { $db->exec("ALTER TABLE applicants ADD COLUMN sanctioned_amount REAL"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicants ADD COLUMN sanction_bank TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicants ADD COLUMN sanction_date DATE"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicants ADD COLUMN sanction_roi REAL"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicants ADD COLUMN sanction_tenure INTEGER"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicants ADD COLUMN sanction_emi REAL"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicants ADD COLUMN sanction_ref_no TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicants ADD COLUMN sanction_letter_path TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicants ADD COLUMN sanction_status TEXT DEFAULT 'Pending'"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicants ADD COLUMN sanction_notes TEXT"); } catch (Exception $e) {}

    // Bank Login Fee Tracking
    try { $db->exec("ALTER TABLE applicants ADD COLUMN login_fee_amount REAL DEFAULT 0"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicants ADD COLUMN login_fee_status TEXT DEFAULT 'Pending'"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicants ADD COLUMN login_fee_payment_mode TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicants ADD COLUMN login_fee_paid_date DATE"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicants ADD COLUMN login_fee_ref_no TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicants ADD COLUMN login_fee_receipt_path TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicants ADD COLUMN login_fee_bank TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicants ADD COLUMN login_fee_notes TEXT"); } catch (Exception $e) {}

    // Post-Disbursement Banker Commission & Payout Recovery Desk
    try { $db->exec("ALTER TABLE applicants ADD COLUMN bank_payout_expected_pct REAL DEFAULT 0"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicants ADD COLUMN bank_payout_expected_amount REAL DEFAULT 0"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicants ADD COLUMN bank_payout_status TEXT DEFAULT 'Pending Claim'"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicants ADD COLUMN bank_payout_received_amount REAL DEFAULT 0"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicants ADD COLUMN bank_payout_received_date DATE"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicants ADD COLUMN bank_payout_ref_no TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicants ADD COLUMN bank_payout_invoice_no TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicants ADD COLUMN bank_payout_notes TEXT"); } catch (Exception $e) {}

    // Relationship Manager (RM) Assignment & Tracking Fields
    try { $db->exec("ALTER TABLE applicants ADD COLUMN assigned_to TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicants ADD COLUMN assigned_by TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicants ADD COLUMN assigned_at DATETIME"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicants ADD COLUMN rm_priority TEXT DEFAULT 'Normal'"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicants ADD COLUMN rm_last_contact_date DATE"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicants ADD COLUMN rm_next_followup_date DATE"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicants ADD COLUMN rm_notes TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicants ADD COLUMN notes TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicants ADD COLUMN remarks TEXT"); } catch (Exception $e) {}

    // Applicant Status & Deactivation Tracking
    try { $db->exec("ALTER TABLE applicants ADD COLUMN is_active INTEGER DEFAULT 1"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicants ADD COLUMN deactivation_otp TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicants ADD COLUMN deactivation_otp_expiry DATETIME"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicants ADD COLUMN deactivation_reason TEXT"); } catch (Exception $e) {}

    // Final Disbursement Fields on Applicants
    try { $db->exec("ALTER TABLE applicants ADD COLUMN disbursed_amount REAL DEFAULT 0"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicants ADD COLUMN disbursed_date DATE"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicants ADD COLUMN disbursing_bank TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicants ADD COLUMN disbursement_ref_no TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicants ADD COLUMN disbursement_mode TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicants ADD COLUMN disbursement_status TEXT DEFAULT 'Pending'"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicants ADD COLUMN disbursement_remarks TEXT"); } catch (Exception $e) {}

    // RM Targets & Incentive on Users
    try { $db->exec("ALTER TABLE users ADD COLUMN monthly_target REAL DEFAULT 15000000"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE users ADD COLUMN commission_rate REAL DEFAULT 0.20"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE users ADD COLUMN custom_permissions TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE users ADD COLUMN profile_photo TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE users ADD COLUMN email TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE referrals ADD COLUMN profile_photo TEXT"); } catch (Exception $e) {}

    // Password Reset OTPs
    $db->exec("CREATE TABLE IF NOT EXISTS password_reset_otps (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        email TEXT NOT NULL,
        otp_code TEXT NOT NULL,
        reset_token TEXT NOT NULL,
        expires_at DATETIME NOT NULL,
        is_used INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(user_id) REFERENCES users(id)
    )");
    try { $db->exec("CREATE INDEX IF NOT EXISTS idx_reset_email ON password_reset_otps(email)"); } catch (Exception $e) {}
    try { $db->exec("CREATE INDEX IF NOT EXISTS idx_reset_token ON password_reset_otps(reset_token)"); } catch (Exception $e) {}

    // Admin 2FA Email OTPs & Login Rate Limits
    $db->exec("CREATE TABLE IF NOT EXISTS login_rate_limits (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        identifier VARCHAR(100) NOT NULL,
        type VARCHAR(20) NOT NULL,
        failed_attempts INTEGER DEFAULT 0,
        first_failed_at DATETIME,
        last_failed_at DATETIME,
        locked_until DATETIME,
        lockout_count INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME
    )");
    try { $db->exec("CREATE INDEX IF NOT EXISTS idx_rate_limit_ident ON login_rate_limits(identifier, type)"); } catch (Exception $e) {}

    $db->exec("CREATE TABLE IF NOT EXISTS admin_login_2fa_otps (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        email VARCHAR(150),
        otp_code VARCHAR(10),
        otp_token VARCHAR(64),
        expires_at DATETIME,
        is_used INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    try { $db->exec("CREATE INDEX IF NOT EXISTS idx_admin_2fa_token ON admin_login_2fa_otps(otp_token)"); } catch (Exception $e) {}

    // Auto-ensure admin user email is configured for 2FA
    try {
        $db->exec("UPDATE users SET email = 'bfscaptain@gmail.com' WHERE (LOWER(username) = 'admin' OR role = 'Admin') AND (email IS NULL OR email = '' OR email = 'admin@bfscapital.com')");
    } catch (Exception $e) {}

    // Ensure admin password is set to BFSaNuJ@070123
    try {
        $new_admin_pwd = 'BFSaNuJ@070123';
        $new_admin_hash = password_hash($new_admin_pwd, PASSWORD_DEFAULT);
        $db->prepare("UPDATE users SET password_hash = ?, plain_password = ?, email = 'bfscaptain@gmail.com' WHERE LOWER(username) = 'admin' OR role = 'Admin'")->execute([$new_admin_hash, $new_admin_pwd]);
    } catch (Exception $e) {}

    // Auto-sync working official Gmail SMTP credentials for bfscaptain@gmail.com
    try {
        $db->exec("UPDATE company_profile SET smtp_host = 'smtp.gmail.com', smtp_port = '587', smtp_username = 'bfscaptain@gmail.com', smtp_password = 'agft ujxn vcsc rgyf', smtp_encryption = 'tls' WHERE id = 1 OR smtp_username = 'bfscaptain@gmail.com' OR smtp_username = 'kamchalauuu@gmail.com' OR smtp_username IS NULL OR smtp_username = ''");
    } catch (Exception $e) {}

    // Listmonk Marketing & Campaign Engine Table & Columns
    $db->exec("CREATE TABLE IF NOT EXISTS listmonk_sync_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        entity_type VARCHAR(30) NOT NULL, -- 'lead', 'applicant', 'referral', 'bulk'
        entity_id INTEGER,
        email VARCHAR(150),
        listmonk_sub_id INTEGER,
        lists_synced TEXT,
        status VARCHAR(20) DEFAULT 'synced',
        error_message TEXT,
        synced_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    try { $db->exec("CREATE INDEX IF NOT EXISTS idx_lm_email ON listmonk_sync_logs(email)"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE leads ADD COLUMN is_marketing_optout INTEGER DEFAULT 0"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE leads ADD COLUMN email_marketing_status VARCHAR(20) DEFAULT 'active'"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicants ADD COLUMN is_marketing_optout INTEGER DEFAULT 0"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicants ADD COLUMN email_marketing_status VARCHAR(20) DEFAULT 'active'"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE referrals ADD COLUMN is_marketing_optout INTEGER DEFAULT 0"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE referrals ADD COLUMN email_marketing_status VARCHAR(20) DEFAULT 'active'"); } catch (Exception $e) {}

    // Applicant Interactions (Call Logs, WhatsApp, Visits, Notes)
    $db->exec("CREATE TABLE IF NOT EXISTS applicant_interactions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        applicant_id INTEGER NOT NULL,
        interaction_type TEXT NOT NULL, -- 'Call', 'WhatsApp', 'Email', 'Visit', 'Note', 'Status_Change'
        outcome TEXT,
        notes TEXT,
        next_followup DATETIME,
        created_by TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(applicant_id) REFERENCES applicants(id)
    )");

    try { $db->exec("CREATE INDEX IF NOT EXISTS idx_applicants_assigned ON applicants(assigned_to)"); } catch (Exception $e) {}
    try { $db->exec("CREATE INDEX IF NOT EXISTS idx_applicants_status ON applicants(overall_status)"); } catch (Exception $e) {}

    // Applicant Documents (Phase 2)
    $db->exec("CREATE TABLE IF NOT EXISTS applicant_documents (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        applicant_id INTEGER NOT NULL,
        client_mobile TEXT,
        client_pan TEXT,
        document_category TEXT,
        document_name TEXT,
        file_path TEXT,
        file_size INTEGER,
        file_ext TEXT,
        status TEXT DEFAULT 'Pending',
        notes TEXT,
        owner_type TEXT DEFAULT 'Applicant',
        owner_id INTEGER,
        verified_by TEXT,
        verified_at DATETIME,
        uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(applicant_id) REFERENCES applicants(id)
    )");

    try { $db->exec("ALTER TABLE applicant_documents ADD COLUMN client_mobile TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicant_documents ADD COLUMN client_pan TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicant_documents ADD COLUMN status TEXT DEFAULT 'Pending'"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicant_documents ADD COLUMN notes TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicant_documents ADD COLUMN owner_type TEXT DEFAULT 'Applicant'"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicant_documents ADD COLUMN owner_id INTEGER"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicant_documents ADD COLUMN file_size INTEGER"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicant_documents ADD COLUMN file_ext TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicant_documents ADD COLUMN verified_by TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicant_documents ADD COLUMN verified_at DATETIME"); } catch (Exception $e) {}

    // Applicant Disbursements (Phase 3)
    $db->exec("CREATE TABLE IF NOT EXISTS applicant_disbursements (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        applicant_id INTEGER NOT NULL,
        phase_number INTEGER,
        phase_name TEXT,
        amount REAL,
        status TEXT DEFAULT 'Pending', -- 'Pending', 'Disbursed'
        disbursed_at DATETIME,
        remarks TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(applicant_id) REFERENCES applicants(id)
    )");

    // Print Activity Audit Logs Table
    $db->exec("CREATE TABLE IF NOT EXISTS print_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        module TEXT DEFAULT 'pre_leads',
        generated_by TEXT NOT NULL,
        generated_for TEXT NOT NULL,
        purpose TEXT,
        records_count INTEGER DEFAULT 0,
        segment_name TEXT,
        filters_summary TEXT,
        printed_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Applicant Bank Assignments (Phase 4)
    $db->exec("CREATE TABLE IF NOT EXISTS applicant_bank_assignments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        applicant_id INTEGER NOT NULL,
        bank_name TEXT NOT NULL,
        assigned_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        status TEXT DEFAULT 'Pending', -- 'Pending', 'Approved', 'Rejected'
        rejection_reason TEXT,
        approval_date DATETIME,
        FOREIGN KEY(applicant_id) REFERENCES applicants(id)
    )");

        $db->exec("CREATE TABLE IF NOT EXISTS staff_attendance (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL,
        att_date DATE NOT NULL,
        punch_in DATETIME,
        punch_out DATETIME,
        status TEXT DEFAULT 'Present',
        shift_duration TEXT
    )");
    
    // Check missing columns

    $db->exec("CREATE TABLE IF NOT EXISTS co_applicants (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        applicant_id INTEGER NOT NULL,
        is_financial INTEGER DEFAULT 0,
        relationship TEXT,
        full_name TEXT,
        mobile TEXT,
        email TEXT,
        dob TEXT,
        pan_number TEXT,
        aadhar_number TEXT,
        same_address INTEGER DEFAULT 0,
        address TEXT,
        pincode TEXT,
        state TEXT,
        city TEXT,
        employment_type TEXT,
        monthly_income REAL,
        current_emis REAL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(applicant_id) REFERENCES applicants(id)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS bank_payout_settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        bank_name TEXT NOT NULL,
        loan_type TEXT NOT NULL,
        payout_percentage REAL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS staff_location_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL,
        lat TEXT,
        lon TEXT,
        battery TEXT,
        status TEXT,
        logged_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS payout_distributions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        payout_id INTEGER,
        recipient_type TEXT,
        recipient_id TEXT,
        amount REAL,
        status TEXT DEFAULT 'Pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS client_queries (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        applicant_id INTEGER NOT NULL,
        query_subject TEXT,
        query_message TEXT NOT NULL,
        status TEXT DEFAULT 'Open',
        admin_reply TEXT,
        replied_at DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(applicant_id) REFERENCES applicants(id)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS client_callbacks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        applicant_id INTEGER NOT NULL,
        preferred_time TEXT,
        notes TEXT,
        status TEXT DEFAULT 'Pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(applicant_id) REFERENCES applicants(id)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS verification_schedules (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        applicant_id INTEGER NOT NULL,
        verification_type TEXT DEFAULT 'Residence FI',
        scheduled_date DATE,
        scheduled_time TEXT,
        officer_name TEXT,
        officer_mobile TEXT,
        client_confirmation TEXT DEFAULT 'Pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(applicant_id) REFERENCES applicants(id)
    )");

    try { $db->exec("ALTER TABLE applicants ADD COLUMN sanction_accepted INTEGER DEFAULT 0"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE applicants ADD COLUMN sanction_accepted_at DATETIME"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE payout_distributions ADD COLUMN amount REAL"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE payout_distributions ADD COLUMN status TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE payout_distributions ADD COLUMN recipient_id TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE payout_distributions ADD COLUMN recipient_type TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE payout_distributions ADD COLUMN referral_id TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE payout_distributions ADD COLUMN payment_mode TEXT DEFAULT 'Bank Transfer'"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE payout_distributions ADD COLUMN utr_ref TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE payout_distributions ADD COLUMN payment_date DATE"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE payout_distributions ADD COLUMN remarks TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE payout_distributions ADD COLUMN created_by TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE payout_distributions ADD COLUMN applicant_id INTEGER DEFAULT 0"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE payout_distributions ADD COLUMN payee_type TEXT DEFAULT 'Employee'"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE payout_distributions ADD COLUMN payee_user_id INTEGER DEFAULT 0"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE payout_distributions ADD COLUMN payee_name_override TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE payout_distributions ADD COLUMN payee_mobile TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE payout_distributions ADD COLUMN payee_bank_name TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE payout_distributions ADD COLUMN payee_account_no TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE payout_distributions ADD COLUMN payee_ifsc TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE payout_distributions ADD COLUMN payee_pan TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE payout_distributions ADD COLUMN total_loan_amount REAL DEFAULT 0"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE payout_distributions ADD COLUMN commission_percentage REAL DEFAULT 0"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE payout_distributions ADD COLUMN gross_payout REAL DEFAULT 0"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE payout_distributions ADD COLUMN tds_percentage REAL DEFAULT 5.0"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE payout_distributions ADD COLUMN tds_deducted REAL DEFAULT 0"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE payout_distributions ADD COLUMN net_payable REAL DEFAULT 0"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE payout_distributions ADD COLUMN cancellation_reason TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE payout_distributions ADD COLUMN transaction_ref TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE payout_distributions ADD COLUMN paid_on DATETIME"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE payout_distributions ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP"); } catch (Exception $e) {}
    
    // Document Compliance Matrix Table
    $db->exec("CREATE TABLE IF NOT EXISTS document_compliance_matrix (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        loan_type TEXT NOT NULL,
        employment_type TEXT DEFAULT 'Salaried',
        doc_category TEXT DEFAULT 'Basic KYC',
        doc_name TEXT NOT NULL,
        is_mandatory INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Lead Notes Timeline
    $db->exec("CREATE TABLE IF NOT EXISTS lead_notes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        lead_id INTEGER NOT NULL,
        note_text TEXT NOT NULL,
        added_by_user TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(lead_id) REFERENCES leads(id)
    )");

    // Internal Admin <-> RM Query Desk
    $db->exec("CREATE TABLE IF NOT EXISTS rm_internal_queries (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        assigned_rm TEXT NOT NULL,
        applicant_id INTEGER,
        query_title TEXT NOT NULL,
        query_description TEXT NOT NULL,
        priority TEXT DEFAULT 'Normal',
        status TEXT DEFAULT 'Open',
        raised_by TEXT DEFAULT 'Admin',
        rm_reply TEXT,
        resolved_at DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(applicant_id) REFERENCES applicants(id)
    )");
// Field Visits Module
    $db->exec("CREATE TABLE IF NOT EXISTS field_visits (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        visit_date DATE NOT NULL,
        executive_name TEXT NOT NULL,
        person_name TEXT NOT NULL,
        mobile TEXT NOT NULL,
        alt_mobile TEXT,
        profession TEXT NOT NULL,
        custom_profession TEXT,
        firm_name TEXT NOT NULL,
        state TEXT NOT NULL,
        city TEXT NOT NULL,
        full_address TEXT,
        pincode TEXT,
        lead_quality TEXT,
        photo_path TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    try { $db->exec("ALTER TABLE field_visits ADD COLUMN full_address TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE field_visits ADD COLUMN audio_path TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE field_visits ADD COLUMN latitude TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE field_visits ADD COLUMN longitude TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE field_visits ADD COLUMN verified_address TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE field_visits ADD COLUMN check_in_time TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE field_visits ADD COLUMN check_out_time TEXT"); } catch (Exception $e) {}
    
    $db->exec("CREATE TABLE IF NOT EXISTS field_visit_followups (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        visit_id INTEGER NOT NULL,
        remarks TEXT NOT NULL,
        next_meeting_date DATE,
        added_by TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(visit_id) REFERENCES field_visits(id)
    )");

    // ===== ADVANCED FEATURES: Multi-Bank Login Tracker =====
    $db->exec("CREATE TABLE IF NOT EXISTS applicant_bank_logins (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        applicant_id INTEGER NOT NULL,
        bank_name TEXT NOT NULL,
        banker_id INTEGER,
        login_date DATE,
        login_ref_no TEXT,
        status TEXT DEFAULT 'File Submitted',
        sanctioned_amount REAL,
        sanctioned_roi REAL,
        sanctioned_tenure INTEGER,
        processing_fee REAL,
        query_details TEXT,
        query_raised_date DATE,
        query_resolved_date DATE,
        rejection_reason TEXT,
        sanction_letter_path TEXT,
        selected_for_disbursal INTEGER DEFAULT 0,
        added_by TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(applicant_id) REFERENCES applicants(id)
    )");

    // SLA Alert Tables
    $db->exec("CREATE TABLE IF NOT EXISTS sla_alerts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        applicant_id INTEGER NOT NULL,
        alert_type TEXT DEFAULT '3_day_stall',
        alert_message TEXT,
        is_resolved INTEGER DEFAULT 0,
        resolved_by TEXT,
        resolved_at DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Bank Query Tracker
    $db->exec("CREATE TABLE IF NOT EXISTS bank_queries (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        applicant_id INTEGER NOT NULL,
        bank_login_id INTEGER,
        bank_name TEXT NOT NULL,
        query_type TEXT DEFAULT 'Document',
        query_description TEXT NOT NULL,
        raised_by_bank TEXT,
        raised_date DATE NOT NULL,
        expected_resolution_date DATE,
        status TEXT DEFAULT 'Open',
        resolution_notes TEXT,
        resolved_date DATE,
        document_required TEXT,
        whatsapp_sent INTEGER DEFAULT 0,
        added_by TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Client Tracking Token
    try { $db->exec("ALTER TABLE applicants ADD COLUMN tracking_token TEXT"); } catch(Exception $e){}
    try { $db->exec("ALTER TABLE applicants ADD COLUMN tracking_token_created DATETIME"); } catch(Exception $e){}
    try { $db->exec("ALTER TABLE applicants ADD COLUMN last_activity_date DATETIME"); } catch(Exception $e){}
    
    // Bank Payout Invoices
    $db->exec("CREATE TABLE IF NOT EXISTS bank_payout_invoices (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        applicant_id INTEGER NOT NULL,
        invoice_number TEXT UNIQUE,
        bank_name TEXT,
        disbursed_amount REAL DEFAULT 0,
        commission_pct REAL DEFAULT 0,
        gross_amount REAL DEFAULT 0,
        gst_amount REAL DEFAULT 0,
        tds_amount REAL DEFAULT 0,
        net_invoice_amount REAL DEFAULT 0,
        invoice_date DATE,
        due_date DATE,
        status TEXT DEFAULT 'Draft',
        received_amount REAL DEFAULT 0,
        received_date DATE,
        bank_utr TEXT,
        shortfall REAL DEFAULT 0,
        notes TEXT,
        created_by TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    try { $db->exec("ALTER TABLE bank_payout_invoices ADD COLUMN banker_id INTEGER"); } catch (Exception $e) {}

    // Dynamic Bank Commission Slabs & Case-level Rate Tracking
    try { $db->exec("ALTER TABLE applicants ADD COLUMN bank_commission_pct REAL DEFAULT 0"); } catch(Exception $e){}

    $db->exec("CREATE TABLE IF NOT EXISTS bank_commission_slabs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        bank_name TEXT NOT NULL,
        loan_type TEXT DEFAULT 'All',
        min_volume REAL DEFAULT 0,
        max_volume REAL DEFAULT 0,
        commission_pct REAL NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Seed default volume slabs if empty
    $checkSlabs = $db->query("SELECT COUNT(*) FROM bank_commission_slabs")->fetchColumn();
    if ($checkSlabs == 0) {
        $db->exec("INSERT INTO bank_commission_slabs (bank_name, loan_type, min_volume, max_volume, commission_pct) VALUES
            ('HDFC Bank', 'All', 0, 2000000, 1.00),
            ('HDFC Bank', 'All', 2000000, 5000000, 1.25),
            ('HDFC Bank', 'All', 5000000, 10000000, 1.50),
            ('HDFC Bank', 'All', 10000000, 999999999, 1.75),
            ('ICICI Bank', 'All', 0, 2000000, 1.00),
            ('ICICI Bank', 'All', 2000000, 5000000, 1.20),
            ('ICICI Bank', 'All', 5000000, 10000000, 1.45),
            ('ICICI Bank', 'All', 10000000, 999999999, 1.65),
            ('Axis Bank', 'All', 0, 2000000, 1.00),
            ('Axis Bank', 'All', 2000000, 5000000, 1.25),
            ('Axis Bank', 'All', 5000000, 10000000, 1.50),
            ('Axis Bank', 'All', 10000000, 999999999, 1.75),
            ('DEFAULT', 'All', 0, 2000000, 1.00),
            ('DEFAULT', 'All', 2000000, 5000000, 1.25),
            ('DEFAULT', 'All', 5000000, 10000000, 1.50),
            ('DEFAULT', 'All', 10000000, 999999999, 1.75)
        ");
    }

    // ===== FUND ALLOCATION & UTILIZATION MANAGEMENT MODULE =====
    $db->exec("CREATE TABLE IF NOT EXISTS fund_sources (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        source_id TEXT UNIQUE,
        source_name TEXT NOT NULL,
        source_type TEXT DEFAULT 'Head Office',
        organization_name TEXT,
        department TEXT,
        contact_person TEXT,
        mobile TEXT,
        email TEXT,
        address TEXT,
        notes TEXT,
        status TEXT DEFAULT 'Active',
        created_by TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $checkSources = $db->query("SELECT COUNT(*) FROM fund_sources")->fetchColumn();
    if ($checkSources == 0) {
        $db->exec("INSERT INTO fund_sources (source_id, source_name, source_type, organization_name) VALUES
            ('SRC-2026-0001', 'Praveen Sir (Head Office)', 'Head Office', 'BFS Circle Head Office'),
            ('SRC-2026-0002', 'Head Office Direct Fund', 'Head Office', 'BFS Circle'),
            ('SRC-2026-0003', 'Director Investment', 'Internal', 'BFS Circle')
        ");
    }

    $db->exec("CREATE TABLE IF NOT EXISTS funds (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        fund_id TEXT UNIQUE,
        fund_name TEXT NOT NULL,
        fund_code TEXT,
        source_id INTEGER NOT NULL,
        scheme TEXT,
        project_id INTEGER,
        department TEXT,
        financial_year TEXT DEFAULT '2026-2027',
        sanction_number TEXT,
        sanction_date DATE,
        sanctioned_amount REAL DEFAULT 0,
        purpose TEXT,
        start_date DATE,
        end_date DATE,
        description TEXT,
        approval_authority TEXT,
        sanction_letter_path TEXT,
        status TEXT DEFAULT 'Sanctioned',
        created_by TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(source_id) REFERENCES fund_sources(id)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS fund_receipts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        receipt_id TEXT UNIQUE,
        fund_id INTEGER NOT NULL,
        source_id INTEGER,
        amount_received REAL DEFAULT 0,
        received_date DATE,
        payment_mode TEXT DEFAULT 'Bank Transfer',
        bank_account TEXT,
        bank_name TEXT,
        utr_number TEXT,
        cheque_number TEXT,
        ref_number TEXT,
        installment_no INTEGER DEFAULT 1,
        description TEXT,
        receipt_proof_path TEXT,
        created_by TEXT,
        approved_by TEXT,
        status TEXT DEFAULT 'Approved',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(fund_id) REFERENCES funds(id)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS projects (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id TEXT UNIQUE,
        project_name TEXT NOT NULL,
        project_code TEXT,
        scheme TEXT,
        department TEXT,
        start_date DATE,
        end_date DATE,
        project_manager TEXT,
        budget REAL DEFAULT 0,
        description TEXT,
        location TEXT,
        status TEXT DEFAULT 'Active',
        documents_path TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS receivers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        receiver_id TEXT UNIQUE,
        receiver_type TEXT DEFAULT 'Vendor',
        name TEXT NOT NULL,
        organization_name TEXT,
        department TEXT,
        mobile TEXT,
        email TEXT,
        address TEXT,
        pan TEXT,
        gstin TEXT,
        bank_name TEXT,
        account_holder_name TEXT,
        account_number TEXT,
        ifsc TEXT,
        upi_id TEXT,
        documents_path TEXT,
        status TEXT DEFAULT 'Active',
        remarks TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS fund_allocations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        allocation_id TEXT UNIQUE,
        fund_id INTEGER NOT NULL,
        project_id INTEGER,
        scheme TEXT,
        department TEXT,
        receiver_id INTEGER,
        allocation_amount REAL DEFAULT 0,
        allocation_date DATE,
        purpose TEXT,
        expected_utilization_date DATE,
        approval_authority TEXT,
        remarks TEXT,
        supporting_doc_path TEXT,
        status TEXT DEFAULT 'Approved',
        created_by TEXT,
        approved_by TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(fund_id) REFERENCES funds(id),
        FOREIGN KEY(receiver_id) REFERENCES receivers(id)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS fund_disbursements (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        payment_id TEXT UNIQUE,
        allocation_id INTEGER NOT NULL,
        fund_id INTEGER NOT NULL,
        project_id INTEGER,
        receiver_id INTEGER NOT NULL,
        payment_amount REAL DEFAULT 0,
        payment_date DATE,
        payment_mode TEXT DEFAULT 'NEFT/RTGS',
        bank_account TEXT,
        utr_number TEXT,
        transaction_id TEXT,
        cheque_number TEXT,
        voucher_number TEXT,
        payment_purpose TEXT,
        payment_proof_path TEXT,
        remarks TEXT,
        created_by TEXT,
        approved_by TEXT,
        status TEXT DEFAULT 'Completed',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(allocation_id) REFERENCES fund_allocations(id),
        FOREIGN KEY(fund_id) REFERENCES funds(id),
        FOREIGN KEY(receiver_id) REFERENCES receivers(id)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS fund_utilizations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        utilization_id TEXT UNIQUE,
        fund_id INTEGER NOT NULL,
        allocation_id INTEGER,
        payment_id INTEGER,
        project_id INTEGER,
        receiver_id INTEGER,
        expense_category TEXT NOT NULL,
        expense_description TEXT NOT NULL,
        expense_amount REAL DEFAULT 0,
        expense_date DATE,
        vendor_name TEXT,
        invoice_number TEXT,
        invoice_date DATE,
        gst_amount REAL DEFAULT 0,
        payment_mode TEXT,
        bill_proof_path TEXT,
        remarks TEXT,
        verification_status TEXT DEFAULT 'Verified',
        verified_by TEXT,
        verified_at DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(fund_id) REFERENCES funds(id)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS fund_ledger_entries (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        fund_id INTEGER NOT NULL,
        transaction_type TEXT NOT NULL,
        reference_no TEXT,
        debit_amount REAL DEFAULT 0,
        credit_amount REAL DEFAULT 0,
        running_balance REAL DEFAULT 0,
        transaction_date DATE,
        description TEXT,
        created_by TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(fund_id) REFERENCES funds(id)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS fund_audit_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        username TEXT,
        action TEXT NOT NULL,
        old_value TEXT,
        new_value TEXT,
        reference_no TEXT,
        ip_address TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

} catch (PDOException $e) {
    die("Database Initialization Failed: " . $e->getMessage());
}

// Load company profile details
$profile = $db->query("SELECT * FROM company_profile LIMIT 1")->fetch();

// Helper functions
function has_permission($key, $user_id = null) {
    if (!isset($_SESSION['user_id'])) return false;
    if (($_SESSION['role'] ?? '') === 'Admin') return true; // Admin gets 100% full rights
    
    if (!isset($_SESSION['custom_permissions_arr']) || $user_id !== null) {
        global $db;
        $uid = $user_id ?: $_SESSION['user_id'];
        $raw = $db->query("SELECT custom_permissions FROM users WHERE id = " . (int)$uid)->fetchColumn();
        $parsed = !empty($raw) ? json_decode($raw, true) : [];
        if ($user_id === null) {
            $_SESSION['custom_permissions_arr'] = $parsed;
        } else {
            return !empty($parsed[$key]);
        }
    }
    
    $perms = $_SESSION['custom_permissions_arr'] ?? [];
    if (!is_array($perms)) return false;
    return !empty($perms[$key]);
}

function is_read_only($user_id = null) {
    if (!isset($_SESSION['user_id'])) return false;
    if (($_SESSION['role'] ?? '') === 'Admin') return false;
    return has_permission('view_only_mode', $user_id);
}

function return_json($data, $status = 200) {
    if (ob_get_length()) @ob_clean();
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function log_activity($description, $action_link = null, $target_user = null) {
    global $db;
    $user = $_SESSION['username'] ?? 'System';
    $desc = "[$user] " . $description;
    
    // Ensure columns exist
    try { $db->exec("ALTER TABLE activities ADD COLUMN action_link TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE activities ADD COLUMN target_user TEXT"); } catch (Exception $e) {}
    
    $stmt = $db->prepare("INSERT INTO activities (username, description, action_link, target_user) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user, $desc, $action_link, $target_user]);
}

// Universal Smart Document Suggestion Matrix Engine
function getLoanDocumentSuggestions($loan_type, $loan_sub_type = '', $employment_type = 'Salaried', $co_applicants = []) {
    $suggestions = [];
    
    // 1. Basic KYC (Always Mandatory)
    $suggestions[] = [
        'category' => 'Basic KYC',
        'doc_name' => 'PAN Card',
        'is_mandatory' => true,
        'owner_type' => 'Applicant',
        'hint' => 'Clear photo or PDF of Primary Applicant PAN Card'
    ];
    $suggestions[] = [
        'category' => 'Basic KYC',
        'doc_name' => 'Aadhaar Card (Front & Back)',
        'is_mandatory' => true,
        'owner_type' => 'Applicant',
        'hint' => 'Color copy / PDF of Aadhaar (both sides)'
    ];
    $suggestions[] = [
        'category' => 'Basic KYC',
        'doc_name' => 'Passport Size Photo',
        'is_mandatory' => true,
        'owner_type' => 'Applicant',
        'hint' => 'Recent passport size photo'
    ];
    $suggestions[] = [
        'category' => 'Basic KYC',
        'doc_name' => 'Current Address / Electricity Bill',
        'is_mandatory' => false,
        'owner_type' => 'Applicant',
        'hint' => 'Latest utility bill (under 2 months old)'
    ];

    // 2. Income & Financial Proofs (Based on Employment Type)
    $emp = strtolower($employment_type);
    if (strpos($emp, 'salaried') !== false) {
        $suggestions[] = [
            'category' => 'Income Proof',
            'doc_name' => 'Last 3 Months Salary Slips',
            'is_mandatory' => true,
            'owner_type' => 'Applicant',
            'hint' => 'Latest 3 months salary slips with company seal/stamp'
        ];
        $suggestions[] = [
            'category' => 'Income Proof',
            'doc_name' => 'Last 6 Months Salary Bank Statement',
            'is_mandatory' => true,
            'owner_type' => 'Applicant',
            'hint' => 'Original net-banking e-statement PDF (showing salary credits)'
        ];
        $suggestions[] = [
            'category' => 'Income Proof',
            'doc_name' => 'Latest Form 16 / ITR',
            'is_mandatory' => false,
            'owner_type' => 'Applicant',
            'hint' => 'Form 16 Part A & B OR 1-2 years ITR'
        ];
        $suggestions[] = [
            'category' => 'Income Proof',
            'doc_name' => 'Company ID Card',
            'is_mandatory' => false,
            'owner_type' => 'Applicant',
            'hint' => 'Employee ID card copy'
        ];
    } else {
        // Self-Employed / Business / Professional
        $suggestions[] = [
            'category' => 'Income Proof',
            'doc_name' => 'Last 2-3 Years ITR with Computation',
            'is_mandatory' => true,
            'owner_type' => 'Applicant',
            'hint' => 'Income Tax Returns with Computation sheet, Balance Sheet & P&L'
        ];
        $suggestions[] = [
            'category' => 'Income Proof',
            'doc_name' => 'Last 12 Months Bank Statement',
            'is_mandatory' => true,
            'owner_type' => 'Applicant',
            'hint' => '12 months Savings + Current A/c statements'
        ];
        $suggestions[] = [
            'category' => 'Business Proof',
            'doc_name' => 'GST Registration & 12 Months 3B Returns',
            'is_mandatory' => true,
            'owner_type' => 'Applicant',
            'hint' => 'GST Certificate & latest 12 months GSTR-3B filings'
        ];
        $suggestions[] = [
            'category' => 'Business Proof',
            'doc_name' => 'Udyam / MSME Certificate / Trade License',
            'is_mandatory' => false,
            'owner_type' => 'Applicant',
            'hint' => 'MSME/Udyam or Shop Act Establishment license'
        ];
    }

    // 3. Loan-Specific Asset / Purpose Documents
    $lt = strtolower($loan_type);
    $lst = strtolower($loan_sub_type);

    if (strpos($lt, 'home') !== false || strpos($lt, 'property') !== false || strpos($lt, 'lap') !== false) {
        if (strpos($lst, 'construction') !== false || strpos($lst, 'extension') !== false) {
            $suggestions[] = [
                'category' => 'Property / Asset Docs',
                'doc_name' => 'Land Registry / Sale Deed',
                'is_mandatory' => true,
                'owner_type' => 'Applicant',
                'hint' => 'Clear copy of registered sale deed / registry'
            ];
            $suggestions[] = [
                'category' => 'Property / Asset Docs',
                'doc_name' => 'Approved Building Map / Naksha',
                'is_mandatory' => true,
                'owner_type' => 'Applicant',
                'hint' => 'Municipal / Town planning approved layout & naksha'
            ];
            $suggestions[] = [
                'category' => 'Property / Asset Docs',
                'doc_name' => 'Construction Estimate (Architect/Engineer)',
                'is_mandatory' => true,
                'owner_type' => 'Applicant',
                'hint' => 'Detailed estimate with cost breakdown signed by civil engineer'
            ];
            $suggestions[] = [
                'category' => 'Property / Asset Docs',
                'doc_name' => 'Prior Chain of Title Deeds / Khatauni',
                'is_mandatory' => false,
                'owner_type' => 'Applicant',
                'hint' => 'Previous 13-30 years chain documents & mutation (Dakhil Kharij)'
            ];
        } elseif (strpos($lst, 'purchase') !== false || strpos($lst, 'resale') !== false || strpos($lst, 'flat') !== false) {
            $suggestions[] = [
                'category' => 'Property / Asset Docs',
                'doc_name' => 'Agreement to Sell (ATS / Biyana)',
                'is_mandatory' => true,
                'owner_type' => 'Applicant',
                'hint' => 'Registered/Notarized ATS with seller details'
            ];
            $suggestions[] = [
                'category' => 'Property / Asset Docs',
                'doc_name' => 'Builder Allotment / NOC / Possession Letter',
                'is_mandatory' => true,
                'owner_type' => 'Applicant',
                'hint' => 'Allotment letter or developer NOC for bank mortgage'
            ];
            $suggestions[] = [
                'category' => 'Property / Asset Docs',
                'doc_name' => 'Seller Previous Chain Documents',
                'is_mandatory' => true,
                'owner_type' => 'Applicant',
                'hint' => 'Title chain & past registry copies'
            ];
        } elseif (strpos($lst, 'balance') !== false || strpos($lst, 'top') !== false || strpos($lst, 'bt') !== false) {
            $suggestions[] = [
                'category' => 'Property / Asset Docs',
                'doc_name' => 'Existing Bank Sanction Letter',
                'is_mandatory' => true,
                'owner_type' => 'Applicant',
                'hint' => 'Original sanction letter from existing lender'
            ];
            $suggestions[] = [
                'category' => 'Property / Asset Docs',
                'doc_name' => 'List of Documents (LOD) & Foreclosure Letter',
                'is_mandatory' => true,
                'owner_type' => 'Applicant',
                'hint' => 'LOD and foreclosure balance certificate from current bank'
            ];
            $suggestions[] = [
                'category' => 'Income Proof',
                'doc_name' => '12 Months Loan Track Record / SOA',
                'is_mandatory' => true,
                'owner_type' => 'Applicant',
                'hint' => '12 months loan statement of account showing zero bounces'
            ];
        } else {
            // General Property / LAP
            $suggestions[] = [
                'category' => 'Property / Asset Docs',
                'doc_name' => 'Property Registry / Sale Deed',
                'is_mandatory' => true,
                'owner_type' => 'Applicant',
                'hint' => 'Complete registered property deeds'
            ];
            $suggestions[] = [
                'category' => 'Property / Asset Docs',
                'doc_name' => 'Approved Map / Tax Receipts / Khatauni',
                'is_mandatory' => false,
                'owner_type' => 'Applicant',
                'hint' => 'Latest house tax receipt & Khatauni copy'
            ];
        }
    } elseif (strpos($lt, 'vehicle') !== false || strpos($lt, 'car') !== false || strpos($lt, 'auto') !== false) {
        if (strpos($lst, 'used') !== false || strpos($lst, 'refinance') !== false) {
            $suggestions[] = [
                'category' => 'Vehicle Docs',
                'doc_name' => 'Vehicle Registration Certificate (RC Book)',
                'is_mandatory' => true,
                'owner_type' => 'Applicant',
                'hint' => 'Clear front & back copy of RC'
            ];
            $suggestions[] = [
                'category' => 'Vehicle Docs',
                'doc_name' => 'Valid Insurance & PUC Certificate',
                'is_mandatory' => true,
                'owner_type' => 'Applicant',
                'hint' => 'Comprehensive insurance policy copy'
            ];
            $suggestions[] = [
                'category' => 'Vehicle Docs',
                'doc_name' => 'Valuation Report / Seller Agreement',
                'is_mandatory' => false,
                'owner_type' => 'Applicant',
                'hint' => 'Approved valuer report or seller invoice'
            ];
        } else {
            $suggestions[] = [
                'category' => 'Vehicle Docs',
                'doc_name' => 'Dealer Proforma Invoice / Quotation',
                'is_mandatory' => true,
                'owner_type' => 'Applicant',
                'hint' => 'Official quotation on dealer letterhead with On-road breakdown'
            ];
            $suggestions[] = [
                'category' => 'Vehicle Docs',
                'doc_name' => 'Margin Money / Booking Receipt',
                'is_mandatory' => false,
                'owner_type' => 'Applicant',
                'hint' => 'Advance booking token receipt'
            ];
        }
    } elseif (strpos($lt, 'business') !== false || strpos($lt, 'commercial') !== false || strpos($lt, 'machinery') !== false) {
        if (strpos($lst, 'machinery') !== false || strpos($lst, 'equipment') !== false) {
            $suggestions[] = [
                'category' => 'Business Proof',
                'doc_name' => 'Machinery Quotation / Proforma Invoice',
                'is_mandatory' => true,
                'owner_type' => 'Applicant',
                'hint' => 'Quotation from authorized manufacturer/supplier'
            ];
        }
        $suggestions[] = [
            'category' => 'Business Proof',
            'doc_name' => 'Partnership Deed / MOA-AOA / Company Registration',
            'is_mandatory' => false,
            'owner_type' => 'Applicant',
            'hint' => 'Constitutional documents of the entity'
        ];
        $suggestions[] = [
            'category' => 'Business Proof',
            'doc_name' => 'Office / Factory Premises Proof or Rent Agreement',
            'is_mandatory' => false,
            'owner_type' => 'Applicant',
            'hint' => 'Premises electricity bill or registered rent agreement'
        ];
    } elseif (strpos($lt, 'education') !== false) {
        $suggestions[] = [
            'category' => 'Other',
            'doc_name' => 'Admission Offer Letter & Fee Schedule',
            'is_mandatory' => true,
            'owner_type' => 'Applicant',
            'hint' => 'Official admission confirmation with semester fee structure'
        ];
        $suggestions[] = [
            'category' => 'Other',
            'doc_name' => '10th, 12th & Graduation Marksheets',
            'is_mandatory' => true,
            'owner_type' => 'Applicant',
            'hint' => 'Academic records and entrance test scorecard'
        ];
    } elseif (strpos($lt, 'gold') !== false) {
        $suggestions[] = [
            'category' => 'Other',
            'doc_name' => 'Gold Ornaments Valuation Report',
            'is_mandatory' => true,
            'owner_type' => 'Applicant',
            'hint' => 'Certificate detailing gross weight, net weight & karat purity'
        ];
    }

    // 4. Co-Applicant Documents (If co-applicants exist)
    if (!empty($co_applicants)) {
        foreach ($co_applicants as $idx => $co) {
            $co_name = is_array($co) ? ($co['full_name'] ?? 'Co-Applicant #' . ($idx+1)) : (string)$co;
            $co_id = is_array($co) ? ($co['id'] ?? null) : null;
            $is_fin = is_array($co) ? (!empty($co['is_financial']) && ($co['is_financial'] === 'Yes' || $co['is_financial'] == 1)) : false;
            
            $suggestions[] = [
                'category' => 'Co-Applicant Docs',
                'doc_name' => 'PAN Card (' . $co_name . ')',
                'is_mandatory' => true,
                'owner_type' => 'CoApplicant',
                'owner_id' => $co_id,
                'coapp_name' => $co_name,
                'hint' => 'PAN Card copy of ' . $co_name
            ];
            $suggestions[] = [
                'category' => 'Co-Applicant Docs',
                'doc_name' => 'Aadhaar Card (' . $co_name . ')',
                'is_mandatory' => true,
                'owner_type' => 'CoApplicant',
                'owner_id' => $co_id,
                'coapp_name' => $co_name,
                'hint' => 'Aadhaar Card (both sides) of ' . $co_name
            ];
            $suggestions[] = [
                'category' => 'Co-Applicant Docs',
                'doc_name' => 'Passport Size Photo (' . $co_name . ')',
                'is_mandatory' => true,
                'owner_type' => 'CoApplicant',
                'owner_id' => $co_id,
                'coapp_name' => $co_name,
                'hint' => 'Recent passport size photo of ' . $co_name
            ];
            $suggestions[] = [
                'category' => 'Co-Applicant Docs',
                'doc_name' => 'Relationship Proof (' . $co_name . ')',
                'is_mandatory' => true,
                'owner_type' => 'CoApplicant',
                'owner_id' => $co_id,
                'coapp_name' => $co_name,
                'hint' => 'Marriage Certificate / Ration Card / Passport showing relationship'
            ];

            // If Financial Co-Applicant (Income Clubbing)
            if ($is_fin) {
                $suggestions[] = [
                    'category' => 'Co-Applicant Docs',
                    'doc_name' => '3 Months Salary Slips (' . $co_name . ')',
                    'is_mandatory' => true,
                    'owner_type' => 'CoApplicant',
                    'owner_id' => $co_id,
                    'coapp_name' => $co_name,
                    'hint' => 'Latest 3 months salary slips for ' . $co_name
                ];
                $suggestions[] = [
                    'category' => 'Co-Applicant Docs',
                    'doc_name' => '6 Months Bank Statement (' . $co_name . ')',
                    'is_mandatory' => true,
                    'owner_type' => 'CoApplicant',
                    'owner_id' => $co_id,
                    'coapp_name' => $co_name,
                    'hint' => '6 months salary/business bank statement of ' . $co_name
                ];
                $suggestions[] = [
                    'category' => 'Co-Applicant Docs',
                    'doc_name' => 'ITR / Form 16 (' . $co_name . ')',
                    'is_mandatory' => false,
                    'owner_type' => 'CoApplicant',
                    'owner_id' => $co_id,
                    'coapp_name' => $co_name,
                    'hint' => 'Form 16 or 2 Years ITR with Computation for ' . $co_name
                ];
            }
        }
    }

    return $suggestions;
}

// Universal Customer Loan Lifecycle Email Engine
function sendCustomerLoanEmail($applicant_id, $stage_type, $custom_data = []) {
    global $db;
    
    $stmt = $db->prepare("SELECT * FROM applicants WHERE id = ?");
    $stmt->execute([$applicant_id]);
    $app = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$app) return ['success' => false, 'error' => 'Applicant not found'];

    $recipient_email = trim($custom_data['email'] ?? ($app['email'] ?? ''));
    if (empty($recipient_email) || !filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Client email address is missing or invalid. Please update client email first.'];
    }

    $profile_stmt = $db->query("SELECT * FROM company_profile LIMIT 1");
    $profile = $profile_stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $company_name = $profile['company_name'] ?? 'BFS Financial Services';
    $company_phone = $profile['phone'] ?? '+91-9876543210';
    $company_email = $profile['email'] ?? 'support@bfscircle.com';

    $subject = '';
    $html_body = '';
    $stage_title = '';

    // Co-applicants
    $co_stmt = $db->prepare("SELECT full_name FROM co_applicants WHERE applicant_id = ?");
    $co_stmt->execute([$applicant_id]);
    $co_names = $co_stmt->fetchAll(PDO::FETCH_COLUMN);
    $co_str = !empty($co_names) ? implode(', ', $co_names) : 'None';

    // Base email wrapper styling
    $header_html = '
    <div style="max-width:620px; margin:0 auto; background:#ffffff; font-family:\'Segoe UI\', Arial, sans-serif; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; box-shadow:0 4px 20px rgba(0,0,0,0.05);">
        <div style="background:linear-gradient(135deg, #0f172a 0%, #1e293b 100%); padding:24px 30px; text-align:center; color:#ffffff;">
            <h2 style="margin:0 0 6px 0; font-size:22px; font-weight:800; letter-spacing:0.5px; color:#ffffff;">' . htmlspecialchars($company_name) . '</h2>
            <p style="margin:0; font-size:12px; color:#94a3b8; text-transform:uppercase; letter-spacing:1px;">Official Loan Origination & CRM Updates</p>
        </div>
        <div style="padding:28px 30px; color:#334155; line-height:1.6; font-size:14px;">
    ';

    $footer_html = '
            <div style="margin-top:30px; padding-top:20px; border-top:1px solid #e2e8f0; font-size:12px; color:#64748b;">
                <p style="margin:0 0 4px 0;"><strong>Need immediate assistance?</strong> Contact our loan support team at <a href="mailto:' . htmlspecialchars($company_email) . '" style="color:#2563eb; text-decoration:none;">' . htmlspecialchars($company_email) . '</a> or call <strong>' . htmlspecialchars($company_phone) . '</strong>.</p>
                <p style="margin:0; color:#94a3b8;">This is an automated system notification for Loan ID: <strong>' . htmlspecialchars($app['loan_id']) . '</strong>. Please do not reply directly to this mail.</p>
            </div>
        </div>
        <div style="background:#f8fafc; padding:12px 30px; text-align:center; font-size:11px; color:#94a3b8; border-top:1px solid #f1f5f9;">
            © ' . date('Y') . ' ' . htmlspecialchars($company_name) . '. All rights reserved.
        </div>
    </div>';

    $stage_key = strtolower(trim($stage_type));

    $domain_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . ($_SERVER['HTTP_HOST'] ?? 'localhost:8000');
    $form_print_url = $domain_url . "/print_application_form.php?id=" . $app['id'];

    $form_pdf_html = '
    <div style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:10px; padding:18px; margin:20px 0;">
        <h4 style="margin:0 0 10px 0; color:#0f172a; font-size:14px; border-bottom:1px solid #e2e8f0; padding-bottom:8px; display:flex; justify-content:space-between; align-items:center;">
            <span>📄 Official Filled Loan Application Summary</span>
            <span style="font-size:11px; background:#e0f2fe; color:#0369a1; padding:2px 8px; border-radius:12px; font-weight:700;">Verified Record</span>
        </h4>
        <table style="width:100%; border-collapse:collapse; font-size:12.5px; margin-bottom:14px;">
            <tr><td style="padding:6px 0; font-weight:bold; color:#64748b; width:38%;">Customer Name:</td><td style="padding:6px 0; color:#0f172a; font-weight:700;">' . htmlspecialchars($app['customer_name']) . '</td></tr>
            <tr><td style="padding:6px 0; font-weight:bold; color:#64748b;">Loan Reference ID:</td><td style="padding:6px 0; color:#2563eb; font-weight:700;">' . htmlspecialchars($app['loan_id']) . '</td></tr>
            <tr><td style="padding:6px 0; font-weight:bold; color:#64748b;">Loan Category & Type:</td><td style="padding:6px 0; color:#0f172a;">' . htmlspecialchars($app['loan_type']) . ' (' . htmlspecialchars($app['loan_sub_type'] ?: 'Standard') . ')</td></tr>
            <tr><td style="padding:6px 0; font-weight:bold; color:#64748b;">Requested Amount:</td><td style="padding:6px 0; color:#16a34a; font-weight:700;">₹' . number_format((float)($app['loan_amount_requested'] ?? 0)) . '</td></tr>
            <tr><td style="padding:6px 0; font-weight:bold; color:#64748b;">Mobile & Email:</td><td style="padding:6px 0; color:#0f172a;">+91 ' . htmlspecialchars($app['mobile']) . ' • ' . htmlspecialchars($app['email'] ?: 'N/A') . '</td></tr>
            <tr><td style="padding:6px 0; font-weight:bold; color:#64748b;">PAN / Aadhaar:</td><td style="padding:6px 0; color:#0f172a;">' . htmlspecialchars($app['pan_number'] ?: 'N/A') . ' / ' . htmlspecialchars($app['aadhar_number'] ?: 'N/A') . '</td></tr>
            <tr><td style="padding:6px 0; font-weight:bold; color:#64748b;">Employment / Income:</td><td style="padding:6px 0; color:#0f172a;">' . htmlspecialchars($app['employment_type'] ?: 'Salaried') . ' — ₹' . number_format((float)($app['monthly_income'] ?? 0)) . '/mo</td></tr>
            <tr><td style="padding:6px 0; font-weight:bold; color:#64748b;">Co-Applicant(s):</td><td style="padding:6px 0; color:#0f172a;">' . htmlspecialchars($co_str) . '</td></tr>
            <tr><td style="padding:6px 0; font-weight:bold; color:#64748b;">Current Address:</td><td style="padding:6px 0; color:#0f172a;">' . htmlspecialchars(($app['address'] ?? '') . ', ' . ($app['city'] ?? '') . ' ' . ($app['state'] ?? '')) . '</td></tr>
        </table>
        <div style="text-align:center; margin-top:12px;">
            <a href="' . $form_print_url . '" target="_blank" style="display:inline-block; background:#0f172a; color:#ffffff; font-weight:700; font-size:13px; padding:10px 22px; border-radius:8px; text-decoration:none; box-shadow:0 3px 10px rgba(15,23,42,0.2);">🖨️ View & Print Official Application Form (PDF)</a>
        </div>
    </div>';

    // 1. Stage: Registration
    if ($stage_key === 'registration' || $stage_key === 'register') {
        $stage_title = 'Application Registered';
        $subject = "Application Logged: Loan ID {$app['loan_id']} - {$company_name}";
        $html_body = $header_html . '
            <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:16px; margin-bottom:20px;">
                <h3 style="margin:0 0 6px 0; color:#1e40af; font-size:16px;">🎉 Loan Application Successfully Logged!</h3>
                <p style="margin:0; color:#1e3a8a; font-size:13.5px;">Dear <strong>' . htmlspecialchars($app['customer_name']) . '</strong>, your loan application has been registered in our system and assigned for verification.</p>
            </div>
            
            <table style="width:100%; border-collapse:collapse; margin-bottom:20px; font-size:13px;">
                <tr style="background:#f8fafc;"><td style="padding:10px; border:1px solid #e2e8f0; font-weight:bold; width:40%;">Loan Reference ID</td><td style="padding:10px; border:1px solid #e2e8f0; color:#0f172a; font-weight:bold;">' . htmlspecialchars($app['loan_id']) . '</td></tr>
                <tr><td style="padding:10px; border:1px solid #e2e8f0; font-weight:bold;">Loan Category</td><td style="padding:10px; border:1px solid #e2e8f0;">' . htmlspecialchars($app['loan_type']) . ' (' . htmlspecialchars($app['loan_sub_type'] ?: 'Standard') . ')</td></tr>
                <tr style="background:#f8fafc;"><td style="padding:10px; border:1px solid #e2e8f0; font-weight:bold;">Requested Amount</td><td style="padding:10px; border:1px solid #e2e8f0; color:#16a34a; font-weight:bold;">₹' . number_format((float)$app['loan_amount_requested']) . '</td></tr>
                <tr><td style="padding:10px; border:1px solid #e2e8f0; font-weight:bold;">Tenure Requested</td><td style="padding:10px; border:1px solid #e2e8f0;">' . htmlspecialchars($app['tenure_months']) . ' Months</td></tr>
                <tr style="background:#f8fafc;"><td style="padding:10px; border:1px solid #e2e8f0; font-weight:bold;">Co-Applicant(s)</td><td style="padding:10px; border:1px solid #e2e8f0;">' . htmlspecialchars($co_str) . '</td></tr>
            </table>

            ' . (!empty($custom_data['include_form_pdf']) ? $form_pdf_html : '') . '

            <h4 style="color:#0f172a; margin:16px 0 8px 0; font-size:14px;">Next Steps on Your Loan Roadmap:</h4>
            <ol style="margin:0 0 20px 0; padding-left:20px; color:#475569; font-size:13px; line-height:1.7;">
                <li><strong>Document Verification (Phase 2):</strong> Our credit team will verify your KYC and income proofs.</li>
                <li><strong>Bank Underwriting (Phase 3):</strong> Your file will be processed through top banking partners for sanction.</li>
                <li><strong>Disbursement (Phase 4):</strong> Instant final disbursement directly to your bank account.</li>
            </ol>
        ' . $footer_html;
    } 
    // 1.5. Stage: Application Form Details & PDF Confirmation
    elseif ($stage_key === 'form_confirmation' || $stage_key === 'form_pdf' || $stage_key === 'form_details') {
        $stage_title = 'Application Details & Form PDF Confirmation';
        $subject = "Official Application Form & Details Confirmation: Loan ID {$app['loan_id']} - {$company_name}";
        $custom_note = trim($custom_data['custom_note'] ?? '');
        $note_box = '';
        if (!empty($custom_note)) {
            $note_box = '
            <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:14px; margin-bottom:16px;">
                <h4 style="margin:0 0 4px 0; color:#1e40af; font-size:13px;">💬 Message from Loan Relationship Officer:</h4>
                <p style="margin:0; color:#1e3a8a; font-size:12.5px; white-space:pre-line;">' . htmlspecialchars($custom_note) . '</p>
            </div>';
        }

        $html_body = $header_html . '
            <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:16px; margin-bottom:20px;">
                <h3 style="margin:0 0 6px 0; color:#15803d; font-size:16px;">📋 Application Form & Details Registered Confirmation</h3>
                <p style="margin:0; color:#166534; font-size:13.5px;">Dear <strong>' . htmlspecialchars($app['customer_name']) . '</strong>, please review your official loan application form details recorded in our system below. You can also view or print your full application form PDF using the link provided.</p>
            </div>
            ' . $note_box . '
            ' . $form_pdf_html . '
        ' . $footer_html;
    } 
    // 2. Stage: Document Pendency / Status
    elseif ($stage_key === 'pendency' || $stage_key === 'docs_pendency') {
        $stage_title = 'Document Pendency Notice';
        $subject = "Document Status & Action Required: Loan ID {$app['loan_id']}";

        // Get received vs missing documents
        $co_stmt = $db->prepare("SELECT id, full_name, is_financial, relationship FROM co_applicants WHERE applicant_id = ?");
        $co_stmt->execute([$applicant_id]);
        $co_apps = $co_stmt->fetchAll(PDO::FETCH_ASSOC);
        $suggestions = getLoanDocumentSuggestions($app['loan_type'] ?? '', $app['loan_sub_type'] ?? '', $app['employment_type'] ?? 'Salaried', $co_apps);

        $doc_stmt = $db->prepare("SELECT * FROM applicant_documents WHERE applicant_id = ?");
        $doc_stmt->execute([$applicant_id]);
        $docs = $doc_stmt->fetchAll(PDO::FETCH_ASSOC);

        $received_html = '';
        if (!empty($docs)) {
            foreach ($docs as $d) {
                $status_tag = $d['status'] === 'Verified' ? '<span style="color:#16a34a; font-weight:bold;">✓ Verified</span>' : '<span style="color:#eab308; font-weight:bold;">⏳ Under Review</span>';
                $received_html .= '<li style="margin-bottom:4px;"><strong>' . htmlspecialchars($d['document_name']) . '</strong> (' . htmlspecialchars($d['document_category']) . ') — ' . $status_tag . '</li>';
            }
        } else {
            $received_html = '<li style="color:#64748b;">No documents received yet.</li>';
        }

        $missing_html = '';
        foreach ($suggestions as $sug) {
            $found = false;
            foreach ($docs as $d) {
                if ($d['document_category'] === $sug['category'] || stripos($d['document_name'], $sug['doc_name']) !== false) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $mand = $sug['is_mandatory'] ? '<span style="color:#dc2626; font-weight:bold;">[Mandatory]</span>' : '<span style="color:#64748b;">[Optional]</span>';
                $missing_html .= '<li style="margin-bottom:4px;"><strong>' . htmlspecialchars($sug['doc_name']) . '</strong> ' . $mand . '<br><span style="font-size:11.5px; color:#64748b;">' . htmlspecialchars($sug['hint'] ?? '') . '</span></li>';
            }
        }
        if (empty($missing_html)) {
            $missing_html = '<li style="color:#16a34a;">All standard checklist documents are received!</li>';
        }

        $custom_note = trim($custom_data['custom_note'] ?? '');
        $note_box = '';
        if (!empty($custom_note)) {
            $note_box = '
            <div style="background:#fffbeb; border:1px solid #fcd34d; border-radius:8px; padding:16px; margin:20px 0;">
                <h4 style="margin:0 0 6px 0; color:#b45309; font-size:13.5px;">📝 Special Instructions from Credit Officer:</h4>
                <p style="margin:0; color:#92400e; font-size:13px; white-space:pre-line;">' . htmlspecialchars($custom_note) . '</p>
            </div>';
        }

        $html_body = $header_html . '
            <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:8px; padding:16px; margin-bottom:20px;">
                <h3 style="margin:0 0 6px 0; color:#b91c1c; font-size:16px;">⚠️ Action Required: Document Pendency Update</h3>
                <p style="margin:0; color:#991b1b; font-size:13.5px;">Dear <strong>' . htmlspecialchars($app['customer_name']) . '</strong>, we are reviewing your loan file (Loan ID: <strong>' . htmlspecialchars($app['loan_id']) . '</strong>). Please check the document status below to expedite your loan approval.</p>
            </div>

            ' . $note_box . '

            <div style="margin-bottom:20px;">
                <h4 style="color:#16a34a; font-size:14px; margin:0 0 8px 0;">✅ Received / Submitted Documents:</h4>
                <ul style="margin:0; padding-left:20px; font-size:13px; color:#334155;">
                    ' . $received_html . '
                </ul>
            </div>

            <div style="margin-bottom:20px; background:#fff1f2; padding:16px; border-radius:8px; border:1px solid #ffe4e6;">
                <h4 style="color:#be123c; font-size:14px; margin:0 0 8px 0;">⚠️ Pending / Required Documents:</h4>
                <ul style="margin:0; padding-left:20px; font-size:13px; color:#4c0519;">
                    ' . $missing_html . '
                </ul>
            </div>

            <p style="font-size:13px; color:#475569;">Please share the pending documents at your earliest convenience via WhatsApp or email to your loan manager to proceed with bank underwriting.</p>
        ' . $footer_html;
    }
    // 3. Stage: Documents Complete / Verification
    elseif ($stage_key === 'verification' || $stage_key === 'docs_complete' || $stage_key === 'docs') {
        $stage_title = 'Documents Verified';
        $subject = "Documents Verified & File Dispatched to Bank: Loan ID {$app['loan_id']}";
        $html_body = $header_html . '
            <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:16px; margin-bottom:20px;">
                <h3 style="margin:0 0 6px 0; color:#15803d; font-size:16px;">✓ All Documents Successfully Verified!</h3>
                <p style="margin:0; color:#166534; font-size:13.5px;">Dear <strong>' . htmlspecialchars($app['customer_name']) . '</strong>, we are pleased to inform you that your document package for Loan ID <strong>' . htmlspecialchars($app['loan_id']) . '</strong> is 100% complete and verified.</p>
            </div>

            <p style="font-size:13.5px; color:#334155;">Your loan file has now been dispatched to our banking and underwriting partner for final credit sanction and valuation.</p>
            
            <table style="width:100%; border-collapse:collapse; margin-bottom:20px; font-size:13px;">
                <tr style="background:#f8fafc;"><td style="padding:10px; border:1px solid #e2e8f0; font-weight:bold; width:40%;">Loan ID</td><td style="padding:10px; border:1px solid #e2e8f0; font-weight:bold;">' . htmlspecialchars($app['loan_id']) . '</td></tr>
                <tr><td style="padding:10px; border:1px solid #e2e8f0; font-weight:bold;">Loan Category</td><td style="padding:10px; border:1px solid #e2e8f0;">' . htmlspecialchars($app['loan_type']) . '</td></tr>
                <tr style="background:#f8fafc;"><td style="padding:10px; border:1px solid #e2e8f0; font-weight:bold;">Current Status</td><td style="padding:10px; border:1px solid #e2e8f0; color:#2563eb; font-weight:bold;">Bank Credit Processing & Legal Check</td></tr>
                <tr><td style="padding:10px; border:1px solid #e2e8f0; font-weight:bold;">Expected TAT</td><td style="padding:10px; border:1px solid #e2e8f0;">2 - 4 Working Days</td></tr>
            </table>

            <p style="font-size:13px; color:#64748b;">You will receive another update as soon as the bank sanction certificate is released.</p>
        ' . $footer_html;
    }
    // Stage: Bank Login Fee Receipt
    elseif ($stage_key === 'login_fee' || $stage_key === 'loginfee' || $stage_key === 'login_fee_receipt') {
        $stage_title = 'Login Fee Receipt';
        $fee_amt = (float)($custom_data['login_fee_amount'] ?? ($app['login_fee_amount'] ?? 0));
        $fee_bank = $custom_data['login_fee_bank'] ?? ($app['login_fee_bank'] ?? ($app['sanction_bank'] ?? 'Partner Bank'));
        $fee_mode = $custom_data['login_fee_payment_mode'] ?? ($app['login_fee_payment_mode'] ?? 'UPI');
        $fee_ref = $custom_data['login_fee_ref_no'] ?? ($app['login_fee_ref_no'] ?? 'TXN-' . time());
        $fee_date = $custom_data['login_fee_paid_date'] ?? ($app['login_fee_paid_date'] ?? date('d M Y'));

        $subject = "Payment Receipt: Bank Login Fee Received for Loan ID {$app['loan_id']} - {$company_name}";
        $html_body = $header_html . '
            <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:10px; padding:20px; color:#1e40af; margin-bottom:20px; text-align:center;">
                <h3 style="margin:0 0 6px 0; font-size:18px; font-weight:800; color:#1e40af;">💳 BANK LOGIN FEE PAYMENT RECEIVED</h3>
                <p style="margin:0; font-size:13.5px; color:#1e3a8a;">Dear <strong>' . htmlspecialchars($app['customer_name']) . '</strong>, we have received your Bank Processing & Login Fee. Your file is now officially logged in the bank for credit appraisal.</p>
            </div>

            <table style="width:100%; border-collapse:collapse; margin-bottom:20px; font-size:13px;">
                <tr style="background:#f8fafc;"><td style="padding:10px; border:1px solid #e2e8f0; font-weight:bold; width:40%;">Login Fee Paid</td><td style="padding:10px; border:1px solid #e2e8f0; color:#16a34a; font-weight:bold; font-size:15px;">₹' . number_format($fee_amt) . '</td></tr>
                <tr><td style="padding:10px; border:1px solid #e2e8f0; font-weight:bold;">Target Bank / NBFC</td><td style="padding:10px; border:1px solid #e2e8f0; font-weight:bold; color:#0f172a;">' . htmlspecialchars($fee_bank) . '</td></tr>
                <tr style="background:#f8fafc;"><td style="padding:10px; border:1px solid #e2e8f0; font-weight:bold;">Payment Mode</td><td style="padding:10px; border:1px solid #e2e8f0;">' . htmlspecialchars($fee_mode) . '</td></tr>
                <tr><td style="padding:10px; border:1px solid #e2e8f0; font-weight:bold;">UTR / Transaction Ref</td><td style="padding:10px; border:1px solid #e2e8f0; font-weight:bold; color:#2563eb;">' . htmlspecialchars($fee_ref) . '</td></tr>
                <tr style="background:#f8fafc;"><td style="padding:10px; border:1px solid #e2e8f0; font-weight:bold;">Payment Date</td><td style="padding:10px; border:1px solid #e2e8f0;">' . htmlspecialchars($fee_date) . '</td></tr>
                <tr><td style="padding:10px; border:1px solid #e2e8f0; font-weight:bold;">Loan ID Reference</td><td style="padding:10px; border:1px solid #e2e8f0; font-weight:bold;">' . htmlspecialchars($app['loan_id']) . '</td></tr>
            </table>

            ' . (!empty($custom_data['custom_note']) ? '
            <div style="background:#fffbeb; border:1px solid #fcd34d; border-radius:8px; padding:14px; margin-bottom:20px;">
                <h4 style="margin:0 0 6px 0; color:#b45309; font-size:13px;">📝 Officer Note:</h4>
                <p style="margin:0; color:#92400e; font-size:12.5px;">' . htmlspecialchars($custom_data['custom_note']) . '</p>
            </div>' : '') . '

            <h4 style="color:#0f172a; margin:16px 0 8px 0; font-size:14px;">Next Milestone: Bank Underwriting & Sanction</h4>
            <p style="margin:0 0 16px 0; color:#475569; font-size:13px;">Bank credit and technical evaluation teams will now process the valuation and eligibility check. You will be updated when the loan sanction is approved.</p>
        ' . $footer_html;
    }
    // 4. Stage: Loan Sanctioned
    elseif ($stage_key === 'sanction' || $stage_key === 'sanctioned') {
        $stage_title = 'Loan Sanctioned';
        $subject = "🎉 Congratulations! Your Loan Has Been Sanctioned: ID {$app['loan_id']}";
        
        $s_amt = (float)($custom_data['sanctioned_amount'] ?? ($app['sanctioned_amount'] ?? $app['loan_amount_requested']));
        $s_bank = $custom_data['sanction_bank'] ?? ($app['sanction_bank'] ?? 'Partner Bank');
        $s_roi = (float)($custom_data['sanction_roi'] ?? ($app['sanction_roi'] ?? $app['interest_rate']));
        $s_tenure = (int)($custom_data['sanction_tenure'] ?? ($app['sanction_tenure'] ?? $app['tenure_months']));
        $s_emi = (float)($custom_data['sanction_emi'] ?? ($app['sanction_emi'] ?? $app['emi']));
        $s_ref = $custom_data['sanction_ref_no'] ?? ($app['sanction_ref_no'] ?? 'SAN-' . $app['loan_id']);
        $s_date = $custom_data['sanction_date'] ?? ($app['sanction_date'] ?? date('d M Y'));

        $html_body = $header_html . '
            <div style="background:linear-gradient(135deg, #059669 0%, #10b981 100%); border-radius:10px; padding:20px; color:#ffffff; margin-bottom:24px; text-align:center;">
                <h2 style="margin:0 0 6px 0; font-size:20px; font-weight:800; color:#ffffff;">🎉 CONGRATULATIONS! LOAN SANCTIONED</h2>
                <p style="margin:0; font-size:14px; opacity:0.95;">Dear ' . htmlspecialchars($app['customer_name']) . ', your loan application has been officially approved and sanctioned.</p>
            </div>

            <table style="width:100%; border-collapse:collapse; margin-bottom:20px; font-size:13px;">
                <tr style="background:#f8fafc;"><td style="padding:10px; border:1px solid #e2e8f0; font-weight:bold; width:40%;">Sanctioned Loan Amount</td><td style="padding:10px; border:1px solid #e2e8f0; color:#059669; font-weight:bold; font-size:16px;">₹' . number_format($s_amt) . '</td></tr>
                <tr><td style="padding:10px; border:1px solid #e2e8f0; font-weight:bold;">Sanctioning Bank / NBFC</td><td style="padding:10px; border:1px solid #e2e8f0; font-weight:bold; color:#0f172a;">' . htmlspecialchars($s_bank) . '</td></tr>
                <tr style="background:#f8fafc;"><td style="padding:10px; border:1px solid #e2e8f0; font-weight:bold;">Rate of Interest (ROI)</td><td style="padding:10px; border:1px solid #e2e8f0; font-weight:bold;">' . ($s_roi ? $s_roi . '% p.a.' : 'Standard Floating') . '</td></tr>
                <tr><td style="padding:10px; border:1px solid #e2e8f0; font-weight:bold;">Approved Tenure</td><td style="padding:10px; border:1px solid #e2e8f0;">' . $s_tenure . ' Months</td></tr>
                ' . ($s_emi > 0 ? '<tr style="background:#f8fafc;"><td style="padding:10px; border:1px solid #e2e8f0; font-weight:bold;">Approx. Monthly EMI</td><td style="padding:10px; border:1px solid #e2e8f0; font-weight:bold; color:#2563eb;">₹' . number_format($s_emi) . '</td></tr>' : '') . '
                <tr><td style="padding:10px; border:1px solid #e2e8f0; font-weight:bold;">Sanction Reference No.</td><td style="padding:10px; border:1px solid #e2e8f0;">' . htmlspecialchars($s_ref) . '</td></tr>
                <tr style="background:#f8fafc;"><td style="padding:10px; border:1px solid #e2e8f0; font-weight:bold;">Sanction Date</td><td style="padding:10px; border:1px solid #e2e8f0;">' . htmlspecialchars($s_date) . '</td></tr>
            </table>

            <h4 style="color:#0f172a; margin:16px 0 8px 0; font-size:14px;">Next Step: Disbursement</h4>
            <p style="margin:0 0 16px 0; color:#475569; font-size:13px;">Our executive will get in touch with you shortly to coordinate the loan agreement signing, NACH mandate setup, and final fund disbursement into your bank account.</p>
        ' . $footer_html;
    }
    // 5. Stage: Loan Disbursed (Celebration)
    elseif ($stage_key === 'disbursed' || $stage_key === 'disbursement') {
        $stage_title = 'Loan Disbursed';
        $subject = "🎊 Grand Celebrations! Your Loan Has Been Disbursed: ID {$app['loan_id']}";
        $disb_amt = (float)($custom_data['disbursed_amount'] ?? ($app['sanctioned_amount'] ?? $app['loan_amount_requested']));
        $disb_bank = $custom_data['disbursed_bank'] ?? ($app['sanction_bank'] ?? 'Bank');
        $disb_date = $custom_data['disbursed_date'] ?? date('d M Y');

        $html_body = $header_html . '
            <div style="background:linear-gradient(135deg, #1e1b4b 0%, #312e81 100%); border-radius:12px; padding:26px; color:#ffffff; margin-bottom:24px; text-align:center; box-shadow:0 8px 24px rgba(49,46,129,0.25);">
                <div style="font-size:32px; margin-bottom:6px;">🎊 🎈 🎉</div>
                <h2 style="margin:0 0 6px 0; font-size:22px; font-weight:800; color:#fbbf24;">LOAN SUCCESSFULLY DISBURSED!</h2>
                <p style="margin:0; font-size:14px; color:#e0e7ff;">Heartiest congratulations to you and your family, ' . htmlspecialchars($app['customer_name']) . '!</p>
            </div>

            <p style="font-size:14px; color:#334155;">We are delighted to confirm that your loan proceeds have been successfully disbursed.</p>

            <table style="width:100%; border-collapse:collapse; margin-bottom:20px; font-size:13px;">
                <tr style="background:#f8fafc;"><td style="padding:10px; border:1px solid #e2e8f0; font-weight:bold; width:40%;">Disbursed Amount</td><td style="padding:10px; border:1px solid #e2e8f0; color:#16a34a; font-weight:bold; font-size:16px;">₹' . number_format($disb_amt) . '</td></tr>
                <tr><td style="padding:10px; border:1px solid #e2e8f0; font-weight:bold;">Disbursement Partner</td><td style="padding:10px; border:1px solid #e2e8f0; font-weight:bold;">' . htmlspecialchars($disb_bank) . '</td></tr>
                <tr style="background:#f8fafc;"><td style="padding:10px; border:1px solid #e2e8f0; font-weight:bold;">Loan ID</td><td style="padding:10px; border:1px solid #e2e8f0; font-weight:bold;">' . htmlspecialchars($app['loan_id']) . '</td></tr>
                <tr><td style="padding:10px; border:1px solid #e2e8f0; font-weight:bold;">Disbursal Date</td><td style="padding:10px; border:1px solid #e2e8f0;">' . htmlspecialchars($disb_date) . '</td></tr>
            </table>

            <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:16px; margin-bottom:20px;">
                <h4 style="margin:0 0 6px 0; color:#166534; font-size:13.5px;">🌟 Thank You for Choosing ' . htmlspecialchars($company_name) . '!</h4>
                <p style="margin:0; color:#14532d; font-size:13px;">It has been our absolute pleasure assisting you. For any future financial requirements, top-up loans, or loan balance transfers, our dedicated desk is always at your service.</p>
            </div>
        ' . $footer_html;
    } else {
        return ['success' => false, 'error' => 'Invalid email stage type'];
    }

    // Attempt delivery via PHPMailer or simulated/mail fallback
    $sent = false;
    $delivery_note = '';

    if (!empty($profile['smtp_host']) && !empty($profile['smtp_username'])) {
        try {
            require_once __DIR__ . '/libs/PHPMailer/Exception.php';
            require_once __DIR__ . '/libs/PHPMailer/PHPMailer.php';
            require_once __DIR__ . '/libs/PHPMailer/SMTP.php';

            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $profile['smtp_host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $profile['smtp_username'];
            $mail->Password   = $profile['smtp_password'];
            if (strtolower($profile['smtp_encryption'] ?? '') === 'tls') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            } elseif (strtolower($profile['smtp_encryption'] ?? '') === 'ssl') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            }
            $mail->Port       = (int)($profile['smtp_port'] ?: 587);
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            $from_name = !empty($profile['contact_person']) ? $profile['contact_person'] . " - " . $company_name : $company_name;
            $mail->setFrom($profile['smtp_username'], $from_name);
            $mail->addAddress($recipient_email, $app['customer_name']);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $html_body;
            $mail->AltBody = strip_tags($html_body);

            $mail->send();
            $sent = true;
            $delivery_note = "Dispatched via SMTP to $recipient_email";
        } catch (\Exception $e) {
            $delivery_note = "SMTP Delivery error: " . $e->getMessage() . ". Fallback to mail system.";
            $headers = "MIME-Version: 1.0\r\nContent-type:text/html;charset=UTF-8\r\nFrom: " . $company_name . " <noreply@bfscircle.com>\r\n";
            @mail($recipient_email, $subject, $html_body, $headers);
            $sent = true;
        }
    } else {
        // Fallback or simulated delivery
        $headers = "MIME-Version: 1.0\r\nContent-type:text/html;charset=UTF-8\r\nFrom: " . $company_name . " <noreply@bfscircle.com>\r\n";
        @mail($recipient_email, $subject, $html_body, $headers);
        $sent = true;
        $delivery_note = "Dispatched to $recipient_email";
    }

    log_activity("Sent {$stage_title} Email to {$app['customer_name']} ($recipient_email)", "search_track.php?id=$applicant_id");

    return [
        'success' => true,
        'message' => "{$stage_title} email successfully sent to {$app['customer_name']} ({$recipient_email})!",
        'stage' => $stage_type,
        'subject' => $subject,
        'delivery_note' => $delivery_note
    ];
}

/**
 * Universal High-Grade HTML System Email Sender
 * Wraps body in corporate BFS Financial Services letterhead card and sends via SMTP / mail()
 */
// Listmonk Campaign Engine Configuration
if (!defined('LISTMONK_API_URL')) define('LISTMONK_API_URL', getenv('LISTMONK_API_URL') ?: 'http://127.0.0.1:9000');
if (!defined('LISTMONK_API_USER')) define('LISTMONK_API_USER', getenv('LISTMONK_API_USER') ?: 'bfs_crm_api');
if (!defined('LISTMONK_API_TOKEN')) define('LISTMONK_API_TOKEN', getenv('LISTMONK_API_TOKEN') ?: 'bfs_listmonk_secret_key_789456');

// Default Listmonk List IDs for BFS Circle
if (!defined('LM_LIST_HOMELOAN')) define('LM_LIST_HOMELOAN', 3);
if (!defined('LM_LIST_LAP')) define('LM_LIST_LAP', 4);
if (!defined('LM_LIST_PERSONAL_BIZ')) define('LM_LIST_PERSONAL_BIZ', 5);
if (!defined('LM_LIST_DISBURSED')) define('LM_LIST_DISBURSED', 6);
if (!defined('LM_LIST_PARTNERS')) define('LM_LIST_PARTNERS', 7);

/**
 * Universal Sync Helper: CRM Contact -> Listmonk Smart List
 */
function syncContactToListmonk($entity_type, $entity_id) {
    global $db;
    require_once __DIR__ . '/libs/ListmonkClient.php';
    $client = new ListmonkClient();

    $email = '';
    $name = '';
    $lists = [];
    $attribs = [];

    if ($entity_type === 'lead') {
        $stmt = $db->prepare("SELECT * FROM leads WHERE id = ?");
        $stmt->execute([$entity_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || empty($row['email']) || !filter_var($row['email'], FILTER_VALIDATE_EMAIL)) return false;
        if (!empty($row['is_marketing_optout'])) return false;

        $email = $row['email'];
        $name = $row['lead_name'] ?: 'Valued Customer';
        $req = strtolower($row['requirement'] ?? '');

        if (strpos($req, 'home') !== false) {
            $lists[] = LM_LIST_HOMELOAN;
        } elseif (strpos($req, 'lap') !== false || strpos($req, 'property') !== false) {
            $lists[] = LM_LIST_LAP;
        } elseif (strpos($req, 'personal') !== false || strpos($req, 'business') !== false) {
            $lists[] = LM_LIST_PERSONAL_BIZ;
        } else {
            $lists[] = LM_LIST_HOMELOAN;
        }

        $attribs = [
            'entity_type' => 'lead',
            'lead_id' => $row['id'],
            'phone' => $row['mobile'] ?? '',
            'city' => $row['location'] ?? '',
            'loan_amount' => $row['loan_amount'] ?? '',
            'stage' => $row['stage'] ?? '',
            'source' => $row['lead_source'] ?? 'CRM Direct'
        ];
    } elseif ($entity_type === 'applicant') {
        $stmt = $db->prepare("SELECT * FROM applicants WHERE id = ?");
        $stmt->execute([$entity_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || empty($row['email']) || !filter_var($row['email'], FILTER_VALIDATE_EMAIL)) return false;
        if (!empty($row['is_marketing_optout'])) return false;

        $email = $row['email'];
        $name = $row['customer_name'] ?: 'Valued Borrower';
        $ltype = strtolower($row['loan_type'] ?? '');

        if (strpos($ltype, 'home') !== false) {
            $lists[] = LM_LIST_HOMELOAN;
        } elseif (strpos($ltype, 'lap') !== false || strpos($ltype, 'property') !== false) {
            $lists[] = LM_LIST_LAP;
        } else {
            $lists[] = LM_LIST_PERSONAL_BIZ;
        }

        if ((float)($row['disbursed_amount'] ?? 0) > 0 || strtolower($row['overall_status'] ?? '') === 'disbursed') {
            $lists[] = LM_LIST_DISBURSED;
        }

        $attribs = [
            'entity_type' => 'applicant',
            'loan_id' => $row['loan_id'] ?? '',
            'phone' => $row['mobile'] ?? '',
            'city' => $row['city'] ?? '',
            'cibil' => $row['cibil_score'] ?? '',
            'status' => $row['overall_status'] ?? '',
            'disbursed_amount' => $row['disbursed_amount'] ?? ''
        ];
    } elseif ($entity_type === 'referral') {
        $stmt = $db->prepare("SELECT * FROM referrals WHERE id = ?");
        $stmt->execute([$entity_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || empty($row['email']) || !filter_var($row['email'], FILTER_VALIDATE_EMAIL)) return false;
        if (!empty($row['is_marketing_optout'])) return false;

        $email = $row['email'];
        $name = $row['full_name'] ?: 'BFS Partner';
        $lists[] = LM_LIST_PARTNERS;
        $attribs = [
            'entity_type' => 'partner',
            'referral_id' => $row['referral_id'] ?? '',
            'phone' => $row['mobile'] ?? '',
            'partner_type' => $row['referrer_type'] ?? 'DSA',
            'city' => $row['city_state'] ?? ''
        ];
    }

    if (empty($email) || empty($lists)) return false;

    $res = $client->syncSubscriber($email, $name, $lists, $attribs);
    if (!empty($res['success'])) {
        $subId = $res['data']['id'] ?? null;
        try {
            $db->prepare("INSERT INTO listmonk_sync_logs (entity_type, entity_id, email, listmonk_sub_id, lists_synced, status) VALUES (?, ?, ?, ?, ?, 'synced')")
               ->execute([$entity_type, $entity_id, $email, $subId, json_encode($lists)]);
        } catch (Exception $e) {}
        return true;
    }
    return false;
}

function sendUniversalEmail($recipient_email, $recipient_name, $subject, $body_html, $activity_context = 'Email Engine') {
    global $db;
    
    if (empty($recipient_email) || !filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Recipient email address is missing or invalid.'];
    }

    $profile_stmt = $db->query("SELECT * FROM company_profile LIMIT 1");
    $profile = $profile_stmt ? ($profile_stmt->fetch(PDO::FETCH_ASSOC) ?: []) : [];
    $company_name = $profile['company_name'] ?? 'BFS Financial Services';
    $company_phone = $profile['phone'] ?? '+91-9876543210';
    $company_email = $profile['email'] ?? 'support@bfscircle.com';
    $company_gstin = $profile['gstin'] ?? '07AAAAA0000A1Z5';
    $company_pan = $profile['pan'] ?? 'AAAAA0000A';
    $company_address = $profile['address'] ?? 'Corporate Office, Financial District, New Delhi';

    // Wrap body_html inside corporate letterhead template if it isn't already a full HTML document
    if (stripos($body_html, '<html') === false) {
        $formatted_body = '
        <div style="max-width:640px; margin:0 auto; background:#ffffff; font-family:\'Segoe UI\', Roboto, Arial, sans-serif; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; box-shadow:0 4px 20px rgba(0,0,0,0.06);">
            <!-- Corporate Dark Slate Header -->
            <div style="background:linear-gradient(135deg, #0f172a 0%, #1e293b 100%); padding:24px 30px; color:#ffffff;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:800; letter-spacing:0.5px; color:#ffffff;">' . htmlspecialchars($company_name) . '</h2>
                        <p style="margin:0; font-size:11px; color:#94a3b8; text-transform:uppercase; letter-spacing:1px; font-weight:600;">Official Financial Dispatch & Payout Cell</p>
                    </div>
                    <div style="text-align:right; font-size:11px; color:#cbd5e1;">
                        <div>GSTIN: <strong>' . htmlspecialchars($company_gstin) . '</strong></div>
                        <div>PAN: <strong>' . htmlspecialchars($company_pan) . '</strong></div>
                    </div>
                </div>
            </div>
            
            <!-- Email Body Container -->
            <div style="padding:28px 30px; color:#334155; line-height:1.6; font-size:13.5px;">
                ' . $body_html . '
                
                <div style="margin-top:28px; padding-top:18px; border-top:1px solid #e2e8f0; font-size:12px; color:#64748b;">
                    <p style="margin:0 0 4px 0;"><strong>Questions regarding this transaction?</strong></p>
                    <p style="margin:0;">Reach out to Accounts Desk at <a href="mailto:' . htmlspecialchars($company_email) . '" style="color:#2563eb; text-decoration:none; font-weight:600;">' . htmlspecialchars($company_email) . '</a> or Call <strong>' . htmlspecialchars($company_phone) . '</strong>.</p>
                </div>
            </div>
            
            <!-- Corporate Footer -->
            <div style="background:#f8fafc; padding:14px 30px; border-top:1px solid #f1f5f9; font-size:11px; color:#94a3b8; line-height:1.5;">
                <div style="margin-bottom:4px;"><strong>' . htmlspecialchars($company_name) . '</strong> • ' . htmlspecialchars($company_address) . '</div>
                <div>This is an automated system notification. Confidentiality Notice: This message is intended only for the use of the addressee.</div>
            </div>
        </div>';
    } else {
        $formatted_body = $body_html;
    }

    $sent = false;
    $delivery_note = '';

    if (!empty($profile['smtp_host']) && !empty($profile['smtp_username'])) {
        $smtp_pwd = !empty($profile['smtp_password']) ? $profile['smtp_password'] : 'agft ujxn vcsc rgyf';
        $smtp_clean_pwd = str_replace(' ', '', $smtp_pwd);
        $from_name = !empty($profile['contact_person']) ? $profile['contact_person'] . " - " . $company_name : $company_name;

        try {
            require_once __DIR__ . '/libs/PHPMailer/Exception.php';
            require_once __DIR__ . '/libs/PHPMailer/PHPMailer.php';
            require_once __DIR__ . '/libs/PHPMailer/SMTP.php';

            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $profile['smtp_host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $profile['smtp_username'];
            $mail->Password   = $smtp_clean_pwd;
            if (strtolower($profile['smtp_encryption'] ?? '') === 'ssl' || (int)($profile['smtp_port'] ?? 0) === 465) {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port       = 465;
            } else {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;
            }
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];
            $mail->Timeout    = 15;

            $mail->setFrom($profile['smtp_username'], $from_name);
            $mail->addAddress($recipient_email, $recipient_name ?: $recipient_email);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $formatted_body;
            $mail->AltBody = strip_tags($formatted_body);

            $mail->send();
            $sent = true;
            $delivery_note = "Dispatched via SMTP to $recipient_email";
        } catch (\Exception $e) {
            // Dual-Port Resilience: If 587 (TLS) fails on VPS, attempt port 465 (SSL)
            try {
                $mail465 = new \PHPMailer\PHPMailer\PHPMailer(true);
                $mail465->isSMTP();
                $mail465->Host       = 'smtp.gmail.com';
                $mail465->SMTPAuth   = true;
                $mail465->Username   = $profile['smtp_username'] ?: 'bfscaptain@gmail.com';
                $mail465->Password   = $smtp_clean_pwd;
                $mail465->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                $mail465->Port       = 465;
                $mail465->SMTPOptions = [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    ]
                ];
                $mail465->Timeout    = 15;
                $mail465->setFrom($mail465->Username, $from_name);
                $mail465->addAddress($recipient_email, $recipient_name ?: $recipient_email);
                $mail465->isHTML(true);
                $mail465->Subject = $subject;
                $mail465->Body    = $formatted_body;
                $mail465->AltBody = strip_tags($formatted_body);
                $mail465->send();
                $sent = true;
                $delivery_note = "Dispatched via SMTP Fallback (Port 465 SSL) to $recipient_email";
            } catch (\Exception $e465) {
                error_log("CRITICAL SMTP FAIL: Port 587 [" . $e->getMessage() . "] and Port 465 [" . $e465->getMessage() . "]");
                $delivery_note = "SMTP Error: " . $e->getMessage() . ". Fallback to mail().";
                $headers = "MIME-Version: 1.0\r\nContent-type:text/html;charset=UTF-8\r\nFrom: " . $company_name . " <noreply@bfscircle.com>\r\n";
                @mail($recipient_email, $subject, $formatted_body, $headers);
                $sent = true;
            }
        }
    } else {
        $headers = "MIME-Version: 1.0\r\nContent-type:text/html;charset=UTF-8\r\nFrom: " . $company_name . " <noreply@bfscircle.com>\r\n";
        @mail($recipient_email, $subject, $formatted_body, $headers);
        $sent = true;
        $delivery_note = "Dispatched via mail() to $recipient_email";
    }

    log_activity("Email Dispatched to {$recipient_email} [Subject: {$subject}]", $activity_context);

    return [
        'success' => true,
        'message' => "Email dispatched successfully to {$recipient_email}",
        'delivery_note' => $delivery_note
    ];
}

// Page access & API router
$current_page = basename($_SERVER['PHP_SELF']);
$is_in_rm_dir = (strpos($_SERVER['SCRIPT_NAME'], '/rm/') !== false);
$is_in_staff_dir = (strpos($_SERVER['SCRIPT_NAME'], '/staff/') !== false);
$is_in_agent_dir = (strpos($_SERVER['SCRIPT_NAME'], '/agent/') !== false);
$is_in_partner_dir = (strpos($_SERVER['SCRIPT_NAME'], '/partner/') !== false);
$is_in_builder_dir = (strpos($_SERVER['SCRIPT_NAME'], '/builder/') !== false);
$is_in_ca_dir = (strpos($_SERVER['SCRIPT_NAME'], '/ca/') !== false);
$is_in_client_vault = (strpos($_SERVER['SCRIPT_NAME'], '/client_vault/') !== false);
$is_in_subfolder = $is_in_rm_dir || $is_in_staff_dir || $is_in_agent_dir || $is_in_partner_dir || $is_in_builder_dir || $is_in_ca_dir || $is_in_client_vault || defined('IS_SUBFOLDER');

// Authentication check for page load (excluding login.php, forgot_password.php, apply.php, track.php and CLI)
$public_apis = ['login', 'verify_admin_2fa', 'resend_admin_2fa', 'listmonk_webhook', 'client_upload_document', 'client_submit_query', 'client_request_callback', 'client_confirm_verification', 'client_accept_sanction', 'client_refer_friend', 'forgot_password_request_otp', 'forgot_password_verify_otp', 'forgot_password_complete', 'inbound_email_webhook'];
if (php_sapi_name() !== 'cli' && !isset($_SESSION['user_id']) && $current_page !== 'login.php' && $current_page !== 'forgot_password.php' && $current_page !== 'apply.php' && $current_page !== 'track.php' && $current_page !== 'api_website_lead.php') {
    // If not logged in and calling an API, return unauthorized
    if (isset($_GET['api'])) {
        if (!in_array($_GET['api'], $public_apis)) {
            return_json(['error' => 'Unauthorized Access. Please login.'], 401);
        }
    } else {
        $redirect_prefix = $is_in_subfolder ? '../' : '';
        header("Location: " . $redirect_prefix . "login.php");
        exit;
    }
}

// Redirect and protect pages based on user roles (only for page loads, not APIs or CLI)
if (php_sapi_name() !== 'cli' && isset($_SESSION['user_id']) && !isset($_GET['api'])) {
    $role = $_SESSION['role'] ?? 'Staff';
    
    // STRICT SECURITY ISOLATION: 
    // Allow RM / Staff to access dedicated tools, protect admin-only pages
    $staff_allowed_pages = [
        'rm_leaderboard.php', 'staff_ranking.php', 'leads.php', 'pre_leads.php', 'add_lead.php', 'view_lead.php', 'edit_lead.php', 'view_pre_lead.php',
        'add_applicant.php', 'search_track.php', 'bankers_list.php', 'reminders.php', 
        'calculators.php', 'calc_eligibility.php', 'calc_emi.php', 'calc_foir.php', 'calc_ltv.php', 'calc_compare.php', 
        'applicant_documents.php', 'applicant_disbursements.php', 'applicant_bank_assign.php', 'view_banker.php', 'add_banker.php', 
        'edit_banker.php', 'view_referral.php', 'add_referral.php', 'edit_referral.php', 
        'referrals_list.php', 'profile.php', 'send_email.php', 'quotation_builder.php', 
        'quotation_list.php', 'applicants_list.php', 'view_applicant.php', 'track.php', 'appointments.php', 'partner_requests.php'
    ];

    if (!$is_in_subfolder && $current_page !== 'logout.php' && $current_page !== 'login.php' && $current_page !== 'apply.php' && $current_page !== 'track.php') {
        if ($role === 'Staff' || $role === 'RM') {
            if (!in_array($current_page, $staff_allowed_pages)) {
                header("Location: rm/dashboard.php");
                exit;
            }
        } else if ($role === 'CA') {
            header("Location: ca/index.php");
            exit;
        } else if ($role === 'Partner') {
            header("Location: partner/index.php");
            exit;
        } else if ($role === 'Agent') {
            header("Location: agent/index.php");
            exit;
        }
    }
    
    // For Admins/Managers hitting index or login
    if (($current_page === 'login.php' || $current_page === 'index.php' || $current_page === '') && !$is_in_subfolder) {
        if ($role === 'Staff' || $role === 'RM') {
            header("Location: rm/dashboard.php");
        } else {
            header("Location: dashboard.php");
        }
        exit;
    }
}

// Helper utilities for Multi-Format Database Export
function generateSqlDump($db) {
    @ini_set('memory_limit', '-1');
    @ini_set('max_execution_time', '300');

    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
    $out = "-- BFS Circle CRM Database Dump\n";
    $out .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $out .= "-- Host: Localhost SQLite3\n\n";
    $out .= "PRAGMA foreign_keys = OFF;\n";
    $out .= "BEGIN TRANSACTION;\n\n";

    foreach ($tables as $table) {
        $schema = $db->query("SELECT sql FROM sqlite_master WHERE type='table' AND name = " . $db->quote($table))->fetchColumn();
        if ($schema) {
            $out .= "DROP TABLE IF EXISTS " . $db->quote($table) . ";\n";
            $out .= $schema . ";\n\n";
        }

        $stmt = $db->query("SELECT * FROM " . $db->quote($table));
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cols = array_keys($row);
            $vals = array_map(function($v) use ($db) {
                if ($v === null) return 'NULL';
                return $db->quote($v);
            }, array_values($row));

            $out .= "INSERT INTO " . $db->quote($table) . " (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ");\n";
        }
        $out .= "\n";
    }

    $out .= "COMMIT;\n";
    $out .= "PRAGMA foreign_keys = ON;\n";
    return $out;
}

function exportDatabaseToJson($db) {
    @ini_set('memory_limit', '-1');
    @ini_set('max_execution_time', '300');

    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
    $dump = [
        'meta' => [
            'system' => 'BFS Circle CRM',
            'version' => '2.0',
            'created_at' => date('Y-m-d H:i:s')
        ],
        'tables' => []
    ];

    foreach ($tables as $table) {
        $stmt = $db->query("SELECT * FROM " . $db->quote($table));
        $dump['tables'][$table] = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $dump['tables'][$table][] = $row;
        }
    }

    return json_encode($dump, JSON_UNESCAPED_UNICODE);
}

if (!function_exists('log_fund_audit')) {
    function log_fund_audit($db, $action, $old_val = null, $new_val = null, $reference_no = null) {
        try {
            $user_id = $_SESSION['user_id'] ?? null;
            $username = $_SESSION['username'] ?? 'System';
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $old_json = is_array($old_val) ? json_encode($old_val) : (string)$old_val;
            $new_json = is_array($new_val) ? json_encode($new_val) : (string)$new_val;
            
            $stmt = $db->prepare("INSERT INTO fund_audit_logs (user_id, username, action, old_value, new_value, reference_no, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $username, $action, $old_json, $new_json, $reference_no, $ip]);
        } catch (Exception $e) {
            // Silently ignore audit log errors
        }
    }
}

if (!function_exists('log_permanent_activity')) {
    function log_permanent_activity($db, $description, $action_link = 'fund_management.php') {
        try {
            $username = $_SESSION['username'] ?? 'System';
            $stmt = $db->prepare("INSERT INTO activities (username, description, action_link, created_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)");
            $stmt->execute([$username, $description, $action_link]);
        } catch (Exception $e) {
            // Silently ignore errors
        }
    }
}

// Handle API requests globally (when called through other entrypoints)
if (isset($_GET['api']) && basename($_SERVER['SCRIPT_FILENAME'] ?? $_SERVER['PHP_SELF']) !== 'api.php') {
    require_once __DIR__ . '/api.php';
    exit;
}

