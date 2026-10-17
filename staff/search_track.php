<?php
require_once __DIR__ . '/../config.php';
$page_title = 'Search & Tracking Dashboard';
$page_subtitle = 'Interactive CRM conversion tracker and client card catalog';
require_once __DIR__ . '/header.php';
?>

<div id="view-search-crm" class="view-container">
    <!-- Search bar -->
    <div class="crm-search-bar">
        <div class="search-input-wrapper">
            <i data-lucide="search" class="search-icon"></i>
            <input type="text" id="search-query" placeholder="Type Client Company Name / GSTIN / Contact Name / Email / City..." oninput="triggerSearch()">
        </div>
        <button class="filters-toggle-btn" onclick="toggleFilterDrawer()"><i data-lucide="sliders-horizontal" style="margin-right: 6px;"></i> Filters</button>
    </div>

    <!-- Filters Drawer -->
    <div class="filters-drawer" id="crm-filters-drawer">
        <div class="form-group">
            <label>By Status</label>
            <select id="filter-status" onchange="triggerSearch()">
                <option value="">All Statuses</option>
                <option value="New">🔵 New</option>
                <option value="Contacted">🟠 Contacted</option>
                <option value="In Negotiation">🟡 In Negotiation</option>
                <option value="Closed Won">🟢 Closed Won</option>
                <option value="Closed Lost">🔴 Closed Lost</option>
            </select>
        </div>
        <div class="form-group">
            <label>By Priority</label>
            <select id="filter-priority" onchange="triggerSearch()">
                <option value="">All Priorities</option>
                <option value="Hot">Hot</option>
                <option value="Warm">Warm</option>
                <option value="Cold">Cold</option>
            </select>
        </div>
        <div class="form-group">
            <label>By City</label>
            <select id="filter-city" onchange="triggerSearch()">
                <option value="">All Cities</option>
            </select>
        </div>
        <div class="form-group">
            <label>Registered Start</label>
            <input type="date" id="filter-date-start" onchange="triggerSearch()">
        </div>
        <div class="form-group">
            <label>Registered End</label>
            <input type="date" id="filter-date-end" onchange="triggerSearch()">
        </div>
    </div>

    <!-- CRM Layout Grid -->
    <div class="crm-layout">
        <!-- Left panel: Card Lists -->
        <div class="client-list-pane" id="crm-client-list">
            <!-- Loaded dynamically -->
        </div>

        <!-- Right panel: Live Tracker Details -->
        <div class="client-detail-pane" id="crm-detail-pane">
            <div class="detail-placeholder">
                <i data-lucide="eye" style="width: 64px; height: 64px; stroke-width: 1.5;"></i>
                <div>
                    <h3>No Client Selected</h3>
                    <p style="font-size: 13.5px; margin-top: 6px;">Select a client from the search results to view their CRM history, communications, and quotation statuses.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let crmClientList = [];

    function toggleFilterDrawer() {
        document.getElementById('crm-filters-drawer').classList.toggle('active');
    }

    async function loadFilterOptions() {
        try {
            const res = await fetch('api.php?api=get_unique_filters');
            const data = await res.json();
            
            const citySelect = document.getElementById('filter-city');
            citySelect.innerHTML = '<option value="">All Cities</option>';
            data.cities.forEach(c => {
                citySelect.innerHTML += `<option value="${c}">${c}</option>`;
            });
        } catch (err) {
            console.error('Filter synchronization failure', err);
        }
    }

    async function triggerSearch() {
        const query = document.getElementById('search-query').value;
        const status = document.getElementById('filter-status').value;
        const priority = document.getElementById('filter-priority').value;
        const city = document.getElementById('filter-city').value;
        const dateStart = document.getElementById('filter-date-start').value;
        const dateEnd = document.getElementById('filter-date-end').value;
        
        const params = new URLSearchParams({
            api: 'search_clients',
            query: query,
            status: status,
            priority: priority,
            city: city,
            date_start: dateStart,
            date_end: dateEnd
        });
        
        try {
            const response = await fetch('../api.php?' + params.toString());
            const clients = await response.json();
            
            if (clients && clients.error === 'SESSION_EXPIRED') {
                window.location.href = '../login.php';
                return;
            }

            if (!Array.isArray(clients)) {
                console.error('API Error:', clients);
                const container = document.getElementById('crm-client-list');
                container.innerHTML = `<div style="background: white; border: 1px solid #fee2e2; text-align: center; color: #dc2626; padding: 40px; border-radius: var(--radius-md);">${clients.error || 'Failed to load records.'}</div>`;
                return;
            }
            crmClientList = clients;
            
            const CELEBRATED_KEY = 'crm_celebrated_clients';
            let celebratedClients = JSON.parse(localStorage.getItem(CELEBRATED_KEY) || '[]');
            let didCelebrate = false;
            
            const container = document.getElementById('crm-client-list');
            container.innerHTML = '';
            
            if (clients.length === 0) {
                container.innerHTML = '<div style="background: white; border: 1px solid #e2e8f0; text-align: center; color: var(--text-light); padding: 40px; border-radius: var(--radius-md);">No records match criteria.</div>';
                return;
            }
            
            clients.forEach(c => {
                let isAllCompleted = c.pitch_sent && c.ppt_shared && c.quotation_sent && c.custom_mail_sent;
                if (isAllCompleted && !celebratedClients.includes(c.id)) {
                    celebratedClients.push(c.id);
                    didCelebrate = true;
                }
                const card = document.createElement('div');
                card.className = 'client-card';
                card.id = 'crm-client-card-' + c.id;
                card.onclick = () => selectClientCard(c.id);
                
                const priorityClass = 'badge-' + c.priority.toLowerCase();
                const statusClass = c.overall_status.toLowerCase().replace(' ', '');
                
                if (c.overall_status === 'Closed Won') {
                    card.classList.add('won');
                    card.style.borderLeft = '4px solid var(--status-won)';
                    card.style.background = 'linear-gradient(to right, var(--status-won-light) 0%, #fff 20%)';
                }
                
                card.innerHTML = `
                    <div class="client-card-header">
                        <span class="client-card-title">${c.company_name}</span>
                        <span class="badge ${priorityClass}">${c.priority}</span>
                    </div>
                    <div class="client-card-meta">
                        <i data-lucide="user" style="width: 12px; height: 12px;"></i>
                        <span>${c.contact_name} — ${c.designation || 'Client Contact'}</span>
                    </div>
                    <div class="client-card-meta">
                        <i data-lucide="phone" style="width: 12px; height: 12px;"></i>
                        <span>${c.mobile}</span>
                    </div>
                    <div class="client-card-meta">
                        <i data-lucide="map-pin" style="width: 12px; height: 12px;"></i>
                        <span>${c.city}, ${c.state}</span>
                    </div>
                    <div style="display:flex; justify-content: space-between; align-items: center; margin-top: 12px; border-top: 1px dashed #e2e8f0; padding-top: 8px;">
                        <span class="badge-status ${statusClass}">${c.overall_status}</span>
                        <span style="font-size: 11px; color: var(--text-muted);">Added: ${formatDateShort(c.created_at)}</span>
                    </div>
                `;
                container.appendChild(card);
            });
            
            if (didCelebrate) {
                localStorage.setItem(CELEBRATED_KEY, JSON.stringify(celebratedClients));
                setTimeout(() => {
                    if (typeof confetti === 'function') {
                        confetti({
                            particleCount: 200,
                            spread: 90,
                            origin: { y: 0.6 },
                            zIndex: 9999,
                            colors: ['#26ccff', '#a25afd', '#ff5e7e', '#88ff5a', '#fcff42', '#ffa62d', '#ff36ff']
                        });
                    }
                    showNotification(`🎉 Incredible! Client Pipeline fully completed!`, 'success');
                }, 500);
            }

            lucide.createIcons();
            
            if (clients.length > 0 && !document.querySelector('.client-card.selected')) {
                selectClientCard(clients[0].id);
            }
        } catch (err) {
            console.error(err);
            showNotification('CRM Search request failed: ' + err.message, 'error');
        }
    }

    async function selectClientCard(id) {
        document.querySelectorAll('.client-card').forEach(el => el.classList.remove('selected'));
        
        const selectedCard = document.getElementById('crm-client-card-' + id);
        if (selectedCard) selectedCard.classList.add('selected');
        
        try {
            const response = await fetch(`api.php?api=client_details&id=${id}`);
            const c = await response.json();
            
            const pane = document.getElementById('crm-detail-pane');
            pane.innerHTML = '';
            
            if (c.error) {
                pane.innerHTML = `<div style="color:red; text-align:center; padding:40px;">${c.error}</div>`;
                return;
            }
            
            const pitchDate = c.pitch_sent ? formatDate(c.pitch_sent) : 'Not Sent';
            const pptDate = c.ppt_sent ? formatDate(c.ppt_sent) : 'Not Shared';
            const mailDate = c.mail_sent ? formatDate(c.mail_sent) : 'Not Sent';
            const quoteDate = c.quotation_sent ? formatDate(c.quotation_sent) : 'No Quotation';
            
            const stepsClassList = {
                pitch: c.pitch_sent ? 'completed' : '',
                ppt: c.ppt_sent ? 'completed' : '',
                mail: c.mail_sent ? 'completed' : '',
                quote: c.quotation_sent ? 'completed' : ''
            };
            
            if (c.overall_status === 'Contacted') stepsClassList.pitch = 'active';
            if (c.overall_status === 'In Negotiation') stepsClassList.quote = 'active';
            
            let quotesHtml = '';
            if (c.quotations && c.quotations.length > 0) {
                quotesHtml = c.quotations.map(q => {
                    const amt = formatIndianCurrency(q.total_amount);
                    return `
                        <div style="display:flex; justify-content: space-between; align-items:center; background:#f8fafc; padding: 10px; border-radius: var(--radius-sm); border:1px solid #e2e8f0; margin-bottom: 8px;">
                            <div>
                                <strong style="color:var(--primary); font-size:13.5px;">${q.quotation_number}</strong>
                                <span style="color: var(--text-muted); font-size:12px; margin-left: 10px;">Created on: ${formatDate(q.created_at)}</span>
                            </div>
                            <div style="display:flex; align-items:center; gap: 12px;">
                                <strong style="font-size:13.5px;">${amt}</strong>
                                <span class="badge-status ${q.status.toLowerCase()}">${q.status}</span>
                            </div>
                        </div>
                    `;
                }).join('');
            } else {
                quotesHtml = '<div style="color: var(--text-light); text-align:center; padding: 20px; font-size:13px;">No quotations generated yet for this company.</div>';
            }

            let commsHtml = '';
            window.currentClientComms = c.communications_logs || [];
            if (c.communications_logs && c.communications_logs.length > 0) {
                commsHtml = c.communications_logs.map((log, index) => {
                    let viewBtn = '';
                    if (log.body && (log.type === 'Custom Mail' || log.type === 'Quotation')) {
                        viewBtn = `<button onclick="viewTrackEmailContent(${index})" class="btn btn-secondary" style="padding: 2px 8px; font-size: 11px; margin-left: 8px;"><i data-lucide="eye" style="width:12px;height:12px;"></i> View</button>`;
                    }
                    return `
                    <div class="history-item">
                        <div class="history-item-header">
                            <div style="display:flex; align-items:center;">
                                <span style="color: var(--primary); font-weight:700;">${log.type}</span>
                                ${viewBtn}
                            </div>
                            <span style="color: var(--text-muted);">${formatDate(log.sent_at)}</span>
                        </div>
                        <div class="history-item-body">
                            <strong>Subject:</strong> ${log.subject}<br>
                            <span style="font-size:11px; color: var(--text-muted);">Sent by: ${log.sent_by}</span>
                        </div>
                    </div>
                `}).join('');
            } else {
                commsHtml = '<div style="color: var(--text-light); text-align:center; padding: 20px; font-size:13px;">No communication entries logged.</div>';
            }

            if (c.overall_status === 'Closed Won') {
                pane.classList.add('won-theme');
            } else {
                pane.classList.remove('won-theme');
            }

            pane.innerHTML = `
                ${c.overall_status === 'Closed Won' ? '<div style="background: var(--status-won); color: white; padding: 12px; border-radius: 8px 8px 0 0; margin: -24px -24px 20px -24px; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px; gap: 8px; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.2);"><i data-lucide="award" style="width:18px;"></i> Deal Closed Won</div>' : ''}
                <div class="detail-header">
                    <div>
                        <div class="detail-company-title">${c.company_name}</div>
                        <div style="font-size: 13.5px; color: var(--text-muted); margin-top: 4px;">Sector: ${c.industry_sector || 'N/A'} | Type: ${c.business_type}</div>
                    </div>
                    <div style="text-align: right;">
                        <span class="badge-status ${c.overall_status.toLowerCase().replace(' ', '')}" style="font-size: 13px; padding: 6px 14px;">${c.overall_status}</span>
                        <div style="font-size:11px; color: var(--text-light); margin-top: 6px;">Added by ${c.added_by}</div>
                    </div>
                </div>
                
                <!-- CRM Funnel Checklist -->
                <div class="detail-block-title">Pipeline Tracking Status</div>
                <div class="pipeline-tracker">
                    <div class="pipeline-step ${stepsClassList.pitch}">
                        <div class="pipeline-icon-circle"><i data-lucide="${c.pitch_sent ? 'check':'mail'}" style="width:14px;"></i></div>
                        <span class="pipeline-step-label">Pitch Sent</span>
                        <span class="pipeline-step-date">${pitchDate}</span>
                    </div>
                    <div class="pipeline-step ${stepsClassList.ppt}">
                        <div class="pipeline-icon-circle"><i data-lucide="${c.ppt_sent ? 'check':'presentation'}" style="width:14px;"></i></div>
                        <span class="pipeline-step-label">PPT Shared</span>
                        <span class="pipeline-step-date">${pptDate}</span>
                    </div>
                    <div class="pipeline-step ${stepsClassList.quote}">
                        <div class="pipeline-icon-circle"><i data-lucide="${c.quotation_sent ? 'check':'file-text'}" style="width:14px;"></i></div>
                        <span class="pipeline-step-label">Quote Sent</span>
                        <span class="pipeline-step-date">${quoteDate}</span>
                    </div>
                    <div class="pipeline-step ${stepsClassList.mail}">
                        <div class="pipeline-icon-circle"><i data-lucide="${c.mail_sent ? 'check':'sparkles'}" style="width:14px;"></i></div>
                        <span class="pipeline-step-label">Custom Mail</span>
                        <span class="pipeline-step-date">${mailDate}</span>
                    </div>
                </div>

                <!-- Client Detail Info Blocks -->
                <div class="detail-grid">
                    <div>
                        <div class="detail-block-title">Company Info</div>
                        <div class="detail-field"><label>GSTIN:</label> <span>${c.gstin || 'Not Available'}</span></div>
                        <div class="detail-field"><label>PAN No:</label> <span>${c.pan || 'Not Available'}</span></div>
                        <div class="detail-field"><label>Website:</label> <span>${c.website ? `<a href="${c.website}" target="_blank" style="color:var(--primary);">${c.website}</a>` : 'N/A'}</span></div>
                        <div class="detail-field"><label>Turnover:</label> <span>${c.turnover || 'N/A'}</span></div>
                        <div class="detail-field"><label>Employees:</label> <span>${c.employees || 'N/A'}</span></div>
                    </div>
                    <div>
                        <div class="detail-block-title">Key Contact Details</div>
                        <div class="detail-field"><label>Contact Name:</label> <span>${c.contact_name}</span></div>
                        <div class="detail-field"><label>Role:</label> <span>${c.designation || 'N/A'}</span></div>
                        <div class="detail-field"><label>Mobile:</label> <span>${c.mobile}</span></div>
                        <div class="detail-field"><label>WhatsApp:</label> <span>${c.whatsapp || 'N/A'}</span></div>
                        <div class="detail-field"><label>Email:</label> <span><a href="mailto:${c.email}" style="color:var(--primary);">${c.email}</a></span></div>
                    </div>
                </div>

                <div style="margin-bottom: 24px;">
                    <div class="detail-block-title">Interaction Analytics</div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; display: flex; flex-direction:column; justify-content: center; align-items: center;">
                            <div style="font-size:12px; color:var(--text-muted); margin-bottom:10px; font-weight:600;">Breakdown</div>
                            <div style="width: 200px; height: 200px;">
                                <canvas id="interaction-breakdown-chart"></canvas>
                            </div>
                        </div>
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; display: flex; flex-direction:column; justify-content: center; align-items: center;">
                            <div style="font-size:12px; color:var(--text-muted); margin-bottom:10px; font-weight:600;">Activity Pulse (7 Days)</div>
                            <div style="width: 100%; height: 200px;">
                                <canvas id="sparkline-detail"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="margin-bottom: 24px;">
                    <div class="detail-block-title">Address coordinates</div>
                    <div style="font-size: 13.5px; line-height:1.5;">
                        ${c.address_line1}, ${c.address_line2 ? c.address_line2 + ',' : ''}<br>
                        ${c.city}, ${c.state} - ${c.pincode}, ${c.country}
                    </div>
                </div>
                
                <!-- Quick action buttons -->
                <div style="display:flex; gap:10px; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px solid #f1f5f9; flex-wrap: wrap;">
                    ${c.overall_status === 'Not Interested' ? `
                    <button class="btn btn-secondary" disabled title="Client is Not Interested. Communication restricted." style="opacity:0.6; cursor:not-allowed;"><i data-lucide="mail"></i> Send Mail</button>
                    <button class="btn btn-secondary" disabled title="Client is Not Interested. PPT restricted." style="opacity:0.6; cursor:not-allowed;"><i data-lucide="presentation"></i> Mark PPT</button>
                    <button class="btn btn-secondary" disabled title="Client is Not Interested. Quoting restricted." style="opacity:0.6; cursor:not-allowed;"><i data-lucide="file-signature"></i> Create Quote</button>
                    ` : `
                    <button class="btn btn-primary" onclick="initiateEmailForClient(${c.id})"><i data-lucide="mail"></i> Send Mail</button>
                    <button class="btn btn-secondary" onclick="markPPTShared(${c.id}, '${c.company_name.replace(/'/g, "\\'")}')"><i data-lucide="presentation"></i> Mark PPT</button>
                    <button class="btn btn-secondary" onclick="initiateQuotationForClient(${c.id})"><i data-lucide="file-signature"></i> Create Quote</button>
                    `}
                    <button class="btn btn-secondary" style="background: var(--bg-main); color: var(--text-primary); border-color: #cbd5e1;" onclick="initiateLogCall(${c.id}, '${c.company_name.replace(/'/g, "\\'")}')"><i data-lucide="phone-call"></i> Log Call</button>
                </div>

                <!-- Tabs Details (History & Quotes) -->
                <div class="tabs">
                    <div class="tab active" onclick="switchDetailTab('history')" id="tab-btn-history">Communication Logs</div>
                    <div class="tab" onclick="switchDetailTab('quotes')" id="tab-btn-quotes">Quotations Created</div>
                </div>

                <div class="tab-content active" id="tab-content-history">
                    <div class="history-list">
                        ${commsHtml}
                    </div>
                </div>
                
                <div class="tab-content" id="tab-content-quotes">
                    <div style="max-height: 250px; overflow-y:auto;">
                        ${quotesHtml}
                    </div>
                </div>
            `;
            lucide.createIcons();

            // Render Interaction Breakdown Doughnut Chart
            if (window.clientDoughnutChart) {
                window.clientDoughnutChart.destroy();
            }
            if (window.clientSparklineChart) {
                window.clientSparklineChart.destroy();
            }

            const ctx = document.getElementById('interaction-breakdown-chart').getContext('2d');
            if (c.interaction_breakdown) {
                window.clientDoughnutChart = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: Object.keys(c.interaction_breakdown),
                        datasets: [{
                            data: Object.values(c.interaction_breakdown),
                            backgroundColor: ['#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6']
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'right', labels: { boxWidth: 12, font: {size: 11} } }
                        },
                        cutout: '70%'
                    }
                });
            }
            
            // Render Sparkline in details pane
            if (c.sparkline) {
                const spCtx = document.getElementById('sparkline-detail').getContext('2d');
                window.clientSparklineChart = new Chart(spCtx, {
                    type: 'line',
                    data: {
                        labels: ['6d ago', '5d ago', '4d ago', '3d ago', '2d ago', 'Yesterday', 'Today'],
                        datasets: [{
                            label: 'Activities',
                            data: c.sparkline,
                            borderColor: 'rgba(59, 130, 246, 0.8)',
                            borderWidth: 2,
                            tension: 0.4,
                            pointRadius: 3,
                            pointBackgroundColor: '#fff',
                            fill: true,
                            backgroundColor: 'rgba(59, 130, 246, 0.1)'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: { 
                            y: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 } },
                            x: { grid: { display: false } }
                        }
                    }
                });
            }

        } catch (err) {
            console.error(err);
            showNotification('Could not load client CRM profile details.', 'error');
        }
    }

    function switchDetailTab(tabName) {
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        
        document.getElementById('tab-btn-' + tabName).classList.add('active');
        document.getElementById('tab-content-' + tabName).classList.add('active');
    }

    function formatDate(sqlDate) {
        if (!sqlDate) return 'N/A';
        const date = new Date(sqlDate);
        return date.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    function formatIndianCurrency(amount) {
        let x = Math.round(amount).toString();
        let lastThree = x.slice(-3);
        let otherNumbers = x.slice(0, -3);
        if (otherNumbers !== '') lastThree = ',' + lastThree;
        let res = otherNumbers.replace(/\B(?=(\d{2})+(?!\d))/g, ",") + lastThree;
        return '₹' + res;
    }

    function initiateEmailForClient(clientId) {
        location.href = 'send_email.php?client_id=' + clientId;
    }

    function initiateQuotationForClient(clientId) {
        location.href = 'quotation_builder.php?client_id=' + clientId;
    }

    async function markPPTShared(clientId, companyName) {
        if (!confirm(`Are you sure you want to mark PPT as shared for ${companyName}?`)) return;
        
        const fd = new FormData();
        fd.append('client_id', clientId);
        
        try {
            const res = await fetch('api.php?api=log_ppt_shared', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                showNotification(data.message, 'success');
                triggerSearch();
            } else {
                showNotification(data.error || 'Failed to mark PPT.', 'error');
            }
        } catch (e) {
            showNotification('Error communicating with server.', 'error');
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        loadFilterOptions();
        triggerSearch();
    });
</script>

<!-- View Email Content Modal -->
<div id="view-email-modal" class="modal" style="display:none; z-index:10005;">
    <div class="modal-content" style="max-width: 800px; max-height: 90vh; overflow: hidden; display: flex; flex-direction: column;">
        <div class="modal-header">
            <h3>Email Content Preview</h3>
            <button onclick="closeViewEmailModal()" style="background:none;border:none;cursor:pointer;"><i data-lucide="x"></i></button>
        </div>
        <div class="modal-body" style="overflow-y:auto; background: #f8fafc; border-radius: 8px; padding: 16px; margin-top: 10px;">
            <div id="view-email-body-content" style="white-space: pre-wrap; font-family: sans-serif; font-size: 14px; color: #334155;"></div>
        </div>
    </div>
</div>

<script>
    function viewTrackEmailContent(index) {
        const email = window.currentClientComms[index];
        if(!email) return;
        
        let htmlContent = email.body;
        if (email.attachment_name) {
            htmlContent += `\n\n<hr style="border:0; border-top:1px solid #e2e8f0; margin:15px 0;">`;
            if (email.attachment_name.toLowerCase().endsWith('.pdf')) {
                htmlContent += `<div style="font-weight:600; margin-bottom:10px;">📎 Attachment: ${email.attachment_name}</div>`;
                htmlContent += `<iframe src="../uploads/${email.attachment_name}" style="width:100%; height:500px; border:1px solid #cbd5e1; border-radius:4px;"></iframe>`;
            } else {
                htmlContent += `<a href="../uploads/${email.attachment_name}" target="_blank" style="color:var(--primary); font-weight:600; text-decoration:none;">📎 Attachment: View File</a>`;
            }
        }
        
        document.getElementById('view-email-body-content').innerHTML = htmlContent;
        document.getElementById('view-email-modal').style.display = 'flex';
    }

    function closeViewEmailModal() {
        document.getElementById('view-email-modal').style.display = 'none';
    }
</script>

<!-- Log Call Modal -->
<div id="log-call-modal" class="modal" style="display:none;">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3>Log a Call</h3>
            <button onclick="closeLogCallModal()" style="background:none;border:none;cursor:pointer;"><i data-lucide="x"></i></button>
        </div>
        <form id="log-call-form" onsubmit="submitLogCall(event)">
            <input type="hidden" id="log-call-client-id" name="client_id">
            
            <div class="form-group" style="margin-bottom: 15px;">
                <label>Call Outcome / Response</label>
                <select id="log-call-outcome" name="outcome" class="form-control" required style="width:100%; padding: 8px; border-radius: 4px; border: 1px solid #cbd5e1;">
                    <option value="">Select Response...</option>
                    <option value="Connected - Interested">Connected - Interested</option>
                    <option value="Connected - Follow-up requested">Connected - Follow-up requested</option>
                    <option value="Connected - Not Interested">Connected - Not Interested</option>
                    <option value="No Answer">No Answer</option>
                    <option value="Number Busy / Invalid">Number Busy / Invalid</option>
                </select>
            </div>
            
            <div class="form-group" style="margin-bottom: 15px;">
                <label>Remarks / Call Summary</label>
                <textarea id="log-call-remarks" name="remarks" class="form-control" required rows="4" placeholder="Briefly describe what was discussed..." style="width:100%; padding: 8px; border-radius: 4px; border: 1px solid #cbd5e1;"></textarea>
            </div>
            
            <div style="text-align: right;">
                <button type="button" class="btn btn-secondary" onclick="closeLogCallModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Call Log</button>
            </div>
        </form>
    </div>
</div>

<script>
    function initiateLogCall(clientId, companyName) {
        document.getElementById('log-call-client-id').value = clientId;
        document.getElementById('log-call-outcome').value = '';
        document.getElementById('log-call-remarks').value = '';
        document.getElementById('log-call-modal').style.display = 'flex';
        
        // Ensure icons load
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }

    function closeLogCallModal() {
        document.getElementById('log-call-modal').style.display = 'none';
    }

    async function submitLogCall(e) {
        e.preventDefault();
        const btn = e.target.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.innerText = 'Saving...';
        
        const clientId = document.getElementById('log-call-client-id').value;
        const outcome = document.getElementById('log-call-outcome').value;
        const remarks = document.getElementById('log-call-remarks').value;
        
        try {
            const fd = new FormData();
            fd.append('client_id', clientId);
            fd.append('outcome', outcome);
            fd.append('remarks', remarks);
            
            const res = await fetch('api.php?api=log_call', {
                method: 'POST',
                body: fd
            });
            const data = await res.json();
            
            if (data.error) {
                showNotification(data.error, 'error');
            } else {
                showNotification('Call logged successfully!', 'success');
                closeLogCallModal();
                selectClientCard(clientId); // Refresh client detail view
            }
        } catch (err) {
            showNotification('Server Error', 'error');
        }
        
        btn.disabled = false;
        btn.innerText = 'Save Call Log';
    }
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
