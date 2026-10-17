    </main>

    <!-- Visual Container holding printable invoice layouts dynamically -->
    <div id="invoice-print-container"></div>

    <!-- Template Preview Modal -->
    <div id="template-preview-modal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h2> Template Preview</h2>
                <span class="close" onclick="closeTemplatePreviewModal()">&times;</span>
            </div>
            <div class="modal-body" id="template-preview-body" style="white-space: pre-wrap; font-size: 14px; color: var(--text-primary); padding: 15px 0;">
            </div>
            <div class="modal-footer" id="template-preview-footer" style="padding-top: 15px; border-top: 1px solid var(--border); display: none;">
            </div>
        </div>
    </div>
    
    <!-- Advanced Global Reminder Modal -->
    <div id="reminder-modal" class="modal" style="z-index: 100000;">
        <div class="modal-content" style="max-width:550px;">
            <div class="modal-header">
                <h2 style="display:flex; align-items:center; gap:8px;"><i data-lucide="bell-ring" style="width:20px;height:20px;color:var(--primary);"></i> Set Reminder</h2>
                <span class="close" onclick="closeReminderModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="reminder-form" onsubmit="handleGlobalReminderSubmit(event)">
                    <input type="hidden" id="rem_lead_type" name="reference_type">
                    <input type="hidden" id="rem_lead_id" name="reference_id">
                    <input type="hidden" id="rem_ref_label" name="reference_label">
                    
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label style="font-size:13px; font-weight:600; color:var(--text-primary);">Title / Subject *</label>
                        <input type="text" id="rem_title" name="title" required placeholder="e.g. Call back regarding pricing" class="field-input">
                    </div>
                    
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                        <div class="form-group">
                            <label style="font-size:13px; font-weight:600; color:var(--text-primary);">Date & Time *</label>
                            <input type="datetime-local" id="rem_date" name="remind_at" required class="field-input">
                        </div>
                        <div class="form-group">
                            <label style="font-size:13px; font-weight:600; color:var(--text-primary);">Priority</label>
                            <select id="rem_priority" name="priority" class="field-input">
                                <option value="High">High (Urgent)</option>
                                <option selected value="Medium">Medium (Standard)</option>
                                <option value="Low">Low</option>
                            </select>
                        </div>
                    </div>
                    
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                        <div class="form-group">
                            <label style="font-size:13px; font-weight:600; color:var(--text-primary);">Category</label>
                            <select id="rem_category" name="reminder_category" class="field-input">
                                <option value="Follow-up">Follow-up</option>
                                <option value="Call Back">Call Back</option>
                                <option value="Bank Visit">Bank Visit</option>
                                <option value="Document Chase">Document Chase</option>
                                <option value="Payout Follow-up">Payout Follow-up</option>
                                <option value="Referral Meeting">Referral Meeting</option>
                                <option value="Field Visit">Field Visit</option>
                            </select>
                        </div>
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'): ?>
                        <div class="form-group">
                            <label style="font-size:13px; font-weight:600; color:var(--text-primary);">Assign To</label>
                            <select id="rem_assigned" name="assigned_to" class="field-input">
                                <option value="<?= htmlspecialchars($_SESSION['username'] ?? '') ?>"><?= htmlspecialchars($_SESSION['username'] ?? '') ?> (Me)</option>
                                <?php 
                                    if(isset($db)) {
                                        $all_staff = $db->query("SELECT username FROM users WHERE is_active=1 ORDER BY username")->fetchAll(PDO::FETCH_COLUMN);
                                        foreach ($all_staff as $st) {
                                            if ($st !== ($_SESSION['username'] ?? '')) echo "<option value=\"".htmlspecialchars($st)."\">".htmlspecialchars($st)."</option>";
                                        }
                                    }
                                ?>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group" style="margin-bottom: 20px;">
                        <label style="font-size:13px; font-weight:600; color:var(--text-primary);">Notes (Optional)</label>
                        <textarea id="rem_notes" name="notes" rows="3" placeholder="Add specific task remarks..." class="field-input"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%; padding:12px; font-weight:600; font-size:14px; display:flex; justify-content:center; align-items:center; gap:8px;"><i data-lucide="check-circle"></i> Save Professional Reminder</button>
                </form>
            </div>
        </div>
    </div>
    <style>
    .field-input { width: 100%; padding: 10px 14px; border: 1px solid var(--border); border-radius: 8px; font-family: 'Outfit', sans-serif; font-size: 14px; background: #fff; transition: all 0.2s; box-sizing: border-box; }
    .field-input:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px rgba(15, 23, 42, 0.05); }
    </style>

    <!-- Notification Toast System -->
    <div class="toast-container" id="toast-container"></div>

    <!-- Bulk Upload Modal -->
    <div id="bulk-upload-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2> Upload Leads Data</h2>
                <span class="close" onclick="closeBulkUploadModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="form-group" style="text-align:center; margin-bottom: 20px;">
                    <label for="bulk-upload-file" class="btn btn-primary" style="display:inline-flex; align-items:center; justify-content:center; cursor:pointer; padding: 14px 28px; font-size: 16px; border-radius: 8px; width: 100%;">
                        <i data-lucide="upload-cloud" style="width:20px;height:20px;margin-right:8px;"></i>
                        Select File to Upload
                    </label>
                    <input type="file" id="bulk-upload-file" accept=".csv, .xls, .xlsx" onchange="handleBulkFileSelect(event)" style="display:none;">
                    <div style="margin-top:15px; display:flex; justify-content:space-between; align-items:center;">
                        <p style="font-size:12px;color:var(--text-light);margin:0;">Supported formats: CSV, XLS, XLSX</p>
                        <button onclick="downloadBulkTemplate()" class="btn btn-secondary" style="font-size:12px; padding:6px 10px; display:flex; align-items:center; gap:4px;"><i data-lucide="download" style="width:14px;height:14px;"></i> Download Format</button>
                    </div>
                </div>
                
                <div id="bulk-preview-container" style="display:none; margin-top:15px; border-top: 1px solid var(--border); padding-top: 15px;">
                    <p style="font-weight:600; color:var(--success); text-align:center; margin-bottom:15px;"> <span id="bulk-record-count">0</span> records parsed successfully.</p>
                    
                    <div style="background:var(--bg-main); border-radius:8px; padding: 15px;">
                        <label style="display:flex; align-items:center; gap:8px; font-weight:600; cursor:pointer;">
                            <input type="checkbox" id="bulk-split-checkbox" onchange="toggleSplitOptions()"> Smart Split Data (Optional)
                        </label>
                        
                        <div id="split-options-container" style="display:none; margin-top:10px; padding-left:25px;">
                            <label style="display:block; margin-bottom:5px;"><input type="radio" name="split_type" value="random" onchange="renderSplitUI()">  Split Randomly (Even/Odd)</label>
                            <label style="display:block; margin-bottom:5px;"><input type="radio" name="split_type" value="serial" onchange="renderSplitUI()">  Split by Serial</label>
                            <label style="display:block; margin-bottom:5px;"><input type="radio" name="split_type" value="location" onchange="renderSplitUI()">  Split by Location</label>
                            
                            <div id="dynamic-split-ui" style="margin-top:15px; padding:10px; border:1px solid var(--border); border-radius:6px; background:var(--bg-card); display:none;">
                            </div>
                        </div>
                    </div>
                    
                    <button class="btn btn-primary" id="btn-save-bulk" style="margin-top:20px; width:100%; padding:12px; font-size:16px;" onclick="processBulkUpload()">Save All Records</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Lucide icons initialization
        lucide.createIcons();

        // Application Base Path helper for subfolder routes (e.g. /rm/, /staff/)
        function getAppBasePath() {
            return (typeof window !== 'undefined' && window.BASE_PATH) ? window.BASE_PATH : '';
        }

        // Modals global management
        function closeTemplatePreviewModal() {
            document.getElementById("template-preview-modal").style.display = "none";
        }
        
        function showTemplatePreview(id) {
            const t = window.globalEmailTemplates ? window.globalEmailTemplates.find(x => x.id == id) : null;
            if(!t) return;
            let bodyContent = `<strong>Subject:</strong> ${t.subject}\n\n${t.body}`;
            document.getElementById("template-preview-body").innerHTML = bodyContent;
            
            const footer = document.getElementById("template-preview-footer");
            if(t.attachment_name) {
                footer.style.display = "block";
                footer.innerHTML = `<a href="uploads/${t.attachment_name}" target="_blank" class="btn btn-secondary" style="text-decoration:none;"> Download Attachment</a>`;
            } else {
                footer.style.display = "none";
            }
            document.getElementById("template-preview-modal").style.display = "flex";
        }

        async function approveDeleteTemplate(id) {
            if(!confirm("Permanently delete this template?")) return;
            let fd = new FormData(); fd.append("id", id);
            let res = await fetch(getAppBasePath() + "api.php?api=approve_delete_template", {method:"POST", body:fd});
            let json = await res.json();
            showNotification(json.message || (json.error ? "Error" : "Success"), json.success ? "success" : "error");
            if(json.success && typeof loadTemplatesList === 'function') loadTemplatesList();
        }
        
        async function rejectDeleteTemplate(id) {
            let fd = new FormData(); fd.append("id", id);
            let res = await fetch(getAppBasePath() + "api.php?api=reject_delete_template", {method:"POST", body:fd});
            let json = await res.json();
            showNotification(json.message || (json.error ? "Error" : "Success"), json.success ? "success" : "error");
            if(json.success && typeof loadTemplatesList === 'function') loadTemplatesList();
        }
        
        async function approveDeletePpt(id) {
            if(!confirm("Permanently delete this presentation?")) return;
            let fd = new FormData(); fd.append("id", id);
            let res = await fetch(getAppBasePath() + "api.php?api=approve_delete_ppt", {method:"POST", body:fd});
            let json = await res.json();
            showNotification(json.message || (json.error ? "Error" : "Success"), json.success ? "success" : "error");
            if(json.success && typeof loadPptsList === 'function') loadPptsList();
        }
        
        async function rejectDeletePpt(id) {
            let fd = new FormData(); fd.append("id", id);
            let res = await fetch(getAppBasePath() + "api.php?api=reject_delete_ppt", {method:"POST", body:fd});
            let json = await res.json();
            showNotification(json.message || (json.error ? "Error" : "Success"), json.success ? "success" : "error");
            if(json.success && typeof loadPptsList === 'function') loadPptsList();
        }

        function openReminderModal(type, id, label = '') {
            if(document.getElementById('rem_lead_type')) document.getElementById('rem_lead_type').value = type;
            if(document.getElementById('rem_lead_id')) document.getElementById('rem_lead_id').value = id;
            if(document.getElementById('rem_ref_label')) document.getElementById('rem_ref_label').value = label;
            if(document.getElementById('rem_title')) document.getElementById('rem_title').value = "Follow-up: " + label;
            if(document.getElementById('reminder-modal')) document.getElementById('reminder-modal').style.display = 'flex';
            if(typeof lucide !== 'undefined') lucide.createIcons();
        }
        
        function closeReminderModal() {
            document.getElementById('reminder-modal').style.display = 'none';
        }

        async function handleGlobalReminderSubmit(e) {
            e.preventDefault();
            let fd = new FormData(e.target);
            fd.append("api", "save_reminder");
            let res = await fetch(getAppBasePath() + "api.php?api=save_reminder", {method:"POST", body:fd});
            let json = await res.json();
            if(json.success) {
                showNotification("Reminder set successfully", "success");
                closeReminderModal();
                if (typeof loadReminders === 'function') {
                    loadReminders();
                }
            } else {
                showNotification(json.error || "Failed to save reminder", "error");
            }
        }

        // Bulk uploads UI elements mapping
        let bulkParsedData = [];
        let systemUsersList = [];

        function openBulkUploadModal(destination = 'leads') {
            window.bulkUploadDestination = destination;
            document.getElementById('bulk-upload-modal').style.display = 'flex';
            document.getElementById('bulk-upload-file').value = '';
            document.getElementById('bulk-preview-container').style.display = 'none';
            document.getElementById('bulk-split-checkbox').checked = false;
            toggleSplitOptions();
            
            if(systemUsersList.length === 0) {
                fetch(getAppBasePath() + 'api.php?api=get_users')
                    .then(res => res.json())
                    .then(data => {
                        if(Array.isArray(data)) systemUsersList = data;
                    })
                    .catch(err => console.error("Error pre-fetching users: ", err));
            }
        }

        function closeBulkUploadModal() {
            document.getElementById('bulk-upload-modal').style.display = 'none';
        }

        function handleBulkFileSelect(e) {
            const file = e.target.files[0];
            if(!file) return;
            const reader = new FileReader();
            reader.onload = function(evt) {
                try {
                    const data = evt.target.result;
                    const workbook = XLSX.read(data, {type: 'binary'});
                    const firstSheetName = workbook.SheetNames[0];
                    const worksheet = workbook.Sheets[firstSheetName];
                    let json = XLSX.utils.sheet_to_json(worksheet, {defval: ""});
                    
                    bulkParsedData = json.map(row => {
                        return {
                            lead_name: row['Contact Name'] || row['contact_name'] || row['lead_name'] || '',
                            company_name: row['Company Name'] || row['company_name'] || '',
                            mobile: row['Mobile'] || row['mobile'] || row['Phone'] || '',
                            email: row['Email'] || row['email'] || '',
                            lead_source: row['Lead Source'] || row['lead_source'] || 'Cold Call',
                            priority: row['Priority'] || row['priority'] || 'Warm',
                            stage: row['Stage'] || row['stage'] || 'New Lead',
                            assigned_to: row['Assigned To'] || row['assigned_to'] || '',
                            location: row['Location'] || row['location'] || row['City'] || '',
                            notes: row['Notes'] || row['notes'] || ''
                        };
                    }).filter(r => r.lead_name !== '' || r.mobile !== '');
                    
                    document.getElementById('bulk-record-count').innerText = bulkParsedData.length;
                    document.getElementById('bulk-preview-container').style.display = 'block';
                    renderSplitUI();
                } catch(err) {
                    console.error(err);
                    alert("Error parsing file. Ensure it is a valid CSV or Excel file.");
                }
            };
            reader.readAsBinaryString(file);
        }

        function downloadBulkTemplate() {
            const headers = "Contact Name,Company Name,Mobile,Email,Lead Source,Priority,Stage,Assigned To,Location,Notes\n";
            const sampleRow = "John Doe,Acme Corp,9876543210,john@example.com,Cold Call,Warm,New Lead,,New York,Interested in product\n";
            const csvContent = "data:text/csv;charset=utf-8," + headers + sampleRow;
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "BFS Financial Services_Bulk_Upload_Format.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function toggleSplitOptions() {
            const isChecked = document.getElementById('bulk-split-checkbox').checked;
            document.getElementById('split-options-container').style.display = isChecked ? 'block' : 'none';
        }

        function renderSplitUI() {
            const uiContainer = document.getElementById('dynamic-split-ui');
            const splitType = document.querySelector('input[name="split_type"]:checked')?.value;
            if(!splitType) {
                uiContainer.style.display = 'none';
                return;
            }
            uiContainer.style.display = 'block';
            
            let userOptions = `<option value="">-- Select Staff --</option>` + systemUsersList.map(u => `<option value="${u.username}">${u.username}</option>`).join('');
            let multiUserOptions = systemUsersList.map(u => `<option value="${u.username}">${u.username}</option>`).join('');

            if(splitType === 'random') {
                uiContainer.innerHTML = `
                    <p style="font-size:13px;margin-bottom:10px;">Select multiple staff per team (Hold Ctrl/Cmd to select multiple). Even rows to Team A, Odd rows to Team B.</p>
                    <div style="display:flex; gap:10px;">
                        <div style="flex:1;"><label style="font-size:12px;font-weight:600;">Team A (Even rows)</label><select id="split-random-a" multiple style="width:100%;height:100px;padding:6px;border-radius:4px;border:1px solid var(--border);" onchange="updateRandomSplitSelects()">${multiUserOptions}</select></div>
                        <div style="flex:1;"><label style="font-size:12px;font-weight:600;">Team B (Odd rows)</label><select id="split-random-b" multiple style="width:100%;height:100px;padding:6px;border-radius:4px;border:1px solid var(--border);" onchange="updateRandomSplitSelects()">${multiUserOptions}</select></div>
                    </div>
                `;
            } 
            else if(splitType === 'serial') {
                uiContainer.innerHTML = `
                    <p style="font-size:13px;margin-bottom:10px;">Define ranges (e.g., 1-50, 51-100).</p>
                    <div id="serial-ranges-container">
                        <div class="serial-row" style="display:flex;gap:10px;margin-bottom:10px;">
                            <input type="number" placeholder="Start" class="s-start" style="width:70px;padding:4px;border:1px solid var(--border);border-radius:4px;">
                            <input type="number" placeholder="End" class="s-end" style="width:70px;padding:4px;border:1px solid var(--border);border-radius:4px;">
                            <select class="s-staff" style="flex:1;padding:4px;border:1px solid var(--border);border-radius:4px;">${userOptions}</select>
                        </div>
                    </div>
                    <button type="button" class="btn btn-secondary" style="padding:4px 10px;font-size:11px;" onclick="addSerialRow()">+ Add Range</button>
                `;
            }
            else if(splitType === 'location') {
                const locations = [...new Set(bulkParsedData.map(r => String(r.location).trim()).filter(l => l !== ''))];
                if(locations.length === 0) {
                    uiContainer.innerHTML = `<p style="font-size:13px;color:red;">No locations found in the uploaded file.</p>`;
                    return;
                }
                
                let locHTML = `<p style="font-size:13px;margin-bottom:10px;">Assign staff for each location found in file:</p><div id="location-mapping-container">`;
                locations.forEach(loc => {
                    locHTML += `
                        <div class="loc-row" data-loc="${loc}" style="display:flex;gap:10px;margin-bottom:8px;align-items:center;">
                            <span style="flex:1;font-weight:600;font-size:12px;"> ${loc}</span>
                            <select class="l-staff" style="flex:1;padding:4px;border:1px solid var(--border);border-radius:4px;">${userOptions}</select>
                        </div>
                    `;
                });
                locHTML += `</div>`;
                uiContainer.innerHTML = locHTML;
            }
        }
        
        function updateRandomSplitSelects() {
            const teamASelect = document.getElementById('split-random-a');
            const teamBSelect = document.getElementById('split-random-b');
            if(!teamASelect || !teamBSelect) return;
            
            const selectedA = Array.from(teamASelect.selectedOptions).map(opt => opt.value);
            const selectedB = Array.from(teamBSelect.selectedOptions).map(opt => opt.value);
            
            Array.from(teamBSelect.options).forEach(opt => {
                if (selectedA.includes(opt.value)) {
                    opt.disabled = true;
                    opt.selected = false;
                } else {
                    opt.disabled = false;
                }
            });
            
            Array.from(teamASelect.options).forEach(opt => {
                if (selectedB.includes(opt.value)) {
                    opt.disabled = true;
                    opt.selected = false;
                } else {
                    opt.disabled = false;
                }
            });
        }
        
        function addSerialRow() {
            let userOptions = `<option value="">-- Select Staff --</option>` + systemUsersList.map(u => `<option value="${u.username}">${u.username}</option>`).join('');
            const row = document.createElement('div');
            row.className = 'serial-row';
            row.style.cssText = 'display:flex;gap:10px;margin-bottom:10px;';
            row.innerHTML = `
                <input type="number" placeholder="Start" class="s-start" style="width:70px;padding:4px;border:1px solid var(--border);border-radius:4px;">
                <input type="number" placeholder="End" class="s-end" style="width:70px;padding:4px;border:1px solid var(--border);border-radius:4px;">
                <select class="s-staff" style="flex:1;padding:4px;border:1px solid var(--border);border-radius:4px;">${userOptions}</select>
            `;
            document.getElementById('serial-ranges-container').appendChild(row);
        }

        async function processBulkUpload() {
            if(bulkParsedData.length === 0) return showNotification('No data to save.', 'error');
            
            const isChecked = document.getElementById('bulk-split-checkbox').checked;
            let finalData = [...bulkParsedData];
            
            if(isChecked) {
                const splitType = document.querySelector('input[name="split_type"]:checked')?.value;
                if(splitType === 'random') {
                    const teamASelect = document.getElementById('split-random-a');
                    const teamBSelect = document.getElementById('split-random-b');
                    const teamA = Array.from(teamASelect.selectedOptions).map(opt => opt.value);
                    const teamB = Array.from(teamBSelect.selectedOptions).map(opt => opt.value);
                    
                    let aIndex = 0;
                    let bIndex = 0;
                    
                    finalData.forEach((row, idx) => {
                        if (idx % 2 === 0) {
                            if (teamA.length > 0) {
                                row.assigned_to = teamA[aIndex % teamA.length];
                                aIndex++;
                            }
                        } else {
                            if (teamB.length > 0) {
                                row.assigned_to = teamB[bIndex % teamB.length];
                                bIndex++;
                            }
                        }
                    });
                }
                else if(splitType === 'serial') {
                    const rows = document.querySelectorAll('.serial-row');
                    let ranges = [];
                    rows.forEach(r => {
                        ranges.push({
                            start: parseInt(r.querySelector('.s-start').value),
                            end: parseInt(r.querySelector('.s-end').value),
                            staff: r.querySelector('.s-staff').value
                        });
                    });
                    
                    finalData.forEach((row, idx) => {
                        const serialNum = idx + 1;
                        let assigned = row.assigned_to;
                        for(let rng of ranges) {
                            if(rng.staff && !isNaN(rng.start) && !isNaN(rng.end) && serialNum >= rng.start && serialNum <= rng.end) {
                                assigned = rng.staff;
                                break;
                            }
                        }
                        row.assigned_to = assigned;
                    });
                }
                else if(splitType === 'location') {
                    const locRows = document.querySelectorAll('.loc-row');
                    let mapping = {};
                    locRows.forEach(r => {
                        const loc = r.getAttribute('data-loc');
                        const staff = r.querySelector('.l-staff').value;
                        if(staff) mapping[loc] = staff;
                    });
                    
                    finalData.forEach(row => {
                        if(row.location && mapping[row.location.trim()]) {
                            row.assigned_to = mapping[row.location.trim()];
                        }
                    });
                }
            }
            
            const btn = document.getElementById('btn-save-bulk');
            btn.innerText = 'Saving... Please wait';
            btn.disabled = true;
            
            try {
                const fd = new FormData();
                const action = window.bulkUploadDestination === 'pre_leads' ? 'bulk_upload_preleads' : 'bulk_upload_leads';
                fd.append('leads_json', JSON.stringify(finalData));
                
                const res = await fetch(`${getAppBasePath()}api.php?api=${action}`, { method:'POST', body: fd });
                const json = await res.json();
                
                if(json.success) {
                    showNotification(json.message || "Bulk upload successful!", 'success');
                    closeBulkUploadModal();
                    if(typeof loadPreLeads === 'function' && window.bulkUploadDestination === 'pre_leads') {
                        loadPreLeads();
                    } else if(typeof loadLeads === 'function') {
                        loadLeads();
                    }
                } else {
                    showNotification(json.error || 'Failed to bulk upload', 'error');
                }
            } catch(e) {
                console.error(e);
                showNotification('Connection Error during bulk upload.', 'error');
            } finally {
                btn.innerText = 'Save All Records';
                btn.disabled = false;
            }
        }
    </script>

    <!--  Global Confirmation Modal -->
    <div id="confirm-modal" onclick="if(event.target===this)confirmCancel()" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.45);z-index:99999;align-items:center;justify-content:center;">
        <div onclick="event.stopPropagation()" style="background:#fff;border-radius:14px;padding:30px 32px;max-width:380px;width:90%;box-shadow:0 20px 50px rgba(0,0,0,0.2);text-align:center;animation:slideDown 0.25s ease;">
            <div style="width:52px;height:52px;border-radius:50%;background:#fef3c7;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="#f59e0b" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
            </div>
            <h3 id="confirm-modal-title" style="margin:0 0 8px;font-size:17px;font-weight:700;color:#0f172a;">Are you sure?</h3>
            <p id="confirm-modal-msg" style="margin:0 0 24px;font-size:14px;color:#64748b;line-height:1.5;">Do you want to save this update?</p>
            <div style="display:flex;gap:10px;justify-content:center;">
                <button onclick="confirmCancel()" style="flex:1;padding:10px 0;border-radius:8px;border:1px solid #e2e8f0;background:#f8fafc;color:#475569;font-size:14px;font-weight:600;cursor:pointer;">Cancel</button>
                <button id="confirm-modal-yes-btn" onclick="confirmProceed()" style="flex:1;padding:10px 0;border-radius:8px;border:none;background:#f59e0b;color:#fff;font-size:14px;font-weight:700;cursor:pointer;">Yes, Update</button>
            </div>
        </div>
    </div>

    <script>
        let _confirmCallback = null;

        /**
         * Show a confirmation popup.
         * @param {Function} callback - Function to run when user clicks Yes
         * @param {string} title - Popup title (optional)
         * @param {string} message - Popup message (optional)
         * @param {string} yesLabel - Yes button label (optional)
         */
        function showConfirm(callback, title = 'Confirm Update', message = 'Do you want to save this update?', yesLabel = 'Yes, Update') {
            _confirmCallback = callback;
            document.getElementById('confirm-modal-title').innerText = title;
            document.getElementById('confirm-modal-msg').innerText = message;
            document.getElementById('confirm-modal-yes-btn').innerText = yesLabel;
            const modal = document.getElementById('confirm-modal');
            modal.style.display = 'flex';
        }

        function confirmProceed() {
            document.getElementById('confirm-modal').style.display = 'none';
            if (typeof _confirmCallback === 'function') _confirmCallback();
            _confirmCallback = null;
        }

        function confirmCancel() {
            document.getElementById('confirm-modal').style.display = 'none';
            _confirmCallback = null;
        }
        
        function showCustomAlert(title, message) {
            document.getElementById('alert-modal-title').innerText = title;
            document.getElementById('alert-modal-msg').innerText = message;
            document.getElementById('alert-modal').style.display = 'flex';
        }

        function closeCustomAlert() {
            document.getElementById('alert-modal').style.display = 'none';
        }
    </script>
    
    <!-- Global Alert Modal -->
    <div id="alert-modal" onclick="if(event.target===this)closeCustomAlert()" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(15,23,42,0.6);z-index:99999;align-items:center;justify-content:center;backdrop-filter: blur(4px);">
        <div onclick="event.stopPropagation()" style="background:#fff;border-radius:14px;padding:30px 32px;max-width:380px;width:90%;box-shadow:0 20px 50px rgba(0,0,0,0.2);text-align:center;animation:slideDown 0.25s ease;">
            <div style="width:52px;height:52px;border-radius:50%;background:#fee2e2;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="#ef4444" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            </div>
            <h3 id="alert-modal-title" style="margin:0 0 8px;font-size:17px;font-weight:700;color:#0f172a;">Action Blocked</h3>
            <p id="alert-modal-msg" style="margin:0 0 24px;font-size:14px;color:#64748b;line-height:1.5;">Message goes here</p>
            <div style="display:flex;gap:10px;justify-content:center;">
                <button onclick="closeCustomAlert()" style="flex:1;padding:10px 0;border-radius:8px;border:none;background:#0f172a;color:#fff;font-size:14px;font-weight:700;cursor:pointer;">Understood, Go Back</button>
            </div>
        </div>
    </div>
    <!-- Global Reminder Notification Checker -->
    <script>
        (function() {
            async function checkDueReminders() {
                try {
                    // Try fetching from reminders.php api endpoint (as it handles get_reminders)
                    const res = await fetch(getAppBasePath() + 'reminders.php?api=get_reminders');
                    if (!res.ok) return;
                    const data = await res.json();
                    if (!Array.isArray(data)) return;

                    const now = new Date();
                    let notifiedIds = JSON.parse(localStorage.getItem('notified_reminders') || '[]');
                    let newNotifications = [];

                    data.forEach(r => {
                        const dt = new Date(r.remind_at);
                        const diffMins = (now - dt) / 60000;
                        
                        // If task is due (now passed the remind_at time) and is recent (last 24h)
                        if (diffMins >= 0 && diffMins < 1440) { 
                            if (!notifiedIds.includes(r.id)) {
                                newNotifications.push(r);
                                notifiedIds.push(r.id);
                            }
                        }
                    });

                    if (newNotifications.length > 0) {
                        // Keep array size manageable (last 500 ids)
                        if (notifiedIds.length > 500) notifiedIds = notifiedIds.slice(-500);
                        localStorage.setItem('notified_reminders', JSON.stringify(notifiedIds));
                        
                        if (window.Swal) {
                            const Toast = Swal.mixin({
                                toast: true,
                                position: 'top-end',
                                showConfirmButton: false,
                                timer: 8000,
                                timerProgressBar: true,
                                didOpen: (toast) => {
                                    toast.addEventListener('mouseenter', Swal.stopTimer)
                                    toast.addEventListener('mouseleave', Swal.resumeTimer)
                                }
                            });

                            let titleText = newNotifications.length > 1 
                                ? `🔔 You have ${newNotifications.length} reminders due now!`
                                : `🔔 Reminder: ${newNotifications[0].title || newNotifications[0].notes || 'Task Due'}`;

                            Toast.fire({
                                icon: 'info',
                                title: titleText,
                                text: 'Click here to view your Reminders & Tasks',
                                background: '#eff6ff',
                                color: '#1e3a8a'
                            });
                            
                            setTimeout(() => {
                                const popup = Swal.getPopup();
                                if (popup) {
                                    popup.style.cursor = 'pointer';
                                    popup.onclick = () => window.location.href = getAppBasePath() + 'reminders.php';
                                }
                            }, 100);
                        }
                    }
                } catch(e) {
                    console.error("Global reminder check failed", e);
                }
            }

            // Check shortly after load, then every 60 seconds
            setTimeout(checkDueReminders, 3000);
            setInterval(checkDueReminders, 60000);
        })();
    </script>

    <!-- Background Auto-Cron for Document Reminders -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Trigger background cron job safely
            fetch(getAppBasePath() + 'cron_document_reminders.php')
                .then(res => res.text())
                .then(text => {
                    try {
                        const data = JSON.parse(text);
                        if(data && data.success && data.emails_sent > 0) {
                            console.log('Automated doc reminders sent: ' + data.emails_sent);
                        }
                    } catch(e){}
                })
                .catch(() => {});
        });
    </script>

    <!-- Automatic PWA ServiceWorker Cache Auto-Purge -->
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.getRegistrations().then(registrations => {
                for (let reg of registrations) {
                    reg.unregister();
                }
            }).catch(e => {});
            if (window.caches) {
                caches.keys().then(keys => {
                    keys.forEach(key => caches.delete(key));
                }).catch(e => {});
            }
        }
    </script>

    <!-- 10x Field Force Tracker Engine (Strict Enforcement + WakeLock) -->
    <?php if(isset($_SESSION['role']) && $_SESSION['role'] === 'Staff'): ?>
    <div id="gps-blocker" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.92); backdrop-filter:blur(8px); z-index:999999; flex-direction:column; align-items:center; justify-content:center; color:white; padding:20px; text-align:center;">
        <i class="fas fa-map-marker-slash" style="font-size:56px; color:#ef4444; margin-bottom:18px;"></i>
        <h2 style="margin-bottom:10px; font-size:22px; font-weight:800;">Location (GPS) Access Required</h2>
        <p style="margin-bottom:20px; font-size:13.5px; opacity:0.85; max-width:320px; line-height:1.5;">Field Staff On-Duty tracking requires device GPS. Enable Location in your browser / phone settings.</p>
        <div style="display:flex; flex-direction:column; gap:10px; width:100%; max-width:290px;">
            <button onclick="window.location.reload()" style="background:#22c55e; color:white; padding:12px 20px; border:none; border-radius:12px; font-weight:bold; font-size:15px; cursor:pointer; box-shadow:0 4px 12px rgba(34,197,94,0.3);">
                🔄 Refresh Page After Enabling GPS
            </button>
            <button onclick="sessionStorage.setItem('gps_bypass', 'true'); hideGpsBlocker();" style="background:rgba(255,255,255,0.15); border:1px solid rgba(255,255,255,0.3); color:white; padding:10px; border-radius:12px; font-size:13px; font-weight:700; cursor:pointer;">
                🧪 Skip GPS (Testing / PC Mode)
            </button>
        </div>
    </div>

    <script>
        (function() {
            let lastPingTime = 0;
            
            async function getBattery() {
                try {
                    if (navigator.getBattery) {
                        const bat = await navigator.getBattery();
                        return Math.round(bat.level * 100) + '%';
                    }
                } catch(e) {}
                return 'Unknown';
            }
            
            async function sendPing(lat, lon) {
                const now = Date.now();
                if (now - lastPingTime < 30000) return;
                
                const bat = await getBattery();
                const fd = new FormData();
                fd.append('api', 'staff_ping');
                fd.append('lat', lat);
                fd.append('lon', lon);
                fd.append('battery', bat);
                fd.append('status', 'Active');
                
                try {
                    await fetch(getAppBasePath() + 'api.php?api=x', { method: 'POST', body: fd });
                } catch(e) {
                    console.error("Ping failed", e);
                }
                lastPingTime = now;
            }
            
            let wakeLock = null;
            async function requestWakeLock() {
                try {
                    if ('wakeLock' in navigator) {
                        wakeLock = await navigator.wakeLock.request('screen');
                    }
                } catch (err) {}
            }

            function fallbackFetch(lat, lon, bat) {
                const fd = new FormData();
                fd.append('api', 'staff_ping');
                fd.append('lat', lat);
                fd.append('lon', lon);
                fd.append('battery', bat);
                fd.append('status', 'Active');
                
                let basePath = window.location.pathname.includes('/staff/') ? '../' : './';
                fetch(basePath + '?api=staff_ping', { method: 'POST', body: fd }).catch(e=>console.log(e));
            }

            function showGpsBlocker() {
                if (sessionStorage.getItem('gps_bypass') === 'true') return;
                const el = document.getElementById('gps-blocker');
                if (el) el.style.display = 'flex';
            }
            function hideGpsBlocker() {
                const el = document.getElementById('gps-blocker');
                if (el) el.style.display = 'none';
            }

            function startTracker() {
                // Respect Punch-In status
                if (typeof window.TRACKING_ACTIVE !== 'undefined' && window.TRACKING_ACTIVE === false) {
                    return; // Do not track if not punched in
                }
                
                requestWakeLock();
                document.addEventListener('visibilitychange', async () => {
                    if (wakeLock !== null && document.visibilityState === 'visible') {
                        requestWakeLock();
                    }
                });

                if (navigator.geolocation) {
                    // Watch position for continuous updates
                    navigator.geolocation.watchPosition(
                        async (pos) => {
                            hideGpsBlocker();
                            const lat = pos.coords.latitude;
                            const lon = pos.coords.longitude;
                            
                            const now = Date.now();
                            if (now - lastPingTime > 45000) { // Ping every 45s max
                                const bat = await getBattery();
                                fallbackFetch(lat, lon, bat);
                                lastPingTime = now;
                            }
                        },
                        (err) => {
                            console.log('Tracker GPS Error:', err);
                            showGpsBlocker(); // ENFORCE GPS!
                        },
                        { enableHighAccuracy: true, maximumAge: 10000, timeout: 5000 }
                    );
                    
                    // Force a ping every 2 mins even if stationary (in case watchPosition sleeps)
                    setInterval(() => {
                        navigator.geolocation.getCurrentPosition(
                            async (pos) => {
                                hideGpsBlocker();
                                const bat = await getBattery();
                                fallbackFetch(pos.coords.latitude, pos.coords.longitude, bat);
                                lastPingTime = Date.now();
                            },
                            (err) => {
                                showGpsBlocker();
                            }
                        );
                    }, 60000); // 1 minute stationary ping
                } else {
                    alert("Your browser does not support GPS tracking.");
                }
            }
            
            // Wait a few seconds before starting to not block page load
            setTimeout(startTracker, 2000);
        })();
    </script>
    <?php endif; ?>

</body>

</html>
