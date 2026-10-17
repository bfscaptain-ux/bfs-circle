<?php
require_once 'config.php';
require_role(['Admin', 'RM']);

$page_title = 'Invoice Ledgers';
$page_subtitle = 'Audit and track approved client quotations';
require_once 'header.php';
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
            const res = await fetch('api.php?' + params.toString());
            if (!res.ok) return;
            const data = await res.json();
            if (!data || !data.summary) return;
            
            // Set summaries
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
            const response = await fetch('api.php?api=update_quotation_status', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: payload.toString()
            });
            const data = await response.json();
            
            if (response.ok && data.success) {
                showNotification(data.message, 'success');
                if (newStatus === 'Approved') {
                    if (typeof confetti === 'function') {
                        confetti({ particleCount: 150, spread: 70, origin: { y: 0.6 } });
                    }
                }
                loadQuotationList(); // refresh
            } else {
                showNotification(data.error || 'Failed to adjust quotation status.', 'error');
            }
        } catch (err) {
            showNotification('Status transaction execution failed.', 'error');
        }
    }

    function emailQuotationQuick(quoteId, clientEmail, companyName) {
        // Redirect to send_email.php and let it know we are sending a quote
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
            const response = await fetch(`api.php?api=quotation_list`);
            const data = await response.json();
            const quote = data.quotations.find(q => q.id == quoteId);
            
            if (!quote) {
                showNotification('Quotation details trace missing.', 'error');
                return;
            }
            
            const cliRes = await fetch(`api.php?api=client_details&id=${quote.client_id}`);
            const cli = await cliRes.json();
            
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
            
            let bankDetailsHtml = '';
            if (companyProfile.bank_name && companyProfile.account_number) {
                bankDetailsHtml = `
                    <div style="margin-top: 15px; border: 1px dashed #cbd5e1; padding: 10px; border-radius: 6px; background-color: #f8fafc;">
                        <strong style="color:#ea580c; font-size: 10px; text-transform: uppercase;">Bank Account Details:</strong>
                        <div style="font-size:10px; color:#475569; margin-top: 4px; line-height: 1.4;">
                            <strong>Bank Name:</strong> ${companyProfile.bank_name}<br>
                            <strong>Account Number:</strong> ${companyProfile.account_number}<br>
                            <strong>IFSC Code:</strong> ${companyProfile.ifsc_code || 'N/A'}
                        </div>
                    </div>
                `;
            }

            template.innerHTML = `
                <div style="display:flex; justify-content: space-between; align-items:flex-start; margin-bottom: 40px; border-bottom: 2px solid #ea580c; padding-bottom: 20px;">
                    <div>
                        <h1 style="color:#ea580c; font-family:'Outfit'; font-size: 26px; font-weight:800; text-transform: uppercase;">Quotation</h1>
                        <div style="font-size:11px; color:#64748b; margin-top:4px;">Draft ID: ${quote.quotation_number}</div>
                    </div>
                    <div style="text-align: right; font-size: 12px; color: #334155; line-height: 1.4;">
                        <strong style="font-size:18px; color: #0f172a;">${companyProfile.company_name}</strong><br>
                        ${companyProfile.address_line1}<br>
                        ${companyProfile.address_line2 ? companyProfile.address_line2 + '<br>' : ''}
                        ${companyProfile.city}, ${companyProfile.state} - ${companyProfile.pincode}<br>
                        <strong>GSTIN:</strong> ${companyProfile.gstin}<br>
                        <strong>Email:</strong> ${companyProfile.email} ${companyProfile.mobile ? '| <strong>Phone:</strong> ' + companyProfile.mobile : ''}
                    </div>
                </div>
                
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-bottom: 40px;">
                    <div>
                        <strong style="text-transform: uppercase; color:#ea580c; font-size:11px;">Quotation Prepared For:</strong>
                        <div style="font-size:15px; font-weight:700; color: #0f172a; margin: 6px 0 4px 0;">${quote.company_name}</div>
                        <div style="color: #475569; line-height: 1.4;">
                            <strong>Contact:</strong> ${cli.contact_name} (${cli.designation})<br>
                            <strong>Email:</strong> ${cli.email} | <strong>Mobile:</strong> ${cli.mobile}<br>
                            <strong>GSTIN:</strong> ${cli.gstin || 'Not Provided'}<br>
                            <strong>Address:</strong> ${cli.address_line1}${cli.address_line2 ? ', ' + cli.address_line2 : ''}, ${cli.city}, ${cli.state} - ${cli.pincode}
                        </div>
                    </div>
                    <div style="text-align: right; color: #475569; line-height: 1.4;">
                        <strong style="text-transform: uppercase; color:#ea580c; font-size:11px;">Quotation Information:</strong>
                        <div style="margin-top: 6px;">
                            <strong>Date Issued:</strong> ${formatDate(quote.created_at)}<br>
                            <strong>Valid Until:</strong> ${formatDate(new Date(new Date(quote.created_at).getTime() + (30 * 24 * 60 * 60 * 1000)))} (30 Days)<br>
                            <strong>Status:</strong> <span style="font-weight: 600; color: ${quote.status === 'Approved' ? '#10b981' : quote.status === 'Rejected' ? '#ef4444' : '#f59e0b'};">${quote.status}</span><br>
                            <strong>Prepared By:</strong> ${companyProfile.contact_person}
                        </div>
                    </div>
                </div>
                
                <table style="width:100%; border-collapse: collapse; margin-bottom: 30px;">
                    <thead>
                        <tr style="background-color: #f8fafc; color:#ea580c;">
                            <th style="border: 1px solid #cbd5e1; padding: 12px 10px; width: 6%; text-align: center;">S.No</th>
                            <th style="border: 1px solid #cbd5e1; padding: 12px 10px; width: 50%; text-align: left;">Item/Service Specification</th>
                            <th style="border: 1px solid #cbd5e1; padding: 12px 10px; width: 8%; text-align: center;">Qty</th>
                            <th style="border: 1px solid #cbd5e1; padding: 12px 10px; width: 14%; text-align: right;">Rate (Rs. )</th>
                            <th style="border: 1px solid #cbd5e1; padding: 12px 10px; width: 10%; text-align: center;">GST</th>
                            <th style="border: 1px solid #cbd5e1; padding: 12px 10px; width: 14%; text-align: right;">Total (Rs. )</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rowsHtml}
                    </tbody>
                </table>
                
                <div style="display:grid; grid-template-columns: 1.3fr 1fr; gap: 40px; margin-top: 40px;">
                    <div>
                        <strong style="color:#ea580c; font-size: 11px; text-transform: uppercase;">Terms & Conditions:</strong>
                        <ol style="font-size:10px; color:#64748b; padding-left:14px; margin-top:6px; line-height: 1.6;">
                            <li>Payment terms: 50% advance on approval, remaining 50% post implementation.</li>
                            <li>The rates listed are valid for 30 calendar days from issued date.</li>
                            <li>All disputes are subject to ${companyProfile.city || 'Mumbai'} jurisdiction.</li>
                        </ol>
                        ${bankDetailsHtml}
                    </div>
                    <div style="background-color: #f8fafc; padding: 15px; border-radius: 6px; border: 1px solid #cbd5e1; align-self: flex-start;">
                        <table style="width: 100%; border-collapse: collapse; font-size:13px; line-height: 1.6;">
                            <tr>
                                <td style="padding: 6px 0; color:#64748b;">Subtotal (Taxable Value):</td>
                                <td style="padding: 6px 0; text-align: right; font-weight:600;">${formatIndianCurrency(quote.subtotal)}</td>
                            </tr>
                            ${taxBreakdownHtml}
                            <tr>
                                <td style="padding: 10px 0 0 0; font-size: 16px; font-weight:800; color:#ea580c; border-top: 1px solid #cbd5e1;">Grand Total:</td>
                                <td style="padding: 10px 0 0 0; text-align: right; font-size: 16px; font-weight:800; color:#ea580c; border-top: 1px solid #cbd5e1;">${formatIndianCurrency(quote.total_amount)}</td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div style="margin-top: 80px; display:flex; justify-content: space-between; align-items:flex-end;">
                    <div>
                        <div style="border-bottom: 1px solid #cbd5e1; width: 160px; margin-bottom:6px;"></div>
                        <span style="font-size: 10px; color:#64748b; font-weight: 500;">Client Signature / Acceptor</span>
                    </div>
                    <div style="text-align: right;">
                        <div style="border-bottom: 1px solid #cbd5e1; width: 160px; margin-bottom:6px; margin-left: auto;"></div>
                        <span style="font-size: 10px; color:#ea580c; font-weight:700;">For ${companyProfile.company_name}</span>
                    </div>
                </div>
            `;
            
            printContainer.appendChild(template);
            
            const opt = {
                margin:       10,
                filename:     `Quotation_${quote.quotation_number}_${quote.company_name.replace(/\s+/g, '_')}.pdf`,
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2 },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            
            showNotification('Generating PDF Quotation...', 'info');
            const pdfWindow = window.open('', '_blank');
            if (pdfWindow) {
                pdfWindow.document.write('<div style="font-family:sans-serif; padding: 20px;">Generating PDF... Please wait.</div>');
            }
            
            html2pdf().set(opt).from(template).outputPdf('bloburl').then(function(pdfUrl) {
                if (pdfWindow) {
                    pdfWindow.location.href = pdfUrl;
                } else {
                    html2pdf().set(opt).from(template).save();
                }
            });
            
        } catch (err) {
            console.error(err);
            showNotification('Could not export quotation to PDF format.', 'error');
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        loadQuotationList();
    });
</script>

<?php require_once 'footer.php'; ?>
