<?php
require_once 'config.php';
require_auth();
require_role(['Admin', 'Staff']);

$page_title = 'Postiz Social Media & Marketing Hub';
$page_subtitle = 'Multi-Platform AI Social Scheduling, Content Calendar & Lead Generation (Instagram, Facebook, LinkedIn, X, YouTube)';
$active_page = 'postiz.php';
$hide_main_header = true;

// Check if Postiz engine is running (check port 4200 first, or port 5000/4007)
$is_postiz_online = false;
$postiz_port = 4200;

$fp = @fsockopen('127.0.0.1', 4200, $errno, $errstr, 0.3);
if ($fp) {
    $is_postiz_online = true;
    fclose($fp);
} else {
    $fp2 = @fsockopen('127.0.0.1', 5000, $errno, $errstr, 0.3);
    if ($fp2) {
        $is_postiz_online = true;
        $postiz_port = 5000;
        fclose($fp2);
    } else {
        $fp3 = @fsockopen('127.0.0.1', 4007, $errno, $errstr, 0.3);
        if ($fp3) {
            $is_postiz_online = true;
            $postiz_port = 4007;
            fclose($fp3);
        }
    }
}

$http_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$is_local = in_array($http_host, ['localhost:8000', '127.0.0.1:8000', 'localhost', '127.0.0.1']);

if ($is_local) {
    $postiz_url = 'http://localhost:' . $postiz_port;
} else {
    if (defined('POSTIZ_PUBLIC_URL') && POSTIZ_PUBLIC_URL) {
        $postiz_url = POSTIZ_PUBLIC_URL;
    } else {
        $is_ssl = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                  ($_SERVER['SERVER_PORT'] ?? 0) == 443 ||
                  (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
                  (!in_array($http_host, ['localhost', '127.0.0.1', 'localhost:8000', '127.0.0.1:8000']));
        $scheme = $is_ssl ? 'https://' : 'http://';
        $domain = preg_replace('/^crm\./', '', $http_host);
        $postiz_url = $scheme . 'social.' . $domain;
    }
}

include 'header.php';
?>

<style>
.postiz-container {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-top: 0;
    padding: 0 4px;
}

.postiz-topbar {
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

.postiz-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
}

.status-online {
    background: rgba(99, 102, 241, 0.15);
    color: #a5b4fc;
    border: 1px solid rgba(99, 102, 241, 0.35);
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

.pulse-indigo {
    background: #6366f1;
    box-shadow: 0 0 0 0 rgba(99, 102, 241, 0.7);
    animation: postizPulse 2s infinite;
}

.pulse-red {
    background: #ef4444;
}

@keyframes postizPulse {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(99, 102, 241, 0.7); }
    70% { transform: scale(1.05); box-shadow: 0 0 0 7px rgba(99, 102, 241, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(99, 102, 241, 0); }
}

.postiz-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.postiz-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 8px;
    font-size: 12.5px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s ease;
    border: none;
}

.btn-postiz-brand {
    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(99, 102, 241, 0.3);
}

.btn-postiz-brand:hover {
    background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
    color: #ffffff;
}

.btn-postiz-outline {
    background: rgba(255, 255, 255, 0.05);
    color: #e2e8f0;
    border: 1px solid rgba(255, 255, 255, 0.12);
}

.btn-postiz-outline:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #ffffff;
    border-color: rgba(255, 255, 255, 0.25);
}

.postiz-viewport {
    width: 100%;
    height: calc(100vh - 160px);
    min-height: 650px;
    background: #0f172a;
    border-radius: 14px;
    border: 1px solid #1e293b;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0,0,0,0.25);
    position: relative;
}

.postiz-iframe {
    width: 100%;
    height: 100%;
    border: none;
    display: block;
    background: #ffffff;
}

.postiz-offline-card {
    padding: 50px 20px;
    text-align: center;
    max-width: 560px;
    margin: 60px auto;
    background: #0f172a;
    border-radius: 16px;
    border: 1px solid #1e293b;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}
</style>

<div class="postiz-container">
    <!-- Top Hub Action Bar -->
    <div class="postiz-topbar">
        <div style="display:flex; align-items:center; gap:12px;">
            <div style="width:36px; height:36px; border-radius:10px; background:linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color:#fff; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <i data-lucide="share-2" style="width:20px; height:20px;"></i>
            </div>
            <div>
                <div style="font-size:14px; font-weight:800; color:#ffffff; line-height:1.2;">BFS Circle Postiz Social Hub</div>
                <div style="font-size:11.5px; color:#94a3b8; font-weight:500;">Multi-Channel AI Social Scheduler • Port: <?php echo $postiz_port; ?></div>
            </div>
        </div>

        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
            <div class="postiz-status-badge <?php echo $is_postiz_online ? 'status-online' : 'status-offline'; ?>">
                <span class="status-pulse <?php echo $is_postiz_online ? 'pulse-indigo' : 'pulse-red'; ?>"></span>
                <span><?php echo $is_postiz_online ? 'Postiz Engine Online' : 'Engine Offline (Port ' . $postiz_port . ')'; ?></span>
            </div>

            <div class="postiz-actions">
                <a href="<?php echo htmlspecialchars($postiz_url); ?>" target="_blank" class="postiz-btn btn-postiz-brand" title="Open in Dedicated Full Window">
                    <i data-lucide="external-link" style="width:13px; height:13px;"></i>
                    <span>Open in Full Tab</span>
                </a>
                <button type="button" onclick="reloadPostizFrame()" class="postiz-btn btn-postiz-outline" title="Refresh Postiz View">
                    <i data-lucide="refresh-cw" style="width:13px; height:13px;"></i>
                    <span>Refresh</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Main Viewport (Direct Native Postiz Application) -->
    <div class="postiz-viewport">
        <?php if ($is_postiz_online): ?>
            <iframe id="postizIframe" class="postiz-iframe" src="<?php echo htmlspecialchars($postiz_url); ?>" allow="camera; microphone; clipboard-read; clipboard-write; display-capture;"></iframe>
        <?php else: ?>
            <div class="postiz-offline-card">
                <div style="width:54px; height:54px; border-radius:16px; background:rgba(239,68,68,0.15); color:#ef4444; display:flex; align-items:center; justify-content:center; margin:0 auto 16px;">
                    <i data-lucide="power-off" style="width:28px; height:28px;"></i>
                </div>
                <h3 style="font-size:18px; font-weight:800; color:#ffffff; margin-bottom:8px;">Postiz Social Engine is Offline</h3>
                <p style="font-size:13px; color:#94a3b8; line-height:1.5; margin-bottom:16px;">
                    The Postiz Next/Nest service (Port 4200) is currently not running. You can start it using your terminal:
                </p>
                <div style="background:#090d16; color:#a5b4fc; font-family:monospace; padding:12px 16px; border-radius:8px; font-size:12.5px; text-align:left; margin-bottom:20px; border-left:3px solid #6366f1;">
                    cd E:\Downloads\postiz-main<br>
                    pnpm run dev
                </div>
                <button type="button" onclick="location.reload()" class="postiz-btn btn-postiz-brand" style="margin:0 auto; padding:10px 24px;">
                    <i data-lucide="refresh-cw"></i> Check Status Again
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function reloadPostizFrame() {
    const frame = document.getElementById('postizIframe');
    if (frame) {
        const base = frame.src.split('?')[0];
        frame.src = base + '?_t=' + Date.now();
    } else {
        location.reload();
    }
}
</script>

<?php include 'footer.php'; ?>
