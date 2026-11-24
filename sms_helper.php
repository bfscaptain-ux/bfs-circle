<?php
/**
 * sms_helper.php
 * Official BFS Circle SMS Gateway Helper (Powered by SMS Captain / textbee Engine)
 * 
 * Provides automated SMS dispatching, lead alerts, verification OTPs, and status triggers
 * via physical Android SIM cards with Zero DLT hassle & zero per-message cost.
 */

if (!defined('SMS_CAPTAIN_API_URL')) {
    define('SMS_CAPTAIN_API_URL', getenv('SMS_CAPTAIN_API_URL') ?: 'http://127.0.0.1:3006');
}

if (!defined('SMS_CAPTAIN_DASHBOARD_URL')) {
    define('SMS_CAPTAIN_DASHBOARD_URL', getenv('SMS_CAPTAIN_DASHBOARD_URL') ?: 'https://sms.bfsfin.cloud');
}

if (!defined('SMS_CAPTAIN_API_KEY')) {
    define('SMS_CAPTAIN_API_KEY', getenv('SMS_CAPTAIN_API_KEY') ?: 'txb_anuj_bfs_live_2026_sms_gateway');
}

if (!defined('BFS_PRIMARY_ADMIN_MOBILE')) {
    define('BFS_PRIMARY_ADMIN_MOBILE', '9258724227');
}

/**
 * Format Indian mobile number to E.164 standard (+91XXXXXXXXXX)
 */
function format_sms_mobile($mobile) {
    $clean = preg_replace('/[^0-9]/', '', (string)$mobile);
    if (strlen($clean) === 10) {
        return '+91' . $clean;
    }
    if (strlen($clean) === 12 && substr($clean, 0, 2) === '91') {
        return '+' . $clean;
    }
    if (strlen($clean) === 11 && substr($clean, 0, 1) === '0') {
        return '+91' . substr($clean, 1);
    }
    if (strpos((string)$mobile, '+') === 0) {
        return '+' . $clean;
    }
    return '+91' . substr($clean, -10);
}

/**
 * Check if SMS Captain Backend Engine (port 3006) is running
 */
function check_sms_gateway_online($timeout_sec = 0.5) {
    $host = parse_url(SMS_CAPTAIN_API_URL, PHP_URL_HOST) ?: '127.0.0.1';
    $port = parse_url(SMS_CAPTAIN_API_URL, PHP_URL_PORT) ?: 3006;

    $fp = @fsockopen($host, $port, $errno, $errstr, $timeout_sec);
    if ($fp) {
        fclose($fp);
        return true;
    }
    return false;
}

/**
 * Send an SMS via SMS Captain Gateway API
 * 
 * @param string|array $recipients Single mobile or array of numbers
 * @param string $message Text content to deliver
 * @param string|null $device_id Specific phone device ID or null for default
 * @return array ['success' => bool, 'message' => string, 'response' => mixed]
 */
function send_bfs_sms($recipients, $message, $device_id = null) {
    if (empty($recipients) || empty(trim($message))) {
        return ['success' => false, 'message' => 'Recipients and message content cannot be empty.'];
    }

    $raw_list = is_array($recipients) ? $recipients : [$recipients];
    $formatted_recipients = [];

    foreach ($raw_list as $mob) {
        $formatted = format_sms_mobile($mob);
        if (strlen($formatted) >= 12) {
            $formatted_recipients[] = $formatted;
        }
    }

    if (empty($formatted_recipients)) {
        return ['success' => false, 'message' => 'No valid mobile numbers provided.'];
    }

    // Check if gateway is reachable
    if (!check_sms_gateway_online(0.6)) {
        return [
            'success' => false,
            'message' => 'SMS Captain Gateway (port 3006) is currently unreachable. Make sure Docker / SMS Captain API is running.'
        ];
    }

    $endpoint = rtrim(SMS_CAPTAIN_API_URL, '/') . '/api/v1/gateway/send-sms';
    if ($device_id) {
        $endpoint = rtrim(SMS_CAPTAIN_API_URL, '/') . '/api/v1/gateway/devices/' . urlencode($device_id) . '/send-sms';
    }

    $payload = [
        'recipients' => $formatted_recipients,
        'message' => trim($message)
    ];

    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'x-api-key: ' . SMS_CAPTAIN_API_KEY
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        return [
            'success' => false,
            'message' => 'cURL connection error: ' . $curl_error
        ];
    }

    $json_res = json_decode($response, true);

    if ($http_code >= 200 && $http_code < 300) {
        return [
            'success' => true,
            'message' => 'SMS queued/dispatched successfully.',
            'response' => $json_res
        ];
    }

    $err_msg = $json_res['message'] ?? ($json_res['error'] ?? 'HTTP ' . $http_code);
    if (is_array($err_msg)) {
        $err_msg = implode(', ', $err_msg);
    }

    return [
        'success' => false,
        'message' => 'SMS Gateway Error (' . $http_code . '): ' . $err_msg,
        'response' => $json_res
    ];
}

/**
 * Dispatch instant Lead Alert SMS to Admin / Executive phone
 */
function send_lead_alert_sms($lead_name, $lead_phone, $loan_amount, $loan_type, $city = 'Agra') {
    $admin_mobile = BFS_PRIMARY_ADMIN_MOBILE;
    $amount_fmt = is_numeric($loan_amount) ? '₹' . number_format($loan_amount) : $loan_amount;

    $msg = "⚡ BFS New Lead Alert!\n"
         . "Client: " . trim($lead_name) . "\n"
         . "Product: " . trim($loan_type) . " (" . $amount_fmt . ")\n"
         . "City: " . trim($city) . "\n"
         . "Phone: " . trim($lead_phone) . "\n"
         . "Action: Login to CRM now to claim lead.";

    return send_bfs_sms($admin_mobile, $msg);
}

/**
 * Dispatch Verification OTP SMS to Client
 */
function send_otp_sms($client_mobile, $otp_code) {
    $msg = "BFS Verification Code: " . $otp_code . ". Bhardwaj Financial Services.";
    return send_bfs_sms($client_mobile, $msg);
}

/**
 * Dispatch Loan Lifecycle Status SMS to Applicant
 */
function send_loan_status_sms($client_mobile, $client_name, $status_type, $bank_name = 'Partner Bank', $app_no = '') {
    $msg = "";
    switch (strtolower($status_type)) {
        case 'logged_in':
        case 'login':
            $msg = "Dear " . trim($client_name) . ", your loan application " . ($app_no ? "[$app_no] " : "") 
                 . "has been logged in at " . $bank_name . ". We are tracking underwriter vetting. - BFS Team";
            break;
        case 'sanctioned':
            $msg = "Congratulations " . trim($client_name) . "! Your loan has been in-principle Sanctioned by " 
                 . $bank_name . ". Contact your BFS RM at 9258-724-227 for offer letter collection.";
            break;
        case 'disbursed':
            $msg = "Dear " . trim($client_name) . ", loan disbursement has been completed by " . $bank_name . ". "
                 . "Thank you for choosing Bhardwaj Financial Services (BFS).";
            break;
        default:
            $msg = "Dear " . trim($client_name) . ", your loan file status has been updated to: " . $status_type . ". - BFS Financial Services";
            break;
    }

    return send_bfs_sms($client_mobile, $msg);
}
