<?php
require_once 'config.php';
require_role(['Admin', 'RM']);

$hide_main_header = true; // Removes old clunky header bar
$page_title = 'Communication Center';
$page_subtitle = 'Executive Email Dispatch & Audit Hub';
require_once 'header.php';

// Get client_id or email if passed in URL
$preset_client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : '';
$preset_email = isset($_GET['email']) ? $_GET['email'] : '';
?>

<!-- TomSelect CSS & JS for lightning-fast searchable recipient picker -->
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>

<style>
/* Executive Clean & Productive Theme */
:root {
    --exec-dark: #0f172a;
    --exec-slate: #f8fafc;
    --exec-border: #e2e8f0;
    --exec-muted: #64748b;
    --exec-primary: #2563eb;
    --exec-emerald: #059669;
}

.comm-wrapper {
    width: 100%;
    margin: 0 auto;
    padding: 6px 4px 32px 4px;
}

/* Top Executive Header Bar */
.comm-top-bar {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 18px 24px;
    margin-bottom: 22px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.02);
}

.comm-title-box {
    display: flex;
    align-items: center;
    gap: 12px;
}

.comm-icon-badge {
    width: 42px;
    height: 42px;
    background: #0f172a;
    color: #ffffff;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 12px rgba(15,23,42,0.15);
}

/* Nav Switcher Tabs */
.comm-nav-pills {
    display: inline-flex;
    background: #f1f5f9;
    padding: 4px;
    border-radius: 10px;
    gap: 4px;
}

.comm-pill-btn {
    padding: 8px 18px;
    font-size: 13px;
    font-weight: 700;
    color: #475569;
    border-radius: 8px;
    border: none;
    background: transparent;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 7px;
}

.comm-pill-btn:hover {
    color: #0f172a;
}

.comm-pill-btn.active {
    background: #ffffff;
    color: #0f172a;
    box-shadow: 0 2px 6px rgba(0,0,0,0.06);
}

/* Delivery Engine Switcher */
.engine-switcher {
    display: inline-flex;
    align-items: center;
    background: #f8fafc;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 3px;
    gap: 4px;
}

.engine-btn {
    padding: 6px 12px;
    font-size: 11.5px;
    font-weight: 700;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    background: transparent;
    color: #64748b;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 5px;
}

.engine-btn.active {
    background: #0f172a;
    color: #ffffff;
    box-shadow: 0 2px 6px rgba(15,23,42,0.15);
}

/* 2-Column Productivity Grid */
.comm-grid {
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 22px;
}

@media (max-width: 1100px) {
    .comm-grid {
        grid-template-columns: 1fr;
    }
}

/* Composer Card */
.composer-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 24px;
    box-shadow: 0 4px 20px -5px rgba(0,0,0,0.03);
}

/* Recipient Profile Banner */
.rec-pill-banner {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 12px 16px;
    margin-bottom: 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    animation: fadeIn 0.2s ease-out;
}

.rec-action-pill {
    padding: 5px 11px;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    color: #334155;
    border-radius: 6px;
    font-size: 11.5px;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.15s;
}

.rec-action-pill:hover {
    background: #0f172a;
    color: #ffffff;
    border-color: #0f172a;
}

/* Form Fields */
.comm-label {
    display: block;
    font-size: 12px;
    font-weight: 700;
    color: #334155;
    margin-bottom: 6px;
}

.comm-input {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 13.5px;
    color: #0f172a;
    outline: none;
    transition: all 0.15s;
    background: #ffffff;
    box-sizing: border-box;
}

.comm-input:focus {
    border-color: #0f172a;
    box-shadow: 0 0 0 3px rgba(15,23,42,0.08);
}

/* Quill Editor Container */
.quill-wrapper {
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    overflow: hidden;
    background: #ffffff;
}

.quill-wrapper .ql-toolbar {
    border: none;
    border-bottom: 1px solid #e2e8f0;
    background: #f8fafc;
    padding: 8px 12px;
}

.quill-wrapper .ql-container {
    border: none;
    font-family: inherit;
    font-size: 14px;
    min-height: 280px;
}

/* Right Sidebar / Helper Cards */
.side-tool-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 18px;
    margin-bottom: 18px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.02);
}

.side-tool-head {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13.5px;
    font-weight: 800;
    color: #0f172a;
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid #f1f5f9;
}

/* Quick Topic Pills */
.topic-pill {
    padding: 6px 11px;
    background: #f8fafc;
    border: 1px solid #cbd5e1;
    border-radius: 20px;
    font-size: 11.5px;
    font-weight: 700;
    color: #334155;
    cursor: pointer;
    transition: all 0.15s;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.topic-pill:hover {
    background: #0f172a;
    color: #ffffff;
    border-color: #0f172a;
}

/* Preset Action Item */
.preset-action-item {
    padding: 10px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #f8fafc;
    margin-bottom: 8px;
    cursor: pointer;
    transition: all 0.15s;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.preset-action-item:hover {
    border-color: #0f172a;
    background: #ffffff;
    box-shadow: 0 2px 6px rgba(0,0,0,0.04);
}

/* Shortcodes & Dynamic Merge Tags */
.shortcode-chip {
    padding: 4px 10px;
    background: #f8fafc;
    color: #0f172a;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 11.5px;
    font-weight: 700;
    cursor: pointer;
    user-select: none;
    transition: all 0.15s;
    font-family: 'Consolas', 'Monaco', monospace;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.shortcode-chip:hover {
    background: #0f172a;
    color: #ffffff;
    border-color: #0f172a;
}

/* TomSelect Styling Overrides */
.ts-control {
    padding: 9px 12px !important;
    border-radius: 8px !important;
    border-color: #cbd5e1 !important;
    font-size: 13.5px !important;
    font-weight: 600 !important;
    color: #0f172a !important;
}

.ts-dropdown {
    font-size: 13px !important;
    border-radius: 8px !important;
    box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important;
    border-color: #cbd5e1 !important;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-4px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<div class="comm-wrapper">

    <!-- Top Executive Header Bar -->
    <div class="comm-top-bar">
        <div class="comm-title-box">
            <div class="comm-icon-badge">
                <i data-lucide="mail" style="width: 22px; height: 22px;"></i>
            </div>
            <div>
                <h1 style="font-size: 20px; font-weight: 800; color: #0f172a; letter-spacing: -0.02em; margin: 0; display: flex; align-items: center; gap: 8px;">
                    Enterprise Communication Center
                </h1>
                <p style="margin: 2px 0 0 0; color: #64748b; font-size: 12.5px; font-weight: 500;">
                    AI-Assisted Email Dispatch, Client Tracking Tokens & Delivery Audit Records
                </p>
            </div>
        </div>

        <!-- Center Tabs -->
        <div class="comm-nav-pills">
            <button type="button" class="comm-pill-btn active" id="tabBtnCompose" onclick="switchCommTab('compose')">
                <i data-lucide="send" style="width: 14px; height: 14px;"></i> Compose & Dispatch
            </button>
            <button type="button" class="comm-pill-btn" id="tabBtnBulkCsv" onclick="switchCommTab('bulk_csv')">
                <i data-lucide="layers" style="width: 14px; height: 14px;"></i> Bulk CSV Campaign
            </button>
            <button type="button" class="comm-pill-btn" id="tabBtnInbox" onclick="switchCommTab('inbox')">
                <i data-lucide="inbox" style="width: 14px; height: 14px;"></i> Inbound Replies
                <span id="inboxUnreadBadge" style="display:none; background:#0f172a; color:#ffffff; font-size:10px; font-weight:700; padding:1px 6px; border-radius:10px; margin-left:4px;">0</span>
            </button>
            <button type="button" class="comm-pill-btn" id="tabBtnHistory" onclick="switchCommTab('history')">
                <i data-lucide="clock" style="width: 14px; height: 14px;"></i> Sent Mail Audit
            </button>
        </div>

        <!-- Right Controls -->
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <!-- Delivery Engine Mode -->
            <div class="engine-switcher">
                <button type="button" class="engine-btn active" id="modeSimulated" onclick="setDispatchMode('simulated')" title="Simulated Delivery Logs without active SMTP connection">
                    <i data-lucide="shield-check" style="width: 12px; height: 12px;"></i> Simulated
                </button>
                <button type="button" class="engine-btn" id="modeSmtp" onclick="setDispatchMode('smtp')" title="Real-time delivery using configured company SMTP">
                    <i data-lucide="globe" style="width: 12px; height: 12px;"></i> Live SMTP
                </button>
            </div>
        </div>
    </div>

    <!-- ============================================== -->
    <!-- TAB 1: 2-COLUMN EXECUTIVE COMPOSER WORKSPACE -->
    <!-- ============================================== -->
    <div id="tabContentCompose" style="display: block;">
        <div class="comm-grid">

            <!-- LEFT: MAIN COMPOSER -->
            <div class="composer-card">
                <form id="email-sender-form" onsubmit="dispatchEmail(event)" enctype="multipart/form-data">
                    <input type="hidden" name="dispatch_mode" id="dispatch_mode_input" value="simulated">

                    <!-- Recipient Selection Row -->
                    <div style="margin-bottom: 16px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                            <label class="comm-label" style="margin: 0;">
                                Select Recipient (Search Name, Mobile, or Loan ID) <span style="color: #ef4444;">*</span>
                            </label>
                            <button type="button" onclick="toggleCcDrawer()" style="background: none; border: none; font-size: 12px; font-weight: 700; color: #2563eb; cursor: pointer;">
                                + Add CC / Sender Options
                            </button>
                        </div>
                        <select name="client_id" id="email-to-select" required placeholder="Type to search clients, applicants, leads...">
                            <option value="">Loading accounts...</option>
                        </select>
                    </div>

                    <!-- Dynamic Recipient Snapshot Card (Auto-shows when selected) -->
                    <div id="recipientProfileCard" class="rec-pill-banner" style="display: none;">
                        <div>
                            <div style="font-weight: 800; font-size: 14px; color: #0f172a;" id="recCardName">Client Name</div>
                            <div style="font-size: 12px; color: #64748b; margin-top: 2px;" id="recCardDetails">
                                File #: - | Mobile: - | Email: -
                            </div>
                        </div>
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <a href="#" id="recWaBtn" target="_blank" class="rec-action-pill" style="color: #15803d; border-color: #bbf7d0; background: #f0fdf4;">
                                <i data-lucide="message-circle" style="width: 13px; height: 13px;"></i> WhatsApp
                            </a>
                            <button type="button" onclick="copyTrackingLink()" class="rec-action-pill">
                                <i data-lucide="link-2" style="width: 13px; height: 13px;"></i> Copy Track Link
                            </button>
                            <a href="#" id="recCallBtn" class="rec-action-pill">
                                <i data-lucide="phone" style="width: 13px; height: 13px;"></i> Call
                            </a>
                        </div>
                    </div>

                    <!-- Collapsible CC & Options Drawer -->
                    <div id="ccOptionsDrawer" style="display: none; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px; margin-bottom: 16px;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px;">
                            <div>
                                <label class="comm-label">CC Email Address(es)</label>
                                <input type="email" name="cc" placeholder="cc@company.com" class="comm-input" style="padding: 8px 12px; font-size: 13px;">
                            </div>
                            <div>
                                <label class="comm-label">Communication Category</label>
                                <select name="type" class="comm-input" style="padding: 8px 12px; font-size: 13px;">
                                    <option value="Custom Mail" selected>Custom Mail / Updates</option>
                                    <option value="Sanction Approval">Sanction Approval</option>
                                    <option value="Document Request">Document Pending Reminder</option>
                                    <option value="Disbursement Notice">Disbursement Confirmation</option>
                                    <option value="Quotation">Quotation / Pitch Proposal</option>
                                </select>
                            </div>
                            <div>
                                <label class="comm-label">Dispatched By</label>
                                <input type="text" name="sent_by" value="<?= htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?>" required class="comm-input" style="padding: 8px 12px; font-size: 13px;">
                            </div>
                        </div>
                    </div>

                    <!-- Subject Line -->
                    <div style="margin-bottom: 16px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                            <label class="comm-label" style="margin: 0;">
                                Subject Line <span style="color: #ef4444;">*</span>
                            </label>
                            <div style="display: flex; gap: 6px;">
                                <span class="topic-pill" onclick="applySubjectSuggestion('sanction')">+ Sanction Approved</span>
                                <span class="topic-pill" onclick="applySubjectSuggestion('docs')">+ Missing Docs</span>
                            </div>
                        </div>
                        <input type="text" name="subject" id="email-subject-input" placeholder="Enter clear, descriptive email subject..." required class="comm-input" style="font-weight: 600;">
                    </div>

                    <!-- Rich Text Editor (Quill.js) -->
                    <div style="margin-bottom: 16px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                            <label class="comm-label" style="margin: 0;">
                                Email Body Content (Rich Text HTML) <span style="color: #ef4444;">*</span>
                            </label>
                            <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                <button type="button" onclick="autoFillShortcodesInComposer()" class="rec-action-pill" style="font-size: 11px; background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe;" title="Auto-fill shortcodes for currently selected recipient">
                                    <i data-lucide="sparkles" style="width: 12px; height: 12px; color: #2563eb;"></i> Fill Shortcodes
                                </button>
                                <button type="button" onclick="openSaveAsTemplateModal()" class="rec-action-pill" style="font-size: 11px; background: #f0fdf4; color: #15803d; border-color: #bbf7d0;" title="Save this drafted email as a reusable template">
                                    <i data-lucide="bookmark-plus" style="width: 12px; height: 12px; color: #059669;"></i> Save as Template
                                </button>
                                <button type="button" onclick="insertSignatureBlock()" class="rec-action-pill" style="font-size: 11px;">
                                    <i data-lucide="pen-tool" style="width: 12px; height: 12px;"></i> Add Signature
                                </button>
                                <button type="button" onclick="insertTrackingButton()" class="rec-action-pill" style="font-size: 11px;">
                                    <i data-lucide="external-link" style="width: 12px; height: 12px;"></i> Add Track Button
                                </button>
                            </div>
                        </div>
                        <div class="quill-wrapper">
                            <div id="email-body-editor"></div>
                        </div>
                        <input type="hidden" name="body" id="email-body-hidden">
                    </div>

                    <!-- Attachment Document -->
                    <div style="margin-bottom: 22px;">
                        <label class="comm-label">File Attachment Document (PDF, Image, Sanction Letter)</label>
                        <input type="file" name="attachment" style="width: 100%; padding: 8px 12px; border: 1px dashed #cbd5e1; border-radius: 8px; background: #f8fafc; font-size: 12.5px; box-sizing: border-box; cursor: pointer;">
                    </div>

                    <!-- Action Bar -->
                    <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 16px; border-top: 1px solid #f1f5f9; flex-wrap: wrap; gap: 12px;">
                        <button type="button" onclick="openLivePreviewModal()" class="rec-action-pill" style="padding: 10px 18px; font-size: 13px;">
                            <i data-lucide="eye" style="width: 15px; height: 15px;"></i> Live Email Preview
                        </button>

                        <button type="submit" id="btnDispatchEmail" style="padding: 11px 26px; background: #0f172a; color: #ffffff; border: none; border-radius: 8px; font-weight: 800; font-size: 13.5px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 14px rgba(15,23,42,0.2); transition: all 0.2s;">
                            <i data-lucide="send" style="width: 15px; height: 15px;"></i>
                            <span id="btnDispatchText">Dispatch Email Now</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- RIGHT: SMART ASSISTANT & PRODUCTIVITY SIDEBAR -->
            <div>

                <!-- 1. AI Email Assistant -->
                <div class="side-tool-card" style="border-color: #cbd5e1; background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);">
                    <div class="side-tool-head">
                        <i data-lucide="sparkles" style="color: #f59e0b; width: 17px; height: 17px;"></i>
                        <span>Smart AI Email Copilot</span>
                    </div>
                    <p style="font-size: 12px; color: #64748b; margin: 0 0 10px 0;">
                        Type your prompt or click a quick scenario to auto-draft formal subject & body:
                    </p>

                    <div style="display: flex; flex-wrap: wrap; gap: 5px; margin-bottom: 12px;">
                        <span class="topic-pill" onclick="quickAiFillPrompt('Write a formal DSA commission payout claim invoice email to the Bank Branch Manager requesting brokerage disbursement.')">Payout Claim</span>
                        <span class="topic-pill" onclick="quickAiFillPrompt('Write an urgent polite reminder to customer requesting pending mandatory KYC and bank statements.')">Missing Docs</span>
                        <span class="topic-pill" onclick="quickAiFillPrompt('Write a congratulatory loan sanction approval email to customer with sanction amount and next steps.')">Sanction Done</span>
                        <span class="topic-pill" onclick="quickAiFillPrompt('Write a loan disbursal confirmation notice to customer thanking them for choosing BFS Circle.')">Disbursed</span>
                    </div>

                    <div style="display: flex; gap: 6px; margin-bottom: 10px;">
                        <input type="text" id="ai_quick_topic_input" placeholder="Prompt (e.g. Write payout follow-up)..." class="comm-input" style="padding: 8px 10px; font-size: 12.5px;">
                        <button type="button" id="ai_write_draft_btn" onclick="generateAiDraftInCard()" style="background: #0f172a; color: #ffffff; border: none; border-radius: 6px; padding: 8px 12px; font-weight: 700; font-size: 12px; cursor: pointer; white-space: nowrap; display: flex; align-items: center; gap: 4px;">
                            <i data-lucide="sparkles" style="width: 13px; height: 13px; color: #f59e0b;"></i> AI Draft
                        </button>
                    </div>

                    <!-- AI Draft Preview Drawer -->
                    <div id="ai_draft_review_card" style="display: none; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 12px; margin-top: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
                        <div style="font-weight: 700; font-size: 11.5px; color: #059669; margin-bottom: 6px; display: flex; align-items: center; gap: 5px;">
                            <i data-lucide="check-circle" style="width: 13px; height: 13px;"></i> AI Draft Ready:
                        </div>
                        <input type="text" id="ai_card_subject_preview" class="comm-input" style="padding: 6px 10px; font-size: 12px; margin-bottom: 6px;" placeholder="AI Subject">
                        <textarea id="ai_card_body_preview" rows="4" class="comm-input" style="padding: 6px 10px; font-size: 12px; margin-bottom: 8px; resize: vertical;" placeholder="AI Body Content"></textarea>
                        
                        <div style="display: flex; justify-content: flex-end; gap: 6px;">
                            <button type="button" onclick="document.getElementById('ai_draft_review_card').style.display='none'" class="rec-action-pill" style="font-size: 11px;">Discard</button>
                            <button type="button" onclick="applyAiDraftToMainForm()" style="background: #059669; color: #ffffff; border: none; border-radius: 6px; padding: 6px 12px; font-weight: 700; font-size: 11.5px; cursor: pointer; display: flex; align-items: center; gap: 4px;">
                                <i data-lucide="check" style="width: 13px; height: 13px;"></i> Apply to Composer
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 2. 1-Click Executive & Custom Templates -->
                <div class="side-tool-card">
                    <div class="side-tool-head" style="display: flex; justify-content: space-between; align-items: center;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <i data-lucide="layers" style="color: #2563eb; width: 17px; height: 17px;"></i>
                            <span>1-Click Preset Templates</span>
                        </div>
                        <button type="button" onclick="openSaveAsTemplateModal()" class="rec-action-pill" style="font-size: 11px; padding: 3px 8px; color: #2563eb; border-color: #bfdbfe; background: #eff6ff;" title="Save new template">
                            <i data-lucide="plus" style="width: 12px; height: 12px;"></i> + Create
                        </button>
                    </div>

                    <!-- Category Filter Tabs -->
                    <div style="display: flex; gap: 4px; margin-bottom: 12px; background: #f1f5f9; padding: 3px; border-radius: 8px;">
                        <button type="button" id="tmplFilterAll" onclick="filterTemplateCategory('all')" style="flex: 1; border: none; background: #ffffff; color: #0f172a; font-size: 11px; font-weight: 700; padding: 4px; border-radius: 6px; cursor: pointer; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">All</button>
                        <button type="button" id="tmplFilterSanction" onclick="filterTemplateCategory('Sanction Approval')" style="flex: 1; border: none; background: transparent; color: #64748b; font-size: 11px; font-weight: 700; padding: 4px; border-radius: 6px; cursor: pointer;">Sanction</button>
                        <button type="button" id="tmplFilterDocs" onclick="filterTemplateCategory('Document Request')" style="flex: 1; border: none; background: transparent; color: #64748b; font-size: 11px; font-weight: 700; padding: 4px; border-radius: 6px; cursor: pointer;">Docs</button>
                        <button type="button" id="tmplFilterCustom" onclick="filterTemplateCategory('custom')" style="flex: 1; border: none; background: transparent; color: #64748b; font-size: 11px; font-weight: 700; padding: 4px; border-radius: 6px; cursor: pointer;">Custom</button>
                    </div>

                    <!-- Dynamic Database Templates Container -->
                    <div id="dynamicTemplatesList" style="max-height: 380px; overflow-y: auto; padding-right: 2px;">
                        <div style="text-align: center; padding: 14px; color: #94a3b8; font-size: 12px;">Loading templates...</div>
                    </div>
                </div>

                <!-- 3. Dynamic Shortcodes Palette -->
                <div class="side-tool-card">
                    <div class="side-tool-head">
                        <i data-lucide="tags" style="color: #059669; width: 17px; height: 17px;"></i>
                        <span>Dynamic Shortcodes</span>
                    </div>
                    <p style="font-size: 11.5px; color: #64748b; margin: 0 0 10px 0;">
                        Click any shortcode to insert at cursor. Auto-evaluates with recipient's actual values:
                    </p>

                    <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                        <span class="shortcode-chip" onclick="insertShortcode('[CUSTOMER_NAME]')">[CUSTOMER_NAME]</span>
                        <span class="shortcode-chip" onclick="insertShortcode('[LOAN_ID]')">[LOAN_ID]</span>
                        <span class="shortcode-chip" onclick="insertShortcode('[LOAN_TYPE]')">[LOAN_TYPE]</span>
                        <span class="shortcode-chip" onclick="insertShortcode('[LOAN_AMOUNT]')">[LOAN_AMOUNT]</span>
                        <span class="shortcode-chip" onclick="insertShortcode('[TRACKING_LINK]')">[TRACKING_LINK]</span>
                        <span class="shortcode-chip" onclick="insertShortcode('[RM_NAME]')">[RM_NAME]</span>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- ============================================== -->
    <!-- TAB 2: ENTERPRISE BULK CSV CAMPAIGN ENGINE -->
    <!-- ============================================== -->
    <div id="tabContentBulkCsv" style="display: none;">
        
        <!-- Top Campaign Hero Bar -->
        <div style="background: #0f172a; border-radius: 14px; padding: 20px 24px; color: #ffffff; margin-bottom: 22px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; box-shadow: 0 4px 20px -4px rgba(15,23,42,0.18); border: 1px solid #1e293b;">
            <div style="display: flex; align-items: center; gap: 14px;">
                <div style="width: 44px; height: 44px; background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #ffffff; flex-shrink: 0;">
                    <i data-lucide="layers" style="width: 22px; height: 22px;"></i>
                </div>
                <div>
                    <div>
                        <h2 style="font-size: 18px; font-weight: 800; color: #ffffff; margin: 0; letter-spacing: -0.01em;">
                            Mass Bulk CSV Email Campaign Dispatcher
                        </h2>
                    </div>
                    <p style="margin: 3px 0 0 0; color: #94a3b8; font-size: 12.5px;">
                        Upload email list in CSV format, customize smart merge tags, set safe random dispatch delays, and monitor live progress in real-time.
                    </p>
                </div>
            </div>
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <a href="sample_email_campaign.csv" download="sample_email_campaign.csv" class="rec-action-pill" style="background: rgba(255,255,255,0.08); color: #ffffff; border-color: rgba(255,255,255,0.2); padding: 7px 14px; font-size: 12px;">
                    <i data-lucide="download" style="width: 13px; height: 13px;"></i> Download Sample CSV
                </a>
                <button type="button" onclick="loadSampleCsvDataIntoCampaign()" class="rec-action-pill" style="background: #ffffff; color: #0f172a; border-color: #ffffff; padding: 7px 14px; font-size: 12px; font-weight: 800;">
                    <i data-lucide="play" style="width: 13px; height: 13px;"></i> Load Demo Leads
                </button>
            </div>
        </div>

        <!-- 2-Column Campaign Grid -->
        <div class="comm-grid" style="grid-template-columns: 1.15fr 0.85fr;">
            
            <!-- LEFT COLUMN: CSV UPLOAD & TEMPLATE COMPOSER -->
            <div style="display: flex; flex-direction: column; gap: 20px;">
                
                <!-- CARD 1: CSV FILE UPLOADER & PARSER -->
                <div class="composer-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <i data-lucide="file-spreadsheet" style="width: 18px; height: 18px; color: #0f172a;"></i>
                            <h3 style="margin: 0; font-size: 15px; font-weight: 800; color: #0f172a;">
                                1. Upload Recipient CSV Contact List
                            </h3>
                        </div>
                        <button type="button" onclick="document.getElementById('campaignCsvFileInput').click()" class="rec-action-pill" style="font-size: 11.5px; padding: 5px 12px;">
                            Browse File
                        </button>
                    </div>

                    <!-- Drag & Drop Zone -->
                    <div id="csvDropZone" onclick="document.getElementById('campaignCsvFileInput').click()" style="border: 2px dashed #cbd5e1; border-radius: 12px; padding: 26px 20px; text-align: center; background: #f8fafc; cursor: pointer; transition: all 0.2s;" ondragover="event.preventDefault(); this.style.borderColor='#0f172a'; this.style.background='#f1f5f9';" ondragleave="this.style.borderColor='#cbd5e1'; this.style.background='#f8fafc';" ondrop="handleCsvDrop(event)">
                        <input type="file" id="campaignCsvFileInput" accept=".csv, .txt" onchange="handleCampaignCsvUpload(event)" style="display: none;">
                        <div style="width: 44px; height: 44px; background: #ffffff; color: #0f172a; border: 1px solid #e2e8f0; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.03);">
                            <i data-lucide="upload-cloud" style="width: 20px; height: 20px;"></i>
                        </div>
                        <div style="font-weight: 700; font-size: 14px; color: #0f172a;">
                            Click to browse or drag &amp; drop your CSV file here
                        </div>
                        <div style="font-size: 12px; color: #64748b; margin-top: 4px;">
                            Accepted columns: <code style="background: #e2e8f0; padding: 2px 6px; border-radius: 4px; color: #0f172a; font-family: monospace;">name, email, company, loan_type, amount</code>
                        </div>
                    </div>

                    <!-- CSV Summary Banner (Shown after parsing) -->
                    <div id="csvSummaryBanner" style="display: none; margin-top: 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px 16px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                            <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                                <span style="font-size: 13px; font-weight: 800; color: #0f172a;" id="csvFileNameBadge">contacts.csv</span>
                                <span style="background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; font-size: 11.5px; font-weight: 700; padding: 2px 8px; border-radius: 6px;" id="csvValidBadge">0 Valid Emails</span>
                                <span style="background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; font-size: 11.5px; font-weight: 700; padding: 2px 8px; border-radius: 6px; display: none;" id="csvInvalidBadge">0 Skipped</span>
                            </div>
                            <div style="display: flex; gap: 8px;">
                                <button type="button" onclick="toggleCsvPreviewTable()" class="rec-action-pill" style="font-size: 11px; padding: 4px 8px;">
                                    <i data-lucide="table" style="width: 12px; height: 12px;"></i> <span id="toggleTableText">View Table</span>
                                </button>
                                <button type="button" onclick="clearLoadedCsv()" class="rec-action-pill" style="font-size: 11px; padding: 4px 8px; color: #dc2626; border-color: #fca5a5;">
                                    Clear
                                </button>
                            </div>
                        </div>

                        <!-- Collapsible Table Preview -->
                        <div id="csvPreviewTableContainer" style="display: none; margin-top: 12px; max-height: 200px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 8px; background: #ffffff;">
                            <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                                <thead>
                                    <tr style="background: #f8fafc; position: sticky; top: 0; z-index: 2;">
                                        <th style="padding: 7px 10px; text-align: left; font-weight: 700; color: #475569; border-bottom: 1px solid #e2e8f0;">#</th>
                                        <th style="padding: 7px 10px; text-align: left; font-weight: 700; color: #475569; border-bottom: 1px solid #e2e8f0;">Name</th>
                                        <th style="padding: 7px 10px; text-align: left; font-weight: 700; color: #475569; border-bottom: 1px solid #e2e8f0;">Email</th>
                                        <th style="padding: 7px 10px; text-align: left; font-weight: 700; color: #475569; border-bottom: 1px solid #e2e8f0;">Company / Product</th>
                                        <th style="padding: 7px 10px; text-align: right; font-weight: 700; color: #475569; border-bottom: 1px solid #e2e8f0;">Status</th>
                                    </tr>
                                </thead>
                                <tbody id="csvPreviewTableBody">
                                    <!-- Populated via JS -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- CARD 2: CAMPAIGN CONTENT & MERGE TAGS -->
                <div class="composer-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <i data-lucide="edit-3" style="width: 18px; height: 18px; color: #0f172a;"></i>
                            <h3 style="margin: 0; font-size: 15px; font-weight: 800; color: #0f172a;">
                                2. Email Campaign Subject &amp; Content
                            </h3>
                        </div>
                        <div style="display: flex; gap: 6px;">
                            <button type="button" onclick="loadCampaignPresetTemplate('pitch')" class="rec-action-pill" style="font-size: 11px; padding: 4px 8px;">
                                Pitch Template
                            </button>
                            <button type="button" onclick="loadCampaignPresetTemplate('offer')" class="rec-action-pill" style="font-size: 11px; padding: 4px 8px;">
                                Special Offer
                            </button>
                        </div>
                    </div>

                    <!-- Campaign Name -->
                    <div style="margin-bottom: 14px;">
                        <label class="comm-label">Internal Campaign Title</label>
                        <input type="text" id="campaign_internal_title" class="comm-input" placeholder="e.g. Q4 Business Loan Pitch - North Region" value="Festive Loan Growth Campaign">
                    </div>

                    <!-- Email Subject -->
                    <div style="margin-bottom: 14px;">
                        <label class="comm-label">
                            Email Subject Line <span style="color: #ef4444;">*</span>
                        </label>
                        <input type="text" id="campaign_subject" class="comm-input" placeholder="e.g. Exclusive Loan Proposal for {name} ({company})" value="Special Pre-Approved Business Loan Offer for {name}">
                    </div>

                    <!-- Dynamic Merge Tag Chips -->
                    <div style="margin-bottom: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 12px;">
                        <div style="font-size: 11.5px; font-weight: 700; color: #475569; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                            <i data-lucide="tag" style="width: 13px; height: 13px; color: #0f172a;"></i> Click tag to insert dynamic variable into message:
                        </div>
                        <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                            <span class="shortcode-chip" onclick="insertCampaignTag('{name}')" title="Insert Recipient Name">{name}</span>
                            <span class="shortcode-chip" onclick="insertCampaignTag('{email}')" title="Insert Recipient Email">{email}</span>
                            <span class="shortcode-chip" onclick="insertCampaignTag('{company}')" title="Insert Company Name">{company}</span>
                            <span class="shortcode-chip" onclick="insertCampaignTag('{loan_type}')" title="Insert Loan Type">{loan_type}</span>
                            <span class="shortcode-chip" onclick="insertCampaignTag('{amount}')" title="Insert Loan Amount">{amount}</span>
                        </div>
                    </div>

                    <!-- Email Body HTML / Textarea -->
                    <div style="margin-bottom: 10px;">
                        <label class="comm-label">
                            Email Message Body (HTML &amp; Plain Text Supported) <span style="color: #ef4444;">*</span>
                        </label>
                        <textarea id="campaign_body" rows="9" class="comm-input" style="font-family: inherit; font-size: 13.5px; line-height: 1.6; resize: vertical;" placeholder="Dear {name},&#10;&#10;We are pleased to introduce an exclusive financing facility for {company}...">Dear {name},

We are pleased to present an exclusive corporate funding facility customized for {company}.

Under our current banking partnership program, you are eligible for pre-sanctioned {loan_type} funding up to {amount} with attractive interest rates and zero upfront evaluation charges.

Key Highlights of the Facility:
• Fast-track sanction within 48 banking hours
• Flexible repayment tenure with tailored EMI schedules
• Minimal documentation requirement

If you would like our executive relationship manager to share the detailed term-sheet and bank sanction matrix, kindly reply to this email or contact our desk directly.

Warm regards,
BFS Circle Loan Operations Team
Email: support@bfsfinancial.com | Toll-Free: 1800-BFS-LOAN</textarea>
                    </div>
                </div>

            </div>

            <!-- RIGHT COLUMN: ANTI-SPAM SETTINGS, DISPATCH CONTROLS & LIVE TERMINAL -->
            <div style="display: flex; flex-direction: column; gap: 20px;">
                
                <!-- CARD 3: ANTI-SPAM RANDOM DELAY & ENGINE CONTROLS -->
                <div class="composer-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <i data-lucide="shield-check" style="width: 18px; height: 18px; color: #0f172a;"></i>
                            <h3 style="margin: 0; font-size: 15px; font-weight: 800; color: #0f172a;">
                                3. Anti-Spam Random Delay Engine
                            </h3>
                        </div>
                        <span style="font-size: 11px; font-weight: 700; color: #15803d; background: #f0fdf4; border: 1px solid #bbf7d0; padding: 2px 7px; border-radius: 4px;">
                            Protection Active
                        </span>
                    </div>

                    <!-- Min & Max Delay Controls -->
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px; margin-bottom: 14px;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 10px;">
                            <div>
                                <label class="comm-label" style="font-size: 11.5px;">Min Delay (Seconds)</label>
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <input type="number" id="campaignMinDelay" min="1" max="60" value="3" class="comm-input" style="font-weight: 800; text-align: center; padding: 8px;" onchange="validateDelayValues()">
                                    <span style="font-size: 12px; color: #64748b; font-weight: 600;">sec</span>
                                </div>
                            </div>
                            <div>
                                <label class="comm-label" style="font-size: 11.5px;">Max Delay (Seconds)</label>
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <input type="number" id="campaignMaxDelay" min="2" max="120" value="8" class="comm-input" style="font-weight: 800; text-align: center; padding: 8px;" onchange="validateDelayValues()">
                                    <span style="font-size: 12px; color: #64748b; font-weight: 600;">sec</span>
                                </div>
                            </div>
                        </div>
                        
                        <p style="margin: 0; font-size: 11.5px; color: #64748b; line-height: 1.5;">
                            <i data-lucide="shield-check" style="width: 13px; height: 13px; color: #0f172a; display: inline-block; vertical-align: middle; margin-right: 4px;"></i><b>Why Random Delay?</b> A random interval between <span id="delayPreviewText" style="color: #0f172a; font-weight: 700;">3s and 8s</span> is generated between every dispatch. This prevents ESP throttling, circumvents spam velocity triggers, and keeps your IP/domain reputation 100% clean.
                        </p>
                    </div>

                    <!-- Engine Dispatch Mode Selector -->
                    <div style="margin-bottom: 6px;">
                        <label class="comm-label" style="font-size: 12px;">Campaign Delivery Mode</label>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                            <div id="cardModeSimulated" onclick="selectCampaignMode('simulated')" style="border: 2px solid #0f172a; background: #f8fafc; border-radius: 10px; padding: 12px 14px; cursor: pointer; transition: all 0.2s; display: flex; align-items: flex-start; gap: 10px;">
                                <div style="width: 18px; height: 18px; border-radius: 50%; border: 2px solid #0f172a; display: flex; align-items: center; justify-content: center; margin-top: 2px; flex-shrink: 0;" id="radioDotSimulated">
                                    <div style="width: 8px; height: 8px; border-radius: 50%; background: #0f172a;"></div>
                                </div>
                                <div>
                                    <div style="font-size: 12.5px; font-weight: 800; color: #0f172a;">Simulated Run (Safe)</div>
                                    <div style="font-size: 11.5px; color: #64748b; margin-top: 2px;">Records in database audit log without sending real emails. Ideal for testing.</div>
                                </div>
                            </div>
                            <div id="cardModeSmtp" onclick="selectCampaignMode('smtp')" style="border: 1px solid #cbd5e1; background: #ffffff; border-radius: 10px; padding: 12px 14px; cursor: pointer; transition: all 0.2s; display: flex; align-items: flex-start; gap: 10px;">
                                <div style="width: 18px; height: 18px; border-radius: 50%; border: 2px solid #cbd5e1; display: flex; align-items: center; justify-content: center; margin-top: 2px; flex-shrink: 0;" id="radioDotSmtp">
                                    <div style="width: 8px; height: 8px; border-radius: 50%; background: transparent;"></div>
                                </div>
                                <div>
                                    <div style="font-size: 12.5px; font-weight: 800; color: #0f172a;">Live SMTP Mode</div>
                                    <div style="font-size: 11.5px; color: #64748b; margin-top: 2px;">Sends genuine outbound emails via configured corporate SMTP credentials.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CARD 4: REAL-TIME COMMAND CONSOLE & LIVE MONITOR -->
                <div class="composer-card" style="border-color: #cbd5e1; box-shadow: 0 4px 15px -3px rgba(0,0,0,0.04);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <i data-lucide="activity" style="width: 18px; height: 18px; color: #0f172a;"></i>
                            <h3 style="margin: 0; font-size: 15px; font-weight: 800; color: #0f172a;">
                                4. Live Command Console &amp; Controls
                            </h3>
                        </div>
                        <span id="campaignStatusPill" style="padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 800; background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1;">
                            IDLE
                        </span>
                    </div>

                    <!-- Real-Time Metrics Counters (4 Badges) -->
                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 14px;">
                        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 6px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                            <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">Queued</div>
                            <div style="font-size: 19px; font-weight: 800; color: #0f172a; margin-top: 2px;" id="cntQueued">0</div>
                        </div>
                        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 6px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                            <div style="font-size: 11px; font-weight: 700; color: #15803d; text-transform: uppercase; display: flex; align-items: center; justify-content: center; gap: 4px;">
                                <span style="width: 5px; height: 5px; background: #16a34a; border-radius: 50%;"></span> Sent
                            </div>
                            <div style="font-size: 19px; font-weight: 800; color: #16a34a; margin-top: 2px;" id="cntSent">0</div>
                        </div>
                        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 6px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                            <div style="font-size: 11px; font-weight: 700; color: #b91c1c; text-transform: uppercase; display: flex; align-items: center; justify-content: center; gap: 4px;">
                                <span style="width: 5px; height: 5px; background: #dc2626; border-radius: 50%;"></span> Failed
                            </div>
                            <div style="font-size: 19px; font-weight: 800; color: #dc2626; margin-top: 2px;" id="cntFailed">0</div>
                        </div>
                        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 6px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                            <div style="font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; display: flex; align-items: center; justify-content: center; gap: 4px;">
                                <span style="width: 5px; height: 5px; background: #94a3b8; border-radius: 50%;"></span> Pending
                            </div>
                            <div style="font-size: 19px; font-weight: 800; color: #0f172a; margin-top: 2px;" id="cntPending">0</div>
                        </div>
                    </div>

                    <!-- Animated Progress Bar -->
                    <div style="margin-bottom: 14px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px; font-size: 12px; font-weight: 700; color: #334155;">
                            <span>Dispatch Completion</span>
                            <span id="campaignPercentText">0% (0 / 0)</span>
                        </div>
                        <div style="width: 100%; height: 8px; background: #e2e8f0; border-radius: 20px; overflow: hidden; position: relative;">
                            <div id="campaignProgressBar" style="width: 0%; height: 100%; background: #0f172a; transition: width 0.3s ease; border-radius: 20px;"></div>
                        </div>
                    </div>

                    <!-- Next Dispatch Countdown Ticker Banner -->
                    <div id="campaignCountdownBox" style="background: #0f172a; color: #ffffff; border-radius: 8px; padding: 10px 14px; margin-bottom: 14px; display: flex; align-items: center; justify-content: space-between; font-size: 12.5px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span id="countdownSpinner" style="width: 8px; height: 8px; background: #10b981; border-radius: 50%; display: inline-block;"></span>
                            <span id="countdownMessage" style="font-weight: 600; color: #f8fafc;">Queue idle. Ready to dispatch.</span>
                        </div>
                        <span id="countdownClock" style="font-family: 'Consolas', monospace; font-weight: 800; color: #ffffff; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.15); padding: 2px 8px; border-radius: 4px; font-size: 12.5px;">0.0s</span>
                    </div>

                    <!-- Interactive Control Buttons -->
                    <div style="display: flex; gap: 8px; margin-bottom: 14px; flex-wrap: wrap;">
                        <button type="button" id="btnStartCampaign" onclick="startCampaignExecution()" style="flex: 1.2; min-width: 140px; padding: 10px 16px; background: #0f172a; color: #ffffff; border: 1px solid #0f172a; border-radius: 8px; font-size: 13px; font-weight: 800; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px; box-shadow: 0 4px 12px rgba(15,23,42,0.12); transition: all 0.15s;">
                            <i data-lucide="play" style="width: 14px; height: 14px;"></i> Start Dispatch
                        </button>
                        <button type="button" id="btnPauseCampaign" onclick="pauseCampaignExecution()" disabled style="padding: 10px 14px; background: #ffffff; color: #475569; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: not-allowed; display: flex; align-items: center; gap: 5px; opacity: 0.6; transition: all 0.15s;">
                            <i data-lucide="pause" style="width: 14px; height: 14px;"></i> Pause
                        </button>
                        <button type="button" id="btnResumeCampaign" onclick="resumeCampaignExecution()" style="display: none; padding: 10px 14px; background: #0f172a; color: #ffffff; border: 1px solid #0f172a; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; align-items: center; gap: 5px; transition: all 0.15s;">
                            <i data-lucide="play" style="width: 14px; height: 14px;"></i> Resume
                        </button>
                        <button type="button" id="btnStopCampaign" onclick="stopCampaignExecution()" disabled style="padding: 10px 14px; background: #ffffff; color: #dc2626; border: 1px solid #fecaca; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: not-allowed; display: flex; align-items: center; gap: 5px; opacity: 0.6; transition: all 0.15s;">
                            <i data-lucide="square" style="width: 14px; height: 14px;"></i> Stop
                        </button>
                        <button type="button" id="btnExportReport" onclick="exportCampaignReport()" disabled style="padding: 10px 14px; background: #ffffff; color: #0f172a; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: not-allowed; display: flex; align-items: center; gap: 5px; opacity: 0.6; transition: all 0.15s;">
                            <i data-lucide="download" style="width: 14px; height: 14px;"></i> Export Report
                        </button>
                    </div>

                    <!-- Live Terminal Output Console -->
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                            <label class="comm-label" style="font-size: 11px; margin: 0; color: #64748b; text-transform: uppercase;">
                                Live Dispatch Activity Terminal
                            </label>
                            <button type="button" onclick="clearCampaignTerminal()" style="background: none; border: none; font-size: 11px; color: #64748b; cursor: pointer; text-decoration: underline;">
                                Clear Console
                            </button>
                        </div>
                        <div id="campaignTerminalLog" style="background: #090d16; border: 1px solid #1e293b; border-radius: 8px; padding: 12px 14px; font-family: 'Consolas', 'Monaco', 'Courier New', monospace; font-size: 11.5px; line-height: 1.5; color: #e2e8f0; height: 180px; overflow-y: auto;">
                            <div style="color: #64748b;">[00:00:00] Terminal ready. Upload CSV or load demo leads to begin.</div>
                        </div>
                    </div>

                </div>

            </div>

        </div>

    </div>

    <!-- ============================================== -->
    <!-- TAB: INBOUND REPLIES MAILBOX -->
    <!-- ============================================== -->
    <div id="tabContentInbox" style="display: none;">
        <!-- 2-Column Split Mailbox Workspace -->
        <div style="display: grid; grid-template-columns: 360px 1fr; gap: 20px; align-items: start;">
            
            <!-- LEFT PANEL: Conversation List -->
            <div class="composer-card" style="padding: 16px; min-height: 620px; display: flex; flex-direction: column;">
                <!-- Mailbox Toolbar & Actions -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; padding-bottom: 10px; border-bottom: 1px solid #f1f5f9;">
                    <div style="font-weight: 800; font-size: 13.5px; color: #0f172a; display: flex; align-items: center; gap: 6px;">
                        <i data-lucide="inbox" style="width: 15px; height: 15px; color: #0f172a;"></i>
                        <span>Customer Inquiries</span>
                    </div>
                    <div style="display: flex; gap: 5px; align-items: center;">
                        <button type="button" onclick="openSimulateReplyModal()" class="rec-action-pill" style="font-size: 11px; padding: 4px 8px;" title="Test incoming reply injection">
                            <i data-lucide="play" style="width: 11px; height: 11px;"></i> Simulate
                        </button>
                        <button type="button" onclick="openWebhookInfoModal()" class="rec-action-pill" style="font-size: 11px; padding: 4px 8px;" title="Webhook Setup Info">
                            <i data-lucide="link" style="width: 11px; height: 11px;"></i> Webhook
                        </button>
                        <button type="button" onclick="loadInboundReplies(true)" class="rec-action-pill" style="font-size: 11px; padding: 4px 7px;" title="Refresh Inbox">
                            <i data-lucide="refresh-cw" style="width: 11px; height: 11px;"></i>
                        </button>
                    </div>
                </div>

                <!-- Search & Filters -->
                <div style="margin-bottom: 12px;">
                    <div style="position: relative; margin-bottom: 8px;">
                        <input type="text" id="inboxSearchInput" oninput="filterInboundList()" placeholder="Search replies or sender..." class="comm-input" style="padding-left: 32px; font-size: 12.5px; padding-top: 8px; padding-bottom: 8px;">
                        <i data-lucide="search" style="position: absolute; left: 10px; top: 10px; width: 14px; height: 14px; color: #94a3b8;"></i>
                    </div>
                    <div style="display: flex; gap: 6px;">
                        <button type="button" class="inbox-filter-btn" id="btnFilterAll" onclick="setInboxFilter('all')" style="flex: 1; padding: 4px 8px; font-size: 11px; font-weight: 700; border-radius: 6px; border: 1px solid #cbd5e1; background: #0f172a; color: #ffffff; cursor: pointer;">All</button>
                        <button type="button" class="inbox-filter-btn" id="btnFilterUnread" onclick="setInboxFilter('unread')" style="flex: 1; padding: 4px 8px; font-size: 11px; font-weight: 700; border-radius: 6px; border: 1px solid #cbd5e1; background: #ffffff; color: #475569; cursor: pointer;">Unread</button>
                        <button type="button" class="inbox-filter-btn" id="btnFilterRead" onclick="setInboxFilter('read')" style="flex: 1; padding: 4px 8px; font-size: 11px; font-weight: 700; border-radius: 6px; border: 1px solid #cbd5e1; background: #ffffff; color: #475569; cursor: pointer;">Read</button>
                    </div>
                </div>

                <!-- Scrollable Item List -->
                <div id="inboundRepliesList" style="flex: 1; overflow-y: auto; max-height: 650px; display: flex; flex-direction: column; gap: 8px;">
                    <div style="text-align: center; padding: 40px 20px; color: #94a3b8;">
                        <i data-lucide="loader" class="animate-spin" style="width: 20px; height: 20px; margin: 0 auto 10px auto; display: block;"></i>
                        Loading customer replies...
                    </div>
                </div>
            </div>

            <!-- RIGHT PANEL: Conversation Thread Reader & Quick Follow-Up -->
            <div class="composer-card" id="inboundReaderCard" style="padding: 22px; min-height: 620px;">
                <!-- Empty Placeholder State -->
                <div id="inboundEmptyState" style="text-align: center; padding: 80px 20px; color: #64748b;">
                    <div style="background: #f1f5f9; width: 56px; height: 56px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px auto; color: #94a3b8;">
                        <i data-lucide="mail" style="width: 26px; height: 26px;"></i>
                    </div>
                    <h3 style="margin: 0 0 6px 0; font-size: 15px; font-weight: 800; color: #1e293b;">Select an Inbound Message</h3>
                    <p style="margin: 0 auto; max-width: 400px; font-size: 12.5px; line-height: 1.5; color: #64748b;">Click on any message from the left list to review customer correspondence, link to loan files, and dispatch quick follow-ups.</p>
                </div>

                <!-- Active Thread Container -->
                <div id="inboundThreadContent" style="display: none;">
                    <!-- Thread Header -->
                    <div style="border-bottom: 1px solid #e2e8f0; padding-bottom: 16px; margin-bottom: 18px;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap;">
                            <div>
                                <h3 id="threadSubject" style="margin: 0 0 6px 0; font-size: 17px; font-weight: 800; color: #0f172a;">-</h3>
                                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; font-size: 12.5px; color: #64748b;">
                                    <span>From: <b id="threadSenderEmail" style="color: #0f172a;">-</b></span>
                                    <span>•</span>
                                    <span id="threadReceivedTime">-</span>
                                    <span id="threadClientBadge" style="display: inline-block;"></span>
                                </div>
                            </div>
                            <div style="display: flex; gap: 8px; align-items: center;">
                                <button type="button" id="btnToggleReadStatus" onclick="toggleCurrentReplyRead()" class="rec-action-pill" style="font-size: 12px;">
                                    <i data-lucide="check" style="width: 13px; height: 13px;"></i> Mark as Unread
                                </button>
                                <a id="threadProfileLink" href="#" target="_blank" class="rec-action-pill" style="font-size: 12px; display: none; background: #0f172a; color: #ffffff; border-color: #0f172a;">
                                    <i data-lucide="external-link" style="width: 13px; height: 13px;"></i> Open Client File
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Conversation Thread Body: 1. Original Outbound (if exists) -->
                    <div id="parentOutboundCard" style="display: none; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 18px; margin-bottom: 16px;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div style="font-size: 12px; font-weight: 700; color: #475569; display: flex; align-items: center; gap: 6px;">
                                <i data-lucide="corner-left-up" style="width: 13px; height: 13px; color: #2563eb;"></i>
                                <span>Original Sent Message (Sent by <span id="parentSenderName">RM</span> on <span id="parentSentAt">-</span>)</span>
                            </div>
                            <button type="button" onclick="toggleParentBody()" style="background: none; border: none; font-size: 11.5px; color: #2563eb; cursor: pointer; font-weight: 600; display: inline-flex; align-items: center; gap: 3px;" id="btnToggleParent">
                                <i data-lucide="chevron-down" style="width: 12px; height: 12px;"></i> View Message
                            </button>
                        </div>
                        <div id="parentBodyContent" style="display: none; font-size: 13px; color: #334155; line-height: 1.5; border-top: 1px dashed #cbd5e1; padding-top: 10px; margin-top: 8px; max-height: 200px; overflow-y: auto;">
                        </div>
                    </div>

                    <!-- Conversation Thread Body: 2. Customer Inbound Reply -->
                    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; padding-bottom: 10px; border-bottom: 1px solid #f1f5f9;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span style="background: #f1f5f9; color: #0f172a; padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 11.5px; display: inline-flex; align-items: center; gap: 5px;">
                                    <i data-lucide="corner-left-down" style="width: 12px; height: 12px; color: #2563eb;"></i>
                                    Customer Response
                                </span>
                            </div>
                            <span id="threadReplyExactTime" style="font-size: 12px; color: #64748b; font-weight: 500;">-</span>
                        </div>
                        <div id="threadReplyMessageBody" style="font-size: 13.5px; line-height: 1.6; color: #1e293b; white-space: pre-line;">
                            -
                        </div>
                    </div>

                    <!-- Quick Reply Follow-Up Composer -->
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; flex-wrap: wrap; gap: 8px;">
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <i data-lucide="corner-up-left" style="width: 14px; height: 14px; color: #0f172a;"></i>
                                <span style="font-weight: 800; font-size: 13px; color: #0f172a;">Follow-Up Quick Reply</span>
                            </div>
                            <!-- Preset Reply Chips -->
                            <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                <button type="button" onclick="quickFillReply('docs')" class="rec-action-pill" style="font-size: 11px; padding: 3px 8px;">
                                    <i data-lucide="file-text" style="width: 11px; height: 11px;"></i> Pending Docs
                                </button>
                                <button type="button" onclick="quickFillReply('review')" class="rec-action-pill" style="font-size: 11px; padding: 3px 8px;">
                                    <i data-lucide="clock" style="width: 11px; height: 11px;"></i> Under Review
                                </button>
                                <button type="button" onclick="quickFillReply('approved')" class="rec-action-pill" style="font-size: 11px; padding: 3px 8px;">
                                    <i data-lucide="check-circle" style="width: 11px; height: 11px;"></i> Sanction Approved
                                </button>
                                <button type="button" onclick="quickFillReply('call')" class="rec-action-pill" style="font-size: 11px; padding: 3px 8px;">
                                    <i data-lucide="phone" style="width: 11px; height: 11px;"></i> Schedule Call
                                </button>
                            </div>
                        </div>

                        <form id="inboundQuickReplyForm" onsubmit="sendInboundQuickReply(event)">
                            <input type="hidden" id="qrReplyToId" value="">
                            <input type="hidden" id="qrRecipientEmail" value="">
                            <input type="hidden" id="qrClientId" value="">

                            <div style="margin-bottom: 10px;">
                                <input type="text" id="qrSubject" class="comm-input" placeholder="Subject..." style="font-size: 13px; font-weight: 700;" required>
                            </div>

                            <div style="margin-bottom: 12px;">
                                <textarea id="qrMessageBody" rows="4" class="comm-input" placeholder="Type your response to the customer..." style="font-size: 13.5px; line-height: 1.5; resize: vertical;" required></textarea>
                            </div>

                            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                                <div style="font-size: 11.5px; color: #64748b;">
                                    Delivery Engine: <span style="font-weight: 700; color: #0f172a;">Configured Active Mode (SMTP / Simulated)</span>
                                </div>
                                <button type="submit" id="btnSendInboundReply" style="background: #0f172a; color: #ffffff; border: none; padding: 8px 18px; border-radius: 8px; font-weight: 700; font-size: 12.5px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                                    <i data-lucide="send" style="width: 13px; height: 13px;"></i>
                                    <span>Send Follow-Up Reply</span>
                                </button>
                            </div>
                        </form>
                    </div>

                </div>
            </div>

        </div>
    </div>

    <!-- ============================================== -->
    <!-- TAB 3: SENT EMAIL AUDIT LOG TABLE -->
    <!-- ============================================== -->
    <div id="tabContentHistory" style="display: none;">
        <div class="composer-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; flex-wrap: wrap; gap: 12px;">
                <div>
                    <h3 style="margin: 0; font-size: 16px; font-weight: 800; color: #0f172a;">Dispatched Email Communications Audit Log</h3>
                    <p style="margin: 2px 0 0 0; font-size: 12.5px; color: #64748b;">Comprehensive history of all customer and bank emails dispatched through the system</p>
                </div>
                <button onclick="loadEmailLogs()" class="rec-action-pill" style="padding: 8px 14px;">
                    <i data-lucide="refresh-cw" style="width: 13px; height: 13px;"></i> Refresh Log
                </button>
            </div>

            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: separate; border-spacing: 0;">
                    <thead>
                        <tr>
                            <th style="background: #f8fafc; padding: 12px 14px; text-align: left; font-size: 11.5px; font-weight: 700; color: #475569; text-transform: uppercase; border-bottom: 2px solid #e2e8f0;">Date & Time</th>
                            <th style="background: #f8fafc; padding: 12px 14px; text-align: left; font-size: 11.5px; font-weight: 700; color: #475569; text-transform: uppercase; border-bottom: 2px solid #e2e8f0;">Recipient Client</th>
                            <th style="background: #f8fafc; padding: 12px 14px; text-align: left; font-size: 11.5px; font-weight: 700; color: #475569; text-transform: uppercase; border-bottom: 2px solid #e2e8f0;">Category</th>
                            <th style="background: #f8fafc; padding: 12px 14px; text-align: left; font-size: 11.5px; font-weight: 700; color: #475569; text-transform: uppercase; border-bottom: 2px solid #e2e8f0;">Subject</th>
                            <th style="background: #f8fafc; padding: 12px 14px; text-align: left; font-size: 11.5px; font-weight: 700; color: #475569; text-transform: uppercase; border-bottom: 2px solid #e2e8f0;">Sent By</th>
                            <th style="background: #f8fafc; padding: 12px 14px; text-align: left; font-size: 11.5px; font-weight: 700; color: #475569; text-transform: uppercase; border-bottom: 2px solid #e2e8f0;">Attachment</th>
                            <th style="background: #f8fafc; padding: 12px 14px; text-align: right; font-size: 11.5px; font-weight: 700; color: #475569; text-transform: uppercase; border-bottom: 2px solid #e2e8f0;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="emailLogsTableBody">
                        <tr><td colspan="7" style="text-align: center; padding: 30px; color: #94a3b8;">Loading email audit history...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ============================================== -->
<!-- LIVE EMAIL PREVIEW MODAL -->
<!-- ============================================== -->
<div id="emailPreviewModal" style="display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.7); z-index: 999999; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div style="background: #ffffff; border-radius: 14px; width: 92%; max-width: 750px; max-height: 90vh; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.3); border: 1px solid #cbd5e1;">
        <div style="background: #0f172a; color: #ffffff; padding: 16px 22px; display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <i data-lucide="eye" style="width: 18px; height: 18px; color: #38bdf8;"></i>
                <span style="font-weight: 800; font-size: 15px; color: #ffffff;">Live Executive Email Preview</span>
            </div>
            <button onclick="closePreviewModal()" style="background: transparent; border: none; color: #94a3b8; font-size: 20px; cursor: pointer; line-height: 1;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#94a3b8'">✕</button>
        </div>
        <div style="padding: 16px 22px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-size: 13px;">
            <div style="margin-bottom: 6px;"><b>TO:</b> <span id="prevToText" style="color: #0f172a;">-</span></div>
            <div><b>SUBJECT:</b> <span id="prevSubjText" style="color: #2563eb; font-weight: 700;">-</span></div>
        </div>
        <div id="prevContentHtml" style="flex: 1; padding: 22px; overflow-y: auto; background: #ffffff; font-size: 14px; line-height: 1.6; color: #1e293b;"></div>
        <div style="padding: 14px 22px; background: #f8fafc; border-top: 1px solid #e2e8f0; text-align: right;">
            <button onclick="closePreviewModal()" class="rec-action-pill" style="padding: 8px 18px; font-size: 13px; background: #0f172a; color: #ffffff; border-color: #0f172a;">Close Preview</button>
        </div>
    </div>
</div>

<!-- ============================================== -->
<!-- SIMULATE INCOMING CLIENT REPLY MODAL -->
<!-- ============================================== -->
<div id="simulateReplyModal" style="display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.7); z-index: 999999; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div style="background: #ffffff; border-radius: 14px; width: 92%; max-width: 600px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.3); border: 1px solid #cbd5e1; overflow: hidden;">
        <div style="background: #0f172a; color: #ffffff; padding: 16px 22px; display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <i data-lucide="play-circle" style="width: 18px; height: 18px; color: #38bdf8;"></i>
                <span style="font-weight: 800; font-size: 15px; color: #ffffff;">Simulate / Test Inbound Client Reply</span>
            </div>
            <button onclick="closeSimulateReplyModal()" style="background: transparent; border: none; color: #94a3b8; font-size: 20px; cursor: pointer; line-height: 1;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#94a3b8'">✕</button>
        </div>
        <form onsubmit="submitSimulateReply(event)" style="padding: 22px;">
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 14px; margin-bottom: 14px; font-size: 12px; color: #334155; line-height: 1.4;">
                <i data-lucide="info" style="width: 13px; height: 13px; display: inline-block; vertical-align: middle; margin-right: 4px; color: #2563eb;"></i><b>Test Ingestion Engine:</b> Simulates a customer hitting "Reply" in their email client. Matches sender email automatically against active loan files and clients.
            </div>
            <div style="margin-bottom: 12px;">
                <label class="comm-label">Customer / Sender Email <span style="color: #ef4444;">*</span></label>
                <input type="email" id="sim_sender_email" class="comm-input" placeholder="e.g. rohit.sharma@example.com" required>
            </div>
            <div style="margin-bottom: 12px;">
                <label class="comm-label">Reply Email Subject <span style="color: #ef4444;">*</span></label>
                <input type="text" id="sim_subject" class="comm-input" placeholder="e.g. Re: Sanction Letter & Disbursement Documents" required>
            </div>
            <div style="margin-bottom: 16px;">
                <label class="comm-label">Incoming Message Content <span style="color: #ef4444;">*</span></label>
                <textarea id="sim_body" rows="5" class="comm-input" placeholder="e.g. Hi Team, I have attached the signed agreement and GST returns. Please proceed with disbursement." required></textarea>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #f1f5f9; padding-top: 14px;">
                <button type="button" onclick="closeSimulateReplyModal()" class="rec-action-pill" style="padding: 8px 18px; font-size: 13px;">Cancel</button>
                <button type="submit" id="btnSubmitSimulateReply" style="padding: 8px 20px; background: #0f172a; color: #ffffff; border: none; border-radius: 8px; font-weight: 700; font-size: 13px; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                    <i data-lucide="send" style="width: 14px; height: 14px;"></i> Inject Test Reply
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================== -->
<!-- INBOUND WEBHOOK SETUP & INTEGRATION DOCS MODAL -->
<!-- ============================================== -->
<div id="webhookInfoModal" style="display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.7); z-index: 999999; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div style="background: #ffffff; border-radius: 14px; width: 92%; max-width: 650px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.3); border: 1px solid #cbd5e1; overflow: hidden;">
        <div style="background: #0f172a; color: #ffffff; padding: 16px 22px; display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <i data-lucide="link" style="width: 18px; height: 18px; color: #38bdf8;"></i>
                <span style="font-weight: 800; font-size: 15px; color: #ffffff;">Inbound Email Webhook & MX Piping Setup</span>
            </div>
            <button onclick="closeWebhookInfoModal()" style="background: transparent; border: none; color: #94a3b8; font-size: 20px; cursor: pointer; line-height: 1;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#94a3b8'">✕</button>
        </div>
        <div style="padding: 22px; max-height: 75vh; overflow-y: auto;">
            <p style="margin: 0 0 14px 0; font-size: 13px; color: #334155; line-height: 1.5;">
                When you deploy to production, you can route customer replies directly into this CRM inbox automatically using any Email Service Provider (ESP) or your own cPanel / Postfix mail server.
            </p>

            <div style="background: #0f172a; color: #e2e8f0; border-radius: 8px; padding: 14px; margin-bottom: 16px; font-family: monospace; font-size: 12.5px;">
                <div style="color: #94a3b8; font-size: 11px; margin-bottom: 4px; text-transform: uppercase; font-weight: 700;">Inbound Webhook Endpoint URL</div>
                <div style="color: #38bdf8; word-break: break-all;" id="webhookUrlDisplay">https://yourdomain.com/api.php?api=inbound_email_webhook</div>
            </div>

            <h4 style="margin: 16px 0 8px 0; font-size: 13.5px; font-weight: 800; color: #0f172a;">How it works with popular services:</h4>
            <div style="display: flex; flex-direction: column; gap: 10px; font-size: 12.5px; color: #475569;">
                <div style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 14px; background: #f8fafc;">
                    <b style="color: #0f172a;">1. SendGrid Inbound Parse / Mailgun Routes / Postmark Inbound:</b>
                    <p style="margin: 4px 0 0 0;">Add your sub-domain MX record (e.g. <code>reply.yourcompany.com</code>) and set the destination URL to the Webhook URL above. Incoming customer replies are POSTed in real time.</p>
                </div>
                <div style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 14px; background: #f8fafc;">
                    <b style="color: #0f172a;">2. cPanel / Linux Postfix Pipe to Program:</b>
                    <p style="margin: 4px 0 0 0;">Set email forwarder to pipe: <code>| curl -X POST -d "from=$SENDER" -d "subject=$SUBJECT" -d "body=$BODY" https://yourdomain.com/api.php?api=inbound_email_webhook</code></p>
                </div>
                <div style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 14px; background: #f8fafc;">
                    <b style="color: #0f172a;">3. Automatic Client Linking & Activity Log:</b>
                    <p style="margin: 4px 0 0 0;">The system automatically looks up the sender's email against Active Applicants, Client Vaults, and Pre-Leads. The conversation thread is tied directly to the borrower's record!</p>
                </div>
            </div>
            <div style="text-align: right; margin-top: 18px;">
                <button type="button" onclick="closeWebhookInfoModal()" class="rec-action-pill" style="padding: 8px 20px; background: #0f172a; color: #ffffff; border-color: #0f172a;">Got It</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================== -->
<!-- SAVE / CREATE EMAIL TEMPLATE MODAL -->
<!-- ============================================== -->
<div id="saveTemplateModal" style="display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.7); z-index: 999999; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div style="background: #ffffff; border-radius: 14px; width: 92%; max-width: 620px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.3); border: 1px solid #cbd5e1; overflow: hidden;">
        <div style="background: #0f172a; color: #ffffff; padding: 16px 22px; display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <i data-lucide="bookmark-plus" style="width: 18px; height: 18px; color: #38bdf8;"></i>
                <span style="font-weight: 800; font-size: 15px; color: #ffffff;">Save Reusable Email Template</span>
            </div>
            <button onclick="closeSaveTemplateModal()" style="background: transparent; border: none; color: #94a3b8; font-size: 20px; cursor: pointer; line-height: 1;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#94a3b8'">✕</button>
        </div>
        <form onsubmit="submitSaveTemplate(event)" style="padding: 22px;">
            <div style="margin-bottom: 14px;">
                <label class="comm-label">Template Display Name <span style="color: #ef4444;">*</span></label>
                <input type="text" id="modal_tmpl_name" class="comm-input" placeholder="e.g. Festive MSME Business Loan Offer" required>
            </div>
            <div style="margin-bottom: 14px;">
                <label class="comm-label">Communication Category <span style="color: #ef4444;">*</span></label>
                <select id="modal_tmpl_type" class="comm-input" required>
                    <option value="Custom Mail" selected>Custom Mail / Pitch</option>
                    <option value="Sanction Approval">Sanction Approval</option>
                    <option value="Document Request">Document Pending Reminder</option>
                    <option value="Disbursement Notice">Disbursement Confirmation</option>
                    <option value="Brokerage Claim">Brokerage Claim Invoice</option>
                    <option value="Quotation">Quotation / Proposal</option>
                </select>
            </div>
            <div style="margin-bottom: 14px;">
                <label class="comm-label">Template Email Subject <span style="color: #ef4444;">*</span></label>
                <input type="text" id="modal_tmpl_subject" class="comm-input" placeholder="Subject with shortcodes like [CUSTOMER_NAME]" required>
            </div>
            <div style="margin-bottom: 16px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                    <label class="comm-label" style="margin: 0;">Template HTML Body Content <span style="color: #ef4444;">*</span></label>
                    <span style="font-size: 11px; color: #64748b;">Supports [CUSTOMER_NAME], [LOAN_ID], etc.</span>
                </div>
                <textarea id="modal_tmpl_body" rows="6" class="comm-input" style="font-size: 13px; line-height: 1.5; resize: vertical;" placeholder="Template HTML or text content..." required></textarea>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #f1f5f9; padding-top: 14px;">
                <button type="button" onclick="closeSaveTemplateModal()" class="rec-action-pill" style="padding: 8px 18px; font-size: 13px;">Cancel</button>
                <button type="submit" id="btnSaveTemplateSubmit" style="padding: 8px 20px; background: #0f172a; color: #ffffff; border: none; border-radius: 8px; font-weight: 700; font-size: 13px; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                    <i data-lucide="check" style="width: 14px; height: 14px;"></i> Save Template
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================== -->
<!-- BULK SEGMENT EMAIL MODAL -->
<!-- ============================================== -->
<div id="bulkSegmentEmailModal" style="display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.7); z-index: 999999; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div style="background: #ffffff; border-radius: 14px; width: 92%; max-width: 600px; padding: 24px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.3); border: 1px solid #cbd5e1;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <i data-lucide="users" style="color: #2563eb; width: 20px; height: 20px;"></i>
                <h3 style="margin: 0; font-size: 17px; font-weight: 800; color: #0f172a;">Bulk Segment Email Dispatcher</h3>
            </div>
            <button onclick="closeBulkModal()" style="background: transparent; border: none; font-size: 20px; color: #94a3b8; cursor: pointer;">✕</button>
        </div>
        <form onsubmit="executeBulkSegmentEmail(event)">
            <div style="margin-bottom: 14px;">
                <label class="comm-label">Target Data Segment Pool <span style="color: #ef4444;">*</span></label>
                <select id="bulk_target_segment" class="comm-input" required>
                    <option value="">-- Choose Data Segment Pool --</option>
                </select>
            </div>
            <div style="margin-bottom: 14px;">
                <label class="comm-label">Email Subject <span style="color: #ef4444;">*</span></label>
                <input type="text" id="bulk_subject" class="comm-input" placeholder="e.g. Exclusive Loan Opportunity for Prime Borrowers" required>
            </div>
            <div style="margin-bottom: 20px;">
                <label class="comm-label">Message Content (HTML Supported) <span style="color: #ef4444;">*</span></label>
                <textarea id="bulk_body" rows="6" class="comm-input" placeholder="Write message to send to all leads in this segment..." required style="resize: vertical;"></textarea>
            </div>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" onclick="closeBulkModal()" class="rec-action-pill" style="padding: 10px 18px; font-size: 13px;">Cancel</button>
                <button type="submit" style="padding: 10px 22px; background: #0f172a; color: #ffffff; border: none; border-radius: 8px; font-weight: 800; font-size: 13px; cursor: pointer;">Dispatch Bulk Emails</button>
            </div>
        </form>
    </div>
</div>

<script>
let quillEmailEditor = null;
let tomSelectRecipient = null;
let presetClientId = "<?= $preset_client_id; ?>";
let presetEmail = "<?= htmlspecialchars($preset_email); ?>";
window.loadedRecipientsMap = {};

function switchCommTab(tab) {
    document.getElementById('tabBtnCompose').classList.toggle('active', tab === 'compose');
    const tabBulk = document.getElementById('tabBtnBulkCsv');
    if (tabBulk) tabBulk.classList.toggle('active', tab === 'bulk_csv');
    const tabInbox = document.getElementById('tabBtnInbox');
    if (tabInbox) tabInbox.classList.toggle('active', tab === 'inbox');
    document.getElementById('tabBtnHistory').classList.toggle('active', tab === 'history');

    document.getElementById('tabContentCompose').style.display = tab === 'compose' ? 'block' : 'none';
    const contentBulk = document.getElementById('tabContentBulkCsv');
    if (contentBulk) contentBulk.style.display = tab === 'bulk_csv' ? 'block' : 'none';
    const contentInbox = document.getElementById('tabContentInbox');
    if (contentInbox) contentInbox.style.display = tab === 'inbox' ? 'block' : 'none';
    document.getElementById('tabContentHistory').style.display = tab === 'history' ? 'block' : 'none';

    if (tab === 'history') loadEmailLogs();
    if (tab === 'inbox') loadInboundReplies();
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function setDispatchMode(mode) {
    document.getElementById('modeSimulated').classList.toggle('active', mode === 'simulated');
    document.getElementById('modeSmtp').classList.toggle('active', mode === 'smtp');
    document.getElementById('dispatch_mode_input').value = mode;
    
    const btnText = document.getElementById('btnDispatchText');
    if (mode === 'smtp') {
        btnText.innerText = 'Dispatch Live via SMTP';
    } else {
        btnText.innerText = 'Dispatch Email (Simulated)';
    }
}

function toggleCcDrawer() {
    const drawer = document.getElementById('ccOptionsDrawer');
    if (drawer) {
        drawer.style.display = drawer.style.display === 'none' ? 'block' : 'none';
    }
}

async function loadAllRecipients() {
    try {
        const response = await fetch('api.php?api=get_all_recipients');
        const data = await response.json();
        if (!data.success) return;
        
        const emailSelect = document.getElementById('email-to-select');
        let options = [];

        // Active Applicants (Loan Operations)
        if (data.applicants && data.applicants.length > 0) {
            data.applicants.forEach(a => {
                const key = `applicant_${a.id}`;
                window.loadedRecipientsMap[key] = a;
                options.push({
                    id: a.id,
                    key: key,
                    name: a.customer_name,
                    sub: `File #${a.loan_id || a.id} • ${a.loan_type || 'Loan'} • ${a.mobile || 'No Mobile'}`,
                    group: 'Active Loan Applicants'
                });
            });
        }

        // Clients
        if (data.clients && data.clients.length > 0) {
            data.clients.forEach(c => {
                const key = `client_${c.id}`;
                window.loadedRecipientsMap[key] = c;
                options.push({
                    id: c.id,
                    key: key,
                    name: c.customer_name,
                    sub: `${c.email || c.mobile || 'No Email'} • Client Account`,
                    group: 'Client Accounts'
                });
            });
        }

        // Pre-Leads
        if (data.pre_leads && data.pre_leads.length > 0) {
            data.pre_leads.forEach(p => {
                const key = `prelead_${p.id}`;
                window.loadedRecipientsMap[key] = p;
                options.push({
                    id: p.id,
                    key: key,
                    name: p.customer_name,
                    sub: `${p.mobile || 'Pre-Lead'} • Raw Inquiries`,
                    group: 'Pre-Leads'
                });
            });
        }

        // Initialize TomSelect for instant search
        if (tomSelectRecipient) {
            tomSelectRecipient.destroy();
        }

        tomSelectRecipient = new TomSelect('#email-to-select', {
            options: options,
            optgroups: [
                { id: 'Active Loan Applicants', name: 'Active Loan Applicants' },
                { id: 'Client Accounts', name: 'Client Accounts' },
                { id: 'Pre-Leads', name: 'Pre-Leads' }
            ],
            optgroupField: 'group',
            labelField: 'name',
            valueField: 'id',
            searchField: ['name', 'sub'],
            placeholder: 'Search borrower name, phone, or loan ID...',
            render: {
                option: function(item, escape) {
                    return `<div>
                        <strong style="color:#0f172a; font-size:13.5px;">${escape(item.name)}</strong>
                        <div style="font-size:11.5px; color:#64748b;">${escape(item.sub)}</div>
                    </div>`;
                },
                item: function(item, escape) {
                    return `<div><strong>${escape(item.name)}</strong> <span style="font-size:11px; color:#64748b;">(${escape(item.sub)})</span></div>`;
                }
            },
            onChange: function(val) {
                onRecipientChanged(val);
            }
        });

        if (presetClientId) {
            tomSelectRecipient.setValue(presetClientId);
        }

    } catch (err) {
        console.warn('loadAllRecipients error:', err);
    }
}

function onRecipientChanged(val) {
    const card = document.getElementById('recipientProfileCard');
    if (!val) {
        card.style.display = 'none';
        return;
    }

    // Locate recipient in loadedRecipientsMap
    let rec = null;
    for (let k in window.loadedRecipientsMap) {
        if (window.loadedRecipientsMap[k].id == val) {
            rec = window.loadedRecipientsMap[k];
            break;
        }
    }

    if (rec) {
        document.getElementById('recCardName').innerText = rec.customer_name || 'Borrower';
        document.getElementById('recCardDetails').innerText = `File #${rec.loan_id || rec.id} | Mobile: ${rec.mobile || 'N/A'} | Email: ${rec.email || 'N/A'} | Product: ${rec.loan_type || 'Loan'}`;

        const waMob = (rec.mobile || '').replace(/[^0-9]/g, '');
        document.getElementById('recWaBtn').href = waMob ? `https://wa.me/91${waMob}` : '#';
        document.getElementById('recCallBtn').href = rec.mobile ? `tel:${rec.mobile}` : '#';
        card.style.display = 'flex';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    } else {
        card.style.display = 'none';
    }
}

function copyTrackingLink() {
    const val = tomSelectRecipient ? tomSelectRecipient.getValue() : null;
    let rec = null;
    for (let k in window.loadedRecipientsMap) {
        if (window.loadedRecipientsMap[k].id == val) {
            rec = window.loadedRecipientsMap[k];
            break;
        }
    }

    const token = rec && rec.tracking_token ? rec.tracking_token : 'demo-token';
    const url = window.location.origin + '/track.php?token=' + token;
    navigator.clipboard.writeText(url);
    
    if (typeof showNotification === 'function') {
        showNotification('Client tracking link copied to clipboard!', 'success');
    } else {
        alert('Client tracking link copied!');
    }
}

function insertShortcode(code) {
    if (!quillEmailEditor) return;
    const range = quillEmailEditor.getSelection(true) || { index: quillEmailEditor.getLength() };
    quillEmailEditor.insertText(range.index, code + ' ');
}

function applySubjectSuggestion(type) {
    const val = tomSelectRecipient ? tomSelectRecipient.getValue() : null;
    let rec = null;
    for (let k in window.loadedRecipientsMap) {
        if (window.loadedRecipientsMap[k].id == val) {
            rec = window.loadedRecipientsMap[k];
            break;
        }
    }
    const name = rec ? rec.customer_name : '[CUSTOMER_NAME]';
    const loanId = rec ? (rec.loan_id || rec.id) : '[LOAN_ID]';

    if (type === 'sanction') {
        document.getElementById('email-subject-input').value = `Formal Loan Sanction Approved - ${name} (File #${loanId})`;
    } else if (type === 'docs') {
        document.getElementById('email-subject-input').value = `Action Required: Pending KYC & Income Documents for Loan #${loanId}`;
    }
}

function insertSignatureBlock() {
    if (!quillEmailEditor) return;
    const html = `
        <br><br>
        <div style="border-top: 2px solid #0f172a; padding-top: 12px; margin-top: 18px; font-family: Arial, sans-serif;">
            <div style="font-weight: 800; font-size: 14px; color: #0f172a; text-transform: uppercase;">BFS FINANCIAL SERVICES</div>
            <div style="font-size: 11.5px; color: #64748b; margin-top: 2px;">Corporate Direct Selling Agent &amp; Banking Logistics</div>
            <div style="font-size: 11.5px; color: #0f172a; margin-top: 4px;">
                Email: support@bfsfinancial.com &nbsp;|&nbsp; Helpline: 1800-BFS-LOAN
            </div>
        </div>`;
    quillEmailEditor.clipboard.dangerouslyPasteHTML(quillEmailEditor.getLength(), html);
}

function insertTrackingButton() {
    if (!quillEmailEditor) return;
    const html = `
        <p style="text-align: center; margin: 18px 0;">
            <a href="[TRACKING_LINK]" style="background: #0f172a; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: 800; display: inline-block; text-transform: uppercase; font-size: 11.5px; letter-spacing: 0.5px;">
                View Live Application Status &amp; Upload Documents &rarr;
            </a>
        </p>`;
    quillEmailEditor.clipboard.dangerouslyPasteHTML(quillEmailEditor.getLength(), html);
}

// ==============================================
// 1-CLICK INSTANT EXECUTIVE TEMPLATES
// ==============================================
function loadInstantTemplate(type) {
    if (!quillEmailEditor) return;

    if (type === 'payout') {
        document.getElementById('email-subject-input').value = 'Brokerage Payout Claim Invoice - Loan File #[LOAN_ID] ([CUSTOMER_NAME])';
        quillEmailEditor.root.innerHTML = `
            <p>Dear Branch Manager,</p>
            <p>Please find below the brokerage payout claim details for the loan account disbursed under our direct selling agency code:</p>
            <ul>
                <li><strong>Borrower Name:</strong> [CUSTOMER_NAME]</li>
                <li><strong>Loan Account ID:</strong> #[LOAN_ID]</li>
                <li><strong>Loan Product:</strong> [LOAN_TYPE]</li>
                <li><strong>Disbursed Amount:</strong> [LOAN_AMOUNT]</li>
            </ul>
            <p>Kindly process the eligible commission payout release to our designated company bank account at your earliest convenience.</p>
            <p>Regards,<br><strong>BFS Circle Payout Desk</strong></p>
        `;
    } else if (type === 'sanction') {
        document.getElementById('email-subject-input').value = 'Congratulations! Your Loan Sanction Letter is Approved - Ref #[LOAN_ID]';
        quillEmailEditor.root.innerHTML = `
            <p>Dear [CUSTOMER_NAME],</p>
            <p>We are delighted to inform you that your <strong>[LOAN_TYPE]</strong> application (Ref: <strong>#[LOAN_ID]</strong>) has been officially <strong>SANCTIONED</strong> for <strong>[LOAN_AMOUNT]</strong>.</p>
            <p>Please click the secure button below to review your sanction letter, inspect approved terms, and accept your sanction to proceed with disbursal:</p>
            <p style="text-align: center; margin: 20px 0;">
                <a href="[TRACKING_LINK]" style="background: #059669; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: 800; display: inline-block;">
                    View &amp; Accept Sanction Letter &rarr;
                </a>
            </p>
            <p>Thank you for partnering with BFS Financial Services.</p>
            <p>Warm regards,<br><strong>[RM_NAME]</strong></p>
        `;
    } else if (type === 'docs') {
        document.getElementById('email-subject-input').value = 'Action Required: Pending Documents for Loan File #[LOAN_ID]';
        quillEmailEditor.root.innerHTML = `
            <p>Dear [CUSTOMER_NAME],</p>
            <p>This is a gentle reminder regarding your loan application <strong>#[LOAN_ID]</strong>. The bank credit underwriting team has requested the following pending documents to finalize your sanction:</p>
            <ul>
                <li>Latest 6 Months Updated Bank Statement</li>
                <li>Clear Pan Card &amp; Aadhaar Card Copy</li>
                <li>Latest 3 Months Salary Slips / ITR Returns</li>
            </ul>
            <p>You can upload your documents directly through your private vault using the link below:</p>
            <p style="text-align: center; margin: 18px 0;">
                <a href="[TRACKING_LINK]" style="background: #0f172a; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: 800; display: inline-block;">
                    Upload Pending Documents Now &rarr;
                </a>
            </p>
            <p>Warm regards,<br><strong>[RM_NAME]</strong></p>
        `;
    } else if (type === 'disburse') {
        document.getElementById('email-subject-input').value = 'Loan Disbursal Confirmation - File #[LOAN_ID] | BFS Financial Services';
        quillEmailEditor.root.innerHTML = `
            <p>Dear [CUSTOMER_NAME],</p>
            <p>We are pleased to confirm that the sanctioned loan amount of <strong>[LOAN_AMOUNT]</strong> has been successfully credited to your designated bank account under Loan Reference <strong>#[LOAN_ID]</strong>.</p>
            <p>Your repayment schedule and EMI mandate have been activated accordingly. You may access your complete customer vault and receipt documents anytime through your self-service portal:</p>
            <p style="text-align: center; margin: 18px 0;">
                <a href="[TRACKING_LINK]" style="background: #0f172a; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: 800; display: inline-block;">
                    Access Customer Vault &amp; Receipts &rarr;
                </a>
            </p>
            <p>Thank you for trusting BFS Financial Services.</p>
            <p>Warm regards,<br><strong>BFS Circle Operations</strong></p>
        `;
    } else if (type === 'tracking') {
        document.getElementById('email-subject-input').value = 'Your Private Loan Tracking Portal Invite - Ref #[LOAN_ID]';
        quillEmailEditor.root.innerHTML = `
            <p>Dear [CUSTOMER_NAME],</p>
            <p>Your loan file <strong>#[LOAN_ID]</strong> is currently being processed by BFS Financial Services. You can track live updates, view bank statuses, and securely upload documents 24/7 through your tracking link below:</p>
            <p style="text-align: center; margin: 18px 0;">
                <a href="[TRACKING_LINK]" style="background: #2563eb; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: 800; display: inline-block;">
                    Open My Live Tracking Portal &rarr;
                </a>
            </p>
            <p>Best regards,<br><strong>BFS Circle Executive Team</strong></p>
        `;
    }

    if (typeof showNotification === 'function') {
        showNotification('Template loaded! Click Dispatch or edit as needed.', 'info');
    }
}

// ==============================================
// SMART AI EMAIL COPILOT
// ==============================================
function quickAiFillPrompt(promptText) {
    const input = document.getElementById('ai_quick_topic_input');
    if (input) input.value = promptText;
    generateAiDraftInCard();
}

async function generateAiDraftInCard() {
    const topicInput = document.getElementById('ai_quick_topic_input');
    const customInstruction = topicInput ? topicInput.value.trim() : '';

    if (!customInstruction) {
        alert('Please enter an email topic or select a scenario chip above.');
        return;
    }

    const val = tomSelectRecipient ? tomSelectRecipient.getValue() : null;
    let rec = null;
    for (let k in window.loadedRecipientsMap) {
        if (window.loadedRecipientsMap[k].id == val) {
            rec = window.loadedRecipientsMap[k];
            break;
        }
    }
    const appId = rec ? rec.id : (presetClientId || 0);

    const btn = document.getElementById('ai_write_draft_btn');
    const oldHtml = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = `<i data-lucide="sparkles" class="spin" style="width:13px;"></i> Drafting...`;
    }

    try {
        const formData = new FormData();
        formData.append('applicant_id', appId);
        formData.append('stage_type', 'Custom');
        formData.append('custom_instruction', customInstruction);

        const res = await fetch('api.php?api=generate_ai_email', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data && data.success) {
            let finalSubject = data.subject || '';
            let finalBody = data.body || data.content || '';

            if (rec) {
                finalSubject = evaluateShortcodes(finalSubject, rec);
                finalBody = evaluateShortcodes(finalBody, rec);
            }

            document.getElementById('ai_card_subject_preview').value = finalSubject;
            document.getElementById('ai_card_body_preview').value = finalBody;

            const reviewCard = document.getElementById('ai_draft_review_card');
            if (reviewCard) reviewCard.style.display = 'block';
            if (typeof lucide !== 'undefined') lucide.createIcons();
        } else {
            alert(data.error || 'Could not generate AI draft.');
        }
    } catch (e) {
        alert('Connection error while generating AI draft.');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = oldHtml;
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
    }
}

function applyAiDraftToMainForm() {
    const subj = document.getElementById('ai_card_subject_preview').value;
    const body = document.getElementById('ai_card_body_preview').value;

    if (subj) {
        document.getElementById('email-subject-input').value = subj;
    }

    if (quillEmailEditor && body) {
        const formattedHtml = body.replace(/\n/g, '<br>');
        quillEmailEditor.root.innerHTML = `
            <div style="font-family: Arial, sans-serif; padding: 12px; color: #0f172a; line-height: 1.6;">
                ${formattedHtml}
            </div>
        `;
    }

    document.getElementById('ai_draft_review_card').style.display = 'none';

    if (typeof showNotification === 'function') {
        showNotification('AI draft applied to composer!', 'success');
    }
}

function evaluateShortcodes(text, rec) {
    if (!text) return '';
    const name = rec ? rec.customer_name : 'Customer';
    const loanId = rec ? (rec.loan_id || ('LOAN-' + rec.id)) : 'LOAN-1001';
    const loanType = rec ? (rec.loan_type || 'Personal Loan') : 'Personal Loan';
    const amount = rec ? (rec.loan_amount ? '₹' + Number(rec.loan_amount).toLocaleString('en-IN') : '₹5,000,000') : '₹5,000,000';
    const trackUrl = window.location.origin + '/track.php?token=' + (rec ? (rec.tracking_token || 'demo-token') : 'demo-token');
    const rm = rec ? (rec.assigned_to || 'BFS Executive') : 'BFS Executive';

    return text.replace(/\[CUSTOMER_NAME\]/g, name)
               .replace(/\[LOAN_ID\]/g, loanId)
               .replace(/\[LOAN_TYPE\]/g, loanType)
               .replace(/\[LOAN_AMOUNT\]/g, amount)
               .replace(/\[TRACKING_LINK\]/g, trackUrl)
               .replace(/\[RM_NAME\]/g, rm);
}

// ==============================================
// PREVIEW & DISPATCH
// ==============================================
function openLivePreviewModal() {
    const val = tomSelectRecipient ? tomSelectRecipient.getValue() : null;
    let rec = null;
    for (let k in window.loadedRecipientsMap) {
        if (window.loadedRecipientsMap[k].id == val) {
            rec = window.loadedRecipientsMap[k];
            break;
        }
    }

    const rawSubj = document.getElementById('email-subject-input').value || '(No Subject)';
    const rawBody = quillEmailEditor.root.innerHTML;

    document.getElementById('prevToText').innerText = rec ? `${rec.customer_name} (${rec.email || 'No email'})` : 'Select a Client';
    document.getElementById('prevSubjText').innerText = evaluateShortcodes(rawSubj, rec);
    document.getElementById('prevContentHtml').innerHTML = evaluateShortcodes(rawBody, rec);
    document.getElementById('emailPreviewModal').style.display = 'flex';
}

function closePreviewModal() {
    document.getElementById('emailPreviewModal').style.display = 'none';
}

async function dispatchEmail(e) {
    e.preventDefault();

    const hiddenBodyInput = document.getElementById('email-body-hidden');
    hiddenBodyInput.value = quillEmailEditor.root.innerHTML;

    const btn = document.getElementById('btnDispatchEmail');
    const btnText = document.getElementById('btnDispatchText');
    btn.disabled = true;
    btnText.innerText = 'Dispatching Email...';

    const form = document.getElementById('email-sender-form');
    const formData = new FormData(form);

    try {
        const response = await fetch('api.php?api=send_email', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (response.ok && data.success) {
            if (typeof showNotification === 'function') {
                showNotification(data.message || 'Email successfully dispatched and logged in audit history!', 'success');
            } else {
                alert(data.message || 'Email successfully dispatched!');
            }
            form.reset();
            quillEmailEditor.setText('');
            if (tomSelectRecipient) tomSelectRecipient.clear();
            document.getElementById('recipientProfileCard').style.display = 'none';
        } else {
            alert(data.error || 'Email dispatch failed. Please check fields.');
        }
    } catch (err) {
        alert('Server connection error while dispatching email.');
    } finally {
        btn.disabled = false;
        btnText.innerText = 'Dispatch Email Now';
    }
}

// ==============================================
// AUDIT LOGS
// ==============================================
function loadEmailLogs() {
    fetch('api.php?api=get_email_logs')
    .then(r => r.json())
    .then(d => {
        const tbody = document.getElementById('emailLogsTableBody');
        if (d.success && d.logs && d.logs.length > 0) {
            let html = '';
            d.logs.forEach(l => {
                html += `<tr>
                    <td style="padding: 12px 14px; font-size: 12px; color: #64748b; font-weight: 600;">${l.created_at || l.sent_at}</td>
                    <td style="padding: 12px 14px; font-size: 13.5px; font-weight: 800; color: #0f172a;">${l.company_name || 'Client #' + l.client_id}</td>
                    <td style="padding: 12px 14px;"><span style="background: #f1f5f9; color: #334155; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; border: 1px solid #e2e8f0;">${l.type}</span></td>
                    <td style="padding: 12px 14px; font-size: 13px; color: #1e293b;">${l.subject}</td>
                    <td style="padding: 12px 14px; font-size: 12px; color: #64748b;">${l.sent_by || 'System'}</td>
                    <td style="padding: 12px 14px; font-size: 12px; color: #64748b;">${l.attachment_name ? '<span style="color:#0f172a; font-weight:700;"><i data-lucide="paperclip" style="width:11px; height:11px; display:inline-block; vertical-align:middle; margin-right:3px;"></i>' + l.attachment_name + '</span>' : 'None'}</td>
                    <td style="padding: 12px 14px; text-align: right;">
                        <button onclick="viewEmailLogModal('${encodeURIComponent(l.subject)}', '${encodeURIComponent(l.body)}')" class="rec-action-pill" style="font-size: 11.5px; padding: 4px 10px;">View</button>
                    </td>
                </tr>`;
            });
            tbody.innerHTML = html;
        } else {
            tbody.innerHTML = `<tr><td colspan="7" style="text-align: center; padding: 30px; color: #94a3b8;">No email audit records found.</td></tr>`;
        }
    });
}

function viewEmailLogModal(subj, body) {
    document.getElementById('prevToText').innerText = 'Audit Record';
    document.getElementById('prevSubjText').innerText = decodeURIComponent(subj);
    document.getElementById('prevContentHtml').innerHTML = decodeURIComponent(body);
    document.getElementById('emailPreviewModal').style.display = 'flex';
}

function openBulkSegmentEmailModal() {
    fetch('api.php?api=get_prelead_segments')
    .then(r => r.json())
    .then(d => {
        const sel = document.getElementById('bulk_target_segment');
        let html = '<option value="">-- Choose Data Segment Pool --</option>';
        if (d.segments) {
            d.segments.forEach(s => {
                html += `<option value="${s.seg}">${s.seg} (${s.cnt} leads)</option>`;
            });
        }
        sel.innerHTML = html;
        document.getElementById('bulkSegmentEmailModal').style.display = 'flex';
    });
}

function closeBulkModal() {
    document.getElementById('bulkSegmentEmailModal').style.display = 'none';
}

function executeBulkSegmentEmail(e) {
    e.preventDefault();
    const seg = document.getElementById('bulk_target_segment').value;
    const subj = document.getElementById('bulk_subject').value;
    const body = document.getElementById('bulk_body').value;

    const fd = new FormData();
    fd.append('segment_name', seg);
    fd.append('subject', subj);
    fd.append('body', body);

    fetch('api.php?api=send_bulk_segment_email', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            if (typeof showNotification === 'function') {
                showNotification(d.message || 'Bulk emails dispatched successfully!', 'success');
            } else {
                alert(d.message || 'Bulk emails dispatched!');
            }
            closeBulkModal();
        } else {
            alert(d.error || 'Bulk dispatch failed.');
        }
    });
}

// ==============================================
// ENTERPRISE BULK CSV CAMPAIGN ENGINE JAVASCRIPT
// ==============================================
let campaignQueue = []; // [{ id, name, email, company, loan_type, amount, isValid, status, dispatchedAt, logMessage }]
let campaignCurrentIndex = 0;
let campaignRunning = false;
let campaignPaused = false;
let campaignCountdownInterval = null;
let campaignSelectedMode = 'simulated';

function handleCsvDrop(e) {
    e.preventDefault();
    const dropZone = document.getElementById('csvDropZone');
    if (dropZone) {
        dropZone.style.borderColor = '#cbd5e1';
        dropZone.style.background = '#f8fafc';
    }
    if (e.dataTransfer && e.dataTransfer.files.length > 0) {
        processCsvFile(e.dataTransfer.files[0]);
    }
}

function handleCampaignCsvUpload(e) {
    if (e.target.files && e.target.files.length > 0) {
        processCsvFile(e.target.files[0]);
    }
}

function processCsvFile(file) {
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function(evt) {
        const text = evt.target.result;
        parseCsvText(text, file.name);
    };
    reader.readAsText(file);
}

// Parses raw CSV text handling quotes, commas, CRLF
function parseCsvRows(text) {
    const lines = [];
    let row = [''];
    let inQuotes = false;

    for (let i = 0; i < text.length; i++) {
        const c = text[i];
        const next = text[i + 1];

        if (c === '"') {
            if (inQuotes && next === '"') {
                row[row.length - 1] += '"';
                i++;
            } else {
                inQuotes = !inQuotes;
            }
        } else if (c === ',' && !inQuotes) {
            row.push('');
        } else if ((c === '\r' || c === '\n') && !inQuotes) {
            if (c === '\r' && next === '\n') i++;
            lines.push(row);
            row = [''];
        } else {
            row[row.length - 1] += c;
        }
    }
    if (row.length > 1 || (row.length === 1 && row[0] !== '')) {
        lines.push(row);
    }
    return lines;
}

function parseCsvText(text, fileName) {
    fileName = fileName || 'contacts.csv';
    const rawRows = parseCsvRows(text.trim());
    if (rawRows.length === 0) {
        alert('The uploaded file is empty.');
        return;
    }

    const header = rawRows[0].map(h => (h || '').trim().toLowerCase());

    // Column detection
    let emailIdx = header.findIndex(h => h.includes('email') || h.includes('mail'));
    let nameIdx = header.findIndex(h => h.includes('name') || h.includes('customer') || h.includes('client') || h.includes('borrower'));
    let compIdx = header.findIndex(h => h.includes('comp') || h.includes('business') || h.includes('org'));
    let loanIdx = header.findIndex(h => h.includes('loan') || h.includes('product') || h.includes('type'));
    let amtIdx = header.findIndex(h => h.includes('amount') || h.includes('budget') || h.includes('val'));

    // Fallback: If no header found, search first row for '@'
    let startRow = 1;
    if (emailIdx === -1) {
        emailIdx = rawRows[0].findIndex(cell => cell.includes('@'));
        if (emailIdx !== -1) {
            startRow = 0;
        } else if (rawRows.length > 1) {
            emailIdx = rawRows[1].findIndex(cell => cell.includes('@'));
        }
    }

    if (emailIdx === -1) {
        alert('Could not find an "email" column in the uploaded CSV. Please check the file format or download the sample template.');
        return;
    }

    campaignQueue = [];
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    let validCount = 0;
    let invalidCount = 0;

    for (let i = startRow; i < rawRows.length; i++) {
        const row = rawRows[i];
        if (!row || row.length === 0 || (row.length === 1 && !row[0].trim())) continue;

        const email = (row[emailIdx] || '').trim();
        const name = nameIdx !== -1 ? (row[nameIdx] || '').trim() : '';
        const company = compIdx !== -1 ? (row[compIdx] || '').trim() : '';
        const loanType = loanIdx !== -1 ? (row[loanIdx] || '').trim() : 'Business Loan';
        const amount = amtIdx !== -1 ? (row[amtIdx] || '').trim() : '₹25,00,000';

        const isValid = emailRegex.test(email);
        if (isValid) validCount++; else invalidCount++;

        campaignQueue.push({
            id: campaignQueue.length + 1,
            name: name || 'Valued Client',
            email: email,
            company: company || 'Enterprise Account',
            loan_type: loanType || 'Business Loan',
            amount: amount || '₹25,00,000',
            isValid: isValid,
            status: isValid ? 'QUEUED' : 'INVALID',
            dispatchedAt: null,
            logMessage: isValid ? 'In Queue' : 'Invalid email format'
        });
    }

    if (campaignQueue.length === 0) {
        alert('No data rows found in CSV.');
        return;
    }

    // Update UI
    document.getElementById('csvSummaryBanner').style.display = 'block';
    document.getElementById('csvFileNameBadge').innerText = fileName;
    document.getElementById('csvValidBadge').innerText = `${validCount} Valid Emails`;

    const invBadge = document.getElementById('csvInvalidBadge');
    if (invalidCount > 0) {
        invBadge.style.display = 'inline-block';
        invBadge.innerText = `${invalidCount} Skipped (Invalid)`;
    } else {
        invBadge.style.display = 'none';
    }

    renderCsvPreviewTable();
    campaignCurrentIndex = 0;
    updateCampaignStatsDisplay();
    logCampaignTerminal(`[CSV_IMPORT] File "${fileName}" parsed successfully (${validCount} valid contacts, ${invalidCount} skipped).`, 'success');
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function renderCsvPreviewTable() {
    const tbody = document.getElementById('csvPreviewTableBody');
    if (!tbody) return;

    let html = '';
    campaignQueue.slice(0, 50).forEach((item, idx) => {
        let statusBadge = '';
        if (!item.isValid) {
            statusBadge = `<span style="background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; padding:2px 7px; border-radius:4px; font-weight:700;">Invalid</span>`;
        } else if (item.status === 'SENT') {
            statusBadge = `<span style="background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0; padding:2px 7px; border-radius:4px; font-weight:700;">Sent</span>`;
        } else if (item.status === 'FAILED') {
            statusBadge = `<span style="background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; padding:2px 7px; border-radius:4px; font-weight:700;">Failed</span>`;
        } else {
            statusBadge = `<span style="background:#f8fafc; color:#334155; border:1px solid #cbd5e1; padding:2px 7px; border-radius:4px; font-weight:700;">Ready</span>`;
        }

        html += `
            <tr style="border-bottom: 1px solid #f1f5f9;" id="row-contact-${item.id}">
                <td style="padding: 7px 10px; color: #64748b;">${idx + 1}</td>
                <td style="padding: 7px 10px; font-weight: 700; color: #0f172a;">${escapeHtml(item.name)}</td>
                <td style="padding: 7px 10px; color: #0f172a; font-family: monospace;">${escapeHtml(item.email)}</td>
                <td style="padding: 7px 10px; color: #475569;">${escapeHtml(item.company)} (${escapeHtml(item.loan_type)})</td>
                <td style="padding: 7px 10px; text-align: right;" id="cell-status-${item.id}">${statusBadge}</td>
            </tr>
        `;
    });
    if (campaignQueue.length > 50) {
        html += `<tr><td colspan="5" style="text-align: center; padding: 10px; color: #64748b; font-style: italic; background: #f8fafc;">... and ${campaignQueue.length - 50} more contacts loaded</td></tr>`;
    }
    tbody.innerHTML = html;
}

function updateContactRowStatus(contact) {
    const cell = document.getElementById(`cell-status-${contact.id}`);
    if (!cell) return;
    if (contact.status === 'SENT') {
        cell.innerHTML = `<span style="background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0; padding:2px 7px; border-radius:4px; font-weight:700;">Sent</span>`;
    } else if (contact.status === 'FAILED') {
        cell.innerHTML = `<span style="background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; padding:2px 7px; border-radius:4px; font-weight:700;">Failed</span>`;
    }
}

function toggleCsvPreviewTable() {
    const container = document.getElementById('csvPreviewTableContainer');
    const text = document.getElementById('toggleTableText');
    if (container.style.display === 'none') {
        container.style.display = 'block';
        text.innerText = 'Hide Table';
    } else {
        container.style.display = 'none';
        text.innerText = 'View Table';
    }
}

function clearLoadedCsv() {
    if (campaignRunning) {
        alert('Please stop the current campaign before clearing contacts.');
        return;
    }
    campaignQueue = [];
    campaignCurrentIndex = 0;
    document.getElementById('csvSummaryBanner').style.display = 'none';
    document.getElementById('csvPreviewTableContainer').style.display = 'none';
    document.getElementById('campaignCsvFileInput').value = '';
    updateCampaignStatsDisplay();
    logCampaignTerminal('[INFO] Contact list cleared.', 'info');
}

function loadSampleCsvDataIntoCampaign() {
    const sampleText = `name,email,company,loan_type,amount
Rajesh Sharma,rajesh.sharma@example.com,Sharma Industries,Business Loan,2500000
Priya Patel,priya.patel@example.com,Patel Logistics,Home Loan,4500000
Amit Verma,amit.verma@example.com,Verma Tech Labs,Personal Loan,800000
Sneha Kulkarni,sneha.k@example.com,Kulkarni Retail,Working Capital,1500000
Vikram Singh,vikram.singh@example.com,Singh Motors,Commercial Vehicle,3200000
Ananya Deshmukh,ananya.d@example.com,Deshmukh Traders,Loan Against Property,5000000
Rohan Gupta,rohan.gupta@example.com,Gupta Exports,Machinery Loan,1800000
Kavita Nair,kavita.nair@example.com,Nair Enterprises,Professional Loan,1200000`;

    parseCsvText(sampleText, 'sample_email_campaign.csv');
    if (typeof showNotification === 'function') {
        showNotification('Sample 8 leads loaded into Campaign Engine!', 'success');
    }
}

function insertCampaignTag(tag) {
    const textarea = document.getElementById('campaign_body');
    if (!textarea) return;

    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const text = textarea.value;
    textarea.value = text.substring(0, start) + tag + text.substring(end);
    textarea.selectionStart = textarea.selectionEnd = start + tag.length;
    textarea.focus();
}

function loadCampaignPresetTemplate(type) {
    const subjInput = document.getElementById('campaign_subject');
    const bodyInput = document.getElementById('campaign_body');

    if (type === 'pitch') {
        subjInput.value = 'Special Pre-Approved Business Loan Facility for {name} ({company})';
        bodyInput.value = `Dear {name},

We are pleased to present an exclusive corporate funding facility customized for {company}.

Under our current banking partnership program, you are eligible for pre-sanctioned {loan_type} funding up to {amount} with attractive interest rates and zero upfront evaluation charges.

Key Highlights of the Facility:
• Fast-track sanction within 48 banking hours
• Flexible repayment tenure with tailored EMI schedules
• Minimal documentation requirement

If you would like our executive relationship manager to share the detailed term-sheet and bank sanction matrix, kindly reply to this email or contact our desk directly.

Warm regards,
BFS Circle Loan Operations Team
Email: support@bfsfinancial.com | Toll-Free: 1800-BFS-LOAN`;
    } else if (type === 'offer') {
        subjInput.value = 'Festive Credit Line Offer: Instant {loan_type} Approval for {company}';
        bodyInput.value = `Dear {name},

Celebrate business growth with our Festive Working Capital credit line for {company}.

Enjoy exclusive rate reductions and pre-approved limits of up to {amount} on {loan_type} facilities across 15+ premier banking partners.

• Zero Processing Fee on approvals finalized this month
• Dedicated relationship manager assistance
• Instant digital disbursement directly into your current account

Reply to this message with your preferred call timing, and our senior lending officer will connect with you.

Best regards,
BFS Financial Services Corporate Circle`;
    }
}

function validateDelayValues() {
    const minInput = document.getElementById('campaignMinDelay');
    const maxInput = document.getElementById('campaignMaxDelay');
    let min = parseInt(minInput.value) || 3;
    let max = parseInt(maxInput.value) || 8;

    if (min < 1) min = 1;
    if (max < min) max = min + 2;

    minInput.value = min;
    maxInput.value = max;
    document.getElementById('delayPreviewText').innerText = `${min}s and ${max}s`;
}

function selectCampaignMode(mode) {
    campaignSelectedMode = mode;
    const cardSim = document.getElementById('cardModeSimulated');
    const cardSmtp = document.getElementById('cardModeSmtp');
    const dotSim = document.getElementById('radioDotSimulated');
    const dotSmtp = document.getElementById('radioDotSmtp');

    if (mode === 'simulated') {
        if (cardSim) {
            cardSim.style.border = '2px solid #0f172a';
            cardSim.style.background = '#f8fafc';
        }
        if (cardSmtp) {
            cardSmtp.style.border = '1px solid #cbd5e1';
            cardSmtp.style.background = '#ffffff';
        }
        if (dotSim) {
            dotSim.style.borderColor = '#0f172a';
            dotSim.innerHTML = '<div style="width: 8px; height: 8px; border-radius: 50%; background: #0f172a;"></div>';
        }
        if (dotSmtp) {
            dotSmtp.style.borderColor = '#cbd5e1';
            dotSmtp.innerHTML = '<div style="width: 8px; height: 8px; border-radius: 50%; background: transparent;"></div>';
        }
        logCampaignTerminal('[MODE] Delivery Mode set to: SIMULATED RUN (Database audit only, zero outbound SMTP)', 'info');
    } else {
        if (cardSmtp) {
            cardSmtp.style.border = '2px solid #0f172a';
            cardSmtp.style.background = '#f8fafc';
        }
        if (cardSim) {
            cardSim.style.border = '1px solid #cbd5e1';
            cardSim.style.background = '#ffffff';
        }
        if (dotSmtp) {
            dotSmtp.style.borderColor = '#0f172a';
            dotSmtp.innerHTML = '<div style="width: 8px; height: 8px; border-radius: 50%; background: #0f172a;"></div>';
        }
        if (dotSim) {
            dotSim.style.borderColor = '#cbd5e1';
            dotSim.innerHTML = '<div style="width: 8px; height: 8px; border-radius: 50%; background: transparent;"></div>';
        }
        logCampaignTerminal('[MODE] Delivery Mode set to: LIVE SMTP MODE (Genuine emails will be sent via SMTP)', 'warn');
    }
}

function toggleCampaignModeRadio(mode) {
    selectCampaignMode(mode);
}

function updateCampaignStatsDisplay() {
    const totalValid = campaignQueue.filter(q => q.isValid).length;
    const sent = campaignQueue.filter(q => q.status === 'SENT').length;
    const failed = campaignQueue.filter(q => q.status === 'FAILED').length;
    const pending = totalValid - (sent + failed);

    document.getElementById('cntQueued').innerText = totalValid;
    document.getElementById('cntSent').innerText = sent;
    document.getElementById('cntFailed').innerText = failed;
    document.getElementById('cntPending').innerText = Math.max(0, pending);

    const pct = totalValid > 0 ? Math.round(((sent + failed) / totalValid) * 100) : 0;
    document.getElementById('campaignProgressBar').style.width = pct + '%';
    document.getElementById('campaignPercentText').innerText = `${pct}% (${sent + failed} / ${totalValid})`;
}

function setCampaignStatusPill(status) {
    const pill = document.getElementById('campaignStatusPill');
    if (!pill) return;
    pill.innerText = status;

    if (status === 'RUNNING') {
        pill.style.background = '#0f172a';
        pill.style.color = '#ffffff';
        pill.style.borderColor = '#0f172a';
    } else if (status === 'PAUSED') {
        pill.style.background = '#fef3c7';
        pill.style.color = '#92400e';
        pill.style.borderColor = '#fde68a';
    } else if (status === 'COMPLETED') {
        pill.style.background = '#f0fdf4';
        pill.style.color = '#15803d';
        pill.style.borderColor = '#bbf7d0';
    } else if (status === 'STOPPED') {
        pill.style.background = '#fef2f2';
        pill.style.color = '#b91c1c';
        pill.style.borderColor = '#fecaca';
    } else {
        pill.style.background = '#f1f5f9';
        pill.style.color = '#475569';
        pill.style.borderColor = '#cbd5e1';
    }
}

// ----------------------------------------------------
// CAMPAIGN EXECUTION DISPATCHER
// ----------------------------------------------------
function startCampaignExecution() {
    const validRecipients = campaignQueue.filter(q => q.isValid);
    if (validRecipients.length === 0) {
        alert('Please upload a CSV file with valid recipient email addresses, or click "Load Demo Leads".');
        return;
    }

    const subject = document.getElementById('campaign_subject').value.trim();
    const body = document.getElementById('campaign_body').value.trim();

    if (!subject || !body) {
        alert('Please provide both an Email Subject and Email Body content.');
        return;
    }

    validateDelayValues();
    campaignRunning = true;
    campaignPaused = false;
    campaignCurrentIndex = 0;

    // Reset items status
    campaignQueue.forEach(item => {
        if (item.isValid) {
            item.status = 'QUEUED';
            item.dispatchedAt = null;
            item.logMessage = 'In Queue';
        }
    });

    // Update buttons
    const btnStart = document.getElementById('btnStartCampaign');
    const btnPause = document.getElementById('btnPauseCampaign');
    const btnResume = document.getElementById('btnResumeCampaign');
    const btnStop = document.getElementById('btnStopCampaign');
    const btnExport = document.getElementById('btnExportReport');

    btnStart.disabled = true;
    btnStart.style.opacity = '0.5';
    btnStart.style.cursor = 'not-allowed';

    btnPause.disabled = false;
    btnPause.style.opacity = '1';
    btnPause.style.cursor = 'pointer';
    btnPause.style.display = 'flex';

    btnResume.style.display = 'none';

    btnStop.disabled = false;
    btnStop.style.opacity = '1';
    btnStop.style.cursor = 'pointer';

    btnExport.disabled = true;
    btnExport.style.opacity = '0.5';
    btnExport.style.cursor = 'not-allowed';

    setCampaignStatusPill('RUNNING');
    updateCampaignStatsDisplay();
    renderCsvPreviewTable();

    const minSec = parseInt(document.getElementById('campaignMinDelay').value) || 3;
    const maxSec = parseInt(document.getElementById('campaignMaxDelay').value) || 8;
    logCampaignTerminal(`[INIT] Campaign Started: ${validRecipients.length} recipients queued. [Mode: ${campaignSelectedMode.toUpperCase()} | Delay: ${minSec}s - ${maxSec}s]`, 'success');

    dispatchNextCampaignEmail();
}

function dispatchNextCampaignEmail() {
    if (!campaignRunning) return;

    if (campaignPaused) {
        setCampaignStatusPill('PAUSED');
        document.getElementById('countdownMessage').innerText = 'Campaign paused. Click Resume to continue.';
        document.getElementById('countdownClock').innerText = '--';
        return;
    }

    // Find next valid queued recipient
    while (campaignCurrentIndex < campaignQueue.length && !campaignQueue[campaignCurrentIndex].isValid) {
        campaignCurrentIndex++;
    }

    if (campaignCurrentIndex >= campaignQueue.length) {
        finishCampaign();
        return;
    }

    const contact = campaignQueue[campaignCurrentIndex];
    const minSec = parseInt(document.getElementById('campaignMinDelay').value) || 3;
    const maxSec = parseInt(document.getElementById('campaignMaxDelay').value) || 8;

    // First email has short delay (1s), subsequent emails have random delay
    let delaySec = 1.0;
    if (campaignCurrentIndex > 0) {
        delaySec = (Math.random() * (maxSec - minSec) + minSec);
    }
    const delayMs = Math.round(delaySec * 1000);
    let remainingMs = delayMs;

    document.getElementById('countdownMessage').innerText = `Next dispatch to "${contact.email}"...`;
    document.getElementById('countdownClock').innerText = (remainingMs / 1000).toFixed(1) + 's';

    clearInterval(campaignCountdownInterval);
    campaignCountdownInterval = setInterval(() => {
        if (!campaignRunning || campaignPaused) {
            clearInterval(campaignCountdownInterval);
            return;
        }

        remainingMs -= 100;
        if (remainingMs <= 0) {
            clearInterval(campaignCountdownInterval);
            document.getElementById('countdownClock').innerText = '0.0s';
            executeSingleDispatch(contact, delaySec.toFixed(1));
        } else {
            document.getElementById('countdownClock').innerText = (remainingMs / 1000).toFixed(1) + 's';
        }
    }, 100);
}

async function executeSingleDispatch(contact, delayUsedSec) {
    if (!campaignRunning || campaignPaused) return;

    const rawSubject = document.getElementById('campaign_subject').value.trim();
    const rawBody = document.getElementById('campaign_body').value.trim();
    const campaignTitle = document.getElementById('campaign_internal_title').value.trim() || 'Bulk CSV Campaign';

    // Personalize Subject & Body
    const persSubject = applyCampaignMergeTags(rawSubject, contact);
    const persBody = applyCampaignMergeTags(rawBody, contact);

    document.getElementById('countdownMessage').innerText = `Sending email to ${contact.email}...`;

    try {
        const fd = new FormData();
        fd.append('recipient_email', contact.email);
        fd.append('recipient_name', contact.name);
        fd.append('subject', persSubject);
        fd.append('body', persBody);
        fd.append('campaign_name', campaignTitle);
        fd.append('dispatch_mode', campaignSelectedMode);

        const response = await fetch('api.php?api=send_campaign_single_email', {
            method: 'POST',
            body: fd
        });
        const res = await response.json();

        if (res && res.success) {
            contact.status = 'SENT';
            contact.dispatchedAt = new Date().toLocaleTimeString();
            contact.logMessage = res.message || 'Dispatched';
            logCampaignTerminal(`[SENT] [${campaignCurrentIndex + 1}/${campaignQueue.length}] Dispatched to ${contact.email} (${contact.name}) | Delay: ${delayUsedSec}s | Status: OK`, 'success');
        } else {
            contact.status = 'FAILED';
            contact.dispatchedAt = new Date().toLocaleTimeString();
            contact.logMessage = (res && res.error) ? res.error : 'Dispatch error';
            logCampaignTerminal(`[FAILED] [${campaignCurrentIndex + 1}/${campaignQueue.length}] Failed for ${contact.email}: ${contact.logMessage}`, 'error');
        }
    } catch (err) {
        contact.status = 'FAILED';
        contact.dispatchedAt = new Date().toLocaleTimeString();
        contact.logMessage = 'Network connection error';
        logCampaignTerminal(`[ERROR] [${campaignCurrentIndex + 1}/${campaignQueue.length}] Connection error for ${contact.email}`, 'error');
    }

    updateContactRowStatus(contact);
    updateCampaignStatsDisplay();
    campaignCurrentIndex++;

    if (campaignRunning && !campaignPaused) {
        dispatchNextCampaignEmail();
    }
}

function applyCampaignMergeTags(text, contact) {
    if (!text) return '';
    return text.replace(/\{name\}/gi, contact.name || 'Valued Customer')
               .replace(/\{email\}/gi, contact.email || '')
               .replace(/\{company\}/gi, contact.company || '')
               .replace(/\{loan_type\}/gi, contact.loan_type || 'Loan')
               .replace(/\{amount\}/gi, contact.amount || '');
}

function pauseCampaignExecution() {
    if (!campaignRunning) return;
    clearInterval(campaignCountdownInterval);
    campaignPaused = true;
    setCampaignStatusPill('PAUSED');

    const btnPause = document.getElementById('btnPauseCampaign');
    const btnResume = document.getElementById('btnResumeCampaign');
    btnPause.style.display = 'none';
    btnResume.style.display = 'flex';

    logCampaignTerminal(`[PAUSED] Campaign paused by operator at contact #${campaignCurrentIndex + 1}.`, 'warn');
}

function resumeCampaignExecution() {
    if (!campaignRunning) return;
    campaignPaused = false;
    setCampaignStatusPill('RUNNING');

    const btnPause = document.getElementById('btnPauseCampaign');
    const btnResume = document.getElementById('btnResumeCampaign');
    btnResume.style.display = 'none';
    btnPause.style.display = 'flex';

    logCampaignTerminal(`[RESUMED] Resuming campaign from contact #${campaignCurrentIndex + 1}...`, 'info');
    dispatchNextCampaignEmail();
}

function stopCampaignExecution() {
    if (!campaignRunning && campaignQueue.length === 0) return;

    if (!confirm('Are you sure you want to stop the campaign queue?')) return;

    clearInterval(campaignCountdownInterval);
    campaignRunning = false;
    campaignPaused = false;

    setCampaignStatusPill('STOPPED');
    document.getElementById('countdownMessage').innerText = 'Campaign stopped by operator.';
    document.getElementById('countdownClock').innerText = '--';

    const btnStart = document.getElementById('btnStartCampaign');
    const btnPause = document.getElementById('btnPauseCampaign');
    const btnResume = document.getElementById('btnResumeCampaign');
    const btnStop = document.getElementById('btnStopCampaign');
    const btnExport = document.getElementById('btnExportReport');

    btnStart.disabled = false;
    btnStart.style.opacity = '1';
    btnStart.style.cursor = 'pointer';

    btnPause.disabled = true;
    btnPause.style.opacity = '0.5';
    btnPause.style.cursor = 'not-allowed';
    btnPause.style.display = 'flex';

    btnResume.style.display = 'none';

    btnStop.disabled = true;
    btnStop.style.opacity = '0.5';
    btnStop.style.cursor = 'not-allowed';

    btnExport.disabled = false;
    btnExport.style.opacity = '1';
    btnExport.style.cursor = 'pointer';

    logCampaignTerminal('[STOPPED] Campaign cancelled by operator.', 'warn');
}

function finishCampaign() {
    clearInterval(campaignCountdownInterval);
    campaignRunning = false;
    campaignPaused = false;

    setCampaignStatusPill('COMPLETED');
    document.getElementById('countdownMessage').innerText = 'All campaign emails have been processed!';
    document.getElementById('countdownClock').innerText = 'Done';

    const btnStart = document.getElementById('btnStartCampaign');
    const btnPause = document.getElementById('btnPauseCampaign');
    const btnResume = document.getElementById('btnResumeCampaign');
    const btnStop = document.getElementById('btnStopCampaign');
    const btnExport = document.getElementById('btnExportReport');

    btnStart.disabled = false;
    btnStart.style.opacity = '1';
    btnStart.style.cursor = 'pointer';

    btnPause.disabled = true;
    btnPause.style.opacity = '0.5';
    btnPause.style.cursor = 'not-allowed';
    btnPause.style.display = 'flex';

    btnResume.style.display = 'none';

    btnStop.disabled = true;
    btnStop.style.opacity = '0.5';
    btnStop.style.cursor = 'not-allowed';

    btnExport.disabled = false;
    btnExport.style.opacity = '1';
    btnExport.style.cursor = 'pointer';

    const sent = campaignQueue.filter(q => q.status === 'SENT').length;
    const failed = campaignQueue.filter(q => q.status === 'FAILED').length;
    logCampaignTerminal(`[COMPLETED] Campaign Finished: Dispatched: ${sent} | Failed: ${failed}. Delivery report ready for download.`, 'success');

    if (typeof showNotification === 'function') {
        showNotification(`Campaign Completed! ${sent} emails successfully processed.`, 'success');
    }
}

function exportCampaignReport() {
    if (campaignQueue.length === 0) {
        alert('No campaign data to export.');
        return;
    }

    let csvContent = 'data:text/csv;charset=utf-8,';
    csvContent += 'Index,Name,Email,Company,Loan Type,Amount,Status,Dispatched At,Notes\n';

    campaignQueue.forEach((item, idx) => {
        const row = [
            idx + 1,
            `"${(item.name || '').replace(/"/g, '""')}"`,
            `"${(item.email || '').replace(/"/g, '""')}"`,
            `"${(item.company || '').replace(/"/g, '""')}"`,
            `"${(item.loan_type || '').replace(/"/g, '""')}"`,
            `"${(item.amount || '').replace(/"/g, '""')}"`,
            `"${(item.status || 'PENDING')}"`,
            `"${(item.dispatchedAt || '')}"`,
            `"${(item.logMessage || '').replace(/"/g, '""')}"`
        ];
        csvContent += row.join(',') + '\n';
    });

    const encodedUri = encodeURI(csvContent);
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute('download', `campaign_report_${Date.now()}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    logCampaignTerminal('[EXPORT] Delivery report CSV downloaded to local disk.', 'info');
}

function logCampaignTerminal(msg, type) {
    type = type || 'info';
    const term = document.getElementById('campaignTerminalLog');
    if (!term) return;

    const time = new Date().toLocaleTimeString();
    let color = '#e2e8f0';
    if (type === 'success') color = '#4ade80';
    else if (type === 'error') color = '#f87171';
    else if (type === 'warn') color = '#fbbf24';
    else if (type === 'info') color = '#38bdf8';

    const line = document.createElement('div');
    line.style.color = color;
    line.style.marginBottom = '3px';
    line.innerHTML = `[${time}] ${escapeHtml(msg)}`;

    term.appendChild(line);
    term.scrollTop = term.scrollHeight;
}

function clearCampaignTerminal() {
    const term = document.getElementById('campaignTerminalLog');
    if (term) term.innerHTML = `<div style="color: #64748b;">[${new Date().toLocaleTimeString()}] Console cleared.</div>`;
}

function escapeHtml(text) {
    if (!text) return '';
    return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// ==============================================
// DYNAMIC TEMPLATE SYSTEM & SHORTCODES
// ==============================================
window.cachedDbTemplates = [];
window.activeTemplateFilter = 'all';

async function loadSavedDbTemplates() {
    const listContainer = document.getElementById('dynamicTemplatesList');
    if (!listContainer) return;

    try {
        const res = await fetch('api.php?api=get_templates');
        const data = await res.json();
        if (Array.isArray(data)) {
            window.cachedDbTemplates = data;
            renderDynamicTemplatesList();
        } else {
            listContainer.innerHTML = '<div style="color:#ef4444; font-size:12px; padding:10px;">Failed to load templates.</div>';
        }
    } catch (e) {
        listContainer.innerHTML = '<div style="color:#64748b; font-size:12px; padding:10px;">Unable to load templates.</div>';
    }
}

function filterTemplateCategory(category) {
    window.activeTemplateFilter = category;
    
    document.querySelectorAll('[id^="tmplFilter"]').forEach(btn => {
        btn.style.background = 'transparent';
        btn.style.color = '#64748b';
        btn.style.boxShadow = 'none';
    });
    
    let activeBtnId = 'tmplFilterAll';
    if (category === 'Sanction Approval') activeBtnId = 'tmplFilterSanction';
    else if (category === 'Document Request') activeBtnId = 'tmplFilterDocs';
    else if (category === 'custom') activeBtnId = 'tmplFilterCustom';
    
    const activeBtn = document.getElementById(activeBtnId);
    if (activeBtn) {
        activeBtn.style.background = '#ffffff';
        activeBtn.style.color = '#0f172a';
        activeBtn.style.boxShadow = '0 1px 3px rgba(0,0,0,0.05)';
    }
    
    renderDynamicTemplatesList();
}

function renderDynamicTemplatesList() {
    const listContainer = document.getElementById('dynamicTemplatesList');
    if (!listContainer) return;

    let templates = window.cachedDbTemplates || [];

    if (window.activeTemplateFilter !== 'all') {
        if (window.activeTemplateFilter === 'custom') {
            templates = templates.filter(t => t.id > 5);
        } else {
            templates = templates.filter(t => t.type === window.activeTemplateFilter);
        }
    }

    if (templates.length === 0) {
        listContainer.innerHTML = `
            <div style="text-align: center; padding: 20px; color: #94a3b8; font-size: 12px;">
                No templates found in this category.<br><button type="button" onclick="openSaveAsTemplateModal()" class="rec-action-pill" style="margin-top:8px; font-size:11.5px;">+ Create One</button>
            </div>`;
        return;
    }

    let html = '';
    templates.forEach(t => {
        const isCustom = t.id > 5;
        let badgeColor = '#eff6ff';
        let badgeTextColor = '#1d4ed8';

        if (t.type === 'Sanction Approval') {
            badgeColor = '#f0fdf4';
            badgeTextColor = '#15803d';
        } else if (t.type === 'Document Request') {
            badgeColor = '#fef2f2';
            badgeTextColor = '#b91c1c';
        } else if (t.type === 'Brokerage Claim') {
            badgeColor = '#f8fafc';
            badgeTextColor = '#334155';
        }

        html += `
            <div class="preset-action-item" onclick="applyDbTemplate(${t.id})" style="position: relative; margin-bottom: 8px;">
                <div style="flex: 1; padding-right: 8px;">
                    <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 2px;">
                        <span style="background: ${badgeColor}; color: ${badgeTextColor}; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 800; text-transform: uppercase;">
                            ${escapeHtml(t.type || 'Template')}
                        </span>
                        ${isCustom ? '<span style="background:#fef3c7; color:#92400e; padding:1px 5px; border-radius:3px; font-size:10px; font-weight:700;">Custom</span>' : ''}
                    </div>
                    <div style="font-weight: 700; font-size: 12.5px; color: #0f172a;">${escapeHtml(t.template_name)}</div>
                    <div style="font-size: 11px; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 220px;">
                        ${escapeHtml(t.subject || 'No Subject')}
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 4px;">
                    ${isCustom ? `
                        <button type="button" onclick="deleteCustomTemplate(${t.id}, '${escapeHtml(t.template_name)}', event)" title="Delete Template" style="background: none; border: none; color: #cbd5e1; cursor: pointer; padding: 4px; border-radius: 4px;" onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='#cbd5e1'">
                            <i data-lucide="trash-2" style="width: 13px; height: 13px;"></i>
                        </button>
                    ` : ''}
                    <i data-lucide="arrow-right" style="width: 14px; height: 14px; color: #94a3b8;"></i>
                </div>
            </div>
        `;
    });

    listContainer.innerHTML = html;
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function applyDbTemplate(templateId) {
    if (!quillEmailEditor) return;

    const tmpl = window.cachedDbTemplates.find(t => t.id == templateId);
    if (!tmpl) return;

    const val = tomSelectRecipient ? tomSelectRecipient.getValue() : null;
    let rec = null;
    if (val && window.loadedRecipientsMap) {
        for (let k in window.loadedRecipientsMap) {
            if (window.loadedRecipientsMap[k].id == val) {
                rec = window.loadedRecipientsMap[k];
                break;
            }
        }
    }

    let finalSubject = tmpl.subject || '';
    let finalBody = tmpl.body || '';

    if (rec) {
        finalSubject = evaluateShortcodes(finalSubject, rec);
        finalBody = evaluateShortcodes(finalBody, rec);
    }

    document.getElementById('email-subject-input').value = finalSubject;
    quillEmailEditor.root.innerHTML = finalBody;

    if (typeof showNotification === 'function') {
        const notice = rec ? `Template "${tmpl.template_name}" loaded with ${rec.customer_name}'s details!` : `Template "${tmpl.template_name}" loaded!`;
        showNotification(notice, 'success');
    }
}

function openSaveAsTemplateModal() {
    const rawSubject = document.getElementById('email-subject-input').value.trim();
    const rawBody = quillEmailEditor ? quillEmailEditor.root.innerHTML.trim() : '';

    document.getElementById('modal_tmpl_name').value = '';
    document.getElementById('modal_tmpl_subject').value = rawSubject;
    document.getElementById('modal_tmpl_body').value = rawBody;
    document.getElementById('saveTemplateModal').style.display = 'flex';
}

function closeSaveTemplateModal() {
    document.getElementById('saveTemplateModal').style.display = 'none';
}

async function submitSaveTemplate(e) {
    e.preventDefault();

    const name = document.getElementById('modal_tmpl_name').value.trim();
    const type = document.getElementById('modal_tmpl_type').value;
    const subject = document.getElementById('modal_tmpl_subject').value.trim();
    const body = document.getElementById('modal_tmpl_body').value.trim();

    if (!name || !subject || !body) {
        alert('Template Name, Subject, and Content are required.');
        return;
    }

    const btn = document.getElementById('btnSaveTemplateSubmit');
    btn.disabled = true;
    btn.innerHTML = 'Saving...';

    const fd = new FormData();
    fd.append('template_name', name);
    fd.append('type', type);
    fd.append('subject', subject);
    fd.append('body', body);

    try {
        const res = await fetch('api.php?api=save_template', {
            method: 'POST',
            body: fd
        });
        const data = await res.json();

        if (data && data.success) {
            closeSaveTemplateModal();
            if (typeof showNotification === 'function') {
                showNotification(`Template "${name}" saved successfully to your library!`, 'success');
            } else {
                alert('Template saved successfully!');
            }
            loadSavedDbTemplates();
        } else {
            alert(data.error || 'Failed to save template.');
        }
    } catch (err) {
        alert('Server connection error while saving template.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="check" style="width: 14px; height: 14px;"></i> Save Template';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }
}

async function deleteCustomTemplate(id, name, e) {
    if (e) e.stopPropagation();

    if (!confirm(`Are you sure you want to delete the template "${name}"?`)) {
        return;
    }

    try {
        const fd = new FormData();
        fd.append('id', id);

        const res = await fetch('api.php?api=delete_template', {
            method: 'POST',
            body: fd
        });
        const data = await res.json();

        if (data && data.success) {
            if (typeof showNotification === 'function') {
                showNotification(`Template "${name}" removed.`, 'info');
            }
            loadSavedDbTemplates();
        } else {
            alert(data.error || 'Could not delete template.');
        }
    } catch (err) {
        alert('Error communicating with server.');
    }
}

function autoFillShortcodesInComposer() {
    const val = tomSelectRecipient ? tomSelectRecipient.getValue() : null;
    if (!val) {
        if (typeof showNotification === 'function') {
            showNotification('Please select a recipient account first to auto-fill details!', 'warning');
        } else {
            alert('Please select a recipient first from the search box.');
        }
        return;
    }

    let rec = null;
    if (window.loadedRecipientsMap) {
        for (let k in window.loadedRecipientsMap) {
            if (window.loadedRecipientsMap[k].id == val) {
                rec = window.loadedRecipientsMap[k];
                break;
            }
        }
    }

    if (!rec) return;

    const subjInput = document.getElementById('email-subject-input');
    if (subjInput && subjInput.value) {
        subjInput.value = evaluateShortcodes(subjInput.value, rec);
    }

    if (quillEmailEditor) {
        const body = quillEmailEditor.root.innerHTML;
        quillEmailEditor.root.innerHTML = evaluateShortcodes(body, rec);
    }

    if (typeof showNotification === 'function') {
        showNotification(`All dynamic shortcodes replaced with ${rec.customer_name}'s details!`, 'success');
    }
}

// ==========================================
// INBOUND REPLIES & 2-WAY MAILBOX CONTROLLER
// ==========================================
let allInboundReplies = [];
let activeInboundReplyId = null;
let currentInboxFilter = 'all';

function escapeInboxHtml(text) {
    if (!text) return '';
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return String(text).replace(/[&<>"']/g, m => map[m]);
}

async function loadInboundReplies(showAlert = false) {
    try {
        const res = await fetch('api.php?api=get_inbound_replies');
        const data = await res.json();
        if (data && data.success) {
            allInboundReplies = data.replies || [];
            
            // Update unread badge in navigation pill
            const badge = document.getElementById('inboxUnreadBadge');
            if (badge) {
                const unreadCount = data.unread_count || 0;
                if (unreadCount > 0) {
                    badge.innerText = unreadCount;
                    badge.style.display = 'inline-block';
                } else {
                    badge.style.display = 'none';
                }
            }

            renderInboundRepliesList();

            // If an active thread was selected, re-select it with updated data
            if (activeInboundReplyId) {
                const stillExists = allInboundReplies.find(r => r.id == activeInboundReplyId);
                if (stillExists) {
                    selectInboundThread(activeInboundReplyId, false);
                }
            }

            if (showAlert && typeof showNotification === 'function') {
                showNotification(`Loaded ${allInboundReplies.length} inbound reply messages.`, 'success');
            }
        }
    } catch (err) {
        console.error('Failed to load inbound replies:', err);
    }
}

function setInboxFilter(status) {
    currentInboxFilter = status;
    const btnAll = document.getElementById('btnFilterAll');
    const btnUnread = document.getElementById('btnFilterUnread');
    const btnRead = document.getElementById('btnFilterRead');

    if (btnAll) {
        btnAll.style.background = status === 'all' ? '#0f172a' : '#ffffff';
        btnAll.style.color = status === 'all' ? '#ffffff' : '#475569';
    }
    if (btnUnread) {
        btnUnread.style.background = status === 'unread' ? '#0f172a' : '#ffffff';
        btnUnread.style.color = status === 'unread' ? '#ffffff' : '#475569';
    }
    if (btnRead) {
        btnRead.style.background = status === 'read' ? '#0f172a' : '#ffffff';
        btnRead.style.color = status === 'read' ? '#ffffff' : '#475569';
    }
    renderInboundRepliesList();
}

function filterInboundList() {
    renderInboundRepliesList();
}

function renderInboundRepliesList() {
    const listEl = document.getElementById('inboundRepliesList');
    if (!listEl) return;

    const query = (document.getElementById('inboxSearchInput')?.value || '').toLowerCase().trim();

    let filtered = allInboundReplies.filter(r => {
        // Status filter
        if (currentInboxFilter === 'unread' && r.is_read == 1) return false;
        if (currentInboxFilter === 'read' && r.is_read == 0) return false;

        // Query filter
        if (query) {
            const sender = (r.sender_email || '').toLowerCase();
            const subj = (r.subject || '').toLowerCase();
            const body = (r.body || '').toLowerCase();
            const client = (r.client_name || '').toLowerCase();
            if (!sender.includes(query) && !subj.includes(query) && !body.includes(query) && !client.includes(query)) {
                return false;
            }
        }
        return true;
    });

    if (filtered.length === 0) {
        listEl.innerHTML = `
            <div style="text-align: center; padding: 40px 16px; color: #94a3b8; font-size: 13px;">
                <i data-lucide="inbox" style="width: 32px; height: 32px; margin: 0 auto 10px auto; display: block; opacity: 0.5;"></i>
                No inbound replies found matching your filter.
            </div>
        `;
        if (typeof lucide !== 'undefined') lucide.createIcons();
        return;
    }

    let html = '';
    filtered.forEach(r => {
        const isSelected = r.id == activeInboundReplyId;
        const isUnread = r.is_read == 0;
        
        let clientBadgeText = '';
        if (r.client_name) {
            clientBadgeText = `<span style="background: #f1f5f9; color: #0f172a; font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 4px; border: 1px solid #e2e8f0;">${escapeInboxHtml(r.client_name)}</span>`;
        } else {
            clientBadgeText = `<span style="background: #f8fafc; color: #64748b; font-size: 10px; font-weight: 600; padding: 2px 6px; border-radius: 4px; border: 1px solid #e2e8f0;">Unlinked</span>`;
        }

        const dateStr = r.sent_at ? formatTimeAgo(r.sent_at) : '';
        const bodyPreview = (r.body || '').replace(/<[^>]*>?/gm, '').substring(0, 85);

        html += `
            <div onclick="selectInboundThread(${r.id})" style="padding: 12px 14px; border-radius: 10px; cursor: pointer; border: 1px solid ${isSelected ? '#0f172a' : (isUnread ? '#cbd5e1' : '#e2e8f0')}; background: ${isSelected ? '#f8fafc' : (isUnread ? '#ffffff' : '#f8fafc')}; box-shadow: ${isSelected ? '0 0 0 1px #0f172a' : 'none'}; transition: all 0.15s; position: relative;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                    <div style="font-weight: ${isUnread ? '800' : '600'}; font-size: 13px; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 210px;">
                        ${isUnread ? '<span style="display:inline-block; width:6px; height:6px; background:#0f172a; border-radius:50%; margin-right:6px;"></span>' : ''}
                        ${escapeInboxHtml(r.sender_email || 'Unknown Sender')}
                    </div>
                    <span style="font-size: 11px; color: #94a3b8; font-weight: 500;">${dateStr}</span>
                </div>
                <div style="font-size: 12.5px; font-weight: ${isUnread ? '700' : '500'}; color: ${isUnread ? '#1e293b' : '#475569'}; margin-bottom: 5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                    ${escapeInboxHtml(r.subject || '(No Subject)')}
                </div>
                <div style="font-size: 11.5px; color: #64748b; line-height: 1.4; margin-bottom: 8px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                    ${escapeInboxHtml(bodyPreview)}...
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    ${clientBadgeText}
                    ${r.reply_to_comm_id ? '<span style="font-size: 10px; color: #64748b; font-weight: 600; display: inline-flex; align-items: center; gap: 3px;"><i data-lucide="corner-down-left" style="width: 10px; height: 10px;"></i> Threaded</span>' : ''}
                </div>
            </div>
        `;
    });

    listEl.innerHTML = html;
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function formatTimeAgo(dateString) {
    if (!dateString) return '';
    try {
        const d = new Date(dateString.replace(/-/g, '/'));
        const now = new Date();
        const diffMs = now - d;
        const diffMins = Math.floor(diffMs / 60000);
        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return `${diffMins}m ago`;
        const diffHours = Math.floor(diffMins / 60);
        if (diffHours < 24) return `${diffHours}h ago`;
        const diffDays = Math.floor(diffHours / 24);
        if (diffDays < 7) return `${diffDays}d ago`;
        return d.toLocaleDateString('en-IN', { month: 'short', day: 'numeric' });
    } catch (e) {
        return dateString;
    }
}

function selectInboundThread(replyId, markAsRead = true) {
    activeInboundReplyId = replyId;
    const item = allInboundReplies.find(r => r.id == replyId);
    if (!item) return;

    renderInboundRepliesList();

    // Show Thread Reader UI
    const emptyState = document.getElementById('inboundEmptyState');
    const threadContent = document.getElementById('inboundThreadContent');
    if (emptyState) emptyState.style.display = 'none';
    if (threadContent) threadContent.style.display = 'block';

    // Populate Thread Details
    document.getElementById('threadSubject').innerText = item.subject || '(No Subject)';
    document.getElementById('threadSenderEmail').innerText = item.sender_email || 'Unknown';
    document.getElementById('threadReceivedTime').innerText = `Received: ${item.sent_at || 'Recently'}`;
    document.getElementById('threadReplyExactTime').innerText = item.sent_at || '';
    document.getElementById('threadReplyMessageBody').innerText = item.body || '(Empty Body)';

    // Client Badge & Link
    const badgeEl = document.getElementById('threadClientBadge');
    const profileLink = document.getElementById('threadProfileLink');
    if (item.client_name) {
        badgeEl.innerHTML = `• <span style="background: #f1f5f9; color: #0f172a; font-weight: 700; font-size: 11px; padding: 2px 8px; border-radius: 4px; border: 1px solid #e2e8f0;">Linked Account: ${escapeInboxHtml(item.client_name)}</span>`;
        if (item.client_id) {
            profileLink.href = `client_vault.php?client_id=${item.client_id}`;
            profileLink.style.display = 'inline-flex';
        } else {
            profileLink.style.display = 'none';
        }
    } else {
        badgeEl.innerHTML = `• <span style="background: #f8fafc; color: #64748b; font-weight: 600; font-size: 11px; padding: 2px 8px; border-radius: 4px; border: 1px solid #e2e8f0;">Unlinked Sender</span>`;
        profileLink.style.display = 'none';
    }

    // Mark as Read button text
    const btnRead = document.getElementById('btnToggleReadStatus');
    if (btnRead) {
        btnRead.innerHTML = item.is_read == 1 
            ? '<i data-lucide="eye-off" style="width: 13px; height: 13px;"></i> Mark Unread' 
            : '<i data-lucide="check" style="width: 13px; height: 13px;"></i> Mark Read';
    }

    // Parent Outbound Message Card
    const parentCard = document.getElementById('parentOutboundCard');
    const parentContent = document.getElementById('parentBodyContent');
    if (item.parent_body || item.parent_subject) {
        parentCard.style.display = 'block';
        document.getElementById('parentSenderName').innerText = item.parent_sent_by || 'Relationship Manager';
        document.getElementById('parentSentAt').innerText = item.parent_sent_at || 'Earlier';
        parentContent.innerHTML = `
            <div style="font-weight: 700; margin-bottom: 4px; color: #0f172a;">Subject: ${escapeInboxHtml(item.parent_subject || '')}</div>
            <div>${item.parent_body || ''}</div>
        `;
        parentContent.style.display = 'none';
        document.getElementById('btnToggleParent').innerText = 'Show Message ▼';
    } else {
        parentCard.style.display = 'none';
    }

    // Prefill Quick Reply Box
    document.getElementById('qrReplyToId').value = item.id;
    document.getElementById('qrRecipientEmail').value = item.sender_email || '';
    document.getElementById('qrClientId').value = item.client_id || '';
    let replySubj = item.subject || '';
    if (!replySubj.toLowerCase().startsWith('re:')) {
        replySubj = 'Re: ' + replySubj;
    }
    document.getElementById('qrSubject').value = replySubj;
    document.getElementById('qrMessageBody').value = '';

    // Mark read on backend if unread
    if (markAsRead && item.is_read == 0) {
        markReplyRead(item.id, 1);
    }

    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function toggleParentBody() {
    const el = document.getElementById('parentBodyContent');
    const btn = document.getElementById('btnToggleParent');
    if (!el || !btn) return;
    if (el.style.display === 'none') {
        el.style.display = 'block';
        btn.innerText = 'Hide Message ▲';
    } else {
        el.style.display = 'none';
        btn.innerText = 'Show Message ▼';
    }
}

async function markReplyRead(replyId, newStatus) {
    try {
        const fd = new FormData();
        fd.append('id', replyId);
        fd.append('is_read', newStatus);

        const res = await fetch('api.php?api=mark_reply_read', {
            method: 'POST',
            body: fd
        });
        const data = await res.json();
        if (data && data.success) {
            const item = allInboundReplies.find(r => r.id == replyId);
            if (item) item.is_read = newStatus;
            
            // Recount unread
            const unreadCount = allInboundReplies.filter(r => r.is_read == 0).length;
            const badge = document.getElementById('inboxUnreadBadge');
            if (badge) {
                if (unreadCount > 0) {
                    badge.innerText = unreadCount;
                    badge.style.display = 'inline-block';
                } else {
                    badge.style.display = 'none';
                }
            }
            renderInboundRepliesList();

            const btnRead = document.getElementById('btnToggleReadStatus');
            if (btnRead) {
                btnRead.innerHTML = newStatus == 1 
                    ? '<i data-lucide="eye-off" style="width: 13px; height: 13px;"></i> Mark Unread' 
                    : '<i data-lucide="check" style="width: 13px; height: 13px;"></i> Mark Read';
                if (typeof lucide !== 'undefined') lucide.createIcons();
            }
        }
    } catch (e) {
        console.error('Error updating read status:', e);
    }
}

function toggleCurrentReplyRead() {
    if (!activeInboundReplyId) return;
    const item = allInboundReplies.find(r => r.id == activeInboundReplyId);
    if (!item) return;
    const newStatus = item.is_read == 1 ? 0 : 1;
    markReplyRead(activeInboundReplyId, newStatus);
}

function quickFillReply(type) {
    const item = allInboundReplies.find(r => r.id == activeInboundReplyId);
    const clientName = item?.client_name || 'Customer';
    const textarea = document.getElementById('qrMessageBody');
    if (!textarea) return;

    if (type === 'docs') {
        textarea.value = `Dear ${clientName},\n\nThank you for reaching out. To proceed with the disbursement process, please provide your latest 3 months bank statements (PDF) and signed sanction agreement.\n\nWarm regards,\nCredit & Operations Team`;
    } else if (type === 'review') {
        textarea.value = `Dear ${clientName},\n\nWe have received your documents and message. Our underwriting team is currently evaluating the application. You will receive a status update within 24 hours.\n\nWarm regards,\nLoan Operations Desk`;
    } else if (type === 'approved') {
        textarea.value = `Dear ${clientName},\n\nWe are pleased to inform you that your loan proposal has received sanction approval! Our Relationship Manager will contact you shortly to coordinate signature and final disbursement.\n\nWarm regards,\nSanction Committee`;
    } else if (type === 'call') {
        textarea.value = `Dear ${clientName},\n\nThank you for your response. Our dedicated Relationship Manager would like to connect with you for 5 minutes to assist with the next steps. Please let us know a suitable time for the call.\n\nWarm regards,\nClient Services Team`;
    }
    textarea.focus();
}

async function sendInboundQuickReply(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSendInboundReply');
    const replyToId = document.getElementById('qrReplyToId').value;
    const recipientEmail = document.getElementById('qrRecipientEmail').value;
    const clientId = document.getElementById('qrClientId').value;
    const subject = document.getElementById('qrSubject').value.trim();
    const body = document.getElementById('qrMessageBody').value.trim();

    if (!recipientEmail || !subject || !body) {
        alert('Please fill out all reply fields.');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader" class="animate-spin" style="width: 14px; height: 14px;"></i> Sending Reply...';
    if (typeof lucide !== 'undefined') lucide.createIcons();

    try {
        const fd = new FormData();
        fd.append('reply_to_comm_id', replyToId);
        fd.append('recipient_email', recipientEmail);
        fd.append('client_id', clientId);
        fd.append('subject', subject);
        fd.append('body', body);

        const res = await fetch('api.php?api=quick_reply_to_inbound', {
            method: 'POST',
            body: fd
        });
        const data = await res.json();

        if (data && data.success) {
            if (typeof showNotification === 'function') {
                showNotification(`Follow-up reply dispatched successfully to ${recipientEmail}!`, 'success');
            } else {
                alert(`Follow-up reply dispatched successfully to ${recipientEmail}!`);
            }
            document.getElementById('qrMessageBody').value = '';
            // Refresh inbox to show updated status
            loadInboundReplies();
        } else {
            alert(data.error || 'Failed to send follow-up reply.');
        }
    } catch (err) {
        alert('Server connection error while sending reply.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="send" style="width: 14px; height: 14px;"></i> <span>Send Follow-Up Reply</span>';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }
}

// Modals: Simulate Reply & Webhook Info
function openSimulateReplyModal() {
    document.getElementById('simulateReplyModal').style.display = 'flex';
    // If a recipient is currently active, prefill their email
    const item = allInboundReplies.find(r => r.id == activeInboundReplyId);
    if (item && item.sender_email) {
        document.getElementById('sim_sender_email').value = item.sender_email;
    }
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function closeSimulateReplyModal() {
    document.getElementById('simulateReplyModal').style.display = 'none';
}

async function submitSimulateReply(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSubmitSimulateReply');
    const senderEmail = document.getElementById('sim_sender_email').value.trim();
    const subject = document.getElementById('sim_subject').value.trim();
    const body = document.getElementById('sim_body').value.trim();

    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader" class="animate-spin" style="width: 14px; height: 14px;"></i> Injecting...';
    if (typeof lucide !== 'undefined') lucide.createIcons();

    try {
        const fd = new FormData();
        fd.append('from', senderEmail);
        fd.append('subject', subject);
        fd.append('body', body);

        const res = await fetch('api.php?api=simulate_incoming_reply', {
            method: 'POST',
            body: fd
        });
        const data = await res.json();

        if (data && data.success) {
            closeSimulateReplyModal();
            if (typeof showNotification === 'function') {
                showNotification(`New inbound reply received from ${senderEmail}!`, 'success');
            }
            await loadInboundReplies();
            if (data.comm_id) {
                selectInboundThread(data.comm_id);
            }
        } else {
            alert(data.error || 'Failed to simulate incoming reply.');
        }
    } catch (err) {
        alert('Server communication error.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="send" style="width: 14px; height: 14px;"></i> Inject Test Reply';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }
}

function openWebhookInfoModal() {
    const loc = window.location;
    const webhookUrl = `${loc.protocol}//${loc.host}/api.php?api=inbound_email_webhook`;
    const disp = document.getElementById('webhookUrlDisplay');
    if (disp) disp.innerText = webhookUrl;
    document.getElementById('webhookInfoModal').style.display = 'flex';
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function closeWebhookInfoModal() {
    document.getElementById('webhookInfoModal').style.display = 'none';
}

// Initial Load
document.addEventListener('DOMContentLoaded', () => {
    quillEmailEditor = new Quill('#email-body-editor', {
        theme: 'snow',
        placeholder: 'Compose official loan proposal, sanction letter, or brokerage claim email here...',
        modules: {
            toolbar: [
                ['bold', 'italic', 'underline', 'strike'],
                [{ 'header': [1, 2, 3, false] }],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                [{ 'color': [] }, { 'background': [] }],
                ['clean']
            ]
        }
    });

    loadAllRecipients();
    loadSavedDbTemplates();
    loadInboundReplies();
    if (typeof lucide !== 'undefined') lucide.createIcons();
});
</script>

<?php require_once 'footer.php'; ?>
