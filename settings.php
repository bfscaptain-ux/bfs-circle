<?php
require_once __DIR__ . '/config.php';
require_role(['Admin'], false);

$page_title = "CRM Master Command & Settings Hub";
$page_subtitle = "Full Enterprise Control: Business Identity, Financial Rules, Security, Modules, Documents, Notifications & Backups";
$hide_main_header = true;
require_once __DIR__ . '/header.php';

// Fetch profile data
$profile_stmt = $db->query("SELECT * FROM company_profile LIMIT 1");
$profile = $profile_stmt ? ($profile_stmt->fetch(PDO::FETCH_ASSOC) ?: []) : [];

$loan_products = isset($profile['loan_products']) && $profile['loan_products'] ? json_decode($profile['loan_products'], true) : ['Home Loan', 'Personal Loan', 'Business Loan', 'Loan Against Property', 'Gold Loan'];
if(!is_array($loan_products)) $loan_products = ['Home Loan', 'Personal Loan', 'Business Loan'];

$lead_sources = isset($profile['lead_sources']) && $profile['lead_sources'] ? json_decode($profile['lead_sources'], true) : ['Facebook Ads', 'Direct Walk-in', 'Referral', 'JustDial', 'DSA Network'];
if(!is_array($lead_sources)) $lead_sources = ['Facebook Ads', 'Direct Walk-in', 'Referral'];

$global_tds = $profile['global_tds'] ?? 5.0;
$bank_gst_rate = $profile['bank_gst_rate'] ?? 18.0;
$bank_tds_rate = $profile['bank_tds_rate'] ?? 2.0;
$default_rm_incentive_pct = $profile['default_rm_incentive_pct'] ?? 0.20;
$default_partner_payout_pct = $profile['default_partner_payout_pct'] ?? 50.0;
$sla_stalled_days = $profile['sla_stalled_days'] ?? 3;
$public_tracking_expiry_days = $profile['public_tracking_expiry_days'] ?? 30;
$lead_auto_assign = $profile['lead_auto_assign'] ?? 'Round Robin (Distribute Equally)';

// Security & Module defaults
$allowed_ips = $profile['allowed_ips'] ?? '';
$session_timeout_mins = $profile['session_timeout_mins'] ?? 30;
$max_failed_logins = $profile['max_failed_logins'] ?? 5;

$enable_payout_module = $profile['enable_payout_module'] ?? 1;
$enable_whatsapp_module = $profile['enable_whatsapp_module'] ?? 1;
$enable_attendance_module = $profile['enable_attendance_module'] ?? 1;
$enable_hrms_module = $profile['enable_hrms_module'] ?? 1;
$enable_calculators_module = $profile['enable_calculators_module'] ?? 1;
$enable_public_tracking_module = $profile['enable_public_tracking_module'] ?? 1;

$notify_lead_assigned = $profile['notify_lead_assigned'] ?? 1;
$notify_sanction_issued = $profile['notify_sanction_issued'] ?? 1;
$notify_disbursement_completed = $profile['notify_disbursement_completed'] ?? 1;
$notify_sla_stalled = $profile['notify_sla_stalled'] ?? 1;

$bonus_over_target_pct = $profile['bonus_over_target_pct'] ?? 0.05;
$late_punch_penalty_count = $profile['late_punch_penalty_count'] ?? 3;
?>

<style>
    /* Executive Pagination & Page Management Styles */
    .pg-btn {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        color: #334155;
        padding: 5px 11px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.15s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 32px;
        height: 32px;
        text-decoration: none;
    }
    .pg-btn:hover:not([disabled]) {
        background: #f1f5f9;
        border-color: #0f172a;
        color: #0f172a;
    }
    .pg-btn-active {
        background: #0f172a !important;
        border-color: #0f172a !important;
        color: #ffffff !important;
        box-shadow: 0 2px 6px rgba(15,23,42,0.2);
    }
    .vault-toolbar-select {
        width: auto !important;
        max-width: 175px !important;
        height: 34px !important;
        padding: 0 10px !important;
        border: 1px solid #cbd5e1 !important;
        border-radius: 6px !important;
        font-size: 12px !important;
        font-weight: 600 !important;
        color: #0f172a !important;
        background-color: #ffffff !important;
        cursor: pointer !important;
        display: inline-block !important;
        box-sizing: border-box !important;
        flex-shrink: 0 !important;
    }
    .vault-toolbar-input {
        width: 210px !important;
        height: 34px !important;
        padding: 0 12px !important;
        border: 1px solid #cbd5e1 !important;
        border-radius: 6px !important;
        font-size: 12px !important;
        color: #0f172a !important;
        background: #ffffff !important;
        outline: none !important;
        box-sizing: border-box !important;
        flex-shrink: 0 !important;
        display: inline-block !important;
    }
    .vault-toolbar-btn {
        height: 34px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        font-size: 12px !important;
        border-radius: 6px !important;
        cursor: pointer !important;
        box-sizing: border-box !important;
        flex-shrink: 0 !important;
    }
    .settings-layout { display: flex; gap: 24px; align-items: flex-start; }
    .settings-sidebar { 
        width: 275px; 
        background: #ffffff; 
        border-radius: 14px; 
        border: 1px solid #e2e8f0; 
        box-shadow: 0 4px 6px -1px rgba(15, 23, 42, 0.03); 
        padding: 12px 0; 
        position: sticky; 
        top: 24px; 
        flex-shrink: 0; 
    }
    .settings-menu-item { 
        display: flex; 
        align-items: center; 
        gap: 11px; 
        padding: 12px 18px; 
        color: #64748b; 
        font-weight: 700; 
        font-size: 13px; 
        cursor: pointer; 
        transition: all 0.2s; 
        border-left: 3px solid transparent; 
    }
    .settings-menu-item:hover { background: #f8fafc; color: #0f172a; }
    .settings-menu-item.active { background: #f1f5f9; color: #0f172a; border-left-color: #0f172a; font-weight: 800; }
    .settings-menu-item svg { width: 17px; height: 17px; color: currentColor; }
    
    .settings-content { flex: 1; min-width: 0; }
    .settings-panel { display: none; animation: fadeIn 0.25s ease-in-out; }
    .settings-panel.active { display: block; }
    
    @keyframes fadeIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: translateY(0); } }
    
    .panel-card { background: #ffffff; border-radius: 14px; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.04); border: 1px solid #e2e8f0; padding: 26px; margin-bottom: 30px; }
    .panel-header { border-bottom: 1px solid #f1f5f9; padding-bottom: 16px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; }
    .panel-title { font-weight: 800; font-size: 18.5px; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 10px; }
    .panel-subtitle { font-weight: 800; font-size: 14px; color: #0f172a; margin: 24px 0 16px 0; border-bottom: 1px dashed #e2e8f0; padding-bottom: 8px; display: flex; align-items: center; gap: 8px; }
    
    .form-group-title { font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 6px; display: block; }
    .ctrl-input { width: 100%; padding: 9px 13px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; font-weight: 600; color: #0f172a; background: #ffffff; outline: none; box-sizing: border-box; transition: border-color 0.2s, box-shadow 0.2s; }
    .ctrl-input:focus { border-color: #0f172a; box-shadow: 0 0 0 3px rgba(15, 23, 42, 0.08); }

    .btn-black-solid { background: #0f172a; color: #ffffff; border: none; padding: 9px 18px; border-radius: 8px; font-weight: 800; font-size: 12.5px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: background 0.2s; }
    .btn-black-solid:hover { background: #1e293b; }

    .btn-black-outline { background: #ffffff; color: #0f172a; border: 1px solid #cbd5e1; padding: 8px 15px; border-radius: 8px; font-weight: 700; font-size: 12px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: background 0.2s, border-color 0.2s; }
    .btn-black-outline:hover { background: #f8fafc; border-color: #0f172a; }

    /* Switch Toggle Styling */
    .switch { position: relative; display: inline-block; width: 44px; height: 24px; }
    .switch input { opacity: 0; width: 0; height: 0; }
    .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .3s; border-radius: 24px; }
    .slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; }
    input:checked + .slider { background-color: #0f172a; }
    input:checked + .slider:before { transform: translateX(20px); }

    /* Danger Zone Red Alert Pulsing Animation */
    @keyframes redPulseWarning {
        0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); border-color: #fca5a5; }
        50% { box-shadow: 0 0 22px 7px rgba(239, 68, 68, 0.55); border-color: #dc2626; }
        100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); border-color: #fca5a5; }
    }
    @keyframes sirenFlash {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.35; transform: scale(1.18); }
    }
    .danger-zone-animated-card {
        border: 2px solid #ef4444 !important;
        background: linear-gradient(180deg, #fff5f5 0%, #fef2f2 100%) !important;
        border-radius: 16px;
        padding: 24px;
        margin-top: 28px;
        animation: redPulseWarning 2.2s infinite ease-in-out;
    }
    .siren-icon-flash {
        animation: sirenFlash 1.2s infinite ease-in-out;
        display: inline-block;
    }

    /* Audit & Roster Data Table Styling */
    .audit-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }
    .audit-table th {
        background: #f8fafc;
        padding: 12px 14px;
        text-align: left;
        font-size: 11.5px;
        font-weight: 700;
        color: #475569;
        border-bottom: 2px solid #e2e8f0;
        letter-spacing: 0.03em;
        white-space: nowrap;
    }
    .audit-table td {
        padding: 12px 14px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
        line-height: 1.5;
        color: #334155;
    }

    /* Responsive */
    @media (max-width: 850px) {
        .settings-layout { flex-direction: column; }
        .settings-sidebar { width: 100%; position: static; display: flex; overflow-x: auto; padding: 0; border-radius: 8px; }
        .settings-menu-item { white-space: nowrap; border-left: none; border-bottom: 3px solid transparent; }
        .settings-menu-item.active { border-left-color: transparent; border-bottom-color: #0f172a; }
    }
</style>

<?php
$active_tab = trim($_GET['tab'] ?? '');
$valid_tabs = ['profile', 'finrules', 'slabs', 'security', 'switches', 'notifications', 'docmatrix', 'vault', 'crmconfig', 'gamification', 'integrations', 'users', 'templates', 'ppts', 'tracking', 'backup', 'dangerzone'];
if (!in_array($active_tab, $valid_tabs)) $active_tab = 'profile';
?>

<div class="view-container">
    <div class="settings-layout">
        
        <!-- SIDEBAR NAVIGATION MENU -->
        <div class="settings-sidebar">
            <div class="settings-menu-item <?= $active_tab === 'profile' ? 'active' : '' ?>" onclick="switchSettingsTab('profile', this)">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                Company Profile
            </div>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'): ?>
            <div class="settings-menu-item <?= $active_tab === 'finrules' ? 'active' : '' ?>" onclick="switchSettingsTab('finrules', this)">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                Financial & Tax Rules
            </div>
            <div class="settings-menu-item <?= $active_tab === 'slabs' ? 'active' : '' ?>" onclick="switchSettingsTab('slabs', this)">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
                Bank Payout Slabs
            </div>
            <div class="settings-menu-item <?= $active_tab === 'security' ? 'active' : '' ?>" onclick="switchSettingsTab('security', this)">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                Security & IP Control
            </div>
            <div class="settings-menu-item <?= $active_tab === 'switches' ? 'active' : '' ?>" onclick="switchSettingsTab('switches', this)">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="1" y="5" width="22" height="14" rx="7" ry="7"></rect><circle cx="16" cy="12" r="3"></circle></svg>
                Module Feature Switches
            </div>
            <div class="settings-menu-item <?= $active_tab === 'notifications' ? 'active' : '' ?>" onclick="switchSettingsTab('notifications', this)">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                Notification Triggers
            </div>
            <div class="settings-menu-item <?= $active_tab === 'docmatrix' ? 'active' : '' ?>" onclick="switchSettingsTab('docmatrix', this)">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                Document Matrix
            </div>
            <div class="settings-menu-item <?= $active_tab === 'vault' ? 'active' : '' ?>" onclick="switchSettingsTab('vault', this)">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect><rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect><line x1="6" y1="6" x2="6.01" y2="6"></line><line x1="6" y1="18" x2="6.01" y2="18"></line></svg>
                On-Premise Vault (F:\BFS)
            </div>
            <div class="settings-menu-item <?= $active_tab === 'crmconfig' ? 'active' : '' ?>" onclick="switchSettingsTab('crmconfig', this)">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                Products & Lead Routing
            </div>
            <div class="settings-menu-item <?= $active_tab === 'gamification' ? 'active' : '' ?>" onclick="switchSettingsTab('gamification', this)">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="8" r="7"></circle><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline></svg>
                HR Gamification & Bonuses
            </div>
            <?php endif; ?>
            <div class="settings-menu-item <?= $active_tab === 'integrations' ? 'active' : '' ?>" onclick="switchSettingsTab('integrations', this)">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                Integrations (SMTP & WA)
            </div>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'): ?>
            <div class="settings-menu-item <?= $active_tab === 'users' ? 'active' : '' ?>" onclick="switchSettingsTab('users', this)">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 1-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                Manage Users & Access
            </div>
            <?php endif; ?>
            <div class="settings-menu-item <?= $active_tab === 'templates' ? 'active' : '' ?>" onclick="switchSettingsTab('templates', this)">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line></svg>
                Email Templates
            </div>
            <div class="settings-menu-item <?= $active_tab === 'ppts' ? 'active' : '' ?>" onclick="switchSettingsTab('ppts', this)">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                Presentation Decks
            </div>
            <div class="settings-menu-item <?= $active_tab === 'tracking' ? 'active' : '' ?>" onclick="switchSettingsTab('tracking', this)">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
                Client Tracking Links
            </div>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'): ?>
            <div class="settings-menu-item <?= $active_tab === 'backup' ? 'active' : '' ?>" onclick="switchSettingsTab('backup', this)">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                Database Backup & Health
            </div>
            <?php endif; ?>
        </div>

        <!-- SETTINGS CONTENT PANELS -->
        <div class="settings-content">
            
            <form id="settings-form" enctype="multipart/form-data" onsubmit="saveCompanySettings(event)">
                
                <!-- TAB 1: COMPANY PROFILE & MASTER BRANDING HUB -->
                <div id="tab-profile" class="settings-panel <?= $active_tab === 'profile' ? 'active' : '' ?>">
                    <div class="panel-card">
                        <div class="panel-header">
                            <div>
                                <h2 class="panel-title">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                                    Master Corporate Identity & Branding Command Center
                                </h2>
                                <div style="font-size:12px; color:#64748b; margin-top:2px;">Central management of logos, corporate seal, brand palette, regulatory licenses, and legal invoice footers</div>
                            </div>
                            <button type="submit" class="btn-black-solid"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg> Save Master Profile</button>
                        </div>
                        
                        <!-- 1. BRAND VISUAL ASSETS & STAMP UPLOADS -->
                        <div class="panel-subtitle" style="margin-top:0;">1. Visual Brand Assets & Digital Seal</div>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 20px; margin-bottom: 24px;">
                            <!-- Dark Logo -->
                            <div style="background:#f8fafc; border:1px dashed #cbd5e1; border-radius:12px; padding:16px; text-align:center;">
                                <label class="form-group-title" style="margin-bottom:8px; display:block;">Dark Logo (White/Light Header)</label>
                                <div style="height:70px; display:flex; align-items:center; justify-content:center; background:#ffffff; border:1px solid #e2e8f0; border-radius:8px; margin-bottom:12px; padding:6px;">
                                    <?php if (!empty($profile['logo_dark']) && file_exists(__DIR__ . '/' . $profile['logo_dark'])): ?>
                                        <img src="<?php echo htmlspecialchars($profile['logo_dark']); ?>" id="preview-logo-dark" style="max-height:55px; max-width:100%; object-fit:contain;">
                                    <?php else: ?>
                                        <img src="" id="preview-logo-dark" style="max-height:55px; max-width:100%; object-fit:contain; display:none;">
                                        <span id="no-logo-dark" style="font-size:11px; color:#94a3b8; font-weight:700;">No Dark Logo Uploaded</span>
                                    <?php endif; ?>
                                </div>
                                <input type="file" name="logo_dark" accept="image/*" onchange="previewBrandingFile(this, 'preview-logo-dark', 'no-logo-dark')" style="font-size:11px; width:100%;">
                                <div style="font-size:10.5px; color:#64748b; margin-top:6px;">PNG, SVG or WEBP (Transparent background recommended)</div>
                            </div>

                            <!-- Light Logo -->
                            <div style="background:#0f172a; border:1px dashed #334155; border-radius:12px; padding:16px; text-align:center; color:#ffffff;">
                                <label class="form-group-title" style="margin-bottom:8px; display:block; color:#f8fafc;">Light Logo (Dark Sidebar / Banner)</label>
                                <div style="height:70px; display:flex; align-items:center; justify-content:center; background:#1e293b; border:1px solid #334155; border-radius:8px; margin-bottom:12px; padding:6px;">
                                    <?php if (!empty($profile['logo_light']) && file_exists(__DIR__ . '/' . $profile['logo_light'])): ?>
                                        <img src="<?php echo htmlspecialchars($profile['logo_light']); ?>" id="preview-logo-light" style="max-height:55px; max-width:100%; object-fit:contain;">
                                    <?php else: ?>
                                        <img src="" id="preview-logo-light" style="max-height:55px; max-width:100%; object-fit:contain; display:none;">
                                        <span id="no-logo-light" style="font-size:11px; color:#64748b; font-weight:700;">No Light Logo Uploaded</span>
                                    <?php endif; ?>
                                </div>
                                <input type="file" name="logo_light" accept="image/*" onchange="previewBrandingFile(this, 'preview-logo-light', 'no-logo-light')" style="font-size:11px; width:100%; color:#cbd5e1;">
                                <div style="font-size:10.5px; color:#94a3b8; margin-top:6px;">White / Light PNG, SVG for dark backgrounds</div>
                            </div>

                            <!-- Digital Stamp / Signature -->
                            <div style="background:#f8fafc; border:1px dashed #cbd5e1; border-radius:12px; padding:16px; text-align:center;">
                                <label class="form-group-title" style="margin-bottom:8px; display:block;">Corporate Stamp & Sign</label>
                                <div style="height:70px; display:flex; align-items:center; justify-content:center; background:#ffffff; border:1px solid #e2e8f0; border-radius:8px; margin-bottom:12px; padding:6px;">
                                    <?php if (!empty($profile['stamp_signature']) && file_exists(__DIR__ . '/' . $profile['stamp_signature'])): ?>
                                        <img src="<?php echo htmlspecialchars($profile['stamp_signature']); ?>" id="preview-stamp" style="max-height:55px; max-width:100%; object-fit:contain;">
                                    <?php else: ?>
                                        <img src="" id="preview-stamp" style="max-height:55px; max-width:100%; object-fit:contain; display:none;">
                                        <span id="no-stamp" style="font-size:11px; color:#94a3b8; font-weight:700;">No Seal Uploaded</span>
                                    <?php endif; ?>
                                </div>
                                <input type="file" name="stamp_signature" accept="image/*" onchange="previewBrandingFile(this, 'preview-stamp', 'no-stamp')" style="font-size:11px; width:100%;">
                                <div style="font-size:10.5px; color:#64748b; margin-top:6px;">Authorized Sign & Round Seal for PDF Invoices</div>
                            </div>
                        </div>

                        <!-- 2. BRAND COLOR PALETTE & WHITE-LABEL ENGINE -->
                        <div class="panel-subtitle">2. Corporate Color Palette & White-Label Document Engine</div>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
                            <div>
                                <label class="form-group-title">Primary Brand Color (Monochrome Base)</label>
                                <div style="display:flex; gap:10px; align-items:center;">
                                    <input type="color" name="primary_color" id="input_primary_color" value="<?php echo htmlspecialchars($profile['primary_color'] ?? '#0f172a'); ?>" onchange="updateLivePreview()" style="width:45px; height:38px; border:none; border-radius:6px; cursor:pointer; background:none;">
                                    <input type="text" value="<?php echo htmlspecialchars($profile['primary_color'] ?? '#0f172a'); ?>" oninput="document.getElementById('input_primary_color').value=this.value; updateLivePreview();" class="ctrl-input" style="font-weight:700; font-family:monospace;">
                                </div>
                            </div>
                            <div>
                                <label class="form-group-title">Accent Color</label>
                                <div style="display:flex; gap:10px; align-items:center;">
                                    <input type="color" name="accent_color" id="input_accent_color" value="<?php echo htmlspecialchars($profile['accent_color'] ?? '#0284c7'); ?>" onchange="updateLivePreview()" style="width:45px; height:38px; border:none; border-radius:6px; cursor:pointer; background:none;">
                                    <input type="text" value="<?php echo htmlspecialchars($profile['accent_color'] ?? '#0284c7'); ?>" oninput="document.getElementById('input_accent_color').value=this.value; updateLivePreview();" class="ctrl-input" style="font-weight:700; font-family:monospace;">
                                </div>
                            </div>
                            <div>
                                <label class="form-group-title">Functional Base Currency</label>
                                <select name="base_currency" id="input_base_currency" onchange="updateLivePreview()" class="ctrl-input" style="font-weight:700;">
                                    <option value="INR (₹)" <?php echo ($profile['base_currency'] ?? 'INR (₹)') === 'INR (₹)' ? 'selected' : ''; ?>>INR (₹) — Indian Rupee</option>
                                    <option value="USD ($)" <?php echo ($profile['base_currency'] ?? '') === 'USD ($)' ? 'selected' : ''; ?>>USD ($) — US Dollar</option>
                                    <option value="AED (د.إ)" <?php echo ($profile['base_currency'] ?? '') === 'AED (د.إ)' ? 'selected' : ''; ?>>AED (د.إ) — UAE Dirham</option>
                                    <option value="EUR (€)" <?php echo ($profile['base_currency'] ?? '') === 'EUR (€)' ? 'selected' : ''; ?>>EUR (€) — Euro</option>
                                    <option value="GBP (£)" <?php echo ($profile['base_currency'] ?? '') === 'GBP (£)' ? 'selected' : ''; ?>>GBP (£) — British Pound</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-group-title">Browser Favicon Upload (.ico / .png)</label>
                                <div style="display:flex; gap:10px; align-items:center;">
                                    <div style="width:38px; height:38px; display:flex; align-items:center; justify-content:center; background:#ffffff; border:1px solid #cbd5e1; border-radius:6px; flex-shrink:0;">
                                        <?php if (!empty($profile['favicon_path']) && file_exists(__DIR__ . '/' . $profile['favicon_path'])): ?>
                                            <img src="<?php echo htmlspecialchars($profile['favicon_path']); ?>" id="preview-favicon" style="width:24px; height:24px; object-fit:contain;">
                                        <?php else: ?>
                                            <img src="" id="preview-favicon" style="width:24px; height:24px; object-fit:contain; display:none;">
                                            <span id="no-favicon" style="font-size:10px; color:#94a3b8; font-weight:700;">ICON</span>
                                        <?php endif; ?>
                                    </div>
                                    <input type="file" name="favicon" accept="image/x-icon,image/png" onchange="previewBrandingFile(this, 'preview-favicon', 'no-favicon')" style="font-size:11px; width:100%;">
                                </div>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 16px; margin-bottom: 24px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:14px;">
                            <div>
                                <label class="form-group-title">PDF Export Default Watermark Text</label>
                                <input type="text" name="watermark_text" id="input_watermark_text" value="<?php echo htmlspecialchars($profile['watermark_text'] ?? 'CONFIDENTIAL & PROPRIETARY'); ?>" oninput="updateLivePreview()" placeholder="CONFIDENTIAL & PROPRIETARY" class="ctrl-input" style="font-weight:700; letter-spacing:0.5px;">
                            </div>
                            <div style="display:flex; flex-direction:column; justify-content:center;">
                                <label class="form-group-title" style="margin-bottom:4px;">Dynamic Invoice Payment QR</label>
                                <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:12.5px; font-weight:700; color:#0f172a;">
                                    <input type="checkbox" name="enable_dynamic_qr" value="1" id="input_enable_dynamic_qr" onchange="updateLivePreview()" <?php echo ($profile['enable_dynamic_qr'] ?? 1) ? 'checked' : ''; ?>>
                                    <span>Auto-Print Dynamic UPI QR</span>
                                </label>
                            </div>
                        </div>

                        <!-- 3. BUSINESS IDENTITY -->
                        <div class="panel-subtitle">3. Business Identity & MCA Corporate Information</div>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 16px;">
                            <div>
                                <label class="form-group-title">Company Legal Name *</label>
                                <input type="text" name="company_name" id="input_company_name" value="<?php echo htmlspecialchars($profile['company_name'] ?? ''); ?>" oninput="updateLivePreview()" required class="ctrl-input">
                            </div>
                            <div>
                                <label class="form-group-title">Trading Brand Name</label>
                                <input type="text" name="trading_name" id="input_trading_name" value="<?php echo htmlspecialchars($profile['trading_name'] ?? ''); ?>" oninput="updateLivePreview()" placeholder="e.g. BFS Circle" class="ctrl-input">
                            </div>
                            <div>
                                <label class="form-group-title">Company Tagline</label>
                                <input type="text" name="company_tagline" id="input_company_tagline" value="<?php echo htmlspecialchars($profile['company_tagline'] ?? ''); ?>" oninput="updateLivePreview()" placeholder="India's Premier DSA Network" class="ctrl-input">
                            </div>
                            <div>
                                <label class="form-group-title">Primary Director / Admin Name *</label>
                                <input type="text" name="contact_person" id="input_contact_person" value="<?php echo htmlspecialchars($profile['contact_person'] ?? ''); ?>" oninput="updateLivePreview()" required class="ctrl-input">
                            </div>
                            <div>
                                <label class="form-group-title">Corporate CIN Number (MCA)</label>
                                <input type="text" name="cin_number" id="input_cin_number" value="<?php echo htmlspecialchars($profile['cin_number'] ?? ''); ?>" oninput="updateLivePreview()" placeholder="U74999DL2026PTC123456" class="ctrl-input" style="font-weight:700;">
                            </div>
                            <div>
                                <label class="form-group-title">ROC Registrar Jurisdiction</label>
                                <input type="text" name="roc_jurisdiction" id="input_roc_jurisdiction" value="<?php echo htmlspecialchars($profile['roc_jurisdiction'] ?? ''); ?>" oninput="updateLivePreview()" placeholder="ROC Delhi / ROC Mumbai" class="ctrl-input">
                            </div>
                            <div>
                                <label class="form-group-title">Date of Incorporation</label>
                                <input type="date" name="date_of_incorporation" value="<?php echo htmlspecialchars($profile['date_of_incorporation'] ?? ''); ?>" class="ctrl-input">
                            </div>
                            <div>
                                <label class="form-group-title">20-Digit International LEI Code</label>
                                <input type="text" name="lei_code" id="input_lei_code" value="<?php echo htmlspecialchars($profile['lei_code'] ?? ''); ?>" oninput="updateLivePreview()" placeholder="33580039140012345678" maxlength="20" class="ctrl-input" style="font-weight:700;">
                            </div>
                        </div>

                        <!-- 4. TAX & REGULATORY COMPLIANCE -->
                        <div class="panel-subtitle">4. Tax & Regulatory Compliance Licenses</div>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                            <div>
                                <label class="form-group-title">GSTIN Number *</label>
                                <input type="text" name="gstin" id="input_gstin" value="<?php echo htmlspecialchars($profile['gstin'] ?? ''); ?>" oninput="updateLivePreview()" placeholder="07AAAAA0000A1Z5" minlength="15" maxlength="15" required class="ctrl-input" style="font-weight:700;">
                            </div>
                            <div>
                                <label class="form-group-title">PAN Number</label>
                                <input type="text" name="pan" id="input_pan" value="<?php echo htmlspecialchars($profile['pan'] ?? ''); ?>" oninput="updateLivePreview()" placeholder="AAAAA0000A" class="ctrl-input" style="font-weight:700;">
                            </div>
                            <div>
                                <label class="form-group-title">TAN Number</label>
                                <input type="text" name="tan" id="input_tan" value="<?php echo htmlspecialchars($profile['tan'] ?? ''); ?>" placeholder="DELA12345F" class="ctrl-input" style="font-weight:700;">
                            </div>
                            <div>
                                <label class="form-group-title">GST Service SAC Code</label>
                                <input type="text" name="sac_code" id="input_sac_code" value="<?php echo htmlspecialchars($profile['sac_code'] ?? '997159'); ?>" class="ctrl-input" style="font-weight:700;">
                            </div>
                            <div>
                                <label class="form-group-title">MSME Registration No.</label>
                                <input type="text" name="msme_reg_no" id="input_msme_reg_no" value="<?php echo htmlspecialchars($profile['msme_reg_no'] ?? ''); ?>" oninput="updateLivePreview()" placeholder="UDYAM-XX-00-0000000" class="ctrl-input" style="font-weight:700;">
                            </div>
                            <div>
                                <label class="form-group-title">RBI DSA Registration / License No.</label>
                                <input type="text" name="rbi_dsa_reg_no" id="input_rbi_dsa_reg_no" value="<?php echo htmlspecialchars($profile['rbi_dsa_reg_no'] ?? ''); ?>" oninput="updateLivePreview()" placeholder="RBI/DSA/2026/XXXX" class="ctrl-input" style="font-weight:700;">
                            </div>
                        </div>

                        <!-- 4.5. ISO CYBERSECURITY & DATA PRIVACY (DPDP ACT) -->
                        <div class="panel-subtitle">4.5. ISO Cybersecurity & Data Protection Officer (DPDP) Desk</div>
                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:16px; margin-bottom:16px;">
                            <label class="form-group-title" style="margin-bottom:8px;">Active ISO & Security Quality Badges</label>
                            <?php $iso_saved = array_map('trim', explode(',', $profile['iso_certifications'] ?? 'ISO 27001:2022, ISO 9001:2015, CERT-In Cleared')); ?>
                            <div style="display:flex; flex-wrap:wrap; gap:12px; margin-bottom:14px;">
                                <label style="display:flex; align-items:center; gap:6px; font-size:12px; font-weight:700; color:#0f172a; background:#ffffff; border:1px solid #cbd5e1; padding:6px 12px; border-radius:6px; cursor:pointer;">
                                    <input type="checkbox" name="iso_certifications[]" value="ISO 27001:2022" onchange="updateLivePreview()" <?php echo in_array('ISO 27001:2022', $iso_saved) ? 'checked' : ''; ?>>
                                    <span>🛡️ ISO 27001:2022 (InfoSec)</span>
                                </label>
                                <label style="display:flex; align-items:center; gap:6px; font-size:12px; font-weight:700; color:#0f172a; background:#ffffff; border:1px solid #cbd5e1; padding:6px 12px; border-radius:6px; cursor:pointer;">
                                    <input type="checkbox" name="iso_certifications[]" value="ISO 9001:2015" onchange="updateLivePreview()" <?php echo in_array('ISO 9001:2015', $iso_saved) ? 'checked' : ''; ?>>
                                    <span>🏅 ISO 9001:2015 (Quality)</span>
                                </label>
                                <label style="display:flex; align-items:center; gap:6px; font-size:12px; font-weight:700; color:#0f172a; background:#ffffff; border:1px solid #cbd5e1; padding:6px 12px; border-radius:6px; cursor:pointer;">
                                    <input type="checkbox" name="iso_certifications[]" value="SOC-2 Type II" onchange="updateLivePreview()" <?php echo in_array('SOC-2 Type II', $iso_saved) ? 'checked' : ''; ?>>
                                    <span>🔒 SOC-2 Type II Compliant</span>
                                </label>
                                <label style="display:flex; align-items:center; gap:6px; font-size:12px; font-weight:700; color:#0f172a; background:#ffffff; border:1px solid #cbd5e1; padding:6px 12px; border-radius:6px; cursor:pointer;">
                                    <input type="checkbox" name="iso_certifications[]" value="CERT-In Cleared" onchange="updateLivePreview()" <?php echo in_array('CERT-In Cleared', $iso_saved) ? 'checked' : ''; ?>>
                                    <span>⚡ CERT-In Security Cleared</span>
                                </label>
                            </div>

                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">
                                <div>
                                    <label class="form-group-title">Data Protection Officer (DPO) Name</label>
                                    <input type="text" name="dpo_name" value="<?php echo htmlspecialchars($profile['dpo_name'] ?? ''); ?>" placeholder="e.g. Rahul Sharma, Lead Counsel" class="ctrl-input">
                                </div>
                                <div>
                                    <label class="form-group-title">DPO Official Email Desk</label>
                                    <input type="email" name="dpo_email" value="<?php echo htmlspecialchars($profile['dpo_email'] ?? ''); ?>" placeholder="privacy@bfscircle.com" class="ctrl-input">
                                </div>
                                <div>
                                    <label class="form-group-title">DPO Helpline Mobile</label>
                                    <input type="text" name="dpo_mobile" value="<?php echo htmlspecialchars($profile['dpo_mobile'] ?? ''); ?>" placeholder="+91 98765 XXXXX" class="ctrl-input">
                                </div>
                            </div>
                        </div>

                        <!-- 5. CORPORATE ADDRESS DETAILS -->
                        <div class="panel-subtitle">5. Corporate Address Details</div>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 16px;">
                            <div>
                                <label class="form-group-title">Billing Address Line 1 *</label>
                                <input type="text" name="address_line1" id="input_address_line1" value="<?php echo htmlspecialchars($profile['address_line1'] ?? ''); ?>" oninput="updateLivePreview()" required class="ctrl-input">
                            </div>
                            <div>
                                <label class="form-group-title">Billing Address Line 2</label>
                                <input type="text" name="address_line2" id="input_address_line2" value="<?php echo htmlspecialchars($profile['address_line2'] ?? ''); ?>" oninput="updateLivePreview()" class="ctrl-input">
                            </div>
                            <div>
                                <label class="form-group-title">City *</label>
                                <input type="text" name="city" id="input_city" value="<?php echo htmlspecialchars($profile['city'] ?? ''); ?>" oninput="updateLivePreview()" required class="ctrl-input">
                            </div>
                            <div>
                                <label class="form-group-title">State *</label>
                                <input type="text" name="state" id="input_state" value="<?php echo htmlspecialchars($profile['state'] ?? ''); ?>" oninput="updateLivePreview()" required class="ctrl-input">
                            </div>
                            <div>
                                <label class="form-group-title">Pincode *</label>
                                <input type="text" name="pincode" id="input_pincode" value="<?php echo htmlspecialchars($profile['pincode'] ?? ''); ?>" oninput="updateLivePreview()" required class="ctrl-input">
                            </div>
                            <div>
                                <label class="form-group-title">Country *</label>
                                <input type="text" name="country" value="<?php echo htmlspecialchars($profile['country'] ?? 'India'); ?>" required class="ctrl-input">
                            </div>
                        </div>

                        <!-- 6. MULTI-BRANCH REGIONAL OFFICE ROSTER -->
                        <div class="panel-subtitle">6. Multi-Branch & Regional Offices Manager Desk</div>
                        <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:10px; padding:16px; margin-bottom:24px;">
                            <input type="hidden" name="branches_json" id="input_branches_json" value="<?php echo htmlspecialchars($profile['branches_json'] ?? '[]'); ?>">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                                <span style="font-size:13px; font-weight:800; color:#0f172a;">Regional Offices & State GSTIN Roster</span>
                                <button type="button" class="btn-black-outline" style="padding:4px 10px; font-size:11.5px;" onclick="openAddBranchModal()">+ Add Regional Branch</button>
                            </div>
                            <div id="branches-list-container">
                                <!-- Rendered via JS -->
                            </div>
                        </div>

                        <!-- 7. MULTI-CHANNEL DIGITAL PRESENCE -->
                        <div class="panel-subtitle">7. Digital Presence & Multi-Channel Support</div>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 16px;">
                            <div>
                                <label class="form-group-title">Support & General Email *</label>
                                <input type="email" name="email" id="input_email" value="<?php echo htmlspecialchars($profile['email'] ?? ''); ?>" oninput="updateLivePreview()" required class="ctrl-input">
                            </div>
                            <div>
                                <label class="form-group-title">Accounts Desk Email</label>
                                <input type="email" name="accounts_email" value="<?php echo htmlspecialchars($profile['accounts_email'] ?? ''); ?>" placeholder="accounts@bfscircle.com" class="ctrl-input">
                            </div>
                            <div>
                                <label class="form-group-title">WhatsApp Support Line</label>
                                <input type="text" name="whatsapp_number" id="input_whatsapp_number" value="<?php echo htmlspecialchars($profile['whatsapp_number'] ?? ''); ?>" oninput="updateLivePreview()" placeholder="+91 98765 43210" class="ctrl-input">
                            </div>
                            <div>
                                <label class="form-group-title">Website Domain URL</label>
                                <input type="url" name="website_url" id="input_website_url" value="<?php echo htmlspecialchars($profile['website_url'] ?? ''); ?>" oninput="updateLivePreview()" placeholder="https://bfscircle.com" class="ctrl-input">
                            </div>
                            <div>
                                <label class="form-group-title">Google Maps HQ Location Link</label>
                                <input type="url" name="maps_url" value="<?php echo htmlspecialchars($profile['maps_url'] ?? ''); ?>" placeholder="https://maps.google.com/..." class="ctrl-input">
                            </div>
                            <div>
                                <label class="form-group-title">LinkedIn Corporate Page</label>
                                <input type="url" name="linkedin_url" value="<?php echo htmlspecialchars($profile['linkedin_url'] ?? ''); ?>" placeholder="https://linkedin.com/company/bfscircle" class="ctrl-input">
                            </div>
                            <div>
                                <label class="form-group-title">Instagram Handle</label>
                                <input type="text" name="instagram_url" value="<?php echo htmlspecialchars($profile['instagram_url'] ?? ''); ?>" placeholder="@bfscircle" class="ctrl-input">
                            </div>
                            <div>
                                <label class="form-group-title">Facebook Page Link</label>
                                <input type="url" name="facebook_url" value="<?php echo htmlspecialchars($profile['facebook_url'] ?? ''); ?>" placeholder="https://facebook.com/bfscircle" class="ctrl-input">
                            </div>
                        </div>
                        
                        <!-- 8. REMITTANCE BANK & LEGAL DISCLAIMER -->
                        <div class="panel-subtitle">8. Remittance Bank Account & Invoice Legal Notes</div>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 16px;">
                            <div>
                                <label class="form-group-title">Bank Name</label>
                                <input type="text" name="bank_name" id="input_bank_name" value="<?php echo htmlspecialchars($profile['bank_name'] ?? ''); ?>" oninput="updateLivePreview()" class="ctrl-input">
                            </div>
                            <div>
                                <label class="form-group-title">Bank Branch Name</label>
                                <input type="text" name="bank_branch" id="input_bank_branch" value="<?php echo htmlspecialchars($profile['bank_branch'] ?? ''); ?>" oninput="updateLivePreview()" placeholder="Connaught Place, New Delhi" class="ctrl-input">
                            </div>
                            <div>
                                <label class="form-group-title">Account Number</label>
                                <input type="text" name="account_number" id="input_account_number" value="<?php echo htmlspecialchars($profile['account_number'] ?? ''); ?>" oninput="updateLivePreview()" class="ctrl-input" style="font-weight:700;">
                            </div>
                            <div>
                                <label class="form-group-title">IFSC Code</label>
                                <input type="text" name="ifsc_code" id="input_ifsc_code" value="<?php echo htmlspecialchars($profile['ifsc_code'] ?? ''); ?>" oninput="updateLivePreview()" class="ctrl-input" style="font-weight:700;">
                            </div>
                            <div>
                                <label class="form-group-title">UPI VPA Handle</label>
                                <input type="text" name="upi_vpa" value="<?php echo htmlspecialchars($profile['upi_vpa'] ?? ''); ?>" class="ctrl-input" style="font-weight:700;">
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 16px;">
                            <div>
                                <label class="form-group-title">Tax Invoice Footer Remittance Note</label>
                                <textarea name="invoice_footer_note" id="input_invoice_footer_note" oninput="updateLivePreview()" rows="3" class="ctrl-input" style="font-size:12px;" placeholder="e.g. Please make all cheques / NEFT payments payable to 'BFS Financial Services Ltd'. TDS u/s 194H applicable."><?php echo htmlspecialchars($profile['invoice_footer_note'] ?? ''); ?></textarea>
                            </div>
                            <div>
                                <label class="form-group-title">Legal Disclaimer Statement (Sanction & Quotation Docs)</label>
                                <textarea name="legal_disclaimer" id="input_legal_disclaimer" oninput="updateLivePreview()" rows="3" class="ctrl-input" style="font-size:12px;" placeholder="e.g. Loan approvals are subject to bank terms & RBI statutory guidelines. BFS Circle acts as an authorized DSA network partner."><?php echo htmlspecialchars($profile['legal_disclaimer'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <!-- 9. LIVE REAL-TIME ENTERPRISE INVOICE & LETTERHEAD PREVIEW CARD -->
                        <div class="panel-subtitle" style="margin-top:30px;">9. Live Real-Time Enterprise Invoice & Letterhead Preview</div>
                        <div style="background:#f1f5f9; border:1px solid #cbd5e1; border-radius:14px; padding:20px; position:relative; overflow:hidden; box-shadow:inset 0 2px 4px rgba(0,0,0,0.03);">
                            
                            <!-- Background Watermark Preview -->
                            <div id="prev-card-watermark" style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%) rotate(-30deg); font-size:42px; font-weight:900; color:rgba(15,23,42,0.04); white-space:nowrap; pointer-events:none; z-index:1;">
                                <?php echo htmlspecialchars($profile['watermark_text'] ?: 'CONFIDENTIAL & PROPRIETARY'); ?>
                            </div>

                            <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:10px; padding:24px; box-shadow:0 4px 12px rgba(15,23,42,0.06); max-width:800px; margin:0 auto; position:relative; z-index:2;" id="preview-invoice-card">
                                
                                <!-- Preview Header -->
                                <div style="display:flex; justify-content:space-between; align-items:flex-start; border-bottom:2px solid #0f172a; padding-bottom:16px; margin-bottom:16px;" id="prev-hdr-border">
                                    <div>
                                        <div style="height:45px; display:flex; align-items:center; margin-bottom:6px;">
                                            <img src="<?php echo htmlspecialchars($profile['logo_dark'] ?? ''); ?>" id="prev-card-logo" style="max-height:45px; max-width:200px; object-fit:contain; <?php echo empty($profile['logo_dark']) ? 'display:none;' : ''; ?>">
                                            <span id="prev-card-logo-txt" style="font-size:20px; font-weight:900; color:#0f172a; <?php echo !empty($profile['logo_dark']) ? 'display:none;' : ''; ?>"><?php echo htmlspecialchars($profile['company_name'] ?: 'BFS FINANCIAL SERVICES'); ?></span>
                                        </div>
                                        <div style="font-size:12px; font-weight:800; color:#0f172a;" id="prev-card-company-name"><?php echo htmlspecialchars($profile['company_name'] ?: 'BFS Financial Services Ltd'); ?></div>
                                        <div style="font-size:11px; color:#64748b;" id="prev-card-tagline"><?php echo htmlspecialchars($profile['company_tagline'] ?: 'India\'s Premier DSA Network'); ?></div>
                                        <div style="font-size:11px; color:#475569; margin-top:4px;" id="prev-card-address"><?php echo htmlspecialchars(($profile['address_line1'] ?? '') . ' ' . ($profile['address_line2'] ?? '') . ', ' . ($profile['city'] ?? '') . ' ' . ($profile['state'] ?? '') . ' - ' . ($profile['pincode'] ?? '')); ?></div>
                                        <div style="font-size:10px; color:#64748b; font-weight:700; margin-top:2px;" id="prev-card-cin"><?php if(!empty($profile['cin_number'])): ?>CIN: <?php echo htmlspecialchars($profile['cin_number']); ?><?php endif; ?></div>
                                    </div>
                                    <div style="text-align:right;">
                                        <span style="background:#0f172a; color:#fff; font-size:11px; font-weight:800; padding:4px 12px; border-radius:4px; text-transform:uppercase; letter-spacing:0.5px;" id="prev-card-badge">TAX INVOICE</span>
                                        <div style="font-size:11px; color:#64748b; margin-top:8px;">INV #BFS-2026-0891</div>
                                        <div style="font-size:11px; color:#64748b;">Date: <?php echo date('d M Y'); ?></div>
                                        <div style="font-size:11px; font-weight:700; color:#0f172a; margin-top:4px;" id="prev-card-gstin">GSTIN: <?php echo htmlspecialchars($profile['gstin'] ?: '07AAAAA0000A1Z5'); ?></div>
                                        <div style="font-size:10px; color:#64748b;" id="prev-card-licenses">
                                            <?php if (!empty($profile['msme_reg_no'])): ?>MSME: <?php echo htmlspecialchars($profile['msme_reg_no']); ?> | <?php endif; ?>
                                            <?php if (!empty($profile['rbi_dsa_reg_no'])): ?>RBI DSA: <?php echo htmlspecialchars($profile['rbi_dsa_reg_no']); ?><?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Security Badges Bar -->
                                <div style="display:flex; justify-content:space-between; align-items:center; background:#f8fafc; border:1px solid #e2e8f0; padding:6px 12px; border-radius:6px; margin-bottom:14px; font-size:10.5px; color:#475569;">
                                    <div id="prev-card-iso-badges" style="display:flex; gap:10px; font-weight:700; color:#1e293b;">
                                        <span>🛡️ ISO 27001</span> <span>🔒 SOC-2 Type II</span>
                                    </div>
                                    <div style="font-weight:700; color:#0284c7;">Currency: <span id="prev-card-currency"><?php echo htmlspecialchars($profile['base_currency'] ?? 'INR (₹)'); ?></span></div>
                                </div>

                                <!-- Sample Table -->
                                <table style="width:100%; border-collapse:collapse; margin-bottom:16px; font-size:12px;">
                                    <thead>
                                        <tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0; text-align:left;">
                                            <th style="padding:8px;">Description</th>
                                            <th style="padding:8px; text-align:center;">SAC</th>
                                            <th style="padding:8px; text-align:right;">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td style="padding:8px; font-weight:700; color:#0f172a;">Home Loan DSA Commission (HDFC Bank)</td>
                                            <td style="padding:8px; text-align:center; color:#64748b;">997159</td>
                                            <td style="padding:8px; text-align:right; font-weight:800; color:#0f172a;" id="prev-card-sample-amt">₹ 1,50,000.00</td>
                                        </tr>
                                    </tbody>
                                </table>

                                <!-- Remittance & Footer -->
                                <div style="display:flex; justify-content:space-between; align-items:flex-end; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px; margin-bottom:12px;">
                                    <div style="font-size:11px; color:#475569;">
                                        <strong style="color:#0f172a;">Bank Details for Remittance:</strong><br>
                                        Bank: <span id="prev-card-bank-name"><?php echo htmlspecialchars($profile['bank_name'] ?: 'HDFC Bank'); ?></span> | A/c: <span id="prev-card-account-number"><?php echo htmlspecialchars($profile['account_number'] ?: '50200012345678'); ?></span><br>
                                        IFSC: <span id="prev-card-ifsc"><?php echo htmlspecialchars($profile['ifsc_code'] ?: 'HDFC0001234'); ?></span> | Branch: <span id="prev-card-bank-branch"><?php echo htmlspecialchars($profile['bank_branch'] ?: 'Connaught Place'); ?></span>
                                    </div>

                                    <div style="display:flex; gap:16px; align-items:center;">
                                        <!-- Dynamic QR Box -->
                                        <div id="prev-card-qr-box" style="text-align:center; border:1px solid #cbd5e1; border-radius:6px; padding:4px; background:#fff;">
                                            <div style="width:42px; height:42px; background:#f1f5f9; display:flex; align-items:center; justify-content:center; font-size:8px; font-weight:800; color:#334155;">
                                                [UPI QR]
                                            </div>
                                            <div style="font-size:8.5px; font-weight:700; color:#64748b; margin-top:2px;">Scan to Pay</div>
                                        </div>

                                        <div style="text-align:center; min-width:130px;">
                                            <div style="height:45px; display:flex; align-items:center; justify-content:center;">
                                                <img src="<?php echo htmlspecialchars($profile['stamp_signature'] ?? ''); ?>" id="prev-card-stamp" style="max-height:45px; max-width:120px; object-fit:contain; <?php echo empty($profile['stamp_signature']) ? 'display:none;' : ''; ?>">
                                                <div id="prev-card-stamp-placeholder" style="border:1px dashed #cbd5e1; border-radius:50%; width:40px; height:40px; display:flex; align-items:center; justify-content:center; font-size:9px; color:#94a3b8; font-weight:700; <?php echo !empty($profile['stamp_signature']) ? 'display:none;' : ''; ?>">SEAL</div>
                                            </div>
                                            <div style="font-size:10px; font-weight:800; color:#0f172a; margin-top:2px;">Authorized Signatory</div>
                                        </div>
                                    </div>
                                </div>

                                <div style="font-size:10.5px; color:#64748b; font-style:italic; border-top:1px dashed #e2e8f0; padding-top:8px;" id="prev-card-footer-note">
                                    <?php echo htmlspecialchars($profile['invoice_footer_note'] ?: 'Please make all NEFT/RTGS payments payable to BFS Financial Services Ltd. TDS u/s 194H applicable.'); ?>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- TAB 2: FINANCIAL & TAX RULES -->
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'): ?>
                <div id="tab-finrules" class="settings-panel">
                    <div class="panel-card">
                        <div class="panel-header">
                            <div>
                                <h2 class="panel-title">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                                    Financial, Tax & Commission Rules
                                </h2>
                                <div style="font-size:12px; color:#64748b; margin-top:2px;">Audit-compliant TDS rates, GST calculations, and default payout percentages</div>
                            </div>
                            <button type="submit" class="btn-black-solid"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg> Save Financial Rules</button>
                        </div>

                        <div class="panel-subtitle" style="margin-top:0;">Tax Rates (TDS & GST)</div>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 16px;">
                            <div>
                                <label class="form-group-title">Outgoing Payout TDS Rate (% u/s 194H) *</label>
                                <input type="number" step="0.1" name="global_tds" value="<?php echo htmlspecialchars($global_tds); ?>" class="ctrl-input" style="font-weight:700;">
                            </div>
                            <div>
                                <label class="form-group-title">Incoming Bank Invoice GST Rate (%) *</label>
                                <input type="number" step="0.1" name="bank_gst_rate" value="<?php echo htmlspecialchars($bank_gst_rate); ?>" class="ctrl-input" style="font-weight:700;">
                            </div>
                            <div>
                                <label class="form-group-title">Bank TDS Deduction Rate (%) *</label>
                                <input type="number" step="0.1" name="bank_tds_rate" value="<?php echo htmlspecialchars($bank_tds_rate); ?>" class="ctrl-input" style="font-weight:700;">
                            </div>
                        </div>

                        <div class="panel-subtitle">Default Payout & Incentive Splits</div>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 16px;">
                            <div>
                                <label class="form-group-title">Default RM Incentive Share Rate (%) *</label>
                                <input type="number" step="0.01" name="default_rm_incentive_pct" value="<?php echo htmlspecialchars($default_rm_incentive_pct); ?>" class="ctrl-input" style="font-weight:700;">
                            </div>
                            <div>
                                <label class="form-group-title">Default Partner Payout Share (%) *</label>
                                <input type="number" step="0.1" name="default_partner_payout_pct" value="<?php echo htmlspecialchars($default_partner_payout_pct); ?>" class="ctrl-input" style="font-weight:700;">
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- TAB 3: SECURITY & IP CONTROL -->
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'): ?>
                <div id="tab-security" class="settings-panel">
                    <div class="panel-card">
                        <div class="panel-header">
                            <div>
                                <h2 class="panel-title">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                                    System Security & Access Controls
                                </h2>
                                <div style="font-size:12px; color:#64748b; margin-top:2px;">IP whitelisting, session timeout policies, and failed login lockout rules</div>
                            </div>
                            <button type="submit" class="btn-black-solid"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg> Save Security Rules</button>
                        </div>

                        <div class="panel-subtitle" style="margin-top:0;">IP Whitelisting & Session Rules</div>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 16px;">
                            <div style="grid-column: 1 / -1;">
                                <label class="form-group-title">Whitelisted Office IP Addresses (Comma-separated, leave blank for all IPs)</label>
                                <input type="text" name="allowed_ips" value="<?php echo htmlspecialchars($allowed_ips); ?>" placeholder="e.g. 103.21.12.5, 122.160.10.4" class="ctrl-input">
                                <span style="font-size:11.5px; color:#64748b; margin-top:4px; display:block;">If set, non-admin staff can only access CRM from these IP addresses.</span>
                            </div>
                            <div>
                                <label class="form-group-title">Auto Session Inactivity Timeout (Minutes)</label>
                                <input type="number" name="session_timeout_mins" value="<?php echo htmlspecialchars($session_timeout_mins); ?>" class="ctrl-input">
                            </div>
                            <div>
                                <label class="form-group-title">Max Failed Login Lockout Attempts</label>
                                <input type="number" name="max_failed_logins" value="<?php echo htmlspecialchars($max_failed_logins); ?>" class="ctrl-input">
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- TAB 4: MODULE FEATURE SWITCHES -->
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'): ?>
                <div id="tab-switches" class="settings-panel">
                    <div class="panel-card">
                        <div class="panel-header">
                            <div>
                                <h2 class="panel-title">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="1" y="5" width="22" height="14" rx="7" ry="7"></rect><circle cx="16" cy="12" r="3"></circle></svg>
                                    CRM Module Feature Toggles (1-Click ON / OFF)
                                </h2>
                                <div style="font-size:12px; color:#64748b; margin-top:2px;">Enable or disable specific CRM modules system-wide</div>
                            </div>
                            <button type="submit" class="btn-black-solid"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg> Save Feature Switches</button>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px;">
                            <div style="background:#f8fafc; border:1px solid #e2e8f0; padding:16px; border-radius:10px; display:flex; justify-content:space-between; align-items:center;">
                                <div>
                                    <div style="font-weight:800; color:#0f172a; font-size:13.5px;">💳 Payout Distribution Hub</div>
                                    <div style="font-size:11.5px; color:#64748b;">Bank claim invoices & RM/Partner remittance advice</div>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="enable_payout_module" value="1" <?php echo $enable_payout_module ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <div style="background:#f8fafc; border:1px solid #e2e8f0; padding:16px; border-radius:10px; display:flex; justify-content:space-between; align-items:center;">
                                <div>
                                    <div style="font-weight:800; color:#0f172a; font-size:13.5px;">📞 WhatsApp Quick Messaging</div>
                                    <div style="font-size:11.5px; color:#64748b;">1-Click document pendency & query WhatsApp chats</div>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="enable_whatsapp_module" value="1" <?php echo $enable_whatsapp_module ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <div style="background:#f8fafc; border:1px solid #e2e8f0; padding:16px; border-radius:10px; display:flex; justify-content:space-between; align-items:center;">
                                <div>
                                    <div style="font-weight:800; color:#0f172a; font-size:13.5px;">⏱️ Geofenced Attendance & GPS</div>
                                    <div style="font-size:11.5px; color:#64748b;">Punch-In/Out tracker with live GPS & battery logs</div>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="enable_attendance_module" value="1" <?php echo $enable_attendance_module ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <div style="background:#f8fafc; border:1px solid #e2e8f0; padding:16px; border-radius:10px; display:flex; justify-content:space-between; align-items:center;">
                                <div>
                                    <div style="font-weight:800; color:#0f172a; font-size:13.5px;">📊 Staff Productivity & HRMS</div>
                                    <div style="font-size:11.5px; color:#64748b;">Target vs achievement scorecards & ranking</div>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="enable_hrms_module" value="1" <?php echo $enable_hrms_module ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <div style="background:#f8fafc; border:1px solid #e2e8f0; padding:16px; border-radius:10px; display:flex; justify-content:space-between; align-items:center;">
                                <div>
                                    <div style="font-weight:800; color:#0f172a; font-size:13.5px;">🧮 Financial Calculators Desk</div>
                                    <div style="font-size:11.5px; color:#64748b;">BT, FOIR, LTV, EMI, and Prepay calculators</div>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="enable_calculators_module" value="1" <?php echo $enable_calculators_module ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <div style="background:#f8fafc; border:1px solid #e2e8f0; padding:16px; border-radius:10px; display:flex; justify-content:space-between; align-items:center;">
                                <div>
                                    <div style="font-weight:800; color:#0f172a; font-size:13.5px;">🔗 Public Client Live Tracker</div>
                                    <div style="font-size:11.5px; color:#64748b;">Tokenized 30-day applicant status URL (track.php)</div>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="enable_public_tracking_module" value="1" <?php echo $enable_public_tracking_module ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- TAB 5: NOTIFICATION TRIGGERS -->
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'): ?>
                <div id="tab-notifications" class="settings-panel">
                    <div class="panel-card">
                        <div class="panel-header">
                            <div>
                                <h2 class="panel-title">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                                    Automated Notification Triggers
                                </h2>
                                <div style="font-size:12px; color:#64748b; margin-top:2px;">Automated email and dispatch triggers on key CRM workflow events</div>
                            </div>
                            <button type="submit" class="btn-black-solid"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg> Save Triggers</button>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px;">
                            <div style="background:#f8fafc; border:1px solid #e2e8f0; padding:16px; border-radius:10px; display:flex; justify-content:space-between; align-items:center;">
                                <div>
                                    <div style="font-weight:800; color:#0f172a; font-size:13.5px;">📩 New Lead Assignment Alert</div>
                                    <div style="font-size:11.5px; color:#64748b;">Notify RM immediately when lead is assigned</div>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="notify_lead_assigned" value="1" <?php echo $notify_lead_assigned ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <div style="background:#f8fafc; border:1px solid #e2e8f0; padding:16px; border-radius:10px; display:flex; justify-content:space-between; align-items:center;">
                                <div>
                                    <div style="font-weight:800; color:#0f172a; font-size:13.5px;">📩 Bank Loan Sanction Notice</div>
                                    <div style="font-size:11.5px; color:#64748b;">Email congratulations letter to applicant on sanction</div>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="notify_sanction_issued" value="1" <?php echo $notify_sanction_issued ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <div style="background:#f8fafc; border:1px solid #e2e8f0; padding:16px; border-radius:10px; display:flex; justify-content:space-between; align-items:center;">
                                <div>
                                    <div style="font-weight:800; color:#0f172a; font-size:13.5px;">💳 Payout Remittance Voucher Email</div>
                                    <div style="font-size:11.5px; color:#64748b;">Auto-send tax voucher on payout disbursal</div>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="notify_disbursement_completed" value="1" <?php echo $notify_disbursement_completed ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <div style="background:#f8fafc; border:1px solid #e2e8f0; padding:16px; border-radius:10px; display:flex; justify-content:space-between; align-items:center;">
                                <div>
                                    <div style="font-weight:800; color:#0f172a; font-size:13.5px;">⚠️ SLA Stalled File Alert</div>
                                    <div style="font-size:11.5px; color:#64748b;">Alert Admin if file is stalled for 3+ days</div>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="notify_sla_stalled" value="1" <?php echo $notify_sla_stalled ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- TAB 6: GAMIFICATION & BONUSES -->
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'): ?>
                <div id="tab-gamification" class="settings-panel">
                    <div class="panel-card">
                        <div class="panel-header">
                            <div>
                                <h2 class="panel-title">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="8" r="7"></circle><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline></svg>
                                    HR Gamification & Over-Achievement Bonus
                                </h2>
                                <div style="font-size:12px; color:#64748b; margin-top:2px;">Performance incentive slabs and attendance penalty thresholds</div>
                            </div>
                            <button type="submit" class="btn-black-solid"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg> Save Gamification Rules</button>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px;">
                            <div>
                                <label class="form-group-title">Target Over-Achievement Bonus Rate (%) *</label>
                                <input type="number" step="0.01" name="bonus_over_target_pct" value="<?php echo htmlspecialchars($bonus_over_target_pct); ?>" class="ctrl-input" style="font-weight:700;">
                                <span style="font-size:11.5px; color:#64748b; margin-top:4px; display:block;">Extra commission % awarded when RM crosses 100% target.</span>
                            </div>
                            <div>
                                <label class="form-group-title">Late Punch-In Warning Limit (Count) *</label>
                                <input type="number" name="late_punch_penalty_count" value="<?php echo htmlspecialchars($late_punch_penalty_count); ?>" class="ctrl-input" style="font-weight:700;">
                                <span style="font-size:11.5px; color:#64748b; margin-top:4px; display:block;">Max late punch-ins before HR alert is flagged.</span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- TAB 7: INTEGRATIONS (SMTP & WHATSAPP) -->
                <div id="tab-integrations" class="settings-panel">
                    <div class="panel-card">
                        <div class="panel-header">
                            <div>
                                <h2 class="panel-title">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                                    Integrations & Communications Setup
                                </h2>
                                <div style="font-size:12px; color:#64748b; margin-top:2px;">SMTP email server credentials, test mail dispatch, and WhatsApp Cloud API</div>
                            </div>
                            <button type="submit" class="btn-black-solid"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg> Save Setup</button>
                        </div>
                        
                        <div class="panel-subtitle" style="margin-top:0;">SMTP Email Credentials</div>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 16px;">
                            <div>
                                <label class="form-group-title">SMTP Host</label>
                                <input type="text" name="smtp_host" value="<?php echo htmlspecialchars($profile['smtp_host'] ?? ''); ?>" placeholder="smtp.gmail.com" class="ctrl-input">
                            </div>
                            <div>
                                <label class="form-group-title">SMTP Port</label>
                                <input type="text" name="smtp_port" value="<?php echo htmlspecialchars($profile['smtp_port'] ?? ''); ?>" placeholder="587 or 465" class="ctrl-input">
                            </div>
                            <div>
                                <label class="form-group-title">SMTP Username / Email</label>
                                <input type="text" name="smtp_username" value="<?php echo htmlspecialchars($profile['smtp_username'] ?? ''); ?>" placeholder="your-email@bfscircle.com" class="ctrl-input">
                            </div>
                            <div>
                                <label class="form-group-title">SMTP Password / App Password</label>
                                <input type="password" name="smtp_password" value="<?php echo htmlspecialchars($profile['smtp_password'] ?? ''); ?>" placeholder="••••••••••••" class="ctrl-input">
                            </div>
                            <div>
                                <label class="form-group-title">Encryption Security</label>
                                <select name="smtp_encryption" class="ctrl-input">
                                    <option value="" <?php echo ($profile['smtp_encryption'] ?? '') === '' ? 'selected' : ''; ?>>None</option>
                                    <option value="tls" <?php echo ($profile['smtp_encryption'] ?? '') === 'tls' ? 'selected' : ''; ?>>TLS (Port 587)</option>
                                    <option value="ssl" <?php echo ($profile['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL (Port 465)</option>
                                </select>
                            </div>
                        </div>

                        <div style="margin-top: 16px; padding: 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                            <div>
                                <div style="font-weight:700; font-size:13px; color:#0f172a;">Verify SMTP Server Connection</div>
                                <div style="font-size:12px; color:#64748b;">Dispatches a test HTML email using current SMTP settings</div>
                            </div>
                            <button type="button" class="btn-black-outline" onclick="triggerTestSmtpEmail()">⚡ Send Test SMTP Email</button>
                        </div>

                        <div class="panel-subtitle" style="margin-top: 26px;">WhatsApp Business Cloud API Settings</div>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 16px;">
                            <div>
                                <label class="form-group-title">Meta Permanent API Access Token</label>
                                <input type="password" name="whatsapp_api_token" value="<?php echo htmlspecialchars($profile['whatsapp_api_token'] ?? ''); ?>" placeholder="EAAG..." class="ctrl-input">
                            </div>
                            <div>
                                <label class="form-group-title">Phone Number ID</label>
                                <input type="text" name="whatsapp_phone_number_id" value="<?php echo htmlspecialchars($profile['whatsapp_phone_number_id'] ?? ''); ?>" placeholder="10594..." class="ctrl-input">
                            </div>
                            <div>
                                <label class="form-group-title">WhatsApp Business Account ID</label>
                                <input type="text" name="whatsapp_business_id" value="<?php echo htmlspecialchars($profile['whatsapp_business_id'] ?? ''); ?>" placeholder="10283..." class="ctrl-input">
                            </div>
                        </div>
                    </div>
                </div>

            </form>

            <!-- TAB 8: BANK COMMISSION SLABS MANAGER -->
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'): ?>
            <div id="tab-slabs" class="settings-panel">
                <div class="panel-card">
                    <div class="panel-header">
                        <div>
                            <h2 class="panel-title">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
                                Bank Volume Commission Slabs Manager
                            </h2>
                            <div style="font-size:12px; color:#64748b; margin-top:2px;">Configures auto-determined slab rates based on cumulative disbursed loan volume</div>
                        </div>
                    </div>

                    <form onsubmit="submitNewCommissionSlab(event)" style="margin-bottom: 24px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px;">
                        <div style="font-size:13.5px; font-weight:800; color:#0f172a; margin-bottom:12px;">Add New Commission Volume Tier</div>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; align-items: flex-end;">
                            <div>
                                <label class="form-group-title">Tier Name *</label>
                                <input type="text" id="slab_tier_name" placeholder="Tier 5 (Over 2Cr)" required class="ctrl-input">
                            </div>
                            <div>
                                <label class="form-group-title">Min Volume (₹)</label>
                                <input type="number" id="slab_min_amt" placeholder="10000000" class="ctrl-input">
                            </div>
                            <div>
                                <label class="form-group-title">Max Volume (₹)</label>
                                <input type="number" id="slab_max_amt" placeholder="20000000" class="ctrl-input">
                            </div>
                            <div>
                                <label class="form-group-title">Commission Rate (%) *</label>
                                <input type="number" step="0.05" id="slab_pct" placeholder="1.85" required class="ctrl-input">
                            </div>
                            <div>
                                <button type="submit" class="btn-black-solid" style="width: 100%; height: 38px; justify-content: center;">Add Volume Tier</button>
                            </div>
                        </div>
                    </form>

                    <div id="commission-slabs-container">
                        <p style="color: #64748b; font-size: 13px;">Loading bank volume slabs...</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- TAB 9: DOCUMENT COMPLIANCE MATRIX -->
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'): ?>
            <div id="tab-docmatrix" class="settings-panel">
                <div class="panel-card">
                    <div class="panel-header">
                        <div>
                            <h2 class="panel-title">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                                Smart Document Compliance Matrix Configurator
                            </h2>
                            <div style="font-size:12px; color:#64748b; margin-top:2px;">Configure mandatory vs optional required documents per loan category</div>
                        </div>
                    </div>

                    <form onsubmit="submitNewDocComplianceItem(event)" style="margin-bottom: 24px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px;">
                        <div style="font-size:13.5px; font-weight:800; color:#0f172a; margin-bottom:12px;">Add Required Document Rule</div>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; align-items: flex-end;">
                            <div>
                                <label class="form-group-title">Loan Product *</label>
                                <select id="doc_rule_loan_type" class="ctrl-input">
                                    <option value="Home Loan">Home Loan</option>
                                    <option value="Personal Loan">Personal Loan</option>
                                    <option value="Business Loan">Business Loan</option>
                                    <option value="Loan Against Property">Loan Against Property</option>
                                    <option value="Gold Loan">Gold Loan</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-group-title">Employment Type</label>
                                <select id="doc_rule_emp_type" class="ctrl-input">
                                    <option value="Salaried">Salaried</option>
                                    <option value="Self-Employed">Self-Employed</option>
                                    <option value="NRI">NRI</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-group-title">Document Category</label>
                                <select id="doc_rule_category" class="ctrl-input">
                                    <option value="Basic KYC">Basic KYC</option>
                                    <option value="Income Proof">Income Proof</option>
                                    <option value="Banking">Banking</option>
                                    <option value="Property Doc">Property Doc</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-group-title">Document Name *</label>
                                <input type="text" id="doc_rule_name" placeholder="e.g. 6-Month Salary Slip" required class="ctrl-input">
                            </div>
                            <div>
                                <button type="submit" class="btn-black-solid" style="width: 100%; height: 38px; justify-content: center;">Add Rule</button>
                            </div>
                        </div>
                    </form>

                    <div id="doc-compliance-matrix-container">
                        <p style="color: #64748b; font-size: 13px;">Loading document compliance rules...</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- TAB: ON-PREMISE DOCUMENT VAULT (F:\BFS) -->
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'): ?>
            <?php
            require_once __DIR__ . '/bfs_vault_engine.php';
            $vaultMetrics = BfsVaultEngine::getVaultMetrics();
            $activeVaultBase = BfsVaultEngine::getActiveBasePath();
            $systemDrives = BfsVaultEngine::getAvailableSystemDrives();

            $vaultLeadFolders = [];
            if (is_dir($activeVaultBase)) {
                $vItems = @scandir($activeVaultBase) ?: [];
                foreach ($vItems as $item) {
                    if ($item === '.' || $item === '..') continue;
                    $itemPath = $activeVaultBase . DIRECTORY_SEPARATOR . $item;
                    if (is_dir($itemPath) && strpos($item, 'Lead_') === 0) {
                        $parts = explode('_', $item, 3);
                        $appId = isset($parts[1]) ? (int)$parts[1] : 0;
                        $appRow = null;
                        if ($appId > 0) {
                            $stmt = $db->prepare("SELECT customer_name, loan_id, loan_type, loan_amount_requested, mobile FROM applicants WHERE id = ?");
                            $stmt->execute([$appId]);
                            $appRow = $stmt->fetch(PDO::FETCH_ASSOC);
                        }
                        
                        $subCounts = [];
                        $totalFolderSize = 0;
                        foreach (BfsVaultEngine::SUBFOLDERS as $key => $subName) {
                            $subDir = $itemPath . DIRECTORY_SEPARATOR . $subName;
                            $cnt = 0;
                            if (is_dir($subDir)) {
                                $files = @scandir($subDir) ?: [];
                                foreach ($files as $f) {
                                    if ($f === '.' || $f === '..') continue;
                                    $fPath = $subDir . DIRECTORY_SEPARATOR . $f;
                                    if (is_file($fPath)) {
                                        $cnt++;
                                        $totalFolderSize += @filesize($fPath) ?: 0;
                                    }
                                }
                            }
                            $subCounts[$key] = $cnt;
                        }

                        $vaultLeadFolders[] = [
                            'folder_name'  => $item,
                            'folder_path'  => $itemPath,
                            'applicant_id' => $appId,
                            'customer_name'=> $appRow['customer_name'] ?? (isset($parts[2]) ? str_replace('_', ' ', $parts[2]) : 'Applicant'),
                            'loan_id'      => $appRow['loan_id'] ?? 'REF-PENDING',
                            'loan_type'    => $appRow['loan_type'] ?? 'Loan',
                            'mobile'       => $appRow['mobile'] ?? '-',
                            'sub_counts'   => $subCounts,
                            'total_files'  => array_sum($subCounts),
                            'size_kb'      => round($totalFolderSize / 1024, 1)
                        ];
                    }
                }
            }
            ?>
            <div id="tab-vault" class="settings-panel <?= $active_tab === 'vault' ? 'active' : '' ?>">
                <div class="panel-card" style="padding: 24px;">
                    
                    <!-- 1. COMPACT MASTER HEADER -->
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:16px; border-bottom:1px solid #e2e8f0; padding-bottom:18px;">
                        <div>
                            <div style="display:flex; align-items:center; gap:10px;">
                                <h2 class="panel-title" style="margin:0; font-size:18px; font-weight:800; color:#0f172a; display:flex; align-items:center; gap:8px;">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect><rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect><line x1="6" y1="6" x2="6.01" y2="6"></line><line x1="6" y1="18" x2="6.01" y2="18"></line></svg>
                                    On-Premise Document Vault Storage
                                </h2>
                                <span style="background:#dcfce7; color:#15803d; border:1px solid #bbf7d0; font-size:11px; font-weight:800; padding:2px 8px; border-radius:20px; display:inline-flex; align-items:center; gap:4px;">
                                    <span style="width:6px; height:6px; border-radius:50%; background:#16a34a;"></span>
                                    ONLINE &amp; ACTIVE
                                </span>
                            </div>
                            <div style="font-size:12px; color:#64748b; margin-top:5px; display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                                <span>Storage Root: <strong style="color:#0f172a; font-family:monospace; background:#f1f5f9; padding:2px 6px; border-radius:4px;"><?= htmlspecialchars($activeVaultBase) ?></strong></span>
                                <span>•</span>
                                <span style="color:#0284c7; font-weight:600;">Zero Cloud Cost (0 MB VPS Load)</span>
                                <span>•</span>
                                <span style="color:#475569;">Auto-Sync Worker: Active (Every 60s)</span>
                            </div>
                        </div>

                        <!-- 3 Clear Primary Actions -->
                        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                            <button type="button" onclick="openRootVaultFolder()" class="btn-black-solid" style="font-size:12px; padding:7px 14px; display:inline-flex; align-items:center; gap:6px; cursor:pointer;" title="Open in Windows Explorer">
                                📂 Open F:\BFS in Explorer
                            </button>
                            <button type="button" onclick="syncWithVpsBridge()" id="btn-sync-vps-action" class="btn-black-outline" style="font-size:12px; padding:7px 14px; display:inline-flex; align-items:center; gap:6px; cursor:pointer;" title="Ping VPS and pull new uploads">
                                ⚡ Sync with VPS
                            </button>
                            <button type="button" onclick="toggleDriveRelocationDrawer()" id="btn-toggle-drawer" class="btn-black-outline" style="font-size:12px; padding:7px 12px; display:inline-flex; align-items:center; gap:5px; cursor:pointer;">
                                ⚙️ Change Drive ▾
                            </button>
                        </div>
                    </div>

                    <!-- 2. SLIM 4-METRIC STATUS ROW -->
                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:12px; margin-top:18px;">
                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px 16px;">
                            <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.4px;">Drive Free Space</div>
                            <div style="font-size:20px; font-weight:800; color:#047857; margin-top:3px; font-family:'Outfit',sans-serif;">
                                <?= $vaultMetrics['disk_free_gb'] ?> GB
                            </div>
                            <div style="font-size:11px; color:#64748b; margin-top:2px;">
                                <?= $vaultMetrics['disk_free_percent'] ?>% available on Drive <?= $vaultMetrics['drive_letter'] ?>
                            </div>
                        </div>

                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px 16px;">
                            <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.4px;">Active Lead Vaults</div>
                            <div style="font-size:20px; font-weight:800; color:#0f172a; margin-top:3px; font-family:'Outfit',sans-serif;">
                                <?= count($vaultLeadFolders) ?> Leads
                            </div>
                            <div style="font-size:11px; color:#64748b; margin-top:2px;">
                                Structured isolated directories
                            </div>
                        </div>

                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px 16px;">
                            <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.4px;">Indexed Documents</div>
                            <div style="font-size:20px; font-weight:800; color:#0284c7; margin-top:3px; font-family:'Outfit',sans-serif;">
                                <?= $vaultMetrics['total_files_in_vault'] ?> Files
                            </div>
                            <div style="font-size:11px; color:#64748b; margin-top:2px;">
                                Total size: <?= $vaultMetrics['vault_size_mb'] ?> MB
                            </div>
                        </div>

                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px 16px;">
                            <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.4px;">Cloud VPS Disk Load</div>
                            <div style="font-size:20px; font-weight:800; color:#d97706; margin-top:3px; font-family:'Outfit',sans-serif;">
                                0 MB (100% Offloaded)
                            </div>
                            <div style="font-size:11px; color:#64748b; margin-top:2px;">
                                Auto-purged from VPS server
                            </div>
                        </div>
                    </div>

                    <!-- 3. COLLAPSIBLE DRIVE RELOCATION DRAWER (Hidden by default, opens on click) -->
                    <div id="drive-relocation-drawer" style="display:none; margin-top:16px; background:#ffffff; border:1.5px solid #cbd5e1; border-radius:10px; padding:18px; box-shadow:0 4px 15px rgba(0,0,0,0.05);">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                            <h4 style="margin:0; font-size:14px; font-weight:800; color:#0f172a; display:flex; align-items:center; gap:6px;">
                                ⚙️ Switch Storage Drive / Folder Location
                            </h4>
                            <button type="button" onclick="toggleDriveRelocationDrawer()" style="background:none; border:none; color:#64748b; font-size:18px; cursor:pointer; line-height:1;">&times;</button>
                        </div>
                        <p style="font-size:12px; color:#64748b; margin:0 0 12px 0;">
                            Select any detected machine drive or enter a custom folder path. Existing files will be copied automatically without breaking links.
                        </p>

                        <!-- Quick select drive letter pills -->
                        <div style="display:flex; gap:6px; flex-wrap:wrap; margin-bottom:12px;">
                            <?php foreach ($systemDrives as $drv): ?>
                                <button type="button" onclick="selectVaultDrive('<?= addslashes($drv['letter']) ?>\\BFS\\Vault_Leads')" class="btn-black-outline" style="font-size:11px; padding:5px 10px; cursor:pointer; background:#f8fafc; border-color:#cbd5e1; display:inline-flex; align-items:center; gap:5px;">
                                    <strong><?= $drv['letter'] ?></strong> (<?= $drv['free_gb'] ?> GB Free)
                                </button>
                            <?php endforeach; ?>
                        </div>

                        <!-- Target folder input -->
                        <div style="display:grid; grid-template-columns:1fr auto; gap:10px; align-items:center;">
                            <input type="text" id="new_vault_path_input" class="ctrl-input" value="<?= htmlspecialchars($activeVaultBase) ?>" placeholder="e.g. D:\BFS\Vault_Leads" style="padding:8px 12px; font-size:12.5px; font-family:monospace; width:100%; box-sizing:border-box;">
                            <button type="button" onclick="submitVaultPathChange()" id="btn-save-vault-path" class="btn-black-solid" style="padding:9px 16px; font-size:12px; font-weight:800; white-space:nowrap; cursor:pointer;">
                                🚀 Validate &amp; Switch
                            </button>
                        </div>

                        <div style="display:flex; align-items:center; gap:8px; margin-top:8px; font-size:11.5px; color:#475569;">
                            <input type="checkbox" id="chk_migrate_vault_files" checked style="width:15px; height:15px; cursor:pointer;">
                            <label for="chk_migrate_vault_files" style="cursor:pointer;">
                                Automatically copy existing lead files (<?= $vaultMetrics['total_files_in_vault'] ?> files) to the new drive &amp; update CRM links
                            </label>
                        </div>
                    </div>

                    <!-- 4. MAIN HERO: ACTIVE PHYSICAL LEAD FOLDERS TABLE & PAGE MANAGEMENT -->
                    <div style="margin-top:22px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; gap:14px; flex-wrap:wrap;">
                            <div style="flex-shrink:0;">
                                <h3 style="margin:0; font-size:15px; font-weight:800; color:#0f172a; display:flex; align-items:center; gap:8px;">
                                    Active Customer Vault Directories
                                    <span id="vault-lead-badge" style="background:#e0f2fe; color:#0369a1; font-size:11px; font-weight:700; padding:2px 8px; border-radius:12px;">
                                        <?= count($vaultLeadFolders) ?> Leads
                                    </span>
                                </h3>
                                <div style="font-size:11.5px; color:#64748b; margin-top:2px;">Individual categorized folders provisioned on office local storage.</div>
                            </div>

                            <!-- Search, Filters, Sort, and Export Tools (INLINE SINGLE ROW) -->
                            <div style="display:flex; align-items:center; gap:8px; flex-wrap:nowrap; margin-left:auto;">
                                <input type="text" id="vault-settings-filter" class="vault-toolbar-input" placeholder="🔍 Search applicant, folder..." oninput="filterAndRenderVaultRoster(true)">

                                <select id="vault-filter-product" class="vault-toolbar-select" onchange="filterAndRenderVaultRoster(true)">
                                    <option value="">All Loan Products</option>
                                </select>

                                <select id="vault-sort-select" class="vault-toolbar-select" onchange="filterAndRenderVaultRoster(true)">
                                    <option value="name_asc">Sort: Customer (A → Z)</option>
                                    <option value="name_desc">Sort: Customer (Z → A)</option>
                                    <option value="size_desc">Sort: Size (Largest First)</option>
                                    <option value="size_asc">Sort: Size (Smallest First)</option>
                                    <option value="files_desc">Sort: Files (Most First)</option>
                                    <option value="files_asc">Sort: Files (Least First)</option>
                                </select>

                                <button type="button" onclick="resetVaultFilters()" class="btn-black-outline vault-toolbar-btn" style="width:34px; padding:0;" title="Reset search &amp; filters">
                                    🔄
                                </button>

                                <button type="button" onclick="exportVaultRosterCSV()" class="btn-black-outline vault-toolbar-btn" style="padding:0 12px; color:#0369a1; border-color:#bae6fd; background:#f0f9ff; font-weight:700; white-space:nowrap;" title="Export current list to CSV">
                                    📥 CSV
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="data-table" id="vault-settings-table" style="font-size:12.5px; width:100%;">
                                <thead>
                                    <tr style="background:#f8fafc; border-bottom:2px solid #e2e8f0;">
                                        <th style="padding:10px 12px; text-align:left;">Applicant &amp; Folder</th>
                                        <th style="padding:10px 12px; text-align:left;">Loan Ref &amp; Product</th>
                                        <th style="padding:10px 12px; text-align:center;">Category Breakdown</th>
                                        <th style="padding:10px 12px; text-align:right;">Total Size &amp; Files</th>
                                        <th style="padding:10px 12px; text-align:right;">Dossier &amp; Explorer Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="vault-roster-tbody">
                                    <!-- Rendered dynamically via JS engine for zero-lag pagination & sorting -->
                                </tbody>
                            </table>
                        </div>

                        <!-- Executive Pagination Footer Bar -->
                        <div style="display:flex; justify-content:space-between; align-items:center; padding-top:14px; margin-top:14px; border-top:1px solid #e2e8f0; flex-wrap:wrap; gap:12px;">
                            <div style="display:flex; align-items:center; gap:8px; font-size:12.5px; color:#64748b; font-weight:500; white-space:nowrap; flex-shrink:0;">
                                <span>Show</span>
                                <select id="vaultPageSizeSelect" onchange="changeVaultPageSize(this.value)" style="padding:5px 9px; border-radius:6px; border:1px solid #cbd5e1; font-size:12px; font-weight:700; color:#0f172a; background:#ffffff; cursor:pointer;">
                                    <option value="5">5</option>
                                    <option value="10" selected>10</option>
                                    <option value="25">25</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                </select>
                                <span>entries per page</span>
                            </div>

                            <div id="vault-pagination-info" style="font-size:12.5px; color:#64748b; font-weight:500; white-space:nowrap;">
                                Showing <b>0</b> to <b>0</b> of <b>0</b> entries
                            </div>

                            <div id="vault-pagination-controls" style="display:flex; gap:4px; align-items:center; flex-wrap:wrap;">
                                <!-- Dynamically populated via JS -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- TAB 10: PRODUCTS & LEAD ROUTING -->
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'): ?>
            <div id="tab-crmconfig" class="settings-panel">
                <div class="panel-card">
                    <div class="panel-header">
                        <div>
                            <h2 class="panel-title">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                                Products, Lead Routing & Automations
                            </h2>
                            <div style="font-size:12px; color:#64748b; margin-top:2px;">Configure lead auto-assignment, SLA stalling warnings, loan categories, and lead channels</div>
                        </div>
                    </div>

                    <form onsubmit="saveCrmRules(event)" id="rules-form" style="margin-bottom: 24px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px;">
                        <div style="font-size:14px; font-weight:800; color:#0f172a; margin-bottom:14px;">Routing & SLA Workflow Settings</div>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px;">
                            <div>
                                <label class="form-group-title">Lead Auto-Assign Logic</label>
                                <select name="lead_auto_assign" class="ctrl-input">
                                    <option value="Round Robin (Distribute Equally)" <?php echo $lead_auto_assign == 'Round Robin (Distribute Equally)' ? 'selected':''; ?>>Round Robin (Distribute Equally)</option>
                                    <option value="Assign to Admin Only" <?php echo $lead_auto_assign == 'Assign to Admin Only' ? 'selected':''; ?>>Assign to Admin Only</option>
                                    <option value="Assign by City (Advanced)" <?php echo $lead_auto_assign == 'Assign by City (Advanced)' ? 'selected':''; ?>>Assign by City (Advanced)</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-group-title">SLA Stalled File Warning (Days)</label>
                                <input type="number" name="sla_stalled_days" value="<?php echo htmlspecialchars($sla_stalled_days); ?>" class="ctrl-input">
                            </div>
                            <div>
                                <label class="form-group-title">Client Tracking Expiry (Days)</label>
                                <input type="number" name="public_tracking_expiry_days" value="<?php echo htmlspecialchars($public_tracking_expiry_days); ?>" class="ctrl-input">
                            </div>
                            <div style="display:flex; align-items:flex-end;">
                                <button type="submit" class="btn-black-solid" style="width:100%; height:38px; justify-content:center;">Save Workflow Rules</button>
                            </div>
                        </div>
                    </form>

                    <!-- Loan Products Manager -->
                    <div style="border: 1px solid #e2e8f0; padding: 20px; border-radius: 10px; background: #ffffff; margin-bottom: 20px;">
                        <h3 style="font-size: 14.5px; font-weight: 800; color: #0f172a; margin: 0 0 10px 0;">Manage Loan Categories</h3>
                        <p style="font-size: 12.5px; color: #64748b; margin-bottom: 14px;">Categories available when logging applicants and leads across the CRM.</p>
                        <form onsubmit="addLoanProduct(event)">
                            <div style="display:flex; gap:10px; margin-bottom: 16px;">
                                <input type="text" id="new-loan-product" placeholder="e.g. Education Loan" required class="ctrl-input" style="flex:1;">
                                <button type="submit" class="btn-black-solid">Add Product</button>
                            </div>
                        </form>
                        <div style="display:flex; flex-wrap:wrap; gap:8px;" id="loan-products-list">
                            <?php foreach($loan_products as $idx => $lp): ?>
                            <span style="background: #f8fafc; border: 1px solid #cbd5e1; padding: 6px 12px; border-radius: 6px; font-size: 13px; font-weight: 700; color: #0f172a;">
                                <?php echo htmlspecialchars($lp); ?> 
                                <span style="cursor:pointer; color:#ef4444; margin-left:6px; font-weight:bold;" onclick="removeListSetting('loan', <?php echo $idx; ?>)">&times;</span>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Lead Sources Manager -->
                    <div style="border: 1px solid #e2e8f0; padding: 20px; border-radius: 10px; background: #ffffff;">
                        <h3 style="font-size: 14.5px; font-weight: 800; color: #0f172a; margin: 0 0 10px 0;">Manage Lead Source Channels</h3>
                        <p style="font-size: 12.5px; color: #64748b; margin-bottom: 14px;">Acquisition channels for lead tracking & analytics.</p>
                        <form onsubmit="addLeadSource(event)">
                            <div style="display:flex; gap:10px; margin-bottom: 16px;">
                                <input type="text" id="new-lead-source" placeholder="e.g. JustDial" required class="ctrl-input" style="flex:1;">
                                <button type="submit" class="btn-black-solid">Add Source</button>
                            </div>
                        </form>
                        <div style="display:flex; flex-wrap:wrap; gap:8px;" id="lead-sources-list">
                            <?php foreach($lead_sources as $idx => $ls): ?>
                            <span style="background: #f8fafc; border: 1px solid #cbd5e1; padding: 6px 12px; border-radius: 6px; font-size: 13px; font-weight: 700; color: #0f172a;">
                                <?php echo htmlspecialchars($ls); ?> 
                                <span style="cursor:pointer; color:#ef4444; margin-left:6px; font-weight:bold;" onclick="removeListSetting('source', <?php echo $idx; ?>)">&times;</span>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- TAB 11: MANAGE USERS -->
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'): ?>
            <div id="tab-users" class="settings-panel">
                <div class="panel-card">
                    <div class="panel-header">
                        <div>
                            <h2 class="panel-title">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 1-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                                Manage CRM Users & Access Roster
                            </h2>
                            <div style="font-size:12px; color:#64748b; margin-top:2px;">Create, edit, reset passwords, set monthly targets, and manage permissions</div>
                        </div>
                    </div>
                    
                    <form id="create-user-form" onsubmit="createUser(event)" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; margin-bottom: 24px;">
                        <div style="font-size: 14px; font-weight: 800; color: #0f172a; margin-bottom: 14px;">Register New Team Member</div>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; align-items: flex-end;">
                            <div>
                                <label class="form-group-title">Full Name *</label>
                                <input type="text" name="name" required placeholder="User Name" class="ctrl-input">
                            </div>
                            <div>
                                <label class="form-group-title">Role *</label>
                                <select name="role" id="new_user_role" onchange="toggleUserFields()" required class="ctrl-input">
                                    <option value="Staff">Staff / Relationship Manager</option>
                                    <option value="Agent">Agent (Individual)</option>
                                    <option value="CA">Chartered Accountant</option>
                                    <option value="Builder">Builder</option>
                                    <option value="Partner">Partner (DSA)</option>
                                </select>
                            </div>
                            <div id="staff_type_group">
                                <label class="form-group-title">Staff Type</label>
                                <select name="staff_type" class="ctrl-input">
                                    <option value="In-Office">In-Office</option>
                                    <option value="Field">Field Officer</option>
                                </select>
                            </div>
                            <div id="dashboard_access_group">
                                <label class="form-group-title">Dashboard Access?</label>
                                <select name="has_dashboard" id="has_dashboard" onchange="toggleUserFields()" class="ctrl-input">
                                    <option value="1">Yes (Provide Login Access)</option>
                                    <option value="0">No (Directory Entry Only)</option>
                                </select>
                            </div>
                            <div id="login_id_group">
                                <label class="form-group-title">Login Username *</label>
                                <input type="text" name="username" id="username_input" required placeholder="rm_verma" class="ctrl-input">
                            </div>
                            <div id="password_group">
                                <label class="form-group-title">Password *</label>
                                <input type="password" name="password" id="password_input" required minlength="6" placeholder="At least 6 chars" class="ctrl-input">
                            </div>
                            <div id="target_group">
                                <label class="form-group-title">Monthly Target (₹)</label>
                                <input type="number" name="monthly_target" value="15000000" class="ctrl-input">
                            </div>
                            <div id="commission_group">
                                <label class="form-group-title">Incentive Share Rate (%)</label>
                                <input type="number" step="0.01" name="commission_rate" value="0.20" class="ctrl-input">
                            </div>

                            <!-- GRANULAR ACCESS & PERMISSIONS MATRIX -->
                            <div style="grid-column: 1 / -1; margin-top: 6px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 10px; padding: 16px;">
                                <div style="font-size: 13.5px; font-weight: 800; color: #0f172a; margin-bottom: 10px; display:flex; align-items:center; justify-content:space-between;">
                                    <span style="display:flex; align-items:center; gap:8px;">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                                        Granular Access & Operational Rights Matrix
                                    </span>
                                    <span style="font-size: 11px; color:#64748b; font-weight:600;">Grant custom advanced rights to this RM</span>
                                </div>

                                <div style="display:flex; gap:6px; flex-wrap:wrap; margin-bottom:12px; background:#f1f5f9; padding:8px 12px; border-radius:8px; align-items:center;">
                                    <span style="font-size:11.5px; font-weight:700; color:#475569; margin-right:4px;">⚡ Quick Presets:</span>
                                    <button type="button" class="btn-black-outline" style="font-size:11px; padding:3px 8px; background:#fff;" onclick="applyPermPreset('new', 'senior_rm')">🛡️ Senior RM</button>
                                    <button type="button" class="btn-black-outline" style="font-size:11px; padding:3px 8px; background:#fff;" onclick="applyPermPreset('new', 'junior_rm')">💼 Junior RM</button>
                                    <button type="button" class="btn-black-outline" style="font-size:11px; padding:3px 8px; background:#fff;" onclick="applyPermPreset('new', 'auditor')">📊 Auditor / CA</button>
                                    <button type="button" class="btn-black-outline" style="font-size:11px; padding:3px 8px; background:#fff;" onclick="applyPermPreset('new', 'view_only')">👁️‍🗨️ View Only</button>
                                    <button type="button" class="btn-black-outline" style="font-size:11px; padding:3px 8px; background:#fff;" onclick="applyPermPreset('new', 'all')">✅ Select All</button>
                                    <button type="button" class="btn-black-outline" style="font-size:11px; padding:3px 8px; background:#fff;" onclick="applyPermPreset('new', 'none')">🧹 Clear All</button>
                                </div>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 10px; font-size: 12px;">
                                    <label style="display:flex; align-items:center; gap:8px; background:#fff1f2; padding:8px 12px; border-radius:6px; border:1px solid #fecdd3; cursor:pointer;">
                                        <input type="checkbox" name="custom_permissions[view_only_mode]" value="1">
                                        <span>👁️‍🗨️ <strong style="color:#e11d48;">View-Only Mode</strong> (Disable Editing)</span>
                                    </label>
                                    <label style="display:flex; align-items:center; gap:8px; background:#f8fafc; padding:8px 12px; border-radius:6px; border:1px solid #e2e8f0; cursor:pointer;">
                                        <input type="checkbox" name="custom_permissions[view_all_applicants]" value="1">
                                        <span>👁️ <strong>View All Clients</strong> (Company-wide)</span>
                                    </label>
                                    <label style="display:flex; align-items:center; gap:8px; background:#f8fafc; padding:8px 12px; border-radius:6px; border:1px solid #e2e8f0; cursor:pointer;">
                                        <input type="checkbox" name="custom_permissions[export_data]" value="1">
                                        <span>📊 <strong>Data Export Access</strong> (SQL/JSON/CSV)</span>
                                    </label>
                                    <label style="display:flex; align-items:center; gap:8px; background:#f8fafc; padding:8px 12px; border-radius:6px; border:1px solid #e2e8f0; cursor:pointer;">
                                        <input type="checkbox" name="custom_permissions[edit_sanctions]" value="1">
                                        <span>🏦 <strong>Bank Sanction Override</strong></span>
                                    </label>
                                    <label style="display:flex; align-items:center; gap:8px; background:#f8fafc; padding:8px 12px; border-radius:6px; border:1px solid #e2e8f0; cursor:pointer;">
                                        <input type="checkbox" name="custom_permissions[manage_bank_logins]" value="1" checked>
                                        <span>🛡️ <strong>Manage Bank Logins</strong></span>
                                    </label>
                                    <label style="display:flex; align-items:center; gap:8px; background:#f8fafc; padding:8px 12px; border-radius:6px; border:1px solid #e2e8f0; cursor:pointer;">
                                        <input type="checkbox" name="custom_permissions[manage_payouts]" value="1">
                                        <span>💰 <strong>Payout & Invoice Access</strong></span>
                                    </label>
                                    <label style="display:flex; align-items:center; gap:8px; background:#f8fafc; padding:8px 12px; border-radius:6px; border:1px solid #e2e8f0; cursor:pointer;">
                                        <input type="checkbox" name="custom_permissions[resolve_sla_alerts]" value="1" checked>
                                        <span>⚡ <strong>Resolve SLA Alerts</strong></span>
                                    </label>
                                    <label style="display:flex; align-items:center; gap:8px; background:#f8fafc; padding:8px 12px; border-radius:6px; border:1px solid #e2e8f0; cursor:pointer;">
                                        <input type="checkbox" name="custom_permissions[delete_records]" value="1">
                                        <span>🗑️ <strong>Delete Records Rights</strong></span>
                                    </label>
                                    <label style="display:flex; align-items:center; gap:8px; background:#f8fafc; padding:8px 12px; border-radius:6px; border:1px solid #e2e8f0; cursor:pointer;">
                                        <input type="checkbox" name="custom_permissions[access_settings]" value="1">
                                        <span>⚙️ <strong>CRM Settings Access</strong></span>
                                    </label>
                                </div>
                            </div>

                            <div style="grid-column: 1 / -1; display:flex; justify-content:flex-end;">
                                <button type="submit" class="btn-black-solid">Register User</button>
                            </div>
                        </div>
                    </form>
                    
                    <div style="font-size: 14.5px; font-weight: 800; color: #0f172a; margin-bottom: 12px;">Active User Roster</div>
                    <div id="users-list-container">
                        <p style="color: #64748b; font-size: 13px;">Loading users...</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- TAB 12: EMAIL TEMPLATES -->
            <div id="tab-templates" class="settings-panel">
                <div class="panel-card">
                    <div class="panel-header">
                        <h2 class="panel-title">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line></svg>
                            Email Templates Hub
                        </h2>
                    </div>
                    
                    <form id="create-template-form" onsubmit="saveTemplate(event)" enctype="multipart/form-data" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; margin-bottom: 24px;">
                        <div style="font-size: 14px; font-weight: 800; color: #0f172a; margin-bottom: 14px;">Create New HTML Letterhead Template</div>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 16px;">
                            <div>
                                <label class="form-group-title">Template Name *</label>
                                <input type="text" name="template_name" placeholder="e.g. Disbursal Gratitude" required class="ctrl-input">
                            </div>
                            <div>
                                <label class="form-group-title">Template Category *</label>
                                <select name="type" required class="ctrl-input">
                                    <option value="Pitch">Pitch / Lead Conversion</option>
                                    <option value="PPT">Presentation Deck</option>
                                    <option value="Custom Mail">Custom Mail</option>
                                </select>
                            </div>
                            <div style="grid-column: 1 / -1;">
                                <label class="form-group-title">Subject Line *</label>
                                <input type="text" name="subject" placeholder="Email Subject" required class="ctrl-input">
                            </div>
                            <div style="grid-column: 1 / -1;">
                                <label class="form-group-title">Message Body *</label>
                                <textarea name="body" rows="6" placeholder="Write template body..." required class="ctrl-input" style="font-family:inherit; line-height:1.5;"></textarea>
                            </div>
                            <div style="grid-column: 1 / -1;">
                                <label class="form-group-title">Default Attachment (PDF / Document)</label>
                                <input type="file" name="attachment" accept=".ppt,.pptx,.pdf,.doc,.docx,.xls,.xlsx,.zip" style="font-size:13px;">
                            </div>
                        </div>
                        <div style="margin-top: 16px; display: flex; justify-content: flex-end;">
                            <button type="submit" class="btn-black-solid">Save Template</button>
                        </div>
                    </form>
                    
                    <div style="font-size: 14.5px; font-weight: 800; color: #0f172a; margin-bottom: 12px;">Saved Templates</div>
                    <div id="templates-list-container">
                        <p style="color: #64748b; font-size: 13px;">Loading templates...</p>
                    </div>
                </div>
            </div>

            <!-- TAB 13: PPT LIBRARY -->
            <div id="tab-ppts" class="settings-panel">
                <div class="panel-card">
                    <div class="panel-header">
                        <h2 class="panel-title">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                            Presentation (PPT / PDF) Decks
                        </h2>
                    </div>
                    
                    <form id="ppt-upload-form" onsubmit="savePpt(event)" enctype="multipart/form-data" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; margin-bottom: 24px;">
                        <div style="font-size: 14px; font-weight: 800; color: #0f172a; margin-bottom: 14px;">Upload Presentation Deck</div>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 16px; align-items: flex-end;">
                            <div>
                                <label class="form-group-title">Deck Title *</label>
                                <input type="text" name="original_name" required placeholder="Corporate Pitch Deck 2026" class="ctrl-input">
                            </div>
                            <div>
                                <label class="form-group-title">File Attachment (.ppt, .pptx, .pdf) *</label>
                                <input type="file" name="ppt_file" required accept=".ppt,.pptx,.pdf" style="font-size:13px;">
                            </div>
                            <div>
                                <button type="submit" class="btn-black-solid" style="width:100%; height:38px; justify-content:center;">Upload Deck</button>
                            </div>
                        </div>
                    </form>
                    
                    <div style="font-size: 14.5px; font-weight: 800; color: #0f172a; margin-bottom: 12px;">Saved Presentation Files</div>
                    <div id="ppts-list-container">
                        <p style="color: #64748b; font-size: 13px;">Loading presentation decks...</p>
                    </div>
                </div>
            </div>

            <!-- TAB: CLIENT TRACKING LINKS & PORTAL ROSTER -->
            <div id="tab-tracking" class="settings-panel <?= $active_tab === 'tracking' ? 'active' : '' ?>">
                <div class="panel-card">
                    <div class="panel-header" style="flex-wrap:wrap; gap:12px;">
                        <div>
                            <h2 class="panel-title">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
                                Client Live Tracking Portal & Secure Access Links
                            </h2>
                            <div style="font-size:12px; color:#64748b; margin-top:2px;">Manage 30-day self-service client tracking URLs, QR code passes, and WhatsApp link dispatch</div>
                        </div>

                        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                            <button type="button" class="btn-black-solid" onclick="bulkGenerateTrackingTokens()" style="font-size:12px; padding:8px 14px;">
                                ⚡ Bulk Generate Missing Tokens
                            </button>
                        </div>
                    </div>

                    <!-- Base URL Banner -->
                    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:14px 18px; margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                        <div>
                            <div style="font-size:11px; font-weight:800; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;">Universal Client Portal Base URL Structure</div>
                            <div id="global-tracking-base-url" style="font-family:monospace; font-size:13px; font-weight:700; color:#0f172a; margin-top:2px;">
                                http://localhost:8000/track.php?token={32_CHAR_HEX_TOKEN}
                            </div>
                        </div>
                        <span style="background:#f0fdf4; color:#166534; border:1px solid #bbf7d0; font-size:11px; font-weight:700; padding:4px 10px; border-radius:6px;">
                            ✓ Active 256-Bit Encrypted Links
                        </span>
                    </div>

                    <!-- Search Bar -->
                    <div style="display:flex; gap:12px; margin-bottom:16px; align-items:center;">
                        <div style="flex:1; position:relative;">
                            <input type="text" id="tracking-search-input" placeholder="Search applicant by Name, File ID, or Mobile..." onkeyup="loadTrackingTokensList(this.value)" class="ctrl-input" style="padding-left:36px;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#94a3b8;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        </div>
                        <button type="button" onclick="loadTrackingTokensList(document.getElementById('tracking-search-input').value)" class="btn-black-outline" style="padding:9px 16px;">
                            Refresh List
                        </button>
                    </div>

                    <!-- Tokens Container Table -->
                    <div class="table-responsive" id="tracking-tokens-container">
                        <p style="color:#64748b; font-size:13px; padding:20px; text-align:center;">Loading tracking links roster...</p>
                    </div>
                </div>
            </div>

            <!-- TAB 14: DATABASE BACKUP & HEALTH DESK -->
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'): ?>
            <div id="tab-backup" class="settings-panel <?= $active_tab === 'backup' ? 'active' : '' ?>">
                <div class="panel-card">
                    <div class="panel-header" style="flex-wrap:wrap; gap:12px;">
                        <div>
                            <h2 class="panel-title">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                                Enterprise Database Backup & Data Migration Hub
                            </h2>
                            <div style="font-size:12px; color:#64748b; margin-top:2px;">Multi-format data exports (SQLite, SQL Dump, JSON, CSV), smart de-duplicated imports, and automated snapshot retention</div>
                        </div>
                        
                        <!-- MULTI-FORMAT EXPORT TOOLBAR -->
                        <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                            <a href="api.php?api=download_db_backup" class="btn-black-solid" style="text-decoration:none; font-size:12px; padding:7px 12px;">
                                📥 Binary (.sqlite)
                            </a>
                            <a href="api.php?api=export_sql_dump" class="btn-black-outline" style="text-decoration:none; font-size:12px; padding:7px 12px;">
                                📄 SQL Dump (.sql)
                            </a>
                            <a href="api.php?api=export_json_backup" class="btn-black-outline" style="text-decoration:none; font-size:12px; padding:7px 12px;">
                                📦 JSON Bundle (.json)
                            </a>
                            <a href="api.php?api=export_module_csv&module=applicants" class="btn-black-outline" style="text-decoration:none; font-size:12px; padding:7px 12px;">
                                📊 Clients CSV (.csv)
                            </a>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-top: 20px;" id="system-health-cards">
                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:18px;">
                            <div style="font-size:12px; font-weight:700; color:#64748b; text-transform:uppercase;">Database File Size</div>
                            <div style="font-size:22px; font-weight:800; color:#0f172a; margin-top:4px;" id="health-db-size">--</div>
                        </div>
                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:18px;">
                            <div style="font-size:12px; font-weight:700; color:#64748b; text-transform:uppercase;">Uploads Directory Storage</div>
                            <div style="font-size:22px; font-weight:800; color:#0f172a; margin-top:4px;" id="health-uploads-size">--</div>
                        </div>
                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:18px;">
                            <div style="font-size:12px; font-weight:700; color:#64748b; text-transform:uppercase;">Total Registered Applicants</div>
                            <div style="font-size:22px; font-weight:800; color:#0f172a; margin-top:4px;" id="health-applicants">--</div>
                        </div>
                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:18px;">
                            <div style="font-size:12px; font-weight:700; color:#64748b; text-transform:uppercase;">Total System Audit Logs</div>
                            <div style="font-size:22px; font-weight:800; color:#0f172a; margin-top:4px;" id="health-logs">--</div>
                        </div>
                    </div>

                    <!-- IMPORT & RESTORE DATABASE DESK -->
                    <div style="margin-top: 24px; background:#fff; border:1.5px solid #0f172a; border-radius:12px; padding:20px;">
                        <h3 style="margin:0 0 8px 0; font-size:15px; font-weight:800; color:#0f172a; display:flex; align-items:center; gap:8px;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                            Import & Restore Database Backup (.sqlite / .db)
                        </h3>
                        <p style="font-size:12.5px; color:#475569; margin:0 0 16px 0;">
                            Upload your saved SQLite backup file here to safely merge new clients without creating duplicates or perform a full database restore.
                        </p>

                        <form id="restoreDbForm">
                            <div style="display:flex; flex-direction:column; gap:12px;">
                                <input type="file" id="restore_file_input" accept=".sqlite,.db,.sqlite3" class="ctrl-input" style="padding:10px 14px; background:#f8fafc; border:1px solid #cbd5e1;" required>
                                
                                <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:4px;">
                                    <button type="button" class="btn-black-solid" style="padding:10px 16px; font-size:13px; font-weight:800; display:flex; align-items:center; gap:6px;" onclick="executeDbImport('smart_merge')">
                                        🛡️ Smart Merge & De-duplicate (Safe Import)
                                    </button>
                                    <button type="button" class="btn-black-outline" style="padding:10px 16px; font-size:13px; font-weight:800; display:flex; align-items:center; gap:6px;" onclick="previewImportFile()">
                                        🔍 Preview & Analyze File
                                    </button>
                                    <button type="button" class="btn-black-outline" style="padding:10px 16px; font-size:13px; font-weight:800; display:flex; align-items:center; gap:6px;" onclick="executeDbImport('full_restore')">
                                        ⚡ Full Replace & Overwrite
                                    </button>
                                </div>
                                <div style="font-size:11.5px; color:#64748b; background:#f1f5f9; border-radius:6px; padding:8px 12px; margin-top:2px;">
                                    <strong>💡 Smart Merge Mode:</strong> Compares records against current database. If mobile, loan ID, or PAN already exists, it <strong>SKIPS</strong> inserting to prevent any duplicate rows!
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- RECENT SERVER BACKUPS HISTORY -->
                    <div style="margin-top: 24px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                            <h3 style="margin:0; font-size:14px; font-weight:800; color:#0f172a;">Stored Server Backups & Automatic Snapshots</h3>
                            <button type="button" class="btn-black-outline" style="font-size:11.5px; padding:4px 10px;" onclick="cleanupOldBackups()">
                                🧹 Cleanup Old Snapshots (Keep 15)
                            </button>
                        </div>
                        <div id="stored-backups-container">
                            <!-- Loaded via loadSystemHealth() -->
                        </div>
                    </div>

                    <!-- MASTER CRM DATABASE RESET DANGER ZONE (ANIMATED RED PULSE WARNER) -->
                    <div class="danger-zone-animated-card">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; border-bottom:1px solid #fecaca; padding-bottom:12px;">
                            <h3 style="margin:0; font-size:17px; font-weight:900; color:#dc2626; display:flex; align-items:center; gap:10px; font-family:'Outfit',sans-serif;">
                                <span class="siren-icon-flash" style="font-size:24px;">🚨</span>
                                Danger Zone: Master CRM Database Reset &amp; Clean
                            </h3>
                            <span style="background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; font-size:11px; font-weight:800; padding:4px 12px; border-radius:20px; text-transform:uppercase; letter-spacing:0.5px;">
                                Admin High Security Desk
                            </span>
                        </div>

                        <div style="background:#ffffff; border:1px solid #fca5a5; border-radius:12px; padding:20px; margin-bottom:20px; box-shadow:0 4px 12px rgba(220,38,38,0.06);">
                            <h4 style="margin:0 0 10px 0; color:#991b1b; font-size:14px; font-weight:800; display:flex; align-items:center; gap:8px;">
                                ⚠️ CRITICAL SECURITY WARNING &amp; STAFF PROTECTION GUARANTEE
                            </h4>
                            <p style="font-size:12.5px; color:#7f1d1d; margin:0 0 14px 0; line-height:1.6;">
                                This master tool clears all test data, dummy loan applications, leads, and document files to start the CRM with a fresh database.
                            </p>
                            
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                                <div style="background:#fef2f2; border:1px solid #fecaca; padding:14px; border-radius:10px;">
                                    <strong style="color:#991b1b; font-size:12px; text-transform:uppercase; display:block; margin-bottom:6px;">🗑️ Records To Be Wiped Clean:</strong>
                                    <ul style="margin:0 0 0 18px; font-size:12px; color:#991b1b; padding:0; line-height:1.5;">
                                        <li>All Loan Applications &amp; Co-Applicants</li>
                                        <li>All Sourced Leads &amp; Pre-Leads</li>
                                        <li>All Uploaded Customer Documents</li>
                                        <li>All Bank Logins &amp; Bank Queries</li>
                                        <li>All Field Staff Visits &amp; Followups</li>
                                        <li>All Reminders &amp; Payout Invoices</li>
                                    </ul>
                                </div>
                                <div style="background:#f0fdf4; border:1px solid #bbf7d0; padding:14px; border-radius:10px;">
                                    <strong style="color:#166534; font-size:12px; text-transform:uppercase; display:block; margin-bottom:6px;">🛡️ Accounts Preserved &amp; Safe (100% Intact):</strong>
                                    <ul style="margin:0 0 0 18px; font-size:12px; color:#166534; padding:0; line-height:1.5;">
                                        <li>✅ ALL Staff &amp; User Accounts (`users` table)</li>
                                        <li>✅ ALL RM &amp; Admin Passwords (`employees`)</li>
                                        <li>✅ Enterprise Company Profile &amp; Branding</li>
                                        <li>✅ Financial Rules &amp; Bank Payout Slabs</li>
                                        <li>✅ Database Schema &amp; Table Structure</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:12px; padding:20px;">
                            <h4 style="margin:0 0 10px 0; font-size:13.5px; font-weight:800; color:#0f172a;">
                                🔒 3-Step Fail-Safe Execution Process
                            </h4>
                            <ol style="margin:0 0 16px 20px; font-size:12.5px; color:#475569; padding:0; line-height:1.6;">
                                <li>Click the <strong>Initiate 3-Step Database Reset</strong> button below.</li>
                                <li>Enter your current <strong>Admin Password</strong> for security authorization.</li>
                                <li>Type exact confirmation text <strong>RESET CRM DATABASE</strong> in uppercase.</li>
                            </ol>

                            <button type="button" onclick="initiate3StepDbReset()" style="background:#dc2626; color:#ffffff; border:none; padding:12px 24px; border-radius:10px; font-size:13.5px; font-weight:800; cursor:pointer; display:inline-flex; align-items:center; gap:8px; box-shadow:0 4px 16px rgba(220,38,38,0.35); transition:all 0.2s;" onmouseover="this.style.background='#b91c1c'" onmouseout="this.style.background='#dc2626'">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 6h18"></path><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                💣 Initiate 3-Step Database Reset
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div> <!-- END SETTINGS CONTENT -->
    </div> <!-- END LAYOUT -->
</div>

<!-- EDIT USER MODAL -->
<div id="editUserModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.65); backdrop-filter:blur(6px); z-index:9999; justify-content:center; align-items:center;">
    <div style="background:#fff; padding:26px; border-radius:16px; width:520px; max-width:92%; max-height:92vh; overflow-y:auto; box-shadow:0 25px 50px -12px rgba(15,23,42,0.35);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; border-bottom:1px solid #e2e8f0; padding-bottom:12px;">
            <h3 style="margin:0; font-size:17px; font-weight:800; color:#0f172a;">Edit User Profile & Access</h3>
            <button type="button" onclick="document.getElementById('editUserModal').style.display='none'" style="background:none; border:none; font-size:22px; color:#64748b; cursor:pointer;">&times;</button>
        </div>
        <form onsubmit="submitEditUser(event)">
            <input type="hidden" id="edit_user_id">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                <div style="grid-column: 1 / -1;">
                    <label class="form-group-title">Full Name *</label>
                    <input type="text" id="edit_user_name" required class="ctrl-input">
                </div>
                <div>
                    <label class="form-group-title">Role</label>
                    <select id="edit_user_role" class="ctrl-input">
                        <option value="Staff">Staff / RM</option>
                        <option value="Agent">Agent</option>
                        <option value="CA">CA</option>
                        <option value="Builder">Builder</option>
                        <option value="Partner">Partner (DSA)</option>
                    </select>
                </div>
                <div>
                    <label class="form-group-title">Staff Type</label>
                    <select id="edit_user_staff_type" class="ctrl-input">
                        <option value="In-Office">In-Office</option>
                        <option value="Field">Field</option>
                    </select>
                </div>
                <div>
                    <label class="form-group-title">Monthly Target (₹)</label>
                    <input type="number" id="edit_user_target" class="ctrl-input">
                </div>
                <div>
                    <label class="form-group-title">Incentive Share (%)</label>
                    <input type="number" step="0.01" id="edit_user_commission" class="ctrl-input">
                </div>
                <div style="grid-column: 1 / -1;">
                    <label class="form-group-title">Reset Password (Leave blank to keep unchanged)</label>
                    <input type="password" id="edit_user_password" placeholder="Enter new password" class="ctrl-input">
                </div>
                
                <!-- GRANULAR PERMISSIONS MATRIX IN EDIT MODAL -->
                <div style="grid-column: 1 / -1; margin-top: 4px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 10px; padding: 14px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 8px;">
                        <div style="font-size: 13px; font-weight: 800; color: #0f172a;">
                            🛡️ Granular Operational Access Rights
                        </div>
                        <div style="display:flex; gap:4px; align-items:center;">
                            <button type="button" class="btn-black-outline" style="font-size:10px; padding:2px 6px; background:#fff;" onclick="applyPermPreset('edit', 'senior_rm')">Senior RM</button>
                            <button type="button" class="btn-black-outline" style="font-size:10px; padding:2px 6px; background:#fff;" onclick="applyPermPreset('edit', 'junior_rm')">Junior RM</button>
                            <button type="button" class="btn-black-outline" style="font-size:10px; padding:2px 6px; background:#fff;" onclick="applyPermPreset('edit', 'auditor')">Auditor</button>
                            <button type="button" class="btn-black-outline" style="font-size:10px; padding:2px 6px; background:#fff;" onclick="applyPermPreset('edit', 'view_only')">View Only</button>
                            <button type="button" class="btn-black-outline" style="font-size:10px; padding:2px 6px; background:#fff;" onclick="applyPermPreset('edit', 'all')">All</button>
                            <button type="button" class="btn-black-outline" style="font-size:10px; padding:2px 6px; background:#fff;" onclick="applyPermPreset('edit', 'none')">Clear</button>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-size: 11.5px;">
                        <label style="display:flex; align-items:center; gap:6px; cursor:pointer; grid-column: 1 / -1; background:#fff1f2; padding:5px 8px; border-radius:6px; border:1px solid #fecdd3;">
                            <input type="checkbox" id="edit_perm_view_only" value="1">
                            <span>👁️‍🗨️ <strong style="color:#e11d48;">View-Only Mode</strong> (Disable All Editing & Saving)</span>
                        </label>
                        <label style="display:flex; align-items:center; gap:6px; cursor:pointer;">
                            <input type="checkbox" id="edit_perm_view_all" value="1">
                            <span>👁️ View All Clients</span>
                        </label>
                        <label style="display:flex; align-items:center; gap:6px; cursor:pointer;">
                            <input type="checkbox" id="edit_perm_export" value="1">
                            <span>📊 Data Export Access</span>
                        </label>
                        <label style="display:flex; align-items:center; gap:6px; cursor:pointer;">
                            <input type="checkbox" id="edit_perm_sanctions" value="1">
                            <span>🏦 Sanction Overrides</span>
                        </label>
                        <label style="display:flex; align-items:center; gap:6px; cursor:pointer;">
                            <input type="checkbox" id="edit_perm_bank_logins" value="1">
                            <span>🛡️ Manage Bank Logins</span>
                        </label>
                        <label style="display:flex; align-items:center; gap:6px; cursor:pointer;">
                            <input type="checkbox" id="edit_perm_payouts" value="1">
                            <span>💰 Payout & Invoices</span>
                        </label>
                        <label style="display:flex; align-items:center; gap:6px; cursor:pointer;">
                            <input type="checkbox" id="edit_perm_sla" value="1">
                            <span>⚡ Resolve SLA Alerts</span>
                        </label>
                        <label style="display:flex; align-items:center; gap:6px; cursor:pointer;">
                            <input type="checkbox" id="edit_perm_delete" value="1">
                            <span>🗑️ Delete Records</span>
                        </label>
                        <label style="display:flex; align-items:center; gap:6px; cursor:pointer;">
                            <input type="checkbox" id="edit_perm_settings" value="1">
                            <span>⚙️ Settings Access</span>
                        </label>
                    </div>
                </div>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px; border-top:1px solid #f1f5f9; padding-top:14px;">
                <button type="button" class="btn-black-outline" onclick="document.getElementById('editUserModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn-black-solid">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- INLINE JS LOGIC -->
<script>
    let loanProductsArr = <?php echo json_encode($loan_products); ?>;
    let leadSourcesArr = <?php echo json_encode($lead_sources); ?>;
    let currentUsersData = [];

    // Dynamic URL Tab Routing & Switch Logic
    function switchSettingsTab(tabId, el, updateUrl = true) {
        document.querySelectorAll('.settings-menu-item').forEach(m => m.classList.remove('active'));
        if (el) {
            el.classList.add('active');
        } else {
            const menuEl = document.querySelector(`.settings-menu-item[onclick*="${tabId}"]`);
            if (menuEl) menuEl.classList.add('active');
        }

        document.querySelectorAll('.settings-panel').forEach(p => p.classList.remove('active'));
        const targetPanel = document.getElementById('tab-' + tabId);
        if (targetPanel) targetPanel.classList.add('active');

        localStorage.setItem('activeSettingsTab', tabId);

        if (updateUrl && history.pushState) {
            const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?tab=' + tabId;
            window.history.pushState({ tab: tabId }, '', newUrl);
        }
        
        if (tabId === 'slabs') loadCommissionSlabs();
        else if (tabId === 'docmatrix') loadDocComplianceMatrix();
        else if (tabId === 'backup') loadSystemHealth();
        else if (tabId === 'tracking') loadTrackingTokensList();
        else if (tabId === 'vault') initAndRenderVaultRoster();
    }
    
    document.addEventListener('DOMContentLoaded', () => {
        const urlParams = new URLSearchParams(window.location.search);
        let tabFromUrl = urlParams.get('tab') || window.location.hash.replace('#', '');
        let tabToLoad = tabFromUrl || localStorage.getItem('activeSettingsTab') || 'profile';

        let tabToActivate = document.querySelector(`.settings-menu-item[onclick*="${tabToLoad}"]`);
        if (tabToActivate) {
            switchSettingsTab(tabToLoad, tabToActivate, false);
        }

        // Initialize vault roster if vault is default or open
        if (tabToLoad === 'vault' || document.getElementById('tab-vault')?.classList.contains('active')) {
            initAndRenderVaultRoster();
        }
    });

    window.onpopstate = function(event) {
        const urlParams = new URLSearchParams(window.location.search);
        let tabFromUrl = urlParams.get('tab') || 'profile';
        let tabToActivate = document.querySelector(`.settings-menu-item[onclick*="${tabFromUrl}"]`);
        if (tabToActivate) {
            switchSettingsTab(tabFromUrl, tabToActivate, false);
        }
        if (tabFromUrl === 'vault') {
            initAndRenderVaultRoster();
        }
    };

    // On-Premise Vault JS Actions for Settings Tab
    async function openRootVaultFolder() {
        try {
            const res = await fetch('open_vault_folder.php?id=0&ajax=1');
            const data = await res.json();
            if (typeof showNotification === 'function') {
                showNotification("📂 Opened Root Vault in Windows Explorer: F:\\BFS\\Vault_Leads", 'success');
            } else {
                alert("📂 Opened Root Vault in Windows Explorer:\nF:\\BFS\\Vault_Leads");
            }
        } catch (e) {
            window.open('open_vault_folder.php?id=0', '_blank');
        }
    }

    async function openLeadFolder(appId, custName) {
        try {
            const res = await fetch(`open_vault_folder.php?id=${appId}&ajax=1`);
            const data = await res.json();
            if (data.success) {
                if (typeof showNotification === 'function') {
                    showNotification(`📂 Opened Vault in Windows Explorer: ${data.path}`, 'success');
                } else {
                    alert(`📂 Opened Vault in Windows Explorer:\n${data.path}`);
                }
            } else {
                alert(data.error || 'Failed to open folder');
            }
        } catch (e) {
            window.open(`open_vault_folder.php?id=${appId}`, '_blank');
        }
    }

    async function runLegacyMigration() {
        const btn = document.getElementById('btn-sync-legacy-settings');
        const origText = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '⚡ Syncing...';
        }
        if (typeof showNotification === 'function') showNotification('⚡ Syncing uploads to F:\\BFS...', 'info');

        try {
            const res = await fetch('vault_sync_bridge.php?action=pull_to_vault');
            const data = await res.json();
            if (data.success) {
                if (typeof showNotification === 'function') {
                    showNotification(data.message || 'Sync completed successfully!', 'success');
                } else {
                    alert(data.message || 'Sync completed!');
                }
                setTimeout(() => location.reload(), 1200);
            } else {
                alert(data.error || 'Sync failed');
            }
        } catch (e) {
            alert('Server error occurred during sync');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = origText;
            }
        }
    }

    function toggleDriveRelocationDrawer() {
        const drawer = document.getElementById('drive-relocation-drawer');
        const btn = document.getElementById('btn-toggle-drawer');
        if (!drawer) return;
        if (drawer.style.display === 'none' || drawer.style.display === '') {
            drawer.style.display = 'block';
            if (btn) btn.innerHTML = '⚙️ Change Drive ▴';
        } else {
            drawer.style.display = 'none';
            if (btn) btn.innerHTML = '⚙️ Change Drive ▾';
        }
    }

    async function syncWithVpsBridge() {
        const btn = document.getElementById('btn-sync-vps-action');
        const origText = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '⚡ Syncing...';
        }
        if (typeof showNotification === 'function') showNotification('⚡ Syncing with Cloud VPS & local drive...', 'info');

        try {
            const res = await fetch('vault_sync_bridge.php?action=pull_to_vault');
            const data = await res.json();
            if (data.success) {
                if (typeof showNotification === 'function') {
                    showNotification(data.message || 'Sync completed successfully! 0 MB VPS Load.', 'success');
                } else {
                    alert(data.message || 'Sync completed successfully!');
                }
                setTimeout(() => location.reload(), 1200);
            } else {
                alert(data.error || 'Sync failed');
            }
        } catch (e) {
            alert('Server error occurred during sync');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = origText;
            }
        }
    }

    // --- ON-PREMISE VAULT EXECUTIVE PAGE MANAGEMENT & PAGINATION ENGINE ---
    window.vaultLeadFolders = <?= json_encode($vaultLeadFolders ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    let vaultCurrentPage = 1;
    let vaultPageSize = 10;
    let vaultFilteredList = [];
    let vaultProductInitialized = false;

    function escapeVaultHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function escapeVaultJs(str) {
        if (!str) return '';
        return String(str).replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '\\"');
    }

    function initVaultProductOptions() {
        if (vaultProductInitialized) return;
        const select = document.getElementById('vault-filter-product');
        if (!select) return;

        const currentVal = select.value;
        const products = new Set();
        (window.vaultLeadFolders || []).forEach(vf => {
            if (vf.loan_type && vf.loan_type.trim() !== '') {
                products.add(vf.loan_type.trim());
            }
        });

        // Retain first "All Loan Products" option
        select.innerHTML = '<option value="">All Loan Products</option>';
        Array.from(products).sort().forEach(prod => {
            const opt = document.createElement('option');
            opt.value = prod;
            opt.textContent = prod;
            if (prod === currentVal) opt.selected = true;
            select.appendChild(opt);
        });
        vaultProductInitialized = true;
    }

    function initAndRenderVaultRoster() {
        initVaultProductOptions();
        filterAndRenderVaultRoster(false);
    }

    function filterAndRenderVaultRoster(resetPage = false) {
        if (resetPage) vaultCurrentPage = 1;
        initVaultProductOptions();

        const searchInput = document.getElementById('vault-settings-filter');
        const query = (searchInput?.value || '').trim().toLowerCase();

        const productFilter = document.getElementById('vault-filter-product')?.value || '';
        const sortMode = document.getElementById('vault-sort-select')?.value || 'name_asc';

        const sourceList = window.vaultLeadFolders || [];

        // 1. Filter
        vaultFilteredList = sourceList.filter(item => {
            // Search text match
            if (query) {
                const matchName = (item.customer_name || '').toLowerCase().includes(query);
                const matchFolder = (item.folder_name || '').toLowerCase().includes(query);
                const matchLoanId = (item.loan_id || '').toLowerCase().includes(query);
                const matchLoanType = (item.loan_type || '').toLowerCase().includes(query);
                const matchMobile = (item.mobile || '').toLowerCase().includes(query);
                if (!matchName && !matchFolder && !matchLoanId && !matchLoanType && !matchMobile) {
                    return false;
                }
            }

            // Loan Product match
            if (productFilter && (item.loan_type || '') !== productFilter) {
                return false;
            }

            return true;
        });

        // 2. Sort
        vaultFilteredList.sort((a, b) => {
            switch (sortMode) {
                case 'name_desc':
                    return (b.customer_name || '').localeCompare(a.customer_name || '');
                case 'size_desc':
                    return (b.size_kb || 0) - (a.size_kb || 0);
                case 'size_asc':
                    return (a.size_kb || 0) - (b.size_kb || 0);
                case 'files_desc':
                    return (b.total_files || 0) - (a.total_files || 0);
                case 'files_asc':
                    return (a.total_files || 0) - (b.total_files || 0);
                case 'name_asc':
                default:
                    return (a.customer_name || '').localeCompare(b.customer_name || '');
            }
        });

        // 3. Update Badge
        const badge = document.getElementById('vault-lead-badge');
        if (badge) {
            if (vaultFilteredList.length === sourceList.length) {
                badge.innerText = `${sourceList.length} Leads`;
            } else {
                badge.innerText = `${vaultFilteredList.length} of ${sourceList.length} Leads`;
            }
        }

        // 4. Pagination Slicing
        const totalItems = vaultFilteredList.length;
        const totalPages = Math.max(1, Math.ceil(totalItems / vaultPageSize));
        if (vaultCurrentPage > totalPages) vaultCurrentPage = totalPages;

        const startIndex = totalItems === 0 ? 0 : (vaultCurrentPage - 1) * vaultPageSize;
        const endIndex = Math.min(startIndex + vaultPageSize, totalItems);
        const pageItems = vaultFilteredList.slice(startIndex, endIndex);

        // 5. Render Table Rows
        const tbody = document.getElementById('vault-roster-tbody');
        if (tbody) {
            if (totalItems === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="5" style="text-align:center; padding:32px 16px; color:#64748b;">
                            <div style="font-size:28px; margin-bottom:6px;">🔍</div>
                            <div style="font-weight:700; color:#334155; font-size:13.5px;">No customer vaults match your search criteria</div>
                            <div style="font-size:11.5px; color:#94a3b8; margin-top:3px;">Try clearing filters or searching with a different applicant name or loan ID.</div>
                        </td>
                    </tr>
                `;
            } else {
                let html = '';
                pageItems.forEach(vf => {
                    const custName = escapeVaultHtml(vf.customer_name || 'Applicant');
                    const folderName = escapeVaultHtml(vf.folder_name || '');
                    const loanId = escapeVaultHtml(vf.loan_id || 'REF-PENDING');
                    const loanType = escapeVaultHtml(vf.loan_type || 'Loan');
                    const mobile = escapeVaultHtml(vf.mobile || '');
                    const sizeFormatted = (vf.size_kb || 0) > 1024 ? (vf.size_kb / 1024).toFixed(1) + ' MB' : (vf.size_kb || 0) + ' KB';
                    const totalFiles = vf.total_files || 0;
                    const subCounts = vf.sub_counts || {};

                    const safeCustJs = escapeVaultJs(vf.customer_name || 'Applicant');

                    html += `
                        <tr class="vault-settings-row" style="border-bottom:1px solid #f1f5f9;">
                            <td style="padding:10px 12px;">
                                <div style="font-weight:700; color:#0f172a;">${custName}</div>
                                <div style="font-size:11px; color:#64748b; font-family:monospace;">${folderName}</div>
                                ${mobile && mobile !== '-' ? `<div style="font-size:10.5px; color:#94a3b8; margin-top:2px;">📱 ${mobile}</div>` : ''}
                            </td>
                            <td style="padding:10px 12px;">
                                <span style="font-weight:600; color:#0f172a;">${loanId}</span>
                                <div>
                                    <span style="display:inline-block; font-size:10.5px; background:#f1f5f9; color:#475569; padding:2px 7px; border-radius:4px; font-weight:700; margin-top:3px;">
                                        ${loanType}
                                    </span>
                                </div>
                            </td>
                            <td style="padding:10px 12px; text-align:center;">
                                <div style="display:flex; gap:4px; justify-content:center; flex-wrap:wrap;">
                                    <span style="background:#e0f2fe; color:#0369a1; font-weight:700; padding:2px 7px; border-radius:4px; font-size:10.5px;" title="KYC Documents">KYC: ${subCounts.kyc || 0}</span>
                                    <span style="background:#dcfce7; color:#15803d; font-weight:700; padding:2px 7px; border-radius:4px; font-size:10.5px;" title="Income &amp; Banking">Inc: ${subCounts.income || 0}</span>
                                    <span style="background:#fef3c7; color:#b45309; font-weight:700; padding:2px 7px; border-radius:4px; font-size:10.5px;" title="Property &amp; Legal">Prop: ${subCounts.property || 0}</span>
                                    <span style="background:#f1f5f9; color:#475569; font-weight:700; padding:2px 7px; border-radius:4px; font-size:10.5px;" title="Business &amp; Financials">Biz: ${subCounts.business || 0}</span>
                                    <span style="background:#f3e8ff; color:#7e22ce; font-weight:700; padding:2px 7px; border-radius:4px; font-size:10.5px;" title="Bank Dockets">Dkt: ${subCounts.dockets || 0}</span>
                                </div>
                            </td>
                            <td style="padding:10px 12px; text-align:right; font-weight:600; color:#475569;">
                                ${sizeFormatted}
                                <div style="font-size:10.5px; color:#94a3b8; font-weight:normal;">${totalFiles} files</div>
                            </td>
                            <td style="padding:10px 12px; text-align:right; white-space:nowrap;">
                                <div style="display:flex; gap:5px; justify-content:flex-end; align-items:center;">
                                    <button type="button" onclick="openLeadFolder(${vf.applicant_id || 0}, '${safeCustJs}')" class="btn-black-outline" style="font-size:11px; padding:5px 9px; cursor:pointer;" title="Open in Windows Explorer">
                                        📂 Explorer
                                    </button>
                                    ${(vf.applicant_id && vf.applicant_id > 0) ? `
                                    <a href="generate_zip.php?id=${vf.applicant_id}" class="btn-black-outline" style="font-size:11px; padding:5px 9px; text-decoration:none; color:#be185d; border-color:#fbcfe8;" title="Download All Files in 1 ZIP">
                                        📦 Bank ZIP
                                    </a>
                                    <a href="applicant_documents.php?id=${vf.applicant_id}" class="btn-black-solid" style="text-decoration:none; font-size:11px; padding:5px 10px;">
                                        📄 CRM Docs
                                    </a>
                                    ` : ''}
                                </div>
                            </td>
                        </tr>
                    `;
                });
                tbody.innerHTML = html;
            }
        }

        // 6. Render Pagination UI
        renderVaultPaginationUI(totalItems === 0 ? 0 : startIndex + 1, endIndex, totalItems);
    }

    function renderVaultPaginationUI(startItem, endItem, totalItems) {
        const infoEl = document.getElementById('vault-pagination-info');
        const controlsEl = document.getElementById('vault-pagination-controls');

        if (!infoEl || !controlsEl) return;

        if (totalItems === 0) {
            infoEl.innerHTML = 'Showing <b>0</b> to <b>0</b> of <b>0</b> entries';
            controlsEl.innerHTML = '';
            return;
        }

        infoEl.innerHTML = `Showing <b>${startItem}</b> to <b>${endItem}</b> of <b>${totalItems}</b> entries`;

        const totalPages = Math.ceil(totalItems / vaultPageSize);
        let buttonsHtml = '';

        // First & Previous buttons
        const prevDisabled = vaultCurrentPage === 1 ? 'disabled style="opacity:0.4; cursor:not-allowed;"' : '';
        buttonsHtml += `<button type="button" onclick="goToVaultPage(1)" ${prevDisabled} class="pg-btn" title="First Page">««</button>`;
        buttonsHtml += `<button type="button" onclick="goToVaultPage(${vaultCurrentPage - 1})" ${prevDisabled} class="pg-btn" title="Previous Page">‹</button>`;

        // Page number buttons (show up to 5 surrounding pages)
        let startPage = Math.max(1, vaultCurrentPage - 2);
        let endPage = Math.min(totalPages, startPage + 4);
        if (endPage - startPage < 4) {
            startPage = Math.max(1, endPage - 4);
        }

        for (let p = startPage; p <= endPage; p++) {
            const activeClass = p === vaultCurrentPage ? 'pg-btn-active' : '';
            buttonsHtml += `<button type="button" onclick="goToVaultPage(${p})" class="pg-btn ${activeClass}">${p}</button>`;
        }

        // Next & Last buttons
        const nextDisabled = vaultCurrentPage === totalPages ? 'disabled style="opacity:0.4; cursor:not-allowed;"' : '';
        buttonsHtml += `<button type="button" onclick="goToVaultPage(${vaultCurrentPage + 1})" ${nextDisabled} class="pg-btn" title="Next Page">›</button>`;
        buttonsHtml += `<button type="button" onclick="goToVaultPage(${totalPages})" ${nextDisabled} class="pg-btn" title="Last Page">»»</button>`;

        controlsEl.innerHTML = buttonsHtml;
    }

    function goToVaultPage(page) {
        const totalPages = Math.ceil(vaultFilteredList.length / vaultPageSize);
        if (page < 1 || page > totalPages) return;
        vaultCurrentPage = page;
        filterAndRenderVaultRoster(false);

        const tableCard = document.getElementById('vault-settings-table');
        if (tableCard) {
            tableCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    function changeVaultPageSize(newSize) {
        vaultPageSize = parseInt(newSize, 10) || 10;
        vaultCurrentPage = 1;
        filterAndRenderVaultRoster(false);
    }

    function resetVaultFilters() {
        const searchInput = document.getElementById('vault-settings-filter');
        if (searchInput) searchInput.value = '';
        const prodSelect = document.getElementById('vault-filter-product');
        if (prodSelect) prodSelect.value = '';
        const sortSelect = document.getElementById('vault-sort-select');
        if (sortSelect) sortSelect.value = 'name_asc';
        filterAndRenderVaultRoster(true);
    }

    function exportVaultRosterCSV() {
        if (!vaultFilteredList || vaultFilteredList.length === 0) {
            alert('No vault entries available to export.');
            return;
        }

        const headers = [
            'Applicant Name',
            'Loan ID',
            'Loan Product',
            'Mobile',
            'Folder Name',
            'KYC Files',
            'Income Files',
            'Property Files',
            'Business Files',
            'Docket Files',
            'Total Files',
            'Size (KB)',
            'Size (Formatted)',
            'Local Storage Path'
        ];

        const rows = vaultFilteredList.map(item => {
            const sizeFormatted = (item.size_kb || 0) > 1024 ? ((item.size_kb || 0) / 1024).toFixed(1) + ' MB' : (item.size_kb || 0) + ' KB';
            const sub = item.sub_counts || {};
            return [
                `"${(item.customer_name || '').replace(/"/g, '""')}"`,
                `"${(item.loan_id || '').replace(/"/g, '""')}"`,
                `"${(item.loan_type || '').replace(/"/g, '""')}"`,
                `"${(item.mobile || '').replace(/"/g, '""')}"`,
                `"${(item.folder_name || '').replace(/"/g, '""')}"`,
                sub.kyc || 0,
                sub.income || 0,
                sub.property || 0,
                sub.business || 0,
                sub.dockets || 0,
                item.total_files || 0,
                item.size_kb || 0,
                `"${sizeFormatted}"`,
                `"${(item.folder_path || '').replace(/"/g, '""')}"`
            ].join(',');
        });

        const csvContent = '\uFEFF' + [headers.join(','), ...rows].join('\r\n');
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        const dateStr = new Date().toISOString().slice(0, 10).replace(/-/g, '');
        link.setAttribute('href', url);
        link.setAttribute('download', `BFS_Vault_Leads_Roster_${dateStr}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);

        if (typeof showNotification === 'function') {
            showNotification(`📥 Exported ${vaultFilteredList.length} vault directories to CSV!`, 'success');
        }
    }

    async function testVpsBridgeConnection() {
        const btn = document.getElementById('btn-test-vps');
        const origText = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '📡 Testing...';
        }

        try {
            const res = await fetch('vault_sync_bridge.php?action=status');
            const data = await res.json();
            if (data.success) {
                if (typeof showNotification === 'function') {
                    showNotification('✅ VPS Storage Bridge is ONLINE & Handshake Verified! (0 MB VPS Disk Load)', 'success');
                } else {
                    alert('✅ VPS Storage Bridge is ONLINE & Handshake Verified! (0 MB VPS Disk Load)');
                }
            } else {
                alert('⚠️ Bridge Error: ' + (data.error || 'Connection check failed'));
            }
        } catch (e) {
            if (typeof showNotification === 'function') {
                showNotification('⚠️ Could not connect to VPS sync bridge.', 'error');
            } else {
                alert('⚠️ Could not connect to VPS sync bridge.');
            }
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = origText;
            }
        }
    }

    function selectVaultDrive(samplePath) {
        const input = document.getElementById('new_vault_path_input');
        if (input) input.value = samplePath;
    }

    async function submitVaultPathChange() {
        const input = document.getElementById('new_vault_path_input');
        const chkMigrate = document.getElementById('chk_migrate_vault_files');
        const btn = document.getElementById('btn-save-vault-path');
        const newPath = (input ? input.value : '').trim();

        if (!newPath) {
            alert('Please enter a target folder path (e.g. D:\\BFS\\Vault_Leads)');
            return;
        }

        const willMigrate = chkMigrate ? chkMigrate.checked : false;
        const confirmMsg = `Are you sure you want to change the document vault storage to:\n\n${newPath}\n\n` + 
            (willMigrate ? '✓ All existing customer files will be copied automatically to the new location.' : '⚠ Existing files will stay in the old folder; only new files will go to the new location.');

        if (!confirm(confirmMsg)) return;

        const origText = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '⏳ Validating & Switching...';
        }

        try {
            const formData = new FormData();
            formData.append('new_path', newPath);
            formData.append('migrate', willMigrate ? '1' : '0');

            const res = await fetch('api.php?api=switch_vault_storage_path', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (data.success) {
                if (typeof showNotification === 'function') {
                    showNotification(data.message || 'Storage switched successfully!', 'success');
                } else {
                    alert(data.message || 'Storage switched successfully!');
                }
                setTimeout(() => location.reload(), 1500);
            } else {
                alert('Storage Switch Error: ' + (data.error || 'Failed to switch path'));
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = origText;
                }
            }
        } catch (e) {
            alert('Server error occurred while validating storage path.');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = origText;
            }
        }
    }

    // Client Tracking Portal Roster JS Logic
    function loadTrackingTokensList(searchQuery = '') {
        const container = document.getElementById('tracking-tokens-container');
        if (!container) return;

        fetch('api.php?api=get_tracking_tokens&search=' + encodeURIComponent(searchQuery))
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                container.innerHTML = '<p style="color:#ef4444; font-size:13px; padding:20px;">Failed to load tokens.</p>';
                return;
            }

            if (data.base_url) {
                const bEl = document.getElementById('global-tracking-base-url');
                if (bEl) bEl.textContent = data.base_url + '{32_CHAR_HEX_TOKEN}';
            }

            const tokens = data.tokens || [];
            if (tokens.length === 0) {
                container.innerHTML = '<p style="color:#64748b; font-size:13px; padding:20px; text-align:center;">No loan applicants found matching search criteria.</p>';
                return;
            }

            let html = `<table class="audit-table">
                <thead>
                    <tr>
                        <th>Applicant Name & ID</th>
                        <th>Loan Type & Stage</th>
                        <th>Assigned RM</th>
                        <th>Token Expiry Status</th>
                        <th style="text-align:right;">Tracking Actions</th>
                    </tr>
                </thead>
                <tbody>`;

            tokens.forEach(t => {
                let badge = '';
                if (!t.tracking_token) {
                    badge = '<span style="background:#f1f5f9; color:#64748b; border:1px solid #cbd5e1; padding:3px 8px; border-radius:12px; font-size:11px; font-weight:800;">Not Generated</span>';
                } else if (t.is_expired) {
                    badge = '<span style="background:#f1f5f9; color:#0f172a; border:1px solid #0f172a; padding:3px 8px; border-radius:12px; font-size:11px; font-weight:800;">Expired</span>';
                } else {
                    badge = `<span style="background:#f1f5f9; color:#0f172a; border:1.5px solid #0f172a; padding:3px 8px; border-radius:12px; font-size:11px; font-weight:800;">Active (${t.days_left}d left)</span>`;
                }

                let docsDisplay = '';
                if (t.docs_uploaded_count > 0) {
                    docsDisplay = `<span style="background:#0f172a; color:#ffffff; padding:2px 7px; border-radius:10px; font-size:10.5px; font-weight:800; margin-left:6px;">📁 ${t.docs_uploaded_count} Uploaded</span>`;
                }

                let actions = '';
                if (!t.tracking_token) {
                    actions = `<button type="button" class="btn-black-solid" style="padding:5px 12px; font-size:11.5px; font-weight:700; border-radius:6px; display:inline-flex; align-items:center; gap:6px;" onclick="generateTrackingToken(${t.id})">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
                        Generate Link
                    </button>`;
                } else {
                    actions += `<button type="button" class="btn-black-solid" style="padding:5px 10px; font-size:11.5px; font-weight:700; border-radius:6px; display:inline-flex; align-items:center; gap:5px;" onclick="copyTrackingLink('${t.tracking_url}')" title="Copy Link to Clipboard">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                        Copy Link
                    </button> `;
                    if (t.mobile) {
                        actions += `<button type="button" class="btn-black-outline" style="padding:5px 9px; font-size:11.5px; font-weight:700; border-radius:6px; display:inline-flex; align-items:center; gap:4px;" onclick="shareTrackingWhatsApp('${t.mobile}', '${t.customer_name}', '${t.tracking_url}')" title="Send via WhatsApp">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
                            WhatsApp
                        </button> `;
                    }
                    actions += `<button type="button" class="btn-black-outline" style="padding:5px 9px; font-size:11.5px; font-weight:700; border-radius:6px; display:inline-flex; align-items:center; gap:4px;" onclick="openQRCodeModal('${t.customer_name}', '${t.tracking_url}')" title="View QR Code">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                        QR
                    </button> `;
                    actions += `<button type="button" class="btn-black-outline" style="padding:5px 9px; font-size:11.5px; font-weight:700; border-radius:6px; display:inline-flex; align-items:center; gap:4px;" onclick="openTrackingActionsMenu(${t.id}, '${t.customer_name}', '${t.mobile||''}', '${t.tracking_url}', 1)" title="More Options">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="1"></circle><circle cx="19" cy="12" r="1"></circle><circle cx="5" cy="12" r="1"></circle></svg>
                        Options
                    </button>`;
                }

                html += `<tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 12px 14px;">
                        <div style="display:flex; align-items:center; gap:6px;">
                            <strong style="color:#0f172a; font-size:13.5px; line-height:1.3;">${t.customer_name}</strong>
                            ${docsDisplay}
                        </div>
                        <div style="font-size:11.5px; color:#64748b; margin-top:4px; line-height:1.4;">
                            <span style="font-family:monospace; color:#0f172a; font-weight:700;">ID: ${t.loan_id}</span>
                            ${t.mobile ? ` &nbsp;•&nbsp; <span style="color:#475569; font-weight:600;">📱 ${t.mobile}</span>` : ''}
                        </div>
                    </td>
                    <td style="padding: 12px 14px;">
                        <span style="font-weight:700; color:#0f172a; font-size:12.5px; display:block;">${t.loan_type || 'Loan'}</span>
                        <div style="font-size:11.5px; color:#64748b; margin-top:3px;">Stage: <strong style="color:#0f172a;">${t.overall_status || 'KYC'}</strong></div>
                    </td>
                    <td style="padding: 12px 14px; font-size:12.5px; color:#334155; font-weight:600;">${t.rm_name}</td>
                    <td style="padding: 12px 14px;">${badge}</td>
                    <td style="padding: 12px 14px; text-align:right; white-space:nowrap;">
                        <div style="display:inline-flex; gap:6px; align-items:center; justify-content:flex-end;">
                            ${actions}
                        </div>
                    </td>
                </tr>`;
            });

            html += '</tbody></table>';
            container.innerHTML = html;
        });
    }

    function openTrackingActionsMenu(applicantId, name, mobile, trackingUrl, hasToken) {
        let actionsHtml = `
            <div style="display:flex; flex-direction:column; gap:8px; padding:6px 0; text-align:left;">
                ${hasToken ? `
                <button type="button" onclick="Swal.close(); copyTrackingLink('${trackingUrl}')" style="display:flex; align-items:center; gap:10px; padding:11px 14px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; font-size:13px; font-weight:700; color:#0f172a; cursor:pointer; text-align:left;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                    <span>Copy Tracking Link to Clipboard</span>
                </button>
                <button type="button" onclick="Swal.close(); openQRCodeModal('${name}', '${trackingUrl}')" style="display:flex; align-items:center; gap:10px; padding:11px 14px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; font-size:13px; font-weight:700; color:#0f172a; cursor:pointer; text-align:left;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                    <span>View & Download QR Code</span>
                </button>
                <button type="button" onclick="Swal.close(); window.open('${trackingUrl}', '_blank')" style="display:flex; align-items:center; gap:10px; padding:11px 14px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; font-size:13px; font-weight:700; color:#0f172a; cursor:pointer; text-align:left;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                    <span>Open Public Portal in New Tab</span>
                </button>
                ` : ''}

                <button type="button" onclick="Swal.close(); openRequestDocModal(${applicantId}, '${name}', '${mobile}', '${trackingUrl}')" style="display:flex; align-items:center; gap:10px; padding:11px 14px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; font-size:13px; font-weight:700; color:#0f172a; cursor:pointer; text-align:left;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="12" y1="18" x2="12" y2="12"></line><line x1="9" y1="15" x2="15" y2="15"></line></svg>
                    <span>Request Document from Client</span>
                </button>

                <button type="button" onclick="Swal.close(); openScheduleVisitModal(${applicantId}, '${name}')" style="display:flex; align-items:center; gap:10px; padding:11px 14px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; font-size:13px; font-weight:700; color:#0f172a; cursor:pointer; text-align:left;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    <span>Schedule FI Verification Visit</span>
                </button>

                <button type="button" onclick="Swal.close(); generateTrackingToken(${applicantId})" style="display:flex; align-items:center; gap:10px; padding:11px 14px; background:#fff1f2; border:1px solid #fecdd3; border-radius:8px; font-size:13px; font-weight:700; color:#e11d48; cursor:pointer; text-align:left; margin-top:4px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"></path></svg>
                    <span>${hasToken ? 'Regenerate / Reset 30-Day Token' : 'Generate New Tracking Token'}</span>
                </button>
            </div>
        `;

        Swal.fire({
            title: `<div style="font-size:15px; font-weight:800; color:#0f172a; display:flex; align-items:center; gap:8px;"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg> Tracking Actions — ${name}</div>`,
            html: actionsHtml,
            showConfirmButton: false,
            showCloseButton: true,
            width: '420px'
        });
    }

    function openScheduleVisitModal(applicantId, name) {
        Swal.fire({
            title: `<span style="font-size:16px; font-weight:800; color:#0f172a;">📅 Schedule FI Verification Visit</span>`,
            html: `
                <div style="text-align:left; padding:10px 4px; font-size:12.5px; color:#334155;">
                    <div style="font-weight:700; margin-bottom:8px; color:#0f172a;">Client: ${name}</div>
                    <label style="font-weight:700; display:block; margin-bottom:4px;">Verification Type *</label>
                    <select id="sched_v_type" class="ctrl-input" style="width:100%; box-sizing:border-box; margin-bottom:10px;">
                        <option value="Residence FI Visit">Residence FI Visit</option>
                        <option value="Office / Business FI Visit">Office / Business FI Visit</option>
                        <option value="Property Valuation Visit">Property Valuation Visit</option>
                        <option value="Legal Document Verification">Legal Document Verification</option>
                    </select>
                    <label style="font-weight:700; display:block; margin-bottom:4px;">Scheduled Date *</label>
                    <input type="date" id="sched_v_date" class="ctrl-input" style="width:100%; box-sizing:border-box; margin-bottom:10px;" value="${new Date().toISOString().split('T')[0]}">
                    <label style="font-weight:700; display:block; margin-bottom:4px;">Scheduled Time *</label>
                    <input type="text" id="sched_v_time" placeholder="e.g. 11:30 AM, 03:00 PM" class="ctrl-input" style="width:100%; box-sizing:border-box; margin-bottom:10px;" value="11:00 AM">
                    <label style="font-weight:700; display:block; margin-bottom:4px;">Field Officer Name</label>
                    <input type="text" id="sched_v_officer" placeholder="Officer Name" class="ctrl-input" style="width:100%; box-sizing:border-box; margin-bottom:10px;">
                    <label style="font-weight:700; display:block; margin-bottom:4px;">Officer Mobile</label>
                    <input type="text" id="sched_v_mobile" placeholder="Mobile Number" class="ctrl-input" style="width:100%; box-sizing:border-box;">
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Schedule Visit',
            confirmButtonColor: '#0f172a',
            preConfirm: () => {
                const vType = document.getElementById('sched_v_type').value;
                const vDate = document.getElementById('sched_v_date').value;
                const vTime = document.getElementById('sched_v_time').value.trim();
                const offName = document.getElementById('sched_v_officer').value.trim();
                const offMobile = document.getElementById('sched_v_mobile').value.trim();
                if (!vDate || !vTime) {
                    Swal.showValidationMessage('Date and Time are required');
                    return false;
                }
                return { vType, vDate, vTime, offName, offMobile };
            }
        }).then((res) => {
            if (res.isConfirmed && res.value) {
                const fd = new FormData();
                fd.append('applicant_id', applicantId);
                fd.append('verification_type', res.value.vType);
                fd.append('scheduled_date', res.value.vDate);
                fd.append('scheduled_time', res.value.vTime);
                fd.append('officer_name', res.value.offName);
                fd.append('officer_mobile', res.value.offMobile);

                fetch('api.php?api=schedule_verification', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({ icon: 'success', title: 'Visit Scheduled!', text: data.message, confirmButtonColor: '#0f172a' });
                        loadTrackingTokensList();
                    } else {
                        Swal.fire('Error', data.error || 'Failed to schedule visit', 'error');
                    }
                });
            }
        });
    }

    function copyTrackingLink(url) {
        navigator.clipboard.writeText(url).then(() => {
            Swal.fire({ icon: 'success', title: 'Link Copied!', text: 'Tracking URL copied to clipboard.', timer: 1500, showConfirmButton: false });
        }).catch(() => {
            prompt("Copy tracking URL:", url);
        });
    }

    function openRequestDocModal(applicantId, name, mobile, trackingUrl) {
        Swal.fire({
            title: `<span style="font-size:16px; font-weight:800; color:#0f172a;">📄 Request Document from ${name}</span>`,
            html: `
                <div style="text-align:left; padding:10px 4px;">
                    <label style="font-size:12px; font-weight:700; color:#0f172a; display:block; margin-bottom:4px;">Document Name *</label>
                    <input type="text" id="req_doc_name" placeholder="e.g., Latest 6-Month Bank Statement, Salary Slip, Form 16" class="ctrl-input" style="width:100%; box-sizing:border-box; margin-bottom:12px;">
                    <label style="font-size:12px; font-weight:700; color:#0f172a; display:block; margin-bottom:4px;">Instructions / Notes for Client (Optional)</label>
                    <input type="text" id="req_doc_notes" placeholder="e.g., Must be original bank PDF with password or stamp" class="ctrl-input" style="width:100%; box-sizing:border-box;">
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Submit Request & Send Alert',
            confirmButtonColor: '#0f172a',
            preConfirm: () => {
                const docName = document.getElementById('req_doc_name').value.trim();
                const notes = document.getElementById('req_doc_notes').value.trim();
                if (!docName) {
                    Swal.showValidationMessage('Please enter document name');
                    return false;
                }
                return { docName, notes };
            }
        }).then((res) => {
            if (res.isConfirmed && res.value) {
                const fd = new FormData();
                fd.append('applicant_id', applicantId);
                fd.append('document_name', res.value.docName);
                fd.append('notes', res.value.notes);

                fetch('api.php?api=request_client_document', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        let waMsg = `Hello ${name},\n\nPlease arrange and upload your required document (*${res.value.docName}*) on your BFS Circle live tracking portal here:\n${trackingUrl}\n\nThank you,\nBFS Circle Team`;
                        let cleanMobile = mobile.replace(/[^0-9]/g, '');
                        if (cleanMobile.length === 10) cleanMobile = '91' + cleanMobile;

                        Swal.fire({
                            icon: 'success',
                            title: 'Document Requested!',
                            html: `
                                <div style="font-size:13px; color:#334155;">
                                    Document <strong>${res.value.docName}</strong> is now flagged on ${name}'s portal screen!
                                    <div style="margin-top:14px;">
                                        <a href="https://wa.me/${cleanMobile}?text=${encodeURIComponent(waMsg)}" target="_blank" class="btn-black-solid" style="display:inline-block; padding:8px 16px; text-decoration:none; font-size:12px;">📲 Send WhatsApp Alert to Client</a>
                                    </div>
                                </div>
                            `,
                            confirmButtonColor: '#0f172a'
                        });
                        loadTrackingTokensList();
                    } else {
                        Swal.fire('Error', data.error, 'error');
                    }
                });
            }
        });
    }

    function openQRCodeModal(name, url) {
        const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=${encodeURIComponent(url)}`;
        Swal.fire({
            title: `<span style="font-size:16px; font-weight:800; color:#0f172a;">📱 Client Tracking QR Code</span>`,
            html: `
                <div style="text-align:center; padding:10px;">
                    <div style="font-size:13px; font-weight:700; color:#0f172a; margin-bottom:12px;">${name}</div>
                    <div style="background:#fff; border:2px solid #0f172a; border-radius:12px; padding:16px; display:inline-block;">
                        <img src="${qrUrl}" alt="QR Code" style="width:200px; height:200px; display:block;">
                    </div>
                    <div style="font-size:11px; color:#64748b; margin-top:12px;">Scan with smartphone camera to view live application status</div>
                    <div style="margin-top:14px;">
                        <a href="${qrUrl}" download="tracking_qr_${name}.png" target="_blank" class="btn-black-solid" style="display:inline-block; padding:6px 14px; font-size:12px; text-decoration:none;">📥 Download QR Code</a>
                    </div>
                </div>
            `,
            showConfirmButton: false,
            showCloseButton: true
        });
    }

    function shareTrackingWhatsApp(mobile, name, url) {
        let cleanMobile = mobile.replace(/[^0-9]/g, '');
        if (cleanMobile.length === 10) cleanMobile = '91' + cleanMobile;
        const msg = encodeURIComponent(`Hello ${name},\n\nYou can track your loan application status live, check document requirements, and upload files directly from your phone here:\n${url}\n\nBFS Circle Team`);
        window.open(`https://wa.me/${cleanMobile}?text=${msg}`, '_blank');
    }

    function generateTrackingToken(applicantId) {
        const fd = new FormData();
        fd.append('applicant_id', applicantId);
        fetch('api.php?api=generate_tracking_token', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                Swal.fire({ icon: 'success', title: 'Token Generated!', text: 'New 30-day tracking URL created successfully.', confirmButtonColor: '#0f172a' });
                loadTrackingTokensList(document.getElementById('tracking-search-input')?.value || '');
            } else {
                Swal.fire('Error', data.error || 'Failed to generate token', 'error');
            }
        });
    }

    function bulkGenerateTokens() {
        Swal.fire({
            title: 'Auto-Generate Tokens?',
            text: 'This will generate 30-day tracking tokens for all active applicants missing a token.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Generate All',
            confirmButtonColor: '#0f172a'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('api.php?api=bulk_generate_tracking_tokens', { method: 'POST' })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({ icon: 'success', title: 'Tokens Created!', text: data.message, confirmButtonColor: '#0f172a' });
                        loadTrackingTokensList();
                    } else {
                        Swal.fire('Error', data.error, 'error');
                    }
                });
            }
        });
    }

    // Save CRM Rules
    function saveCrmRules(e) {
        e.preventDefault();
        const fd = new FormData(e.target);
        fd.append('is_partial_config', '1');
        fetch('api.php?api=save_settings', { method: 'POST', body: fd })
        .then(r => r.json()).then(data => {
            if (data.success) Swal.fire({ icon:'success', title:'Rules Saved', text: data.message, confirmButtonColor: '#0f172a' });
            else Swal.fire('Error', data.error, 'error');
        });
    }

    // Save Company Settings Form Handler
    function saveCompanySettings(e) {
        e.preventDefault();
        const formData = new FormData(document.getElementById('settings-form'));
        fetch('api.php?api=save_settings', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                Swal.fire({ icon: 'success', title: 'Settings Saved', text: data.message, confirmButtonColor: '#0f172a' }).then(() => location.reload());
            } else {
                Swal.fire('Error', data.error || 'Failed to save settings.', 'error');
            }
        });
    }

    // Instant Image Preview for Branding File Uploads
    function previewBrandingFile(input, imgId, placeholderId) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.getElementById(imgId);
                const placeholder = document.getElementById(placeholderId);
                if (img) {
                    img.src = e.target.result;
                    img.style.display = 'block';
                }
                if (placeholder) {
                    placeholder.style.display = 'none';
                }
                if (imgId === 'preview-logo-dark') {
                    const cardLogo = document.getElementById('prev-card-logo');
                    const cardTxt = document.getElementById('prev-card-logo-txt');
                    if (cardLogo) { cardLogo.src = e.target.result; cardLogo.style.display = 'block'; }
                    if (cardTxt) { cardTxt.style.display = 'none'; }
                }
                if (imgId === 'preview-stamp') {
                    const cardStamp = document.getElementById('prev-card-stamp');
                    const cardPlaceholder = document.getElementById('prev-card-stamp-placeholder');
                    if (cardStamp) { cardStamp.src = e.target.result; cardStamp.style.display = 'block'; }
                    if (cardPlaceholder) { cardPlaceholder.style.display = 'none'; }
                }
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    // Live Sync Invoice & Letterhead Preview Card
    function updateLivePreview() {
        const getVal = (id, fallback = '') => {
            const el = document.getElementById(id);
            return el ? (el.value.trim() || fallback) : fallback;
        };

        const companyName = getVal('input_company_name', 'BFS Financial Services Ltd');
        const tagline = getVal('input_company_tagline', "India's Premier DSA Network");
        const gstin = getVal('input_gstin', '07AAAAA0000A1Z5');
        const msme = getVal('input_msme_reg_no');
        const rbi = getVal('input_rbi_dsa_reg_no');
        const cin = getVal('input_cin_number');
        const currency = getVal('input_base_currency', 'INR (₹)');
        const watermark = getVal('input_watermark_text', 'CONFIDENTIAL & PROPRIETARY');
        const add1 = getVal('input_address_line1');
        const add2 = getVal('input_address_line2');
        const city = getVal('input_city');
        const state = getVal('input_state');
        const pin = getVal('input_pincode');
        const bank = getVal('input_bank_name', 'HDFC Bank');
        const acc = getVal('input_account_number', '50200012345678');
        const ifsc = getVal('input_ifsc_code', 'HDFC0001234');
        const branch = getVal('input_bank_branch', 'Connaught Place');
        const footerNote = getVal('input_invoice_footer_note', 'Please make all NEFT/RTGS payments payable to BFS Financial Services Ltd. TDS u/s 194H applicable.');
        const primaryColor = getVal('input_primary_color', '#0f172a');
        const enableQr = document.getElementById('input_enable_dynamic_qr')?.checked;

        const elCompName = document.getElementById('prev-card-company-name');
        if (elCompName) elCompName.innerText = companyName;

        const elLogoTxt = document.getElementById('prev-card-logo-txt');
        if (elLogoTxt) elLogoTxt.innerText = companyName.toUpperCase();

        const elTagline = document.getElementById('prev-card-tagline');
        if (elTagline) elTagline.innerText = tagline;

        const elAddress = document.getElementById('prev-card-address');
        if (elAddress) elAddress.innerText = [add1, add2, city, state, pin].filter(Boolean).join(', ');

        const elCin = document.getElementById('prev-card-cin');
        if (elCin) elCin.innerText = cin ? 'CIN: ' + cin : '';

        const elGstin = document.getElementById('prev-card-gstin');
        if (elGstin) elGstin.innerText = 'GSTIN: ' + gstin;

        const elLic = document.getElementById('prev-card-licenses');
        if (elLic) {
            let licArr = [];
            if (msme) licArr.push('MSME: ' + msme);
            if (rbi) licArr.push('RBI DSA: ' + rbi);
            elLic.innerText = licArr.join(' | ');
        }

        const elCurrency = document.getElementById('prev-card-currency');
        if (elCurrency) elCurrency.innerText = currency;

        const elSampleAmt = document.getElementById('prev-card-sample-amt');
        if (elSampleAmt) {
            const curSymbol = currency.includes('$') ? '$' : (currency.includes('€') ? '€' : (currency.includes('£') ? '£' : '₹'));
            elSampleAmt.innerText = curSymbol + ' 1,50,000.00';
        }

        const elWatermark = document.getElementById('prev-card-watermark');
        if (elWatermark) elWatermark.innerText = watermark;

        const elQrBox = document.getElementById('prev-card-qr-box');
        if (elQrBox) elQrBox.style.display = enableQr ? 'block' : 'none';

        // ISO Badges
        const isoBoxes = document.querySelectorAll('input[name="iso_certifications[]"]:checked');
        const elIsoBadges = document.getElementById('prev-card-iso-badges');
        if (elIsoBadges) {
            let badgesHtml = '';
            isoBoxes.forEach(box => {
                badgesHtml += `<span>🛡️ ${box.value}</span> `;
            });
            elIsoBadges.innerHTML = badgesHtml || '<span>🛡️ ISO Certified</span>';
        }

        const elBank = document.getElementById('prev-card-bank-name');
        if (elBank) elBank.innerText = bank;
        const elAcc = document.getElementById('prev-card-account-number');
        if (elAcc) elAcc.innerText = acc;
        const elIfsc = document.getElementById('prev-card-ifsc');
        if (elIfsc) elIfsc.innerText = ifsc;
        const elBranch = document.getElementById('prev-card-bank-branch');
        if (elBranch) elBranch.innerText = branch;

        const elFooterNote = document.getElementById('prev-card-footer-note');
        if (elFooterNote) elFooterNote.innerText = footerNote;

        const elHdrBorder = document.getElementById('prev-hdr-border');
        if (elHdrBorder) elHdrBorder.style.borderBottomColor = primaryColor;

        const elBadge = document.getElementById('prev-card-badge');
        if (elBadge) elBadge.style.background = primaryColor;
    }

    // Multi-Branch Manager Desk
    let branchesArr = [];
    try {
        branchesArr = JSON.parse(document.getElementById('input_branches_json')?.value || '[]');
        if (!Array.isArray(branchesArr)) branchesArr = [];
    } catch(e) { branchesArr = []; }

    function renderBranchesList() {
        const container = document.getElementById('branches-list-container');
        if (!container) return;
        if (branchesArr.length === 0) {
            container.innerHTML = '<p style="color:#64748b; font-size:12.5px; margin:0;">No regional office branches added yet. Click <strong>+ Add Regional Branch</strong> above to add secondary branch GSTINs.</p>';
            return;
        }
        let html = `<table class="audit-table"><thead><tr><th>Branch Details</th><th>State GSTIN</th><th>Branch Manager</th><th style="text-align:right;">Actions</th></tr></thead><tbody>`;
        branchesArr.forEach((b, idx) => {
            html += `<tr>
                <td>
                    <strong style="color:#0f172a; font-weight:800;">${b.name} (${b.code})</strong>
                    <div style="font-size:11.5px; color:#64748b;">${b.address || 'Address N/A'}</div>
                </td>
                <td style="font-weight:700; color:#0f172a; font-family:monospace;">${b.gstin || 'N/A'}</td>
                <td style="font-size:12px; color:#334155;">${b.manager || 'N/A'} (${b.contact || 'N/A'})</td>
                <td style="text-align:right;">
                    <button type="button" class="btn-black-outline" style="padding:3px 8px; font-size:11px;" onclick="deleteBranchItem(${idx})">Delete</button>
                </td>
            </tr>`;
        });
        html += `</tbody></table>`;
        container.innerHTML = html;
        const hiddenInput = document.getElementById('input_branches_json');
        if (hiddenInput) hiddenInput.value = JSON.stringify(branchesArr);
    }

    function openAddBranchModal() {
        Swal.fire({
            title: 'Add Regional Branch / State Office',
            html: `
                <div style="text-align:left; display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                    <div>
                        <label style="font-size:11px; font-weight:700; color:#334155;">Branch Name *</label>
                        <input type="text" id="m_b_name" placeholder="e.g. Mumbai Regional Hub" class="ctrl-input">
                    </div>
                    <div>
                        <label style="font-size:11px; font-weight:700; color:#334155;">Branch Code *</label>
                        <input type="text" id="m_b_code" placeholder="e.g. MUM-01" class="ctrl-input" style="font-weight:700;">
                    </div>
                    <div>
                        <label style="font-size:11px; font-weight:700; color:#334155;">Branch State GSTIN</label>
                        <input type="text" id="m_b_gstin" placeholder="27AAAAA0000A1Z5" class="ctrl-input" style="font-weight:700;">
                    </div>
                    <div>
                        <label style="font-size:11px; font-weight:700; color:#334155;">Branch Manager Name</label>
                        <input type="text" id="m_b_manager" placeholder="e.g. Vikram Mehta" class="ctrl-input">
                    </div>
                    <div style="grid-column: 1 / -1;">
                        <label style="font-size:11px; font-weight:700; color:#334155;">Branch Contact Number</label>
                        <input type="text" id="m_b_contact" placeholder="+91 98765 12345" class="ctrl-input">
                    </div>
                    <div style="grid-column: 1 / -1;">
                        <label style="font-size:11px; font-weight:700; color:#334155;">Full Branch Address</label>
                        <input type="text" id="m_b_address" placeholder="Bandra Kurla Complex, Mumbai, Maharashtra 400051" class="ctrl-input">
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Add Branch',
            confirmButtonColor: '#0f172a',
            preConfirm: () => {
                const name = document.getElementById('m_b_name').value.trim();
                const code = document.getElementById('m_b_code').value.trim();
                const gstin = document.getElementById('m_b_gstin').value.trim();
                const manager = document.getElementById('m_b_manager').value.trim();
                const contact = document.getElementById('m_b_contact').value.trim();
                const address = document.getElementById('m_b_address').value.trim();
                if (!name || !code) {
                    Swal.showValidationMessage('Branch Name and Code are required!');
                    return false;
                }
                return { name, code, gstin, manager, contact, address };
            }
        }).then((res) => {
            if (res.isConfirmed && res.value) {
                branchesArr.push(res.value);
                renderBranchesList();
            }
        });
    }

    function deleteBranchItem(idx) {
        if (!confirm('Delete this regional branch?')) return;
        branchesArr.splice(idx, 1);
        renderBranchesList();
    }

    // Trigger Test SMTP Email
    async function triggerTestSmtpEmail() {
        const { value: testEmail } = await Swal.fire({
            title: 'Send Test SMTP Email',
            text: 'Enter recipient email address to test your SMTP configuration:',
            input: 'email',
            inputPlaceholder: 'Enter email address...',
            showCancelButton: true,
            confirmButtonText: '⚡ Dispatch Test Email',
            confirmButtonColor: '#0f172a'
        });

        if (testEmail) {
            Swal.fire({ title: 'Dispatching Test Email...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            let fd = new FormData();
            fd.append('test_email', testEmail);
            fetch('api.php?api=test_smtp_email', { method: 'POST', body: fd })
            .then(r => r.json()).then(d => {
                if (d.success) Swal.fire({ icon: 'success', title: 'SMTP Test Successful!', text: d.message, confirmButtonColor: '#0f172a' });
                else Swal.fire('SMTP Dispatch Failed', d.error, 'error');
            });
        }
    }

    // Load Commission Slabs
    function loadCommissionSlabs() {
        fetch('api.php?api=get_commission_slabs')
        .then(r => r.json()).then(data => {
            const container = document.getElementById('commission-slabs-container');
            if (!container) return;
            if (!data.success || !data.slabs || data.slabs.length === 0) {
                container.innerHTML = '<p style="color:#64748b; font-size:13px;">No volume slabs configured.</p>';
                return;
            }
            let html = `<table class="audit-table"><thead><tr><th>Tier Name</th><th>Disbursed Range (₹)</th><th>Commission Rate (%)</th><th style="text-align:right;">Actions</th></tr></thead><tbody>`;
            data.slabs.forEach(s => {
                let rangeStr = s.max_disbursed > 0 ? `₹${Number(s.min_disbursed).toLocaleString('en-IN')} — ₹${Number(s.max_disbursed).toLocaleString('en-IN')}` : `₹${Number(s.min_disbursed).toLocaleString('en-IN')}+ (Unlimited)`;
                html += `<tr>
                    <td style="font-weight:800; color:#0f172a;">${s.tier_name}</td>
                    <td style="font-weight:700; color:#334155;">${rangeStr}</td>
                    <td style="font-weight:900; color:#059669; font-size:14px;">${s.commission_pct}%</td>
                    <td style="text-align:right;">
                        <button type="button" class="btn-black-outline" style="padding:4px 10px; font-size:11px;" onclick="deleteCommissionSlab(${s.id})">Delete</button>
                    </td>
                </tr>`;
            });
            html += `</tbody></table>`;
            container.innerHTML = html;
        });
    }

    function submitNewCommissionSlab(e) {
        e.preventDefault();
        let fd = new FormData();
        fd.append('tier_name', document.getElementById('slab_tier_name').value);
        fd.append('min_disbursed', document.getElementById('slab_min_amt').value || 0);
        fd.append('max_disbursed', document.getElementById('slab_max_amt').value || 0);
        fd.append('commission_pct', document.getElementById('slab_pct').value);

        fetch('api.php?api=save_commission_slab', { method: 'POST', body: fd })
        .then(r => r.json()).then(d => {
            if (d.success) {
                Swal.fire({ icon:'success', title:'Slab Added', text: d.message, confirmButtonColor:'#0f172a' });
                e.target.reset();
                loadCommissionSlabs();
            } else {
                Swal.fire('Error', d.error, 'error');
            }
        });
    }

    function deleteCommissionSlab(id) {
        if (!confirm('Delete this volume slab?')) return;
        let fd = new FormData(); fd.append('id', id);
        fetch('api.php?api=delete_commission_slab', { method: 'POST', body: fd })
        .then(r => r.json()).then(d => { loadCommissionSlabs(); });
    }

    // Document Compliance Matrix Logic
    function loadDocComplianceMatrix() {
        fetch('api.php?api=get_doc_compliance_matrix')
        .then(r => r.json()).then(data => {
            const container = document.getElementById('doc-compliance-matrix-container');
            if (!container) return;
            if (!data.success || !data.matrix || data.matrix.length === 0) {
                container.innerHTML = '<p style="color:#64748b; font-size:13px;">No document compliance rules configured.</p>';
                return;
            }
            let html = `<table class="audit-table"><thead><tr><th>Loan Product</th><th>Employment</th><th>Category</th><th>Document Name</th><th style="text-align:right;">Action</th></tr></thead><tbody>`;
            data.matrix.forEach(d => {
                html += `<tr>
                    <td style="font-weight:800; color:#0f172a;">${d.loan_type}</td>
                    <td><span style="background:#f1f5f9; padding:2px 8px; border-radius:4px; font-size:11.5px; font-weight:700;">${d.employment_type}</span></td>
                    <td style="font-size:12.5px; color:#64748b;">${d.doc_category}</td>
                    <td style="font-weight:700; color:#0f172a;">${d.doc_name} ${d.is_mandatory ? '<span style="color:#dc2626;">*</span>' : ''}</td>
                    <td style="text-align:right;"><button type="button" class="btn-black-outline" style="padding:4px 8px; font-size:11px;" onclick="deleteDocComplianceItem(${d.id})">Delete</button></td>
                </tr>`;
            });
            html += `</tbody></table>`;
            container.innerHTML = html;
        });
    }

    function submitNewDocComplianceItem(e) {
        e.preventDefault();
        let fd = new FormData();
        fd.append('loan_type', document.getElementById('doc_rule_loan_type').value);
        fd.append('employment_type', document.getElementById('doc_rule_emp_type').value);
        fd.append('doc_category', document.getElementById('doc_rule_category').value);
        fd.append('doc_name', document.getElementById('doc_rule_name').value);
        fd.append('is_mandatory', '1');

        fetch('api.php?api=save_doc_compliance_item', { method: 'POST', body: fd })
        .then(r => r.json()).then(d => {
            if (d.success) {
                Swal.fire({ icon:'success', title:'Rule Added', text: d.message, confirmButtonColor:'#0f172a' });
                document.getElementById('doc_rule_name').value = '';
                loadDocComplianceMatrix();
            } else {
                Swal.fire('Error', d.error, 'error');
            }
        });
    }

    function deleteDocComplianceItem(id) {
        if (!confirm('Delete this document rule?')) return;
        let fd = new FormData(); fd.append('id', id);
        fetch('api.php?api=delete_doc_compliance_item', { method: 'POST', body: fd })
        .then(r => r.json()).then(d => { loadDocComplianceMatrix(); });
    }

    // System Health & Database Backup Desk Logic
    function loadSystemHealth() {
        fetch('api.php?api=get_system_health')
        .then(r => r.json()).then(data => {
            if (data.success && data.health) {
                const dbSizeEl = document.getElementById('health-db-size');
                if (dbSizeEl) dbSizeEl.innerText = data.health.db_size_mb;
                
                const uploadsSizeEl = document.getElementById('health-uploads-size');
                if (uploadsSizeEl) uploadsSizeEl.innerText = data.health.uploads_size_mb + ' MB (' + data.health.uploads_count + ' files)';
                
                const applicantsEl = document.getElementById('health-applicants');
                if (applicantsEl) applicantsEl.innerText = data.health.total_applicants.toLocaleString();
                
                const logsEl = document.getElementById('health-logs');
                if (logsEl) logsEl.innerText = data.health.total_logs.toLocaleString();

                const bContainer = document.getElementById('stored-backups-container');
                if (bContainer) {
                    const backups = data.health.backups || [];
                    if (backups.length === 0) {
                        bContainer.innerHTML = '<p style="color:#64748b; font-size:12.5px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:14px; text-align:center;">No saved backup files found on server yet. Click Download Backup above to generate your first backup snapshot.</p>';
                    } else {
                        let bHtml = `<table class="audit-table">
                            <thead>
                                <tr>
                                    <th>Backup Filename</th>
                                    <th>File Size</th>
                                    <th>Created Date</th>
                                    <th style="text-align:right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>`;
                        backups.forEach(b => {
                            bHtml += `<tr>
                                <td><strong style="color:#0f172a; font-size:12.5px; font-family:monospace;">${b.filename}</strong></td>
                                <td><span style="font-size:12px; font-weight:700; color:#334155;">${b.size}</span></td>
                                <td style="font-size:12px; color:#64748b;">${b.date}</td>
                                <td style="text-align:right;">
                                    <a href="api.php?api=download_stored_backup&filename=${encodeURIComponent(b.filename)}" class="btn-black-outline" style="padding:4px 10px; font-size:11px; text-decoration:none; display:inline-block; margin-right:4px;">📥 Download</a>
                                    <button type="button" class="btn-black-solid" style="padding:4px 10px; font-size:11px;" onclick="restoreStoredBackup('${b.filename}')">🔄 Restore</button>
                                </td>
                            </tr>`;
                        });
                        bHtml += '</tbody></table>';
                        bContainer.innerHTML = bHtml;
                    }
                }
            }
        });
    }

    function executeDbImport(mode = 'smart_merge') {
        const fileInput = document.getElementById('restore_file_input');
        if (!fileInput || !fileInput.files || !fileInput.files[0]) {
            Swal.fire({ icon: 'warning', title: 'Select Backup File', text: 'Please select a valid .sqlite or .db file to import.', confirmButtonColor: '#0f172a' });
            return;
        }

        const file = fileInput.files[0];
        const isSmart = (mode === 'smart_merge');

        const titleText = isSmart ? '🛡️ Smart Merge & De-duplicate Data?' : '⚠️ Full Replace & Overwrite Database?';
        const bodyHtml = isSmart 
            ? `<div style="text-align:left; font-size:13px; color:#334155;">
                <p style="margin:0 0 10px 0;">Importing new records from <strong>${file.name}</strong> (${(file.size / (1024*1024)).toFixed(2)} MB).</p>
                <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:10px; font-size:12px; color:#166534;">
                    <strong>✓ Safe De-duplication:</strong> Any applicants with existing Mobile Number, PAN, or Loan ID will be <strong>SKIPPED</strong>. No duplicate rows will be created!
                </div>
               </div>`
            : `<div style="text-align:left; font-size:13px; color:#334155;">
                <p style="margin:0 0 10px 0;">Overwriting entire database from <strong>${file.name}</strong> (${(file.size / (1024*1024)).toFixed(2)} MB).</p>
                <div style="background:#fff1f2; border:1px solid #fecdd3; border-radius:8px; padding:10px; font-size:12px; color:#991b1b;">
                    <strong>Warning:</strong> All active CRM database tables will be replaced by the backup file. An emergency snapshot of current data will be saved automatically.
                </div>
               </div>`;

        Swal.fire({
            title: titleText,
            html: bodyHtml,
            icon: isSmart ? 'info' : 'warning',
            showCancelButton: true,
            confirmButtonText: isSmart ? 'Yes, Merge & De-duplicate' : 'Yes, Replace Entire Database',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#0f172a'
        }).then((res) => {
            if (res.isConfirmed) {
                Swal.fire({
                    title: isSmart ? 'Merging & De-duplicating...' : 'Restoring Database...',
                    text: 'Please wait while system processes your database backup file.',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });

                const fd = new FormData();
                fd.append('backup_file', file);

                const targetApi = isSmart ? 'api.php?api=merge_db_backup' : 'api.php?api=restore_db_backup';

                fetch(targetApi, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: isSmart ? '🎉 Smart Merge Complete!' : '🎉 Database Restored!',
                            text: data.message,
                            confirmButtonColor: '#0f172a'
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Import Failed', text: data.error || 'Server error', confirmButtonColor: '#0f172a' });
                    }
                })
                .catch(err => {
                    Swal.fire({ icon: 'error', title: 'Connection Error', text: 'Error connecting to server. Please try again.', confirmButtonColor: '#0f172a' });
                });
            }
        });
    }

    function restoreStoredBackup(filename) {
        Swal.fire({
            title: '⚠️ Restore Stored Backup?',
            html: `Are you sure you want to restore the CRM database from server backup <strong>${filename}</strong>?<br><br><span style="font-size:12px; color:#991b1b;">An emergency safety snapshot of current data will be saved automatically.</span>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Restore Now',
            confirmButtonColor: '#0f172a'
        }).then((res) => {
            if (res.isConfirmed) {
                Swal.fire({ title: 'Restoring...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                const fd = new FormData();
                fd.append('filename', filename);
                fetch('api.php?api=restore_local_backup_file', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({ icon: 'success', title: 'Restored!', text: data.message, confirmButtonColor: '#0f172a' }).then(() => location.reload());
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: data.error, confirmButtonColor: '#0f172a' });
                    }
                });
            }
        });
    }

    function previewImportFile() {
        const fileInput = document.getElementById('restore_file_input');
        if (!fileInput || !fileInput.files || !fileInput.files[0]) {
            Swal.fire({ icon: 'warning', title: 'Select File', text: 'Please choose a .sqlite or .db file to analyze.', confirmButtonColor: '#0f172a' });
            return;
        }

        const file = fileInput.files[0];
        Swal.fire({ title: 'Analyzing Backup File...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        const fd = new FormData();
        fd.append('backup_file', file);

        fetch('api.php?api=preview_import_file', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success && data.analysis) {
                const a = data.analysis;
                Swal.fire({
                    title: `🔍 Import Dry-Run Inspector`,
                    html: `
                        <div style="text-align:left; font-size:13px; color:#334155; padding:4px;">
                            <div style="font-weight:700; color:#0f172a; margin-bottom:12px;">File: <code style="background:#f1f5f9; padding:2px 6px; border-radius:4px;">${data.filename}</code></div>
                            
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:14px;">
                                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px;">
                                    <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase;">Applicants in File</div>
                                    <div style="font-size:20px; font-weight:800; color:#0f172a; margin-top:2px;">${a.total_applicants}</div>
                                    <div style="font-size:11.5px; color:#166534; margin-top:4px;">✨ ${a.new_applicants} New Records</div>
                                    <div style="font-size:11.5px; color:#991b1b;">⏭️ ${a.duplicate_applicants} Duplicates (Will Skip)</div>
                                </div>
                                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px;">
                                    <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase;">Users in File</div>
                                    <div style="font-size:20px; font-weight:800; color:#0f172a; margin-top:2px;">${a.total_users}</div>
                                    <div style="font-size:11.5px; color:#166534; margin-top:4px;">✨ ${a.new_users} New Users</div>
                                    <div style="font-size:11.5px; color:#991b1b;">⏭️ ${a.duplicate_users} Duplicates (Will Skip)</div>
                                </div>
                            </div>

                            <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:10px; font-size:12px; color:#166534;">
                                💡 Ready to import! Click Smart Merge below to safely insert the ${a.new_applicants} new applicants without altering your existing records.
                            </div>
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonText: '🛡️ Proceed with Smart Merge',
                    cancelButtonText: 'Close Analysis',
                    confirmButtonColor: '#0f172a'
                }).then((res) => {
                    if (res.isConfirmed) {
                        executeDbImport('smart_merge');
                    }
                });
            } else {
                Swal.fire({ icon: 'error', title: 'Analysis Error', text: data.error || 'Unable to inspect file.', confirmButtonColor: '#0f172a' });
            }
        });
    }

    function cleanupOldBackups() {
        Swal.fire({
            title: 'Purge Old Backup Snapshots?',
            text: 'This will automatically delete backup snapshots older than the latest 15 files to free up disk space.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Purge Old Backups',
            confirmButtonColor: '#0f172a'
        }).then((res) => {
            if (res.isConfirmed) {
                fetch('api.php?api=cleanup_old_backups')
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({ icon: 'success', title: 'Cleaned Up!', text: data.message, confirmButtonColor: '#0f172a' });
                        loadSystemHealth();
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: data.error, confirmButtonColor: '#0f172a' });
                    }
                });
            }
        });
    }

    // Add Loan Product
    function addLoanProduct(e) {
        e.preventDefault();
        const val = document.getElementById('new-loan-product').value.trim();
        if(!val) return;
        loanProductsArr.push(val);
        saveListSetting('loan_products', JSON.stringify(loanProductsArr), () => location.reload());
    }

    // Add Lead Source
    function addLeadSource(e) {
        e.preventDefault();
        const val = document.getElementById('new-lead-source').value.trim();
        if(!val) return;
        leadSourcesArr.push(val);
        saveListSetting('lead_sources', JSON.stringify(leadSourcesArr), () => location.reload());
    }

    // Remove Item from array
    function removeListSetting(type, index) {
        if(!confirm('Delete this item?')) return;
        if(type === 'loan') {
            loanProductsArr.splice(index, 1);
            saveListSetting('loan_products', JSON.stringify(loanProductsArr), () => location.reload());
        } else {
            leadSourcesArr.splice(index, 1);
            saveListSetting('lead_sources', JSON.stringify(leadSourcesArr), () => location.reload());
        }
    }

    function saveListSetting(key, valStr, callback) {
        const fd = new FormData();
        fd.append('is_partial_config', '1');
        fd.append(key, valStr);
        fetch('api.php?api=save_settings', { method: 'POST', body: fd })
        .then(r => r.json()).then(data => {
            if(data.success) callback();
            else Swal.fire('Error', data.error, 'error');
        });
    }

    // Load templates list
    function loadTemplatesList() {
        fetch('api.php?api=get_templates')
        .then(res => res.json())
        .then(data => {
            const container = document.getElementById('templates-list-container');
            if (!container) return;
            if (data.length === 0) {
                container.innerHTML = '<p style="color:#64748b; font-size: 13px;">No templates saved yet.</p>';
            } else {
                let html = '<table class="audit-table"><thead><tr><th>Template Details</th><th style="text-align:right;">Actions</th></tr></thead><tbody>';
                data.forEach(t => {
                    let attachBadge = t.attachment_name ? `<span style="font-size: 11px; background: #e0e7ff; color: #3730a3; padding: 2px 6px; border-radius: 4px; margin-left: 8px; font-weight:700;"> 📎 ${t.attachment_name}</span>` : '';
                    html += `<tr>
                        <td>
                            <strong style="color:#0f172a; font-weight:800;">${t.template_name} (${t.type})</strong>
                            <div style="font-size: 12.5px; color: #64748b; margin-top:2px;">Subject: ${t.subject} ${attachBadge}</div>
                        </td>
                        <td style="text-align:right;">
                            <button type="button" class="btn-black-outline" style="padding:4px 10px; font-size:11px;" onclick="deleteTemplate(${t.id})">Delete</button>
                        </td>
                    </tr>`;
                });
                html += '</tbody></table>';
                container.innerHTML = html;
            }
        });
    }

    function saveTemplate(e) {
        e.preventDefault();
        const fd = new FormData(e.target);
        fetch('api.php?api=save_template', {method: 'POST', body: fd})
        .then(res => res.json())
        .then(data => {
            if (data.success) { Swal.fire({ icon: 'success', title: 'Saved', text: data.message, confirmButtonColor: '#0f172a' }); e.target.reset(); loadTemplatesList(); } 
            else { Swal.fire('Error', data.error || 'Failed.', 'error'); }
        });
    }

    function deleteTemplate(id) {
        if (!confirm('Are you sure you want to delete this template?')) return;
        const fd = new FormData(); fd.append('id', id);
        fetch('api.php?api=delete_template', {method: 'POST', body: fd})
        .then(r => r.json()).then(d => { loadTemplatesList(); });
    }

    // Load PDF/PPT Decks List
    function loadPptsList() {
        fetch('api.php?api=get_ppts')
        .then(res => res.json())
        .then(data => {
            const container = document.getElementById('ppts-list-container');
            if (!container) return;
            if (data.length === 0) {
                container.innerHTML = '<p style="color: #64748b; font-size: 13px;">No presentations uploaded yet.</p>';
            } else {
                let html = '<table class="audit-table"><thead><tr><th>Deck Title</th><th style="text-align:right;">Actions</th></tr></thead><tbody>';
                data.forEach(p => {
                    html += `<tr>
                        <td>
                            <strong><a href="uploads/${p.filename}" target="_blank" style="color:#0f172a; text-decoration:underline; font-weight:800;">${p.original_name}</a></strong>
                            <div style="font-size: 11px; color: #64748b; margin-top:2px;">File: ${p.filename}</div>
                        </td>
                        <td style="text-align:right;">
                            <button type="button" class="btn-black-outline" style="padding:4px 10px; font-size:11px;" onclick="deletePpt(${p.id})">Delete</button>
                        </td>
                    </tr>`;
                });
                html += '</tbody></table>';
                container.innerHTML = html;
            }
        });
    }

    function savePpt(e) {
        e.preventDefault();
        fetch('api.php?api=upload_ppt', {method: 'POST', body: new FormData(e.target)})
        .then(res => res.json()).then(data => {
            if(data.success) { Swal.fire({ icon: 'success', title: 'Uploaded', text: data.message, confirmButtonColor: '#0f172a' }); e.target.reset(); loadPptsList(); } 
            else { Swal.fire('Error', data.error || 'Failed.', 'error'); }
        });
    }

    function deletePpt(id) {
        if(!confirm('Delete this presentation deck?')) return;
        let fd = new FormData(); fd.append('id', id);
        fetch('api.php?api=delete_ppt', {method: 'POST', body: fd})
        .then(r => r.json()).then(d => { loadPptsList(); });
    }

    // User management scripts
    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'): ?>
    function toggleUserFields() {
        const role = document.getElementById('new_user_role').value;
        const hasDash = document.getElementById('has_dashboard').value;
        const staffTypeGroup = document.getElementById('staff_type_group');
        const dashAccessGroup = document.getElementById('dashboard_access_group');
        const loginIdGroup = document.getElementById('login_id_group');
        const passwordGroup = document.getElementById('password_group');
        const usernameInput = document.getElementById('username_input');
        const passwordInput = document.getElementById('password_input');
        
        if (role === 'Staff') {
            staffTypeGroup.style.display = 'block';
            dashAccessGroup.style.display = 'block';
        } else {
            staffTypeGroup.style.display = 'none';
            dashAccessGroup.style.display = 'none';
        }
        
        if ((role === 'Staff' && hasDash == '0')) {
            loginIdGroup.style.display = 'none';
            passwordGroup.style.display = 'none';
            usernameInput.removeAttribute('required');
            passwordInput.removeAttribute('required');
        } else {
            loginIdGroup.style.display = 'block';
            passwordGroup.style.display = 'block';
            usernameInput.setAttribute('required', 'required');
            passwordInput.setAttribute('required', 'required');
        }
    }

    function loadUsersList() {
        fetch('api.php?api=get_users')
        .then(res => res.json())
        .then(data => {
            currentUsersData = data || [];
            const container = document.getElementById('users-list-container');
            if (!container) return;
            let html = `<table class="audit-table"><thead><tr><th>User Info</th><th>Role & Type</th><th>Login Credentials</th><th>Status & Rights</th><th style="text-align:right;">Actions</th></tr></thead><tbody>`;
            currentUsersData.forEach(u => {
                let statusBadge = u.is_active == 1 ? '<span style="background:#dcfce7; color:#166534; padding:3px 8px; border-radius:12px; font-size:11px; font-weight:800;">Active</span>' : '<span style="background:#fee2e2; color:#991b1b; padding:3px 8px; border-radius:12px; font-size:11px; font-weight:800;">Deactivated</span>';
                
                let permCount = 0;
                let pObj = u.permissions || {};
                if (u.role === 'Admin') {
                    permCount = 8;
                } else if (typeof pObj === 'object') {
                    permCount = Object.values(pObj).filter(v => v == 1 || v === true).length;
                }
                let permBadge = u.role === 'Admin' ? '<span style="background:#0f172a; color:#fff; padding:2.5px 7px; border-radius:10px; font-size:10.5px; font-weight:800;">⚡ Full Admin</span>' : `<span style="background:#f1f5f9; color:#0f172a; border:1px solid #cbd5e1; padding:2.5px 7px; border-radius:10px; font-size:10.5px; font-weight:800;">🛡️ ${permCount} Rights</span>`;

                let actionBtn = ''; let deleteBtn = ''; let editBtn = ''; let rightsBtn = '';
                if (u.role !== 'Admin') {
                    editBtn = `<button type="button" class="btn-black-outline" style="padding:4px 8px; font-size:11px;" onclick="openEditUserModal(${u.id})">Edit</button>`;
                    rightsBtn = `<button type="button" class="btn-black-solid" style="padding:4px 8px; font-size:11px;" onclick="openQuickPermissionsModal(${u.id})">🔑 Rights</button>`;
                    actionBtn = u.is_active == 1 ? `<button type="button" class="btn-black-outline" style="padding:4px 8px; font-size:11px;" onclick="toggleUserStatus(${u.id})">Deactivate</button>` : `<button type="button" class="btn-black-solid" style="padding:4px 8px; font-size:11px;" onclick="toggleUserStatus(${u.id})">Activate</button>`;
                    deleteBtn = `<button type="button" class="btn-black-outline" style="padding:4px 8px; font-size:11px; color:#dc2626;" onclick="deleteUser(${u.id})">Delete</button>`;
                }
                
                let targetDisplay = '';
                if (u.role === 'Staff' && u.monthly_target) {
                    targetDisplay = `<div style="font-size:11px; color:#0284c7; font-weight:700; margin-top:2px;">🎯 Target: ₹${Number(u.monthly_target).toLocaleString('en-IN')} (${(Number(u.commission_rate||0.2)*100).toFixed(1)}%)</div>`;
                }
                
                let loginDetails = `<div style="font-size:12px; color:#64748b;">No Dashboard Access</div>`;
                if (u.has_dashboard == 1 || u.has_dashboard == null) {
                    loginDetails = `<strong>ID:</strong> <code style="background:#f1f5f9; padding:2px 5px; border-radius:4px; font-weight:bold; color:#0f172a;">${u.username}</code><br><strong>Pwd:</strong> <span style="color:#059669; font-weight:700;">${u.plain_password ? u.plain_password : '••••••'}</span>`;
                }
                
                html += `<tr>
                    <td>
                        <strong style="color:#0f172a; font-size:13.5px;">${u.name ? u.name : u.username}</strong>
                        ${targetDisplay}
                    </td>
                    <td><span style="background:#f1f5f9; padding:3px 8px; border-radius:6px; font-size:12px; font-weight:800; color:#0f172a;">${u.role} ${u.staff_type ? `(${u.staff_type})` : ''}</span></td>
                    <td style="font-size:12.5px;">${loginDetails}</td>
                    <td>
                        <div style="display:flex; flex-direction:column; gap:4px; align-items:flex-start;">
                            ${statusBadge}
                            ${permBadge}
                        </div>
                    </td>
                    <td style="text-align:right;">
                        <div style="display:inline-flex; gap:4px;">
                            ${editBtn}
                            ${rightsBtn}
                            ${actionBtn}
                            ${deleteBtn}
                        </div>
                    </td>
                </tr>`;
            });
            html += '</tbody></table>';
            container.innerHTML = html;
        });
    }

    function applyPermPreset(targetMode, presetType) {
        let keys = ['view_only_mode', 'view_all_applicants', 'export_data', 'edit_sanctions', 'manage_bank_logins', 'manage_payouts', 'resolve_sla_alerts', 'delete_records', 'access_settings'];
        let selected = [];
        if (presetType === 'senior_rm') selected = ['view_all_applicants', 'edit_sanctions', 'manage_bank_logins', 'resolve_sla_alerts', 'manage_payouts'];
        else if (presetType === 'junior_rm') selected = ['manage_bank_logins', 'resolve_sla_alerts'];
        else if (presetType === 'auditor') selected = ['view_all_applicants', 'export_data', 'manage_payouts'];
        else if (presetType === 'view_only') selected = ['view_only_mode'];
        else if (presetType === 'all') selected = [...keys];
        else if (presetType === 'none') selected = [];

        if (targetMode === 'new') {
            keys.forEach(k => {
                const el = document.querySelector(`form#create-user-form input[name="custom_permissions[${k}]"]`);
                if (el) el.checked = selected.includes(k);
            });
        } else if (targetMode === 'edit') {
            const idMap = {
                'view_only_mode': 'edit_perm_view_only',
                'view_all_applicants': 'edit_perm_view_all',
                'export_data': 'edit_perm_export',
                'edit_sanctions': 'edit_perm_sanctions',
                'manage_bank_logins': 'edit_perm_bank_logins',
                'manage_payouts': 'edit_perm_payouts',
                'resolve_sla_alerts': 'edit_perm_sla',
                'delete_records': 'edit_perm_delete',
                'access_settings': 'edit_perm_settings'
            };
            keys.forEach(k => {
                const el = document.getElementById(idMap[k]);
                if (el) el.checked = selected.includes(k);
            });
        }
    }

    function openQuickPermissionsModal(userId) {
        let u = currentUsersData.find(x => x.id == userId);
        if (!u) return;

        let p = u.permissions || {};
        let isChecked = (key, defaultVal = false) => (p[key] !== undefined ? !!p[key] : defaultVal);

        let html = `
            <div style="text-align:left; padding:4px 0; font-size:12.5px;">
                <div style="display:flex; justify-content:space-between; align-items:center; background:#f8fafc; border:1px solid #e2e8f0; padding:10px 14px; border-radius:8px; margin-bottom:14px;">
                    <div>
                        <div style="font-weight:800; font-size:14px; color:#0f172a;">${u.name || u.username}</div>
                        <div style="font-size:11.5px; color:#64748b;">Role: <strong>${u.role}</strong> ${u.staff_type ? `(${u.staff_type})` : ''}</div>
                    </div>
                    <span style="background:#0f172a; color:#fff; font-size:11px; font-weight:800; padding:3px 9px; border-radius:12px;">ID #${u.id}</span>
                </div>

                <div style="display:flex; gap:6px; flex-wrap:wrap; margin-bottom:12px; background:#f1f5f9; padding:8px 10px; border-radius:8px; align-items:center;">
                    <span style="font-size:11px; font-weight:700; color:#475569;">Quick Presets:</span>
                    <button type="button" class="btn-black-outline" style="font-size:10.5px; padding:2px 7px; background:#fff;" onclick="setModalPermPreset('senior_rm')">🛡️ Senior RM</button>
                    <button type="button" class="btn-black-outline" style="font-size:10.5px; padding:2px 7px; background:#fff;" onclick="setModalPermPreset('junior_rm')">💼 Junior RM</button>
                    <button type="button" class="btn-black-outline" style="font-size:10.5px; padding:2px 7px; background:#fff;" onclick="setModalPermPreset('auditor')">📊 Auditor</button>
                    <button type="button" class="btn-black-outline" style="font-size:10.5px; padding:2px 7px; background:#fff;" onclick="setModalPermPreset('view_only')">👁️‍🗨️ View Only</button>
                    <button type="button" class="btn-black-outline" style="font-size:10.5px; padding:2px 7px; background:#fff;" onclick="setModalPermPreset('all')">✅ All</button>
                    <button type="button" class="btn-black-outline" style="font-size:10.5px; padding:2px 7px; background:#fff;" onclick="setModalPermPreset('none')">🧹 Clear</button>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                    <label style="display:flex; align-items:center; gap:8px; background:#fff1f2; border:1px solid #fecdd3; padding:9px 12px; border-radius:6px; cursor:pointer; grid-column:1 / -1;">
                        <input type="checkbox" id="m_perm_view_only" ${isChecked('view_only_mode') ? 'checked' : ''}>
                        <span>👁️‍🗨️ <strong style="color:#e11d48;">View-Only Mode</strong> (Disable Editing & Saving)</span>
                    </label>
                    <label style="display:flex; align-items:center; gap:8px; background:#fff; border:1px solid #cbd5e1; padding:9px 12px; border-radius:6px; cursor:pointer;">
                        <input type="checkbox" id="m_perm_view_all" ${isChecked('view_all_applicants') ? 'checked' : ''}>
                        <span>👁️ <strong>View All Clients</strong></span>
                    </label>
                    <label style="display:flex; align-items:center; gap:8px; background:#fff; border:1px solid #cbd5e1; padding:9px 12px; border-radius:6px; cursor:pointer;">
                        <input type="checkbox" id="m_perm_export" ${isChecked('export_data') ? 'checked' : ''}>
                        <span>📊 <strong>Data Export Access</strong></span>
                    </label>
                    <label style="display:flex; align-items:center; gap:8px; background:#fff; border:1px solid #cbd5e1; padding:9px 12px; border-radius:6px; cursor:pointer;">
                        <input type="checkbox" id="m_perm_sanctions" ${isChecked('edit_sanctions') ? 'checked' : ''}>
                        <span>🏦 <strong>Sanction Overrides</strong></span>
                    </label>
                    <label style="display:flex; align-items:center; gap:8px; background:#fff; border:1px solid #cbd5e1; padding:9px 12px; border-radius:6px; cursor:pointer;">
                        <input type="checkbox" id="m_perm_bank_logins" ${isChecked('manage_bank_logins', true) ? 'checked' : ''}>
                        <span>🛡️ <strong>Manage Bank Logins</strong></span>
                    </label>
                    <label style="display:flex; align-items:center; gap:8px; background:#fff; border:1px solid #cbd5e1; padding:9px 12px; border-radius:6px; cursor:pointer;">
                        <input type="checkbox" id="m_perm_payouts" ${isChecked('manage_payouts') ? 'checked' : ''}>
                        <span>💰 <strong>Payout & Invoices</strong></span>
                    </label>
                    <label style="display:flex; align-items:center; gap:8px; background:#fff; border:1px solid #cbd5e1; padding:9px 12px; border-radius:6px; cursor:pointer;">
                        <input type="checkbox" id="m_perm_sla" ${isChecked('resolve_sla_alerts', true) ? 'checked' : ''}>
                        <span>⚡ <strong>Resolve SLA Alerts</strong></span>
                    </label>
                    <label style="display:flex; align-items:center; gap:8px; background:#fff; border:1px solid #cbd5e1; padding:9px 12px; border-radius:6px; cursor:pointer;">
                        <input type="checkbox" id="m_perm_delete" ${isChecked('delete_records') ? 'checked' : ''}>
                        <span>🗑️ <strong>Delete Records Rights</strong></span>
                    </label>
                    <label style="display:flex; align-items:center; gap:8px; background:#fff; border:1px solid #cbd5e1; padding:9px 12px; border-radius:6px; cursor:pointer;">
                        <input type="checkbox" id="m_perm_settings" ${isChecked('access_settings') ? 'checked' : ''}>
                        <span>⚙️ <strong>CRM Settings Access</strong></span>
                    </label>
                </div>
            </div>
        `;

        Swal.fire({
            title: `<div style="font-size:16px; font-weight:800; color:#0f172a; display:flex; align-items:center; gap:8px;"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg> Quick Access Control Matrix</div>`,
            html: html,
            showCancelButton: true,
            confirmButtonText: 'Save Access Rights',
            confirmButtonColor: '#0f172a',
            width: '560px',
            preConfirm: () => {
                const fd = new FormData();
                fd.append('user_id', userId);
                fd.append('custom_permissions[view_only_mode]', document.getElementById('m_perm_view_only').checked ? 1 : 0);
                fd.append('custom_permissions[view_all_applicants]', document.getElementById('m_perm_view_all').checked ? 1 : 0);
                fd.append('custom_permissions[export_data]', document.getElementById('m_perm_export').checked ? 1 : 0);
                fd.append('custom_permissions[edit_sanctions]', document.getElementById('m_perm_sanctions').checked ? 1 : 0);
                fd.append('custom_permissions[manage_bank_logins]', document.getElementById('m_perm_bank_logins').checked ? 1 : 0);
                fd.append('custom_permissions[manage_payouts]', document.getElementById('m_perm_payouts').checked ? 1 : 0);
                fd.append('custom_permissions[resolve_sla_alerts]', document.getElementById('m_perm_sla').checked ? 1 : 0);
                fd.append('custom_permissions[delete_records]', document.getElementById('m_perm_delete').checked ? 1 : 0);
                fd.append('custom_permissions[access_settings]', document.getElementById('m_perm_settings').checked ? 1 : 0);
                return fetch('api.php?api=update_user_permissions', { method: 'POST', body: fd })
                    .then(r => r.json());
            }
        }).then(res => {
            if (res.isConfirmed && res.value) {
                if (res.value.success) {
                    Swal.fire({ icon: 'success', title: 'Rights Updated!', text: res.value.message, confirmButtonColor: '#0f172a' });
                    loadUsersList();
                } else {
                    Swal.fire('Error', res.value.error || 'Failed to save permissions', 'error');
                }
            }
        });
    }

    function setModalPermPreset(presetType) {
        let keys = ['view_only_mode', 'view_all_applicants', 'export_data', 'edit_sanctions', 'manage_bank_logins', 'manage_payouts', 'resolve_sla_alerts', 'delete_records', 'access_settings'];
        let selected = [];
        if (presetType === 'senior_rm') selected = ['view_all_applicants', 'edit_sanctions', 'manage_bank_logins', 'resolve_sla_alerts', 'manage_payouts'];
        else if (presetType === 'junior_rm') selected = ['manage_bank_logins', 'resolve_sla_alerts'];
        else if (presetType === 'auditor') selected = ['view_all_applicants', 'export_data', 'manage_payouts'];
        else if (presetType === 'view_only') selected = ['view_only_mode'];
        else if (presetType === 'all') selected = [...keys];
        else if (presetType === 'none') selected = [];

        const idMap = {
            'view_only_mode': 'm_perm_view_only',
            'view_all_applicants': 'm_perm_view_all',
            'export_data': 'm_perm_export',
            'edit_sanctions': 'm_perm_sanctions',
            'manage_bank_logins': 'm_perm_bank_logins',
            'manage_payouts': 'm_perm_payouts',
            'resolve_sla_alerts': 'm_perm_sla',
            'delete_records': 'm_perm_delete',
            'access_settings': 'm_perm_settings'
        };
        keys.forEach(k => {
            const el = document.getElementById(idMap[k]);
            if (el) el.checked = selected.includes(k);
        });
    }

    function openEditUserModal(id) {
        let u = currentUsersData.find(x => x.id == id);
        if (!u) return;
        document.getElementById('edit_user_id').value = u.id;
        document.getElementById('edit_user_name').value = u.name || u.username;
        document.getElementById('edit_user_role').value = u.role || 'Staff';
        document.getElementById('edit_user_staff_type').value = u.staff_type || 'In-Office';
        document.getElementById('edit_user_target').value = u.monthly_target || 15000000;
        document.getElementById('edit_user_commission').value = u.commission_rate || 0.20;
        document.getElementById('edit_user_password').value = '';

        let p = u.permissions || {};
        document.getElementById('edit_perm_view_only').checked = !!p.view_only_mode;
        document.getElementById('edit_perm_view_all').checked = !!p.view_all_applicants;
        document.getElementById('edit_perm_export').checked = !!p.export_data;
        document.getElementById('edit_perm_sanctions').checked = !!p.edit_sanctions;
        document.getElementById('edit_perm_bank_logins').checked = p.manage_bank_logins !== undefined ? !!p.manage_bank_logins : true;
        document.getElementById('edit_perm_payouts').checked = !!p.manage_payouts;
        document.getElementById('edit_perm_sla').checked = p.resolve_sla_alerts !== undefined ? !!p.resolve_sla_alerts : true;
        document.getElementById('edit_perm_delete').checked = !!p.delete_records;
        document.getElementById('edit_perm_settings').checked = !!p.access_settings;

        document.getElementById('editUserModal').style.display = 'flex';
    }

    function submitEditUser(e) {
        e.preventDefault();
        let fd = new FormData();
        fd.append('user_id', document.getElementById('edit_user_id').value);
        fd.append('name', document.getElementById('edit_user_name').value);
        fd.append('role', document.getElementById('edit_user_role').value);
        fd.append('staff_type', document.getElementById('edit_user_staff_type').value);
        fd.append('monthly_target', document.getElementById('edit_user_target').value);
        fd.append('commission_rate', document.getElementById('edit_user_commission').value);
        fd.append('password', document.getElementById('edit_user_password').value);

        fd.append('custom_permissions[view_only_mode]', document.getElementById('edit_perm_view_only').checked ? 1 : 0);
        fd.append('custom_permissions[view_all_applicants]', document.getElementById('edit_perm_view_all').checked ? 1 : 0);
        fd.append('custom_permissions[export_data]', document.getElementById('edit_perm_export').checked ? 1 : 0);
        fd.append('custom_permissions[edit_sanctions]', document.getElementById('edit_perm_sanctions').checked ? 1 : 0);
        fd.append('custom_permissions[manage_bank_logins]', document.getElementById('edit_perm_bank_logins').checked ? 1 : 0);
        fd.append('custom_permissions[manage_payouts]', document.getElementById('edit_perm_payouts').checked ? 1 : 0);
        fd.append('custom_permissions[resolve_sla_alerts]', document.getElementById('edit_perm_sla').checked ? 1 : 0);
        fd.append('custom_permissions[delete_records]', document.getElementById('edit_perm_delete').checked ? 1 : 0);
        fd.append('custom_permissions[access_settings]', document.getElementById('edit_perm_settings').checked ? 1 : 0);

        fetch('api.php?api=update_user', { method: 'POST', body: fd })
        .then(r => r.json()).then(d => {
            if (d.success) {
                document.getElementById('editUserModal').style.display = 'none';
                Swal.fire({ icon: 'success', title: 'User Profile Updated', text: d.message, confirmButtonColor: '#0f172a' });
                loadUsersList();
            } else {
                Swal.fire('Error', d.error, 'error');
            }
        });
    }

    function toggleUserStatus(id) {
        if (!confirm("Change this user's active status?")) return;
        let fd = new FormData(); fd.append('id', id);
        fetch('api.php?api=toggle_user_status', {method: 'POST', body: fd})
        .then(r => r.json()).then(d => { loadUsersList(); });
    }

    function deleteUser(id) {
        if (!confirm("Permanently DELETE this user?")) return;
        let fd = new FormData(); fd.append('id', id);
        fetch('api.php?api=delete_user', {method: 'POST', body: fd})
        .then(r => r.json()).then(d => { loadUsersList(); });
    }

    function createUser(e) {
        e.preventDefault();
        fetch('api.php?api=create_user', {method: 'POST', body: new FormData(e.target)})
        .then(res => res.json()).then(data => {
            if (data.success) { Swal.fire({ icon: 'success', title: 'User Registered', text: data.message, confirmButtonColor: '#0f172a' }); e.target.reset(); loadUsersList(); } 
            else { Swal.fire('Error', data.error, 'error'); }
        });
    }
    function initiate3StepDbReset() {
        // Step 1: Security Warning Modal
        Swal.fire({
            title: '⚠️ Step 1 of 3: Master Database Wipe Warning',
            text: 'This will permanently delete all loan applications, leads, documents, and records. ALL STAFF ACCOUNTS AND PASSWORDS WILL REMAIN 100% SAFE. Do you want to proceed?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, Proceed to Password Check →'
        }).then((res1) => {
            if (!res1.isConfirmed) return;

            // Step 2: Admin Password Verification Modal
            Swal.fire({
                title: '🔐 Step 2 of 3: Admin Password Verification',
                text: 'Please enter your current Admin Account Password to authorize database wipe:',
                input: 'password',
                inputPlaceholder: 'Enter Admin Password',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                confirmButtonText: 'Verify Password →',
                inputValidator: (val) => {
                    if (!val) return 'Admin password is required!';
                }
            }).then((res2) => {
                if (!res2.isConfirmed || !res2.value) return;

                const adminPassword = res2.value;

                // Step 3: Explicit Text Confirmation Modal
                Swal.fire({
                    title: '💣 Step 3 of 3: Final Confirmation',
                    html: 'Type <strong style="color:#dc2626; font-size:16px;">RESET CRM DATABASE</strong> in exact uppercase letters below to execute wipe:',
                    input: 'text',
                    inputPlaceholder: 'RESET CRM DATABASE',
                    showCancelButton: true,
                    confirmButtonColor: '#dc2626',
                    confirmButtonText: '🚨 WIPE CRM DATABASE NOW',
                    inputValidator: (val) => {
                        if (!val || val.trim().toUpperCase() !== 'RESET CRM DATABASE') {
                            return 'You must type RESET CRM DATABASE in exact uppercase!';
                        }
                    }
                }).then((res3) => {
                    if (!res3.isConfirmed || !res3.value) return;

                    const confirmText = res3.value;

                    // Send request to API
                    Swal.fire({
                        title: 'Executing Database Reset...',
                        text: 'Wiping transactional records and cleaning CRM database.',
                        allowOutsideClick: false,
                        didOpen: () => { Swal.showLoading(); }
                    });

                    let fd = new FormData();
                    fd.append('admin_password', adminPassword);
                    fd.append('confirm_text', confirmText);

                    fetch('api.php?api=reset_crm_database', {
                        method: 'POST',
                        body: fd
                    })
                    .then(r => r.json())
                    .then(d => {
                        if (d.success) {
                            Swal.fire({
                                icon: 'success',
                                title: '🎉 Database Reset Completed!',
                                text: d.message,
                                confirmButtonColor: '#0f172a'
                            }).then(() => {
                                window.location.href = 'dashboard.php';
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Reset Failed',
                                text: d.error,
                                confirmButtonColor: '#0f172a'
                            });
                        }
                    })
                    .catch(err => {
                        Swal.fire('Error', 'Server communication error: ' + err.message, 'error');
                    });
                });
            });
        });
    }
    <?php endif; ?>

    document.addEventListener('DOMContentLoaded', () => {
        renderBranchesList();
        loadTemplatesList();
        loadPptsList();
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'): ?>
        loadUsersList();
        toggleUserFields();
        <?php endif; ?>
    });
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
