<?php
require_once '../config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'CA') {
    header("Location: ../login.php");
    exit;
}

// Handle API Submission
if (isset($_GET['api']) && $_GET['api'] == 'submit_lead') {
    header('Content-Type: application/json');
    $customer_name = trim($_POST['customer_name'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $loan_type = trim($_POST['loan_type'] ?? 'Personal Loan');
    $loan_sub_type = trim($_POST['loan_sub_type'] ?? '');
    $requirement = $loan_type . ($loan_sub_type ? ' - ' . $loan_sub_type : '');
    $loan_amount = floatval($_POST['loan_amount'] ?? 0);
    $pan_number = trim($_POST['pan_number'] ?? '');
    $aadhar_number = trim($_POST['aadhar_number'] ?? '');
    
    if (empty($customer_name) || empty($mobile) || $loan_amount <= 0) {
        echo json_encode(['success' => false, 'error' => 'Please fill required fields']);
        exit;
    }
    
    // Get CA Details
    $stmt = $db->prepare("SELECT referral_id FROM referrals WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $ca = $stmt->fetch(PDO::FETCH_ASSOC);
    $referral_id = $ca['referral_id'] ?? '';
    
    $loan_id = 'L' . date('Ymd') . rand(1000, 9999);
    $username = $_SESSION['username'];
    
    try {
        $notes = "Form Details - PAN: " . ($pan_number ?: 'N/A') . " | Aadhar: " . ($aadhar_number ?: 'N/A');
    
$photo_path = null;
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = __DIR__ . '/../uploads/leads/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
    
    $tmp = $_FILES['photo']['tmp_name'];
    $filename = uniqid('lead_') . '.jpg';
    $dest = $upload_dir . $filename;
    
        $info = getimagesize($tmp);
    if ($info && function_exists('imagecreatefromjpeg')) {
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
}

    $stmt_insert = $db->prepare("INSERT INTO leads (lead_name, mobile, email, requirement, loan_amount, added_by, lead_source, stage, notes, photo) VALUES (?, ?, ?, ?, ?, ?, 'CA Referral', 'New Lead', ?, ?)");
    $stmt_insert->execute([$customer_name, $mobile, $email, $requirement, $loan_amount, $username, $notes, $photo_path]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

$page_title = 'Submit Lead - CA Portal';
$active_page = 'add_lead';
require_once 'includes/header.php';
?>
<style>
    .page-header { display: flex; align-items: center; gap: 12px; margin-bottom: 24px; font-size: 20px; font-weight: 800; color: var(--primary); }
    
    .form-card {
        background: var(--surface); border-radius: 12px; padding: 32px;
        border: 1px solid var(--border); max-width: 800px; margin: 0 auto;
    }
    .form-group { margin-bottom: 20px; }
    .form-label { display: block; font-size: 13px; font-weight: 700; color: var(--text-main); margin-bottom: 6px; }
    .form-control {
        width: 100%; padding: 14px 16px; border-radius: 8px; border: 1px solid var(--border);
        background: var(--bg); font-size: 15px; color: var(--text-main);
        transition: all 0.2s; font-weight: 500; outline: none;
    }
    .form-control:focus { border-color: var(--primary); background: var(--surface); box-shadow: 0 0 0 2px rgba(15, 44, 89, 0.1); }
    .form-control::placeholder { color: var(--text-muted); }
    
    .btn-submit {
        background: var(--accent); color: white; width: 100%; padding: 16px;
        border-radius: 8px; border: none; font-size: 15px; font-weight: 700;
        cursor: pointer; display: flex; justify-content: center; align-items: center; gap: 8px;
        transition: background 0.2s; margin-top: 24px;
    }
    .btn-submit:hover { background: #EA580C; }

    .toast {
        position: fixed; bottom: 80px; left: 50%; transform: translateX(-50%) translateY(100px);
        background: var(--success); color: white; padding: 14px 24px; border-radius: 8px;
        font-weight: 600; font-size: 14px; box-shadow: var(--shadow-lg); opacity: 0;
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); z-index: 2000;
        display: flex; align-items: center; gap: 10px;
    }
    .toast.show { transform: translateX(-50%) translateY(0); opacity: 1; }
    .toast.error { background: var(--danger); }

    @media (max-width: 768px) { .form-card { padding: 20px; border-radius: 8px; } }
</style>

<div class="page-header">
    <i data-lucide="user-plus" style="color:var(--primary); width:28px; height:28px;"></i>
    Submit New Lead
</div>

<div class="form-card">
    <form id="ca-lead-form" enctype="multipart/form-data">
        <div class="form-group">
            <label class="form-label">Customer Name <span style="color:var(--danger)">*</span></label>
            <input type="text" name="customer_name" class="form-control" placeholder="Full Name" required>
        </div>

        <div class="form-group">
            <label class="form-label">Mobile Number <span style="color:var(--danger)">*</span></label>
            <input type="tel" name="mobile" class="form-control" placeholder="10-digit mobile number" pattern="[0-9]{10}" required>
        </div>

        <div class="form-group">
            <label class="form-label">Email Address</label>
            <input type="email" name="email" class="form-control" placeholder="your@email.com">
        </div>

        <div class="form-group">
            <label class="form-label">Loan Type <span style="color:var(--danger)">*</span></label>
            <select name="loan_type" id="loan_type" class="form-control" style="appearance: auto;" required onchange="updateSubTypes()">
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
            <label class="form-label">Loan Sub Type <span style="color:var(--danger)">*</span></label>
            <select name="loan_sub_type" id="loan_sub_type" class="form-control" style="appearance: auto;" required>
                <option value="">-- Select Sub Type --</option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Required Loan Amount <span style="color:var(--danger)">*</span></label>
            <input type="number" name="loan_amount" class="form-control" placeholder="e.g. 500000" required>
        </div>

        <div class="form-group">
            <label class="form-label">PAN Number (Optional)</label>
            <input type="text" name="pan_number" class="form-control" placeholder="ABCDE1234F">
        </div>

        <div class="form-group">
            <label class="form-label">Aadhar Number (Optional)</label>
            <input type="text" name="aadhar_number" class="form-control" placeholder="12-digit Aadhar">
        </div>

        
<div class="form-group" style="text-align:center; margin-bottom: 20px;">
    <label style="display:block; margin-bottom:10px; font-weight:600; font-size:13px; color:var(--text-main);">Take a Photo <span style="color:red">*</span></label>
    <input type="file" name="photo" id="photo" accept="image/*" capture="user" required style="display:none;" onchange="previewPhoto(event)">
    <button type="button" onclick="document.getElementById('photo').click()" style="background:#f1f5f9; border:2px dashed #cbd5e1; padding:20px; border-radius:12px; width:100%; cursor:pointer; color:#64748b; font-weight:600;">
        <i data-lucide="camera" style="width:32px; height:32px; margin-bottom:8px; display:block; margin:0 auto 8px;"></i>
        Click to Capture Photo
    </button>
    <img id="photo-preview" style="display:none; width:100px; height:100px; object-fit:cover; border-radius:12px; margin: 15px auto 0; border:2px solid var(--primary);">
</div>
<button type="submit" class="btn-submit ripple" id="submit-btn" onclick="vibrateAction()">
            <i data-lucide="send"></i> Submit Lead to Team
        </button>
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

<div id="toast" class="toast">
    <i data-lucide="check-circle"></i>
    <span id="toast-msg">Lead submitted successfully!</span>
</div>

<script>
const loanData = {
    "Home Loan": [
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
    document.getElementById('ca-lead-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = document.getElementById('submit-btn');
        btn.innerHTML = '<i data-lucide="loader-2" class="spin"></i> Submitting...';
        btn.disabled = true;
        lucide.createIcons();

        try {
            const formData = new FormData(e.target);
            const res = await fetch('../api.php?api=submit_lead', {
                  method: 'POST',
                  body: formData
              });

            const result = await res.json();
            
            if (result.success) {
                showToast('Lead submitted successfully! Our team will contact them.');
                e.target.reset();
            } else {
                showToast(result.error || 'Failed to submit lead', true);
            }
        } catch (err) {
            console.error(err);
            showToast('Network error. Please try again.', true);
        } finally {
            btn.innerHTML = '<i data-lucide="send"></i> Submit Lead to Team';
            btn.disabled = false;
            lucide.createIcons();
        }
    });

    function showToast(msg, isError = false) {
        const toast = document.getElementById('toast');
        const icon = isError ? 'alert-circle' : 'check-circle';
        
        toast.className = 'toast' + (isError ? ' error' : '');
        toast.innerHTML = `<i data-lucide="${icon}"></i> <span id="toast-msg">${msg}</span>`;
        lucide.createIcons();
        
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 4000);
    }
</script>

<?php require_once 'includes/footer.php'; ?>
