<?php
require_once 'includes/header.php';

$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$builder_id]);
$user_info = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

// Also get referral info
$stmt_ref = $db->prepare("SELECT * FROM referrals WHERE user_id = ? LIMIT 1");
$stmt_ref->execute([$builder_id]);
$partner = $stmt_ref->fetch(PDO::FETCH_ASSOC);

if (!$partner) {
    $stmt_ref = $db->prepare("SELECT * FROM referrals WHERE mobile = ? OR full_name = ? ORDER BY id DESC LIMIT 1");
    $stmt_ref->execute([$builder_username, $builder_name]);
    $partner = $stmt_ref->fetch(PDO::FETCH_ASSOC);
}

$display_name = !empty($user_info['name']) ? $user_info['name'] : ($partner['full_name'] ?? $builder_name);
$phone = !empty($partner['mobile']) ? $partner['mobile'] : ($user_info['username'] ?? '');
$email = !empty($partner['email']) ? $partner['email'] : ($user_info['email'] ?? '');
$city_state = $partner['city_state'] ?? '';
$bank_name = $partner['bank_name'] ?? '';
$account_number = $partner['account_number'] ?? '';
$ifsc_code = $partner['ifsc_code'] ?? '';
$upi_id = $partner['upi_id'] ?? '';
$profile_photo = !empty($user_info['profile_photo']) ? $user_info['profile_photo'] : ($partner['profile_photo'] ?? '');
$joined = !empty($user_info['created_at']) ? date('d M Y', strtotime($user_info['created_at'])) : date('d M Y');
$ref_code = $partner['referral_id'] ?? ('REF-BLD-' . $builder_id);

$has_avatar = false;
$avatar_url = '';
if (!empty($profile_photo)) {
    $clean_p = ltrim($profile_photo, './');
    if (file_exists(__DIR__ . '/../' . $clean_p)) {
        $has_avatar = true;
        $avatar_url = '../' . $clean_p;
    }
}
?>

<style>
    .profile-hero {
        background: linear-gradient(135deg, #0369a1 0%, #0c4a6e 100%);
        border-radius: 20px;
        padding: 30px 20px;
        color: white;
        text-align: center;
        margin-bottom: 24px;
        position: relative;
        box-shadow: 0 10px 30px rgba(3, 105, 161, 0.25);
    }
    .avatar-wrapper {
        position: relative;
        width: 104px;
        height: 104px;
        margin: 0 auto 16px auto;
        cursor: pointer;
    }
    .avatar-circle {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        background: linear-gradient(135deg, #f97316, #ea580c);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 40px;
        font-weight: 800;
        font-family: 'Outfit', sans-serif;
        color: white;
        border: 4px solid #ffffff;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.25);
        overflow: hidden;
        position: relative;
    }
    .avatar-circle img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    .avatar-edit-badge {
        position: absolute;
        bottom: 2px;
        right: 2px;
        background: #f97316;
        color: white;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid white;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        transition: transform 0.2s, background 0.2s;
    }
    .avatar-wrapper:hover .avatar-edit-badge {
        transform: scale(1.1);
        background: #ea580c;
    }
    .form-section-title {
        font-family: 'Outfit', sans-serif;
        font-size: 16px;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .form-group {
        margin-bottom: 16px;
    }
    .form-group label {
        display: block;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        color: var(--text-muted);
        margin-bottom: 6px;
        letter-spacing: 0.5px;
    }
    .input-with-icon {
        position: relative;
        display: flex;
        align-items: center;
    }
    .input-with-icon i.input-icon {
        position: absolute;
        left: 14px;
        width: 18px;
        height: 18px;
        color: var(--text-muted);
        pointer-events: none;
    }
    .input-with-icon input {
        width: 100%;
        padding: 12px 14px 12px 42px;
        border: 1.5px solid var(--border);
        border-radius: 12px;
        font-size: 14px;
        background: #fff;
        color: var(--text-primary);
        font-weight: 500;
        transition: all 0.2s;
    }
    .input-with-icon input:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
        outline: none;
    }
    .pwd-toggle {
        position: absolute;
        right: 14px;
        cursor: pointer;
        color: var(--text-muted);
        padding: 4px;
        display: flex;
        align-items: center;
    }
    .pwd-toggle:hover {
        color: var(--primary);
    }
    .btn-save {
        width: 100%;
        padding: 14px;
        background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
        color: white;
        border: none;
        border-radius: 12px;
        font-size: 16px;
        font-weight: 700;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        box-shadow: 0 6px 18px rgba(2, 132, 199, 0.3);
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .btn-save:active {
        transform: scale(0.98);
    }
    .btn-save:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
    .photo-upload-note {
        font-size: 11px;
        color: rgba(255, 255, 255, 0.85);
        margin-top: 6px;
    }
</style>

<!-- Profile Hero -->
<div class="profile-hero">
    <div class="avatar-wrapper" onclick="document.getElementById('profile_photo_input').click();" title="Click to change profile photo">
        <div class="avatar-circle" id="avatarPreviewContainer">
            <?php if ($has_avatar): ?>
                <img src="<?php echo htmlspecialchars($avatar_url); ?>" id="avatarPreviewImg" alt="Profile Photo">
            <?php else: ?>
                <span id="avatarInitials"><?php echo strtoupper(substr($display_name, 0, 1)); ?></span>
            <?php endif; ?>
        </div>
        <div class="avatar-edit-badge" title="Upload new photo">
            <i data-lucide="camera" style="width: 16px; height: 16px;"></i>
        </div>
    </div>
    
    <input type="file" id="profile_photo_input" accept="image/jpeg,image/png,image/webp,image/gif" style="display: none;" onchange="previewSelectedPhoto(this);">

    <h2 style="font-family: 'Outfit'; font-size: 22px; font-weight: 700; margin-bottom: 4px;" id="heroDisplayName">
        <?php echo htmlspecialchars($display_name); ?>
    </h2>
    <p style="font-size: 13px; opacity: 0.85; margin-bottom: 12px;">Builder / Real Estate Partner</p>

    <div style="display: flex; align-items: center; justify-content: center; gap: 8px; flex-wrap: wrap;">
        <span style="display: inline-flex; align-items: center; gap: 4px; background: rgba(255,255,255,0.18); backdrop-filter: blur(4px); padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; border: 1px solid rgba(255,255,255,0.25);">
            <i data-lucide="tag" style="width: 12px; height: 12px;"></i> <?php echo htmlspecialchars($ref_code); ?>
        </span>
        <span style="display: inline-flex; align-items: center; gap: 4px; background: #dcfce7; color: #166534; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 700;">
            <i data-lucide="check-circle-2" style="width: 13px; height: 13px;"></i> Active Partner
        </span>
    </div>
    <div class="photo-upload-note" id="photoChangeStatus">
        Tap camera to change photo (JPG, PNG, WebP &bull; Max 5MB)
    </div>
</div>

<form id="partnerProfileForm" enctype="multipart/form-data" onsubmit="savePartnerProfile(event);">
    <!-- Section 1: Personal Details -->
    <div class="card" style="margin-bottom: 18px;">
        <div class="form-section-title">
            <i data-lucide="user" style="width: 18px; color: var(--primary);"></i>
            <span>Personal & Contact Information</span>
        </div>

        <div class="form-group">
            <label for="input_full_name">Full Name *</label>
            <div class="input-with-icon">
                <i data-lucide="user-check" class="input-icon"></i>
                <input type="text" id="input_full_name" name="full_name" required value="<?php echo htmlspecialchars($display_name); ?>" placeholder="Enter your full name">
            </div>
        </div>

        <div class="form-group">
            <label for="input_mobile">Mobile Number *</label>
            <div class="input-with-icon">
                <i data-lucide="phone" class="input-icon"></i>
                <input type="tel" id="input_mobile" name="mobile" required value="<?php echo htmlspecialchars($phone); ?>" placeholder="10-digit mobile number" maxlength="15">
            </div>
        </div>

        <div class="form-group">
            <label for="input_email">Email Address</label>
            <div class="input-with-icon">
                <i data-lucide="mail" class="input-icon"></i>
                <input type="email" id="input_email" name="email" value="<?php echo htmlspecialchars($email); ?>" placeholder="builder@example.com">
            </div>
        </div>

        <div class="form-group">
            <label for="input_city_state">City & State</label>
            <div class="input-with-icon">
                <i data-lucide="map-pin" class="input-icon"></i>
                <input type="text" id="input_city_state" name="city_state" value="<?php echo htmlspecialchars($city_state); ?>" placeholder="e.g. Mumbai, Maharashtra">
            </div>
        </div>
    </div>

    <!-- Section 2: Banking & Payout Details -->
    <div class="card" style="margin-bottom: 18px;">
        <div class="form-section-title">
            <i data-lucide="landmark" style="width: 18px; color: var(--accent);"></i>
            <span>Banking & Commission Payout Details</span>
        </div>

        <div class="form-group">
            <label for="input_bank_name">Bank Name</label>
            <div class="input-with-icon">
                <i data-lucide="building" class="input-icon"></i>
                <input type="text" id="input_bank_name" name="bank_name" value="<?php echo htmlspecialchars($bank_name); ?>" placeholder="e.g. HDFC Bank / ICICI">
            </div>
        </div>

        <div class="form-group">
            <label for="input_account_number">Bank Account Number</label>
            <div class="input-with-icon">
                <i data-lucide="credit-card" class="input-icon"></i>
                <input type="text" id="input_account_number" name="account_number" value="<?php echo htmlspecialchars($account_number); ?>" placeholder="Account number for payouts">
            </div>
        </div>

        <div class="form-group">
            <label for="input_ifsc_code">IFSC Code</label>
            <div class="input-with-icon">
                <i data-lucide="hash" class="input-icon"></i>
                <input type="text" id="input_ifsc_code" name="ifsc_code" style="text-transform: uppercase;" value="<?php echo htmlspecialchars($ifsc_code); ?>" placeholder="e.g. HDFC0001234">
            </div>
        </div>

        <div class="form-group">
            <label for="input_upi_id">UPI ID / VPA</label>
            <div class="input-with-icon">
                <i data-lucide="smartphone" class="input-icon"></i>
                <input type="text" id="input_upi_id" name="upi_id" value="<?php echo htmlspecialchars($upi_id); ?>" placeholder="e.g. username@okhdfcbank">
            </div>
        </div>
    </div>

    <!-- Section 3: Password & Security -->
    <div class="card" style="margin-bottom: 24px;">
        <div class="form-section-title">
            <i data-lucide="lock" style="width: 18px; color: #f59e0b;"></i>
            <span>Security & Change Password</span>
        </div>
        <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 14px;">Leave password fields blank if you do not want to change your password.</p>

        <div class="form-group">
            <label for="input_current_password">Current Password</label>
            <div class="input-with-icon">
                <i data-lucide="key" class="input-icon"></i>
                <input type="password" id="input_current_password" name="current_password" placeholder="Enter current password to verify">
                <span class="pwd-toggle" onclick="togglePasswordVisibility('input_current_password', this)">
                    <i data-lucide="eye" style="width: 18px; height: 18px;"></i>
                </span>
            </div>
        </div>

        <div class="form-group">
            <label for="input_new_password">New Password</label>
            <div class="input-with-icon">
                <i data-lucide="shield-check" class="input-icon"></i>
                <input type="password" id="input_new_password" name="new_password" placeholder="Minimum 4 characters">
                <span class="pwd-toggle" onclick="togglePasswordVisibility('input_new_password', this)">
                    <i data-lucide="eye" style="width: 18px; height: 18px;"></i>
                </span>
            </div>
        </div>

        <div class="form-group">
            <label for="input_confirm_password">Confirm New Password</label>
            <div class="input-with-icon">
                <i data-lucide="shield-alert" class="input-icon"></i>
                <input type="password" id="input_confirm_password" name="confirm_password" placeholder="Re-enter new password">
                <span class="pwd-toggle" onclick="togglePasswordVisibility('input_confirm_password', this)">
                    <i data-lucide="eye" style="width: 18px; height: 18px;"></i>
                </span>
            </div>
        </div>
    </div>

    <!-- Save Button -->
    <div style="margin-bottom: 20px;">
        <button type="submit" class="btn-save" id="btnSubmitProfile">
            <i data-lucide="check" style="width: 20px; height: 20px;"></i>
            <span>Save Profile Changes</span>
        </button>
    </div>
</form>

<div class="card" style="text-align: center; padding: 18px; margin-bottom: 24px;">
    <div style="font-size: 13px; color: var(--text-muted); margin-bottom: 12px;">
        Account Joined On: <strong><?php echo $joined; ?></strong>
    </div>
    <a href="../login.php?logout=1" style="display: inline-flex; align-items: center; justify-content: center; gap: 8px; background: #fee2e2; color: #b91c1c; padding: 10px 20px; border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 14px; transition: background 0.2s;">
        <i data-lucide="log-out" style="width: 16px;"></i> Logout Securely
    </a>
</div>

<script>
// Toggle Password Visibility
function togglePasswordVisibility(inputId, toggleEl) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const isPassword = input.getAttribute('type') === 'password';
    input.setAttribute('type', isPassword ? 'text' : 'password');
    toggleEl.innerHTML = isPassword 
        ? '<i data-lucide="eye-off" style="width: 18px; height: 18px;"></i>' 
        : '<i data-lucide="eye" style="width: 18px; height: 18px;"></i>';
    lucide.createIcons();
}

// Preview Selected Photo
function previewSelectedPhoto(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        if (file.size > 5 * 1024 * 1024) {
            Swal.fire({
                icon: 'error',
                title: 'File Too Large',
                text: 'Profile photo size must be less than 5MB.'
            });
            input.value = '';
            return;
        }

        const reader = new FileReader();
        reader.onload = function(e) {
            const container = document.getElementById('avatarPreviewContainer');
            container.innerHTML = `<img src="${e.target.result}" id="avatarPreviewImg" style="width:100%; height:100%; object-fit:cover; display:block;">`;
            
            const status = document.getElementById('photoChangeStatus');
            if (status) {
                status.innerHTML = '<span style="color:#a7f3d0; font-weight:700;">&#10003; New photo selected! Click "Save Profile Changes" below.</span>';
            }
        };
        reader.readAsDataURL(file);
    }
}

// Save Profile AJAX
function savePartnerProfile(event) {
    event.preventDefault();
    
    const form = document.getElementById('partnerProfileForm');
    const submitBtn = document.getElementById('btnSubmitProfile');
    const newPwd = document.getElementById('input_new_password').value;
    const confirmPwd = document.getElementById('input_confirm_password').value;
    const currentPwd = document.getElementById('input_current_password').value;

    if (newPwd || confirmPwd) {
        if (newPwd.length < 4) {
            Swal.fire({
                icon: 'warning',
                title: 'Weak Password',
                text: 'New password must be at least 4 characters.'
            });
            return;
        }
        if (newPwd !== confirmPwd) {
            Swal.fire({
                icon: 'error',
                title: 'Password Mismatch',
                text: 'New password and Confirm password do not match.'
            });
            return;
        }
        if (!currentPwd) {
            Swal.fire({
                icon: 'warning',
                title: 'Current Password Required',
                text: 'Please enter your current password to authorize this security change.'
            });
            return;
        }
    }

    const formData = new FormData(form);
    const photoInput = document.getElementById('profile_photo_input');
    if (photoInput && photoInput.files && photoInput.files[0]) {
        formData.append('profile_photo', photoInput.files[0]);
    }

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i data-lucide="loader-2" class="lucide-spin" style="width:20px; height:20px;"></i> <span>Saving Updates...</span>';
    lucide.createIcons();

    fetch('../api.php?api=update_partner_profile', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i data-lucide="check" style="width: 20px; height: 20px;"></i> <span>Save Profile Changes</span>';
        lucide.createIcons();

        if (data.success) {
            if (data.full_name) {
                document.getElementById('heroDisplayName').innerText = data.full_name;
            }

            if (data.photo_url) {
                const headerAvatar = document.querySelector('.app-user .user-avatar');
                if (headerAvatar) {
                    headerAvatar.innerHTML = `<img src="${data.photo_url}" style="width:100%; height:100%; object-fit:cover; display:block;">`;
                }
            }

            Swal.fire({
                icon: 'success',
                title: 'Profile Updated!',
                text: data.message || 'Your profile details and photo have been updated successfully.',
                timer: 1800,
                showConfirmButton: false
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Update Failed',
                text: data.error || 'Could not update profile. Please try again.'
            });
        }
    })
    .catch(err => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i data-lucide="check" style="width: 20px; height: 20px;"></i> <span>Save Profile Changes</span>';
        lucide.createIcons();

        Swal.fire({
            icon: 'error',
            title: 'Connection Error',
            text: 'Unable to connect to server. Please check your network.'
        });
    });
}

document.addEventListener('DOMContentLoaded', function() {
    lucide.createIcons();
});
</script>

<?php require_once 'includes/footer.php'; ?>
