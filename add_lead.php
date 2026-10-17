<?php
require_once 'config.php';
require_role(['Admin', 'RM']);


$page_title = 'Lead Capture Form';
$page_subtitle = ' Advanced Data Entry for New Leads';
$hide_main_header = true;
require_once 'header.php';

// Fetch all staff members for assignment dropdowns
$staff_members = $db->query("SELECT * FROM users WHERE is_active = 1")->fetchAll();
?>

<div id="view-add-lead" class="view-container">
    <form id="lead-capture-form" onsubmit="submitLeadCapture(event)" enctype="multipart/form-data">
        <!-- Hidden input to store JSON for dynamic source data -->
        <input type="hidden" name="source_data" id="source_data" value="{}">
        <input type="hidden" name="stage" value="New Lead">
        
        <div class="card">
            <div class="card-title-bar" style="display:flex; justify-content:space-between; align-items:center;">
                <div style="display:flex; align-items:center; gap:15px;">
                    <a href="leads.php" class="btn btn-secondary" style="padding: 6px 12px; background: transparent; border: 1px solid var(--border-color); color: var(--text-dark);"><i data-lucide="arrow-left" style="width:16px;height:16px;margin-right:4px;"></i> Back to Leads</a>
                    <h2 style="margin:0;">Lead Capture Form</h2>
                </div>
                <div class="badge-locked"><i data-lucide="target"></i> Prospect Entry</div>
            </div>
            
            <!-- Section A: Basic Prospect Details -->
            <div class="form-section-title"> Section A: Basic Prospect Details</div>
            <div class="form-grid" style="background:#f8fafc; padding:20px; border-radius:8px; border:1px solid #e2e8f0; margin-bottom: 25px;">
                <div class="form-group">
                    <label class="required">Prospect / Client Name</label>
                    <input type="text" name="lead_name" placeholder="e.g. Ramesh Kumar" required>
                </div>
                
                <div class="form-group">
                    <label class="required">Primary Mobile Number</label>
                    <input type="tel" name="mobile" placeholder="10-digit primary number" required pattern="[0-9]{10}" maxlength="10" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                </div>

                <div class="form-group">
                    <label>Secondary / Alternate Number</label>
                    <input type="tel" name="secondary_mobile" placeholder="10-digit secondary number" pattern="[0-9]{10}" maxlength="10" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" placeholder="client@example.com">
                </div>

                <div class="form-group">
                    <label>City & Location</label>
                    <input type="text" name="location" placeholder="e.g. Agra (Sikandra)">
                </div>

                <div class="form-group">
                    <label class="required">Loan Type</label>
                    <select name="loan_type" id="loan_type" required onchange="updateSubTypes()">
                <option value="">-- Select Loan Type --</option>
                <option value="Home Loan">Home Loan</option>
                <option value="Loan Against Property (LAP)">Loan Against Property (LAP)</option>
                <option value="Business Loan">Business Loan</option>
                <option value="Personal Loan">Personal Loan</option>
                <option value="Auto / Vehicle Loan">Auto / Vehicle Loan</option>
                <option value="Commercial Property Loan">Commercial Property Loan</option>
                <option value="Professional Loan">Professional Loan</option>
            </select>
                </div>
                
                <div class="form-group">
                    <label class="required">Loan Sub Type</label>
                    <select name="loan_sub_type" id="loan_sub_type" required>
                        <option value="">-- Select Sub Type --</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Tentative Loan Amount Required (₹)</label>
                    <input type="number" name="loan_amount" placeholder="e.g. 5000000" min="0" step="10000">
                </div>
            </div>

            <!-- Section B: Lead Source Mapping -->
            <div class="form-section-title"> Section B: Lead Source Mapping</div>
            <div class="form-grid" style="background:#fff7ed; padding:20px; border-radius:8px; border:1px solid #ffedd5; margin-bottom: 25px;">
                
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label class="required" style="color:var(--primary);">Primary Lead Source Type</label>
                    <select name="lead_source" id="lead_source" required onchange="toggleSourceFields()" style="border-color:var(--primary); box-shadow: 0 0 0 2px rgba(249, 115, 22, 0.1);">
                        <option value="">-- Select Source --</option>
                        <option value="Builder / Contractor Site Visit">Builder / Contractor Site Visit</option>
                        <option value="Justdial / Paid Ad Portal">Justdial / Paid Ad Portal</option>
                        <option value="Social Media / Website Inquiry">Social Media / Website Inquiry</option>
                        <option value="Cold Calling / Direct Field Inquiry">Cold Calling / Direct Field Inquiry</option>
                        <option value="Referral Partner / DSA">Referral Partner / DSA</option>
                    </select>
                </div>

                <!-- Dynamic Container for Builder Fields -->
                <div id="builder_fields" style="display:none; grid-column: 1 / -1; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>Builder / Firm Name</label>
                        <input type="text" id="sd_builder_name" placeholder="e.g. Apex Builders">
                    </div>
                    <div class="form-group">
                        <label>Project / Site Location</label>
                        <input type="text" id="sd_project_location" placeholder="e.g. Taj View Apartments">
                    </div>
                    <div class="form-group">
                        <label>Visited By (Field Employee)</label>
                        <select id="sd_visited_by">
                            <option value="">-- Select Employee --</option>
                            <?php foreach($staff_members as $staff): ?>
                                <option value="<?php echo htmlspecialchars($staff['name']); ?>"><?php echo htmlspecialchars($staff['name']); ?> (<?php echo htmlspecialchars($staff['username']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Dynamic Container for Ad/Web Fields -->
                <div id="digital_fields" style="display:none; grid-column: 1 / -1; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>Campaign / Source Name</label>
                        <select id="sd_campaign_name">
                            <option value="">-- Select Campaign --</option>
                            <option value="Justdial Agra">Justdial Agra</option>
                            <option value="Meta Ad - Home Loans">Meta Ad - Home Loans</option>
                            <option value="Google Search">Google Search</option>
                            <option value="Website Contact Form">Website Contact Form</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Auto-Assign To Telecaller</label>
                        <select name="assigned_to" id="sd_assigned_to">
                            <option value="">-- Let Admin Assign Later --</option>
                            <?php foreach($staff_members as $staff): ?>
                                <option value="<?php echo htmlspecialchars($staff['username']); ?>"><?php echo htmlspecialchars($staff['name']); ?> (<?php echo htmlspecialchars($staff['username']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Referral Fields -->
                <div id="referral_fields" style="display:none; grid-column: 1 / -1; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>Referral Partner Name / DSA</label>
                        <input type="text" id="sd_referral_partner" placeholder="Enter partner name">
                    </div>
                </div>
            </div>

            <!-- Notes & Priority -->
            <div class="form-section-title"> Additional Details</div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Lead Priority</label>
                    <select name="priority">
                        <option value="Hot"> Hot (Immediate)</option>
                        <option value="Warm" selected> Warm (Standard)</option>
                        <option value="Cold"> Cold (Future)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Internal Notes / Remarks</label>
                    <textarea name="notes" rows="2" placeholder="Any special notes about this prospect..."></textarea>
                </div>
            </div>

            <!-- Action panel -->
            <div class="form-actions" style="margin-top: 30px;">
                <button type="reset" class="btn btn-secondary" onclick="setTimeout(toggleSourceFields, 10)">Clear Form</button>
                <button type="submit" class="btn btn-primary" style="padding: 12px 24px; font-size: 16px;"><i data-lucide="check-circle"></i> Save Lead Entry</button>
            </div>
        </div>
    </form>
<script>
function previewPhoto(event) {
    const file = event.target.files[0];
    if(file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = document.getElementById('photo-preview');
            img.src = e.target.result;
            img.style.display = 'block';
        }
        reader.readAsDataURL(file);
    }
}
</script>

</div>

<script>
const loanData = {
    "Home Loan": [
        "Home Purchase",
        "New Home Purchase", 
        "Balance Transfer & Top-up", 
        "Plot Purchase & Construction", 
        "Home Improvement / Renovation"
    ],
    "Loan Against Property (LAP)": [
        "Residential LAP", 
        "Commercial LAP", 
        "Industrial LAP", 
        "Lease Rental Discounting (LRD)"
    ],
    "Business Loan": [
        "Unsecured Business Loan", 
        "Machinery / Equipment Loan", 
        "Working Capital (CC/OD)", 
        "CGTMSE / Mudra Loan"
    ],
    "Personal Loan": [
        "Personal Loan for Salaried", 
        "Personal Loan for Self Employed", 
        "Medical Emergency Loan", 
        "Wedding Loan"
    ],
    "Auto / Vehicle Loan": [
        "New Car Loan", 
        "Used Car Loan", 
        "Commercial Vehicle Loan"
    ],
    "Commercial Property Loan": [
        "Office Space Purchase", 
        "Shop / Showroom Purchase", 
        "Warehouse / Godown Finance"
    ],
    "Professional Loan": [
        "Doctor Loan",
        "Chartered Accountant (CA) Loan",
        "Company Secretary (CS) Loan"
    ]
};

function updateSubTypes() {
    const typeSelect = document.getElementById('loan_type');
    const subTypeSelect = document.getElementById('loan_sub_type');
    
    subTypeSelect.innerHTML = '<option value="">-- Select Sub Type --</option>';
    
    const selectedType = typeSelect.value;
    if (selectedType && loanData[selectedType]) {
        loanData[selectedType].forEach(subType => {
            const option = document.createElement('option');
            option.value = subType;
            option.textContent = subType;
            subTypeSelect.appendChild(option);
        });
    }
}

    function toggleSourceFields() {
        const source = document.getElementById('lead_source').value;
        const builderFields = document.getElementById('builder_fields');
        const digitalFields = document.getElementById('digital_fields');
        const referralFields = document.getElementById('referral_fields');

        // Hide all initially
        builderFields.style.display = 'none';
        digitalFields.style.display = 'none';
        referralFields.style.display = 'none';

        if (source === 'Builder / Contractor Site Visit') {
            builderFields.style.display = 'grid';
        } else if (source === 'Justdial / Paid Ad Portal' || source === 'Social Media / Website Inquiry') {
            digitalFields.style.display = 'grid';
        } else if (source === 'Referral Partner / DSA') {
            referralFields.style.display = 'grid';
        }
    }

    // Call once on load to ensure correct state
    toggleSourceFields();

    async function submitLeadCapture(e) {
        e.preventDefault();
        
        // Build the source_data JSON
        let sourceData = {};
        const source = document.getElementById('lead_source').value;
        
        if (source === 'Builder / Contractor Site Visit') {
            sourceData.builder_name = document.getElementById('sd_builder_name').value;
            sourceData.project_location = document.getElementById('sd_project_location').value;
            sourceData.visited_by = document.getElementById('sd_visited_by').value;
        } else if (source === 'Justdial / Paid Ad Portal' || source === 'Social Media / Website Inquiry') {
            sourceData.campaign_name = document.getElementById('sd_campaign_name').value;
            // assigned_to is natively handled by the select with name="assigned_to" inside digital_fields
        } else if (source === 'Referral Partner / DSA') {
            sourceData.referral_partner = document.getElementById('sd_referral_partner').value;
        }
        
        document.getElementById('source_data').value = JSON.stringify(sourceData);

        const form = document.getElementById('lead-capture-form');
        const fd = new FormData(form);
        
        try {
            const res = await fetch('api.php?api=save_lead', {
                method: 'POST',
                body: fd
            });
            const data = await res.json();
            
            if (data.success) {
                Swal.fire({
                    title: 'Success!',
                    text: data.message,
                    icon: 'success',
                    confirmButtonText: 'View Leads'
                }).then(() => {
                    window.location.href = 'leads.php';
                });
            } else {
                Swal.fire('Error', data.error || 'Failed to save lead', 'error');
            }
        } catch(err) {
            Swal.fire('Error', 'A system error occurred.', 'error');
        }
    }
</script>

<?php require_once 'footer.php'; ?>
