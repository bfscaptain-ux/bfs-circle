<?php
/**
 * omni_channel_bar.php
 * Official BFS Circle Omni-Channel Unified Communication Header & Dispatcher
 * Seamlessly connects WhatsApp CRM, SMS Captain Gateway, and Email Campaign Mailer.
 */

if (!defined('BASE_PATH')) {
    $base_path = '';
}

// 1. Detect Channel Statuses in real time
$oc_wa_online = false;
$oc_sms_online = false;
$oc_lm_online = false;

// WhatsApp Check (Port 3001)
$fp = @fsockopen('127.0.0.1', 3001, $errno, $errstr, 0.15);
if ($fp) { $oc_wa_online = true; fclose($fp); }

// SMS Captain Check (Port 3006 API or 3005 Web)
$fp1 = @fsockopen('127.0.0.1', 3006, $errno, $errstr, 0.15);
$fp2 = @fsockopen('127.0.0.1', 3005, $errno, $errstr, 0.15);
if ($fp1 || $fp2) {
    $oc_sms_online = true;
    if ($fp1) fclose($fp1);
    if ($fp2) fclose($fp2);
}

// Email Mailer Check (Port 9000)
$fp = @fsockopen('127.0.0.1', 9000, $errno, $errstr, 0.15);
if ($fp) { $oc_lm_online = true; fclose($fp); }

// Current active channel
$current_script = basename($_SERVER['SCRIPT_NAME'] ?? '');

$http_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$is_local = in_array($http_host, ['localhost:8000', '127.0.0.1:8000', 'localhost', '127.0.0.1']);
$domain = preg_replace('/^crm\./', '', $http_host);
$scheme = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 0) == 443) ? 'https://' : 'http://';

$wa_external_url = $is_local ? 'http://localhost:3001' : $scheme . 'wa.' . $domain;
$sms_external_url = $is_local ? 'http://localhost:3005/dashboard' : $scheme . 'sms.' . $domain . '/dashboard';
$lm_external_url = $is_local ? 'http://localhost:9000/admin' : $scheme . 'mailer.' . $domain . '/admin';

$current_external_url = $wa_external_url;
if ($current_script === 'sms_crm.php') {
    $current_external_url = $sms_external_url;
} else if ($current_script === 'marketing.php') {
    $current_external_url = $lm_external_url;
}
?>

<style>
/* BFS Omni-Channel Master Bar Styling */
.bfs-omni-bar {
    background: #090d16;
    border: 1px solid #1e293b;
    border-radius: 14px;
    padding: 10px 16px;
    margin-bottom: 14px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.35);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    color: #f8fafc;
}

.bfs-omni-tabs {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.bfs-omni-tab {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    padding: 7px 14px;
    border-radius: 10px;
    text-decoration: none;
    font-size: 12.5px;
    font-weight: 700;
    color: #94a3b8;
    background: #0f172a;
    border: 1px solid #1e293b;
    transition: all 0.2s ease;
    cursor: pointer;
}

.bfs-omni-tab:hover {
    color: #ffffff;
    background: #1e293b;
    border-color: #334155;
    transform: translateY(-1px);
}

.bfs-omni-tab.active-wa {
    color: #ffffff;
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.3));
    border-color: #10b981;
    box-shadow: 0 0 14px rgba(16, 185, 129, 0.25);
}

.bfs-omni-tab.active-sms {
    color: #ffffff;
    background: linear-gradient(135deg, rgba(14, 165, 233, 0.2), rgba(2, 132, 199, 0.3));
    border-color: #0ea5e9;
    box-shadow: 0 0 14px rgba(14, 165, 233, 0.25);
}

.bfs-omni-tab.active-mail {
    color: #ffffff;
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(220, 38, 38, 0.3));
    border-color: #ef4444;
    box-shadow: 0 0 14px rgba(239, 68, 68, 0.25);
}

.omni-pulse {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
}

.omni-pulse-green {
    background: #10b981;
    box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
    animation: omniPulseAnim 2s infinite;
}

.omni-pulse-red {
    background: #ef4444;
}

@keyframes omniPulseAnim {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
    70% { transform: scale(1.05); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}

.bfs-omni-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.omni-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 13px;
    border-radius: 9px;
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.15s ease;
}

.btn-omni-dispatch {
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    color: #ffffff;
    border: 1px solid #818cf8;
    box-shadow: 0 2px 10px rgba(99, 102, 241, 0.35);
}
.btn-omni-dispatch:hover {
    background: linear-gradient(135deg, #4f46e5, #4338ca);
    transform: translateY(-1px);
}

.btn-omni-sync {
    background: #1e293b;
    color: #e2e8f0;
    border: 1px solid #334155;
}
.btn-omni-sync:hover {
    background: #334155;
    color: #ffffff;
}

.btn-omni-open {
    background: #0f172a;
    color: #94a3b8;
    border: 1px solid #1e293b;
}
.btn-omni-open:hover {
    background: #1e293b;
    color: #ffffff;
}

/* Modal Styling */
.omni-modal-backdrop {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.75);
    backdrop-filter: blur(5px);
    z-index: 999999;
    align-items: center;
    justify-content: center;
    padding: 16px;
}

.omni-modal-card {
    background: #0b1120;
    border: 1px solid #334155;
    border-radius: 18px;
    width: 100%;
    max-width: 580px;
    max-height: 90vh;
    overflow-y: auto;
    padding: 24px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7);
    color: #f8fafc;
    font-family: 'Inter', sans-serif;
}

.omni-field-label {
    display: block;
    font-size: 11.5px;
    font-weight: 700;
    color: #cbd5e1;
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.omni-input {
    width: 100%;
    background: #1e293b;
    border: 1px solid #334155;
    border-radius: 9px;
    padding: 9px 12px;
    color: #ffffff;
    font-size: 13px;
    outline: none;
    box-sizing: border-box;
}
.omni-input:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.25);
}

.channel-pill-checkbox {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #1e293b;
    border: 1px solid #334155;
    padding: 8px 12px;
    border-radius: 10px;
    cursor: pointer;
    user-select: none;
    transition: all 0.15s ease;
    flex: 1;
}
.channel-pill-checkbox input {
    cursor: pointer;
    accent-color: #6366f1;
}
.channel-pill-checkbox:hover {
    background: #273549;
}
</style>

<!-- BFS Master Omni-Channel Control Header -->
<div class="bfs-omni-bar">
    <!-- Channel Switcher Tabs -->
    <div class="bfs-omni-tabs">
        <div style="display:flex; align-items:center; gap:8px; margin-right:8px; padding-right:12px; border-right:1px solid #1e293b;">
            <div style="width:28px; height:28px; border-radius:7px; background:linear-gradient(135deg, #6366f1, #3b82f6); display:flex; align-items:center; justify-content:center; color:#fff;">
                <i data-lucide="radio" style="width:15px; height:15px;"></i>
            </div>
            <span style="font-size:12px; font-weight:800; color:#cbd5e1; letter-spacing:0.5px;">OMNI-HUB</span>
        </div>

        <!-- 1. WhatsApp Tab -->
        <a href="<?php echo $base_path; ?>whatsapp_crm.php" class="bfs-omni-tab <?php echo ($current_script === 'whatsapp_crm.php') ? 'active-wa' : ''; ?>" title="Port 3001: Integrated WhatsApp Baileys & Cloud API Hub">
            <i data-lucide="message-circle" style="width:15px; height:15px; color:#34d399;"></i>
            <span>WhatsApp CRM</span>
            <span class="omni-pulse <?php echo $oc_wa_online ? 'omni-pulse-green' : 'omni-pulse-red'; ?>" title="<?php echo $oc_wa_online ? 'Port 3001 Online' : 'Port 3001 Offline'; ?>"></span>
        </a>

        <!-- 2. SMS Gateway Tab -->
        <a href="<?php echo $base_path; ?>sms_crm.php" class="bfs-omni-tab <?php echo ($current_script === 'sms_crm.php') ? 'active-sms' : ''; ?>" title="Ports 3005/3006: SMS Captain Physical SIM Gateway">
            <i data-lucide="smartphone" style="width:15px; height:15px; color:#38bdf8;"></i>
            <span>SMS Captain</span>
            <span class="omni-pulse <?php echo $oc_sms_online ? 'omni-pulse-green' : 'omni-pulse-red'; ?>" title="<?php echo $oc_sms_online ? 'Ports 3005/3006 Online' : 'Ports 3005/3006 Offline'; ?>"></span>
        </a>

        <!-- 3. Email & Listmonk Mailer Tab -->
        <a href="<?php echo $base_path; ?>marketing.php" class="bfs-omni-tab <?php echo ($current_script === 'marketing.php') ? 'active-mail' : ''; ?>" title="Port 9000: BFS Circle Mailer & Campaign Engine">
            <i data-lucide="mail" style="width:15px; height:15px; color:#f87171;"></i>
            <span>Email Mailer</span>
            <span class="omni-pulse <?php echo $oc_lm_online ? 'omni-pulse-green' : 'omni-pulse-red'; ?>" title="<?php echo $oc_lm_online ? 'Port 9000 Online' : 'Port 9000 Offline'; ?>"></span>
        </a>

        <!-- 4. Direct Email Composer Link -->
        <a href="<?php echo $base_path; ?>send_email.php" class="bfs-omni-tab <?php echo ($current_script === 'send_email.php') ? 'active-mail' : ''; ?>" title="SMTP Transactional Mail Dispatcher">
            <i data-lucide="send" style="width:14px; height:14px; color:#94a3b8;"></i>
            <span>Direct SMTP</span>
        </a>
    </div>

    <!-- Right Actions -->
    <div class="bfs-omni-actions">
        <!-- Omni Quick Dispatch Button -->
        <button type="button" onclick="openOmniDispatchModal()" class="omni-btn btn-omni-dispatch" title="Send simultaneous or individual WhatsApp, SMS, and Email">
            <i data-lucide="zap" style="width:14px; height:14px;"></i>
            <span>⚡ Multi-Channel Dispatch</span>
        </button>

        <!-- CRM Contact Sync Across All Channels -->
        <button type="button" onclick="syncOmniContacts(this)" class="omni-btn btn-omni-sync" title="Sync CRM Leads & Applicants to WhatsApp, SMS, and Mailer lists">
            <i data-lucide="refresh-cw" style="width:13px; height:13px;"></i>
            <span id="omniSyncText">Sync Audience</span>
        </button>

        <!-- Open External Standalone App in New Window -->
        <a href="<?php echo htmlspecialchars($current_external_url); ?>" target="_blank" rel="noopener noreferrer" class="omni-btn btn-omni-open" title="Open standalone dashboard in external tab">
            <i data-lucide="external-link" style="width:13px; height:13px;"></i>
            <span>Full Window</span>
        </a>
    </div>
</div>

<!-- ========================================================================= -->
<!-- BFS UNIVERSAL OMNI-CHANNEL DISPATCH MODAL                                 -->
<!-- ========================================================================= -->
<div id="omniDispatchModal" class="omni-modal-backdrop">
    <div class="omni-modal-card">
        <!-- Modal Header -->
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #1e293b; padding-bottom:14px; margin-bottom:18px;">
            <div style="display:flex; align-items:center; gap:10px;">
                <div style="width:36px; height:36px; border-radius:10px; background:linear-gradient(135deg, #6366f1, #4f46e5); color:#fff; display:flex; align-items:center; justify-content:center; box-shadow:0 0 12px rgba(99,102,241,0.4);">
                    <i data-lucide="send" style="width:18px; height:18px;"></i>
                </div>
                <div>
                    <h3 style="font-size:16px; font-weight:800; color:#f8fafc; margin:0;">Omni-Channel Quick Dispatch</h3>
                    <p style="font-size:11.5px; color:#94a3b8; margin:0;">Send message across WhatsApp, SMS Captain, and Email</p>
                </div>
            </div>
            <button type="button" onclick="closeOmniDispatchModal()" style="background:none; border:none; color:#94a3b8; font-size:24px; cursor:pointer; line-height:1;">&times;</button>
        </div>

        <form id="omniDispatchForm" onsubmit="executeOmniDispatch(event)">
            <!-- 1. Select Active Channels -->
            <div style="margin-bottom:16px;">
                <label class="omni-field-label">1. Choose Communication Channels *</label>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <label class="channel-pill-checkbox">
                        <input type="checkbox" name="channels[]" value="whatsapp" id="ch_whatsapp" checked>
                        <i data-lucide="message-circle" style="width:16px; height:16px; color:#34d399;"></i>
                        <span style="font-size:12.5px; font-weight:700;">WhatsApp</span>
                    </label>

                    <label class="channel-pill-checkbox">
                        <input type="checkbox" name="channels[]" value="sms" id="ch_sms" checked>
                        <i data-lucide="smartphone" style="width:16px; height:16px; color:#38bdf8;"></i>
                        <span style="font-size:12.5px; font-weight:700;">SMS Captain</span>
                    </label>

                    <label class="channel-pill-checkbox">
                        <input type="checkbox" name="channels[]" value="email" id="ch_email">
                        <i data-lucide="mail" style="width:16px; height:16px; color:#f87171;"></i>
                        <span style="font-size:12.5px; font-weight:700;">Email</span>
                    </label>
                </div>
            </div>

            <!-- 2. Recipient Information -->
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:14px;">
                <div>
                    <label class="omni-field-label">Recipient Name</label>
                    <input type="text" name="recipient_name" id="omni_recipient_name" placeholder="e.g. Rahul Sharma" class="omni-input">
                </div>
                <div>
                    <label class="omni-field-label">Mobile Number *</label>
                    <input type="text" name="mobile" id="omni_mobile" required placeholder="e.g. 9258724227" value="9258724227" class="omni-input">
                </div>
            </div>

            <div style="margin-bottom:14px;">
                <label class="omni-field-label">Email Address (Optional)</label>
                <input type="email" name="email" id="omni_email" placeholder="e.g. rahul@example.com" class="omni-input">
            </div>

            <!-- 3. Pre-made Templates Dropdown -->
            <div style="margin-bottom:14px;">
                <label class="omni-field-label">Pre-Made Message Template</label>
                <select id="omni_template_picker" onchange="applyOmniTemplate(this.value)" class="omni-input" style="cursor:pointer;">
                    <option value="">-- Select a ready-to-use BFS template --</option>
                    <option value="welcome">⚡ Welcome & Loan File Logged In</option>
                    <option value="sanction">🎉 Loan In-Principle Sanctioned Alert</option>
                    <option value="disbursement">💰 Loan Disbursement Success Alert</option>
                    <option value="docs">📁 Document Pending Reminder</option>
                    <option value="otp">🔐 Verification OTP Code</option>
                    <option value="emi">💳 EMI Payment Due Reminder</option>
                    <option value="custom">✍️ Clear / Custom Message</option>
                </select>
            </div>

            <!-- 4. Email Subject (Visible when email selected) -->
            <div id="omni_subject_box" style="margin-bottom:14px; display:none;">
                <label class="omni-field-label">Email Subject</label>
                <input type="text" name="subject" id="omni_subject" value="Important Update from Bhardwaj Financial Services (BFS)" class="omni-input">
            </div>

            <!-- 5. Message Body -->
            <div style="margin-bottom:18px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                    <label class="omni-field-label" style="margin-bottom:0;">Message Body *</label>
                    <span id="omniCharCount" style="font-size:11px; color:#94a3b8;">0 chars • 1 SMS part</span>
                </div>
                <textarea name="message" id="omni_message" rows="4" required class="omni-input" oninput="updateOmniCharCount(this.value)" style="resize:vertical; font-family:inherit;">Namaste from Bhardwaj Financial Services (BFS). Your loan file has been received and is currently under underwriting review. Contact your Relationship Manager at 9258-724-227 for updates.</textarea>
            </div>

            <!-- Modal Action Buttons -->
            <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid #1e293b; pt-3; margin-top:14px;">
                <button type="button" onclick="closeOmniDispatchModal()" class="omni-btn btn-omni-open" style="padding:8px 18px;">Cancel</button>
                <button type="submit" id="btnOmniSubmit" class="omni-btn btn-omni-dispatch" style="padding:9px 24px; font-size:13px;">
                    <i data-lucide="send" style="width:14px; height:14px;"></i>
                    <span id="btnOmniSubmitText">⚡ Execute Multi-Channel Blast</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Templates Dictionary
const BFS_OMNI_TEMPLATES = {
    welcome: "Namaste! Your loan file has been successfully logged in with Bhardwaj Financial Services (BFS). Our credit team is processing your case. For live status call 9258-724-227.",
    sanction: "Congratulations! Your loan has received In-Principle Sanction Approval from our partner institution. Contact BFS at 9258-724-227 to collect your official sanction docket.",
    disbursement: "Dear Client, Your loan amount has been successfully disbursed! Thank you for choosing Bhardwaj Financial Services (BFS) as your trusted financial partner.",
    docs: "Dear Applicant, A few KYC/income documents are still pending to complete your loan processing. Please submit them to your BFS RM or call 9258-724-227 today.",
    otp: "Your Bhardwaj Financial Services verification OTP is 748291. Valid for 10 minutes for your loan inquiry. Please do not share this OTP with anyone.",
    emi: "Friendly reminder from BFS: Your upcoming loan EMI installment is scheduled for debited soon. Please ensure sufficient balance in your linked bank account.",
    custom: ""
};

function openOmniDispatchModal() {
    document.getElementById('omniDispatchModal').style.display = 'flex';
    if (window.lucide) lucide.createIcons();
}

function closeOmniDispatchModal() {
    document.getElementById('omniDispatchModal').style.display = 'none';
}

function applyOmniTemplate(key) {
    if (BFS_OMNI_TEMPLATES[key] !== undefined) {
        const msgEl = document.getElementById('omni_message');
        msgEl.value = BFS_OMNI_TEMPLATES[key];
        updateOmniCharCount(msgEl.value);
    }
}

function updateOmniCharCount(text) {
    const len = text.length;
    const parts = Math.ceil(len / 160) || 1;
    document.getElementById('omniCharCount').innerText = `${len} chars • ${parts} SMS part(s)`;
}

// Show/hide email subject when email checkbox changes
document.getElementById('ch_email').addEventListener('change', function() {
    document.getElementById('omni_subject_box').style.display = this.checked ? 'block' : 'none';
});

async function executeOmniDispatch(e) {
    e.preventDefault();
    const btn = document.getElementById('btnOmniSubmit');
    const btnText = document.getElementById('btnOmniSubmitText');
    
    // Check channels
    const chs = [];
    if (document.getElementById('ch_whatsapp').checked) chs.push('whatsapp');
    if (document.getElementById('ch_sms').checked) chs.push('sms');
    if (document.getElementById('ch_email').checked) chs.push('email');

    if (chs.length === 0) {
        alert('Please select at least one channel (WhatsApp, SMS, or Email).');
        return;
    }

    const mobile = document.getElementById('omni_mobile').value.trim();
    const email = document.getElementById('omni_email').value.trim();
    const recipientName = document.getElementById('omni_recipient_name').value.trim();
    const message = document.getElementById('omni_message').value.trim();
    const subject = document.getElementById('omni_subject').value.trim();

    btn.disabled = true;
    btnText.innerText = 'Dispatching across ' + chs.join(' + ') + '...';

    const formData = new FormData();
    formData.append('mobile', mobile);
    formData.append('email', email);
    formData.append('recipient_name', recipientName);
    formData.append('message', message);
    formData.append('subject', subject);
    chs.forEach(c => formData.append('channels[]', c));

    try {
        const res = await fetch('api.php?api=send_omni_api', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (res.ok && data.success) {
            let detailStr = '';
            if (data.channels) {
                for (const [ch, info] of Object.entries(data.channels)) {
                    detailStr += `\n• ${ch.toUpperCase()}: ${info.message || (info.success ? 'Success' : 'Failed')}`;
                }
            }
            alert('✅ Omni-Channel Dispatch Completed!' + detailStr);
            closeOmniDispatchModal();
        } else {
            alert('❌ Dispatch Failed: ' + (data.error || data.message || 'Server error'));
        }
    } catch (err) {
        alert('Server communication error during dispatch.');
    } finally {
        btn.disabled = false;
        btnText.innerText = '⚡ Execute Multi-Channel Blast';
    }
}

async function syncOmniContacts(btn) {
    const textEl = document.getElementById('omniSyncText');
    const origText = textEl.innerText;
    textEl.innerText = 'Syncing...';
    btn.disabled = true;

    try {
        const res = await fetch('api.php?api=listmonk_sync_all', { method: 'POST' });
        const data = await res.json();
        if (res.ok && data.success) {
            alert(`✅ Audience Synced! Synchronized ${data.total_synced || 0} CRM contacts across channels.`);
        } else {
            alert('Sync completed with warnings: ' + (data.error || 'Check server logs'));
        }
    } catch (e) {
        alert('Contact sync completed.');
    } finally {
        textEl.innerText = origText;
        btn.disabled = false;
    }
}

// Close modal on Escape
window.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeOmniDispatchModal();
});
</script>
