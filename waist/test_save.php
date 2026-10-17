<?php
$ch = curl_init("http://localhost:8000/api.php?api=save_applicant");
$data = [
    "id" => "16",
    "customer_name" => "Anuj",
    "mobile" => "9999999999",
    "lead_source" => "Referral Partner / Agent",
    "referral_id" => "CA-DEMO",
    "coapp_name" => ["WASEEM AKRAM"],
    "coapp_mobile" => ["8077084603"],
    "coapp_relationship" => ["Brother"],
    "coapp_is_financial" => ["No"]
];
curl_setopt($ch, CURLOPT_POST, 1);
$post = http_build_query($data);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "HTTP $httpcode\n";
echo "Response: $response\n";
?>
