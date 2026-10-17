<?php
require_once 'config.php';
require_role(['Admin', 'RM']);

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header("Location: applicants_list.php");
    exit;
}

$stmt = $db->prepare("SELECT * FROM applicants WHERE id = ?");
$stmt->execute([$id]);
$applicant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$applicant) {
    die("Applicant not found.");
}

// Get co-applicants
$co_stmt = $db->prepare("SELECT id, full_name, relationship, is_financial, monthly_income FROM co_applicants WHERE applicant_id = ?");
$co_stmt->execute([$id]);
$co_applicants = $co_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get verified document count
$verified_doc_count = 0;
try {
    $verified_doc_count = (int)$db->query("SELECT COUNT(*) FROM applicant_documents WHERE applicant_id = $id AND (status = 'Verified' OR qa_status = 'Approved')")->fetchColumn();
} catch (Exception $e) {}

// Get document count for the modal
$doc_stmt = $db->prepare("SELECT COUNT(*) FROM applicant_documents WHERE applicant_id = ?");
$doc_stmt->execute([$id]);
$doc_count = (int)$doc_stmt->fetchColumn();

// Get bank assignments with all advanced fields
$bank_stmt = $db->prepare("SELECT * FROM applicant_bank_assignments WHERE applicant_id = ? ORDER BY id DESC");
$bank_stmt->execute([$id]);
$bank_assignments = $bank_stmt->fetchAll(PDO::FETCH_ASSOC);

$has_approved_bank = false;
$has_accepted_bank = false;
$winning_assignment = null;
foreach ($bank_assignments as $ba) {
    if ($ba['status'] === 'Approved') {
        $has_approved_bank = true;
    }
    if (!empty($ba['is_accepted'])) {
        $has_accepted_bank = true;
        $winning_assignment = $ba;
    }
}

// Applicant Specs
$loan_type = trim($applicant['loan_type'] ?? 'Home Loan');
$loan_sub_type = trim($applicant['loan_sub_type'] ?? 'General');
$loan_amount = (float)($applicant['loan_amount_requested'] ?? 0);

// 🌟 SMART LENDER ELIGIBILITY FILTERING:
// Match applicant's loan product & ticket size against Banker Directory
$all_bankers_rows = [];
try {
    $bq = $db->query("SELECT * FROM bankers WHERE (status IS NULL OR status = '' OR status = 'Active') ORDER BY bank_name ASC");
    if ($bq) {
        $all_bankers_rows = $bq->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {}

$eligible_bankers = [];
$eligible_banks = [];
$ineligible_bankers_count = 0;
$bankers_by_name = [];

foreach ($all_bankers_rows as $b) {
    $cats = array_map('trim', explode(',', strtolower($b['loan_category'] ?? '')));
    $matches_category = false;
    $needle = strtolower($loan_type);
    
    foreach ($cats as $c) {
        if (!empty($c) && (strpos($c, $needle) !== false || strpos($needle, $c) !== false)) {
            $matches_category = true;
            break;
        }
    }
    
    $min = (float)($b['min_loan_limit'] ?? 0);
    $max = (float)($b['max_loan_limit'] ?? 0);
    $matches_amount = true;
    if ($min > 0 && $loan_amount < $min) $matches_amount = false;
    if ($max > 0 && $loan_amount > $max) $matches_amount = false;
    
    if ($matches_category && $matches_amount) {
        $eligible_bankers[] = $b;
        if (!in_array($b['bank_name'], $eligible_banks)) {
            $eligible_banks[] = $b['bank_name'];
        }
        if (!isset($bankers_by_name[$b['bank_name']])) {
            $bankers_by_name[$b['bank_name']] = [
                'name' => $b['full_name'] ?? '',
                'designation' => $b['designation'] ?? '',
                'email' => $b['official_email'] ?? '',
                'contact' => $b['contact_number'] ?? '',
                'min_roi' => (float)($b['min_roi'] ?? 0),
                'max_roi' => (float)($b['max_roi'] ?? 0),
                'min_loan' => (float)($b['min_loan_limit'] ?? 0),
                'max_loan' => (float)($b['max_loan_limit'] ?? 0)
            ];
        }
    } else {
        $ineligible_bankers_count++;
    }
}

// Fallback safeguard if no specific match
if (empty($eligible_banks)) {
    $distinct_banks = $db->query("SELECT DISTINCT bank_name FROM bankers WHERE bank_name IS NOT NULL AND bank_name != '' ORDER BY bank_name");
    $eligible_banks = $distinct_banks ? $distinct_banks->fetchAll(PDO::FETCH_COLUMN) : ['HDFC Bank', 'State Bank of India', 'Bank of Baroda'];
}
sort($eligible_banks);
$banks = $eligible_banks; // For backwards compatibility

// Financial calculations for Left Column
$co_name = !empty($co_applicants) ? $co_applicants[0]['full_name'] : '';
$co_rel = !empty($co_applicants) ? ($co_applicants[0]['relationship'] ?? 'Co-App') : '';
$co_income = !empty($co_applicants) ? (float)($co_applicants[0]['monthly_income'] ?? 0) : 0;
$total_household_income = (float)($applicant['monthly_income'] ?? 0) + $co_income;

// Sanction Shortfall / Surplus Analysis
$cur_sanctioned = (float)($applicant['sanctioned_amount'] ?? 0);
$shortfall = ($cur_sanctioned > 0 && $cur_sanctioned < $loan_amount) ? ($loan_amount - $cur_sanctioned) : 0;

// 🌟 STRICT PHASE 4 DISBURSEMENT GATEKEEPER LOGIC:
// Gate 1: At least 1 bank submission logged
$gate_submissions_ok = (count($bank_assignments) > 0);

// Gate 2: At least 1 bank has issued 'Approved' status
$gate_approval_ok = false;
$approved_banks_list = [];
foreach ($bank_assignments as $ba) {
    if ($ba['status'] === 'Approved') {
        $gate_approval_ok = true;
        $approved_banks_list[] = $ba['bank_name'];
    }
}

// Gate 3: Winning Sanction Offer Accepted (either assignment is_accepted=1 OR applicant sanction_accepted=1 with sanction_bank)
$gate_offer_accepted = ($has_accepted_bank || (!empty($applicant['sanction_accepted']) && !empty($applicant['sanction_bank'])));

// Gate 4: Official Sanction Terms (CIBIL, Sanctioned Amount > 0, ROI > 0, Tenure > 0, EMI > 0, Sanction Date)
$gate_terms_ok = (
    !empty($applicant['cibil_score']) &&
    !empty($applicant['sanctioned_amount']) && (float)$applicant['sanctioned_amount'] > 0 &&
    !empty($applicant['interest_rate']) && (float)$applicant['interest_rate'] > 0 &&
    !empty($applicant['tenure_months']) && (int)$applicant['tenure_months'] > 0 &&
    !empty($applicant['emi']) && (float)$applicant['emi'] > 0 &&
    !empty($applicant['sanction_date'])
);

$gates_passed = 0;
if ($gate_submissions_ok) $gates_passed++;
if ($gate_approval_ok) $gates_passed++;
if ($gate_offer_accepted) $gates_passed++;
if ($gate_terms_ok) $gates_passed++;

$phase4_ready = ($gates_passed === 4);

// WhatsApp pre-formatted message
$customer_mobile = preg_replace('/[^0-9]/', '', $applicant['mobile'] ?? '');
if (strlen($customer_mobile) === 10) {
    $whatsapp_phone = '91' . $customer_mobile;
} else {
    $whatsapp_phone = $customer_mobile;
}

$wa_bank = !empty($applicant['sanction_bank']) ? $applicant['sanction_bank'] : (!empty($applicant['login_fee_bank']) ? $applicant['login_fee_bank'] : 'Partner Bank');
$wa_sanctioned = $cur_sanctioned > 0 ? '₹' . number_format($cur_sanctioned) : '₹' . number_format($loan_amount);
$wa_roi = !empty($applicant['interest_rate']) ? $applicant['interest_rate'] . '% p.a.' : 'Competitive ROI';
$wa_tenure = !empty($applicant['tenure_months']) ? $applicant['tenure_months'] . ' Months (' . round($applicant['tenure_months']/12, 1) . ' Yrs)' : 'Standard Tenure';
$wa_emi = !empty($applicant['emi']) && $applicant['emi'] > 0 ? '₹' . number_format($applicant['emi']) . ' / mo' : 'As per Term Sheet';

$wa_message = "🎉 *Congratulations " . ($applicant['customer_name'] ?? 'Customer') . "!*\n\n" .
              "Your *" . $loan_type . "* loan file (Ref: *" . ($applicant['loan_id'] ?? '') . "*) has been processed and sanctioned with *" . $wa_bank . "*.\n\n" .
              "📋 *Approved Sanction Terms:*\n" .
              "• Sanctioned Amount: *" . $wa_sanctioned . "*\n" .
              "• Rate of Interest: *" . $wa_roi . "*\n" .
              "• Approved Tenure: *" . $wa_tenure . "*\n" .
              "• Monthly EMI: *" . $wa_emi . "*\n\n" .
              "Our Sourcing Team is processing your Phase 4 Disbursement file.\n\n" .
              "— *BFS Financial Services Team*";

$wa_link = "https://api.whatsapp.com/send?phone=" . urlencode($whatsapp_phone) . "&text=" . urlencode($wa_message);

$page_title = 'Bank Processing & Sanction';
$page_subtitle = 'Phase 3: Send file to Bank & Update Sanction Details';
$hide_main_header = true;
require_once 'header.php';
?>
<style>
/* BFS Circle Luxury Obsidian & Slate Styling for Phase 3 */
body {
    background-color: #f8fafc;
}
main {
    padding: 14px 20px 32px 20px !important;
}
.view-container {
    padding: 0 !important;
    max-width: 100% !important;
    margin: 0 !important;
    width: 100% !important;
}
.phase3-card {
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
    padding: 22px;
    transition: all 0.2s ease;
}
.phase3-card:hover {
    border-color: #cbd5e1;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
}
.phase3-card-title {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #f1f5f9;
    padding-bottom: 14px;
    margin-bottom: 18px;
}
.phase3-card-title h3 {
    font-size: 15.5px;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.01em;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Premium Obsidian Buttons */
.btn-obsidian {
    background: #0f172a !important;
    color: #ffffff !important;
    border: 1px solid #0f172a !important;
    font-weight: 700 !important;
    font-size: 12px !important;
    border-radius: 8px !important;
    padding: 8px 16px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 6px !important;
    cursor: pointer !important;
    text-decoration: none !important;
    transition: all 0.15s ease !important;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.15) !important;
}
.btn-obsidian:hover {
    background: #000000 !important;
    border-color: #000000 !important;
    transform: translateY(-1px) !important;
    box-shadow: 0 3px 8px rgba(15, 23, 42, 0.25) !important;
}

.btn-slate-outline {
    background: #ffffff !important;
    border: 1px solid #cbd5e1 !important;
    color: #334155 !important;
    font-weight: 700 !important;
    font-size: 12px !important;
    border-radius: 8px !important;
    padding: 8px 14px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 6px !important;
    cursor: pointer !important;
    text-decoration: none !important;
    transition: all 0.15s ease !important;
}
.btn-slate-outline:hover {
    background: #f8fafc !important;
    border-color: #94a3b8 !important;
    color: #0f172a !important;
    transform: translateY(-1px) !important;
}

/* Phase 4 Proceed Button */
.btn-proceed-phase4 {
    background: #0f172a !important;
    color: #ffffff !important;
    border: 1px solid #0f172a !important;
    border-radius: 8px !important;
    padding: 12px 20px !important;
    font-size: 12.5px !important;
    font-weight: 800 !important;
    letter-spacing: 0.04em !important;
    text-transform: uppercase !important;
    cursor: pointer !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 8px !important;
    width: 100% !important;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1) !important;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.2) !important;
}
.btn-proceed-phase4:hover {
    background: #000000 !important;
    border-color: #000000 !important;
    transform: translateY(-1px) !important;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.3) !important;
}

/* WhatsApp Executive Button */
.btn-whatsapp-dispatch {
    background: #15803d !important;
    color: #ffffff !important;
    border: 1px solid #166534 !important;
    font-weight: 700 !important;
    font-size: 12px !important;
    border-radius: 8px !important;
    padding: 9px 14px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 6px !important;
    cursor: pointer !important;
    text-decoration: none !important;
    transition: all 0.15s ease !important;
    box-shadow: 0 1px 3px rgba(22, 101, 52, 0.25) !important;
}
.btn-whatsapp-dispatch:hover {
    background: #166534 !important;
    transform: translateY(-1px) !important;
    box-shadow: 0 4px 10px rgba(22, 101, 52, 0.35) !important;
}

/* High Density Clean Data Table */
.data-table {
    width: 100%;
    border-collapse: collapse;
}
.data-table th {
    background: #f8fafc;
    color: #64748b;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    padding: 10px 14px;
    border-bottom: 1.5px solid #e2e8f0;
}
.data-table td {
    padding: 12px 14px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
    color: #334155;
    font-size: 12.5px;
}
.data-table tbody tr:hover {
    background: #f8fafc;
}

/* Clean Form Controls */
.form-group {
    display: flex;
    flex-direction: column;
}
.form-group label {
    font-size: 11.5px;
    font-weight: 700;
    color: #475569;
    margin-bottom: 5px;
    letter-spacing: 0.02em;
}
.form-group input, .form-group select, .form-group textarea {
    background: #f8fafc;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    padding: 9px 12px;
    font-size: 13px;
    color: #0f172a;
    transition: all 0.15s ease;
    box-sizing: border-box;
}
.form-group input:focus, .form-group select:focus, .form-group textarea:focus {
    background: #ffffff;
    border-color: #0f172a;
    box-shadow: 0 0 0 3px rgba(15, 23, 42, 0.08);
    outline: none;
}

/* Journey Stepper Component */
.stepper-container {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 12px 18px;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
    gap: 8px;
    overflow-x: auto;
}
.step-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    font-weight: 700;
    color: #94a3b8;
    white-space: nowrap;
}
.step-item.active {
    color: #0f172a;
}
.step-item.completed {
    color: #059669;
}
.step-badge {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 800;
    background: #f1f5f9;
    color: #64748b;
    border: 1px solid #cbd5e1;
}
.step-item.active .step-badge {
    background: #0f172a;
    color: #ffffff;
    border-color: #0f172a;
}
.step-item.completed .step-badge {
    background: #ecfdf5;
    color: #059669;
    border-color: #a7f3d0;
}
.step-divider {
    flex: 1;
    height: 1px;
    background: #e2e8f0;
    min-width: 16px;
}
</style>

<div id="view-applicant-bank" class="view-container">

    <!-- 🌟 TOP MASTER EXECUTIVE APPLICANT HEADER CARD (FULL WIDTH) -->
    <div style="background: #000000; border-radius: 12px; padding: 18px 20px; color: #ffffff; margin-bottom: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.4); border: 1px solid #27272a;">
        
        <!-- ROW 1: APPLICANT IDENTITY & KEY CRM STATS -->
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 14px;">
            
            <!-- Applicant Meta Desk -->
            <div style="flex: 1; min-width: 280px;">
                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <a href="applicant_documents.php?id=<?php echo $id; ?>" style="background: #18181b; color: #ffffff; border: 1px solid #27272a; padding: 4px 10px; font-weight: 700; font-size: 11.5px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; transition: background 0.15s ease;" onmouseover="this.style.background='#27272a'" onmouseout="this.style.background='#18181b'" title="Return to Phase 2 Documents">
                        <i data-lucide="arrow-left" style="width:13px; height:13px; color:#ffffff;"></i> Phase 2 (Docs)
                    </a>
                    <h2 style="margin: 0; font-size: 20px; font-weight: 800; color: #ffffff; font-family: 'Outfit', sans-serif; letter-spacing: -0.02em;">
                        <?php echo htmlspecialchars($applicant['customer_name'] ?? ''); ?>
                    </h2>
                    <span style="background: #18181b; color: #ffffff; border: 1px solid #27272a; padding: 3px 10px; font-weight: 700; font-size: 11px; border-radius: 20px;">
                        Ref: <?php echo htmlspecialchars($applicant['loan_id'] ?? ''); ?>
                    </span>
                    <span style="background: #18181b; color: #ffffff; border: 1px solid #27272a; padding: 3px 10px; font-weight: 700; font-size: 11px; border-radius: 20px;">
                        Phase 3: Bank Processing &amp; Sanction
                    </span>
                    <?php if (!empty($co_applicants)): ?>
                        <?php foreach ($co_applicants as $co): ?>
                            <span style="font-size: 11px; font-weight: 600; background: #18181b; color: #a1a1aa; padding: 3px 10px; border-radius: 20px; border: 1px solid #27272a; display: inline-flex; align-items: center; gap: 4px;">
                                <i data-lucide="users" style="width:12px; height:12px; color:#ffffff;"></i> Co-App: <?php echo htmlspecialchars($co['full_name']); ?> (<?php echo htmlspecialchars($co['relationship'] ?? 'Co-App'); ?>)
                            </span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-top: 6px; font-size: 12px; color: #a1a1aa;">
                    <span>Loan Type: <strong style="color: #ffffff;"><?php echo htmlspecialchars($loan_type); ?> (<?php echo htmlspecialchars($loan_sub_type); ?>)</strong></span>
                    <span style="color: #3f3f46;">•</span>
                    <span>Employment: <strong style="color: #ffffff;"><?php echo htmlspecialchars($applicant['employment_type'] ?? '-'); ?></strong></span>
                    <?php if (!empty($applicant['created_at'])): ?>
                        <span style="color: #3f3f46;">•</span>
                        <span>Created: <strong style="color: #ffffff;"><?php echo date('d M Y', strtotime($applicant['created_at'])); ?></strong></span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Dark Glass Summary Stat Cards -->
            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                <div style="background: #18181b; border: 1px solid #27272a; border-radius: 8px; padding: 6px 14px; text-align: right; min-width: 120px;">
                    <div style="font-size: 10px; color: #a1a1aa; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;">Loan Amount</div>
                    <div style="font-size: 14px; font-weight: 800; color: #ffffff; font-family: 'Outfit', sans-serif;">₹<?php echo number_format($loan_amount); ?></div>
                </div>
                <div style="background: #18181b; border: 1px solid #27272a; border-radius: 8px; padding: 6px 14px; text-align: right; min-width: 120px;">
                    <div style="font-size: 10px; color: #a1a1aa; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;">Sanctioned Bank</div>
                    <div style="font-size: 13px; font-weight: 800; color: #ffffff; font-family: 'Outfit', sans-serif;"><?php echo htmlspecialchars(!empty($applicant['sanction_bank']) ? $applicant['sanction_bank'] : (!empty($applicant['login_fee_bank']) ? $applicant['login_fee_bank'] : 'Partner Banks')); ?></div>
                </div>
                <div style="background: #18181b; border: 1px solid #27272a; border-radius: 8px; padding: 6px 14px; text-align: right; min-width: 120px;">
                    <div style="font-size: 10px; color: #a1a1aa; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;">Assigned RM</div>
                    <div style="font-size: 13px; font-weight: 800; color: #ffffff; font-family: 'Outfit', sans-serif;"><?php echo htmlspecialchars(!empty($applicant['assigned_to']) ? $applicant['assigned_to'] : 'Admin Desk'); ?></div>
                </div>
            </div>
        </div>

        <!-- ROW 2: UNIFIED FULL-WIDTH EXECUTIVE ACTION TOOLBAR -->
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px; background: #18181b; padding: 6px; border-radius: 8px; border: 1px solid #27272a; flex-wrap: wrap; width: 100%;">
            <div style="display: inline-flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                <a href="search_track.php" class="btn" style="background: #18181b; color: #ffffff; border: 1px solid #27272a; padding: 6px 12px; border-radius: 6px; font-weight: 700; font-size: 11.5px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; text-decoration:none;" onmouseover="this.style.background='#27272a'" onmouseout="this.style.background='#18181b'">
                    <i data-lucide="folder" style="width: 13px; height: 13px; color: #ffffff;"></i> All LOS Cases
                </a>
                <a href="applicant_documents.php?id=<?php echo $id; ?>" class="btn" style="background: #18181b; color: #ffffff; border: 1px solid #27272a; padding: 6px 12px; border-radius: 6px; font-weight: 700; font-size: 11.5px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; text-decoration:none;" onmouseover="this.style.background='#27272a'" onmouseout="this.style.background='#18181b'">
                    <i data-lucide="file-check" style="width: 13px; height: 13px; color: #ffffff;"></i> Verified Docs (<?php echo $doc_count; ?>)
                </a>
                <a href="generate_zip.php?id=<?php echo $id; ?>" target="_blank" class="btn" style="background: #18181b; color: #ffffff; border: 1px solid #27272a; padding: 6px 12px; border-radius: 6px; font-weight: 700; font-size: 11.5px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; text-decoration:none;" onmouseover="this.style.background='#27272a'" onmouseout="this.style.background='#18181b'">
                    <i data-lucide="download" style="width: 13px; height: 13px; color: #ffffff;"></i> Download Bundle (ZIP)
                </a>
                <button type="button" onclick="openEmailModal()" class="btn" style="background: #18181b; color: #ffffff; border: 1px solid #27272a; padding: 6px 12px; border-radius: 6px; font-weight: 700; font-size: 11.5px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; transition: background 0.15s ease;" onmouseover="this.style.background='#27272a'" onmouseout="this.style.background='#18181b'">
                    <i data-lucide="mail" style="width: 13px; height: 13px; color: #ffffff;"></i> Email Banker
                </button>
                <button type="button" onclick="document.getElementById('manual-bank-modal').style.display='flex';" class="btn" style="background: #ffffff; color: #000000; border: none; padding: 6px 12px; border-radius: 6px; font-weight: 700; font-size: 11.5px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.2);">
                    <i data-lucide="plus-circle" style="width: 13px; height: 13px; color: #000000;"></i> Log Bank Submission
                </button>
                <a href="<?php echo htmlspecialchars($wa_link); ?>" target="_blank" class="btn" style="background: #15803d; color: #ffffff; border: none; padding: 6px 12px; border-radius: 6px; font-weight: 700; font-size: 11.5px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; text-decoration:none; box-shadow: 0 1px 3px rgba(22,101,52,0.3);">
                    <i data-lucide="message-square" style="width: 13px; height: 13px; color: #ffffff;"></i> WhatsApp Sanction
                </a>
            </div>
            <div>
                <?php if ($phase4_ready): ?>
                    <button type="button" onclick="verifyAndProceed()" class="btn" style="background: #16a34a; color: #ffffff; padding: 6px 14px; border-radius: 6px; font-size: 11.5px; font-weight: 800; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 8px rgba(22,163,74,0.4);">
                        <i data-lucide="unlock" style="width: 13px; height: 13px; color: #ffffff;"></i>
                        <span>Phase 4 Proceed (Disbursements) &rarr;</span>
                    </button>
                <?php else: ?>
                    <button type="button" onclick="verifyAndProceed()" class="btn" style="background: #27272a; color: #d4d4d8; border: 1px solid #3f3f46; padding: 6px 14px; border-radius: 6px; font-size: 11.5px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;" title="Click to view Phase 4 Gatekeeper checklist">
                        <i data-lucide="lock" style="width: 13px; height: 13px; color: #f59e0b;"></i>
                        <span>Phase 4 Locked (<?php echo $gates_passed; ?>/4 Gates)</span>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 🗺️ LOS PHASE 3 POST-DOCUMENT LIFECYCLE STEPPER -->
    <div class="stepper-container">
        <div class="step-item completed">
            <div class="step-badge"><i data-lucide="check" style="width:12px; height:12px;"></i></div>
            <span>1. Docs Verified (19/19)</span>
        </div>
        <div class="step-divider"></div>
        <div class="step-item <?php echo (($applicant['login_fee_status'] ?? '') === 'Paid' || (!empty($applicant['login_fee_amount']) && $applicant['login_fee_amount'] > 0)) ? 'completed' : 'active'; ?>">
            <div class="step-badge"><?php echo (($applicant['login_fee_status'] ?? '') === 'Paid') ? '<i data-lucide="check" style="width:12px; height:12px;"></i>' : '2'; ?></div>
            <span>2. Bank Login Fee (<?php echo (($applicant['login_fee_status'] ?? '') === 'Paid') ? 'Paid' : 'Pending'; ?>)</span>
        </div>
        <div class="step-divider"></div>
        <div class="step-item <?php echo !empty($bank_assignments) ? 'completed' : 'active'; ?>">
            <div class="step-badge"><?php echo !empty($bank_assignments) ? '<i data-lucide="check" style="width:12px; height:12px;"></i>' : '3'; ?></div>
            <span>3. Bank Submissions (<?php echo count($bank_assignments); ?> Dispatched)</span>
        </div>
        <div class="step-divider"></div>
        <div class="step-item <?php echo $has_approved_bank ? 'completed' : 'active'; ?>">
            <div class="step-badge"><?php echo $has_approved_bank ? '<i data-lucide="check" style="width:12px; height:12px;"></i>' : '4'; ?></div>
            <span>4. Sanction Offers &amp; Compare</span>
        </div>
        <div class="step-divider"></div>
        <div class="step-item <?php echo $phase4_ready ? 'completed' : 'active'; ?>">
            <div class="step-badge"><?php echo $phase4_ready ? '<i data-lucide="check" style="width:12px; height:12px;"></i>' : '5'; ?></div>
            <span>5. <?php echo $phase4_ready ? 'Phase 4 Ready (Unlocked)' : 'Phase 4 Gatekeeper (' . $gates_passed . '/4)'; ?></span>
        </div>
    </div>

    <!-- 🎯 SMART LENDER MATCHING BADGE -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-left: 4px solid #0f172a; border-radius: 8px; padding: 10px 16px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <div style="display:flex; align-items:center; gap:10px;">
            <i data-lucide="sparkles" style="width:18px; height:18px; color:#0f172a; flex-shrink:0;"></i>
            <div>
                <span style="font-size:12.5px; font-weight:800; color:#0f172a;">Smart Lender Policy Filter Active:</span>
                <span style="font-size:12.5px; color:#475569; margin-left:4px;">
                    Matched <strong><?php echo count($eligible_banks); ?> Partner Banks</strong> that service <strong><?php echo htmlspecialchars($loan_type); ?></strong> for ₹<?php echo number_format($loan_amount); ?> ticket size (<?php echo implode(', ', $eligible_banks); ?>).
                </span>
            </div>
        </div>
        <?php if ($ineligible_bankers_count > 0): ?>
            <span style="font-size:11px; font-weight:700; color:#64748b; background:#f1f5f9; padding:3px 8px; border-radius:12px;">
                <?php echo $ineligible_bankers_count; ?> non-matching lenders filtered out
            </span>
        <?php endif; ?>
    </div>

    <!-- MAIN 2-COLUMN WORKSPACE GRID (1fr 2fr) -->
    <div class="dashboard-layout-row" style="grid-template-columns: 1fr 2fr; gap: 20px; align-items: start;">
        
        <!-- LEFT COLUMN: APPLICANT FINANCIAL BLUEPRINT, EMI BREAKDOWN & ACTIONS -->
        <div style="display:flex; flex-direction:column; gap:20px;">
            
            <!-- Sourcing & Financial Specs Card -->
            <div class="phase3-card">
                <div class="phase3-card-title">
                    <h3>
                        <i data-lucide="wallet" style="width:16px; height:16px; color:#0f172a;"></i>
                        Sourcing &amp; Financial Blueprint
                    </h3>
                    <span style="font-size:11px; font-weight:700; background:#f1f5f9; color:#475569; border:1px solid #cbd5e1; padding:2px 8px; border-radius:12px;">Phase 3</span>
                </div>

                <!-- Financial Specs Summary Card -->
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:16px; margin-bottom:18px;">
                    <div style="font-size:10px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.04em; margin-bottom:4px;">Requested Loan Amount</div>
                    <div style="font-size:22px; font-weight:800; color:#0f172a; font-family:'Outfit', sans-serif; line-height:1.2; margin-bottom:14px;">
                        ₹<?php echo number_format($loan_amount); ?>
                    </div>

                    <div style="display:flex; flex-direction:column; gap:8px; font-size:12px;">
                        <div style="display:flex; justify-content:space-between; border-bottom:1px solid #e2e8f0; padding-bottom:6px;">
                            <span style="color:#64748b;">Loan Product</span>
                            <strong style="color:#0f172a;"><?php echo htmlspecialchars($loan_type); ?></strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; border-bottom:1px solid #e2e8f0; padding-bottom:6px;">
                            <span style="color:#64748b;">Sub-Category</span>
                            <strong style="color:#0f172a;"><?php echo htmlspecialchars($loan_sub_type); ?></strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; border-bottom:1px solid #e2e8f0; padding-bottom:6px;">
                            <span style="color:#64748b;">Primary Income</span>
                            <strong style="color:#0f172a;">₹<?php echo number_format($applicant['monthly_income'] ?? 0); ?> / mo</strong>
                        </div>
                        <?php if ($co_name): ?>
                        <div style="display:flex; justify-content:space-between; border-bottom:1px solid #e2e8f0; padding-bottom:6px;">
                            <span style="color:#64748b;">Co-Applicant</span>
                            <strong style="color:#0f172a;">₹<?php echo number_format($co_income); ?> / mo (<?php echo htmlspecialchars($co_name); ?>)</strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; border-bottom:1px solid #e2e8f0; padding-bottom:6px;">
                            <span style="color:#64748b;">Household Total</span>
                            <strong style="color:#0f172a; font-weight:800;">₹<?php echo number_format($total_household_income); ?> / mo</strong>
                        </div>
                        <?php endif; ?>
                        <div style="display:flex; justify-content:space-between; padding-top:2px;">
                            <span style="color:#64748b;">Verified Documents</span>
                            <span style="color:#059669; font-weight:700; display:inline-flex; align-items:center; gap:4px;">
                                <i data-lucide="check-circle-2" style="width:12px; height:12px;"></i> <?php echo $verified_doc_count; ?> of <?php echo $doc_count; ?> (100% Ready)
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Sanction Shortfall / Surplus Alert Banner -->
                <?php if ($shortfall > 0): ?>
                    <div style="background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:12px; margin-bottom:18px;">
                        <div style="display:flex; align-items:center; gap:6px; color:#b45309; font-weight:800; font-size:12px; margin-bottom:4px;">
                            <i data-lucide="alert-triangle" style="width:14px; height:14px;"></i> Sanction Shortfall: ₹<?php echo number_format($shortfall); ?>
                        </div>
                        <div style="font-size:11.5px; color:#78350f; line-height:1.4;">
                            Bank approved ₹<?php echo number_format($cur_sanctioned); ?> vs requested ₹<?php echo number_format($loan_amount); ?>.
                            <?php if ($co_income > 0): ?>
                                Suggestion: Combine Co-App <?php echo htmlspecialchars($co_name); ?> income (₹<?php echo number_format($co_income); ?>/mo) for top-up appeal.
                            <?php else: ?>
                                Suggestion: Consider extending tenure or presenting additional collateral.
                            <?php endif; ?>
                        </div>
                    </div>
                <?php elseif ($cur_sanctioned >= $loan_amount && $cur_sanctioned > 0): ?>
                    <div style="background:#ecfdf5; border:1px solid #a7f3d0; border-radius:8px; padding:12px; margin-bottom:18px;">
                        <div style="display:flex; align-items:center; gap:6px; color:#065f46; font-weight:800; font-size:12px; margin-bottom:4px;">
                            <i data-lucide="check-circle-2" style="width:14px; height:14px;"></i> 100% Loan Fulfilled (₹<?php echo number_format($cur_sanctioned); ?>)
                        </div>
                        <div style="font-size:11.5px; color:#047857; line-height:1.4;">
                            Target loan requirement completely met without any shortfall. Ready for final disbursement handover.
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Share Dossier Action Buttons -->
                <div style="margin-bottom:18px;">
                    <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.04em; margin-bottom:10px;">Bank File Dossier</div>
                    <div style="display:flex; flex-direction:column; gap:8px;">
                        <a href="generate_zip.php?id=<?php echo $id; ?>" target="_blank" class="btn-obsidian" style="width:100%; box-sizing:border-box;">
                            <i data-lucide="download" style="width:14px; height:14px;"></i> Download Bundle (ZIP)
                        </a>
                        <button type="button" onclick="openEmailModal()" class="btn-slate-outline" style="width:100%; box-sizing:border-box;">
                            <i data-lucide="mail" style="width:14px; height:14px;"></i> Email directly to Banker
                        </button>
                        <a href="<?php echo htmlspecialchars($wa_link); ?>" target="_blank" class="btn-whatsapp-dispatch" style="width:100%; box-sizing:border-box;">
                            <i data-lucide="message-square" style="width:14px; height:14px;"></i> WhatsApp Sanction to Customer
                        </a>
                    </div>
                </div>

                <!-- 🛡️ Phase 4 Disbursement Readiness Gatekeeper Card -->
                <div style="background:#f8fafc; border:1px solid <?php echo $phase4_ready ? '#a7f3d0' : '#e2e8f0'; ?>; border-radius:10px; padding:14px; margin-bottom:16px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                        <span style="font-size:11px; font-weight:800; color:#0f172a; text-transform:uppercase; letter-spacing:0.04em; display:flex; align-items:center; gap:5px;">
                            <i data-lucide="<?php echo $phase4_ready ? 'shield-check' : 'shield-alert'; ?>" style="width:14px; height:14px; color:<?php echo $phase4_ready ? '#16a34a' : '#f59e0b'; ?>;"></i>
                            Phase 4 Gatekeeper
                        </span>
                        <span style="font-size:11px; font-weight:800; padding:2px 8px; border-radius:12px; background:<?php echo $phase4_ready ? '#ecfdf5' : '#fffbeb'; ?>; color:<?php echo $phase4_ready ? '#065f46' : '#b45309'; ?>; border:1px solid <?php echo $phase4_ready ? '#a7f3d0' : '#fde68a'; ?>;">
                            <?php echo $gates_passed; ?> of 4 Gates Passed
                        </span>
                    </div>
                    
                    <div style="display:flex; flex-direction:column; gap:8px; font-size:11.5px;">
                        <div style="display:flex; align-items:center; justify-content:space-between; padding-bottom:5px; border-bottom:1px solid #f1f5f9;">
                            <span style="display:flex; align-items:center; gap:6px; color:<?php echo $gate_submissions_ok ? '#059669' : '#64748b'; ?>; font-weight:<?php echo $gate_submissions_ok ? '700' : '500'; ?>;">
                                <i data-lucide="<?php echo $gate_submissions_ok ? 'check-circle-2' : 'circle'; ?>" style="width:13px; height:13px; color:<?php echo $gate_submissions_ok ? '#059669' : '#cbd5e1'; ?>;"></i>
                                1. Bank Submission Logged
                            </span>
                            <span style="font-weight:700; font-size:11px; color:<?php echo $gate_submissions_ok ? '#059669' : '#94a3b8'; ?>;">
                                <?php echo count($bank_assignments); ?> Dispatched
                            </span>
                        </div>
                        
                        <div style="display:flex; align-items:center; justify-content:space-between; padding-bottom:5px; border-bottom:1px solid #f1f5f9;">
                            <span style="display:flex; align-items:center; gap:6px; color:<?php echo $gate_approval_ok ? '#059669' : '#64748b'; ?>; font-weight:<?php echo $gate_approval_ok ? '700' : '500'; ?>;">
                                <i data-lucide="<?php echo $gate_approval_ok ? 'check-circle-2' : 'circle'; ?>" style="width:13px; height:13px; color:<?php echo $gate_approval_ok ? '#059669' : '#cbd5e1'; ?>;"></i>
                                2. Bank Approval Issued
                            </span>
                            <span style="font-weight:700; font-size:11px; color:<?php echo $gate_approval_ok ? '#059669' : '#b45309'; ?>;">
                                <?php echo $gate_approval_ok ? 'Approved' : 'Pending Bank'; ?>
                            </span>
                        </div>

                        <div style="display:flex; align-items:center; justify-content:space-between; padding-bottom:5px; border-bottom:1px solid #f1f5f9;">
                            <span style="display:flex; align-items:center; gap:6px; color:<?php echo $gate_offer_accepted ? '#059669' : '#64748b'; ?>; font-weight:<?php echo $gate_offer_accepted ? '700' : '500'; ?>;">
                                <i data-lucide="<?php echo $gate_offer_accepted ? 'check-circle-2' : 'circle'; ?>" style="width:13px; height:13px; color:<?php echo $gate_offer_accepted ? '#059669' : '#cbd5e1'; ?>;"></i>
                                3. Offer Accepted &amp; Locked
                            </span>
                            <span style="font-weight:700; font-size:11px; color:<?php echo $gate_offer_accepted ? '#059669' : '#94a3b8'; ?>;">
                                <?php echo $gate_offer_accepted ? htmlspecialchars($applicant['sanction_bank'] ?: 'Locked') : 'Not Accepted'; ?>
                            </span>
                        </div>

                        <div style="display:flex; align-items:center; justify-content:space-between;">
                            <span style="display:flex; align-items:center; gap:6px; color:<?php echo $gate_terms_ok ? '#059669' : '#64748b'; ?>; font-weight:<?php echo $gate_terms_ok ? '700' : '500'; ?>;">
                                <i data-lucide="<?php echo $gate_terms_ok ? 'check-circle-2' : 'circle'; ?>" style="width:13px; height:13px; color:<?php echo $gate_terms_ok ? '#059669' : '#cbd5e1'; ?>;"></i>
                                4. Sanction Terms (CIBIL, EMI)
                            </span>
                            <span style="font-weight:700; font-size:11px; color:<?php echo $gate_terms_ok ? '#059669' : '#94a3b8'; ?>;">
                                <?php echo $gate_terms_ok ? 'Complete' : 'Missing'; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Proceed to Phase 4 Button -->
                <div style="padding-top:14px; border-top:1px solid #f1f5f9;">
                    <?php if ($phase4_ready): ?>
                        <button type="button" onclick="verifyAndProceed()" class="btn-proceed-phase4" style="background:#16a34a !important; border-color:#16a34a !important; box-shadow:0 4px 14px rgba(22,163,74,0.35) !important;">
                            <i data-lucide="unlock" style="width:15px; height:15px;"></i>
                            <span>Proceed to Phase 4 (Disbursements) &rarr;</span>
                        </button>
                    <?php else: ?>
                        <button type="button" onclick="verifyAndProceed()" class="btn-proceed-phase4" style="background:#334155 !important; border-color:#334155 !important; cursor:pointer;" title="Click to view gatekeeper checklist">
                            <i data-lucide="lock" style="width:15px; height:15px; color:#f59e0b;"></i>
                            <span>Phase 4 Locked (<?php echo $gates_passed; ?>/4 Gates Passed)</span>
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 📊 Interactive Live EMI & Amortization Breakdown Widget -->
            <div class="phase3-card">
                <div class="phase3-card-title">
                    <h3>
                        <i data-lucide="pie-chart" style="width:16px; height:16px; color:#0f172a;"></i>
                        Live Amortization Economics
                    </h3>
                    <span style="font-size:11px; font-weight:700; color:#64748b;">Calculator</span>
                </div>

                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:14px; margin-bottom:14px;">
                    <div style="display:flex; justify-content:space-between; align-items:baseline; margin-bottom:10px;">
                        <div>
                            <div style="font-size:10px; font-weight:700; color:#64748b; text-transform:uppercase;">Monthly EMI</div>
                            <div id="calc-emi-display" style="font-size:18px; font-weight:800; color:#0f172a; font-family:'Outfit', sans-serif;">
                                ₹<?php echo number_format($applicant['emi'] ?? 0); ?>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-size:10px; font-weight:700; color:#64748b; text-transform:uppercase;">Total Payable</div>
                            <div id="calc-total-payable" style="font-size:14px; font-weight:800; color:#0f172a; font-family:'Outfit', sans-serif;">—</div>
                        </div>
                    </div>

                    <!-- Dual Progress Bar: Principal vs Interest -->
                    <div style="height:10px; background:#e2e8f0; border-radius:5px; display:flex; overflow:hidden; margin-bottom:8px;">
                        <div id="calc-bar-principal" style="background:#0f172a; width:55%; transition:width 0.3s ease;" title="Principal Share"></div>
                        <div id="calc-bar-interest" style="background:#94a3b8; width:45%; transition:width 0.3s ease;" title="Interest Share"></div>
                    </div>

                    <div style="display:flex; justify-content:space-between; font-size:11px; color:#64748b;">
                        <span><strong style="color:#0f172a;">● Principal:</strong> <span id="calc-principal-label">₹<?php echo number_format($cur_sanctioned > 0 ? $cur_sanctioned : $loan_amount); ?></span></span>
                        <span><strong style="color:#64748b;">● Interest:</strong> <span id="calc-interest-label">—</span></span>
                    </div>
                </div>

                <div style="font-size:11px; color:#94a3b8; line-height:1.4;">
                    Amortization estimates are dynamically recalculated from the Rate of Interest and Approved Tenure.
                </div>
            </div>

        </div>

        <!-- RIGHT COLUMN: PROCESSING FEE, MULTI-BANK COMPARISON & FINAL SANCTION -->
        <div style="display:flex; flex-direction:column; gap:20px;">

            <!-- 1. Bank Login / Processing Fee Management -->
            <div class="phase3-card">
                <div class="phase3-card-title">
                    <h3>
                        <i data-lucide="receipt" style="width:16px; height:16px; color:#0f172a;"></i>
                        Bank Login &amp; Processing Fee Management
                    </h3>
                    <?php if (($applicant['login_fee_status'] ?? '') === 'Paid' || (!empty($applicant['login_fee_amount']) && $applicant['login_fee_amount'] > 0)): ?>
                        <span class="badge" style="background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; font-size:11.5px; font-weight:700; padding:3px 10px; border-radius:20px; display:inline-flex; align-items:center; gap:4px;">
                            <i data-lucide="check-circle" style="width:12px; height:12px;"></i> PAID: ₹<?php echo number_format($applicant['login_fee_amount'] ?? 0); ?>
                        </span>
                    <?php else: ?>
                        <span class="badge" style="background:#fffbeb; color:#b45309; border:1px solid #fde68a; font-size:11.5px; font-weight:700; padding:3px 10px; border-radius:20px; display:inline-flex; align-items:center; gap:4px;">
                            <i data-lucide="clock" style="width:12px; height:12px;"></i> PAYMENT PENDING
                        </span>
                    <?php endif; ?>
                </div>

                <form id="login-fee-form" onsubmit="saveLoginFee(event)" enctype="multipart/form-data">
                    <input type="hidden" name="applicant_id" value="<?php echo $id; ?>">
                    
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:14px; margin-bottom:14px;">
                        <div class="form-group">
                            <label class="required">Login Fee Amount (₹)</label>
                            <input type="number" step="0.01" name="login_fee_amount" id="lf_amount" required placeholder="e.g. 2500" value="<?php echo htmlspecialchars($applicant['login_fee_amount'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="required">Target Bank (Eligible Only)</label>
                            <select name="login_fee_bank" id="lf_bank" required>
                                <option value="">-- Select Eligible Bank --</option>
                                <?php 
                                $cur_lbank = $applicant['login_fee_bank'] ?? $applicant['sanction_bank'] ?? '';
                                foreach($eligible_banks as $eb): 
                                    $sel = ($cur_lbank === $eb) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo htmlspecialchars($eb); ?>" <?php echo $sel; ?>>
                                        <?php echo htmlspecialchars($eb); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="required">Payment Mode</label>
                            <select name="login_fee_payment_mode" id="lf_mode" required>
                                <?php
                                $cur_mode = $applicant['login_fee_payment_mode'] ?? 'UPI / QR Code';
                                $modes = ['UPI / QR Code', 'NetBanking / NEFT / RTGS', 'Cheque / DD', 'Cash / POS', 'Bank Direct Debit'];
                                foreach ($modes as $m) {
                                    $sel = ($cur_mode === $m) ? 'selected' : '';
                                    echo "<option value=\"$m\" $sel>$m</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:14px; margin-bottom:14px;">
                        <div class="form-group">
                            <label class="required">Payment Date</label>
                            <input type="date" name="login_fee_paid_date" id="lf_date" required value="<?php echo htmlspecialchars($applicant['login_fee_paid_date'] ?? date('Y-m-d')); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Bank Reference / UTR Number</label>
                            <input type="text" name="login_fee_ref_no" id="lf_ref" placeholder="e.g. UTR-9876543210" value="<?php echo htmlspecialchars($applicant['login_fee_ref_no'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom:14px;">
                        <label>Upload Payment Receipt / Deposit Slip (PDF / Image)</label>
                        <input type="file" name="login_fee_receipt" id="lf_receipt" accept=".pdf,.png,.jpg,.jpeg">
                        <?php if (!empty($applicant['login_fee_receipt_path'])): ?>
                            <div style="margin-top:6px; font-size:12px;">
                                <a href="<?php echo htmlspecialchars($applicant['login_fee_receipt_path']); ?>" target="_blank" style="color:#0f172a; font-weight:700; text-decoration:underline;">
                                    <i data-lucide="external-link" style="width:12px; height:12px; display:inline;"></i> View Uploaded Receipt
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group" style="margin-bottom:16px;">
                        <label>Remarks / Bank Counter Notes</label>
                        <textarea name="login_fee_notes" id="lf_notes" rows="2" placeholder="e.g. Paid via QR scan at branch counter."><?php echo htmlspecialchars($applicant['login_fee_notes'] ?? ''); ?></textarea>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center; padding-top:14px; border-top:1px solid #f1f5f9; flex-wrap:wrap; gap:10px;">
                        <label style="display:flex; align-items:center; gap:8px; font-size:12px; color:#475569; cursor:pointer;">
                            <input type="checkbox" name="dispatch_customer_receipt" value="1" checked style="accent-color:#0f172a;">
                            <span>Dispatch Official Login Fee Payment Receipt &amp; Banker Intake Email to Customer</span>
                        </label>
                        <button type="submit" class="btn-obsidian" id="save-login-fee-btn">
                            <i data-lucide="receipt" style="width:14px; height:14px;"></i> Save Login Fee &amp; Issue Confirmation
                        </button>
                    </div>
                </form>
            </div>

            <!-- 🌟 2. MULTI-BANK SANCTION OFFER COMPARISON MATRIX (THE OFFER SHOWDOWN) -->
            <div class="phase3-card">
                <div class="phase3-card-title">
                    <h3>
                        <i data-lucide="scale" style="width:16px; height:16px; color:#0f172a;"></i>
                        Multi-Bank Sanction Offer Comparison Matrix
                    </h3>
                    <button type="button" class="btn-slate-outline" onclick="document.getElementById('manual-bank-modal').style.display='flex';" style="padding: 5px 12px; font-size: 11.5px;">
                        <i data-lucide="plus" style="width:13px; height:13px;"></i> Log Submission
                    </button>
                </div>

                <div style="background: #f8fafc; padding: 10px 14px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 16px; display:flex; gap:10px; align-items:center;">
                    <i data-lucide="info" style="color: #0f172a; width: 16px; height: 16px; flex-shrink:0;"></i>
                    <p style="font-size: 12px; color: #475569; margin:0; line-height:1.5;">
                        Compare simultaneous sanction offers received across lenders. Click <strong>Accept &amp; Lock Sanction</strong> to officially designate the winning lender for Phase 4.
                    </p>
                </div>

                <?php if (empty($bank_assignments)): ?>
                    <div style="text-align:center; padding:32px 20px; background:#f8fafc; border:1px dashed #cbd5e1; border-radius:10px; color:#64748b;">
                        <i data-lucide="layers" style="width:32px; height:32px; color:#94a3b8; margin-bottom:8px;"></i>
                        <div style="font-weight:700; font-size:13px; color:#0f172a;">No Bank Submissions Logged Yet</div>
                        <div style="font-size:12px; margin-top:4px;">Use <strong>'+ Log Bank Submission'</strong> or <strong>'Email Banker'</strong> to initiate file dispatch to eligible lenders.</div>
                    </div>
                <?php else: ?>
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:16px; margin-bottom:10px;">
                        <?php foreach($bank_assignments as $ba): 
                            $is_acc = !empty($ba['is_accepted']);
                            $is_app = ($ba['status'] === 'Approved');
                            $s_amt = (float)($ba['sanctioned_amount'] ?? 0);
                            $s_roi = (float)($ba['interest_rate'] ?? 0);
                            $s_tenure = (int)($ba['tenure_months'] ?? 0);
                            $s_emi = (float)($ba['emi'] ?? 0);
                            $s_pf = (float)($ba['processing_fee'] ?? 0);
                        ?>
                            <div style="background: <?php echo $is_acc ? '#f0fdf4' : '#ffffff'; ?>; border: 2px solid <?php echo $is_acc ? '#16a34a' : ($is_app ? '#0f172a' : '#e2e8f0'); ?>; border-radius: 10px; padding: 16px; display:flex; flex-direction:column; justify-content:space-between; position:relative; box-shadow:0 1px 3px rgba(0,0,0,0.04);">
                                
                                <?php if ($is_acc): ?>
                                    <div style="position:absolute; top:-10px; right:12px; background:#16a34a; color:#ffffff; font-size:10px; font-weight:800; padding:2px 8px; border-radius:12px; text-transform:uppercase; letter-spacing:0.04em; display:flex; align-items:center; gap:3px;">
                                        <i data-lucide="star" style="width:10px; height:10px;"></i> Official Winning Sanction
                                    </div>
                                <?php endif; ?>

                                <div>
                                    <div style="display:flex; justify-content:space-between; align-items:start; margin-bottom:10px;">
                                        <div>
                                            <h4 style="margin:0; font-size:15px; font-weight:800; color:#0f172a;"><?php echo htmlspecialchars($ba['bank_name']); ?></h4>
                                            <div style="font-size:11px; color:#64748b; margin-top:2px;">
                                                Logged: <?php echo date('d M Y', strtotime($ba['assigned_date'] ?? $ba['created_at'] ?? date('Y-m-d'))); ?>
                                                <?php 
                                                    $days_elapsed = floor((time() - strtotime($ba['assigned_date'] ?? $ba['created_at'] ?? date('Y-m-d'))) / 86400);
                                                    echo " • TAT: <strong>{$days_elapsed}d</strong>";
                                                ?>
                                            </div>
                                        </div>
                                        <div>
                                            <?php if ($ba['status'] === 'Approved'): ?>
                                                <span style="font-size:11px; font-weight:700; background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; padding:2px 8px; border-radius:12px;">Approved</span>
                                            <?php elseif ($ba['status'] === 'Rejected' || $ba['status'] === 'Customer Rejected'): ?>
                                                <span style="font-size:11px; font-weight:700; background:#fef2f2; color:#991b1b; border:1px solid #fecaca; padding:2px 8px; border-radius:12px;">Rejected</span>
                                            <?php else: ?>
                                                <span style="font-size:11px; font-weight:700; background:#fffbeb; color:#b45309; border:1px solid #fde68a; padding:2px 8px; border-radius:12px;"><?php echo htmlspecialchars($ba['status']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Key Offer Terms -->
                                    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:10px; margin-bottom:12px; display:flex; flex-direction:column; gap:6px; font-size:12px;">
                                        <div style="display:flex; justify-content:space-between;">
                                            <span style="color:#64748b;">Sanctioned Amt:</span>
                                            <strong style="color:#0f172a;"><?php echo $s_amt > 0 ? '₹' . number_format($s_amt) : 'Pending / Not Logged'; ?></strong>
                                        </div>
                                        <div style="display:flex; justify-content:space-between;">
                                            <span style="color:#64748b;">Interest Rate (ROI):</span>
                                            <strong style="color:#0f172a;"><?php echo $s_roi > 0 ? $s_roi . '% p.a.' : '—'; ?></strong>
                                        </div>
                                        <div style="display:flex; justify-content:space-between;">
                                            <span style="color:#64748b;">Tenure / EMI:</span>
                                            <strong style="color:#0f172a;"><?php echo $s_tenure > 0 ? $s_tenure . ' Mo / ₹' . number_format($s_emi) : '—'; ?></strong>
                                        </div>
                                        <div style="display:flex; justify-content:space-between;">
                                            <span style="color:#64748b;">Processing Fee:</span>
                                            <strong style="color:#0f172a;"><?php echo $s_pf > 0 ? '₹' . number_format($s_pf) : 'Standard'; ?></strong>
                                        </div>
                                        <div style="display:flex; justify-content:space-between;">
                                            <span style="color:#64748b;">Stage:</span>
                                            <span style="font-weight:700; color:#0f172a;"><?php echo htmlspecialchars($ba['underwriting_stage'] ?? 'Logged In'); ?></span>
                                        </div>
                                    </div>

                                    <?php if (!empty($ba['sanction_letter_path'])): ?>
                                        <div style="margin-bottom:12px;">
                                            <button type="button" onclick="previewSanctionLetter('<?php echo htmlspecialchars($ba['sanction_letter_path']); ?>', '<?php echo htmlspecialchars(addslashes($ba['bank_name'])); ?>')" class="btn-slate-outline" style="width:100%; box-sizing:border-box; font-size:11px; padding:4px 8px;">
                                                <i data-lucide="file-text" style="width:12px; height:12px;"></i> View Sanction Letter (PDF)
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div style="display:flex; gap:8px; margin-top:8px;">
                                    <?php if ($ba['status'] === 'Approved'): ?>
                                        <?php if ($is_acc): ?>
                                            <button type="button" class="btn-obsidian" disabled style="flex:1; opacity:0.8; background:#16a34a !important; border-color:#16a34a !important;">
                                                <i data-lucide="check" style="width:13px; height:13px;"></i> Offer Locked
                                            </button>
                                        <?php else: ?>
                                            <button type="button" onclick="acceptBankOffer(<?php echo $ba['id']; ?>, '<?php echo htmlspecialchars(addslashes($ba['bank_name'])); ?>')" class="btn-obsidian" style="flex:1;">
                                                <i data-lucide="check-circle" style="width:13px; height:13px;"></i> Accept Offer
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <button type="button" class="btn-slate-outline" style="flex:1;" onclick="openUpdateStatusModal(<?php echo $ba['id']; ?>, '<?php echo $ba['status']; ?>', '<?php echo htmlspecialchars(addslashes($ba['rejection_reason'] ?? '')); ?>', '<?php echo htmlspecialchars(addslashes($ba['underwriting_stage'] ?? 'Logged In')); ?>', '<?php echo $s_amt; ?>', '<?php echo $s_roi; ?>', '<?php echo $s_tenure; ?>', '<?php echo $s_emi; ?>', '<?php echo $s_pf; ?>')">
                                        Update Terms
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- 3. Final Banker Feedback & Sanction Terms Locked Form -->
            <div class="phase3-card">
                <div class="phase3-card-title">
                    <h3>
                        <i data-lucide="landmark" style="width:16px; height:16px; color:#0f172a;"></i>
                        Official Sanction Terms &amp; Document Archival
                    </h3>
                    <?php if (!empty($applicant['sanction_letter_path'])): ?>
                        <button type="button" onclick="previewSanctionLetter('<?php echo htmlspecialchars($applicant['sanction_letter_path']); ?>', 'Official Sanction Document')" class="btn-slate-outline" style="padding:4px 10px; font-size:11px;">
                            <i data-lucide="file-check" style="width:12px; height:12px;"></i> View Attached Sanction PDF
                        </button>
                    <?php endif; ?>
                </div>
            
                <div style="background: #f8fafc; padding: 10px 14px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 18px; display:flex; align-items:center; gap:8px;">
                    <i data-lucide="shield-check" style="width:16px; height:16px; color:#0f172a; flex-shrink:0;"></i>
                    <p style="font-size: 12px; color: #475569; margin:0; line-height: 1.5;">
                        These official terms lock into Dharmendra Bhaskar's permanent loan profile for Phase 4 Disbursements. You can attach the bank's signed Sanction Letter below.
                    </p>
                </div>

                <form id="bank-feedback-form" onsubmit="saveBankFeedback(event)" enctype="multipart/form-data">
                    <input type="hidden" name="applicant_id" value="<?php echo $id; ?>">
                    
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:14px; margin-bottom:14px;">
                        <div class="form-group">
                            <label class="required">Sanctioning Bank (Eligible Only)</label>
                            <select name="sanction_bank" id="sanction_bank" required>
                                <option value="">-- Select Bank --</option>
                                <?php 
                                $cur_sbank = $applicant['sanction_bank'] ?? $applicant['login_fee_bank'] ?? '';
                                foreach($eligible_banks as $eb): 
                                    $sel = ($cur_sbank === $eb) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo htmlspecialchars($eb); ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($eb); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Sanction Order / Reference No</label>
                            <input type="text" name="sanction_ref_no" id="sanction_ref_no" placeholder="e.g. HDFC-SAN-2026-993" value="<?php echo htmlspecialchars($applicant['sanction_ref_no'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label class="required">CIBIL Score</label>
                            <input type="number" name="cibil_score" id="cibil_score" required placeholder="e.g. 750" value="<?php echo htmlspecialchars($applicant['cibil_score'] ?? ''); ?>">
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:14px; margin-bottom:14px;">
                        <div class="form-group">
                            <label class="required">Sanctioned Amount (₹)</label>
                            <input type="number" name="sanctioned_amount" id="sanctioned_amount" required placeholder="e.g. 1500000" value="<?php echo htmlspecialchars($applicant['sanctioned_amount'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="required">Rate of Interest (ROI %)</label>
                            <input type="number" step="0.01" name="interest_rate" id="interest_rate" required placeholder="e.g. 8.5" value="<?php echo htmlspecialchars($applicant['interest_rate'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label class="required">Approved Tenure (Months)</label>
                            <input type="number" name="tenure_months" id="tenure_months" required placeholder="e.g. 180" value="<?php echo htmlspecialchars($applicant['tenure_months'] ?? ''); ?>">
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:14px; margin-bottom:16px;">
                        <div class="form-group">
                            <label class="required">Approved Monthly EMI (₹)</label>
                            <input type="number" step="0.01" name="emi" id="emi" required placeholder="e.g. 14780" value="<?php echo htmlspecialchars($applicant['emi'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="required">Sanction Date</label>
                            <input type="date" name="sanction_date" id="sanction_date" required value="<?php echo htmlspecialchars($applicant['sanction_date'] ?? date('Y-m-d')); ?>">
                        </div>

                        <div class="form-group">
                            <label>Attach Official Sanction Letter (PDF / Img)</label>
                            <input type="file" name="sanction_letter" id="sanction_letter" accept=".pdf,.png,.jpg,.jpeg">
                        </div>
                    </div>
                    
                    <div style="display:flex; justify-content:flex-end; padding-top: 14px; border-top: 1px solid #f1f5f9;">
                        <button type="submit" class="btn-obsidian" id="save-feedback-btn">
                            <i data-lucide="save" style="width:14px; height:14px;"></i> Save Official Sanction Terms
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>
</div>

<!-- 🌟 SANCTION LETTER IN-BROWSER PREVIEW MODAL -->
<div id="sanction-letter-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:99999; align-items:center; justify-content:center; padding:20px; backdrop-filter:blur(3px);">
    <div style="background:#fff; width:900px; max-width:96vw; height:88vh; border-radius:12px; display:flex; flex-direction:column; overflow:hidden; box-shadow:0 20px 40px rgba(0,0,0,0.3);">
        <div style="background:#0f172a; padding:14px 20px; color:#fff; display:flex; justify-content:space-between; align-items:center;">
            <div style="display:flex; align-items:center; gap:8px;">
                <i data-lucide="file-text" style="width:18px; height:18px;"></i>
                <h3 id="sanction-modal-title" style="margin:0; font-size:15px; font-weight:800; color:#fff;">Official Bank Sanction Letter</h3>
            </div>
            <div style="display:flex; align-items:center; gap:10px;">
                <a id="sanction-modal-download" href="#" target="_blank" class="btn-obsidian" style="padding:4px 10px; font-size:11px; background:#27272a !important; border-color:#3f3f46 !important;">
                    <i data-lucide="download" style="width:12px; height:12px;"></i> Download
                </a>
                <button type="button" onclick="closeSanctionModal()" style="background:none; border:none; color:#a1a1aa; cursor:pointer; font-size:22px; line-height:1;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#a1a1aa'">&times;</button>
            </div>
        </div>
        <div style="flex:1; background:#f1f5f9; position:relative;">
            <iframe id="sanction-modal-frame" src="" style="width:100%; height:100%; border:none;"></iframe>
        </div>
    </div>
</div>

<!-- 🌟 DETAILED EMAIL BANKER MODAL (SMART AUTO-POPULATION) -->
<div id="email-modal" onclick="closeEmailModalOutside(event)" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); z-index:9999; align-items:center; justify-content:center; backdrop-filter: blur(4px);">
    <div id="email-modal-content" style="background:#fff; width:700px; min-width:500px; min-height:400px; border-radius:12px; box-shadow:0 10px 25px rgba(0,0,0,0.2); overflow:hidden; display:flex; flex-direction:column; max-height: 90vh; max-width: 95vw; resize:both;">
        
        <!-- Header -->
        <div style="background:#0f172a; padding:16px 24px; color:#fff; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:16px; font-weight:800; display:flex; align-items:center; gap:8px;">
                <i data-lucide="send" style="color:#fff; width:18px; height:18px;"></i> Dispatch File to Eligible Banker
            </h3>
            <button type="button" onclick="closeEmailModal()" style="background:none; border:none; color:#94a3b8; cursor:pointer; padding:4px;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#94a3b8'"><i data-lucide="x" style="width:20px; height:20px;"></i></button>
        </div>

        <div style="padding:24px; overflow-y:auto; flex:1;">
            <!-- Attachment Preview Box -->
            <div style="background:#f8fafc; border:1px dashed #cbd5e1; border-radius:8px; padding:16px; margin-bottom:20px; display:flex; align-items:center; gap:16px;">
                <div style="background:#e2e8f0; width:48px; height:48px; border-radius:8px; display:flex; align-items:center; justify-content:center; color:#475569;">
                    <i data-lucide="file-archive" style="width:24px; height:24px;"></i>
                </div>
                <div>
                    <div style="font-weight:600; color:#1e293b; font-size:14px;">Bundle_<?php echo htmlspecialchars($applicant['loan_id']); ?>_<?php echo preg_replace('/[^A-Za-z0-9_-]/', '_', $applicant['customer_name']); ?>.zip</div>
                    <div style="font-size:12px; color:#64748b; margin-top:4px;">Auto-generated ZIP containing Applicant Profile Summary + <?php echo $doc_count; ?> Uploaded Verified Documents</div>
                </div>
                <div style="margin-left:auto;">
                    <span class="badge badge-success" style="font-size:11px; background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; padding:3px 8px; border-radius:12px;">Ready to Attach</span>
                </div>
            </div>

            <form id="email-banker-form" onsubmit="sendBankerEmail(event)">
                <input type="hidden" name="applicant_id" value="<?php echo $id; ?>">
                
                <div class="form-grid" style="grid-template-columns: 1fr 1fr; gap:16px;">
                    <div class="form-group">
                        <label class="required">Select Eligible Bank</label>
                        <select name="bank_name" id="modal_bank_name" required onchange="handleBankSelectChange()" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:6px;">
                            <option value="">-- Select Eligible Bank --</option>
                            <?php foreach($eligible_banks as $b): ?>
                                <option value="<?php echo htmlspecialchars($b); ?>"><?php echo htmlspecialchars($b); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Banker's Name</label>
                        <input type="text" name="banker_name" id="modal_banker_name" onkeyup="updateSubjectAndBody()" placeholder="e.g. Mr. Sharma" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:6px;">
                    </div>

                    <div class="form-group">
                        <label class="required">To (Banker's Email)</label>
                        <input type="email" name="banker_email" id="modal_banker_email" required placeholder="banker@bank.com" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:6px;">
                    </div>
                    <div class="form-group">
                        <label>CC <span style="font-size:11px; color:#64748b;">(Optional)</span></label>
                        <input type="email" name="cc_email" placeholder="manager@bank.com" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:6px;">
                    </div>
                    
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label class="required">Subject</label>
                        <input type="text" name="subject" id="modal_subject" required value="New <?php echo htmlspecialchars($loan_type); ?> Application - <?php echo htmlspecialchars($applicant['customer_name']); ?> [ID: <?php echo htmlspecialchars($applicant['loan_id']); ?>]" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:6px; font-weight:600;">
                    </div>
                    
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label class="required">Email Body</label>
                        <textarea name="body" id="modal_body" rows="7" required style="width:100%; padding:12px; border:1px solid var(--border); border-radius:6px; font-family:inherit; line-height:1.5;">Dear Sir/Madam,

Please find attached the bundled ZIP file containing the complete KYC and property documents for the loan application of <?php echo htmlspecialchars($applicant['customer_name']); ?>.

Applicant Name: <?php echo htmlspecialchars($applicant['customer_name']); ?>
Loan Type: <?php echo htmlspecialchars($loan_type); ?>
Requested Amount: INR <?php echo number_format($loan_amount); ?>
Total Documents Attached: <?php echo $doc_count; ?>

Kindly review the file and let us know the sanction details.

Regards,
BFS Financial Services Sourcing Team</textarea>
                    </div>
                </div>
            </form>
        </div>

        <!-- Footer -->
        <div style="background:#f8fafc; padding:16px 24px; border-top:1px solid #e2e8f0; display:flex; justify-content:flex-end; gap:12px;">
            <button type="button" class="btn-slate-outline" onclick="closeEmailModal()">Cancel</button>
            <button type="submit" form="email-banker-form" class="btn-obsidian" id="send-email-btn"><i data-lucide="send" style="width:14px;height:14px;"></i> Dispatch Email</button>
        </div>
    </div>
</div>

<!-- 🌟 MANUAL BANK SUBMISSION MODAL (ELIGIBLE BANKS ONLY) -->
<div class="modal-overlay" id="manual-bank-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; justify-content:center; align-items:center;">
    <div class="phase3-card" style="width: 100%; max-width: 440px; background: white; padding: 24px;">
        <h3 style="margin:0 0 16px 0; font-size:16px; font-weight:800; color:#0f172a;">Log Bank Submission</h3>
        <form onsubmit="logBankSubmission(event)">
            <input type="hidden" name="applicant_id" value="<?php echo $id; ?>">
            <div class="form-group" style="margin-bottom:14px;">
                <label class="required">Select Eligible Bank</label>
                <select name="bank_name" required>
                    <option value="">Select Eligible Bank...</option>
                    <?php foreach($eligible_banks as $b): ?>
                        <option value="<?php echo htmlspecialchars($b); ?>"><?php echo htmlspecialchars($b); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:14px;">
                <label>Initial Submission Notes / Handover Details</label>
                <input type="text" name="notes" placeholder="e.g. Physical dossier handed over to BM">
            </div>
            <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:20px;">
                <button type="button" class="btn-slate-outline" onclick="document.getElementById('manual-bank-modal').style.display='none'">Cancel</button>
                <button type="submit" class="btn-obsidian">Log Submission</button>
            </div>
        </form>
    </div>
</div>

<!-- 🌟 ENHANCED UPDATE BANK STATUS & TERMS MODAL -->
<div class="modal-overlay" id="update-status-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; justify-content:center; align-items:center;">
    <div class="phase3-card" style="width: 100%; max-width: 520px; background: white; padding: 24px;">
        <h3 style="margin:0 0 16px 0; font-size:16px; font-weight:800; color:#0f172a;">Update Bank Status &amp; Sanction Terms</h3>
        <form id="update-bank-form" onsubmit="updateBankStatus(event)" enctype="multipart/form-data">
            <input type="hidden" name="assignment_id" id="update-assignment-id">
            
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                <div class="form-group">
                    <label class="required">Submission Status</label>
                    <select name="status" id="update-status-val" required onchange="toggleSanctionFields()">
                        <option value="Pending">Pending</option>
                        <option value="In Underwriting">In Underwriting</option>
                        <option value="Approved">Approved by Bank</option>
                        <option value="Rejected">Rejected by Bank</option>
                        <option value="Customer Rejected">Rejected by Customer</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Underwriting Stage</label>
                    <select name="underwriting_stage" id="update-stage-val">
                        <option value="Logged In">1. Logged In</option>
                        <option value="FI / Field Verification">2. Field Verification</option>
                        <option value="Valuation Done">3. Valuation Done</option>
                        <option value="Credit Underwriting">4. Credit Underwriting</option>
                        <option value="Sanction Approved">5. Sanction Approved</option>
                        <option value="Rejected">6. Rejected</option>
                    </select>
                </div>
            </div>

            <!-- Conditional Sanction Terms block if Approved -->
            <div id="modal-sanction-fields" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px; margin-bottom:12px;">
                <div style="font-size:11px; font-weight:700; color:#0f172a; text-transform:uppercase; margin-bottom:8px;">Sanction Terms Offered By Bank</div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:10px;">
                    <div class="form-group">
                        <label>Sanctioned Amount (₹)</label>
                        <input type="number" name="sanctioned_amount" id="modal_sanctioned_amount" oninput="calculateModalEMI()" placeholder="1500000">
                    </div>
                    <div class="form-group">
                        <label>ROI (% p.a.)</label>
                        <input type="number" step="0.01" name="interest_rate" id="modal_interest_rate" oninput="calculateModalEMI()" placeholder="8.5">
                    </div>
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:10px;">
                    <div class="form-group">
                        <label>Tenure (Months)</label>
                        <input type="number" name="tenure_months" id="modal_tenure_months" oninput="calculateModalEMI()" placeholder="180">
                    </div>
                    <div class="form-group">
                        <label>Calculated EMI (₹)</label>
                        <input type="number" step="0.01" name="emi" id="modal_emi" placeholder="14780">
                    </div>
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                    <div class="form-group">
                        <label>Processing Fee (₹)</label>
                        <input type="number" step="0.01" name="processing_fee" id="modal_processing_fee" placeholder="3500">
                    </div>
                    <div class="form-group">
                        <label>Upload Sanction Letter</label>
                        <input type="file" name="sanction_letter" accept=".pdf,.png,.jpg,.jpeg">
                    </div>
                </div>
            </div>

            <div class="form-group" style="margin-bottom:16px;">
                <label>Reason / Banker Remarks</label>
                <textarea name="rejection_reason" id="update-reason-val" rows="2" placeholder="Underwriter notes, rejection reason, or sanction conditions..."></textarea>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:12px;">
                <button type="button" class="btn-slate-outline" onclick="document.getElementById('update-status-modal').style.display='none'">Cancel</button>
                <button type="submit" class="btn-obsidian">Save Status &amp; Terms</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.ckeditor.com/4.22.1/standard/ckeditor.js"></script>
<script>
// Bankers directory mapping for instant auto-filling
const bankersDirectory = <?php echo json_encode($bankers_by_name); ?>;

document.addEventListener('DOMContentLoaded', () => {
    if (typeof lucide !== 'undefined') lucide.createIcons();

    // Hook up real-time live EMI & Amortization Breakdown
    const pInput = document.getElementById('sanctioned_amount');
    const rInput = document.getElementById('interest_rate');
    const nInput = document.getElementById('tenure_months');
    
    if (pInput && rInput && nInput) {
        pInput.addEventListener('input', updateAmortizationEconomics);
        rInput.addEventListener('input', updateAmortizationEconomics);
        nInput.addEventListener('input', updateAmortizationEconomics);
        updateAmortizationEconomics();
    }

    // Initialize CKEditor for rich email composition
    if (document.getElementById('modal_body')) {
        CKEDITOR.replace('modal_body', {
            height: 180,
            versionCheck: false,
            toolbar: [
                ['Bold', 'Italic', 'Underline'],
                ['NumberedList', 'BulletedList'],
                ['Link', 'Unlink']
            ]
        });
    }
});

// Dynamic Live Amortization Economics Widget Calculation
function updateAmortizationEconomics() {
    let p = parseFloat(document.getElementById('sanctioned_amount')?.value || <?php echo $cur_sanctioned > 0 ? $cur_sanctioned : $loan_amount; ?>);
    let r = parseFloat(document.getElementById('interest_rate')?.value || <?php echo (float)($applicant['interest_rate'] ?? 8.5); ?>);
    let n = parseFloat(document.getElementById('tenure_months')?.value || <?php echo (int)($applicant['tenure_months'] ?? 180); ?>);
    const emiInput = document.getElementById('emi');

    if (isNaN(p) || p <= 0) p = <?php echo $loan_amount; ?>;
    if (isNaN(r) || r <= 0) r = 8.5;
    if (isNaN(n) || n <= 0) n = 180;

    let emi = 0;
    if (r === 0) {
        emi = Math.round(p / n);
    } else {
        const monthlyRate = (r / 12) / 100;
        emi = Math.round((p * monthlyRate * Math.pow(1 + monthlyRate, n)) / (Math.pow(1 + monthlyRate, n) - 1));
    }

    if (emiInput && (!emiInput.value || emiInput.value === '0')) {
        emiInput.value = emi;
    }

    const totalPayable = emi * n;
    const totalInterest = totalPayable - p;
    const principalPct = Math.round((p / totalPayable) * 100);
    const interestPct = 100 - principalPct;

    const emiDisplay = document.getElementById('calc-emi-display');
    const totalDisplay = document.getElementById('calc-total-payable');
    const pLabel = document.getElementById('calc-principal-label');
    const iLabel = document.getElementById('calc-interest-label');
    const pBar = document.getElementById('calc-bar-principal');
    const iBar = document.getElementById('calc-bar-interest');

    if (emiDisplay) emiDisplay.textContent = '₹' + emi.toLocaleString('en-IN');
    if (totalDisplay) totalDisplay.textContent = '₹' + Math.round(totalPayable).toLocaleString('en-IN');
    if (pLabel) pLabel.textContent = '₹' + Math.round(p).toLocaleString('en-IN') + ' (' + principalPct + '%)';
    if (iLabel) iLabel.textContent = '₹' + Math.round(totalInterest).toLocaleString('en-IN') + ' (' + interestPct + '%)';
    if (pBar) pBar.style.width = principalPct + '%';
    if (iBar) iBar.style.width = interestPct + '%';
}

// Auto-fill banker contact details upon bank selection
function handleBankSelectChange() {
    const bank = document.getElementById('modal_bank_name').value;
    const data = bankersDirectory[bank];
    if (data) {
        if (data.name) document.getElementById('modal_banker_name').value = data.name;
        if (data.email) document.getElementById('modal_banker_email').value = data.email;
    }
    updateSubjectAndBody();
}

function updateSubjectAndBody() {
    const bank = document.getElementById('modal_bank_name')?.value || '';
    const bName = document.getElementById('modal_banker_name')?.value || '';
    const subjEl = document.getElementById('modal_subject');
    const bodyEl = document.getElementById('modal_body');
    
    const cName = "<?php echo addslashes($applicant['customer_name']); ?>";
    const lType = "<?php echo addslashes($loan_type); ?>";
    const lId = "<?php echo addslashes($applicant['loan_id']); ?>";
    const amt = "<?php echo number_format($loan_amount); ?>";
    const docs = "<?php echo $doc_count; ?>";

    let subj = `New ${lType} Application - ${cName} [ID: ${lId}]`;
    if (bank) subj += ` for ${bank}`;
    if (subjEl) subjEl.value = subj;

    let salutation = bName ? `Dear ${bName},` : `Dear Sir/Madam,`;
    
    const newBody = `${salutation}<br><br>
Please find attached the bundled ZIP dossier containing complete KYC and verified financials for the loan application of <strong>${cName}</strong>.<br><br>
<strong>Applicant Name:</strong> ${cName}<br>
<strong>Loan Type:</strong> ${lType}<br>
<strong>Requested Amount:</strong> INR ${amt}<br>
<strong>Verified Documents Attached:</strong> ${docs} documents<br><br>
Kindly process the underwriting and share sanction details.<br><br>
Regards,<br>
BFS Financial Services Sourcing Team`;

    if (window.CKEDITOR && CKEDITOR.instances.modal_body) {
        CKEDITOR.instances.modal_body.setData(newBody);
    } else if (bodyEl) {
        bodyEl.value = newBody.replace(/<br>/g, "\n").replace(/<[^>]+>/g, '');
    }
}

// Modal open/close helpers
function openEmailModal() {
    document.getElementById('email-modal').style.display = 'flex';
    if(window.lucide) lucide.createIcons();
}
function closeEmailModalOutside(e) {
    if (e.target.id === 'email-modal') closeEmailModal();
}
function closeEmailModal() {
    document.getElementById('email-modal').style.display = 'none';
}

// Preview Sanction Letter in modal
function previewSanctionLetter(filePath, bankTitle) {
    document.getElementById('sanction-modal-title').textContent = 'Official Sanction Letter — ' + bankTitle;
    document.getElementById('sanction-modal-download').href = filePath;
    document.getElementById('sanction-modal-frame').src = filePath;
    document.getElementById('sanction-letter-modal').style.display = 'flex';
}
function closeSanctionModal() {
    document.getElementById('sanction-letter-modal').style.display = 'none';
    document.getElementById('sanction-modal-frame').src = '';
}

// Save Bank Login Fee
async function saveLoginFee(e) {
    e.preventDefault();
    const form = document.getElementById('login-fee-form');
    const formData = new FormData(form);
    const btn = document.getElementById('save-login-fee-btn');
    
    btn.disabled = true;
    btn.innerHTML = 'Saving Payment...';
    
    try {
        const res = await fetch('api.php?api=save_login_fee', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        
        if (res.ok && data.success) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Payment Saved',
                    text: data.message || 'Login fee successfully recorded.',
                    confirmButtonColor: '#0f172a'
                }).then(() => location.reload());
            } else {
                alert(data.message || 'Payment saved successfully.');
                location.reload();
            }
        } else {
            alert(data.error || 'Failed to save login fee');
        }
    } catch(err) {
        alert('Network error while saving login fee');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="receipt"></i> Save Login Fee & Issue Confirmation';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }
}

// Send Email directly with ZIP bundle
async function sendBankerEmail(e) {
    e.preventDefault();
    if (window.CKEDITOR && CKEDITOR.instances.modal_body) {
        CKEDITOR.instances.modal_body.updateElement();
    }
    const form = document.getElementById('email-banker-form');
    const formData = new FormData(form);
    const btn = document.getElementById('send-email-btn');
    
    btn.disabled = true;
    btn.innerHTML = 'Generating Bundle & Sending...';
    
    try {
        const res = await fetch('api.php?api=email_banker_zip', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        
        if (res.ok && data.success) {
            closeEmailModal();
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Dossier Dispatched',
                    text: data.message || 'Email sent to banker with complete bundle.',
                    confirmButtonColor: '#0f172a'
                }).then(() => location.reload());
            } else {
                alert('Email sent successfully!');
                location.reload();
            }
        } else {
            alert(data.error || 'Failed to send email');
        }
    } catch(err) {
        alert('Network error while sending email');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="send"></i> Dispatch Email';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }
}

// Log manual bank submission
async function logBankSubmission(e) {
    e.preventDefault();
    const fd = new FormData(e.target);
    try {
        const res = await fetch('api.php?api=assign_applicant_bank', { method: 'POST', body: fd });
        const data = await res.json();
        if(data.success) location.reload();
        else alert(data.error || 'Error logging bank submission');
    } catch(err) { alert('Error logging submission'); }
}

// Update Bank Status & Sanction Terms Modal
function openUpdateStatusModal(id, currentStatus, reason, stage, amt, roi, tenure, emi, pf) {
    document.getElementById('update-assignment-id').value = id;
    document.getElementById('update-status-val').value = currentStatus;
    document.getElementById('update-reason-val').value = reason || '';
    if (stage) document.getElementById('update-stage-val').value = stage;
    if (amt && amt > 0) document.getElementById('modal_sanctioned_amount').value = amt;
    if (roi && roi > 0) document.getElementById('modal_interest_rate').value = roi;
    if (tenure && tenure > 0) document.getElementById('modal_tenure_months').value = tenure;
    if (emi && emi > 0) document.getElementById('modal_emi').value = emi;
    if (pf && pf > 0) document.getElementById('modal_processing_fee').value = pf;

    toggleSanctionFields();
    document.getElementById('update-status-modal').style.display = 'flex';
}

function toggleSanctionFields() {
    const status = document.getElementById('update-status-val').value;
    const fields = document.getElementById('modal-sanction-fields');
    if (status === 'Approved' || status === 'In Underwriting') {
        fields.style.display = 'block';
    } else {
        fields.style.display = 'none';
    }
}

function calculateModalEMI() {
    const p = parseFloat(document.getElementById('modal_sanctioned_amount').value);
    const r = parseFloat(document.getElementById('modal_interest_rate').value);
    const n = parseFloat(document.getElementById('modal_tenure_months').value);
    const emiInput = document.getElementById('modal_emi');

    if (isNaN(p) || isNaN(r) || isNaN(n) || p <= 0 || n <= 0) return;

    if (r === 0) {
        emiInput.value = Math.round(p / n);
        return;
    }

    const monthlyRate = (r / 12) / 100;
    const emi = (p * monthlyRate * Math.pow(1 + monthlyRate, n)) / (Math.pow(1 + monthlyRate, n) - 1);
    emiInput.value = Math.round(emi);
}

async function updateBankStatus(e) {
    e.preventDefault();
    const fd = new FormData(e.target);
    try {
        const res = await fetch('api.php?api=update_bank_assignment', { method: 'POST', body: fd });
        const data = await res.json();
        if(data.success) {
            location.reload();
        } else {
            alert(data.error || 'Failed to update bank status');
        }
    } catch(err) { alert('Error updating status'); }
}

// 🌟 Accept Bank Sanction Offer
async function acceptBankOffer(assignmentId, bankName) {
    if (typeof Swal !== 'undefined') {
        const result = await Swal.fire({
            title: 'Accept Sanction Offer?',
            text: `Confirm accepting the official loan sanction from ${bankName}? This will lock ${bankName} terms into Dharmendra Bhaskar's profile.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#0f172a',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, Accept Offer'
        });
        if (!result.isConfirmed) return;
    } else {
        if (!confirm(`Accept sanction offer from ${bankName}?`)) return;
    }

    const fd = new FormData();
    fd.append('assignment_id', assignmentId);
    fd.append('applicant_id', <?php echo $id; ?>);

    try {
        const res = await fetch('api.php?api=accept_bank_sanction', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Sanction Locked',
                    text: data.message,
                    confirmButtonColor: '#0f172a'
                }).then(() => location.reload());
            } else {
                alert(data.message);
                location.reload();
            }
        } else {
            alert(data.error || 'Failed to accept offer');
        }
    } catch(err) {
        alert('Network error accepting offer');
    }
}

// Save Final Official Bank Sanction Feedback
async function saveBankFeedback(e) {
    e.preventDefault();
    const form = document.getElementById('bank-feedback-form');
    const formData = new FormData(form);
    const btn = document.getElementById('save-feedback-btn');
    
    btn.disabled = true;
    btn.innerHTML = 'Saving Terms...';
    
    try {
        const res = await fetch('api.php?api=save_bank_feedback', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        
        if (res.ok && data.success) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Terms Saved',
                    text: data.message || 'Official sanction terms recorded.',
                    confirmButtonColor: '#0f172a'
                }).then(() => location.reload());
            } else {
                alert(data.message || 'Official sanction terms recorded.');
                location.reload();
            }
        } else {
            alert(data.error || 'Failed to save sanction terms');
        }
    } catch(err) {
        alert('Network error while saving sanction terms');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="save"></i> Save Official Sanction Terms';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }
}

// 🌟 Verify and Proceed to Phase 4 Gatekeeper Logic
function verifyAndProceed() {
    const isReady = <?php echo $phase4_ready ? 'true' : 'false'; ?>;
    
    if (isReady) {
        window.location.href = "applicant_bank_assign.php?id=<?php echo $id; ?>";
        return;
    }

    const g1 = <?php echo $gate_submissions_ok ? 'true' : 'false'; ?>;
    const g2 = <?php echo $gate_approval_ok ? 'true' : 'false'; ?>;
    const g3 = <?php echo $gate_offer_accepted ? 'true' : 'false'; ?>;
    const g4 = <?php echo $gate_terms_ok ? 'true' : 'false'; ?>;

    const htmlContent = `
        <div style="text-align:left; font-size:12.5px; line-height:1.6; color:#334155;">
            <p style="margin:0 0 12px 0; font-weight:600; color:#0f172a;">
                Phase 4 (Disbursements) is compliance-locked. To proceed, all 4 banking compliance gates must be cleared:
            </p>
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px; display:flex; flex-direction:column; gap:10px;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <span style="color:${g1 ? '#059669' : '#dc2626'}; font-weight:700;">
                        ${g1 ? '✓' : '✗'} Gate 1: Bank Submission Logged
                    </span>
                    <span style="font-size:11px; font-weight:700; color:${g1 ? '#059669' : '#dc2626'};">
                        ${g1 ? 'Passed' : 'Pending'}
                    </span>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <span style="color:${g2 ? '#059669' : '#dc2626'}; font-weight:700;">
                        ${g2 ? '✓' : '✗'} Gate 2: At Least 1 Bank Approval Issued
                    </span>
                    <span style="font-size:11px; font-weight:700; color:${g2 ? '#059669' : '#dc2626'};">
                        ${g2 ? 'Passed' : 'Pending'}
                    </span>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <span style="color:${g3 ? '#059669' : '#dc2626'}; font-weight:700;">
                        ${g3 ? '✓' : '✗'} Gate 3: Sanction Offer Accepted &amp; Locked
                    </span>
                    <span style="font-size:11px; font-weight:700; color:${g3 ? '#059669' : '#dc2626'};">
                        ${g3 ? 'Passed' : 'Pending'}
                    </span>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <span style="color:${g4 ? '#059669' : '#dc2626'}; font-weight:700;">
                        ${g4 ? '✓' : '✗'} Gate 4: Official Terms (CIBIL, Sanctioned Amt, ROI, EMI)
                    </span>
                    <span style="font-size:11px; font-weight:700; color:${g4 ? '#059669' : '#dc2626'};">
                        ${g4 ? 'Passed' : 'Pending'}
                    </span>
                </div>
            </div>
            <div style="margin-top:12px; font-size:11.5px; color:#64748b; background:#f1f5f9; padding:8px 10px; border-radius:6px; line-height:1.4;">
                💡 <em>Action Plan: Use <strong>'+ Log Bank Submission'</strong> to record bank dispatch, update status to <strong>'Approved'</strong>, and click <strong>'Accept Offer'</strong> to unlock Phase 4.</em>
            </div>
        </div>
    `;

    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Phase 4 Gatekeeper Locked',
            html: htmlContent,
            icon: 'warning',
            confirmButtonText: 'Understood, Complete Steps',
            confirmButtonColor: '#0f172a'
        });
    } else {
        alert("Phase 4 is locked until at least one bank approves and sanction terms are officially accepted.");
    }
}

// On-load notification if user attempted direct URL access to Phase 4
<?php if (isset($_GET['phase4_locked'])): ?>
document.addEventListener('DOMContentLoaded', () => {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Phase 4 is Locked',
            text: 'You cannot open Disbursements until at least one bank approves and sanction terms are officially accepted in Phase 3.',
            icon: 'warning',
            confirmButtonColor: '#0f172a'
        });
    }
});
<?php endif; ?>
</script>

<?php require_once 'footer.php'; ?>
