<?php
require_once 'config.php';
require_role(['Admin'], false);

$page_title = 'Staff Productivity & Intelligence Command Center';
$page_subtitle = 'Executive surveillance of daily staff performance, hourly activity heatmaps, & real-time audit ledger';
$hide_main_header = true;
require_once 'header.php';
?>

<div class="view-container" style="font-family:'Outfit',sans-serif;">
    <!-- TOP EXECUTIVE METRICS BANNER -->
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:16px; margin-bottom:24px;">
        <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:18px; box-shadow:0 1px 3px rgba(0,0,0,0.02); display:flex; align-items:center; gap:14px;">
            <div style="width:44px; height:44px; border-radius:10px; background:#0f172a; color:#ffffff; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <i data-lucide="users" style="width:22px; height:22px;"></i>
            </div>
            <div>
                <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;">Monitored Officers</div>
                <div style="font-size:22px; font-weight:800; color:#0f172a;" id="summary-total-staff">--</div>
            </div>
        </div>

        <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:18px; box-shadow:0 1px 3px rgba(0,0,0,0.02); display:flex; align-items:center; gap:14px;">
            <div style="width:44px; height:44px; border-radius:10px; background:#0f172a; color:#ffffff; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <i data-lucide="zap" style="width:22px; height:22px;"></i>
            </div>
            <div>
                <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;">Total Actions Logged</div>
                <div style="font-size:22px; font-weight:800; color:#0f172a;" id="summary-total-actions">--</div>
            </div>
        </div>

        <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:18px; box-shadow:0 1px 3px rgba(0,0,0,0.02); display:flex; align-items:center; gap:14px;">
            <div style="width:44px; height:44px; border-radius:10px; background:#0f172a; color:#ffffff; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <i data-lucide="clock" style="width:22px; height:22px;"></i>
            </div>
            <div>
                <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;">Cum. Shift Hours</div>
                <div style="font-size:22px; font-weight:800; color:#0f172a;" id="summary-total-hours">--</div>
            </div>
        </div>

        <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:18px; box-shadow:0 1px 3px rgba(0,0,0,0.02); display:flex; align-items:center; gap:14px;">
            <div style="width:44px; height:44px; border-radius:10px; background:#0f172a; color:#ffffff; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <i data-lucide="award" style="width:22px; height:22px;"></i>
            </div>
            <div style="overflow:hidden;">
                <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;">Top Performer Today</div>
                <div style="font-size:15px; font-weight:800; color:#0f172a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" id="summary-top-performer">--</div>
            </div>
        </div>
    </div>

    <!-- MAIN TOOLBAR & CONTROLS -->
    <div style="background:#ffffff; border:1px solid #e2e8f0; padding:16px 20px; border-radius:14px; box-shadow:0 1px 3px rgba(0,0,0,0.02); margin-bottom:24px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px;">
        <div style="display:flex; align-items:center; gap:14px;">
            <div>
                <h2 style="font-size:17px; font-weight:800; color:#0f172a; margin:0 0 2px;">Productivity & Audit Ledger</h2>
                <p style="font-size:12.5px; color:#64748b; margin:0;">Real-time hourly heatmaps & activity logs for every employee.</p>
            </div>
        </div>

        <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
            <!-- VIEW SWITCHER SEGMENT -->
            <div style="display:inline-flex; background:#f1f5f9; padding:3px; border-radius:10px; border:1px solid #cbd5e1;">
                <button id="view-btn-cards" onclick="switchProductivityView('cards')" style="padding:6px 14px; border-radius:7px; border:none; font-size:12px; font-weight:800; cursor:pointer; background:#0f172a; color:#ffffff; display:flex; align-items:center; gap:5px;">
                    <i data-lucide="grid" style="width:13px;"></i> Grid Cards
                </button>
                <button id="view-btn-table" onclick="switchProductivityView('table')" style="padding:6px 14px; border-radius:7px; border:none; font-size:12px; font-weight:800; cursor:pointer; background:transparent; color:#64748b; display:flex; align-items:center; gap:5px;">
                    <i data-lucide="table" style="width:13px;"></i> Leaderboard Table
                </button>
            </div>

            <!-- DATE QUICK SELECTORS -->
            <div style="display:flex; align-items:center; gap:6px;">
                <button onclick="setQuickDate('today')" style="background:#f8fafc; border:1px solid #cbd5e1; color:#0f172a; padding:7px 12px; border-radius:8px; font-size:12px; font-weight:700; cursor:pointer;">Today</button>
                <button onclick="setQuickDate('yesterday')" style="background:#f8fafc; border:1px solid #cbd5e1; color:#0f172a; padding:7px 12px; border-radius:8px; font-size:12px; font-weight:700; cursor:pointer;">Yesterday</button>
                <input type="date" id="activity-date" value="<?php echo date('Y-m-d'); ?>" onchange="loadProductivity()" style="padding:7px 12px; border-radius:8px; border:1px solid #cbd5e1; font-size:12.5px; font-weight:700; color:#0f172a; outline:none;">
            </div>

            <!-- EXPORT AUDIT REPORT BUTTON -->
            <button onclick="exportProductivityCSV()" style="background:#0f172a; color:#ffffff; border:none; padding:8px 14px; border-radius:8px; font-size:12px; font-weight:800; cursor:pointer; display:flex; align-items:center; gap:6px;">
                <i data-lucide="download" style="width:13px;"></i> Export CSV Report
            </button>
        </div>
    </div>

    <!-- MAIN DASHBOARD CONTENT AREA (CARDS vs TABLE + LIVE STREAM DRAWER) -->
    <div style="display:grid; grid-template-columns: 1fr 320px; gap:20px; align-items:start;" id="dashboard-main-layout">
        
        <!-- LEFT PANEL: GRID CARDS OR LEADERBOARD TABLE -->
        <div>
            <!-- CARDS GRID VIEW -->
            <div id="productivity-grid" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap:20px;">
                <!-- Loaded via JS -->
            </div>

            <!-- LEADERBOARD TABLE VIEW -->
            <div id="productivity-table-container" style="display:none; background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.02);">
                <table style="width:100%; border-collapse:collapse; text-align:left; font-size:13px;">
                    <thead>
                        <tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0; font-size:11px; font-weight:800; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;">
                            <th style="padding:14px 16px;">Rank & Staff</th>
                            <th style="padding:14px 12px;">Shift Status</th>
                            <th style="padding:14px 12px; text-align:center;">Leads</th>
                            <th style="padding:14px 12px; text-align:center;">Docs</th>
                            <th style="padding:14px 12px; text-align:center;">Banks</th>
                            <th style="padding:14px 12px; text-align:center;">Disbursal</th>
                            <th style="padding:14px 12px; text-align:center;">Total Actions</th>
                            <th style="padding:14px 12px; text-align:center;">Productivity</th>
                            <th style="padding:14px 16px; text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="productivity-table-body">
                        <!-- Loaded via JS -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- RIGHT PANEL: REAL-TIME LIVE ACTIVITY STREAM FEED -->
        <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:18px; box-shadow:0 1px 3px rgba(0,0,0,0.02); position:sticky; top:20px;">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px; padding-bottom:10px; border-bottom:1px solid #f1f5f9;">
                <div style="display:flex; align-items:center; gap:8px;">
                    <span style="width:8px; height:8px; border-radius:50%; background:#22c55e; animation:livePulseDot 2s infinite;"></span>
                    <h3 style="margin:0; font-size:14px; font-weight:800; color:#0f172a; text-transform:uppercase; letter-spacing:0.5px;">Live Action Feed</h3>
                </div>
                <span style="font-size:11px; color:#64748b; font-weight:700;">Real-time</span>
            </div>

            <div id="live-stream-container" style="display:flex; flex-direction:column; gap:12px; max-height:650px; overflow-y:auto; padding-right:4px;">
                <!-- Live stream items loaded via JS -->
            </div>
        </div>
    </div>
</div>

<!-- DETAILED ACTIVITY TIMELINE MODAL -->
<div id="timeline-modal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(15,23,42,0.65); backdrop-filter:blur(6px); z-index:100005; align-items:center; justify-content:center;">
    <div style="background:#ffffff; width:92%; max-width:680px; height:85vh; border-radius:20px; border:1px solid #e2e8f0; box-shadow:0 25px 50px -12px rgba(15,23,42,0.35); display:flex; flex-direction:column; overflow:hidden; font-family:'Outfit',sans-serif;">
        <!-- MODAL HEADER -->
        <div style="padding:20px 24px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; background:#f8fafc;">
            <div>
                <h3 id="timeline-title" style="margin:0; font-size:18px; font-weight:800; color:#0f172a;">Staff Activity Timeline</h3>
                <p id="timeline-subtitle" style="margin:4px 0 0; font-size:12.5px; color:#64748b;">Step-by-step history of recorded actions</p>
            </div>
            <button onclick="closeTimeline()" style="background:none; border:none; color:#64748b; cursor:pointer; padding:6px; border-radius:50%; transition:all 0.2s;" onmouseover="this.style.color='#0f172a'" onmouseout="this.style.color='#64748b'">
                <i data-lucide="x" style="width:20px; height:20px;"></i>
            </button>
        </div>

        <!-- CATEGORY FILTER TABS -->
        <div style="padding:10px 24px; border-bottom:1px solid #e2e8f0; background:#ffffff; display:flex; gap:8px; overflow-x:auto;">
            <button class="t-filter-btn active" onclick="filterTimelineCat('All', this)">All</button>
            <button class="t-filter-btn" onclick="filterTimelineCat('Leads & Applicants', this)">Leads</button>
            <button class="t-filter-btn" onclick="filterTimelineCat('Documents', this)">Docs</button>
            <button class="t-filter-btn" onclick="filterTimelineCat('Bank Submissions', this)">Banks</button>
            <button class="t-filter-btn" onclick="filterTimelineCat('Field Visits', this)">Visits</button>
            <button class="t-filter-btn" onclick="filterTimelineCat('Attendance', this)">Shift Logs</button>
        </div>

        <!-- MODAL BODY TIMELINE -->
        <div style="flex:1; overflow-y:auto; padding:24px; background:#ffffff;">
            <div id="timeline-container" class="mono-timeline">
                <!-- Timeline items loaded via JS -->
            </div>
        </div>

        <!-- MODAL FOOTER -->
        <div style="padding:14px 24px; border-top:1px solid #e2e8f0; background:#f8fafc; text-align:right;">
            <button onclick="closeTimeline()" style="padding:9px 18px; border-radius:8px; border:1px solid #cbd5e1; background:#ffffff; color:#0f172a; font-weight:700; font-size:12.5px; cursor:pointer;">Close</button>
        </div>
    </div>
</div>

<style>
@keyframes livePulseDot {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7); }
    70% { transform: scale(1.05); box-shadow: 0 0 0 8px rgba(34, 197, 94, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
}

.t-filter-btn {
    padding: 6px 14px;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #64748b;
    font-size: 11.5px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
    white-space: nowrap;
}
.t-filter-btn.active, .t-filter-btn:hover {
    background: #0f172a;
    color: #ffffff;
    border-color: #0f172a;
}

.mono-prod-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    display: flex;
    flex-direction: column;
}
.mono-prod-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 24px -6px rgba(15, 23, 42, 0.08);
    border-color: #cbd5e1;
}

.heat-bar-col {
    flex: 1;
    height: 24px;
    background: #f1f5f9;
    border-radius: 4px;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    overflow: hidden;
    position: relative;
}
.heat-bar-fill {
    width: 100%;
    background: #0f172a;
    border-radius: 2px;
    transition: height 0.3s ease;
}

.mono-timeline {
    position: relative;
    padding-left: 28px;
}
.mono-timeline::before {
    content: '';
    position: absolute;
    left: 8px;
    top: 6px;
    bottom: 6px;
    width: 2px;
    background: #e2e8f0;
}
.m-timeline-item {
    position: relative;
    margin-bottom: 20px;
}
.m-timeline-item::before {
    content: '';
    position: absolute;
    left: -24px;
    top: 10px;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #0f172a;
    box-shadow: 0 0 0 3px #ffffff, 0 0 0 5px #e2e8f0;
}
</style>

<script>
let currentProductivityData = [];
let currentTimelineData = [];

function switchProductivityView(mode) {
    const cardsGrid = document.getElementById('productivity-grid');
    const tableContainer = document.getElementById('productivity-table-container');
    const btnCards = document.getElementById('view-btn-cards');
    const btnTable = document.getElementById('view-btn-table');

    if (mode === 'cards') {
        cardsGrid.style.display = 'grid';
        tableContainer.style.display = 'none';
        btnCards.style.background = '#0f172a';
        btnCards.style.color = '#ffffff';
        btnTable.style.background = 'transparent';
        btnTable.style.color = '#64748b';
    } else {
        cardsGrid.style.display = 'none';
        tableContainer.style.display = 'block';
        btnTable.style.background = '#0f172a';
        btnTable.style.color = '#ffffff';
        btnCards.style.background = 'transparent';
        btnCards.style.color = '#64748b';
    }
}

function setQuickDate(type) {
    const picker = document.getElementById('activity-date');
    const today = new Date();
    if (type === 'today') {
        picker.value = today.toISOString().split('T')[0];
    } else if (type === 'yesterday') {
        today.setDate(today.getDate() - 1);
        picker.value = today.toISOString().split('T')[0];
    }
    loadProductivity();
}

async function loadProductivity() {
    const date = document.getElementById('activity-date').value;
    const grid = document.getElementById('productivity-grid');
    const tbody = document.getElementById('productivity-table-body');
    const liveStreamContainer = document.getElementById('live-stream-container');

    grid.innerHTML = '<div style="grid-column: 1 / -1; text-align:center; padding: 60px; color: #64748b; font-weight:700;">Loading command center data...</div>';
    tbody.innerHTML = '<tr><td colspan="9" style="text-align:center; padding:40px; color:#64748b;">Loading productivity table...</td></tr>';
    liveStreamContainer.innerHTML = '<div style="text-align:center; color:#64748b; padding:20px; font-size:12px;">Loading live activity stream...</div>';

    try {
        const res = await fetch(`api.php?api=get_employee_productivity&date=${date}`);
        const response = await res.json();
        
        grid.innerHTML = '';
        tbody.innerHTML = '';

        currentProductivityData = (response && response.staff) ? response.staff : [];
        const liveStream = (response && response.live_stream) ? response.live_stream : [];

        if (currentProductivityData.length === 0) {
            grid.innerHTML = `
                <div style="grid-column: 1 / -1; background: #ffffff; text-align:center; padding: 60px; border-radius: 16px; border: 1px solid #e2e8f0; color: #64748b;">
                    <i data-lucide="coffee" style="width: 48px; height: 48px; margin-bottom: 16px; color:#94a3b8;"></i>
                    <h3 style="color:#0f172a; margin:0 0 6px; font-weight:800;">No Staff Activity Found</h3>
                    <p style="margin:0; font-size:13px;">No recorded actions or working hours found for ${date}.</p>
                </div>`;
            if (window.lucide) lucide.createIcons();
            return;
        }

        // Calculate Overall Summary Stats
        let totalStaffCount = currentProductivityData.length;
        let totalActionsCount = 0;
        let totalMinsWorked = 0;
        let topPerformer = '--';
        let topScore = -1;

        currentProductivityData.forEach((user, idx) => {
            totalActionsCount += (user.total_actions || 0);
            totalMinsWorked += (user.working_minutes || 0);

            if (user.productivity_score > topScore) {
                topScore = user.productivity_score;
                topPerformer = `${user.full_name} (${user.productivity_score}%)`;
            }

            const initials = user.full_name ? user.full_name.split(' ').map(n=>n[0]).join('').substring(0,2).toUpperCase() : user.username.substring(0,2).toUpperCase();
            
            // Status Badge HTML
            let dutyBadgeHTML = '';
            if (user.is_live) {
                dutyBadgeHTML = `<span style="display:inline-flex; align-items:center; gap:5px; background:#f0fdf4; color:#166534; border:1px solid #bbf7d0; font-size:10.5px; font-weight:800; padding:3px 8px; border-radius:20px;"><span style="width:6px; height:6px; border-radius:50%; background:#22c55e;"></span> ON DUTY (${user.working_hours})</span>`;
            } else if (user.att_status === 'Shift Completed') {
                dutyBadgeHTML = `<span style="display:inline-flex; align-items:center; gap:5px; background:#f0f9ff; color:#0369a1; border:1px solid #bae6fd; font-size:10.5px; font-weight:800; padding:3px 8px; border-radius:20px;"><i data-lucide="check" style="width:11px;"></i> COMPLETED (${user.working_hours})</span>`;
            } else {
                dutyBadgeHTML = `<span style="display:inline-flex; align-items:center; gap:5px; background:#f8fafc; color:#64748b; border:1px solid #e2e8f0; font-size:10.5px; font-weight:700; padding:3px 8px; border-radius:20px;">OFF DUTY</span>`;
            }

            const scoreColor = user.productivity_score >= 80 ? '#0f172a' : (user.productivity_score >= 50 ? '#334155' : '#64748b');

            // Build Hourly Activity Heatmap HTML (9 AM - 6 PM)
            let heatMapHTML = '';
            const hoursList = ['09','10','11','12','13','14','15','16','17','18'];
            let maxHourVal = 1;
            hoursList.forEach(h => { if ((user.hourly_map[h]||0) > maxHourVal) maxHourVal = user.hourly_map[h]; });

            hoursList.forEach(h => {
                const count = user.hourly_map[h] || 0;
                const pct = Math.min(100, Math.round((count / maxHourVal) * 100));
                const label = (parseInt(h) > 12) ? `${parseInt(h)-12}P` : `${parseInt(h)}A`;
                heatMapHTML += `
                    <div class="heat-bar-col" title="${label}: ${count} actions">
                        <div class="heat-bar-fill" style="height:${pct}%;"></div>
                        <div style="font-size:8px; color:#94a3b8; text-align:center; position:absolute; bottom:1px; width:100%; font-weight:700;">${label}</div>
                    </div>
                `;
            });

            // RENDER GRID CARD
            const card = document.createElement('div');
            card.className = 'mono-prod-card';
            card.innerHTML = `
                <!-- CARD HEADER -->
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:14px; padding-bottom:12px; border-bottom:1px solid #f1f5f9;">
                    <div style="display:flex; align-items:center; gap:12px;">
                        <div style="width:42px; height:42px; border-radius:12px; background:#0f172a; color:#ffffff; font-weight:800; font-size:14px; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                            ${initials}
                        </div>
                        <div>
                            <div style="font-size:15px; font-weight:800; color:#0f172a;">${user.full_name}</div>
                            <div style="font-size:11.5px; color:#64748b; font-weight:600;">${user.designation} • <span style="color:#0f172a;">${user.emp_code}</span></div>
                        </div>
                    </div>
                    ${dutyBadgeHTML}
                </div>

                <!-- PRODUCTIVITY SCORE BAR -->
                <div style="margin-bottom:14px; background:#f8fafc; border:1px solid #f1f5f9; padding:10px 14px; border-radius:10px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; font-size:11.5px; margin-bottom:6px;">
                        <span style="font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;">Productivity Index</span>
                        <span style="font-weight:800; color:${scoreColor};">${user.activity_badge} (${user.productivity_score}%)</span>
                    </div>
                    <div style="height:6px; background:#e2e8f0; border-radius:10px; overflow:hidden;">
                        <div style="height:100%; width:${user.productivity_score}%; background:${scoreColor}; border-radius:10px; transition:width 0.5s ease;"></div>
                    </div>
                </div>

                <!-- HOURLY ACTIVITY HEATMAP -->
                <div style="margin-bottom:14px; background:#f8fafc; border:1px solid #f1f5f9; padding:10px; border-radius:10px;">
                    <div style="font-size:10px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px;">Hourly Activity Distribution (9 AM - 6 PM)</div>
                    <div style="display:flex; gap:4px; align-items:flex-end;">
                        ${heatMapHTML}
                    </div>
                </div>

                <!-- 8 GRANULAR METRICS GRID -->
                <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:8px; margin-bottom:14px;">
                    <div style="background:#f8fafc; border:1px solid #f1f5f9; padding:8px 6px; border-radius:8px; text-align:center;">
                        <div style="font-size:9.5px; font-weight:700; color:#64748b; text-transform:uppercase;">Leads</div>
                        <div style="font-size:15px; font-weight:800; color:#0f172a; margin-top:2px;">${user.applicants_added}</div>
                    </div>
                    <div style="background:#f8fafc; border:1px solid #f1f5f9; padding:8px 6px; border-radius:8px; text-align:center;">
                        <div style="font-size:9.5px; font-weight:700; color:#64748b; text-transform:uppercase;">Docs</div>
                        <div style="font-size:15px; font-weight:800; color:#0f172a; margin-top:2px;">${user.documents_uploaded}</div>
                    </div>
                    <div style="background:#f8fafc; border:1px solid #f1f5f9; padding:8px 6px; border-radius:8px; text-align:center;">
                        <div style="font-size:9.5px; font-weight:700; color:#64748b; text-transform:uppercase;">Banks</div>
                        <div style="font-size:15px; font-weight:800; color:#0f172a; margin-top:2px;">${user.banks_assigned}</div>
                    </div>
                    <div style="background:#f8fafc; border:1px solid #f1f5f9; padding:8px 6px; border-radius:8px; text-align:center;">
                        <div style="font-size:9.5px; font-weight:700; color:#64748b; text-transform:uppercase;">Disbursal</div>
                        <div style="font-size:15px; font-weight:800; color:#0f172a; margin-top:2px;">${user.disbursements_processed}</div>
                    </div>
                </div>

                <!-- SHIFT TIMINGS SUMMARY -->
                <div style="display:flex; justify-content:space-between; align-items:center; font-size:11.5px; color:#64748b; padding:8px 12px; background:#f8fafc; border-radius:8px; border:1px solid #f1f5f9; margin-bottom:14px;">
                    <div>In: <strong style="color:#0f172a;">${user.check_in_time}</strong> • Out: <strong style="color:#0f172a;">${user.check_out_time}</strong></div>
                    <div>Actions: <strong style="color:#0f172a;">${user.total_actions}</strong></div>
                </div>

                <!-- ACTION FOOTER BUTTONS -->
                <div style="display:flex; gap:8px; margin-top:auto;">
                    <button onclick="openTimeline('${user.username}', '${date}', '${user.full_name}')" style="flex:1; padding:9px; border-radius:10px; border:1px solid #cbd5e1; background:#ffffff; color:#0f172a; font-weight:800; font-size:11.5px; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:5px; transition:all 0.2s;" onmouseover="this.style.background='#0f172a'; this.style.color='#ffffff';" onmouseout="this.style.background='#ffffff'; this.style.color='#0f172a';">
                        <i data-lucide="list" style="width:13px; height:13px;"></i> View Timeline
                    </button>
                    <a href="https://wa.me/?text=Hi%20${encodeURIComponent(user.full_name)},%20please%20update%20your%20today%27s%20work%20activity%20logs." target="_blank" title="Send WhatsApp Alert" style="background:#f8fafc; border:1px solid #cbd5e1; color:#166534; padding:9px 12px; border-radius:10px; display:flex; align-items:center; justify-content:center; text-decoration:none;">
                        <i data-lucide="message-square" style="width:14px; height:14px;"></i>
                    </a>
                </div>
            `;
            grid.appendChild(card);

            // RENDER TABLE ROW
            const tr = document.createElement('tr');
            tr.style.borderBottom = '1px solid #f1f5f9';
            tr.innerHTML = `
                <td style="padding:14px 16px;">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span style="font-weight:800; color:#64748b; font-size:12px;">#${idx+1}</span>
                        <div style="width:34px; height:34px; border-radius:8px; background:#0f172a; color:#ffffff; font-weight:800; font-size:12px; display:flex; align-items:center; justify-content:center;">${initials}</div>
                        <div>
                            <strong style="color:#0f172a;">${user.full_name}</strong>
                            <div style="font-size:11px; color:#64748b;">${user.designation} (${user.emp_code})</div>
                        </div>
                    </div>
                </td>
                <td style="padding:14px 12px;">${dutyBadgeHTML}</td>
                <td style="padding:14px 12px; text-align:center; font-weight:800; color:#0f172a;">${user.applicants_added}</td>
                <td style="padding:14px 12px; text-align:center; font-weight:800; color:#0f172a;">${user.documents_uploaded}</td>
                <td style="padding:14px 12px; text-align:center; font-weight:800; color:#0f172a;">${user.banks_assigned}</td>
                <td style="padding:14px 12px; text-align:center; font-weight:800; color:#0f172a;">${user.disbursements_processed}</td>
                <td style="padding:14px 12px; text-align:center; font-weight:800; color:#0f172a;">${user.total_actions}</td>
                <td style="padding:14px 12px; text-align:center;">
                    <span style="font-weight:800; color:${scoreColor};">${user.productivity_score}%</span>
                </td>
                <td style="padding:14px 16px; text-align:right;">
                    <button onclick="openTimeline('${user.username}', '${date}', '${user.full_name}')" style="background:#0f172a; color:#ffffff; border:none; padding:6px 12px; border-radius:6px; font-weight:700; font-size:11px; cursor:pointer;">
                        Timeline
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        });

        // Update Summary Top Banner
        const cumH = Math.floor(totalMinsWorked / 60);
        const cumM = totalMinsWorked % 60;
        document.getElementById('summary-total-staff').innerText = `${totalStaffCount} Officers`;
        document.getElementById('summary-total-actions').innerText = `${totalActionsCount} Actions`;
        document.getElementById('summary-total-hours').innerText = `${cumH}h ${cumM}m`;
        document.getElementById('summary-top-performer').innerText = topPerformer;

        // Render Live Activity Stream Feed (Right Panel)
        liveStreamContainer.innerHTML = '';
        if (liveStream.length === 0) {
            liveStreamContainer.innerHTML = '<div style="text-align:center; color:#94a3b8; padding:30px; font-size:12px;">No activity stream today</div>';
        } else {
            liveStream.forEach(st => {
                const sHtml = `
                    <div style="background:#f8fafc; border:1px solid #f1f5f9; padding:10px 12px; border-radius:10px; font-size:12px; color:#0f172a; line-height:1.3;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                            <strong style="color:#0f172a; font-size:11.5px;">${st.username}</strong>
                            <span style="font-size:10px; color:#64748b; font-weight:700;">${st.formatted_time}</span>
                        </div>
                        <div style="color:#475569; font-size:12px;">${st.description}</div>
                    </div>
                `;
                liveStreamContainer.innerHTML += sHtml;
            });
        }

        if (window.lucide) lucide.createIcons();
    } catch (err) {
        console.error(err);
        grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:40px; color:#ef4444; font-weight:700;">Failed to load staff productivity stats.</div>';
    }
}

async function openTimeline(username, date, fullName) {
    document.getElementById('timeline-title').innerText = `Activity Timeline: ${fullName || username}`;
    document.getElementById('timeline-subtitle').innerText = `Full audit log of recorded actions on ${date}`;
    document.getElementById('timeline-modal').style.display = 'flex';
    
    const container = document.getElementById('timeline-container');
    container.innerHTML = '<div style="text-align:center; color:#64748b; padding:40px; font-weight:700;">Loading detailed action timeline...</div>';
    
    try {
        const res = await fetch(`api.php?api=get_employee_activity_timeline&username=${username}&date=${date}`);
        const response = await res.json();
        
        currentTimelineData = (response && response.activities) ? response.activities : [];
        renderTimelineItems(currentTimelineData);
    } catch (err) {
        console.error(err);
        container.innerHTML = '<div style="text-align:center; color:#ef4444; padding:40px;">Failed to load timeline history.</div>';
    }
}

function filterTimelineCat(category, btnEl) {
    document.querySelectorAll('.t-filter-btn').forEach(b => b.classList.remove('active'));
    if (btnEl) btnEl.classList.add('active');

    if (category === 'All') {
        renderTimelineItems(currentTimelineData);
    } else {
        const filtered = currentTimelineData.filter(item => item.category === category);
        renderTimelineItems(filtered);
    }
}

function renderTimelineItems(items) {
    const container = document.getElementById('timeline-container');
    container.innerHTML = '';
    
    if (!items || items.length === 0) {
        container.innerHTML = `
            <div style="text-align:center; color:#64748b; padding:40px;">
                <i data-lucide="file-x" style="width:36px; height:36px; margin-bottom:12px; opacity:0.4;"></i>
                <div style="font-weight:700; color:#0f172a;">No Activity Logs Found</div>
                <div style="font-size:12px; margin-top:4px;">No actions recorded for this filter category.</div>
            </div>`;
        if (window.lucide) lucide.createIcons();
        return;
    }
    
    items.forEach(act => {
        const html = `
            <div class="m-timeline-item">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                    <span style="font-size:11px; font-weight:800; color:#0f172a;">${act.formatted_time}</span>
                    <span style="font-size:10px; font-weight:700; color:#64748b; background:#f1f5f9; padding:2px 8px; border-radius:12px; border:1px solid #e2e8f0;">${act.category}</span>
                </div>
                <div style="background:#f8fafc; border:1px solid #e2e8f0; padding:12px 14px; border-radius:10px; font-size:13px; color:#0f172a; line-height:1.4;">
                    <i data-lucide="${act.icon || 'activity'}" style="width:14px; height:14px; vertical-align:middle; margin-right:6px; color:#0f172a;"></i>
                    <span>${act.description}</span>
                    ${act.action_link ? `<div style="margin-top:6px;"><a href="${act.action_link}" style="font-size:11px; font-weight:700; color:#0f172a; text-decoration:underline;">View Detail →</a></div>` : ''}
                </div>
            </div>
        `;
        container.innerHTML += html;
    });
    
    if (window.lucide) lucide.createIcons();
}

function exportProductivityCSV() {
    if (!currentProductivityData || currentProductivityData.length === 0) {
        showNotification('No data available to export.', 'error');
        return;
    }
    const date = document.getElementById('activity-date').value;
    let csv = `Date,Staff Username,Full Name,Role,Emp Code,Status,Working Hours,Leads,Docs,Banks,Disbursals,Total Actions,Score Pct\n`;
    
    currentProductivityData.forEach(u => {
        csv += `"${date}","${u.username}","${u.full_name}","${u.role}","${u.emp_code}","${u.att_status}","${u.working_hours}",${u.applicants_added},${u.documents_uploaded},${u.banks_assigned},${u.disbursements_processed},${u.total_actions},${u.productivity_score}%\n`;
    });

    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `Staff_Productivity_Report_${date}.csv`;
    a.click();
    window.URL.revokeObjectURL(url);
    showNotification('Productivity CSV Report downloaded!', 'success');
}

function closeTimeline() {
    document.getElementById('timeline-modal').style.display = 'none';
}

document.addEventListener('DOMContentLoaded', () => {
    loadProductivity();
});
</script>

<?php require_once 'footer.php'; ?>
