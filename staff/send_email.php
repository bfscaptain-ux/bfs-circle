<?php
require_once __DIR__ . '/../config.php';
$page_title = 'Communication Center';
$page_subtitle = 'Compose and dispatch simulated customer interaction emails';
require_once __DIR__ . '/header.php';

// Get client_id or email if passed in URL
$preset_client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : '';
$preset_email = isset($_GET['email']) ? $_GET['email'] : '';
?>

<div id="view-send-email" class="view-container">
    <div class="email-form-card">
        <form id="email-sender-form" onsubmit="dispatchEmail(event)" enctype="multipart/form-data">
            <div class="form-grid">
                <div class="form-group">
                    <label class="required">Select Recipient Client</label>
                    <select name="client_id" id="email-to-select" required>
                        <option value="" disabled selected>Choose client account...</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>CC</label>
                    <input type="email" name="cc" placeholder="cc@yourcompany.com">
                </div>
                <div class="form-group">
                    <label class="required">Communication Stage Category</label>
                    <select name="type" required>
                        <option value="Custom Mail" selected>Custom Mail / Updates</option>
                        <option value="Quotation">Quotation Sent</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="required">Dispatched By</label>
                    <input type="text" name="sent_by" value="<?php echo htmlspecialchars($_SESSION['username']); ?>" readonly style="background:#f1f5f9; cursor:not-allowed;" required>
                </div>
                
                <div class="form-group full-width" style="background: var(--bg-main); padding: 12px; border-radius: 8px; border: 1px dashed var(--border);">
                    <label style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span>Load Saved Template (Optional)</span>
                    </label>
                    <select id="email-template-select" onchange="applyEmailTemplate()">
                        <option value="" selected>-- Select a template to auto-fill --</option>
                    </select>
                    <input type="hidden" name="template_id" id="email-template-id-hidden">
                    <div id="email-template-attachment-badge" style="display:none; font-size: 11px; margin-top: 6px; color: var(--success); font-weight: 600;"></div>
                </div>
                
                <div class="form-group full-width" style="background: var(--bg-main); padding: 12px; border-radius: 8px; border: 1px dashed var(--border); margin-top: 10px;">
                    <label style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span>Attach Saved Presentation (Optional)</span>
                    </label>
                    <select name="ppt_id" id="saved-ppt-select">
                        <option value="">-- Select Presentation --</option>
                    </select>
                </div>
                
                <div class="form-group full-width" style="margin-top: 10px;">
                    <label class="required">Subject</label>
                    <input type="text" name="subject" placeholder="Enter clear descriptive email subject" required>
                </div>
                <div class="form-group full-width">
                    <label class="required">Email Message (Rich Text Description)</label>
                    <div id="email-body-editor"></div>
                    <input type="hidden" name="body" id="email-body-hidden">
                </div>
                <div class="form-group">
                    <label>Or Upload Attachment Document</label>
                    <input type="file" name="attachment" style="border: 1px solid #cbd5e1; padding: 8px;">
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i data-lucide="send"></i> Dispatch simulated email</button>
            </div>
        </form>
    </div>
</div>

<script>
    let quillEmailEditor = null;
    let presetClientId = "<?php echo $preset_client_id; ?>";
    let presetEmail = "<?php echo htmlspecialchars($preset_email); ?>";
    window.globalEmailTemplates = [];

    async function refreshClientDropdowns() {
        try {
            // API search_clients is role-aware and returns only clients assigned to the logged-in staff member
            const response = await fetch('../api.php?api=search_clients');
            const clients = await response.json();
            if (!Array.isArray(clients)) return;
            
            const emailSelect = document.getElementById('email-to-select');
            const optHtml = clients.map(c => `<option value="${c.id}" data-email="${c.email}">${c.company_name} (${c.contact_name})</option>`).join('');
            emailSelect.innerHTML = '<option value="" disabled selected>Choose client account...</option>' + optHtml;
            
            if (presetClientId) {
                emailSelect.value = presetClientId;
            } else if (presetEmail) {
                let options = emailSelect.options;
                for (let i = 0; i < options.length; i++) {
                    if (options[i].getAttribute('data-email') === presetEmail) {
                        emailSelect.selectedIndex = i;
                        break;
                    }
                }
            }
        } catch (err) {
            console.warn('refreshClientDropdowns failed: ', err);
        }
    }

    async function loadTemplatesList() {
        try {
            const res = await fetch('../api.php?api=get_templates');
            const data = await res.json();
            if (Array.isArray(data)) {
                window.globalEmailTemplates = data;
                const select = document.getElementById('email-template-select');
                select.innerHTML = '<option value="" selected>-- Select a template to auto-fill --</option>';
                data.forEach(t => {
                    select.innerHTML += `<option value="${t.id}">${t.template_name} [${t.type}]</option>`;
                });
            }
        } catch (e) {
            console.error("Error loading templates:", e);
        }
    }

    async function loadPptsList() {
        try {
            const res = await fetch('../api.php?api=get_ppts');
            const data = await res.json();
            if (Array.isArray(data)) {
                const select = document.getElementById('saved-ppt-select');
                select.innerHTML = '<option value="">-- Select Presentation --</option>';
                data.forEach(p => {
                    select.innerHTML += `<option value="${p.id}">${p.original_name}</option>`;
                });
            }
        } catch(e) {
            console.error("Error loading presentations:", e);
        }
    }

    function applyEmailTemplate() {
        const id = document.getElementById('email-template-select').value;
        const hiddenId = document.getElementById('email-template-id-hidden');
        const badge = document.getElementById('email-template-attachment-badge');
        
        if (!id) {
            hiddenId.value = '';
            badge.style.display = 'none';
            return;
        }
        
        const t = window.globalEmailTemplates.find(x => x.id == id);
        if (t) {
            hiddenId.value = t.id;
            document.querySelector('[name="subject"]').value = t.subject;
            document.querySelector('[name="type"]').value = t.type;
            quillEmailEditor.root.innerHTML = t.body;
            
            if (t.attachment_name) {
                badge.innerText = `📎 Template Attachment: ${t.attachment_name.substring(13)}`;
                badge.style.display = 'block';
            } else {
                badge.style.display = 'none';
            }
        }
    }

    async function dispatchEmail(e) {
        e.preventDefault();
        
        const hiddenBodyInput = document.getElementById('email-body-hidden');
        hiddenBodyInput.value = quillEmailEditor.getSemanticHTML();
        
        const form = document.getElementById('email-sender-form');
        const formData = new FormData(form);
        
        try {
            const response = await fetch('../api.php?api=send_email', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (response.ok && data.success) {
                showNotification(data.message, 'success');
                form.reset();
                quillEmailEditor.setText('');
                
                setTimeout(() => {
                    location.href = 'search_track.php';
                }, 1000);
            } else {
                showNotification(data.error || 'Simulated mail routing failed.', 'error');
            }
        } catch (err) {
            showNotification('Email sending operation failed.', 'error');
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        quillEmailEditor = new Quill('#email-body-editor', {
            theme: 'snow',
            placeholder: 'Write your official pitch/proposals/quotation email here...',
            modules: {
                toolbar: [
                    ['bold', 'italic', 'underline'],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    ['clean']
                ]
            }
        });
        
        refreshClientDropdowns();
        loadTemplatesList();
        loadPptsList();
    });
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
