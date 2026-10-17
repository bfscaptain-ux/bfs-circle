<?php
require_once 'config.php';

require_role(['Admin'], false);


$id = intval($_GET['id'] ?? 0);
if (!$id) { header("Location: employees_list.php"); exit; }
$stmt = $db->prepare("SELECT e.*, u.username, u.role as access_role FROM employees e JOIN users u ON e.user_id = u.id WHERE e.id = ?");
$stmt->execute([$id]);
$emp = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$emp) { header("Location: employees_list.php"); exit; }

$page_title = 'Edit Staff Dossier: ' . htmlspecialchars($emp['full_name']);
$page_subtitle = 'Update employee personal records, KYE banking details, and access privileges';
$hide_main_header = true;
require_once 'header.php';
?>

<style>
/* Pure Black & White Corporate Monochrome Form Styling */
:root {
    --bw-black: #0f172a;
    --bw-dark: #1e293b;
    --bw-mid: #475569;
    --bw-muted: #64748b;
    --bw-light: #f8fafc;
    --bw-border: #e2e8f0;
    --bw-border-dark: #cbd5e1;
}

.mono-card {
    background: #ffffff;
    border: 1px solid var(--bw-border);
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    margin-bottom: 24px;
}

.mono-form-section {
    font-size: 14px;
    font-weight: 800;
    color: var(--bw-black);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding-bottom: 8px;
    margin: 24px 0 16px 0;
    border-bottom: 2px solid var(--bw-black);
}

.mono-form-section:first-of-type {
    margin-top: 0;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 16px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.form-group.full-width {
    grid-column: 1 / -1;
}

.form-group label {
    font-size: 12px;
    font-weight: 700;
    color: var(--bw-dark);
}

.form-group label.required::after {
    content: " *";
    color: var(--bw-black);
}

.mono-input, .mono-select, .mono-textarea {
    padding: 9px 12px;
    border: 1px solid var(--bw-border-dark);
    border-radius: 7px;
    font-size: 13px;
    color: var(--bw-black);
    background: #ffffff;
    outline: none;
    transition: all 0.2s;
    box-sizing: border-box;
    width: 100%;
}

.mono-input:focus, .mono-select:focus, .mono-textarea:focus {
    border-color: var(--bw-black);
    box-shadow: 0 0 0 3px rgba(15, 23, 42, 0.08);
}

.mono-input[readonly] {
    background: var(--bw-light);
    color: var(--bw-muted);
}

.btn-mono-primary {
    background: var(--bw-black);
    color: #ffffff;
    border: 1px solid var(--bw-black);
    padding: 9px 20px;
    border-radius: 7px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
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
    padding: 8px 16px;
    border-radius: 7px;
    font-size: 12.5px;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none;
    transition: all 0.2s;
}

.btn-mono-outline:hover {
    background: var(--bw-light);
    color: var(--bw-black);
}
</style>

<div class="view-container">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px;">
        <a href="view_employee.php?id=<?= $emp['id'] ?>" class="btn-mono-outline">
            <i data-lucide="arrow-left" style="width:14px;"></i> Back to Employee Profile
        </a>
        <span style="font-size:12px; color:var(--bw-muted); font-weight:600;">Updating Dossier #<?= htmlspecialchars($emp['emp_id']) ?></span>
    </div>

    <form id="edit-employee-form" onsubmit="saveEmployee(event)" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?= $emp['id'] ?>">

        <div class="mono-card">
            <!-- Section 1: Basic & Contact Details -->
            <div class="mono-form-section">Section 1: Basic &amp; Contact Information</div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="required">Employee Code</label>
                    <input type="text" name="emp_id" value="<?= htmlspecialchars($emp['emp_id']) ?>" readonly class="mono-input">
                </div>

                <div class="form-group">
                    <label class="required">Full Name (As per Identity Documents)</label>
                    <input type="text" name="full_name" value="<?= htmlspecialchars($emp['full_name'] ?? '') ?>" required class="mono-input" placeholder="First Middle Last">
                </div>

                <div class="form-group">
                    <label class="required">Official Email ID</label>
                    <input type="email" name="official_email" value="<?= htmlspecialchars($emp['official_email'] ?? '') ?>" required class="mono-input" placeholder="name@bfs.com">
                </div>

                <div class="form-group">
                    <label>Personal Email ID</label>
                    <input type="email" name="personal_email" value="<?= htmlspecialchars($emp['personal_email'] ?? '') ?>" class="mono-input" placeholder="personal@gmail.com">
                </div>

                <div class="form-group">
                    <label class="required">Mobile Phone Number</label>
                    <input type="text" name="mobile" value="<?= htmlspecialchars($emp['mobile'] ?? '') ?>" required class="mono-input" placeholder="10-digit mobile number">
                </div>

                <div class="form-group">
                    <label>Commission Rate (%)</label>
                    <input type="number" step="0.01" name="commission_rate" value="<?= htmlspecialchars($emp['commission_rate'] ?? '1.0') ?>" class="mono-input" placeholder="1.0">
                </div>

                <div class="form-group full-width" style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
                    <div>
                        <label class="required">Current Residential Address</label>
                        <textarea name="current_address" rows="3" required class="mono-textarea" placeholder="Full address details"><?= htmlspecialchars($emp['current_address'] ?? '') ?></textarea>
                    </div>
                    <div>
                        <label class="required">Permanent Residential Address</label>
                        <textarea name="permanent_address" rows="3" required class="mono-textarea" placeholder="Full address details"><?= htmlspecialchars($emp['permanent_address'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Emergency Contact -->
            <div class="mono-form-section">Emergency Contact Details</div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="required">Emergency Contact Name</label>
                    <input type="text" name="emergency_contact_name" value="<?= htmlspecialchars($emp['emergency_contact_name'] ?? '') ?>" required class="mono-input">
                </div>

                <div class="form-group">
                    <label class="required">Relation</label>
                    <select name="emergency_relation" required class="mono-select">
                        <option value="Spouse" <?= ($emp['emergency_relation'] ?? '') === 'Spouse' ? 'selected' : '' ?>>Spouse</option>
                        <option value="Parent" <?= ($emp['emergency_relation'] ?? '') === 'Parent' ? 'selected' : '' ?>>Parent</option>
                        <option value="Sibling" <?= ($emp['emergency_relation'] ?? '') === 'Sibling' ? 'selected' : '' ?>>Sibling</option>
                        <option value="Child" <?= ($emp['emergency_relation'] ?? '') === 'Child' ? 'selected' : '' ?>>Child</option>
                        <option value="Other" <?= ($emp['emergency_relation'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="required">Emergency Phone Number</label>
                    <input type="text" name="emergency_phone" value="<?= htmlspecialchars($emp['emergency_phone'] ?? '') ?>" required class="mono-input">
                </div>
            </div>

            <!-- Section 2: Department, Role & Compensation -->
            <div class="mono-form-section">Section 2: Department, Role &amp; Payroll Setup</div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="required">Team / Department</label>
                    <select name="department" id="department-select" onchange="toggleTeamFields()" required class="mono-select">
                        <option value="Lead Generation Team" <?= ($emp['department'] ?? '') === 'Lead Generation Team' ? 'selected' : '' ?>>Lead Generation Team</option>
                        <option value="Digital Marketing Team" <?= ($emp['department'] ?? '') === 'Digital Marketing Team' ? 'selected' : '' ?>>Digital Marketing Team</option>
                        <option value="Content & Education Team" <?= ($emp['department'] ?? '') === 'Content & Education Team' ? 'selected' : '' ?>>Content & Education Team</option>
                        <option value="Customer Relationship Team" <?= ($emp['department'] ?? '') === 'Customer Relationship Team' ? 'selected' : '' ?>>Customer Relationship Team</option>
                        <option value="IT & Systems Team" <?= ($emp['department'] ?? '') === 'IT & Systems Team' ? 'selected' : '' ?>>IT & Systems Team</option>
                        <option value="Operations & HR" <?= ($emp['department'] ?? '') === 'Operations & HR' ? 'selected' : '' ?>>Operations & HR</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="required">Designation / Job Title</label>
                    <select name="designation" required class="mono-select">
                        <option value="Executive" <?= ($emp['designation'] ?? '') === 'Executive' ? 'selected' : '' ?>>Executive</option>
                        <option value="Senior Executive" <?= ($emp['designation'] ?? '') === 'Senior Executive' ? 'selected' : '' ?>>Senior Executive</option>
                        <option value="Team Lead" <?= ($emp['designation'] ?? '') === 'Team Lead' ? 'selected' : '' ?>>Team Lead</option>
                        <option value="Manager" <?= ($emp['designation'] ?? '') === 'Manager' ? 'selected' : '' ?>>Manager</option>
                        <option value="Director / HOD" <?= ($emp['designation'] ?? '') === 'Director / HOD' ? 'selected' : '' ?>>Director / HOD</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="required">Monthly Base Salary (₹)</label>
                    <input type="number" step="0.01" name="monthly_base_salary" value="<?= htmlspecialchars($emp['monthly_base_salary'] ?? '0') ?>" required class="mono-input" placeholder="e.g. 25000">
                </div>

                <div class="form-group">
                    <label class="required">Reporting Manager</label>
                    <input type="text" name="reporting_manager" value="<?= htmlspecialchars($emp['reporting_manager'] ?? '') ?>" required class="mono-input">
                </div>

                <div class="form-group">
                    <label class="required">Date of Joining (DOJ)</label>
                    <input type="date" name="doj" value="<?= htmlspecialchars($emp['doj'] ?? '') ?>" required class="mono-input">
                </div>

                <div class="form-group">
                    <label class="required">System Access Level / Role</label>
                    <select name="access_role" required class="mono-select">
                        <option value="Staff" <?= ($emp['access_role'] ?? '') === 'Staff' ? 'selected' : '' ?>>Staff / Field Officer</option>
                        <option value="RM" <?= ($emp['access_role'] ?? '') === 'RM' ? 'selected' : '' ?>>Relationship Manager (RM)</option>
                        <option value="Admin" <?= ($emp['access_role'] ?? '') === 'Admin' ? 'selected' : '' ?>>System Administrator (Admin)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="required">Work Mode</label>
                    <select name="work_mode" required class="mono-select">
                        <option value="Full-Time (Office)" <?= ($emp['work_mode'] ?? '') === 'Full-Time (Office)' ? 'selected' : '' ?>>Full-Time (Office)</option>
                        <option value="Part-Time (Office)" <?= ($emp['work_mode'] ?? '') === 'Part-Time (Office)' ? 'selected' : '' ?>>Part-Time (Office)</option>
                        <option value="Work From Home (Remote)" <?= ($emp['work_mode'] ?? '') === 'Work From Home (Remote)' ? 'selected' : '' ?>>Work From Home (Remote)</option>
                        <option value="Hybrid" <?= ($emp['work_mode'] ?? '') === 'Hybrid' ? 'selected' : '' ?>>Hybrid</option>
                    </select>
                </div>
            </div>

            <!-- Section 3: Dynamic Operational Fields -->
            <div class="mono-form-section">Section 3: Department Operational Settings</div>
            <div id="team-fields-container" style="background:var(--bw-light); padding:16px; border-radius:8px; border:1px solid var(--bw-border); margin-bottom:20px;">
                <p style="color:var(--bw-muted); font-size:13px; text-align:center; margin:0;">Select a Team / Department above to load operational settings.</p>
            </div>
            <input type="hidden" name="team_specific_data" id="team_specific_data" value="<?= htmlspecialchars($emp['team_specific_data'] ?? '') ?>">

            <!-- Section 4: KYE Banking Details -->
            <div class="mono-form-section">Section 4: KYE Tax &amp; Banking Information</div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="required">PAN Card Number</label>
                    <input type="text" name="pan_number" value="<?= htmlspecialchars($emp['pan_number'] ?? '') ?>" required class="mono-input" placeholder="10-digit PAN">
                </div>

                <div class="form-group">
                    <label class="required">Aadhaar Card Number</label>
                    <input type="text" name="aadhar_number" value="<?= htmlspecialchars($emp['aadhar_number'] ?? '') ?>" required class="mono-input" placeholder="12-digit Aadhaar">
                </div>

                <div class="form-group">
                    <label class="required">Bank Account Holder Name</label>
                    <input type="text" name="bank_holder_name" value="<?= htmlspecialchars($emp['bank_holder_name'] ?? '') ?>" required class="mono-input">
                </div>

                <div class="form-group">
                    <label class="required">Bank Name</label>
                    <input type="text" name="bank_name" value="<?= htmlspecialchars($emp['bank_name'] ?? '') ?>" required class="mono-input">
                </div>

                <div class="form-group">
                    <label class="required">Bank Account Number</label>
                    <input type="text" name="bank_account_no" value="<?= htmlspecialchars($emp['bank_account_no'] ?? '') ?>" required class="mono-input">
                </div>

                <div class="form-group">
                    <label class="required">IFSC Code</label>
                    <input type="text" name="bank_ifsc" value="<?= htmlspecialchars($emp['bank_ifsc'] ?? '') ?>" required class="mono-input">
                </div>
            </div>

            <!-- Upload Documents -->
            <div class="mono-form-section">Upload / Replace Official Documents</div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Profile Photo</label>
                    <input type="file" name="photo_path" accept="image/png, image/jpeg" class="mono-input">
                    <?php if (!empty($emp['photo_path'])): ?>
                        <small style="font-size:11px; color:var(--bw-muted);">Current: <?= basename($emp['photo_path']) ?></small>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label>Aadhaar Card Copy</label>
                    <input type="file" name="aadhar_path" accept=".pdf, image/*" class="mono-input">
                </div>

                <div class="form-group">
                    <label>PAN Card Copy</label>
                    <input type="file" name="pan_path" accept=".pdf, image/*" class="mono-input">
                </div>

                <div class="form-group">
                    <label>Educational Marksheets</label>
                    <input type="file" name="marksheet_path" accept=".pdf, .zip" class="mono-input">
                </div>

                <div class="form-group">
                    <label>Relieving / Offer Letter</label>
                    <input type="file" name="relieving_letter_path" accept=".pdf" class="mono-input">
                </div>

                <div class="form-group">
                    <label>Cancelled Cheque</label>
                    <input type="file" name="cheque_path" accept=".pdf, image/*" class="mono-input">
                </div>
            </div>

            <!-- Section 5: Credentials -->
            <div class="mono-form-section">Section 5: System Login Credentials</div>
            <div class="form-grid" style="background:var(--bw-light); padding:16px; border-radius:8px; border:1px solid var(--bw-border);">
                <div class="form-group">
                    <label>Login Username</label>
                    <input type="text" name="username" value="<?= htmlspecialchars($emp['username'] ?? '') ?>" readonly class="mono-input">
                </div>
                <div class="form-group">
                    <label>Change System Password</label>
                    <input type="password" name="password" placeholder="Leave empty to keep existing password" class="mono-input">
                </div>
            </div>

            <!-- Action Buttons -->
            <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:24px;">
                <a href="view_employee.php?id=<?= $emp['id'] ?>" class="btn-mono-outline">Cancel</a>
                <button type="submit" id="save-btn" class="btn-mono-primary">
                    <i data-lucide="check" style="width:14px;"></i> Update Employee Dossier
                </button>
            </div>
        </div>
    </form>
</div>

<script>
const teamTemplates = {
    "Lead Generation Team": `
        <div class="form-grid">
            <div class="form-group">
                <label>Assigned Outreach Channel</label>
                <select id="ts_channel" class="mono-select">
                    <option value="Cold Calling">Cold Calling</option>
                    <option value="Inbound">Inbound</option>
                    <option value="Field Sourcing">Field Sourcing</option>
                </select>
            </div>
            <div class="form-group">
                <label>Daily Call Target</label>
                <input type="number" id="ts_target" placeholder="e.g. 100" class="mono-input">
            </div>
        </div>
    `,
    "Digital Marketing Team": `
        <div class="form-grid">
            <div class="form-group">
                <label>Assigned Ad Platforms</label>
                <select id="ts_platform" class="mono-select">
                    <option value="Meta (Facebook/IG)">Meta (Facebook/IG)</option>
                    <option value="Google Ads">Google Ads</option>
                    <option value="Local SEO">Local SEO</option>
                </select>
            </div>
            <div class="form-group">
                <label>Monthly Ad Budget Limit (₹)</label>
                <input type="number" id="ts_budget" placeholder="e.g. 50000" class="mono-input">
            </div>
        </div>
    `
};

function toggleTeamFields() {
    const team = document.getElementById('department-select').value;
    const container = document.getElementById('team-fields-container');
    if (teamTemplates[team]) {
        container.innerHTML = teamTemplates[team];
    } else {
        container.innerHTML = '<p style="color:var(--bw-muted); font-size:13px; text-align:center; margin:0;">Standard operational profile active for this team.</p>';
    }
}

function collectTeamData() {
    const container = document.getElementById('team-fields-container');
    const inputs = container.querySelectorAll('input, select');
    let data = {};
    inputs.forEach(el => {
        if (el.id && el.id.startsWith('ts_')) {
            let key = el.id.substring(3);
            data[key] = el.value;
        }
    });
    document.getElementById('team_specific_data').value = JSON.stringify(data);
}

async function saveEmployee(event) {
    event.preventDefault();
    const form = document.getElementById('edit-employee-form');
    const btn = document.getElementById('save-btn');

    collectTeamData();
    const formData = new FormData(form);

    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader-2" class="spin" style="width:14px;"></i> Updating...';

    try {
        const response = await fetch('api.php?api=update_employee', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (response.ok && data.success) {
            showNotification(data.message, 'success');
            setTimeout(() => {
                location.href = 'view_employee.php?id=<?= $emp['id'] ?>';
            }, 800);
        } else {
            showNotification(data.error || 'Failed to update profile.', 'error');
        }
    } catch (err) {
        showNotification('Network communication error.', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="check" style="width:14px;"></i> Update Employee Dossier';
        lucide.createIcons();
    }
}

window.addEventListener('DOMContentLoaded', () => {
    toggleTeamFields();
});
</script>

<?php require_once 'footer.php'; ?>
