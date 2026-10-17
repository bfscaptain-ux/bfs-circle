<?php
require_once __DIR__ . '/../config.php';
$page_title = 'My Leads';
$page_subtitle = '🎯 Capture, track and convert assigned leads into clients';
require_once __DIR__ . '/header.php';
?>

<div id="view-leads" class="view-container">
    <!-- Stats Bar -->
    <div class="stats-grid" style="grid-template-columns: repeat(3,1fr); margin-bottom:1.5rem;">
        <div class="stat-card" style="border-left:4px solid #6366f1;">
            <div class="stat-card-header"><span class="stat-label">Total Leads</span><i data-lucide="target" style="color:#6366f1;width:20px;height:20px;"></i></div>
            <div class="stat-value" id="lead-stat-total">—</div>
        </div>
        <div class="stat-card" style="border-left:4px solid var(--danger);">
            <div class="stat-card-header"><span class="stat-label">Hot Leads</span><i data-lucide="flame" style="color:var(--danger);width:20px;height:20px;"></i></div>
            <div class="stat-value" id="lead-stat-hot">—</div>
        </div>

        <div class="stat-card" style="border-left:4px solid var(--text-muted);">
            <div class="stat-card-header"><span class="stat-label">In Progress</span><i data-lucide="clock" style="color:var(--text-muted);width:20px;height:20px;"></i></div>
            <div class="stat-value" id="lead-stat-progress">—</div>
        </div>
    </div>

    <div style="display:grid; grid-template-columns:1fr; gap:1.5rem; align-items:start;">

        <!-- LEADS TABLE -->
        <div class="card">
            <div class="card-title-bar" style="flex-wrap:wrap; gap:10px;">
                <div style="display:flex; align-items:center; gap: 10px;">
                    <h2 style="margin:0;">📋 My Leads List</h2>
                    <button class="btn btn-secondary" onclick="printLeads()" title="Print Leads Data" style="padding:6px 8px; font-size:12px; display:flex; align-items:center; justify-content:center; height:28px; background:white; border:1px solid #cbd5e1; color:#475569;"><i data-lucide="printer" style="width:14px; height:14px;"></i></button>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap; align-items: center;">
                    <input type="text" id="lead-search" placeholder="Search name/company..." style="padding:6px 12px;border:1px solid var(--border);border-radius:6px;font-size:13px;min-width:150px;" oninput="loadLeads()">
                    <select id="lead-filter-stage" onchange="loadLeads()" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; background-color:#fff;">
                    <option value="">📊 All Stages</option>
                    <option value="New Lead">New Lead</option>
                    <option value="Scheduled">Scheduled 🗓️</option>
                    <option value="Interested">Interested</option>
                    <option value="Not Answered">Not Answered 📵</option>
                    <option value="Rejected">Rejected ❌</option>
                </select>
                    <select id="lead-filter-priority" onchange="loadLeads()" style="padding:6px 10px;border:1px solid var(--border);border-radius:6px;font-size:13px;">
                        <option value="">All Priorities</option>
                        <option value="Hot">🔴 Hot</option>
                        <option value="Warm">🟡 Warm</option>
                        <option value="Cold">🔵 Cold</option>
                    </select>
                </div>
            </div>

            <!-- Pipeline Summary Bar -->
            <div id="lead-pipeline-bar" style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:1rem;padding:12px;background:var(--bg-main);border-radius:8px;"></div>

            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead>
                        <tr style="background:var(--bg-main);color:var(--text-light);font-size:11px;text-transform:uppercase;letter-spacing:0.5px;border-bottom:1px solid var(--border);">
                            <th style="padding:10px 12px;text-align:left;">Contact / Company</th>
                            <th style="padding:10px 12px;text-align:left;">Mobile</th>
                            <th style="padding:10px 12px;text-align:left;">Source</th>
                            <th style="padding:10px 12px;text-align:left;">Priority</th>
                            <th style="padding:10px 12px;text-align:left;">Stage</th>
                            <th style="padding:10px 12px;text-align:left;">Assigned</th>
                            <th style="padding:10px 12px;text-align:left;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="leads-table-body">
                        <tr><td colspan="7" style="padding:30px;text-align:center;color:var(--text-light);">Loading leads...</td></tr>
                    </tbody>
                </table>
            </div>
            <div id="leads-pagination" style="display:flex; justify-content:flex-end; align-items:center; gap:10px; margin-top:20px; padding:10px 15px; border-top:1px solid var(--border);"></div>
        </div>
    </div>
</div>

<!-- QUICK UPDATE MODAL -->
<div id="quick-update-modal" class="modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
    <div class="modal-content" style="background:var(--bg-main);width:100%;max-width:400px;border-radius:12px;box-shadow:0 10px 25px rgba(0,0,0,0.2);overflow:hidden;animation:slideDown 0.3s ease;">
        <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
            <h3 style="margin:0;font-size:16px;">📞 Log Call / Update Stage</h3>
            <button onclick="document.getElementById('quick-update-modal').style.display='none'" style="background:none;border:none;font-size:20px;cursor:pointer;color:var(--text-light);">&times;</button>
        </div>
        <form id="quick-update-form" onsubmit="saveQuickUpdate(event)" style="padding:20px;">
            <input type="hidden" name="lead_id" id="qu-lead-id">
            <input type="hidden" name="lead_name" id="qu-name">
            <input type="hidden" name="company_name" id="qu-company">
            <input type="hidden" name="mobile" id="qu-mobile">
            <input type="hidden" name="email" id="qu-email">
            <input type="hidden" name="lead_source" id="qu-source">
            
            <div style="margin-bottom:15px;background:#f8fafc;padding:12px;border-radius:8px;border:1px solid var(--border);">
                <div style="font-size:12px;color:var(--text-light);margin-bottom:4px;">Contact Details</div>
                <div style="font-weight:600;font-size:14px;color:var(--text-primary);" id="qu-display-name"></div>
                <div style="font-size:13px;color:var(--text-muted);margin-top:2px;">📞 <span id="qu-display-mobile"></span></div>
            </div>

            <div class="form-grid" style="grid-template-columns:1fr 1fr; gap:10px; margin-bottom: 12px;">
                <div class="form-group">
                    <label>Priority</label>
                    <select name="priority" id="qu-priority">
                        <option value="Hot">🔴 Hot</option>
                        <option value="Warm">🟡 Warm</option>
                        <option value="Cold">🔵 Cold</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Stage</label>
                    <select name="stage" id="qu-stage">

                        <option value="Scheduled">Scheduled 🗓️</option>
                        <option value="Interested">Interested</option>
                        <option value="Not Answered">Not Answered 📵</option>
                        <option value="Rejected">Rejected ❌</option>
                    </select>
                </div>
            </div>
            <div class="form-group" style="margin-bottom: 15px;">
                <label>Notes / Remarks</label>
                <textarea name="notes" id="qu-notes" rows="3" placeholder="Enter call summary or remarks..."></textarea>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:10px;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('quick-update-modal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary" id="qu-submit-btn">Save Update</button>
            </div>
        </form>
    </div>
</div>

<script>
    const STAGE_COLORS = {
        'New Lead': '#6366f1',
        'Contacted': '#f59e0b',
        'Scheduled': '#8b5cf6',
        'Interested': '#3b82f6',
        'Not Answered': '#94a3b8',
        'Rejected': '#ef4444'
    };

    const PRIORITY_BADGE = {
        'Hot':  '<span style="background:#fee2e2;color:#ef4444;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;">Hot</span>',
        'Warm': '<span style="background:#fef9c3;color:#ca8a04;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;">Warm</span>',
        'Cold': '<span style="background:#dbeafe;color:#2563eb;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;">Cold</span>'
    };

    let currentLeadsPage = 1;
    async function loadLeads(page = 1) {
        currentLeadsPage = page;
        const search   = document.getElementById('lead-search')?.value || '';
        const stage    = document.getElementById('lead-filter-stage')?.value || '';
        const priority = document.getElementById('lead-filter-priority')?.value || '';
        const params   = new URLSearchParams({ api: 'get_leads', search, stage, priority, page: currentLeadsPage, limit: 20 });

        const tbody = document.getElementById('leads-table-body');
        if (tbody) {
            tbody.innerHTML = Array(5).fill(0).map(() => `
                <tr>
                    <td style="padding:12px;"><div class="skeleton skeleton-text medium"></div><div class="skeleton skeleton-text short"></div></td>
                    <td style="padding:12px;"><div class="skeleton skeleton-text medium"></div></td>
                    <td style="padding:12px;"><div class="skeleton skeleton-text medium"></div></td>
                    <td style="padding:12px;"><div class="skeleton skeleton-text" style="width:50px;height:22px;border-radius:20px;"></div></td>
                    <td style="padding:12px;"><div class="skeleton skeleton-text" style="width:70px;height:22px;border-radius:20px;"></div></td>
                    <td style="padding:12px;"><div class="skeleton skeleton-text medium"></div><div class="skeleton skeleton-text short"></div></td>
                    <td style="padding:12px;"><div class="skeleton skeleton-text" style="width:60px;height:26px;border-radius:6px;"></div></td>
                </tr>
            `).join('');
        }

        try {
            const res = await fetch('../api.php?' + params.toString());
            if (!res.ok) return;
            const resData = await res.json();
            
            // Check if it's the new format or old array format (fallback)
            const leads = resData.leads ? resData.leads : (Array.isArray(resData) ? resData : []);
            window.allLeadsData = leads;
            const stageCounts = resData.stage_counts || {};
            const stats = resData.stats || {};

            // Update stat cards
            const total    = stats.total !== undefined ? stats.total : leads.length;
            const hot      = stats.hot !== undefined ? stats.hot : leads.filter(l => l.priority === 'Hot' && l.stage !== 'Won' && l.stage !== 'Lost').length;
            const won      = stats.won !== undefined ? stats.won : leads.filter(l => l.stage === 'Won').length;
            const progress = stats.progress !== undefined ? stats.progress : leads.filter(l => l.stage !== 'Won' && l.stage !== 'Lost').length;

            const setEl = (id, v) => { const el = document.getElementById(id); if(el) el.innerText = v; };
            setEl('lead-stat-total',    total);
            setEl('lead-stat-hot',      hot);
            setEl('lead-stat-won',      won);
            setEl('lead-stat-progress', progress);

            // Pipeline Bar counts
            if (!resData.stage_counts && leads.length > 0) {
                // Fallback if API hasn't been updated yet
                leads.forEach(l => { stageCounts[l.stage] = (stageCounts[l.stage] || 0) + 1; });
            }
            const pipelineBar = document.getElementById('lead-pipeline-bar');
            if (pipelineBar) {
                const stages = ['New Lead','Scheduled','Interested','Not Answered','Rejected'];
                pipelineBar.innerHTML = stages.map(s => {
                    const cnt = stageCounts[s] || 0;
                    const col = STAGE_COLORS[s];
                    return `<div onclick="document.getElementById('lead-filter-stage').value='${s}'; loadLeads();"
                        style="flex:1;min-width:80px;text-align:center;padding:8px 6px;border-radius:8px;background:${col}18;border:1px solid ${col}44;cursor:pointer;transition:all .2s;"
                        onmouseover="this.style.background='${col}33'" onmouseout="this.style.background='${col}18'">
                        <div style="font-size:18px;font-weight:700;color:${col};">${cnt}</div>
                        <div style="font-size:10px;color:${col};font-weight:600;">${s}</div>
                    </div>`;
                }).join('');
            }

            // Table rows
            if (!tbody) return;
            if (leads.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" style="padding:40px;text-align:center;color:var(--text-light);">No leads found. Add your first lead using the form!</td></tr>';
                return;
            }
            tbody.innerHTML = leads.map(l => {
                const col = STAGE_COLORS[l.stage] || '#64748b';
                return `<tr style="border-bottom:1px solid var(--border);transition:background .15s;" onmouseover="this.style.background='rgba(0,0,0,0.02)'" onmouseout="this.style.background=''">
                    <td style="padding:12px;">
                        <div style="font-weight:600;color:var(--text-primary);">${l.lead_name}</div>
                        <div style="font-size:11px;color:var(--text-light);">${l.company_name || '—'}</div>
                    </td>
                    <td style="padding:12px;font-size:13px;">${l.mobile}</td>
                    <td style="padding:12px;font-size:12px;color:var(--text-light);">${l.lead_source}</td>
                    <td style="padding:12px;">${PRIORITY_BADGE[l.priority] || l.priority}</td>
                    <td style="padding:12px;">
                        <span style="padding:3px 8px;border-radius:20px;font-size:12px;font-weight:600;background:${col}20;color:${col};display:inline-block;text-align:center;">
                            ${l.stage}
                        </span>
                    </td>
                    <td style="padding:12px;">
                        ${(() => {
                            if (!l.assigned_at) return '<span style="color:var(--text-light);font-size:12px;">—</span>';
                            const d = new Date(l.assigned_at.replace(' ', 'T') + 'Z');
                            const localDate = isNaN(d) ? new Date(l.assigned_at) : d;
                            const diffMs = new Date() - localDate;
                            if (diffMs < 0) return `<div style="font-size:12px;">${localDate.toLocaleString()}</div>`;
                            const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
                            const diffHrs = Math.floor((diffMs % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                            const diffMins = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));
                            let timeAgo = '';
                            if (diffDays > 0) timeAgo = diffDays + 'd ' + diffHrs + 'h ago';
                            else if (diffHrs > 0) timeAgo = diffHrs + 'h ' + diffMins + 'm ago';
                            else timeAgo = diffMins + 'm ago';
                            
                            return `
                                <div style="font-size:12px;color:var(--text-primary);">${localDate.toLocaleString()}</div>
                                <div style="font-size:11px;color:var(--text-light);font-weight:600;margin-top:2px;">⏱️ ${timeAgo}</div>
                            `;
                        })()}
                    </td>
                    <td style="padding:12px;">
                        <div style="display:flex;gap:6px;">
                            <button class="btn btn-secondary" style="padding:4px 10px;font-size:11px;" onclick="editLead(${l.id})" title="Log Call / Update">✏️ Update</button>
                            <button class="btn btn-secondary" style="padding:4px 10px;font-size:11px;" onclick="openReminderModal('Lead', ${l.id})" title="Set Reminder">⏰</button>
                        </div>
                    </td>
                </tr>`;
            }).join('');
            
            // Render Pagination
            const paginationContainer = document.getElementById('leads-pagination');
            if (paginationContainer && resData.pagination) {
                const p = resData.pagination;
                let html = `<div style="font-size:13px; color:var(--text-light); margin-right:auto;">Showing ${leads.length} of ${p.total_records} records</div>`;
                
                if (p.total_pages > 1) {
                    html += `<button class="btn btn-secondary" style="padding:4px 10px;font-size:12px;" onclick="loadLeads(${p.current_page - 1})" ${p.current_page <= 1 ? 'disabled' : ''}>Previous</button>`;
                    html += `<span style="font-size:13px; font-weight:600;">Page ${p.current_page} of ${p.total_pages}</span>`;
                    html += `<button class="btn btn-secondary" style="padding:4px 10px;font-size:12px;" onclick="loadLeads(${p.current_page + 1})" ${p.current_page >= p.total_pages ? 'disabled' : ''}>Next</button>`;
                }
                paginationContainer.innerHTML = html;
            }
            
            lucide.createIcons();
        } catch(e) {
            console.error(e);
        }
    }

    async function saveQuickUpdate(e) {
        e.preventDefault();
        const form = e.target;
        showConfirm(async () => {
            const fd = new FormData(form);
            fd.append('action', 'save_lead');
            try {
                const btn = document.getElementById('qu-submit-btn');
                const origText = btn.innerText;
                btn.innerText = 'Saving...';
                btn.disabled = true;

                const res = await fetch('../api.php?api=save_lead', { method:'POST', body:fd });
                const data = await res.json();
                
                btn.innerText = origText;
                btn.disabled = false;

                if(data.success) {
                    showNotification(data.message, 'success');
                    document.getElementById('quick-update-modal').style.display = 'none';
                    loadLeads();
                    
                    // Automatic Workflow Triggers
                    const stage = fd.get('stage');
                    if (stage === 'Interested') {
                        convertToClient(data.id);
                    } else if (stage === 'Scheduled') {
                        setTimeout(() => {
                            openReminderModal('Lead', data.id);
                            showNotification('Lead Scheduled! Please set a reminder for the call.', 'info');
                        }, 500);
                    } else if (stage === 'Rejected') {
                        showNotification('Lead marked as Rejected and paused.', 'error');
                    }
                } else {
                    showNotification(data.error || 'Failed to save lead', 'error');
                }
            } catch(e) {
                showNotification('Network connection error', 'error');
            }
        }, 'Confirm Update', 'Are you sure you want to save changes to this lead?', 'Yes, Save');
    }

    function editLead(id) {
        fetch(`../api.php?api=get_lead_detail&id=${id}`)
        .then(r => r.json())
        .then(l => {
            document.getElementById('qu-lead-id').value = l.id;
            document.getElementById('qu-name').value = l.lead_name;
            document.getElementById('qu-company').value = l.company_name || '';
            document.getElementById('qu-mobile').value = l.mobile;
            document.getElementById('qu-email').value = l.email || '';
            document.getElementById('qu-source').value = l.lead_source || 'Cold Call';
            
            document.getElementById('qu-display-name').innerText = l.lead_name + (l.company_name ? ` (${l.company_name})` : '');
            document.getElementById('qu-display-mobile').innerText = l.mobile;
            
            document.getElementById('qu-priority').value = l.priority || 'Warm';
            document.getElementById('qu-stage').value = l.stage || 'New Lead';
            document.getElementById('qu-notes').value = l.notes || '';
            
            document.getElementById('quick-update-modal').style.display = 'flex';
        });
    }


    function convertToClient(id) {
        fetch(`../api.php?api=get_lead_detail&id=${id}`)
        .then(r => r.json())
        .then(l => {
            sessionStorage.setItem('convert_lead_data', JSON.stringify(l));
            location.href = 'add_client.php';
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        loadLeads();
    });

    function printLeads() {
        const data = window.allLeadsData || [];
        if (data.length === 0) {
            alert('No data to print.');
            return;
        }

        let printWindow = window.open('', '_blank');
        let html = `
        <html>
        <head>
            <title>Leads Data</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 13px; }
                th { background-color: #f4f4f4; }
                h2 { text-align: center; }
                @media print {
                    @page { size: landscape; }
                }
            </style>
        </head>
        <body>
            <h2>Leads Report</h2>
            <p>Total Records: ${data.length}</p>
            <table>
                <thead>
                    <tr>
                        <th>Lead Name</th>
                        <th>Company</th>
                        <th>Mobile</th>
                        <th>Source</th>
                        <th>Priority</th>
                        <th>Stage</th>
                        <th>Assigned To</th>
                        <th>Created At</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
        `;

        data.forEach(l => {
            html += `
                <tr>
                    <td>${l.lead_name || ''}</td>
                    <td>${l.company_name || ''}</td>
                    <td>${l.mobile || ''}</td>
                    <td>${l.lead_source || ''}</td>
                    <td>${l.priority || ''}</td>
                    <td>${l.stage || ''}</td>
                    <td>${l.assigned_to || 'Unassigned'}</td>
                    <td>${new Date(l.created_at).toLocaleDateString()}</td>
                    <td>${l.notes || ''}</td>
                </tr>
            `;
        });

        html += `
                </tbody>
            </table>
            <script>
                window.onload = function() { window.print(); window.close(); }
            <\/script>
        </body>
        </html>
        `;

        printWindow.document.write(html);
        printWindow.document.close();
    }
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
