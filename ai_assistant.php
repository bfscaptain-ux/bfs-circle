<?php
require_once __DIR__ . '/config.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$is_admin = ($_SESSION['role'] ?? '') === 'Admin';
$current_page = 'ai_assistant.php';
$page_title = 'BFS Circle AI Command Center';
$page_subtitle = 'Enterprise Ollama LLM Intelligence • Live CRM Knowledge Base Aggregator';
require_once __DIR__ . '/header.php';
?>

<!-- Include Marked.js for Enterprise Markdown Rendering -->
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>

<style>
/* Hide Default Header Title to Remove Duplicate Banner Clutter */
.main-header { display: none !important; }

/* Production-Grade Executive AI Layout */
.ai-page-wrapper {
    width: 100%;
    max-width: 1320px;
    margin: 0 auto;
    padding: 0 12px 20px 12px;
    box-sizing: border-box;
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
    color: #0f172a;
}

/* Single Unified Hero Banner */
.ai-top-banner {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    border-radius: 16px;
    padding: 20px 24px;
    color: #ffffff;
    margin-bottom: 16px;
    box-shadow: 0 10px 28px rgba(15, 23, 42, 0.14);
    border: 1px solid rgba(255,255,255,0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 14px;
}

.ai-top-title {
    font-size: 22px;
    font-weight: 800;
    color: #ffffff;
    display: flex;
    align-items: center;
    gap: 12px;
    letter-spacing: -0.3px;
}

.ai-top-sub {
    font-size: 13px;
    color: #94a3b8;
    margin-top: 4px;
    font-weight: 500;
}

.ai-engine-badge {
    background: rgba(251, 191, 36, 0.12);
    color: #fbbf24;
    border: 1px solid rgba(251, 191, 36, 0.35);
    padding: 7px 16px;
    border-radius: 30px;
    font-size: 12px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 2px 8px rgba(251, 191, 36, 0.1);
}

.ai-live-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #10b981;
    display: inline-block;
    box-shadow: 0 0 10px #10b981;
}

/* Quick Suggestion Chips Grid */
.ai-chips-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 12px;
    margin-bottom: 16px;
}

.ai-chip-card {
    background: #ffffff;
    border: 1.5px solid #e2e8f0;
    border-radius: 12px;
    padding: 12px 16px;
    cursor: pointer;
    transition: all 0.22s ease-in-out;
    display: flex;
    align-items: center;
    gap: 12px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.02);
}

.ai-chip-card:hover {
    border-color: #0f172a;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08);
    background: #f8fafc;
}

.chip-icon-wrapper {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: #f1f5f9;
    color: #0f172a;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: all 0.2s;
}

.ai-chip-card:hover .chip-icon-wrapper {
    background: #0f172a;
    color: #fbbf24;
}

.chip-title-text {
    font-size: 12.5px;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.3;
}

/* Main Chat Container Card */
.ai-chat-card {
    background: #ffffff;
    border: 1.5px solid #cbd5e1;
    border-radius: 18px;
    height: calc(100vh - 210px);
    min-height: 560px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    box-shadow: 0 8px 30px rgba(15, 23, 42, 0.05);
}

/* Chat Messages Scroll Window */
.chat-body-scroll {
    flex: 1;
    padding: 24px;
    overflow-y: auto;
    background: #f8fafc;
    display: flex;
    flex-direction: column;
    gap: 18px;
}

/* Message Rows */
.msg-container-row {
    display: flex;
    gap: 12px;
    max-width: 92%;
}

.msg-container-row.user {
    align-self: flex-end;
    flex-direction: row-reverse;
}

.msg-container-row.ai {
    align-self: flex-start;
}

.avatar-badge-icon {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 12px;
    flex-shrink: 0;
    box-shadow: 0 3px 8px rgba(0,0,0,0.1);
}

.msg-container-row.user .avatar-badge-icon {
    background: #0f172a;
    color: #ffffff;
}

.msg-container-row.ai .avatar-badge-icon {
    background: #1e293b;
    color: #fbbf24;
    border: 1.5px solid #f59e0b;
}

.msg-bubble-box {
    padding: 16px 20px;
    border-radius: 16px;
    font-size: 14px;
    line-height: 1.6;
    word-break: break-word;
}

.msg-container-row.user .msg-bubble-box {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    color: #ffffff;
    border-top-right-radius: 4px;
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.15);
}

.msg-container-row.ai .msg-bubble-box {
    background: #ffffff;
    color: #0f172a;
    border: 1.5px solid #e2e8f0;
    border-top-left-radius: 4px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.03);
}

.msg-time-stamp {
    font-size: 11px;
    color: #94a3b8;
    margin-top: 6px;
    font-weight: 500;
}

.msg-container-row.user .msg-time-stamp { text-align: right; }
.msg-container-row.ai .msg-time-stamp { text-align: left; }

/* Production Markdown Tables */
.table-responsive-box {
    width: 100%;
    overflow-x: auto;
    margin: 14px 0;
    border-radius: 12px;
    border: 1.5px solid #cbd5e1;
    box-shadow: 0 3px 10px rgba(0,0,0,0.03);
    background: #ffffff;
}

.executive-data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    background: #ffffff;
    text-align: left;
}

.executive-data-table th {
    background: #0f172a;
    color: #f8fafc;
    font-weight: 700;
    padding: 11px 16px;
    border-bottom: 2px solid #0284c7;
    font-size: 11.5px;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    white-space: nowrap;
}

.executive-data-table td {
    padding: 10px 16px;
    border-bottom: 1px solid #e2e8f0;
    color: #1e293b;
    vertical-align: middle;
    white-space: nowrap;
}

.executive-data-table tr:last-child td { border-bottom: none; }
.executive-data-table tr:nth-child(even) { background: #f8fafc; }
.executive-data-table tr:hover { background: #f1f5f9; }

/* Status Badges in Tables */
.badge-pill-status {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    text-transform: capitalize;
}
.badge-pill-status.status-success { background: #dcfce7; color: #15803d; border: 1px solid #86efac; }
.badge-pill-status.status-danger { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }
.badge-pill-status.status-info { background: #e0f2fe; color: #0369a1; border: 1px solid #7dd3fc; }
.badge-pill-status.status-warning { background: #fef3c7; color: #b45309; border: 1px solid #fcd34d; }

/* Markdown Content Elements */
.msg-bubble-box h1, .msg-bubble-box h2, .msg-bubble-box h3, .msg-bubble-box h4 {
    color: #0f172a;
    font-weight: 800;
    margin: 12px 0 6px 0;
    letter-spacing: -0.2px;
}

.msg-bubble-box ul, .msg-bubble-box ol {
    margin: 8px 0;
    padding-left: 22px;
}

.msg-bubble-box li {
    margin-bottom: 4px;
}

.msg-bubble-box p {
    margin: 6px 0;
}

/* Typing Dots Indicator */
.dots-typing-indicator { display: inline-flex; gap: 4px; align-items: center; }
.dot-typing { width: 6px; height: 6px; background: #64748b; border-radius: 50%; animation: pulseDots 1.4s infinite ease-in-out both; }
.dot-typing:nth-child(1) { animation-delay: -0.32s; }
.dot-typing:nth-child(2) { animation-delay: -0.16s; }
@keyframes pulseDots { 0%, 80%, 100% { opacity: 0.2; } 40% { opacity: 1; } }

/* Bottom Chat Input Controls */
.chat-bottom-bar {
    padding: 16px 20px;
    background: #ffffff;
    border-top: 1.5px solid #e2e8f0;
    display: flex;
    gap: 12px;
    align-items: center;
}

.chat-input-field {
    flex: 1;
    padding: 13px 20px;
    border: 1.5px solid #cbd5e1;
    border-radius: 30px;
    font-size: 14px;
    font-weight: 600;
    color: #0f172a;
    outline: none;
    background: #f8fafc;
    transition: all 0.2s;
}

.chat-input-field:focus {
    border-color: #0f172a;
    background: #ffffff;
    box-shadow: 0 0 0 4px rgba(15,23,42,0.08);
}

.btn-ask-ai {
    background: #0f172a;
    color: #ffffff;
    border: none;
    padding: 13px 24px;
    border-radius: 30px;
    font-weight: 700;
    font-size: 13.5px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s;
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.15);
}

.btn-ask-ai:hover { background: #1e293b; transform: translateY(-1px); }
.btn-ask-ai:disabled { background: #94a3b8; cursor: not-allowed; transform: none; box-shadow: none; }

.btn-clear-chat-history {
    background: #f1f5f9;
    color: #475569;
    border: 1.5px solid #cbd5e1;
    border-radius: 30px;
    padding: 11px 18px;
    font-weight: 600;
    font-size: 12.5px;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-clear-chat-history:hover { background: #e2e8f0; color: #0f172a; }
</style>

<div class="ai-page-wrapper">

    <!-- Unified Single Hero Header -->
    <div class="ai-top-banner">
        <div>
            <div class="ai-top-title">
                <i data-lucide="sparkles" style="color:#fbbf24; width:26px; height:26px;"></i>
                BFS Circle AI Command Center
            </div>
            <div class="ai-top-sub">Enterprise Ollama LLM • 360-Degree Live CRM Knowledge Base Aggregator</div>
        </div>
        <div class="ai-engine-badge">
            <span class="ai-live-dot"></span>
            Ollama Engine (`llama3.2:3b`) Active
        </div>
    </div>

    <!-- Quick Suggestion Chips -->
    <div class="ai-chips-grid">
        <div class="ai-chip-card" onclick="sendQuickPrompt('Kin kin logo ki file hai unki naam batao kitne logo ki loan file humare pass chal rahi hai?')">
            <div class="chip-icon-wrapper"><i data-lucide="users" style="width:18px;"></i></div>
            <div class="chip-title-text">📋 Active Applicants & File Names</div>
        </div>
        <div class="ai-chip-card" onclick="sendQuickPrompt('refrell kon kon h humare ?')">
            <div class="chip-icon-wrapper"><i data-lucide="handshake" style="width:18px;"></i></div>
            <div class="chip-title-text">🤝 Referral Partners & DSA Directory</div>
        </div>
        <div class="ai-chip-card" onclick="sendQuickPrompt('What is our current Fund Flow available balance, total received and total disbursed?')">
            <div class="chip-icon-wrapper"><i data-lucide="coins" style="width:18px;"></i></div>
            <div class="chip-title-text">💰 Fund Flow Balance & Transactions</div>
        </div>
        <div class="ai-chip-card" onclick="sendQuickPrompt('Show live summary of all Loan Applications, Disbursed volume and Stalled files.')">
            <div class="chip-icon-wrapper"><i data-lucide="bar-chart-3" style="width:18px;"></i></div>
            <div class="chip-title-text">📊 Stalled Files & SLA Summary</div>
        </div>
    </div>

    <!-- Main Chat Window -->
    <div class="ai-chat-card">
        <div class="chat-body-scroll" id="chatMessages">
            
            <!-- AI Welcome Message -->
            <div class="msg-container-row ai">
                <div class="avatar-badge-icon">AI</div>
                <div>
                    <div class="msg-bubble-box">
                        👋 Namaste <b><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></b>! Main aapka <b>BFS Circle AI Command Assistant</b> hoon.<br><br>
                        Main aapke poore CRM (Applicants, Staff RMs, Bankers, Referrals/DSAs, Fund Flow, HRMS) ke <b>Live 360-Degree Data</b> se judha hoon.<br><br>
                        Aap mujhse kisi bhi person, file ID, DSA partner, status ya fund report ke baare mein pooch sakte hain!
                    </div>
                    <div class="msg-time-stamp"><?php echo date('h:i A'); ?></div>
                </div>
            </div>

        </div>

        <!-- Chat Input Form -->
        <form class="chat-bottom-bar" onsubmit="handleChatSubmit(event)">
            <input type="text" id="aiQueryInput" class="chat-input-field" placeholder="Ask BFS AI anything about your CRM... (e.g. Applicant names, DSA referrals, fund balance)" required autocomplete="off">
            <button type="button" class="btn-clear-chat-history" onclick="clearChatHistory()" title="Clear Chat">
                <i data-lucide="trash-2" style="width:14px; vertical-align:middle;"></i> Clear
            </button>
            <button type="submit" id="btnSendAi" class="btn-ask-ai">
                <i data-lucide="send" style="width:15px;"></i> Ask AI
            </button>
        </form>
    </div>

</div>

<script>
// Format Markdown text & tables into production HTML
function renderFormattedMarkdown(text) {
    if (!text) return '';

    // If marked library is available, use marked with custom table rendering
    if (typeof marked !== 'undefined' && typeof marked.parse === 'function') {
        try {
            let parsedHtml = marked.parse(text);
            // Wrap any <table> in table-responsive-box and style table
            parsedHtml = parsedHtml.replace(/<table>/g, '<div class="table-responsive-box"><table class="executive-data-table">');
            parsedHtml = parsedHtml.replace(/<\/table>/g, '</table></div>');
            
            // Format Status keywords inside table cells into crisp badges
            parsedHtml = parsedHtml.replace(/<td>(Completed|Disbursed|Active)<\/td>/gi, '<td><span class="badge-pill-status status-success">$1</span></td>');
            parsedHtml = parsedHtml.replace(/<td>(Rejected|Stalled|Inactive)<\/td>/gi, '<td><span class="badge-pill-status status-danger">$1</span></td>');
            parsedHtml = parsedHtml.replace(/<td>(Phase \d|New)<\/td>/gi, '<td><span class="badge-pill-status status-info">$1</span></td>');
            parsedHtml = parsedHtml.replace(/<td>(Pending|Under Review|Incomplete)<\/td>/gi, '<td><span class="badge-pill-status status-warning">$1</span></td>');
            
            return parsedHtml;
        } catch(e) {
            console.warn("Marked parser fallback triggered:", e);
        }
    }

    // High-performance fallback Markdown parser
    let lines = text.split('\n');
    let inTable = false;
    let tableBuffer = [];
    let outLines = [];

    for (let i = 0; i < lines.length; i++) {
        let line = lines[i].trim();
        if (line.startsWith('|') && line.endsWith('|')) {
            if (line.replace(/[\s\|\-\:]/g, '') === '') {
                // Ignore separator line like |---|---|
                continue;
            }
            let cells = line.split('|').map(c => c.trim()).slice(1, -1);
            if (!inTable) {
                inTable = true;
                tableBuffer.push('<div class="table-responsive-box"><table class="executive-data-table"><thead><tr>');
                cells.forEach(c => tableBuffer.push(`<th>${c}</th>`));
                tableBuffer.push('</tr></thead><tbody>');
            } else {
                tableBuffer.push('<tr>');
                cells.forEach(c => {
                    let cellVal = c;
                    if (/^(Completed|Disbursed|Active)$/i.test(c)) cellVal = `<span class="badge-pill-status status-success">${c}</span>`;
                    else if (/^(Rejected|Stalled|Inactive)$/i.test(c)) cellVal = `<span class="badge-pill-status status-danger">${c}</span>`;
                    else if (/^(Phase \d|New)$/i.test(c)) cellVal = `<span class="badge-pill-status status-info">${c}</span>`;
                    else if (/^(Pending|Under Review|Incomplete)$/i.test(c)) cellVal = `<span class="badge-pill-status status-warning">${c}</span>`;
                    tableBuffer.push(`<td>${cellVal}</td>`);
                });
                tableBuffer.push('</tr>');
            }
        } else {
            if (inTable) {
                inTable = false;
                tableBuffer.push('</tbody></table></div>');
                outLines.push(tableBuffer.join(''));
                tableBuffer = [];
            }
            outLines.push(line);
        }
    }
    if (inTable) {
        tableBuffer.push('</tbody></table></div>');
        outLines.push(tableBuffer.join(''));
    }

    let result = outLines.join('\n');

    // Standard markdown replacements
    result = result.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    result = result.replace(/^\*\s+(.*)$/gm, '<li>$1</li>');
    result = result.replace(/^\-\s+(.*)$/gm, '<li>$1</li>');
    result = result.replace(/(<li>.*<\/li>)/s, '<ul>$1</ul>');
    result = result.replace(/\n/g, '<br>');

    return result;
}

function appendChatMessage(sender, text, timeStr) {
    const chatContainer = document.getElementById('chatMessages');
    const isUser = sender === 'user';
    const row = document.createElement('div');
    row.className = `msg-container-row ${isUser ? 'user' : 'ai'}`;

    const formattedContent = isUser ? text.replace(/\n/g, '<br>') : renderFormattedMarkdown(text);

    row.innerHTML = `
        <div class="avatar-badge-icon">${isUser ? 'ME' : 'AI'}</div>
        <div>
            <div class="msg-bubble-box">${formattedContent}</div>
            <div class="msg-time-stamp">${timeStr || new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</div>
        </div>
    `;
    chatContainer.appendChild(row);
    chatContainer.scrollTop = chatContainer.scrollHeight;
}

function showAiTypingIndicator() {
    const chatContainer = document.getElementById('chatMessages');
    const row = document.createElement('div');
    row.id = 'aiTypingIndicatorRow';
    row.className = 'msg-container-row ai';
    row.innerHTML = `
        <div class="avatar-badge-icon">AI</div>
        <div>
            <div class="msg-bubble-box" style="background:#ffffff; color:#64748b;">
                <span style="font-size:12.5px; font-weight:600; margin-right:8px;">BFS AI is querying Live 360° Knowledge Base...</span>
                <span class="dots-typing-indicator">
                    <span class="dot-typing"></span>
                    <span class="dot-typing"></span>
                    <span class="dot-typing"></span>
                </span>
            </div>
        </div>
    `;
    chatContainer.appendChild(row);
    chatContainer.scrollTop = chatContainer.scrollHeight;
}

function removeAiTypingIndicator() {
    const el = document.getElementById('aiTypingIndicatorRow');
    if (el) el.remove();
}

function sendQuickPrompt(promptText) {
    const input = document.getElementById('aiQueryInput');
    input.value = promptText;
    document.forms[0].dispatchEvent(new Event('submit', { cancelable: true }));
}

function handleChatSubmit(e) {
    e.preventDefault();
    const input = document.getElementById('aiQueryInput');
    const query = input.value.trim();
    if (!query) return;

    const sendBtn = document.getElementById('btnSendAi');
    sendBtn.disabled = true;

    appendChatMessage('user', query);
    input.value = '';

    showAiTypingIndicator();

    const fd = new FormData();
    fd.append('query', query);

    fetch('api.php?api=ai_chat_assistant', { method: 'POST', body: fd })
    .then(async r => {
        const rawText = await r.text();
        try {
            return JSON.parse(rawText);
        } catch(e) {
            console.error("RAW API RESPONSE:", rawText);
            let cleaned = rawText.substring(rawText.indexOf('{'));
            try { return JSON.parse(cleaned); } catch(e2) { throw new Error("Invalid response format from server"); }
        }
    })
    .then(d => {
        removeAiTypingIndicator();
        sendBtn.disabled = false;
        if (d.success) {
            appendChatMessage('ai', d.answer, d.timestamp);
        } else {
            appendChatMessage('ai', `⚠️ <b>Error:</b> ${d.error || 'Ollama LLM non-responsive. Ensure Ollama service is active.'}`);
        }
    })
    .catch(err => {
        removeAiTypingIndicator();
        sendBtn.disabled = false;
        appendChatMessage('ai', `⚠️ <b>Server Error:</b> ${err.message || 'Could not communicate with Ollama backend service.'}`);
    });
}

function clearChatHistory() {
    const chatContainer = document.getElementById('chatMessages');
    chatContainer.innerHTML = `
        <div class="msg-container-row ai">
            <div class="avatar-badge-icon">AI</div>
            <div>
                <div class="msg-bubble-box">Chat history cleared. How can I assist you with CRM Knowledge Base?</div>
                <div class="msg-time-stamp">${new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</div>
            </div>
        </div>
    `;
}

document.addEventListener("DOMContentLoaded", () => {
    if (window.lucide) lucide.createIcons();
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
