<?php
require_once 'config.php';
if (($_SESSION['role'] ?? '') === 'Staff') {
    $queryString = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
    header("Location: staff/add_applicant.php" . $queryString);
    exit;
}
require_role(['Admin', 'RM']);

$edit_id = $_GET['id'] ?? '';
$init_customer_name = $_GET['customer_name'] ?? $_GET['name'] ?? '';
$init_mobile = $_GET['mobile'] ?? '';
$init_visit_id = $_GET['visit_id'] ?? $_GET['converted_lead_id'] ?? '';
$page_title = $edit_id ? 'Complete Applicant Profile' : 'Add Loan Applicant';
$page_subtitle = ' Phase 1: KYC & Origination';
$staff_members = $db->query("SELECT username, name FROM users WHERE role = 'Staff' AND is_active = 1 ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);
$hide_main_header = true;
require_once 'header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
<script>
    window.toggleKbdMenu = function(e) {
        if (e) {
            if (e.stopPropagation) e.stopPropagation();
            if (e.preventDefault) e.preventDefault();
        }
        var menu = document.getElementById('kbd-shortcuts-menu');
        if (menu) {
            var current = getComputedStyle(menu).display;
            var isHidden = (current === 'none');
            menu.style.display = isHidden ? 'block' : 'none';
            if (isHidden && typeof lucide !== 'undefined') {
                try { lucide.createIcons(); } catch(err){}
            }
        }
    };

    window.toggleCoApplicantSection = function() {
        try {
            var chk = document.getElementById('has_co_applicant');
            var wrapper = document.getElementById('co_applicant_wrapper');
            var container = document.getElementById('co_applicants_container');
            if (!chk || !wrapper) return;
            
            var isChecked = !!chk.checked;
            wrapper.style.display = isChecked ? 'block' : 'none';
            
            if (isChecked && container) {
                if (container.children.length === 0 && typeof window.addCoApplicantBlock === 'function') {
                    try { window.addCoApplicantBlock(); } catch(err) { console.error('Error adding co-applicant block:', err); }
                }
            }
            if (typeof updateSmartDocPreview === 'function') {
                try { updateSmartDocPreview(); } catch(e){}
            }
        } catch(err) {
            console.error('Error toggling co-applicant section', err);
        }
    };
</script>
<style>
.ts-control { padding: 10px 14px !important; border-radius: 6px !important; border-color: #cbd5e1 !important; font-size: 14px !important; }
.ts-dropdown { font-size: 14px !important; border-radius: 6px !important; }

#applicant-registration-form input:focus, 
#applicant-registration-form select:focus, 
#applicant-registration-form textarea:focus {
    outline: none !important;
    border-color: #0f172a !important;
    box-shadow: 0 0 0 3px rgba(15, 23, 42, 0.14), 0 1px 2px rgba(0, 0, 0, 0.05) !important;
    background-color: #ffffff !important;
}

.kbd-shortcut-pill-dark {
    display: inline-block;
    background: #27272a;
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 5px;
    padding: 2px 7px;
    font-size: 11px;
    font-weight: 700;
    color: #f4f4f5;
    box-shadow: 0 1px 2px rgba(0,0,0,0.3);
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}

@keyframes laserScan {
    0% { top: 0%; opacity: 0.8; }
    50% { top: 85%; opacity: 1; }
    100% { top: 0%; opacity: 0.8; }
}
.scan-laser-line {
    position: absolute;
    left: 0;
    width: 100%;
    height: 3px;
    background: linear-gradient(90deg, transparent, #0f172a, #2563eb, #0f172a, transparent);
    box-shadow: 0 0 10px #2563eb, 0 0 4px #0f172a;
    animation: laserScan 1.6s infinite ease-in-out;
}
.scan-progress-bar {
    height: 6px;
    background: #0f172a;
    border-radius: 3px;
    transition: width 0.4s ease;
}
</style>

<div id="view-add-applicant" class="view-container" style="padding-top: 0; margin-top: 0;">
    <form id="applicant-registration-form" onsubmit="saveApplicant(event)">
        <input type="hidden" name="converted_lead_id" id="converted_lead_id" value="<?php echo htmlspecialchars($init_visit_id); ?>">
        <input type="hidden" name="id" id="applicant_id" value="<?php echo htmlspecialchars($edit_id); ?>">
        <div class="card" style="margin-top: 0; padding: 0; background: transparent; border: none; box-shadow: none;">
            
            <!-- EXECUTIVE CLEAN HERO HEADER BANNER -->
            <div style="background: linear-gradient(135deg, #09090b 0%, #18181b 100%); border-radius: 12px; padding: 20px 24px; color: #ffffff; margin-bottom: 20px; border: 1px solid rgba(255,255,255,0.12); box-shadow: 0 10px 30px -10px rgba(15,23,42,0.3); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; position: relative;">
                
                <!-- Left Column: Title & Subtitle -->
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 40px; height: 40px; background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15); border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <i data-lucide="user-plus" style="width: 20px; height: 20px; color: #ffffff;"></i>
                    </div>
                    <div>
                        <h2 style="margin: 0; font-size: 18px; font-weight: 800; color: #ffffff; letter-spacing: -0.02em;">
                            <?php echo $edit_id ? 'Complete Missing Applicant Details' : 'Add Loan Applicant Profile'; ?>
                        </h2>
                        <div style="font-size: 12.5px; color: #a1a1aa; margin-top: 2px;">
                            Phase 1: KYC, Employment & Loan Origination
                        </div>
                    </div>
                </div>

                <!-- Right Column: Professional Action Controls -->
                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <!-- AI OCR Scanner Button -->
                    <input type="file" id="ai-scan-file-input" accept="image/*,.pdf" style="display: none;" onchange="handleAiDocScan(this.files[0])">
                    <button type="button" onclick="openExecutiveScanModal()" style="background: #ffffff; color: #09090b; border: none; padding: 9px 16px; border-radius: 8px; font-weight: 700; font-size: 12.5px; cursor: pointer; display: flex; align-items: center; gap: 7px; transition: all 0.2s ease; box-shadow: 0 4px 14px rgba(255,255,255,0.15);" onmouseover="this.style.background='#f4f4f5'" onmouseout="this.style.background='#ffffff'">
                        <i data-lucide="scan" style="width: 15px; height: 15px; color: #09090b;"></i>
                        <span>Scan Document & Auto-Fill</span>
                    </button>

                    <!-- Keyboard Shortcuts Dropdown Button -->
                    <div class="kbd-dropdown-container" style="position: relative; display: inline-block; z-index: 100;">
                        <button type="button" onclick="toggleKbdMenu(event)" style="display: flex; align-items: center; gap: 6px; background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.18); border-radius: 8px; padding: 9px 14px; font-size: 12.5px; font-weight: 600; color: #ffffff; cursor: pointer; transition: all 0.2s ease;" onmouseover="this.style.background='rgba(255,255,255,0.14)'" onmouseout="this.style.background='rgba(255,255,255,0.08)'">
                            <i data-lucide="keyboard" style="width: 15px; height: 15px; color: #cbd5e1;"></i>
                            <span>Keyboard Shortcuts</span>
                            <i data-lucide="chevron-down" style="width: 14px; height: 14px; color: #94a3b8; margin-left: 2px;"></i>
                        </button>
                        <div id="kbd-shortcuts-menu" style="display: none; position: absolute; right: 0; top: 100%; margin-top: 8px; width: 260px; background: #18181b; border: 1px solid rgba(255,255,255,0.18); border-radius: 10px; box-shadow: 0 20px 35px -10px rgba(0,0,0,0.6); padding: 14px; z-index: 1000;">
                            <div style="font-weight: 700; font-size: 12px; color: #ffffff; margin-bottom: 10px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                                <i data-lucide="command" style="width: 14px; height: 14px; color: #38bdf8;"></i> Keyboard Navigation Guide
                            </div>
                            <div style="display: flex; flex-direction: column; gap: 9px; font-size: 12px;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span style="color: #a1a1aa; font-weight: 500;">Next Field</span>
                                    <kbd class="kbd-shortcut-pill-dark">Enter</kbd>
                                </div>
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span style="color: #a1a1aa; font-weight: 500;">Previous Field</span>
                                    <kbd class="kbd-shortcut-pill-dark">Shift + Enter</kbd>
                                </div>
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span style="color: #a1a1aa; font-weight: 500;">Quick Save File</span>
                                    <kbd class="kbd-shortcut-pill-dark">Ctrl + S</kbd>
                                </div>
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span style="color: #a1a1aa; font-weight: 500;">Focus Top Field</span>
                                    <kbd class="kbd-shortcut-pill-dark">Esc</kbd>
                                </div>
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span style="color: #a1a1aa; font-weight: 500;">Jump Section</span>
                                    <kbd class="kbd-shortcut-pill-dark">Alt + 1..6</kbd>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- MAIN FORM BODY CARD CONTAINER -->
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 28px; box-shadow: 0 4px 20px -5px rgba(0,0,0,0.05);">

            <!-- 1. Basic Info -->
            <div class="form-section-title"> 1. Basic Information</div>
            <div class="form-grid">
                <div class="form-group full-width">
                    <label class="required">Customer Name</label>
                    <input type="text" name="customer_name" placeholder="Full name of applicant" value="<?php echo htmlspecialchars($init_customer_name); ?>" required>
                </div>
                <div class="form-group">
                    <label class="required">Mobile Number</label>
                    <input type="text" name="mobile" placeholder="10-digit mobile number" value="<?php echo htmlspecialchars($init_mobile); ?>" required pattern="\d{10}">
                </div>
                <div class="form-group">
                    <label>Email ID</label>
                    <input type="email" name="email" placeholder="applicant@example.com">
                </div>
            </div>

            <!-- 2. Address Details -->
            <div class="form-section-title"> 2. Address & Location</div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="required">Pincode</label>
                    <div style="position: relative;">
                        <input type="text" name="pincode" id="pincode" placeholder="6-digit pincode" pattern="\d{6}" maxlength="6" oninput="verifyPincode(this.value)">
                        <span id="pincode-status" style="position: absolute; right: 10px; top: 10px;"></span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="required">State</label>
                    <select name="state" id="state" onchange="loadCities(this.value)">
                        <option value="">Select State</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="required">City / District</label>
                    <select name="city" id="city">
                        <option value="">Select City</option>
                    </select>
                </div>
                <div class="form-group full-width">
                    <label class="required">Complete Address</label>
                    <textarea name="address" rows="2" placeholder="House/Flat No., Street, Area"></textarea>
                </div>
            </div>

            <!-- 3. Financial & KYC -->
            <div class="form-section-title"> 3. Financial & KYC Details</div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="required">PAN Number</label>
                    <input type="text" name="pan_number" placeholder="ABCDE1234F" required maxlength="10">
                </div>
                <div class="form-group">
                    <label class="required">Aadhaar Card Number</label>
                    <input type="text" name="aadhar_number" placeholder="12-digit Aadhaar" required pattern="\d{12}">
                </div>
                <div class="form-group">
                    <label class="required">Employment Type</label>
                    <select name="employment_type" id="employment_type" required onchange="handleEmploymentTypeChange(this.value)">
                        <option value="" selected>Select Employment</option>
                        <option value="Salaried">Salaried</option>
                        <option value="Government Employee">Government Employee</option>
                        <option value="Self-Employed">Self-Employed / Business Owner</option>
                        <option value="Professional">Professional</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="required">Monthly Income (₹)</label>
                    <input type="number" name="monthly_income" placeholder="e.g. 50000" required>
                </div>
            </div>

            <!-- Dynamic Employer / Organization Details -->
            <div id="org_details_wrapper" style="display:none; margin-bottom:24px; background:#f8fafc; border:1px solid #cbd5e1; border-radius:8px; padding:16px;">
                <div class="form-grid">
                    <div class="form-group">
                        <label id="org_name_label" class="required">Company / Department Name</label>
                        <input type="text" name="organization_name" id="organization_name" placeholder="Enter organization name">
                    </div>
                    <div class="form-group">
                        <label id="org_address_label">Office / Business Address</label>
                        <input type="text" name="organization_address" id="organization_address" placeholder="Enter office address">
                    </div>
                </div>
            </div>

            <!-- 4. Co-Applicant Details -->
            <div class="form-section-title" style="display:flex; justify-content:space-between; align-items:center;"> 
                <span>4. Co-Applicant Details (Optional)</span>
                <label id="lbl_has_co_applicant" style="font-size:14px; display:flex; align-items:center; gap:8px; cursor:pointer; font-weight:600; color:#0f172a; user-select:none;" onclick="setTimeout(function(){ if(typeof window.toggleCoApplicantSection === 'function') window.toggleCoApplicantSection(); }, 10)">
                    <input type="checkbox" id="has_co_applicant" onchange="window.toggleCoApplicantSection()" onclick="window.toggleCoApplicantSection()" style="width:18px; height:18px; cursor:pointer; accent-color:#0f172a;">
                    Include Co-Applicant?
                </label>
            </div>
            <div id="co_applicant_wrapper" style="display:none; background:#f8fafc; border:1px solid #cbd5e1; border-radius:8px; padding:20px; margin-bottom:32px;">
                <div id="co_applicants_container">
                    <!-- Dynamic blocks go here -->
                </div>
                <button type="button" class="btn btn-secondary" onclick="addCoApplicantBlock()" style="margin-top:16px; display:flex; align-items:center; gap:8px; background:white;">
                    <i data-lucide="plus" style="width:16px; height:16px;"></i> Add Another Co-Applicant
                </button>
            </div>

            <!-- 5. Loan Application -->
            
            <div class="form-section-title" style="display:flex; justify-content:space-between; align-items:center;">
                <span>5. Personal Discussion (PD) & Field Assessment (Optional)</span>
                <label style="font-size:14px; display:flex; align-items:center; gap:8px; cursor:pointer; font-weight:normal;">
                    <input type="checkbox" id="add_pd_toggle" onchange="document.getElementById('pd_wrapper').style.display = this.checked ? 'block' : 'none'" style="width:16px; height:16px;">
                    Add PD Assessment
                </label>
            </div>
            <div id="pd_wrapper" style="display:none; padding:16px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; margin-bottom:24px;">
                <div class="form-grid">
                <div class="form-group">
                    <label>PD Conducted By</label>
                    <input type="text" name="pd_conducted_by" id="pd_conducted_by" placeholder="Credit Officer Name">
                </div>
                <div class="form-group">
                    <label>PD Date & Time</label>
                    <input type="datetime-local" name="pd_date" id="pd_date">
                </div>
                <div class="form-group">
                    <label>PD Mode</label>
                    <div style="display:flex; gap:15px; align-items:center; height:42px;">
                        <label><input type="radio" name="pd_mode" value="Physical Visit"> Physical</label>
                        <label><input type="radio" name="pd_mode" value="Telephonic"> Telephonic</label>
                        <label><input type="radio" name="pd_mode" value="Video Verification"> Video</label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Business Board / Signage Seen</label>
                    <div style="display:flex; gap:15px; align-items:center; height:42px;">
                        <label><input type="radio" name="business_board_seen" value="Yes"> Yes</label>
                        <label><input type="radio" name="business_board_seen" value="No"> No</label>
                    </div>
                </div>
                <div class="form-group">
                    <label>Stock / Inventory Status</label>
                    <select name="stock_status" id="stock_status">
                        <option value="">-- Select --</option>
                        <option value="Nil">Nil</option>
                        <option value="Moderate">Moderate</option>
                        <option value="High">High</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Business Continuity / Stability</label>
                    <select name="business_stability" id="business_stability">
                        <option value="">-- Select --</option>
                        <option value="Less than 1 year">Less than 1 year</option>
                        <option value="1-3 years">1-3 years</option>
                        <option value="3+ years">3+ years</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Approx. Monthly Turnover (?)</label>
                    <input type="number" name="monthly_turnover" id="monthly_turnover" placeholder="e.g. 500000">
                </div>

                <div class="form-group">
                    <label>Residence Type</label>
                    <select name="residence_type" id="residence_type">
                        <option value="">-- Select --</option>
                        <option value="Owned">Owned</option>
                        <option value="Rented">Rented</option>
                        <option value="Ancestral">Ancestral</option>
                        <option value="Company Provided">Company Provided</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Years at Current Address</label>
                    <input type="number" step="0.1" name="years_at_address" id="years_at_address" placeholder="e.g. 5.5">
                </div>
                <div class="form-group">
                    <label>Locality Classification</label>
                    <select name="locality_classification" id="locality_classification">
                        <option value="">-- Select --</option>
                        <option value="Slum">Slum</option>
                        <option value="Lower-Middle">Lower-Middle</option>
                        <option value="Middle">Middle</option>
                        <option value="Upper-Middle">Upper-Middle</option>
                        <option value="Premium">Premium</option>
                    </select>
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label>Neighbor Verification Feedback</label>
                    <textarea name="neighbor_feedback" id="neighbor_feedback" rows="2" placeholder="Summary of local feedback about applicant's reputation..."></textarea>
                </div>

                <div class="form-group" style="grid-column: 1 / -1;">
                    <label>Consumer Durables Observed</label>
                    <div style="display:flex; flex-wrap:wrap; gap:15px; margin-top:5px;">
                        <label><input type="checkbox" name="consumer_durables[]" value="AC"> AC</label>
                        <label><input type="checkbox" name="consumer_durables[]" value="Refrigerator"> Refrigerator</label>
                        <label><input type="checkbox" name="consumer_durables[]" value="Car"> Car</label>
                        <label><input type="checkbox" name="consumer_durables[]" value="Two-Wheeler"> Two-Wheeler</label>
                        <label><input type="checkbox" name="consumer_durables[]" value="Washing Machine"> Washing Machine</label>
                        <label><input type="checkbox" name="consumer_durables[]" value="Smart TV"> Smart TV</label>
                    </div>
                </div>

                <div class="form-group">
                    <label>Overall Lifestyle Score</label>
                    <select name="lifestyle_score" id="lifestyle_score">
                        <option value="">-- Select --</option>
                        <option value="Low">Low</option>
                        <option value="Average">Average</option>
                        <option value="Affluent">Affluent</option>
                    </select>
                </div>

                <div class="form-group" style="grid-column: 1 / -1;">
                    <label>Positive Triggers</label>
                    <div style="display:flex; flex-wrap:wrap; gap:15px; margin-top:5px;">
                        <label><input type="checkbox" name="positive_triggers[]" value="Clean track record"> Clean track record</label>
                        <label><input type="checkbox" name="positive_triggers[]" value="Stable business setup"> Stable business setup</label>
                        <label><input type="checkbox" name="positive_triggers[]" value="Good residential stability"> Good residential stability</label>
                    </div>
                </div>

                <div class="form-group" style="grid-column: 1 / -1;">
                    <label>Negative / Risk Triggers</label>
                    <div style="display:flex; flex-wrap:wrap; gap:15px; margin-top:5px;">
                        <label><input type="checkbox" name="negative_triggers[]" value="Aggressive behavior"> Aggressive behavior</label>
                        <label><input type="checkbox" name="negative_triggers[]" value="Inconsistent income proofs"> Inconsistent income proofs</label>
                        <label><input type="checkbox" name="negative_triggers[]" value="High existing liabilities"> High existing liabilities</label>
                        <label><input type="checkbox" name="negative_triggers[]" value="Suspicious documents"> Suspicious documents</label>
                    </div>
                </div>

                <div class="form-group">
                    <label>Final PD Status / Recommendation</label>
                    <select name="final_pd_status" id="final_pd_status">
                        <option value="">-- Select --</option>
                        <option value="Positive">Positive</option>
                        <option value="Negative">Negative</option>
                        <option value="Refer to Risk Manager">Refer to Risk Manager</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Recommended Loan Amount (?)</label>
                    <input type="number" name="recommended_loan_amount" id="recommended_loan_amount" placeholder="If differs from requested">
                </div>

                <div class="form-group">
                    <label>Upload PD Geotagged Photos / Report</label>
                    <input type="file" name="pd_report_file" id="pd_report_file" accept=".pdf, .jpg, .jpeg, .png">
                    <div id="pd_report_file_link" style="margin-top:5px; font-size:12px;"></div>
                </div>
            </div>
            </div>

            <div class="form-section-title"> 6. Loan Application Details</div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="required">Loan Type</label>
                    <select name="loan_type" id="loan_type" onchange="updateSubTypes()">
                        <option value="" selected>Select Loan Type</option>
                        <option value="Home Loan">Home Loan</option>
                        <option value="Personal Loan">Personal Loan</option>
                        <option value="Vehicle Loan">Vehicle Loan</option>
                        <option value="Gold Loan">Gold Loan</option>
                        <option value="Business Loan">Business Loan</option>
                        <option value="Education Loan">Education Loan</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="required">Loan Sub-Type</label>
                    <select name="loan_sub_type" id="loan_sub_type">
                        <option value="" selected>Select Sub-Type First</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="required">Requested Loan Amount (₹)</label>
                    <input type="number" name="loan_amount_requested" id="loan_amount" placeholder="e.g. 1500000" required>
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label class="required" style="display:flex; justify-content:space-between; align-items:center;">
                        <span>Expected Loan Tenure</span>
                        <span style="font-size:11px; font-weight:normal; color:#64748b;">(Auto calculates Years ↔ Months)</span>
                    </label>
                    <div style="display: flex; gap: 12px; align-items: center;">
                        <div style="flex: 1; position: relative;">
                            <input type="number" id="tenure_years" step="0.5" min="0" placeholder="Years (e.g. 10)" oninput="calcTenureFromYears(this.value)" style="padding-right: 52px; font-weight:600; color:#0f172a;">
                            <span style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); font-size: 12px; color: #64748b; font-weight: 700; pointer-events: none;">Years</span>
                        </div>
                        <div style="font-weight: 800; color: #64748b; font-size: 16px;">=</div>
                        <div style="flex: 1; position: relative;">
                            <input type="number" name="tenure_months" id="tenure" min="1" placeholder="Months (e.g. 120)" required oninput="calcTenureFromMonths(this.value)" style="padding-right: 60px; font-weight:600; color:#0f172a;">
                            <span style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); font-size: 12px; color: #64748b; font-weight: 700; pointer-events: none;">Months</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Smart Dynamic Document Suggestion Real-time Preview -->
            <div id="smart-doc-preview-box" style="margin-top: 16px; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 16px; display: none;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <span style="font-weight: 700; color: #1e40af; font-size: 13.5px; display: flex; align-items: center; gap: 6px;">
                        <i data-lucide="file-check-2" style="width: 16px; height: 16px;"></i> Smart Document Checklist (Auto-tailored for Phase 2)
                    </span>
                    <span id="smart-doc-count-badge" style="background: #2563eb; color: #fff; font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 12px;">0 Documents</span>
                </div>
                <div id="smart-doc-preview-list" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 8px; font-size: 12px; color: #1e3a8a;">
                    <!-- Populated dynamically via JS -->
                </div>
            </div>

            <!-- 7. Lead Source & RM Allocation -->
            <div class="form-section-title"> 7. Lead Source & Relationship Manager (RM) Assignment</div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="required">Lead Source</label>
                    <select name="lead_source" id="lead_source" onchange="toggleSourceFields()">
                        <option value="" selected>Select Lead Source</option>
                        <option value="Direct / Walk-in">Direct / Walk-in</option>
                        <option value="Referral Partner / Agent">Referral Partner / Agent</option>
                        <option value="Employee Referral">Employee Referral</option>
                        <option value="Digital / Social Media Campaign">Digital / Social Media Campaign</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Assign to Relationship Manager (RM)</label>
                    <select name="assigned_to" id="assigned_to">
                        <option value="">-- Auto-Assign / Unassigned Pool --</option>
                        <?php foreach($staff_members as $sm): ?>
                            <option value="<?php echo htmlspecialchars($sm['username']); ?>">
                                <?php echo htmlspecialchars($sm['name'] ?: $sm['username']); ?> (<?php echo htmlspecialchars($sm['username']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group" id="referral_field" style="display:none;">
                    <label>Select Referral Partner</label>
                    <select name="referral_id" id="referral_dropdown">
                        <option value="">-- Loading Referrals --</option>
                    </select>
                </div>

                <div class="form-group" id="employee_field" style="display:none;">
                    <label>Select Referring Employee</label>
                    <select name="employee_id" id="employee_dropdown">
                        <option value="">-- Loading Employees --</option>
                    </select>
                </div>
            </div>

            <!-- Action panel -->
            <div class="form-actions" style="margin-top: 24px; padding-top: 16px; border-top: 1px solid var(--border);">
                <button type="reset" class="btn btn-secondary">Clear Inputs</button>
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="check-circle"></i> 
                    <?php echo $edit_id ? 'Update Applicant Details' : 'Save Applicant & Proceed to Phase 2'; ?>
                </button>
            </div>
            </div>
        </div>
    </form>
</div>

<script>
    // --- CO-APPLICANT LOGIC START ---
    let coAppCount = 0;
    
    function toggleCoApplicantSection() {
        try {
            const chk = document.getElementById('has_co_applicant');
            const wrapper = document.getElementById('co_applicant_wrapper');
            const container = document.getElementById('co_applicants_container');
            if (!chk || !wrapper) return;
            
            const isChecked = !!chk.checked;
            wrapper.style.display = isChecked ? 'block' : 'none';
            
            if (isChecked && container && container.children.length === 0) {
                try { addCoApplicantBlock(); } catch(err) { console.error('Error adding co-applicant block:', err); }
            }
            if (typeof updateSmartDocPreview === 'function') {
                try { updateSmartDocPreview(); } catch(e){}
            }
        } catch(err) {
            console.error('Error toggling co-applicant section', err);
        }
    }

    function populateCoAppStateOptions(index) {
        const coState = document.getElementById(`coapp_state_${index}`);
        if (!coState) return;
        const mainState = document.getElementById('state');
        let hasOptions = false;
        let optionsHtml = '<option value="">Select State</option>';
        
        if (mainState && mainState.options && mainState.options.length > 1) {
            Array.from(mainState.options).forEach(opt => {
                if (opt.value) {
                    optionsHtml += `<option value="${opt.value}">${opt.text}</option>`;
                    hasOptions = true;
                }
            });
        }
        
        if (hasOptions) {
            coState.innerHTML = optionsHtml;
            try { initTS(`coapp_state_${index}`); } catch(e){}
        } else {
            fetch('api.php?api=get_states')
                .then(res => res.json())
                .then(data => {
                    if (data && data.success && Array.isArray(data.states)) {
                        let html = '<option value="">Select State</option>';
                        data.states.forEach(s => { html += `<option value="${s}">${s}</option>`; });
                        coState.innerHTML = html;
                        try { initTS(`coapp_state_${index}`); } catch(e){}
                    }
                })
                .catch(e => console.error(e));
        }
    }

    function addCoApplicantBlock() {
        coAppCount++;
        const index = coAppCount;
        
        const html = `
        <div id="coapp_block_${index}" style="background:white; border:1px solid #e2e8f0; border-radius:8px; padding:16px; margin-bottom:16px; position:relative;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; border-bottom:1px solid #f1f5f9; padding-bottom:12px;">
                <h4 style="margin:0; font-size:14px; color:#0f172a;"><i data-lucide="user-plus" style="width:16px; height:16px; vertical-align:middle; margin-right:4px;"></i> Co-Applicant #${index}</h4>
                ${index > 1 ? `<button type="button" onclick="document.getElementById('coapp_block_${index}').remove()" style="background:none; border:none; color:#ef4444; cursor:pointer; font-size:12px; font-weight:600;"><i data-lucide="trash-2" style="width:14px; height:14px; vertical-align:middle;"></i> Remove</button>` : ''}
            </div>
            
            <div class="form-grid" style="margin-bottom:16px;">
                <div class="form-group">
                    <label class="required">Relationship with Applicant</label>
                    <select name="coapp_relationship[]" id="coapp_rel_${index}">
                        <option value="" selected>Select Relation</option>
                        <option value="Wife">Wife</option>
                        <option value="Husband">Husband</option>
                        <option value="Spouse">Spouse</option>
                        <option value="Father">Father</option>
                        <option value="Mother">Mother</option>
                        <option value="Son">Son</option>
                        <option value="Daughter">Daughter</option>
                        <option value="Brother">Brother</option>
                        <option value="Sister">Sister</option>
                        <option value="Business Partner">Business Partner</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="required">Income Considered for Loan?</label>
                    <div style="display:flex; gap:16px; padding-top:8px;">
                        <label style="cursor:pointer;"><input type="radio" name="coapp_financial_${index}" value="Yes" onchange="toggleFinancial(${index})"> Yes (Financial)</label>
                        <label style="cursor:pointer;"><input type="radio" name="coapp_financial_${index}" value="No" onchange="toggleFinancial(${index})" checked> No (Guarantor)</label>
                        <input type="hidden" name="coapp_is_financial[]" id="hidden_financial_${index}" value="No">
                    </div>
                </div>
            </div>

            <div class="form-grid" style="margin-bottom:16px;">
                <div class="form-group"><label class="required">Full Name</label><input type="text" name="coapp_name[]" placeholder="Full Name"></div>
                <div class="form-group"><label class="required">Mobile Number</label><input type="text" name="coapp_mobile[]" placeholder="10-digit number" maxlength="10"></div>
                <div class="form-group"><label>Email ID</label><input type="email" name="coapp_email[]" placeholder="Optional"></div>
                <div class="form-group"><label class="required">Date of Birth</label><input type="date" name="coapp_dob[]"></div>
                <div class="form-group"><label class="required">PAN Number</label><input type="text" name="coapp_pan[]" placeholder="ABCDE1234F" style="text-transform:uppercase;"></div>
                <div class="form-group"><label class="required">Aadhaar Number</label><input type="text" name="coapp_aadhar[]" placeholder="12-digit Aadhaar" maxlength="12"></div>
            </div>

            <div style="margin-bottom:16px;">
                <label style="display:flex; align-items:center; gap:8px; font-size:13px; font-weight:600; cursor:pointer; margin-bottom:12px;">
                    <input type="checkbox" name="coapp_same_address_check_${index}" id="coapp_same_${index}" value="1" onchange="toggleCoappAddress(this, ${index})" style="accent-color:var(--primary);">
                    Same as Primary Applicant Address
                    <input type="hidden" name="coapp_same_address[]" id="hidden_same_address_${index}" value="0">
                </label>
                <div id="coapp_address_block_${index}" class="form-grid">
                    <div class="form-group full-width"><label class="required">Complete Address</label><input type="text" name="coapp_address[]" id="coapp_addr_${index}"></div>
                    <div class="form-group">
                        <label class="required">Pincode</label>
                        <div style="position: relative;">
                            <input type="text" name="coapp_pincode[]" id="coapp_pin_${index}" maxlength="6" pattern="\\d{6}" onkeyup="verifyPincode(this.value, false, 'coapp_pin_status_${index}', 'coapp_state_${index}', 'coapp_city_${index}')">
                            <span id="coapp_pin_status_${index}" style="position: absolute; right: 10px; top: 10px;"></span>
                        </div>
                    </div>
                    <div class="form-group"><label class="required">State</label><select name="coapp_state[]" id="coapp_state_${index}" onchange="loadCities(this.value, null, 'coapp_city_${index}')"><option value="">Select State</option></select></div>
                    <div class="form-group"><label class="required">City / District</label><select name="coapp_city[]" id="coapp_city_${index}"><option value="">Select City</option></select></div>
                </div>
            </div>

            <div id="coapp_financial_block_${index}" style="display:none; background:#f1f5f9; padding:16px; border-radius:6px;">
                <h5 style="margin:0 0 12px 0; font-size:13px; color:#475569;">Financial & Employment Details</h5>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="required">Employment Type</label>
                        <select name="coapp_emp_type[]" id="coapp_emp_${index}">
                            <option value="" selected>Select</option>
                            <option value="Salaried">Salaried</option>
                            <option value="Government Employee">Government Employee</option>
                            <option value="Self-Employed Business">Self-Employed Business</option>
                            <option value="Self-Employed Professional">Self-Employed Professional</option>
                            <option value="Homemaker">Homemaker</option>
                            <option value="Retired">Retired</option>
                        </select>
                    </div>
                    <div class="form-group"><label class="required">Monthly Net Income (₹)</label><input type="number" name="coapp_income[]" id="coapp_inc_${index}" placeholder="0"></div>
                    <div class="form-group"><label>Current Monthly EMIs (₹)</label><input type="number" name="coapp_emis[]" id="coapp_emis_${index}" placeholder="Optional"></div>
                </div>
            </div>
        </div>`;
        
        const container = document.getElementById('co_applicants_container');
        if (container) container.insertAdjacentHTML('beforeend', html);
        
        populateCoAppStateOptions(index);
        
        try { initTS(`coapp_rel_${index}`, true); } catch(e){}
        try { initTS(`coapp_city_${index}`); } catch(e){}
        try { initTS(`coapp_emp_${index}`); } catch(e){}
        
        if (typeof lucide !== 'undefined') {
            try { lucide.createIcons(); } catch(e){}
        }
    }

    function toggleFinancial(index) {
        const radios = document.getElementsByName(`coapp_financial_${index}`);
        let isFin = false;
        radios.forEach(r => { if(r.checked && r.value === 'Yes') isFin = true; });
        
        const hiddenInput = document.getElementById(`hidden_financial_${index}`);
        if (hiddenInput) hiddenInput.value = isFin ? 'Yes' : 'No';
        
        const block = document.getElementById(`coapp_financial_block_${index}`);
        if (block) block.style.display = isFin ? 'block' : 'none';
        
        const incInput = document.getElementById(`coapp_inc_${index}`);
        if (incInput) incInput.required = isFin;
        if (typeof updateSmartDocPreview === 'function') {
            try { updateSmartDocPreview(); } catch(e){}
        }
    }

    function toggleCoappAddress(elOrIndex, idx) {
        let index = idx;
        let isChecked = false;
        if (typeof elOrIndex === 'object' && elOrIndex !== null) {
            isChecked = !!elOrIndex.checked;
        } else {
            index = elOrIndex;
            const chk = document.querySelector(`[name="coapp_same_address_check_${index}"]`) || document.getElementById(`coapp_same_${index}`);
            isChecked = chk ? chk.checked : false;
        }
        
        const hiddenInput = document.getElementById(`hidden_same_address_${index}`);
        if (hiddenInput) hiddenInput.value = isChecked ? '1' : '0';
        
        const block = document.getElementById(`coapp_address_block_${index}`);
        if (block) {
            block.style.display = isChecked ? 'none' : 'grid';
        }
    }
    window.toggleCoappAddress = toggleCoappAddress;
    window.toggleCoAppAddress = toggleCoappAddress;
    // --- CO-APPLICANT LOGIC END ---

    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const custName = urlParams.get('customer_name') || urlParams.get('name');
        const custMobile = urlParams.get('mobile');
        const visitId = urlParams.get('visit_id');

        if (custName) {
            const elName = document.querySelector('[name="customer_name"]');
            if (elName && !elName.value) elName.value = custName;
        }
        if (custMobile) {
            const elMobile = document.querySelector('[name="mobile"]');
            if (elMobile && !elMobile.value) elMobile.value = custMobile;
        }
        if (visitId) {
            const elLead = document.getElementById('converted_lead_id');
            if (elLead && !elLead.value) elLead.value = visitId;
        }

        const leadDataStr = sessionStorage.getItem('convert_lead_data');
        if (leadDataStr) {
            try {
                const l = JSON.parse(leadDataStr);
                const setVal = (name, val) => {
                    const el = document.querySelector(`[name="${name}"]`);
                    if (el && val) el.value = val;
                };
                
                setVal('converted_lead_id', l.id);
                setVal('customer_name', l.lead_name);
                setVal('mobile', l.mobile);
                setVal('email', l.email);
                setVal('loan_amount_requested', l.loan_amount);
                
                if (l.requirement) {
                    const ltSelect = document.getElementById('loan_type');
                    let found = Array.from(ltSelect.options).some(o => o.value === l.requirement);
                    if (!found) {
                        let matched = Array.from(ltSelect.options).find(o => o.text.toLowerCase().includes(l.requirement.toLowerCase()) || l.requirement.toLowerCase().includes(o.text.toLowerCase()));
                        if (matched) {
                            ltSelect.value = matched.value;
                            found = true;
                        }
                    } else {
                        ltSelect.value = l.requirement;
                    }
                    if (found) {
                        updateSubTypes();
                    }
                }

                if (l.lead_source) {
                    const srcSelect = document.getElementById('lead_source');
                    let srcVal = '';
                    let lowerSrc = l.lead_source.toLowerCase();
                    if (lowerSrc.includes('referral') || lowerSrc.includes('partner')) srcVal = 'Referral Partner / Agent';
                    else if (lowerSrc.includes('employee') || lowerSrc.includes('staff')) srcVal = 'Employee Referral';
                    else if (lowerSrc.includes('website')) srcVal = 'Direct / Website';
                    else if (lowerSrc.includes('builder')) srcVal = 'Builder Tie-up';
                    else srcVal = 'Direct / Website';
                    
                    srcSelect.value = srcVal;
                    toggleSourceFields();
                    
                    if (l.added_by && l.added_by !== 'direct') {
                        setTimeout(() => {
                            if (srcVal === 'Referral Partner / Agent' || srcVal === 'Builder Tie-up') {
                                const refSelect = document.getElementById('referral_dropdown');
                                if (refSelect) {
                                    refSelect.value = l.added_by;
                                }
                            } else if (srcVal === 'Employee Referral') {
                                const empSelect = document.getElementById('employee_dropdown');
                                if (empSelect) {
                                    empSelect.value = l.added_by;
                                }
                            }
                        }, 500);
                    }
                }
            } catch(e) {
                console.error("Error parsing convert_lead_data", e);
            } finally {
                sessionStorage.removeItem('convert_lead_data');
            }
        }
    });

    function toggleKbdMenu(e) {
        if (e) {
            e.stopPropagation();
            if (e.preventDefault) e.preventDefault();
        }
        const menu = document.getElementById('kbd-shortcuts-menu');
        if (menu) {
            const isHidden = (menu.style.display === 'none' || menu.style.display === '');
            menu.style.display = isHidden ? 'block' : 'none';
            if (isHidden && typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }
    }

    document.addEventListener('click', function(e) {
        const menu = document.getElementById('kbd-shortcuts-menu');
        const btn = e.target.closest('.kbd-dropdown-container');
        if (menu && !btn) {
            menu.style.display = 'none';
        }
    });

    function handleEmploymentTypeChange(val) {
        const wrapper = document.getElementById('org_details_wrapper');
        const nameLabel = document.getElementById('org_name_label');
        const nameInput = document.getElementById('organization_name');
        const addrLabel = document.getElementById('org_address_label');
        const addrInput = document.getElementById('organization_address');

        if (!wrapper || !nameLabel || !nameInput || !addrLabel || !addrInput) return;

        if (!val) {
            wrapper.style.display = 'none';
            return;
        }

        wrapper.style.display = 'block';

        if (val === 'Government Employee') {
            nameLabel.textContent = 'Department / Posting Name';
            nameInput.placeholder = 'e.g. Ministry of Railways, Revenue Dept, PWD, Police Dept';
            addrLabel.textContent = 'Office / Job Address';
            addrInput.placeholder = 'e.g. Department Office Address & City';
        } else if (val === 'Salaried') {
            nameLabel.textContent = 'Company / Employer Name';
            nameInput.placeholder = 'e.g. Tata Consultancy Services, Infosys, Pvt Ltd';
            addrLabel.textContent = 'Company / Office Address';
            addrInput.placeholder = 'e.g. Corporate Office / Work premises address';
        } else if (val === 'Professional') {
            nameLabel.textContent = 'Practice / Firm Name';
            nameInput.placeholder = 'e.g. Apex Legal Chambers, Dr. Care Clinic, CA Firm';
            addrLabel.textContent = 'Clinic / Office Address';
            addrInput.placeholder = 'e.g. Clinic, Chamber, or Studio address';
        } else {
            // Self-Employed or Business Owner
            nameLabel.textContent = 'Firm / Business Name';
            nameInput.placeholder = 'e.g. Apex Enterprises, Universal Traders, Royal Store';
            addrLabel.textContent = 'Business / Shop Address';
            addrInput.placeholder = 'e.g. Store, Factory, or Office establishment address';
        }
        updateSmartDocPreview();
    }

    function calcTenureFromYears(yearsVal) {
        const yrs = parseFloat(yearsVal);
        const monthsInput = document.getElementById('tenure');
        if (!isNaN(yrs) && yrs > 0) {
            monthsInput.value = Math.round(yrs * 12);
        } else if (yearsVal === '') {
            monthsInput.value = '';
        }
    }

    function calcTenureFromMonths(monthsVal) {
        const m = parseInt(monthsVal, 10);
        const yearsInput = document.getElementById('tenure_years');
        if (!isNaN(m) && m > 0) {
            const yrs = m / 12;
            yearsInput.value = (yrs % 1 === 0) ? yrs : yrs.toFixed(1);
        } else if (monthsVal === '') {
            yearsInput.value = '';
        }
    }

    const subTypeMap = {
        'Home Loan': ['Home Purchase', 'Plot Purchase', 'New Flat Purchase', 'Purchase + Construction', 'Home Construction', 'Renovation', 'Balance Transfer (Top-Up)'],
        'Personal Loan': ['Medical Emergency', 'Wedding', 'Travel', 'Debt Consolidation'],
        'Vehicle Loan': ['Two-Wheeler (New)', 'Two-Wheeler (Used)', 'Four-Wheeler (New)', 'Commercial Vehicle'],
        'Gold Loan': ['Standard Gold Jewel Loan', 'Agricultural Gold Loan'],
        'Business Loan': ['Working Capital', 'Equipment Machinery Purchase', 'MSME Expansion', 'Merchant Overdraft'],
        'Education Loan': ['Inland Studies (India)', 'Overseas Studies (Foreign)']
    };

    function updateSubTypes() {
        const typeSelect = document.getElementById('loan_type');
        const selected = typeSelect.value;
        const subSelect = document.getElementById('loan_sub_type');
        
        // If TomSelect is active on loan_sub_type, use its API
        if (tsInstances['loan_sub_type']) {
            const ts = tsInstances['loan_sub_type'];
            ts.clear();
            ts.clearOptions();
            ts.addOption({value: '', text: 'Select Sub-Type'});
            if (subTypeMap[selected]) {
                subTypeMap[selected].forEach(sub => {
                    ts.addOption({value: sub, text: sub});
                });
            }
            ts.refreshOptions(false);
        } else {
            subSelect.innerHTML = '<option value="">Select Sub-Type</option>';
            if (subTypeMap[selected]) {
                subTypeMap[selected].forEach(sub => {
                    let opt = document.createElement('option');
                    opt.value = sub;
                    opt.textContent = sub;
                    subSelect.appendChild(opt);
                });
            }
        }
        updateSmartDocPreview();
    }

    async function updateSmartDocPreview() {
        const loanType = document.getElementById('loan_type') ? document.getElementById('loan_type').value : '';
        const loanSubType = document.getElementById('loan_sub_type') ? document.getElementById('loan_sub_type').value : '';
        const empTypeSelect = document.querySelector('[name="employment_type"]');
        const empType = empTypeSelect ? empTypeSelect.value : 'Salaried';
        const hasCoApp = document.getElementById('has_co_applicant') ? document.getElementById('has_co_applicant').checked : false;
        let isFinCoApp = false;
        if (hasCoApp) {
            const finRadios = document.querySelectorAll('[name^="coapp_financial_"]');
            finRadios.forEach(r => {
                if (r.checked && r.value === 'Yes') isFinCoApp = true;
            });
        }

        const box = document.getElementById('smart-doc-preview-box');
        const list = document.getElementById('smart-doc-preview-list');
        const badge = document.getElementById('smart-doc-count-badge');
        if (!box || !list) return;

        if (!loanType) {
            box.style.display = 'none';
            return;
        }

        try {
            const res = await fetch(`api.php?api=get_document_suggestions&loan_type=${encodeURIComponent(loanType)}&loan_sub_type=${encodeURIComponent(loanSubType)}&employment_type=${encodeURIComponent(empType)}&has_coapp=${hasCoApp ? 1 : 0}&coapp_is_financial=${isFinCoApp ? 1 : 0}`);
            const data = await res.json();
            if (data && data.success && data.suggestions && data.suggestions.length > 0) {
                box.style.display = 'block';
                badge.innerText = `${data.suggestions.length} Suggested Documents`;
                
                let html = '';
                data.suggestions.forEach(s => {
                    const mandBadge = s.is_mandatory ? '<span style="color:#dc2626; font-weight:700;">*Mandatory</span>' : '<span style="color:#64748b;">Optional</span>';
                    const ownerBadge = s.owner_type === 'CoApplicant' ? '<span style="background:#f1f5f9; color:#475569; font-size:10px; padding:1px 5px; border-radius:4px; font-weight:600;">Co-App</span>' : '';
                    html += `
                        <div style="background:#ffffff; border:1px solid #dbeafe; border-radius:6px; padding:8px 10px; display:flex; flex-direction:column; gap:2px; box-shadow:0 1px 2px rgba(0,0,0,0.02);">
                            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:4px;">
                                <strong style="color:#0f172a; font-size:12px;">${s.doc_name}</strong>
                                ${ownerBadge}
                            </div>
                            <div style="display:flex; justify-content:space-between; font-size:11px; margin-top:2px;">
                                <span style="color:#64748b;">${s.category}</span>
                                ${mandBadge}
                            </div>
                        </div>
                    `;
                });
                list.innerHTML = html;
                if (typeof lucide !== 'undefined') lucide.createIcons();
            } else {
                box.style.display = 'none';
            }
        } catch (e) {
            console.error("Error fetching smart doc suggestions", e);
        }
    }

    // --- AI DOCUMENT OCR & HARDWARE SCANNER ENGINE ---
    let currentScanTarget = 'primary';

    function openExecutiveScanModal() {
        const hasCoApp = document.getElementById('has_co_applicant') ? document.getElementById('has_co_applicant').checked : false;
        
        let targetOptionsHtml = `
            <label style="display:flex; align-items:center; gap:8px; padding:10px 14px; background:#f8fafc; border:1px solid #cbd5e1; border-radius:6px; cursor:pointer; font-weight:700; color:#0f172a; font-size:13px;">
                <input type="radio" name="scan_target_select" value="primary" checked style="accent-color:#0f172a;">
                👤 Primary Applicant Details
            </label>
        `;
        if (hasCoApp) {
            targetOptionsHtml += `
                <label style="display:flex; align-items:center; gap:8px; padding:10px 14px; background:#f8fafc; border:1px solid #cbd5e1; border-radius:6px; cursor:pointer; font-weight:700; color:#0f172a; font-size:13px; margin-top:8px;">
                    <input type="radio" name="scan_target_select" value="coapplicant" style="accent-color:#0f172a;">
                    👥 Co-Applicant Details
                </label>
            `;
        }

        Swal.fire({
            title: '<div style="font-size:16px; font-weight:800; color:#0f172a;">AI Document & Physical Hardware Scanner</div>',
            html: `
                <div style="text-align:left; font-size:12.5px; color:#475569; margin-bottom:14px;">
                    Select target applicant and capture method to auto-extract details into form fields:
                </div>
                <div style="margin-bottom:16px; text-align:left;">
                    <div style="font-size:11px; font-weight:800; text-transform:uppercase; color:#64748b; margin-bottom:6px;">1. Target Person</div>
                    ${targetOptionsHtml}
                </div>
                <div style="text-align:left;">
                    <div style="font-size:11px; font-weight:800; text-transform:uppercase; color:#64748b; margin-bottom:8px;">2. Capture Method</div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                        <button type="button" onclick="triggerFileScanUpload()" style="background:#0f172a; color:#fff; border:none; padding:12px; border-radius:6px; font-weight:700; font-size:12px; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:6px;">
                            <i data-lucide="file-up" style="width:16px; height:16px;"></i> Upload Image / PDF
                        </button>
                        <button type="button" onclick="triggerHardwarePrinterScan()" style="background:#1e293b; color:#fff; border:1px solid #334155; padding:12px; border-radius:6px; font-weight:700; font-size:12px; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:6px;">
                            <i data-lucide="printer" style="width:16px; height:16px;"></i> Physical Printer / Scanner
                        </button>
                    </div>
                </div>
            `,
            showConfirmButton: false,
            showCloseButton: true,
            didOpen: () => {
                if (typeof lucide !== 'undefined') lucide.createIcons();
            }
        });
    }

    function getSelectedScanTarget() {
        const radios = document.getElementsByName('scan_target_select');
        let selected = 'primary';
        radios.forEach(r => { if (r.checked) selected = r.value; });
        return selected;
    }

    function triggerFileScanUpload() {
        currentScanTarget = getSelectedScanTarget();
        Swal.close();
        document.getElementById('ai-scan-file-input').click();
    }

    function triggerHardwarePrinterScan() {
        currentScanTarget = getSelectedScanTarget();
        Swal.close();
        
        if (typeof scanner === 'undefined') {
            Swal.fire({
                icon: 'info',
                title: 'Hardware Printer Scanner',
                text: 'Scanner.js is loading or USB/Network printer scanner is disconnected. You can also select Upload Image/PDF.',
                confirmButtonColor: '#0f172a'
            });
            return;
        }

        Swal.fire({
            title: '<div style="font-size:15px; font-weight:800; color:#0f172a;">Connecting Physical Scanner...</div>',
            html: `
                <div style="padding:10px 4px; text-align:center;">
                    <div style="position:relative; width:120px; height:100px; margin:0 auto 16px auto; background:#f8fafc; border:1.5px solid #cbd5e1; border-radius:8px; overflow:hidden; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:6px;">
                        <div class="scan-laser-line"></div>
                        <i data-lucide="printer" style="width:36px; height:36px; color:#0f172a;"></i>
                        <span style="font-size:9.5px; font-weight:800; text-transform:uppercase; color:#0f172a; letter-spacing:0.5px;">Scanner Bed Active</span>
                    </div>
                    <div style="font-size:13px; font-weight:700; color:#0f172a; margin-bottom:4px;">
                        Reading Physical Scanner Bed...
                    </div>
                    <div style="font-size:11.5px; color:#64748b; margin-bottom:14px;">
                        Please place document on scanner glass. Feeder is capturing page...
                    </div>
                    <div style="background:#e2e8f0; border-radius:3px; height:6px; overflow:hidden; width:100%;">
                        <div class="scan-progress-bar" style="width: 45%;"></div>
                    </div>
                </div>
            `,
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => {
                if (typeof lucide !== 'undefined') lucide.createIcons();
            }
        });

        scanner.scan(function(status, response) {
            Swal.close();
            if (!scanner.isScannedSuccessfully(response)) {
                Swal.fire({ icon: 'info', title: 'Scan Cancelled', text: 'Scanning was cancelled or no physical scanner detected.', confirmButtonColor: '#0f172a' });
                return;
            }
            const images = scanner.getScannedImages(response, true, false);
            if (images && images.length > 0) {
                fetch(images[0].src)
                    .then(res => res.blob())
                    .then(blob => {
                        const file = new File([blob], "hardware_printer_scan_" + Date.now() + ".jpg", { type: "image/jpeg" });
                        handleAiDocScan(file, currentScanTarget);
                    });
            }
        }, {
            output_settings: [{ type: 'returnurl', format: 'jpg' }]
        });
    }

    async function handleAiDocScan(file, targetType = null) {
        if (!file) return;
        if (!targetType) targetType = currentScanTarget || 'primary';

        const isCoApp = (targetType === 'coapplicant');

        Swal.fire({
            title: `<div style="font-size:15px; font-weight:800; color:#0f172a; display:flex; align-items:center; justify-content:center; gap:8px;">
                <i data-lucide="scan" style="width:18px; height:18px;"></i>
                Scanning Document for ${isCoApp ? 'Co-Applicant' : 'Primary Applicant'}
            </div>`,
            html: `
                <div style="padding:10px 4px; text-align:center;">
                    <!-- Laser Scan Animation Box -->
                    <div style="position:relative; width:120px; height:100px; margin:0 auto 16px auto; background:#f8fafc; border:1.5px solid #cbd5e1; border-radius:8px; overflow:hidden; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:6px;">
                        <div class="scan-laser-line"></div>
                        <i data-lucide="file-text" style="width:36px; height:36px; color:#64748b;"></i>
                        <span style="font-size:9.5px; font-weight:800; text-transform:uppercase; color:#0f172a; letter-spacing:0.5px;">AI OCR Active</span>
                    </div>

                    <!-- Step-by-step Status Text -->
                    <div id="scan-step-text" style="font-size:13px; font-weight:700; color:#0f172a; margin-bottom:4px;">
                        Step 1/3: Reading Document Image...
                    </div>
                    <div id="scan-detail-text" style="font-size:11.5px; color:#64748b; margin-bottom:14px;">
                        Extracting PAN, Aadhaar, Name, DOB, Pincode & Income...
                    </div>

                    <!-- Progress Bar -->
                    <div style="background:#e2e8f0; border-radius:3px; height:6px; overflow:hidden; width:100%;">
                        <div id="scan-progress-fill" class="scan-progress-bar" style="width: 25%;"></div>
                    </div>
                </div>
            `,
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => {
                if (typeof lucide !== 'undefined') lucide.createIcons();
            }
        });

        // Update progress step 2 after 600ms
        const progressTimer = setTimeout(() => {
            const stepText = document.getElementById('scan-step-text');
            const detailText = document.getElementById('scan-detail-text');
            const fillBar = document.getElementById('scan-progress-fill');
            if (stepText) stepText.innerText = "Step 2/3: Running AI Context Extraction & Pincode Resolution...";
            if (detailText) detailText.innerText = "Parsing text structures & searching database...";
            if (fillBar) fillBar.style.width = "65%";
        }, 600);

        const formData = new FormData();
        formData.append('document_file', file);

        try {
            const response = await fetch('api.php?api=extract_document_details', {
                method: 'POST',
                body: formData
            });
            clearTimeout(progressTimer);
            
            const fillBar = document.getElementById('scan-progress-fill');
            const stepText = document.getElementById('scan-step-text');
            if (fillBar) fillBar.style.width = "100%";
            if (stepText) stepText.innerText = "Step 3/3: Auto-filling Form Fields...";

            const data = await response.json();
            setTimeout(() => { Swal.close(); }, 400);

            if (data && data.success && data.extracted_data) {
                const ext = data.extracted_data;
                const filledFields = [];

                if (isCoApp) {
                    const block = document.getElementById('coapp_block_1') || document.getElementById('co_applicants_container');
                    
                    if (ext.customer_name) {
                        const el = block ? block.querySelector('[name="coapp_name[]"]') : document.querySelector('[name="coapp_name[]"]');
                        if (el) { el.value = ext.customer_name; highlightField(el); filledFields.push('Co-Applicant Name: ' + ext.customer_name); }
                    }
                    if (ext.mobile) {
                        const el = block ? block.querySelector('[name="coapp_mobile[]"]') : document.querySelector('[name="coapp_mobile[]"]');
                        if (el) { el.value = ext.mobile; highlightField(el); filledFields.push('Co-App Mobile: ' + ext.mobile); }
                    }
                    if (ext.email) {
                        const el = block ? block.querySelector('[name="coapp_email[]"]') : document.querySelector('[name="coapp_email[]"]');
                        if (el) { el.value = ext.email; highlightField(el); filledFields.push('Co-App Email: ' + ext.email); }
                    }
                    if (ext.pan_number) {
                        const el = block ? block.querySelector('[name="coapp_pan[]"]') : document.querySelector('[name="coapp_pan[]"]');
                        if (el) { el.value = ext.pan_number; highlightField(el); filledFields.push('Co-App PAN: ' + ext.pan_number); }
                    }
                    if (ext.aadhar_number) {
                        const el = block ? block.querySelector('[name="coapp_aadhar[]"]') : document.querySelector('[name="coapp_aadhar[]"]');
                        if (el) { el.value = ext.aadhar_number; highlightField(el); filledFields.push('Co-App Aadhaar: ' + ext.aadhar_number); }
                    }
                    if (ext.dob) {
                        const el = block ? block.querySelector('[name="coapp_dob[]"]') : document.querySelector('[name="coapp_dob[]"]');
                        if (el) { el.value = ext.dob; highlightField(el); filledFields.push('Co-App DOB: ' + ext.dob); }
                    }
                    if (ext.pincode) {
                        const el = block ? block.querySelector('[name="coapp_pincode[]"]') : document.querySelector('[name="coapp_pincode[]"]');
                        if (el) {
                            el.value = ext.pincode;
                            highlightField(el);
                            filledFields.push('Co-App Pincode: ' + ext.pincode);
                            if (typeof verifyPincode === 'function') verifyPincode(ext.pincode, false, 'coapp_pin_status_1', 'coapp_state_1', 'coapp_city_1');
                        }
                    }
                    if (ext.address) {
                        const el = block ? block.querySelector('[name="coapp_address[]"]') : document.querySelector('[name="coapp_address[]"]');
                        if (el) { el.value = ext.address; highlightField(el); filledFields.push('Co-App Address: ' + ext.address); }
                    }
                    if (ext.monthly_income) {
                        const el = block ? block.querySelector('[name="coapp_income[]"]') : document.querySelector('[name="coapp_income[]"]');
                        if (el) { el.value = ext.monthly_income; highlightField(el); filledFields.push('Co-App Income: ₹' + ext.monthly_income); }
                    }
                } else {
                    if (ext.customer_name) {
                        const el = document.querySelector('[name="customer_name"]');
                        if (el) { el.value = ext.customer_name; highlightField(el); filledFields.push('Customer Name: ' + ext.customer_name); }
                    }
                    if (ext.mobile) {
                        const el = document.querySelector('[name="mobile"]');
                        if (el) { el.value = ext.mobile; highlightField(el); filledFields.push('Mobile: ' + ext.mobile); }
                    }
                    if (ext.email) {
                        const el = document.querySelector('[name="email"]');
                        if (el) { el.value = ext.email; highlightField(el); filledFields.push('Email: ' + ext.email); }
                    }
                    if (ext.pan_number) {
                        const el = document.querySelector('[name="pan_number"]');
                        if (el) { el.value = ext.pan_number; highlightField(el); filledFields.push('PAN Number: ' + ext.pan_number); }
                    }
                    if (ext.aadhar_number) {
                        const el = document.querySelector('[name="aadhar_number"]');
                        if (el) { el.value = ext.aadhar_number; highlightField(el); filledFields.push('Aadhaar Number: ' + ext.aadhar_number); }
                    }
                    if (ext.dob) {
                        const el = document.querySelector('[name="dob"]');
                        if (el) { el.value = ext.dob; highlightField(el); filledFields.push('Date of Birth: ' + ext.dob); }
                    }
                    if (ext.pincode) {
                        const el = document.querySelector('[name="pincode"]');
                        if (el) {
                            el.value = ext.pincode;
                            highlightField(el);
                            filledFields.push('Pincode: ' + ext.pincode);
                            if (typeof verifyPincode === 'function') verifyPincode(ext.pincode);
                        }
                    }
                    if (ext.address) {
                        const el = document.querySelector('[name="address"]');
                        if (el) { el.value = ext.address; highlightField(el); filledFields.push('Address: ' + ext.address); }
                    }
                    if (ext.monthly_income) {
                        const el = document.querySelector('[name="monthly_income"]');
                        if (el) { el.value = ext.monthly_income; highlightField(el); filledFields.push('Monthly Income: ₹' + ext.monthly_income); }
                    }
                    if (ext.employment_type) {
                        const el = document.querySelector('[name="employment_type"]');
                        if (el) { el.value = ext.employment_type; highlightField(el); filledFields.push('Employment Type: ' + ext.employment_type); }
                    }
                }

                if (filledFields.length > 0) {
                    Swal.fire({
                        icon: 'success',
                        title: `⚡ ${isCoApp ? 'Co-Applicant' : 'Primary Applicant'} Form Auto-Filled!`,
                        html: `<div style="text-align:left; font-size:13px; color:#334155;"><b>Extracted Fields (${filledFields.length}):</b><br><ul style="margin-top:6px; padding-left:20px;">${filledFields.map(f => `<li>${f}</li>`).join('')}</ul></div>`,
                        confirmButtonText: 'Confirm & Continue',
                        confirmButtonColor: '#0f172a'
                    });
                } else {
                    Swal.fire({
                        icon: 'info',
                        title: 'Document Scanned',
                        text: 'No matching KYC fields could be automatically recognized in this file. Please verify and fill manually.',
                        confirmButtonColor: '#0f172a'
                    });
                }
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Scan Failed',
                    text: (data && data.error) ? data.error : 'Could not extract document details.',
                    confirmButtonColor: '#0f172a'
                });
            }
        } catch (e) {
            Swal.close();
            console.error("OCR Scan Error:", e);
            Swal.fire({
                icon: 'error',
                title: 'Extraction Error',
                text: 'An error occurred while parsing the document.',
                confirmButtonColor: '#0f172a'
            });
        }

        const inputEl = document.getElementById('ai-scan-file-input');
        if (inputEl) inputEl.value = '';
    }

    function highlightField(el) {
        if (!el) return;
        el.style.transition = 'all 0.3s ease';
        el.style.borderColor = '#0f172a';
        el.style.boxShadow = '0 0 0 3px rgba(15, 23, 42, 0.15)';
        setTimeout(() => {
            el.style.borderColor = '';
            el.style.boxShadow = '';
        }, 3500);
    }

    let tsInstances = {};
    function initTS(elementId, keepOrder = false) {
        const el = document.getElementById(elementId);
        if (!el) return;
        if (tsInstances[elementId]) {
            try { tsInstances[elementId].destroy(); } catch(e){}
            delete tsInstances[elementId];
        }
        const config = { create: false };
        if (!keepOrder) {
            config.sortField = { field: "text", direction: "asc" };
        }
        try {
            tsInstances[elementId] = new TomSelect(el, config);
        } catch(err) {
            console.warn("TomSelect init warning for", elementId, err);
        }
    }

    async function loadCities(stateName, callback = null, cityId = 'city', defaultCity = null) {
        const cityEl = document.getElementById(cityId);
        
        let ts = tsInstances[cityId];
        if (!ts && cityEl) {
            initTS(cityId);
            ts = tsInstances[cityId];
        }
        
        if (ts) {
            ts.clear();
            ts.clearOptions();
            ts.addOption({value: '', text: 'Select City'});
        } else if (cityEl) {
            cityEl.innerHTML = '<option value="">Select City</option>';
        }

        if (!stateName) return;
        
        try {
            const res = await fetch(`api.php?api=get_cities&state=${encodeURIComponent(stateName)}`);
            const data = await res.json();
            if (data && data.success) {
                data.cities.forEach(c => {
                    if (ts) {
                        ts.addOption({value: c, text: c});
                    } else if (cityEl) {
                        let opt = document.createElement('option');
                        opt.value = c;
                        opt.textContent = c;
                        cityEl.appendChild(opt);
                    }
                });
                
                if (ts && defaultCity) {
                    let exactMatch = data.cities.find(c => c.toLowerCase() === defaultCity.toLowerCase());
                    if (exactMatch) ts.setValue(exactMatch);
                } else if (!ts && cityEl && defaultCity) {
                    cityEl.value = defaultCity;
                }
            }
            if (callback) callback();
        } catch(e) {
            console.error(e);
        }
    }

    async function verifyPincode(pin, isLoad = false, statusId = 'pincode-status', stateId = 'state', cityId = 'city') {
        const statusEl = document.getElementById(statusId);
        const cityEl = document.getElementById(cityId);
        const stateEl = document.getElementById(stateId);
        
        if (pin.length === 6) {
            statusEl.innerHTML = '<i data-lucide="loader" class="spin" style="color: var(--primary); width: 18px; height: 18px;"></i>';
            if (window.lucide) lucide.createIcons();
            
            try {
                const res = await fetch(`api.php?api=verify_pincode&pin=${pin}`);
                const data = await res.json();
                
                if (data && data.success) {
                    statusEl.innerHTML = '<i data-lucide="check-circle" style="color: var(--success); width: 18px; height: 18px;"></i>';
                    
                    if (tsInstances[stateId]) {
                        tsInstances[stateId].setValue(data.state, true); // true = silent, prevent double fetch
                    } else {
                        stateEl.value = data.state;
                    }
                    
                    // Pass data.city as the 4th parameter (defaultCity)
                    await loadCities(data.state, null, cityId, data.city);
                    
                } else {
                    statusEl.innerHTML = '<i data-lucide="x-circle" style="color: var(--danger); width: 18px; height: 18px;"></i>';
                }
                if (window.lucide) lucide.createIcons();
            } catch(e) {
                statusEl.innerHTML = '';
            }
        } else {
            statusEl.innerHTML = '';
        }
    }

    function toggleSourceFields() {
        const source = document.getElementById('lead_source').value;
        const refField = document.getElementById('referral_field');
        const empField = document.getElementById('employee_field');
        
        refField.style.display = (source === 'Referral Partner / Agent') ? 'block' : 'none';
        empField.style.display = (source === 'Employee Referral') ? 'block' : 'none';
    }

    async function loadStates() {
        try {
            const res = await fetch('api.php?api=get_states');
            const data = await res.json();
            const stateEl = document.getElementById('state');
            stateEl.innerHTML = '<option value="">Select State</option>';
            if (data && data.success) {
                data.states.forEach(s => {
                    let opt = document.createElement('option');
                    opt.value = s;
                    opt.textContent = s;
                    stateEl.appendChild(opt);
                });
            }
            initTS('state');
            initTS('city');
        } catch(e) {
            initTS('state');
            initTS('city');
        }
    }



    async function loadSourceDropdowns() {
        try {
            // Load referrals
            const refRes = await fetch('api.php?api=get_active_referrals');
            const refs = await refRes.json();
            const refDrop = document.getElementById('referral_dropdown');
            refDrop.innerHTML = '<option value="">-- Select Partner --</option>';
            if(refs && !refs.error) {
                refs.forEach(r => {
                    refDrop.innerHTML += `<option value="${r.referral_id}">${r.full_name} (${r.referral_id})</option>`;
                });
            }

            // Load employees (using get_users api)
            const empRes = await fetch('api.php?api=get_users');
            const emps = await empRes.json();
            const empDrop = document.getElementById('employee_dropdown');
            empDrop.innerHTML = '<option value="">-- Select Employee --</option>';
            if(emps && !emps.error) {
                emps.forEach(u => {
                    if (u.is_active == 1) {
                        empDrop.innerHTML += `<option value="${u.username}">${u.name || u.username}</option>`;
                    }
                });
            }
            initTS('referral_dropdown');
            initTS('employee_dropdown');
        } catch (e) { 
            console.error('Error loading dropdowns', e); 
            initTS('referral_dropdown');
            initTS('employee_dropdown');
        }
    }

    function isLoanCompleted(status) {
        if (!status) return false;
        const s = String(status).trim().toLowerCase();
        return s.includes('complete') || s.includes('disburs') || s.includes('close') || s.includes('approved') || s.includes('sanctioned');
    }

    async function saveApplicant(event, force = false) {
        if(event) event.preventDefault();
        const form = document.getElementById('applicant-registration-form');
        const formData = new FormData(form);
        if(force) {
            formData.append('force_duplicate', '1');
        }
        
        try {
            const response = await fetch('api.php?api=save_applicant', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (response.status === 409 && data.warning === 'duplicate_detected') {
                let matchesList = [];
                if (data.duplicates && Array.isArray(data.duplicates)) {
                    data.duplicates.forEach(dup => {
                        if (dup.matches && Array.isArray(dup.matches)) {
                            dup.matches.forEach(m => {
                                if (!matchesList.some(item => item.id === m.id || item.loan_id === m.loan_id)) {
                                    matchesList.push(m);
                                }
                            });
                        }
                    });
                }

                let cardsHtml = '';
                matchesList.forEach(m => {
                    const loanId = m.loan_id || ('ID #' + m.id);
                    const name = m.customer_name || 'N/A';
                    const mobile = m.mobile || 'N/A';
                    const pan = m.pan_number || 'N/A';
                    const aad = m.aadhar_number || 'N/A';
                    const status = m.overall_status || 'Active';
                    const ltype = m.loan_type ? ` &nbsp;•&nbsp; Loan: <b>${m.loan_type}</b>` : '';
                    const isCompleted = isLoanCompleted(status);
                    const reApplyBtnHtml = isCompleted ? `
                        <button type="button" onclick="Swal.close(); openFastReLoanModal(${m.id}, '${name.replace(/'/g, "\\'")}', '${mobile}', '${pan}', '${loanId}')" style="background:#0f172a; color:#ffffff; border:none; padding:5px 11px; border-radius:6px; font-size:11.5px; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:4px; box-shadow:0 2px 5px rgba(15,23,42,0.2);">
                            ⚡ Fast Re-Apply (Repeat Loan)
                        </button>
                    ` : '';

                    cardsHtml += `
                        <div style="background:#ffffff; border:1px solid #fee2e2; border-left:4px solid #dc2626; border-radius:8px; padding:12px 14px; margin-bottom:10px; text-align:left; box-shadow:0 2px 6px rgba(0,0,0,0.03);">
                            <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                                <div>
                                    <div style="font-weight:800; font-size:14px; color:#0f172a;">${name}</div>
                                    <div style="font-size:12px; color:#dc2626; font-weight:700; margin-top:1px; font-family:monospace;">File ID: ${loanId}</div>
                                </div>
                                <span style="background:#fee2e2; color:#991b1b; font-size:11px; font-weight:700; padding:3px 8px; border-radius:6px;">${status}</span>
                            </div>
                            <div style="font-size:12px; color:#475569; margin-top:6px; line-height:1.5;">
                                <span>Mobile: <b>${mobile}</b></span> &nbsp;•&nbsp; 
                                <span>PAN: <b>${pan}</b></span> ${aad !== 'N/A' ? `&nbsp;•&nbsp; <span>Aadhaar: <b>${aad}</b></span>` : ''}
                                ${ltype}
                            </div>
                            <div style="margin-top:8px; padding-top:8px; border-top:1px solid #fecaca; display:flex; justify-content:flex-end; gap:8px; flex-wrap:wrap;">
                                ${reApplyBtnHtml}
                                <a href="applicant_documents.php?id=${m.id}" target="_blank" style="background:#dc2626; color:#ffffff; text-decoration:none; padding:5px 11px; border-radius:6px; font-size:11.5px; font-weight:700; display:inline-flex; align-items:center; gap:4px;">
                                    Open Registered Profile ↗
                                </a>
                            </div>
                        </div>
                    `;
                });

                Swal.fire({
                    title: '<div style="display:flex; align-items:center; gap:10px; text-align:left;"><div style="width:36px; height:36px; background:#fee2e2; border-radius:8px; display:flex; align-items:center; justify-content:center; flex-shrink:0;"><i data-lucide="shield-alert" style="color:#dc2626; width:20px; height:20px;"></i></div><div><div style="font-size:16px; font-weight:800; color:#991b1b;">Applicant Profile Already Registered</div><div style="font-size:12px; color:#64748b; font-weight:normal;">Matching Mobile, PAN, or Aadhaar found in database</div></div></div>',
                    html: `
                        <div style="max-height:260px; overflow-y:auto; margin-top:12px;">
                            ${cardsHtml}
                        </div>
                        <div style="font-size:12px; color:#7f1d1d; margin-top:10px; background:#fef2f2; padding:10px; border-radius:6px; border:1px solid #fecaca; text-align:left;">
                            <b>Note:</b> You can open the registered profile above or click <b>"Create Additional Application"</b> to proceed with a new file.
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonText: 'Create Additional Loan File',
                    cancelButtonText: 'Cancel',
                    showDenyButton: matchesList.length > 0,
                    denyButtonText: 'Open Registered Profile',
                    confirmButtonColor: '#dc2626',
                    cancelButtonColor: '#64748b',
                    denyButtonColor: '#0f172a',
                    didOpen: () => {
                        if (typeof lucide !== 'undefined') lucide.createIcons();
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        saveApplicant(null, true);
                    } else if (result.isDenied && matchesList.length > 0) {
                        window.open(`applicant_documents.php?id=${matchesList[0].id}`, '_blank');
                    }
                });
                return;
            }

            if (response.ok && data.success) {
                showNotification(data.message, 'success');
                setTimeout(() => {
                    // Redirect to Phase 2: Documents Upload
                    location.href = `applicant_documents.php?id=${data.id}`;
                }, 1500);
            } else {
                showNotification(data.error || 'Registration failed.', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification('Server connection error.', 'error');
        }
    }

    async function loadApplicantDetails(id) {
        try {
            const res = await fetch(`api.php?api=get_applicant&id=${id}`);
            const data = await res.json();
            if(data && !data.error) {
                // First load cities for the applicant's state if present
                if (data.state) {
                    await loadCities(data.state, null, 'city', data.city);
                }

                const populateFields = (obj) => {
                    for (let key in obj) {
                        if (key === 'pd_report_path' || key === 'id' || key === 'applicant_id') continue;
                        const el = document.querySelector(`[name="${key}"]`);
                        if (el) {
                            let val = obj[key];
                            if (key === 'lead_source' && val) {
                                let srcVal = 'Direct / Website';
                                let lowerSrc = val.toLowerCase();
                                if (lowerSrc.includes('referral') || lowerSrc.includes('partner') || lowerSrc.includes('agent')) srcVal = 'Referral Partner / Agent';
                                else if (lowerSrc.includes('employee') || lowerSrc.includes('staff')) srcVal = 'Employee Referral';
                                else if (lowerSrc.includes('builder')) srcVal = 'Builder Tie-up';
                                val = srcVal;
                            }

                            if (el.id && tsInstances[el.id]) {
                                tsInstances[el.id].setValue(val);
                                el.value = val;
                            } else if (el.type !== 'radio' && el.type !== 'checkbox') {
                                el.value = val;
                            }
                        }
                        
                        const radios = document.querySelectorAll(`input[type="radio"][name="${key}"]`);
                        if (radios.length > 0 && obj[key]) {
                            radios.forEach(r => { if (r.value === obj[key]) r.checked = true; });
                        }
                        
                        const multiCheckboxes = document.querySelectorAll(`input[type="checkbox"][name="${key}\[\]"]`);
                        if (multiCheckboxes.length > 0 && obj[key]) {
                            const values = String(obj[key]).split(',').map(s => s.trim());
                            multiCheckboxes.forEach(cb => { if (values.includes(cb.value)) cb.checked = true; });
                        }
                    }
                };
                
                populateFields(data);

                if (data.employment_type) {
                    handleEmploymentTypeChange(data.employment_type);
                }
                if (data.tenure_months) {
                    calcTenureFromMonths(data.tenure_months);
                }
                
                if (data.pd_report && (data.pd_report.pd_conducted_by || data.pd_report.final_pd_status)) {
                    document.getElementById('add_pd_toggle').checked = true;
                    document.getElementById('pd_wrapper').style.display = 'block';
                    populateFields(data.pd_report);
                    if (data.pd_report.pd_report_path) {
                        const linkEl = document.getElementById('pd_report_file_link');
                        if (linkEl) {
                            linkEl.innerHTML = `<a href="${data.pd_report.pd_report_path}" target="_blank" style="color:var(--primary); font-weight: 500; display:inline-block; margin-top: 5px;"><i data-lucide="file-check" style="width:14px;height:14px;vertical-align:middle;"></i> View Existing Uploaded PD Report</a>`;
                            if (typeof lucide !== 'undefined') lucide.createIcons();
                        }
                    }
                }
                
                if (data.pincode && data.pincode.length === 6) {
                    verifyPincode(data.pincode, true);
                }
                
                if (data.loan_type) {
                    updateSubTypes();
                    setTimeout(() => {
                        const subTypeEl = document.getElementById('loan_sub_type');
                        if(subTypeEl) subTypeEl.value = data.loan_sub_type;
                    }, 50);
                }
                
                if (data.lead_source) {
                    toggleSourceFields();
                    setTimeout(() => {
                        let lowerSrc = data.lead_source.toLowerCase();
                        if((lowerSrc.includes('referral') || lowerSrc.includes('partner') || lowerSrc.includes('agent')) && data.referral_id) {
                            if (tsInstances['referral_dropdown']) {
                                tsInstances['referral_dropdown'].setValue(data.referral_id);
                            } else {
                                document.getElementById('referral_dropdown').value = data.referral_id;
                            }
                        } else if((lowerSrc.includes('employee') || lowerSrc.includes('staff')) && data.employee_id) {
                            if (tsInstances['employee_dropdown']) {
                                tsInstances['employee_dropdown'].setValue(data.employee_id);
                            } else {
                                document.getElementById('employee_dropdown').value = data.employee_id;
                            }
                        }
                    }, 600); // Wait a bit longer for async dropdowns to populate
                }

                if (data.co_applicants && data.co_applicants.length > 0) {
                    document.getElementById('has_co_applicant').checked = true;
                    toggleCoApplicantSection();
                    document.getElementById('co_applicants_container').innerHTML = ''; // clear auto-added block
                    coAppCount = 0; // reset
                    
                    for (const co of data.co_applicants) {
                        addCoApplicantBlock();
                        const idx = coAppCount;
                        
                        // Wait for cities to load if needed (state is populated synchronously inside addCoApplicantBlock)
                        if (co.state) {
                            // We need to wait for cities to load to populate the city dropdown
                            await loadCities(co.state, null, `coapp_city_${idx}`, co.city);
                        }
                        
                        document.getElementById(`coapp_rel_${idx}`).value = co.relationship;
                        const block = document.getElementById(`coapp_block_${idx}`);
                        block.querySelector(`[name="coapp_name[]"]`).value = co.full_name;
                        block.querySelector(`[name="coapp_mobile[]"]`).value = co.mobile;
                        block.querySelector(`[name="coapp_email[]"]`).value = co.email;
                        block.querySelector(`[name="coapp_dob[]"]`).value = co.dob;
                        block.querySelector(`[name="coapp_pan[]"]`).value = co.pan_number;
                        block.querySelector(`[name="coapp_aadhar[]"]`).value = co.aadhar_number;
                        
                        if (co.same_address == 1) {
                            document.getElementById(`coapp_same_${idx}`).checked = true;
                            toggleCoAppAddress(idx);
                        } else {
                            block.querySelector(`[name="coapp_address[]"]`).value = co.address;
                            block.querySelector(`[name="coapp_pincode[]"]`).value = co.pincode;
                            block.querySelector(`[name="coapp_state[]"]`).value = co.state;
                        }
                        
                        block.querySelector(`[name="coapp_emp_type[]"]`).value = co.employment_type;
                        block.querySelector(`[name="coapp_income[]"]`).value = co.monthly_income;
                        block.querySelector(`[name="coapp_emis[]"]`).value = co.current_emis;
                        
                        const radios = document.getElementsByName(`coapp_financial_${idx}`);
                        radios.forEach(r => { if(r.value === co.is_financial) r.checked = true; });
                        document.getElementById(`hidden_financial_${idx}`).value = co.is_financial;
                        if (co.is_financial === 'Yes') {
                            document.getElementById(`coapp_financial_block_${idx}`).style.display = 'block';
                        }
                        
                        // Refresh TS for this block
                        if (tsInstances[`coapp_rel_${idx}`]) tsInstances[`coapp_rel_${idx}`].setValue(co.relationship ? co.relationship.trim() : "");
                        if (tsInstances[`coapp_state_${idx}`]) tsInstances[`coapp_state_${idx}`].setValue(co.state);
                        if (tsInstances[`coapp_city_${idx}`]) tsInstances[`coapp_city_${idx}`].setValue(co.city);
                        if (tsInstances[`coapp_emp_${idx}`]) tsInstances[`coapp_emp_${idx}`].setValue(co.employment_type);
                    }
                }
            }
        } catch(e) {
            console.error('Failed to load applicant details', e);
        }
    }

    function openFastReLoanModal(existingId, name, mobile, pan, loanId) {
        const subTypes = {
            'Home Loan': ['Home Purchase', 'Plot Purchase', 'New Flat Purchase', 'Purchase + Construction', 'Home Construction', 'Renovation', 'Balance Transfer (Top-Up)'],
            'Personal Loan': ['Medical Emergency', 'Wedding', 'Travel', 'Debt Consolidation'],
            'Vehicle Loan': ['Two-Wheeler (New)', 'Two-Wheeler (Used)', 'Four-Wheeler (New)', 'Commercial Vehicle'],
            'Gold Loan': ['Standard Gold Jewel Loan', 'Agricultural Gold Loan'],
            'Business Loan': ['Working Capital', 'Equipment Machinery Purchase', 'MSME Expansion', 'Merchant Overdraft'],
            'Education Loan': ['Inland Studies (India)', 'Overseas Studies (Foreign)']
        };

        const updateSubTypesModal = () => {
            const typeEl = document.getElementById('reloan_type');
            const subEl = document.getElementById('reloan_sub_type');
            if (!typeEl || !subEl) return;
            const val = typeEl.value;
            subEl.innerHTML = '';
            if (subTypes[val]) {
                subTypes[val].forEach(st => {
                    const opt = document.createElement('option');
                    opt.value = st;
                    opt.textContent = st;
                    subEl.appendChild(opt);
                });
            }
        };

        const safeName = (name || 'Applicant').replace(/'/g, "\\'");

        Swal.fire({
            title: '<div style="display:flex; align-items:center; gap:10px; text-align:left;"><div style="width:36px; height:36px; background:#0f172a; border-radius:8px; display:flex; align-items:center; justify-content:center; flex-shrink:0;"><i data-lucide="zap" style="color:#38bdf8; width:20px; height:20px;"></i></div><div><div style="font-size:16px; font-weight:800; color:#0f172a;">Fast Re-Loan Application</div><div style="font-size:12px; color:#64748b; font-weight:normal;">Re-using verified KYC & Vault docs for client <b>' + (name || 'Applicant') + '</b></div></div></div>',
            html: `
                <div style="text-align:left; font-family:sans-serif; margin-top:10px;">
                    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:10px 14px; margin-bottom:14px; display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <div style="font-size:13px; font-weight:700; color:#0f172a;">${name || 'Client'}</div>
                            <div style="font-size:11.5px; color:#64748b; margin-top:1px;">Mobile: <b>${mobile || 'N/A'}</b> &nbsp;•&nbsp; PAN: <b>${pan || 'N/A'}</b></div>
                        </div>
                        <span style="background:#0f172a; color:#ffffff; font-size:11px; font-weight:700; padding:3px 8px; border-radius:6px; font-family:monospace;">Past File: ${loanId || 'N/A'}</span>
                    </div>

                    <div style="display:flex; flex-direction:column; gap:12px;">
                        <div>
                            <label style="font-size:12px; font-weight:700; color:#334155; display:block; margin-bottom:4px;">Select New Loan Category *</label>
                            <select id="reloan_type" class="swal2-input" style="margin:0; width:100%; font-size:13px; height:42px; border-radius:6px;" onchange="window._updateReloanSubTypes()">
                                <option value="Home Loan" selected>Home Loan</option>
                                <option value="Personal Loan">Personal Loan</option>
                                <option value="Business Loan">Business Loan</option>
                                <option value="Vehicle Loan">Vehicle Loan</option>
                                <option value="Gold Loan">Gold Loan</option>
                                <option value="Education Loan">Education Loan</option>
                            </select>
                        </div>

                        <div>
                            <label style="font-size:12px; font-weight:700; color:#334155; display:block; margin-bottom:4px;">Select Loan Sub-Type *</label>
                            <select id="reloan_sub_type" class="swal2-input" style="margin:0; width:100%; font-size:13px; height:42px; border-radius:6px;">
                                <option value="Home Purchase">Home Purchase</option>
                                <option value="Plot Purchase">Plot Purchase</option>
                                <option value="New Flat Purchase">New Flat Purchase</option>
                                <option value="Home Construction">Home Construction</option>
                                <option value="Balance Transfer (Top-Up)">Balance Transfer (Top-Up)</option>
                            </select>
                        </div>

                        <div style="display:flex; gap:10px;">
                            <div style="flex:1;">
                                <label style="font-size:12px; font-weight:700; color:#334155; display:block; margin-bottom:4px;">New Loan Amount (₹) *</label>
                                <input type="number" id="reloan_amount" placeholder="e.g. 2500000" class="swal2-input" style="margin:0; width:100%; font-size:13px; height:42px; border-radius:6px;">
                            </div>
                            <div style="flex:1;">
                                <label style="font-size:12px; font-weight:700; color:#334155; display:block; margin-bottom:4px;">Tenure (Years) *</label>
                                <input type="number" id="reloan_years" placeholder="e.g. 10" step="0.5" class="swal2-input" style="margin:0; width:100%; font-size:13px; height:42px; border-radius:6px;">
                            </div>
                        </div>
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: '🚀 Launch New File & Re-Submit to Bank Pipeline',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#09090b',
            cancelButtonColor: '#64748b',
            didOpen: () => {
                window._updateReloanSubTypes = updateSubTypesModal;
                if (typeof lucide !== 'undefined') lucide.createIcons();
            },
            preConfirm: () => {
                const loanType = document.getElementById('reloan_type').value;
                const loanSubType = document.getElementById('reloan_sub_type').value;
                const amount = document.getElementById('reloan_amount').value;
                const years = document.getElementById('reloan_years').value;

                if (!amount || parseFloat(amount) <= 0) {
                    Swal.showValidationMessage('Please enter new requested loan amount');
                    return false;
                }
                if (!years || parseFloat(years) <= 0) {
                    Swal.showValidationMessage('Please enter loan tenure in years');
                    return false;
                }

                return {
                    existing_applicant_id: existingId,
                    loan_type: loanType,
                    loan_sub_type: loanSubType,
                    loan_amount_requested: amount,
                    tenure_years: years
                };
            }
        }).then(async (result) => {
            if (result.isConfirmed && result.value) {
                Swal.fire({
                    title: 'Generating New Loan File...',
                    text: 'Cloning profile details & linking verified KYC documents...',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });

                try {
                    const fd = new FormData();
                    for (let k in result.value) {
                        fd.append(k, result.value[k]);
                    }

                    const res = await fetch('api.php?api=reapply_applicant', {
                        method: 'POST',
                        body: fd
                    });
                    const data = await res.json();

                    if (res.ok && data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '🎉 Re-Loan File Created!',
                            html: `<b>New File ID: ${data.loan_id}</b><br><span style="font-size:13px; color:#64748b;">${data.cloned_docs_count} verified KYC documents linked to new file!</span>`,
                            confirmButtonText: 'Proceed to Bank Submission Pipeline 🚀',
                            confirmButtonColor: '#09090b'
                        }).then(() => {
                            window.location.href = `applicant_documents.php?id=${data.id}`;
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Creation Failed',
                            text: data.error || 'Failed to create re-loan file.',
                            confirmButtonColor: '#09090b'
                        });
                    }
                } catch (err) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Network Error',
                        text: 'Failed to connect to server.',
                        confirmButtonColor: '#09090b'
                    });
                }
            }
        });
    }

    async function checkLiveDuplicateApplicant() {
        const mobileEl = document.querySelector('[name="mobile"]');
        const panEl = document.querySelector('[name="pan_number"]');
        const aadEl = document.querySelector('[name="aadhar_number"]');
        const appId = document.getElementById('applicant_id') ? document.getElementById('applicant_id').value : '';

        if (appId) return; // Only check for new registrations

        const mob = mobileEl ? mobileEl.value.trim() : '';
        const pan = panEl ? panEl.value.trim() : '';
        const aad = aadEl ? aadEl.value.trim() : '';

        if (mob.length !== 10 && pan.length !== 10 && aad.length !== 12) return;

        try {
            const res = await fetch(`api.php?api=check_duplicate_applicant&mobile=${encodeURIComponent(mob)}&pan=${encodeURIComponent(pan)}&aadhar=${encodeURIComponent(aad)}`);
            const data = await res.json();

            let banner = document.getElementById('live-duplicate-banner');

            if (data && data.success && data.is_duplicate && data.duplicates.length > 0) {
                const match = data.duplicates[0];
                const safeName = (match.customer_name || '').replace(/'/g, "\\'");
                const isCompleted = isLoanCompleted(match.overall_status);
                const reApplyBtnHtml = isCompleted ? `
                    <button type="button" onclick="openFastReLoanModal(${match.id}, '${safeName}', '${match.mobile}', '${match.pan_number}', '${match.loan_id}')" style="background: #0f172a; color: #ffffff; border: none; padding: 7px 14px; border-radius: 6px; font-size: 12px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; box-shadow: 0 2px 6px rgba(15,23,42,0.25);">
                        ⚡ Fast Re-Apply (Repeat Loan)
                    </button>
                ` : '';

                if (!banner) {
                    banner = document.createElement('div');
                    banner.id = 'live-duplicate-banner';
                    banner.style.cssText = 'background: #fef2f2; border: 1px solid #fecaca; border-left: 4px solid #dc2626; border-radius: 10px; padding: 14px 18px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; box-shadow: 0 4px 14px rgba(220, 38, 38, 0.08);';
                    const mainCard = document.querySelector('#applicant-registration-form .card') || document.getElementById('applicant-registration-form');
                    if (mainCard) mainCard.insertBefore(banner, mainCard.firstChild);
                }

                banner.innerHTML = `
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div style="width: 36px; height: 36px; background: #fee2e2; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <i data-lucide="shield-alert" style="width: 19px; height: 19px; color: #dc2626;"></i>
                        </div>
                        <div>
                            <div style="font-weight: 800; font-size: 13.5px; color: #991b1b; letter-spacing: -0.01em;">
                                APPLICANT PROFILE ALREADY REGISTERED (${match.matched_by.toUpperCase()})
                            </div>
                            <div style="font-size: 12px; color: #7f1d1d; margin-top: 2px;">
                                Name: <b>${match.customer_name}</b> &nbsp;•&nbsp; File ID: <span style="font-family: monospace; font-weight: 700;">${match.loan_id}</span> &nbsp;•&nbsp; Stage: <b>${match.overall_status || 'Active'}</b>
                            </div>
                        </div>
                    </div>
                    <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                        ${reApplyBtnHtml}
                        <a href="applicant_documents.php?id=${match.id}" target="_blank" style="background: #dc2626; color: #ffffff; text-decoration: none; padding: 7px 14px; border-radius: 6px; font-size: 12px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                            View Profile ↗
                        </a>
                    </div>
                `;
                if (typeof lucide !== 'undefined') lucide.createIcons();
            } else if (banner) {
                banner.remove();
            }
        } catch (e) {
            console.error('Error checking duplicate applicant', e);
        }
    }

    document.addEventListener('DOMContentLoaded', async () => {
        // Force initial display sync for co-applicant section
        toggleCoApplicantSection();
        setTimeout(toggleCoApplicantSection, 100);
        setTimeout(toggleCoApplicantSection, 400);

        await loadStates();
        await loadSourceDropdowns();
        
        // Initialize TS on fixed static dropdowns
        initTS('loan_type');
        initTS('loan_sub_type');
        initTS('lead_source');

        // Attach live duplicate check on key identity fields
        ['mobile', 'pan_number', 'aadhar_number'].forEach(fieldName => {
            const el = document.querySelector(`[name="${fieldName}"]`);
            if (el) {
                el.addEventListener('blur', checkLiveDuplicateApplicant);
                el.addEventListener('change', checkLiveDuplicateApplicant);
            }
        });

        // Hook up change listeners for smart preview
        const empSelect = document.querySelector('[name="employment_type"]');
        if (empSelect) empSelect.addEventListener('change', updateSmartDocPreview);
        const coAppCheck = document.getElementById('has_co_applicant');
        if (coAppCheck) {
            coAppCheck.addEventListener('change', function() {
                toggleCoApplicantSection();
                if (typeof updateSmartDocPreview === 'function') updateSmartDocPreview();
            });
            coAppCheck.addEventListener('click', function() {
                setTimeout(toggleCoApplicantSection, 10);
            });
        }
        const subTypeSelect = document.getElementById('loan_sub_type');
        if (subTypeSelect) subTypeSelect.addEventListener('change', updateSmartDocPreview);
        
        // --- HIGH-PERFORMANCE KEYBOARD CONTROLS & NAVIGATION ENGINE ---
        function getNavigableFormControls() {
            const form = document.getElementById('applicant-registration-form');
            if (!form) return [];

            const rawControls = Array.from(form.querySelectorAll('input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled]), button[type="submit"]'));
            
            return rawControls.filter(el => {
                // If it's a select element managed by TomSelect
                if (el.tagName.toLowerCase() === 'select' && typeof tsInstances !== 'undefined' && tsInstances[el.id]) {
                    const ts = tsInstances[el.id];
                    const wrapper = ts ? ts.wrapper : null;
                    return wrapper && wrapper.offsetWidth > 0 && wrapper.offsetHeight > 0 && getComputedStyle(wrapper).display !== 'none';
                }

                // Check standard element visibility
                if (el.offsetWidth === 0 || el.offsetHeight === 0) return false;
                const style = getComputedStyle(el);
                if (style.display === 'none' || style.visibility === 'hidden') return false;

                // Check parent section visibility (e.g. hidden co-applicant or PD wrappers)
                let parent = el.parentElement;
                while (parent && parent !== form) {
                    if (getComputedStyle(parent).display === 'none') return false;
                    parent = parent.parentElement;
                }

                return true;
            });
        }

        function getCurrentFocusedControl(controls) {
            const active = document.activeElement;
            if (!active) return -1;

            // Check if active element is inside a TomSelect dropdown container
            if (typeof tsInstances !== 'undefined') {
                for (let id in tsInstances) {
                    const ts = tsInstances[id];
                    if (ts && ts.wrapper && (ts.wrapper.contains(active) || (ts.control && ts.control.contains(active)))) {
                        const selectEl = document.getElementById(id);
                        return controls.indexOf(selectEl);
                    }
                }
            }

            return controls.indexOf(active);
        }

        function focusTargetControl(targetEl) {
            if (!targetEl) return;

            if (targetEl.tagName.toLowerCase() === 'select' && typeof tsInstances !== 'undefined' && tsInstances[targetEl.id]) {
                const ts = tsInstances[targetEl.id];
                ts.focus();
                if (ts.wrapper) {
                    ts.wrapper.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            } else {
                targetEl.focus();
                if (typeof targetEl.select === 'function' && targetEl.type !== 'checkbox' && targetEl.type !== 'radio' && targetEl.type !== 'submit') {
                    try { targetEl.select(); } catch(e){}
                }
                targetEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }

            // High-visibility focus indicator
            if (typeof highlightField === 'function') {
                const highlightEl = (targetEl.tagName.toLowerCase() === 'select' && typeof tsInstances !== 'undefined' && tsInstances[targetEl.id]) ? tsInstances[targetEl.id].wrapper : targetEl;
                highlightField(highlightEl);
            }
        }

        document.addEventListener('keydown', function(e) {
            const form = document.getElementById('applicant-registration-form');
            if (!form) return;

            // Prevent intercepting keyboard shortcuts when SweetAlert modal is open
            if (document.querySelector('.swal2-container')) return;

            // 1. Quick Save: Ctrl + S or Cmd + S
            if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S')) {
                e.preventDefault();
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) submitBtn.click();
                else form.requestSubmit();
                return;
            }

            // 2. Focus Top Field: Escape key
            if (e.key === 'Escape') {
                const controls = getNavigableFormControls();
                if (controls.length > 0) {
                    e.preventDefault();
                    focusTargetControl(controls[0]);
                }
                return;
            }

            // 3. Jump Section: Alt + 1..6
            if (e.altKey && e.key >= '1' && e.key <= '6') {
                e.preventDefault();
                const secNum = parseInt(e.key, 10);
                let targetEl = null;
                if (secNum === 1) targetEl = document.querySelector('[name="customer_name"]');
                else if (secNum === 2) targetEl = document.querySelector('[name="pincode"]');
                else if (secNum === 3) targetEl = document.querySelector('[name="pan_number"]');
                else if (secNum === 4) targetEl = document.getElementById('has_co_applicant');
                else if (secNum === 5) targetEl = document.getElementById('add_pd_toggle');
                else if (secNum === 6) targetEl = document.getElementById('loan_type');

                if (targetEl) {
                    focusTargetControl(targetEl);
                }
                return;
            }

            // 4. Enter / Shift + Enter Navigation
            if (e.key === 'Enter') {
                const controls = getNavigableFormControls();
                const currIdx = getCurrentFocusedControl(controls);

                if (currIdx === -1) return; // Focused outside form

                const currentEl = controls[currIdx];

                // If inside textarea, allow standard newline unless Ctrl key is held
                if (currentEl.tagName.toLowerCase() === 'textarea' && !e.ctrlKey) {
                    return;
                }

                // If inside TomSelect dropdown and options list is currently open, let TomSelect handle Enter to pick option
                if (currentEl.tagName.toLowerCase() === 'select' && typeof tsInstances !== 'undefined' && tsInstances[currentEl.id]) {
                    const ts = tsInstances[currentEl.id];
                    if (ts && ts.isOpen) {
                        return; // TomSelect processes option selection
                    }
                }

                // If focused directly on submit button, let standard click submit the form
                if (currentEl.type === 'submit' || currentEl.tagName.toLowerCase() === 'button') {
                    return;
                }

                e.preventDefault();

                const targetIdx = e.shiftKey ? currIdx - 1 : currIdx + 1;
                if (targetIdx >= 0 && targetIdx < controls.length) {
                    focusTargetControl(controls[targetIdx]);
                }
            }
        });

        const id = document.getElementById('applicant_id').value;
        if (id) {
            await loadApplicantDetails(id);
        }
        updateSmartDocPreview();
    });
</script>

<?php require_once 'footer.php'; ?>
