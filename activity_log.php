<?php
require_once __DIR__ . '/config.php';

// Restrict audit trails to Admin
require_role(['Admin'], false);


$role = $_SESSION['role'] ?? '';
$current_username = $_SESSION['username'] ?? '';
$page_title = "Activity & Audit Logs";
$page_subtitle = $role === 'Admin' ? "System-wide real-time audit trails and user activity logs" : "Your recent activity history";
$hide_main_header = true;
require_once __DIR__ . '/header.php';
?>

<style>
    /* Monochrome Corporate Theme & Card Styling */
    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }
    .kpi-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 18px 20px;
        box-shadow: 0 4px 6px -1px rgba(15, 23, 42, 0.03);
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .kpi-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 15px -3px rgba(15, 23, 42, 0.08);
    }
    .kpi-val {
        font-size: 24px;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.2;
    }
    .kpi-label {
        font-size: 12px;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-top: 4px;
    }
    .kpi-icon {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        background: #f8fafc;
        border: 1px solid #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #0f172a;
    }

    .filter-panel {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 12px 16px;
        margin-bottom: 24px;
        box-shadow: 0 4px 6px -1px rgba(15, 23, 42, 0.03);
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: nowrap !important;
        overflow-x: auto;
        width: 100%;
        box-sizing: border-box;
    }
    
    .filter-search-wrapper {
        flex: 2;
        min-width: 220px;
        position: relative;
    }

    .filter-search-input {
        width: 100% !important;
        height: 38px;
        padding: 0 12px 0 36px !important;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        font-size: 12.5px;
        font-weight: 600;
        color: #0f172a;
        background: #ffffff;
        outline: none;
        box-sizing: border-box;
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    .filter-search-input:focus {
        border-color: #0f172a !important;
        box-shadow: 0 0 0 3px rgba(15, 23, 42, 0.08);
    }

    .filter-select-item {
        height: 38px;
        width: auto !important;
        max-width: 190px;
        flex: 1;
        min-width: 130px;
        padding: 0 28px 0 12px !important;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        font-size: 12.5px;
        font-weight: 600;
        color: #0f172a;
        background: #ffffff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23475569' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E") no-repeat right 10px center;
        appearance: none;
        -webkit-appearance: none;
        outline: none;
        cursor: pointer;
        box-sizing: border-box;
        transition: border-color 0.2s, box-shadow 0.2s;
        white-space: nowrap;
        text-overflow: ellipsis;
    }
    .filter-select-item:focus {
        border-color: #0f172a !important;
        box-shadow: 0 0 0 3px rgba(15, 23, 42, 0.08);
    }

    .audit-table-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.03);
        overflow: hidden;
    }
    .audit-table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
        font-size: 13px;
    }
    .audit-table th {
        background: #f8fafc;
        color: #475569;
        font-weight: 700;
        font-size: 11.5px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 14px 18px;
        border-bottom: 1px solid #e2e8f0;
    }
    .audit-table td {
        padding: 14px 18px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
        color: #334155;
    }
    .audit-table tbody tr {
        transition: background-color 0.15s;
    }
    .audit-table tbody tr:hover {
        background-color: #f8fafc;
    }

    .user-avatar-badge {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: #0f172a;
        color: #ffffff;
        font-weight: 800;
        font-size: 13px;
        display: flex;
        align-items: center;
        justify-content: center;
        text-transform: uppercase;
        flex-shrink: 0;
    }
    
    .role-badge {
        display: inline-block;
        padding: 2px 7px;
        border-radius: 10px;
        font-size: 10.5px;
        font-weight: 700;
        text-transform: uppercase;
    }
    .role-admin { background: #0f172a; color: #ffffff; }
    .role-rm { background: #e0e7ff; color: #3730a3; }
    .role-staff { background: #f1f5f9; color: #334155; }
    .role-system { background: #fef2f2; color: #991b1b; }

    .category-tag {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 11.5px;
        font-weight: 700;
    }

    .btn-mono-black {
        background: #0f172a;
        color: #ffffff;
        border: none;
        padding: 9px 16px;
        border-radius: 8px;
        font-weight: 700;
        font-size: 12.5px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: background 0.2s;
    }
    .btn-mono-black:hover { background: #1e293b; }

    .btn-mono-outline {
        background: #ffffff;
        color: #0f172a;
        border: 1px solid #cbd5e1;
        padding: 9px 16px;
        border-radius: 8px;
        font-weight: 700;
        font-size: 12.5px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: background 0.2s, border-color 0.2s;
    }
    .btn-mono-outline:hover { background: #f8fafc; border-color: #0f172a; }

    .pagination-bar {
        padding: 16px 20px;
        background: #ffffff;
        border-top: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
    }
</style>

<div class="view-container">
    <!-- Header Section -->
    <div style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <div>
            <h1 style="font-size: 22px; font-weight: 800; color: #0f172a; margin: 0 0 4px 0; display: flex; align-items: center; gap: 10px;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="color: #0f172a;"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
                <?= htmlspecialchars($page_title) ?>
            </h1>
            <p style="margin: 0; color: #64748b; font-size: 13px; font-weight: 600;"><?= htmlspecialchars($page_subtitle) ?></p>
        </div>
        <div style="display: flex; gap: 10px;">
            <button class="btn-mono-outline" onclick="exportActivityLogsCSV()" title="Export Filtered Logs to CSV">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                Export Audit CSV
            </button>
            <button class="btn-mono-black" onclick="loadFullActivityLogs(1)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" id="refresh-icon"><path d="M23 4v6h-6"></path><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg>
                Refresh Audit Logs
            </button>
        </div>
    </div>

    <!-- Analytics KPIs -->
    <div class="kpi-grid">
        <div class="kpi-card">
            <div>
                <div class="kpi-val" id="kpi-total-logs">--</div>
                <div class="kpi-label">Total Audit Logs</div>
            </div>
            <div class="kpi-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
            </div>
        </div>

        <div class="kpi-card">
            <div>
                <div class="kpi-val" id="kpi-today-logs">--</div>
                <div class="kpi-label">Actions Today</div>
            </div>
            <div class="kpi-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
            </div>
        </div>

        <div class="kpi-card">
            <div>
                <div class="kpi-val" id="kpi-fin-logs">--</div>
                <div class="kpi-label">Financial & Payout Logs</div>
            </div>
            <div class="kpi-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
            </div>
        </div>

        <div class="kpi-card">
            <div>
                <div class="kpi-val" id="kpi-users-count">--</div>
                <div class="kpi-label">Logging Users</div>
            </div>
            <div class="kpi-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
            </div>
        </div>
    </div>

    <!-- Advanced Single-Line Controls & Filter Panel -->
    <div class="filter-panel">
        <div class="filter-search-wrapper">
            <input type="text" id="log_search_input" class="filter-search-input" placeholder="Search by Keyword, Customer, Loan ID, User..." oninput="onSearchInputDebounce()">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="position: absolute; left: 12px; top: 12px; color: #94a3b8;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
        </div>

        <?php if ($role === 'Admin'): ?>
        <select id="log_user_select" class="filter-select-item" onchange="loadFullActivityLogs(1)">
            <option value="">👤 All System Users</option>
        </select>
        <?php endif; ?>

        <select id="log_category_select" class="filter-select-item" onchange="loadFullActivityLogs(1)">
            <option value="">🏷️ All Action Categories</option>
            <option value="financial">🟢 Financial & Payouts</option>
            <option value="client">🔵 Client & Lead CRM</option>
            <option value="documents">🟣 Document Management</option>
            <option value="attendance">🟡 Attendance & HR</option>
            <option value="system">🔴 System & Security</option>
        </select>

        <select id="log_date_select" class="filter-select-item" style="max-width: 140px;" onchange="loadFullActivityLogs(1)">
            <option value="">📅 All Time</option>
            <option value="today">Today</option>
            <option value="yesterday">Yesterday</option>
            <option value="week">Last 7 Days</option>
            <option value="month">Last 30 Days</option>
        </select>

        <select id="log_limit_select" class="filter-select-item" style="max-width: 110px; min-width: 90px;" onchange="loadFullActivityLogs(1)">
            <option value="25">25 / page</option>
            <option value="50" selected>50 / page</option>
            <option value="100">100 / page</option>
        </select>

        <button type="button" class="btn-mono-outline" style="height: 38px; padding: 0 14px; font-size: 12.5px; white-space: nowrap; flex-shrink: 0;" onclick="resetLogFilters()" title="Reset All Filters">
            Reset
        </button>
    </div>

    <!-- Activity Audit Table Card -->
    <div class="audit-table-card">
        <div class="table-responsive">
            <table class="audit-table">
                <thead>
                    <tr>
                        <th style="width: 220px;">User / Operator</th>
                        <th style="width: 180px;">Category</th>
                        <th>Action Audit Trail Details</th>
                        <th style="width: 180px;">Date & Time</th>
                        <th style="width: 120px; text-align: center;">Action Link</th>
                    </tr>
                </thead>
                <tbody id="full-activity-logs-tbody">
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 40px; color: #64748b;">
                            <div style="display: inline-flex; align-items: center; gap: 8px; font-weight: 700;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="animate-spin"><line x1="12" y1="2" x2="12" y2="6"></line><line x1="12" y1="18" x2="12" y2="22"></line><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"></line><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"></line><line x1="2" y1="12" x2="6" y2="12"></line><line x1="18" y1="12" x2="22" y2="12"></line><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"></line><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"></line></svg>
                                Loading audit trail records...
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination Controls -->
        <div class="pagination-bar">
            <div style="font-size: 13px; font-weight: 700; color: #475569;" id="pagination-summary">
                Showing 0 to 0 of 0 logs
            </div>
            <div style="display: flex; gap: 8px; align-items: center;" id="pagination-controls">
                <button class="btn-mono-outline" id="btn-prev-page" onclick="changeLogPage(-1)" disabled style="padding: 6px 14px; font-size: 12px;">← Prev</button>
                <span style="font-size: 12.5px; font-weight: 700; color: #0f172a; padding: 0 6px;" id="page-indicator">Page 1 of 1</span>
                <button class="btn-mono-outline" id="btn-next-page" onclick="changeLogPage(1)" disabled style="padding: 6px 14px; font-size: 12px;">Next →</button>
            </div>
        </div>
    </div>
</div>

<style>
    .animate-spin {
        animation: spin 1s linear infinite;
    }
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
</style>

<script>
    let currentLogPage = 1;
    let totalLogPages = 1;
    let currentLogsData = [];
    let searchDebounceTimer = null;

    function onSearchInputDebounce() {
        clearTimeout(searchDebounceTimer);
        searchDebounceTimer = setTimeout(() => {
            loadFullActivityLogs(1);
        }, 300);
    }

    function resetLogFilters() {
        document.getElementById('log_search_input').value = '';
        if (document.getElementById('log_user_select')) document.getElementById('log_user_select').value = '';
        document.getElementById('log_category_select').value = '';
        document.getElementById('log_date_select').value = '';
        document.getElementById('log_limit_select').value = '50';
        loadFullActivityLogs(1);
    }

    function changeLogPage(delta) {
        let targetPage = currentLogPage + delta;
        if (targetPage >= 1 && targetPage <= totalLogPages) {
            loadFullActivityLogs(targetPage);
        }
    }

    async function loadFullActivityLogs(page = 1) {
        currentLogPage = page;
        const tbody = document.getElementById('full-activity-logs-tbody');
        const refreshIcon = document.getElementById('refresh-icon');
        if (refreshIcon) refreshIcon.classList.add('animate-spin');

        if (tbody) {
            tbody.innerHTML = `<tr><td colspan="5" style="text-align: center; padding: 30px; color: #64748b;">
                <div style="display: inline-flex; align-items: center; gap: 8px; font-weight: 700;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="animate-spin"><line x1="12" y1="2" x2="12" y2="6"></line><line x1="12" y1="18" x2="12" y2="22"></line><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"></line><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"></line><line x1="2" y1="12" x2="6" y2="12"></line><line x1="18" y1="12" x2="22" y2="12"></line><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"></line><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"></line></svg>
                    Fetching audit trail logs...
                </div>
            </td></tr>`;
        }

        const search = encodeURIComponent(document.getElementById('log_search_input').value);
        const user = document.getElementById('log_user_select') ? encodeURIComponent(document.getElementById('log_user_select').value) : '';
        const category = encodeURIComponent(document.getElementById('log_category_select').value);
        const dateRange = encodeURIComponent(document.getElementById('log_date_select').value);
        const limit = document.getElementById('log_limit_select').value;

        try {
            const response = await fetch(`api.php?api=get_activity_logs&page=${page}&limit=${limit}&search=${search}&username=${user}&category=${category}&date_range=${dateRange}`);
            const data = await response.json();

            if (refreshIcon) refreshIcon.classList.remove('animate-spin');

            if (!data.success && data.error) {
                if (tbody) tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; color:#dc2626; padding:30px; font-weight:700;">Error: ${data.error}</td></tr>`;
                return;
            }

            currentLogsData = data.logs || [];
            totalLogPages = data.total_pages || 1;

            // Populate Users Dropdown if empty
            const userSelect = document.getElementById('log_user_select');
            if (userSelect && userSelect.options.length <= 1 && data.users_list) {
                data.users_list.forEach(u => {
                    let opt = document.createElement('option');
                    opt.value = u.username;
                    opt.textContent = `👤 ${u.name || u.username} (${u.role || 'User'})`;
                    userSelect.appendChild(opt);
                });
            }

            // Update KPI Cards
            if (data.summary) {
                document.getElementById('kpi-total-logs').innerText = Number(data.summary.total_all || 0).toLocaleString();
                document.getElementById('kpi-today-logs').innerText = Number(data.summary.today_count || 0).toLocaleString();
                document.getElementById('kpi-fin-logs').innerText = Number(data.summary.financial_count || 0).toLocaleString();
                document.getElementById('kpi-users-count').innerText = Number(data.summary.unique_users || 0).toLocaleString();
            }

            // Update Pagination Bar
            let startRow = (data.page - 1) * data.limit + 1;
            let endRow = Math.min(data.page * data.limit, data.total_count);
            if (data.total_count === 0) { startRow = 0; endRow = 0; }

            document.getElementById('pagination-summary').innerText = `Showing ${startRow.toLocaleString()} to ${endRow.toLocaleString()} of ${data.total_count.toLocaleString()} audit records`;
            document.getElementById('page-indicator').innerText = `Page ${data.page} of ${data.total_pages || 1}`;
            
            document.getElementById('btn-prev-page').disabled = (data.page <= 1);
            document.getElementById('btn-next-page').disabled = (data.page >= data.total_pages);

            if (currentLogsData.length === 0) {
                if (tbody) tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; padding:40px; color:#64748b; font-weight:700;">No activity logs found matching the selected filters.</td></tr>`;
                return;
            }

            let html = '';
            currentLogsData.forEach(act => {
                let initial = (act.display_name || act.username || 'S').charAt(0).toUpperCase();
                let roleClass = 'role-staff';
                let roleLabel = act.user_role || 'Staff';
                if (roleLabel.toLowerCase() === 'admin') roleClass = 'role-admin';
                else if (roleLabel.toLowerCase() === 'rm') roleClass = 'role-rm';
                else if (roleLabel.toLowerCase() === 'system') roleClass = 'role-system';

                let actionLinkBtn = '-';
                if (act.action_link) {
                    let targetUrl = act.action_link.replace('staff_hrms.php', 'employees_list.php');
                    actionLinkBtn = `<a href="${targetUrl}" class="btn-mono-outline" style="padding:4px 10px; font-size:11px; text-decoration:none; white-space:nowrap;">View Details ↗</a>`;
                }

                html += `<tr>
                    <td>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div class="user-avatar-badge">${initial}</div>
                            <div>
                                <div style="font-weight:800; color:#0f172a; font-size:13px;">${act.display_name}</div>
                                <div style="display:flex; align-items:center; gap:6px; margin-top:2px;">
                                    <span style="font-size:11px; color:#64748b; font-weight:600;">@${act.username}</span>
                                    <span class="role-badge ${roleClass}">${roleLabel}</span>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="category-tag" style="background:${act.category_bg}; color:${act.category_color}; border:1px solid ${act.category_color}22;">
                            ${act.category}
                        </span>
                    </td>
                    <td style="font-weight:600; color:#1e293b; line-height:1.5;">
                        ${act.description}
                    </td>
                    <td>
                        <div style="font-family:monospace; font-size:12px; font-weight:700; color:#0f172a;">${act.created_at_formatted}</div>
                        <div style="font-size:11px; color:#64748b; font-weight:600; margin-top:2px;">⏱ ${act.time_ago}</div>
                    </td>
                    <td style="text-align:center;">
                        ${actionLinkBtn}
                    </td>
                </tr>`;
            });

            if (tbody) tbody.innerHTML = html;

        } catch (err) {
            console.error(err);
            if (refreshIcon) refreshIcon.classList.remove('animate-spin');
            if (tbody) tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; color:#dc2626; padding:30px; font-weight:700;">Server Connection Error while loading logs.</td></tr>`;
        }
    }

    function exportActivityLogsCSV() {
        if (!currentLogsData || currentLogsData.length === 0) {
            alert('No log data available to export.');
            return;
        }
        let csv = 'Log ID,User,Role,Category,Description,Action Link,Date Time\n';
        currentLogsData.forEach(r => {
            let descEsc = `"${(r.description || '').replace(/"/g, '""')}"`;
            csv += `${r.id},"${r.display_name} (${r.username})","${r.user_role}","${r.category}",${descEsc},"${r.action_link || ''}","${r.created_at_formatted}"\n`;
        });

        let blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        let link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.setAttribute('download', `activity_audit_logs_${new Date().toISOString().slice(0,10)}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    document.addEventListener("DOMContentLoaded", () => {
        loadFullActivityLogs(1);
    });
</script>

<?php
require_once __DIR__ . '/footer.php';
?>
