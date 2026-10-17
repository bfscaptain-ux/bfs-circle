<?php
define('IS_SUBFOLDER', true);
require_once '../config.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'RM'])) {
    die("Access Denied");
}

$client_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($client_id === 0) die("Invalid Client ID");

// Fetch Primary Applicant File
$stmt = $db->prepare("SELECT * FROM applicants WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$client) die("Client file not found.");

// Fetch ALL Loan Files for this client across the system (Matched by Mobile or PAN)
$stmt_all_loans = $db->prepare("
    SELECT * FROM applicants 
    WHERE (mobile = ? AND mobile != '') OR (pan_number = ? AND pan_number != '') 
    ORDER BY created_at DESC
");
$stmt_all_loans->execute([$client['mobile'], $client['pan_number']]);
$all_loans = $stmt_all_loans->fetchAll(PDO::FETCH_ASSOC);

// If for any reason all_loans is empty, include the current file
if (empty($all_loans)) {
    $all_loans = [$client];
}

$all_app_ids = array_column($all_loans, 'id');
if (empty($all_app_ids)) $all_app_ids = [$client_id];

// Fetch ALL Documents across all loan files of this client
$placeholders = str_repeat('?,', count($all_app_ids) - 1) . '?';
$stmt_docs = $db->prepare("
    SELECT d.*, a.loan_id as file_loan_id, a.loan_type as file_loan_type 
    FROM applicant_documents d 
    LEFT JOIN applicants a ON d.applicant_id = a.id
    WHERE d.applicant_id IN ($placeholders) 
       OR (d.client_mobile = ? AND d.client_mobile != '') 
       OR (d.client_pan = ? AND d.client_pan != '') 
    ORDER BY d.uploaded_at DESC
");
$params_docs = array_merge($all_app_ids, [$client['mobile'], $client['pan_number']]);
$stmt_docs->execute($params_docs);
$documents = $stmt_docs->fetchAll(PDO::FETCH_ASSOC);

// Fetch Bank Assignments for current loan file
$stmt_bank = $db->prepare("SELECT * FROM applicant_bank_assignments WHERE applicant_id = ? ORDER BY id DESC");
$stmt_bank->execute([$client_id]);
$banks = $stmt_bank->fetchAll(PDO::FETCH_ASSOC);

// Fetch Co-applicants for current loan file
$stmt_co = $db->prepare("SELECT * FROM co_applicants WHERE applicant_id = ?");
$stmt_co->execute([$client_id]);
$co_applicants = $stmt_co->fetchAll(PDO::FETCH_ASSOC);

// Fetch Disbursements for current loan file
$stmt_disb = $db->prepare("SELECT * FROM applicant_disbursements WHERE applicant_id = ? ORDER BY disbursed_at ASC");
$stmt_disb->execute([$client_id]);
$disbursements = $stmt_disb->fetchAll(PDO::FETCH_ASSOC);

// Fetch active staff for loan officer assignment
$staff_list = $db->query("SELECT username, name, role FROM users WHERE role IN ('Admin', 'RM', 'Staff') AND is_active = 1 ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);

// Summary Stats
$total_borrowed_volume = 0;
foreach ($all_loans as $ln) {
    $amt = $ln['sanctioned_amount'] > 0 ? $ln['sanctioned_amount'] : $ln['loan_amount_requested'];
    $total_borrowed_volume += (float)$amt;
}

$hide_main_header = true; // Removes old generic header
$current_page = 'client_vault';
$page_title = '360° Client Vault - ' . htmlspecialchars($client['customer_name']);

require_once '../header.php';
?>

<style>
    .vault-profile-container { width: 100%; margin: 0 auto; padding: 6px 4px 40px 4px; }
    
    /* Hero Banner */
    .client-hero-card {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        border-radius: 16px;
        padding: 30px 32px;
        color: #ffffff;
        margin-bottom: 24px;
        box-shadow: 0 10px 30px rgba(15,23,42,0.12);
        position: relative;
        overflow: hidden;
    }
    .client-hero-card::after {
        content: '';
        position: absolute;
        top: -60%;
        right: -10%;
        width: 450px;
        height: 450px;
        background: radial-gradient(circle, rgba(59,130,246,0.18) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }
    .hero-top-row {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        flex-wrap: wrap;
        gap: 20px;
        position: relative;
        z-index: 2;
    }
    .hero-name {
        font-size: 28px;
        font-weight: 800;
        letter-spacing: -0.02em;
        margin: 0 0 10px 0;
        color: #ffffff;
    }
    .hero-tags {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        align-items: center;
    }
    .hero-tag {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: rgba(255,255,255,0.08);
        border: 1px solid rgba(255,255,255,0.14);
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 500;
        color: #e2e8f0;
    }
    
    /* Hero Metrics Bar */
    .hero-metrics-strip {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 16px;
        margin-top: 24px;
        padding-top: 20px;
        border-top: 1px solid rgba(255,255,255,0.12);
        position: relative;
        z-index: 2;
    }
    .hero-metric-item { display: flex; flex-direction: column; gap: 4px; }
    .hero-metric-val { font-size: 22px; font-weight: 800; color: #ffffff; }
    .hero-metric-lbl { font-size: 11.5px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: #94a3b8; }
    
    /* Layout Grid */
    .vault-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 24px;
    }
    @media (max-width: 1024px) {
        .vault-grid { grid-template-columns: 1fr; }
    }
    
    /* Content Cards */
    .v-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 22px;
        box-shadow: 0 4px 20px -5px rgba(0,0,0,0.03);
        margin-bottom: 24px;
    }
    .v-card-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 18px;
        padding-bottom: 14px;
        border-bottom: 1px solid #f1f5f9;
    }
    .v-card-title {
        font-size: 16.5px;
        font-weight: 800;
        color: #0f172a;
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0;
    }
    
    /* Data Items */
    .d-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; }
    .d-item { display: flex; flex-direction: column; gap: 3px; }
    .d-lbl { font-size: 11.5px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
    .d-val { font-size: 14px; font-weight: 700; color: #0f172a; }
    
    /* Document Cards */
    .doc-vault-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 14px;
    }
    .doc-vault-item {
        display: flex;
        align-items: center;
        padding: 14px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        transition: all 0.2s;
        text-decoration: none;
        color: inherit;
    }
    .doc-vault-item:hover {
        border-color: #2563eb;
        background: #ffffff;
        box-shadow: 0 4px 12px rgba(37,99,235,0.08);
        transform: translateY(-1px);
    }
    .doc-vault-icon {
        width: 38px;
        height: 38px;
        border-radius: 8px;
        background: #eff6ff;
        color: #2563eb;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 12px;
        flex-shrink: 0;
    }
    
    /* Multi-Loan File Card */
    .loan-file-card {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 16px;
        margin-bottom: 12px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 14px;
        transition: all 0.15s;
    }
    .loan-file-card:hover {
        border-color: #cbd5e1;
        background: #ffffff;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    }
    .loan-file-card.active-file {
        border-left: 4px solid #0f172a;
    }
    
    /* Buttons */
    .btn-hero-cta {
        background: #f59e0b;
        color: #0f172a;
        font-weight: 800;
        font-size: 14px;
        padding: 12px 22px;
        border-radius: 10px;
        border: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 4px 14px rgba(245,158,11,0.3);
        transition: all 0.2s;
    }
    .btn-hero-cta:hover {
        background: #fbbf24;
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(245,158,11,0.4);
    }
    
    .btn-sub-action {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        color: #0f172a;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 700;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .btn-sub-action:hover {
        background: #0f172a;
        color: #ffffff;
        border-color: #0f172a;
    }
</style>

<div class="vault-profile-container">
    
    <!-- Breadcrumb -->
    <div style="margin-bottom: 14px;">
        <a href="index.php" style="color: #64748b; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; font-weight: 600; font-size: 13.5px;">
            <i data-lucide="arrow-left" style="width: 15px; height: 15px;"></i> Back to Client Vault
        </a>
    </div>

    <!-- Hero Profile Banner -->
    <div class="client-hero-card">
        <div class="hero-top-row">
            <div>
                <div style="font-size: 11.5px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                    <i data-lucide="shield-check" style="width: 14px; height: 14px; color: #34d399;"></i> Verified Client Profile & Vault
                </div>
                <h1 class="hero-name"><?= htmlspecialchars($client['customer_name']) ?></h1>
                
                <div class="hero-tags">
                    <div class="hero-tag"><i data-lucide="phone" style="width: 13px;"></i> <?= htmlspecialchars($client['mobile']) ?></div>
                    <div class="hero-tag"><i data-lucide="mail" style="width: 13px;"></i> <?= htmlspecialchars($client['email'] ?: 'No email') ?></div>
                    <div class="hero-tag"><i data-lucide="map-pin" style="width: 13px;"></i> <?= htmlspecialchars(($client['city'] ? $client['city'] . ', ' : '') . ($client['state'] ?: 'India')) ?></div>
                    <div class="hero-tag" style="font-family: monospace; font-weight: 700; color: #38bdf8;">PAN: <?= htmlspecialchars($client['pan_number'] ?: 'N/A') ?></div>
                </div>
            </div>
            
            <div style="text-align: right;">
                <button type="button" class="btn-hero-cta" onclick="openActivateModal(<?= $client['id'] ?>, '<?= htmlspecialchars(addslashes($client['customer_name'])) ?>', '<?= htmlspecialchars(addslashes($client['mobile'])) ?>', '<?= htmlspecialchars(addslashes($client['pan_number'] ?: 'N/A')) ?>', <?= count($documents) ?>, '<?= htmlspecialchars($client['loan_type']) ?>', <?= (float)($client['sanctioned_amount'] ?: $client['loan_amount_requested']) ?>)">
                    <i data-lucide="zap" style="width: 17px; height: 17px;"></i> Activate New Loan Application
                </button>
            </div>
        </div>
        
        <!-- Metrics Strip -->
        <div class="hero-metrics-strip">
            <div class="hero-metric-item">
                <div class="hero-metric-val"><?= count($all_loans) ?> <?= count($all_loans) > 1 ? 'Files' : 'File' ?></div>
                <div class="hero-metric-lbl">Total Loan Facilities</div>
            </div>
            
            <div class="hero-metric-item">
                <div class="hero-metric-val">&#8377;<?= number_format($total_borrowed_volume, 0) ?></div>
                <div class="hero-metric-lbl">Total Borrowed / Disbursed</div>
            </div>
            
            <div class="hero-metric-item">
                <div class="hero-metric-val"><?= count($documents) ?> Docs</div>
                <div class="hero-metric-lbl">Verified Documents in Vault</div>
            </div>
            
            <div class="hero-metric-item">
                <div class="hero-metric-val" style="color: #34d399;"><?= htmlspecialchars($client['cibil_score'] ?: '750+') ?></div>
                <div class="hero-metric-lbl">CIBIL / Credit Score</div>
            </div>
        </div>
    </div>
    
    <!-- Grid Content Layout -->
    <div class="vault-grid">
        
        <!-- Left / Primary Column -->
        <div>
            
            <!-- 1. All Loan Files Portfolio (Multi-Loan History) -->
            <div class="v-card">
                <div class="v-card-head">
                    <h3 class="v-card-title">
                        <i data-lucide="layers" style="color: #2563eb;"></i> Client Loan Files & History (<?= count($all_loans) ?>)
                    </h3>
                    <span style="font-size: 12px; color: #64748b; font-weight: 600;">
                        All current and completed files for this borrower
                    </span>
                </div>
                
                <div>
                    <?php foreach($all_loans as $ln): 
                        $is_current = ($ln['id'] == $client_id);
                        $amt = $ln['sanctioned_amount'] > 0 ? $ln['sanctioned_amount'] : $ln['loan_amount_requested'];
                        
                        // Determine proper redirect url in LOS
                        $los_url = "../add_applicant.php?id=" . $ln['id'];
                        if ($ln['overall_status'] === 'Phase 2') $los_url = "../applicant_documents.php?id=" . $ln['id'];
                        elseif (in_array($ln['overall_status'], ['Phase 3', 'Phase 4'])) $los_url = "../applicant_bank_assign.php?id=" . $ln['id'];
                        elseif ($ln['overall_status'] === 'Completed') $los_url = "../applicant_disbursements.php?id=" . $ln['id'];
                    ?>
                    <div class="loan-file-card <?= $is_current ? 'active-file' : '' ?>">
                        <div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <strong style="font-size: 14.5px; color: #0f172a;"><?= htmlspecialchars($ln['loan_type']) ?></strong>
                                <span style="font-family: monospace; font-size: 12px; color: #64748b;">#<?= htmlspecialchars($ln['loan_id']) ?></span>
                                <?php if($is_current): ?>
                                    <span style="background: #e0f2fe; color: #0369a1; font-size: 10.5px; font-weight: 800; padding: 2px 6px; border-radius: 4px;">Viewing</span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size: 12.5px; color: #64748b; margin-top: 4px; display: flex; gap: 10px; align-items: center;">
                                <span>&#8377;<?= number_format((float)$amt, 0) ?></span>
                                <span>•</span>
                                <span>Date: <?= date('M d, Y', strtotime($ln['created_at'])) ?></span>
                                <?php if(!empty($ln['sanction_bank'])): ?>
                                    <span>•</span>
                                    <span>Bank: <strong><?= htmlspecialchars($ln['sanction_bank']) ?></strong></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <span class="badge-status" style="background: <?= $ln['overall_status'] === 'Completed' ? '#ecfdf5' : '#eff6ff' ?>; color: <?= $ln['overall_status'] === 'Completed' ? '#047857' : '#1d4ed8' ?>; border: 1px solid <?= $ln['overall_status'] === 'Completed' ? '#a7f3d0' : '#bfdbfe' ?>;">
                                <?= htmlspecialchars($ln['overall_status']) ?>
                            </span>
                            
                            <?php if(!$is_current): ?>
                                <a href="view_client.php?id=<?= $ln['id'] ?>" class="btn-sub-action">Switch View</a>
                            <?php endif; ?>
                            <a href="<?= $los_url ?>" class="btn-sub-action" style="background: #0f172a; color: #ffffff; border-color: #0f172a;">
                                <i data-lucide="external-link" style="width: 12px; height: 12px;"></i> Open in LOS
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- 2. Central Document Vault -->
            <div class="v-card">
                <div class="v-card-head">
                    <h3 class="v-card-title">
                        <i data-lucide="shield" style="color: #f59e0b;"></i> Verified Document Vault (<?= count($documents) ?>)
                    </h3>
                    <span style="font-size: 12px; color: #64748b; font-weight: 600;">
                        Documents automatically carried forward for repeat loans
                    </span>
                </div>
                
                <?php if(empty($documents)): ?>
                    <div style="text-align: center; padding: 30px; color: #94a3b8; font-size: 13.5px;">
                        <i data-lucide="folder-x" style="width: 36px; height: 36px; display: block; margin: 0 auto 8px auto; color: #cbd5e1;"></i>
                        No documents currently attached to this borrower's vault.
                    </div>
                <?php else: ?>
                    <div class="doc-vault-grid">
                        <?php foreach($documents as $doc): 
                            $filePath = '../' . ltrim($doc['file_path'], '/');
                            $cat = htmlspecialchars($doc['document_category'] ?: 'General');
                            $docName = htmlspecialchars($doc['document_name'] ?: basename($doc['file_path']));
                        ?>
                        <a href="<?= htmlspecialchars($filePath) ?>" target="_blank" class="doc-vault-item">
                            <div class="doc-vault-icon">
                                <i data-lucide="file-text" style="width: 18px; height: 18px;"></i>
                            </div>
                            <div style="flex: 1; min-width: 0;">
                                <div style="font-size: 13.5px; font-weight: 700; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 2px;">
                                    <?= $docName ?>
                                </div>
                                <div style="font-size: 11.5px; color: #64748b; display: flex; align-items: center; gap: 6px;">
                                    <span><?= $cat ?></span>
                                    <span>•</span>
                                    <span><?= !empty($doc['uploaded_at']) ? date('M d, Y', strtotime($doc['uploaded_at'])) : 'Verified' ?></span>
                                </div>
                            </div>
                            <div>
                                <i data-lucide="download" style="width: 14px; height: 14px; color: #94a3b8;"></i>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- 3. Demographic & Financial KYC -->
            <div class="v-card">
                <div class="v-card-head">
                    <h3 class="v-card-title">
                        <i data-lucide="user-check" style="color: #10b981;"></i> Borrower Identity & Financial Profile
                    </h3>
                </div>
                
                <div class="d-grid" style="margin-bottom: 20px;">
                    <div class="d-item">
                        <span class="d-lbl">PAN Number</span>
                        <span class="d-val" style="font-family: monospace; font-size: 15px; color: #2563eb;"><?= htmlspecialchars($client['pan_number'] ?: 'N/A') ?></span>
                    </div>
                    <div class="d-item">
                        <span class="d-lbl">Aadhaar Number</span>
                        <span class="d-val" style="font-family: monospace; font-size: 15px;"><?= htmlspecialchars($client['aadhar_number'] ?: 'N/A') ?></span>
                    </div>
                    <div class="d-item">
                        <span class="d-lbl">Employment Type</span>
                        <span class="d-val"><?= htmlspecialchars($client['employment_type'] ?: 'Salaried') ?></span>
                    </div>
                    <div class="d-item">
                        <span class="d-lbl">Monthly Net Income</span>
                        <span class="d-val">&#8377;<?= number_format((float)$client['monthly_income'], 0) ?></span>
                    </div>
                </div>
                
                <div class="d-grid" style="padding-top: 16px; border-top: 1px dashed #e2e8f0;">
                    <div class="d-item">
                        <span class="d-lbl">Organization / Employer</span>
                        <span class="d-val"><?= htmlspecialchars($client['organization_name'] ?: 'Not Specified') ?></span>
                    </div>
                    <div class="d-item">
                        <span class="d-lbl">Permanent Address</span>
                        <span class="d-val"><?= htmlspecialchars($client['address'] ?: 'Not Specified') ?></span>
                    </div>
                    <div class="d-item">
                        <span class="d-lbl">City, State & Pincode</span>
                        <span class="d-val"><?= htmlspecialchars(($client['city'] ? $client['city'] . ', ' : '') . ($client['state'] ? $client['state'] . ' - ' : '') . $client['pincode']) ?></span>
                    </div>
                </div>
            </div>
            
        </div>
        
        <!-- Right / Sidebar Column -->
        <div>
            
            <!-- Quick Action Card -->
            <div class="v-card" style="background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%); border-color: #cbd5e1;">
                <h4 style="margin: 0 0 10px 0; font-size: 16px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                    <i data-lucide="zap" style="color: #f59e0b;"></i> One-Click Re-Activation
                </h4>
                <p style="font-size: 13px; color: #64748b; line-height: 1.5; margin: 0 0 16px 0;">
                    Instantly clone this borrower's verified profile and carry forward all <?= count($documents) ?> KYC documents into a brand-new loan application.
                </p>
                
                <button type="button" class="btn-hero-cta" style="width: 100%; justify-content: center;" onclick="openActivateModal(<?= $client['id'] ?>, '<?= htmlspecialchars(addslashes($client['customer_name'])) ?>', '<?= htmlspecialchars(addslashes($client['mobile'])) ?>', '<?= htmlspecialchars(addslashes($client['pan_number'] ?: 'N/A')) ?>', <?= count($documents) ?>, '<?= htmlspecialchars($client['loan_type']) ?>', <?= (float)($client['sanctioned_amount'] ?: $client['loan_amount_requested']) ?>)">
                    <i data-lucide="zap" style="width: 16px; height: 16px;"></i> Activate New Loan
                </button>
            </div>
            
            <!-- Co-Applicants Card -->
            <?php if(!empty($co_applicants)): ?>
            <div class="v-card">
                <div class="v-card-head">
                    <h3 class="v-card-title">
                        <i data-lucide="users" style="color: #8b5cf6;"></i> Co-Applicants (<?= count($co_applicants) ?>)
                    </h3>
                </div>
                <?php foreach($co_applicants as $co): ?>
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; margin-bottom: 10px;">
                        <div style="font-weight: 800; font-size: 13.5px; color: #0f172a;"><?= htmlspecialchars($co['full_name']) ?></div>
                        <div style="font-size: 12px; color: #64748b; margin-top: 3px;">
                            <?= htmlspecialchars($co['relationship'] ?: 'Co-borrower') ?> • <?= htmlspecialchars($co['mobile']) ?>
                        </div>
                        <div style="font-size: 12px; color: #64748b; margin-top: 2px; font-family: monospace;">
                            PAN: <?= htmlspecialchars($co['pan_number'] ?: 'N/A') ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <!-- Bank & Sanction Snapshot -->
            <?php if(!empty($banks)): ?>
            <div class="v-card">
                <div class="v-card-head">
                    <h3 class="v-card-title">
                        <i data-lucide="landmark" style="color: #059669;"></i> Underwriting Banks
                    </h3>
                </div>
                <?php foreach($banks as $b): ?>
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <div style="font-weight: 700; font-size: 13.5px; color: #0f172a;"><?= htmlspecialchars($b['bank_name']) ?></div>
                            <div style="font-size: 11.5px; color: #64748b; margin-top: 2px;">Assigned: <?= date('M d, Y', strtotime($b['assigned_at'] ?? 'now')) ?></div>
                        </div>
                        <span class="badge-status" style="background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0;">
                            <?= htmlspecialchars($b['status']) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
        </div>
        
    </div>
</div>

<!-- ========================================== -->
<!-- ⚡ RE-ACTIVATE FOR NEW LOAN APPLICATION MODAL -->
<!-- ========================================== -->
<div id="activateLoanModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.7); z-index: 999999; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div style="background: #ffffff; width: 100%; max-width: 580px; border-radius: 16px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.35); border: 1px solid #e2e8f0; overflow: hidden; animation: modalPop 0.25s ease-out;">
        
        <!-- Modal Header -->
        <div style="background: #0f172a; color: #ffffff; padding: 20px 24px; display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="width: 34px; height: 34px; background: rgba(255,255,255,0.15); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                    <i data-lucide="zap" style="width: 18px; height: 18px; color: #f59e0b;"></i>
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 17px; font-weight: 800; color: #ffffff; letter-spacing: -0.01em;">
                        Activate New Loan Application
                    </h3>
                    <p style="margin: 2px 0 0 0; font-size: 12px; color: #94a3b8;">
                        Re-activating existing Vault borrower into LOS pipeline
                    </p>
                </div>
            </div>
            <button onclick="closeActivateModal()" style="background: none; border: none; color: #94a3b8; cursor: pointer; padding: 4px;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#94a3b8'">
                <i data-lucide="x" style="width: 20px; height: 20px;"></i>
            </button>
        </div>
        
        <!-- Modal Body / Form -->
        <form id="activateLoanForm" onsubmit="submitActivateLoan(event)" style="padding: 24px;">
            <input type="hidden" name="existing_applicant_id" id="act_applicant_id">
            
            <!-- Borrower Snapshot Banner -->
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 16px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <div style="font-weight: 800; font-size: 15px; color: #0f172a;" id="act_client_name">-</div>
                    <div style="font-size: 12px; color: #64748b; margin-top: 3px; display: flex; gap: 10px;">
                        <span><i data-lucide="phone" style="width: 12px; height: 12px; vertical-align: middle;"></i> <span id="act_client_mobile">-</span></span>
                        <span>•</span>
                        <span>PAN: <strong style="color: #334155;" id="act_client_pan">-</strong></span>
                    </div>
                </div>
                <div style="text-align: right;">
                    <span class="badge-doc-vault" id="act_docs_badge">
                        <i data-lucide="shield" style="width: 12px; height: 12px;"></i> Verified Profile
                    </span>
                </div>
            </div>
            
            <!-- Form Grid -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                        New Loan Product <span style="color: #ef4444;">*</span>
                    </label>
                    <select name="loan_type" id="act_loan_type" required style="width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13.5px; font-weight: 600; color: #0f172a; outline: none; background: #ffffff;">
                        <option value="Personal Loan">Personal Loan</option>
                        <option value="Home Loan">Home Loan</option>
                        <option value="Business Loan">Business Loan</option>
                        <option value="Mortgage Loan">Mortgage Loan / LAP</option>
                        <option value="Auto Loan">Auto / Vehicle Loan</option>
                        <option value="Top-up Loan">Top-up Loan</option>
                        <option value="Education Loan">Education Loan</option>
                        <option value="Credit Card">Credit Card</option>
                    </select>
                </div>
                
                <div>
                    <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                        Loan Sub-Type / Purpose
                    </label>
                    <select name="loan_sub_type" id="act_loan_sub_type" style="width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13.5px; font-weight: 500; color: #0f172a; outline: none; background: #ffffff;">
                        <option value="Fresh Loan">Fresh Loan</option>
                        <option value="Top-up Facility">Top-up Facility</option>
                        <option value="Balance Transfer">Balance Transfer</option>
                        <option value="Working Capital">Working Capital</option>
                        <option value="Home Renovation">Home Renovation</option>
                        <option value="Plot Purchase">Plot Purchase</option>
                    </select>
                </div>
            </div>
            
            <!-- Amount and Tenure -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                        Requested Amount (₹) <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="number" name="loan_amount_requested" id="act_loan_amount" required placeholder="e.g. 500000" min="10000" step="5000" oninput="previewIndianCurrency(this.value)" style="width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; font-weight: 700; color: #0f172a; outline: none; box-sizing: border-box;">
                    <div id="currencyWordsPreview" style="font-size: 11.5px; color: #2563eb; font-weight: 600; margin-top: 4px; min-height: 16px;"></div>
                </div>
                
                <div>
                    <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                        Tenure (Years / Months)
                    </label>
                    <select name="tenure_months" id="act_tenure_months" style="width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13.5px; font-weight: 600; color: #0f172a; outline: none; background: #ffffff;">
                        <option value="12">1 Year (12 Months)</option>
                        <option value="24">2 Years (24 Months)</option>
                        <option value="36" selected>3 Years (36 Months)</option>
                        <option value="48">4 Years (48 Months)</option>
                        <option value="60">5 Years (60 Months)</option>
                        <option value="84">7 Years (84 Months)</option>
                        <option value="120">10 Years (120 Months)</option>
                        <option value="180">15 Years (180 Months)</option>
                        <option value="240">20 Years (240 Months)</option>
                    </select>
                </div>
            </div>
            
            <!-- Staff Assignment -->
            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                    Assign Loan Officer / RM
                </label>
                <select name="assigned_to" id="act_assigned_to" style="width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13.5px; color: #0f172a; outline: none; background: #ffffff;">
                    <option value="">Leave Unassigned (System Queue)</option>
                    <?php foreach($staff_list as $st): ?>
                        <option value="<?= htmlspecialchars($st['username']) ?>">
                            <?= htmlspecialchars($st['name'] ?: $st['username']) ?> (<?= htmlspecialchars($st['role']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Vault Options Box -->
            <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; padding: 14px 16px; margin-bottom: 20px;">
                <div style="font-weight: 700; font-size: 13px; color: #166534; margin-bottom: 10px; display: flex; align-items: center; gap: 6px;">
                    <i data-lucide="check-check" style="width: 16px; height: 16px;"></i> Smart Vault Automation
                </div>
                
                <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #1e293b; font-weight: 600; cursor: pointer; margin-bottom: 8px;">
                    <input type="checkbox" name="clone_documents" value="1" checked style="width: 16px; height: 16px; accent-color: #0f172a; cursor: pointer;">
                    Carry forward verified KYC Documents (PAN, Aadhaar, Statements) from Vault
                </label>
                
                <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #1e293b; font-weight: 600; cursor: pointer;">
                    <input type="checkbox" name="clone_coapplicants" value="1" checked style="width: 16px; height: 16px; accent-color: #0f172a; cursor: pointer;">
                    Carry forward registered Co-Applicants & Guarantors
                </label>
            </div>
            
            <!-- Executive Notes -->
            <div style="margin-bottom: 24px;">
                <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                    Executive Notes / Re-activation Remarks
                </label>
                <textarea name="notes" id="act_notes" rows="2" placeholder="e.g. Existing borrower requested top-up loan. Documents already on file." style="width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; outline: none; resize: vertical; box-sizing: border-box;"></textarea>
            </div>
            
            <!-- Modal Actions -->
            <div style="display: flex; justify-content: flex-end; gap: 12px; align-items: center;">
                <button type="button" onclick="closeActivateModal()" style="padding: 11px 18px; background: #f1f5f9; color: #475569; border: none; border-radius: 8px; font-weight: 700; font-size: 13.5px; cursor: pointer;">
                    Cancel
                </button>
                <button type="submit" id="btnSubmitActivate" style="padding: 11px 22px; background: #0f172a; color: #ffffff; border: none; border-radius: 8px; font-weight: 700; font-size: 13.5px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(15,23,42,0.2);">
                    <i data-lucide="zap" style="width: 16px; height: 16px; color: #f59e0b;"></i>
                    <span id="btnSubmitActivateText">Activate & Enter LOS Pipeline</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function formatINR(val) {
    if (!val || isNaN(val)) return '0';
    return new Intl.NumberFormat('en-IN').format(val);
}

function previewIndianCurrency(val) {
    const preview = document.getElementById('currencyWordsPreview');
    if (!val || val <= 0) {
        preview.innerText = '';
        return;
    }
    preview.innerText = '₹ ' + formatINR(val);
}

function openActivateModal(id, name, mobile, pan, docsCount, lastLoanType, lastAmount) {
    document.getElementById('act_applicant_id').value = id;
    document.getElementById('act_client_name').innerText = name;
    document.getElementById('act_client_mobile').innerText = mobile;
    document.getElementById('act_client_pan').innerText = pan;
    document.getElementById('act_docs_badge').innerHTML = `<i data-lucide="shield-check" style="width:12px; height:12px;"></i> ${docsCount} Verified Docs in Vault`;
    
    const amountInput = document.getElementById('act_loan_amount');
    amountInput.value = lastAmount > 0 ? lastAmount : 500000;
    previewIndianCurrency(amountInput.value);
    
    document.getElementById('activateLoanModal').style.display = 'flex';
    lucide.createIcons();
}

function closeActivateModal() {
    document.getElementById('activateLoanModal').style.display = 'none';
}

function submitActivateLoan(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSubmitActivate');
    const btnText = document.getElementById('btnSubmitActivateText');
    const form = document.getElementById('activateLoanForm');
    const formData = new FormData(form);
    
    btn.disabled = true;
    btnText.innerText = 'Creating LOS Application...';
    
    fetch('../api.php?api=reapply_applicant', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            closeActivateModal();
            
            if(typeof showNotification === 'function') {
                showNotification(`New Application #${data.loan_id} successfully activated for ${data.customer_name}! ${data.cloned_docs_count} verified documents carried forward.`, 'success');
            } else {
                alert(`Success! Application #${data.loan_id} created with ${data.cloned_docs_count} documents carried forward.`);
            }
            
            setTimeout(() => {
                window.location.href = `../applicant_documents.php?id=${data.id}&activated=1&loan_id=${encodeURIComponent(data.loan_id)}`;
            }, 1000);
        } else {
            alert(data.error || 'Failed to activate loan application. Please try again.');
            btn.disabled = false;
            btnText.innerText = 'Activate & Enter LOS Pipeline';
        }
    })
    .catch(err => {
        console.error('Activation error:', err);
        alert('Server connection error. Please try again.');
        btn.disabled = false;
        btnText.innerText = 'Activate & Enter LOS Pipeline';
    });
}

document.addEventListener("DOMContentLoaded", () => {
    lucide.createIcons();
});
</script>

<?php require_once '../footer.php'; ?>
