<?php
require_once 'config.php';
require_role(['Admin', 'RM']);

$hide_main_header = true; // Clean single-header layout without duplicate top bars
$page_title = 'Loan Applications Dashboard';
$page_subtitle = 'Track, Manage and Process Loan Applicant Files';
require_once 'header.php';
?>

<style>
    .pg-btn {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        color: #334155;
        padding: 6px 12px;
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

    /* Shimmer Skeleton Loading Animation */
    @keyframes skeletonShimmer {
        0% { background-position: -200px 0; }
        100% { background-position: 200px 0; }
    }
    .skeleton-cell {
        display: inline-block;
        height: 14px;
        border-radius: 4px;
        background: #f1f5f9;
        background-image: linear-gradient(90deg, #f1f5f9 0px, #cbd5e1 40px, #f1f5f9 80px);
        background-size: 400px 100%;
        animation: skeletonShimmer 1.2s ease-in-out infinite;
    }
</style>

<div id="view-applicants-list" class="view-container" style="padding-top: 6px;">
    <!-- Executive Header Bar -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 20px 24px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; box-shadow: 0 2px 10px rgba(0,0,0,0.02);">
        <div>
            <h1 style="font-size: 22px; font-weight: 800; color: #0f172a; letter-spacing: -0.02em; margin: 0; display: flex; align-items: center; gap: 10px;">
                <div style="width: 38px; height: 38px; background: #0f172a; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <i data-lucide="layout-dashboard" style="color: #ffffff; width: 20px; height: 20px;"></i>
                </div>
                <span>Loan Applications Dashboard</span>
            </h1>
            <p style="margin: 4px 0 0 48px; color: #64748b; font-size: 13px; font-weight: 500;">
                Track, Manage and Process Loan Applicant Files &nbsp;•&nbsp; <span id="total-applicants-badge" style="font-weight: 700; color: #0f172a;">Loading...</span>
            </p>
        </div>
        <div>
            <a href="add_applicant.php" class="btn btn-primary" style="background: #0f172a; color: #ffffff; padding: 10px 18px; border-radius: 8px; font-weight: 700; font-size: 13px; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; box-shadow: 0 4px 12px rgba(15,23,42,0.15);">
                <i data-lucide="plus" style="width: 16px; height: 16px;"></i> New Applicant
            </a>
        </div>
    </div>

    <!-- Main Data Table Container -->
    <div class="card" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 20px; box-shadow: 0 4px 20px -5px rgba(0,0,0,0.04);">
        <!-- Filter Controls Bar -->
        <div class="filter-bar" style="display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; align-items: center; background: #f8fafc; padding: 14px 16px; border-radius: 10px; border: 1px solid #e2e8f0;">
            <div style="flex: 1; min-width: 260px; position: relative;">
                <i data-lucide="search" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; width: 17px; height: 17px;"></i>
                <input type="text" id="searchInput" placeholder="Search by Name, Loan ID, or Mobile..." style="width: 100%; padding: 10px 14px 10px 42px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 13px; outline: none; background: #ffffff;" onkeyup="filterTable()">
            </div>
            <div style="min-width: 170px;">
                <select id="stageFilter" onchange="filterTable()" style="width: 100%; padding: 10px 12px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 13px; background: #ffffff; cursor: pointer;">
                    <option value="">All Stages</option>
                    <option value="Phase 1">Phase 1 (KYC)</option>
                    <option value="Phase 2">Phase 2 (Docs)</option>
                    <option value="Phase 3">Phase 3 (Disburse)</option>
                    <option value="Phase 4">Phase 4 (Bank)</option>
                    <option value="Completed">Completed</option>
                    <option value="Rejected">Rejected</option>
                </select>
            </div>
            <div style="min-width: 170px;">
                <select id="typeFilter" onchange="filterTable()" style="width: 100%; padding: 10px 12px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 13px; background: #ffffff; cursor: pointer;">
                    <option value="">All Loan Types</option>
                    <option value="Home Loan">Home Loan</option>
                    <option value="Business Loan">Business Loan</option>
                    <option value="Personal Loan">Personal Loan</option>
                    <option value="Mortgage / LAP">Mortgage / LAP</option>
                    <option value="Vehicle Loan">Vehicle Loan</option>
                    <option value="Gold Loan">Gold Loan</option>
                </select>
            </div>
        </div>

        <div class="table-responsive">
            <table class="data-table" style="width:100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid #e2e8f0; background: #f8fafc;">
                        <th style="padding: 12px 14px; text-align: left; font-size: 11.5px; font-weight: 700; color: #475569; letter-spacing: 0.03em;">APPLICANT ID</th>
                        <th style="padding: 12px 14px; text-align: left; font-size: 11.5px; font-weight: 700; color: #475569; letter-spacing: 0.03em;">APPLICANT NAME</th>
                        <th style="padding: 12px 14px; text-align: left; font-size: 11.5px; font-weight: 700; color: #475569; letter-spacing: 0.03em;">MOBILE</th>
                        <th style="padding: 12px 14px; text-align: left; font-size: 11.5px; font-weight: 700; color: #475569; letter-spacing: 0.03em;">LOAN TYPE</th>
                        <th style="padding: 12px 14px; text-align: left; font-size: 11.5px; font-weight: 700; color: #475569; letter-spacing: 0.03em;">AMOUNT (₹)</th>
                        <th style="padding: 12px 14px; text-align: left; font-size: 11.5px; font-weight: 700; color: #475569; letter-spacing: 0.03em;">CURRENT STAGE</th>
                        <th style="padding: 12px 14px; text-align: left; font-size: 11.5px; font-weight: 700; color: #475569; letter-spacing: 0.03em;">ADDED BY</th>
                        <th style="padding: 12px 14px; text-align: left; font-size: 11.5px; font-weight: 700; color: #475569; letter-spacing: 0.03em;">DATE ADDED</th>
                        <th style="padding: 12px 14px; text-align: right; font-size: 11.5px; font-weight: 700; color: #475569; letter-spacing: 0.03em;">ACTION</th>
                    </tr>
                </thead>
                <tbody id="applicants-tbody">
                    <!-- Initial Skeleton Loading placeholder -->
                </tbody>
            </table>
        </div>

        <!-- Executive Pagination Footer Bar -->
        <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 16px; margin-top: 16px; border-top: 1px solid #e2e8f0; flex-wrap: wrap; gap: 12px;">
            <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #64748b; font-weight: 500; white-space: nowrap; flex-shrink: 0;">
                <span>Show</span>
                <select id="pageSizeSelect" onchange="changePageSize(this.value)" style="padding: 6px 10px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 12.5px; font-weight: 700; color: #0f172a; background: #ffffff; cursor: pointer; display: inline-block;">
                    <option value="10" selected>10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                <span>entries per page</span>
            </div>

            <div id="pagination-info" style="font-size: 13px; color: #64748b; font-weight: 500; white-space: nowrap;">
                Showing 0 to 0 of 0 entries
            </div>

            <div id="pagination-controls" style="display: flex; gap: 4px; align-items: center; flex-wrap: wrap;">
                <!-- Dynamically populated via JS -->
            </div>
        </div>
    </div>
</div>

<script>
    let allApplicants = [];
    let filteredApplicants = [];
    let currentPage = 1;
    let pageSize = 10;

    function renderSkeletonLoading() {
        const tbody = document.getElementById('applicants-tbody');
        if (!tbody) return;
        
        let html = '';
        for (let i = 0; i < 5; i++) {
            html += `
                <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 14px;"><span class="skeleton-cell" style="width: 100px;"></span></td>
                    <td style="padding: 14px;"><span class="skeleton-cell" style="width: 140px;"></span></td>
                    <td style="padding: 14px;"><span class="skeleton-cell" style="width: 90px;"></span></td>
                    <td style="padding: 14px;"><span class="skeleton-cell" style="width: 80px;"></span></td>
                    <td style="padding: 14px;"><span class="skeleton-cell" style="width: 70px;"></span></td>
                    <td style="padding: 14px;"><span class="skeleton-cell" style="width: 110px;"></span></td>
                    <td style="padding: 14px;"><span class="skeleton-cell" style="width: 50px;"></span></td>
                    <td style="padding: 14px;"><span class="skeleton-cell" style="width: 80px;"></span></td>
                    <td style="padding: 14px; text-align: right;"><span class="skeleton-cell" style="width: 70px;"></span></td>
                </tr>
            `;
        }
        tbody.innerHTML = html;
    }

    function getStatusBadge(status) {
        let icon = 'circle';
        let bg = '#f1f5f9';
        let color = '#334155';
        let border = '#cbd5e1';

        if(status === 'Phase 1') { icon = 'user-check'; bg = '#f0f9ff'; color = '#0369a1'; border = '#bae6fd'; }
        else if(status === 'Phase 2') { icon = 'file-text'; bg = '#fefce8'; color = '#a16207'; border = '#fef08a'; }
        else if(status === 'Phase 3') { icon = 'coins'; bg = '#f0fdf4'; color = '#15803d'; border = '#bbf7d0'; }
        else if(status === 'Phase 4') { icon = 'landmark'; bg = '#faf5ff'; color = '#6b21a8'; border = '#e9d5ff'; }
        else if(status === 'Completed') { icon = 'check-circle-2'; bg = '#f0fdf4'; color = '#166534'; border = '#bbf7d0'; }
        else if(status === 'Rejected') { icon = 'x-circle'; bg = '#fef2f2'; color = '#991b1b'; border = '#fecaca'; }
        
        const title = status === 'Phase 1' ? 'PHASE 1 (KYC)' : 
                      status === 'Phase 2' ? 'PHASE 2 (DOCS)' : 
                      status === 'Phase 3' ? 'PHASE 3 (DISBURSE)' : 
                      status === 'Phase 4' ? 'PHASE 4 (BANK)' : 
                      status ? status.toUpperCase() : 'NEW';
                      
        return `<span style="background:${bg}; color:${color}; border:1px solid ${border}; font-weight:700; font-size:11px; padding:4px 9px; border-radius:6px; display:inline-flex; align-items:center; gap:5px;"><i data-lucide="${icon}" style="width:12px; height:12px;"></i> ${title}</span>`;
    }
    
    function formatAmt(num) {
        if (!num || isNaN(num)) return '0';
        return new Intl.NumberFormat('en-IN').format(num);
    }

    async function loadApplicants() {
        renderSkeletonLoading();
        try {
            const res = await fetch('api.php?api=get_applicants');
            const data = await res.json();
            
            if (data && Array.isArray(data)) {
                allApplicants = data;
                filterTable();
            } else {
                document.getElementById('applicants-tbody').innerHTML = '<tr><td colspan="9" style="text-align: center; padding: 40px; color: #64748b;">No loan applications found. Start by creating a New Applicant.</td></tr>';
                renderPaginationUI(0, 0, 0);
            }
        } catch (e) {
            console.error('Error fetching applicants:', e);
            document.getElementById('applicants-tbody').innerHTML = '<tr><td colspan="9" style="text-align: center; color: #dc2626;">Failed to load data.</td></tr>';
        }
    }

    function filterTable() {
        const searchInput = document.getElementById('searchInput').value.toLowerCase().trim();
        const stageFilter = document.getElementById('stageFilter').value;
        const typeFilter = document.getElementById('typeFilter').value;

        filteredApplicants = allApplicants.filter(app => {
            const stage = app.overall_status || '';
            const type = app.loan_type || '';
            const textData = `${app.loan_id || ''} ${app.customer_name || ''} ${app.mobile || ''}`.toLowerCase();

            const matchSearch = !searchInput || textData.includes(searchInput);
            const matchStage = !stageFilter || stage === stageFilter;
            const matchType = !typeFilter || type === typeFilter;

            return matchSearch && matchStage && matchType;
        });

        currentPage = 1; // Reset to page 1 on search or filter change
        renderTablePage();
    }

    function renderTablePage() {
        const tbody = document.getElementById('applicants-tbody');
        const totalBadge = document.getElementById('total-applicants-badge');
        const totalCount = filteredApplicants.length;

        if(totalBadge) totalBadge.textContent = `${allApplicants.length} Total Applicants`;

        if (totalCount === 0) {
            tbody.innerHTML = '<tr><td colspan="9" style="text-align: center; padding: 40px; color: #64748b;">No matching applicants found.</td></tr>';
            renderPaginationUI(0, 0, 0);
            return;
        }

        const totalPages = Math.ceil(totalCount / pageSize);
        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        const startIdx = (currentPage - 1) * pageSize;
        const endIdx = Math.min(startIdx + pageSize, totalCount);
        const pageItems = filteredApplicants.slice(startIdx, endIdx);

        tbody.innerHTML = '';
        pageItems.forEach(app => {
            const statusBadge = getStatusBadge(app.overall_status);
            
            let actionUrl = 'add_applicant.php';
            let btnText = 'Continue';
            let btnIcon = 'arrow-right';
            let btnStyle = 'background:#0f172a; color:#ffffff;';
            
            if(app.overall_status === 'Phase 1') {
                actionUrl = `applicant_documents.php?id=${app.id}`;
            } else if(app.overall_status === 'Phase 2') {
                actionUrl = `applicant_disbursements.php?id=${app.id}`;
            } else if(app.overall_status === 'Phase 3') {
                actionUrl = `applicant_bank_assign.php?id=${app.id}`;
            } else if(app.overall_status === 'Phase 4') {
                actionUrl = `applicant_bank_assign.php?id=${app.id}`;
                btnText = 'Update';
                btnIcon = 'edit-3';
            } else if(app.overall_status === 'Completed' || app.overall_status === 'Rejected') {
                actionUrl = `applicant_bank_assign.php?id=${app.id}`;
                btnText = 'View';
                btnIcon = 'eye';
                btnStyle = 'background:#f1f5f9; color:#0f172a; border:1px solid #cbd5e1;';
            }

            const loanTypeStr = app.loan_type ? app.loan_type : 'Unspecified';

            tbody.innerHTML += `
                <tr class="applicant-row" style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 12px 14px;"><span style="font-family: monospace; font-weight: 700; font-size: 12.5px; color: #0f172a;">${app.loan_id}</span></td>
                    <td style="padding: 12px 14px;"><strong style="font-size: 13.5px; color: #0f172a;">${app.customer_name}</strong></td>
                    <td style="padding: 12px 14px; font-size: 13px; color: #334155;">${app.mobile}</td>
                    <td style="padding: 12px 14px; font-size: 13px; color: #334155;">${loanTypeStr}</td>
                    <td style="padding: 12px 14px; font-size: 13px; font-weight: 700; color: #0f172a;">₹${formatAmt(app.loan_amount_requested)}</td>
                    <td style="padding: 12px 14px;">${statusBadge}</td>
                    <td style="padding: 12px 14px; color: #64748b; font-size: 12px;">${app.added_by || 'System'}</td>
                    <td style="padding: 12px 14px; color: #64748b; font-size: 12px;">${app.created_at ? app.created_at.split(' ')[0] : '-'}</td>
                    <td style="padding: 12px 14px; text-align: right; white-space: nowrap;">
                        <a href="applicant_documents.php?id=${app.id}" style="background:#f1f5f9; color:#0f172a; border:1px solid #cbd5e1; padding: 6px 10px; font-size: 12px; font-weight: 700; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; margin-right: 4px;" title="View Documents">
                            <i data-lucide="folder-check" style="width: 13px; height: 13px;"></i> Docs
                        </a>
                        <button type="button" onclick="openWindowsVault(${app.id})" style="background:#18181b; color:#38bdf8; border:1px solid #0284c7; padding: 6px 9px; font-size: 12px; font-weight: 700; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; margin-right: 4px;" title="Open F:\BFS local storage in Windows Explorer">
                            <i data-lucide="folder" style="width: 13px; height: 13px;"></i>
                        </button>
                        <a href="${actionUrl}" style="${btnStyle} padding: 6px 14px; font-size: 12px; font-weight: 700; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 5px;">
                            <i data-lucide="${btnIcon}" style="width: 13px; height: 13px;"></i> ${btnText}
                        </a>
                    </td>
                </tr>
            `;
        });

        if (typeof lucide !== 'undefined') lucide.createIcons();
        renderPaginationUI(startIdx + 1, endIdx, totalCount);
    }

    function changePageSize(newSize) {
        pageSize = parseInt(newSize, 10);
        currentPage = 1;
        renderTablePage();
    }

    function goToPage(page) {
        const totalPages = Math.ceil(filteredApplicants.length / pageSize);
        if (page < 1 || page > totalPages) return;
        currentPage = page;
        renderTablePage();
        
        // Smooth scroll to top of table
        const el = document.getElementById('view-applicants-list');
        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function renderPaginationUI(startItem, endItem, totalItems) {
        const infoEl = document.getElementById('pagination-info');
        const controlsEl = document.getElementById('pagination-controls');

        if (!infoEl || !controlsEl) return;

        if (totalItems === 0) {
            infoEl.innerHTML = 'Showing <b>0</b> to <b>0</b> of <b>0</b> entries';
            controlsEl.innerHTML = '';
            return;
        }

        infoEl.innerHTML = `Showing <b>${startItem}</b> to <b>${endItem}</b> of <b>${totalItems}</b> entries`;

        const totalPages = Math.ceil(totalItems / pageSize);
        let buttonsHtml = '';

        // First & Previous buttons
        const prevDisabled = currentPage === 1 ? 'disabled style="opacity:0.4; cursor:not-allowed;"' : '';
        buttonsHtml += `<button onclick="goToPage(1)" ${prevDisabled} class="pg-btn" title="First Page">««</button>`;
        buttonsHtml += `<button onclick="goToPage(${currentPage - 1})" ${prevDisabled} class="pg-btn" title="Previous Page">‹</button>`;

        // Page number buttons (show up to 5 surrounding pages)
        let startPage = Math.max(1, currentPage - 2);
        let endPage = Math.min(totalPages, startPage + 4);
        if (endPage - startPage < 4) {
            startPage = Math.max(1, endPage - 4);
        }

        for (let p = startPage; p <= endPage; p++) {
            const activeClass = p === currentPage ? 'pg-btn-active' : '';
            buttonsHtml += `<button onclick="goToPage(${p})" class="pg-btn ${activeClass}">${p}</button>`;
        }

        // Next & Last buttons
        const nextDisabled = currentPage === totalPages ? 'disabled style="opacity:0.4; cursor:not-allowed;"' : '';
        buttonsHtml += `<button onclick="goToPage(${currentPage + 1})" ${nextDisabled} class="pg-btn" title="Next Page">›</button>`;
        buttonsHtml += `<button onclick="goToPage(${totalPages})" ${nextDisabled} class="pg-btn" title="Last Page">»»</button>`;

        controlsEl.innerHTML = buttonsHtml;
    }

    async function openWindowsVault(appId) {
        try {
            const res = await fetch(`open_vault_folder.php?id=${appId}&ajax=1`);
            const data = await res.json();
            if (data.success) {
                alert("📂 Opened in Windows Explorer:\n" + data.path);
            } else {
                alert(data.error || "Failed to open folder");
            }
        } catch (e) {
            window.open(`open_vault_folder.php?id=${appId}`, '_blank');
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        loadApplicants();
    });
</script>

<?php require_once 'footer.php'; ?>
