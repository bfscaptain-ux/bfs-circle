<?php
require_once 'config.php';
require_role(['Admin', 'RM']);

$current_page = 'field_visits.php';
$page_title = 'Field Visits & Market Leads Hub';
$page_subtitle = 'Track real-time field visits, market leads, executive GPS check-ins, and follow-ups';
$hide_main_header = true;
require_once 'header.php';

// Calculate KPI Summary Stats
$stat_total = (int)$db->query("SELECT COUNT(*) FROM field_visits")->fetchColumn();
$stat_hot = (int)$db->query("SELECT COUNT(*) FROM field_visits WHERE LOWER(lead_quality) = 'hot'")->fetchColumn();
$stat_gps = (int)$db->query("SELECT COUNT(*) FROM field_visits WHERE latitude IS NOT NULL AND latitude != '' AND latitude != '0'")->fetchColumn();
$stat_execs = (int)$db->query("SELECT COUNT(DISTINCT executive_name) FROM field_visits WHERE executive_name IS NOT NULL AND executive_name != ''")->fetchColumn();
?>

<style>
    /* Monochrome Stats Bar */
    .field-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }
    
    .field-stat-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 18px 20px;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
        display: flex;
        align-items: center;
        justify-content: space-between;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .field-stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 14px rgba(15, 23, 42, 0.08);
        border-color: #0f172a;
    }
    .stat-info-title {
        font-size: 11.5px;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .stat-info-value {
        font-size: 26px;
        font-weight: 800;
        color: #0f172a;
        margin-top: 2px;
        font-family: 'Outfit', sans-serif;
    }
    .stat-icon-box {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #0f172a;
        font-size: 18px;
    }

    /* Monochrome Compact Single-View Filter Bar */
    .filter-bar {
        background: #ffffff;
        padding: 8px 12px;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        margin-bottom: 20px;
        display: flex;
        gap: 6px;
        align-items: center;
        flex-wrap: nowrap;
        overflow: hidden;
        box-shadow: 0 2px 6px rgba(15, 23, 42, 0.03);
    }

    .search-box {
        flex: 1;
        min-width: 140px;
        max-width: 220px;
        position: relative;
        display: flex;
        align-items: center;
    }
    .search-box i,
    .search-box svg {
        position: absolute;
        left: 10px;
        top: 50%;
        transform: translateY(-50%);
        color: #64748b;
        width: 14px;
        height: 14px;
        pointer-events: none;
        z-index: 2;
    }
    .search-box input {
        width: 100%;
        height: 32px;
        padding: 0 10px 0 30px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
        color: #0f172a;
        outline: none;
        background: #ffffff;
        box-sizing: border-box;
        transition: all 0.2s;
    }
    .search-box input:focus {
        border-color: #0f172a;
        box-shadow: 0 0 0 2px rgba(15, 23, 42, 0.08);
    }
    
    .filter-select {
        height: 32px;
        padding: 0 8px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        font-size: 11.5px;
        font-weight: 700;
        background: #ffffff;
        color: #0f172a;
        outline: none;
        cursor: pointer;
        white-space: nowrap;
        flex-shrink: 0;
        max-width: 140px;
        box-sizing: border-box;
        text-overflow: ellipsis;
        transition: border-color 0.2s;
    }
    .filter-select:focus {
        border-color: #0f172a;
    }

    .btn-mono-solid {
        height: 32px;
        background: #0f172a;
        color: #ffffff;
        border: 1px solid #0f172a;
        padding: 0 12px;
        border-radius: 6px;
        font-size: 11.5px;
        font-weight: 800;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        text-decoration: none;
        box-sizing: border-box;
        transition: all 0.2s;
        white-space: nowrap;
        flex-shrink: 0;
    }
    .btn-mono-solid:hover {
        background: #000000;
        border-color: #000000;
    }

    .btn-mono-outline {
        height: 32px;
        background: #ffffff;
        color: #0f172a;
        border: 1px solid #cbd5e1;
        padding: 0 12px;
        border-radius: 6px;
        font-size: 11.5px;
        font-weight: 700;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        text-decoration: none;
        box-sizing: border-box;
        transition: all 0.2s;
        white-space: nowrap;
        flex-shrink: 0;
    }
    .btn-mono-outline:hover {
        background: #f8fafc;
        border-color: #0f172a;
    }

    /* Data Table Styling */
    .table-container {
        background: #ffffff;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.04);
        overflow-x: auto;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        white-space: nowrap;
    }

    th {
        background: #f8fafc;
        padding: 14px 20px;
        text-align: left;
        font-size: 11px;
        font-weight: 800;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: 2px solid #e2e8f0;
    }

    td {
        padding: 14px 20px;
        font-size: 13.5px;
        color: #0f172a;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }

    tr:last-child td { border-bottom: none; }
    tr:hover { background: #f8fafc; }
    
    /* Monochrome Quality Badges */
    .quality-badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .quality-hot { background: #000000; color: #ffffff; border: 1px solid #000000; }
    .quality-warm { background: #f1f5f9; color: #0f172a; border: 1px solid #cbd5e1; }
    .quality-cold { background: #ffffff; color: #64748b; border: 1px solid #e2e8f0; }

    .btn-action-view {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        color: #0f172a;
        padding: 6px 12px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 12px;
        font-weight: 700;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .btn-action-view:hover {
        background: #0f172a;
        color: #ffffff;
        border-color: #0f172a;
    }

    .btn-action-convert {
        background: #f8fafc;
        border: 1px solid #0f172a;
        color: #0f172a;
        padding: 6px 12px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 12px;
        font-weight: 800;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-left: 4px;
    }
    .btn-action-convert:hover {
        background: #0f172a;
        color: #ffffff;
    }

    .pagination {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 14px 20px;
        border-top: 1px solid #e2e8f0;
        background: #ffffff;
    }
    
    .page-btn {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        padding: 6px 14px;
        border-radius: 6px;
        font-size: 12.5px;
        font-weight: 700;
        color: #0f172a;
        cursor: pointer;
        transition: all 0.2s;
    }
    .page-btn:hover:not(:disabled) {
        background: #f1f5f9;
        border-color: #0f172a;
    }
    .page-btn:disabled {
        opacity: 0.4;
        cursor: not-allowed;
    }

    /* Slide-Over Drawer for Details & Follow-ups */
    .modal-overlay {
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(15, 23, 42, 0.65);
        backdrop-filter: blur(4px);
        display: none;
        z-index: 999;
    }
    .followup-sidebar {
        position: fixed;
        top: 0; right: -480px;
        width: 480px;
        max-width: 90vw;
        height: 100vh;
        background: #ffffff;
        box-shadow: -10px 0 30px rgba(15, 23, 42, 0.25);
        z-index: 1000;
        transition: right 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        display: flex;
        flex-direction: column;
    }
    .followup-sidebar.open { right: 0; }

    .fs-header {
        padding: 20px 24px;
        background: #0f172a;
        color: #ffffff;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .fs-header h2 { font-size: 17px; font-weight: 800; color: #ffffff; margin: 0 0 2px 0; }
    .fs-header p { font-size: 12px; color: #94a3b8; margin: 0; }
    .fs-close { cursor: pointer; color: #ffffff; font-size: 18px; opacity: 0.8; transition: opacity 0.2s; }
    .fs-close:hover { opacity: 1; }
    
    .fs-content {
        padding: 24px;
        flex: 1;
        overflow-y: auto;
    }

    .timeline {
        position: relative;
        margin-left: 10px;
        border-left: 2px solid #e2e8f0;
        padding-left: 18px;
        margin-bottom: 24px;
    }
    .timeline-item {
        position: relative;
        margin-bottom: 20px;
    }
    .timeline-item::before {
        content: '';
        position: absolute;
        left: -25px;
        top: 4px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #0f172a;
        border: 2px solid #ffffff;
    }
    .tl-date { font-size: 11.5px; font-weight: 700; color: #64748b; margin-bottom: 4px; }
    .tl-text { font-size: 13px; color: #0f172a; background: #f8fafc; padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; }
    .tl-meta { font-size: 11.5px; font-weight: 700; margin-top: 6px; display: flex; justify-content: space-between; color: #475569; }
    
    .add-followup-form {
        background: #f8fafc;
        padding: 18px;
        border-radius: 10px;
        border: 1px solid #cbd5e1;
    }
    .add-followup-form label {
        display: block; font-size: 12px; font-weight: 700; color: #0f172a; margin-bottom: 5px; margin-top: 10px;
    }
    .add-followup-form textarea, .add-followup-form input {
        width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; outline: none; background: #ffffff; box-sizing: border-box;
    }
    .add-followup-form textarea:focus, .add-followup-form input:focus {
        border-color: #0f172a;
    }
    
    .photo-preview {
        width: 100%;
        height: 160px;
        object-fit: cover;
        border-radius: 10px;
        margin-bottom: 16px;
        border: 1px solid #cbd5e1;
        display: none;
    }
</style>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<div class="view-container">

    <!-- KPI STATS SUMMARY CARDS -->
    <div class="field-stats-grid">
        <div class="field-stat-card">
            <div>
                <div class="stat-info-title">Total Field Visits</div>
                <div class="stat-info-value"><?php echo number_format($stat_total); ?></div>
            </div>
            <div class="stat-icon-box">
                <i data-lucide="map-pin"></i>
            </div>
        </div>

        <div class="field-stat-card">
            <div>
                <div class="stat-info-title">Hot Market Leads</div>
                <div class="stat-info-value"><?php echo number_format($stat_hot); ?></div>
            </div>
            <div class="stat-icon-box" style="background:#0f172a; color:#ffffff;">
                <i data-lucide="flame"></i>
            </div>
        </div>

        <div class="field-stat-card">
            <div>
                <div class="stat-info-title">GPS Verified Visits</div>
                <div class="stat-info-value"><?php echo number_format($stat_gps); ?></div>
            </div>
            <div class="stat-icon-box">
                <i data-lucide="crosshair"></i>
            </div>
        </div>

        <div class="field-stat-card">
            <div>
                <div class="stat-info-title">Active Field Execs</div>
                <div class="stat-info-value"><?php echo number_format($stat_execs); ?></div>
            </div>
            <div class="stat-icon-box">
                <i data-lucide="users"></i>
            </div>
        </div>
    </div>

    <!-- COMPACT SINGLE-VIEW TOOLBAR -->
    <div class="filter-bar">
        <div class="search-box">
            <i data-lucide="search"></i>
            <input type="text" id="searchInput" placeholder="Search Firm, Person, Mobile..." oninput="loadData(1)">
        </div>

        <select class="filter-select" id="qualityFilter" onchange="loadData(1)">
            <option value="">All Qualities</option>
            <option value="Hot">🔥 Hot</option>
            <option value="Warm">⚡ Warm</option>
            <option value="Cold">❄️ Cold</option>
        </select>

        <select class="filter-select" id="profFilter" onchange="loadData(1)">
            <option value="">All Professions</option>
            <option value="CA">CA</option>
            <option value="PROPERTY DEALER">Property Dealer</option>
            <option value="ARCHITECT">Architect</option>
            <option value="CONTRACTOR">Contractor</option>
            <option value="BUSINESS OWNER">Business Owner</option>
            <option value="OTHER">Other</option>
        </select>

        <button type="button" class="btn-mono-solid" onclick="openNewVisitModal()">
            <i data-lucide="plus" style="width:13px;height:13px;"></i> Log Visit
        </button>

        <button type="button" class="btn-mono-outline" onclick="openLiveTrackingModal()">
            <i data-lucide="radar" style="width:13px;height:13px;"></i> Radar
        </button>

        <button type="button" class="btn-mono-outline" onclick="openLeaderboardModal()">
            <i data-lucide="trophy" style="width:13px;height:13px;"></i> Leaderboard
        </button>

        <a href="export_admin_visits.php" class="btn-mono-outline">
            <i data-lucide="download" style="width:13px;height:13px;"></i> Export
        </a>
    </div>

    <!-- TABLE -->
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Date & Executive</th>
                    <th>Firm Details</th>
                    <th>Person & Contact</th>
                    <th>Profession</th>
                    <th>Lead Quality</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="tableBody">
                <tr><td colspan="6" style="text-align:center; padding:30px; color:#64748b;">Loading field visits...</td></tr>
            </tbody>
        </table>
        
        <!-- Pagination -->
        <div class="pagination">
            <span id="pageInfo" style="font-size:13px; font-weight:700; color:#64748b;">Showing 0 entries</span>
            <div style="display:flex; gap:8px;">
                <button class="page-btn" id="prevBtn" onclick="changePage(-1)">&laquo; Previous</button>
                <button class="page-btn" id="nextBtn" onclick="changePage(1)">Next &raquo;</button>
            </div>
        </div>
    </div>
</div>

<!-- Follow-up & Details Drawer -->
<div class="modal-overlay" id="overlay" onclick="closeSidebar()"></div>
<div class="followup-sidebar" id="sidebar">
    <div class="fs-header">
        <div>
            <h2 id="fs_firm_name">Firm Name</h2>
            <p id="fs_person_name">Person Name</p>
        </div>
        <i data-lucide="x" class="fs-close" onclick="closeSidebar()"></i>
    </div>
    
    <div class="fs-content">
        <div id="sidebar-map" style="height: 190px; width: 100%; border-radius: 10px; margin-bottom: 16px; border: 1px solid #cbd5e1; display: none;"></div>
        <a href="#" target="_blank" id="fs_photo_link"><img src="" class="photo-preview" id="fs_photo"></a>
        
        <div id="smart-visit-info" style="display:none; background:#f8fafc; border:1px solid #cbd5e1; border-radius:10px; padding:14px; margin-bottom:18px;"></div>
        
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
            <h3 style="font-size:14.5px; font-weight:800; color:#0f172a; margin:0;">Follow-up Timeline & History</h3>
            <button type="button" class="btn-mono-outline" id="fs_convert_btn" style="padding:4px 10px; font-size:11px;" onclick="convertVisitToLead()">
                ⚡ Convert to CRM Lead
            </button>
        </div>

        <div class="timeline" id="timelineContainer">
            <!-- Injected via JS -->
        </div>
        
        <div class="add-followup-form">
            <h3 style="font-size:14px; font-weight:800; color:#0f172a; margin-bottom:6px;">Add New Executive Follow-up</h3>
            <form id="followupForm" onsubmit="submitFollowup(event)">
                <input type="hidden" id="fv_id" name="visit_id">
                
                <label>Follow-up Remarks / Meeting Outcome *</label>
                <textarea name="remarks" rows="2" placeholder="Enter follow-up outcome or client notes..." required></textarea>
                
                <label>Next Scheduled Meeting Date</label>
                <input type="date" name="next_meeting_date">
                
                <button type="submit" class="btn-mono-solid" style="width:100%; margin-top:14px; justify-content:center;">
                    Save Follow-up Record
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    let currentPage = 1;
    let totalPages = 1;
    let sidebarMap = null;
    let sidebarMarker = null;
    let currentActiveVisit = null;

    function initSidebarMap(lat, lng, title, subtitle) {
        const mapDiv = document.getElementById('sidebar-map');
        mapDiv.style.display = 'block';
        
        if (!sidebarMap) {
            sidebarMap = L.map('sidebar-map').setView([lat, lng], 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(sidebarMap);
        } else {
            sidebarMap.setView([lat, lng], 15);
        }
        
        if (sidebarMarker) {
            sidebarMap.removeLayer(sidebarMarker);
        }
        
        sidebarMarker = L.marker([lat, lng]).addTo(sidebarMap)
            .bindPopup(`<b>${title}</b><br>${subtitle}`).openPopup();
            
        setTimeout(() => sidebarMap.invalidateSize(), 300);
    }

    function getQualityBadge(q) {
        if(!q) return '-';
        const v = q.toLowerCase();
        let label = q.toUpperCase();
        return `<span class="quality-badge quality-${v}">${label}</span>`;
    }

    async function loadData(page = 1) {
        currentPage = page;
        const search = document.getElementById('searchInput').value;
        const quality = document.getElementById('qualityFilter').value;
        const prof = document.getElementById('profFilter').value;
        
        const tbody = document.getElementById('tableBody');
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:30px; color:#64748b;">Loading field visits...</td></tr>';
        
        try {
            const res = await fetch(`api.php?api=get_field_visits&page=${page}&search=${encodeURIComponent(search)}&quality=${encodeURIComponent(quality)}&prof=${encodeURIComponent(prof)}`);
            const data = await res.json();
            
            if (!data.data || data.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:30px; color:#64748b; font-weight:600;">No field visits found matching your criteria.</td></tr>';
                document.getElementById('pageInfo').innerText = `Showing 0 entries`;
                return;
            }
            
            let html = '';
            data.data.forEach(row => {
                const profDisplay = row.profession === 'OTHER' ? (row.custom_profession || 'OTHER') : row.profession;
                html += `
                    <tr>
                        <td>
                            <div style="font-weight:800; color:#0f172a;">${row.visit_date}</div>
                            <div style="font-size:12px; color:#64748b;">By: ${row.executive_name || 'Staff'}</div>
                        </td>
                        <td>
                            <div style="font-weight:800; color:#0f172a;">${row.firm_name}</div>
                            <div style="font-size:12px; color:#64748b;"><i data-lucide="map-pin" style="width:12px;height:12px;display:inline;"></i> ${row.city || 'N/A'}, ${row.state || ''}</div>
                        </td>
                        <td>
                            <div style="font-weight:700; color:#0f172a;">${row.person_name}</div>
                            <div style="font-size:12px; color:#64748b;"><i data-lucide="phone" style="width:12px;height:12px;display:inline;"></i> ${row.mobile}</div>
                        </td>
                        <td>
                            <span style="font-size:11.5px; font-weight:700; color:#0f172a; background:#f1f5f9; padding:4px 9px; border-radius:6px; border:1px solid #cbd5e1;">${profDisplay}</span>
                        </td>
                        <td>${getQualityBadge(row.lead_quality)}</td>
                        <td>
                            <button class="btn-action-view" onclick="openSidebar(${row.id})">
                                <i data-lucide="eye" style="width:13px;height:13px;"></i> Follow-ups & Map
                            </button>
                            <button class="btn-action-convert" onclick="convertVisitToLeadDirect(${row.id}, '${addslashes(row.person_name)}', '${row.mobile}', '${addslashes(row.firm_name)}', '${row.profession || ''}', '${addslashes(row.city || '')}', '${addslashes(row.executive_name || '')}')">
                                <i data-lucide="user-plus" style="width:13px;height:13px;"></i> Convert
                            </button>
                        </td>
                    </tr>
                `;
            });
            
            tbody.innerHTML = html;
            if (window.lucide) lucide.createIcons();
            
            totalPages = data.total_pages || 1;
            document.getElementById('pageInfo').innerText = `Page ${page} of ${totalPages} (${data.total_records} visits)`;
            document.getElementById('prevBtn').disabled = page <= 1;
            document.getElementById('nextBtn').disabled = page >= totalPages;
            
        } catch (err) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; color:#e11d48; padding:20px;">Error loading field visits data.</td></tr>';
        }
    }

    function addslashes(str) {
        return (str || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '\\"');
    }

    function changePage(delta) {
        const newPage = currentPage + delta;
        if (newPage >= 1 && newPage <= totalPages) {
            loadData(newPage);
        }
    }

    async function openSidebar(visitId) {
        document.getElementById('overlay').style.display = 'block';
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.add('open');
        document.getElementById('fv_id').value = visitId;
        
        const timeline = document.getElementById('timelineContainer');
        timeline.innerHTML = '<p style="font-size:13px; color:#64748b; text-align:center;">Loading visit history...</p>';
        
        try {
            const res = await fetch(`api.php?api=get_visit_details&id=${visitId}`);
            const data = await res.json();
            const v = data.visit;
            currentActiveVisit = v;
            
            document.getElementById('fs_firm_name').innerText = v.firm_name;
            document.getElementById('fs_person_name').innerText = v.person_name + ' | ' + v.mobile;
            
            if (v.photo_path) {
                const img = document.getElementById('fs_photo');
                img.src = v.photo_path;
                img.style.display = 'block';
                document.getElementById('fs_photo_link').href = v.photo_path;
            } else {
                document.getElementById('fs_photo').style.display = 'none';
            }
            
            // Live Tracking Map
            document.getElementById('sidebar-map').style.display = 'none';
            try {
                const onlineRes = await fetch('api.php?api=get_online_staff');
                const onlineData = await onlineRes.json();
                const staffTracking = (onlineData || []).find(s => s.username === v.executive_name);
                
                if (staffTracking && staffTracking.lat) {
                    initSidebarMap(staffTracking.lat, staffTracking.lon, 
                        "Live Exec Location (" + (v.executive_name || '').split('@')[0] + ")", 
                        "Battery: " + (staffTracking.battery || '--') + "% | Status: " + staffTracking.status);
                } else if (v.latitude && v.longitude && v.latitude !== '0') {
                    initSidebarMap(v.latitude, v.longitude, 
                        "Visit GPS Capture", 
                        "Location recorded at check-in: " + (v.verified_address || 'Verified GPS'));
                }
            } catch(e) {}
            
            // Smart Check-in Block
            const smartBlock = document.getElementById('smart-visit-info');
            let smartHtml = '<h4 style="font-size:13px; font-weight:800; color:#0f172a; margin-bottom:8px; display:flex; justify-content:space-between; align-items:center;"><span>📍 GPS & Smart Check-In Logs</span></h4>';
            let hasSmartData = false;
            
            if (v.check_in_time) {
                hasSmartData = true;
                smartHtml += `<div style="font-size:12px; color:#475569; margin-bottom:4px;"><b>Check-in Time:</b> ${v.check_in_time}</div>`;
            }
            if (v.check_out_time) {
                hasSmartData = true;
                smartHtml += `<div style="font-size:12px; color:#475569; margin-bottom:4px;"><b>Check-out Time:</b> ${v.check_out_time}</div>`;
            }
            if (v.verified_address) {
                hasSmartData = true;
                smartHtml += `<div style="font-size:12px; color:#475569; margin-bottom:4px;"><b>GPS Verified Address:</b> ${v.verified_address}</div>`;
            }
            if (v.audio_path) {
                hasSmartData = true;
                smartHtml += `
                    <div style="margin-top:10px; padding:12px; background:#FEF2F2; border:1px solid #FECACA; border-radius:12px;">
                        <div style="font-size:12px; font-weight:700; color:#0F172A; margin-bottom:8px; display:flex; align-items:center; justify-content:space-between;">
                            <span style="color:#DC2626; display:flex; align-items:center; gap:6px;">
                                <i class="fas fa-microphone-alt" style="color:#EF4444; font-size:14px;"></i> Meeting Voice Recording
                            </span>
                            <a href="${v.audio_path}" download style="font-size:11px; color:#FF7A00; text-decoration:none; font-weight:700; background:#FFF; padding:3px 10px; border-radius:6px; border:1px solid #FFD8B2;">
                                <i class="fas fa-download"></i> Download
                            </a>
                        </div>
                        <audio controls style="width:100%; height:38px; outline:none; border-radius:8px;">
                            <source src="${v.audio_path}" type="audio/webm">
                            <source src="${v.audio_path}" type="audio/mp4">
                            <source src="${v.audio_path}" type="audio/mpeg">
                            <source src="${v.audio_path}" type="audio/ogg">
                            Your browser does not support audio playback.
                        </audio>
                    </div>`;
            } else {
                hasSmartData = true;
                smartHtml += `
                    <div style="margin-top:8px; padding:8px 10px; background:#F8FAFC; border:1px dashed #CBD5E1; border-radius:8px; font-size:11.5px; color:#64748B; display:flex; align-items:center; gap:6px;">
                        <i class="fas fa-microphone-slash" style="color:#94A3B8;"></i> <b>Voice Recording:</b> No audio recorded for this visit
                    </div>`;
            }
            
            if (hasSmartData) {
                smartBlock.innerHTML = smartHtml;
                smartBlock.style.display = 'block';
            } else {
                smartBlock.style.display = 'none';
            }
            
            // Timeline
            let tlHtml = '';
            tlHtml += `
                <div class="timeline-item">
                    <div class="tl-date">${v.visit_date} — Initial Market Check-in</div>
                    <div class="tl-text">Logged field visit. Quality: <strong>${v.lead_quality || 'Cold'}</strong></div>
                    <div class="tl-meta"><span>By: ${v.executive_name || 'Staff'}</span></div>
                </div>
            `;
            
            (data.followups || []).forEach(f => {
                tlHtml += `
                    <div class="timeline-item">
                        <div class="tl-date">${f.created_at}</div>
                        <div class="tl-text">${f.remarks}</div>
                        <div class="tl-meta">
                            <span>By: ${f.added_by}</span>
                            <span style="color:#0f172a;"><i data-lucide="calendar" style="width:12px;height:12px;display:inline;"></i> Next: ${f.next_meeting_date || 'N/A'}</span>
                        </div>
                    </div>
                `;
            });
            
            timeline.innerHTML = tlHtml;
            if (window.lucide) lucide.createIcons();
            
        } catch (err) {
            timeline.innerHTML = '<p style="color:#e11d48; font-size:13px;">Error loading visit details.</p>';
        }
    }

    function closeSidebar() {
        document.getElementById('overlay').style.display = 'none';
        document.getElementById('sidebar').classList.remove('open');
    }

    async function submitFollowup(e) {
        e.preventDefault();
        const form = document.getElementById('followupForm');
        const formData = new FormData(form);
        
        try {
            const res = await fetch('api.php?api=add_visit_followup', { method: 'POST', body: formData });
            if (res.ok) {
                form.reset();
                const visitId = document.getElementById('fv_id').value;
                openSidebar(visitId);
                Swal.fire({ icon: 'success', title: 'Follow-up Saved', timer: 1500, showConfirmButton: false });
            } else {
                Swal.fire('Error', 'Failed to save follow-up record', 'error');
            }
        } catch (err) {
            Swal.fire('Network Error', 'Could not save follow-up', 'error');
        }
    }

    function convertVisitToLeadDirect(id, name, mobile, firm, profession = '', city = '', execName = '') {
        Swal.fire({
            title: `<div style="font-size:17px; font-weight:800; color:#0f172a; display:flex; align-items:center; justify-content:center; gap:8px;"><i class="fas fa-random" style="color:#FF7A00;"></i> Select Conversion Route</div>`,
            html: `
                <div style="text-align:left; font-size:13px; color:#475569; margin-bottom:14px; background:#f8fafc; padding:10px 12px; border-radius:8px; border:1px solid #e2e8f0;">
                    Visit Contact: <strong>${name}</strong> | Mobile: <strong>${mobile}</strong><br>
                    Firm/Location: <strong>${firm || 'N/A'}</strong> ${city ? ' ('+city+')' : ''}
                </div>
                <div style="font-size:12.5px; font-weight:700; color:#0f172a; margin-bottom:10px; text-align:left;">Choose how you want to add this contact to CRM:</div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div onclick="selectConvertOption('applicant', ${id}, '${addslashes(name)}', '${mobile}', '${addslashes(firm)}', '${profession}', '${addslashes(city)}', '${addslashes(execName)}')" 
                         style="background:#ffffff; border:2px solid #cbd5e1; border-radius:12px; padding:16px 12px; cursor:pointer; text-align:center; transition:all 0.2s; box-shadow:0 2px 5px rgba(0,0,0,0.03);"
                         onmouseover="this.style.borderColor='#FF7A00'; this.style.background='#fffcf9';"
                         onmouseout="this.style.borderColor='#cbd5e1'; this.style.background='#ffffff';">
                        <div style="width:44px; height:44px; border-radius:50%; background:#fff7ed; color:#FF7A00; display:flex; align-items:center; justify-content:center; margin:0 auto 10px auto; font-size:18px;">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </div>
                        <div style="font-weight:800; color:#0f172a; font-size:13.5px; margin-bottom:4px;">1. Client Loan File</div>
                        <div style="font-size:11px; color:#64748b; line-height:1.3;">Direct Customer borrowing Home Loan, LAP, Business Loan, etc.</div>
                    </div>

                    <div onclick="selectConvertOption('referral', ${id}, '${addslashes(name)}', '${mobile}', '${addslashes(firm)}', '${profession}', '${addslashes(city)}', '${addslashes(execName)}')" 
                         style="background:#ffffff; border:2px solid #cbd5e1; border-radius:12px; padding:16px 12px; cursor:pointer; text-align:center; transition:all 0.2s; box-shadow:0 2px 5px rgba(0,0,0,0.03);"
                         onmouseover="this.style.borderColor='#10b981'; this.style.background='#f0fdf4';"
                         onmouseout="this.style.borderColor='#cbd5e1'; this.style.background='#ffffff';">
                        <div style="width:44px; height:44px; border-radius:50%; background:#d1fae5; color:#059669; display:flex; align-items:center; justify-content:center; margin:0 auto 10px auto; font-size:18px;">
                            <i class="fas fa-handshake"></i>
                        </div>
                        <div style="font-weight:800; color:#0f172a; font-size:13.5px; margin-bottom:4px;">2. Channel Partner / DSA</div>
                        <div style="font-size:11px; color:#64748b; line-height:1.3;">Builder, Property Dealer, CA, Advocate, Architect, DSA</div>
                    </div>
                </div>
            `,
            showConfirmButton: false,
            showCancelButton: true,
            cancelButtonText: 'Cancel',
            width: 520
        });
    }

    function selectConvertOption(type, id, name, mobile, firm, profession, city, execName) {
        Swal.close();
        if (type === 'applicant') {
            window.location.href = `add_applicant.php?customer_name=${encodeURIComponent(name)}&mobile=${encodeURIComponent(mobile)}&notes=${encodeURIComponent('Converted from Field Visit: ' + firm)}&visit_id=${id}`;
        } else if (type === 'referral') {
            let categoryVal = (profession || 'Property Dealer').toUpperCase();
            
            Swal.fire({
                title: `<div style="font-size:16px; font-weight:800; color:#0f172a;">🤝 Register Referral Partner / DSA</div>`,
                html: `
                    <div style="text-align:left; font-size:12.5px; color:#334155; padding:8px 0;">
                        <label style="font-weight:700; display:block; margin-bottom:4px;">Partner Contact Name *</label>
                        <input type="text" id="ref_name" class="ctrl-input" value="${name}" style="width:100%; box-sizing:border-box; margin-bottom:10px;">
                        
                        <label style="font-weight:700; display:block; margin-bottom:4px;">Mobile Number *</label>
                        <input type="tel" id="ref_mobile" class="ctrl-input" value="${mobile}" style="width:100%; box-sizing:border-box; margin-bottom:10px;">
                        
                        <label style="font-weight:700; display:block; margin-bottom:4px;">Firm / Agency Name</label>
                        <input type="text" id="ref_firm" class="ctrl-input" value="${firm}" style="width:100%; box-sizing:border-box; margin-bottom:10px;">
                        
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:10px;">
                            <div>
                                <label style="font-weight:700; display:block; margin-bottom:4px;">Partner Category *</label>
                                <select id="ref_category" class="ctrl-input" style="width:100%; box-sizing:border-box;">
                                    <option value="Property Dealer" ${categoryVal.includes('PROPERTY') ? 'selected' : ''}>Property Dealer</option>
                                    <option value="Builder / Developer" ${categoryVal.includes('BUILDER') || categoryVal.includes('CONTRACTOR') ? 'selected' : ''}>Builder / Developer</option>
                                    <option value="Chartered Accountant (CA)" ${categoryVal === 'CA' ? 'selected' : ''}>Chartered Accountant (CA)</option>
                                    <option value="Advocate / Legal" ${categoryVal === 'ADVOCATE' ? 'selected' : ''}>Advocate / Legal</option>
                                    <option value="Architect / Interior" ${categoryVal === 'ARCHITECT' ? 'selected' : ''}>Architect / Interior</option>
                                    <option value="DSA Connector" ${categoryVal.includes('CONNECTOR') || categoryVal.includes('DSA') ? 'selected' : ''}>DSA Connector</option>
                                    <option value="Other Partner" ${categoryVal === 'OTHER' ? 'selected' : ''}>Other Partner</option>
                                </select>
                            </div>
                            <div>
                                <label style="font-weight:700; display:block; margin-bottom:4px;">City / Area</label>
                                <input type="text" id="ref_city" class="ctrl-input" value="${city || ''}" placeholder="Location" style="width:100%; box-sizing:border-box;">
                            </div>
                        </div>

                        <label style="font-weight:700; display:block; margin-bottom:4px;">Assigned Executive / RM</label>
                        <input type="text" id="ref_exec" class="ctrl-input" value="${execName || ''}" placeholder="Assigned RM" style="width:100%; box-sizing:border-box;">
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Register Partner & Link Visit',
                confirmButtonColor: '#10b981',
                preConfirm: () => {
                    const partnerName = document.getElementById('ref_name').value.trim();
                    const partnerMobile = document.getElementById('ref_mobile').value.trim();
                    const partnerFirm = document.getElementById('ref_firm').value.trim();
                    const category = document.getElementById('ref_category').value;
                    const partnerCity = document.getElementById('ref_city').value.trim();
                    const assignedRm = document.getElementById('ref_exec').value.trim();

                    if (!partnerName || !partnerMobile) {
                        Swal.showValidationMessage('Partner Name and Mobile are required');
                        return false;
                    }
                    return { partnerName, partnerMobile, partnerFirm, category, partnerCity, assignedRm };
                }
            }).then((res) => {
                if (res.isConfirmed && res.value) {
                    const fd = new FormData();
                    fd.append('visit_id', id);
                    fd.append('name', res.value.partnerName);
                    fd.append('mobile', res.value.partnerMobile);
                    fd.append('firm_name', res.value.partnerFirm);
                    fd.append('category', res.value.category);
                    fd.append('city_state', res.value.partnerCity);
                    fd.append('assigned_to', res.value.assignedRm);

                    fetch('api.php?api=convert_visit_to_referral', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Referral Partner Registered!',
                                text: `Registered as ${res.value.category}. Visit converted successfully.`,
                                confirmButtonColor: '#0f172a'
                            });
                            loadData(currentPage);
                        } else {
                            Swal.fire('Error', data.error || 'Failed to register partner', 'error');
                        }
                    });
                }
            });
        }
    }

    function convertVisitToLead() {
        if (!currentActiveVisit) return;
        convertVisitToLeadDirect(currentActiveVisit.id, currentActiveVisit.person_name, currentActiveVisit.mobile, currentActiveVisit.firm_name, currentActiveVisit.profession, currentActiveVisit.city, currentActiveVisit.executive_name);
    }

    // Modal: Log New Field Visit from Web UI
    function openNewVisitModal() {
        Swal.fire({
            title: `<span style="font-size:16px; font-weight:800; color:#0f172a;">📍 Log New Field Visit</span>`,
            html: `
                <div style="text-align:left; font-size:12.5px; color:#334155; padding:8px 0;">
                    <label style="font-weight:700; display:block; margin-bottom:4px;">Firm / Business Name *</label>
                    <input type="text" id="fv_new_firm" class="ctrl-input" placeholder="e.g. Apex Realtors / Verma CA" style="width:100%; box-sizing:border-box; margin-bottom:10px;">
                    
                    <label style="font-weight:700; display:block; margin-bottom:4px;">Contact Person Name *</label>
                    <input type="text" id="fv_new_person" class="ctrl-input" placeholder="Person Name" style="width:100%; box-sizing:border-box; margin-bottom:10px;">
                    
                    <label style="font-weight:700; display:block; margin-bottom:4px;">Mobile Number *</label>
                    <input type="tel" id="fv_new_mobile" class="ctrl-input" placeholder="10-digit mobile" style="width:100%; box-sizing:border-box; margin-bottom:10px;">
                    
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:10px;">
                        <div>
                            <label style="font-weight:700; display:block; margin-bottom:4px;">Profession</label>
                            <select id="fv_new_prof" class="ctrl-input" style="width:100%; box-sizing:border-box;">
                                <option value="PROPERTY DEALER">Property Dealer</option>
                                <option value="CA">CA</option>
                                <option value="ARCHITECT">Architect</option>
                                <option value="CONTRACTOR">Builder/Contractor</option>
                                <option value="BUSINESS OWNER">Business Owner</option>
                                <option value="OTHER">Other</option>
                            </select>
                        </div>
                        <div>
                            <label style="font-weight:700; display:block; margin-bottom:4px;">Lead Quality</label>
                            <select id="fv_new_quality" class="ctrl-input" style="width:100%; box-sizing:border-box;">
                                <option value="Hot">🔥 Hot</option>
                                <option value="Warm" selected>⚡ Warm</option>
                                <option value="Cold">❄️ Cold</option>
                            </select>
                        </div>
                    </div>
                    
                    <label style="font-weight:700; display:block; margin-bottom:4px;">City / Area Location</label>
                    <input type="text" id="fv_new_city" class="ctrl-input" placeholder="e.g. Connaught Place, New Delhi" style="width:100%; box-sizing:border-box; margin-bottom:10px;">
                    
                    <label style="font-weight:700; display:block; margin-bottom:4px;">Visit Remarks / Discussion</label>
                    <textarea id="fv_new_remarks" class="ctrl-input" rows="2" placeholder="Discussion notes..." style="width:100%; box-sizing:border-box;"></textarea>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Save Field Visit',
            confirmButtonColor: '#0f172a',
            preConfirm: () => {
                const firm = document.getElementById('fv_new_firm').value.trim();
                const person = document.getElementById('fv_new_person').value.trim();
                const mobile = document.getElementById('fv_new_mobile').value.trim();
                const prof = document.getElementById('fv_new_prof').value;
                const quality = document.getElementById('fv_new_quality').value;
                const city = document.getElementById('fv_new_city').value.trim();
                const remarks = document.getElementById('fv_new_remarks').value.trim();

                if (!firm || !person || !mobile) {
                    Swal.showValidationMessage('Firm, Person, and Mobile are required');
                    return false;
                }
                return { firm, person, mobile, prof, quality, city, remarks };
            }
        }).then((res) => {
            if (res.isConfirmed && res.value) {
                const fd = new FormData();
                fd.append('firm_name', res.value.firm);
                fd.append('person_name', res.value.person);
                fd.append('mobile', res.value.mobile);
                fd.append('profession', res.value.prof);
                fd.append('lead_quality', res.value.quality);
                fd.append('city', res.value.city);
                fd.append('remarks', res.value.remarks);

                fetch('api.php?api=save_field_visit', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({ icon: 'success', title: 'Visit Recorded!', text: 'Field visit logged successfully.', confirmButtonColor: '#0f172a' });
                        loadData(1);
                    } else {
                        Swal.fire('Error', data.error || 'Failed to save visit', 'error');
                    }
                });
            }
        });
    }

    // Modal: Live Tracking Radar
    function openLiveTrackingModal() {
        fetch('api.php?api=get_online_staff')
        .then(r => r.json())
        .then(staff => {
            let listHtml = '';
            if (!staff || staff.length === 0) {
                listHtml = '<p style="color:#64748b; text-align:center; padding:20px;">No field staff currently active on GPS radar.</p>';
            } else {
                staff.forEach(s => {
                    listHtml += `
                        <div style="display:flex; justify-content:space-between; align-items:center; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:10px 14px; margin-bottom:8px;">
                            <div>
                                <div style="font-weight:800; color:#0f172a; font-size:13px;">${s.name || s.username}</div>
                                <div style="font-size:11px; color:#64748b;">Last Active: ${s.last_active || 'Just now'} | Battery: ${s.battery || '--'}%</div>
                            </div>
                            <span style="font-size:11px; font-weight:800; background:#0f172a; color:#fff; padding:3px 9px; border-radius:12px;">Active Radar</span>
                        </div>
                    `;
                });
            }

            Swal.fire({
                title: `<span style="font-size:16px; font-weight:800; color:#0f172a;">📡 Live Field Tracking Radar</span>`,
                html: `<div style="text-align:left; max-height:360px; overflow-y:auto; padding:10px 0;">${listHtml}</div>`,
                confirmButtonText: 'Open Full GPS Radar Map',
                confirmButtonColor: '#0f172a',
                showCloseButton: true
            }).then(res => {
                if (res.isConfirmed) {
                    window.location.href = 'admin_tracking.php';
                }
            });
        });
    }

    // Modal: Executive Leaderboard
    function openLeaderboardModal() {
        Swal.fire({
            title: `<span style="font-size:16px; font-weight:800; color:#0f172a;">🏆 Field Executive Leaderboard</span>`,
            html: `
                <div style="text-align:center; padding:20px 10px;">
                    <div style="font-size:13px; color:#64748b; margin-bottom:16px;">View top field staff rankings by market visits logged & hot leads sourced.</div>
                    <a href="staff_ranking.php" class="btn-mono-solid" style="display:inline-flex; padding:10px 20px; font-size:13px;">View Full Leaderboard Workspace &raquo;</a>
                </div>
            `,
            showConfirmButton: false,
            showCloseButton: true
        });
    }

    // Init Page
    document.addEventListener('DOMContentLoaded', () => {
        loadData(1);
    });
</script>

<?php require_once 'footer.php'; ?>
