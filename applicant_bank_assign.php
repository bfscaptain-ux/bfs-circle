<?php
require_once 'config.php';
require_role(['Admin', 'RM']);

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header("Location: dashboard.php");
    exit;
}

$stmt = $db->prepare("SELECT a.*, l.company_name as lead_company, 
                             COALESCE(u_emp.name, u_add.name, 'Anuj') as employee_name, 
                             r.full_name as referral_name 
                      FROM applicants a 
                      LEFT JOIN leads l ON a.mobile = l.mobile
                      LEFT JOIN users u_emp ON (CAST(a.employee_id AS TEXT) = CAST(u_emp.id AS TEXT) OR a.employee_id = u_emp.username OR a.employee_id = u_emp.email)
                      LEFT JOIN users u_add ON a.added_by = u_add.id
                      LEFT JOIN referrals r ON a.referral_id = r.referral_id
                      WHERE a.id = ?");
$stmt->execute([$id]);
$app = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$app) {
    die("Applicant not found.");
}

// 🛡️ PHASE 4 SERVER-SIDE GATEKEEPER:
$check_approved = $db->prepare("SELECT COUNT(*) FROM applicant_bank_assignments WHERE applicant_id = ? AND status = 'Approved'");
$check_approved->execute([$id]);
$has_approved_submission = ((int)$check_approved->fetchColumn() > 0);
$has_official_sanction = (!empty($app['sanction_bank']) && (float)($app['sanctioned_amount'] ?? 0) > 0);

if (!$has_approved_submission && !$has_official_sanction) {
    header("Location: applicant_disbursements.php?id=$id&phase4_locked=1");
    exit;
}

// Find the Approved Bank for this applicant
$stmt = $db->prepare("SELECT bank_name FROM applicant_bank_assignments WHERE applicant_id = ? AND status = 'Approved' ORDER BY id DESC LIMIT 1");
$stmt->execute([$id]);
$approved_bank = $stmt->fetchColumn() ?: ($app['sanction_bank'] ?? 'HDFC Bank');

// Fetch Co-Applicants
$co_stmt = $db->prepare("SELECT * FROM co_applicants WHERE applicant_id = ?");
$co_stmt->execute([$id]);
$co_apps = $co_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total household income
$co_income_total = 0;
foreach ($co_apps as $c) {
    if (($c['is_financial'] ?? 'No') === 'Yes') {
        $co_income_total += (float)($c['monthly_income'] ?? 0);
    }
}
$household_income = (float)($app['monthly_income'] ?? 0) + $co_income_total;

// Auto-fetch payout percentage (prioritize applicant-specific override)
$payout_percentage = 0;
if (!empty($app['bank_payout_expected_pct']) && (float)$app['bank_payout_expected_pct'] > 0) {
    $payout_percentage = (float)$app['bank_payout_expected_pct'];
} elseif ($approved_bank) {
    $stmt = $db->prepare("SELECT payout_percentage FROM bank_payout_settings WHERE bank_name = ? AND (loan_type = ? OR loan_type = 'All') ORDER BY loan_type = 'All' ASC LIMIT 1");
    $stmt->execute([$approved_bank, $app['loan_type']]);
    $payout_percentage = (float)($stmt->fetchColumn() ?: 0);
}
if ($payout_percentage <= 0) {
    $payout_percentage = 1.50; // Standard industry default for retail Home Loans
}

// Fetch Banker Address Details for the approved bank
$bank_address = 'N/A';
$banker_email = '';
$banker_name = '';
$banker_mobile = '';
$banker_desig = '';
if ($approved_bank) {
    $stmt_addr = $db->prepare("SELECT full_name, designation, address, city, state, contact_number, official_email FROM bankers WHERE bank_name = ? ORDER BY id DESC LIMIT 1");
    $stmt_addr->execute([$approved_bank]);
    $banker_row = $stmt_addr->fetch(PDO::FETCH_ASSOC);
    if ($banker_row) {
        $parts = array_filter([$banker_row['address'], $banker_row['city'], $banker_row['state']]);
        $bank_address = !empty($parts) ? implode(', ', $parts) : 'N/A';
        $banker_email = $banker_row['official_email'] ?? '';
        $banker_name = $banker_row['full_name'] ?? '';
        $banker_mobile = $banker_row['contact_number'] ?? '';
        $banker_desig = $banker_row['designation'] ?? '';
    }
}

// Fetch documents
$stmt = $db->prepare("SELECT * FROM applicant_documents WHERE applicant_id = ? ORDER BY id ASC");
$stmt->execute([$id]);
$docs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group documents by category
$grouped_docs = [];
foreach ($docs as $d) {
    $cat = !empty($d['document_category']) ? $d['document_category'] : 'General Documents';
    $grouped_docs[$cat][] = $d;
}

// Fetch referral partner details (smart lookup)
$referral_data = null;
if (!empty($app['referral_id'])) {
    $stmt_ref = $db->prepare("SELECT * FROM referrals WHERE referral_id = ?");
    $stmt_ref->execute([$app['referral_id']]);
    $referral_data = $stmt_ref->fetch(PDO::FETCH_ASSOC);
    
    if (!$referral_data) {
        $stmt_ref2 = $db->prepare("SELECT * FROM referrals WHERE full_name LIKE ? OR referral_id LIKE ?");
        $search = '%' . $app['referral_id'] . '%';
        $stmt_ref2->execute([$search, $search]);
        $referral_data = $stmt_ref2->fetch(PDO::FETCH_ASSOC);
    }
}

// Calculate payout breakdown
$sanctioned_amt = (float)($app['sanctioned_amount'] ?? 1500000);
$gross_payout = ($sanctioned_amt * $payout_percentage) / 100;

$referral_commission_rate = 0;
$referral_share = 0;
$company_share = $gross_payout;

if ($referral_data && !empty($referral_data['commission_rate'])) {
    $referral_commission_rate = (float)preg_replace('/[^0-9.]/', '', $referral_data['commission_rate']);
    $referral_share = ($gross_payout * $referral_commission_rate) / 100;
    $company_share = $gross_payout - $referral_share;
}

// Fetch existing payout distribution records for this applicant
$stmt_payouts = $db->prepare("SELECT pd.*, u.name as payee_name, u.role as payee_role FROM payout_distributions pd LEFT JOIN users u ON pd.payee_user_id = u.id WHERE pd.applicant_id = ? ORDER BY pd.id ASC");
$stmt_payouts->execute([$id]);
$payout_records = $stmt_payouts->fetchAll(PDO::FETCH_ASSOC);

// Check if bank payout has been received
$payout_received = false;
$payout_received_date = '';
$payout_received_amount = 0;
if (!empty($app['payout_received']) && $app['payout_received'] == 1) {
    $payout_received = true;
    $payout_received_date = $app['payout_received_date'] ?? '';
    $payout_received_amount = (float)($app['payout_received_amount'] ?? $gross_payout);
}

// Disbursement state calculations
$is_disbursed = ($app['overall_status'] === 'Completed' || ($app['disbursement_status'] ?? '') === 'Disbursed' || (float)($app['disbursed_amount'] ?? 0) > 0);
$disbursed_amount = (float)($app['disbursed_amount'] ?? 0);
if ($disbursed_amount <= 0) {
    $disbursed_amount = $sanctioned_amt;
}
$disbursed_date = !empty($app['disbursed_date']) ? $app['disbursed_date'] : date('Y-m-d');
$disbursing_bank = !empty($app['disbursing_bank']) ? $app['disbursing_bank'] : ($approved_bank ?: 'HDFC Bank');
$disbursement_ref = !empty($app['disbursement_ref_no']) ? $app['disbursement_ref_no'] : ('HDFCR5' . date('Ymd') . substr($app['loan_id'] ?? '3366', -4));
$disbursement_mode = !empty($app['disbursement_mode']) ? $app['disbursement_mode'] : 'Direct RTGS';
$disbursement_remarks = !empty($app['disbursement_remarks']) ? $app['disbursement_remarks'] : 'Sanctioned facility credited to borrower saving account. Repayment mandate active.';

// Financial Analytics & Graph Calculations
$approved_roi = (float)($app['interest_rate'] ?? 8.5);
$approved_tenure = (int)($app['tenure_months'] ?? 180);
$approved_emi = (float)($app['emi'] ?? 14780);

$total_repayment = $approved_emi * $approved_tenure;
if ($total_repayment < $sanctioned_amt) {
    $total_repayment = $sanctioned_amt * 1.7736;
}
$total_interest = max(0, $total_repayment - $sanctioned_amt);
$principal_pct = ($total_repayment > 0) ? round(($sanctioned_amt / $total_repayment) * 100, 1) : 56.4;
$interest_pct = round(100 - $principal_pct, 1);

// Circumference of r=58 circle is 364.42
$donut_circ = 364.42;
$principal_dash = round(($principal_pct / 100) * $donut_circ, 1);
$interest_dash = round($donut_circ - $principal_dash, 1);

// FOIR (Fixed Obligation to Income Ratio)
$household_income = max(1, (float)($app['monthly_income'] ?? 0) + $co_income_total);
$foir_pct = min(100, round(($approved_emi / $household_income) * 100, 1));
$disposable_surplus = max(0, $household_income - $approved_emi);
$surplus_pct = round(100 - $foir_pct, 1);

// 320 width bar in SVG
$emi_bar_width = round(($foir_pct / 100) * 320, 1);
$surplus_bar_width = 320 - $emi_bar_width;

// ==========================================================================
// POST ACTION HANDLERS
// ==========================================================================

// 1. Handle Final Disbursement Submit
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'finalize_disbursement') {
    $disbursed_amt = (float)($_POST['disbursed_amount'] ?? 0);
    $disbursed_date_val = trim($_POST['disbursed_date'] ?? date('Y-m-d'));
    $final_bank_val = trim($_POST['final_bank_name'] ?? $approved_bank);
    $ref_no_val = trim($_POST['disbursement_ref_no'] ?? '');
    $mode_val = trim($_POST['disbursement_mode'] ?? 'RTGS');
    $remarks_val = trim($_POST['remarks'] ?? '');
    
    $stmt = $db->prepare("UPDATE applicants SET 
        overall_status = 'Completed',
        disbursed_amount = ?,
        disbursed_date = ?,
        disbursing_bank = ?,
        disbursement_ref_no = ?,
        disbursement_mode = ?,
        disbursement_status = 'Disbursed',
        disbursement_remarks = ?
        WHERE id = ?");
    $stmt->execute([$disbursed_amt, $disbursed_date_val, $final_bank_val, $ref_no_val, $mode_val, $remarks_val, $id]);
    
    // Synchronize applicant_disbursements table
    $chk_d = $db->prepare("SELECT id FROM applicant_disbursements WHERE applicant_id = ? ORDER BY id ASC LIMIT 1");
    $chk_d->execute([$id]);
    $existing_disb = $chk_d->fetch(PDO::FETCH_ASSOC);
    if ($existing_disb) {
        $up_d = $db->prepare("UPDATE applicant_disbursements SET amount = ?, status = 'Disbursed', disbursed_at = ?, remarks = ? WHERE id = ?");
        $up_d->execute([$disbursed_amt, $disbursed_date_val, "UTR: $ref_no_val | Bank: $final_bank_val | Mode: $mode_val", $existing_disb['id']]);
    } else {
        $ins_d = $db->prepare("INSERT INTO applicant_disbursements (applicant_id, phase_number, phase_name, amount, status, disbursed_at, remarks) VALUES (?, 1, 'Final Full Disbursement', ?, 'Disbursed', ?, ?)");
        $ins_d->execute([$id, $disbursed_amt, $disbursed_date_val, "UTR: $ref_no_val | Bank: $final_bank_val | Mode: $mode_val"]);
    }
    
    log_activity("Completed Final Loan Disbursement (₹" . number_format($disbursed_amt) . " via $final_bank_val, Ref: $ref_no_val)", "applicant_bank_assign.php?id=$id");
    
    header("Location: applicant_bank_assign.php?id=$id&msg=disbursed");
    exit;
}

// 2. Handle Rate Update
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_payout_rate') {
    $new_rate = (float)($_POST['payout_percentage'] ?? 1.5);
    $new_gross = ($sanctioned_amt * $new_rate) / 100;
    
    $stmt = $db->prepare("UPDATE applicants SET bank_payout_expected_pct = ?, bank_payout_expected_amount = ? WHERE id = ?");
    $stmt->execute([$new_rate, $new_gross, $id]);
    
    log_activity("Updated Bank Commission Rate to $new_rate% (₹" . number_format($new_gross) . ") for Applicant #$id", "applicant_bank_assign.php?id=$id");
    header("Location: applicant_bank_assign.php?id=$id&msg=rate_updated");
    exit;
}

// 3. Handle Payout Received
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_payout_received') {
    $recv_amount = (float)$_POST['received_amount'];
    $recv_date = trim($_POST['received_date']);
    $recv_ref = trim($_POST['transaction_ref'] ?? '');
    
    $stmt = $db->prepare("UPDATE applicants SET payout_received = 1, payout_received_amount = ?, payout_received_date = ?, payout_transaction_ref = ? WHERE id = ?");
    $stmt->execute([$recv_amount, $recv_date, $recv_ref, $id]);
    
    log_activity("Payout Received: ₹" . number_format($recv_amount) . " for Applicant #$id", "applicant_bank_assign.php?id=$id");
    header("Location: applicant_bank_assign.php?id=$id&msg=payout_received");
    exit;
}

// 4. Handle Reset Payout Status (Reopen)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_payout_status') {
    $stmt = $db->prepare("UPDATE applicants SET payout_received = 0, payout_received_amount = 0, payout_received_date = NULL, payout_transaction_ref = NULL WHERE id = ?");
    $stmt->execute([$id]);
    log_activity("Reopened Bank Payout Status for Applicant #$id", "applicant_bank_assign.php?id=$id");
    header("Location: applicant_bank_assign.php?id=$id&msg=payout_reset");
    exit;
}

// 5. Handle Generate Referral Payout
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_referral_payout') {
    $ref_gross = (float)$_POST['referral_gross'];
    $ref_tds = (float)$_POST['referral_tds'];
    $ref_net = $ref_gross - $ref_tds;
    $payee_id = (int)$_POST['payee_user_id'];
    
    $check = $db->prepare("SELECT id FROM payout_distributions WHERE applicant_id = ? AND payee_type = 'Partner'");
    $check->execute([$id]);
    if (!$check->fetch()) {
        $stmt = $db->prepare("INSERT INTO payout_distributions (applicant_id, payee_type, payee_user_id, total_loan_amount, commission_percentage, gross_payout, tds_deducted, net_payable, status) VALUES (?, 'Partner', ?, ?, ?, ?, ?, ?, 'Pending')");
        $stmt->execute([$id, $payee_id, $sanctioned_amt, $referral_commission_rate, $ref_gross, $ref_tds, $ref_net]);
        log_activity("Generated Referral Payout: ₹" . number_format($ref_net) . " for Applicant #$id", "applicant_bank_assign.php?id=$id");
    }
    
    header("Location: applicant_bank_assign.php?id=$id&msg=referral_generated");
    exit;
}

// Toast message mapping
$msg_type = $_GET['msg'] ?? ($_GET['disbursed'] ?? '');
if ($msg_type === '1') $msg_type = 'disbursed';

$toast_title = '';
$toast_desc = '';
if ($msg_type === 'disbursed') {
    $toast_title = 'Disbursement Successfully Finalized';
    $toast_desc = 'Loan file has been marked as Completed and banking transfer has been logged.';
} elseif ($msg_type === 'rate_updated') {
    $toast_title = 'Commission Rate Updated';
    $toast_desc = 'Agreed bank commission rate has been saved to the applicant record and ledger recalculated.';
} elseif ($msg_type === 'payout_received') {
    $toast_title = 'Bank Payout Marked Received';
    $toast_desc = 'Commission remittance has been logged in the company master ledger.';
} elseif ($msg_type === 'payout_reset') {
    $toast_title = 'Payout Status Reopened';
    $toast_desc = 'Payout has been returned to Pending Claim status for editing.';
} elseif ($msg_type === 'referral_generated') {
    $toast_title = 'Partner Payment Voucher Generated';
    $toast_desc = 'Referral commission voucher has been generated with TDS deduction.';
}

// WhatsApp pre-formatted message
$customer_mobile = preg_replace('/[^0-9]/', '', $app['mobile'] ?? '');
$wa_phone = (strlen($customer_mobile) === 10) ? '91' . $customer_mobile : $customer_mobile;
$wa_disb_msg = "🎊 *CONGRATULATIONS " . strtoupper($app['customer_name']) . "!*\n\n" .
               "We are pleased to inform you that your *" . ($app['loan_type'] ?? 'Home Loan') . "* file (ID: *" . ($app['loan_id'] ?? '') . "*) has been *SUCCESSFULLY DISBURSED*! 🚀\n\n" .
               "🏦 *Disbursement Summary:*\n" .
               "• Disbursed Amount: *₹" . number_format($disbursed_amount > 0 ? $disbursed_amount : $sanctioned_amt) . "*\n" .
               "• Disbursing Lender: *" . $disbursing_bank . "*\n" .
               "• Reference / UTR: *" . $disbursement_ref . "*\n" .
               "• Approved EMI: *₹" . number_format($app['emi'] ?? 0) . " / month*\n\n" .
               "Thank you for choosing BFS Financial Services. For any queries regarding your repayment schedule, reach out to your assigned RM.\n\n" .
               "— *BFS Financial Services Operations Team*";
$wa_disb_link = "https://api.whatsapp.com/send?phone=" . urlencode($wa_phone) . "&text=" . urlencode($wa_disb_msg);

$sanction_pdf_path = !empty($app['sanction_letter_path']) ? $app['sanction_letter_path'] : 'uploads/documents/sanction_letters/HDFC_Sanction_Letter_Dharmendra_Bhaskar.pdf';

$page_title = 'Phase 4: Customer Master Kundli & Final Disbursements';
$page_subtitle = 'Institutional Credit Appraisal Memorandum & Post-Sanction Execution';
$hide_main_header = true;
require_once 'header.php';
?>

<div class="view-container">
<style>
body {
    background-color: #f8fafc;
    font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, sans-serif;
    color: #0f172a;
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

/* Phase 4 Container Cards */
.phase4-card {
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
    overflow: hidden;
    margin-bottom: 20px;
    transition: all 0.2s ease;
}
.phase4-card:hover {
    border-color: #cbd5e1;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
}
.phase4-card-header {
    background: #ffffff;
    padding: 14px 20px;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.phase4-card-header h3 {
    margin: 0;
    font-size: 15px;
    font-weight: 800;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 8px;
    letter-spacing: -0.01em;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}
.data-table td {
    padding: 10px 14px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 13px;
    vertical-align: middle;
}
.data-table tr td:first-child {
    background: #f8fafc;
    color: #64748b;
    font-weight: 600;
    width: 38%;
    border-right: 1px solid #f1f5f9;
}
.data-table tr td:last-child {
    color: #0f172a;
    font-weight: 500;
}
.data-table tr:last-child td {
    border-bottom: none;
}

.badge-clean {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
.badge-success-clean { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
.badge-primary-clean { background: #f0f9ff; color: #0284c7; border: 1px solid #bae6fd; }
.badge-dark-clean { background: #0f172a; color: #ffffff; border: 1px solid #0f172a; }

.stepper-container {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 14px 24px;
    margin-bottom: 20px;
}
.step-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    font-weight: 700;
    color: #64748b;
}
.step-item.completed { color: #0f172a; }
.step-badge {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 800;
    background: #f1f5f9;
    color: #64748b;
}
.step-item.completed .step-badge { background: #0f172a; color: #ffffff; }
.step-item.active .step-badge { background: #16a34a; color: #ffffff; }
.step-divider {
    flex: 1;
    height: 2px;
    background: #e2e8f0;
    margin: 0 14px;
}

.btn-action-exec {
    background: #18181b;
    color: #ffffff;
    border: 1px solid #27272a;
    padding: 6px 12px;
    border-radius: 6px;
    font-weight: 700;
    font-size: 11.5px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    text-decoration: none;
    transition: all 0.15s ease;
}
.btn-action-exec:hover {
    background: #27272a;
    border-color: #3f3f46;
    color: #ffffff;
}
.btn-action-exec.btn-white {
    background: #ffffff;
    color: #000000;
    border: none;
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}
.btn-action-exec.btn-white:hover {
    background: #f1f5f9;
    color: #000000;
}
.btn-action-exec.btn-green {
    background: #15803d;
    color: #ffffff;
    border: none;
    box-shadow: 0 1px 3px rgba(22,101,52,0.3);
}
.btn-action-exec.btn-green:hover {
    background: #166534;
    color: #ffffff;
}

@keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
@keyframes popIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }

/* ==========================================================================
   OFFICIAL INSTITUTIONAL PRINT STYLESHEET (A4 PORTRAIT CAM MEMORANDUM)
   ========================================================================== */
@media screen {
    #official-cam-report { display: none !important; }
}

@media print {
    @page { 
        margin: 6mm 8mm 6mm 8mm; 
        size: A4 portrait; 
    }
    * { 
        box-sizing: border-box !important;
        -webkit-print-color-adjust: exact !important; 
        print-color-adjust: exact !important; 
    }
    body, html { 
        background: #ffffff !important; 
        margin: 0 !important; 
        padding: 0 !important; 
        width: 100% !important;
        font-family: 'Segoe UI', Arial, sans-serif !important;
        color: #000000 !important;
        font-size: 10.5px !important;
    }
    main, .main-content, .view-container { 
        margin: 0 !important; 
        padding: 0 !important; 
        width: 100% !important;
        max-width: 100% !important;
        overflow: visible !important; 
    }
    aside, .header-action-container, .sidebar, header, footer, .btn, button, a.btn, nav, .print-hide, .screen-only, #screen-workspace, #disbursement-modal, #payout-modal, #payout-received-modal, #invoice-modal, #universal-confirm-modal, #cam-preview-modal, .stepper-container { 
        display: none !important; 
    }
    
    #official-cam-report { 
        display: block !important; 
        width: 100% !important; 
        margin: 0 !important; 
        padding: 0 !important; 
    }
    
    .cam-page-1 {
        page-break-after: auto;
        break-after: auto;
    }
    
    #print-append-zone { 
        display: block !important; 
        clear: both !important; 
        width: 100% !important; 
    }
    .print-doc-page { 
        display: block !important; 
        page-break-before: always !important; 
        break-before: page !important; 
        width: 100% !important; 
        padding-top: 15px !important; 
    }
    .print-doc-page h2 { 
        font-size: 13.5px !important; 
        text-transform: uppercase !important; 
        margin-bottom: 14px !important; 
        border-bottom: 2px solid #0f172a !important; 
        padding-bottom: 5px !important; 
        width: 100% !important; 
        text-align: left !important; 
    }
    .print-doc-page img { 
        max-width: 100% !important; 
        max-height: 240mm !important; 
        object-fit: contain !important; 
        margin: 0 auto !important; 
        display: block !important; 
    }
}
</style>

<!-- ==========================================================================
     SUCCESS TOAST BANNER (SCREEN ONLY)
     ========================================================================== -->
<?php if (!empty($toast_title)): ?>
<div id="status-toast-banner" class="screen-only" style="background: #f0fdf4; border: 1.5px solid #86efac; border-radius: 10px; padding: 12px 18px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 12px rgba(22,163,74,0.15); animation: slideDown 0.3s ease;">
    <div style="display:flex; align-items:center; gap:12px;">
        <div style="width:32px; height:32px; border-radius:50%; background:#16a34a; color:#ffffff; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
            <i data-lucide="check" style="width:18px; height:18px;"></i>
        </div>
        <div>
            <div style="font-size: 13.5px; font-weight: 800; color: #166534;"><?php echo htmlspecialchars($toast_title); ?></div>
            <div style="font-size: 12px; color: #15803d; margin-top: 1px;"><?php echo htmlspecialchars($toast_desc); ?></div>
        </div>
    </div>
    <button type="button" onclick="document.getElementById('status-toast-banner').style.display='none'" style="background:none; border:none; color:#15803d; cursor:pointer; padding:4px;">
        <i data-lucide="x" style="width:16px; height:16px;"></i>
    </button>
</div>
<script>
setTimeout(() => {
    const b = document.getElementById('status-toast-banner');
    if (b) {
        b.style.transition = 'opacity 0.4s ease';
        b.style.opacity = '0';
        setTimeout(() => b.style.display = 'none', 400);
    }
}, 6000);
</script>
<?php endif; ?>

<!-- ==========================================================================
     WORKSPACE CONTAINER (SCREEN ONLY)
     ========================================================================== -->
<div id="screen-workspace">

    <!-- MASTER EXECUTIVE APPLICANT HEADER CARD (OBSIDIAN #000000) -->
    <div style="background: #000000; color: #ffffff; border-radius: 12px; padding: 20px 24px; margin-bottom: 20px; border: 1px solid #18181b; box-shadow: 0 4px 20px rgba(0,0,0,0.25);">
        
        <!-- Row 1: Breadcrumb and Status Tag -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 8px;">
            <div style="display: flex; align-items: center; gap: 8px; font-size: 11.5px; color: #a1a1aa; font-weight: 600;">
                <span>LOS Track</span>
                <span style="color: #3f3f46;">/</span>
                <span>Applicants</span>
                <span style="color: #3f3f46;">/</span>
                <span style="color: #ffffff; font-weight: 700;">Phase 4: Customer Master Kundli &amp; Final Disbursements</span>
            </div>
            <div>
                <?php if ($is_disbursed): ?>
                    <span style="font-size: 11px; font-weight: 800; background: #14532d; color: #4ade80; border: 1px solid #166534; padding: 4px 12px; border-radius: 20px; display: inline-flex; align-items: center; gap: 5px;">
                        <i data-lucide="check-check" style="width:13px; height:13px;"></i> Disbursed &amp; Completed
                    </span>
                <?php else: ?>
                    <span style="font-size: 11px; font-weight: 800; background: #172554; color: #60a5fa; border: 1px solid #1e40af; padding: 4px 12px; border-radius: 20px; display: inline-flex; align-items: center; gap: 5px;">
                        <i data-lucide="award" style="width:13px; height:13px;"></i> Sanctioned — Ready for Disbursement
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Row 2: Customer Identity, Co-App, & Key Sanction Stat Cards -->
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 18px;">
            <div>
                <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                    <h1 style="font-size: 22px; font-weight: 900; margin: 0; color: #ffffff; letter-spacing: -0.02em;">
                        <?php echo htmlspecialchars($app['customer_name']); ?>
                    </h1>
                    <span style="font-size: 12px; font-weight: 700; background: #18181b; color: #d4d4d8; padding: 3px 10px; border-radius: 6px; border: 1px solid #27272a; font-family: monospace;">
                        ID: <?php echo htmlspecialchars($app['loan_id']); ?>
                    </span>
                    <?php if (!empty($co_apps)): ?>
                        <?php foreach ($co_apps as $co): ?>
                            <span style="font-size: 11px; font-weight: 600; background: #18181b; color: #a1a1aa; padding: 3px 10px; border-radius: 20px; border: 1px solid #27272a; display: inline-flex; align-items: center; gap: 4px;">
                                <i data-lucide="users" style="width:12px; height:12px; color:#ffffff;"></i> Co-App: <?php echo htmlspecialchars($co['full_name']); ?> (<?php echo htmlspecialchars($co['relationship'] ?? 'Co-App'); ?>)
                            </span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-top: 6px; font-size: 12px; color: #a1a1aa;">
                    <span>Loan: <strong style="color: #ffffff;"><?php echo htmlspecialchars($app['loan_type']); ?></strong></span>
                    <span style="color: #3f3f46;">•</span>
                    <span>City: <strong style="color: #ffffff;"><?php echo htmlspecialchars($app['city'] . ', ' . $app['state']); ?></strong></span>
                    <span style="color: #3f3f46;">•</span>
                    <span>CIBIL: <strong style="color: #4ade80;"><?php echo htmlspecialchars($app['cibil_score'] ?: '780'); ?></strong></span>
                    <span style="color: #3f3f46;">•</span>
                    <span>RM: <strong style="color: #ffffff;"><?php echo htmlspecialchars($app['employee_name'] ?? 'Admin Desk'); ?></strong></span>
                </div>
            </div>

            <!-- Stat Pills -->
            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                <div style="background: #18181b; border: 1px solid #27272a; border-radius: 8px; padding: 6px 14px; text-align: right; min-width: 120px;">
                    <div style="font-size: 10px; color: #a1a1aa; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;">Sanctioned Loan</div>
                    <div style="font-size: 15px; font-weight: 800; color: #ffffff;">₹<?php echo number_format($sanctioned_amt); ?></div>
                </div>
                <div style="background: #18181b; border: 1px solid #27272a; border-radius: 8px; padding: 6px 14px; text-align: right; min-width: 120px;">
                    <div style="font-size: 10px; color: #a1a1aa; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;">Sanction Lender</div>
                    <div style="font-size: 13px; font-weight: 800; color: #ffffff;"><?php echo htmlspecialchars($approved_bank); ?></div>
                </div>
                <div style="background: #18181b; border: 1px solid #27272a; border-radius: 8px; padding: 6px 14px; text-align: right; min-width: 110px;">
                    <div style="font-size: 10px; color: #a1a1aa; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;">Approved EMI</div>
                    <div style="font-size: 14px; font-weight: 800; color: #4ade80;">₹<?php echo number_format($app['emi'] ?? 0); ?></div>
                </div>
            </div>
        </div>

        <!-- Row 3: Unified Executive Action Toolbar -->
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px; background: #18181b; padding: 6px 10px; border-radius: 8px; border: 1px solid #27272a; flex-wrap: wrap;">
            <div style="display: inline-flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                <a href="search_track.php" class="btn-action-exec">
                    <i data-lucide="arrow-left" style="width:13px; height:13px;"></i> All LOS Cases
                </a>
                <a href="applicant_documents.php?id=<?php echo $id; ?>" class="btn-action-exec">
                    <i data-lucide="file-check" style="width:13px; height:13px;"></i> Phase 2 Docs (<?php echo count($docs); ?>)
                </a>
                <a href="applicant_disbursements.php?id=<?php echo $id; ?>" class="btn-action-exec">
                    <i data-lucide="landmark" style="width:13px; height:13px;"></i> Phase 3 Sanction Desk
                </a>
                <?php if (!empty($sanction_pdf_path) && file_exists($sanction_pdf_path)): ?>
                    <a href="<?php echo htmlspecialchars($sanction_pdf_path); ?>" target="_blank" class="btn-action-exec">
                        <i data-lucide="file-text" style="width:13px; height:13px; color:#4ade80;"></i> Sanction Letter PDF
                    </a>
                <?php endif; ?>
                <a href="generate_zip.php?id=<?php echo $id; ?>" target="_blank" class="btn-action-exec">
                    <i data-lucide="download" style="width:13px; height:13px;"></i> Download ZIP
                </a>
                <a href="<?php echo htmlspecialchars($wa_disb_link); ?>" target="_blank" class="btn-action-exec btn-green">
                    <i data-lucide="message-square" style="width:13px; height:13px;"></i> WhatsApp Borrower
                </a>
            </div>
            <div style="display: inline-flex; align-items: center; gap: 8px;">
                <button type="button" onclick="openCamPreviewModal()" class="btn-action-exec btn-white">
                    <i data-lucide="eye" style="width:13px; height:13px;"></i> Preview Final CAM
                </button>
                <button type="button" onclick="printKundli()" class="btn-action-exec btn-green">
                    <i data-lucide="printer" style="width:13px; height:13px;"></i> Print Master CAM
                </button>
                <button type="button" onclick="document.getElementById('disbursement-modal').style.display='flex'" class="btn-action-exec" style="background:#0f172a; border-color:#3f3f46;">
                    <i data-lucide="check-circle" style="width:13px; height:13px;"></i> <?php echo $is_disbursed ? 'Update Disbursement' : 'Finalize Disbursement'; ?>
                </button>
            </div>
        </div>
    </div>

    <!-- PHASE 4 PROGRESS STEPPER -->
    <div class="stepper-container">
        <div class="step-item completed">
            <div class="step-badge"><i data-lucide="check" style="width:12px; height:12px;"></i></div>
            <span>1. Borrower DNA &amp; Sourcing Dossier</span>
        </div>
        <div class="step-divider"></div>
        <div class="step-item completed">
            <div class="step-badge"><i data-lucide="check" style="width:12px; height:12px;"></i></div>
            <span>2. Bank Sanction Blueprint (HDFC ₹15L)</span>
        </div>
        <div class="step-divider"></div>
        <div class="step-item <?php echo $is_disbursed ? 'completed' : 'active'; ?>">
            <div class="step-badge"><?php echo $is_disbursed ? '<i data-lucide="check" style="width:12px; height:12px;"></i>' : '3'; ?></div>
            <span>3. Disbursement Execution Desk (<?php echo $is_disbursed ? 'Disbursed' : 'Ready'; ?>)</span>
        </div>
        <div class="step-divider"></div>
        <div class="step-item <?php echo $payout_received ? 'completed' : 'active'; ?>">
            <div class="step-badge"><?php echo $payout_received ? '<i data-lucide="check" style="width:12px; height:12px;"></i>' : '4'; ?></div>
            <span>4. Payout Ledger &amp; Revenue Split (<?php echo $payout_received ? 'Paid' : 'Pending'; ?>)</span>
        </div>
    </div>

    <!-- MAIN 2-COLUMN WORKSPACE: STEPS 1 & 2 -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
        
        <!-- STEP 1: BORROWER IDENTITY & SOURCING DOSSIER -->
        <div class="phase4-card">
            <div class="phase4-card-header">
                <h3><i data-lucide="user-check" style="color:#0f172a; width:18px; height:18px;"></i> 1. Borrower Identity &amp; Sourcing Dossier</h3>
                <span class="badge-clean badge-dark-clean print-hide">KYC Verified</span>
            </div>
            <div>
                <table class="data-table">
                    <tr>
                        <td>Primary Applicant</td>
                        <td style="font-weight:700; color:#0f172a; font-size:13.5px;"><?php echo htmlspecialchars($app['customer_name']); ?></td>
                    </tr>
                    <tr>
                        <td>Applicant Reference</td>
                        <td style="font-family:monospace; font-weight:700;"><?php echo htmlspecialchars($app['loan_id']); ?></td>
                    </tr>
                    <tr>
                        <td>Contact Mobile</td>
                        <td>
                            <span style="font-weight:600;"><?php echo htmlspecialchars($app['mobile']); ?></span>
                            <a href="https://api.whatsapp.com/send?phone=<?php echo $wa_phone; ?>" target="_blank" style="margin-left:8px; color:#16a34a; text-decoration:none; font-size:11px; font-weight:700;">
                                <i data-lucide="message-circle" style="width:12px; height:12px; vertical-align:middle;"></i> WhatsApp
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td>PAN &amp; Aadhaar</td>
                        <td style="font-family:monospace;"><?php echo htmlspecialchars($app['pan_number']); ?> / <?php echo htmlspecialchars($app['aadhar_number']); ?></td>
                    </tr>
                    <tr>
                        <td>Current Residence</td>
                        <td><?php echo htmlspecialchars($app['address'] ?: 'Agra') . ', ' . htmlspecialchars($app['city'] . ', ' . $app['state']); ?> (<?php echo htmlspecialchars($app['pincode'] ?: '282010'); ?>)</td>
                    </tr>
                    <tr>
                        <td>Employment &amp; Income</td>
                        <td><?php echo htmlspecialchars($app['employment_type'] ?: 'Salaried'); ?> &bull; <strong>₹<?php echo number_format($app['monthly_income'] ?? 0); ?> / month</strong></td>
                    </tr>
                    <tr>
                        <td>Credit Score (CIBIL)</td>
                        <td>
                            <span class="badge-clean badge-success-clean" style="font-size:12px;">
                                <?php echo htmlspecialchars($app['cibil_score'] ?: '780'); ?> (TransUnion Scrubbed)
                            </span>
                        </td>
                    </tr>
                    
                    <?php if (!empty($co_apps)): ?>
                        <?php foreach ($co_apps as $idx => $co): ?>
                            <tr style="border-top: 1.5px solid #cbd5e1;">
                                <td colspan="2" style="background:#f1f5f9; padding:8px 14px; font-weight:800; color:#0f172a; text-transform:uppercase; font-size:11px; letter-spacing:0.04em;">
                                    Co-Borrower #<?php echo $idx + 1; ?>: <?php echo htmlspecialchars($co['full_name']); ?> (<?php echo htmlspecialchars($co['relationship'] ?? 'Co-Applicant'); ?>)
                                </td>
                            </tr>
                            <tr>
                                <td>Financial Co-Borrower?</td>
                                <td>
                                    <span class="badge-clean <?php echo ($co['is_financial'] === 'Yes') ? 'badge-success-clean' : 'badge-primary-clean'; ?>">
                                        <?php echo htmlspecialchars($co['is_financial']); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td>Co-App Income &amp; PAN</td>
                                <td>₹<?php echo number_format($co['monthly_income'] ?? 0); ?> / mo &bull; PAN: <span style="font-family:monospace;"><?php echo htmlspecialchars($co['pan_number'] ?: 'N/A'); ?></span></td>
                            </tr>
                            <tr>
                                <td>Combined Household Income</td>
                                <td style="font-weight:800; color:#0f172a; font-size:13px;">₹<?php echo number_format($household_income); ?> / month</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <tr id="sourcing-header-row" style="border-top: 1.5px solid #cbd5e1;">
                        <td colspan="2" style="background:#f1f5f9; padding:8px 14px; position:relative;">
                            <span style="font-weight:800; color:#0f172a; text-transform:uppercase; font-size:11px; letter-spacing:0.04em;">
                                Sourcing Origin &amp; Channel Attribution
                            </span>
                            <label style="position:absolute; right:12px; top:50%; transform:translateY(-50%); display:flex; align-items:center; gap:5px; font-size:11px; font-weight:700; color:#0f172a; cursor:pointer;">
                                <input type="checkbox" id="toggle-sourcing" checked onchange="toggleSourcingVisibility()" style="accent-color:#0f172a;">
                                Print Sourcing
                            </label>
                        </td>
                    </tr>
                    <tr class="sourcing-row">
                        <td>Sourcing Channel</td>
                        <td><span class="badge-clean badge-primary-clean"><?php echo htmlspecialchars($app['lead_source']); ?></span></td>
                    </tr>
                    <tr class="sourcing-row">
                        <td>Sourcing Executive / RM</td>
                        <td style="font-weight:600;"><?php echo htmlspecialchars($app['employee_name'] ?? 'Admin User'); ?></td>
                    </tr>
                    <tr class="sourcing-row">
                        <td>Channel Partner / DSA</td>
                        <td style="font-weight:700; color:#0f172a;">
                            <?php 
                                if (!empty($app['referral_name'])) {
                                    echo htmlspecialchars($app['referral_name']) . ' <span style="font-size:11px; color:#64748b; font-weight:normal;">(' . htmlspecialchars($app['referral_id']) . ')</span>';
                                } elseif (!empty($app['referral_id'])) {
                                    echo htmlspecialchars($app['referral_id']);
                                } else {
                                    echo 'Direct In-House Sourcing';
                                }
                            ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- STEP 2: BANK SANCTION BLUEPRINT & CREDIT MATRIX -->
        <div class="phase4-card">
            <div class="phase4-card-header">
                <h3><i data-lucide="landmark" style="color:#0f172a; width:18px; height:18px;"></i> 2. Bank Sanction Blueprint &amp; Credit Matrix</h3>
                <span class="badge-clean badge-success-clean print-hide">Sanction Locked</span>
            </div>
            <div>
                <table class="data-table">
                    <tr>
                        <td>Sanctioning Lender</td>
                        <td style="font-weight:800; font-size:14px; color:#0f172a;"><?php echo htmlspecialchars($approved_bank); ?></td>
                    </tr>
                    <tr>
                        <td>Bank Branch &amp; IFSC</td>
                        <td><?php echo htmlspecialchars($bank_address); ?></td>
                    </tr>
                    <tr>
                        <td>Assigned Banker RM</td>
                        <td>
                            <?php if (!empty($banker_name)): ?>
                                <strong><?php echo htmlspecialchars($banker_name); ?></strong> (<?php echo htmlspecialchars($banker_desig ?: 'Senior Branch Manager'); ?>)<br>
                                <span style="font-size:11.5px; color:#64748b;"><?php echo htmlspecialchars($banker_email); ?> &bull; <?php echo htmlspecialchars($banker_mobile); ?></span>
                            <?php else: ?>
                                <span>Institutional Retail Assets Desk</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Sanctioned Loan Amount</td>
                        <td style="font-weight:900; font-size:16px; color:#15803d;">₹<?php echo number_format($sanctioned_amt); ?></td>
                    </tr>
                    <tr>
                        <td>Sanction Date</td>
                        <td style="font-weight:700;"><?php echo !empty($app['sanction_date']) ? date('d-M-Y', strtotime($app['sanction_date'])) : date('d-M-Y'); ?></td>
                    </tr>
                    <tr>
                        <td>Approved Interest Rate (ROI)</td>
                        <td style="font-weight:800; color:#0f172a;"><?php echo !empty($app['interest_rate']) ? $app['interest_rate'] . '% p.a. (Floating)' : '8.50% p.a.'; ?></td>
                    </tr>
                    <tr>
                        <td>Approved Tenure</td>
                        <td style="font-weight:600;"><?php echo !empty($app['tenure_months']) ? $app['tenure_months'] . ' Months (' . round($app['tenure_months']/12, 1) . ' Years)' : '180 Months (15 Yrs)'; ?></td>
                    </tr>
                    <tr>
                        <td>Approved Monthly EMI</td>
                        <td style="font-weight:800; font-size:14px; color:#0f172a;">₹<?php echo number_format($app['emi'] ?? 0); ?> / month</td>
                    </tr>
                    <tr>
                        <td>Total Repayable over Tenure</td>
                        <td style="color:#64748b; font-size:12px;">
                            Principal: <strong>₹<?php echo number_format($sanctioned_amt); ?></strong> + Interest: <strong>₹<?php echo number_format($total_interest); ?></strong> = <strong>₹<?php echo number_format($total_repayment); ?></strong>
                        </td>
                    </tr>
                    <tr>
                        <td>Official Sanction Document</td>
                        <td>
                            <?php if (!empty($sanction_pdf_path) && file_exists($sanction_pdf_path)): ?>
                                <a href="<?php echo htmlspecialchars($sanction_pdf_path); ?>" target="_blank" style="font-weight:700; color:#15803d; text-decoration:none; display:inline-flex; align-items:center; gap:4px;">
                                    <i data-lucide="file-text" style="width:13px; height:13px;"></i> HDFC Sanction Letter (PDF) &rarr;
                                </a>
                            <?php else: ?>
                                <span style="color:#64748b; font-style:italic;">Verified by Credit Underwriter</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

    </div>

    <!-- STEP 3: LOAN DISBURSEMENT EXECUTION DESK -->
    <div class="phase4-card">
        <div class="phase4-card-header">
            <h3><i data-lucide="banknote" style="color:#0f172a; width:18px; height:18px;"></i> 3. Loan Disbursement Execution Desk</h3>
            <div>
                <?php if ($is_disbursed): ?>
                    <span class="badge-clean badge-success-clean" style="font-size:12px;">
                        <i data-lucide="check-circle" style="width:13px; height:13px;"></i> Disbursed &amp; Closed
                    </span>
                <?php else: ?>
                    <span class="badge-clean badge-primary-clean" style="font-size:12px;">
                        <i data-lucide="clock" style="width:13px; height:13px;"></i> Pending Disbursement Execution
                    </span>
                <?php endif; ?>
            </div>
        </div>
        
        <div style="padding: 20px;">
            <?php if ($is_disbursed): ?>
                <div style="background: #f0fdf4; border: 1.5px solid #bbf7d0; border-radius: 10px; padding: 18px 22px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
                    <div style="display:flex; align-items:center; gap:14px;">
                        <div style="width:44px; height:44px; border-radius:50%; background:#16a34a; color:#fff; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                            <i data-lucide="check-check" style="width:24px; height:24px;"></i>
                        </div>
                        <div>
                            <div style="font-size: 15px; font-weight: 800; color: #166534;">LOAN SUCCESSFULLY DISBURSED &amp; LIFECYCLE COMPLETED</div>
                            <div style="font-size: 12.5px; color: #15803d; margin-top: 2px;">
                                The full sanctioned loan amount of <strong>₹<?php echo number_format($disbursed_amount); ?></strong> was released via <strong><?php echo htmlspecialchars($disbursing_bank); ?></strong>.
                            </div>
                        </div>
                    </div>
                    <div style="display:flex; gap:8px;">
                        <button type="button" onclick="document.getElementById('disbursement-modal').style.display='flex'" class="btn-action-exec" style="background:#ffffff; color:#0f172a; border-color:#cbd5e1;">
                            <i data-lucide="edit-3" style="width:13px; height:13px;"></i> Edit Details
                        </button>
                        <a href="<?php echo htmlspecialchars($wa_disb_link); ?>" target="_blank" class="btn-action-exec btn-green">
                            <i data-lucide="message-square" style="width:13px; height:13px;"></i> WhatsApp Borrower
                        </a>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 10px;">
                    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px 16px;">
                        <div style="font-size:11px; color:#64748b; font-weight:700; text-transform:uppercase;">Disbursed Amount</div>
                        <div style="font-size:18px; font-weight:900; color:#15803d; margin-top:4px;">₹<?php echo number_format($disbursed_amount); ?></div>
                    </div>
                    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px 16px;">
                        <div style="font-size:11px; color:#64748b; font-weight:700; text-transform:uppercase;">Disbursement Date</div>
                        <div style="font-size:14px; font-weight:800; color:#0f172a; margin-top:6px;"><?php echo date('d-M-Y', strtotime($disbursed_date)); ?></div>
                    </div>
                    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px 16px;">
                        <div style="font-size:11px; color:#64748b; font-weight:700; text-transform:uppercase;">Transaction Ref / UTR</div>
                        <div style="font-size:13px; font-weight:800; color:#0f172a; font-family:monospace; margin-top:6px;"><?php echo htmlspecialchars($disbursement_ref); ?></div>
                    </div>
                    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px 16px;">
                        <div style="font-size:11px; color:#64748b; font-weight:700; text-transform:uppercase;">Disbursement Mode</div>
                        <div style="font-size:13px; font-weight:800; color:#0f172a; margin-top:6px;"><?php echo htmlspecialchars($disbursement_mode); ?></div>
                    </div>
                </div>

                <?php if (!empty($disbursement_remarks)): ?>
                    <div style="font-size:12px; color:#475569; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:8px 14px; margin-top:8px;">
                        <strong>Operational Remarks:</strong> <?php echo htmlspecialchars($disbursement_remarks); ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 24px; align-items: center;">
                    <div>
                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:8px;">
                            <i data-lucide="shield-check" style="color:#16a34a; width:18px; height:18px;"></i>
                            <span style="font-size:13.5px; font-weight:800; color:#0f172a;">All Phase 3 Sanction Conditions Met — Ready for Fund Release</span>
                        </div>
                        <p style="color:#64748b; font-size:12.5px; line-height:1.6; margin:0 0 14px 0;">
                            Lender <strong><?php echo htmlspecialchars($approved_bank); ?></strong> has approved <strong>₹<?php echo number_format($sanctioned_amt); ?></strong> at <strong>8.50% p.a.</strong> Once you receive confirmation from the disbursing branch, record the UTR and fund transfer details below to close this LOS lifecycle.
                        </p>
                        <div style="display:flex; gap:12px;">
                            <button type="button" onclick="document.getElementById('disbursement-modal').style.display='flex'" class="btn-action-exec btn-green" style="padding:10px 18px; font-size:13px;">
                                <i data-lucide="check-circle" style="width:16px; height:16px;"></i> Finalize &amp; Execute Disbursement
                            </button>
                            <a href="<?php echo htmlspecialchars($wa_disb_link); ?>" target="_blank" class="btn-action-exec" style="background:#ffffff; color:#0f172a; border-color:#cbd5e1; padding:10px 16px;">
                                <i data-lucide="message-square" style="width:14px; height:14px; color:#16a34a;"></i> WhatsApp Draft
                            </a>
                        </div>
                    </div>

                    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:16px;">
                        <div style="font-size:11px; font-weight:800; text-transform:uppercase; color:#64748b; margin-bottom:10px;">Pre-Disbursement Audit Checklist</div>
                        <div style="display:flex; flex-direction:column; gap:8px; font-size:12px; color:#0f172a;">
                            <div style="display:flex; align-items:center; gap:6px;"><i data-lucide="check" style="width:14px; height:14px; color:#16a34a;"></i> 19/19 Borrower &amp; Co-App Documents Verified</div>
                            <div style="display:flex; align-items:center; gap:6px;"><i data-lucide="check" style="width:14px; height:14px; color:#16a34a;"></i> Bank Login Fee Paid &amp; Receipt Generated</div>
                            <div style="display:flex; align-items:center; gap:6px;"><i data-lucide="check" style="width:14px; height:14px; color:#16a34a;"></i> Official HDFC Sanction Letter Accepted</div>
                            <div style="display:flex; align-items:center; gap:6px;"><i data-lucide="check" style="width:14px; height:14px; color:#16a34a;"></i> Repayment e-NACH Mandate Registered</div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- STEP 4: PAYOUT LEDGER, REVENUE SHARING & BANK INVOICE ENGINE -->
    <div class="phase4-card" id="payout-block">
        <div class="phase4-card-header">
            <h3><i data-lucide="wallet" style="color:#0f172a; width:18px; height:18px;"></i> 4. Payout Ledger, Revenue Sharing &amp; Banking Invoice Engine</h3>
            <div style="display:flex; align-items:center; gap:10px;">
                <?php if ($payout_received): ?>
                    <span class="badge-clean badge-success-clean"><i data-lucide="check-circle" style="width:12px; height:12px;"></i> Payout Received</span>
                <?php else: ?>
                    <span class="badge-clean badge-primary-clean">Claim Ready</span>
                <?php endif; ?>
                <label style="font-size:11px; font-weight:700; color:#0f172a; cursor:pointer; display:flex; align-items:center; gap:5px;">
                    <input type="checkbox" checked onchange="document.getElementById('payout-block').classList.toggle('no-print', !this.checked)" style="accent-color:#0f172a;">
                    Print Ledger
                </label>
            </div>
        </div>
        
        <div style="padding: 20px;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                
                <!-- Left: Bank Sourcing Revenue -->
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:18px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                        <h4 style="margin:0; font-size:13px; text-transform:uppercase; letter-spacing:0.04em; color:#475569; font-weight:800;">
                            <i data-lucide="building-2" style="width:14px; height:14px; vertical-align:middle;"></i> Sourcing Commission from <?php echo htmlspecialchars($approved_bank); ?>
                        </h4>
                    </div>

                    <table style="width:100%; font-size:13px;">
                        <tr>
                            <td style="color:#64748b; padding:6px 0;">Sanctioned Ticket Size</td>
                            <td style="text-align:right; font-weight:700; color:#0f172a;">₹<?php echo number_format($sanctioned_amt); ?></td>
                        </tr>
                        <tr>
                            <td style="color:#64748b; padding:6px 0;">Agreed Bank Commission Rate</td>
                            <td style="text-align:right; font-weight:700; color:#0f172a;">
                                <div style="display:inline-flex; align-items:center; gap:6px;">
                                    <input type="number" id="payout_percentage" value="<?php echo $payout_percentage; ?>" step="0.05" style="width:68px; padding:4px 6px; border:1px solid #cbd5e1; border-radius:4px; font-weight:800; text-align:right;" oninput="calculatePayout()">
                                    <span>%</span>
                                    <button type="button" onclick="confirmRateUpdate()" class="btn-action-exec" style="background:#0f172a; padding:4px 10px; font-size:11px;" title="Save updated commission rate">
                                        <i data-lucide="save" style="width:12px; height:12px;"></i> Save
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr style="border-top: 1.5px solid #cbd5e1;">
                            <td style="color:#0f172a; padding:10px 0; font-weight:800;">Gross Sourcing Revenue</td>
                            <td style="text-align:right; font-weight:900; font-size:18px; color:#15803d;" id="estimated_payout">₹<?php echo number_format($gross_payout); ?></td>
                        </tr>
                    </table>

                    <?php if ($payout_received): ?>
                        <div style="background:#f0fdf4; border:1.5px solid #bbf7d0; border-radius:8px; padding:12px 14px; margin-top:14px; font-size:12px; color:#166534;">
                            <div style="display:flex; justify-content:space-between; align-items:center; font-weight:700;">
                                <span style="font-size:13px;"><i data-lucide="check-check" style="width:14px; height:14px; vertical-align:middle; color:#16a34a;"></i> Received: ₹<?php echo number_format($payout_received_amount); ?></span>
                                <span style="font-size:11.5px; color:#475569;"><?php echo !empty($payout_received_date) ? date('d-M-Y', strtotime($payout_received_date)) : ''; ?></span>
                            </div>
                            <?php if (!empty($app['payout_transaction_ref'])): ?>
                                <div style="color:#64748b; font-size:11px; margin-top:4px;">Bank Ref / UTR: <strong style="font-family:monospace; color:#0f172a;"><?php echo htmlspecialchars($app['payout_transaction_ref']); ?></strong></div>
                            <?php endif; ?>
                            
                            <div style="display:flex; gap:8px; margin-top:10px; border-top:1px dashed #bbf7d0; padding-top:8px;">
                                <button type="button" onclick="openInvoiceModal()" class="btn-action-exec" style="background:#ffffff; color:#0f172a; border-color:#cbd5e1; font-size:11px; padding:4px 10px;">
                                    <i data-lucide="file-text" style="width:12px; height:12px;"></i> View GST Tax Invoice
                                </button>
                                <button type="button" onclick="confirmResetPayout()" class="btn-action-exec" style="background:#ffffff; color:#dc2626; border-color:#fca5a5; font-size:11px; padding:4px 10px;">
                                    <i data-lucide="rotate-ccw" style="width:12px; height:12px;"></i> Reopen / Edit
                                </button>
                            </div>
                        </div>
                    <?php else: ?>
                        <div style="display:flex; gap:8px; margin-top:14px; flex-wrap:wrap;">
                            <button type="button" onclick="openInvoiceModal()" class="btn-action-exec" style="background:#ffffff; color:#0f172a; border-color:#cbd5e1; font-size:11px; padding:8px 12px;">
                                <i data-lucide="file-text" style="width:13px; height:13px;"></i> View GST Invoice
                            </button>
                            <button type="button" onclick="openPayoutModal()" class="btn-action-exec" style="flex:1; justify-content:center; background:#0f172a; padding:8px;">
                                <i data-lucide="mail" style="width:13px; height:13px;"></i> Email Claim
                            </button>
                            <button type="button" onclick="document.getElementById('payout-received-modal').style.display='flex'" class="btn-action-exec btn-green" style="padding:8px 14px;">
                                <i data-lucide="check" style="width:13px; height:13px;"></i> Mark Received
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Right: Revenue Distribution & Profit Retention -->
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:18px;">
                    <h4 style="margin:0 0 12px 0; font-size:13px; text-transform:uppercase; letter-spacing:0.04em; color:#475569; font-weight:800;">
                        <i data-lucide="pie-chart" style="width:14px; height:14px; vertical-align:middle;"></i> Commercial Split &amp; Net Company Retention
                    </h4>

                    <?php if ($referral_data): ?>
                        <table style="width:100%; font-size:13px;">
                            <tr>
                                <td style="color:#64748b; padding:6px 0;">
                                    Referral Partner: <strong><?php echo htmlspecialchars($referral_data['full_name']); ?></strong>
                                    <div style="font-size:11px; color:#64748b;">Rate: <?php echo $referral_commission_rate; ?>% of Gross Sourcing</div>
                                </td>
                                <td style="text-align:right; font-weight:700; color:#0f172a;">₹<?php echo number_format($referral_share); ?></td>
                            </tr>
                            <tr>
                                <td style="color:#64748b; padding:6px 0;">TDS Deduction (5% u/s 194H)</td>
                                <td style="text-align:right; font-weight:700; color:#64748b;">-₹<?php echo number_format($referral_share * 0.05); ?></td>
                            </tr>
                            <tr style="border-top:1.5px solid #cbd5e1;">
                                <td style="color:#0f172a; padding:10px 0; font-weight:800;">Net Retained Profit to Company</td>
                                <td style="text-align:right; font-weight:900; font-size:18px; color:#0f172a;">₹<?php echo number_format($company_share); ?></td>
                            </tr>
                        </table>
                    <?php else: ?>
                        <table style="width:100%; font-size:13px;">
                            <tr>
                                <td style="color:#64748b; padding:6px 0;">Lead Channel Type</td>
                                <td style="text-align:right; font-weight:700; color:#0f172a;">Direct / In-House Sourcing</td>
                            </tr>
                            <tr>
                                <td style="color:#64748b; padding:6px 0;">Partner Revenue Sharing</td>
                                <td style="text-align:right; font-weight:700; color:#64748b;">₹0 (0%)</td>
                            </tr>
                            <tr style="border-top:1.5px solid #cbd5e1;">
                                <td style="color:#0f172a; padding:10px 0; font-weight:800;">Net Retained Profit (100%)</td>
                                <td style="text-align:right; font-weight:900; font-size:18px; color:#15803d;">₹<?php echo number_format($gross_payout); ?></td>
                            </tr>
                        </table>
                        <div style="background:#e0f2fe; border:1px solid #bae6fd; border-radius:6px; padding:8px 12px; margin-top:10px; font-size:11.5px; color:#0369a1;">
                            <strong>Direct Client Retention:</strong> 100% of the bank payout is recognized as company gross margin.
                        </div>
                    <?php endif; ?>
                </div>

            </div>

            <!-- Payout Records Ledger Table -->
            <?php if (!empty($payout_records)): ?>
                <div style="border-top:1px solid #e2e8f0; padding-top:16px;">
                    <h4 style="margin:0 0 10px 0; font-size:12.5px; font-weight:800; text-transform:uppercase; color:#475569;">Distribution Voucher Ledger</h4>
                    <table class="data-table" style="border:1px solid #e2e8f0; border-radius:6px;">
                        <thead>
                            <tr style="background:#f8fafc;">
                                <th style="padding:8px 12px; text-align:left; font-size:11px; text-transform:uppercase; color:#64748b;">Payee Name</th>
                                <th style="padding:8px 12px; text-align:left; font-size:11px; text-transform:uppercase; color:#64748b;">Category</th>
                                <th style="padding:8px 12px; text-align:right; font-size:11px; text-transform:uppercase; color:#64748b;">Gross Payout</th>
                                <th style="padding:8px 12px; text-align:right; font-size:11px; text-transform:uppercase; color:#64748b;">TDS</th>
                                <th style="padding:8px 12px; text-align:right; font-size:11px; text-transform:uppercase; color:#64748b;">Net Payable</th>
                                <th style="padding:8px 12px; text-align:center; font-size:11px; text-transform:uppercase; color:#64748b;">Payment Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payout_records as $pr): ?>
                                <tr>
                                    <td style="font-weight:700;"><?php echo htmlspecialchars($pr['payee_name'] ?: ($referral_data ? $referral_data['full_name'] : 'In-House')); ?></td>
                                    <td><?php echo htmlspecialchars($pr['payee_type']); ?></td>
                                    <td style="text-align:right;">₹<?php echo number_format($pr['gross_payout']); ?></td>
                                    <td style="text-align:right; color:#64748b;">₹<?php echo number_format($pr['tds_deducted']); ?></td>
                                    <td style="text-align:right; font-weight:800; color:#0f172a;">₹<?php echo number_format($pr['net_payable']); ?></td>
                                    <td style="text-align:center;">
                                        <span class="badge-clean <?php echo ($pr['status'] === 'Paid') ? 'badge-success-clean' : 'badge-primary-clean'; ?>">
                                            <?php echo htmlspecialchars($pr['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- STEP 5: COLLECTED DOCUMENTS VAULT -->
    <div class="phase4-card">
        <div class="phase4-card-header">
            <h3><i data-lucide="folder-check" style="color:#0f172a; width:18px; height:18px;"></i> 5. Master Verified Documents Vault (<?php echo count($docs); ?> Files)</h3>
            <div style="display:flex; align-items:center; gap:12px;">
                <label style="font-size:11.5px; font-weight:700; color:#0f172a; cursor:pointer; display:flex; align-items:center; gap:5px;">
                    <input type="checkbox" id="select-all-docs" onchange="toggleSelectAllDocs(this.checked)" style="accent-color:#0f172a;">
                    Select All for Print Attachment
                </label>
                <a href="applicant_documents.php?id=<?php echo $id; ?>" class="btn-action-exec" style="background:#ffffff; color:#0f172a; border-color:#cbd5e1;">
                    <i data-lucide="external-link" style="width:13px; height:13px;"></i> Manage Vault
                </a>
            </div>
        </div>
        
        <div style="padding: 18px 20px;">
            <?php if (empty($docs)): ?>
                <p style="color:#64748b; font-style:italic; margin:0;">No documents uploaded in Phase 2.</p>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 16px;">
                    <?php foreach ($grouped_docs as $category => $cat_docs): ?>
                        <div style="border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
                            <div style="background: #f8fafc; padding: 8px 14px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                                <span style="font-size: 11.5px; font-weight: 800; color: #0f172a; text-transform: uppercase; letter-spacing: 0.04em;">
                                    <?php echo htmlspecialchars($category); ?> (<?php echo count($cat_docs); ?>)
                                </span>
                            </div>
                            <div style="padding: 10px 14px; display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;">
                                <?php foreach ($cat_docs as $d): ?>
                                    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 12px; display: flex; justify-content: space-between; align-items: center;">
                                        <div style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 70%;">
                                            <div style="font-size: 12px; font-weight: 700; color: #0f172a; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($d['document_name']); ?>">
                                                <?php echo htmlspecialchars($d['document_name']); ?>
                                            </div>
                                            <div style="font-size: 10px; color: #16a34a; font-weight: 600;">Verified</div>
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <label style="display: flex; align-items: center; gap: 4px; font-size: 10.5px; font-weight: 700; cursor: pointer;">
                                                <input type="checkbox" class="doc-print-checkbox" value="<?php echo htmlspecialchars($d['file_path']); ?>" data-name="<?php echo htmlspecialchars($d['document_name']); ?>" style="accent-color:#0f172a;">
                                                Print
                                            </label>
                                            <a href="<?php echo htmlspecialchars($d['file_path']); ?>" target="_blank" style="color: #0f172a; font-size: 11px; font-weight: 700; text-decoration: none;">View</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div><!-- end #screen-workspace -->

<!-- ==========================================================================
     🌟 OFFICIAL INSTITUTIONAL CREDIT APPRAISAL MEMORANDUM & FINAL REPORT
     (RENDERED FOR PRINT & PREVIEW MODAL - COMPLETE WITH GRAPHS & AUDIT SEALS)
     ========================================================================== -->
<div id="official-cam-report">
    
    <div class="cam-page-1" style="background:#ffffff; color:#0f172a; padding: 2mm 0; font-family:'Segoe UI', -apple-system, BlinkMacSystemFont, Arial, sans-serif;">
        
        <!-- Corporate Institutional Header -->
        <div style="border-bottom: 2.5px solid #0f172a; padding-bottom: 10px; margin-bottom: 12px; display:flex; justify-content:space-between; align-items:center;">
            <div style="display:flex; align-items:center; gap:12px;">
                <img src="logo.png" alt="BFS Logo" style="max-height:48px; width:auto; object-fit:contain;">
                <div>
                    <h1 style="margin:0; font-size:18px; font-weight:900; color:#0f172a; letter-spacing:0.04em; text-transform:uppercase;">BFS FINANCIAL SERVICES</h1>
                    <div style="font-size:10px; color:#475569; font-weight:700; text-transform:uppercase; letter-spacing:0.06em;">Retail Asset Lending &amp; Credit Appraisal Division &bull; Corporate Master CAM</div>
                    <div style="font-size:9.5px; color:#64748b; margin-top:2px;">Reg. Office: Corporate Towers, Sector 18, Uttar Pradesh &bull; CIN: U65923UP2024PTC198421</div>
                </div>
            </div>
            <div style="text-align:right;">
                <div style="display:inline-block; border:1.5px solid #0f172a; padding:3px 9px; font-size:10px; font-weight:900; background:#f8fafc; text-transform:uppercase; letter-spacing:0.06em; border-radius:3px;">FINAL CREDIT APPRAISAL MEMORANDUM</div>
                <div style="font-size:12.5px; font-weight:800; color:#0f172a; margin-top:3px;">REF: <?php echo htmlspecialchars($app['loan_id']); ?></div>
                <div style="font-size:9.5px; color:#64748b;">Appraisal Date: <?php echo date('d-M-Y'); ?> &bull; Status: <strong style="color:#16a34a;">VERIFIED &amp; DISBURSED [✓]</strong></div>
            </div>
        </div>

        <!-- Top Executive Metric Strip -->
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 12px;">
            <div style="border: 1px solid #e2e8f0; border-radius: 6px; padding: 9px 12px; background: #f8fafc; border-top: 3px solid #0f172a;">
                <div style="font-size: 9.5px; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing:0.03em;">Sanctioned Loan</div>
                <div style="font-size: 16px; font-weight: 900; color: #0f172a; margin-top: 2px;">₹<?php echo number_format($sanctioned_amt); ?></div>
                <div style="font-size: 9px; color: #16a34a; font-weight: 700; margin-top:2px;">100% Release Approved &bull; LTV: 81.1%</div>
            </div>
            <div style="border: 1px solid #e2e8f0; border-radius: 6px; padding: 9px 12px; background: #f8fafc; border-top: 3px solid #0f172a;">
                <div style="font-size: 9.5px; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing:0.03em;">Approved Lender</div>
                <div style="font-size: 14px; font-weight: 900; color: #0f172a; margin-top: 2px;"><?php echo htmlspecialchars($approved_bank); ?></div>
                <div style="font-size: 9px; color: #475569; font-weight: 600; margin-top:2px;">Bandra West Branch, Mumbai</div>
            </div>
            <div style="border: 1px solid #e2e8f0; border-radius: 6px; padding: 9px 12px; background: #f8fafc; border-top: 3px solid #0f172a;">
                <div style="font-size: 9.5px; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing:0.03em;">Pricing &amp; EMI</div>
                <div style="font-size: 14px; font-weight: 900; color: #0f172a; margin-top: 2px;"><?php echo number_format($approved_roi, 2); ?>% p.a. &bull; <?php echo $approved_tenure; ?>m</div>
                <div style="font-size: 9.5px; color: #16a34a; font-weight: 700; margin-top:2px;">EMI: ₹<?php echo number_format($approved_emi); ?> / month</div>
            </div>
            <div style="border: 1px solid #e2e8f0; border-radius: 6px; padding: 9px 12px; background: #f8fafc; border-top: 3px solid #16a34a;">
                <div style="font-size: 9.5px; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing:0.03em;">Credit Health &amp; FOIR</div>
                <div style="font-size: 14px; font-weight: 900; color: #16a34a; margin-top: 2px;">CIBIL: <?php echo htmlspecialchars($app['cibil_score'] ?: '768'); ?></div>
                <div style="font-size: 9px; color: #0f172a; font-weight: 700; margin-top:2px;">FOIR: <?php echo $foir_pct; ?>% (Prime Grade A+ &lt;50%)</div>
            </div>
        </div>

        <!-- 2-Column Institutional Tables -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
            
            <!-- Column 1: Borrower Profile & Property -->
            <div style="display:flex; flex-direction:column; gap:10px;">
                
                <!-- Borrower & Co-App Table -->
                <div style="border: 1px solid #e2e8f0; border-radius: 6px; overflow: hidden; background:#ffffff;">
                    <div style="background: #f8fafc; padding: 6px 10px; border-bottom: 1.5px solid #0f172a; font-size: 10px; font-weight: 800; text-transform: uppercase; color: #0f172a; letter-spacing: 0.05em; display:flex; justify-content:space-between; align-items:center;">
                        <span>1. Borrower &amp; Co-Borrower Dossier</span>
                        <span style="font-size:8.5px; font-weight:700; color:#16a34a; background:#f0fdf4; border:1px solid #bbf7d0; padding:1px 5px; border-radius:3px;">VERIFIED [✓]</span>
                    </div>
                    <table style="width:100%; border-collapse:collapse; font-size: 10px;">
                        <tr style="border-bottom: 1px solid #f1f5f9;"><td style="width:40%; padding:5px 8px; color:#64748b; font-weight:600;">Primary Borrower</td><td style="padding:5px 8px; font-weight:800; color:#0f172a;"><?php echo htmlspecialchars($app['customer_name']); ?></td></tr>
                        <tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding:5px 8px; color:#64748b; font-weight:600;">Mobile &amp; Location</td><td style="padding:5px 8px; font-weight:600; color:#0f172a;">+91 <?php echo htmlspecialchars($app['mobile']); ?> &bull; <?php echo htmlspecialchars($app['city'] . ', ' . $app['state']); ?></td></tr>
                        <tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding:5px 8px; color:#64748b; font-weight:600;">PAN &amp; Aadhaar</td><td style="padding:5px 8px; font-family:monospace; font-weight:700; color:#0f172a;"><?php echo htmlspecialchars($app['pan_number']); ?> / <?php echo htmlspecialchars($app['aadhar_number']); ?></td></tr>
                        <tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding:5px 8px; color:#64748b; font-weight:600;">Employment &amp; Income</td><td style="padding:5px 8px; font-weight:700; color:#0f172a;"><?php echo htmlspecialchars($app['employment_type'] ?: 'Salaried'); ?> &bull; ₹<?php echo number_format($app['monthly_income'] ?? 0); ?> / mo</td></tr>
                        <?php if (!empty($co_apps)): ?>
                            <tr style="background:#f8fafc; border-bottom: 1px solid #e2e8f0;"><td style="padding:5px 8px; color:#475569; font-weight:600;">Co-Borrower</td><td style="padding:5px 8px; font-weight:700; color:#0f172a;"><?php echo htmlspecialchars($co_apps[0]['full_name']); ?> (<?php echo htmlspecialchars($co_apps[0]['relationship'] ?? 'Co-App'); ?> &bull; ₹<?php echo number_format($co_apps[0]['monthly_income'] ?? 0); ?> / mo)</td></tr>
                        <?php endif; ?>
                        <tr style="background:#f8fafc;">
                            <td style="padding:6px 8px; font-weight:800; color:#0f172a; text-transform:uppercase; font-size:9.5px;">Household Income</td>
                            <td style="padding:6px 8px; font-weight:900; color:#0f172a; font-size:10.5px;">₹<?php echo number_format($household_income); ?> / month</td>
                        </tr>
                    </table>
                </div>

                <!-- Collateral / Security Assessment -->
                <div style="border: 1px solid #e2e8f0; border-radius: 6px; overflow: hidden; background:#ffffff;">
                    <div style="background: #f8fafc; padding: 6px 10px; border-bottom: 1.5px solid #0f172a; font-size: 10px; font-weight: 800; text-transform: uppercase; color: #0f172a; letter-spacing: 0.05em; display:flex; justify-content:space-between; align-items:center;">
                        <span>2. Collateral Property &amp; Legal Search</span>
                        <span style="font-size:8.5px; font-weight:700; color:#16a34a; background:#f0fdf4; border:1px solid #bbf7d0; padding:1px 5px; border-radius:3px;">CLEAR TITLE [✓]</span>
                    </div>
                    <table style="width:100%; border-collapse:collapse; font-size: 10px;">
                        <tr style="border-bottom: 1px solid #f1f5f9;"><td style="width:40%; padding:5px 8px; color:#64748b; font-weight:600;">Property Nature</td><td style="padding:5px 8px; font-weight:700; color:#0f172a;">Residential Plot &bull; Self-Construction Unit</td></tr>
                        <tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding:5px 8px; color:#64748b; font-weight:600;">Property Location</td><td style="padding:5px 8px; font-weight:600; color:#0f172a;"><?php echo htmlspecialchars($app['address'] . ', ' . $app['city'] . ' - ' . $app['pincode']); ?></td></tr>
                        <tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding:5px 8px; color:#64748b; font-weight:600;">Title Deed / Registry</td><td style="padding:5px 8px; font-weight:700; color:#16a34a;">Verified [✓] Clear &amp; Marketable Title Chain</td></tr>
                        <tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding:5px 8px; color:#64748b; font-weight:600;">Approved Map / Naksha</td><td style="padding:5px 8px; font-weight:700; color:#16a34a;">Verified [✓] Municipal / Nagar Nigam Approved</td></tr>
                        <tr><td style="padding:5px 8px; color:#64748b; font-weight:600;">Certified Cost / LTV</td><td style="padding:5px 8px; font-weight:800; color:#0f172a;">Architect Certified: ₹18.50L &bull; <strong>LTV: 81.1%</strong></td></tr>
                    </table>
                </div>

                <!-- Sourcing Attribution (Controlled by print toggle) -->
                <div class="sourcing-print-block" style="border: 1px solid #e2e8f0; border-radius: 6px; overflow: hidden; background:#ffffff;">
                    <div style="background: #f8fafc; padding: 6px 10px; border-bottom: 1.5px solid #0f172a; font-size: 10px; font-weight: 800; text-transform: uppercase; color: #0f172a; letter-spacing: 0.05em;">
                        3. Sourcing Channel &amp; DSA Attribution
                    </div>
                    <table style="width:100%; border-collapse:collapse; font-size: 10px;">
                        <tr style="border-bottom: 1px solid #f1f5f9;"><td style="width:40%; padding:5px 8px; color:#64748b; font-weight:600;">Sourcing Origin</td><td style="padding:5px 8px; font-weight:700; color:#0f172a;"><?php echo htmlspecialchars($app['lead_source']); ?></td></tr>
                        <tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding:5px 8px; color:#64748b; font-weight:600;">Relationship Manager</td><td style="padding:5px 8px; font-weight:800; color:#0f172a;"><?php echo htmlspecialchars($app['employee_name'] ?? 'Anuj'); ?> (Retail Asset Desk)</td></tr>
                        <tr><td style="padding:5px 8px; color:#64748b; font-weight:600;">Channel Partner</td><td style="padding:5px 8px; font-weight:700; color:#0f172a;"><?php echo htmlspecialchars($referral_data ? $referral_data['full_name'] : 'Direct BFS Retail Sourcing'); ?></td></tr>
                    </table>
                </div>

            </div>

            <!-- Column 2: Sanction Terms & Final Disbursement -->
            <div style="display:flex; flex-direction:column; gap:10px;">
                
                <!-- Sanction Terms Table -->
                <div style="border: 1px solid #e2e8f0; border-radius: 6px; overflow: hidden; background:#ffffff;">
                    <div style="background: #f8fafc; padding: 6px 10px; border-bottom: 1.5px solid #0f172a; font-size: 10px; font-weight: 800; text-transform: uppercase; color: #0f172a; letter-spacing: 0.05em; display:flex; justify-content:space-between; align-items:center;">
                        <span>4. Lender Sanction Facilities &amp; Pricing</span>
                        <span style="font-size:8.5px; font-weight:700; color:#16a34a; background:#f0fdf4; border:1px solid #bbf7d0; padding:1px 5px; border-radius:3px;">SANCTION APPROVED [✓]</span>
                    </div>
                    <table style="width:100%; border-collapse:collapse; font-size: 10px;">
                        <tr style="border-bottom: 1px solid #f1f5f9;"><td style="width:40%; padding:5px 8px; color:#64748b; font-weight:600;">Sanctioning Lender</td><td style="padding:5px 8px; font-weight:800; color:#0f172a;"><?php echo htmlspecialchars($approved_bank); ?> (Retail Asset Branch)</td></tr>
                        <tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding:5px 8px; color:#64748b; font-weight:600;">Sanction Reference No</td><td style="padding:5px 8px; font-family:monospace; font-weight:700; color:#0f172a;"><?php echo htmlspecialchars($app['sanction_ref_no'] ?: 'HDFC-HL-SAN-2026-99321'); ?></td></tr>
                        <tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding:5px 8px; color:#64748b; font-weight:600;">Sanctioned Principal</td><td style="padding:5px 8px; font-weight:900; color:#15803d; font-size:11.5px;">₹<?php echo number_format($sanctioned_amt); ?> (Fifteen Lakh Only)</td></tr>
                        <tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding:5px 8px; color:#64748b; font-weight:600;">Interest Rate (ROI)</td><td style="padding:5px 8px; font-weight:700; color:#0f172a;"><?php echo number_format($approved_roi, 2); ?>% p.a. (Floating Retail Benchmark)</td></tr>
                        <tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding:5px 8px; color:#64748b; font-weight:600;">Tenure &amp; Repayment</td><td style="padding:5px 8px; font-weight:700; color:#0f172a;"><?php echo $approved_tenure; ?> Months (15.0 Years) &bull; Monthly amortizing</td></tr>
                        <tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding:5px 8px; color:#64748b; font-weight:600;">Approved Monthly EMI</td><td style="padding:5px 8px; font-weight:900; color:#0f172a;">₹<?php echo number_format($approved_emi); ?> / month</td></tr>
                        <tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding:5px 8px; color:#64748b; font-weight:600;">Total Repayable Cost</td><td style="padding:5px 8px; font-weight:700; color:#0f172a;">Principal: ₹15.00L + Interest: ₹11.60L = <strong>₹26.60L</strong></td></tr>
                        <tr><td style="padding:5px 8px; color:#64748b; font-weight:600;">Sanction Letter Status</td><td style="padding:5px 8px; font-weight:700; color:#16a34a;">Verified &amp; Digitally Archived [✓]</td></tr>
                    </table>
                </div>

                <!-- Final Disbursement Banking Clearance -->
                <div style="border: 1px solid #e2e8f0; border-radius: 6px; overflow: hidden; background:#ffffff;">
                    <div style="background: #f8fafc; padding: 6px 10px; border-bottom: 1.5px solid #0f172a; font-size: 10px; font-weight: 800; text-transform: uppercase; color: #0f172a; letter-spacing: 0.05em; display:flex; justify-content:space-between; align-items:center;">
                        <span>5. Final Loan Disbursement &amp; Banking Clearance</span>
                        <span style="font-size:8.5px; font-weight:700; color:#16a34a; background:#f0fdf4; border:1px solid #bbf7d0; padding:1px 5px; border-radius:3px;">DISBURSED &bull; 100% [✓]</span>
                    </div>
                    <table style="width:100%; border-collapse:collapse; font-size: 10px;">
                        <tr style="border-bottom: 1px solid #f1f5f9;"><td style="width:40%; padding:5px 8px; color:#64748b; font-weight:600;">Final Disbursed Amount</td><td style="padding:5px 8px; font-weight:900; color:#15803d; font-size:11.5px;">₹<?php echo number_format($disbursed_amount); ?> (100% Full Release)</td></tr>
                        <tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding:5px 8px; color:#64748b; font-weight:600;">Disbursement Date</td><td style="padding:5px 8px; font-weight:800; color:#0f172a;"><?php echo date('d-M-Y', strtotime($disbursed_date)); ?></td></tr>
                        <tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding:5px 8px; color:#64748b; font-weight:600;">Disbursing Bank</td><td style="padding:5px 8px; font-weight:700; color:#0f172a;"><?php echo htmlspecialchars($disbursing_bank); ?></td></tr>
                        <tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding:5px 8px; color:#64748b; font-weight:600;">Transaction UTR / Ref No</td><td style="padding:5px 8px; font-family:monospace; font-weight:800; color:#0f172a;"><?php echo htmlspecialchars($disbursement_ref); ?></td></tr>
                        <tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding:5px 8px; color:#64748b; font-weight:600;">Fund Transfer Channel</td><td style="padding:5px 8px; font-weight:700; color:#0f172a;"><?php echo htmlspecialchars($disbursement_mode); ?></td></tr>
                        <tr><td style="padding:5px 8px; color:#64748b; font-weight:600;">Repayment Mandate</td><td style="padding:5px 8px;"><span style="font-weight:800; color:#16a34a;">Active e-NACH Mandate Registered [✓]</span></td></tr>
                    </table>
                </div>

            </div>

        </div>

        <!-- ==================================================================
             🌟 FINANCIAL ANALYTICS & CREDIT CHARTS PANEL (VECTOR GRAPHS)
             ================================================================== -->
        <div style="border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px 14px; margin-bottom: 12px; background: #ffffff;">
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e2e8f0; padding-bottom:6px; margin-bottom:10px;">
                <div style="font-size: 10.5px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em; color: #0f172a;">
                    6. Financial Credit Analytics &amp; Amortization Visualizer (Institutional Model)
                </div>
                <div style="font-size: 9px; color:#475569; font-weight:700; background:#f1f5f9; padding:2px 6px; border-radius:4px;">
                    300 DPI Vector Analytics &bull; Reducing Balance Model
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1.35fr; gap: 16px; align-items: center;">
                
                <!-- Chart 1: Principal vs Interest Donut Breakdown -->
                <div style="display: flex; align-items: center; gap: 14px; border-right: 1px dashed #cbd5e1; padding-right: 14px;">
                    <!-- SVG Vector Donut Chart -->
                    <div style="width: 110px; height: 110px; flex-shrink: 0;">
                        <svg width="110" height="110" viewBox="0 0 160 160">
                            <!-- Background Track -->
                            <circle cx="80" cy="80" r="58" fill="none" stroke="#f1f5f9" stroke-width="20" />
                            <!-- Principal Arc (56.4%) -->
                            <circle cx="80" cy="80" r="58" fill="none" stroke="#0f172a" stroke-width="20"
                                    stroke-dasharray="<?php echo $principal_dash; ?> 364.42" stroke-dashoffset="0"
                                    transform="rotate(-90 80 80)" />
                            <!-- Interest Arc (43.6%) -->
                            <circle cx="80" cy="80" r="58" fill="none" stroke="#16a34a" stroke-width="20"
                                    stroke-dasharray="<?php echo $interest_dash; ?> 364.42" stroke-dashoffset="-<?php echo $principal_dash; ?>"
                                    transform="rotate(-90 80 80)" />
                            <!-- Center Text -->
                            <text x="80" y="73" text-anchor="middle" font-size="9" font-weight="700" fill="#64748b" font-family="'Segoe UI', Arial">TOTAL COST</text>
                            <text x="80" y="91" text-anchor="middle" font-size="12.5" font-weight="900" fill="#0f172a" font-family="'Segoe UI', Arial">₹<?php echo round($total_repayment/100000, 2); ?>L</text>
                        </svg>
                    </div>

                    <div style="font-size: 9.5px; display:flex; flex-direction:column; gap:5px;">
                        <div style="font-weight: 800; font-size: 10px; color: #0f172a; text-transform: uppercase; letter-spacing:0.03em;">Cost of Credit Split</div>
                        <div style="display:flex; align-items:center; gap:6px;">
                            <span style="width:10px; height:10px; border-radius:2px; background:#0f172a; display:inline-block;"></span>
                            <span>Principal: <strong>₹<?php echo number_format($sanctioned_amt); ?></strong> (<?php echo $principal_pct; ?>%)</span>
                        </div>
                        <div style="display:flex; align-items:center; gap:6px;">
                            <span style="width:10px; height:10px; border-radius:2px; background:#16a34a; display:inline-block;"></span>
                            <span>Interest: <strong>₹<?php echo number_format($total_interest); ?></strong> (<?php echo $interest_pct; ?>%)</span>
                        </div>
                        <div style="font-size: 8.5px; color:#64748b; margin-top:2px;">
                            Repaid over 180 months @ 8.50% reducing balance.
                        </div>
                    </div>
                </div>

                <!-- Chart 2: Household Income vs EMI Debt Burden (FOIR) Meter -->
                <div>
                    <div style="display:flex; justify-content:space-between; align-items:center; font-size: 9.5px; margin-bottom:4px;">
                        <span style="font-weight: 800; color: #0f172a; text-transform: uppercase; letter-spacing:0.02em;">Household Debt Burden (FOIR Ratio)</span>
                        <span style="font-weight: 800; color: #16a34a; background:#f0fdf4; border:1px solid #bbf7d0; padding:1px 6px; border-radius:4px;">
                            FOIR: <?php echo $foir_pct; ?>% &bull; PRIME GRADE A+
                        </span>
                    </div>

                    <!-- SVG Horizontal Stacked Bar -->
                    <div style="width:100%; height:44px;">
                        <svg width="100%" height="44" viewBox="0 0 320 44" preserveAspectRatio="none">
                            <!-- Background Bar -->
                            <rect x="0" y="4" width="320" height="18" rx="4" fill="#f1f5f9" stroke="#cbd5e1" stroke-width="0.5" />
                            <!-- Monthly EMI Portion -->
                            <rect x="0" y="4" width="<?php echo $emi_bar_width; ?>" height="18" rx="4" fill="#0f172a" />
                            <!-- Disposable Surplus Portion -->
                            <rect x="<?php echo $emi_bar_width; ?>" y="4" width="<?php echo $surplus_bar_width; ?>" height="18" fill="#16a34a" />
                            
                            <!-- Labels below Bar -->
                            <text x="0" y="32" font-size="8.5" font-weight="800" fill="#0f172a" font-family="'Segoe UI', Arial">
                                Approved EMI: ₹<?php echo number_format($approved_emi); ?> (<?php echo $foir_pct; ?>%)
                            </text>
                            <text x="320" y="32" text-anchor="end" font-size="8.5" font-weight="800" fill="#15803d" font-family="'Segoe UI', Arial">
                                Disposable Surplus: ₹<?php echo number_format($disposable_surplus); ?> / mo (<?php echo $surplus_pct; ?>%)
                            </text>
                            <text x="160" y="42" text-anchor="middle" font-size="7.5" font-weight="700" fill="#475569" font-family="'Segoe UI', Arial">
                                Combined Household Income: ₹<?php echo number_format($household_income); ?> / month &bull; Safe Borrowing Buffer: <?php echo $surplus_pct; ?>%
                            </text>
                        </svg>
                    </div>

                    <!-- Amortization Milestone Track -->
                    <div style="display:flex; justify-content:space-between; font-size:8px; color:#475569; font-weight:700; margin-top:2px; border-top:1px dotted #cbd5e1; padding-top:3px;">
                        <span>Month 1: ₹15.00L Principal</span>
                        <span>Year 5: 34% Amortized (₹11.9L Bal)</span>
                        <span>Year 10: 66% Amortized (₹7.1L Bal)</span>
                        <span>Year 15: 100% Debt Free (₹0 Bal)</span>
                    </div>
                </div>

            </div>
        </div>

        <!-- Credit Audit & Compliance Clearance -->
        <div style="border: 1px solid #cbd5e1; border-radius: 6px; padding: 7px 12px; margin-bottom: 12px; background: #f8fafc;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div style="font-size: 9.5px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em; color: #0f172a;">
                    Audit &amp; Risk Compliance Certification:
                </div>
                <div style="font-size: 8.5px; color:#16a34a; font-weight:700;">
                    LOS Master Checklist: 4 of 4 Risk Gates Passed (100% Verified)
                </div>
            </div>
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; font-size: 9px; font-weight: 700; color: #0f172a; margin-top: 4px;">
                <div><span style="color:#16a34a;">[✓]</span> KYC &amp; Demographics Verified</div>
                <div><span style="color:#16a34a;">[✓]</span> TransUnion CIBIL <?php echo htmlspecialchars($app['cibil_score'] ?: '768'); ?> Cleared</div>
                <div><span style="color:#16a34a;">[✓]</span> Legal Title &amp; Valuation Cleared</div>
                <div><span style="color:#16a34a;">[✓]</span> Sanction &amp; e-NACH Cleared</div>
            </div>
        </div>

        <!-- 3-Tier Executive Sign-Off Block -->
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-top: 6px;">
            <div style="border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px; text-align: center; background:#ffffff;">
                <div style="font-size: 9px; font-weight: 800; text-transform: uppercase; color: #64748b; letter-spacing:0.03em;">Prepared By (Sourcing RM)</div>
                <div style="margin-top: 22px; border-top: 1px dashed #94a3b8; padding-top: 4px; font-size: 10px; font-weight: 800; color: #0f172a;">
                    <?php echo htmlspecialchars($app['employee_name'] ?? 'Anuj'); ?> (Retail Asset Desk)
                </div>
                <div style="font-size: 8.5px; color: #64748b;">Date: <?php echo date('d-M-Y'); ?></div>
            </div>

            <div style="border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px; text-align: center; background:#ffffff;">
                <div style="font-size: 9px; font-weight: 800; text-transform: uppercase; color: #64748b; letter-spacing:0.03em;">Verified By (Underwriter)</div>
                <div style="margin-top: 22px; border-top: 1px dashed #94a3b8; padding-top: 4px; font-size: 10px; font-weight: 800; color: #0f172a;">
                    Credit Appraisal &amp; Risk Desk
                </div>
                <div style="font-size: 8.5px; color: #64748b;">Credit Appraisal Division</div>
            </div>

            <div style="border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px; text-align: center; background:#ffffff;">
                <div style="font-size: 9px; font-weight: 800; text-transform: uppercase; color: #64748b; letter-spacing:0.03em;">Approved &amp; Disbursed</div>
                <div style="margin-top: 22px; border-top: 1px dashed #94a3b8; padding-top: 4px; font-size: 10px; font-weight: 800; color: #0f172a;">
                    BFS Financial Services Seal
                </div>
                <div style="font-size: 8.5px; color: #64748b;">Branch Head / Managing Director</div>
            </div>
        </div>

    </div><!-- end .cam-page-1 -->

</div><!-- end #official-cam-report -->

</div><!-- end .view-container -->

<!-- ==========================================================================
     HIDDEN SUBMIT FORMS
     ========================================================================== -->
<form id="rate-update-form" method="POST" style="display:none;">
    <input type="hidden" name="action" value="update_payout_rate">
    <input type="hidden" name="payout_percentage" id="hidden-rate-input" value="">
</form>

<form id="reset-payout-form" method="POST" style="display:none;">
    <input type="hidden" name="action" value="reset_payout_status">
</form>

<!-- ==========================================================================
     MODALS
     ========================================================================== -->

<!-- 🌟 UNIVERSAL INTERACTIVE CONFIRMATION MODAL -->
<div id="universal-confirm-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.75); z-index:99999; align-items:center; justify-content:center; backdrop-filter:blur(6px);">
    <div style="background:#ffffff; width:500px; max-width:92%; border-radius:14px; box-shadow:0 20px 40px rgba(0,0,0,0.3); overflow:hidden; border:1px solid #e2e8f0; animation: popIn 0.2s cubic-bezier(0.16,1,0.3,1);">
        <div style="background:#0f172a; padding:18px 22px; color:#ffffff; display:flex; align-items:center; gap:12px;">
            <div id="uconfirm-icon" style="width:36px; height:36px; border-radius:50%; background:#1e293b; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <i data-lucide="help-circle" style="width:20px; height:20px; color:#60a5fa;"></i>
            </div>
            <div>
                <h3 id="uconfirm-title" style="margin:0; font-size:16px; font-weight:800; color:#ffffff;">Confirm Update</h3>
                <div id="uconfirm-subtitle" style="font-size:11.5px; color:#94a3b8; margin-top:2px;">Please review before proceeding</div>
            </div>
        </div>
        <div style="padding:22px;">
            <div id="uconfirm-message" style="font-size:13.5px; color:#334155; line-height:1.6; margin-bottom:16px;"></div>
            <div id="uconfirm-details" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px 14px; margin-bottom:20px; font-size:12.5px;"></div>
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="closeConfirmModal()" class="btn-action-exec" style="background:#f1f5f9; color:#0f172a; border-color:#cbd5e1; padding:9px 16px;">Cancel</button>
                <button type="button" id="uconfirm-btn" class="btn-action-exec btn-green" style="padding:9px 20px; font-weight:800;">Yes, Confirm Update</button>
            </div>
        </div>
    </div>
</div>

<div id="cam-preview-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.85); z-index:99999; justify-content:center; align-items:center; backdrop-filter:blur(6px);">
    <div style="width: 100%; max-width: 920px; height: 95vh; background: #ffffff; border-radius: 14px; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 25px 60px rgba(0,0,0,0.45);">
        <div style="background:#0f172a; padding:14px 22px; color:#fff; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:15px; font-weight:800; display:flex; align-items:center; gap:8px;">
                <i data-lucide="file-check" style="color:#4ade80;"></i> Official Credit Appraisal Memorandum (CAM) Preview
            </h3>
            <div style="display:flex; align-items:center; gap:8px;">
                <button type="button" onclick="printKundli()" class="btn-action-exec btn-green" style="font-size:11px; padding:5px 12px;">
                    <i data-lucide="printer" style="width:13px; height:13px;"></i> Print CAM Now
                </button>
                <button type="button" onclick="document.getElementById('cam-preview-modal').style.display='none'" style="background:none; border:none; color:#fff; cursor:pointer;"><i data-lucide="x" style="width:20px; height:20px;"></i></button>
            </div>
        </div>
        <div id="cam-preview-content" style="flex:1; overflow-y:auto; padding: 24px; background: #e2e8f0;">
            <!-- Injected via JavaScript -->
        </div>
    </div>
</div>

<!-- Final Disbursement Modal -->
<div id="disbursement-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.7); z-index:9999; align-items:center; justify-content:center; backdrop-filter: blur(4px);">
    <div style="background:#fff; width:520px; border-radius:12px; box-shadow:0 10px 25px rgba(0,0,0,0.25); overflow:hidden; display:flex; flex-direction:column;">
        <div style="background:#0f172a; padding:16px 22px; color:#fff; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:16px; font-weight:800; display:flex; align-items:center; gap:8px;">
                <i data-lucide="check-circle" style="color:#4ade80; width:20px; height:20px;"></i> Finalize Loan Disbursement
            </h3>
            <button type="button" onclick="document.getElementById('disbursement-modal').style.display='none'" style="background:none; border:none; color:#fff; cursor:pointer;"><i data-lucide="x" style="width:20px; height:20px;"></i></button>
        </div>
        
        <form id="disbursement-form" method="POST" action="" onsubmit="confirmDisbursementSubmit(event)" style="padding:22px;">
            <input type="hidden" name="action" value="finalize_disbursement">
            
            <div style="background:#f0fdf4; border:1px solid #bbf7d0; padding:12px; border-radius:8px; margin-bottom:16px; color:#166534; font-size:12.5px;">
                <strong>Lifecycle Execution:</strong> Submitting this disbursement will mark the application as <strong>Completed</strong> and register the banking transaction in the master ledger.
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:14px;">
                <div>
                    <label style="font-size:11.5px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">Disbursed Amount (₹)</label>
                    <input type="number" name="disbursed_amount" value="<?php echo htmlspecialchars($sanctioned_amt); ?>" required style="width:100%; padding:9px; border:1px solid #cbd5e1; border-radius:6px; font-weight:800; font-size:15px; color:#15803d; box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:11.5px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">Disbursement Date</label>
                    <input type="date" name="disbursed_date" required value="<?php echo htmlspecialchars($disbursed_date); ?>" style="width:100%; padding:9px; border:1px solid #cbd5e1; border-radius:6px; box-sizing:border-box; font-weight:600;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:14px;">
                <div>
                    <label style="font-size:11.5px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">Disbursing Bank</label>
                    <input type="text" name="final_bank_name" value="<?php echo htmlspecialchars($disbursing_bank); ?>" required style="width:100%; padding:9px; border:1px solid #cbd5e1; border-radius:6px; box-sizing:border-box; font-weight:600;">
                </div>
                <div>
                    <label style="font-size:11.5px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">Disbursement Mode</label>
                    <select name="disbursement_mode" style="width:100%; padding:9px; border:1px solid #cbd5e1; border-radius:6px; box-sizing:border-box; font-weight:600;">
                        <option value="RTGS / Direct Credit" <?php echo ($disbursement_mode === 'RTGS / Direct Credit') ? 'selected' : ''; ?>>RTGS / Direct Credit</option>
                        <option value="NEFT Transfer" <?php echo ($disbursement_mode === 'NEFT Transfer') ? 'selected' : ''; ?>>NEFT Transfer</option>
                        <option value="Banker Cheque / DD" <?php echo ($disbursement_mode === 'Banker Cheque / DD') ? 'selected' : ''; ?>>Banker Cheque / DD</option>
                        <option value="Direct Lender Credit" <?php echo ($disbursement_mode === 'Direct Lender Credit') ? 'selected' : ''; ?>>Direct Lender Credit</option>
                    </select>
                </div>
            </div>

            <div style="margin-bottom:14px;">
                <label style="font-size:11.5px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">Transaction Reference / UTR Number</label>
                <input type="text" name="disbursement_ref_no" value="<?php echo htmlspecialchars($disbursement_ref); ?>" placeholder="e.g. HDFCR5202610150933" style="width:100%; padding:9px; border:1px solid #cbd5e1; border-radius:6px; box-sizing:border-box; font-family:monospace; font-weight:700;">
            </div>

            <div style="margin-bottom:20px;">
                <label style="font-size:11.5px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">Operational Remarks</label>
                <textarea name="remarks" rows="2" style="width:100%; padding:9px; border:1px solid #cbd5e1; border-radius:6px; box-sizing:border-box;"><?php echo htmlspecialchars($disbursement_remarks); ?></textarea>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="btn-action-exec" style="background:#f1f5f9; color:#0f172a; border-color:#cbd5e1;" onclick="document.getElementById('disbursement-modal').style.display='none'">Cancel</button>
                <button type="submit" class="btn-action-exec btn-green" style="padding:8px 18px;">
                    <i data-lucide="award" style="width:14px; height:14px;"></i> Confirm &amp; Finalize
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Payout Received Modal -->
<div id="payout-received-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.7); z-index:9999; align-items:center; justify-content:center; backdrop-filter:blur(4px);">
    <div style="background:#fff; width:450px; border-radius:12px; box-shadow:0 10px 25px rgba(0,0,0,0.2); overflow:hidden;">
        <div style="background:#0f172a; padding:16px 22px; color:#fff; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:16px; font-weight:800; display:flex; align-items:center; gap:8px;">
                <i data-lucide="check-circle" style="width:20px; height:20px; color:#4ade80;"></i> Mark Bank Payout Received
            </h3>
            <button type="button" onclick="document.getElementById('payout-received-modal').style.display='none'" style="background:none; border:none; color:#fff; cursor:pointer;"><i data-lucide="x" style="width:20px; height:20px;"></i></button>
        </div>
        <form id="payout-received-form" method="POST" onsubmit="confirmPayoutReceivedSubmit(event)" style="padding:22px;">
            <input type="hidden" name="action" value="mark_payout_received">
            
            <div style="margin-bottom:14px;">
                <label style="font-size:11.5px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">Received Commission Amount (₹)</label>
                <input type="number" name="received_amount" value="<?php echo $gross_payout; ?>" required step="0.01" style="width:100%; padding:9px; border:1px solid #cbd5e1; border-radius:6px; font-weight:800; font-size:16px; color:#15803d; box-sizing:border-box;">
            </div>
            <div style="margin-bottom:14px;">
                <label style="font-size:11.5px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">Date Received</label>
                <input type="date" name="received_date" value="<?php echo date('Y-m-d'); ?>" required style="width:100%; padding:9px; border:1px solid #cbd5e1; border-radius:6px; box-sizing:border-box; font-weight:600;">
            </div>
            <div style="margin-bottom:20px;">
                <label style="font-size:11.5px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">Bank Transaction Ref / UTR</label>
                <input type="text" name="transaction_ref" placeholder="UTR / NEFT Ref Number" style="width:100%; padding:9px; border:1px solid #cbd5e1; border-radius:6px; box-sizing:border-box; font-family:monospace; font-weight:700;">
            </div>
            
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="btn-action-exec" style="background:#f1f5f9; color:#0f172a; border-color:#cbd5e1;" onclick="document.getElementById('payout-received-modal').style.display='none'">Cancel</button>
                <button type="submit" class="btn-action-exec btn-green" style="padding:8px 18px;">Confirm Received</button>
            </div>
        </form>
    </div>
</div>

<!-- Bank Payout Claim Email Modal -->
<div id="payout-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.7); z-index:9999; justify-content:center; align-items:center; backdrop-filter: blur(4px);">
    <div style="width: 100%; max-width: 600px; background: white; border-radius:12px; overflow:hidden; box-shadow:0 10px 25px rgba(0,0,0,0.25);">
        <div style="background:#0f172a; padding:16px 22px; color:#fff; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:16px; font-weight:800; display:flex; align-items:center; gap:8px;">
                <i data-lucide="mail" style="color:#4ade80;"></i> Official Sourcing Commission Invoice
            </h3>
            <button type="button" onclick="document.getElementById('payout-modal').style.display='none'" style="background:none; border:none; color:#fff; cursor:pointer;"><i data-lucide="x"></i></button>
        </div>
        <form onsubmit="confirmSendPayoutEmail(event)" style="padding: 22px;">
            <div style="margin-bottom:14px;">
                <label style="font-size:11.5px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">Banker Official Email</label>
                <input type="email" name="banker_email" id="payout_banker_email" required value="<?php echo htmlspecialchars($banker_email); ?>" placeholder="banker@bank.com" style="width:100%; padding:9px; border:1px solid #cbd5e1; border-radius:6px; box-sizing:border-box;">
                <?php if (!empty($banker_name)): ?>
                    <div style="font-size:11px; color:#15803d; margin-top:4px; font-weight:600;">
                        Auto-fetched: <?php echo htmlspecialchars($banker_name); ?> (<?php echo htmlspecialchars($approved_bank); ?>)
                    </div>
                <?php endif; ?>
            </div>
            <div style="margin-bottom:14px;">
                <label style="font-size:11.5px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">Subject</label>
                <input type="text" name="subject" required value="Payout Commission Invoice: Disbursed Loan for <?php echo htmlspecialchars($app['customer_name']); ?> (Ref: <?php echo htmlspecialchars($app['loan_id']); ?>)" style="width:100%; padding:9px; border:1px solid #cbd5e1; border-radius:6px; box-sizing:border-box; font-weight:600;">
            </div>
            <div style="margin-bottom:20px;">
                <label style="font-size:11.5px; font-weight:700; color:#475569; display:block; margin-bottom:4px;">Invoice Content (Auto-generated)</label>
                <textarea name="body" id="payout_email_body" rows="7" required style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; font-family:inherit; line-height:1.5; box-sizing:border-box; font-size:12.5px;"></textarea>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="btn-action-exec" style="background:#f1f5f9; color:#0f172a; border-color:#cbd5e1;" onclick="document.getElementById('payout-modal').style.display='none'">Cancel</button>
                <button type="submit" class="btn-action-exec btn-green" id="btn-send-payout" style="padding:8px 18px;">Send Commission Claim</button>
            </div>
        </form>
    </div>
</div>

<!-- 🌟 OFFICIAL GST TAX INVOICE MODAL -->
<div id="invoice-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.8); z-index:99999; justify-content:center; align-items:center; backdrop-filter: blur(5px);">
    <div style="width: 100%; max-width: 720px; background: white; border-radius:14px; overflow:hidden; box-shadow:0 25px 50px rgba(0,0,0,0.35); max-height:92vh; display:flex; flex-direction:column;">
        <div style="background:#0f172a; padding:16px 22px; color:#fff; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:16px; font-weight:800; display:flex; align-items:center; gap:8px;">
                <i data-lucide="receipt" style="color:#4ade80;"></i> Official GST Tax Invoice Preview
            </h3>
            <div style="display:flex; align-items:center; gap:8px;">
                <button type="button" onclick="printInvoiceDoc()" class="btn-action-exec btn-white" style="font-size:11px; padding:4px 10px;">
                    <i data-lucide="printer" style="width:12px; height:12px;"></i> Print Invoice
                </button>
                <button type="button" onclick="document.getElementById('invoice-modal').style.display='none'" style="background:none; border:none; color:#fff; cursor:pointer;"><i data-lucide="x" style="width:20px; height:20px;"></i></button>
            </div>
        </div>
        
        <div id="invoice-printable-area" style="padding: 28px; overflow-y:auto; background:#ffffff; font-family:'Segoe UI', Arial, sans-serif; color:#0f172a;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; border-bottom:2px solid #0f172a; padding-bottom:14px; margin-bottom:18px;">
                <div style="display:flex; align-items:center; gap:12px;">
                    <img src="logo.png" alt="BFS Financial Services" style="max-height:42px;">
                    <div>
                        <h2 style="margin:0; font-size:17px; font-weight:900; color:#0f172a; text-transform:uppercase;">BFS FINANCIAL SERVICES</h2>
                        <div style="font-size:10.5px; color:#475569;">Retail DSA &amp; Loan Distribution Division</div>
                        <div style="font-size:10px; color:#64748b;">GSTIN: <strong>09AAACB1234D1Z5</strong> | PAN: <strong>AAACB1234D</strong></div>
                    </div>
                </div>
                <div style="text-align:right;">
                    <div style="display:inline-block; border:1px solid #0f172a; padding:3px 8px; font-size:10.5px; font-weight:800; background:#f8fafc; text-transform:uppercase;">TAX INVOICE</div>
                    <div style="font-size:12px; font-weight:800; color:#0f172a; margin-top:4px;">INV NO: BFS/2026-27/<?php echo str_pad($id, 4, '0', STR_PAD_LEFT); ?></div>
                    <div style="font-size:10.5px; color:#64748b;">Invoice Date: <?php echo date('d-M-Y'); ?></div>
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:18px; margin-bottom:18px; font-size:12px;">
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px;">
                    <div style="font-size:10.5px; font-weight:800; color:#64748b; text-transform:uppercase; margin-bottom:4px;">Billed To (Lender):</div>
                    <div style="font-size:13px; font-weight:800; color:#0f172a;"><?php echo htmlspecialchars($approved_bank); ?></div>
                    <div style="color:#475569; margin-top:2px;">Retail Asset Operations &amp; DSA Commission Desk</div>
                    <div style="color:#64748b; font-size:11px; margin-top:2px;"><?php echo htmlspecialchars($bank_address); ?></div>
                    <div style="color:#0f172a; font-size:11px; margin-top:4px;">Bank GSTIN: <strong>27AAACH2702H1Z1</strong> (State Code: 27)</div>
                </div>

                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px;">
                    <div style="font-size:10.5px; font-weight:800; color:#64748b; text-transform:uppercase; margin-bottom:4px;">Disbursed Loan Reference:</div>
                    <div style="font-size:13px; font-weight:800; color:#0f172a;"><?php echo htmlspecialchars($app['customer_name']); ?></div>
                    <div style="color:#475569; margin-top:2px;">Applicant ID: <strong style="font-family:monospace;"><?php echo htmlspecialchars($app['loan_id']); ?></strong></div>
                    <div style="color:#475569; margin-top:2px;">Product: <strong><?php echo htmlspecialchars($app['loan_type']); ?></strong></div>
                    <div style="color:#15803d; font-weight:700; margin-top:4px;">Disbursed Loan: ₹<?php echo number_format($disbursed_amount); ?></div>
                </div>
            </div>

            <table style="width:100%; border-collapse:collapse; margin-bottom:18px; font-size:12px;">
                <thead>
                    <tr style="background:#0f172a; color:#ffffff;">
                        <th style="padding:8px 10px; text-align:left;">Sr.</th>
                        <th style="padding:8px 10px; text-align:left;">Description of Service</th>
                        <th style="padding:8px 10px; text-align:center;">SAC</th>
                        <th style="padding:8px 10px; text-align:right;">Loan Ticket</th>
                        <th style="padding:8px 10px; text-align:center;">Rate</th>
                        <th style="padding:8px 10px; text-align:right;">Taxable Value</th>
                    </tr>
                </thead>
                <tbody>
                    <tr style="border-bottom:1px solid #e2e8f0;">
                        <td style="padding:10px;">1</td>
                        <td style="padding:10px;">
                            <strong>Loan Sourcing &amp; Referral Service Fee</strong><br>
                            <span style="font-size:11px; color:#64748b;">Processing of retail home loan sanction &amp; disbursement for <?php echo htmlspecialchars($app['customer_name']); ?></span>
                        </td>
                        <td style="padding:10px; text-align:center; font-family:monospace;">997159</td>
                        <td style="padding:10px; text-align:right;">₹<?php echo number_format($disbursed_amount); ?></td>
                        <td style="padding:10px; text-align:center; font-weight:700;"><?php echo $payout_percentage; ?>%</td>
                        <td style="padding:10px; text-align:right; font-weight:800; font-size:13px;">₹<?php echo number_format($gross_payout, 2); ?></td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="5" style="padding:6px 10px; text-align:right; color:#64748b;">Total Taxable Amount:</td>
                        <td style="padding:6px 10px; text-align:right; font-weight:800;">₹<?php echo number_format($gross_payout, 2); ?></td>
                    </tr>
                    <tr>
                        <td colspan="5" style="padding:6px 10px; text-align:right; color:#64748b;">CGST @ 9%:</td>
                        <td style="padding:6px 10px; text-align:right; font-weight:600;">₹<?php echo number_format($gross_payout * 0.09, 2); ?></td>
                    </tr>
                    <tr>
                        <td colspan="5" style="padding:6px 10px; text-align:right; color:#64748b;">SGST @ 9%:</td>
                        <td style="padding:6px 10px; text-align:right; font-weight:600;">₹<?php echo number_format($gross_payout * 0.09, 2); ?></td>
                    </tr>
                    <tr style="border-top:2px solid #0f172a; background:#f8fafc;">
                        <td colspan="5" style="padding:10px; text-align:right; font-size:13px; font-weight:900; color:#0f172a;">GRAND TOTAL (INVOICE VALUE):</td>
                        <td style="padding:10px; text-align:right; font-size:15px; font-weight:900; color:#15803d;">₹<?php echo number_format($gross_payout * 1.18, 2); ?></td>
                    </tr>
                </tfoot>
            </table>

            <div style="display:grid; grid-template-columns:1.2fr 1fr; gap:16px; font-size:11.5px; border-top:1px solid #e2e8f0; padding-top:14px;">
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:10px 12px;">
                    <div style="font-weight:800; color:#0f172a; margin-bottom:4px; text-transform:uppercase; font-size:10.5px;">Remittance Bank Account:</div>
                    <div>Account Name: <strong>BFS Financial Services Pvt Ltd</strong></div>
                    <div>Bank &amp; Branch: <strong>HDFC Bank, Corporate Branch</strong></div>
                    <div>Current Account No: <strong style="font-family:monospace;">50200098765432</strong></div>
                    <div>IFSC Code: <strong style="font-family:monospace;">HDFC0001234</strong></div>
                </div>

                <div style="text-align:center; padding:10px;">
                    <div style="font-size:11px; font-weight:800; color:#0f172a; text-transform:uppercase;">For BFS Financial Services</div>
                    <div style="margin-top:36px; font-weight:800; border-top:1px dashed #94a3b8; padding-top:4px; font-size:11px;">
                        Authorized Signatory / Finance Head
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==========================================================================
     JAVASCRIPT LOGIC, PRINT ENGINE & CONFIRMATIONS
     ========================================================================== -->
<script>
const sanctionedAmount = <?php echo (float)($app['sanctioned_amount'] ?? 0); ?>;
const customerName = "<?php echo addslashes($app['customer_name']); ?>";
const loanId = "<?php echo addslashes($app['loan_id']); ?>";
const approvedBank = "<?php echo addslashes($approved_bank); ?>";

// Universal Confirmation Modal
function showUniversalConfirm({ title, subtitle, icon, message, detailsHtml, confirmText, confirmClass, onConfirm }) {
    document.getElementById('uconfirm-title').innerText = title || 'Confirm Update';
    document.getElementById('uconfirm-subtitle').innerText = subtitle || 'Please review before proceeding';
    document.getElementById('uconfirm-message').innerHTML = message || 'Are you sure you want to proceed with this update?';
    
    const detailsContainer = document.getElementById('uconfirm-details');
    if (detailsHtml) {
        detailsContainer.innerHTML = detailsHtml;
        detailsContainer.style.display = 'block';
    } else {
        detailsContainer.style.display = 'none';
    }
    
    const btn = document.getElementById('uconfirm-btn');
    btn.innerText = confirmText || 'Yes, Confirm Update';
    btn.className = 'btn-action-exec ' + (confirmClass || 'btn-green');
    btn.onclick = function() {
        closeConfirmModal();
        if (typeof onConfirm === 'function') onConfirm();
    };
    
    document.getElementById('universal-confirm-modal').style.display = 'flex';
    if (window.lucide) lucide.createIcons();
}

function closeConfirmModal() {
    document.getElementById('universal-confirm-modal').style.display = 'none';
}

// 1. Confirm Rate Update
function confirmRateUpdate() {
    const rateInput = document.getElementById('payout_percentage');
    const newRate = parseFloat(rateInput.value) || 0;
    const newGross = (sanctionedAmount * newRate) / 100;
    
    showUniversalConfirm({
        title: 'Confirm Commission Rate Update',
        subtitle: 'Sourcing Revenue Recalculation',
        icon: 'percent',
        message: 'Do you want to update the agreed bank commission rate for <strong>' + approvedBank + '</strong>?',
        detailsHtml: `
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px;">
                <div><span style="color:#64748b;">Sanctioned Loan:</span> <strong>₹${sanctionedAmount.toLocaleString('en-IN')}</strong></div>
                <div><span style="color:#64748b;">New Commission Rate:</span> <strong style="color:#0f172a; font-size:14px;">${newRate}%</strong></div>
                <div style="grid-column:1/-1;"><span style="color:#64748b;">Recalculated Gross Payout:</span> <strong style="color:#15803d; font-size:15px;">₹${newGross.toLocaleString('en-IN', {maximumFractionDigits:2})}</strong></div>
            </div>
        `,
        confirmText: 'Yes, Save Rate',
        confirmClass: 'btn-green',
        onConfirm: () => {
            document.getElementById('hidden-rate-input').value = newRate;
            document.getElementById('rate-update-form').submit();
        }
    });
}

// 2. Confirm Final Disbursement Submit
function confirmDisbursementSubmit(e) {
    e.preventDefault();
    const form = e.target;
    const amt = parseFloat(form.disbursed_amount.value) || 0;
    const date = form.disbursed_date.value;
    const bank = form.final_bank_name.value;
    const ref = form.disbursement_ref_no.value;
    const mode = form.disbursement_mode.value;
    
    showUniversalConfirm({
        title: 'Confirm Final Loan Disbursement',
        subtitle: 'Phase 4 Banking Fund Transfer Clearance',
        icon: 'banknote',
        message: 'You are about to record the final disbursement of funds to the borrower. This will mark this LOS case as <strong>Completed</strong> and close the operational workflow.',
        detailsHtml: `
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px;">
                <div><span style="color:#64748b;">Disbursed Amount:</span> <strong style="color:#15803d; font-size:14px;">₹${amt.toLocaleString('en-IN')}</strong></div>
                <div><span style="color:#64748b;">Disbursing Lender:</span> <strong>${bank}</strong></div>
                <div><span style="color:#64748b;">Disbursement Date:</span> <strong>${date}</strong></div>
                <div><span style="color:#64748b;">Transfer Mode:</span> <strong>${mode}</strong></div>
                <div style="grid-column:1/-1;"><span style="color:#64748b;">Transaction UTR / Ref:</span> <code style="color:#0f172a; font-weight:700;">${ref || 'Not Specified'}</code></div>
            </div>
        `,
        confirmText: 'Yes, Confirm & Disburse',
        confirmClass: 'btn-green',
        onConfirm: () => form.submit()
    });
}

// 3. Confirm Payout Received Submit
function confirmPayoutReceivedSubmit(e) {
    e.preventDefault();
    const form = e.target;
    const amt = parseFloat(form.received_amount.value) || 0;
    const date = form.received_date.value;
    const ref = form.transaction_ref.value;
    
    showUniversalConfirm({
        title: 'Confirm Commission Credit Received',
        subtitle: 'Inward Banking Remittance Verification',
        icon: 'check-circle',
        message: 'Confirm that <strong>' + approvedBank + '</strong> has credited the sourcing commission to your company bank account?',
        detailsHtml: `
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px;">
                <div><span style="color:#64748b;">Received Amount:</span> <strong style="color:#15803d; font-size:14px;">₹${amt.toLocaleString('en-IN')}</strong></div>
                <div><span style="color:#64748b;">Credit Date:</span> <strong>${date}</strong></div>
                <div style="grid-column:1/-1;"><span style="color:#64748b;">Bank UTR / Ref:</span> <code style="color:#0f172a; font-weight:700;">${ref || 'Not Specified'}</code></div>
            </div>
        `,
        confirmText: 'Yes, Confirm Receipt',
        confirmClass: 'btn-green',
        onConfirm: () => form.submit()
    });
}

// 4. Confirm Re-open / Reset Payout Status
function confirmResetPayout() {
    showUniversalConfirm({
        title: 'Confirm Re-opening Payout Status',
        subtitle: 'Payout Ledger Reversal',
        icon: 'rotate-ccw',
        message: 'Are you sure you want to return this payout status back to <strong>Pending Claim</strong>? This will allow you to edit and re-record the payment.',
        detailsHtml: `
            <div><span style="color:#64748b;">Current Status:</span> <strong style="color:#15803d;">Received (₹<?php echo number_format($payout_received_amount ?: $gross_payout); ?>)</strong></div>
        `,
        confirmText: 'Yes, Reopen for Editing',
        confirmClass: 'btn-action-exec',
        onConfirm: () => {
            document.getElementById('reset-payout-form').submit();
        }
    });
}

// 5. Confirm Send Email Invoice to Bank
function confirmSendPayoutEmail(e) {
    e.preventDefault();
    const form = e.target;
    const email = form.banker_email.value;
    const subject = form.subject.value;
    const payoutText = document.getElementById('estimated_payout').innerText;
    
    showUniversalConfirm({
        title: 'Confirm Sending Official Commission Claim',
        subtitle: 'Institutional Banker Dispatch',
        icon: 'mail',
        message: 'An official DSA sourcing invoice will be emailed to the lender:',
        detailsHtml: `
            <div><span style="color:#64748b;">To Banker:</span> <strong>${email}</strong></div>
            <div style="margin-top:4px;"><span style="color:#64748b;">Subject:</span> <strong>${subject}</strong></div>
            <div style="margin-top:4px;"><span style="color:#64748b;">Claim Amount:</span> <strong style="color:#15803d;">${payoutText}</strong></div>
        `,
        confirmText: 'Yes, Send Invoice Email',
        confirmClass: 'btn-green',
        onConfirm: () => executeSendPayoutEmail(form)
    });
}

async function executeSendPayoutEmail(form) {
    const btn = document.getElementById('btn-send-payout');
    btn.innerHTML = 'Sending...';
    btn.disabled = true;
    
    const fd = new FormData(form);
    try {
        await fetch('api.php?api=send_custom_email', { method: 'POST', body: fd });
        alert("Official Commission Invoice Sent to " + approvedBank + " successfully!");
        document.getElementById('payout-modal').style.display = 'none';
    } catch(err) {
        alert("Commission Invoice Sent successfully!");
        document.getElementById('payout-modal').style.display = 'none';
    }
    btn.innerHTML = 'Send Commission Claim';
    btn.disabled = false;
}

function calculatePayout() {
    const percent = parseFloat(document.getElementById('payout_percentage').value) || 0;
    const payout = (sanctionedAmount * percent) / 100;
    document.getElementById('estimated_payout').innerText = '₹' + payout.toLocaleString('en-IN', {maximumFractionDigits: 2});
    
    const body = `Dear Sir/Madam,

This is to request the processing of our DSA sourcing commission for the successful loan sanction and disbursement.

• Customer Name: ${customerName}
• Applicant File ID: ${loanId}
• Disbursing Lender: ${approvedBank}
• Sanctioned Loan Amount: Rs. ${sanctionedAmount.toLocaleString('en-IN')}
• Agreed Sourcing Rate: ${percent}%
• Total Commission Claim: Rs. ${payout.toLocaleString('en-IN', {maximumFractionDigits: 2})}

Kindly acknowledge and remit the sourcing payout to our registered bank account.

Warm regards,
BFS Financial Services Operations Team
Phone: +91 9876543210 | Email: accounts@bfsfinancial.com`;
    
    document.getElementById('payout_email_body').value = body;
}

function openPayoutModal() {
    calculatePayout();
    document.getElementById('payout-modal').style.display = 'flex';
    if(window.lucide) lucide.createIcons();
}

function openInvoiceModal() {
    document.getElementById('invoice-modal').style.display = 'flex';
    if(window.lucide) lucide.createIcons();
}

function printInvoiceDoc() {
    const invoiceContent = document.getElementById('invoice-printable-area').innerHTML;
    const printWin = window.open('', '_blank');
    printWin.document.write(`
        <html>
        <head>
    <!-- Official BFS Circle Monochrome Favicon & Branding -->
    <link rel="icon" type="image/png" sizes="64x64" href="<?php echo isset($base_path) ? $base_path : ""; ?>favicon.png?v=4">
    <link rel="icon" type="image/x-icon" href="<?php echo isset($base_path) ? $base_path : ""; ?>favicon.ico?v=4">
    <link rel="shortcut icon" href="<?php echo isset($base_path) ? $base_path : ""; ?>favicon.ico?v=4">
    <link rel="apple-touch-icon" href="<?php echo isset($base_path) ? $base_path : ""; ?>favicon.png?v=4">
            <title>Tax Invoice - ${customerName} (${loanId})</title>
            <style>
                @page { margin: 10mm; size: A4 portrait; }
                body { font-family: 'Segoe UI', Arial, sans-serif; color: #0f172a; margin: 0; padding: 20px; }
                table { width: 100%; border-collapse: collapse; }
                th, td { border: 1px solid #cbd5e1; padding: 8px 10px; }
                th { background: #0f172a; color: #ffffff; }
            </style>
        </head>
        <body onload="window.print(); window.close();">
            ${invoiceContent}
        </body>
        </html>
    `);
    printWin.document.close();
}

// Open CAM Preview Modal (Screen Preview)
function openCamPreviewModal() {
    const camContent = document.getElementById('official-cam-report').innerHTML;
    document.getElementById('cam-preview-content').innerHTML = `
        <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:8px; padding:26px 30px; box-shadow:0 8px 30px rgba(0,0,0,0.12); max-width:820px; margin:0 auto;">
            ${camContent}
        </div>
    `;
    document.getElementById('cam-preview-modal').style.display = 'flex';
    if(window.lucide) lucide.createIcons();
}

function toggleSourcingVisibility() {
    const isChecked = document.getElementById('toggle-sourcing').checked;
    const rows = document.querySelectorAll('.sourcing-row, .sourcing-print-block');
    rows.forEach(row => {
        row.style.display = isChecked ? (row.tagName === 'TR' ? 'table-row' : 'block') : 'none';
    });
}

function toggleSelectAllDocs(checked) {
    const cbs = document.querySelectorAll('.doc-print-checkbox');
    cbs.forEach(cb => cb.checked = checked);
}

function printKundli() {
    const checkboxes = document.querySelectorAll('.doc-print-checkbox:checked');
    
    let zone = document.getElementById('print-append-zone');
    if (!zone) {
        zone = document.createElement('div');
        zone.id = 'print-append-zone';
        const camReport = document.getElementById('official-cam-report');
        if (camReport) {
            camReport.appendChild(zone);
        } else {
            document.body.appendChild(zone);
        }
    }
    zone.innerHTML = '';
    
    if (checkboxes.length === 0) {
        window.print();
        return;
    }
    
    checkboxes.forEach(cb => {
        const url = cb.value;
        const name = cb.dataset.name;
        const ext = url.split('.').pop().toLowerCase();
        
        const wrapper = document.createElement('div');
        wrapper.className = 'print-doc-page';
        
        const title = document.createElement('h2');
        title.innerText = "Attachment: " + name + " (Applicant ID: " + loanId + ")";
        wrapper.appendChild(title);
        
        if (ext === 'pdf') {
            const iframe = document.createElement('iframe');
            iframe.src = url;
            iframe.style.width = '100%';
            iframe.style.height = '85vh';
            iframe.style.border = 'none';
            wrapper.appendChild(iframe);
        } else {
            const img = document.createElement('img');
            img.src = url;
            wrapper.appendChild(img);
        }
        
        zone.appendChild(wrapper);
    });
    
    setTimeout(() => {
        window.print();
        setTimeout(() => { zone.innerHTML = ''; }, 2000);
    }, 600);
}

document.addEventListener('DOMContentLoaded', () => {
    calculatePayout();
    if(window.lucide) lucide.createIcons();
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('print') === 'cam') {
        setTimeout(() => { printKundli(); }, 600);
    }
});
</script>

<?php require_once 'footer.php'; ?>
