<?php
require_once __DIR__ . '/../config.php';
$page_title = 'Invoice Ledgers';
$page_subtitle = 'Audit and track approved client quotations';
require_once __DIR__ . '/header.php';
?>

<div id="view-quotation-list" class="view-container">
    <div class="card">
        <!-- Search & Filters -->
        <div class="crm-search-bar">
            <div class="search-input-wrapper">
                <i data-lucide="search" class="search-icon"></i>
                <input type="text" id="quote-search" placeholder="Search by Client Name or Quotation number..." oninput="loadQuotationList()">
            </div>
            <div class="form-group" style="width: 200px;">
                <select id="quote-filter-status" onchange="loadQuotationList()">
                    <option value="">All Statuses</option>
                    <option value="Pending">Pending</option>
                    <option value="Approved">Approved</option>
                    <option value="Rejected">Rejected</option>
                </select>
            </div>
        </div>

        <!-- Quotation Value summary dashboard metrics -->
        <div class="stats-grid" style="grid-template-columns: repeat(4, 1fr); margin-top: 14px; margin-bottom: 24px;">
            <div class="stat-card" style="padding: 16px;">
                <span class="stat-label" style="font-size: 11px;">Total Drafted Value</span>
                <div class="stat-value" id="qs-total" style="font-size: 20px; margin-top: 4px;">Rs. 0.00</div>
            </div>
            <div class="stat-card" style="padding: 16px; border-left: 4px solid var(--warning);">
                <span class="stat-label" style="font-size: 11px; color: var(--warning);">Pending Value</span>
                <div class="stat-value" id="qs-pending" style="font-size: 20px; color: var(--warning); margin-top: 4px;">Rs. 0.00</div>
            </div>
            <div class="stat-card" style="padding: 16px; border-left: 4px solid var(--success);">
                <span class="stat-label" style="font-size: 11px; color: var(--success);">Approved (Won) Value</span>
                <div class="stat-value" id="qs-approved" style="font-size: 20px; color: var(--success); margin-top: 4px;">Rs. 0.00</div>
            </div>
            <div class="stat-card" style="padding: 16px; border-left: 4px solid var(--danger);">
                <span class="stat-label" style="font-size: 11px; color: var(--danger);">Rejected Value</span>
                <div class="stat-value" id="qs-rejected" style="font-size: 20px; color: var(--danger); margin-top: 4px;">Rs. 0.00</div>
            </div>
        </div>

        <!-- Data Table -->
        <div style="overflow-x: auto;">
            <table class="quotation-list-table">
                <thead>
                    <tr>
                        <th style="width: 10%;">Quote No.</th>
                        <th style="width: 25%;">Client Company Name</th>
                        <th style="width: 15%;">Subtotal (Rs. )</th>
                        <th style="width: 15%;">GST Amount (Rs. )</th>
                        <th style="width: 15%;">Total Value (Rs. )</th>
                        <th style="width: 12%;">Status</th>
                        <th style="width: 8%;">Actions</th>
                    </tr>
                </thead>
                <tbody id="quotation-list-tbody">
                    <!-- Loaded via API -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    async function loadQuotationList() {
        const search = document.getElementById('quote-search').value;
        const status = document.getElementById('quote-filter-status').value;
        
        const params = new URLSearchParams({
            api: 'quotation_list',
            search: search,
            status: status
        });
        
        try {
            const res = await fetch('../api.php?' + params.toString());
            if (!res.ok) return;
            const data = await res.json();
            if (!data || !data.summary) return;
            
            document.getElementById('qs-total').innerText = formatIndianCurrency(data.summary.total_value);
            document.getElementById('qs-pending').innerText = formatIndianCurrency(data.summary.pending_value);
            document.getElementById('qs-approved').innerText = formatIndianCurrency(data.summary.approved_value);
            document.getElementById('qs-rejected').innerText = formatIndianCurrency(data.summary.rejected_value);
            
            const tbody = document.getElementById('quotation-list-tbody');
            tbody.innerHTML = '';
            
            if (data.quotations.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: var(--text-light); padding: 30px;">No quotations matched requirements.</td></tr>';
                return;
            }
            
            data.quotations.forEach(q => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><strong>${q.quotation_number}</strong></td>
                    <td>
                        <strong style="color:var(--text-primary);">${q.company_name}</strong><br>
                        <span style="font-size:11px; color: var(--text-muted);">${q.client_email} | ${q.city}</span>
                    </td>
                    <td>${formatIndianCurrency(q.subtotal)}</td>
                    <td>${formatIndianCurrency(q.gst_amount)}</td>
                    <td><strong style="color:var(--primary);">${formatIndianCurrency(q.total_amount)}</strong></td>
                    <td>
                        <select class="status-pill-select ${q.status}" onchange="updateQuoteStatus(${q.id}, this.value)">
                            <option value="Pending" ${q.status === 'Pending' ? 'selected' : ''}>Pending</option>
                            <option value="Approved" ${q.status === 'Approved' ? 'selected' : ''}>Approved</option>
                            <option value="Rejected" ${q.status === 'Rejected' ? 'selected' : ''}>Rejected</option>
                        </select>
                    </td>
                    <td>
                        <div style="display:flex; gap:8px;">
                            <button class="btn btn-secondary" style="padding: 6px 10px;" onclick="printQuotationPDF(${q.id})" title="Print or Save PDF"><i data-lucide="printer" style="width:14px; height:14px;"></i></button>
                            <button class="btn btn-secondary" style="padding: 6px 10px;" onclick="emailQuotationQuick(${q.id}, '${q.client_email}', '${q.company_name}')" title="Email Quotation"><i data-lucide="mail" style="width:14px; height:14px;"></i></button>
                        </div>
                    </td>
                `;
                tbody.appendChild(tr);
            });
            lucide.createIcons();
        } catch (err) {
            showNotification('Failed to retrieve quotation ledger data.', 'error');
        }
    }

    async function updateQuoteStatus(quoteId, newStatus) {
        const payload = new URLSearchParams({
            quote_id: quoteId,
            status: newStatus
        });
        
        try {
            const response = await fetch('../api.php?api=update_quotation_status', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: payload.toString()
            });
            const data = await response.json();
            
            if (response.ok && data.success) {
                showNotification(data.message, 'success');
                if (newStatus === 'Approved') {
                    if (typeof confetti === 'function') confetti({ particleCount: 150, spread: 70, origin: { y: 0.6 } });
                }
                loadQuotationList(); 
            } else {
                showNotification(data.error || 'Failed to adjust quotation status.', 'error');
            }
        } catch (err) {
            showNotification('Status transaction execution failed.', 'error');
        }
    }

    function emailQuotationQuick(quoteId, clientEmail, companyName) {
        sessionStorage.setItem('email_prefill_quotation', JSON.stringify({
            companyName: companyName,
            subject: `Quotation Proposal Details for ${companyName}`,
            body: `
                <p>Dear Sir/Madam,</p>
                <p>Please find enclosed our quotation proposal regarding the requirements discussed.</p>
                <p>Kindly check the quotation details ledger in the portal attachment section and approve the transaction.</p>
                <p>Thank you for your business!</p>
            `
        }));
        location.href = 'send_email.php';
    }

    function formatDate(sqlDate) {
        if (!sqlDate) return 'N/A';
        const date = new Date(sqlDate);
        return date.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    async function printQuotationPDF(quoteId) {
        try {
            const response = await fetch(`../api.php?api=quotation_list`);
            const data = await response.json();
            const quote = data.quotations.find(q => q.id == quoteId);
            
            if (!quote) {
                showNotification('Quotation details trace missing.', 'error');
                return;
            }
            
            const cliRes = await fetch(`../api.php?api=client_details&id=${quote.client_id}`);
            const cli = await cliRes.json();
            
            if (cli.error) {
                showNotification(cli.error, 'error');
                return;
            }
            
            const items = JSON.parse(quote.items_json);
            const printContainer = document.getElementById('invoice-print-container');
            printContainer.innerHTML = '';
            
            const template = document.createElement('div');
            template.style.padding = '30px';
            template.style.background = '#ffffff';
            template.style.color = '#1e293b';
            template.style.fontFamily = 'Inter, sans-serif';
            template.style.fontSize = '12px';
            template.style.lineHeight = '1.6';
            
            let rowsHtml = items.map((item, idx) => `
                <tr>
                    <td style="border: 1px solid #cbd5e1; padding: 10px; text-align: center;">${idx + 1}</td>
                    <td style="border: 1px solid #cbd5e1; padding: 10px;">${item.name}</td>
                    <td style="border: 1px solid #cbd5e1; padding: 10px; text-align: center;">${item.qty}</td>
                    <td style="border: 1px solid #cbd5e1; padding: 10px; text-align: right;">${formatIndianCurrency(item.rate)}</td>
                    <td style="border: 1px solid #cbd5e1; padding: 10px; text-align: center;">${item.gst_rate}%</td>
                    <td style="border: 1px solid #cbd5e1; padding: 10px; text-align: right; font-weight:600;">${formatIndianCurrency(item.total)}</td>
                </tr>
            `).join('');
            
            let isSameState = false;
            if (cli.state && companyProfile.state) {
                isSameState = cli.state.trim().toLowerCase() === companyProfile.state.trim().toLowerCase();
            }
            
            let cgstAmount = 0;
            let sgstAmount = 0;
            let igstAmount = 0;
            if (isSameState) {
                cgstAmount = quote.gst_amount / 2;
                sgstAmount = quote.gst_amount / 2;
            } else {
                igstAmount = quote.gst_amount;
            }
            
            let taxBreakdownHtml = '';
            if (isSameState) {
                taxBreakdownHtml = `
                    <tr>
                        <td style="padding: 6px 0; color:#64748b;">CGST:</td>
                        <td style="padding: 6px 0; text-align: right; font-weight:600;">${formatIndianCurrency(cgstAmount)}</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 0; color:#64748b; border-bottom: 1px solid #cbd5e1;">SGST:</td>
                        <td style="padding: 6px 0; text-align: right; font-weight:600; border-bottom: 1px solid #cbd5e1;">${formatIndianCurrency(sgstAmount)}</td>
                    </tr>
                `;
            } else {
                taxBreakdownHtml = `
                    <tr>
                        <td style="padding: 6px 0; color:#64748b; border-bottom: 1px solid #cbd5e1;">IGST:</td>
                        <td style="padding: 6px 0; text-align: right; font-weight:600; border-bottom: 1px solid #cbd5e1;">${formatIndianCurrency(igstAmount)}</td>
                    </tr>
                `;
            }
            
            template.innerHTML = `
                <div style="display:flex; justify-content:space-between; border-bottom: 2px solid var(--primary); padding-bottom: 20px; margin-bottom: 20px;">
                    <div>
                        <h1 style="margin:0; font-family:'Outfit'; font-size:24px; color:var(--primary);">${companyProfile.company_name || 'BFS Financial Services'}</h1>
                        <p style="margin:4px 0 0 0; color:#64748b;">
                            ${companyProfile.address_line1 || ''}<br>
                            ${companyProfile.city || ''}, ${companyProfile.state || ''} - ${companyProfile.pincode || ''}<br>
                            <strong>GSTIN:</strong> ${companyProfile.gstin || 'N/A'}<br>
                            <strong>Email:</strong> ${companyProfile.email || ''} | <strong>Mobile:</strong> ${companyProfile.mobile || ''}
                        </p>
                    </div>
                    <div style="text-align:right;">
                        <h2 style="margin:0; font-family:'Outfit'; font-size:20px; color:#1e293b; text-transform:uppercase; letter-spacing:1px;">Quotation</h2>
                        <p style="margin:6px 0 0 0; font-size:13px;">
                            <strong>Quote No:</strong> <span style="color:var(--primary); font-weight:700;">${quote.quotation_number}</span><br>
                            <strong>Date:</strong> ${formatDate(quote.created_at)}<br>
                            <strong>Status:</strong> ${quote.status}
                        </p>
                    </div>
                </div>
                
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:30px; margin-bottom:30px;">
                    <div style="background:#f8fafc; padding:15px; border-radius:6px; border:1px solid #e2e8f0;">
                        <strong style="font-size:13px; color:#1e293b; display:block; margin-bottom:8px; border-bottom:1px solid #e2e8f0; padding-bottom:4px;">Billing Customer Recipient:</strong>
                        <strong style="font-size:14px; color:#0f172a;">${cli.company_name}</strong>
                        <p style="margin:6px 0 0 0; color:#475569;">
                            ${cli.address_line1}, ${cli.address_line2 ? cli.address_line2 + ',' : ''}<br>
                            ${cli.city}, ${cli.state} - ${cli.pincode}, ${cli.country}<br>
                            <strong>GSTIN:</strong> ${cli.gstin || 'N/A'}<br>
                            <strong>PAN:</strong> ${cli.pan || 'N/A'}
                        </p>
                    </div>
                    <div style="background:#f8fafc; padding:15px; border-radius:6px; border:1px solid #e2e8f0;">
                        <strong style="font-size:13px; color:#1e293b; display:block; margin-bottom:8px; border-bottom:1px solid #e2e8f0; padding-bottom:4px;">Contact Coordinator:</strong>
                        <strong style="font-size:13px; color:#0f172a;">${cli.contact_name}</strong>
                        <p style="margin:6px 0 0 0; color:#475569;">
                            <strong>Designation:</strong> ${cli.designation || 'Purchase Exec.'}<br>
                            <strong>Mobile:</strong> ${cli.mobile}<br>
                            <strong>Email:</strong> ${cli.email}<br>
                            <strong>Account Owner:</strong> @${cli.assigned_to || 'Unassigned'}
                        </p>
                    </div>
                </div>
                
                <table style="width: 100%; border-collapse: collapse; margin-bottom: 25px; font-size:11px;">
                    <thead>
                        <tr style="background:#f1f5f9; color:#475569; font-weight:700;">
                            <th style="border: 1px solid #cbd5e1; padding: 10px; text-align: center; width: 5%;">S.N</th>
                            <th style="border: 1px solid #cbd5e1; padding: 10px; text-align: left; width: 45%;">Item / Description Specification</th>
                            <th style="border: 1px solid #cbd5e1; padding: 10px; text-align: center; width: 8%;">Qty</th>
                            <th style="border: 1px solid #cbd5e1; padding: 10px; text-align: right; width: 14%;">Rate (Rs. )</th>
                            <th style="border: 1px solid #cbd5e1; padding: 10px; text-align: center; width: 10%;">GST %</th>
                            <th style="border: 1px solid #cbd5e1; padding: 10px; text-align: right; width: 18%;">Gross Value (Rs. )</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rowsHtml}
                    </tbody>
                </table>
                
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <div style="width: 50%; background:#fff7ed; padding: 12px; border-radius: 6px; border:1px solid #ffedd5; font-size:11px; color:#c2410c;">
                        <strong>📌 Terms & Conditions:</strong>
                        <ul style="margin: 6px 0 0 0; padding-left: 15px; line-height: 1.5;">
                            <li>GST taxes applied as per standard schedules of Central Goods & Services Acts.</li>
                            <li>Pricing quotes values are locked strictly for 30 calendar days from date of draft.</li>
                            <li>Standard dispatch timelines: 7-10 working days from purchase order confirmation.</li>
                        </ul>
                    </div>
                    <div style="width: 40%;">
                        <table style="width:100%; border-collapse:collapse; font-size:12px;">
                            <tr>
                                <td style="padding: 6px 0; color:#64748b;">Subtotal (Taxable):</td>
                                <td style="padding: 6px 0; text-align: right; font-weight:600;">${formatIndianCurrency(quote.subtotal)}</td>
                            </tr>
                            ${taxBreakdownHtml}
                            <tr style="border-top:2px double #cbd5e1; font-size:14px;">
                                <td style="padding: 10px 0; font-weight:700; color:var(--primary);">Net Payable Total:</td>
                                <td style="padding: 10px 0; text-align: right; font-weight:800; color:var(--primary);">${formatIndianCurrency(quote.total_amount)}</td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div style="margin-top: 40px; border-top: 1px dashed #cbd5e1; padding-top:20px; display:flex; justify-content:space-between; align-items:center; font-size:11px; color:#94a3b8;">
                    <div>System Generated Audit Record: <strong>${quote.quotation_number}</strong>. No signature required.</div>
                    <div style="text-align:right;">Powered by BFS Financial Services Solutions</div>
                </div>
            `;
            
            printContainer.appendChild(template);
            
            const opt = {
                margin:       10,
                filename:     `Quotation_${quote.quotation_number}_${cli.company_name.replace(/\s+/g, '_')}.pdf`,
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            
            html2pdf().from(template).set(opt).save();
            showNotification(`Generating PDF for ${quote.quotation_number}...`, 'success');
        } catch (err) {
            console.error(err);
            showNotification('Error during PDF rendering.', 'error');
        }
    }

    function formatIndianCurrency(amount) {
        let x = Math.round(amount).toString();
        let lastThree = x.slice(-3);
        let otherNumbers = x.slice(0, -3);
        if (otherNumbers !== '') lastThree = ',' + lastThree;
        let res = otherNumbers.replace(/\B(?=(\d{2})+(?!\d))/g, ",") + lastThree;
        return 'Rs. ' + res;
    }

    document.addEventListener('DOMContentLoaded', () => {
        loadQuotationList();
    });
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
