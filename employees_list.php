<?php
require_once 'config.php';
require_role(['Admin'], false);
$page_title = 'Staff & HRMS Directory';
$page_subtitle = 'Executive Staff Surveillance, Attendance Tracking, Payroll Ledgers & HR Intelligence';
$hide_main_header = true;
require_once 'header.php';

$is_admin = (($_SESSION['role'] ?? '') === 'Admin');

// Fetch employees with their active status from users table (only internal staff: Staff, RM, Admin)
$stmt = $db->query("
    SELECT e.*, u.is_active, u.username, u.role
    FROM employees e 
    JOIN users u ON e.user_id = u.id 
    WHERE u.role IN ('Staff', 'RM', 'Admin')
    ORDER BY e.id DESC
");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate Directory Summary Metrics
$total_employees = count($employees);
$active_employees = 0;
$total_base_payroll = 0;

foreach ($employees as $e) {
    if ($e['is_active']) $active_employees++;
    $total_base_payroll += (float)($e['monthly_base_salary'] ?: 0);
}

// Attendance Today Count
$today = date('Y-m-d');
$att_today_count = (int)$db->query("SELECT COUNT(*) FROM employee_attendance WHERE attendance_date = '$today' AND status IN ('Present', 'Half Day')")->fetchColumn();
?>

<style>
/* Pure Professional Monochrome Corporate Theme (Black & White) */
:root {
    --bw-black: #0f172a;
    --bw-dark: #1e293b;
    --bw-mid: #475569;
    --bw-muted: #64748b;
    --bw-light: #f8fafc;
    --bw-border: #e2e8f0;
    --bw-border-dark: #cbd5e1;
}

.hrms-metrics-banner {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}

.hrms-metric-card {
    background: #ffffff;
    border: 1px solid var(--bw-border);
    border-radius: 10px;
    padding: 16px 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    display: flex;
    align-items: center;
    gap: 14px;
    transition: all 0.2s ease;
}

.hrms-metric-card:hover {
    border-color: var(--bw-black);
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
}

.hrms-icon-box {
    width: 42px;
    height: 42px;
    border-radius: 8px;
    background: var(--bw-black);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.hrms-control-bar {
    background: #ffffff;
    border: 1px solid var(--bw-border);
    border-radius: 12px;
    padding: 10px 16px;
    margin-bottom: 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    overflow-x: auto;
}

.mono-filter-group {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1;
    min-width: 0;
}

.mono-search-wrapper {
    position: relative;
    flex: 1;
    min-width: 200px;
    max-width: 320px;
}

.mono-search-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    width: 15px;
    height: 15px;
    color: var(--bw-muted);
    pointer-events: none;
}

.mono-input-field {
    width: 100% !important;
    height: 38px !important;
    padding: 0 12px 0 36px !important;
    border: 1px solid var(--bw-border-dark) !important;
    border-radius: 8px !important;
    font-size: 13px !important;
    color: var(--bw-black) !important;
    background: #ffffff !important;
    outline: none !important;
    box-sizing: border-box !important;
    transition: all 0.2s ease !important;
}

.mono-input-field:focus {
    border-color: var(--bw-black) !important;
    box-shadow: 0 0 0 3px rgba(15, 23, 42, 0.08) !important;
}

.mono-select-field {
    height: 38px !important;
    padding: 0 28px 0 12px !important;
    border: 1px solid var(--bw-border-dark) !important;
    border-radius: 8px !important;
    font-size: 13px !important;
    font-weight: 600 !important;
    color: var(--bw-black) !important;
    background: #ffffff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%230f172a' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E") no-repeat right 10px center !important;
    background-size: 14px !important;
    -webkit-appearance: none !important;
    -moz-appearance: none !important;
    appearance: none !important;
    outline: none !important;
    box-sizing: border-box !important;
    cursor: pointer !important;
    flex-shrink: 0 !important;
    transition: all 0.2s ease !important;
}

.mono-select-field:focus {
    border-color: var(--bw-black) !important;
    box-shadow: 0 0 0 3px rgba(15, 23, 42, 0.08) !important;
}

#staffDepartmentFilter {
    width: 210px !important;
}

#staffStatusFilter {
    width: 170px !important;
}

/* Premium Segmented View Switcher */
.view-switch-segmented {
    display: inline-flex;
    background: var(--bw-light);
    border: 1px solid var(--bw-border-dark);
    padding: 3px;
    border-radius: 9px;
    gap: 3px;
    flex-shrink: 0;
    box-shadow: inset 0 1px 2px rgba(0,0,0,0.03);
}

.seg-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 15px;
    font-size: 12.5px;
    font-weight: 700;
    border-radius: 7px;
    border: none;
    background: transparent;
    color: var(--bw-muted);
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    white-space: nowrap;
}

.seg-btn:hover {
    color: var(--bw-black);
    background: rgba(15, 23, 42, 0.04);
}

.seg-btn.active {
    background: var(--bw-black) !important;
    color: #ffffff !important;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.18) !important;
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

/* Executive 3-Column HRMS Staff Grid */
.staff-grid {
    display: grid !important;
    grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
    gap: 20px !important;
    margin-bottom: 30px !important;
    width: 100% !important;
    box-sizing: border-box !important;
}

@media (max-width: 1200px) {
    .staff-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 16px !important;
    }
}

@media (max-width: 768px) {
    .staff-grid {
        grid-template-columns: 1fr !important;
        gap: 16px !important;
    }
}

/* Luxury Staff Card (BFS Circle Executive Edition) */
.staff-card {
    background: #ffffff !important;
    border: 1px solid var(--bw-border) !important;
    border-radius: 14px !important;
    padding: 20px !important;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04) !important;
    display: flex !important;
    flex-direction: column !important;
    justify-content: space-between !important;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1) !important;
    position: relative !important;
    box-sizing: border-box !important;
    overflow: hidden !important;
}

.staff-card:hover {
    border-color: var(--bw-black) !important;
    box-shadow: 0 12px 28px rgba(15, 23, 42, 0.09) !important;
    transform: translateY(-3px) !important;
}

.staff-avatar-img {
    width: 76px !important;
    height: 76px !important;
    border-radius: 50% !important;
    object-fit: cover !important;
    border: 2px solid var(--bw-black) !important;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.12) !important;
    display: block !important;
    margin: 0 auto !important;
}

.staff-avatar-placeholder {
    width: 76px !important;
    height: 76px !important;
    border-radius: 50% !important;
    background: var(--bw-black) !important;
    color: #ffffff !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-size: 26px !important;
    font-weight: 800 !important;
    font-family: 'Outfit', sans-serif !important;
    border: 2px solid var(--bw-black) !important;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.12) !important;
    margin: 0 auto !important;
    user-select: none !important;
}

.staff-code-tag {
    display: inline-flex;
    align-items: center;
    background: var(--bw-light);
    border: 1px solid var(--bw-border-dark);
    border-radius: 6px;
    overflow: hidden;
    font-family: 'Outfit', sans-serif;
    font-size: 11px;
}

.staff-code-brand {
    background: var(--bw-black);
    color: #ffffff;
    font-weight: 800;
    font-size: 9px;
    letter-spacing: 0.6px;
    padding: 3px 7px;
    text-transform: uppercase;
}

.staff-code-num {
    padding: 2px 8px;
    font-weight: 700;
    color: var(--bw-black);
}

.staff-status-active {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: var(--bw-black);
    color: #ffffff;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.5px;
    padding: 3px 9px;
    border-radius: 20px;
}

.staff-status-inactive {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: var(--bw-light);
    color: var(--bw-muted);
    border: 1px solid var(--bw-border-dark);
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.5px;
    padding: 2px 9px;
    border-radius: 20px;
}

.staff-pulse-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #10b981;
    display: inline-block;
}

.staff-info-box {
    background: var(--bw-light);
    border: 1px solid var(--bw-border);
    border-radius: 10px;
    padding: 12px 14px;
    margin: 14px 0 16px 0;
    font-size: 12px;
}

.staff-action-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 6px;
}
</style>

<div class="view-container">
    <!-- Clean Action Bar (No Duplicate Title) -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; flex-wrap:wrap; gap:12px;">
        <div style="font-size:13px; color:var(--bw-muted); font-weight:600;">
            Manage employee directory, daily attendance logs, monthly base salaries, and performance dossiers.
        </div>
        <div style="display:flex; gap:10px; align-items:center;">
            <?php if ($is_admin): ?>
                <a href="attendance_sheet.php" class="btn-mono-outline">
                    <i data-lucide="calendar-check" style="width:14px;"></i> Master Attendance Register
                </a>
                <a href="add_employee.php" class="btn-mono-primary">
                    <i data-lucide="user-plus" style="width:14px;"></i> + Add New Employee
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- 4 High-Impact Monochrome Metric Cards -->
    <div class="hrms-metrics-banner">
        <div class="hrms-metric-card">
            <div class="hrms-icon-box">
                <i data-lucide="users" style="width:20px;"></i>
            </div>
            <div>
                <div style="font-size:22px; font-weight:900; color:var(--bw-black); font-family:'Outfit',sans-serif;"><?= $total_employees ?></div>
                <div style="font-size:11px; font-weight:700; color:var(--bw-muted); text-transform:uppercase; letter-spacing:0.5px;">Registered Staff</div>
            </div>
        </div>

        <div class="hrms-metric-card">
            <div class="hrms-icon-box" style="background:#ffffff; color:var(--bw-black); border:1px solid var(--bw-black);">
                <i data-lucide="user-check" style="width:20px;"></i>
            </div>
            <div>
                <div style="font-size:22px; font-weight:900; color:var(--bw-black); font-family:'Outfit',sans-serif;"><?= $active_employees ?></div>
                <div style="font-size:11px; font-weight:700; color:var(--bw-muted); text-transform:uppercase; letter-spacing:0.5px;">Active Officers</div>
            </div>
        </div>

        <div class="hrms-metric-card">
            <div class="hrms-icon-box">
                <i data-lucide="banknote" style="width:20px;"></i>
            </div>
            <div>
                <div style="font-size:22px; font-weight:900; color:var(--bw-black); font-family:'Outfit',sans-serif;">₹<?= number_format($total_base_payroll) ?></div>
                <div style="font-size:11px; font-weight:700; color:var(--bw-muted); text-transform:uppercase; letter-spacing:0.5px;">Monthly Base Payroll</div>
            </div>
        </div>

        <div class="hrms-metric-card">
            <div class="hrms-icon-box" style="background:#ffffff; color:var(--bw-black); border:1px solid var(--bw-black);">
                <i data-lucide="calendar-check" style="width:20px;"></i>
            </div>
            <div>
                <div style="font-size:22px; font-weight:900; color:var(--bw-black); font-family:'Outfit',sans-serif;"><?= $att_today_count ?> / <?= $total_employees ?></div>
                <div style="font-size:11px; font-weight:700; color:var(--bw-muted); text-transform:uppercase; letter-spacing:0.5px;">Present Today</div>
            </div>
        </div>
    </div>

    <!-- Unified Single-Row Filter Toolbar & Segmented View Switcher -->
    <div class="hrms-control-bar">
        <div class="mono-filter-group">
            <div class="mono-search-wrapper">
                <i data-lucide="search" class="mono-search-icon"></i>
                <input type="text" id="staffSearch" onkeyup="filterStaff()" class="mono-input-field" placeholder="Search by name, ID, mobile...">
            </div>

            <select id="staffDepartmentFilter" onchange="filterStaff()" class="mono-select-field">
                <option value="">All Departments</option>
                <option value="Lead Generation Team">Lead Generation Team</option>
                <option value="Digital Marketing Team">Digital Marketing Team</option>
                <option value="Content & Education Team">Content & Education Team</option>
                <option value="Customer Relationship Team">Customer Relationship Team</option>
                <option value="IT & Systems Team">IT & Systems Team</option>
                <option value="Operations & HR">Operations & HR</option>
            </select>

            <select id="staffStatusFilter" onchange="filterStaff()" class="mono-select-field">
                <option value="">All Employment Statuses</option>
                <option value="Active">Active Staff</option>
                <option value="Inactive">Inactive Staff</option>
            </select>
        </div>

        <div class="view-switch-segmented">
            <button id="btn-view-grid" onclick="switchViewMode('grid')" class="seg-btn active">
                <i data-lucide="layout-grid" style="width:14px; height:14px;"></i>
                <span>Grid Cards</span>
            </button>
            <button id="btn-view-table" onclick="switchViewMode('table')" class="seg-btn">
                <i data-lucide="list" style="width:14px; height:14px;"></i>
                <span>Data Table</span>
            </button>
        </div>
    </div>

    <!-- GRID CARDS VIEW (3 CARDS PER ROW EXECUTIVE MONOCHROME) -->
    <div class="staff-grid" id="staffGrid">
        <?php if (empty($employees)): ?>
            <div style="grid-column:1/-1; text-align:center; padding:60px 20px; background:#ffffff; border-radius:14px; border:1px solid var(--bw-border); color:var(--bw-muted);">
                <i data-lucide="users" style="width:42px; height:42px; margin-bottom:12px; color:var(--bw-black);"></i>
                <h3 style="color:var(--bw-black); margin:0 0 6px 0; font-weight:800; font-family:'Outfit',sans-serif;">No Staff Officers Registered Yet</h3>
                <p style="margin:0; font-size:13px;">Click "+ Add New Employee" above to build your BFS Circle HRMS directory.</p>
            </div>
        <?php else: ?>
            <?php foreach ($employees as $emp): 
                $isAct = $emp['is_active'];
                $dispName = $emp['full_name'];
                $code = $emp['emp_id'];
                $dept = $emp['department'];
                $role = $emp['designation'];
                $salary = (float)($emp['monthly_base_salary'] ?: 0);
                $comm = (float)($emp['commission_rate'] ?: 1.0);
            ?>
                <div class="staff-card staff-row-item" data-department="<?= htmlspecialchars($dept) ?>" data-status="<?= $isAct ? 'Active' : 'Inactive' ?>" data-search="<?= strtolower(htmlspecialchars($dispName . ' ' . $code . ' ' . $emp['username'] . ' ' . $emp['mobile'] . ' ' . $dept)) ?>">
                    
                    <!-- Card Top Header with BFS Branding & Status -->
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
                        <div class="staff-code-tag" title="BFS Circle Staff Identification">
                            <span class="staff-code-brand">BFS CIRCLE</span>
                            <span class="staff-code-num"><?= htmlspecialchars($code) ?></span>
                        </div>
                        <?php if ($isAct): ?>
                            <span class="staff-status-active"><span class="staff-pulse-dot"></span>ACTIVE</span>
                        <?php else: ?>
                            <span class="staff-status-inactive">INACTIVE</span>
                        <?php endif; ?>
                    </div>

                    <!-- Staff Avatar & Identity Center -->
                    <div style="display:flex; flex-direction:column; align-items:center; text-align:center; margin-bottom:14px;">
                        <?php if(!empty($emp['photo_path']) && file_exists(__DIR__ . '/' . $emp['photo_path'])): ?>
                            <img src="<?php echo htmlspecialchars($emp['photo_path']); ?>" alt="Profile" class="staff-avatar-img">
                        <?php else: ?>
                            <div class="staff-avatar-placeholder">
                                <?= strtoupper(substr(htmlspecialchars($dispName), 0, 1)) ?>
                            </div>
                        <?php endif; ?>

                        <h3 style="font-size:16px; font-weight:800; color:var(--bw-black); margin:12px 0 2px 0; font-family:'Outfit',sans-serif; text-overflow:ellipsis; overflow:hidden; white-space:nowrap; max-width:100%;" title="<?= htmlspecialchars($dispName) ?>"><?= htmlspecialchars($dispName) ?></h3>
                        <div style="font-size:12.5px; font-weight:700; color:var(--bw-dark); text-overflow:ellipsis; overflow:hidden; white-space:nowrap; max-width:100%;"><?= htmlspecialchars($role) ?></div>
                        <div style="margin-top:6px;">
                            <span class="badge" style="background:#ffffff; color:var(--bw-dark); border:1px solid var(--bw-border-dark); font-size:10.5px; font-weight:700; padding:3px 10px; border-radius:20px; display:inline-flex; align-items:center; gap:4px;">
                                <i data-lucide="building" style="width:11px; height:11px;"></i> <?= htmlspecialchars($dept) ?>
                            </span>
                        </div>
                    </div>

                    <!-- Contact & Compensation Dossier Box -->
                    <div class="staff-info-box">
                        <div style="display:flex; align-items:center; gap:8px; color:var(--bw-dark); margin-bottom:6px; font-size:12px;">
                            <i data-lucide="phone" style="width:13px; height:13px; color:var(--bw-black); flex-shrink:0;"></i>
                            <a href="tel:<?= htmlspecialchars($emp['mobile']) ?>" style="font-weight:600; color:var(--bw-black); text-decoration:none;"><?= htmlspecialchars($emp['mobile']) ?></a>
                        </div>
                        <div style="display:flex; align-items:center; gap:8px; color:var(--bw-dark); margin-bottom:8px; font-size:12px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?= htmlspecialchars($emp['official_email']) ?>">
                            <i data-lucide="mail" style="width:13px; height:13px; color:var(--bw-black); flex-shrink:0;"></i>
                            <a href="mailto:<?= htmlspecialchars($emp['official_email']) ?>" style="font-weight:500; color:var(--bw-mid); text-decoration:none; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= htmlspecialchars($emp['official_email']) ?></a>
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center; pt-8px; margin-top:8px; border-top:1px dashed var(--bw-border-dark); font-size:11.5px;">
                            <div>
                                <span style="font-size:10px; font-weight:700; color:var(--bw-muted); text-transform:uppercase; letter-spacing:0.5px; display:block;">Base Salary</span>
                                <strong style="color:var(--bw-black); font-size:13.5px; font-family:'Outfit',sans-serif;">₹<?= number_format($salary) ?></strong>
                            </div>
                            <div style="text-align:right;">
                                <span style="font-size:10px; font-weight:700; color:var(--bw-muted); text-transform:uppercase; letter-spacing:0.5px; display:block;">Commission</span>
                                <strong style="color:var(--bw-black); font-size:13.5px; font-family:'Outfit',sans-serif;"><?= $comm ?>%</strong>
                            </div>
                        </div>
                    </div>

                    <!-- Action Suite -->
                    <div style="display:flex; flex-direction:column; gap:6px; margin-top:auto;">
                        <a href="view_employee.php?id=<?= $emp['id'] ?>" class="btn-mono-primary" style="justify-content:center; padding:9px 12px; font-size:12.5px; border-radius:8px;">
                            <i data-lucide="eye" style="width:14px; height:14px;"></i> View 360° Profile
                        </a>
                        <div class="staff-action-grid-2">
                            <button onclick="openQuickAttendanceModal(<?= $emp['id'] ?>, '<?= htmlspecialchars(addslashes($dispName)) ?>', '<?= htmlspecialchars($emp['username']) ?>')" class="btn-mono-outline" style="border-radius:7px; padding:7px 10px; font-size:11.5px;" title="Log Attendance Today"><i data-lucide="calendar" style="width:12px; height:12px;"></i> Attendance</button>
                            <button onclick="openQuickSalaryModal(<?= $emp['id'] ?>, '<?= htmlspecialchars(addslashes($dispName)) ?>', '<?= htmlspecialchars($emp['username']) ?>', <?= $salary ?>)" class="btn-mono-outline" style="border-radius:7px; padding:7px 10px; font-size:11.5px;" title="Record Payout"><i data-lucide="banknote" style="width:12px; height:12px;"></i> Payout</button>
                        </div>
                        <?php if ($is_admin): ?>
                            <div class="staff-action-grid-2" style="margin-top:2px;">
                                <a href="edit_employee.php?id=<?= $emp['id'] ?>" class="btn-mono-subtle" style="border-radius:7px; padding:6px 10px; font-size:11.5px; text-align:center;"><i data-lucide="edit-3" style="width:12px; height:12px;"></i> Edit</a>
                                <button onclick="deleteEmployee(<?= $emp['id'] ?>, '<?= htmlspecialchars(addslashes($dispName)) ?>')" class="btn-mono-subtle" style="border-radius:7px; padding:6px 10px; font-size:11.5px; color:var(--bw-black);"><i data-lucide="trash-2" style="width:12px; height:12px;"></i> Delete</button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- HIGH-DENSITY DATA TABLE VIEW -->
    <div id="staffTableView" style="display:none; background:#ffffff; border-radius:12px; border:1px solid var(--bw-border); padding:20px; box-shadow:0 1px 3px rgba(0,0,0,0.02);">
        <div style="overflow-x:auto;">
            <table class="data-table" style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr>
                        <th>Employee Code &amp; Officer</th>
                        <th>Designation &amp; Department</th>
                        <th>Contact Details</th>
                        <th>Base Salary (₹)</th>
                        <th>Commission Rate</th>
                        <th>Status</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody id="staffTableTbody">
                    <?php foreach ($employees as $emp): 
                        $isAct = $emp['is_active'];
                        $dispName = $emp['full_name'];
                    ?>
                        <tr class="staff-row-item" data-department="<?= htmlspecialchars($emp['department']) ?>" data-status="<?= $isAct ? 'Active' : 'Inactive' ?>" data-search="<?= strtolower(htmlspecialchars($dispName . ' ' . $emp['emp_id'] . ' ' . $emp['username'] . ' ' . $emp['mobile'] . ' ' . $emp['department'])) ?>">
                            <td>
                                <div style="font-weight:700; color:var(--bw-black); font-size:13.5px;"><?= htmlspecialchars($dispName) ?></div>
                                <div style="font-size:11.5px; color:var(--bw-muted);">Code: <span style="font-weight:700; color:var(--bw-black);"><?= htmlspecialchars($emp['emp_id']) ?></span> • <?= htmlspecialchars($emp['username']) ?></div>
                            </td>
                            <td>
                                <div style="font-weight:600; color:var(--bw-dark);"><?= htmlspecialchars($emp['designation']) ?></div>
                                <div style="font-size:11px; color:var(--bw-muted);"><?= htmlspecialchars($emp['department']) ?></div>
                            </td>
                            <td>
                                <div style="font-size:12.5px; color:var(--bw-dark);">📞 <?= htmlspecialchars($emp['mobile']) ?></div>
                                <div style="font-size:11.5px; color:var(--bw-muted);">✉️ <?= htmlspecialchars($emp['official_email']) ?></div>
                            </td>
                            <td>
                                <strong style="color:var(--bw-black);">₹<?= number_format((float)($emp['monthly_base_salary'] ?: 0)) ?></strong>
                            </td>
                            <td>
                                <span class="badge" style="background:var(--bw-light); color:var(--bw-black); border:1px solid var(--bw-border-dark);"><?= (float)($emp['commission_rate'] ?: 1.0) ?>%</span>
                            </td>
                            <td>
                                <?= $isAct 
                                    ? '<span class="badge" style="background:var(--bw-black); color:#ffffff;">Active</span>' 
                                    : '<span class="badge" style="background:var(--bw-light); color:var(--bw-muted); border:1px solid var(--bw-border-dark);">Inactive</span>' ?>
                            </td>
                            <td style="text-align:right;">
                                <div style="display:inline-flex; gap:6px;">
                                    <a href="view_employee.php?id=<?= $emp['id'] ?>" class="btn-mono-primary" style="padding:5px 10px; font-size:11px;"><i data-lucide="eye" style="width:12px;"></i> Profile</a>
                                    <button onclick="openQuickAttendanceModal(<?= $emp['id'] ?>, '<?= htmlspecialchars(addslashes($dispName)) ?>', '<?= htmlspecialchars($emp['username']) ?>')" class="btn-mono-outline" style="padding:5px 10px; font-size:11px;"><i data-lucide="calendar" style="width:12px;"></i> Attendance</button>
                                    <button onclick="openQuickSalaryModal(<?= $emp['id'] ?>, '<?= htmlspecialchars(addslashes($dispName)) ?>', '<?= htmlspecialchars($emp['username']) ?>', <?= (float)($emp['monthly_base_salary'] ?: 0) ?>)" class="btn-mono-outline" style="padding:5px 10px; font-size:11px;"><i data-lucide="banknote" style="width:12px;"></i> Payout</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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
function switchViewMode(mode) {
    const grid = document.getElementById('staffGrid');
    const table = document.getElementById('staffTableView');
    const btnGrid = document.getElementById('btn-view-grid');
    const btnTable = document.getElementById('btn-view-table');

    if (mode === 'grid') {
        grid.style.display = 'grid';
        table.style.display = 'none';
        btnGrid.classList.add('active');
        btnTable.classList.remove('active');
    } else {
        grid.style.display = 'none';
        table.style.display = 'block';
        btnGrid.classList.remove('active');
        btnTable.classList.add('active');
    }
}

function filterStaff() {
    const search = (document.getElementById('staffSearch').value || '').toLowerCase().trim();
    const deptFilter = document.getElementById('staffDepartmentFilter').value;
    const statusFilter = document.getElementById('staffStatusFilter').value;

    const items = document.querySelectorAll('.staff-row-item');
    items.forEach(el => {
        const dSearch = el.getAttribute('data-search') || '';
        const dDept = el.getAttribute('data-department') || '';
        const dStatus = el.getAttribute('data-status') || '';

        let show = true;
        if (search && !dSearch.includes(search)) show = false;
        if (deptFilter && dDept !== deptFilter) show = false;
        if (statusFilter && dStatus !== statusFilter) show = false;

        el.style.display = show ? '' : 'none';
    });
}

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

function deleteEmployee(id, name) {
    if (confirm('Are you sure you want to delete employee "' + name + '"? This will remove their user login access.')) {
        window.location.href = 'fix_employee.py?action=delete&id=' + id;
    }
}
</script>

<?php require_once 'footer.php'; ?>
