<?php
require_once 'config.php';
require_once 'sms_helper.php';
require_auth();
require_role(['Admin', 'Staff']);

$page_title = 'BFS Circle SMS Gateway Hub';
$page_subtitle = 'Integrated Android SMS Gateway (SMS Captain), Single/Bulk Dispatch & Webhooks';
$active_page = 'sms_crm.php';
$hide_main_header = true;

// Check if SMS Captain Backend API (port 3006) & Web Dashboard (port 3005) are running
$is_api_online = false;
$is_web_online = false;

$fp1 = @fsockopen('127.0.0.1', 3006, $errno, $errstr, 0.3);
if ($fp1) {
    $is_api_online = true;
    fclose($fp1);
}

$fp2 = @fsockopen('127.0.0.1', 3005, $errno, $errstr, 0.3);
if ($fp2) {
    $is_web_online = true;
    fclose($fp2);
}

$http_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$is_local = in_array($http_host, ['localhost:8000', '127.0.0.1:8000', 'localhost', '127.0.0.1']);

if ($is_local) {
    $sms_dashboard_url = 'http://localhost:3005/dashboard';
} else {
    if (defined('SMS_CAPTAIN_PUBLIC_URL') && SMS_CAPTAIN_PUBLIC_URL) {
        $sms_dashboard_url = SMS_CAPTAIN_PUBLIC_URL;
    } else {
        $is_ssl = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                  ($_SERVER['SERVER_PORT'] ?? 0) == 443 ||
                  (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
                  (!in_array($http_host, ['localhost', '127.0.0.1', 'localhost:8000', '127.0.0.1:8000']));
        $scheme = $is_ssl ? 'https://' : 'http://';
        $domain = preg_replace('/^crm\./', '', $http_host);
        $sms_dashboard_url = $scheme . 'sms.' . $domain . '/dashboard';
    }
}

// Handle Direct Quick SMS Dispatch from CRM UI
$test_sms_result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'quick_send_sms') {
    $mobile = trim($_POST['recipient_mobile'] ?? '');
    $msg = trim($_POST['sms_message'] ?? '');
    
    if (!empty($mobile) && !empty($msg)) {
        $test_sms_result = send_bfs_sms($mobile, $msg);
    } else {
        $test_sms_result = ['success' => false, 'message' => 'Recipient mobile and message cannot be empty.'];
    }
}

include 'header.php';
?>

<style>
.smscrm-container {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-top: 0;
    padding: 0 4px;
}

.smscrm-topbar {
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: 12px;
    padding: 10px 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.15);
}

.smscrm-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
}

.status-online {
    background: rgba(16, 185, 129, 0.15);
    color: #34d399;
    border: 1px solid rgba(16, 185, 129, 0.4);
}

.status-offline {
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, 0.4);
}

.status-pulse {
    width: 8px;
    height: 8px;
    border-radius: 50%;
}

.pulse-green {
    background: #10b981;
    box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
    animation: smsPulse 2s infinite;
}

.pulse-red {
    background: #ef4444;
}

@keyframes smsPulse {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
    70% { transform: scale(1.05); box-shadow: 0 0 0 7px rgba(16, 185, 129, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}

.smscrm-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.smscrm-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 14px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.15s ease;
    border: none;
}

.btn-sms-brand {
    background: linear-gradient(135deg, #0ea5e9, #0284c7);
    color: #ffffff;
    border: 1px solid #38bdf8;
    box-shadow: 0 2px 8px rgba(14, 165, 233, 0.3);
}
.btn-sms-brand:hover {
    background: linear-gradient(135deg, #0284c7, #0369a1);
    color: #ffffff;
    transform: translateY(-1px);
}

.btn-sms-action {
    background: #1e293b;
    color: #e2e8f0;
    border: 1px solid #334155;
}
.btn-sms-action:hover {
    background: #334155;
    color: #ffffff;
}

.smscrm-viewport {
    width: 100%;
    height: calc(100vh - 100px);
    min-height: 680px;
    background: #0b1329;
    border-radius: 14px;
    border: 1px solid #1e293b;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0,0,0,0.25);
    position: relative;
}

.smscrm-iframe {
    width: 100%;
    height: 100%;
    border: none;
    display: block;
    background: #ffffff;
}

.smscrm-offline-card {
    padding: 50px 24px;
    text-align: center;
    max-width: 580px;
    margin: 50px auto;
    background: #0f172a;
    border-radius: 20px;
    border: 1px solid #1e293b;
    box-shadow: 0 20px 40px rgba(0,0,0,0.4);
    color: #e2e8f0;
}

/* Modal styling for Quick Send SMS */
.sms-modal-backdrop {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(4px);
    z-index: 99999;
    align-items: center;
    justify-content: center;
}
.sms-modal-box {
    background: #0f172a;
    border: 1px solid #334155;
    border-radius: 16px;
    width: 95%;
    max-width: 480px;
    padding: 24px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
    color: #f8fafc;
}
</style>

<div class="smscrm-container">
    <?php require_once 'omni_channel_bar.php'; ?>
    <!-- Result Alert Banner (if Quick SMS was sent) -->
    <?php if ($test_sms_result !== null): ?>
        <div style="background: <?php echo $test_sms_result['success'] ? 'rgba(16, 185, 129, 0.15)' : 'rgba(239, 68, 68, 0.15)'; ?>; border: 1px solid <?php echo $test_sms_result['success'] ? '#10b981' : '#ef4444'; ?>; border-radius: 10px; padding: 12px 18px; display: flex; align-items: center; justify-content: space-between; color: #fff; font-size: 13px;">
            <div style="display:flex; align-items:center; gap:10px;">
                <i data-lucide="<?php echo $test_sms_result['success'] ? 'check-circle' : 'alert-triangle'; ?>" style="color: <?php echo $test_sms_result['success'] ? '#34d399' : '#f87171'; ?>; width: 18px; height: 18px;"></i>
                <span><strong><?php echo $test_sms_result['success'] ? 'Success: ' : 'Error: '; ?></strong><?php echo htmlspecialchars($test_sms_result['message']); ?></span>
            </div>
            <button onclick="this.parentElement.style.display='none'" style="background:none; border:none; color:#94a3b8; cursor:pointer; font-size:16px;">&times;</button>
        </div>
    <?php endif; ?>

    <!-- Top Hub Action Bar -->
    <div class="smscrm-topbar">
        <div style="display:flex; align-items:center; gap:12px;">
            <div style="width:38px; height:38px; border-radius:10px; background:linear-gradient(135deg, #0284c7, #0369a1); color:#fff; display:flex; align-items:center; justify-content:center; flex-shrink:0; box-shadow:0 0 12px rgba(14,165,233,0.35);">
                <i data-lucide="smartphone" style="width:20px; height:20px;"></i>
            </div>
            <div>
                <div style="font-size:14px; font-weight:800; color:#f8fafc; line-height:1.2;">BFS SMS Captain Gateway</div>
                <div style="font-size:11.5px; color:#94a3b8; font-weight:500;">
                    API Port: <code>3006</code> • Web Port: <code>3005</code> • Engine: <code>NestJS + Redis + Android SIM</code>
                </div>
            </div>
        </div>

        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
            <!-- Status Badge -->
            <div class="smscrm-status-badge <?php echo ($is_api_online && $is_web_online) ? 'status-online' : 'status-offline'; ?>">
                <span class="status-pulse <?php echo ($is_api_online && $is_web_online) ? 'pulse-green' : 'pulse-red'; ?>"></span>
                <span><?php echo ($is_api_online && $is_web_online) ? 'SMS Gateway Online' : 'Service Offline (Ports 3005/3006)'; ?></span>
            </div>

            <!-- Action Buttons -->
            <div class="smscrm-actions">
                <button type="button" onclick="openQuickSmsModal()" class="smscrm-btn btn-sms-brand" title="Send Fast SMS from CRM">
                    <i data-lucide="send" style="width:13px; height:13px;"></i>
                    <span>Quick Send SMS</span>
                </button>

                <a href="<?php echo htmlspecialchars($sms_dashboard_url); ?>" target="_blank" class="smscrm-btn btn-sms-action" title="Open SMS Captain in Full Tab">
                    <i data-lucide="external-link" style="width:13px; height:13px;"></i>
                    <span>Open in Full Tab</span>
                </a>

                <button type="button" onclick="reloadSmsFrame()" class="smscrm-btn btn-sms-action" title="Refresh Dashboard Frame">
                    <i data-lucide="refresh-cw" style="width:13px; height:13px;"></i>
                    <span>Refresh</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Main Viewport -->
    <div class="smscrm-viewport">
        <?php if ($is_web_online): ?>
            <iframe id="smsIframe" class="smscrm-iframe" src="<?php echo htmlspecialchars($sms_dashboard_url); ?>" allow="clipboard-read; clipboard-write;"></iframe>
        <?php else: ?>
            <div class="smscrm-offline-card">
                <div style="width:58px; height:58px; border-radius:18px; background:rgba(239,68,68,0.15); color:#f87171; display:flex; align-items:center; justify-content:center; margin:0 auto 18px; border:1px solid rgba(239,68,68,0.3);">
                    <i data-lucide="power-off" style="width:30px; height:30px;"></i>
                </div>
                <h3 style="font-size:19px; font-weight:800; color:#f8fafc; margin-bottom:8px;">SMS Captain Dashboard Offline</h3>
                <p style="font-size:13px; color:#94a3b8; line-height:1.6; margin-bottom:20px;">
                    The SMS Captain Next.js frontend (Port 3005) or NestJS API (Port 3006) is currently not responding. Please make sure the service is started.
                </p>
                <div style="background:#020617; color:#38bdf8; font-family:monospace; padding:14px 18px; border-radius:10px; font-size:12px; text-align:left; margin-bottom:22px; border-left:3px solid #0ea5e9;">
                    📁 Start SMS Captain Backend: <code>pm2 restart sms-api</code> (Port 3006)<br>
                    🌐 Start SMS Captain Web: <code>pm2 restart sms-web</code> (Port 3005)
                </div>
                <button type="button" onclick="location.reload()" class="smscrm-btn btn-sms-brand" style="padding:10px 24px; font-size:13px; margin:0 auto;">
                    <i data-lucide="refresh-cw"></i> Check Status Again
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- QUICK SEND SMS MODAL -->
<div id="quickSmsModal" class="sms-modal-backdrop">
    <div class="sms-modal-box">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; border-bottom:1px solid #334155; pb-3;">
            <div style="display:flex; align-items:center; gap:10px;">
                <div style="width:32px; height:32px; border-radius:8px; background:#0ea5e9; color:#fff; display:flex; align-items:center; justify-content:center;">
                    <i data-lucide="send" style="width:16px; height:16px;"></i>
                </div>
                <div>
                    <h4 style="font-size:15px; font-weight:800; color:#f8fafc; margin:0;">Quick Send SMS</h4>
                    <p style="font-size:11px; color:#94a3b8; margin:0;">Dispatched instantly via connected Android SIM</p>
                </div>
            </div>
            <button onclick="closeQuickSmsModal()" style="background:none; border:none; color:#94a3b8; font-size:20px; cursor:pointer;">&times;</button>
        </div>

        <form method="POST" action="sms_crm.php">
            <input type="hidden" name="action" value="quick_send_sms">

            <div style="margin-bottom:14px;">
                <label style="display:block; font-size:12px; font-weight:700; color:#cbd5e1; margin-bottom:6px;">Recipient Mobile Number *</label>
                <div style="display:flex; align-items:center; background:#1e293b; border:1px solid #334155; border-radius:8px; padding:0 10px;">
                    <span style="font-size:13px; font-weight:700; color:#94a3b8; margin-right:6px;">+91</span>
                    <input type="text" name="recipient_mobile" required placeholder="9258724227" value="9258724227" style="width:100%; background:transparent; border:none; padding:10px 0; color:#f8fafc; font-size:13px; font-weight:600; outline:none;">
                </div>
            </div>

            <div style="margin-bottom:18px;">
                <label style="display:block; font-size:12px; font-weight:700; color:#cbd5e1; margin-bottom:6px;">SMS Message Content *</label>
                <textarea name="sms_message" rows="4" required placeholder="Type your SMS here..." style="width:100%; background:#1e293b; border:1px solid #334155; border-radius:8px; padding:10px 12px; color:#f8fafc; font-size:13px; font-family:sans-serif; outline:none; resize:none;">Namaste from Bhardwaj Financial Services (BFS). Your loan file has been verified and processed successfully. Call 9258-724-227 for details.</textarea>
                <div style="font-size:11px; color:#64748b; margin-top:4px; text-align:right;">Zero DLT restriction • Delivered via local SIM</div>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="closeQuickSmsModal()" class="smscrm-btn btn-sms-action">Cancel</button>
                <button type="submit" class="smscrm-btn btn-sms-brand">
                    <i data-lucide="send" style="width:13px; height:13px;"></i>
                    <span>Send SMS Now</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function reloadSmsFrame() {
    const frame = document.getElementById('smsIframe');
    if (frame) {
        const base = frame.src.split('?')[0];
        frame.src = base + '?_t=' + Date.now();
    } else {
        location.reload();
    }
}

function openQuickSmsModal() {
    document.getElementById('quickSmsModal').style.display = 'flex';
    if (window.lucide) { lucide.createIcons(); }
}

function closeQuickSmsModal() {
    document.getElementById('quickSmsModal').style.display = 'none';
}

window.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeQuickSmsModal();
});
</script>

<?php include 'footer.php'; ?>
