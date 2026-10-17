<?php
require_once 'config.php';
require_auth();

$page_title = 'WhatsApp CRM & Live Hub';
$page_subtitle = 'Integrated Multi-Agent WhatsApp Inbox, Baileys QR Engine, Broadcasts & Automations';
$active_page = 'whatsapp_crm.php';
$hide_main_header = true;

// Check if WhatsApp CRM Node engine is running (check port 3000 locally, or port 3001 on VPS)
$is_wa_online = false;
$wa_port = 3000;
$fp = @fsockopen('127.0.0.1', 3000, $errno, $errstr, 0.3);
if ($fp) {
    $is_wa_online = true;
    fclose($fp);
} else {
    $fp2 = @fsockopen('127.0.0.1', 3001, $errno, $errstr, 0.3);
    if ($fp2) {
        $is_wa_online = true;
        $wa_port = 3001;
        fclose($fp2);
    }
}

$http_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$is_local = in_array($http_host, ['localhost:8000', '127.0.0.1:8000', 'localhost', '127.0.0.1']);
if ($is_local) {
    $wa_url = 'http://localhost:' . $wa_port;
} else {
    if (defined('WA_URL') && WA_URL) {
        $wa_url = WA_URL;
    } else {
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';
        $domain = preg_replace('/^crm\./', '', $http_host);
        $wa_url = $scheme . 'wa.' . $domain;
    }
}

include 'header.php';
?>

<style>
.wacrm-container {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-top: 0;
}

.wacrm-topbar {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 10px 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}

.wacrm-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
}

.status-online {
    background: #ecfdf5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}

.status-offline {
    background: #fef2f2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

.status-pulse {
    width: 8px;
    height: 8px;
    border-radius: 50%;
}

.pulse-green {
    background: #10b981;
    box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
    animation: waPulse 2s infinite;
}

.pulse-red {
    background: #ef4444;
}

@keyframes waPulse {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
    70% { transform: scale(1.05); box-shadow: 0 0 0 7px rgba(16, 185, 129, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}

.wacrm-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.wacrm-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.15s ease;
}

.btn-wa-brand {
    background: #25d366;
    color: #ffffff;
    border: 1px solid #20ba5a;
}
.btn-wa-brand:hover {
    background: #20ba5a;
    color: #ffffff;
    transform: translateY(-1px);
}

.btn-wa-outline {
    background: #f8fafc;
    color: #0f172a;
    border: 1px solid #cbd5e1;
}
.btn-wa-outline:hover {
    background: #f1f5f9;
}

.wacrm-viewport {
    width: 100%;
    height: calc(100vh - 90px);
    min-height: 680px;
    background: #ffffff;
    border-radius: 14px;
    border: 1px solid #cbd5e1;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.06);
    position: relative;
}

.wacrm-iframe {
    width: 100%;
    height: 100%;
    border: none;
    display: block;
}

.wacrm-offline-card {
    padding: 50px 20px;
    text-align: center;
    max-width: 540px;
    margin: 40px auto;
    background: #ffffff;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
}
</style>

<div class="wacrm-container">
    <!-- Top Hub Action Bar -->
    <div class="wacrm-topbar">
        <div style="display:flex; align-items:center; gap:12px;">
            <div style="width:36px; height:36px; border-radius:10px; background:#25d366; color:#fff; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <i data-lucide="message-circle" style="width:20px; height:20px;"></i>
            </div>
            <div>
                <div style="font-size:14px; font-weight:800; color:#0f172a; line-height:1.2;">BFS Circle WhatsApp Live Control Center</div>
                <div style="font-size:11.5px; color:#64748b; font-weight:500;">Synced with Workspace: <code>wa</code> • Port: <?php echo $wa_port; ?></div>
            </div>
        </div>

        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
            <div class="wacrm-status-badge <?php echo $is_wa_online ? 'status-online' : 'status-offline'; ?>">
                <span class="status-pulse <?php echo $is_wa_online ? 'pulse-green' : 'pulse-red'; ?>"></span>
                <span><?php echo $is_wa_online ? 'WhatsApp Engine Online' : 'Engine Offline (Port ' . $wa_port . ')'; ?></span>
            </div>

            <div class="wacrm-actions">
                <a href="<?php echo htmlspecialchars($wa_url); ?>" target="_blank" class="wacrm-btn btn-wa-brand" title="Open in Dedicated Full Window">
                    <i data-lucide="external-link" style="width:13px; height:13px;"></i>
                    <span>Open in Full Tab</span>
                </a>
                <button type="button" onclick="reloadWaFrame()" class="wacrm-btn btn-wa-outline" title="Refresh WhatsApp Session">
                    <i data-lucide="refresh-cw" style="width:13px; height:13px;"></i>
                    <span>Refresh</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Main Viewport -->
    <div class="wacrm-viewport">
        <?php if ($is_wa_online): ?>
            <iframe id="waIframe" class="wacrm-iframe" src="<?php echo htmlspecialchars($wa_url); ?>" allow="camera; microphone; clipboard-read; clipboard-write;"></iframe>
        <?php else: ?>
            <div class="wacrm-offline-card">
                <div style="width:54px; height:54px; border-radius:16px; background:#fef2f2; color:#ef4444; display:flex; align-items:center; justify-content:center; margin:0 auto 16px;">
                    <i data-lucide="power-off" style="width:28px; height:28px;"></i>
                </div>
                <h3 style="font-size:18px; font-weight:800; color:#0f172a; margin-bottom:8px;">WhatsApp CRM Service is Not Running</h3>
                <p style="font-size:13px; color:#64748b; line-height:1.5; margin-bottom:16px;">
                    The WhatsApp engine (Node microservice on Port 3000) is currently offline. You can start all services in 1-click using your launcher shortcut:
                </p>
                <div style="background:#0f172a; color:#38bdf8; font-family:monospace; padding:12px 16px; border-radius:8px; font-size:12.5px; text-align:left; margin-bottom:20px; border-left:3px solid #25d366;">
                    🚀 Double-click <b>START_CRM.bat</b> in project root or <b>BFS CRM Master Launcher</b> on your Desktop!
                </div>
                <button type="button" onclick="location.reload()" class="btn-primary" style="padding:10px 24px; font-size:13px; margin:0 auto;">
                    <i data-lucide="refresh-cw"></i> Check Status Again
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function reloadWaFrame() {
    const frame = document.getElementById('waIframe');
    if (frame) {
        frame.src = frame.src;
    } else {
        location.reload();
    }
}
</script>

<?php include 'footer.php'; ?>
