<?php
define('IS_SUBFOLDER', true);
require_once __DIR__ . '/../config.php';
require_role(['Staff']);
header("Location: index.php");
exit;
?>

$current_rm = $_SESSION['username'] ?? 'RM';
$current_name = $_SESSION['user_full_name'] ?? $current_rm;
?>

<style>
/* Modern RM Loan Command Center Styles */
:root {
    --rm-primary: #0f172a;
    --rm-accent: #0284c7;
    --rm-teal: #0f766e;
    --rm-emerald: #10b981;
    --rm-amber: #f59e0b;
    --rm-rose: #f43f5e;
}

.rm-hero-card {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    border-radius: 16px;
    padding: 24px;
    color: #ffffff;
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.2);
    margin-bottom: 24px;
    position: relative;
    overflow: hidden;
}

.rm-hero-card::after {
    content: '';
    position: absolute;
    top: -50px;
    right: -50px;
    width: 200px;
    height: 200px;
    background: radial-gradient(circle, rgba(56, 189, 248, 0.15) 0%, rgba(255,255,255,0) 70%);
    border-radius: 50%;
    pointer-events: none;
}

.rm-target-progress-bar {
    height: 10px;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 10px;
    overflow: hidden;
    position: relative;
    margin: 12px 0 6px 0;
}

.rm-target-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #38bdf8 0%, #10b981 100%);
    border-radius: 10px;
    transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1);
}

.rm-metric-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.rm-stat-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 18px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.03);
    transition: all 0.2s ease;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.rm-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.06);
    border-color: #cbd5e1;
}

.action-trigger-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.action-trigger-card {
    background: #ffffff;
    border-radius: 12px;
    padding: 16px;
    border: 1px solid #e2e8f0;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 14px;
    position: relative;
    overflow: hidden;
}

.action-trigger-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(0,0,0,0.06);
}

.action-trigger-card.active-filter {
    border: 2px solid #0284c7;
    background: #f0f9ff;
}

.trigger-icon-box {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

/* 5-Stage Funnel Strip */
.loan-funnel-strip {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 10px;
    background: #ffffff;
    padding: 14px;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    margin-bottom: 24px;
}

.funnel-step {
    padding: 10px 12px;
    border-radius: 8px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    cursor: pointer;
    transition: all 0.2s;
    text-align: center;
}

.funnel-step:hover, .funnel-step.active {
    background: #0f172a;
    color: #ffffff;
    border-color: #0f172a;
}

.funnel-step.active .funnel-step-name {
    color: #94a3b8;
}

.funnel-step.active .funnel-step-count {
    color: #38bdf8;
}

.funnel-step-count {
    font-size: 20px;
    font-weight: 800;
    color: #0f172a;
    font-family: 'Outfit', sans-serif;
}

.funnel-step-name {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    color: #64748b;
    margin-top: 2px;
}

.badge-priority {
    font-size: 10px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 12px;
    text-transform: uppercase;
}
.priority-urgent { background: #ffe4e6; color: #e11d48; border: 1px solid #fecdd3; }
.priority-high { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
.priority-normal { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }

.table-container {
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    padding: 20px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.02);
}

.data-table th {
    background: #f8fafc;
    color: #475569;
    font-weight: 700;
    font-size: 12px;
    text-transform: uppercase;
    padding: 12px 14px;
    border-bottom: 1px solid #e2e8f0;
}

.data-table td {
    padding: 12px 14px;
    font-size: 13px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}

.data-table tr:hover td {
    background: #f8fafc;
}
</style>

<div class="view-container">
    <!-- Top Greeting & RM Header -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
        <div>
            <h1 style="font-size:22px; font-weight:800; color:#0f172a; margin:0; display:flex; align-items:center; gap:8px;">
                <i data-lucide="user-check" style="color:#0284c7; width:24px; height:24px;"></i>
                Namaste, <?= htmlspecialchars($current_name) ?>
                <span class="badge" style="background:#e0f2fe; color:#0369a1; border:1px solid #bae6fd; font-size:11px;">Dedicated Relationship Manager</span>
            </h1>
            <p style="font-size:13px; color:#64748b; margin:4px 0 0 0;">Manage your loan applicants, clear pendencies, record login fees, and drive disbursals.</p>
        </div>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <a href="../add_applicant.php" class="btn btn-primary" style="background:#0f172a; border:none; padding:8px 16px; font-size:13px; display:inline-flex; align-items:center; gap:6px;"><i data-lucide="user-plus" style="width:15px;"></i> + New Loan Application</a>
            <a href="../search_track.php" class="btn btn-secondary" style="padding:8px 14px; font-size:13px; display:inline-flex; align-items:center; gap:6px;"><i data-lucide="search" style="width:15px;"></i> Search & Track Cases</a>
            <a href="../reminders.php" class="btn btn-secondary" style="padding:8px 14px; font-size:13px; display:inline-flex; align-items:center; gap:6px;"><i data-lucide="bell" style="width:15px;"></i> Reminders</a>
            <a href="../calculator.php" class="btn btn-secondary" style="padding:8px 14px; font-size:13px; display:inline-flex; align-items:center; gap:6px;"><i data-lucide="calculator" style="width:15px;"></i> Loan Calculator</a>
        </div>
    </div>

    <!-- RM Hero Target & Incentive Scorecard -->
    <div class="rm-hero-card">
        <div style="display:grid; grid-template-columns: 1.5fr 1fr; gap:24px; align-items:center;">
            <div>
                <div style="display:flex; justify-content:space-between; align-items:baseline;">
                    <div>
                        <span style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#94a3b8;">Monthly Disbursal Target</span>
                        <div style="font-size:26px; font-weight:800; font-family:'Outfit', sans-serif; color:#ffffff;" id="hero-mtd-disbursed">₹0 <span style="font-size:15px; font-weight:500; color:#94a3b8;">/ <span id="hero-monthly-target">₹1.50 Cr</span></span></div>
                    </div>
                    <div style="text-align:right;">
                        <span style="font-size:18px; font-weight:800; color:#38bdf8;" id="hero-progress-pct">0%</span>
                        <div style="font-size:11px; color:#94a3b8;">Achieved MTD</div>
                    </div>
                </div>

                <div class="rm-target-progress-bar">
                    <div class="rm-target-progress-fill" id="hero-progress-bar" style="width: 0%;"></div>
                </div>

                <div style="display:flex; justify-content:space-between; font-size:12px; color:#cbd5e1; margin-top:8px;">
                    <div>Target Gap: <strong style="color:#f87171;" id="hero-target-gap">₹0</strong></div>
                    <div>Disbursed Cases: <strong style="color:#4ade80;" id="hero-disbursed-count">0</strong></div>
                </div>
            </div>

            <!-- Incentive Meter Box -->
            <div style="background:rgba(255,255,255,0.07); border:1px solid rgba(255,255,255,0.12); border-radius:12px; padding:16px 20px; display:flex; align-items:center; gap:16px;">
                <div style="width:48px; height:48px; border-radius:12px; background:rgba(16, 185, 129, 0.2); color:#4ade80; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <i data-lucide="wallet" style="width:24px; height:24px;"></i>
                </div>
                <div>
                    <span style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#94a3b8;">Est. Incentive Earned (MTD)</span>
                    <div style="font-size:22px; font-weight:800; font-family:'Outfit', sans-serif; color:#4ade80;" id="hero-incentive-earned">₹0</div>
                    <div style="font-size:11px; color:#cbd5e1;">Commission Rate: <strong id="hero-commission-rate">0.20%</strong></div>
                </div>
            </div>
        </div>
    </div>

    <!-- 4 High-Impact Daily Priority Triggers -->
    <div style="margin-bottom:12px;">
        <h2 style="font-size:14px; font-weight:800; text-transform:uppercase; letter-spacing:0.5px; color:#475569; margin-bottom:12px; display:flex; align-items:center; gap:6px;">
            <i data-lucide="zap" style="width:16px; color:#f59e0b;"></i> Daily Action Center (Priority Triggers)
        </h2>
    </div>
    
    <div class="action-trigger-grid">
        <!-- Trigger 1: Calls Due -->
        <div class="action-trigger-card" id="trigger-card-calls" onclick="filterByTrigger('calls')">
            <div class="trigger-icon-box" style="background:#eff6ff; color:#2563eb; border:1px solid #bfdbfe;">
                <i data-lucide="phone-call" style="width:20px;"></i>
            </div>
            <div>
                <div style="font-size:20px; font-weight:800; color:#0f172a;" id="trigger-calls-count">0</div>
                <div style="font-size:12px; font-weight:700; color:#334155;">Calls / Reminders Due</div>
                <div style="font-size:11px; color:#64748b;">Scheduled for today</div>
            </div>
        </div>

        <!-- Trigger 2: Missing Docs -->
        <div class="action-trigger-card" id="trigger-card-docs" onclick="filterByTrigger('docs')">
            <div class="trigger-icon-box" style="background:#fffbeb; color:#d97706; border:1px solid #fde68a;">
                <i data-lucide="file-warning" style="width:20px;"></i>
            </div>
            <div>
                <div style="font-size:20px; font-weight:800; color:#0f172a;" id="trigger-docs-count">0</div>
                <div style="font-size:12px; font-weight:700; color:#334155;">Doc Pendencies</div>
                <div style="font-size:11px; color:#64748b;">Missing KYC / Income / Property</div>
            </div>
        </div>

        <!-- Trigger 3: Login Fees Due -->
        <div class="action-trigger-card" id="trigger-card-loginfee" onclick="filterByTrigger('loginfee')">
            <div class="trigger-icon-box" style="background:#f0fdfa; color:#0f766e; border:1px solid #99f6e4;">
                <i data-lucide="credit-card" style="width:20px;"></i>
            </div>
            <div>
                <div style="font-size:20px; font-weight:800; color:#0f172a;" id="trigger-fee-count">0</div>
                <div style="font-size:12px; font-weight:700; color:#334155;">Bank Login Fees Due</div>
                <div style="font-size:11px; color:#64748b;">Ready for banker login</div>
            </div>
        </div>

        <!-- Trigger 4: Sanctions Ready -->
        <div class="action-trigger-card" id="trigger-card-sanctions" onclick="filterByTrigger('sanctions')">
            <div class="trigger-icon-box" style="background:#f0fdf4; color:#16a34a; border:1px solid #bbf7d0;">
                <i data-lucide="award" style="width:20px;"></i>
            </div>
            <div>
                <div style="font-size:20px; font-weight:800; color:#0f172a;" id="trigger-sanctions-count">0</div>
                <div style="font-size:12px; font-weight:700; color:#334155;">Sanctioned Cases</div>
                <div style="font-size:11px; color:#64748b;">Pending disbursal agreement</div>
            </div>
        </div>
    </div>

    <!-- 5-Stage Loan Pipeline Funnel Strip -->
    <div style="margin-bottom:12px;">
        <h2 style="font-size:14px; font-weight:800; text-transform:uppercase; letter-spacing:0.5px; color:#475569; margin-bottom:12px; display:flex; align-items:center; gap:6px;">
            <i data-lucide="layers" style="width:16px; color:#0284c7;"></i> 5-Stage Loan Lifecycle Pipeline
        </h2>
    </div>

    <div class="loan-funnel-strip">
        <div class="funnel-step active" id="funnel-all" onclick="filterByPhase('ALL')">
            <div class="funnel-step-count" id="funnel-count-all">0</div>
            <div class="funnel-step-name">All Active Files</div>
        </div>
        <div class="funnel-step" id="funnel-phase1" onclick="filterByPhase('Phase 1')">
            <div class="funnel-step-count" id="funnel-count-phase1">0</div>
            <div class="funnel-step-name">Phase 1: Registration</div>
        </div>
        <div class="funnel-step" id="funnel-phase2" onclick="filterByPhase('Phase 2')">
            <div class="funnel-step-count" id="funnel-count-phase2">0</div>
            <div class="funnel-step-name">Phase 2: Docs & Pendency</div>
        </div>
        <div class="funnel-step" id="funnel-phase3" onclick="filterByPhase('Phase 3')">
            <div class="funnel-step-count" id="funnel-count-phase3">0</div>
            <div class="funnel-step-name">Phase 3: Bank Login</div>
        </div>
        <div class="funnel-step" id="funnel-phase4" onclick="filterByPhase('Phase 4')">
            <div class="funnel-step-count" id="funnel-count-phase4">0</div>
            <div class="funnel-step-name">Phase 4: Sanctioned</div>
        </div>
        <div class="funnel-step" id="funnel-completed" onclick="filterByPhase('Completed')">
            <div class="funnel-step-count" id="funnel-count-completed">0</div>
            <div class="funnel-step-name">Phase 5: Disbursed</div>
        </div>
    </div>

    <!-- Main Assigned Loan Files Table -->
    <div class="table-container">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:12px;">
            <div>
                <h3 style="font-size:16px; font-weight:800; color:#0f172a; margin:0; display:flex; align-items:center; gap:8px;">
                    <i data-lucide="folder-check" style="width:18px; color:#0f766e;"></i>
                    My Assigned Loan Cases
                    <span class="badge" style="background:#f1f5f9; color:#475569;" id="table-filtered-badge">0 files</span>
                </h3>
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <input type="text" id="rm-search-input" oninput="renderFilesTable()" placeholder="🔍 Search Applicant, Loan ID, Mobile..." style="padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:12.5px; width:240px;">
                <select id="rm-priority-filter" onchange="renderFilesTable()" style="padding:8px 10px; border:1px solid #cbd5e1; border-radius:6px; font-size:12.5px;">
                    <option value="">All Priorities</option>
                    <option value="Urgent">⚡ Urgent</option>
                    <option value="High">🔥 High</option>
                    <option value="Normal">Normal</option>
                </select>
            </div>
        </div>

        <div style="overflow-x:auto;">
            <table class="data-table" style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr>
                        <th>Applicant Details</th>
                        <th>Loan Type & Amount</th>
                        <th>Current Stage</th>
                        <th>Bank / Sanction</th>
                        <th>TAT Age</th>
                        <th>Priority</th>
                        <th>Next Follow-up</th>
                        <th style="text-align:right;">RM Quick Actions</th>
                    </tr>
                </thead>
                <tbody id="rm-files-tbody">
                    <tr>
                        <td colspan="8" style="text-align:center; padding:30px; color:#94a3b8;">
                            <div class="skeleton skeleton-text" style="width:200px; margin:auto;"></div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Surveillance & Activity Timeline -->
    <div style="margin-top:24px; background:#ffffff; border-radius:12px; border:1px solid #e2e8f0; padding:20px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
            <h3 style="font-size:15px; font-weight:800; color:#0f172a; margin:0; display:flex; align-items:center; gap:8px;">
                <i data-lucide="history" style="width:16px; color:#64748b;"></i>
                Recent Activity & Call Logs
            </h3>
            <span style="font-size:11px; color:#94a3b8;">Real-time interaction audit</span>
        </div>
        <div id="rm-recent-activities" style="font-size:13px; color:#475569;">
            <p style="color:#94a3b8; font-size:12.5px;">Loading recent activities...</p>
        </div>
    </div>
</div>

<!-- RM Dashboard Log Interaction Modal -->
<div id="rm-log-interaction-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.8); z-index:9999; justify-content:center; align-items:center; backdrop-filter:blur(4px);">
    <div style="background:#fff; border-radius:12px; width:90%; max-width:540px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); overflow:hidden;">
        <div style="padding:16px 20px; background:#0f766e; color:#fff; display:flex; justify-content:space-between; align-items:center;">
            <div style="display:flex; align-items:center; gap:8px;">
                <i data-lucide="phone-call" style="width:18px; color:#5eead4;"></i>
                <span style="font-weight:700; font-size:15px;">Log Customer Call / Interaction</span>
            </div>
            <button onclick="closeRmLogInteractionModal()" style="background:none; border:none; color:#fff; cursor:pointer; font-size:20px; line-height:1;">&times;</button>
        </div>
        <form id="rm-log-interaction-form" onsubmit="submitRmLogInteraction(event)" style="padding:20px;">
            <input type="hidden" name="applicant_id" id="rm_log_applicant_id" value="">

            <div style="background:#f0fdfa; border:1px solid #99f6e4; padding:10px 14px; border-radius:8px; margin-bottom:16px;">
                <div style="font-size:11px; color:#0f766e; font-weight:700; text-transform:uppercase;">Customer Profile</div>
                <div id="rm_log_app_title" style="font-size:14px; font-weight:700; color:#0f172a; margin-top:2px;">-</div>
            </div>

            <div class="form-grid" style="grid-template-columns: 1fr 1fr; gap:14px; margin-bottom:14px; display:grid;">
                <div class="form-group">
                    <label class="required" style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Channel</label>
                    <select name="interaction_type" id="rm_log_type" required style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; box-sizing:border-box;">
                        <option value="Phone Call">📞 Outbound Phone Call</option>
                        <option value="WhatsApp Chat">💬 WhatsApp Discussion</option>
                        <option value="Physical Meeting">🤝 Physical Meeting</option>
                        <option value="Branch Visit">🏦 Branch Visit</option>
                        <option value="Video Call">📹 Video Verification</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="required" style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Outcome</label>
                    <select name="outcome" id="rm_log_outcome" required style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; box-sizing:border-box;">
                        <option value="Interested & Agreed">Interested & Moving Forward</option>
                        <option value="Document Promised">Document Promised by Client</option>
                        <option value="Login Fee Agreed">Login Fee Payment Agreed</option>
                        <option value="Follow-up Required">Follow-up Required</option>
                        <option value="Call Rescheduled / Busy">Client Busy / Rescheduled</option>
                        <option value="Not Reachable / Switch Off">Not Reachable</option>
                        <option value="Sanction Acceptance">Sanction Terms Accepted</option>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-bottom:14px;">
                <label class="required" style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Conversation Notes</label>
                <textarea name="notes" id="rm_log_notes" required rows="3" placeholder="e.g. Spoke with client regarding pending bank statement. Promised sending by evening." style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:12.5px; box-sizing:border-box;"></textarea>
            </div>

            <div class="form-group" style="margin-bottom:20px;">
                <label style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Next Follow-up Date & Time</label>
                <input type="datetime-local" name="next_followup" id="rm_log_followup" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; box-sizing:border-box;">
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="closeRmLogInteractionModal()" class="btn btn-secondary" style="padding:8px 16px;">Cancel</button>
                <button type="submit" id="rm_log_submit_btn" class="btn btn-primary" style="padding:8px 20px; font-weight:700; background:#0f766e; border:none;"><i data-lucide="check" style="width:14px;"></i> Save Call & Audit Log</button>
            </div>
        </form>
    </div>
</div>

<!-- RM Dashboard 1-Click WhatsApp Modal -->
<div id="rm-whatsapp-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.8); z-index:9999; justify-content:center; align-items:center; backdrop-filter:blur(4px);">
    <div style="background:#fff; border-radius:12px; width:90%; max-width:600px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); overflow:hidden;">
        <div style="padding:16px 20px; background:#16a34a; color:#fff; display:flex; justify-content:space-between; align-items:center;">
            <div style="display:flex; align-items:center; gap:8px;">
                <i data-lucide="message-circle" style="width:18px;"></i>
                <span style="font-weight:700; font-size:15px;">1-Click WhatsApp Quick Desk</span>
            </div>
            <button onclick="closeRmWhatsAppModal()" style="background:none; border:none; color:#fff; cursor:pointer; font-size:20px; line-height:1;">&times;</button>
        </div>
        <div style="padding:20px;">
            <div style="background:#f0fdf4; border:1px solid #bbf7d0; padding:10px 14px; border-radius:8px; margin-bottom:16px;">
                <div style="font-size:11px; color:#16a34a; font-weight:700; text-transform:uppercase;">Recipient Customer</div>
                <div id="rm_wa_customer_title" style="font-size:14px; font-weight:700; color:#0f172a; margin-top:2px;">-</div>
            </div>

            <div style="margin-bottom:12px;">
                <label style="font-size:12px; font-weight:700; color:#334155; margin-bottom:6px; display:block;">Select Ready Template:</label>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <button type="button" onclick="loadRmWhatsAppTemplate('welcome')" class="btn btn-secondary" style="font-size:11.5px; padding:4px 10px; border-radius:6px; font-weight:600;"><i data-lucide="sparkles" style="width:12px;"></i> RM Welcome</button>
                    <button type="button" onclick="loadRmWhatsAppTemplate('pendency')" class="btn btn-secondary" style="font-size:11.5px; padding:4px 10px; border-radius:6px; font-weight:600;"><i data-lucide="file-text" style="width:12px;"></i> Missing Docs</button>
                    <button type="button" onclick="loadRmWhatsAppTemplate('login_fee')" class="btn btn-secondary" style="font-size:11.5px; padding:4px 10px; border-radius:6px; font-weight:600;"><i data-lucide="credit-card" style="width:12px;"></i> Login Fee</button>
                    <button type="button" onclick="loadRmWhatsAppTemplate('sanction')" class="btn btn-secondary" style="font-size:11.5px; padding:4px 10px; border-radius:6px; font-weight:600;"><i data-lucide="award" style="width:12px;"></i> Sanction Notice</button>
                </div>
            </div>

            <div class="form-group" style="margin-bottom:20px;">
                <label style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Message Preview / Edit:</label>
                <textarea id="rm_wa_message_content" rows="7" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; line-height:1.5; box-sizing:border-box; font-family:inherit;"></textarea>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="closeRmWhatsAppModal()" class="btn btn-secondary" style="padding:8px 16px;">Cancel</button>
                <button type="button" onclick="sendRmQuickWhatsApp()" class="btn btn-primary" style="padding:8px 20px; font-weight:700; background:#16a34a; border:none;"><i data-lucide="send" style="width:14px;"></i> Open in WhatsApp</button>
            </div>
        </div>
    </div>
</div>

<script>
let rmDashboardData = null;
let currentPhaseFilter = 'ALL';
let currentTriggerFilter = null;

async function loadRmDashboard() {
    try {
        const res = await fetch('../api.php?api=rm_dashboard_metrics');
        const data = await res.json();
        
        if (!data || !data.success) {
            showNotification(data.error || 'Failed to load RM metrics', 'error');
            return;
        }

        rmDashboardData = data;
        renderHeroMetrics(data);
        renderTriggers(data);
        renderFunnel(data);
        renderFilesTable();
        renderRecentActivities(data.recent_activities || []);
    } catch (err) {
        console.error("RM Dashboard error:", err);
    }
}

function formatIndianCurrency(amount) {
    let x = Math.round(amount || 0).toString();
    let lastThree = x.slice(-3);
    let otherNumbers = x.slice(0, -3);
    if (otherNumbers !== '') lastThree = ',' + lastThree;
    let res = otherNumbers.replace(/\B(?=(\d{2})+(?!\d))/g, ",") + lastThree;
    return '₹' + res;
}

function renderHeroMetrics(d) {
    const target = d.monthly_target || 15000000;
    const mtdDisb = d.mtd_disbursed_val || 0;
    const pct = d.target_progress_pct || 0;
    const gap = Math.max(0, target - mtdDisb);

    document.getElementById('hero-mtd-disbursed').innerHTML = `${formatIndianCurrency(mtdDisb)} <span style="font-size:15px; font-weight:500; color:#94a3b8;">/ <span id="hero-monthly-target">${formatIndianCurrency(target)}</span></span>`;
    document.getElementById('hero-progress-pct').innerText = `${pct}%`;
    document.getElementById('hero-progress-bar').style.width = `${Math.min(100, pct)}%`;
    document.getElementById('hero-target-gap').innerText = formatIndianCurrency(gap);
    document.getElementById('hero-disbursed-count').innerText = `${d.mtd_disbursed_count || 0} Files`;
    document.getElementById('hero-incentive-earned').innerText = formatIndianCurrency(d.incentive_earned || 0);
    document.getElementById('hero-commission-rate').innerText = `${d.commission_rate || 0.20}%`;
}

function renderTriggers(d) {
    const t = d.triggers || {};
    document.getElementById('trigger-calls-count').innerText = t.reminders_due || 0;
    document.getElementById('trigger-docs-count').innerText = t.docs_pending || 0;
    document.getElementById('trigger-fee-count').innerText = t.login_fee_due || 0;
    document.getElementById('trigger-sanctions-count').innerText = t.sanctions_pending_disb || 0;
}

function renderFunnel(d) {
    const p = d.phases || {};
    const total = (p['Phase 1'] || 0) + (p['Phase 2'] || 0) + (p['Phase 3'] || 0) + (p['Phase 4'] || 0) + (p['Completed'] || 0);
    
    document.getElementById('funnel-count-all').innerText = total;
    document.getElementById('funnel-count-phase1').innerText = p['Phase 1'] || 0;
    document.getElementById('funnel-count-phase2').innerText = p['Phase 2'] || 0;
    document.getElementById('funnel-count-phase3').innerText = p['Phase 3'] || 0;
    document.getElementById('funnel-count-phase4').innerText = p['Phase 4'] || 0;
    document.getElementById('funnel-count-completed').innerText = p['Completed'] || 0;
}

function filterByPhase(phase) {
    currentPhaseFilter = phase;
    currentTriggerFilter = null;
    
    document.querySelectorAll('.funnel-step').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.action-trigger-card').forEach(el => el.classList.remove('active-filter'));

    if (phase === 'ALL') {
        document.getElementById('funnel-all').classList.add('active');
    } else if (phase === 'Phase 1') {
        document.getElementById('funnel-phase1').classList.add('active');
    } else if (phase === 'Phase 2') {
        document.getElementById('funnel-phase2').classList.add('active');
    } else if (phase === 'Phase 3') {
        document.getElementById('funnel-phase3').classList.add('active');
    } else if (phase === 'Phase 4') {
        document.getElementById('funnel-phase4').classList.add('active');
    } else if (phase === 'Completed') {
        document.getElementById('funnel-completed').classList.add('active');
    }

    renderFilesTable();
}

function filterByTrigger(trigger) {
    currentTriggerFilter = trigger;
    currentPhaseFilter = 'ALL';

    document.querySelectorAll('.funnel-step').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.action-trigger-card').forEach(el => el.classList.remove('active-filter'));

    if (trigger === 'calls') {
        document.getElementById('trigger-card-calls').classList.add('active-filter');
    } else if (trigger === 'docs') {
        document.getElementById('trigger-card-docs').classList.add('active-filter');
    } else if (trigger === 'loginfee') {
        document.getElementById('trigger-card-loginfee').classList.add('active-filter');
    } else if (trigger === 'sanctions') {
        document.getElementById('trigger-card-sanctions').classList.add('active-filter');
    }

    renderFilesTable();
}

function renderFilesTable() {
    if (!rmDashboardData || !rmDashboardData.assigned_files) return;

    const files = rmDashboardData.assigned_files;
    const query = (document.getElementById('rm-search-input').value || '').toLowerCase().trim();
    const prioFilter = document.getElementById('rm-priority-filter').value;

    let filtered = files.filter(f => {
        // Search query
        if (query) {
            const str = `${f.customer_name} ${f.loan_id} ${f.mobile} ${f.loan_type}`.toLowerCase();
            if (!str.includes(query)) return false;
        }

        // Priority filter
        if (prioFilter && (f.rm_priority || 'Normal') !== prioFilter) {
            return false;
        }

        // Phase filter
        if (currentPhaseFilter !== 'ALL' && f.overall_status !== currentPhaseFilter) {
            return false;
        }

        // Trigger filter
        if (currentTriggerFilter === 'docs') {
            if (f.overall_status !== 'Phase 1' && f.overall_status !== 'Phase 2') return false;
        } else if (currentTriggerFilter === 'loginfee') {
            if ((f.overall_status !== 'Phase 2' && f.overall_status !== 'Phase 3') || f.login_fee_status === 'Paid') return false;
        } else if (currentTriggerFilter === 'sanctions') {
            if (f.overall_status !== 'Phase 4' && f.sanction_status !== 'Sanctioned') return false;
        }

        return true;
    });

    document.getElementById('table-filtered-badge').innerText = `${filtered.length} files`;

    const tbody = document.getElementById('rm-files-tbody');
    if (filtered.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" style="text-align:center; padding:30px; color:#94a3b8;"><i data-lucide="inbox" style="width:32px; height:32px; margin-bottom:6px;"></i><p>No loan cases found matching current filters.</p></td></tr>`;
        lucide.createIcons();
        return;
    }

    let html = '';
    filtered.forEach(f => {
        const prio = f.rm_priority || 'Normal';
        let prioClass = 'priority-normal';
        if (prio === 'Urgent') prioClass = 'priority-urgent';
        else if (prio === 'High') prioClass = 'priority-high';

        const stageBadge = f.overall_status === 'Completed' 
            ? '<span class="badge" style="background:#dcfce7; color:#15803d; border:1px solid #bbf7d0;">Disbursed</span>'
            : `<span class="badge" style="background:#f1f5f9; color:#334155; border:1px solid #cbd5e1;">${f.overall_status}</span>`;

        const feeBadge = f.login_fee_status === 'Paid'
            ? `<span style="font-size:11px; color:#15803d; font-weight:700;"><i data-lucide="check-circle" style="width:12px; vertical-align:-1px;"></i> Fee Paid</span>`
            : `<span style="font-size:11px; color:#d97706; font-weight:600;"><i data-lucide="clock" style="width:12px; vertical-align:-1px;"></i> Fee Due</span>`;

        html += `
            <tr>
                <td>
                    <div style="font-weight:700; color:#0f172a; font-size:13.5px;">${f.customer_name}</div>
                    <div style="font-size:11.5px; color:#64748b;">ID: <span style="color:#0284c7; font-weight:600;">${f.loan_id}</span> • 📞 ${f.mobile || '-'}</div>
                </td>
                <td>
                    <div style="font-weight:600; color:#334155;">${f.loan_type || 'Loan'}</div>
                    <div style="font-size:13px; font-weight:700; color:#0f172a;">${formatIndianCurrency(f.loan_amount_requested)}</div>
                </td>
                <td>
                    ${stageBadge}
                    <div style="margin-top:3px;">${feeBadge}</div>
                </td>
                <td>
                    ${f.sanction_bank ? `<div style="font-weight:600; color:#0f172a; font-size:12.5px;">${f.sanction_bank}</div>` : '<span style="color:#94a3b8; font-size:11px;">In appraisal</span>'}
                    ${f.sanctioned_amount ? `<div style="font-size:12px; color:#15803d; font-weight:700;">₹${(f.sanctioned_amount).toLocaleString('en-IN')}</div>` : ''}
                </td>
                <td>
                    <span class="badge ${f.tat_days > 7 ? 'badge-danger' : 'badge-info'}" style="font-size:10.5px;">${f.tat_days || 0}d in system</span>
                </td>
                <td>
                    <span class="badge-priority ${prioClass}">${prio}</span>
                </td>
                <td>
                    ${f.rm_next_followup_date ? `<span style="font-size:11.5px; font-weight:600; color:#0284c7;">${f.rm_next_followup_date}</span>` : '<span style="font-size:11px; color:#94a3b8;">None</span>'}
                </td>
                <td style="text-align:right;">
                    <div style="display:inline-flex; gap:6px;">
                        <a href="../search_track.php?app_id=${f.id}" class="btn btn-secondary" style="padding:4px 8px; font-size:11px; font-weight:600; color:#0f172a;" title="Open Loan Workspace"><i data-lucide="eye" style="width:12px;"></i> View</a>
                        <button onclick="openRmLogModal(${f.id}, '${encodeURIComponent(f.customer_name)}')" class="btn btn-secondary" style="padding:4px 8px; font-size:11px; font-weight:600; color:#0f766e; background:#f0fdfa; border:1px solid #99f6e4;" title="Log Call Outcome"><i data-lucide="phone-call" style="width:12px;"></i> Call</button>
                        <button onclick="openRmWhatsAppModal('${f.mobile}', '${encodeURIComponent(f.customer_name)}', '${f.loan_id}', '${f.loan_type}')" class="btn btn-secondary" style="padding:4px 8px; font-size:11px; font-weight:600; color:#16a34a; background:#f0fdf4; border:1px solid #bbf7d0;" title="1-Click WhatsApp"><i data-lucide="message-circle" style="width:12px;"></i> WA</button>
                    </div>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
    lucide.createIcons();
}

function renderRecentActivities(acts) {
    const box = document.getElementById('rm-recent-activities');
    if (!acts || acts.length === 0) {
        box.innerHTML = '<p style="color:#94a3b8; font-size:12.5px;">No recent customer interactions logged yet today.</p>';
        return;
    }

    let html = '<div style="display:flex; flex-direction:column; gap:8px;">';
    acts.forEach(a => {
        html += `
            <div style="padding:8px 12px; background:#f8fafc; border-radius:6px; border:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
                <div style="font-size:12.5px; color:#334155;">${a.description}</div>
                <div style="font-size:11px; color:#94a3b8; white-space:nowrap; margin-left:12px;">${a.created_at || ''}</div>
            </div>
        `;
    });
    html += '</div>';
    box.innerHTML = html;
}

// ====== LOG INTERACTION MODAL ======
function openRmLogModal(appId, custName) {
    document.getElementById('rm_log_applicant_id').value = appId;
    document.getElementById('rm_log_app_title').innerText = decodeURIComponent(custName);
    document.getElementById('rm_log_notes').value = '';
    
    const tmrw = new Date();
    tmrw.setDate(tmrw.getDate() + 1);
    tmrw.setHours(11, 0, 0, 0);
    const pad = (n) => n.toString().padStart(2, '0');
    const formattedDate = `${tmrw.getFullYear()}-${pad(tmrw.getMonth()+1)}-${pad(tmrw.getDate())}T${pad(tmrw.getHours())}:${pad(tmrw.getMinutes())}`;
    document.getElementById('rm_log_followup').value = formattedDate;

    document.getElementById('rm-log-interaction-modal').style.display = 'flex';
    lucide.createIcons();
}

function closeRmLogInteractionModal() {
    document.getElementById('rm-log-interaction-modal').style.display = 'none';
}

async function submitRmLogInteraction(e) {
    e.preventDefault();
    const form = document.getElementById('rm-log-interaction-form');
    const formData = new FormData(form);
    const btn = document.getElementById('rm_log_submit_btn');

    btn.disabled = true;
    btn.innerHTML = 'Saving Log...';

    try {
        const res = await fetch('../api.php?api=log_rm_interaction', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data && data.success) {
            showNotification(data.message, 'success');
            closeRmLogInteractionModal();
            loadRmDashboard();
        } else {
            showNotification(data.error || 'Failed to log call', 'error');
        }
    } catch (err) {
        showNotification('Network error', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="check" style="width:14px;"></i> Save Call & Audit Log';
        lucide.createIcons();
    }
}

// ====== 1-CLICK WHATSAPP DESK ======
let activeRmWhatsAppContext = {};

function openRmWhatsAppModal(mobile, custName, loanId, loanType) {
    activeRmWhatsAppContext = {
        mobile: mobile ? mobile.replace(/\D/g, '') : '',
        name: decodeURIComponent(custName || 'Customer'),
        loanId: loanId || '',
        loanType: loanType || 'Loan'
    };

    if (!activeRmWhatsAppContext.mobile || activeRmWhatsAppContext.mobile.length < 10) {
        showNotification('Customer mobile number is missing or invalid', 'error');
        return;
    }

    document.getElementById('rm_wa_customer_title').innerText = `${activeRmWhatsAppContext.name} (+91 ${activeRmWhatsAppContext.mobile})`;
    loadRmWhatsAppTemplate('welcome');
    document.getElementById('rm-whatsapp-modal').style.display = 'flex';
    lucide.createIcons();
}

function closeRmWhatsAppModal() {
    document.getElementById('rm-whatsapp-modal').style.display = 'none';
}

function loadRmWhatsAppTemplate(templateKey) {
    const c = activeRmWhatsAppContext;
    let text = '';

    if (templateKey === 'welcome') {
        text = `Namaste ${c.name} ji,\n\nMain aapka Dedicated Relationship Manager baat kar raha hoon BFS Loan Services se.\n\nAapka ${c.loanType} application (Ref ID: *${c.loanId}*) hamare system me active process me hai.\n\nKisi bhi jaankari ya file status ke liye aap mujhe is number par direct call ya WhatsApp kar sakte hain.\n\nDhanyawad,\nTeam BFS`;
    } else if (templateKey === 'pendency') {
        text = `Namaste ${c.name} ji,\n\nAapke *${c.loanType}* (ID: *${c.loanId}*) file ko bank me process karne ke liye kuch zaroori documents required hain:\n\n1. Latest 6 Months Bank Statement (PDF format)\n2. Latest 3 Months Salary Slips / ITR with Computation\n3. KYC (Aadhaar & PAN Card)\n4. Property / Business Proof\n\nKripya ye documents jaldi se jaldi share karein taaki hum aapka loan approval fast process kara sakein.\n\nDhanyawad,\nBFS Loan Team`;
    } else if (templateKey === 'login_fee') {
        text = `Namaste ${c.name} ji,\n\nAapki loan application (ID: *${c.loanId}*) documents complete ho chuke hain aur file officially Bank Login ke liye ready hai.\n\nBank processing & verification start karne ke liye official Bank Login Fee pay karni hai.\n\nPayment UPI / QR Code ya Official Bank Account me transfer karke receipt share karein taaki banker verification turant start ho sake.\n\nDhanyawad,\nBFS Team`;
    } else if (templateKey === 'sanction') {
        text = `🎉 *CONGRATULATIONS ${c.name.toUpperCase()} JI!* 🎉\n\nHamein batate hue khushi ho rahi hai ki aapka *${c.loanType}* (ID: *${c.loanId}*) Bank dwara successfully *SANCTION / APPROVE* ho chuka hai!\n\nDisbursement aur final agreement signing ke liye hamari team aapse coordinate kar rahi hai.\n\nDhanyawad,\nBFS Financial Services`;
    }

    document.getElementById('rm_wa_message_content').value = text;
}

function sendRmQuickWhatsApp() {
    const c = activeRmWhatsAppContext;
    const msg = document.getElementById('rm_wa_message_content').value.trim();
    if (!msg) {
        showNotification('Please enter a WhatsApp message', 'error');
        return;
    }

    let phone = c.mobile;
    if (phone.length === 10) phone = '91' + phone;

    const waUrl = `https://api.whatsapp.com/send?phone=${phone}&text=${encodeURIComponent(msg)}`;
    window.open(waUrl, '_blank');
    closeRmWhatsAppModal();
}

window.addEventListener('DOMContentLoaded', () => {
    loadRmDashboard();
    setInterval(loadRmDashboard, 60000);
});
</script>

<?php require_once __DIR__ . '/../footer.php'; ?>

