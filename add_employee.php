<?php
require_once 'config.php';

require_role(['Admin'], false);


$page_title = 'Register New Employee / Staff';
$page_subtitle = ' Complete HRMS onboarding process and assign system access';
$hide_main_header = true;
require_once 'header.php';

// Generate Employee ID (BF-EMP-YEAR-XXX)
$year = date('Y');
$prefix = "BF-EMP-$year-";
$last_emp = $db->query("SELECT emp_id FROM employees WHERE emp_id LIKE '$prefix%' ORDER BY id DESC LIMIT 1")->fetchColumn();
$next_num = 1;
if ($last_emp) {
    $parts = explode('-', $last_emp);
    $last_val = end($parts);
    $next_num = intval($last_val) + 1;
}
$new_employee_id = $prefix . str_pad($next_num, 3, '0', STR_PAD_LEFT);
?>

<div id="view-add-employee" class="view-container">
    <form id="employee-registration-form" onsubmit="saveEmployee(event)" enctype="multipart/form-data">
        <div class="card">
            <div class="card-title-bar" style="display:flex; justify-content:space-between; align-items:center;">
                <div style="display:flex; align-items:center; gap:15px;">
                    <a href="employees_list.php" class="btn btn-secondary" style="padding: 6px 12px; background: transparent; border: 1px solid var(--border-color); color: var(--text-dark);"><i data-lucide="arrow-left" style="width:16px;height:16px;margin-right:4px;"></i> Back</a>
                    <h2 style="margin:0;">Staff Onboarding & HRMS Form</h2>
                </div>
                <div class="badge-locked"><i data-lucide="shield"></i> Secure Registration</div>
            </div>
            
            <!-- Section 1: Basic & Contact Details -->
            <div class="form-section-title"> Section 1: Basic & Contact Details</div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="required">Employee ID</label>
                    <input type="text" name="emp_id" value="<?php echo htmlspecialchars($new_employee_id); ?>" readonly style="background-color: #f1f5f9;">
                </div>
                <div class="form-group">
                    <label class="required">Full Name (As per Documents)</label>
                    <input type="text" name="full_name" placeholder="First Middle Last" required>
                </div>
                <div class="form-group">
                    <label class="required">Official Email ID</label>
                    <input type="email" name="official_email" placeholder="name@bhardwajfinance.com" required>
                </div>
                <div class="form-group">
                    <label>Personal Email ID</label>
                    <input type="email" name="personal_email" placeholder="personal@gmail.com">
                </div>
                <div class="form-group">
                    <label class="required">Mobile Number</label>
                    <input type="text" name="mobile" placeholder="10-digit number" required>
                </div>
                <div class="form-group full-width" style="display:grid; grid-template-columns:1fr 1fr; gap:18px;">
                    <div>
                        <label class="required">Current Address</label>
                        <textarea name="current_address" rows="3" placeholder="House No, Street, Area, City, Pincode" required style="width:100%"></textarea>
                    </div>
                    <div>
                        <label class="required">Permanent Address</label>
                        <textarea name="permanent_address" rows="3" placeholder="House No, Street, Area, City, Pincode" required style="width:100%"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Commission Rate (%)</label>
                        <input type="number" step="0.01" name="commission_rate" placeholder="e.g. 1.0" value="1.0">
                    </div>
                </div>
            </div>

            <!-- Emergency Contact -->
            <div class="form-section-title"> Emergency Contact Details</div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="required">Contact Person Name</label>
                    <input type="text" name="emergency_contact_name" placeholder="Emergency Contact Name" required>
                </div>
                <div class="form-group">
                    <label class="required">Relation</label>
                    <select name="emergency_relation" required>
                        <option value="" disabled selected>Select Relation</option>
                        <option value="Spouse">Spouse</option>
                        <option value="Parent">Parent</option>
                        <option value="Sibling">Sibling</option>
                        <option value="Child">Child</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="required">Emergency Phone Number</label>
                    <input type="text" name="emergency_phone" placeholder="Emergency Mobile Number" required>
                </div>
            </div>

            <!-- Section 2: Department & Access Role -->
            <div class="form-section-title"> Section 2: Department & Access Role</div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="required">Team / Department</label>
                    <select name="department" id="department-select" onchange="toggleTeamFields()" required>
                        <option value="" disabled selected>Select Team</option>
                        <option value="Lead Generation Team">Lead Generation Team</option>
                        <option value="Digital Marketing Team">Digital Marketing Team</option>
                        <option value="Content & Education Team">Content & Education Team</option>
                        <option value="Customer Relationship Team">Customer Relationship Team</option>
                        <option value="IT & Systems Team">IT & Systems Team</option>
                        <option value="Operations & HR">Operations & HR</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="required">Designation / Job Title</label>
                    <select name="designation" required>
                        <option value="" disabled selected>Select Designation</option>
                        <option value="Executive">Executive</option>
                        <option value="Senior Executive">Senior Executive</option>
                        <option value="Team Lead">Team Lead</option>
                        <option value="Manager">Manager</option>
                        <option value="Director / HOD">Director / HOD</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="required">Reporting Manager</label>
                    <input type="text" name="reporting_manager" placeholder="Manager Name" required>
                </div>
                <div class="form-group">
                    <label class="required">Date of Joining (DOJ)</label>
                    <input type="date" name="doj" required>
                </div>
                <div class="form-group">
                    <label class="required">System Access Level / Role</label>
                    <select name="access_role" required>
                        <option value="Staff" selected>Executive / User (Limited Access)</option>
                        <option value="Admin">Admin (Full Control)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="required">Work Mode & Shift</label>
                    <select name="work_mode" required>
                        <option value="" disabled selected>Select Mode</option>
                        <option value="Full-Time (Office)">Full-Time (Office)</option>
                        <option value="Part-Time (Office)">Part-Time (Office)</option>
                        <option value="Work From Home (Remote)">Work From Home (Remote)</option>
                        <option value="Hybrid">Hybrid</option>
                    </select>
                </div>
            </div>

            <!-- Section 3: Team-Specific Operational Fields -->
            <div class="form-section-title">️ Section 3: Team-Specific Operational Settings</div>
            
            <div id="team-fields-container" style="background:#f8fafc; padding:15px; border-radius:8px; border:1px solid #e2e8f0; margin-bottom:20px;">
                <p style="color:var(--text-muted); font-size:13px; text-align:center;">Select a Team / Department above to reveal specific operational fields.</p>
            </div>
            
            <input type="hidden" name="team_specific_data" id="team_specific_data">

            <!-- Section 4: KYC, Payout & Documents Verification -->
            <div class="form-section-title"> Section 4: KYC, Payout & Document Verification</div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="required">PAN Card Number</label>
                    <input type="text" name="pan_number" placeholder="10-digit PAN" required>
                </div>
                <div class="form-group">
                    <label class="required">Aadhaar Card Number</label>
                    <input type="text" name="aadhar_number" placeholder="12-digit Aadhaar" required>
                </div>
                
                <div class="form-group full-width">
                    <label style="color:var(--primary); font-weight:700;"> Salary Bank Account Details</label>
                </div>
                <div class="form-group">
                    <label class="required">Account Holder Name</label>
                    <input type="text" name="bank_holder_name" placeholder="As per bank records" required>
                </div>
                <div class="form-group">
                    <label class="required">Account Number</label>
                    <input type="text" name="bank_account_no" placeholder="e.g. 098765432123" required>
                </div>
                <div class="form-group">
                    <label class="required">Bank Name</label>
                    <input type="text" name="bank_name" placeholder="e.g. HDFC Bank" required>
                </div>
                <div class="form-group">
                    <label class="required">IFSC Code</label>
                    <input type="text" name="bank_ifsc" placeholder="e.g. HDFC0001234" required>
                </div>
            </div>
            
            <div class="form-grid" style="margin-top:20px;">
                <div class="form-group">
                    <label>Profile Photo (Image)</label>
                    <input type="file" name="photo_path" accept="image/png, image/jpeg">
                </div>
                <div class="form-group">
                    <label>Aadhaar Card Copy (PDF/Image)</label>
                    <input type="file" name="aadhar_path" accept=".pdf, image/*">
                </div>
                <div class="form-group">
                    <label>PAN Card Copy (PDF/Image)</label>
                    <input type="file" name="pan_path" accept=".pdf, image/*">
                </div>
                <div class="form-group">
                    <label>Educational Marksheets (PDF/Zip)</label>
                    <input type="file" name="marksheet_path" accept=".pdf, .zip">
                </div>
                <div class="form-group">
                    <label>Relieving / Offer Letter (PDF)</label>
                    <input type="file" name="relieving_letter_path" accept=".pdf">
                </div>
                <div class="form-group">
                    <label>Cancelled Cheque (Image/PDF)</label>
                    <input type="file" name="cheque_path" accept=".pdf, image/*">
                </div>
            </div>

            <!-- Section 5: System Login Credentials -->
            <div class="form-section-title" style="margin-top: 30px;"> Section 5: System Login Credentials (Auto-Generated)</div>
            <div class="form-grid" style="background:#f0f9ff; padding:20px; border-radius:8px; border:1px solid #bae6fd;">
                <div class="form-group">
                    <label style="color:#0369a1; font-weight:600;">Login Username (Email ID)</label>
                    <input type="text" name="username" id="preview_username" readonly style="background-color:#e0f2fe; border-color:#7dd3fc; color:#0c4a6e; font-weight:bold;">
                    <small style="color:#0284c7;">This will be their Official Email ID.</small>
                </div>
                <div class="form-group">
                    <label style="color:#0369a1; font-weight:600;">System Password</label>
                    <input type="text" name="password" id="preview_password" readonly style="background-color:#e0f2fe; border-color:#7dd3fc; color:#0c4a6e; font-weight:bold;">
                    <small style="color:#0284c7;">First 4 letters of Name + Last 4 digits of Aadhaar</small>
                </div>
            </div>

            <!-- Action panel -->
            <div class="form-actions" style="margin-top: 30px;">
                <button type="reset" class="btn btn-secondary">Clear Inputs</button>
                <button type="submit" class="btn btn-primary"><i data-lucide="save"></i> Complete Onboarding</button>
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
                    <select id="ts_channel">
                        <option value="Cold Calling">Cold Calling</option>
                        <option value="Inbound">Inbound</option>
                        <option value="Field Sourcing">Field Sourcing</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Daily Call Target</label>
                    <input type="number" id="ts_target" placeholder="e.g. 100">
                </div>
                <div class="form-group">
                    <label>Virtual Calling Line / Extension ID</label>
                    <input type="text" id="ts_ext" placeholder="e.g. EXT-204">
                </div>
            </div>
        `,
        "Digital Marketing Team": `
            <div class="form-grid">
                <div class="form-group">
                    <label>Assigned Ad Platforms</label>
                    <select id="ts_platform">
                        <option value="Meta (Facebook/IG)">Meta (Facebook/IG)</option>
                        <option value="Google Ads">Google Ads</option>
                        <option value="Local SEO">Local SEO</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Ad Account / Pixel ID</label>
                    <input type="text" id="ts_pixel" placeholder="e.g. FB-198273645">
                </div>
                <div class="form-group">
                    <label>Monthly Ad Budget Limit (₹)</label>
                    <input type="number" id="ts_budget" placeholder="e.g. 50000">
                </div>
            </div>
        `,
        "Content & Education Team": `
            <div class="form-grid">
                <div class="form-group">
                    <label>Content Expertise</label>
                    <select id="ts_expertise">
                        <option value="Video Editing">Video Editing</option>
                        <option value="Script Writing">Script Writing</option>
                        <option value="Graphic Design">Graphic Design</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Publishing Platform Access</label>
                    <select id="ts_publish">
                        <option value="YouTube">YouTube</option>
                        <option value="Social Media">Social Media</option>
                        <option value="Web CMS">Web CMS</option>
                    </select>
                </div>
            </div>
        `,
        "Customer Relationship Team": `
            <div class="form-grid">
                <div class="form-group">
                    <label>Customer Bucket Allocated</label>
                    <select id="ts_bucket">
                        <option value="Active Borrowers">Active Borrowers</option>
                        <option value="Overdue Accounts">Overdue Accounts</option>
                        <option value="General Support">General Support</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Support Channel</label>
                    <select id="ts_support_channel">
                        <option value="Telecalling">Telecalling</option>
                        <option value="WhatsApp Business">WhatsApp Business</option>
                        <option value="Desk Support">Desk Support</option>
                    </select>
                </div>
            </div>
        `,
        "IT & Systems Team": `
            <div class="form-grid">
                <div class="form-group">
                    <label>Tech Stack / Specialization</label>
                    <select id="ts_stack">
                        <option value="Full-Stack">Full-Stack</option>
                        <option value="Database">Database</option>
                        <option value="Server Admin">Server Admin</option>
                        <option value="IoT">IoT</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>System Privilege Level</label>
                    <select id="ts_privilege">
                        <option value="Database Manager">Database Manager</option>
                        <option value="Server Admin">Server Admin</option>
                        <option value="Developer">Developer</option>
                    </select>
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
            container.innerHTML = '<p style="color:var(--text-muted); font-size:13px; text-align:center;">No specific operational fields required for this team.</p>';
        }
    }

    function collectTeamData() {
        const container = document.getElementById('team-fields-container');
        const inputs = container.querySelectorAll('input, select');
        let data = {};
        inputs.forEach(el => {
            if (el.id && el.id.startsWith('ts_')) {
                // Remove ts_ prefix for key
                let key = el.id.substring(3);
                data[key] = el.value;
            }
        });
        document.getElementById('team_specific_data').value = JSON.stringify(data);
    }

    // Auto-generate Username and Password logic
    const nameInput = document.querySelector('input[name="full_name"]');
    const emailInput = document.querySelector('input[name="official_email"]');
    const aadhaarInput = document.querySelector('input[name="aadhar_number"]');
    const previewUsername = document.getElementById('preview_username');
    const previewPassword = document.getElementById('preview_password');

    function updateCredentialsPreview() {
        const email = emailInput.value.trim();
        const name = nameInput.value.trim().replace(/[^a-zA-Z]/g, '');
        const aadhaar = aadhaarInput.value.trim().replace(/[^0-9]/g, '');

        previewUsername.value = email || 'Will be auto-filled';
        
        let passPrefix = name.length >= 4 ? name.substring(0, 4) : name;
        let passSuffix = aadhaar.length >= 4 ? aadhaar.substring(aadhaar.length - 4) : aadhaar;
        
        if(passPrefix || passSuffix) {
            previewPassword.value = passPrefix.toUpperCase() + passSuffix;
        } else {
            previewPassword.value = '';
        }
    }

    nameInput.addEventListener('keyup', updateCredentialsPreview);
    emailInput.addEventListener('keyup', updateCredentialsPreview);
    aadhaarInput.addEventListener('keyup', updateCredentialsPreview);

    async function saveEmployee(event) {
        event.preventDefault();
        const form = document.getElementById('employee-registration-form');
        
        // Before submitting, collect dynamic fields into the hidden JSON input
        collectTeamData();
        
        const formData = new FormData(form);
        
        try {
            const response = await fetch('api.php?api=add_employee', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (response.ok && data.success) {
                showNotification(data.message, 'success');
                setTimeout(() => {
                    location.href = 'employees_list.php';
                }, 1000);
            } else {
                showNotification(data.error || 'Registration failed.', 'error');
            }
        } catch (err) {
            showNotification('Connection failure in employee registration.', 'error');
        }
    }
</script>

<?php require_once 'footer.php'; ?>
