<?php
require_once 'config.php';
require_role(['Admin'], false);

$page_title = 'Add Bank Contact';
$page_subtitle = 'Register a new relationship manager or banker contact';
$hide_main_header = true;
require_once 'header.php';
?>

<script src="assets/js/locations.js"></script>

<div id="view-add-banker" class="view-container" style="width:100%; font-family:'Inter', sans-serif;">
    <form id="banker-registration-form" onsubmit="saveBanker(event)">
        <div class="card" style="border: 1px solid #e4e4e7; border-radius: 14px; box-shadow: 0 4px 24px rgba(0,0,0,0.04); background:#ffffff; padding:28px;">
            
            <!-- Card Executive Monochrome Header -->
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e4e4e7; padding-bottom:20px; margin-bottom:28px; flex-wrap:wrap; gap:14px;">
                <div style="display:flex; align-items:center; gap:14px;">
                    <a href="bankers_list.php" style="background:#09090b; color:#ffffff; width:40px; height:40px; border-radius:10px; display:inline-flex; align-items:center; justify-content:center; text-decoration:none; transition:all 0.2s;" title="Back to Directory">
                        <i data-lucide="arrow-left" style="width:20px;height:20px;"></i>
                    </a>
                    <div>
                        <h2 style="margin:0; font-size:22px; font-weight:800; color:#09090b; letter-spacing:-0.4px;">Add Bank Relationship Manager</h2>
                        <p style="margin:3px 0 0 0; font-size:13px; color:#71717a; font-weight:500;">Register a new relationship manager or banker contact details</p>
                    </div>
                </div>
                <div style="display:flex; align-items:center; gap:10px;">
                    <span style="background:#09090b; color:#ffffff; font-size:12px; font-weight:700; padding:6px 14px; border-radius:20px; display:inline-flex; align-items:center; gap:6px;">
                        <i data-lucide="briefcase" style="width:14px;height:14px;"></i> External Bank Contact
                    </span>
                </div>
            </div>
            
            <!-- SECTION 1: BANK & RM PRIMARY INFORMATION -->
            <div style="background:#fafafa; border:1px solid #e4e4e7; border-radius:10px; padding:20px; margin-bottom:24px;">
                <div style="font-size:12px; font-weight:800; color:#09090b; text-transform:uppercase; letter-spacing:0.8px; margin-bottom:16px; display:flex; align-items:center; gap:8px;">
                    <span style="background:#09090b; color:#fff; width:24px; height:24px; border-radius:6px; display:inline-flex; align-items:center; justify-content:center; font-size:11px;">1</span>
                    <span>Bank & Relationship Manager Primary Info</span>
                </div>
                
                <div style="display:grid; grid-template-columns: 2fr 2fr 1fr; gap:16px; margin-bottom:16px;">
                    <div>
                        <label class="required" style="font-size:12px; font-weight:700; color:#09090b; display:block; margin-bottom:6px;">RM Full Name</label>
                        <input type="text" name="full_name" placeholder="e.g. Rajesh Kumar" required style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; font-weight:600; color:#09090b; box-sizing:border-box;">
                    </div>
                    <div>
                        <label class="required" style="font-size:12px; font-weight:700; color:#09090b; display:block; margin-bottom:6px;">Position / Designation</label>
                        <input type="text" name="designation" placeholder="e.g. Senior Branch Manager" required style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; font-weight:600; color:#09090b; box-sizing:border-box;">
                    </div>
                    <div>
                        <label class="required" style="font-size:12px; font-weight:700; color:#09090b; display:block; margin-bottom:6px;">Account Status</label>
                        <select name="status" required style="width:100%; padding:10px 12px; border:1px solid #09090b; border-radius:6px; font-size:13px; font-weight:700; color:#09090b; box-sizing:border-box;" onchange="this.style.color = this.value === 'Active' ? '#09090b' : '#ef4444';">
                            <option value="Active" selected>🟢 Active</option>
                            <option value="Inactive">🔴 Inactive</option>
                        </select>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
                    <div>
                        <label class="required" style="font-size:12px; font-weight:700; color:#09090b; display:block; margin-bottom:6px;">Bank Type</label>
                        <select name="bank_type" id="bank_type_select" required style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; font-weight:600; color:#09090b; box-sizing:border-box;">
                            <option value="" disabled selected>Select Bank Type</option>
                        </select>
                    </div>
                    <div>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                            <label class="required" style="font-size:12px; font-weight:700; color:#09090b; margin:0;">Bank Name</label>
                            <button type="button" style="font-size:11px; padding:2px 8px; background:#09090b; color:#ffffff; border:none; border-radius:4px; font-weight:700; cursor:pointer;" onclick="toggleCustomBankInput(true)">+ Add New Bank</button>
                        </div>
                        <select name="bank_name" id="bank_name_select" required onchange="handleBankNameChange(this)" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; font-weight:600; color:#09090b; box-sizing:border-box;">
                            <option value="" disabled selected>Select Type First</option>
                        </select>

                        <div id="custom_bank_container" style="display:none; margin-top:10px; background:#ffffff; border:1px solid #18181b; padding:14px; border-radius:8px; box-shadow:0 2px 10px rgba(0,0,0,0.05);">
                            <label style="font-size:12px; font-weight:700; color:#09090b; display:block; margin-bottom:6px;">
                                ✏️ Enter New Custom Bank Name:
                            </label>
                            <div style="display:flex; gap:8px;">
                                <input type="text" id="custom_bank_name" name="custom_bank_name" placeholder="e.g. Bandhan Housing Finance" style="flex:1; padding:9px 12px; border:1px solid #18181b; border-radius:6px; font-size:13px; background:#ffffff; color:#09090b; box-sizing:border-box;">
                                <button type="button" class="btn" style="font-size:12px; padding:6px 14px; background:#18181b; color:#ffffff; border:none; border-radius:6px; cursor:pointer;" onclick="toggleCustomBankInput(false)">Cancel</button>
                            </div>
                            <small style="color:#52525b; font-size:11px; display:block; margin-top:6px; line-height:1.3;">
                                ✨ This new bank will be automatically saved to your Bank Directory for future use.
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION 2: BRANCH LOCATION & ADDRESS -->
            <div style="background:#fafafa; border:1px solid #e4e4e7; border-radius:10px; padding:20px; margin-bottom:24px;">
                <div style="font-size:12px; font-weight:800; color:#09090b; text-transform:uppercase; letter-spacing:0.8px; margin-bottom:16px; display:flex; align-items:center; gap:8px;">
                    <span style="background:#09090b; color:#fff; width:24px; height:24px; border-radius:6px; display:inline-flex; align-items:center; justify-content:center; font-size:11px;">2</span>
                    <span>Branch Location & Address Details</span>
                </div>

                <!-- State -> City -> PIN Code Grid -->
                <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:16px; margin-bottom:16px;">
                    <div>
                        <label class="required" style="font-size:12px; font-weight:700; color:#09090b; display:block; margin-bottom:6px;">State</label>
                        <select name="state" id="state-select" required onchange="updateCities()" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; font-weight:600; color:#09090b; box-sizing:border-box;">
                            <option value="" disabled selected>Select State</option>
                        </select>
                    </div>
                    <div>
                        <label class="required" style="font-size:12px; font-weight:700; color:#09090b; display:block; margin-bottom:6px;">City</label>
                        <select name="city" id="city-select" required style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; font-weight:600; color:#09090b; box-sizing:border-box;">
                            <option value="" disabled selected>Select City first</option>
                        </select>
                    </div>
                    <div>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                            <label class="required" style="font-size:12px; font-weight:700; color:#09090b; margin:0;">PIN Code</label>
                            <span id="pincode-loading" style="display:none; font-size:11px; color:#09090b; font-weight:600;">Fetching...</span>
                        </div>
                        <input type="text" name="pincode" id="pincode-input" placeholder="e.g. 400001" oninput="fetchPincodeDetails(this.value)" maxlength="6" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; font-weight:600; color:#09090b; box-sizing:border-box;">
                    </div>
                </div>

                <!-- IFSC & Address Grid -->
                <div style="display:grid; grid-template-columns: 1fr 2fr; gap:16px;">
                    <div>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                            <label class="required" style="font-size:12px; font-weight:700; color:#09090b; margin:0;">IFSC Code</label>
                            <span id="ifsc-loading" style="display:none; font-size:11px; color:#09090b; font-weight:600;">Fetching...</span>
                        </div>
                        <div style="display:flex; gap:8px;">
                            <input type="text" name="ifsc_code" id="ifsc_code_input" placeholder="e.g. SBIN0001234" pattern="^[A-Z]{4}0[A-Z0-9]{6}$" title="Enter a valid 11-character IFSC Code" required style="text-transform: uppercase; width: 100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; font-weight:700; color:#09090b; box-sizing:border-box;" oninput="if(this.value.length === 11) fetchIfscDetails()" maxlength="11">
                            <select id="branch_dropdown" style="display:none; width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing:border-box;">
                                <option value="">▼ Select Branch</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="required" style="font-size:12px; font-weight:700; color:#09090b; display:block; margin-bottom:6px;">Branch Street Address</label>
                        <input type="text" name="address" id="address_input" placeholder="Local branch street address or building details" required style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; font-weight:600; color:#09090b; box-sizing:border-box;">
                    </div>
                </div>
            </div>

            <!-- SECTION 3: CONTACT & COMMUNICATION -->
            <div style="background:#fafafa; border:1px solid #e4e4e7; border-radius:10px; padding:20px; margin-bottom:24px;">
                <div style="font-size:12px; font-weight:800; color:#09090b; text-transform:uppercase; letter-spacing:0.8px; margin-bottom:16px; display:flex; align-items:center; gap:8px;">
                    <span style="background:#09090b; color:#fff; width:24px; height:24px; border-radius:6px; display:inline-flex; align-items:center; justify-content:center; font-size:11px;">3</span>
                    <span>Contact & Communication Emails</span>
                </div>

                <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:16px;">
                    <div>
                        <label class="required" style="font-size:12px; font-weight:700; color:#09090b; display:block; margin-bottom:6px;">RM Contact Mobile Number</label>
                        <input type="text" name="contact_number" placeholder="Mobile / Direct Line" required style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; font-weight:600; color:#09090b; box-sizing:border-box;">
                    </div>
                    <div>
                        <label class="required" style="font-size:12px; font-weight:700; color:#09090b; display:block; margin-bottom:6px;">Banker RM Official Email</label>
                        <input type="email" name="official_email" placeholder="rm.name@bank.com" required style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; font-weight:600; color:#09090b; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="font-size:12px; font-weight:700; color:#09090b; display:block; margin-bottom:6px;">Bank Nodal / Official Email <span style="font-size:11px; color:#71717a; font-weight:500;">(For System Mails)</span></label>
                        <input type="email" name="bank_official_email" placeholder="nodal.officer@bank.com / branch@bank.com" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; font-weight:600; color:#09090b; box-sizing:border-box;">
                    </div>
                </div>
            </div>

            <!-- SECTION 4: DSA CODE & ONBOARDING DOCUMENTS -->
            <div style="background:#fafafa; border:1px solid #e4e4e7; border-radius:10px; padding:20px; margin-bottom:24px;">
                <div style="font-size:12px; font-weight:800; color:#09090b; text-transform:uppercase; letter-spacing:0.8px; margin-bottom:16px; display:flex; align-items:center; gap:8px;">
                    <span style="background:#09090b; color:#fff; width:24px; height:24px; border-radius:6px; display:inline-flex; align-items:center; justify-content:center; font-size:11px;">4</span>
                    <span>DSA Code & Onboarding Agreement Documents</span>
                </div>

                <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:16px;">
                    <div>
                        <label style="font-size:12px; font-weight:700; color:#09090b; display:block; margin-bottom:6px;">Assigned Bank DSA Code</label>
                        <input type="text" name="dsa_code" placeholder="e.g. DSA-BOB-90812" style="text-transform: uppercase; width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; font-weight:700; color:#09090b; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="font-size:12px; font-weight:700; color:#09090b; display:block; margin-bottom:6px;">Google Drive Onboarding Folder Link</label>
                        <input type="url" name="dsa_documents_drive_link" placeholder="https://drive.google.com/drive/folders/..." style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; font-weight:500; color:#09090b; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="font-size:12px; font-weight:700; color:#09090b; display:block; margin-bottom:6px;">DSA Undertaking / Validity Certificate</label>
                        <input type="file" name="dsa_undertaking_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" style="width:100%; padding:7px 10px; border:1px solid #cbd5e1; border-radius:6px; background:#ffffff; font-size:12px; color:#09090b; box-sizing:border-box;">
                    </div>
                </div>
            </div>

            <!-- SECTION 5: LOAN PORTFOLIO & ELIGIBILITY CRITERIA -->
            <div style="background:#fafafa; border:1px solid #e4e4e7; border-radius:10px; padding:20px; margin-bottom:24px;">
                <div style="font-size:12px; font-weight:800; color:#09090b; text-transform:uppercase; letter-spacing:0.8px; margin-bottom:16px; display:flex; align-items:center; gap:8px;">
                    <span style="background:#09090b; color:#fff; width:24px; height:24px; border-radius:6px; display:inline-flex; align-items:center; justify-content:center; font-size:11px;">5</span>
                    <span>Loan Portfolio & ROI Criteria</span>
                </div>

                <div style="margin-bottom:18px;">
                    <label class="required" style="font-size:12px; font-weight:700; color:#09090b; display:block; margin-bottom:8px;">Loan Categories Handled</label>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <?php 
                        $opts = ['Home Loan', 'Personal Loan', 'Business Loan', 'Mortgage Loan', 'Education Loan', 'Vehicle Loan'];
                        foreach($opts as $opt): 
                        ?>
                        <label style="background:#ffffff; border:1px solid #cbd5e1; border-radius:8px; padding:8px 14px; display:inline-flex; align-items:center; gap:8px; font-size:13px; font-weight:600; color:#09090b; cursor:pointer; user-select:none; transition:all 0.15s;">
                            <input type="checkbox" name="loan_category[]" value="<?php echo $opt; ?>" style="width:16px; height:16px; accent-color:#09090b;"> <?php echo $opt; ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:16px;">
                    <div>
                        <label class="required" style="font-size:12px; font-weight:700; color:#09090b; display:block; margin-bottom:6px;">Min Loan Limit (₹)</label>
                        <input type="number" name="min_loan_limit" placeholder="e.g. 500000" min="0" required style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; font-weight:600; color:#09090b; box-sizing:border-box;">
                    </div>
                    <div>
                        <label class="required" style="font-size:12px; font-weight:700; color:#09090b; display:block; margin-bottom:6px;">Max Loan Limit (₹)</label>
                        <input type="number" name="max_loan_limit" placeholder="e.g. 50000000" min="0" required style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; font-weight:600; color:#09090b; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="font-size:12px; font-weight:700; color:#09090b; display:block; margin-bottom:6px;">Min Interest Rate (Min ROI %)</label>
                        <input type="number" step="0.01" name="min_roi" placeholder="e.g. 8.40" min="0" max="100" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; font-weight:600; color:#09090b; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="font-size:12px; font-weight:700; color:#09090b; display:block; margin-bottom:6px;">Max Interest Rate (Max ROI %)</label>
                        <input type="number" step="0.01" name="max_roi" placeholder="e.g. 14.50" min="0" max="100" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; font-weight:600; color:#09090b; box-sizing:border-box;">
                    </div>
                </div>
            </div>

            <!-- SECTION 6: SERVICEABILITY & COVERAGE AREA -->
            <div style="background:#fafafa; border:1px solid #e4e4e7; border-radius:10px; padding:20px; margin-bottom:24px;">
                <div style="font-size:12px; font-weight:800; color:#09090b; text-transform:uppercase; letter-spacing:0.8px; margin-bottom:16px; display:flex; align-items:center; gap:8px;">
                    <span style="background:#09090b; color:#fff; width:24px; height:24px; border-radius:6px; display:inline-flex; align-items:center; justify-content:center; font-size:11px;">6</span>
                    <span>Serviceability & Coverage Area</span>
                </div>
                
                <div style="display:grid; grid-template-columns: 1fr; gap:16px;">
                    <div>
                        <label class="required" style="font-size:12px; font-weight:700; color:#09090b; display:block; margin-bottom:6px;">Coverage Type</label>
                        <select name="coverage_type" required onchange="toggleProfessionalCoverage(this.value)" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; font-weight:600; color:#09090b; box-sizing:border-box;">
                            <option value="" disabled selected>Select Coverage Area Type</option>
                            <option value="PAN India">🌐 PAN India (All over India)</option>
                            <option value="State Level">🗺️ State Level (Specific States)</option>
                            <option value="City Level">🏙️ City Level (Specific Cities)</option>
                            <option value="Local Radius (KM)">📍 Local Radius (Specific Distance)</option>
                        </select>
                    </div>
                    
                    <div id="professional_coverage_container" style="display:none; background: #ffffff; padding: 18px; border-radius: 8px; border: 1px solid #18181b; width: 100%; box-sizing:border-box;">
                        
                        <!-- Hidden input to store final string for DB -->
                        <input type="hidden" name="coverage_details" id="final_coverage_details">
                        
                        <!-- View for PAN India -->
                        <div id="cov_pan_india" style="display:none; text-align:center; color:#09090b; padding: 10px;">
                            <i data-lucide="globe" style="width:32px; height:32px; margin-bottom:10px;"></i>
                            <div style="font-weight:700; font-size:15px;">Covering all states and cities across India.</div>
                        </div>
                        
                        <!-- View for State Level -->
                        <div id="cov_state_level" style="display:none;">
                            <label style="font-weight:700; margin-bottom:10px; display:block; color:#09090b; font-size:13px;">Select Serviceable States:</label>
                            <div id="states_grid" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap:10px; max-height: 200px; overflow-y: auto; padding-right:10px;">
                                <!-- Populated by JS -->
                            </div>
                        </div>
                        
                        <!-- View for City Level -->
                        <div id="cov_city_level" style="display:none;">
                            <label style="font-weight:700; margin-bottom:10px; display:block; color:#09090b; font-size:13px;">Enter Serviceable Cities:</label>
                            <div style="display:flex; gap:10px; margin-bottom: 15px;">
                                <input type="text" id="city_input" list="all_cities_list" placeholder="Type a city and press Enter..." style="flex:1; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px;">
                                <button type="button" class="btn" onclick="addCityChip()" style="padding: 9px 18px; background:#09090b; color:#ffffff; border:none; border-radius:6px; font-weight:700; font-size:13px; cursor:pointer;">Add</button>
                            </div>
                            <datalist id="all_cities_list"></datalist>
                            <div id="city_chips_container" style="display:flex; flex-wrap:wrap; gap:8px;">
                                <!-- Chips go here -->
                            </div>
                        </div>
                        
                        <!-- View for Local Radius -->
                        <div id="cov_radius" style="display:none;">
                            <label style="font-weight:700; margin-bottom:10px; display:block; color:#09090b; font-size:13px;">Define Local Service Radius:</label>
                            <div style="display:flex; gap:15px;">
                                <div style="flex:1;">
                                    <label style="font-size:12px; color:#71717a; font-weight:600; display:block; margin-bottom:4px;">Distance (in KM)</label>
                                    <input type="number" id="radius_km" placeholder="e.g. 50" min="1" oninput="updateRadiusFinal()" style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px;">
                                </div>
                                <div style="flex:2;">
                                    <label style="font-size:12px; color:#71717a; font-weight:600; display:block; margin-bottom:4px;">Base Location / Pincode</label>
                                    <input type="text" id="radius_base" placeholder="e.g. Mumbai 400001" oninput="updateRadiusFinal()" style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px;">
                                </div>
                            </div>
                            <div id="radius_preview" style="margin-top:15px; font-size:13px; color:#09090b; font-weight:700;"></div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Action panel -->
            <div class="form-actions" style="margin-top: 28px; padding-top:20px; border-top:1px solid #e4e4e7; display:flex; justify-content:flex-end; gap:12px; align-items:center;">
                <button type="reset" class="btn" style="background:#ffffff; color:#09090b; font-weight:600; padding:12px 20px; border-radius:8px; border:1px solid #cbd5e1; cursor:pointer; font-size:13px;">Clear Inputs</button>
                <button type="submit" class="btn" style="background:#09090b; color:#ffffff; font-weight:700; padding:12px 28px; border-radius:8px; border:none; display:inline-flex; align-items:center; gap:8px; cursor:pointer; font-size:14px; box-shadow:0 2px 8px rgba(0,0,0,0.15);">
                    <i data-lucide="save" style="width:16px; height:16px;"></i> Save Banker Details
                </button>
            </div>
        </div>
    </form>
</div>

<script>
    let selectedCities = [];

    document.addEventListener("DOMContentLoaded", () => {
        const stateSelect = document.getElementById('state-select');
        const statesGrid = document.getElementById('states_grid');
        const citiesList = document.getElementById('all_cities_list');
        let allCitiesArray = [];

        Object.keys(locationData).sort().forEach(state => {
            // Populate main form state dropdown
            const opt = document.createElement('option');
            opt.value = state;
            opt.textContent = state;
            stateSelect.appendChild(opt);

            // Populate coverage states grid
            const lbl = document.createElement('label');
            lbl.style.display = 'flex';
            lbl.style.alignItems = 'center';
            lbl.style.gap = '6px';
            lbl.style.fontSize = '13px';
            lbl.style.cursor = 'pointer';
            lbl.innerHTML = `<input type="checkbox" value="${state}" onchange="updateStatesFinal()" style="accent-color:#09090b;"> ${state}`;
            statesGrid.appendChild(lbl);

            // Collect all cities for the datalist
            allCitiesArray = allCitiesArray.concat(locationData[state]);
        });

        // Deduplicate and sort cities for datalist
        allCitiesArray = [...new Set(allCitiesArray)].sort();
        allCitiesArray.forEach(city => {
            const opt = document.createElement('option');
            opt.value = city;
            citiesList.appendChild(opt);
        });

        // Add Enter key listener for city input
        document.getElementById('city_input').addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                addCityChip();
            }
        });
    });

    function updateCities(preselectedCity = null) {
        const stateSelect = document.getElementById('state-select');
        const citySelect = document.getElementById('city-select');
        const selectedState = stateSelect.value;
        
        citySelect.innerHTML = '<option value="" disabled selected>Select City</option>';
        let cityMatched = false;
        if(selectedState && locationData[selectedState]) {
            locationData[selectedState].sort().forEach(city => {
                const opt = document.createElement('option');
                opt.value = city;
                opt.textContent = city;
                if(preselectedCity && city.toUpperCase() === preselectedCity.toUpperCase()) {
                    opt.selected = true;
                    cityMatched = true;
                }
                citySelect.appendChild(opt);
            });
        }
        
        // If preselectedCity exists but not in standard list
        if(preselectedCity && !cityMatched && preselectedCity !== 'OTHER_CITY') {
            const customOpt = document.createElement('option');
            customOpt.value = preselectedCity;
            customOpt.textContent = preselectedCity;
            customOpt.selected = true;
            citySelect.appendChild(customOpt);
        }

        const addCityOpt = document.createElement('option');
        addCityOpt.value = 'OTHER_CITY';
        addCityOpt.textContent = '➕ + Other City (Enter Manually)';
        citySelect.appendChild(addCityOpt);
        
        citySelect.onchange = function() {
            if(this.value === 'OTHER_CITY') {
                const customCity = prompt('Enter City Name:');
                if(customCity && customCity.trim() !== '') {
                    const cleanCity = customCity.trim();
                    const newOpt = document.createElement('option');
                    newOpt.value = cleanCity;
                    newOpt.textContent = cleanCity;
                    newOpt.selected = true;
                    citySelect.appendChild(newOpt);
                    citySelect.value = cleanCity;
                } else {
                    citySelect.value = '';
                }
            }
            if(typeof loadBranches === 'function') loadBranches();
        };
    }

    function toggleProfessionalCoverage(type) {
        document.getElementById('professional_coverage_container').style.display = 'block';
        
        document.getElementById('cov_pan_india').style.display = 'none';
        document.getElementById('cov_state_level').style.display = 'none';
        document.getElementById('cov_city_level').style.display = 'none';
        document.getElementById('cov_radius').style.display = 'none';

        const finalInput = document.getElementById('final_coverage_details');

        if (type === 'PAN India') {
            document.getElementById('cov_pan_india').style.display = 'block';
            finalInput.value = 'All Over India';
        } else if (type === 'State Level') {
            document.getElementById('cov_state_level').style.display = 'block';
            updateStatesFinal();
        } else if (type === 'City Level') {
            document.getElementById('cov_city_level').style.display = 'block';
            updateCitiesFinal();
        } else if (type === 'Local Radius (KM)') {
            document.getElementById('cov_radius').style.display = 'block';
            updateRadiusFinal();
        }
    }

    function updateStatesFinal() {
        const checked = Array.from(document.querySelectorAll('#states_grid input:checked')).map(cb => cb.value);
        document.getElementById('final_coverage_details').value = checked.join(', ');
    }

    function addCityChip() {
        const input = document.getElementById('city_input');
        const val = input.value.trim();
        if(val && !selectedCities.includes(val)) {
            selectedCities.push(val);
            renderCityChips();
            input.value = '';
        }
    }

    function removeCityChip(city) {
        selectedCities = selectedCities.filter(c => c !== city);
        renderCityChips();
    }

    function renderCityChips() {
        const container = document.getElementById('city_chips_container');
        container.innerHTML = '';
        selectedCities.forEach(city => {
            const chip = document.createElement('div');
            chip.style.background = '#09090b';
            chip.style.color = '#ffffff';
            chip.style.padding = '5px 12px';
            chip.style.borderRadius = '16px';
            chip.style.fontSize = '13px';
            chip.style.display = 'flex';
            chip.style.alignItems = 'center';
            chip.style.gap = '6px';
            chip.innerHTML = `<span>${city}</span> <i data-lucide="x" style="width:14px;height:14px;cursor:pointer;" onclick="removeCityChip('${city}')"></i>`;
            container.appendChild(chip);
        });
        lucide.createIcons();
        updateCitiesFinal();
    }

    function updateCitiesFinal() {
        document.getElementById('final_coverage_details').value = selectedCities.join(', ');
    }

    function updateRadiusFinal() {
        const km = document.getElementById('radius_km').value;
        const base = document.getElementById('radius_base').value;
        const finalInput = document.getElementById('final_coverage_details');
        const preview = document.getElementById('radius_preview');
        
        if(km && base) {
            const str = `${km} KM radius from ${base}`;
            finalInput.value = str;
            preview.innerText = `Final Area: ${str}`;
        } else {
            finalInput.value = '';
            preview.innerText = '';
        }
    }

    function handleBankNameChange(select) {
        const customContainer = document.getElementById('custom_bank_container');
        const customInput = document.getElementById('custom_bank_name');
        if (!customContainer || !customInput) return;

        if (select.value === 'OTHER_CUSTOM' || select.value === 'Other (Manual Entry)') {
            customContainer.style.display = 'block';
            customInput.required = true;
            customInput.focus();
        } else {
            customContainer.style.display = 'none';
            customInput.required = false;
        }
    }

    function toggleCustomBankInput(show) {
        const nameSelect = document.getElementById('bank_name_select');
        const customContainer = document.getElementById('custom_bank_container');
        const customInput = document.getElementById('custom_bank_name');
        
        if (show) {
            if (nameSelect) nameSelect.value = 'OTHER_CUSTOM';
            if (customContainer) customContainer.style.display = 'block';
            if (customInput) {
                customInput.required = true;
                customInput.focus();
            }
        } else {
            if (nameSelect) nameSelect.value = '';
            if (customContainer) customContainer.style.display = 'none';
            if (customInput) {
                customInput.required = false;
                customInput.value = '';
            }
        }
    }

    async function saveBanker(event) {
        event.preventDefault();
        const form = document.getElementById('banker-registration-form');
        
        let bankName = document.getElementById('bank_name_select').value;
        const bankType = document.getElementById('bank_type_select').value;
        const customInput = document.getElementById('custom_bank_name');
        
        if (bankName === 'OTHER_CUSTOM' || bankName === 'Other (Manual Entry)' || (customInput && customInput.value.trim() !== '')) {
            const enteredBank = customInput ? customInput.value.trim() : '';
            if (enteredBank) bankName = enteredBank;

            if (!bankName) {
                showNotification('Please enter the custom bank name.', 'error');
                return;
            }
            
            const fd = new FormData();
            fd.append('bank_type', bankType);
            fd.append('bank_name', bankName);
            
            try {
                const res = await fetch('api.php?api=add_custom_bank', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success && data.message !== 'Already exists') {
                    showNotification('New bank saved to directory permanently!', 'info');
                }
            } catch (err) {
                console.error('Connection failure while saving custom bank.', err);
            }
        }
        
        // Ensure at least one category is selected
        const checkboxes = form.querySelectorAll('input[name="loan_category[]"]:checked');
        if(checkboxes.length === 0) {
            showNotification('Please select at least one Loan Category.', 'error');
            return;
        }

        // Ensure coverage details is not empty unless it's PAN India
        const coverageType = form.querySelector('select[name="coverage_type"]').value;
        const finalCov = document.getElementById('final_coverage_details').value;
        if(!finalCov) {
            showNotification(`Please specify details for ${coverageType} coverage.`, 'warning');
            return;
        }

        const formData = new FormData(form);
        if (bankName) formData.set('bank_name', bankName);
        
        try {
            const response = await fetch('api.php?api=add_banker', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (response.ok && data.success) {
                showNotification(data.message, 'success');
                form.reset();
                setTimeout(() => {
                    location.href = 'bankers_list.php';
                }, 1000);
            } else {
                showNotification(data.error || 'Registration failed.', 'error');
            }
        } catch (err) {
            showNotification('Connection failure.', 'error');
        }
    }

    // Initialize Bank Dropdowns
    document.write('<script src="assets/js/banks_directory.js"><\/script>');
    setTimeout(() => {
        populateBankDropdowns('bank_type_select', 'bank_name_select');
    }, 100);

    // Auto-fill via IFSC from Local Master Table
    async function fetchIfscDetails() {
        const ifsc = document.getElementById('ifsc_code_input').value.trim();
        if (!ifsc || ifsc.length !== 11) {
            return;
        }
        
        document.getElementById('ifsc-loading').style.display = 'inline';
        try {
            const res = await fetch(`api.php?api=fetch_ifsc&ifsc=${ifsc}`);
            const data = await res.json();
            
            if (data.success && data.data) {
                const info = data.data;
                // Update State and City dropdowns dynamically
                const stateSelect = document.getElementById('state-select');
                
                // Find and select state case-insensitively
                Array.from(stateSelect.options).forEach(opt => {
                    if (opt.text.toUpperCase() === info.state.toUpperCase()) {
                        opt.selected = true;
                    }
                });
                
                updateCities(); // Populates cities for this state
                
                setTimeout(() => {
                    const citySelect = document.getElementById('city-select');
                    const normalizedCity = info.city.toUpperCase();
                    let matchFound = false;
                    
                    // First pass: exact match
                    Array.from(citySelect.options).forEach(opt => {
                        if (opt.value.toUpperCase() === normalizedCity) {
                            citySelect.value = opt.value;
                            matchFound = true;
                        }
                    });
                    
                    // Second pass: includes
                    if (!matchFound) {
                        Array.from(citySelect.options).forEach(opt => {
                            if (opt.value.toUpperCase().includes(normalizedCity) || normalizedCity.includes(opt.value.toUpperCase())) {
                                citySelect.value = opt.value;
                            }
                        });
                    }
                    
                    // Populate address
                    const addrInput = document.querySelector('input[name="address"]');
                    if (addrInput) addrInput.value = info.branch + ", " + info.address;
                    
                    // Try to auto-select bank type and name if they exist in directory
                    if (info.bank) {
                        const bankNameInput = document.getElementById('bank_name_select');
                        const bankTypeInput = document.getElementById('bank_type_select');
                        
                        let foundType = null;
                        let foundName = null;
                        for (const type in BANK_DIRECTORY) {
                            const match = BANK_DIRECTORY[type].find(b => info.bank.toUpperCase().includes(b.toUpperCase()) || b.toUpperCase().includes(info.bank.toUpperCase()));
                            if (match) {
                                foundType = type;
                                foundName = match;
                                break;
                            }
                        }
                        
                        if (foundType) {
                            bankTypeInput.value = foundType;
                            // Trigger change to populate bank names
                            bankTypeInput.dispatchEvent(new Event('change'));
                            setTimeout(() => {
                                bankNameInput.value = foundName;
                            }, 50);
                        }
                    }
                    
                    showNotification('Bank details auto-filled successfully!', 'success');
                    document.getElementById('ifsc-loading').style.display = 'none';
                }, 100);
            } else {
                showNotification('IFSC not found. Please fill manually.', 'error');
                document.getElementById('ifsc-loading').style.display = 'none';
            }
        } catch (e) {
            showNotification('Error fetching IFSC details.', 'error');
            document.getElementById('ifsc-loading').style.display = 'none';
        }
    }

    async function fetchPincodeDetails(pincode) {
        if (pincode.length === 6) {
            document.getElementById('pincode-loading').style.display = 'inline';
            try {
                const response = await fetch(`https://api.postalpincode.in/pincode/${pincode}`);
                const data = await response.json();
                
                if (data[0].Status === 'Success') {
                    const state = data[0].PostOffice[0].State;
                    const district = data[0].PostOffice[0].District;
                    
                    const stateSelect = document.getElementById('state-select');
                    const citySelect = document.getElementById('city-select');
                    
                    // Match and select state
                    Array.from(stateSelect.options).forEach(opt => {
                        if (opt.text.toLowerCase() === state.toLowerCase()) {
                            opt.selected = true;
                            updateCities(); // Populates cities for the selected state
                        }
                    });
                    
                    // Allow cities to populate then select city
                    setTimeout(() => {
                        let matchFound = false;
                        
                        // First pass: Try exact match
                        Array.from(citySelect.options).forEach(opt => {
                            if (opt.text.toLowerCase() === district.toLowerCase()) {
                                opt.selected = true;
                                matchFound = true;
                            }
                        });
                        
                        // Second pass: Try includes/startsWith if no exact match
                        if (!matchFound) {
                            Array.from(citySelect.options).forEach(opt => {
                                if (opt.text.toLowerCase().includes(district.toLowerCase()) || district.toLowerCase().includes(opt.text.toLowerCase())) {
                                    opt.selected = true;
                                }
                            });
                        }
                        
                        document.getElementById('pincode-loading').style.display = 'none';
                        
                        // Automatically load branches now that City is selected!
                        if (typeof loadBranches === 'function') {
                            loadBranches();
                        }
                    }, 100);
                    
                } else {
                    document.getElementById('pincode-loading').style.display = 'none';
                }
            } catch (error) {
                console.error('Error fetching pincode details:', error);
                document.getElementById('pincode-loading').style.display = 'none';
            }
        }
    }

    async function loadBranches() {
        const bank = document.getElementById('bank_name_select').value;
        const city = document.getElementById('city-select').value;
        const dropdown = document.getElementById('branch_dropdown');
        const ifscInput = document.getElementById('ifsc_code_input');
        
        if (!bank || !city || bank === "" || city === "") {
            dropdown.innerHTML = '<option value="">▼ Select Branch</option>';
            dropdown.style.display = 'none';
            ifscInput.style.display = 'block';
            return;
        }

        try {
            const res = await fetch(`api.php?api=get_branches&bank=${encodeURIComponent(bank)}&city=${encodeURIComponent(city)}`);
            const data = await res.json();
            
            dropdown.innerHTML = '<option value="">▼ Select Branch</option>';
            dropdown.innerHTML += '<option value="MANUAL">+ Branch Not Listed (Enter Manually)</option>';
            
            if (data.success && data.data && data.data.length > 0) {
                data.data.forEach(b => {
                    const opt = document.createElement('option');
                    opt.value = b.ifsc;
                    opt.textContent = `${b.branch} - ${b.address.substring(0, 40)}`;
                    dropdown.appendChild(opt);
                });
                dropdown.style.display = 'block';
                ifscInput.style.display = 'none'; // Hide manual input to encourage dropdown usage
                
                dropdown.onchange = function() {
                    if (this.value === 'MANUAL') {
                        dropdown.style.display = 'none';
                        ifscInput.style.display = 'block';
                        ifscInput.value = '';
                        ifscInput.focus();
                    } else if (this.value !== '') {
                        ifscInput.value = this.value;
                        fetchIfscDetails();
                    }
                };
            } else {
                dropdown.style.display = 'none';
                ifscInput.style.display = 'block';
            }
        } catch (e) {
            console.error('Error fetching branches:', e);
        }
    }

    // Attach listeners for dynamic IFSC fetching
    document.getElementById('bank_name_select').addEventListener('change', loadBranches);
    document.getElementById('city-select').addEventListener('change', loadBranches);

</script>

<?php require_once 'footer.php'; ?>
