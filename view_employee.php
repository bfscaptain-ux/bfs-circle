<?php
require_once 'config.php';
require_role(['Admin'], false);
$page_title = 'Employee 360° Executive Intelligence Dossier';
$page_subtitle = 'Comprehensive Employee Audit, Attendance Log, Salary & Incentive Ledger, and Performance Tracking';
$hide_main_header = true;
require_once 'header.php';

$is_admin = (($_SESSION['role'] ?? '') === 'Admin');
$emp_id = intval($_GET['id'] ?? 0);
$emp_username = trim($_GET['username'] ?? '');

if ($emp_id <= 0 && empty($emp_username)) {
    die("<div style='padding:40px; text-align:center;'><h2>Invalid Employee Request</h2><p>Employee ID or Username is required.</p><a href='employees_list.php' class='btn-mono-primary'>Back to Staff Directory</a></div>");
}

if ($emp_id > 0) {
    $stmt = $db->prepare("SELECT e.*, u.is_active, u.username, u.role FROM employees e JOIN users u ON e.user_id = u.id WHERE e.id = ?");
    $stmt->execute([$emp_id]);
} else {
    $stmt = $db->prepare("SELECT e.*, u.is_active, u.username, u.role FROM employees e JOIN users u ON e.user_id = u.id WHERE u.username = ?");
    $stmt->execute([$emp_username]);
}
$emp = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$emp) {
    die("<div style='padding:40px; text-align:center;'><h2>Employee Profile Not Found</h2><p>The requested staff officer could not be located in the database.</p><a href='employees_list.php' class='btn-mono-primary'>Back to Staff Directory</a></div>");
}

$u_name = $emp['username'];
$e_id   = $emp['id'];

// Check Today's Attendance Status
$today = date('Y-m-d');
$today_att = $db->query("SELECT status FROM employee_attendance WHERE employee_id = $e_id AND attendance_date = '$today'")->fetchColumn();
$att_badge = $today_att 
    ? "<span class='badge' style='background:#ffffff; color:#0f172a; border:1px solid #cbd5e1; font-weight:700;'>Present Today ($today_att)</span>"
    : "<span class='badge' style='background:#f8fafc; color:#64748b; border:1px solid #cbd5e1; font-weight:600;'>Attendance Pending Today</span>";
?>

<style>
/* Pure Professional Black & White Corporate Theme */
:root {
    --bw-black: #0f172a;
    --bw-dark: #1e293b;
    --bw-mid: #475569;
    --bw-muted: #64748b;
    --bw-light: #f8fafc;
    --bw-border: #e2e8f0;
    --bw-border-dark: #cbd5e1;
}

.emp-hero-header {
    background: var(--bw-black);
    border-radius: 14px;
    padding: 24px;
    color: #ffffff;
    box-shadow: 0 4px 15px rgba(15, 23, 42, 0.1);
    margin-bottom: 24px;
    border: 1px solid var(--bw-black);
}

.emp-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.emp-kpi-card {
    background: #ffffff;
    border: 1px solid var(--bw-border);
    border-left: 4px solid var(--bw-black);
    border-radius: 10px;
    padding: 18px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    transition: border-color 0.2s;
}

.emp-kpi-card:hover {
    border-color: var(--bw-black);
}

.tab-nav-bar {
    display: flex;
    background: #ffffff;
    border: 1px solid var(--bw-border);
    border-radius: 10px 10px 0 0;
    padding: 6px 10px 0 10px;
    gap: 6px;
    border-bottom: 2px solid var(--bw-border);
    overflow-x: auto;
}

.tab-btn {
    padding: 10px 18px;
    font-size: 13px;
    font-weight: 700;
    color: var(--bw-muted);
    border: none;
    background: none;
    cursor: pointer;
    border-bottom: 3px solid transparent;
    transition: all 0.2s;
    white-space: nowrap;
}

.tab-btn.active {
    color: var(--bw-black);
    border-bottom-color: var(--bw-black);
    background: var(--bw-light);
    border-radius: 6px 6px 0 0;
}

.tab-pane {
    background: #ffffff;
    border: 1px solid var(--bw-border);
    border-top: none;
    border-radius: 0 0 10px 10px;
    padding: 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
}

.mono-input, .mono-select {
    padding: 8px 12px;
    border: 1px solid var(--bw-border-dark);
    border-radius: 6px;
    font-size: 13px;
    color: var(--bw-black);
    background: #ffffff;
    outline: none;
    transition: border-color 0.2s;
}

.mono-input:focus, .mono-select:focus {
    border-color: var(--bw-black);
    box-shadow: 0 0 0 2px rgba(15, 23, 42, 0.1);
}

.btn-mono-primary {
    background: var(--bw-black);
    color: #ffffff;
    border: 1px solid var(--bw-black);
    padding: 8px 16px;
    border-radius: 6px;
    font-size: 12.5px;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none;
    transition: all 0.2s;
}

.btn-mono-primary:hover {
    background: #1e293b;
    border-color: #1e293b;
    color: #ffffff;
}

.btn-mono-outline {
    background: #ffffff;
    color: var(--bw-black);
    border: 1px solid var(--bw-black);
    padding: 7px 14px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    text-decoration: none;
    transition: all 0.2s;
}

.btn-mono-outline:hover {
    background: var(--bw-light);
    border-color: var(--bw-black);
    color: var(--bw-black);
}

.btn-mono-subtle {
    background: var(--bw-light);
    color: var(--bw-black);
    border: 1px solid var(--bw-border-dark);
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 11.5px;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    text-decoration: none;
    transition: all 0.2s;
}

.btn-mono-subtle:hover {
    background: #ffffff;
    border-color: var(--bw-black);
    color: var(--bw-black);
}
</style>

<div class="view-container">
    <!-- Navigation Bar -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
        <a href="employees_list.php" class="btn-mono-outline" style="padding:7px 14px; font-size:12.5px;"><i data-lucide="arrow-left" style="width:14px;"></i> Back to Staff Directory</a>
        <span style="font-size:12px; color:var(--bw-muted); font-weight:600;">Staff Intelligence Portal • ID #<?= $emp['id'] ?></span>
    </div>

    <!-- Hero Profile Header -->
    <div class="emp-hero-header">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:20px;">
            <div style="display:flex; align-items:center; gap:20px;">
                <?php if(!empty($emp['photo_path']) && file_exists(__DIR__ . '/' . $emp['photo_path'])): ?>
                    <img src="<?= htmlspecialchars($emp['photo_path']) ?>" alt="Avatar" style="width:80px; height:80px; border-radius:50%; object-fit:cover; border:2px solid #ffffff;">
                <?php else: ?>
                    <div style="width:80px; height:80px; border-radius:50%; background:#ffffff; color:var(--bw-black); display:flex; align-items:center; justify-content:center; font-size:30px; font-weight:800; border:2px solid #ffffff;">
                        <?= strtoupper(substr(htmlspecialchars($emp['full_name']), 0, 1)) ?>
                    </div>
                <?php endif; ?>

                <div>
                    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                        <h1 style="font-size:22px; font-weight:900; color:#ffffff; margin:0; font-family:'Outfit',sans-serif;"><?= htmlspecialchars($emp['full_name']) ?></h1>
                        <span class="badge" style="background:rgba(255,255,255,0.15); color:#ffffff; border:1px solid rgba(255,255,255,0.3); font-size:11px; padding:3px 10px; font-family:'Outfit',sans-serif; font-weight:700;"><?= htmlspecialchars($emp['emp_id']) ?></span>
                        <?= $att_badge ?>
                    </div>
                    <div style="font-size:13.5px; color:#ffffff; font-weight:700; margin-top:4px;"><?= htmlspecialchars($emp['designation']) ?> • <span style="color:var(--bw-border-dark);"><?= htmlspecialchars($emp['department']) ?></span></div>
                    <div style="font-size:12px; color:var(--bw-border-dark); margin-top:6px; display:flex; gap:16px; flex-wrap:wrap;">
                        <span>📞 <?= htmlspecialchars($emp['mobile']) ?></span>
                        <span>✉️ <?= htmlspecialchars($emp['official_email']) ?></span>
                        <span>📅 Joined: <?= htmlspecialchars($emp['doj'] ?: 'N/A') ?></span>
                    </div>
                </div>
            </div>

            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <button onclick="openQuickAttendanceModal(<?= $emp['id'] ?>, '<?= htmlspecialchars(addslashes($emp['full_name'])) ?>', '<?= htmlspecialchars($emp['username']) ?>')" class="btn-mono-outline" style="background:#ffffff; color:var(--bw-black); padding:8px 14px; font-size:12.5px;"><i data-lucide="calendar-check" style="width:14px;"></i> Log Attendance</button>
                <button onclick="openQuickSalaryModal(<?= $emp['id'] ?>, '<?= htmlspecialchars(addslashes($emp['full_name'])) ?>', '<?= htmlspecialchars($emp['username']) ?>', <?= (float)($emp['monthly_base_salary'] ?: 0) ?>)" class="btn-mono-outline" style="background:#ffffff; color:var(--bw-black); padding:8px 14px; font-size:12.5px;"><i data-lucide="banknote" style="width:14px;"></i> Pay Salary / Incentive</button>
                <?php if ($is_admin): ?>
                    <a href="edit_employee.php?id=<?= $emp['id'] ?>" class="btn-mono-outline" style="background:#ffffff; color:var(--bw-black); padding:8px 14px; font-size:12.5px;"><i data-lucide="edit" style="width:14px;"></i> Edit Profile</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 4 Core KPI Scorecards -->
    <div class="emp-kpi-grid">
        <div class="emp-kpi-card">
            <span style="font-size:11px; font-weight:700; text-transform:uppercase; color:var(--bw-muted); letter-spacing:0.5px;">Disbursed Volume (MTD)</span>
            <div style="font-size:22px; font-weight:900; color:var(--bw-black); font-family:'Outfit',sans-serif; margin-top:4px;" id="kpi-disbursed-val">₹0</div>
            <div style="font-size:11.5px; color:var(--bw-muted); margin-top:4px;" id="kpi-disbursed-count">0 Disbursed Cases</div>
        </div>

        <div class="emp-kpi-card">
            <span style="font-size:11px; font-weight:700; text-transform:uppercase; color:var(--bw-muted); letter-spacing:0.5px;">Estimated Incentive (MTD)</span>
            <div style="font-size:22px; font-weight:900; color:var(--bw-black); font-family:'Outfit',sans-serif; margin-top:4px;" id="kpi-incentive-val">₹0</div>
            <div style="font-size:11.5px; color:var(--bw-muted); margin-top:4px;">Rate: <strong><?= (float)($emp['commission_rate'] ?: 1.0) ?>%</strong></div>
        </div>

        <div class="emp-kpi-card">
            <span style="font-size:11px; font-weight:700; text-transform:uppercase; color:var(--bw-muted); letter-spacing:0.5px;">Attendance (This Month)</span>
            <div style="font-size:22px; font-weight:900; color:var(--bw-black); font-family:'Outfit',sans-serif; margin-top:4px;" id="kpi-present-count">0 Days</div>
            <div style="font-size:11.5px; color:var(--bw-muted); margin-top:4px;" id="kpi-absent-count">0 Absences</div>
        </div>

        <div class="emp-kpi-card">
            <span style="font-size:11px; font-weight:700; text-transform:uppercase; color:var(--bw-muted); letter-spacing:0.5px;">Monthly Base Salary</span>
            <div style="font-size:22px; font-weight:900; color:var(--bw-black); font-family:'Outfit',sans-serif; margin-top:4px;">₹<?= number_format((float)($emp['monthly_base_salary'] ?: 0)) ?></div>
            <div style="font-size:11.5px; color:var(--bw-muted); margin-top:4px;">Status: <strong style="color:var(--bw-black);"><?= htmlspecialchars($emp['employment_status'] ?: 'Active') ?></strong></div>
        </div>
    </div>

    <!-- 5 Content Tabs -->
    <div class="tab-nav-bar">
        <button class="tab-btn active" onclick="switchEmpTab('portfolio')" id="tab-btn-portfolio"><i data-lucide="folder-check" style="width:14px; vertical-align:-1px;"></i> Loan Performance</button>
        <button class="tab-btn" onclick="switchEmpTab('attendance')" id="tab-btn-attendance"><i data-lucide="calendar" style="width:14px; vertical-align:-1px;"></i> Attendance Log</button>
        <button class="tab-btn" onclick="switchEmpTab('salary')" id="tab-btn-salary"><i data-lucide="banknote" style="width:14px; vertical-align:-1px;"></i> Salary &amp; Payout Ledger</button>
        <button class="tab-btn" onclick="switchEmpTab('activity')" id="tab-btn-activity"><i data-lucide="activity" style="width:14px; vertical-align:-1px;"></i> Real-Time Activity Stream</button>
        <button class="tab-btn" onclick="switchEmpTab('dossier')" id="tab-btn-dossier"><i data-lucide="file-text" style="width:14px; vertical-align:-1px;"></i> HR Dossier &amp; KYE</button>
    </div>

    <!-- TAB 1: LOAN PERFORMANCE -->
    <div class="tab-pane" id="tab-pane-portfolio">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h3 style="font-size:16px; font-weight:800; color:var(--bw-black); margin:0;">Assigned Loan Portfolio &amp; Disbursals</h3>
            <span class="badge" style="background:var(--bw-light); color:var(--bw-black); border:1px solid var(--bw-border-dark); font-weight:700;" id="portfolio-count-badge">0 files</span>
        </div>
        <div style="overflow-x:auto;">
            <table class="data-table" style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr>
                        <th>Loan ID &amp; Applicant</th>
                        <th>Loan Type &amp; Amount</th>
                        <th>Current Stage</th>
                        <th>Sanction Bank</th>
                        <th>Created Date</th>
                        <th style="text-align:right;">Action</th>
                    </tr>
                </thead>
                <tbody id="emp-portfolio-tbody">
                    <tr><td colspan="6" style="text-align:center; padding:20px; color:var(--bw-muted);">Loading loan portfolio...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TAB 2: ATTENDANCE LOG -->
    <div class="tab-pane" id="tab-pane-attendance" style="display:none;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <div>
                <h3 style="font-size:16px; font-weight:800; color:var(--bw-black); margin:0;">Monthly Attendance Record</h3>
                <p style="font-size:12px; color:var(--bw-muted); margin:2px 0 0 0;">Daily check-in, presence, absence, and leave history.</p>
            </div>
            <button onclick="openQuickAttendanceModal(<?= $emp['id'] ?>, '<?= htmlspecialchars(addslashes($emp['full_name'])) ?>', '<?= htmlspecialchars($emp['username']) ?>')" class="btn-mono-outline" style="padding:6px 12px; font-size:12px;"><i data-lucide="plus" style="width:13px;"></i> Log Attendance Entry</button>
        </div>
        <div style="overflow-x:auto;">
            <table class="data-table" style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr>
                        <th>Attendance Date</th>
                        <th>Status</th>
                        <th>Punch In / Out</th>
                        <th>Worked Shift</th>
                        <th>Punch Method</th>
                        <th>Logged By &amp; Remarks</th>
                        <?php if ($is_admin): ?>
                            <th style="text-align:right;">Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody id="emp-attendance-tbody">
                    <tr><td colspan="7" style="text-align:center; padding:20px; color:var(--bw-muted);">Loading attendance history...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TAB 3: SALARY & PAYOUT LEDGER -->
    <div class="tab-pane" id="tab-pane-salary" style="display:none;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <div>
                <h3 style="font-size:16px; font-weight:800; color:var(--bw-black); margin:0;">Salary &amp; Disbursal Incentive Payout Ledger</h3>
                <p style="font-size:12px; color:var(--bw-muted); margin:2px 0 0 0;">Official payroll records, monthly base salary, incentives, and bank transfer receipts.</p>
            </div>
            <button onclick="openQuickSalaryModal(<?= $emp['id'] ?>, '<?= htmlspecialchars(addslashes($emp['full_name'])) ?>', '<?= htmlspecialchars($emp['username']) ?>', <?= (float)($emp['monthly_base_salary'] ?: 0) ?>)" class="btn-mono-primary" style="padding:6px 12px; font-size:12px;"><i data-lucide="plus" style="width:13px;"></i> Record Salary Payout</button>
        </div>
        <div style="overflow-x:auto;">
            <table class="data-table" style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr>
                        <th>Payment Month</th>
                        <th>Payment Date</th>
                        <th>Base Salary</th>
                        <th>Incentive &amp; Bonus</th>
                        <th>Deductions</th>
                        <th>Net Paid Amount</th>
                        <th>Mode &amp; UTR Ref</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="emp-salary-tbody">
                    <tr><td colspan="8" style="text-align:center; padding:20px; color:var(--bw-muted);">Loading salary ledger...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TAB 4: REAL-TIME ACTIVITY STREAM -->
    <div class="tab-pane" id="tab-pane-activity" style="display:none;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h3 style="font-size:16px; font-weight:800; color:var(--bw-black); margin:0;">Real-Time Activity Audit Stream ("Kon Kya Kar Raha Hai")</h3>
            <span style="font-size:11px; color:var(--bw-muted);">Live interaction &amp; audit log</span>
        </div>
        <div id="emp-activity-content">
            <p style="color:var(--bw-muted); text-align:center; padding:20px;">Loading activity audit stream...</p>
        </div>
    </div>

    <!-- TAB 5: HR DOSSIER & KYE -->
    <div class="tab-pane" id="tab-pane-dossier" style="display:none;">
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:24px;">
            <!-- Personal & Emergency Details -->
            <div style="background:var(--bw-light); border-radius:10px; border:1px solid var(--bw-border); padding:20px;">
                <h4 style="font-size:14px; font-weight:800; color:var(--bw-black); margin:0 0 14px 0; display:flex; align-items:center; gap:6px;">
                    <i data-lucide="user" style="width:16px; color:var(--bw-black);"></i> Personal &amp; Emergency Information
                </h4>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; font-size:13px;">
                    <div><span style="color:var(--bw-muted);">Full Name:</span><br><strong style="color:var(--bw-black);"><?= htmlspecialchars($emp['full_name']) ?></strong></div>
                    <div><span style="color:var(--bw-muted);">Employee Code:</span><br><strong style="color:var(--bw-black);"><?= htmlspecialchars($emp['emp_id']) ?></strong></div>
                    <div><span style="color:var(--bw-muted);">Official Email:</span><br><strong style="color:var(--bw-black);"><?= htmlspecialchars($emp['official_email']) ?></strong></div>
                    <div><span style="color:var(--bw-muted);">Personal Email:</span><br><strong style="color:var(--bw-black);"><?= htmlspecialchars($emp['personal_email'] ?: 'N/A') ?></strong></div>
                    <div><span style="color:var(--bw-muted);">Emergency Contact:</span><br><strong style="color:var(--bw-black);"><?= htmlspecialchars($emp['emergency_contact_name'] ?: 'N/A') ?> (<?= htmlspecialchars($emp['emergency_relation'] ?: '-') ?>)</strong></div>
                    <div><span style="color:var(--bw-muted);">Emergency Phone:</span><br><strong style="color:var(--bw-black);"><?= htmlspecialchars($emp['emergency_phone'] ?: 'N/A') ?></strong></div>
                </div>
            </div>

            <!-- Bank & Banking Details -->
            <div style="background:var(--bw-light); border-radius:10px; border:1px solid var(--bw-border); padding:20px;">
                <h4 style="font-size:14px; font-weight:800; color:var(--bw-black); margin:0 0 14px 0; display:flex; align-items:center; gap:6px;">
                    <i data-lucide="building-2" style="width:16px; color:var(--bw-black);"></i> Bank Account &amp; Tax Identifiers (KYE)
                </h4>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; font-size:13px;">
                    <div><span style="color:var(--bw-muted);">PAN Card Number:</span><br><strong style="color:var(--bw-black);"><?= htmlspecialchars($emp['pan_number'] ?: 'N/A') ?></strong></div>
                    <div><span style="color:var(--bw-muted);">Aadhaar Number:</span><br><strong style="color:var(--bw-black);"><?= htmlspecialchars($emp['aadhar_number'] ?: 'N/A') ?></strong></div>
                    <div><span style="color:var(--bw-muted);">Bank Account Name:</span><br><strong style="color:var(--bw-black);"><?= htmlspecialchars($emp['bank_holder_name'] ?: 'N/A') ?></strong></div>
                    <div><span style="color:var(--bw-muted);">Bank Name:</span><br><strong style="color:var(--bw-black);"><?= htmlspecialchars($emp['bank_name'] ?: 'N/A') ?></strong></div>
                    <div><span style="color:var(--bw-muted);">Account Number:</span><br><strong style="color:var(--bw-black);"><?= htmlspecialchars($emp['bank_account_no'] ?: 'N/A') ?></strong></div>
                    <div><span style="color:var(--bw-muted);">IFSC Code:</span><br><strong style="color:var(--bw-black);"><?= htmlspecialchars($emp['bank_ifsc'] ?: 'N/A') ?></strong></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- QUICK ATTENDANCE MODAL -->
<div id="quick-attendance-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.8); z-index:9999; justify-content:center; align-items:center; backdrop-filter:blur(4px);">
    <div style="background:#fff; border-radius:12px; width:90%; max-width:480px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); overflow:hidden;">
        <div style="padding:16px 20px; background:var(--bw-black); color:#fff; display:flex; justify-content:space-between; align-items:center;">
            <div style="display:flex; align-items:center; gap:8px;">
                <i data-lucide="calendar-check" style="width:18px;"></i>
                <span style="font-weight:800; font-size:15px;">Record Daily Attendance</span>
            </div>
            <button onclick="closeQuickAttendanceModal()" style="background:none; border:none; color:#fff; cursor:pointer; font-size:20px;">&times;</button>
        </div>
        <form id="quick-attendance-form" onsubmit="submitQuickAttendance(event)" style="padding:20px;">
            <input type="hidden" name="employee_id" id="att_emp_id" value="">
            <input type="hidden" name="emp_username" id="att_emp_username" value="">

            <div style="background:var(--bw-light); border:1px solid var(--bw-border-dark); padding:10px 14px; border-radius:8px; margin-bottom:16px;">
                <div style="font-size:11px; color:var(--bw-muted); font-weight:700; text-transform:uppercase;">Staff Member</div>
                <div id="att_emp_name_display" style="font-size:14px; font-weight:700; color:var(--bw-black); margin-top:2px;">-</div>
            </div>

            <div style="margin-bottom:14px;">
                <label class="required" style="font-size:12px; font-weight:700; color:var(--bw-dark); margin-bottom:4px; display:block;">Attendance Date</label>
                <input type="date" name="attendance_date" id="att_date" value="<?= date('Y-m-d') ?>" required class="mono-input" style="width:100%; box-sizing:border-box;">
            </div>

            <div style="margin-bottom:14px;">
                <label class="required" style="font-size:12px; font-weight:700; color:var(--bw-dark); margin-bottom:4px; display:block;">Attendance Status</label>
                <select name="status" id="att_status" required class="mono-select" style="width:100%; box-sizing:border-box;">
                    <option value="Present">Present (Full Day)</option>
                    <option value="Half Day">Half Day</option>
                    <option value="On Leave">On Leave (Unpaid)</option>
                    <option value="Paid Leave">Paid Leave (CL / SL)</option>
                    <option value="Absent">Absent</option>
                </select>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
                <div>
                    <label style="font-size:12px; font-weight:700; color:var(--bw-dark); margin-bottom:4px; display:block;">Check-In Time</label>
                    <input type="time" name="check_in_time" id="att_cin_time" class="mono-input" style="width:100%; box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:12px; font-weight:700; color:var(--bw-dark); margin-bottom:4px; display:block;">Check-Out Time</label>
                    <input type="time" name="check_out_time" id="att_cout_time" class="mono-input" style="width:100%; box-sizing:border-box;">
                </div>
            </div>

            <div style="margin-bottom:20px;">
                <label style="font-size:12px; font-weight:700; color:var(--bw-dark); margin-bottom:4px; display:block;">Remarks / Notes</label>
                <input type="text" name="notes" id="att_notes" placeholder="e.g. On-site client visit" class="mono-input" style="width:100%; box-sizing:border-box;">
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="closeQuickAttendanceModal()" class="btn-mono-outline">Cancel</button>
                <button type="submit" id="att_submit_btn" class="btn-mono-primary"><i data-lucide="check" style="width:14px;"></i> Save Attendance Log</button>
            </div>
        </form>
    </div>
</div>

<!-- QUICK SALARY PAYOUT MODAL -->
<div id="quick-salary-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.8); z-index:9999; justify-content:center; align-items:center; backdrop-filter:blur(4px);">
    <div style="background:#fff; border-radius:12px; width:90%; max-width:560px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); overflow:hidden;">
        <div style="padding:16px 20px; background:var(--bw-black); color:#fff; display:flex; justify-content:space-between; align-items:center;">
            <div style="display:flex; align-items:center; gap:8px;">
                <i data-lucide="banknote" style="width:18px;"></i>
                <span style="font-weight:800; font-size:15px;">Record Salary &amp; Incentive Payout</span>
            </div>
            <button onclick="closeQuickSalaryModal()" style="background:none; border:none; color:#fff; cursor:pointer; font-size:20px;">&times;</button>
        </div>
        <form id="quick-salary-form" onsubmit="submitQuickSalary(event)" style="padding:20px;">
            <input type="hidden" name="employee_id" id="sal_emp_id" value="">
            <input type="hidden" name="emp_username" id="sal_emp_username" value="">

            <div style="background:var(--bw-light); border:1px solid var(--bw-border-dark); padding:10px 14px; border-radius:8px; margin-bottom:16px;">
                <div style="font-size:11px; color:var(--bw-muted); font-weight:700; text-transform:uppercase;">Recipient Staff Member</div>
                <div id="sal_emp_name_display" style="font-size:14px; font-weight:700; color:var(--bw-black); margin-top:2px;">-</div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                <div>
                    <label class="required" style="font-size:12px; font-weight:700; color:var(--bw-dark); margin-bottom:4px; display:block;">Payout Month</label>
                    <input type="month" name="payment_month" id="sal_month" value="<?= date('Y-m') ?>" required class="mono-input" style="width:100%; box-sizing:border-box;">
                </div>
                <div>
                    <label class="required" style="font-size:12px; font-weight:700; color:var(--bw-dark); margin-bottom:4px; display:block;">Payment Date</label>
                    <input type="date" name="payment_date" id="sal_date" value="<?= date('Y-m-d') ?>" required class="mono-input" style="width:100%; box-sizing:border-box;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                <div>
                    <label class="required" style="font-size:12px; font-weight:700; color:var(--bw-dark); margin-bottom:4px; display:block;">Base Salary (₹)</label>
                    <input type="number" step="0.01" name="base_salary" id="sal_base" oninput="calcNetSalary()" required class="mono-input" style="width:100%; box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:12px; font-weight:700; color:var(--bw-dark); margin-bottom:4px; display:block;">Disbursal Incentive (₹)</label>
                    <input type="number" step="0.01" name="incentive_amount" id="sal_incentive" oninput="calcNetSalary()" value="0" class="mono-input" style="width:100%; box-sizing:border-box;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                <div>
                    <label style="font-size:12px; font-weight:700; color:var(--bw-dark); margin-bottom:4px; display:block;">Performance Bonus (₹)</label>
                    <input type="number" step="0.01" name="bonus_amount" id="sal_bonus" oninput="calcNetSalary()" value="0" class="mono-input" style="width:100%; box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:12px; font-weight:700; color:var(--bw-dark); margin-bottom:4px; display:block;">TDS / Deductions (₹)</label>
                    <input type="number" step="0.01" name="deductions_amount" id="sal_deductions" oninput="calcNetSalary()" value="0" class="mono-input" style="width:100%; box-sizing:border-box;">
                </div>
            </div>

            <div style="background:var(--bw-light); border:1px solid var(--bw-border-dark); padding:12px; border-radius:8px; margin-bottom:14px; display:flex; justify-content:space-between; align-items:center;">
                <span style="font-weight:700; font-size:13px; color:var(--bw-dark);">Net Payable Amount:</span>
                <span style="font-size:18px; font-weight:900; color:var(--bw-black); font-family:'Outfit',sans-serif;" id="sal_net_display">₹0</span>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:20px;">
                <div>
                    <label class="required" style="font-size:12px; font-weight:700; color:var(--bw-dark); margin-bottom:4px; display:block;">Payment Mode</label>
                    <select name="payment_mode" id="sal_mode" required class="mono-select" style="width:100%; box-sizing:border-box;">
                        <option value="Bank Transfer">NEFT / RTGS Bank Transfer</option>
                        <option value="UPI">UPI Transfer</option>
                        <option value="Cheque">Bank Cheque</option>
                        <option value="Cash">Cash Payout</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:12px; font-weight:700; color:var(--bw-dark); margin-bottom:4px; display:block;">Transaction UTR / Ref No</label>
                    <input type="text" name="transaction_ref" id="sal_tx_ref" placeholder="e.g. UTR12345678" class="mono-input" style="width:100%; box-sizing:border-box;">
                </div>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="closeQuickSalaryModal()" class="btn-mono-outline">Cancel</button>
                <button type="submit" id="sal_submit_btn" class="btn-mono-primary"><i data-lucide="check" style="width:14px;"></i> Issue Payout Receipt</button>
            </div>
        </form>
    </div>
</div>

<script>
const targetEmpId = <?= $emp['id'] ?>;
let emp360Data = null;

async function loadEmployee360() {
    try {
        const res = await fetch(`api.php?api=get_employee_360_data&id=${targetEmpId}`);
        const data = await res.json();

        if (!data || !data.success) {
            showNotification(data.error || 'Failed to load employee details', 'error');
            return;
        }

        emp360Data = data;
        renderKPIs(data);
        renderPortfolioTab(data.assigned_files || []);
        renderAttendanceTab(data.attendance_logs || []);
        renderSalaryTab(data.salary_payments || []);
        renderActivityTab(data.activity_log || []);
    } catch (e) {
        console.error("Error loading employee 360:", e);
    }
}

function formatIndianCurrency(amount) {
    let x = Math.round(amount || 0).toString();
    let lastThree = x.slice(-3);
    let otherNumbers = x.slice(0, -3);
    if (otherNumbers !== '') lastThree = ',' + lastThree;
    let res = otherNumbers.replace(/\B(?=(\d{2})+(?!\d))/g, ",") + lastThree;
    return '₹' + res;
}

function renderKPIs(d) {
    const perf = d.performance || {};
    const att = d.attendance_summary || {};

    document.getElementById('kpi-disbursed-val').innerText = formatIndianCurrency(perf.disbursed_val || 0);
    document.getElementById('kpi-disbursed-count').innerText = `${perf.disbursed_count || 0} Disbursed Files (${perf.total_assigned_count || 0} Active)`;
    document.getElementById('kpi-incentive-val').innerText = formatIndianCurrency(perf.estimated_incentive || 0);
    document.getElementById('kpi-present-count').innerText = `${att.Present || 0} Days Present`;
    document.getElementById('kpi-absent-count').innerText = `${att.Absent || 0} Absences MTD`;
}

function switchEmpTab(tabKey) {
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-pane').forEach(pane => pane.style.display = 'none');

    const activeBtn = document.getElementById(`tab-btn-${tabKey}`);
    const activePane = document.getElementById(`tab-pane-${tabKey}`);

    if (activeBtn) activeBtn.classList.add('active');
    if (activePane) activePane.style.display = 'block';

    lucide.createIcons();
}

function renderPortfolioTab(files) {
    document.getElementById('portfolio-count-badge').innerText = `${files.length} Files`;
    const tbody = document.getElementById('emp-portfolio-tbody');

    if (!files || files.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:20px; color:var(--bw-muted);">No loan applications assigned to this employee.</td></tr>';
        return;
    }

    let html = '';
    files.forEach(f => {
        html += `
            <tr>
                <td>
                    <div style="font-weight:700; color:var(--bw-black); font-size:13.5px;">${f.customer_name}</div>
                    <div style="font-size:11.5px; color:var(--bw-muted); font-weight:600;">ID: ${f.loan_id}</div>
                </td>
                <td>
                    <div style="font-weight:600; color:var(--bw-dark);">${f.loan_type || 'Loan'}</div>
                    <div style="font-size:13px; font-weight:700; color:var(--bw-black);">${formatIndianCurrency(f.loan_amount_requested)}</div>
                </td>
                <td>
                    <span class="badge" style="background:var(--bw-light); color:var(--bw-black); border:1px solid var(--bw-border-dark); font-weight:700;">${f.overall_status}</span>
                </td>
                <td>${f.sanction_bank || '<span style="color:var(--bw-muted); font-size:11px;">In Appraisal</span>'}</td>
                <td style="font-size:12px; color:var(--bw-muted);">${f.created_at}</td>
                <td style="text-align:right;">
                    <a href="search_track.php?id=${f.id}" class="btn-mono-outline" style="padding:4px 8px; font-size:11px;"><i data-lucide="external-link" style="width:12px;"></i> View File</a>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
    lucide.createIcons();
}

function renderAttendanceTab(logs) {
    const tbody = document.getElementById('emp-attendance-tbody');

    if (!logs || logs.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:20px; color:var(--bw-muted);">No attendance entries recorded for this employee yet.</td></tr>';
        return;
    }

    let html = '';
    logs.forEach(a => {
        const statusBadge = a.status === 'Present'
            ? '<span class="badge" style="background:var(--bw-black); color:#ffffff; font-weight:700;">Present</span>'
            : (a.status === 'Half Day' ? '<span class="badge" style="background:var(--bw-light); color:var(--bw-black); border:1px solid var(--bw-border-dark); font-weight:700;">Half Day</span>' : '<span class="badge" style="background:var(--bw-light); color:var(--bw-muted); border:1px solid var(--bw-border-dark);">' + a.status + '</span>');

        const cinStr = a.check_in_time ? new Date(a.check_in_time.replace(/-/g, '/')).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : '--:--';
        const coutStr = a.check_out_time ? new Date(a.check_out_time.replace(/-/g, '/')).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : '--:--';

        let shiftStr = '-';
        if (a.total_minutes_worked > 0) {
            const h = Math.floor(a.total_minutes_worked / 60);
            const m = a.total_minutes_worked % 60;
            shiftStr = `<strong>${h}h ${m}m</strong>`;
        }

        html += `
            <tr>
                <td style="font-weight:700; color:var(--bw-black);">${a.attendance_date}</td>
                <td>${statusBadge}</td>
                <td style="font-size:12.5px; color:var(--bw-dark);">
                    <span style="color:var(--bw-black); font-weight:600;">IN: ${cinStr}</span> | 
                    <span style="color:var(--bw-muted);">OUT: ${coutStr}</span>
                </td>
                <td>${shiftStr}</td>
                <td><span class="badge" style="background:var(--bw-light); color:var(--bw-black); border:1px solid var(--bw-border-dark); font-size:10.5px;">${a.punch_method || 'Manual'}</span></td>
                <td style="font-size:12px; color:var(--bw-muted);">
                    <strong>${a.logged_by || 'Admin'}</strong><br>${a.notes || '-'}
                </td>
                <?php if ($is_admin): ?>
                    <td style="text-align:right;">
                        <button onclick="deleteAttendanceLog(${a.id}, '${a.attendance_date}')" class="btn-mono-subtle" style="padding:4px 8px; font-size:11px;" title="Delete Attendance Log">
                            <i data-lucide="trash-2" style="width:12px;"></i> Delete
                        </button>
                    </td>
                <?php endif; ?>
            </tr>
        `;
    });

    tbody.innerHTML = html;
    lucide.createIcons();
}

async function deleteAttendanceLog(attId, attDate) {
    if (!confirm(`Are you sure you want to permanently delete the attendance log for date ${attDate}? This action cannot be undone.`)) {
        return;
    }

    const formData = new FormData();
    formData.append('attendance_id', attId);

    try {
        const res = await fetch('api.php?api=delete_employee_attendance', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data && data.success) {
            showNotification(data.message, 'success');
            loadEmployee360();
        } else {
            showNotification(data.error || 'Failed to delete attendance log', 'error');
        }
    } catch (e) {
        showNotification('Network communication error', 'error');
    }
}

function renderSalaryTab(payments) {
    const tbody = document.getElementById('emp-salary-tbody');

    if (!payments || payments.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:20px; color:var(--bw-muted);">No salary or incentive payout receipts issued yet.</td></tr>';
        return;
    }

    let html = '';
    payments.forEach(p => {
        html += `
            <tr>
                <td style="font-weight:700; color:var(--bw-black);">${p.payment_month}</td>
                <td style="font-size:12.5px; color:var(--bw-dark);">${p.payment_date}</td>
                <td style="font-weight:600; color:var(--bw-black);">₹${parseFloat(p.base_salary || 0).toLocaleString('en-IN')}</td>
                <td style="color:var(--bw-black); font-weight:700;">+₹${parseFloat((p.incentive_amount||0) + (p.bonus_amount||0)).toLocaleString('en-IN')}</td>
                <td style="color:var(--bw-muted);">-₹${parseFloat(p.deductions_amount || 0).toLocaleString('en-IN')}</td>
                <td style="font-size:14px; font-weight:900; color:var(--bw-black); font-family:'Outfit',sans-serif;">₹${parseFloat(p.net_salary_paid || 0).toLocaleString('en-IN')}</td>
                <td style="font-size:12px; color:var(--bw-muted);">${p.payment_mode}<br><span style="font-weight:600; color:var(--bw-black);">${p.transaction_ref || '-'}</span></td>
                <td><span class="badge" style="background:var(--bw-black); color:#ffffff; font-weight:700;">Paid</span></td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
    lucide.createIcons();
}

function renderActivityTab(acts) {
    const box = document.getElementById('emp-activity-content');

    if (!acts || acts.length === 0) {
        box.innerHTML = '<p style="color:var(--bw-muted); text-align:center; padding:20px;">No activity logs recorded for this employee yet.</p>';
        return;
    }

    let html = '<div style="display:flex; flex-direction:column; gap:8px;">';
    acts.forEach(a => {
        html += `
            <div style="padding:10px 14px; background:var(--bw-light); border-radius:8px; border:1px solid var(--bw-border); display:flex; justify-content:space-between; align-items:center;">
                <div style="font-size:13px; color:var(--bw-dark); font-weight:500;">${a.description}</div>
                <div style="font-size:11px; color:var(--bw-muted); white-space:nowrap; font-weight:600; margin-left:14px;">${a.created_at}</div>
            </div>
        `;
    });
    html += '</div>';

    box.innerHTML = html;
    lucide.createIcons();
}

// Quick Modals Integration
function openQuickAttendanceModal(empId, empName, username) {
    document.getElementById('att_emp_id').value = empId;
    document.getElementById('att_emp_username').value = username;
    document.getElementById('att_emp_name_display').innerText = empName + ' (' + username + ')';
    document.getElementById('att_notes').value = '';
    document.getElementById('quick-attendance-modal').style.display = 'flex';
    lucide.createIcons();
}

function closeQuickAttendanceModal() {
    document.getElementById('quick-attendance-modal').style.display = 'none';
}

async function submitQuickAttendance(e) {
    e.preventDefault();
    const form = document.getElementById('quick-attendance-form');
    const formData = new FormData(form);
    const btn = document.getElementById('att_submit_btn');

    btn.disabled = true;
    btn.innerText = 'Saving...';

    try {
        const res = await fetch('api.php?api=record_employee_attendance', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data && data.success) {
            showNotification(data.message, 'success');
            closeQuickAttendanceModal();
            loadEmployee360();
        } else {
            showNotification(data.error || 'Failed to record attendance', 'error');
        }
    } catch (err) {
        showNotification('Network error', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="check" style="width:14px;"></i> Save Attendance Log';
        lucide.createIcons();
    }
}

function openQuickSalaryModal(empId, empName, username, baseSal) {
    document.getElementById('sal_emp_id').value = empId;
    document.getElementById('sal_emp_username').value = username;
    document.getElementById('sal_emp_name_display').innerText = empName + ' (' + username + ')';
    document.getElementById('sal_base').value = baseSal || 0;
    document.getElementById('sal_incentive').value = 0;
    document.getElementById('sal_bonus').value = 0;
    document.getElementById('sal_deductions').value = 0;
    document.getElementById('sal_tx_ref').value = '';
    calcNetSalary();

    document.getElementById('quick-salary-modal').style.display = 'flex';
    lucide.createIcons();
}

function closeQuickSalaryModal() {
    document.getElementById('quick-salary-modal').style.display = 'none';
}

function calcNetSalary() {
    const base = parseFloat(document.getElementById('sal_base').value) || 0;
    const inc = parseFloat(document.getElementById('sal_incentive').value) || 0;
    const bon = parseFloat(document.getElementById('sal_bonus').value) || 0;
    const ded = parseFloat(document.getElementById('sal_deductions').value) || 0;
    const net = Math.max(0, (base + inc + bon) - ded);
    document.getElementById('sal_net_display').innerText = '₹' + net.toLocaleString('en-IN');
}

async function submitQuickSalary(e) {
    e.preventDefault();
    const form = document.getElementById('quick-salary-form');
    const formData = new FormData(form);
    const btn = document.getElementById('sal_submit_btn');

    btn.disabled = true;
    btn.innerText = 'Processing Payout...';

    try {
        const res = await fetch('api.php?api=record_employee_salary_payment', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data && data.success) {
            showNotification(data.message, 'success');
            closeQuickSalaryModal();
            loadEmployee360();
        } else {
            showNotification(data.error || 'Failed to record salary payout', 'error');
        }
    } catch (err) {
        showNotification('Network error', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="check" style="width:14px;"></i> Issue Payout Receipt';
        lucide.createIcons();
    }
}

window.addEventListener('DOMContentLoaded', () => {
    loadEmployee360();
});
</script>

<?php require_once 'footer.php'; ?>
