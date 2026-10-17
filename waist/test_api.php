<?php
$db = new PDO("sqlite:crm.db");
$co_stmt = $db->prepare("SELECT * FROM co_applicants WHERE applicant_id = 16");
$co_stmt->execute();
print_r($co_stmt->fetchAll(PDO::FETCH_ASSOC));
?>
