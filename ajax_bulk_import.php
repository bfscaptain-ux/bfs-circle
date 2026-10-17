<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

$type = $_GET['type'] ?? 'pre_leads';
$segment_name = trim($_POST['import_segment'] ?? $_POST['segment_name'] ?? $_POST['import_source'] ?? 'General Pool');
if (empty($segment_name)) $segment_name = 'General Pool';

$source = trim($_POST['import_source'] ?? 'CSV Upload');
$service_intent = trim($_POST['import_intent'] ?? 'Loan');

if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'No valid CSV file uploaded']);
    exit;
}

$filePath = $_FILES['csv_file']['tmp_name'];
$handle = fopen($filePath, 'r');
if (!$handle) {
    echo json_encode(['error' => 'Could not open CSV file']);
    exit;
}

// Read header row
$header = fgetcsv($handle);
if (!$header) {
    echo json_encode(['error' => 'CSV file is empty']);
    exit;
}

// Map column indices
$map = [];
foreach ($header as $idx => $col) {
    $colClean = strtolower(trim(preg_replace('/[^a-zA-Z0-9_\s]/', '', $col)));
    if (in_array($colClean, ['name', 'full name', 'customer name', 'lead name', 'client name'])) $map['name'] = $idx;
    elseif (in_array($colClean, ['mobile', 'phone', 'mobile number', 'contact', 'phone number'])) $map['mobile'] = $idx;
    elseif (in_array($colClean, ['email', 'email address', 'mail'])) $map['email'] = $idx;
    elseif (in_array($colClean, ['company', 'company name', 'business'])) $map['company_name'] = $idx;
    elseif (in_array($colClean, ['city', 'location', 'address'])) $map['city'] = $idx;
    elseif (in_array($colClean, ['income', 'monthly income', 'salary'])) $map['monthly_income'] = $idx;
    elseif (in_array($colClean, ['loan amount', 'amount', 'required amount'])) $map['loan_amount_requested'] = $idx;
    elseif (in_array($colClean, ['loan type', 'product', 'service'])) $map['loan_type'] = $idx;
    elseif (in_array($colClean, ['segment', 'segment name', 'batch'])) $map['segment_name'] = $idx;
}

if (!isset($map['mobile']) && !isset($map['name'])) {
    $map['name'] = 0;
    $map['mobile'] = 1;
    if (count($header) > 2) $map['email'] = 2;
}

$db->beginTransaction();
try {
    $existing_mobiles = $db->query("SELECT mobile FROM pre_leads WHERE mobile IS NOT NULL AND mobile != ''")->fetchAll(PDO::FETCH_COLUMN);
    $existing_map = array_flip($existing_mobiles);

    $stmt = $db->prepare("INSERT INTO pre_leads 
        (name, company_name, mobile, email, source, service_intent, status, location, city, monthly_income, loan_amount_requested, loan_type, segment_name, assigned_to, notes) 
        VALUES (?, ?, ?, ?, ?, ?, 'Not Contacted', ?, ?, ?, ?, ?, ?, ?, ?)");

    $imported = 0;
    $skipped = 0;

    while (($row = fgetcsv($handle)) !== false) {
        $name = isset($map['name']) && isset($row[$map['name']]) ? trim($row[$map['name']]) : 'Raw Lead';
        $mobRaw = isset($map['mobile']) && isset($row[$map['mobile']]) ? $row[$map['mobile']] : '';
        $mob = preg_replace('/[^0-9]/', '', $mobRaw);
        $email = isset($map['email']) && isset($row[$map['email']]) ? trim($row[$map['email']]) : '';
        $company = isset($map['company_name']) && isset($row[$map['company_name']]) ? trim($row[$map['company_name']]) : '';
        $city = isset($map['city']) && isset($row[$map['city']]) ? trim($row[$map['city']]) : '';
        $income = isset($map['monthly_income']) && isset($row[$map['monthly_income']]) ? floatval($row[$map['monthly_income']]) : 0;
        $loan_amt = isset($map['loan_amount_requested']) && isset($row[$map['loan_amount_requested']]) ? floatval($row[$map['loan_amount_requested']]) : 0;
        $loan_tp = isset($map['loan_type']) && isset($row[$map['loan_type']]) ? trim($row[$map['loan_type']]) : $service_intent;
        $row_seg = isset($map['segment_name']) && isset($row[$map['segment_name']]) && !empty(trim($row[$map['segment_name']])) ? trim($row[$map['segment_name']]) : $segment_name;

        if (empty($mob) || strlen($mob) < 10) {
            $skipped++;
            continue;
        }

        if (strlen($mob) > 10) $mob = substr($mob, -10);

        if (isset($existing_map[$mob])) {
            $skipped++;
            continue;
        }

        $existing_map[$mob] = true;
        $imported++;

        $stmt->execute([
            $name,
            $company,
            $mob,
            $email,
            $source,
            $service_intent,
            $city,
            $city,
            $income,
            $loan_amt,
            $loan_tp,
            $row_seg,
            '',
            "Imported via CSV Batch on " . date('Y-m-d H:i')
        ]);
    }

    fclose($handle);
    $db->commit();
    log_activity("Imported $imported raw pre-leads into segment '$segment_name' ($skipped skipped/duplicates)");
    echo json_encode([
        'success' => true,
        'message' => "Successfully imported $imported leads into segment '$segment_name' ($skipped skipped as duplicates).",
        'imported_count' => $imported,
        'skipped_count' => $skipped
    ]);
} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    fclose($handle);
    echo json_encode(['error' => 'Failed to process CSV file: ' . $e->getMessage()]);
}
