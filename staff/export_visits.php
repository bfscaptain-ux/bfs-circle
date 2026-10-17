<?php
define('IS_SUBFOLDER', true);
require_once __DIR__ . '/../config.php';
require_role(['Staff']);


$username = $_SESSION['username'];
$stmt = $db->prepare("SELECT department FROM employees WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$department = $stmt->fetchColumn() ?: 'Lead Generation Team';


// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=My_Field_Visits_' . date('Y-m-d') . '.csv');

$output = fopen('php://output', 'w');
// Add CSV BOM to fix UTF-8 encoding in Excel
fputs($output, "\xEF\xBB\xBF");

// Add headers
fputcsv($output, ['Visit Date', 'Firm Name', 'Person Name', 'Mobile', 'Alt Mobile', 'Profession', 'State', 'City', 'Pincode', 'Address', 'Lead Quality']);

// Fetch data
$stmt = $db->prepare("SELECT * FROM field_visits WHERE executive_name = ? ORDER BY created_at DESC");
$stmt->execute([$username]);
$visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($visits as $row) {
    fputcsv($output, [
        date('d M Y', strtotime($row['created_at'])),
        $row['firm_name'],
        $row['person_name'],
        "'" . $row['mobile'],
        "'" . $row['alt_mobile'],
        $row['profession'] === 'OTHER' ? $row['custom_profession'] : $row['profession'],
        $row['state'],
        $row['city'],
        $row['pincode'],
        $row['full_address'],
        $row['lead_quality']
    ]);
}

fclose($output);
exit;
