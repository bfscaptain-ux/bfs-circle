<?php
require_once '../config.php';

header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Staff';
$role = $_SESSION['role'] ?? 'Staff';

$stmt_emp = $db->prepare("SELECT * FROM employees WHERE user_id = ?");
$stmt_emp->execute([$user_id]);
$emp_data = $stmt_emp->fetch() ?: [];

$fullname = !empty($emp_data['full_name']) ? $emp_data['full_name'] : $username;
$emp_code = !empty($emp_data['emp_id']) ? $emp_data['emp_id'] : 'EMP-' . str_pad($user_id, 4, '0', STR_PAD_LEFT);
$dept = !empty($emp_data['department']) ? $emp_data['department'] : 'Field Operations';
$desig = !empty($emp_data['designation']) ? $emp_data['designation'] : 'Field Officer';
$comm = $emp_data['commission_rate'] ?? 1.0;
$mobile = $emp_data['mobile'] ?? '';
$email = $emp_data['official_email'] ?? '';
$personal_email = $emp_data['personal_email'] ?? '';
$photo_path = $emp_data['photo_path'] ?? '';
$bank_name = !empty($emp_data['bank_name']) ? $emp_data['bank_name'] : 'N/A';
$bank_acc = !empty($emp_data['bank_account_no']) ? $emp_data['bank_account_no'] : 'N/A';
$bank_ifsc = !empty($emp_data['bank_ifsc']) ? $emp_data['bank_ifsc'] : 'N/A';
$bank_holder = !empty($emp_data['bank_holder_name']) ? $emp_data['bank_holder_name'] : $fullname;
$pan_num = !empty($emp_data['pan_number']) ? $emp_data['pan_number'] : 'N/A';
$aadhaar_num = !empty($emp_data['aadhar_number']) ? $emp_data['aadhar_number'] : 'N/A';

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>My Profile - BFS Field App</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary: #FF7A00;
            --primary-dark: #E66A00;
            --primary-light: #FFF0E5;
            --secondary: #0F172A;
            --bg-color: #F8FAFC;
            --card-bg: #FFFFFF;
            --text-main: #0F172A;
            --text-muted: #64748B;
            --border-color: #E2E8F0;
            --success: #10B981;
            --danger: #EF4444;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; -webkit-tap-highlight-color: transparent; }
        
        body {
            background-color: var(--bg-color);
            color: var(--text-main);
            padding-bottom: 90px;
        }

        /* App Header */
        .app-header {
            background: linear-gradient(135deg, #FF7A00 0%, #FF5500 100%);
            color: white;
            padding: 20px 16px 50px 16px;
            border-bottom-left-radius: 24px;
            border-bottom-right-radius: 24px;
            box-shadow: 0 10px 25px rgba(255, 122, 0, 0.25);
            position: relative;
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .brand-title {
            font-size: 18px;
            font-weight: 800;
            letter-spacing: -0.5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .logout-btn-top {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.35);
            padding: 7px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
            backdrop-filter: blur(5px);
            transition: all 0.2s ease;
        }
        .logout-btn-top:active {
            transform: scale(0.95);
            background: rgba(239, 68, 68, 0.8);
        }

        /* Container & Floating Profile Card */
        .profile-wrapper {
            padding: 0 16px;
            margin-top: -40px;
        }

        .profile-card {
            background: white;
            border-radius: 20px;
            padding: 24px 20px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.06);
            border: 1px solid rgba(226, 232, 240, 0.8);
            text-align: center;
            position: relative;
        }

        /* Avatar Container with Camera Overlay */
        .avatar-container {
            position: relative;
            width: 96px;
            height: 96px;
            margin: 0 auto 14px auto;
        }

        .avatar-img-box {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            overflow: hidden;
            border: 4px solid white;
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
            background: linear-gradient(135deg, var(--primary), #3B82F6);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 36px;
            font-weight: 800;
        }

        .avatar-img-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .camera-overlay {
            position: absolute;
            bottom: 2px;
            right: 2px;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2);
            cursor: pointer;
            border: 2px solid white;
            transition: transform 0.2s;
        }

        .camera-overlay:active {
            transform: scale(0.9);
        }

        .user-fullname {
            font-size: 20px;
            font-weight: 700;
            color: var(--secondary);
            margin-bottom: 2px;
        }

        .user-desig {
            font-size: 13px;
            color: var(--text-muted);
            font-weight: 500;
            margin-bottom: 12px;
        }

        .badge-row {
            display: flex;
            justify-content: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 8px;
        }

        .badge-chip {
            background: #F1F5F9;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            color: #475569;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .badge-chip.primary {
            background: var(--primary-light);
            color: var(--primary-dark);
        }

        /* Navigation Tabs */
        .tab-nav {
            display: flex;
            background: #F1F5F9;
            padding: 5px;
            border-radius: 16px;
            margin-top: 22px;
            gap: 4px;
            border: 1px solid #E2E8F0;
        }

        .tab-btn {
            flex: 1;
            padding: 11px 6px;
            border: 1.5px solid transparent;
            background: transparent;
            font-size: 12.5px;
            font-weight: 600;
            color: #64748B;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            white-space: nowrap;
            outline: none;
        }

        .tab-btn i {
            font-size: 13px;
            transition: all 0.2s ease;
        }

        .tab-btn.active {
            background: #FFFFFF;
            color: var(--primary);
            font-weight: 700;
            box-shadow: 0 4px 14px rgba(255, 122, 0, 0.16), 0 1px 3px rgba(0,0,0,0.04);
            border-color: rgba(255, 122, 0, 0.35);
        }

        .tab-btn.active i {
            color: var(--primary);
            transform: scale(1.15);
        }

        /* Tab Content Cards */
        .tab-content {
            display: none;
            margin-top: 16px;
        }
        .tab-content.active {
            display: block;
        }

        .info-card {
            background: white;
            border-radius: 16px;
            padding: 18px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            border: 1px solid var(--border-color);
            margin-bottom: 16px;
        }

        .info-card-title {
            font-size: 14px;
            font-weight: 700;
            color: var(--secondary);
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-card-title i {
            color: var(--primary);
        }

        .form-group {
            margin-bottom: 14px;
            text-align: left;
        }

        .form-label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 6px;
        }

        .form-control {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            font-size: 14px;
            color: var(--text-main);
            background: #FAFAFA;
            outline: none;
            transition: border-color 0.2s;
        }

        .form-control:focus {
            border-color: var(--primary);
            background: white;
        }

        .form-control[readonly] {
            background: #F1F5F9;
            color: #64748B;
            cursor: not-allowed;
        }

        .password-input-wrapper {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            cursor: pointer;
            font-size: 14px;
            padding: 4px;
        }

        .btn-submit {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #FF7A00 0%, #FF5500 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 6px 18px rgba(255, 122, 0, 0.3);
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-submit:active {
            transform: scale(0.98);
            box-shadow: 0 2px 8px rgba(255, 122, 0, 0.2);
        }

        /* Read-only Info List */
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px dashed var(--border-color);
        }
        .info-row:last-child {
            border-bottom: none;
        }

        .info-key {
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 500;
        }

        .info-val {
            font-size: 13px;
            color: var(--text-main);
            font-weight: 600;
        }

        /* Bottom Nav */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            display: flex;
            justify-content: space-around;
            padding: 10px 0;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.05);
            z-index: 100;
        }

        .nav-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 10px;
            font-weight: 500;
        }

        .nav-btn.active { color: var(--primary); }
        .nav-btn i { font-size: 20px; }

        .fab {
            position: absolute;
            top: -20px;
            left: 50%;
            transform: translateX(-50%);
            height: 44px;
            padding: 0 16px;
            border-radius: 22px;
            background: linear-gradient(135deg, #FF6B00 0%, #FF9A44 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 600;
            box-shadow: 0 6px 15px rgba(255, 107, 0, 0.35);
            text-decoration: none;
            white-space: nowrap;
            gap: 6px;
        }
    </style>
</head>
<body>

    <!-- Header Banner -->
    <div class="app-header">
        <div class="header-top">
            <div class="brand-title">
                <i class="fas fa-shield-alt"></i> BFS Field App
            </div>
            <a href="../logout.php" onclick="return confirm('Are you sure you want to log out of your account?');" class="logout-btn-top">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>

    <!-- Main Content Wrapper -->
    <div class="profile-wrapper">
        <form id="profileForm" onsubmit="handleProfileSubmit(event)" enctype="multipart/form-data">
            <input type="hidden" name="username" value="<?= htmlspecialchars($username); ?>">

            <!-- Profile Summary Card -->
            <div class="profile-card">
                <div class="avatar-container">
                    <div class="avatar-img-box" id="avatarBox">
                        <?php if (!empty($photo_path)): ?>
                            <img id="avatarPreview" src="../uploads/employees/<?= htmlspecialchars($photo_path); ?>" alt="Profile Photo">
                        <?php else: ?>
                            <span id="avatarInitials"><?= strtoupper(substr($fullname, 0, 1)); ?></span>
                            <img id="avatarPreview" src="" alt="Profile Photo" style="display:none;">
                        <?php endif; ?>
                    </div>
                    <label for="profile_photo_input" class="camera-overlay" title="Upload New Photo">
                        <i class="fas fa-camera"></i>
                    </label>
                    <input type="file" id="profile_photo_input" name="profile_photo" accept="image/*" style="display:none;" onchange="previewProfilePhoto(this)">
                </div>

                <h2 class="user-fullname"><?= htmlspecialchars($fullname); ?></h2>
                <div class="user-desig"><?= htmlspecialchars($desig); ?> &bull; <?= htmlspecialchars($dept); ?></div>

                <div class="badge-row">
                    <span class="badge-chip primary"><i class="fas fa-id-badge"></i> ID: <?= htmlspecialchars($emp_code); ?></span>
                    <span class="badge-chip"><i class="fas fa-percent"></i> Comm. Rate: <?= htmlspecialchars($comm); ?>%</span>
                </div>

                <!-- Navigation Tabs -->
                <div class="tab-nav">
                    <button type="button" class="tab-btn active" onclick="switchTab('personal', this)">
                        <i class="fas fa-user-circle"></i> Profile
                    </button>
                    <button type="button" class="tab-btn" onclick="switchTab('security', this)">
                        <i class="fas fa-lock"></i> Password
                    </button>
                    <button type="button" class="tab-btn" onclick="switchTab('bank', this)">
                        <i class="fas fa-university"></i> Bank Info
                    </button>
                </div>
            </div>

            <!-- Tab 1: Personal Details -->
            <div id="tab-personal" class="tab-content active">
                <div class="info-card">
                    <div class="info-card-title">
                        <i class="fas fa-user-circle"></i> Personal Information
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Full Name <span style="color:var(--danger);">*</span></label>
                        <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($fullname); ?>" placeholder="Enter full name" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Phone / Mobile Number <span style="color:var(--danger);">*</span></label>
                        <input type="tel" name="mobile" class="form-control" value="<?= htmlspecialchars($mobile); ?>" placeholder="Enter 10-digit mobile number" required pattern="[0-9]{10}">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Personal Email Address</label>
                        <input type="email" name="personal_email" class="form-control" value="<?= htmlspecialchars($personal_email); ?>" placeholder="name@example.com">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Official Work Email</label>
                        <input type="email" class="form-control" value="<?= htmlspecialchars($email); ?>" readonly>
                    </div>
                </div>

                <button type="submit" class="btn-submit" id="savePersonalBtn">
                    <i class="fas fa-check-circle"></i> Save Profile Details
                </button>
            </div>

            <!-- Tab 2: Security & Password Change -->
            <div id="tab-security" class="tab-content">
                <div class="info-card">
                    <div class="info-card-title">
                        <i class="fas fa-key"></i> Update Security Password
                    </div>

                    <div class="form-group">
                        <label class="form-label">New Password</label>
                        <div class="password-input-wrapper">
                            <input type="password" id="newPassword" name="password" class="form-control" placeholder="Minimum 6 characters">
                            <i class="fas fa-eye toggle-password" onclick="togglePassVisibility('newPassword', this)"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Confirm New Password</label>
                        <div class="password-input-wrapper">
                            <input type="password" id="confirmPassword" class="form-control" placeholder="Re-enter new password">
                            <i class="fas fa-eye toggle-password" onclick="togglePassVisibility('confirmPassword', this)"></i>
                        </div>
                    </div>
                    
                    <p style="font-size:11px; color:var(--text-muted); line-height:1.4;">
                        <i class="fas fa-info-circle"></i> Leave blank if you do not want to change your current password.
                    </p>
                </div>

                <button type="submit" class="btn-submit" id="saveSecurityBtn">
                    <i class="fas fa-shield-alt"></i> Update Password
                </button>
            </div>

            <!-- Tab 3: Bank & Payout Remittance Details (Verified Card) -->
            <div id="tab-bank" class="tab-content">
                <div class="info-card">
                    <div class="info-card-title" style="justify-content:space-between;">
                        <span><i class="fas fa-wallet"></i> Payout & Bank Details</span>
                        <span style="background:#DCFCE7; color:#15803D; font-size:10px; padding:3px 8px; border-radius:12px; font-weight:700;">VERIFIED</span>
                    </div>

                    <div class="info-row">
                        <span class="info-key">Account Holder</span>
                        <span class="info-val"><?= htmlspecialchars($bank_holder); ?></span>
                    </div>

                    <div class="info-row">
                        <span class="info-key">Bank Name</span>
                        <span class="info-val"><?= htmlspecialchars($bank_name); ?></span>
                    </div>

                    <div class="info-row">
                        <span class="info-key">Account Number</span>
                        <span class="info-val"><?= htmlspecialchars($bank_acc); ?></span>
                    </div>

                    <div class="info-row">
                        <span class="info-key">IFSC Code</span>
                        <span class="info-val"><?= htmlspecialchars($bank_ifsc); ?></span>
                    </div>

                    <div class="info-row">
                        <span class="info-key">PAN Number</span>
                        <span class="info-val"><?= htmlspecialchars($pan_num); ?></span>
                    </div>

                    <div class="info-row">
                        <span class="info-key">Aadhaar Card</span>
                        <span class="info-val"><?= htmlspecialchars($aadhaar_num); ?></span>
                    </div>
                </div>

                <p style="font-size:11px; color:var(--text-muted); text-align:center; padding:0 10px;">
                    <i class="fas fa-lock"></i> Bank payout details are locked for security. Contact Admin/HR to update payout account details.
                </p>
            </div>

        </form>

        <!-- Danger Logout Box -->
        <div style="margin-top: 24px; text-align: center;">
            <a href="../logout.php" onclick="return confirm('Are you sure you want to log out of your account?');" style="display:inline-flex; align-items:center; justify-content:center; gap:8px; width:100%; padding:14px; background:#FEF2F2; color:#DC2626; border:1px solid #FCA5A5; border-radius:14px; font-size:14px; font-weight:700; text-decoration:none;">
                <i class="fas fa-power-off"></i> Log Out of Account
            </a>
        </div>

    </div>

    <!-- Bottom Navigation Bar -->
    <div class="bottom-nav">
        <a href="index.php" class="nav-btn <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">
            <i class="fas fa-home"></i>
            <span>Home</span>
        </a>
        <a href="visits.php" class="nav-btn <?= basename($_SERVER['PHP_SELF']) == 'visits.php' ? 'active' : '' ?>">
            <i class="fas fa-list-ul"></i>
            <span>My Visits</span>
        </a>
        <div style="width: 110px; position: relative;">
            <a href="add_visit.php" class="fab">
                <i class="fas fa-plus"></i> <span>Start Visit</span>
            </a>
        </div>
        <a href="files.php" class="nav-btn <?= basename($_SERVER['PHP_SELF']) == 'files.php' ? 'active' : '' ?>">
            <i class="fas fa-file-invoice"></i>
            <span>Files</span>
        </a>
        <a href="profile.php" class="nav-btn <?= basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : '' ?>">
            <i class="fas fa-user"></i>
            <span>Profile</span>
        </a>
    </div>

    <script>
        // Tab Switcher
        function switchTab(tabId, btn) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            btn.classList.add('active');
            document.getElementById('tab-' + tabId).classList.add('active');
        }

        // Live Photo Preview Handler
        function previewProfilePhoto(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const imgPreview = document.getElementById('avatarPreview');
                    const initials = document.getElementById('avatarInitials');
                    
                    imgPreview.src = e.target.result;
                    imgPreview.style.display = 'block';
                    if (initials) initials.style.display = 'none';
                    
                    Swal.fire({
                        toast: true,
                        position: 'top',
                        icon: 'info',
                        title: 'Photo selected. Click "Save Profile Details" to upload.',
                        showConfirmButton: false,
                        timer: 3000
                    });
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Password Visibility Toggle
        function togglePassVisibility(inputId, icon) {
            const input = document.getElementById(inputId);
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Form Submit Handler
        async function handleProfileSubmit(e) {
            e.preventDefault();
            
            const newPass = document.getElementById('newPassword').value;
            const confPass = document.getElementById('confirmPassword').value;
            
            if (newPass !== '' || confPass !== '') {
                if (newPass.length < 6) {
                    Swal.fire({ icon: 'warning', title: 'Invalid Password', text: 'Password must be at least 6 characters long.' });
                    return;
                }
                if (newPass !== confPass) {
                    Swal.fire({ icon: 'error', title: 'Password Mismatch', text: 'New password and confirm password do not match.' });
                    return;
                }
            }

            const activeBtn = document.querySelector('.tab-content.active .btn-submit');
            const origHtml = activeBtn ? activeBtn.innerHTML : '';
            if (activeBtn) {
                activeBtn.disabled = true;
                activeBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving Profile...';
            }

            const fd = new FormData(e.target);

            try {
                const res = await fetch('../api.php?api=update_staff_profile', {
                    method: 'POST',
                    body: fd
                });
                const data = await res.json();
                
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Profile Updated!',
                        text: 'Your profile details have been saved successfully.',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Update Failed',
                        text: data.error || 'Failed to update profile.'
                    });
                    if (activeBtn) {
                        activeBtn.disabled = false;
                        activeBtn.innerHTML = origHtml;
                    }
                }
            } catch (err) {
                Swal.fire({
                    icon: 'error',
                    title: 'Network Error',
                    text: 'Unable to connect to server. Please try again.'
                });
                if (activeBtn) {
                    activeBtn.disabled = false;
                    activeBtn.innerHTML = origHtml;
                }
            }
        }
    </script>
</body>
</html>
