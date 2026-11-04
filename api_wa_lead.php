<?php
// ==============================================================================
// BFS Circle - Inbound WhatsApp Lead Webhook Endpoint
// Receives leads captured by WhatsApp Automation/Bot (wa.bfsfin.cloud)
// and creates active leads in Main Loan CRM (crm.bfsfin.cloud)
// ==============================================================================

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=UTF-8');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Only POST is accepted.']);
    exit;
}

$rawInput = file_get_contents('php://input');
$payload = json_decode($rawInput, true);

if (!$payload || !is_array($payload)) {
    // Support standard form-urlencoded POST
    $payload = $_POST;
}

// Handle wacrm webhook format if wrapped in event payload
if (isset($payload['event']) && isset($payload['data'])) {
    $eventData = $payload['data'];
    $payload = array_merge($payload, is_array($eventData) ? $eventData : []);
}

// Extract lead attributes
$name = trim($payload['name'] ?? $payload['customer_name'] ?? $payload['lead_name'] ?? '');
$mobile = trim($payload['phone'] ?? $payload['mobile'] ?? '');
$email = trim($payload['email'] ?? '');
$loanType = trim($payload['loan_type'] ?? $payload['requirement'] ?? 'Loan Inquiry');
$loanAmount = trim($payload['loan_amount'] ?? $payload['amount'] ?? '');
$city = trim($payload['city'] ?? $payload['location'] ?? 'Agra');
$notes = trim($payload['notes'] ?? $payload['message_text'] ?? $payload['message'] ?? 'Lead captured via Official WhatsApp Automation (+91 9286683232)');
$contactId = trim($payload['contact_id'] ?? '');
$mediaUrl = trim($payload['media_url'] ?? $payload['mediaUrl'] ?? '');
$mediaType = trim($payload['media_type'] ?? $payload['mediaType'] ?? $payload['content_type'] ?? '');
$filename = trim($payload['filename'] ?? '');

// If mobile is missing but contact_id is provided, resolve from WhatsApp CRM API
if ((empty($mobile) || strlen($mobile) < 10) && !empty($contactId)) {
    $cApiUrl = (defined('WACRM_API_URL') ? WACRM_API_URL : 'https://wa.bfsfin.cloud/api/v1') . '/contacts/' . urlencode($contactId);
    $cApiKey = defined('WACRM_API_KEY') ? WACRM_API_KEY : '';
    $ch = curl_init($cApiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if (!empty($cApiKey)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $cApiKey,
            'Content-Type: application/json'
        ]);
    }
    curl_setopt($ch, CURLOPT_TIMEOUT, 6);
    $cRes = curl_exec($ch);
    curl_close($ch);
    if ($cRes) {
        $cData = json_decode($cRes, true);
        $contactObj = $cData['data'] ?? $cData;
        if (!empty($contactObj) && isset($contactObj['phone'])) {
            $mobile = $contactObj['phone'];
            if (empty($name) && !empty($contactObj['name'])) {
                $name = $contactObj['name'];
            }
            if (empty($email) && !empty($contactObj['email'])) {
                $email = $contactObj['email'];
            }
        }
    }
}

// Normalize mobile number
$cleanMobile = preg_replace('/\D/', '', $mobile);
if (strlen($cleanMobile) === 12 && substr($cleanMobile, 0, 2) === '91') {
    $cleanMobile = substr($cleanMobile, 2);
} elseif (strlen($cleanMobile) === 11 && substr($cleanMobile, 0, 1) === '0') {
    $cleanMobile = substr($cleanMobile, 1);
}

if (empty($cleanMobile) || strlen($cleanMobile) < 10) {
    http_response_code(400);
    echo json_encode(['error' => 'Valid 10-digit mobile number is required.', 'received' => $payload]);
    exit;
}

if (empty($name)) {
    $name = 'WhatsApp User ' . substr($cleanMobile, -4);
}

try {
    // 0. If this is an incoming document/media from WhatsApp, attach to Applicant / Loan File
    if (!empty($mediaUrl)) {
        $appStmt = $db->prepare("SELECT id, customer_name FROM applicants WHERE mobile = ? OR mobile = ? OR mobile LIKE ? LIMIT 1");
        $appStmt->execute([$cleanMobile, '91' . $cleanMobile, '%' . substr($cleanMobile, -10)]);
        $applicant = $appStmt->fetch(PDO::FETCH_ASSOC);

        $docName = !empty($filename) ? $filename : 'WhatsApp_Doc_' . date('Ymd_His');
        if (!empty($mediaType) && strpos($docName, '.') === false) {
            $ext = strpos($mediaType, 'pdf') !== false ? '.pdf' : (strpos($mediaType, 'png') !== false ? '.png' : '.jpg');
            $docName .= $ext;
        }

        // Try downloading to local uploads folder if possible
        $dbFilePath = $mediaUrl;
        $uploadDir = __DIR__ . '/uploads/';
        if (is_dir($uploadDir) && is_writable($uploadDir)) {
            $savedFilename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $docName);
            $localDest = $uploadDir . $savedFilename;
            $fileData = @file_get_contents($mediaUrl);
            if ($fileData !== false && strlen($fileData) > 0) {
                if (@file_put_contents($localDest, $fileData)) {
                    $dbFilePath = 'uploads/' . $savedFilename;
                }
            }
        }

        if ($applicant) {
            $insDoc = $db->prepare("INSERT INTO applicant_documents (applicant_id, document_category, document_name, file_path, uploaded_at, status, qa_status, notes, file_type, client_mobile) VALUES (?, 'WhatsApp Upload', ?, ?, CURRENT_TIMESTAMP, 'Uploaded', 'Under Review', 'Received via WhatsApp Cloud API (+91 9286683232)', ?, ?)");
            $insDoc->execute([$applicant['id'], $docName, $dbFilePath, $mediaType, $cleanMobile]);

            log_activity("📎 WhatsApp document attached for applicant {$applicant['customer_name']}: $docName (Under 24-Hour Review)", "applicant_documents.php?id={$applicant['id']}");

            // Immediate receipt notification to customer with 24-Hour SLA notice
            if (function_exists('send_bfs_whatsapp_cloud_api')) {
                $receiptNotice = "📥 *Document Received — Under Verification*\n\nNamaste {$applicant['customer_name']} ji,\n\nAapka document *{$docName}* hamare verification portal par safely receive ho gaya hai.\n\n⏱️ *Verification Notice (Within 24 Hours):*\nHamari Credit & Legal Team is document ko bank guidelines ke mutabik verify karegi. Ye document *agle 24 ghante ke andar* review & approve hoga. Approval ya rejection ka status aapko yahan WhatsApp par mil jayega.\n\nDhanyawad,\nBhardwaj Financial Services (+91 9286-683-232)";
                @send_bfs_whatsapp_cloud_api($cleanMobile, $receiptNotice, $applicant['customer_name']);
            }

            echo json_encode([
                'success' => true,
                'action' => 'document_attached',
                'applicant_id' => $applicant['id'],
                'document_name' => $docName,
                'file_path' => $dbFilePath,
                'qa_status' => 'Under Review',
                'message' => "Document successfully saved to Loan File of '{$applicant['customer_name']}' under 24-hr review!"
            ]);
            exit;
        }
    }

    // 1. Check if lead already exists by mobile
    $stmt = $db->prepare("SELECT id, lead_name, stage FROM leads WHERE mobile = ? OR mobile = ? LIMIT 1");
    $stmt->execute([$cleanMobile, '91' . $cleanMobile]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        // Update existing lead notes and requirement
        $updateNote = "\n[" . date('d-m-Y H:i') . " WhatsApp Bot]: Loan Req: $loanType ($loanAmount), Notes: $notes" . (!empty($mediaUrl) ? " | Document: $mediaUrl" : "");
        $up = $db->prepare("UPDATE leads SET requirement = COALESCE(NULLIF(?, ''), requirement), loan_amount = COALESCE(NULLIF(?, ''), loan_amount), notes = notes || ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $up->execute([$loanType, $loanAmount, $updateNote, $existing['id']]);

        log_activity("WhatsApp Automation updated existing lead: {$existing['lead_name']} ($cleanMobile)", "leads.php?edit_lead={$existing['id']}");

        echo json_encode([
            'success' => true,
            'action' => 'updated',
            'lead_id' => $existing['id'],
            'message' => 'Existing lead updated with WhatsApp automation details.'
        ]);
        exit;
    }

    // 2. Insert new lead
    $stmt = $db->prepare("INSERT INTO leads (lead_name, company_name, mobile, email, lead_source, priority, stage, assigned_to, location, notes, requirement, loan_amount, created_at, updated_at) VALUES (?, 'Individual', ?, ?, 'WhatsApp Automation', 'High', 'New Lead', 'Unassigned', ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
    $stmt->execute([$name, $cleanMobile, $email, $city, $notes, $loanType, $loanAmount]);
    $newLeadId = $db->lastInsertId();

    log_activity("🎉 New WhatsApp Lead received: $name ($cleanMobile) for $loanType ($loanAmount)", "leads.php?edit_lead=$newLeadId");

    echo json_encode([
        'success' => true,
        'action' => 'created',
        'lead_id' => $newLeadId,
        'message' => "Lead '$name' successfully added to CRM from WhatsApp Automation!"
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
