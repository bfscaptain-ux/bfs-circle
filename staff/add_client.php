<?php
require_once __DIR__ . '/../config.php';
$page_title = 'Register Client Account';
$page_subtitle = '🔒 Lock-in customer profile parameters permanently';
require_once __DIR__ . '/header.php';
?>

<div id="view-add-client" class="view-container">
    <form id="client-registration-form" onsubmit="saveClient(event)">
        <div class="card">
            <div class="card-title-bar">
                <h2>Add New Client Account</h2>
                <div class="badge-locked"><i data-lucide="lock"></i> Locked Entry — Permanent Record</div>
            </div>
            
            <!-- Business Information -->
            <div class="form-section-title">🏢 Business Information</div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="required">Business / Company Name</label>
                    <input type="text" name="company_name" placeholder="e.g. Acme Corporation" required>
                </div>
                <div class="form-group">
                    <label class="required">Business Type</label>
                    <select name="business_type" required>
                        <option value="" disabled selected>Select Business Type</option>
                        <option value="Manufacturer">Manufacturer</option>
                        <option value="Trader">Trader</option>
                        <option value="Retailer">Retailer</option>
                        <option value="Service">Service Provider</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Industry Sector</label>
                    <input type="text" name="industry_sector" placeholder="e.g. Automobile, Chemical">
                </div>
                <div class="form-group">
                    <label>GSTIN Number</label>
                    <input type="text" name="gstin" placeholder="15-digit GSTIN ID" minlength="15" maxlength="15">
                </div>
                <div class="form-group">
                    <label>PAN Number</label>
                    <input type="text" name="pan" placeholder="10-digit PAN ID" minlength="10" maxlength="10">
                </div>
                <div class="form-group">
                    <label>Website URL</label>
                    <input type="url" name="website" placeholder="https://example.com">
                </div>
                <div class="form-group">
                    <label>Annual Turnover (approx.)</label>
                    <select name="turnover">
                        <option value="" disabled selected>Select Turnover Tier</option>
                        <option value="Under Rs. 1 Crore">Under Rs. 1 Crore</option>
                        <option value="Rs. 1-5 Crores">Rs. 1-5 Crores</option>
                        <option value="Rs. 5-10 Crores">Rs. 5-10 Crores</option>
                        <option value="Rs. 10-25 Crores">Rs. 10-25 Crores</option>
                        <option value="Rs. 25-50 Crores">Rs. 25-50 Crores</option>
                        <option value="Rs. 50+ Crores">Rs. 50+ Crores</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>No. of Employees</label>
                    <input type="number" name="employees" placeholder="e.g. 80">
                </div>
            </div>

            <!-- Contact Person details -->
            <div class="form-section-title">👨‍💼 Contact Person Details</div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="required">Contact Person Name</label>
                    <input type="text" name="contact_name" placeholder="Full legal name" required>
                </div>
                <div class="form-group">
                    <label>Designation / Role</label>
                    <input type="text" name="designation" placeholder="e.g. Purchase Head, Manager">
                </div>
                <div class="form-group">
                    <label class="required">Mobile Number</label>
                    <input type="text" name="mobile" placeholder="10-digit mobile code" required>
                </div>
                <div class="form-group">
                    <label>WhatsApp Number</label>
                    <input type="text" name="whatsapp" placeholder="WhatsApp contact info">
                </div>
                <div class="form-group">
                    <label class="required">Email Address</label>
                    <input type="email" name="email" placeholder="official@company.com" required>
                </div>
                <div class="form-group">
                    <label>Alternate Email</label>
                    <input type="email" name="alternate_email" placeholder="backup@company.com">
                </div>
                <div class="form-group">
                    <label>LinkedIn Profile</label>
                    <input type="url" name="linkedin" placeholder="LinkedIn Profile URL">
                </div>
            </div>

            <!-- Address details -->
            <div class="form-section-title">📍 Address Details</div>
            <div class="form-grid">
                <div class="form-group full-width">
                    <label class="required">Address Line 1</label>
                    <input type="text" name="address_line1" placeholder="Plot No, Street name, Area" required>
                </div>
                <div class="form-group full-width">
                    <label>Address Line 2</label>
                    <input type="text" name="address_line2" placeholder="Suite, floor, landmark">
                </div>
                <div class="form-group">
                    <label class="required">City</label>
                    <input type="text" name="city" placeholder="City name" required>
                </div>
                <div class="form-group">
                    <label class="required">State</label>
                    <input type="text" name="state" placeholder="State/UT name" required>
                </div>
                <div class="form-group">
                    <label class="required">Pincode</label>
                    <input type="text" name="pincode" placeholder="6-digit postal pincode" required>
                </div>
                <div class="form-group">
                    <label class="required">Country</label>
                    <input type="text" name="country" value="India" required>
                </div>
            </div>

            <!-- Bank Details -->
            <div class="form-section-title">🏦 Bank Details (Optional)</div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Bank Name</label>
                    <input type="text" name="bank_name" placeholder="e.g. HDFC Bank">
                </div>
                <div class="form-group">
                    <label>Account Number</label>
                    <input type="text" name="account_number" placeholder="Bank savings/current account no.">
                </div>
                <div class="form-group">
                    <label>IFSC Code</label>
                    <input type="text" name="ifsc_code" placeholder="11-character IFSC Code" minlength="11" maxlength="11">
                </div>
            </div>

            <!-- Other metadata info -->
            <div class="form-section-title">📝 Other Info</div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="required">Lead Source</label>
                    <select name="lead_source" required>
                        <option value="" disabled selected>Select Source</option>
                        <option value="Reference">Reference</option>
                        <option value="Cold Call">Cold Call</option>
                        <option value="Website">Website</option>
                        <option value="Exhibition">Exhibition</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="required">Priority Level</label>
                    <select name="priority" required>
                        <option value="Hot">🔥 Hot</option>
                        <option value="Warm" selected>☀️ Warm</option>
                        <option value="Cold">❄️ Cold</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="required">Added By</label>
                    <input type="text" name="added_by" value="<?php echo htmlspecialchars($_SESSION['username']); ?>" readonly style="background:#f1f5f9; cursor:not-allowed;" required>
                </div>
                <div class="form-group full-width">
                    <label>Remarks / Notes</label>
                    <textarea name="remarks" rows="3" placeholder="Enter key conversational remarks here..."></textarea>
                </div>
            </div>

            <!-- Action panel -->
            <div class="form-actions">
                <button type="reset" class="btn btn-secondary">Clear Inputs</button>
                <button type="submit" class="btn btn-primary"><i data-lucide="shield-check"></i> Lock & Save Account</button>
            </div>
        </div>
    </form>
</div>

<script>
    async function saveClient(event) {
        event.preventDefault();
        const form = document.getElementById('client-registration-form');
        showConfirm(async () => {
            const formData = new FormData(form);
            // Force assigned_to to be the logged in staff member
            formData.append('assigned_to', '<?php echo htmlspecialchars($_SESSION['username']); ?>');
            
            try {
                const response = await fetch('../api.php?api=add_client', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (response.ok && data.success) {
                    showNotification(data.message, 'success');
                    form.reset();
                    setTimeout(() => {
                        location.href = 'search_track.php';
                    }, 1000);
                } else {
                    showNotification(data.error || 'Registration failed.', 'error');
                }
            } catch (err) {
                showNotification('Connection failure in client registration.', 'error');
            }
        }, 'Confirm Registration', 'Are you sure you want to register this new client?', 'Yes, Register');
    }

    document.addEventListener('DOMContentLoaded', () => {
        const leadDataStr = sessionStorage.getItem('convert_lead_data');
        if (leadDataStr) {
            try {
                const l = JSON.parse(leadDataStr);
                const setVal = (name, val) => {
                    const el = document.querySelector(`#client-registration-form [name="${name}"]`);
                    if (el && val !== undefined && val !== null) el.value = val;
                };
                
                setVal('contact_name', l.lead_name);
                setVal('company_name', l.company_name);
                setVal('mobile',       l.mobile);
                setVal('email',        l.email);
                setVal('lead_source',  l.lead_source);
                setVal('priority',     l.priority);
                if (l.location) {
                    setVal('city', l.location);
                }
                if (l.notes) {
                    setVal('remarks', l.notes);
                }
                
                showNotification('Lead data pre-filled! Complete the client registration form.', 'info');
            } catch (e) {
                console.error("Error parsing sessionStorage converted lead data:", e);
            } finally {
                sessionStorage.removeItem('convert_lead_data');
            }
        }
    });
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
