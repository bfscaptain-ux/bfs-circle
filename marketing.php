<?php
require_once 'config.php';
require_auth();
require_role(['Admin', 'Staff']);

$page_title = 'BFS Circle Mailer & Campaign Hub';
$page_subtitle = 'Integrated High-Speed Bulk Campaign & Audience Engine for BFS Circle';
$active_page = 'marketing.php';
$hide_main_header = true;

// Check if Listmonk engine is running on port 9000
$is_lm_online = false;
$lm_port = 9000;
$fp = @fsockopen('127.0.0.1', 9000, $errno, $errstr, 0.3);
if ($fp) {
    $is_lm_online = true;
    fclose($fp);
}

$http_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$is_local = in_array($http_host, ['localhost:8000', '127.0.0.1:8000', 'localhost', '127.0.0.1']);

if ($is_local) {
    $lm_url = 'http://localhost:9000/admin';
} else {
    if (defined('LISTMONK_PUBLIC_URL') && LISTMONK_PUBLIC_URL) {
        $lm_url = LISTMONK_PUBLIC_URL;
    } else {
        $is_ssl = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                  ($_SERVER['SERVER_PORT'] ?? 0) == 443 ||
                  (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
                  (!in_array($http_host, ['localhost', '127.0.0.1', 'localhost:8000', '127.0.0.1:8000']));
        $scheme = $is_ssl ? 'https://' : 'http://';
        $domain = preg_replace('/^crm\./', '', $http_host);
        $lm_url = $scheme . 'mailer.' . $domain . '/admin';
    }
}

include 'header.php';
?>

<style>
.lmcrm-container {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-top: 0;
    padding: 0 4px;
}

.lmcrm-topbar {
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: 12px;
    padding: 10px 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
}

.lmcrm-status-badge {
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
    border: 1px solid rgba(16, 185, 129, 0.35);
}

.status-offline {
    background: rgba(239, 68, 68, 0.15);
    color: #fca5a5;
    border: 1px solid rgba(239, 68, 68, 0.35);
}

.status-pulse {
    width: 8px;
    height: 8px;
    border-radius: 50%;
}

.pulse-green {
    background: #10b981;
    box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
    animation: lmPulse 2s infinite;
}

.pulse-red {
    background: #ef4444;
}

@keyframes lmPulse {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
    70% { transform: scale(1.05); box-shadow: 0 0 0 7px rgba(16, 185, 129, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}

.lmcrm-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.lmcrm-btn {
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

.btn-lm-brand {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.35);
}
.btn-lm-brand:hover {
    background: linear-gradient(135deg, #34d399 0%, #10b981 100%);
    color: #ffffff;
    transform: translateY(-1px);
}

.btn-lm-outline {
    background: rgba(255, 255, 255, 0.06);
    color: #cbd5e1;
    border: 1px solid rgba(255, 255, 255, 0.14);
}
.btn-lm-outline:hover {
    background: rgba(255, 255, 255, 0.12);
    color: #ffffff;
}

.lmcrm-viewport {
    width: 100%;
    height: calc(100vh - 125px);
    min-height: 720px;
    background: #ffffff;
    border-radius: 14px;
    border: 1px solid #1e293b;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0,0,0,0.25);
    position: relative;
}

.lmcrm-iframe {
    width: 100%;
    height: 100%;
    border: none;
    display: block;
    background: #ffffff;
}

.lmcrm-offline-card {
    padding: 50px 20px;
    text-align: center;
    max-width: 540px;
    margin: 60px auto;
    background: #0f172a;
    border-radius: 16px;
    border: 1px solid #1e293b;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}
</style>

<div class="lmcrm-container">
    <!-- Top Hub Action Bar -->
    <div class="lmcrm-topbar">
        <div style="display:flex; align-items:center; gap:12px;">
            <div style="width:36px; height:36px; border-radius:10px; background:#0f172a; border:1px solid #334155; color:#fff; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <i data-lucide="mail" style="width:20px; height:20px;"></i>
            </div>
            <div>
                <div style="font-size:14px; font-weight:800; color:#ffffff; line-height:1.2;">BFS Circle Mailer Control Center</div>
                <div style="font-size:11.5px; color:#94a3b8; font-weight:500;">High-Speed Campaign Engine • Port: <?php echo $lm_port; ?></div>
            </div>
        </div>

        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
            <div class="lmcrm-status-badge <?php echo $is_lm_online ? 'status-online' : 'status-offline'; ?>">
                <span class="status-pulse <?php echo $is_lm_online ? 'pulse-green' : 'pulse-red'; ?>"></span>
                <span><?php echo $is_lm_online ? 'BFS Mailer Online' : 'Engine Offline (Port ' . $lm_port . ')'; ?></span>
            </div>

            <div class="lmcrm-actions">
                <button type="button" onclick="triggerQuickSync(this)" class="lmcrm-btn btn-lm-brand" title="Synchronize CRM Contacts into Listmonk Smart Lists">
                    <i data-lucide="arrows-rotate" style="width:13px; height:13px;"></i>
                    <span id="syncBtnText">Sync CRM Contacts</span>
                </button>
                <a href="<?php echo htmlspecialchars($lm_url); ?>" target="_blank" class="lmcrm-btn btn-lm-outline" title="Open in Dedicated Full Window">
                    <i data-lucide="external-link" style="width:13px; height:13px;"></i>
                    <span>Open in Full Tab</span>
                </a>
                <button type="button" onclick="reloadLmFrame()" class="lmcrm-btn btn-lm-outline" title="Refresh Listmonk View">
                    <i data-lucide="refresh-cw" style="width:13px; height:13px;"></i>
                    <span>Refresh</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Main Viewport (Direct Native Listmonk Application) -->
    <div class="lmcrm-viewport">
        <?php if ($is_lm_online): ?>
            <iframe id="lmIframe" class="lmcrm-iframe" src="<?php echo htmlspecialchars($lm_url); ?>" allow="clipboard-read; clipboard-write;"></iframe>
        <?php else: ?>
            <div class="lmcrm-offline-card">
                <div style="width:54px; height:54px; border-radius:16px; background:rgba(239,68,68,0.15); color:#ef4444; display:flex; align-items:center; justify-content:center; margin:0 auto 16px;">
                    <i data-lucide="power-off" style="width:28px; height:28px;"></i>
                </div>
                <h3 style="font-size:18px; font-weight:800; color:#ffffff; margin-bottom:8px;">Listmonk Engine is Not Running</h3>
                <p style="font-size:13px; color:#94a3b8; line-height:1.5; margin-bottom:16px;">
                    The Listmonk Docker container (Go microservice on Port 9000) is currently offline. You can start it in your Docker desktop or terminal:
                </p>
                <div style="background:#090d16; color:#38bdf8; font-family:monospace; padding:12px 16px; border-radius:8px; font-size:12.5px; text-align:left; margin-bottom:20px; border-left:3px solid #10b981;">
                    docker start listmonk_db listmonk_app
                </div>
                <button type="button" onclick="location.reload()" class="lmcrm-btn btn-lm-brand" style="margin:0 auto; padding:10px 24px;">
                    <i data-lucide="refresh-cw"></i> Check Status Again
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function reloadLmFrame() {
    const frame = document.getElementById('lmIframe');
    if (frame) {
        frame.src = frame.src;
    } else {
        location.reload();
    }
}

function triggerQuickSync(btn) {
    const textEl = document.getElementById('syncBtnText');
    const origText = textEl.innerText;
    btn.disabled = true;
    textEl.innerText = 'Syncing...';

    fetch('api.php?api=listmonk_sync_all', { method: 'POST' })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        textEl.innerText = origText;
        if (data.success) {
            alert(`✅ Synchronized ${data.total_synced} CRM contacts to Listmonk smart lists!`);
            reloadLmFrame();
        } else {
            alert('Sync failed: ' + (data.error || 'Server error'));
        }
    })
    .catch(err => {
        btn.disabled = false;
        textEl.innerText = origText;
        alert('Network connection error.');
    });
}
</script>

<?php include 'footer.php'; ?>
