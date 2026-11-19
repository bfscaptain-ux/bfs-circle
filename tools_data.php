<?php
// tools_data.php - Website Financial Tools & Calculators Data Hub (OTP Verified Leads)
require_once __DIR__ . '/config.php';
require_role(['Admin', 'RM', 'Staff']);

$is_admin = (($_SESSION['role'] ?? '') === 'Admin');
$username = $_SESSION['username'] ?? '';

// Ensure tools_data table exists
try {
    $db->exec("CREATE TABLE IF NOT EXISTS tools_data (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        lead_id INTEGER,
        name TEXT NOT NULL,
        phone TEXT NOT NULL,
        email TEXT,
        otp_verified INTEGER DEFAULT 0,
        otp_verified_at DATETIME,
        tool_name TEXT NOT NULL,
        loan_amount REAL DEFAULT 0,
        monthly_emi REAL DEFAULT 0,
        interest_rate REAL DEFAULT 0,
        tenure_years INTEGER DEFAULT 0,
        calculation_summary TEXT,
        city TEXT DEFAULT 'Agra',
        status TEXT DEFAULT 'New Lead',
        assigned_to TEXT DEFAULT 'Unassigned',
        notes TEXT,
        raw_payload TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {}

// 1. Handle AJAX Actions (Status Update, RM Assignment, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'];
    $item_id = (int)($_POST['id'] ?? 0);

    if ($action === 'update_status' && $item_id > 0) {
        $new_status = trim($_POST['status'] ?? 'New Lead');
        $valid_statuses = ['New Lead', 'Contacted', 'In Progress', 'Converted', 'Rejected'];
        if (in_array($new_status, $valid_statuses)) {
            $stmt = $db->prepare("UPDATE tools_data SET status = ?, updated_at = datetime('now', 'localtime') WHERE id = ?");
            $stmt->execute([$new_status, $item_id]);
            log_activity("Tools Lead #{$item_id} status updated to '{$new_status}'", "tools_data.php");
            echo json_encode(['success' => true]);
            exit;
        }
    }

    if ($action === 'assign_rm' && $item_id > 0 && $is_admin) {
        $assigned_rm = trim($_POST['assigned_to'] ?? 'Unassigned');
        $stmt = $db->prepare("UPDATE tools_data SET assigned_to = ?, updated_at = datetime('now', 'localtime') WHERE id = ?");
        $stmt->execute([$assigned_rm, $item_id]);
        log_activity("Tools Lead #{$item_id} assigned to '{$assigned_rm}'", "tools_data.php");
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'delete_item' && $item_id > 0 && $is_admin) {
        $stmt = $db->prepare("DELETE FROM tools_data WHERE id = ?");
        $stmt->execute([$item_id]);
        log_activity("Deleted Tools Lead #{$item_id}", "tools_data.php");
        echo json_encode(['success' => true]);
        exit;
    }

    // Convert Tool Lead directly to full Loan Applicant (LOS)
    if ($action === 'convert_to_applicant' && $item_id > 0) {
        $stmt = $db->prepare("SELECT * FROM tools_data WHERE id = ?");
        $stmt->execute([$item_id]);
        $td = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($td) {
            // Check if already an applicant with same phone
            $chk = $db->prepare("SELECT id FROM applicants WHERE phone = ? LIMIT 1");
            $chk->execute([$td['phone']]);
            $existing_app_id = $chk->fetchColumn();

            if ($existing_app_id) {
                echo json_encode(['success' => true, 'applicant_id' => $existing_app_id, 'message' => 'Applicant already exists with this phone.']);
                exit;
            }

            $app_name = $td['name'];
            $app_phone = $td['phone'];
            $app_email = $td['email'] ?: null;
            $app_city = $td['city'] ?: 'Agra';
            $loan_type = $td['tool_name'] ?: 'Home Loan';
            $loan_amt = $td['loan_amount'] ?: 3500000;
            $notes = "Auto-converted from Website Financial Tool: {$td['tool_name']}. " . ($td['calculation_summary'] ?: '');

            $ins = $db->prepare("INSERT INTO applicants (
                name, phone, email, city, loan_type, loan_amount, 
                notes, status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'Lead Ingested', datetime('now', 'localtime'), datetime('now', 'localtime'))");
            $ins->execute([$app_name, $app_phone, $app_email, $app_city, $loan_type, $loan_amt, $notes]);
            $new_app_id = (int)$db->lastInsertId();

            // Mark tool lead as Converted
            $db->prepare("UPDATE tools_data SET status = 'Converted', updated_at = datetime('now', 'localtime') WHERE id = ?")->execute([$item_id]);
            log_activity("Tools Lead #{$item_id} converted to Applicant #{$new_app_id}", "applicant_documents.php?id={$new_app_id}");

            echo json_encode(['success' => true, 'applicant_id' => $new_app_id]);
            exit;
        }
    }

    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}

// 2. Export to CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="BFS_Tools_Leads_' . date('Y-m-d_His') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Date', 'Applicant Name', 'Mobile', 'Email', 'OTP Verified', 'Tool Name', 'Loan Amount', 'Monthly EMI', 'Rate (%)', 'Tenure (Yrs)', 'City', 'Status', 'Assigned To', 'Summary']);

    $stmt_csv = $db->query("SELECT * FROM tools_data ORDER BY id DESC");
    while ($row = $stmt_csv->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['id'],
            $row['created_at'],
            $row['name'],
            $row['phone'],
            $row['email'] ?: 'N/A',
            $row['otp_verified'] ? 'YES' : 'NO',
            $row['tool_name'],
            $row['loan_amount'],
            $row['monthly_emi'],
            $row['interest_rate'],
            $row['tenure_years'],
            $row['city'],
            $row['status'],
            $row['assigned_to'],
            $row['calculation_summary']
        ]);
    }
    fclose($output);
    exit;
}

// 3. Fetch KPI Metrics
$total_leads = (int)$db->query("SELECT COUNT(*) FROM tools_data")->fetchColumn();
$verified_leads = (int)$db->query("SELECT COUNT(*) FROM tools_data WHERE otp_verified = 1")->fetchColumn();
$today_leads = (int)$db->query("SELECT COUNT(*) FROM tools_data WHERE date(created_at, 'localtime') = date('now', 'localtime')")->fetchColumn();
$total_volume = (float)$db->query("SELECT COALESCE(SUM(loan_amount), 0) FROM tools_data")->fetchColumn();

// 4. Fetch Staff list for assignment
$staff_list = [];
try {
    $staff_list = $db->query("SELECT username, COALESCE(name, username) as name, role FROM users WHERE is_active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// 5. Query Filters
$search = trim($_GET['search'] ?? '');
$filter_tool = trim($_GET['tool'] ?? '');
$filter_status = trim($_GET['status'] ?? '');
$filter_otp = trim($_GET['otp'] ?? '');

$where_clauses = [];
$params = [];

if ($search !== '') {
    $where_clauses[] = "(name LIKE ? OR phone LIKE ? OR email LIKE ? OR tool_name LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if ($filter_tool !== '') {
    $where_clauses[] = "tool_name LIKE ?";
    $params[] = "%{$filter_tool}%";
}
if ($filter_status !== '') {
    $where_clauses[] = "status = ?";
    $params[] = $filter_status;
}
if ($filter_otp !== '') {
    $where_clauses[] = "otp_verified = ?";
    $params[] = ($filter_otp === '1' ? 1 : 0);
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
$query = "SELECT * FROM tools_data {$where_sql} ORDER BY id DESC LIMIT 200";
$stmt = $db->prepare($query);
$stmt->execute($params);
$tools_leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Website Tools & Calculator Leads';
$hide_main_header = false;
require_once __DIR__ . '/header.php';
?>

<div class="content-wrapper" style="padding: 24px; max-width: 1560px; margin: 0 auto; font-family: 'Inter', system-ui, sans-serif;">

    <!-- Header & Action Row -->
    <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; margin-bottom: 24px; gap: 16px;">
        <div>
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="width: 42px; height: 42px; border-radius: 12px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); display: flex; align-items: center; justify-content: center; color: #fff; box-shadow: 0 4px 12px rgba(16,185,129,0.3);">
                    <i data-lucide="calculator" style="width: 22px; height: 22px;"></i>
                </div>
                <div>
                    <h1 style="font-size: 22px; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.02em;">Website Tools Data</h1>
                    <p style="font-size: 13px; color: #64748b; margin: 2px 0 0 0;">Inquiries captured from bfsfin.com calculators & tools with Email OTP verification</p>
                </div>
            </div>
        </div>
        <div style="display: flex; align-items: center; gap: 10px;">
            <a href="tools_data.php?export=csv" class="btn btn-outline" style="display: inline-flex; align-items: center; gap: 8px; background: #ffffff; border: 1px solid #cbd5e1; color: #334155; padding: 8px 16px; border-radius: 10px; font-weight: 700; font-size: 13px; text-decoration: none; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                <i data-lucide="download" style="width: 15px; height: 15px; color: #059669;"></i>
                <span>Export CSV</span>
            </a>
            <a href="https://bfsfin.com/calculator" target="_blank" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 8px; background: #059669; color: #ffffff; padding: 8px 16px; border-radius: 10px; font-weight: 700; font-size: 13px; text-decoration: none; box-shadow: 0 4px 12px rgba(5,150,105,0.25);">
                <i data-lucide="external-link" style="width: 15px; height: 15px;"></i>
                <span>Open Website Tools</span>
            </a>
        </div>
    </div>

    <!-- Top KPI Summary Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <!-- Card 1 -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); display: flex; align-items: center; justify-content: space-between;">
            <div>
                <span style="font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;">Total Tool Leads</span>
                <div style="font-size: 28px; font-weight: 900; color: #0f172a; margin-top: 4px;"><?php echo number_format($total_leads); ?></div>
                <div style="font-size: 11px; color: #10b981; font-weight: 600; margin-top: 2px;">Simulated via bfsfin.com</div>
            </div>
            <div style="width: 48px; height: 48px; border-radius: 12px; background: #ecfdf5; display: flex; align-items: center; justify-content: center; color: #059669;">
                <i data-lucide="cpu" style="width: 24px; height: 24px;"></i>
            </div>
        </div>

        <!-- Card 2 -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); display: flex; align-items: center; justify-content: space-between;">
            <div>
                <span style="font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;">Email OTP Verified</span>
                <div style="font-size: 28px; font-weight: 900; color: #059669; margin-top: 4px;"><?php echo number_format($verified_leads); ?></div>
                <div style="font-size: 11px; color: #64748b; font-weight: 600; margin-top: 2px;">
                    <?php echo $total_leads > 0 ? round(($verified_leads / $total_leads) * 100, 1) : 0; ?>% Authenticated Users
                </div>
            </div>
            <div style="width: 48px; height: 48px; border-radius: 12px; background: #e0f2fe; display: flex; align-items: center; justify-content: center; color: #0284c7;">
                <i data-lucide="shield-check" style="width: 24px; height: 24px;"></i>
            </div>
        </div>

        <!-- Card 3 -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); display: flex; align-items: center; justify-content: space-between;">
            <div>
                <span style="font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;">Today's Leads</span>
                <div style="font-size: 28px; font-weight: 900; color: #2563eb; margin-top: 4px;"><?php echo number_format($today_leads); ?></div>
                <div style="font-size: 11px; color: #64748b; font-weight: 600; margin-top: 2px;">Direct Website Activity</div>
            </div>
            <div style="width: 48px; height: 48px; border-radius: 12px; background: #eff6ff; display: flex; align-items: center; justify-content: center; color: #2563eb;">
                <i data-lucide="clock" style="width: 24px; height: 24px;"></i>
            </div>
        </div>

        <!-- Card 4 -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); display: flex; align-items: center; justify-content: space-between;">
            <div>
                <span style="font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;">Total Simulated Loan Value</span>
                <div style="font-size: 24px; font-weight: 900; color: #0f172a; margin-top: 4px;">
                    <?php 
                        if ($total_volume >= 10000000) {
                            echo '₹' . number_format($total_volume / 10000000, 2) . ' Cr';
                        } elseif ($total_volume >= 100000) {
                            echo '₹' . number_format($total_volume / 100000, 2) . ' Lakh';
                        } else {
                            echo '₹' . number_format($total_volume);
                        }
                    ?>
                </div>
                <div style="font-size: 11px; color: #64748b; font-weight: 600; margin-top: 2px;">Aggregated Inquiries</div>
            </div>
            <div style="width: 48px; height: 48px; border-radius: 12px; background: #fef3c7; display: flex; align-items: center; justify-content: center; color: #d97706;">
                <i data-lucide="banknote" style="width: 24px; height: 24px;"></i>
            </div>
        </div>
    </div>

    <!-- Search & Filter Controls -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 16px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
        <form method="GET" action="tools_data.php" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)) 100px; gap: 12px; align-items: end;">
            <!-- Search -->
            <div>
                <label style="display: block; font-size: 11px; font-weight: 700; color: #64748b; margin-bottom: 4px;">Search Keyword</label>
                <div style="position: relative;">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name, Phone, Email..." style="width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px 12px 8px 32px; font-size: 13px; outline: none; font-weight: 500;">
                    <i data-lucide="search" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); width: 14px; height: 14px; color: #94a3b8;"></i>
                </div>
            </div>

            <!-- Tool Type -->
            <div>
                <label style="display: block; font-size: 11px; font-weight: 700; color: #64748b; margin-bottom: 4px;">Calculator / Tool</label>
                <select name="tool" style="width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px 12px; font-size: 13px; outline: none; background: #ffffff;">
                    <option value="">All Tools & Calculators</option>
                    <option value="EMI" <?php echo ($filter_tool === 'EMI') ? 'selected' : ''; ?>>Smart EMI Calculator</option>
                    <option value="Affordability" <?php echo ($filter_tool === 'Affordability') ? 'selected' : ''; ?>>Affordability Calculator</option>
                    <option value="Balance Transfer" <?php echo ($filter_tool === 'Balance Transfer') ? 'selected' : ''; ?>>Balance Transfer</option>
                    <option value="Prepayment" <?php echo ($filter_tool === 'Prepayment') ? 'selected' : ''; ?>>Prepayment Savings</option>
                    <option value="Compare" <?php echo ($filter_tool === 'Compare') ? 'selected' : ''; ?>>Rate Compare</option>
                    <option value="Stamp Duty" <?php echo ($filter_tool === 'Stamp Duty') ? 'selected' : ''; ?>>Stamp Duty & Registry</option>
                    <option value="Tax" <?php echo ($filter_tool === 'Tax') ? 'selected' : ''; ?>>Tax Benefit 80C/24b</option>
                </select>
            </div>

            <!-- OTP Verification -->
            <div>
                <label style="display: block; font-size: 11px; font-weight: 700; color: #64748b; margin-bottom: 4px;">Email OTP Status</label>
                <select name="otp" style="width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px 12px; font-size: 13px; outline: none; background: #ffffff;">
                    <option value="">All OTP Status</option>
                    <option value="1" <?php echo ($filter_otp === '1') ? 'selected' : ''; ?>>Verified Only (Genuine)</option>
                    <option value="0" <?php echo ($filter_otp === '0') ? 'selected' : ''; ?>>Pending OTP</option>
                </select>
            </div>

            <!-- Status -->
            <div>
                <label style="display: block; font-size: 11px; font-weight: 700; color: #64748b; margin-bottom: 4px;">CRM Lead Status</label>
                <select name="status" style="width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px 12px; font-size: 13px; outline: none; background: #ffffff;">
                    <option value="">All Statuses</option>
                    <option value="New Lead" <?php echo ($filter_status === 'New Lead') ? 'selected' : ''; ?>>New Lead</option>
                    <option value="Contacted" <?php echo ($filter_status === 'Contacted') ? 'selected' : ''; ?>>Contacted</option>
                    <option value="In Progress" <?php echo ($filter_status === 'In Progress') ? 'selected' : ''; ?>>In Progress</option>
                    <option value="Converted" <?php echo ($filter_status === 'Converted') ? 'selected' : ''; ?>>Converted to Loan</option>
                    <option value="Rejected" <?php echo ($filter_status === 'Rejected') ? 'selected' : ''; ?>>Rejected</option>
                </select>
            </div>

            <!-- Submit -->
            <div>
                <button type="submit" style="width: 100%; background: #0f172a; color: #ffffff; border: none; border-radius: 8px; padding: 9px 12px; font-size: 13px; font-weight: 700; cursor: pointer;">
                    Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Leads Table Card -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); overflow: hidden;">
        <div style="padding: 16px 20px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
            <div style="font-weight: 800; font-size: 15px; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                <span>Inquiries Feed</span>
                <span style="background: #ecfdf5; color: #059669; font-size: 11px; padding: 2px 8px; border-radius: 6px; font-weight: 700;">
                    <?php echo count($tools_leads); ?> Records
                </span>
            </div>
            <div style="font-size: 12px; color: #94a3b8;">
                Showing latest submissions sorted by date
            </div>
        </div>

        <?php if (empty($tools_leads)): ?>
        <div style="text-align: center; padding: 60px 20px;">
            <div style="width: 56px; height: 56px; margin: 0 auto 16px; border-radius: 16px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; color: #94a3b8;">
                <i data-lucide="inbox" style="width: 28px; height: 28px;"></i>
            </div>
            <h3 style="font-size: 16px; font-weight: 700; color: #1e293b; margin: 0;">No Tools Data Found</h3>
            <p style="font-size: 13px; color: #64748b; margin: 4px 0 0 0;">Inquiries will show up here as soon as users calculate loans on bfsfin.com.</p>
        </div>
        <?php else: ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13px;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #475569; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em;">
                        <th style="padding: 14px 18px;">ID / Date</th>
                        <th style="padding: 14px 18px;">Applicant Details</th>
                        <th style="padding: 14px 18px;">Email Verification</th>
                        <th style="padding: 14px 18px;">Tool & Loan Amount</th>
                        <th style="padding: 14px 18px;">EMI & Terms</th>
                        <th style="padding: 14px 18px;">Status</th>
                        <th style="padding: 14px 18px;">Assigned To</th>
                        <th style="padding: 14px 18px; text-align: right;">Quick Actions</th>
                    </tr>
                </thead>
                <tbody style="divide-y: divide-slate-100;">
                    <?php foreach ($tools_leads as $td): ?>
                    <tr id="row-<?php echo $td['id']; ?>" style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='#ffffff'">
                        <!-- ID & Date -->
                        <td style="padding: 14px 18px; vertical-align: top;">
                            <div style="font-weight: 800; color: #0f172a; font-size: 13px;">#TL-<?php echo str_pad($td['id'], 5, '0', STR_PAD_LEFT); ?></div>
                            <div style="font-size: 11px; color: #94a3b8; margin-top: 2px;">
                                <?php echo date('d M Y, h:i A', strtotime($td['created_at'])); ?>
                            </div>
                            <?php if (!empty($td['lead_id'])): ?>
                            <a href="leads.php?edit_lead=<?php echo $td['lead_id']; ?>" style="display: inline-block; font-size: 10px; font-weight: 700; color: #2563eb; background: #eff6ff; padding: 1px 6px; border-radius: 4px; text-decoration: none; margin-top: 4px;">
                                CRM Lead #<?php echo $td['lead_id']; ?>
                            </a>
                            <?php endif; ?>
                        </td>

                        <!-- Applicant Details -->
                        <td style="padding: 14px 18px; vertical-align: top;">
                            <div style="font-weight: 800; color: #0f172a; font-size: 14px;"><?php echo htmlspecialchars($td['name']); ?></div>
                            <div style="display: flex; align-items: center; gap: 6px; margin-top: 4px;">
                                <a href="tel:<?php echo htmlspecialchars($td['phone']); ?>" style="color: #475569; text-decoration: none; font-weight: 600; font-size: 12px; display: inline-flex; align-items: center; gap: 4px;">
                                    <i data-lucide="phone" style="width: 12px; height: 12px; color: #059669;"></i>
                                    <span>+91 <?php echo htmlspecialchars($td['phone']); ?></span>
                                </a>
                                <a href="https://wa.me/91<?php echo preg_replace('/[^0-9]/', '', $td['phone']); ?>?text=<?php echo urlencode('Namaste ' . $td['name'] . ' ji! Bhardwaj Financial Services (BFS) me aapka swagat hai. Aapne hamare tool par ' . ($td['tool_name'] ?: 'Loan') . ' calculate kiya tha. Kya aapko loan ki need hai?'); ?>" target="_blank" title="Direct WhatsApp Chat" style="color: #25d366; display: inline-flex;">
                                    <i data-lucide="message-circle" style="width: 14px; height: 14px;"></i>
                                </a>
                            </div>
                            <?php if (!empty($td['email'])): ?>
                            <div style="font-size: 12px; color: #64748b; margin-top: 2px;">
                                <i data-lucide="mail" style="width: 11px; height: 11px; display: inline; vertical-align: -1px;"></i>
                                <?php echo htmlspecialchars($td['email']); ?>
                            </div>
                            <?php endif; ?>
                            <div style="font-size: 11px; color: #94a3b8; margin-top: 2px;">
                                <i data-lucide="map-pin" style="width: 11px; height: 11px; display: inline; vertical-align: -1px;"></i>
                                <?php echo htmlspecialchars($td['city'] ?: 'Agra'); ?>
                            </div>
                        </td>

                        <!-- OTP Verification -->
                        <td style="padding: 14px 18px; vertical-align: top;">
                            <?php if (!empty($td['otp_verified'])): ?>
                            <span style="display: inline-flex; align-items: center; gap: 4px; background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; font-weight: 800; font-size: 11px; padding: 3px 8px; border-radius: 6px;">
                                <i data-lucide="shield-check" style="width: 13px; height: 13px;"></i>
                                <span>OTP Verified</span>
                            </span>
                            <div style="font-size: 10px; color: #64748b; margin-top: 4px;">
                                Verified at: <?php echo !empty($td['otp_verified_at']) ? date('h:i A', strtotime($td['otp_verified_at'])) : 'Submission'; ?>
                            </div>
                            <?php else: ?>
                            <span style="display: inline-flex; align-items: center; gap: 4px; background: #fffbeb; color: #b45309; border: 1px solid #fde68a; font-weight: 700; font-size: 11px; padding: 3px 8px; border-radius: 6px;">
                                <i data-lucide="alert-circle" style="width: 13px; height: 13px;"></i>
                                <span>Pending OTP</span>
                            </span>
                            <?php endif; ?>
                        </td>

                        <!-- Tool & Loan Amount -->
                        <td style="padding: 14px 18px; vertical-align: top;">
                            <span style="display: inline-block; background: #f1f5f9; color: #334155; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 6px; margin-bottom: 4px;">
                                <?php echo htmlspecialchars($td['tool_name']); ?>
                            </span>
                            <div style="font-size: 16px; font-weight: 900; color: #0f172a;">
                                ₹<?php echo number_format($td['loan_amount']); ?>
                            </div>
                        </td>

                        <!-- EMI & Terms -->
                        <td style="padding: 14px 18px; vertical-align: top;">
                            <?php if (!empty($td['monthly_emi'])): ?>
                            <div style="font-weight: 800; color: #059669; font-size: 14px;">
                                ₹<?php echo number_format($td['monthly_emi']); ?><span style="font-size: 10px; font-weight: 600; color: #64748b;">/mo</span>
                            </div>
                            <?php endif; ?>
                            <div style="font-size: 12px; color: #64748b; margin-top: 2px;">
                                <?php if (!empty($td['interest_rate'])): ?>
                                <span>Rate: <strong><?php echo $td['interest_rate']; ?>%</strong></span>
                                <?php endif; ?>
                                <?php if (!empty($td['tenure_years'])): ?>
                                <span style="margin-left: 6px;">Term: <strong><?php echo $td['tenure_years']; ?> Yrs</strong></span>
                                <?php endif; ?>
                            </div>
                        </td>

                        <!-- Status -->
                        <td style="padding: 14px 18px; vertical-align: top;">
                            <select onchange="updateLeadStatus(<?php echo $td['id']; ?>, this.value)" style="border: 1px solid #cbd5e1; border-radius: 6px; padding: 4px 8px; font-size: 12px; font-weight: 700; background: #ffffff; color: #0f172a; outline: none; cursor: pointer;">
                                <option value="New Lead" <?php echo ($td['status'] === 'New Lead') ? 'selected' : ''; ?>>New Lead</option>
                                <option value="Contacted" <?php echo ($td['status'] === 'Contacted') ? 'selected' : ''; ?>>Contacted</option>
                                <option value="In Progress" <?php echo ($td['status'] === 'In Progress') ? 'selected' : ''; ?>>In Progress</option>
                                <option value="Converted" <?php echo ($td['status'] === 'Converted') ? 'selected' : ''; ?>>Converted</option>
                                <option value="Rejected" <?php echo ($td['status'] === 'Rejected') ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </td>

                        <!-- Assigned To -->
                        <td style="padding: 14px 18px; vertical-align: top;">
                            <?php if ($is_admin): ?>
                            <select onchange="assignLeadRM(<?php echo $td['id']; ?>, this.value)" style="border: 1px solid #cbd5e1; border-radius: 6px; padding: 4px 8px; font-size: 12px; font-weight: 600; background: #ffffff; color: #334155; outline: none;">
                                <option value="Unassigned" <?php echo ($td['assigned_to'] === 'Unassigned') ? 'selected' : ''; ?>>Unassigned</option>
                                <?php foreach ($staff_list as $st): ?>
                                <option value="<?php echo htmlspecialchars($st['username']); ?>" <?php echo ($td['assigned_to'] === $st['username']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($st['name']); ?> (<?php echo $st['role']; ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php else: ?>
                            <span style="font-weight: 600; color: #475569; font-size: 12px;">
                                <?php echo htmlspecialchars($td['assigned_to'] ?: 'Unassigned'); ?>
                            </span>
                            <?php endif; ?>
                        </td>

                        <!-- Quick Actions -->
                        <td style="padding: 14px 18px; vertical-align: top; text-align: right;">
                            <div style="display: inline-flex; align-items: center; gap: 6px;">
                                <button type="button" onclick="showLeadDetails(<?php echo htmlspecialchars(json_encode($td), ENT_QUOTES, 'UTF-8'); ?>)" class="btn-icon" title="View Simulation Breakdown" style="border: 1px solid #cbd5e1; background: #ffffff; padding: 6px; border-radius: 8px; color: #475569; cursor: pointer;">
                                    <i data-lucide="eye" style="width: 14px; height: 14px;"></i>
                                </button>
                                <button type="button" onclick="convertLeadToApplicant(<?php echo $td['id']; ?>, '<?php echo addslashes($td['name']); ?>')" class="btn-icon" title="Convert to Official Loan Application (LOS)" style="border: 1px solid #a7f3d0; background: #ecfdf5; padding: 6px; border-radius: 8px; color: #059669; cursor: pointer;">
                                    <i data-lucide="user-plus" style="width: 14px; height: 14px;"></i>
                                </button>
                                <?php if ($is_admin): ?>
                                <button type="button" onclick="deleteToolLead(<?php echo $td['id']; ?>)" class="btn-icon" title="Delete Entry" style="border: 1px solid #fecaca; background: #fef2f2; padding: 6px; border-radius: 8px; color: #dc2626; cursor: pointer;">
                                    <i data-lucide="trash-2" style="width: 14px; height: 14px;"></i>
                                </button>
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

<!-- Details Modal -->
<div id="detailsModal" style="display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.6); z-index: 9999; backdrop-filter: blur(4px); align-items: center; justify-content: center; padding: 20px;">
    <div style="background: #ffffff; border-radius: 20px; max-width: 650px; width: 100%; max-height: 90vh; overflow-y: auto; padding: 24px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); position: relative;">
        <button onclick="closeDetailsModal()" style="position: absolute; right: 20px; top: 20px; border: none; background: #f1f5f9; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #64748b;">
            <i data-lucide="x" style="width: 16px; height: 16px;"></i>
        </button>

        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 16px;">
            <div style="width: 36px; height: 36px; border-radius: 10px; background: #ecfdf5; color: #059669; display: flex; align-items: center; justify-content: center;">
                <i data-lucide="file-spreadsheet" style="width: 20px; height: 20px;"></i>
            </div>
            <div>
                <h3 id="modalLeadName" style="font-size: 18px; font-weight: 800; color: #0f172a; margin: 0;">Lead Details</h3>
                <p id="modalToolName" style="font-size: 12px; color: #059669; font-weight: 700; margin: 2px 0 0 0;">Tool Calculation</p>
            </div>
        </div>

        <div id="modalContent" style="display: flex; flex-col; gap: 12px;">
            <!-- Injected via JavaScript -->
        </div>

        <div style="margin-top: 24px; display: flex; justify-content: flex-end; gap: 10px;">
            <button type="button" onclick="closeDetailsModal()" style="padding: 8px 16px; border: 1px solid #cbd5e1; border-radius: 8px; font-weight: 700; font-size: 13px; background: #ffffff; color: #475569; cursor: pointer;">
                Close
            </button>
        </div>
    </div>
</div>

<script>
function updateLeadStatus(id, newStatus) {
    fetch('tools_data.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=update_status&id=${id}&status=${encodeURIComponent(newStatus)}`
    })
    .then(r => r.json())
    .then(res => {
        if (!res.success) alert('Failed to update status.');
    })
    .catch(() => alert('Network error.'));
}

function assignLeadRM(id, username) {
    fetch('tools_data.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=assign_rm&id=${id}&assigned_to=${encodeURIComponent(username)}`
    })
    .then(r => r.json())
    .then(res => {
        if (!res.success) alert('Failed to assign RM.');
    })
    .catch(() => alert('Network error.'));
}

function deleteToolLead(id) {
    if (!confirm('Are you sure you want to permanently delete this tool lead?')) return;

    fetch('tools_data.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=delete_item&id=${id}`
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            const row = document.getElementById(`row-${id}`);
            if (row) row.remove();
        } else {
            alert('Failed to delete lead.');
        }
    })
    .catch(() => alert('Network error.'));
}

function convertLeadToApplicant(id, name) {
    if (!confirm(`Convert ${name} to an official Loan File in the Loan Origination System (LOS)?`)) return;

    fetch('tools_data.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=convert_to_applicant&id=${id}`
    })
    .then(r => r.json())
    .then(res => {
        if (res.success && res.applicant_id) {
            window.location.href = `applicant_documents.php?id=${res.applicant_id}`;
        } else {
            alert(res.message || 'Failed to convert lead.');
        }
    })
    .catch(() => alert('Network error.'));
}

function showLeadDetails(lead) {
    document.getElementById('modalLeadName').textContent = lead.name || 'Applicant';
    document.getElementById('modalToolName').textContent = (lead.tool_name || 'Loan Tool') + (lead.otp_verified ? ' (Email OTP Verified)' : '');

    const content = `
        <div style="background: #f8fafc; border-radius: 12px; padding: 14px; margin-bottom: 12px; border: 1px solid #e2e8f0;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 13px;">
                <div><span style="color:#64748b; font-size:11px; display:block;">Phone:</span> <strong>+91 ${lead.phone}</strong></div>
                <div><span style="color:#64748b; font-size:11px; display:block;">Email:</span> <strong>${lead.email || 'N/A'}</strong></div>
                <div><span style="color:#64748b; font-size:11px; display:block;">City:</span> <strong>${lead.city || 'Agra'}</strong></div>
                <div><span style="color:#64748b; font-size:11px; display:block;">Submission Date:</span> <strong>${lead.created_at}</strong></div>
            </div>
        </div>

        <div style="background: #ecfdf5; border-radius: 12px; padding: 14px; margin-bottom: 12px; border: 1px solid #a7f3d0;">
            <h4 style="font-size: 12px; font-weight: 800; color: #065f46; text-transform: uppercase; margin: 0 0 8px 0;">Calculation Breakdown</h4>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; font-size: 13px;">
                <div><span style="color:#047857; font-size:11px; display:block;">Loan Amount:</span> <strong style="font-size:15px; color:#064e3b;">₹${Number(lead.loan_amount || 0).toLocaleString('en-IN')}</strong></div>
                <div><span style="color:#047857; font-size:11px; display:block;">Monthly EMI:</span> <strong style="font-size:15px; color:#064e3b;">₹${Number(lead.monthly_emi || 0).toLocaleString('en-IN')}/mo</strong></div>
                <div><span style="color:#047857; font-size:11px; display:block;">Interest & Tenure:</span> <strong>${lead.interest_rate || 0}% • ${lead.tenure_years || 0} Yrs</strong></div>
            </div>
        </div>

        ${lead.calculation_summary ? `
        <div style="background: #ffffff; border-radius: 12px; padding: 14px; margin-bottom: 12px; border: 1px solid #e2e8f0;">
            <span style="color:#64748b; font-size:11px; font-weight:700; text-transform:uppercase; display:block; margin-bottom:4px;">Simulation Summary / Notes:</span>
            <div style="font-size: 13px; color: #334155; line-height: 1.5; white-space: pre-wrap;">${lead.calculation_summary}</div>
        </div>` : ''}

        <div style="display: flex; gap: 10px; margin-top: 10px;">
            <a href="https://wa.me/91${lead.phone.replace(/[^0-9]/g, '')}?text=${encodeURIComponent('Namaste ' + lead.name + ' ji! Bhardwaj Financial Services se loan inquiry ke sambandh me.')}" target="_blank" style="flex: 1; text-align: center; background: #25d366; color: white; padding: 10px; border-radius: 8px; font-weight: 700; font-size: 13px; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 6px;">
                <i data-lucide="message-circle" style="width: 16px; height: 16px;"></i> Chat on WhatsApp
            </a>
            <a href="tel:${lead.phone}" style="flex: 1; text-align: center; background: #0f172a; color: white; padding: 10px; border-radius: 8px; font-weight: 700; font-size: 13px; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 6px;">
                <i data-lucide="phone" style="width: 16px; height: 16px;"></i> Call Applicant
            </a>
        </div>
    `;

    document.getElementById('modalContent').innerHTML = content;
    document.getElementById('detailsModal').style.display = 'flex';
    if (window.lucide) window.lucide.createIcons();
}

function closeDetailsModal() {
    document.getElementById('detailsModal').style.display = 'none';
}
</script>

<?php 
require_once __DIR__ . '/footer.php';
?>
