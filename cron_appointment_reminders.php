<?php
/**
 * cron_appointment_reminders.php
 * Automatically sends a WhatsApp reminder ~2 hours before each upcoming
 * appointment in `website_appointments` (once per scheduled slot).
 *
 * Recommended VPS Crontab (runs every 5 minutes):
 *   crontab line: every-5-min /usr/bin/php /var/www/crm/cron_appointment_reminders.php
 *
 * Also callable securely via HTTPS with ?key=WACRM_API_KEY (or Admin session)
 * and testable from CLI with `--dry-run`.
 */

$isCli = (php_sapi_name() === 'cli');
$dryRun = $isCli && in_array('--dry-run', $argv ?? [], true);

require_once __DIR__ . '/config.php';

if (!$isCli) {
    header('Content-Type: application/json; charset=utf-8');
    $providedKey = $_GET['key'] ?? ($_SERVER['HTTP_X_API_KEY'] ?? '');
    $expectedKey = defined('WACRM_API_KEY') ? WACRM_API_KEY : '';
    $isAuthedAdmin = !empty($_SESSION['user_id']) && (($_SESSION['role'] ?? '') === 'Admin');
    $validKey = ($expectedKey !== '' && is_string($providedKey) && hash_equals($expectedKey, $providedKey));

    if (!$isAuthedAdmin && !$validKey) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Unauthorized cron access']);
        exit;
    }
}

/**
 * Parse `slot_date` and `slot_time` into a Unix timestamp in Asia/Kolkata.
 * Returns null if the date/time string cannot be reliably parsed.
 */
function bfs_parse_appointment_start_ts($slotDate, $slotTime) {
    $dateRaw = trim((string)$slotDate);
    $timeRaw = trim((string)$slotTime);
    if ($dateRaw === '') {
        return null;
    }

    // Strip leading weekday names like "Wednesday, Nov 18, 2026" -> "Nov 18, 2026"
    $dateClean = preg_replace('/^(?:Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday),?\s*/i', '', $dateRaw);

    // Extract start time from ranges like "02:00 PM - 03:30 PM" or "14:00 – 15:00"
    $timeClean = '10:00 AM';
    if ($timeRaw !== '') {
        if (preg_match('/(\d{1,2}(?::\d{2})?\s*(?:AM|PM))/i', $timeRaw, $m)) {
            $timeClean = trim($m[1]);
        } elseif (preg_match('/(\d{1,2}:\d{2})/', $timeRaw, $m)) {
            $timeClean = trim($m[1]);
        }
    }

    $combined = trim($dateClean . ' ' . $timeClean);
    $ts = strtotime($combined);
    if ($ts !== false && $ts > 0) {
        return $ts;
    }

    foreach (['Y-m-d h:i A', 'Y-m-d H:i', 'd-m-Y h:i A', 'd/m/Y h:i A', 'M j, Y h:i A', 'd M Y h:i A'] as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $combined);
        if ($dt instanceof DateTime) {
            return $dt->getTimestamp();
        }
    }

    return null;
}

/**
 * Dispatch the 2-hour appointment reminder template with safe fallback.
 */
function bfs_dispatch_appt_reminder_wa(array $appt, $dateLabel, $timeLabel) {
    if (!function_exists('send_bfs_whatsapp_template')) {
        return ['success' => false, 'error' => 'send_bfs_whatsapp_template helper missing'];
    }

    $nameClean = trim((string)($appt['name'] ?? '')) ?: 'Customer';
    $topicClean = trim((($appt['product_type'] ?? '') . ' ' . ($appt['sub_type'] ?? ''))) ?: 'Loan Consultation';
    $topicClean = preg_replace('/\s+/', ' ', $topicClean);
    $cityClean = trim((string)($appt['city'] ?? '')) ?: 'Agra';

    $primary = send_bfs_whatsapp_template(
        $appt['phone'],
        'bfs_appointment_reminder_v1',
        'en_US',
        [$nameClean, $dateLabel, $timeLabel, $topicClean],
        $nameClean
    );
    if (!empty($primary['success'])) {
        $primary['template_used'] = 'bfs_appointment_reminder_v1';
        return $primary;
    }

    // Fallback to already-approved Utility confirmation template while v1 awaits review
    $fallback = send_bfs_whatsapp_template(
        $appt['phone'],
        'bfs_website_lead_confirm_v2',
        'en_US',
        [
            $nameClean,
            "Appointment Reminder: {$topicClean}",
            "{$dateLabel} • {$timeLabel}",
            $cityClean
        ],
        $nameClean
    );
    $fallback['template_used'] = !empty($fallback['success']) ? 'bfs_website_lead_confirm_v2' : 'failed';
    return $fallback;
}

$db->exec("CREATE TABLE IF NOT EXISTS appointment_reminders_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    appointment_id INTEGER NOT NULL,
    slot_signature TEXT NOT NULL,
    reminder_type TEXT NOT NULL DEFAULT '2h_before',
    phone TEXT,
    template_used TEXT,
    status TEXT NOT NULL DEFAULT 'sent',
    response_json TEXT,
    sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(appointment_id, slot_signature, reminder_type)
)");

$nowTs = time();
$windowSeconds = 7200; // 2 hours prior to appointment start

$stmt = $db->query("SELECT * FROM website_appointments
    WHERE status IN ('Scheduled', 'Confirmed', 'Rescheduled')
      AND phone IS NOT NULL
      AND TRIM(phone) != ''
    ORDER BY id DESC LIMIT 300");
$appointments = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

$summary = [
    'success' => true,
    'checked_at' => date('Y-m-d H:i:s', $nowTs),
    'dry_run' => $dryRun,
    'evaluated' => count($appointments),
    'due_within_2h' => 0,
    'sent' => 0,
    'skipped_already_sent' => 0,
    'failed' => 0,
    'details' => [],
];

$checkStmt = $db->prepare("SELECT id, status FROM appointment_reminders_log
    WHERE appointment_id = ? AND slot_signature = ? AND reminder_type = '2h_before' LIMIT 1");
$upsertStmt = $db->prepare("INSERT INTO appointment_reminders_log
    (appointment_id, slot_signature, reminder_type, phone, template_used, status, response_json, sent_at)
    VALUES (?, ?, '2h_before', ?, ?, ?, ?, datetime('now', 'localtime'))
    ON CONFLICT(appointment_id, slot_signature, reminder_type) DO UPDATE SET
        phone = excluded.phone,
        template_used = excluded.template_used,
        status = excluded.status,
        response_json = excluded.response_json,
        sent_at = datetime('now', 'localtime')");

foreach ($appointments as $appt) {
    $startTs = bfs_parse_appointment_start_ts($appt['slot_date'] ?? '', $appt['slot_time'] ?? '');
    if ($startTs === null) {
        continue;
    }

    $secondsUntil = $startTs - $nowTs;
    if ($secondsUntil <= 0 || $secondsUntil > $windowSeconds) {
        continue;
    }

    $summary['due_within_2h']++;
    $slotSig = date('Y-m-d H:i', $startTs);
    $apptId = (int)$appt['id'];

    $checkStmt->execute([$apptId, $slotSig]);
    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
    if ($existing && ($existing['status'] ?? '') === 'sent') {
        $summary['skipped_already_sent']++;
        continue;
    }

    $dateLabel = trim((string)($appt['slot_date'] ?? '')) ?: date('d M Y', $startTs);
    $timeLabel = trim((string)($appt['slot_time'] ?? '')) ?: date('h:i A', $startTs);
    $minsLeft = (int)ceil($secondsUntil / 60);

    if ($dryRun) {
        $summary['details'][] = [
            'appointment_id' => $apptId,
            'name' => $appt['name'],
            'phone' => $appt['phone'],
            'slot' => "{$dateLabel} {$timeLabel}",
            'minutes_until' => $minsLeft,
            'action' => 'would_send',
        ];
        continue;
    }

    $waRes = bfs_dispatch_appt_reminder_wa($appt, $dateLabel, $timeLabel);
    $isOk = !empty($waRes['success']);
    $tplUsed = (string)($waRes['template_used'] ?? 'bfs_appointment_reminder_v1');

    $upsertStmt->execute([
        $apptId,
        $slotSig,
        $appt['phone'],
        $tplUsed,
        $isOk ? 'sent' : 'failed',
        json_encode($waRes, JSON_UNESCAPED_UNICODE)
    ]);

    if ($isOk) {
        $summary['sent']++;
        try {
            $adminId = $db->query("SELECT id FROM users WHERE role = 'Admin' ORDER BY id ASC LIMIT 1")->fetchColumn() ?: 1;
            $notifMsg = "2h Appointment WhatsApp Reminder sent to {$appt['name']} ({$dateLabel} {$timeLabel})";
            $db->prepare("INSERT INTO notifications (user_id, message, type, is_read, created_at) VALUES (?, ?, 'appointment', 0, datetime('now', 'localtime'))")
               ->execute([$adminId, $notifMsg]);
            if (function_exists('log_activity')) {
                log_activity($notifMsg, "appointments.php");
            }
        } catch (Throwable $e) {
            // Non-blocking notification failure
        }
    } else {
        $summary['failed']++;
    }

    $summary['details'][] = [
        'appointment_id' => $apptId,
        'name' => $appt['name'],
        'slot' => "{$dateLabel} {$timeLabel}",
        'minutes_until' => $minsLeft,
        'template' => $tplUsed,
        'status' => $isOk ? 'sent' : 'failed',
    ];
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
