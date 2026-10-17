<?php
define('IS_SUBFOLDER', true);
require_once '../config.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'RM'])) {
    die("Access Denied");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $existing_id = intval($_POST['applicant_id'] ?? $_POST['existing_applicant_id'] ?? 0);
    $loan_type = trim($_POST['loan_type'] ?? $_POST['product_type'] ?? '');
    $loan_sub_type = trim($_POST['loan_sub_type'] ?? '');
    $loan_amount_requested = (float)($_POST['loan_amount_requested'] ?? 0);
    $tenure_months = (int)($_POST['tenure_months'] ?? 36);
    $notes = trim($_POST['pitch_notes'] ?? $_POST['notes'] ?? '');
    $clone_documents = isset($_POST['clone_documents']) ? (int)$_POST['clone_documents'] : 1;
    $clone_coapplicants = isset($_POST['clone_coapplicants']) ? (int)$_POST['clone_coapplicants'] : 1;

    if ($existing_id <= 0 || empty($loan_type)) {
        header("Location: index.php?error=invalid_params");
        exit;
    }

    if ($loan_amount_requested <= 0) {
        $loan_amount_requested = 500000; // default 5L if not filled in simple pitch
    }

    // Fetch existing
    $stmt = $db->prepare("SELECT * FROM applicants WHERE id = ?");
    $stmt->execute([$existing_id]);
    $old = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$old) {
        header("Location: index.php?error=not_found");
        exit;
    }

    // Generate unique loan_id
    $yearMonth = date('Ym');
    $base_count = (int)$db->query("SELECT COUNT(*) FROM applicants")->fetchColumn() + 1;
    $chk_stmt = $db->prepare("SELECT id FROM applicants WHERE loan_id = ?");
    do {
        $new_loan_id = "L" . $yearMonth . str_pad($base_count, 4, '0', STR_PAD_LEFT);
        $chk_stmt->execute([$new_loan_id]);
        $exists = $chk_stmt->fetchColumn();
        if ($exists) $base_count++;
    } while ($exists);

    $now = date('Y-m-d H:i:s');
    $added_by = $_SESSION['username'] ?? 'Admin';
    $lead_source = !empty($notes) ? "Client Vault (Activated: " . substr($notes, 0, 45) . ")" : "Client Vault / Repeat Application";

    $ins_stmt = $db->prepare("
        INSERT INTO applicants (
            loan_id, customer_name, mobile, email, pincode, state, city, address, 
            pan_number, aadhar_number, employment_type, organization_name, organization_address, 
            monthly_income, loan_type, loan_sub_type, loan_amount_requested, tenure_months, 
            lead_source, referral_id, employee_id, assigned_to, assigned_by, assigned_at, 
            overall_status, created_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, 
            ?, ?, ?, ?, ?, 
            ?, ?, ?, ?, ?, 
            ?, ?, ?, ?, ?, ?, 
            'Phase 1', ?
        )
    ");

    $ins_stmt->execute([
        $new_loan_id,
        $old['customer_name'],
        $old['mobile'],
        $old['email'] ?? '',
        $old['pincode'] ?? '',
        $old['state'] ?? '',
        $old['city'] ?? '',
        $old['address'] ?? '',
        $old['pan_number'] ?? '',
        $old['aadhar_number'] ?? '',
        $old['employment_type'] ?? 'Salaried',
        $old['organization_name'] ?? '',
        $old['organization_address'] ?? '',
        $old['monthly_income'] ?? 0,
        $loan_type,
        $loan_sub_type,
        $loan_amount_requested,
        $tenure_months,
        $lead_source,
        $old['referral_id'] ?? '',
        $old['employee_id'] ?? '',
        $old['assigned_to'] ?? '',
        $added_by,
        $now,
        $now
    ]);

    $new_applicant_id = $db->lastInsertId();

    // Clone co-applicants
    if ($clone_coapplicants) {
        $co_stmt = $db->prepare("SELECT * FROM co_applicants WHERE applicant_id = ?");
        $co_stmt->execute([$existing_id]);
        $co_apps = $co_stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($co_apps) {
            $ins_co = $db->prepare("
                INSERT INTO co_applicants (
                    applicant_id, is_financial, relationship, full_name, mobile, email, dob, 
                    pan_number, aadhar_number, same_address, address, pincode, state, city, 
                    employment_type, monthly_income, current_emis
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            foreach ($co_apps as $co) {
                $ins_co->execute([
                    $new_applicant_id, $co['is_financial'], $co['relationship'], $co['full_name'], $co['mobile'],
                    $co['email'], $co['dob'], $co['pan_number'], $co['aadhar_number'], $co['same_address'],
                    $co['address'], $co['pincode'], $co['state'], $co['city'], $co['employment_type'],
                    $co['monthly_income'], $co['current_emis']
                ]);
            }
        }
    }

    // Clone documents
    $cloned_docs = 0;
    if ($clone_documents) {
        $docs_stmt = $db->prepare("SELECT * FROM applicant_documents WHERE applicant_id = ?");
        $docs_stmt->execute([$existing_id]);
        $old_docs = $docs_stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($old_docs) {
            $ins_doc = $db->prepare("
                INSERT INTO applicant_documents (
                    applicant_id, client_mobile, client_pan, document_category, document_name, 
                    file_path, file_size, file_ext, status, notes, owner_type, owner_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            foreach ($old_docs as $doc) {
                $ins_doc->execute([
                    $new_applicant_id, $old['mobile'], $old['pan_number'], $doc['document_category'],
                    $doc['document_name'], $doc['file_path'], $doc['file_size'] ?? 0, $doc['file_ext'] ?? '',
                    $doc['status'] ?? 'Verified', 'Carried forward from Vault file #' . $old['loan_id'],
                    $doc['owner_type'] ?? 'Applicant', $doc['owner_id'] ?? null
                ]);
                $cloned_docs++;
            }
        }
    }

    header("Location: ../applicant_documents.php?id=" . $new_applicant_id . "&activated=1&loan_id=" . urlencode($new_loan_id));
    exit;
}

header("Location: index.php");
exit;
