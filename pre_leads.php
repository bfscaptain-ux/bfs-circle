<?php
require_once 'config.php';
require_role(['Admin', 'RM']);

$is_admin = ($_SESSION['role'] ?? '') === 'Admin';
$current_page = 'pre_leads.php';
$page_title = $is_admin ? 'Raw Data Hub & RM Segment Allocation' : 'My Pre-Leads Raw Data Pool';
$page_subtitle = $is_admin ? 'Segment purchased raw data pools & assign selectively to RMs' : 'Manage your assigned raw prospects and unverified entries';
$hide_main_header = true;
require_once 'header.php';

// Query active RM & Admin staff members
$staff_members = $db->query("
    SELECT username, COALESCE(name, full_name, username) as full_name, role 
    FROM users 
    WHERE is_active = 1 
      AND LOWER(role) IN ('admin','staff','rm','manager','credit officer') 
      AND LOWER(role) NOT IN ('agent','builder','ca','partner','connector','dsa') 
    ORDER BY role ASC, username ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch KPI statistics
$total_raw = $db->query("SELECT COUNT(*) FROM pre_leads")->fetchColumn();
$total_segments = $db->query("SELECT COUNT(DISTINCT COALESCE(NULLIF(segment_name, ''), 'General Pool')) FROM pre_leads")->fetchColumn();
$unassigned_raw = $db->query("SELECT COUNT(*) FROM pre_leads WHERE assigned_to IS NULL OR assigned_to = ''")->fetchColumn();
$promoted_count = $db->query("SELECT COUNT(*) FROM pre_leads WHERE status IN ('Promoted', 'Converted')")->fetchColumn();
?>
<script>
window.staffMembersList = <?php echo json_encode($staff_members); ?>;
window.isAdminUser = <?php echo $is_admin ? 'true' : 'false'; ?>;
</script>
<style>
/* Executive Corporate Theme - BFS Circle Standard */
.pl-layout { width: 100%; margin: 0 auto; padding: 16px 20px; font-family: 'Inter', system-ui, -apple-system, sans-serif; color: #0f172a; box-sizing: border-box; }
@media (max-width: 768px) {
    .pl-layout { padding: 10px 8px; }
}

/* KPI Grid */
.kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin-bottom: 20px; }
@media (max-width: 640px) {
    .kpi-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
}
@media (max-width: 480px) {
    .kpi-grid { grid-template-columns: 1fr; }
}
.kpi-card { background: #ffffff; border-radius: 10px; padding: 18px 20px; border: 1px solid #e2e8f0; display: flex; align-items: center; gap: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); transition: 0.2s ease; }
.kpi-card:hover { border-color: #0f172a; transform: translateY(-1px); }
.kpi-icon { width: 42px; height: 42px; border-radius: 8px; background: #f8fafc; color: #0f172a; border: 1px solid #cbd5e1; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
.kpi-val { font-size: 22px; font-weight: 800; color: #0f172a; line-height: 1.2; }
.kpi-lbl { font-size: 11px; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 2px; }

/* Segmented View Switcher Control */
.view-switcher-group { display: inline-flex; background: #f1f5f9; padding: 3px; border-radius: 8px; border: 1px solid #cbd5e1; }
.view-mode-btn { background: transparent; color: #64748b; border: none; padding: 6px 14px; border-radius: 6px; font-size: 12px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: 0.2s; }
.view-mode-btn.active { background: #0f172a; color: #ffffff; box-shadow: 0 1px 2px rgba(0,0,0,0.1); }

/* Filter Bar - Compact Single Row */
.filter-bar { background: #ffffff; padding: 12px 16px; border-radius: 10px; border: 1px solid #e2e8f0; display: flex; gap: 10px; margin-bottom: 16px; align-items: center; flex-wrap: wrap; box-shadow: 0 1px 2px rgba(0,0,0,0.02); }
.filter-input { padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12.5px; font-weight: 600; color: #0f172a; outline: none; background: #f8fafc; transition: 0.2s; }
.filter-input:focus { border-color: #0f172a; background: #ffffff; }

/* Standard Buttons */
.btn-primary { background: #0f172a; color: #ffffff; border: 1px solid #0f172a; padding: 8px 16px; border-radius: 6px; font-weight: 700; font-size: 12.5px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: 0.2s; }
.btn-primary:hover { background: #1e293b; border-color: #1e293b; }
.btn-secondary { background: #ffffff; color: #0f172a; border: 1px solid #cbd5e1; padding: 8px 14px; border-radius: 6px; font-weight: 700; font-size: 12.5px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: 0.2s; }
.btn-secondary:hover { background: #f8fafc; border-color: #0f172a; }

.action-btn { width: 30px; height: 30px; border-radius: 6px; border: 1px solid #cbd5e1; background: #ffffff; color: #0f172a; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.15s; margin-right: 3px; }
.action-btn:hover { background: #0f172a; color: #ffffff; border-color: #0f172a; }
.action-btn-promote { background: #0f172a; color: #ffffff; border-color: #0f172a; }
.action-btn-promote:hover { background: #1e293b; }

/* Production Pipeline Stage Board Styling */
.kanban-col { background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 14px; min-height: 520px; display: flex; flex-direction: column; transition: all 0.2s ease; }
.kanban-col-header { font-size: 12px; font-weight: 800; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px; padding-bottom: 10px; margin-bottom: 12px; border-bottom: 3px solid #0f172a; display: flex; justify-content: space-between; align-items: center; }
.kanban-cards-list { flex: 1; min-height: 420px; border-radius: 8px; padding: 4px; transition: background 0.2s; }
.kanban-card { background: #ffffff; border: 1px solid #cbd5e1; border-radius: 10px; padding: 14px; margin-bottom: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); transition: all 0.2s ease; cursor: grab; user-select: none; position: relative; }
.kanban-card:hover { border-color: #0f172a; transform: translateY(-2px); box-shadow: 0 6px 14px rgba(0,0,0,0.08); }
.kanban-card:active { cursor: grabbing; opacity: 0.8; }

/* Table */
.pl-table { width: 100%; border-collapse: collapse; background: #ffffff; border-radius: 10px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
.pl-table th { background: #0f172a; padding: 12px 14px; text-align: left; font-size: 11.5px; font-weight: 700; color: #ffffff; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #0f172a; }
.pl-table td { padding: 12px 14px; font-size: 12.5px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
.pl-table tr:hover td { background: #f8fafc; }

/* Badges */
.badge-segment { display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; background: #0f172a; color: #ffffff; }
.badge-status { display: inline-flex; align-items: center; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; background: #f8fafc; color: #0f172a; border: 1px solid #cbd5e1; }

/* Modals */
.modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(3px); }
.modal-content { background: #ffffff; width: 100%; max-width: 580px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 20px 40px rgba(0,0,0,0.2); overflow: hidden; }
.modal-header { padding: 18px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #f8fafc; }
.modal-header h3 { margin: 0; font-size: 16px; color: #0f172a; font-weight: 800; display: flex; align-items: center; gap: 8px; }
.modal-body { padding: 20px; max-height: 75vh; overflow-y: auto; }
.modal-footer { padding: 16px 20px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 10px; }

/* Pagination */
.pagination { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; background: #ffffff; border-radius: 8px; margin-top: 16px; border: 1px solid #e2e8f0; }

.print-only { display: none !important; }

/* Executive Print Engine */
@media print {
    aside, nav, header, .sidebar, .sidebar-menu, .app-sidebar, .main-header, .top-bar, .page-header, .kpi-grid, .filter-bar, #bulkActionBar, .top-actions-row, .pagination, .modal-overlay, .btn-primary, .btn-secondary, .checkbox-col, .action-col, select, .no-print, [class*="sidebar"], [class*="navigation"] {
        display: none !important; visibility: hidden !important; width: 0 !important; height: 0 !important; position: absolute !important; left: -9999px !important; top: -9999px !important;
    }
    body, html, .main-content, #main-content, .content, .main-wrapper, #wrapper { margin: 0 !important; padding: 0 !important; background: #ffffff !important; color: #000000 !important; font-family: 'Inter', sans-serif !important; position: static !important; width: 100% !important; }
    .pl-layout { width: 100% !important; margin: 0 !important; padding: 0 !important; border: none !important; box-shadow: none !important; }
    .print-only { display: block !important; }
    span.print-only, div.print-only { display: inline-block !important; }
    .print-header-container { border-bottom: 2px solid #000000; padding-bottom: 6px; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: flex-end; }
    .print-header-title { font-size: 14pt; font-weight: 900; text-transform: uppercase; letter-spacing: 0.5px; color: #000000; }
    .print-header-sub { font-size: 8.5pt; font-weight: 700; color: #333333; margin-top: 1px; }
    .print-meta-info { text-align: right; font-size: 8pt; font-weight: 600; line-height: 1.25; color: #111111; }
    .pl-table { width: 100% !important; border-collapse: collapse !important; border: 1px solid #000000 !important; margin-top: 4px !important; background: #ffffff !important; box-shadow: none !important; }
    .pl-table th { background: #0f172a !important; color: #ffffff !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; padding: 4px 6px !important; font-size: 8pt !important; font-weight: 800 !important; border: 1px solid #000000 !important; text-transform: uppercase !important; white-space: nowrap !important; }
    .pl-table td { padding: 3px 6px !important; font-size: 8pt !important; border: 1px solid #cccccc !important; color: #000000 !important; line-height: 1.15 !important; vertical-align: middle !important; white-space: nowrap !important; }
    .pl-table tr { page-break-inside: avoid !important; height: 20px !important; }
    .badge-segment, .badge-status { background: transparent !important; color: #000000 !important; border: none !important; padding: 0 !important; font-size: 8pt !important; font-weight: 700 !important; }
}
</style>

<div class="pl-layout">

    <!-- Executive Print Letterhead -->
    <div id="printReportHeader" class="print-only" style="margin-bottom: 8px;">
        <div class="print-header-container">
            <div>
                <div class="print-header-title">BFS FINANCIAL SERVICES</div>
                <div class="print-header-sub">RAW PRE-LEADS & DATA SEGMENT EXECUTIVE REPORT</div>
            </div>
            <div class="print-meta-info">
                <div>Print Date: <span id="printDateText">25-09-2026</span></div>
                <div>Active Filters: <span id="printFilterSummary">All Segments | All Statuses</span></div>
                <div>Records Printed: <span id="printRecordCount">0</span></div>
            </div>
        </div>
        <div style="background:#0f172a !important; color:#ffffff !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; padding:6px 12px; border-radius:4px; font-size:10pt; font-weight:900; letter-spacing:0.5px; display:flex; justify-content:space-between; align-items:center; border: 1.5px solid #000000;">
            <span>📋 ASSIGNED SHEET FOR: <span id="printGeneratedForText" style="text-decoration:underline; font-size:11pt; text-transform:uppercase;">GENERAL FILE POOL</span></span>
            <span style="font-size:8.5pt; font-weight:700; opacity:0.95;">ISSUED BY: <?php echo strtoupper(htmlspecialchars($_SESSION['username'] ?? 'User')); ?></span>
        </div>
    </div>

    <!-- Top KPI Grid (Hidden on Print) -->
    <div class="kpi-grid no-print">
        <div class="kpi-card">
            <div class="kpi-icon"><i data-lucide="database"></i></div>
            <div>
                <div class="kpi-val" id="kpi-total"><?php echo number_format($total_raw); ?></div>
                <div class="kpi-lbl">Total Raw Data</div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon"><i data-lucide="layers"></i></div>
            <div>
                <div class="kpi-val" id="kpi-segments"><?php echo number_format($total_segments); ?></div>
                <div class="kpi-lbl">Active Data Segments</div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon"><i data-lucide="user-x"></i></div>
            <div>
                <div class="kpi-val" id="kpi-unassigned"><?php echo number_format($unassigned_raw); ?></div>
                <div class="kpi-lbl">Unassigned Raw Pool</div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon"><i data-lucide="check-circle-2"></i></div>
            <div>
                <div class="kpi-val" id="kpi-promoted"><?php echo number_format($promoted_count); ?></div>
                <div class="kpi-lbl">Promoted to Pipeline</div>
            </div>
        </div>
    </div>

    <!-- Executive Action Toolbar (Hidden on Print) -->
    <div class="top-actions-row no-print" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:12px;">
        <!-- Left: Segmented Control View Switcher -->
        <div class="view-switcher-group">
            <button class="view-mode-btn active" id="btnViewTable" onclick="switchPreLeadView('table')"><i data-lucide="list" style="width:14px;"></i> Table View</button>
            <button class="view-mode-btn" id="btnViewKanban" onclick="switchPreLeadView('kanban')"><i data-lucide="kanban" style="width:14px;"></i> Pipeline Stage Board</button>
        </div>

        <!-- Right: Professional Action Button Bar -->
        <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
            <button class="btn-primary" onclick="openTelecallerDesk()"><i data-lucide="phone-call" style="width:14px;"></i> Dialer Console</button>
            <button class="btn-secondary" onclick="openVendorRoiAnalyticsModal()"><i data-lucide="bar-chart-3" style="width:14px;"></i> Vendor Analytics</button>
            <button class="btn-secondary" onclick="openPrintDeskModal()"><i data-lucide="printer" style="width:14px;"></i> Print Report</button>
            <button class="btn-secondary" onclick="openPrintAuditHistoryModal()"><i data-lucide="history" style="width:14px;"></i> Print Audit</button>
            <?php if ($is_admin): ?>
            <button class="btn-secondary" onclick="openSegmentAllocationModal()"><i data-lucide="sliders" style="width:14px;"></i> Segment Allocation</button>
            <button class="btn-secondary" onclick="document.getElementById('importModal').style.display='flex'"><i data-lucide="upload" style="width:14px;"></i> Import Batch Data</button>
            <?php endif; ?>
            <button class="btn-primary" onclick="document.getElementById('addModal').style.display='flex'"><i data-lucide="plus" style="width:14px;"></i> New Pre-Lead</button>
        </div>
    </div>

    <!-- Filter Bar (Hidden on Print) -->
    <div class="filter-bar no-print">
        <div style="position:relative; flex: 1; min-width: 220px;">
            <i data-lucide="search" style="position:absolute; left:10px; top:50%; transform:translateY(-50%); width:15px; color:#94a3b8;"></i>
            <input type="text" id="searchInput" class="filter-input" placeholder="Search Prospect Name, Mobile, Segment..." style="padding-left:32px; width:100%; box-sizing:border-box;" oninput="debounceLoad()">
        </div>
        
        <select id="segmentFilter" class="filter-input" style="width: 210px; flex-shrink: 0;" onchange="loadData()">
            <option value="">All Data Segments</option>
            <option value="General Pool">General Pool</option>
        </select>

        <select id="statusFilter" class="filter-input" style="width: 170px; flex-shrink: 0;" onchange="loadData()">
            <option value="">All Statuses</option>
            <option value="Not Contacted">New Prospect</option>
            <option value="Follow Up">In Discussion</option>
            <option value="Interested">Qualified / Interested</option>
            <option value="Promoted">Promoted to CRM</option>
            <option value="Not Interested">Not Interested</option>
            <option value="Junk">Disqualified</option>
        </select>

        <?php if ($is_admin): ?>
        <select id="rmFilter" class="filter-input" style="width: 180px; flex-shrink: 0;" onchange="loadData()">
            <option value="">All Staff / RMs</option>
            <option value="UNASSIGNED">Unassigned Only</option>
            <?php foreach ($staff_members as $sm): ?>
                <option value="<?php echo htmlspecialchars($sm['username']); ?>"><?php echo htmlspecialchars($sm['full_name'] ?: $sm['username']); ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>

        <button class="btn-secondary" style="padding:8px 12px; flex-shrink: 0;" onclick="resetFilters()">
            <i data-lucide="refresh-cw" style="width:13px;"></i> Reset
        </button>
    </div>

    <!-- Professional Bulk Actions Bar (Hidden on Print) -->
    <div id="bulkActionBar" class="no-print" style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:10px; padding:10px 16px; margin-bottom:14px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
        <div style="font-size:12.5px; font-weight:700; color:#0f172a; display:flex; align-items:center; gap:8px;">
            <i data-lucide="check-square" style="width:16px;"></i> <span id="checkedCountBadge" style="background:#0f172a; color:#fff; border-radius:20px; padding:2px 8px; font-size:11px;">0</span> Bulk Operations:
        </div>
        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
            <select id="bulk_target_rm" class="filter-input" style="width:160px; background:#fff;">
                <option value="">-- Select RM --</option>
                <?php foreach ($staff_members as $sm): ?>
                    <option value="<?php echo htmlspecialchars($sm['username']); ?>"><?php echo htmlspecialchars($sm['full_name'] ?: $sm['username']); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="button" onclick="executeCheckedBulkAction('assign')" class="btn-primary" style="padding:6px 12px; font-size:12px;">Assign RM</button>
            
            <input type="text" id="bulk_target_segment" class="filter-input" placeholder="Target Segment..." style="width:140px; background:#fff;">
            <button type="button" onclick="executeCheckedBulkAction('segment')" class="btn-secondary" style="padding:6px 12px; font-size:12px;">Apply Segment</button>

            <select id="bulk_target_status" class="filter-input" style="width:140px; background:#fff;">
                <option value="">-- Select Status --</option>
                <option value="Not Contacted">New Prospect</option>
                <option value="Follow Up">In Discussion</option>
                <option value="Interested">Qualified</option>
                <option value="Junk">Disqualified</option>
            </select>
            <button type="button" onclick="executeCheckedBulkAction('status')" class="btn-secondary" style="padding:6px 12px; font-size:12px;">Set Status</button>

            <?php if ($is_admin): ?>
            <button type="button" onclick="executeCheckedBulkAction('delete')" class="btn-secondary" style="color:#991b1b; border-color:#fca5a5; background:#fff; padding:6px 12px; font-size:12px;"><i data-lucide="trash-2" style="width:13px; vertical-align:middle;"></i> Remove Selected</button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Table View Container -->
    <div id="tableViewContainer">
        <div class="table-responsive">
            <table class="pl-table">
                <thead>
                    <tr>
                        <?php if ($is_admin): ?>
                        <th style="width:36px; text-align:center;" class="checkbox-col no-print"><input type="checkbox" id="selectAllPreleads" onclick="toggleSelectAllPreleads(this)" style="cursor:pointer;"></th>
                        <?php endif; ?>
                        <th>Prospect Name</th>
                        <th>Contact Info</th>
                        <th>Segment & Source</th>
                        <th>Assigned RM</th>
                        <th>Lead Status</th>
                        <th>Engagement Log</th>
                        <th style="text-align:right;" class="action-col no-print">Actions</th>
                    </tr>
                </thead>
                <tbody id="plBody">
                    <tr><td colspan="<?php echo $is_admin ? 8 : 7; ?>" style="text-align:center; padding:40px; color:#64748b;">Loading raw data pool...</td></tr>
                </tbody>
            </table>
        </div>

        <div class="pagination no-print">
            <button id="prevBtn" class="btn-secondary" onclick="changePage(-1)">Previous</button>
            <span id="pageInfo" style="font-size:12.5px; font-weight:700; color:#0f172a;">Page 1</span>
            <button id="nextBtn" class="btn-secondary" onclick="changePage(1)">Next</button>
        </div>
    </div>

    <!-- Visual Kanban View Container (Hidden by default) -->
    <div id="kanbanViewContainer" class="no-print" style="display:none; margin-bottom:20px;">
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap:14px;" id="kanbanGrid">
            <!-- Dynamic Kanban Columns rendered via JS -->
        </div>
    </div>

</div>

<!-- Modal 1: Add Raw Pre-Lead -->
<div id="addModal" class="modal-overlay no-print">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i data-lucide="user-plus"></i> New Prospect Entry</h3>
            <button onclick="document.getElementById('addModal').style.display='none'" style="background:none;border:none;cursor:pointer;color:#0f172a;"><i data-lucide="x"></i></button>
        </div>
        <form onsubmit="savePreleadSingle(event)">
            <div class="modal-body">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div>
                        <label style="font-size:11.5px; font-weight:700; color:#0f172a; display:block; margin-bottom:4px;">Full Name *</label>
                        <input type="text" name="name" class="filter-input" style="width:100%; box-sizing:border-box;" required placeholder="Prospect Full Name">
                    </div>
                    <div>
                        <label style="font-size:11.5px; font-weight:700; color:#0f172a; display:block; margin-bottom:4px;">Mobile Number *</label>
                        <input type="text" name="mobile" class="filter-input" style="width:100%; box-sizing:border-box;" required placeholder="10-Digit Mobile">
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div>
                        <label style="font-size:11.5px; font-weight:700; color:#0f172a; display:block; margin-bottom:4px;">Email Address</label>
                        <input type="email" name="email" class="filter-input" style="width:100%; box-sizing:border-box;" placeholder="name@domain.com">
                    </div>
                    <div>
                        <label style="font-size:11.5px; font-weight:700; color:#0f172a; display:block; margin-bottom:4px;">Data Segment Tag</label>
                        <input type="text" name="segment_name" class="filter-input" style="width:100%; box-sizing:border-box;" placeholder="e.g. Salaried Pool, HDFC Campaign" value="General Pool">
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div>
                        <label style="font-size:11.5px; font-weight:700; color:#0f172a; display:block; margin-bottom:4px;">Product Interest</label>
                        <select name="loan_type" class="filter-input" style="width:100%; box-sizing:border-box;">
                            <option value="Personal Loan">Personal Loan</option>
                            <option value="Business Loan">Business Loan</option>
                            <option value="Home Loan">Home Loan</option>
                            <option value="LAP">Loan Against Property</option>
                            <option value="Car Loan">Car Loan</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:11.5px; font-weight:700; color:#0f172a; display:block; margin-bottom:4px;">Monthly Income (₹)</label>
                        <input type="number" name="monthly_income" class="filter-input" style="width:100%; box-sizing:border-box;" placeholder="e.g. 60000">
                    </div>
                </div>

                <?php if ($is_admin): ?>
                <div style="margin-bottom:12px;">
                    <label style="font-size:11.5px; font-weight:700; color:#0f172a; display:block; margin-bottom:4px;">Assign Relationship Manager</label>
                    <select name="assigned_to" class="filter-input" style="width:100%; box-sizing:border-box;">
                        <option value="">-- Unassigned --</option>
                        <?php foreach ($staff_members as $sm): ?>
                            <option value="<?php echo htmlspecialchars($sm['username']); ?>"><?php echo htmlspecialchars($sm['full_name'] ?: $sm['username']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div style="margin-bottom:12px;">
                    <label style="font-size:11.5px; font-weight:700; color:#0f172a; display:block; margin-bottom:4px;">Notes / Background</label>
                    <textarea name="notes" class="filter-input" style="width:100%; box-sizing:border-box; height:60px;" placeholder="Additional context or remarks..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="document.getElementById('addModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn-primary">Save Prospect</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 1B: Edit Pre-Lead -->
<div id="editModal" class="modal-overlay no-print">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i data-lucide="edit-3"></i> Edit Prospect Details</h3>
            <button onclick="document.getElementById('editModal').style.display='none'" style="background:none;border:none;cursor:pointer;color:#0f172a;"><i data-lucide="x"></i></button>
        </div>
        <form onsubmit="updatePreleadSingle(event)">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-body">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div>
                        <label style="font-size:11.5px; font-weight:700; color:#0f172a; display:block; margin-bottom:4px;">Full Name *</label>
                        <input type="text" name="name" id="edit_name" class="filter-input" style="width:100%; box-sizing:border-box;" required placeholder="Prospect Full Name">
                    </div>
                    <div>
                        <label style="font-size:11.5px; font-weight:700; color:#0f172a; display:block; margin-bottom:4px;">Mobile Number *</label>
                        <input type="text" name="mobile" id="edit_mobile" class="filter-input" style="width:100%; box-sizing:border-box;" required placeholder="10-Digit Mobile">
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div>
                        <label style="font-size:11.5px; font-weight:700; color:#0f172a; display:block; margin-bottom:4px;">Email Address</label>
                        <input type="email" name="email" id="edit_email" class="filter-input" style="width:100%; box-sizing:border-box;" placeholder="name@domain.com">
                    </div>
                    <div>
                        <label style="font-size:11.5px; font-weight:700; color:#0f172a; display:block; margin-bottom:4px;">Company / Business Name</label>
                        <input type="text" name="company_name" id="edit_company_name" class="filter-input" style="width:100%; box-sizing:border-box;" placeholder="Company Name">
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div>
                        <label style="font-size:11.5px; font-weight:700; color:#0f172a; display:block; margin-bottom:4px;">Data Segment Tag</label>
                        <input type="text" name="segment_name" id="edit_segment_name" class="filter-input" style="width:100%; box-sizing:border-box;" placeholder="e.g. Salaried Pool">
                    </div>
                    <div>
                        <label style="font-size:11.5px; font-weight:700; color:#0f172a; display:block; margin-bottom:4px;">Product Interest</label>
                        <select name="loan_type" id="edit_loan_type" class="filter-input" style="width:100%; box-sizing:border-box;">
                            <option value="Personal Loan">Personal Loan</option>
                            <option value="Business Loan">Business Loan</option>
                            <option value="Home Loan">Home Loan</option>
                            <option value="LAP">Loan Against Property</option>
                            <option value="Car Loan">Car Loan</option>
                        </select>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div>
                        <label style="font-size:11.5px; font-weight:700; color:#0f172a; display:block; margin-bottom:4px;">Monthly Income (₹)</label>
                        <input type="number" name="monthly_income" id="edit_monthly_income" class="filter-input" style="width:100%; box-sizing:border-box;" placeholder="e.g. 60000">
                    </div>
                    <div>
                        <label style="font-size:11.5px; font-weight:700; color:#0f172a; display:block; margin-bottom:4px;">Status</label>
                        <select name="status" id="edit_status" class="filter-input" style="width:100%; box-sizing:border-box;">
                            <option value="Not Contacted">New Prospect</option>
                            <option value="Follow Up">In Discussion</option>
                            <option value="Interested">Qualified / Interested</option>
                            <option value="Not Interested">Not Interested</option>
                            <option value="Junk">Disqualified</option>
                        </select>
                    </div>
                </div>

                <?php if ($is_admin): ?>
                <div style="margin-bottom:12px;">
                    <label style="font-size:11.5px; font-weight:700; color:#0f172a; display:block; margin-bottom:4px;">Assign Relationship Manager</label>
                    <select name="assigned_to" id="edit_assigned_to" class="filter-input" style="width:100%; box-sizing:border-box;">
                        <option value="">-- Unassigned --</option>
                        <?php foreach ($staff_members as $sm): ?>
                            <option value="<?php echo htmlspecialchars($sm['username']); ?>"><?php echo htmlspecialchars($sm['full_name'] ?: $sm['username']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div style="margin-bottom:12px;">
                    <label style="font-size:11.5px; font-weight:700; color:#0f172a; display:block; margin-bottom:4px;">Notes / Background</label>
                    <textarea name="notes" id="edit_notes" class="filter-input" style="width:100%; box-sizing:border-box; height:60px;" placeholder="Additional context or remarks..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="document.getElementById('editModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn-primary">Update Prospect</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 2: Bulk CSV Import -->
<div id="importModal" class="modal-overlay no-print">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i data-lucide="upload-cloud"></i> Import Raw Data Batch</h3>
            <button onclick="document.getElementById('importModal').style.display='none'" style="background:none;border:none;cursor:pointer;color:#0f172a;"><i data-lucide="x"></i></button>
        </div>
        <form id="bulkCsvForm" onsubmit="submitBulkCsv(event)" enctype="multipart/form-data">
            <div class="modal-body">
                <div style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:8px; padding:12px; margin-bottom:16px; font-size:12px; color:#475569;">
                    <b>💡 Smart Batch Processing:</b> Upload any standard CSV file. Mobile numbers will be automatically deduplicated against existing records.
                </div>

                <div style="margin-bottom:14px;">
                    <label style="font-size:11.5px; font-weight:700; color:#0f172a; display:block; margin-bottom:4px;">Select Data File (CSV) *</label>
                    <input type="file" name="csv_file" accept=".csv" required class="filter-input" style="width:100%; box-sizing:border-box; background:#fff;">
                </div>

                <div style="margin-bottom:14px;">
                    <label style="font-size:11.5px; font-weight:700; color:#0f172a; display:block; margin-bottom:4px;">Data Segment Tag *</label>
                    <input type="text" name="import_segment" class="filter-input" style="width:100%; box-sizing:border-box;" placeholder="e.g., HDFC Salary Data, Meta Lead Gen" required>
                </div>

                <div style="margin-bottom:14px;">
                    <label style="font-size:11.5px; font-weight:700; color:#0f172a; display:block; margin-bottom:4px;">Vendor / Data Channel</label>
                    <input type="text" name="import_source" class="filter-input" style="width:100%; box-sizing:border-box;" placeholder="e.g. JustDial, Meta Campaign">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="document.getElementById('importModal').style.display='none'">Cancel</button>
                <button type="submit" id="importBtnSubmit" class="btn-primary">Process Import</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 3: Segment Allocation Desk -->
<div id="segmentDeskModal" class="modal-overlay no-print">
    <div class="modal-content" style="max-width:620px;">
        <div class="modal-header">
            <h3><i data-lucide="sliders"></i> RM Segment Allocation Console</h3>
            <button onclick="document.getElementById('segmentDeskModal').style.display='none'" style="background:none;border:none;cursor:pointer;color:#0f172a;"><i data-lucide="x"></i></button>
        </div>
        <form onsubmit="executeSegmentAllocation(event)">
            <div class="modal-body">
                <div style="margin-bottom:14px;">
                    <label style="font-size:12px; font-weight:700; color:#0f172a; display:block; margin-bottom:4px;">1. Select Data Segment Batch</label>
                    <select id="desk_segment_select" class="filter-input" style="width:100%; box-sizing:border-box;" required onchange="updateSegmentAllocationCount()">
                        <option value="">-- Select Target Data Segment --</option>
                    </select>
                    <div id="segmentCountNotice" style="font-size:11.5px; color:#475569; margin-top:4px; font-weight:600;"></div>
                </div>

                <div style="margin-bottom:14px;">
                    <label style="font-size:12px; font-weight:700; color:#0f172a; display:block; margin-bottom:6px;">2. Distribution Mode</label>
                    <div style="display:flex; gap:16px;">
                        <label style="font-size:12.5px; cursor:pointer; font-weight:600;">
                            <input type="radio" name="alloc_mode" value="single" checked onclick="toggleAllocMode('single')"> Direct RM Assignment
                        </label>
                        <label style="font-size:12.5px; cursor:pointer; font-weight:600;">
                            <input type="radio" name="alloc_mode" value="round_robin" onclick="toggleAllocMode('round_robin')"> Equal Round-Robin Auto Distribution
                        </label>
                    </div>
                </div>

                <div id="singleRmContainer" style="margin-bottom:14px;">
                    <label style="font-size:11.5px; font-weight:700; color:#0f172a; display:block; margin-bottom:4px;">Select Target Relationship Manager</label>
                    <select id="desk_single_rm" class="filter-input" style="width:100%; box-sizing:border-box;">
                        <option value="">-- Select Target RM --</option>
                        <?php foreach ($staff_members as $sm): ?>
                            <option value="<?php echo htmlspecialchars($sm['username']); ?>"><?php echo htmlspecialchars($sm['full_name'] ?: $sm['username']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="roundRobinContainer" style="display:none; margin-bottom:14px;">
                    <label style="font-size:11.5px; font-weight:700; color:#0f172a; display:block; margin-bottom:6px;">Select RMs for Round-Robin Distribution:</label>
                    <div style="max-height:140px; overflow-y:auto; border:1px solid #cbd5e1; border-radius:6px; padding:10px; background:#f8fafc;">
                        <?php foreach ($staff_members as $sm): ?>
                            <label style="display:block; font-size:12px; margin-bottom:6px; cursor:pointer;">
                                <input type="checkbox" class="rr-rm-check" value="<?php echo htmlspecialchars($sm['username']); ?>" checked> <?php echo htmlspecialchars($sm['full_name'] ?: $sm['username']); ?> (<?php echo htmlspecialchars($sm['role']); ?>)
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div style="margin-bottom:10px;">
                    <label style="font-size:12px; cursor:pointer; font-weight:600;">
                        <input type="checkbox" id="desk_only_unassigned" value="1" checked> Limit to UNASSIGNED prospects only
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="document.getElementById('segmentDeskModal').style.display='none'">Cancel</button>
                <button type="submit" id="executeDeskBtn" class="btn-primary"><i data-lucide="zap" style="width:14px;"></i> Execute Distribution</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 4: Selective Print Hub -->
<div id="printOptionsModal" class="modal-overlay no-print">
    <div class="modal-content" style="max-width:520px;">
        <div class="modal-header">
            <h3><i data-lucide="printer"></i> Executive Report Generator</h3>
            <button onclick="document.getElementById('printOptionsModal').style.display='none'" style="background:none;border:none;cursor:pointer;color:#0f172a;"><i data-lucide="x"></i></button>
        </div>
        <div class="modal-body">
            <div style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:8px; padding:12px; margin-bottom:16px;">
                <div style="font-size:12px; font-weight:800; color:#0f172a; margin-bottom:8px;">Report Audit Metadata</div>
                <div style="margin-bottom:8px;">
                    <label style="font-size:11px; font-weight:700; color:#0f172a; display:block; margin-bottom:3px;">Issued To (Recipient Name / Team) *</label>
                    <input type="text" id="printForInput" class="filter-input" style="width:100%; box-sizing:border-box; background:#fff;" placeholder="e.g. Telecalling Desk, Branch Office" required>
                </div>
                <div>
                    <label style="font-size:11px; font-weight:700; color:#0f172a; display:block; margin-bottom:3px;">Campaign / Purpose Note</label>
                    <input type="text" id="printPurposeInput" class="filter-input" style="width:100%; box-sizing:border-box; background:#fff;" placeholder="Daily Work List" value="Pre-Leads Work List">
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
                        <div style="font-weight:700; color:#0f172a; font-size:13px;">Current Page Only</div>
                        <div style="font-size:11.5px; color:#64748b;">Print records displayed on current view</div>
                    </div>
                </label>
                <label style="border:1px solid #cbd5e1; border-radius:8px; padding:10px 14px; display:flex; align-items:center; gap:12px; cursor:pointer; background:#ffffff;" id="lblAllScope">
                    <input type="radio" name="print_scope" value="all" id="scopeAll" onchange="updatePrintScopeUI()">
                    <div>
                        <div style="font-weight:700; color:#0f172a; font-size:13px;">Entire Filtered Pool</div>
                        <div style="font-size:11.5px; color:#64748b;">Fetch & print all matching records across pages</div>
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

<!-- Modal 5: Dialer Console (Telecaller Queue) -->
<div id="telecallerDeskModal" class="modal-overlay no-print">
    <div class="modal-content" style="max-width:720px; border-radius:14px;">
        <div class="modal-header" style="background:#0f172a; color:#fff; padding:16px 20px;">
            <h3 style="color:#fff; font-size:16px;"><i data-lucide="phone-call" style="color:#38bdf8;"></i> Telecaller Dialer Console</h3>
            <span id="queueProgressBadge" style="background:#1e293b; color:#38bdf8; border:1px solid #334155; padding:4px 12px; border-radius:20px; font-size:12px; font-weight:700;">Queue Loading...</span>
            <button onclick="closeTelecallerDesk()" style="background:none;border:none;cursor:pointer;color:#fff;"><i data-lucide="x"></i></button>
        </div>
        <div class="modal-body" style="padding:22px;" id="tcDeskBody">
            <div style="text-align:center; padding:40px; color:#64748b;">Loading Lead Queue...</div>
        </div>
    </div>
</div>

<!-- Modal 6: Vendor Analytics Desk -->
<div id="vendorRoiModal" class="modal-overlay no-print">
    <div class="modal-content" style="max-width:920px; border-radius:14px;">
        <div class="modal-header" style="background:#0f172a; color:#fff;">
            <h3 style="color:#fff;"><i data-lucide="bar-chart-3" style="color:#38bdf8;"></i> Data Source & Vendor ROI Analytics</h3>
            <button onclick="document.getElementById('vendorRoiModal').style.display='none'" style="background:none;border:none;cursor:pointer;color:#fff;"><i data-lucide="x"></i></button>
        </div>
        <div class="modal-body" style="padding:20px;">
            <div id="vendorRoiTableContainer">
                <table class="pl-table" style="font-size:12px;">
                    <thead>
                        <tr>
                            <th>Batch / Source</th>
                            <th>Vendor</th>
                            <th>Purchase Date</th>
                            <th>Total Leads</th>
                            <th>Cost (₹)</th>
                            <th>Cost / Lead</th>
                            <th>Promoted</th>
                            <th>Conversion %</th>
                            <th>Effective Cost / Qualified</th>
                        </tr>
                    </thead>
                    <tbody id="vendorRoiBody">
                        <tr><td colspan="9" style="text-align:center; padding:20px;">Loading Vendor Analytics...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal 7: Engagement Console -->
<div id="callModal" class="modal-overlay no-print">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i data-lucide="clipboard-list"></i> Log Prospect Engagement</h3>
            <button onclick="document.getElementById('callModal').style.display='none'" style="background:none;border:none;cursor:pointer;color:#0f172a;"><i data-lucide="x"></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="call_lead_id">
            
            <div style="background:#f8fafc; padding:12px 14px; border-radius:8px; border:1px solid #cbd5e1; margin-bottom:14px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                    <span style="font-size:11px; color:#64748b; font-weight:700; text-transform:uppercase;">Recommended Pitch & Context</span>
                    <button type="button" onclick="generateAiPitchForLead()" style="background:#0f172a; color:#fbbf24; border:none; border-radius:6px; padding:3px 8px; font-size:10.5px; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:4px;">
                        <i data-lucide="sparkles" style="width:12px;height:12px;"></i> ✨ AI Pitch Assist (Ollama)
                    </button>
                </div>
                <div id="scriptContent" style="font-size:13px; color:#0f172a; font-weight:600; margin-top:4px;">
                    "Hi [Name], calling from BFS Circle regarding your financial requirements..."
                </div>
            </div>
            
            <div style="margin-bottom:12px;">
                <label style="font-size:11.5px; font-weight:700; color:#0f172a; display:block; margin-bottom:4px;">Call Outcome Status</label>
                <select id="call_status" class="filter-input" style="width:100%;">
                    <option value="Follow Up">In Discussion / Followup</option>
                    <option value="Interested">Qualified / Interested</option>
                    <option value="Not Interested">Not Interested</option>
                    <option value="Junk">Disqualified / Invalid</option>
                </select>
            </div>
            
            <div style="margin-bottom:12px;">
                <label style="font-size:11.5px; font-weight:700; color:#0f172a; display:block; margin-bottom:4px;">Next Follow-up Schedule</label>
                <input type="datetime-local" id="call_followup" class="filter-input" style="width:100%;">
            </div>
            
            <div style="margin-bottom:12px;">
                <label style="font-size:11.5px; font-weight:700; color:#0f172a; display:block; margin-bottom:4px;">Discussion Notes</label>
                <textarea id="call_notes" class="filter-input" style="width:100%; resize:vertical; height:60px;" placeholder="Enter interaction summary..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="document.getElementById('callModal').style.display='none'">Cancel</button>
            <button class="btn-primary" onclick="submitCallLog()">Save Engagement</button>
        </div>
    </div>
</div>

<script>
let currentPage = 1;
let searchTimeout;
let segmentsDataList = [];
let currentDataCount = 0;
let currentLoadedRowsData = [];
let currentViewMode = 'table';
let telecallerQueue = [];
let telecallerQueueIndex = 0;

function switchPreLeadView(mode) {
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
        loadData();
    }
}

function debounceLoad() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => { currentPage = 1; if (currentViewMode === 'kanban') loadKanbanBoard(); else loadData(); }, 400);
}

function loadData() {
    const s = encodeURIComponent(document.getElementById('searchInput').value);
    const seg = encodeURIComponent(document.getElementById('segmentFilter').value);
    const stat = encodeURIComponent(document.getElementById('statusFilter').value);
    const rm = window.isAdminUser ? encodeURIComponent(document.getElementById('rmFilter').value) : '';
    
    fetch(`api.php?api=get_preleads&page=${currentPage}&search=${s}&segment_name=${seg}&status=${stat}&assigned_to=${rm}`)
    .then(r => r.json())
    .then(res => {
        if (!res.success) return;

        currentDataCount = res.total || 0;
        currentLoadedRowsData = res.data || [];

        if (res.segments_summary) {
            segmentsDataList = res.segments_summary;
            populateSegmentDropdown(res.segments_summary);
        }

        renderTableBody(currentLoadedRowsData);
        document.getElementById('pageInfo').innerText = `Page ${res.page} of ${res.total_pages || 1} (${res.total} total)`;
        document.getElementById('prevBtn').disabled = res.page <= 1;
        document.getElementById('nextBtn').disabled = res.page >= (res.total_pages||1);
    });
}

function renderTableBody(dataRows) {
    let html = '';
    const colSpan = window.isAdminUser ? 8 : 7;
    if(!dataRows || dataRows.length === 0) {
        html = `<tr><td colspan="${colSpan}" style="text-align:center; padding:40px; color:#64748b; font-weight:600;">No pre-leads found matching your search filter.</td></tr>`;
    } else {
        dataRows.forEach(row => {
            let segTag = row.segment_name || 'General Pool';
            let safeName = (row.name || 'Prospect').replace(/'/g, "");
            let safeMobile = row.mobile || '';
            let waMsg = encodeURIComponent(`Hi ${safeName}, this is from BFS Circle regarding your financial requirements.`);
            let checkboxCell = window.isAdminUser ? `<td style="text-align:center;" class="checkbox-col no-print"><input type="checkbox" class="prelead-checkbox" value="${row.id}" onchange="updateCheckedCount()"></td>` : '';
            let callCount = row.call_count || 0;

            let assignedRmDisplay = row.assigned_to || 'Unassigned';
            let rmCell = '';
            if (window.isAdminUser) {
                rmCell = `<td>
                    <span class="print-only" style="font-weight:700; color:#000;">${assignedRmDisplay}</span>
                    <select class="no-print" onchange="assignSinglePrelead(${row.id}, this.value)" style="padding:4px 8px; font-size:11.5px; border:1px solid #cbd5e1; border-radius:5px; background:#fff; font-weight:600; color:#0f172a; cursor:pointer;">
                        <option value="">-- Unassigned --</option>
                        ${window.staffMembersList.map(sm => `<option value="${sm.username}" ${sm.username === row.assigned_to ? 'selected' : ''}>${sm.full_name || sm.username}</option>`).join('')}
                    </select>
                </td>`;
            } else {
                rmCell = `<td><span style="font-weight:700; color:#0f172a;">${assignedRmDisplay}</span></td>`;
            }

            let statusBadge = `<span class="badge-status">${row.status || 'New Prospect'}</span>`;
            if (row.status === 'Promoted' || row.status === 'Converted') {
                statusBadge = `<span class="badge-segment" style="background:#0f172a;">Promoted</span>`;
            }

            html += `
            <tr data-lead-id="${row.id}">
                ${checkboxCell}
                <td>
                    <div class="no-print" style="font-weight:800; color:#0f172a;">${row.name || 'Unknown'}</div>
                    <div class="no-print" style="font-size:11px; color:#64748b;">${row.company_name || 'Individual Prospect'}</div>
                    <div class="print-only" style="font-weight:800; color:#000;">${row.name || 'Unknown'} ${row.company_name ? `(${row.company_name})` : ''}</div>
                </td>
                <td>
                    <div class="no-print" style="font-weight:700; color:#0f172a; display:flex; align-items:center; gap:5px; line-height:1.3;"><i data-lucide="phone" style="width:13px; height:13px; flex-shrink:0;"></i> <span>${safeMobile || '-'}</span></div>
                    <div class="no-print" style="font-size:11px; color:#64748b; margin-top:2px; line-height:1.2;">${row.email || 'No Email'}</div>
                    <div class="print-only" style="font-weight:700; color:#000;">${safeMobile} ${row.email ? `| ${row.email}` : ''}</div>
                </td>
                <td>
                    <div class="no-print">
                        <span class="badge-segment"><i data-lucide="tag" style="width:11px;"></i> ${segTag}</span>
                        ${row.source ? `<div style="font-size:10.5px; color:#64748b; margin-top:2px;">Source: ${row.source}</div>` : ''}
                    </div>
                    <div class="print-only" style="font-weight:700; color:#000;">${segTag} ${row.source ? `(${row.source})` : ''}</div>
                </td>
                ${rmCell}
                <td>${statusBadge}</td>
                <td>
                    <div class="no-print" style="font-size:11.5px; font-weight:700; color:#0f172a;">Calls: ${callCount}</div>
                    <div class="no-print" style="font-size:10.5px; color:#64748b;">Last: ${row.last_called_at || 'Never'}</div>
                    <div class="print-only" style="font-weight:600; color:#000;">Calls: ${callCount}</div>
                </td>
                <td style="text-align:right; white-space:nowrap;" class="action-col no-print">
                    <a href="tel:${safeMobile}" class="action-btn" title="Call Prospect"><i data-lucide="phone" style="width:13px;"></i></a>
                    <a href="https://wa.me/91${safeMobile}?text=${waMsg}" target="_blank" class="action-btn" title="WhatsApp Message"><i data-lucide="message-circle" style="width:13px;"></i></a>
                    <button class="action-btn" onclick="openCallConsole(${row.id}, '${safeName}', '${row.loan_type || 'Loan'}')" title="Log Engagement Notes"><i data-lucide="clipboard-list" style="width:13px;"></i></button>
                    <button class="action-btn" onclick="openEditPreleadModal(${row.id})" title="Edit Prospect"><i data-lucide="edit-3" style="width:13px;"></i></button>
                    <button class="action-btn action-btn-promote" onclick="promotePreleadToApplicant(${row.id}, '${safeName}')" title="Promote to CRM Pipeline"><i data-lucide="rocket" style="width:13px;"></i></button>
                    <button class="action-btn" style="color:#ef4444; border-color:#fca5a5;" onclick="deletePreleadSingle(${row.id}, '${safeName}')" title="Delete Pre-Lead"><i data-lucide="trash-2" style="width:13px;"></i></button>
                </td>
            </tr>`;
        });
    }

    document.getElementById('plBody').innerHTML = html;
    updateCheckedCount();
    lucide.createIcons();
}

let draggedPreleadId = null;

const colMetaConfig = {
    'New': { title: 'New Prospect', color: '#3b82f6', bg: '#eff6ff', badgeBg: '#dbeafe', badgeColor: '#1e40af' },
    'In Followup': { title: 'In Discussion', color: '#f59e0b', bg: '#fffbeb', badgeBg: '#fef3c7', badgeColor: '#92400e' },
    'Callback Scheduled': { title: 'Callback Scheduled', color: '#8b5cf6', bg: '#f5f3ff', badgeBg: '#ede9fe', badgeColor: '#5b21b6' },
    'Interested': { title: 'Qualified / Hot', color: '#10b981', bg: '#ecfdf5', badgeBg: '#d1fae5', badgeColor: '#065f46' },
    'Promoted': { title: 'Promoted to CRM', color: '#0f172a', bg: '#f8fafc', badgeBg: '#f1f5f9', badgeColor: '#0f172a' },
    'Junk': { title: 'Disqualified', color: '#ef4444', bg: '#fef2f2', badgeBg: '#fee2e2', badgeColor: '#991b1b' }
};

function onKanbanDragStart(e, id) {
    draggedPreleadId = id;
    e.dataTransfer.setData('text/plain', id);
    e.dataTransfer.effectAllowed = 'move';
    setTimeout(() => { if (e.target) e.target.style.opacity = '0.4'; }, 0);
}

function onKanbanDragEnd(e) {
    if (e.target) e.target.style.opacity = '1';
}

function onKanbanDragOver(e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    const list = e.currentTarget;
    list.style.background = '#f1f5f9';
    list.style.border = '2px dashed #0f172a';
}

function onKanbanDragLeave(e) {
    const list = e.currentTarget;
    list.style.background = 'transparent';
    list.style.border = 'none';
}

function onKanbanDrop(e, targetStatus) {
    e.preventDefault();
    const list = e.currentTarget;
    list.style.background = 'transparent';
    list.style.border = 'none';
    
    if (!draggedPreleadId) return;

    const leadId = draggedPreleadId;
    draggedPreleadId = null;

    const fd = new FormData();
    fd.append('id', leadId);
    fd.append('status', targetStatus);

    fetch('api.php?api=update_prelead_status', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            const toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 1800,
                timerProgressBar: true
            });
            toast.fire({
                icon: 'success',
                title: `Lead moved to ${colMetaConfig[targetStatus]?.title || targetStatus}`
            });
            loadKanbanBoard();
        } else {
            Swal.fire('Error', res.error || 'Failed to update stage', 'error');
        }
    });
}

function loadKanbanBoard() {
    const s = encodeURIComponent(document.getElementById('searchInput').value);
    const seg = encodeURIComponent(document.getElementById('segmentFilter').value);
    const rm = window.isAdminUser ? encodeURIComponent(document.getElementById('rmFilter').value) : '';
    
    fetch(`api.php?api=get_pre_leads_kanban&segment=${seg}&assigned_to=${rm}&search=${s}`)
    .then(r => r.json())
    .then(res => {
        if (!res.success || !res.kanban) return;
        const columns = res.kanban;
        let gridHtml = '';
        
        Object.keys(columns).forEach(colKey => {
            const cards = columns[colKey] || [];
            const meta = colMetaConfig[colKey] || { title: colKey, color: '#0f172a', bg: '#f8fafc', badgeBg: '#e2e8f0', badgeColor: '#0f172a' };
            
            gridHtml += `
            <div class="kanban-col" style="border-top: 4px solid ${meta.color};">
                <div class="kanban-col-header">
                    <span style="display:flex; align-items:center; gap:6px;">
                        <span style="width:8px; height:8px; border-radius:50%; background:${meta.color}; display:inline-block;"></span>
                        ${meta.title}
                    </span>
                    <span style="background:${meta.badgeBg}; color:${meta.badgeColor}; font-weight:800; border-radius:12px; padding:2px 9px; font-size:11px; border:1px solid ${meta.color}40;">${cards.length}</span>
                </div>
                
                <div class="kanban-cards-list" 
                     ondragover="onKanbanDragOver(event)" 
                     ondragleave="onKanbanDragLeave(event)" 
                     ondrop="onKanbanDrop(event, '${colKey}')">`;
                
                if (cards.length === 0) {
                    gridHtml += `
                        <div style="text-align:center; padding:30px 10px; border:1.5px dashed #cbd5e1; border-radius:8px; background:#ffffff; margin-top:6px;">
                            <i data-lucide="inbox" style="width:24px; height:24px; color:#cbd5e1; margin-bottom:6px;"></i>
                            <div style="font-size:11px; font-weight:600; color:#94a3b8;">No prospects in stage</div>
                            <div style="font-size:10px; color:#cbd5e1; margin-top:2px;">Drag leads here</div>
                        </div>`;
                } else {
                    cards.forEach(card => {
                        let safeName = (card.name || 'Prospect').replace(/'/g, "");
                        let safeMobile = card.mobile || '';
                        let waMsg = encodeURIComponent(`Hi ${safeName}, this is from BFS Circle regarding your financial requirements.`);

                        gridHtml += `
                        <div class="kanban-card" draggable="true" ondragstart="onKanbanDragStart(event, ${card.id})" ondragend="onKanbanDragEnd(event)">
                            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:6px;">
                                <div style="font-weight:800; font-size:13.5px; color:#0f172a; line-height:1.2;">${card.name || 'Unknown'}</div>
                                <span class="badge-segment" style="font-size:10px; padding:2px 6px;">${card.segment_name || 'General'}</span>
                            </div>
                            
                            ${card.company_name ? `<div style="font-size:11px; font-weight:600; color:#64748b; margin-bottom:6px;">🏢 ${card.company_name}</div>` : ''}
                            
                            <div style="font-size:12px; font-weight:700; color:#1e293b; margin-bottom:4px; display:flex; align-items:center; gap:5px;">
                                <i data-lucide="phone" style="width:12px; height:12px; color:#0f172a;"></i> ${safeMobile || '-'}
                            </div>

                            <div style="font-size:11px; color:#64748b; margin-bottom:10px; display:flex; flex-wrap:wrap; gap:6px;">
                                ${card.loan_type ? `<span style="background:#f1f5f9; padding:2px 6px; border-radius:4px; font-weight:600;">${card.loan_type}</span>` : ''}
                                ${card.monthly_income ? `<span style="background:#f1f5f9; padding:2px 6px; border-radius:4px; font-weight:600;">₹${Number(card.monthly_income).toLocaleString('en-IN')}/mo</span>` : ''}
                            </div>

                            <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid #f1f5f9; padding-top:10px; margin-top:6px;">
                                <div style="font-size:10.5px; font-weight:700; color:#475569; display:flex; align-items:center; gap:4px;">
                                    <i data-lucide="user" style="width:11px; height:11px;"></i> ${card.assigned_to || 'Unassigned'}
                                </div>
                                <div style="display:flex; gap:3px;">
                                    <a href="tel:${safeMobile}" class="action-btn" style="width:26px; height:26px;" title="Call"><i data-lucide="phone" style="width:12px;"></i></a>
                                    <a href="https://wa.me/91${safeMobile}?text=${waMsg}" target="_blank" class="action-btn" style="width:26px; height:26px;" title="WhatsApp"><i data-lucide="message-circle" style="width:12px;"></i></a>
                                    <button class="action-btn" style="width:26px; height:26px;" onclick="openCallConsole(${card.id}, '${safeName}', '${card.loan_type || 'Loan'}')" title="Log Engagement"><i data-lucide="clipboard-list" style="width:12px;"></i></button>
                                    <button class="action-btn" style="width:26px; height:26px;" onclick="openEditPreleadModal(${card.id})" title="Edit Prospect"><i data-lucide="edit-3" style="width:12px;"></i></button>
                                    <button class="action-btn action-btn-promote" style="width:26px; height:26px;" onclick="promotePreleadToApplicant(${card.id}, '${safeName}')" title="Promote to CRM"><i data-lucide="rocket" style="width:12px;"></i></button>
                                    <button class="action-btn" style="width:26px; height:26px; color:#ef4444; border-color:#fca5a5;" onclick="deletePreleadSingle(${card.id}, '${safeName}')" title="Delete Prospect"><i data-lucide="trash-2" style="width:12px;"></i></button>
                                </div>
                            </div>
                        </div>`;
                    });
                }
                
            gridHtml += `</div></div>`;
        });
        
        document.getElementById('kanbanGrid').innerHTML = gridHtml;
        lucide.createIcons();
    });
}

function openTelecallerDesk() {
    const rm = window.isAdminUser ? document.getElementById('rmFilter').value : '';
    fetch(`api.php?api=get_telecaller_queue&rm=${encodeURIComponent(rm)}`)
    .then(r => r.json())
    .then(res => {
        if (!res.success || !res.queue || res.queue.length === 0) {
            Swal.fire({ icon: 'info', title: 'Queue Empty', text: 'No pending prospects in call queue.', confirmButtonColor: '#0f172a' });
            return;
        }
        telecallerQueue = res.queue;
        telecallerQueueIndex = 0;
        document.getElementById('telecallerDeskModal').style.display = 'flex';
        renderTelecallerCurrentLead();
    });
}

function renderTelecallerCurrentLead() {
    if (telecallerQueueIndex >= telecallerQueue.length) {
        document.getElementById('tcDeskBody').innerHTML = `
            <div style="text-align:center; padding:50px 20px;">
                <div style="font-size:44px; margin-bottom:12px;">✅</div>
                <h3 style="font-size:18px; font-weight:800; color:#0f172a;">Dialer Queue Completed</h3>
                <p style="color:#64748b; font-size:13px; margin-bottom:20px;">All active prospects in your queue have been processed.</p>
                <button class="btn-primary" onclick="closeTelecallerDesk()">Close Console</button>
            </div>`;
        document.getElementById('queueProgressBadge').innerText = 'Queue Finished';
        return;
    }

    const lead = telecallerQueue[telecallerQueueIndex];
    document.getElementById('queueProgressBadge').innerText = `Prospect ${telecallerQueueIndex + 1} of ${telecallerQueue.length}`;

    let safeName = (lead.name || 'Prospect').replace(/'/g, "");
    let safeMobile = lead.mobile || '';
    let waMsg = encodeURIComponent(`Hi ${safeName}, calling from BFS Circle regarding your inquiry for ${lead.loan_type || 'financial services'}.`);

    document.getElementById('tcDeskBody').innerHTML = `
        <div style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:10px; padding:16px; margin-bottom:16px;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px;">
                <div>
                    <h2 style="margin:0; font-size:18px; font-weight:800; color:#0f172a;">${lead.name || 'Unknown'}</h2>
                    <div style="font-size:12.5px; color:#64748b; margin-top:2px;">${lead.company_name ? `${lead.company_name} • ` : ''}${lead.city || 'City N/A'}</div>
                </div>
                <span class="badge-segment" style="font-size:12px; padding:5px 10px;">${lead.segment_name || 'General Pool'}</span>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; border-top:1px solid #e2e8f0; padding-top:12px; font-size:12.5px;">
                <div><span style="color:#64748b; font-size:11px; display:block;">MOBILE</span> <b>${safeMobile}</b></div>
                <div><span style="color:#64748b; font-size:11px; display:block;">LOAN TYPE</span> <b>${lead.loan_type || 'Personal Loan'}</b></div>
                <div><span style="color:#64748b; font-size:11px; display:block;">MONTHLY INCOME</span> <b>${lead.monthly_income ? '₹' + Number(lead.monthly_income).toLocaleString() : 'N/A'}</b></div>
            </div>
        </div>

        <div style="display:flex; gap:10px; margin-bottom:18px;">
            <a href="tel:${safeMobile}" class="btn-primary" style="flex:1; justify-content:center; padding:12px; font-size:13.5px;">
                <i data-lucide="phone-call" style="width:16px;"></i> Call Prospect (${safeMobile})
            </a>
            <a href="https://wa.me/91${safeMobile}?text=${waMsg}" target="_blank" class="btn-secondary" style="flex:1; justify-content:center; padding:12px; font-size:13.5px;">
                <i data-lucide="message-circle" style="width:16px;"></i> WhatsApp Message
            </a>
        </div>

        <div style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:16px;">
            <div style="font-size:12px; font-weight:800; color:#0f172a; margin-bottom:8px;">LOG CALL OUTCOME</div>
            
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                <div>
                    <label style="font-size:11px; font-weight:700; color:#0f172a; display:block; margin-bottom:4px;">Outcome Status *</label>
                    <select id="tc_status" class="filter-input" style="width:100%; box-sizing:border-box;">
                        <option value="Follow Up">In Discussion / Followup</option>
                        <option value="Interested">Qualified / Hot Prospect</option>
                        <option value="Callback Scheduled">Callback Scheduled</option>
                        <option value="Not Interested">Not Interested</option>
                        <option value="Junk">Disqualified / Invalid</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:11px; font-weight:700; color:#0f172a; display:block; margin-bottom:4px;">Next Followup Date</label>
                    <input type="datetime-local" id="tc_followup" class="filter-input" style="width:100%; box-sizing:border-box;">
                </div>
            </div>

            <div style="margin-bottom:14px;">
                <label style="font-size:11px; font-weight:700; color:#0f172a; display:block; margin-bottom:4px;">Discussion Summary</label>
                <textarea id="tc_notes" class="filter-input" style="width:100%; box-sizing:border-box; height:54px;" placeholder="Enter call discussion notes..."></textarea>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:center;">
                <button class="btn-secondary" onclick="telecallerQueueIndex++; renderTelecallerCurrentLead();">Skip Prospect ➔</button>
                <div style="display:flex; gap:8px;">
                    <button class="btn-secondary" onclick="promotePreleadToApplicant(${lead.id}, '${safeName}')">Promote to CRM Pipeline</button>
                    <button class="btn-primary" onclick="submitTelecallerLeadLog(${lead.id})">Save & Continue ➔</button>
                </div>
            </div>
        </div>`;
    lucide.createIcons();
}

function submitTelecallerLeadLog(leadId) {
    const stat = document.getElementById('tc_status').value;
    const fdate = document.getElementById('tc_followup').value;
    const notes = document.getElementById('tc_notes').value;

    const fd = new FormData();
    fd.append('id', leadId);
    fd.append('status', stat);
    fd.append('followup_date', fdate);
    fd.append('notes', notes);

    fetch('api.php?api=log_pre_lead_call', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            telecallerQueueIndex++;
            renderTelecallerCurrentLead();
        }
    });
}

function closeTelecallerDesk() {
    document.getElementById('telecallerDeskModal').style.display = 'none';
    loadData();
}

function openVendorRoiAnalyticsModal() {
    fetch('api.php?api=get_purchased_batches_analytics')
    .then(r => r.json())
    .then(d => {
        let html = '';
        if (d.success && d.batches && d.batches.length > 0) {
            d.batches.forEach(b => {
                html += `<tr>
                    <td><b>${b.batch_name || b.batch_id}</b></td>
                    <td>${b.vendor_name || 'Direct Import'}</td>
                    <td>${b.purchase_date || b.created_at.substring(0,10)}</td>
                    <td><b>${b.total_leads}</b></td>
                    <td>₹${Number(b.purchase_amount || 0).toLocaleString()}</td>
                    <td>₹${b.cost_per_lead}</td>
                    <td><span class="badge-segment">${b.promoted_count}</span></td>
                    <td><b>${b.conversion_pct}%</b></td>
                    <td><b>₹${b.effective_cost_per_promoted}</b></td>
                </tr>`;
            });
        } else {
            html = `<tr><td colspan="9" style="text-align:center; padding:20px; color:#64748b;">No data batches registered yet. Upload batch files to view analytics.</td></tr>`;
        }
        document.getElementById('vendorRoiBody').innerHTML = html;
        document.getElementById('vendorRoiModal').style.display = 'flex';
    });
}

function updateCheckedCount() {
    const count = document.querySelectorAll('.prelead-checkbox:checked').length;
    document.getElementById('checkedCountBadge').innerText = count;
}

function executeCheckedBulkAction(actionType) {
    const checked = Array.from(document.querySelectorAll('.prelead-checkbox:checked')).map(cb => parseInt(cb.value));
    if (checked.length === 0) {
        Swal.fire({ icon: 'warning', title: 'Selection Required', text: 'Select at least one prospect checkbox.', confirmButtonColor: '#0f172a' });
        return;
    }

    let targetVal = '';
    if (actionType === 'assign') {
        targetVal = document.getElementById('bulk_target_rm').value;
        if (!targetVal) { Swal.fire({ icon: 'warning', title: 'RM Required', text: 'Select an RM from dropdown.', confirmButtonColor: '#0f172a' }); return; }
    } else if (actionType === 'segment') {
        targetVal = document.getElementById('bulk_target_segment').value.trim();
        if (!targetVal) { Swal.fire({ icon: 'warning', title: 'Segment Required', text: 'Enter target segment name.', confirmButtonColor: '#0f172a' }); return; }
    } else if (actionType === 'status') {
        targetVal = document.getElementById('bulk_target_status').value;
        if (!targetVal) { Swal.fire({ icon: 'warning', title: 'Status Required', text: 'Select a status from dropdown.', confirmButtonColor: '#0f172a' }); return; }
    } else if (actionType === 'delete') {
        if (!confirm(`Are you sure you want to remove ${checked.length} selected prospects?`)) return;
    }

    const fd = new FormData();
    fd.append('action_type', actionType);
    fd.append('ids', JSON.stringify(checked));
    fd.append('target_value', targetVal);

    fetch('api.php?api=bulk_action_pre_leads', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            Swal.fire({ icon: 'success', title: 'Bulk Action Complete', text: d.message, confirmButtonColor: '#0f172a' });
            loadData();
        } else {
            Swal.fire({ icon: 'error', title: 'Action Failed', text: d.error, confirmButtonColor: '#0f172a' });
        }
    });
}

function openPrintDeskModal() {
    const checkedCount = document.querySelectorAll('.prelead-checkbox:checked').length;
    const notice = document.getElementById('checkedCountNotice');
    if (checkedCount > 0) {
        notice.innerText = `Print ${checkedCount} checked row(s) only`;
        document.getElementById('scopeChecked').checked = true;
    } else {
        notice.innerText = `No checkboxes selected (select rows in table first)`;
        document.getElementById('scopePage').checked = true;
    }
    updatePrintScopeUI();
    document.getElementById('printOptionsModal').style.display = 'flex';
}

function updatePrintScopeUI() {
    const checkedVal = document.querySelector('input[name="print_scope"]:checked').value;
    document.getElementById('lblCheckedScope').style.background = checkedVal === 'checked' ? '#f8fafc' : '#fff';
    document.getElementById('lblPageScope').style.background = checkedVal === 'page' ? '#f8fafc' : '#fff';
    document.getElementById('lblAllScope').style.background = checkedVal === 'all' ? '#f8fafc' : '#fff';
}

async function executeSelectivePrint() {
    const generatedFor = document.getElementById('printForInput').value.trim();
    if (!generatedFor) {
        Swal.fire({ icon: 'warning', title: 'Recipient Required', text: 'Please specify report recipient name.', confirmButtonColor: '#0f172a' });
        return;
    }

    const purpose = document.getElementById('printPurposeInput').value.trim() || 'Pre-Leads Work List';
    const scope = document.querySelector('input[name="print_scope"]:checked').value;

    let rowsToPrint = [];

    if (scope === 'checked') {
        const checkedIds = Array.from(document.querySelectorAll('.prelead-checkbox:checked')).map(c => parseInt(c.value));
        rowsToPrint = currentLoadedRowsData.filter(r => checkedIds.includes(parseInt(r.id)));
    } else if (scope === 'page') {
        rowsToPrint = [...currentLoadedRowsData];
    } else if (scope === 'all') {
        Swal.fire({ title: 'Preparing Print Data...', text: 'Fetching filtered records for report', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        const s = encodeURIComponent(document.getElementById('searchInput').value);
        const seg = encodeURIComponent(document.getElementById('segmentFilter').value);
        const stat = encodeURIComponent(document.getElementById('statusFilter').value);
        const rm = window.isAdminUser ? encodeURIComponent(document.getElementById('rmFilter').value) : '';
        
        try {
            const r = await fetch(`api.php?api=get_preleads&page=1&limit=9999&search=${s}&segment_name=${seg}&status=${stat}&assigned_to=${rm}`);
            const res = await r.json();
            Swal.close();
            rowsToPrint = (res.success && res.data) ? res.data : [...currentLoadedRowsData];
        } catch(e) {
            Swal.close();
            rowsToPrint = [...currentLoadedRowsData];
        }
    }

    const segVal = document.getElementById('segmentFilter').value || 'All Segments';
    const statVal = document.getElementById('statusFilter').value || 'All Statuses';
    const filterSummary = `${segVal} | ${statVal}`;

    const auditFd = new FormData();
    auditFd.append('generated_for', generatedFor);
    auditFd.append('purpose', purpose);
    auditFd.append('records_count', rowsToPrint.length);
    auditFd.append('segment_name', segVal);
    auditFd.append('filters_summary', filterSummary);

    try { await fetch('api.php?api=log_print_activity', { method: 'POST', body: auditFd }); } catch(e) {}

    const now = new Date();
    const dStr = now.toLocaleDateString('en-GB') + ' ' + now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    document.getElementById('printDateText').innerText = dStr;
    document.getElementById('printGeneratedForText').innerText = `${generatedFor} (${purpose})`;
    document.getElementById('printFilterSummary').innerText = filterSummary;
    document.getElementById('printRecordCount').innerText = rowsToPrint.length;

    renderTableBody(rowsToPrint);
    document.getElementById('printOptionsModal').style.display = 'none';

    setTimeout(() => {
        window.print();
        setTimeout(() => { renderTableBody(currentLoadedRowsData); }, 800);
    }, 300);
}

function openPrintAuditHistoryModal() {
    fetch('api.php?api=get_print_logs&limit=30')
    .then(r => r.json())
    .then(d => {
        let html = '';
        if (d.success && d.logs && d.logs.length > 0) {
            d.logs.forEach(l => {
                html += `<tr>
                    <td><b>${l.printed_at}</b></td>
                    <td><b style="color:#0f172a;">${l.generated_for || 'N/A'}</b></td>
                    <td>${l.generated_by || 'System'}</td>
                    <td>${l.purpose || '-'}</td>
                    <td><span class="badge-segment">${l.records_count} leads</span></td>
                    <td>${l.segment_name || 'General Pool'}</td>
                </tr>`;
            });
        } else {
            html = `<tr><td colspan="6" style="text-align:center; padding:20px; color:#64748b;">No print logs recorded yet.</td></tr>`;
        }
        document.getElementById('printAuditBody').innerHTML = html;
        document.getElementById('printAuditModal').style.display = 'flex';
    });
}

function populateSegmentDropdown(segments) {
    const sel = document.getElementById('segmentFilter');
    const curr = sel.value;
    let html = '<option value="">All Data Segments</option>';
    segments.forEach(s => {
        const name = s.seg || 'General Pool';
        const isSel = name === curr ? 'selected' : '';
        html += `<option value="${name}" ${isSel}>${name} (${s.cnt})</option>`;
    });
    sel.innerHTML = html;
}

function resetFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('segmentFilter').value = '';
    document.getElementById('statusFilter').value = '';
    if (document.getElementById('rmFilter')) document.getElementById('rmFilter').value = '';
    if (currentViewMode === 'kanban') loadKanbanBoard(); else loadData();
}

function changePage(d) { currentPage += d; loadData(); }

function toggleSelectAllPreleads(master) {
    document.querySelectorAll('.prelead-checkbox').forEach(cb => cb.checked = master.checked);
    updateCheckedCount();
}

function savePreleadSingle(e) {
    e.preventDefault();
    const fd = new FormData(e.target);
    fetch('api.php?api=save_prelead', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            Swal.fire({ icon: 'success', title: 'Prospect Saved', text: d.message, confirmButtonColor: '#0f172a' });
            document.getElementById('addModal').style.display = 'none';
            e.target.reset();
            loadData();
        } else {
            Swal.fire({ icon: 'error', title: 'Save Failed', text: d.error, confirmButtonColor: '#0f172a' });
        }
    });
}

function submitBulkCsv(e) {
    e.preventDefault();
    const btn = document.getElementById('importBtnSubmit');
    btn.disabled = true; btn.innerText = 'Uploading...';
    const fd = new FormData(e.target);
    fetch('ajax_bulk_import.php?type=pre_leads', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        btn.disabled = false; btn.innerText = 'Process Import';
        if (d.success) {
            Swal.fire({ icon: 'success', title: 'Data Uploaded', text: d.message, confirmButtonColor: '#0f172a' });
            document.getElementById('importModal').style.display = 'none';
            e.target.reset();
            loadData();
        } else {
            Swal.fire({ icon: 'error', title: 'Import Failed', text: d.error, confirmButtonColor: '#0f172a' });
        }
    }).catch(err => {
        btn.disabled = false; btn.innerText = 'Process Import';
        Swal.fire({ icon: 'error', title: 'Server Error', text: 'Error uploading file.', confirmButtonColor: '#0f172a' });
    });
}

function openSegmentAllocationModal() {
    const sel = document.getElementById('desk_segment_select');
    let html = '<option value="">-- Choose Data Segment Pool --</option><option value="ALL">ALL Segments Combined</option>';
    segmentsDataList.forEach(s => {
        const name = s.seg || 'General Pool';
        html += `<option value="${name}">${name} (${s.cnt} leads)</option>`;
    });
    sel.innerHTML = html;
    document.getElementById('segmentDeskModal').style.display = 'flex';
}

function updateSegmentAllocationCount() {
    const seg = document.getElementById('desk_segment_select').value;
    const notice = document.getElementById('segmentCountNotice');
    if (!seg) { notice.innerHTML = ''; return; }
    if (seg === 'ALL') { notice.innerText = `Selected ALL segments combined.`; return; }
    const found = segmentsDataList.find(s => s.seg === seg);
    if (found) { notice.innerText = `Found ${found.cnt} total leads in segment "${seg}".`; }
}

function toggleAllocMode(mode) {
    if (mode === 'single') {
        document.getElementById('singleRmContainer').style.display = 'block';
        document.getElementById('roundRobinContainer').style.display = 'none';
    } else {
        document.getElementById('singleRmContainer').style.display = 'none';
        document.getElementById('roundRobinContainer').style.display = 'block';
    }
}

function executeSegmentAllocation(e) {
    e.preventDefault();
    const seg = document.getElementById('desk_segment_select').value;
    if (!seg) { Swal.fire({ icon: 'warning', title: 'Segment Required', text: 'Please select a data segment.', confirmButtonColor: '#0f172a' }); return; }

    const mode = document.querySelector('input[name="alloc_mode"]:checked').value;
    const singleRm = document.getElementById('desk_single_rm').value;
    const checkedRms = Array.from(document.querySelectorAll('.rr-rm-check:checked')).map(c => c.value);
    const onlyUnassigned = document.getElementById('desk_only_unassigned').checked ? 1 : 0;

    if (mode === 'single' && !singleRm) { Swal.fire({ icon: 'warning', title: 'Target RM Required', text: 'Please select a target RM.', confirmButtonColor: '#0f172a' }); return; }
    if (mode === 'round_robin' && checkedRms.length === 0) { Swal.fire({ icon: 'warning', title: 'RMs Required', text: 'Select at least one RM.', confirmButtonColor: '#0f172a' }); return; }

    const fd = new FormData();
    fd.append('segment_name', seg);
    fd.append('mode', mode);
    fd.append('assigned_to', singleRm);
    fd.append('target_rms', JSON.stringify(checkedRms));
    fd.append('only_unassigned', onlyUnassigned);

    fetch('api.php?api=bulk_assign_segment', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            Swal.fire({ icon: 'success', title: 'Distribution Complete', text: d.message, confirmButtonColor: '#0f172a' });
            document.getElementById('segmentDeskModal').style.display = 'none';
            loadData();
        } else {
            Swal.fire({ icon: 'error', title: 'Distribution Failed', text: d.error, confirmButtonColor: '#0f172a' });
        }
    });
}

function assignSinglePrelead(id, rm) {
    const fd = new FormData();
    fd.append('type', 'pre_leads');
    fd.append('assigned_to', rm);
    fd.append('ids', JSON.stringify([id]));

    fetch('api.php?api=bulk_assign', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            typeof showNotification === 'function' ? showNotification('RM updated!', 'success') : true;
            loadData();
        }
    });
}

function promotePreleadToApplicant(id, name) {
    promotePreleadModal(id, name);
}

function promotePreleadModal(id, name) {
    Swal.fire({
        title: 'Promote Prospect Pipeline',
        html: `Where would you like to promote <b>${name}</b>?<br><br>` +
              `<div style="font-size:12px; color:#475569; text-align:left; background:#f8fafc; padding:10px 12px; border-radius:8px; border:1px solid #cbd5e1; line-height:1.4;">` +
              `• <b>🎯 Convert to Lead</b>: Moves prospect to <code>leads.php</code> (Lead Management) for discussion & qualification.<br>` +
              `• <b>📄 Direct Loan File</b>: Creates formal Loan Application file in LOS (<code>applicants</code>).` +
              `</div>`,
        icon: 'question',
        showCancelButton: true,
        showDenyButton: true,
        confirmButtonColor: '#0f172a',
        denyButtonColor: '#2563eb',
        cancelButtonColor: '#64748b',
        confirmButtonText: '🎯 Convert to Lead (leads.php)',
        denyButtonText: '📄 Create Direct Loan Application',
        cancelButtonText: 'Cancel'
    }).then(res => {
        if (res.isConfirmed) {
            const fd = new FormData();
            fd.append('id', id);
            fetch('api.php?api=promote_prelead', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Promoted to Lead Management!',
                        text: `"${name}" is now an active lead in Lead Management (leads.php).`,
                        confirmButtonColor: '#0f172a'
                    });
                    if (currentViewMode === 'kanban') loadKanbanBoard(); else loadData();
                } else {
                    Swal.fire({ icon: 'error', title: 'Promotion Failed', text: d.error, confirmButtonColor: '#0f172a' });
                }
            });
        } else if (res.isDenied) {
            const fd = new FormData();
            fd.append('id', id);
            fetch('api.php?api=promote_prelead_to_applicant', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Promoted to Loan File',
                        text: `Generated Loan File #${d.loan_id}. Track in Search & Track / Applicants list.`,
                        confirmButtonColor: '#0f172a'
                    });
                    if (currentViewMode === 'kanban') loadKanbanBoard(); else loadData();
                } else {
                    Swal.fire({ icon: 'error', title: 'Promotion Failed', text: d.error, confirmButtonColor: '#0f172a' });
                }
            });
        }
    });
}

function openCallConsole(id, name, loanType) {
    document.getElementById('call_lead_id').value = id;
    document.getElementById('scriptContent').innerText = `"Hi ${name}, calling from BFS Circle regarding your inquiry for ${loanType}. We have pre-approved options available for you."`;
    document.getElementById('callModal').style.display = 'flex';
}

function generateAiPitchForLead() {
    const leadId = document.getElementById('call_lead_id').value;
    const row = currentLoadedRowsData.find(r => parseInt(r.id) === parseInt(leadId));
    
    document.getElementById('scriptContent').innerHTML = `<span style="color:#64748b; font-weight:600;"><i data-lucide="loader" style="width:14px; vertical-align:middle;"></i> Generating personalized AI pitch with Ollama (llama3.2:3b)...</span>`;
    if (window.lucide) lucide.createIcons();

    const fd = new FormData();
    fd.append('name', row ? (row.name || '') : '');
    fd.append('loan_type', row ? (row.loan_type || '') : '');
    fd.append('monthly_income', row ? (row.monthly_income || '') : '');
    fd.append('company_name', row ? (row.company_name || '') : '');
    fd.append('segment_name', row ? (row.segment_name || '') : '');

    fetch('api.php?api=ai_generate_pitch', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            document.getElementById('scriptContent').innerHTML = `<div style="white-space:pre-line; color:#0f172a; font-size:12.5px; line-height:1.4;">${d.ai_output}</div>`;
        } else {
            document.getElementById('scriptContent').innerHTML = `<span style="color:#dc2626;">AI Error: ${d.error || 'Could not reach Ollama'}</span>`;
        }
    })
    .catch(err => {
        document.getElementById('scriptContent').innerHTML = `<span style="color:#64748b;">Ollama model downloading/starting in background... Please retry in a few moments.</span>`;
    });
}

function submitCallLog() {
    const id = document.getElementById('call_lead_id').value;
    const stat = document.getElementById('call_status').value;
    const fdate = document.getElementById('call_followup').value;
    const notes = document.getElementById('call_notes').value;

    const fd = new FormData();
    fd.append('id', id);
    fd.append('status', stat);
    fd.append('followup_date', fdate);
    fd.append('notes', notes);

    fetch('api.php?api=log_call', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            document.getElementById('callModal').style.display = 'none';
            loadData();
        }
    });
}

function openEditPreleadModal(id) {
    let row = currentLoadedRowsData.find(r => parseInt(r.id) === parseInt(id));
    if (!row) {
        fetch(`api.php?api=get_preleads&page=1&limit=1000`)
        .then(r => r.json())
        .then(res => {
            if (res.data) {
                let found = res.data.find(r => parseInt(r.id) === parseInt(id));
                if (found) populateAndShowEditModal(found);
            }
        });
        return;
    }
    populateAndShowEditModal(row);
}

function populateAndShowEditModal(row) {
    document.getElementById('edit_id').value = row.id || '';
    document.getElementById('edit_name').value = row.name || '';
    document.getElementById('edit_mobile').value = row.mobile || '';
    document.getElementById('edit_email').value = row.email || '';
    document.getElementById('edit_company_name').value = row.company_name || '';
    document.getElementById('edit_segment_name').value = row.segment_name || 'General Pool';
    document.getElementById('edit_loan_type').value = row.loan_type || 'Personal Loan';
    document.getElementById('edit_monthly_income').value = row.monthly_income || '';
    document.getElementById('edit_status').value = row.status || 'Not Contacted';
    if (document.getElementById('edit_assigned_to')) {
        document.getElementById('edit_assigned_to').value = row.assigned_to || '';
    }
    document.getElementById('edit_notes').value = row.notes || '';
    document.getElementById('editModal').style.display = 'flex';
    lucide.createIcons();
}

function updatePreleadSingle(e) {
    e.preventDefault();
    const fd = new FormData(e.target);
    fetch('api.php?api=save_prelead', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            Swal.fire({ icon: 'success', title: 'Prospect Updated', text: d.message, confirmButtonColor: '#0f172a' });
            document.getElementById('editModal').style.display = 'none';
            if (currentViewMode === 'kanban') loadKanbanBoard(); else loadData();
        } else {
            Swal.fire({ icon: 'error', title: 'Update Failed', text: d.error, confirmButtonColor: '#0f172a' });
        }
    });
}

function deletePreleadSingle(id, name) {
    Swal.fire({
        title: 'Delete Prospect?',
        text: `Are you sure you want to permanently remove "${name}" from Pre-Leads?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, Delete Prospect'
    }).then(res => {
        if (res.isConfirmed) {
            const fd = new FormData();
            fd.append('id', id);
            fetch('api.php?api=delete_prelead', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    Swal.fire({ icon: 'success', title: 'Deleted', text: d.message, confirmButtonColor: '#0f172a' });
                    if (currentViewMode === 'kanban') loadKanbanBoard(); else loadData();
                } else {
                    Swal.fire({ icon: 'error', title: 'Delete Failed', text: d.error, confirmButtonColor: '#0f172a' });
                }
            });
        }
    });
}

document.addEventListener("DOMContentLoaded", () => {
    lucide.createIcons();
    loadData();
});
</script>

<?php require_once 'footer.php'; ?>
