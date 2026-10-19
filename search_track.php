<?php
require_once 'config.php';
if (($_SESSION['role'] ?? '') === 'Staff') {
    $queryString = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
    header("Location: staff/search_track.php" . $queryString);
    exit;
}
require_role(['Admin', 'RM']);

$staff_members = $db->query("SELECT username, COALESCE(name, full_name, username) as full_name, role FROM users WHERE is_active = 1 ORDER BY role ASC, username ASC")->fetchAll(PDO::FETCH_ASSOC);
$transfer_targets = $db->query("SELECT username, COALESCE(name, full_name, username) as full_name, role FROM users WHERE is_active = 1 AND LOWER(role) IN ('admin','staff','rm','manager','credit officer') AND LOWER(role) NOT IN ('agent','builder','ca','partner','connector','dsa') ORDER BY role ASC, username ASC")->fetchAll(PDO::FETCH_ASSOC);
$page_title = 'Search & CRM Track (LOS)';
$page_subtitle = 'Track applicant progress, manage pipeline phases and direct processing';
$hide_main_header = true;
require_once 'header.php';
// Get distinct bank names for email modal
$stmt_banks = $db->query("SELECT DISTINCT bank_name FROM bankers WHERE bank_name IS NOT NULL AND bank_name != '' ORDER BY bank_name");
$banks = $stmt_banks->fetchAll(PDO::FETCH_COLUMN);
?>



<style>
.client-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 16px;
    cursor: pointer;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
    margin-bottom: 4px;
}
.client-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
    border-color: #cbd5e1;
}
.client-card.active {
    border: 2px solid #0f172a;
    background: #f8fafc;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
    transform: translateY(-2px);
}
.client-card-title {
    font-size: 15px;
    font-weight: 700;
    color: #0f172a;
    font-family: 'Outfit', sans-serif;
    letter-spacing: -0.3px;
}
.client-card-meta {
    font-size: 12.5px;
    color: #64748b;
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.badge-info { background: #f1f5f9; color: #334155; border: 1px solid #e2e8f0; }
.badge-warning { background: #f1f5f9; color: #334155; border: 1px solid #e2e8f0; }
.badge-primary { background: #f1f5f9; color: #334155; border: 1px solid #e2e8f0; }
.badge-success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
.badge { font-weight: 600; font-size: 11px; padding: 4px 10px; border-radius: 20px; letter-spacing: 0.2px; text-transform: uppercase; }

/* Scrollbar beautification */
::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}
::-webkit-scrollbar-track {
    background: transparent;
}
::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 10px;
}
::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

@keyframes shimmer {
    0% { background-position: -468px 0; }
    100% { background-position: 468px 0; }
}
.skeleton {
    background: #f6f7f8;
    background-image: linear-gradient(to right, #f6f7f8 0%, #edeef1 20%, #f6f7f8 40%, #f6f7f8 100%);
    background-repeat: no-repeat;
    background-size: 800px 100%;
    animation-duration: 1.5s;
    animation-fill-mode: forwards;
    animation-iteration-count: infinite;
    animation-name: shimmer;
    animation-timing-function: linear;
    border-radius: 4px;
}

/* Smart smooth entrance animation for cards */
@keyframes slideUpFade {
    from {
        opacity: 0;
        transform: translateY(15px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.card-animate-enter {
    animation: slideUpFade 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}


.filter-item { display: flex; flex-direction: column; gap: 5px; }
.filter-item label { font-size: 11px; font-weight: 800; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; }
.filter-select, .filter-input { padding: 10px 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 13px; font-weight: 600; color: #0f172a; background: #f8fafc; outline: none; transition: 0.2s; width: 100%; box-sizing: border-box; }
.filter-select:focus, .filter-input:focus { border-color: #f97316; background: #fff; }

.filter-range-row { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }

.filter-actions { display: flex; align-items: center; justify-content: space-between; margin-top: 16px; padding-top: 16px; border-top: 1px solid #f1f5f9; }
.filter-active-chips { display: flex; flex-wrap: wrap; gap: 6px; }
.filter-chip { display: flex; align-items: center; gap: 5px; background: rgba(249,115,22,0.1); border: 1px solid rgba(249,115,22,0.3); color: #c2410c; border-radius: 20px; padding: 4px 10px; font-size: 12px; font-weight: 700; cursor: pointer; }
.filter-chip:hover { background: rgba(249,115,22,0.2); }

.btn-apply-filter { background: #0f172a; color: #fff; border: none; padding: 10px 22px; border-radius: 8px; font-weight: 800; font-size: 13px; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: 0.2s; }
.btn-apply-filter:hover { background: #1e293b; }
.btn-reset-filter { background: transparent; color: #64748b; border: 2px solid #e2e8f0; padding: 9px 16px; border-radius: 8px; font-weight: 700; font-size: 13px; cursor: pointer; transition: 0.2s; }
.btn-reset-filter:hover { border-color: #f97316; color: #f97316; }

/* Quick filter chips row */
.quick-filters { display: flex; gap: 8px; overflow-x: auto; -webkit-overflow-scrolling: touch; padding-bottom: 6px; margin-bottom: 12px; }
.quick-filters::-webkit-scrollbar { height: 4px; }
.quick-filters::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
.qf-chip { padding: 6px 14px; border-radius: 20px; border: 2px solid #e2e8f0; background: #fff; font-size: 12px; font-weight: 700; color: #475569; cursor: pointer; transition: 0.2s; white-space: nowrap; flex-shrink: 0; }
.qf-chip:hover, .qf-chip.active { border-color: #0f172a; background: #0f172a; color: #fff; }
.qf-chip.orange.active { border-color: #f97316; background: #f97316; color: #fff; }

.applicant-workspace-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
}
@media (max-width: 1250px) {
    .applicant-workspace-grid {
        grid-template-columns: 1fr !important;
        gap: 16px;
    }
}
@media (max-width: 1024px) {
    .crm-layout {
        grid-template-columns: 1fr !important;
    }
    .client-list-pane-container {
        height: auto !important;
        max-height: 420px !important;
    }
    .client-detail-pane {
        position: static !important;
        max-height: none !important;
        overflow-y: visible !important;
    }
}

/* Result count badge */
.result-meta { font-size: 12px; color: #64748b; font-weight: 600; padding: 8px 0 4px; display: flex; justify-content: space-between; align-items: center; }
.result-count-badge { background: #0f172a; color: #fff; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 800; }

</style>

<div id="view-search-crm" class="view-container">
    <!-- Search bar -->
    <div class="crm-search-bar" style="margin-bottom: 12px; display:flex; gap:12px;">
        <div class="search-input-wrapper" style="flex:1;">
            <i data-lucide="search" class="search-icon"></i>
            <input type="text" id="search-query" placeholder="Search by Applicant Name / Loan ID / Mobile / PAN..." oninput="triggerSearch()">
        </div>
        <button class="filters-toggle-btn" onclick="toggleAdvFilters()" id="advFilterToggleBtn" style="background:#fff; border:1px solid #cbd5e1; color:#0f172a; font-weight:600; padding:0 16px; border-radius:8px; display:flex; align-items:center; gap:8px; cursor:pointer; transition:0.2s;">
            <i data-lucide="sliders-horizontal" style="width:16px;"></i> <span id="advFilterToggleText">Filters</span>
            <span id="filterCountBadge" style="display:none; background:#f97316; color:#fff; border-radius:12px; padding:2px 6px; font-size:10px; margin-left:4px;">0</span>
        </button>
    </div>

    <!-- ===== QUICK FILTER CHIPS ===== -->
    <div class="quick-filters" id="quickFilters">
        <button class="qf-chip active" onclick="setQuickFilter('all', this)">All Files</button>
        <button class="qf-chip" onclick="setQuickFilter('Phase 1', this)">Phase 1 - KYC</button>
        <button class="qf-chip" onclick="setQuickFilter('Phase 2', this)">Phase 2 - Docs</button>
        <button class="qf-chip" onclick="setQuickFilter('Phase 3', this)">Phase 3 - Bank</button>
        <button class="qf-chip" onclick="setQuickFilter('Phase 4', this)">Phase 4 - Disburse</button>
        <button class="qf-chip" onclick="setQuickFilter('Completed', this)" style="border-color:#bbf7d0; color:#15803d; background:#f0fdf4;">Completed</button>
        <button class="qf-chip" onclick="setQuickFilter('Rejected', this)" style="border-color:#fecaca; color:#b91c1c; background:#fef2f2;">Rejected</button>
    </div>

    <style>
        
        .adv-filter-panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px 20px; margin-bottom: 20px; display: none; box-shadow: 0 4px 12px rgba(0,0,0,0.03); }
        .adv-filter-panel.open { display: block; }
        
        .compact-filter-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px 16px; }
        
        .f-group { display: flex; flex-direction: column; gap: 4px; }
        .f-group label { font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        .f-input { padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12px; font-weight: 500; color: #0f172a; outline: none; background: #f8fafc; transition: 0.2s; width: 100%; box-sizing: border-box; }
        .f-input:focus { border-color: #f97316; background: #fff; box-shadow: 0 0 0 3px rgba(249,115,22,0.1); }
        
        .filter-actions-row { display: flex; justify-content: space-between; align-items: center; margin-top: 16px; padding-top: 16px; border-top: 1px solid #f1f5f9; }
        .btn-reset { background: transparent; border: 1px solid #cbd5e1; color: #475569; padding: 8px 16px; border-radius: 6px; font-weight: 600; font-size: 12px; cursor: pointer; transition: 0.2s; }
        .btn-reset:hover { border-color: #0f172a; color: #0f172a; }
        .btn-apply { background: #0f172a; border: none; color: #fff; padding: 8px 24px; border-radius: 6px; font-weight: 600; font-size: 12px; cursor: pointer; transition: 0.2s; box-shadow: 0 2px 8px rgba(15,23,42,0.2); }
        .btn-apply:hover { background: #1e293b; box-shadow: 0 4px 12px rgba(15,23,42,0.3); }
    </style>

    <!-- ===== ADVANCED FILTER PANEL ===== -->
    <div class="adv-filter-panel" id="advFilterBody">
        <div class="compact-filter-grid">
            <div class="f-group">
                <label>Phase Status</label>
                <select id="filter-status" class="f-input" onchange="triggerSearch()">
                    <option value="">All Phases</option>
                    <option value="Phase 1">Phase 1 - KYC</option>
                    <option value="Phase 2">Phase 2 - Documents</option>
                    <option value="Phase 3">Phase 3 - Bank Processing</option>
                    <option value="Phase 4">Phase 4 - Disbursements</option>
                    <option value="Completed">Completed / Sanctioned</option>
                    <option value="Rejected">Rejected</option>
                </select>
            </div>
            <div class="f-group">
                <label>Loan Type</label>
                <select id="filter-type" class="f-input" onchange="triggerSearch()">
                    <option value="">All Loan Types</option>
                    <option value="Home Loan">Home Loan</option>
                    <option value="Personal Loan">Personal Loan</option>
                    <option value="Vehicle Loan">Vehicle Loan</option>
                    <option value="Business Loan">Business Loan</option>
                    <option value="Gold Loan">Gold Loan</option>
                    <option value="Education Loan">Education Loan</option>
                    <option value="LAP">LAP</option>
                </select>
            </div>
            <div class="f-group">
                <label>Assigned Staff</label>
                <select id="filter-staff" class="f-input" onchange="triggerSearch()">
                    <option value="">All Staff</option>
                    <?php foreach($staff_members as $sm): ?>
                    <option value="<?= htmlspecialchars($sm['username']) ?>"><?= htmlspecialchars($sm['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="f-group">
                <label>Bank Assigned</label>
                <select id="filter-bank" class="f-input" onchange="triggerSearch()">
                    <option value="">All Banks</option>
                    <?php foreach($banks as $bk): ?>
                    <option value="<?= htmlspecialchars($bk) ?>"><?= htmlspecialchars($bk) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="f-group">
                <label>TAT Aging</label>
                <select id="filter-aging" class="f-input" onchange="triggerSearch()">
                    <option value="">Any Duration</option>
                    <option value="0-7">0-7 Days</option>
                    <option value="7-30">7-30 Days</option>
                    <option value="30-90">30-90 Days</option>
                    <option value="90+">90+ Days</option>
                </select>
            </div>
            <div class="f-group">
                <label>Sort By</label>
                <select id="filter-sort" class="f-input" onchange="triggerSearch()">
                    <option value="newest">Newest First</option>
                    <option value="oldest">Oldest First</option>
                    <option value="amount_high">Highest Loan</option>
                    <option value="amount_low">Lowest Loan</option>
                    <option value="name_az">Name A-Z</option>
                </select>
            </div>
            <div class="f-group">
                <label>Date From</label>
                <input type="date" id="filter-date-from" class="f-input" onchange="triggerSearch()">
            </div>
            <div class="f-group">
                <label>Date To</label>
                <input type="date" id="filter-date-to" class="f-input" onchange="triggerSearch()">
            </div>
            <div class="f-group">
                <label>Min Amt (₹)</label>
                <input type="number" id="filter-amt-min" class="f-input" placeholder="0" onchange="triggerSearch()">
            </div>
            <div class="f-group">
                <label>Max Amt (₹)</label>
                <input type="number" id="filter-amt-max" class="f-input" placeholder="Any" onchange="triggerSearch()">
            </div>
        </div>

        <div class="filter-actions-row">
            <div id="activeFilterChips" style="display:flex; gap:6px; flex-wrap:wrap;"></div>
            <div style="display:flex; gap:10px; margin-left:auto;">
                <button onclick="resetAllFilters()" class="btn-reset">Reset</button>
                <button onclick="triggerSearch(); toggleAdvFilters();" class="btn-apply">Apply Filters</button>
            </div>
        </div>
    </div>

    <!-- CRM Layout Grid -->
    <div class="crm-layout">
        <div class="client-list-pane-container" style="display:flex; flex-direction:column; background: #f8fafc; border-right: 1px solid var(--border); overflow: hidden; height: calc(100vh - 180px);">
            <div class="client-list-pane" id="crm-client-list" style="flex:1; border-right:none; background:transparent; padding-bottom: 20px;">
                <!-- Loaded dynamically -->
            </div>
        </div>

        <!-- Right panel: Live Tracker Details -->
        <div class="client-detail-pane" id="crm-detail-pane" style="position: sticky; top: 24px; max-height: calc(100vh - 48px); overflow-y: auto;">
            <div class="detail-placeholder" style="display:flex; flex-direction:column; justify-content:center; align-items:center; height:100%; color:var(--text-light); text-align:center;">
                <i data-lucide="file-search" style="width: 64px; height: 64px; stroke-width: 1.5; margin-bottom:16px;"></i>
                <div>
                    <h3 style="color:var(--text-primary);">No Applicant Selected</h3>
                    <p style="font-size: 13.5px; margin-top: 6px; max-width:300px;">Select an applicant from the list to view their pipeline progress, documents, and banking assignments.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Advanced Filter JS
    function toggleFilterDrawer() {
        toggleAdvFilters();
    }
    
    function toggleAdvFilters() {
        const body = document.getElementById('advFilterBody');
        const btn = document.getElementById('advFilterToggleBtn');
        const txt = document.getElementById('advFilterToggleText');
        body.classList.toggle('open');
        
        if (body.classList.contains('open')) {
            btn.style.background = '#0f172a';
            btn.style.color = '#fff';
            btn.style.borderColor = '#0f172a';
            txt.innerText = 'Close';
        } else {
            btn.style.background = '#fff';
            btn.style.color = '#0f172a';
            btn.style.borderColor = '#cbd5e1';
            txt.innerText = 'Filters';
        }
    }

    function setQuickFilter(status, btn) {
        document.querySelectorAll('.qf-chip').forEach(c => c.classList.remove('active'));
        btn.classList.add('active');
        
        if (status === 'all') {
            document.getElementById('filter-status').value = '';
        } else {
            document.getElementById('filter-status').value = status;
        }
        triggerSearch();
    }

    function resetAllFilters() {
        document.getElementById('search-query').value = '';
        document.getElementById('filter-status').value = '';
        document.getElementById('filter-type').value = '';
        document.getElementById('filter-staff').value = '';
        document.getElementById('filter-bank').value = '';
        document.getElementById('filter-aging').value = '';
        document.getElementById('filter-sort').value = 'newest';
        document.getElementById('filter-date-from').value = '';
        document.getElementById('filter-date-to').value = '';
        document.getElementById('filter-amt-min').value = '';
        document.getElementById('filter-amt-max').value = '';
        
        document.querySelectorAll('.qf-chip').forEach(c => c.classList.remove('active'));
        document.querySelector('.qf-chip').classList.add('active'); // Set 'All' active
        
        triggerSearch();
    }

    function updateFilterChips() {
        const chips = [];
        const status = document.getElementById('filter-status').value;
        const type = document.getElementById('filter-type').value;
        const staff = document.getElementById('filter-staff').value;
        const bank = document.getElementById('filter-bank').value;
        const aging = document.getElementById('filter-aging').value;
        const dFrom = document.getElementById('filter-date-from').value;
        const dTo = document.getElementById('filter-date-to').value;
        const aMin = document.getElementById('filter-amt-min').value;
        const aMax = document.getElementById('filter-amt-max').value;
        
        if(status) chips.push(`Status: ${status}`);
        if(type) chips.push(`Type: ${type}`);
        if(staff) chips.push(`Staff: ${staff}`);
        if(bank) chips.push(`Bank: ${bank}`);
        if(aging) chips.push(`Aging: ${aging} days`);
        if(dFrom || dTo) chips.push(`Date: ${dFrom} to ${dTo}`);
        if(aMin || aMax) chips.push(`Amt: ${aMin} to ${aMax}`);
        
        const container = document.getElementById('activeFilterChips');
        const badge = document.getElementById('filterCountBadge');
        
        container.innerHTML = chips.map(c => `<span style="background:#f8fafc; border:1px solid #cbd5e1; color:#475569; padding:2px 8px; border-radius:4px; font-size:10px; font-weight:600;">${c}</span>`).join('');
        
        if(chips.length > 0) {
            badge.style.display = 'inline-block';
            badge.innerText = `${chips.length} active`;
        } else {
            badge.style.display = 'none';
        }
    }

    function formatAmt(num) {
        return new Intl.NumberFormat('en-IN').format(num);
    }


    async function addNote(id) {
        const input = document.getElementById('noteInput');
        const note = input.value.trim();
        if(!note) return;
        
        const fd = new FormData();
        fd.append('id', id);
        fd.append('note', note);
        
        try {
            const res = await fetch('api.php?api=add_applicant_note', { method: 'POST', body: fd });
            const data = await res.json();
            if(data.success) {
                input.value = '';
                showNotification('Note added', 'success');
                selectApplicantCard(id); // reload the pane
            } else {
                showNotification(data.error || 'Failed', 'error');
            }
        } catch(e) {
            showNotification('Error adding note', 'error');
        }
    }

    let currentOffset = 0;

    async function triggerSearch(reset = true) {
        if (reset) {
            currentOffset = 0;
        }
        
        updateFilterChips();
        
        const params = new URLSearchParams({
            api: 'search_applicants',
            query: document.getElementById('search-query').value,
            status: document.getElementById('filter-status').value,
            loan_type: document.getElementById('filter-type').value,
            staff: document.getElementById('filter-staff').value,
            bank: document.getElementById('filter-bank').value,
            aging: document.getElementById('filter-aging').value,
            sort: document.getElementById('filter-sort').value,
            date_from: document.getElementById('filter-date-from').value,
            date_to: document.getElementById('filter-date-to').value,
            amt_min: document.getElementById('filter-amt-min').value,
            amt_max: document.getElementById('filter-amt-max').value,
            offset: currentOffset
        });
        
        try {
            const container = document.getElementById('crm-client-list');
            if (reset) {
                // Show Skeleton Loader for Left Pane
                container.innerHTML = Array(5).fill(`
                    <div class="client-card" style="pointer-events: none; border: 1px solid #e2e8f0;">
                        <div class="client-card-header">
                            <div class="skeleton" style="width: 140px; height: 20px;"></div>
                            <div class="skeleton" style="width: 60px; height: 24px; border-radius: 12px;"></div>
                        </div>
                        <div class="client-card-meta"><div class="skeleton" style="width: 120px; height: 14px;"></div></div>
                        <div class="client-card-meta"><div class="skeleton" style="width: 180px; height: 14px;"></div></div>
                        <div class="client-card-meta" style="margin-bottom:0;"><div class="skeleton" style="width: 100px; height: 14px;"></div></div>
                        <div style="margin-top: 12px; border-top: 1px dashed var(--border); padding-top: 10px; display: flex; justify-content: space-between;">
                            <div class="skeleton" style="width: 70px; height: 12px;"></div>
                            <div class="skeleton" style="width: 90px; height: 12px;"></div>
                        </div>
                    </div>
                `).join('');
            } else {
                // Change Load More button text to loading
                const realBtn = document.getElementById('real-load-more-btn');
                if (realBtn) realBtn.innerHTML = '<i data-lucide="loader-2" style="width:16px; height:16px; vertical-align:-3px; margin-right:6px;"></i> Loading...';
            }
            
            const response = await fetch('api.php?' + params.toString());
            const applicants = await response.json();
            
            if (reset) {
                container.innerHTML = '';
            }
            
            if (applicants && applicants.error === 'SESSION_EXPIRED') {
                window.location.href = 'login.php';
                return;
            }

            if (!Array.isArray(applicants)) {
                console.error('API Returned Error or Invalid Format:', applicants);
                container.innerHTML = `<div style="background: white; border: 1px solid #fee2e2; text-align: center; color: #dc2626; padding: 40px; border-radius: var(--radius-md);">${applicants.error || 'Failed to load applicants.'}</div>`;
                return;
            }

            if (reset && applicants.length === 0) {
                container.innerHTML = '<div style="background: white; border: 1px solid #e2e8f0; text-align: center; color: var(--text-light); padding: 40px; border-radius: var(--radius-md);">No applicants match criteria.</div>';
                return;
            }
            
            const existingBtn = document.getElementById('load-more-btn-container');
            if (existingBtn) existingBtn.remove();
            
            applicants.forEach((app, index) => {
                const card = document.createElement('div');
                // Add the smooth animation class with a staggered delay based on index
                card.className = 'client-card card-animate-enter';
                card.style.animationDelay = `${index * 0.05}s`;
                card.id = 'crm-client-card-' + app.id;
                card.dataset.id = app.id;
                card.onclick = () => selectApplicantCard(app.id, true);
                
                let badgeClass = 'badge-info';
                if(app.overall_status === 'Phase 2') badgeClass = 'badge-warning';
                if(app.overall_status === 'Phase 3') badgeClass = 'badge-primary';
                if(app.overall_status === 'Phase 4') badgeClass = 'badge-warning';
                if(app.overall_status === 'Completed') {
                    badgeClass = 'badge-success';
                    card.classList.add('won');
                }
                if(app.overall_status === 'Rejected') {
                    badgeClass = 'badge-danger';
                }
                
                const isDeactiveCard = (app.is_active == 0 || app.is_active === '0');
                const deactiveBadge = isDeactiveCard ? `<span class="badge" style="background:#ef4444; color:#fff; font-size:9.5px; padding:2px 6px; margin-left:4px;"><i data-lucide="power" style="width:10px; vertical-align:-1px;"></i> DEACTIVE</span>` : '';
                
                card.innerHTML = `
                    <div class="client-card-header">
                        <span class="client-card-title">
                            ${app.customer_name}
                            ${app.calculated_completion < 100 
                                ? `<span style="display:inline-block; font-size: 10px; color: #ef4444; font-weight: 600; margin-left: 4px;" title="${app.calculated_completion}% Profile Complete"><i data-lucide="alert-circle" style="width:10px;height:10px;"></i> ${app.calculated_completion}%</span>`
                                : `<span style="display:inline-block; font-size: 10px; color: #10b981; font-weight: 600; margin-left: 4px;" title="100% Complete Profile"><i data-lucide="check-circle" style="width:10px;height:10px;"></i> 100%</span>`
                            }
                            ${deactiveBadge}
                        </span>
                        <span class="badge ${badgeClass}">${app.overall_status}</span>
                    </div>
                    <div class="client-card-meta">
                        <i data-lucide="tag" style="width: 12px; height: 12px;"></i>
                        ${app.loan_id}
                    </div>
                    <div class="client-card-meta">
                        <i data-lucide="briefcase" style="width: 12px; height: 12px;"></i>
                        ${app.loan_type} - <span style="font-weight: 600;">₹${new Intl.NumberFormat('en-IN').format(app.amount)}</span>
                    </div>
                    <div class="client-card-meta" style="margin-bottom: 0;">
                        <i data-lucide="phone" style="width: 12px; height: 12px;"></i>
                        ${app.mobile}
                    </div>
                    <div style="margin-top: 12px; border-top: 1px dashed var(--border); padding-top: 10px; font-size: 11px; color: var(--text-light); display: flex; justify-content: space-between;">
                        <span><i data-lucide="clock" style="width:10px;height:10px;vertical-align:-1px;"></i> Unknown</span>
                        <span>Added by: System</span>
                    </div>
                `;
                container.appendChild(card);
            });
            
            if (applicants.length === 10) {
                const btnContainer = document.createElement('div');
                btnContainer.id = 'load-more-btn-container';
                btnContainer.style.padding = '16px';
                btnContainer.innerHTML = `<button id="real-load-more-btn" class="btn btn-primary" style="width: 100%; padding: 12px; font-weight: bold; background: #0f172a; color: white; border: none; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.2);" onclick="currentOffset += 10; triggerSearch(false);">
                    ↓ Load More Records ↓
                </button>`;
                container.appendChild(btnContainer);
            }
            
            if(window.lucide) lucide.createIcons();
            
            // Automatically select the first applicant if this is a fresh search and results exist
            if (reset && applicants.length > 0) {
                // Ensure a small delay so the DOM has time to render the cards before adding 'active' class
                setTimeout(() => {
                    selectApplicantCard(applicants[0].id, false);
                }, 50);
            }
            
        } catch (error) {
            console.error('Error searching:', error);
            showNotification('Applicant search failed.', 'error');
        }
    }

    async function selectApplicantCard(id, userInitiated = true) {
        document.querySelectorAll('.client-card').forEach(el => el.classList.remove('active'));
        
        const selectedCard = document.getElementById('crm-client-card-' + id);
        if (selectedCard) selectedCard.classList.add('active');
        
        const pane = document.getElementById('crm-detail-pane');
        
        if (userInitiated && window.innerWidth <= 1024 && pane) {
            pane.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        
        // Show loading state with logo.png
        pane.innerHTML = `
            <div style="display:flex; flex-direction:column; justify-content:center; align-items:center; height:100%; color:var(--text-light); text-align:center;">
                <div style="background:var(--primary); width:80px; height:80px; border-radius:50%; display:flex; justify-content:center; align-items:center; margin-bottom:24px; animation: pulse-ring 1.5s cubic-bezier(0.215, 0.61, 0.355, 1) infinite;">
                    <img src="logo.png" style="width:40px; height:40px; filter:brightness(0) invert(1); animation: pulse-logo 1.5s ease-in-out infinite alternate;" alt="Loading...">
                </div>
                <h3 style="color:var(--text-primary); margin:0;">Loading Records...</h3>
                <p style="font-size: 13.5px; margin-top: 6px; max-width:300px;">Please wait while we fetch the pipeline progress.</p>
                <style>
                    @keyframes pulse-ring {
                        0% { box-shadow: 0 0 0 0 rgba(15,23,42,0.3); }
                        70% { box-shadow: 0 0 0 20px rgba(15,23,42,0); }
                        100% { box-shadow: 0 0 0 0 rgba(15,23,42,0); }
                    }
                    @keyframes pulse-logo {
                        0% { transform: scale(0.9); opacity:0.8; }
                        100% { transform: scale(1.1); opacity:1; }
                    }
                </style>
            </div>
        `;
        
        try {
            const response = await fetch(`api.php?api=applicant_full_details&id=${id}`);
            const app = await response.json();
            
            if (app.error) {
                showNotification(app.error, 'error');
                pane.innerHTML = '<div style="text-align:center; padding:40px; color:var(--danger);">Error loading details</div>';
                return;
            }
            
            window.currentLoadedApplicant = app;
            const formatAmt = (val) => new Intl.NumberFormat('en-IN').format(val || 0);
            
            pane.innerHTML = '';
            
            const steps = {
                phase1: ['Phase 1', 'Phase 2', 'Phase 3', 'Phase 4', 'Completed', 'Rejected'].includes(app.overall_status),
                phase2: ['Phase 2', 'Phase 3', 'Phase 4', 'Completed', 'Rejected'].includes(app.overall_status),
                phase3: ['Phase 3', 'Phase 4', 'Completed', 'Rejected'].includes(app.overall_status),
                phase4: ['Phase 4', 'Completed', 'Rejected'].includes(app.overall_status)
            };
            

            // Documents Smart Recommendation Matrix & Uploaded Vault Files
            let docsHtml = '';
            if (app.phase2_checklist && app.phase2_checklist.length > 0) {
                let checklistItemsHtml = '';
                app.phase2_checklist.forEach(item => {
                    const isUploaded = item.uploaded;
                    const hasVault = !isUploaded && item.vault_match;
                    const statusBadge = isUploaded 
                        ? `<span class="badge badge-success" style="font-size:10px; padding:2px 8px;"><i data-lucide="check" style="width:11px;vertical-align:middle;"></i> Ready</span>` 
                        : (hasVault 
                            ? `<span class="badge" style="background:#f3e8ff; color:#7e22ce; border:1px solid #d8b4fe; font-size:10px; padding:2px 8px;"><i data-lucide="sparkles" style="width:11px;vertical-align:middle;"></i> In Vault</span>` 
                            : `<span class="badge" style="background:#fee2e2; color:#dc2626; border:1px solid #fca5a5; font-size:10px; padding:2px 8px;"><i data-lucide="x" style="width:11px;vertical-align:middle;"></i> Missing</span>`);
                    
                    let actionBtn = '';
                    if (isUploaded) {
                        actionBtn = `<button onclick="openTrackPreviewModal('${item.matched_doc.file_path}', '${encodeURIComponent(item.matched_doc.document_name)}')" class="btn btn-secondary" style="padding:2px 8px; font-size:11px; display:inline-flex; align-items:center; gap:3px;"><i data-lucide="eye" style="width:12px;"></i> View</button>`;
                    } else if (hasVault) {
                        actionBtn = `<button onclick="attachTrackVaultDoc(${app.id}, ${item.vault_match.id})" class="btn btn-primary" style="padding:2px 8px; font-size:11px; background:#9333ea; border:none; display:inline-flex; align-items:center; gap:3px;" title="Link from previous file"><i data-lucide="link-2" style="width:12px;"></i> Attach</button>`;
                    } else {
                        actionBtn = `<button onclick="openQuickDocUpload(${app.id}, '${item.category}', '${item.doc_name.replace(/'/g, "\\'")}', '${item.owner_type}', '${item.owner_id || 0}')" class="btn btn-primary" style="padding:2px 8px; font-size:11px; display:inline-flex; align-items:center; gap:3px;"><i data-lucide="upload" style="width:12px;"></i> Upload</button>`;
                    }

                    const ownerTag = item.owner_type === 'CoApplicant' ? `<span style="color:#64748b; font-size:10px; margin-left:4px;">(Co-App)</span>` : '';

                    checklistItemsHtml += `
                        <tr style="border-bottom:1px solid #f1f5f9;">
                            <td style="padding:8px 10px;">
                                <div style="font-weight:600; color:#0f172a; font-size:12px;">${item.doc_name} ${ownerTag}</div>
                                <div style="font-size:10.5px; color:#64748b;">${item.category} ${item.is_mandatory ? '• <span style="color:#dc2626; font-weight:bold;">*Mandatory</span>' : ''}</div>
                            </td>
                            <td style="padding:8px 10px; text-align:center;">${statusBadge}</td>
                            <td style="padding:8px 10px; text-align:right;">${actionBtn}</td>
                        </tr>
                    `;
                });

                docsHtml = `
                    <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:10px; padding:12px; margin-bottom:16px; box-shadow:0 1px 3px rgba(0,0,0,0.02);">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; padding-bottom:6px; border-bottom:1px solid #f1f5f9;">
                            <span style="font-weight:700; color:#0f172a; font-size:12.5px;"><i data-lucide="sparkles" style="width:14px; color:var(--primary); vertical-align:-2px;"></i> Smart Checklist (${app.loan_type})</span>
                            <span class="badge ${app.phase2_completion === 100 ? 'badge-success' : 'badge-warning'}" style="font-size:10.5px;">${app.phase2_completion}% Ready</span>
                        </div>
                        <div class="table-responsive" style="max-height:260px; overflow-y:auto;">
                            <table class="data-table" style="font-size:12px; margin:0; width:100%;">
                                <tbody>
                                    ${checklistItemsHtml}
                                </tbody>
                            </table>
                        </div>
                    </div>
                `;
            } else {
                docsHtml = '<div style="text-align:center; padding:16px; color:var(--text-light); background:#f8fafc; border-radius:8px; margin-bottom:16px;">No checklist found.</div>';
            }
            
            // Bank Login Fee HTML
            let loginFeeHtml = '';
            const hasLoginFee = (app.login_fee_status === 'Paid' || (app.login_fee_amount && parseFloat(app.login_fee_amount) > 0));
            if (hasLoginFee) {
                const lfAmt = formatAmt(app.login_fee_amount);
                const lfMode = app.login_fee_payment_mode || 'UPI';
                const lfDate = app.login_fee_paid_date || '-';
                const lfBank = app.login_fee_bank || app.sanction_bank || 'Bank Partner';
                const lfRef = app.login_fee_ref_no || '-';

                let receiptBtn = '';
                if (app.login_fee_receipt_path) {
                    receiptBtn = `<button type="button" onclick="openTrackPreviewModal('${app.login_fee_receipt_path}', 'Login Fee Receipt - ${lfBank}')" class="btn btn-secondary" style="padding:4px 10px; font-size:11.5px; display:inline-flex; align-items:center; gap:4px;"><i data-lucide="receipt" style="width:13px;"></i> Receipt</button>`;
                }

                loginFeeHtml = `
                    <div style="background: linear-gradient(135deg, #f0fdfa 0%, #ffffff 100%); border: 1.5px solid #5eead4; border-radius: 12px; padding: 14px; margin-bottom: 16px; box-shadow: 0 4px 12px rgba(13, 148, 136, 0.06);">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px; border-bottom: 1px solid #ccfbf1; padding-bottom:8px;">
                            <div>
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <span class="badge" style="background:#0d9488; color:#fff; font-size:10.5px; padding:2px 8px;"><i data-lucide="check-circle" style="width:11px; vertical-align:-1px;"></i> LOGIN FEE PAID</span>
                                    <strong style="color:#0f172a; font-size:13.5px;">${lfBank}</strong>
                                </div>
                                <div style="font-size:11px; color:#64748b; margin-top:3px;">UTR/Ref: <strong>${lfRef}</strong> • Date: <strong>${lfDate}</strong> • Mode: <strong>${lfMode}</strong></div>
                            </div>
                            <div style="display:flex; gap:6px;">
                                ${receiptBtn}
                                <button type="button" onclick="openRecordLoginFeeModal(${app.id})" class="btn btn-secondary" style="padding:4px 8px; font-size:11px; display:inline-flex; align-items:center; gap:3px;"><i data-lucide="edit-2" style="width:12px;"></i> Edit</button>
                                <button type="button" onclick="openCustomerEmailHub(${app.id}, 'Login_Fee')" class="btn btn-primary" style="padding:4px 8px; font-size:11px; background:#0d9488; border:none; display:inline-flex; align-items:center; gap:3px;"><i data-lucide="mail" style="width:12px;"></i> Send Receipt</button>
                            </div>
                        </div>
                        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap:8px; background:#ffffff; padding:10px; border-radius:8px; border:1px solid #e2e8f0; text-align:center;">
                            <div>
                                <div style="font-size:10px; color:#64748b; font-weight:700; text-transform:uppercase;">Fee Amount</div>
                                <div style="font-size:14px; font-weight:800; color:#0f766e; margin-top:2px;">₹${lfAmt}</div>
                            </div>
                            <div>
                                <div style="font-size:10px; color:#64748b; font-weight:700; text-transform:uppercase;">Bank Processing</div>
                                <div style="font-size:12.5px; font-weight:700; color:#0f172a; margin-top:2px;">Official Logged-In</div>
                            </div>
                            <div>
                                <div style="font-size:10px; color:#64748b; font-weight:700; text-transform:uppercase;">Payment Status</div>
                                <div style="font-size:12.5px; font-weight:700; color:#059669; margin-top:2px;">Settled & Cleared</div>
                            </div>
                        </div>
                        ${app.login_fee_notes ? `<div style="margin-top:8px; font-size:11.5px; color:#475569; background:rgba(255,255,255,0.7); padding:6px 10px; border-radius:6px; border-left:3px solid #0d9488;"><strong>Remarks:</strong> ${app.login_fee_notes}</div>` : ''}
                    </div>
                `;
            } else {
                loginFeeHtml = `
                    <div style="background: #f0fdfa; border: 1px dashed #14b8a6; border-radius: 10px; padding: 12px 14px; margin-bottom: 16px; display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <div style="font-weight:700; color:#0f766e; font-size:12.5px; display:flex; align-items:center; gap:6px;">
                                <i data-lucide="receipt" style="width:14px; color:#0d9488;"></i> Bank Login / Processing Fee Pending
                            </div>
                            <div style="font-size:11.5px; color:#115e59; margin-top:2px;">Record client login fee payment, upload bank receipt, and trigger official confirmation.</div>
                        </div>
                        <button type="button" onclick="openRecordLoginFeeModal(${app.id})" class="btn btn-primary" style="background:#0d9488; border:none; padding:5px 12px; font-size:11.5px; font-weight:700; display:inline-flex; align-items:center; gap:5px;">
                            <i data-lucide="plus-circle" style="width:13px;"></i> Record Login Fee
                        </button>
                    </div>
                `;
            }
            
            // Bank Sanction HTML
            let sanctionHtml = '';
            const hasSanction = (app.sanction_status === 'Sanctioned' || (app.sanctioned_amount && parseFloat(app.sanctioned_amount) > 0) || ['Phase 3', 'Phase 4', 'Completed'].includes(app.overall_status));
            if (hasSanction) {
                const sAmt = formatAmt(app.sanctioned_amount || app.loan_amount_requested || 0);
                const rawRoi = app.sanction_roi || app.interest_rate;
                const sRoi = rawRoi ? `${rawRoi}% p.a.` : '8.5% p.a.';
                const rawTenure = app.sanction_tenure || app.tenure_months;
                const sTenure = rawTenure ? `${rawTenure} M` : '180 M';
                const rawEmi = app.sanction_emi || app.emi;
                const sEmi = rawEmi ? `₹${formatAmt(rawEmi)}` : 'N/A';
                const sDate = app.sanction_date || app.disbursed_date || '-';
                const sBank = app.sanction_bank || app.disbursing_bank || 'HDFC Bank';
                const sRef = app.sanction_ref_no || app.disbursement_ref_no || '-';
                
                let letterBtn = '';
                if (app.sanction_letter_path) {
                    letterBtn = `<button type="button" onclick="openTrackPreviewModal('${app.sanction_letter_path}', 'Sanction Letter - ${sBank}')" class="btn btn-secondary" style="padding:4px 10px; font-size:11.5px; display:inline-flex; align-items:center; gap:4px;"><i data-lucide="file-check" style="width:13px;"></i> Letter</button>`;
                }

                sanctionHtml = `
                    <div style="background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 100%); border: 1.5px solid #86efac; border-radius: 12px; padding: 14px; margin-bottom: 16px; box-shadow: 0 4px 12px rgba(22, 163, 74, 0.06);">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px; border-bottom: 1px solid #bbf7d0; padding-bottom:8px;">
                            <div>
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <span class="badge badge-success" style="font-size:10.5px; padding:2px 8px;"><i data-lucide="check-circle" style="width:11px; vertical-align:-1px;"></i> SANCTIONED</span>
                                    <strong style="color:#0f172a; font-size:13.5px;">${sBank}</strong>
                                </div>
                                <div style="font-size:11px; color:#64748b; margin-top:3px;">Ref: <strong>${sRef}</strong> • Date: <strong>${sDate}</strong></div>
                            </div>
                            <div style="display:flex; gap:6px;">
                                ${letterBtn}
                                <button type="button" onclick="openRecordSanctionModal(${app.id}, true)" class="btn btn-secondary" style="padding:4px 8px; font-size:11px; display:inline-flex; align-items:center; gap:3px;"><i data-lucide="edit-2" style="width:12px;"></i> Edit</button>
                                <button type="button" onclick="openCustomerEmailHub(${app.id}, 'Sanction')" class="btn btn-primary" style="padding:4px 8px; font-size:11px; background:#16a34a; border:none; display:inline-flex; align-items:center; gap:3px;"><i data-lucide="mail" style="width:12px;"></i> Email</button>
                            </div>
                        </div>
                        
                        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap:8px; background:#ffffff; padding:10px; border-radius:8px; border:1px solid #e2e8f0; text-align:center;">
                            <div>
                                <div style="font-size:10px; color:#64748b; font-weight:700; text-transform:uppercase;">Sanctioned Amt</div>
                                <div style="font-size:14px; font-weight:800; color:#15803d; margin-top:2px;">₹${sAmt}</div>
                            </div>
                            <div>
                                <div style="font-size:10px; color:#64748b; font-weight:700; text-transform:uppercase;">ROI</div>
                                <div style="font-size:12.5px; font-weight:700; color:#0f172a; margin-top:2px;">${sRoi}</div>
                            </div>
                            <div>
                                <div style="font-size:10px; color:#64748b; font-weight:700; text-transform:uppercase;">Tenure</div>
                                <div style="font-size:12.5px; font-weight:700; color:#0f172a; margin-top:2px;">${sTenure}</div>
                            </div>
                            <div>
                                <div style="font-size:10px; color:#64748b; font-weight:700; text-transform:uppercase;">Monthly EMI</div>
                                <div style="font-size:12.5px; font-weight:700; color:#0f172a; margin-top:2px;">${sEmi}</div>
                            </div>
                        </div>

                        ${app.sanction_notes ? `<div style="margin-top:8px; font-size:11.5px; color:#475569; background:rgba(255,255,255,0.7); padding:6px 10px; border-radius:6px; border-left:3px solid #16a34a;"><strong>Remarks:</strong> ${app.sanction_notes}</div>` : ''}
                    </div>
                `;
            } else {
                sanctionHtml = `
                    <div style="background: #fffbeb; border: 1px dashed #f59e0b; border-radius: 10px; padding: 12px 14px; margin-bottom: 16px; display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <div style="font-weight:700; color:#92400e; font-size:12.5px; display:flex; align-items:center; gap:6px;">
                                <i data-lucide="clock" style="width:14px; color:#d97706;"></i> Bank Sanction Pending
                            </div>
                            <div style="font-size:11.5px; color:#b45309; margin-top:2px;">Record official sanction letter, approved amount, ROI & EMI once approved.</div>
                        </div>
                        <button type="button" onclick="openRecordSanctionModal(${app.id}, false)" class="btn btn-primary" style="background:#d97706; border:none; padding:5px 12px; font-size:11.5px; font-weight:700; display:inline-flex; align-items:center; gap:5px;">
                            <i data-lucide="plus-circle" style="width:13px;"></i> Record Sanction
                        </button>
                    </div>
                `;
            }

            // Phase 4 Disbursements HTML
            let disbHtml = '';
            const isDisbursed = (app.disbursement_status === 'Disbursed' || (app.disbursed_amount && parseFloat(app.disbursed_amount) > 0) || (app.disbursements && app.disbursements.length > 0) || app.overall_status === 'Completed');
            
            if (isDisbursed) {
                const totalDisbAmt = formatAmt(app.disbursed_amount || (app.disbursements ? app.disbursements.reduce((sum, d) => sum + parseFloat(d.amount || 0), 0) : 0));
                const disbBank = app.disbursing_bank || app.sanction_bank || 'HDFC Bank';
                const disbDate = app.disbursed_date || (app.disbursements && app.disbursements[0] ? app.disbursements[0].disbursed_at : '-') || '-';
                const disbRef = app.disbursement_ref_no || (app.disbursements && app.disbursements[0] && app.disbursements[0].remarks ? app.disbursements[0].remarks.replace(/.*UTR:\s*([^|]+).*/, '$1').trim() : '-') || '-';
                const disbMode = app.disbursement_mode || 'Direct RTGS';
                
                const tranches = (app.disbursements && app.disbursements.length > 0) ? app.disbursements : [{
                    phase_number: 1,
                    phase_name: 'Full Disbursement (' + disbBank + ')',
                    amount: app.disbursed_amount || app.sanctioned_amount || 0,
                    status: 'Disbursed',
                    disbursed_at: disbDate,
                    remarks: `UTR: ${disbRef} | Mode: ${disbMode}`
                }];

                disbHtml = `
                    <div style="background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 100%); border: 1.5px solid #86efac; border-radius: 12px; padding: 16px; margin-bottom: 16px; box-shadow: 0 4px 16px rgba(22, 163, 74, 0.08);">
                        <!-- Disbursal Header -->
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; border-bottom: 1px solid #bbf7d0; padding-bottom:10px; flex-wrap:wrap; gap:10px;">
                            <div>
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <span class="badge badge-success" style="font-size:11px; padding:3px 10px; font-weight:800; background:#15803d; color:#ffffff;"><i data-lucide="check-check" style="width:13px; vertical-align:-2px;"></i> DISBURSED &amp; COMPLETED</span>
                                    <strong style="color:#0f172a; font-size:14px;">${disbBank}</strong>
                                </div>
                                <div style="font-size:11.5px; color:#64748b; margin-top:3px;">
                                    UTR / Ref: <strong style="color:#0f172a; font-family:monospace;">${disbRef}</strong> • Date: <strong>${disbDate}</strong> • Mode: <strong>${disbMode}</strong>
                                </div>
                            </div>
                            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                <a href="applicant_bank_assign.php?id=${app.id}" class="btn btn-secondary" style="padding:5px 10px; font-size:11.5px; font-weight:700; display:inline-flex; align-items:center; gap:5px; border-color:#cbd5e1; background:#ffffff;">
                                    <i data-lucide="file-spreadsheet" style="width:13px; color:#0f172a;"></i> Kundli &amp; CAM
                                </a>
                                <a href="applicant_bank_assign.php?id=${app.id}&print=cam" target="_blank" class="btn btn-secondary" style="padding:5px 10px; font-size:11.5px; font-weight:700; display:inline-flex; align-items:center; gap:5px; border-color:#86efac; background:#f0fdf4; color:#15803d;">
                                    <i data-lucide="printer" style="width:13px; color:#16a34a;"></i> Print CAM Report
                                </a>
                                <a href="payout_distribution.php?search=${app.loan_id}" class="btn btn-primary" style="padding:5px 12px; font-size:11.5px; font-weight:700; background:#0f172a; border:none; display:inline-flex; align-items:center; gap:5px;">
                                    <i data-lucide="wallet" style="width:13px;"></i> Payout Desk
                                </a>
                            </div>
                        </div>

                        <!-- 4 Metric Cards -->
                        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap:10px; background:#ffffff; padding:12px; border-radius:10px; border:1px solid #e2e8f0; text-align:center; margin-bottom:14px;">
                            <div>
                                <div style="font-size:10px; color:#64748b; font-weight:700; text-transform:uppercase;">Disbursed Amount</div>
                                <div style="font-size:15px; font-weight:800; color:#15803d; margin-top:2px;">₹${totalDisbAmt}</div>
                            </div>
                            <div>
                                <div style="font-size:10px; color:#64748b; font-weight:700; text-transform:uppercase;">Lender Partner</div>
                                <div style="font-size:13px; font-weight:700; color:#0f172a; margin-top:2px;">${disbBank}</div>
                            </div>
                            <div>
                                <div style="font-size:10px; color:#64748b; font-weight:700; text-transform:uppercase;">Remittance Channel</div>
                                <div style="font-size:12.5px; font-weight:700; color:#0f172a; margin-top:2px;">${disbMode}</div>
                            </div>
                            <div>
                                <div style="font-size:10px; color:#64748b; font-weight:700; text-transform:uppercase;">Banking Status</div>
                                <div style="font-size:12.5px; font-weight:800; color:#15803d; margin-top:2px;"><i data-lucide="check" style="width:12px; vertical-align:-1px;"></i> Released</div>
                            </div>
                        </div>

                        <!-- Tranches Table -->
                        <div style="font-size:11px; font-weight:700; text-transform:uppercase; color:#475569; margin-bottom:6px; letter-spacing:0.5px;">Official Tranche Disbursement Log:</div>
                        <div style="background:#ffffff; border-radius:8px; border:1px solid #e2e8f0; overflow:hidden;">
                            <table class="data-table" style="margin:0; font-size:12px;">
                                <thead>
                                    <tr style="background:#f8fafc;">
                                        <th style="padding:8px 10px;">Phase / Tranche</th>
                                        <th style="padding:8px 10px;">Description</th>
                                        <th style="padding:8px 10px;">Date</th>
                                        <th style="padding:8px 10px; text-align:right;">Amount</th>
                                        <th style="padding:8px 10px; text-align:center;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${tranches.map(d => `
                                        <tr>
                                            <td style="padding:8px 10px; font-weight:700; color:#0f172a;">Phase ${d.phase_number || 1}</td>
                                            <td style="padding:8px 10px; color:#334155;">${d.phase_name || 'Loan Disbursal'} ${d.remarks ? `<div style="font-size:10.5px; color:#64748b;">${d.remarks}</div>` : ''}</td>
                                            <td style="padding:8px 10px; color:#64748b;">${d.disbursed_at || disbDate}</td>
                                            <td style="padding:8px 10px; text-align:right; font-weight:800; color:#15803d;">₹${formatAmt(d.amount)}</td>
                                            <td style="padding:8px 10px; text-align:center;">
                                                <span class="badge ${d.status === 'Disbursed' ? 'badge-success' : 'badge-warning'}" style="font-size:10px; padding:2px 8px;">${d.status || 'Disbursed'}</span>
                                            </td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>
                `;
            } else {
                disbHtml = `
                    <div style="background: #f8fafc; border: 1.5px dashed #cbd5e1; border-radius: 12px; padding: 18px; margin-bottom: 16px; text-align:center;">
                        <div style="width:40px; height:40px; border-radius:50%; background:#f1f5f9; display:inline-flex; align-items:center; justify-content:center; margin-bottom:8px;">
                            <i data-lucide="coins" style="width:20px; height:20px; color:#64748b;"></i>
                        </div>
                        <div style="font-size:13.5px; font-weight:700; color:#0f172a;">Phase 4 Disbursement Workspace</div>
                        <div style="font-size:12px; color:#64748b; margin-top:3px; max-width:440px; margin-left:auto; margin-right:auto;">
                            Official bank sanction is in place. Once borrower signs bank agreements and OTC/PDD conditions are met, finalize disbursement in the Master Kundli desk.
                        </div>
                        <div style="margin-top:12px;">
                            <a href="applicant_bank_assign.php?id=${app.id}" class="btn btn-primary" style="background:#0f172a; border:none; padding:7px 16px; font-size:12px; font-weight:700; display:inline-flex; align-items:center; gap:6px;">
                                <i data-lucide="arrow-right-circle" style="width:14px;"></i> Open Phase 4 Kundli &amp; Disbursal Desk
                            </a>
                        </div>
                    </div>
                `;
            }
            
            // Payout Distributions HTML
            let payoutsHtml = '';
            if (app.payouts && app.payouts.length > 0) {
                payoutsHtml = `
                    <table class="data-table">
                        <thead><tr><th>Payee</th><th>Type</th><th>Net Payable</th><th>Status</th></tr></thead>
                        <tbody>
                            ${app.payouts.map(p => {
                                let badge = 'badge-warning';
                                if (p.status === 'Paid') badge = 'badge-success';
                                if (p.status === 'Cancelled') badge = 'badge-danger';
                                return `<tr>
                                    <td><strong>${p.payee_name || 'N/A'}</strong></td>
                                    <td>${p.payee_type}</td>
                                    <td style="font-weight:bold; color:var(--success);">₹${formatAmt(p.net_payable)}</td>
                                    <td><span class="badge ${badge}">${p.status}</span></td>
                                </tr>`;
                            }).join('')}
                        </tbody>
                    </table>
                `;
            } else {
                payoutsHtml = '<div style="text-align:center; padding:20px; color:var(--text-light);">No internal payouts distributed yet.</div>';
            }

            // Bank Payout / Commission Recovery Desk HTML
            let bankPayoutDeskHtml = '';
            const bpStatus = app.bank_payout_status || 'Pending';
            let bpBadgeClass = 'badge-warning';
            if (bpStatus === 'Received' || bpStatus === 'Settled') bpBadgeClass = 'badge-success';
            if (bpStatus === 'Claimed' || bpStatus === 'Invoice Submitted') bpBadgeClass = 'badge-info';

            const expAmt = app.bank_payout_expected_amount ? formatAmt(app.bank_payout_expected_amount) : (app.sanctioned_amount ? formatAmt(app.sanctioned_amount * (parseFloat(app.bank_payout_expected_pct || 1.25) / 100)) : '0');
            const recAmt = app.bank_payout_received_amount ? formatAmt(app.bank_payout_received_amount) : '0';

            bankPayoutDeskHtml = `
                <div style="background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%); border: 1.5px solid #cbd5e1; border-radius: 12px; padding: 14px; margin-bottom: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; border-bottom:1px solid #e2e8f0; padding-bottom:8px;">
                        <div>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <span class="badge ${bpBadgeClass}" style="font-size:11px; padding:3px 8px;"><i data-lucide="landmark" style="width:12px; vertical-align:-1px;"></i> BANK PAYOUT: ${bpStatus.toUpperCase()}</span>
                                <strong style="color:#0f172a; font-size:13.5px;">Bank Brokerage / Commission Desk</strong>
                            </div>
                            <div style="font-size:11px; color:#64748b; margin-top:3px;">
                                Bank: <strong>${app.sanction_bank || 'Assigned Bank'}</strong> • Invoice No: <strong>${app.bank_payout_invoice_no || 'Not Generated'}</strong>
                            </div>
                        </div>
                        <div style="display:flex; gap:6px;">
                            <button type="button" onclick="openBankerPayoutClaimEmailModal(${app.id})" class="btn btn-secondary" style="padding:4px 8px; font-size:11px; color:#2563eb; border:1px solid #bfdbfe; background:#eff6ff; display:inline-flex; align-items:center; gap:3px;"><i data-lucide="mail" style="width:12px;"></i> Email Banker Claim</button>
                            <button type="button" onclick="openManageBankPayoutModal(${app.id})" class="btn btn-primary" style="padding:4px 10px; font-size:11px; background:#0f172a; border:none; display:inline-flex; align-items:center; gap:3px;"><i data-lucide="edit-3" style="width:12px;"></i> Manage Claim</button>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap:8px; background:#f8fafc; padding:10px; border-radius:8px; border:1px solid #e2e8f0; text-align:center;">
                        <div>
                            <div style="font-size:10px; color:#64748b; font-weight:700; text-transform:uppercase;">Expected Commission</div>
                            <div style="font-size:14px; font-weight:800; color:#0284c7; margin-top:2px;">₹${expAmt} <span style="font-size:11px; font-weight:600; color:#64748b;">(${app.bank_payout_expected_pct || '1.25'}%)</span></div>
                        </div>
                        <div>
                            <div style="font-size:10px; color:#64748b; font-weight:700; text-transform:uppercase;">Received from Bank</div>
                            <div style="font-size:14px; font-weight:800; color:#16a34a; margin-top:2px;">₹${recAmt}</div>
                        </div>
                        <div>
                            <div style="font-size:10px; color:#64748b; font-weight:700; text-transform:uppercase;">Recovery Status</div>
                            <div style="font-size:12.5px; font-weight:700; color:#0f172a; margin-top:2px;">${bpStatus}</div>
                        </div>
                    </div>

                    ${app.bank_payout_notes ? `<div style="margin-top:8px; font-size:11.5px; color:#475569; background:white; padding:6px 10px; border-radius:6px; border-left:3px solid #0284c7;"><strong>Bank Recovery Notes:</strong> ${app.bank_payout_notes}</div>` : ''}
                </div>
            `;
            
            // Banks HTML
            let banksHtml = '';
            if (app.banks && app.banks.length > 0) {
                banksHtml = `
                    <table class="data-table">
                        <thead><tr><th>Bank Name</th><th>Status</th><th>Resolution</th></tr></thead>
                        <tbody>
                            ${app.banks.map(b => {
                                let badge = 'badge-warning';
                                if (b.status === 'Approved') badge = 'badge-success';
                                if (b.status === 'Rejected') badge = 'badge-danger';
                                return `<tr><td><strong>${b.bank_name}</strong></td><td><span class="badge ${badge}">${b.status}</span></td><td style="font-size:12px; color:var(--text-muted);">${b.rejection_reason || '-'}</td></tr>`;
                            }).join('')}
                        </tbody>
                    </table>
                `;
            } else {
                banksHtml = '<div style="text-align:center; padding:20px; color:var(--text-light);">No banks assigned yet.</div>';
            }
            
            // Timeline HTML
            let timelineHtml = '<div class="timeline" style="margin-top:16px;">';
            if(app.timeline && app.timeline.length > 0) {
                app.timeline.forEach(t => {
                    timelineHtml += `
                        <div style="border-left: 2px solid var(--border); padding-left: 12px; margin-bottom: 12px; position:relative;">
                            <div style="position:absolute; left:-6px; top:4px; width:10px; height:10px; background:var(--primary); border-radius:50%;"></div>
                            <div style="font-size:11px; color:var(--text-muted);">${t.created_at}</div>
                            <div style="font-size:13px; color:var(--text-primary);">${t.description}</div>
                            <div style="font-size:11px; color:var(--text-light);">By: ${t.username}</div>
                        </div>
                    `;
                });
            } else {
                timelineHtml += '<div style="color:var(--text-muted); font-size:12px;">No activities recorded.</div>';
            }
            timelineHtml += '</div>';

            let cibilColor = app.cibil_score >= 750 ? 'badge-success' : (app.cibil_score >= 650 ? 'badge-warning' : 'badge-danger');
            let tatDays = app.created_at ? Math.max(0, Math.floor((new Date() - new Date(app.created_at.replace(' ', 'T'))) / (1000 * 60 * 60 * 24))) : 0;
            let tatDisplay = `${tatDays} Days`;
            
            let actionButtons = '';
            if (app.phase1_completion === 100 && app.phase2_completion === 100) {
                let msg = encodeURIComponent(`Hello Bank Team,
Sharing complete file for ${app.customer_name}.
Loan Type: ${app.loan_type}
Amount: ${app.loan_amount_requested}
CIBIL: ${app.cibil_score || 'N/A'}
Please process.`);
                actionButtons = `
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px; border-radius: var(--radius-md); display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <div style="color: #0f172a; font-weight: 600;"><i data-lucide="check-circle" style="vertical-align:-2px; width:16px;"></i> File 100% Ready for Bank Dispatch</div>
                        <div style="display: flex; gap: 8px;">
                            <button type="button" onclick="openBankDispatchModal(${app.id}, '${app.customer_name}', '${app.loan_id}', '${app.loan_type}', '${app.loan_amount_requested}', ${app.documents ? app.documents.length : 0})" class="btn btn-primary" style="background:#0f172a; border:none; padding:6px 12px; font-size:13px;"><i data-lucide="mail" style="width:14px;"></i> Dispatch to Bank</button>
                            <a href="https://wa.me/?text=${msg}" target="_blank" class="btn btn-primary" style="background:#16a34a; border:none; padding:6px 12px; font-size:13px;"><i data-lucide="message-circle" style="width:14px;"></i> WhatsApp</a>
                        </div>
                    </div>
                `;
            }

            const isDeactive = (app.is_active == 0 || app.is_active === '0');

            const mobileBackHtml = (window.innerWidth <= 1024) ? `
                <div style="margin-bottom:12px;">
                    <button type="button" onclick="document.getElementById('crm-client-list').scrollIntoView({behavior:'smooth', block:'start'})" class="btn btn-secondary" style="width:100%; justify-content:center; padding:9px 16px; font-weight:700; font-size:12.5px; background:#fff; border:1.5px solid #cbd5e1; display:flex; align-items:center; gap:8px;">
                        <i data-lucide="arrow-left" style="width:16px;"></i> Back to Applicant List
                    </button>
                </div>
            ` : '';

            pane.innerHTML = `
                ${mobileBackHtml}
                ${isDeactive ? `
                    <div style="background:#fee2e2; border:1.5px dashed #ef4444; color:#991b1b; padding:14px 18px; border-radius:12px; margin-bottom:16px; display:flex; justify-content:space-between; align-items:center; box-shadow:0 4px 12px rgba(239,68,68,0.12);">
                        <div>
                            <div style="font-weight:800; font-size:14px; display:flex; align-items:center; gap:8px;">
                                <i data-lucide="power" style="width:18px; height:18px; color:#dc2626;"></i> APPLICANT FILE IS CURRENTLY DEACTIVATED
                            </div>
                            <div style="font-size:12px; color:#7f1d1d; margin-top:3px;">
                                File is paused and deactivated. Reason: <strong>${app.deactivation_reason || 'Manual Deactivation'}</strong>
                            </div>
                        </div>
                        <button onclick="reactivateApplicantFile(${app.id})" class="btn" style="background:#16a34a; border:none; padding:7px 16px; font-size:12.5px; font-weight:800; color:#ffffff; border-radius:8px; display:inline-flex; align-items:center; gap:6px; cursor:pointer; box-shadow:0 2px 8px rgba(22,163,74,0.3);">
                            <i data-lucide="power" style="width:14px;"></i> Reactivate File
                        </button>
                    </div>
                ` : ''}
                ${app.overall_status === 'Completed' ? '<div style="background: var(--status-won); color: white; padding: 12px; border-radius: 8px 8px 0 0; margin: -24px -24px 20px -24px; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px; gap: 8px;"><i data-lucide="award" style="width:18px;"></i> Loan Application Successfully Completed</div>' : ''}
                ${app.overall_status === 'Rejected' ? '<div style="background: var(--danger); color: white; padding: 12px; border-radius: 8px 8px 0 0; margin: -24px -24px 20px -24px; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px; gap: 8px;"><i data-lucide="x-octagon" style="width:18px;"></i> Loan Application Rejected</div>' : ''}
                
                ${actionButtons}
                
                <!-- EXECUTIVE JET BLACK HEADER CARD -->
                <div class="detail-header-card" style="background: linear-gradient(135deg, #09090b 0%, #18181b 100%); border: 1px solid #27272a; border-radius: 12px; padding: 16px 20px; margin-bottom: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.35); color: #ffffff;">
                    <!-- Row 1: Applicant Name & Badges + Right-Aligned Action Buttons -->
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                            <h2 style="font-size:20px; font-weight:800; color:#ffffff; margin:0; font-family:'Outfit', sans-serif; letter-spacing:-0.3px;">${app.customer_name}</h2>
                            ${isDeactive 
                                ? `<span class="badge" style="font-size:10.5px; padding:3px 9px; border-radius:12px; background:#dc2626; color:#fff; font-weight:700;"><i data-lucide="slash" style="width:11px; vertical-align:-1px;"></i> Deactive File</span>`
                                : `<span class="badge ${app.phase1_completion === 100 ? 'badge-success' : 'badge-warning'}" style="font-size:10.5px; padding:3px 9px; border-radius:12px;">${app.phase1_completion}% Profile Filled</span>`
                            }
                            <span class="badge-status ${app.overall_status.toLowerCase().replace(' ', '')}" style="font-size:11px; padding:4px 12px; border-radius:12px; font-weight:700;">
                                ${app.overall_status === 'Phase 1' && app.phase1_completion === 100 && app.documents && app.documents.length > 0 ? 'Phase 2' : app.overall_status}
                            </span>
                        </div>

                        <!-- Action Dropdown Toolbar (Compact & Sleek) -->
                        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                            <!-- Communication Desk Dropdown -->
                            <div style="position:relative; display:inline-block;" class="action-dropdown-container">
                                <button onclick="toggleActionDropdown(event, 'outreachDropdown_${app.id}')" class="btn" style="background:#27272a; color:#ffffff; border:1px solid #3f3f46; padding:6.5px 13px; border-radius:7px; font-weight:700; font-size:12px; display:inline-flex; align-items:center; gap:5px; box-shadow:0 2px 5px rgba(0,0,0,0.2); cursor:pointer;">
                                    <i data-lucide="message-square" style="width:13px; height:13px; color:#38bdf8;"></i> Communication Desk <i data-lucide="chevron-down" style="width:13px; height:13px; margin-left:2px; color:#a1a1aa;"></i>
                                </button>
                                <div id="outreachDropdown_${app.id}" class="action-dropdown-menu" style="display:none; position:absolute; top:calc(100% + 4px); right:0; min-width:240px; background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; box-shadow:0 12px 30px rgba(0,0,0,0.25); z-index:9999; padding:5px 0; font-size:12px; color:#0f172a;">
                                    <a href="javascript:void(0)" onclick="closeAllActionDropdowns(); openClientTrackingModal(${app.id})" style="display:flex; align-items:center; gap:9px; padding:8px 14px; color:#0284c7; text-decoration:none; font-weight:700;" onmouseover="this.style.background='#f0f9ff';" onmouseout="this.style.background='transparent';">
                                        <i data-lucide="globe" style="width:14px; height:14px; color:#0284c7;"></i> Client Live Tracking Portal
                                    </a>
                                    <a href="javascript:void(0)" onclick="closeAllActionDropdowns(); openLogInteractionModal(${app.id}, '${encodeURIComponent(app.customer_name)}')" style="display:flex; align-items:center; gap:9px; padding:8px 14px; color:#1e293b; text-decoration:none; font-weight:600;" onmouseover="this.style.background='#f1f5f9';" onmouseout="this.style.background='transparent';">
                                        <i data-lucide="phone-call" style="width:14px; height:14px; color:#0f766e;"></i> Log Call / Interaction
                                    </a>
                                    <a href="javascript:void(0)" onclick="closeAllActionDropdowns(); openWhatsAppQuickMenu('${app.mobile}', '${encodeURIComponent(app.customer_name)}', '${app.loan_id}', '${app.loan_type}', '${app.overall_status}')" style="display:flex; align-items:center; gap:9px; padding:8px 14px; color:#1e293b; text-decoration:none; font-weight:600;" onmouseover="this.style.background='#f1f5f9';" onmouseout="this.style.background='transparent';">
                                        <i data-lucide="message-circle" style="width:14px; height:14px; color:#16a34a;"></i> 1-Click WhatsApp Desk
                                    </a>
                                    <a href="javascript:void(0)" onclick="closeAllActionDropdowns(); generateMissingDocsWhatsAppQuery(${app.id})" style="display:flex; align-items:center; gap:9px; padding:8px 14px; color:#1e293b; text-decoration:none; font-weight:600;" onmouseover="this.style.background='#f1f5f9';" onmouseout="this.style.background='transparent';">
                                        <i data-lucide="file-question" style="width:14px; height:14px; color:#ea580c;"></i> Missing Docs Query
                                    </a>
                                    <a href="javascript:void(0)" onclick="closeAllActionDropdowns(); openCustomerEmailHub(${app.id})" style="display:flex; align-items:center; gap:9px; padding:8px 14px; color:#1e293b; text-decoration:none; font-weight:600;" onmouseover="this.style.background='#f1f5f9';" onmouseout="this.style.background='transparent';">
                                        <i data-lucide="mail" style="width:14px; height:14px; color:#2563eb;"></i> Email Dispatch Hub
                                    </a>
                                    <div style="height:1px; background:#f1f5f9; margin:4px 0;"></div>
                                    ${isDeactive ? `
                                        <a href="javascript:void(0)" onclick="closeAllActionDropdowns(); reactivateApplicantFile(${app.id})" style="display:flex; align-items:center; gap:9px; padding:8px 14px; color:#16a34a; text-decoration:none; font-weight:600;" onmouseover="this.style.background='#f0fdf4';" onmouseout="this.style.background='transparent';">
                                            <i data-lucide="power" style="width:14px; height:14px; color:#16a34a;"></i> Reactivate File
                                        </a>
                                    ` : `
                                        <a href="javascript:void(0)" onclick="closeAllActionDropdowns(); openDeactivateModal(${app.id}, '${encodeURIComponent(app.customer_name)}', '${app.loan_id}', '${encodeURIComponent(app.email || '')}')" style="display:flex; align-items:center; gap:9px; padding:8px 14px; color:#dc2626; text-decoration:none; font-weight:600;" onmouseover="this.style.background='#fef2f2';" onmouseout="this.style.background='transparent';">
                                            <i data-lucide="power" style="width:14px; height:14px; color:#dc2626;"></i> Deactivate File (Email OTP)
                                        </a>
                                    `}
                                    <div style="height:1px; background:#f1f5f9; margin:4px 0;"></div>
                                    <a href="javascript:void(0)" onclick="closeAllActionDropdowns(); openReminderModal('Lead', ${app.id}, '${encodeURIComponent(app.customer_name)}')" style="display:flex; align-items:center; gap:9px; padding:8px 14px; color:#1e293b; text-decoration:none; font-weight:600;" onmouseover="this.style.background='#f1f5f9';" onmouseout="this.style.background='transparent';">
                                        <i data-lucide="bell" style="width:14px; height:14px; color:#d97706;"></i> Schedule Reminder
                                    </a>
                                    <a href="javascript:void(0)" onclick="closeAllActionDropdowns(); openTransferRmModal(${app.id}, '${encodeURIComponent(app.customer_name)}', '${app.loan_id}', '${app.assigned_to || ''}')" style="display:flex; align-items:center; gap:9px; padding:8px 14px; color:#1e293b; text-decoration:none; font-weight:600;" onmouseover="this.style.background='#f1f5f9';" onmouseout="this.style.background='transparent';">
                                        <i data-lucide="user-check" style="width:14px; height:14px; color:#6366f1;"></i> Assign / Transfer RM
                                    </a>
                                </div>
                            </div>

                            <!-- Documents & Reports Dropdown -->
                            <div style="position:relative; display:inline-block;" class="action-dropdown-container">
                                <button onclick="toggleActionDropdown(event, 'formsDropdown_${app.id}')" class="btn" style="background:#27272a; color:#ffffff; border:1px solid #3f3f46; padding:6.5px 13px; border-radius:7px; font-weight:700; font-size:12px; display:inline-flex; align-items:center; gap:5px; box-shadow:0 1px 3px rgba(0,0,0,0.2); cursor:pointer;">
                                    <i data-lucide="folder-archive" style="width:13px; height:13px; color:#38bdf8;"></i> Documents & Reports <i data-lucide="chevron-down" style="width:13px; height:13px; margin-left:2px; color:#a1a1aa;"></i>
                                </button>
                                <div id="formsDropdown_${app.id}" class="action-dropdown-menu" style="display:none; position:absolute; top:calc(100% + 4px); right:0; min-width:230px; background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; box-shadow:0 12px 30px rgba(0,0,0,0.25); z-index:9999; padding:5px 0; font-size:12px; color:#0f172a;">
                                    <a href="print_application_form.php?id=${app.id}" target="_blank" onclick="closeAllActionDropdowns();" style="display:flex; align-items:center; gap:9px; padding:8px 14px; color:#1e293b; text-decoration:none; font-weight:600;" onmouseover="this.style.background='#f1f5f9';" onmouseout="this.style.background='transparent';">
                                        <i data-lucide="printer" style="width:14px; height:14px; color:#0f172a;"></i> Form PDF & Print
                                    </a>
                                    <a href="generate_cam.php?id=${app.id}" target="_blank" onclick="closeAllActionDropdowns();" style="display:flex; align-items:center; gap:9px; padding:8px 14px; color:#1e293b; text-decoration:none; font-weight:600;" onmouseover="this.style.background='#f1f5f9';" onmouseout="this.style.background='transparent';">
                                        <i data-lucide="file-text" style="width:14px; height:14px; color:#3b82f6;"></i> Generate CAM Memo
                                    </a>
                                    <div style="height:1px; background:#f1f5f9; margin:4px 0;"></div>
                                    <a href="add_applicant.php?id=${app.id}" onclick="closeAllActionDropdowns();" style="display:flex; align-items:center; gap:9px; padding:8px 14px; color:#1e293b; text-decoration:none; font-weight:600;" onmouseover="this.style.background='#f1f5f9';" onmouseout="this.style.background='transparent';">
                                        <i data-lucide="edit-3" style="width:14px; height:14px; color:#0f766e;"></i> Edit Applicant Profile
                                    </a>
                                </div>
                            </div>

                            <!-- Primary Log Quick Call Button -->
                            <button onclick="openLogInteractionModal(${app.id}, '${encodeURIComponent(app.customer_name)}')" class="btn" style="background:#0d9488; color:#ffffff; border:none; padding:6.5px 14px; border-radius:7px; font-weight:700; font-size:12px; display:inline-flex; align-items:center; gap:5px; box-shadow:0 2px 8px rgba(13,148,136,0.3); cursor:pointer;">
                                <i data-lucide="phone-call" style="width:13px; height:13px;"></i> Log Quick Call
                            </button>

                            <!-- HIGH-VISIBILITY TOP-RIGHT LIVE TRACKING BUTTON (VIBRANT GREEN) -->
                            <button onclick="openClientTrackingModal(${app.id})" class="btn" style="background: linear-gradient(135deg, #16a34a 0%, #15803d 100%); color: #ffffff; border: 1px solid rgba(74, 222, 128, 0.4); padding: 6.5px 15px; border-radius: 7px; font-weight: 800; font-size: 12px; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 4px 14px rgba(22, 163, 74, 0.45); cursor: pointer; transition: all 0.2s ease;" onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 6px 18px rgba(22, 163, 74, 0.65)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 14px rgba(22, 163, 74, 0.45)'" title="View & Share Client Live Tracking Portal Link">
                                <span style="width:7px; height:7px; border-radius:50%; background:#ffffff; display:inline-block; box-shadow:0 0 6px #ffffff;"></span>
                                <i data-lucide="globe" style="width:13.5px; height:13.5px; color:#ffffff;"></i>
                                <span style="letter-spacing:-0.2px;">Client Live Portal</span>
                            </button>
                        </div>
                    </div>

                    <!-- Row 2: Executive Single-Line Metadata Bar -->
                    <div style="margin-top:14px; padding-top:12px; border-top:1px solid #27272a; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; font-size:12px; color:#a1a1aa;">
                        <div style="display:flex; align-items:center; gap:14px; flex-wrap:wrap;">
                            <div><span style="color:#71717a; font-weight:600;">Loan Ref:</span> <strong style="color:#ffffff;">${app.loan_id}</strong></div>
                            <div style="color:#3f3f46;">•</div>
                            <div><span style="color:#71717a; font-weight:600;">Mobile:</span> <strong style="color:#ffffff;">${app.mobile}</strong></div>
                            <div style="color:#3f3f46;">•</div>
                            <div><span style="color:#71717a; font-weight:600;">Email:</span> <strong style="color:#ffffff;">${app.email || 'N/A'}</strong></div>
                            <div style="color:#3f3f46;">•</div>
                            <div><span style="color:#71717a; font-weight:600;">TAT Ageing:</span> <span class="badge" style="background:#27272a; color:#f4f4f5; font-weight:700; border:1px solid #3f3f46; padding:2px 7px; font-size:11px;">${tatDisplay}</span></div>
                            <div style="color:#3f3f46;">•</div>
                            <div><span style="color:#71717a; font-weight:600;">Source:</span> <strong style="color:#ffffff;">${app.lead_source || 'Direct'}</strong></div>
                        </div>
                        <div style="display:flex; align-items:center; gap:14px; flex-wrap:wrap;">
                            <div style="display:flex; align-items:center; gap:6px;">
                                <span style="color:#71717a; font-weight:600;">RM:</span>
                                <strong style="color:#ffffff;">${app.assigned_to || 'Unassigned'}</strong>
                                <button onclick="openTransferRmModal(${app.id}, '${encodeURIComponent(app.customer_name)}', '${app.loan_id}', '${app.assigned_to || ''}')" style="background:none; border:none; color:#60a5fa; font-size:11px; font-weight:700; cursor:pointer; padding:0 2px; text-decoration:underline;" title="Transfer RM">Transfer</button>
                            </div>
                            <div style="color:#3f3f46;">•</div>
                            <div><span style="color:#71717a; font-weight:600;">Added By:</span> <strong style="color:#ffffff;">${app.created_by_name || 'System'}</strong></div>
                            <div style="color:#3f3f46;">•</div>
                            <div style="display:flex; align-items:center; gap:4px;">
                                <span style="color:#71717a; font-weight:600;">CIBIL:</span>
                                <span class="badge ${cibilColor}" style="font-weight:700; font-size:11px; padding:2px 7px;">${app.cibil_score || 'N/A'}</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                ${app.phase1_completion < 100 ? `
                    <div style="background: #fffbeb; border: 1px solid #fcd34d; padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 16px; display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <div style="color: #b45309; font-weight: 700; margin-bottom:4px;"><i data-lucide="alert-triangle" style="width:16px; vertical-align:-3px;"></i> Incomplete Phase 1 Profile (${app.phase1_completion}%)</div>
                            <div style="color: #92400e; font-size: 12px;">Missing details: <strong>${app.phase1_missing ? app.phase1_missing.join(', ') : 'Required fields'}</strong></div>
                        </div>
                        <a href="add_applicant.php?id=${app.id}" class="btn" style="background:#b45309; color:white; border:none; padding:6px 14px; font-size:12px; font-weight:700; border-radius:6px;">Complete Profile</a>
                    </div>
                ` : ''}
                
                ${(() => {
                    if (app.pd_report && (app.pd_report.pd_conducted_by || app.pd_report.final_pd_status)) {
                        let badgeClass = 'badge-info';
                        if(app.pd_report.final_pd_status === 'Positive') badgeClass = 'badge-success';
                        else if(app.pd_report.final_pd_status === 'Negative') badgeClass = 'badge-danger';
                        else if(app.pd_report.final_pd_status === 'Refer to Risk Manager') badgeClass = 'badge-warning';
                        
                        return `
                        <div style="margin-top:16px; margin-bottom:16px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:16px; box-shadow:0 1px 2px rgba(0,0,0,0.05);">
                            <div style="font-weight:600; font-size:14px; margin-bottom:12px; display:flex; justify-content:space-between; align-items:center;">
                                <span style="color:#0f172a;"><i data-lucide="clipboard-check" style="width:16px; height:16px; vertical-align:text-bottom; margin-right:4px; color:var(--primary);"></i> PD Assessment Summary</span>
                                ${app.pd_report.final_pd_status ? `<span class="badge ${badgeClass}">${app.pd_report.final_pd_status}</span>` : ''}
                            </div>
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; font-size:13px; color:#475569;">
                                <div><strong>Conducted By:</strong> ${app.pd_report.pd_conducted_by || '-'}</div>
                                <div><strong>Mode:</strong> ${app.pd_report.pd_mode || '-'}</div>
                                <div><strong>Date:</strong> ${app.pd_report.pd_date ? new Date(app.pd_report.pd_date).toLocaleString() : '-'}</div>
                                <div><strong>Rec. Amount:</strong> ₹${app.pd_report.recommended_loan_amount ? parseFloat(app.pd_report.recommended_loan_amount).toLocaleString('en-IN') : '-'}</div>
                            </div>
                            ${app.pd_report.pd_report_path ? `<div style="margin-top:12px;"><a href="${app.pd_report.pd_report_path}" target="_blank" class="btn btn-secondary" style="font-size:12px; padding:4px 8px; background:white;"><i data-lucide="external-link" style="width:12px; height:12px;"></i> View Full PD Report</a></div>` : ''}
                        </div>`;
                    }
                    return '';
                })()}
                
                <!-- LOS Pipeline Tracker -->
                <div class="detail-block-title" style="margin-top:20px;">LOS Pipeline Progress</div>
                <div class="pipeline-tracker">
                    <div class="pipeline-step ${app.phase1_completion === 100 ? 'completed' : 'active'}" 
                         onclick="window.location.href='add_applicant.php?id=${app.id}'" 
                         style="cursor:pointer;" title="Go to KYC">
                        <div class="pipeline-icon-circle"><i data-lucide="user-check" style="width:14px;"></i></div>
                        <span class="pipeline-step-label">1. KYC</span>
                    </div>
                    
                    <div class="pipeline-step ${app.phase2_completion === 100 ? 'completed' : (app.phase1_completion === 100 ? 'active' : '')}" 
                         onclick="${app.phase1_completion === 100 ? `window.location.href='applicant_documents.php?id=${app.id}'` : `showCustomAlert('Action Blocked', 'Please complete Step 1 (KYC) first.')`}" 
                         style="cursor:${app.phase1_completion === 100 ? 'pointer' : 'not-allowed'}; opacity:${app.phase1_completion === 100 ? '1' : '0.6'};" title="Go to Docs">
                        <div class="pipeline-icon-circle"><i data-lucide="file-text" style="width:14px;"></i></div>
                        <span class="pipeline-step-label">2. Docs</span>
                    </div>
                    
                    <div class="pipeline-step ${hasSanction ? 'completed' : (app.phase2_completion === 100 ? 'active' : '')}" 
                         onclick="${app.phase2_completion === 100 ? `window.location.href='applicant_disbursements.php?id=${app.id}'` : `showCustomAlert('Action Blocked', 'Please complete Step 2 (Docs) first.')`}" 
                         style="cursor:${app.phase2_completion === 100 ? 'pointer' : 'not-allowed'}; opacity:${app.phase2_completion === 100 ? '1' : '0.6'};" title="Go to Bank & Sanction">
                        <div class="pipeline-icon-circle"><i data-lucide="landmark" style="width:14px;"></i></div>
                        <span class="pipeline-step-label">3. Sanction</span>
                    </div>
                    
                    <div class="pipeline-step ${steps.phase4 ? 'completed' : (hasSanction ? 'active' : '')}" 
                         onclick="${hasSanction ? `window.location.href='applicant_bank_assign.php?id=${app.id}'` : `showCustomAlert('Action Blocked', 'Please record Bank Sanction first.')`}" 
                         style="cursor:${hasSanction ? 'pointer' : 'not-allowed'}; opacity:${hasSanction ? '1' : '0.6'};" title="Go to Disb.">
                        <div class="pipeline-icon-circle"><i data-lucide="coins" style="width:14px;"></i></div>
                        <span class="pipeline-step-label">4. Disb.</span>
                    </div>
                    
                    <div class="pipeline-step ${(app.payouts && app.payouts.length > 0) ? 'completed' : (steps.phase4 ? 'active' : '')}" 
                         onclick="${steps.phase4 ? `window.location.href='payout_distribution.php?search=${app.loan_id}'` : `showCustomAlert('Action Blocked', 'Please complete Step 4 (Disbursement) first.')`}" 
                         style="cursor:${steps.phase4 ? 'pointer' : 'not-allowed'}; opacity:${steps.phase4 ? '1' : '0.6'};" title="Go to Payout">
                        <div class="pipeline-icon-circle"><i data-lucide="wallet" style="width:14px;"></i></div>
                        <span class="pipeline-step-label">5. Payout</span>
                    </div>
                </div>
                
                <div id="income-clubbing-widget" style="margin-top: 20px;"></div>
                
                <!-- Notes Input -->
                <div style="margin-bottom: 20px; display:flex; gap:8px;">
                    <input type="text" id="noteInput" placeholder="Add an internal note or remark..." style="flex:1; padding:8px 12px; border:1px solid var(--border); border-radius:var(--radius-md);">
                    <button onclick="addNote(${app.id})" class="btn btn-secondary" style="padding:8px 16px;"><i data-lucide="send" style="width:16px;"></i></button>
                </div>

                <!-- MAIN DASHBOARD GRID WITH WORKSPACE DESK TABS -->
                <div class="dashboard-layout-row applicant-workspace-grid">
                    <div>
                        <!-- SEGMENTED DESK TAB BAR -->
                        <div class="track-segmented-tabs" style="display:flex; gap:6px; margin-bottom:16px; background:#f1f5f9; padding:5px; border-radius:10px; border:1px solid #e2e8f0; overflow-x:auto;">
                            <button type="button" class="track-desk-tab active" onclick="switchTrackDeskTab(event, 'desk-docs')" style="flex:1; padding:8px 12px; border:none; border-radius:7px; font-weight:700; font-size:12px; cursor:pointer; background:#0f172a; color:#ffffff; display:flex; align-items:center; justify-content:center; gap:6px; white-space:nowrap; transition:all 0.2s ease;">
                                <i data-lucide="file-text" style="width:14px; height:14px;"></i> Phase 2 Docs
                            </button>
                            <button type="button" class="track-desk-tab" onclick="switchTrackDeskTab(event, 'desk-sanction')" style="flex:1; padding:8px 12px; border:none; border-radius:7px; font-weight:700; font-size:12px; cursor:pointer; background:transparent; color:#475569; display:flex; align-items:center; justify-content:center; gap:6px; white-space:nowrap; transition:all 0.2s ease;">
                                <i data-lucide="landmark" style="width:14px; height:14px;"></i> Phase 3 Bank & Sanction
                            </button>
                            <button type="button" class="track-desk-tab" onclick="switchTrackDeskTab(event, 'desk-disb')" style="flex:1; padding:8px 12px; border:none; border-radius:7px; font-weight:700; font-size:12px; cursor:pointer; background:transparent; color:#475569; display:flex; align-items:center; justify-content:center; gap:6px; white-space:nowrap; transition:all 0.2s ease;">
                                <i data-lucide="coins" style="width:14px; height:14px;"></i> Phase 4 Disb.
                            </button>
                            <button type="button" class="track-desk-tab" onclick="switchTrackDeskTab(event, 'desk-payout')" style="flex:1; padding:8px 12px; border:none; border-radius:7px; font-weight:700; font-size:12px; cursor:pointer; background:transparent; color:#475569; display:flex; align-items:center; justify-content:center; gap:6px; white-space:nowrap; transition:all 0.2s ease;">
                                <i data-lucide="wallet" style="width:14px; height:14px;"></i> Payout Desk
                            </button>
                            <button type="button" class="track-desk-tab" onclick="switchTrackDeskTab(event, 'desk-comm')" style="flex:1; padding:8px 12px; border:none; border-radius:7px; font-weight:700; font-size:12px; cursor:pointer; background:transparent; color:#475569; display:flex; align-items:center; justify-content:center; gap:6px; white-space:nowrap; transition:all 0.2s ease;">
                                <i data-lucide="message-square" style="width:14px; height:14px;"></i> Comms & Queries
                            </button>
                        </div>

                        <!-- TAB 1: PHASE 2 DOCS HUB -->
                        <div id="desk-docs" class="track-desk-content" style="display:block;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                                <div class="detail-block-title" style="margin:0;">Phase 2: Document Hub</div>
                                <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                    <a href="generate_zip.php?id=${app.id}" class="btn btn-primary" style="padding:4px 10px; font-size:11.5px; background:#0f172a; border:none; display:inline-flex; align-items:center; gap:4px;" title="Download complete organized dossier"><i data-lucide="archive" style="width:13px;"></i> ZIP Bundle</a>
                                    <button onclick="openQuickDocUpload(${app.id}, '', '', 'Applicant', 0)" class="btn btn-primary" style="padding:4px 10px; font-size:11.5px; display:inline-flex; align-items:center; gap:4px;"><i data-lucide="upload" style="width:13px;"></i> Quick Upload</button>
                                    <a href="applicant_documents.php?id=${app.id}" class="btn btn-secondary" style="padding:4px 10px; font-size:11.5px; display:inline-flex; align-items:center; gap:4px;"><i data-lucide="external-link" style="width:13px;"></i> Full Manager</a>
                                </div>
                            </div>
                            ${docsHtml}
                        </div>
                        
                        <!-- TAB 2: PHASE 3 BANK PROCESSING & SANCTION -->
                        <div id="desk-sanction" class="track-desk-content" style="display:none;">
                            <div class="detail-block-title" style="margin:0 0 12px 0; display:flex; justify-content:space-between; align-items:center;">
                                <span>Phase 3: Bank Processing & Sanction</span>
                                <div style="display:flex; gap:6px;">
                                    <button onclick="openRecordLoginFeeModal(${app.id})" class="btn btn-secondary" style="padding:3px 8px; font-size:11px; display:inline-flex; align-items:center; gap:4px;">
                                        <i data-lucide="receipt" style="width:12px;"></i> ${hasLoginFee ? 'Edit Login Fee' : '+ Login Fee'}
                                    </button>
                                    <button onclick="openRecordSanctionModal(${app.id}, ${hasSanction})" class="btn btn-primary" style="padding:3px 10px; font-size:11.5px; display:inline-flex; align-items:center; gap:4px; background:#0f172a; border:none;">
                                        <i data-lucide="file-check" style="width:13px;"></i> ${hasSanction ? 'Edit Sanction' : '+ Record Sanction'}
                                    </button>
                                </div>
                            </div>
                            <div style="margin-bottom:12px;"><a href="applicant_disbursements.php?id=${app.id}" class="btn btn-secondary" style="padding:4px 12px; font-size:12px;"><i data-lucide="landmark" style="width:14px;"></i> Bank Processing Workspace</a></div>
                            ${loginFeeHtml}
                            ${sanctionHtml}
                            ${banksHtml}
                        </div>
                        
                        <!-- TAB 3: PHASE 4 FINAL DISBURSEMENTS -->
                        <div id="desk-disb" class="track-desk-content" style="display:none;">
                            <div class="detail-block-title" style="margin:0 0 12px 0;">Phase 4: Final Disbursements</div>
                            <div style="margin-bottom:12px;"><a href="applicant_bank_assign.php?id=${app.id}" class="btn btn-secondary" style="padding:4px 12px; font-size:12px;"><i data-lucide="coins" style="width:14px;"></i> Customer Kundli & Disb.</a></div>
                            ${disbHtml}
                        </div>

                        <!-- TAB 4: PAYOUT RECOVERY DESK -->
                        <div id="desk-payout" class="track-desk-content" style="display:none;">
                            <div class="detail-block-title" style="margin:0 0 12px 0;">Banker Commission & Payout Recovery Desk</div>
                            ${bankPayoutDeskHtml}
                            <div style="margin:16px 0 12px 0; display:flex; justify-content:space-between; align-items:center;">
                                <span style="font-size:12px; font-weight:700; color:#475569;">Internal Referral & Staff Distributions:</span>
                                <a href="payout_distribution.php?search=${app.loan_id}" class="btn btn-secondary" style="padding:3px 10px; font-size:11px;"><i data-lucide="coins" style="width:12px;"></i> Distribute Payee Payouts</a>
                            </div>
                            ${payoutsHtml}
                        </div>

                        <!-- TAB 5: COMMUNICATION & BANK QUERIES -->
                        <div id="desk-comm" class="track-desk-content" style="display:none;">
                            <!-- BANK QUERY TRACKER -->
                            <div class="detail-section" id="bank-query-section" style="margin-top:0;">
                                <div class="section-header" onclick="const qb = document.getElementById('query-body'); qb.style.display = qb.style.display === 'none' ? 'block' : 'none';" style="cursor:pointer; display:flex; justify-content:space-between; align-items:center; background:#fff; border:1px solid #f1f5f9; border-radius:10px; padding:12px 16px; margin-bottom:8px;">
                                    <span style="font-weight:700; font-size:13px; color:#0f172a; display:flex; align-items:center; gap:8px;">
                                        <i data-lucide='alert-triangle' style='width:15px;height:15px;color:#f59e0b;'></i>
                                        Bank Query Tracker
                                    </span>
                                    <button onclick="event.stopPropagation(); openQueryModal(${app.id})" style="background:#000;color:#fff;border:none;border-radius:6px;padding:4px 10px;font-size:11px;cursor:pointer;">+ Raise Query</button>
                                </div>
                                <div id="query-body" style="display:block;">
                                    <div id="bank-queries-list"><!-- Loaded via JS --></div>
                                </div>
                            </div>

                            <!-- WHATSAPP QUICK MESSAGING -->
                            <div class="detail-section" id="whatsapp-section" style="margin-top:16px;">
                                <div style="background:#fff; border:1px solid #f1f5f9; border-radius:10px; padding:12px 16px;">
                                    <div style="font-weight:700; font-size:13px; color:#0f172a; margin-bottom:12px; display:flex; align-items:center; gap:8px;">
                                        <i data-lucide='message-circle' style='width:15px;height:15px;color:#25D366;'></i>
                                        WhatsApp Quick Messaging
                                    </div>
                                    <div id="wa-templates-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                                        <!-- Loaded via JS -->
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                    
                    <div style="background:#f8fafc; border:1px solid var(--border); border-radius:var(--radius-md); padding:16px;">
                        <h4 style="margin:0 0 16px 0; font-size:14px; color:var(--text-primary);"><i data-lucide="clock" style="width:16px; vertical-align:-2px;"></i> Activity Timeline</h4>
                        ${timelineHtml}
                    </div>
                </div>

                <!-- BOTTOM APPLICANT FILE DEACTIVATION / REACTIVATION BAR -->
                <div style="margin-top:24px; padding-top:16px; border-top:1px dashed #cbd5e1; display:flex; justify-content:space-between; align-items:center; background:#ffffff; border:1px solid #cbd5e1; border-radius:12px; padding:16px 20px; box-shadow:0 2px 10px rgba(15,23,42,0.03);">
                    <div>
                        <div style="font-weight:800; font-size:13.5px; color:#0f172a; display:flex; align-items:center; gap:8px;">
                            <i data-lucide="shield-alert" style="width:17px; height:17px; color:${isDeactive ? '#16a34a' : '#dc2626'};"></i>
                            ${isDeactive ? 'Applicant File Status: DEACTIVATED' : 'Applicant File Management & Status'}
                        </div>
                        <div style="font-size:12px; color:#64748b; margin-top:3px;">
                            ${isDeactive 
                                ? `This application is currently deactivated. Click Reactivate File to restore full workspace access.` 
                                : `Need to pause or deactivate this application? Trigger email OTP verification code to safely deactivate file without deleting any data.`
                            }
                        </div>
                    </div>
                    <div>
                        ${isDeactive ? `
                            <button onclick="reactivateApplicantFile(${app.id})" class="btn" style="background:#16a34a; color:#ffffff; border:none; padding:8px 18px; border-radius:8px; font-weight:800; font-size:12.5px; display:inline-flex; align-items:center; gap:6px; box-shadow:0 2px 8px rgba(22,163,74,0.3); cursor:pointer;">
                                <i data-lucide="power" style="width:15px; height:15px;"></i> Reactivate File
                            </button>
                        ` : `
                            <button onclick="openDeactivateModal(${app.id}, '${encodeURIComponent(app.customer_name)}', '${app.loan_id}', '${encodeURIComponent(app.email || '')}')" class="btn" style="background:#dc2626; color:#ffffff; border:none; padding:8px 18px; border-radius:8px; font-weight:800; font-size:12.5px; display:inline-flex; align-items:center; gap:6px; box-shadow:0 2px 8px rgba(220,38,38,0.3); cursor:pointer;" title="Deactivate File via Email OTP Verification">
                                <i data-lucide="power" style="width:15px; height:15px;"></i> Deactivate File
                            </button>
                        `}
                    </div>
                </div>
            `;
            
            lucide.createIcons();
            
            calculateIncomeClubing(app, app.co_applicants || []);
            
            // Load Bank Queries and WhatsApp Templates
            loadBankQueries(app.id);
            loadWhatsAppTemplates(app.id, app.mobile || app.phone || app.phone_number || '');

        } catch (err) {
            console.error(err);
            showNotification('Could not load applicant LOS details.', 'error');
        }
    }

    function switchTrackDeskTab(evt, targetId) {
        document.querySelectorAll('.track-desk-tab').forEach(b => {
            b.style.background = 'transparent';
            b.style.color = '#475569';
            b.classList.remove('active');
        });
        if(evt && evt.currentTarget) {
            evt.currentTarget.style.background = '#0f172a';
            evt.currentTarget.style.color = '#ffffff';
            evt.currentTarget.classList.add('active');
        }

        document.querySelectorAll('.track-desk-content').forEach(c => {
            c.style.display = 'none';
        });
        const target = document.getElementById(targetId);
        if(target) target.style.display = 'block';
    }

    function calculateIncomeClubing(app, coApps) {
        const widget = document.getElementById('income-clubbing-widget');
        if (!widget) return;
        
        let appIncome = parseFloat(app.monthly_income) || 0;
        let totalIncome = appIncome;
        let appEmi = parseFloat(app.emi || app.sanction_emi) || 0;
        let totalEmi = appEmi;
        
        let coAppHtml = '';
        if (coApps && coApps.length > 0) {
            coApps.forEach(co => {
                if (co.is_financial === 'Yes' || co.is_financial == 1 || String(co.is_financial).toLowerCase() === 'yes') {
                    let cInc = parseFloat(co.monthly_income) || 0;
                    totalIncome += cInc;
                    let cEmi = parseFloat(co.current_emis) || 0;
                    totalEmi += cEmi;
                    let coName = co.full_name || co.name || 'Co-Applicant';
                    let coRel = co.relationship ? ` (${co.relationship})` : '';
                    coAppHtml += `<div style="display:flex; justify-content:space-between; font-size:12px; padding:4px 0;"><span>Co-Applicant (${coName}${coRel}):</span> <strong>₹${new Intl.NumberFormat('en-IN').format(cInc)}</strong></div>`;
                }
            });
        }
        
        let foir = totalIncome > 0 ? ((totalEmi / totalIncome) * 100).toFixed(1) : 0;
        let foirColor = foir > 55 ? '#dc2626' : (foir >= 40 ? '#d97706' : '#16a34a');
        
        // FOIR benchmark in Indian banks: 50% max allowable EMI capacity
        let maxAllowedEmi = totalIncome * 0.50;
        let monthlyRoi = (parseFloat(app.sanction_roi || app.interest_rate || 8.5) / 100) / 12;
        let tenureMonths = parseInt(app.sanction_tenure || app.tenure_months || 180);
        let emiPerLakh = 100000 * monthlyRoi * Math.pow(1 + monthlyRoi, tenureMonths) / (Math.pow(1 + monthlyRoi, tenureMonths) - 1);
        let maxEligibility = emiPerLakh > 0 ? Math.round((maxAllowedEmi / emiPerLakh) * 100000) : (maxAllowedEmi * 100);
        
        widget.innerHTML = `
            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:16px; margin-bottom:16px; box-shadow:0 1px 3px rgba(0,0,0,0.02);">
                <div style="font-weight:700; color:#0f172a; font-size:13px; margin-bottom:12px; display:flex; align-items:center; gap:6px;">
                    <i data-lucide="calculator" style="width:16px; color:var(--primary);"></i> Income Clubbing &amp; Lending Eligibility
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
                    <div style="border-right:1px solid #f1f5f9; padding-right:16px;">
                        <div style="display:flex; justify-content:space-between; font-size:12px; padding:4px 0; border-bottom:1px dashed #e2e8f0;"><span>Applicant Income:</span> <strong>₹${new Intl.NumberFormat('en-IN').format(appIncome)}</strong></div>
                        ${coAppHtml}
                        <div style="display:flex; justify-content:space-between; font-size:13px; padding:6px 0; margin-top:4px; font-weight:700; color:#0f172a;"><span>Combined Income:</span> <strong>₹${new Intl.NumberFormat('en-IN').format(totalIncome)}</strong></div>
                        <div style="display:flex; justify-content:space-between; font-size:12px; padding:4px 0; color:#64748b;"><span>Current Serviced EMIs:</span> <span>₹${new Intl.NumberFormat('en-IN').format(totalEmi)}</span></div>
                    </div>
                    <div>
                        <div style="margin-bottom:12px;">
                            <div style="font-size:11px; color:#64748b; text-transform:uppercase; font-weight:700;">Calculated FOIR (Obligations / Income)</div>
                            <div style="font-size:20px; font-weight:800; color:${foirColor};">${foir}%</div>
                            <div style="font-size:11px; color:#94a3b8; margin-top:2px;">Bank Standard Benchmark &lt; 50-55%</div>
                        </div>
                        <div>
                            <div style="font-size:11px; color:#64748b; text-transform:uppercase; font-weight:700;">Combined Max Loan Eligibility</div>
                            <div style="font-size:16px; font-weight:800; color:#15803d;">₹${new Intl.NumberFormat('en-IN').format(maxEligibility)}</div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        if(window.lucide) lucide.createIcons({root: widget});
    }

    function switchDetailTab(tabName) {
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        
        document.getElementById('tab-btn-' + tabName).classList.add('active');
        document.getElementById('tab-content-' + tabName).classList.add('active');
    }

    async function deleteApplicant(id) {
        if (!confirm('Are you sure you want to completely delete this application? This action cannot be undone.')) return;
        
        try {
            const formData = new FormData();
            formData.append('id', id);
            
            const res = await fetch('api.php?api=delete_applicant', {
                method: 'POST',
                body: formData
            });
            
            const text = await res.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.error("Failed to parse JSON. Server returned:", text);
                alert("Server Error. Please check console for details.\n\n" + text.substring(0, 100));
                return;
            }

            if (data.success) {
                alert('Application deleted successfully.');
                location.reload();
            } else {
                alert(data.error || 'Failed to delete application.');
            }
        } catch (err) {
            console.error(err);
            alert('A network error occurred: ' + err.message);
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        triggerSearch();
        
        const urlParams = new URLSearchParams(window.location.search);
        const viewId = urlParams.get('id') || urlParams.get('app_id');
        if (viewId) {
            setTimeout(() => {
                selectApplicantCard(viewId);
            }, 600);
        }
    });

    // BANK QUERY TRACKER FUNCTIONS
    async function loadBankQueries(applicantId) {
        try {
            const res = await fetch(`api.php?api=get_bank_queries&applicant_id=${applicantId}`);
            if (!res.ok) return;
            const data = await res.json();
            const container = document.getElementById('bank-queries-list');
            if (!container) return;

            const queries = data.data || data.queries || [];
            if (!data.success || queries.length === 0) {
                container.innerHTML = '<div style="font-size:12px; color:#64748b; padding:8px 0;"><i data-lucide="check-circle" style="width:13px; color:#16a34a; vertical-align:-2px;"></i> No active bank queries. Application is clear.</div>';
                if (window.lucide) lucide.createIcons();
                return;
            }

            let html = '';
            queries.forEach(q => {
                let badgeColor = q.status === 'Open' ? 'background:#fee2e2; color:#dc2626; border:1px solid #fecaca;' :
                                 (q.status === 'In Progress' ? 'background:#fef3c7; color:#d97706; border:1px solid #fde68a;' :
                                 'background:#dcfce7; color:#16a34a; border:1px solid #bbf7d0;');
                const qDesc = q.description || q.query_description || '-';
                const qDate = q.date_raised || q.raised_date || '-';
                
                html += `
                <div style="border:1px solid #e2e8f0; border-radius:8px; padding:10px; margin-bottom:8px; background:#ffffff;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:6px;">
                        <div style="font-weight:700; font-size:12px; color:#0f172a;">${q.bank_name} - <span style="font-weight:600; font-size:11px; color:#475569;">${q.query_type}</span></div>
                        <span style="font-size:10px; padding:2px 6px; border-radius:10px; font-weight:700; ${badgeColor}">${q.status}</span>
                    </div>
                    <div style="font-size:11.5px; color:#334155; margin-bottom:6px;">${qDesc}</div>
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <div style="font-size:10px; color:#64748b;">Raised: ${qDate} ${q.document_required ? `• Doc Req: <strong>${q.document_required}</strong>` : ''}</div>
                        ${(q.status === 'Open' || q.status === 'In Progress') ? `<button onclick="resolveQuery(${q.id}, ${applicantId})" style="background:#fff; border:1px solid #cbd5e1; border-radius:4px; padding:2px 8px; font-size:10px; font-weight:600; cursor:pointer;">Resolve</button>` : `<span style="font-size:10px; color:#16a34a; font-weight:600;">Resolved on ${q.resolved_date || ''}</span>`}
                    </div>
                </div>`;
            });
            container.innerHTML = html;
            if (window.lucide) lucide.createIcons();
        } catch (err) {
            console.error(err);
        }
    }

    function openQueryModal(applicantId) {
        const defaultBank = (window.currentLoadedApplicant && (window.currentLoadedApplicant.sanction_bank || window.currentLoadedApplicant.disbursing_bank)) ? (window.currentLoadedApplicant.sanction_bank || window.currentLoadedApplicant.disbursing_bank) : '';
        Swal.fire({
            title: 'Raise Bank Query',
            html: `
                <form id="query-form" style="text-align:left;">
                    <div style="margin-bottom:10px;">
                        <label style="display:block; font-size:12px; font-weight:700; margin-bottom:4px;">Bank Name *</label>
                        <input type="text" id="swal-bank-name" class="swal2-input" style="width:100%; margin:0; height:36px; font-size:13px;" value="${defaultBank}" placeholder="e.g. HDFC Bank, ICICI Bank" required>
                    </div>
                    <div style="margin-bottom:10px;">
                        <label style="display:block; font-size:12px; font-weight:700; margin-bottom:4px;">Query Type *</label>
                        <select id="swal-query-type" class="swal2-select" style="width:100%; margin:0; height:36px; font-size:13px; padding:0 12px; border: 1px solid #d9d9d9; border-radius: 4px;" required>
                            <option value="Document">Document</option>
                            <option value="Income">Income</option>
                            <option value="Property">Property</option>
                            <option value="Legal">Legal</option>
                            <option value="Technical">Technical</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div style="margin-bottom:10px;">
                        <label style="display:block; font-size:12px; font-weight:700; margin-bottom:4px;">Description *</label>
                        <textarea id="swal-description" class="swal2-textarea" style="width:100%; margin:0; font-size:13px; padding:8px;" rows="3" placeholder="Describe the query raised by bank..." required></textarea>
                    </div>
                    <div style="margin-bottom:10px;">
                        <label style="display:block; font-size:12px; font-weight:700; margin-bottom:4px;">Date Raised *</label>
                        <input type="date" id="swal-date-raised" class="swal2-input" style="width:100%; margin:0; height:36px; font-size:13px;" value="${new Date().toISOString().split('T')[0]}" required>
                    </div>
                    <div style="margin-bottom:10px;">
                        <label style="display:block; font-size:12px; font-weight:700; margin-bottom:4px;">Document Required (Optional)</label>
                        <input type="text" id="swal-doc-required" class="swal2-input" style="width:100%; margin:0; height:36px; font-size:13px;" placeholder="e.g. 6-Month Bank Statement, Latest Form 16">
                    </div>
                </form>
            `,
            showCancelButton: true,
            confirmButtonText: 'Submit Query',
            confirmButtonColor: '#0f172a',
            preConfirm: () => {
                const bankName = document.getElementById('swal-bank-name').value;
                const queryType = document.getElementById('swal-query-type').value;
                const description = document.getElementById('swal-description').value;
                const dateRaised = document.getElementById('swal-date-raised').value;
                const docRequired = document.getElementById('swal-doc-required').value;
                
                if (!bankName || !queryType || !description || !dateRaised) {
                    Swal.showValidationMessage('Please fill all required fields');
                    return false;
                }
                
                return { applicant_id: applicantId, bank_name: bankName, query_type: queryType, description: description, date_raised: dateRaised, document_required: docRequired };
            }
        }).then(async (result) => {
            if (result.isConfirmed) {
                try {
                    const formData = new FormData();
                    for(let key in result.value) {
                        formData.append(key, result.value[key]);
                    }
                    
                    const res = await fetch('api.php?api=save_bank_query', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await res.json();
                    
                    if (data.success) {
                        Swal.fire({icon: 'success', title: 'Query Raised', toast: true, position: 'top-end', showConfirmButton: false, timer: 3000});
                        loadBankQueries(applicantId);
                    } else {
                        Swal.fire('Error', data.error || 'Failed to save query', 'error');
                    }
                } catch(err) {
                    Swal.fire('Error', 'Network error', 'error');
                }
            }
        });
    }

    function resolveQuery(queryId, applicantId) {
        Swal.fire({
            title: 'Resolve Query',
            input: 'textarea',
            inputLabel: 'Resolution Notes',
            inputPlaceholder: 'How was this query resolved?',
            inputAttributes: {
                'aria-label': 'Resolution Notes',
                required: 'true'
            },
            showCancelButton: true,
            confirmButtonText: 'Resolve',
            confirmButtonColor: '#16a34a',
            inputValidator: (value) => {
                if (!value) return 'You need to write something!'
            }
        }).then(async (result) => {
            if (result.isConfirmed) {
                try {
                    const formData = new FormData();
                    formData.append('id', queryId);
                    formData.append('query_id', queryId);
                    formData.append('resolution_notes', result.value);
                    
                    const res = await fetch('api.php?api=resolve_bank_query', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await res.json();
                    
                    if (data.success) {
                        Swal.fire({icon: 'success', title: 'Query Resolved', toast: true, position: 'top-end', showConfirmButton: false, timer: 3000});
                        loadBankQueries(applicantId);
                    } else {
                        Swal.fire('Error', data.error || 'Failed to resolve query', 'error');
                    }
                } catch(err) {
                    Swal.fire('Error', 'Network error', 'error');
                }
            }
        });
    }

    // WHATSAPP QUICK MESSAGING FUNCTIONS
    function loadWhatsAppTemplates(applicantId, mobile) {
        const container = document.getElementById('wa-templates-grid');
        if (!container) return;
        
        // These templates could come from API, but we'll define standard ones here if api fails
        const standardTemplates = [
            { name: 'Welcome', icon: 'hand', message: 'Hello! Welcome to BFS Circle. We are processing your loan application and will keep you updated.' },
            { name: 'Document Pending', icon: 'file-warning', message: 'Hi, a few documents are pending for your loan application. Please provide them at the earliest to proceed.' },
            { name: 'Bank Login', icon: 'landmark', message: 'Good news! Your loan application has been successfully logged into the bank system.' },
            { name: 'Query Alert', icon: 'alert-circle', message: 'Hi, the bank has raised a query regarding your application. We need your quick response to resolve it.' },
            { name: 'Sanction', icon: 'party-popper', message: 'Congratulations! Your loan has been officially sanctioned. We will contact you for the next steps.' },
            { name: 'Disbursement', icon: 'banknote', message: 'Great news! Your loan amount has been disbursed. Thank you for choosing BFS Circle.' }
        ];

        // Try to load from API if implemented, else use standards
        fetch(`api.php?api=get_whatsapp_templates&applicant_id=${applicantId}`)
            .then(res => res.json())
            .then(data => {
                let templates = (data.success && data.data && data.data.length > 0) ? data.data : standardTemplates;
                renderWaTemplates(templates, mobile, applicantId);
            })
            .catch(() => {
                renderWaTemplates(standardTemplates, mobile, applicantId);
            });
    }

    function renderWaTemplates(templates, mobile, applicantId) {
        const container = document.getElementById('wa-templates-grid');
        let html = '';
        templates.forEach((t, idx) => {
            html += `
            <div onclick="handleWaTemplateClick(${idx}, '${mobile}', ${applicantId})" style="border:1px solid #e2e8f0; border-radius:8px; padding:8px 10px; cursor:pointer; font-size:11px; font-weight:600; color:#0f172a; display:flex; align-items:center; gap:6px; transition:all 0.15s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='#fff'">
                <i data-lucide="${t.icon || 'message-circle'}" style="width:12px;height:12px;color:#25D366;"></i> ${t.name}
            </div>`;
        });
        window.currentWaTemplates = templates;
        container.innerHTML = html;
        if(window.lucide) lucide.createIcons();
    }

    function handleWaTemplateClick(idx, mobile, applicantId) {
        const t = (window.currentWaTemplates || [])[idx];
        if (t) sendWhatsApp(t.message, mobile, applicantId);
    }

    function sendWhatsApp(message, mobile, applicantId) {
        if (!mobile || mobile.trim() === '') {
            Swal.fire('No Mobile Number', 'Applicant does not have a registered mobile number.', 'warning');
            return;
        }

        Swal.fire({
            title: 'Send WhatsApp Message',
            input: 'textarea',
            inputValue: message,
            inputAttributes: {
                style: 'height:100px; font-size:13px;'
            },
            showCancelButton: true,
            confirmButtonText: '<i class="fab fa-whatsapp"></i> Send on WhatsApp',
            confirmButtonColor: '#25D366'
        }).then((result) => {
            if (result.isConfirmed) {
                const finalMsg = result.value;
                const cleanMobile = mobile.replace(/[^0-9]/g, '');
                const waUrl = 'https://wa.me/91' + cleanMobile + '?text=' + encodeURIComponent(finalMsg);
                
                // Open WhatsApp
                window.open(waUrl, '_blank');
                
                // Log interaction
                const formData = new FormData();
                formData.append('applicant_id', applicantId);
                formData.append('interaction_type', 'WhatsApp');
                formData.append('outcome', 'Message Sent');
                formData.append('notes', finalMsg);
                
                fetch('api.php?api=log_rm_interaction', {
                    method: 'POST',
                    body: formData
                }).catch(console.error);
            }
        });
    }
</script>


<!-- Detailed Email Modal for Bank Dispatch -->
<div id="email-modal" onclick="closeBankDispatchModalOutside(event)" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); z-index:9999; align-items:center; justify-content:center; backdrop-filter: blur(4px);">
    <div id="email-modal-content" style="background:#fff; width:700px; min-width:500px; min-height:400px; border-radius:12px; box-shadow:0 10px 25px rgba(0,0,0,0.2); overflow:hidden; display:flex; flex-direction:column; max-height: 90vh; max-width: 95vw; resize:both;">
        
        <!-- Header -->
        <div style="background:var(--primary); padding:16px 24px; color:#fff; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:16px; display:flex; align-items:center; gap:8px;">
                <i data-lucide="send" style="color:#fff; width:20px; height:20px;"></i> Dispatch File to Banker
            </h3>
            <button type="button" onclick="closeBankDispatchModal()" style="background:none; border:none; color:#fff; cursor:pointer; padding:4px;"><i data-lucide="x" style="width:20px; height:20px;"></i></button>
        </div>

        <div style="padding:24px; overflow-y:auto; flex:1;">
            <!-- Attachment Preview Box -->
            <div style="background:#f8fafc; border:1px dashed #cbd5e1; border-radius:8px; padding:16px; margin-bottom:24px; display:flex; align-items:center; gap:16px;">
                <div style="background:#e2e8f0; width:48px; height:48px; border-radius:8px; display:flex; align-items:center; justify-content:center; color:#475569;">
                    <i data-lucide="file-archive" style="width:24px; height:24px;"></i>
                </div>
                <div>
                    <div id="modal_attachment_name" style="font-weight:600; color:#1e293b; font-size:14px;">Bundle.zip</div>
                    <div id="modal_attachment_desc" style="font-size:12px; color:#64748b; margin-top:4px;">Auto-generated ZIP containing Applicant Profile Summary + 0 Uploaded Documents</div>
                </div>
                <div style="margin-left:auto;">
                    <span class="badge badge-success" style="font-size:11px;">Ready to Attach</span>
                </div>
            </div>

            <form id="email-banker-form" onsubmit="sendBankDispatchEmail(event)">
                <input type="hidden" name="applicant_id" id="modal_applicant_id">
                
                <div class="form-grid" style="grid-template-columns: 1fr 1fr; gap:16px;">
                    <div class="form-group">
                        <label>Bank Name</label>
                        <select name="bank_name" id="modal_bank_name" onchange="updateBankDispatchSubjectAndBody()" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:6px;">
                            <option value="">-- Select Bank --</option>
                            <?php foreach($banks as $b): ?>
                                <option value="<?php echo htmlspecialchars($b); ?>"><?php echo htmlspecialchars($b); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Banker's Name <span style="font-size:11px; color:#64748b;">(Optional)</span></label>
                        <input type="text" name="banker_name" id="modal_banker_name" onkeyup="updateBankDispatchSubjectAndBody()" placeholder="e.g. Mr. Sharma" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:6px;">
                    </div>

                    <div class="form-group">
                        <label class="required">To (Banker's Email)</label>
                        <input type="email" name="banker_email" required placeholder="banker@bank.com" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:6px;">
                    </div>
                    <div class="form-group">
                        <label>CC <span style="font-size:11px; color:#64748b;">(Optional)</span></label>
                        <input type="email" name="cc_email" placeholder="manager@bank.com" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:6px;">
                    </div>
                    
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label class="required">Subject</label>
                        <input type="text" name="subject" id="modal_subject" required style="width:100%; padding:10px; border:1px solid var(--border); border-radius:6px; font-weight:600;">
                    </div>
                    
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label class="required">Email Body</label>
                        <textarea name="body" id="modal_body" rows="7" required style="width:100%; padding:12px; border:1px solid var(--border); border-radius:6px; font-family:inherit; line-height:1.5;"></textarea>
                    </div>
                </div>
            </form>
        </div>

        <!-- Footer -->
        <div style="background:#f8fafc; padding:16px 24px; border-top:1px solid var(--border); display:flex; justify-content:flex-end; gap:12px;">
            <button type="button" class="btn btn-secondary" onclick="closeBankDispatchModal()" style="padding:10px 20px;">Cancel</button>
            <button type="submit" form="email-banker-form" class="btn btn-primary" id="send-email-btn" style="padding:10px 24px; font-weight:600;"><i data-lucide="send" style="width:16px;height:16px;"></i> Dispatch Email</button>
        </div>
    </div>
</div>

<script src="https://cdn.ckeditor.com/4.22.1/standard/ckeditor.js"></script>
<script>
    let currentApp = {};

    function openBankDispatchModal(id, cName, lId, lType, amt, docsCount) {
        currentApp = { id, cName, lId, lType, amt, docsCount };
        
        document.getElementById('modal_applicant_id').value = id;
        
        const safeName = cName.replace(/[^A-Za-z0-9_-]/g, '_');
        document.getElementById('modal_attachment_name').innerText = `Bundle_${lId}_${safeName}.zip`;
        document.getElementById('modal_attachment_desc').innerText = `Auto-generated ZIP containing Applicant Profile Summary + ${docsCount} Uploaded Documents`;
        
        if(!CKEDITOR.instances.modal_body) {
            CKEDITOR.replace('modal_body', {
                height: 200,
                versionCheck: false,
                toolbar: [
                    ['Bold', 'Italic', 'Underline', 'Strike'],
                    ['NumberedList', 'BulletedList', '-', 'Outdent', 'Indent'],
                    ['Link', 'Unlink'],
                    ['Format', 'Font', 'FontSize']
                ]
            });
        }
        
        updateBankDispatchSubjectAndBody();
        document.getElementById('email-modal').style.display = 'flex';
        if(window.lucide) lucide.createIcons();
            const realBtn = document.getElementById('real-load-more-btn');
            if (realBtn) realBtn.innerText = '↓ Load More Records ↓';
    }

    function updateBankDispatchSubjectAndBody() {
        const bank = document.getElementById('modal_bank_name').value;
        const bName = document.getElementById('modal_banker_name').value;
        const subjEl = document.getElementById('modal_subject');
        
        let subj = `New ${currentApp.lType} Application - ${currentApp.cName} [ID: ${currentApp.lId}]`;
        if (bank) subj += ` for ${bank}`;
        subjEl.value = subj;

        let salutation = bName ? `Dear ${bName},` : `Dear Sir/Madam,`;
        
        const amtFormat = new Intl.NumberFormat('en-IN').format(currentApp.amt);
        
        const newBody = `${salutation}<br><br>

Please find attached the bundled ZIP file containing the complete KYC and property documents for the loan application of <strong>${currentApp.cName}</strong>.<br><br>

<strong>Applicant Name:</strong> ${currentApp.cName}<br>
<strong>Loan Type:</strong> ${currentApp.lType}<br>
<strong>Requested Amount:</strong> INR ${amtFormat}<br>
<strong>Total Documents Attached:</strong> ${currentApp.docsCount}<br><br>

Kindly review the file and let us know the sanction details.<br><br>

Regards,<br>
BFS Financial Services Sourcing Team`;

        if (CKEDITOR.instances.modal_body) {
            CKEDITOR.instances.modal_body.setData(newBody);
        } else {
            document.getElementById('modal_body').value = newBody.replace(/<br>/g, "\n").replace(/<[^>]+>/g, '');
        }
    }

    function closeBankDispatchModal() {
        document.getElementById('email-modal').style.display = 'none';
    }

    function closeBankDispatchModalOutside(event) {
        if (event.target.id === 'email-modal') {
            closeBankDispatchModal();
        }
    }
    
    async function sendBankDispatchEmail(e) {
        e.preventDefault();
        if (CKEDITOR.instances.modal_body) {
            CKEDITOR.instances.modal_body.updateElement();
        }
        const form = document.getElementById('email-banker-form');
        const formData = new FormData(form);
        const btn = document.getElementById('send-email-btn');
        
        btn.disabled = true;
        btn.innerHTML = 'Generating ZIP & Sending...';
        
        try {
            const res = await fetch('api.php?api=email_banker_zip', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            
            if (res.ok && data.success) {
                showNotification(data.message, 'success');
                closeBankDispatchModal();
                form.reset();
            } else {
                showNotification(data.error || 'Failed to send email', 'error');
            }
        } catch(err) {
            showNotification('Network error', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = 'Dispatch Email';
        }
    }

    // --- QUICK DOCUMENT UPLOAD & VAULT ATTACH IN SEARCH & CRM TRACK ---
    function openQuickDocUpload(applicantId, category, docName, ownerType, ownerId) {
        document.getElementById('quick_applicant_id').value = applicantId;
        if (category) document.getElementById('quick_doc_category').value = category;
        if (docName) document.getElementById('quick_doc_name').value = docName;
        
        const ownerVal = (ownerType === 'CoApplicant' && ownerId > 0) ? `CoApplicant_${ownerId}` : 'Applicant_0';
        document.getElementById('quick_owner_val').value = ownerVal;

        const title = document.getElementById('quick-modal-title');
        if (title) title.innerText = docName ? `Upload: ${docName}` : 'Quick Document Upload';

        document.getElementById('quick-doc-upload-modal').style.display = 'flex';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function closeQuickDocUpload() {
        document.getElementById('quick-doc-upload-modal').style.display = 'none';
        document.getElementById('quick-doc-upload-form').reset();
    }

    async function submitQuickDocUpload(e) {
        e.preventDefault();
        const form = document.getElementById('quick-doc-upload-form');
        const formData = new FormData(form);
        const btn = document.getElementById('quick-upload-submit-btn');
        const appId = document.getElementById('quick_applicant_id').value;

        btn.disabled = true;
        btn.innerHTML = 'Uploading...';

        try {
            const res = await fetch('api.php?api=upload_applicant_document', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data && data.success) {
                showNotification(data.message, 'success');
                closeQuickDocUpload();
                if (appId) loadApplicantDetails(appId);
            } else {
                showNotification(data.error || 'Upload failed', 'error');
            }
        } catch(err) {
            showNotification('Network error during upload', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i data-lucide="upload" style="width:14px;"></i> Upload & Save';
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
    }

    async function attachTrackVaultDoc(applicantId, sourceDocId) {
        if (!confirm("Attach this document from Client's Permanent Vault to this loan application?")) return;
        const formData = new FormData();
        formData.append('applicant_id', applicantId);
        formData.append('source_doc_id', sourceDocId);

        try {
            const res = await fetch('api.php?api=attach_vault_document', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data && data.success) {
                showNotification(data.message, 'success');
                if (applicantId) loadApplicantDetails(applicantId);
            } else {
                showNotification(data.error || 'Failed to attach from vault', 'error');
            }
        } catch (e) {
            showNotification('Connection error while attaching from vault', 'error');
        }
    }

    function openTrackPreviewModal(filePath, docName) {
        const modal = document.getElementById('track-doc-preview-modal');
        const title = document.getElementById('track-preview-title');
        const body = document.getElementById('track-preview-body');
        const downloadBtn = document.getElementById('track-preview-download-btn');

        if (!modal || !body) return;

        title.innerText = decodeURIComponent(docName || 'Document Preview');
        downloadBtn.href = filePath;
        
        const ext = filePath.split('.').pop().toLowerCase();
        if (ext === 'pdf') {
            body.innerHTML = `<iframe src="${filePath}" style="width:100%; height:100%; border:none; border-radius:8px;"></iframe>`;
        } else {
            body.innerHTML = `<img src="${filePath}" style="max-width:100%; max-height:100%; object-fit:contain; border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,0.1);">`;
        }

        modal.style.display = 'flex';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function closeTrackPreviewModal() {
        const modal = document.getElementById('track-doc-preview-modal');
        if (modal) modal.style.display = 'none';
    }

    // --- LOAN SANCTION & CUSTOMER LIFECYCLE EMAIL HUB ---
    function openRecordSanctionModal(appId, isEdit) {
        const app = window.currentLoadedApplicant;
        if (!app || app.id != appId) return;

        document.getElementById('sanction_applicant_id').value = app.id;
        document.getElementById('sanction_app_title').innerText = `${app.customer_name} [${app.loan_id}]`;
        document.getElementById('sanction_bank_input').value = app.sanction_bank || '';
        document.getElementById('sanction_amount_input').value = app.sanctioned_amount || app.loan_amount_requested || '';
        document.getElementById('sanction_date_input').value = app.sanction_date || new Date().toISOString().split('T')[0];
        document.getElementById('sanction_roi_input').value = app.sanction_roi || '';
        document.getElementById('sanction_tenure_input').value = app.sanction_tenure || '';
        document.getElementById('sanction_emi_input').value = app.sanction_emi || '';
        document.getElementById('sanction_ref_input').value = app.sanction_ref_no || '';
        document.getElementById('sanction_notes_input').value = app.sanction_notes || '';
        
        const letterBox = document.getElementById('sanction_existing_letter_box');
        if (app.sanction_letter_path) {
            letterBox.style.display = 'block';
            letterBox.innerHTML = `<span style="color:#16a34a; font-weight:600;"><i data-lucide="check-circle" style="width:14px; vertical-align:-2px;"></i> Attached Letter:</span> <a href="${app.sanction_letter_path}" target="_blank" style="color:var(--primary); text-decoration:underline; font-weight:600;">View Existing Document</a>`;
        } else {
            letterBox.style.display = 'none';
        }

        document.getElementById('record-sanction-modal').style.display = 'flex';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function closeRecordSanctionModal() {
        document.getElementById('record-sanction-modal').style.display = 'none';
    }

    async function submitRecordSanction(e) {
        e.preventDefault();
        const form = document.getElementById('record-sanction-form');
        const formData = new FormData(form);
        const btn = document.getElementById('record-sanction-submit-btn');
        const appId = document.getElementById('sanction_applicant_id').value;

        btn.disabled = true;
        btn.innerHTML = 'Saving Sanction...';

        try {
            const res = await fetch('api.php?api=save_applicant_sanction', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data && data.success) {
                showNotification(data.message, 'success');
                closeRecordSanctionModal();
                if (appId) selectApplicantCard(appId);
                triggerSearch(false);
            } else {
                showNotification(data.error || 'Failed to save sanction details', 'error');
            }
        } catch (err) {
            showNotification('Network error while saving sanction', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i data-lucide="check" style="width:14px;"></i> Save Bank Sanction';
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
    }

    function openCustomerEmailHub(appId, defaultStage) {
        const app = window.currentLoadedApplicant;
        if (!app || app.id != appId) return;

        document.getElementById('email_hub_applicant_id').value = app.id;
        document.getElementById('email_hub_client_name').innerText = app.customer_name;
        
        const emailInput = document.getElementById('email_hub_recipient_email');
        if (emailInput) {
            emailInput.value = app.email || '';
        }

        const noEmailWarn = document.getElementById('email_hub_no_email_warning');
        const submitBtn = document.getElementById('email_hub_submit_btn');
        
        if (!app.email) {
            if (noEmailWarn) noEmailWarn.style.display = 'block';
        } else {
            if (noEmailWarn) noEmailWarn.style.display = 'none';
        }
        if (submitBtn) submitBtn.disabled = false;

        // Determine default stage if not passed
        let stage = defaultStage;
        if (!stage) {
            if (app.overall_status === 'Completed') stage = 'Disbursed';
            else if (app.sanction_status === 'Sanctioned' || app.overall_status === 'Phase 4') stage = 'Sanction';
            else if (app.phase2_completion === 100 || app.overall_status === 'Phase 3') stage = 'Verification';
            else if (app.overall_status === 'Phase 2') stage = 'Pendency';
            else stage = 'Registration';
        }

        document.getElementById('email_hub_stage_select').value = stage;
        onEmailHubStageChange();

        document.getElementById('customer-email-hub-modal').style.display = 'flex';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function closeCustomerEmailHub() {
        document.getElementById('customer-email-hub-modal').style.display = 'none';
        document.getElementById('customer-email-hub-form').reset();
    }

    async function generateAiEmailContent(stageType = 'Pendency', targetAreaId = 'email_hub_custom_note') {
        const targetEl = document.getElementById(targetAreaId);
        if (!targetEl) return;

        const btn = event ? event.currentTarget : null;
        const oldHtml = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = `<i data-lucide="sparkles" style="width:12px; height:12px;" class="spin"></i> AI Writing Email...`;
        }

        const app = window.currentLoadedApplicant || {};
        const appId = app.id || (document.getElementById('email_hub_applicant_id') ? document.getElementById('email_hub_applicant_id').value : 0);

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: '✨ AI Writing Email...',
                text: 'Drafting formal, executive email content for your client',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });
        }

        try {
            const formData = new FormData();
            formData.append('applicant_id', appId);
            formData.append('stage_type', stageType);

            const res = await fetch('api.php?api=generate_ai_email', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (typeof Swal !== 'undefined') Swal.close();

            if (data && data.success) {
                targetEl.value = data.body || data.content || '';
                const subjInput = document.getElementById('email_hub_custom_subject');
                if (subjInput && data.subject) {
                    subjInput.value = data.subject;
                }
                if (typeof Swal !== 'undefined') {
                    Swal.fire('✨ AI Email Written!', 'The email content has been auto-generated. Review and click Dispatch.', 'success');
                }
            } else {
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Error', data.error || 'Failed to generate AI email.', 'error');
                }
            }
        } catch (e) {
            if (typeof Swal !== 'undefined') {
                Swal.fire('Error', 'Connection error while writing email with AI.', 'error');
            }
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = oldHtml;
                if (typeof lucide !== 'undefined') lucide.createIcons();
            }
        }
    }

    function onEmailHubStageChange() {
        const stage = document.getElementById('email_hub_stage_select').value;
        const app = window.currentLoadedApplicant || {};
        const infoBox = document.getElementById('email_hub_stage_info');
        const noteHint = document.getElementById('email_hub_note_hint');
        const noteArea = document.getElementById('email_hub_custom_note');

        let desc = '';
        let hint = '';
        let placeholder = '';

        if (stage === 'Registration') {
            desc = `<strong>Stage 1 (Application Registration):</strong> Sends official Loan Application Confirmation with Loan ID <strong>${app.loan_id || ''}</strong>, requested amount, and roadmap steps.`;
            hint = 'Add special welcome remarks or assigned Relationship Manager contact:';
            placeholder = 'e.g. Your dedicated loan manager is Mr. Verma (9876543210). He will coordinate for doorstep document collection.';
        } else if (stage === 'Form_Confirmation') {
            desc = `<strong>Application Form PDF & Details Confirmation:</strong> Dispatches official filled loan application form summary (KYC, PAN, Aadhaar, Income, Co-Applicants) with a direct link to view/print the Application Form PDF.`;
            hint = 'Add custom confirmation note for the customer:';
            placeholder = 'e.g. Please review the filled application details attached below. If any correction is required, please reply to this email.';
        } else if (stage === 'Pendency') {
            desc = `<strong>Stage 2 (Document Status & Smart Pendency):</strong> Auto-generates clean checklist of <em>Received & Verified documents</em> vs <em>Pending / Missing documents</em> based on ${app.loan_type || 'Loan'} requirements.`;
            hint = 'Write specific pendency instructions or timeline for the customer:';
            placeholder = 'e.g. Please send the last 6 months bank statement in PDF format with password by tomorrow evening.';
        } else if (stage === 'Login_Fee') {
            desc = `<strong>Bank Login Fee Receipt & File Intake:</strong> Dispatches official payment receipt for Bank Login Fee (Amount: <strong>₹${app.login_fee_amount || '0'}</strong>, Bank: <strong>${app.login_fee_bank || app.sanction_bank || 'Bank'}</strong>, Mode: <strong>${app.login_fee_payment_mode || 'UPI'}</strong>, UTR: <strong>${app.login_fee_ref_no || '-'}</strong>) confirming their file is officially logged in with the bank.`;
            hint = 'Add payment confirmation remarks or expected bank processing turnaround time:';
            placeholder = 'e.g. Your login fee payment is received and processed. Banker file login reference is active.';
        } else if (stage === 'Verification') {
            desc = `<strong>Stage 3 (Documents Complete & Verification):</strong> Congratulates customer that 100% documents are received and file is forwarded to Bank Credit/Operations for final appraisal.`;
            hint = 'Add bank processing notes:';
            placeholder = 'e.g. Bank credit verification team may visit your residence tomorrow for brief inspection.';
        } else if (stage === 'Sanction') {
            desc = `<strong>Stage 4 (Loan Sanction & Approval Notice):</strong> Sends structured Sanction Approval Letter summary (Bank: <strong>${app.sanction_bank || 'Bank'}</strong>, Sanctioned: <strong>₹${app.sanctioned_amount || ''}</strong>, ROI: <strong>${app.sanction_roi || ''}%</strong>, EMI: <strong>₹${app.sanction_emi || ''}</strong>).`;
            hint = 'Add sanction acceptance or disbursement prerequisites:';
            placeholder = 'e.g. Please arrange 3 security cheques and original property title deed for final disbursement agreement.';
        } else if (stage === 'Disbursed') {
            desc = `<strong>Stage 5 (Loan Disbursal & Celebration):</strong> Sends celebratory notification with Congratulations announcement, disbursed amount, first EMI due date guidance, and thank you note.`;
            hint = 'Add welcome to family & repayment instructions:';
            placeholder = 'e.g. First EMI will be debited on 5th of next month via NACH. Thank you for choosing BFS!';
        }

        infoBox.innerHTML = desc;
        noteHint.innerText = hint;
        noteArea.placeholder = placeholder;
    }

    async function submitCustomerEmailHub(e) {
        e.preventDefault();
        const form = document.getElementById('customer-email-hub-form');
        const formData = new FormData(form);
        const btn = document.getElementById('email_hub_submit_btn');
        const appId = document.getElementById('email_hub_applicant_id').value;

        btn.disabled = true;
        btn.innerHTML = 'Sending Email...';

        try {
            const res = await fetch('api.php?api=send_customer_stage_email', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data && data.success) {
                showNotification(data.message, 'success');
                closeCustomerEmailHub();
                if (appId) selectApplicantCard(appId);
            } else {
                showNotification(data.error || 'Failed to send customer email', 'error');
            }
        } catch (err) {
            showNotification('Network error while dispatching email', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i data-lucide="send" style="width:14px;"></i> Dispatch Email to Customer';
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
    }

    // ====== BANK LOGIN FEE HANDLERS ======
    function openRecordLoginFeeModal(appId) {
        const app = window.currentLoadedApplicant;
        if (!app || app.id != appId) return;

        document.getElementById('login_fee_applicant_id').value = app.id;
        document.getElementById('login_fee_app_title').innerText = `${app.customer_name} (${app.loan_id}) - ${app.loan_type}`;
        document.getElementById('login_fee_amount_input').value = app.login_fee_amount || '';
        document.getElementById('login_fee_bank_input').value = app.login_fee_bank || app.sanction_bank || '';
        document.getElementById('login_fee_mode_select').value = app.login_fee_payment_mode || 'UPI / QR Code';
        document.getElementById('login_fee_paid_date_input').value = app.login_fee_paid_date || new Date().toISOString().split('T')[0];
        document.getElementById('login_fee_ref_input').value = app.login_fee_ref_no || '';
        document.getElementById('login_fee_notes_input').value = app.login_fee_notes || '';

        const receiptBox = document.getElementById('login_fee_existing_receipt_box');
        if (app.login_fee_receipt_path) {
            receiptBox.style.display = 'block';
            receiptBox.innerHTML = `<span style="color:#0d9488; font-weight:600;"><i data-lucide="check-circle" style="width:14px; vertical-align:-2px;"></i> Attached Receipt:</span> <a href="${app.login_fee_receipt_path}" target="_blank" style="color:var(--primary); text-decoration:underline; font-weight:600;">View Existing Receipt</a>`;
        } else {
            receiptBox.style.display = 'none';
        }

        document.getElementById('record-login-fee-modal').style.display = 'flex';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function closeRecordLoginFeeModal() {
        document.getElementById('record-login-fee-modal').style.display = 'none';
    }

    async function submitRecordLoginFee(e) {
        e.preventDefault();
        const form = document.getElementById('record-login-fee-form');
        const formData = new FormData(form);
        const btn = document.getElementById('record-login-fee-submit-btn');
        const appId = document.getElementById('login_fee_applicant_id').value;

        btn.disabled = true;
        btn.innerHTML = 'Saving Payment...';

        try {
            const res = await fetch('api.php?api=save_login_fee', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data && data.success) {
                showNotification(data.message, 'success');
                closeRecordLoginFeeModal();
                if (appId) selectApplicantCard(appId);
                triggerSearch(false);
            } else {
                showNotification(data.error || 'Failed to save login fee details', 'error');
            }
        } catch (err) {
            showNotification('Network error while saving login fee', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i data-lucide="check" style="width:14px;"></i> Save Login Fee & Issue Confirmation';
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
    }

    // ====== BANK PAYOUT & COMMISSION RECOVERY DESK HANDLERS ======
    function openManageBankPayoutModal(appId) {
        const app = window.currentLoadedApplicant;
        if (!app || app.id != appId) return;

        document.getElementById('bank_payout_applicant_id').value = app.id;
        document.getElementById('bank_payout_app_title').innerText = `${app.customer_name} (${app.loan_id}) • Bank: ${app.sanction_bank || 'Assigned Bank'}`;
        
        const sanctionAmt = parseFloat(app.sanctioned_amount || app.loan_amount_requested || 0);
        const expectedPct = parseFloat(app.bank_payout_expected_pct || 1.25);
        document.getElementById('bank_payout_pct_input').value = expectedPct;
        
        const calcExp = app.bank_payout_expected_amount ? app.bank_payout_expected_amount : Math.round(sanctionAmt * (expectedPct / 100));
        document.getElementById('bank_payout_expected_amt_input').value = calcExp;
        document.getElementById('bank_payout_status_select').value = app.bank_payout_status || 'Pending';
        document.getElementById('bank_payout_invoice_input').value = app.bank_payout_invoice_no || `INV-${new Date().getFullYear()}-${app.loan_id}`;
        document.getElementById('bank_payout_received_amt_input').value = app.bank_payout_received_amount || '';
        document.getElementById('bank_payout_received_date_input').value = app.bank_payout_received_date || '';
        document.getElementById('bank_payout_ref_input').value = app.bank_payout_ref_no || '';
        document.getElementById('bank_payout_notes_input').value = app.bank_payout_notes || '';

        document.getElementById('manage-bank-payout-modal').style.display = 'flex';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function closeManageBankPayoutModal() {
        document.getElementById('manage-bank-payout-modal').style.display = 'none';
    }

    function updateExpectedBankPayout() {
        const app = window.currentLoadedApplicant;
        if (!app) return;
        const sanctionAmt = parseFloat(app.sanctioned_amount || app.loan_amount_requested || 0);
        const pct = parseFloat(document.getElementById('bank_payout_pct_input').value || 0);
        if (sanctionAmt > 0 && pct > 0) {
            document.getElementById('bank_payout_expected_amt_input').value = Math.round(sanctionAmt * (pct / 100));
        }
    }

    async function submitManageBankPayout(e) {
        e.preventDefault();
        const form = document.getElementById('manage-bank-payout-form');
        const formData = new FormData(form);
        const btn = document.getElementById('manage-bank-payout-submit-btn');
        const appId = document.getElementById('bank_payout_applicant_id').value;

        btn.disabled = true;
        btn.innerHTML = 'Updating Payout...';

        try {
            const res = await fetch('api.php?api=save_bank_payout_recovery', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data && data.success) {
                showNotification(data.message, 'success');
                closeManageBankPayoutModal();
                if (appId) selectApplicantCard(appId);
                triggerSearch(false);
            } else {
                showNotification(data.error || 'Failed to save bank payout recovery', 'error');
            }
        } catch (err) {
            showNotification('Network error while saving bank payout', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i data-lucide="check" style="width:14px;"></i> Update Bank Commission Record';
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
    }

    function openBankerPayoutClaimEmailModal(appId) {
        const app = window.currentLoadedApplicant;
        if (!app || app.id != appId) return;

        document.getElementById('banker_claim_applicant_id').value = app.id;
        document.getElementById('banker_claim_app_title').innerText = `${app.customer_name} (${app.loan_id}) • Disbursed: ₹${(app.sanctioned_amount || 0).toLocaleString('en-IN')}`;
        document.getElementById('banker_claim_invoice_no').value = app.bank_payout_invoice_no || `INV-${new Date().getFullYear()}-${app.loan_id}`;
        
        const sanctionAmt = parseFloat(app.sanctioned_amount || app.loan_amount_requested || 0);
        const expectedPct = parseFloat(app.bank_payout_expected_pct || 1.25);
        const claimAmt = app.bank_payout_expected_amount || Math.round(sanctionAmt * (expectedPct / 100));
        document.getElementById('banker_claim_amount_input').value = claimAmt;
        
        document.getElementById('email-banker-payout-claim-modal').style.display = 'flex';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function closeBankerPayoutClaimEmailModal() {
        document.getElementById('email-banker-payout-claim-modal').style.display = 'none';
    }

    async function submitBankerPayoutClaimEmail(e) {
        e.preventDefault();
        const form = document.getElementById('email-banker-payout-claim-form');
        const formData = new FormData(form);
        const btn = document.getElementById('banker-claim-submit-btn');
        const appId = document.getElementById('banker_claim_applicant_id').value;

        btn.disabled = true;
        btn.innerHTML = 'Sending Claim Email...';

        try {
            const res = await fetch('api.php?api=email_banker_payout_claim', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data && data.success) {
                showNotification(data.message, 'success');
                closeBankerPayoutClaimEmailModal();
                if (appId) selectApplicantCard(appId);
            } else {
                showNotification(data.error || 'Failed to send banker payout claim email', 'error');
            }
        } catch (err) {
            showNotification('Network error while dispatching banker claim email', 'error');
        } finally {
            btn.disabled = false;
        }
    }

    // ====== RELATIONSHIP MANAGER (RM) TRANSFER & ALLOCATION ======
    function openTransferRmModal(appId, currentRm, custName, loanId) {
        document.getElementById('transfer_rm_applicant_id').value = appId;
        document.getElementById('transfer_rm_app_title').innerText = `${decodeURIComponent(custName)} (${loanId})`;
        document.getElementById('transfer_rm_select').value = currentRm || '';
        document.getElementById('transfer_rm_notes').value = '';
        document.getElementById('transfer-rm-modal').style.display = 'flex';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function closeTransferRmModal() {
        document.getElementById('transfer-rm-modal').style.display = 'none';
    }

    async function submitTransferRm(e) {
        e.preventDefault();
        const form = document.getElementById('transfer-rm-form');
        const formData = new FormData(form);
        const btn = document.getElementById('transfer_rm_submit_btn');
        const appId = document.getElementById('transfer_rm_applicant_id').value;

        btn.disabled = true;
        btn.innerHTML = 'Assigning RM...';

        try {
            const res = await fetch('api.php?api=assign_applicant_rm', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data && data.success) {
                showNotification(data.message, 'success');
                closeTransferRmModal();
                if (appId) selectApplicantCard(appId);
                triggerSearch(false);
            } else {
                showNotification(data.error || 'Failed to re-assign RM', 'error');
            }
        } catch (err) {
            showNotification('Network error while assigning RM', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i data-lucide="user-check" style="width:14px;"></i> Update RM Allocation';
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
    }

    // ====== RM INTERACTION & CALL LOGGER (SURVEILLANCE) ======
    function openLogInteractionModal(appId, custName) {
        const app = window.currentLoadedApplicant || {};
        const name = decodeURIComponent(custName || app.customer_name || 'Customer');
        const mobile = app.mobile || '';
        
        document.getElementById('log_interaction_applicant_id').value = appId;
        document.getElementById('log_interaction_app_title').innerText = `${name} ${mobile ? ' (' + mobile + ')' : ''}`;
        
        const callBox = document.getElementById('log_interaction_direct_call_box');
        if (callBox) {
            if (mobile) {
                callBox.innerHTML = `<a href="tel:${mobile}" class="btn" style="background:#0f766e; color:#ffffff; padding:5px 12px; font-size:12px; font-weight:700; border-radius:6px; text-decoration:none; display:inline-flex; align-items:center; gap:5px;"><i data-lucide="phone" style="width:13px; height:13px;"></i> Call Now</a>`;
            } else {
                callBox.innerHTML = '';
            }
        }
        
        document.getElementById('log_interaction_type').value = 'Phone Call';
        document.getElementById('log_interaction_outcome').value = 'Interested & Agreed';
        document.getElementById('log_interaction_notes').value = '';
        
        // Default next follow-up to tomorrow 11:00 AM
        const tmrw = new Date();
        tmrw.setDate(tmrw.getDate() + 1);
        tmrw.setHours(11, 0, 0, 0);
        const pad = (n) => n.toString().padStart(2, '0');
        const formattedDate = `${tmrw.getFullYear()}-${pad(tmrw.getMonth()+1)}-${pad(tmrw.getDate())}T${pad(tmrw.getHours())}:${pad(tmrw.getMinutes())}`;
        document.getElementById('log_interaction_followup').value = formattedDate;
        
        document.getElementById('log-interaction-modal').style.display = 'flex';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function closeLogInteractionModal() {
        document.getElementById('log-interaction-modal').style.display = 'none';
    }

    async function submitLogInteraction(e) {
        e.preventDefault();
        const form = document.getElementById('log-interaction-form');
        const formData = new FormData(form);
        const btn = document.getElementById('log_interaction_submit_btn');
        const appId = document.getElementById('log_interaction_applicant_id').value;

        btn.disabled = true;
        btn.innerHTML = 'Logging Interaction...';

        try {
            const res = await fetch('api.php?api=log_rm_interaction', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data && data.success) {
                showNotification(data.message, 'success');
                closeLogInteractionModal();
                if (appId) selectApplicantCard(appId);
            } else {
                showNotification(data.error || 'Failed to log customer interaction', 'error');
            }
        } catch (err) {
            showNotification('Network error while logging interaction', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i data-lucide="check" style="width:14px;"></i> Save Call & Log Record';
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
    }

    // ====== 1-CLICK WHATSAPP DESK ======
    let activeWhatsAppContext = {};

    function openWhatsAppQuickMenu(mobile, custName, loanId, loanType, stage) {
        let cleanMobile = mobile ? mobile.replace(/\D/g, '') : '';
        if (cleanMobile.startsWith('91') && cleanMobile.length === 12) cleanMobile = cleanMobile.substring(2);
        if (cleanMobile.startsWith('0') && cleanMobile.length === 11) cleanMobile = cleanMobile.substring(1);

        activeWhatsAppContext = {
            mobile: cleanMobile,
            name: decodeURIComponent(custName || 'Valued Customer'),
            loanId: loanId || '',
            loanType: loanType || 'Loan',
            stage: stage || 'Phase 1'
        };

        if (!activeWhatsAppContext.mobile || activeWhatsAppContext.mobile.length < 10) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: '📱 Enter Customer Mobile Number',
                    text: `Mobile number missing for ${activeWhatsAppContext.name}. Enter 10-digit number to proceed with WhatsApp:`,
                    input: 'text',
                    inputPlaceholder: '9876543210',
                    showCancelButton: true,
                    confirmButtonText: 'Continue to WhatsApp',
                    confirmButtonColor: '#16a34a'
                }).then(res => {
                    if (res.isConfirmed && res.value) {
                        activeWhatsAppContext.mobile = res.value.replace(/\D/g, '');
                        openWhatsAppQuickMenu(activeWhatsAppContext.mobile, custName, loanId, loanType, stage);
                    }
                });
            } else {
                showNotification('Customer mobile number is missing or invalid', 'error');
            }
            return;
        }

        document.getElementById('wa_customer_title').innerText = `${activeWhatsAppContext.name} (+91 ${activeWhatsAppContext.mobile})`;
        loadWhatsAppTemplate('welcome');
        document.getElementById('whatsapp-templates-modal').style.display = 'flex';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function closeWhatsAppQuickMenu() {
        document.getElementById('whatsapp-templates-modal').style.display = 'none';
    }

    function loadWhatsAppTemplate(templateKey) {
        const c = activeWhatsAppContext;
        let text = '';

        if (templateKey === 'welcome') {
            text = `Namaste ${c.name} ji,\n\nMain aapka Dedicated Relationship Manager baat kar raha hoon BFS Loan Services se.\n\nAapka ${c.loanType} application (Ref ID: *${c.loanId}*) hamare system me active process me hai.\n\nKisi bhi jaankari ya file status ke liye aap mujhe is number par direct call ya WhatsApp kar sakte hain.\n\nDhanyawad,\nTeam BFS`;
        } else if (templateKey === 'pendency') {
            text = `Namaste ${c.name} ji,\n\nAapke *${c.loanType}* (ID: *${c.loanId}*) file ko bank me process karne ke liye kuch zaroori documents required hain:\n\n1. Latest 6 Months Bank Statement (PDF format)\n2. Latest 3 Months Salary Slips / ITR with Computation\n3. KYC (Aadhaar & PAN Card)\n4. Property / Business Proof\n\nKripya ye documents jaldi se jaldi share karein taaki hum aapka loan approval fast process kara sakein.\n\nDhanyawad,\nBFS Loan Team`;
        } else if (templateKey === 'login_fee') {
            text = `Namaste ${c.name} ji,\n\nAapki loan application (ID: *${c.loanId}*) documents complete ho chuke hain aur file officially Bank Login ke liye ready hai.\n\nBank processing & verification start karne ke liye official Bank Login Fee pay karni hai.\n\nPayment UPI / QR Code ya Official Bank Account me transfer karke receipt share karein taaki banker verification turant start ho sake.\n\nDhanyawad,\nBFS Team`;
        } else if (templateKey === 'sanction') {
            text = `🎉 *CONGRATULATIONS ${c.name.toUpperCase()} JI!* 🎉\n\nHamein batate hue khushi ho rahi hai ki aapka *${c.loanType}* (ID: *${c.loanId}*) Bank dwara successfully *SANCTION / APPROVE* ho chuka hai!\n\nDisbursement aur final agreement signing ke liye hamari team aapse coordinate kar rahi hai.\n\nDhanyawad,\nBFS Financial Services`;
        } else if (templateKey === 'custom') {
            text = `Namaste ${c.name} ji,\n\nRegarding your ${c.loanType} application (*${c.loanId}*):\n\n[Type your custom message here]\n\nRegards,\nBFS Team`;
        }

        document.getElementById('wa_message_content').value = text;
    }

    async function sendQuickWhatsApp() {
        const c = activeWhatsAppContext;
        const msg = document.getElementById('wa_message_content').value.trim();
        if (!msg) {
            showNotification('Please enter a WhatsApp message', 'error');
            return;
        }

        let phone = c.mobile ? c.mobile.replace(/\D/g, '') : '';
        if (phone.length === 10) phone = '91' + phone;
        if (phone.startsWith('0') && phone.length === 11) phone = '91' + phone.substring(1);

        if (!phone || phone.length < 10) {
            showNotification('Invalid target phone number for WhatsApp', 'error');
            return;
        }

        // Auto log interaction in background for admin audit
        if (window.currentLoadedApplicant && window.currentLoadedApplicant.id) {
            const formData = new FormData();
            formData.append('applicant_id', window.currentLoadedApplicant.id);
            formData.append('interaction_type', 'WhatsApp Chat');
            formData.append('outcome', 'Interested & Agreed');
            formData.append('notes', 'WhatsApp template dispatched: ' + msg.substring(0, 100) + '...');
            fetch('api.php?api=log_rm_interaction', { method: 'POST', body: formData }).catch(console.error);
        }

        const waUrl = `https://api.whatsapp.com/send?phone=${phone}&text=${encodeURIComponent(msg)}`;
        window.open(waUrl, '_blank');
        if (typeof showNotification === 'function') showNotification('WhatsApp dispatched & interaction logged!', 'success');
        closeWhatsAppQuickMenu();
    }
</script>

<!-- Quick In-Page Document Upload Modal -->
<div id="quick-doc-upload-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.8); z-index:9999; justify-content:center; align-items:center; backdrop-filter:blur(4px);">
    <div style="background:#fff; border-radius:12px; width:90%; max-width:550px; overflow:hidden; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);">
        <div style="padding:16px 20px; background:#0f172a; color:#fff; display:flex; justify-content:space-between; align-items:center;">
            <div style="display:flex; align-items:center; gap:8px;">
                <i data-lucide="upload-cloud" style="width:18px;"></i>
                <span style="font-weight:700; font-size:15px;" id="quick-modal-title">Quick Document Upload</span>
            </div>
            <button onclick="closeQuickDocUpload()" style="background:none; border:none; color:#fff; cursor:pointer; font-size:20px; line-height:1;">&times;</button>
        </div>
        <form id="quick-doc-upload-form" onsubmit="submitQuickDocUpload(event)" style="padding:20px;">
            <input type="hidden" name="applicant_id" id="quick_applicant_id" value="">
            <input type="hidden" name="owner_val" id="quick_owner_val" value="Applicant_0">
            
            <div class="form-group" style="margin-bottom:14px;">
                <label class="required" style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Document Category</label>
                <select name="document_category" id="quick_doc_category" required style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px;">
                    <option value="Basic KYC">Basic KYC (PAN, Aadhaar, Photo, Address)</option>
                    <option value="Income Proof">Income Proof (Salary, ITR, Bank Stmt)</option>
                    <option value="Property / Asset Docs">Property / Asset Docs (Registry, Map, Naksha)</option>
                    <option value="Vehicle Docs">Vehicle Docs (RC, Quotation, Insurance)</option>
                    <option value="Business Proof">Business Proof (GST, MSME, Trade License)</option>
                    <option value="Co-Applicant Docs">Co-Applicant Docs</option>
                    <option value="Other">Other / Additional</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom:14px;">
                <label class="required" style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Document Name</label>
                <input type="text" name="document_name" id="quick_doc_name" placeholder="e.g. Last 3 Months Salary Slips" required style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; box-sizing:border-box;">
            </div>

            <div class="form-group" style="margin-bottom:20px;">
                <label class="required" style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Select File (PDF / JPG / PNG)</label>
                <input type="file" name="document_file" id="quick_file_input" accept="image/*, .pdf" required style="width:100%; padding:8px; border:1px dashed #94a3b8; border-radius:6px; background:#f8fafc; font-size:12.5px;">
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="closeQuickDocUpload()" class="btn btn-secondary" style="padding:8px 16px;">Cancel</button>
                <button type="submit" id="quick-upload-submit-btn" class="btn btn-primary" style="padding:8px 20px; font-weight:700;"><i data-lucide="upload" style="width:14px;"></i> Upload & Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Instant In-Page Document Preview Modal -->
<div id="track-doc-preview-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.85); z-index:9999; justify-content:center; align-items:center; backdrop-filter:blur(4px);">
    <div style="background:#fff; border-radius:12px; width:90%; max-width:900px; height:85vh; display:flex; flex-direction:column; overflow:hidden; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);">
        <div style="padding:14px 20px; background:#0f172a; color:#fff; display:flex; justify-content:space-between; align-items:center;">
            <div style="display:flex; align-items:center; gap:10px;">
                <i data-lucide="file-text" style="width:18px;"></i>
                <span id="track-preview-title" style="font-weight:700; font-size:15px;">Document Preview</span>
            </div>
            <div style="display:flex; gap:10px; align-items:center;">
                <a id="track-preview-download-btn" href="#" download class="btn btn-secondary" style="padding:4px 10px; font-size:12px; background:rgba(255,255,255,0.15); color:#fff; border:none;" target="_blank"><i data-lucide="download" style="width:14px;"></i> Download</a>
                <button onclick="closeTrackPreviewModal()" style="background:none; border:none; color:#fff; cursor:pointer; font-size:20px; line-height:1;">&times;</button>
            </div>
        </div>
        <div id="track-preview-body" style="flex:1; background:#f8fafc; display:flex; justify-content:center; align-items:center; overflow:auto; padding:10px;">
            <!-- Rendered iframe or img -->
        </div>
    </div>
</div>

<!-- Record Bank Sanction Modal -->
<div id="record-sanction-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.8); z-index:9999; justify-content:center; align-items:center; backdrop-filter:blur(4px);">
    <div style="background:#fff; border-radius:12px; width:90%; max-width:680px; max-height:92vh; overflow-y:auto; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);">
        <div style="padding:16px 20px; background:#0f172a; color:#fff; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #1e293b; position:sticky; top:0; z-index:10;">
            <div style="display:flex; align-items:center; gap:8px;">
                <i data-lucide="landmark" style="width:18px; color:#22c55e;"></i>
                <span style="font-weight:700; font-size:15px;">Record Bank Sanction & Terms</span>
            </div>
            <button onclick="closeRecordSanctionModal()" style="background:none; border:none; color:#fff; cursor:pointer; font-size:20px; line-height:1;">&times;</button>
        </div>
        <form id="record-sanction-form" onsubmit="submitRecordSanction(event)" enctype="multipart/form-data" style="padding:20px;">
            <input type="hidden" name="applicant_id" id="sanction_applicant_id" value="">
            
            <div style="background:#f8fafc; border:1px solid #e2e8f0; padding:10px 14px; border-radius:8px; margin-bottom:16px;">
                <div style="font-size:11px; color:#64748b; font-weight:700; text-transform:uppercase;">Applicant Target</div>
                <div id="sanction_app_title" style="font-size:14px; font-weight:700; color:#0f172a; margin-top:2px;">-</div>
            </div>

            <div class="form-grid" style="grid-template-columns: 1fr 1fr; gap:14px; margin-bottom:14px;">
                <div class="form-group">
                    <label class="required" style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Sanctioning Bank / NBFC</label>
                    <input list="sanction_bank_list" name="sanction_bank" id="sanction_bank_input" required placeholder="e.g. HDFC Bank / SBI" style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; box-sizing:border-box;">
                    <datalist id="sanction_bank_list">
                        <?php foreach($banks as $b): ?>
                            <option value="<?php echo htmlspecialchars($b); ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <div class="form-group">
                    <label class="required" style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Sanctioned Amount (₹)</label>
                    <input type="number" step="0.01" name="sanctioned_amount" id="sanction_amount_input" required placeholder="e.g. 2500000" style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; font-weight:700; color:#15803d; box-sizing:border-box;">
                </div>
            </div>

            <div class="form-grid" style="grid-template-columns: 1fr 1fr 1fr; gap:14px; margin-bottom:14px;">
                <div class="form-group">
                    <label class="required" style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Sanction Date</label>
                    <input type="date" name="sanction_date" id="sanction_date_input" required style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; box-sizing:border-box;">
                </div>

                <div class="form-group">
                    <label style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Interest Rate (ROI %)</label>
                    <input type="text" name="sanction_roi" id="sanction_roi_input" placeholder="e.g. 8.75%" style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; box-sizing:border-box;">
                </div>

                <div class="form-group">
                    <label style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Loan Tenure</label>
                    <input type="text" name="sanction_tenure" id="sanction_tenure_input" placeholder="e.g. 240 Months" style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; box-sizing:border-box;">
                </div>
            </div>

            <div class="form-grid" style="grid-template-columns: 1fr 1fr; gap:14px; margin-bottom:14px;">
                <div class="form-group">
                    <label style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Monthly EMI (₹)</label>
                    <input type="number" step="0.01" name="sanction_emi" id="sanction_emi_input" placeholder="e.g. 22500" style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; box-sizing:border-box;">
                </div>

                <div class="form-group">
                    <label style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Sanction Ref / Letter No.</label>
                    <input type="text" name="sanction_ref_no" id="sanction_ref_input" placeholder="e.g. HDFC/2026/LN-883" style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; box-sizing:border-box;">
                </div>
            </div>

            <div class="form-group" style="margin-bottom:14px;">
                <label style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Upload Official Bank Sanction Letter (PDF / JPG)</label>
                <input type="file" name="sanction_letter_file" id="sanction_file_input" accept="image/*, .pdf" style="width:100%; padding:8px; border:1px dashed #94a3b8; border-radius:6px; background:#f8fafc; font-size:12px;">
                <div id="sanction_existing_letter_box" style="margin-top:6px; font-size:12px; display:none;"></div>
            </div>

            <div class="form-group" style="margin-bottom:16px;">
                <label style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Sanction Conditions / Internal Notes</label>
                <textarea name="sanction_notes" id="sanction_notes_input" rows="2" placeholder="e.g. Subject to original registry deposit & legal vetting." style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:12.5px; box-sizing:border-box;"></textarea>
            </div>

            <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:12px; margin-bottom:20px;">
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-weight:700; font-size:13px; color:#1e40af; margin-bottom:6px;">
                    <input type="checkbox" name="send_email" id="sanction_send_email_chk" value="1" checked style="width:16px; height:16px; accent-color:#2563eb;">
                    Dispatch Official Sanction Approval Email to Customer Now
                </label>
                <input type="text" name="email_custom_note" placeholder="Optional custom note to include in email (e.g. Disbursement scheduled for Monday)" style="width:100%; padding:7px 10px; border:1px solid #93c5fd; border-radius:6px; font-size:12px; background:#ffffff; box-sizing:border-box;">
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="closeRecordSanctionModal()" class="btn btn-secondary" style="padding:8px 16px;">Cancel</button>
                <button type="submit" id="record-sanction-submit-btn" class="btn btn-primary" style="padding:8px 20px; font-weight:700; background:#16a34a; border:none;"><i data-lucide="check" style="width:14px;"></i> Save Bank Sanction</button>
            </div>
        </form>
    </div>
</div>

<!-- Customer Lifecycle Email & Notification Hub Modal -->
<div id="customer-email-hub-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.8); z-index:9999; justify-content:center; align-items:center; backdrop-filter:blur(4px);">
    <div style="background:#fff; border-radius:12px; width:90%; max-width:680px; max-height:92vh; overflow-y:auto; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);">
        <div style="padding:16px 20px; background:#0f172a; color:#fff; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #1e293b; position:sticky; top:0; z-index:10;">
            <div style="display:flex; align-items:center; gap:8px;">
                <i data-lucide="mail" style="width:18px; color:#38bdf8;"></i>
                <span style="font-weight:700; font-size:15px;">Customer Lifecycle Communications Hub</span>
            </div>
            <button onclick="closeCustomerEmailHub()" style="background:none; border:none; color:#fff; cursor:pointer; font-size:20px; line-height:1;">&times;</button>
        </div>
        <form id="customer-email-hub-form" onsubmit="submitCustomerEmailHub(event)" style="padding:20px;">
            <input type="hidden" name="applicant_id" id="email_hub_applicant_id" value="">

            <div style="background:#f8fafc; border:1px solid #cbd5e1; padding:12px 14px; border-radius:8px; margin-bottom:16px; display:grid; grid-template-columns: 1fr 1fr; gap:14px; align-items:center;">
                <div>
                    <div style="font-size:11px; color:#64748b; font-weight:700; text-transform:uppercase;">Recipient Customer</div>
                    <div id="email_hub_client_name" style="font-size:14px; font-weight:700; color:#0f172a; margin-top:2px;">-</div>
                </div>
                <div>
                    <label style="font-size:11px; color:#64748b; font-weight:700; text-transform:uppercase; display:block; margin-bottom:2px;">Recipient Email Address <span style="color:#ef4444;">*</span></label>
                    <input type="email" name="recipient_email" id="email_hub_recipient_email" required placeholder="client@example.com" style="width:100%; padding:7px 10px; border:1.5px solid #cbd5e1; border-radius:6px; font-size:13px; font-weight:600; color:#0f172a; background:#ffffff;">
                </div>
            </div>

            <div id="email_hub_no_email_warning" style="display:none; background:#eff6ff; border:1px solid #bfdbfe; color:#1e40af; padding:10px 14px; border-radius:8px; font-size:12.5px; margin-bottom:16px;">
                <i data-lucide="info" style="width:14px; vertical-align:-2px;"></i> <strong>Note:</strong> Customer email was not configured in Phase 1 profile. Please enter the target recipient email above to dispatch communication.
            </div>

            <div class="form-group" style="margin-bottom:16px;">
                <label class="required" style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Select Milestone / Notification Stage</label>
                <select name="stage_type" id="email_hub_stage_select" onchange="onEmailHubStageChange()" required style="width:100%; padding:10px 12px; border:1.5px solid #cbd5e1; border-radius:6px; font-size:13.5px; font-weight:700; color:#0f172a; background:#f8fafc;">
                    <option value="Registration">Stage 1: Application Registered & Welcome Confirmation</option>
                    <option value="Form_Confirmation">Stage 1.5: Application Form PDF & Filled Profile Details Confirmation</option>
                    <option value="Pendency">Stage 2: Document Status & Smart Pendency (Received vs Missing Docs)</option>
                    <option value="Login_Fee">Stage 2.5: Bank Login Fee Payment Receipt & Banker File Intake</option>
                    <option value="Verification">Stage 3: Documents 100% Complete & Forwarded for Bank Processing</option>
                    <option value="Sanction">Stage 4: Bank Sanction & Loan Approval Notice</option>
                    <option value="Disbursed">Stage 5: Loan Disbursed & Congratulations Celebration</option>
                </select>
            </div>

            <div id="email_hub_stage_info" style="background:#f1f5f9; border-left:3px solid var(--primary); padding:10px 14px; border-radius:0 6px 6px 0; font-size:12.5px; color:#334155; line-height:1.5; margin-bottom:16px;">
                <!-- Auto-updated description of stage -->
            </div>

            <div style="background:#f0f9ff; border:1px solid #bae6fd; border-radius:8px; padding:10px 14px; margin-bottom:16px;">
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-weight:700; font-size:12.5px; color:#0369a1; margin:0;">
                    <input type="checkbox" name="include_form_pdf" id="email_hub_include_form_pdf" value="1" checked style="width:16px; height:16px; accent-color:#0284c7;">
                    📄 Attach Official Application Form PDF Link & Detailed Filled Profile Summary
                </label>
            </div>

            <div class="form-group" style="margin-bottom:14px;">
                <label style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Custom Email Subject <span style="font-size:11px; color:#64748b;">(Leave blank for automated professional subject)</span></label>
                <input type="text" name="custom_subject" id="email_hub_custom_subject" placeholder="Default automated subject will be used..." style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; box-sizing:border-box;">
            </div>

            <div class="form-group" style="margin-bottom:20px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                    <label id="email_hub_note_hint" style="font-size:12px; font-weight:700; color:#334155; margin:0;">Custom Remarks / Instructions to Customer</label>
                    <button type="button" onclick="generateAiEmailContent(document.getElementById('email_hub_stage_select').value, 'email_hub_custom_note')" style="background:linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color:#fbbf24; border:1px solid #f59e0b; border-radius:12px; padding:3px 10px; font-size:11px; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:4px; box-shadow:0 2px 6px rgba(0,0,0,0.15);" title="Auto-write email content using AI">
                        <i data-lucide="sparkles" style="width:12px; height:12px; color:#f59e0b;"></i> ✨ AI Write Email
                    </button>
                </div>
                <textarea name="custom_note" id="email_hub_custom_note" rows="3" style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; box-sizing:border-box; line-height:1.4;"></textarea>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:center;">
                <span style="font-size:11.5px; color:#64748b;"><i data-lucide="shield-check" style="width:13px; vertical-align:-2px;"></i> BFS Branded Responsive HTML Template</span>
                <div style="display:flex; gap:10px;">
                    <button type="button" onclick="closeCustomerEmailHub()" class="btn btn-secondary" style="padding:8px 16px;">Cancel</button>
                    <button type="submit" id="email_hub_submit_btn" class="btn btn-primary" style="padding:8px 20px; font-weight:700; background:#0f172a; border:none;"><i data-lucide="send" style="width:14px;"></i> Dispatch Email to Customer</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Record Bank Login Fee Modal -->
<div id="record-login-fee-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.8); z-index:9999; justify-content:center; align-items:center; backdrop-filter:blur(4px);">
    <div style="background:#fff; border-radius:12px; width:90%; max-width:640px; max-height:92vh; overflow-y:auto; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);">
        <div style="padding:16px 20px; background:#0d9488; color:#fff; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #0f766e; position:sticky; top:0; z-index:10;">
            <div style="display:flex; align-items:center; gap:8px;">
                <i data-lucide="receipt" style="width:18px; color:#ccfbf1;"></i>
                <span style="font-weight:700; font-size:15px;">Record Bank Login Fee Payment</span>
            </div>
            <button onclick="closeRecordLoginFeeModal()" style="background:none; border:none; color:#fff; cursor:pointer; font-size:20px; line-height:1;">&times;</button>
        </div>
        <form id="record-login-fee-form" onsubmit="submitRecordLoginFee(event)" enctype="multipart/form-data" style="padding:20px;">
            <input type="hidden" name="applicant_id" id="login_fee_applicant_id" value="">
            
            <div style="background:#f0fdfa; border:1px solid #ccfbf1; padding:10px 14px; border-radius:8px; margin-bottom:16px;">
                <div style="font-size:11px; color:#0f766e; font-weight:700; text-transform:uppercase;">Applicant Target</div>
                <div id="login_fee_app_title" style="font-size:14px; font-weight:700; color:#0f172a; margin-top:2px;">-</div>
            </div>

            <div class="form-grid" style="grid-template-columns: 1fr 1fr; gap:14px; margin-bottom:14px;">
                <div class="form-group">
                    <label class="required" style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Login Fee Amount (₹)</label>
                    <input type="number" step="0.01" name="login_fee_amount" id="login_fee_amount_input" required placeholder="e.g. 2500" style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13.5px; font-weight:700; color:#0f766e; box-sizing:border-box;">
                </div>

                <div class="form-group">
                    <label class="required" style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Bank Target</label>
                    <input list="login_fee_bank_list" name="login_fee_bank" id="login_fee_bank_input" required placeholder="e.g. HDFC Bank / SBI" style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; box-sizing:border-box;">
                    <datalist id="login_fee_bank_list">
                        <?php foreach($banks as $b): ?>
                            <option value="<?php echo htmlspecialchars($b); ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
            </div>

            <div class="form-grid" style="grid-template-columns: 1fr 1fr; gap:14px; margin-bottom:14px;">
                <div class="form-group">
                    <label class="required" style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Payment Mode</label>
                    <select name="login_fee_payment_mode" id="login_fee_mode_select" required style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; box-sizing:border-box;">
                        <option value="UPI / QR Code">UPI / QR Code (GPay, PhonePe, Paytm)</option>
                        <option value="NetBanking / NEFT / RTGS">NetBanking / NEFT / RTGS</option>
                        <option value="Cheque / DD">Cheque / Demand Draft</option>
                        <option value="Cash / POS">Cash / POS Swipe</option>
                        <option value="Bank Direct Debit">Bank Direct Debit</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="required" style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Payment Date</label>
                    <input type="date" name="login_fee_paid_date" id="login_fee_paid_date_input" required style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; box-sizing:border-box;">
                </div>
            </div>

            <div class="form-group" style="margin-bottom:14px;">
                <label style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Bank Reference / UTR Number</label>
                <input type="text" name="login_fee_ref_no" id="login_fee_ref_input" placeholder="e.g. UTR-49382019482 or Chq #000412" style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; box-sizing:border-box;">
            </div>

            <div class="form-group" style="margin-bottom:14px;">
                <label style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Upload Bank Payment Receipt / Slip (PDF / Image)</label>
                <input type="file" name="login_fee_receipt_file" id="login_fee_file_input" accept="image/*, .pdf" style="width:100%; padding:8px; border:1px dashed #94a3b8; border-radius:6px; background:#f8fafc; font-size:12px;">
                <div id="login_fee_existing_receipt_box" style="margin-top:6px; font-size:12px; display:none;"></div>
            </div>

            <div class="form-group" style="margin-bottom:16px;">
                <label style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Login Fee Remarks / Bank Counter Notes</label>
                <textarea name="login_fee_notes" id="login_fee_notes_input" rows="2" placeholder="e.g. Paid directly to HDFC Bank processing account for login." style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:12.5px; box-sizing:border-box;"></textarea>
            </div>

            <div style="background:#f0fdfa; border:1px solid #99f6e4; border-radius:8px; padding:12px; margin-bottom:20px;">
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-weight:700; font-size:13px; color:#0f766e; margin-bottom:6px;">
                    <input type="checkbox" name="send_email" id="login_fee_send_email_chk" value="1" checked style="width:16px; height:16px; accent-color:#0d9488;">
                    Dispatch Official Login Fee Payment Receipt & Banker File Intake Confirmation to Customer
                </label>
                <input type="text" name="email_custom_note" placeholder="Optional custom note in receipt email (e.g. Bank login reference number generated)" style="width:100%; padding:7px 10px; border:1px solid #5eead4; border-radius:6px; font-size:12px; background:#ffffff; box-sizing:border-box;">
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="closeRecordLoginFeeModal()" class="btn btn-secondary" style="padding:8px 16px;">Cancel</button>
                <button type="submit" id="record-login-fee-submit-btn" class="btn btn-primary" style="padding:8px 20px; font-weight:700; background:#0d9488; border:none;"><i data-lucide="check" style="width:14px;"></i> Save Login Fee & Issue Confirmation</button>
            </div>
        </form>
    </div>
</div>

<!-- Manage Banker Commission / Payout Recovery Desk Modal -->
<div id="manage-bank-payout-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.8); z-index:9999; justify-content:center; align-items:center; backdrop-filter:blur(4px);">
    <div style="background:#fff; border-radius:12px; width:90%; max-width:680px; max-height:92vh; overflow-y:auto; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);">
        <div style="padding:16px 20px; background:#0f172a; color:#fff; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #1e293b; position:sticky; top:0; z-index:10;">
            <div style="display:flex; align-items:center; gap:8px;">
                <i data-lucide="wallet" style="width:18px; color:#38bdf8;"></i>
                <span style="font-weight:700; font-size:15px;">Bank Brokerage / Commission Recovery Desk</span>
            </div>
            <button onclick="closeManageBankPayoutModal()" style="background:none; border:none; color:#fff; cursor:pointer; font-size:20px; line-height:1;">&times;</button>
        </div>
        <form id="manage-bank-payout-form" onsubmit="submitManageBankPayout(event)" style="padding:20px;">
            <input type="hidden" name="applicant_id" id="bank_payout_applicant_id" value="">

            <div style="background:#f8fafc; border:1px solid #e2e8f0; padding:10px 14px; border-radius:8px; margin-bottom:16px;">
                <div style="font-size:11px; color:#64748b; font-weight:700; text-transform:uppercase;">Disbursed Application</div>
                <div id="bank_payout_app_title" style="font-size:14px; font-weight:700; color:#0f172a; margin-top:2px;">-</div>
            </div>

            <div class="form-grid" style="grid-template-columns: 1fr 1fr; gap:14px; margin-bottom:14px;">
                <div class="form-group">
                    <label class="required" style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Agreed Bank Commission Rate (%)</label>
                    <input type="number" step="0.01" name="bank_payout_expected_pct" id="bank_payout_pct_input" oninput="updateExpectedBankPayout()" placeholder="e.g. 1.25" style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; box-sizing:border-box;">
                </div>

                <div class="form-group">
                    <label class="required" style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Expected Commission (₹)</label>
                    <input type="number" step="0.01" name="bank_payout_expected_amount" id="bank_payout_expected_amt_input" required placeholder="e.g. 31250" style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13.5px; font-weight:700; color:#0284c7; box-sizing:border-box;">
                </div>
            </div>

            <div class="form-grid" style="grid-template-columns: 1fr 1fr; gap:14px; margin-bottom:14px;">
                <div class="form-group">
                    <label class="required" style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Bank Claim / Settlement Status</label>
                    <select name="bank_payout_status" id="bank_payout_status_select" required style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; box-sizing:border-box;">
                        <option value="Pending">Claim Pending (Not Billed)</option>
                        <option value="Claimed">Claimed / Follow-up with Manager</option>
                        <option value="Invoice Submitted">Official Invoice Submitted</option>
                        <option value="Approved">Claim Approved by Bank</option>
                        <option value="Received">Received in Company Account</option>
                        <option value="Settled">Settled & Closed</option>
                    </select>
                </div>

                <div class="form-group">
                    <label style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">DSA Invoice / Claim Reference No.</label>
                    <input type="text" name="bank_payout_invoice_no" id="bank_payout_invoice_input" placeholder="e.g. INV-2026-BFS-008" style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; box-sizing:border-box;">
                </div>
            </div>

            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px; margin-bottom:14px;">
                <div style="font-size:12px; font-weight:700; color:#16a34a; margin-bottom:10px; display:flex; align-items:center; gap:6px;">
                    <i data-lucide="check-circle" style="width:14px;"></i> Bank Clearance & Settlement Inward (Fill when bank credits payout)
                </div>
                <div class="form-grid" style="grid-template-columns: 1fr 1fr 1fr; gap:12px;">
                    <div class="form-group">
                        <label style="font-size:11px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Received Amount (₹)</label>
                        <input type="number" step="0.01" name="bank_payout_received_amount" id="bank_payout_received_amt_input" placeholder="e.g. 31250" style="width:100%; padding:8px 10px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; font-weight:700; color:#15803d; box-sizing:border-box;">
                    </div>
                    <div class="form-group">
                        <label style="font-size:11px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Received Date</label>
                        <input type="date" name="bank_payout_received_date" id="bank_payout_received_date_input" style="width:100%; padding:8px 10px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; box-sizing:border-box;">
                    </div>
                    <div class="form-group">
                        <label style="font-size:11px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Bank Credit UTR No</label>
                        <input type="text" name="bank_payout_ref_no" id="bank_payout_ref_input" placeholder="e.g. HDFCR5202688" style="width:100%; padding:8px 10px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; box-sizing:border-box;">
                    </div>
                </div>
            </div>

            <div class="form-group" style="margin-bottom:20px;">
                <label style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Banker Recovery Notes & Follow-up History</label>
                <textarea name="bank_payout_notes" id="bank_payout_notes_input" rows="2" placeholder="e.g. Discussed with Branch Manager Mr. Sharma. Clearance expected by 25th." style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:12.5px; box-sizing:border-box;"></textarea>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="closeManageBankPayoutModal()" class="btn btn-secondary" style="padding:8px 16px;">Cancel</button>
                <button type="submit" id="manage-bank-payout-submit-btn" class="btn btn-primary" style="padding:8px 20px; font-weight:700; background:#0f172a; border:none;"><i data-lucide="check" style="width:14px;"></i> Update Bank Commission Record</button>
            </div>
        </form>
    </div>
</div>

<!-- Email Banker Commission Claim Modal -->
<div id="email-banker-payout-claim-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.8); z-index:9999; justify-content:center; align-items:center; backdrop-filter:blur(4px);">
    <div style="background:#fff; border-radius:12px; width:90%; max-width:640px; max-height:92vh; overflow-y:auto; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);">
        <div style="padding:16px 20px; background:#0f172a; color:#fff; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #1e293b; position:sticky; top:0; z-index:10;">
            <div style="display:flex; align-items:center; gap:8px;">
                <i data-lucide="mail" style="width:18px; color:#38bdf8;"></i>
                <span style="font-weight:700; font-size:15px;">Send Formal Payout Claim Invoice to Bank</span>
            </div>
            <button onclick="closeBankerPayoutClaimEmailModal()" style="background:none; border:none; color:#fff; cursor:pointer; font-size:20px; line-height:1;">&times;</button>
        </div>
        <form id="email-banker-payout-claim-form" onsubmit="submitBankerPayoutClaimEmail(event)" style="padding:20px;">
            <input type="hidden" name="applicant_id" id="banker_claim_applicant_id" value="">

            <div style="background:#f8fafc; border:1px solid #e2e8f0; padding:10px 14px; border-radius:8px; margin-bottom:16px;">
                <div style="font-size:11px; color:#64748b; font-weight:700; text-transform:uppercase;">Target Disbursed Loan</div>
                <div id="banker_claim_app_title" style="font-size:14px; font-weight:700; color:#0f172a; margin-top:2px;">-</div>
            </div>

            <div class="form-grid" style="grid-template-columns: 1fr 1fr; gap:14px; margin-bottom:14px;">
                <div class="form-group">
                    <label class="required" style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Banker / Branch Email</label>
                    <input type="email" name="banker_email" id="banker_claim_email_input" required placeholder="manager.branch@bank.com" style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; box-sizing:border-box;">
                </div>

                <div class="form-group">
                    <label style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Banker Contact Name</label>
                    <input type="text" name="banker_name" id="banker_claim_name_input" placeholder="e.g. Mr. Sharma (Branch Manager)" style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; box-sizing:border-box;">
                </div>
            </div>

            <div class="form-grid" style="grid-template-columns: 1fr 1fr; gap:14px; margin-bottom:14px;">
                <div class="form-group">
                    <label class="required" style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Invoice / Claim Number</label>
                    <input type="text" name="invoice_no" id="banker_claim_invoice_no" required style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; box-sizing:border-box;">
                </div>

                <div class="form-group">
                    <label class="required" style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Claim Amount (₹)</label>
                    <input type="number" step="0.01" name="claim_amount" id="banker_claim_amount_input" required style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13.5px; font-weight:700; color:#0284c7; box-sizing:border-box;">
                </div>
            </div>

            <div class="form-group" style="margin-bottom:20px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                    <label style="font-size:12px; font-weight:700; color:#334155; margin:0;">Additional Notes for Banker / Account Details Confirmation</label>
                    <button type="button" onclick="generateAiEmailContent('Banker_Claim', 'banker_claim_notes_input')" style="background:linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color:#fbbf24; border:1px solid #f59e0b; border-radius:12px; padding:3px 10px; font-size:11px; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:4px; box-shadow:0 2px 6px rgba(0,0,0,0.15);" title="Auto-write email content using AI">
                        <i data-lucide="sparkles" style="width:12px; height:12px; color:#f59e0b;"></i> ✨ AI Write Email
                    </button>
                </div>
                <textarea name="custom_remarks" id="banker_claim_notes_input" rows="2" placeholder="e.g. Please release brokerage to BFS Current Account A/C 9876543210 IFSC HDFC0001234." style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:12.5px; box-sizing:border-box;"></textarea>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="closeBankerPayoutClaimEmailModal()" class="btn btn-secondary" style="padding:8px 16px;">Cancel</button>
                <button type="submit" id="banker-claim-submit-btn" class="btn btn-primary" style="padding:8px 20px; font-weight:700; background:#0284c7; border:none;"><i data-lucide="send" style="width:14px;"></i> Dispatch Commission Claim to Bank</button>
            </div>
        </form>
    </div>
</div>

<!-- Assign / Transfer Relationship Manager Modal -->
<div id="transfer-rm-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.8); z-index:9999; justify-content:center; align-items:center; backdrop-filter:blur(4px);">
    <div style="background:#fff; border-radius:12px; width:90%; max-width:520px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); overflow:hidden;">
        <div style="padding:16px 20px; background:#0f172a; color:#fff; display:flex; justify-content:space-between; align-items:center;">
            <div style="display:flex; align-items:center; gap:8px;">
                <i data-lucide="user-check" style="width:18px; color:#38bdf8;"></i>
                <span style="font-weight:700; font-size:15px;">Assign / Transfer Relationship Manager (RM)</span>
            </div>
            <button onclick="closeTransferRmModal()" style="background:none; border:none; color:#fff; cursor:pointer; font-size:20px; line-height:1;">&times;</button>
        </div>
        <form id="transfer-rm-form" onsubmit="submitTransferRm(event)" style="padding:20px;">
            <input type="hidden" name="applicant_id" id="transfer_rm_applicant_id" value="">

            <div style="background:#f8fafc; border:1px solid #e2e8f0; padding:10px 14px; border-radius:8px; margin-bottom:16px;">
                <div style="font-size:11px; color:#64748b; font-weight:700; text-transform:uppercase;">Application Selected</div>
                <div id="transfer_rm_app_title" style="font-size:14px; font-weight:700; color:#0f172a; margin-top:2px;">-</div>
            </div>

            <div class="form-group" style="margin-bottom:14px;">
                <label class="required" style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Select Target Relationship Manager (RM) / Admin</label>
                <select name="assigned_to" id="transfer_rm_select" required style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; box-sizing:border-box;">
                    <option value="">-- Select RM or Admin --</option>
                    <?php foreach ($transfer_targets as $sm): ?>
                        <option value="<?= htmlspecialchars($sm['username']) ?>"><?= htmlspecialchars($sm['full_name'] ?: $sm['username']) ?> (<?= htmlspecialchars($sm['role']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom:14px;">
                <label style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">File Priority</label>
                <select name="rm_priority" id="transfer_rm_priority" style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; box-sizing:border-box;">
                    <option value="Normal">Normal</option>
                    <option value="Medium">Medium Priority</option>
                    <option value="High">High Priority 🔥</option>
                    <option value="Urgent">Urgent / Escalate ⚡</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom:20px;">
                <label style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Handover / Assignment Notes</label>
                <textarea name="transfer_notes" id="transfer_rm_notes" rows="2" placeholder="e.g. Assigned to Mr. Verma for urgent document collection from client office." style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:12.5px; box-sizing:border-box;"></textarea>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="closeTransferRmModal()" class="btn btn-secondary" style="padding:8px 16px;">Cancel</button>
                <button type="submit" id="transfer_rm_submit_btn" class="btn btn-primary" style="padding:8px 20px; font-weight:700; background:#0f172a; border:none;"><i data-lucide="user-check" style="width:14px;"></i> Update RM Allocation</button>
            </div>
        </form>
    </div>
</div>

<!-- Log Customer Interaction / RM Surveillance Call Logger Modal -->
<div id="log-interaction-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.8); z-index:9999; justify-content:center; align-items:center; backdrop-filter:blur(4px);">
    <div style="background:#fff; border-radius:12px; width:90%; max-width:560px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); overflow:hidden;">
        <div style="padding:16px 20px; background:#0f766e; color:#fff; display:flex; justify-content:space-between; align-items:center;">
            <div style="display:flex; align-items:center; gap:8px;">
                <i data-lucide="phone-call" style="width:18px; color:#5eead4;"></i>
                <span style="font-weight:700; font-size:15px;">Log Customer Call / Interaction (Audit Trail)</span>
            </div>
            <button onclick="closeLogInteractionModal()" style="background:none; border:none; color:#fff; cursor:pointer; font-size:20px; line-height:1;">&times;</button>
        </div>
        <form id="log-interaction-form" onsubmit="submitLogInteraction(event)" style="padding:20px;">
            <input type="hidden" name="applicant_id" id="log_interaction_applicant_id" value="">

            <div style="background:#f0fdfa; border:1px solid #99f6e4; padding:10px 14px; border-radius:8px; margin-bottom:16px; display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <div style="font-size:11px; color:#0f766e; font-weight:700; text-transform:uppercase;">Customer Interaction Audit</div>
                    <div id="log_interaction_app_title" style="font-size:14px; font-weight:700; color:#0f172a; margin-top:2px;">-</div>
                </div>
                <div id="log_interaction_direct_call_box"></div>
            </div>

            <div class="form-grid" style="grid-template-columns: 1fr 1fr; gap:14px; margin-bottom:14px;">
                <div class="form-group">
                    <label class="required" style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Interaction Channel</label>
                    <select name="interaction_type" id="log_interaction_type" required style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; box-sizing:border-box;">
                        <option value="Phone Call">📞 Outbound Phone Call</option>
                        <option value="WhatsApp Chat">💬 WhatsApp Discussion</option>
                        <option value="Physical Meeting">🤝 Physical / Doorstep Meeting</option>
                        <option value="Branch Visit">🏦 Customer Branch Visit</option>
                        <option value="Video Call">📹 Video Verification Call</option>
                        <option value="Email Follow-up">✉️ Email Follow-up</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="required" style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Call / Meeting Outcome</label>
                    <select name="outcome" id="log_interaction_outcome" required style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; box-sizing:border-box;">
                        <option value="Interested & Agreed">Interested & Moving Forward</option>
                        <option value="Document Promised">Document Promised by Client</option>
                        <option value="Login Fee Agreed">Login Fee Payment Agreed</option>
                        <option value="Follow-up Required">Follow-up Required</option>
                        <option value="Call Rescheduled / Busy">Client Busy / Rescheduled</option>
                        <option value="Not Reachable / Switch Off">Not Reachable / Switch Off</option>
                        <option value="Sanction Acceptance">Sanction Terms Accepted</option>
                        <option value="Escalated / Query">Escalation / Banker Query</option>
                        <option value="Not Interested">Client Not Interested / Dropped</option>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-bottom:14px;">
                <label class="required" style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Conversation Summary / Detailed Notes</label>
                <textarea name="notes" id="log_interaction_notes" required rows="3" placeholder="e.g. Spoke with customer regarding pending 6 months bank statement. He confirmed sending PDF by today 4:00 PM." style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:12.5px; box-sizing:border-box;"></textarea>
            </div>

            <div class="form-group" style="margin-bottom:20px;">
                <label style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Next Follow-up Date & Time</label>
                <input type="datetime-local" name="next_followup" id="log_interaction_followup" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; box-sizing:border-box;">
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="closeLogInteractionModal()" class="btn btn-secondary" style="padding:8px 16px;">Cancel</button>
                <button type="submit" id="log_interaction_submit_btn" class="btn btn-primary" style="padding:8px 20px; font-weight:700; background:#0f766e; border:none;"><i data-lucide="check" style="width:14px;"></i> Save Call & Audit Log</button>
            </div>
        </form>
    </div>
</div>

<!-- 1-Click WhatsApp Quick Templates Modal -->
<div id="whatsapp-templates-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.8); z-index:9999; justify-content:center; align-items:center; backdrop-filter:blur(4px);">
    <div style="background:#fff; border-radius:12px; width:90%; max-width:620px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); overflow:hidden;">
        <div style="padding:16px 20px; background:#16a34a; color:#fff; display:flex; justify-content:space-between; align-items:center;">
            <div style="display:flex; align-items:center; gap:8px;">
                <i data-lucide="message-circle" style="width:18px;"></i>
                <span style="font-weight:700; font-size:15px;">1-Click WhatsApp Quick Desk</span>
            </div>
            <button onclick="closeWhatsAppQuickMenu()" style="background:none; border:none; color:#fff; cursor:pointer; font-size:20px; line-height:1;">&times;</button>
        </div>
        <div style="padding:20px;">
            <div style="background:#f0fdf4; border:1px solid #bbf7d0; padding:10px 14px; border-radius:8px; margin-bottom:16px;">
                <div style="font-size:11px; color:#16a34a; font-weight:700; text-transform:uppercase;">Recipient Customer</div>
                <div id="wa_customer_title" style="font-size:14px; font-weight:700; color:#0f172a; margin-top:2px;">-</div>
            </div>

            <div style="margin-bottom:12px;">
                <label style="font-size:12px; font-weight:700; color:#334155; margin-bottom:6px; display:block;">Select Ready Template:</label>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <button type="button" onclick="loadWhatsAppTemplate('welcome')" class="btn btn-secondary" style="font-size:11.5px; padding:4px 10px; border-radius:6px; font-weight:600;"><i data-lucide="sparkles" style="width:12px;"></i> RM Welcome</button>
                    <button type="button" onclick="loadWhatsAppTemplate('pendency')" class="btn btn-secondary" style="font-size:11.5px; padding:4px 10px; border-radius:6px; font-weight:600;"><i data-lucide="file-text" style="width:12px;"></i> Missing Docs</button>
                    <button type="button" onclick="loadWhatsAppTemplate('login_fee')" class="btn btn-secondary" style="font-size:11.5px; padding:4px 10px; border-radius:6px; font-weight:600;"><i data-lucide="credit-card" style="width:12px;"></i> Login Fee</button>
                    <button type="button" onclick="loadWhatsAppTemplate('sanction')" class="btn btn-secondary" style="font-size:11.5px; padding:4px 10px; border-radius:6px; font-weight:600;"><i data-lucide="award" style="width:12px;"></i> Sanction Notice</button>
                    <button type="button" onclick="loadWhatsAppTemplate('custom')" class="btn btn-secondary" style="font-size:11.5px; padding:4px 10px; border-radius:6px; font-weight:600;"><i data-lucide="edit-3" style="width:12px;"></i> Custom</button>
                </div>
            </div>

            <div class="form-group" style="margin-bottom:20px;">
                <label style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Message Preview / Edit:</label>
                <textarea id="wa_message_content" rows="7" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; line-height:1.5; box-sizing:border-box; font-family:inherit;"></textarea>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="closeWhatsAppQuickMenu()" class="btn btn-secondary" style="padding:8px 16px;">Cancel</button>
                <button type="button" onclick="sendQuickWhatsApp()" class="btn btn-primary" style="padding:8px 20px; font-weight:700; background:#16a34a; border:none;"><i data-lucide="send" style="width:14px;"></i> Open in WhatsApp</button>
            </div>
        </div>
    </div>
</div>

<script>
// 1. Smart Missing Docs WhatsApp Query Generator
function generateMissingDocsWhatsAppQuery(appId) {
    const app = window.currentLoadedApplicant;
    if (!app || app.id != appId) {
        if (typeof showNotification === 'function') showNotification("Please select applicant file first", "warning");
        return;
    }

    const name = app.customer_name || 'Customer';
    const loanId = app.loan_id || 'N/A';
    const loanType = app.loan_type || 'Loan';
    const mobile = app.mobile || '';

    let receivedDocs = [];
    let pendingDocs = [];

    if (app.phase2_checklist && app.phase2_checklist.length > 0) {
        app.phase2_checklist.forEach(item => {
            if (item.uploaded) {
                receivedDocs.push(item.doc_name);
            } else {
                pendingDocs.push(item.doc_name + (item.is_mandatory ? ' (Mandatory)' : ''));
            }
        });
    }

    let msg = `Dear ${name},\n\nGreetings from BFS Circle Financial Services! 👋\n\nRegarding your ${loanType} Application (Loan ID: ${loanId}):\n\n`;

    if (receivedDocs.length > 0) {
        msg += `✅ Received & Verified Documents:\n`;
        receivedDocs.forEach(d => { msg += `• ${d}\n`; });
        msg += `\n`;
    }

    if (pendingDocs.length > 0) {
        msg += `⚠️ Pending Documents Required for Bank Submission:\n`;
        pendingDocs.forEach((d, idx) => { msg += `${idx + 1}. ${d}\n`; });
        msg += `\n`;
    } else {
        msg += `🎉 Great news! All required documents for your ${loanType} file have been received.\n\n`;
    }

    msg += `Kindly send the pending documents at the earliest so we can proceed with your Bank Submission.\n\nThank you,\nBFS Circle Team`;

    let cleanMobile = mobile ? mobile.replace(/\D/g, '') : '';
    if (cleanMobile.length === 10) cleanMobile = '91' + cleanMobile;
    if (cleanMobile.startsWith('0') && cleanMobile.length === 11) cleanMobile = '91' + cleanMobile.substring(1);

    const waUrl = cleanMobile ? `https://api.whatsapp.com/send?phone=${cleanMobile}&text=` + encodeURIComponent(msg) : '#';

    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: `📋 Missing Documents Query - ${name}`,
            html: `
                <div style="text-align:left; font-size:12.5px; font-family:inherit;">
                    <div style="background:#fff7ed; border:1px solid #ffedd5; padding:10px; border-radius:8px; margin-bottom:10px; color:#c2410c; font-weight:600;">
                        Auto-generated missing documents checklist for ${loanType} (Loan ID: ${loanId})
                    </div>
                    <textarea id="swalMissingDocsMsg" rows="10" style="width:100%; padding:10px; border:1.5px solid #cbd5e1; border-radius:8px; font-size:12.5px; line-height:1.4; box-sizing:border-box;">${msg}</textarea>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: '📲 Send via WhatsApp',
            cancelButtonText: 'Copy Text',
            confirmButtonColor: '#16a34a',
            cancelButtonColor: '#0f172a',
            customClass: { container: 'swal-wide-modal' }
        }).then((result) => {
            if (result.isConfirmed) {
                const updatedMsg = document.getElementById('swalMissingDocsMsg').value;
                if (!cleanMobile) {
                    showNotification("Customer mobile number is missing", "error");
                    return;
                }
                window.open(`https://api.whatsapp.com/send?phone=${cleanMobile}&text=` + encodeURIComponent(updatedMsg), '_blank');
            } else if (result.dismiss === Swal.DismissReason.cancel) {
                const updatedMsg = document.getElementById('swalMissingDocsMsg').value;
                navigator.clipboard.writeText(updatedMsg);
                if (typeof showNotification === 'function') showNotification("Query text copied to clipboard!", "success");
            }
        });
    } else {
        if (cleanMobile) window.open(waUrl, '_blank');
        else showNotification("Customer mobile number is missing", "error");
    }
}

// 2. Applicant AI Copilot Modal Drawer Launcher
function openApplicantAiCopilotModal(appId, nameEnc, loanId, loanType) {
    const name = decodeURIComponent(nameEnc);
    const app = window.currentLoadedApplicant;
    
    // Toggle global drawer
    if (typeof toggleGlobalAiDrawer === 'function') {
        const d = document.getElementById('globalAiDrawer');
        if (d) d.style.display = 'flex';
    }

    const msgContainer = document.getElementById('globalAiMessages');
    if (!msgContainer) return;

    msgContainer.innerHTML = `
        <div style="background:#0f172a; color:#ffffff; padding:14px; border-radius:14px; box-shadow:0 4px 14px rgba(0,0,0,0.15);">
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid rgba(255,255,255,0.15); padding-bottom:8px; margin-bottom:8px;">
                <div style="font-weight:800; font-size:14px; color:#fbbf24; display:flex; align-items:center; gap:6px;">
                    ✨ AI Copilot: ${name}
                </div>
                <span class="badge" style="background:#1e293b; color:#cbd5e1; border:1px solid #475569; font-size:10px;">${loanId}</span>
            </div>
            <div style="font-size:12px; color:#cbd5e1; line-height:1.4;">
                Loan Product: <b>${loanType}</b> • Status: <b>${app ? app.overall_status : 'Active'}</b><br>
                Ask any specific query about this file (e.g. Missing docs, FOIR, Bank eligibility, Pitch script).
            </div>
        </div>
        <div style="display:flex; gap:6px; flex-wrap:wrap; margin-top:4px;">
            <button onclick="generateMissingDocsWhatsAppQuery(${appId})" style="background:#fff7ed; border:1px solid #ffedd5; color:#c2410c; padding:4px 10px; border-radius:20px; font-size:11px; font-weight:700; cursor:pointer;">📋 Missing Docs Query</button>
            <button onclick="document.getElementById('globalAiInput').value='What is the eligibility and FOIR for this file?'; handleGlobalAiSubmit(new Event('submit'))" style="background:#eff6ff; border:1px solid #bfdbfe; color:#1d4ed8; padding:4px 10px; border-radius:20px; font-size:11px; font-weight:700; cursor:pointer;">💰 Check FOIR & Eligibility</button>
            <button onclick="document.getElementById('globalAiInput').value='Analyze credit risk and bank recommendation for ${name}'; handleGlobalAiSubmit(new Event('submit'))" style="background:#f0fdf4; border:1px solid #bbf7d0; color:#15803d; padding:4px 10px; border-radius:20px; font-size:11px; font-weight:700; cursor:pointer;">📊 Bank CAM Analysis</button>
        </div>
    `;
}

// 3. Action Toolbar Dropdown Handlers
function toggleActionDropdown(event, dropdownId) {
    if (event) event.stopPropagation();
    const target = document.getElementById(dropdownId);
    if (!target) return;
    const isVisible = target.style.display === 'block';
    closeAllActionDropdowns();
    if (!isVisible) {
        target.style.display = 'block';
    }
}

function closeAllActionDropdowns() {
    document.querySelectorAll('.action-dropdown-menu').forEach(menu => {
        menu.style.display = 'none';
    });
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.action-dropdown-container')) {
        closeAllActionDropdowns();
    }
});

// Client Live Tracking Portal Modal Handler (Executive Jet-Black UI)
async function openClientTrackingModal(applicantId) {
    let app = window.currentLoadedApplicant;
    if (!app || app.id != applicantId) {
        try {
            const res = await fetch(`api.php?api=applicant_full_details&id=${applicantId}`);
            app = await res.json();
        } catch(e) {
            showNotification('Failed to fetch applicant details', 'error');
            return;
        }
    }

    let token = app.tracking_token;
    let trackingUrl = token ? (window.location.origin + '/track.php?token=' + token) : '';

    if (!token) {
        Swal.fire({
            title: '🌐 Client Live Tracking Link',
            html: `
                <div style="text-align:left; font-size:13px; color:#334155; line-height:1.5;">
                    <div style="background:#f0f9ff; border:1px solid #bae6fd; border-radius:8px; padding:12px; margin-bottom:12px; color:#0369a1;">
                        <strong>Applicant:</strong> ${app.customer_name} &bull; <strong>Ref:</strong> ${app.loan_id}
                    </div>
                    <p style="margin:0 0 8px 0; color:#1e293b;">No active 30-day tracking portal link currently exists for this file.</p>
                    <p style="margin:0; color:#64748b; font-size:12px;">Generating a link enables real-time tracking for the applicant on their personal portal.</p>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: '⚡ Generate 30-Day Tracking Link',
            confirmButtonColor: '#0284c7',
            cancelButtonText: 'Cancel'
        }).then(async (result) => {
            if (result.isConfirmed) {
                Swal.showLoading();
                try {
                    const fd = new FormData();
                    fd.append('applicant_id', applicantId);
                    const res = await fetch('api.php?api=generate_tracking_token', { method: 'POST', body: fd });
                    const data = await res.json();
                    if (data.success) {
                        if (window.currentLoadedApplicant && window.currentLoadedApplicant.id == applicantId) {
                            window.currentLoadedApplicant.tracking_token = data.token;
                        }
                        openClientTrackingModal(applicantId);
                    } else {
                        Swal.fire('Error', data.error || 'Failed to generate token', 'error');
                    }
                } catch(e) {
                    Swal.fire('Error', 'Server connection error', 'error');
                }
            }
        });
        return;
    }

    const cleanMobile = (app.mobile || '').replace(/\D/g, '');
    const waMsg = encodeURIComponent(`Dear ${app.customer_name},\n\nTrack your ${app.loan_type} application (${app.loan_id}) in real-time via your personal BFS Circle Portal:\n${trackingUrl}\n\nThank you,\nBFS Circle Financial Services`);

    Swal.fire({
        html: `
            <div style="margin:-1.25em -1.25em 16px -1.25em; background:linear-gradient(135deg, #09090b 0%, #18181b 100%); padding:18px 22px; border-bottom:1px solid #27272a; text-align:left; color:#ffffff; border-radius:15px 15px 0 0;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <div style="width:36px; height:36px; border-radius:10px; background:rgba(56,189,248,0.15); border:1px solid rgba(56,189,248,0.3); display:flex; align-items:center; justify-content:center; color:#38bdf8;">
                            <i data-lucide="globe" style="width:20px; height:20px;"></i>
                        </div>
                        <div>
                            <div style="font-size:16px; font-weight:800; font-family:'Outfit', sans-serif; color:#ffffff; letter-spacing:-0.2px;">Client Live Tracking Portal</div>
                            <div style="font-size:11.5px; color:#a1a1aa; margin-top:1px;">${app.customer_name} &bull; Ref: <strong style="color:#38bdf8;">${app.loan_id}</strong></div>
                        </div>
                    </div>
                </div>
            </div>

            <div style="text-align:left; font-size:13px; color:#0f172a; padding:0 4px;">
                <!-- URL INPUT CARD -->
                <div style="background:#f8fafc; border:1px solid #cbd5e1; padding:14px; border-radius:10px; margin-bottom:16px; box-shadow:inset 0 1px 2px rgba(0,0,0,0.03);">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                        <span style="font-size:11px; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.5px;">APPLICANT TRACKING URL</span>
                        <span style="font-size:10.5px; font-weight:700; color:#16a34a; background:#dcfce7; border:1px solid #bbf7d0; padding:2px 7px; border-radius:10px;">🟢 Live Link</span>
                    </div>
                    <div style="display:flex; gap:8px; align-items:center;">
                        <input type="text" id="clientTrackingUrlInput" value="${trackingUrl}" readonly onclick="this.select();" style="width:100%; padding:9px 12px; border:1px solid #94a3b8; border-radius:7px; font-size:12px; background:#ffffff; color:#0f172a; font-family:monospace; font-weight:600; box-shadow:0 1px 2px rgba(0,0,0,0.05);">
                        <button id="btnCopyTrackingUrl" onclick="copyTrackingUrlToClipboard('${trackingUrl}')" style="background:#16a34a; color:#ffffff; border:none; padding:9px 16px; border-radius:7px; font-weight:700; cursor:pointer; font-size:12.5px; white-space:nowrap; display:inline-flex; align-items:center; gap:6px; transition:all 0.2s ease; box-shadow:0 2px 5px rgba(22,163,74,0.25);">
                            <i data-lucide="copy" style="width:14px; height:14px;"></i> Copy Link
                        </button>
                    </div>
                </div>

                <!-- ACTION BUTTONS GRID -->
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">
                    <a href="https://wa.me/91${cleanMobile}?text=${waMsg}" target="_blank" style="background:#16a34a; color:#ffffff !important; text-decoration:none !important; border-radius:9px; padding:11px 14px; font-size:13px; font-weight:700; text-align:center; display:flex; align-items:center; justify-content:center; gap:8px; box-shadow:0 3px 10px rgba(22,163,74,0.3); transition:transform 0.15s ease;">
                        <i data-lucide="message-circle" style="width:16px; height:16px; color:#ffffff;"></i> WhatsApp Client
                    </a>
                    <a href="${trackingUrl}" target="_blank" style="background:#0f172a; color:#ffffff !important; text-decoration:none !important; border-radius:9px; padding:11px 14px; font-size:13px; font-weight:700; text-align:center; display:flex; align-items:center; justify-content:center; gap:8px; box-shadow:0 3px 10px rgba(15,23,42,0.25); transition:transform 0.15s ease;">
                        <i data-lucide="external-link" style="width:16px; height:16px; color:#38bdf8;"></i> Portal Preview
                    </a>
                </div>

                <!-- FOOTER METADATA -->
                <div style="background:#f1f5f9; border:1px solid #e2e8f0; padding:10px 14px; border-radius:8px; font-size:11.5px; color:#475569; display:flex; justify-content:space-between; align-items:center;">
                    <div style="display:flex; align-items:center; gap:6px;">
                        <i data-lucide="shield-check" style="width:14px; height:14px; color:#0d9488;"></i>
                        <span>Encrypted Token (30 Days Validity)</span>
                    </div>
                    <button onclick="regenerateClientTrackingToken(${applicantId})" style="background:none; border:none; color:#d97706; font-weight:700; cursor:pointer; font-size:11.5px; display:inline-flex; align-items:center; gap:4px; text-decoration:underline;">
                        <i data-lucide="refresh-cw" style="width:12px; height:12px;"></i> Regenerate
                    </button>
                </div>
            </div>
        `,
        showConfirmButton: false,
        showCancelButton: true,
        cancelButtonText: 'Close',
        cancelButtonColor: '#64748b',
        width: '500px',
        padding: '1.25em',
        didOpen: (popup) => {
            if (window.lucide) lucide.createIcons({ root: popup });
        }
    });
}

function copyTrackingUrlToClipboard(url) {
    navigator.clipboard.writeText(url).then(() => {
        const btn = document.getElementById('btnCopyTrackingUrl');
        if (btn) {
            btn.style.background = '#15803d';
            btn.innerHTML = '<i data-lucide="check" style="width:14px; height:14px;"></i> Copied!';
            if (window.lucide) lucide.createIcons({ root: btn });
            setTimeout(() => {
                btn.style.background = '#16a34a';
                btn.innerHTML = '<i data-lucide="copy" style="width:14px; height:14px;"></i> Copy Link';
                if (window.lucide) lucide.createIcons({ root: btn });
            }, 2000);
        }
        if (typeof showNotification === 'function') {
            showNotification('Client Tracking URL copied to clipboard!', 'success');
        }
    }).catch(err => {
        const input = document.getElementById('clientTrackingUrlInput');
        if (input) {
            input.select();
            document.execCommand('copy');
            if (typeof showNotification === 'function') {
                showNotification('Tracking URL copied!', 'success');
            }
        }
    });
}

async function regenerateClientTrackingToken(applicantId) {
    if (!confirm('Are you sure you want to regenerate a new tracking token? The previous tracking link will expire.')) return;
    try {
        const fd = new FormData();
        fd.append('applicant_id', applicantId);
        const res = await fetch('api.php?api=generate_tracking_token', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            if (window.currentLoadedApplicant && window.currentLoadedApplicant.id == applicantId) {
                window.currentLoadedApplicant.tracking_token = data.token;
            }
            openClientTrackingModal(applicantId);
        } else {
            showNotification(data.error || 'Failed to regenerate token', 'error');
        }
    } catch(e) {
        showNotification('Server error', 'error');
    }
}

// 4. Deactivation OTP Workflow Handlers
function openDeactivateModal(appId, nameEnc, loanId, emailEnc) {
    const name = decodeURIComponent(nameEnc);
    const email = decodeURIComponent(emailEnc || '');
    
    document.getElementById('deactivateAppId').value = appId;
    document.getElementById('deactivateCustomerName').textContent = name;
    document.getElementById('deactivateLoanId').textContent = loanId;
    document.getElementById('deactivateEmailInput').value = email;
    document.getElementById('deactivateOtpInput').value = '';
    document.getElementById('deactivateReasonInput').value = '';
    
    document.getElementById('deactivateStep1').style.display = 'block';
    document.getElementById('deactivateStep2').style.display = 'none';
    document.getElementById('simulatedOtpNotice').style.display = 'none';
    
    const modal = document.getElementById('deactivateApplicantModal');
    if (modal) modal.style.display = 'flex';
    if (window.lucide) lucide.createIcons({root: modal});
}

function closeDeactivateModal() {
    const modal = document.getElementById('deactivateApplicantModal');
    if (modal) modal.style.display = 'none';
}

async function sendDeactivationOtp() {
    const appId = document.getElementById('deactivateAppId').value;
    const email = document.getElementById('deactivateEmailInput').value.trim();
    const btn = document.getElementById('btnSendOtp');
    
    if (!email) {
        showNotification("Please enter a valid email address.", "error");
        return;
    }
    
    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader" style="width:14px; animation:spin 1s linear infinite;"></i> Sending Code...';
    if(window.lucide) lucide.createIcons({root: btn});

    try {
        const formData = new FormData();
        formData.append('applicant_id', appId);
        formData.append('email', email);
        
        const res = await fetch('api.php?api=send_deactivation_otp', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="mail" style="width:14px;"></i> Send Verification Code';
        
        if (data.error) {
            showNotification(data.error, 'error');
            return;
        }
        
        showNotification(data.message, 'success');
        document.getElementById('deactivateSentEmailText').textContent = data.target_email || email;
        
        if (data.otp_simulated) {
            const notice = document.getElementById('simulatedOtpNotice');
            notice.textContent = "💡 OTP Verification Code: " + data.otp_simulated;
            notice.style.display = 'block';
            document.getElementById('deactivateOtpInput').value = data.otp_simulated;
        }
        
        document.getElementById('deactivateStep1').style.display = 'none';
        document.getElementById('deactivateStep2').style.display = 'block';
        if(window.lucide) lucide.createIcons();
        
    } catch (err) {
        console.error(err);
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="mail" style="width:14px;"></i> Send Verification Code';
        showNotification("Failed to send verification code.", "error");
    }
}

async function verifyDeactivationOtp() {
    const appId = document.getElementById('deactivateAppId').value;
    const otp = document.getElementById('deactivateOtpInput').value.trim();
    const reason = document.getElementById('deactivateReasonInput').value.trim();
    const btn = document.getElementById('btnVerifyOtp');
    
    if (!otp || otp.length < 6) {
        showNotification("Please enter the 6-digit verification code.", "error");
        return;
    }
    
    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader" style="width:14px; animation:spin 1s linear infinite;"></i> Verifying Code...';
    if(window.lucide) lucide.createIcons({root: btn});

    try {
        const formData = new FormData();
        formData.append('applicant_id', appId);
        formData.append('otp', otp);
        formData.append('reason', reason);
        
        const res = await fetch('api.php?api=verify_deactivation_otp', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="power" style="width:14px;"></i> Confirm & Deactivate';
        
        if (data.error) {
            showNotification(data.error, 'error');
            return;
        }
        
        closeDeactivateModal();
        showNotification(data.message, 'success');
        
        selectApplicantCard(appId);
        if (typeof performSearch === 'function') performSearch();
        
    } catch (err) {
        console.error(err);
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="power" style="width:14px;"></i> Confirm & Deactivate';
        showNotification("Failed to verify code.", "error");
    }
}

async function reactivateApplicantFile(appId) {
    if (!confirm("Are you sure you want to REACTIVATE this applicant file?")) return;

    try {
        const formData = new FormData();
        formData.append('applicant_id', appId);
        
        const res = await fetch('api.php?api=reactivate_applicant', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        
        if (data.error) {
            showNotification(data.error, 'error');
            return;
        }
        
        showNotification(data.message, 'success');
        selectApplicantCard(appId);
        if (typeof performSearch === 'function') performSearch();
        
    } catch (err) {
        console.error(err);
        showNotification("Failed to reactivate applicant file.", "error");
    }
}
</script>

<!-- DEACTIVATE APPLICANT FILE MODAL -->
<div id="deactivateApplicantModal" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.65); backdrop-filter:blur(4px); z-index:99999; justify-content:center; align-items:center;">
    <div class="modal-card" style="background:#ffffff; border-radius:14px; width:92%; max-width:480px; box-shadow:0 20px 40px rgba(0,0,0,0.3); overflow:hidden; animation:fadeIn 0.2s ease;">
        <div style="background:#0f172a; color:#ffffff; padding:16px 20px; display:flex; justify-content:space-between; align-items:center;">
            <div style="font-weight:800; font-size:15px; display:flex; align-items:center; gap:8px;">
                <i data-lucide="power" style="width:18px; color:#ef4444;"></i> Deactivate Loan File
            </div>
            <button onclick="closeDeactivateModal()" style="background:none; border:none; color:#94a3b8; font-size:22px; cursor:pointer; line-height:1;">&times;</button>
        </div>
        
        <div style="padding:20px;">
            <input type="hidden" id="deactivateAppId" value="">
            
            <!-- STEP 1: VERIFY EMAIL & SEND CODE -->
            <div id="deactivateStep1">
                <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:8px; padding:12px; margin-bottom:16px; color:#991b1b; font-size:12px; line-height:1.4;">
                    <strong>Security Notice:</strong> To deactivate <span id="deactivateCustomerName" style="font-weight:800; text-decoration:underline;"></span> (Ref: <span id="deactivateLoanId" style="font-weight:700;"></span>), a 6-digit verification code will be dispatched to the applicant's email address. The file will be deactivated (never deleted) and can be reactivated at any time.
                </div>
                
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:6px;">Applicant Registered Email Address</label>
                    <input type="email" id="deactivateEmailInput" class="form-control" placeholder="enter applicant email address..." style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px; box-sizing:border-box;">
                    <small style="color:#64748b; font-size:11px; margin-top:4px; display:block;">Verification OTP code will be sent to this email.</small>
                </div>
                
                <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:20px;">
                    <button type="button" onclick="closeDeactivateModal()" class="btn btn-secondary" style="padding:8px 16px; font-size:12px;">Cancel</button>
                    <button type="button" onclick="sendDeactivationOtp()" id="btnSendOtp" class="btn btn-primary" style="background:#ef4444; border:none; padding:8px 18px; font-size:12.5px; font-weight:700; color:#fff; display:inline-flex; align-items:center; gap:6px; cursor:pointer;">
                        <i data-lucide="mail" style="width:14px;"></i> Send Verification Code
                    </button>
                </div>
            </div>
            
            <!-- STEP 2: ENTER OTP & CONFIRM -->
            <div id="deactivateStep2" style="display:none;">
                <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:12px; margin-bottom:16px; color:#166534; font-size:12px; line-height:1.4;">
                    <i data-lucide="check-circle" style="width:14px; vertical-align:-2px;"></i> Verification code sent to <strong id="deactivateSentEmailText"></strong>. Please enter the 6-digit code below.
                </div>
                
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:6px;">6-Digit OTP Verification Code</label>
                    <input type="text" id="deactivateOtpInput" maxlength="6" class="form-control" placeholder="e.g. 584920" style="width:100%; padding:12px; border:2px solid #0f172a; border-radius:8px; font-size:22px; font-weight:800; letter-spacing:8px; text-align:center; box-sizing:border-box;">
                    <small id="simulatedOtpNotice" style="color:#059669; font-size:11.5px; font-weight:700; margin-top:4px; display:none;"></small>
                </div>

                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:6px;">Deactivation Reason / Remark</label>
                    <textarea id="deactivateReasonInput" rows="2" class="form-control" placeholder="e.g. Client requested cancellation, duplicate entry..." style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:12px; box-sizing:border-box;"></textarea>
                </div>
                
                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:20px;">
                    <button type="button" onclick="sendDeactivationOtp()" style="background:none; border:none; color:#2563eb; font-size:12px; font-weight:700; cursor:pointer; text-decoration:underline;">Resend Code</button>
                    <div style="display:flex; gap:8px;">
                        <button type="button" onclick="closeDeactivateModal()" class="btn btn-secondary" style="padding:8px 16px; font-size:12px;">Cancel</button>
                        <button type="button" onclick="verifyDeactivationOtp()" id="btnVerifyOtp" class="btn btn-primary" style="background:#dc2626; border:none; padding:8px 18px; font-size:12.5px; font-weight:700; color:#fff; display:inline-flex; align-items:center; gap:6px; cursor:pointer;">
                            <i data-lucide="power" style="width:14px;"></i> Confirm & Deactivate
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
