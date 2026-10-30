<?php
require_once __DIR__ . '/config.php';

$token = preg_replace('/[^a-f0-9]/i', '', $_GET['token'] ?? '');

// Simple file-based rate limit for invalid tokens
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$log_file = __DIR__ . '/uploads/rate_limit.txt';
$attempts = 0;
if (file_exists($log_file)) {
    $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $time_limit = time() - 3600; // 1 hour
    $valid_lines = [];
    foreach ($lines as $line) {
        list($log_ip, $log_time) = explode('|', $line);
        if ($log_time > $time_limit) {
            $valid_lines[] = $line;
            if ($log_ip === $ip) {
                $attempts++;
            }
        }
    }
    file_put_contents($log_file, implode(PHP_EOL, $valid_lines) . PHP_EOL);
}

if ($attempts > 10) {
    die("<!DOCTYPE html><html><head>
    <!-- Official BFS Circle Monochrome Favicon & Branding -->
    <link rel="icon" type="image/png" sizes="64x64" href="<?php echo isset($base_path) ? $base_path : ""; ?>favicon.png?v=4">
    <link rel="icon" type="image/x-icon" href="<?php echo isset($base_path) ? $base_path : ""; ?>favicon.ico?v=4">
    <link rel="shortcut icon" href="<?php echo isset($base_path) ? $base_path : ""; ?>favicon.ico?v=4">
    <link rel="apple-touch-icon" href="<?php echo isset($base_path) ? $base_path : ""; ?>favicon.png?v=4"><title>Too Many Requests</title><meta name='viewport' content='width=device-width, initial-scale=1'></head><body style='font-family:sans-serif; text-align:center; padding:50px;'><h2>Rate limit exceeded. Try again later.</h2></body></html>");
}

if(strlen($token) !== 32) {
    file_put_contents($log_file, $ip . '|' . time() . PHP_EOL, FILE_APPEND);
    die("<!DOCTYPE html><html><head><title>Invalid Token</title><meta name='viewport' content='width=device-width, initial-scale=1'></head><body style='font-family:sans-serif; text-align:center; padding:50px;'><h2>Invalid or expired tracking link.</h2></body></html>");
}

$stmt = $db->prepare("SELECT a.*, u.name as rm_name, e.mobile as rm_mobile 
                      FROM applicants a 
                      LEFT JOIN users u ON u.username = a.assigned_to 
                      LEFT JOIN employees e ON e.user_id = u.id 
                      WHERE a.tracking_token = ? 
                      AND datetime(a.tracking_token_created, '+30 days') > datetime('now')");
$stmt->execute([$token]);
$applicant = $stmt->fetch();

if (!$applicant) {
    file_put_contents($log_file, $ip . '|' . time() . PHP_EOL, FILE_APPEND);
    die("<!DOCTYPE html><html><head><title>Link Expired</title><meta name='viewport' content='width=device-width, initial-scale=1'></head><body style='font-family:sans-serif; text-align:center; padding:50px;'><h2>This tracking link has expired or is invalid. Please contact your RM for a new link.</h2></body></html>");
}

// Fetch pending documents
$doc_stmt = $db->prepare("SELECT document_name, notes FROM applicant_documents WHERE applicant_id = ? AND status = 'Pending'");
$doc_stmt->execute([$applicant['id']]);
$pending_docs = $doc_stmt->fetchAll();

// Fetch already uploaded documents
$uploaded_stmt = $db->prepare("SELECT document_name, file_path, status, uploaded_at FROM applicant_documents WHERE applicant_id = ? AND status != 'Pending' ORDER BY id DESC");
$uploaded_stmt->execute([$applicant['id']]);
$uploaded_docs = $uploaded_stmt->fetchAll();

$bank_logins_stmt = $db->prepare("SELECT * FROM applicant_bank_logins WHERE applicant_id = ? ORDER BY id DESC");
$bank_logins_stmt->execute([$applicant['id']]);
$bank_logins = $bank_logins_stmt->fetchAll();

// Fetch referrals made by this customer (Refer & Earn)
$cust_refs_stmt = $db->prepare("SELECT * FROM customer_referrals WHERE referrer_applicant_id = ? ORDER BY id DESC");
$cust_refs_stmt->execute([$applicant['id']]);
$customer_referrals = $cust_refs_stmt->fetchAll(PDO::FETCH_ASSOC);

$total_customer_referrals = count($customer_referrals);
$total_reward_earned = 0;
foreach ($customer_referrals as $cr) {
    if (in_array(strtolower($cr['status'] ?? ''), ['disbursed', 'completed', 'sanctioned'])) {
        $total_reward_earned += floatval($cr['reward_amount'] ?? 2000);
    }
}

$http_protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
$host = $_SERVER['HTTP_HOST'] ?? '192.168.1.6:8000';
$referral_url = $http_protocol . '://' . $host . '/apply.php?ref=CUST-' . $applicant['id'];
$whatsapp_share_text = "Namaste! Apply for Home / Business / Personal Loan through BFS Financial with fast approval & lowest interest rates. Click here: " . $referral_url;
$whatsapp_share_url = "https://wa.me/?text=" . urlencode($whatsapp_share_text);


// Fetch co-applicants for document suggestions
$co_stmt = $db->prepare("SELECT id, full_name, relationship FROM co_applicants WHERE applicant_id = ?");
$co_stmt->execute([$applicant['id']]);
$co_applicants = $co_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all uploaded documents for smart matching
$all_docs_stmt = $db->prepare("SELECT * FROM applicant_documents WHERE applicant_id = ? ORDER BY id DESC");
$all_docs_stmt->execute([$applicant['id']]);
$all_uploaded_docs = $all_docs_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate loan document suggestions matrix
$suggestions = getLoanDocumentSuggestions(
    $applicant['loan_type'] ?? '',
    $applicant['loan_sub_type'] ?? '',
    $applicant['employment_type'] ?? 'Salaried',
    $co_applicants
);

$checklist_categories = [];
$total_checklist_req = 0;
$total_checklist_uploaded = 0;

foreach ($suggestions as &$sug) {
    $cat = $sug['category'] ?? 'Basic KYC';
    
    // Check if uploaded
    $is_uploaded = false;
    $matched_doc = null;
    $sug_name = strtolower($sug['doc_name']);
    $sug_clean = preg_replace('/\s*\(.*?\)\s*/', '', $sug_name);

    foreach ($all_uploaded_docs as $d) {
        $doc_name = strtolower($d['document_name']);
        $doc_clean = preg_replace('/\s*\(.*?\)\s*/', '', $doc_name);
        
        $owner_match = true;
        if (($sug['owner_type'] ?? 'Applicant') === 'CoApplicant') {
            if (($d['owner_type'] ?? '') === 'CoApplicant') {
                if (!empty($sug['owner_id']) && !empty($d['owner_id'])) {
                    $owner_match = ($d['owner_id'] == $sug['owner_id']);
                }
            } else {
                $owner_match = false;
            }
        } else {
            if (($d['owner_type'] ?? 'Applicant') === 'CoApplicant') {
                $owner_match = false;
            }
        }
        
        if (!$owner_match) continue;

        $name_match = (
            $doc_name === $sug_name ||
            stripos($doc_name, $sug_clean) !== false ||
            stripos($sug_name, $doc_clean) !== false
        );

        if (!$name_match) {
            $keywords = ['pan', 'aadhaar', 'salary', 'bank', 'itr', 'form 16', 'passport', 'photo', 'voter', 'driving', 'electricity', 'utility', 'gst', 'trade', 'msme', 'registry', 'property', 'tax', 'vehicle', 'rc', 'cibil', 'valuation', 'relation'];
            foreach ($keywords as $kw) {
                if (preg_match('/\b' . preg_quote($kw, '/') . '\b/i', $sug_clean) && preg_match('/\b' . preg_quote($kw, '/') . '\b/i', $doc_clean)) {
                    $name_match = true;
                    break;
                }
            }
        }

        if ($name_match) {
            $is_uploaded = true;
            $matched_doc = $d;
            break;
        }
    }
    
    $is_reupload = ($matched_doc && intval($matched_doc['reupload_requested'] ?? 0) === 1);
    $is_locked = ($is_uploaded && !$is_reupload);

    $total_checklist_req++;
    if ($is_locked) $total_checklist_uploaded++;
    
    $sug['is_uploaded'] = $is_uploaded;
    $sug['is_reupload'] = $is_reupload;
    $sug['is_locked'] = $is_locked;
    $sug['matched_doc'] = $matched_doc;
    $checklist_categories[$cat][] = $sug;
}
unset($sug);

$completion_percent = $total_checklist_req > 0 ? round(($total_checklist_uploaded / $total_checklist_req) * 100) : 0;

// Fetch client queries
$queries_stmt = $db->prepare("SELECT * FROM client_queries WHERE applicant_id = ? ORDER BY id DESC");
$queries_stmt->execute([$applicant['id']]);
$client_queries = $queries_stmt->fetchAll();

// Fetch verification schedules (FI visits)
$verif_stmt = $db->prepare("SELECT * FROM verification_schedules WHERE applicant_id = ? ORDER BY id DESC");
$verif_stmt->execute([$applicant['id']]);
$verif_schedules = $verif_stmt->fetchAll();

// Determine current phase
$status = $applicant['overall_status'];
$phases = ['Phase 1' => 1, 'Phase 2' => 2, 'Phase 3' => 3, 'Phase 4' => 4, 'Disbursed' => 5, 'Completed' => 5];
$current_phase = $phases[$status] ?? 1;
if ($status === 'Rejected') {
    $current_phase = -1;
}

$first_name = explode(' ', trim($applicant['customer_name']))[0];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Application #<?= htmlspecialchars($applicant['loan_id']) ?> - BFS Circle</title>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --black: #0f172a;
            --black-solid: #000000;
            --bg: #f8fafc;
            --text: #0f172a;
            --gray-light: #e2e8f0;
            --gray: #64748b;
            accent-color: #0f172a;
            color-scheme: light;
        }

        * {
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--bg);
            color: var(--text);
            margin: 0;
            padding: 0;
            line-height: 1.5;
        }

        ::selection {
            background: #0f172a;
            color: #ffffff;
        }

        .container {
            max-width: 780px;
            margin: 0 auto;
            padding: 24px 16px;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--gray-light);
        }

        .logo {
            font-size: 24px;
            font-weight: 800;
            color: var(--black);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 4px;
            letter-spacing: -0.5px;
        }

        .security-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #0f172a;
            color: #ffffff;
            font-size: 11px;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 20px;
            margin-top: 6px;
            letter-spacing: 0.3px;
        }

        .card {
            background: #ffffff;
            border-radius: 14px;
            border: 1px solid var(--gray-light);
            padding: 22px;
            margin-bottom: 18px;
            box-shadow: 0 4px 6px -1px rgba(15, 23, 42, 0.04);
        }

        .dark-card {
            background: #0f172a;
            color: #ffffff;
            border-radius: 14px;
            padding: 22px;
            margin-bottom: 18px;
            box-shadow: 0 10px 20px -5px rgba(15, 23, 42, 0.25);
        }

        .greeting { font-size: 1.4rem; font-weight: 800; margin-bottom: 2px; color: #0f172a; }
        .loan-type { color: var(--gray); font-size: 0.9rem; font-weight: 600; margin-bottom: 20px; }

        /* Summary Grid */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 10px;
            margin-bottom: 20px;
        }
        .summary-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px;
        }
        .summary-label { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; }
        .summary-val { font-size: 14px; font-weight: 800; color: #0f172a; margin-top: 2px; }
        
        /* Monochrome Progress Bar */
        .progress-wrapper {
            margin: 25px 0 10px 0;
            position: relative;
        }
        .progress-line {
            position: absolute;
            top: 15px;
            left: 10%;
            right: 10%;
            height: 3px;
            background: #e2e8f0;
            z-index: 1;
            overflow: hidden;
            border-radius: 2px;
        }
        .progress-line-fill {
            height: 100%;
            background: #0f172a;
            transition: width 0.4s ease;
        }
        .steps {
            display: flex;
            justify-content: space-between;
            position: relative;
            z-index: 3;
        }
        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 20%;
        }
        .step-circle {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #ffffff;
            border: 2.5px solid #cbd5e1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: bold;
            color: var(--gray);
            margin-bottom: 8px;
            transition: all 0.3s ease;
        }
        .step.active .step-circle {
            border-color: #0f172a;
            background: #0f172a;
            color: #ffffff;
            box-shadow: 0 0 0 4px rgba(15, 23, 42, 0.15);
        }
        .step.completed .step-circle {
            border-color: #0f172a;
            background: #0f172a;
            color: #ffffff;
        }
        .step-label {
            font-size: 11px;
            text-align: center;
            color: var(--gray);
            white-space: nowrap;
        }
        .step.active .step-label, .step.completed .step-label {
            color: #0f172a;
            font-weight: 800;
        }

        /* CUSTOM MONOCHROME SELECT DROPDOWN (NO BROWSER BLUE HIGHLIGHT) */
        .custom-select-wrapper {
            position: relative;
            user-select: none;
            margin-bottom: 16px;
        }
        .custom-select-trigger {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            background: #ffffff;
            border: 1.5px solid #0f172a;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            color: #0f172a;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .custom-select-trigger:hover {
            background: #f8fafc;
        }
        .custom-select-options {
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            right: 0;
            background: #ffffff;
            border: 1.5px solid #0f172a;
            border-radius: 10px;
            box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.25);
            z-index: 100;
            display: none;
            max-height: 240px;
            overflow-y: auto;
        }
        .custom-select-options.open {
            display: block;
        }
        .custom-option {
            padding: 12px 16px;
            font-size: 13.5px;
            font-weight: 600;
            color: #0f172a;
            cursor: pointer;
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.15s ease, color 0.15s ease;
        }
        .custom-option:last-child {
            border-bottom: none;
        }
        .custom-option:hover {
            background: #0f172a;
            color: #ffffff;
        }
        .custom-option.selected {
            background: #0f172a;
            color: #ffffff;
            font-weight: 800;
        }
        .custom-option.pending-option {
            background: #f8fafc;
            font-weight: 800;
        }
        .custom-option.pending-option:hover {
            background: #0f172a;
            color: #ffffff;
        }

        /* Input Controls */
        .ctrl-input-mono {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid #0f172a;
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 600;
            color: #0f172a;
            background: #ffffff;
            margin-bottom: 12px;
            outline: none;
            font-family: inherit;
        }
        .ctrl-input-mono:focus {
            box-shadow: 0 0 0 3px rgba(15, 23, 42, 0.15);
        }

        /* Buttons */
        .btn-monochrome {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: #0f172a;
            color: #ffffff;
            text-decoration: none;
            padding: 12px 18px;
            border-radius: 10px;
            font-weight: 800;
            font-size: 14px;
            width: 100%;
            box-sizing: border-box;
            border: 1.5px solid #0f172a;
            cursor: pointer;
            transition: background 0.2s ease, opacity 0.2s ease;
        }
        .btn-monochrome:hover {
            background: #000000;
            color: #ffffff;
        }
        .btn-monochrome-outline {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: #ffffff;
            color: #0f172a;
            text-decoration: none;
            padding: 12px 18px;
            border-radius: 10px;
            font-weight: 800;
            font-size: 14px;
            width: 100%;
            box-sizing: border-box;
            border: 1.5px solid #0f172a;
            cursor: pointer;
            transition: background 0.2s ease;
        }
        .btn-monochrome-outline:hover {
            background: #f1f5f9;
        }

        /* Upload Box */
        .upload-box {
            background: #f8fafc;
            border: 2px dashed #0f172a;
            border-radius: 12px;
            padding: 24px 16px;
            text-align: center;
            margin-top: 15px;
            transition: background 0.2s ease;
            cursor: pointer;
        }
        .upload-box.dragover {
            background: #e2e8f0;
            border-style: solid;
        }
        .upload-input {
            display: none;
        }
        .upload-label {
            display: inline-block;
            background: #0f172a;
            color: #ffffff;
            padding: 10px 22px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
            margin-top: 10px;
            transition: background 0.2s ease;
        }
        .upload-label:hover {
            background: #000000;
        }

        .uploaded-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13px;
        }
        .uploaded-item:last-child { border-bottom: none; }

        .status-badge-mono {
            background: #f1f5f9;
            color: #0f172a;
            border: 1px solid #0f172a;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 800;
            white-space: nowrap;
        }

        .rejected-state {
            text-align: center;
            padding: 30px 10px;
        }
        .rejected-state i { color: #0f172a; width: 48px; height: 48px; margin-bottom: 10px;}

        /* SweetAlert Monochrome Override */
        .swal2-popup {
            font-family: inherit !important;
            border-radius: 16px !important;
            border: 1px solid #e2e8f0 !important;
        }
        .swal2-styled.swal2-confirm {
            background-color: #0f172a !important;
            box-shadow: none !important;
            border-radius: 8px !important;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- HEADER -->
        <div class="header">
            <div class="logo">
                <img src="logo.png" alt="BFS Circle Logo" style="height: 38px; width: auto; object-fit: contain; filter: brightness(0);" onerror="this.style.display='none'">
                BFS Circle
            </div>
            <div style="font-size: 0.9rem; color: var(--gray); font-weight: 600;">Application Tracking Portal</div>
            <div class="security-badge">
                <i data-lucide="shield-check" style="width: 14px; height: 14px;"></i>
                256-BIT ENCRYPTED • VALID FOR 30 DAYS
            </div>
        </div>
        
        <!-- CARD 1: APPLICANT STAGE TIMELINE & FINANCIAL SUMMARY -->
        <div class="card">
            <div class="greeting">Hi <?= htmlspecialchars($first_name) ?>,</div>
            <div class="loan-type"><?= htmlspecialchars($applicant['loan_type']) ?> Application (Ref: #<?= htmlspecialchars($applicant['loan_id']) ?>)</div>
            
            <div class="summary-grid">
                <div class="summary-box">
                    <div class="summary-label">Requested Loan</div>
                    <div class="summary-val">₹<?= number_format($applicant['loan_amount_requested'] ?? 0) ?></div>
                </div>
                <div class="summary-box">
                    <div class="summary-label">Loan Sub-Type</div>
                    <div class="summary-val"><?= htmlspecialchars($applicant['loan_sub_type'] ?: 'Standard') ?></div>
                </div>
                <div class="summary-box">
                    <div class="summary-label">Employment</div>
                    <div class="summary-val"><?= htmlspecialchars($applicant['employment_type'] ?: 'Salaried') ?></div>
                </div>
                <div class="summary-box">
                    <div class="summary-label">CIBIL Status</div>
                    <div class="summary-val"><?= htmlspecialchars($applicant['cibil_score'] ?: '750+') ?></div>
                </div>
            </div>

            <?php if($current_phase === -1): ?>
                <div class="rejected-state">
                    <i data-lucide="x-circle"></i>
                    <h3 style="margin:0 0 10px 0;">Application Status Update</h3>
                    <p style="color:var(--gray); font-size:0.9rem;">We are unable to proceed with your application at this stage. Please contact your Relationship Manager for detailed feedback.</p>
                </div>
            <?php else: ?>
                <?php
                    $fill_width = (($current_phase - 1) / 4) * 100;
                    if($fill_width > 100) $fill_width = 100;
                ?>
                <div class="progress-wrapper">
                    <div class="progress-line">
                        <div class="progress-line-fill" style="width: <?= $fill_width ?>%;"></div>
                    </div>
                    <div class="steps">
                        <div class="step <?= $current_phase >= 1 ? 'completed' : '' ?> <?= $current_phase == 1 ? 'active' : '' ?>">
                            <div class="step-circle"><i data-lucide="user" style="width: 16px;"></i></div>
                            <div class="step-label">KYC</div>
                        </div>
                        <div class="step <?= $current_phase >= 2 ? 'completed' : '' ?> <?= $current_phase == 2 ? 'active' : '' ?>">
                            <div class="step-circle"><i data-lucide="file-text" style="width: 16px;"></i></div>
                            <div class="step-label">Docs</div>
                        </div>
                        <div class="step <?= $current_phase >= 3 ? 'completed' : '' ?> <?= $current_phase == 3 ? 'active' : '' ?>">
                            <div class="step-circle"><i data-lucide="building" style="width: 16px;"></i></div>
                            <div class="step-label">Bank Review</div>
                        </div>
                        <div class="step <?= $current_phase >= 4 ? 'completed' : '' ?> <?= $current_phase == 4 ? 'active' : '' ?>">
                            <div class="step-circle"><i data-lucide="check-circle" style="width: 16px;"></i></div>
                            <div class="step-label">Sanction</div>
                        </div>
                        <div class="step <?= $current_phase >= 5 ? 'completed' : '' ?> <?= $current_phase == 5 ? 'active' : '' ?>">
                            <div class="step-circle"><i data-lucide="coins" style="width: 16px;"></i></div>
                            <div class="step-label">Disbursed</div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- CARD: DIGITAL LOAN SANCTION APPROVAL SUMMARY -->
        <?php if(($applicant['sanctioned_amount'] ?? 0) > 0 || $current_phase >= 4): ?>
        <div class="card" style="background:#f8fafc; border:1.5px solid #0f172a;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                <h3 style="margin:0; font-size:1.1rem; color:#0f172a; display:flex; align-items:center; gap:8px;">
                    <i data-lucide="award" style="width:22px; color:#0f172a;"></i>
                    Digital Loan Sanction Approval Summary
                </h3>
                <?php if(($applicant['sanction_accepted'] ?? 0) == 1): ?>
                    <span class="status-badge-mono" style="background:#0f172a; color:#fff;">✓ Sanction Accepted</span>
                <?php else: ?>
                    <span class="status-badge-mono">Action Needed</span>
                <?php endif; ?>
            </div>
            <div class="summary-grid" style="margin-bottom:14px;">
                <div class="summary-box" style="background:#fff;">
                    <div class="summary-label">Approved Bank</div>
                    <div class="summary-val"><?= htmlspecialchars($applicant['sanction_bank'] ?: 'Partner Bank') ?></div>
                </div>
                <div class="summary-box" style="background:#fff;">
                    <div class="summary-label">Sanctioned Amount</div>
                    <div class="summary-val">₹<?= number_format($applicant['sanctioned_amount'] ?? 0) ?></div>
                </div>
                <div class="summary-box" style="background:#fff;">
                    <div class="summary-label">Interest Rate</div>
                    <div class="summary-val"><?= htmlspecialchars($applicant['sanction_roi'] ?? '8.5') ?>% p.a.</div>
                </div>
                <div class="summary-box" style="background:#fff;">
                    <div class="summary-label">Approved Tenure</div>
                    <div class="summary-val"><?= htmlspecialchars($applicant['sanction_tenure'] ?? '240') ?> Months</div>
                </div>
            </div>
            <?php if(($applicant['sanction_accepted'] ?? 0) == 1): ?>
                <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:10px; font-size:12px; color:#334155;">
                    ✓ You digitally accepted these sanction terms on <strong><?= date('d M Y, h:i A', strtotime($applicant['sanction_accepted_at'])) ?></strong>. Disbursal processing is in progress!
                </div>
            <?php else: ?>
                <button type="button" class="btn-monochrome" onclick="acceptSanctionTerms()">
                    👍 Accept Sanction Terms & Proceed to Disbursal
                </button>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- CARD: SCHEDULED PHYSICAL VERIFICATION & FIELD VISITS -->
        <?php if(!empty($verif_schedules)): ?>
        <div class="card">
            <h3 style="margin:0 0 12px 0; font-size:1.05rem; display:flex; align-items:center; gap:8px; color:#0f172a;">
                <i data-lucide="calendar-check" style="width: 20px;"></i>
                Scheduled Physical Verification & Field Visits
            </h3>
            <div style="display:flex; flex-direction:column; gap:10px;">
                <?php foreach($verif_schedules as $vs): ?>
                    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:12px 14px;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <strong style="color:#0f172a; font-size:13.5px;">🏠 <?= htmlspecialchars($vs['verification_type']) ?></strong>
                            <span class="status-badge-mono"><?= htmlspecialchars($vs['client_confirmation'] ?: 'Pending') ?></span>
                        </div>
                        <div style="font-size:12px; color:#334155; margin-top:4px;">
                            📅 Date: <strong><?= date('d M Y', strtotime($vs['scheduled_date'])) ?></strong> at <strong><?= htmlspecialchars($vs['scheduled_time']) ?></strong>
                            <?= $vs['officer_name'] ? ' • Officer: ' . htmlspecialchars($vs['officer_name']) . ($vs['officer_mobile'] ? ' (' . htmlspecialchars($vs['officer_mobile']) . ')' : '') : '' ?>
                        </div>
                        <?php if($vs['client_confirmation'] === 'Pending'): ?>
                            <div style="display:flex; gap:8px; margin-top:10px;">
                                <button type="button" class="btn-monochrome" style="padding:6px 12px; font-size:12px;" onclick="confirmVerificationVisit(<?= $vs['id'] ?>, 'Confirmed')">👍 Confirm Availability</button>
                                <button type="button" class="btn-monochrome-outline" style="padding:6px 12px; font-size:12px;" onclick="confirmVerificationVisit(<?= $vs['id'] ?>, 'Reschedule Requested')">🔄 Request Reschedule</button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- CARD 2: MULTI-BANK APPLICATION TRACKER -->
        <?php if(!empty($bank_logins)): ?>
        <div class="card">
            <h3 style="margin:0 0 12px 0; font-size:1.05rem; display:flex; align-items:center; gap:8px; color:#0f172a;">
                <i data-lucide="landmark" style="width: 20px;"></i>
                Partner Bank Logins & Review Status
            </h3>
            <div style="display:flex; flex-direction:column; gap:10px;">
                <?php foreach($bank_logins as $b): ?>
                    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:12px 14px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                        <div>
                            <strong style="color:#0f172a; font-size:13.5px;"><?= htmlspecialchars($b['bank_name']) ?></strong>
                            <div style="font-size:11px; color:#64748b; margin-top:2px;">
                                Ref: <code style="font-weight:bold; color:#0f172a;"><?= htmlspecialchars($b['login_ref_no'] ?: 'In Process') ?></code> 
                                <?= $b['login_date'] ? ' • Submitted: ' . date('d M Y', strtotime($b['login_date'])) : '' ?>
                            </div>
                            <?php if($b['sanctioned_amount'] > 0): ?>
                                <div style="font-size:11.5px; font-weight:800; color:#0f172a; margin-top:4px;">
                                    🎉 Sanctioned: ₹<?= number_format($b['sanctioned_amount']) ?> @ <?= htmlspecialchars($b['sanctioned_roi'] ?? 0) ?>% ROI
                                </div>
                            <?php endif; ?>
                        </div>
                        <span class="status-badge-mono">
                            <?= htmlspecialchars($b['status'] ?: 'File Submitted') ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- PENDING DOCUMENTS CARD (DARK MONOCHROME) -->
        <?php if(!empty($pending_docs)): ?>
        <div class="dark-card" id="pending-docs-banner">
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                <i data-lucide="alert-triangle" style="width: 24px; height: 24px; color:#ffffff;"></i>
                <strong style="font-size: 16.5px;">⚠️ Action Required: Please Arrange & Upload Document(s)</strong>
            </div>
            <p style="margin: 0 0 14px 0; font-size: 0.88rem; color:#cbd5e1;">Your Relationship Manager has requested the following document(s) to process your loan application:</p>
            <div style="display:flex; flex-direction:column; gap:10px;">
                <?php foreach($pending_docs as $doc): ?>
                    <div style="background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.15); border-radius:10px; padding:12px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                        <div>
                            <div style="font-size:14px; font-weight:800; color:#ffffff;">📄 <?= htmlspecialchars($doc['document_name']) ?></div>
                            <?= $doc['notes'] ? '<div style="color:#94a3b8; font-size:11.5px; margin-top:2px;">Instructions: '.htmlspecialchars($doc['notes']).'</div>' : '' ?>
                        </div>
                        <button type="button" class="btn-monochrome-outline" style="width:auto; padding:6px 14px; font-size:12px; background:#ffffff; color:#0f172a;" onclick="quickSelectDocument('<?= htmlspecialchars($doc['document_name']) ?>')">
                            📤 Upload Now
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- 📋 RECOMMENDED DOCUMENT CHECKLIST FOR CLIENT PORTAL -->
        <div class="card" id="client-checklist-card" style="box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.05); border: 1px solid #e2e8f0; border-radius: 16px; padding: 24px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:12px;">
                <div>
                    <h3 style="margin:0; font-size:1.15rem; font-weight:800; color:#0f172a; display:flex; align-items:center; gap:8px;">
                        <i data-lucide="shield-check" style="width:22px; height:22px; color:#0f172a;"></i>
                        Required Documents Checklist
                    </h3>
                    <div style="font-size:12.5px; color:#64748b; margin-top:3px; font-weight:500;">
                        Upload the required documents for <strong style="color:#0f172a;"><?= htmlspecialchars($applicant['customer_name']) ?></strong> (<?= htmlspecialchars($applicant['loan_type'] ?: 'Loan Application') ?>)
                    </div>
                </div>
                <div style="background:#f1f5f9; border:1px solid #cbd5e1; border-radius:20px; padding:5px 14px; font-size:12px; font-weight:700; color:#0f172a; display:flex; align-items:center; gap:6px;">
                    <i data-lucide="file-check-2" style="width:15px; color:#16a34a;"></i>
                    <span><?= $total_checklist_uploaded ?> of <?= $total_checklist_req ?> Completed (<?= $completion_percent ?>%)</span>
                </div>
            </div>

            <!-- Progress bar -->
            <div style="width:100%; height:8px; background:#e2e8f0; border-radius:10px; overflow:hidden; margin-bottom:20px;">
                <div style="width:<?= $completion_percent ?>%; height:100%; background:linear-gradient(90deg, #0f172a 0%, #16a34a 100%); border-radius:10px; transition:width 0.5s ease;"></div>
            </div>

            <!-- Category Groups -->
            <div style="display:flex; flex-direction:column; gap:18px;">
                <?php foreach ($checklist_categories as $cat_name => $items): ?>
                    <?php 
                        $cat_uploaded = count(array_filter($items, function($i){ return !empty($i['is_locked']); }));
                        $cat_total = count($items);
                    ?>
                    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:14px; padding:16px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
                            <div style="font-size:14px; font-weight:800; color:#0f172a; display:flex; align-items:center; gap:8px;">
                                <i data-lucide="folder-closed" style="width:18px; height:18px; color:#0f172a;"></i> <?= htmlspecialchars($cat_name) ?>
                            </div>
                            <span style="font-size:11.5px; font-weight:700; background:#ffffff; border:1px solid #cbd5e1; color:#475569; padding:3px 10px; border-radius:12px;">
                                <?= $cat_uploaded ?> of <?= $cat_total ?> Verified
                            </span>
                        </div>

                        <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap:12px;">
                            <?php foreach ($items as $item): ?>
                                <?php
                                    $m_doc = $item['matched_doc'] ?? null;
                                    $is_reupload = !empty($item['is_reupload']);
                                    $is_locked = !empty($item['is_locked']);
                                    $is_uploaded = !empty($item['is_uploaded']) && !$is_reupload;
                                    $rej_reason = $m_doc['rejection_reason'] ?? $m_doc['notes'] ?? '';
                                    $qa_stat = $m_doc['qa_status'] ?? 'Under Review';

                                    $m_file_path = $m_doc['file_path'] ?? '';
                                    $m_file_url = '';
                                    if (!empty($m_file_path)) {
                                        $m_file_url = (strpos($m_file_path, 'http') === 0 || strpos($m_file_path, 'uploads/') === 0) ? $m_file_path : 'uploads/' . ltrim($m_file_path, '/');
                                    }
                                ?>
                                <div style="background:#ffffff; border:1.5px solid <?= $is_reupload ? '#fca5a5' : ($is_locked ? '#bbf7d0' : '#e2e8f0') ?>; border-radius:12px; padding:14px; display:flex; flex-direction:column; justify-content:space-between; gap:10px; transition:all 0.2s ease; box-shadow:0 2px 4px rgba(0,0,0,0.02);">
                                    <div>
                                        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:8px;">
                                            <strong style="font-size:13.5px; color:#0f172a; font-weight:700; line-height:1.35;">
                                                <?= htmlspecialchars($item['doc_name']) ?>
                                                <?= !empty($item['is_mandatory']) ? '<span style="color:#dc2626; font-weight:800;" title="Mandatory document">*</span>' : '' ?>
                                            </strong>
                                            <?php if ($is_reupload): ?>
                                                <span style="font-size:10.5px; font-weight:700; color:#991b1b; background:#fee2e2; border:1px solid #fecaca; padding:3px 8px; border-radius:6px; white-space:nowrap; display:inline-flex; align-items:center; gap:4px;"><i data-lucide="alert-circle" style="width:12px;"></i> Action Required</span>
                                            <?php elseif ($is_locked && ($qa_stat === 'Approved' || ($m_doc['status'] ?? '') === 'Verified')): ?>
                                                <span style="font-size:10.5px; font-weight:700; color:#15803d; background:#dcfce7; border:1px solid #bbf7d0; padding:3px 8px; border-radius:6px; white-space:nowrap; display:inline-flex; align-items:center; gap:4px;"><i data-lucide="check-circle-2" style="width:12px;"></i> Verified &amp; Locked</span>
                                            <?php elseif ($is_locked): ?>
                                                <span style="font-size:10.5px; font-weight:700; color:#0369a1; background:#e0f2fe; border:1px solid #bae6fd; padding:3px 8px; border-radius:6px; white-space:nowrap; display:inline-flex; align-items:center; gap:4px;"><i data-lucide="clock" style="width:12px;"></i> Under Review</span>
                                            <?php else: ?>
                                                <span style="font-size:10.5px; font-weight:700; color:#d97706; background:#fffbe6; border:1px solid #fef08a; padding:3px 8px; border-radius:6px; white-space:nowrap; display:inline-flex; align-items:center; gap:4px;"><i data-lucide="file-plus" style="width:12px;"></i> Missing</span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div style="font-size:11.5px; color:#64748b; margin-top:4px; line-height:1.4;">
                                            <?= htmlspecialchars($item['hint'] ?? ($item['owner_type'] === 'CoApplicant' ? 'Co-Applicant Document' : 'Required for processing')) ?>
                                        </div>

                                        <?php if ($is_reupload && !empty($rej_reason)): ?>
                                            <div style="margin-top:8px; background:#fff1f2; border:1px solid #fecdd3; padding:8px 10px; border-radius:8px; font-size:11.5px; color:#9f1239; line-height:1.4;">
                                                <strong><i data-lucide="info" style="width:13px; vertical-align:-2px;"></i> Team Note:</strong> <?= htmlspecialchars($rej_reason) ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($is_locked): ?>
                                            <div style="margin-top:8px; background:#f8fafc; border:1px solid #e2e8f0; padding:6px 10px; border-radius:6px; font-size:11px; color:#475569; display:flex; align-items:center; gap:5px;">
                                                <i data-lucide="lock" style="width:12px; color:#64748b; flex-shrink:0;"></i>
                                                <span>Uploaded &amp; secured. Re-upload disabled.</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($is_reupload): ?>
                                        <div style="display:flex; gap:6px; margin-top:4px;">
                                            <?php if (!empty($m_file_url)): ?>
                                                <button type="button" onclick="openClientDocPreview('<?= htmlspecialchars(addslashes($m_file_url)) ?>', '<?= htmlspecialchars(addslashes($item['doc_name'])) ?>')" class="btn-monochrome" style="flex:1; padding:8px 8px; font-size:11.5px; font-weight:700; background:#ffffff; color:#991b1b; border:1px solid #fca5a5; border-radius:8px; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:4px;" title="View Previously Uploaded File">
                                                    <i data-lucide="eye" style="width:13px;"></i> View
                                                </button>
                                            <?php endif; ?>
                                            <button type="button" onclick="quickUploadChecklistItem('<?= htmlspecialchars(addslashes($item['doc_name'])) ?>', '<?= htmlspecialchars(addslashes($cat_name)) ?>', '<?= htmlspecialchars($item['owner_type'] ?? 'Applicant') ?>', '<?= htmlspecialchars($item['owner_id'] ?? 0) ?>')" class="btn-monochrome" style="flex:2; padding:8px 8px; font-size:11.5px; font-weight:700; background:#dc2626; color:#ffffff; border:none; border-radius:8px; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:5px; box-shadow:0 2px 4px rgba(220,38,38,0.2);">
                                                <i data-lucide="upload" style="width:13px;"></i> Re-upload
                                            </button>
                                        </div>
                                    <?php elseif ($is_locked): ?>
                                        <div style="display:flex; gap:6px; margin-top:4px;">
                                            <?php if (!empty($m_file_url)): ?>
                                                <button type="button" onclick="openClientDocPreview('<?= htmlspecialchars(addslashes($m_file_url)) ?>', '<?= htmlspecialchars(addslashes($item['doc_name'])) ?>')" class="btn-monochrome" style="flex:1; padding:8px 8px; font-size:11.5px; font-weight:700; background:#ffffff; color:#0f172a; border:1px solid #cbd5e1; border-radius:8px; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:4px;" title="View Uploaded File">
                                                    <i data-lucide="eye" style="width:13px;"></i> View File
                                                </button>
                                            <?php endif; ?>
                                            <button type="button" disabled class="btn-monochrome" style="flex:1; padding:8px 8px; font-size:11.5px; font-weight:700; background:#f1f5f9; color:#94a3b8; border:1px solid #cbd5e1; border-radius:8px; cursor:not-allowed; display:flex; align-items:center; justify-content:center; gap:4px;">
                                                <i data-lucide="lock" style="width:13px;"></i> Locked
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <button type="button" onclick="quickUploadChecklistItem('<?= htmlspecialchars(addslashes($item['doc_name'])) ?>', '<?= htmlspecialchars(addslashes($cat_name)) ?>', '<?= htmlspecialchars($item['owner_type'] ?? 'Applicant') ?>', '<?= htmlspecialchars($item['owner_id'] ?? 0) ?>')" class="btn-monochrome" style="width:100%; padding:9px 12px; font-size:12px; font-weight:700; margin-top:4px; display:flex; align-items:center; justify-content:center; gap:6px; background:#0f172a; color:#ffffff; border:none; border-radius:8px; cursor:pointer; box-shadow:0 2px 6px rgba(15,23,42,0.12);">
                                            <i data-lucide="upload" style="width:14px;"></i> Upload This Document
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- 📁 MY UPLOADED DOCUMENTS VAULT CARD -->
        <?php 
            $user_vault_docs = array_filter($all_uploaded_docs, function($d) {
                return !empty($d['file_path']);
            });
        ?>
        <?php if (!empty($user_vault_docs)): ?>
        <div class="card" id="my-uploaded-vault-card" style="box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.05); border: 1px solid #e2e8f0; border-radius: 16px; padding: 24px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:10px;">
                <div>
                    <h3 style="margin:0; font-size:1.15rem; font-weight:800; color:#0f172a; display:flex; align-items:center; gap:8px;">
                        <i data-lucide="folder-check" style="width:22px; height:22px; color:#16a34a;"></i>
                        My Uploaded Documents Vault
                    </h3>
                    <div style="font-size:12.5px; color:#64748b; margin-top:3px;">
                        Files uploaded for <?= htmlspecialchars($applicant['customer_name']) ?> (Click view to preview your uploaded document)
                    </div>
                </div>
                <span style="font-size:12px; font-weight:700; background:#dcfce7; color:#15803d; border:1px solid #bbf7d0; padding:4px 12px; border-radius:20px;">
                    <?= count($user_vault_docs) ?> File(s) Saved
                </span>
            </div>

            <div style="display:flex; flex-direction:column; gap:10px;">
                <?php foreach ($user_vault_docs as $v_doc): ?>
                    <?php
                        $v_path = $v_doc['file_path'];
                        $v_url = (strpos($v_path, 'http') === 0 || strpos($v_path, 'uploads/') === 0) ? $v_path : 'uploads/' . ltrim($v_path, '/');
                        $v_date = !empty($v_doc['uploaded_at']) ? date('d M Y, h:i A', strtotime($v_doc['uploaded_at'])) : 'Recently';
                        $v_qa = $v_doc['qa_status'] ?? 'Under Review';
                        $v_reup = intval($v_doc['reupload_requested'] ?? 0);
                    ?>
                    <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; padding:12px 14px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                        <div style="display:flex; align-items:center; gap:12px; flex:1; min-width:200px;">
                            <div style="width:38px; height:38px; background:#f1f5f9; border-radius:8px; display:flex; align-items:center; justify-content:center; color:#0f172a; flex-shrink:0;">
                                <i data-lucide="file-text" style="width:20px; height:20px;"></i>
                            </div>
                            <div>
                                <div style="font-size:13.5px; font-weight:700; color:#0f172a; display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                                    <?= htmlspecialchars($v_doc['document_name']) ?>
                                    <?php if ($v_reup == 1): ?>
                                        <span style="font-size:10px; background:#fee2e2; color:#991b1b; border:1px solid #fecaca; padding:1px 6px; border-radius:4px; font-weight:700;">Re-upload Requested</span>
                                    <?php elseif ($v_qa === 'Approved' || $v_doc['status'] === 'Verified'): ?>
                                        <span style="font-size:10px; background:#dcfce7; color:#15803d; border:1px solid #bbf7d0; padding:1px 6px; border-radius:4px; font-weight:700;">✓ Approved</span>
                                    <?php else: ?>
                                        <span style="font-size:10px; background:#e0f2fe; color:#0369a1; border:1px solid #bae6fd; padding:1px 6px; border-radius:4px; font-weight:700;">⏳ Under Review</span>
                                    <?php endif; ?>
                                </div>
                                <div style="font-size:11px; color:#64748b; margin-top:2px;">
                                    Category: <?= htmlspecialchars($v_doc['category'] ?? 'KYC') ?> | Uploaded: <?= $v_date ?>
                                </div>
                            </div>
                        </div>
                        <button type="button" onclick="openClientDocPreview('<?= htmlspecialchars(addslashes($v_url)) ?>', '<?= htmlspecialchars(addslashes($v_doc['document_name'])) ?>')" class="btn-monochrome" style="padding:7px 14px; font-size:12px; font-weight:700; background:#0f172a; color:#ffffff; border:none; border-radius:8px; cursor:pointer; display:flex; align-items:center; gap:5px;">
                            <i data-lucide="eye" style="width:14px;"></i> View File
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- 📲 2-WAY CLIENT DOCUMENT UPLOAD CARD (CUSTOM MONOCHROME DROPDOWN) -->
        <div class="card" id="client-upload-desk-card">
            <h3 style="margin:0 0 8px 0; font-size:1.1rem; display:flex; align-items:center; gap:8px; color:#0f172a;">
                <i data-lucide="upload-cloud" style="width: 22px;"></i>
                Mobile Document Upload Desk
            </h3>
            <p style="color:var(--gray); font-size:0.88rem; margin-bottom: 16px;">
                Select document type and upload your file or take a photo directly from your mobile device.
            </p>

            <form id="clientUploadForm" onsubmit="submitClientDocument(event)">
                
                <!-- CUSTOM MONOCHROME SELECT DROPDOWN -->
                <div class="custom-select-wrapper" id="customSelectContainer">
                    <div class="custom-select-trigger" onclick="toggleCustomSelect(event)">
                        <span id="custom-select-label">-- Select Document Type --</span>
                        <i data-lucide="chevron-down" style="width:18px; height:18px; transition:transform 0.2s ease;" id="custom-select-arrow"></i>
                    </div>
                    <div class="custom-select-options" id="customSelectDropdown">
                        <div class="custom-option" data-value="" onclick="selectCustomOption('', '-- Select Document Type --', 'Basic KYC', 'Applicant', 0)">-- Select Document Type --</div>
                        
                        <?php foreach($suggestions as $sug): ?>
                            <?php $is_up = !empty($sug['is_uploaded']); ?>
                            <div class="custom-option <?= $is_up ? '' : 'pending-option' ?>" 
                                 data-value="<?= htmlspecialchars($sug['doc_name']) ?>" 
                                 onclick="selectCustomOption('<?= htmlspecialchars(addslashes($sug['doc_name'])) ?>', '<?= htmlspecialchars(addslashes(($is_up ? '✓ ' : '📌 ') . $sug['doc_name'] . ($sug['owner_type'] === 'CoApplicant' ? ' [Co-App]' : ''))) ?>', '<?= htmlspecialchars(addslashes($sug['category'] ?? 'Basic KYC')) ?>', '<?= htmlspecialchars($sug['owner_type'] ?? 'Applicant') ?>', '<?= htmlspecialchars($sug['owner_id'] ?? 0) ?>')">
                                <?= $is_up ? '✓' : '📌' ?> <?= htmlspecialchars($sug['doc_name']) ?> 
                                <?php if($sug['owner_type'] === 'CoApplicant'): ?>
                                    <span style="font-size:10.5px; opacity:0.8; background:#e2e8f0; color:#334155; padding:1px 5px; border-radius:4px;">[Co-App]</span>
                                <?php endif; ?>
                                <?php if(!empty($sug['is_mandatory'])): ?>
                                    <span style="font-size:10.5px; color:#dc2626;">[Required]</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                        <div class="custom-option" data-value="Other Document" onclick="selectCustomOption('Other Document', 'Other Document', 'Other', 'Applicant', 0)">Other Document</div>
                    </div>
                    <input type="hidden" id="doc_select_name" value="">
                    <input type="hidden" id="doc_select_category" value="Basic KYC">
                    <input type="hidden" id="doc_select_owner_type" value="Applicant">
                    <input type="hidden" id="doc_select_owner_id" value="0">
                </div>

                <div class="upload-box" id="uploadDropZone" onclick="document.getElementById('client_file_input').click()">
                    <i data-lucide="file-up" style="width: 38px; height: 38px; color:#0f172a;"></i>
                    <div style="font-size: 13.5px; font-weight: 800; color: #0f172a; margin-top: 8px;" id="file-name-preview">Select File or Take Photo</div>
                    <div style="font-size: 11.5px; color: #64748b; margin-top: 4px;">Allowed: PDF, JPG, PNG, DOC (Max 15MB)</div>
                    <input type="file" id="client_file_input" class="upload-input" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" onchange="onFileSelected(this)">
                    <span class="upload-label" onclick="event.stopPropagation(); document.getElementById('client_file_input').click();">📷 Choose File / Take Photo</span>
                </div>

                <button type="submit" class="btn-monochrome" style="margin-top: 16px;" id="btn-submit-doc">
                    <i data-lucide="upload" style="width: 18px;"></i>
                    Upload Document Now
                </button>
            </form>
        </div>

        <!-- UPLOADED HISTORY -->
        <?php if(!empty($uploaded_docs)): ?>
        <div class="card">
            <h3 style="margin:0 0 12px 0; font-size:1.05rem; color:#0f172a;">Uploaded Documents History</h3>
            <div>
                <?php foreach($uploaded_docs as $ud): ?>
                    <div class="uploaded-item">
                        <div>
                            <strong style="color:#0f172a; font-weight:800;"><?= htmlspecialchars($ud['document_name']) ?></strong>
                            <div style="font-size:11px; color:#64748b; margin-top:2px;">Uploaded on <?= date('d M Y, h:i A', strtotime($ud['uploaded_at'])) ?></div>
                        </div>
                        <span class="status-badge-mono">
                            ✓ <?= htmlspecialchars($ud['status']) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- 💬 CLIENT QUERY & SUPPORT TICKET DESK -->
        <div class="card">
            <h3 style="margin:0 0 8px 0; font-size:1.1rem; display:flex; align-items:center; gap:8px; color:#0f172a;">
                <i data-lucide="help-circle" style="width: 22px;"></i>
                Ask Your Relationship Manager / Raise Query
            </h3>
            <p style="color:var(--gray); font-size:0.88rem; margin-bottom: 16px;">
                Have a question regarding your application status, documents, or bank review? Submit your query below:
            </p>

            <form id="clientQueryForm" onsubmit="submitClientQuery(event)">
                <input type="text" id="query_subject" placeholder="Query Subject (e.g., Bank Verification, Disbursal Date)" class="ctrl-input-mono" required>
                <textarea id="query_message" rows="3" placeholder="Write your question or request in detail..." class="ctrl-input-mono" required></textarea>
                <button type="submit" class="btn-monochrome" id="btn-submit-query">
                    <i data-lucide="send" style="width: 18px;"></i>
                    Submit Question to RM
                </button>
            </form>

            <?php if(!empty($client_queries)): ?>
                <div style="margin-top:20px; border-top:1px solid #f1f5f9; padding-top:16px;">
                    <div style="font-size:12.5px; font-weight:800; color:#0f172a; margin-bottom:10px;">Your Submitted Questions</div>
                    <?php foreach($client_queries as $cq): ?>
                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:12px; margin-bottom:10px;">
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <strong style="font-size:13px; color:#0f172a;"><?= htmlspecialchars($cq['query_subject']) ?></strong>
                                <span class="status-badge-mono"><?= htmlspecialchars($cq['status']) ?></span>
                            </div>
                            <div style="font-size:12px; color:#334155; margin-top:4px;"><?= nl2br(htmlspecialchars($cq['query_message'])) ?></div>
                            <div style="font-size:10.5px; color:#64748b; margin-top:4px;">Submitted: <?= date('d M Y, h:i A', strtotime($cq['created_at'])) ?></div>
                            
                            <?php if(!empty($cq['admin_reply'])): ?>
                                <div style="background:#0f172a; color:#ffffff; border-radius:8px; padding:8px 12px; margin-top:8px; font-size:12px;">
                                    <strong>RM Response:</strong> <?= htmlspecialchars($cq['admin_reply']) ?>
                                    <div style="font-size:10px; color:#94a3b8; margin-top:2px;"><?= date('d M Y, h:i A', strtotime($cq['replied_at'])) ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- 🎁 ADVANCED REFER & EARN PORTAL FOR EXISTING CUSTOMER -->
        <div class="card" id="refer-and-earn-card" style="border: 2px solid #0f172a; border-radius: 16px; padding: 24px; position: relative; overflow: hidden; background: #ffffff; margin-bottom: 20px;">
            <!-- Top Banner -->
            <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:14px; margin-bottom:18px;">
                <div>
                    <div style="display:inline-flex; align-items:center; gap:6px; background:#0f172a; color:#ffffff; font-size:11px; font-weight:800; padding:4px 10px; border-radius:20px; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:8px;">
                        <i data-lucide="gift" style="width:14px; height:14px;"></i> Refer & Earn Program
                    </div>
                    <h3 style="margin:0; font-size:1.25rem; font-weight:800; color:#0f172a;">
                        Refer Friends & Family, Earn ₹2,000 Reward!
                    </h3>
                    <p style="margin:4px 0 0; color:#64748b; font-size:0.88rem;">
                        Help your friends get the best loan rates. When their loan is disbursed, you get <strong>₹2,000 cash reward</strong> directly in your bank account!
                    </p>
                </div>
                <!-- Earnings Mini Badge -->
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:12px 18px; text-align:center;">
                    <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase;">Your Referral Earnings</div>
                    <div style="font-size:20px; font-weight:900; color:#16a34a; margin-top:2px;">₹<?= number_format($total_reward_earned) ?></div>
                    <div style="font-size:11px; color:#475569; font-weight:600;"><?= $total_customer_referrals ?> Friends Referred</div>
                </div>
            </div>

            <!-- Mode 1: Instant WhatsApp & Link Sharing -->
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:16px; margin-bottom:20px;">
                <label style="display:block; font-size:12px; font-weight:800; color:#0f172a; margin-bottom:8px; text-transform:uppercase;">
                    Your Unique Referral Link:
                </label>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <input type="text" readonly value="<?= htmlspecialchars($referral_url) ?>" id="customer_ref_link" style="flex:1; min-width:240px; background:#ffffff; border:1px solid #cbd5e1; border-radius:8px; padding:10px 14px; font-size:13px; font-weight:600; color:#0f172a;" onclick="this.select()">
                    <button type="button" class="btn-monochrome" onclick="copyReferralLink()" style="padding:10px 16px; display:inline-flex; align-items:center; gap:6px;">
                        <i data-lucide="copy" style="width:16px; height:16px;"></i> <span id="copy_btn_text">Copy Link</span>
                    </button>
                    <a href="<?= $whatsapp_share_url ?>" target="_blank" class="btn-monochrome" style="background:#25D366; border-color:#25D366; color:#ffffff; padding:10px 18px; display:inline-flex; align-items:center; gap:6px; text-decoration:none; font-weight:700;">
                        <i data-lucide="message-circle" style="width:16px; height:16px;"></i> Share on WhatsApp
                    </a>
                </div>
            </div>

            <!-- Mode 2: Quick Direct Friend Referral Form -->
            <div style="border-top:1px dashed #e2e8f0; padding-top:18px; margin-bottom:18px;">
                <h4 style="margin:0 0 10px; font-size:14px; font-weight:800; color:#0f172a; display:flex; align-items:center; gap:6px;">
                    <i data-lucide="user-plus" style="width:16px; height:16px;"></i> Or Directly Refer a Friend Online:
                </h4>
                <form id="quickReferForm" onsubmit="submitQuickReferral(event)" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)) 120px; gap:10px; align-items:flex-end;">
                    <div>
                        <label style="display:block; font-size:11px; font-weight:700; color:#475569; margin-bottom:4px;">Friend's Name *</label>
                        <input type="text" id="ref_friend_name" required placeholder="e.g. Rahul Sharma" style="width:100%; box-sizing:border-box; background:#ffffff; border:1px solid #cbd5e1; border-radius:8px; padding:8px 12px; font-size:13px;">
                    </div>
                    <div>
                        <label style="display:block; font-size:11px; font-weight:700; color:#475569; margin-bottom:4px;">10-Digit Mobile *</label>
                        <input type="tel" id="ref_friend_mobile" required maxlength="10" placeholder="9876543210" style="width:100%; box-sizing:border-box; background:#ffffff; border:1px solid #cbd5e1; border-radius:8px; padding:8px 12px; font-size:13px;" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0,10)">
                    </div>
                    <div>
                        <label style="display:block; font-size:11px; font-weight:700; color:#475569; margin-bottom:4px;">Loan Type</label>
                        <select id="ref_loan_type" style="width:100%; box-sizing:border-box; background:#ffffff; border:1px solid #cbd5e1; border-radius:8px; padding:8px 12px; font-size:13px;">
                            <option value="Home Loan">Home Loan</option>
                            <option value="Personal Loan">Personal Loan</option>
                            <option value="Business Loan">Business Loan</option>
                            <option value="Loan Against Property">Loan Against Property</option>
                            <option value="Balance Transfer">Balance Transfer</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-monochrome" id="btn-submit-ref" style="height:37px; padding:0 14px; font-size:12.5px; font-weight:700; display:inline-flex; align-items:center; justify-content:center; gap:6px;">
                        <i data-lucide="send" style="width:14px; height:14px;"></i> Submit
                    </button>
                </form>
            </div>

            <!-- Mode 3: Live Referred Friends Status List -->
            <div style="border-top:1px solid #f1f5f9; padding-top:16px;">
                <div style="font-size:13px; font-weight:800; color:#0f172a; margin-bottom:10px; display:flex; justify-content:space-between; align-items:center;">
                    <span>📋 Friends Referred by You</span>
                    <span style="font-size:11px; font-weight:600; color:#64748b;">Live Status Tracking</span>
                </div>
                <div id="referrals_list_container">
                    <?php if(empty($customer_referrals)): ?>
                        <div style="text-align:center; padding:16px; background:#f8fafc; border-radius:10px; color:#64748b; font-size:12.5px;" id="empty_referrals_msg">
                            You haven't referred any friends yet. Share your link or submit their details above to start earning rewards!
                        </div>
                    <?php else: ?>
                        <div style="display:flex; flex-direction:column; gap:8px;">
                            <?php foreach($customer_referrals as $cr): ?>
                                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:10px 14px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                                    <div>
                                        <strong style="font-size:13px; color:#0f172a;"><?= htmlspecialchars($cr['friend_name']) ?></strong>
                                        <div style="font-size:11px; color:#64748b;">
                                            <?= htmlspecialchars($cr['loan_requirement'] ?: 'Loan') ?> • Referred on <?= date('d M Y', strtotime($cr['created_at'])) ?>
                                        </div>
                                    </div>
                                    <div style="text-align:right;">
                                        <span class="status-badge-mono" style="font-size:11px;">
                                            <?= htmlspecialchars($cr['status'] ?: 'Lead Received') ?>
                                        </span>
                                        <div style="font-size:11px; font-weight:800; color:<?= in_array(strtolower($cr['status'] ?? ''), ['disbursed', 'completed']) ? '#16a34a' : '#64748b' ?>; margin-top:2px;">
                                            Reward: ₹<?= number_format($cr['reward_amount'] ?? 2000) ?> (<?= htmlspecialchars($cr['reward_status'] ?? 'Pending') ?>)
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- RELATIONSHIP MANAGER SUPPORT DESK (MONOCHROME) -->
        <div class="card">
            <h3 style="margin:0 0 8px 0; font-size:1.1rem; color:#0f172a;">Relationship Manager Direct Support</h3>
            <p style="color:var(--gray); font-size:0.88rem; margin-bottom: 20px;">
                Assigned Relationship Manager: <strong><?= htmlspecialchars($applicant['rm_name'] ?? 'BFS Support Desk') ?></strong>
            </p>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                <?php if(!empty($applicant['rm_mobile'])): ?>
                    <a href="tel:<?= htmlspecialchars($applicant['rm_mobile']) ?>" class="btn-monochrome-outline">
                        <i data-lucide="phone" style="width: 18px;"></i>
                        Call RM
                    </a>
                    <a href="https://wa.me/91<?= preg_replace('/[^0-9]/', '', $applicant['rm_mobile']) ?>?text=<?= urlencode("Hello " . ($applicant['rm_name'] ?? 'RM') . ", I am tracking my loan application #" . $applicant['loan_id'] . " on BFS Circle.") ?>" target="_blank" class="btn-monochrome">
                        <i data-lucide="message-square" style="width: 18px;"></i>
                        WhatsApp RM
                    </a>
                <?php else: ?>
                    <a href="mailto:info@bfscircle.com" class="btn-monochrome">
                        <i data-lucide="mail" style="width: 18px;"></i>
                        Email Support Desk
                    </a>
                <?php endif; ?>
                <button type="button" class="btn-monochrome-outline" style="grid-column: 1 / -1; margin-top:4px;" onclick="requestCallbackModal()">
                    <i data-lucide="phone-incoming" style="width: 18px;"></i>
                    Request Immediate Callback
                </button>
            </div>
        </div>
        
    </div>

    <!-- JS LOGIC -->
    <script>
        lucide.createIcons();

        // Custom Dropdown Logic
        function toggleCustomSelect(e) {
            e.stopPropagation();
            const dropdown = document.getElementById('customSelectDropdown');
            const arrow = document.getElementById('custom-select-arrow');
            const isOpen = dropdown.classList.contains('open');
            
            if (isOpen) {
                dropdown.classList.remove('open');
                arrow.style.transform = 'rotate(0deg)';
            } else {
                dropdown.classList.add('open');
                arrow.style.transform = 'rotate(180deg)';
            }
        }

        function selectCustomOption(val, labelText, category = 'Basic KYC', ownerType = 'Applicant', ownerId = 0) {
            document.getElementById('doc_select_name').value = val;
            if (document.getElementById('doc_select_category')) document.getElementById('doc_select_category').value = category || 'Basic KYC';
            if (document.getElementById('doc_select_owner_type')) document.getElementById('doc_select_owner_type').value = ownerType || 'Applicant';
            if (document.getElementById('doc_select_owner_id')) document.getElementById('doc_select_owner_id').value = ownerId || 0;
            document.getElementById('custom-select-label').innerText = labelText;
            
            document.querySelectorAll('.custom-option').forEach(el => {
                if (el.getAttribute('data-value') === val) {
                    el.classList.add('selected');
                } else {
                    el.classList.remove('selected');
                }
            });

            const dropdown = document.getElementById('customSelectDropdown');
            const arrow = document.getElementById('custom-select-arrow');
            if (dropdown) dropdown.classList.remove('open');
            if (arrow) arrow.style.transform = 'rotate(0deg)';
        }

        document.addEventListener('click', () => {
            const dropdown = document.getElementById('customSelectDropdown');
            const arrow = document.getElementById('custom-select-arrow');
            if (dropdown && dropdown.classList.contains('open')) {
                dropdown.classList.remove('open');
                arrow.style.transform = 'rotate(0deg)';
            }
        });

        function quickSelectDocument(docName) {
            selectCustomOption(docName, '📌 ' + docName + ' (Required)', 'Basic KYC', 'Applicant', 0);
            const formEl = document.getElementById('clientUploadForm');
            if (formEl) formEl.scrollIntoView({ behavior: 'smooth' });
        }

        function quickUploadChecklistItem(docName, category, ownerType, ownerId) {
            selectCustomOption(docName, '📌 ' + docName, category, ownerType, ownerId);
            const desk = document.getElementById('client-upload-desk-card');
            if (desk) {
                desk.scrollIntoView({ behavior: 'smooth', block: 'center' });
                desk.style.border = '2px solid #0284c7';
                setTimeout(() => { desk.style.border = '1px solid var(--gray-light)'; }, 2500);
            }
            const fileInput = document.getElementById('client_file_input');
            if (fileInput) fileInput.click();
        }

        // Drag and drop & File Preview
        const dropZone = document.getElementById('uploadDropZone');
        if (dropZone) {
            ['dragenter', 'dragover'].forEach(eventName => {
                dropZone.addEventListener(eventName, (e) => {
                    e.preventDefault(); e.stopPropagation();
                    dropZone.classList.add('dragover');
                }, false);
            });
            ['dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, (e) => {
                    e.preventDefault(); e.stopPropagation();
                    dropZone.classList.remove('dragover');
                }, false);
            });
            dropZone.addEventListener('drop', (e) => {
                const dt = e.dataTransfer;
                const files = dt.files;
                if (files && files.length > 0) {
                    document.getElementById('client_file_input').files = files;
                    onFileSelected(document.getElementById('client_file_input'));
                }
            });
        }

        function onFileSelected(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
                document.getElementById('file-name-preview').innerHTML = '📄 ' + file.name + ' <span style="font-weight:normal; font-size:11px; color:#64748b;">(' + sizeMB + ' MB)</span>';
            }
        }

        function submitClientDocument(e) {
            e.preventDefault();
            const docName = document.getElementById('doc_select_name').value;
            const fileInput = document.getElementById('client_file_input');
            const submitBtn = document.getElementById('btn-submit-doc');

            if (!docName) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Select Document Type',
                    text: 'Please choose the document type from the dropdown.',
                    confirmButtonColor: '#0f172a'
                });
                return;
            }
            if (!fileInput.files || !fileInput.files[0]) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Attach Document File',
                    text: 'Please select a photo or file from your device.',
                    confirmButtonColor: '#0f172a'
                });
                return;
            }

            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Uploading Document...';

            let fd = new FormData();
            fd.append('token', '<?= $token ?>');
            fd.append('document_name', docName);
            fd.append('category', document.getElementById('doc_select_category')?.value || 'Basic KYC');
            fd.append('owner_type', document.getElementById('doc_select_owner_type')?.value || 'Applicant');
            fd.append('owner_id', document.getElementById('doc_select_owner_id')?.value || '0');
            fd.append('document_file', fileInput.files[0]);

            fetch('api.php?api=client_upload_document', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i data-lucide="upload"></i> Upload Document Now';
                lucide.createIcons();

                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '🎉 Thank You!',
                        html: `
                            <div style="text-align:center; padding:10px;">
                                <div style="font-size:16px; font-weight:800; color:#0f172a; margin-bottom:8px;">Document Received Successfully!</div>
                                <p style="font-size:13px; color:#475569; margin:0 0 14px 0;">We have received your document (<strong>${data.doc_name || docName}</strong>). Your Relationship Manager (<strong><?= htmlspecialchars($applicant['rm_name'] ?? 'Support Team') ?></strong>) has been notified and will verify it shortly.</p>
                                <div style="background:#f1f5f9; border-radius:8px; padding:10px; font-size:12px; font-weight:700; color:#0f172a;">Application Ref #<?= htmlspecialchars($applicant['loan_id']) ?> • Verified Client Portal</div>
                            </div>
                        `,
                        confirmButtonText: 'Great, Thank You!',
                        confirmButtonColor: '#0f172a'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Upload Failed',
                        text: data.error || 'Server error',
                        confirmButtonColor: '#0f172a'
                    });
                }
            })
            .catch(err => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i data-lucide="upload"></i> Upload Document Now';
                lucide.createIcons();
                Swal.fire({
                    icon: 'error',
                    title: 'Connection Error',
                    text: 'Network issue while uploading. Please retry.',
                    confirmButtonColor: '#0f172a'
                });
            });
        }

        function submitClientQuery(e) {
            e.preventDefault();
            const subject = document.getElementById('query_subject').value.trim();
            const message = document.getElementById('query_message').value.trim();
            const submitBtn = document.getElementById('btn-submit-query');

            if (!message) return;

            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Submitting Question...';

            let fd = new FormData();
            fd.append('token', '<?= $token ?>');
            fd.append('query_subject', subject);
            fd.append('query_message', message);

            fetch('api.php?api=client_submit_query', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i data-lucide="send"></i> Submit Question to RM';
                lucide.createIcons();

                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Question Submitted!',
                        text: data.message,
                        confirmButtonColor: '#0f172a'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Submission Failed',
                        text: data.error || 'Server error',
                        confirmButtonColor: '#0f172a'
                    });
                }
            })
            .catch(err => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i data-lucide="send"></i> Submit Question to RM';
                lucide.createIcons();
                Swal.fire({
                    icon: 'error',
                    title: 'Connection Error',
                    text: 'Network issue while sending question.',
                    confirmButtonColor: '#0f172a'
                });
            });
        }

        // ==========================================
        // 🎁 CLIENT REFER & EARN JAVASCRIPT HANDLERS
        // ==========================================
        function copyReferralLink() {
            const input = document.getElementById('customer_ref_link');
            if (!input) return;
            const linkText = input.value;
            
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(linkText).then(() => {
                    showCopySuccess();
                }).catch(() => {
                    fallbackCopy(input);
                });
            } else {
                fallbackCopy(input);
            }
        }

        function fallbackCopy(input) {
            input.select();
            input.setSelectionRange(0, 99999);
            try {
                document.execCommand('copy');
                showCopySuccess();
            } catch (err) {
                Swal.fire({
                    icon: 'info',
                    title: 'Referral Link',
                    text: 'Please select and copy this link: ' + input.value,
                    confirmButtonColor: '#0f172a'
                });
            }
        }

        function showCopySuccess() {
            const btnText = document.getElementById('copy_btn_text');
            if (btnText) {
                const orig = btnText.innerText;
                btnText.innerText = 'Copied! ✓';
                setTimeout(() => { btnText.innerText = orig; }, 2500);
            }
            Swal.fire({
                icon: 'success',
                title: 'Link Copied! 📋',
                text: 'Your referral link has been copied to your clipboard. Share it with friends and family!',
                timer: 2000,
                showConfirmButton: false
            });
        }

        function submitQuickReferral(e) {
            e.preventDefault();
            const friendName = document.getElementById('ref_friend_name').value.trim();
            const friendMobile = document.getElementById('ref_friend_mobile').value.trim();
            const loanType = document.getElementById('ref_loan_type').value;
            const submitBtn = document.getElementById('btn-submit-ref');

            if (!friendName || !friendMobile) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Missing Details',
                    text: 'Please provide friend name and mobile number.',
                    confirmButtonColor: '#0f172a'
                });
                return;
            }

            if (!/^[0-9]{10}$/.test(friendMobile)) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Invalid Mobile Number',
                    text: 'Please enter a valid 10-digit mobile number.',
                    confirmButtonColor: '#0f172a'
                });
                return;
            }

            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Submitting...';

            let fd = new FormData();
            fd.append('token', '<?= $token ?>');
            fd.append('friend_name', friendName);
            fd.append('friend_mobile', friendMobile);
            fd.append('loan_type', loanType);

            fetch('api.php?api=client_refer_friend', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i data-lucide="send" style="width:14px; height:14px;"></i> Submit';
                if (window.lucide) lucide.createIcons();

                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '🎉 Referral Submitted!',
                        text: data.message || 'Your friend has been successfully registered. You will receive ₹2,000 reward upon loan disbursal!',
                        confirmButtonColor: '#0f172a'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Submission Failed',
                        text: data.error || 'Unable to submit referral',
                        confirmButtonColor: '#0f172a'
                    });
                }
            })
            .catch(err => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i data-lucide="send" style="width:14px; height:14px;"></i> Submit';
                if (window.lucide) lucide.createIcons();
                Swal.fire({
                    icon: 'error',
                    title: 'Connection Error',
                    text: 'Network issue while submitting referral. Please try again.',
                    confirmButtonColor: '#0f172a'
                });
            });
        }

        function acceptSanctionTerms() {
            Swal.fire({
                title: 'Accept Sanction Terms?',
                text: 'By accepting, you confirm your approval of the sanctioned amount, ROI, and tenure to proceed with loan disbursal.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, Accept & Proceed',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#0f172a'
            }).then((res) => {
                if(res.isConfirmed) {
                    let fd = new FormData();
                    fd.append('token', '<?= $token ?>');
                    fetch('api.php?api=client_accept_sanction', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(data => {
                        if(data.success) {
                            Swal.fire({ icon: 'success', title: 'Sanction Accepted!', text: data.message, confirmButtonColor: '#0f172a' }).then(() => location.reload());
                        } else {
                            Swal.fire({ icon: 'error', title: 'Error', text: data.error || 'Failed to accept sanction terms', confirmButtonColor: '#0f172a' });
                        }
                    });
                }
            });
        }

        function confirmVerificationVisit(scheduleId, status) {
            let fd = new FormData();
            fd.append('token', '<?= $token ?>');
            fd.append('schedule_id', scheduleId);
            fd.append('status', status);

            fetch('api.php?api=client_confirm_verification', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if(data.success) {
                    Swal.fire({ icon: 'success', title: 'Visit Status Updated', text: data.message, confirmButtonColor: '#0f172a' }).then(() => location.reload());
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: data.error || 'Failed to update visit status', confirmButtonColor: '#0f172a' });
                }
            });
        }

        function requestCallbackModal() {
            Swal.fire({
                title: '📞 Request Call Back',
                html: `
                    <div style="text-align:left; font-size:13px; color:#334155;">
                        <label style="font-weight:700; display:block; margin-bottom:4px;">Preferred Time:</label>
                        <select id="swal_pref_time" class="ctrl-input-mono" style="margin-bottom:10px; width:100%;">
                            <option value="As soon as possible">As soon as possible (Immediate)</option>
                            <option value="Morning (10 AM - 1 PM)">Morning (10 AM - 1 PM)</option>
                            <option value="Afternoon (1 PM - 5 PM)">Afternoon (1 PM - 5 PM)</option>
                            <option value="Evening (5 PM - 8 PM)">Evening (5 PM - 8 PM)</option>
                        </select>
                        <label style="font-weight:700; display:block; margin-bottom:4px;">Notes / Topic (Optional):</label>
                        <textarea id="swal_cb_notes" class="ctrl-input-mono" style="width:100%;" rows="2" placeholder="What would you like to discuss?"></textarea>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Submit Callback Request',
                confirmButtonColor: '#0f172a',
                preConfirm: () => {
                    const prefTime = document.getElementById('swal_pref_time').value;
                    const cbNotes = document.getElementById('swal_cb_notes').value;
                    return { prefTime, cbNotes };
                }
            }).then((res) => {
                if(res.isConfirmed && res.value) {
                    let fd = new FormData();
                    fd.append('token', '<?= $token ?>');
                    fd.append('preferred_time', res.value.prefTime);
                    fd.append('notes', res.value.cbNotes);

                    fetch('api.php?api=client_request_callback', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(data => {
                        if(data.success) {
                            Swal.fire({ icon: 'success', title: 'Request Received!', text: data.message, confirmButtonColor: '#0f172a' });
                        } else {
                            Swal.fire({ icon: 'error', title: 'Error', text: data.error || 'Failed to submit callback request', confirmButtonColor: '#0f172a' });
                        }
                    });
                }
            });
        }

        let currentClientPreviewAngle = 0;

        function openClientDocPreview(fileUrl, docName) {
            const modal = document.getElementById('track-doc-preview-modal');
            const title = document.getElementById('track-preview-title');
            const body = document.getElementById('track-preview-body');
            const downloadBtn = document.getElementById('track-preview-download-btn');
            const rotateBtn = document.getElementById('track-preview-rotate-btn');

            if (!modal || !body) return;

            currentClientPreviewAngle = 0;
            if (title) title.innerText = docName || 'Document Preview';
            if (downloadBtn) downloadBtn.href = fileUrl;

            const cleanPath = (fileUrl || '').split('?')[0];
            const ext = cleanPath.split('.').pop().toLowerCase();

            if (ext === 'pdf') {
                if (rotateBtn) rotateBtn.style.display = 'none';
                body.innerHTML = `
                    <iframe src="${fileUrl}#toolbar=1" style="width:100%; height:75vh; border:none; border-radius:10px; background:#fff;"></iframe>
                `;
            } else if (['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'].includes(ext)) {
                if (rotateBtn) rotateBtn.style.display = 'inline-flex';
                body.innerHTML = `
                    <img id="track-preview-img-el" src="${fileUrl}" style="max-width:100%; max-height:75vh; object-fit:contain; border-radius:8px; transition:transform 0.2s ease; box-shadow:0 10px 30px rgba(0,0,0,0.5);">
                `;
            } else {
                if (rotateBtn) rotateBtn.style.display = 'none';
                body.innerHTML = `
                    <div style="text-align:center; color:#ffffff; padding:40px;">
                        <i data-lucide="file-text" style="width:48px; height:48px; color:#38bdf8; margin-bottom:12px;"></i>
                        <div style="font-size:16px; font-weight:700; margin-bottom:6px;">${docName}</div>
                        <div style="font-size:12px; color:#94a3b8; margin-bottom:16px;">Document Format: ${ext.toUpperCase()}</div>
                        <a href="${fileUrl}" target="_blank" download class="btn" style="background:#38bdf8; color:#0f172a; border:none; padding:10px 20px; border-radius:8px; font-size:13px; font-weight:700; text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
                            <i data-lucide="download" style="width:16px;"></i> Open / Download File
                        </a>
                    </div>
                `;
            }

            modal.style.display = 'flex';
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }

        function closeClientDocPreview() {
            const modal = document.getElementById('track-doc-preview-modal');
            if (modal) modal.style.display = 'none';
        }

        function rotateClientPreviewImage() {
            const img = document.getElementById('track-preview-img-el');
            if (!img) return;
            currentClientPreviewAngle = (currentClientPreviewAngle + 90) % 360;
            img.style.transform = `rotate(${currentClientPreviewAngle}deg)`;
        }
    </script>

    <!-- 📱 MOBILE-FRIENDLY CLIENT DOCUMENT PREVIEW LIGHTBOX MODAL -->
    <div id="track-doc-preview-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.92); z-index:999999; justify-content:center; align-items:center; backdrop-filter:blur(8px); padding:10px; box-sizing:border-box;">
        <div style="background:#ffffff; border-radius:16px; width:100%; max-width:700px; max-height:94vh; display:flex; flex-direction:column; overflow:hidden; box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);">
            <!-- Modal Header -->
            <div style="padding:14px 18px; background:#0f172a; color:#ffffff; display:flex; justify-content:space-between; align-items:center;">
                <div style="display:flex; align-items:center; gap:10px; overflow:hidden;">
                    <i data-lucide="file-text" style="width:20px; height:20px; color:#38bdf8; flex-shrink:0;"></i>
                    <div style="font-weight:700; font-size:14px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" id="track-preview-title">Document Preview</div>
                </div>
                <button type="button" onclick="closeClientDocPreview()" style="background:rgba(255,255,255,0.15); border:none; color:#ffffff; cursor:pointer; width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:20px; line-height:1;">&times;</button>
            </div>
            
            <!-- Modal Body -->
            <div id="track-preview-body" style="flex:1; padding:12px; background:#0f172a; display:flex; justify-content:center; align-items:center; min-height:300px; max-height:calc(94vh - 120px); overflow:auto;">
                <!-- Content loaded dynamically -->
            </div>

            <!-- Modal Footer -->
            <div style="padding:12px 18px; background:#ffffff; border-top:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                <span style="font-size:11px; color:#64748b; font-weight:600;"><i data-lucide="shield-check" style="width:13px; vertical-align:-2px; color:#16a34a;"></i> BFS Secure Document Viewer</span>
                <div style="display:flex; gap:8px;">
                    <button type="button" id="track-preview-rotate-btn" onclick="rotateClientPreviewImage()" class="btn" style="background:#f1f5f9; color:#0f172a; border:1px solid #cbd5e1; padding:7px 12px; border-radius:8px; font-weight:700; font-size:12px; display:inline-flex; align-items:center; gap:5px; cursor:pointer;">
                        <i data-lucide="rotate-cw" style="width:13px;"></i> Rotate
                    </button>
                    <a id="track-preview-download-btn" href="#" target="_blank" download class="btn" style="background:#0f172a; color:#ffffff; border:none; padding:7px 14px; border-radius:8px; font-weight:700; font-size:12px; display:inline-flex; align-items:center; gap:5px; text-decoration:none;">
                        <i data-lucide="download" style="width:13px;"></i> Download / Open
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
