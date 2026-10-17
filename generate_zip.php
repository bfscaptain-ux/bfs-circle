<?php
require_once 'config.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    die("Invalid request.");
}

// Check admin/user session if needed
if (!isset($_SESSION['user_id'])) {
    die("Unauthorized access.");
}

// Fetch applicant
$stmt = $db->prepare("SELECT * FROM applicants WHERE id = ?");
$stmt->execute([$id]);
$applicant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$applicant) {
    die("Applicant not found.");
}

$loan_id = $applicant['loan_id'] ?: 'L'.$id;
$customer_name = preg_replace('/[^A-Za-z0-9_-]/', '_', $applicant['customer_name']);
$zip_filename = "Bundle_" . $loan_id . "_" . $customer_name . ".zip";
$zip_path = sys_get_temp_dir() . '/' . $zip_filename;

$zip = new ZipArchive();
if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    die("Could not create ZIP file.");
}

// 1. Generate Applicant Profile Text
$profile_text = "APPLICANT PROFILE\n";
$profile_text .= "=================\n\n";
$profile_text .= "Loan ID: " . $applicant['loan_id'] . "\n";
$profile_text .= "Name: " . $applicant['customer_name'] . "\n";
$profile_text .= "Mobile: " . $applicant['mobile'] . "\n";
$profile_text .= "Email: " . $applicant['email'] . "\n";
$profile_text .= "Address: " . $applicant['address'] . ", " . $applicant['city'] . ", " . $applicant['state'] . " - " . $applicant['pincode'] . "\n\n";

$profile_text .= "KYC DETAILS\n";
$profile_text .= "-----------\n";
$profile_text .= "PAN Number: " . $applicant['pan_number'] . "\n";
$profile_text .= "Aadhaar Number: " . $applicant['aadhar_number'] . "\n\n";

$profile_text .= "FINANCIAL & LOAN DETAILS\n";
$profile_text .= "------------------------\n";
$profile_text .= "Employment Type: " . $applicant['employment_type'] . "\n";
$profile_text .= "Monthly Income: INR " . number_format((float)$applicant['monthly_income'], 2) . "\n";
$profile_text .= "Loan Type: " . $applicant['loan_type'] . " (" . $applicant['loan_sub_type'] . ")\n";
$profile_text .= "Requested Amount: INR " . number_format((float)$applicant['loan_amount_requested'], 2) . "\n";
$profile_text .= "Expected Tenure: " . $applicant['tenure_months'] . " months\n\n";

$profile_text .= "SOURCING\n";
$profile_text .= "--------\n";
$profile_text .= "Lead Source: " . $applicant['lead_source'] . "\n";

if ($applicant['sanctioned_amount'] > 0) {
    $profile_text .= "\nBANK SANCTION\n";
    $profile_text .= "-------------\n";
    $profile_text .= "CIBIL Score: " . $applicant['cibil_score'] . "\n";
    $profile_text .= "Sanctioned Amount: INR " . number_format((float)$applicant['sanctioned_amount'], 2) . "\n";
    $profile_text .= "ROI: " . $applicant['interest_rate'] . "%\n";
    $profile_text .= "Approved EMI: INR " . number_format((float)$applicant['emi'], 2) . "\n";
}

$zip->addFromString("00_Applicant_Profile.txt", $profile_text);

// 2. Add Documents organized into clean categorized folders
$stmt_docs = $db->prepare("
    SELECT d.*, c.full_name as coapp_name 
    FROM applicant_documents d 
    LEFT JOIN co_applicants c ON d.owner_id = c.id 
    WHERE d.applicant_id = ?
");
$stmt_docs->execute([$id]);
$documents = $stmt_docs->fetchAll(PDO::FETCH_ASSOC);

$added_files = [];
foreach ($documents as $doc) {
    $file_path = $doc['file_path'];
    if (file_exists($file_path)) {
        $ext = pathinfo($file_path, PATHINFO_EXTENSION);
        $safe_name = preg_replace('/[^A-Za-z0-9_-]/', '_', $doc['document_name']);
        
        $cat = $doc['document_category'] ?? 'Other';
        $subfolder = '05_Other_Documents';
        if ($doc['owner_type'] === 'CoApplicant') {
            $subfolder = '04_Co_Applicants';
        } elseif (stripos($cat, 'KYC') !== false) {
            $subfolder = '01_Basic_KYC';
        } elseif (stripos($cat, 'Income') !== false || stripos($cat, 'Business') !== false) {
            $subfolder = '02_Income_Financials';
        } elseif (stripos($cat, 'Property') !== false || stripos($cat, 'Asset') !== false) {
            $subfolder = '03_Property_Asset_Docs';
        } elseif (stripos($cat, 'Vehicle') !== false) {
            $subfolder = '03_Vehicle_Documents';
        }
        
        $doc_name = $safe_name;
        if (isset($added_files[$subfolder . '/' . $doc_name])) {
            $added_files[$subfolder . '/' . $doc_name]++;
            $doc_name .= "_" . $added_files[$subfolder . '/' . $doc_name];
        } else {
            $added_files[$subfolder . '/' . $doc_name] = 1;
        }
        
        $doc_name .= "." . $ext;
        
        $zip->addFile($file_path, $subfolder . "/" . $doc_name);
    }
}

$zip->close();

// 3. Force download
header('Content-Type: application/zip');
header('Content-disposition: attachment; filename=' . $zip_filename);
header('Content-Length: ' . filesize($zip_path));
readfile($zip_path);
unlink($zip_path);
exit;
?>
