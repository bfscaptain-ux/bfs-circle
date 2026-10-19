<?php
ob_start();
@set_time_limit(300);
@ini_set('max_execution_time', '300');
@ini_set('memory_limit', '512M');
@ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
// api.php - BFS Financial Services backend API endpoints dispatcher
require_once __DIR__ . '/config.php';

if (!isset($_GET['api'])) {
    return_json(['error' => 'API command not specified.'], 400);
}

$action = $_GET['api'];

try {
    // Authentication check for protected APIs
    $public_apis = ['login', 'client_upload_document', 'client_submit_query', 'client_request_callback', 'client_confirm_verification', 'client_accept_sanction', 'client_refer_friend', 'forgot_password_request_otp', 'forgot_password_verify_otp', 'forgot_password_complete', 'inbound_email_webhook'];
    if (!in_array($action, $public_apis) && isset($_SESSION['user_id'])) {
        $stmt = $db->prepare("SELECT session_token FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $db_token = $stmt->fetchColumn();
        if ($db_token) {
            if (empty($_SESSION['session_token'])) {
                $_SESSION['session_token'] = $db_token;
            } elseif ($db_token !== $_SESSION['session_token']) {
                session_destroy();
                return_json(['error' => 'SESSION_EXPIRED'], 401);
            }
        }
    }
    
    if (!function_exists('call_ollama_ai')) {
        function call_ollama_ai($prompt, $system_prompt = '', $model = 'llama3.2:3b') {
            @set_time_limit(300);
            $url = 'http://127.0.0.1:11434/api/generate';
            $payload_arr = [
                'model' => $model,
                'prompt' => $prompt,
                'system' => $system_prompt ?: "You are an expert Indian Banking Advisor for BFS Circle Financial Services. Output clear, concise, professional responses in natural Hinglish or English.",
                'stream' => false,
                'options' => [
                    'temperature' => 0.3,
                    'num_predict' => 200
                ]
            ];
            $payload = json_encode($payload_arr);
            $response = false;

            $curl_err = '';
            if (function_exists('curl_init')) {
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
                $response = curl_exec($ch);
                if (curl_errno($ch)) {
                    $curl_err = curl_error($ch);
                }
                curl_close($ch);
            }

            if ($response === false) {
                $options = [
                    'http' => [
                        'header'  => "Content-Type: application/json\r\nContent-Length: " . strlen($payload) . "\r\n",
                        'method'  => 'POST',
                        'content' => $payload,
                        'timeout' => 15,
                        'ignore_errors' => true
                    ]
                ];
                $context = stream_context_create($options);
                $response = @file_get_contents($url, false, $context);
            }

            if ($response === FALSE || empty($response)) {
                $err_detail = $curl_err ?: 'HTTP stream request failed';
                return ['success' => false, 'error' => "Ollama service connection issue: $err_detail. Check if Ollama service is listening on port 11434."];
            }

            $data = json_decode($response, true);
            if (isset($data['response'])) {
                return ['success' => true, 'response' => trim($data['response'])];
            }
            return ['success' => false, 'error' => 'Invalid AI response format'];
        }
    }

    switch ($action) {
                case 'delete_employee':
            if (($_SESSION['role'] ?? '') !== 'Admin') return_json(['error' => 'Unauthorized'], 403);
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request'], 405);
            
            $emp_id = (int)($_POST['emp_id'] ?? 0);
            if (!$emp_id) return_json(['error' => 'Employee ID is required.'], 400);
            
            try {
                $db->beginTransaction();
                
                // Get user_id to delete from users table as well
                $stmt = $db->prepare("SELECT user_id, full_name FROM employees WHERE id = ?");
                $stmt->execute([$emp_id]);
                $emp = $stmt->fetch();
                
                if ($emp) {
                    $db->prepare("DELETE FROM employees WHERE id = ?")->execute([$emp_id]);
                    if (!empty($emp['user_id'])) {
                        $db->prepare("DELETE FROM users WHERE id = ?")->execute([$emp['user_id']]);
                    }
                    log_activity("Deleted employee: " . $emp['full_name'], "employees_list.php");
                }
                
                $db->commit();
                return_json(['success' => true]);
            } catch (Exception $e) {
                $db->rollBack();
                return_json(['error' => 'Database error: ' . $e->getMessage()]);
            }
            break;

                case 'update_employee':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request'], 405);
            
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) return_json(['error' => 'Employee ID is required'], 400);

            $full_name = trim($_POST['full_name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $role = trim($_POST['access_role'] ?? 'Staff');
            $mobile = trim($_POST['mobile'] ?? '');
            
            if(empty($full_name) || empty($username)) {
                return_json(['error' => 'Name and Username are required.'], 400);
            }
            
            try {
                $db->beginTransaction();
                
                // Get existing employee user_id
                $stmt = $db->prepare("SELECT user_id, photo_path, aadhar_path, pan_path, marksheet_path, relieving_letter_path, cheque_path FROM employees WHERE id = ?");
                $stmt->execute([$id]);
                $emp = $stmt->fetch();
                if (!$emp) return_json(['error' => 'Employee not found'], 404);
                
                $user_id = $emp['user_id'];
                
                // 1. Update User
                if (!empty($password)) {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $ustmt = $db->prepare("UPDATE users SET username=?, name=?, password_hash=?, role=? WHERE id=?");
                    $ustmt->execute([$username, $full_name, $hash, $role, $user_id]);
                } else {
                    $ustmt = $db->prepare("UPDATE users SET username=?, name=?, role=? WHERE id=?");
                    $ustmt->execute([$username, $full_name, $role, $user_id]);
                }
                
                // 2. Upload Files (if any)
                $uploads_dir = 'uploads/employees';
                if (!file_exists($uploads_dir)) mkdir($uploads_dir, 0777, true);
                
                $files = ['photo_path', 'aadhar_path', 'pan_path', 'marksheet_path', 'relieving_letter_path' => 'relieving_letter_path', 'cheque_path' => 'cheque_path'];
                $paths = [];
                foreach ($files as $post_key => $db_key) {
                    if (is_numeric($post_key)) {
                        $post_key = $db_key;
                    }
                    $paths[$db_key] = $emp[$db_key]; // Default to old path
                    if (isset($_FILES[$post_key]) && $_FILES[$post_key]['error'] == UPLOAD_ERR_OK) {
                        $ext = pathinfo($_FILES[$post_key]['name'], PATHINFO_EXTENSION);
                        $filename = $post_key . '_' . time() . '_' . rand(100, 999) . '.' . $ext;
                        $dest = $uploads_dir . '/' . $filename;
                        if (move_uploaded_file($_FILES[$post_key]['tmp_name'], $dest)) {
                            $paths[$db_key] = $dest;
                        }
                    }
                }

                // 3. Update Employee profile
                $official_email = trim($_POST['official_email'] ?? '');
                $personal_email = trim($_POST['personal_email'] ?? '');
                $current_address = trim($_POST['current_address'] ?? '');
                $permanent_address = trim($_POST['permanent_address'] ?? '');
                $emergency_contact_name = trim($_POST['emergency_contact_name'] ?? '');
                $emergency_relation = trim($_POST['emergency_relation'] ?? '');
                $emergency_phone = trim($_POST['emergency_phone'] ?? '');
                $department = trim($_POST['department'] ?? 'General');
                $designation = trim($_POST['designation'] ?? 'Staff');
                $reporting_manager = trim($_POST['reporting_manager'] ?? '');
                $doj = trim($_POST['doj'] ?? '');
                $work_mode = trim($_POST['work_mode'] ?? '');
                $pan_number = trim($_POST['pan_number'] ?? '');
                $aadhar_number = trim($_POST['aadhar_number'] ?? '');
                $bank_holder_name = trim($_POST['bank_holder_name'] ?? '');
                $bank_account_no = trim($_POST['bank_account_no'] ?? '');
                $bank_name = trim($_POST['bank_name'] ?? '');
                $bank_ifsc = trim($_POST['bank_ifsc'] ?? '');
                $commission_rate = (float)($_POST['commission_rate'] ?? 1.0);
                $team_data = trim($_POST['team_specific_data'] ?? '');

                $stmt2 = $db->prepare("UPDATE employees SET 
                    full_name=?, official_email=?, personal_email=?, mobile=?, 
                    current_address=?, permanent_address=?, emergency_contact_name=?, emergency_relation=?, emergency_phone=?, 
                    department=?, designation=?, reporting_manager=?, doj=?, access_role=?, work_mode=?, 
                    pan_number=?, aadhar_number=?, bank_holder_name=?, bank_account_no=?, bank_name=?, bank_ifsc=?,
                    photo_path=?, aadhar_path=?, pan_path=?, marksheet_path=?, relieving_letter_path=?, cheque_path=?,
                    commission_rate=?, team_specific_data=?
                    WHERE id=?
                ");
                
                $stmt2->execute([
                    $full_name, $official_email, $personal_email, $mobile,
                    $current_address, $permanent_address, $emergency_contact_name, $emergency_relation, $emergency_phone,
                    $department, $designation, $reporting_manager, $doj, $role, $work_mode,
                    $pan_number, $aadhar_number, $bank_holder_name, $bank_account_no, $bank_name, $bank_ifsc,
                    $paths['photo_path'], $paths['aadhar_path'], $paths['pan_path'], $paths['marksheet_path'], $paths['relieving_letter_path'], $paths['cheque_path'],
                    $commission_rate, $team_data,
                    $id
                ]);

                $db->commit();
                
                log_activity("Updated employee: $full_name", "employees_list.php");
                return_json(['success' => true, 'message' => 'Employee updated successfully!']);
            } catch (PDOException $e) {
                $db->rollBack();
                if(strpos($e->getMessage(), 'UNIQUE') !== false) {
                    return_json(['error' => 'Username/Email already exists.']);
                }
                return_json(['error' => 'Database error: ' . $e->getMessage()]);
            }
            break;

        case 'add_employee':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request'], 405);
            $full_name = trim($_POST['full_name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $role = trim($_POST['access_role'] ?? 'Staff');
            $mobile = trim($_POST['mobile'] ?? '');
            
            if(empty($full_name) || empty($username) || empty($password)) {
                return_json(['error' => 'Name, Username, and Password are required.'], 400);
            }
            
            try {
                $db->beginTransaction();
                
                // 1. Create User
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (username, name, password_hash, role) VALUES (?, ?, ?, ?)");
                $stmt->execute([$username, $full_name, $hash, $role]);
                $user_id = $db->lastInsertId();
                
                // 2. Upload Files (if any)
                $uploads_dir = 'uploads/employees';
                if (!file_exists($uploads_dir)) mkdir($uploads_dir, 0777, true);
                
                $files = ['photo_path', 'aadhar_path', 'pan_path', 'marksheet_path', 'relieving_letter_path', 'cheque_path'];
                $paths = [];
                foreach ($files as $file) {
                    $paths[$file] = null;
                    if (isset($_FILES[$file]) && $_FILES[$file]['error'] == UPLOAD_ERR_OK) {
                        $ext = pathinfo($_FILES[$file]['name'], PATHINFO_EXTENSION);
                        $filename = $file . '_' . time() . '_' . rand(100, 999) . '.' . $ext;
                        $dest = $uploads_dir . '/' . $filename;
                        if (move_uploaded_file($_FILES[$file]['tmp_name'], $dest)) {
                            $paths[$file] = $dest;
                        }
                    }
                }

                // 3. Create Employee profile
                $emp_id = trim($_POST['emp_id'] ?? ('EMP' . date('Ym') . rand(100, 999)));
                $official_email = trim($_POST['official_email'] ?? '');
                $personal_email = trim($_POST['personal_email'] ?? '');
                $current_address = trim($_POST['current_address'] ?? '');
                $permanent_address = trim($_POST['permanent_address'] ?? '');
                $emergency_contact_name = trim($_POST['emergency_contact_name'] ?? '');
                $emergency_relation = trim($_POST['emergency_relation'] ?? '');
                $emergency_phone = trim($_POST['emergency_phone'] ?? '');
                $department = trim($_POST['department'] ?? 'General');
                $designation = trim($_POST['designation'] ?? 'Staff');
                $reporting_manager = trim($_POST['reporting_manager'] ?? '');
                $doj = trim($_POST['doj'] ?? '');
                $work_mode = trim($_POST['work_mode'] ?? '');
                $pan_number = trim($_POST['pan_number'] ?? '');
                $aadhar_number = trim($_POST['aadhar_number'] ?? '');
                $bank_holder_name = trim($_POST['bank_holder_name'] ?? '');
                $bank_account_no = trim($_POST['bank_account_no'] ?? '');
                $bank_name = trim($_POST['bank_name'] ?? '');
                $bank_ifsc = trim($_POST['bank_ifsc'] ?? '');
                $commission_rate = (float)($_POST['commission_rate'] ?? 1.0);
                $team_data = trim($_POST['team_specific_data'] ?? '');

                $stmt2 = $db->prepare("INSERT INTO employees (
                    user_id, emp_id, full_name, official_email, personal_email, mobile, 
                    current_address, permanent_address, emergency_contact_name, emergency_relation, emergency_phone, 
                    department, designation, reporting_manager, doj, access_role, work_mode, 
                    pan_number, aadhar_number, bank_holder_name, bank_account_no, bank_name, bank_ifsc,
                    photo_path, aadhar_path, pan_path, marksheet_path, relieving_letter_path, cheque_path,
                    commission_rate, team_specific_data
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                )");
                
                $stmt2->execute([
                    $user_id, $emp_id, $full_name, $official_email, $personal_email, $mobile,
                    $current_address, $permanent_address, $emergency_contact_name, $emergency_relation, $emergency_phone,
                    $department, $designation, $reporting_manager, $doj, $role, $work_mode,
                    $pan_number, $aadhar_number, $bank_holder_name, $bank_account_no, $bank_name, $bank_ifsc,
                    $paths['photo_path'], $paths['aadhar_path'], $paths['pan_path'], $paths['marksheet_path'], $paths['relieving_letter_path'], $paths['cheque_path'],
                    $commission_rate, $team_data
                ]);

                $db->commit();
                
                log_activity("Added new employee: $full_name", "employees_list.php");
                return_json(['success' => true, 'message' => 'Employee onboarded successfully!']);
            } catch (PDOException $e) {
                $db->rollBack();
                if(strpos($e->getMessage(), 'UNIQUE') !== false) {
                    return_json(['error' => 'Username/Email already exists.']);
                }
                return_json(['error' => 'Database error: ' . $e->getMessage()]);
            }
            break;

        case 'update_employee':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request'], 405);
            if (($_SESSION['role'] ?? '') !== 'Admin') return_json(['error' => 'Admin privileges required'], 403);
            
            $id = intval($_POST['id'] ?? 0);
            if (!$id) return_json(['error' => 'Employee ID is required.'], 400);

            $stmt = $db->prepare("SELECT * FROM employees WHERE id = ?");
            $stmt->execute([$id]);
            $existing_emp = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$existing_emp) return_json(['error' => 'Employee record not found.'], 404);

            $full_name = trim($_POST['full_name'] ?? $existing_emp['full_name']);
            $official_email = trim($_POST['official_email'] ?? $existing_emp['official_email']);
            $personal_email = trim($_POST['personal_email'] ?? $existing_emp['personal_email']);
            $mobile = trim($_POST['mobile'] ?? $existing_emp['mobile']);
            $current_address = trim($_POST['current_address'] ?? $existing_emp['current_address']);
            $permanent_address = trim($_POST['permanent_address'] ?? $existing_emp['permanent_address']);
            $emergency_contact_name = trim($_POST['emergency_contact_name'] ?? $existing_emp['emergency_contact_name']);
            $emergency_relation = trim($_POST['emergency_relation'] ?? $existing_emp['emergency_relation']);
            $emergency_phone = trim($_POST['emergency_phone'] ?? $existing_emp['emergency_phone']);
            $department = trim($_POST['department'] ?? $existing_emp['department']);
            $designation = trim($_POST['designation'] ?? $existing_emp['designation']);
            $reporting_manager = trim($_POST['reporting_manager'] ?? $existing_emp['reporting_manager']);
            $doj = trim($_POST['doj'] ?? $existing_emp['doj']);
            $access_role = trim($_POST['access_role'] ?? $existing_emp['access_role']);
            $work_mode = trim($_POST['work_mode'] ?? $existing_emp['work_mode']);
            $monthly_base_salary = floatval($_POST['monthly_base_salary'] ?? ($existing_emp['monthly_base_salary'] ?? 0));
            $pan_number = trim($_POST['pan_number'] ?? $existing_emp['pan_number']);
            $aadhar_number = trim($_POST['aadhar_number'] ?? $existing_emp['aadhar_number']);
            $bank_holder_name = trim($_POST['bank_holder_name'] ?? $existing_emp['bank_holder_name']);
            $bank_account_no = trim($_POST['bank_account_no'] ?? $existing_emp['bank_account_no']);
            $bank_name = trim($_POST['bank_name'] ?? $existing_emp['bank_name']);
            $bank_ifsc = trim($_POST['bank_ifsc'] ?? $existing_emp['bank_ifsc']);
            $commission_rate = floatval($_POST['commission_rate'] ?? $existing_emp['commission_rate']);
            $team_data = trim($_POST['team_specific_data'] ?? $existing_emp['team_specific_data']);

            // File uploads handling
            $uploads_dir = 'uploads/employees';
            if (!file_exists($uploads_dir)) mkdir($uploads_dir, 0777, true);
            $files = ['photo_path', 'aadhar_path', 'pan_path', 'marksheet_path', 'relieving_letter_path', 'cheque_path'];
            $paths = [];
            foreach ($files as $file) {
                $paths[$file] = $existing_emp[$file] ?? null;
                if (isset($_FILES[$file]) && $_FILES[$file]['error'] == UPLOAD_ERR_OK) {
                    $ext = pathinfo($_FILES[$file]['name'], PATHINFO_EXTENSION);
                    $filename = $file . '_' . time() . '_' . rand(100, 999) . '.' . $ext;
                    $dest = $uploads_dir . '/' . $filename;
                    if (move_uploaded_file($_FILES[$file]['tmp_name'], $dest)) {
                        $paths[$file] = $dest;
                    }
                }
            }

            try {
                $db->beginTransaction();

                // Update users table
                $password = trim($_POST['password'] ?? '');
                if (!empty($password)) {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt_u = $db->prepare("UPDATE users SET name = ?, role = ?, password_hash = ? WHERE id = ?");
                    $stmt_u->execute([$full_name, $access_role, $hash, $existing_emp['user_id']]);
                } else {
                    $stmt_u = $db->prepare("UPDATE users SET name = ?, role = ? WHERE id = ?");
                    $stmt_u->execute([$full_name, $access_role, $existing_emp['user_id']]);
                }

                // Update employees table
                $stmt2 = $db->prepare("UPDATE employees SET 
                    full_name = ?, official_email = ?, personal_email = ?, mobile = ?,
                    current_address = ?, permanent_address = ?, emergency_contact_name = ?, emergency_relation = ?, emergency_phone = ?,
                    department = ?, designation = ?, reporting_manager = ?, doj = ?, access_role = ?, work_mode = ?, monthly_base_salary = ?,
                    pan_number = ?, aadhar_number = ?, bank_holder_name = ?, bank_account_no = ?, bank_name = ?, bank_ifsc = ?,
                    photo_path = ?, aadhar_path = ?, pan_path = ?, marksheet_path = ?, relieving_letter_path = ?, cheque_path = ?,
                    commission_rate = ?, team_specific_data = ?
                    WHERE id = ?");

                $stmt2->execute([
                    $full_name, $official_email, $personal_email, $mobile,
                    $current_address, $permanent_address, $emergency_contact_name, $emergency_relation, $emergency_phone,
                    $department, $designation, $reporting_manager, $doj, $access_role, $work_mode, $monthly_base_salary,
                    $pan_number, $aadhar_number, $bank_holder_name, $bank_account_no, $bank_name, $bank_ifsc,
                    $paths['photo_path'], $paths['aadhar_path'], $paths['pan_path'], $paths['marksheet_path'], $paths['relieving_letter_path'], $paths['cheque_path'],
                    $commission_rate, $team_data,
                    $id
                ]);

                $db->commit();
                log_activity("Updated employee profile: $full_name (ID: $id)", "edit_employee.php");
                return_json(['success' => true, 'message' => 'Employee profile updated successfully!']);
            } catch (PDOException $e) {
                $db->rollBack();
                return_json(['error' => 'Database error: ' . $e->getMessage()]);
            }
            break;

        case 'ping':
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $stmt = $db->prepare("UPDATE users SET last_active = datetime('now', 'localtime'), last_ip = ? WHERE id = ?");
            $stmt->execute([$ip, $_SESSION['user_id']]);
            return_json(['success' => true]);
            break;
            
        case 'get_online_staff':
            if (($_SESSION['role'] ?? '') !== 'Admin') return_json(['error' => 'Admin privileges required'], 403);
            $stmt = $db->query("SELECT id, username, name, role, last_ip, last_active FROM users WHERE last_active >= datetime('now', 'localtime', '-2 minutes') AND role = 'Staff' ORDER BY last_active DESC");
            return_json($stmt->fetchAll());
            break;

        case 'login':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request'], 405);
            $username = trim($_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');
            
            $stmt = $db->prepare("SELECT * FROM users WHERE LOWER(username) = LOWER(?)");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            $auth_success = false;
            if ($user) {
                if (!empty($user['password_hash']) && password_verify($password, $user['password_hash'])) {
                    $auth_success = true;
                } elseif (!empty($user['plain_password']) && $password === $user['plain_password']) {
                    $auth_success = true;
                    // Auto-sync valid hash in DB
                    $new_hash = password_hash($password, PASSWORD_DEFAULT);
                    $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$new_hash, $user['id']]);
                }
            }
            
            if ($user && $auth_success) {
                if (isset($user['is_active']) && (int)$user['is_active'] === 0) {
                    return_json(['error' => '❌ Your account has been deactivated by Admin. You cannot access the dashboard.'], 403);
                }

                // If user is a partner or linked to a referral, check referrals status
                $ref_chk = $db->prepare("SELECT status, full_name FROM referrals WHERE user_id = ? OR LOWER(referral_id) = LOWER(?) OR mobile = ? LIMIT 1");
                $ref_chk->execute([$user['id'], $user['username'], $user['username']]);
                $ref_acc = $ref_chk->fetch(PDO::FETCH_ASSOC);
                if ($ref_acc && in_array(strtolower($ref_acc['status'] ?? ''), ['inactive', 'deactive', 'deactivated', 'blocked', 'suspended'])) {
                    return_json(['error' => '❌ Your partner account has been deactivated by Admin. You cannot access the dashboard.'], 403);
                }
                $token = bin2hex(random_bytes(16));
                $db->prepare("UPDATE users SET session_token = ? WHERE id = ?")->execute([$token, $user['id']]);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['session_token'] = $token;
                $_SESSION['custom_permissions_arr'] = !empty($user['custom_permissions']) ? json_decode($user['custom_permissions'], true) : [];
                return_json(['success' => true, 'message' => 'Login successful', 'role' => $user['role']]);
            }
            return_json(['error' => 'Invalid username or password'], 401);
            break;

        case 'logout':
            session_destroy();
            return_json(['success' => true, 'message' => 'Logged out successfully']);
            break;

        case 'forgot_password_request_otp':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request Method'], 405);
            $email = trim($_POST['email'] ?? '');
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return_json(['error' => 'Please enter a valid email address.'], 400);
            }

            // 1. Direct match on users table
            $stmt = $db->prepare("SELECT * FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // 2. Search employees table
            if (!$user) {
                $stmt_emp = $db->prepare("SELECT user_id, full_name, mobile, official_email, personal_email FROM employees WHERE LOWER(official_email) = LOWER(?) OR LOWER(personal_email) = LOWER(?) LIMIT 1");
                $stmt_emp->execute([$email, $email]);
                if ($emp = $stmt_emp->fetch(PDO::FETCH_ASSOC)) {
                    if (!empty($emp['user_id'])) {
                        $stmt = $db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
                        $stmt->execute([$emp['user_id']]);
                        $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    }
                }
            }

            // 3. Search referrals table
            if (!$user) {
                $stmt_ref = $db->prepare("SELECT user_id, full_name, mobile, email FROM referrals WHERE LOWER(email) = LOWER(?) LIMIT 1");
                $stmt_ref->execute([$email]);
                if ($ref = $stmt_ref->fetch(PDO::FETCH_ASSOC)) {
                    if (!empty($ref['user_id'])) {
                        $stmt = $db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
                        $stmt->execute([$ref['user_id']]);
                        $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    } elseif (!empty($ref['mobile'])) {
                        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
                        $stmt->execute([$ref['mobile']]);
                        $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    }
                }
            }

            if (!$user) {
                return_json(['error' => 'No account found with this email address. Please check and try again or contact Admin.'], 404);
            }

            if (isset($user['is_active']) && (int)$user['is_active'] === 0) {
                return_json(['error' => 'Your account has been deactivated by Admin. Please contact support.'], 403);
            }

            // Generate 6-digit OTP and reset token
            $otp = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            $reset_token = bin2hex(random_bytes(16));

            // Invalidate old OTPs for this email
            $db->prepare("UPDATE password_reset_otps SET is_used = 1 WHERE LOWER(email) = LOWER(?)")->execute([$email]);

            // Insert new OTP record (valid for 15 minutes)
            $stmt_ins = $db->prepare("INSERT INTO password_reset_otps (user_id, email, otp_code, reset_token, expires_at) VALUES (?, ?, ?, ?, datetime('now', '+15 minutes'))");
            $stmt_ins->execute([$user['id'], $email, $otp, $reset_token]);

            // Ensure email is synced to users table
            if (empty($user['email'])) {
                try {
                    $db->prepare("UPDATE users SET email = ? WHERE id = ?")->execute([$email, $user['id']]);
                } catch (Exception $e) {}
            }

            // Helper to mask email for UI confirmation
            $email_parts = explode('@', $email);
            $user_part = $email_parts[0];
            $domain_part = $email_parts[1] ?? '';
            $masked_email = (strlen($user_part) <= 2) ? $user_part . '***@' . $domain_part : substr($user_part, 0, 2) . str_repeat('*', max(3, strlen($user_part) - 3)) . substr($user_part, -1) . '@' . $domain_part;

            $user_display_name = !empty($user['name']) ? $user['name'] : (!empty($user['full_name']) ? $user['full_name'] : $user['username']);
            $subject = "🔒 Password Reset OTP Code — BFS Financial Services";
            $body = "
            <div style='text-align:center; padding:10px 0 20px 0;'>
                <div style='font-size:18px; font-weight:700; color:#0f172a; margin-bottom:8px;'>Account Access Verification</div>
                <p style='color:#64748b; font-size:13.5px; margin:0 0 20px 0;'>Dear <strong>" . htmlspecialchars($user_display_name) . "</strong>, we received a request to access or reset your BFS Financial Services account password. Use the single-use OTP below to verify your request:</p>
                
                <div style='background:#f0fdf4; border:2px dashed #86efac; border-radius:12px; padding:18px 24px; display:inline-block; margin:0 auto 20px auto;'>
                    <span style='font-size:36px; font-weight:800; letter-spacing:10px; color:#15803d; font-family:Courier, monospace; display:block; padding-left:10px;'>" . $otp . "</span>
                </div>

                <p style='color:#dc2626; font-size:12px; font-weight:600; margin:0 0 16px 0;'>⚠️ This OTP is confidential and valid for 15 minutes only.</p>
                <p style='color:#94a3b8; font-size:11px; margin:0;'>If you did not request this OTP, please ignore this email. Your existing password remains completely secure.</p>
            </div>";

            sendUniversalEmail($email, $user_display_name, $subject, $body, 'forgot_password');

            return_json([
                'success' => true,
                'message' => 'OTP sent successfully to ' . $masked_email,
                'masked_email' => $masked_email,
                'reset_token' => $reset_token
            ]);
            break;

        case 'forgot_password_verify_otp':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request Method'], 405);
            $email = trim($_POST['email'] ?? '');
            $otp_code = trim($_POST['otp_code'] ?? '');
            $reset_token = trim($_POST['reset_token'] ?? '');

            if (empty($email) || empty($otp_code) || empty($reset_token)) {
                return_json(['error' => 'All fields (Email, OTP, Token) are required.'], 400);
            }

            $stmt = $db->prepare("SELECT * FROM password_reset_otps WHERE LOWER(email) = LOWER(?) AND reset_token = ? AND is_used = 0 AND expires_at > datetime('now') ORDER BY id DESC LIMIT 1");
            $stmt->execute([$email, $reset_token]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$record) {
                return_json(['error' => 'Invalid or expired OTP session. Please request a new OTP.'], 400);
            }

            if (trim($record['otp_code']) !== trim($otp_code)) {
                return_json(['error' => 'Incorrect OTP code. Please enter the valid 6-digit code received on your email.'], 400);
            }

            $user_stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $user_stmt->execute([$record['user_id']]);
            $user = $user_stmt->fetch(PDO::FETCH_ASSOC);

            return_json([
                'success' => true,
                'message' => 'OTP verified successfully!',
                'reset_token' => $record['reset_token'],
                'user_name' => $user['name'] ?: ($user['full_name'] ?: $user['username']),
                'role' => $user['role']
            ]);
            break;

        case 'forgot_password_complete':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request Method'], 405);
            $reset_token = trim($_POST['reset_token'] ?? '');
            $action_type = trim($_POST['action_type'] ?? 'reset_and_login'); // 'reset_and_login' or 'direct_continue'

            if (empty($reset_token)) {
                return_json(['error' => 'Invalid session token. Please restart password recovery.'], 400);
            }

            $stmt = $db->prepare("SELECT * FROM password_reset_otps WHERE reset_token = ? AND is_used = 0 AND expires_at > datetime('now') ORDER BY id DESC LIMIT 1");
            $stmt->execute([$reset_token]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$record) {
                return_json(['error' => 'Session expired or already used. Please request a new OTP.'], 400);
            }

            $user_id = $record['user_id'];
            $user_stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $user_stmt->execute([$user_id]);
            $user = $user_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                return_json(['error' => 'User account not found.'], 404);
            }

            if ($action_type === 'reset_and_login') {
                $new_password = $_POST['new_password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';

                if (empty($new_password) || strlen($new_password) < 4) {
                    return_json(['error' => 'New password must be at least 4 characters long.'], 400);
                }
                if ($new_password !== $confirm_password) {
                    return_json(['error' => 'New password and confirm password do not match.'], 400);
                }

                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $db->prepare("UPDATE users SET password_hash = ?, plain_password = ? WHERE id = ?")->execute([$new_hash, $new_password, $user['id']]);
            }

            // Mark OTP as used
            $db->prepare("UPDATE password_reset_otps SET is_used = 1 WHERE reset_token = ?")->execute([$reset_token]);

            // Auto-login user
            $token = bin2hex(random_bytes(16));
            $db->prepare("UPDATE users SET session_token = ?, last_active = datetime('now') WHERE id = ?")->execute([$token, $user['id']]);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['name'] = $user['name'] ?: ($user['full_name'] ?: $user['username']);
            $_SESSION['full_name'] = $user['full_name'] ?: $user['name'];
            $_SESSION['session_token'] = $token;
            $_SESSION['custom_permissions_arr'] = !empty($user['custom_permissions']) ? json_decode($user['custom_permissions'], true) : [];

            $redirect_url = get_role_dashboard_url($user['role'], false);
            log_activity("User {$user['username']} logged in via Email OTP (" . ($action_type === 'reset_and_login' ? "Password Reset" : "Direct Continue") . ")", "forgot_password.php", $user['username']);

            return_json([
                'success' => true,
                'message' => ($action_type === 'reset_and_login') ? 'Password changed successfully! Redirecting...' : 'Identity verified! Redirecting to dashboard...',
                'redirect_url' => $redirect_url,
                'role' => $user['role']
            ]);
            break;

        case 'create_user':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request'], 405);
            if (($_SESSION['role'] ?? '') !== 'Admin') return_json(['error' => 'Admin privileges required'], 403);
            
            $name = trim($_POST['name'] ?? '');
            $role = $_POST['role'] ?? 'Staff';
            $staff_type = $_POST['staff_type'] ?? 'In-Office';
            $has_dashboard = isset($_POST['has_dashboard']) ? (int)$_POST['has_dashboard'] : 1;
            $monthly_target = isset($_POST['monthly_target']) && (float)$_POST['monthly_target'] > 0 ? (float)$_POST['monthly_target'] : 15000000;
            $commission_rate = isset($_POST['commission_rate']) ? (float)$_POST['commission_rate'] : 0.20;
            
            $perms = $_POST['custom_permissions'] ?? [];
            if (is_array($perms)) {
                $perms_json = json_encode($perms);
            } else if (is_string($perms)) {
                $perms_json = $perms;
            } else {
                $perms_json = json_encode([]);
            }

            if ($has_dashboard === 1) {
                $username = trim($_POST['username'] ?? '');
                $password = $_POST['password'] ?? '';
                if (empty($username) || empty($password)) return_json(['error' => 'Username and password required'], 400);
            } else {
                // Generate a unique username for non-dashboard staff
                $username = 'staff_' . time() . rand(10, 99);
                $password = bin2hex(random_bytes(4)); // random password
            }
            
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $plain_password = $has_dashboard === 1 ? $password : null;
            
            try {
                $stmt = $db->prepare("INSERT INTO users (username, name, full_name, password_hash, role, is_active, staff_type, has_dashboard, plain_password, monthly_target, commission_rate, custom_permissions) VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$username, $name, $name, $hash, $role, $staff_type, $has_dashboard, $plain_password, $monthly_target, $commission_rate, $perms_json]);
                return_json(['success' => true, 'message' => 'RM User created successfully!']);
            } catch (Exception $e) {
                return_json(['error' => 'Username might already exist: ' . $e->getMessage()], 400);
            }
            break;


        case 'bulk_assign':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request'], 405);
            if (($_SESSION['role'] ?? '') !== 'Admin') return_json(['error' => 'Admin privileges required'], 403);
            
            $type = $_POST['type'] ?? '';
            $assigned_to = trim($_POST['assigned_to'] ?? '');
            $ids_json = $_POST['ids'] ?? '[]';
            
            $ids = json_decode($ids_json, true);
            if (!is_array($ids) || empty($ids)) return_json(['error' => 'No records selected'], 400);
            
            $table = $type === 'leads' ? 'leads' : 'pre_leads';
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            
            $targetVal = ($assigned_to === '' || strtolower($assigned_to) === 'null') ? null : $assigned_to;
            $params = array_merge([$targetVal], $ids);
            
            $stmt = $db->prepare("UPDATE $table SET assigned_to = ? WHERE id IN ($placeholders)");
            $stmt->execute($params);
            
            $c = count($ids);
            log_activity("Assigned $c $type to " . ($targetVal ? $targetVal : 'Unassigned'));
            
            return_json(['success' => true]);
            break;
            
        case 'bulk_delete':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request'], 405);
            if (($_SESSION['role'] ?? '') !== 'Admin') return_json(['error' => 'Admin privileges required'], 403);
            
            $type = $_POST['type'] ?? '';
            $ids_json = $_POST['ids'] ?? '[]';
            
            $ids = json_decode($ids_json, true);
            if (!is_array($ids) || empty($ids)) return_json(['error' => 'No records selected'], 400);
            
            $table = $type === 'leads' ? 'leads' : 'pre_leads';
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            
            $stmt = $db->prepare("DELETE FROM $table WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            
            $currentUser = $_SESSION['username'] ?? 'Unknown';
            $c = count($ids);
            log_activity("Bulk deleted $c $type");
            
            return_json(['success' => true]);
            break;
            
        case 'reassign_client':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request'], 405);
            if (($_SESSION['role'] ?? '') !== 'Admin') return_json(['error' => 'Admin privileges required'], 403);
            
            $client_id = (int)($_POST['client_id'] ?? 0);
            $new_staff = trim($_POST['new_staff'] ?? '');
            
            if (!$client_id || empty($new_staff)) return_json(['error' => 'Missing data'], 400);
            
            $stmt = $db->prepare("UPDATE clients SET assigned_to = ? WHERE id = ?");
            $stmt->execute([$new_staff, $client_id]);
            
            log_activity("Reassigned client ID $client_id to $new_staff");
            return_json(['success' => true]);
            break;
            
        case 'get_users':
            $stmt = $db->query("SELECT id, username, name, role, is_active, created_at, staff_type, has_dashboard, plain_password, monthly_target, commission_rate, custom_permissions FROM users ORDER BY created_at ASC");
            $users_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($users_list as &$u) {
                if (!empty($u['custom_permissions'])) {
                    $u['permissions'] = json_decode($u['custom_permissions'], true);
                } else {
                    if ($u['role'] === 'Admin') {
                        $u['permissions'] = ['view_all_applicants'=>1,'export_data'=>1,'edit_sanctions'=>1,'manage_bank_logins'=>1,'manage_payouts'=>1,'resolve_sla_alerts'=>1,'delete_records'=>1,'access_settings'=>1];
                    } else if ($u['role'] === 'Staff') {
                        $u['permissions'] = ['manage_bank_logins'=>1, 'resolve_sla_alerts'=>1];
                    } else {
                        $u['permissions'] = ['manage_bank_logins'=>1];
                    }
                }
            }
            return_json($users_list);
            break;

        case 'update_user_permissions':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request'], 405);
            if (($_SESSION['role'] ?? '') !== 'Admin') return_json(['error' => 'Admin privileges required'], 403);
            $user_id = (int)($_POST['user_id'] ?? 0);
            if ($user_id <= 0) return_json(['error' => 'User ID required'], 400);

            $perms = $_POST['custom_permissions'] ?? [];
            $perms_json = is_array($perms) ? json_encode($perms) : (is_string($perms) ? $perms : json_encode([]));

            $stmt = $db->prepare("UPDATE users SET custom_permissions = ? WHERE id = ?");
            $stmt->execute([$perms_json, $user_id]);

            if ($user_id === ($_SESSION['user_id'] ?? 0)) {
                $_SESSION['custom_permissions_arr'] = is_array($perms) ? $perms : json_decode($perms_json, true);
            }

            $user_name = $db->query("SELECT COALESCE(name, username) FROM users WHERE id = " . $user_id)->fetchColumn();
            log_activity("Updated Access Permissions for User: $user_name (ID: #$user_id)", "settings.php");
            return_json(['success' => true, 'message' => "Access rights for '$user_name' updated successfully!"]);
            break;
            
        case 'toggle_user_status':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request'], 405);
            if (($_SESSION['role'] ?? '') !== 'Admin') return_json(['error' => 'Admin privileges required'], 403);
            $id = $_POST['id'] ?? null;
            if (!$id) return_json(['error' => 'Missing ID'], 400);
            if ($id == $_SESSION['user_id']) return_json(['error' => 'Cannot deactivate yourself'], 400);
            
            $target_role = $db->query("SELECT role FROM users WHERE id = " . (int)$id)->fetchColumn();
            if ($target_role === 'Admin') return_json(['error' => 'Cannot deactivate the Admin account'], 400);

            $stmt = $db->prepare("UPDATE users SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END WHERE id = ?");
            $stmt->execute([$id]);
            return_json(['success' => true, 'message' => 'Status updated']);
            break;
            
        case 'delete_user':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request'], 405);
            if (($_SESSION['role'] ?? '') !== 'Admin') return_json(['error' => 'Admin privileges required'], 403);
            $id = $_POST['id'] ?? null;
            if (!$id) return_json(['error' => 'Missing ID'], 400);
            if ($id == $_SESSION['user_id']) return_json(['error' => 'Cannot delete yourself'], 400);
            
            $target_role = $db->query("SELECT role FROM users WHERE id = " . (int)$id)->fetchColumn();
            if ($target_role === 'Admin') return_json(['error' => 'Cannot delete the Admin account'], 400);

            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            return_json(['success' => true, 'message' => 'User deleted']);
            break;

        case 'get_templates':
            $type = $_GET['type'] ?? '';
            if ($type) {
                $stmt = $db->prepare("SELECT * FROM email_templates WHERE type = ? ORDER BY template_name ASC");
                $stmt->execute([$type]);
            } else {
                $stmt = $db->query("SELECT * FROM email_templates ORDER BY type ASC, template_name ASC");
            }
            return_json($stmt->fetchAll());
            break;

        case 'save_template':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request'], 405);
            $id = $_POST['id'] ?? null;
            $name = trim($_POST['template_name'] ?? '');
            $type = $_POST['type'] ?? '';
            $subject = trim($_POST['subject'] ?? '');
            $body = trim($_POST['body'] ?? '');
            
            if (empty($name) || empty($type) || empty($subject) || empty($body)) {
                return_json(['error' => 'All fields are required'], 400);
            }
            
            $attachment_name = null;
            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = __DIR__ . '/uploads/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                $filename = time() . '_' . preg_replace("/[^a-zA-Z0-9.-]/", "_", $_FILES['attachment']['name']);
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_dir . $filename)) {
                    $attachment_name = $filename;
                }
            }

            if ($id) {
                if ($attachment_name) {
                    $stmt = $db->prepare("UPDATE email_templates SET template_name=?, type=?, subject=?, body=?, attachment_name=? WHERE id=?");
                    $stmt->execute([$name, $type, $subject, $body, $attachment_name, $id]);
                } else {
                    $stmt = $db->prepare("UPDATE email_templates SET template_name=?, type=?, subject=?, body=? WHERE id=?");
                    $stmt->execute([$name, $type, $subject, $body, $id]);
                }
            } else {
                $stmt = $db->prepare("INSERT INTO email_templates (template_name, type, subject, body, attachment_name) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $type, $subject, $body, $attachment_name]);
            }
            return_json(['success' => true, 'message' => 'Template saved successfully']);
            break;

        case 'delete_template':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request'], 405);
            $id = $_POST['id'] ?? null;
            if (!$id) return_json(['error' => 'Missing ID'], 400);
            
            if (($_SESSION['role'] ?? '') === 'Admin') {
                $stmt = $db->prepare("DELETE FROM email_templates WHERE id = ?");
                $stmt->execute([$id]);
                log_activity("Admin deleted email template ID: $id");
                return_json(['success' => true, 'message' => 'Template deleted']);
            } else {
                $stmt = $db->prepare("UPDATE email_templates SET delete_requested = 1 WHERE id = ?");
                $stmt->execute([$id]);
                log_activity("Staff requested deletion for email template ID: $id");
                return_json(['success' => true, 'message' => 'Delete request sent to Admin']);
            }
            break;
            
        case 'approve_delete_template':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_SESSION['role'] ?? '') !== 'Admin') return_json(['error' => 'Unauthorized'], 403);
            $id = $_POST['id'] ?? null;
            $db->prepare("DELETE FROM email_templates WHERE id = ?")->execute([$id]);
            log_activity("Admin approved deletion of email template ID: $id");
            return_json(['success' => true, 'message' => 'Template deleted permanently']);
            break;
            
        case 'reject_delete_template':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_SESSION['role'] ?? '') !== 'Admin') return_json(['error' => 'Unauthorized'], 403);
            $id = $_POST['id'] ?? null;
            $db->prepare("UPDATE email_templates SET delete_requested = 0 WHERE id = ?")->execute([$id]);
            log_activity("Admin rejected deletion for email template ID: $id");
            return_json(['success' => true, 'message' => 'Delete request rejected']);
            break;

        case 'get_ppts':
            $stmt = $db->query("SELECT * FROM presentations ORDER BY created_at DESC");
            return_json($stmt->fetchAll());
            break;
            
        case 'upload_ppt':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request'], 405);
            $original_name = trim($_POST['original_name'] ?? '');
            
            if (empty($original_name)) return_json(['error' => 'Presentation title is required'], 400);
            
            if (!isset($_FILES['ppt_file']) || $_FILES['ppt_file']['error'] !== UPLOAD_ERR_OK) {
                return_json(['error' => 'File upload failed or no file selected'], 400);
            }
            
            $upload_dir = __DIR__ . '/uploads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $filename = time() . '_ppt_' . preg_replace("/[^a-zA-Z0-9.-]/", "_", $_FILES['ppt_file']['name']);
            
            if (move_uploaded_file($_FILES['ppt_file']['tmp_name'], $upload_dir . $filename)) {
                $stmt = $db->prepare("INSERT INTO presentations (original_name, filename) VALUES (?, ?)");
                $stmt->execute([$original_name, $filename]);
                return_json(['success' => true, 'message' => 'Presentation uploaded successfully']);
            }
            return_json(['error' => 'Failed to move uploaded file'], 500);
            break;
            
        case 'delete_ppt':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request'], 405);
            $id = $_POST['id'] ?? null;
            if (!$id) return_json(['error' => 'Missing ID'], 400);
            
            if (($_SESSION['role'] ?? '') === 'Admin') {
                $filename = $db->query("SELECT filename FROM presentations WHERE id = " . (int)$id)->fetchColumn();
                if ($filename && file_exists(__DIR__ . '/uploads/' . $filename)) {
                    @unlink(__DIR__ . '/uploads/' . $filename);
                }
                $stmt = $db->prepare("DELETE FROM presentations WHERE id = ?");
                $stmt->execute([$id]);
                log_activity("Admin deleted presentation ID: $id");
                return_json(['success' => true, 'message' => 'Presentation deleted']);
            } else {
                $stmt = $db->prepare("UPDATE presentations SET delete_requested = 1 WHERE id = ?");
                $stmt->execute([$id]);
                log_activity("Staff requested deletion for presentation ID: $id");
                return_json(['success' => true, 'message' => 'Delete request sent to Admin']);
            }
            break;
            
        case 'approve_delete_ppt':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_SESSION['role'] ?? '') !== 'Admin') return_json(['error' => 'Unauthorized'], 403);
            $id = $_POST['id'] ?? null;
            $filename = $db->query("SELECT filename FROM presentations WHERE id = " . (int)$id)->fetchColumn();
            if ($filename && file_exists(__DIR__ . '/uploads/' . $filename)) {
                @unlink(__DIR__ . '/uploads/' . $filename);
            }
            $db->prepare("DELETE FROM presentations WHERE id = ?")->execute([$id]);
            log_activity("Admin approved deletion of presentation ID: $id");
            return_json(['success' => true, 'message' => 'Presentation deleted permanently']);
            break;
            
        case 'reject_delete_ppt':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_SESSION['role'] ?? '') !== 'Admin') return_json(['error' => 'Unauthorized'], 403);
            $id = $_POST['id'] ?? null;
            $db->prepare("UPDATE presentations SET delete_requested = 0 WHERE id = ?")->execute([$id]);
            log_activity("Admin rejected deletion for presentation ID: $id");
            return_json(['success' => true, 'message' => 'Delete request rejected']);
            break;

        case 'bulk_upload_leads':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request'], 405);
            $leads_json = $_POST['leads_json'] ?? '[]';
            $leads = json_decode($leads_json, true);
            if (!$leads || !is_array($leads)) return_json(['error' => 'Invalid data format'], 400);

            $db->beginTransaction();
            try {
                $existing_mobiles = $db->query("SELECT mobile FROM leads WHERE mobile != ''")->fetchAll(PDO::FETCH_COLUMN);
                $existing_emails = $db->query("SELECT email FROM leads WHERE email != ''")->fetchAll(PDO::FETCH_COLUMN);
                $existing_mobiles_map = array_flip($existing_mobiles);
                $existing_emails_map = array_flip($existing_emails);

                $stmt = $db->prepare("INSERT INTO leads (lead_name, company_name, mobile, email, lead_source, priority, stage, assigned_to, location, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $upload_count = 0;
                foreach ($leads as $lead) {
                    $mob = trim($lead['mobile'] ?? '');
                    $eml = trim($lead['email'] ?? '');
                    if ($mob !== '' && isset($existing_mobiles_map[$mob])) continue;
                    if ($eml !== '' && isset($existing_emails_map[$eml])) continue;
                    if ($mob !== '') $existing_mobiles_map[$mob] = true;
                    if ($eml !== '') $existing_emails_map[$eml] = true;
                    
                    $upload_count++;
                    $stmt->execute([
                        $lead['lead_name'] ?? '',
                        $lead['company_name'] ?? '',
                        $mob,
                        $eml,
                        $lead['lead_source'] ?? 'Cold Call',
                        $lead['priority'] ?? 'Warm',
                        $lead['stage'] ?? 'New Lead',
                        $lead['assigned_to'] ?? '',
                        $lead['location'] ?? '',
                        $lead['notes'] ?? ''
                    ]);
                }
                $db->commit();
                log_activity("Bulk uploaded " . $upload_count . " new leads");
                return_json(['success' => true, 'message' => $upload_count . ' new leads uploaded successfully (duplicates skipped)']);
            } catch (Exception $e) {
                $db->rollBack();
                return_json(['error' => 'Database error: ' . $e->getMessage()], 500);
            }
            break;

        case 'save_lead':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request'], 405);
            $lead_id     = isset($_POST['lead_id']) && (int)$_POST['lead_id'] > 0 ? (int)$_POST['lead_id'] : null;
            
            $role = $_SESSION['role'] ?? '';
            $username = $_SESSION['username'] ?? '';

            $lead_name   = trim($_POST['lead_name'] ?? '');
            $company     = trim($_POST['company_name'] ?? '');
            $mobile      = trim($_POST['mobile'] ?? '');
            $email       = trim($_POST['email'] ?? '');
            $source      = trim($_POST['lead_source'] ?? 'Cold Call');
            $assigned_to = trim($_POST['assigned_to'] ?? '');
            if ($role !== 'Admin') $assigned_to = $username;
            $priority    = trim($_POST['priority'] ?? 'Warm');
            $stage       = trim($_POST['stage'] ?? 'New Lead');
            $notes       = trim($_POST['notes'] ?? '');
            $requirement = trim($_POST['loan_type'] ?? $_POST['requirement'] ?? '');
            $loan_amount = floatval($_POST['loan_amount'] ?? 0);

            if (empty($lead_name)) return_json(['error' => 'Lead name is required'], 400);

            if ($lead_id) {
                $existing = $db->query("SELECT * FROM leads WHERE id=$lead_id")->fetch();
                if ($role !== 'Admin' && $existing['assigned_to'] !== $username) {
                    return_json(['error' => 'Unauthorized to edit this lead'], 403);
                }
                if ($role !== 'Admin') {
                    $mobile = $existing['mobile'];
                    $email = $existing['email'];
                }
                
                $stmt = $db->prepare("UPDATE leads SET lead_name=?,company_name=?,mobile=?,email=?,lead_source=?,assigned_to=?,priority=?,stage=?,notes=?,requirement=?,loan_amount=? WHERE id=?");
                $stmt->execute([$lead_name,$company,$mobile,$email,$source,$assigned_to,$priority,$stage,$notes,$requirement,$loan_amount,$lead_id]);
                log_activity("Updated lead: $lead_name", "leads.php?edit_lead=$lead_id");
                return_json(['success' => true, 'message' => 'Lead updated successfully']);
            } else {
                if (empty($mobile)) return_json(['error' => 'Mobile is required'], 400);
                $stmt = $db->prepare("INSERT INTO leads (lead_name,company_name,mobile,email,lead_source,assigned_to,priority,stage,notes,requirement,loan_amount) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$lead_name,$company,$mobile,$email,$source,$assigned_to,$priority,$stage,$notes,$requirement,$loan_amount]);
                $new_id = $db->lastInsertId();
                log_activity("New lead added: $lead_name (" . ($company ?: 'Individual') . ")", "leads.php?edit_lead=$new_id");
                return_json(['success' => true, 'message' => "Lead '$lead_name' added successfully!"]);
            }
            break;

        case 'log_print_activity':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request'], 405);
            $generated_for = trim($_POST['generated_for'] ?? '');
            $purpose = trim($_POST['purpose'] ?? 'Pre-Leads Report');
            $records_count = intval($_POST['records_count'] ?? 0);
            $segment_name = trim($_POST['segment_name'] ?? 'General Pool');
            $filters_summary = trim($_POST['filters_summary'] ?? '');
            $generated_by = $_SESSION['username'] ?? 'System';

            if (empty($generated_for)) return_json(['error' => 'Please enter who this print is generated for.'], 400);

            $stmt = $db->prepare("INSERT INTO print_logs (module, generated_by, generated_for, purpose, records_count, segment_name, filters_summary, printed_at) VALUES ('pre_leads', ?, ?, ?, ?, ?, ?, datetime('now', 'localtime'))");
            $stmt->execute([$generated_by, $generated_for, $purpose, $records_count, $segment_name, $filters_summary]);
            $log_id = $db->lastInsertId();

            log_activity("Generated print report ($records_count leads) for: $generated_for ($purpose)", "pre_leads.php");
            return_json(['success' => true, 'message' => 'Print audit logged successfully', 'log_id' => $log_id]);
            break;

        case 'get_print_logs':
            $limit = intval($_GET['limit'] ?? 20);
            $stmt = $db->prepare("SELECT * FROM print_logs WHERE module = 'pre_leads' ORDER BY printed_at DESC LIMIT ?");
            $stmt->execute([$limit]);
            return_json(['success' => true, 'logs' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'get_prelead_segments':
            $stmt = $db->query("SELECT COALESCE(NULLIF(segment_name, ''), 'General Pool') as segment_name, 
                                       COUNT(*) as total_count,
                                       SUM(CASE WHEN assigned_to IS NULL OR assigned_to = '' THEN 1 ELSE 0 END) as unassigned_count
                                FROM pre_leads 
                                GROUP BY COALESCE(NULLIF(segment_name, ''), 'General Pool')
                                ORDER BY total_count DESC");
            return_json(['success' => true, 'segments' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'bulk_assign_segment':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request'], 405);
            if (($_SESSION['role'] ?? '') !== 'Admin') return_json(['error' => 'Admin privileges required'], 403);

            $segment_name = trim($_POST['segment_name'] ?? '');
            $mode = trim($_POST['mode'] ?? 'single'); // 'single' or 'round_robin'
            $assigned_to = trim($_POST['assigned_to'] ?? '');
            $target_rms = json_decode($_POST['target_rms'] ?? '[]', true);
            $only_unassigned = intval($_POST['only_unassigned'] ?? 1);

            $where = "1=1";
            $params = [];
            if ($segment_name !== '' && $segment_name !== 'ALL') {
                if ($segment_name === 'General Pool') {
                    $where .= " AND (segment_name IS NULL OR segment_name = '' OR segment_name = 'General Pool')";
                } else {
                    $where .= " AND segment_name = ?";
                    $params[] = $segment_name;
                }
            }
            if ($only_unassigned) {
                $where .= " AND (assigned_to IS NULL OR assigned_to = '')";
            }

            $stmt = $db->prepare("SELECT id FROM pre_leads WHERE $where ORDER BY id ASC");
            $stmt->execute($params);
            $lead_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (empty($lead_ids)) {
                return_json(['error' => 'No matching pre-leads found for allocation.'], 404);
            }

            $updated_count = 0;
            if ($mode === 'single') {
                if (empty($assigned_to)) return_json(['error' => 'Please select a target RM.'], 400);
                $in = implode(',', array_fill(0, count($lead_ids), '?'));
                $up_stmt = $db->prepare("UPDATE pre_leads SET assigned_to = ?, assigned_at = datetime('now', 'localtime') WHERE id IN ($in)");
                $up_stmt->execute(array_merge([$assigned_to], $lead_ids));
                $updated_count = count($lead_ids);
                log_activity("Assigned $updated_count pre-leads from segment '$segment_name' to RM $assigned_to");
            } else if ($mode === 'round_robin') {
                if (!is_array($target_rms) || empty($target_rms)) {
                    return_json(['error' => 'Please select at least one target RM for round-robin.'], 400);
                }
                $rm_count = count($target_rms);
                $up_stmt = $db->prepare("UPDATE pre_leads SET assigned_to = ?, assigned_at = datetime('now', 'localtime') WHERE id = ?");
                foreach ($lead_ids as $idx => $lid) {
                    $rm = $target_rms[$idx % $rm_count];
                    $up_stmt->execute([$rm, $lid]);
                    $updated_count++;
                }
                log_activity("Distributed $updated_count pre-leads from segment '$segment_name' via round-robin across " . count($target_rms) . " RMs");
            }

            return_json(['success' => true, 'message' => "Successfully allocated $updated_count pre-leads!", 'assigned_count' => $updated_count]);
            break;

        case 'promote_prelead_to_applicant':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request'], 405);
            $id = intval($_POST['id'] ?? 0);
            if (!$id) return_json(['error' => 'Pre-lead ID required'], 400);

            $pl = $db->query("SELECT * FROM pre_leads WHERE id = $id")->fetch(PDO::FETCH_ASSOC);
            if (!$pl) return_json(['error' => 'Pre-lead record not found'], 404);

            $loan_id = 'BFS-' . date('Ymd') . '-' . rand(1000, 9999);

            try { $db->exec("ALTER TABLE applicants ADD COLUMN notes TEXT"); } catch (Exception $e) {}
            try { $db->exec("ALTER TABLE applicants ADD COLUMN remarks TEXT"); } catch (Exception $e) {}

            $stmt = $db->prepare("INSERT INTO applicants (loan_id, customer_name, mobile, email, city, monthly_income, loan_amount_requested, loan_type, assigned_to, overall_status, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'New', ?, datetime('now', 'localtime'))");
            $stmt->execute([
                $loan_id,
                $pl['name'],
                $pl['mobile'],
                $pl['email'] ?? '',
                $pl['city'] ?? ($pl['location'] ?? ''),
                $pl['monthly_income'] ?? 0,
                $pl['loan_amount_requested'] ?? 0,
                $pl['loan_type'] ?? ($pl['service_intent'] ?? 'Personal Loan'),
                $pl['assigned_to'] ?? ($_SESSION['username'] ?? ''),
                "Promoted from Raw Pre-Lead Segment: " . ($pl['segment_name'] ?? 'General Pool') . "\nNotes: " . ($pl['notes'] ?? '')
            ]);
            $new_applicant_id = $db->lastInsertId();

            $db->prepare("UPDATE pre_leads SET status = 'Promoted' WHERE id = ?")->execute([$id]);

            log_activity("Promoted pre-lead #" . $id . " (" . $pl['name'] . ") to Active Applicant File #" . $loan_id);
            return_json(['success' => true, 'message' => 'Successfully promoted to active loan pipeline!', 'loan_id' => $loan_id, 'applicant_id' => $new_applicant_id]);
            break;

        case 'get_preleads':
            $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            $limit = 15;
            $offset = ($page - 1) * $limit;
            $search = isset($_GET['search']) ? trim($_GET['search']) : '';
            $tab = isset($_GET['tab']) ? trim($_GET['tab']) : 'all';
            $status = isset($_GET['status']) ? trim($_GET['status']) : '';
            $source = isset($_GET['source']) ? trim($_GET['source']) : '';
            $segment_filter = isset($_GET['segment_name']) ? trim($_GET['segment_name']) : '';
            $rm_filter = isset($_GET['assigned_to']) ? trim($_GET['assigned_to']) : '';
            
            $where = "1=1";
            $params = [];
            
            if (($_SESSION['role'] ?? '') !== 'Admin') {
                $where .= " AND assigned_to = ?";
                $params[] = $_SESSION['username'] ?? '';
            } else if ($rm_filter !== '') {
                if ($rm_filter === 'UNASSIGNED') {
                    $where .= " AND (assigned_to IS NULL OR assigned_to = '')";
                } else {
                    $where .= " AND assigned_to = ?";
                    $params[] = $rm_filter;
                }
            }

            if ($segment_filter !== '') {
                if ($segment_filter === 'General Pool') {
                    $where .= " AND (segment_name IS NULL OR segment_name = '' OR segment_name = 'General Pool')";
                } else {
                    $where .= " AND segment_name = ?";
                    $params[] = $segment_filter;
                }
            }
            
            // Tab Filtering
            if ($tab === 'new') {
                $where .= " AND (status = 'Not Contacted' OR status IS NULL OR status = '') AND call_count = 0";
            } elseif ($tab === 'followup') {
                $where .= " AND (status = 'Interested' OR status = 'Follow Up' OR followup_date IS NOT NULL)";
            } elseif ($tab === 'archived') {
                $where .= " AND (status = 'Not Interested' OR status = 'Junk' OR status = 'Converted' OR status = 'Promoted')";
            }
            
            if ($search !== '') {
                $where .= " AND (name LIKE ? OR mobile LIKE ? OR email LIKE ? OR company_name LIKE ? OR segment_name LIKE ?)";
                $sp = "%$search%";
                $params = array_merge($params, [$sp, $sp, $sp, $sp, $sp]);
            }
            
            if ($status !== '') {
                $where .= " AND status = ?";
                $params[] = $status;
            }
            if ($source !== '') {
                $where .= " AND source = ?";
                $params[] = $source;
            }
            
            // Total count
            $stmt_c = $db->prepare("SELECT COUNT(*) FROM pre_leads WHERE $where");
            $stmt_c->execute($params);
            $total = $stmt_c->fetchColumn();
            
            // Fetch records
            $stmt = $db->prepare("SELECT * FROM pre_leads WHERE $where ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
            $stmt->execute($params);

            // Fetch list of distinct segments for dropdown
            $seg_stmt = $db->query("SELECT COALESCE(NULLIF(segment_name, ''), 'General Pool') as seg, COUNT(*) as cnt FROM pre_leads GROUP BY COALESCE(NULLIF(segment_name, ''), 'General Pool') ORDER BY cnt DESC");
            $segments_list = $seg_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return_json([
                'success' => true,
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
                'total' => $total,
                'page' => $page,
                'total_pages' => ceil($total / $limit),
                'segments_summary' => $segments_list
            ]);
            break;
            
        case 'log_call':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request'], 405);
            $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
            $new_status = isset($_POST['status']) ? trim($_POST['status']) : '';
            $followup = isset($_POST['followup_date']) ? trim($_POST['followup_date']) : null;
            $note = isset($_POST['notes']) ? trim($_POST['notes']) : '';
            
            if (!$id) return_json(['error'=>'Missing ID']);
            
            $stmt = $db->prepare("UPDATE pre_leads SET status = ?, followup_date = ?, last_called_at = datetime('now', 'localtime'), call_count = call_count + 1, notes = IFNULL(notes,'') || '
' || ? WHERE id = ?");
            $stmt->execute([$new_status, $followup, date('Y-m-d H:i').": ".$note, $id]);
            
            // Auto Archive logic
            $check = $db->prepare("SELECT call_count FROM pre_leads WHERE id = ?");
            $check->execute([$id]);
            $cc = $check->fetchColumn();
            if ($cc >= 5 && $new_status !== 'Interested' && $new_status !== 'Converted') {
                $db->prepare("UPDATE pre_leads SET status = 'Junk' WHERE id = ?")->execute([$id]);
            }
            
            return_json(['success' => true]);
            break;


        case 'save_prelead':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request'], 405);
            $id = isset($_POST['id']) && (int)$_POST['id'] > 0 ? (int)$_POST['id'] : null;
            
            $role = $_SESSION['role'] ?? '';
            $username = $_SESSION['username'] ?? '';

            $name = trim($_POST['name'] ?? '');
            $company = trim($_POST['company_name'] ?? '');
            $mobile = trim($_POST['mobile'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $source = trim($_POST['source'] ?? 'Manual Entry');
            $status = trim($_POST['status'] ?? 'Not Contacted');
            $segment_name = trim($_POST['segment_name'] ?? 'General Pool');
            if (empty($segment_name)) $segment_name = 'General Pool';
            $city = trim($_POST['city'] ?? $_POST['location'] ?? '');
            $monthly_income = floatval($_POST['monthly_income'] ?? 0);
            $loan_amount_requested = floatval($_POST['loan_amount_requested'] ?? 0);
            $loan_type = trim($_POST['loan_type'] ?? 'Personal Loan');
            $assigned_to = trim($_POST['assigned_to'] ?? '');
            if ($role !== 'Admin') $assigned_to = $username;
            $location = trim($_POST['location'] ?? '');
            $notes = trim($_POST['notes'] ?? '');

            if (empty($name)) return_json(['error' => 'Name is required'], 400);

            if ($id) {
                $existing = $db->query("SELECT * FROM pre_leads WHERE id=$id")->fetch();
                if ($role !== 'Admin' && $existing['assigned_to'] !== $username) {
                    return_json(['error' => 'Unauthorized to edit this pre-lead'], 403);
                }
                if ($role !== 'Admin') {
                    $mobile = $existing['mobile'];
                    $email = $existing['email'];
                }

                $stmt = $db->prepare("UPDATE pre_leads SET name=?, company_name=?, mobile=?, email=?, source=?, status=?, segment_name=?, city=?, monthly_income=?, loan_amount_requested=?, loan_type=?, assigned_to=?, location=?, notes=? WHERE id=?");
                $stmt->execute([$name, $company, $mobile, $email, $source, $status, $segment_name, $city, $monthly_income, $loan_amount_requested, $loan_type, $assigned_to, $location, $notes, $id]);
                log_activity("Updated pre-lead: $name", "pre_leads.php?edit_prelead=$id");
                return_json(['success' => true, 'message' => 'Pre-Lead updated!']);
            } else {
                if (empty($mobile)) return_json(['error' => 'Mobile is required'], 400);
                $stmt = $db->prepare("INSERT INTO pre_leads (name, company_name, mobile, email, source, status, segment_name, city, monthly_income, loan_amount_requested, loan_type, assigned_to, location, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $company, $mobile, $email, $source, $status, $segment_name, $city, $monthly_income, $loan_amount_requested, $loan_type, $assigned_to, $location, $notes]);
                $new_id = $db->lastInsertId();
                log_activity("Added new pre-lead: $name in segment '$segment_name'", "pre_leads.php?edit_prelead=$new_id");
                return_json(['success' => true, 'message' => 'Pre-Lead added to segment successfully!']);
            }
            break;

        case 'delete_prelead':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request'], 405);
            if (($_SESSION['role'] ?? '') !== 'Admin') return_json(['error' => 'Admin privileges required to delete pre-leads'], 403);
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) return_json(['error' => 'Missing ID'], 400);
            $prelead_name = $db->query("SELECT name FROM pre_leads WHERE id=$id")->fetchColumn();
            $db->prepare("DELETE FROM pre_leads WHERE id = ?")->execute([$id]);
            log_activity("Deleted pre-lead: $prelead_name", "pre_leads.php");
            return_json(['success' => true, 'message' => 'Pre-Lead deleted']);
            break;

        case 'promote_prelead':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request'], 405);
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) return_json(['error' => 'Missing ID'], 400);
            
            $db->beginTransaction();
            try {
                $pre_lead = $db->query("SELECT * FROM pre_leads WHERE id = $id")->fetch();
                if (!$pre_lead) throw new Exception("Pre-Lead not found");

                $notes = "Segment: " . ($pre_lead['segment_name'] ?? 'General Pool');
                if (!empty($pre_lead['notes'])) $notes .= "\nNotes: " . $pre_lead['notes'];

                $stmt = $db->prepare("INSERT INTO leads (lead_name, company_name, mobile, email, lead_source, priority, stage, assigned_to, location, notes, requirement, loan_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $pre_lead['name'],
                    $pre_lead['company_name'] ?? '',
                    $pre_lead['mobile'],
                    $pre_lead['email'] ?? '',
                    $pre_lead['source'] ?? 'Raw Pre-Lead',
                    'Warm',
                    'New Lead',
                    $pre_lead['assigned_to'] ?? ($_SESSION['username'] ?? ''),
                    $pre_lead['location'] ?? ($pre_lead['city'] ?? ''),
                    $notes,
                    $pre_lead['loan_type'] ?? 'Personal Loan',
                    $pre_lead['loan_amount_requested'] ?? 0
                ]);
                $new_id = $db->lastInsertId();
                
                $db->prepare("UPDATE pre_leads SET status = 'Promoted' WHERE id = ?")->execute([$id]);
                $db->commit();
                log_activity("Promoted pre-lead to lead: " . $pre_lead['name'], "leads.php?edit_lead=$new_id");
                return_json(['success' => true, 'message' => 'Promoted to Lead Management successfully!', 'lead_id' => $new_id]);
            } catch (Exception $e) {
                $db->rollBack();
                return_json(['error' => $e->getMessage()], 500);
            }
            break;

        case 'update_prelead_status':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request'], 405);
            $id = (int)($_POST['id'] ?? 0);
            $status = trim($_POST['status'] ?? '');
            if (!$id || !$status) return_json(['error' => 'Invalid inputs'], 400);
            
            $name = "Unknown";
            $l = $db->query("SELECT name FROM pre_leads WHERE id=$id")->fetch();
            if($l) $name = $l['name'];
            
            $db->prepare("UPDATE pre_leads SET status = ? WHERE id = ?")->execute([$status, $id]);
            log_activity("Updated pre-lead status to $status for: $name", "pre_leads.php?edit_prelead=$id");
            
            return_json(['success' => true]);
            break;

        case 'bulk_upload_preleads':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request'], 405);
            $leads_json = $_POST['leads_json'] ?? '[]';
            $leads = json_decode($leads_json, true);
            if (!$leads || !is_array($leads)) return_json(['error' => 'Invalid data format'], 400);

            $db->beginTransaction();
            try {
                $existing_mobiles = $db->query("SELECT mobile FROM pre_leads WHERE mobile != ''")->fetchAll(PDO::FETCH_COLUMN);
                $existing_emails = $db->query("SELECT email FROM pre_leads WHERE email != ''")->fetchAll(PDO::FETCH_COLUMN);
                $existing_mobiles_map = array_flip($existing_mobiles);
                $existing_emails_map = array_flip($existing_emails);

                $stmt = $db->prepare("INSERT INTO pre_leads (name, company_name, mobile, email, source, status, assigned_to, location, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $upload_count = 0;
                foreach ($leads as $lead) {
                    $mob = trim($lead['mobile'] ?? '');
                    $eml = trim($lead['email'] ?? '');
                    if ($mob !== '' && isset($existing_mobiles_map[$mob])) continue;
                    if ($eml !== '' && isset($existing_emails_map[$eml])) continue;
                    if ($mob !== '') $existing_mobiles_map[$mob] = true;
                    if ($eml !== '') $existing_emails_map[$eml] = true;

                    $upload_count++;
                    $stmt->execute([
                        $lead['lead_name'] ?? '',
                        $lead['company_name'] ?? '',
                        $mob,
                        $eml,
                        $lead['lead_source'] ?? 'Unknown',
                        'Not Contacted',
                        $lead['assigned_to'] ?? '',
                        $lead['location'] ?? '',
                        $lead['notes'] ?? ''
                    ]);
                }
                $db->commit();
                log_activity("Bulk uploaded " . $upload_count . " new Pre-Leads", "pre_leads.php");
                return_json(['success' => true, 'message' => $upload_count . ' new Pre-Leads uploaded successfully (duplicates skipped)']);
            } catch (Exception $e) {
                $db->rollBack();
                return_json(['error' => 'Database error: ' . $e->getMessage()], 500);
            }
            break;

        case 'get_pre_leads_kanban':
            $search = trim($_GET['search'] ?? '');
            $segment = trim($_GET['segment'] ?? '');
            $assigned_to = trim($_GET['assigned_to'] ?? '');
            
            $where = ["1=1"];
            $params = [];
            if($search !== '') {
                $where[] = "(name LIKE ? OR mobile LIKE ? OR company_name LIKE ? OR segment_name LIKE ?)";
                $sParam = "%$search%";
                $params[] = $sParam;
                $params[] = $sParam;
                $params[] = $sParam;
                $params[] = $sParam;
            }
            if($segment !== '') {
                $where[] = "COALESCE(NULLIF(segment_name, ''), 'General Pool') = ?";
                $params[] = $segment;
            }
            if($assigned_to !== '') {
                if($assigned_to === 'unassigned') {
                    $where[] = "(assigned_to IS NULL OR assigned_to = '')";
                } else {
                    $where[] = "assigned_to = ?";
                    $params[] = $assigned_to;
                }
            }
            if(!$is_admin) {
                $where[] = "assigned_to = ?";
                $params[] = $_SESSION['username'];
            }
            
            $whereStr = implode(' AND ', $where);
            $stmt = $db->prepare("SELECT id, name, company_name, mobile, email, source, status, segment_name, city, monthly_income, loan_amount_requested, loan_type, assigned_to, followup_date, call_count, last_called_at FROM pre_leads WHERE $whereStr ORDER BY id DESC LIMIT 500");
            $stmt->execute($params);
            $all = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $columns = [
                'New' => [],
                'In Followup' => [],
                'Callback Scheduled' => [],
                'Interested' => [],
                'Promoted' => [],
                'Junk' => []
            ];
            
            foreach($all as $row) {
                $st = $row['status'] ?? 'New';
                if(stristr($st, 'Follow') !== false) $col = 'In Followup';
                elseif(stristr($st, 'Callback') !== false) $col = 'Callback Scheduled';
                elseif(stristr($st, 'Interested') !== false || stristr($st, 'Hot') !== false) $col = 'Interested';
                elseif(stristr($st, 'Promoted') !== false || stristr($st, 'Converted') !== false) $col = 'Promoted';
                elseif(stristr($st, 'Junk') !== false || stristr($st, 'Not Interested') !== false) $col = 'Junk';
                else $col = 'New';
                
                $columns[$col][] = $row;
            }
            return_json(['success' => true, 'kanban' => $columns]);
            break;

        case 'get_telecaller_queue':
            $assigned_to = $_SESSION['username'] ?? '';
            $where = ["status NOT IN ('Promoted', 'Converted', 'Junk')"];
            $params = [];
            if(!$is_admin) {
                $where[] = "assigned_to = ?";
                $params[] = $assigned_to;
            } else {
                $rm = trim($_GET['rm'] ?? '');
                if($rm !== '') {
                    $where[] = "assigned_to = ?";
                    $params[] = $rm;
                }
            }
            $whereStr = implode(' AND ', $where);
            $stmt = $db->prepare("SELECT * FROM pre_leads WHERE $whereStr ORDER BY CASE WHEN followup_date IS NOT NULL AND date(followup_date) <= date('now') THEN 0 ELSE 1 END, call_count ASC, id ASC LIMIT 100");
            $stmt->execute($params);
            $queue = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return_json(['success' => true, 'queue' => $queue]);
            break;

        case 'bulk_action_pre_leads':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request'], 405);
            $action_type = trim($_POST['action_type'] ?? '');
            $ids = $_POST['ids'] ?? [];
            if (is_string($ids)) $ids = json_decode($ids, true);
            if (!is_array($ids) || empty($ids)) return_json(['error' => 'No leads selected'], 400);

            $ids = array_map('intval', $ids);
            $in = implode(',', $ids);

            if ($action_type === 'assign') {
                $assign_to = trim($_POST['target_value'] ?? '');
                $up = $db->prepare("UPDATE pre_leads SET assigned_to = ?, assigned_at = datetime('now', 'localtime') WHERE id IN ($in)");
                $up->execute([$assign_to]);
                log_activity("Bulk assigned " . count($ids) . " pre-leads to $assign_to", "pre_leads.php");
                return_json(['success' => true, 'message' => count($ids) . ' pre-leads assigned to ' . $assign_to]);
            } elseif ($action_type === 'segment') {
                $new_segment = trim($_POST['target_value'] ?? 'General Pool');
                $up = $db->prepare("UPDATE pre_leads SET segment_name = ? WHERE id IN ($in)");
                $up->execute([$new_segment]);
                log_activity("Bulk updated segment to '$new_segment' for " . count($ids) . " pre-leads", "pre_leads.php");
                return_json(['success' => true, 'message' => count($ids) . ' pre-leads updated to segment ' . $new_segment]);
            } elseif ($action_type === 'status') {
                $new_status = trim($_POST['target_value'] ?? 'In Touch');
                $up = $db->prepare("UPDATE pre_leads SET status = ? WHERE id IN ($in)");
                $up->execute([$new_status]);
                log_activity("Bulk updated status to '$new_status' for " . count($ids) . " pre-leads", "pre_leads.php");
                return_json(['success' => true, 'message' => count($ids) . ' pre-leads status updated to ' . $new_status]);
            } elseif ($action_type === 'delete') {
                if (!$is_admin) return_json(['error' => 'Admin permissions required to delete'], 403);
                $db->prepare("DELETE FROM pre_leads WHERE id IN ($in)")->execute();
                log_activity("Bulk deleted " . count($ids) . " pre-leads", "pre_leads.php");
                return_json(['success' => true, 'message' => count($ids) . ' pre-leads deleted']);
            } else {
                return_json(['error' => 'Invalid bulk action'], 400);
            }
            break;

        case 'get_purchased_batches_analytics':
            $batches = $db->query("SELECT * FROM purchased_data_batches ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
            foreach($batches as &$b) {
                $bid = $b['batch_id'];
                $vname = $b['vendor_name'];
                $stmt = $db->prepare("SELECT COUNT(*) as total, 
                                             SUM(CASE WHEN status IN ('Promoted','Converted') THEN 1 ELSE 0 END) as promoted,
                                             SUM(CASE WHEN status IN ('Interested','Hot') THEN 1 ELSE 0 END) as interested,
                                             SUM(CASE WHEN status = 'Junk' THEN 1 ELSE 0 END) as junk,
                                             SUM(call_count) as total_calls
                                      FROM pre_leads WHERE purchase_batch_id = ? OR (vendor_name = ? AND vendor_name != '')");
                $stmt->execute([$bid, $vname]);
                $stats = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $b['total_leads'] = max((int)$b['records_bought'], (int)($stats['total'] ?? 0));
                $b['promoted_count'] = (int)($stats['promoted'] ?? 0);
                $b['interested_count'] = (int)($stats['interested'] ?? 0);
                $b['junk_count'] = (int)($stats['junk'] ?? 0);
                $b['total_calls'] = (int)($stats['total_calls'] ?? 0);
                $b['cost_per_lead'] = $b['total_leads'] > 0 ? round($b['purchase_amount'] / $b['total_leads'], 2) : 0;
                $b['effective_cost_per_promoted'] = $b['promoted_count'] > 0 ? round($b['purchase_amount'] / $b['promoted_count'], 2) : 0;
                $b['conversion_pct'] = $b['total_leads'] > 0 ? round(($b['promoted_count'] / $b['total_leads']) * 100, 1) : 0;
            }
            return_json(['success' => true, 'batches' => $batches]);
            break;

        case 'promote_lead_to_applicant':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request'], 405);
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) return_json(['error' => 'Lead ID required'], 400);

            $lead = $db->query("SELECT * FROM leads WHERE id = $id")->fetch(PDO::FETCH_ASSOC);
            if (!$lead) return_json(['error' => 'Lead not found'], 404);

            $loan_id = 'BFS-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $token = bin2hex(random_bytes(16));

            // Resolve referral_id if lead originated from a partner/referral
            $resolved_ref_id = '';
            $lead_added_by = $lead['added_by'] ?? '';
            $lead_source = $lead['lead_source'] ?? '';
            
            if (!empty($lead_added_by)) {
                $ref_chk = $db->prepare("
                    SELECT referral_id FROM referrals 
                    WHERE referral_id = ? OR full_name = ? OR mobile = ? 
                       OR user_id = (SELECT id FROM users WHERE username = ?)
                    ORDER BY id DESC LIMIT 1
                ");
                $ref_chk->execute([$lead_added_by, $lead_added_by, $lead_added_by, $lead_added_by]);
                $resolved_ref_id = $ref_chk->fetchColumn() ?: '';
            }
            if (empty($resolved_ref_id) && !empty($lead_source)) {
                $ref_chk2 = $db->prepare("SELECT referral_id FROM referrals WHERE full_name = ? ORDER BY id DESC LIMIT 1");
                $ref_chk2->execute([$lead_source]);
                $resolved_ref_id = $ref_chk2->fetchColumn() ?: '';
            }

            $stmt = $db->prepare("INSERT INTO applicants (
                loan_id, customer_name, mobile, email, loan_type, loan_amount_requested, 
                assigned_to, overall_status, lead_source, referral_id, tracking_token, tracking_token_created, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'Phase 1', ?, ?, ?, datetime('now', 'localtime'), datetime('now', 'localtime'))");

            $stmt->execute([
                $loan_id,
                $lead['lead_name'] ?? $lead['name'] ?? $lead['customer_name'] ?? 'Applicant',
                $lead['mobile'] ?? '',
                $lead['email'] ?? '',
                $lead['loan_type'] ?? $lead['requirement'] ?? 'Home Loan',
                $lead['loan_amount'] ?? $lead['loan_amount_requested'] ?? 0,
                $lead['assigned_to'] ?? $_SESSION['username'],
                $lead['lead_source'] ?? $lead['source'] ?? 'Lead Pipeline',
                $resolved_ref_id,
                $token
            ]);

            $new_applicant_id = $db->lastInsertId();
            $db->prepare("UPDATE leads SET stage = 'Converted' WHERE id = ?")->execute([$id]);

            log_activity("Converted lead to active loan file #$loan_id for: " . ($lead['lead_name'] ?? $lead['name'] ?? 'Client'), "leads.php");

            return_json([
                'success' => true,
                'message' => 'Lead successfully converted to Loan Application file',
                'loan_id' => $loan_id,
                'applicant_id' => $new_applicant_id
            ]);
            break;

        case 'get_leads_kanban':
            $search      = trim($_GET['search'] ?? '');
            $stage       = trim($_GET['stage'] ?? '');
            $priority    = trim($_GET['priority'] ?? '');
            $assigned_to = trim($_GET['assigned_to'] ?? '');
            $loan_type   = trim($_GET['loan_type'] ?? '');
            $date_range  = trim($_GET['date_range'] ?? '');

            $where = ["1=1"];
            $params = [];
            
            if (!$is_admin) {
                $user = $_SESSION['username'] ?? '';
                $where[] = "(assigned_to = ? OR added_by = ?)";
                $params[] = $user;
                $params[] = $user;
            }
            if ($stage !== '') {
                $where[] = "stage = ?";
                $params[] = $stage;
            }
            if ($assigned_to !== '') {
                $where[] = "assigned_to = ?";
                $params[] = $assigned_to;
            }
            if ($priority !== '') {
                $where[] = "priority = ?";
                $params[] = $priority;
            }
            if ($loan_type !== '') {
                $where[] = "requirement = ?";
                $params[] = $loan_type;
            }
            if ($search !== '') {
                $where[] = "(lead_name LIKE ? OR company_name LIKE ? OR mobile LIKE ?)";
                $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
            }
            if ($date_range !== '') {
                if ($date_range === 'today') {
                    $where[] = "date(created_at) = date('now')";
                } elseif ($date_range === 'yesterday') {
                    $where[] = "date(created_at) = date('now', '-1 day')";
                } elseif ($date_range === 'last7') {
                    $where[] = "date(created_at) >= date('now', '-7 days')";
                } elseif ($date_range === 'this_month') {
                    $where[] = "strftime('%Y-%m', created_at) = strftime('%Y-%m', 'now')";
                }
            }

            $whereStr = implode(' AND ', $where);
            $stmt = $db->prepare("SELECT * FROM leads WHERE $whereStr ORDER BY id DESC LIMIT 500");
            $stmt->execute($params);
            $all = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch notes preview
            $notes_map = [];
            try {
                $n_rows = $db->query("SELECT lead_id, COUNT(*) as cnt, note_text as latest_note FROM lead_notes GROUP BY lead_id ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($n_rows as $nr) { $notes_map[$nr['lead_id']] = $nr; }
            } catch(Exception $e) {}

            $columns = [
                'New Lead' => [],
                'Contacted' => [],
                'Scheduled' => [],
                'Interested' => [],
                'Converted' => [],
                'Rejected' => []
            ];
            $stage_totals = [
                'New Lead' => 0,
                'Contacted' => 0,
                'Scheduled' => 0,
                'Interested' => 0,
                'Converted' => 0,
                'Rejected' => 0
            ];
            $overall_amount = 0;

            foreach ($all as &$row) {
                $ref_id = $row['added_by'] ?? '';
                $src = $row['lead_source'] ?? '';
                $row['notes_count'] = $notes_map[$row['id']]['cnt'] ?? 0;
                $row['latest_note'] = $notes_map[$row['id']]['latest_note'] ?? ($row['notes'] ?? '');

                if (!empty($ref_id) || !empty($src)) {
                    $ref_stmt = $db->prepare("SELECT full_name, mobile, referrer_type, referral_id FROM referrals WHERE referral_id = ? OR full_name = ? OR mobile = ? LIMIT 1");
                    $ref_stmt->execute([$ref_id, $src, $ref_id]);
                    $ref = $ref_stmt->fetch(PDO::FETCH_ASSOC);
                    if ($ref) {
                        $row['referrer_name'] = $ref['full_name'];
                        $row['referrer_mobile'] = $ref['mobile'];
                        $row['referrer_type'] = $ref['referrer_type'];
                    } else {
                        $u_stmt = $db->prepare("SELECT COALESCE(name, full_name, username) as full_name, role FROM users WHERE username = ?");
                        $u_stmt->execute([$ref_id]);
                        $u = $u_stmt->fetch(PDO::FETCH_ASSOC);
                        if ($u) {
                            $row['referrer_name'] = $u['full_name'];
                            $row['referrer_type'] = $u['role'];
                        }
                    }
                }

                $st = $row['stage'] ?? 'New Lead';
                if (!isset($columns[$st])) {
                    $columns[$st] = [];
                    $stage_totals[$st] = 0;
                }
                $amt = (float)($row['loan_amount'] ?? 0);
                $stage_totals[$st] += $amt;
                $overall_amount += $amt;
                $columns[$st][] = $row;
            }

            return_json([
                'success' => true, 
                'kanban' => $columns, 
                'stage_totals' => $stage_totals, 
                'total_leads' => count($all), 
                'overall_amount' => $overall_amount
            ]);
            break;
            
        case 'get_reminders':
            $role = $_SESSION['role'] ?? '';
            $username = $_SESSION['username'] ?? '';
            $search = $_GET['search'] ?? '';
            $date_filter = $_GET['date'] ?? '';
            $ref_type_filter = $_GET['ref_type'] ?? '';
            $category_filter = $_GET['category'] ?? '';
            $staff_filter = $_GET['assigned_to'] ?? '';
            $priority_filter = $_GET['priority'] ?? '';
            
            $now = date('Y-m-d H:i:s');
            $today_start = date('Y-m-d') . ' 00:00:00';
            $today_end   = date('Y-m-d') . ' 23:59:59';
            
            // Build WHERE conditions
            $where = "status IN ('Pending','Snoozed') AND (snoozed_until IS NULL OR snoozed_until <= ?)";
            $params_r = [$now];
            
            if ($role !== 'Admin') {
                $where .= " AND assigned_to = ?";
                $params_r[] = $username;
            } elseif ($staff_filter) {
                $where .= " AND assigned_to = ?";
                $params_r[] = $staff_filter;
            }
            if ($ref_type_filter) {
                $where .= " AND reference_type = ?";
                $params_r[] = $ref_type_filter;
            }
            if ($category_filter) {
                $where .= " AND reminder_category = ?";
                $params_r[] = $category_filter;
            }
            if ($priority_filter) {
                $where .= " AND priority = ?";
                $params_r[] = $priority_filter;
            }
            if ($date_filter === 'overdue') {
                $where .= " AND remind_at < ?";
                $params_r[] = $now;
            } elseif ($date_filter === 'today') {
                $where .= " AND remind_at BETWEEN ? AND ?";
                $params_r[] = $today_start;
                $params_r[] = $today_end;
            } elseif ($date_filter === 'upcoming') {
                $where .= " AND remind_at > ?";
                $params_r[] = $today_end;
            }
            if ($search) {
                $where .= " AND (title LIKE ? OR notes LIKE ? OR reference_label LIKE ?)";
                $params_r[] = "%$search%";
                $params_r[] = "%$search%";
                $params_r[] = "%$search%";
            }
            
            $stmt = $db->prepare("SELECT * FROM reminders WHERE $where ORDER BY 
                CASE WHEN remind_at < '$now' THEN 0 ELSE 1 END ASC,
                CASE priority WHEN 'High' THEN 0 WHEN 'Medium' THEN 1 ELSE 2 END ASC,
                remind_at ASC");
            $stmt->execute($params_r);
            $reminders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Enrich with customer/banker/partner mobile and real names
            foreach ($reminders as &$rem) {
                $ref_t = $rem['reference_type'] ?: $rem['lead_type'];
                $ref_i = (int)($rem['reference_id'] ?: $rem['lead_id']);
                $mobile = '';
                $name = '';
                if ($ref_i > 0) {
                    if ($ref_t === 'Lead') {
                        $row = $db->query("SELECT customer_name, mobile FROM applicants WHERE id=$ref_i")->fetch(PDO::FETCH_ASSOC);
                        if (!$row) $row = $db->query("SELECT lead_name as customer_name, mobile FROM leads WHERE id=$ref_i")->fetch(PDO::FETCH_ASSOC);
                        if ($row) { $name = $row['customer_name'] ?? ''; $mobile = $row['mobile'] ?? ''; }
                    } elseif ($ref_t === 'Banker') {
                        $row = $db->query("SELECT full_name, mobile FROM bankers WHERE id=$ref_i")->fetch(PDO::FETCH_ASSOC);
                        if ($row) { $name = $row['full_name'] ?? ''; $mobile = $row['mobile'] ?? ''; }
                    } elseif ($ref_t === 'Referral') {
                        $row = $db->query("SELECT full_name, mobile FROM referrals WHERE id=$ref_i")->fetch(PDO::FETCH_ASSOC);
                        if ($row) { $name = $row['full_name'] ?? ''; $mobile = $row['mobile'] ?? ''; }
                    } elseif ($ref_t === 'Pre-Lead') {
                        $row = $db->query("SELECT name, mobile FROM pre_leads WHERE id=$ref_i")->fetch(PDO::FETCH_ASSOC);
                        if ($row) { $name = $row['name'] ?? ''; $mobile = $row['mobile'] ?? ''; }
                    }
                }
                $rem['fetched_name'] = $name ?: ($rem['reference_label'] ?: '');
                $rem['fetched_mobile'] = $mobile;
            }
            unset($rem);

            return_json($reminders);
            break;

        case 'get_reminder_count':
            // Returns count of due/overdue reminders for bell badge
            $username = $_SESSION['username'] ?? '';
            $role = $_SESSION['role'] ?? '';
            $now = date('Y-m-d H:i:s');
            if ($role === 'Admin') {
                $s = $db->prepare("SELECT COUNT(*) FROM reminders WHERE status IN ('Pending','Snoozed') AND remind_at <= ? AND (snoozed_until IS NULL OR snoozed_until <= ?)");
                $s->execute([$now, $now]);
            } else {
                $s = $db->prepare("SELECT COUNT(*) FROM reminders WHERE status IN ('Pending','Snoozed') AND assigned_to = ? AND remind_at <= ? AND (snoozed_until IS NULL OR snoozed_until <= ?)");
                $s->execute([$username, $now, $now]);
            }
            $due_count = (int)$s->fetchColumn();
            
            // Also get top 5 upcoming/overdue for bell dropdown
            if ($role === 'Admin') {
                $s2 = $db->prepare("SELECT id, title, notes, remind_at, priority, reference_type, reference_label, reminder_category FROM reminders WHERE status IN ('Pending','Snoozed') AND (snoozed_until IS NULL OR snoozed_until <= ?) ORDER BY remind_at ASC LIMIT 5");
                $s2->execute([$now]);
            } else {
                $s2 = $db->prepare("SELECT id, title, notes, remind_at, priority, reference_type, reference_label, reminder_category FROM reminders WHERE status IN ('Pending','Snoozed') AND assigned_to = ? AND (snoozed_until IS NULL OR snoozed_until <= ?) ORDER BY remind_at ASC LIMIT 5");
                $s2->execute([$username, $now]);
            }
            return_json(['count' => $due_count, 'items' => $s2->fetchAll(PDO::FETCH_ASSOC)]);
            break;
            
        case 'save_reminder':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request'], 405);
            // New advanced save_reminder — supports all entity types
            $title        = trim($_POST['title'] ?? '');
            $ref_type     = $_POST['reference_type'] ?? 'General';
            $ref_id       = trim($_POST['reference_id'] ?? '');
            $ref_label    = trim($_POST['reference_label'] ?? '');
            $remind_at    = $_POST['remind_at'] ?? '';
            $notes        = trim($_POST['notes'] ?? '');
            $priority     = $_POST['priority'] ?? 'Medium';
            $category     = $_POST['reminder_category'] ?? 'Follow-up';
            $recurrence   = $_POST['recurrence'] ?? 'None';
            $assigned_to  = $_POST['assigned_to'] ?? $_SESSION['username'];
            
            // Legacy support — if old lead_type/lead_id sent
            $lead_type = $_POST['lead_type'] ?? $ref_type;
            $lead_id   = (int)($_POST['lead_id'] ?? ($ref_type !== 'General' ? $ref_id : 0));
            
            // If ref_label not given, try to auto-fetch from DB
            if (empty($ref_label) && $ref_id) {
                if ($ref_type === 'Lead') {
                    $lbl = $db->query("SELECT customer_name FROM applicants WHERE id=" . (int)$ref_id)->fetchColumn();
                    if (!$lbl) $lbl = $db->query("SELECT lead_name FROM leads WHERE id=" . (int)$ref_id)->fetchColumn();
                    $ref_label = $lbl ?: '';
                } elseif ($ref_type === 'Banker') {
                    $lbl = $db->query("SELECT full_name || ' (' || bank_name || ')' FROM bankers WHERE id=" . (int)$ref_id)->fetchColumn();
                    $ref_label = $lbl ?: '';
                } elseif ($ref_type === 'Referral') {
                    $lbl = $db->query("SELECT full_name FROM referrals WHERE id=" . (int)$ref_id)->fetchColumn();
                    $ref_label = $lbl ?: '';
                } elseif ($ref_type === 'Pre-Lead') {
                    $lbl = $db->query("SELECT name FROM pre_leads WHERE id=" . (int)$ref_id)->fetchColumn();
                    $ref_label = $lbl ?: '';
                }
            }
            
            if (empty($title)) $title = $ref_label ? "Follow-up: $ref_label" : 'New Reminder';
            
            // Auto-assign to entity owner if staff not given
            if ($ref_type === 'Lead' && $ref_id) {
                $owner = $db->query("SELECT added_by FROM applicants WHERE id=" . (int)$ref_id)->fetchColumn();
                if (!$owner) $owner = $db->query("SELECT assigned_to FROM leads WHERE id=" . (int)$ref_id)->fetchColumn();
                if ($owner && ($assigned_to === $_SESSION['username'])) $assigned_to = $owner;
            }
            
            $stmt = $db->prepare("INSERT INTO reminders (lead_type, lead_id, assigned_to, remind_at, notes, title, priority, reference_type, reference_id, reference_label, reminder_category, recurrence) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$lead_type, $lead_id, $assigned_to, $remind_at, $notes, $title, $priority, $ref_type, $ref_id, $ref_label, $category, $recurrence]);
            $new_id = $db->lastInsertId();
            
            log_activity("Set reminder: '$title' for $ref_type" . ($ref_label ? " ($ref_label)" : '') . " at $remind_at", "reminders.php");
            return_json(['success' => true, 'message' => 'Reminder saved!', 'id' => $new_id]);
            break;

        case 'complete_reminder':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request'], 405);
            $id = (int)($_POST['id'] ?? 0);
            if ($id) {
                $rem = $db->query("SELECT * FROM reminders WHERE id=$id")->fetch(PDO::FETCH_ASSOC);
                $db->prepare("UPDATE reminders SET status='Completed', completed_at=datetime('now'), completed_by=? WHERE id=?")->execute([$_SESSION['username'], $id]);
                $label = $rem ? ($rem['title'] ?: $rem['reference_label'] ?: 'reminder') : 'reminder';
                log_activity("Completed reminder: '$label'", "reminders.php");
                return_json(['success' => true]);
            }
            return_json(['error' => 'Missing ID'], 400);
            break;

        case 'delete_reminder':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request'], 405);
            $id = (int)($_POST['id'] ?? 0);
            if ($id) {
                $rem = $db->query("SELECT * FROM reminders WHERE id=$id")->fetch(PDO::FETCH_ASSOC);
                $role = $_SESSION['role'] ?? '';
                $username = $_SESSION['username'] ?? '';
                if ($role === 'Admin' || ($rem && $rem['assigned_to'] === $username)) {
                    $db->prepare("DELETE FROM reminders WHERE id=?")->execute([$id]);
                    $label = $rem ? ($rem['title'] ?: $rem['reference_label'] ?: 'reminder') : 'reminder';
                    log_activity("Deleted reminder #$id: '$label'", "reminders.php");
                    return_json(['success' => true, 'message' => 'Reminder deleted']);
                }
                return_json(['error' => 'Unauthorized to delete this reminder'], 403);
            }
            return_json(['error' => 'Missing ID'], 400);
            break;

        case 'snooze_reminder':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request'], 405);
            $id = (int)($_POST['id'] ?? 0);
            $minutes = (int)($_POST['minutes'] ?? 60);
            if ($id && $minutes > 0) {
                $snooze_until = date('Y-m-d H:i:s', strtotime("+$minutes minutes"));
                $db->prepare("UPDATE reminders SET status='Snoozed', snoozed_until=? WHERE id=?")->execute([$snooze_until, $id]);
                return_json(['success' => true, 'snoozed_until' => $snooze_until]);
            }
            return_json(['error' => 'Invalid request'], 400);
            break;

        case 'search_entity':
            // Powers the "Linked To" live search in Add Reminder modal
            $type = $_GET['type'] ?? 'Lead';
            $q    = trim($_GET['q'] ?? '');
            if (empty($q)) { return_json([]); break; }
            $like = "%$q%";
            $results = [];
            if ($type === 'Lead') {
                // Search applicants (main leads)
                $s = $db->prepare("SELECT id, customer_name AS label, loan_id AS sub, overall_status AS status FROM applicants WHERE customer_name LIKE ? OR loan_id LIKE ? OR mobile LIKE ? LIMIT 10");
                $s->execute([$like, $like, $like]);
                foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $results[] = ['id' => $r['id'], 'label' => $r['label'], 'sub' => $r['sub'] . ' · ' . $r['status'], 'type' => 'Lead'];
                }
            } elseif ($type === 'Banker') {
                $s = $db->prepare("SELECT id, full_name || ' - ' || bank_name AS label, designation AS sub FROM bankers WHERE full_name LIKE ? OR bank_name LIKE ? OR official_email LIKE ? LIMIT 10");
                $s->execute([$like, $like, $like]);
                foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $results[] = ['id' => $r['id'], 'label' => $r['label'], 'sub' => $r['sub'], 'type' => 'Banker'];
                }
            } elseif ($type === 'Referral') {
                $s = $db->prepare("SELECT id, full_name AS label, referrer_type AS sub FROM referrals WHERE full_name LIKE ? OR mobile LIKE ? OR referral_id LIKE ? LIMIT 10");
                $s->execute([$like, $like, $like]);
                foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $results[] = ['id' => $r['id'], 'label' => $r['label'], 'sub' => $r['sub'], 'type' => 'Referral'];
                }
            } elseif ($type === 'Pre-Lead') {
                $s = $db->prepare("SELECT id, name AS label, mobile AS sub FROM pre_leads WHERE name LIKE ? OR mobile LIKE ? LIMIT 10");
                $s->execute([$like, $like]);
                foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $results[] = ['id' => $r['id'], 'label' => $r['label'], 'sub' => $r['sub'], 'type' => 'Pre-Lead'];
                }
            }
            return_json($results);
            break;

        case 'get_leads':
            $s          = $_GET['search'] ?? '';
            $f_stage    = $_GET['stage'] ?? '';
            $f_priority = $_GET['priority'] ?? '';
            $f_assigned = $_GET['assigned_to'] ?? '';
            $f_type     = $_GET['loan_type'] ?? '';
            $f_source   = $_GET['source'] ?? '';
            $f_date     = $_GET['date_range'] ?? '';
            $f_amount   = $_GET['min_amount'] ?? '';
            
            $sql_l      = "SELECT * FROM leads WHERE 1=1";
            $params_l   = [];
            
            if (($_SESSION['role'] ?? '') !== 'Admin') {
                $user_name = $_SESSION['username'] ?? '';
                $sql_l .= " AND (assigned_to = ? OR added_by = ?)";
                $params_l[] = $user_name;
                $params_l[] = $user_name;
            }
            
            if ($s) { 
                $sql_l .= " AND (lead_name LIKE ? OR company_name LIKE ? OR mobile LIKE ? OR added_by LIKE ? OR notes LIKE ?)"; 
                $params_l = array_merge($params_l, ["%$s%","%$s%","%$s%","%$s%","%$s%"]); 
            }
            
            if ($f_stage)    { $sql_l .= " AND stage = ?";       $params_l[] = $f_stage; }
            if ($f_priority) { $sql_l .= " AND priority = ?";    $params_l[] = $f_priority; }
            if ($f_assigned) { $sql_l .= " AND assigned_to = ?"; $params_l[] = $f_assigned; }
            if ($f_type)     { $sql_l .= " AND requirement = ?"; $params_l[] = $f_type; }
            if ($f_source)   { $sql_l .= " AND lead_source = ?"; $params_l[] = $f_source; }
            if ($f_amount)   { $sql_l .= " AND CAST(loan_amount AS NUMERIC) >= ?"; $params_l[] = $f_amount; }
            
            if ($f_date) {
                if ($f_date === 'today') {
                    $sql_l .= " AND date(created_at) = date('now')";
                } elseif ($f_date === 'yesterday') {
                    $sql_l .= " AND date(created_at) = date('now', '-1 day')";
                } elseif ($f_date === 'last7') {
                    $sql_l .= " AND date(created_at) >= date('now', '-7 days')";
                } elseif ($f_date === 'this_month') {
                    $sql_l .= " AND strftime('%Y-%m', created_at) = strftime('%Y-%m', 'now')";
                }
            }
            
            $sql_l .= " ORDER BY created_at DESC";
            $stmt = $db->prepare($sql_l);
            $stmt->execute($params_l);
            $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Enhance leads with added_by details & calculate stats
            $total_count = count($leads);
            $today_count = 0;
            $hot_count = 0;
            $won_count = 0;
            $progress_count = 0;
            $stage_counts = [];
            $today_str = date('Y-m-d');

            $notes_map = [];
            try {
                $n_rows = $db->query("SELECT lead_id, COUNT(*) as cnt, note_text as latest_note, created_at as latest_note_at FROM lead_notes GROUP BY lead_id ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($n_rows as $nr) {
                    $notes_map[$nr['lead_id']] = $nr;
                }
            } catch(Exception $e) {}

            $cr_map = [];
            try {
                $cr_rows = $db->query("SELECT cr.*, a.loan_id as applicant_loan_id, a.customer_name as applicant_name, a.mobile as applicant_mobile, a.overall_status as applicant_status 
                                      FROM customer_referrals cr 
                                      LEFT JOIN applicants a ON cr.referrer_applicant_id = a.id")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($cr_rows as $crr) {
                    if (!empty($crr['referred_lead_id'])) {
                        $cr_map[$crr['referred_lead_id']] = $crr;
                    }
                }
            } catch(Exception $e) {}

            foreach ($leads as &$l) {
                $ref_id = $l['added_by'] ?? '';
                $src = $l['lead_source'] ?? '';
                
                // Attach notes info
                $l['notes_count'] = $notes_map[$l['id']]['cnt'] ?? 0;
                $l['latest_note'] = $notes_map[$l['id']]['latest_note'] ?? ($l['notes'] ?? '');
                $l['latest_note_at'] = $notes_map[$l['id']]['latest_note_at'] ?? '';

                // Fetch referrer information from referrals or users
                if (!empty($ref_id) || !empty($src)) {
                    $ref_stmt = $db->prepare("SELECT full_name, mobile, referrer_type, referral_id FROM referrals WHERE referral_id = ? OR full_name = ? OR mobile = ? LIMIT 1");
                    $ref_stmt->execute([$ref_id, $src, $ref_id]);
                    $ref = $ref_stmt->fetch(PDO::FETCH_ASSOC);
                    if ($ref) {
                        $l['referrer_name'] = $ref['full_name'];
                        $l['referrer_mobile'] = $ref['mobile'];
                        $l['referrer_type'] = $ref['referrer_type'];
                        $l['referrer_code'] = $ref['referral_id'];
                        $l['added_by_name'] = $ref['full_name'];
                        $l['added_by_role'] = $ref['referrer_type'];
                    } else {
                        $u_stmt = $db->prepare("SELECT COALESCE(name, full_name, username) as full_name, role FROM users WHERE username = ?");
                        $u_stmt->execute([$ref_id]);
                        $u = $u_stmt->fetch(PDO::FETCH_ASSOC);
                        if ($u) {
                            $l['added_by_name'] = $u['full_name'];
                            $l['added_by_role'] = $u['role'];
                            $l['referrer_name'] = $u['full_name'];
                            $l['referrer_mobile'] = '';
                            $l['referrer_type'] = $u['role'];
                        }
                    }
                }

                // Check if this lead was referred by an existing customer
                if (isset($cr_map[$l['id']])) {
                    $cr = $cr_map[$l['id']];
                    $cust_name = $cr['referrer_name'] ?: ($cr['applicant_name'] ?: 'Existing Customer');
                    $cust_mob = $cr['referrer_mobile'] ?: ($cr['applicant_mobile'] ?: '');
                    $cust_loan = $cr['applicant_loan_id'] ? '#' . $cr['applicant_loan_id'] : 'Applicant #' . $cr['referrer_applicant_id'];

                    $l['is_customer_referral'] = true;
                    $l['customer_referrer'] = [
                        'applicant_id' => $cr['referrer_applicant_id'],
                        'name' => $cust_name,
                        'mobile' => $cust_mob,
                        'loan_id' => $cr['applicant_loan_id'] ?: '',
                        'reward_amount' => $cr['reward_amount'] ?? 2000,
                        'reward_status' => $cr['reward_status'] ?? 'Pending'
                    ];
                    $l['referrer_name'] = $cust_name;
                    $l['referrer_mobile'] = $cust_mob;
                    $l['referrer_type'] = 'Customer Referral';
                    $l['added_by_name'] = $cust_name;
                    $l['added_by_role'] = 'Customer (' . $cust_loan . ')';
                }

                $st = $l['stage'] ?? 'New Lead';
                $pr = $l['priority'] ?? 'Cold';
                $stage_counts[$st] = ($stage_counts[$st] ?? 0) + 1;

                if (!empty($l['created_at']) && strpos($l['created_at'], $today_str) === 0) {
                    $today_count++;
                }
                if ($pr === 'Hot' && $st !== 'Converted' && $st !== 'Lost' && $st !== 'Rejected') {
                    $hot_count++;
                }
                if ($st === 'Converted') {
                    $won_count++;
                }
                if ($st !== 'Converted' && $st !== 'Lost' && $st !== 'Rejected') {
                    $progress_count++;
                }
            }
            unset($l);

            return_json([
                'success' => true,
                'leads' => $leads,
                'stats' => [
                    'total' => $total_count,
                    'today' => $today_count,
                    'hot' => $hot_count,
                    'won' => $won_count,
                    'progress' => $progress_count
                ],
                'stage_counts' => $stage_counts
            ]);
            break;

        case 'get_lead_detail':
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) return_json(['error' => 'Missing ID'], 400);
            $stmt = $db->prepare("SELECT * FROM leads WHERE id = ?");
            $stmt->execute([$id]);
            $lead = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$lead) return_json(['error' => 'Lead not found'], 404);
            
            // Security check: any authenticated staff/RM/Admin can view lead details in the CRM
            $user_role = $_SESSION['role'] ?? '';
            $username  = $_SESSION['username'] ?? '';
            if (!in_array($user_role, ['Admin', 'RM', 'Staff', 'Executive']) && ($lead['assigned_to'] ?? '') !== $username && ($lead['added_by'] ?? '') !== $username) {
                return_json(['error' => 'Unauthorized access to this lead'], 403);
            }

            // Fetch added_by name and referrer details
            $ref_id = $lead['added_by'] ?? '';
            $src = $lead['lead_source'] ?? '';
            if (!empty($ref_id) || !empty($src)) {
                $ref_stmt = $db->prepare("SELECT full_name, mobile, referrer_type, referral_id FROM referrals WHERE referral_id = ? OR full_name = ? OR mobile = ? LIMIT 1");
                $ref_stmt->execute([$ref_id, $src, $ref_id]);
                $ref = $ref_stmt->fetch(PDO::FETCH_ASSOC);
                if ($ref) {
                    $lead['referrer_name'] = $ref['full_name'];
                    $lead['referrer_mobile'] = $ref['mobile'];
                    $lead['referrer_type'] = $ref['referrer_type'];
                    $lead['referrer_code'] = $ref['referral_id'];
                    $lead['added_by_name'] = $ref['full_name'];
                    $lead['added_by_role'] = $ref['referrer_type'];
                } else {
                    $u_stmt = $db->prepare("SELECT u.username, COALESCE(u.name, u.full_name, u.username) as full_name, u.role, e.photo_path as referrer_photo FROM users u LEFT JOIN employees e ON (e.user_id = u.id OR e.official_email = u.username) WHERE u.username = ?");
                    $u_stmt->execute([$ref_id]);
                    $u = $u_stmt->fetch(PDO::FETCH_ASSOC);
                    if ($u) {
                        $lead['added_by_name'] = $u['full_name'];
                        $lead['added_by_role'] = $u['role'];
                        $lead['referrer_name'] = $u['full_name'];
                        $lead['referrer_mobile'] = '';
                        $lead['referrer_type'] = $u['role'];
                        $lead['referrer_photo'] = $u['referrer_photo'] ?? '';
                    }
                }
            }
            if (!empty($lead['assigned_to'])) {
                $u_stmt2 = $db->prepare("SELECT username, COALESCE(name, full_name, username) as full_name, role FROM users WHERE username = ?");
                $u_stmt2->execute([$lead['assigned_to']]);
                $u2 = $u_stmt2->fetch(PDO::FETCH_ASSOC);
                if ($u2) {
                    $lead['assigned_to_name'] = $u2['full_name'];
                }
            }

            // Fetch timeline activity notes
            $t_stmt = $db->prepare("SELECT * FROM lead_notes WHERE lead_id = ? ORDER BY created_at DESC");
            $t_stmt->execute([$id]);
            $lead['timeline'] = $t_stmt->fetchAll(PDO::FETCH_ASSOC);

            return_json($lead);
            break;

        case 'add_lead_note':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request'], 405);
            $lead_id   = (int)($_POST['lead_id'] ?? 0);
            $note_text = trim($_POST['note_text'] ?? ($_POST['note'] ?? ''));
            if (!$lead_id || !$note_text) return_json(['error' => 'Lead ID and note text required'], 400);
            
            $username = $_SESSION['username'] ?? 'System';
            $db->prepare("INSERT INTO lead_notes (lead_id, note_text, added_by_user) VALUES (?, ?, ?)")
               ->execute([$lead_id, $note_text, $username]);

            // Update lead timestamp
            try { $db->query("UPDATE leads SET updated_at = CURRENT_TIMESTAMP WHERE id = $lead_id"); } catch(Exception $e){}

            log_activity("Added follow-up note to lead #$lead_id: $note_text", "leads.php");
            return_json(['success' => true, 'message' => 'Note added successfully']);
            break;

        case 'update_lead_stage':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request'], 405);
            $id    = (int)($_POST['id'] ?? 0);
            $stage = trim($_POST['stage'] ?? '');
            $priority = trim($_POST['priority'] ?? '');
            $allowed_stages = ['New Lead', 'Contacted', 'Scheduled', 'Interested', 'Proposal Sent', 'Negotiation', 'Login', 'Converted', 'Disbursed', 'Won', 'Lost', 'Rejected'];
            if (!$id) return_json(['error' => 'Invalid ID'], 400);

            $lead = $db->query("SELECT lead_name FROM leads WHERE id = $id")->fetch();
            $lead_name = $lead ? $lead['lead_name'] : "Unknown";

            $updated = false;
            if ($stage && in_array($stage, $allowed_stages)) {
                $db->prepare("UPDATE leads SET stage = ? WHERE id = ?")->execute([$stage, $id]);
                log_activity("Updated lead stage for $lead_name to $stage", "leads.php?edit_lead=$id");
                $updated = true;
            }
            if ($priority && in_array($priority, ['Hot', 'Warm', 'Cold'])) {
                $db->prepare("UPDATE leads SET priority = ? WHERE id = ?")->execute([$priority, $id]);
                log_activity("Updated lead priority for $lead_name to $priority", "leads.php?edit_lead=$id");
                $updated = true;
            }
            if (isset($_POST['reminder_date'])) {
                $rem = trim($_POST['reminder_date']);
                $db->prepare("UPDATE leads SET reminder_date = ? WHERE id = ?")->execute([$rem ?: null, $id]);
                log_activity("Updated reminder date for $lead_name", "leads.php?edit_lead=$id");
                $updated = true;
            }
            if (!$updated) return_json(['error' => 'No valid stage, priority or reminder provided'], 400);
            return_json(['success' => true, 'message' => 'Lead updated successfully']);
            break;

        case 'delete_lead':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request'], 405);
            if (($_SESSION['role'] ?? '') !== 'Admin') return_json(['error' => 'Admin privileges required to delete leads'], 403);
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) return_json(['error' => 'Missing ID'], 400);
            $lead_name = $db->query("SELECT lead_name FROM leads WHERE id=$id")->fetchColumn();
            $db->prepare("DELETE FROM leads WHERE id=?")->execute([$id]);
            log_activity("Deleted lead: $lead_name", "leads.php");
            return_json(['success' => true, 'message' => 'Lead deleted']);
            break;

        case 'dashboard_analytics':
            $today = date('Y-m-d');
            $this_month = date('Y-m');
            $last_6_months = [];
            for ($i = 5; $i >= 0; $i--) {
                $last_6_months[] = date('Y-m', strtotime("-$i months"));
            }

            // 1. PRE-LEADS
            $pl_total    = (int)$db->query("SELECT COUNT(*) FROM pre_leads")->fetchColumn();
            $pl_new      = (int)$db->query("SELECT COUNT(*) FROM pre_leads WHERE (status='Not Contacted' OR status IS NULL OR status='') ")->fetchColumn();
            $pl_followup = (int)$db->query("SELECT COUNT(*) FROM pre_leads WHERE status='Follow Up' OR status='Interested'")->fetchColumn();
            $pl_junk     = (int)$db->query("SELECT COUNT(*) FROM pre_leads WHERE status='Not Interested' OR status='Junk'")->fetchColumn();

            // 2. LEADS
            $leads_total  = (int)$db->query("SELECT COUNT(*) FROM leads")->fetchColumn();
            $leads_hot    = (int)$db->query("SELECT COUNT(*) FROM leads WHERE priority='Hot'")->fetchColumn();
            $leads_month  = (int)$db->query("SELECT COUNT(*) FROM leads WHERE strftime('%Y-%m',created_at)='{$this_month}'")->fetchColumn();
            $leads_stages = $db->query("SELECT stage, COUNT(*) as cnt FROM leads GROUP BY stage")->fetchAll(PDO::FETCH_ASSOC);

            // 3. LOAN APPLICATIONS
            $apps_total     = (int)$db->query("SELECT COUNT(*) FROM applicants")->fetchColumn();
            $apps_active    = (int)$db->query("SELECT COUNT(*) FROM applicants WHERE overall_status NOT IN ('Completed','Rejected')")->fetchColumn();
            $apps_completed = (int)$db->query("SELECT COUNT(*) FROM applicants WHERE overall_status IN ('Completed','Disbursed')")->fetchColumn();
            $apps_rejected  = (int)$db->query("SELECT COUNT(*) FROM applicants WHERE overall_status='Rejected'")->fetchColumn();
            
            $disb_tbl = (float)($db->query("SELECT COALESCE(SUM(amount),0) FROM applicant_disbursements WHERE status IN ('Disbursed','Completed')")->fetchColumn() ?: 0);
            $app_tbl  = (float)($db->query("SELECT COALESCE(SUM(COALESCE(NULLIF(sanctioned_amount,0), loan_amount_requested)),0) FROM applicants WHERE overall_status IN ('Completed','Disbursed','Phase 4')")->fetchColumn() ?: 0);
            $apps_disbursed = max($disb_tbl, $app_tbl);

            $loan_types = $db->query("SELECT COALESCE(NULLIF(loan_type,''), 'Unspecified') as loan_type, COUNT(*) as cnt, COALESCE(SUM(loan_amount_requested), 0) as total_val FROM applicants GROUP BY loan_type ORDER BY cnt DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);

            $monthly_apps = [];
            foreach ($last_6_months as $m) {
                $cnt = (int)$db->query("SELECT COUNT(*) FROM applicants WHERE strftime('%Y-%m',created_at)='$m'")->fetchColumn();
                $disb_m1 = (float)($db->query("SELECT COALESCE(SUM(amount), 0) FROM applicant_disbursements WHERE status IN ('Disbursed','Completed') AND strftime('%Y-%m', disbursed_at)='$m'")->fetchColumn() ?: 0);
                $disb_m2 = (float)($db->query("SELECT COALESCE(SUM(COALESCE(disbursed_amount, sanctioned_amount)), 0) FROM applicants WHERE overall_status IN ('Completed','Disbursed') AND strftime('%Y-%m', disbursed_date)='$m'")->fetchColumn() ?: 0);
                $disb_vol = max($disb_m1, $disb_m2);
                $pipe_vol = (float)($db->query("SELECT COALESCE(SUM(loan_amount_requested), 0) FROM applicants WHERE strftime('%Y-%m',created_at)='$m'")->fetchColumn() ?: 0);
                $monthly_apps[] = [
                    'month'         => date('M y', strtotime($m . '-01')),
                    'count'         => $cnt,
                    'disbursed_vol' => $disb_vol,
                    'pipeline_vol'  => $pipe_vol
                ];
            }

            // 4. FIELD VISITS
            $fv_total  = (int)$db->query("SELECT COUNT(*) FROM field_visits")->fetchColumn();
            $fv_today  = (int)$db->query("SELECT COUNT(*) FROM field_visits WHERE date(visit_date)='{$today}'")->fetchColumn();
            $fv_month  = (int)$db->query("SELECT COUNT(*) FROM field_visits WHERE strftime('%Y-%m',visit_date)='{$this_month}'")->fetchColumn();
            $fv_weekly = [];
            for ($i = 6; $i >= 0; $i--) {
                $d   = date('Y-m-d', strtotime("-$i days"));
                $cnt = (int)$db->query("SELECT COUNT(*) FROM field_visits WHERE date(visit_date)='$d'")->fetchColumn();
                $fv_weekly[] = ['day' => date('D', strtotime($d)), 'count' => $cnt];
            }

            // 5. CLIENT VAULT
            $vault_total = (int)$db->query("SELECT COUNT(DISTINCT applicant_id) FROM applicant_documents")->fetchColumn();
            if ($vault_total == 0) $vault_total = $apps_total;
            $vault_prime = (int)$db->query("SELECT COUNT(*) FROM applicants WHERE overall_status IN ('Completed','Disbursed')")->fetchColumn();

            // 6. BANKERS
            $bankers_total  = (int)$db->query("SELECT COUNT(*) FROM bankers")->fetchColumn();
            $bankers_active = (int)$db->query("SELECT COUNT(*) FROM bankers WHERE status='Active'")->fetchColumn();
            $bank_assigned  = (int)$db->query("SELECT COUNT(*) FROM applicant_bank_assignments")->fetchColumn();
            $bank_approved  = (int)$db->query("SELECT COUNT(*) FROM applicant_bank_assignments WHERE status IN ('Approved','Sanctioned')")->fetchColumn();
            $bank_wise      = $db->query("SELECT bank_name, COUNT(*) as cnt FROM applicant_bank_assignments GROUP BY bank_name ORDER BY cnt DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);

            // 7. REFERRALS
            $referrals_total = (int)$db->query("SELECT COUNT(*) FROM referrals")->fetchColumn();
            $top_referrals   = $db->query("SELECT full_name as name FROM referrals WHERE status='Active' ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

            // 8. PAYOUTS
            try { $payout_total = (float)($db->query("SELECT COALESCE(SUM(net_payable),0) FROM payout_distributions WHERE status='Paid'")->fetchColumn() ?: 0); } catch (Exception $e) { $payout_total = 0; }
            try { $payout_month = (float)($db->query("SELECT COALESCE(SUM(net_payable),0) FROM payout_distributions WHERE status='Paid' AND strftime('%Y-%m',paid_on)='{$this_month}'")->fetchColumn() ?: 0); } catch (Exception $e) { $payout_month = 0; }

            // 9. STAFF
            $staff_total  = (int)$db->query("SELECT COUNT(*) FROM users WHERE role='Staff'")->fetchColumn();
            $staff_online = (int)$db->query("SELECT COUNT(*) FROM users WHERE role='Staff' AND last_active >= datetime('now','localtime','-2 minutes')")->fetchColumn();
            $staff_perf   = $db->query("SELECT added_by as name, COUNT(*) as cnt FROM applicants WHERE added_by IS NOT NULL AND added_by != '' GROUP BY added_by ORDER BY cnt DESC LIMIT 7")->fetchAll(PDO::FETCH_ASSOC);

            // 10. EMAILS / COMMUNICATIONS
            $emails_today = (int)$db->query("SELECT COUNT(*) FROM communications WHERE date(sent_at)='{$today}'")->fetchColumn();
            $emails_month = (int)$db->query("SELECT COUNT(*) FROM communications WHERE strftime('%Y-%m',sent_at)='{$this_month}'")->fetchColumn();

            // 11. REMINDERS
            $reminders_today   = (int)$db->query("SELECT COUNT(*) FROM reminders WHERE date(remind_at)='{$today}' AND status='Pending'")->fetchColumn();
            $reminders_overdue = (int)$db->query("SELECT COUNT(*) FROM reminders WHERE remind_at < '{$today}' AND status='Pending'")->fetchColumn();

            // 12. EXECUTIVE CONTROL TOWER ANALYTICS (PULSE, ALERTS & PERFORMANCE)
            $pipe_vol = (float)($db->query("SELECT COALESCE(SUM(loan_amount_requested), 0) FROM applicants WHERE overall_status NOT IN ('Completed', 'Rejected')")->fetchColumn() ?: 0);
            $disb_month_tbl = (float)($db->query("SELECT COALESCE(SUM(amount), 0) FROM applicant_disbursements WHERE status IN ('Disbursed', 'Completed') AND strftime('%Y-%m', disbursed_at) = '{$this_month}'")->fetchColumn() ?: 0);
            $disb_month_app = (float)($db->query("SELECT COALESCE(SUM(COALESCE(disbursed_amount, sanctioned_amount)), 0) FROM applicants WHERE overall_status IN ('Completed', 'Disbursed') AND strftime('%Y-%m', disbursed_date) = '{$this_month}'")->fetchColumn() ?: 0);
            $disb_this_month = max($disb_month_tbl, $disb_month_app);
            
            $dsa_comm_received = (float)($db->query("SELECT COALESCE(SUM(payout_received_amount), 0) FROM applicants WHERE payout_received = 1")->fetchColumn() ?: 0);
            $dsa_comm_est = $dsa_comm_received > 0 ? $dsa_comm_received : round($apps_disbursed * 0.015, 2);
            $net_company_profit = max(0, round($dsa_comm_est - $payout_total, 2));

            // Alerts
            $unassigned_leads = (int)$db->query("SELECT COUNT(*) FROM leads WHERE assigned_to IS NULL OR assigned_to = '' OR assigned_to = '0'")->fetchColumn();
            $stalled_files = (int)$db->query("SELECT COUNT(*) FROM applicants WHERE overall_status IN ('Phase 2', 'Phase 3') AND date(COALESCE(last_activity_date, created_at)) <= date('now', '-5 days')")->fetchColumn();
            $sanctioned_ready = (int)$db->query("SELECT COUNT(*) FROM applicants WHERE overall_status = 'Phase 4' OR (sanctioned_amount > 0 AND overall_status NOT IN ('Completed', 'Rejected'))")->fetchColumn();
            $sanctioned_ready_vol = (float)($db->query("SELECT COALESCE(SUM(sanctioned_amount), 0) FROM applicants WHERE overall_status = 'Phase 4' OR (sanctioned_amount > 0 AND overall_status NOT IN ('Completed', 'Rejected'))")->fetchColumn() ?: 0);
            $pending_payouts_cnt = (int)$db->query("SELECT COUNT(*) FROM payout_distributions WHERE status = 'Pending'")->fetchColumn();
            $pending_payouts_amt = (float)($db->query("SELECT COALESCE(SUM(net_payable), 0) FROM payout_distributions WHERE status = 'Pending'")->fetchColumn() ?: 0);

            // 5-Phase Funnel breakdown
            $funnel_p1 = (int)$db->query("SELECT COUNT(*) FROM applicants WHERE overall_status = 'Phase 1'")->fetchColumn();
            $funnel_p2 = (int)$db->query("SELECT COUNT(*) FROM applicants WHERE overall_status = 'Phase 2'")->fetchColumn();
            $funnel_p3 = (int)$db->query("SELECT COUNT(*) FROM applicants WHERE overall_status = 'Phase 3'")->fetchColumn();
            $funnel_p4 = (int)$db->query("SELECT COUNT(*) FROM applicants WHERE overall_status = 'Phase 4'")->fetchColumn();
            $funnel_p5 = (int)$db->query("SELECT COUNT(*) FROM applicants WHERE overall_status IN ('Completed', 'Disbursed')")->fetchColumn();

            // Channels Mix
            $channel_mix = $db->query("
                SELECT 
                    SUM(CASE WHEN lead_source LIKE '%Agent%' THEN 1 ELSE 0 END) as agent_leads,
                    SUM(CASE WHEN lead_source LIKE '%CA%' THEN 1 ELSE 0 END) as ca_leads,
                    SUM(CASE WHEN lead_source LIKE '%Partner%' OR lead_source LIKE '%DSA%' THEN 1 ELSE 0 END) as partner_leads,
                    SUM(CASE WHEN lead_source LIKE '%Builder%' OR notes LIKE '%Tower%' OR notes LIKE '%Flat%' THEN 1 ELSE 0 END) as builder_leads,
                    SUM(CASE WHEN lead_source NOT LIKE '%Agent%' AND lead_source NOT LIKE '%CA%' AND lead_source NOT LIKE '%Partner%' AND lead_source NOT LIKE '%DSA%' AND lead_source NOT LIKE '%Builder%' THEN 1 ELSE 0 END) as direct_leads
                FROM leads
            ")->fetch(PDO::FETCH_ASSOC);

            // RM Workload & Leaderboard
            $rm_workload = $db->query("
                SELECT u.name, u.username, u.role,
                    (SELECT COUNT(*) FROM applicants a WHERE a.assigned_to = u.username AND a.overall_status NOT IN ('Completed', 'Rejected')) as active_files,
                    (SELECT COALESCE(SUM(COALESCE(a.disbursed_amount, a.sanctioned_amount)), 0) FROM applicants a WHERE a.assigned_to = u.username AND a.overall_status IN ('Completed', 'Disbursed')) as disbursed_vol,
                    (SELECT COUNT(*) FROM leads l WHERE l.assigned_to = u.username AND l.stage NOT IN ('Converted', 'Dead', 'Lost')) as active_leads
                FROM users u
                WHERE u.role IN ('RM', 'Staff', 'Admin')
                ORDER BY disbursed_vol DESC, active_files DESC
                LIMIT 5
            ")->fetchAll(PDO::FETCH_ASSOC);

            // Top Banks Performance
            $top_banks = $db->query("
                SELECT 
                    bank_name,
                    COUNT(*) as total_logins,
                    SUM(CASE WHEN status IN ('Approved', 'Sanctioned') THEN 1 ELSE 0 END) as approvals,
                    COALESCE(SUM(sanctioned_amount), 0) as total_sanctioned
                FROM applicant_bank_assignments
                WHERE bank_name IS NOT NULL AND bank_name != ''
                GROUP BY bank_name
                ORDER BY total_logins DESC
                LIMIT 5
            ")->fetchAll(PDO::FETCH_ASSOC);

            return_json([
                'pre_leads'    => ['total'=>$pl_total,'new'=>$pl_new,'followup'=>$pl_followup,'junk'=>$pl_junk],
                'leads'        => ['total'=>$leads_total,'hot'=>$leads_hot,'this_month'=>$leads_month,'stages'=>$leads_stages],
                'applications' => ['total'=>$apps_total,'active'=>$apps_active,'completed'=>$apps_completed,'rejected'=>$apps_rejected,'disbursed'=>$apps_disbursed,'loan_types'=>$loan_types,'monthly_growth'=>$monthly_apps],
                'field_visits' => ['total'=>$fv_total,'today'=>$fv_today,'this_month'=>$fv_month,'weekly'=>$fv_weekly],
                'client_vault' => ['total'=>$vault_total,'prime'=>$vault_prime],
                'bankers'      => ['total'=>$bankers_total,'active'=>$bankers_active,'assignments'=>$bank_assigned,'approved'=>$bank_approved,'bank_wise'=>$bank_wise],
                'referrals'    => ['total'=>$referrals_total,'top'=>$top_referrals],
                'payouts'      => ['total'=>$payout_total,'this_month'=>$payout_month],
                'staff'        => ['total'=>$staff_total,'online'=>$staff_online,'performance'=>$staff_perf],
                'emails'       => ['today'=>$emails_today,'this_month'=>$emails_month],
                'reminders'    => ['today'=>$reminders_today,'overdue'=>$reminders_overdue],
                
                // New Executive Command Center Payload
                'executive'    => [
                    'pipeline_volume'      => $pipe_vol,
                    'disbursed_total'      => $apps_disbursed,
                    'disbursed_this_month' => $disb_this_month,
                    'dsa_commission_est'   => $dsa_comm_est,
                    'payouts_released'     => $payout_total,
                    'net_company_profit'   => $net_company_profit,
                ],
                'alerts'       => [
                    'unassigned_leads'     => $unassigned_leads,
                    'stalled_files'        => $stalled_files,
                    'sanctioned_ready'     => $sanctioned_ready,
                    'sanctioned_ready_vol' => $sanctioned_ready_vol,
                    'pending_payouts'      => $pending_payouts_cnt,
                    'pending_payouts_amt'  => $pending_payouts_amt,
                ],
                'funnel'       => [
                    'raw_enquiries'        => $pl_total,
                    'active_leads'         => $leads_total,
                    'phase1'               => $funnel_p1,
                    'phase2'               => $funnel_p2,
                    'phase3'               => $funnel_p3,
                    'phase4'               => $funnel_p4,
                    'phase5'               => $funnel_p5,
                    'active_underwriting'  => ($funnel_p1 + $funnel_p2 + $funnel_p3),
                ],
                'channel_mix'  => $channel_mix,
                'rm_workload'  => $rm_workload,
                'top_banks'    => $top_banks,
            ]);
            break;

        case 'stats':
            $username = $_SESSION['username'] ?? '';
            $isStaff = ($_SESSION['role'] ?? '') === 'Staff';
            $assignedFilter = $isStaff ? " WHERE assigned_to = '$username'" : "";
            $assignedFilterAnd = $isStaff ? " AND assigned_to = '$username'" : "";
            $quotationJoinFilter = $isStaff ? " JOIN clients c ON quotations.client_id = c.id WHERE c.assigned_to = '$username' AND " : " WHERE ";
            $quotationJoinFilterEmpty = $isStaff ? " JOIN clients c ON quotations.client_id = c.id WHERE c.assigned_to = '$username'" : "";
            $commJoinFilter = $isStaff ? " JOIN clients c ON communications.client_id = c.id WHERE c.assigned_to = '$username' AND " : " WHERE ";

            $total_clients = $db->query("SELECT COUNT(*) FROM clients$assignedFilter")->fetchColumn();
            $emails_today = $db->query("SELECT COUNT(*) FROM communications" . $commJoinFilter . "date(sent_at) = date('now', 'localtime')")->fetchColumn();
            $quotes_this_month = $db->query("SELECT COUNT(quotations.id) FROM quotations" . $quotationJoinFilter . "strftime('%Y-%m', quotations.created_at) = strftime('%Y-%m', 'now', 'localtime')")->fetchColumn();
            $pending_followups = $db->query("SELECT COUNT(*) FROM leads WHERE priority = 'Hot' AND stage IN ('New', 'Contacted', 'In Negotiation') $assignedFilterAnd")->fetchColumn();
            $total_quote_value = $db->query("SELECT SUM(quotations.total_amount) FROM quotations" . $quotationJoinFilterEmpty)->fetchColumn() ?: 0;
            $no_quotation_clients = $db->query("SELECT COUNT(*) FROM clients WHERE id NOT IN (SELECT DISTINCT client_id FROM quotations) $assignedFilterAnd")->fetchColumn();
            
            $total_staff = $db->query("SELECT COUNT(*) FROM users WHERE role = 'Staff'")->fetchColumn();
            $online_staff = $db->query("SELECT COUNT(*) FROM users WHERE role = 'Staff' AND last_active >= datetime('now', 'localtime', '-2 minutes')")->fetchColumn();
            $active_staff = $db->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();
            
            $total_leads = $db->query("SELECT COUNT(*) FROM leads$assignedFilter")->fetchColumn();
            $hot_leads   = $db->query("SELECT COUNT(*) FROM leads WHERE priority='Hot' AND stage NOT IN ('Won','Lost') $assignedFilterAnd")->fetchColumn();
            $total_leads_month = $db->query("SELECT COUNT(*) FROM leads WHERE strftime('%Y-%m', created_at) = strftime('%Y-%m', 'now', 'localtime') $assignedFilterAnd")->fetchColumn();
            $active_loan_files = $db->query("SELECT COUNT(*) FROM applicants WHERE overall_status NOT IN ('Completed', 'Rejected')")->fetchColumn();
            $total_disbursed_amt = $db->query("SELECT SUM(amount) FROM applicant_disbursements WHERE status = 'Disbursed'")->fetchColumn();
            $rejected_files = $db->query("SELECT COUNT(*) FROM applicants WHERE overall_status = 'Rejected'")->fetchColumn();
            
            return_json([
                'total_clients'        => (int)$total_clients,
                'emails_today'         => (int)$emails_today,
                'quotes_this_month'    => (int)$quotes_this_month,
                'pending_followups'    => (int)$pending_followups,
                'total_quote_value'    => (float)$total_quote_value,
                'no_quotation_clients' => (int)$no_quotation_clients,
                'total_staff'          => (int)$total_staff,
                'online_staff'         => (int)$online_staff,
                'active_staff'         => (int)$active_staff,
                'total_leads'          => (int)$total_leads,
                'hot_leads'            => (int)$hot_leads,
                'total_leads_month'    => (int)$total_leads_month,
                'active_loan_files'    => (int)$active_loan_files,
                'total_disbursed_amt'  => (float)$total_disbursed_amt,
                'rejected_files'       => (int)$rejected_files,
            ]);
            break;

        case 'charts_data':
            $username = $_SESSION['username'] ?? '';
            $isStaff = ($_SESSION['role'] ?? '') === 'Staff';
            $assignedFilter = $isStaff ? " WHERE assigned_to = '$username'" : "";
            $assignedFilterAnd = $isStaff ? " AND assigned_to = '$username'" : "";
            $commJoinFilter = $isStaff ? " JOIN clients c ON communications.client_id = c.id WHERE c.assigned_to = '$username' AND " : " WHERE ";
            $quoteJoinFilterEmpty = $isStaff ? " JOIN clients c ON quotations.client_id = c.id WHERE c.assigned_to = '$username'" : "";
            $topClientsFilter = $isStaff ? " WHERE c.assigned_to = '$username' " : "";

            $growth_query = $db->query("SELECT strftime('%Y-%m', created_at) as month, COUNT(*) as count FROM clients $assignedFilter GROUP BY month ORDER BY month ASC");
            $growth_data = [];
            while ($row = $growth_query->fetch()) {
                $dateObj = DateTime::createFromFormat('!Y-m', $row['month']);
                $monthName = $dateObj ? $dateObj->format('M Y') : $row['month'];
                $growth_data[] = ['label' => $monthName, 'value' => (int)$row['count']];
            }
            
            $pitch_count = $db->query("SELECT COUNT(DISTINCT communications.client_id) FROM communications $commJoinFilter communications.type = 'Pitch'")->fetchColumn();
            $ppt_count = $db->query("SELECT COUNT(DISTINCT communications.client_id) FROM communications $commJoinFilter communications.type = 'PPT'")->fetchColumn();
            $mail_count = $db->query("SELECT COUNT(DISTINCT communications.client_id) FROM communications $commJoinFilter communications.type = 'Custom Mail'")->fetchColumn();
            $quote_count = $db->query("SELECT COUNT(DISTINCT quotations.client_id) FROM quotations $quoteJoinFilterEmpty")->fetchColumn();
            $closed_count = $db->query("SELECT COUNT(*) FROM clients WHERE overall_status = 'Closed Won' $assignedFilterAnd")->fetchColumn();
            
            $funnel = [
                ['stage' => 'Pitch Sent', 'count' => (int)$pitch_count],
                ['stage' => 'PPT Shared', 'count' => (int)$ppt_count],
                ['stage' => 'Custom Mail', 'count' => (int)$mail_count],
                ['stage' => 'Quotation Sent', 'count' => (int)$quote_count],
                ['stage' => 'Deals Won', 'count' => (int)$closed_count]
            ];
            
            $top_clients_query = $db->query("
                SELECT c.company_name, SUM(q.total_amount) as total_val 
                FROM clients c 
                JOIN quotations q ON c.id = q.client_id 
                $topClientsFilter
                GROUP BY c.id 
                ORDER BY total_val DESC 
                LIMIT 5
            ");
            $top_clients = [];
            while ($row = $top_clients_query->fetch()) {
                $top_clients[] = ['name' => $row['company_name'], 'value' => (float)$row['total_val']];
            }
            
            $activity_summary = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-$i days"));
                $day_name = date('D', strtotime("-$i days"));
                $act_count = $db->query("SELECT COUNT(*) FROM activities WHERE date(created_at) = '$date'")->fetchColumn();
                $activity_summary[] = ['label' => $day_name, 'value' => (int)$act_count];
            }

            return_json([
                'growth' => $growth_data,
                'funnel' => $funnel,
                'top_clients' => $top_clients,
                'activity_weekly' => $activity_summary
            ]);
            break;

        case 'get_activity_logs':
            $current_role = $_SESSION['role'] ?? 'Staff';
            $current_user = $_SESSION['username'] ?? '';

            // Fetch distinct system users for dropdown
            $users_stmt = $db->query("SELECT u.username, u.name, u.role FROM users u ORDER BY u.name ASC");
            $all_users = $users_stmt ? $users_stmt->fetchAll(PDO::FETCH_ASSOC) : [];

            $user_map = [];
            foreach ($all_users as $u) {
                if (!empty($u['username'])) {
                    $user_map[strtolower($u['username'])] = $u;
                }
            }

            // Filters
            $search = trim($_GET['search'] ?? '');
            $filter_user = trim($_GET['username'] ?? '');
            $filter_category = trim($_GET['category'] ?? '');
            $date_range = trim($_GET['date_range'] ?? '');
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = max(10, min(200, (int)($_GET['limit'] ?? 50)));
            $offset = ($page - 1) * $limit;

            $where = ["1=1"];
            $params = [];

            // If non-admin user, restrict logs to their own actions or actions targeted at them
            if ($current_role !== 'Admin') {
                $where[] = "(a.username = ? OR a.target_user = ? OR a.description LIKE ?)";
                $params[] = $current_user;
                $params[] = $current_user;
                $params[] = "%[$current_user]%";
            } elseif ($filter_user !== '') {
                $where[] = "(a.username = ? OR a.target_user = ? OR a.description LIKE ?)";
                $params[] = $filter_user;
                $params[] = $filter_user;
                $params[] = "%[$filter_user]%";
            }

            if ($search !== '') {
                $where[] = "(a.description LIKE ? OR a.username LIKE ? OR a.target_user LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }

            if ($date_range === 'today') {
                $where[] = "date(a.created_at) = date('now')";
            } elseif ($date_range === 'yesterday') {
                $where[] = "date(a.created_at) = date('now', '-1 day')";
            } elseif ($date_range === 'week') {
                $where[] = "a.created_at >= date('now', '-7 days')";
            } elseif ($date_range === 'month') {
                $where[] = "a.created_at >= date('now', '-30 days')";
            }

            if ($filter_category !== '') {
                if ($filter_category === 'financial') {
                    $where[] = "(a.description LIKE '%payout%' OR a.description LIKE '%invoice%' OR a.description LIKE '%commission%' OR a.description LIKE '%disburs%' OR a.description LIKE '%fee%')";
                } elseif ($filter_category === 'client') {
                    $where[] = "(a.description LIKE '%lead%' OR a.description LIKE '%applicant%' OR a.description LIKE '%client%' OR a.description LIKE '%kyc%')";
                } elseif ($filter_category === 'documents') {
                    $where[] = "(a.description LIKE '%doc%' OR a.description LIKE '%upload%' OR a.description LIKE '%file%')";
                } elseif ($filter_category === 'attendance') {
                    $where[] = "(a.description LIKE '%punch%' OR a.description LIKE '%duty%' OR a.description LIKE '%attendance%')";
                } elseif ($filter_category === 'system') {
                    $where[] = "(a.description LIKE '%delete%' OR a.description LIKE '%setting%' OR a.description LIKE '%user%' OR a.description LIKE '%admin%')";
                }
            }

            $where_sql = implode(' AND ', $where);

            // Total count matching query
            $count_stmt = $db->prepare("SELECT COUNT(*) FROM activities a WHERE $where_sql");
            $count_stmt->execute($params);
            $total_count = (int)$count_stmt->fetchColumn();

            // Fetch rows
            $sql = "SELECT a.* FROM activities a WHERE $where_sql ORDER BY a.id DESC LIMIT $limit OFFSET $offset";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $formatted = [];
            foreach ($rows as $r) {
                $uKey = strtolower($r['username'] ?? '');
                $uInfo = $user_map[$uKey] ?? null;
                $displayName = $uInfo ? ($uInfo['name'] ?: $r['username']) : ($r['username'] ?: 'System');
                $userRole = $uInfo ? ($uInfo['role'] ?: 'Staff') : ($r['username'] === 'admin' ? 'Admin' : 'System');

                $cleanDesc = preg_replace('/^\[(.*?)\]\s*/', '', $r['description']);

                $descLower = strtolower($r['description']);
                $cat = 'General Audit';
                $catColor = '#64748b';
                $catBg = '#f1f5f9';

                if (strpos($descLower, 'payout') !== false || strpos($descLower, 'invoice') !== false || strpos($descLower, 'commission') !== false || strpos($descLower, 'disburs') !== false) {
                    $cat = 'Financial & Payouts';
                    $catColor = '#059669';
                    $catBg = '#ecfdf5';
                } elseif (strpos($descLower, 'lead') !== false || strpos($descLower, 'applicant') !== false || strpos($descLower, 'client') !== false) {
                    $cat = 'Client & Lead CRM';
                    $catColor = '#2563eb';
                    $catBg = '#eff6ff';
                } elseif (strpos($descLower, 'doc') !== false || strpos($descLower, 'upload') !== false || strpos($descLower, 'file') !== false) {
                    $cat = 'Document Management';
                    $catColor = '#7c3aed';
                    $catBg = '#f5f3ff';
                } elseif (strpos($descLower, 'punch') !== false || strpos($descLower, 'duty') !== false || strpos($descLower, 'attendance') !== false) {
                    $cat = 'Attendance & HR';
                    $catColor = '#d97706';
                    $catBg = '#fffbeb';
                } elseif (strpos($descLower, 'delete') !== false || strpos($descLower, 'admin') !== false || strpos($descLower, 'setting') !== false) {
                    $cat = 'System & Security';
                    $catColor = '#dc2626';
                    $catBg = '#fef2f2';
                }

                $ts = strtotime($r['created_at']);
                $diff = time() - $ts;
                if ($diff < 60) {
                    $timeAgo = 'Just now';
                } elseif ($diff < 3600) {
                    $timeAgo = floor($diff / 60) . 'm ago';
                } elseif ($diff < 86400) {
                    $timeAgo = floor($diff / 3600) . 'h ago';
                } else {
                    $timeAgo = floor($diff / 86400) . 'd ago';
                }

                $formatted[] = [
                    'id' => $r['id'],
                    'username' => $r['username'] ?: 'System',
                    'display_name' => $displayName,
                    'user_role' => $userRole,
                    'description' => $cleanDesc,
                    'raw_description' => $r['description'],
                    'action_link' => $r['action_link'],
                    'target_user' => $r['target_user'],
                    'created_at' => $r['created_at'],
                    'created_at_formatted' => date('d M Y, h:i A', $ts),
                    'time_ago' => $timeAgo,
                    'category' => $cat,
                    'category_color' => $catColor,
                    'category_bg' => $catBg
                ];
            }

            // Summary metrics
            $total_all = (int)$db->query("SELECT COUNT(*) FROM activities")->fetchColumn();
            $today_count = (int)$db->query("SELECT COUNT(*) FROM activities WHERE date(created_at) = date('now')")->fetchColumn();
            $fin_today = (int)$db->query("SELECT COUNT(*) FROM activities WHERE (description LIKE '%payout%' OR description LIKE '%invoice%' OR description LIKE '%disburs%' OR description LIKE '%commission%')")->fetchColumn();
            $unique_users_count = (int)$db->query("SELECT COUNT(DISTINCT username) FROM activities WHERE username IS NOT NULL")->fetchColumn();

            return_json([
                'success' => true,
                'logs' => $formatted,
                'total_count' => $total_count,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($total_count / $limit),
                'summary' => [
                    'total_all' => $total_all,
                    'today_count' => $today_count,
                    'financial_count' => $fin_today,
                    'unique_users' => $unique_users_count
                ],
                'users_list' => $all_users
            ]);
            break;

        case 'recent_activities':
            $user = $_GET['user'] ?? '';
            if (($_SESSION['role'] ?? '') === 'Staff') {
                $user = $_SESSION['username'] ?? '';
            }
            $days = (int)($_GET['days'] ?? 0);
            
            $sql = "SELECT description, created_at, action_link FROM activities WHERE 1=1";
            $params = [];
            if ($user) {
                $sql .= " AND (username = ? OR target_user = ? OR description LIKE ?)";
                $params[] = $user;
                $params[] = $user;
                $params[] = "%[$user]%";
            }
            if ($days > 0) {
                $sql .= " AND created_at >= date('now', '-$days days')";
            }
            $sql .= " ORDER BY id DESC LIMIT 50";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $activities = $stmt->fetchAll();
            
            foreach ($activities as &$act) {
                $timestamp = strtotime($act['created_at'] . ' UTC');
                $diff = time() - $timestamp;
                
                if ($diff < 60) {
                    $act['time_formatted'] = 'Just now';
                } elseif ($diff < 3600) {
                    $act['time_formatted'] = round($diff / 60) . ' mins ago';
                } elseif ($diff < 86400) {
                    $act['time_formatted'] = round($diff / 3600) . ' hrs ago';
                } else {
                    if (date('Y-m-d', $timestamp) == date('Y-m-d')) {
                        $act['time_formatted'] = 'Today ' . date('h:i A', $timestamp);
                    } else {
                        $act['time_formatted'] = date('d M h:i A', $timestamp);
                    }
                }
            }
            return_json($activities);
            break;

        case 'search_clients':
            $query_term = isset($_GET['query']) ? trim($_GET['query']) : '';
            $status = isset($_GET['status']) ? trim($_GET['status']) : '';
            $priority = isset($_GET['priority']) ? trim($_GET['priority']) : '';
            $city = isset($_GET['city']) ? trim($_GET['city']) : '';
            $added_by = isset($_GET['added_by']) ? trim($_GET['added_by']) : '';
            $date_start = isset($_GET['date_start']) ? trim($_GET['date_start']) : '';
            $date_end = isset($_GET['date_end']) ? trim($_GET['date_end']) : '';
            
            $sql = "SELECT * FROM clients WHERE 1=1";
            $params = [];
            
            if (($_SESSION['role'] ?? '') !== 'Admin') {
                $sql .= " AND (assigned_to = :assigned_to OR added_by = :assigned_to)";
                $params[':assigned_to'] = $_SESSION['username'] ?? '';
            }
            
            if ($query_term !== '') {
                $sql .= " AND (company_name LIKE :q OR contact_name LIKE :q OR email LIKE :q OR city LIKE :q OR gstin LIKE :q)";
                $params[':q'] = "%$query_term%";
            }
            if ($status !== '') {
                $sql .= " AND overall_status = :status";
                $params[':status'] = $status;
            }
            if ($priority !== '') {
                $sql .= " AND priority = :priority";
                $params[':priority'] = $priority;
            }
            if ($city !== '') {
                $sql .= " AND city = :city";
                $params[':city'] = $city;
            }
            if ($added_by !== '') {
                $sql .= " AND added_by = :added_by";
                $params[':added_by'] = $added_by;
            }
            if ($date_start !== '') {
                $sql .= " AND date(created_at) >= :date_start";
                $params[':date_start'] = $date_start;
            }
            if ($date_end !== '') {
                $sql .= " AND date(created_at) <= :date_end";
                $params[':date_end'] = $date_end;
            }
            
            $sql .= " ORDER BY id DESC";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $clients = $stmt->fetchAll();
            
            foreach ($clients as &$c) {
                $c['pitch_sent'] = $db->query("SELECT sent_at FROM communications WHERE client_id = {$c['id']} AND (type = 'Pitch' OR type = 'Call') ORDER BY id DESC LIMIT 1")->fetchColumn() ?: null;
                $c['ppt_sent'] = $db->query("SELECT sent_at FROM communications WHERE client_id = {$c['id']} AND type = 'PPT' ORDER BY id DESC LIMIT 1")->fetchColumn() ?: null;
                $c['mail_sent'] = $db->query("SELECT sent_at FROM communications WHERE client_id = {$c['id']} AND type = 'Custom Mail' ORDER BY id DESC LIMIT 1")->fetchColumn() ?: null;
                $c['quotation_sent'] = $db->query("SELECT created_at FROM quotations WHERE client_id = {$c['id']} ORDER BY id DESC LIMIT 1")->fetchColumn() ?: null;
                $c['quotations'] = $db->query("SELECT * FROM quotations WHERE client_id = {$c['id']} ORDER BY id DESC")->fetchAll();
            }
            
            return_json($clients);
            break;

        case 'client_details':
            $id = (int)$_GET['id'];
            $client = $db->prepare("SELECT * FROM clients WHERE id = ?");
            $client->execute([$id]);
            $c = $client->fetch();
            
            if (!$c) {
                return_json(['error' => 'Client not found'], 404);
            }
            
            if (($_SESSION['role'] ?? '') !== 'Admin' && $c['assigned_to'] !== ($_SESSION['username'] ?? '') && $c['added_by'] !== ($_SESSION['username'] ?? '')) {
                return_json(['error' => 'Unauthorized Access to this client\'s details.'], 403);
            }
            
            $comms = $db->prepare("SELECT * FROM communications WHERE client_id = ? ORDER BY id DESC");
            $comms->execute([$id]);
            $c['communications_logs'] = $comms->fetchAll();
            
            $quotes = $db->prepare("SELECT * FROM quotations WHERE client_id = ? ORDER BY id DESC");
            $quotes->execute([$id]);
            $c['quotations'] = $quotes->fetchAll();
            
            $c['pitch_sent'] = $db->query("SELECT sent_at FROM communications WHERE client_id = $id AND (type = 'Pitch' OR type = 'Call') ORDER BY id DESC LIMIT 1")->fetchColumn() ?: null;
            $c['ppt_sent'] = $db->query("SELECT sent_at FROM communications WHERE client_id = $id AND type = 'PPT' ORDER BY id DESC LIMIT 1")->fetchColumn() ?: null;
            $c['mail_sent'] = $db->query("SELECT sent_at FROM communications WHERE client_id = $id AND type = 'Custom Mail' ORDER BY id DESC LIMIT 1")->fetchColumn() ?: null;
            $c['quotation_sent'] = $db->query("SELECT created_at FROM quotations WHERE client_id = $id ORDER BY id DESC LIMIT 1")->fetchColumn() ?: null;
            
            return_json($c);
            break;

        case 'get_unique_filters':
            $cities = $db->query("SELECT DISTINCT city FROM clients WHERE city != '' ORDER BY city")->fetchAll(PDO::FETCH_COLUMN);
            $states = $db->query("SELECT DISTINCT state FROM clients WHERE state != '' ORDER BY state")->fetchAll(PDO::FETCH_COLUMN);
            $added_by = $db->query("SELECT username FROM users ORDER BY username")->fetchAll(PDO::FETCH_COLUMN);
            
            return_json([
                'cities' => $cities,
                'states' => $states,
                'added_by' => $added_by
            ]);
            break;

        case 'add_client':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                return_json(['error' => 'Invalid Request Method'], 405);
            }
            
            $company_name = trim($_POST['company_name'] ?? '');
            $business_type = trim($_POST['business_type'] ?? '');
            $industry_sector = trim($_POST['industry_sector'] ?? '');
            $gstin = strtoupper(trim($_POST['gstin'] ?? ''));
            $pan = strtoupper(trim($_POST['pan'] ?? ''));
            $website = trim($_POST['website'] ?? '');
            $turnover = trim($_POST['turnover'] ?? '');
            $employees = !empty($_POST['employees']) ? (int)$_POST['employees'] : null;
            
            $contact_name = trim($_POST['contact_name'] ?? '');
            $designation = trim($_POST['designation'] ?? '');
            $mobile = trim($_POST['mobile'] ?? '');
            $whatsapp = trim($_POST['whatsapp'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $alternate_email = trim($_POST['alternate_email'] ?? '');
            $linkedin = trim($_POST['linkedin'] ?? '');
            
            $address_line1 = trim($_POST['address_line1'] ?? '');
            $address_line2 = trim($_POST['address_line2'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $state = trim($_POST['state'] ?? '');
            $pincode = trim($_POST['pincode'] ?? '');
            $country = trim($_POST['country'] ?? 'India');
            
            $bank_name = trim($_POST['bank_name'] ?? '');
            $account_number = trim($_POST['account_number'] ?? '');
            $ifsc_code = strtoupper(trim($_POST['ifsc_code'] ?? ''));
            
            $lead_source = trim($_POST['lead_source'] ?? '');
            $priority = trim($_POST['priority'] ?? 'Warm');
            $added_by = trim($_POST['added_by'] ?? 'System');
            $remarks = trim($_POST['remarks'] ?? '');
            $source_lead_id = isset($_POST['source_lead_id']) ? (int)$_POST['source_lead_id'] : null;

            $assigned_to = trim($_POST['assigned_to'] ?? '');
            if (empty($assigned_to) && ($_SESSION['role'] ?? '') === 'Staff') {
                $assigned_to = $_SESSION['username'] ?? '';
            }
            
            if (empty($company_name) || empty($business_type) || empty($contact_name) || empty($mobile) || empty($email) || empty($address_line1) || empty($city) || empty($state) || empty($pincode) || empty($country)) {
                return_json(['error' => 'Please fill in all mandatory fields.'], 400);
            }
            
            $chk = $db->prepare("SELECT COUNT(*) FROM clients WHERE company_name = ?");
            $chk->execute([$company_name]);
            if ($chk->fetchColumn() > 0) {
                return_json(['error' => "A client company named '{$company_name}' already exists."], 400);
            }
            
            $sql = "INSERT INTO clients (
                company_name, business_type, industry_sector, gstin, pan, website, turnover, employees,
                contact_name, designation, mobile, whatsapp, email, alternate_email, linkedin,
                address_line1, address_line2, city, state, pincode, country,
                bank_name, account_number, ifsc_code,
                lead_source, priority, added_by, remarks, overall_status, assigned_to
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'New', ?)";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $company_name, $business_type, $industry_sector, $gstin, $pan, $website, $turnover, $employees,
                $contact_name, $designation, $mobile, $whatsapp, $email, $alternate_email, $linkedin,
                $address_line1, $address_line2, $city, $state, $pincode, $country,
                $bank_name, $account_number, $ifsc_code,
                $lead_source, $priority, $added_by, $remarks, $assigned_to
            ]);
            
            if ($source_lead_id) {
                $db->query("UPDATE leads SET stage='Won' WHERE id=$source_lead_id");
            }
            
            $new_id = $db->lastInsertId();
            $action_desc = "{$company_name} added by {$added_by} — Today " . date('h:i A');
            log_activity($action_desc, "search_track.php?view_client=$new_id");
            
            return_json(['success' => true, 'message' => 'Client profile locked & registered successfully!']);
            break;

        case 'log_call':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                return_json(['error' => 'Invalid Request Method'], 405);
            }
            
            $client_id = (int)$_POST['client_id'];
            $outcome = trim($_POST['outcome'] ?? '');
            $remarks = trim($_POST['remarks'] ?? '');
            $sent_by = $_SESSION['username'] ?? 'System';
            
            if (empty($client_id) || empty($outcome) || empty($remarks)) {
                return_json(['error' => 'Outcome and remarks are required fields.'], 400);
            }
            
            $client_query = $db->prepare("SELECT company_name, assigned_to FROM clients WHERE id = ?");
            $client_query->execute([$client_id]);
            $client = $client_query->fetch();
            
            if (!$client) {
                return_json(['error' => 'Client not found.'], 404);
            }
            
            $stmt = $db->prepare("INSERT INTO communications (client_id, type, subject, body, sent_by) VALUES (?, 'Call', ?, ?, ?)");
            $stmt->execute([$client_id, "Call Log: " . $outcome, $remarks, $sent_by]);
            
            // Update overall_status based on outcome
            $new_status = 'Pitched'; // default
            if ($outcome === 'Connected - Not Interested' || $outcome === 'No Answer' || $outcome === 'Number Busy / Invalid') {
                $new_status = 'Not Interested';
            } elseif ($outcome === 'Connected - Interested') {
                $new_status = 'Interested';
            } elseif ($outcome === 'Connected - Follow-up requested') {
                $new_status = 'Follow-up';
            }
            
            $update_stmt = $db->prepare("UPDATE clients SET overall_status = ? WHERE id = ?");
            $update_stmt->execute([$new_status, $client_id]);
            
            // Log to dashboard activities
            $act_desc = "Call made to {$client['company_name']} by {$sent_by} — Response: {$outcome}";
            log_activity($act_desc, "search_track.php?view_client=$client_id", $client['assigned_to']);
            
            return_json(['success' => true, 'message' => 'Call logged successfully!']);
            break;

        case 'log_ppt_shared':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                return_json(['error' => 'Invalid Request Method'], 405);
            }
            
            $client_id = (int)$_POST['client_id'];
            $remarks = trim($_POST['remarks'] ?? 'PPT shared manually via external platform (e.g. WhatsApp/Meeting).');
            $sent_by = $_SESSION['username'] ?? 'System';
            
            $client_query = $db->prepare("SELECT company_name, assigned_to FROM clients WHERE id = ?");
            $client_query->execute([$client_id]);
            $client = $client_query->fetch();
            
            if (!$client) {
                return_json(['error' => 'Client not found.'], 404);
            }
            
            $stmt = $db->prepare("INSERT INTO communications (client_id, type, subject, body, sent_by) VALUES (?, 'PPT', ?, ?, ?)");
            $stmt->execute([$client_id, "Manual Log: PPT Shared", $remarks, $sent_by]);
            
            $act_desc = "PPT manually marked as shared for {$client['company_name']} by {$sent_by}";
            log_activity($act_desc, "search_track.php?view_client=$client_id", $client['assigned_to']);
            
            return_json(['success' => true, 'message' => 'PPT marked as shared successfully!']);
            break;

        case 'send_email':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                return_json(['error' => 'Invalid Request Method'], 405);
            }
            
            $client_id = (int)$_POST['client_id'];
            $cc = trim($_POST['cc'] ?? '');
            $subject = trim($_POST['subject'] ?? '');
            $body = trim($_POST['body'] ?? '');
            $type = trim($_POST['type'] ?? 'Custom Mail');
            $sent_by = trim($_POST['sent_by'] ?? 'System');
            $dispatch_mode = trim($_POST['dispatch_mode'] ?? 'simulated');
            
            if (empty($client_id) || empty($subject) || empty($body)) {
                return_json(['error' => 'Client, Subject, and Body are required fields.'], 400);
            }
            
            $client_query = $db->prepare("SELECT company_name, email, overall_status, assigned_to FROM clients WHERE id = ?");
            $client_query->execute([$client_id]);
            $client = $client_query->fetch();
            
            if (!$client) {
                // Fallback check applicants table (Loan Operations)
                $app_query = $db->prepare("SELECT customer_name as company_name, email, overall_status, assigned_to FROM applicants WHERE id = ?");
                $app_query->execute([$client_id]);
                $client = $app_query->fetch();
            }
            if (!$client) {
                // Fallback check pre_leads table
                $pl_query = $db->prepare("SELECT customer_name as company_name, email, status as overall_status, assigned_to FROM pre_leads WHERE id = ?");
                $pl_query->execute([$client_id]);
                $client = $pl_query->fetch();
            }
            
            if (!$client) {
                return_json(['error' => 'Recipient Client or Applicant account not found.'], 404);
            }
            
            $attachment_name = null;
            $template_id = $_POST['template_id'] ?? null;
            $ppt_id = $_POST['ppt_id'] ?? null;

            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['attachment']['tmp_name'];
                $orig_name = basename($_FILES['attachment']['name']);
                $file_ext = pathinfo($orig_name, PATHINFO_EXTENSION);
                $safe_name = time() . '_' . preg_replace("/[^a-zA-Z0-9\._-]/", "", $orig_name);
                
                if (move_uploaded_file($file_tmp, $uploads_dir . '/' . $safe_name)) {
                    $attachment_name = $safe_name;
                }
            } elseif ($ppt_id) {
                $ppt_res = $db->query("SELECT filename FROM presentations WHERE id = " . (int)$ppt_id)->fetchColumn();
                if ($ppt_res) {
                    $attachment_name = $ppt_res;
                }
            } elseif ($template_id) {
                $tmpl = $db->prepare("SELECT attachment_name FROM email_templates WHERE id = ?");
                $tmpl->execute([$template_id]);
                $tmpl_res = $tmpl->fetch();
                if ($tmpl_res && !empty($tmpl_res['attachment_name'])) {
                    $attachment_name = $tmpl_res['attachment_name'];
                }
            }

            // Always save to communications table (Audit Log)
            $stmt = $db->prepare("INSERT INTO communications (client_id, type, subject, body, cc, attachment_name, sent_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$client_id, $type, $subject, $body, $cc, $attachment_name, $sent_by]);
            
            $new_status = $client['overall_status'];
            if ($client['overall_status'] === 'New') {
                $new_status = 'Contacted';
                $upd = $db->prepare("UPDATE clients SET overall_status = 'Contacted' WHERE id = ?");
                $upd->execute([$client_id]);
            }
            
            $act_desc = "{$type} email dispatched to {$client['company_name']} by {$sent_by} — " . date('h:i A');
            log_activity($act_desc, "search_track.php?view_client=$client_id", $client['assigned_to']);

            // Live SMTP Mode Dispatch
            if ($dispatch_mode === 'smtp') {
                $profile_stmt = $db->query("SELECT smtp_host, smtp_port, smtp_username, smtp_password, smtp_encryption FROM company_profile LIMIT 1");
                $profile = $profile_stmt->fetch();
                
                if ($profile && !empty($profile['smtp_host']) && !empty($profile['smtp_username'])) {
                    require_once __DIR__ . '/libs/PHPMailer/Exception.php';
                    require_once __DIR__ . '/libs/PHPMailer/PHPMailer.php';
                    require_once __DIR__ . '/libs/PHPMailer/SMTP.php';

                    try {
                        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                        $mail->isSMTP();
                        $mail->Host       = $profile['smtp_host'];
                        $mail->SMTPAuth   = true;
                        $mail->Username   = $profile['smtp_username'];
                        $mail->Password   = $profile['smtp_password'];
                        if (strtolower($profile['smtp_encryption']) === 'tls') {
                            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                        } elseif (strtolower($profile['smtp_encryption']) === 'ssl') {
                            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                        }
                        $mail->Port       = (int)$profile['smtp_port'];

                        $mail->setFrom($profile['smtp_username'], 'BFS Financial Services');
                        $mail->addAddress($client['email'], $client['company_name']);
                        if (!empty($cc)) $mail->addCC($cc);
                        if ($attachment_name) $mail->addAttachment($uploads_dir . '/' . $attachment_name);

                        $mail->isHTML(true);
                        $mail->Subject = $subject;
                        $mail->Body    = $body;
                        $mail->AltBody = strip_tags($body);

                        $mail->send();
                        return_json([
                            'success' => true, 
                            'message' => 'Live Email sent successfully via SMTP!', 
                            'new_status' => $new_status,
                            'attachment' => $attachment_name
                        ]);
                    } catch (Exception $e) {
                        return_json([
                            'success' => true,
                            'message' => 'Simulated Email Logged (SMTP Error: ' . $e->getMessage() . ')',
                            'new_status' => $new_status
                        ]);
                    }
                } else {
                    return_json([
                        'success' => true, 
                        'message' => 'Simulated Email Logged (SMTP Not Configured). Recorded in Audit History.', 
                        'new_status' => $new_status
                    ]);
                }
            }

            return_json([
                'success' => true, 
                'message' => 'Simulated Email Dispatched & Logged Successfully!', 
                'new_status' => $new_status,
                'attachment' => $attachment_name
            ]);
            break;

        case 'get_all_recipients':
            // Fetch Active Applicants
            $applicants = [];
            try {
                $apps_stmt = $db->query("SELECT id, loan_id, customer_name, mobile, email, loan_type, loan_amount_requested as loan_amount, overall_status, tracking_token, assigned_to, 'applicant' as rec_type FROM applicants ORDER BY id DESC LIMIT 200");
                $applicants = $apps_stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {}

            // Fetch Clients
            $clients = [];
            try {
                $clients_stmt = $db->query("SELECT id, id as loan_id, company_name as customer_name, mobile, email, business_type as loan_type, turnover as loan_amount, overall_status, NULL as tracking_token, assigned_to, 'client' as rec_type FROM clients ORDER BY id DESC LIMIT 200");
                $clients = $clients_stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {}

            // Fetch Pre-Leads
            $pre_leads = [];
            try {
                $pre_stmt = $db->query("SELECT id, id as loan_id, COALESCE(name, company_name, 'Lead') as customer_name, mobile, email, loan_type, loan_amount_requested as loan_amount, status as overall_status, NULL as tracking_token, assigned_to, 'pre_lead' as rec_type FROM pre_leads ORDER BY id DESC LIMIT 200");
                $pre_leads = $pre_stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {}

            return_json([
                'success' => true,
                'applicants' => $applicants,
                'clients' => $clients,
                'pre_leads' => $pre_leads
            ]);
            break;

        case 'get_email_logs':
            $stmt = $db->query("SELECT c.*, cl.company_name, cl.contact_name FROM communications c LEFT JOIN clients cl ON cl.id = c.client_id ORDER BY c.id DESC LIMIT 100");
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return_json(['success' => true, 'logs' => $logs]);
            break;

        case 'get_inbound_replies':
            // Fetch all inbound communications
            $stmt = $db->query("
                SELECT c.*, 
                       COALESCE(a.customer_name, cl.company_name, pl.name, c.sent_by) as borrower_name,
                       COALESCE(a.loan_id, cl.id, pl.id) as ref_id,
                       COALESCE(a.loan_type, cl.business_type, pl.loan_type, 'Loan') as product_type,
                       COALESCE(a.assigned_to, cl.assigned_to, pl.assigned_to, 'System') as assigned_agent,
                       p.subject as parent_subject,
                       p.body as parent_body,
                       p.sent_at as parent_sent_at
                FROM communications c
                LEFT JOIN applicants a ON (a.email = c.sender_email OR a.id = c.client_id)
                LEFT JOIN clients cl ON (cl.email = c.sender_email OR cl.id = c.client_id)
                LEFT JOIN pre_leads pl ON (pl.email = c.sender_email OR pl.id = c.client_id)
                LEFT JOIN communications p ON p.id = c.reply_to_comm_id
                WHERE c.direction = 'inbound'
                GROUP BY c.id
                ORDER BY c.id DESC
                LIMIT 100
            ");
            $replies = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $unread_count = (int)$db->query("SELECT count(*) FROM communications WHERE direction = 'inbound' AND (is_read = 0 OR is_read IS NULL)")->fetchColumn();

            return_json([
                'success' => true,
                'replies' => $replies,
                'unread_count' => $unread_count
            ]);
            break;

        case 'mark_reply_read':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid method'], 405);
            $comm_id = (int)($_POST['comm_id'] ?? 0);
            if ($comm_id) {
                $stmt = $db->prepare("UPDATE communications SET is_read = 1 WHERE id = ?");
                $stmt->execute([$comm_id]);
            }
            return_json(['success' => true, 'comm_id' => $comm_id]);
            break;

        case 'inbound_email_webhook':
        case 'simulate_incoming_reply':
            // Can be called via JSON webhook or POST form
            $raw_input = file_get_contents('php://input');
            $json_payload = json_decode($raw_input, true);

            $sender_email = trim($_POST['sender_email'] ?? $_POST['from'] ?? $_POST['sender'] ?? $json_payload['from'] ?? $json_payload['sender'] ?? $json_payload['sender_email'] ?? '');
            $sender_name = trim($_POST['sender_name'] ?? $_POST['from_name'] ?? $json_payload['from_name'] ?? $json_payload['sender_name'] ?? '');
            $subject = trim($_POST['subject'] ?? $json_payload['subject'] ?? 'Re: Loan Application Inquiry');
            $body = trim($_POST['body'] ?? $json_payload['html'] ?? $json_payload['text'] ?? $json_payload['body'] ?? '');
            $reply_to_comm_id = (int)($_POST['reply_to_comm_id'] ?? $json_payload['reply_to_id'] ?? 0);

            if (empty($sender_email)) {
                return_json(['error' => 'Sender email address is required.'], 400);
            }
            if (empty($body)) {
                $body = "Client responded to email regarding '{$subject}'.";
            }

            // Find matching borrower in applicants, clients, pre_leads
            $matched_client_id = 0;
            $borrower_name = $sender_name;
            $assigned_to = null;

            $chk_app = $db->prepare("SELECT id, customer_name, assigned_to FROM applicants WHERE email = ? LIMIT 1");
            $chk_app->execute([$sender_email]);
            $app = $chk_app->fetch(PDO::FETCH_ASSOC);
            if ($app) {
                $matched_client_id = (int)$app['id'];
                if (!$borrower_name) $borrower_name = $app['customer_name'];
                $assigned_to = $app['assigned_to'];
            } else {
                $chk_cl = $db->prepare("SELECT id, company_name, assigned_to FROM clients WHERE email = ? LIMIT 1");
                $chk_cl->execute([$sender_email]);
                $cl = $chk_cl->fetch(PDO::FETCH_ASSOC);
                if ($cl) {
                    $matched_client_id = (int)$cl['id'];
                    if (!$borrower_name) $borrower_name = $cl['company_name'];
                    $assigned_to = $cl['assigned_to'];
                } else {
                    $chk_pl = $db->prepare("SELECT id, COALESCE(name, company_name) as name, assigned_to FROM pre_leads WHERE email = ? LIMIT 1");
                    $chk_pl->execute([$sender_email]);
                    $pl = $chk_pl->fetch(PDO::FETCH_ASSOC);
                    if ($pl) {
                        $matched_client_id = (int)$pl['id'];
                        if (!$borrower_name) $borrower_name = $pl['name'];
                        $assigned_to = $pl['assigned_to'];
                    }
                }
            }

            if (!$borrower_name) $borrower_name = $sender_email;

            // If reply_to_comm_id not passed, find latest sent email to this recipient
            if (!$reply_to_comm_id) {
                $find_p = $db->prepare("SELECT id FROM communications WHERE client_id = ? AND direction = 'outbound' ORDER BY id DESC LIMIT 1");
                $find_p->execute([$matched_client_id]);
                $reply_to_comm_id = (int)$find_p->fetchColumn() ?: null;
            }

            $stmt = $db->prepare("
                INSERT INTO communications (client_id, type, subject, body, sent_by, direction, sender_email, is_read, reply_to_comm_id)
                VALUES (?, 'Incoming Reply', ?, ?, ?, 'inbound', ?, 0, ?)
            ");
            $stmt->execute([
                $matched_client_id,
                $subject,
                $body,
                $borrower_name,
                $sender_email,
                $reply_to_comm_id
            ]);
            $new_comm_id = (int)$db->lastInsertId();

            // Log activity
            $act_desc = "📥 Inbound email reply from {$borrower_name} ({$sender_email}): '{$subject}'";
            log_activity($act_desc, "send_email.php?tab=inbox", $assigned_to);

            return_json([
                'success' => true,
                'message' => 'Incoming reply ingested successfully into CRM Inbox',
                'comm_id' => $new_comm_id,
                'borrower_name' => $borrower_name,
                'sender_email' => $sender_email
            ]);
            break;

        case 'quick_reply_to_inbound':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid method'], 405);

            $parent_comm_id = (int)($_POST['parent_comm_id'] ?? 0);
            $recipient_email = trim($_POST['recipient_email'] ?? '');
            $recipient_name = trim($_POST['recipient_name'] ?? '');
            $subject = trim($_POST['subject'] ?? '');
            $body = trim($_POST['body'] ?? '');
            $dispatch_mode = trim($_POST['dispatch_mode'] ?? 'simulated');
            $sent_by = $_SESSION['username'] ?? 'RM Executive';

            if (empty($recipient_email) || empty($subject) || empty($body)) {
                return_json(['error' => 'Recipient email, subject, and reply body are required.'], 400);
            }

            // Find client id
            $client_id = 0;
            $chk = $db->prepare("SELECT id FROM applicants WHERE email = ? LIMIT 1");
            $chk->execute([$recipient_email]);
            $client_id = (int)$chk->fetchColumn() ?: 0;

            // Save outbound communication
            $stmt = $db->prepare("
                INSERT INTO communications (client_id, type, subject, body, sent_by, direction, reply_to_comm_id)
                VALUES (?, 'Inbound Response', ?, ?, ?, 'outbound', ?)
            ");
            $stmt->execute([$client_id, $subject, $body, $sent_by, $parent_comm_id]);

            // Mark parent inbound reply as read
            if ($parent_comm_id) {
                $db->prepare("UPDATE communications SET is_read = 1 WHERE id = ?")->execute([$parent_comm_id]);
            }

            // If live SMTP
            if ($dispatch_mode === 'smtp') {
                $profile_stmt = $db->query("SELECT smtp_host, smtp_port, smtp_username, smtp_password, smtp_encryption FROM company_profile LIMIT 1");
                $profile = $profile_stmt->fetch();
                if ($profile && !empty($profile['smtp_host'])) {
                    require_once __DIR__ . '/libs/PHPMailer/Exception.php';
                    require_once __DIR__ . '/libs/PHPMailer/PHPMailer.php';
                    require_once __DIR__ . '/libs/PHPMailer/SMTP.php';
                    try {
                        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                        $mail->isSMTP();
                        $mail->Host = $profile['smtp_host'];
                        $mail->SMTPAuth = true;
                        $mail->Username = $profile['smtp_username'];
                        $mail->Password = $profile['smtp_password'];
                        if (strtolower($profile['smtp_encryption']) === 'tls') {
                            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                        } elseif (strtolower($profile['smtp_encryption']) === 'ssl') {
                            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                        }
                        $mail->Port = (int)$profile['smtp_port'];
                        $mail->setFrom($profile['smtp_username'], 'BFS Financial Services');
                        $mail->addAddress($recipient_email, $recipient_name ?: $recipient_email);
                        $mail->addReplyTo($profile['smtp_username'], 'BFS Financial Services');
                        $mail->isHTML(true);
                        $mail->Subject = $subject;
                        $mail->Body = $body;
                        $mail->AltBody = strip_tags($body);
                        $mail->send();
                    } catch (\Exception $e) {}
                }
            }

            return_json([
                'success' => true,
                'message' => 'Follow-up reply dispatched and logged successfully!'
            ]);
            break;

        case 'send_bulk_segment_email':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid method'], 405);
            $segment_name = trim($_POST['segment_name'] ?? '');
            $subject = trim($_POST['subject'] ?? '');
            $body = trim($_POST['body'] ?? '');

            if (empty($segment_name) || empty($subject) || empty($body)) {
                return_json(['error' => 'Segment, Subject, and Body are required.'], 400);
            }

            if ($segment_name === 'ALL') {
                $stmt = $db->query("SELECT id, customer_name, mobile, email FROM pre_leads");
            } else {
                $stmt = $db->prepare("SELECT id, customer_name, mobile, email FROM pre_leads WHERE segment_name = ?");
                $stmt->execute([$segment_name]);
            }
            $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $count = count($leads);

            log_activity("Bulk email dispatched to {$count} leads in segment '{$segment_name}'", "send_email.php");
            return_json(['success' => true, 'message' => "Bulk emails queued and logged for {$count} leads in segment '{$segment_name}'."]);
            break;

        case 'send_campaign_single_email':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid method'], 405);

            $recipient_email = trim($_POST['recipient_email'] ?? '');
            $recipient_name = trim($_POST['recipient_name'] ?? '');
            $subject = trim($_POST['subject'] ?? '');
            $body = trim($_POST['body'] ?? '');
            $campaign_name = trim($_POST['campaign_name'] ?? 'Bulk CSV Campaign');
            $dispatch_mode = trim($_POST['dispatch_mode'] ?? 'simulated'); // 'smtp' or 'simulated'
            $sent_by = $_SESSION['username'] ?? 'Bulk Dispatcher';

            if (empty($recipient_email) || !filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
                return_json(['success' => false, 'error' => "Invalid email address format: '{$recipient_email}'"], 400);
            }
            if (empty($subject) || empty($body)) {
                return_json(['success' => false, 'error' => 'Subject and Email Body are required.'], 400);
            }

            // Check if email matches existing client or applicant
            $matched_client_id = 0;
            $chk = $db->prepare("SELECT id FROM clients WHERE email = ? LIMIT 1");
            $chk->execute([$recipient_email]);
            $c_id = $chk->fetchColumn();
            if ($c_id) {
                $matched_client_id = (int)$c_id;
            } else {
                $chk_app = $db->prepare("SELECT id FROM applicants WHERE email = ? LIMIT 1");
                $chk_app->execute([$recipient_email]);
                $a_id = $chk_app->fetchColumn();
                if ($a_id) $matched_client_id = (int)$a_id;
            }

            if ($dispatch_mode === 'smtp') {
                $profile_stmt = $db->query("SELECT smtp_host, smtp_port, smtp_username, smtp_password, smtp_encryption FROM company_profile LIMIT 1");
                $profile = $profile_stmt->fetch();

                if ($profile && !empty($profile['smtp_host']) && !empty($profile['smtp_username'])) {
                    require_once __DIR__ . '/libs/PHPMailer/Exception.php';
                    require_once __DIR__ . '/libs/PHPMailer/PHPMailer.php';
                    require_once __DIR__ . '/libs/PHPMailer/SMTP.php';

                    try {
                        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                        $mail->isSMTP();
                        $mail->Host       = $profile['smtp_host'];
                        $mail->SMTPAuth   = true;
                        $mail->Username   = $profile['smtp_username'];
                        $mail->Password   = $profile['smtp_password'];
                        if (strtolower($profile['smtp_encryption']) === 'tls') {
                            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                        } elseif (strtolower($profile['smtp_encryption']) === 'ssl') {
                            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                        }
                        $mail->Port       = (int)$profile['smtp_port'];

                        $from_name = 'BFS Financial Services';
                        $mail->setFrom($profile['smtp_username'], $from_name);
                        $mail->addAddress($recipient_email, $recipient_name ?: $recipient_email);

                        $mail->isHTML(true);
                        $mail->Subject = $subject;
                        $mail->Body    = $body;
                        $mail->AltBody = strip_tags($body);

                        $mail->send();

                        // Log to communications table
                        $log_type = 'Bulk Campaign: ' . $campaign_name;
                        $stmt = $db->prepare("INSERT INTO communications (client_id, type, subject, body, sent_by) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$matched_client_id, $log_type, $subject, $body, $sent_by]);

                        return_json([
                            'success' => true,
                            'mode' => 'smtp',
                            'message' => 'Dispatched live via SMTP',
                            'email' => $recipient_email
                        ]);
                    } catch (\Exception $e) {
                        return_json([
                            'success' => false,
                            'mode' => 'smtp',
                            'error' => 'SMTP Failed: ' . $e->getMessage(),
                            'email' => $recipient_email
                        ], 500);
                    }
                } else {
                    return_json([
                        'success' => false,
                        'mode' => 'smtp',
                        'error' => 'SMTP settings not configured in company profile',
                        'email' => $recipient_email
                    ], 400);
                }
            } else {
                // Simulated Delivery Mode
                $log_type = 'Bulk Campaign (Simulated): ' . $campaign_name;
                $stmt = $db->prepare("INSERT INTO communications (client_id, type, subject, body, sent_by) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$matched_client_id, $log_type, $subject, $body, $sent_by]);

                return_json([
                    'success' => true,
                    'mode' => 'simulated',
                    'message' => 'Simulated delivery recorded in audit log',
                    'email' => $recipient_email
                ]);
            }
            break;

        case 'save_quotation':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                return_json(['error' => 'Invalid Request Method'], 405);
            }
            
            $client_id = (int)$_POST['client_id'];
            $subtotal = (float)$_POST['subtotal'];
            $gst_amount = (float)$_POST['gst_amount'];
            $total_amount = (float)$_POST['total_amount'];
            $items_json = $_POST['items_json'];
            
            if (empty($client_id) || empty($items_json)) {
                return_json(['error' => 'Invalid Client or Quotation Line items.'], 400);
            }
            
            $c_name = $db->query("SELECT company_name FROM clients WHERE id = $client_id")->fetchColumn();
            if (!$c_name) {
                return_json(['error' => 'Client not found'], 404);
            }
            
            $max_id = $db->query("SELECT MAX(id) FROM quotations")->fetchColumn() ?: 0;
            $next_num = $max_id + 1;
            $quotation_number = "Q" . str_pad($next_num, 3, '0', STR_PAD_LEFT);
            
            $stmt = $db->prepare("INSERT INTO quotations (client_id, quotation_number, status, subtotal, gst_amount, total_amount, items_json) VALUES (?, ?, 'Pending', ?, ?, ?, ?)");
            $stmt->execute([$client_id, $quotation_number, $subtotal, $gst_amount, $total_amount, $items_json]);
            
            $current_status = $db->query("SELECT overall_status FROM clients WHERE id = $client_id")->fetchColumn();
            if (in_array($current_status, ['New', 'Contacted'])) {
                $db->query("UPDATE clients SET overall_status = 'In Negotiation' WHERE id = $client_id");
            }
            
            $act_desc = "Quotation {$quotation_number} (Rs. " . number_format($total_amount, 2, '.', ',') . ") drafted for {$c_name}";
            log_activity($act_desc, "search_track.php?view_client=$client_id");
            
            $quote_id = $db->lastInsertId();
            return_json(['success' => true, 'quotation_number' => $quotation_number, 'quotation_id' => $quote_id, 'message' => "Quotation {$quotation_number} saved successfully!"]);
            break;

        case 'send_quotation_email':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                return_json(['error' => 'Invalid Request Method'], 405);
            }
            
            $client_id = (int)($_POST['client_id'] ?? 0);
            $quote_id = (int)($_POST['quote_id'] ?? 0);
            $subject = trim($_POST['subject'] ?? '');
            $body = trim($_POST['body'] ?? '');
            
            if (!$client_id || !$quote_id || empty($subject) || empty($body)) {
                return_json(['error' => 'Missing required fields.'], 400);
            }
            
            $client_query = $db->prepare("SELECT company_name, email, assigned_to FROM clients WHERE id = ?");
            $client_query->execute([$client_id]);
            $client = $client_query->fetch();
            if (!$client) {
                return_json(['error' => 'Client not found.'], 404);
            }
            
            $attachment_name = null;
            if (isset($_FILES['pdf_blob']) && $_FILES['pdf_blob']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = __DIR__ . '/uploads/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                
                $filename = 'Quotation_' . $quote_id . '_' . time() . '.pdf';
                if (move_uploaded_file($_FILES['pdf_blob']['tmp_name'], $upload_dir . $filename)) {
                    $attachment_name = $filename;
                }
            }
            
            if (!$attachment_name) {
                return_json(['error' => 'Failed to receive PDF document.'], 400);
            }
            
            $sent_by = $_SESSION['username'] ?? 'System';
            
            // Send via PHPMailer
            require_once __DIR__ . '/libs/PHPMailer/Exception.php';
            require_once __DIR__ . '/libs/PHPMailer/PHPMailer.php';
            require_once __DIR__ . '/libs/PHPMailer/SMTP.php';
            
            $company_profile = $db->query("SELECT * FROM company_profile LIMIT 1")->fetch();
            
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            try {
                if (!empty($company_profile['smtp_host'])) {
                    $mail->isSMTP();
                    $mail->Host       = $company_profile['smtp_host'];
                    $mail->SMTPAuth   = true;
                    $mail->Username   = $company_profile['smtp_username'];
                    $mail->Password   = $company_profile['smtp_password'];
                    $mail->SMTPSecure = $company_profile['smtp_encryption'] === 'ssl' ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = $company_profile['smtp_port'];
                }
                
                $mail->setFrom($company_profile['email'] ?? 'admin@BFS Financial Services.com', $company_profile['company_name'] ?? 'BFS Financial Services');
                $mail->addAddress($client['email'], $client['company_name']);
                
                $mail->addAttachment($upload_dir . $attachment_name, $attachment_name);
                
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body    = $body;
                
                $mail->send();
                
                // Log communication
                $stmt = $db->prepare("INSERT INTO communications (client_id, type, subject, body, cc, attachment_name, sent_by) VALUES (?, 'Quotation', ?, ?, '', ?, ?)");
                $stmt->execute([$client_id, $subject, $body, $attachment_name, $sent_by]);
                
                $target_user = $client['assigned_to'] ?: null;
                log_activity("Quotation emailed to {$client['company_name']} by {$sent_by}", "search_track.php?view_client=$client_id", $target_user);
                
                return_json(['success' => true, 'message' => 'Quotation successfully saved and emailed!']);
            } catch (Exception $e) {
                return_json(['error' => "Quotation saved but email failed to send. Mailer Error: {$mail->ErrorInfo}"], 500);
            }
            break;

        case 'update_quotation_status':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                return_json(['error' => 'Invalid Request Method'], 405);
            }
            
            $quote_id = (int)$_POST['quote_id'];
            $status = $_POST['status'];
            
            if (!in_array($status, ['Pending', 'Approved', 'Rejected'])) {
                return_json(['error' => 'Invalid status option.'], 400);
            }
            
            $q_info = $db->query("SELECT q.quotation_number, q.client_id, c.company_name, c.assigned_to, c.added_by FROM quotations q JOIN clients c ON q.client_id = c.id WHERE q.id = $quote_id")->fetch();
            if (!$q_info) {
                return_json(['error' => 'Quotation not found.'], 404);
            }
            
            if (($_SESSION['role'] ?? '') !== 'Admin' && $q_info['assigned_to'] !== ($_SESSION['username'] ?? '') && $q_info['added_by'] !== ($_SESSION['username'] ?? '')) {
                return_json(['error' => 'Unauthorized to modify this quotation status.'], 403);
            }
            
            $stmt = $db->prepare("UPDATE quotations SET status = ? WHERE id = ?");
            $stmt->execute([$status, $quote_id]);
            
            $client_status = 'In Negotiation';
            if ($status === 'Approved') {
                $client_status = 'Closed Won';
            } elseif ($status === 'Rejected') {
                $other_approved = $db->query("SELECT COUNT(*) FROM quotations WHERE client_id = {$q_info['client_id']} AND status = 'Approved'")->fetchColumn();
                $client_status = ($other_approved > 0) ? 'Closed Won' : 'Closed Lost';
            }
            
            $db->query("UPDATE clients SET overall_status = '$client_status' WHERE id = {$q_info['client_id']}");
            
            $act_desc = "Quotation {$q_info['quotation_number']} updated to {$status} for {$q_info['company_name']}";
            log_activity($act_desc, "search_track.php?view_client={$q_info['client_id']}");
            
            return_json(['success' => true, 'message' => 'Status updated successfully', 'client_status' => $client_status]);
            break;

        case 'quotation_list':
            $search = isset($_GET['search']) ? trim($_GET['search']) : '';
            $status = isset($_GET['status']) ? trim($_GET['status']) : '';
            
            $sql = "SELECT q.*, c.company_name, c.email as client_email, c.city, c.state FROM quotations q JOIN clients c ON q.client_id = c.id WHERE 1=1";
            $params = [];
            
            if (($_SESSION['role'] ?? '') !== 'Admin') {
                $sql .= " AND (c.assigned_to = :assigned_to OR c.added_by = :assigned_to)";
                $params[':assigned_to'] = $_SESSION['username'] ?? '';
            }
            
            if ($search !== '') {
                $sql .= " AND (c.company_name LIKE :s OR q.quotation_number LIKE :s)";
                $params[':s'] = "%$search%";
            }
            if ($status !== '') {
                $sql .= " AND q.status = :status";
                $params[':status'] = $status;
            }
            
            $sql .= " ORDER BY q.id DESC";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $quotes = $stmt->fetchAll();
            
            $summary = [
                'total_count' => count($quotes),
                'pending_value' => 0,
                'approved_value' => 0,
                'rejected_value' => 0,
                'total_value' => 0
            ];
            
            foreach ($quotes as $q) {
                $amt = (float)$q['total_amount'];
                $summary['total_value'] += $amt;
                
                if ($q['status'] === 'Pending') $summary['pending_value'] += $amt;
                elseif ($q['status'] === 'Approved') $summary['approved_value'] += $amt;
                elseif ($q['status'] === 'Rejected') $summary['rejected_value'] += $amt;
            }
            
            return_json([
                'quotations' => $quotes,
                'summary' => $summary
            ]);
            break;

        case 'save_settings':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                return_json(['error' => 'Invalid Request Method'], 405);
            }
            
            // Ensure advanced columns exist
            try { $db->exec("ALTER TABLE company_profile ADD COLUMN global_tds REAL DEFAULT 5.0"); } catch (Exception $e) {}
            try { $db->exec("ALTER TABLE company_profile ADD COLUMN lead_auto_assign TEXT DEFAULT 'Round Robin'"); } catch (Exception $e) {}
            try { $db->exec("ALTER TABLE company_profile ADD COLUMN loan_products TEXT"); } catch (Exception $e) {}
            try { $db->exec("ALTER TABLE company_profile ADD COLUMN lead_sources TEXT"); } catch (Exception $e) {}
            try { $db->exec("ALTER TABLE company_profile ADD COLUMN trading_name TEXT"); } catch (Exception $e) {}
            try { $db->exec("ALTER TABLE company_profile ADD COLUMN company_tagline TEXT"); } catch (Exception $e) {}
            try { $db->exec("ALTER TABLE company_profile ADD COLUMN pan TEXT"); } catch (Exception $e) {}
            try { $db->exec("ALTER TABLE company_profile ADD COLUMN tan TEXT"); } catch (Exception $e) {}
            try { $db->exec("ALTER TABLE company_profile ADD COLUMN sac_code TEXT DEFAULT '997159'"); } catch (Exception $e) {}
            try { $db->exec("ALTER TABLE company_profile ADD COLUMN upi_vpa TEXT"); } catch (Exception $e) {}
            try { $db->exec("ALTER TABLE company_profile ADD COLUMN accounts_email TEXT"); } catch (Exception $e) {}
            try { $db->exec("ALTER TABLE company_profile ADD COLUMN bank_gst_rate REAL DEFAULT 18.0"); } catch (Exception $e) {}
            try { $db->exec("ALTER TABLE company_profile ADD COLUMN bank_tds_rate REAL DEFAULT 2.0"); } catch (Exception $e) {}
            try { $db->exec("ALTER TABLE company_profile ADD COLUMN outgoing_tds_rate REAL DEFAULT 5.0"); } catch (Exception $e) {}
            try { $db->exec("ALTER TABLE company_profile ADD COLUMN default_rm_incentive_pct REAL DEFAULT 0.20"); } catch (Exception $e) {}
            try { $db->exec("ALTER TABLE company_profile ADD COLUMN default_partner_payout_pct REAL DEFAULT 50.0"); } catch (Exception $e) {}
            try { $db->exec("ALTER TABLE company_profile ADD COLUMN sla_stalled_days INTEGER DEFAULT 3"); } catch (Exception $e) {}
            try { $db->exec("ALTER TABLE company_profile ADD COLUMN public_tracking_expiry_days INTEGER DEFAULT 30"); } catch (Exception $e) {}
            try { $db->exec("ALTER TABLE company_profile ADD COLUMN whatsapp_api_token TEXT"); } catch (Exception $e) {}
            try { $db->exec("ALTER TABLE company_profile ADD COLUMN whatsapp_phone_number_id TEXT"); } catch (Exception $e) {}
            try { $db->exec("ALTER TABLE company_profile ADD COLUMN whatsapp_business_id TEXT"); } catch (Exception $e) {}
            try { $db->exec("ALTER TABLE company_profile ADD COLUMN whatsapp_enabled INTEGER DEFAULT 0"); } catch (Exception $e) {}

            // Branding & Corporate Identity Columns
            try { $db->exec("ALTER TABLE company_profile ADD COLUMN logo_dark TEXT"); } catch (Exception $e) {}
            try { $db->exec("ALTER TABLE company_profile ADD COLUMN logo_light TEXT"); } catch (Exception $e) {}
            try { $db->exec("ALTER TABLE company_profile ADD COLUMN stamp_signature TEXT"); } catch (Exception $e) {}
            try { $db->exec("ALTER TABLE company_profile ADD COLUMN primary_color TEXT DEFAULT '#0f172a'"); } catch (Exception $e) {}
            try { $db->exec("ALTER TABLE company_profile ADD COLUMN accent_color TEXT DEFAULT '#0284c7'"); } catch (Exception $e) {}
            try { $db->exec("ALTER TABLE company_profile ADD COLUMN msme_reg_no TEXT"); } catch (Exception $e) {}
            try { $db->exec("ALTER TABLE company_profile ADD COLUMN rbi_dsa_reg_no TEXT"); } catch (Exception $e) {}
            try { $db->exec("ALTER TABLE company_profile ADD COLUMN website_url TEXT"); } catch (Exception $e) {}
            try { $db->exec("ALTER TABLE company_profile ADD COLUMN maps_url TEXT"); } catch (Exception $e) {}
            try { $db->exec("ALTER TABLE company_profile ADD COLUMN whatsapp_number TEXT"); } catch (Exception $e) {}
            try { $db->exec("ALTER TABLE company_profile ADD COLUMN linkedin_url TEXT"); } catch (Exception $e) {}
            try { $db->exec("ALTER TABLE company_profile ADD COLUMN instagram_url TEXT"); } catch (Exception $e) {}
            try { $db->exec("ALTER TABLE company_profile ADD COLUMN facebook_url TEXT"); } catch (Exception $e) {}
            try { $db->exec("ALTER TABLE company_profile ADD COLUMN bank_branch TEXT"); } catch (Exception $e) {}
            try { $db->exec("ALTER TABLE company_profile ADD COLUMN invoice_footer_note TEXT"); } catch (Exception $e) {}
            try { $db->exec("ALTER TABLE company_profile ADD COLUMN legal_disclaimer TEXT"); } catch (Exception $e) {}

            // Enterprise IT & FinTech Master Columns
            try { $db->exec("ALTER TABLE company_profile ADD COLUMN cin_number TEXT"); } catch (Exception $e) {}
            try { $db->exec("ALTER TABLE company_profile ADD COLUMN roc_jurisdiction TEXT"); } catch (Exception $e) {}
            try { $db->exec("ALTER TABLE company_profile ADD COLUMN date_of_incorporation DATE"); } catch (Exception $e) {}
            try { $db->exec("ALTER TABLE company_profile ADD COLUMN lei_code TEXT"); } catch (Exception $e) {}
            try { $db->exec("ALTER TABLE company_profile ADD COLUMN iso_certifications TEXT"); } catch (Exception $e) {}
            try { $db->exec("ALTER TABLE company_profile ADD COLUMN dpo_name TEXT"); } catch (Exception $e) {}
            try { $db->exec("ALTER TABLE company_profile ADD COLUMN dpo_email TEXT"); } catch (Exception $e) {}
            try { $db->exec("ALTER TABLE company_profile ADD COLUMN dpo_mobile TEXT"); } catch (Exception $e) {}
            try { $db->exec("ALTER TABLE company_profile ADD COLUMN favicon_path TEXT"); } catch (Exception $e) {}
            try { $db->exec("ALTER TABLE company_profile ADD COLUMN watermark_text TEXT DEFAULT 'CONFIDENTIAL'"); } catch (Exception $e) {}
            try { $db->exec("ALTER TABLE company_profile ADD COLUMN enable_dynamic_qr INTEGER DEFAULT 1"); } catch (Exception $e) {}
            try { $db->exec("ALTER TABLE company_profile ADD COLUMN base_currency TEXT DEFAULT 'INR (₹)'"); } catch (Exception $e) {}
            try { $db->exec("ALTER TABLE company_profile ADD COLUMN branches_json TEXT"); } catch (Exception $e) {}

            $company_name = trim($_POST['company_name'] ?? '');
            $trading_name = trim($_POST['trading_name'] ?? '');
            $company_tagline = trim($_POST['company_tagline'] ?? '');
            $address_line1 = trim($_POST['address_line1'] ?? '');
            $address_line2 = trim($_POST['address_line2'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $state = trim($_POST['state'] ?? '');
            $pincode = trim($_POST['pincode'] ?? '');
            $country = trim($_POST['country'] ?? 'India');
            $gstin = strtoupper(trim($_POST['gstin'] ?? ''));
            $pan = strtoupper(trim($_POST['pan'] ?? ''));
            $tan = strtoupper(trim($_POST['tan'] ?? ''));
            $sac_code = trim($_POST['sac_code'] ?? '997159');
            $email = trim($_POST['email'] ?? '');
            $accounts_email = trim($_POST['accounts_email'] ?? '');
            $mobile = trim($_POST['mobile'] ?? '');
            $contact_person = trim($_POST['contact_person'] ?? '');
            $bank_name = trim($_POST['bank_name'] ?? '');
            $account_number = trim($_POST['account_number'] ?? '');
            $ifsc_code = strtoupper(trim($_POST['ifsc_code'] ?? ''));
            $upi_vpa = trim($_POST['upi_vpa'] ?? '');
            
            $smtp_host = trim($_POST['smtp_host'] ?? '');
            $smtp_port = trim($_POST['smtp_port'] ?? '');
            $smtp_username = trim($_POST['smtp_username'] ?? '');
            $smtp_password = trim($_POST['smtp_password'] ?? '');
            $smtp_encryption = trim($_POST['smtp_encryption'] ?? '');
            
            $whatsapp_api_token = trim($_POST['whatsapp_api_token'] ?? '');
            $whatsapp_phone_number_id = trim($_POST['whatsapp_phone_number_id'] ?? '');
            $whatsapp_business_id = trim($_POST['whatsapp_business_id'] ?? '');
            $whatsapp_enabled = isset($_POST['whatsapp_enabled']) ? 1 : 0;
            
            // Branding & Regulatory Text Fields
            $primary_color = trim($_POST['primary_color'] ?? '#0f172a');
            $accent_color = trim($_POST['accent_color'] ?? '#0284c7');
            $msme_reg_no = trim($_POST['msme_reg_no'] ?? '');
            $rbi_dsa_reg_no = trim($_POST['rbi_dsa_reg_no'] ?? '');
            $website_url = trim($_POST['website_url'] ?? '');
            $maps_url = trim($_POST['maps_url'] ?? '');
            $whatsapp_number = trim($_POST['whatsapp_number'] ?? '');
            $linkedin_url = trim($_POST['linkedin_url'] ?? '');
            $instagram_url = trim($_POST['instagram_url'] ?? '');
            $facebook_url = trim($_POST['facebook_url'] ?? '');
            $bank_branch = trim($_POST['bank_branch'] ?? '');
            $invoice_footer_note = trim($_POST['invoice_footer_note'] ?? '');
            $legal_disclaimer = trim($_POST['legal_disclaimer'] ?? '');

            // Enterprise IT & FinTech Parameter Parsing
            $cin_number = strtoupper(trim($_POST['cin_number'] ?? ''));
            $roc_jurisdiction = trim($_POST['roc_jurisdiction'] ?? '');
            $date_of_incorporation = trim($_POST['date_of_incorporation'] ?? '');
            $lei_code = strtoupper(trim($_POST['lei_code'] ?? ''));
            $iso_certifications = is_array($_POST['iso_certifications'] ?? null) ? implode(', ', $_POST['iso_certifications']) : trim($_POST['iso_certifications'] ?? '');
            $dpo_name = trim($_POST['dpo_name'] ?? '');
            $dpo_email = trim($_POST['dpo_email'] ?? '');
            $dpo_mobile = trim($_POST['dpo_mobile'] ?? '');
            $watermark_text = trim($_POST['watermark_text'] ?? 'CONFIDENTIAL');
            $enable_dynamic_qr = isset($_POST['enable_dynamic_qr']) ? 1 : 0;
            $base_currency = trim($_POST['base_currency'] ?? 'INR (₹)');
            $branches_json = $_POST['branches_json'] ?? null;

            // Advanced Configs
            $global_tds = isset($_POST['global_tds']) ? (float)$_POST['global_tds'] : null;
            $bank_gst_rate = isset($_POST['bank_gst_rate']) ? (float)$_POST['bank_gst_rate'] : null;
            $bank_tds_rate = isset($_POST['bank_tds_rate']) ? (float)$_POST['bank_tds_rate'] : null;
            $outgoing_tds_rate = isset($_POST['outgoing_tds_rate']) ? (float)$_POST['outgoing_tds_rate'] : null;
            $default_rm_incentive_pct = isset($_POST['default_rm_incentive_pct']) ? (float)$_POST['default_rm_incentive_pct'] : null;
            $default_partner_payout_pct = isset($_POST['default_partner_payout_pct']) ? (float)$_POST['default_partner_payout_pct'] : null;
            $sla_stalled_days = isset($_POST['sla_stalled_days']) ? (int)$_POST['sla_stalled_days'] : null;
            $public_tracking_expiry_days = isset($_POST['public_tracking_expiry_days']) ? (int)$_POST['public_tracking_expiry_days'] : null;

            $lead_auto_assign = $_POST['lead_auto_assign'] ?? null;
            $loan_products = $_POST['loan_products'] ?? null;
            $lead_sources = $_POST['lead_sources'] ?? null;
            
            // File Uploads Handling for Branding Assets & Favicon
            $upload_dir = __DIR__ . '/uploads/branding/';
            if (!file_exists($upload_dir)) {
                @mkdir($upload_dir, 0777, true);
            }

            $allowed_exts = ['png', 'jpg', 'jpeg', 'svg', 'webp', 'ico'];

            $uploaded_files = [];
            foreach (['logo_dark', 'logo_light', 'stamp_signature', 'favicon'] as $file_field) {
                if (isset($_FILES[$file_field]) && $_FILES[$file_field]['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES[$file_field]['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, $allowed_exts)) {
                        $target_filename = ($file_field === 'favicon' ? 'favicon_path' : $file_field) . '_' . time() . '.' . $ext;
                        $target_path = $upload_dir . $target_filename;
                        if (move_uploaded_file($_FILES[$file_field]['tmp_name'], $target_path)) {
                            $col_name = ($file_field === 'favicon' ? 'favicon_path' : $file_field);
                            $uploaded_files[$col_name] = 'uploads/branding/' . $target_filename;
                        }
                    }
                }
            }

            // Skip validation of core fields if we are ONLY saving Partial Advanced Configs
            $is_partial = isset($_POST['is_partial_config']);
            
            if (!$is_partial) {
                if (empty($company_name) || empty($address_line1) || empty($city) || empty($state) || empty($pincode) || empty($gstin) || empty($email) || empty($contact_person)) {
                    return_json(['error' => 'All core business identity fields (Company Name, Address, GSTIN, Support Email, Contact Person) are required.'], 400);
                }
                
                $stmt = $db->prepare("UPDATE company_profile SET company_name = ?, trading_name = ?, company_tagline = ?, address_line1 = ?, address_line2 = ?, city = ?, state = ?, pincode = ?, country = ?, gstin = ?, pan = ?, tan = ?, sac_code = ?, email = ?, accounts_email = ?, mobile = ?, contact_person = ?, bank_name = ?, account_number = ?, ifsc_code = ?, upi_vpa = ?, smtp_host = ?, smtp_port = ?, smtp_username = ?, smtp_password = ?, smtp_encryption = ?, whatsapp_api_token = ?, whatsapp_phone_number_id = ?, whatsapp_business_id = ?, whatsapp_enabled = ?, primary_color = ?, accent_color = ?, msme_reg_no = ?, rbi_dsa_reg_no = ?, website_url = ?, maps_url = ?, whatsapp_number = ?, linkedin_url = ?, instagram_url = ?, facebook_url = ?, bank_branch = ?, invoice_footer_note = ?, legal_disclaimer = ?, cin_number = ?, roc_jurisdiction = ?, date_of_incorporation = ?, lei_code = ?, iso_certifications = ?, dpo_name = ?, dpo_email = ?, dpo_mobile = ?, watermark_text = ?, enable_dynamic_qr = ?, base_currency = ?");
                $stmt->execute([$company_name, $trading_name, $company_tagline, $address_line1, $address_line2, $city, $state, $pincode, $country, $gstin, $pan, $tan, $sac_code, $email, $accounts_email, $mobile, $contact_person, $bank_name, $account_number, $ifsc_code, $upi_vpa, $smtp_host, $smtp_port, $smtp_username, $smtp_password, $smtp_encryption, $whatsapp_api_token, $whatsapp_phone_number_id, $whatsapp_business_id, $whatsapp_enabled, $primary_color, $accent_color, $msme_reg_no, $rbi_dsa_reg_no, $website_url, $maps_url, $whatsapp_number, $linkedin_url, $instagram_url, $facebook_url, $bank_branch, $invoice_footer_note, $legal_disclaimer, $cin_number, $roc_jurisdiction, $date_of_incorporation, $lei_code, $iso_certifications, $dpo_name, $dpo_email, $dpo_mobile, $watermark_text, $enable_dynamic_qr, $base_currency]);
            }

            if ($branches_json !== null) {
                $db->prepare("UPDATE company_profile SET branches_json = ?")->execute([$branches_json]);
            }

            // Update file upload paths if uploaded
            foreach ($uploaded_files as $col => $rel_path) {
                $db->prepare("UPDATE company_profile SET {$col} = ?")->execute([$rel_path]);
            }
            
            // Save advanced configs if provided
            if ($global_tds !== null) {
                $db->prepare("UPDATE company_profile SET global_tds = ?, outgoing_tds_rate = ?")->execute([$global_tds, $global_tds]);
            }
            if ($bank_gst_rate !== null) {
                $db->prepare("UPDATE company_profile SET bank_gst_rate = ?")->execute([$bank_gst_rate]);
            }
            if ($bank_tds_rate !== null) {
                $db->prepare("UPDATE company_profile SET bank_tds_rate = ?")->execute([$bank_tds_rate]);
            }
            if ($default_rm_incentive_pct !== null) {
                $db->prepare("UPDATE company_profile SET default_rm_incentive_pct = ?")->execute([$default_rm_incentive_pct]);
            }
            if ($default_partner_payout_pct !== null) {
                $db->prepare("UPDATE company_profile SET default_partner_payout_pct = ?")->execute([$default_partner_payout_pct]);
            }
            if ($sla_stalled_days !== null) {
                $db->prepare("UPDATE company_profile SET sla_stalled_days = ?")->execute([$sla_stalled_days]);
            }
            if ($public_tracking_expiry_days !== null) {
                $db->prepare("UPDATE company_profile SET public_tracking_expiry_days = ?")->execute([$public_tracking_expiry_days]);
            }
            if ($lead_auto_assign !== null) {
                $db->prepare("UPDATE company_profile SET lead_auto_assign = ?")->execute([$lead_auto_assign]);
            }
            if ($loan_products !== null) {
                $db->prepare("UPDATE company_profile SET loan_products = ?")->execute([$loan_products]);
            }
            if ($lead_sources !== null) {
                $db->prepare("UPDATE company_profile SET lead_sources = ?")->execute([$lead_sources]);
            }
            
            $act_desc = "Master Corporate Branding & System Settings updated by " . ($_SESSION['username'] ?? 'Admin') . " — " . date('d M h:i A');
            log_activity($act_desc, "settings.php");
            
            return_json(['success' => true, 'message' => 'Master Corporate Branding & System Settings updated successfully!']);
            break;

        case 'test_smtp_email':
            if (($_SESSION['role'] ?? '') !== 'Admin') return_json(['error' => 'Admin privileges required'], 403);
            $target_email = trim($_POST['test_email'] ?? ($_SESSION['username'] ?? ''));
            if (empty($target_email)) return_json(['error' => 'Recipient email required'], 400);

            $subject = "⚡ SMTP Connection Test — BFS Financial Services";
            $body = "<h3>SMTP Dispatch Verification Successful!</h3><p>Your SMTP mail configuration is active and working properly on BFS Circle CRM.</p><p>Dispatched at: " . date('Y-m-d H:i:s') . "</p>";

            $res = sendUniversalEmail($target_email, 'Admin Test', $subject, $body, 'settings.php');
            if ($res['success']) {
                return_json(['success' => true, 'message' => "Test email successfully sent to $target_email!"]);
            } else {
                return_json(['error' => $res['error'] ?? 'SMTP dispatch failed.'], 500);
            }
            break;

        case 'get_commission_slabs':
            $stmt = $db->query("SELECT * FROM bank_commission_slabs ORDER BY min_disbursed ASC");
            $slabs = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            return_json(['success' => true, 'slabs' => $slabs]);
            break;

        case 'save_commission_slab':
            if (($_SESSION['role'] ?? '') !== 'Admin') return_json(['error' => 'Admin privileges required'], 403);
            $tier_name = trim($_POST['tier_name'] ?? '');
            $min_disbursed = (float)($_POST['min_disbursed'] ?? 0);
            $max_disbursed = (float)($_POST['max_disbursed'] ?? 0);
            $commission_pct = (float)($_POST['commission_pct'] ?? 0);

            if (empty($tier_name) || $commission_pct <= 0) {
                return_json(['error' => 'Tier name and Commission % are required'], 400);
            }

            $stmt = $db->prepare("INSERT INTO bank_commission_slabs (tier_name, min_disbursed, max_disbursed, commission_pct) VALUES (?, ?, ?, ?)");
            $stmt->execute([$tier_name, $min_disbursed, $max_disbursed, $commission_pct]);
            log_activity("Added Bank Commission Slab: $tier_name ($commission_pct%)", "settings.php");
            return_json(['success' => true, 'message' => "Slab '$tier_name' added successfully!"]);
            break;

        case 'delete_commission_slab':
            if (($_SESSION['role'] ?? '') !== 'Admin') return_json(['error' => 'Admin privileges required'], 403);
            $slab_id = (int)($_POST['id'] ?? 0);
            if ($slab_id <= 0) return_json(['error' => 'Invalid Slab ID'], 400);

            $stmt = $db->prepare("DELETE FROM bank_commission_slabs WHERE id = ?");
            $stmt->execute([$slab_id]);
            log_activity("Deleted Bank Commission Slab ID #$slab_id", "settings.php");
            return_json(['success' => true, 'message' => 'Slab deleted successfully!']);
            break;

        case 'update_user':
            if (($_SESSION['role'] ?? '') !== 'Admin') return_json(['error' => 'Admin privileges required'], 403);
            $user_id = (int)($_POST['user_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $role = trim($_POST['role'] ?? 'Staff');
            $staff_type = trim($_POST['staff_type'] ?? 'In-Office');
            $target = (float)($_POST['monthly_target'] ?? 0);
            $comm_rate = (float)($_POST['commission_rate'] ?? 0.20);
            $password = trim($_POST['password'] ?? '');

            $perms = $_POST['custom_permissions'] ?? [];
            if (is_array($perms)) {
                $perms_json = json_encode($perms);
            } else if (is_string($perms)) {
                $perms_json = $perms;
            } else {
                $perms_json = json_encode([]);
            }

            if ($user_id <= 0 || empty($name)) {
                return_json(['error' => 'User ID and Name are required'], 400);
            }

            $up_fields = ["name = ?", "role = ?", "staff_type = ?", "monthly_target = ?", "commission_rate = ?", "custom_permissions = ?"];
            $up_params = [$name, $role, $staff_type, $target, $comm_rate, $perms_json];

            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $up_fields[] = "password = ?";
                $up_params[] = $hash;
                $up_fields[] = "plain_password = ?";
                $up_params[] = $password;
            }

            $up_params[] = $user_id;
            $sql = "UPDATE users SET " . implode(', ', $up_fields) . " WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute($up_params);

            if (($user_id === ($_SESSION['user_id'] ?? 0))) {
                $_SESSION['custom_permissions_arr'] = is_array($perms) ? $perms : json_decode($perms_json, true);
            }

            log_activity("Updated User Profile: $name (ID: #$user_id)", "settings.php");
            return_json(['success' => true, 'message' => "User '$name' updated successfully!"]);
            break;

        case 'download_db_backup':
            if (($_SESSION['role'] ?? '') !== 'Admin') return_json(['error' => 'Admin privileges required'], 403);
            
            $db_path = defined('DB_FILE') ? DB_FILE : (__DIR__ . '/crm.db');
            if (!file_exists($db_path)) {
                return_json(['error' => 'Database file not found on server'], 404);
            }

            $backup_dir = __DIR__ . '/backups';
            if (!is_dir($backup_dir)) @mkdir($backup_dir, 0777, true);

            $filename = 'BFS_CRM_DB_Backup_' . date('Ymd_His') . '.sqlite';
            $backup_file = $backup_dir . '/' . $filename;

            try {
                $db->exec("VACUUM INTO " . $db->quote($backup_file));
            } catch (Exception $e) {
                copy($db_path, $backup_file);
            }

            log_activity("Admin downloaded full CRM database backup ({$filename})", "settings.php");

            header('Content-Description: File Transfer');
            header('Content-Type: application/x-sqlite3');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($backup_file));
            readfile($backup_file);
            exit;
            break;

        case 'restore_db_backup':
            if (($_SESSION['role'] ?? '') !== 'Admin') return_json(['error' => 'Admin privileges required'], 403);
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid request method'], 405);

            if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
                return_json(['error' => 'Please select a valid SQLite database backup file.'], 400);
            }

            $tmp_path = $_FILES['backup_file']['tmp_name'];
            $file_name = $_FILES['backup_file']['name'];
            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            if (!in_array($ext, ['sqlite', 'db', 'sqlite3'])) {
                return_json(['error' => 'Invalid file format! File must have .sqlite or .db extension.'], 400);
            }

            $handle = fopen($tmp_path, 'rb');
            $header = fread($handle, 16);
            fclose($handle);

            if (strpos($header, 'SQLite format 3') !== 0) {
                return_json(['error' => 'Corrupted file! The uploaded file is not a valid SQLite database.'], 400);
            }

            try {
                $test_db = new PDO("sqlite:" . $tmp_path);
                $test_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $stmt = $test_db->query("SELECT name FROM sqlite_master WHERE type='table' AND name IN ('users', 'applicants')");
                $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
                if (count($tables) < 1) {
                    return_json(['error' => 'Database structure mismatch! Required CRM tables missing in backup file.'], 400);
                }
                $test_db = null;
            } catch (Exception $e) {
                return_json(['error' => 'Unable to read database backup file: ' . $e->getMessage()], 400);
            }

            $active_db_path = defined('DB_FILE') ? DB_FILE : (__DIR__ . '/crm.db');

            $backup_dir = __DIR__ . '/backups';
            if (!is_dir($backup_dir)) @mkdir($backup_dir, 0777, true);
            $safety_filename = 'crm_auto_safety_before_restore_' . date('Ymd_His') . '.sqlite';
            $safety_path = $backup_dir . '/' . $safety_filename;

            try {
                $db->exec("VACUUM INTO " . $db->quote($safety_path));
            } catch (Exception $e) {
                @copy($active_db_path, $safety_path);
            }

            $db = null;

            if (!copy($tmp_path, $active_db_path)) {
                return_json(['error' => 'Failed to overwrite database file. Check server write permissions.'], 500);
            }

            try {
                $db = new PDO("sqlite:" . $active_db_path);
                $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                log_activity("Admin restored database from backup file ({$file_name}). Safety backup saved as {$safety_filename}", "settings.php");
            } catch (Exception $e) {}

            return_json([
                'success' => true,
                'message' => "Database successfully restored from '{$file_name}'! Emergency snapshot saved as '{$safety_filename}'. All system data refreshed.",
                'safety_backup' => $safety_filename
            ]);
            break;

        case 'merge_db_backup':
            if (($_SESSION['role'] ?? '') !== 'Admin') return_json(['error' => 'Admin privileges required'], 403);
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid request method'], 405);

            if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
                return_json(['error' => 'Please select a valid SQLite database backup file.'], 400);
            }

            $tmp_path = $_FILES['backup_file']['tmp_name'];
            $file_name = $_FILES['backup_file']['name'];
            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            if (!in_array($ext, ['sqlite', 'db', 'sqlite3'])) {
                return_json(['error' => 'Invalid file format! File must have .sqlite or .db extension.'], 400);
            }

            $handle = fopen($tmp_path, 'rb');
            $header = fread($handle, 16);
            fclose($handle);

            if (strpos($header, 'SQLite format 3') !== 0) {
                return_json(['error' => 'Corrupted file! The uploaded file is not a valid SQLite database.'], 400);
            }

            try {
                $import_db = new PDO("sqlite:" . $tmp_path);
                $import_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $import_db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                return_json(['error' => 'Unable to read import database file: ' . $e->getMessage()], 400);
            }

            $active_db_path = defined('DB_FILE') ? DB_FILE : (__DIR__ . '/crm.db');
            $backup_dir = __DIR__ . '/backups';
            if (!is_dir($backup_dir)) @mkdir($backup_dir, 0777, true);
            $safety_filename = 'crm_auto_safety_before_merge_' . date('Ymd_His') . '.sqlite';
            $safety_path = $backup_dir . '/' . $safety_filename;
            try { $db->exec("VACUUM INTO " . $db->quote($safety_path)); } catch (Exception $e) { @copy($active_db_path, $safety_path); }

            $added_users = 0; $skipped_users = 0;
            $added_applicants = 0; $skipped_applicants = 0;
            $added_docs = 0; $skipped_docs = 0;

            // 1. Merge Users (De-duplicate by username)
            try {
                $u_rows = $import_db->query("SELECT * FROM users")->fetchAll();
                $chk_u = $db->prepare("SELECT id FROM users WHERE username = ?");
                $ins_u = $db->prepare("INSERT INTO users (username, name, password_hash, role) VALUES (?, ?, ?, ?)");

                foreach ($u_rows as $u) {
                    if (empty($u['username'])) continue;
                    $chk_u->execute([$u['username']]);
                    if ($chk_u->fetchColumn()) {
                        $skipped_users++;
                    } else {
                        $ins_u->execute([$u['username'], $u['name'] ?? 'User', $u['password_hash'] ?? '', $u['role'] ?? 'Staff']);
                        $added_users++;
                    }
                }
            } catch (Exception $e) {}

            // 2. Merge Applicants (De-duplicate by mobile, pan_number, loan_id)
            $applicant_id_map = [];
            try {
                $app_rows = $import_db->query("SELECT * FROM applicants")->fetchAll();
                $chk_app = $db->prepare("SELECT id FROM applicants WHERE (mobile = ? AND mobile != '') OR (loan_id = ? AND loan_id != '') OR (pan_number = ? AND pan_number != '')");
                
                $col_stmt = $db->query("PRAGMA table_info(applicants)");
                $target_cols = array_column($col_stmt->fetchAll(), 'name');
                $target_cols = array_diff($target_cols, ['id', 'created_at']);

                foreach ($app_rows as $app) {
                    $mob = trim($app['mobile'] ?? '');
                    $lid = trim($app['loan_id'] ?? '');
                    $pan = trim($app['pan_number'] ?? '');

                    $existing_id = null;
                    if ($mob !== '' || $lid !== '' || $pan !== '') {
                        $chk_app->execute([$mob, $lid, $pan]);
                        $existing_id = $chk_app->fetchColumn();
                    }

                    if ($existing_id) {
                        $skipped_applicants++;
                        $applicant_id_map[$app['id']] = $existing_id;
                    } else {
                        $insert_data = [];
                        foreach ($target_cols as $c) {
                            if (array_key_exists($c, $app)) {
                                $insert_data[$c] = $app[$c];
                            }
                        }

                        if (!empty($insert_data)) {
                            $fields = array_keys($insert_data);
                            $placeholders = implode(',', array_fill(0, count($fields), '?'));
                            $sql = "INSERT INTO applicants (" . implode(',', $fields) . ") VALUES (" . $placeholders . ")";
                            $ins_stmt = $db->prepare($sql);
                            $ins_stmt->execute(array_values($insert_data));
                            $new_id = $db->lastInsertId();
                            $applicant_id_map[$app['id']] = $new_id;
                            $added_applicants++;
                        }
                    }
                }
            } catch (Exception $e) {}

            // 3. Merge Documents (De-duplicate by applicant_id & document_name)
            try {
                $doc_rows = $import_db->query("SELECT * FROM applicant_documents")->fetchAll();
                $chk_doc = $db->prepare("SELECT id FROM applicant_documents WHERE applicant_id = ? AND document_name = ?");
                $ins_doc = $db->prepare("INSERT INTO applicant_documents (applicant_id, client_mobile, client_pan, document_category, document_name, file_path, file_size, file_ext, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                foreach ($doc_rows as $d) {
                    $old_app_id = $d['applicant_id'] ?? 0;
                    $target_app_id = $applicant_id_map[$old_app_id] ?? 0;
                    if (!$target_app_id) continue;

                    $chk_doc->execute([$target_app_id, $d['document_name'] ?? '']);
                    if ($chk_doc->fetchColumn()) {
                        $skipped_docs++;
                    } else {
                        $ins_doc->execute([
                            $target_app_id,
                            $d['client_mobile'] ?? '',
                            $d['client_pan'] ?? '',
                            $d['document_category'] ?? 'KYC',
                            $d['document_name'] ?? 'Document',
                            $d['file_path'] ?? '',
                            $d['file_size'] ?? 0,
                            $d['file_ext'] ?? '',
                            $d['status'] ?? 'Verified',
                            $d['notes'] ?? ''
                        ]);
                        $added_docs++;
                    }
                }
            } catch (Exception $e) {}

            log_activity("Smart Merged DB backup '{$file_name}': Added {$added_applicants} applicants, {$added_users} users, {$added_docs} docs. Skipped {$skipped_applicants} duplicate applicants.", "settings.php");

            return_json([
                'success' => true,
                'message' => "Smart De-duplicated Import Complete! Added {$added_applicants} new applicants, {$added_users} users, and {$added_docs} documents. Skipped {$skipped_applicants} duplicate applicants to protect your data.",
                'summary' => [
                    'added_applicants' => $added_applicants,
                    'skipped_applicants' => $skipped_applicants,
                    'added_users' => $added_users,
                    'skipped_users' => $skipped_users,
                    'added_docs' => $added_docs,
                    'skipped_docs' => $skipped_docs
                ]
            ]);
            break;

        case 'export_sql_dump':
            if (($_SESSION['role'] ?? '') !== 'Admin') return_json(['error' => 'Admin privileges required'], 403);
            @ini_set('memory_limit', '-1');
            @ini_set('max_execution_time', '300');
            $sql_content = generateSqlDump($db);
            $filename = 'BFS_CRM_SQL_Dump_' . date('Ymd_His') . '.sql';
            
            log_activity("Admin exported full CRM SQL dump ({$filename})", "settings.php");

            header('Content-Description: File Transfer');
            header('Content-Type: application/sql');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . strlen($sql_content));
            echo $sql_content;
            exit;
            break;

        case 'export_json_backup':
            if (($_SESSION['role'] ?? '') !== 'Admin') return_json(['error' => 'Admin privileges required'], 403);
            @ini_set('memory_limit', '-1');
            @ini_set('max_execution_time', '300');
            $json_content = exportDatabaseToJson($db);
            $filename = 'BFS_CRM_JSON_Backup_' . date('Ymd_His') . '.json';
            
            log_activity("Admin exported JSON database bundle ({$filename})", "settings.php");

            header('Content-Description: File Transfer');
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . strlen($json_content));
            echo $json_content;
            exit;
            break;

        case 'export_module_csv':
            if (($_SESSION['role'] ?? '') !== 'Admin') return_json(['error' => 'Admin privileges required'], 403);
            @ini_set('memory_limit', '-1');
            @ini_set('max_execution_time', '300');
            $module = trim($_GET['module'] ?? 'applicants');
            $allowed_tables = ['applicants', 'bank_queries', 'payout_distributions', 'users', 'activity_logs'];

            if (!in_array($module, $allowed_tables)) $module = 'applicants';

            $filename = 'BFS_CRM_' . ucfirst($module) . '_' . date('Ymd_His') . '.csv';

            log_activity("Admin exported {$module} table to CSV ({$filename})", "settings.php");

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            
            $output = fopen('php://output', 'w');
            $stmt = $db->query("SELECT * FROM " . $db->quote($module));
            $header_written = false;
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (!$header_written) {
                    fputcsv($output, array_keys($row));
                    $header_written = true;
                }
                fputcsv($output, $row);
            }
            fclose($output);
            exit;
            break;

        case 'preview_import_file':
            if (($_SESSION['role'] ?? '') !== 'Admin') return_json(['error' => 'Admin privileges required'], 403);
            if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
                return_json(['error' => 'Please select a valid file to analyze.'], 400);
            }

            $tmp_path = $_FILES['backup_file']['tmp_name'];
            $file_name = $_FILES['backup_file']['name'];
            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            $total_applicants = 0; $new_applicants = 0; $duplicate_applicants = 0;
            $total_users = 0; $new_users = 0; $duplicate_users = 0;

            if (in_array($ext, ['sqlite', 'db', 'sqlite3'])) {
                try {
                    $import_db = new PDO("sqlite:" . $tmp_path);
                    $import_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    try {
                        $apps = $import_db->query("SELECT mobile, loan_id, pan_number FROM applicants")->fetchAll(PDO::FETCH_ASSOC);
                        $total_applicants = count($apps);
                        $chk_app = $db->prepare("SELECT id FROM applicants WHERE (mobile = ? AND mobile != '') OR (loan_id = ? AND loan_id != '') OR (pan_number = ? AND pan_number != '')");

                        foreach ($apps as $a) {
                            $chk_app->execute([$a['mobile'] ?? '', $a['loan_id'] ?? '', $a['pan_number'] ?? '']);
                            if ($chk_app->fetchColumn()) {
                                $duplicate_applicants++;
                            } else {
                                $new_applicants++;
                            }
                        }
                    } catch (Exception $e) {}

                    try {
                        $users = $import_db->query("SELECT username FROM users")->fetchAll(PDO::FETCH_ASSOC);
                        $total_users = count($users);
                        $chk_u = $db->prepare("SELECT id FROM users WHERE username = ?");

                        foreach ($users as $u) {
                            if (empty($u['username'])) continue;
                            $chk_u->execute([$u['username']]);
                            if ($chk_u->fetchColumn()) {
                                $duplicate_users++;
                            } else {
                                $new_users++;
                            }
                        }
                    } catch (Exception $e) {}

                } catch (Exception $e) {
                    return_json(['error' => 'Failed to read SQLite file: ' . $e->getMessage()], 400);
                }
            } else {
                return_json(['error' => 'Dry-run preview currently supports .sqlite and .db files.'], 400);
            }

            return_json([
                'success' => true,
                'filename' => $file_name,
                'analysis' => [
                    'total_applicants' => $total_applicants,
                    'new_applicants' => $new_applicants,
                    'duplicate_applicants' => $duplicate_applicants,
                    'total_users' => $total_users,
                    'new_users' => $new_users,
                    'duplicate_users' => $duplicate_users
                ]
            ]);
            break;

        case 'cleanup_old_backups':
            if (($_SESSION['role'] ?? '') !== 'Admin') return_json(['error' => 'Admin privileges required'], 403);
            $backup_dir = __DIR__ . '/backups';
            $purged_count = 0;

            if (is_dir($backup_dir)) {
                $files = glob($backup_dir . '/*.sqlite');
                if (count($files) > 15) {
                    usort($files, function($a, $b) { return filemtime($b) - filemtime($a); });
                    $to_delete = array_slice($files, 15);
                    foreach ($to_delete as $f) {
                        if (@unlink($f)) $purged_count++;
                    }
                }
            }

            log_activity("Admin purged {$purged_count} old database backup snapshots", "settings.php");
            return_json(['success' => true, 'message' => "Cleaned up {$purged_count} old backup files! Latest 15 backups retained."]);
            break;

        case 'restore_local_backup_file':
            if (($_SESSION['role'] ?? '') !== 'Admin') return_json(['error' => 'Admin privileges required'], 403);
            $filename = basename(trim($_POST['filename'] ?? ''));
            if (empty($filename)) return_json(['error' => 'Filename required'], 400);

            $target_file = __DIR__ . '/backups/' . $filename;
            if (!file_exists($target_file)) return_json(['error' => 'Backup file not found on server'], 404);

            $active_db_path = defined('DB_FILE') ? DB_FILE : (__DIR__ . '/crm.db');

            $backup_dir = __DIR__ . '/backups';
            $safety_filename = 'crm_auto_safety_before_restore_' . date('Ymd_His') . '.sqlite';
            $safety_path = $backup_dir . '/' . $safety_filename;

            try {
                $db->exec("VACUUM INTO " . $db->quote($safety_path));
            } catch (Exception $e) {
                @copy($active_db_path, $safety_path);
            }

            $db = null;
            if (!copy($target_file, $active_db_path)) {
                return_json(['error' => 'Failed to restore database file.'], 500);
            }

            try {
                $db = new PDO("sqlite:" . $active_db_path);
                $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                log_activity("Admin restored database from stored backup ({$filename})", "settings.php");
            } catch(Exception $e) {}

            return_json(['success' => true, 'message' => "Database restored from stored backup '{$filename}' successfully!"]);
            break;

        case 'download_stored_backup':
            if (($_SESSION['role'] ?? '') !== 'Admin') return_json(['error' => 'Admin privileges required'], 403);
            $filename = basename(trim($_GET['filename'] ?? ''));
            $target_file = __DIR__ . '/backups/' . $filename;

            if (empty($filename) || !file_exists($target_file)) {
                return_json(['error' => 'Backup file not found'], 404);
            }

            header('Content-Description: File Transfer');
            header('Content-Type: application/x-sqlite3');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($target_file));
            readfile($target_file);
            exit;
            break;

        case 'get_system_health':
            $active_db_path = defined('DB_FILE') ? DB_FILE : (__DIR__ . '/crm.db');
            
            $db_size_bytes = file_exists($active_db_path) ? filesize($active_db_path) : 0;
            if ($db_size_bytes >= 1024 * 1024) {
                $db_size_formatted = number_format($db_size_bytes / (1024 * 1024), 2) . ' MB';
            } else {
                $db_size_formatted = number_format($db_size_bytes / 1024, 1) . ' KB';
            }

            $uploads_dir = __DIR__ . '/uploads';
            $uploads_size = 0;
            $uploads_count = 0;

            if (is_dir($uploads_dir)) {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($uploads_dir, RecursiveDirectoryIterator::SKIP_DOTS)
                );
                foreach ($iterator as $file) {
                    if ($file->isFile()) {
                        $uploads_size += $file->getSize();
                        $uploads_count++;
                    }
                }
            }

            $uploads_size_mb = number_format($uploads_size / (1024 * 1024), 2);

            $applicants_count = 0;
            try {
                $applicants_count = (int)$db->query("SELECT COUNT(*) FROM applicants")->fetchColumn();
            } catch(Exception $e) {}

            $logs_count = 0;
            try {
                $logs_count = (int)$db->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn();
            } catch(Exception $e) {}

            $backup_dir = __DIR__ . '/backups';
            $backups_list = [];
            if (is_dir($backup_dir)) {
                $files = glob($backup_dir . '/*.sqlite');
                if (is_array($files)) {
                    usort($files, function($a, $b) { return filemtime($b) - filemtime($a); });
                    foreach (array_slice($files, 0, 10) as $f) {
                        $backups_list[] = [
                            'filename' => basename($f),
                            'size' => number_format(filesize($f) / (1024 * 1024), 2) . ' MB',
                            'date' => date('d M Y, h:i A', filemtime($f))
                        ];
                    }
                }
            }

            return_json([
                'success' => true,
                'health' => [
                    'db_size_mb' => $db_size_formatted,
                    'uploads_size_mb' => $uploads_size_mb,
                    'uploads_count' => $uploads_count,
                    'total_applicants' => $applicants_count,
                    'total_logs' => $logs_count,
                    'backups' => $backups_list
                ]
            ]);
            break;

        case 'get_doc_compliance_matrix':
            $stmt = $db->query("SELECT * FROM document_compliance_matrix ORDER BY loan_type ASC, id ASC");
            $items = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            return_json(['success' => true, 'matrix' => $items]);
            break;

        case 'save_doc_compliance_item':
            if (($_SESSION['role'] ?? '') !== 'Admin') return_json(['error' => 'Admin privileges required'], 403);
            $loan_type = trim($_POST['loan_type'] ?? '');
            $employment_type = trim($_POST['employment_type'] ?? 'Salaried');
            $doc_category = trim($_POST['doc_category'] ?? 'Basic KYC');
            $doc_name = trim($_POST['doc_name'] ?? '');
            $is_mandatory = isset($_POST['is_mandatory']) ? 1 : 0;

            if (empty($loan_type) || empty($doc_name)) {
                return_json(['error' => 'Loan type and Document name are required'], 400);
            }

            $stmt = $db->prepare("INSERT INTO document_compliance_matrix (loan_type, employment_type, doc_category, doc_name, is_mandatory) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$loan_type, $employment_type, $doc_category, $doc_name, $is_mandatory]);
            log_activity("Added Required Document '$doc_name' for $loan_type", "settings.php");
            return_json(['success' => true, 'message' => "Document '$doc_name' added to $loan_type matrix!"]);
            break;

        case 'delete_doc_compliance_item':
            if (($_SESSION['role'] ?? '') !== 'Admin') return_json(['error' => 'Admin privileges required'], 403);
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) return_json(['error' => 'Invalid ID'], 400);

            $stmt = $db->prepare("DELETE FROM document_compliance_matrix WHERE id = ?");
            $stmt->execute([$id]);
            log_activity("Deleted Document Compliance Item ID #$id", "settings.php");
            return_json(['success' => true, 'message' => 'Document item removed successfully!']);
            break;

        case 'get_system_health':
            $db_file = __DIR__ . '/aura_crm.sqlite';
            if (!file_exists($db_file)) $db_file = __DIR__ . '/crm.db';
            $db_size_mb = file_exists($db_file) ? round(filesize($db_file) / (1024 * 1024), 2) : 0;

            $uploads_dir = __DIR__ . '/uploads';
            $uploads_size_mb = 0;
            $uploads_count = 0;
            if (is_dir($uploads_dir)) {
                $files = scandir($uploads_dir);
                foreach ($files as $f) {
                    if ($f !== '.' && $f !== '..') {
                        $uploads_count++;
                        $uploads_size_mb += filesize($uploads_dir . '/' . $f);
                    }
                }
                $uploads_size_mb = round($uploads_size_mb / (1024 * 1024), 2);
            }

            $total_applicants = (int)$db->query("SELECT COUNT(*) FROM applicants")->fetchColumn();
            $total_users = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
            $total_logs = (int)$db->query("SELECT COUNT(*) FROM activities")->fetchColumn();

            return_json([
                'success' => true,
                'health' => [
                    'db_size_mb' => $db_size_mb,
                    'uploads_count' => $uploads_count,
                    'uploads_size_mb' => $uploads_size_mb,
                    'total_applicants' => $total_applicants,
                    'total_users' => $total_users,
                    'total_logs' => $total_logs,
                    'php_version' => PHP_VERSION,
                    'server_time' => date('d M Y, h:i:s A')
                ]
            ]);
            break;

        case 'client_upload_document':
            $token = preg_replace('/[^a-f0-9]/i', '', $_POST['token'] ?? '');
            if (strlen($token) !== 32) return_json(['error' => 'Invalid tracking token'], 400);

            $stmt = $db->prepare("SELECT id, customer_name, assigned_to FROM applicants WHERE tracking_token = ? AND datetime(tracking_token_created, '+30 days') > datetime('now')");
            $stmt->execute([$token]);
            $applicant = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$applicant) return_json(['error' => 'Tracking link expired or invalid'], 404);

            $document_name = trim($_POST['document_name'] ?? 'Client Upload');
            $category = trim($_POST['category'] ?? 'KYC');
            $owner_type = trim($_POST['owner_type'] ?? 'Applicant');
            $owner_id = (int)($_POST['owner_id'] ?? 0);

            if (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
                return_json(['error' => 'Please select a valid document file (PDF, JPG, PNG) to upload.'], 400);
            }

            $file = $_FILES['document_file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed_exts = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'zip'];

            if (!in_array($ext, $allowed_exts)) {
                return_json(['error' => 'Invalid file type. Allowed: PDF, JPG, PNG, DOC, DOCX, ZIP'], 400);
            }

            $new_filename = 'client_' . $applicant['id'] . '_' . time() . '_' . rand(100,999) . '.' . $ext;
            $target_path = __DIR__ . '/uploads/' . $new_filename;

            if (!move_uploaded_file($file['tmp_name'], $target_path)) {
                return_json(['error' => 'Failed to save file on server'], 500);
            }

            // Check if there is an existing 'Pending' document request for this applicant
            $chk_pending = $db->prepare("SELECT id FROM applicant_documents WHERE applicant_id = ? AND document_name = ? AND status = 'Pending'");
            $chk_pending->execute([$applicant['id'], $document_name]);
            $pending_id = $chk_pending->fetchColumn();

            if ($pending_id) {
                $upd_stmt = $db->prepare("UPDATE applicant_documents SET file_path = ?, status = 'Uploaded', qa_status = 'Under Review', reupload_requested = 0, rejection_reason = NULL, owner_type = ?, owner_id = ?, verified_by = 'Client Upload', uploaded_at = CURRENT_TIMESTAMP WHERE id = ?");
                $upd_stmt->execute([$new_filename, $owner_type, $owner_id, $pending_id]);
            } else {
                $doc_stmt = $db->prepare("INSERT INTO applicant_documents (applicant_id, document_name, category, owner_type, owner_id, file_path, status, qa_status, reupload_requested, verified_by, uploaded_at) VALUES (?, ?, ?, ?, ?, ?, 'Uploaded', 'Under Review', 0, 'Client Upload', CURRENT_TIMESTAMP)");
                $doc_stmt->execute([$applicant['id'], $document_name, $category, $owner_type, $owner_id, $new_filename]);
            }

            // Update applicant last activity
            $db->prepare("UPDATE applicants SET last_activity_date = CURRENT_TIMESTAMP WHERE id = ?")->execute([$applicant['id']]);

            // Log activity
            $act_desc = "📁 [Client Upload] Customer " . $applicant['customer_name'] . " uploaded document: " . $document_name;
            log_activity($act_desc, "search_track.php?id=" . $applicant['id'], $applicant['assigned_to']);

            return_json([
                'success' => true, 
                'doc_name' => $document_name,
                'message' => "Thank you! Your document '$document_name' has been received successfully."
            ]);
            break;

        case 'update_doc_qa_status':
            $doc_id = (int)($_POST['doc_id'] ?? 0);
            $action_status = trim($_POST['status'] ?? ''); // 'Approved' or 'Rejected'
            $reason = trim($_POST['reason'] ?? '');

            if (!$doc_id || !in_array($action_status, ['Approved', 'Rejected'])) {
                return_json(['error' => 'Invalid document ID or status.'], 400);
            }

            $chk = $db->prepare("SELECT d.*, a.customer_name, a.loan_id FROM applicant_documents d JOIN applicants a ON d.applicant_id = a.id WHERE d.id = ?");
            $chk->execute([$doc_id]);
            $doc = $chk->fetch(PDO::FETCH_ASSOC);

            if (!$doc) return_json(['error' => 'Document not found.'], 404);

            if ($action_status === 'Approved') {
                $upd = $db->prepare("UPDATE applicant_documents SET qa_status = 'Approved', status = 'Verified', reupload_requested = 0, rejection_reason = NULL WHERE id = ?");
                $upd->execute([$doc_id]);
                log_activity("Approved document '{$doc['document_name']}' for {$doc['customer_name']} ({$doc['loan_id']})", "applicant_documents.php?id={$doc['applicant_id']}");
                return_json(['success' => true, 'message' => "Document '{$doc['document_name']}' has been Approved & Verified!"]);
            } else { // Rejected / Re-upload Requested
                if (empty($reason)) {
                    return_json(['error' => 'Please provide a reason / instruction for re-upload request.'], 400);
                }
                $upd = $db->prepare("UPDATE applicant_documents SET qa_status = 'Rejected', status = 'Pending', reupload_requested = 1, rejection_reason = ? WHERE id = ?");
                $upd->execute([$reason, $doc_id]);
                log_activity("Requested Re-upload for document '{$doc['document_name']}' of {$doc['customer_name']} ({$doc['loan_id']}). Reason: {$reason}", "applicant_documents.php?id={$doc['applicant_id']}");
                return_json(['success' => true, 'message' => "Re-upload requested for '{$doc['document_name']}'. Client portal unlocked with instructions!"]);
            }
            break;

        case 'request_client_document':
            $applicant_id = intval($_POST['applicant_id'] ?? 0);
            $doc_name = trim($_POST['document_name'] ?? '');
            $notes = trim($_POST['notes'] ?? '');

            if ($applicant_id <= 0 || empty($doc_name)) {
                return_json(['error' => 'Applicant ID and Document Name are required'], 400);
            }

            $app_stmt = $db->prepare("SELECT customer_name, loan_id, mobile, assigned_to FROM applicants WHERE id = ?");
            $app_stmt->execute([$applicant_id]);
            $app = $app_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$app) return_json(['error' => 'Applicant not found'], 404);

            $ins_stmt = $db->prepare("INSERT INTO applicant_documents (applicant_id, document_name, category, status, notes, uploaded_at) VALUES (?, ?, 'KYC', 'Pending', ?, CURRENT_TIMESTAMP)");
            $ins_stmt->execute([$applicant_id, $doc_name, $notes]);

            // Update last activity date
            $db->prepare("UPDATE applicants SET last_activity_date = CURRENT_TIMESTAMP WHERE id = ?")->execute([$applicant_id]);

            log_activity("Requested document '$doc_name' from {$app['customer_name']} ({$app['loan_id']})", "settings.php");

            return_json([
                'success' => true,
                'message' => "Document '$doc_name' requested from {$app['customer_name']}!",
                'doc_name' => $doc_name
            ]);
            break;

        case 'client_submit_query':
            $token = preg_replace('/[^a-f0-9]/i', '', $_POST['token'] ?? '');
            $subject = trim($_POST['query_subject'] ?? 'General Query');
            $message = trim($_POST['query_message'] ?? '');

            if (strlen($token) !== 32) return_json(['error' => 'Invalid security token'], 403);
            if (empty($message)) return_json(['error' => 'Query description cannot be empty'], 400);

            $stmt = $db->prepare("SELECT id, customer_name, loan_id, assigned_to FROM applicants WHERE tracking_token = ? AND datetime(tracking_token_created, '+30 days') > datetime('now')");
            $stmt->execute([$token]);
            $app = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$app) return_json(['error' => 'Invalid or expired tracking link'], 403);

            $qStmt = $db->prepare("INSERT INTO client_queries (applicant_id, query_subject, query_message, status, created_at) VALUES (?, ?, ?, 'Open', CURRENT_TIMESTAMP)");
            $qStmt->execute([$app['id'], $subject, $message]);

            // Update applicant last activity
            $db->prepare("UPDATE applicants SET last_activity_date = CURRENT_TIMESTAMP WHERE id = ?")->execute([$app['id']]);

            // Log activity for RM / Admin
            $act_desc = "❓ [Client Query] " . $app['customer_name'] . " submitted query: " . $subject;
            log_activity($act_desc, "search_track.php?id=" . $app['id'], $app['assigned_to']);

            return_json([
                'success' => true,
                'message' => 'Your query has been submitted! Your Relationship Manager will respond shortly.'
            ]);
        case 'client_refer_friend':
            $token = preg_replace('/[^a-f0-9]/i', '', $_POST['token'] ?? '');
            if (strlen($token) !== 32) return_json(['error' => 'Invalid security token'], 403);

            $stmt = $db->prepare("SELECT id, customer_name, mobile, loan_id, assigned_to FROM applicants WHERE tracking_token = ? AND datetime(tracking_token_created, '+30 days') > datetime('now')");
            $stmt->execute([$token]);
            $app = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$app) return_json(['error' => 'Invalid or expired tracking link'], 403);

            $friend_name = trim($_POST['friend_name'] ?? '');
            $friend_mobile = trim($_POST['friend_mobile'] ?? '');
            $loan_type = trim($_POST['loan_type'] ?? 'Home Loan');
            $loan_amount = floatval($_POST['loan_amount'] ?? 0);

            if (empty($friend_name) || empty($friend_mobile)) {
                return_json(['error' => 'Friend name and mobile number are required.'], 400);
            }

            if (!preg_match('/^[0-9]{10}$/', $friend_mobile)) {
                return_json(['error' => 'Please enter a valid 10-digit mobile number.'], 400);
            }

            // Create lead in CRM
            $notes = "Referred directly by Existing Customer: {$app['customer_name']} (Mobile: {$app['mobile']}, Loan ID: {$app['loan_id']})";
            $l_stmt = $db->prepare("INSERT INTO leads (lead_name, mobile, requirement, loan_amount, added_by, lead_source, stage, notes) VALUES (?, ?, ?, ?, ?, 'Customer Referral', 'New Lead', ?)");
            $l_stmt->execute([$friend_name, $friend_mobile, $loan_type, $loan_amount, $app['customer_name'], $notes]);
            $new_lead_id = $db->lastInsertId();

            // Insert into customer_referrals table
            $cr_stmt = $db->prepare("INSERT INTO customer_referrals (referrer_applicant_id, referrer_name, referrer_mobile, referred_lead_id, friend_name, friend_mobile, loan_requirement, status, reward_amount) VALUES (?, ?, ?, ?, ?, ?, ?, 'Lead Received', 2000.0)");
            $cr_stmt->execute([$app['id'], $app['customer_name'], $app['mobile'], $new_lead_id, $friend_name, $friend_mobile, $loan_type]);

            log_activity("🎁 [Customer Referral] {$app['customer_name']} referred friend {$friend_name} ({$friend_mobile}) for {$loan_type}", "leads.php", $app['assigned_to']);

            return_json([
                'success' => true,
                'message' => 'Thank you! Your friend has been successfully referred. You will receive ₹2,000 reward upon loan disbursal!',
                'referral' => [
                    'friend_name' => $friend_name,
                    'loan_requirement' => $loan_type,
                    'status' => 'Lead Received',
                    'reward_amount' => 2000,
                    'reward_status' => 'Pending',
                    'created_at' => date('Y-m-d H:i:s')
                ]
            ]);
            break;

        case 'client_request_callback':
            $token = preg_replace('/[^a-f0-9]/i', '', $_POST['token'] ?? '');
            $preferred_time = trim($_POST['preferred_time'] ?? 'As soon as possible');
            $notes = trim($_POST['notes'] ?? '');

            if (strlen($token) !== 32) return_json(['error' => 'Invalid security token'], 403);

            $stmt = $db->prepare("SELECT id, customer_name, loan_id, assigned_to FROM applicants WHERE tracking_token = ? AND datetime(tracking_token_created, '+30 days') > datetime('now')");
            $stmt->execute([$token]);
            $app = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$app) return_json(['error' => 'Invalid or expired tracking link'], 403);

            $cStmt = $db->prepare("INSERT INTO client_callbacks (applicant_id, preferred_time, notes, status, created_at) VALUES (?, ?, ?, 'Pending', CURRENT_TIMESTAMP)");
            $cStmt->execute([$app['id'], $preferred_time, $notes]);

            $db->prepare("UPDATE applicants SET last_activity_date = CURRENT_TIMESTAMP WHERE id = ?")->execute([$app['id']]);

            log_activity("📞 [Callback Request] Client {$app['customer_name']} requested RM callback ({$preferred_time})", "search_track.php?id=" . $app['id'], $app['assigned_to']);

            return_json([
                'success' => true,
                'message' => 'Callback request submitted! Your Relationship Manager will call you shortly.'
            ]);
            break;

        case 'client_confirm_verification':
            $token = preg_replace('/[^a-f0-9]/i', '', $_POST['token'] ?? '');
            $schedule_id = intval($_POST['schedule_id'] ?? 0);
            $confirmation = trim($_POST['confirmation'] ?? 'Confirmed'); // Confirmed / Reschedule Requested

            if (strlen($token) !== 32 || $schedule_id <= 0) return_json(['error' => 'Invalid request data'], 400);

            $stmt = $db->prepare("SELECT id, customer_name, loan_id, assigned_to FROM applicants WHERE tracking_token = ? AND datetime(tracking_token_created, '+30 days') > datetime('now')");
            $stmt->execute([$token]);
            $app = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$app) return_json(['error' => 'Invalid or expired tracking link'], 403);

            $uStmt = $db->prepare("UPDATE verification_schedules SET client_confirmation = ? WHERE id = ? AND applicant_id = ?");
            $uStmt->execute([$confirmation, $schedule_id, $app['id']]);

            log_activity("🏠 [FI Visit] Client {$app['customer_name']} set verification status: {$confirmation}", "search_track.php?id=" . $app['id'], $app['assigned_to']);

            return_json([
                'success' => true,
                'message' => "Verification visit updated to '$confirmation'."
            ]);
            break;

        case 'client_accept_sanction':
            $token = preg_replace('/[^a-f0-9]/i', '', $_POST['token'] ?? '');
            if (strlen($token) !== 32) return_json(['error' => 'Invalid security token'], 403);

            $stmt = $db->prepare("SELECT id, customer_name, loan_id, assigned_to, sanction_bank, sanctioned_amount FROM applicants WHERE tracking_token = ? AND datetime(tracking_token_created, '+30 days') > datetime('now')");
            $stmt->execute([$token]);
            $app = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$app) return_json(['error' => 'Invalid or expired tracking link'], 403);

            $accStmt = $db->prepare("UPDATE applicants SET sanction_accepted = 1, sanction_accepted_at = CURRENT_TIMESTAMP WHERE id = ?");
            $accStmt->execute([$app['id']]);

            log_activity("👍 [Sanction Accepted] Client {$app['customer_name']} digitally accepted sanction terms for ₹" . number_format($app['sanctioned_amount']), "search_track.php?id=" . $app['id'], $app['assigned_to']);

            return_json([
                'success' => true,
                'message' => 'Thank you! You have digitally accepted the loan sanction terms. Our team is now initiating disbursal processing.'
            ]);
            break;

        case 'schedule_verification':
            $applicant_id = intval($_POST['applicant_id'] ?? 0);
            $v_type = trim($_POST['verification_type'] ?? 'Residence FI');
            $v_date = trim($_POST['scheduled_date'] ?? date('Y-m-d'));
            $v_time = trim($_POST['scheduled_time'] ?? '11:00 AM');
            $officer_name = trim($_POST['officer_name'] ?? '');
            $officer_mobile = trim($_POST['officer_mobile'] ?? '');

            if ($applicant_id <= 0) return_json(['error' => 'Applicant ID required'], 400);

            $chk = $db->prepare("SELECT customer_name, loan_id, assigned_to FROM applicants WHERE id = ?");
            $chk->execute([$applicant_id]);
            $app = $chk->fetch(PDO::FETCH_ASSOC);

            if (!$app) return_json(['error' => 'Applicant not found'], 404);

            $vStmt = $db->prepare("INSERT INTO verification_schedules (applicant_id, verification_type, scheduled_date, scheduled_time, officer_name, officer_mobile, client_confirmation, created_at) VALUES (?, ?, ?, ?, ?, ?, 'Pending', CURRENT_TIMESTAMP)");
            $vStmt->execute([$applicant_id, $v_type, $v_date, $v_time, $officer_name, $officer_mobile]);

            log_activity("Scheduled $v_type visit for {$app['customer_name']} on $v_date ($v_time)", "settings.php");

            return_json([
                'success' => true,
                'message' => "$v_type scheduled for {$app['customer_name']} on $v_date at $v_time!"
            ]);
            break;

        case 'get_staff_360':
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
            $username = trim($_GET['username'] ?? '');
            if (!$username) return_json(['error' => 'Missing username'], 400);

            // Basic Profile
            $stmt = $db->prepare("SELECT u.name as full_name, u.username, u.role, u.current_status, e.photo_path FROM users u LEFT JOIN employees e ON u.id = e.user_id WHERE u.username = ?");
            $stmt->execute([$username]);
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get live tracking data if available
            $track_stmt = $db->prepare("SELECT battery as current_battery, lat as current_lat, lon as current_lon, created_at as last_ping FROM staff_location_logs WHERE username = ? ORDER BY id DESC LIMIT 1");
            $track_stmt->execute([$username]);
            if ($track = $track_stmt->fetch(PDO::FETCH_ASSOC)) {
                $profile = array_merge($profile, $track);
            }

            if (!$profile) return_json(['error' => 'Staff not found'], 404);

            $today = date('Y-m-d');

            // Today's Attendance
            $att_stmt = $db->prepare("SELECT * FROM staff_attendance WHERE username = ? AND att_date = ? ORDER BY id DESC LIMIT 1");
            $att_stmt->execute([$username, $today]);
            $today_att = $att_stmt->fetch(PDO::FETCH_ASSOC);

            // Today's Field Visits
            $visit_stmt = $db->prepare("SELECT firm_name, person_name, mobile, check_in_time, check_out_time, verified_address, audio_path FROM field_visits WHERE executive_name = ? AND visit_date = ? ORDER BY id ASC");
            $visit_stmt->execute([$username, $today]);
            $today_visits = $visit_stmt->fetchAll(PDO::FETCH_ASSOC);

            // Attendance History (Last 30 Days)
            $hist_stmt = $db->prepare("SELECT att_date, punch_in, punch_out, total_distance FROM staff_attendance WHERE username = ? AND att_date <= ? ORDER BY att_date DESC LIMIT 30");
            $hist_stmt->execute([$username, $today]);
            $history = $hist_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format history
            $formatted_history = [];
            foreach ($history as $h) {
                $dur = "-";
                if ($h['punch_in']) {
                    $p_in = strtotime($h['punch_in']);
                    $p_out = $h['punch_out'] ? strtotime($h['punch_out']) : ($h['att_date'] == $today ? time() : strtotime($h['att_date'] . ' 18:00:00'));
                    $sec = max(0, $p_out - $p_in);
                    $hrs = floor($sec / 3600);
                    $mins = floor(($sec % 3600) / 60);
                    $dur = "{$hrs}h {$mins}m";
                }
                $h['duration'] = $dur;
                $formatted_history[] = $h;
            }

            return_json([
                'profile' => $profile,
                'today_attendance' => $today_att,
                'today_visits' => $today_visits,
                'history' => $formatted_history
            ]);
            break;

        case 'get_employee_productivity':
            $date = trim($_GET['date'] ?? date('Y-m-d'));
            
            // Fetch all staff and admin users with their linked employee records
            $stmt = $db->query("
                SELECT u.id as user_id, u.username, u.role, 
                       e.id as employee_id, e.full_name, e.department, e.designation, e.emp_id
                FROM users u
                LEFT JOIN employees e ON (e.user_id = u.id OR e.official_email = u.username)
                WHERE u.role IN ('Staff', 'Admin') AND u.is_active = 1
                ORDER BY u.role ASC, u.username ASC
            ");
            $staff_members = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $result = [];
            foreach ($staff_members as $m) {
                $user_name = $m['username'];
                $emp_id = $m['employee_id'];
                $full_name = $m['full_name'] ?: ucwords(str_replace(['.', '_', '-'], ' ', explode('@', $user_name)[0]));
                
                // Attendance Lookup from employee_attendance
                $att = null;
                if ($emp_id) {
                    $att_stmt = $db->prepare("SELECT * FROM employee_attendance WHERE employee_id = ? AND attendance_date = ?");
                    $att_stmt->execute([$emp_id, $date]);
                    $att = $att_stmt->fetch(PDO::FETCH_ASSOC);
                }

                if (!$att) {
                    $att_stmt = $db->prepare("SELECT * FROM employee_attendance WHERE emp_username = ? AND attendance_date = ?");
                    $att_stmt->execute([$user_name, $date]);
                    $att = $att_stmt->fetch(PDO::FETCH_ASSOC);
                }

                $working_hours = "0h 0m";
                $working_minutes = 0;
                $is_live = false;
                $cin = $att['check_in_time'] ?? null;
                $cout = $att['check_out_time'] ?? null;
                $att_status = $att['status'] ?? 'Not Punched In';

                if ($cin) {
                    $t_in = strtotime($cin);
                    $t_out = $cout ? strtotime($cout) : (($date === date('Y-m-d')) ? time() : strtotime($date . ' 18:00:00'));
                    if ($t_in > 0 && $t_out >= $t_in) {
                        $diff_sec = $t_out - $t_in;
                        $working_minutes = round($diff_sec / 60);
                        $h = floor($working_minutes / 60);
                        $m_left = $working_minutes % 60;
                        $working_hours = "{$h}h {$m_left}m";
                    }
                    if (!$cout && $date === date('Y-m-d')) {
                        $is_live = true;
                        $att_status = 'On Duty (Shift Active)';
                    } else if ($cout) {
                        $att_status = 'Shift Completed';
                    }
                }

                // Activity Metrics Breakdown from activities table
                $stmt_act = $db->prepare("SELECT description, created_at FROM activities WHERE username = ? AND date(created_at) = ? ORDER BY created_at ASC");
                $stmt_act->execute([$user_name, $date]);
                $activities = $stmt_act->fetchAll(PDO::FETCH_ASSOC);

                $total_actions = count($activities);
                $applicants_added = 0;
                $documents_uploaded = 0;
                $banks_assigned = 0;
                $disbursements_processed = 0;
                $field_visits = 0;
                $client_interactions = 0;
                $punch_logs = 0;

                foreach ($activities as $act) {
                    $desc = strtolower($act['description']);
                    if (strpos($desc, 'applicant') !== false || strpos($desc, 'lead') !== false) {
                        $applicants_added++;
                    } else if (strpos($desc, 'document') !== false || strpos($desc, 'upload') !== false || strpos($desc, 'file') !== false) {
                        $documents_uploaded++;
                    } else if (strpos($desc, 'bank') !== false || strpos($desc, 'login') !== false || strpos($desc, 'submitted') !== false) {
                        $banks_assigned++;
                    } else if (strpos($desc, 'disburs') !== false || strpos($desc, 'sanction') !== false) {
                        $disbursements_processed++;
                    } else if (strpos($desc, 'visit') !== false || strpos($desc, 'field') !== false) {
                        $field_visits++;
                    } else if (strpos($desc, 'note') !== false || strpos($desc, 'status') !== false || strpos($desc, 'follow') !== false || strpos($desc, 'whatsapp') !== false) {
                        $client_interactions++;
                    } else if (strpos($desc, 'punch') !== false) {
                        $punch_logs++;
                    }
                }

                // Calculate Hourly Breakdown (9 AM to 6 PM)
                $hourly_map = [
                    '09' => 0, '10' => 0, '11' => 0, '12' => 0,
                    '13' => 0, '14' => 0, '15' => 0, '16' => 0, '17' => 0, '18' => 0
                ];

                $last_active_time = null;
                $last_active_desc = null;

                foreach ($activities as $act) {
                    $h_str = date('H', strtotime($act['created_at']));
                    if (isset($hourly_map[$h_str])) {
                        $hourly_map[$h_str]++;
                    }
                    $last_active_time = date('h:i A', strtotime($act['created_at']));
                    $last_active_desc = $act['description'];
                }

                // Calculate Productivity Score (0 - 100%)
                $score = 0;
                if ($working_minutes > 0) $score += min(50, round(($working_minutes / 480) * 50));
                if ($total_actions > 0) $score += min(50, $total_actions * 10);
                if ($score > 100) $score = 100;

                $badge = 'Idle';
                if ($score >= 80) $badge = 'Top Performer';
                else if ($score >= 50) $badge = 'Highly Active';
                else if ($score > 0) $badge = 'Moderate';

                $result[] = [
                    'user_id' => $m['user_id'],
                    'username' => $user_name,
                    'full_name' => $full_name,
                    'role' => $m['role'],
                    'department' => $m['department'] ?: (($m['role'] === 'Admin') ? 'Executive Management' : 'Loan Operations'),
                    'designation' => $m['designation'] ?: (($m['role'] === 'Admin') ? 'Administrator' : 'Relationship Manager'),
                    'emp_code' => $m['emp_id'] ?: ('EMP-' . str_pad($m['user_id'], 3, '0', STR_PAD_LEFT)),
                    'working_hours' => $working_hours,
                    'working_minutes' => $working_minutes,
                    'is_live' => $is_live,
                    'att_status' => $att_status,
                    'check_in_time' => $cin ? date('h:i A', strtotime($cin)) : '--:--',
                    'check_out_time' => $cout ? date('h:i A', strtotime($cout)) : ($is_live ? 'Active' : '--:--'),
                    'total_actions' => $total_actions,
                    'applicants_added' => $applicants_added,
                    'documents_uploaded' => $documents_uploaded,
                    'banks_assigned' => $banks_assigned,
                    'disbursements_processed' => $disbursements_processed,
                    'field_visits' => $field_visits,
                    'client_interactions' => $client_interactions,
                    'punch_logs' => $punch_logs,
                    'productivity_score' => $score,
                    'activity_badge' => $badge,
                    'hourly_map' => $hourly_map,
                    'last_active_time' => $last_active_time ?: '--:--',
                    'last_active_desc' => $last_active_desc ?: 'No action logged'
                ];
            }

            // Fetch Top 15 Live Stream Activities across ALL staff today
            $stmt_stream = $db->prepare("
                SELECT id, username, description, action_link, created_at 
                FROM activities 
                WHERE date(created_at) = ? 
                ORDER BY created_at DESC 
                LIMIT 15
            ");
            $stmt_stream->execute([$date]);
            $live_stream = $stmt_stream->fetchAll(PDO::FETCH_ASSOC);

            foreach ($live_stream as &$stream_item) {
                $stream_item['formatted_time'] = date('h:i:s A', strtotime($stream_item['created_at']));
            }

            return_json([
                'success' => true, 
                'date' => $date, 
                'staff' => $result,
                'live_stream' => $live_stream
            ]);
            break;

        case 'get_employee_activity_timeline':
            $date = trim($_GET['date'] ?? date('Y-m-d'));
            $username = trim($_GET['username'] ?? '');
            
            if (!$username) return_json(['error' => 'Username parameter required'], 400);

            $stmt = $db->prepare("SELECT id, username, description, action_link, target_user, created_at FROM activities WHERE username = ? AND date(created_at) = ? ORDER BY created_at DESC");
            $stmt->execute([$username, $date]);
            $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($activities as &$act) {
                $desc = strtolower($act['description']);
                $cat = 'General';
                $icon = 'activity';

                if (strpos($desc, 'applicant') !== false || strpos($desc, 'lead') !== false) {
                    $cat = 'Leads & Applicants';
                    $icon = 'user-plus';
                } else if (strpos($desc, 'document') !== false || strpos($desc, 'upload') !== false || strpos($desc, 'file') !== false) {
                    $cat = 'Documents';
                    $icon = 'file-text';
                } else if (strpos($desc, 'bank') !== false || strpos($desc, 'login') !== false || strpos($desc, 'submitted') !== false) {
                    $cat = 'Bank Submissions';
                    $icon = 'building-2';
                } else if (strpos($desc, 'disburs') !== false || strpos($desc, 'sanction') !== false) {
                    $cat = 'Disbursements';
                    $icon = 'check-circle-2';
                } else if (strpos($desc, 'visit') !== false || strpos($desc, 'field') !== false) {
                    $cat = 'Field Visits';
                    $icon = 'map-pin';
                } else if (strpos($desc, 'punch') !== false) {
                    $cat = 'Attendance';
                    $icon = 'clock';
                }

                $act['category'] = $cat;
                $act['icon'] = $icon;
                $act['formatted_time'] = date('h:i:s A', strtotime($act['created_at']));
            }

            return_json([
                'success' => true,
                'username' => $username,
                'date' => $date,
                'total_actions' => count($activities),
                'activities' => $activities
            ]);
            break;


        
        case 'save_field_visit':
            $executive_name = $_SESSION['username'] ?? 'Staff';
            $visit_date = $_POST['visit_date'] ?? date('Y-m-d');
            $person_name = trim($_POST['person_name'] ?? '');
            $mobile = trim($_POST['mobile'] ?? '');
            $alt_mobile = trim($_POST['alt_mobile'] ?? '');
            $profession = trim($_POST['profession'] ?? '');
            $custom_profession = trim($_POST['custom_profession'] ?? '');
            $firm_name = trim($_POST['firm_name'] ?? '');
            $state = trim($_POST['state'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $pincode = trim($_POST['pincode'] ?? '');
            $full_address = trim($_POST['full_address'] ?? '');
            $lead_quality = trim($_POST['lead_quality'] ?? '');
            $remarks = trim($_POST['remarks'] ?? '');
            $next_meeting_date = trim($_POST['next_meeting_date'] ?? '');
            $latitude = trim($_POST['lat'] ?? '');
            $longitude = trim($_POST['lon'] ?? '');
            $verified_address = trim($_POST['v_addr'] ?? '');
            
            $check_in_time = trim($_POST['check_in_time'] ?? '');
            $check_out_time = trim($_POST['check_out_time'] ?? '');
            
            if (!$person_name || !$mobile || !$firm_name || !$state || !$city || !$lead_quality) {
                return_json(['error' => 'Please fill all required fields.'], 400);
            }

            // Handle Photo
            $photo_path = null;
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
                $filename = 'visit_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                $dest = __DIR__ . '/uploads/field_visits/' . $filename;
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {
                    $photo_path = 'uploads/field_visits/' . $filename;
                }
            }
            
            // Handle Audio
            $audio_path = null;
            if (isset($_FILES['audio_file']) && $_FILES['audio_file']['error'] === UPLOAD_ERR_OK) {
                $ext = 'webm'; // Usually webm from mediaRecorder
                $filename = 'audio_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                $dest = __DIR__ . '/uploads/field_visits/' . $filename;
                if (move_uploaded_file($_FILES['audio_file']['tmp_name'], $dest)) {
                    $audio_path = 'uploads/field_visits/' . $filename;
                }
            }

            $stmt = $db->prepare("INSERT INTO field_visits 
                (visit_date, executive_name, person_name, mobile, alt_mobile, profession, custom_profession, firm_name, state, city, pincode, full_address, lead_quality, photo_path, audio_path, latitude, longitude, verified_address, check_in_time, check_out_time) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
            $stmt->execute([
                $visit_date, $executive_name, $person_name, $mobile, $alt_mobile, $profession, $custom_profession, 
                $firm_name, $state, $city, $pincode, $full_address, $lead_quality, $photo_path, $audio_path, 
                $latitude, $longitude, $verified_address, $check_in_time, $check_out_time
            ]);
            
            $visit_id = $db->lastInsertId();
            
            if ($remarks || $next_meeting_date) {
                $f_stmt = $db->prepare("INSERT INTO field_visit_followups (visit_id, remarks, next_meeting_date, added_by) VALUES (?, ?, ?, ?)");
                $f_stmt->execute([$visit_id, $remarks, $next_meeting_date, $executive_name]);
            }

            return_json(['success' => true, 'message' => 'Visit recorded successfully!']);
            break;

        case 'get_field_visits':
            $page = max(1, intval($_GET['page'] ?? 1));
            $limit = 10;
            $offset = ($page - 1) * $limit;
            $search = trim($_GET['search'] ?? '');
            $quality = trim($_GET['quality'] ?? '');
            $prof = trim($_GET['prof'] ?? '');
            
            $sql = "SELECT * FROM field_visits WHERE 1=1";
            $params = [];
            
            if (($_SESSION['role'] ?? '') !== 'Admin') {
                $sql .= " AND executive_name = ?";
                $params[] = $_SESSION['name'] ?? $_SESSION['username'];
            }
            if ($search) {
                $sql .= " AND (firm_name LIKE ? OR person_name LIKE ? OR mobile LIKE ?)";
                array_push($params, "%$search%", "%$search%", "%$search%");
            }
            if ($quality) {
                $sql .= " AND lead_quality = ?";
                $params[] = $quality;
            }
            if ($prof) {
                $sql .= " AND profession = ?";
                $params[] = $prof;
            }
            
            $stmt = $db->prepare("SELECT COUNT(*) FROM ($sql)");
            $stmt->execute($params);
            $total = $stmt->fetchColumn();
            
            $sql .= " ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return_json([
                'data' => $data,
                'total_records' => $total,
                'total_pages' => ceil($total / $limit)
            ]);
            break;

        case 'get_visit_details':
            $id = intval($_GET['id'] ?? 0);
            $stmt = $db->prepare("SELECT * FROM field_visits WHERE id = ?");
            $stmt->execute([$id]);
            $visit = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$visit) return_json(['error' => 'Not found'], 404);
            
            $stmt = $db->prepare("SELECT * FROM field_visit_followups WHERE visit_id = ? ORDER BY created_at DESC");
            $stmt->execute([$id]);
            $followups = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return_json(['visit' => $visit, 'followups' => $followups]);
            break;
            
        case 'add_visit_followup':
            $visit_id = (int)($_POST['visit_id'] ?? 0);
            $remarks = trim($_POST['remarks'] ?? '');
            $next_meeting_date = trim($_POST['next_meeting_date'] ?? '');
            $added_by = $_SESSION['name'] ?? $_SESSION['username'] ?? 'Admin';
            
            if (!$visit_id || !$remarks) {
                return_json(['error' => 'Missing fields'], 400);
            }
            $stmt = $db->prepare("INSERT INTO field_visit_followups (visit_id, remarks, next_meeting_date, added_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([$visit_id, $remarks, $next_meeting_date, $added_by]);
            return_json(['success' => true]);
            break;

        case 'convert_visit_to_referral':
            $visit_id = intval($_POST['visit_id'] ?? 0);
            $full_name = trim($_POST['full_name'] ?? $_POST['name'] ?? '');
            $mobile = trim($_POST['mobile'] ?? '');
            $referrer_type = trim($_POST['referrer_type'] ?? $_POST['category'] ?? 'Referral Partner');
            $firm_name = trim($_POST['firm_name'] ?? '');
            $city_state = trim($_POST['city_state'] ?? '');
            $assigned_rm = trim($_POST['assigned_rm'] ?? $_POST['assigned_to'] ?? $_SESSION['username'] ?? 'Admin');
            
            if (!$full_name || !$mobile) {
                return_json(['error' => 'Partner Name and Mobile are required.'], 400);
            }
            
            $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $referrer_type), 0, 3));
            if (empty($prefix)) $prefix = 'REF';
            $referral_id = "REF-" . $prefix . "-" . date('Ymd') . rand(100, 999);
            
            try {
                $db->beginTransaction();
                
                $stmt = $db->prepare("INSERT INTO referrals (referral_id, referrer_type, full_name, mobile, city_state, assigned_rm, status) VALUES (?, ?, ?, ?, ?, ?, 'Active')");
                $stmt->execute([$referral_id, $referrer_type, $full_name, $mobile, $city_state, $assigned_rm]);
                
                if ($visit_id > 0) {
                    try {
                        $upd = $db->prepare("UPDATE field_visits SET is_converted = 1, converted_as = 'Referral' WHERE id = ?");
                        $upd->execute([$visit_id]);
                    } catch (Exception $ve) {}
                }
                
                log_activity("Converted Field Visit #{$visit_id} into Referral Partner ({$referral_id} - {$full_name} / {$referrer_type})", "referrals_list.php");
                $db->commit();
                
                return_json(['success' => true, 'message' => 'Partner registered successfully!', 'referral_id' => $referral_id]);
            } catch (Exception $e) {
                $db->rollBack();
                return_json(['error' => 'Database Error: ' . $e->getMessage()], 500);
            }
            break;

        case 'save_applicant':
            // Ensure co_applicants table exists
            $db->exec("CREATE TABLE IF NOT EXISTS co_applicants (
                id INTEGER PRIMARY KEY AUTOINCREMENT, 
                applicant_id INTEGER, 
                is_financial TEXT, 
                relationship TEXT, 
                full_name TEXT, 
                mobile TEXT, 
                email TEXT, 
                dob TEXT, 
                pan_number TEXT, 
                aadhar_number TEXT, 
                same_address INTEGER, 
                address TEXT, 
                pincode TEXT, 
                state TEXT, 
                city TEXT, 
                employment_type TEXT, 
                monthly_income REAL, 
                current_emis REAL
            )");

            $converted_lead_id = (int)($_POST['converted_lead_id'] ?? 0);
            $id = (int)($_POST['id'] ?? 0);
            $customer_name = trim($_POST['customer_name'] ?? '');
            $mobile = trim($_POST['mobile'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $state = trim($_POST['state'] ?? '');
            $pincode = trim($_POST['pincode'] ?? '');
            $pan_number = trim($_POST['pan_number'] ?? '');
            $aadhar_number = trim($_POST['aadhar_number'] ?? '');
            $employment_type = trim($_POST['employment_type'] ?? '');
            $organization_name = trim($_POST['organization_name'] ?? '');
            $organization_address = trim($_POST['organization_address'] ?? '');
            $monthly_income = (float)($_POST['monthly_income'] ?? 0);
            $loan_type = trim($_POST['loan_type'] ?? '');
            $loan_sub_type = trim($_POST['loan_sub_type'] ?? '');
            $loan_amount_requested = (float)($_POST['loan_amount_requested'] ?? 0);
            $tenure_months = (int)($_POST['tenure_months'] ?? 0);
            $lead_source = trim($_POST['lead_source'] ?? '');
            $referral_id = trim($_POST['referral_id'] ?? '');
            $employee_id = trim($_POST['employee_id'] ?? '');
            $added_by = $_SESSION['name'] ?? $_SESSION['username'] ?? 'Admin';
            $assigned_to = trim($_POST['assigned_to'] ?? '');
            if (empty($assigned_to) && isset($_SESSION['role']) && $_SESSION['role'] === 'Staff') {
                $assigned_to = $_SESSION['username'];
            }
            $now = date('Y-m-d H:i:s');
            $validation_errors = [];

            // Name validation
            if(empty($customer_name)) $validation_errors[] = 'Customer name is required';
            elseif(strlen($customer_name) < 2) $validation_errors[] = 'Name must be at least 2 characters';
            elseif(!preg_match('/^[a-zA-Z\s\.]+$/', $customer_name)) $validation_errors[] = 'Name should contain only letters and spaces';

            // Mobile validation
            if(empty($mobile)) $validation_errors[] = 'Mobile number is required';
            elseif(!preg_match('/^[6-9][0-9]{9}$/', $mobile)) $validation_errors[] = 'Invalid mobile number (must be 10 digits starting with 6-9)';

            // Email validation (if provided)
            if(!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $validation_errors[] = 'Invalid email address format';
            }

            // PAN validation (if provided)
            if(!empty($pan_number) && !preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', strtoupper($pan_number))) {
                $validation_errors[] = 'Invalid PAN number format (e.g., ABCDE1234F)';
            }

            // Aadhaar validation (if provided)
            if(!empty($aadhar_number) && !preg_match('/^[0-9]{12}$/', $aadhar_number)) {
                $validation_errors[] = 'Aadhaar must be exactly 12 digits';
            }

            // Amount validation
            if($loan_amount_requested > 0 && $loan_amount_requested < 10000) {
                $validation_errors[] = 'Loan amount seems too low (minimum ₹10,000)';
            }
            if($loan_amount_requested > 100000000) { // 10 crore
                $validation_errors[] = 'Loan amount exceeds maximum limit (₹10 Crore)';
            }

            // CIBIL score validation
            if(!empty($_POST['cibil_score'])) {
                $cibil = intval($_POST['cibil_score']);
                if($cibil < 300 || $cibil > 900) $validation_errors[] = 'CIBIL score must be between 300 and 900';
            }

            if(!empty($validation_errors)) {
                return_json(['error' => implode('. ', $validation_errors)], 422);
            }

            // Sanitize inputs
            $customer_name = htmlspecialchars(strip_tags(trim($customer_name)));
            $mobile = preg_replace('/[^0-9]/', '', $mobile);
            $pan_number = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $pan_number));
            $aadhar_number = preg_replace('/[^0-9]/', '', $aadhar_number);

            // Duplicate detection - only for new applicants (not edits)
            if(!$id) {
                $dup_checks = [];
                if(!empty($mobile) && strlen($mobile) == 10) {
                    $dup_stmt = $db->prepare("SELECT id, loan_id, customer_name, mobile, pan_number, aadhar_number, overall_status, loan_type, loan_amount_requested, created_at, assigned_to FROM applicants WHERE mobile = ? AND overall_status != 'Rejected' LIMIT 3");
                    $dup_stmt->execute([$mobile]);
                    $existing = $dup_stmt->fetchAll(PDO::FETCH_ASSOC);
                    if($existing) {
                        $dup_checks[] = ['type'=>'Mobile Number', 'matches'=>$existing];
                    }
                }
                if(!empty($pan_number) && strlen($pan_number) == 10) {
                    $pan_stmt = $db->prepare("SELECT id, loan_id, customer_name, mobile, pan_number, aadhar_number, overall_status, loan_type, loan_amount_requested, created_at, assigned_to FROM applicants WHERE pan_number = ? AND overall_status != 'Rejected' LIMIT 3");
                    $pan_stmt->execute([$pan_number]);
                    $existing_pan = $pan_stmt->fetchAll(PDO::FETCH_ASSOC);
                    if($existing_pan) {
                        $dup_checks[] = ['type'=>'PAN Number', 'matches'=>$existing_pan];
                    }
                }
                if(!empty($aadhar_number) && strlen($aadhar_number) == 12) {
                    $aad_stmt = $db->prepare("SELECT id, loan_id, customer_name, mobile, pan_number, aadhar_number, overall_status, loan_type, loan_amount_requested, created_at, assigned_to FROM applicants WHERE aadhar_number = ? AND overall_status != 'Rejected' LIMIT 3");
                    $aad_stmt->execute([$aadhar_number]);
                    $existing_aad = $aad_stmt->fetchAll(PDO::FETCH_ASSOC);
                    if($existing_aad) {
                        $dup_checks[] = ['type'=>'Aadhaar Number', 'matches'=>$existing_aad];
                    }
                }
                if(!empty($dup_checks)) {
                    // Return warning - client will ask user to confirm before proceeding
                    $confirm_force = intval($_POST['force_duplicate'] ?? 0);
                    if(!$confirm_force) {
                        return_json(['warning'=>'duplicate_detected', 'duplicates'=>$dup_checks, 'message'=>'Applicant details already registered in database.'], 409);
                    }
                    // If force_duplicate=1, proceed with creation
                }
            }
            
            if ($id > 0) {
                $upd_sql = "UPDATE applicants SET customer_name=?, mobile=?, email=?, address=?, city=?, state=?, pincode=?, pan_number=?, aadhar_number=?, employment_type=?, organization_name=?, organization_address=?, monthly_income=?, loan_type=?, loan_sub_type=?, loan_amount_requested=?, tenure_months=?, lead_source=?, referral_id=?, employee_id=?";
                $upd_params = [$customer_name, $mobile, $email, $address, $city, $state, $pincode, $pan_number, $aadhar_number, $employment_type, $organization_name, $organization_address, $monthly_income, $loan_type, $loan_sub_type, $loan_amount_requested, $tenure_months, $lead_source, $referral_id, $employee_id];
                if (!empty($assigned_to)) {
                    $upd_sql .= ", assigned_to = ?, assigned_by = ?, assigned_at = ?";
                    $upd_params[] = $assigned_to;
                    $upd_params[] = $added_by;
                    $upd_params[] = $now;
                }
                $upd_sql .= " WHERE id = ?";
                $upd_params[] = $id;

                $stmt = $db->prepare($upd_sql);
                $stmt->execute($upd_params);
                $final_id = $id;
                $msg = 'Applicant updated';
            } else {
                $loan_id = 'L' . date('Ymd') . rand(1000, 9999);
                $stmt = $db->prepare("INSERT INTO applicants (loan_id, customer_name, mobile, email, address, city, state, pincode, pan_number, aadhar_number, employment_type, organization_name, organization_address, monthly_income, loan_type, loan_sub_type, loan_amount_requested, tenure_months, lead_source, referral_id, employee_id, added_by, assigned_to, assigned_by, assigned_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$loan_id, $customer_name, $mobile, $email, $address, $city, $state, $pincode, $pan_number, $aadhar_number, $employment_type, $organization_name, $organization_address, $monthly_income, $loan_type, $loan_sub_type, $loan_amount_requested, $tenure_months, $lead_source, $referral_id, $employee_id, $added_by, $assigned_to ?: null, $added_by, $now]);
                $final_id = $db->lastInsertId();
                
                if ($converted_lead_id > 0) {
                    $db->prepare("UPDATE leads SET stage = 'Converted' WHERE id = ?")->execute([$converted_lead_id]);
                }
                $msg = 'Applicant registered';
            }

            // Save Co-Applicants logic
            $db->prepare("DELETE FROM co_applicants WHERE applicant_id = ?")->execute([$final_id]);
            
            if (!empty($_POST['coapp_name']) && is_array($_POST['coapp_name'])) {
                $co_stmt = $db->prepare("INSERT INTO co_applicants (applicant_id, is_financial, relationship, full_name, mobile, email, dob, pan_number, aadhar_number, same_address, address, pincode, state, city, employment_type, monthly_income, current_emis) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                foreach ($_POST['coapp_name'] as $index => $name) {
                    if (trim($name) === '') continue; // Skip empties
                    
                    $r_financial = $_POST['coapp_is_financial'][$index] ?? 'No';
                    $r_rel = $_POST['coapp_relationship'][$index] ?? '';
                    $r_mob = $_POST['coapp_mobile'][$index] ?? '';
                    $r_em = $_POST['coapp_email'][$index] ?? '';
                    $r_dob = $_POST['coapp_dob'][$index] ?? '';
                    $r_pan = $_POST['coapp_pan'][$index] ?? '';
                    $r_aad = $_POST['coapp_aadhar'][$index] ?? '';
                    
                    $r_same = (int)($_POST['coapp_same_address'][$index] ?? 0);
                    $r_addr = $r_same ? $address : ($_POST['coapp_address'][$index] ?? '');
                    $r_pin = $r_same ? $pincode : ($_POST['coapp_pincode'][$index] ?? '');
                    $r_state = $r_same ? $state : ($_POST['coapp_state'][$index] ?? '');
                    $r_city = $r_same ? $city : ($_POST['coapp_city'][$index] ?? '');
                    
                    $r_emp = $_POST['coapp_emp_type'][$index] ?? '';
                    $r_inc = (float)($_POST['coapp_income'][$index] ?? 0);
                    $r_emi = (float)($_POST['coapp_emis'][$index] ?? 0);
                    
                    $co_stmt->execute([
                        $final_id, $r_financial, $r_rel, trim($name), $r_mob, $r_em, $r_dob, 
                        $r_pan, $r_aad, $r_same, $r_addr, $r_pin, $r_state, $r_city, 
                        $r_emp, $r_inc, $r_emi
                    ]);
                }
            }
            

            // Save PD Report Details
            $pd_conducted_by = trim($_POST['pd_conducted_by'] ?? '');
            if ($pd_conducted_by || !empty($_POST['final_pd_status'])) {
                $pd_date = trim($_POST['pd_date'] ?? '');
                $pd_mode = trim($_POST['pd_mode'] ?? '');
                $business_board_seen = trim($_POST['business_board_seen'] ?? '');
                $stock_status = trim($_POST['stock_status'] ?? '');
                $business_stability = trim($_POST['business_stability'] ?? '');
                $monthly_turnover = (float)($_POST['monthly_turnover'] ?? 0);
                $residence_type = trim($_POST['residence_type'] ?? '');
                $years_at_address = (float)($_POST['years_at_address'] ?? 0);
                $locality_classification = trim($_POST['locality_classification'] ?? '');
                $neighbor_feedback = trim($_POST['neighbor_feedback'] ?? '');
                
                $consumer_durables = isset($_POST['consumer_durables']) && is_array($_POST['consumer_durables']) ? implode(', ', $_POST['consumer_durables']) : '';
                $lifestyle_score = trim($_POST['lifestyle_score'] ?? '');
                
                $positive_triggers = isset($_POST['positive_triggers']) && is_array($_POST['positive_triggers']) ? implode(', ', $_POST['positive_triggers']) : '';
                $negative_triggers = isset($_POST['negative_triggers']) && is_array($_POST['negative_triggers']) ? implode(', ', $_POST['negative_triggers']) : '';
                
                $recommended_loan_amount = (float)($_POST['recommended_loan_amount'] ?? 0);
                $final_pd_status = trim($_POST['final_pd_status'] ?? '');
                
                $pd_report_path = '';
                // Check if existing PD record exists to retain old file path
                $existing_pd = $db->query("SELECT pd_report_path FROM applicant_pd_reports WHERE applicant_id = $final_id")->fetch();
                if ($existing_pd) $pd_report_path = $existing_pd['pd_report_path'];
                
                if (isset($_FILES['pd_report_file']) && $_FILES['pd_report_file']['error'] === UPLOAD_ERR_OK) {
                    $ext = pathinfo($_FILES['pd_report_file']['name'], PATHINFO_EXTENSION);
                    $new_name = 'uploads/applicants/pd_' . $final_id . '_' . time() . '.' . $ext;
                    if (!is_dir('uploads/applicants')) mkdir('uploads/applicants', 0777, true);
                    if (move_uploaded_file($_FILES['pd_report_file']['tmp_name'], $new_name)) {
                        $pd_report_path = $new_name;
                    }
                }
                
                if ($existing_pd) {
                    $pd_stmt = $db->prepare("UPDATE applicant_pd_reports SET pd_conducted_by=?, pd_date=?, pd_mode=?, business_board_seen=?, stock_status=?, business_stability=?, monthly_turnover=?, residence_type=?, years_at_address=?, locality_classification=?, neighbor_feedback=?, consumer_durables=?, lifestyle_score=?, positive_triggers=?, negative_triggers=?, recommended_loan_amount=?, final_pd_status=?, pd_report_path=? WHERE applicant_id=?");
                    $pd_stmt->execute([$pd_conducted_by, $pd_date, $pd_mode, $business_board_seen, $stock_status, $business_stability, $monthly_turnover, $residence_type, $years_at_address, $locality_classification, $neighbor_feedback, $consumer_durables, $lifestyle_score, $positive_triggers, $negative_triggers, $recommended_loan_amount, $final_pd_status, $pd_report_path, $final_id]);
                } else {
                    $pd_stmt = $db->prepare("INSERT INTO applicant_pd_reports (applicant_id, pd_conducted_by, pd_date, pd_mode, business_board_seen, stock_status, business_stability, monthly_turnover, residence_type, years_at_address, locality_classification, neighbor_feedback, consumer_durables, lifestyle_score, positive_triggers, negative_triggers, recommended_loan_amount, final_pd_status, pd_report_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $pd_stmt->execute([$final_id, $pd_conducted_by, $pd_date, $pd_mode, $business_board_seen, $stock_status, $business_stability, $monthly_turnover, $residence_type, $years_at_address, $locality_classification, $neighbor_feedback, $consumer_durables, $lifestyle_score, $positive_triggers, $negative_triggers, $recommended_loan_amount, $final_pd_status, $pd_report_path]);
                }
            }

            return_json(['success' => true, 'message' => $msg, 'id' => $final_id]);
            break;

        case 'get_applicants':
            $page = max(1, intval($_GET['page'] ?? 1));
            $limit = 20;
            $offset = ($page - 1) * $limit;
            $search = trim($_GET['search'] ?? '');
            
            $sql = "SELECT *, 100 as calculated_completion FROM applicants WHERE 1=1";
            $params = [];

            if (isset($_SESSION['role']) && ($_SESSION['role'] === 'Staff' || $_SESSION['role'] === 'RM')) {
                $cur_u = $_SESSION['username'] ?? '';
                $cur_n = $_SESSION['name'] ?? $_SESSION['user_full_name'] ?? $cur_u;
                $sql .= " AND (assigned_to = ? OR added_by = ? OR added_by = ? OR employee_id = ?)";
                array_push($params, $cur_u, $cur_u, $cur_n, $cur_u);
            }
            
            if ($search) {
                $sql .= " AND (customer_name LIKE ? OR loan_id LIKE ? OR mobile LIKE ?)";
                array_push($params, "%$search%", "%$search%", "%$search%");
            }
            
            $stmt = $db->prepare("SELECT COUNT(*) FROM ($sql)");
            $stmt->execute($params);
            $total = $stmt->fetchColumn();
            
            $sql .= " ORDER BY id DESC LIMIT $limit OFFSET $offset";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return_json($data);
            break;

        case 'get_applicant':
            $id = (int)($_GET['id'] ?? 0);
            $stmt = $db->prepare("SELECT * FROM applicants WHERE id = ?");
            $stmt->execute([$id]);
            $app = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$app) return_json(['error' => 'Not found'], 404);
            
            // Fetch co-applicants
            $co_stmt = $db->prepare("SELECT * FROM co_applicants WHERE applicant_id = ?");
            $co_stmt->execute([$id]);
            $app['co_applicants'] = $co_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Fetch PD reports
            $pd_stmt = $db->prepare("SELECT * FROM applicant_pd_reports WHERE applicant_id = ?");
            $pd_stmt->execute([$id]);
            $app['pd_report'] = $pd_stmt->fetch(PDO::FETCH_ASSOC);
            
            return_json($app);
            break;
            
        case 'delete_applicant':
            if (($_SESSION['role'] ?? '') !== 'Admin') return_json(['error' => 'Unauthorized'], 403);
            $id = (int)($_POST['id'] ?? 0);
            $db->prepare("DELETE FROM applicants WHERE id = ?")->execute([$id]);
            return_json(['success' => true]);
            break;

        case 'send_deactivation_otp':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Method'], 405);
            $app_id = (int)($_POST['applicant_id'] ?? 0);
            $override_email = trim($_POST['email'] ?? '');

            $app_stmt = $db->prepare("SELECT * FROM applicants WHERE id = ?");
            $app_stmt->execute([$app_id]);
            $app = $app_stmt->fetch(PDO::FETCH_ASSOC);
            if (!$app) return_json(['error' => 'Applicant file not found.'], 404);

            $target_email = !empty($override_email) ? $override_email : trim($app['email'] ?? '');
            if (empty($target_email) || !filter_var($target_email, FILTER_VALIDATE_EMAIL)) {
                return_json(['error' => 'Valid email address is required to send deactivation code.'], 400);
            }

            // Generate 6-digit OTP
            $otp = sprintf("%06d", mt_rand(100000, 999999));
            $expiry = date('Y-m-d H:i:s', time() + 900); // 15 mins

            // Update DB with OTP and email if override provided
            $up_stmt = $db->prepare("UPDATE applicants SET email = ?, deactivation_otp = ?, deactivation_otp_expiry = ? WHERE id = ?");
            $up_stmt->execute([$target_email, $otp, $expiry, $app_id]);

            // Email Body Construction
            $subject = "Security OTP Code: {$otp} - Deactivate Loan File #{$app['loan_id']}";
            $body = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 12px; background: #ffffff;'>
                    <div style='background: #0f172a; padding: 16px 20px; border-radius: 8px 8px 0 0; color: #ffffff;'>
                        <h2 style='margin: 0; font-size: 18px;'>BFS Circle - Security Verification</h2>
                    </div>
                    <div style='padding: 20px; color: #334155; line-height: 1.6;'>
                        <p>Dear <strong>" . htmlspecialchars($app['customer_name']) . "</strong>,</p>
                        <p>A request has been initiated to <strong>DEACTIVATE</strong> your loan application file (Loan Ref: <strong>" . htmlspecialchars($app['loan_id']) . "</strong>).</p>
                        <div style='background: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 8px; padding: 16px; text-align: center; margin: 20px 0;'>
                            <div style='font-size: 12px; color: #64748b; text-transform: uppercase; font-weight: 700; letter-spacing: 1px;'>Your 6-Digit Verification Code</div>
                            <div style='font-size: 32px; font-weight: 800; color: #0f172a; letter-spacing: 6px; margin: 8px 0;'>" . $otp . "</div>
                            <div style='font-size: 11px; color: #ef4444;'>Valid for 15 minutes. Do not share this code with unauthorized personnel.</div>
                        </div>
                        <p>If you did not request this deactivation, please contact your Relationship Manager immediately.</p>
                        <hr style='border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;'>
                        <p style='font-size: 11px; color: #94a3b8; text-align: center;'>BFS Circle Financial Services • Secure Customer Portal</p>
                    </div>
                </div>
            ";

            // Attempt Email Dispatch via PHPMailer or simulated dispatch
            $profile_stmt = $db->query("SELECT smtp_host, smtp_port, smtp_username, smtp_password, smtp_encryption FROM company_profile LIMIT 1");
            $profile = $profile_stmt ? $profile_stmt->fetch(PDO::FETCH_ASSOC) : null;

            $sent_live = false;
            $email_error = '';

            if ($profile && !empty($profile['smtp_host']) && !empty($profile['smtp_username'])) {
                require_once __DIR__ . '/libs/PHPMailer/Exception.php';
                require_once __DIR__ . '/libs/PHPMailer/PHPMailer.php';
                require_once __DIR__ . '/libs/PHPMailer/SMTP.php';

                try {
                    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                    $mail->isSMTP();
                    $mail->Host       = $profile['smtp_host'];
                    $mail->SMTPAuth   = true;
                    $mail->Username   = $profile['smtp_username'];
                    $mail->Password   = $profile['smtp_password'];
                    if (strtolower($profile['smtp_encryption']) === 'tls') {
                        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                    } elseif (strtolower($profile['smtp_encryption']) === 'ssl') {
                        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                    }
                    $mail->Port       = (int)$profile['smtp_port'];

                    $mail->setFrom($profile['smtp_username'], 'BFS Circle Verification Desk');
                    $mail->addAddress($target_email, $app['customer_name']);

                    $mail->isHTML(true);
                    $mail->Subject = $subject;
                    $mail->Body    = $body;
                    $mail->AltBody = "Your Deactivation Code for Loan Ref #{$app['loan_id']} is: {$otp}";

                    $mail->send();
                    $sent_live = true;
                } catch (Exception $e) {
                    $email_error = $e->getMessage();
                }
            }

            // Log communication in communications table
            $now_dt = date('Y-m-d H:i:s');
            $comm_stmt = $db->prepare("INSERT INTO communications (client_id, type, subject, body, sent_by, sent_at) VALUES (?, 'Email', ?, ?, ?, ?)");
            $comm_stmt->execute([$app_id, $subject, $body, $_SESSION['username'] ?? 'System', $now_dt]);

            log_activity("Deactivation OTP code generated for applicant {$app['customer_name']} (Ref #{$app['loan_id']}) and sent to {$target_email}.", "search_track.php?id={$app_id}");

            return_json([
                'success' => true,
                'message' => $sent_live ? "Verification code sent to {$target_email}!" : "Verification code generated for {$target_email}.",
                'target_email' => $target_email,
                'otp_simulated' => $sent_live ? null : $otp
            ]);
            break;

        case 'verify_deactivation_otp':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Method'], 405);
            $app_id = (int)($_POST['applicant_id'] ?? 0);
            $user_otp = trim($_POST['otp'] ?? '');
            $reason = trim($_POST['reason'] ?? 'Requested by user / staff');

            if (empty($user_otp)) {
                return_json(['error' => 'Please enter the 6-digit verification code.'], 400);
            }

            $app_stmt = $db->prepare("SELECT * FROM applicants WHERE id = ?");
            $app_stmt->execute([$app_id]);
            $app = $app_stmt->fetch(PDO::FETCH_ASSOC);
            if (!$app) return_json(['error' => 'Applicant file not found.'], 404);

            if (empty($app['deactivation_otp']) || $app['deactivation_otp'] !== $user_otp) {
                return_json(['error' => 'Invalid verification code. Please check your email and try again.'], 400);
            }

            if (!empty($app['deactivation_otp_expiry']) && strtotime($app['deactivation_otp_expiry']) < time()) {
                return_json(['error' => 'Verification code has expired. Please request a new code.'], 400);
            }

            // Code verified! Deactivate the applicant file (is_active = 0)
            $db->prepare("UPDATE applicants SET is_active = 0, deactivation_otp = NULL, deactivation_otp_expiry = NULL, deactivation_reason = ? WHERE id = ?")->execute([$reason, $app_id]);

            log_activity("Applicant {$app['customer_name']} (Ref #{$app['loan_id']}) file DEACTIVATED via email OTP confirmation. Reason: {$reason}", "search_track.php?id={$app_id}");

            return_json([
                'success' => true,
                'message' => "Applicant file for {$app['customer_name']} has been successfully DEACTIVATED."
            ]);
            break;

        case 'reactivate_applicant':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Method'], 405);
            $app_id = (int)($_POST['applicant_id'] ?? 0);

            $app_stmt = $db->prepare("SELECT * FROM applicants WHERE id = ?");
            $app_stmt->execute([$app_id]);
            $app = $app_stmt->fetch(PDO::FETCH_ASSOC);
            if (!$app) return_json(['error' => 'Applicant file not found.'], 404);

            $db->prepare("UPDATE applicants SET is_active = 1, deactivation_reason = NULL WHERE id = ?")->execute([$app_id]);

            log_activity("Applicant {$app['customer_name']} (Ref #{$app['loan_id']}) file REACTIVATED.", "search_track.php?id={$app_id}");

            return_json([
                'success' => true,
                'message' => "Applicant file for {$app['customer_name']} is now active!"
            ]);
            break;

        case 'search_applicants':
            $query = trim($_GET['query'] ?? '');
            $status = trim($_GET['status'] ?? '');
            $loan_type = trim($_GET['loan_type'] ?? '');
            $staff = trim($_GET['staff'] ?? '');
            $bank = trim($_GET['bank'] ?? '');
            $aging = trim($_GET['aging'] ?? '');
            $sort = trim($_GET['sort'] ?? 'newest');
            $date_from = trim($_GET['date_from'] ?? '');
            $date_to = trim($_GET['date_to'] ?? '');
            $amt_min = trim($_GET['amt_min'] ?? '');
            $amt_max = trim($_GET['amt_max'] ?? '');
            
            // If filtering by bank, we need a JOIN
            if ($bank) {
                $sql = "SELECT DISTINCT a.id, a.loan_id, a.customer_name, a.mobile, a.loan_amount_requested as amount, a.loan_type, a.overall_status, a.created_at, COALESCE(a.is_active, 1) as is_active 
                        FROM applicants a 
                        JOIN applicant_bank_assignments b ON a.id = b.applicant_id 
                        WHERE b.bank_name = ?";
                $params = [$bank];
            } else {
                $sql = "SELECT id, loan_id, customer_name, mobile, loan_amount_requested as amount, loan_type, overall_status, created_at, COALESCE(is_active, 1) as is_active FROM applicants WHERE 1=1";
                $params = [];
            }

            $prefix = $bank ? "a." : "";
            if (isset($_SESSION['role']) && ($_SESSION['role'] === 'Staff' || $_SESSION['role'] === 'RM')) {
                $cur_u = $_SESSION['username'] ?? '';
                $cur_n = $_SESSION['name'] ?? $_SESSION['user_full_name'] ?? $cur_u;
                $sql .= " AND ({$prefix}assigned_to = ? OR {$prefix}assigned_to = ? OR {$prefix}added_by = ? OR {$prefix}added_by = ? OR {$prefix}employee_id = ?)";
                array_push($params, $cur_u, $cur_n, $cur_u, $cur_n, $cur_u);
            }
            
            if ($query) {
                if ($bank) {
                    $sql .= " AND (a.customer_name LIKE ? OR a.loan_id LIKE ? OR a.mobile LIKE ? OR a.pan_number LIKE ?)";
                } else {
                    $sql .= " AND (customer_name LIKE ? OR loan_id LIKE ? OR mobile LIKE ? OR pan_number LIKE ?)";
                }
                array_push($params, "%$query%", "%$query%", "%$query%", "%$query%");
            }
            if ($status) {
                $prefix = $bank ? "a." : "";
                $sql .= " AND {$prefix}overall_status = ?";
                $params[] = $status;
            }
            if ($loan_type) {
                $prefix = $bank ? "a." : "";
                $sql .= " AND {$prefix}loan_type = ?";
                $params[] = $loan_type;
            }
            if ($staff) {
                $prefix = $bank ? "a." : "";
                $sql .= " AND {$prefix}added_by = ?";
                $params[] = $staff;
            }
            if ($date_from) {
                $prefix = $bank ? "a." : "";
                $sql .= " AND DATE({$prefix}created_at) >= ?";
                $params[] = $date_from;
            }
            if ($date_to) {
                $prefix = $bank ? "a." : "";
                $sql .= " AND DATE({$prefix}created_at) <= ?";
                $params[] = $date_to;
            }
            if ($amt_min !== '') {
                $prefix = $bank ? "a." : "";
                $sql .= " AND {$prefix}loan_amount_requested >= ?";
                $params[] = $amt_min;
            }
            if ($amt_max !== '') {
                $prefix = $bank ? "a." : "";
                $sql .= " AND {$prefix}loan_amount_requested <= ?";
                $params[] = $amt_max;
            }
            if ($aging) {
                $prefix = $bank ? "a." : "";
                if ($aging === '0-7') {
                    $sql .= " AND (julianday('now') - julianday({$prefix}created_at)) <= 7";
                } elseif ($aging === '7-30') {
                    $sql .= " AND (julianday('now') - julianday({$prefix}created_at)) > 7 AND (julianday('now') - julianday({$prefix}created_at)) <= 30";
                } elseif ($aging === '30-90') {
                    $sql .= " AND (julianday('now') - julianday({$prefix}created_at)) > 30 AND (julianday('now') - julianday({$prefix}created_at)) <= 90";
                } elseif ($aging === '90+') {
                    $sql .= " AND (julianday('now') - julianday({$prefix}created_at)) > 90";
                }
            }
            
            // ORDER BY Logic
            $prefix = $bank ? "a." : "";
            if ($sort === 'oldest') {
                $sql .= " ORDER BY {$prefix}id ASC";
            } elseif ($sort === 'amount_high') {
                $sql .= " ORDER BY CAST({$prefix}loan_amount_requested AS REAL) DESC";
            } elseif ($sort === 'amount_low') {
                $sql .= " ORDER BY CAST({$prefix}loan_amount_requested AS REAL) ASC";
            } elseif ($sort === 'name_az') {
                $sql .= " ORDER BY {$prefix}customer_name ASC";
            } else {
                $sql .= " ORDER BY {$prefix}id DESC"; // newest default
            }
            
            $offset = (int)($_GET['offset'] ?? 0);
            $limit = (int)($_GET['limit'] ?? 25);
            if ($limit <= 0 || $limit > 100) $limit = 25;
            
            // Calculate total count if with_meta requested
            $total_count = null;
            if (!empty($_GET['with_meta'])) {
                $where_pos = stripos($sql, 'WHERE');
                $order_pos = stripos($sql, 'ORDER BY');
                if ($where_pos !== false && $order_pos !== false) {
                    $where_clause = substr($sql, $where_pos, $order_pos - $where_pos);
                    $cnt_sql = ($bank) 
                        ? "SELECT COUNT(DISTINCT a.id) FROM applicants a JOIN applicant_bank_assignments b ON a.id = b.applicant_id " . $where_clause 
                        : "SELECT COUNT(id) FROM applicants " . $where_clause;
                    try {
                        $cnt_stmt = $db->prepare($cnt_sql);
                        $cnt_stmt->execute($params);
                        $total_count = (int)$cnt_stmt->fetchColumn();
                    } catch (Exception $e) {
                        $total_count = null;
                    }
                }
            }
            
            $sql .= " LIMIT $limit OFFSET $offset";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($_GET['with_meta'])) {
                return_json([
                    'applicants' => $rows,
                    'total' => $total_count ?? count($rows),
                    'offset' => $offset,
                    'limit' => $limit,
                    'page' => floor($offset / $limit) + 1,
                    'total_pages' => ($total_count !== null) ? max(1, ceil($total_count / $limit)) : 1
                ]);
            } else {
                return_json($rows);
            }
            break;

        case 'applicant_full_details':
            $id = (int)($_GET['id'] ?? 0);
            $stmt = $db->prepare("SELECT * FROM applicants WHERE id = ?");
            $stmt->execute([$id]);
            $app = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$app) return_json(['error' => 'Not found'], 404);
            
            // Fetch PD Report
            $pd_stmt = $db->prepare("SELECT * FROM applicant_pd_reports WHERE applicant_id = ?");
            $pd_stmt->execute([$id]);
            $app['pd_report'] = $pd_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Phase 1 Calculation
            $phase1_fields = [
                'customer_name' => 'Name', 'mobile' => 'Mobile', 'email' => 'Email', 
                'pan_number' => 'PAN', 'aadhar_number' => 'Aadhaar', 'address' => 'Address', 
                'monthly_income' => 'Income', 'loan_type' => 'Loan Type'
            ];
            $phase1_missing = [];
            $phase1_filled = 0;
            foreach ($phase1_fields as $key => $label) {
                if (empty($app[$key])) {
                    $phase1_missing[] = $label;
                } else {
                    $phase1_filled++;
                }
            }
            $app['phase1_completion'] = round(($phase1_filled / count($phase1_fields)) * 100);
            $app['phase1_missing'] = $phase1_missing;

            // Phase 2 Calculation (Dynamic Checklist via Smart Engine)
            $co_stmt = $db->prepare("SELECT * FROM co_applicants WHERE applicant_id = ?");
            $co_stmt->execute([$id]);
            $co_apps = $co_stmt->fetchAll(PDO::FETCH_ASSOC);
            $app['co_applicants'] = $co_apps;

            $suggestions = getLoanDocumentSuggestions($app['loan_type'] ?? '', $app['loan_sub_type'] ?? '', $app['employment_type'] ?? 'Salaried', $co_apps);
            
            // documents
            $stmt = $db->prepare("
                SELECT d.*, c.full_name as coapp_name 
                FROM applicant_documents d 
                LEFT JOIN co_applicants c ON d.owner_id = c.id 
                WHERE d.applicant_id = ? 
                ORDER BY d.id DESC
            ");
            $stmt->execute([$id]);
            $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $app['documents'] = $docs;
            
            // Check Client Vault for any lifelong documents available
            $vault_docs = [];
            if (!empty($app['mobile']) || !empty($app['pan_number'])) {
                $v_stmt = $db->prepare("
                    SELECT d.*, a.customer_name, a.loan_id as source_loan_id
                    FROM applicant_documents d
                    JOIN applicants a ON d.applicant_id = a.id
                    WHERE (d.client_mobile = ? OR (d.client_pan = ? AND d.client_pan != ''))
                      AND d.applicant_id != ?
                    ORDER BY d.id DESC
                ");
                $v_stmt->execute([$app['mobile'], $app['pan_number'], $id]);
                $vault_docs = $v_stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            $app['vault_documents'] = $vault_docs;

            // Match against smart checklist items
            $checklist = [];
            $mandatory_count = 0;
            $mandatory_uploaded = 0;
            
            foreach ($suggestions as $sug) {
                if ($sug['is_mandatory']) $mandatory_count++;
                
                $matched_docs = [];
                $sug_name = strtolower($sug['doc_name']);
                $sug_clean = preg_replace('/\s*\(.*?\)\s*/', '', $sug_name);

                foreach ($docs as $d) {
                    // Match Owner Type
                    $owner_match = false;
                    if (($sug['owner_type'] ?? 'Applicant') === 'CoApplicant') {
                        if (($d['owner_type'] ?? '') === 'CoApplicant') {
                            if (!empty($sug['owner_id']) && !empty($d['owner_id'])) {
                                $owner_match = ($d['owner_id'] == $sug['owner_id']);
                            } else {
                                $owner_match = true;
                            }
                        }
                    } else {
                        if (($d['owner_type'] ?? 'Applicant') !== 'CoApplicant') {
                            $owner_match = true;
                        }
                    }

                    if (!$owner_match) continue;

                    // Match Document Name smartly (exact, substring, or core keywords)
                    $doc_name = strtolower($d['document_name']);
                    $doc_clean = preg_replace('/\s*\(.*?\)\s*/', '', $doc_name);

                    $name_match = (
                        $doc_name === $sug_name ||
                        stripos($doc_name, $sug_clean) !== false ||
                        stripos($sug_name, $doc_clean) !== false
                    );

                    if (!$name_match) {
                        $keywords = ['pan', 'aadhaar', 'salary', 'bank', 'itr', 'form 16', 'passport', 'photo', 'voter', 'driving', 'electricity', 'utility', 'gst', 'trade', 'msme', 'registry', 'property', 'tax', 'vehicle', 'rc', 'cibil', 'valuation', 'relation'];
                        foreach ($keywords as $kw) {
                            if (preg_match('/\b' . preg_quote($kw, '/') . '\b/i', $sug_clean) && preg_match('/\b' . preg_quote($kw, '/') . '\b/i', $doc_clean)) {
                                $name_match = true;
                                break;
                            }
                        }
                    }

                    if ($name_match) {
                        $matched_docs[] = $d;
                    }
                }
                
                // Check if available in vault
                $vault_match = null;
                if (empty($matched_docs) && !empty($vault_docs)) {
                    foreach ($vault_docs as $vd) {
                        $doc_name = strtolower($vd['document_name']);
                        $doc_clean = preg_replace('/\s*\(.*?\)\s*/', '', $doc_name);

                        $v_match = (
                            $doc_name === $sug_name ||
                            stripos($doc_name, $sug_clean) !== false ||
                            stripos($sug_name, $doc_clean) !== false
                        );

                        if (!$v_match) {
                            $keywords = ['pan', 'aadhaar', 'salary', 'bank', 'itr', 'form 16', 'passport', 'photo', 'voter', 'driving', 'electricity', 'utility', 'gst', 'trade', 'msme', 'registry', 'property', 'tax', 'vehicle', 'rc', 'cibil', 'valuation', 'relation'];
                            foreach ($keywords as $kw) {
                                if (preg_match('/\b' . preg_quote($kw, '/') . '\b/i', $sug_clean) && preg_match('/\b' . preg_quote($kw, '/') . '\b/i', $doc_clean)) {
                                    $v_match = true;
                                    break;
                                }
                            }
                        }

                        if ($v_match) {
                            $vault_match = $vd;
                            break;
                        }
                    }
                }
                
                $is_uploaded = !empty($matched_docs);
                if ($sug['is_mandatory'] && $is_uploaded) {
                    $mandatory_uploaded++;
                }
                
                $sug_copy = $sug;
                $sug_copy['uploaded'] = $is_uploaded;
                $sug_copy['matched_doc'] = $matched_docs[0] ?? null;
                $sug_copy['matched_docs'] = $matched_docs;
                $sug_copy['vault_match'] = $vault_match;
                $checklist[] = $sug_copy;
            }
            
            $app['phase2_checklist'] = $checklist;
            $app['phase2_suggestions'] = $suggestions;
            $app['phase2_completion'] = $mandatory_count > 0 ? round(($mandatory_uploaded / $mandatory_count) * 100) : (count($docs) > 0 ? 100 : 0);

            // disbursements
            $stmt = $db->prepare("SELECT * FROM applicant_disbursements WHERE applicant_id = ? ORDER BY phase_number ASC, id ASC");
            $stmt->execute([$id]);
            $disbs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($disbs) && !empty($app['disbursed_amount']) && floatval($app['disbursed_amount']) > 0) {
                $disbs[] = [
                    'id' => 0,
                    'applicant_id' => $id,
                    'phase_number' => 1,
                    'phase_name' => 'Full Disbursement (' . ($app['disbursing_bank'] ?? 'Bank') . ')',
                    'amount' => $app['disbursed_amount'],
                    'status' => $app['disbursement_status'] ?? 'Disbursed',
                    'disbursed_at' => $app['disbursed_date'] ?? date('Y-m-d'),
                    'remarks' => 'UTR: ' . ($app['disbursement_ref_no'] ?? 'N/A') . ' | Mode: ' . ($app['disbursement_mode'] ?? 'RTGS')
                ];
            }
            $app['disbursements'] = $disbs;
            
            // banks
            $stmt = $db->prepare("SELECT * FROM applicant_bank_assignments WHERE applicant_id = ?");
            $stmt->execute([$id]);
            $app['banks'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // timeline / activities
            $searchStr = "%id=$id%";
            $stmt = $db->prepare("SELECT * FROM activities WHERE action_link LIKE ? ORDER BY id DESC LIMIT 20");
            $stmt->execute([$searchStr]);
            $app['timeline'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // TAT Calculation
            $created_at = new DateTime($app['created_at']);
            $now = new DateTime();
            $diff = $now->diff($created_at);
            $app['tat_days'] = $diff->days;
            
            // Payout Distributions
            $stmt = $db->prepare("SELECT pd.*, u.name as payee_name FROM payout_distributions pd LEFT JOIN users u ON pd.payee_user_id = u.id WHERE pd.applicant_id = ?");
            $stmt->execute([$id]);
            $app['payouts'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return_json($app);
            break;

        case 'omni_search':
            $q = trim($_GET['q'] ?? '');
            $cat = trim($_GET['category'] ?? 'all');
            
            $like = empty($q) ? "%" : "%$q%";
            $results = [
                'applicants' => [],
                'pre_leads' => [],
                'leads' => [],
                'staff' => [],
                'bankers' => [],
                'referrals' => [],
                'field_visits' => [],
                'bank_logins' => [],
                'invoices' => []
            ];

            // 1. Applicants / LOS Search (Deep Attribute Matching)
            if ($cat === 'all' || $cat === 'clients') {
                try {
                    $role = strtolower($_SESSION['role'] ?? '');
                    $username = $_SESSION['username'] ?? '';
                    $isAdmin = ($role === 'admin' || strpos($role, 'admin') !== false);
                    
                    $sql = "SELECT id, loan_id, customer_name, mobile, pan_number as pan, aadhar_number as aadhaar, loan_type, sanction_bank, overall_status, loan_amount_requested, assigned_to, city, pincode 
                            FROM applicants 
                            WHERE (customer_name LIKE :q OR loan_id LIKE :q OR mobile LIKE :q OR pan_number LIKE :q OR aadhar_number LIKE :q OR sanction_bank LIKE :q OR overall_status LIKE :q OR loan_type LIKE :q OR city LIKE :q OR pincode LIKE :q OR email LIKE :q)";
                    
                    $params = [':q' => $like];
                    if (!$isAdmin) {
                        $sql .= " AND (assigned_to = :u OR added_by = :u OR assigned_to IS NULL OR assigned_to = '' OR assigned_to = 'UNASSIGNED')";
                        $params[':u'] = $username;
                    }
                    $sql .= " ORDER BY id DESC LIMIT 10";
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $results['applicants'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch(Exception $e) {}
            }

            // 2. Pre-Leads Search
            if ($cat === 'all' || $cat === 'pre_leads') {
                try {
                    $stmt = $db->prepare("SELECT id, name as customer_name, mobile, email, status, source, location as city, company_name 
                                           FROM pre_leads 
                                           WHERE name LIKE :q OR mobile LIKE :q OR email LIKE :q OR source LIKE :q OR location LIKE :q OR notes LIKE :q OR company_name LIKE :q 
                                           ORDER BY id DESC LIMIT 6");
                    $stmt->execute([':q' => $like]);
                    $results['pre_leads'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch(Exception $e) {}
            }

            // 3. CRM Managed Leads Search
            if ($cat === 'all' || $cat === 'leads' || $cat === 'pre_leads') {
                try {
                    $stmt = $db->prepare("SELECT id, lead_name as customer_name, mobile, email, stage as status, lead_source as source, location as city, company_name 
                                           FROM leads 
                                           WHERE lead_name LIKE :q OR mobile LIKE :q OR email LIKE :q OR company_name LIKE :q OR location LIKE :q OR lead_source LIKE :q 
                                           ORDER BY id DESC LIMIT 6");
                    $stmt->execute([':q' => $like]);
                    $results['leads'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch(Exception $e) {}
            }

            // 4. Staff / RMs / Field Staff / Employees Search
            if ($cat === 'all' || $cat === 'staff') {
                try {
                    $stmt = $db->prepare("SELECT id, emp_id, full_name as name, mobile, official_email as email, department, designation, access_role as role 
                                           FROM employees 
                                           WHERE full_name LIKE :q OR emp_id LIKE :q OR mobile LIKE :q OR official_email LIKE :q OR department LIKE :q OR designation LIKE :q 
                                           ORDER BY id DESC LIMIT 6");
                    $stmt->execute([':q' => $like]);
                    $emp_results = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    // User Accounts Fallback
                    $stmt_u = $db->prepare("SELECT id, username, name, role, staff_type 
                                             FROM users 
                                             WHERE name LIKE :q OR username LIKE :q OR role LIKE :q OR staff_type LIKE :q 
                                             ORDER BY id DESC LIMIT 6");
                    $stmt_u->execute([':q' => $like]);
                    $user_results = $stmt_u->fetchAll(PDO::FETCH_ASSOC);

                    $seen = [];
                    foreach ($emp_results as $emp) {
                        $seen[strtolower($emp['name'])] = true;
                        $results['staff'][] = [
                            'id' => $emp['id'],
                            'name' => $emp['full_name'] ?? $emp['name'],
                            'emp_id' => $emp['emp_id'],
                            'mobile' => $emp['mobile'],
                            'role' => ($emp['designation'] ? $emp['designation'] . ' (' . $emp['department'] . ')' : $emp['role']),
                            'type' => 'Employee'
                        ];
                    }
                    foreach ($user_results as $u) {
                        $uname = $u['name'] ?: $u['username'];
                        if (!isset($seen[strtolower($uname)])) {
                            $results['staff'][] = [
                                'id' => $u['id'],
                                'name' => $uname,
                                'emp_id' => '@' . $u['username'],
                                'mobile' => '',
                                'role' => ($u['role'] . ($u['staff_type'] ? ' - ' . $u['staff_type'] : '')),
                                'type' => 'User Account'
                            ];
                        }
                    }
                } catch(Exception $e) {}
            }

            // 5. Bankers Search
            if ($cat === 'all' || $cat === 'bankers') {
                try {
                    $stmt = $db->prepare("SELECT id, full_name as banker_name, bank_name, designation, contact_number as mobile, official_email as email, city 
                                           FROM bankers 
                                           WHERE full_name LIKE :q OR bank_name LIKE :q OR contact_number LIKE :q OR city LIKE :q OR designation LIKE :q OR official_email LIKE :q 
                                           ORDER BY id DESC LIMIT 6");
                    $stmt->execute([':q' => $like]);
                    $results['bankers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch(Exception $e) {}
            }

            // 6. Referrals / DSA Partners Search
            if ($cat === 'all' || $cat === 'referrals') {
                try {
                    $stmt = $db->prepare("SELECT id, full_name as name, referral_id as ref_code, mobile, city_state as city, referrer_type as type, pan_number 
                                           FROM referrals 
                                           WHERE full_name LIKE :q OR referral_id LIKE :q OR mobile LIKE :q OR city_state LIKE :q OR pan_number LIKE :q OR referrer_type LIKE :q 
                                           ORDER BY id DESC LIMIT 6");
                    $stmt->execute([':q' => $like]);
                    $results['referrals'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch(Exception $e) {}
            }

            // 7. Field Verification Visits Search
            if ($cat === 'all' || $cat === 'field_visits') {
                try {
                    $stmt = $db->prepare("SELECT id, person_name, firm_name, mobile, executive_name, city, profession, visit_date 
                                           FROM field_visits 
                                           WHERE person_name LIKE :q OR firm_name LIKE :q OR mobile LIKE :q OR executive_name LIKE :q OR city LIKE :q OR profession LIKE :q 
                                           ORDER BY id DESC LIMIT 6");
                    $stmt->execute([':q' => $like]);
                    $results['field_visits'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch(Exception $e) {}
            }

            // 8. Bank Logins Search
            if ($cat === 'all' || $cat === 'clients') {
                try {
                    $stmt = $db->prepare("SELECT b.id, b.applicant_id, b.bank_name, b.login_ref_no, b.status, a.customer_name, a.loan_id 
                                           FROM applicant_bank_logins b 
                                           JOIN applicants a ON a.id = b.applicant_id 
                                           WHERE b.bank_name LIKE :q OR b.login_ref_no LIKE :q OR b.status LIKE :q 
                                           ORDER BY b.id DESC LIMIT 5");
                    $stmt->execute([':q' => $like]);
                    $results['bank_logins'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch(Exception $e) {}
            }

            // 9. Bank Invoices Search
            if ($cat === 'all' || $cat === 'clients') {
                try {
                    $stmt = $db->prepare("SELECT i.id, i.applicant_id, i.invoice_number, i.bank_name, i.status, i.net_invoice_amount, a.customer_name, a.loan_id 
                                           FROM bank_payout_invoices i 
                                           JOIN applicants a ON a.id = i.applicant_id 
                                           WHERE i.invoice_number LIKE :q OR i.bank_name LIKE :q OR i.bank_utr LIKE :q OR i.status LIKE :q 
                                           ORDER BY i.id DESC LIMIT 5");
                    $stmt->execute([':q' => $like]);
                    $results['invoices'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch(Exception $e) {}
            }

            return_json(['success' => true, 'query' => $q, 'results' => $results]);
            break;
            
        case 'save_applicant_sanction':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request Method'], 405);
            
            $applicant_id = (int)($_POST['applicant_id'] ?? 0);
            if (!$applicant_id) return_json(['error' => 'Applicant ID is required.'], 400);

            $stmt = $db->prepare("SELECT * FROM applicants WHERE id = ?");
            $stmt->execute([$applicant_id]);
            $applicant = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$applicant) return_json(['error' => 'Applicant not found.'], 404);

            $sanctioned_amount = !empty($_POST['sanctioned_amount']) ? (float)$_POST['sanctioned_amount'] : 0;
            $sanction_bank = trim($_POST['sanction_bank'] ?? '');
            $sanction_date = trim($_POST['sanction_date'] ?? date('Y-m-d'));
            $sanction_roi = trim($_POST['sanction_roi'] ?? '');
            $sanction_tenure = trim($_POST['sanction_tenure'] ?? '');
            $sanction_emi = !empty($_POST['sanction_emi']) ? (float)$_POST['sanction_emi'] : 0;
            $sanction_ref_no = trim($_POST['sanction_ref_no'] ?? '');
            $sanction_notes = trim($_POST['sanction_notes'] ?? '');
            $send_email = !empty($_POST['send_email']) && ($_POST['send_email'] === '1' || $_POST['send_email'] === 'true' || $_POST['send_email'] === true);
            $email_custom_note = trim($_POST['email_custom_note'] ?? '');

            // Handle Sanction Letter File Upload
            $sanction_letter_path = $applicant['sanction_letter_path'] ?? null;
            if (isset($_FILES['sanction_letter_file']) && $_FILES['sanction_letter_file']['error'] === UPLOAD_ERR_OK) {
                $target_dir = __DIR__ . '/uploads/sanctions/';
                if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);

                $file_name = $_FILES['sanction_letter_file']['name'];
                $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $allowed = ['pdf', 'jpg', 'jpeg', 'png'];

                if (in_array($ext, $allowed)) {
                    $new_file_name = 'Sanction_' . $applicant['loan_id'] . '_' . time() . '.' . $ext;
                    $dest = $target_dir . $new_file_name;
                    if (move_uploaded_file($_FILES['sanction_letter_file']['tmp_name'], $dest)) {
                        $sanction_letter_path = 'uploads/sanctions/' . $new_file_name;
                    }
                }
            }

            try {
                $db->beginTransaction();

                // Determine overall status progression
                $new_status = $applicant['overall_status'];
                if ($new_status === 'Phase 1' || $new_status === 'Phase 2' || $new_status === 'Phase 3') {
                    $new_status = 'Phase 4'; // Progress to Customer Kundli & Disbursal
                }

                $upd = $db->prepare("
                    UPDATE applicants 
                    SET sanctioned_amount = ?, sanction_bank = ?, sanction_date = ?, 
                        sanction_roi = ?, sanction_tenure = ?, sanction_emi = ?, 
                        sanction_ref_no = ?, sanction_letter_path = ?, sanction_status = 'Sanctioned', 
                        sanction_notes = ?, overall_status = ?
                    WHERE id = ?
                ");
                $upd->execute([
                    $sanctioned_amount, $sanction_bank, $sanction_date, 
                    $sanction_roi, $sanction_tenure, $sanction_emi, 
                    $sanction_ref_no, $sanction_letter_path, $sanction_notes, 
                    $new_status, $applicant_id
                ]);

                // Log Activity
                $amt_formatted = number_format($sanctioned_amount, 2);
                log_activity("Bank Sanction Recorded: Rs. $amt_formatted by $sanction_bank (Ref: $sanction_ref_no) for {$applicant['customer_name']}", "search_track.php?id=$applicant_id");

                $db->commit();

                // Optionally dispatch Sanction Email
                $email_result = null;
                if ($send_email && !empty($applicant['email'])) {
                    $email_result = sendCustomerLoanEmail($applicant_id, 'Sanction', ['custom_note' => $email_custom_note]);
                }

                return_json([
                    'success' => true,
                    'message' => 'Loan sanction details saved successfully.' . ($send_email ? ' Customer sanction email dispatched.' : ''),
                    'sanction_letter_path' => $sanction_letter_path,
                    'email_result' => $email_result
                ]);
            } catch (Exception $e) {
                $db->rollBack();
                return_json(['error' => 'Database error: ' . $e->getMessage()], 500);
            }
            break;

        case 'send_customer_stage_email':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request Method'], 405);
            
            $applicant_id = (int)($_POST['applicant_id'] ?? 0);
            $stage_type = trim($_POST['stage_type'] ?? '');
            $custom_note = trim($_POST['custom_note'] ?? '');
            $custom_subject = trim($_POST['custom_subject'] ?? '');

            if (!$applicant_id) return_json(['error' => 'Applicant ID is required.'], 400);
            if (empty($stage_type)) return_json(['error' => 'Stage Type is required.'], 400);

            $recipient_email = trim($_POST['recipient_email'] ?? '');
            $include_form_pdf = !empty($_POST['include_form_pdf']);

            $res = sendCustomerLoanEmail($applicant_id, $stage_type, [
                'email' => $recipient_email,
                'custom_note' => $custom_note,
                'custom_subject' => $custom_subject,
                'include_form_pdf' => $include_form_pdf
            ]);

            return_json($res);
            break;

        case 'save_login_fee':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request Method'], 405);

            $applicant_id = (int)($_POST['applicant_id'] ?? 0);
            if (!$applicant_id) return_json(['error' => 'Applicant ID is required.'], 400);

            $stmt = $db->prepare("SELECT * FROM applicants WHERE id = ?");
            $stmt->execute([$applicant_id]);
            $applicant = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$applicant) return_json(['error' => 'Applicant not found.'], 404);

            $login_fee_amount = !empty($_POST['login_fee_amount']) ? (float)$_POST['login_fee_amount'] : 0;
            $login_fee_status = trim($_POST['login_fee_status'] ?? 'Pending');
            $login_fee_payment_mode = trim($_POST['login_fee_payment_mode'] ?? 'UPI');
            $login_fee_paid_date = trim($_POST['login_fee_paid_date'] ?? date('Y-m-d'));
            $login_fee_ref_no = trim($_POST['login_fee_ref_no'] ?? '');
            $login_fee_bank = trim($_POST['login_fee_bank'] ?? ($applicant['sanction_bank'] ?? ''));
            $login_fee_notes = trim($_POST['login_fee_notes'] ?? '');
            $send_email = !empty($_POST['send_email']) && ($_POST['send_email'] === '1' || $_POST['send_email'] === 'true' || $_POST['send_email'] === true);
            $email_custom_note = trim($_POST['email_custom_note'] ?? '');

            // Handle Receipt Upload
            $login_fee_receipt_path = $applicant['login_fee_receipt_path'] ?? null;
            if (isset($_FILES['login_fee_receipt_file']) && $_FILES['login_fee_receipt_file']['error'] === UPLOAD_ERR_OK) {
                $target_dir = __DIR__ . '/uploads/login_fees/';
                if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);

                $file_name = $_FILES['login_fee_receipt_file']['name'];
                $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $allowed = ['pdf', 'jpg', 'jpeg', 'png'];

                if (in_array($ext, $allowed)) {
                    $new_file_name = 'LoginFee_' . $applicant['loan_id'] . '_' . time() . '.' . $ext;
                    $dest = $target_dir . $new_file_name;
                    if (move_uploaded_file($_FILES['login_fee_receipt_file']['tmp_name'], $dest)) {
                        $login_fee_receipt_path = 'uploads/login_fees/' . $new_file_name;
                    }
                }
            }

            try {
                $db->beginTransaction();

                $upd = $db->prepare("
                    UPDATE applicants 
                    SET login_fee_amount = ?, login_fee_status = ?, login_fee_payment_mode = ?, 
                        login_fee_paid_date = ?, login_fee_ref_no = ?, login_fee_receipt_path = ?, 
                        login_fee_bank = ?, login_fee_notes = ?
                    WHERE id = ?
                ");
                $upd->execute([
                    $login_fee_amount, $login_fee_status, $login_fee_payment_mode,
                    $login_fee_paid_date, $login_fee_ref_no, $login_fee_receipt_path,
                    $login_fee_bank, $login_fee_notes, $applicant_id
                ]);

                $amt_fmt = number_format($login_fee_amount, 2);
                log_activity("Bank Login Fee recorded: Rs. $amt_fmt ($login_fee_status via $login_fee_payment_mode - Ref: $login_fee_ref_no) for {$applicant['customer_name']}", "search_track.php?id=$applicant_id");

                $db->commit();

                // Dispatch Login Fee Receipt Email if requested
                $email_result = null;
                if ($send_email && $login_fee_status === 'Paid' && !empty($applicant['email'])) {
                    $email_result = sendCustomerLoanEmail($applicant_id, 'login_fee', [
                        'custom_note' => $email_custom_note,
                        'login_fee_amount' => $login_fee_amount,
                        'login_fee_bank' => $login_fee_bank,
                        'login_fee_payment_mode' => $login_fee_payment_mode,
                        'login_fee_ref_no' => $login_fee_ref_no,
                        'login_fee_paid_date' => $login_fee_paid_date
                    ]);
                }

                return_json([
                    'success' => true,
                    'message' => 'Login fee record saved successfully.' . ($send_email ? ' Receipt email dispatched to customer.' : ''),
                    'login_fee_receipt_path' => $login_fee_receipt_path,
                    'email_result' => $email_result
                ]);
            } catch (Exception $e) {
                $db->rollBack();
                return_json(['error' => 'Database error: ' . $e->getMessage()], 500);
            }
            break;

        case 'save_bank_payout_recovery':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request Method'], 405);

            $applicant_id = (int)($_POST['applicant_id'] ?? 0);
            if (!$applicant_id) return_json(['error' => 'Applicant ID is required.'], 400);

            $stmt = $db->prepare("SELECT * FROM applicants WHERE id = ?");
            $stmt->execute([$applicant_id]);
            $applicant = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$applicant) return_json(['error' => 'Applicant not found.'], 404);

            $bank_payout_expected_pct = !empty($_POST['bank_payout_expected_pct']) ? (float)$_POST['bank_payout_expected_pct'] : 0;
            $bank_payout_expected_amount = !empty($_POST['bank_payout_expected_amount']) ? (float)$_POST['bank_payout_expected_amount'] : 0;
            $bank_payout_status = trim($_POST['bank_payout_status'] ?? 'Pending Claim');
            $bank_payout_received_amount = !empty($_POST['bank_payout_received_amount']) ? (float)$_POST['bank_payout_received_amount'] : 0;
            $bank_payout_received_date = !empty($_POST['bank_payout_received_date']) ? trim($_POST['bank_payout_received_date']) : null;
            $bank_payout_ref_no = trim($_POST['bank_payout_ref_no'] ?? '');
            $bank_payout_invoice_no = trim($_POST['bank_payout_invoice_no'] ?? '');
            $bank_payout_notes = trim($_POST['bank_payout_notes'] ?? '');

            try {
                $db->beginTransaction();

                $upd = $db->prepare("
                    UPDATE applicants 
                    SET bank_payout_expected_pct = ?, bank_payout_expected_amount = ?, 
                        bank_payout_status = ?, bank_payout_received_amount = ?, 
                        bank_payout_received_date = ?, bank_payout_ref_no = ?, 
                        bank_payout_invoice_no = ?, bank_payout_notes = ?
                    WHERE id = ?
                ");
                $upd->execute([
                    $bank_payout_expected_pct, $bank_payout_expected_amount,
                    $bank_payout_status, $bank_payout_received_amount,
                    $bank_payout_received_date, $bank_payout_ref_no,
                    $bank_payout_invoice_no, $bank_payout_notes, $applicant_id
                ]);

                $exp_fmt = number_format($bank_payout_expected_amount, 2);
                $rec_fmt = number_format($bank_payout_received_amount, 2);
                log_activity("Bank Commission Desk updated: Status=$bank_payout_status, Expected=Rs. $exp_fmt, Received=Rs. $rec_fmt for loan {$applicant['loan_id']}", "search_track.php?id=$applicant_id");

                $db->commit();

                return_json([
                    'success' => true,
                    'message' => 'Bank commission recovery details updated successfully.'
                ]);
            } catch (Exception $e) {
                $db->rollBack();
                return_json(['error' => 'Database error: ' . $e->getMessage()], 500);
            }
            break;

        case 'email_banker_payout_claim':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request Method'], 405);

            $applicant_id = (int)($_POST['applicant_id'] ?? 0);
            $banker_email = trim($_POST['banker_email'] ?? '');
            $banker_name = trim($_POST['banker_name'] ?? '');
            $subject = trim($_POST['subject'] ?? '');
            $body = trim($_POST['body'] ?? '');

            if (!$applicant_id || empty($banker_email) || empty($subject)) {
                return_json(['error' => 'Applicant ID, Banker Email, and Subject are required.'], 400);
            }

            $stmt = $db->prepare("SELECT * FROM applicants WHERE id = ?");
            $stmt->execute([$applicant_id]);
            $applicant = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$applicant) return_json(['error' => 'Applicant not found.'], 404);

            $profile_stmt = $db->query("SELECT * FROM company_profile LIMIT 1");
            $profile = $profile_stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $company_name = $profile['company_name'] ?? 'BFS Financial Services';

            $full_html = '
            <div style="font-family:\'Segoe UI\', Arial, sans-serif; font-size:14px; line-height:1.6; color:#334155; padding:20px; border:1px solid #e2e8f0; border-radius:8px; max-width:650px;">
                <div style="font-weight:bold; color:#0f172a; margin-bottom:12px; font-size:16px;">' . htmlspecialchars($company_name) . ' — Payout & Brokerage Desk</div>
                <div>' . $body . '</div>
                <div style="margin-top:20px; padding-top:14px; border-top:1px solid #e2e8f0; font-size:12px; color:#64748b;">
                    Loan Reference: <strong>' . htmlspecialchars($applicant['loan_id']) . '</strong> | Applicant: <strong>' . htmlspecialchars($applicant['customer_name']) . '</strong>
                </div>
            </div>';

            $sent = false;
            $delivery_note = '';
            // Attempt delivery
            if (!empty($profile['smtp_host']) && !empty($profile['smtp_username']) && !empty($profile['smtp_password'])) {
                try {
                    require_once __DIR__ . '/libs/PHPMailer/Exception.php';
                    require_once __DIR__ . '/libs/PHPMailer/PHPMailer.php';
                    require_once __DIR__ . '/libs/PHPMailer/SMTP.php';

                    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                    $mail->isSMTP();
                    $mail->Host = $profile['smtp_host'];
                    $mail->SMTPAuth = true;
                    $mail->Username = $profile['smtp_username'];
                    $mail->Password = $profile['smtp_password'];
                    $mail->SMTPSecure = ($profile['smtp_encryption'] === 'ssl') ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = (int)$profile['smtp_port'] ?: 587;

                    $mail->setFrom($profile['smtp_username'], $company_name);
                    $mail->addAddress($banker_email, $banker_name);
                    if (!empty($_POST['cc_email'])) {
                        $mail->addCC(trim($_POST['cc_email']));
                    }

                    $mail->isHTML(true);
                    $mail->Subject = $subject;
                    $mail->Body = $full_html;
                    $mail->send();
                    $sent = true;
                } catch (Exception $e) {
                    $delivery_note = 'SMTP Error: ' . $e->getMessage();
                }
            }

            if (!$sent) {
                $headers = "MIME-Version: 1.0\r\nContent-type:text/html;charset=UTF-8\r\nFrom: {$company_name} <info@bfscircle.com>\r\n";
                @mail($banker_email, $subject, $full_html, $headers);
                $sent = true;
            }

            log_activity("Payout Claim Email sent to Banker ($banker_email) for loan {$applicant['loan_id']}", "search_track.php?id=$applicant_id");

            return_json([
                'success' => true,
                'message' => "Payout claim email successfully dispatched to {$banker_email}!",
                'delivery_note' => $delivery_note
            ]);
            break;

        case 'get_payouts':
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = max(1, (int)($_GET['limit'] ?? 10));
            $offset = ($page - 1) * $limit;
            
            $where_clauses = ["1=1"];
            $params = [];

            if (!empty($_GET['status'])) {
                $st = strtolower(trim($_GET['status']));
                if ($st === 'paid') {
                    $where_clauses[] = "LOWER(pd.status) IN ('paid', 'disbursed', 'completed')";
                } else {
                    $where_clauses[] = "LOWER(pd.status) = ?";
                    $params[] = $st;
                }
            }
            if (!empty($_GET['payee_type'])) {
                $pt = strtolower(trim($_GET['payee_type']));
                if ($pt === 'employee' || $pt === 'staff') {
                    $where_clauses[] = "(LOWER(pd.payee_type) IN ('employee', 'staff', 'rm', 'user') OR LOWER(u.role) IN ('employee', 'staff', 'rm', 'admin'))";
                } elseif ($pt === 'partner') {
                    $where_clauses[] = "(LOWER(pd.payee_type) IN ('partner', 'dsa', 'ca', 'builder', 'agent') OR LOWER(u.role) IN ('partner', 'dsa', 'ca', 'builder', 'agent'))";
                } elseif ($pt === 'referral') {
                    $where_clauses[] = "(LOWER(pd.payee_type) = 'referral' OR (pd.referral_id IS NOT NULL AND pd.referral_id != ''))";
                } else {
                    $where_clauses[] = "LOWER(pd.payee_type) = ?";
                    $params[] = $pt;
                }
            }
            if (!empty($_GET['search'])) {
                $search_term = trim($_GET['search']);
                $clean_id = preg_replace('/[^0-9]/', '', $search_term);
                $search = "%" . $search_term . "%";

                $search_conds = [
                    "a.customer_name LIKE ?",
                    "a.loan_id LIKE ?",
                    "a.sanction_bank LIKE ?",
                    "u.name LIKE ?",
                    "u.username LIKE ?",
                    "pd.payee_name_override LIKE ?",
                    "pd.payee_mobile LIKE ?",
                    "pd.payee_bank_name LIKE ?",
                    "pd.transaction_ref LIKE ?",
                    "pd.payment_mode LIKE ?",
                    "pd.referral_id LIKE ?"
                ];
                array_push($params, $search, $search, $search, $search, $search, $search, $search, $search, $search, $search, $search);

                if (!empty($clean_id) && is_numeric($clean_id)) {
                    $search_conds[] = "pd.id = ?";
                    $params[] = intval($clean_id);
                }

                $where_clauses[] = "(" . implode(" OR ", $search_conds) . ")";
            }

            $where_sql = implode(" AND ", $where_clauses);
            
            // Metrics summary query
            $summary_sql = "SELECT 
                COALESCE(SUM(pd.gross_payout), 0) as total_gross,
                COALESCE(SUM(pd.tds_deducted), 0) as total_tds,
                COALESCE(SUM(CASE WHEN LOWER(pd.status) IN ('pending', 'approved', 'hold') THEN pd.net_payable ELSE 0 END), 0) as total_pending,
                COALESCE(SUM(CASE WHEN LOWER(pd.status) = 'paid' THEN pd.net_payable ELSE 0 END), 0) as total_paid,
                COALESCE(SUM(CASE WHEN LOWER(pd.status) = 'cancelled' THEN pd.net_payable ELSE 0 END), 0) as total_cancelled
                FROM payout_distributions pd
                LEFT JOIN applicants a ON pd.applicant_id = a.id
                LEFT JOIN users u ON pd.payee_user_id = u.id
                WHERE $where_sql";
            $stmtS = $db->prepare($summary_sql);
            $stmtS->execute($params);
            $summary = $stmtS->fetch(PDO::FETCH_ASSOC);

            // Get total count
            $count_sql = "SELECT COUNT(*) FROM payout_distributions pd
                          LEFT JOIN applicants a ON pd.applicant_id = a.id
                          LEFT JOIN users u ON pd.payee_user_id = u.id
                          WHERE $where_sql";
            $stmtC = $db->prepare($count_sql);
            $stmtC->execute($params);
            $total_records = (int)$stmtC->fetchColumn();
            
            // Get paginated data
            $sql = "SELECT pd.*, 
                           a.customer_name, a.loan_id, a.sanction_bank, a.loan_amount_requested,
                           u.name as u_name, u.role as u_role, u.username as u_email,
                           emp.mobile as emp_mobile, emp.bank_name as emp_bank, emp.bank_account_no as emp_acc, emp.bank_ifsc as emp_ifsc, emp.pan_number as emp_pan
                    FROM payout_distributions pd
                    LEFT JOIN applicants a ON pd.applicant_id = a.id
                    LEFT JOIN users u ON pd.payee_user_id = u.id
                    LEFT JOIN employees emp ON (emp.user_id = u.id OR emp.official_email = u.username)
                    WHERE $where_sql
                    ORDER BY pd.created_at DESC
                    LIMIT $limit OFFSET $offset";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $payouts_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $payouts = [];
            foreach ($payouts_raw as $p) {
                // Determine display payee name & role
                $payee_name = $p['u_name'] ?: ($p['payee_name_override'] ?: ($p['referral_id'] ? "Referral ({$p['referral_id']})" : ($p['recipient_id'] ? "Partner ({$p['recipient_id']})" : "Direct Payee")));
                $payee_role = $p['u_role'] ?: ucfirst($p['payee_type'] ?: 'Payee');
                $customer_name = $p['customer_name'] ?: ($p['applicant_id'] > 0 ? "Applicant #{$p['applicant_id']}" : "General Payout");
                $loan_id = $p['loan_id'] ?: ($p['referral_id'] ? "Ref: {$p['referral_id']}" : "N/A");
                
                // Banking details fallback
                $bank_name = $p['payee_bank_name'] ?: ($p['emp_bank'] ?: 'Not Specified');
                $account_no = $p['payee_account_no'] ?: ($p['emp_acc'] ?: 'N/A');
                $ifsc = $p['payee_ifsc'] ?: ($p['emp_ifsc'] ?: 'N/A');
                $pan = $p['payee_pan'] ?: ($p['emp_pan'] ?: 'N/A');
                $mobile = $p['payee_mobile'] ?: ($p['emp_mobile'] ?: 'N/A');

                $payouts[] = array_merge($p, [
                    'customer_name' => $customer_name,
                    'loan_id' => $loan_id,
                    'payee_name' => $payee_name,
                    'payee_role' => $payee_role,
                    'payee_email' => $p['u_email'] ?: '',
                    'bank_name' => $bank_name,
                    'bank_account_no' => $account_no,
                    'bank_ifsc' => $ifsc,
                    'bank_pan' => $pan,
                    'payee_mobile' => $mobile
                ]);
            }

            return_json([
                'success' => true,
                'payouts' => $payouts,
                'summary' => $summary,
                'total' => $total_records,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => max(1, ceil($total_records / $limit))
            ]);
            break;

        case 'add_payout':
            $applicant_id = intval($_POST['applicant_id'] ?? 0);
            $payee_user_id = intval($_POST['payee_user_id'] ?? 0);
            $payee_type = trim($_POST['payee_type'] ?? 'Employee');
            $custom_payee_name = trim($_POST['custom_payee_name'] ?? '');
            $total_loan_amount = floatval($_POST['total_loan_amount'] ?? 0);
            $commission_pct = floatval($_POST['commission_percentage'] ?? 0);
            $gross_payout = floatval($_POST['gross_payout'] ?? 0);
            $tds_pct = floatval($_POST['tds_percentage'] ?? 5.0);
            $tds_deducted = floatval($_POST['tds_deducted'] ?? 0);
            $payment_mode = trim($_POST['payment_mode'] ?? 'NEFT');
            $remarks = trim($_POST['remarks'] ?? '');

            if ($gross_payout <= 0 && $total_loan_amount > 0 && $commission_pct > 0) {
                $gross_payout = ($total_loan_amount * $commission_pct) / 100;
            }

            if ($tds_deducted <= 0 && $gross_payout > 0 && $tds_pct > 0) {
                $tds_deducted = ($gross_payout * $tds_pct) / 100;
            }

            $net_payable = $gross_payout - $tds_deducted;
            if ($net_payable < 0) $net_payable = 0;

            // Fetch payee bank details if available
            $payee_bank_name = ''; $payee_account_no = ''; $payee_ifsc = ''; $payee_pan = ''; $payee_mobile = '';
            if ($payee_user_id > 0) {
                $uStmt = $db->prepare("SELECT u.name, u.username, emp.mobile, emp.bank_name, emp.bank_account_no, emp.bank_ifsc, emp.pan_number 
                                      FROM users u LEFT JOIN employees emp ON (emp.user_id = u.id OR emp.official_email = u.username) WHERE u.id = ?");
                $uStmt->execute([$payee_user_id]);
                $uRow = $uStmt->fetch(PDO::FETCH_ASSOC);
                if ($uRow) {
                    $payee_bank_name = $uRow['bank_name'] ?? '';
                    $payee_account_no = $uRow['bank_account_no'] ?? '';
                    $payee_ifsc = $uRow['bank_ifsc'] ?? '';
                    $payee_pan = $uRow['pan_number'] ?? '';
                    $payee_mobile = $uRow['mobile'] ?? '';
                }
            }

            $creator = $_SESSION['username'] ?? 'Admin';

            $insStmt = $db->prepare("INSERT INTO payout_distributions 
                (applicant_id, payee_type, payee_user_id, payee_name_override, payee_mobile, payee_bank_name, payee_account_no, payee_ifsc, payee_pan,
                 total_loan_amount, commission_percentage, gross_payout, tds_percentage, tds_deducted, net_payable, payment_mode, remarks, created_by, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
            $insStmt->execute([
                $applicant_id, $payee_type, $payee_user_id, $custom_payee_name, $payee_mobile,
                $payee_bank_name, $payee_account_no, $payee_ifsc, $payee_pan,
                $total_loan_amount, $commission_pct, $gross_payout, $tds_pct, $tds_deducted, $net_payable,
                $payment_mode, $remarks, $creator
            ]);

            $p_id = $db->lastInsertId();
            log_activity("Created Payout #{$p_id} of ₹" . number_format($net_payable, 2) . " for Applicant ID #{$applicant_id}", "payout_distribution.php");

            return_json(['success' => true, 'payout_id' => $p_id, 'message' => 'Payout generated successfully!']);
            break;

        case 'update_payout_status_api':
            $payout_id = intval($_POST['payout_id'] ?? 0);
            $status = trim($_POST['status'] ?? 'Pending');
            $cancellation_reason = trim($_POST['cancellation_reason'] ?? '');
            $transaction_ref = trim($_POST['transaction_ref'] ?? '');
            $payment_mode = trim($_POST['payment_mode'] ?? 'NEFT');
            $paid_on = ($status === 'Paid') ? date('Y-m-d H:i:s') : null;

            if (!$payout_id) return_json(['error' => 'Payout ID required'], 400);

            $stmt = $db->prepare("UPDATE payout_distributions 
                                  SET status = ?, cancellation_reason = ?, transaction_ref = ?, payment_mode = ?, paid_on = ?, updated_at = CURRENT_TIMESTAMP 
                                  WHERE id = ?");
            $stmt->execute([$status, $cancellation_reason, $transaction_ref, $payment_mode, $paid_on, $payout_id]);

            // Notify payee on Paid
            if ($status === 'Paid') {
                $pd_stmt = $db->prepare("SELECT pd.*, a.loan_id, a.customer_name FROM payout_distributions pd LEFT JOIN applicants a ON a.id = pd.applicant_id WHERE pd.id = ?");
                $pd_stmt->execute([$payout_id]);
                $pd = $pd_stmt->fetch(PDO::FETCH_ASSOC);

                if ($pd && !empty($pd['payee_user_id'])) {
                    $net_fmt = '₹' . number_format((float)$pd['net_payable'], 0);
                    $loan_ref = $pd['loan_id'] ?: 'N/A';
                    $tx_ref = $transaction_ref ? " (UTR: {$transaction_ref})" : '';
                    $msg = "💰 Payout Credited! {$net_fmt} disbursed for loan {$loan_ref}{$tx_ref}. Mode: {$payment_mode}. Check your Wallet.";
                    
                    try {
                        $db->prepare("INSERT INTO notifications (user_id, message, type, is_read) VALUES (?, ?, 'payout', 0)")
                           ->execute([$pd['payee_user_id'], $msg]);
                    } catch (Exception $e) {}
                }
                log_activity("Payout #{$payout_id} marked as Paid with UTR {$transaction_ref}", "payout_distribution.php");
            } else {
                log_activity("Payout #{$payout_id} status updated to {$status}", "payout_distribution.php");
            }

            return_json(['success' => true, 'message' => "Payout #{$payout_id} status updated to {$status}!"]);
            break;

        case 'bulk_disburse_payouts':
            $raw_ids = $_POST['payout_ids'] ?? [];
            if (is_string($raw_ids)) $raw_ids = json_decode($raw_ids, true);
            $ids = array_map('intval', (array)$raw_ids);
            $ids = array_filter($ids);
            $common_utr = trim($_POST['utr_prefix'] ?? 'BULK-' . date('YmdHi'));
            $payment_mode = trim($_POST['payment_mode'] ?? 'NEFT');

            if (empty($ids)) return_json(['error' => 'No payouts selected'], 400);

            $count = 0;
            $now = date('Y-m-d H:i:s');
            foreach ($ids as $index => $pid) {
                $utr = $common_utr . '-' . ($index + 1);
                $stmt = $db->prepare("UPDATE payout_distributions SET status = 'Paid', transaction_ref = ?, payment_mode = ?, paid_on = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND status != 'Paid'");
                $stmt->execute([$utr, $payment_mode, $now, $pid]);
                if ($stmt->rowCount() > 0) $count++;
            }

            log_activity("Bulk disbursed {$count} payouts with UTR batch {$common_utr}", "payout_distribution.php");
            return_json(['success' => true, 'disbursed_count' => $count, 'message' => "Successfully disbursed {$count} payouts!"]);
            break;

        case 'get_payees_directory':
            $users = $db->query("SELECT u.id as user_id, u.name, u.username, u.role, 
                                        emp.id as employee_id, emp.mobile, emp.bank_name, emp.bank_account_no, emp.bank_ifsc, emp.bank_holder_name, emp.pan_number, emp.commission_rate
                                 FROM users u
                                 LEFT JOIN employees emp ON (emp.user_id = u.id OR emp.official_email = u.username)
                                 WHERE u.role IN ('Partner', 'Builder', 'CA', 'Agent', 'Staff', 'Admin', 'RM')
                                 ORDER BY u.name ASC")->fetchAll(PDO::FETCH_ASSOC);
            return_json(['success' => true, 'payees' => $users]);
            break;

        case 'update_payee_bank_details':
            $user_id = intval($_POST['user_id'] ?? 0);
            $bank_name = trim($_POST['bank_name'] ?? '');
            $account_no = trim($_POST['bank_account_no'] ?? '');
            $ifsc = trim($_POST['bank_ifsc'] ?? '');
            $holder = trim($_POST['bank_holder_name'] ?? '');
            $pan = trim($_POST['pan_number'] ?? '');
            $mobile = trim($_POST['mobile'] ?? '');

            if (!$user_id) return_json(['error' => 'User ID required'], 400);

            $uStmt = $db->prepare("SELECT username, name FROM users WHERE id = ?");
            $uStmt->execute([$user_id]);
            $uRow = $uStmt->fetch(PDO::FETCH_ASSOC);
            if (!$uRow) return_json(['error' => 'User not found'], 404);

            $empStmt = $db->prepare("SELECT id FROM employees WHERE user_id = ? OR official_email = ?");
            $empStmt->execute([$user_id, $uRow['username']]);
            $empId = $empStmt->fetchColumn();

            if ($empId) {
                $db->prepare("UPDATE employees SET bank_name=?, bank_account_no=?, bank_ifsc=?, bank_holder_name=?, pan_number=?, mobile=? WHERE id=?")
                   ->execute([$bank_name, $account_no, $ifsc, $holder, $pan, $mobile, $empId]);
            } else {
                $db->prepare("INSERT INTO employees (user_id, emp_id, full_name, official_email, mobile, department, designation, access_role, bank_name, bank_account_no, bank_ifsc, bank_holder_name, pan_number)
                              VALUES (?, ?, ?, ?, ?, 'Operations', 'Relationship Manager', 'Staff', ?, ?, ?, ?, ?)")
                   ->execute([$user_id, 'EMP-' . str_pad($user_id, 3, '0', STR_PAD_LEFT), $uRow['name'], $uRow['username'], $mobile, $bank_name, $account_no, $ifsc, $holder, $pan]);
            }

            log_activity("Updated Bank Details for {$uRow['name']}", "payout_distribution.php");
            return_json(['success' => true, 'message' => "Bank details updated for {$uRow['name']}!"]);
            break;

        case 'get_disbursed_bank_payout_tracker':
            // 1. Calculate cumulative business volume per bank for active disbursed cases
            $volStmt = $db->query("SELECT COALESCE(sanction_bank, 'Partner Bank') as b_name, SUM(COALESCE(loan_amount_requested, 0)) as total_vol 
                                   FROM applicants 
                                   WHERE overall_status IN ('Phase 4', 'Completed', 'Disbursed') 
                                   GROUP BY COALESCE(sanction_bank, 'Partner Bank')");
            $bank_volumes = [];
            while ($vRow = $volStmt->fetch(PDO::FETCH_ASSOC)) {
                $bank_volumes[$vRow['b_name']] = floatval($vRow['total_vol']);
            }

            // 2. Fetch all slabs
            $slabsStmt = $db->query("SELECT * FROM bank_commission_slabs ORDER BY min_volume ASC");
            $all_slabs = $slabsStmt->fetchAll(PDO::FETCH_ASSOC);

            // Helper lambda to find slab rate
            $getSlabRate = function($bank_name, $volume) use ($all_slabs) {
                $bank_slabs = array_filter($all_slabs, fn($s) => strcasecmp($s['bank_name'], $bank_name) === 0);
                if (empty($bank_slabs)) {
                    $bank_slabs = array_filter($all_slabs, fn($s) => strcasecmp($s['bank_name'], 'DEFAULT') === 0);
                }
                foreach ($bank_slabs as $s) {
                    if ($volume >= floatval($s['min_volume']) && $volume < floatval($s['max_volume'])) {
                        return floatval($s['commission_pct']);
                    }
                }
                return 1.50; // default fallback
            };

            $stmt = $db->query("SELECT a.id, a.loan_id, a.customer_name, a.loan_type, a.loan_amount_requested, 
                                       a.sanction_bank, a.overall_status, a.bank_payout_status, 
                                       a.bank_payout_received_amount, a.bank_payout_received_date, a.assigned_to,
                                       a.bank_commission_pct,
                                       u.name as rm_name
                                FROM applicants a
                                LEFT JOIN users u ON u.username = a.assigned_to
                                WHERE a.overall_status IN ('Phase 4', 'Completed', 'Disbursed')
                                ORDER BY a.id DESC");
            $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $total_disbursed = 0;
            $total_bank_received = 0;
            $total_bank_pending = 0;

            $tracker_files = [];
            foreach ($files as $f) {
                $disbursed_amt = floatval($f['loan_amount_requested']);
                $total_disbursed += $disbursed_amt;
                
                $bank_name = $f['sanction_bank'] ?: 'Partner Bank';
                $st = $f['bank_payout_status'] ?: 'Pending Claim';
                $rec_amt = floatval($f['bank_payout_received_amount']);
                
                $monthly_vol = $bank_volumes[$bank_name] ?? $disbursed_amt;
                $auto_slab_rate = $getSlabRate($bank_name, $monthly_vol);
                
                $comm_pct = floatval($f['bank_commission_pct']) > 0 ? floatval($f['bank_commission_pct']) : $auto_slab_rate;
                $is_override = floatval($f['bank_commission_pct']) > 0;

                $est_comm = ($disbursed_amt * $comm_pct) / 100;
                
                if ($st === 'Received') {
                    $total_bank_received += ($rec_amt > 0 ? $rec_amt : $est_comm);
                } else {
                    $total_bank_pending += $est_comm;
                }

                $tracker_files[] = array_merge($f, [
                    'sanction_bank' => $bank_name,
                    'bank_status' => $st,
                    'commission_pct' => $comm_pct,
                    'auto_slab_rate' => $auto_slab_rate,
                    'is_override' => $is_override,
                    'bank_volume' => $monthly_vol,
                    'estimated_commission' => $est_comm,
                    'rm_display' => $f['rm_name'] ?: ($f['assigned_to'] ?: 'Unassigned')
                ]);
            }

            return_json([
                'success' => true,
                'files' => $tracker_files,
                'bank_volumes' => $bank_volumes,
                'summary' => [
                    'total_disbursed' => $total_disbursed,
                    'total_bank_received' => $total_bank_received,
                    'total_bank_pending' => $total_bank_pending,
                    'count_pending' => count(array_filter($tracker_files, fn($x) => $x['bank_status'] !== 'Received'))
                ]
            ]);
            break;

        case 'update_case_bank_commission':
            $applicant_id = intval($_POST['applicant_id'] ?? 0);
            $comm_pct = floatval($_POST['commission_pct'] ?? 0);

            if ($applicant_id <= 0) return_json(['error' => 'Invalid Applicant ID'], 400);
            if ($comm_pct <= 0 || $comm_pct > 15) return_json(['error' => 'Commission rate must be between 0.1% and 15%'], 400);

            $stmt = $db->prepare("UPDATE applicants SET bank_commission_pct = ? WHERE id = ?");
            $stmt->execute([$comm_pct, $applicant_id]);

            // Also sync any draft/sent bank payout invoice for this applicant
            $invStmt = $db->prepare("SELECT * FROM bank_payout_invoices WHERE applicant_id = ? AND status != 'Received'");
            $invStmt->execute([$applicant_id]);
            $inv = $invStmt->fetch();

            if ($inv) {
                $disbursed = floatval($inv['disbursed_amount']);
                $gross = round(($disbursed * $comm_pct) / 100, 2);
                $gst = round($gross * 0.18, 2);
                $tds = round($gross * 0.02, 2);
                $net_receivable = round($gross + $gst - $tds, 2);

                $upInv = $db->prepare("UPDATE bank_payout_invoices SET commission_pct = ?, gross_amount = ?, gst_amount = ?, tds_amount = ?, net_receivable = ?, net_invoice_amount = ? WHERE id = ?");
                $upInv->execute([$comm_pct, $gross, $gst, $tds, $net_receivable, $net_receivable, $inv['id']]);
            }

            return_json(['success' => true, 'message' => "Bank commission rate updated to {$comm_pct}% successfully!"]);
            break;

        case 'get_bank_commission_slabs':
            $stmt = $db->query("SELECT * FROM bank_commission_slabs ORDER BY bank_name ASC, min_volume ASC");
            return_json(['success' => true, 'slabs' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'save_bank_commission_slab':
            $bank_name = trim($_POST['bank_name'] ?? '');
            $min_volume = floatval($_POST['min_volume'] ?? 0);
            $max_volume = floatval($_POST['max_volume'] ?? 999999999);
            $comm_pct = floatval($_POST['commission_pct'] ?? 0);

            if (empty($bank_name)) return_json(['error' => 'Bank Name is required'], 400);
            if ($comm_pct <= 0 || $comm_pct > 15) return_json(['error' => 'Invalid Commission %'], 400);

            $stmt = $db->prepare("INSERT INTO bank_commission_slabs (bank_name, loan_type, min_volume, max_volume, commission_pct) VALUES (?, 'All', ?, ?, ?)");
            $stmt->execute([$bank_name, $min_volume, $max_volume, $comm_pct]);

            return_json(['success' => true, 'message' => "Slab rule saved for {$bank_name}!"]);
            break;

        case 'record_bank_payout_received':
            $applicant_id = intval($_POST['applicant_id'] ?? 0);
            $received_amount = floatval($_POST['received_amount'] ?? 0);
            $received_date = trim($_POST['received_date'] ?? date('Y-m-d'));
            $bank_utr = trim($_POST['bank_utr'] ?? '');

            if (!$applicant_id) return_json(['error' => 'Applicant ID required'], 400);
            if ($received_amount <= 0) return_json(['error' => 'Received amount must be > 0'], 400);

            $stmt = $db->prepare("UPDATE applicants 
                                  SET bank_payout_status = 'Received', 
                                      bank_payout_received_amount = ?, 
                                      bank_payout_received_date = ? 
                                  WHERE id = ?");
            $stmt->execute([$received_amount, $received_date, $applicant_id]);

            log_activity("Recorded Bank Payout Received: ₹" . number_format($received_amount, 2) . " for Applicant ID #{$applicant_id}. UTR: {$bank_utr}", "payout_distribution.php");
            return_json(['success' => true, 'message' => 'Bank commission payment recorded successfully!']);
            break;
            
        case 'add_applicant_note':
            $id = (int)($_POST['id'] ?? 0);
            $note = trim($_POST['note'] ?? '');
            if($id && $note) {
                log_activity("Internal Note: " . $note, "search_track.php?id=$id");
                return_json(['success' => true]);
            }
            return_json(['error' => 'Invalid data'], 400);
            break;

        case 'check_duplicate_applicant':
            $mobile = preg_replace('/[^0-9]/', '', $_GET['mobile'] ?? '');
            $pan_number = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $_GET['pan'] ?? $_GET['pan_number'] ?? ''));
            $aadhar_number = preg_replace('/[^0-9]/', '', $_GET['aadhar'] ?? $_GET['aadhar_number'] ?? '');
            $exclude_id = (int)($_GET['exclude_id'] ?? 0);

            $matches = [];
            if (!empty($mobile) && strlen($mobile) == 10) {
                $sql = "SELECT id, loan_id, customer_name, mobile, pan_number, aadhar_number, overall_status, loan_type, loan_amount_requested, created_at, assigned_to FROM applicants WHERE mobile = ? AND overall_status != 'Rejected'";
                $params = [$mobile];
                if ($exclude_id > 0) { $sql .= " AND id != ?"; $params[] = $exclude_id; }
                $sql .= " LIMIT 3";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $found = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($found as $f) {
                    $f['matched_by'] = 'Mobile Number';
                    $matches[$f['id']] = $f;
                }
            }

            if (!empty($pan_number) && strlen($pan_number) == 10) {
                $sql = "SELECT id, loan_id, customer_name, mobile, pan_number, aadhar_number, overall_status, loan_type, loan_amount_requested, created_at, assigned_to FROM applicants WHERE pan_number = ? AND overall_status != 'Rejected'";
                $params = [$pan_number];
                if ($exclude_id > 0) { $sql .= " AND id != ?"; $params[] = $exclude_id; }
                $sql .= " LIMIT 3";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $found = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($found as $f) {
                    $f['matched_by'] = isset($matches[$f['id']]) ? ($matches[$f['id']]['matched_by'] . ' & PAN') : 'PAN Number';
                    $matches[$f['id']] = $f;
                }
            }

            if (!empty($aadhar_number) && strlen($aadhar_number) == 12) {
                $sql = "SELECT id, loan_id, customer_name, mobile, pan_number, aadhar_number, overall_status, loan_type, loan_amount_requested, created_at, assigned_to FROM applicants WHERE aadhar_number = ? AND overall_status != 'Rejected'";
                $params = [$aadhar_number];
                if ($exclude_id > 0) { $sql .= " AND id != ?"; $params[] = $exclude_id; }
                $sql .= " LIMIT 3";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $found = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($found as $f) {
                    $f['matched_by'] = isset($matches[$f['id']]) ? ($matches[$f['id']]['matched_by'] . ' & Aadhaar') : 'Aadhaar Number';
                    $matches[$f['id']] = $f;
                }
            }

            $matches_list = array_values($matches);
            return_json([
                'success' => true,
                'is_duplicate' => !empty($matches_list),
                'duplicates' => $matches_list
            ]);
            break;

        case 'reapply_applicant':
            $existing_id = (int)($_POST['existing_applicant_id'] ?? $_POST['applicant_id'] ?? 0);
            $loan_type = trim($_POST['loan_type'] ?? '');
            $loan_sub_type = trim($_POST['loan_sub_type'] ?? '');
            $loan_amount_requested = (float)($_POST['loan_amount_requested'] ?? 0);
            $tenure_years = (float)($_POST['tenure_years'] ?? 0);
            $tenure_months = (int)($_POST['tenure_months'] ?? ($tenure_years > 0 ? round($tenure_years * 12) : 0));
            $assigned_to = trim($_POST['assigned_to'] ?? '');
            $clone_documents = isset($_POST['clone_documents']) ? (int)$_POST['clone_documents'] : 1;
            $clone_coapplicants = isset($_POST['clone_coapplicants']) ? (int)$_POST['clone_coapplicants'] : 1;
            $notes = trim($_POST['notes'] ?? $_POST['pitch_notes'] ?? '');

            if (!$existing_id) {
                return_json(['error' => 'Missing existing applicant ID'], 400);
            }
            if (empty($loan_type) || $loan_amount_requested <= 0) {
                return_json(['error' => 'Please select Loan Type and enter a valid Loan Amount.'], 400);
            }
            if ($tenure_months <= 0) {
                $tenure_months = 36; // Default to 36 months if unselected
            }

            // 1. Fetch existing applicant profile
            $old_stmt = $db->prepare("SELECT * FROM applicants WHERE id = ?");
            $old_stmt->execute([$existing_id]);
            $old = $old_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$old) {
                return_json(['error' => 'Existing applicant record not found in database.'], 404);
            }

            // 2. Generate brand-new unique Loan Application ID
            $yearMonth = date('Ym');
            $base_count = (int)$db->query("SELECT COUNT(*) FROM applicants")->fetchColumn() + 1;
            $chk_stmt = $db->prepare("SELECT id FROM applicants WHERE loan_id = ?");
            do {
                $new_loan_id = "L" . $yearMonth . str_pad($base_count, 4, '0', STR_PAD_LEFT);
                $chk_stmt->execute([$new_loan_id]);
                $exists = $chk_stmt->fetchColumn();
                if ($exists) {
                    $base_count++;
                }
            } while ($exists);

            $now = date('Y-m-d H:i:s');
            $added_by = $_SESSION['username'] ?? 'system';
            $lead_source = !empty($notes) ? "Client Vault (Re-applied: " . substr($notes, 0, 50) . ")" : "Client Vault / Repeat Loan";
            
            // 3. Insert brand new applicant file reusing client identity & profile details
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
                !empty($assigned_to) ? $assigned_to : ($old['assigned_to'] ?? ''),
                $added_by,
                $now,
                $now
            ]);

            $new_applicant_id = $db->lastInsertId();

            // 4. Clone co-applicants if requested & present
            $cloned_co_count = 0;
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
                        ) VALUES (
                            ?, ?, ?, ?, ?, ?, ?, 
                            ?, ?, ?, ?, ?, ?, ?, 
                            ?, ?, ?
                        )
                    ");
                    foreach ($co_apps as $co) {
                        $ins_co->execute([
                            $new_applicant_id,
                            $co['is_financial'],
                            $co['relationship'],
                            $co['full_name'],
                            $co['mobile'],
                            $co['email'],
                            $co['dob'],
                            $co['pan_number'],
                            $co['aadhar_number'],
                            $co['same_address'],
                            $co['address'],
                            $co['pincode'],
                            $co['state'],
                            $co['city'],
                            $co['employment_type'],
                            $co['monthly_income'],
                            $co['current_emis']
                        ]);
                        $cloned_co_count++;
                    }
                }
            }

            // 5. Clone & Link existing verified documents from previous loan file
            $cloned_docs_count = 0;
            if ($clone_documents) {
                $docs_stmt = $db->prepare("SELECT * FROM applicant_documents WHERE applicant_id = ?");
                $docs_stmt->execute([$existing_id]);
                $old_docs = $docs_stmt->fetchAll(PDO::FETCH_ASSOC);

                if ($old_docs) {
                    $ins_doc = $db->prepare("
                        INSERT INTO applicant_documents (
                            applicant_id, client_mobile, client_pan, document_category, document_name, 
                            file_path, file_size, file_ext, status, notes, owner_type, owner_id
                        ) VALUES (
                            ?, ?, ?, ?, ?, 
                            ?, ?, ?, ?, ?, ?, ?
                        )
                    ");
                    foreach ($old_docs as $doc) {
                        $ins_doc->execute([
                            $new_applicant_id,
                            $old['mobile'],
                            $old['pan_number'],
                            $doc['document_category'],
                            $doc['document_name'],
                            $doc['file_path'],
                            $doc['file_size'] ?? 0,
                            $doc['file_ext'] ?? '',
                            $doc['status'] ?? 'Verified',
                            'Carried forward from Vault file #' . $old['loan_id'],
                            $doc['owner_type'] ?? 'Applicant',
                            $doc['owner_id'] ?? null
                        ]);
                        $cloned_docs_count++;
                    }
                }
            }

            log_activity("Activated New Loan {$new_loan_id} ({$loan_type} - ₹" . number_format($loan_amount_requested) . ") for vault client '{$old['customer_name']}' with {$cloned_docs_count} carried-forward documents", "applicant_documents.php?id={$new_applicant_id}");

            return_json([
                'success' => true,
                'id' => $new_applicant_id,
                'loan_id' => $new_loan_id,
                'customer_name' => $old['customer_name'],
                'loan_type' => $loan_type,
                'cloned_docs_count' => $cloned_docs_count,
                'redirect_url' => "applicant_documents.php?id={$new_applicant_id}",
                'message' => "New Loan Application #{$new_loan_id} successfully activated for {$old['customer_name']}! {$cloned_docs_count} verified documents carried forward."
            ]);
            break;


        case 'get_document_suggestions':
            $applicant_id = (int)($_GET['applicant_id'] ?? 0);
            $loan_type = trim($_GET['loan_type'] ?? '');
            $loan_sub_type = trim($_GET['loan_sub_type'] ?? '');
            $employment_type = trim($_GET['employment_type'] ?? 'Salaried');
            $has_coapp = !empty($_GET['has_coapp']) && ($_GET['has_coapp'] === '1' || $_GET['has_coapp'] === 'true' || (int)$_GET['has_coapp'] === 1);
            $coapp_is_fin = (!empty($_GET['coapp_is_financial']) && ($_GET['coapp_is_financial'] === '1' || $_GET['coapp_is_financial'] === 'Yes' || $_GET['coapp_is_financial'] === 'true')) ? 'Yes' : 'No';
            $co_apps = [];

            if ($applicant_id > 0) {
                $app_stmt = $db->prepare("SELECT * FROM applicants WHERE id = ?");
                $app_stmt->execute([$applicant_id]);
                $app = $app_stmt->fetch(PDO::FETCH_ASSOC);
                if ($app) {
                    $loan_type = $app['loan_type'] ?? $loan_type;
                    $loan_sub_type = $app['loan_sub_type'] ?? $loan_sub_type;
                    $employment_type = $app['employment_type'] ?? $employment_type;
                    
                    $co_stmt = $db->prepare("SELECT id, full_name, is_financial, relationship FROM co_applicants WHERE applicant_id = ?");
                    $co_stmt->execute([$applicant_id]);
                    $co_apps = $co_stmt->fetchAll(PDO::FETCH_ASSOC);
                }
            }

            if (empty($co_apps) && $has_coapp) {
                $co_apps = [
                    [
                        'id' => null,
                        'full_name' => 'Co-Applicant',
                        'is_financial' => $coapp_is_fin,
                        'relationship' => 'Co-Applicant'
                    ]
                ];
            }

            $suggestions = getLoanDocumentSuggestions($loan_type, $loan_sub_type, $employment_type, $co_apps);
            return_json(['success' => true, 'suggestions' => $suggestions]);
            break;

        case 'attach_vault_document':
            $target_applicant_id = (int)($_POST['applicant_id'] ?? 0);
            $source_doc_id = (int)($_POST['source_doc_id'] ?? 0);
            
            if (!$target_applicant_id || !$source_doc_id) {
                return_json(['error' => 'Missing applicant or document ID'], 400);
            }
            
            // Get source doc
            $src_stmt = $db->prepare("SELECT * FROM applicant_documents WHERE id = ?");
            $src_stmt->execute([$source_doc_id]);
            $src = $src_stmt->fetch(PDO::FETCH_ASSOC);
            if (!$src) {
                return_json(['error' => 'Source document not found in Vault'], 404);
            }
            
            // Get target applicant for mobile/pan
            $app_stmt = $db->prepare("SELECT mobile, pan_number FROM applicants WHERE id = ?");
            $app_stmt->execute([$target_applicant_id]);
            $target_app = $app_stmt->fetch(PDO::FETCH_ASSOC);
            
            $client_mobile = $target_app['mobile'] ?? ($src['client_mobile'] ?? '');
            $client_pan = $target_app['pan_number'] ?? ($src['client_pan'] ?? '');
            
            // Insert linked doc into target applicant
            $stmt = $db->prepare("
                INSERT INTO applicant_documents (applicant_id, client_mobile, client_pan, document_category, document_name, file_path, file_size, file_ext, status, notes, owner_type, owner_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Verified', 'Attached from Permanent Vault', ?, ?)
            ");
            $stmt->execute([
                $target_applicant_id,
                $client_mobile,
                $client_pan,
                $src['document_category'],
                $src['document_name'],
                $src['file_path'],
                $src['file_size'] ?? 0,
                $src['file_ext'] ?? '',
                $src['owner_type'] ?? 'Applicant',
                $src['owner_id'] ?? null
            ]);
            
            // Auto advance phase
            $db->query("UPDATE applicants SET overall_status = 'Phase 2' WHERE id = $target_applicant_id AND overall_status = 'Phase 1'");
            log_activity("Attached document from Vault: {$src['document_name']} ({$src['document_category']})", "applicant_documents.php?id=$target_applicant_id");
            
            return_json(['success' => true, 'message' => 'Document successfully linked from Client Vault!']);
            break;

        case 'get_applicant_documents':
            $applicant_id = (int)($_GET['applicant_id'] ?? 0);
            $stmt = $db->prepare("
                SELECT d.*, c.full_name as coapp_name 
                FROM applicant_documents d 
                LEFT JOIN co_applicants c ON d.owner_id = c.id 
                WHERE d.applicant_id = ? 
                ORDER BY d.id DESC
            ");
            $stmt->execute([$applicant_id]);
            return_json($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'upload_applicant_document':
            $applicant_id = (int)($_POST['applicant_id'] ?? 0);
            $category = trim($_POST['document_category'] ?? 'Other');
            $base_name = trim($_POST['document_name'] ?? 'Document');
            $added_by = $_SESSION['name'] ?? $_SESSION['username'] ?? 'Admin';
            
            if (!$applicant_id) {
                return_json(['error' => 'Applicant ID required'], 400);
            }

            // Standardize single and multi-file structures
            $files_to_process = [];
            if (isset($_FILES['document_file'])) {
                if (is_array($_FILES['document_file']['name'])) {
                    for ($i = 0; $i < count($_FILES['document_file']['name']); $i++) {
                        if ($_FILES['document_file']['error'][$i] === UPLOAD_ERR_OK) {
                            $files_to_process[] = [
                                'name' => $_FILES['document_file']['name'][$i],
                                'tmp_name' => $_FILES['document_file']['tmp_name'][$i],
                                'size' => $_FILES['document_file']['size'][$i],
                                'error' => $_FILES['document_file']['error'][$i],
                            ];
                        }
                    }
                } elseif ($_FILES['document_file']['error'] === UPLOAD_ERR_OK) {
                    $files_to_process[] = $_FILES['document_file'];
                }
            }
            if (isset($_FILES['document_files']) && is_array($_FILES['document_files']['name'])) {
                for ($i = 0; $i < count($_FILES['document_files']['name']); $i++) {
                    if ($_FILES['document_files']['error'][$i] === UPLOAD_ERR_OK) {
                        $files_to_process[] = [
                            'name' => $_FILES['document_files']['name'][$i],
                            'tmp_name' => $_FILES['document_files']['tmp_name'][$i],
                            'size' => $_FILES['document_files']['size'][$i],
                            'error' => $_FILES['document_files']['error'][$i],
                        ];
                    }
                }
            }

            if (empty($files_to_process)) {
                return_json(['error' => 'No valid file received. Please choose PDF, JPG, or PNG files to upload.'], 400);
            }

            $owner_val = trim($_POST['owner_val'] ?? 'Applicant_0');
            $owner_parts = explode('_', $owner_val);
            $owner_type = $owner_parts[0] ?? 'Applicant';
            $owner_id = (isset($owner_parts[1]) && $owner_parts[1] > 0) ? (int)$owner_parts[1] : null;
            if ($owner_type !== 'CoApplicant') {
                $owner_type = 'Applicant';
                $owner_id = null;
            }

            // Get client mobile & pan for lifelong vault index
            $app_stmt = $db->prepare("SELECT mobile, pan_number FROM applicants WHERE id = ?");
            $app_stmt->execute([$applicant_id]);
            $app_info = $app_stmt->fetch(PDO::FETCH_ASSOC);
            $client_mobile = $app_info['mobile'] ?? '';
            $client_pan = $app_info['pan_number'] ?? '';

            $upload_dir = 'uploads/documents/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

            $uploaded_count = 0;
            $total_files = count($files_to_process);

            foreach ($files_to_process as $idx => $f) {
                $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'])) {
                    continue;
                }

                $filename = uniqid('doc_') . '_' . $idx . '.' . $ext;
                $filepath = $upload_dir . $filename;
                $filesize = (int)($f['size'] ?? 0);

                // Build clean display name when uploading multiple files at once
                $doc_display_name = $base_name;
                if ($total_files > 1) {
                    $clean_orig_name = pathinfo($f['name'], PATHINFO_FILENAME);
                    $doc_display_name = $base_name . ' (' . $clean_orig_name . ')';
                }

                if (move_uploaded_file($f['tmp_name'], $filepath)) {
                    $stmt = $db->prepare("
                        INSERT INTO applicant_documents (applicant_id, client_mobile, client_pan, document_category, document_name, file_path, file_size, file_ext, status, owner_type, owner_id) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?, ?)
                    ");
                    $stmt->execute([$applicant_id, $client_mobile, $client_pan, $category, $doc_display_name, $filepath, $filesize, $ext, $owner_type, $owner_id]);
                    $uploaded_count++;
                }
            }

            if ($uploaded_count > 0) {
                $db->query("UPDATE applicants SET overall_status = 'Phase 2' WHERE id = $applicant_id AND overall_status = 'Phase 1'");
                log_activity("Uploaded $uploaded_count document(s): $base_name ($category)", "applicant_documents.php?id=$applicant_id");
                
                $msg = $uploaded_count === 1 
                    ? 'Document uploaded successfully and indexed in Permanent Vault' 
                    : "$uploaded_count documents uploaded successfully and indexed in Permanent Vault!";
                return_json(['success' => true, 'message' => $msg, 'uploaded_count' => $uploaded_count]);
            }

            return_json(['error' => 'Failed to save files. Please check file permissions or format.'], 500);
            break;

        case 'delete_applicant_document':
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $db->prepare("SELECT file_path, applicant_id, document_name FROM applicant_documents WHERE id = ?");
            $stmt->execute([$id]);
            $doc = $stmt->fetch();
            
            if ($doc) {
                if (file_exists($doc['file_path'])) {
                    // Check if file is shared with another vault record before unlinking
                    $other_count = $db->query("SELECT COUNT(*) FROM applicant_documents WHERE file_path = " . $db->quote($doc['file_path']) . " AND id != $id")->fetchColumn();
                    if ($other_count == 0) {
                        unlink($doc['file_path']);
                    }
                }
                $db->prepare("DELETE FROM applicant_documents WHERE id = ?")->execute([$id]);
                log_activity("Deleted document: {$doc['document_name']}", "applicant_documents.php?id={$doc['applicant_id']}");
                return_json(['success' => true]);
            }
            return_json(['error' => 'Not found'], 404);
            break;

        case 'update_document_status':
            $id = (int)($_POST['id'] ?? 0);
            $status = trim($_POST['status'] ?? 'Verified');
            $notes = trim($_POST['notes'] ?? '');
            $user = $_SESSION['username'] ?? 'Admin';
            $now = date('Y-m-d H:i:s');
            
            $stmt = $db->prepare("UPDATE applicant_documents SET status = ?, notes = ?, verified_by = ?, verified_at = ? WHERE id = ?");
            $stmt->execute([$status, $notes, $user, $now, $id]);
            
            // log activity
            $stmt2 = $db->prepare("SELECT applicant_id, document_name FROM applicant_documents WHERE id = ?");
            $stmt2->execute([$id]);
            if ($doc = $stmt2->fetch()) {
                log_activity("Updated status to $status for document: {$doc['document_name']}", "applicant_documents.php?id={$doc['applicant_id']}");
            }
            return_json(['success' => true, 'message' => "Document status updated to $status"]);
            break;


        case 'get_states':
            $stmt = $db->query("SELECT DISTINCT state FROM pincodes ORDER BY state");
            return_json(['success' => true, 'states' => $stmt->fetchAll(PDO::FETCH_COLUMN)]);
            break;
            
        case 'get_cities':
            $state = trim($_GET['state'] ?? '');
            $stmt = $db->prepare("SELECT DISTINCT city FROM pincodes WHERE state = ? ORDER BY city");
            $stmt->execute([$state]);
            return_json(['success' => true, 'cities' => $stmt->fetchAll(PDO::FETCH_COLUMN)]);
            break;
            
        case 'verify_pincode':
            $pin = trim($_GET['pin'] ?? '');
            $stmt = $db->prepare("SELECT city, state FROM pincodes WHERE pincode = ?");
            $stmt->execute([$pin]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($res) {
                return_json(['success' => true, 'city' => $res['city'], 'state' => $res['state']]);
            }
            return_json(['error' => 'Not found'], 404);
            break;


        case 'get_applicant_disbursements':
            $applicant_id = (int)($_GET['applicant_id'] ?? 0);
            $stmt = $db->prepare("SELECT * FROM applicant_disbursements WHERE applicant_id = ? ORDER BY phase_number ASC");
            $stmt->execute([$applicant_id]);
            return_json($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'save_applicant_disbursement':
            $applicant_id = (int)($_POST['applicant_id'] ?? 0);
            $phase_number = (int)($_POST['phase_number'] ?? 0);
            $phase_name = trim($_POST['phase_name'] ?? '');
            $amount = (float)($_POST['amount'] ?? 0);
            $status = trim($_POST['status'] ?? 'Pending');
            $disbursed_date = trim($_POST['disbursed_date'] ?? '');
            $remarks = trim($_POST['remarks'] ?? '');
            
            if (!$applicant_id || !$phase_number || !$amount) {
                return_json(['error' => 'Missing fields'], 400);
            }
            
            $stmt = $db->prepare("INSERT INTO applicant_disbursements (applicant_id, phase_number, phase_name, amount, status, disbursed_date, remarks) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$applicant_id, $phase_number, $phase_name, $amount, $status, $disbursed_date, $remarks]);
            
            log_activity("Added disbursement Phase $phase_number: $amount ($status)", "applicant_disbursements.php?id=$applicant_id");
            return_json(['success' => true]);
            break;

        case 'delete_applicant_disbursement':
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $db->prepare("SELECT applicant_id, phase_number FROM applicant_disbursements WHERE id = ?");
            $stmt->execute([$id]);
            $disb = $stmt->fetch();
            if ($disb) {
                $db->prepare("DELETE FROM applicant_disbursements WHERE id = ?")->execute([$id]);
                log_activity("Deleted disbursement Phase {$disb['phase_number']}", "applicant_disbursements.php?id={$disb['applicant_id']}");
                return_json(['success' => true]);
            }
            return_json(['error' => 'Not found'], 404);
            break;

        case 'get_applicant_banks':
            $applicant_id = (int)($_GET['applicant_id'] ?? 0);
            $stmt = $db->prepare("SELECT * FROM applicant_bank_assignments WHERE applicant_id = ? ORDER BY id DESC");
            $stmt->execute([$applicant_id]);
            return_json($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'assign_applicant_bank':
            $applicant_id = (int)($_POST['applicant_id'] ?? 0);
            $bank_name = trim($_POST['bank_name'] ?? '');
            $status = trim($_POST['status'] ?? 'Pending');
            $notes = trim($_POST['notes'] ?? '');
            $assigned_by = $_SESSION['name'] ?? $_SESSION['username'] ?? 'Admin';
            
            if (!$applicant_id || !$bank_name) return_json(['error' => 'Missing fields'], 400);
            
            $stmt = $db->prepare("INSERT INTO applicant_bank_assignments (applicant_id, bank_name, status, rejection_reason, assigned_by) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$applicant_id, $bank_name, $status, $notes, $assigned_by]);
            
            log_activity("Assigned file to bank: $bank_name ($status)", "applicant_bank_assign.php?id=$applicant_id");
            return_json(['success' => true]);
            break;


        
        case 'add_referral':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Method'], 405);
            
            $referrer_type = trim($_POST['referrer_type'] ?? '');
            $full_name = trim($_POST['full_name'] ?? '');
            $dob = trim($_POST['dob'] ?? '');
            $mobile = trim($_POST['mobile'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $city_state = trim($_POST['city_state'] ?? '');
            $account_name = trim($_POST['account_name'] ?? '');
            $bank_name = trim($_POST['bank_name'] ?? '');
            $account_number = trim($_POST['account_number'] ?? '');
            $ifsc_code = trim($_POST['ifsc_code'] ?? '');
            $upi_id = trim($_POST['upi_id'] ?? '');
            $commission_rate = trim($_POST['commission_rate'] ?? '');
            $payout_frequency = trim($_POST['payout_frequency'] ?? '');
            $pan_number = trim($_POST['pan_number'] ?? '');
            $aadhar_number = trim($_POST['aadhar_number'] ?? '');
            $assigned_rm = trim($_POST['assigned_rm'] ?? '');
            $status = trim($_POST['status'] ?? 'Active');
            
            if (!$referrer_type || !$full_name || !$mobile) {
                return_json(['error' => 'Type, Name and Mobile are required.'], 400);
            }
            
            $bank_doc_path = '';
            $pan_doc_path = '';
            $aadhar_doc_path = '';
            
            if (!is_dir('uploads/referrals')) {
                @mkdir('uploads/referrals', 0777, true);
            }
            
            if (isset($_FILES['bank_document']) && $_FILES['bank_document']['error'] == 0) {
                $ext = pathinfo($_FILES['bank_document']['name'], PATHINFO_EXTENSION);
                $bank_doc_path = 'uploads/referrals/' . uniqid('bank_') . '.' . $ext;
                move_uploaded_file($_FILES['bank_document']['tmp_name'], $bank_doc_path);
            }
            if (isset($_FILES['pan_document']) && $_FILES['pan_document']['error'] == 0) {
                $ext = pathinfo($_FILES['pan_document']['name'], PATHINFO_EXTENSION);
                $pan_doc_path = 'uploads/referrals/' . uniqid('pan_') . '.' . $ext;
                move_uploaded_file($_FILES['pan_document']['tmp_name'], $pan_doc_path);
            }
            if (isset($_FILES['aadhar_document']) && $_FILES['aadhar_document']['error'] == 0) {
                $ext = pathinfo($_FILES['aadhar_document']['name'], PATHINFO_EXTENSION);
                $aadhar_doc_path = 'uploads/referrals/' . uniqid('aadhar_') . '.' . $ext;
                move_uploaded_file($_FILES['aadhar_document']['tmp_name'], $aadhar_doc_path);
            }
            
            $prefix = strtoupper(substr($referrer_type, 0, 3));
            $referral_id = "REF-" . $prefix . "-" . date('Ymd') . rand(100, 999);
            
            try {
                $stmt = $db->prepare("INSERT INTO referrals (referral_id, referrer_type, full_name, dob, mobile, email, city_state, account_name, bank_name, account_number, ifsc_code, upi_id, commission_rate, payout_frequency, pan_number, aadhar_number, bank_document_path, pan_document_path, aadhar_document_path, assigned_rm, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$referral_id, $referrer_type, $full_name, $dob, $mobile, $email, $city_state, $account_name, $bank_name, $account_number, $ifsc_code, $upi_id, $commission_rate, $payout_frequency, $pan_number, $aadhar_number, $bank_doc_path, $pan_doc_path, $aadhar_doc_path, $assigned_rm, $status]);
                $new_ref_pk = $db->lastInsertId();
                
                // Also create a user account for them
                $username = trim($_POST['username'] ?? '');
                $password = trim($_POST['password'] ?? '');
                if (!empty($username) && !empty($password)) {
                    $role = 'Partner';
                    if ($referrer_type === 'Individual Agent') {
                        $role = 'Agent';
                    } elseif ($referrer_type === 'Builder / Real Estate') {
                        $role = 'Builder';
                    } elseif ($referrer_type === 'Chartered Accountant (CA)') {
                        $role = 'CA';
                    } elseif ($referrer_type === 'Financial Advisor / DSA') {
                        $role = 'Partner';
                    }
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    try {
                        $ustmt = $db->prepare("INSERT INTO users (username, name, password_hash, role, plain_password, has_dashboard) VALUES (?, ?, ?, ?, ?, 1)");
                        $ustmt->execute([$username, $full_name, $hash, $role, $password]);
                        $new_uid = $db->lastInsertId();
                        $db->prepare("UPDATE referrals SET user_id = ? WHERE id = ?")->execute([$new_uid, $new_ref_pk]);
                    } catch (Exception $ue) {
                        // If username already exists, link existing user
                        $existing_uid = $db->prepare("SELECT id FROM users WHERE username = ?");
                        $existing_uid->execute([$username]);
                        $e_uid = $existing_uid->fetchColumn();
                        if ($e_uid) {
                            $db->prepare("UPDATE referrals SET user_id = ? WHERE id = ?")->execute([$e_uid, $new_ref_pk]);
                        }
                    }
                }

                log_activity("Added new referral partner: $full_name", "referrals_list.php");
                return_json(['success' => true, 'message' => 'Referral Partner added successfully!', 'referral_id' => $referral_id]);
            } catch (Exception $e) {
                return_json(['error' => 'Database error: ' . $e->getMessage()], 400);
            }

        case 'edit_referral':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Method'], 405);
            $id = $_POST['id'] ?? '';
            if (!$id) return_json(['error' => 'Missing ID'], 400);
            
            $referrer_type = trim($_POST['referrer_type'] ?? '');
            $full_name = trim($_POST['full_name'] ?? '');
            $dob = trim($_POST['dob'] ?? '');
            $mobile = trim($_POST['mobile'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $city_state = trim($_POST['city_state'] ?? '');
            $account_name = trim($_POST['account_name'] ?? '');
            $bank_name = trim($_POST['bank_name'] ?? '');
            $account_number = trim($_POST['account_number'] ?? '');
            $ifsc_code = trim($_POST['ifsc_code'] ?? '');
            $upi_id = trim($_POST['upi_id'] ?? '');
            $commission_rate = trim($_POST['commission_rate'] ?? '');
            $payout_frequency = trim($_POST['payout_frequency'] ?? '');
            $pan_number = trim($_POST['pan_number'] ?? '');
            $aadhar_number = trim($_POST['aadhar_number'] ?? '');
            $assigned_rm = trim($_POST['assigned_rm'] ?? '');
            $status = trim($_POST['status'] ?? 'Active');
            
            $stmt = $db->prepare("SELECT bank_document_path, pan_document_path, aadhar_document_path FROM referrals WHERE id = ?");
            $stmt->execute([$id]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$existing) return_json(['error' => 'Not found'], 404);
            
            $bank_doc_path = $existing['bank_document_path'];
            $pan_doc_path = $existing['pan_document_path'];
            $aadhar_doc_path = $existing['aadhar_document_path'] ?? '';
            
            if (!is_dir('uploads/referrals')) {
                @mkdir('uploads/referrals', 0777, true);
            }
            
            if (isset($_FILES['bank_document']) && $_FILES['bank_document']['error'] == 0) {
                $ext = pathinfo($_FILES['bank_document']['name'], PATHINFO_EXTENSION);
                $bank_doc_path = 'uploads/referrals/' . uniqid('bank_') . '.' . $ext;
                move_uploaded_file($_FILES['bank_document']['tmp_name'], $bank_doc_path);
            }
            if (isset($_FILES['pan_document']) && $_FILES['pan_document']['error'] == 0) {
                $ext = pathinfo($_FILES['pan_document']['name'], PATHINFO_EXTENSION);
                $pan_doc_path = 'uploads/referrals/' . uniqid('pan_') . '.' . $ext;
                move_uploaded_file($_FILES['pan_document']['tmp_name'], $pan_doc_path);
            }
            if (isset($_FILES['aadhar_document']) && $_FILES['aadhar_document']['error'] == 0) {
                $ext = pathinfo($_FILES['aadhar_document']['name'], PATHINFO_EXTENSION);
                $aadhar_doc_path = 'uploads/referrals/' . uniqid('aadhar_') . '.' . $ext;
                move_uploaded_file($_FILES['aadhar_document']['tmp_name'], $aadhar_doc_path);
            }
            $extra_data = [
                'company_name' => trim($_POST['company_name'] ?? ''),
                'rera_no' => trim($_POST['rera_no'] ?? ''),
                'gst_no' => trim($_POST['gst_no'] ?? ''),
                'website' => trim($_POST['website'] ?? ''),
                'registration_number' => trim($_POST['registration_number'] ?? ''),
                'occupation' => trim($_POST['occupation'] ?? '')
            ];
            $extra_json = json_encode($extra_data);

            try {
                $stmt = $db->prepare("UPDATE referrals SET referrer_type=?, full_name=?, dob=?, mobile=?, email=?, city_state=?, account_name=?, bank_name=?, account_number=?, ifsc_code=?, upi_id=?, commission_rate=?, payout_frequency=?, pan_number=?, aadhar_number=?, bank_document_path=?, pan_document_path=?, aadhar_document_path=?, assigned_rm=?, status=?, extra_details=? WHERE id=?");
                $stmt->execute([$referrer_type, $full_name, $dob, $mobile, $email, $city_state, $account_name, $bank_name, $account_number, $ifsc_code, $upi_id, $commission_rate, $payout_frequency, $pan_number, $aadhar_number, $bank_doc_path, $pan_doc_path, $aadhar_doc_path, $assigned_rm, $status, $extra_json, $id]);
                
                // Sync user account active status
                $is_act = ($status === 'Active') ? 1 : 0;
                $ref_obj = $db->query("SELECT user_id, referral_id, mobile, email FROM referrals WHERE id = {$id}")->fetch(PDO::FETCH_ASSOC);
                $u_ids = [];
                if (!empty($ref_obj['user_id'])) $u_ids[] = (int)$ref_obj['user_id'];
                if (!empty($ref_obj['referral_id'])) {
                    $u1 = $db->prepare("SELECT id FROM users WHERE LOWER(username) = LOWER(?)");
                    $u1->execute([$ref_obj['referral_id']]);
                    while ($r = $u1->fetch(PDO::FETCH_ASSOC)) $u_ids[] = (int)$r['id'];
                }
                if (!empty($ref_obj['mobile'])) {
                    try {
                        $u2 = $db->prepare("SELECT id FROM users WHERE username = ? OR mobile = ?");
                        $u2->execute([$ref_obj['mobile'], $ref_obj['mobile']]);
                        while ($r = $u2->fetch(PDO::FETCH_ASSOC)) $u_ids[] = (int)$r['id'];
                    } catch (Exception $e) {
                        $u2 = $db->prepare("SELECT id FROM users WHERE username = ?");
                        $u2->execute([$ref_obj['mobile']]);
                        while ($r = $u2->fetch(PDO::FETCH_ASSOC)) $u_ids[] = (int)$r['id'];
                    }
                }
                $u_ids = array_unique(array_filter($u_ids));
                foreach ($u_ids as $uid) {
                    if ($is_act === 0) {
                        $db->prepare("UPDATE users SET is_active = 0, session_token = NULL WHERE id = ?")->execute([$uid]);
                    } else {
                        $db->prepare("UPDATE users SET is_active = 1 WHERE id = ?")->execute([$uid]);
                    }
                }

                log_activity("Updated referral partner: $full_name (Status: $status)", "referrals_list.php");
                return_json(['success' => true, 'message' => 'Referral Partner updated successfully!']);
            } catch (Exception $e) {
                return_json(['error' => 'Database error: ' . $e->getMessage()], 400);
            }


        case 'get_active_referrals':
            // Get from referrals table
            $stmt = $db->query("SELECT referral_id, full_name, referrer_type as type FROM referrals WHERE status = 'Active'");
            $refs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Also append users who act as partners (DSA, CA, Builder, etc.)
            $stmt2 = $db->query("SELECT username as referral_id, name as full_name, role as type FROM users WHERE is_active = 1 AND role IN ('DSA', 'CA', 'Builder', 'Partner')");
            $users = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            
            // Merge and remove duplicates if any (though unlikely to overlap if ID structures are different)
            $all = array_merge($refs, $users);
            return_json($all);
            break;



        case 'update_bank_assignment':
            $assignment_id = (int)($_POST['assignment_id'] ?? 0);
            $status = trim($_POST['status'] ?? '');
            $reason = trim($_POST['rejection_reason'] ?? '');
            if (!$assignment_id || !$status) return_json(['error' => 'Missing fields'], 400);

            $stage = trim($_POST['underwriting_stage'] ?? 'Logged In');
            $sanctioned_amount = !empty($_POST['sanctioned_amount']) ? (float)$_POST['sanctioned_amount'] : 0;
            $interest_rate = !empty($_POST['interest_rate']) ? (float)$_POST['interest_rate'] : 0;
            $tenure_months = !empty($_POST['tenure_months']) ? (int)$_POST['tenure_months'] : 0;
            $emi = !empty($_POST['emi']) ? (float)$_POST['emi'] : 0;
            $processing_fee = !empty($_POST['processing_fee']) ? (float)$_POST['processing_fee'] : 0;

            // Handle Sanction Letter File Upload if provided
            $sanction_letter_path = null;
            if (isset($_FILES['sanction_letter']) && $_FILES['sanction_letter']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/documents/sanction_letters/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                $ext = strtolower(pathinfo($_FILES['sanction_letter']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'])) {
                    $dest_filename = 'sanction_' . $assignment_id . '_' . time() . '.' . $ext;
                    $dest_path = $upload_dir . $dest_filename;
                    if (move_uploaded_file($_FILES['sanction_letter']['tmp_name'], $dest_path)) {
                        $sanction_letter_path = $dest_path;
                    }
                }
            }

            if ($sanction_letter_path) {
                $stmt = $db->prepare("UPDATE applicant_bank_assignments SET status = ?, rejection_reason = ?, underwriting_stage = ?, sanctioned_amount = ?, interest_rate = ?, tenure_months = ?, emi = ?, processing_fee = ?, sanction_letter_path = ? WHERE id = ?");
                $ok = $stmt->execute([$status, $reason, $stage, $sanctioned_amount, $interest_rate, $tenure_months, $emi, $processing_fee, $sanction_letter_path, $assignment_id]);
            } else {
                $stmt = $db->prepare("UPDATE applicant_bank_assignments SET status = ?, rejection_reason = ?, underwriting_stage = ?, sanctioned_amount = ?, interest_rate = ?, tenure_months = ?, emi = ?, processing_fee = ? WHERE id = ?");
                $ok = $stmt->execute([$status, $reason, $stage, $sanctioned_amount, $interest_rate, $tenure_months, $emi, $processing_fee, $assignment_id]);
            }

            if ($ok) {
                // If marked as Approved and sanctioned amount exists, optionally auto-sync to applicant if none accepted yet
                if ($status === 'Approved') {
                    $assign_row = $db->query("SELECT * FROM applicant_bank_assignments WHERE id = $assignment_id")->fetch(PDO::FETCH_ASSOC);
                    if ($assign_row) {
                        $app_id = (int)$assign_row['applicant_id'];
                        $check_accepted = $db->query("SELECT COUNT(*) FROM applicant_bank_assignments WHERE applicant_id = $app_id AND is_accepted = 1")->fetchColumn();
                        if ($check_accepted == 0 && $sanctioned_amount > 0) {
                            $db->prepare("UPDATE applicants SET sanction_bank = ?, sanctioned_amount = ?, interest_rate = ?, tenure_months = ?, emi = ?, sanction_letter_path = COALESCE(?, sanction_letter_path) WHERE id = ?")
                               ->execute([$assign_row['bank_name'], $sanctioned_amount, $interest_rate, $tenure_months, $emi, $sanction_letter_path, $app_id]);
                        }
                    }
                }
                return_json(['success' => true, 'message' => 'Bank submission and status updated successfully!']);
            }
            return_json(['error' => 'Database error'], 500);
            break;

        case 'accept_bank_sanction':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request Method'], 405);
            $assignment_id = (int)($_POST['assignment_id'] ?? 0);
            $applicant_id = (int)($_POST['applicant_id'] ?? 0);
            if (!$assignment_id || !$applicant_id) return_json(['error' => 'Missing ID'], 400);

            $stmt = $db->prepare("SELECT * FROM applicant_bank_assignments WHERE id = ? AND applicant_id = ?");
            $stmt->execute([$assignment_id, $applicant_id]);
            $assign = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$assign) return_json(['error' => 'Bank assignment not found'], 404);

            // Reset all assignments for this applicant to is_accepted = 0
            $db->prepare("UPDATE applicant_bank_assignments SET is_accepted = 0 WHERE applicant_id = ?")->execute([$applicant_id]);
            // Set this assignment to is_accepted = 1 and status = Approved
            $db->prepare("UPDATE applicant_bank_assignments SET is_accepted = 1, status = 'Approved' WHERE id = ?")->execute([$assignment_id]);

            // Sync to applicants master table
            $upd = $db->prepare("UPDATE applicants SET 
                sanction_bank = ?, 
                sanctioned_amount = CASE WHEN ? > 0 THEN ? ELSE sanctioned_amount END, 
                interest_rate = CASE WHEN ? > 0 THEN ? ELSE interest_rate END, 
                tenure_months = CASE WHEN ? > 0 THEN ? ELSE tenure_months END, 
                emi = CASE WHEN ? > 0 THEN ? ELSE emi END, 
                sanction_letter_path = COALESCE(?, sanction_letter_path), 
                sanction_status = 'Approved', 
                sanction_accepted = 1, 
                sanction_accepted_at = datetime('now'),
                overall_status = 'Phase 3' 
                WHERE id = ?");
            $upd->execute([
                $assign['bank_name'],
                (float)$assign['sanctioned_amount'], (float)$assign['sanctioned_amount'],
                (float)$assign['interest_rate'], (float)$assign['interest_rate'],
                (int)$assign['tenure_months'], (int)$assign['tenure_months'],
                (float)$assign['emi'], (float)$assign['emi'],
                $assign['sanction_letter_path'],
                $applicant_id
            ]);

            log_activity("Accepted official sanction offer from " . $assign['bank_name'], "applicant_disbursements.php?id=$applicant_id");
            return_json(['success' => true, 'message' => 'Sanction offer from ' . $assign['bank_name'] . ' accepted and locked into official profile!']);
            break;

        case 'save_bank_feedback':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request'], 405);
            $applicant_id = (int)($_POST['applicant_id'] ?? 0);
            if (!$applicant_id) return_json(['error' => 'Missing ID'], 400);

            $cibil = trim($_POST['cibil_score'] ?? '');
            $sanctioned = trim($_POST['sanctioned_amount'] ?? '');
            $roi = trim($_POST['interest_rate'] ?? '');
            $tenure = trim($_POST['tenure_months'] ?? '');
            $emi = trim($_POST['emi'] ?? '');
            $sanction_date = trim($_POST['sanction_date'] ?? date('Y-m-d'));
            $sanction_bank = trim($_POST['sanction_bank'] ?? '');
            $sanction_ref_no = trim($_POST['sanction_ref_no'] ?? '');

            // Handle Sanction Letter File Upload if provided
            $sanction_letter_path = null;
            if (isset($_FILES['sanction_letter']) && $_FILES['sanction_letter']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/documents/sanction_letters/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                $ext = strtolower(pathinfo($_FILES['sanction_letter']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'])) {
                    $dest_filename = 'sanction_app_' . $applicant_id . '_' . time() . '.' . $ext;
                    $dest_path = $upload_dir . $dest_filename;
                    if (move_uploaded_file($_FILES['sanction_letter']['tmp_name'], $dest_path)) {
                        $sanction_letter_path = $dest_path;
                    }
                }
            }

            if ($sanction_letter_path) {
                $stmt = $db->prepare("UPDATE applicants SET cibil_score = ?, sanctioned_amount = ?, interest_rate = ?, tenure_months = ?, emi = ?, sanction_date = ?, sanction_bank = ?, sanction_ref_no = ?, sanction_letter_path = ?, sanction_status = 'Approved', sanction_accepted = 1, overall_status = 'Phase 3' WHERE id = ?");
                $ok = $stmt->execute([$cibil, $sanctioned, $roi, $tenure, $emi, $sanction_date, $sanction_bank, $sanction_ref_no, $sanction_letter_path, $applicant_id]);
            } else {
                $stmt = $db->prepare("UPDATE applicants SET cibil_score = ?, sanctioned_amount = ?, interest_rate = ?, tenure_months = ?, emi = ?, sanction_date = ?, sanction_bank = ?, sanction_ref_no = ?, sanction_status = 'Approved', sanction_accepted = 1, overall_status = 'Phase 3' WHERE id = ?");
                $ok = $stmt->execute([$cibil, $sanctioned, $roi, $tenure, $emi, $sanction_date, $sanction_bank, $sanction_ref_no, $applicant_id]);
            }

            if ($ok) {
                log_activity("Updated Banker Feedback & Official Sanction terms", "applicant_disbursements.php?id=$applicant_id");
                return_json(['success' => true, 'message' => 'Official sanction terms saved successfully!']);
            }
            return_json(['error' => 'Database error'], 500);
            break;


        case 'email_banker_zip':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request Method'], 405);
            $applicant_id = (int)($_POST['applicant_id'] ?? 0);
            $banker_email = trim($_POST['banker_email'] ?? '');
            $cc_email = trim($_POST['cc_email'] ?? '');
            $subject = trim($_POST['subject'] ?? '');
            $body = trim($_POST['body'] ?? '');
            
            if (!$applicant_id || !$banker_email || !$subject) {
                return_json(['error' => 'Missing required fields'], 400);
            }
            
            $stmt = $db->prepare("SELECT * FROM applicants WHERE id = ?");
            $stmt->execute([$applicant_id]);
            $applicant = $stmt->fetch();
            if (!$applicant) return_json(['error' => 'Applicant not found'], 404);
            
            $loan_id = $applicant['loan_id'] ?: 'L'.$applicant_id;
            $customer_name = preg_replace('/[^A-Za-z0-9_-]/', '_', $applicant['customer_name']);
            $zip_filename = "Bundle_" . $loan_id . "_" . $customer_name . ".zip";
            
            $upload_dir = 'uploads/documents/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $zip_path = $upload_dir . time() . '_' . $zip_filename;
            
            $zip = new ZipArchive();
            if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
                return_json(['error' => 'Failed to generate ZIP'], 500);
            }
            
            // 1. Text Summary
            $profile_text = "APPLICANT PROFILE\n=================\n";
            $profile_text .= "Name: " . $applicant['customer_name'] . "\nLoan Amount: INR " . $applicant['loan_amount_requested'] . "\n";
            $zip->addFromString("00_Applicant_Summary.txt", $profile_text);
            
            // 2. Add Documents
            $stmt_docs = $db->prepare("SELECT * FROM applicant_documents WHERE applicant_id = ?");
            $stmt_docs->execute([$applicant_id]);
            foreach ($stmt_docs->fetchAll(PDO::FETCH_ASSOC) as $doc) {
                if (file_exists($doc['file_path'])) {
                    $zip->addFile($doc['file_path'], "Documents/" . basename($doc['file_path']));
                }
            }
            $zip->close();
            
            // 3. Send via PHPMailer
            require_once __DIR__ . '/libs/PHPMailer/Exception.php';
            require_once __DIR__ . '/libs/PHPMailer/PHPMailer.php';
            require_once __DIR__ . '/libs/PHPMailer/SMTP.php';
            
            $company_profile = $db->query("SELECT * FROM company_profile LIMIT 1")->fetch();
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            try {
                if (!empty($company_profile['smtp_host'])) {
                    $mail->isSMTP();
                    $mail->Host = $company_profile['smtp_host'];
                    $mail->SMTPAuth = true;
                    $mail->Username = $company_profile['smtp_username'];
                    $mail->Password = $company_profile['smtp_password'];
                    if (strtolower($company_profile['smtp_encryption']) === 'ssl') {
                        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                    } else {
                        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                    }
                    $mail->Port = (int)$company_profile['smtp_port'];
                }
                $mail->setFrom($company_profile['smtp_username'] ?? 'admin@BFS Financial Services.com', $company_profile['company_name'] ?? 'BFS Financial Services');
                $mail->addAddress($banker_email);
                if (!empty($cc_email)) {
                    $mail->addCC($cc_email);
                }
                $mail->Subject = $subject;
                $mail->isHTML(true);
                $mail->Body = $body;
                $mail->addAttachment($zip_path, $zip_filename);
                $mail->send();
                
                unlink($zip_path); // clean up
                
                // Auto-create a bank submission record
                $posted_bank = trim($_POST['bank_name'] ?? '');
                $bank_name = !empty($posted_bank) ? $posted_bank : "Banker (" . $banker_email . ")";
                $assigned_by = $_SESSION['name'] ?? $_SESSION['username'] ?? 'Admin';
                
                $stmt_assign = $db->prepare("INSERT INTO applicant_bank_assignments (applicant_id, bank_name, status, rejection_reason, assigned_by) VALUES (?, ?, 'Pending', 'Sent via Email', ?)");
                $stmt_assign->execute([$applicant_id, $bank_name, $assigned_by]);

                log_activity("Emailed ZIP bundle to banker ($banker_email)", "applicant_disbursements.php?id=$applicant_id");
                return_json(['success' => true, 'message' => 'Email sent successfully with ZIP attachment!']);
            } catch (Exception $e) {
                if (file_exists($zip_path)) unlink($zip_path);
                return_json(['error' => 'Mail error: ' . $mail->ErrorInfo], 500);
            }
            break;


        
        case 'delete_referral':
            if (($_SESSION['role'] ?? '') !== 'Admin') return_json(['error' => 'Unauthorized'], 403);
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) return_json(['error' => 'Invalid ID'], 400);
            try {
                $stmt = $db->prepare("SELECT full_name FROM referrals WHERE id = ?");
                $stmt->execute([$id]);
                $ref = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $del = $db->prepare("DELETE FROM referrals WHERE id = ?");
                $del->execute([$id]);
                
                if ($ref) {
                    log_activity("Deleted referral partner: " . $ref['full_name'], "referrals_list.php");
                }
                return_json(['success' => true, 'message' => 'Referral deleted successfully.']);
            } catch (Exception $e) {
                return_json(['error' => 'Database error: ' . $e->getMessage()], 400);
            }
            break;

        case 'toggle_referral_status':
            if (($_SESSION['role'] ?? '') !== 'Admin') return_json(['error' => 'Unauthorized. Admin access required.'], 403);
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) return_json(['error' => 'Invalid Partner ID'], 400);

            try {
                $stmt = $db->prepare("SELECT * FROM referrals WHERE id = ?");
                $stmt->execute([$id]);
                $ref = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$ref) return_json(['error' => 'Partner not found'], 404);

                $current_status = $ref['status'] ?? 'Active';
                $requested_status = trim($_POST['status'] ?? '');
                
                if (!empty($requested_status)) {
                    $new_status = (strtolower($requested_status) === 'active') ? 'Active' : 'Inactive';
                } else {
                    $new_status = (strtolower($current_status) === 'active') ? 'Inactive' : 'Active';
                }
                $is_active_val = ($new_status === 'Active') ? 1 : 0;

                // 1. Update referrals table
                $upd = $db->prepare("UPDATE referrals SET status = ? WHERE id = ?");
                $upd->execute([$new_status, $id]);

                // 2. Synchronize linked user in users table
                $user_ids = [];
                if (!empty($ref['user_id'])) {
                    $user_ids[] = (int)$ref['user_id'];
                }
                if (!empty($ref['referral_id'])) {
                    $u1 = $db->prepare("SELECT id FROM users WHERE LOWER(username) = LOWER(?)");
                    $u1->execute([$ref['referral_id']]);
                    while ($row = $u1->fetch(PDO::FETCH_ASSOC)) $user_ids[] = (int)$row['id'];
                }
                if (!empty($ref['mobile'])) {
                    try {
                        $u2 = $db->prepare("SELECT id FROM users WHERE username = ? OR mobile = ?");
                        $u2->execute([$ref['mobile'], $ref['mobile']]);
                        while ($row = $u2->fetch(PDO::FETCH_ASSOC)) $user_ids[] = (int)$row['id'];
                    } catch (Exception $e) {
                        $u2 = $db->prepare("SELECT id FROM users WHERE username = ?");
                        $u2->execute([$ref['mobile']]);
                        while ($row = $u2->fetch(PDO::FETCH_ASSOC)) $user_ids[] = (int)$row['id'];
                    }
                }
                if (!empty($ref['email'])) {
                    $u3 = $db->prepare("SELECT id FROM users WHERE LOWER(email) = LOWER(?)");
                    $u3->execute([$ref['email']]);
                    while ($row = $u3->fetch(PDO::FETCH_ASSOC)) $user_ids[] = (int)$row['id'];
                }
                $user_ids = array_unique(array_filter($user_ids));

                foreach ($user_ids as $uid) {
                    if ($is_active_val === 0) {
                        $db->prepare("UPDATE users SET is_active = 0, session_token = NULL WHERE id = ?")->execute([$uid]);
                    } else {
                        $db->prepare("UPDATE users SET is_active = 1 WHERE id = ?")->execute([$uid]);
                    }
                }

                log_activity("Partner status toggled to {$new_status}: " . $ref['full_name'], "referrals_list.php");
                return_json([
                    'success' => true,
                    'new_status' => $new_status,
                    'is_active' => ($new_status === 'Active'),
                    'partner_name' => $ref['full_name'],
                    'message' => "Partner '{$ref['full_name']}' is now {$new_status}." . ($new_status === 'Inactive' ? " Dashboard login access disabled." : " Dashboard login access enabled.")
                ]);
            } catch (Exception $e) {
                return_json(['error' => 'Database error: ' . $e->getMessage()], 400);
            }
            break;
            
        case 'add_custom_bank':
            if (($_SESSION['role'] ?? '') !== 'Admin') return_json(['error' => 'Unauthorized'], 403);
            $type = trim($_POST['bank_type'] ?? '');
            $name = trim($_POST['bank_name'] ?? '');
            
            if (!$type || !$name) return_json(['error' => 'Missing data'], 400);
            
            $js_file = __DIR__ . '/assets/js/banks_directory.js';
            if (!file_exists($js_file)) return_json(['error' => 'JS file not found'], 404);
            
            $content = file_get_contents($js_file);
            // We need to inject the new bank into the array for the specific type
            $search = '"' . $type . '": [';
            if (strpos($content, $search) !== false) {
                // Check if it already exists
                if (stripos($content, '"' . $name . '"') !== false) {
                    return_json(['success' => true, 'message' => 'Already exists']);
                }
                
                $replace = $search . "\n        \"" . $name . "\",";
                $new_content = str_replace($search, $replace, $content);
                file_put_contents($js_file, $new_content);
                return_json(['success' => true, 'message' => 'Bank added successfully']);
            } else {
                return_json(['error' => 'Bank type not found in directory'], 404);
            }

        case 'add_banker':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Method'], 405);
            $full_name = trim($_POST['full_name'] ?? '');
            $bank_type = trim($_POST['bank_type'] ?? 'Public Sector Bank');
            $bank_name = trim($_POST['bank_name'] ?? '');
            $designation = trim($_POST['designation'] ?? '');
            $state = trim($_POST['state'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $pincode = trim($_POST['pincode'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $ifsc_code = trim($_POST['ifsc_code'] ?? '');
            $contact_number = trim($_POST['contact_number'] ?? '');
            $official_email = trim($_POST['official_email'] ?? '');
            $bank_official_email = trim($_POST['bank_official_email'] ?? '');
            $dsa_documents_drive_link = trim($_POST['dsa_documents_drive_link'] ?? '');
            $loan_category = isset($_POST['loan_category']) ? (is_array($_POST['loan_category']) ? implode(',', $_POST['loan_category']) : trim($_POST['loan_category'])) : '';
            $coverage_type = trim($_POST['coverage_type'] ?? '');
            $coverage_details = trim($_POST['coverage_details'] ?? '');
            $min_limit = (float)($_POST['min_loan_limit'] ?? 0);
            $max_limit = (float)($_POST['max_loan_limit'] ?? 0);
            $min_roi = (float)($_POST['min_roi'] ?? 0);
            $max_roi = (float)($_POST['max_roi'] ?? 0);
            $dsa_code = trim($_POST['dsa_code'] ?? '');
            $employee_id = $_SESSION['user_id'] ?? 1;

            // Handle file upload for DSA undertaking document
            $dsa_undertaking_file = '';
            if (isset($_FILES['dsa_undertaking_file']) && $_FILES['dsa_undertaking_file']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['dsa_undertaking_file']['name'], PATHINFO_EXTENSION));
                $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
                if (in_array($ext, $allowed)) {
                    $new_name = 'dsa_doc_' . time() . '_' . rand(100, 999) . '.' . $ext;
                    $dest_dir = __DIR__ . '/uploads/bankers/';
                    if (!is_dir($dest_dir)) @mkdir($dest_dir, 0777, true);
                    if (move_uploaded_file($_FILES['dsa_undertaking_file']['tmp_name'], $dest_dir . $new_name)) {
                        $dsa_undertaking_file = 'uploads/bankers/' . $new_name;
                    }
                }
            }

            if(empty($full_name) || empty($bank_name)) return_json(['error' => 'Name and Bank Name are required.'], 400);

            $stmt = $db->prepare("INSERT INTO bankers (full_name, bank_type, bank_name, designation, state, city, pincode, address, ifsc_code, contact_number, official_email, bank_official_email, dsa_documents_drive_link, dsa_undertaking_file, loan_category, coverage_type, coverage_details, min_loan_limit, max_loan_limit, min_roi, max_roi, employee_id, dsa_code, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active')");
            if($stmt->execute([$full_name, $bank_type, $bank_name, $designation, $state, $city, $pincode, $address, $ifsc_code, $contact_number, $official_email, $bank_official_email, $dsa_documents_drive_link, $dsa_undertaking_file, $loan_category, $coverage_type, $coverage_details, $min_limit, $max_limit, $min_roi, $max_roi, $employee_id, $dsa_code])) {
                
                if (!empty($ifsc_code)) {
                    $check = $db->prepare("SELECT ifsc FROM ifsc_master WHERE ifsc = ?");
                    $check->execute([$ifsc_code]);
                    if (!$check->fetch()) {
                        $ins = $db->prepare("INSERT INTO ifsc_master (ifsc, bank, branch, address, city, state) VALUES (?, ?, ?, ?, ?, ?)");
                        $ins->execute([$ifsc_code, $bank_name, $city . ' BRANCH', $address, $city, $state]);
                    }
                }

                if (function_exists('send_bank_status_email')) {
                    send_bank_status_email($db, $bank_name, $full_name, $official_email, $bank_official_email, 'Active', 'New Bank relationship added to system by Bhardwaj Financial Services.');
                }

                log_activity("Added Banker: $full_name ($bank_name)", "bankers_list.php");
                return_json(['success' => true, 'message' => 'Banker relationship added successfully & activation email dispatched!']);
            }
            return_json(['error' => 'Failed to add banker'], 500);
            break;

        case 'update_banker':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Method'], 405);
            $id = (int)($_POST['id'] ?? 0);
            $full_name = trim($_POST['full_name'] ?? '');
            $bank_type = trim($_POST['bank_type'] ?? 'Public Sector Bank');
            $bank_name = trim($_POST['bank_name'] ?? '');
            $designation = trim($_POST['designation'] ?? '');
            $state = trim($_POST['state'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $pincode = trim($_POST['pincode'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $ifsc_code = trim($_POST['ifsc_code'] ?? '');
            $contact_number = trim($_POST['contact_number'] ?? '');
            $official_email = trim($_POST['official_email'] ?? '');
            $bank_official_email = trim($_POST['bank_official_email'] ?? '');
            $dsa_documents_drive_link = trim($_POST['dsa_documents_drive_link'] ?? '');
            $loan_category = isset($_POST['loan_category']) ? (is_array($_POST['loan_category']) ? implode(',', $_POST['loan_category']) : trim($_POST['loan_category'])) : '';
            $coverage_type = trim($_POST['coverage_type'] ?? '');
            $coverage_details = trim($_POST['coverage_details'] ?? '');
            $min_limit = (float)($_POST['min_loan_limit'] ?? 0);
            $max_limit = (float)($_POST['max_loan_limit'] ?? 0);
            $min_roi = (float)($_POST['min_roi'] ?? 0);
            $max_roi = (float)($_POST['max_roi'] ?? 0);
            $dsa_code = trim($_POST['dsa_code'] ?? '');
            $status = trim($_POST['status'] ?? 'Active');

            if(!$id || empty($full_name) || empty($bank_name)) return_json(['error' => 'Invalid input.'], 400);

            // Existing record
            $cur_stmt = $db->prepare("SELECT dsa_undertaking_file FROM bankers WHERE id = ?");
            $cur_stmt->execute([$id]);
            $cur_rec = $cur_stmt->fetch(PDO::FETCH_ASSOC);
            $dsa_undertaking_file = $cur_rec['dsa_undertaking_file'] ?? '';

            if (isset($_FILES['dsa_undertaking_file']) && $_FILES['dsa_undertaking_file']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['dsa_undertaking_file']['name'], PATHINFO_EXTENSION));
                $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
                if (in_array($ext, $allowed)) {
                    $new_name = 'dsa_doc_' . time() . '_' . rand(100, 999) . '.' . $ext;
                    $dest_dir = __DIR__ . '/uploads/bankers/';
                    if (!is_dir($dest_dir)) @mkdir($dest_dir, 0777, true);
                    if (move_uploaded_file($_FILES['dsa_undertaking_file']['tmp_name'], $dest_dir . $new_name)) {
                        $dsa_undertaking_file = 'uploads/bankers/' . $new_name;
                    }
                }
            }

            $stmt = $db->prepare("UPDATE bankers SET full_name=?, bank_type=?, bank_name=?, designation=?, state=?, city=?, pincode=?, address=?, ifsc_code=?, contact_number=?, official_email=?, bank_official_email=?, dsa_documents_drive_link=?, dsa_undertaking_file=?, loan_category=?, coverage_type=?, coverage_details=?, min_loan_limit=?, max_loan_limit=?, min_roi=?, max_roi=?, dsa_code=?, status=? WHERE id=?");
            if($stmt->execute([$full_name, $bank_type, $bank_name, $designation, $state, $city, $pincode, $address, $ifsc_code, $contact_number, $official_email, $bank_official_email, $dsa_documents_drive_link, $dsa_undertaking_file, $loan_category, $coverage_type, $coverage_details, $min_limit, $max_limit, $min_roi, $max_roi, $dsa_code, $status, $id])) {
                
                if (!empty($ifsc_code)) {
                    $check = $db->prepare("SELECT ifsc FROM ifsc_master WHERE ifsc = ?");
                    $check->execute([$ifsc_code]);
                    if (!$check->fetch()) {
                        $ins = $db->prepare("INSERT INTO ifsc_master (ifsc, bank, branch, address, city, state) VALUES (?, ?, ?, ?, ?, ?)");
                        $ins->execute([$ifsc_code, $bank_name, $city . ' BRANCH', $address, $city, $state]);
                    }
                }

                log_activity("Updated Banker: $full_name ($bank_name)", "bankers_list.php");
                return_json(['success' => true, 'message' => 'Banker updated successfully']);
            }
            return_json(['error' => 'Failed to update banker'], 500);
            break;

        case 'toggle_banker_status':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Method'], 405);
            $bank_name = trim($_POST['bank_name'] ?? '');
            $banker_id = (int)($_POST['banker_id'] ?? 0);
            $new_status = trim($_POST['status'] ?? 'Inactive');
            $reason = trim($_POST['reason'] ?? '');
            $user_name = $_SESSION['username'] ?? 'Admin';

            if (empty($bank_name) && !$banker_id) return_json(['error' => 'Bank Name or Banker ID is required.'], 400);

            if ($banker_id > 0) {
                $stmt = $db->prepare("SELECT * FROM bankers WHERE id = ?");
                $stmt->execute([$banker_id]);
                $b_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $stmt = $db->prepare("SELECT * FROM bankers WHERE bank_name = ?");
                $stmt->execute([$bank_name]);
                $b_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            if (empty($b_records)) return_json(['error' => 'No matching bank record found.'], 404);

            if ($banker_id > 0) {
                $upd = $db->prepare("UPDATE bankers SET status = ?, deactivated_at = CURRENT_TIMESTAMP, deactivated_by = ?, deactivation_reason = ? WHERE id = ?");
                $upd->execute([$new_status, $user_name, $reason, $banker_id]);
            } else {
                $upd = $db->prepare("UPDATE bankers SET status = ?, deactivated_at = CURRENT_TIMESTAMP, deactivated_by = ?, deactivation_reason = ? WHERE bank_name = ?");
                $upd->execute([$new_status, $user_name, $reason, $bank_name]);
            }

            // Send notification emails
            foreach ($b_records as $rec) {
                if (function_exists('send_bank_status_email')) {
                    send_bank_status_email($db, $rec['bank_name'], $rec['full_name'], $rec['official_email'] ?? '', $rec['bank_official_email'] ?? '', $new_status, $reason);
                }
            }

            log_activity("Updated Bank Status to $new_status for $bank_name", "view_bank.php");
            return_json(['success' => true, 'message' => "Bank relationship status updated to '$new_status' and notification emails sent!"]);
            break;

        case 'send_dsa_onboarding_mail':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Method'], 405);
            $bank_name = trim($_POST['bank_name'] ?? '');
            $drive_link = trim($_POST['dsa_documents_drive_link'] ?? '');
            $custom_notes = trim($_POST['notes'] ?? '');

            if (empty($bank_name)) return_json(['error' => 'Bank Name is required.'], 400);

            $stmt = $db->prepare("SELECT * FROM bankers WHERE bank_name = ?");
            $stmt->execute([$bank_name]);
            $b_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($b_records)) return_json(['error' => 'Bank record not found.'], 404);

            $company_name = "Bhardwaj Financial Services";
            $subject = "Company DSA Onboarding Documents - $company_name | $bank_name";

            foreach ($b_records as $rec) {
                $recipients = array_filter(array_unique([$rec['official_email'] ?? '', $rec['bank_official_email'] ?? '']));
                if (empty($recipients)) continue;

                $link_to_send = !empty($drive_link) ? $drive_link : ($rec['dsa_documents_drive_link'] ?? '');
                $dsa_code_txt = !empty($rec['dsa_code']) ? "DSA Code: <strong>{$rec['dsa_code']}</strong>" : "";

                $email_html = "
                <div style='font-family: Arial, sans-serif; color: #09090b; max-width: 600px; margin: 0 auto; border: 1px solid #e4e4e7; border-radius: 8px; overflow: hidden;'>
                    <div style='background: #09090b; color: #ffffff; padding: 20px; text-align: center;'>
                        <h2 style='margin: 0; font-size: 20px; font-weight: 800;'>$company_name</h2>
                        <p style='margin: 4px 0 0 0; font-size: 13px; color: #a1a1aa;'>DSA Onboarding & Relationship Documents</p>
                    </div>
                    <div style='padding: 24px;'>
                        <h3 style='margin-top: 0; color: #09090b;'>DSA Code Activation & Document Submission</h3>
                        <p style='font-size: 14px; line-height: 1.6;'>Dear Bank Relationship Officer ({$rec['full_name']}),</p>
                        <p style='font-size: 14px; line-height: 1.6;'>Please find below our company DSA onboarding documents for setting up / verifying our DSA relationship with <strong>$bank_name</strong>.</p>
                        " . (!empty($dsa_code_txt) ? "<div style='margin: 12px 0; font-size: 14px;'>$dsa_code_txt</div>" : "") . "
                        " . (!empty($link_to_send) ? "<div style='margin: 16px 0; padding: 14px; background: #f4f4f5; border: 1px solid #18181b; border-radius: 6px;'><strong style='font-size: 13px; display: block; margin-bottom: 6px;'>📁 Google Drive Company Documents Folder:</strong> <a href='" . htmlspecialchars($link_to_send) . "' target='_blank' style='color: #09090b; font-weight: 700; word-break: break-all;'>" . htmlspecialchars($link_to_send) . "</a></div>" : "") . "
                        " . (!empty($custom_notes) ? "<div style='margin-top: 14px; padding: 12px; background: #f8fafc; border-left: 4px solid #09090b; font-size: 13px;'><strong>Additional Remarks:</strong> " . htmlspecialchars($custom_notes) . "</div>" : "") . "
                        <hr style='border: none; border-top: 1px solid #e4e4e7; margin: 24px 0;'>
                        <p style='font-size: 12px; color: #71717a; margin-bottom: 0;'>Sent by <strong>$company_name</strong>.</p>
                    </div>
                </div>";

                try {
                    $db->exec("CREATE TABLE IF NOT EXISTS email_logs (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        recipient TEXT,
                        subject TEXT,
                        body TEXT,
                        status TEXT DEFAULT 'Sent',
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    )");
                    foreach ($recipients as $to) {
                        $stmt_log = $db->prepare("INSERT INTO email_logs (recipient, subject, body, status) VALUES (?, ?, ?, 'Sent')");
                        $stmt_log->execute([$to, $subject, $email_html]);
                        @mail($to, $subject, $email_html, "MIME-Version: 1.0\r\nContent-type:text/html;charset=UTF-8\r\nFrom: $company_name <info@bhardwajfinancialservices.com>\r\n");
                    }
                } catch(Exception $e) {}
            }

            return_json(['success' => true, 'message' => "DSA Onboarding Documents sent successfully to RM & Bank Official emails!"]);
            break;

        
        case 'save_payout_rule':
            if ($_SESSION['role'] !== 'Admin') return_json(['error' => 'Unauthorized'], 403);
            $bank_name = trim($_POST['bank_name'] ?? '');
            $loan_type = trim($_POST['loan_type'] ?? '');
            $payout = (float)($_POST['payout_percentage'] ?? 0);
            
            // Check if exists
            $stmt = $db->prepare("SELECT id FROM bank_payout_settings WHERE bank_name = ? AND loan_type = ?");
            $stmt->execute([$bank_name, $loan_type]);
            $existing = $stmt->fetchColumn();
            
            if ($existing) {
                $stmt = $db->prepare("UPDATE bank_payout_settings SET payout_percentage = ? WHERE id = ?");
                $stmt->execute([$payout, $existing]);
            } else {
                $stmt = $db->prepare("INSERT INTO bank_payout_settings (bank_name, loan_type, payout_percentage) VALUES (?, ?, ?)");
                $stmt->execute([$bank_name, $loan_type, $payout]);
            }
            return_json(['success' => true]);
            break;
            
        case 'delete_payout_rule':
            if ($_SESSION['role'] !== 'Admin') return_json(['error' => 'Unauthorized'], 403);
            $id = (int)($_POST['id'] ?? 0);
            $db->prepare("DELETE FROM bank_payout_settings WHERE id = ?")->execute([$id]);
            return_json(['success' => true]);
            break;
            
        case 'get_payout_percent':
            $bank_name = trim($_POST['bank_name'] ?? '');
            $loan_type = trim($_POST['loan_type'] ?? '');
            
            // Priority: Specific Loan Type -> 'All' Loan Types -> 0
            $stmt = $db->prepare("SELECT payout_percentage FROM bank_payout_settings WHERE bank_name = ? AND (loan_type = ? OR loan_type = 'All') ORDER BY loan_type = 'All' ASC LIMIT 1");
            $stmt->execute([$bank_name, $loan_type]);
            $percent = $stmt->fetchColumn();
            return_json(['success' => true, 'payout' => $percent ?: 0]);
            break;

        
        case 'fetch_pincode':
            $pin = trim($_GET['pincode'] ?? '');
            if (!$pin || strlen($pin) !== 6) return_json(['error' => 'Invalid Pincode'], 400);
            
            $stmt = $db->prepare("SELECT city, state FROM pincode_master WHERE pincode = ?");
            $stmt->execute([$pin]);
            $info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($info) {
                return_json(['success' => true, 'data' => $info]);
            } else {
                return_json(['error' => 'Pincode not found'], 404);
            }

        case 'fetch_ifsc':
            $ifsc = trim($_GET['ifsc'] ?? '');
            if (!$ifsc || strlen($ifsc) !== 11) return_json(['error' => 'Invalid IFSC code'], 400);
            
            $stmt = $db->prepare("SELECT * FROM ifsc_master WHERE ifsc = ? LIMIT 1");
            $stmt->execute([$ifsc]);
            $info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($info) {
                return_json(['success' => true, 'data' => $info]);
            } else {
                // Fallback to Razorpay via PHP cURL to avoid frontend CORS/Adblock issues
                $ch = curl_init("https://ifsc.razorpay.com/" . $ifsc);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode === 200 && $response) {
                    $liveData = json_decode($response, true);
                    if ($liveData && isset($liveData['BANK'])) {
                        $info = [
                            'bank' => $liveData['BANK'],
                            'branch' => $liveData['BRANCH'],
                            'address' => $liveData['ADDRESS'],
                            'city' => $liveData['CITY'],
                            'state' => $liveData['STATE']
                        ];
                        // Cache it back to local DB so next time it's fast!
                        $insert = $db->prepare("INSERT OR REPLACE INTO ifsc_master (ifsc, bank, branch, address, city, state) VALUES (?, ?, ?, ?, ?, ?)");
                        $insert->execute([$ifsc, $info['bank'], $info['branch'], $info['address'], $info['city'], $info['state']]);
                        
                        return_json(['success' => true, 'data' => $info]);
                    }
                }
                
                return_json(['success' => false, 'message' => 'IFSC not found or invalid']);
            }
            break;
            
        case 'get_branches':
            $bank = trim($_GET['bank'] ?? '');
            $city = trim($_GET['city'] ?? '');
            if (!$bank || !$city) return_json(['success' => true, 'data' => []]);

            $stmt = $db->prepare("SELECT ifsc, branch, address FROM ifsc_master WHERE bank LIKE ? AND (city = ? OR city LIKE ? OR city LIKE ? OR city LIKE ?) LIMIT 100");
            $stmt->execute(['%'.$bank.'%', $city, $city . ' %', '% ' . $city, '% ' . $city . ' %']);
            $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return_json(['success' => true, 'data' => $branches]);
            break;

        
        case 'staff_ping':
            if (!isset($_SESSION['username'])) { echo json_encode(['error' => 'Not logged in']); exit; }
            $u = $_SESSION['username'];
            $lat = $_POST['lat'] ?? '';
            $lon = $_POST['lon'] ?? '';
            $bat = $_POST['battery'] ?? '';
            $status = $_POST['status'] ?? 'Active';
            
            if($lat && $lon) {
                // Update user current state
                $now = date('Y-m-d H:i:s');
                $upd = $db->prepare("UPDATE users SET last_ping = ?, current_lat = ?, current_lon = ?, current_battery = ?, current_status = ? WHERE username = ?");
                $upd->execute([$now, $lat, $lon, $bat, $status, $u]);
                
                // Log location
                $log = $db->prepare("INSERT INTO staff_location_logs (username, lat, lon, battery, status) VALUES (?, ?, ?, ?, ?)");
                $log->execute([$u, $lat, $lon, $bat, $status]);
            }
            echo json_encode(['success' => true]);
            break;

        case 'staff_sos':
            if (!isset($_SESSION['username'])) { 
                echo json_encode(['error' => 'Not logged in']); 
                exit; 
            }
            $u = $_SESSION['username'];
            $lat = $_POST['lat'] ?? '';
            $lon = $_POST['lon'] ?? '';
            $msg = trim($_POST['message'] ?? 'Field Staff Emergency SOS Alert');
            
            // Mark user status as SOS
            $upd = $db->prepare("UPDATE users SET current_status = 'EMERGENCY_SOS' WHERE username = ?");
            $upd->execute([$u]);
            
            // Log high priority alert
            $db->exec("CREATE TABLE IF NOT EXISTS system_alerts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT,
                alert_type TEXT,
                message TEXT,
                lat TEXT,
                lon TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
            $stmt = $db->prepare("INSERT INTO system_alerts (username, alert_type, message, lat, lon) VALUES (?, 'SOS', ?, ?, ?)");
            $stmt->execute([$u, $msg, $lat, $lon]);
            
            echo json_encode(['success' => true, 'message' => '🚨 SOS alert sent! Admin & RM notified with your live GPS location.']);
            break;


        
        case 'get_staff_performance':
            if (!isset($_SESSION['user_id'])) { echo json_encode(['error' => 'Unauthorized']); exit; }
            $u = $_GET['username'] ?? $_SESSION['username'];
            
            // Total visits
            $v_stmt = $db->prepare("SELECT COUNT(*) FROM field_visits WHERE executive_name = ?");
            $v_stmt->execute([$u]);
            $tv = $v_stmt->fetchColumn();
            
            // Total leads (applicants generated by this user)
            $l_stmt = $db->prepare("SELECT COUNT(*) FROM applicants WHERE added_by = ?");
            $l_stmt->execute([$u]);
            $tl = $l_stmt->fetchColumn();
            
            // Get Employee Commission Rate
            $uid = $_SESSION['user_id'];
            $c_stmt = $db->prepare("SELECT commission_rate FROM employees WHERE user_id = ?");
            $c_stmt->execute([$uid]);
            $crate = $c_stmt->fetchColumn();
            $crate = $crate ? $crate : 0;
            
            // Get Total Commission Earned from payout_distributions
            $p_stmt = $db->prepare("SELECT SUM(net_payable) FROM payout_distributions WHERE payee_type = 'Staff' AND payee_user_id = ?");
            $p_stmt->execute([$uid]);
            $t_comm = $p_stmt->fetchColumn();
            $t_comm = $t_comm ? $t_comm : 0;
            
            echo json_encode([
                'total_visits' => $tv,
                'total_leads' => $tl,
                'total_commission' => $t_comm,
                'commission_rate' => $crate
            ]);
            break;

        case 'get_staff_recent_leads':
            if (!isset($_SESSION['user_id'])) { echo json_encode(['error' => 'Unauthorized']); exit; }
            $u = $_GET['username'] ?? $_SESSION['username'];
            $limit = intval($_GET['limit'] ?? 50);
            if ($limit > 100) $limit = 100;
            
            $stmt = $db->prepare("
                SELECT id, loan_id, customer_name, customer_name as applicant_name, loan_type, 
                       loan_amount_requested, sanctioned_amount, sanction_bank, overall_status, 
                       bank_payout_status, last_activity_date, created_at, tracking_token
                FROM applicants 
                WHERE added_by = ? OR assigned_to = ? 
                ORDER BY created_at DESC 
                LIMIT ?
            ");
            $stmt->execute([$u, $u, $limit]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        
        case 'punch_in':
            if (!isset($_SESSION['username'])) exit;
            $u = $_SESSION['username'];
            $today = date('Y-m-d');
            $now = date('Y-m-d H:i:s');
            
            $stmt = $db->prepare("SELECT id FROM staff_attendance WHERE username = ? AND att_date = ?");
            $stmt->execute([$u, $today]);
            if (!$stmt->fetch()) {
                $ins = $db->prepare("INSERT INTO staff_attendance (username, att_date, punch_in, status) VALUES (?, ?, ?, 'Working')");
                $ins->execute([$u, $today, $now]);
            } else {
                $upd = $db->prepare("UPDATE staff_attendance SET punch_out = NULL, status = 'Working' WHERE username = ? AND att_date = ?");
                $upd->execute([$u, $today]);
            }
            
            $u_upd = $db->prepare("UPDATE users SET current_status = 'Online' WHERE username = ?");
            $u_upd->execute([$u]);
            
            echo json_encode(['success' => true]);
            break;
            
        case 'update_staff_profile':
            if (!isset($_SESSION['user_id'])) { echo json_encode(['error' => 'Not logged in']); exit; }
            $u = $_SESSION['username'];
            $uid = $_SESSION['user_id'];
            
            $full_name = trim($_POST['full_name'] ?? '');
            $mobile = trim($_POST['mobile'] ?? '');
            $p_email = trim($_POST['personal_email'] ?? '');
            $pass = $_POST['password'] ?? '';
            
            if (empty($mobile)) {
                echo json_encode(['error' => 'Mobile number is required']);
                exit;
            }
            
            try {
                // Handle file upload
                $photo_sql = "";
                $params = [];
                $emp_update_fields = [];
                
                if (!empty($full_name)) {
                    $emp_update_fields[] = "full_name = ?";
                    $params[] = $full_name;
                }
                
                $emp_update_fields[] = "mobile = ?";
                $params[] = $mobile;
                
                $emp_update_fields[] = "personal_email = ?";
                $params[] = $p_email;
                
                $photo_uploaded = false;
                if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
                    $allowed = ['jpg','jpeg','png'];
                    if (in_array($ext, $allowed)) {
                        $new_name = 'profile_' . $uid . '_' . time() . '.' . $ext;
                        $dest = __DIR__ . '/uploads/employees/' . $new_name;
                        if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $dest)) {
                            $emp_update_fields[] = "photo_path = ?";
                            $params[] = $new_name;
                            $photo_uploaded = true;
                        }
                    }
                }
                
                $params[] = $uid; // for WHERE user_id = ?
                
                $db->beginTransaction();
                
                // 1. Update employees table
                $emp_sql = "UPDATE employees SET " . implode(", ", $emp_update_fields) . " WHERE user_id = ?";
                $upd_emp = $db->prepare($emp_sql);
                $upd_emp->execute($params);
                
                // 2. Sync to users table (Name & Password)
                if (!empty($full_name)) {
                    try {
                        $upd_usr_name = $db->prepare("UPDATE users SET full_name = ?, name = ? WHERE id = ?");
                        $upd_usr_name->execute([$full_name, $full_name, $uid]);
                    } catch (Exception $e) {
                        $upd_usr_name = $db->prepare("UPDATE users SET name = ? WHERE id = ?");
                        $upd_usr_name->execute([$full_name, $uid]);
                    }
                }
                
                if (!empty($pass)) {
                    $hash = password_hash($pass, PASSWORD_DEFAULT);
                    try {
                        $upd_usr = $db->prepare("UPDATE users SET password_hash = ?, plain_password = ? WHERE id = ?");
                        $upd_usr->execute([$hash, $pass, $uid]);
                    } catch (Exception $pe) {
                        $upd_usr = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                        $upd_usr->execute([$hash, $uid]);
                    }
                }
                
                // 3. Log Activity for Admin & System Audits
                $display_name = !empty($full_name) ? $full_name : $u;
                $log_msg = "Staff Profile Updated by {$display_name} ({$u}). Mobile: {$mobile}, Email: {$p_email}";
                if ($photo_uploaded) $log_msg .= ", Photo Changed";
                if (!empty($pass)) $log_msg .= ", Password Changed";
                
                log_activity($log_msg, "staff/profile.php", $u);
                
                // 4. Trigger SLA / System Alert for Admin Notification
                try {
                    $db->prepare("INSERT INTO sla_alerts (applicant_id, alert_type, alert_message) VALUES (0, 'staff_profile_update', ?)")
                       ->execute([$log_msg]);
                } catch (Exception $ae) {}
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Profile updated everywhere & Admin notified successfully!']);
            } catch(Exception $e) {
                $db->rollBack();
                echo json_encode(['error' => $e->getMessage()]);
            }
            break;

        case 'update_partner_profile':
            if (!isset($_SESSION['user_id'])) {
                return_json(['error' => 'Session expired. Please log in again.'], 401);
            }
            $uid = $_SESSION['user_id'];
            $username = $_SESSION['username'] ?? '';

            // Fetch current user from DB
            $stmt_curr = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt_curr->execute([$uid]);
            $current_user = $stmt_curr->fetch(PDO::FETCH_ASSOC);

            if (!$current_user) {
                return_json(['error' => 'User not found.'], 404);
            }

            $full_name = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $mobile = trim($_POST['mobile'] ?? '');
            $city_state = trim($_POST['city_state'] ?? '');
            $bank_name = trim($_POST['bank_name'] ?? '');
            $account_number = trim($_POST['account_number'] ?? '');
            $ifsc_code = trim(strtoupper($_POST['ifsc_code'] ?? ''));
            $upi_id = trim($_POST['upi_id'] ?? '');

            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            if (empty($full_name)) {
                return_json(['error' => 'Full Name cannot be empty.'], 400);
            }

            // Password change validation
            $password_changed = false;
            $new_pwd_hash = null;
            if (!empty($new_password)) {
                if (strlen($new_password) < 4) {
                    return_json(['error' => 'New password must be at least 4 characters long.'], 400);
                }
                if ($new_password !== $confirm_password) {
                    return_json(['error' => 'New password and confirm password do not match.'], 400);
                }
                // Verify current password if user has an existing password set
                if (!empty($current_user['password_hash']) || !empty($current_user['plain_password'])) {
                    $verified = false;
                    if (!empty($current_password)) {
                        if (password_verify($current_password, $current_user['password_hash'] ?? '')) {
                            $verified = true;
                        } elseif ($current_password === ($current_user['plain_password'] ?? '')) {
                            $verified = true;
                        }
                    }
                    if (!$verified) {
                        return_json(['error' => 'Current password is incorrect.'], 400);
                    }
                }
                $new_pwd_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $password_changed = true;
            }

            // Handle Profile Photo Upload
            $new_photo_rel = null;
            if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['profile_photo'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                if (!in_array($ext, $allowed_exts)) {
                    return_json(['error' => 'Invalid photo format. Please upload JPG, PNG, or WebP.'], 400);
                }
                if ($file['size'] > 5 * 1024 * 1024) {
                    return_json(['error' => 'Photo size must be less than 5MB.'], 400);
                }

                $target_dir = __DIR__ . '/uploads/profiles';
                if (!is_dir($target_dir)) {
                    @mkdir($target_dir, 0777, true);
                }

                $new_filename = 'profile_' . $uid . '_' . time() . '_' . rand(100, 999) . '.' . $ext;
                $target_filepath = $target_dir . '/' . $new_filename;

                if (move_uploaded_file($file['tmp_name'], $target_filepath) || (php_sapi_name() === 'cli' && @copy($file['tmp_name'], $target_filepath))) {
                    $new_photo_rel = 'uploads/profiles/' . $new_filename;
                } else {
                    return_json(['error' => 'Failed to save uploaded photo.'], 500);
                }
            }

            try {
                $db->beginTransaction();

                // 1. Update users table
                $user_updates = ["name = ?", "full_name = ?", "email = ?"];
                $user_params = [$full_name, $full_name, $email];

                if ($password_changed) {
                    $user_updates[] = "password_hash = ?";
                    $user_params[] = $new_pwd_hash;
                    $user_updates[] = "plain_password = ?";
                    $user_params[] = $new_password;
                }

                if ($new_photo_rel !== null) {
                    $user_updates[] = "profile_photo = ?";
                    $user_params[] = $new_photo_rel;
                }

                $user_params[] = $uid;
                $sql_u = "UPDATE users SET " . implode(", ", $user_updates) . " WHERE id = ?";
                $db->prepare($sql_u)->execute($user_params);

                // 2. Update referrals table
                $stmt_ref_chk = $db->prepare("SELECT id FROM referrals WHERE user_id = ? OR mobile = ? ORDER BY id DESC LIMIT 1");
                $stmt_ref_chk->execute([$uid, $username]);
                $ref_rec = $stmt_ref_chk->fetch(PDO::FETCH_ASSOC);

                if ($ref_rec) {
                    $ref_updates = [
                        "full_name = ?",
                        "email = ?",
                        "city_state = ?",
                        "bank_name = ?",
                        "account_number = ?",
                        "ifsc_code = ?",
                        "upi_id = ?",
                        "user_id = ?"
                    ];
                    $ref_params = [
                        $full_name,
                        $email,
                        $city_state,
                        $bank_name,
                        $account_number,
                        $ifsc_code,
                        $upi_id,
                        $uid
                    ];

                    if (!empty($mobile)) {
                        $ref_updates[] = "mobile = ?";
                        $ref_params[] = $mobile;
                    }

                    if ($new_photo_rel !== null) {
                        $ref_updates[] = "profile_photo = ?";
                        $ref_params[] = $new_photo_rel;
                    }

                    $ref_params[] = $ref_rec['id'];
                    $sql_r = "UPDATE referrals SET " . implode(", ", $ref_updates) . " WHERE id = ?";
                    $db->prepare($sql_r)->execute($ref_params);
                }

                $db->commit();

                // Update session values
                $_SESSION['name'] = $full_name;
                $_SESSION['full_name'] = $full_name;

                // Log audit activity
                $log_detail = "Partner Profile Updated: {$full_name} ({$username})";
                if ($password_changed) $log_detail .= " | Password updated";
                if ($new_photo_rel) $log_detail .= " | Photo updated";
                log_activity($log_detail, "partner/profile.php", $username);

                return_json([
                    'success' => true,
                    'message' => 'Profile updated successfully!',
                    'full_name' => $full_name,
                    'photo_url' => $new_photo_rel ? '../' . $new_photo_rel : null
                ]);
            } catch (Exception $e) {
                $db->rollBack();
                return_json(['error' => 'Database error: ' . $e->getMessage()], 500);
            }
            break;

        case 'punch_out':
            if (!isset($_SESSION['username'])) exit;
            $u = $_SESSION['username'];
            $today = date('Y-m-d');
            $now = date('Y-m-d H:i:s');
            $upd = $db->prepare("UPDATE staff_attendance SET punch_out = ?, status = 'Completed' WHERE username = ? AND att_date = ?");
            $upd->execute([$now, $u, $today]);
            
            // Also set user to Offline
            $u_upd = $db->prepare("UPDATE users SET current_status = 'Offline' WHERE username = ?");
            $u_upd->execute([$u]);
            
            echo json_encode(['success' => true]);
            break;

        case 'get_staff_todays_tasks':
            if (!isset($_SESSION['username'])) exit;
            $u = $_GET['username'] ?? $_SESSION['username'];
            $today = date('Y-m-d');
            
            // Fetch visits scheduled for today or created today
            $stmt = $db->prepare("
                SELECT id, person_name, firm_name, mobile, lead_quality, remarks, 
                       next_meeting_date, created_at, 'visit' as type
                FROM field_visits 
                WHERE executive_name = ? AND (next_meeting_date = ? OR DATE(created_at) = ?)
                ORDER BY created_at DESC
            ");
            $stmt->execute([$u, $today, $today]);
            $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // If empty, fetch top 3 recent warm/hot leads for quick follow-up
            if (empty($tasks)) {
                $stmt2 = $db->prepare("
                    SELECT id, person_name, firm_name, mobile, lead_quality, remarks, 
                           next_meeting_date, created_at, 'lead' as type
                    FROM field_visits 
                    WHERE executive_name = ? 
                    ORDER BY created_at DESC LIMIT 5
                ");
                $stmt2->execute([$u]);
                $tasks = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            }
            
            echo json_encode(['success' => true, 'tasks' => $tasks]);
            break;

        case 'get_nearby_leads':
            if (!isset($_SESSION['username'])) exit;
            $u = $_GET['username'] ?? $_SESSION['username'];
            
            // Fetch visits and applicants with lat/lon or city/state
            $stmt = $db->prepare("
                SELECT id, person_name as customer_name, firm_name, mobile, check_in_lat as lat, 
                       check_in_lon as lon, city, state, lead_quality, 'visit' as source
                FROM field_visits 
                WHERE executive_name = ?
                UNION ALL
                SELECT id, customer_name, loan_type as firm_name, mobile, NULL as lat, 
                       NULL as lon, city, state, overall_status as lead_quality, 'applicant' as source
                FROM applicants 
                WHERE added_by = ? OR assigned_to = ?
                LIMIT 30
            ");
            $stmt->execute([$u, $u, $u]);
            $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'leads' => $leads]);
            break;

        case 'rm_dashboard_metrics':
            $rm_user = trim($_GET['rm_user'] ?? $_SESSION['username'] ?? '');
            if (empty($rm_user)) {
                return_json(['error' => 'No RM user specified'], 400);
            }

            $cur_month_start = date('Y-m-01 00:00:00');
            $today_end = date('Y-m-d 23:59:59');

            if ($rm_user === 'ALL') {
                // Admin Master Operations Overview Mode
                $active_stmt = $db->query("SELECT COUNT(*) as count, COALESCE(SUM(loan_amount_requested), 0) as total_val FROM applicants WHERE overall_status != 'Rejected'");
                $active_data = $active_stmt->fetch(PDO::FETCH_ASSOC);
                $active_files_count = (int)$active_data['count'];
                $active_pipeline_val = (float)$active_data['total_val'];

                $disb_stmt = $db->prepare("SELECT COUNT(*) as count, COALESCE(SUM(COALESCE(sanctioned_amount, loan_amount_requested)), 0) as total_disb FROM applicants WHERE (overall_status = 'Completed' OR sanction_status = 'Disbursed') AND created_at >= ?");
                $disb_stmt->execute([$cur_month_start]);
                $disb_data = $disb_stmt->fetch(PDO::FETCH_ASSOC);
                $mtd_disbursed_val = (float)$disb_data['total_disb'];
                $mtd_disbursed_count = (int)$disb_data['count'];

                // Team Targets Sum (only Active Staff / RMs, excluding Admin)
                $target_stmt = $db->query("SELECT SUM(COALESCE(monthly_target, 15000000)) as total_target FROM users WHERE (role = 'Staff' OR role = 'RM') AND (is_active IS NULL OR is_active != 0)");
                $team_target_val = (float)$target_stmt->fetchColumn() ?: 15000000;
                $target_progress_pct = $team_target_val > 0 ? min(100, round(($mtd_disbursed_val / $team_target_val) * 100, 1)) : 0;

                $phases = ['Phase 1' => 0, 'Phase 2' => 0, 'Phase 3' => 0, 'Phase 4' => 0, 'Completed' => 0];
                $phase_stmt = $db->query("SELECT overall_status, COUNT(*) as c FROM applicants GROUP BY overall_status");
                while ($row = $phase_stmt->fetch(PDO::FETCH_ASSOC)) {
                    if (isset($phases[$row['overall_status']])) {
                        $phases[$row['overall_status']] = (int)$row['c'];
                    }
                }

                $call_stmt = $db->prepare("SELECT COUNT(*) FROM reminders WHERE status IN ('Pending', 'Snoozed') AND remind_at <= ?");
                $call_stmt->execute([$today_end]);
                $reminders_due_count = (int)$call_stmt->fetchColumn();

                $docs_pending_count = (int)$db->query("SELECT COUNT(*) FROM applicants WHERE overall_status IN ('Phase 1', 'Phase 2')")->fetchColumn();
                $login_fee_due_count = (int)$db->query("SELECT COUNT(*) FROM applicants WHERE overall_status IN ('Phase 2', 'Phase 3') AND (login_fee_status IS NULL OR login_fee_status != 'Paid')")->fetchColumn();
                $sanctions_pending_disb_count = (int)$db->query("SELECT COUNT(*) FROM applicants WHERE (sanction_status = 'Sanctioned' OR overall_status = 'Phase 4') AND overall_status != 'Completed'")->fetchColumn();

                $files_stmt = $db->query("SELECT id, loan_id, customer_name, mobile, email, loan_type, loan_amount_requested, sanctioned_amount, sanction_bank, overall_status, login_fee_status, login_fee_amount, rm_priority, rm_next_followup_date, assigned_to, created_at, CAST((julianday('now') - julianday(created_at)) AS INTEGER) as tat_days FROM applicants ORDER BY id DESC LIMIT 100");
                $assigned_files = $files_stmt->fetchAll(PDO::FETCH_ASSOC);

                $act_stmt = $db->query("SELECT * FROM activities ORDER BY id DESC LIMIT 50");
                $recent_activities = $act_stmt->fetchAll(PDO::FETCH_ASSOC);

                // Build Master RM Matrix Table Data (Exclude Admin and Deactivated accounts)
                $rm_matrix = [];
                $staff_users = $db->query("SELECT username, name, full_name, role, monthly_target, commission_rate FROM users WHERE (role = 'Staff' OR role = 'RM') AND (is_active IS NULL OR is_active != 0) ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);

                $total_team_incentives = 0;
                foreach ($staff_users as $su) {
                    $u_name = $su['username'];
                    $disp_name = !empty($su['full_name']) ? $su['full_name'] : (!empty($su['name']) ? $su['name'] : $u_name);
                    $u_target = (float)($su['monthly_target'] ?: 15000000);
                    $u_comm = (float)($su['commission_rate'] ?: 0.20);

                    // Assigned leads & pre-leads (case-insensitive check on username or display name)
                    $l_stmt = $db->prepare("SELECT COUNT(*) FROM leads WHERE LOWER(assigned_to) = LOWER(?) OR LOWER(assigned_to) = LOWER(?)");
                    $l_stmt->execute([$u_name, $disp_name]);
                    $l_count = (int)$l_stmt->fetchColumn();

                    $pl_stmt = $db->prepare("SELECT COUNT(*) FROM pre_leads WHERE LOWER(assigned_to) = LOWER(?) OR LOWER(assigned_to) = LOWER(?)");
                    $pl_stmt->execute([$u_name, $disp_name]);
                    $pl_count = (int)$pl_stmt->fetchColumn();

                    // Active files & pipeline
                    $act_stmt = $db->prepare("SELECT COUNT(*) as c, COALESCE(SUM(loan_amount_requested), 0) as v FROM applicants WHERE (LOWER(assigned_to) = LOWER(?) OR LOWER(assigned_to) = LOWER(?) OR (assigned_to IS NULL AND (LOWER(added_by) = LOWER(?) OR LOWER(added_by) = LOWER(?)))) AND overall_status != 'Rejected'");
                    $act_stmt->execute([$u_name, $disp_name, $u_name, $disp_name]);
                    $act_res = $act_stmt->fetch(PDO::FETCH_ASSOC);
                    $u_active_count = (int)$act_res['c'];
                    $u_active_val = (float)$act_res['v'];

                    // MTD Disbursed
                    $disb_stmt = $db->prepare("SELECT COUNT(*) as c, COALESCE(SUM(COALESCE(sanctioned_amount, loan_amount_requested)), 0) as v FROM applicants WHERE (LOWER(assigned_to) = LOWER(?) OR LOWER(assigned_to) = LOWER(?) OR (assigned_to IS NULL AND (LOWER(added_by) = LOWER(?) OR LOWER(added_by) = LOWER(?)))) AND (overall_status = 'Completed' OR sanction_status = 'Disbursed') AND created_at >= ?");
                    $disb_stmt->execute([$u_name, $disp_name, $u_name, $disp_name, $cur_month_start]);
                    $disb_res = $disb_stmt->fetch(PDO::FETCH_ASSOC);
                    $u_disb_count = (int)$disb_res['c'];
                    $u_disb_val = (float)$disb_res['v'];

                    $u_incentive = round($u_disb_val * ($u_comm / 100));
                    $total_team_incentives += $u_incentive;
                    $u_pct = $u_target > 0 ? min(100, round(($u_disb_val / $u_target) * 100, 1)) : 0;

                    // Reminders due
                    $rem_stmt = $db->prepare("SELECT COUNT(*) FROM reminders WHERE (LOWER(assigned_to) = LOWER(?) OR LOWER(assigned_to) = LOWER(?)) AND status IN ('Pending', 'Snoozed') AND remind_at <= ?");
                    $rem_stmt->execute([$u_name, $disp_name, $today_end]);
                    $u_rems = (int)$rem_stmt->fetchColumn();

                    // Specific operational bottlenecks for this officer
                    $u_docs_stmt = $db->prepare("SELECT COUNT(*) FROM applicants WHERE (LOWER(assigned_to) = LOWER(?) OR LOWER(assigned_to) = LOWER(?) OR (assigned_to IS NULL AND (LOWER(added_by) = LOWER(?) OR LOWER(added_by) = LOWER(?)))) AND overall_status IN ('Phase 1', 'Phase 2')");
                    $u_docs_stmt->execute([$u_name, $disp_name, $u_name, $disp_name]);
                    $u_docs = (int)$u_docs_stmt->fetchColumn();

                    $u_fee_stmt = $db->prepare("SELECT COUNT(*) FROM applicants WHERE (LOWER(assigned_to) = LOWER(?) OR LOWER(assigned_to) = LOWER(?) OR (assigned_to IS NULL AND (LOWER(added_by) = LOWER(?) OR LOWER(added_by) = LOWER(?)))) AND overall_status IN ('Phase 2', 'Phase 3') AND (login_fee_status IS NULL OR login_fee_status != 'Paid')");
                    $u_fee_stmt->execute([$u_name, $disp_name, $u_name, $disp_name]);
                    $u_fees = (int)$u_fee_stmt->fetchColumn();

                    $u_sanc_stmt = $db->prepare("SELECT COUNT(*) FROM applicants WHERE (LOWER(assigned_to) = LOWER(?) OR LOWER(assigned_to) = LOWER(?) OR (assigned_to IS NULL AND (LOWER(added_by) = LOWER(?) OR LOWER(added_by) = LOWER(?)))) AND (sanction_status = 'Sanctioned' OR overall_status = 'Phase 4') AND overall_status != 'Completed'");
                    $u_sanc_stmt->execute([$u_name, $disp_name, $u_name, $disp_name]);
                    $u_sancs = (int)$u_sanc_stmt->fetchColumn();

                    $u_actions_total = $u_rems + $u_docs + $u_fees + $u_sancs;
                    $u_capacity_max = 15;
                    $u_capacity_pct = min(100, round(($u_active_count / $u_capacity_max) * 100));

                    $rm_matrix[] = [
                        'username' => $u_name,
                        'name' => $disp_name,
                        'role' => $su['role'],
                        'assigned_leads' => $l_count + $pl_count,
                        'active_files' => $u_active_count,
                        'active_pipeline_val' => $u_active_val,
                        'mtd_disbursed_val' => $u_disb_val,
                        'mtd_disbursed_count' => $u_disb_count,
                        'monthly_target' => $u_target,
                        'commission_rate' => $u_comm,
                        'incentive_earned' => $u_incentive,
                        'target_progress_pct' => $u_pct,
                        'reminders_due' => $u_rems,
                        'docs_pending' => $u_docs,
                        'fees_due' => $u_fees,
                        'sanctions_ready' => $u_sancs,
                        'actions_due_total' => $u_actions_total,
                        'capacity_max' => $u_capacity_max,
                        'capacity_pct' => $u_capacity_pct
                    ];
                }

                return_json([
                    'success' => true,
                    'is_master' => true,
                    'rm_user' => 'ALL',
                    'monthly_target' => $team_target_val,
                    'commission_rate' => 0.20,
                    'mtd_disbursed_val' => $mtd_disbursed_val,
                    'mtd_disbursed_count' => $mtd_disbursed_count,
                    'incentive_earned' => $total_team_incentives,
                    'target_progress_pct' => $target_progress_pct,
                    'active_files_count' => $active_files_count,
                    'active_pipeline_val' => $active_pipeline_val,
                    'phases' => $phases,
                    'triggers' => [
                        'reminders_due' => $reminders_due_count,
                        'docs_pending' => $docs_pending_count,
                        'login_fee_due' => $login_fee_due_count,
                        'sanctions_pending_disb' => $sanctions_pending_disb_count
                    ],
                    'assigned_files' => $assigned_files,
                    'recent_activities' => $recent_activities,
                    'rm_matrix' => $rm_matrix
                ]);
            }

            // Fetch Single RM targets
            $target_stmt = $db->prepare("SELECT name, full_name, monthly_target, commission_rate FROM users WHERE username = ?");
            $target_stmt->execute([$rm_user]);
            $user_meta = $target_stmt->fetch(PDO::FETCH_ASSOC);
            $rm_disp = $user_meta ? (!empty($user_meta['full_name']) ? $user_meta['full_name'] : (!empty($user_meta['name']) ? $user_meta['name'] : $rm_user)) : $rm_user;
            $monthly_target = $user_meta ? (float)($user_meta['monthly_target'] ?: 15000000) : 15000000;
            $comm_rate = $user_meta ? (float)($user_meta['commission_rate'] ?: 0.20) : 0.20;

            // Total Assigned Active Files
            $active_stmt = $db->prepare("SELECT COUNT(*) as count, COALESCE(SUM(loan_amount_requested), 0) as total_val FROM applicants WHERE (LOWER(assigned_to) = LOWER(?) OR LOWER(assigned_to) = LOWER(?) OR (assigned_to IS NULL AND (LOWER(added_by) = LOWER(?) OR LOWER(added_by) = LOWER(?)))) AND overall_status != 'Rejected'");
            $active_stmt->execute([$rm_user, $rm_disp, $rm_user, $rm_disp]);
            $active_data = $active_stmt->fetch(PDO::FETCH_ASSOC);
            $active_files_count = (int)$active_data['count'];
            $active_pipeline_val = (float)$active_data['total_val'];

            // Month-to-date Disbursed Volume
            $disb_stmt = $db->prepare("SELECT COUNT(*) as count, COALESCE(SUM(COALESCE(sanctioned_amount, loan_amount_requested)), 0) as total_disb FROM applicants WHERE (LOWER(assigned_to) = LOWER(?) OR LOWER(assigned_to) = LOWER(?) OR (assigned_to IS NULL AND (LOWER(added_by) = LOWER(?) OR LOWER(added_by) = LOWER(?)))) AND (overall_status = 'Completed' OR sanction_status = 'Disbursed') AND created_at >= ?");
            $disb_stmt->execute([$rm_user, $rm_disp, $rm_user, $rm_disp, $cur_month_start]);
            $disb_data = $disb_stmt->fetch(PDO::FETCH_ASSOC);
            $mtd_disbursed_val = (float)$disb_data['total_disb'];
            $mtd_disbursed_count = (int)$disb_data['count'];
            $incentive_earned = round($mtd_disbursed_val * ($comm_rate / 100));
            $target_progress_pct = $monthly_target > 0 ? min(100, round(($mtd_disbursed_val / $monthly_target) * 100, 1)) : 0;

            // Phase Breakdown
            $phases = ['Phase 1' => 0, 'Phase 2' => 0, 'Phase 3' => 0, 'Phase 4' => 0, 'Completed' => 0];
            $phase_stmt = $db->prepare("SELECT overall_status, COUNT(*) as c FROM applicants WHERE (LOWER(assigned_to) = LOWER(?) OR LOWER(assigned_to) = LOWER(?) OR (assigned_to IS NULL AND (LOWER(added_by) = LOWER(?) OR LOWER(added_by) = LOWER(?)))) GROUP BY overall_status");
            $phase_stmt->execute([$rm_user, $rm_disp, $rm_user, $rm_disp]);
            while ($row = $phase_stmt->fetch(PDO::FETCH_ASSOC)) {
                if (isset($phases[$row['overall_status']])) {
                    $phases[$row['overall_status']] = (int)$row['c'];
                }
            }

            // 4 Daily Priority Action Triggers:
            // 1. Scheduled Calls / Reminders Due Today
            $call_stmt = $db->prepare("SELECT COUNT(*) FROM reminders WHERE (LOWER(assigned_to) = LOWER(?) OR LOWER(assigned_to) = LOWER(?) OR assigned_to IS NULL) AND status IN ('Pending', 'Snoozed') AND remind_at <= ?");
            $call_stmt->execute([$rm_user, $rm_disp, $today_end]);
            $reminders_due_count = (int)$call_stmt->fetchColumn();

            // 2. Critical Missing Doc Pendencies Count
            $p2_stmt = $db->prepare("SELECT COUNT(*) FROM applicants WHERE (LOWER(assigned_to) = LOWER(?) OR LOWER(assigned_to) = LOWER(?) OR (assigned_to IS NULL AND (LOWER(added_by) = LOWER(?) OR LOWER(added_by) = LOWER(?)))) AND overall_status IN ('Phase 1', 'Phase 2')");
            $p2_stmt->execute([$rm_user, $rm_disp, $rm_user, $rm_disp]);
            $docs_pending_count = (int)$p2_stmt->fetchColumn();

            // 3. Bank Login Fee Due Count
            $lf_stmt = $db->prepare("SELECT COUNT(*) FROM applicants WHERE (LOWER(assigned_to) = LOWER(?) OR LOWER(assigned_to) = LOWER(?) OR (assigned_to IS NULL AND (LOWER(added_by) = LOWER(?) OR LOWER(added_by) = LOWER(?)))) AND overall_status IN ('Phase 2', 'Phase 3') AND (login_fee_status IS NULL OR login_fee_status != 'Paid')");
            $lf_stmt->execute([$rm_user, $rm_disp, $rm_user, $rm_disp]);
            $login_fee_due_count = (int)$lf_stmt->fetchColumn();

            // 4. Sanctions Ready for Disbursal
            $sanc_stmt = $db->prepare("SELECT COUNT(*) FROM applicants WHERE (LOWER(assigned_to) = LOWER(?) OR LOWER(assigned_to) = LOWER(?) OR (assigned_to IS NULL AND (LOWER(added_by) = LOWER(?) OR LOWER(added_by) = LOWER(?)))) AND (sanction_status = 'Sanctioned' OR overall_status = 'Phase 4') AND overall_status != 'Completed'");
            $sanc_stmt->execute([$rm_user, $rm_disp, $rm_user, $rm_disp]);
            $sanctions_pending_disb_count = (int)$sanc_stmt->fetchColumn();

            // Recent RM Assigned Files List
            $files_stmt = $db->prepare("SELECT id, loan_id, customer_name, mobile, email, loan_type, loan_amount_requested, sanctioned_amount, sanction_bank, overall_status, login_fee_status, login_fee_amount, rm_priority, rm_next_followup_date, assigned_to, created_at, CAST((julianday('now') - julianday(created_at)) AS INTEGER) as tat_days FROM applicants WHERE (LOWER(assigned_to) = LOWER(?) OR LOWER(assigned_to) = LOWER(?) OR (assigned_to IS NULL AND (LOWER(added_by) = LOWER(?) OR LOWER(added_by) = LOWER(?)))) ORDER BY id DESC LIMIT 50");
            $files_stmt->execute([$rm_user, $rm_disp, $rm_user, $rm_disp]);
            $assigned_files = $files_stmt->fetchAll(PDO::FETCH_ASSOC);

            // Recent Activities / Interactions for this RM
            $act_stmt = $db->prepare("SELECT * FROM activities WHERE (LOWER(username) = LOWER(?) OR LOWER(username) = LOWER(?) OR LOWER(target_user) = LOWER(?) OR LOWER(target_user) = LOWER(?) OR username IS NULL) ORDER BY id DESC LIMIT 40");
            $act_stmt->execute([$rm_user, $rm_disp, $rm_user, $rm_disp]);
            $recent_activities = $act_stmt->fetchAll(PDO::FETCH_ASSOC);

            return_json([
                'success' => true,
                'is_master' => false,
                'rm_user' => $rm_user,
                'monthly_target' => $monthly_target,
                'commission_rate' => $comm_rate,
                'mtd_disbursed_val' => $mtd_disbursed_val,
                'mtd_disbursed_count' => $mtd_disbursed_count,
                'incentive_earned' => $incentive_earned,
                'target_progress_pct' => $target_progress_pct,
                'active_files_count' => $active_files_count,
                'active_pipeline_val' => $active_pipeline_val,
                'phases' => $phases,
                'triggers' => [
                    'reminders_due' => $reminders_due_count,
                    'docs_pending' => $docs_pending_count,
                    'login_fee_due' => $login_fee_due_count,
                    'sanctions_pending_disb' => $sanctions_pending_disb_count
                ],
                'assigned_files' => $assigned_files,
                'recent_activities' => $recent_activities
            ]);
            break;

        case 'assign_applicant_rm':
            $applicant_id = (int)($_POST['applicant_id'] ?? 0);
            $new_rm = trim($_POST['assigned_to'] ?? '');
            $notes = trim($_POST['assignment_notes'] ?? '');
            $assigned_by = $_SESSION['username'] ?? 'Admin';
            $now = date('Y-m-d H:i:s');

            if ($applicant_id <= 0 || empty($new_rm)) {
                return_json(['error' => 'Applicant ID and Target RM are required'], 400);
            }

            // Verify target user's role (must be RM or Admin)
            $usr_stmt = $db->prepare("SELECT role FROM users WHERE username = ?");
            $usr_stmt->execute([$new_rm]);
            $target_usr = $usr_stmt->fetch(PDO::FETCH_ASSOC);
            if ($target_usr) {
                $role_lower = strtolower($target_usr['role'] ?? '');
                $forbidden_roles = ['agent', 'builder', 'ca', 'partner', 'connector', 'dsa'];
                if (in_array($role_lower, $forbidden_roles)) {
                    return_json(['error' => 'Applications can only be transferred to a Relationship Manager (RM) or Admin'], 400);
                }
            }

            // Get applicant details
            $app_stmt = $db->prepare("SELECT customer_name, loan_id, assigned_to FROM applicants WHERE id = ?");
            $app_stmt->execute([$applicant_id]);
            $app = $app_stmt->fetch(PDO::FETCH_ASSOC);
            if (!$app) return_json(['error' => 'Applicant not found'], 404);

            $old_rm = $app['assigned_to'] ?: 'Unassigned';

            $upd = $db->prepare("UPDATE applicants SET assigned_to = ?, assigned_by = ?, assigned_at = ? WHERE id = ?");
            $upd->execute([$new_rm, $assigned_by, $now, $applicant_id]);

            // Log interaction & activity
            $act_desc = "Re-assigned Loan File {$app['loan_id']} ({$app['customer_name']}) from {$old_rm} to {$new_rm} by {$assigned_by}";
            if ($notes) $act_desc .= " (Remarks: $notes)";
            log_activity($act_desc);

            $inter_stmt = $db->prepare("INSERT INTO applicant_interactions (applicant_id, interaction_type, outcome, notes, created_by) VALUES (?, 'Status_Change', 'RM Re-assigned', ?, ?)");
            $inter_stmt->execute([$applicant_id, $act_desc, $assigned_by]);

            return_json([
                'success' => true,
                'message' => "Loan File successfully assigned to {$new_rm}!",
                'assigned_to' => $new_rm,
                'assigned_by' => $assigned_by,
                'assigned_at' => $now
            ]);
            break;

        case 'log_rm_interaction':
            $applicant_id = (int)($_POST['applicant_id'] ?? 0);
            $type = trim($_POST['interaction_type'] ?? 'Call'); // 'Call', 'WhatsApp', 'Visit', 'Note'
            $outcome = trim($_POST['outcome'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            $next_followup = trim($_POST['next_followup'] ?? '');
            $created_by = $_SESSION['username'] ?? 'RM';
            $today = date('Y-m-d');

            if ($applicant_id <= 0) {
                return_json(['error' => 'Applicant ID required'], 400);
            }

            $inter_stmt = $db->prepare("INSERT INTO applicant_interactions (applicant_id, interaction_type, outcome, notes, next_followup, created_by) VALUES (?, ?, ?, ?, ?, ?)");
            $inter_stmt->execute([$applicant_id, $type, $outcome, $notes, $next_followup ?: null, $created_by]);

            // Update applicant last contact and next follow-up
            $upd_app = $db->prepare("UPDATE applicants SET rm_last_contact_date = ?, rm_next_followup_date = ?, rm_notes = ? WHERE id = ?");
            $upd_app->execute([$today, $next_followup ?: null, $notes, $applicant_id]);

            // If next_followup is set, create a reminder
            if ($next_followup) {
                $rem_stmt = $db->prepare("INSERT INTO reminders (lead_type, lead_id, assigned_to, remind_at, notes, title, priority, reference_type, reference_id, reference_label, reminder_category) VALUES ('Applicant', ?, ?, ?, ?, ?, 'High', 'Applicant', ?, 'Loan Follow-up', 'Call')");
                $rem_stmt->execute([$applicant_id, $created_by, $next_followup, $notes, "Follow-up: $outcome", $applicant_id]);
            }

            $act_desc = "Logged $type with Applicant #$applicant_id ($outcome): $notes";
            log_activity($act_desc);

            return_json([
                'success' => true,
                'message' => "Interaction logged successfully!"
            ]);
            break;

        case 'admin_rm_workload_matrix':
            // Fetch all Staff / RM users
            $rms_stmt = $db->query("SELECT id, username, name, monthly_target, commission_rate FROM users WHERE role = 'Staff' AND is_active = 1 ORDER BY username ASC");
            $rms = $rms_stmt->fetchAll(PDO::FETCH_ASSOC);

            $matrix = [];
            $cur_month_start = date('Y-m-01 00:00:00');

            foreach ($rms as $rm) {
                $u = $rm['username'];
                // Total Active Files & Pipeline Value
                $s_tot = $db->prepare("SELECT COUNT(*) as c, COALESCE(SUM(loan_amount_requested), 0) as val FROM applicants WHERE assigned_to = ? AND overall_status != 'Rejected'");
                $s_tot->execute([$u]);
                $d_tot = $s_tot->fetch(PDO::FETCH_ASSOC);

                // Phase wise counts
                $s_p = $db->prepare("SELECT overall_status, COUNT(*) as c FROM applicants WHERE assigned_to = ? GROUP BY overall_status");
                $s_p->execute([$u]);
                $phase_counts = ['Phase 1' => 0, 'Phase 2' => 0, 'Phase 3' => 0, 'Phase 4' => 0, 'Completed' => 0];
                while ($r = $s_p->fetch(PDO::FETCH_ASSOC)) {
                    if (isset($phase_counts[$r['overall_status']])) {
                        $phase_counts[$r['overall_status']] = (int)$r['c'];
                    }
                }

                // Month Disbursed
                $s_disb = $db->prepare("SELECT COUNT(*) as c, COALESCE(SUM(COALESCE(sanctioned_amount, loan_amount_requested)), 0) as val FROM applicants WHERE assigned_to = ? AND overall_status = 'Completed' AND created_at >= ?");
                $s_disb->execute([$u, $cur_month_start]);
                $d_disb = $s_disb->fetch(PDO::FETCH_ASSOC);

                // Calls logged today
                $today_start = date('Y-m-d 00:00:00');
                $s_calls = $db->prepare("SELECT COUNT(*) FROM applicant_interactions WHERE created_by = ? AND created_at >= ?");
                $s_calls->execute([$u, $today_start]);
                $calls_today = (int)$s_calls->fetchColumn();

                $matrix[] = [
                    'username' => $u,
                    'name' => $rm['name'] ?: $u,
                    'monthly_target' => (float)$rm['monthly_target'],
                    'commission_rate' => (float)$rm['commission_rate'],
                    'total_active_files' => (int)$d_tot['c'],
                    'total_pipeline_val' => (float)$d_tot['val'],
                    'month_disbursed_val' => (float)$d_disb['val'],
                    'month_disbursed_count' => (int)$d_disb['c'],
                    'phases' => $phase_counts,
                    'calls_today' => $calls_today
                ];
            }

            // Unassigned files count
            $unassigned_stmt = $db->query("SELECT COUNT(*) as c, COALESCE(SUM(loan_amount_requested), 0) as val FROM applicants WHERE (assigned_to IS NULL OR assigned_to = '') AND overall_status != 'Rejected'");
            $unassigned = $unassigned_stmt->fetch(PDO::FETCH_ASSOC);

            return_json([
                'success' => true,
                'matrix' => $matrix,
                'unassigned' => [
                    'count' => (int)$unassigned['c'],
                    'value' => (float)$unassigned['val']
                ]
            ]);
            break;

        case 'get_bank_logins':
            $applicant_id = intval($_GET['applicant_id'] ?? 0);
            if(!$applicant_id) return_json(['error'=>'Applicant ID required'], 400);
            $stmt = $db->prepare("SELECT * FROM applicant_bank_logins WHERE applicant_id = ? ORDER BY created_at DESC");
            $stmt->execute([$applicant_id]);
            return_json(['success'=>true, 'logins'=>$stmt->fetchAll()]);
            break;

        case 'save_bank_login':
            $applicant_id = intval($_POST['applicant_id'] ?? 0);
            $id = intval($_POST['id'] ?? 0);
            $bank_name = trim($_POST['bank_name'] ?? '');
            $login_date = $_POST['login_date'] ?? '';
            $status = $_POST['status'] ?? 'File Submitted';
            $sanctioned_amount = floatval($_POST['sanctioned_amount'] ?? 0);
            $sanctioned_roi = floatval($_POST['sanctioned_roi'] ?? 0);
            
            if(!$applicant_id || empty($bank_name)) return_json(['error'=>'Applicant ID and Bank Name required'], 400);
            if($login_date && strtotime($login_date) > time()) return_json(['error'=>'Login date cannot be in the future'], 400);
            if(isset($_POST['sanctioned_amount']) && $_POST['sanctioned_amount'] !== '' && $sanctioned_amount <= 0) return_json(['error'=>'Sanctioned amount must be > 0'], 400);
            if($status === 'Sanctioned' && (!$sanctioned_amount || !$sanctioned_roi)) return_json(['error'=>'Sanctioned amount and ROI required for Sanctioned status'], 400);

            if(!$id && empty($_POST['force'])) {
                $check = $db->prepare("SELECT id FROM applicant_bank_logins WHERE applicant_id=? AND bank_name=?");
                $check->execute([$applicant_id, $bank_name]);
                if($check->fetch()) return_json(['error'=>"Warning: Login for $bank_name already exists.", 'require_force'=>true], 400);
            }

            if($id) {
                $db->prepare("UPDATE applicant_bank_logins SET bank_name=?, login_date=?, status=?, sanctioned_amount=?, sanctioned_roi=?, updated_at=CURRENT_TIMESTAMP WHERE id=?")
                   ->execute([$bank_name, $login_date, $status, $sanctioned_amount, $sanctioned_roi, $id]);
                log_activity("Updated bank login $bank_name for applicant $applicant_id");
            } else {
                $db->prepare("INSERT INTO applicant_bank_logins (applicant_id, bank_name, login_date, status, sanctioned_amount, sanctioned_roi, added_by) VALUES (?, ?, ?, ?, ?, ?, ?)")
                   ->execute([$applicant_id, $bank_name, $login_date, $status, $sanctioned_amount, $sanctioned_roi, $_SESSION['username']]);
                log_activity("Added bank login $bank_name for applicant $applicant_id");
            }
            return_json(['success'=>true]);
            break;

        case 'update_bank_login_status':
            $id = intval($_POST['id'] ?? 0);
            $status = trim($_POST['status'] ?? '');
            $rejection_reason = trim($_POST['rejection_reason'] ?? '');
            $sanctioned_amount = floatval($_POST['sanctioned_amount'] ?? 0);
            $sanctioned_roi = floatval($_POST['sanctioned_roi'] ?? 0);

            if(!$id) return_json(['error'=>'ID required'], 400);
            $valid = ['File Submitted', 'Under Review', 'Query Raised', 'Sanctioned', 'Rejected'];
            if(!in_array($status, $valid)) return_json(['error'=>'Invalid status'], 400);
            if($status === 'Rejected' && empty($rejection_reason)) return_json(['error'=>'Rejection reason required'], 400);
            if($status === 'Sanctioned' && (!$sanctioned_amount || !$sanctioned_roi)) return_json(['error'=>'Sanctioned amount and ROI required'], 400);

            $stmt = $db->prepare("SELECT l.*, a.assigned_to FROM applicant_bank_logins l JOIN applicants a ON a.id = l.applicant_id WHERE l.id = ?");
            $stmt->execute([$id]);
            $app = $stmt->fetch();
            if(!$app) return_json(['error'=>'Not found'], 404);
            if($_SESSION['role'] !== 'Admin' && $app['assigned_to'] !== $_SESSION['username']) return_json(['error'=>'Unauthorized'], 403);
            
            $db->prepare("UPDATE applicant_bank_logins SET status=?, rejection_reason=?, sanctioned_amount=?, sanctioned_roi=?, updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$status, $rejection_reason, $sanctioned_amount, $sanctioned_roi, $id]);
            log_activity("Updated bank login status to $status for ID $id");
            return_json(['success'=>true]);
            break;

        case 'select_bank_for_disbursal':
            if($_SESSION['role'] !== 'Admin') return_json(['error'=>'Admin only'], 403);
            $id = intval($_POST['id'] ?? 0);
            $note = trim($_POST['confirmation_note'] ?? '');
            if(!$id || !$note) return_json(['error'=>'ID and confirmation note required'], 400);
            
            $db->beginTransaction();
            try {
                $stmt = $db->prepare("SELECT * FROM applicant_bank_logins WHERE id=?");
                $stmt->execute([$id]);
                $login = $stmt->fetch();
                if(!$login) throw new Exception("Not found");
                
                $db->prepare("UPDATE applicant_bank_logins SET selected_for_disbursal=0 WHERE applicant_id=?")->execute([$login['applicant_id']]);
                $db->prepare("UPDATE applicant_bank_logins SET selected_for_disbursal=1 WHERE id=?")->execute([$id]);
                
                $db->prepare("UPDATE applicants SET sanction_bank=?, sanctioned_amount=? WHERE id=?")->execute([$login['bank_name'], $login['sanctioned_amount'], $login['applicant_id']]);
                
                log_activity("Selected bank ".$login['bank_name']." for disbursal for applicant ".$login['applicant_id'].". Note: $note");
                $db->commit();
                return_json(['success'=>true]);
            } catch(Exception $e) {
                $db->rollBack();
                return_json(['error'=>$e->getMessage()], 400);
            }
            break;

        case 'get_sla_alerts':
            $query = "SELECT a.id, a.loan_id, a.customer_name, a.mobile, a.overall_status, a.assigned_to,
                       a.loan_type, a.loan_amount_requested,
                       julianday('now') - julianday(COALESCE(a.last_activity_date, a.created_at)) as days_stalled,
                       COALESCE(a.last_activity_date, a.created_at) as last_activity
                FROM applicants a
                WHERE a.overall_status NOT IN ('Disbursed','Rejected','Completed')
                  AND julianday('now') - julianday(COALESCE(a.last_activity_date, a.created_at)) >= 3";
            $params = [];
            if($_SESSION['role'] !== 'Admin') {
                $query .= " AND a.assigned_to = ?";
                $params[] = $_SESSION['username'];
            }
            $query .= " ORDER BY days_stalled DESC";
            
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            return_json(['success'=>true, 'alerts'=>$stmt->fetchAll()]);
            break;

        case 'resolve_sla_alert':
            $applicant_id = intval($_POST['applicant_id'] ?? 0);
            $note = trim($_POST['resolution_note'] ?? '');
            if(!$applicant_id || !$note) return_json(['error'=>'Applicant ID and resolution note required'], 400);
            
            $db->beginTransaction();
            try {
                $db->prepare("UPDATE applicants SET last_activity_date=CURRENT_TIMESTAMP WHERE id=?")->execute([$applicant_id]);
                $db->prepare("INSERT INTO sla_alerts (applicant_id, alert_message, is_resolved, resolved_by, resolved_at) VALUES (?, ?, 1, ?, CURRENT_TIMESTAMP)")
                   ->execute([$applicant_id, $note, $_SESSION['username']]);
                log_activity("Resolved SLA alert for applicant $applicant_id");
                $db->commit();
                return_json(['success'=>true]);
            } catch(Exception $e) {
                $db->rollBack();
                return_json(['error'=>$e->getMessage()], 400);
            }
            break;

        case 'get_bank_queries':
            $applicant_id = intval($_GET['applicant_id'] ?? 0);
            if(!$applicant_id) return_json(['error'=>'Applicant ID required'], 400);
            $stmt = $db->prepare("SELECT * FROM bank_queries WHERE applicant_id = ? ORDER BY id DESC");
            $stmt->execute([$applicant_id]);
            $raw_queries = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $queries = [];
            foreach ($raw_queries as $q) {
                $q['description'] = $q['query_description'] ?? '';
                $q['date_raised'] = $q['raised_date'] ?? substr($q['created_at'] ?? '', 0, 10);
                $queries[] = $q;
            }
            return_json(['success'=>true, 'data'=>$queries, 'queries'=>$queries]);
            break;

        case 'save_bank_query':
            $applicant_id = intval($_POST['applicant_id'] ?? 0);
            $bank_name = trim($_POST['bank_name'] ?? '');
            $query_description = trim($_POST['query_description'] ?? $_POST['description'] ?? '');
            $raised_date = trim($_POST['raised_date'] ?? $_POST['date_raised'] ?? date('Y-m-d'));
            $query_type = trim($_POST['query_type'] ?? 'Other');
            $doc_required = trim($_POST['document_required'] ?? $_POST['doc_required'] ?? '');
            
            if(!$applicant_id || !$bank_name) return_json(['error'=>'Required fields missing (Applicant ID & Bank Name)'], 400);
            if(strlen($query_description) < 5) return_json(['error'=>'Description must be at least 5 chars'], 400);
            if(!strtotime($raised_date)) $raised_date = date('Y-m-d');
            if(!in_array($query_type, ['Document', 'Income', 'Property', 'Legal', 'Technical', 'Other'])) $query_type = 'Other';
            
            $stmt = $db->prepare("INSERT INTO bank_queries (applicant_id, bank_name, query_type, query_description, raised_date, document_required, status, added_by) VALUES (?, ?, ?, ?, ?, ?, 'Open', ?)");
            $stmt->execute([$applicant_id, $bank_name, $query_type, $query_description, $raised_date, $doc_required, $_SESSION['username'] ?? 'RM']);
            
            $db->prepare("UPDATE applicants SET last_activity_date=CURRENT_TIMESTAMP WHERE id=?")->execute([$applicant_id]);
            log_activity("Bank query raised for Applicant #$applicant_id: $bank_name ($query_type)");
            return_json(['success'=>true]);
            break;

        case 'resolve_bank_query':
            $id = intval($_POST['id'] ?? $_POST['query_id'] ?? 0);
            $resolution_notes = trim($_POST['resolution_notes'] ?? '');
            if(!$id) return_json(['error'=>'ID required'], 400);
            if(empty($resolution_notes)) $resolution_notes = 'Resolved by operations';
            
            $db->beginTransaction();
            try {
                $db->prepare("UPDATE bank_queries SET status='Resolved', resolution_notes=?, resolved_date=CURRENT_DATE WHERE id=?")->execute([$resolution_notes, $id]);
                $stmt = $db->prepare("SELECT applicant_id FROM bank_queries WHERE id=?");
                $stmt->execute([$id]);
                $q = $stmt->fetch();
                if($q) {
                    $db->prepare("UPDATE applicants SET last_activity_date=CURRENT_TIMESTAMP WHERE id=?")->execute([$q['applicant_id']]);
                }
                log_activity("Resolved bank query $id");
                $db->commit();
                return_json(['success'=>true]);
            } catch(Exception $e) {
                $db->rollBack();
                return_json(['error'=>$e->getMessage()], 400);
            }
            break;

        case 'get_whatsapp_templates':
            $applicant_id = intval($_GET['applicant_id'] ?? 0);
            $stmt = $db->prepare("SELECT * FROM applicants WHERE id=?");
            $stmt->execute([$applicant_id]);
            $app = $stmt->fetch(PDO::FETCH_ASSOC);
            if(!$app) return_json(['error'=>'Not found'], 404);
            
            $name = $app['customer_name'];
            $loan_id = $app['loan_id'];
            $amount = !empty($app['sanctioned_amount']) ? '₹' . number_format($app['sanctioned_amount']) : (!empty($app['loan_amount_requested']) ? '₹' . number_format($app['loan_amount_requested']) : 'TBD');
            $bank = !empty($app['sanction_bank']) ? $app['sanction_bank'] : (!empty($app['disbursing_bank']) ? $app['disbursing_bank'] : 'Bank Partner');
            $disb_amt = !empty($app['disbursed_amount']) ? '₹' . number_format($app['disbursed_amount']) : $amount;
            $utr = !empty($app['disbursement_ref_no']) ? $app['disbursement_ref_no'] : 'Bank RTGS';
            $date = !empty($app['disbursed_date']) ? $app['disbursed_date'] : date('Y-m-d');
            $roi = !empty($app['sanction_roi']) ? $app['sanction_roi'] . '% p.a.' : (!empty($app['interest_rate']) ? $app['interest_rate'] . '% p.a.' : 'approved rates');
            
            $templates = [
                ['id'=>1, 'name'=>'Welcome', 'icon'=>'hand', 'message'=>"Hello $name, welcome to BFS Circle! We have received your loan file (ID: $loan_id). Our team is dedicated to getting your approval quickly."],
                ['id'=>2, 'name'=>'Document Pending', 'icon'=>'file-warning', 'message'=>"Dear $name, a few documents are pending for your loan file ($loan_id). Kindly submit them today so we can fast-track your bank login."],
                ['id'=>3, 'name'=>'Bank Login', 'icon'=>'landmark', 'message'=>"Good news $name! Your loan file ($loan_id) has been officially logged in with $bank. Credit processing has commenced."],
                ['id'=>4, 'name'=>'Query Alert', 'icon'=>'alert-circle', 'message'=>"Hello $name, $bank has raised an verification query on your file ($loan_id). Please connect with us urgently to resolve it."],
                ['id'=>5, 'name'=>'Sanction Alert', 'icon'=>'party-popper', 'message'=>"🎊 Congratulations $name! Your loan of $amount has been APPROVED & SANCTIONED by $bank at ROI $roi. Our team will contact you for signing."],
                ['id'=>6, 'name'=>'Disbursal Voucher', 'icon'=>'banknote', 'message'=>"🚀 Great news $name! Your loan amount of $disb_amt has been successfully DISBURSED by $bank via Ref/UTR: $utr on $date. Thank you for choosing us!"]
            ];
            return_json(['success'=>true, 'data'=>$templates, 'templates'=>$templates]);
            break;

        case 'generate_tracking_token':
            $applicant_id = intval($_POST['applicant_id'] ?? 0);
            $stmt = $db->prepare("SELECT assigned_to FROM applicants WHERE id=?");
            $stmt->execute([$applicant_id]);
            $app = $stmt->fetch();
            if(!$app) return_json(['error'=>'Not found'], 404);
            
            if($_SESSION['role'] !== 'Admin' && $app['assigned_to'] !== $_SESSION['username']) return_json(['error'=>'Unauthorized'], 403);
            
            $token = bin2hex(random_bytes(16));
            $db->prepare("UPDATE applicants SET tracking_token=?, tracking_token_created=CURRENT_TIMESTAMP WHERE id=?")->execute([$token, $applicant_id]);
            log_activity("Generated tracking token for applicant $applicant_id");
            
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
            $domainName = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $base_dir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
            $url = $protocol . $domainName . $base_dir . "/track.php?token=" . $token;
            return_json(['success'=>true, 'token'=>$token, 'url'=>$url]);
            break;

        case 'save_payout_invoice':
            $applicant_id = intval($_POST['applicant_id'] ?? 0);
            $disbursed_amount = floatval($_POST['disbursed_amount'] ?? 0);
            $commission_pct = floatval($_POST['commission_pct'] ?? 0);
            $notes = trim($_POST['notes'] ?? '');

            if(!$applicant_id) return_json(['error'=>'Applicant ID required'], 400);
            if($commission_pct <= 0 || $commission_pct > 25) return_json(['error'=>'Commission must be between 0.1% and 25%'], 400);
            if($disbursed_amount <= 0) return_json(['error'=>'Disbursed amount must be greater than 0'], 400);

            $stmt = $db->prepare("SELECT id, loan_id, customer_name, loan_type, sanction_bank, loan_amount_requested FROM applicants WHERE id = ?");
            $stmt->execute([$applicant_id]);
            $app = $stmt->fetch(PDO::FETCH_ASSOC);
            if(!$app) return_json(['error'=>'Applicant not found'], 404);

            $gross = round(($disbursed_amount * $commission_pct) / 100, 2);
            $gst_amount = round(($gross * 18) / 100, 2); // 18% GST
            $tds_amount = round(($gross * 2) / 100, 2); // 2% TDS
            $net_receivable = round($gross + $gst_amount - $tds_amount, 2);

            // Check if an existing invoice already exists for this applicant
            $checkInv = $db->prepare("SELECT id, invoice_number, status FROM bank_payout_invoices WHERE applicant_id = ? ORDER BY id DESC LIMIT 1");
            $checkInv->execute([$applicant_id]);
            $existingInv = $checkInv->fetch(PDO::FETCH_ASSOC);

            if ($existingInv) {
                $invoice_number = $existingInv['invoice_number'];
                $upStmt = $db->prepare("UPDATE bank_payout_invoices SET 
                    commission_pct = ?, 
                    disbursed_amount = ?, 
                    gross_amount = ?, 
                    gst_amount = ?, 
                    tds_amount = ?, 
                    net_receivable = ?, 
                    net_invoice_amount = ?, 
                    notes = ?,
                    status = CASE WHEN status = 'Received' THEN 'Received' ELSE 'Sent' END
                    WHERE id = ?");
                $upStmt->execute([$commission_pct, $disbursed_amount, $gross, $gst_amount, $tds_amount, $net_receivable, $net_receivable, $notes, $existingInv['id']]);
                $inv_id = $existingInv['id'];
                $msg = "Bank Claim Invoice $invoice_number updated successfully!";
            } else {
                // Generate a unique invoice number with collision safety
                $base_inv_no = 'INV-' . date('Ymd') . '-' . $app['loan_id'];
                $invoice_number = $base_inv_no;
                $seq = 1;
                while ($db->query("SELECT COUNT(*) FROM bank_payout_invoices WHERE invoice_number = " . $db->quote($invoice_number))->fetchColumn() > 0) {
                    $invoice_number = $base_inv_no . '-' . sprintf("%02d", $seq++);
                }

                $stmt = $db->prepare("INSERT INTO bank_payout_invoices (applicant_id, invoice_number, commission_pct, disbursed_amount, gross_amount, gst_amount, tds_amount, net_receivable, net_invoice_amount, status, notes, invoice_date, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Sent', ?, date('now'), datetime('now'))");
                $stmt->execute([$applicant_id, $invoice_number, $commission_pct, $disbursed_amount, $gross, $gst_amount, $tds_amount, $net_receivable, $net_receivable, $notes]);
                $inv_id = $db->lastInsertId();
                $msg = "Invoice $invoice_number generated successfully!";
            }
            
            $db->prepare("UPDATE applicants SET bank_payout_status='Claim Sent', bank_commission_pct=? WHERE id=?")->execute([$commission_pct, $applicant_id]);
            log_activity("Generated/Updated bank payout invoice $invoice_number of ₹" . number_format($net_receivable, 2), "payout_distribution.php");
            
            return_json([
                'success' => true, 
                'invoice_id' => $inv_id,
                'invoice_number' => $invoice_number,
                'invoice' => [
                    'id' => $inv_id,
                    'invoice_number' => $invoice_number,
                    'customer_name' => $app['customer_name'],
                    'loan_id' => $app['loan_id'],
                    'loan_type' => $app['loan_type'],
                    'sanction_bank' => $app['sanction_bank'] ?: 'Partner Bank',
                    'disbursed_amount' => $disbursed_amount,
                    'commission_pct' => $commission_pct,
                    'gross' => $gross,
                    'gst' => $gst_amount,
                    'tds' => $tds_amount,
                    'net_receivable' => $net_receivable,
                    'date' => date('d-m-Y')
                ],
                'message' => $msg
            ]);
            break;

        case 'get_bank_tax_invoice':
            $applicant_id = intval($_GET['applicant_id'] ?? 0);
            $invoice_id = intval($_GET['invoice_id'] ?? 0);

            if ($invoice_id > 0) {
                $stmt = $db->prepare("SELECT i.*, a.customer_name, a.loan_id, a.loan_type, a.sanction_bank FROM bank_payout_invoices i JOIN applicants a ON i.applicant_id = a.id WHERE i.id = ?");
                $stmt->execute([$invoice_id]);
            } else {
                $stmt = $db->prepare("SELECT i.*, a.customer_name, a.loan_id, a.loan_type, a.sanction_bank FROM bank_payout_invoices i JOIN applicants a ON i.applicant_id = a.id WHERE i.applicant_id = ? ORDER BY i.id DESC LIMIT 1");
                $stmt->execute([$applicant_id]);
            }
            $inv = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$inv && $applicant_id > 0) {
                // Return dynamic draft calculation
                $aStmt = $db->prepare("SELECT id, loan_id, customer_name, loan_type, sanction_bank, loan_amount_requested FROM applicants WHERE id = ?");
                $aStmt->execute([$applicant_id]);
                $app = $aStmt->fetch(PDO::FETCH_ASSOC);
                if ($app) {
                    $disbursed = floatval($app['loan_amount_requested']);
                    $comm_pct = 1.5;
                    $gross = ($disbursed * $comm_pct) / 100;
                    $gst = ($gross * 18) / 100;
                    $tds = ($gross * 2) / 100;
                    $net = $gross + $gst - $tds;
                    $inv = [
                        'id' => 0,
                        'invoice_number' => 'INV-' . date('Ymd') . '-' . $app['loan_id'],
                        'customer_name' => $app['customer_name'],
                        'loan_id' => $app['loan_id'],
                        'loan_type' => $app['loan_type'],
                        'sanction_bank' => $app['sanction_bank'] ?: 'Partner Bank',
                        'disbursed_amount' => $disbursed,
                        'commission_pct' => $comm_pct,
                        'gross_amount' => $gross,
                        'gst_amount' => $gst,
                        'tds_amount' => $tds,
                        'net_receivable' => $net,
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                }
            }

            if (!$inv) return_json(['error' => 'Invoice not found'], 404);
            return_json(['success' => true, 'invoice' => $inv]);
            break;

        case 'send_bank_payout_reminder_email':
            $applicant_id = intval($_POST['applicant_id'] ?? 0);
            if (!$applicant_id) return_json(['error' => 'Applicant ID required'], 400);

            $stmt = $db->prepare("SELECT id, loan_id, customer_name, loan_type, sanction_bank, loan_amount_requested, assigned_to FROM applicants WHERE id = ?");
            $stmt->execute([$applicant_id]);
            $app = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$app) return_json(['error' => 'Applicant case not found'], 404);

            $bank_name = $app['sanction_bank'] ?: 'Partner Bank';
            $disbursed = floatval($app['loan_amount_requested']);
            $est_claim = ($disbursed * 1.5) / 100;
            $inv_no = 'INV-' . date('Ymd') . '-' . $app['loan_id'];

            $subject = "[URGENT REMINDER] Payout Clearance Claim: {$app['customer_name']} (Loan #{$app['loan_id']}) - BFS Circle";
            $message_body = "DEAR BANK NODAL OFFICER / LOAN HUB MANAGER,\n\n" .
                            "Greetings from BFS Financial Services Private Limited.\n\n" .
                            "This is an automated formal escalation regarding the pending DSA commission payout for the following loan case successfully disbursed through {$bank_name}:\n\n" .
                            "--------------------------------------------------\n" .
                            "• Customer Name: {$app['customer_name']}\n" .
                            "• Loan Account Ref: {$app['loan_id']}\n" .
                            "• Loan Category: {$app['loan_type']}\n" .
                            "• Disbursed Loan Volume: ₹" . number_format($disbursed, 2) . "\n" .
                            "• Net Expected Claim Amount: ₹" . number_format($est_claim, 2) . "\n" .
                            "• Proforma Invoice Ref: {$inv_no}\n" .
                            "--------------------------------------------------\n\n" .
                            "OUR CORPORATE BANK TRANSFER DETAILS:\n" .
                            "• Account Name: BFS FINANCIAL SERVICES PVT LTD\n" .
                            "• Bank Name: HDFC BANK LTD\n" .
                            "• Account Number: 50200019840291\n" .
                            "• IFSC Code: HDFC0000123\n" .
                            "• Branch: New Delhi Main Branch\n\n" .
                            "Kindly arrange for the credit of the commission amount at the earliest and share the bank transaction UTR reference.\n\n" .
                            "Warm Regards,\n" .
                            "Accounts & Disbursal Control Cell\n" .
                            "BFS Circle | BFS Financial Services Pvt Ltd\n" .
                            "Phone: +91 9876543210 | Email: accounts@bfscircle.com";

            // Log activity
            log_activity("Sent Bank Payout Reminder Email to $bank_name for Loan #{$app['loan_id']} ({$app['customer_name']})", "payout_distribution.php");

            return_json([
                'success' => true,
                'message' => "Formal payout reminder email dispatched to {$bank_name} for {$app['customer_name']} (Ref: {$app['loan_id']})!",
                'email_preview' => [
                    'subject' => $subject,
                    'body' => $message_body
                ]
            ]);
            break;

        case 'get_payout_invoices':
            $stmt = $db->query("SELECT i.*, 
                                       COALESCE(a.loan_id, 'N/A') as loan_id, 
                                       COALESCE(a.customer_name, 'Direct Client') as customer_name, 
                                       COALESCE(i.bank_name, a.sanction_bank, 'Partner Bank') as sanction_bank,
                                       COALESCE(i.net_receivable, i.net_invoice_amount, 0) as net_receivable,
                                       COALESCE(i.shortfall_amount, i.shortfall, 0) as shortfall_amount
                                FROM bank_payout_invoices i 
                                LEFT JOIN applicants a ON i.applicant_id = a.id 
                                ORDER BY i.created_at DESC");
            $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return_json(['success'=>true, 'invoices'=>$invoices]);
            break;

        case 'mark_payout_received':
            $id = intval($_POST['id'] ?? 0);
            $received_amount = floatval($_POST['received_amount'] ?? 0);
            $received_date = trim($_POST['received_date'] ?? '');
            $bank_utr = trim($_POST['bank_utr'] ?? '');

            if(!$id) return_json(['error'=>'Invoice ID required'], 400);
            if($received_amount <= 0) return_json(['error'=>'Received amount must be > 0'], 400);
            if(empty($bank_utr) || strlen($bank_utr) < 6) return_json(['error'=>'Valid UTR required'], 400);
            if(empty($received_date)) return_json(['error'=>'Date required'], 400);

            $stmt = $db->prepare("SELECT * FROM bank_payout_invoices WHERE id=?");
            $stmt->execute([$id]);
            $inv = $stmt->fetch();
            if(!$inv) return_json(['error'=>'Not found'], 404);

            $shortfall = $inv['net_receivable'] - $received_amount;
            $status = ($shortfall > 0) ? 'Partial' : 'Received';

            $db->prepare("UPDATE bank_payout_invoices SET received_amount=?, shortfall_amount=?, bank_utr=?, received_date=?, status=? WHERE id=?")
               ->execute([$received_amount, $shortfall, $bank_utr, $received_date, $status, $id]);
            
            $db->prepare("UPDATE applicants SET bank_payout_status=?, bank_payout_received_amount=?, bank_payout_received_date=? WHERE id=?")
               ->execute([$status, $received_amount, $received_date, $inv['applicant_id']]);

            log_activity("Marked payout invoice {$inv['invoice_number']} as $status. UTR: $bank_utr");
            return_json(['success'=>true, 'shortfall'=>$shortfall]);
            break;

        case 'get_sla_alerts':
            $stmt = $db->query("SELECT id, loan_id, customer_name, overall_status FROM applicants WHERE overall_status NOT IN ('Completed', 'Rejected') AND last_activity_date < datetime('now', '-3 days')");
            $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return_json(['success'=>true, 'alerts'=>$alerts]);
            break;

        // ===== RM WALLET: Summary =====
        case 'get_rm_wallet_summary':
            $rm_username = $_SESSION['username'] ?? '';
            $rm_user_id  = (int)($_SESSION['user_id'] ?? 0);
            if(!$rm_username) return_json(['error'=>'Unauthorized'], 401);

            // If Admin is viewing a specific RM
            if(($_SESSION['role'] ?? '') === 'Admin' && !empty($_GET['rm_user_id'])) {
                $rm_user_id = (int)$_GET['rm_user_id'];
            }

            $month_start = date('Y-m-01 00:00:00');

            $s = $db->prepare("SELECT
                COALESCE(SUM(gross_payout), 0)                                         AS total_gross,
                COALESCE(SUM(CASE WHEN status='Paid'    THEN net_payable ELSE 0 END),0) AS total_received,
                COALESCE(SUM(CASE WHEN status='Pending' THEN net_payable ELSE 0 END),0) AS total_pending,
                COALESCE(SUM(CASE WHEN status='Paid' AND paid_on >= ? THEN net_payable ELSE 0 END),0) AS this_month
                FROM payout_distributions
                WHERE payee_user_id = ?");
            $s->execute([$month_start, $rm_user_id]);
            $sum = $s->fetch(PDO::FETCH_ASSOC);

            return_json(['success'=>true, 'summary'=> [
                'total_gross'    => (float)$sum['total_gross'],
                'total_received' => (float)$sum['total_received'],
                'total_pending'  => (float)$sum['total_pending'],
                'this_month'     => (float)$sum['this_month'],
            ]]);
            break;

        // ===== RM WALLET: Transaction History =====
        case 'get_rm_wallet_history':
            $rm_user_id = (int)($_SESSION['user_id'] ?? 0);
            if(!$rm_user_id) return_json(['error'=>'Unauthorized'], 401);

            // Admin viewing specific RM
            if(($_SESSION['role'] ?? '') === 'Admin' && !empty($_GET['rm_user_id'])) {
                $rm_user_id = (int)$_GET['rm_user_id'];
            }

            $filter_status = trim($_GET['status'] ?? '');
            $page          = max(1, (int)($_GET['page'] ?? 1));
            $per_page      = min(50, max(5, (int)($_GET['per_page'] ?? 10)));
            $offset        = ($page - 1) * $per_page;

            $where = "pd.payee_user_id = ?";
            $params = [$rm_user_id];
            if($filter_status) {
                $where .= " AND pd.status = ?";
                $params[] = $filter_status;
            }

            // Total count
            $ct = $db->prepare("SELECT COUNT(*) FROM payout_distributions pd WHERE $where");
            $ct->execute($params);
            $total = (int)$ct->fetchColumn();

            // Records
            $params[] = $per_page;
            $params[] = $offset;
            $stmt = $db->prepare("
                SELECT pd.*, a.customer_name, a.loan_id, a.loan_type, a.loan_amount_requested AS total_loan_amount
                FROM payout_distributions pd
                LEFT JOIN applicants a ON a.id = pd.applicant_id
                WHERE $where
                ORDER BY pd.created_at DESC
                LIMIT ? OFFSET ?");
            $stmt->execute($params);
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return_json(['success'=>true, 'total'=>$total, 'page'=>$page, 'records'=>$records]);
            break;

        // ===== CO-APPLICANT: Add Co-Applicant =====
        case 'add_co_applicant':
            $applicant_id = intval($_POST['applicant_id'] ?? $_GET['applicant_id'] ?? 0);
            $full_name = trim($_POST['full_name'] ?? '');
            $relationship = trim($_POST['relationship'] ?? 'Co-Applicant');
            $mobile = trim($_POST['mobile'] ?? '');
            $monthly_income = floatval($_POST['monthly_income'] ?? 0);
            $is_financial = trim($_POST['is_financial'] ?? 'Yes');
            $employment_type = trim($_POST['employment_type'] ?? 'Salaried');

            if (!$applicant_id) return_json(['error' => 'Applicant ID required'], 400);
            if (empty($full_name)) return_json(['error' => 'Full Name is required for Co-Applicant'], 400);

            // Ensure table exists
            $db->exec("CREATE TABLE IF NOT EXISTS co_applicants (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                applicant_id INTEGER NOT NULL,
                is_financial TEXT DEFAULT 'Yes',
                relationship TEXT,
                full_name TEXT NOT NULL,
                mobile TEXT,
                email TEXT,
                dob DATE,
                pan_number TEXT,
                aadhar_number TEXT,
                same_address TEXT,
                address TEXT,
                pincode TEXT,
                state TEXT,
                city TEXT,
                employment_type TEXT,
                monthly_income REAL DEFAULT 0,
                current_emis REAL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            $ins = $db->prepare("INSERT INTO co_applicants (applicant_id, is_financial, relationship, full_name, mobile, employment_type, monthly_income) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $ins->execute([$applicant_id, $is_financial, $relationship, $full_name, $mobile, $employment_type, $monthly_income]);
            $co_id = $db->lastInsertId();

        case 'get_referral_janam_kundli':
            $ref_id_param = intval($_GET['id'] ?? 0);
            $ref_code = trim($_GET['referral_id'] ?? '');

            $referral = null;
            if ($ref_id_param > 0) {
                $stmt = $db->prepare("SELECT * FROM referrals WHERE id = ?");
                $stmt->execute([$ref_id_param]);
                $referral = $stmt->fetch(PDO::FETCH_ASSOC);
            } elseif (!empty($ref_code)) {
                $stmt = $db->prepare("SELECT * FROM referrals WHERE referral_id = ?");
                $stmt->execute([$ref_code]);
                $referral = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            if (!$referral && $ref_id_param > 0) {
                $stmt_u = $db->prepare("SELECT * FROM users WHERE id = ?");
                $stmt_u->execute([$ref_id_param]);
                $u = $stmt_u->fetch(PDO::FETCH_ASSOC);
                if ($u) {
                    $referral = [
                        'id' => $u['id'],
                        'referral_id' => $u['username'],
                        'referrer_type' => $u['role'],
                        'full_name' => $u['name'],
                        'mobile' => $u['mobile'] ?? 'N/A',
                        'email' => $u['email'] ?? 'N/A',
                        'city_state' => 'N/A',
                        'commission_rate' => '1.5%',
                        'status' => 'Active',
                        'created_at' => $u['created_at'] ?? date('Y-m-d H:i:s'),
                    ];
                }
            }

            if (!$referral) {
                return_json(['error' => 'Referral Partner not found.'], 404);
            }

            $ref_code = $referral['referral_id'];

            // Fetch all leads & applications sourced by this partner
            $leads_stmt = $db->prepare("
                SELECT 'lead' as record_type, id, lead_name as customer_name, mobile, email, requirement as loan_type, 
                       loan_amount, stage as status, created_at, assigned_to
                FROM leads 
                WHERE added_by = ? OR added_by = ?
                UNION ALL
                SELECT 'applicant' as record_type, id, customer_name, mobile, email, loan_type, 
                       loan_amount_requested as loan_amount, overall_status as status, created_at, assigned_to
                FROM applicants 
                WHERE referral_id = ? OR added_by = ?
                ORDER BY created_at DESC
            ");
            $leads_stmt->execute([$ref_code, $referral['full_name'], $ref_code, $ref_code]);
            $all_sourced_leads = $leads_stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate stats
            $total_sourced = count($all_sourced_leads);
            $converted_count = 0;
            $total_disbursed_volume = 0;

            foreach ($all_sourced_leads as $item) {
                $st = strtolower($item['status'] ?? '');
                if (in_array($st, ['converted', 'disbursed', 'completed', 'phase 4', 'phase 5'])) {
                    $converted_count++;
                    $total_disbursed_volume += floatval($item['loan_amount'] ?? 0);
                }
            }

            // Commission Calculation Rate
            $rate_str = $referral['commission_rate'] ?? '1.5%';
            $rate_num = floatval(preg_replace('/[^0-9.]/', '', $rate_str));
            if ($rate_num <= 0) $rate_num = 1.5;

            $total_commission_earned = round(($total_disbursed_volume * $rate_num) / 100, 2);

            // Fetch Paid Payouts Ledger
            $payout_stmt = $db->prepare("SELECT *, COALESCE(amount, net_payable) as amount, COALESCE(utr_ref, transaction_ref) as utr_ref, COALESCE(payment_date, paid_on) as payment_date FROM payout_distributions WHERE referral_id = ? OR recipient_id = ? ORDER BY created_at DESC");
            $payout_stmt->execute([$ref_code, $ref_code]);
            $payouts_list = $payout_stmt->fetchAll(PDO::FETCH_ASSOC);

            $total_paid = 0;
            foreach ($payouts_list as $p) {
                if (strtolower($p['status'] ?? '') === 'paid' || empty($p['status'])) {
                    $total_paid += floatval($p['amount'] ?? 0);
                }
            }

            $pending_payout = max(0, $total_commission_earned - $total_paid);

            // Activity Log
            $act_stmt = $db->prepare("SELECT * FROM activities WHERE description LIKE ? OR target_user = ? ORDER BY created_at DESC LIMIT 20");
            $act_stmt->execute(["%$ref_code%", $ref_code]);
            $activities = $act_stmt->fetchAll(PDO::FETCH_ASSOC);

            return_json([
                'success' => true,
                'referral' => $referral,
                'stats' => [
                    'total_sourced' => $total_sourced,
                    'converted_count' => $converted_count,
                    'total_disbursed_volume' => $total_disbursed_volume,
                    'commission_rate' => $rate_num . '%',
                    'total_commission_earned' => $total_commission_earned,
                    'total_paid' => $total_paid,
                    'pending_payout' => $pending_payout
                ],
                'sourced_leads' => $all_sourced_leads,
                'payouts' => $payouts_list,
                'activities' => $activities
            ]);
            break;

        case 'record_referral_payout':
            if (($_SESSION['role'] ?? '') !== 'Admin') return_json(['error' => 'Unauthorized Access'], 403);
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request Method'], 405);

            $ref_code     = trim($_POST['referral_id'] ?? '');
            $amount       = floatval($_POST['amount'] ?? 0);
            $payment_mode = trim($_POST['payment_mode'] ?? 'Bank Transfer');
            $utr_ref      = trim($_POST['utr_ref'] ?? '');
            $payment_date = trim($_POST['payment_date'] ?? date('Y-m-d'));
            $remarks      = trim($_POST['remarks'] ?? '');

            if (empty($ref_code)) return_json(['error' => 'Referral Partner ID is required.'], 400);
            if ($amount <= 0) return_json(['error' => 'Valid payout amount is required.'], 400);

            $stmt = $db->prepare("INSERT INTO payout_distributions (applicant_id, payee_type, referral_id, recipient_id, recipient_type, amount, net_payable, status, payment_mode, utr_ref, transaction_ref, payment_date, remarks, created_by, created_at) VALUES (0, 'referral', ?, ?, 'referral', ?, ?, 'Paid', ?, ?, ?, ?, ?, ?, datetime('now'))");
            $stmt->execute([$ref_code, $ref_code, $amount, $amount, $payment_mode, $utr_ref, $utr_ref, $payment_date, $remarks, $_SESSION['username'] ?? 'admin']);
            return_json(['success' => true, 'message' => 'Partner payout recorded successfully.']);
            break;

        case 'get_payees_directory':
            // 1. Staff / Employees payees from users + employees table
            $emp_stmt = $db->query("
                SELECT 
                    u.id as user_id,
                    0 as referral_id,
                    'user' as source_type,
                    COALESCE(NULLIF(u.name, ''), u.username) as payee_name,
                    u.role,
                    COALESCE(emp.mobile, u.username) as mobile_email,
                    COALESCE(emp.bank_name, '') as bank_name,
                    COALESCE(emp.bank_account_no, '') as account_number,
                    COALESCE(emp.bank_ifsc, '') as ifsc_code,
                    COALESCE(emp.pan_number, '') as pan_number,
                    COALESCE(emp.id, 0) as employee_id
                FROM users u
                LEFT JOIN employees emp ON (emp.user_id = u.id OR emp.official_email = u.username)
                WHERE u.role IN ('Partner', 'Builder', 'CA', 'Agent', 'Staff', 'Admin', 'RM')
                ORDER BY payee_name ASC
            ");
            $payees = $emp_stmt->fetchAll(PDO::FETCH_ASSOC);

            // 2. Also fetch Referrals / Partners from referrals table
            $ref_stmt = $db->query("
                SELECT 
                    0 as user_id,
                    r.id as referral_id,
                    'referral' as source_type,
                    r.full_name as payee_name,
                    r.referrer_type as role,
                    COALESCE(r.mobile, r.email, '') as mobile_email,
                    COALESCE(r.bank_name, '') as bank_name,
                    COALESCE(r.account_number, '') as account_number,
                    COALESCE(r.ifsc_code, '') as ifsc_code,
                    COALESCE(r.pan_number, '') as pan_number,
                    0 as employee_id
                FROM referrals r
                ORDER BY r.full_name ASC
            ");
            $referral_payees = $ref_stmt->fetchAll(PDO::FETCH_ASSOC);

            return_json(['success' => true, 'payees' => array_merge($payees, $referral_payees)]);
            break;

        case 'update_payee_bank_details':
            if (($_SESSION['role'] ?? '') !== 'Admin') return_json(['error' => 'Unauthorized Access.'], 403);
            $source_type = trim($_POST['source_type'] ?? 'user');
            $user_id = intval($_POST['user_id'] ?? 0);
            $referral_id = intval($_POST['referral_id'] ?? 0);
            $bank_name = trim($_POST['bank_name'] ?? '');
            $account_number = trim($_POST['account_number'] ?? '');
            $ifsc_code = trim($_POST['ifsc_code'] ?? '');
            $pan_number = trim($_POST['pan_number'] ?? '');

            if ($source_type === 'user' && $user_id > 0) {
                // Check if employee record exists
                $chk = $db->prepare("SELECT id FROM employees WHERE user_id = ?");
                $chk->execute([$user_id]);
                $emp_id = $chk->fetchColumn();
                if ($emp_id) {
                    $u = $db->prepare("UPDATE employees SET bank_name = ?, bank_account_no = ?, bank_ifsc = ?, pan_number = ? WHERE id = ?");
                    $u->execute([$bank_name, $account_number, $ifsc_code, $pan_number, $emp_id]);
                } else {
                    $u_stmt = $db->prepare("SELECT username, name FROM users WHERE id = ?");
                    $u_stmt->execute([$user_id]);
                    $u_row = $u_stmt->fetch(PDO::FETCH_ASSOC);
                    $ins = $db->prepare("INSERT INTO employees (user_id, official_email, bank_name, bank_account_no, bank_ifsc, pan_number) VALUES (?, ?, ?, ?, ?, ?)");
                    $ins->execute([$user_id, $u_row['username'] ?? '', $bank_name, $account_number, $ifsc_code, $pan_number]);
                }
                log_activity("Updated banking details for user ID $user_id", "payout_distribution.php");
                return_json(['success' => true, 'message' => 'Payee bank details updated successfully.']);
            } else if ($source_type === 'referral' && $referral_id > 0) {
                $u = $db->prepare("UPDATE referrals SET bank_name = ?, account_number = ?, ifsc_code = ?, pan_number = ? WHERE id = ?");
                $u->execute([$bank_name, $account_number, $ifsc_code, $pan_number, $referral_id]);
                log_activity("Updated banking details for referral partner ID $referral_id", "payout_distribution.php");
                return_json(['success' => true, 'message' => 'Partner bank details updated successfully.']);
            }
            return_json(['error' => 'Invalid payee target.'], 400);
            break;

        case 'get_bank_360_analytics':
            $bank_name = trim($_GET['bank_name'] ?? '');
            $banker_id = intval($_GET['banker_id'] ?? 0);

            $all_banks_stmt = $db->query("
                SELECT DISTINCT bank_name FROM (
                    SELECT bank_name FROM bankers WHERE bank_name IS NOT NULL AND bank_name != ''
                    UNION
                    SELECT bank_name FROM applicant_bank_logins WHERE bank_name IS NOT NULL AND bank_name != ''
                    UNION
                    SELECT bank_name FROM bank_payout_invoices WHERE bank_name IS NOT NULL AND bank_name != ''
                ) ORDER BY bank_name ASC
            ");
            $all_bank_names = $all_banks_stmt->fetchAll(PDO::FETCH_COLUMN);

            if (empty($bank_name) && $banker_id > 0) {
                $b_stmt = $db->prepare("SELECT bank_name FROM bankers WHERE id = ?");
                $b_stmt->execute([$banker_id]);
                $bank_name = $b_stmt->fetchColumn();
            }

            if (empty($bank_name) && !empty($all_bank_names)) {
                $bank_name = $all_bank_names[0];
            }

            if (empty($bank_name)) {
                return_json(['error' => 'No bank specified or found in system.'], 404);
            }

            // 1. Registered Bankers for this Bank
            $b_list_stmt = $db->prepare("SELECT * FROM bankers WHERE bank_name LIKE ? ORDER BY created_at DESC");
            $b_list_stmt->execute(["%$bank_name%"]);
            $bankers_list = $b_list_stmt->fetchAll(PDO::FETCH_ASSOC);

            // 2. Submitted Loan Files to this Bank
            $logins_stmt = $db->prepare("
                SELECT l.*, a.customer_name, a.mobile, a.email, a.loan_type, a.loan_amount_requested, a.assigned_to
                FROM applicant_bank_logins l
                LEFT JOIN applicants a ON a.id = l.applicant_id
                WHERE l.bank_name LIKE ?
                ORDER BY l.created_at DESC
            ");
            $logins_stmt->execute(["%$bank_name%"]);
            $submitted_files = $logins_stmt->fetchAll(PDO::FETCH_ASSOC);

            // Aggregate metrics
            $total_files = count($submitted_files);
            $sanctioned_count = 0;
            $total_disbursed_volume = 0;

            foreach ($submitted_files as $f) {
                $st = strtolower($f['status'] ?? '');
                if ($st === 'sanctioned' || $st === 'disbursed' || $st === 'completed') {
                    $sanctioned_count++;
                }
                $disb_amt = floatval($f['sanctioned_amount'] ?? 0);
                if ($disb_amt <= 0) $disb_amt = floatval($f['loan_amount_requested'] ?? 0);
                if ($st === 'sanctioned' || $st === 'disbursed' || $st === 'completed') {
                    $total_disbursed_volume += $disb_amt;
                }
            }

            // 3. Bank Queries
            $queries_stmt = $db->prepare("SELECT q.*, a.customer_name FROM bank_queries q LEFT JOIN applicants a ON a.id = q.applicant_id WHERE q.bank_name LIKE ? ORDER BY q.created_at DESC");
            $queries_stmt->execute(["%$bank_name%"]);
            $queries_list = $queries_stmt->fetchAll(PDO::FETCH_ASSOC);

            // 4. Bank Payout Invoices
            $payout_stmt = $db->prepare("SELECT * FROM bank_payout_invoices WHERE bank_name LIKE ? ORDER BY created_at DESC");
            $payout_stmt->execute(["%$bank_name%"]);
            $invoices_list = $payout_stmt->fetchAll(PDO::FETCH_ASSOC);

            $total_invoiced = 0;
            $total_received = 0;
            foreach ($invoices_list as $inv) {
                $total_invoiced += floatval($inv['net_invoice_amount'] ?? 0);
                if (strtolower($inv['status'] ?? '') === 'received') {
                    $total_received += floatval($inv['received_amount'] ?? $inv['net_invoice_amount'] ?? 0);
                }
            }

            return_json([
                'success' => true,
                'bank_name' => $bank_name,
                'all_bank_names' => $all_bank_names,
                'stats' => [
                    'total_files' => $total_files,
                    'sanctioned_count' => $sanctioned_count,
                    'total_disbursed_volume' => $total_disbursed_volume,
                    'total_invoiced' => $total_invoiced,
                    'total_received' => $total_received,
                    'pending_payout' => max(0, $total_invoiced - $total_received)
                ],
                'bankers' => $bankers_list,
                'submitted_files' => $submitted_files,
                'bank_queries' => $queries_list,
                'payout_invoices' => $invoices_list
            ]);
            break;

        case 'record_banker_payout_invoice':
            if (($_SESSION['role'] ?? '') !== 'Admin') return_json(['error' => 'Unauthorized Access'], 403);
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request Method'], 405);

            $bank_name       = trim($_POST['bank_name'] ?? '');
            $banker_id       = intval($_POST['banker_id'] ?? 0);
            $disbursed_amt   = floatval($_POST['disbursed_amount'] ?? 0);
            $commission_pct  = floatval($_POST['commission_pct'] ?? 1.5);
            $received_amount = floatval($_POST['received_amount'] ?? 0);
            $bank_utr        = trim($_POST['bank_utr'] ?? '');
            $received_date   = trim($_POST['received_date'] ?? date('Y-m-d'));
            $notes           = trim($_POST['notes'] ?? '');

            if (empty($bank_name)) return_json(['error' => 'Bank Name is required.'], 400);
            if ($received_amount <= 0 && $disbursed_amt <= 0) return_json(['error' => 'Valid payout or disbursed amount is required.'], 400);

            $net_inv = $received_amount > 0 ? $received_amount : round(($disbursed_amt * $commission_pct) / 100, 2);
            $inv_num = 'INV-' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $bank_name), 0, 4)) . '-' . date('Ymd') . '-' . rand(100, 999);

            $stmt = $db->prepare("INSERT INTO bank_payout_invoices (applicant_id, banker_id, invoice_number, bank_name, disbursed_amount, commission_pct, net_invoice_amount, received_amount, received_date, bank_utr, status, notes, created_by, created_at) VALUES (0, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Received', ?, ?, datetime('now'))");
            $stmt->execute([$banker_id, $inv_num, $bank_name, $disbursed_amt, $commission_pct, $net_inv, $net_inv, $received_date, $bank_utr, $notes, $_SESSION['username'] ?? 'admin']);

            log_activity("Recorded Bank Payout Invoice of ₹" . number_format($net_inv) . " for " . $bank_name . " (UTR: " . $bank_utr . ")", "view_bank.php");

            return_json(['success' => true, 'message' => 'Bank Payout Invoice recorded successfully.', 'invoice_number' => $inv_num]);
            break;

        case 'raise_rm_query':
            if (($_SESSION['role'] ?? '') !== 'Admin') return_json(['error' => 'Unauthorized Access. Admin role required.'], 403);
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request Method'], 405);

            $assigned_rm       = trim($_POST['assigned_rm'] ?? '');
            $applicant_id      = intval($_POST['applicant_id'] ?? 0);
            $query_title       = trim($_POST['query_title'] ?? '');
            $query_description = trim($_POST['query_description'] ?? '');
            $priority          = trim($_POST['priority'] ?? 'Normal');
            $due_date          = trim($_POST['due_date'] ?? '');

            if (empty($assigned_rm) || empty($query_title) || empty($query_description)) {
                return_json(['error' => 'Target Officer, Task Title, and Instructions are required.'], 400);
            }

            if (empty($due_date)) {
                $remind_at = date('Y-m-d 18:00:00');
            } else {
                $remind_at = str_replace('T', ' ', $due_date);
                if (strlen($remind_at) === 16) $remind_at .= ':00';
            }

            $rem_prio = ($priority === 'Urgent') ? 'High' : (($priority === 'High') ? 'High' : 'Medium');
            $ref_type = ($applicant_id > 0) ? 'Loan' : 'General';
            $ref_id   = ($applicant_id > 0) ? (string)$applicant_id : '0';

            $ref_label = '';
            if ($applicant_id > 0) {
                try {
                    $c_stmt = $db->prepare("SELECT customer_name, loan_id FROM applicants WHERE id = ?");
                    $c_stmt->execute([$applicant_id]);
                    $app_row = $c_stmt->fetch(PDO::FETCH_ASSOC);
                    if ($app_row) {
                        $ref_label = $app_row['customer_name'] . ' (' . ($app_row['loan_id'] ?: '#' . $applicant_id) . ')';
                    }
                } catch (Exception $e) {}
            }

            if ($assigned_rm === 'ALL_RMS') {
                $rm_stmt = $db->query("SELECT username FROM users WHERE role IN ('RM', 'Staff') AND status = 'active'");
                $rms = $rm_stmt->fetchAll(PDO::FETCH_COLUMN);
                if (empty($rms)) {
                    $rms = ['ANUJ'];
                }
                $stmt = $db->prepare("INSERT INTO rm_internal_queries (assigned_rm, applicant_id, query_title, query_description, priority, status, raised_by, created_at) VALUES (?, ?, ?, ?, ?, 'Open', ?, datetime('now'))");
                $rem_stmt = $db->prepare("INSERT INTO reminders (lead_type, lead_id, assigned_to, remind_at, notes, status, title, priority, reference_type, reference_id, reference_label, reminder_category) VALUES (?, ?, ?, ?, ?, 'Pending', ?, ?, ?, ?, ?, 'Task')");

                foreach ($rms as $u) {
                    $stmt->execute([$u, $applicant_id > 0 ? $applicant_id : null, $query_title, $query_description, $priority, $_SESSION['username'] ?? 'Admin']);
                    try {
                        $rem_stmt->execute([$ref_type, $applicant_id, $u, $remind_at, $query_description, $query_title, $rem_prio, $ref_type, $ref_id, $ref_label]);
                    } catch (Exception $re) {}
                }
                log_activity("[" . ($_SESSION['username'] ?? 'Admin') . "] Broadcast task to ALL RMs: " . $query_title, "rm/dashboard.php");
                return_json(['success' => true, 'message' => 'Task broadcast to all Relationship Managers successfully.']);
            }

            $stmt = $db->prepare("INSERT INTO rm_internal_queries (assigned_rm, applicant_id, query_title, query_description, priority, status, raised_by, created_at) VALUES (?, ?, ?, ?, ?, 'Open', ?, datetime('now'))");
            $stmt->execute([$assigned_rm, $applicant_id > 0 ? $applicant_id : null, $query_title, $query_description, $priority, $_SESSION['username'] ?? 'Admin']);

            try {
                $rem_stmt = $db->prepare("INSERT INTO reminders (lead_type, lead_id, assigned_to, remind_at, notes, status, title, priority, reference_type, reference_id, reference_label, reminder_category) VALUES (?, ?, ?, ?, ?, 'Pending', ?, ?, ?, ?, ?, 'Task')");
                $rem_stmt->execute([$ref_type, $applicant_id, $assigned_rm, $remind_at, $query_description, $query_title, $rem_prio, $ref_type, $ref_id, $ref_label]);
            } catch (Exception $re) {}

            log_activity("[" . ($_SESSION['username'] ?? 'Admin') . "] Assigned task to RM (" . $assigned_rm . "): " . $query_title, "rm/dashboard.php");

            return_json(['success' => true, 'message' => "Task successfully assigned to {$assigned_rm} and added to their reminders schedule."]);
            break;

        case 'get_rm_queries':
            $rm_user = trim($_GET['rm_user'] ?? $_SESSION['username'] ?? 'ALL');
            $status  = trim($_GET['status'] ?? 'ALL');

            $sql = "SELECT q.*, a.customer_name, a.loan_id, a.loan_type FROM rm_internal_queries q LEFT JOIN applicants a ON q.applicant_id = a.id WHERE 1=1";
            $params = [];

            if ($rm_user !== 'ALL') {
                $sql .= " AND q.assigned_rm = ?";
                $params[] = $rm_user;
            }

            if ($status !== 'ALL') {
                $sql .= " AND q.status = ?";
                $params[] = $status;
            }

            $sql .= " ORDER BY q.id DESC LIMIT 100";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $queries = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return_json(['success' => true, 'queries' => $queries]);
            break;

        case 'resolve_rm_query':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request Method'], 405);

            $query_id = intval($_POST['query_id'] ?? 0);
            $rm_reply = trim($_POST['rm_reply'] ?? '');
            $new_status = trim($_POST['status'] ?? 'Resolved');

            if ($query_id <= 0 || empty($rm_reply)) {
                return_json(['error' => 'Query ID and Reply message are required.'], 400);
            }

            $stmt = $db->prepare("UPDATE rm_internal_queries SET rm_reply = ?, status = ?, resolved_at = datetime('now') WHERE id = ?");
            $stmt->execute([$rm_reply, $new_status, $query_id]);

            log_activity("[" . ($_SESSION['username'] ?? 'User') . "] Responded to query #" . $query_id . " (" . $new_status . ")", "rm/dashboard.php");

            return_json(['success' => true, 'message' => 'Query updated successfully.']);
            break;

        case 'get_rm_detailed_dossier':
            $rm_user = trim($_GET['rm_user'] ?? '');
            if (empty($rm_user)) return_json(['error' => 'RM Username required.'], 400);

            // Fetch RM user profile
            $u_stmt = $db->prepare("SELECT id, username, name, full_name, role, monthly_target, commission_rate, created_at FROM users WHERE username = ?");
            $u_stmt->execute([$rm_user]);
            $rm_info = $u_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$rm_info) return_json(['error' => 'RM officer not found.'], 404);

            // Fetch assigned applicants
            $apps_stmt = $db->prepare("SELECT id, loan_id, customer_name, mobile, loan_type, loan_amount_requested, sanctioned_amount, sanction_bank, overall_status, login_fee_status, rm_next_followup_date, created_at FROM applicants WHERE (assigned_to = ? OR (assigned_to IS NULL AND added_by = ?)) ORDER BY id DESC");
            $apps_stmt->execute([$rm_user, $rm_user]);
            $assigned_files = $apps_stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch full activities log for this RM ("Kon kya kar raha hai")
            $acts_stmt = $db->prepare("SELECT * FROM activities WHERE (username = ? OR description LIKE ?) ORDER BY id DESC LIMIT 50");
            $acts_stmt->execute([$rm_user, "%" . $rm_user . "%"]);
            $activity_log = $acts_stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch queries assigned to this RM
            $q_stmt = $db->prepare("SELECT q.*, a.customer_name, a.loan_id FROM rm_internal_queries q LEFT JOIN applicants a ON q.applicant_id = a.id WHERE q.assigned_rm = ? ORDER BY q.id DESC");
            $q_stmt->execute([$rm_user]);
            $queries = $q_stmt->fetchAll(PDO::FETCH_ASSOC);

            return_json([
                'success' => true,
                'rm_info' => $rm_info,
                'assigned_files' => $assigned_files,
                'activity_log' => $activity_log,
                'queries' => $queries
            ]);
            break;

        case 'record_employee_attendance':
            if (($_SESSION['role'] ?? '') !== 'Admin') return_json(['error' => 'Unauthorized Access. Admin role required.'], 403);
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request Method'], 405);

            $emp_id          = intval($_POST['employee_id'] ?? 0);
            $emp_username    = trim($_POST['emp_username'] ?? '');
            $att_date        = trim($_POST['attendance_date'] ?? date('Y-m-d'));
            $status          = trim($_POST['status'] ?? 'Present');
            $check_in_time   = trim($_POST['check_in_time'] ?? '');
            $check_out_time  = trim($_POST['check_out_time'] ?? '');
            $notes           = trim($_POST['notes'] ?? '');

            if ($emp_id <= 0 || empty($emp_username)) {
                return_json(['error' => 'Employee ID and Username are required.'], 400);
            }

            // Construct full datetime if time provided
            $cin_full  = !empty($check_in_time)  ? ($att_date . ' ' . $check_in_time . ':00')  : null;
            $cout_full = !empty($check_out_time) ? ($att_date . ' ' . $check_out_time . ':00') : null;

            // Calculate total minutes if both times provided
            $total_mins = 0;
            if ($cin_full && $cout_full) {
                $t1 = strtotime($cin_full);
                $t2 = strtotime($cout_full);
                if ($t2 > $t1) {
                    $total_mins = round(($t2 - $t1) / 60);
                }
            }

            // Check if attendance entry exists for date
            $check = $db->prepare("SELECT id FROM employee_attendance WHERE employee_id = ? AND attendance_date = ?");
            $check->execute([$emp_id, $att_date]);
            $existing_id = $check->fetchColumn();

            if ($existing_id) {
                $stmt = $db->prepare("UPDATE employee_attendance SET 
                    status = ?, check_in_time = ?, check_out_time = ?, total_minutes_worked = ?, notes = ?, logged_by = ?, punch_method = 'Admin Manual' 
                    WHERE id = ?");
                $stmt->execute([$status, $cin_full, $cout_full, $total_mins, $notes, $_SESSION['username'] ?? 'Admin', $existing_id]);
            } else {
                $stmt = $db->prepare("INSERT INTO employee_attendance (
                    employee_id, emp_username, attendance_date, status, check_in_time, check_out_time, total_minutes_worked, notes, logged_by, punch_method, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Admin Manual', datetime('now'))");
                $stmt->execute([$emp_id, $emp_username, $att_date, $status, $cin_full, $cout_full, $total_mins, $notes, $_SESSION['username'] ?? 'Admin']);
            }

            log_activity("[" . ($_SESSION['username'] ?? 'Admin') . "] Admin Recorded Attendance for " . $emp_username . " (" . $att_date . ": " . $status . ")", "employees_list.php");
            return_json(['success' => true, 'message' => 'Attendance record updated successfully.']);
            break;

        case 'delete_employee_attendance':
            if (($_SESSION['role'] ?? '') !== 'Admin') return_json(['error' => 'Unauthorized Access. Admin role required.'], 403);
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request Method'], 405);

            $att_id = intval($_POST['attendance_id'] ?? 0);
            if ($att_id <= 0) return_json(['error' => 'Invalid Attendance ID.'], 400);

            $stmt = $db->prepare("DELETE FROM employee_attendance WHERE id = ?");
            $stmt->execute([$att_id]);

            log_activity("[" . ($_SESSION['username'] ?? 'Admin') . "] Deleted Attendance Entry #$att_id", "employees_list.php");
            return_json(['success' => true, 'message' => 'Attendance record permanently deleted.']);
            break;

        case 'employee_punch_in':
            if (!isset($_SESSION['user_id'])) return_json(['error' => 'Login required'], 401);
            
            $my_username = $_SESSION['username'] ?? '';
            $my_user_id = $_SESSION['user_id'];
            $my_role = $_SESSION['role'] ?? '';

            // Auto-resolve or create employee profile for RM / Staff / Admin
            $stmt = $db->prepare("SELECT id FROM employees WHERE user_id = ? OR official_email = ? OR emp_id = ?");
            $stmt->execute([$my_user_id, $my_username, $my_username]);
            $my_emp_id = $stmt->fetchColumn();

            if (!$my_emp_id) {
                // Auto-create employee record for this user so RM/Staff can Punch In seamlessly
                $emp_code = 'EMP-' . str_pad($my_user_id, 3, '0', STR_PAD_LEFT);
                $clean_name = ucwords(str_replace(['.', '_', '-'], ' ', explode('@', $my_username)[0]));
                $dept = ($my_role === 'Admin') ? 'Executive Management' : 'Loan Operations & RM';
                $desg = ($my_role === 'Admin') ? 'Administrator' : 'Relationship Manager';
                $acc_role = ($my_role === 'Admin') ? 'Admin' : 'Staff';

                $ins = $db->prepare("INSERT INTO employees (user_id, emp_id, full_name, official_email, mobile, department, designation, access_role, employment_status, doj) VALUES (?, ?, ?, ?, '9999999999', ?, ?, ?, 'Active', DATE('now'))");
                $ins->execute([$my_user_id, $emp_code, $clean_name, $my_username, $dept, $desg, $acc_role]);
                $my_emp_id = $db->lastInsertId();
            }

            $today = date('Y-m-d');
            $now = date('Y-m-d H:i:s');
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';

            // Check if already punched in today
            $check = $db->prepare("SELECT * FROM employee_attendance WHERE employee_id = ? AND attendance_date = ?");
            $check->execute([$my_emp_id, $today]);
            $existing = $check->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                // If user is currently actively punched in (check_in set, check_out empty) and no force reset requested
                if (!empty($existing['check_in_time']) && empty($existing['check_out_time']) && empty($_POST['force'])) {
                    return_json(['error' => 'You are already actively punched in today at ' . date('h:i A', strtotime($existing['check_in_time']))], 400);
                }
                
                // If check_out_time was set (e.g. finished shift or pre-seeded), or force is passed -> Reset to active live punch
                $stmt = $db->prepare("UPDATE employee_attendance SET check_in_time = ?, check_out_time = NULL, total_minutes_worked = NULL, status = 'Present', ip_address = ?, punch_method = 'Web Self-Punch' WHERE id = ?");
                $stmt->execute([$now, $ip, $existing['id']]);
            } else {
                $stmt = $db->prepare("INSERT INTO employee_attendance (
                    employee_id, emp_username, attendance_date, status, check_in_time, ip_address, punch_method, logged_by, created_at
                ) VALUES (?, ?, ?, 'Present', ?, ?, 'Web Self-Punch', ?, datetime('now'))");
                $stmt->execute([$my_emp_id, $my_username, $today, $now, $ip, $my_username]);
            }

            log_activity("Punch-In recorded for $my_username at $now", "dashboard.php");
            return_json(['success' => true, 'message' => 'Punched in successfully at ' . date('h:i:s A'), 'check_in_time' => $now]);
            break;

        case 'reset_my_today_punch':
            if (!isset($_SESSION['user_id'])) return_json(['error' => 'Login required'], 401);
            $my_username = $_SESSION['username'] ?? '';
            $my_user_id = $_SESSION['user_id'];

            $stmt = $db->prepare("SELECT id FROM employees WHERE user_id = ? OR official_email = ? OR emp_id = ?");
            $stmt->execute([$my_user_id, $my_username, $my_username]);
            $my_emp_id = $stmt->fetchColumn() ?: $my_user_id;

            $today = date('Y-m-d');
            $stmt = $db->prepare("DELETE FROM employee_attendance WHERE employee_id = ? AND attendance_date = ?");
            $stmt->execute([$my_emp_id, $today]);

            log_activity("Reset today's punch timer for $my_username", "dashboard.php");
            return_json(['success' => true, 'message' => "Today's shift timer has been reset."]);
            break;

        case 'employee_punch_out':
            if (!isset($_SESSION['user_id'])) return_json(['error' => 'Login required'], 401);
            
            $my_username = $_SESSION['username'] ?? '';
            $my_user_id = $_SESSION['user_id'];

            $stmt = $db->prepare("SELECT id FROM employees WHERE user_id = ? OR official_email = ? OR emp_id = ?");
            $stmt->execute([$my_user_id, $my_username, $my_username]);
            $my_emp_id = $stmt->fetchColumn() ?: $my_user_id;

            $today = date('Y-m-d');
            $now = date('Y-m-d H:i:s');

            $check = $db->prepare("SELECT * FROM employee_attendance WHERE employee_id = ? AND attendance_date = ?");
            $check->execute([$my_emp_id, $today]);
            $existing = $check->fetch(PDO::FETCH_ASSOC);

            if (!$existing || empty($existing['check_in_time'])) {
                return_json(['error' => 'You must Punch In before you can Punch Out.'], 400);
            }

            if (!empty($existing['check_out_time'])) {
                return_json(['error' => 'You have already punched out today at ' . date('h:i A', strtotime($existing['check_out_time']))], 400);
            }

            $cin_time = strtotime($existing['check_in_time']);
            $cout_time = strtotime($now);
            $total_mins = round(($cout_time - $cin_time) / 60);

            $new_status = ($total_mins >= 480) ? 'Present' : (($total_mins >= 240) ? 'Half Day' : 'Short Shift');

            $stmt = $db->prepare("UPDATE employee_attendance SET check_out_time = ?, total_minutes_worked = ?, status = ? WHERE id = ?");
            $stmt->execute([$now, $total_mins, $new_status, $existing['id']]);

            $hrs = floor($total_mins / 60);
            $mins = $total_mins % 60;

            log_activity("Punch-Out recorded for $my_username ($hrs hrs $mins mins worked)", "dashboard.php");
            return_json([
                'success' => true,
                'message' => "Punched out successfully! Total Shift Duration: {$hrs}h {$mins}m",
                'check_out_time' => $now,
                'total_minutes' => $total_mins,
                'duration_formatted' => "{$hrs}h {$mins}m"
            ]);
            break;

        case 'get_my_today_attendance':
            if (!isset($_SESSION['user_id'])) return_json(['error' => 'Login required'], 401);
            $my_username = $_SESSION['username'] ?? '';
            $my_user_id = $_SESSION['user_id'];

            $stmt = $db->prepare("SELECT id FROM employees WHERE user_id = ? OR official_email = ? OR emp_id = ?");
            $stmt->execute([$my_user_id, $my_username, $my_username]);
            $my_emp_id = $stmt->fetchColumn() ?: $my_user_id;

            $today = date('Y-m-d');
            $att = null;
            if ($my_emp_id) {
                $check = $db->prepare("SELECT * FROM employee_attendance WHERE employee_id = ? AND attendance_date = ?");
                $check->execute([$my_emp_id, $today]);
                $att = $check->fetch(PDO::FETCH_ASSOC);
            }

            return_json([
                'success' => true,
                'server_now' => date('Y-m-d H:i:s'),
                'attendance' => $att
            ]);
            break;

        case 'get_daily_attendance_sheet':
            if (($_SESSION['role'] ?? '') !== 'Admin') return_json(['error' => 'Admin privileges required'], 403);
            $date = trim($_GET['date'] ?? date('Y-m-d'));

            // Fetch all active employees joined with their attendance log for date
            $stmt = $db->prepare("
                SELECT e.id, e.emp_id, e.full_name, e.department, e.designation, u.username,
                       a.id as attendance_id, a.status, a.check_in_time, a.check_out_time, a.total_minutes_worked, a.notes, a.logged_by, a.punch_method
                FROM employees e
                JOIN users u ON e.user_id = u.id
                LEFT JOIN employee_attendance a ON (a.employee_id = e.id AND a.attendance_date = ?)
                WHERE u.is_active = 1
                ORDER BY e.full_name ASC
            ");
            $stmt->execute([$date]);
            $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return_json([
                'success' => true,
                'date' => $date,
                'employees' => $employees
            ]);
            break;

        case 'bulk_save_attendance':
            if (($_SESSION['role'] ?? '') !== 'Admin') return_json(['error' => 'Admin privileges required'], 403);
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request Method'], 405);

            $date = trim($_POST['attendance_date'] ?? date('Y-m-d'));
            $sheet_raw = $_POST['sheet_data'] ?? '[]';
            $rows = json_decode($sheet_raw, true);

            if (!is_array($rows)) {
                return_json(['error' => 'Invalid sheet data payload.'], 400);
            }

            try {
                $db->beginTransaction();

                foreach ($rows as $r) {
                    $emp_id = intval($r['employee_id'] ?? 0);
                    $emp_username = trim($r['emp_username'] ?? '');
                    $status = trim($r['status'] ?? 'Present');
                    $cin = trim($r['check_in_time'] ?? '');
                    $cout = trim($r['check_out_time'] ?? '');
                    $notes = trim($r['notes'] ?? '');

                    if ($emp_id <= 0 || empty($emp_username)) continue;

                    $cin_full = !empty($cin) ? ($date . ' ' . $cin . ':00') : null;
                    $cout_full = !empty($cout) ? ($date . ' ' . $cout . ':00') : null;

                    $total_mins = 0;
                    if ($cin_full && $cout_full) {
                        $t1 = strtotime($cin_full);
                        $t2 = strtotime($cout_full);
                        if ($t2 > $t1) $total_mins = round(($t2 - $t1) / 60);
                    }

                    $check = $db->prepare("SELECT id FROM employee_attendance WHERE employee_id = ? AND attendance_date = ?");
                    $check->execute([$emp_id, $date]);
                    $existing_id = $check->fetchColumn();

                    if ($existing_id) {
                        $stmt = $db->prepare("UPDATE employee_attendance SET status = ?, check_in_time = ?, check_out_time = ?, total_minutes_worked = ?, notes = ?, logged_by = ?, punch_method = 'Bulk Sheet' WHERE id = ?");
                        $stmt->execute([$status, $cin_full, $cout_full, $total_mins, $notes, $_SESSION['username'] ?? 'Admin', $existing_id]);
                    } else {
                        $stmt = $db->prepare("INSERT INTO employee_attendance (employee_id, emp_username, attendance_date, status, check_in_time, check_out_time, total_minutes_worked, notes, logged_by, punch_method, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Bulk Sheet', datetime('now'))");
                        $stmt->execute([$emp_id, $emp_username, $date, $status, $cin_full, $cout_full, $total_mins, $notes, $_SESSION['username'] ?? 'Admin']);
                    }
                }

                $db->commit();
                log_activity("Bulk Attendance Sheet saved for $date (" . count($rows) . " staff records)", "attendance_sheet.php");
                return_json(['success' => true, 'message' => 'Entire attendance sheet saved successfully!']);
            } catch (PDOException $e) {
                $db->rollBack();
                return_json(['error' => 'Database error: ' . $e->getMessage()]);
            }
            break;

        case 'record_employee_salary_payment':
            if (($_SESSION['role'] ?? '') !== 'Admin') return_json(['error' => 'Unauthorized Access. Admin role required.'], 403);
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request Method'], 405);

            $emp_id          = intval($_POST['employee_id'] ?? 0);
            $emp_username    = trim($_POST['emp_username'] ?? '');
            $payment_month   = trim($_POST['payment_month'] ?? date('Y-m'));
            $base_salary     = floatval($_POST['base_salary'] ?? 0);
            $incentive_amt   = floatval($_POST['incentive_amount'] ?? 0);
            $bonus_amt       = floatval($_POST['bonus_amount'] ?? 0);
            $deductions_amt  = floatval($_POST['deductions_amount'] ?? 0);
            $payment_date    = trim($_POST['payment_date'] ?? date('Y-m-d'));
            $payment_mode    = trim($_POST['payment_mode'] ?? 'Bank Transfer');
            $tx_ref          = trim($_POST['transaction_ref'] ?? '');
            $remarks         = trim($_POST['remarks'] ?? '');

            if ($emp_id <= 0 || empty($emp_username)) {
                return_json(['error' => 'Employee ID and Username are required.'], 400);
            }

            $net_paid = round(($base_salary + $incentive_amt + $bonus_amt) - $deductions_amt, 2);
            if ($net_paid <= 0) return_json(['error' => 'Calculated Net Payable amount must be greater than zero.'], 400);

            $stmt = $db->prepare("INSERT INTO employee_salary_payments (employee_id, emp_username, payment_month, base_salary, incentive_amount, bonus_amount, deductions_amount, net_salary_paid, payment_date, payment_mode, transaction_ref, status, remarks, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Paid', ?, ?, datetime('now'))");
            $stmt->execute([$emp_id, $emp_username, $payment_month, $base_salary, $incentive_amt, $bonus_amt, $deductions_amt, $net_paid, $payment_date, $payment_mode, $tx_ref, $remarks, $_SESSION['username'] ?? 'Admin']);

            log_activity("[" . ($_SESSION['username'] ?? 'Admin') . "] Recorded Salary & Incentive Payment of ₹" . number_format($net_paid) . " for " . $emp_username . " (Ref: " . $tx_ref . ")", "employees_list.php");

            return_json(['success' => true, 'message' => 'Salary & Incentive payout recorded successfully.', 'net_salary_paid' => $net_paid]);
            break;

        case 'get_employee_360_data':
            $emp_id = intval($_GET['id'] ?? 0);
            $username = trim($_GET['username'] ?? '');

            if ($emp_id <= 0 && empty($username)) {
                return_json(['error' => 'Employee ID or Username is required.'], 400);
            }

            if ($emp_id > 0) {
                $stmt = $db->prepare("SELECT e.*, u.is_active, u.username, u.role FROM employees e JOIN users u ON e.user_id = u.id WHERE e.id = ?");
                $stmt->execute([$emp_id]);
            } else {
                $stmt = $db->prepare("SELECT e.*, u.is_active, u.username, u.role FROM employees e JOIN users u ON e.user_id = u.id WHERE u.username = ?");
                $stmt->execute([$username]);
            }
            $emp = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$emp) return_json(['error' => 'Employee profile not found.'], 404);

            $u_name = $emp['username'];
            $e_id   = $emp['id'];

            // Fetch Attendance records
            $att_stmt = $db->prepare("SELECT * FROM employee_attendance WHERE employee_id = ? ORDER BY attendance_date DESC LIMIT 30");
            $att_stmt->execute([$e_id]);
            $attendance_logs = $att_stmt->fetchAll(PDO::FETCH_ASSOC);

            // Attendance counts (MTD)
            $cur_m = date('Y-m-');
            $att_counts_stmt = $db->prepare("SELECT status, COUNT(*) as c FROM employee_attendance WHERE employee_id = ? AND attendance_date LIKE ? GROUP BY status");
            $att_counts_stmt->execute([$e_id, $cur_m . '%']);
            $att_counts = ['Present' => 0, 'Absent' => 0, 'Half Day' => 0, 'On Leave' => 0, 'Paid Leave' => 0];
            while ($row = $att_counts_stmt->fetch(PDO::FETCH_ASSOC)) {
                if (isset($att_counts[$row['status']])) {
                    $att_counts[$row['status']] = (int)$row['c'];
                }
            }

            // Fetch Salary payment history
            $sal_stmt = $db->prepare("SELECT * FROM employee_salary_payments WHERE employee_id = ? ORDER BY payment_date DESC");
            $sal_stmt->execute([$e_id]);
            $salary_payments = $sal_stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch assigned loan cases
            $apps_stmt = $db->prepare("SELECT id, loan_id, customer_name, mobile, loan_type, loan_amount_requested, sanctioned_amount, sanction_bank, overall_status, login_fee_status, created_at FROM applicants WHERE (assigned_to = ? OR (assigned_to IS NULL AND added_by = ?)) ORDER BY id DESC");
            $apps_stmt->execute([$u_name, $u_name]);
            $assigned_files = $apps_stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate loan performance totals
            $total_assigned_count = count($assigned_files);
            $disbursed_val = 0;
            $disbursed_count = 0;
            foreach ($assigned_files as $af) {
                if ($af['overall_status'] === 'Completed') {
                    $disbursed_count++;
                    $disbursed_val += floatval($af['sanctioned_amount'] ?: $af['loan_amount_requested']);
                }
            }

            // Fetch activity audit stream ("Kon kya kar raha hai")
            $acts_stmt = $db->prepare("SELECT * FROM activities WHERE (username = ? OR description LIKE ?) ORDER BY id DESC LIMIT 50");
            $acts_stmt->execute([$u_name, "%" . $u_name . "%"]);
            $activity_log = $acts_stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch queries assigned to this employee
            $q_stmt = $db->prepare("SELECT q.*, a.customer_name, a.loan_id FROM rm_internal_queries q LEFT JOIN applicants a ON q.applicant_id = a.id WHERE q.assigned_rm = ? ORDER BY q.id DESC");
            $q_stmt->execute([$u_name]);
            $queries = $q_stmt->fetchAll(PDO::FETCH_ASSOC);

            return_json([
                'success' => true,
                'employee' => $emp,
                'attendance_summary' => $att_counts,
                'attendance_logs' => $attendance_logs,
                'salary_payments' => $salary_payments,
                'performance' => [
                    'total_assigned_count' => $total_assigned_count,
                    'disbursed_count' => $disbursed_count,
                    'disbursed_val' => $disbursed_val,
                    'estimated_incentive' => round($disbursed_val * (($emp['commission_rate'] ?: 1.0) / 100))
                ],
                'assigned_files' => $assigned_files,
                'activity_log' => $activity_log,
                'queries' => $queries
            ]);
            break;

        case 'add_payout_distribution':
            if (($_SESSION['role'] ?? '') !== 'Admin') return_json(['error' => 'Admin privileges required'], 403);
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request Method'], 405);

            $applicant_id = intval($_POST['applicant_id'] ?? 0);
            $payee_type = trim($_POST['payee_type'] ?? 'Employee');
            $payee_user_id = intval($_POST['payee_user_id'] ?? 0);
            $total_loan_amount = floatval($_POST['total_loan_amount'] ?? 0);
            $commission_pct = floatval($_POST['commission_percentage'] ?? 0);
            $gross_payout = floatval($_POST['gross_payout'] ?? 0);
            $payment_mode = trim($_POST['payment_mode'] ?? 'NEFT');
            $remarks = trim($_POST['remarks'] ?? '');

            if ($gross_payout <= 0 && $total_loan_amount > 0 && $commission_pct > 0) {
                $gross_payout = ($total_loan_amount * $commission_pct) / 100;
            }

            if ($gross_payout <= 0) {
                return_json(['error' => 'Please enter valid loan amount, commission %, or gross payout amount.'], 400);
            }

            $tds_deducted = round(($gross_payout * 5.0) / 100, 2);
            $net_payable = round($gross_payout - $tds_deducted, 2);

            // Fetch payee bank details
            $payee_name = '';
            $payee_mobile = '';
            $payee_bank = '';
            $payee_acc = '';
            $payee_ifsc = '';
            $payee_pan = '';

            if ($payee_user_id > 0) {
                $u_stmt = $db->prepare("SELECT u.name, u.username, emp.mobile, emp.bank_name, emp.bank_account_no, emp.bank_ifsc, emp.pan_number 
                                        FROM users u 
                                        LEFT JOIN employees emp ON (emp.user_id = u.id OR emp.official_email = u.username) 
                                        WHERE u.id = ?");
                $u_stmt->execute([$payee_user_id]);
                $u = $u_stmt->fetch(PDO::FETCH_ASSOC);
                if ($u) {
                    $payee_name = $u['name'] ?: $u['username'];
                    $payee_mobile = $u['mobile'] ?: '';
                    $payee_bank = $u['bank_name'] ?: '';
                    $payee_acc = $u['bank_account_no'] ?: '';
                    $payee_ifsc = $u['bank_ifsc'] ?: '';
                    $payee_pan = $u['pan_number'] ?: '';
                }
            }

            // Ensure payment_proof_path column exists
            try { $db->exec("ALTER TABLE payout_distributions ADD COLUMN payment_proof_path TEXT"); } catch (Exception $e) {}

            $stmt = $db->prepare("INSERT INTO payout_distributions (
                applicant_id, payee_type, payee_user_id, payee_name_override, payee_mobile, payee_bank_name, payee_account_no, payee_ifsc, payee_pan,
                total_loan_amount, commission_percentage, gross_payout, tds_percentage, tds_deducted, net_payable, status, payment_mode, remarks, created_by, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 5.0, ?, ?, 'Pending', ?, ?, ?, datetime('now'))");
            
            $stmt->execute([
                $applicant_id, $payee_type, $payee_user_id, $payee_name, $payee_mobile, $payee_bank, $payee_acc, $payee_ifsc, $payee_pan,
                $total_loan_amount, $commission_pct, $gross_payout, $tds_deducted, $net_payable, $payment_mode, $remarks, $_SESSION['username'] ?? 'Admin'
            ]);
            $new_id = $db->lastInsertId();

            // Check for file upload proof
            if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/payout_proofs/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                $ext = strtolower(pathinfo($_FILES['payment_proof']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
                if (in_array($ext, $allowed)) {
                    $proof_filename = 'proof_payout_' . $new_id . '_' . time() . '.' . $ext;
                    $proof_path = $upload_dir . $proof_filename;
                    if (move_uploaded_file($_FILES['payment_proof']['tmp_name'], $proof_path)) {
                        $db->prepare("UPDATE payout_distributions SET payment_proof_path = ? WHERE id = ?")->execute([$proof_path, $new_id]);
                    }
                }
            }

            log_activity("Generated New Payout #PD-" . str_pad($new_id, 3, '0', STR_PAD_LEFT) . " of ₹" . number_format($net_payable) . " for " . $payee_name, "payout_distribution.php");
            return_json(['success' => true, 'message' => 'New Payout Voucher #PD-' . str_pad($new_id, 3, '0', STR_PAD_LEFT) . ' generated successfully!', 'id' => $new_id]);
            break;

        case 'update_payout_status':
            if (($_SESSION['role'] ?? '') !== 'Admin') return_json(['error' => 'Admin privileges required'], 403);
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request Method'], 405);

            $id = intval($_POST['id'] ?? 0);
            $status = trim($_POST['status'] ?? 'Pending');
            $payment_mode = trim($_POST['payment_mode'] ?? 'NEFT Transfer');
            $tx_ref = trim($_POST['transaction_ref'] ?? '');
            $cancel_reason = trim($_POST['cancellation_reason'] ?? '');

            if ($id <= 0) return_json(['error' => 'Payout ID is required'], 400);

            if ($status === 'Paid' && empty($tx_ref)) {
                return_json(['error' => 'Bank UTR / Transaction Reference Number is required when marking as Paid'], 400);
            }

            // Ensure payment_proof_path column exists
            try { $db->exec("ALTER TABLE payout_distributions ADD COLUMN payment_proof_path TEXT"); } catch (Exception $e) {}

            // Handle Payment Proof File Upload (UPI Screenshot / Bank Receipt)
            $proof_path = null;
            if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/payout_proofs/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                
                $ext = strtolower(pathinfo($_FILES['payment_proof']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
                if (in_array($ext, $allowed)) {
                    $proof_filename = 'proof_payout_' . $id . '_' . time() . '.' . $ext;
                    $proof_path = $upload_dir . $proof_filename;
                    move_uploaded_file($_FILES['payment_proof']['tmp_name'], $proof_path);
                }
            }

            $paid_on = ($status === 'Paid') ? date('Y-m-d H:i:s') : null;

            if ($proof_path) {
                $stmt = $db->prepare("UPDATE payout_distributions SET status = ?, payment_mode = ?, transaction_ref = ?, cancellation_reason = ?, payment_proof_path = ?, paid_on = COALESCE(?, paid_on), updated_at = datetime('now') WHERE id = ?");
                $stmt->execute([$status, $payment_mode, $tx_ref, $cancel_reason, $proof_path, $paid_on, $id]);
            } else {
                $stmt = $db->prepare("UPDATE payout_distributions SET status = ?, payment_mode = ?, transaction_ref = ?, cancellation_reason = ?, paid_on = COALESCE(?, paid_on), updated_at = datetime('now') WHERE id = ?");
                $stmt->execute([$status, $payment_mode, $tx_ref, $cancel_reason, $paid_on, $id]);
            }

            log_activity("Updated Payout #PD-" . str_pad($id, 3, '0', STR_PAD_LEFT) . " status to " . $status . ($proof_path ? ' (with UPI Payment Screenshot Proof)' : ''), "payout_distribution.php");
            return_json(['success' => true, 'message' => 'Payout status updated to ' . $status . ' successfully!', 'proof_path' => $proof_path]);
            break;

        case 'bulk_disburse_payouts':
            if (($_SESSION['role'] ?? '') !== 'Admin') return_json(['error' => 'Admin privileges required'], 403);
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request Method'], 405);

            $ids_raw = $_POST['ids'] ?? '[]';
            $ids = json_decode($ids_raw, true);
            if (!is_array($ids) || empty($ids)) {
                $ids = array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')));
            }

            if (empty($ids)) {
                return_json(['error' => 'No payout records selected for bulk disbursal'], 400);
            }

            $payment_mode = trim($_POST['payment_mode'] ?? 'NEFT Bulk Batch');
            $batch_ref = trim($_POST['transaction_ref'] ?? 'BATCH-' . date('YmdHi'));
            $now = date('Y-m-d H:i:s');

            $in_clause = implode(',', array_map('intval', $ids));
            $stmt = $db->prepare("UPDATE payout_distributions SET status = 'Paid', payment_mode = ?, transaction_ref = ?, paid_on = ?, updated_at = ? WHERE id IN ($in_clause)");
            $stmt->execute([$payment_mode, $batch_ref, $now, $now]);

            log_activity("Bulk Disbursed " . count($ids) . " payouts via " . $payment_mode . " (Ref: " . $batch_ref . ")", "payout_distribution.php");
            return_json(['success' => true, 'message' => count($ids) . ' payouts marked as Paid successfully under Batch Ref: ' . $batch_ref]);
            break;

        case 'record_bank_payout_received':
            if (($_SESSION['role'] ?? '') !== 'Admin') return_json(['error' => 'Admin privileges required'], 403);
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request Method'], 405);

            $applicant_id = intval($_POST['applicant_id'] ?? 0);
            $rec_amt = floatval($_POST['received_amount'] ?? 0);
            $bank_utr = trim($_POST['bank_utr'] ?? '');
            $rec_date = trim($_POST['received_date'] ?? date('Y-m-d'));

            if ($applicant_id <= 0 || $rec_amt <= 0 || empty($bank_utr)) {
                return_json(['error' => 'Applicant ID, Received Amount, and Bank UTR are required'], 400);
            }

            // Ensure column exists
            try { $db->exec("ALTER TABLE applicants ADD COLUMN bank_payout_status TEXT DEFAULT 'Pending'"); } catch (Exception $e) {}
            try { $db->exec("ALTER TABLE applicants ADD COLUMN bank_payout_received_amount REAL DEFAULT 0"); } catch (Exception $e) {}
            try { $db->exec("ALTER TABLE applicants ADD COLUMN bank_utr TEXT"); } catch (Exception $e) {}

            $stmt = $db->prepare("UPDATE applicants SET bank_payout_status = 'Received', bank_payout_received_amount = ?, bank_utr = ? WHERE id = ?");
            $stmt->execute([$rec_amt, $bank_utr, $applicant_id]);

            // Also check if an invoice exists in bank_payout_invoices
            $inv_check = $db->prepare("SELECT id FROM bank_payout_invoices WHERE applicant_id = ?");
            $inv_check->execute([$applicant_id]);
            $inv_id = $inv_check->fetchColumn();
            if ($inv_id) {
                $u_inv = $db->prepare("UPDATE bank_payout_invoices SET status = 'Received', received_amount = ?, bank_utr = ?, received_date = ? WHERE id = ?");
                $u_inv->execute([$rec_amt, $bank_utr, $rec_date, $inv_id]);
            }

            log_activity("Recorded Bank Commission Receipt of ₹" . number_format($rec_amt) . " (UTR: " . $bank_utr . ") for Applicant ID #" . $applicant_id, "payout_distribution.php");
            return_json(['success' => true, 'message' => 'Bank commission receipt of ₹' . number_format($rec_amt) . ' recorded successfully!']);
            break;

        case 'send_bank_email_escalation':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request Method'], 405);

            $applicant_id = intval($_POST['applicant_id'] ?? 0);
            $to_email = trim($_POST['email'] ?? '');
            $subject = trim($_POST['subject'] ?? '');
            $body_text = trim($_POST['body'] ?? '');

            if (empty($to_email) || empty($subject) || empty($body_text)) {
                return_json(['error' => 'Email address, Subject, and Message Body are required'], 400);
            }

            $formatted_html = '<div style="font-size:13.5px; line-height:1.7; color:#0f172a; white-space:pre-wrap;">' . nl2br(htmlspecialchars($body_text)) . '</div>';

            $res = sendUniversalEmail($to_email, 'Bank Nodal Officer', $subject, $formatted_html, "payout_distribution.php?id=$applicant_id");
            if ($res['success']) {
                return_json(['success' => true, 'message' => 'Bank Payout Escalation Email dispatched successfully to ' . $to_email]);
            } else {
                return_json(['error' => $res['error'] ?? 'Failed to send escalation email'], 500);
            }
            break;

        case 'send_payout_invoice_email':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request Method'], 405);

            $invoice_id = intval($_POST['invoice_id'] ?? 0);
            $to_email = trim($_POST['email'] ?? '');
            
            if ($invoice_id <= 0) return_json(['error' => 'Invalid Invoice ID'], 400);

            $stmt = $db->prepare("SELECT inv.*, a.customer_name, a.loan_id, a.loan_type FROM bank_payout_invoices inv LEFT JOIN applicants a ON inv.applicant_id = a.id WHERE inv.id = ?");
            $stmt->execute([$invoice_id]);
            $inv = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$inv) return_json(['error' => 'Invoice record not found'], 404);

            if (empty($to_email)) {
                $to_email = 'nodal.disbursal@' . strtolower(preg_replace('/[^a-z0-9]/', '', $inv['bank_name'] ?: 'bank')) . '.com';
            }

            $subject = "TAX INVOICE: " . $inv['invoice_number'] . " — " . $inv['bank_name'] . " [DSA Commission Claim]";
            
            $inv_html = '
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:20px; margin-bottom:20px;">
                <div style="display:flex; justify-content:space-between; margin-bottom:12px; border-bottom:1px solid #e2e8f0; padding-bottom:8px;">
                    <span style="font-weight:800; font-size:16px; color:#0f172a;">TAX INVOICE DISPATCH</span>
                    <span style="background:#0f172a; color:#fff; padding:3px 10px; border-radius:12px; font-size:12px; font-weight:700;">' . htmlspecialchars($inv['invoice_number']) . '</span>
                </div>
                <p style="margin:0 0 10px 0; color:#475569; font-size:13px;">Dear Finance & Accounts Nodal Team (<strong>' . htmlspecialchars($inv['bank_name']) . '</strong>),</p>
                <p style="margin:0 0 16px 0; color:#334155; font-size:13px;">Please find below the official Tax Invoice breakdown for DSA Referral Commission against the disbursed loan case of <strong>' . htmlspecialchars($inv['customer_name']) . '</strong> (Loan Ref: <strong>' . htmlspecialchars($inv['loan_id']) . '</strong>).</p>

                <table style="width:100%; border-collapse:collapse; background:#ffffff; border-radius:8px; overflow:hidden; border:1px solid #cbd5e1; font-size:13px;">
                    <tr style="background:#f1f5f9; color:#0f172a;"><th style="padding:10px; text-align:left; border-bottom:1px solid #cbd5e1;">Description / Particulars</th><th style="padding:10px; text-align:right; border-bottom:1px solid #cbd5e1;">Amount (₹)</th></tr>
                    <tr><td style="padding:10px; border-bottom:1px solid #f1f5f9;">Total Loan Disbursed Amount</td><td style="padding:10px; text-align:right; font-weight:700; border-bottom:1px solid #f1f5f9;">₹' . number_format($inv['disbursed_amount']) . '</td></tr>
                    <tr><td style="padding:10px; border-bottom:1px solid #f1f5f9;">Approved DSA Slab Rate</td><td style="padding:10px; text-align:right; font-weight:700; border-bottom:1px solid #f1f5f9;">' . number_format($inv['commission_pct'], 2) . '%</td></tr>
                    <tr style="background:#f8fafc;"><td style="padding:10px; border-bottom:1px solid #f1f5f9; font-weight:700;">Gross Base Commission</td><td style="padding:10px; text-align:right; font-weight:700; border-bottom:1px solid #f1f5f9; color:#0f172a;">₹' . number_format($inv['gross_amount'], 2) . '</td></tr>
                    <tr><td style="padding:10px; border-bottom:1px solid #f1f5f9;">+ GST @ 18% (SAC 997159)</td><td style="padding:10px; text-align:right; color:#16a34a; font-weight:700; border-bottom:1px solid #f1f5f9;">+ ₹' . number_format($inv['gst_amount'], 2) . '</td></tr>
                    <tr><td style="padding:10px; border-bottom:1px solid #f1f5f9;">- TDS Deductible @ 2% u/s 194H</td><td style="padding:10px; text-align:right; color:#dc2626; font-weight:700; border-bottom:1px solid #f1f5f9;">- ₹' . number_format($inv['tds_amount'], 2) . '</td></tr>
                    <tr style="background:#0f172a; color:#ffffff;"><td style="padding:12px; font-weight:800; font-size:14px;">NET CLAIM RECEIVABLE</td><td style="padding:12px; text-align:right; font-weight:800; font-size:15px; color:#38bdf8;">₹' . number_format($inv['net_invoice_amount'], 2) . '</td></tr>
                </table>
                
                <p style="margin:16px 0 0 0; font-size:12.5px; color:#64748b;">Kindly process the remittance to our registered bank account at the earliest and share the bank UTR confirmation.</p>
            </div>';

            $res = sendUniversalEmail($to_email, $inv['bank_name'] . ' Accounts', $subject, $inv_html, "bank_payout_invoices.php?id=$invoice_id");
            if ($res['success']) {
                return_json(['success' => true, 'message' => 'Tax Invoice ' . $inv['invoice_number'] . ' successfully emailed to ' . $to_email]);
            } else {
                return_json(['error' => $res['error'] ?? 'Failed to email invoice'], 500);
            }
            break;

        case 'send_outgoing_payout_email':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request Method'], 405);

            $payout_id = intval($_POST['payout_id'] ?? 0);
            $to_email = trim($_POST['email'] ?? '');

            if ($payout_id <= 0) return_json(['error' => 'Invalid Payout ID'], 400);

            $stmt = $db->prepare("SELECT p.*, a.customer_name, a.loan_id, a.loan_type, a.sanction_bank FROM payout_distributions p LEFT JOIN applicants a ON p.applicant_id = a.id WHERE p.id = ?");
            $stmt->execute([$payout_id]);
            $p = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$p) return_json(['error' => 'Payout record not found'], 404);

            if (empty($to_email)) {
                $u_stmt = $db->prepare("SELECT email FROM users WHERE name = ? OR username = ? LIMIT 1");
                $u_stmt->execute([$p['payee_name'], $p['payee_name']]);
                $to_email = $u_stmt->fetchColumn();

                if (empty($to_email)) {
                    $r_stmt = $db->prepare("SELECT email FROM referrals WHERE name = ? LIMIT 1");
                    $r_stmt->execute([$p['payee_name']]);
                    $to_email = $r_stmt->fetchColumn();
                }
            }

            if (empty($to_email) || !filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
                return_json(['error' => 'No valid email address found for payee ' . $p['payee_name'] . '. Please provide email manually.'], 400);
            }

            $v_no = '#PD-' . str_pad($p['id'], 3, '0', STR_PAD_LEFT);
            $subject = "PAYOUT RECEIPT VOUCHER: " . $v_no . " — " . $p['payee_name'] . " [₹" . number_format($p['net_payable']) . "]";

            $voucher_html = '
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:20px; margin-bottom:20px;">
                <div style="display:flex; justify-content:space-between; margin-bottom:12px; border-bottom:1px solid #e2e8f0; padding-bottom:8px;">
                    <span style="font-weight:800; font-size:16px; color:#0f172a;">PAYOUT REMITTANCE ADVICE</span>
                    <span style="background:#059669; color:#fff; padding:3px 10px; border-radius:12px; font-size:12px; font-weight:700;">' . htmlspecialchars($v_no) . '</span>
                </div>
                <p style="margin:0 0 10px 0; color:#475569; font-size:13px;">Dear <strong>' . htmlspecialchars($p['payee_name']) . '</strong> (' . htmlspecialchars($p['payee_role']) . '),</p>
                <p style="margin:0 0 16px 0; color:#334155; font-size:13px;">We are pleased to share the Payout Advice Voucher for the commission earned on customer loan case <strong>' . htmlspecialchars($p['customer_name'] ?: 'Direct Case') . '</strong> (Ref: <strong>' . htmlspecialchars($p['loan_id'] ?: 'N/A') . '</strong>).</p>

                <table style="width:100%; border-collapse:collapse; background:#ffffff; border-radius:8px; overflow:hidden; border:1px solid #cbd5e1; font-size:13px;">
                    <tr style="background:#f1f5f9; color:#0f172a;"><th style="padding:10px; text-align:left; border-bottom:1px solid #cbd5e1;">Voucher Detail</th><th style="padding:10px; text-align:right; border-bottom:1px solid #cbd5e1;">Amount (₹)</th></tr>
                    <tr><td style="padding:10px; border-bottom:1px solid #f1f5f9;">Gross Commission Share (' . number_format($p['payout_percentage'] ?? 0, 2) . '%)</td><td style="padding:10px; text-align:right; font-weight:700; border-bottom:1px solid #f1f5f9;">₹' . number_format($p['gross_payout'], 2) . '</td></tr>
                    <tr><td style="padding:10px; border-bottom:1px solid #f1f5f9;">- TDS Deductible @ 5% (Sec 194H)</td><td style="padding:10px; text-align:right; color:#dc2626; font-weight:700; border-bottom:1px solid #f1f5f9;">- ₹' . number_format($p['tds_deducted'], 2) . '</td></tr>
                    <tr style="background:#0f172a; color:#ffffff;"><td style="padding:12px; font-weight:800; font-size:14px;">NET AMOUNT REMITTED</td><td style="padding:12px; text-align:right; font-weight:800; font-size:15px; color:#4ade80;">₹' . number_format($p['net_payable'], 2) . '</td></tr>
                </table>
                
                <div style="margin-top:16px; background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:12px; font-size:12px;">
                    <div><strong>Payment Mode:</strong> ' . htmlspecialchars($p['payment_mode'] ?: 'Bank Transfer') . '</div>
                    <div><strong>Bank UTR / Reference:</strong> <span style="font-family:monospace; font-weight:bold; color:#0f172a;">' . htmlspecialchars($p['transaction_ref'] ?: 'Processing') . '</span></div>
                    <div><strong>Disbursal Date:</strong> ' . htmlspecialchars($p['created_at'] ?: date('Y-m-d')) . '</div>
                </div>
            </div>';

            $res = sendUniversalEmail($to_email, $p['payee_name'], $subject, $voucher_html, "payout_distributions.php?id=$payout_id");
            if ($res['success']) {
                return_json(['success' => true, 'message' => 'Payout Voucher ' . $v_no . ' successfully emailed to ' . $to_email]);
            } else {
                return_json(['error' => $res['error'] ?? 'Failed to email voucher'], 500);
            }
            break;

        case 'get_payout_voucher':
            $id = intval($_GET['id'] ?? 0);
            if ($id <= 0) return_json(['error' => 'Invalid ID'], 400);

            $stmt = $db->prepare("
                SELECT p.*, a.customer_name, a.loan_id, a.loan_amount_requested, a.sanction_bank, a.loan_type
                FROM payout_distributions p
                LEFT JOIN applicants a ON p.applicant_id = a.id
                WHERE p.id = ?
            ");
            $stmt->execute([$id]);
            $v = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$v) return_json(['error' => 'Voucher not found'], 404);

            return_json(['success' => true, 'voucher' => $v]);
            break;

        case 'get_tracking_tokens':
            $search = trim($_GET['search'] ?? '');
            $query = "SELECT a.id, a.customer_name, a.loan_id, a.loan_type, a.overall_status, a.mobile, a.assigned_to, a.tracking_token, a.tracking_token_created, 
                             u.name as rm_name,
                             (SELECT COUNT(*) FROM applicant_documents d WHERE d.applicant_id = a.id AND d.status != 'Pending') as docs_uploaded_count
                      FROM applicants a 
                      LEFT JOIN users u ON u.username = a.assigned_to";
            $params = [];
            if ($search !== '') {
                $query .= " WHERE a.customer_name LIKE ? OR a.loan_id LIKE ? OR a.mobile LIKE ?";
                $params = ["%$search%", "%$search%", "%$search%"];
            }
            $query .= " ORDER BY a.id DESC LIMIT 100";
            
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
            $baseUrl = $scheme . '://' . $host . '/track.php?token=';

            $tracking_list = [];
            foreach ($rows as $r) {
                $token = $r['tracking_token'];
                $token_created = $r['tracking_token_created'];
                $days_left = 0;
                $is_expired = true;

                if ($token && $token_created) {
                    $created_ts = strtotime($token_created);
                    $expires_ts = strtotime('+30 days', $created_ts);
                    $now = time();
                    if ($expires_ts > $now) {
                        $days_left = ceil(($expires_ts - $now) / 86400);
                        $is_expired = false;
                    }
                }

                $tracking_list[] = [
                    'id' => $r['id'],
                    'customer_name' => $r['customer_name'],
                    'loan_id' => $r['loan_id'],
                    'loan_type' => $r['loan_type'],
                    'overall_status' => $r['overall_status'],
                    'mobile' => $r['mobile'],
                    'rm_name' => $r['rm_name'] ?: ($r['assigned_to'] ?: 'Unassigned'),
                    'tracking_token' => $token,
                    'tracking_token_created' => $token_created,
                    'days_left' => $days_left,
                    'is_expired' => $is_expired,
                    'docs_uploaded_count' => intval($r['docs_uploaded_count']),
                    'tracking_url' => $token ? ($baseUrl . $token) : null
                ];
            }

            return_json(['success' => true, 'tokens' => $tracking_list, 'base_url' => $baseUrl]);
            break;

        case 'generate_tracking_token':
            $applicant_id = intval($_POST['applicant_id'] ?? $_GET['applicant_id'] ?? 0);
            if ($applicant_id <= 0) return_json(['error' => 'Invalid Applicant ID'], 400);

            $chk = $db->prepare("SELECT id, customer_name, loan_id FROM applicants WHERE id = ?");
            $chk->execute([$applicant_id]);
            $app = $chk->fetch(PDO::FETCH_ASSOC);
            if (!$app) return_json(['error' => 'Applicant not found'], 404);

            $newToken = bin2hex(random_bytes(16)); // 32 char hex token
            $upd = $db->prepare("UPDATE applicants SET tracking_token = ?, tracking_token_created = CURRENT_TIMESTAMP WHERE id = ?");
            $upd->execute([$newToken, $applicant_id]);

            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
            $tracking_url = $scheme . '://' . $host . '/track.php?token=' . $newToken;

            log_activity("Generated 30-Day Tracking Token for {$app['customer_name']} ({$app['loan_id']})", "settings.php");

            return_json([
                'success' => true, 
                'message' => 'Tracking token generated successfully!',
                'token' => $newToken,
                'tracking_url' => $tracking_url,
                'days_left' => 30
            ]);
            break;

        case 'bulk_generate_tracking_tokens':
            $stmt = $db->query("SELECT id FROM applicants WHERE tracking_token IS NULL OR tracking_token = '' OR datetime(tracking_token_created, '+30 days') <= datetime('now')");
            $apps = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $count = 0;
            $upd = $db->prepare("UPDATE applicants SET tracking_token = ?, tracking_token_created = CURRENT_TIMESTAMP WHERE id = ?");
            foreach ($apps as $a) {
                $newToken = bin2hex(random_bytes(16));
                $upd->execute([$newToken, $a['id']]);
                $count++;
            }

            log_activity("Bulk Generated $count Client Tracking Tokens in Settings Hub", "settings.php");

            return_json([
                'success' => true,
                'message' => "Successfully generated tracking tokens for $count applicants!",
                'count' => $count
            ]);
            break;

        case 'client_upload_document':
            $token = preg_replace('/[^a-f0-9]/i', '', $_POST['token'] ?? $_GET['token'] ?? '');
            if (strlen($token) !== 32) return_json(['error' => 'Invalid or expired tracking token.'], 403);
            $t_stmt = $db->prepare("SELECT * FROM applicants WHERE tracking_token = ? AND datetime(tracking_token_created, '+30 days') > datetime('now')");
            $t_stmt->execute([$token]);
            $client_app = $t_stmt->fetch(PDO::FETCH_ASSOC);
            if (!$client_app) return_json(['error' => 'Invalid or expired tracking link.'], 403);

            $applicant_id = $client_app['id'];
            $doc_name = trim($_POST['document_name'] ?? 'Client Upload');
            $category = trim($_POST['category'] ?? 'KYC');

            if (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
                return_json(['error' => 'Please select a valid file or photo to upload.'], 400);
            }

            $upload_dir = 'uploads/documents/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

            $ext = strtolower(pathinfo($_FILES['document_file']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'])) {
                return_json(['error' => 'Allowed file formats: PDF, JPG, PNG, DOC.'], 400);
            }

            $filename = uniqid('client_doc_') . '.' . $ext;
            $filepath = $upload_dir . $filename;
            $filesize = (int)$_FILES['document_file']['size'];

            if (move_uploaded_file($_FILES['document_file']['tmp_name'], $filepath)) {
                $upd_pending = $db->prepare("UPDATE applicant_documents SET file_path = ?, file_size = ?, file_ext = ?, status = 'Uploaded', uploaded_at = CURRENT_TIMESTAMP WHERE applicant_id = ? AND document_name = ? AND status = 'Pending'");
                $upd_pending->execute([$filepath, $filesize, $ext, $applicant_id, $doc_name]);

                if ($upd_pending->rowCount() == 0) {
                    $ins = $db->prepare("INSERT INTO applicant_documents (applicant_id, client_mobile, client_pan, document_category, document_name, file_path, file_size, file_ext, status, owner_type, uploaded_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Uploaded', 'Applicant', CURRENT_TIMESTAMP)");
                    $ins->execute([$applicant_id, $client_app['mobile'] ?? '', $client_app['pan_number'] ?? '', $category, $doc_name, $filepath, $filesize, $ext]);
                }

                $db->prepare("UPDATE applicants SET last_activity_date = CURRENT_TIMESTAMP WHERE id = ?")->execute([$applicant_id]);
                log_activity("Client uploaded document: {$doc_name} for {$client_app['customer_name']}", "track.php");

                return_json(['success' => true, 'doc_name' => $doc_name, 'message' => 'Document uploaded successfully!']);
            }
            return_json(['error' => 'Failed to save uploaded file.'], 500);
            break;

        case 'request_client_document':
            $applicant_id = intval($_POST['applicant_id'] ?? 0);
            $doc_name = trim($_POST['document_name'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            if (!$applicant_id || !$doc_name) return_json(['error' => 'Applicant ID and Document Name required'], 400);

            $stmt = $db->prepare("INSERT INTO applicant_documents (applicant_id, document_category, document_name, status, notes) VALUES (?, 'Required', ?, 'Pending', ?)");
            $stmt->execute([$applicant_id, $doc_name, $notes]);

            $db->prepare("UPDATE applicants SET last_activity_date = CURRENT_TIMESTAMP WHERE id = ?")->execute([$applicant_id]);
            log_activity("Requested Document: {$doc_name} from applicant #{$applicant_id}", "settings.php");
            return_json(['success' => true, 'message' => "Requested '{$doc_name}' from client!"]);
            break;

        case 'client_submit_query':
            $token = preg_replace('/[^a-f0-9]/i', '', $_POST['token'] ?? $_GET['token'] ?? '');
            if (strlen($token) !== 32) return_json(['error' => 'Invalid or expired tracking token.'], 403);
            $t_stmt = $db->prepare("SELECT * FROM applicants WHERE tracking_token = ? AND datetime(tracking_token_created, '+30 days') > datetime('now')");
            $t_stmt->execute([$token]);
            $client_app = $t_stmt->fetch(PDO::FETCH_ASSOC);
            if (!$client_app) return_json(['error' => 'Invalid or expired tracking link.'], 403);

            $subject = trim($_POST['query_subject'] ?? 'General Query');
            $message = trim($_POST['query_message'] ?? '');
            if (empty($message)) return_json(['error' => 'Query message is required.'], 400);

            $ins = $db->prepare("INSERT INTO client_queries (applicant_id, query_subject, query_message, status) VALUES (?, ?, ?, 'Open')");
            $ins->execute([$client_app['id'], $subject, $message]);

            $db->prepare("UPDATE applicants SET last_activity_date = CURRENT_TIMESTAMP WHERE id = ?")->execute([$client_app['id']]);
            log_activity("Client raised query: {$subject} for {$client_app['customer_name']}", "track.php");

            return_json(['success' => true, 'message' => 'Your question has been sent to your Relationship Manager!']);
            break;

        case 'client_request_callback':
            $token = preg_replace('/[^a-f0-9]/i', '', $_POST['token'] ?? $_GET['token'] ?? '');
            if (strlen($token) !== 32) return_json(['error' => 'Invalid or expired tracking token.'], 403);
            $t_stmt = $db->prepare("SELECT * FROM applicants WHERE tracking_token = ? AND datetime(tracking_token_created, '+30 days') > datetime('now')");
            $t_stmt->execute([$token]);
            $client_app = $t_stmt->fetch(PDO::FETCH_ASSOC);
            if (!$client_app) return_json(['error' => 'Invalid or expired tracking link.'], 403);

            $pref_time = trim($_POST['preferred_time'] ?? 'As soon as possible');
            $notes = trim($_POST['notes'] ?? '');

            $ins = $db->prepare("INSERT INTO client_callbacks (applicant_id, preferred_time, notes, status) VALUES (?, ?, ?, 'Pending')");
            $ins->execute([$client_app['id'], $pref_time, $notes]);

            $db->prepare("UPDATE applicants SET last_activity_date = CURRENT_TIMESTAMP WHERE id = ?")->execute([$client_app['id']]);
            log_activity("Client requested callback ({$pref_time}) for {$client_app['customer_name']}", "track.php");

            return_json(['success' => true, 'message' => 'Callback request submitted! Your RM will call you shortly.']);
            break;

        case 'client_confirm_verification':
            $token = preg_replace('/[^a-f0-9]/i', '', $_POST['token'] ?? $_GET['token'] ?? '');
            if (strlen($token) !== 32) return_json(['error' => 'Invalid or expired tracking token.'], 403);
            $t_stmt = $db->prepare("SELECT * FROM applicants WHERE tracking_token = ? AND datetime(tracking_token_created, '+30 days') > datetime('now')");
            $t_stmt->execute([$token]);
            $client_app = $t_stmt->fetch(PDO::FETCH_ASSOC);
            if (!$client_app) return_json(['error' => 'Invalid or expired tracking link.'], 403);

            $schedule_id = intval($_POST['schedule_id'] ?? 0);
            $status = trim($_POST['status'] ?? 'Confirmed');
            if (!$schedule_id) return_json(['error' => 'Schedule ID required'], 400);

            $upd = $db->prepare("UPDATE verification_schedules SET client_confirmation = ? WHERE id = ? AND applicant_id = ?");
            $upd->execute([$status, $schedule_id, $client_app['id']]);

            $db->prepare("UPDATE applicants SET last_activity_date = CURRENT_TIMESTAMP WHERE id = ?")->execute([$client_app['id']]);
            log_activity("Client updated verification schedule #{$schedule_id} to {$status}", "track.php");

            return_json(['success' => true, 'message' => "Verification visit updated to '{$status}'."]);
            break;

        case 'client_accept_sanction':
            $token = preg_replace('/[^a-f0-9]/i', '', $_POST['token'] ?? $_GET['token'] ?? '');
            if (strlen($token) !== 32) return_json(['error' => 'Invalid or expired tracking token.'], 403);
            $t_stmt = $db->prepare("SELECT * FROM applicants WHERE tracking_token = ? AND datetime(tracking_token_created, '+30 days') > datetime('now')");
            $t_stmt->execute([$token]);
            $client_app = $t_stmt->fetch(PDO::FETCH_ASSOC);
            if (!$client_app) return_json(['error' => 'Invalid or expired tracking link.'], 403);

            $upd = $db->prepare("UPDATE applicants SET sanction_accepted = 1, sanction_accepted_at = CURRENT_TIMESTAMP, last_activity_date = CURRENT_TIMESTAMP WHERE id = ?");
            $upd->execute([$client_app['id']]);

            log_activity("Client digitally accepted sanction terms for {$client_app['customer_name']}", "track.php");

            return_json(['success' => true, 'message' => 'Sanction terms accepted! Disbursal process initiated.']);
            break;

        case 'schedule_verification':
            $applicant_id = intval($_POST['applicant_id'] ?? 0);
            $v_type = trim($_POST['verification_type'] ?? 'Residence FI');
            $sched_date = trim($_POST['scheduled_date'] ?? date('Y-m-d'));
            $sched_time = trim($_POST['scheduled_time'] ?? '11:00 AM');
            $off_name = trim($_POST['officer_name'] ?? '');
            $off_mobile = trim($_POST['officer_mobile'] ?? '');

            if (!$applicant_id) return_json(['error' => 'Applicant ID required'], 400);

            $ins = $db->prepare("INSERT INTO verification_schedules (applicant_id, verification_type, scheduled_date, scheduled_time, officer_name, officer_mobile, client_confirmation) VALUES (?, ?, ?, ?, ?, ?, 'Pending')");
            $ins->execute([$applicant_id, $v_type, $sched_date, $sched_time, $off_name, $off_mobile]);

            $db->prepare("UPDATE applicants SET last_activity_date = CURRENT_TIMESTAMP WHERE id = ?")->execute([$applicant_id]);
            log_activity("Scheduled physical verification visit for applicant #{$applicant_id}", "settings.php");

        case 'reset_crm_database':
            // Strictly Admin only check
            if (($_SESSION['role'] ?? '') !== 'Admin') {
                return_json(['error' => 'Access Denied: Only Administrator can perform CRM database reset.'], 403);
            }

            $admin_password = $_POST['admin_password'] ?? '';
            $confirm_text   = strtoupper(trim($_POST['confirm_text'] ?? ''));

            if (empty($admin_password)) {
                return_json(['error' => 'Admin password is required.'], 400);
            }

            if ($confirm_text !== 'RESET CRM DATABASE') {
                return_json(['error' => 'Invalid confirmation text. You must type RESET CRM DATABASE in exact uppercase.'], 400);
            }

            // Verify Admin password against database
            $user_id = $_SESSION['user_id'] ?? 0;
            $u_stmt = $db->prepare("SELECT password_hash, username FROM users WHERE id = ?");
            $u_stmt->execute([$user_id]);
            $admin_user = $u_stmt->fetch();

            if (!$admin_user || !password_verify($admin_password, $admin_user['password_hash'])) {
                return_json(['error' => 'Incorrect Admin Password. Database reset cancelled.'], 401);
            }

            // WIPE ALL TRANSACTIONAL & APPLICATION DATA (PRESERVING ALL STAFF ACCOUNTS, PASSWORDS & SYSTEM SCHEMA)
            $tables_to_wipe = [
                'applicants',
                'co_applicants',
                'applicant_documents',
                'applicant_bank_logins',
                'leads',
                'pre_leads',
                'field_visits',
                'field_visit_followups',
                'reminders',
                'payout_distributions',
                'bank_payout_invoices',
                'bank_queries',
                'sla_alerts',
                'verification_schedules'
            ];

            foreach ($tables_to_wipe as $tbl) {
                try {
                    $db->exec("DELETE FROM {$tbl};");
                    $db->exec("DELETE FROM sqlite_sequence WHERE name = '{$tbl}';");
                } catch (Exception $e) {}
            }

            // Reset activity logs
            try {
                $db->exec("DELETE FROM activity_logs;");
            } catch (Exception $e) {}

            log_activity("CRITICAL: Master CRM Database Reset executed by Admin ({$admin_user['username']}). All applications and lead records wiped cleanly. Staff & Users preserved.", "settings.php");

            return_json([
                'success' => true,
                'message' => 'CRM Database wiped successfully! All old records cleared. All staff accounts, passwords & settings remain 100% safe.'
            ]);
            break;

        // ===== FUND ALLOCATION & UTILIZATION MANAGEMENT MODULE ENDPOINTS =====

        case 'get_fund_dashboard_stats':
            $total_sanctioned = (float)$db->query("SELECT COALESCE(SUM(sanctioned_amount), 0) FROM funds")->fetchColumn();
            $total_received = (float)$db->query("SELECT COALESCE(SUM(amount_received), 0) FROM fund_receipts WHERE status IN ('Approved', 'Received', 'Active')")->fetchColumn();
            $total_allocated = (float)$db->query("SELECT COALESCE(SUM(allocation_amount), 0) FROM fund_allocations WHERE status IN ('Approved', 'Active')")->fetchColumn();
            $total_disbursed = (float)$db->query("SELECT COALESCE(SUM(payment_amount), 0) FROM fund_disbursements WHERE status IN ('Completed', 'Approved', 'Paid')")->fetchColumn();
            $total_utilized = (float)$db->query("SELECT COALESCE(SUM(expense_amount), 0) FROM fund_utilizations WHERE verification_status IN ('Verified', 'Approved')")->fetchColumn();

            $available_balance = $total_received - $total_disbursed;
            $pending_utilization = $total_disbursed - $total_utilized;
            if ($pending_utilization < 0) $pending_utilization = 0;
            $pending_disbursement = $total_allocated - $total_disbursed;
            if ($pending_disbursement < 0) $pending_disbursement = 0;
            $unutilized_fund = $pending_utilization;

            // Attention Required counts
            $payments_awaiting_utilization_proof = (int)$db->query("
                SELECT COUNT(*) FROM fund_disbursements fd 
                WHERE status IN ('Completed', 'Approved', 'Paid') 
                AND (SELECT COALESCE(SUM(expense_amount), 0) FROM fund_utilizations WHERE payment_id = fd.id AND verification_status = 'Verified') < fd.payment_amount
            ")->fetchColumn();

            $bills_awaiting_verification = (int)$db->query("
                SELECT COUNT(*) FROM fund_utilizations WHERE verification_status IN ('Pending', 'Pending Review')
            ")->fetchColumn();

            $allocations_awaiting_payment = (int)$db->query("
                SELECT COUNT(*) FROM fund_allocations fa 
                WHERE status IN ('Approved', 'Active') 
                AND (SELECT COALESCE(SUM(payment_amount), 0) FROM fund_disbursements WHERE allocation_id = fa.id AND status IN ('Completed', 'Approved', 'Paid')) < fa.allocation_amount
            ")->fetchColumn();

            $projects_near_budget_limit = (int)$db->query("
                SELECT COUNT(*) FROM projects p 
                WHERE budget > 0 
                AND (SELECT COALESCE(SUM(allocation_amount), 0) FROM fund_allocations WHERE project_id = p.id AND status IN ('Approved', 'Active')) >= (budget * 0.8)
            ")->fetchColumn();

            $pending_approvals = (int)$db->query("SELECT COUNT(*) FROM fund_allocations WHERE status = 'Pending'")->fetchColumn() +
                                 (int)$db->query("SELECT COUNT(*) FROM fund_receipts WHERE status = 'Pending'")->fetchColumn() +
                                 (int)$db->query("SELECT COUNT(*) FROM fund_disbursements WHERE status = 'Pending'")->fetchColumn();

            // Recent Activity Feed (10 most recent actions across system)
            $activities = [];
            
            // Receipts
            $rec_items = $db->query("SELECT 'receipt' as type, amount_received as amount, received_date as activity_date, description as title, created_at FROM fund_receipts ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rec_items as $r) {
                $activities[] = [
                    'type' => 'receipt',
                    'icon' => '💰',
                    'text' => '₹' . number_format($r['amount'], 2) . ' received' . ($r['title'] ? ' (' . $r['title'] . ')' : ''),
                    'date' => $r['activity_date'] ?: date('Y-m-d', strtotime($r['created_at'])),
                    'raw_date' => $r['created_at'] ?? $r['activity_date']
                ];
            }

            // Allocations
            $alloc_items = $db->query("SELECT fa.allocation_amount as amount, fa.allocation_date as activity_date, fa.purpose, fa.created_at, p.project_name, r.name as receiver_name FROM fund_allocations fa LEFT JOIN projects p ON fa.project_id = p.id LEFT JOIN receivers r ON fa.receiver_id = r.id ORDER BY fa.id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($alloc_items as $a) {
                $target = $a['project_name'] ?: ($a['receiver_name'] ?: 'Project/Receiver');
                $activities[] = [
                    'type' => 'allocation',
                    'icon' => '🎯',
                    'text' => '₹' . number_format($a['amount'], 2) . ' allocated to ' . $target,
                    'date' => $a['activity_date'] ?: date('Y-m-d', strtotime($a['created_at'])),
                    'raw_date' => $a['created_at'] ?? $a['activity_date']
                ];
            }

            // Disbursements
            $disb_items = $db->query("SELECT fd.payment_amount as amount, fd.payment_date as activity_date, fd.created_at, r.name as receiver_name FROM fund_disbursements fd LEFT JOIN receivers r ON fd.receiver_id = r.id ORDER BY fd.id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($disb_items as $d) {
                $activities[] = [
                    'type' => 'disbursement',
                    'icon' => '💳',
                    'text' => '₹' . number_format($d['amount'], 2) . ' paid to ' . ($d['receiver_name'] ?: 'Receiver'),
                    'date' => $d['activity_date'] ?: date('Y-m-d', strtotime($d['created_at'])),
                    'raw_date' => $d['created_at'] ?? $d['activity_date']
                ];
            }

            // Utilizations
            $util_items = $db->query("SELECT fu.expense_amount as amount, fu.expense_date as activity_date, fu.expense_category, fu.created_at FROM fund_utilizations fu ORDER BY fu.id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($util_items as $u) {
                $activities[] = [
                    'type' => 'utilization',
                    'icon' => '🧾',
                    'text' => '₹' . number_format($u['amount'], 2) . ' bill uploaded for ' . ($u['expense_category'] ?: 'Expense'),
                    'date' => $u['activity_date'] ?: date('Y-m-d', strtotime($u['created_at'])),
                    'raw_date' => $u['created_at'] ?? $u['activity_date']
                ];
            }

            usort($activities, function($a, $b) {
                return strcmp($b['raw_date'], $a['raw_date']);
            });
            $activities = array_slice($activities, 0, 10);

            // Fund Journey Summary
            $fund_journey = [
                'received' => $total_received,
                'allocated' => $total_allocated,
                'disbursed' => $total_disbursed,
                'utilized' => $total_utilized,
                'pending_utilization' => $pending_utilization,
                'available_balance' => $available_balance
            ];

            return_json([
                'success' => true,
                'stats' => [
                    'total_sanctioned' => $total_sanctioned,
                    'total_received' => $total_received,
                    'total_allocated' => $total_allocated,
                    'total_disbursed' => $total_disbursed,
                    'total_utilized' => $total_utilized,
                    'available_balance' => $available_balance,
                    'pending_utilization' => $pending_utilization,
                    'pending_disbursement' => $pending_disbursement,
                    'unutilized_fund' => $unutilized_fund,
                    'pending_approvals' => $pending_approvals,
                    'attention_required' => [
                        'payments_awaiting_utilization_proof' => $payments_awaiting_utilization_proof,
                        'bills_awaiting_verification' => $bills_awaiting_verification,
                        'allocations_awaiting_payment' => $allocations_awaiting_payment,
                        'projects_near_budget_limit' => $projects_near_budget_limit
                    ],
                    'formatted' => [
                        'total_sanctioned' => '₹' . number_format($total_sanctioned, 2),
                        'total_received' => '₹' . number_format($total_received, 2),
                        'total_allocated' => '₹' . number_format($total_allocated, 2),
                        'total_disbursed' => '₹' . number_format($total_disbursed, 2),
                        'total_utilized' => '₹' . number_format($total_utilized, 2),
                        'available_balance' => '₹' . number_format($available_balance, 2),
                        'pending_utilization' => '₹' . number_format($pending_utilization, 2),
                        'pending_disbursement' => '₹' . number_format($pending_disbursement, 2),
                        'unutilized_fund' => '₹' . number_format($unutilized_fund, 2),
                    ]
                ],
                'activity_feed' => $activities,
                'fund_journey' => $fund_journey
            ]);
            break;

        case 'save_fund_source':
            $id = (int)($_POST['id'] ?? 0);
            $source_name = trim($_POST['source_name'] ?? '');
            if (empty($source_name)) {
                return_json(['error' => 'Source Name is required.'], 400);
            }

            $source_type = trim($_POST['source_type'] ?? 'Government');
            $organization_name = trim($_POST['organization_name'] ?? '');
            $department = trim($_POST['department'] ?? '');
            $contact_person = trim($_POST['contact_person'] ?? '');
            $mobile = trim($_POST['mobile'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            $status = trim($_POST['status'] ?? 'Active');
            $user_name = $_SESSION['username'] ?? 'Admin';

            if ($id > 0) {
                $old_val = $db->query("SELECT * FROM fund_sources WHERE id = {$id}")->fetch(PDO::FETCH_ASSOC);
                $stmt = $db->prepare("UPDATE fund_sources SET source_name=?, source_type=?, organization_name=?, department=?, contact_person=?, mobile=?, email=?, address=?, notes=?, status=?, updated_at=CURRENT_TIMESTAMP WHERE id=?");
                $stmt->execute([$source_name, $source_type, $organization_name, $department, $contact_person, $mobile, $email, $address, $notes, $status, $id]);
                if (!function_exists('log_fund_audit')) {
                    function log_fund_audit($db, $action, $old_val = null, $new_val = null, $ref_no = null) {
                        try {
                            $stmt = $db->prepare("INSERT INTO fund_audit_logs (user_id, username, action, old_value, new_value, reference_no, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
                            $stmt->execute([
                                $_SESSION['user_id'] ?? null,
                                $_SESSION['username'] ?? 'System',
                                $action,
                                is_array($old_val) ? json_encode($old_val) : $old_val,
                                is_array($new_val) ? json_encode($new_val) : $new_val,
                                $ref_no,
                                $_SERVER['REMOTE_ADDR'] ?? ''
                            ]);
                        } catch (Exception $e) {}
                    }
                }
                log_fund_audit($db, "Updated Fund Source", $old_val, $_POST, $old_val['source_id'] ?? null);
            } else {
                if (!function_exists('log_fund_audit')) {
                    function log_fund_audit($db, $action, $old_val = null, $new_val = null, $ref_no = null) {
                        try {
                            $stmt = $db->prepare("INSERT INTO fund_audit_logs (user_id, username, action, old_value, new_value, reference_no, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
                            $stmt->execute([
                                $_SESSION['user_id'] ?? null,
                                $_SESSION['username'] ?? 'System',
                                $action,
                                is_array($old_val) ? json_encode($old_val) : $old_val,
                                is_array($new_val) ? json_encode($new_val) : $new_val,
                                $ref_no,
                                $_SERVER['REMOTE_ADDR'] ?? ''
                            ]);
                        } catch (Exception $e) {}
                    }
                }
                $source_id = 'SRC-2026-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
                $stmt = $db->prepare("INSERT INTO fund_sources (source_id, source_name, source_type, organization_name, department, contact_person, mobile, email, address, notes, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$source_id, $source_name, $source_type, $organization_name, $department, $contact_person, $mobile, $email, $address, $notes, $status, $user_name]);
                $id = $db->lastInsertId();
                log_fund_audit($db, "Created Fund Source", null, $_POST, $source_id);
            }

            return_json(['success' => true, 'message' => 'Fund Source saved successfully.', 'id' => $id]);
            break;

        case 'get_fund_sources':
            $sources = $db->query("SELECT * FROM fund_sources ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
            return_json(['success' => true, 'sources' => $sources]);
            break;

        case 'save_fund_master':
            $id = (int)($_POST['id'] ?? 0);
            $fund_name = trim($_POST['fund_name'] ?? '');
            $source_id = (int)($_POST['source_id'] ?? 0);
            if (empty($fund_name) || !$source_id) {
                return_json(['error' => 'Fund Name and Fund Source are required.'], 400);
            }

            $fund_code = trim($_POST['fund_code'] ?? '');
            $scheme = trim($_POST['scheme'] ?? '');
            $project_id = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null;
            $department = trim($_POST['department'] ?? '');
            $financial_year = trim($_POST['financial_year'] ?? '2026-2027');
            $sanction_number = trim($_POST['sanction_number'] ?? '');
            $sanction_date = !empty($_POST['sanction_date']) ? $_POST['sanction_date'] : null;
            $sanctioned_amount = (float)($_POST['sanctioned_amount'] ?? 0);
            $purpose = trim($_POST['purpose'] ?? '');
            $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
            $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
            $description = trim($_POST['description'] ?? '');
            $approval_authority = trim($_POST['approval_authority'] ?? '');
            $status = trim($_POST['status'] ?? 'Sanctioned');
            $user_name = $_SESSION['username'] ?? 'Admin';

            // Sanction Letter Upload
            $sanction_letter_path = null;
            if (isset($_FILES['sanction_letter_file']) && $_FILES['sanction_letter_file']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = __DIR__ . '/uploads/fund_docs/';
                if (!is_dir($upload_dir)) @mkdir($upload_dir, 0777, true);
                $ext = pathinfo($_FILES['sanction_letter_file']['name'], PATHINFO_EXTENSION);
                $filename = 'sanction_' . time() . '_' . rand(100,999) . '.' . $ext;
                if (move_uploaded_file($_FILES['sanction_letter_file']['tmp_name'], $upload_dir . $filename)) {
                    $sanction_letter_path = 'uploads/fund_docs/' . $filename;
                }
            }

            if ($id > 0) {
                $old_val = $db->query("SELECT * FROM funds WHERE id = {$id}")->fetch(PDO::FETCH_ASSOC);
                $filePath = $sanction_letter_path ?: ($old_val['sanction_letter_path'] ?? null);
                $stmt = $db->prepare("UPDATE funds SET fund_name=?, fund_code=?, source_id=?, scheme=?, project_id=?, department=?, financial_year=?, sanction_number=?, sanction_date=?, sanctioned_amount=?, purpose=?, start_date=?, end_date=?, description=?, approval_authority=?, sanction_letter_path=?, status=? WHERE id=?");
                $stmt->execute([$fund_name, $fund_code, $source_id, $scheme, $project_id, $department, $financial_year, $sanction_number, $sanction_date, $sanctioned_amount, $purpose, $start_date, $end_date, $description, $approval_authority, $filePath, $status, $id]);
                log_fund_audit($db, "Updated Fund Master", $old_val, $_POST, $old_val['fund_id'] ?? null);
            } else {
                $fund_id = 'FUND-2026-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
                $stmt = $db->prepare("INSERT INTO funds (fund_id, fund_name, fund_code, source_id, scheme, project_id, department, financial_year, sanction_number, sanction_date, sanctioned_amount, purpose, start_date, end_date, description, approval_authority, sanction_letter_path, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$fund_id, $fund_name, $fund_code, $source_id, $scheme, $project_id, $department, $financial_year, $sanction_number, $sanction_date, $sanctioned_amount, $purpose, $start_date, $end_date, $description, $approval_authority, $sanction_letter_path, $status, $user_name]);
                $id = $db->lastInsertId();
                log_fund_audit($db, "Created Fund Master", null, $_POST, $fund_id);
            }

            return_json(['success' => true, 'message' => 'Fund Master saved successfully.', 'id' => $id]);
            break;

        case 'get_funds':
            $funds = $db->query("SELECT f.*, fs.source_name, p.project_name FROM funds f LEFT JOIN fund_sources fs ON f.source_id = fs.id LEFT JOIN projects p ON f.project_id = p.id ORDER BY f.id DESC")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($funds as &$f) {
                $fid = (int)$f['id'];
                $rec = (float)$db->query("SELECT COALESCE(SUM(amount_received), 0) FROM fund_receipts WHERE fund_id = {$fid} AND status = 'Approved'")->fetchColumn();
                $alloc = (float)$db->query("SELECT COALESCE(SUM(allocation_amount), 0) FROM fund_allocations WHERE fund_id = {$fid} AND status = 'Approved'")->fetchColumn();
                $disb = (float)$db->query("SELECT COALESCE(SUM(payment_amount), 0) FROM fund_disbursements WHERE fund_id = {$fid} AND status = 'Completed'")->fetchColumn();
                $util = (float)$db->query("SELECT COALESCE(SUM(expense_amount), 0) FROM fund_utilizations WHERE fund_id = {$fid} AND verification_status = 'Verified'")->fetchColumn();

                $f['total_received'] = $rec;
                $f['total_allocated'] = $alloc;
                $f['total_disbursed'] = $disb;
                $f['total_utilized'] = $util;
                $f['available_balance'] = $rec - $disb;
            }
            return_json(['success' => true, 'funds' => $funds]);
            break;

        case 'save_fund_receipt':
            $fund_id = !empty($_POST['fund_id']) ? (int)$_POST['fund_id'] : 1;
            $amount_received = (float)($_POST['amount_received'] ?? 0);
            if ($amount_received <= 0) {
                return_json(['error' => 'Valid Received Amount (> 0) is required.'], 400);
            }

            $source_id = !empty($_POST['source_id']) ? (int)$_POST['source_id'] : 0;
            $source_name = trim($_POST['source_name'] ?? '');

            if (!empty($source_name)) {
                $src_stmt = $db->prepare("SELECT id, source_name FROM fund_sources WHERE LOWER(source_name) = LOWER(?) LIMIT 1");
                $src_stmt->execute([$source_name]);
                $existing_src = $src_stmt->fetch(PDO::FETCH_ASSOC);
                if ($existing_src) {
                    $source_id = (int)$existing_src['id'];
                    $source_name = $existing_src['source_name'];
                } else {
                    $auto_src_code = 'SRC-2026-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    $ins_src = $db->prepare("INSERT INTO fund_sources (source_id, source_name, status) VALUES (?, ?, 'Active')");
                    $ins_src->execute([$auto_src_code, $source_name]);
                    $source_id = (int)$db->lastInsertId();
                }
            } else if ($source_id > 0) {
                $s_row = $db->query("SELECT source_name FROM fund_sources WHERE id = {$source_id}")->fetch(PDO::FETCH_ASSOC);
                if ($s_row) $source_name = $s_row['source_name'];
            }

            if (!$source_id) $source_id = 1;
            if (empty($source_name)) $source_name = "Praveen Sir (Head Office)";

            $received_date = !empty($_POST['received_date']) ? $_POST['received_date'] : date('Y-m-d');
            $payment_mode = trim($_POST['payment_mode'] ?? 'Bank Transfer');
            $bank_account = trim($_POST['bank_account'] ?? '');
            $bank_name = trim($_POST['bank_name'] ?? '');
            $utr_number = trim($_POST['utr_number'] ?? '');
            $cheque_number = trim($_POST['cheque_number'] ?? '');
            $ref_number = trim($_POST['ref_number'] ?? '');
            $installment_no = (int)($_POST['installment_no'] ?? 1);
            $description = trim($_POST['description'] ?? '');
            $status = trim($_POST['status'] ?? 'Approved');
            $user_name = $_SESSION['username'] ?? 'Admin';

            // Receipt Proof Upload
            $receipt_proof_path = null;
            if (isset($_FILES['receipt_proof_file']) && $_FILES['receipt_proof_file']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = __DIR__ . '/uploads/receipt_proofs/';
                if (!is_dir($upload_dir)) @mkdir($upload_dir, 0777, true);
                $ext = pathinfo($_FILES['receipt_proof_file']['name'], PATHINFO_EXTENSION);
                $filename = 'receipt_' . time() . '_' . rand(100,999) . '.' . $ext;
                if (move_uploaded_file($_FILES['receipt_proof_file']['tmp_name'], $upload_dir . $filename)) {
                    $receipt_proof_path = 'uploads/receipt_proofs/' . $filename;
                }
            }

            $receipt_id = 'REC-2026-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $stmt = $db->prepare("INSERT INTO fund_receipts (receipt_id, fund_id, source_id, amount_received, received_date, payment_mode, bank_account, bank_name, utr_number, cheque_number, ref_number, installment_no, description, receipt_proof_path, created_by, approved_by, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$receipt_id, $fund_id, $source_id, $amount_received, $received_date, $payment_mode, $bank_account, $bank_name, $utr_number, $cheque_number, $ref_number, $installment_no, $description, $receipt_proof_path, $user_name, $user_name, $status]);
            $rec_insert_id = $db->lastInsertId();

            // Automatic Ledger Entry (Credit)
            $last_bal = (float)$db->query("SELECT running_balance FROM fund_ledger_entries WHERE fund_id = {$fund_id} ORDER BY id DESC LIMIT 1")->fetchColumn();
            $new_bal = $last_bal + $amount_received;
            $ledger_desc = "Received from: " . $source_name . ($description ? " (" . $description . ")" : "");
            $ledger_stmt = $db->prepare("INSERT INTO fund_ledger_entries (fund_id, transaction_type, reference_no, debit_amount, credit_amount, running_balance, transaction_date, description, created_by) VALUES (?, 'Credit', ?, 0, ?, ?, ?, ?, ?)");
            $ledger_stmt->execute([$fund_id, $receipt_id, $amount_received, $new_bal, $received_date, $ledger_desc, $user_name]);

            log_fund_audit($db, "Created Fund Receipt", null, $_POST, $receipt_id);
            log_permanent_activity($db, "💰 Received ₹" . number_format($amount_received) . " capital from " . $source_name . " [Ref: " . $receipt_id . "]");
            return_json(['success' => true, 'message' => 'Capital Receipt of ₹' . number_format($amount_received) . ' saved and credited to operating balance.', 'id' => $rec_insert_id, 'receipt_id' => $receipt_id]);
            break;

        case 'get_fund_receipts':
            $receipts = $db->query("SELECT fr.*, f.fund_name, fs.source_name FROM fund_receipts fr LEFT JOIN funds f ON fr.fund_id = f.id LEFT JOIN fund_sources fs ON fr.source_id = fs.id ORDER BY fr.id DESC")->fetchAll(PDO::FETCH_ASSOC);
            return_json(['success' => true, 'receipts' => $receipts]);
            break;

        case 'save_project':
            $id = (int)($_POST['id'] ?? 0);
            $project_name = trim($_POST['project_name'] ?? '');
            if (empty($project_name)) {
                return_json(['error' => 'Project Name is required.'], 400);
            }

            $project_code = trim($_POST['project_code'] ?? '');
            $scheme = trim($_POST['scheme'] ?? '');
            $department = trim($_POST['department'] ?? '');
            $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
            $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
            $project_manager = trim($_POST['project_manager'] ?? '');
            $budget = (float)($_POST['budget'] ?? 0);
            $description = trim($_POST['description'] ?? '');
            $location = trim($_POST['location'] ?? '');
            $status = trim($_POST['status'] ?? 'Active');

            // Doc Upload
            $documents_path = null;
            if (isset($_FILES['project_doc_file']) && $_FILES['project_doc_file']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = __DIR__ . '/uploads/project_docs/';
                if (!is_dir($upload_dir)) @mkdir($upload_dir, 0777, true);
                $ext = pathinfo($_FILES['project_doc_file']['name'], PATHINFO_EXTENSION);
                $filename = 'project_' . time() . '_' . rand(100,999) . '.' . $ext;
                if (move_uploaded_file($_FILES['project_doc_file']['tmp_name'], $upload_dir . $filename)) {
                    $documents_path = 'uploads/project_docs/' . $filename;
                }
            }

            if ($id > 0) {
                $old_val = $db->query("SELECT * FROM projects WHERE id = {$id}")->fetch(PDO::FETCH_ASSOC);
                $doc = $documents_path ?: ($old_val['documents_path'] ?? null);
                $stmt = $db->prepare("UPDATE projects SET project_name=?, project_code=?, scheme=?, department=?, start_date=?, end_date=?, project_manager=?, budget=?, description=?, location=?, status=?, documents_path=? WHERE id=?");
                $stmt->execute([$project_name, $project_code, $scheme, $department, $start_date, $end_date, $project_manager, $budget, $description, $location, $status, $doc, $id]);
                log_fund_audit($db, "Updated Project", $old_val, $_POST, $old_val['project_id'] ?? null);
            } else {
                $project_id = 'PRJ-2026-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
                $stmt = $db->prepare("INSERT INTO projects (project_id, project_name, project_code, scheme, department, start_date, end_date, project_manager, budget, description, location, status, documents_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$project_id, $project_name, $project_code, $scheme, $department, $start_date, $end_date, $project_manager, $budget, $description, $location, $status, $documents_path]);
                $id = $db->lastInsertId();
                log_fund_audit($db, "Created Project", null, $_POST, $project_id);
            }

            return_json(['success' => true, 'message' => 'Project saved successfully.', 'id' => $id]);
            break;

        case 'get_projects':
            $projects = $db->query("SELECT * FROM projects ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
            return_json(['success' => true, 'projects' => $projects]);
            break;

        case 'get_project_detail':
            $project_id = (int)($_GET['id'] ?? 0);
            $project = $db->query("SELECT * FROM projects WHERE id = {$project_id}")->fetch(PDO::FETCH_ASSOC);
            if (!$project) {
                return_json(['error' => 'Project not found.'], 404);
            }

            $allocated = (float)$db->query("SELECT COALESCE(SUM(allocation_amount), 0) FROM fund_allocations WHERE project_id = {$project_id} AND status IN ('Approved', 'Active')")->fetchColumn();
            $paid = (float)$db->query("SELECT COALESCE(SUM(payment_amount), 0) FROM fund_disbursements WHERE project_id = {$project_id} AND status IN ('Completed', 'Approved', 'Paid')")->fetchColumn();
            $utilized = (float)$db->query("SELECT COALESCE(SUM(expense_amount), 0) FROM fund_utilizations WHERE project_id = {$project_id} AND verification_status IN ('Verified', 'Approved')")->fetchColumn();
            $pending_proof = $paid - $utilized;
            if ($pending_proof < 0) $pending_proof = 0;
            $budget = (float)($project['budget'] ?? 0);
            $balance = $budget > 0 ? ($budget - $paid) : ($allocated - $paid);
            if ($balance < 0) $balance = 0;

            $allocations = $db->query("SELECT fa.*, f.fund_name, r.name as receiver_name FROM fund_allocations fa LEFT JOIN funds f ON fa.fund_id = f.id LEFT JOIN receivers r ON fa.receiver_id = r.id WHERE fa.project_id = {$project_id} ORDER BY fa.id DESC")->fetchAll(PDO::FETCH_ASSOC);
            $payments = $db->query("SELECT fd.*, r.name as receiver_name FROM fund_disbursements fd LEFT JOIN receivers r ON fd.receiver_id = r.id WHERE fd.project_id = {$project_id} ORDER BY fd.id DESC")->fetchAll(PDO::FETCH_ASSOC);
            $expenses = $db->query("SELECT fu.*, r.name as receiver_name FROM fund_utilizations fu LEFT JOIN receivers r ON fu.receiver_id = r.id WHERE fu.project_id = {$project_id} ORDER BY fu.id DESC")->fetchAll(PDO::FETCH_ASSOC);

            return_json([
                'success' => true,
                'project' => $project,
                'summary' => [
                    'budget' => $budget,
                    'allocated' => $allocated,
                    'paid' => $paid,
                    'utilized' => $utilized,
                    'pending_proof' => $pending_proof,
                    'balance' => $balance
                ],
                'allocations' => $allocations,
                'payments' => $payments,
                'expenses' => $expenses
            ]);
            break;

        case 'save_receiver':
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            if (empty($name)) {
                return_json(['error' => 'Receiver Name is required.'], 400);
            }

            $receiver_type = trim($_POST['receiver_type'] ?? 'Vendor');
            $organization_name = trim($_POST['organization_name'] ?? '');
            $department = trim($_POST['department'] ?? '');
            $mobile = trim($_POST['mobile'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $pan = trim($_POST['pan'] ?? '');
            $gstin = trim($_POST['gstin'] ?? '');
            $bank_name = trim($_POST['bank_name'] ?? '');
            $account_holder_name = trim($_POST['account_holder_name'] ?? '');
            $account_number = trim($_POST['account_number'] ?? '');
            $ifsc = trim($_POST['ifsc'] ?? '');
            $upi_id = trim($_POST['upi_id'] ?? '');
            $status = trim($_POST['status'] ?? 'Active');
            $remarks = trim($_POST['remarks'] ?? '');

            // Document upload
            $documents_path = null;
            if (isset($_FILES['receiver_doc_file']) && $_FILES['receiver_doc_file']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = __DIR__ . '/uploads/receiver_docs/';
                if (!is_dir($upload_dir)) @mkdir($upload_dir, 0777, true);
                $ext = pathinfo($_FILES['receiver_doc_file']['name'], PATHINFO_EXTENSION);
                $filename = 'receiver_' . time() . '_' . rand(100,999) . '.' . $ext;
                if (move_uploaded_file($_FILES['receiver_doc_file']['tmp_name'], $upload_dir . $filename)) {
                    $documents_path = 'uploads/receiver_docs/' . $filename;
                }
            }

            if ($id > 0) {
                $old_val = $db->query("SELECT * FROM receivers WHERE id = {$id}")->fetch(PDO::FETCH_ASSOC);
                $doc = $documents_path ?: ($old_val['documents_path'] ?? null);
                $stmt = $db->prepare("UPDATE receivers SET receiver_type=?, name=?, organization_name=?, department=?, mobile=?, email=?, address=?, pan=?, gstin=?, bank_name=?, account_holder_name=?, account_number=?, ifsc=?, upi_id=?, status=?, remarks=?, documents_path=? WHERE id=?");
                $stmt->execute([$receiver_type, $name, $organization_name, $department, $mobile, $email, $address, $pan, $gstin, $bank_name, $account_holder_name, $account_number, $ifsc, $upi_id, $status, $remarks, $doc, $id]);
                log_fund_audit($db, "Updated Receiver", $old_val, $_POST, $old_val['receiver_id'] ?? null);
            } else {
                $receiver_id = 'RCV-2026-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
                $stmt = $db->prepare("INSERT INTO receivers (receiver_id, receiver_type, name, organization_name, department, mobile, email, address, pan, gstin, bank_name, account_holder_name, account_number, ifsc, upi_id, status, remarks, documents_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$receiver_id, $receiver_type, $name, $organization_name, $department, $mobile, $email, $address, $pan, $gstin, $bank_name, $account_holder_name, $account_number, $ifsc, $upi_id, $status, $remarks, $documents_path]);
                $id = $db->lastInsertId();
                log_fund_audit($db, "Created Receiver", null, $_POST, $receiver_id);
            }

            return_json(['success' => true, 'message' => 'Receiver saved successfully.', 'id' => $id]);
            break;

        case 'get_receivers':
            $receivers = $db->query("SELECT * FROM receivers ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
            return_json(['success' => true, 'receivers' => $receivers]);
            break;

        case 'get_receiver_detail':
            $receiver_id = (int)($_GET['id'] ?? 0);
            $receiver = $db->query("SELECT * FROM receivers WHERE id = {$receiver_id}")->fetch(PDO::FETCH_ASSOC);
            if (!$receiver) {
                return_json(['error' => 'Receiver not found.'], 404);
            }

            $allocated = (float)$db->query("SELECT COALESCE(SUM(allocation_amount), 0) FROM fund_allocations WHERE receiver_id = {$receiver_id} AND status IN ('Approved', 'Active')")->fetchColumn();
            $paid = (float)$db->query("SELECT COALESCE(SUM(payment_amount), 0) FROM fund_disbursements WHERE receiver_id = {$receiver_id} AND status IN ('Completed', 'Approved', 'Paid')")->fetchColumn();
            $utilized = (float)$db->query("SELECT COALESCE(SUM(expense_amount), 0) FROM fund_utilizations WHERE receiver_id = {$receiver_id} AND verification_status IN ('Verified', 'Approved')")->fetchColumn();
            $pending_proof = $paid - $utilized;
            if ($pending_proof < 0) $pending_proof = 0;
            $balance = $allocated - $paid;
            if ($balance < 0) $balance = 0;

            $transactions = [];
            $alloc_list = $db->query("SELECT 'Allocation' as type, allocation_amount as amount, allocation_date as txn_date, status, purpose as description, p.project_name FROM fund_allocations fa LEFT JOIN projects p ON fa.project_id = p.id WHERE fa.receiver_id = {$receiver_id} ORDER BY fa.id DESC")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($alloc_list as $a) $transactions[] = $a;

            $disb_list = $db->query("SELECT 'Payment' as type, payment_amount as amount, payment_date as txn_date, status, payment_purpose as description, p.project_name FROM fund_disbursements fd LEFT JOIN projects p ON fd.project_id = p.id WHERE fd.receiver_id = {$receiver_id} ORDER BY fd.id DESC")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($disb_list as $d) $transactions[] = $d;

            $util_list = $db->query("SELECT 'Expense / Utilization' as type, expense_amount as amount, expense_date as txn_date, verification_status as status, expense_description as description, p.project_name FROM fund_utilizations fu LEFT JOIN projects p ON fu.project_id = p.id WHERE fu.receiver_id = {$receiver_id} ORDER BY fu.id DESC")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($util_list as $u) $transactions[] = $u;

            usort($transactions, function($a, $b) {
                return strcmp($b['txn_date'], $a['txn_date']);
            });

            return_json([
                'success' => true,
                'receiver' => $receiver,
                'summary' => [
                    'allocated' => $allocated,
                    'paid' => $paid,
                    'utilized' => $utilized,
                    'pending_proof' => $pending_proof,
                    'balance' => $balance
                ],
                'transactions' => $transactions
            ]);
            break;

        case 'get_fund_journey':
            $fund_id = (int)($_GET['fund_id'] ?? 0);
            if ($fund_id > 0) {
                $fund = $db->query("SELECT * FROM funds WHERE id = {$fund_id}")->fetch(PDO::FETCH_ASSOC);
                $received = (float)$db->query("SELECT COALESCE(SUM(amount_received), 0) FROM fund_receipts WHERE fund_id = {$fund_id} AND status IN ('Approved', 'Received', 'Active')")->fetchColumn();
                $allocated = (float)$db->query("SELECT COALESCE(SUM(allocation_amount), 0) FROM fund_allocations WHERE fund_id = {$fund_id} AND status IN ('Approved', 'Active')")->fetchColumn();
                $disbursed = (float)$db->query("SELECT COALESCE(SUM(payment_amount), 0) FROM fund_disbursements WHERE fund_id = {$fund_id} AND status IN ('Completed', 'Approved', 'Paid')")->fetchColumn();
                $utilized = (float)$db->query("SELECT COALESCE(SUM(expense_amount), 0) FROM fund_utilizations WHERE fund_id = {$fund_id} AND verification_status IN ('Verified', 'Approved')")->fetchColumn();
                $fund_name = $fund['fund_name'] ?? 'Selected Fund';
            } else {
                $received = (float)$db->query("SELECT COALESCE(SUM(amount_received), 0) FROM fund_receipts WHERE status IN ('Approved', 'Received', 'Active')")->fetchColumn();
                $allocated = (float)$db->query("SELECT COALESCE(SUM(allocation_amount), 0) FROM fund_allocations WHERE status IN ('Approved', 'Active')")->fetchColumn();
                $disbursed = (float)$db->query("SELECT COALESCE(SUM(payment_amount), 0) FROM fund_disbursements WHERE status IN ('Completed', 'Approved', 'Paid')")->fetchColumn();
                $utilized = (float)$db->query("SELECT COALESCE(SUM(expense_amount), 0) FROM fund_utilizations WHERE verification_status IN ('Verified', 'Approved')")->fetchColumn();
                $fund_name = 'All Combined Funds';
            }

            $pending_utilization = $disbursed - $utilized;
            if ($pending_utilization < 0) $pending_utilization = 0;
            $available = $received - $disbursed;

            return_json([
                'success' => true,
                'journey' => [
                    'fund_name' => $fund_name,
                    'received' => $received,
                    'allocated' => $allocated,
                    'disbursed' => $disbursed,
                    'utilized' => $utilized,
                    'pending_utilization' => $pending_utilization,
                    'available_balance' => $available
                ]
            ]);
            break;

        case 'save_fund_allocation':
            $fund_id = (int)($_POST['fund_id'] ?? 0);
            $allocation_amount = (float)($_POST['allocation_amount'] ?? 0);
            if (!$fund_id || $allocation_amount <= 0) {
                return_json(['error' => 'Valid Fund and Allocation Amount (> 0) are required.'], 400);
            }

            // Calculate available fund balance for reference
            $tot_received = (float)$db->query("SELECT COALESCE(SUM(amount_received), 0) FROM fund_receipts WHERE fund_id = {$fund_id} AND status = 'Approved'")->fetchColumn();
            $tot_disbursed = (float)$db->query("SELECT COALESCE(SUM(payment_amount), 0) FROM fund_disbursements WHERE fund_id = {$fund_id} AND status = 'Completed'")->fetchColumn();
            $available_for_fund = $tot_received - $tot_disbursed;

            $project_id = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null;
            $scheme = trim($_POST['scheme'] ?? '');
            $department = trim($_POST['department'] ?? '');
            $receiver_id = !empty($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : null;
            $allocation_date = !empty($_POST['allocation_date']) ? $_POST['allocation_date'] : date('Y-m-d');
            $purpose = trim($_POST['purpose'] ?? '');
            $expected_utilization_date = !empty($_POST['expected_utilization_date']) ? $_POST['expected_utilization_date'] : null;
            $approval_authority = trim($_POST['approval_authority'] ?? '');
            $remarks = trim($_POST['remarks'] ?? '');
            $status = trim($_POST['status'] ?? 'Approved');
            $user_name = $_SESSION['username'] ?? 'Admin';

            // Document Upload
            $supporting_doc_path = null;
            if (isset($_FILES['supporting_doc_file']) && $_FILES['supporting_doc_file']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = __DIR__ . '/uploads/allocation_docs/';
                if (!is_dir($upload_dir)) @mkdir($upload_dir, 0777, true);
                $ext = pathinfo($_FILES['supporting_doc_file']['name'], PATHINFO_EXTENSION);
                $filename = 'alloc_' . time() . '_' . rand(100,999) . '.' . $ext;
                if (move_uploaded_file($_FILES['supporting_doc_file']['tmp_name'], $upload_dir . $filename)) {
                    $supporting_doc_path = 'uploads/allocation_docs/' . $filename;
                }
            }

            $allocation_id = 'ALLOC-2026-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $stmt = $db->prepare("INSERT INTO fund_allocations (allocation_id, fund_id, project_id, scheme, department, receiver_id, allocation_amount, allocation_date, purpose, expected_utilization_date, approval_authority, remarks, supporting_doc_path, status, created_by, approved_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$allocation_id, $fund_id, $project_id, $scheme, $department, $receiver_id, $allocation_amount, $allocation_date, $purpose, $expected_utilization_date, $approval_authority, $remarks, $supporting_doc_path, $status, $user_name, $user_name]);
            $alloc_insert_id = $db->lastInsertId();

            log_fund_audit($db, "Created Fund Allocation", null, $_POST, $allocation_id);
            return_json(['success' => true, 'message' => 'Fund Allocation saved successfully.', 'id' => $alloc_insert_id, 'allocation_id' => $allocation_id]);
            break;

        case 'get_fund_allocations':
            $allocations = $db->query("SELECT fa.*, f.fund_name, p.project_name, r.name as receiver_name, r.receiver_type FROM fund_allocations fa LEFT JOIN funds f ON fa.fund_id = f.id LEFT JOIN projects p ON fa.project_id = p.id LEFT JOIN receivers r ON fa.receiver_id = r.id ORDER BY fa.id DESC")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($allocations as &$alloc) {
                $aid = (int)$alloc['id'];
                $paid = (float)$db->query("SELECT COALESCE(SUM(payment_amount), 0) FROM fund_disbursements WHERE allocation_id = {$aid} AND status = 'Completed'")->fetchColumn();
                $alloc['paid_amount'] = $paid;
                $alloc['remaining_allocation'] = (float)$alloc['allocation_amount'] - $paid;
            }
            return_json(['success' => true, 'allocations' => $allocations]);
            break;

        case 'save_fund_disbursement':
            $payment_amount = (float)($_POST['payment_amount'] ?? 0);
            if ($payment_amount <= 0) {
                return_json(['error' => 'Valid Payment Amount (> 0) is required.'], 400);
            }

            // Track available balance for reference (allow negative balance / overdraft payouts)
            $total_received = (float)$db->query("SELECT COALESCE(SUM(amount_received), 0) FROM fund_receipts WHERE status = 'Approved'")->fetchColumn();
            $total_disbursed = (float)$db->query("SELECT COALESCE(SUM(payment_amount), 0) FROM fund_disbursements WHERE status IN ('Completed', 'Approved', 'Paid')")->fetchColumn();
            $current_available = $total_received - $total_disbursed;

            $allocation_id = (int)($_POST['allocation_id'] ?? 0);
            $fund_id = !empty($_POST['fund_id']) ? (int)$_POST['fund_id'] : 1;
            $receiver_id = !empty($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : 0;
            $project_id = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : 1;
            $receiver_name = trim($_POST['receiver_name'] ?? '');

            // Dynamic Receiver Name Resolution
            if (!empty($receiver_name)) {
                $rcv_stmt = $db->prepare("SELECT id, name FROM receivers WHERE LOWER(name) = LOWER(?) LIMIT 1");
                $rcv_stmt->execute([$receiver_name]);
                $existing_rcv = $rcv_stmt->fetch(PDO::FETCH_ASSOC);
                if ($existing_rcv) {
                    $receiver_id = (int)$existing_rcv['id'];
                    $receiver_name = $existing_rcv['name'];
                } else {
                    $auto_rcv_code = 'RCV-2026-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    $rcv_type = trim($_POST['receiver_type'] ?? 'Vendor');
                    $ins_rcv = $db->prepare("INSERT INTO receivers (receiver_id, receiver_type, name, status) VALUES (?, ?, ?, 'Active')");
                    $ins_rcv->execute([$auto_rcv_code, $rcv_type, $receiver_name]);
                    $receiver_id = (int)$db->lastInsertId();
                }
            } else if ($receiver_id > 0) {
                $r_row = $db->query("SELECT name FROM receivers WHERE id = {$receiver_id}")->fetch(PDO::FETCH_ASSOC);
                if ($r_row) $receiver_name = $r_row['name'];
            }

            if (!$receiver_id) $receiver_id = 1;
            if (empty($receiver_name)) $receiver_name = "Beneficiary";

            // If no allocation_id provided, create a direct allocation automatically on the fly
            if (!$allocation_id) {
                $auto_alloc_id = 'ALLOC-2026-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
                $alloc_stmt = $db->prepare("INSERT INTO fund_allocations (allocation_id, fund_id, project_id, receiver_id, allocation_amount, allocation_date, purpose, status, created_by) VALUES (?, ?, ?, ?, ?, date('now'), ?, 'Approved', ?)");
                $alloc_stmt->execute([$auto_alloc_id, $fund_id, $project_id, $receiver_id, $payment_amount, "Payment to " . $receiver_name, $_SESSION['username'] ?? 'Admin']);
                $allocation_id = $db->lastInsertId();
            } else {
                $alloc = $db->query("SELECT allocation_amount, fund_id, project_id, receiver_id FROM fund_allocations WHERE id = {$allocation_id}")->fetch(PDO::FETCH_ASSOC);
                if ($alloc) {
                    $fund_id = (int)$alloc['fund_id'];
                    if (!$receiver_id && !empty($alloc['receiver_id'])) $receiver_id = (int)$alloc['receiver_id'];
                    if (!$project_id && !empty($alloc['project_id'])) $project_id = (int)$alloc['project_id'];
                }
            }

            $payment_date = !empty($_POST['payment_date']) ? $_POST['payment_date'] : date('Y-m-d');
            $payment_mode = trim($_POST['payment_mode'] ?? 'NEFT/RTGS');
            $bank_account = trim($_POST['bank_account'] ?? '');
            $utr_number = trim($_POST['utr_number'] ?? '');
            $transaction_id = trim($_POST['transaction_id'] ?? '');
            $cheque_number = trim($_POST['cheque_number'] ?? '');
            $voucher_number = trim($_POST['voucher_number'] ?? '');
            $payment_purpose = trim($_POST['payment_purpose'] ?? '');
            $remarks = trim($_POST['remarks'] ?? '');
            $status = trim($_POST['status'] ?? 'Completed');
            $user_name = $_SESSION['username'] ?? 'Admin';

            // Payment proof upload
            $payment_proof_path = null;
            if (isset($_FILES['payment_proof_file']) && $_FILES['payment_proof_file']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = __DIR__ . '/uploads/payment_proofs/';
                if (!is_dir($upload_dir)) @mkdir($upload_dir, 0777, true);
                $ext = pathinfo($_FILES['payment_proof_file']['name'], PATHINFO_EXTENSION);
                $filename = 'pay_' . time() . '_' . rand(100,999) . '.' . $ext;
                if (move_uploaded_file($_FILES['payment_proof_file']['tmp_name'], $upload_dir . $filename)) {
                    $payment_proof_path = 'uploads/payment_proofs/' . $filename;
                }
            }

            $payment_id = 'PAY-2026-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $stmt = $db->prepare("INSERT INTO fund_disbursements (payment_id, allocation_id, fund_id, project_id, receiver_id, payment_amount, payment_date, payment_mode, bank_account, utr_number, transaction_id, cheque_number, voucher_number, payment_purpose, payment_proof_path, remarks, created_by, approved_by, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$payment_id, $allocation_id, $fund_id, $project_id, $receiver_id, $payment_amount, $payment_date, $payment_mode, $bank_account, $utr_number, $transaction_id, $cheque_number, $voucher_number, $payment_purpose, $payment_proof_path, $remarks, $user_name, $user_name, $status]);
            $disb_insert_id = $db->lastInsertId();

            // Automatic Ledger Entry (Debit)
            $last_bal = (float)$db->query("SELECT running_balance FROM fund_ledger_entries WHERE fund_id = {$fund_id} ORDER BY id DESC LIMIT 1")->fetchColumn();
            $new_bal = $last_bal - $payment_amount;
            $ledger_desc = "Paid to: " . $receiver_name . ($payment_purpose ? " (" . $payment_purpose . ")" : "");
            $ledger_stmt = $db->prepare("INSERT INTO fund_ledger_entries (fund_id, transaction_type, reference_no, debit_amount, credit_amount, running_balance, transaction_date, description, created_by) VALUES (?, 'Debit', ?, ?, 0, ?, ?, ?, ?)");
            $ledger_stmt->execute([$fund_id, $payment_id, $payment_amount, $new_bal, $payment_date, $ledger_desc, $user_name]);

            log_fund_audit($db, "Created Fund Disbursement", null, $_POST, $payment_id);
            log_permanent_activity($db, "💸 Paid ₹" . number_format($payment_amount) . " to " . $receiver_name . " [Ref: " . $payment_id . "]");
            return_json(['success' => true, 'message' => "Payment of ₹" . number_format($payment_amount) . " to " . $receiver_name . " recorded successfully.", 'id' => $disb_insert_id, 'payment_id' => $payment_id]);
            break;

        case 'get_fund_disbursements':
            $disbursements = $db->query("SELECT fd.*, fa.allocation_id as alloc_code, f.fund_name, p.project_name, r.name as receiver_name FROM fund_disbursements fd LEFT JOIN fund_allocations fa ON fd.allocation_id = fa.id LEFT JOIN funds f ON fd.fund_id = f.id LEFT JOIN projects p ON fd.project_id = p.id LEFT JOIN receivers r ON fd.receiver_id = r.id ORDER BY fd.id DESC")->fetchAll(PDO::FETCH_ASSOC);
            return_json(['success' => true, 'disbursements' => $disbursements]);
            break;

        case 'save_fund_utilization':
            $fund_id = (int)($_POST['fund_id'] ?? 0);
            $expense_category = trim($_POST['expense_category'] ?? '');
            $expense_description = trim($_POST['expense_description'] ?? '');
            $expense_amount = (float)($_POST['expense_amount'] ?? 0);
            if (!$fund_id || empty($expense_category) || empty($expense_description) || $expense_amount <= 0) {
                return_json(['error' => 'Fund, Expense Category, Description and Amount (> 0) are required.'], 400);
            }

            $allocation_id = !empty($_POST['allocation_id']) ? (int)$_POST['allocation_id'] : null;
            $payment_id = !empty($_POST['payment_id']) ? (int)$_POST['payment_id'] : null;
            $project_id = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null;
            $receiver_id = !empty($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : null;
            $expense_date = !empty($_POST['expense_date']) ? $_POST['expense_date'] : date('Y-m-d');
            $vendor_name = trim($_POST['vendor_name'] ?? '');
            $invoice_number = trim($_POST['invoice_number'] ?? '');
            $invoice_date = !empty($_POST['invoice_date']) ? $_POST['invoice_date'] : null;
            $gst_amount = (float)($_POST['gst_amount'] ?? 0);
            $payment_mode = trim($_POST['payment_mode'] ?? '');
            $remarks = trim($_POST['remarks'] ?? '');
            $verification_status = trim($_POST['verification_status'] ?? 'Verified');
            $user_name = $_SESSION['username'] ?? 'Admin';

            // Bill proof upload
            $bill_proof_path = null;
            if (isset($_FILES['bill_proof_file']) && $_FILES['bill_proof_file']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = __DIR__ . '/uploads/bill_proofs/';
                if (!is_dir($upload_dir)) @mkdir($upload_dir, 0777, true);
                $ext = pathinfo($_FILES['bill_proof_file']['name'], PATHINFO_EXTENSION);
                $filename = 'bill_' . time() . '_' . rand(100,999) . '.' . $ext;
                if (move_uploaded_file($_FILES['bill_proof_file']['tmp_name'], $upload_dir . $filename)) {
                    $bill_proof_path = 'uploads/bill_proofs/' . $filename;
                }
            }

            $utilization_id = 'UTIL-2026-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $stmt = $db->prepare("INSERT INTO fund_utilizations (utilization_id, fund_id, allocation_id, payment_id, project_id, receiver_id, expense_category, expense_description, expense_amount, expense_date, vendor_name, invoice_number, invoice_date, gst_amount, payment_mode, bill_proof_path, remarks, verification_status, verified_by, verified_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
            $stmt->execute([$utilization_id, $fund_id, $allocation_id, $payment_id, $project_id, $receiver_id, $expense_category, $expense_description, $expense_amount, $expense_date, $vendor_name, $invoice_number, $invoice_date, $gst_amount, $payment_mode, $bill_proof_path, $remarks, $verification_status, $user_name]);
            $util_insert_id = $db->lastInsertId();

            log_fund_audit($db, "Logged Expense Utilization", null, $_POST, $utilization_id);
            return_json(['success' => true, 'message' => 'Expense utilization logged successfully.', 'id' => $util_insert_id, 'utilization_id' => $utilization_id]);
            break;

        case 'get_fund_utilizations':
            $utilizations = $db->query("SELECT fu.*, f.fund_name, p.project_name, r.name as receiver_name FROM fund_utilizations fu LEFT JOIN funds f ON fu.fund_id = f.id LEFT JOIN projects p ON fu.project_id = p.id LEFT JOIN receivers r ON fu.receiver_id = r.id ORDER BY fu.id DESC")->fetchAll(PDO::FETCH_ASSOC);
            return_json(['success' => true, 'utilizations' => $utilizations]);
            break;

        case 'get_fund_ledger':
            $fund_id = isset($_GET['fund_id']) ? (int)$_GET['fund_id'] : 0;
            if ($fund_id > 0) {
                $ledger = $db->query("SELECT fle.*, f.fund_name FROM fund_ledger_entries fle LEFT JOIN funds f ON fle.fund_id = f.id WHERE fle.fund_id = {$fund_id} ORDER BY fle.id ASC")->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $ledger = $db->query("SELECT fle.*, f.fund_name FROM fund_ledger_entries fle LEFT JOIN funds f ON fle.fund_id = f.id ORDER BY fle.id ASC")->fetchAll(PDO::FETCH_ASSOC);
            }
            
            // Enrich with 24-Hour Edit & Delete Policy Metadata
            $now = time();
            foreach ($ledger as &$item) {
                $ref = $item['reference_no'] ?? '';
                $item['can_modify'] = false;
                $item['hours_remaining'] = 0;
                $item['source_table'] = '';
                $item['source_id'] = 0;

                if (strpos($ref, 'REC-') === 0) {
                    $rec = $db->query("SELECT fr.*, fs.source_name FROM fund_receipts fr LEFT JOIN fund_sources fs ON fr.source_id = fs.id WHERE fr.receipt_id = '{$ref}' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                    if ($rec) {
                        $created_ts = strtotime($rec['created_at'] ?? $item['created_at']);
                        $diff_sec = $now - $created_ts;
                        $item['can_modify'] = ($diff_sec <= 86400); // 24 hours = 86400 seconds
                        $item['hours_remaining'] = max(0, round((86400 - $diff_sec) / 3600, 1));
                        $item['source_table'] = 'fund_receipts';
                        $item['source_id'] = $rec['id'];
                        $item['proof_path'] = $rec['receipt_proof_path'] ?? null;
                        $item['payment_mode'] = $rec['payment_mode'] ?? 'Bank Transfer';
                        $item['utr_number'] = $rec['utr_number'] ?? '';
                        $item['notes'] = $rec['description'] ?? '';
                        $item['party_name'] = $rec['source_name'] ?? 'Praveen Sir (Head Office)';
                        $item['txn_details'] = $rec;
                    }
                } elseif (strpos($ref, 'PAY-') === 0) {
                    $disb = $db->query("SELECT fd.*, r.name as receiver_name FROM fund_disbursements fd LEFT JOIN receivers r ON fd.receiver_id = r.id WHERE fd.payment_id = '{$ref}' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                    if ($disb) {
                        $created_ts = strtotime($disb['created_at'] ?? $item['created_at']);
                        $diff_sec = $now - $created_ts;
                        $item['can_modify'] = ($diff_sec <= 86400); // 24 hours = 86400 seconds
                        $item['hours_remaining'] = max(0, round((86400 - $diff_sec) / 3600, 1));
                        $item['source_table'] = 'fund_disbursements';
                        $item['source_id'] = $disb['id'];
                        $item['proof_path'] = $disb['payment_proof_path'] ?? null;
                        $item['payment_mode'] = $disb['payment_mode'] ?? 'NEFT/RTGS';
                        $item['utr_number'] = $disb['utr_number'] ?? '';
                        $item['notes'] = $disb['payment_purpose'] ?? '';
                        $item['party_name'] = $disb['receiver_name'] ?? 'Beneficiary';
                        $item['txn_details'] = $disb;
                    }
                }
            }

            return_json(['success' => true, 'ledger' => $ledger]);
            break;

        case 'delete_fund_transaction':
            $type = trim($_POST['type'] ?? $_GET['type'] ?? '');
            $ref_no = trim($_POST['ref_no'] ?? $_GET['ref_no'] ?? '');
            $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);

            if (empty($type) && !empty($ref_no)) {
                if (strpos($ref_no, 'REC-') === 0) $type = 'receipt';
                elseif (strpos($ref_no, 'PAY-') === 0) $type = 'disbursement';
            }

            if ($type === 'receipt') {
                $stmt = $db->prepare("SELECT * FROM fund_receipts WHERE id = ? OR receipt_id = ?");
                $stmt->execute([$id, $ref_no]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) return_json(['error' => 'Receipt transaction not found.'], 404);

                $created_ts = strtotime($row['created_at']);
                if ((time() - $created_ts) > 86400) {
                    return_json(['error' => 'Security Policy: Financial transactions cannot be deleted after 24 hours.'], 403);
                }

                $receipt_id = $row['receipt_id'];
                $fund_id = $row['fund_id'] ?: 1;

                $db->prepare("DELETE FROM fund_receipts WHERE id = ?")->execute([$row['id']]);
                $db->prepare("DELETE FROM fund_ledger_entries WHERE reference_no = ? AND transaction_type = 'Credit'")->execute([$receipt_id]);

                // Recalculate running balance
                $entries = $db->query("SELECT id, transaction_type, debit_amount, credit_amount FROM fund_ledger_entries WHERE fund_id = {$fund_id} ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
                $running = 0.0;
                foreach ($entries as $e) {
                    if ($e['transaction_type'] === 'Credit') $running += (float)$e['credit_amount'];
                    else $running -= (float)$e['debit_amount'];
                    $db->prepare("UPDATE fund_ledger_entries SET running_balance = ? WHERE id = ?")->execute([$running, $e['id']]);
                }

                log_fund_audit($db, "Deleted Inward Capital Receipt within 24h window", $row, null, $receipt_id);
                return_json(['success' => true, 'message' => "Receipt {$receipt_id} deleted successfully within 24h window. Operating balance updated."]);

            } elseif ($type === 'disbursement') {
                $stmt = $db->prepare("SELECT * FROM fund_disbursements WHERE id = ? OR payment_id = ?");
                $stmt->execute([$id, $ref_no]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) return_json(['error' => 'Disbursement transaction not found.'], 404);

                $created_ts = strtotime($row['created_at']);
                if ((time() - $created_ts) > 86400) {
                    return_json(['error' => 'Security Policy: Financial transactions cannot be deleted after 24 hours.'], 403);
                }

                $payment_id = $row['payment_id'];
                $fund_id = $row['fund_id'] ?: 1;

                $db->prepare("DELETE FROM fund_disbursements WHERE id = ?")->execute([$row['id']]);
                $db->prepare("DELETE FROM fund_ledger_entries WHERE reference_no = ? AND transaction_type = 'Debit'")->execute([$payment_id]);

                // Recalculate running balance
                $entries = $db->query("SELECT id, transaction_type, debit_amount, credit_amount FROM fund_ledger_entries WHERE fund_id = {$fund_id} ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
                $running = 0.0;
                foreach ($entries as $e) {
                    if ($e['transaction_type'] === 'Credit') $running += (float)$e['credit_amount'];
                    else $running -= (float)$e['debit_amount'];
                    $db->prepare("UPDATE fund_ledger_entries SET running_balance = ? WHERE id = ?")->execute([$running, $e['id']]);
                }

                log_fund_audit($db, "Deleted Disbursement Payment within 24h window", $row, null, $payment_id);
                return_json(['success' => true, 'message' => "Payment {$payment_id} deleted successfully within 24h window. Balance restored."]);

            } else {
                return_json(['error' => 'Invalid transaction type for deletion.'], 400);
            }
            break;

        case 'update_fund_transaction':
            $type = trim($_POST['type'] ?? '');
            $ref_no = trim($_POST['ref_no'] ?? '');
            $id = intval($_POST['id'] ?? 0);

            if (strpos($ref_no, 'REC-') === 0) $type = 'receipt';
            elseif (strpos($ref_no, 'PAY-') === 0) $type = 'disbursement';

            if ($type === 'receipt') {
                $row = $db->query("SELECT * FROM fund_receipts WHERE id = {$id} OR receipt_id = '{$ref_no}'")->fetch(PDO::FETCH_ASSOC);
                if (!$row) return_json(['error' => 'Receipt not found.'], 404);

                if ((time() - strtotime($row['created_at'])) > 86400) {
                    return_json(['error' => 'Security Policy: Financial transactions cannot be edited after 24 hours.'], 403);
                }

                $amt = (float)($_POST['amount_received'] ?? $_POST['amount'] ?? 0);
                if ($amt <= 0) return_json(['error' => 'Valid amount (> 0) required.'], 400);
                $date = trim($_POST['received_date'] ?? $_POST['payment_date'] ?? date('Y-m-d'));
                $mode = trim($_POST['payment_mode'] ?? 'Bank Transfer');
                $utr = trim($_POST['utr_number'] ?? '');
                $desc = trim($_POST['description'] ?? '');

                $db->prepare("UPDATE fund_receipts SET amount_received = ?, received_date = ?, payment_mode = ?, utr_number = ?, description = ? WHERE id = ?")
                   ->execute([$amt, $date, $mode, $utr, $desc, $row['id']]);

                $db->prepare("UPDATE fund_ledger_entries SET credit_amount = ?, transaction_date = ?, description = ? WHERE reference_no = ? AND transaction_type = 'Credit'")
                   ->execute([$amt, $date, "Fund Received: " . ($desc ?: $row['receipt_id']), $row['receipt_id']]);

                $fund_id = $row['fund_id'] ?: 1;
                $entries = $db->query("SELECT id, transaction_type, debit_amount, credit_amount FROM fund_ledger_entries WHERE fund_id = {$fund_id} ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
                $running = 0.0;
                foreach ($entries as $e) {
                    if ($e['transaction_type'] === 'Credit') $running += (float)$e['credit_amount'];
                    else $running -= (float)$e['debit_amount'];
                    $db->prepare("UPDATE fund_ledger_entries SET running_balance = ? WHERE id = ?")->execute([$running, $e['id']]);
                }

                log_fund_audit($db, "Updated Inward Capital Receipt within 24h window", $row, $_POST, $row['receipt_id']);
                return_json(['success' => true, 'message' => 'Capital Receipt updated successfully.']);

            } elseif ($type === 'disbursement') {
                $row = $db->query("SELECT * FROM fund_disbursements WHERE id = {$id} OR payment_id = '{$ref_no}'")->fetch(PDO::FETCH_ASSOC);
                if (!$row) return_json(['error' => 'Payment not found.'], 404);

                if ((time() - strtotime($row['created_at'])) > 86400) {
                    return_json(['error' => 'Security Policy: Financial transactions cannot be edited after 24 hours.'], 403);
                }

                $amt = (float)($_POST['payment_amount'] ?? $_POST['amount'] ?? 0);
                if ($amt <= 0) return_json(['error' => 'Valid amount (> 0) required.'], 400);

                // Track available balance for reference
                $total_received = (float)$db->query("SELECT COALESCE(SUM(amount_received), 0) FROM fund_receipts WHERE status = 'Approved'")->fetchColumn();
                $total_disbursed = (float)$db->query("SELECT COALESCE(SUM(payment_amount), 0) FROM fund_disbursements WHERE status IN ('Completed', 'Approved', 'Paid') AND id != {$row['id']}")->fetchColumn();
                $current_available = $total_received - $total_disbursed;

                $date = trim($_POST['payment_date'] ?? date('Y-m-d'));
                $mode = trim($_POST['payment_mode'] ?? 'NEFT/RTGS');
                $utr = trim($_POST['utr_number'] ?? '');
                $purpose = trim($_POST['payment_purpose'] ?? '');
                $receiver_name = trim($_POST['receiver_name'] ?? '');

                if (!empty($receiver_name)) {
                    $rcv_stmt = $db->prepare("SELECT id, name FROM receivers WHERE LOWER(name) = LOWER(?) LIMIT 1");
                    $rcv_stmt->execute([$receiver_name]);
                    $existing_rcv = $rcv_stmt->fetch(PDO::FETCH_ASSOC);
                    if ($existing_rcv) {
                        $rcv_id = (int)$existing_rcv['id'];
                    } else {
                        $auto_rcv_code = 'RCV-2026-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
                        $ins_rcv = $db->prepare("INSERT INTO receivers (receiver_id, receiver_type, name, status) VALUES (?, 'Vendor', ?, 'Active')");
                        $ins_rcv->execute([$auto_rcv_code, $receiver_name]);
                        $rcv_id = (int)$db->lastInsertId();
                    }
                    $db->prepare("UPDATE fund_disbursements SET receiver_id = ? WHERE id = ?")->execute([$rcv_id, $row['id']]);
                } else {
                    $r_row = $db->query("SELECT name FROM receivers WHERE id = {$row['receiver_id']}")->fetch(PDO::FETCH_ASSOC);
                    $receiver_name = $r_row ? $r_row['name'] : 'Beneficiary';
                }

                $db->prepare("UPDATE fund_disbursements SET payment_amount = ?, payment_date = ?, payment_mode = ?, utr_number = ?, payment_purpose = ? WHERE id = ?")
                   ->execute([$amt, $date, $mode, $utr, $purpose, $row['id']]);

                $ledger_desc = "Paid to: " . $receiver_name . ($purpose ? " (" . $purpose . ")" : "");
                $db->prepare("UPDATE fund_ledger_entries SET debit_amount = ?, transaction_date = ?, description = ? WHERE reference_no = ? AND transaction_type = 'Debit'")
                   ->execute([$amt, $date, $ledger_desc, $row['payment_id']]);

                $fund_id = $row['fund_id'] ?: 1;
                $entries = $db->query("SELECT id, transaction_type, debit_amount, credit_amount FROM fund_ledger_entries WHERE fund_id = {$fund_id} ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
                $running = 0.0;
                foreach ($entries as $e) {
                    if ($e['transaction_type'] === 'Credit') $running += (float)$e['credit_amount'];
                    else $running -= (float)$e['debit_amount'];
                    $db->prepare("UPDATE fund_ledger_entries SET running_balance = ? WHERE id = ?")->execute([$running, $e['id']]);
                }

                log_fund_audit($db, "Updated Disbursement Payment within 24h window", $row, $_POST, $row['payment_id']);
                return_json(['success' => true, 'message' => 'Disbursement Payment updated successfully.']);
            }
            return_json(['error' => 'Invalid transaction type.'], 400);
            break;

        case 'get_fund_audit_logs':
            $audit_logs = $db->query("SELECT * FROM fund_audit_logs ORDER BY id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
            return_json(['success' => true, 'audit_logs' => $audit_logs]);
            break;

        case 'ai_generate_pitch':
            $name = trim($_POST['name'] ?? $_GET['name'] ?? 'Prospect');
            $loan_type = trim($_POST['loan_type'] ?? $_GET['loan_type'] ?? 'Personal Loan');
            $income = trim($_POST['monthly_income'] ?? $_GET['monthly_income'] ?? '');
            $company = trim($_POST['company_name'] ?? $_GET['company_name'] ?? '');
            $segment = trim($_POST['segment_name'] ?? $_GET['segment_name'] ?? 'General Pool');

            $prompt = "Prospect Name: $name\nProduct Interest: $loan_type\nMonthly Income: " . ($income ? "₹$income" : "Not specified") . "\nCompany: " . ($company ?: "Self/Individual") . "\nSegment Tag: $segment\n\nTask: Generate two short sections:\n[PITCH]\nGenerate a 2-3 sentence telecalling pitch in conversational Hinglish tailored specifically for this prospect.\n\n[WHATSAPP]\nGenerate a 3-line WhatsApp pitch with emojis and a clear Call To Action to apply with BFS Circle.";
            
            $system = "You are an AI Sales Coach for BFS Circle Financial Services. Be clear, polite, and persuasive.";
            
            $ai_res = call_ollama_ai($prompt, $system);
            if ($ai_res['success']) {
                return_json(['success' => true, 'ai_output' => $ai_res['response']]);
            } else {
                return_json(['success' => false, 'error' => $ai_res['error']]);
            }
            break;

        case 'ai_analyze_cam':
            $applicant_id = intval($_POST['applicant_id'] ?? $_GET['applicant_id'] ?? 0);
            if (!$applicant_id) return_json(['error' => 'Applicant ID required'], 400);

            $app = $db->query("SELECT * FROM applicants WHERE id = $applicant_id")->fetch(PDO::FETCH_ASSOC);
            if (!$app) return_json(['error' => 'Applicant not found'], 404);

            $co_apps = $db->query("SELECT * FROM co_applicants WHERE applicant_id = $applicant_id")->fetchAll(PDO::FETCH_ASSOC);
            
            $prompt = "Applicant Name: {$app['customer_name']}\nLoan Type: {$app['loan_type']}\nRequested Amount: ₹" . number_format($app['loan_amount_requested'] ?? 0) . "\nMonthly Income: ₹" . number_format($app['monthly_income'] ?? 0) . "\nCIBIL Score: " . ($app['cibil_score'] ?: 'N/A') . "\nEmployment: {$app['employment_type']}\nCo-Applicants Count: " . count($co_apps) . "\n\nProvide 3 bullet points for Bank Credit Manager:\n1. Eligibility & Risk Level (Low/Medium/High)\n2. Key Financial Strengths\n3. Credit Officer Recommendation.";

            $system = "You are a Senior DSA Credit Officer at BFS Circle. Provide sharp, professional financial assessment bullets.";

            $ai_res = call_ollama_ai($prompt, $system);
            if ($ai_res['success']) {
                return_json(['success' => true, 'cam_summary' => $ai_res['response']]);
            } else {
                return_json(['success' => false, 'error' => $ai_res['error']]);
            }
            break;

        case 'ai_chat_assistant':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return_json(['error' => 'Invalid Request Method'], 405);
            $query = trim($_POST['query'] ?? $_POST['message'] ?? '');
            if (empty($query)) return_json(['error' => 'Query is required'], 400);

            $q_lower = strtolower($query);

            // ===== ACTION INTENT DISPATCHER & EXECUTION ENGINE =====

            // 1. Attendance Punch In Intent
            if (strpos($q_lower, 'punch in') !== false || strpos($q_lower, 'punchin') !== false || (strpos($q_lower, 'attendance') !== false && strpos($q_lower, 'out') === false)) {
                $u = $_SESSION['username'] ?? 'admin';
                $today = date('Y-m-d');
                $now = date('Y-m-d H:i:s');
                $stmt = $db->prepare("SELECT id FROM staff_attendance WHERE username = ? AND att_date = ?");
                $stmt->execute([$u, $today]);
                if (!$stmt->fetch()) {
                    $db->prepare("INSERT INTO staff_attendance (username, att_date, punch_in, status) VALUES (?, ?, ?, 'Working')")->execute([$u, $today, $now]);
                } else {
                    $db->prepare("UPDATE staff_attendance SET punch_out = NULL, status = 'Working' WHERE username = ? AND att_date = ?")->execute([$u, $today]);
                }
                $db->prepare("UPDATE users SET current_status = 'Online' WHERE username = ?")->execute([$u]);
                log_activity("AI Attendance Punch In executed for $u", "dashboard.php");
                return_json([
                    'success' => true,
                    'action_performed' => 'punch_in',
                    'answer' => "⏱️ **Attendance Punched In Successfully!**\n\n• **User**: `{$u}`\n• **Punch In Time**: `" . date('h:i:s A') . "`\n• **Status**: `Working / Online`\n\nAapki aaj ki hazari/attendance system mein successfully register ho gayi hai.",
                    'timestamp' => date('h:i A')
                ]);
            }

            // 2. Attendance Punch Out Intent
            if (strpos($q_lower, 'punch out') !== false || strpos($q_lower, 'punchout') !== false) {
                $u = $_SESSION['username'] ?? 'admin';
                $today = date('Y-m-d');
                $now = date('Y-m-d H:i:s');
                $db->prepare("UPDATE staff_attendance SET punch_out = ?, status = 'Completed' WHERE username = ? AND att_date = ?")->execute([$now, $u, $today]);
                $db->prepare("UPDATE users SET current_status = 'Offline' WHERE username = ?")->execute([$u]);
                log_activity("AI Attendance Punch Out executed for $u", "dashboard.php");
                return_json([
                    'success' => true,
                    'action_performed' => 'punch_out',
                    'answer' => "🛑 **Attendance Punched Out Successfully!**\n\n• **User**: `{$u}`\n• **Punch Out Time**: `" . date('h:i:s A') . "`\n• **Status**: `Offline / Completed`\n\nAapka duty shift success with completed status close kar diya gaya hai.",
                    'timestamp' => date('h:i A')
                ]);
            }

            // 3. Set Reminder Intent
            if (strpos($q_lower, 'reminder') !== false || strpos($q_lower, 'remind') !== false || strpos($q_lower, 'yad dilao') !== false) {
                $remind_at = date('Y-m-d H:i:s', strtotime('+1 day 10:00:00'));
                if (strpos($q_lower, 'today') !== false || strpos($q_lower, 'aaj') !== false) {
                    $remind_at = date('Y-m-d 18:00:00');
                } elseif (preg_match('/(\d{1,2})\s*(baje|am|pm|hrs)/i', $query, $m)) {
                    $hr = intval($m[1]);
                    if (strpos($m[2], 'pm') !== false || (strpos($m[2], 'baje') !== false && $hr < 12)) $hr += 12;
                    $remind_at = date("Y-m-d $hr:00:00", strtotime('+1 day'));
                }
                $title = trim(preg_replace('/(reminder|set|karo|banao|kar|do|kal|baje|remind|me|aaj)/i', '', $query));
                if (empty($title) || strlen($title) < 3) $title = "Follow-up Task Reminder";
                $u = $_SESSION['username'] ?? 'admin';
                $stmt = $db->prepare("INSERT INTO reminders (lead_type, lead_id, assigned_to, remind_at, notes, title, priority, reference_type) VALUES ('General', 0, ?, ?, ?, ?, 'Medium', 'General')");
                $stmt->execute([$u, $remind_at, "AI Generated Reminder: $query", $title]);
                $rem_id = $db->lastInsertId();
                return_json([
                    'success' => true,
                    'action_performed' => 'set_reminder',
                    'answer' => "⏰ **Reminder Successfully Set!**\n\n• **Title**: `{$title}`\n• **Remind At**: `" . date('d-M-Y h:i A', strtotime($remind_at)) . "`\n• **Assigned To**: `{$u}`\n\nAapko exact time par Dashboard aur Bell Notification drawer par alert dikh jayega.",
                    'timestamp' => date('h:i A')
                ]);
            }

            // 4. Guided Form Assistant Mode Trigger
            if (strpos($q_lower, 'fill') !== false || strpos($q_lower, 'form') !== false || strpos($q_lower, 'puch') !== false || strpos($q_lower, 'step by step') !== false || strpos($q_lower, 'one by one') !== false) {
                $form_type = 'applicant';
                $title_label = "Loan Applicant Registration Form";
                if (strpos($q_lower, 'bank') !== false || strpos($q_lower, 'banker') !== false) {
                    $form_type = 'banker';
                    $title_label = "Bank Relationship Manager Form";
                } elseif (strpos($q_lower, 'ref') !== false || strpos($q_lower, 'dsa') !== false) {
                    $form_type = 'referral';
                    $title_label = "Referral Partner / DSA Form";
                } elseif (strpos($q_lower, 'emp') !== false || strpos($q_lower, 'staff') !== false) {
                    $form_type = 'employee';
                    $title_label = "New Employee Onboarding Form";
                }

                return_json([
                    'success' => true,
                    'is_wizard' => true,
                    'form_type' => $form_type,
                    'step' => 1,
                    'answer' => "📝 **Guided Form Assistant Activated ({$title_label})!**\n\nMain aapko step-by-step 1-by-1 saare questions puchunga aur form auto-fill karta jauga.\n\n**Question 1 of 5:** Customer/Person ka **Full Name** kya hai?",
                    'timestamp' => date('h:i A')
                ]);
            }

            // 5. Autonomous AI Follow-up & Lifecycle Execution Engine
            if (strpos($q_lower, 'followup') !== false || strpos($q_lower, 'follow up') !== false || strpos($q_lower, 'follow-up') !== false || strpos($q_lower, 'stalled') !== false) {
                // Check if specific applicant is mentioned in query
                $matched_app = null;
                $search_raw = preg_replace('/(followup|follow|up|take|aaj|ke|laga|lo|karo|banao|show|list|details)/i', ' ', $query);
                $tokens = array_filter(explode(' ', strtolower(trim($search_raw))), function($t) { return strlen($t) >= 3; });
                foreach ($tokens as $tk) {
                    $stmt = $db->prepare("SELECT * FROM applicants WHERE LOWER(customer_name) LIKE ? OR LOWER(loan_id) LIKE ? OR mobile LIKE ? LIMIT 1");
                    $stmt->execute(["%$tk%", "%$tk%", "%$tk%"]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($row) { $matched_app = $row; break; }
                }

                if ($matched_app) {
                    $aid = $matched_app['id'];
                    $docs = $db->query("SELECT document_type FROM applicant_documents WHERE applicant_id = $aid AND status = 'Pending'")->fetchAll(PDO::FETCH_COLUMN);
                    $queries = $db->query("SELECT bank_name, query_description FROM bank_queries WHERE applicant_id = $aid AND status != 'Resolved'")->fetchAll(PDO::FETCH_ASSOC);
                    
                    $pending_reasons = [];
                    if (!empty($docs)) $pending_reasons[] = "Missing Docs: " . implode(', ', array_slice($docs, 0, 3));
                    if (!empty($queries)) foreach ($queries as $q) $pending_reasons[] = "Bank Query ({$q['bank_name']}): {$q['query_description']}";
                    if (empty($pending_reasons)) $pending_reasons[] = "Phase Stage: {$matched_app['overall_status']} (Regular Progress Update)";

                    $reasons_text = implode(' | ', $pending_reasons);
                    $wa_msg = "Namaste {$matched_app['customer_name']} Ji! 🌿 BFS Circle se aapke {$matched_app['loan_type']} (Loan ID: {$matched_app['loan_id']}) ke regarding update hai. " . (!empty($docs) ? "Kripya bache hue documents (" . implode(', ', array_slice($docs, 0, 3)) . ") share karein taaki sanction proceed ho sake." : "Aapki loan file currently {$matched_app['overall_status']} phase mein active hai. Any help needed, please contact us.") . " Thank you!";
                    $next_date = date('Y-m-d 11:00:00', strtotime('+1 day'));

                    return_json([
                        'success' => true,
                        'action_performed' => 'ai_followup_card',
                        'followup_data' => [
                            'applicant_id' => $aid,
                            'customer_name' => $matched_app['customer_name'],
                            'loan_id' => $matched_app['loan_id'],
                            'mobile' => $matched_app['mobile'],
                            'loan_type' => $matched_app['loan_type'],
                            'overall_status' => $matched_app['overall_status'],
                            'pending_reasons' => $reasons_text,
                            'wa_message' => $wa_msg,
                            'next_followup' => $next_date
                        ],
                        'answer' => "📞 **AI Follow-up Executed for {$matched_app['customer_name']} (Loan ID: {$matched_app['loan_id']})**\n\n• **Current Status**: `{$matched_app['overall_status']}`\n• **Follow-up Focus**: `{$reasons_text}`\n• **Mobile**: `{$matched_app['mobile']}`\n\nAap niche diye gaye 1-Click buttons se WhatsApp message bhej sakte hain aur auto-reminder schedule kar sakte hain.",
                        'timestamp' => date('h:i A')
                    ]);
                } else {
                    // General Roster of Pending Follow-ups Today
                    $pending_apps = $db->query("SELECT id, loan_id, customer_name, mobile, loan_type, overall_status, rm_next_followup_date, COALESCE(last_activity_date, created_at) as last_act FROM applicants WHERE overall_status NOT IN ('Completed','Disbursed','Rejected') ORDER BY id DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);
                    
                    $table_rows = "| Loan ID | Customer | Loan Type | Phase Status | AI Follow-up Reason | Action |\n| :--- | :--- | :--- | :--- | :--- | :--- |\n";
                    foreach ($pending_apps as $pa) {
                        $p_docs = $db->query("SELECT COUNT(*) FROM applicant_documents WHERE applicant_id = {$pa['id']} AND status = 'Pending'")->fetchColumn();
                        $reason = $p_docs > 0 ? "{$p_docs} Pending Docs" : "Regular Update";
                        $enc_name = urlencode($pa['customer_name']);
                        $table_rows .= "| `{$pa['loan_id']}` | **{$pa['customer_name']}** | {$pa['loan_type']} | `{$pa['overall_status']}` | {$reason} | [Take Followup](javascript:triggerAiQuickAction('Followup%20{$pa['loan_id']}')) |\n";
                    }

                    return_json([
                        'success' => true,
                        'answer' => "📞 **Today's Active Loan Follow-ups Roster:**\n\n{$table_rows}\n\nAap kisi bhi record par **Take Followup** click karein, AI automatic WhatsApp message aur reminder schedule kar dega!",
                        'timestamp' => date('h:i A')
                    ]);
                }
            }

            // 6. Staff & Employee Count Intent Interceptor
            if ((strpos($q_lower, 'staff') !== false || strpos($q_lower, 'employee') !== false) && (strpos($q_lower, 'kitn') !== false || strpos($q_lower, 'count') !== false || strpos($q_lower, 'total') !== false || strpos($q_lower, 'list') !== false || strpos($q_lower, 'batao') !== false || strpos($q_lower, 'hai') !== false || strpos($q_lower, 'hain') !== false)) {
                $staff_members = $db->query("SELECT e.full_name, COALESCE(e.designation, u.role) as designation, u.role, u.username FROM employees e JOIN users u ON e.user_id = u.id WHERE u.role IN ('Staff', 'RM', 'Admin') AND u.is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
                $total_staff_cnt = count($staff_members);
                
                $list_text = "";
                foreach ($staff_members as $sm) {
                    $desig = !empty($sm['designation']) ? $sm['designation'] : $sm['role'];
                    $list_text .= "\n• **{$sm['full_name']}** - `{$desig}` ({$sm['role']})";
                }

                return_json([
                    'success' => true,
                    'action_performed' => 'staff_count',
                    'answer' => "👥 **Staff & HRMS Directory Summary:**\n\nHumare system mein kul **{$total_staff_cnt} active staff / employees** registered hain:\n{$list_text}\n\nAap `Staff & HRMS Directory` page par inki details view kar sakte hain.",
                    'timestamp' => date('h:i A')
                ]);
            }

            // Aggregate Live System Knowledge Base
            $kb = [];
            try {
                $kb['pre_leads'] = "Total Raw Pre-Leads: " . $db->query("SELECT COUNT(*) FROM pre_leads")->fetchColumn() . ", Unassigned Raw Pool: " . $db->query("SELECT COUNT(*) FROM pre_leads WHERE assigned_to IS NULL OR assigned_to = ''")->fetchColumn();
                $kb['leads'] = "Total Active Leads: " . $db->query("SELECT COUNT(*) FROM leads")->fetchColumn();
                $kb['field_visits'] = "Total Field Visits: " . $db->query("SELECT COUNT(*) FROM field_visits")->fetchColumn();
                
                $disbursed = $db->query("SELECT COUNT(*), COALESCE(SUM(sanctioned_amount),0) FROM applicants WHERE overall_status IN ('Completed','Disbursed')")->fetch(PDO::FETCH_NUM);
                $stalled = $db->query("SELECT COUNT(*) FROM applicants WHERE overall_status NOT IN ('Disbursed','Rejected','Completed') AND julianday('now') - julianday(COALESCE(last_activity_date, created_at)) >= 3")->fetchColumn();
                $kb['loan_applications'] = "Total Applicant Files: " . $db->query("SELECT COUNT(*) FROM applicants")->fetchColumn() . ", Completed/Disbursed Files: " . ($disbursed[0] ?? 0) . " (Total Disbursed Amount: ₹" . number_format($disbursed[1] ?? 0) . "), Stalled Files (3+ Days): $stalled";

                $applicant_list = $db->query("SELECT customer_name, loan_id, loan_type, loan_amount_requested, overall_status, assigned_to FROM applicants ORDER BY id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
                $kb['applicant_names_list'] = "Live Loan Applicant Records (Name, Loan ID, Type, Amount, Status, RM): " . json_encode($applicant_list);

                $staff_rows = $db->query("SELECT e.full_name, e.designation, u.role, u.username FROM employees e JOIN users u ON e.user_id = u.id WHERE u.role IN ('Staff', 'RM', 'Admin') AND u.is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
                $kb['hrms_attendance'] = "Total Active Staff/Employees Count: " . count($staff_rows) . ", Present Today: " . $db->query("SELECT COUNT(DISTINCT user_id) FROM attendance WHERE date(punch_in) = date('now')")->fetchColumn() . ". Exact Staff Members Roster: " . json_encode($staff_rows);
                
                $top_rms = $db->query("SELECT assigned_to, COUNT(*) as files, COALESCE(SUM(sanctioned_amount),0) as total_val FROM applicants WHERE assigned_to IS NOT NULL AND assigned_to != '' GROUP BY assigned_to ORDER BY total_val DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
                $kb['rm_leaderboard'] = "Top Performing RMs by Business Volume: " . json_encode($top_rms);

                $rec = (float)($db->query("SELECT COALESCE(SUM(amount_received),0) FROM fund_receipts")->fetchColumn() ?: 0);
                $disb = (float)($db->query("SELECT COALESCE(SUM(payment_amount),0) FROM fund_disbursements")->fetchColumn() ?: 0);
                $util = (float)($db->query("SELECT COALESCE(SUM(expense_amount),0) FROM fund_utilizations")->fetchColumn() ?: 0);
                $avail = $rec - $disb;
                $kb['fund_flow_system'] = "Total Fund Received: ₹" . number_format($rec) . ", Total Paid/Disbursed: ₹" . number_format($disb) . ", Total Utilized: ₹" . number_format($util) . ", Net Available Balance: ₹" . number_format($avail);

                $inv = $db->query("SELECT COUNT(*), COALESCE(SUM(net_invoice_amount),0), COALESCE(SUM(received_amount),0) FROM bank_payout_invoices")->fetch(PDO::FETCH_NUM);
                $kb['bank_payout_invoices'] = "Total Invoices: " . ($inv[0] ?? 0) . ", Total Net Billed: ₹" . number_format($inv[1] ?? 0) . ", Total Payout Received: ₹" . number_format($inv[2] ?? 0);

                $ref_all = $db->query("SELECT referral_id, full_name, referrer_type, mobile, status FROM referrals ORDER BY id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
                $kb['referrals_dsa_directory'] = "Total Registered Referral Partners/DSAs: " . count($ref_all) . ", Registered Partners List: " . json_encode($ref_all);

                $kb['today_followups'] = "Pending RM Follow-ups Today: " . $db->query("SELECT COUNT(*) FROM applicants WHERE date(rm_next_followup_date) = date('now')")->fetchColumn();
            } catch (Exception $e) {
                $kb['error'] = $e->getMessage();
            }

            // Dynamic 360-Degree Entity Search Engine
            $entity_dossiers = "";
            try {
                $search_raw = preg_replace('/[^a-zA-Z0-9\s\-]/', ' ', $query);
                $tokens = array_filter(explode(' ', strtolower($search_raw)), function($t) {
                    $stop = ['batao','bataiye','kya','kaun','kiske','bare','main','mujhe','humare','kaho','show','list','details','detail','info','profile','ke','ka','ki','hai','hain','me','kiya','kar','do','pass','tell','about','the','is','for','file','application','applicant','ko','se'];
                    return strlen($t) >= 2 && !in_array($t, $stop);
                });

                if (!empty($tokens)) {
                    // 1. Search Applicants (Name, Loan ID, Mobile, PAN)
                    $matched_apps = [];
                    foreach ($tokens as $tk) {
                        $stmt = $db->prepare("SELECT * FROM applicants WHERE LOWER(customer_name) LIKE ? OR LOWER(loan_id) LIKE ? OR mobile LIKE ? OR LOWER(pan_number) LIKE ? LIMIT 5");
                        $stmt->execute(["%$tk%", "%$tk%", "%$tk%", "%$tk%"]);
                        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($rows as $r) { $matched_apps[$r['id']] = $r; }
                    }

                    if (!empty($matched_apps)) {
                        $entity_dossiers .= "\n🔍 [MATCHED APPLICANT 360-DEGREE DOSSIER(S)]:\n";
                        foreach ($matched_apps as $app) {
                            $aid = $app['id'];
                            $co_apps = $db->query("SELECT full_name, relation, monthly_income, is_financial FROM co_applicants WHERE applicant_id = $aid")->fetchAll(PDO::FETCH_ASSOC);
                            $logins = $db->query("SELECT bank_name, login_date, status, sanctioned_amount, sanctioned_roi FROM applicant_bank_logins WHERE applicant_id = $aid")->fetchAll(PDO::FETCH_ASSOC);
                            $queries = $db->query("SELECT bank_name, query_type, query_description, status FROM bank_queries WHERE applicant_id = $aid")->fetchAll(PDO::FETCH_ASSOC);
                            $docs = $db->query("SELECT document_type, status FROM applicant_documents WHERE applicant_id = $aid AND status = 'Pending'")->fetchAll(PDO::FETCH_ASSOC);

                            $entity_dossiers .= "• APPLICANT: {$app['customer_name']} | Loan ID: {$app['loan_id']}\n";
                            $entity_dossiers .= "  - Contact & KYC: Mobile: {$app['mobile']} | Email: " . ($app['email'] ?: 'N/A') . " | PAN: " . ($app['pan_number'] ?: 'N/A') . " | Aadhaar: " . ($app['aadhar_number'] ?: 'N/A') . "\n";
                            $entity_dossiers .= "  - Loan Product: {$app['loan_type']} | Requested Amount: ₹" . number_format($app['loan_amount_requested'] ?: 0) . " | Sanctioned Amount: ₹" . number_format($app['sanctioned_amount'] ?: 0) . "\n";
                            $entity_dossiers .= "  - Financial Evaluation: Monthly Income = ₹" . number_format($app['monthly_income'] ?: 0) . ", Existing EMIs = ₹" . number_format($app['emi'] ?: 0) . ", CIBIL Score = " . ($app['cibil_score'] ?: 'N/A') . ", Employment = " . ($app['employment_type'] ?: 'N/A') . "\n";
                            $entity_dossiers .= "  - Pipeline State: Overall Status = {$app['overall_status']} | Current Phase = " . ($app['current_phase'] ?: 'Phase 1') . " | Assigned RM = " . ($app['assigned_to'] ?: 'Unassigned') . "\n";
                            $entity_dossiers .= "  - Referral Partner / Lead Source: " . ($app['lead_source'] ?: ($app['referral_id'] ? "Referral ID: {$app['referral_id']}" : "Direct Sourcing (No External Referral Partner)")) . "\n";
                            if (!empty($co_apps)) $entity_dossiers .= "  - Co-Applicants List: " . json_encode($co_apps) . "\n";
                            if (!empty($logins)) $entity_dossiers .= "  - Bank Submissions / Logins: " . json_encode($logins) . "\n";
                            if (!empty($queries)) $entity_dossiers .= "  - Active Bank Queries: " . json_encode($queries) . "\n";
                            if (!empty($docs)) $entity_dossiers .= "  - Pending Documents Required: " . json_encode($docs) . "\n";
                            $entity_dossiers .= "\n";
                        }
                    }

                    // 2. Search Staff / RMs (Name, Username, Mobile)
                    $matched_staff = [];
                    foreach ($tokens as $tk) {
                        $stmt = $db->prepare("SELECT id, name, username, role, mobile, email, is_active FROM users WHERE LOWER(name) LIKE ? OR LOWER(username) LIKE ? OR mobile LIKE ? LIMIT 5");
                        $stmt->execute(["%$tk%", "%$tk%", "%$tk%"]);
                        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($rows as $r) { $matched_staff[$r['id']] = $r; }
                    }

                    if (!empty($matched_staff)) {
                        $entity_dossiers .= "\n👤 [MATCHED STAFF / RM DOSSIER(S)]:\n";
                        foreach ($matched_staff as $st) {
                            $uname = $st['username'];
                            $sname = $st['name'];
                            $assigned_files = $db->query("SELECT loan_id, customer_name, overall_status, loan_amount_requested FROM applicants WHERE assigned_to = '$uname' OR assigned_to = '$sname'")->fetchAll(PDO::FETCH_ASSOC);
                            $disb_vol = $db->query("SELECT COALESCE(SUM(sanctioned_amount),0) FROM applicants WHERE (assigned_to = '$uname' OR assigned_to = '$sname') AND overall_status IN ('Completed','Disbursed')")->fetchColumn();
                            $att_today = $db->query("SELECT punch_in FROM attendance WHERE (user_id = {$st['id']} OR username = '$uname') AND date(punch_in) = date('now')")->fetchColumn();

                            $entity_dossiers .= "• STAFF MEMBER: {$st['name']} (Username: {$st['username']})\n";
                            $entity_dossiers .= "  - Designation/Role: {$st['role']} | Mobile: " . ($st['mobile'] ?: 'N/A') . " | Email: " . ($st['email'] ?: 'N/A') . " | Status: " . ($st['is_active'] ? 'Active' : 'Inactive') . "\n";
                            $entity_dossiers .= "  - Attendance Today: " . ($att_today ? "Punched in at " . date('h:i A', strtotime($att_today)) : "Not Punched In Yet") . "\n";
                            $entity_dossiers .= "  - Total Assigned Applications: " . count($assigned_files) . " files | Total Disbursed Volume: ₹" . number_format($disb_vol) . "\n";
                            if (!empty($assigned_files)) $entity_dossiers .= "  - Assigned Applications List: " . json_encode(array_slice($assigned_files, 0, 8)) . "\n";
                            $entity_dossiers .= "\n";
                        }
                    }

                    // 3. Search Bankers Directory
                    $matched_bankers = [];
                    try {
                        foreach ($tokens as $tk) {
                            $stmt = $db->prepare("SELECT * FROM bankers_directory WHERE LOWER(banker_name) LIKE ? OR LOWER(bank_name) LIKE ? OR mobile LIKE ? LIMIT 5");
                            $stmt->execute(["%$tk%", "%$tk%", "%$tk%"]);
                            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($rows as $r) { $matched_bankers[$r['id']] = $r; }
                        }
                    } catch(Exception $e) {}

                    if (!empty($matched_bankers)) {
                        $entity_dossiers .= "\n🏛️ [MATCHED BANKER DOSSIER(S)]:\n";
                        foreach ($matched_bankers as $bk) {
                            $entity_dossiers .= "• BANKER: {$bk['banker_name']} | Bank: {$bk['bank_name']}\n";
                            $entity_dossiers .= "  - Designation: " . ($bk['designation'] ?? 'Bank Executive') . " | Branch: " . ($bk['branch'] ?? 'N/A') . " | Mobile: " . ($bk['mobile'] ?? 'N/A') . " | Email: " . ($bk['email'] ?? 'N/A') . "\n\n";
                        }
                    }

                    // 4. Search Pre-Leads & Field Visit Referrals
                    $matched_leads = [];
                    try {
                        foreach ($tokens as $tk) {
                            $stmt = $db->prepare("SELECT * FROM pre_leads WHERE LOWER(name) LIKE ? OR phone_number LIKE ? LIMIT 5");
                            $stmt->execute(["%$tk%", "%$tk%"]);
                            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($rows as $r) { $matched_leads[$r['id']] = $r; }
                        }
                    } catch(Exception $e) {}

                    if (!empty($matched_leads)) {
                        $entity_dossiers .= "\n📞 [MATCHED PRE-LEADS DOSSIER(S)]:\n";
                        foreach ($matched_leads as $ld) {
                            $entity_dossiers .= "• PRE-LEAD: {$ld['name']} | Mobile: {$ld['phone_number']}\n";
                            $entity_dossiers .= "  - Product Interest: " . ($ld['loan_type'] ?? 'Loan') . " | Status: " . ($ld['status'] ?? 'New') . " | Source: " . ($ld['source'] ?? 'Referral') . "\n\n";
                        }
                    }

                    // 5. Search Registered Referrals / DSA Directory Table
                    $matched_referrals = [];
                    try {
                        $q_lower = strtolower($query);
                        $is_gen_ref = (strpos($q_lower, 'ref') !== false || strpos($q_lower, 'dsa') !== false || strpos($q_lower, 'partner') !== false || strpos($q_lower, 'agent') !== false);
                        if ($is_gen_ref) {
                            $rows = $db->query("SELECT referral_id, full_name, referrer_type, mobile, commission_rate, status FROM referrals ORDER BY id DESC LIMIT 15")->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($rows as $r) { $matched_referrals[$r['referral_id']] = $r; }
                        } else {
                            foreach ($tokens as $tk) {
                                $stmt = $db->prepare("SELECT referral_id, full_name, referrer_type, mobile, commission_rate, status FROM referrals WHERE LOWER(full_name) LIKE ? OR LOWER(referral_id) LIKE ? OR mobile LIKE ? LIMIT 5");
                                $stmt->execute(["%$tk%", "%$tk%", "%$tk%"]);
                                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($rows as $r) { $matched_referrals[$r['referral_id']] = $r; }
                            }
                        }
                    } catch(Exception $e) {}

                    if (!empty($matched_referrals)) {
                        $entity_dossiers .= "\n🤝 [MATCHED REGISTERED REFERRAL PARTNERS & DSA DIRECTORY]:\n";
                        foreach ($matched_referrals as $rf) {
                            $entity_dossiers .= "• PARTNER: Full Name = {$rf['full_name']} | Referral ID = {$rf['referral_id']} | Type/Category = {$rf['referrer_type']} | Mobile = " . ($rf['mobile'] ?: 'N/A') . " | Status = {$rf['status']}\n";
                        }
                        $entity_dossiers .= "\n";
                    }
                    // 6. Search Financial Projects & Schemes Table
                    try {
                        $q_lower = strtolower($query);
                        $is_gen_proj = (strpos($q_lower, 'project') !== false || strpos($q_lower, 'scheme') !== false);
                        $matched_projs = [];
                        if ($is_gen_proj) {
                            $rows = $db->query("SELECT project_id, project_name, scheme, budget, status FROM projects ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($rows as $r) { $matched_projs[$r['project_id']] = $r; }
                        } else {
                            foreach ($tokens as $tk) {
                                $stmt = $db->prepare("SELECT project_id, project_name, scheme, budget, status FROM projects WHERE LOWER(project_name) LIKE ? OR LOWER(project_id) LIKE ? LIMIT 5");
                                $stmt->execute(["%$tk%", "%$tk%"]);
                                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($rows as $r) { $matched_projs[$r['project_id']] = $r; }
                            }
                        }
                        if (!empty($matched_projs)) {
                            $entity_dossiers .= "\n📁 [MATCHED FINANCIAL PROJECTS & SCHEMES]:\n";
                            foreach ($matched_projs as $pj) {
                                $entity_dossiers .= "• PROJECT: {$pj['project_name']} (ID: {$pj['project_id']}) | Scheme: " . ($pj['scheme'] ?: 'General') . " | Budget: ₹" . number_format($pj['budget'] ?: 0) . " | Status: {$pj['status']}\n\n";
                            }
                        }
                    } catch(Exception $e) {}

                    // 7. Search Receivers / Vendors Table
                    try {
                        $q_lower = strtolower($query);
                        $is_gen_rcv = (strpos($q_lower, 'vendor') !== false || strpos($q_lower, 'receiver') !== false || strpos($q_lower, 'contractor') !== false);
                        $matched_rcvs = [];
                        if ($is_gen_rcv) {
                            $rows = $db->query("SELECT receiver_id, name, receiver_type, mobile, status FROM receivers ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($rows as $r) { $matched_rcvs[$r['receiver_id']] = $r; }
                        } else {
                            foreach ($tokens as $tk) {
                                $stmt = $db->prepare("SELECT receiver_id, name, receiver_type, mobile, status FROM receivers WHERE LOWER(name) LIKE ? OR LOWER(receiver_id) LIKE ? LIMIT 5");
                                $stmt->execute(["%$tk%", "%$tk%"]);
                                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($rows as $r) { $matched_rcvs[$r['receiver_id']] = $r; }
                            }
                        }
                        if (!empty($matched_rcvs)) {
                            $entity_dossiers .= "\n👤 [MATCHED VENDORS & RECEIVERS DIRECTORY]:\n";
                            foreach ($matched_rcvs as $rc) {
                                $entity_dossiers .= "• RECEIVER/VENDOR: {$rc['name']} (ID: {$rc['receiver_id']}) | Type: {$rc['receiver_type']} | Status: {$rc['status']} | Mobile: " . ($rc['mobile'] ?: 'N/A') . "\n\n";
                            }
                        }
                    } catch(Exception $e) {}

                    // 8. Search Bank Commission Slabs
                    try {
                        $q_lower = strtolower($query);
                        if (strpos($q_lower, 'slab') !== false || strpos($q_lower, 'commission') !== false || strpos($q_lower, 'payout') !== false || strpos($q_lower, 'rate') !== false) {
                            $slabs = $db->query("SELECT bank_name, loan_type, min_volume, max_volume, commission_pct FROM bank_commission_slabs ORDER BY bank_name ASC LIMIT 15")->fetchAll(PDO::FETCH_ASSOC);
                            if (!empty($slabs)) {
                                $entity_dossiers .= "\n📊 [BANK PAYOUT & COMMISSION SLABS]: " . json_encode($slabs) . "\n\n";
                            }
                        }
                    } catch(Exception $e) {}

                }
            } catch (Exception $e) {
                $entity_dossiers = "\nEntity Search Note: " . $e->getMessage() . "\n";
            }

            $context_text = "LIVE CRM KNOWLEDGE BASE METRICS SNAPSHOT (" . date('d-M-Y H:i A') . "):\n";
            foreach ($kb as $module => $val) {
                $context_text .= "• [" . strtoupper($module) . "]: " . (is_array($val) ? json_encode($val) : $val) . "\n";
            }
            if (!empty($entity_dossiers)) {
                $context_text .= "\n" . $entity_dossiers;
            }

            $prompt = "REAL-TIME CRM KNOWLEDGE BASE & ENTITY CONTEXT:\n$context_text\n\nUSER QUESTION: \"$query\"\n\nCRITICAL DIRECTIVE:\n1. Answer the question DIRECTLY and CONCISELY in 1-3 sentences. NEVER repeat or restate the user's question (e.g. NEVER write 'User ka question hai...').\n2. If asked who is the referral for a specific applicant (e.g. 'Rajesh Gupta ka referral kon hai'), check that applicant's dossier. If 'Direct Sourcing', state directly: 'Rajesh Gupta ka koi external referral partner nahi hai (Direct Sourcing).'\n3. If asked for general referral list ('refrell kon kon h'), list the registered referral partners in a clean Markdown table.\n4. Respond politely in natural Hinglish or English.";

            $system = "You are 'BFS Circle AI Command Assistant', an enterprise AI assistant for BFS Financial Services powered by Ollama llama3.2:3b. Be direct, precise, concise, and helpful. Do not write filler words or repeat user prompts.";

            $ai_res = call_ollama_ai($prompt, $system);
            if ($ai_res['success']) {
                return_json(['success' => true, 'answer' => $ai_res['response'], 'timestamp' => date('h:i A')]);
            } else {
                // Smart Fallback Engine when Ollama AI service is not running locally
                $fallback_answer = "🤖 **BFS AI Agent (Live Database Knowledge Engine):**\n\n";
                if (!empty($entity_dossiers)) {
                    $fallback_answer .= "🔍 **System Search Results:**\n" . trim($entity_dossiers);
                } else {
                    $fallback_answer .= "👋 Namaste! Main aapka **BFS Enterprise AI Assistant** hoon.\n\n";
                    $fallback_answer .= "📊 **Live System Overview:**\n";
                    $fallback_answer .= "• **Raw Pre-Leads**: " . ($kb['pre_leads'] ?? 'N/A') . "\n";
                    $fallback_answer .= "• **Active Leads Pool**: " . ($kb['leads'] ?? 'N/A') . "\n";
                    $fallback_answer .= "• **Applicant Files**: " . ($kb['loan_applications'] ?? 'N/A') . "\n";
                    $fallback_answer .= "• **Active Staff/Employees**: " . ($kb['hrms_attendance'] ?? 'N/A') . "\n\n";
                    $fallback_answer .= "Aap niche diye gaye Quick Action buttons use kar sakte hain: **Punch In**, **Punch Out**, **Set Reminder**, **Form Fill**, **Followup**, ya kisi Applicant/Staff ka namesearch kar sakte hain!";
                }
                return_json(['success' => true, 'answer' => $fallback_answer, 'timestamp' => date('h:i A')]);
            }
            break;

        case 'generate_ai_email':
            $applicant_id = intval($_POST['applicant_id'] ?? $_GET['applicant_id'] ?? 0);
            $stage_type = trim($_POST['stage_type'] ?? $_GET['stage_type'] ?? 'general');
            $custom_instruction = trim($_POST['custom_instruction'] ?? $_GET['custom_instruction'] ?? '');

            $app = null;
            if ($applicant_id) {
                $stmt = $db->prepare("SELECT * FROM applicants WHERE id = ?");
                $stmt->execute([$applicant_id]);
                $app = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            $customer_name = $app['customer_name'] ?? 'Valued Client';
            $loan_type = $app['loan_type'] ?? 'Loan Application';
            $loan_id = $app['loan_id'] ?? 'L-REF';
            $loan_amount = isset($app['loan_amount_requested']) ? '₹' . number_format($app['loan_amount_requested']) : '';
            $bank_name = $app['sanction_bank'] ?? 'Bank Partner';

            $prompt = "Write an executive, highly professional email to customer '$customer_name' for stage '$stage_type'. Loan Type: $loan_type, Loan ID: $loan_id, Amount: $loan_amount, Bank: $bank_name. Instructions: $custom_instruction. Include a Subject line starting with 'Subject: ' followed by the email body. Make it polite, clear, and action-oriented.";
            $system = "You are an AI Email Assistant for BFS Financial Services. Draft formal, concise emails for loan applicants.";

            $ai_res = call_ollama_ai($prompt, $system);

            if ($ai_res['success'] && !empty($ai_res['response'])) {
                $content = trim($ai_res['response']);
                $subject = "Update regarding your $loan_type ($loan_id) - BFS Circle";
                if (preg_match('/Subject:\s*(.+)/i', $content, $m)) {
                    $subject = trim($m[1]);
                    $content = trim(preg_replace('/Subject:\s*.+\n*/i', '', $content));
                }
                return_json(['success' => true, 'subject' => $subject, 'body' => $content]);
            } else {
                $fallback = get_smart_email_template($stage_type, $customer_name, $loan_type, $loan_id, $bank_name, $loan_amount);
                return_json($fallback);
            }
            break;

        case 'send_whatsapp_api':
            $applicant_id = intval($_POST['applicant_id'] ?? $_GET['applicant_id'] ?? 0);
            $message = trim($_POST['message'] ?? $_GET['message'] ?? '');
            $reminder_type = trim($_POST['reminder_type'] ?? $_GET['reminder_type'] ?? 'general');
            
            $mobile = '';
            $customer_name = 'Applicant';

            if ($applicant_id) {
                $stmt = $db->prepare("SELECT customer_name, mobile, loan_id FROM applicants WHERE id = ?");
                $stmt->execute([$applicant_id]);
                $app = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($app) {
                    $customer_name = $app['customer_name'];
                    $mobile = preg_replace('/[^0-9]/', '', $app['mobile'] ?? '');
                }
            } else {
                $mobile = preg_replace('/[^0-9]/', '', $_POST['mobile'] ?? $_GET['mobile'] ?? '');
            }

            if (!$mobile) {
                return_json(['error' => 'Mobile number is required for WhatsApp dispatch.'], 400);
            }

            // Log activity / interaction
            try {
                $username = $_SESSION['username'] ?? 'System_AI';
                $db->prepare("INSERT INTO activities (username, description, action_link, created_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)")
                   ->execute([$username, "Dispatched WhatsApp missing docs reminder to $customer_name ($mobile)", "applicant_documents.php?id=$applicant_id"]);
            } catch (Exception $e) {}

            $wa_url = "https://wa.me/91{$mobile}?text=" . urlencode($message);

            return_json([
                'success' => true,
                'message' => 'WhatsApp missing docs follow-up logged & queued.',
                'whatsapp_url' => $wa_url,
                'mobile' => $mobile,
                'customer_name' => $customer_name,
                'status' => 'queued_for_dispatch'
            ]);
            break;

        case 'extract_document_details':
            $extracted = [];
            $raw_text = '';

            // 1. Check if a file was uploaded or base64 data sent
            if (!empty($_FILES['document_file']['tmp_name']) && is_uploaded_file($_FILES['document_file']['tmp_name'])) {
                $file_tmp = $_FILES['document_file']['tmp_name'];
                $file_name = $_FILES['document_file']['name'];
                $mime_type = $_FILES['document_file']['type'] ?? '';

                // Read file binary content safely
                $content = @file_get_contents($file_tmp);
                if ($content !== false) {
                    // Extract printable strings & text
                    $raw_text = preg_replace('/[^\x20-\x7E\x0A\x0D]/', ' ', $content);
                    // Also include raw original filename which might contain hints (e.g. PAN_ABCDE1234F.jpg)
                    $raw_text .= ' ' . $file_name;
                }
            } elseif (!empty($_POST['document_text'])) {
                $raw_text = trim($_POST['document_text']);
            }

            if (empty($raw_text)) {
                return_json(['error' => 'No readable document file or text provided.'], 400);
            }

            // --- REGEX & PATTERN EXTRACTION ENGINE ---
            
            // 1. PAN Card Pattern (5 uppercase letters, 4 digits, 1 uppercase letter)
            if (preg_match('/[A-Z]{5}[0-9]{4}[A-Z]/', strtoupper($raw_text), $m)) {
                $extracted['pan_number'] = $m[0];
            }

            // 2. Aadhaar Card Pattern (12 digits, optional spaces)
            if (preg_match('/\b([2-9]\d{3}\s?\d{4}\s?\d{4})\b/', $raw_text, $m)) {
                $extracted['aadhar_number'] = str_replace(' ', '', $m[1]);
            }

            // 3. Pincode Pattern (6 digits starting with 1-9)
            if (preg_match('/\b([1-9][0-9]{5})\b/', $raw_text, $m)) {
                $extracted['pincode'] = $m[1];
            }

            // 4. DOB Pattern (DD/MM/YYYY or YYYY-MM-DD)
            if (preg_match('/\b(0[1-9]|[12][0-9]|3[01])[\/\.-](0[1-9]|1[012])[\/\.-](19|20)\d\d\b/', $raw_text, $m)) {
                $parts = preg_split('/[\/\.-]/', $m[0]);
                if (count($parts) === 3) {
                    $extracted['dob'] = sprintf('%04d-%02d-%02d', (int)$parts[2], (int)$parts[1], (int)$parts[0]);
                }
            }

            // 5. Mobile Number Pattern (10 digits starting with 6-9)
            if (preg_match('/\b([6-9][0-9]{9})\b/', $raw_text, $m)) {
                $extracted['mobile'] = $m[1];
            }

            // 6. Email Pattern
            if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $raw_text, $m)) {
                $extracted['email'] = strtolower($m[0]);
            }

            // 7. Monthly Income / Salary Pattern
            if (preg_match('/(?:salary|net pay|gross pay|income|amount|rs\.?|inr)\s*:?\s*₹?\s*([0-9,]{4,10})/i', $raw_text, $m)) {
                $amt = (int)str_replace(',', '', $m[1]);
                if ($amt >= 5000 && $amt <= 5000000) {
                    $extracted['monthly_income'] = $amt;
                }
            }

            // 8. OLLAMA DEEP CONTEXTUAL AI EXTRACTOR (If Ollama server is active)
            if (function_exists('call_ollama_ai')) {
                $prompt = "Extract details from this document text:\n\"" . substr($raw_text, 0, 2000) . "\"\n\nReturn JSON ONLY with keys: customer_name, pan_number, aadhar_number, dob, pincode, address, monthly_income, employment_type. Do not include markdown code block syntax.";
                $system = "You are an Indian Financial KYC OCR Extractor. Return strictly a JSON object with extracted values or empty strings.";
                
                $ai_res = call_ollama_ai($prompt, $system);
                if (!empty($ai_res['success']) && !empty($ai_res['response'])) {
                    $clean_json = preg_replace('/```json|```/i', '', trim($ai_res['response']));
                    $ai_data = json_decode(trim($clean_json), true);
                    if (is_array($ai_data)) {
                        foreach ($ai_data as $key => $val) {
                            $val = trim((string)$val);
                            if (!empty($val) && empty($extracted[$key]) && strtolower($val) !== 'null' && strtolower($val) !== 'n/a') {
                                $extracted[$key] = $val;
                            }
                        }
                    }
                }
            }

            // 9. State & City lookup from Pincode if pincode was found
            if (!empty($extracted['pincode'])) {
                $pin_stmt = $db->prepare("SELECT state, city FROM pincodes WHERE pincode = ? LIMIT 1");
                $pin_stmt->execute([$extracted['pincode']]);
                $pin_info = $pin_stmt->fetch(PDO::FETCH_ASSOC);
                if ($pin_info) {
                    $extracted['state'] = $pin_info['state'];
                    $extracted['city'] = $pin_info['city'];
                }
            }

            return_json([
                'success' => true,
                'extracted_data' => $extracted,
                'extracted_fields' => array_keys($extracted)
            ]);
            break;

        default:
            return_json(['error' => 'API command not recognized.'], 404);
    }
} catch (Exception $e) {
    return_json(['error' => 'Server Error: ' . $e->getMessage()], 500);
}

function get_smart_email_template($stage, $name, $loan_type, $loan_id, $bank, $amount) {
    switch($stage) {
        case 'Registration':
            return [
                'success' => true,
                'subject' => "Welcome to BFS Circle! Application Received: $loan_type ($loan_id)",
                'body' => "Dear $name,\n\nGreetings from BFS Financial Services!\n\nWe are pleased to confirm that your application for a $loan_type ($loan_id) for $amount has been successfully registered with BFS Circle.\n\nOur dedicated Relationship Manager will guide you through the document verification stage. You can track your real-time application progress on our portal.\n\nThank you for choosing BFS Circle.\n\nWarm regards,\nBFS Financial Services Team"
            ];
        case 'Pendency':
            return [
                'success' => true,
                'subject' => "Action Required: Pending Documents for $loan_type ($loan_id)",
                'body' => "Dear $name,\n\nThank you for choosing BFS Circle for your $loan_type ($loan_id).\n\nTo proceed with your bank sanction submission, we kindly request you to upload the remaining mandatory documents at your earliest convenience.\n\nPrompt submission will help us expedite bank credit review and sanction approval.\n\nWarm regards,\nCredit Operations Team\nBFS Financial Services"
            ];
        case 'Login_Fee':
            return [
                'success' => true,
                'subject' => "Receipt Confirmation: Bank Login Fee for $loan_id",
                'body' => "Dear $name,\n\nWe have received your Bank Login / Processing Fee payment for $loan_type ($loan_id).\n\nYour file has been officially logged with $bank for credit appraisal. We will keep you updated on progress.\n\nWarm regards,\nBFS Financial Services"
            ];
        case 'Verification':
            return [
                'success' => true,
                'subject' => "File Verification Complete - Submitted to $bank ($loan_id)",
                'body' => "Dear $name,\n\nGreat news! Your document dossier for $loan_type ($loan_id) is 100% verified and has been forwarded to $bank for formal credit assessment and sanction issuance.\n\nWe expect feedback within 48-72 business hours.\n\nWarm regards,\nBFS Financial Services"
            ];
        case 'Sanction':
            return [
                'success' => true,
                'subject' => "Congratulations! Loan Sanctioned from $bank ($loan_id)",
                'body' => "Dear $name,\n\nWe are delighted to inform you that your $loan_type application ($loan_id) has been SANCTIONED by $bank!\n\nPlease review your formal Sanction Letter attached. Our team will contact you shortly to complete disbursal documentation.\n\nCongratulations from all of us at BFS Circle!\n\nWarm regards,\nBFS Financial Services"
            ];
        case 'Disbursed':
            return [
                'success' => true,
                'subject' => "Loan Disbursed Successfully - $loan_id",
                'body' => "Dear $name,\n\nWe are happy to announce that your $loan_type ($loan_id) from $bank has been successfully DISBURSED.\n\nThank you for trusting BFS Circle as your financial growth partner.\n\nWarm regards,\nBFS Financial Services"
            ];
        case 'Banker_Claim':
            return [
                'success' => true,
                'subject' => "Formal DSA Payout Commission Claim Invoice - BFS Circle ($loan_id)",
                'body' => "Dear Branch Manager / Credit Manager,\n\nGreetings from BFS Financial Services.\n\nPlease find attached our formal DSA commission payout invoice for the disbursed loan file ($name, $loan_type - $loan_id).\n\nKindly process the brokerage payout to our registered BFS Current Account.\n\nThank you for your partnership.\n\nBest regards,\nAccounts & Payout Desk\nBFS Financial Services"
            ];
        default:
            return [
                'success' => true,
                'subject' => "Important Update Regarding Your Loan Application ($loan_id)",
                'body' => "Dear $name,\n\nGreetings from BFS Circle!\n\nThis is an update regarding your $loan_type application ($loan_id). Please review the details attached or get in touch with your assigned Relationship Manager for any queries.\n\nWarm regards,\nBFS Financial Services"
            ];
    }
}
