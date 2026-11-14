<?php
// One-time Sync for Anurag Sikarwar WhatsApp Documents into Main CRM
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=UTF-8');

$mobile = '8958416681';
$cleanMobile = '8958416681';
$loanRef = 'L202610267515';

$appStmt = $db->prepare("SELECT id, loan_id, customer_name, mobile FROM applicants WHERE mobile LIKE ? OR loan_id LIKE ? LIMIT 1");
$appStmt->execute(['%' . $cleanMobile, '%' . $loanRef . '%']);
$applicant = $appStmt->fetch(PDO::FETCH_ASSOC);

if (!$applicant) {
    echo json_encode(['error' => 'Applicant not found on this system.', 'mobile' => $cleanMobile, 'loan_ref' => $loanRef]);
    exit;
}

$docsToSync = [
    [
        'category' => 'KYC',
        'name' => 'Aadhaar_PAN_Photo_1.jpg',
        'url' => 'https://wepvxsrqvumvlqzuiryk.supabase.co/storage/v1/object/public/chat-media/account-03c21714-cd19-42bf-b506-6edcca12fd2a/inbound/1809215830284240-image-1794555736.jpg',
        'type' => 'image/jpeg',
        'notes' => 'Received via WhatsApp Cloud chat'
    ],
    [
        'category' => 'KYC',
        'name' => 'Aadhaar_PAN_Photo_2.jpg',
        'url' => 'https://wepvxsrqvumvlqzuiryk.supabase.co/storage/v1/object/public/chat-media/account-03c21714-cd19-42bf-b506-6edcca12fd2a/inbound/955922754038484-image-1794555736.jpg',
        'type' => 'image/jpeg',
        'notes' => 'Received via WhatsApp Cloud chat'
    ],
    [
        'category' => 'KYC',
        'name' => 'Aadhaar_PAN_Photo_3.jpg',
        'url' => 'https://wepvxsrqvumvlqzuiryk.supabase.co/storage/v1/object/public/chat-media/account-03c21714-cd19-42bf-b506-6edcca12fd2a/inbound/1734422721643338-image-1794555737.jpg',
        'type' => 'image/jpeg',
        'notes' => 'Received via WhatsApp Cloud chat'
    ],
    [
        'category' => 'Banking',
        'name' => 'Bank_Statement_1792566112047.pdf',
        'url' => 'https://wepvxsrqvumvlqzuiryk.supabase.co/storage/v1/object/public/chat-media/account-03c21714-cd19-42bf-b506-6edcca12fd2a/inbound/1184233200942564-Statement_1792566112047.pdf',
        'type' => 'application/pdf',
        'notes' => 'Bank Statement PDF. Customer Password: anur2809'
    ],
    [
        'category' => 'Banking',
        'name' => 'Bank_Statement_1793184054196.pdf',
        'url' => 'https://wepvxsrqvumvlqzuiryk.supabase.co/storage/v1/object/public/chat-media/account-03c21714-cd19-42bf-b506-6edcca12fd2a/inbound/2198276811042768-Statement_1793184054196.pdf',
        'type' => 'application/pdf',
        'notes' => 'Bank Statement PDF. Customer Password: anur2809'
    ],
    [
        'category' => 'Banking',
        'name' => 'Bank_Statement_1793183734384.pdf',
        'url' => 'https://wepvxsrqvumvlqzuiryk.supabase.co/storage/v1/object/public/chat-media/account-03c21714-cd19-42bf-b506-6edcca12fd2a/inbound/1760990768314182-Statement_1793183734384.pdf',
        'type' => 'application/pdf',
        'notes' => 'Bank Statement PDF. Customer Password: anur2809'
    ],
    [
        'category' => 'Banking',
        'name' => 'Bank_Statement_1793183785878.pdf',
        'url' => 'https://wepvxsrqvumvlqzuiryk.supabase.co/storage/v1/object/public/chat-media/account-03c21714-cd19-42bf-b506-6edcca12fd2a/inbound/1472337125000384-Statement_1793183785878.pdf',
        'type' => 'application/pdf',
        'notes' => 'Bank Statement PDF. Customer Password: anur2809'
    ],
    [
        'category' => 'Banking',
        'name' => 'Bank_Statement_1793183869378.pdf',
        'url' => 'https://wepvxsrqvumvlqzuiryk.supabase.co/storage/v1/object/public/chat-media/account-03c21714-cd19-42bf-b506-6edcca12fd2a/inbound/4135350256758339-Statement_1793183869378.pdf',
        'type' => 'application/pdf',
        'notes' => 'Bank Statement PDF. Customer Password: anur2809'
    ]
];

$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0775, true);
}

$results = [];

foreach ($docsToSync as $item) {
    $dbPath = $item['url'];
    $localFilename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $item['name']);
    $dest = $uploadDir . $localFilename;

    $fileData = @file_get_contents($item['url']);
    if ($fileData === false || strlen($fileData) === 0) {
        if (function_exists('curl_init')) {
            $ch = curl_init($item['url']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            $fileData = curl_exec($ch);
            curl_close($ch);
        }
    }

    if ($fileData !== false && strlen($fileData) > 0) {
        if (@file_put_contents($dest, $fileData)) {
            $dbPath = 'uploads/' . $localFilename;
        }
    }

    // Check if already inserted to prevent dupes
    $chk = $db->prepare("SELECT id FROM applicant_documents WHERE applicant_id = ? AND (document_name = ? OR file_path = ?) LIMIT 1");
    $chk->execute([$applicant['id'], $item['name'], $dbPath]);
    $existingDoc = $chk->fetch(PDO::FETCH_ASSOC);

    if (!$existingDoc) {
        $ins = $db->prepare("INSERT INTO applicant_documents (applicant_id, document_category, document_name, file_path, uploaded_at, status, qa_status, notes, file_type, client_mobile) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP, 'Uploaded', 'Under Review', ?, ?, ?)");
        $ins->execute([$applicant['id'], $item['category'], $item['name'], $dbPath, $item['notes'], $item['type'], $cleanMobile]);
        $results[] = ['name' => $item['name'], 'status' => 'attached', 'path' => $dbPath];
    } else {
        $results[] = ['name' => $item['name'], 'status' => 'already_exists'];
    }
}

log_activity("📎 Synced " . count($results) . " WhatsApp documents for applicant {$applicant['customer_name']} (#{$applicant['loan_id']}) with Password: anur2809", "applicant_documents.php?id={$applicant['id']}");

echo json_encode([
    'success' => true,
    'applicant' => $applicant,
    'synced_count' => count($results),
    'details' => $results
], JSON_PRETTY_PRINT);
