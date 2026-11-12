<?php
// cron_document_reminders.php
// Run this script via cron daily (e.g., 0 9 * * *) to send automated missing document reminders

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/libs/PHPMailer/Exception.php';
require_once __DIR__ . '/libs/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/libs/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 1. Setup logging table
$db->exec("CREATE TABLE IF NOT EXISTS doc_reminders_log (applicant_id INTEGER, sent_date DATE)");

$today = date('Y-m-d');

// 2. Fetch all active applicants (not completed, not rejected) who have an email
$stmt = $db->query("SELECT * FROM applicants WHERE overall_status IN ('Phase 1', 'Phase 2') AND ((email IS NOT NULL AND email != '') OR (mobile IS NOT NULL AND mobile != ''))");
$applicants = $stmt->fetchAll(PDO::FETCH_ASSOC);

$dry_run = (isset($argv) && is_array($argv) && in_array('--dry-run', $argv, true)) || isset($_GET['dry_run']);
$evaluated = 0;
$pending_count = 0;
$sent_count = 0;
$wa_sent_count = 0;
$details = [];

// Fetch company SMTP profile
$company = $db->query("SELECT * FROM company_profile LIMIT 1")->fetch(PDO::FETCH_ASSOC);

foreach ($applicants as $app) {
    $evaluated++;
    $id = $app['id'];
    
    // Check if already sent today
    $logCheck = $db->prepare("SELECT 1 FROM doc_reminders_log WHERE applicant_id = ? AND sent_date = ?");
    $logCheck->execute([$id, $today]);
    if ($logCheck->fetch()) {
        continue; // Already sent today
    }
    
    // Determine missing documents (Phase 2 logic)
    $loan_type = $app['loan_type'] ?? '';
    $mandatory_categories = ['Basic KYC'];
    if (stripos($loan_type, 'Home') !== false) {
        $mandatory_categories = ['Basic KYC', 'Income Proof', 'Property / Asset Docs'];
    } elseif (stripos($loan_type, 'Business') !== false) {
        $mandatory_categories = ['Basic KYC', 'Income Proof', 'Business Proof'];
    } elseif (stripos($loan_type, 'Vehicle') !== false) {
        $mandatory_categories = ['Basic KYC', 'Income Proof', 'Vehicle Docs'];
    } else {
        $mandatory_categories = ['Basic KYC', 'Income Proof'];
    }
    
    // Get uploaded docs
    $docStmt = $db->prepare("SELECT document_category FROM applicant_documents WHERE applicant_id = ?");
    $docStmt->execute([$id]);
    $docs = $docStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Find missing
    $missing_cats = [];
    foreach ($mandatory_categories as $cat) {
        if (!in_array($cat, $docs)) {
            $missing_cats[] = $cat;
        }
    }
    
    // If there are missing documents, BUT they have at least 1 document OR we just want to remind them anyway?
    // User said: "JESE HUMARE PASS KISI APPLICANT KE KUCH DOCUMENT AAGYE HE BUT USKE DOCCUMENT BAKI HE TO PER DAY USKO AUTO EMAIL JAYE"
    // "if some docs have arrived but some are remaining" -> count($docs) > 0 && count($missing_cats) > 0
    
    if (count($docs) > 0 && count($missing_cats) > 0) {
        $pending_count++;
        $nameClean = trim((string)($app['customer_name'] ?? 'Customer')) ?: 'Customer';
        $loanLabel = trim((string)(($app['loan_type'] ?: 'Loan') . (!empty($app['loan_id']) ? (' #' . $app['loan_id']) : '')));
        $missingList = implode(', ', $missing_cats);
        if ($dry_run) {
            $details[] = ['applicant_id' => (int)$app['id'], 'name' => $nameClean, 'has_email' => !empty($app['email']), 'has_mobile' => !empty($app['mobile']), 'missing' => $missing_cats];
            continue;
        }

        $emailSent = false;
        $waSent = false;
        $smtpUser = trim((string)($company['smtp_user'] ?? $company['smtp_username'] ?? ''));
        $smtpPass = (string)($company['smtp_pass'] ?? $company['smtp_password'] ?? '');
        $smtpHost = trim((string)($company['smtp_host'] ?? ''));
        $smtpEnc  = strtolower(trim((string)($company['smtp_encryption'] ?? 'tls')));
        $smtpPort = (int)($company['smtp_port'] ?? 587);

        if (!empty($app['email'])) {
            $mail = new PHPMailer(true);
            try {
                if ($smtpHost !== '' && $smtpUser !== '' && $smtpPass !== '') {
                    $mail->isSMTP();
                    $mail->Host       = $smtpHost;
                    $mail->SMTPAuth   = true;
                    $mail->Username   = $smtpUser;
                    $mail->Password   = $smtpPass;
                    if ($smtpEnc === 'ssl') {
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                    } else {
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    }
                    $mail->Port       = $smtpPort > 0 ? $smtpPort : 587;
                    $mail->setFrom($smtpUser, $company['company_name'] ?? 'CRM Team');
                }
                $mail->addAddress($app['email'], $nameClean);
                $mail->isHTML(true);
                $mail->Subject = "Pending Documents Reminder - Loan Application #" . $app['loan_id'];
                $missingHtml = "<ul style='color: #dc2626; font-weight: bold;'>";
                foreach ($missing_cats as $mc) {
                    $missingHtml .= "<li>" . htmlspecialchars($mc) . "</li>";
                }
                $missingHtml .= "</ul>";
                $compName = htmlspecialchars($company['company_name'] ?? 'Bhardwaj Financial Services');
                $custHtml = htmlspecialchars($nameClean);
                $loanHtml = htmlspecialchars((string)($app['loan_type'] ?? 'Loan'));
                $mail->Body = <<<HTML
<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden;'>
    <div style='background: #0f172a; color: #fff; padding: 20px; text-align: center;'>
        <h2 style='margin: 0;'>Pending Document Reminder</h2>
    </div>
    <div style='padding: 25px; color: #334155; line-height: 1.6;'>
        <p>Dear <strong>{$custHtml}</strong>,</p>
        <p>Thank you for initiating your <strong>{$loanHtml}</strong> application with us. We have received your initial documents, however, to process your file swiftly, the following mandatory documents are still pending:</p>
        {$missingHtml}
        <p>Please share clear scanned copies/PDFs of these documents at your earliest convenience on WhatsApp (+91 79009-79001) or reply to this email.</p>
        <p>If you have already submitted these, please ignore this email or contact your loan executive.</p>
        <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 20px 0;' />
        <p style='font-size: 12px; color: #64748b;'>Regards,<br/><strong>{$compName}</strong></p>
    </div>
</div>
HTML;
                $mail->send();
                $emailSent = true;
                $sent_count++;
            } catch (Exception $e) {
                error_log("Document Reminder Email failed for {$app['email']}: {$mail->ErrorInfo}");
            }
        }

        // Dispatch WhatsApp Pending Document Reminder (bfs_doc_request_v1 takes 2 params: {{1}}=name, {{2}}=loan/pending summary)
        if (!empty($app['mobile']) && function_exists('send_bfs_whatsapp_template')) {
            $docParam = $loanLabel . ' (Pending: ' . $missingList . ')';
            if (strlen($docParam) > 120) {
                $docParam = substr($docParam, 0, 117) . '...';
            }
            $waRes = @send_bfs_whatsapp_template((string)$app['mobile'], 'bfs_doc_request_v1', 'en_US', [$nameClean, $docParam], $nameClean);
            if (!empty($waRes['success'])) {
                $waSent = true;
                $wa_sent_count++;
            }
        }

        if ($emailSent || $waSent) {
            $logIns = $db->prepare("INSERT INTO doc_reminders_log (applicant_id, sent_date) VALUES (?, ?)");
            $logIns->execute([$app['id'], $today]);
        }
    }
}

echo json_encode([
    'success' => true,
    'dry_run' => $dry_run,
    'evaluated' => $evaluated,
    'pending_count' => $pending_count,
    'emails_sent' => $sent_count,
    'whatsapp_sent' => $wa_sent_count,
    'date' => $today,
    'details' => $details,
]);
