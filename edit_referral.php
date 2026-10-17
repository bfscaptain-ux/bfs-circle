<?php
require_once 'config.php';
require_role(['Admin'], false);
$page_title = 'Edit Referral Profile';
$page_subtitle = 'Update and complete referral partner details';
$hide_main_header = true;
require_once 'header.php';
?>
<script src="assets/js/banks_directory.js"></script>
<?php

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) die("Missing Partner ID");

$stmt = $db->prepare("SELECT * FROM referrals WHERE id = ?");
$stmt->execute([$id]);
$referral = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$referral) die("Referral Partner not found");

$extra = json_decode($referral['extra_details'] ?? '{}', true) ?: [];

function getVal($field, $ref, $ext) {
    if (isset($ref[$field]) && $ref[$field] !== null) return htmlspecialchars($ref[$field]);
    if (isset($ext[$field]) && $ext[$field] !== null) return htmlspecialchars($ext[$field]);
    return '';
}

$is_active = (strtolower($referral['status'] ?? 'active') === 'active');

// Find previous and next partner IDs for quick paginated record navigation
$prev_stmt = $db->prepare("SELECT id, full_name FROM referrals WHERE id < ? ORDER BY id DESC LIMIT 1");
$prev_stmt->execute([$id]);
$prev_partner = $prev_stmt->fetch(PDO::FETCH_ASSOC);

$next_stmt = $db->prepare("SELECT id, full_name FROM referrals WHERE id > ? ORDER BY id ASC LIMIT 1");
$next_stmt->execute([$id]);
$next_partner = $next_stmt->fetch(PDO::FETCH_ASSOC);

// Calculate partner record position & total
$pos_stmt = $db->prepare("SELECT COUNT(*) FROM referrals WHERE id <= ?");
$pos_stmt->execute([$id]);
$partner_position = (int)$pos_stmt->fetchColumn();
$total_all_partners = (int)$db->query("SELECT COUNT(*) FROM referrals")->fetchColumn();
?>

<style>
/* Modern Executive Design System for Edit Referral */
.edit-referral-wrapper {
    width: 100%;
    max-width: 100%;
    margin: 0 0 30px 0;
    padding: 0;
}

/* Top Navigation Bar */
.edit-header-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 14px;
}
.btn-back-profile {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    color: #0f172a;
    font-weight: 600;
    font-size: 13px;
    padding: 8px 16px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none;
    box-shadow: 0 1px 2px rgba(0,0,0,0.03);
    transition: all 0.2s ease;
}
.btn-back-profile:hover {
    background: #f8fafc;
    border-color: #94a3b8;
    color: #000000;
}

/* Hero Partner Identity Strip */
.edit-hero-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 18px 24px;
    margin-bottom: 24px;
    box-shadow: 0 2px 6px -1px rgba(15, 23, 42, 0.04);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
}
.edit-hero-left {
    display: flex;
    align-items: center;
    gap: 16px;
}
.edit-hero-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: linear-gradient(135deg, #0f172a 0%, #334155 100%);
    color: #ffffff;
    font-size: 18px;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 3px 8px rgba(15, 23, 42, 0.18);
    flex-shrink: 0;
}
.edit-hero-title {
    font-size: 19px;
    font-weight: 800;
    color: #0f172a;
    margin: 0;
    font-family: 'Outfit', sans-serif;
    letter-spacing: -0.3px;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.edit-hero-sub {
    font-size: 12.5px;
    color: #64748b;
    margin-top: 3px;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Section Cards */
.form-section-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 22px 24px;
    margin-bottom: 22px;
    box-shadow: 0 2px 6px -1px rgba(15, 23, 42, 0.03);
}
.section-header-row {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 20px;
    padding-bottom: 14px;
    border-bottom: 1px solid #f1f5f9;
}
.section-icon-badge {
    width: 36px;
    height: 36px;
    border-radius: 9px;
    background: #f1f5f9;
    color: #0f172a;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.section-title-wrap h3 {
    font-size: 15.5px;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
    letter-spacing: -0.2px;
}
.section-title-wrap p {
    font-size: 12px;
    color: #64748b;
    margin: 2px 0 0 0;
}

/* Balanced Grid Layouts */
.grid-3-col {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
}
.grid-2-col {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
}
@media (max-width: 900px) {
    .grid-3-col, .grid-2-col {
        grid-template-columns: 1fr;
    }
}

/* Form Groups & Inputs */
.form-group {
    display: flex;
    flex-direction: column;
}
.form-group label {
    font-size: 12.5px;
    font-weight: 600;
    color: #334155;
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 4px;
}
.form-group label.required::after {
    content: '*';
    color: #ef4444;
    font-weight: 700;
}
.form-group input,
.form-group select {
    height: 42px;
    padding: 0 14px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 13.5px;
    color: #0f172a;
    background-color: #f8fafc;
    transition: all 0.2s ease;
    width: 100%;
    box-sizing: border-box;
    outline: none;
}
.form-group input:focus,
.form-group select:focus {
    border-color: #0f172a;
    background-color: #ffffff;
    box-shadow: 0 0 0 3px rgba(15, 23, 42, 0.08);
}
.form-group input::placeholder {
    color: #94a3b8;
}

/* Dynamic Container Styling */
#dynamic_fields_container {
    display: none;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 16px 18px;
    margin-top: 16px;
    gap: 16px;
}

/* Document Upload Cards */
.doc-upload-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 14px 16px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.doc-card-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.doc-card-title {
    font-size: 12.5px;
    font-weight: 700;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 6px;
}
.doc-card-body {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    padding: 6px 10px;
    height: 40px;
    box-sizing: border-box;
}

/* Action Bar Bottom */
.form-action-bar {
    position: static;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 12px;
    padding: 16px 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 2px 6px -1px rgba(15, 23, 42, 0.04);
    margin-top: 24px;
    margin-bottom: 30px;
}
.btn-save-submit {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    color: #ffffff;
    border: 1px solid #0f172a;
    padding: 10px 26px;
    border-radius: 8px;
    font-size: 13.5px;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.25);
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.btn-save-submit:hover {
    background: linear-gradient(135deg, #000000 0%, #0f172a 100%);
    box-shadow: 0 6px 20px rgba(15, 23, 42, 0.35);
    transform: translateY(-1px);
}

/* Partner Record Pagination in Top Header */
.partner-record-paginator {
    display: inline-flex;
    align-items: center;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 3px 5px;
    gap: 4px;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}
.btn-partner-pag {
    height: 28px;
    padding: 0 8px;
    border-radius: 6px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #0f172a;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 11.5px;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.15s ease;
}
.btn-partner-pag:hover:not(.disabled) {
    background: #0f172a;
    color: #ffffff;
    border-color: #0f172a;
}
.btn-partner-pag.disabled {
    opacity: 0.35;
    pointer-events: none;
    cursor: not-allowed;
    background: #f8fafc;
    border-color: #e2e8f0;
}
.partner-record-counter {
    font-size: 11.5px;
    font-weight: 600;
    color: #475569;
    padding: 0 6px;
    white-space: nowrap;
}
.partner-record-counter b {
    color: #0f172a;
    font-weight: 800;
}

/* Form Section Page Management Deck */
.section-nav-deck {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 8px 12px;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
    flex-wrap: wrap;
    gap: 8px;
}
.section-tabs-list {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}
.sec-tab-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    font-size: 12px;
    font-weight: 600;
    color: #475569;
    cursor: pointer;
    transition: all 0.15s ease;
}
.sec-tab-btn svg {
    width: 13px;
    height: 13px;
}
.sec-tab-btn:hover {
    background: #f8fafc;
    color: #0f172a;
    border-color: #94a3b8;
}
.sec-tab-btn.active {
    background: #0f172a;
    color: #ffffff;
    border-color: #0f172a;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.2);
}
.mode-toggle-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 12px;
    background: #f8fafc;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    font-size: 11.5px;
    font-weight: 700;
    color: #334155;
    cursor: pointer;
    transition: all 0.15s ease;
}
.mode-toggle-btn:hover {
    background: #e2e8f0;
    color: #0f172a;
}
.mode-toggle-btn.active {
    background: #eff6ff;
    border-color: #93c5fd;
    color: #1d4ed8;
}
</style>

<div class="view-container edit-referral-wrapper">

    <!-- Top Navigation Header -->
    <div class="edit-header-bar">
        <div style="display:flex; align-items:center; gap:12px;">
            <a href="view_referral.php?id=<?php echo $id; ?>" class="btn-back-profile">
                <i data-lucide="arrow-left" style="width:15px; height:15px;"></i> Back to Profile
            </a>
            <span style="font-size:13px; color:#94a3b8;">/</span>
            <span style="font-size:13px; color:#64748b; font-weight:600;">Partner Profile Editor</span>
        </div>
        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
            <!-- Partner Record Pagination Quick-Switcher -->
            <div class="partner-record-paginator">
                <a href="<?php echo $prev_partner ? 'edit_referral.php?id=' . $prev_partner['id'] : 'javascript:void(0)'; ?>" 
                   class="btn-partner-pag <?php echo !$prev_partner ? 'disabled' : ''; ?>" 
                   title="<?php echo $prev_partner ? 'Previous Partner: ' . htmlspecialchars($prev_partner['full_name']) : 'No previous partner'; ?>">
                    <i data-lucide="chevron-left" style="width:13px; height:13px;"></i> Prev Partner
                </a>
                
                <span class="partner-record-counter">
                    Partner <b><?php echo $partner_position; ?></b> of <b><?php echo $total_all_partners; ?></b>
                </span>

                <a href="<?php echo $next_partner ? 'edit_referral.php?id=' . $next_partner['id'] : 'javascript:void(0)'; ?>" 
                   class="btn-partner-pag <?php echo !$next_partner ? 'disabled' : ''; ?>" 
                   title="<?php echo $next_partner ? 'Next Partner: ' . htmlspecialchars($next_partner['full_name']) : 'No next partner'; ?>">
                    Next Partner <i data-lucide="chevron-right" style="width:13px; height:13px;"></i>
                </a>
            </div>

            <a href="referrals_list.php" class="btn btn-secondary" style="background:#fff; border:1px solid #cbd5e1; font-weight:600; font-size:12.5px; height:34px; padding:0 12px; display:inline-flex; align-items:center; gap:5px; border-radius:8px;">
                <i data-lucide="list" style="width:14px; height:14px;"></i> Directory
            </a>
        </div>
    </div>

    <!-- Hero Partner Identity Card -->
    <div class="edit-hero-card">
        <div class="edit-hero-left">
            <div class="edit-hero-avatar">
                <?php echo strtoupper(substr($referral['full_name'] ?? 'P', 0, 1)); ?>
            </div>
            <div>
                <div class="edit-hero-title">
                    <span><?php echo htmlspecialchars($referral['full_name']); ?></span>
                    <span style="font-family:'SFMono-Regular', Consolas, monospace; font-size:11.5px; font-weight:800; background:#f1f5f9; color:#0f172a; border:1px solid #cbd5e1; padding:2px 8px; border-radius:6px; letter-spacing:0.02em;">
                        <?php echo htmlspecialchars($referral['referral_id'] ?? ''); ?>
                    </span>
                    <span style="font-size:11px; font-weight:800; padding:2px 10px; border-radius:20px; border:1px solid <?php echo $is_active ? '#a7f3d0' : '#fecaca'; ?>; background:<?php echo $is_active ? '#ecfdf5' : '#fef2f2'; ?>; color:<?php echo $is_active ? '#065f46' : '#991b1b'; ?>;">
                        <?php echo $is_active ? '● ACTIVE' : '● DEACTIVATED'; ?>
                    </span>
                </div>
                <div class="edit-hero-sub">
                    <span><i data-lucide="briefcase" style="width:12px; height:12px; display:inline; vertical-align:middle;"></i> <?php echo htmlspecialchars($referral['referrer_type'] ?? 'Partner'); ?></span>
                    <span>&bull;</span>
                    <span><i data-lucide="phone" style="width:12px; height:12px; display:inline; vertical-align:middle;"></i> <?php echo htmlspecialchars($referral['mobile'] ?? 'N/A'); ?></span>
                    <span>&bull;</span>
                    <span><i data-lucide="map-pin" style="width:12px; height:12px; display:inline; vertical-align:middle;"></i> <?php echo htmlspecialchars($referral['city_state'] ?? 'India'); ?></span>
                </div>
            </div>
        </div>
        <div>
            <button type="button" onclick="document.getElementById('editReferralForm').requestSubmit()" class="btn-save-submit" style="padding:8px 18px; font-size:13px;">
                <i data-lucide="check" style="width:15px; height:15px;"></i> Save Changes
            </button>
        </div>
    </div>

    <!-- Form Page Management & Section Navigator -->
    <div class="section-nav-deck">
        <div class="section-tabs-list">
            <button type="button" class="sec-tab-btn active" id="tab-btn-1" onclick="switchSectionTab('sec-1', this)">
                <i data-lucide="briefcase"></i> <span>1. Classification</span>
            </button>
            <button type="button" class="sec-tab-btn" id="tab-btn-2" onclick="switchSectionTab('sec-2', this)">
                <i data-lucide="user-check"></i> <span>2. Personal Info</span>
            </button>
            <button type="button" class="sec-tab-btn" id="tab-btn-3" onclick="switchSectionTab('sec-3', this)">
                <i data-lucide="landmark"></i> <span>3. Banking & Payout</span>
            </button>
            <button type="button" class="sec-tab-btn" id="tab-btn-4" onclick="switchSectionTab('sec-4', this)">
                <i data-lucide="shield-check"></i> <span>4. KYC Vault</span>
            </button>
            <button type="button" class="sec-tab-btn" id="tab-btn-5" onclick="switchSectionTab('sec-5', this)">
                <i data-lucide="settings-2"></i> <span>5. Governance</span>
            </button>
        </div>
        <div>
            <button type="button" class="mode-toggle-btn active" id="btnToggleSectionView" onclick="toggleSectionViewMode()">
                <i data-lucide="layers" style="width:13px; height:13px;"></i>
                <span id="viewModeLabel">Step-by-Step View</span>
            </button>
        </div>
    </div>

    <!-- Main Edit Form -->
    <form id="editReferralForm" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?php echo $id; ?>">

        <!-- 1. CATEGORY & CLASSIFICATION -->
        <div class="form-section-card" id="sec-1">
            <div class="section-header-row">
                <div class="section-icon-badge" style="background:#eff6ff; color:#2563eb;">
                    <i data-lucide="briefcase" style="width:18px; height:18px;"></i>
                </div>
                <div class="section-title-wrap">
                    <h3>1. Partner Category &amp; Classification</h3>
                    <p>Select the partner business model and configure registered corporate agency credentials</p>
                </div>
            </div>

            <div class="grid-2-col">
                <div class="form-group">
                    <label for="referrer_type" class="required">Partner Classification Type</label>
                    <select name="referrer_type" id="referrer_type" required>
                        <option value="">-- Select Type --</option>
                        <?php 
                        $types = ['Individual Agent', 'Financial Advisor / DSA', 'Builder / Real Estate', 'Chartered Accountant (CA)', 'PROPERTY DEALER'];
                        foreach($types as $t) {
                            $sel = (strcasecmp($referral['referrer_type'] ?? '', $t) === 0) ? 'selected' : '';
                            echo "<option value=\"$t\" $sel>$t</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Partner Code (Readonly System ID)</label>
                    <input type="text" value="<?php echo htmlspecialchars($referral['referral_id'] ?? ''); ?>" readonly style="background:#f1f5f9; cursor:not-allowed; font-family:monospace; font-weight:700;">
                </div>
            </div>

            <!-- Dynamic Firm / Commercial Details Container -->
            <div id="dynamic_fields_container">
                <!-- Dynamically populated via JS -->
            </div>

            <!-- Card 1 Step Navigation Footer -->
            <div style="display:flex; justify-content:flex-end; margin-top:20px; padding-top:14px; border-top:1px solid #f1f5f9;">
                <button type="button" onclick="switchSectionTab('sec-2', document.getElementById('tab-btn-2'))" class="btn btn-secondary" style="background:#0f172a; color:#fff; font-weight:600; font-size:12.5px; padding:8px 18px; border-radius:6px; display:inline-flex; align-items:center; gap:6px; cursor:pointer;">
                    Next: Personal Details <i data-lucide="arrow-right" style="width:14px; height:14px;"></i>
                </button>
            </div>
        </div>

        <!-- 2. BASIC PERSONAL & CONTACT DETAILS -->
        <div class="form-section-card" id="sec-2">
            <div class="section-header-row">
                <div class="section-icon-badge" style="background:#f0fdf4; color:#16a34a;">
                    <i data-lucide="user-check" style="width:18px; height:18px;"></i>
                </div>
                <div class="section-title-wrap">
                    <h3>2. Personal &amp; Contact Dossier</h3>
                    <p>Official legal identity details as verified on government identification records</p>
                </div>
            </div>

            <div class="grid-3-col" style="margin-bottom:16px;">
                <div class="form-group">
                    <label for="full_name" class="required">Full Legal Name (as per PAN/Aadhaar)</label>
                    <input type="text" name="full_name" id="full_name" value="<?php echo getVal('full_name', $referral, $extra); ?>" required placeholder="e.g. Diya Sharma">
                </div>
                <div class="form-group">
                    <label for="dob" class="required">Date of Birth</label>
                    <input type="date" name="dob" id="dob" value="<?php echo getVal('dob', $referral, $extra); ?>" required>
                </div>
                <div class="form-group">
                    <label for="mobile" class="required">10-Digit Mobile Number</label>
                    <input type="text" name="mobile" id="mobile" value="<?php echo getVal('mobile', $referral, $extra); ?>" required maxlength="10" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0,10)" pattern="[0-9]{10}" placeholder="e.g. 9528836338">
                </div>
            </div>

            <div class="grid-2-col">
                <div class="form-group">
                    <label for="email">Official Email Address</label>
                    <input type="email" name="email" id="email" value="<?php echo getVal('email', $referral, $extra); ?>" placeholder="e.g. partner@example.com">
                </div>
                <div class="form-group">
                    <label for="city_state">City &amp; State / Territory</label>
                    <input type="text" name="city_state" id="city_state" value="<?php echo getVal('city_state', $referral, $extra); ?>" placeholder="e.g. Agra, Uttar Pradesh">
                </div>
            </div>

            <!-- Card 2 Step Navigation Footer -->
            <div style="display:flex; justify-content:space-between; margin-top:20px; padding-top:14px; border-top:1px solid #f1f5f9;">
                <button type="button" onclick="switchSectionTab('sec-1', document.getElementById('tab-btn-1'))" class="btn btn-secondary" style="background:#fff; border:1px solid #cbd5e1; font-weight:600; font-size:12.5px; padding:8px 16px; border-radius:6px; display:inline-flex; align-items:center; gap:6px; cursor:pointer;">
                    <i data-lucide="arrow-left" style="width:14px; height:14px;"></i> Previous
                </button>
                <button type="button" onclick="switchSectionTab('sec-3', document.getElementById('tab-btn-3'))" class="btn btn-secondary" style="background:#0f172a; color:#fff; font-weight:600; font-size:12.5px; padding:8px 18px; border-radius:6px; display:inline-flex; align-items:center; gap:6px; cursor:pointer;">
                    Next: Banking Details <i data-lucide="arrow-right" style="width:14px; height:14px;"></i>
                </button>
            </div>
        </div>

        <!-- 3. BANKING & COMMISSION STRUCTURE -->
        <div class="form-section-card" id="sec-3">
            <div class="section-header-row">
                <div class="section-icon-badge" style="background:#ecfdf5; color:#059669;">
                    <i data-lucide="landmark" style="width:18px; height:18px;"></i>
                </div>
                <div class="section-title-wrap">
                    <h3>3. Banking Coordinates &amp; Commission Slab</h3>
                    <p>Bank settlement account details for direct referral payout disbursements</p>
                </div>
            </div>

            <div class="grid-3-col" style="margin-bottom:16px;">
                <div class="form-group">
                    <label for="account_name">Bank Account Holder Name</label>
                    <input type="text" name="account_name" id="account_name" value="<?php echo getVal('account_name', $referral, $extra); ?>" placeholder="As per bank passbook">
                </div>
                <div class="form-group">
                    <label for="bank_name">Bank Name</label>
                    <input type="text" name="bank_name" id="bank_name" list="bank_list" value="<?php echo getVal('bank_name', $referral, $extra); ?>" placeholder="Type or select Bank name">
                    <datalist id="bank_list"></datalist>
                </div>
                <div class="form-group">
                    <label for="account_number">Bank Account Number</label>
                    <input type="text" name="account_number" id="account_number" value="<?php echo getVal('account_number', $referral, $extra); ?>" maxlength="22" oninput="this.value = this.value.replace(/[^0-9A-Z]/g, '')" placeholder="Account Number">
                </div>
            </div>

            <div class="grid-2-col" style="margin-bottom:18px;">
                <div class="form-group">
                    <label for="ifsc_code">Bank IFSC Code</label>
                    <input type="text" name="ifsc_code" id="ifsc_code" value="<?php echo getVal('ifsc_code', $referral, $extra); ?>" maxlength="11" oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0,11)" placeholder="e.g. HDFC0001234">
                </div>
                <div class="form-group">
                    <label for="upi_id">UPI ID / VPA (Optional)</label>
                    <input type="text" name="upi_id" id="upi_id" value="<?php echo getVal('upi_id', $referral, $extra); ?>" placeholder="e.g. partner@okhdfcbank">
                </div>
            </div>

            <!-- Commission Slab Box -->
            <div style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:10px; padding:16px 18px;">
                <div style="font-size:12px; font-weight:700; color:#0f172a; text-transform:uppercase; letter-spacing:0.04em; margin-bottom:12px; display:flex; align-items:center; gap:6px;">
                    <i data-lucide="percent" style="width:14px; height:14px; color:#059669;"></i> Agreed Commission &amp; Settlement Terms
                </div>
                <div class="grid-2-col">
                    <div class="form-group">
                        <label for="commission_rate" style="font-weight:600;">Agreed Commission / Slab Rate</label>
                        <input type="text" name="commission_rate" id="commission_rate" value="<?php echo getVal('commission_rate', $referral, $extra); ?>" placeholder="e.g. 1.2% or 0.5% per disbursement">
                    </div>
                    <div class="form-group">
                        <label for="payout_frequency">Payout Settlement Frequency</label>
                        <select name="payout_frequency" id="payout_frequency">
                            <?php 
                            $freqs = ['Monthly', 'Per Disbursement', 'Quarterly'];
                            foreach($freqs as $f) {
                                $sel = (strcasecmp($referral['payout_frequency'] ?? '', $f) === 0) ? 'selected' : '';
                                echo "<option value=\"$f\" $sel>$f</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Card 3 Step Navigation Footer -->
            <div style="display:flex; justify-content:space-between; margin-top:20px; padding-top:14px; border-top:1px solid #f1f5f9;">
                <button type="button" onclick="switchSectionTab('sec-2', document.getElementById('tab-btn-2'))" class="btn btn-secondary" style="background:#fff; border:1px solid #cbd5e1; font-weight:600; font-size:12.5px; padding:8px 16px; border-radius:6px; display:inline-flex; align-items:center; gap:6px; cursor:pointer;">
                    <i data-lucide="arrow-left" style="width:14px; height:14px;"></i> Previous
                </button>
                <button type="button" onclick="switchSectionTab('sec-4', document.getElementById('tab-btn-4'))" class="btn btn-secondary" style="background:#0f172a; color:#fff; font-weight:600; font-size:12.5px; padding:8px 18px; border-radius:6px; display:inline-flex; align-items:center; gap:6px; cursor:pointer;">
                    Next: KYC Documents <i data-lucide="arrow-right" style="width:14px; height:14px;"></i>
                </button>
            </div>
        </div>

        <!-- 4. KYC & DOCUMENTS VAULT -->
        <div class="form-section-card" id="sec-4">
            <div class="section-header-row">
                <div class="section-icon-badge" style="background:#faf5ff; color:#7c3aed;">
                    <i data-lucide="shield-check" style="width:18px; height:18px;"></i>
                </div>
                <div class="section-title-wrap">
                    <h3>4. KYC Vault &amp; Verified Document Uploads</h3>
                    <p>Government statutory identification numbers and document proof scans (Max 5MB PDF or Images)</p>
                </div>
            </div>

            <div class="grid-2-col" style="margin-bottom:18px;">
                <div class="form-group">
                    <label for="pan_number" class="required">PAN Card Number</label>
                    <input type="text" name="pan_number" id="pan_number" value="<?php echo getVal('pan_number', $referral, $extra); ?>" required maxlength="10" oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0,10)" placeholder="ABCDE1234F">
                </div>
                <div class="form-group">
                    <label for="aadhar_number" class="required">Aadhaar Card Number</label>
                    <input type="text" name="aadhar_number" id="aadhar_number" value="<?php echo getVal('aadhar_number', $referral, $extra); ?>" required maxlength="12" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0,12)" pattern="[0-9]{12}" placeholder="12-digit Aadhaar number">
                </div>
            </div>

            <!-- 3 Clean Document Upload Cards -->
            <div class="grid-3-col">
                <!-- 1. Bank Document -->
                <div class="doc-upload-card">
                    <div class="doc-card-top">
                        <span class="doc-card-title"><i data-lucide="file-check" style="width:14px; height:14px; color:#059669;"></i> Bank Proof Scan</span>
                        <?php if(!empty($referral['bank_document_path'])): ?>
                            <button type="button" onclick="openPreviewModal('<?php echo htmlspecialchars($referral['bank_document_path']); ?>')" class="btn btn-sm" style="background:#ecfdf5; color:#059669; border:1px solid #a7f3d0; padding:2px 8px; font-size:11px; font-weight:700; cursor:pointer;">
                                <i data-lucide="eye" style="width:11px; height:11px; display:inline; vertical-align:middle;"></i> View Saved
                            </button>
                        <?php else: ?>
                            <span style="font-size:10.5px; color:#94a3b8; font-weight:600;">No file saved</span>
                        <?php endif; ?>
                    </div>
                    <div class="doc-card-body">
                        <input type="file" name="bank_document" id="bank_document" style="display:none;" onchange="updateFileName(this, 'bank_doc_wrapper', 'bank_doc_empty', 'bank_doc_preview')" accept="image/*,.pdf">
                        <button type="button" class="btn btn-sm" style="background:#ffffff; color:#334155; border:1px solid #cbd5e1; padding:4px 10px; font-size:12px; font-weight:600;" onclick="document.getElementById('bank_document').click()">
                            <i data-lucide="upload" style="width:13px; height:13px; display:inline; vertical-align:middle;"></i> Choose
                        </button>
                        <div id="bank_doc_wrapper" style="display:none; flex:1; align-items:center; gap:4px; min-width:0;">
                            <span id="bank_doc_preview" style="flex:1; font-size:12px; color:#0284c7; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"></span>
                            <button type="button" onclick="clearFileSelection('bank_document', 'bank_doc_wrapper', 'bank_doc_empty')" style="background:none; border:none; color:#ef4444; cursor:pointer;"><i data-lucide="x" style="width:14px; height:14px;"></i></button>
                        </div>
                        <span id="bank_doc_empty" style="font-size:11.5px; color:#94a3b8; margin-left:auto;">Choose new file</span>
                    </div>
                </div>

                <!-- 2. PAN Document -->
                <div class="doc-upload-card">
                    <div class="doc-card-top">
                        <span class="doc-card-title"><i data-lucide="credit-card" style="width:14px; height:14px; color:#2563eb;"></i> PAN Card Scan</span>
                        <?php if(!empty($referral['pan_document_path'])): ?>
                            <button type="button" onclick="openPreviewModal('<?php echo htmlspecialchars($referral['pan_document_path']); ?>')" class="btn btn-sm" style="background:#ecfdf5; color:#059669; border:1px solid #a7f3d0; padding:2px 8px; font-size:11px; font-weight:700; cursor:pointer;">
                                <i data-lucide="eye" style="width:11px; height:11px; display:inline; vertical-align:middle;"></i> View Saved
                            </button>
                        <?php else: ?>
                            <span style="font-size:10.5px; color:#94a3b8; font-weight:600;">No file saved</span>
                        <?php endif; ?>
                    </div>
                    <div class="doc-card-body">
                        <input type="file" name="pan_document" id="pan_document" style="display:none;" onchange="updateFileName(this, 'pan_doc_wrapper', 'pan_doc_empty', 'pan_doc_preview')" accept="image/*,.pdf">
                        <button type="button" class="btn btn-sm" style="background:#ffffff; color:#334155; border:1px solid #cbd5e1; padding:4px 10px; font-size:12px; font-weight:600;" onclick="document.getElementById('pan_document').click()">
                            <i data-lucide="upload" style="width:13px; height:13px; display:inline; vertical-align:middle;"></i> Choose
                        </button>
                        <div id="pan_doc_wrapper" style="display:none; flex:1; align-items:center; gap:4px; min-width:0;">
                            <span id="pan_doc_preview" style="flex:1; font-size:12px; color:#0284c7; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"></span>
                            <button type="button" onclick="clearFileSelection('pan_document', 'pan_doc_wrapper', 'pan_doc_empty')" style="background:none; border:none; color:#ef4444; cursor:pointer;"><i data-lucide="x" style="width:14px; height:14px;"></i></button>
                        </div>
                        <span id="pan_doc_empty" style="font-size:11.5px; color:#94a3b8; margin-left:auto;">Choose new file</span>
                    </div>
                </div>

                <!-- 3. Aadhaar Document -->
                <div class="doc-upload-card">
                    <div class="doc-card-top">
                        <span class="doc-card-title"><i data-lucide="file-text" style="width:14px; height:14px; color:#7c3aed;"></i> Aadhaar Card Scan</span>
                        <?php if(!empty($referral['aadhar_document_path'])): ?>
                            <button type="button" onclick="openPreviewModal('<?php echo htmlspecialchars($referral['aadhar_document_path']); ?>')" class="btn btn-sm" style="background:#ecfdf5; color:#059669; border:1px solid #a7f3d0; padding:2px 8px; font-size:11px; font-weight:700; cursor:pointer;">
                                <i data-lucide="eye" style="width:11px; height:11px; display:inline; vertical-align:middle;"></i> View Saved
                            </button>
                        <?php else: ?>
                            <span style="font-size:10.5px; color:#94a3b8; font-weight:600;">No file saved</span>
                        <?php endif; ?>
                    </div>
                    <div class="doc-card-body">
                        <input type="file" name="aadhar_document" id="aadhar_document" style="display:none;" onchange="updateFileName(this, 'aadhar_doc_wrapper', 'aadhar_doc_empty', 'aadhar_doc_preview')" accept="image/*,.pdf">
                        <button type="button" class="btn btn-sm" style="background:#ffffff; color:#334155; border:1px solid #cbd5e1; padding:4px 10px; font-size:12px; font-weight:600;" onclick="document.getElementById('aadhar_document').click()">
                            <i data-lucide="upload" style="width:13px; height:13px; display:inline; vertical-align:middle;"></i> Choose
                        </button>
                        <div id="aadhar_doc_wrapper" style="display:none; flex:1; align-items:center; gap:4px; min-width:0;">
                            <span id="aadhar_doc_preview" style="flex:1; font-size:12px; color:#0284c7; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"></span>
                            <button type="button" onclick="clearFileSelection('aadhar_document', 'aadhar_doc_wrapper', 'aadhar_doc_empty')" style="background:none; border:none; color:#ef4444; cursor:pointer;"><i data-lucide="x" style="width:14px; height:14px;"></i></button>
                        </div>
                        <span id="aadhar_doc_empty" style="font-size:11.5px; color:#94a3b8; margin-left:auto;">Choose new file</span>
                    </div>
                </div>
            </div>

            <!-- Card 4 Step Navigation Footer -->
            <div style="display:flex; justify-content:space-between; margin-top:20px; padding-top:14px; border-top:1px solid #f1f5f9;">
                <button type="button" onclick="switchSectionTab('sec-3', document.getElementById('tab-btn-3'))" class="btn btn-secondary" style="background:#fff; border:1px solid #cbd5e1; font-weight:600; font-size:12.5px; padding:8px 16px; border-radius:6px; display:inline-flex; align-items:center; gap:6px; cursor:pointer;">
                    <i data-lucide="arrow-left" style="width:14px; height:14px;"></i> Previous
                </button>
                <button type="button" onclick="switchSectionTab('sec-5', document.getElementById('tab-btn-5'))" class="btn btn-secondary" style="background:#0f172a; color:#fff; font-weight:600; font-size:12.5px; padding:8px 18px; border-radius:6px; display:inline-flex; align-items:center; gap:6px; cursor:pointer;">
                    Next: Governance & Assignment <i data-lucide="arrow-right" style="width:14px; height:14px;"></i>
                </button>
            </div>
        </div>

        <!-- 5. GOVERNANCE & ACCESS CONTROL -->
        <div class="form-section-card" id="sec-5">
            <div class="section-header-row">
                <div class="section-icon-badge" style="background:#f1f5f9; color:#0f172a;">
                    <i data-lucide="shield" style="width:18px; height:18px;"></i>
                </div>
                <div class="section-title-wrap">
                    <h3>5. Branch Allocation &amp; Account Governance</h3>
                    <p>Internal branch assignment, relationship manager mapping, and portal login permissions</p>
                </div>
            </div>

            <div class="grid-3-col">
                <div class="form-group">
                    <label for="mapped_branch">Mapped Branch Office</label>
                    <input type="text" name="mapped_branch" id="mapped_branch" value="Sanjay Place" readonly style="background-color: #f1f5f9; cursor: not-allowed; color: #64748b; font-weight:600;">
                </div>
                <div class="form-group">
                    <label for="assigned_rm">Assigned RM (Relationship Manager)</label>
                    <select name="assigned_rm" id="assigned_rm" class="user-select">
                        <option value="<?php echo getVal('assigned_rm', $referral, $extra); ?>"><?php echo getVal('assigned_rm', $referral, $extra) ?: 'Select RM'; ?></option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="status">Partner Portal Status</label>
                    <select name="status" id="status" style="font-weight:700;">
                        <?php 
                        $stats = ['Active', 'Inactive', 'Blocked', 'Pending Approval'];
                        foreach($stats as $s) {
                            $sel = (strcasecmp($referral['status'] ?? '', $s) === 0) ? 'selected' : '';
                            echo "<option value=\"$s\" $sel>$s</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>
            <div style="font-size:11.5px; color:#64748b; margin-top:10px; display:flex; align-items:center; gap:6px;">
                <i data-lucide="info" style="width:13px; height:13px; color:#2563eb;"></i>
                <span>Setting status to <strong>Inactive</strong> or <strong>Blocked</strong> immediately revokes partner dashboard login access and terminates any active sessions.</span>
            </div>

            <!-- Card 5 Step Navigation Footer -->
            <div style="display:flex; justify-content:space-between; margin-top:20px; padding-top:14px; border-top:1px solid #f1f5f9;">
                <button type="button" onclick="switchSectionTab('sec-4', document.getElementById('tab-btn-4'))" class="btn btn-secondary" style="background:#fff; border:1px solid #cbd5e1; font-weight:600; font-size:12.5px; padding:8px 16px; border-radius:6px; display:inline-flex; align-items:center; gap:6px; cursor:pointer;">
                    <i data-lucide="arrow-left" style="width:14px; height:14px;"></i> Previous
                </button>
                <span style="font-size:12px; color:#059669; font-weight:700; display:inline-flex; align-items:center; gap:5px;">
                    <i data-lucide="check-circle-2" style="width:15px; height:15px;"></i> Ready to Save
                </span>
            </div>
        </div>

        <!-- Bottom Sticky Action Bar -->
        <div class="form-action-bar">
            <a href="view_referral.php?id=<?php echo $id; ?>" class="btn btn-secondary" style="background:#ffffff; border:1px solid #cbd5e1; color:#475569; font-weight:600; padding:10px 20px; border-radius:8px;">
                Cancel Changes
            </a>
            <div style="display:flex; align-items:center; gap:12px;">
                <button type="submit" class="btn-save-submit">
                    <i data-lucide="check-circle" style="width:16px; height:16px;"></i> Save All Changes
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Modern Document Preview Lightbox Modal -->
<div id="previewModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15, 23, 42, 0.75); backdrop-filter:blur(4px); z-index:99999; align-items:center; justify-content:center; flex-direction:column; padding:20px;">
    <div style="width:100%; max-width:850px; display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; color:#fff;">
        <span style="font-weight:700; font-size:15px; display:inline-flex; align-items:center; gap:6px;">
            <i data-lucide="file-text" style="width:18px; height:18px;"></i> Document Viewer
        </span>
        <button type="button" onclick="closePreviewModal()" style="background:#ffffff; border:none; padding:7px; border-radius:50%; cursor:pointer; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 8px rgba(0,0,0,0.3);">
            <i data-lucide="x" style="width:18px; height:18px; color:#0f172a;"></i>
        </button>
    </div>
    <div id="previewContent" style="width:100%; max-width:850px; height:80vh; background:#ffffff; border-radius:12px; overflow:hidden; display:flex; align-items:center; justify-content:center; padding:12px; box-shadow:0 25px 60px rgba(0,0,0,0.35);">
    </div>
</div>

<script>
// Pass existing dynamic JSON data
const existingDynamicData = <?php echo json_encode($extra); ?>;

// Form Page Management & Step-by-Step View Engine
let isStepViewMode = true;

function switchSectionTab(secId, btn) {
    if (!isStepViewMode) {
        const el = document.getElementById(secId);
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        document.querySelectorAll('.sec-tab-btn').forEach(b => b.classList.remove('active'));
        if (btn) btn.classList.add('active');
        return;
    }

    // Step-by-Step View Mode: Show only active section
    document.querySelectorAll('.form-section-card').forEach(card => {
        card.style.display = 'none';
    });
    const target = document.getElementById(secId);
    if (target) {
        target.style.display = 'block';
    }
    document.querySelectorAll('.sec-tab-btn').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');

    if (window.lucide) lucide.createIcons();
    const navDeck = document.querySelector('.section-nav-deck');
    if (navDeck) {
        window.scrollTo({ top: navDeck.offsetTop - 15, behavior: 'smooth' });
    }
}

function toggleSectionViewMode() {
    isStepViewMode = !isStepViewMode;
    const btn = document.getElementById('btnToggleSectionView');
    const label = document.getElementById('viewModeLabel');
    if (isStepViewMode) {
        btn.classList.add('active');
        label.innerText = 'Step-by-Step View';
        switchSectionTab('sec-1', document.getElementById('tab-btn-1'));
    } else {
        btn.classList.remove('active');
        label.innerText = 'Show All Sections';
        document.querySelectorAll('.form-section-card').forEach(card => {
            card.style.display = 'block';
        });
    }
    if (window.lucide) lucide.createIcons();
}

function clearFileSelection(inputId, wrapperId, emptyId) {
    const input = document.getElementById(inputId);
    input.value = "";
    document.getElementById(wrapperId).style.display = 'none';
    if (document.getElementById(emptyId)) document.getElementById(emptyId).style.display = 'block';
    if (window.lucide) lucide.createIcons();
}

function updateFileName(input, wrapperId, emptyId, previewId) {
    const wrapperEl = document.getElementById(wrapperId);
    const previewEl = document.getElementById(previewId);
    const emptyEl = document.getElementById(emptyId);
    
    if (input.files && input.files.length > 0) {
        const file = input.files[0];
        if (file.size > 5 * 1024 * 1024) { // 5MB limit
            Swal.fire({
                icon: 'warning',
                title: 'File Too Large',
                text: 'File size must be less than 5MB.',
                confirmButtonText: 'OK'
            });
            clearFileSelection(input.id, wrapperId, emptyId);
            return;
        }
        wrapperEl.style.display = 'flex';
        if (emptyEl) emptyEl.style.display = 'none';
        previewEl.textContent = file.name;
        if (window.lucide) lucide.createIcons();
    } else {
        clearFileSelection(input.id, wrapperId, emptyId);
    }
}

function openPreviewModal(url, isPdf) {
    if (window.event) window.event.preventDefault();
    const modal = document.getElementById('previewModal');
    const content = document.getElementById('previewContent');
    const isDocPdf = isPdf || url.toLowerCase().endsWith('.pdf');
    if (isDocPdf) {
        content.innerHTML = `<iframe src="${url}" style="width:100%; height:100%; border:none; border-radius:8px;"></iframe>`;
    } else {
        content.innerHTML = `<img src="${url}" style="max-width:100%; max-height:100%; object-fit:contain; border-radius:8px;" alt="Document Preview" />`;
    }
    modal.style.display = 'flex';
    if (window.lucide) lucide.createIcons();
}

function closePreviewModal() {
    document.getElementById('previewModal').style.display = 'none';
    document.getElementById('previewContent').innerHTML = '';
}

function populateDynamicFields(val) {
    const dynamicContainer = document.getElementById('dynamic_fields_container');
    let html = '';
    
    if (val === 'Builder / Real Estate') {
        html = `
            <div style="grid-column: 1 / -1; font-size:12px; font-weight:700; color:#c2410c; text-transform:uppercase; letter-spacing:0.04em; display:flex; align-items:center; gap:6px;">
                <i data-lucide="building-2" style="width:14px; height:14px;"></i> Builder &amp; Real Estate Enterprise Credentials
            </div>
            <div class="form-group"><label>Company / Builder Name</label><input type="text" name="company_name" placeholder="e.g. Skyline Developers" value="${existingDynamicData.company_name || ''}"></div>
            <div class="form-group"><label>RERA Registration No.</label><input type="text" name="rera_no" placeholder="e.g. UPRERA123456" value="${existingDynamicData.rera_no || ''}"></div>
            <div class="form-group"><label>GSTIN Number</label><input type="text" name="gst_no" placeholder="15-digit GSTIN" value="${existingDynamicData.gst_no || ''}"></div>
            <div class="form-group"><label>Official Website</label><input type="url" name="website" placeholder="https://www.example.com" value="${existingDynamicData.website || ''}"></div>
        `;
    } else if (val === 'Chartered Accountant (CA)' || val === 'Financial Advisor / DSA') {
        const label = (val === 'Chartered Accountant (CA)') ? 'ICAI Membership No.' : 'DSA License / Reg No.';
        const iconName = (val === 'Chartered Accountant (CA)') ? 'file-text' : 'briefcase';
        html = `
            <div style="grid-column: 1 / -1; font-size:12px; font-weight:700; color:#1d4ed8; text-transform:uppercase; letter-spacing:0.04em; display:flex; align-items:center; gap:6px;">
                <i data-lucide="${iconName}" style="width:14px; height:14px;"></i> Registered Firm / Agency Details
            </div>
            <div class="form-group"><label>Firm / Agency Name</label><input type="text" name="company_name" placeholder="Name of Firm" value="${existingDynamicData.company_name || ''}"></div>
            <div class="form-group"><label>${label}</label><input type="text" name="registration_number" placeholder="${label}" value="${existingDynamicData.registration_number || ''}"></div>
            <div class="form-group"><label>GSTIN Number</label><input type="text" name="gst_no" placeholder="15-digit GSTIN" value="${existingDynamicData.gst_no || ''}"></div>
            <div class="form-group"><label>Official Website</label><input type="url" name="website" placeholder="https://www.example.com" value="${existingDynamicData.website || ''}"></div>
        `;
    } else if (val === 'Individual Agent' || val === 'PROPERTY DEALER') {
        html = `
            <div style="grid-column: 1 / -1; font-size:12px; font-weight:700; color:#15803d; text-transform:uppercase; letter-spacing:0.04em; display:flex; align-items:center; gap:6px;">
                <i data-lucide="user-check" style="width:14px; height:14px;"></i> Agent Professional Profile
            </div>
            <div class="form-group"><label>Primary Occupation / Business</label><input type="text" name="occupation" placeholder="e.g. Loan Consultant / Real Estate Agent" value="${existingDynamicData.occupation || ''}"></div>
            <div class="form-group"><label>Operating Market / Region</label><input type="text" name="company_name" placeholder="e.g. Sanjay Place &amp; Civil Lines" value="${existingDynamicData.company_name || ''}"></div>
        `;
    }
    
    if (html !== '') {
        dynamicContainer.innerHTML = html;
        dynamicContainer.style.display = 'grid';
        dynamicContainer.className = 'grid-2-col';
    } else {
        dynamicContainer.innerHTML = '';
        dynamicContainer.style.display = 'none';
    }
    if (window.lucide) lucide.createIcons();
}

function populateBankDropdown() {
    const dl = document.getElementById('bank_list');
    if (!dl || typeof BANK_DIRECTORY === 'undefined') return;
    let allBanks = [];
    for (const cat in BANK_DIRECTORY) {
        allBanks = allBanks.concat(BANK_DIRECTORY[cat]);
    }
    allBanks = [...new Set(allBanks)].sort((a,b) => a.localeCompare(b));
    dl.innerHTML = allBanks.map(b => `<option value="${b}">`).join('');
}

function initValidationAndCapitalize() {
    const textFields = document.querySelectorAll('input[type="text"]');
    textFields.forEach(f => {
        f.addEventListener('input', function() {
            if (this.id === 'pan_number' || this.id === 'ifsc_code' || this.name === 'gst_no') {
                this.value = this.value.toUpperCase();
            }
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const typeSelect = document.getElementById('referrer_type');
    if (typeSelect) {
        populateDynamicFields(typeSelect.value);
        typeSelect.addEventListener('change', function() {
            populateDynamicFields(this.value);
        });
    }
    populateBankDropdown();
    initValidationAndCapitalize();
    switchSectionTab('sec-1', document.getElementById('tab-btn-1'));
});

// AJAX Form Submit with Luxury SweetAlert Feedback
document.getElementById('editReferralForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const submitBtns = document.querySelectorAll('.btn-save-submit');
    submitBtns.forEach(b => {
        b.disabled = true;
        b.innerHTML = '<i data-lucide="loader" class="spin"></i> Saving Profile...';
    });
    if (window.lucide) lucide.createIcons();

    try {
        const formData = new FormData(this);
        const res = await fetch('api.php?api=edit_referral', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Changes Saved Successfully!',
                text: data.message || 'Partner profile details and access rights have been updated.',
                timer: 1800,
                showConfirmButton: true,
                confirmButtonText: 'View Profile'
            }).then(() => {
                window.location.href = 'view_referral.php?id=<?php echo $id; ?>';
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Update Failed',
                text: data.error || 'Failed to update partner profile.',
                confirmButtonText: 'OK'
            });
            submitBtns.forEach(b => {
                b.disabled = false;
                b.innerHTML = '<i data-lucide="check-circle" style="width:16px; height:16px;"></i> Save All Changes';
            });
            if (window.lucide) lucide.createIcons();
        }
    } catch(err) {
        Swal.fire({
            icon: 'error',
            title: 'Network Error',
            text: 'Unable to reach the server. Please check your connection.',
            confirmButtonText: 'OK'
        });
        submitBtns.forEach(b => {
            b.disabled = false;
            b.innerHTML = '<i data-lucide="check-circle" style="width:16px; height:16px;"></i> Save All Changes';
        });
        if (window.lucide) lucide.createIcons();
    }
});
</script>

<?php require_once 'footer.php'; ?>
