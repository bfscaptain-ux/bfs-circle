<?php
// api_website_lead.php - Ingestion webhook for Website Leads, Appointments & Partner Requests
// Accepts JSON or POST form data from the website frontend (bfsfin.com / BFS 5826)

$is_cli = (php_sapi_name() === 'cli');

if (!$is_cli) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');
    header('Content-Type: application/json; charset=utf-8');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

require_once __DIR__ . '/config.php';

try {
    // 1. Parse Input Data (CLI argument, JSON raw body, or $_POST)
    $input = [];
    if ($is_cli) {
        $raw = '';
        if (isset($argv[1]) && $argv[1] === '--base64' && isset($argv[2])) {
            $raw = base64_decode($argv[2]);
        } elseif (isset($argv[1]) && $argv[1] === '--cli' && isset($argv[2])) {
            $raw = $argv[2];
        } elseif (isset($argv[1])) {
            $raw = $argv[1];
        } else {
            $raw = @file_get_contents('php://stdin');
        }
        $input = json_decode($raw, true) ?: [];
    } else {
        $raw_body = file_get_contents('php://input');
        if (!empty($raw_body)) {
            $decoded = json_decode($raw_body, true);
            if (is_array($decoded)) {
                $input = $decoded;
            }
        }
        if (empty($input) && !empty($_POST)) {
            $input = $_POST;
        }
    }

    if (empty($input)) {
        echo json_encode(['success' => false, 'error' => 'No input data received']);
        exit;
    }

    // 2. Extract common attributes
    $lead_name = trim($input['name'] ?? $input['lead_name'] ?? 'Website User');
    $mobile = trim($input['phone'] ?? $input['mobile'] ?? '');
    $email = trim($input['email'] ?? '');
    $raw_source = trim($input['source'] ?? '');
    $form_type = trim($input['formType'] ?? '');
    $city = trim($input['city'] ?? 'Agra');
    $state = trim($input['state'] ?? 'UP');
    $loan_type = trim($input['loanType'] ?? '');
    $product_type = trim($input['productType'] ?? '');
    $sub_type = trim($input['subType'] ?? '');
    $message = trim($input['message'] ?? '');

    // =========================================================================
    // CASE A: PARTNER PORTAL REGISTRATION REQUEST (/partner)
    // =========================================================================
    $is_partner = ($form_type === 'Partner Registration' || 
                   $loan_type === 'B2B_PARTNERSHIP' || 
                   stripos($raw_source, 'Partner') !== false ||
                   isset($input['profession']) || 
                   isset($input['monthlyVolume']));

    if ($is_partner) {
        $profession = trim($input['profession'] ?? 'Partner / DSA');
        $experience = trim($input['experience'] ?? '1-3 Years');
        $monthly_volume = trim($input['monthlyVolume'] ?? $input['monthly_volume'] ?? '₹25L - ₹50L');

        // Check recent duplicate
        if (!empty($mobile)) {
            $chk = $db->prepare("SELECT id FROM partner_requests WHERE phone = ? AND strftime('%s', 'now') - strftime('%s', created_at) < 180 LIMIT 1");
            $chk->execute([$mobile]);
            $exist_id = $chk->fetchColumn();
            if ($exist_id) {
                echo json_encode(['success' => true, 'id' => $exist_id, 'type' => 'partner', 'duplicate' => true]);
                exit;
            }
        }

        $ins_p = $db->prepare("INSERT INTO partner_requests (
            name, phone, email, profession, experience, monthly_volume, city, state, message, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending Review', datetime('now', 'localtime'))");

        $ins_p->execute([
            $lead_name,
            $mobile,
            $email,
            $profession,
            $experience,
            $monthly_volume,
            $city,
            $state,
            $message
        ]);

        $partner_id = (int)$db->lastInsertId();

        // Notification & Activity Log
        try {
            $admin_id = $db->query("SELECT id FROM users WHERE role = 'Admin' ORDER BY id ASC LIMIT 1")->fetchColumn() ?: 1;
            $notif = "New B2B Partner Request: {$lead_name} ({$profession}) from {$city}";
            $db->prepare("INSERT INTO notifications (user_id, message, type, is_read, created_at) VALUES (?, ?, 'partner', 0, datetime('now', 'localtime'))")->execute([$admin_id, $notif]);
            log_activity("New Partner Registration: {$lead_name} ({$profession})", "partner_requests.php");
        } catch (Exception $e) {}

        echo json_encode([
            'success' => true,
            'id' => $partner_id,
            'type' => 'partner',
            'message' => 'Partner request successfully received in CRM'
        ]);
        exit;
    }

    // =========================================================================
    // CASE B: APPOINTMENT CONSULTATION BOOKING (/appointment)
    // =========================================================================
    $is_appointment = ($form_type === 'Appointment Booking' || 
                       stripos($raw_source, 'APPOINTMENT') !== false ||
                       !empty($input['slotDate']));

    if ($is_appointment) {
        $slot_date = trim($input['slotDate'] ?? '');
        $slot_time = trim($input['slotTime'] ?? '');

        // If slot parts in raw_source: APPOINTMENT: 28 Oct | 11:30 AM
        if (empty($slot_date) && stripos($raw_source, 'APPOINTMENT:') !== false) {
            $parts = explode('|', str_replace('APPOINTMENT:', '', $raw_source));
            $slot_date = trim($parts[0] ?? '');
            $slot_time = trim($parts[1] ?? '');
        }

        $p_type = $product_type ?: ($loan_type ?: 'Finance');
        $s_type = $sub_type ?: 'Consultation';

        // 1. Insert into website_appointments table
        $ins_a = $db->prepare("INSERT INTO website_appointments (
            name, phone, email, product_type, sub_type, slot_date, slot_time, city, status, message, source, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Scheduled', ?, ?, datetime('now', 'localtime'))");

        $ins_a->execute([
            $lead_name,
            $mobile,
            $email,
            $p_type,
            $s_type,
            $slot_date,
            $slot_time,
            $city,
            $message,
            $raw_source ?: 'Website Appointment'
        ]);

        $appt_id = (int)$db->lastInsertId();

        // 2. Also register in Leads table as 'Scheduled' Hot Lead
        $lead_id = 0;
        try {
            $ins_l = $db->prepare("INSERT INTO leads (
                lead_name, company_name, mobile, email, requirement, loan_amount, 
                lead_source, priority, stage, notes, location, added_by, created_at
            ) VALUES (?, 'Individual', ?, ?, ?, 3500000, ?, 'Hot', 'Scheduled', ?, ?, 'Website', datetime('now', 'localtime'))");

            $req_desc = "{$p_type} - {$s_type}";
            $src_desc = "Website (Appointment: {$slot_date} {$slot_time})";
            $notes_desc = "Booked Slot: {$slot_date} {$slot_time} | City: {$city} | " . $message;

            $ins_l->execute([
                $lead_name,
                $mobile,
                $email,
                $req_desc,
                $src_desc,
                $notes_desc,
                $city
            ]);
            $lead_id = (int)$db->lastInsertId();

            // Link lead_id back to appointment
            $db->prepare("UPDATE website_appointments SET lead_id = ? WHERE id = ?")->execute([$lead_id, $appt_id]);
        } catch (Exception $e_lead) {}

        // 3. Auto-generate Reminder in reminders table
        try {
            $rem_date = date('Y-m-d H:i:s');
            // Try parsing slot date if possible
            if (!empty($slot_date)) {
                $parsed_t = strtotime($slot_date);
                if ($parsed_t !== false) {
                    $rem_date = date('Y-m-d 09:30:00', $parsed_t);
                }
            }
            $ins_rem = $db->prepare("INSERT INTO reminders (
                lead_id, assigned_to, remind_at, notes, status, created_at, title, priority, reference_type
            ) VALUES (?, 'admin', ?, ?, 'Pending', datetime('now', 'localtime'), ?, 'High', 'Appointment')");

            $rem_title = "Website Appointment: {$lead_name} ({$slot_date} {$slot_time})";
            $ins_rem->execute([$lead_id, $rem_date, $message ?: $rem_title, $rem_title]);
        } catch (Exception $e_rem) {}

        // Notification & Activity Log
        try {
            $admin_id = $db->query("SELECT id FROM users WHERE role = 'Admin' ORDER BY id ASC LIMIT 1")->fetchColumn() ?: 1;
            $notif = "New Appointment Scheduled: {$lead_name} for {$slot_date} {$slot_time}";
            $db->prepare("INSERT INTO notifications (user_id, message, type, is_read, created_at) VALUES (?, ?, 'appointment', 0, datetime('now', 'localtime'))")->execute([$admin_id, $notif]);
            log_activity("New Appointment Scheduled: {$lead_name} ({$slot_date} {$slot_time})", "appointments.php");
        } catch (Exception $e) {}

        echo json_encode([
            'success' => true,
            'id' => $appt_id,
            'lead_id' => $lead_id,
            'type' => 'appointment',
            'message' => 'Appointment scheduled and synced to CRM'
        ]);
        exit;
    }

    // =========================================================================
    // CASE C: REGULAR LOAN / SERVICE APPLY FORM (/apply)
    // =========================================================================
    if (empty($mobile) && empty($email)) {
        echo json_encode(['success' => false, 'error' => 'Phone or Email is required']);
        exit;
    }

    // Determine Requirement
    if (!empty($loan_type)) {
        $requirement = $loan_type;
    } elseif (!empty($product_type) && !empty($sub_type)) {
        $requirement = $product_type . ' - ' . $sub_type;
    } elseif (!empty($product_type)) {
        $requirement = $product_type;
    } else {
        $requirement = 'Finance - Home Loan';
    }

    $loan_amount = 0;
    if (isset($input['loanAmount'])) {
        $loan_amount = floatval(preg_replace('/[^0-9.]/', '', (string)$input['loanAmount']));
    } elseif (isset($input['loan_amount'])) {
        $loan_amount = floatval(preg_replace('/[^0-9.]/', '', (string)$input['loan_amount']));
    }

    $income = trim((string)($input['income'] ?? ''));
    $employment = trim($input['employmentType'] ?? $input['empType'] ?? 'Salaried');
    $location = trim($input['location'] ?? $input['userLocation'] ?? $city ?: 'Agra');
    $secondary_mobile = trim($input['secondary_mobile'] ?? $input['alt_phone'] ?? '');

    $lead_source = !empty($form_type) ? "Website ({$form_type})" : (!empty($raw_source) ? "Website ({$raw_source})" : "Website (Apply Form)");
    $priority = ($loan_amount >= 2500000) ? 'Hot' : 'Warm';

    // Duplicate check within 2 minutes
    if (!empty($mobile)) {
        $chk_l = $db->prepare("SELECT id FROM leads WHERE mobile = ? AND strftime('%s', 'now') - strftime('%s', created_at) < 120 ORDER BY id DESC LIMIT 1");
        $chk_l->execute([$mobile]);
        $existing_lead_id = $chk_l->fetchColumn();
        if ($existing_lead_id) {
            echo json_encode(['success' => true, 'lead_id' => $existing_lead_id, 'type' => 'lead', 'duplicate' => true]);
            exit;
        }
    }

    // Detailed notes
    $notes_arr = [];
    if (!empty($requirement)) $notes_arr[] = "Product: " . $requirement;
    if (!empty($employment))  $notes_arr[] = "Employment: " . $employment;
    if (!empty($income))      $notes_arr[] = "Income: " . (is_numeric($income) ? '₹' . number_format((float)$income) : $income);
    if (!empty($city))        $notes_arr[] = "City: " . $city;
    if (!empty($input['panNumber'])) $notes_arr[] = "PAN: " . $input['panNumber'];
    if (!empty($input['refId']))     $notes_arr[] = "App ID: " . $input['refId'];
    if (!empty($message))            $notes_arr[] = "Message: " . $message;
    $notes = implode(" | ", $notes_arr);

    $company = (strtolower($employment) === 'business' || strtolower($employment) === 'self-employed') ? 'Business Enterprise' : 'Individual';

    $ins_lead = $db->prepare("INSERT INTO leads (
        lead_name, company_name, mobile, secondary_mobile, email, 
        requirement, loan_amount, lead_source, priority, stage, 
        notes, location, source_data, added_by, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'New Lead', ?, ?, ?, 'Website', datetime('now', 'localtime'))");

    $ins_lead->execute([
        $lead_name,
        $company,
        $mobile,
        $secondary_mobile,
        $email,
        $requirement,
        $loan_amount,
        $lead_source,
        $priority,
        $notes,
        $location,
        json_encode($input, JSON_UNESCAPED_UNICODE)
    ]);

    $new_lead_id = (int)$db->lastInsertId();

    // Notification & Activity Log
    try {
        $admin_id = $db->query("SELECT id FROM users WHERE role = 'Admin' ORDER BY id ASC LIMIT 1")->fetchColumn() ?: 1;
        $amt_str = $loan_amount > 0 ? " (₹" . number_format($loan_amount) . ")" : "";
        $notif = "New Website Apply Lead: {$lead_name} applied for {$requirement}{$amt_str}";
        $db->prepare("INSERT INTO notifications (user_id, message, type, is_read, created_at) VALUES (?, ?, 'lead', 0, datetime('now', 'localtime'))")->execute([$admin_id, $notif]);
        log_activity("New Website Lead received: {$lead_name} - {$requirement}{$amt_str}", "leads.php?edit_lead={$new_lead_id}");
    } catch (Exception $e) {}

    echo json_encode([
        'success' => true,
        'lead_id' => $new_lead_id,
        'type' => 'lead',
        'message' => 'Apply lead saved to CRM'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
