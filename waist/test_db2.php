<?php
$db = new PDO("sqlite:crm.db");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "--- Applicants ---\n";
$stmt = $db->query("SELECT id, customer_name, lead_source, referral_id FROM applicants ORDER BY id DESC LIMIT 3");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "--- Co-Applicants ---\n";
$stmt = $db->query("SELECT id, applicant_id, full_name, relationship, is_financial FROM co_applicants ORDER BY id DESC LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
