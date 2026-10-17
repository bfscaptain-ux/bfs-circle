<?php
require_once 'config.php';
require_role(['Admin'], false);
$page_title = 'Add New Referral / DSA';
$page_subtitle = ' Register a new referral partner or DSA';
$hide_main_header = true;
require_once 'header.php';
?>
<script src="assets/js/banks_directory.js?v=<?php echo time(); ?>"></script>
<?php
?>

<div id="add-referral-view" class="view-container">
    <div class="card">
        <div class="card-title-bar">
            <h2>Partner Registration Form</h2>
            <div class="actions">
                <a href="referrals_list.php" class="btn btn-secondary"><i data-lucide="list"></i> View All Referrals</a>
            </div>
        </div>

        <form id="addReferralForm" enctype="multipart/form-data">
            <!-- 1. Partner Profile -->
            <div class="form-section-title">1. Referrer / Partner Profile</div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="referrer_type" class="required">Referrer Type</label>
                    <select name="referrer_type" id="referrer_type" required>
                        <option value="">-- Select Type --</option>
                        <option value="Individual Agent">Individual Agent</option>
                        <option value="Financial Advisor / DSA">Financial Advisor / DSA</option>
                        <option value="Builder / Real Estate">Builder / Real Estate</option>
                        <option value="Chartered Accountant (CA)">Chartered Accountant (CA)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="full_name" class="required">Full Name</label>
                    <input type="text" name="full_name" id="full_name" required placeholder="Partner's full name">
                </div>
                <div class="form-group">
                    <label for="dob" class="required">Date of Birth</label>
                    <input type="date" name="dob" id="dob" required>
                </div>
                <div class="form-group">
                    <label for="mobile" class="required">Mobile Number</label>
                    <input type="tel" name="mobile" id="mobile" required maxlength="10" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0,10)" pattern="[0-9]{10}" placeholder="10-digit mobile number">
                </div>
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" name="email" id="email" placeholder="example@email.com">
                </div>
                <div class="form-group full-width">
                    <label for="city_state">City & State</label>
                    <input type="text" name="city_state" id="city_state" placeholder="e.g. Agra, Uttar Pradesh">
                </div>
                
                <!-- Dynamic Fields Container -->
                <div id="dynamic_fields_container" class="form-grid" style="grid-column: 1 / -1;">
                    <!-- Injected via JS -->
                </div>
            </div>

            <!-- 2. Banking Details -->
            <div class="form-section-title">2. Banking Details (Payout)</div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="account_name">Account Holder Name</label>
                    <input type="text" name="account_name" id="account_name" placeholder="Name as per passbook/cheque">
                </div>
                <div class="form-group">
                    <label for="bank_name">Bank Name</label>
                                        <select name="bank_name" id="bank_name">
                        <option value="">-- Select Bank --</option>
                        <?php
                        $js_file = 'assets/js/banks_directory.js';
                        if (file_exists($js_file)) {
                            $content = file_get_contents($js_file);
                            $lines = explode("\n", $content);
                            $banks = [];
                            foreach ($lines as $line) {
                                $line = trim($line);
                                if (preg_match('/^"([^"]+)"(,|\])?$/', $line, $m)) {
                                    $bank = $m[1];
                                    if (!in_array($bank, ['Public Sector Bank', 'Private Sector Bank', 'Small Finance Bank', 'Housing Finance Company (HFC)', 'NBFC / Others'])) {
                                        $banks[] = $bank;
                                    }
                                }
                            }
                            $banks = array_unique($banks);
                            sort($banks);
                            foreach ($banks as $bank) {
                                echo '<option value="' . htmlspecialchars($bank) . '">' . htmlspecialchars($bank) . '</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="account_number">Account Number</label>
                    <input type="text" name="account_number" id="account_number" maxlength="20" oninput="this.value = this.value.replace(/[^0-9A-Z]/g, '')" placeholder="Enter account number">
                </div>
                <div class="form-group">
                    <label for="ifsc_code">IFSC Code</label>
                    <input type="text" name="ifsc_code" id="ifsc_code" maxlength="11" oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0,11)" placeholder="e.g. HDFC0001234">
                </div>
                <div class="form-group full-width">
                    <label for="upi_id">UPI ID (Optional)</label>
                    <input type="text" name="upi_id" id="upi_id" placeholder="e.g. 9876543210@paytm">
                </div>
            </div>

            <!-- 3. Commission Structure -->
            <div class="form-section-title">3. Payout & Commission Structure</div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="commission_rate">Commission Rate / Slab</label>
                    <input type="text" name="commission_rate" id="commission_rate" placeholder="e.g. 1.5% or Flat ₹5000">
                </div>
                <div class="form-group">
                    <label for="payout_frequency">Payout Frequency</label>
                    <select name="payout_frequency" id="payout_frequency">
                        <option value="Monthly">Monthly</option>
                        <option value="Weekly">Weekly</option>
                        <option value="Per Disbursement Settlement">Per Disbursement Settlement</option>
                    </select>
                </div>
            </div>

            <!-- 4. KYC & DOCUMENTS -->
            <div class="form-section-title">4. KYC & DOCUMENTS</div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="pan_number" class="required">PAN Card Number</label>
                    <input type="text" name="pan_number" id="pan_number" required maxlength="10" oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0,10)" placeholder="ABCDE1234F">
                </div>
                <div class="form-group">
                    <label for="aadhar_number" class="required">Aadhar Card Number</label>
                    <input type="text" name="aadhar_number" id="aadhar_number" required maxlength="12" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0,12)" pattern="[0-9]{12}" placeholder="12-digit Aadhar number">
                </div>
              </div>
              <div class="form-grid" style="grid-template-columns: repeat(3, 1fr); gap: 20px;">
                <div class="form-group">
                    <label style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">Bank Document (Max 5MB)</label>
                    <div style="display: flex; gap: 8px; align-items: center; margin-top: 8px; padding: 8px 12px; background: #f8fafc; border: 1px solid var(--border); border-radius: 8px; height: 42px; overflow:hidden;">
                        <input type="file" name="bank_document" id="bank_document" style="display:none;" onchange="updateFileName(this, 'bank_doc_wrapper', 'bank_doc_empty', 'bank_doc_preview')" accept="image/*,.pdf">
                        
                        <button type="button" class="btn btn-sm" style="flex-shrink:0; background: white; color: #475569; border: 1px solid #cbd5e1; padding: 4px 10px;" onclick="document.getElementById('bank_document').click()">
                            <i data-lucide="upload" style="width:14px;height:14px;"></i> Choose File
                        </button>
                        
                        <div id="bank_doc_wrapper" style="display:none; flex:1; align-items:center; gap:4px; min-width:0; margin-left: 8px;">
                            <a id="bank_doc_preview" href="#" style="flex:1; font-size:12px; color:#f97316; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; text-decoration:none;"> file</a>
                            <button type="button" onclick="clearFileSelection('bank_document', 'bank_doc_wrapper', 'bank_doc_empty')" style="flex-shrink:0; background:none; border:none; color:#ef4444; cursor:pointer; padding:2px;"><i data-lucide="x" style="width:16px;height:16px;"></i></button>
                        </div>
                        
                        <span id="bank_doc_empty" style="font-size: 12px; color: var(--text-muted); margin-left: auto; flex-shrink:0;">No file</span>
                    </div>
                </div>

                <div class="form-group">
                    <label style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">PAN Card Scan (Max 5MB)</label>
                    <div style="display: flex; gap: 8px; align-items: center; margin-top: 8px; padding: 8px 12px; background: #f8fafc; border: 1px solid var(--border); border-radius: 8px; height: 42px; overflow:hidden;">
                        <input type="file" name="pan_document" id="pan_document" style="display:none;" onchange="updateFileName(this, 'pan_doc_wrapper', 'pan_doc_empty', 'pan_doc_preview')" accept="image/*,.pdf">
                        
                        <button type="button" class="btn btn-sm" style="flex-shrink:0; background: white; color: #475569; border: 1px solid #cbd5e1; padding: 4px 10px;" onclick="document.getElementById('pan_document').click()">
                            <i data-lucide="upload" style="width:14px;height:14px;"></i> Choose File
                        </button>
                        
                        <div id="pan_doc_wrapper" style="display:none; flex:1; align-items:center; gap:4px; min-width:0; margin-left: 8px;">
                            <a id="pan_doc_preview" href="#" style="flex:1; font-size:12px; color:#f97316; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; text-decoration:none;"> file</a>
                            <button type="button" onclick="clearFileSelection('pan_document', 'pan_doc_wrapper', 'pan_doc_empty')" style="flex-shrink:0; background:none; border:none; color:#ef4444; cursor:pointer; padding:2px;"><i data-lucide="x" style="width:16px;height:16px;"></i></button>
                        </div>
                        
                        <span id="pan_doc_empty" style="font-size: 12px; color: var(--text-muted); margin-left: auto; flex-shrink:0;">No file</span>
                    </div>
                </div>
            </div>
                  <div class="form-group">
                      <label style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">Aadhar Card Scan (Max 5MB)</label>
                      <div style="display: flex; gap: 8px; align-items: center; margin-top: 8px; padding: 8px 12px; background: #f8fafc; border: 1px solid var(--border); border-radius: 8px; height: 42px; overflow:hidden;">
                          <input type="file" name="aadhar_document" id="aadhar_document" style="display:none;" onchange="updateFileName(this, 'aadhar_doc_wrapper', 'aadhar_doc_empty')" accept="image/*,.pdf">
                          
                          <button type="button" class="btn btn-sm" style="flex-shrink:0; background: white; color: #475569; border: 1px solid #cbd5e1; padding: 4px 10px;" onclick="document.getElementById('aadhar_document').click()">
                              <i data-lucide="upload" style="width:14px;height:14px;"></i> Choose File
                          </button>
                          
                          <div id="aadhar_doc_wrapper" style="flex:1; display:flex; align-items:center; overflow:hidden;">
                              <span id="aadhar_doc_empty" style="font-size:12px; color:#94a3b8; font-style:italic; white-space:nowrap;">No file chosen</span>
                          </div>
                      </div>
                  </div>

            <!-- 6. STATUS & MANAGEMENT -->
            
            <!-- 5. SYSTEM LOGIN CREDENTIALS -->
            <div class="form-section-title">5. SYSTEM LOGIN CREDENTIALS (AUTO-GENERATED)</div>
            <div class="form-grid" style="background:#f0f9ff; padding:20px; border-radius:8px; border:1px solid #bae6fd;">
                <div class="form-group">
                    <label style="color:#0369a1; font-weight:600;">Login Username</label>
                    <input type="text" name="username" id="preview_username" readonly style="background-color:#e0f2fe; border-color:#7dd3fc; color:#0c4a6e; font-weight:bold;">
                    <small style="color:#0284c7;">Email ID (or Mobile No if Email is empty)</small>
                </div>
                <div class="form-group">
                    <label style="color:#0369a1; font-weight:600;">System Password</label>
                    <input type="text" name="password" id="preview_password" readonly style="background-color:#e0f2fe; border-color:#7dd3fc; color:#0c4a6e; font-weight:bold;">
                    <small style="color:#0284c7;">First 4 letters of Name + Last 4 digits of Aadhaar (or Mobile)</small>
                </div>
            </div>
            
            <div class="form-section-title">6. STATUS & MANAGEMENT</div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="mapped_branch">Branch Office</label>
                    <input type="text" name="mapped_branch" id="mapped_branch" value="Sanjay Place" readonly style="background-color: #f1f5f9; cursor: not-allowed; color: var(--text-muted);">
                </div>
                <div class="form-group">
                    <label for="assigned_rm">Assigned RM (Relationship Manager)</label>
                    <select name="assigned_rm" id="assigned_rm" class="user-select">
                        <option value="">-- Loading Staff --</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="status">Referral Status</label>
                    <select name="status" id="status">
                        <option value="Active">Active</option>
                        <option value="Pending Approval">Pending Approval</option>
                        <option value="Blocked">Blocked</option>
                    </select>
                </div>
            </div>

            <div class="form-actions">
                <button type="reset" class="btn btn-secondary">Clear</button>
                <button type="submit" class="btn btn-primary"><i data-lucide="check"></i> Register Partner</button>
            </div>
        </form>
    </div>
</main>
</div>

<!-- Document Preview Modal -->
<div id="previewModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:9999; align-items:center; justify-content:center; flex-direction:column; padding:20px;">
    <div style="width:100%; max-width:800px; display:flex; justify-content:flex-end; margin-bottom:10px;">
        <button type="button" onclick="closePreviewModal()" style="background:white; border:none; padding:8px; border-radius:50%; cursor:pointer; display:flex; align-items:center; justify-content:center;">
            <i data-lucide="x" style="width:20px; height:20px; color:#ef4444;"></i>
        </button>
    </div>
    <div id="previewContent" style="width:100%; max-width:800px; height:80vh; background:white; border-radius:8px; overflow:hidden; display:flex; align-items:center; justify-content:center; padding:10px;">
    </div>
</div>

<script>
    function clearFileSelection(inputId, wrapperId, emptyId) {
        const input = document.getElementById(inputId);
        input.value = "";
        document.getElementById(wrapperId).style.display = 'none';
        if (document.getElementById(emptyId)) document.getElementById(emptyId).style.display = 'block';
        lucide.createIcons(); // refresh icon
    }

    function updateFileName(input, wrapperId, emptyId, previewId) {
        const wrapperEl = document.getElementById(wrapperId);
        const previewEl = document.getElementById(previewId);
        const emptyEl = document.getElementById(emptyId);
        
        if (input.files && input.files.length > 0) {
            const file = input.files[0];
            if (file.size > 5 * 1024 * 1024) { // 5MB limit
                alert("File size must be less than 5MB!");
                clearFileSelection(input.id, wrapperId, emptyId);
                return;
            }
            wrapperEl.style.display = 'flex';
            if (emptyEl) emptyEl.style.display = 'none';
            previewEl.textContent = " " + file.name;
            
            const fileUrl = URL.createObjectURL(file);
            const isPdf = file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf');
            previewEl.onclick = function(e) {
                e.preventDefault();
                openPreviewModal(fileUrl, isPdf);
            };
            lucide.createIcons();
        } else {
            clearFileSelection(input.id, wrapperId, emptyId);
        }
    }

    function openPreviewModal(url, isPdf) {
        const modal = document.getElementById('previewModal');
        const content = document.getElementById('previewContent');
        if (isPdf || url.toLowerCase().endsWith('.pdf')) {
            content.innerHTML = `<iframe src="${url}" style="width:100%; height:100%; border:none;"></iframe>`;
        } else {
            content.innerHTML = `<img src="${url}" style="max-width:100%; max-height:100%; object-fit:contain;" />`;
        }
        modal.style.display = 'flex';
    }

    function closePreviewModal() {
        document.getElementById('previewModal').style.display = 'none';
        document.getElementById('previewContent').innerHTML = '';
    }

    
    const refNameInput = document.getElementById('full_name');
    const refEmailInput = document.getElementById('email');
    const refMobileInput = document.getElementById('mobile');
    const refAadhaarInput = document.getElementById('aadhar_number');
    const previewUsername = document.getElementById('preview_username');
    const previewPassword = document.getElementById('preview_password');

    function updateRefCredentialsPreview() {
        const email = refEmailInput.value.trim();
        const mobile = refMobileInput.value.trim();
        const name = refNameInput.value.trim().replace(/[^a-zA-Z]/g, '');
        const aadhar = refAadhaarInput.value.trim();
        
        // Username is Email or Mobile
        previewUsername.value = email ? email.toLowerCase() : mobile;
        
        // Password logic
        if (name.length >= 2 && (aadhar.length >= 4 || mobile.length >= 4)) {
            const namePart = name.substring(0, 4).toUpperCase();
            let numPart = "";
            if (aadhar.length === 12) {
                numPart = aadhar.slice(-4);
            } else if (mobile.length === 10) {
                numPart = mobile.slice(-4);
            }
            previewPassword.value = namePart + numPart;
        } else {
            previewPassword.value = '';
        }
    }

    if (refNameInput) refNameInput.addEventListener('input', updateRefCredentialsPreview);
    if (refEmailInput) refEmailInput.addEventListener('input', updateRefCredentialsPreview);
    if (refMobileInput) refMobileInput.addEventListener('input', updateRefCredentialsPreview);
    if (refAadhaarInput) refAadhaarInput.addEventListener('input', updateRefCredentialsPreview);

    document.addEventListener('DOMContentLoaded', () => {
        const typeSelect = document.getElementById('referrer_type');
        const dynamicContainer = document.getElementById('dynamic_fields_container');
        
        typeSelect.addEventListener('change', function() {
            const val = this.value;
            let html = '';
            
            if (val === 'Builder / Real Estate') {
                html = `
                    <div class="form-group" style="grid-column: 1 / -1; margin-bottom: 8px;"><strong style="color:var(--primary);"> Builder / Real Estate Details</strong></div>
                    <div class="form-group"><label>Company / Builder Name</label><input type="text" name="company_name" placeholder="e.g. Skyline Builders"></div>
                    <div class="form-group"><label>RERA Registration No.</label><input type="text" name="rera_no" placeholder="RERA Number"></div>
                    <div class="form-group"><label>GST Number</label><input type="text" name="gst_no" placeholder="15-digit GSTIN"></div>
                    <div class="form-group"><label>Website</label><input type="url" name="website" placeholder="https://www.example.com"></div>
                `;
            } else if (val === 'Chartered Accountant (CA)' || val === 'Financial Advisor / DSA') {
                const label = val === 'Chartered Accountant (CA)' ? 'ICAI Membership No.' : 'DSA License No.';
                html = `
                    <div class="form-group" style="grid-column: 1 / -1; margin-bottom: 8px;"><strong style="color:var(--primary);"> Firm / Agency Details</strong></div>
                    <div class="form-group"><label>Firm / Agency Name</label><input type="text" name="company_name" placeholder="Name of Firm"></div>
                    <div class="form-group"><label>${label}</label><input type="text" name="registration_number" placeholder="${label}"></div>
                    <div class="form-group"><label>GST Number</label><input type="text" name="gst_no" placeholder="15-digit GSTIN"></div>
                    <div class="form-group"><label>Website</label><input type="url" name="website" placeholder="https://www.example.com"></div>
                `;
            } else if (val === 'Individual Agent') {
                html = `
                    <div class="form-group" style="grid-column: 1 / -1; margin-bottom: 8px;"><strong style="color:var(--primary);"> Agent Details</strong></div>
                    <div class="form-group"><label>Occupation</label><input type="text" name="occupation" placeholder="Current Occupation"></div>
                `;
            }
            
            if (html !== '') {
                dynamicContainer.innerHTML = html;
                dynamicContainer.style.display = 'grid';
            } else {
                dynamicContainer.innerHTML = '';
                dynamicContainer.style.display = 'none';
            }
        });
        
    });

document.getElementById('addReferralForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = this.querySelector('button[type="submit"]');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i data-lucide="loader" class="spin"></i> Processing...';
    btn.disabled = true;

    try {
        const formData = new FormData(this);
        const res = await fetch('api.php?api=add_referral', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        
        if (data.success) {
            showNotification(data.message, 'success');
            setTimeout(() => {
                window.location.href = 'referrals_list.php';
            }, 1500);
        } else {
            showNotification(data.error || 'Registration failed', 'error');
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    } catch(err) {
        showNotification('Network error occurred.', 'error');
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
});
</script>


<script>
    function populateBankDropdown() {
        const select = document.getElementById('bank_name');
        if (!select) return;
        
        let allBanks = [];
        if (typeof BANK_DIRECTORY !== 'undefined') {
            for (const type in BANK_DIRECTORY) {
                allBanks = allBanks.concat(BANK_DIRECTORY[type]);
            }
            
            // Remove duplicates and sort
            allBanks = [...new Set(allBanks)].sort((a,b) => a.localeCompare(b));
            
            allBanks.forEach(bank => {
                const opt = document.createElement('option');
                opt.value = bank;
                opt.textContent = bank;
                select.appendChild(opt);
            });
            
            // Auto-select existing if present (from data-existing)
            const existingBank = select.getAttribute('data-existing');
            if (existingBank) select.value = existingBank;
        }
    }
    
    function initValidationAndCapitalize() {
        const textFields = document.querySelectorAll('input[type="text"]');
        textFields.forEach(f => {
            f.addEventListener('input', function() {
                // Ignore specific fields like PAN, IFSC that need full uppercase
                if (this.id === 'pan_number' || this.id === 'ifsc_code' || this.id === 'gst_no') {
                    this.value = this.value.toUpperCase();
                } else if (this.id !== 'account_number' && this.id !== 'aadhar_number' && this.id !== 'mobile') {
                    // Capitalize first letter of every word
                    let words = this.value.split(' ');
                    for (let i = 0; i < words.length; i++) {
                        if (words[i].length > 0) {
                            words[i] = words[i].charAt(0).toUpperCase() + words[i].slice(1);
                        }
                    }
                    this.value = words.join(' ');
                }
            });
        });
    }

    
    const refNameInput = document.getElementById('full_name');
    const refEmailInput = document.getElementById('email');
    const refMobileInput = document.getElementById('mobile');
    const refAadhaarInput = document.getElementById('aadhar_number');
    const previewUsername = document.getElementById('preview_username');
    const previewPassword = document.getElementById('preview_password');

    function updateRefCredentialsPreview() {
        const email = refEmailInput.value.trim();
        const mobile = refMobileInput.value.trim();
        const name = refNameInput.value.trim().replace(/[^a-zA-Z]/g, '');
        const aadhar = refAadhaarInput.value.trim();
        
        // Username is Email or Mobile
        previewUsername.value = email ? email.toLowerCase() : mobile;
        
        // Password logic
        if (name.length >= 2 && (aadhar.length >= 4 || mobile.length >= 4)) {
            const namePart = name.substring(0, 4).toUpperCase();
            let numPart = "";
            if (aadhar.length === 12) {
                numPart = aadhar.slice(-4);
            } else if (mobile.length === 10) {
                numPart = mobile.slice(-4);
            }
            previewPassword.value = namePart + numPart;
        } else {
            previewPassword.value = '';
        }
    }

    if (refNameInput) refNameInput.addEventListener('input', updateRefCredentialsPreview);
    if (refEmailInput) refEmailInput.addEventListener('input', updateRefCredentialsPreview);
    if (refMobileInput) refMobileInput.addEventListener('input', updateRefCredentialsPreview);
    if (refAadhaarInput) refAadhaarInput.addEventListener('input', updateRefCredentialsPreview);

    document.addEventListener('DOMContentLoaded', () => {
        // populateBankDropdown();
        initValidationAndCapitalize();
    });
</script>

<?php require_once 'footer.php'; ?>
