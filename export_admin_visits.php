<?php
require_once 'config.php';

require_role(['Admin'], false);

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=All_Field_Visits_' . date('Y-m-d') . '.csv');

$output = fopen('php://output', 'w');
// Add CSV BOM to fix UTF-8 encoding in Excel
fputs($output, "\xEF\xBB\xBF");

// Add headers
fputcsv($output, ['Visit Date', 'Executive Name', 'Firm Name', 'Person Name', 'Mobile', 'Alt Mobile', 'Profession', 'State', 'City', 'Pincode', 'Address', 'Lead Quality']);

// Fetch ALL data from field_visits table
$stmt = $db->query("SELECT * FROM field_visits ORDER BY created_at DESC");
$visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($visits as $row) {
    fputcsv($output, [
        date('d M Y', strtotime($row['created_at'])),
        $row['executive_name'],
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
