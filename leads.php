<?php
require_once 'config.php';
require_role(['Admin', 'RM']);

$is_admin = ($_SESSION['role'] ?? '') === 'Admin';
$staff_members = $db->query("
    SELECT username, COALESCE(name, full_name, username) as full_name, role 
    FROM users 
    WHERE is_active = 1 
      AND LOWER(role) IN ('admin','staff','rm','manager','credit officer') 
      AND LOWER(role) NOT IN ('agent','builder','ca','partner','connector','dsa') 
    ORDER BY role ASC, username ASC
")->fetchAll(PDO::FETCH_ASSOC);

$page_title = $is_admin ? 'Lead Management Engine' : 'My Assigned Leads';
$page_subtitle = $is_admin ? 'Capture, track, and convert prospect leads into loan applications' : 'Track and convert your assigned leads into clients';
$hide_main_header = true;
require_once 'header.php';
?>
<script>
window.staffMembersList = <?php echo json_encode($staff_members); ?>;
window.isAdminUser = <?php echo (($_SESSION['role'] ?? '') === 'Admin') ? 'true' : 'false'; ?>;
</script>

<style>
/* ==========================================================================
   BFS CIRCLE EXECUTIVE CRM LUXURY DESIGN SYSTEM - LEADS WORKSPACE
   ========================================================================== */
.lm-layout {
    width: 100%;
    margin: 0 auto;
    padding: 16px 14px 40px;
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
    color: #0f172a;
    box-sizing: border-box;
}

/* --- Top KPI Metric Grid --- */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}
.kpi-card {
    background: #ffffff;
    border-radius: 12px;
    padding: 16px 20px;
    border: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.03);
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    position: relative;
    overflow: hidden;
}
.kpi-card:hover {
    border-color: #0f172a;
    transform: translateY(-2px);
    box-shadow: 0 8px 18px rgba(15, 23, 42, 0.07);
}
.kpi-card::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 2.5px;
    background: transparent;
    transition: background 0.2s;
}
.kpi-card:hover::after {
    background: #0f172a;
}
.kpi-val {
    font-size: 26px;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.15;
    font-family: 'Outfit', sans-serif;
}
.kpi-lbl {
    font-size: 11px;
    color: #64748b;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    margin-top: 4px;
}
.kpi-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    background: #f8fafc;
    color: #0f172a;
    border: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
    transition: all 0.2s;
}
.kpi-card:hover .kpi-icon {
    background: #0f172a;
    color: #ffffff;
    border-color: #0f172a;
}


/* --- Executive Control Toolbar --- */
.control-deck {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 12px 16px;
    margin-bottom: 16px;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.02);
}
.top-actions-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 12px;
    padding-bottom: 12px;
    border-bottom: 1px solid #f1f5f9;
}

/* Logical Segmented View Capsule */
.view-switcher-group {
    display: inline-flex;
    align-items: center;
    background: #f1f5f9;
    padding: 3px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    gap: 2px;
    height: 34px;
    box-sizing: border-box;
}
.view-mode-btn {
    background: transparent;
    color: #64748b;
    border: none;
    height: 26px;
    padding: 0 12px;
    border-radius: 6px;
    font-size: 11.5px;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    user-select: none;
}
.view-mode-btn:hover:not(.active) {
    color: #0f172a;
    background: rgba(0, 0, 0, 0.04);
}
.view-mode-btn.active {
    background: #0f172a;
    color: #ffffff;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.18);
}
.view-mode-btn.active svg {
    color: #ffffff;
}

/* Logical Secondary Utilities */
.btn-toolbar-utility {
    background: #ffffff;
    color: #334155;
    border: 1px solid #cbd5e1;
    height: 34px;
    padding: 0 13px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 11.5px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
    white-space: nowrap;
}
.btn-toolbar-utility:hover {
    background: #f8fafc;
    border-color: #0f172a;
    color: #0f172a;
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(15, 23, 42, 0.08);
}
.btn-toolbar-utility svg {
    color: #64748b;
    transition: color 0.15s;
}
.btn-toolbar-utility:hover svg {
    color: #0f172a;
}

/* Logical Hero Primary CTA */
.btn-hero-primary {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    color: #ffffff;
    border: 1px solid #0f172a;
    height: 34px;
    padding: 0 16px;
    border-radius: 8px;
    font-weight: 800;
    font-size: 12px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.18);
    white-space: nowrap;
    letter-spacing: 0.2px;
}
.btn-hero-primary:hover {
    background: #000000;
    border-color: #000000;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.25);
    color: #ffffff;
}
.btn-hero-primary svg {
    color: #ffffff;
}

.filters-row {
    display: flex !important;
    flex-direction: row !important;
    flex-wrap: nowrap !important;
    gap: 8px !important;
    align-items: center !important;
    width: 100% !important;
    overflow-x: auto !important;
    box-sizing: border-box !important;
    scrollbar-width: thin;
    padding-bottom: 2px;
}
.filters-row .search-wrapper {
    flex: 1 1 200px !important;
    min-width: 180px !important;
    position: relative !important;
    margin: 0 !important;
}
.filters-row .search-wrapper input {
    width: 100% !important;
    height: 34px !important;
    padding: 0 12px 0 34px !important;
    border: 1px solid #cbd5e1 !important;
    border-radius: 8px !important;
    font-size: 12px !important;
    font-weight: 600 !important;
    color: #0f172a !important;
    background: #f8fafc !important;
    box-sizing: border-box !important;
    outline: none !important;
    transition: all 0.2s;
}
.filters-row .search-wrapper input:focus {
    border-color: #0f172a !important;
    background: #ffffff !important;
    box-shadow: 0 0 0 2px rgba(15, 23, 42, 0.08) !important;
}
.filters-row .search-wrapper svg {
    position: absolute !important;
    left: 11px !important;
    top: 50% !important;
    transform: translateY(-50%) !important;
    width: 14px !important;
    height: 14px !important;
    color: #94a3b8 !important;
    pointer-events: none !important;
}
.filters-row select.filter-select {
    width: auto !important;
    flex: 0 0 auto !important;
    height: 34px !important;
    padding: 0 10px !important;
    border: 1px solid #cbd5e1 !important;
    border-radius: 8px !important;
    font-size: 11.5px !important;
    font-weight: 600 !important;
    color: #0f172a !important;
    background: #ffffff !important;
    cursor: pointer !important;
    outline: none !important;
    box-sizing: border-box !important;
    white-space: nowrap !important;
    transition: border-color 0.2s;
}
.filters-row select.filter-select:focus {
    border-color: #0f172a !important;
}
.filters-row .btn-reset-filters {
    flex: 0 0 auto !important;
    height: 34px !important;
    padding: 0 12px !important;
    border-radius: 8px !important;
    font-size: 11.5px !important;
    font-weight: 700 !important;
    white-space: nowrap !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 5px !important;
    margin: 0 !important;
}
.filters-row #leadsResultCount {
    flex: 0 0 auto !important;
    white-space: nowrap !important;
    margin-left: auto !important;
    font-size: 11.5px !important;
    font-weight: 700 !important;
    color: #64748b !important;
    padding-left: 6px !important;
}

/* --- Buttons --- */
.btn-primary {
    background: #0f172a;
    color: #ffffff;
    border: 1px solid #0f172a;
    padding: 8px 16px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 12.5px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none;
    transition: all 0.2s;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.12);
}
.btn-primary:hover {
    background: #000000;
    border-color: #000000;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}
.btn-secondary {
    background: #ffffff;
    color: #0f172a;
    border: 1px solid #cbd5e1;
    padding: 8px 14px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 12.5px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none;
    transition: all 0.2s;
}
.btn-secondary:hover {
    background: #f8fafc;
    border-color: #0f172a;
    color: #0f172a;
}

/* --- Dynamic Bulk Operations Strip --- */
#bulkActionBar {
    display: none;
    background: #0f172a;
    color: #ffffff;
    border-radius: 10px;
    padding: 10px 18px;
    margin-bottom: 14px;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    box-shadow: 0 6px 18px rgba(15, 23, 42, 0.18);
    animation: fadeIn 0.2s ease;
}
@keyframes fadeIn { from { opacity: 0; transform: translateY(-4px); } to { opacity: 1; transform: translateY(0); } }

/* --- Table Container & Modern Grid Styling --- */
.table-container {
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.04);
    overflow-x: auto;
    scrollbar-width: thin;
}
.lm-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    table-layout: auto;
}
.lm-table th {
    background: #0f172a;
    padding: 9px 6px;
    text-align: left;
    font-size: 10px;
    font-weight: 800;
    color: #ffffff;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #0f172a;
    white-space: nowrap;
}
.lm-table td {
    padding: 7px 6px;
    font-size: 11.5px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
    transition: background 0.15s;
}
.lm-table tbody tr.lead-row {
    transition: all 0.15s ease;
}
.lm-table tbody tr.lead-row:hover {
    background: #f8fafc;
}
.lm-table tbody tr.lead-row.expanded {
    background: #f1f5f9;
}

/* --- Interactive Inline Dropdown Selectors --- */
.table-dropdown-select {
    padding: 2px 4px;
    font-size: 11px;
    font-weight: 700;
    border-radius: 6px;
    border: 1px solid #cbd5e1;
    background-color: #ffffff;
    color: #0f172a;
    cursor: pointer;
    outline: none;
    transition: all 0.2s;
    height: 28px;
    white-space: nowrap;
    box-sizing: border-box;
    display: inline-block;
}
.table-dropdown-select:hover {
    border-color: #0f172a;
}
.table-dropdown-select:focus {
    border-color: #0f172a;
    box-shadow: 0 0 0 2px rgba(15, 23, 42, 0.1);
}

.select-prio {
    min-width: 76px !important;
    max-width: 86px !important;
}
.select-stage {
    min-width: 102px !important;
    max-width: 116px !important;
}
.select-rm {
    min-width: 95px !important;
    max-width: 116px !important;
}

/* ==========================================================================
   BFS CIRCLE EXECUTIVE SEARCHABLE RM ASSIGNMENT & POPOVER SYSTEM
   ========================================================================== */
.rm-assign-trigger-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    height: 28px;
    padding: 2px 8px;
    border-radius: 6px;
    font-size: 11.5px;
    font-weight: 600;
    cursor: pointer;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    color: #0f172a;
    transition: all 0.15s ease;
    max-width: 155px;
    white-space: nowrap;
    outline: none;
    user-select: none;
    box-sizing: border-box;
}
.rm-assign-trigger-btn:hover {
    border-color: #0f172a;
    background: #f8fafc;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}
.rm-assign-trigger-btn.is-assigned {
    background: #ffffff;
    border-color: #e2e8f0;
    color: #0f172a;
}
.rm-assign-trigger-btn.is-assigned:hover {
    background: #f8fafc;
    border-color: #0f172a;
}
.rm-assign-trigger-btn.is-unassigned {
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    color: #64748b;
    font-weight: 500;
}
.rm-assign-trigger-btn.is-unassigned:hover {
    background: #ffffff;
    border-color: #0f172a;
    border-style: solid;
    color: #0f172a;
}
.rm-assign-trigger-btn.is-open {
    border-color: #0f172a !important;
    box-shadow: 0 0 0 2px rgba(15, 23, 42, 0.08) !important;
    background: #ffffff !important;
}
.rm-trigger-avatar {
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: #0f172a;
    color: #ffffff;
    font-size: 8.5px;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    letter-spacing: -0.3px;
}
.rm-trigger-avatar.unassigned {
    background: #f1f5f9;
    color: #94a3b8;
    border: 1px solid #e2e8f0;
}
.rm-trigger-label {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 95px;
    font-size: 11px;
    letter-spacing: -0.1px;
}
.rm-trigger-arrow {
    width: 11px;
    height: 11px;
    color: #94a3b8;
    flex-shrink: 0;
    transition: transform 0.2s ease;
}
.rm-assign-trigger-btn.is-open .rm-trigger-arrow {
    transform: rotate(180deg);
}

/* Floating RM Popover Dropdown */
.rm-search-popover-container {
    position: fixed;
    z-index: 999999;
    width: 290px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    box-shadow: 0 14px 34px -4px rgba(15, 23, 42, 0.14), 0 4px 10px -2px rgba(15, 23, 42, 0.05);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    animation: rmPopoverFadeIn 0.15s cubic-bezier(0.16, 1, 0.3, 1);
    font-family: inherit;
}
@keyframes rmPopoverFadeIn {
    from { opacity: 0; transform: translateY(-4px) scale(0.98); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}
.rm-popover-header {
    padding: 10px 10px 8px;
    background: #ffffff;
    border-bottom: 1px solid #f1f5f9;
}
.rm-popover-search-wrap {
    position: relative;
    display: flex;
    align-items: center;
}
.rm-popover-search-icon {
    position: absolute;
    left: 10px;
    width: 13px;
    height: 13px;
    color: #94a3b8;
    pointer-events: none;
}
.rm-popover-search-input {
    width: 100%;
    height: 32px;
    padding: 0 28px 0 30px;
    border: 1px solid #e2e8f0;
    border-radius: 7px;
    font-size: 11.5px;
    outline: none;
    background: #f8fafc;
    color: #0f172a;
    transition: all 0.15s;
    box-sizing: border-box;
}
.rm-popover-search-input:focus {
    background: #ffffff;
    border-color: #0f172a;
    box-shadow: 0 0 0 2px rgba(15, 23, 42, 0.06);
}
.rm-popover-search-input::placeholder {
    color: #94a3b8;
}
.rm-popover-clear-btn {
    position: absolute;
    right: 6px;
    background: none;
    border: none;
    font-size: 15px;
    line-height: 1;
    color: #94a3b8;
    cursor: pointer;
    padding: 2px 4px;
}
.rm-popover-clear-btn:hover {
    color: #0f172a;
}
.rm-popover-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 6px;
    padding: 0 2px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #94a3b8;
}
.rm-popover-meta kbd {
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-radius: 3px;
    padding: 1px 4px;
    font-size: 8.5px;
    color: #64748b;
    font-family: inherit;
    font-weight: 600;
}
.rm-popover-list {
    max-height: 250px;
    overflow-y: auto;
    padding: 5px;
}
.rm-popover-item {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 6px 8px;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.12s ease;
    user-select: none;
    outline: none;
    border: 1px solid transparent;
}
.rm-popover-item:hover, .rm-popover-item.is-focused {
    background: #f8fafc;
    border-color: #f1f5f9;
}
.rm-popover-item.is-selected {
    background: #f8fafc;
    border-color: #e2e8f0;
}
.rm-item-avatar {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: #f1f5f9;
    color: #0f172a;
    border: 1px solid #e2e8f0;
    font-size: 9.5px;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.rm-popover-item.is-selected .rm-item-avatar {
    background: #0f172a;
    color: #ffffff;
    border-color: #0f172a;
}
.rm-item-avatar-clear {
    background: #f8fafc;
    color: #94a3b8;
    border: 1px solid #e2e8f0;
}
.rm-popover-item-unassign:hover {
    background: #fef2f2 !important;
    border-color: #fee2e2 !important;
}
.rm-popover-item-unassign:hover .rm-item-title {
    color: #b91c1c !important;
}
.rm-popover-item-unassign:hover .rm-item-avatar-clear {
    background: #fee2e2;
    color: #b91c1c;
    border-color: #fca5a5;
}
.rm-item-content {
    flex: 1;
    min-width: 0;
}
.rm-item-title {
    font-size: 11.5px;
    font-weight: 600;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 6px;
    line-height: 1.25;
}
.rm-item-title mark, .rm-item-sub mark {
    background: #fef08a;
    color: #0f172a;
    padding: 0 2px;
    border-radius: 2px;
    font-weight: 700;
}
.rm-item-sub {
    font-size: 10px;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 1px;
}
.rm-item-role-badge {
    font-size: 8.5px;
    font-weight: 700;
    text-transform: uppercase;
    padding: 1px 4px;
    border-radius: 3px;
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #e2e8f0;
}
.rm-item-role-badge.badge-rm { background: #f8fafc; color: #0f172a; border-color: #e2e8f0; font-weight: 700; }
.rm-item-role-badge.badge-manager { background: #faf5ff; color: #581c87; border-color: #e9d5ff; }
.rm-item-role-badge.badge-admin { background: #fef3c7; color: #78350f; border-color: #fde68a; }
.rm-item-role-badge.badge-credit { background: #ecfdf5; color: #065f46; border-color: #a7f3d0; }
.rm-item-check {
    width: 14px;
    height: 14px;
    color: #0f172a;
    flex-shrink: 0;
}
.rm-popover-empty {
    padding: 24px 16px;
    text-align: center;
    color: #64748b;
    font-size: 11.5px;
}
.kanban-rm-tag.is-clickable {
    cursor: pointer;
    transition: all 0.15s ease;
}
.kanban-rm-tag.is-clickable:hover {
    background: #e2e8f0;
    color: #0f172a;
}


/* Custom Dropdown Badge Variations */
.select-stage-new       { background: #ffffff; color: #0f172a; border-color: #cbd5e1; }
.select-stage-contacted { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
.select-stage-scheduled { background: #fffbeb; color: #b45309; border-color: #fde68a; }
.select-stage-qualified { background: #faf5ff; color: #6b21a8; border-color: #e9d5ff; }
.select-stage-converted { background: #0f172a; color: #ffffff; border-color: #0f172a; }
.select-stage-disqual   { background: #fef2f2; color: #991b1b; border-color: #fca5a5; }

.select-prio-hot  { background: #fef2f2; color: #b91c1c; border-color: #fca5a5; font-weight: 800; }
.select-prio-warm { background: #fffbeb; color: #b45309; border-color: #fde68a; font-weight: 700; }
.select-prio-cold { background: #f8fafc; color: #475569; border-color: #cbd5e1; font-weight: 600; }

/* --- Partner Badges --- */
.badge-partner {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 6px;
    border-radius: 5px;
    font-size: 10.5px;
    font-weight: 700;
}
.badge-partner-builder { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
.badge-partner-ca      { background: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe; }
.badge-partner-dsa     { background: #f0f9ff; color: #075985; border: 1px solid #bae6fd; }
.badge-partner-customer { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
.badge-partner-direct  { background: #f8fafc; color: #475569; border: 1px solid #cbd5e1; }
.partner-tag {
    font-size: 8.5px;
    font-weight: 800;
    text-transform: uppercase;
    padding: 1px 3px;
    border-radius: 3px;
    background: rgba(0, 0, 0, 0.06);
}

/* --- Action Buttons --- */
.action-btn {
    width: 24px;
    height: 24px;
    border-radius: 5px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #0f172a;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.15s ease;
    margin: 0;
    padding: 0;
    text-decoration: none;
    flex-shrink: 0;
}
.action-btn svg {
    width: 11.5px;
    height: 11.5px;
}
.action-btn:hover {
    background: #0f172a;
    color: #ffffff;
    border-color: #0f172a;
    transform: translateY(-1px);
}
.action-btn-wa { background: #f0fdf4; color: #16a34a; border-color: #bbf7d0; }
.action-btn-wa:hover { background: #16a34a !important; color: #ffffff !important; border-color: #16a34a !important; }
.action-btn-promote { background: #0f172a; color: #ffffff; border-color: #0f172a; }
.action-btn-promote:hover { background: #1e293b !important; }
.action-btn-delete:hover { background: #dc2626 !important; color: #ffffff !important; border-color: #dc2626 !important; }
.action-btn-expand { border: none; background: transparent; font-size: 13px; color: #64748b; cursor: pointer; padding: 2px; display: inline-flex; align-items: center; justify-content: center; }
.action-btn-expand:hover { color: #0f172a; }

/* --- Expandable Inline Row Drawer --- */
.inline-detail-cell {
    background: #f8fafc;
    border-bottom: 2px solid #e2e8f0;
    padding: 18px 24px !important;
}
.inline-detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 16px;
}
.inline-detail-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 14px 16px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
}
.inline-card-title {
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    color: #64748b;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 6px;
}

/* --- Slide-Out Drawer --- */
.drawer-backdrop { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15,23,42,0.45); backdrop-filter: blur(3px); z-index: 9998; opacity: 0; pointer-events: none; transition: opacity 0.25s ease; }
.drawer-backdrop.active { opacity: 1; pointer-events: auto; }
.side-drawer { position: fixed; top: 0; right: 0; width: 520px; max-width: 92vw; height: 100vh; background: #ffffff; box-shadow: -10px 0 30px rgba(0,0,0,0.18); z-index: 9999; transform: translateX(100%); transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1); display: flex; flex-direction: column; }
.side-drawer.active { transform: translateX(0); }
.drawer-header { padding: 18px 22px; background: #0f172a; color: #ffffff; display: flex; justify-content: space-between; align-items: center; }
.drawer-header h3 { margin: 0; font-size: 16px; font-weight: 800; color: #ffffff; display: flex; align-items: center; gap: 8px; }
.drawer-close-btn { background: none; border: none; color: #ffffff; cursor: pointer; font-size: 20px; opacity: 0.8; transition: 0.15s; }
.drawer-close-btn:hover { opacity: 1; }
.drawer-body { padding: 20px 22px; flex: 1; overflow-y: auto; background: #ffffff; }
.drawer-footer { padding: 14px 22px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; gap: 10px; }
.drawer-section { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px; margin-bottom: 14px; }
.drawer-section-title { font-size: 11.5px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px; display: flex; align-items: center; gap: 6px; }

/* Modals */
.modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 99999; align-items: center; justify-content: center; backdrop-filter: blur(3px); }
.modal-content { background: #ffffff; width: 100%; max-width: 580px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 20px 40px rgba(0,0,0,0.2); overflow: hidden; }
.modal-header { padding: 18px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #f8fafc; }
.modal-header h3 { margin: 0; font-size: 16px; color: #0f172a; font-weight: 800; display: flex; align-items: center; gap: 8px; }
.modal-body { padding: 20px; max-height: 75vh; overflow-y: auto; }
.modal-footer { padding: 16px 20px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 10px; }

/* --- Ultra-Smooth Skeleton Loader & Animation --- */
@keyframes shimmer {
    0% { background-position: -200% 0; }
    100% { background-position: 200% 0; }
}
.skeleton-shimmer {
    background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 37%, #f1f5f9 63%);
    background-size: 400% 100%;
    animation: shimmer 1.4s ease infinite;
    border-radius: 5px;
    display: inline-block;
}
.skeleton-row td {
    padding: 8px 6px !important;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
}

/* --- Executive Pagination Deck Styling (Clean Single Line) --- */
.pagination-deck {
    display: flex !important;
    flex-direction: row !important;
    justify-content: space-between !important;
    align-items: center !important;
    flex-wrap: nowrap !important;
    gap: 12px !important;
    padding: 6px 14px !important;
    background: #ffffff !important;
    border: 1px solid #e2e8f0 !important;
    border-radius: 8px !important;
    margin-top: 10px !important;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04) !important;
    box-sizing: border-box !important;
    overflow-x: auto !important;
    scrollbar-width: thin !important;
}
.pagination-info {
    display: inline-flex !important;
    align-items: center !important;
    gap: 12px !important;
    font-size: 11.5px !important;
    font-weight: 600 !important;
    color: #475569 !important;
    white-space: nowrap !important;
    flex-shrink: 0 !important;
}
.pagination-info b {
    color: #0f172a !important;
    font-weight: 800 !important;
}
.page-size-selector {
    display: inline-flex !important;
    align-items: center !important;
    gap: 6px !important;
    font-size: 11px !important;
    color: #64748b !important;
    border-left: 1px solid #e2e8f0 !important;
    padding-left: 12px !important;
    white-space: nowrap !important;
    flex-shrink: 0 !important;
}
.page-size-selector span,
.page-size-selector label {
    margin: 0 !important;
    padding: 0 !important;
    font-size: 11px !important;
    font-weight: 600 !important;
    color: #64748b !important;
    white-space: nowrap !important;
    line-height: 1 !important;
    display: inline !important;
}
.page-size-selector select {
    height: 26px !important;
    padding: 0 6px !important;
    border: 1px solid #cbd5e1 !important;
    border-radius: 5px !important;
    background: #ffffff !important;
    color: #0f172a !important;
    font-size: 11px !important;
    font-weight: 700 !important;
    cursor: pointer !important;
    outline: none !important;
    transition: all 0.2s !important;
    box-sizing: border-box !important;
}
.page-size-selector select:focus {
    border-color: #0f172a !important;
}

.pagination-nav {
    display: inline-flex !important;
    align-items: center !important;
    gap: 3px !important;
    white-space: nowrap !important;
    flex-shrink: 0 !important;
}
.pag-btn {
    width: 26px !important;
    height: 26px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    border: 1px solid #cbd5e1 !important;
    border-radius: 5px !important;
    background: #ffffff !important;
    color: #0f172a !important;
    font-size: 11px !important;
    font-weight: 700 !important;
    cursor: pointer !important;
    transition: all 0.15s ease !important;
    padding: 0 !important;
    flex-shrink: 0 !important;
}
.pag-btn svg {
    width: 12px !important;
    height: 12px !important;
}
.pag-btn:hover:not(:disabled) {
    background: #0f172a !important;
    color: #ffffff !important;
    border-color: #0f172a !important;
    transform: translateY(-1px) !important;
}
.pag-btn:disabled {
    opacity: 0.3 !important;
    cursor: not-allowed !important;
    background: #f8fafc !important;
    border-color: #e2e8f0 !important;
}
.pag-numbers {
    display: inline-flex !important;
    align-items: center !important;
    gap: 3px !important;
}
.pag-num-btn {
    min-width: 26px !important;
    height: 26px !important;
    padding: 0 6px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    border: 1px solid #cbd5e1 !important;
    border-radius: 5px !important;
    background: #ffffff !important;
    color: #0f172a !important;
    font-size: 11px !important;
    font-weight: 700 !important;
    cursor: pointer !important;
    transition: all 0.15s ease !important;
    box-sizing: border-box !important;
}
.pag-num-btn:hover {
    background: #f1f5f9 !important;
    border-color: #0f172a !important;
}
.pag-num-btn.active {
    background: #0f172a !important;
    color: #ffffff !important;
    border-color: #0f172a !important;
    box-shadow: 0 2px 4px rgba(15, 23, 42, 0.2) !important;
}
.pag-ellipsis {
    padding: 0 3px !important;
    font-size: 11px !important;
    font-weight: 800 !important;
    color: #94a3b8 !important;
    line-height: 1 !important;
}
.pag-jump {
    display: inline-flex !important;
    align-items: center !important;
    gap: 4px !important;
    font-size: 11px !important;
    color: #64748b !important;
    margin-left: 6px !important;
    padding-left: 10px !important;
    border-left: 1px solid #e2e8f0 !important;
    white-space: nowrap !important;
    flex-shrink: 0 !important;
}
.pag-jump input {
    width: 36px !important;
    height: 26px !important;
    text-align: center !important;
    border: 1px solid #cbd5e1 !important;
    border-radius: 5px !important;
    font-size: 11px !important;
    font-weight: 700 !important;
    color: #0f172a !important;
    outline: none !important;
    background: #ffffff !important;
    transition: all 0.2s !important;
    box-sizing: border-box !important;
    padding: 0 2px !important;
}
.pag-jump input:focus {
    border-color: #0f172a !important;
    box-shadow: 0 0 0 2px rgba(15, 23, 42, 0.08) !important;
}

/* --- Executive Lead Pipeline View System (Zero Horizontal Scroll, 100% Fit) --- */
.kanban-view-wrapper {
    margin-top: 6px;
    margin-bottom: 20px;
    width: 100%;
    overflow-x: hidden;
}
.kanban-board-track {
    display: grid !important;
    grid-template-columns: repeat(6, minmax(0, 1fr)) !important;
    gap: 8px !important;
    width: 100% !important;
    box-sizing: border-box !important;
    align-items: flex-start !important;
    overflow-x: hidden !important;
    padding: 0 !important;
    margin: 0 !important;
}
.kanban-col {
    width: 100% !important;
    min-width: 0 !important;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 9px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
    display: flex;
    flex-direction: column;
    transition: all 0.2s ease;
    overflow: hidden;
}
.kanban-col.drag-over {
    background: #eff6ff !important;
    border-color: #2563eb !important;
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.25) !important;
}
.kanban-col-header {
    padding: 8px 8px 6px 8px;
    background: #ffffff;
    border-bottom: 1px solid #e2e8f0;
    border-top: 3.5px solid #0f172a;
    transition: all 0.2s;
}
.kanban-col-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 3px;
}
.kanban-col-title {
    font-size: 11px;
    font-weight: 800;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 5px;
    letter-spacing: -0.2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    min-width: 0;
}
.kanban-col-icon-wrap {
    width: 18px;
    height: 18px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.kanban-col-icon-wrap svg {
    width: 11px;
    height: 11px;
}
.kanban-col-count {
    font-size: 10px;
    font-weight: 800;
    padding: 1px 5px;
    border-radius: 4px;
    background: #0f172a;
    color: #ffffff;
    line-height: 1.3;
    flex-shrink: 0;
}
.kanban-col-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 9.5px;
    color: #64748b;
    padding-top: 1px;
}
.kanban-col-amount {
    font-weight: 800;
    color: #0f172a;
    font-family: 'Outfit', sans-serif;
    font-size: 10.5px;
}
.kanban-cards-list {
    padding: 6px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-height: 50px;
    max-height: calc(100vh - 270px);
    overflow-y: auto;
    scrollbar-width: thin;
}
.kanban-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 7px 8px;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    cursor: grab;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    position: relative;
    user-select: none;
    display: flex;
    flex-direction: column;
    gap: 5px;
}
.kanban-card:hover {
    border-color: #0f172a;
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(15, 23, 42, 0.08);
}
.kanban-card:active {
    cursor: grabbing;
}
.kanban-card.is-dragging {
    opacity: 0.35;
    transform: scale(0.97);
}
.kanban-card-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 4px;
}
.kanban-card-user {
    display: flex;
    align-items: center;
    gap: 5px;
    min-width: 0;
    flex: 1;
}
.kanban-card-avatar {
    width: 22px;
    height: 22px;
    border-radius: 5px;
    background: linear-gradient(135deg, #0f172a 0%, #334155 100%);
    color: #ffffff;
    font-weight: 800;
    font-size: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.kanban-card-avatar img {
    width: 100%;
    height: 100%;
    border-radius: 5px;
    object-fit: cover;
}
.kanban-card-name-box {
    min-width: 0;
    flex: 1;
}
.kanban-card-name {
    font-size: 11px;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    cursor: pointer;
}
.kanban-card-name:hover {
    text-decoration: underline;
}
.kanban-card-sub {
    font-size: 9.5px;
    font-weight: 600;
    color: #64748b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.1;
    margin-top: 1px;
}
.kanban-prio-pill {
    font-size: 8.5px;
    font-weight: 800;
    padding: 1px 4px;
    border-radius: 4px;
    white-space: nowrap;
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    gap: 2px;
}
.kanban-prio-hot {
    background: #fef2f2;
    color: #b91c1c;
    border: 1px solid #fca5a5;
}
.kanban-prio-warm {
    background: #fffbeb;
    color: #b45309;
    border: 1px solid #fde68a;
}
.kanban-prio-cold {
    background: #f8fafc;
    color: #475569;
    border: 1px solid #cbd5e1;
}
.kanban-card-meta-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 4px;
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    border-radius: 5px;
    padding: 2px 5px;
}
.kanban-product-tag {
    font-size: 9.5px;
    font-weight: 700;
    color: #1e293b;
    display: inline-flex;
    align-items: center;
    gap: 3px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 95px;
}
.kanban-product-tag svg {
    width: 9px;
    height: 9px;
    color: #64748b;
    flex-shrink: 0;
}
.kanban-amount-pill {
    font-size: 10px;
    font-weight: 800;
    color: #0f172a;
    font-family: 'Outfit', sans-serif;
    white-space: nowrap;
    flex-shrink: 0;
}
.kanban-partner-row {
    display: flex;
    align-items: center;
    font-size: 9.5px;
}
.kanban-contact-strip {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-bottom: 4px;
    border-bottom: 1px solid #f1f5f9;
}
.kanban-phone-num {
    font-size: 10px;
    font-weight: 700;
    color: #334155;
    display: inline-flex;
    align-items: center;
    gap: 3px;
    white-space: nowrap;
}
.kanban-phone-num svg {
    width: 9px;
    height: 9px;
    color: #64748b;
}
.kanban-quick-comm {
    display: flex;
    align-items: center;
    gap: 3px;
}
.kanban-comm-btn {
    width: 20px;
    height: 20px;
    border-radius: 4px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #0f172a;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    transition: all 0.15s;
}
.kanban-comm-btn svg {
    width: 10px;
    height: 10px;
}
.kanban-comm-btn:hover {
    background: #0f172a;
    color: #ffffff;
    border-color: #0f172a;
}
.kanban-comm-btn.wa {
    background: #f0fdf4;
    color: #16a34a;
    border-color: #bbf7d0;
}
.kanban-comm-btn.wa:hover {
    background: #16a34a;
    color: #ffffff;
    border-color: #16a34a;
}
.kanban-card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 3px;
}
.kanban-rm-tag {
    font-size: 9.5px;
    font-weight: 700;
    color: #475569;
    background: #f1f5f9;
    padding: 1px 4px;
    border-radius: 3px;
    display: inline-flex;
    align-items: center;
    gap: 3px;
    max-width: 60px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.kanban-rm-tag svg {
    width: 8.5px;
    height: 8.5px;
    color: #64748b;
}
.kanban-stage-quick-select {
    height: 22px;
    padding: 0 2px;
    font-size: 9.5px;
    font-weight: 700;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    background: #ffffff;
    color: #0f172a;
    cursor: pointer;
    outline: none;
    max-width: 70px;
}
.kanban-stage-quick-select:hover {
    border-color: #0f172a;
}
.kanban-empty-zone {
    border: 1px dashed #cbd5e1;
    background: #ffffff;
    border-radius: 6px;
    padding: 12px 4px;
    text-align: center;
    margin: 2px 0;
}
.kanban-empty-icon {
    width: 18px;
    height: 18px;
    margin: 0 auto 3px;
    color: #94a3b8;
    display: flex;
    align-items: center;
    justify-content: center;
}
.kanban-empty-icon svg {
    width: 14px;
    height: 14px;
}
.kanban-empty-title {
    font-size: 9.5px;
    font-weight: 700;
    color: #94a3b8;
}

/* Print styling */
.print-only { display: none !important; }

@page {
    size: landscape;
    margin: 8mm 8mm 8mm 8mm;
}

@media print {
    /* 1. Global Reset & Chrome Erasure */
    aside, nav, header, footer, .sidebar, .sidebar-menu, .app-sidebar, 
    .main-header, .top-bar, .page-header, .kpi-grid, .pipeline-funnel-bar, 
    .control-deck, #bulkActionBar, .pagination-deck, .pagination, .modal-overlay, 
    .side-drawer, .drawer-backdrop, .btn, .btn-primary, .btn-secondary, 
    .checkbox-col, .action-col, select, .no-print, button, .action-btn, 
    .action-btn-expand, .action-btn-wa, .partner-chat-link, .lead-avatar-img, 
    .lead-avatar-box, .rm-trigger-avatar, .rm-trigger-arrow, a[href^="https://wa.me"] {
        display: none !important;
    }

    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        box-sizing: border-box !important;
    }

    html, body {
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        background: #ffffff !important;
        color: #0f172a !important;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
        font-size: 8pt !important;
        line-height: 1.25 !important;
    }

    .main-content, #main-content, .content, .main-wrapper, #wrapper, .lm-layout {
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        border: none !important;
        box-shadow: none !important;
        background: transparent !important;
    }

    .print-only {
        display: block !important;
    }

    /* 2. Executive Corporate Letterhead */
    .print-header-container {
        border-bottom: 2px solid #0f172a;
        padding-bottom: 6px;
        margin-bottom: 7px;
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
    }
    .print-header-title {
        font-size: 15pt;
        font-weight: 900;
        text-transform: uppercase;
        color: #0f172a;
        letter-spacing: -0.02em;
        line-height: 1.1;
    }
    .print-header-sub {
        font-size: 8pt;
        font-weight: 700;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        margin-top: 2px;
    }
    .print-meta-info {
        text-align: right;
        font-size: 7.5pt;
        font-weight: 600;
        line-height: 1.35;
        color: #334155;
    }
    .print-scope-card {
        background: #0f172a !important;
        color: #ffffff !important;
        padding: 6px 10px;
        border-radius: 4px;
        font-size: 8.5pt;
        font-weight: 800;
        letter-spacing: 0.4px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border: 1px solid #0f172a;
        margin-bottom: 6px;
    }

    /* 3. Table Layout & Geometry (Guaranteed 100% Fit) */
    .table-container {
        width: 100% !important;
        max-width: 100% !important;
        overflow: visible !important;
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
        margin: 0 !important;
        background: transparent !important;
    }
    .lm-table {
        width: 100% !important;
        max-width: 100% !important;
        table-layout: fixed !important;
        border-collapse: collapse !important;
        border: 1px solid #cbd5e1 !important;
        background: #ffffff !important;
        margin: 0 !important;
    }
    .lm-table thead {
        display: table-header-group !important;
    }
    .lm-table tr {
        page-break-inside: avoid !important;
        height: auto !important;
    }
    .lm-table th {
        background: #0f172a !important;
        color: #ffffff !important;
        padding: 6px 6px !important;
        font-size: 7.5pt !important;
        font-weight: 800 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.3px !important;
        border: 1px solid #0f172a !important;
        text-align: left !important;
        white-space: normal !important;
        word-wrap: break-word !important;
    }
    .lm-table td {
        padding: 5px 6px !important;
        font-size: 7.5pt !important;
        color: #0f172a !important;
        border: 1px solid #e2e8f0 !important;
        vertical-align: top !important;
        line-height: 1.25 !important;
        word-wrap: break-word !important;
        overflow-wrap: break-word !important;
    }
    .lm-table tbody tr:nth-child(even) td {
        background-color: #f8fafc !important;
    }

    /* Fixed Column Width Distribution (Exact 100% of Printable Area) */
    .lm-table th.col-customer, .lm-table td.col-customer, .lm-table th:nth-child(2), .lm-table td:nth-child(2) { width: 16% !important; max-width: 16% !important; }
    .lm-table th.col-contact, .lm-table td.col-contact, .lm-table th:nth-child(3), .lm-table td:nth-child(3) { width: 16% !important; max-width: 16% !important; }
    .lm-table th.col-loan, .lm-table td.col-loan, .lm-table th:nth-child(4), .lm-table td:nth-child(4) { width: 15% !important; max-width: 15% !important; }
    .lm-table th.col-partner, .lm-table td.col-partner, .lm-table th:nth-child(5), .lm-table td:nth-child(5) { width: 16% !important; max-width: 16% !important; }
    .lm-table th.col-priority, .lm-table td.col-priority, .lm-table th:nth-child(6), .lm-table td:nth-child(6) { width: 8% !important; max-width: 8% !important; text-align: center !important; white-space: nowrap !important; }
    .lm-table th.col-stage, .lm-table td.col-stage, .lm-table th:nth-child(7), .lm-table td:nth-child(7) { width: 11% !important; max-width: 11% !important; text-align: center !important; }
    .lm-table th.col-date, .lm-table td.col-date, .lm-table th:nth-child(8), .lm-table td:nth-child(8) { width: 9% !important; max-width: 9% !important; text-align: center !important; }
    .lm-table th.col-rm, .lm-table td.col-rm, .lm-table th:nth-child(9), .lm-table td:nth-child(9) { width: 9% !important; max-width: 9% !important; }

    /* Print-Specific Formatting For Customer, Contact, Loan, and Sourcing Partner */
    .print-cust-name {
        font-size: 7.8pt !important;
        font-weight: 800 !important;
        color: #0f172a !important;
        line-height: 1.2 !important;
        word-break: break-word !important;
    }
    .print-cust-firm {
        font-size: 6.8pt !important;
        font-weight: 600 !important;
        color: #475569 !important;
        margin-top: 1px !important;
        line-height: 1.2 !important;
    }
    .print-cust-loc {
        font-size: 6.5pt !important;
        font-weight: 600 !important;
        color: #64748b !important;
        margin-top: 1px !important;
        line-height: 1.2 !important;
    }

    .print-contact-phone {
        font-size: 7.8pt !important;
        font-weight: 800 !important;
        color: #0f172a !important;
        line-height: 1.2 !important;
    }
    .print-contact-alt {
        font-size: 6.8pt !important;
        color: #475569 !important;
        font-weight: 600 !important;
        margin-top: 1px !important;
        line-height: 1.2 !important;
    }
    .print-contact-email {
        font-size: 6.8pt !important;
        color: #334155 !important;
        font-weight: 600 !important;
        word-break: break-all !important;
        max-width: none !important;
        overflow: visible !important;
        text-overflow: clip !important;
        white-space: normal !important;
        margin-top: 1px !important;
        line-height: 1.2 !important;
    }

    .print-loan-block {
        display: block !important;
        line-height: 1.25 !important;
    }
    .print-loan-req {
        font-size: 7.5pt !important;
        font-weight: 800 !important;
        color: #0f172a !important;
        word-break: break-word !important;
    }
    .print-loan-amt {
        font-size: 8pt !important;
        font-weight: 800 !important;
        color: #047857 !important;
        margin-top: 1px !important;
        font-family: 'Outfit', -apple-system, sans-serif !important;
        white-space: nowrap !important;
    }
    .print-loan-lakh {
        font-size: 6.8pt !important;
        font-weight: 700 !important;
        color: #475569 !important;
        margin-top: 1px !important;
        white-space: nowrap !important;
    }

    .print-source-block {
        display: block !important;
        line-height: 1.25 !important;
    }
    .print-source-name {
        font-size: 7.5pt !important;
        font-weight: 800 !important;
        color: #0f172a !important;
        word-break: break-word !important;
        line-height: 1.2 !important;
    }
    .print-channel-tag {
        display: inline-block !important;
        font-size: 6pt !important;
        font-weight: 800 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.3px !important;
        padding: 1px 4px !important;
        border-radius: 3px !important;
        margin-top: 2px !important;
        line-height: 1.1 !important;
        white-space: nowrap !important;
    }
    .print-tag-dsa {
        background: #f0f9ff !important;
        color: #0369a1 !important;
        border: 1px solid #bae6fd !important;
    }
    .print-tag-builder {
        background: #f0fdf4 !important;
        color: #15803d !important;
        border: 1px solid #bbf7d0 !important;
    }
    .print-tag-ca {
        background: #faf5ff !important;
        color: #6b21a8 !important;
        border: 1px solid #e9d5ff !important;
    }
    .print-tag-agent {
        background: #f8fafc !important;
        color: #334155 !important;
        border: 1px solid #cbd5e1 !important;
    }
    .print-tag-customer {
        background: #ecfdf5 !important;
        color: #166534 !important;
        border: 1px solid #86efac !important;
    }
    .print-tag-direct {
        background: #f8fafc !important;
        color: #475569 !important;
        border: 1px solid #cbd5e1 !important;
    }
    .print-tag-internal {
        background: #f8fafc !important;
        color: #334155 !important;
        border: 1px solid #cbd5e1 !important;
    }
    .print-source-meta {
        font-size: 6.5pt !important;
        color: #475569 !important;
        font-weight: 600 !important;
        margin-top: 2px !important;
        line-height: 1.2 !important;
    }
    .print-source-meta b {
        color: #0f172a !important;
    }

    /* Clean Printed Badges */
    .print-badge {
        display: inline-block !important;
        padding: 2px 5px !important;
        border-radius: 4px !important;
        font-size: 6.8pt !important;
        font-weight: 800 !important;
        text-align: center !important;
        white-space: nowrap !important;
    }
    .print-badge-hot {
        background: #fef2f2 !important;
        color: #b91c1c !important;
        border: 1px solid #fca5a5 !important;
    }
    .print-badge-warm {
        background: #fffbeb !important;
        color: #b45309 !important;
        border: 1px solid #fde68a !important;
    }
    .print-badge-cold {
        background: #f0fdf4 !important;
        color: #15803d !important;
        border: 1px solid #86efac !important;
    }
    .print-badge-stage {
        background: #f1f5f9 !important;
        color: #0f172a !important;
        border: 1px solid #cbd5e1 !important;
    }
    .print-badge-converted {
        background: #ecfdf5 !important;
        color: #047857 !important;
        border: 1px solid #6ee7b7 !important;
    }

    /* Print Footer */
    .print-footer-container {
        margin-top: 8px;
        padding-top: 5px;
        border-top: 1px solid #cbd5e1;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 7pt;
        color: #64748b;
        font-weight: 600;
    }
}
</style>

<div class="lm-layout">

    <!-- Executive Print Letterhead (Hidden on Screen) -->
    <div id="printReportHeader" class="print-only" style="margin-bottom: 6px;">
        <div class="print-header-container">
            <div>
                <div class="print-header-title">BFS FINANCIAL SERVICES</div>
                <div class="print-header-sub">LEAD PIPELINE &amp; CONVERSION EXECUTIVE REPORT</div>
            </div>
            <div class="print-meta-info">
                <div>Print Date: <strong id="printDateText">-</strong></div>
                <div>Active Filters: <strong id="printFilterSummary">All Stages | All Staff</strong></div>
                <div>Records Printed: <strong id="printRecordCount">0</strong></div>
            </div>
        </div>
        <div class="print-scope-card">
            <span>📋 REPORT SCOPE: <span id="printGeneratedForText" style="text-decoration:underline; font-size:9.5pt; text-transform:uppercase;">LEAD PIPELINE</span></span>
            <span style="font-size:8pt; font-weight:700; opacity:0.95;">ISSUED BY: <?php echo strtoupper(htmlspecialchars($_SESSION['username'] ?? 'Admin')); ?> • CONFIDENTIAL MIS REPORT</span>
        </div>
    </div>

    <!-- 1. Top Executive KPI Metric Tiles -->
    <div class="kpi-grid no-print">
        <div class="kpi-card">
            <div>
                <div class="kpi-val" id="lead-stat-total">0</div>
                <div class="kpi-lbl">Total Prospects</div>
            </div>
            <div class="kpi-icon"><i data-lucide="target"></i></div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-val" id="lead-stat-today">0</div>
                <div class="kpi-lbl">Today's Fresh Leads</div>
            </div>
            <div class="kpi-icon"><i data-lucide="calendar-plus"></i></div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-val" id="lead-stat-hot">0</div>
                <div class="kpi-lbl">Hot Priority Leads</div>
            </div>
            <div class="kpi-icon" style="background:#fef2f2; color:#dc2626; border-color:#fecaca;"><i data-lucide="flame"></i></div>
        </div>
        <div class="kpi-card">
            <div>
                <div class="kpi-val" id="lead-stat-progress">0</div>
                <div class="kpi-lbl">Active Funnel Pipeline</div>
            </div>
            <div class="kpi-icon"><i data-lucide="trending-up"></i></div>
        </div>
    </div>


    <!-- 3. Consolidated Executive Control Deck (Toolbar + Filters) -->
    <div class="control-deck no-print">
        <div class="top-actions-row">
            <!-- Left: View Mode Toggle (Logical Segmented Capsule) -->
            <div class="view-switcher-group">
                <button type="button" class="view-mode-btn active" id="btnViewTable" onclick="switchLeadView('table')">
                    <i data-lucide="layout-list" style="width:13.5px;height:13.5px;"></i>
                    <span>Table View</span>
                </button>
                <button type="button" class="view-mode-btn" id="btnViewKanban" onclick="switchLeadView('kanban')" title="Visual Pipeline Flow">
                    <i data-lucide="columns-3" style="width:13.5px;height:13.5px;"></i>
                    <span>Pipeline View</span>
                </button>
            </div>

            <!-- Right: Action Buttons (Logical Utilities + Hero CTA) -->
            <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                <button type="button" class="btn-toolbar-utility" onclick="openPrintDeskModal()" title="Print Executive Leads Report">
                    <i data-lucide="printer" style="width:13.5px;height:13.5px;"></i>
                    <span>Print Desk</span>
                </button>
                <button type="button" class="btn-toolbar-utility" onclick="openPrintAuditHistoryModal()" title="View Print Audit History">
                    <i data-lucide="history" style="width:13.5px;height:13.5px;"></i>
                    <span>Print Audit</span>
                </button>
                <?php if ($is_admin): ?>
                <button type="button" class="btn-toolbar-utility" onclick="openLeadsBulkUploadModal()" title="Import Leads from CSV/Excel">
                    <i data-lucide="file-up" style="width:13.5px;height:13.5px;"></i>
                    <span>Import Leads</span>
                </button>
                <?php endif; ?>
                <a href="add_lead.php" class="btn-hero-primary" title="Add New Lead Prospect">
                    <i data-lucide="plus-circle" style="width:14px;height:14px;"></i>
                    <span>New Lead Entry</span>
                </a>
            </div>
        </div>

        <div class="filters-row">
            <div class="search-wrapper">
                <i data-lucide="search"></i>
                <input type="text" id="lead-search" placeholder="Search Prospect Name, Mobile, Partner, Loan..." oninput="debouncedLoadLeads(1)">
            </div>
            
            <select id="lead-filter-date" class="filter-select" onchange="loadLeads()">
                <option value="">All Time</option>
                <option value="today">Today</option>
                <option value="yesterday">Yesterday</option>
                <option value="last7">Last 7 Days</option>
                <option value="this_month">This Month</option>
            </select>
            
            <select id="lead-filter-type" class="filter-select" onchange="loadLeads()">
                <option value="">All Loan Products</option>
                <option value="Personal Loan">Personal Loan</option>
                <option value="Home Loan">Home Loan</option>
                <option value="Business Loan">Business Loan</option>
                <option value="Auto Loan">Auto Loan</option>
                <option value="Mortgage Loan">Mortgage Loan / LAP</option>
            </select>

            <select id="lead-filter-stage" class="filter-select" onchange="loadLeads(1)">
                <option value="">All Stages</option>
                <option value="New Lead">New Lead</option>
                <option value="Contacted">Contacted</option>
                <option value="Scheduled">Meeting Scheduled</option>
                <option value="Interested">Qualified / Hot</option>
                <option value="Converted">Converted to Client</option>
                <option value="Rejected">Disqualified</option>
            </select>
            
            <select id="lead-filter-priority" class="filter-select" onchange="loadLeads()">
                <option value="">All Priorities</option>
                <option value="Hot">🔥 Hot</option>
                <option value="Warm">⚡ Warm</option>
                <option value="Cold">❄️ Cold</option>
            </select>
            
            <?php if ($is_admin): ?>
            <select id="lead-filter-staff" class="filter-select" onchange="loadLeads()">
                <option value="">All Staff / RMs</option>
                <?php foreach ($staff_members as $sm): ?>
                    <option value="<?php echo htmlspecialchars($sm['username']); ?>"><?php echo htmlspecialchars($sm['full_name'] ?: $sm['username']); ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>

            <button class="btn-secondary btn-reset-filters" onclick="resetLeadFilters()" title="Reset all filters">
                <i data-lucide="refresh-cw" style="width:13px;"></i> Reset
            </button>

            <div id="leadsResultCount">
                <!-- Result Count -->
            </div>
        </div>
    </div>

    <!-- 4. Dynamic Floating / Integrated Bulk Operations Bar -->
    <div id="bulkActionBar" class="no-print">
        <div style="font-size:13px; font-weight:800; display:flex; align-items:center; gap:8px;">
            <i data-lucide="check-square" style="width:16px;"></i>
            <span><span id="checkedCountBadge">0</span> Selected</span>
        </div>
        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
            <?php if ($is_admin): ?>
            <div style="position:relative; display:inline-flex; align-items:center;">
                <input type="hidden" id="bulk-assign-leads-staff" value="">
                <button type="button" id="bulk-assign-rm-trigger" class="rm-assign-trigger-btn is-unassigned" style="height:30px; background:#fff; min-width:160px; max-width:220px;" onclick="openBulkRmSearchPopover(this)">
                    <span class="rm-trigger-avatar unassigned"><i data-lucide="user-plus" style="width:11px;height:11px;"></i></span>
                    <span class="rm-trigger-label" id="bulk-assign-rm-label">Choose RM to Assign</span>
                    <i data-lucide="chevron-down" class="rm-trigger-arrow"></i>
                </button>
            </div>
            <button type="button" onclick="bulkAssign('leads')" class="btn-secondary" style="padding:5px 12px; font-size:12px; background:#fff; color:#0f172a; height:30px;">Assign RM</button>
            <?php endif; ?>

            <select id="bulk-change-stage-select" class="table-dropdown-select" style="background:#ffffff; color:#0f172a; width:160px;">
                <option value="">-- Change Stage --</option>
                <option value="New Lead">New Lead</option>
                <option value="Contacted">Contacted</option>
                <option value="Scheduled">Scheduled</option>
                <option value="Interested">Qualified / Hot</option>
                <option value="Converted">Converted</option>
                <option value="Rejected">Disqualified</option>
            </select>
            <button type="button" onclick="bulkChangeStage()" class="btn-secondary" style="padding:5px 12px; font-size:12px; background:#fff; color:#0f172a;">Update Stage</button>

            <?php if ($is_admin): ?>
            <button type="button" onclick="bulkDelete('leads')" class="btn-secondary" style="color:#ef4444; border-color:#f87171; background:#fff; padding:5px 12px; font-size:12px;"><i data-lucide="trash-2" style="width:13px;"></i> Delete</button>
            <?php endif; ?>

            <button type="button" onclick="clearLeadSelection()" class="btn-secondary" style="background:transparent; color:#94a3b8; border-color:transparent; padding:5px 8px; font-size:12px;">Cancel</button>
        </div>
    </div>

    <!-- 5. Table View Container -->
    <div id="tableViewContainer">
        <div class="table-container">
            <table class="lm-table">
                <thead>
                    <tr>
                        <th style="width:34px; text-align:center; padding:8px 4px;" class="checkbox-col no-print">
                            <input type="checkbox" id="selectAllLeads" onclick="toggleSelectAll('leads')" style="cursor:pointer; accent-color:#0f172a; margin:0;">
                        </th>
                        <th class="col-customer">Customer / Prospect</th>
                        <th class="col-contact">Contact & Connect</th>
                        <th class="col-loan">Loan & Amount</th>
                        <th class="col-partner">Sourcing Partner</th>
                        <th class="col-priority">Priority</th>
                        <th class="col-stage">Pipeline Stage</th>
                        <th class="col-date">Date & Reminder</th>
                        <th class="col-rm">Assigned RM</th>
                        <th style="text-align:right;" class="action-col no-print">Actions</th>
                    </tr>
                </thead>
                <tbody id="leads-table-body">
                    <tr><td colspan="10" style="text-align:center; padding:50px; color:#64748b; font-weight:600;"><i data-lucide="loader" class="spin"></i> Loading lead pipeline...</td></tr>
                </tbody>
            </table>
        </div>

        <!-- Executive Print Footer (Hidden on screen, prints at bottom) -->
        <div id="printReportFooter" class="print-only print-footer-container">
            <span>BFS Financial Services MIS • Lead Pipeline Management &amp; Conversion System</span>
            <span id="printFooterPageInfo">Confidential • For Internal Distribution Only</span>
        </div>

        <!-- 5.1 Professional Pagination & Page Management Deck -->
        <div id="leads-pagination-deck" class="pagination-deck no-print">
            <div class="pagination-info">
                <span id="paginationShowingText">Showing <b>0</b> to <b>0</b> of <b>0</b> prospects</span>
                <div class="page-size-selector">
                    <span>Per page:</span>
                    <select id="leadsPerPageSelect" onchange="changeLeadsPerPage(this.value)">
                        <option value="10">10</option>
                        <option value="15" selected>15</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="all">All</option>
                    </select>
                </div>
            </div>

            <div class="pagination-nav">
                <button type="button" class="pag-btn" id="pagBtnFirst" onclick="goToLeadsPage(1)" title="First Page"><i data-lucide="chevrons-left"></i></button>
                <button type="button" class="pag-btn" id="pagBtnPrev" onclick="goToLeadsPage(currentLeadsPage - 1)" title="Previous Page"><i data-lucide="chevron-left"></i></button>
                <div class="pag-numbers" id="pagNumbersContainer"></div>
                <button type="button" class="pag-btn" id="pagBtnNext" onclick="goToLeadsPage(currentLeadsPage + 1)" title="Next Page"><i data-lucide="chevron-right"></i></button>
                <button type="button" class="pag-btn" id="pagBtnLast" onclick="goToLeadsPage(totalLeadPages)" title="Last Page"><i data-lucide="chevrons-right"></i></button>
                
                <div class="pag-jump">
                    <span>Page</span>
                    <input type="number" id="pagJumpInput" min="1" value="1" placeholder="1" onkeydown="if(event.key==='Enter') jumpToLeadsPage(this.value)" title="Press Enter to jump">
                    <span>of <b id="pagTotalPagesText">1</b></span>
                </div>
            </div>
        </div>
    </div>

    <!-- 6. Visual Kanban View Container -->
    <div id="kanbanViewContainer" class="no-print kanban-view-wrapper" style="display:none;">
        <div class="kanban-board-track" id="kanbanGrid">
            <!-- Dynamic Kanban Columns rendered via JS -->
        </div>
    </div>

</div>

<!-- 7. Slide-Out 360 Detail Drawer -->
<div class="drawer-backdrop" id="drawerBackdrop" onclick="closeLeadDetailDrawer()"></div>
<div class="side-drawer" id="leadDetailDrawer">
    <div class="drawer-header">
        <div>
            <h3 id="drawerLeadTitle"><i data-lucide="user-check"></i> Lead Details</h3>
            <div id="drawerLeadSubtitle" style="font-size:11.5px; color:#94a3b8; font-weight:600; margin-top:2px;">-</div>
        </div>
        <button class="drawer-close-btn" onclick="closeLeadDetailDrawer()"><i data-lucide="x"></i></button>
    </div>
    
    <div class="drawer-body" id="drawerLeadBody">
        <div style="text-align:center; padding:50px 20px; color:#64748b;">Loading Lead details...</div>
    </div>

    <div class="drawer-footer" id="drawerLeadFooter">
        <button class="btn-secondary" onclick="closeLeadDetailDrawer()">Close</button>
        <div style="display:flex; gap:8px;" id="drawerFooterActions"></div>
    </div>
</div>

<!-- 8. Modal: Executive Print Desk -->
<div id="printOptionsModal" class="modal-overlay no-print">
    <div class="modal-content" style="max-width:520px;">
        <div class="modal-header">
            <h3><i data-lucide="printer"></i> Executive Lead Report Generator</h3>
            <button onclick="document.getElementById('printOptionsModal').style.display='none'" style="background:none;border:none;cursor:pointer;color:#0f172a;"><i data-lucide="x"></i></button>
        </div>
        <div class="modal-body">
            <div style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:8px; padding:12px; margin-bottom:16px;">
                <div style="font-size:12px; font-weight:800; color:#0f172a; margin-bottom:8px;">Report Audit Metadata</div>
                <div style="margin-bottom:8px;">
                    <label style="font-size:11px; font-weight:700; color:#0f172a; display:block; margin-bottom:3px;">Issued To (Recipient Name / Desk) *</label>
                    <input type="text" id="printForInput" class="filter-input" style="width:100%; box-sizing:border-box; background:#fff;" placeholder="e.g. Sales Team, Branch Desk" required>
                </div>
                <div>
                    <label style="font-size:11px; font-weight:700; color:#0f172a; display:block; margin-bottom:3px;">Campaign / Purpose Note</label>
                    <input type="text" id="printPurposeInput" class="filter-input" style="width:100%; box-sizing:border-box; background:#fff;" placeholder="Lead Conversion Report" value="Lead Conversion Executive Sheet">
                </div>
            </div>

            <div style="font-size:12px; font-weight:700; color:#0f172a; margin-bottom:8px;">Select Scope:</div>
            <div style="display:flex; flex-direction:column; gap:10px; margin-bottom:16px;">
                <label style="border:1px solid #cbd5e1; border-radius:8px; padding:10px 14px; display:flex; align-items:center; gap:12px; cursor:pointer; background:#f8fafc;" id="lblCheckedScope">
                    <input type="radio" name="print_scope" value="checked" id="scopeChecked" onchange="updatePrintScopeUI()">
                    <div>
                        <div style="font-weight:700; color:#0f172a; font-size:13px;">Selected Checkboxes Only</div>
                        <div style="font-size:11.5px; color:#64748b;" id="checkedCountNotice">Print checked rows</div>
                    </div>
                </label>
                <label style="border:1px solid #cbd5e1; border-radius:8px; padding:10px 14px; display:flex; align-items:center; gap:12px; cursor:pointer; background:#ffffff;" id="lblPageScope">
                    <input type="radio" name="print_scope" value="page" checked id="scopePage" onchange="updatePrintScopeUI()">
                    <div>
                        <div style="font-weight:700; color:#0f172a; font-size:13px;">Current View Page</div>
                        <div style="font-size:11.5px; color:#64748b;">Print records displayed on page</div>
                    </div>
                </label>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="document.getElementById('printOptionsModal').style.display='none'">Cancel</button>
            <button type="button" class="btn-primary" onclick="executeSelectivePrint()"><i data-lucide="printer"></i> Generate Print Report</button>
        </div>
</div>
</div>

<!-- ==========================================================================
     GLOBAL SEARCHABLE RM ASSIGNMENT POPOVER
     ========================================================================== -->
<div id="rmSearchPopover" class="rm-search-popover-container no-print" style="display:none;" onclick="event.stopPropagation()">
    <!-- Header with Search Input -->
    <div class="rm-popover-header">
        <div class="rm-popover-search-wrap">
            <i data-lucide="search" class="rm-popover-search-icon"></i>
            <input type="text" id="rmPopoverSearchInput" class="rm-popover-search-input" placeholder="Search relationship manager..." autocomplete="off" spellcheck="false">
            <button type="button" id="rmPopoverClearBtn" class="rm-popover-clear-btn" style="display:none;" onclick="clearRmSearch(event)">&times;</button>
        </div>
        <div class="rm-popover-meta">
            <span id="rmPopoverCount" class="rm-popover-count">Select officer</span>
            <span class="rm-popover-hint"><kbd>ESC</kbd> to close</span>
        </div>
    </div>
    
    <!-- Scrollable Items List -->
    <div class="rm-popover-list custom-scrollbar" id="rmPopoverList">
        <!-- Rendered dynamically via JavaScript -->
    </div>
</div>

<script>
let currentLeadsPage = 1;
let leadsPerPage = localStorage.getItem('bfs_leads_per_page') === 'all' ? 'all' : (parseInt(localStorage.getItem('bfs_leads_per_page') || '15', 10) || 15);
let totalLeadPages = 1;
let leadSearchTimeout = null;
let currentViewMode = 'table';
let windowAllLeads = [];
let activeDrawerLeadId = null;
let expandedRowIds = new Set();
let cachedStageCounts = {};

const STAGES_CONFIG = [
    { key: 'New Lead',   label: 'New Lead',           icon: '📋' },
    { key: 'Contacted',  label: 'Contacted',          icon: '📞' },
    { key: 'Scheduled',  label: 'Meeting Scheduled',  icon: '📅' },
    { key: 'Interested', label: 'Qualified / Hot',    icon: '⭐' },
    { key: 'Converted',  label: 'Converted',          icon: '🚀' },
    { key: 'Rejected',   label: 'Disqualified',       icon: '❌' }
];

function switchLeadView(mode) {
    currentViewMode = mode;
    document.getElementById('btnViewTable').classList.toggle('active', mode === 'table');
    document.getElementById('btnViewKanban').classList.toggle('active', mode === 'kanban');

    if (mode === 'kanban') {
        document.getElementById('tableViewContainer').style.display = 'none';
        document.getElementById('kanbanViewContainer').style.display = 'block';
        loadKanbanBoard();
    } else {
        document.getElementById('tableViewContainer').style.display = 'block';
        document.getElementById('kanbanViewContainer').style.display = 'none';
        loadLeads(currentLeadsPage);
    }
}

function toggleSelectAll(type) {
    const isChecked = document.getElementById('selectAllLeads').checked;
    document.querySelectorAll('.lead-checkbox').forEach(cb => cb.checked = isChecked);
    updateCheckedCount();
}

function updateCheckedCount() {
    const checkedBoxes = document.querySelectorAll('.lead-checkbox:checked');
    const count = checkedBoxes.length;
    const badge = document.getElementById('checkedCountBadge');
    if (badge) badge.innerText = count;
    const bulkBar = document.getElementById('bulkActionBar');
    if (bulkBar) {
        if (count > 0) {
            bulkBar.style.display = 'flex';
        } else {
            bulkBar.style.display = 'none';
            const selectAll = document.getElementById('selectAllLeads');
            if (selectAll) selectAll.checked = false;
        }
    }
}

function clearLeadSelection() {
    document.querySelectorAll('.lead-checkbox').forEach(cb => cb.checked = false);
    updateCheckedCount();
}

function formatLakhs(amt) {
    const num = Number(amt);
    if (!num || isNaN(num)) return '';
    if (num >= 10000000) return `(₹ ${(num / 10000000).toFixed(2)} Cr)`;
    if (num >= 100000) return `(₹ ${(num / 100000).toFixed(2)} L)`;
    return '';
}



function debouncedLoadLeads(page = 1) {
    clearTimeout(leadSearchTimeout);
    leadSearchTimeout = setTimeout(() => {
        loadLeads(page);
    }, 250);
}

/* --- Ultra-Smooth Shimmer Skeleton Loader --- */
function renderSkeletonLoader(count = 10) {
    const tbody = document.getElementById('leads-table-body');
    if (!tbody) return;

    const countLabel = document.getElementById('leadsResultCount');
    if (countLabel) countLabel.innerHTML = `<span style="display:inline-flex; align-items:center; gap:5px; color:#64748b;"><i data-lucide="loader" class="spin" style="width:12px;height:12px;"></i> Loading prospects...</span>`;

    const showingText = document.getElementById('paginationShowingText');
    if (showingText) showingText.innerHTML = `Loading lead records...`;

    let html = '';
    const safeCount = Math.max(5, Math.min(count || 10, 15));
    for (let i = 0; i < safeCount; i++) {
        html += `
        <tr class="skeleton-row">
            <td style="text-align:center; padding:7px 4px;">
                <div class="skeleton-shimmer" style="width:16px; height:16px; border-radius:4px;"></div>
            </td>
            <td>
                <div style="display:flex; align-items:center; gap:7px;">
                    <div class="skeleton-shimmer" style="width:30px; height:30px; border-radius:7px; flex-shrink:0;"></div>
                    <div style="flex:1;">
                        <div class="skeleton-shimmer" style="width:${70 + (i % 3) * 25}px; height:12px; margin-bottom:4px;"></div>
                        <div class="skeleton-shimmer" style="width:55px; height:9px;"></div>
                    </div>
                </div>
            </td>
            <td>
                <div class="skeleton-shimmer" style="width:85px; height:12px; margin-bottom:4px;"></div>
                <div class="skeleton-shimmer" style="width:65px; height:9px;"></div>
            </td>
            <td>
                <div class="skeleton-shimmer" style="width:75px; height:12px; margin-bottom:4px;"></div>
                <div class="skeleton-shimmer" style="width:60px; height:11px; border-radius:4px;"></div>
            </td>
            <td>
                <div class="skeleton-shimmer" style="width:75px; height:18px; border-radius:5px;"></div>
            </td>
            <td>
                <div class="skeleton-shimmer" style="width:76px; height:26px; border-radius:6px;"></div>
            </td>
            <td>
                <div class="skeleton-shimmer" style="width:102px; height:26px; border-radius:6px;"></div>
            </td>
            <td>
                <div class="skeleton-shimmer" style="width:70px; height:12px; margin-bottom:4px;"></div>
                <div class="skeleton-shimmer" style="width:50px; height:10px; border-radius:4px;"></div>
            </td>
            <td>
                <div class="skeleton-shimmer" style="width:95px; height:26px; border-radius:6px;"></div>
            </td>
            <td style="text-align:right; padding:7px 4px;">
                <div style="display:inline-flex; align-items:center; gap:2px; justify-content:flex-end;">
                    <div class="skeleton-shimmer" style="width:24px; height:24px; border-radius:5px;"></div>
                    <div class="skeleton-shimmer" style="width:24px; height:24px; border-radius:5px;"></div>
                    <div class="skeleton-shimmer" style="width:24px; height:24px; border-radius:5px;"></div>
                    <div class="skeleton-shimmer" style="width:24px; height:24px; border-radius:5px;"></div>
                    ${window.isAdminUser ? '<div class="skeleton-shimmer" style="width:24px; height:24px; border-radius:5px;"></div>' : ''}
                </div>
            </td>
        </tr>`;
    }
    tbody.innerHTML = html;
    lucide.createIcons();
}

/* --- Professional Page Management & Pagination Engine --- */
function renderCurrentPage() {
    const total = windowAllLeads.length;
    const isAll = (leadsPerPage === 'all');
    const perPage = isAll ? (total || 1) : leadsPerPage;
    totalLeadPages = isAll ? 1 : Math.max(1, Math.ceil(total / perPage));

    if (currentLeadsPage > totalLeadPages) currentLeadsPage = totalLeadPages;
    if (currentLeadsPage < 1) currentLeadsPage = 1;

    const startIdx = total === 0 ? 0 : (currentLeadsPage - 1) * perPage;
    const endIdx = isAll ? total : Math.min(startIdx + perPage, total);
    const pageLeads = isAll ? windowAllLeads : windowAllLeads.slice(startIdx, endIdx);

    renderTableRows(pageLeads);
    renderPaginationUI(total, totalLeadPages, startIdx, endIdx);
    lucide.createIcons();
    updateCheckedCount();
}

function renderPaginationUI(total, totalPages, startIdx, endIdx) {
    const showingText = document.getElementById('paginationShowingText');
    const countLabel = document.getElementById('leadsResultCount');
    const pagesText = document.getElementById('pagTotalPagesText');
    const jumpInput = document.getElementById('pagJumpInput');
    const btnFirst = document.getElementById('pagBtnFirst');
    const btnPrev = document.getElementById('pagBtnPrev');
    const btnNext = document.getElementById('pagBtnNext');
    const btnLast = document.getElementById('pagBtnLast');
    const numbersContainer = document.getElementById('pagNumbersContainer');
    const perPageSelect = document.getElementById('leadsPerPageSelect');

    if (perPageSelect) {
        perPageSelect.value = String(leadsPerPage);
    }

    if (showingText) {
        if (total === 0) {
            showingText.innerHTML = `Showing <b>0</b> of <b>0</b> prospects`;
        } else {
            showingText.innerHTML = `Showing <b>${startIdx + 1}</b>–<b>${endIdx}</b> of <b>${total}</b> prospects`;
        }
    }

    if (countLabel) {
        countLabel.innerHTML = `Showing <b>${total}</b> Prospect${total === 1 ? '' : 's'}`;
    }

    if (pagesText) pagesText.innerText = totalPages;
    if (jumpInput) {
        jumpInput.value = currentLeadsPage;
        jumpInput.max = totalPages;
    }

    if (btnFirst) btnFirst.disabled = (currentLeadsPage <= 1);
    if (btnPrev) btnPrev.disabled = (currentLeadsPage <= 1);
    if (btnNext) btnNext.disabled = (currentLeadsPage >= totalPages);
    if (btnLast) btnLast.disabled = (currentLeadsPage >= totalPages);

    if (numbersContainer) {
        if (totalPages <= 1) {
            numbersContainer.innerHTML = '';
            return;
        }

        let html = '';
        // Always show page 1
        html += `<button type="button" class="pag-num-btn ${currentLeadsPage === 1 ? 'active' : ''}" onclick="goToLeadsPage(1)">1</button>`;

        // Left ellipsis
        if (currentLeadsPage > 3) {
            html += `<span class="pag-ellipsis">…</span>`;
        }

        // Window of numbers around current page
        const startPage = Math.max(2, currentLeadsPage - 1);
        const endPage = Math.min(totalPages - 1, currentLeadsPage + 1);

        for (let p = startPage; p <= endPage; p++) {
            html += `<button type="button" class="pag-num-btn ${p === currentLeadsPage ? 'active' : ''}" onclick="goToLeadsPage(${p})">${p}</button>`;
        }

        // Right ellipsis
        if (currentLeadsPage < totalPages - 2) {
            html += `<span class="pag-ellipsis">…</span>`;
        }

        // Always show last page if > 1
        if (totalPages > 1) {
            html += `<button type="button" class="pag-num-btn ${currentLeadsPage === totalPages ? 'active' : ''}" onclick="goToLeadsPage(${totalPages})">${totalPages}</button>`;
        }

        numbersContainer.innerHTML = html;
    }
}

function goToLeadsPage(page) {
    if (page < 1) page = 1;
    if (page > totalLeadPages) page = totalLeadPages;
    if (page === currentLeadsPage && windowAllLeads.length > 0) {
        document.querySelector('.table-container')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        return;
    }
    currentLeadsPage = page;
    const selectAll = document.getElementById('selectAllLeads');
    if (selectAll) selectAll.checked = false;
    renderCurrentPage();
    document.querySelector('.table-container')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function changeLeadsPerPage(val) {
    leadsPerPage = (val === 'all') ? 'all' : (parseInt(val, 10) || 15);
    localStorage.setItem('bfs_leads_per_page', String(val));
    currentLeadsPage = 1;
    const selectAll = document.getElementById('selectAllLeads');
    if (selectAll) selectAll.checked = false;
    renderCurrentPage();
}

function jumpToLeadsPage(val) {
    const p = parseInt(val, 10);
    if (!isNaN(p) && p >= 1 && p <= totalLeadPages) {
        goToLeadsPage(p);
    } else {
        const jumpInput = document.getElementById('pagJumpInput');
        if (jumpInput) jumpInput.value = currentLeadsPage;
    }
}

async function loadLeads(page = 1) {
    if (currentViewMode === 'kanban') {
        loadKanbanBoard();
        const search   = document.getElementById('lead-search')?.value || '';
        const stage    = document.getElementById('lead-filter-stage')?.value || '';
        const priority = document.getElementById('lead-filter-priority')?.value || '';
        const assigned = document.getElementById('lead-filter-staff')?.value || '';
        const type     = document.getElementById('lead-filter-type')?.value || '';
        const date     = document.getElementById('lead-filter-date')?.value || '';
        const params   = new URLSearchParams({ api: 'get_leads', search, stage, priority, assigned_to: assigned, loan_type: type, date_range: date });
        fetch('api.php?' + params.toString())
        .then(r => r.json())
        .then(resData => {
            const stats = resData.stats || {};
            const leads = resData.leads ? resData.leads : (Array.isArray(resData) ? resData : []);
            windowAllLeads = leads;
            document.getElementById('lead-stat-total').innerText = stats.total !== undefined ? stats.total : leads.length;
            document.getElementById('lead-stat-today').innerText = stats.today !== undefined ? stats.today : 0;
            document.getElementById('lead-stat-hot').innerText = stats.hot !== undefined ? stats.hot : leads.filter(l => l.priority === 'Hot').length;
            document.getElementById('lead-stat-progress').innerText = stats.progress !== undefined ? stats.progress : leads.filter(l => l.stage !== 'Converted' && l.stage !== 'Rejected').length;
        }).catch(()=>{});
        return;
    }

    currentLeadsPage = page;
    renderSkeletonLoader(leadsPerPage === 'all' ? 10 : Math.min(leadsPerPage, 12));

    const search   = document.getElementById('lead-search')?.value || '';
    const stage    = document.getElementById('lead-filter-stage')?.value || '';
    const priority = document.getElementById('lead-filter-priority')?.value || '';
    const assigned = document.getElementById('lead-filter-staff')?.value || '';
    const type     = document.getElementById('lead-filter-type')?.value || '';
    const date     = document.getElementById('lead-filter-date')?.value || '';
    const params   = new URLSearchParams({ api: 'get_leads', search, stage, priority, assigned_to: assigned, loan_type: type, date_range: date });

    try {
        const res = await fetch('api.php?' + params.toString());
        if (res.status === 401) { window.location.href = 'login.php'; return; }
        const resData = await res.json();
        
        const leads = resData.leads ? resData.leads : (Array.isArray(resData) ? resData : []);
        windowAllLeads = leads;
        const stats = resData.stats || {};
        cachedStageCounts = resData.stage_counts || {};

        // Update KPI Cards
        document.getElementById('lead-stat-total').innerText = stats.total !== undefined ? stats.total : leads.length;
        document.getElementById('lead-stat-today').innerText = stats.today !== undefined ? stats.today : 0;
        document.getElementById('lead-stat-hot').innerText = stats.hot !== undefined ? stats.hot : leads.filter(l => l.priority === 'Hot').length;
        document.getElementById('lead-stat-progress').innerText = stats.progress !== undefined ? stats.progress : leads.filter(l => l.stage !== 'Converted' && l.stage !== 'Rejected').length;

        renderCurrentPage();
    } catch(err) {
        console.warn('loadLeads error:', err);
        const tbody = document.getElementById('leads-table-body');
        if (tbody) tbody.innerHTML = `<tr><td colspan="10" style="padding:40px; text-align:center; color:#dc2626; font-weight:700;"><i data-lucide="alert-triangle" style="width:24px;height:24px;display:block;margin:0 auto 6px;"></i> Failed to load leads pipeline. Please refresh or check connection.</td></tr>`;
        lucide.createIcons();
    }
}

function toggleExpandRow(leadId) {
    if (expandedRowIds.has(leadId)) {
        expandedRowIds.delete(leadId);
    } else {
        expandedRowIds.add(leadId);
    }
    renderCurrentPage();
}

function renderTableRows(leads) {
    const tbody = document.getElementById('leads-table-body');
    if (!tbody) return;

    if (leads.length === 0) {
        tbody.innerHTML = '<tr><td colspan="10" style="padding:50px; text-align:center; color:#64748b; font-weight:600;"><i data-lucide="inbox" style="width:28px;height:28px;display:block;margin:0 auto 8px;opacity:0.5;"></i> No prospects found matching your criteria. Try adjusting filters or search.</td></tr>';
        return;
    }

    let rowsHtml = '';
    leads.forEach(l => {
        const stageStr = l.stage || 'New Lead';
        const leadName = (l.lead_name || l.name || 'Unnamed Prospect').replace(/'/g, "");
        const mobile = l.mobile || '';
        const secMobile = l.secondary_mobile || '';
        const email = l.email || '';
        const requirement = l.requirement || 'Loan';
        const priorityStr = l.priority || 'Warm';
        const locationStr = l.location || '';
        const waMsg = encodeURIComponent(`Hi ${leadName}, this is regarding your inquiry for ${requirement} at BFS Circle.`);
        const isExpanded = expandedRowIds.has(l.id);

        let dateDisplay = '—';
        if (l.created_at) {
            try {
                const d = new Date(l.created_at.replace(' ', 'T'));
                if (!isNaN(d)) dateDisplay = d.toLocaleDateString('en-GB', {day:'2-digit', month:'short', year:'numeric'});
            } catch(e) {}
        }

        // RM Searchable Trigger / Pill
        let rmCell = '';
        if (window.isAdminUser) {
            const currentRm = (window.staffMembersList || []).find(sm => sm.username === l.assigned_to);
            const rmName = currentRm ? (currentRm.full_name || currentRm.username) : (l.assigned_to || '');
            const isAssigned = !!l.assigned_to;
            
            // Get 2-letter initials
            let initials = 'RM';
            if (rmName) {
                const parts = rmName.trim().split(/\s+/);
                initials = parts.length >= 2 ? (parts[0][0] + parts[1][0]).toUpperCase() : parts[0].substring(0, 2).toUpperCase();
            }
            rmCell = `<td class="col-rm" onclick="event.stopPropagation()" style="vertical-align:middle;">
                <button type="button" 
                        class="rm-assign-trigger-btn ${isAssigned ? 'is-assigned' : 'is-unassigned'} no-print" 
                        id="rm-btn-lead-${l.id}"
                        data-lead-id="${l.id}" 
                        data-assigned="${escapeHtml(l.assigned_to || '')}"
                        onclick="openRmSearchPopover(this, ${l.id}, '${escapeHtml(l.assigned_to || '')}')"
                        title="${isAssigned ? 'Assigned: ' + escapeHtml(rmName) + ' (Click to change)' : 'Click to assign an RM'}">
                    ${isAssigned ? `
                        <span class="rm-trigger-avatar">${initials}</span>
                        <span class="rm-trigger-label">${escapeHtml(rmName)}</span>
                        <i data-lucide="chevron-down" class="rm-trigger-arrow"></i>
                    ` : `
                        <span class="rm-trigger-avatar unassigned"><i data-lucide="user-plus" style="width:10px;height:10px;"></i></span>
                        <span class="rm-trigger-label">Assign RM</span>
                        <i data-lucide="chevron-down" class="rm-trigger-arrow"></i>
                    `}
                </button>
                <div class="print-only" style="font-weight:700; color:#0f172a; font-size:7.5pt;">${escapeHtml(rmName) || 'Unassigned'}</div>
            </td>`;
        } else {
            rmCell = `<td class="col-rm"><span class="no-print" style="font-weight:700; color:#0f172a; font-size:11px; white-space:nowrap;">${escapeHtml(l.assigned_to || 'Unassigned')}</span><div class="print-only" style="font-weight:700; color:#0f172a; font-size:7.5pt;">${escapeHtml(l.assigned_to || 'Unassigned')}</div></td>`;
        }

        // Interactive Priority Dropdown
        const prioClass = priorityStr === 'Hot' ? 'select-prio-hot' : (priorityStr === 'Warm' ? 'select-prio-warm' : 'select-prio-cold');
        const prioCell = `
        <td class="col-priority" onclick="event.stopPropagation()">
            <select class="table-dropdown-select select-prio ${prioClass} no-print" onchange="quickUpdatePriority(${l.id}, this.value)">
                <option value="Hot" ${priorityStr === 'Hot' ? 'selected' : ''}>🔥 Hot</option>
                <option value="Warm" ${priorityStr === 'Warm' ? 'selected' : ''}>⚡ Warm</option>
                <option value="Cold" ${priorityStr === 'Cold' ? 'selected' : ''}>❄️ Cold</option>
            </select>
            <div class="print-only print-badge print-badge-${priorityStr.toLowerCase()}">${priorityStr.toUpperCase()}</div>
        </td>`;

        // Interactive Pipeline Stage Dropdown
        let stageClass = 'select-stage-new';
        if (stageStr === 'Contacted') stageClass = 'select-stage-contacted';
        else if (stageStr === 'Scheduled') stageClass = 'select-stage-scheduled';
        else if (stageStr === 'Interested') stageClass = 'select-stage-qualified';
        else if (stageStr === 'Converted') stageClass = 'select-stage-converted';
        else if (stageStr === 'Rejected') stageClass = 'select-stage-disqual';

        const stageCell = `
        <td class="col-stage" onclick="event.stopPropagation()">
            <select class="table-dropdown-select select-stage ${stageClass} no-print" onchange="quickUpdateStage(${l.id}, this.value)">
                <option value="New Lead" ${stageStr === 'New Lead' ? 'selected' : ''}>📋 New Lead</option>
                <option value="Contacted" ${stageStr === 'Contacted' ? 'selected' : ''}>📞 Contacted</option>
                <option value="Scheduled" ${stageStr === 'Scheduled' ? 'selected' : ''}>📅 Scheduled</option>
                <option value="Interested" ${stageStr === 'Interested' ? 'selected' : ''}>⭐ Qualified</option>
                <option value="Converted" ${stageStr === 'Converted' ? 'selected' : ''}>🚀 Converted</option>
                <option value="Rejected" ${stageStr === 'Rejected' ? 'selected' : ''}>❌ Disqualified</option>
            </select>
            <div class="print-only print-badge ${stageStr === 'Converted' ? 'print-badge-converted' : 'print-badge-stage'}">${stageStr}</div>
        </td>`;

        // Sourcing Channel Badge (Screen)
        let sourceBadge = `<span class="badge-partner badge-partner-direct"><i data-lucide="globe" style="width:10px;"></i> Direct</span>`;
        const refTypeStr = (l.referrer_type || l.added_by_role || l.lead_source || '').toLowerCase();
        let pClass = 'badge-partner-dsa';
        if (refTypeStr.includes('customer')) pClass = 'badge-partner-customer';
        else if (refTypeStr.includes('builder')) pClass = 'badge-partner-builder';
        else if (refTypeStr.includes('ca')) pClass = 'badge-partner-ca';
        else if (refTypeStr.includes('dsa') || refTypeStr.includes('agent') || refTypeStr.includes('partner')) pClass = 'badge-partner-dsa';

        if (l.is_customer_referral || refTypeStr.includes('customer')) {
            const cr = l.customer_referrer || {};
            const custName = cr.name || l.added_by_name || l.added_by || 'Customer';
            const custLoan = cr.loan_id ? '#' + cr.loan_id : '';
            const custMob = cr.mobile || l.referrer_mobile || '';

            sourceBadge = `<div>
                <span class="badge-partner badge-partner-customer" title="Customer Referral by ${custName}">
                    <i data-lucide="gift" style="width:10px;"></i> ${custName}
                    <span class="partner-tag" style="background:#166534; color:#fff;">Customer Ref</span>
                </span>
                <div style="font-size:10px; color:#64748b; font-weight:600; margin-top:2px; display:flex; align-items:center; gap:4px; flex-wrap:wrap;">
                    ${custLoan ? `<a href="search_track.php?q=${encodeURIComponent(cr.loan_id)}" target="_blank" style="color:#0284c7; text-decoration:none; font-weight:700;" title="Click to view Customer Loan Details">Loan ${custLoan}</a>` : ''}
                    ${custMob ? `<span>• 📞 ${custMob}</span>` : ''}
                </div>
            </div>`;
        } else if (l.referrer_name) {
            sourceBadge = `<div>
                <span class="badge-partner ${pClass}">
                    <i data-lucide="share-2" style="width:10px;"></i> ${l.referrer_name}
                    ${l.referrer_type ? `<span class="partner-tag">${l.referrer_type}</span>` : ''}
                </span>
                ${l.referrer_mobile ? `<div class="no-print" style="margin-top:2px;"><a href="https://wa.me/91${l.referrer_mobile}?text=${encodeURIComponent('Hi ' + l.referrer_name + ', updates regarding your referred client ' + leadName)}" target="_blank" style="font-size:10px; color:#16a34a; font-weight:700; text-decoration:none; display:inline-flex; align-items:center; gap:2px;"><i data-lucide="message-circle" style="width:9px;"></i> Partner Chat</a></div>` : ''}
            </div>`;
        } else if (l.added_by && l.added_by !== 'direct') {
            sourceBadge = `<div>
                <span class="badge-partner ${pClass}">
                    <i data-lucide="user-check" style="width:10px;"></i> ${l.added_by_name || l.added_by}
                    ${l.added_by_role ? `<span class="partner-tag">${l.added_by_role}</span>` : ''}
                </span>
            </div>`;
        }

        // Print-Optimized Sourcing Partner Representation
        let printPartnerName = 'Direct Inflow';
        let printChannelTag = '<span class="print-channel-tag print-tag-direct">DIRECT</span>';
        let printPartnerMeta = '';

        if (l.is_customer_referral || refTypeStr.includes('customer')) {
            const cr = l.customer_referrer || {};
            const custName = cr.name || l.added_by_name || l.added_by || 'Customer Referral';
            const custLoan = cr.loan_id ? ('#' + cr.loan_id) : '';
            const custMob = cr.mobile || l.referrer_mobile || '';
            printPartnerName = custName;
            printChannelTag = '<span class="print-channel-tag print-tag-customer">CUSTOMER REF</span>';
            const metaParts = [];
            if (custLoan) metaParts.push(`<b>Loan:</b> ${custLoan}`);
            if (custMob) metaParts.push(`<b>Ph:</b> ${custMob}`);
            if (metaParts.length > 0) {
                printPartnerMeta = `<div class="print-source-meta">${metaParts.join(' • ')}</div>`;
            }
        } else if (l.referrer_name) {
            printPartnerName = l.referrer_name;
            let tagClass = 'print-tag-dsa';
            let tagLabel = l.referrer_type || 'PARTNER';
            if (refTypeStr.includes('builder')) { tagClass = 'print-tag-builder'; tagLabel = l.referrer_type || 'BUILDER'; }
            else if (refTypeStr.includes('ca')) { tagClass = 'print-tag-ca'; tagLabel = l.referrer_type || 'CA'; }
            else if (refTypeStr.includes('agent')) { tagClass = 'print-tag-agent'; tagLabel = l.referrer_type || 'AGENT'; }
            else if (refTypeStr.includes('dsa') || refTypeStr.includes('partner')) { tagClass = 'print-tag-dsa'; tagLabel = l.referrer_type || 'DSA'; }
            
            printChannelTag = `<span class="print-channel-tag ${tagClass}">${escapeHtml(tagLabel)}</span>`;
            if (l.referrer_mobile) {
                printPartnerMeta = `<div class="print-source-meta"><b>Ph:</b> ${l.referrer_mobile}</div>`;
            }
        } else if (l.added_by && l.added_by !== 'direct') {
            printPartnerName = l.added_by_name || l.added_by;
            printChannelTag = `<span class="print-channel-tag print-tag-internal">${escapeHtml(l.added_by_role || 'STAFF ADDED')}</span>`;
        }

        // Reminders & Notes Preview
        let reminderBadge = '';
        if (l.reminder_date) {
            reminderBadge = `<div style="margin-top:2px;"><span style="background:#fffbeb; color:#b45309; border:1px solid #fde68a; font-size:9.5px; font-weight:700; padding:1px 4px; border-radius:3px; display:inline-flex; align-items:center; gap:2px;"><i data-lucide="alarm-clock" style="width:9px;"></i> Rem: ${l.reminder_date}</span></div>`;
        }
        let notesBadge = '';
        if (l.notes_count > 0 || l.latest_note) {
            const countText = l.notes_count > 0 ? `${l.notes_count} note${l.notes_count > 1 ? 's' : ''}` : '1 note';
            notesBadge = `<div class="no-print" style="margin-top:2px;"><span title="${(l.latest_note || '').replace(/"/g, '&quot;')}" style="font-size:10px; color:#64748b; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:2px;" onclick="openLeadDetailDrawer(${l.id})"><i data-lucide="message-square" style="width:9px;"></i> ${countText}</span></div>`;
        }

        // Format Loan Amount
        const lakhStr = formatLakhs(l.loan_amount);
        const lakhClean = lakhStr ? lakhStr.replace(/[()]/g, '').trim() : '';
        const amountDisplay = l.loan_amount ? `
            <div style="margin-top:2px; white-space:nowrap;">
                <span style="font-size:11px; font-weight:800; color:#0f172a; font-family:'Outfit',sans-serif; background:#f8fafc; padding:1px 5px; border-radius:4px; border:1px solid #cbd5e1; display:inline-block;">
                    ₹ ${Number(l.loan_amount).toLocaleString('en-IN')}
                </span>
                ${lakhStr ? `<span style="font-size:10px; font-weight:700; color:#64748b; margin-left:3px;">${lakhStr}</span>` : ''}
            </div>` : '';

        // Avatar
        const avatarHtml = l.photo 
            ? `<img src="${l.photo}" class="lead-avatar-img no-print" style="width:30px; height:30px; border-radius:7px; object-fit:cover; border:1px solid #cbd5e1; flex-shrink:0;">` 
            : `<div class="lead-avatar-box no-print" style="width:30px; height:30px; border-radius:7px; background:linear-gradient(135deg, #0f172a 0%, #334155 100%); color:#ffffff; font-weight:800; font-size:12px; display:flex; align-items:center; justify-content:center; flex-shrink:0;">${leadName.substring(0,1).toUpperCase()}</div>`;

        rowsHtml += `
        <tr class="lead-row ${isExpanded ? 'expanded' : ''}" data-lead-id="${l.id}">
            <td style="text-align:center; padding:7px 4px;" class="checkbox-col no-print" onclick="event.stopPropagation()">
                <div style="display:flex; align-items:center; justify-content:center; gap:2px;">
                    <button type="button" class="action-btn-expand" onclick="toggleExpandRow(${l.id})" title="Toggle Quick Details Accordion">
                        <i data-lucide="${isExpanded ? 'chevron-down' : 'chevron-right'}" style="width:13px; height:13px;"></i>
                    </button>
                    <input type="checkbox" class="lead-checkbox" value="${l.id}" onchange="updateCheckedCount()" style="cursor:pointer; accent-color:#0f172a; margin:0;">
                </div>
            </td>
            <td class="col-customer">
                <div class="no-print" style="display:flex; align-items:center; gap:7px;">
                    ${avatarHtml}
                    <div>
                        <div style="font-weight:800; color:#0f172a; font-size:12px; line-height:1.2; cursor:pointer;" onclick="openLeadDetailDrawer(${l.id})">${leadName}</div>
                        <div style="font-size:10.5px; font-weight:600; color:#64748b; margin-top:1px;">${l.company_name || 'Individual Prospect'}</div>
                        ${locationStr ? `<div style="font-size:9.5px; font-weight:700; color:#475569; margin-top:1px; display:flex; align-items:center; gap:2px;"><i data-lucide="map-pin" style="width:9px; color:#94a3b8;"></i> ${locationStr}</div>` : ''}
                    </div>
                </div>
                <div class="print-only">
                    <div class="print-cust-name">${escapeHtml(leadName)}</div>
                    <div class="print-cust-firm">${escapeHtml(l.company_name || 'Individual Prospect')}</div>
                    ${locationStr ? `<div class="print-cust-loc">${escapeHtml(locationStr)}</div>` : ''}
                </div>
            </td>
            <td class="col-contact">
                <div class="no-print">
                    <div style="display:flex; align-items:center; gap:4px; white-space:nowrap;">
                        <a href="tel:${mobile}" style="font-weight:700; color:#0f172a; font-size:11.5px; text-decoration:none; display:inline-flex; align-items:center; gap:3px;">
                            <i data-lucide="phone" style="width:11px; color:#475569;"></i> ${mobile || '—'}
                        </a>
                        ${mobile ? `<a href="https://wa.me/91${mobile}?text=${waMsg}" target="_blank" class="action-btn action-btn-wa" style="width:18px; height:18px; border-radius:4px;" title="1-Click WhatsApp"><i data-lucide="message-circle" style="width:10px;"></i></a>` : ''}
                    </div>
                    ${secMobile ? `<div style="font-size:10px; color:#475569; margin-top:1px; display:flex; align-items:center; gap:2px; white-space:nowrap;"><span style="font-weight:700; color:#64748b; font-size:9px;">ALT:</span> <a href="tel:${secMobile}" style="color:#0f172a; text-decoration:none; font-weight:600;">${secMobile}</a></div>` : ''}
                    ${email ? `<div style="font-size:10px; color:#64748b; margin-top:1px; display:flex; align-items:center; gap:2px; white-space:nowrap; max-width:130px; overflow:hidden; text-overflow:ellipsis;" title="${email}"><i data-lucide="mail" style="width:10px; color:#94a3b8; flex-shrink:0;"></i> <a href="mailto:${email}" style="color:#64748b; text-decoration:none; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${email}</a></div>` : '<div style="font-size:9.5px; color:#94a3b8; margin-top:1px;">No Email</div>'}
                </div>
                <div class="print-only">
                    <div class="print-contact-phone">${mobile || '—'}</div>
                    ${secMobile ? `<div class="print-contact-alt"><b>Alt:</b> ${secMobile}</div>` : ''}
                    ${email ? `<div class="print-contact-email">${escapeHtml(email)}</div>` : ''}
                </div>
            </td>
            <td class="col-loan">
                <div class="no-print">
                    <div style="font-weight:700; color:#0f172a; font-size:11.5px; white-space:nowrap;">${requirement}</div>
                    ${amountDisplay}
                </div>
                <div class="print-only print-loan-block">
                    <div class="print-loan-req">${escapeHtml(requirement)}</div>
                    ${l.loan_amount ? `
                        <div class="print-loan-amt">₹ ${Number(l.loan_amount).toLocaleString('en-IN')}</div>
                        ${lakhClean ? `<div class="print-loan-lakh">(${lakhClean})</div>` : ''}
                    ` : `<div class="print-loan-amt" style="color:#64748b; font-size:7pt; font-weight:600;">Not Specified</div>`}
                </div>
            </td>
            <td class="col-partner">
                <div class="no-print">
                    ${sourceBadge}
                </div>
                <div class="print-only print-source-block">
                    <div class="print-source-name">${escapeHtml(printPartnerName)}</div>
                    <div>${printChannelTag}</div>
                    ${printPartnerMeta}
                </div>
            </td>
            ${prioCell}
            ${stageCell}
            <td class="col-date">
                <div class="no-print">
                    <div style="font-weight:700; color:#0f172a; font-size:11px; white-space:nowrap;">${dateDisplay}</div>
                    ${reminderBadge}
                    ${notesBadge}
                </div>
                <div class="print-only" style="text-align:center;">
                    <div style="font-weight:700; color:#0f172a; font-size:7.5pt; white-space:nowrap;">${dateDisplay}</div>
                    ${l.reminder_date ? `<div style="margin-top:2px;"><span class="print-badge print-badge-warm" style="font-size:6.5pt; padding:1px 3px;">Rem: ${escapeHtml(l.reminder_date)}</span></div>` : ''}
                </div>
            </td>
            ${rmCell}
            <td style="text-align:right; white-space:nowrap; padding:7px 4px;" class="action-col no-print" onclick="event.stopPropagation()">
                <div style="display:inline-flex; align-items:center; gap:2px; justify-content:flex-end;">
                    <a href="tel:${mobile}" class="action-btn" title="Call"><i data-lucide="phone"></i></a>
                    <a href="https://wa.me/91${mobile}?text=${waMsg}" target="_blank" class="action-btn action-btn-wa" title="WhatsApp"><i data-lucide="message-circle"></i></a>
                    <button class="action-btn action-btn-promote" onclick="promoteLeadToApplicant(${l.id}, '${leadName}')" title="Convert to Active Loan Application"><i data-lucide="rocket"></i></button>
                    <button class="action-btn" onclick="openLeadDetailDrawer(${l.id})" title="Open 360° Drawer Details"><i data-lucide="eye"></i></button>
                    ${window.isAdminUser ? `<button class="action-btn action-btn-delete" onclick="deleteLead(${l.id})" title="Delete Lead"><i data-lucide="trash-2"></i></button>` : ''}
                </div>
            </td>
        </tr>`;

        // Expandable Inline Detail Card (Accordion)
        if (isExpanded) {
            rowsHtml += `
            <tr class="inline-detail-row no-print">
                <td colspan="10" class="inline-detail-cell">
                    <div class="inline-detail-grid">
                        <div class="inline-detail-card">
                            <div class="inline-card-title"><i data-lucide="user" style="width:12px;"></i> Full Prospect Profile</div>
                            <div style="font-size:12px; line-height:1.6; color:#334155;">
                                <div><b>Full Name:</b> ${leadName}</div>
                                <div><b>Company / Firm:</b> ${l.company_name || 'Individual / Not specified'}</div>
                                <div><b>Primary Phone:</b> ${mobile || 'N/A'}</div>
                                ${secMobile ? `<div><b>Alternate Phone:</b> ${secMobile}</div>` : ''}
                                <div><b>Email:</b> ${email || 'N/A'}</div>
                                <div><b>Location / City:</b> ${locationStr || 'N/A'}</div>
                            </div>
                        </div>

                        <div class="inline-detail-card">
                            <div class="inline-card-title"><i data-lucide="file-text" style="width:12px;"></i> Loan & Sourcing Details</div>
                            <div style="font-size:12px; line-height:1.6; color:#334155;">
                                <div><b>Product:</b> ${requirement}</div>
                                <div><b>Requested Amount:</b> ${l.loan_amount ? '₹ ' + Number(l.loan_amount).toLocaleString('en-IN') : 'N/A'}</div>
                                <div><b>Sourcing Partner:</b> ${l.referrer_name || l.added_by_name || 'Direct / Self'}</div>
                                <div><b>Partner Type:</b> ${l.referrer_type || l.added_by_role || l.lead_source || 'Direct'}</div>
                                ${l.reminder_date ? `<div><b>Follow-up Date:</b> ⏰ ${l.reminder_date}</div>` : ''}
                            </div>
                        </div>

                        ${(l.is_customer_referral || refTypeStr.includes('customer')) ? `
                        <div class="inline-detail-card" style="background:#f0fdf4; border-left:3px solid #16a34a;">
                            <div class="inline-card-title" style="color:#166534;"><i data-lucide="gift" style="width:12px;"></i> Referring Customer (Existing Borrower)</div>
                            <div style="font-size:12px; line-height:1.6; color:#14532d;">
                                <div><b>Customer Name:</b> ${l.customer_referrer?.name || l.added_by_name || l.added_by || 'Customer'}</div>
                                <div><b>Customer Mobile:</b> <a href="tel:${l.customer_referrer?.mobile || l.referrer_mobile || ''}" style="color:#15803d; font-weight:700;">${l.customer_referrer?.mobile || l.referrer_mobile || 'N/A'}</a></div>
                                ${l.customer_referrer?.loan_id ? `<div><b>Existing Loan File:</b> <a href="search_track.php?q=${encodeURIComponent(l.customer_referrer.loan_id)}" target="_blank" style="color:#0284c7; font-weight:700; text-decoration:underline;">Loan #${l.customer_referrer.loan_id} (Open Profile)</a></div>` : ''}
                                <div><b>Referral Cash Reward:</b> ₹${Number(l.customer_referrer?.reward_amount || 2000).toLocaleString('en-IN')} <span style="background:#dcfce7; color:#15803d; padding:1px 5px; border-radius:4px; font-weight:800; font-size:10px;">${l.customer_referrer?.reward_status || 'Pending'}</span></div>
                            </div>
                        </div>` : ''}

                        <div class="inline-detail-card">
                            <div class="inline-card-title"><i data-lucide="message-square" style="width:12px;"></i> Quick Notes & Follow-up</div>
                            <div style="font-size:11.5px; color:#475569; margin-bottom:8px; max-height:60px; overflow-y:auto; background:#f8fafc; padding:6px 8px; border-radius:6px; border:1px solid #e2e8f0;">
                                ${l.latest_note ? `<b>Latest:</b> ${l.latest_note}` : 'No notes entered yet.'}
                            </div>
                            <div style="display:flex; gap:6px;">
                                <input type="text" id="inlineNoteInput_${l.id}" placeholder="Type quick interaction note..." style="flex:1; padding:5px 8px; font-size:11.5px; border:1px solid #cbd5e1; border-radius:6px; outline:none;">
                                <button type="button" class="btn-primary" style="padding:4px 10px; font-size:11px;" onclick="submitInlineNote(${l.id})">Save</button>
                            </div>
                        </div>

                        ${l.photo ? `
                        <div class="inline-detail-card" style="text-align:center;">
                            <div class="inline-card-title"><i data-lucide="image" style="width:12px;"></i> Attached Document / Photo</div>
                            <a href="${l.photo}" target="_blank" title="Click to view full image">
                                <img src="${l.photo}" style="max-height:85px; max-width:100%; border-radius:6px; border:1px solid #cbd5e1; object-fit:contain;">
                            </a>
                        </div>` : ''}
                    </div>
                </td>
            </tr>`;
        }
    });

    tbody.innerHTML = rowsHtml;
    updateCheckedCount();
}

function submitInlineNote(leadId) {
    const input = document.getElementById(`inlineNoteInput_${leadId}`);
    if (!input) return;
    const note = input.value.trim();
    if (!note) return;

    const fd = new FormData();
    fd.append('lead_id', leadId);
    fd.append('note', note);

    fetch('api.php?api=add_lead_note', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            typeof showNotification === 'function' ? showNotification('Note added successfully', 'success') : true;
            loadLeads(currentLeadsPage);
        }
    });
}

function quickUpdateStage(id, stage) {
    const fd = new FormData();
    fd.append('id', id);
    fd.append('stage', stage);
    fetch('api.php?api=update_lead_stage', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            typeof showNotification === 'function' ? showNotification(`Stage updated to ${stage}`, 'success') : true;
            loadLeads(currentLeadsPage);
        } else {
            Swal.fire({ icon: 'error', title: 'Update Failed', text: d.error || 'Failed to update stage', confirmButtonColor: '#0f172a' });
        }
    });
}

function quickUpdatePriority(id, priority) {
    const fd = new FormData();
    fd.append('id', id);
    fd.append('priority', priority);
    fetch('api.php?api=update_lead_stage', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            typeof showNotification === 'function' ? showNotification(`Priority set to ${priority}`, 'success') : true;
            loadLeads(currentLeadsPage);
        } else {
            Swal.fire({ icon: 'error', title: 'Update Failed', text: d.error || 'Failed to update priority', confirmButtonColor: '#0f172a' });
        }
    });
}

/* ==========================================================================
   BFS CIRCLE SEARCHABLE RM ASSIGNMENT & POPOVER ENGINE
   ========================================================================== */
function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str).replace(/[&<>"']/g, function(m) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
    });
}

function getAvatarColor(str) {
    const colors = ['#0284c7', '#0d9488', '#16a34a', '#d97706', '#7c3aed', '#db2777', '#4f46e5', '#0891b2', '#2563eb'];
    if (!str) return colors[0];
    let hash = 0;
    for (let i = 0; i < str.length; i++) hash = str.charCodeAt(i) + ((hash << 5) - hash);
    return colors[Math.abs(hash) % colors.length];
}

function highlightSearchMatch(text, query) {
    if (!query || !text) return escapeHtml(text || '');
    const escapedText = escapeHtml(text);
    const escapedQuery = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const regex = new RegExp(`(${escapedQuery})`, 'gi');
    return escapedText.replace(regex, '<mark>$1</mark>');
}

let activePopoverTriggerEl = null;
let activePopoverLeadId = null;
let activePopoverCurrentRm = '';
let activePopoverIsBulk = false;
let popoverFocusedIndex = -1;

function openRmSearchPopover(triggerEl, leadId, currentRm, isBulk = false) {
    if (activePopoverTriggerEl === triggerEl) {
        closeRmSearchPopover();
        return;
    }

    closeRmSearchPopover();

    activePopoverTriggerEl = triggerEl;
    activePopoverLeadId = leadId;
    activePopoverCurrentRm = currentRm || '';
    activePopoverIsBulk = isBulk;
    popoverFocusedIndex = -1;

    triggerEl.classList.add('is-open');

    const popover = document.getElementById('rmSearchPopover');
    if (!popover) return;

    popover.style.display = 'flex';

    // Position Popover anchored to trigger button
    const rect = triggerEl.getBoundingClientRect();
    const popoverWidth = 300;
    const popoverHeight = 310;

    let left = rect.left;
    if (left + popoverWidth > window.innerWidth - 12) {
        left = Math.max(12, rect.right - popoverWidth);
    }

    let top = rect.bottom + 6;
    if (top + popoverHeight > window.innerHeight - 12 && rect.top > popoverHeight + 12) {
        top = rect.top - popoverHeight - 6;
    }

    popover.style.top = `${top}px`;
    popover.style.left = `${left}px`;

    // Clear search input & focus
    const input = document.getElementById('rmPopoverSearchInput');
    if (input) {
        input.value = '';
        const clearBtn = document.getElementById('rmPopoverClearBtn');
        if (clearBtn) clearBtn.style.display = 'none';
        setTimeout(() => input.focus(), 40);
    }

    renderRmPopoverItems('');
}

function openBulkRmSearchPopover(triggerEl) {
    const currentVal = document.getElementById('bulk-assign-leads-staff')?.value || '';
    openRmSearchPopover(triggerEl, null, currentVal, true);
}

function closeRmSearchPopover() {
    const popover = document.getElementById('rmSearchPopover');
    if (popover) popover.style.display = 'none';
    if (activePopoverTriggerEl) {
        activePopoverTriggerEl.classList.remove('is-open');
    }
    activePopoverTriggerEl = null;
    activePopoverLeadId = null;
    activePopoverCurrentRm = '';
    activePopoverIsBulk = false;
    popoverFocusedIndex = -1;
}

function clearRmSearch(e) {
    if (e) e.stopPropagation();
    const input = document.getElementById('rmPopoverSearchInput');
    if (input) {
        input.value = '';
        input.focus();
    }
    const clearBtn = document.getElementById('rmPopoverClearBtn');
    if (clearBtn) clearBtn.style.display = 'none';
    renderRmPopoverItems('');
}

function renderRmPopoverItems(searchQuery) {
    const listContainer = document.getElementById('rmPopoverList');
    const countBadge = document.getElementById('rmPopoverCount');
    if (!listContainer) return;

    popoverFocusedIndex = -1;
    const query = (searchQuery || '').trim().toLowerCase();
    const allStaff = window.staffMembersList || [];

    const filtered = allStaff.filter(sm => {
        if (!query) return true;
        const name = (sm.full_name || '').toLowerCase();
        const user = (sm.username || '').toLowerCase();
        const role = (sm.role || '').toLowerCase();
        return name.includes(query) || user.includes(query) || role.includes(query);
    });

    if (countBadge) {
        countBadge.innerText = query ? `${filtered.length} matching` : `${allStaff.length} Officers available`;
    }

    let html = '';

    // If query matches unassign keywords or empty query, show clear option
    if (!query || 'unassigned clear remove none'.includes(query)) {
        const isNoneSel = !activePopoverCurrentRm;
        html += `
        <div class="rm-popover-item rm-popover-item-unassign ${isNoneSel ? 'is-selected' : ''}" 
             onclick="selectRmOption('', '')" 
             tabindex="0" data-username="">
            <span class="rm-item-avatar rm-item-avatar-clear"><i data-lucide="user-x" style="width:12px;height:12px;"></i></span>
            <div class="rm-item-content">
                <div class="rm-item-title" style="color:#64748b;">Unassigned</div>
                <div class="rm-item-sub">No relationship manager</div>
            </div>
            ${isNoneSel ? '<i data-lucide="check" class="rm-item-check"></i>' : ''}
        </div>`;
    }

    if (filtered.length === 0 && html === '') {
        html = `
        <div class="rm-popover-empty">
            <i data-lucide="user-x" style="width:24px;height:24px;color:#94a3b8;margin:0 auto 6px;display:block;"></i>
            <div>No staff member found matching "<strong>${escapeHtml(query)}</strong>"</div>
            <div style="font-size:11px; color:#94a3b8; margin-top:4px;">Try searching by name, username or role</div>
        </div>`;
    } else {
        filtered.forEach(sm => {
            const isSel = (sm.username === activePopoverCurrentRm);
            const displayName = sm.full_name || sm.username;
            const role = sm.role || 'Staff';
            const rLow = role.toLowerCase();
            let roleClass = 'badge-staff';
            if (rLow.includes('rm')) roleClass = 'badge-rm';
            else if (rLow.includes('manager')) roleClass = 'badge-manager';
            else if (rLow.includes('admin')) roleClass = 'badge-admin';
            else if (rLow.includes('credit')) roleClass = 'badge-credit';

            let initials = 'RM';
            if (displayName) {
                const parts = displayName.trim().split(/\s+/);
                initials = parts.length >= 2 ? (parts[0][0] + parts[1][0]).toUpperCase() : parts[0].substring(0, 2).toUpperCase();
            }

            let cleanHandle = sm.username;
            if (cleanHandle.includes('@')) {
                cleanHandle = cleanHandle.toLowerCase();
            } else {
                cleanHandle = '@' + cleanHandle.toLowerCase();
            }

            const highlightedName = highlightSearchMatch(displayName, query);
            const highlightedUser = highlightSearchMatch(cleanHandle, query);

            html += `
            <div class="rm-popover-item ${isSel ? 'is-selected' : ''}" 
                 onclick="selectRmOption('${escapeHtml(sm.username)}', '${escapeHtml(displayName)}')" 
                 tabindex="0" data-username="${escapeHtml(sm.username)}" data-name="${escapeHtml(displayName)}">
                <span class="rm-item-avatar">${initials}</span>
                <div class="rm-item-content">
                    <div class="rm-item-title">${highlightedName}</div>
                    <div class="rm-item-sub">
                        <span>${highlightedUser}</span>
                        <span class="rm-item-role-badge ${roleClass}">${escapeHtml(role)}</span>
                    </div>
                </div>
                ${isSel ? '<i data-lucide="check" class="rm-item-check"></i>' : ''}
            </div>`;
        });
    }

    listContainer.innerHTML = html;
    lucide.createIcons({ root: listContainer });
}

function selectRmOption(username, fullName) {
    const isBulk = activePopoverIsBulk;
    const leadId = activePopoverLeadId;
    const triggerBtn = activePopoverTriggerEl;

    closeRmSearchPopover();

    if (isBulk) {
        const bulkInput = document.getElementById('bulk-assign-leads-staff');
        if (bulkInput) bulkInput.value = username;
        const bulkLabel = document.getElementById('bulk-assign-rm-label');
        if (bulkLabel) bulkLabel.innerText = username ? (fullName || username) : 'Choose RM to Assign';
        const bulkTrigger = document.getElementById('bulk-assign-rm-trigger');
        if (bulkTrigger) {
            bulkTrigger.classList.toggle('is-assigned', !!username);
            bulkTrigger.classList.toggle('is-unassigned', !username);
        }
        typeof showNotification === 'function' ? showNotification(username ? `Selected ${fullName || username} for bulk assignment. Click 'Assign RM' to apply.` : 'Cleared bulk RM selection.', 'info') : true;
        return;
    }

    if (!leadId) return;

    // Optimistic UI Update on trigger button if present
    if (triggerBtn) {
        triggerBtn.dataset.assigned = username;
        const isAssigned = !!username;
        triggerBtn.className = `rm-assign-trigger-btn ${isAssigned ? 'is-assigned' : 'is-unassigned'} no-print`;
        
        let initials = 'RM';
        if (fullName) {
            const parts = fullName.trim().split(/\s+/);
            initials = parts.length >= 2 ? (parts[0][0] + parts[1][0]).toUpperCase() : parts[0].substring(0, 2).toUpperCase();
        }

        if (isAssigned) {
            triggerBtn.innerHTML = `
                <span class="rm-trigger-avatar">${initials}</span>
                <span class="rm-trigger-label">${escapeHtml(fullName || username)}</span>
                <i data-lucide="chevron-down" class="rm-trigger-arrow"></i>
            `;
            triggerBtn.title = `Assigned: ${escapeHtml(fullName || username)} (Click to change)`;
        } else {
            triggerBtn.innerHTML = `
                <span class="rm-trigger-avatar unassigned"><i data-lucide="user-plus" style="width:10px;height:10px;"></i></span>
                <span class="rm-trigger-label">Assign RM</span>
                <i data-lucide="chevron-down" class="rm-trigger-arrow"></i>
            `;
            triggerBtn.title = `Click to assign an RM`;
        }
        lucide.createIcons({ root: triggerBtn });
    }

    // Call API
    assignSingleLead(leadId, username);
}

function assignSingleLead(id, rmUsername) {
    const fd = new FormData();
    fd.append('type', 'leads');
    fd.append('assigned_to', rmUsername);
    fd.append('ids', JSON.stringify([id]));

    fetch('api.php?api=bulk_assign', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            const sm = (window.staffMembersList || []).find(s => s.username === rmUsername);
            const name = sm ? (sm.full_name || sm.username) : rmUsername;
            typeof showNotification === 'function' ? showNotification(rmUsername ? `RM assigned to ${name}` : 'RM unassigned successfully', 'success') : true;
            if (currentViewMode === 'kanban') {
                loadKanbanBoard();
            } else {
                loadLeads(currentLeadsPage);
            }
            if (activeDrawerLeadId === id && document.getElementById('leadDetailDrawer')?.classList.contains('active')) {
                openLeadDetailDrawer(id);
            }
        } else {
            Swal.fire({ icon: 'error', title: 'Assignment Failed', text: d.error || 'Failed to assign RM', confirmButtonColor: '#0f172a' });
        }
    });
}

function bulkAssign(type) {
    const checked = Array.from(document.querySelectorAll('.lead-checkbox:checked')).map(c => parseInt(c.value));
    const rm = document.getElementById('bulk-assign-leads-staff').value;
    if (checked.length === 0) { Swal.fire({ icon: 'warning', title: 'Selection Required', text: 'Select at least one lead.', confirmButtonColor: '#0f172a' }); return; }
    if (!rm) { Swal.fire({ icon: 'warning', title: 'RM Required', text: 'Please click Choose RM to Assign and pick an officer first.', confirmButtonColor: '#0f172a' }); return; }

    const fd = new FormData();
    fd.append('type', 'leads');
    fd.append('assigned_to', rm);
    fd.append('ids', JSON.stringify(checked));

    fetch('api.php?api=bulk_assign', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            const sm = (window.staffMembersList || []).find(s => s.username === rm);
            const name = sm ? (sm.full_name || sm.username) : rm;
            Swal.fire({ icon: 'success', title: 'Leads Assigned', text: `${checked.length} leads assigned to ${name}`, confirmButtonColor: '#0f172a' });
            clearLeadSelection();
            
            // Reset Bulk UI
            const bulkInput = document.getElementById('bulk-assign-leads-staff');
            if (bulkInput) bulkInput.value = '';
            const bulkLabel = document.getElementById('bulk-assign-rm-label');
            if (bulkLabel) bulkLabel.innerText = 'Choose RM to Assign';
            const bulkTrigger = document.getElementById('bulk-assign-rm-trigger');
            if (bulkTrigger) {
                bulkTrigger.classList.remove('is-assigned');
                bulkTrigger.classList.add('is-unassigned');
            }
            
            if (currentViewMode === 'kanban') loadKanbanBoard(); else loadLeads(currentLeadsPage);
        } else {
            Swal.fire({ icon: 'error', title: 'Assignment Failed', text: d.error || 'Failed to assign leads', confirmButtonColor: '#0f172a' });
        }
    });
}

function bulkChangeStage() {
    const checked = Array.from(document.querySelectorAll('.lead-checkbox:checked')).map(c => parseInt(c.value));
    const stage = document.getElementById('bulk-change-stage-select').value;
    if (checked.length === 0) { Swal.fire({ icon: 'warning', title: 'Selection Required', text: 'Select at least one lead.', confirmButtonColor: '#0f172a' }); return; }
    if (!stage) { Swal.fire({ icon: 'warning', title: 'Stage Required', text: 'Select a target stage from dropdown.', confirmButtonColor: '#0f172a' }); return; }

    let promises = checked.map(id => {
        const fd = new FormData();
        fd.append('id', id);
        fd.append('stage', stage);
        return fetch('api.php?api=update_lead_stage', { method: 'POST', body: fd }).then(r => r.json());
    });

    Promise.all(promises).then(() => {
        Swal.fire({ icon: 'success', title: 'Stages Updated', text: `${checked.length} leads moved to ${stage}`, confirmButtonColor: '#0f172a' });
        clearLeadSelection();
        loadLeads(currentLeadsPage);
    });
}

function bulkDelete(type) {
    const checked = Array.from(document.querySelectorAll('.lead-checkbox:checked')).map(c => parseInt(c.value));
    if (checked.length === 0) { Swal.fire({ icon: 'warning', title: 'Selection Required', text: 'Select at least one lead.', confirmButtonColor: '#0f172a' }); return; }

    if (!confirm(`Are you sure you want to delete ${checked.length} selected leads?`)) return;

    const fd = new FormData();
    fd.append('api', 'bulk_delete');
    fd.append('type', 'leads');
    fd.append('ids', JSON.stringify(checked));

    fetch('api.php?api=bulk_delete', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            Swal.fire({ icon: 'success', title: 'Leads Deleted', text: d.message, confirmButtonColor: '#0f172a' });
            clearLeadSelection();
            loadLeads(currentLeadsPage);
        }
    });
}

function deleteLead(id) {
    if (!confirm('Are you sure you want to delete this lead?')) return;
    const fd = new FormData(); fd.append('id', id);
    fetch('api.php?api=delete_lead', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            closeLeadDetailDrawer();
            loadLeads(currentLeadsPage);
        }
    });
}

function resetLeadFilters() {
    document.getElementById('lead-search').value = '';
    document.getElementById('lead-filter-date').value = '';
    document.getElementById('lead-filter-type').value = '';
    document.getElementById('lead-filter-stage').value = '';
    document.getElementById('lead-filter-priority').value = '';
    if (document.getElementById('lead-filter-staff')) document.getElementById('lead-filter-staff').value = '';
    if (currentViewMode === 'kanban') loadKanbanBoard(); else loadLeads(1);
}

/* --- Slide-out 360 Detail Drawer --- */
function openLeadDetailDrawer(leadId) {
    activeDrawerLeadId = leadId;
    document.getElementById('drawerBackdrop').classList.add('active');
    document.getElementById('leadDetailDrawer').classList.add('active');
    document.getElementById('drawerLeadBody').innerHTML = `<div style="text-align:center; padding:50px 20px; color:#64748b;"><i data-lucide="loader" class="spin"></i> Loading details...</div>`;
    lucide.createIcons();

    fetch(`api.php?api=get_lead_detail&id=${leadId}`)
    .then(r => r.json())
    .then(lead => {
        if (!lead || lead.error) {
            document.getElementById('drawerLeadBody').innerHTML = `<div style="color:#ef4444; padding:20px;">Failed to load lead details.</div>`;
            return;
        }

        const leadName = lead.lead_name || lead.name || 'Unnamed Prospect';
        const mobile = lead.mobile || '—';
        const secMobile = lead.secondary_mobile || '';
        const email = lead.email || 'No email provided';
        const waMsg = encodeURIComponent(`Hi ${leadName}, calling from BFS Circle regarding your inquiry.`);
        const refName = lead.referrer_name || lead.added_by_name;
        const refMobile = lead.referrer_mobile || '';
        const refType = lead.referrer_type || lead.added_by_role || lead.lead_source || 'Direct';

        const photoAvatarHtml = lead.photo ? `<img src="${lead.photo}" style="width:34px; height:34px; border-radius:50%; object-fit:cover; border:2px solid #0f172a; vertical-align:middle; margin-right:8px;">` : `<div style="width:34px; height:34px; border-radius:50%; background:#0f172a; color:#fff; font-weight:800; font-size:14px; display:inline-flex; align-items:center; justify-content:center; vertical-align:middle; margin-right:8px;">${leadName.substring(0,1).toUpperCase()}</div>`;
        document.getElementById('drawerLeadTitle').innerHTML = `${photoAvatarHtml} ${leadName}`;
        document.getElementById('drawerLeadSubtitle').innerText = `${lead.company_name ? lead.company_name + ' • ' : ''}${lead.requirement || 'Loan'}`;

        let referrerCard = '';
        if (lead.is_customer_referral) {
            const cr = lead.customer_referrer || {};
            const crName = cr.name || refName || 'Existing Customer';
            const crMob = cr.mobile || refMobile || '';
            const crLoan = cr.loan_id ? '#' + cr.loan_id : '';
            const refWaMsg = encodeURIComponent(`Hi ${crName}, thank you for referring ${leadName} to BFS Circle. We are working on their loan application!`);

            referrerCard = `
            <div class="drawer-section" style="background:#f0fdf4; border:1.5px solid #86efac; border-radius:10px; padding:14px;">
                <div class="drawer-section-title" style="color:#166534; font-weight:800; font-size:12px; margin-bottom:10px; display:flex; align-items:center; gap:6px;">
                    <i data-lucide="gift" style="width:15px; height:15px;"></i> REFERRING CUSTOMER (EXISTING BORROWER)
                </div>
                <div style="font-size:12.5px; color:#14532d; line-height:1.7;">
                    <div><b>Existing Customer:</b> <span style="font-size:13.5px; font-weight:800; color:#0f172a;">${crName}</span> <span style="font-size:10.5px; background:#16a34a; color:#fff; padding:2px 6px; border-radius:4px; font-weight:700;">Verified Customer</span></div>
                    ${crMob ? `<div><b>Customer Mobile:</b> <b>${crMob}</b></div>` : ''}
                    ${crLoan ? `<div><b>Customer Loan Account:</b> <a href="search_track.php?q=${encodeURIComponent(cr.loan_id)}" target="_blank" style="color:#0284c7; font-weight:800; text-decoration:underline;">Loan ${crLoan} (Open Details)</a></div>` : ''}
                    <div><b>Referral Cash Reward:</b> ₹${Number(cr.reward_amount || 2000).toLocaleString('en-IN')} <span style="background:#dcfce7; color:#15803d; padding:1px 6px; border-radius:4px; font-weight:800; font-size:10.5px;">${cr.reward_status || 'Pending Disbursal'}</span></div>
                </div>
                <div style="display:flex; gap:8px; margin-top:10px; flex-wrap:wrap;">
                    ${crMob ? `<a href="https://wa.me/91${crMob}?text=${refWaMsg}" target="_blank" class="btn-secondary" style="padding:5px 12px; font-size:11.5px; color:#15803d; border-color:#86efac; background:#fff; font-weight:700; text-decoration:none; display:inline-flex; align-items:center; gap:4px;"><i data-lucide="message-circle" style="width:13px;"></i> WhatsApp Referrer</a>` : ''}
                    ${crMob ? `<a href="tel:${crMob}" class="btn-secondary" style="padding:5px 12px; font-size:11.5px; color:#0f172a; border-color:#cbd5e1; background:#fff; font-weight:700; text-decoration:none; display:inline-flex; align-items:center; gap:4px;"><i data-lucide="phone" style="width:13px;"></i> Call Referrer</a>` : ''}
                </div>
            </div>`;
        } else if (refName) {
            let refWaMsg = encodeURIComponent(`Hi ${refName}, updates regarding your referred client ${leadName}.`);
            let refPhotoHtml = lead.referrer_photo ? `<img src="${lead.referrer_photo}" style="width:40px; height:40px; border-radius:50%; object-fit:cover; border:2px solid #166534; flex-shrink:0;">` : `<div style="width:40px; height:40px; border-radius:50%; background:#166534; color:#fff; font-weight:800; font-size:14px; display:flex; align-items:center; justify-content:center; flex-shrink:0;">${refName.substring(0,1).toUpperCase()}</div>`;
            referrerCard = `
            <div class="drawer-section" style="background:#f0fdf4; border-color:#bbf7d0;">
                <div class="drawer-section-title" style="color:#166534;">
                    <i data-lucide="share-2" style="width:14px;"></i> REFERRAL / PARTNER SOURCE DETAILS
                </div>
                <div style="display:flex; align-items:center; gap:12px; margin-bottom:8px;">
                    ${refPhotoHtml}
                    <div>
                        <div style="font-size:13px; font-weight:800; color:#0f172a;">
                            ${refName} <span style="font-size:11px; font-weight:700; color:#166534; background:#dcfce7; padding:2px 6px; border-radius:4px; margin-left:4px;">${refType}</span>
                        </div>
                        ${refMobile ? `<div style="font-size:12px; color:#334155; margin-top:2px;">Contact: <b>${refMobile}</b></div>` : ''}
                    </div>
                </div>
                <div style="display:flex; gap:8px;">
                    ${refMobile ? `<a href="https://wa.me/91${refMobile}?text=${refWaMsg}" target="_blank" class="btn-secondary" style="padding:4px 10px; font-size:11.5px; color:#15803d; border-color:#86efac; background:#fff;"><i data-lucide="message-circle" style="width:12px;"></i> WhatsApp Referrer</a>` : ''}
                </div>
            </div>`;
        }

        let leadPhotoCard = '';
        if (lead.photo) {
            leadPhotoCard = `
            <div class="drawer-section" style="background:#f8fafc; border-color:#cbd5e1;">
                <div class="drawer-section-title"><i data-lucide="camera" style="width:14px;"></i> ATTACHED PROSPECT PHOTO / LEAD FORM DOCUMENT</div>
                <div style="text-align:center; padding:8px; background:#fff; border:1px solid #e2e8f0; border-radius:8px; margin-bottom:8px;">
                    <a href="${lead.photo}" target="_blank" title="Click to open full size photo">
                        <img src="${lead.photo}" style="max-width:100%; max-height:220px; border-radius:6px; object-fit:contain; box-shadow:0 2px 8px rgba(0,0,0,0.08);">
                    </a>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-size:11px; color:#64748b; font-weight:600;"><i data-lucide="paperclip" style="width:12px;"></i> Submitted with Lead Form</span>
                    <a href="${lead.photo}" target="_blank" class="btn-secondary" style="padding:4px 10px; font-size:11.5px; background:#fff;"><i data-lucide="external-link" style="width:12px;"></i> View Full Photo</a>
                </div>
            </div>`;
        }

        let timelineHtml = '';
        if (lead.timeline && lead.timeline.length > 0) {
            lead.timeline.forEach(t => {
                timelineHtml += `<div style="border-left:2px solid #0f172a; padding-left:10px; margin-bottom:10px;">
                    <div style="font-size:11px; font-weight:700; color:#64748b;">${t.created_at} • ${t.added_by_user || 'Staff'}</div>
                    <div style="font-size:12px; color:#0f172a; margin-top:2px;">${t.note_text || t.notes || ''}</div>
                </div>`;
            });
        } else {
            timelineHtml = `<div style="font-size:11.5px; color:#94a3b8;">No activity notes recorded yet.</div>`;
        }

        const drawerRm = (window.staffMembersList || []).find(sm => sm.username === lead.assigned_to);
        const drawerRmName = drawerRm ? (drawerRm.full_name || drawerRm.username) : (lead.assigned_to || '');
        let drawerInitials = 'RM';
        if (drawerRmName) {
            const parts = drawerRmName.trim().split(/\s+/);
            drawerInitials = parts.length >= 2 ? (parts[0][0] + parts[1][0]).toUpperCase() : parts[0].substring(0, 2).toUpperCase();
        }

        document.getElementById('drawerLeadBody').innerHTML = `
            ${referrerCard}
            ${leadPhotoCard}

            <div class="drawer-section">
                <div class="drawer-section-title"><i data-lucide="user" style="width:14px;"></i> PROSPECT DETAILS & CONTACT</div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; font-size:12.5px; margin-bottom:12px;">
                    <div><span style="color:#64748b; font-size:11px; display:block;">PRIMARY MOBILE</span> <b>${mobile}</b></div>
                    <div><span style="color:#64748b; font-size:11px; display:block;">ALT MOBILE</span> <b>${secMobile || 'None'}</b></div>
                    <div><span style="color:#64748b; font-size:11px; display:block;">EMAIL ADDRESS</span> <b>${email}</b></div>
                    <div><span style="color:#64748b; font-size:11px; display:block;">LOCATION</span> <b>${lead.location || 'Not Specified'}</b></div>
                    <div><span style="color:#64748b; font-size:11px; display:block;">REQUIREMENT</span> <b>${lead.requirement || 'Loan'}</b></div>
                    <div><span style="color:#64748b; font-size:11px; display:block;">AMOUNT</span> <b>${lead.loan_amount ? '₹ ' + Number(lead.loan_amount).toLocaleString('en-IN') : 'N/A'}</b></div>
                </div>
                <div style="display:flex; gap:8px;">
                    <a href="tel:${mobile}" class="btn-primary" style="flex:1; justify-content:center; padding:7px 12px; font-size:12px;"><i data-lucide="phone" style="width:14px;"></i> Call Prospect</a>
                    <a href="https://wa.me/91${mobile}?text=${waMsg}" target="_blank" class="btn-secondary" style="flex:1; justify-content:center; padding:7px 12px; font-size:12px;"><i data-lucide="message-circle" style="width:14px;"></i> WhatsApp</a>
                </div>
            </div>

            <div class="drawer-section">
                <div class="drawer-section-title"><i data-lucide="sliders" style="width:14px;"></i> PIPELINE CONTROL & ASSIGNMENT</div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:10px;">
                    <div>
                        <label style="font-size:11px; font-weight:700; color:#0f172a; display:block; margin-bottom:3px;">Pipeline Stage</label>
                        <select class="filter-select" style="width:100%; box-sizing:border-box;" onchange="quickUpdateStage(${lead.id}, this.value)">
                            <option value="New Lead" ${lead.stage === 'New Lead' ? 'selected' : ''}>New Lead</option>
                            <option value="Contacted" ${lead.stage === 'Contacted' ? 'selected' : ''}>Contacted</option>
                            <option value="Scheduled" ${lead.stage === 'Scheduled' ? 'selected' : ''}>Meeting Scheduled</option>
                            <option value="Interested" ${lead.stage === 'Interested' ? 'selected' : ''}>Qualified / Hot</option>
                            <option value="Converted" ${lead.stage === 'Converted' ? 'selected' : ''}>Converted to Client</option>
                            <option value="Rejected" ${lead.stage === 'Rejected' ? 'selected' : ''}>Disqualified</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:11px; font-weight:700; color:#0f172a; display:block; margin-bottom:3px;">Priority Level</label>
                        <select class="filter-select" style="width:100%; box-sizing:border-box;" onchange="quickUpdatePriority(${lead.id}, this.value)">
                            <option value="Hot" ${lead.priority === 'Hot' ? 'selected' : ''}>🔥 Hot</option>
                            <option value="Warm" ${lead.priority === 'Warm' ? 'selected' : ''}>⚡ Warm</option>
                            <option value="Cold" ${lead.priority === 'Cold' ? 'selected' : ''}>❄️ Cold</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label style="font-size:11px; font-weight:700; color:#0f172a; display:block; margin-bottom:4px;">Assigned Relationship Manager</label>
                    <button type="button" 
                            class="rm-assign-trigger-btn ${lead.assigned_to ? 'is-assigned' : 'is-unassigned'} no-print" 
                            style="width:100%; height:34px; justify-content:space-between; max-width:none; padding:4px 10px; font-size:12px;"
                            id="rm-drawer-btn-lead-${lead.id}"
                            onclick="openRmSearchPopover(this, ${lead.id}, '${escapeHtml(lead.assigned_to || '')}')">
                        <span style="display:inline-flex; align-items:center; gap:8px;">
                            <span class="rm-trigger-avatar ${lead.assigned_to ? '' : 'unassigned'}">${drawerInitials}</span>
                            <span class="rm-trigger-label" style="max-width:none; font-size:12px; font-weight:700;">${escapeHtml(drawerRmName || 'Assign RM')}</span>
                        </span>
                        <i data-lucide="chevron-down" class="rm-trigger-arrow"></i>
                    </button>
                </div>
            </div>

            <div class="drawer-section">
                <div class="drawer-section-title"><i data-lucide="message-square" style="width:14px;"></i> ADD INTERACTION NOTE</div>
                <textarea id="drawerNoteInput" class="filter-input" style="width:100%; box-sizing:border-box; height:54px; margin-bottom:8px; border:1px solid #cbd5e1; border-radius:8px;" placeholder="Write call summary or follow-up note..."></textarea>
                <button class="btn-primary" style="padding:6px 12px; font-size:12px;" onclick="submitDrawerNote(${lead.id})">Add Note</button>
            </div>

            <div class="drawer-section">
                <div class="drawer-section-title"><i data-lucide="history" style="width:14px;"></i> ENGAGEMENT TIMELINE</div>
                <div style="max-height:160px; overflow-y:auto; padding-right:4px;">${timelineHtml}</div>
            </div>`;

        document.getElementById('drawerFooterActions').innerHTML = `
            <button class="btn-primary" onclick="promoteLeadToApplicant(${lead.id}, '${leadName}')">🚀 Convert to Application</button>
        `;
        lucide.createIcons();
    });
}

function closeLeadDetailDrawer() {
    document.getElementById('drawerBackdrop').classList.remove('active');
    document.getElementById('leadDetailDrawer').classList.remove('active');
}

function submitDrawerNote(leadId) {
    const note = document.getElementById('drawerNoteInput').value.trim();
    if (!note) return;

    const fd = new FormData();
    fd.append('lead_id', leadId);
    fd.append('note', note);

    fetch('api.php?api=add_lead_note', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        openLeadDetailDrawer(leadId);
        loadLeads(currentLeadsPage);
    });
}

const kanbanStagesConfig = {
    'New Lead': {
        title: 'New Lead',
        shortTitle: 'New Lead',
        icon: 'sparkles',
        accentColor: '#2563eb',
        accentBg: '#eff6ff',
        emptyIcon: 'inbox'
    },
    'Contacted': {
        title: 'Contacted',
        shortTitle: 'Contacted',
        icon: 'phone-call',
        accentColor: '#0284c7',
        accentBg: '#f0f9ff',
        emptyIcon: 'phone-incoming'
    },
    'Scheduled': {
        title: 'Meeting Scheduled',
        shortTitle: 'Scheduled',
        icon: 'calendar-clock',
        accentColor: '#d97706',
        accentBg: '#fffbeb',
        emptyIcon: 'calendar'
    },
    'Interested': {
        title: 'Qualified / Hot 🔥',
        shortTitle: 'Qualified 🔥',
        icon: 'flame',
        accentColor: '#dc2626',
        accentBg: '#fef2f2',
        emptyIcon: 'check-circle-2'
    },
    'Converted': {
        title: 'Converted to Client 🚀',
        shortTitle: 'Converted 🚀',
        icon: 'award',
        accentColor: '#16a34a',
        accentBg: '#f0fdf4',
        emptyIcon: 'trophy'
    },
    'Rejected': {
        title: 'Disqualified',
        shortTitle: 'Disqualified',
        icon: 'x-circle',
        accentColor: '#64748b',
        accentBg: '#f1f5f9',
        emptyIcon: 'archive'
    }
};

function onKanbanDragStart(e, leadId, currentStage) {
    e.dataTransfer.setData('text/plain', String(leadId));
    e.dataTransfer.setData('sourceStage', currentStage);
    e.dataTransfer.effectAllowed = 'move';
    e.target.classList.add('is-dragging');
}

function onKanbanDragEnd(e) {
    e.target.classList.remove('is-dragging');
    document.querySelectorAll('.kanban-col').forEach(col => col.classList.remove('drag-over'));
}

function onKanbanColDragOver(e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    const col = e.currentTarget;
    if (!col.classList.contains('drag-over')) {
        col.classList.add('drag-over');
    }
}

function onKanbanColDragLeave(e) {
    e.currentTarget.classList.remove('drag-over');
}

function onKanbanColDrop(e, targetStage) {
    e.preventDefault();
    e.currentTarget.classList.remove('drag-over');
    const leadId = e.dataTransfer.getData('text/plain');
    const sourceStage = e.dataTransfer.getData('sourceStage');
    if (!leadId || sourceStage === targetStage) return;

    quickUpdateLeadStageFromKanban(leadId, targetStage);
}

function quickUpdateLeadStageFromKanban(leadId, targetStage) {
    const fd = new FormData();
    fd.append('id', leadId);
    fd.append('stage', targetStage);

    fetch('api.php?api=update_lead_stage', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            typeof showNotification === 'function' ? showNotification(`Stage updated to ${targetStage}!`, 'success') : true;
            loadKanbanBoard();
        } else {
            Swal.fire({ icon: 'error', title: 'Update Failed', text: d.error || 'Could not move lead' });
        }
    })
    .catch(() => {
        Swal.fire({ icon: 'error', title: 'Connection Error', text: 'Failed to update stage' });
    });
}

function loadKanbanBoard() {
    const search   = encodeURIComponent(document.getElementById('lead-search')?.value || '');
    const stage    = encodeURIComponent(document.getElementById('lead-filter-stage')?.value || '');
    const priority = encodeURIComponent(document.getElementById('lead-filter-priority')?.value || '');
    const rm       = window.isAdminUser ? encodeURIComponent(document.getElementById('lead-filter-staff')?.value || '') : '';
    const type     = encodeURIComponent(document.getElementById('lead-filter-type')?.value || '');
    const date     = encodeURIComponent(document.getElementById('lead-filter-date')?.value || '');

    const countLabel = document.getElementById('leadsResultCount');
    if (countLabel) countLabel.innerHTML = `<span style="display:inline-flex; align-items:center; gap:5px; color:#64748b;"><i data-lucide="loader" class="spin" style="width:12px;height:12px;"></i> Loading Pipeline...</span>`;

    fetch(`api.php?api=get_leads_kanban&search=${search}&stage=${stage}&priority=${priority}&assigned_to=${rm}&loan_type=${type}&date_range=${date}`)
    .then(r => r.json())
    .then(res => {
        if (!res.success || !res.kanban) return;
        const columns = res.kanban;
        const stageTotals = res.stage_totals || {};
        const totalLeadsCount = res.total_leads || 0;

        if (countLabel) {
            countLabel.innerHTML = `Showing <b>${totalLeadsCount}</b> Prospects across 6 Pipeline Stages`;
        }

        let gridHtml = '';

        Object.keys(kanbanStagesConfig).forEach(colKey => {
            const cfg = kanbanStagesConfig[colKey] || {
                title: colKey,
                shortTitle: colKey,
                icon: 'layers',
                accentColor: '#0f172a',
                accentBg: '#f8fafc',
                emptyIcon: 'inbox'
            };
            const cards = columns[colKey] || [];
            const colTotalAmt = stageTotals[colKey] || 0;
            const amtFormatted = colTotalAmt > 0 ? (colTotalAmt >= 10000000 ? `₹ ${(colTotalAmt / 10000000).toFixed(2)} Cr` : `₹ ${(colTotalAmt / 100000).toFixed(1)} L`) : '₹ 0';

            gridHtml += `
            <div class="kanban-col" data-stage="${colKey}" ondragover="onKanbanColDragOver(event)" ondragleave="onKanbanColDragLeave(event)" ondrop="onKanbanColDrop(event, '${colKey}')">
                <div class="kanban-col-header" style="border-top-color: ${cfg.accentColor};">
                    <div class="kanban-col-top">
                        <div class="kanban-col-title" title="${cfg.title}">
                            <span class="kanban-col-icon-wrap" style="color:${cfg.accentColor}; background:${cfg.accentBg};">
                                <i data-lucide="${cfg.icon}"></i>
                            </span>
                            <span>${cfg.shortTitle}</span>
                        </div>
                        <span class="kanban-col-count" style="background:${cfg.accentColor};">${cards.length}</span>
                    </div>
                    <div class="kanban-col-meta">
                        <span class="kanban-col-amount" title="Pipeline sum: ${amtFormatted}">${amtFormatted}</span>
                        <span>${cards.length} lead${cards.length === 1 ? '' : 's'}</span>
                    </div>
                </div>
                
                <div class="kanban-cards-list">`;

            if (cards.length === 0) {
                gridHtml += `
                <div class="kanban-empty-zone">
                    <div class="kanban-empty-icon"><i data-lucide="${cfg.emptyIcon}"></i></div>
                    <div class="kanban-empty-title">No leads</div>
                </div>`;
            } else {
                cards.forEach(card => {
                    const safeName = (card.name || card.lead_name || 'Prospect').replace(/'/g, "");
                    const mobile = card.mobile || '';
                    const company = card.company_name || 'Individual Prospect';
                    const prio = card.priority || 'Cold';
                    const prioLower = prio.toLowerCase();
                    const req = card.requirement || card.loan_type || 'Loan';
                    const waMsg = encodeURIComponent(`Hi ${safeName}, regarding your ${req} inquiry at BFS Circle.`);
                    
                    let prioIcon = '❄️';
                    if (prio === 'Hot') prioIcon = '🔥';
                    else if (prio === 'Warm') prioIcon = '⚡';

                    const avatarHtml = card.photo
                        ? `<div class="kanban-card-avatar"><img src="${card.photo}" alt="${safeName}"></div>`
                        : `<div class="kanban-card-avatar">${safeName.substring(0,1).toUpperCase()}</div>`;

                    let amtPill = '';
                    if (card.loan_amount) {
                        const amtNum = Number(card.loan_amount);
                        const lakhStr = formatLakhs(amtNum);
                        amtPill = `<span class="kanban-amount-pill" title="₹ ${amtNum.toLocaleString('en-IN')}">${lakhStr ? lakhStr.replace(/[()]/g, '') : '₹ ' + amtNum.toLocaleString('en-IN')}</span>`;
                    }

                    let partnerTag = '';
                    if (card.referrer_name) {
                        partnerTag = `
                        <div class="kanban-partner-row">
                            <span class="badge-partner badge-partner-dsa" style="font-size:8.5px; padding:1px 4px; max-width:100%; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="Partner: ${card.referrer_name}">
                                <i data-lucide="share-2" style="width:8px; height:8px; flex-shrink:0;"></i> ${card.referrer_name}
                            </span>
                        </div>`;
                    } else if (card.added_by && card.added_by !== 'direct') {
                        partnerTag = `
                        <div class="kanban-partner-row">
                            <span class="badge-partner badge-partner-direct" style="font-size:8.5px; padding:1px 4px; max-width:100%; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                <i data-lucide="user-check" style="width:8px; height:8px; flex-shrink:0;"></i> ${card.referrer_name || card.added_by}
                            </span>
                        </div>`;
                    }

                    gridHtml += `
                    <div class="kanban-card" draggable="true" data-lead-id="${card.id}" data-current-stage="${colKey}"
                         ondragstart="onKanbanDragStart(event, ${card.id}, '${colKey}')"
                         ondragend="onKanbanDragEnd(event)"
                         onclick="openLeadDetailDrawer(${card.id})">
                        
                        <!-- Top Row: Avatar + Name + Priority -->
                        <div class="kanban-card-top">
                            <div class="kanban-card-user">
                                ${avatarHtml}
                                <div class="kanban-card-name-box">
                                    <div class="kanban-card-name" title="${safeName}">${safeName}</div>
                                    <div class="kanban-card-sub" title="${company}">${company}</div>
                                </div>
                            </div>
                            <span class="kanban-prio-pill kanban-prio-${prioLower}">${prioIcon} ${prio}</span>
                        </div>

                        <!-- Loan Product & Amount Row -->
                        <div class="kanban-card-meta-row">
                            <span class="kanban-product-tag" title="${req}"><i data-lucide="tag"></i> ${req}</span>
                            ${amtPill || '<span style="font-size:9px; color:#94a3b8; font-weight:700;">TBD</span>'}
                        </div>

                        <!-- Partner Sourcing Badge -->
                        ${partnerTag}

                        <!-- Contact & One-Click Communication Row -->
                        <div class="kanban-contact-strip" onclick="event.stopPropagation()">
                            <span class="kanban-phone-num">
                                <i data-lucide="phone"></i> ${mobile || '—'}
                            </span>
                            <div class="kanban-quick-comm">
                                ${mobile ? `<a href="tel:${mobile}" class="kanban-comm-btn" title="Call Prospect"><i data-lucide="phone-outgoing"></i></a>` : ''}
                                ${mobile ? `<a href="https://wa.me/91${mobile}?text=${waMsg}" target="_blank" class="kanban-comm-btn wa" title="WhatsApp Message"><i data-lucide="message-circle"></i></a>` : ''}
                            </div>
                        </div>

                        <!-- Card Footer: RM, Quick Stage Select, and Actions -->
                        <div class="kanban-card-footer" onclick="event.stopPropagation()">
                            <span class="kanban-rm-tag ${window.isAdminUser ? 'is-clickable' : ''}" 
                                  title="Assigned RM: ${escapeHtml(card.assigned_to || 'Unassigned')} ${window.isAdminUser ? '(Click to search & assign)' : ''}"
                                  ${window.isAdminUser ? `onclick="openRmSearchPopover(this, ${card.id}, '${escapeHtml(card.assigned_to || '')}')"` : ''}>
                                <i data-lucide="user"></i> ${escapeHtml(card.assigned_to || 'Assign RM')}
                            </span>
                            
                            <div style="display:inline-flex; align-items:center; gap:2px;">
                                <select class="kanban-stage-quick-select" onchange="quickUpdateLeadStageFromKanban(${card.id}, this.value)" title="Move Stage">
                                    <option value="New Lead" ${colKey === 'New Lead' ? 'selected' : ''}>New</option>
                                    <option value="Contacted" ${colKey === 'Contacted' ? 'selected' : ''}>Contact</option>
                                    <option value="Scheduled" ${colKey === 'Scheduled' ? 'selected' : ''}>Meet</option>
                                    <option value="Interested" ${colKey === 'Interested' ? 'selected' : ''}>Qual</option>
                                    <option value="Converted" ${colKey === 'Converted' ? 'selected' : ''}>Conv</option>
                                    <option value="Rejected" ${colKey === 'Rejected' ? 'selected' : ''}>Disq</option>
                                </select>
                                
                                <button type="button" class="action-btn" style="width:20px;height:20px;" title="View 360° Drawer" onclick="openLeadDetailDrawer(${card.id})">
                                    <i data-lucide="eye" style="width:10px;height:10px;"></i>
                                </button>
                                <button type="button" class="action-btn action-btn-promote" style="width:20px;height:20px;" title="Promote to Loan Application" onclick="promoteLeadToApplicant(${card.id}, '${safeName}')">
                                    <i data-lucide="rocket" style="width:10px;height:10px;"></i>
                                </button>
                            </div>
                        </div>
                    </div>`;
                });
            }

            gridHtml += `</div></div>`;
        });

        document.getElementById('kanbanGrid').innerHTML = gridHtml;
        lucide.createIcons();
    })
    .catch(err => {
        console.warn('loadKanbanBoard error:', err);
        const grid = document.getElementById('kanbanGrid');
        if (grid) grid.innerHTML = `<div style="padding:40px; text-align:center; width:100%; color:#dc2626; font-weight:700;"><i data-lucide="alert-triangle" style="width:24px;height:24px;display:block;margin:0 auto 6px;"></i> Failed to load Pipeline. Please refresh.</div>`;
        lucide.createIcons();
    });
}

function promoteLeadToApplicant(id, name) {
    Swal.fire({
        title: 'Convert to Loan Application?',
        text: `Promote "${name}" into an active Loan Application file in BFS Circle CRM?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#0f172a',
        cancelButtonColor: '#64748b',
        confirmButtonText: '🚀 Yes, Convert Now'
    }).then(res => {
        if (res.isConfirmed) {
            const fd = new FormData();
            fd.append('id', id);
            fetch('api.php?api=promote_lead_to_applicant', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Lead Converted!',
                        text: `Generated Loan Application File #${d.loan_id}. You can track it in Search & Track / Applicants list.`,
                        confirmButtonColor: '#0f172a'
                    });
                    if (document.getElementById('leadDetailDrawer').classList.contains('active')) {
                        openLeadDetailDrawer(id);
                    }
                    loadLeads(currentLeadsPage);
                } else {
                    Swal.fire({ icon: 'error', title: 'Conversion Failed', text: d.error, confirmButtonColor: '#0f172a' });
                }
            });
        }
    });
}

function openPrintDeskModal() {
    const checkedCount = document.querySelectorAll('.lead-checkbox:checked').length;
    const notice = document.getElementById('checkedCountNotice');
    if (checkedCount > 0) {
        notice.innerText = `Print ${checkedCount} checked row(s) only`;
        document.getElementById('scopeChecked').checked = true;
    } else {
        notice.innerText = `No checkboxes selected (select rows in table first)`;
        document.getElementById('scopePage').checked = true;
    }
    document.getElementById('printOptionsModal').style.display = 'flex';
}

function updatePrintScopeUI() {
    const checkedVal = document.querySelector('input[name="print_scope"]:checked').value;
    document.getElementById('lblCheckedScope').style.background = checkedVal === 'checked' ? '#f8fafc' : '#fff';
    document.getElementById('lblPageScope').style.background = checkedVal === 'page' ? '#f8fafc' : '#fff';
}

async function executeSelectivePrint() {
    const generatedFor = document.getElementById('printForInput').value.trim();
    if (!generatedFor) {
        Swal.fire({ icon: 'warning', title: 'Recipient Required', text: 'Please specify report recipient name.', confirmButtonColor: '#0f172a' });
        return;
    }

    const purpose = document.getElementById('printPurposeInput').value.trim() || 'Lead Conversion Report';
    const scope = document.querySelector('input[name="print_scope"]:checked').value;

    let rowsToPrint = [];

    if (scope === 'checked') {
        const checkedIds = Array.from(document.querySelectorAll('.lead-checkbox:checked')).map(c => parseInt(c.value));
        rowsToPrint = windowAllLeads.filter(r => checkedIds.includes(parseInt(r.id)));
    } else {
        rowsToPrint = [...windowAllLeads];
    }

    const searchVal = document.getElementById('lead-search')?.value.trim() || '';
    const stageVal = document.getElementById('lead-filter-stage')?.value || '';
    const prioVal = document.getElementById('lead-filter-priority')?.value || '';
    const staffVal = document.getElementById('lead-filter-staff')?.value || '';
    const typeVal = document.getElementById('lead-filter-type')?.value || '';

    const filterParts = [];
    if (stageVal) filterParts.push(`Stage: ${stageVal}`);
    if (prioVal) filterParts.push(`Priority: ${prioVal}`);
    if (staffVal) filterParts.push(`Staff: ${staffVal}`);
    if (typeVal) filterParts.push(`Product: ${typeVal}`);
    if (searchVal) filterParts.push(`Search: "${searchVal}"`);
    const filterSummary = filterParts.length > 0 ? filterParts.join(' • ') : 'All Stages • All Staff • All Products';

    const auditFd = new FormData();
    auditFd.append('generated_for', generatedFor);
    auditFd.append('purpose', purpose);
    auditFd.append('records_count', rowsToPrint.length);
    auditFd.append('segment_name', 'Lead Pipeline');
    auditFd.append('filters_summary', filterSummary);

    try { await fetch('api.php?api=log_print_activity', { method: 'POST', body: auditFd }); } catch(e) {}

    const now = new Date();
    const dStr = now.toLocaleDateString('en-GB') + ' ' + now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    document.getElementById('printDateText').innerText = dStr;
    document.getElementById('printGeneratedForText').innerText = `${generatedFor} (${purpose})`;
    document.getElementById('printFilterSummary').innerText = filterSummary;
    document.getElementById('printRecordCount').innerText = rowsToPrint.length;

    renderTableRows(rowsToPrint);
    document.getElementById('printOptionsModal').style.display = 'none';

    setTimeout(() => {
        window.print();
        setTimeout(() => { renderCurrentPage(); }, 800);
    }, 300);
}

function openLeadsBulkUploadModal() {
    if (typeof openBulkUploadModal === 'function') openBulkUploadModal('leads');
}

document.addEventListener("DOMContentLoaded", () => {
    const sel = document.getElementById('leadsPerPageSelect');
    if (sel) sel.value = String(leadsPerPage);
    lucide.createIcons();
    loadLeads();

    // Setup Searchable RM Popover Listeners
    const searchInput = document.getElementById('rmPopoverSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            const clearBtn = document.getElementById('rmPopoverClearBtn');
            if (clearBtn) clearBtn.style.display = e.target.value ? 'block' : 'none';
            renderRmPopoverItems(e.target.value);
        });

        searchInput.addEventListener('keydown', (e) => {
            const items = Array.from(document.querySelectorAll('#rmPopoverList .rm-popover-item'));
            if (!items.length) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                popoverFocusedIndex = (popoverFocusedIndex + 1) % items.length;
                items.forEach((it, idx) => it.classList.toggle('is-focused', idx === popoverFocusedIndex));
                items[popoverFocusedIndex].scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                popoverFocusedIndex = (popoverFocusedIndex - 1 + items.length) % items.length;
                items.forEach((it, idx) => it.classList.toggle('is-focused', idx === popoverFocusedIndex));
                items[popoverFocusedIndex].scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (popoverFocusedIndex >= 0 && items[popoverFocusedIndex]) {
                    items[popoverFocusedIndex].click();
                } else if (items.length === 1) {
                    items[0].click();
                }
            }
        });
    }

    // Close popover when clicking outside
    document.addEventListener('click', (e) => {
        const popover = document.getElementById('rmSearchPopover');
        if (!popover || popover.style.display === 'none') return;
        if (!popover.contains(e.target) && (!activePopoverTriggerEl || !activePopoverTriggerEl.contains(e.target))) {
            closeRmSearchPopover();
        }
    });

    // Close popover on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeRmSearchPopover();
        }
    });

    // Close popover on window resize
    window.addEventListener('resize', () => {
        closeRmSearchPopover();
    });
});
</script>

<?php require_once 'footer.php'; ?>
