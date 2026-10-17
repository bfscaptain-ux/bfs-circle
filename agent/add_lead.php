<?php
require_once 'includes/header.php';

// Get Agent Details
$stmt = $db->prepare("SELECT * FROM referrals WHERE user_id = ?");
$stmt->execute([$agent_id]);
$agent = $stmt->fetch();
$referral_id = $agent['referral_id'] ?? '';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_name = trim($_POST['customer_name'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $loan_type = trim($_POST['loan_type'] ?? '');
    $loan_sub_type = trim($_POST['loan_sub_type'] ?? '');
    $requirement = $loan_type . ($loan_sub_type ? ' - ' . $loan_sub_type : '');
    $loan_amount = floatval($_POST['loan_amount'] ?? 0);
    $pan_number = trim($_POST['pan_number'] ?? '');
    $aadhar_number = trim($_POST['aadhar_number'] ?? '');
    
    if (empty($customer_name) || empty($mobile) || $loan_amount <= 0) {
        $error = "Please fill all required fields correctly.";
    } else {
        // Generate unique loan_id
        $loan_id = 'L' . date('Ymd') . rand(1000, 9999);
        
        $notes = ($pan_number ? "PAN: " . $pan_number : "") . ($aadhar_number ? " | Aadhar: " . $aadhar_number : "");
    
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

$stmt_insert = $db->prepare("INSERT INTO leads (lead_name, mobile, email, requirement, loan_amount, notes, lead_source, stage, priority, added_by, photo) VALUES (?, ?, ?, ?, ?, ?, 'Agent Referral', 'New Lead', 'Warm', ?, ?)");
try {
$stmt_insert->execute([$customer_name, $mobile, $email, $requirement, $loan_amount, $notes, $agent_username, $photo_path]);
            $message = "Lead added successfully!";
        } catch(PDOException $e) {
            $error = "Error adding lead: " . $e->getMessage();
        }
    }
}
?>

<div style="margin-bottom: 20px;">
    <h2 style="font-family: 'Outfit'; font-size: 22px; color: var(--text-primary);">Add New Lead</h2>
    <p style="color: var(--text-muted); font-size: 14px;">Enter customer details to submit a new loan lead.</p>
</div>

<?php if($message): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        showNotification('<?php echo htmlspecialchars($message); ?>', 'success');
        setTimeout(() => { window.location.href = 'leads.php'; }, 1500);
    });
</script>
<?php endif; ?>

<?php if($error): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        showNotification('<?php echo htmlspecialchars($error); ?>', 'error');
    });
</script>
<?php endif; ?>

<div class="card">
    <form method="POST" action="" enctype="multipart/form-data">
        <div class="form-group">
            <label for="customer_name">Customer Name <span style="color:red">*</span></label>
            <input type="text" id="customer_name" name="customer_name" required placeholder="Full Name">
        </div>
        
        <div class="form-group">
            <label for="mobile">Mobile Number <span style="color:red">*</span></label>
            <input type="tel" id="mobile" name="mobile" required placeholder="10-digit mobile number" pattern="[0-9]{10}">
        </div>
        
        <div class="form-group">
            <label for="email">Email Address (Optional)</label>
            <input type="email" id="email" name="email" placeholder="your@email.com">
        </div>
        
        <style>
        .category-picker-label { font-size: 13px; font-weight: 700; color: var(--text-primary); margin-bottom: 8px; display: flex; align-items: center; gap: 6px; }
        .cat-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-bottom: 16px; }
        .cat-card-btn { background: #ffffff; border: 1.5px solid #e2e8f0; border-radius: 14px; padding: 12px 10px; display: flex; align-items: center; gap: 10px; cursor: pointer; transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1); box-shadow: 0 2px 6px rgba(0,0,0,0.02); }
        .cat-card-btn:hover { border-color: var(--primary); transform: translateY(-1px); }
        .cat-card-btn.active { border-color: var(--primary); background: #ecfdf5; box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.15); }
        .cat-card-icon { width: 32px; height: 32px; border-radius: 10px; background: #f1f5f9; color: var(--primary); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .cat-card-btn.active .cat-card-icon { background: var(--primary); color: #ffffff; }
        .cat-card-text { font-size: 12px; font-weight: 700; color: var(--text-primary); line-height: 1.25; }
        .subtype-chips-wrapper { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; margin-bottom: 16px; }
        .chip-btn { padding: 8px 14px; border-radius: 20px; background: #ffffff; border: 1.5px solid #cbd5e1; font-size: 12px; font-weight: 700; color: #334155; cursor: pointer; transition: all 0.15s ease; }
        .chip-btn:hover { border-color: var(--primary); color: var(--primary); }
        .chip-btn.active { background: var(--primary); color: #ffffff; border-color: var(--primary); box-shadow: 0 3px 8px rgba(5, 150, 105, 0.25); }
        </style>

        <!-- VISUAL CATEGORY PICKER FOR APP -->
        <div class="form-group">
            <div class="category-picker-label"><i data-lucide="layers" style="width:16px; color:var(--primary);"></i> Select Loan Category <span style="color:red">*</span></div>
            <div class="cat-grid">
                <div class="cat-card-btn" onclick="selectVisualCat('Home Loan', this)">
                    <div class="cat-card-icon"><i data-lucide="home" style="width:18px;"></i></div>
                    <div class="cat-card-text">Home Loan</div>
                </div>
                <div class="cat-card-btn" onclick="selectVisualCat('Loan Against Property (LAP)', this)">
                    <div class="cat-card-icon"><i data-lucide="building-2" style="width:18px;"></i></div>
                    <div class="cat-card-text">LAP (Property)</div>
                </div>
                <div class="cat-card-btn" onclick="selectVisualCat('Business Loan', this)">
                    <div class="cat-card-icon"><i data-lucide="briefcase" style="width:18px;"></i></div>
                    <div class="cat-card-text">Business Loan</div>
                </div>
                <div class="cat-card-btn" onclick="selectVisualCat('Personal Loan', this)">
                    <div class="cat-card-icon"><i data-lucide="user" style="width:18px;"></i></div>
                    <div class="cat-card-text">Personal Loan</div>
                </div>
                <div class="cat-card-btn" onclick="selectVisualCat('Auto / Vehicle Loan', this)">
                    <div class="cat-card-icon"><i data-lucide="car" style="width:18px;"></i></div>
                    <div class="cat-card-text">Vehicle Loan</div>
                </div>
                <div class="cat-card-btn" onclick="selectVisualCat('Commercial Property Loan', this)">
                    <div class="cat-card-icon"><i data-lucide="building" style="width:18px;"></i></div>
                    <div class="cat-card-text">Commercial</div>
                </div>
            </div>
        </div>

        <div class="form-group" style="display:none;">
            <select id="loan_type" name="loan_type" required onchange="updateSubTypes()">
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

        <!-- VISUAL SUB-TYPE CHIPS -->
        <div class="form-group" id="visualSubGroup" style="display:none;">
            <div class="category-picker-label"><i data-lucide="check-circle-2" style="width:16px; color:var(--primary);"></i> Select Sub-Type Option <span style="color:red">*</span></div>
            <div class="subtype-chips-wrapper" id="visualSubChips"></div>
        </div>
        
        <div class="form-group" style="display:none;">
            <select id="loan_sub_type" name="loan_sub_type" required onchange="syncSubChip(this.value)">
                <option value="">-- Select Sub Type --</option>
            </select>
        </div>
        
        <div class="form-group">
            <label for="loan_amount">Required Loan Amount <span style="color:red">*</span></label>
            <input type="number" id="loan_amount" name="loan_amount" required placeholder="e.g. 500000" min="1000">
        </div>
        
        <div class="form-group">
            <label for="pan_number">PAN Number (Optional)</label>
            <input type="text" id="pan_number" name="pan_number" placeholder="ABCDE1234F">
        </div>
        
        <div class="form-group">
            <label for="aadhar_number">Aadhar Number (Optional)</label>
            <input type="text" id="aadhar_number" name="aadhar_number" placeholder="12-digit Aadhar">
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
<button type="submit" class="btn" style="margin-top: 10px;">Submit Lead</button>
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
    ],
    "Secured Loans": [
        "Home Loan - New Purchase",
        "Home Loan - Balance Transfer & Top-up",
        "Loan Against Property (LAP)",
        "Plot Purchase & Construction",
        "Commercial Property Loan"
    ],
    "Unsecured Loans": [
        "Personal Loan for Salaried",
        "Personal Loan for Self Employed",
        "Unsecured Business Loan",
        "Professional Loan (Doctor/CA/CS)"
    ],
    "Working Capital": [
        "Cash Credit (CC) / Overdraft (OD)",
        "Letter of Credit (LC) / Bank Guarantee (BG)",
        "Trade Credit / Invoice Discounting",
        "Machinery / Equipment Loan"
    ],
    "Project Finance": [
        "Real Estate Project Finance",
        "Industrial & Factory Construction Finance",
        "Infrastructure & Commercial Project Finance"
    ]
};

function selectVisualCat(catName, el) {
    document.querySelectorAll('.cat-card-btn').forEach(c => c.classList.remove('active'));
    if(el) el.classList.add('active');
    
    const typeSelect = document.getElementById('loan_type');
    typeSelect.value = catName;
    updateSubTypes();
}

function updateSubTypes() {
    const typeSelect = document.getElementById('loan_type');
    const subTypeSelect = document.getElementById('loan_sub_type');
    const visualGroup = document.getElementById('visualSubGroup');
    const visualChips = document.getElementById('visualSubChips');
    
    subTypeSelect.innerHTML = '<option value="">-- Select Sub Type --</option>';
    if (visualChips) visualChips.innerHTML = '';
    
    const selectedType = typeSelect.value;
    
    // Sync category cards if changed via select dropdown
    document.querySelectorAll('.cat-card-btn').forEach(c => {
        const txt = c.querySelector('.cat-card-text')?.innerText || '';
        if (selectedType && (selectedType.includes(txt) || txt.includes(selectedType.split(' ')[0]))) {
            c.classList.add('active');
        } else {
            c.classList.remove('active');
        }
    });

    if (selectedType && loanData[selectedType]) {
        if (visualGroup) visualGroup.style.display = 'block';
        loanData[selectedType].forEach((subType, idx) => {
            const option = document.createElement('option');
            option.value = subType;
            option.textContent = subType;
            subTypeSelect.appendChild(option);

            // Create Visual Pill Chip
            if (visualChips) {
                const chip = document.createElement('div');
                chip.className = 'chip-btn' + (idx === 0 ? ' active' : '');
                chip.innerText = subType;
                chip.onclick = function() {
                    document.querySelectorAll('.chip-btn').forEach(cb => cb.classList.remove('active'));
                    chip.classList.add('active');
                    subTypeSelect.value = subType;
                };
                visualChips.appendChild(chip);
            }

            if (idx === 0) {
                subTypeSelect.value = subType;
            }
        });
    } else {
        if (visualGroup) visualGroup.style.display = 'none';
    }
}

function syncSubChip(val) {
    document.querySelectorAll('.chip-btn').forEach(cb => {
        if (cb.innerText === val) cb.classList.add('active');
        else cb.classList.remove('active');
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
