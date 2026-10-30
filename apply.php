<?php
require_once 'config.php';

$ref = $_GET['ref'] ?? '';
$partner = null;

if ($ref) {
    // 1. Check in referrals table by referral_id
    $stmt = $db->prepare("SELECT r.full_name, r.user_id, r.referral_id, u.username FROM referrals r JOIN users u ON r.user_id = u.id WHERE r.referral_id = ?");
    $stmt->execute([$ref]);
    $partner = $stmt->fetch();
    
    // 2. Check in users table by username (fallback for CA QR codes)
    if (!$partner) {
        $stmt_u = $db->prepare("SELECT u.id as user_id, u.username, r.full_name, r.referral_id FROM users u LEFT JOIN referrals r ON u.id = r.user_id WHERE u.username = ?");
        $stmt_u->execute([$ref]);
        $partner_u = $stmt_u->fetch();
        if ($partner_u) {
            $partner = [
                'full_name' => $partner_u['full_name'] ?: $partner_u['username'],
                'user_id' => $partner_u['user_id'],
                'referral_id' => $partner_u['referral_id'],
                'username' => $partner_u['username']
            ];
        }
    }
    
    // 3. Check Builder ID (BLD-18)
    if (!$partner && strpos($ref, 'BLD-') === 0) {
        $b_id = str_replace('BLD-', '', $ref);
        $stmt_b = $db->prepare("SELECT id as user_id, name as full_name, username FROM users WHERE id = ? AND role = 'Builder'");
        $stmt_b->execute([$b_id]);
        $pb = $stmt_b->fetch();
        if ($pb) {
             $partner = [
                 'full_name' => $pb['full_name'],
                 'user_id' => $pb['user_id'],
                 'referral_id' => $ref,
                 'username' => $pb['username']
             ];
        }
    }

    // 4. Check Existing Customer / Applicant Referral (CUST-123 or tracking_token or mobile)
    if (!$partner) {
        $app_ref_id = null;
        if (strpos($ref, 'CUST-') === 0) {
            $app_ref_id = intval(substr($ref, 5));
        }
        if ($app_ref_id > 0) {
            $stmt_app = $db->prepare("SELECT id, customer_name, mobile, tracking_token FROM applicants WHERE id = ?");
            $stmt_app->execute([$app_ref_id]);
        } else {
            $stmt_app = $db->prepare("SELECT id, customer_name, mobile, tracking_token FROM applicants WHERE tracking_token = ? OR (mobile = ? AND mobile != '')");
            $stmt_app->execute([$ref, $ref]);
        }
        $app_data = $stmt_app->fetch();
        if ($app_data) {
            $partner = [
                'full_name' => $app_data['customer_name'] . ' (Customer)',
                'user_id' => 0,
                'applicant_id' => $app_data['id'],
                'referral_id' => 'CUST-' . $app_data['id'],
                'username' => 'CUST_' . $app_data['id'],
                'is_customer' => true,
                'customer_name' => $app_data['customer_name'],
                'customer_mobile' => $app_data['mobile']
            ];
        }
    }
}

$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $partner) {
    $name = trim($_POST['name'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $loan_type = trim($_POST['loan_type'] ?? '');
    $loan_sub_type = trim($_POST['loan_sub_type'] ?? '');
    $requirement = $loan_type . ($loan_sub_type ? ' - ' . $loan_sub_type : '');
    $amount = floatval($_POST['amount'] ?? 0);
    $pan = trim($_POST['pan'] ?? '');
    $aadhar = trim($_POST['aadhar'] ?? '');
    
    if ($name && $mobile) {
        $added_by = !empty($partner['is_customer']) ? $partner['customer_name'] : ($partner['username'] ?? $partner['full_name']);
        $notes = "Form Details - PAN: " . ($pan ?: 'N/A') . " | Aadhar: " . ($aadhar ?: 'N/A');
        
        $photo_path = null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/leads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $filename = uniqid('lead_') . '.' . $ext;
            // Compress Image
            $tmp = $_FILES['photo']['tmp_name'];
            $dest = $upload_dir . $filename;
                if (function_exists('imagecreatefromjpeg')) {
        $info = getimagesize($tmp);
        if ($info) {
            if ($info['mime'] == 'image/jpeg') $image = imagecreatefromjpeg($tmp);
            elseif ($info['mime'] == 'image/png') $image = imagecreatefrompng($tmp);
            elseif ($info['mime'] == 'image/gif') $image = imagecreatefromgif($tmp);
            else $image = function_exists('imagecreatefromstring') ? imagecreatefromstring(file_get_contents($tmp)) : false;
            
            if ($image) {
                $width = imagesx($image);
                $height = imagesy($image);
                $new_width = 400;
                $new_height = floor($height * ($new_width / $width));
                $tmp_img = imagecreatetruecolor($new_width, $new_height);
                imagecopyresampled($tmp_img, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
                imagejpeg($tmp_img, $dest, 50);
                imagedestroy($image);
                imagedestroy($tmp_img);
                $photo_path = 'uploads/leads/' . $filename;
            } else {
                if (move_uploaded_file($tmp, $dest)) $photo_path = 'uploads/leads/' . $filename;
            }
        } else {
            if (move_uploaded_file($tmp, $dest)) $photo_path = 'uploads/leads/' . $filename;
        }
    } else {
        if (move_uploaded_file($tmp, $dest)) $photo_path = 'uploads/leads/' . $filename;
    }
        }
        $address = trim($_POST['address'] ?? '');
        $pincode = trim($_POST['pincode'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $location = $address . ', ' . $city . ', ' . $state . ' - ' . $pincode;
        
        $lead_source = !empty($partner['is_customer']) ? 'Customer Referral' : 'Partner Referral';
        if (!empty($partner['is_customer'])) {
            $notes .= " | Referred by Customer: " . ($partner['customer_name'] ?? '') . " (" . ($partner['customer_mobile'] ?? '') . ")";
        }
        $stmt = $db->prepare("INSERT INTO leads (lead_name, mobile, email, requirement, loan_amount, added_by, lead_source, stage, notes, photo, location) VALUES (?, ?, ?, ?, ?, ?, ?, 'New Lead', ?, ?, ?)");
        $stmt->execute([$name, $mobile, $email, $requirement, $amount, $added_by, $lead_source, $notes, $photo_path, $location]);
        $new_lead_id = $db->lastInsertId();

        // Record in customer_referrals table
        if (!empty($partner['is_customer']) && !empty($partner['applicant_id'])) {
            try {
                $stmt_cr = $db->prepare("INSERT INTO customer_referrals (referrer_applicant_id, referrer_name, referrer_mobile, referred_lead_id, friend_name, friend_mobile, loan_requirement, status, reward_amount) VALUES (?, ?, ?, ?, ?, ?, ?, 'Lead Received', 2000.0)");
                $stmt_cr->execute([$partner['applicant_id'], $partner['customer_name'], $partner['customer_mobile'], $new_lead_id, $name, $mobile, $requirement]);
            } catch (Exception $e) {}
        }
        
        // Notification
        if (!empty($partner['user_id'])) {
            $msg = "New Lead Alert: $name has applied for a $loan_type via your referral link.";
            try {
                $db->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)")->execute([$partner['user_id'], $msg]);
            } catch (Exception $e) {}
        }
        
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Official BFS Circle Monochrome Favicon & Branding -->
    <link rel="icon" type="image/png" sizes="64x64" href="<?php echo isset($base_path) ? $base_path : ""; ?>favicon.png?v=3">
    <link rel="icon" type="image/x-icon" href="<?php echo isset($base_path) ? $base_path : ""; ?>favicon.ico?v=3">
    <link rel="shortcut icon" href="<?php echo isset($base_path) ? $base_path : ""; ?>favicon.ico?v=3">
    <link rel="apple-touch-icon" href="<?php echo isset($base_path) ? $base_path : ""; ?>favicon.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for Loan - BFS Financial Services</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root { --primary: #0F2C59; --accent: #F97316; }
        body { font-family: 'Inter', sans-serif; background-color: #f1f5f9; margin: 0; padding: 20px; color: #0f172a; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .card { background: white; border-radius: 20px; padding: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); width: 100%; max-width: 450px; }
        
        .brand { text-align: center; margin-bottom: 20px; font-size: 24px; font-family: 'Outfit'; font-weight: 800; }
        .brand .p1 { color: var(--primary); } .brand .p2 { color: var(--accent); }
        
        h2 { font-family: 'Outfit'; margin: 0 0 8px 0; font-size: 24px; color: var(--primary); text-align: center; }
        p { color: #64748b; font-size: 14px; margin-top: 0; margin-bottom: 24px; text-align: center; font-weight: 500; }
        
        .ref-badge { background: #eff6ff; color: #1e40af; padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: 700; text-align: center; margin: 0 auto 24px auto; display: inline-flex; align-items: center; gap: 6px; }
        
        .form-group { margin-bottom: 20px; }
        label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 8px; color: #475569; }
        input, select, textarea { width: 100%; padding: 14px 16px; border: 1.5px solid #e2e8f0; border-radius: 10px; font-size: 15px; box-sizing: border-box; outline: none; transition: 0.2s; font-family: 'Inter'; }
        input:focus, select:focus, textarea:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(15, 44, 89, 0.1); }
        
        .btn { width: 100%; background: var(--accent); color: white; border: none; padding: 16px; border-radius: 10px; font-size: 16px; font-weight: 700; cursor: pointer; transition: 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .btn:active { transform: scale(0.98); }
        .btn:hover { background: #EA580C; }
        
        .success-box { text-align: center; padding: 30px 0; }
        .success-icon { width: 80px; height: 80px; background: #dcfce7; color: #16a34a; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px auto; }
        .success-box h2 { color: #16a34a; }
        
        .error-box { text-align: center; padding: 30px 0; }

        .address-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .full-width-desktop { grid-column: 1 / -1; }
        
        @media (max-width: 480px) {
            .brand span { font-size: 18px !important; }
            .brand img { height: 32px !important; }
            .brand > div { flex-direction: column; gap: 6px !important; }
            .address-grid { grid-template-columns: 1fr; }
            .full-width-desktop { grid-column: span 1; }
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="brand"><div style="display:flex; justify-content:center; align-items:center; gap:10px; margin-bottom:15px;"><img src="logo.png" alt="Logo" style="height:36px;"> <span style="font-family:'Outfit',sans-serif; font-weight:800; font-size:22px; color:var(--primary); text-align:center;">BFS Financial Services</span></div></div>
        
        <?php if (!$partner): ?>
            <div class="error-box">
                <i data-lucide="alert-circle" style="width:60px; height:60px; color:#ef4444; margin:0 auto 16px auto; display:block;"></i>
                <h2>Invalid Link</h2>
                <p>This referral link is invalid or expired.</p>
            </div>
        <?php elseif ($success): ?>
            <div class="success-box">
                <div class="success-icon"><i data-lucide="check" style="width:40px; height:40px;"></i></div>
                <h2>Application Submitted!</h2>
                <p>Thank you! Our team will review your request and get back to you shortly.</p>
            </div>
        <?php else: ?>
            <div style="text-align: center; margin-bottom: 32px; padding-bottom: 24px; border-bottom: 1px solid #e2e8f0;">
                <h2 style="font-size:28px; color:var(--primary); margin-bottom:12px; font-weight:800; font-family:'Outfit', sans-serif; letter-spacing:-0.5px;">Apply for a Loan</h2>
                <p style="margin:0; font-size:15px; color:#475569; line-height:1.6; max-width: 90%; margin: 0 auto;">
                    Take the first step towards your financial goals.<br>Fast, secure, and completely paperless.
                </p>
                <div style="display:flex; justify-content:center; gap:24px; margin-top:24px;">
                    <div style="display:flex; flex-direction:column; align-items:center; gap:8px;">
                        <div style="width:44px; height:44px; border-radius:50%; background:rgba(15, 44, 89, 0.05); display:flex; align-items:center; justify-content:center; color:var(--primary);"><i data-lucide="zap" style="width:22px; stroke-width:2.5px;"></i></div>
                        <span style="font-size:11px; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.5px;">Instant</span>
                    </div>
                    <div style="display:flex; flex-direction:column; align-items:center; gap:8px;">
                        <div style="width:44px; height:44px; border-radius:50%; background:rgba(15, 44, 89, 0.05); display:flex; align-items:center; justify-content:center; color:var(--primary);"><i data-lucide="shield-check" style="width:22px; stroke-width:2.5px;"></i></div>
                        <span style="font-size:11px; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.5px;">Secure</span>
                    </div>
                    <div style="display:flex; flex-direction:column; align-items:center; gap:8px;">
                        <div style="width:44px; height:44px; border-radius:50%; background:rgba(15, 44, 89, 0.05); display:flex; align-items:center; justify-content:center; color:var(--primary);"><i data-lucide="file-check-2" style="width:22px; stroke-width:2.5px;"></i></div>
                        <span style="font-size:11px; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.5px;">Paperless</span>
                    </div>
                </div>
            </div>
            
                        <div id="progress-container" style="margin-bottom: 24px;">
                <div style="display:flex; justify-content:space-between; margin-bottom:8px; font-size:12px; font-weight:600; color:#64748b;">
                    <span id="step-text">Step 1 of 3: Personal Info</span>
                    <span id="step-percent">33%</span>
                </div>
                <div style="height:6px; background:#e2e8f0; border-radius:10px; overflow:hidden;">
                    <div id="progress-bar" style="height:100%; width:33%; background:var(--primary); transition:0.3s ease;"></div>
                </div>
            </div>

            <form id="applyForm" method="POST" enctype="multipart/form-data" onsubmit="return prepareSubmit()">
                
                <!-- STEP 1: Personal Details -->
                <div class="form-step" id="step1">
                    <h3 style="margin-top:0; color:var(--primary); font-family:'Outfit', sans-serif;">Personal Information</h3>
                    <div class="form-group">
                        <label>Full Name <span style="color:#ef4444">*</span></label>
                        <input type="text" name="name" required placeholder="Enter your full name">
                    </div>
                    <div class="form-group">
                        <label>Mobile Number <span style="color:#ef4444">*</span></label>
                        <input type="tel" name="mobile" required placeholder="10-digit mobile number" pattern="[0-9]{10}">
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" placeholder="your@email.com">
                    </div>
                    <button type="button" class="btn" onclick="nextStep(2)">Continue to Loan Details <i data-lucide="arrow-right"></i></button>
                </div>

                <!-- STEP 2: Loan Details -->
                <div class="form-step" id="step2" style="display:none;">
                    <h3 style="margin-top:0; color:var(--primary); font-family:'Outfit', sans-serif;">Loan Requirements</h3>
                    <div class="form-group">
                        <label class="required">Loan Type <span style="color:#ef4444">*</span></label>
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
                        <label class="required">Loan Sub Type <span style="color:#ef4444">*</span></label>
                        <select name="loan_sub_type" id="loan_sub_type" required>
                            <option value="">-- Select Sub Type --</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Required Loan Amount (₹) <span style="color:#ef4444">*</span></label>
                        <input type="text" id="amount_display" required placeholder="e.g. 5,00,000" oninput="formatAmount(this)">
                        <input type="hidden" name="amount" id="amount_real">
                    </div>
                    <div style="display:flex; gap:10px;">
                        <button type="button" class="btn" style="background:#e2e8f0; color:#475569;" onclick="prevStep(1)"><i data-lucide="arrow-left"></i> Back</button>
                        <button type="button" class="btn" onclick="nextStep(3)">Continue to Documents <i data-lucide="arrow-right"></i></button>
                    </div>
                </div>

                <!-- STEP 3: Address & Documents -->
                <div class="form-step" id="step3" style="display:none;">
                    <h3 style="margin-top:0; color:var(--primary); font-family:'Outfit', sans-serif;">Address & Documents</h3>
                    <div class="form-group">
                        <label>Complete Address <span style="color:#ef4444">*</span></label>
                        <textarea name="address" required placeholder="House/Flat No, Building Name, Street Area..." rows="2" style="resize:vertical;"></textarea>
                    </div>
                    <div class="form-group address-grid">
                        <div>
                            <label>Pincode <span style="color:#ef4444">*</span></label>
                            <input type="text" name="pincode" id="pincode_input" required placeholder="6-digit PIN" maxlength="6" pattern="\d{6}" oninput="fetchPinDetails(this.value)" style="margin-bottom:4px;">
                            <span id="pin_status" style="font-size:12px; color:var(--primary); font-weight:500;"></span>
                        </div>
                        <div>
                            <label>State <span style="color:#ef4444">*</span></label>
                            <input type="text" name="state" id="state_input" required placeholder="State">
                        </div>
                        <div class="full-width-desktop">
                            <label>City / District <span style="color:#ef4444">*</span></label>
                            <input type="text" name="city" id="city_input" required placeholder="City">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>PAN Number (Optional)</label>
                        <input type="text" name="pan" placeholder="ABCDE1234F" style="text-transform: uppercase;" oninput="this.value = this.value.toUpperCase()">
                    </div>
                    <div class="form-group">
                        <label>Aadhar Number (Optional)</label>
                        <input type="text" name="aadhar" placeholder="12-digit Aadhar" maxlength="12" pattern="\d{12}">
                    </div>
                    <div class="form-group" style="text-align:center;">
                        <label style="display:block; margin-bottom:10px;">Take a Photo <span style="color:#ef4444">*</span></label>
                        <input type="file" name="photo" id="photo" accept="image/*" capture="user" required style="display:none;" onchange="previewPhoto(event)">
                        <button type="button" onclick="document.getElementById('photo').click()" style="background:#f1f5f9; border:2px dashed #cbd5e1; padding:20px; border-radius:12px; width:100%; cursor:pointer; color:#64748b; font-weight:600;">
                            <i data-lucide="camera" style="width:32px; height:32px; margin-bottom:8px; display:block; margin:0 auto 8px;"></i>
                            Click to Capture Photo
                        </button>
                        <img id="photo-preview" style="display:none; width:100px; height:100px; object-fit:cover; border-radius:12px; margin: 15px auto 0; border:2px solid var(--primary);">
                    </div>
                    <div style="display:flex; gap:10px;">
                        <button type="button" class="btn" style="background:#e2e8f0; color:#475569;" onclick="prevStep(2)"><i data-lucide="arrow-left"></i> Back</button>
                        <button type="submit" class="btn"><i data-lucide="send"></i> Submit Application</button>
                    </div>
                </div>
            </form>
            
            <!-- TRUST BADGES -->
            <div style="margin-top: 24px; padding-top: 16px; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-around; opacity: 0.7;">
                <div style="display: flex; flex-direction: column; align-items: center; gap: 4px;">
                    <i data-lucide="lock" style="width: 16px; height: 16px; color: #475569;"></i>
                    <span style="font-size: 10px; font-weight: 600; color: #64748b;">256-bit Secure</span>
                </div>
                <div style="display: flex; flex-direction: column; align-items: center; gap: 4px;">
                    <i data-lucide="shield" style="width: 16px; height: 16px; color: #475569;"></i>
                    <span style="font-size: 10px; font-weight: 600; color: #64748b;">Bank Level Security</span>
                </div>
                <div style="display: flex; flex-direction: column; align-items: center; gap: 4px;">
                    <i data-lucide="award" style="width: 16px; height: 16px; color: #475569;"></i>
                    <span style="font-size: 10px; font-weight: 600; color: #64748b;">Trusted Partner</span>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <script>
        lucide.createIcons();
    </script>

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
</script>

<script>
function previewPhoto(event) {
    const file = event.target.files[0];
    if(file) {
        // Simple preview
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = document.getElementById('photo-preview');
            img.src = e.target.result;
            img.style.display = 'block';
        }
        reader.readAsDataURL(file);
    }
}

async function fetchPinDetails(pin) {
    const status = document.getElementById('pin_status');
    const city = document.getElementById('city_input');
    const state = document.getElementById('state_input');
    
    if (pin.length === 6) {
        status.innerHTML = 'Fetching...';
        try {
            // First try local DB
            const res = await fetch(`api.php?api=verify_pincode&pin=${pin}`);
            const data = await res.json();
            if (data && data.success) {
                city.value = data.city;
                state.value = data.state;
                status.innerHTML = '<span style="color:#16a34a;">Found!</span>';
                setTimeout(() => status.innerHTML = '', 2000);
            } else {
                // Fallback to postal API
                const apiRes = await fetch(`https://api.postalpincode.in/pincode/${pin}`);
                const apiData = await apiRes.json();
                if (apiData && apiData[0].Status === "Success") {
                    const postOffice = apiData[0].PostOffice[0];
                    city.value = postOffice.District;
                    state.value = postOffice.State;
                    status.innerHTML = '<span style="color:#16a34a;">Found!</span>';
                    setTimeout(() => status.innerHTML = '', 2000);
                } else {
                    status.innerHTML = '<span style="color:#ef4444;">Not found</span>';
                }
            }
        } catch (e) {
            status.innerHTML = '';
        }
    } else {
        status.innerHTML = '';
    }
}

// Smart Amount Formatting
function formatAmount(input) {
    let val = input.value.replace(/\D/g, '');
    if (!val) {
        input.value = '';
        document.getElementById('amount_real').value = '';
        return;
    }
    document.getElementById('amount_real').value = val;
    let x = val.toString();
    let lastThree = x.substring(x.length-3);
    let otherNumbers = x.substring(0, x.length-3);
    if (otherNumbers != '') {
        lastThree = ',' + lastThree;
    }
    input.value = otherNumbers.replace(/\B(?=(\d{2})+(?!\d))/g, ",") + lastThree;
}

function prepareSubmit() {
    return true; 
}

const totalSteps = 3;
function nextStep(step) {
    const currentStepDiv = document.getElementById('step' + (step - 1));
    if(currentStepDiv) {
        const inputs = currentStepDiv.querySelectorAll('input[required], select[required], textarea[required]');
        for (let input of inputs) {
            if (!input.checkValidity()) {
                input.reportValidity();
                return;
            }
        }
    }
    document.querySelectorAll('.form-step').forEach(el => el.style.display = 'none');
    document.getElementById('step' + step).style.display = 'block';
    
    const pct = Math.round((step / totalSteps) * 100);
    document.getElementById('progress-bar').style.width = pct + '%';
    const titles = ["", "Personal Info", "Loan Details", "Address & Docs"];
    document.getElementById('step-text').innerText = 'Step ' + step + ' of 3: ' + titles[step];
    document.getElementById('step-percent').innerText = pct + '%';
}

function prevStep(step) {
    document.querySelectorAll('.form-step').forEach(el => el.style.display = 'none');
    document.getElementById('step' + step).style.display = 'block';
    
    const pct = Math.round((step / totalSteps) * 100);
    document.getElementById('progress-bar').style.width = pct + '%';
    const titles = ["", "Personal Info", "Loan Details", "Address & Docs"];
    document.getElementById('step-text').innerText = 'Step ' + step + ' of 3: ' + titles[step];
    document.getElementById('step-percent').innerText = pct + '%';
}
</script>
</body>
</html>


