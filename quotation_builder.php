<?php
require_once 'config.php';
require_role(['Admin', 'RM']);

$page_title = 'Quotation Builder Suite';
$page_subtitle = 'Create items proposals with instant Indian GST taxation logic';
require_once 'header.php';

$preset_client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : '';
?>

<div id="view-create-quotation" class="view-container">
    <div class="quote-builder-layout">
        <form id="quotation-builder-form" onsubmit="saveQuotation(event)">
            <!-- Quote Header info -->
            <div class="quote-meta-row">
                <div class="form-group">
                    <label class="required">Client Company Account</label>
                    <select id="quote-client-select" onchange="autofillQuoteClient()" required>
                        <option value="" disabled selected>Choose client account...</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Quotation Number</label>
                    <input type="text" id="quote-number-display" value="Auto-generated on Save" disabled style="background-color: #f1f5f9; font-weight: bold; color: var(--primary);">
                </div>
                <div class="form-group">
                    <label class="required">Date</label>
                    <input type="date" id="quote-date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>

            <!-- Selected Client Meta Card -->
            <div id="quote-client-details-card" style="display: none; background: var(--primary-light); padding: 14px; border-radius: var(--radius-md); border: 1px solid var(--primary-border); margin-bottom: 24px;">
                <h4 id="qc-company" style="color: var(--primary); font-family: 'Outfit'; margin-bottom: 6px;">Apex Industries</h4>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; font-size: 12px; color: var(--text-muted);">
                    <div><strong>GSTIN:</strong> <span id="qc-gstin">-</span></div>
                    <div><strong>Email:</strong> <span id="qc-email">-</span></div>
                    <div><strong>Billing Address:</strong> <span id="qc-address">-</span></div>
                </div>
            </div>

            <!-- Items Table -->
            <h3 style="font-family: 'Outfit'; font-size: 15px; margin-bottom: 12px; color: var(--text-primary);">Line Items</h3>
            <table class="items-table" id="quote-items-table">
                <thead>
                    <tr>
                        <th style="width: 40%;">Item Description</th>
                        <th style="width: 10%;">Qty</th>
                        <th style="width: 15%;">Rate (Rs. )</th>
                        <th style="width: 15%;">Taxable Value (Rs. )</th>
                        <th style="width: 15%;">GST Rate</th>
                        <th style="width: 5%;"></th>
                    </tr>
                </thead>
                <tbody id="quote-items-tbody">
                    <!-- Rows added dynamically -->
                </tbody>
            </table>

            <button type="button" class="btn btn-secondary" style="margin-bottom: 24px;" onclick="addQuotationRow()"><i data-lucide="plus" style="width: 16px;"></i> Add Row</button>

            <!-- Summary & Math Details -->
            <div class="summary-block-wrapper">
                <div class="summary-block">
                    <div class="summary-row">
                        <span>Subtotal (Taxable):</span>
                        <strong id="quote-subtotal">Rs. 0.00</strong>
                    </div>
                    <div class="summary-row">
                        <span>GST Amount:</span>
                        <strong id="quote-gst">Rs. 0.00</strong>
                    </div>
                    <div class="summary-row grand-total">
                        <span>Grand Total:</span>
                        <strong id="quote-grand-total">Rs. 0.00</strong>
                    </div>
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-primary" onclick="openEmailModal()"><i data-lucide="mail"></i> Save & Email Quotation</button>
                <button type="submit" class="btn btn-secondary"><i data-lucide="save"></i> Save Quotation</button>
            </div>
        </form>
    </div>
</div>

<script>
    let presetClientId = "<?php echo $preset_client_id; ?>";

    async function refreshClientDropdowns() {
        try {
            const response = await fetch('api.php?api=search_clients');
            const clients = await response.json();
            if (!Array.isArray(clients)) return;
            
            const quoteSelect = document.getElementById('quote-client-select');
            const optHtml = clients.map(c => `<option value="${c.id}">${c.company_name} (${c.contact_name})</option>`).join('');
            quoteSelect.innerHTML = '<option value="" disabled selected>Choose client account...</option>' + optHtml;
            
            if (presetClientId) {
                quoteSelect.value = presetClientId;
                autofillQuoteClient();
            }
        } catch (err) {
            console.warn('refreshClientDropdowns failed: ', err);
        }
    }

    async function autofillQuoteClient() {
        const clientId = document.getElementById('quote-client-select').value;
        if (!clientId) return;
        
        try {
            const response = await fetch(`api.php?api=client_details&id=${clientId}`);
            const c = await response.json();
            
            document.getElementById('quote-client-details-card').style.display = 'block';
            document.getElementById('qc-company').innerText = c.company_name;
            document.getElementById('qc-gstin').innerText = c.gstin || 'No GSTIN Entered';
            document.getElementById('qc-email').innerText = c.email;
            document.getElementById('qc-address').innerText = `${c.address_line1}, ${c.city}, ${c.state} - ${c.pincode}`;
        } catch (err) {
            showNotification('Autofill metadata mapping failed.', 'error');
        }
    }

    function resetQuotationForm() {
        document.getElementById('quotation-builder-form').reset();
        document.getElementById('quote-items-tbody').innerHTML = '';
        document.getElementById('quote-client-details-card').style.display = 'none';
        document.getElementById('quote-number-display').value = 'Auto-generated on Save';
        
        addQuotationRow();
        calculateQuotationTotals();
    }

    function addQuotationRow() {
        const tbody = document.getElementById('quote-items-tbody');
        const rowId = 'row-' + Date.now();
        
        const tr = document.createElement('tr');
        tr.id = rowId;
        tr.innerHTML = `
            <td><input type="text" placeholder="Item/Service name description..." required class="item-name"></td>
            <td><input type="number" min="1" value="1" required class="item-qty" oninput="calculateRowMath('${rowId}')"></td>
            <td><input type="number" min="0.01" step="0.01" placeholder="0.00" required class="item-rate" oninput="calculateRowMath('${rowId}')"></td>
            <td><input type="text" readonly value="Rs. 0.00" class="item-taxval" style="background:#f1f5f9; font-weight: 500;"></td>
            <td>
                <select class="item-gst" onchange="calculateRowMath('${rowId}')">
                    <option value="0">0% Exempt</option>
                    <option value="5">5% SGST+CGST</option>
                    <option value="12">12% Standard</option>
                    <option value="18" selected>18% Standard</option>
                    <option value="28">28% Premium</option>
                </select>
            </td>
            <td><button type="button" class="delete-row-btn" onclick="removeQuotationRow('${rowId}')"><i data-lucide="trash-2" style="width:16px;"></i></button></td>
        `;
        
        tbody.appendChild(tr);
        lucide.createIcons();
        calculateQuotationTotals();
    }

    function removeQuotationRow(rowId) {
        const row = document.getElementById(rowId);
        const tbody = document.getElementById('quote-items-tbody');
        
        if (tbody.children.length > 1) {
            row.remove();
            calculateQuotationTotals();
        } else {
            showNotification('At least one item line must exist in quotes.', 'warning');
        }
    }

    function calculateRowMath(rowId) {
        const row = document.getElementById(rowId);
        const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
        const rate = parseFloat(row.querySelector('.item-rate').value) || 0;
        
        const taxVal = qty * rate;
        row.querySelector('.item-taxval').value = formatIndianCurrency(taxVal);
        
        calculateQuotationTotals();
    }

    function calculateQuotationTotals() {
        let subtotal = 0;
        let totalGst = 0;
        
        document.querySelectorAll('#quote-items-tbody tr').forEach(row => {
            const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
            const rate = parseFloat(row.querySelector('.item-rate').value) || 0;
            const gstRate = parseFloat(row.querySelector('.item-gst').value) || 0;
            
            const taxVal = qty * rate;
            const gstVal = taxVal * (gstRate / 100);
            
            subtotal += taxVal;
            totalGst += gstVal;
        });
        
        const grandTotal = subtotal + totalGst;
        
        document.getElementById('quote-subtotal').innerText = formatIndianCurrency(subtotal);
        document.getElementById('quote-gst').innerText = formatIndianCurrency(totalGst);
        document.getElementById('quote-grand-total').innerText = formatIndianCurrency(grandTotal);
    }

    async function saveQuotation(e) {
        e.preventDefault();
        
        const clientId = document.getElementById('quote-client-select').value;
        if (!clientId) {
            showNotification('Please select a client account.', 'warning');
            return;
        }
        
        const items = [];
        let errorFlag = false;
        
        let subtotal = 0;
        let totalGst = 0;
        
        document.querySelectorAll('#quote-items-tbody tr').forEach(row => {
            const name = row.querySelector('.item-name').value.trim();
            const qty = parseInt(row.querySelector('.item-qty').value) || 0;
            const rate = parseFloat(row.querySelector('.item-rate').value) || 0;
            const gstRate = parseFloat(row.querySelector('.item-gst').value) || 0;
            
            if (name === '' || qty <= 0 || rate <= 0) {
                errorFlag = true;
            }
            
            const taxVal = qty * rate;
            const gstVal = taxVal * (gstRate / 100);
            
            subtotal += taxVal;
            totalGst += gstVal;
            
            items.push({
                name: name,
                qty: qty,
                rate: rate,
                gst_rate: gstRate,
                total: taxVal + gstVal
            });
        });
        
        if (errorFlag) {
            showNotification('Please verify all row descriptions, rates, and values.', 'warning');
            return;
        }
        
        const grandTotal = subtotal + totalGst;
        
        const payload = new URLSearchParams({
            client_id: clientId,
            subtotal: subtotal.toFixed(2),
            gst_amount: totalGst.toFixed(2),
            total_amount: grandTotal.toFixed(2),
            items_json: JSON.stringify(items)
        });
        
        try {
            const response = await fetch('api.php?api=save_quotation', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: payload.toString()
            });
            
            const data = await response.json();
            
            if (response.ok && data.success) {
                showNotification(data.message, 'success');
                
                setTimeout(() => {
                    location.href = 'quotation_list.php';
                }, 1000);
            } else {
                showNotification(data.error || 'Failed to save quotation.', 'error');
            }
        } catch (err) {
            showNotification('Quotation save error.', 'error');
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        refreshClientDropdowns();
        resetQuotationForm();
    });
</script>

<!-- Email Quotation Modal -->
<div id="email-quotation-modal" class="modal" style="display:none;">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3>Save & Email Quotation</h3>
            <button onclick="closeEmailModal()" style="background:none;border:none;cursor:pointer;"><i data-lucide="x"></i></button>
        </div>
        <form onsubmit="saveAndEmailQuotation(event)">
            <div class="form-group" style="margin-bottom: 15px;">
                <label>Email Subject</label>
                <input type="text" id="eq-subject" class="form-control" required style="width:100%; padding: 8px; border-radius: 4px; border: 1px solid #cbd5e1;" value="Your Quotation Proposal">
            </div>
            <div class="form-group" style="margin-bottom: 15px;">
                <label>Email Body / Message</label>
                <textarea id="eq-body" class="form-control" required rows="4" style="width:100%; padding: 8px; border-radius: 4px; border: 1px solid #cbd5e1;">Please find the attached quotation proposal. Let us know if you have any questions.</textarea>
            </div>
            <div style="text-align: right;">
                <button type="button" class="btn btn-secondary" onclick="closeEmailModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="eq-submit-btn">Save & Send Email</button>
            </div>
        </form>
    </div>
</div>

<div id="hidden-pdf-template" style="display:none;"></div>

<script>
    function openEmailModal() {
        const clientId = document.getElementById('quote-client-select').value;
        if (!clientId) {
            showNotification('Please select a client account first.', 'warning');
            return;
        }
        document.getElementById('email-quotation-modal').style.display = 'flex';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function closeEmailModal() {
        document.getElementById('email-quotation-modal').style.display = 'none';
    }

    async function saveAndEmailQuotation(e) {
        e.preventDefault();
        const btn = document.getElementById('eq-submit-btn');
        btn.disabled = true;
        btn.innerText = 'Saving & Generating PDF...';
        
        // 1. Save Quotation Silently
        const clientId = document.getElementById('quote-client-select').value;
        const items = [];
        let errorFlag = false;
        let subtotal = 0; let totalGst = 0;
        
        document.querySelectorAll('#quote-items-tbody tr').forEach(row => {
            const name = row.querySelector('.item-name').value.trim();
            const qty = parseInt(row.querySelector('.item-qty').value) || 0;
            const rate = parseFloat(row.querySelector('.item-rate').value) || 0;
            const gstRate = parseFloat(row.querySelector('.item-gst').value) || 0;
            if (name === '' || qty <= 0 || rate <= 0) errorFlag = true;
            const taxVal = qty * rate;
            const gstVal = taxVal * (gstRate / 100);
            subtotal += taxVal;
            totalGst += gstVal;
            items.push({ name, qty, rate, gst_rate: gstRate, total: taxVal + gstVal });
        });
        
        if (errorFlag) {
            showNotification('Please verify all row descriptions, rates, and values.', 'warning');
            btn.disabled = false; btn.innerText = 'Save & Send Email';
            return;
        }
        
        const grandTotal = subtotal + totalGst;
        const payload = new URLSearchParams({
            client_id: clientId,
            subtotal: subtotal.toFixed(2),
            gst_amount: totalGst.toFixed(2),
            total_amount: grandTotal.toFixed(2),
            items_json: JSON.stringify(items)
        });
        
        let savedData;
        try {
            const res = await fetch('api.php?api=save_quotation', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: payload.toString()
            });
            savedData = await res.json();
            if (!savedData.success) {
                showNotification(savedData.error || 'Failed to save quotation.', 'error');
                btn.disabled = false; btn.innerText = 'Save & Send Email';
                return;
            }
        } catch (err) {
            showNotification('Error saving quotation.', 'error');
            btn.disabled = false; btn.innerText = 'Save & Send Email';
            return;
        }

        // 2. Fetch Client & Company Data for PDF Generation
        try {
            const cliRes = await fetch(`api.php?api=client_details&id=${clientId}`);
            const cli = await cliRes.json();
            
            const qListRes = await fetch(`api.php?api=quotation_list`);
            const qList = await qListRes.json();
            const companyProfile = qList.company_profile;
            
            // 3. Render HTML Template
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
                    <td style="border: 1px solid #cbd5e1; padding: 10px; text-align: right;">${item.rate.toFixed(2)}</td>
                    <td style="border: 1px solid #cbd5e1; padding: 10px; text-align: center;">${item.gst_rate}%</td>
                    <td style="border: 1px solid #cbd5e1; padding: 10px; text-align: right; font-weight:600;">${item.total.toFixed(2)}</td>
                </tr>
            `).join('');
            
            let isSameState = (cli && companyProfile && cli.state && companyProfile.state && cli.state.trim().toLowerCase() === companyProfile.state.trim().toLowerCase());
            let cgstAmount = isSameState ? (totalGst / 2) : 0;
            let sgstAmount = isSameState ? (totalGst / 2) : 0;
            let igstAmount = isSameState ? 0 : totalGst;
            
            let taxBreakdownHtml = isSameState ? `
                <tr><td style="padding: 6px 0; color:#64748b;">CGST:</td><td style="padding: 6px 0; text-align: right; font-weight:600;">${cgstAmount.toFixed(2)}</td></tr>
                <tr><td style="padding: 6px 0; color:#64748b; border-bottom: 1px solid #cbd5e1;">SGST:</td><td style="padding: 6px 0; text-align: right; font-weight:600; border-bottom: 1px solid #cbd5e1;">${sgstAmount.toFixed(2)}</td></tr>
            ` : `
                <tr><td style="padding: 6px 0; color:#64748b; border-bottom: 1px solid #cbd5e1;">IGST:</td><td style="padding: 6px 0; text-align: right; font-weight:600; border-bottom: 1px solid #cbd5e1;">${igstAmount.toFixed(2)}</td></tr>
            `;
            
            let bankDetailsHtml = (companyProfile && companyProfile.bank_name && companyProfile.account_number) ? `
                <div style="margin-top: 15px; border: 1px dashed #cbd5e1; padding: 10px; border-radius: 6px; background-color: #f8fafc;">
                    <strong style="color:#ea580c; font-size: 10px; text-transform: uppercase;">Bank Account Details:</strong>
                    <div style="font-size:10px; color:#475569; margin-top: 4px; line-height: 1.4;">
                        <strong>Bank Name:</strong> ${companyProfile.bank_name}<br>
                        <strong>Account Number:</strong> ${companyProfile.account_number}<br>
                        <strong>IFSC Code:</strong> ${companyProfile.ifsc_code}
                    </div>
                </div>
            ` : '';
            
            let dateObj = new Date();
            let validObj = new Date(dateObj.getTime() + (30 * 24 * 60 * 60 * 1000));
            
            template.innerHTML = `
                <div style="display:flex; justify-content: space-between; align-items:flex-start; margin-bottom: 30px; border-bottom: 2px solid #ea580c; padding-bottom: 20px;">
                    <div>
                        <div style="font-size:28px; font-weight:800; color: #ea580c; letter-spacing: -0.5px;">QUOTATION</div>
                        <div style="font-size:14px; font-weight:600; color: #475569; margin-top:4px;">#${savedData.quotation_number}</div>
                    </div>
                    <div style="text-align: right; font-size: 12px; color: #334155; line-height: 1.4;">
                        <strong style="font-size:18px; color: #0f172a;">${companyProfile ? companyProfile.company_name : 'Our Company'}</strong><br>
                        ${(companyProfile && companyProfile.address_line1) ? companyProfile.address_line1 + '<br>' : ''}
                        ${(companyProfile && companyProfile.city) ? companyProfile.city + ', ' + companyProfile.state + ' - ' + companyProfile.pincode + '<br>' : ''}
                        ${(companyProfile && companyProfile.gstin) ? '<strong>GSTIN:</strong> ' + companyProfile.gstin + '<br>' : ''}
                        ${(companyProfile && companyProfile.email) ? '<strong>Email:</strong> ' + companyProfile.email : ''}
                    </div>
                </div>
                
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-bottom: 40px;">
                    <div>
                        <strong style="text-transform: uppercase; color:#ea580c; font-size:11px;">Quotation Prepared For:</strong>
                        <div style="font-size:15px; font-weight:700; color: #0f172a; margin: 6px 0 4px 0;">${cli ? cli.company_name : 'Unknown Client'}</div>
                        <div style="color: #475569; line-height: 1.4;">
                            <strong>Contact:</strong> ${cli ? cli.contact_name : ''} (${cli ? cli.designation : ''})<br>
                            <strong>Email:</strong> ${cli ? cli.email : ''} | <strong>Mobile:</strong> ${cli ? cli.mobile : ''}<br>
                            <strong>GSTIN:</strong> ${cli && cli.gstin ? cli.gstin : 'Not Provided'}<br>
                            <strong>Address:</strong> ${cli ? cli.address_line1 : ''}, ${cli ? cli.city : ''}, ${cli ? cli.state : ''}
                        </div>
                    </div>
                    <div style="text-align: right; color: #475569; line-height: 1.4;">
                        <strong style="text-transform: uppercase; color:#ea580c; font-size:11px;">Quotation Information:</strong>
                        <div style="margin-top: 6px;">
                            <strong>Date Issued:</strong> ${dateObj.toLocaleDateString('en-IN')}<br>
                            <strong>Valid Until:</strong> ${validObj.toLocaleDateString('en-IN')} (30 Days)<br>
                            <strong>Prepared By:</strong> ${companyProfile ? companyProfile.contact_person : 'Admin'}
                        </div>
                    </div>
                </div>
                
                <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px;">
                    <thead>
                        <tr style="background-color: #f1f5f9;">
                            <th style="border: 1px solid #cbd5e1; padding: 10px; text-align: center; color: #0f172a; font-weight: 600; width: 5%;">#</th>
                            <th style="border: 1px solid #cbd5e1; padding: 10px; text-align: left; color: #0f172a; font-weight: 600;">Description</th>
                            <th style="border: 1px solid #cbd5e1; padding: 10px; text-align: center; color: #0f172a; font-weight: 600; width: 10%;">Qty</th>
                            <th style="border: 1px solid #cbd5e1; padding: 10px; text-align: right; color: #0f172a; font-weight: 600; width: 15%;">Rate</th>
                            <th style="border: 1px solid #cbd5e1; padding: 10px; text-align: center; color: #0f172a; font-weight: 600; width: 10%;">GST %</th>
                            <th style="border: 1px solid #cbd5e1; padding: 10px; text-align: right; color: #0f172a; font-weight: 600; width: 15%;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rowsHtml}
                    </tbody>
                </table>
                
                <div style="display:flex; justify-content: space-between; align-items:flex-start;">
                    <div style="width: 50%;">
                        <strong style="font-size:11px; color:#ea580c; text-transform:uppercase;">Terms & Conditions:</strong>
                        <ul style="font-size:10px; color:#64748b; padding-left:15px; margin-top:6px; line-height:1.5;">
                            <li>Payment is due within 15 days of invoice generation.</li>
                            <li>This quotation is valid for 30 days from the date of issue.</li>
                            <li>Taxes are calculated based on current GST rates.</li>
                        </ul>
                        ${bankDetailsHtml}
                    </div>
                    <div style="width: 40%;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr><td style="padding: 6px 0; color:#64748b;">Subtotal:</td><td style="padding: 6px 0; text-align: right; font-weight:600;">${subtotal.toFixed(2)}</td></tr>
                            ${taxBreakdownHtml}
                            <tr><td style="padding: 10px 0 0 0; font-size: 16px; font-weight:800; color:#ea580c; border-top: 1px solid #cbd5e1;">Grand Total:</td><td style="padding: 10px 0 0 0; text-align: right; font-size: 16px; font-weight:800; color:#ea580c; border-top: 1px solid #cbd5e1;">${grandTotal.toFixed(2)}</td></tr>
                        </table>
                    </div>
                </div>
            `;
            
            const printContainer = document.getElementById('hidden-pdf-template');
            printContainer.innerHTML = '';
            printContainer.appendChild(template);
            
            const opt = {
                margin:       10,
                filename:     'quotation.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2 },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            
            // 4. Generate PDF Blob
            btn.innerText = 'Sending Email...';
            html2pdf().set(opt).from(template).output('blob').then(async function(pdfBlob) {
                try {
                    // 5. Send via Email API
                    const subject = document.getElementById('eq-subject').value;
                    const body = document.getElementById('eq-body').value;
                    
                    const fd = new FormData();
                    fd.append('client_id', clientId);
                    fd.append('quote_id', savedData.quotation_id);
                    fd.append('subject', subject);
                    fd.append('body', body);
                    fd.append('pdf_blob', pdfBlob, \`Quotation_\${savedData.quotation_number}.pdf\`);
                    
                    const emailRes = await fetch('api.php?api=send_quotation_email', {
                        method: 'POST',
                        body: fd
                    });
                    
                    const emailData = await emailRes.json();
                    
                    if (emailRes.ok && emailData.success) {
                        showNotification(emailData.message, 'success');
                        setTimeout(() => {
                            location.href = 'quotation_list.php';
                        }, 1500);
                    } else {
                        showNotification(emailData.error || 'Quotation saved, but failed to send email.', 'warning');
                        btn.disabled = false; btn.innerText = 'Save & Send Email';
                        closeEmailModal();
                    }
                } catch (innerErr) {
                    console.error(innerErr);
                    showNotification('Error communicating with server while sending email.', 'error');
                    btn.disabled = false; btn.innerText = 'Save & Send Email';
                }
            }).catch(function(pdfErr) {
                console.error(pdfErr);
                showNotification('Error generating PDF document.', 'error');
                btn.disabled = false; btn.innerText = 'Save & Send Email';
            });
            
        } catch (err) {
            console.error(err);
            showNotification('Error generating PDF or communicating with server.', 'error');
            btn.disabled = false; btn.innerText = 'Save & Send Email';
        }
    }
</script>

<?php require_once 'footer.php'; ?>
