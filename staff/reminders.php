<?php
require_once __DIR__ . '/../config.php';

$page_title = "Follow-up Reminders";
$page_subtitle = "Pending CRM Tasks, Call Backs, and Lead Schedules";
require_once __DIR__ . '/header.php';
?>

<div class="view-container">
    <div class="card" style="background: var(--bg-card); border-radius: var(--radius-lg); box-shadow: var(--shadow-md); border: 1px solid #f1f5f9; padding: 24px; margin-bottom: 30px;">
        <div class="card-title-bar" style="border-bottom: 1px solid #f1f5f9; padding-bottom: 15px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
            <h2 style="font-family: 'Outfit', sans-serif; font-weight: 700; font-size: 18px; color: var(--text-primary); margin: 0;">📅 My Follow-up Reminders</h2>
            <div style="background-color: var(--primary-light); color: var(--primary); padding: 6px 12px; border-radius: var(--radius-sm); font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">
                <i data-lucide="bell" style="width: 14px; height: 14px;"></i> Active Schedules
            </div>
        </div>
        
        <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap:15px; margin-bottom:20px; background:var(--bg-main); padding:15px; border-radius:var(--radius-md); border:1px solid var(--border);">
            <div class="form-group" style="margin:0;">
                <label style="font-size:12px; color:var(--text-light); margin-bottom:4px; display:block;">Date Filter</label>
                <select id="filter-date" onchange="loadReminders()" style="width:100%; padding:8px; border-radius:4px; border:1px solid var(--border);">
                    <option value="">All Dates</option>
                    <option value="today">Today</option>
                    <option value="upcoming">Upcoming</option>
                    <option value="overdue">Overdue</option>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label style="font-size:12px; color:var(--text-light); margin-bottom:4px; display:block;">Lead Type</label>
                <select id="filter-type" onchange="loadReminders()" style="width:100%; padding:8px; border-radius:4px; border:1px solid var(--border);">
                    <option value="">All Types</option>
                    <option value="Lead">Lead</option>
                    <option value="pre_lead">Pre-Lead</option>
                </select>
            </div>
        </div>

        <div style="overflow-x: auto; width: 100%;">
            <table class="data-table" id="reminders-table" style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border); color: var(--text-muted); font-weight: 600;">
                        <th style="padding: 12px; width: 25%;">Date & Time</th>
                        <th style="padding: 12px; width: 15%;">Lead Type</th>
                        <th style="padding: 12px; width: 25%;">Client Name / ID</th>
                        <th style="padding: 12px; width: 25%;">Follow-up Notes</th>
                        <th style="padding: 12px; width: 10%; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 30px; color: var(--text-light);">
                            <div style="display: inline-flex; align-items: center; gap: 8px;">
                                <i data-lucide="loader" class="animate-spin" style="width: 16px; height: 16px;"></i> Loading follow-ups...
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
    .data-table tbody tr {
        border-bottom: 1px solid var(--border);
        transition: background-color var(--transition-fast);
    }
    .data-table tbody tr:hover {
        background-color: var(--bg-main);
    }
    .data-table tbody td {
        padding: 14px 12px;
    }
    .animate-spin {
        animation: spin 1s linear infinite;
    }
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
</style>

<script>
    async function loadReminders() {
        try {
            const date = document.getElementById('filter-date')?.value || '';
            const type = document.getElementById('filter-type')?.value || '';
            const params = new URLSearchParams({ api: 'get_reminders', date: date, lead_type: type });
            
            let tbody = document.querySelector("#reminders-table tbody");
            if (tbody) {
                tbody.innerHTML = Array(5).fill(0).map(() => `
                    <tr>
                        <td style="padding:12px;"><div class="skeleton skeleton-text" style="width:120px;height:20px;margin:0;"></div></td>
                        <td style="padding:12px;"><div class="skeleton skeleton-text medium"></div></td>
                        <td style="padding:12px;"><div class="skeleton skeleton-text medium"></div><div class="skeleton skeleton-text short"></div></td>
                        <td style="padding:12px;"><div class="skeleton skeleton-text long"></div></td>
                        <td style="padding:12px;text-align:right;"><div class="skeleton skeleton-text" style="width:100px;height:26px;border-radius:6px;display:inline-block;"></div></td>
                    </tr>
                `).join('');
            }

            let res = await fetch('../api.php?' + params.toString());
            let data = await res.json();
            if (!tbody) return;

            if (data.error) { 
                tbody.innerHTML = `<tr><td colspan='5' style='text-align:center;color:red;padding:20px;'>Failed to load reminders: ${data.error}</td></tr>`; 
                return; 
            }
            if (!Array.isArray(data)) {
                tbody.innerHTML = `<tr><td colspan='5' style='text-align:center;color:red;padding:20px;'>API Error: Expected array, got ${typeof data}</td></tr>`;
                return;
            }
            if (data.length === 0) {
                tbody.innerHTML = `<tr><td colspan='5' style='text-align:center;padding:20px;color:var(--text-light);'>No pending reminders set.</td></tr>`;
                return;
            }
            
            let html = "";
            data.forEach(r => {
                let dateObj = new Date(r.remind_at);
                let isPast = dateObj < new Date() && r.status !== 'Completed';
                let color = isPast ? 'var(--danger)' : 'var(--primary)';
                let pastBadge = isPast ? '<span style="font-size:10px; background:var(--danger-light); color:var(--danger); padding:2px 4px; border-radius:4px; margin-left:6px; font-weight:600;">OVERDUE</span>' : '';
                
                html += `<tr>
                    <td style="color:${color}; font-weight:600; white-space:nowrap;">
                        ${dateObj.toLocaleString()}
                        ${pastBadge}
                    </td>
                    <td style="text-transform: capitalize; font-weight:500;">
                        ${r.lead_type === 'pre_lead' ? 'Pre-Lead' : 'Lead'}
                    </td>
                    <td>
                        <strong>${r.client_name ? r.client_name + ' (#' + r.lead_id + ')' : '<span style="color:var(--danger)">Deleted (#' + r.lead_id + ')</span>'}</strong>
                    </td>
                    <td style="color:var(--text-primary); font-weight:500;">
                        ${r.notes || '<span style="color:var(--text-light); font-style:italic;">No notes specified</span>'}
                    </td>
                    <td style="text-align:right;">
                        <button class="btn btn-secondary" style="padding:4px 8px; font-size:12px; cursor:pointer; margin-right:4px;" onclick="viewClientDetails('${r.lead_type === 'pre_lead' || r.lead_type === 'Pre-Lead' ? 'Pre-Lead' : 'Lead'}', ${r.lead_id})">Details</button>
                        ${r.status === 'Pending' ? `<button class="btn btn-primary" style="padding:4px 8px; font-size:12px; cursor:pointer;" onclick="completeReminder(${r.id})">Mark Done</button>` : ''}
                    </td>
                </tr>`;
            });
            tbody.innerHTML = html;
        } catch(e) {
            console.error(e);
            let tbody = document.querySelector("#reminders-table tbody");
            if (tbody) tbody.innerHTML = `<tr><td colspan='5' style='text-align:center;color:red;padding:20px;'>JS Exception: ${e.message}</td></tr>`;
        }
    }
    
    async function completeReminder(id) {
        const result = await Swal.fire({
            title: 'Complete Reminder',
            text: "Mark this reminder as Completed?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: 'var(--primary)',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'Yes',
            background: 'var(--bg-main)',
            color: 'var(--text-primary)'
        });
        if (!result.isConfirmed) return;
        let fd = new FormData();
        fd.append("api", "complete_reminder");
        fd.append("id", id);
        try {
            let res = await fetch("../api.php?api=complete_reminder", {method:"POST", body:fd});
            let json = await res.json();
            if (json.success) {
                showNotification("Reminder completed successfully", "success");
                loadReminders();
            } else {
                showNotification(json.error || "Failed to complete reminder", "error");
            }
        } catch(e) {
            console.error(e);
            showNotification("Connection error", "error");
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        loadReminders();
    });
</script>

<!-- CLIENT DETAILS MODAL -->
<div id="client-details-modal" class="modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
    <div class="modal-content" style="background:var(--bg-main);width:100%;max-width:900px;border-radius:12px;box-shadow:0 10px 25px rgba(0,0,0,0.2);overflow:hidden;animation:slideDown 0.3s ease;display:flex;flex-direction:column;max-height:90vh;">
        <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
            <h3 style="margin:0;font-size:18px;">👤 Client Full Details</h3>
            <button onclick="document.getElementById('client-details-modal').style.display='none'" style="background:none;border:none;font-size:20px;cursor:pointer;color:var(--text-light);">&times;</button>
        </div>
        
        <div style="display:flex; flex-direction:row; flex:1; overflow:hidden;">
            <!-- LEFT PANEL: Details -->
            <div style="flex:1; padding:20px; border-right:1px solid var(--border); overflow-y:auto; background:#f8fafc;">
                <div id="cd-loading" style="text-align:center; padding:20px; color:var(--text-light);">Loading details...</div>
                <div id="cd-content" style="display:none;">
                    <div style="margin-bottom:20px;">
                        <h2 id="cd-name" style="margin:0; font-size:20px; color:var(--text-primary);">Name</h2>
                        <div id="cd-company" style="font-size:13px; color:var(--text-light); margin-top:4px;">Company</div>
                        <div style="margin-top:12px; display:flex; gap:8px;">
                            <a id="cd-btn-wa" href="#" target="_blank" class="btn btn-secondary" style="padding:4px 8px; font-size:11px; background:#dcfce7; color:#166534; border-color:#bbf7d0; text-decoration:none;" title="WhatsApp">💬 WhatsApp</a>
                            <a id="cd-btn-email" href="#" target="_blank" class="btn btn-secondary" style="padding:4px 8px; font-size:11px; text-decoration:none;" title="Send Email">📧 Email</a>
                        </div>
                    </div>
                    
                    <div class="form-grid" style="grid-template-columns: 1fr; gap:16px;">
                        <div style="background:white; padding:16px; border-radius:8px; border:1px solid var(--border);">
                            <div style="font-size:11px; color:var(--text-light); text-transform:uppercase; font-weight:600; letter-spacing:0.5px;">Contact Info</div>
                            <div style="font-size:14px; margin-top:8px; font-weight:500;">📞 <span id="cd-mobile"></span></div>
                            <div style="font-size:14px; margin-top:4px; font-weight:500; word-break:break-all;">✉️ <span id="cd-email"></span></div>
                        </div>
                        <div style="background:white; padding:12px; border-radius:8px; border:1px solid var(--border);">
                            <div style="font-size:11px; color:var(--text-light); text-transform:uppercase; font-weight:600;">CRM Info</div>
                            <div style="font-size:13px; margin-top:4px;"><strong>Type:</strong> <span id="cd-type"></span></div>
                            <div style="font-size:13px; margin-top:4px;"><strong>Status/Stage:</strong> <span id="cd-stage" class="badge" style="background:#e0e7ff; color:#4338ca;"></span></div>
                            <div style="font-size:13px; margin-top:4px;"><strong>Source:</strong> <span id="cd-source"></span></div>
                            <div style="font-size:13px; margin-top:4px;"><strong>Assigned To:</strong> @<span id="cd-assigned"></span></div>
                        </div>
                        <div style="background:white; padding:12px; border-radius:8px; border:1px solid var(--border); grid-column: 1 / -1;">
                            <div style="font-size:11px; color:var(--text-light); text-transform:uppercase; font-weight:600;">Additional Details</div>
                            <div style="font-size:13px; margin-top:4px;"><strong>Location:</strong> <span id="cd-location"></span></div>
                            <div style="font-size:13px; margin-top:4px;"><strong>Priority:</strong> <span id="cd-priority"></span></div>
                            <div style="font-size:13px; margin-top:4px;"><strong>Created:</strong> <span id="cd-date"></span></div>
                            <div style="font-size:13px; margin-top:8px; padding-top:8px; border-top:1px solid var(--border);"><strong>Notes:</strong> <span id="cd-notes" style="color:var(--text-light);"></span></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- RIGHT PANEL: Call History & Logging -->
            <div style="flex:1.2; display:flex; flex-direction:column; background:white;">
                <div style="padding:15px 20px; border-bottom:1px solid var(--border); background:#f1f5f9;">
                    <h4 style="margin:0; font-size:14px; color:var(--text-primary); display:flex; justify-content:space-between; align-items:center;">
                        Activity & Call Timeline
                        <span id="cd-call-count" style="background:var(--primary); color:white; padding:2px 8px; border-radius:12px; font-size:11px;">0 Activities</span>
                    </h4>
                </div>
                
                <div id="cd-logs-container" style="flex:1; overflow-y:auto; padding:20px; display:flex; flex-direction:column; gap:12px;">
                    <!-- Logs will appear here -->
                </div>
                
                <div id="cd-logs-pagination" style="padding:12px 20px; border-top:1px solid var(--border); display:none; justify-content:space-between; align-items:center; font-size:12px; background:#f8fafc; border-bottom:1px solid var(--border);">
                    <button class="btn btn-secondary" style="padding:6px 12px; font-size:12px; font-weight:500; border-radius:6px;" id="cd-prev-btn" onclick="changeTimelinePage(-1)">← Previous</button>
                    <span id="cd-page-info" style="font-weight:600; color:var(--text-primary); font-size:13px;">Page 1</span>
                    <button class="btn btn-secondary" style="padding:6px 12px; font-size:12px; font-weight:500; border-radius:6px;" id="cd-next-btn" onclick="changeTimelinePage(1)">Next →</button>
                </div>
                
                <div style="padding:15px; border-top:1px solid var(--border); background:#f8fafc;">
                    <div style="font-size:12px; font-weight:600; color:var(--text-light); margin-bottom:8px;">Quick Add Log</div>
                    <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:10px;">
                        <button class="btn btn-secondary" style="font-size:11px; padding:4px 10px;" onclick="addCallLog('No Answer')">No Answer</button>
                        <button class="btn btn-secondary" style="font-size:11px; padding:4px 10px;" onclick="addCallLog('Left Voicemail')">Left Voicemail</button>
                        <button class="btn btn-secondary" style="font-size:11px; padding:4px 10px; background:#dcfce7; color:#166534; border-color:#bbf7d0;" onclick="addCallLog('Interested')">Interested</button>
                        <button class="btn btn-secondary" style="font-size:11px; padding:4px 10px; background:#fee2e2; color:#991b1b; border-color:#fecaca;" onclick="addCallLog('Not Interested')">Not Interested</button>
                    </div>
                    <div style="display:flex; gap:8px;">
                        <input type="text" id="custom-log-input" placeholder="Type custom response..." style="flex:1; padding:8px; border:1px solid var(--border); border-radius:4px; font-size:13px;">
                        <button class="btn btn-primary" onclick="addCallLog(document.getElementById('custom-log-input').value)">Save</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let currentDetailType = '';
    let currentDetailId = 0;
    let currentTimelineLogs = [];
    let currentTimelinePage = 1;
    const TIMELINE_PER_PAGE = 5;

    async function viewClientDetails(type, id) {
        currentDetailType = type;
        currentDetailId = id;
        document.getElementById('client-details-modal').style.display = 'flex';
        document.getElementById('cd-loading').style.display = 'block';
        document.getElementById('cd-content').style.display = 'none';
        document.getElementById('cd-logs-container').innerHTML = '<div style="text-align:center; color:#94a3b8; font-size:12px;">Loading logs...</div>';
        
        try {
            // Load Lead Details
            const res = await fetch(`../api.php?api=get_lead_full_details&lead_type=${type}&lead_id=${id}`);
            const data = await res.json();
            
            if (data.error) {
                document.getElementById('cd-loading').innerText = data.error;
                return;
            }
            
            document.getElementById('cd-loading').style.display = 'none';
            document.getElementById('cd-content').style.display = 'block';
            
            document.getElementById('cd-name').innerText = data.lead_name || data.name || 'Unknown';
            document.getElementById('cd-company').innerText = data.company_name || 'N/A';
            document.getElementById('cd-mobile').innerText = data.mobile || 'N/A';
            document.getElementById('cd-email').innerText = data.email || 'N/A';
            document.getElementById('cd-type').innerText = type;
            document.getElementById('cd-stage').innerText = data.stage || data.status || 'N/A';
            document.getElementById('cd-source').innerText = data.lead_source || data.source || 'N/A';
            document.getElementById('cd-assigned').innerText = data.assigned_to || 'Unassigned';
            
            document.getElementById('cd-location').innerText = data.location || 'N/A';
            document.getElementById('cd-priority').innerText = data.priority || 'N/A';
            document.getElementById('cd-notes').innerText = data.notes || 'No notes available.';
            document.getElementById('cd-date').innerText = new Date(data.created_at).toLocaleString();
            
            const mobileForWa = (data.mobile || '').replace(/\D/g, '');
            document.getElementById('cd-btn-wa').href = mobileForWa ? `https://wa.me/91${mobileForWa}` : '#';
            document.getElementById('cd-btn-wa').style.display = mobileForWa ? 'inline-block' : 'none';
            
            document.getElementById('cd-btn-email').href = data.email ? `send_email.php?email=${encodeURIComponent(data.email)}` : '#';
            document.getElementById('cd-btn-email').style.display = data.email ? 'inline-block' : 'none';
            
            // Load Call Logs
            await loadCallLogs();
            
        } catch(e) {
            console.error(e);
            document.getElementById('cd-loading').innerText = 'Failed to load details.';
        }
    }
    
    async function loadCallLogs() {
        const container = document.getElementById('cd-logs-container');
        if (container) {
            container.innerHTML = `
                <div class="skeleton-row" style="border:none; padding:8px 0;"><div class="skeleton skeleton-avatar" style="width:24px;height:24px;"></div><div style="flex:1"><div class="skeleton skeleton-text medium" style="margin-bottom:4px;"></div><div class="skeleton skeleton-text short"></div></div></div>
                <div class="skeleton-row" style="border:none; padding:8px 0;"><div class="skeleton skeleton-avatar" style="width:24px;height:24px;"></div><div style="flex:1"><div class="skeleton skeleton-text long" style="margin-bottom:4px;"></div><div class="skeleton skeleton-text medium"></div></div></div>
            `;
        }

        try {
            const res = await fetch(`../api.php?api=get_call_logs&lead_type=${currentDetailType}&lead_id=${currentDetailId}`);
            const logs = await res.json();
            
            document.getElementById('cd-call-count').innerText = `${logs.length} Activities`;
            
            currentTimelineLogs = logs;
            currentTimelinePage = 1;
            renderTimelinePage();
        } catch (e) {
            console.error(e);
        }
    }
    
    function changeTimelinePage(dir) {
        currentTimelinePage += dir;
        renderTimelinePage();
    }
    
    function renderTimelinePage() {
        const container = document.getElementById('cd-logs-container');
        if (currentTimelineLogs.length === 0) {
            container.innerHTML = '<div style="text-align:center; color:#94a3b8; font-size:12px; margin-top:20px;">No activity found.</div>';
            document.getElementById('cd-logs-pagination').style.display = 'none';
            return;
        }
        
        const totalPages = Math.ceil(currentTimelineLogs.length / TIMELINE_PER_PAGE);
        if (currentTimelinePage < 1) currentTimelinePage = 1;
        if (currentTimelinePage > totalPages) currentTimelinePage = totalPages;
        
        const start = (currentTimelinePage - 1) * TIMELINE_PER_PAGE;
        const end = start + TIMELINE_PER_PAGE;
        const pageLogs = currentTimelineLogs.slice(start, end);
        
        container.innerHTML = pageLogs.map(log => {
            const d = new Date(log.created_at.replace(' ', 'T') + 'Z');
            const dt = isNaN(d) ? new Date(log.created_at) : d;
            
            if (log.type === 'System') {
                return `
                <div style="background:white; border-left:3px solid #cbd5e1; padding:8px 12px; border-radius:4px; font-size:12px; margin-left:8px; box-shadow:0 1px 2px rgba(0,0,0,0.05);">
                    <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                        <span style="font-weight:600; color:var(--text-primary);">⚙️ System / @${log.caller}</span>
                        <span style="color:var(--text-light); font-size:10px;">${dt.toLocaleString()}</span>
                    </div>
                    <div style="color:var(--text-muted);">${log.response}</div>
                </div>
                `;
            } else {
                return `
                <div style="background:#f8fafc; border:1px solid var(--border); padding:10px 12px; border-radius:8px; font-size:13px;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                        <span style="font-weight:600; color:var(--primary);">📞 @${log.caller}</span>
                        <span style="color:var(--text-light); font-size:11px;">${dt.toLocaleString()}</span>
                    </div>
                    <div style="color:var(--text-primary);">${log.response}</div>
                </div>
                `;
            }
        }).join('');
        
        if (totalPages > 1) {
            document.getElementById('cd-logs-pagination').style.display = 'flex';
            document.getElementById('cd-page-info').innerText = `Page ${currentTimelinePage} of ${totalPages}`;
            document.getElementById('cd-prev-btn').disabled = currentTimelinePage === 1;
            document.getElementById('cd-next-btn').disabled = currentTimelinePage === totalPages;
        } else {
            document.getElementById('cd-logs-pagination').style.display = 'none';
        }
    }
    
    async function addCallLog(response) {
        if (!response || !response.trim()) return;
        
        const result = await Swal.fire({
            title: 'Confirm Action',
            text: `Are you sure you want to log: "${response.trim()}"?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: 'var(--primary)',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'Yes, log it!',
            background: 'var(--bg-main)',
            color: 'var(--text-primary)'
        });
        
        if (!result.isConfirmed) {
            return;
        }
        
        const fd = new FormData();
        fd.append('api', 'add_call_log');
        fd.append('lead_type', currentDetailType);
        fd.append('lead_id', currentDetailId);
        fd.append('response', response.trim());
        
        try {
            document.getElementById('custom-log-input').value = '';
            const res = await fetch('../api.php?api=add_call_log', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                await loadCallLogs(); // Refresh logs immediately
            } else {
                showNotification(data.error || 'Failed to add log', 'error');
            }
        } catch (e) {
            console.error(e);
            showNotification('Error connecting to server', 'error');
        }
    }
</script>

<?php
require_once __DIR__ . '/footer.php';
?>
