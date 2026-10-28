<?php
require_once 'config.php';
require_role(['Admin', 'RM']);

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header("Location: applicants_list.php");
    exit;
}

$stmt = $db->prepare("SELECT * FROM applicants WHERE id = ?");
$stmt->execute([$id]);
$applicant = $stmt->fetch();

if (!$applicant) {
    die("Applicant not found.");
}

$co_stmt = $db->prepare("SELECT id, full_name, relationship FROM co_applicants WHERE applicant_id = ?");
$co_stmt->execute([$id]);
$co_applicants = $co_stmt->fetchAll();

// Smart Lender Eligibility Filter: Compute eligible banks for THIS specific applicant
$eligibility_result = getEligibleBanksForApplicant($applicant, $db);
$eligible_banks = $eligibility_result['banks'];
$eligible_bank_details = $eligibility_result['details'];
$ineligible_bankers_count = $eligibility_result['ineligible_count'];

// Single source of truth for bank dropdowns on this applicant page
$all_banks_list = $eligible_banks;

$page_title = 'Applicant Documents';
$page_subtitle = 'Phase 2: Upload KYC & Loan Specific Documents';
$hide_main_header = true;
require_once 'header.php';
?>

<div id="view-applicant-docs" class="view-container">

    <!-- 🌟 TOP MASTER EXECUTIVE APPLICANT HEADER CARD (FULL WIDTH) -->
    <div style="background: #000000; border-radius: 12px; padding: 18px 20px; color: #ffffff; margin-bottom: 18px; box-shadow: 0 4px 20px rgba(0,0,0,0.4); border: 1px solid #27272a;">
        
        <!-- ROW 1: APPLICANT IDENTITY (LEFT) & KEY CRM STATS (RIGHT - NO DUPLICATION) -->
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 14px;">
            
            <!-- Applicant Meta & Identity Desk -->
            <div style="flex: 1; min-width: 280px;">
                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <a href="applicants_list.php" style="background: #18181b; color: #ffffff; border: 1px solid #27272a; padding: 4px 10px; font-weight: 700; font-size: 11.5px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; transition: background 0.15s ease;" onmouseover="this.style.background='#27272a'" onmouseout="this.style.background='#18181b'" title="Return to Applicants List">
                        <i data-lucide="arrow-left" style="width:13px; height:13px; color:#ffffff;"></i> Back
                    </a>
                    <h2 style="margin: 0; font-size: 20px; font-weight: 800; color: #ffffff; font-family: 'Outfit', sans-serif; letter-spacing: -0.02em;">
                        <?php echo htmlspecialchars($applicant['customer_name'] ?? ''); ?>
                    </h2>
                    <span style="background: #18181b; color: #ffffff; border: 1px solid #27272a; padding: 3px 10px; font-weight: 700; font-size: 11px; border-radius: 20px;">
                        Ref: <?php echo htmlspecialchars($applicant['loan_id'] ?? ''); ?>
                    </span>
                    <span style="background: #18181b; color: #ffffff; border: 1px solid #27272a; padding: 3px 10px; font-weight: 700; font-size: 11px; border-radius: 20px;">
                        Phase 2: Docs Collection
                    </span>
                    <?php if (!empty($co_applicants)): ?>
                        <?php foreach ($co_applicants as $co): ?>
                            <span style="font-size: 11px; font-weight: 600; background: #18181b; color: #a1a1aa; padding: 3px 10px; border-radius: 20px; border: 1px solid #27272a; display: inline-flex; align-items: center; gap: 4px;">
                                <i data-lucide="users" style="width:12px; height:12px; color:#ffffff;"></i> Co-App: <?php echo htmlspecialchars($co['full_name']); ?> (<?php echo htmlspecialchars($co['relationship'] ?? 'Co-App'); ?>)
                            </span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-top: 6px; font-size: 12px; color: #a1a1aa;">
                    <span>Loan Type: <strong style="color: #ffffff;"><?php echo htmlspecialchars($applicant['loan_type'] ?? ''); ?> (<?php echo htmlspecialchars($applicant['loan_sub_type'] ?? 'General'); ?>)</strong></span>
                    <span style="color: #3f3f46;">•</span>
                    <span>Employment: <strong style="color: #ffffff;"><?php echo htmlspecialchars($applicant['employment_type'] ?? '-'); ?></strong></span>
                    <?php if (!empty($applicant['created_at'])): ?>
                        <span style="color: #3f3f46;">•</span>
                        <span>Created: <strong style="color: #ffffff;"><?php echo date('d M Y', strtotime($applicant['created_at'])); ?></strong></span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Executive Dark Glass Summary Stat Cards (Unique Financial & Assignment Context) -->
            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                <div style="background: #18181b; border: 1px solid #27272a; border-radius: 8px; padding: 6px 14px; text-align: right; min-width: 120px;">
                    <div style="font-size: 10px; color: #a1a1aa; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;">Loan Amount</div>
                    <div style="font-size: 14px; font-weight: 800; color: #ffffff; font-family: 'Outfit', sans-serif;">₹<?php echo number_format((float)($applicant['loan_amount_requested'] ?? 0)); ?></div>
                </div>
                <div style="background: #18181b; border: 1px solid #27272a; border-radius: 8px; padding: 6px 14px; text-align: right; min-width: 120px;">
                    <div style="font-size: 10px; color: #a1a1aa; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;">Target Bank</div>
                    <div style="font-size: 13px; font-weight: 800; color: #ffffff; font-family: 'Outfit', sans-serif;"><?php echo htmlspecialchars(!empty($applicant['bank_name']) ? $applicant['bank_name'] : 'Partner Banks'); ?></div>
                </div>
                <div style="background: #18181b; border: 1px solid #27272a; border-radius: 8px; padding: 6px 14px; text-align: right; min-width: 120px;">
                    <div style="font-size: 10px; color: #a1a1aa; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;">Assigned RM</div>
                    <div style="font-size: 13px; font-weight: 800; color: #ffffff; font-family: 'Outfit', sans-serif;"><?php echo htmlspecialchars(!empty($applicant['assigned_to']) ? $applicant['assigned_to'] : 'Admin Desk'); ?></div>
                </div>
            </div>

        </div>

        <!-- ROW 2: UNIFIED FULL-WIDTH EXECUTIVE ACTION TOOLBAR -->
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px; background: #18181b; padding: 6px; border-radius: 8px; border: 1px solid #27272a; flex-wrap: wrap; width: 100%;">
            <!-- Left Action Button Group -->
            <div style="display: inline-flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                <a href="add_applicant.php?id=<?php echo $id; ?>" class="btn" style="background: #0d9488; color: #ffffff; border: none; padding: 6px 12px; border-radius: 6px; font-weight: 700; font-size: 11.5px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; text-decoration:none; box-shadow: 0 1px 3px rgba(13,148,136,0.3);" title="Edit Application Form & Customer Profile">
                    <i data-lucide="edit-3" style="width: 13px; height: 13px; color: #ffffff;"></i> Edit Form Details
                </a>
                <button type="button" onclick="sendClientSelfUploadLink(<?php echo $id; ?>)" class="btn" style="background: #ffffff; color: #000000; border: none; padding: 6px 12px; border-radius: 6px; font-weight: 700; font-size: 11.5px; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.2);" title="Open Client Upload Portal Link Dialog">
                    <i data-lucide="link-2" style="width: 13px; height: 13px; color: #000000;"></i> Client Link
                </button>
                <button type="button" onclick="triggerAiMissingDocsWhatsApp(<?php echo $id; ?>)" class="btn" style="background: #18181b; color: #ffffff; border: 1px solid #27272a; padding: 6px 12px; border-radius: 6px; font-weight: 700; font-size: 11.5px; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; transition: background 0.15s ease;" onmouseover="this.style.background='#27272a'" onmouseout="this.style.background='#18181b'">
                    <i data-lucide="message-circle" style="width: 13px; height: 13px; color: #ffffff;"></i> WhatsApp Audit
                </button>
                <button type="button" onclick="openPhotoGalleryModal()" class="btn" style="background: #18181b; color: #ffffff; border: 1px solid #27272a; padding: 6px 12px; border-radius: 6px; font-weight: 700; font-size: 11.5px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; transition: background 0.15s ease;" onmouseover="this.style.background='#27272a'" onmouseout="this.style.background='#18181b'">
                    <i data-lucide="images" style="width: 13px; height: 13px; color: #ffffff;"></i> Photos Lightbox
                </button>
                <button type="button" onclick="openBankDocketModal()" class="btn" style="background: #047857; color: #ffffff; border: none; padding: 6px 12px; border-radius: 6px; font-weight: 800; font-size: 11.5px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; box-shadow: 0 1px 3px rgba(4,120,87,0.4);" title="Compile all verified docs into a single PDF dossier with watermark & BFS cover sheet">
                    <i data-lucide="file-check-2" style="width: 13px; height: 13px; color: #ffffff;"></i> ⚡ Bank Docket
                </button>
                <button type="button" onclick="openWindowsVaultFolder(<?php echo $id; ?>)" class="btn" style="background: #18181b; color: #38bdf8; border: 1px solid #0284c7; padding: 6px 12px; border-radius: 6px; font-weight: 700; font-size: 11.5px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; transition: background 0.15s ease;" onmouseover="this.style.background='#0369a1'; this.style.color='#fff';" onmouseout="this.style.background='#18181b'; this.style.color='#38bdf8';" title="Open local physical folder on F:\BFS in Windows Explorer">
                    <i data-lucide="folder" style="width: 13px; height: 13px;"></i> 📂 Vault (F:\BFS)
                </button>
                <button type="button" onclick="generateAndPreviewMasterPdf()" class="btn" style="background: #18181b; color: #ffffff; border: 1px solid #27272a; padding: 6px 12px; border-radius: 6px; font-weight: 700; font-size: 11.5px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; transition: background 0.15s ease;" onmouseover="this.style.background='#27272a'" onmouseout="this.style.background='#18181b'">
                    <i data-lucide="file-text" style="width: 13px; height: 13px; color: #ffffff;"></i> Master PDF
                </button>
                <a href="generate_zip.php?id=<?php echo $id; ?>" class="btn" style="background: #18181b; color: #ffffff; border: 1px solid #27272a; padding: 6px 12px; border-radius: 6px; font-weight: 700; font-size: 11.5px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; text-decoration:none; transition: background 0.15s ease;" onmouseover="this.style.background='#27272a'" onmouseout="this.style.background='#18181b'">
                    <i data-lucide="download" style="width: 13px; height: 13px; color: #ffffff;"></i> Bank Dossier (ZIP)
                </a>
                <button type="button" onclick="openEmailPendencyModal()" class="btn" style="background: #18181b; color: #ffffff; border: 1px solid #27272a; padding: 6px 12px; border-radius: 6px; font-weight: 700; font-size: 11.5px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; transition: background 0.15s ease;" onmouseover="this.style.background='#27272a'" onmouseout="this.style.background='#18181b'">
                    <i data-lucide="send" style="width: 13px; height: 13px; color: #ffffff;"></i> Email Client
                </button>
            </div>

            <!-- Right Anchored Action Button Group (Phase 3 CTA) -->
            <div style="margin-left: auto;">
                <a href="applicant_disbursements.php?id=<?php echo $id; ?>" id="phase3-proceed-btn" style="display: inline-flex; align-items: center; gap: 5px; background: #ffffff; color: #000000; padding: 6px 14px; border-radius: 6px; text-decoration: none; font-size: 11.5px; font-weight: 800; opacity: 0.6; pointer-events: none; transition: all 0.2s ease;">
                    <span>Phase 3 Proceed &rarr;</span>
                </a>
            </div>
        </div>

        <!-- ROW 3: AI AUDIT LIVE COMPLETENESS BAR -->
        <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #27272a; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; font-size: 11.5px;">
            <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                <div>Completeness: <strong id="ai-completeness-text" style="color: #ffffff; font-size: 13px; margin-left: 3px;">Calculating...</strong></div>
                <div style="color: #3f3f46;">|</div>
                <div>Submission Status: <span id="ai-bank-readiness-text" style="color: #ffffff; font-weight: 700;">Analyzing...</span></div>
                <div style="color: #3f3f46;">|</div>
                <div id="ai-wa-tracker-text" style="color: #a1a1aa;">Auto 2-Day WhatsApp Reminder Active</div>
                <div style="color: #3f3f46;">|</div>
                <div style="display:inline-flex; align-items:center; gap:5px; color:#38bdf8; font-weight:700; font-size:11px;">
                    <i data-lucide="hard-drive" style="width:12px; height:12px; color:#10b981;"></i> On-Premise Vault F:\BFS (Active)
                </div>
            </div>
            
            <button type="button" onclick="runAiDocAudit(<?php echo $id; ?>)" style="background: none; border: none; color: #a1a1aa; font-size: 11px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; transition: color 0.15s ease;" onmouseover="this.style.color='#ffffff'" onmouseout="this.style.color='#a1a1aa'">
                <i data-lucide="refresh-cw" style="width: 12px; height: 12px;"></i> Refresh AI Audit
            </button>
        </div>

    </div>

    <!-- ⚡ UNIFIED 2-COLUMN WORKSPACE GRID (1fr 2fr) -->
    <div class="dashboard-layout-row" style="grid-template-columns: 1fr 2fr; gap: 24px; align-items: start;">
        
        <!-- LEFT COLUMN: DOCUMENT UPLOAD & CAPTURE WORKSPACE (35%) -->
        <div class="card" style="padding: 18px; align-self: start;">
            <div style="display: flex; background: #f1f5f9; padding: 4px; border-radius: 8px; margin-bottom: 16px; border: 1px solid #cbd5e1;">
                <button type="button" id="tab-btn-single" onclick="switchUploadTab('single')" style="flex: 1; padding: 8px 12px; font-weight: 800; font-size: 12.5px; color: #0f172a; border: none; border-radius: 6px; background: #ffffff; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px; box-shadow: 0 1px 3px rgba(15,23,42,0.08); transition: all 0.2s ease;">
                    <i data-lucide="upload-cloud" style="width: 15px;"></i> Single Upload
                </button>
                <button type="button" id="tab-btn-bulk" onclick="switchUploadTab('bulk')" style="flex: 1; padding: 8px 12px; font-weight: 700; font-size: 12.5px; color: #64748b; border: none; border-radius: 6px; background: transparent; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px; transition: all 0.2s ease;">
                    <i data-lucide="folder-up" style="width: 15px;"></i> ⚡ Bulk Uploader
                </button>
            </div>

            <!-- SINGLE UPLOADER PANEL -->
            <div id="upload-single-panel">
                <form id="doc-upload-form" onsubmit="uploadDocument(event)" enctype="multipart/form-data">
                    <input type="hidden" name="applicant_id" value="<?php echo $id; ?>">
                    
                    <div class="form-group" style="margin-bottom: 14px;">
                        <label class="required" style="font-size:12px; font-weight:700; color:#334155;">Document Category</label>
                        <select name="document_category" id="doc_category" required onchange="updateDocSuggestions(this.value)" style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:12.5px;">
                            <option value="" disabled selected>-- Select Category --</option>
                            <option value="Basic KYC">🪪 Basic KYC (PAN, Aadhaar, Photo, Address, Voter ID, DL)</option>
                            <option value="Income Proof">💼 Income &amp; Salary Proof (Salary Slips, Bank Stmt, Form 16, ITR)</option>
                            <option value="Business Proof">🏢 Business &amp; Employment Proof (GST, Trade License, MSME)</option>
                            <option value="Property / Asset Docs">🏠 Property Docs (Registry, Map, Tax, Electricity Bill)</option>
                            <option value="Vehicle Docs">🚗 Vehicle Docs (RC Copy, Quotation, Insurance)</option>
                            <option value="Existing Loan Obligations">🏦 Existing Loan Obligations (SOA, Sanction, NOC)</option>
                            <option value="Co-Applicant Docs">👥 Co-Applicant &amp; Guarantor Docs (KYC, Income)</option>
                            <option value="Bank Login & Fee Receipts">💳 Bank Receipts (Login Fee, Valuation, CIBIL)</option>
                            <option value="Other">📁 Other / Custom Loan Document</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 14px;">
                        <label class="required" style="font-size:12px; font-weight:700; color:#334155;">Specific Document Name</label>
                        <input type="text" name="document_name" id="doc_name_input" placeholder="e.g. PAN Card Front" required style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:12.5px;">
                        <div id="quick-doc-chips" style="display:flex; gap:5px; flex-wrap:wrap; margin-top:6px;"></div>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 14px;">
                        <label class="required" style="font-size:12px; font-weight:700; color:#334155;">Document Owner</label>
                        <select name="owner_val" id="owner_val" required style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:12.5px;">
                            <option value="Applicant_0">Primary Applicant (<?php echo htmlspecialchars($applicant['customer_name']); ?>)</option>
                            <?php foreach ($co_applicants as $co): ?>
                            <option value="CoApplicant_<?php echo $co['id']; ?>">Co-Applicant: <?php echo htmlspecialchars($co['full_name']); ?> (<?php echo htmlspecialchars($co['relationship'] ?? 'Co-App'); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 16px;">
                        <label class="required" style="font-size:12px; font-weight:700; color:#334155;">Document File / Capture</label>
                        <div style="display: flex; gap: 10px; align-items: stretch;">
                            <div style="flex: 1; border: 1.5px dashed #cbd5e1; padding: 16px; border-radius: 8px; text-align: center; background: #f8fafc; cursor: pointer; position: relative;" onclick="document.getElementById('file-input').click()">
                                <i data-lucide="upload-cloud" style="width: 24px; height: 24px; color: #0f172a; margin-bottom: 4px;"></i>
                                <div style="font-size:12px; font-weight:700; color:#0f172a;">Choose File</div>
                                <div style="font-size:10.5px; color:#64748b;">PDF, JPG, PNG</div>
                                <input type="file" name="document_file[]" id="file-input" accept="image/*, .pdf, .jpg, .jpeg, .png, .doc, .docx" multiple style="opacity: 0; position: absolute; top: 0; left: 0; width: 100%; height: 100%; cursor: pointer;" onchange="updateFileName(this)">
                                <div id="file-name-display" style="margin-top: 6px; font-weight: bold; color: #16a34a; font-size: 11.5px;"></div>
                            </div>
                            <div style="flex: 1; border: 1.5px dashed #cbd5e1; padding: 16px; border-radius: 8px; text-align: center; background: #f8fafc; cursor: pointer; position: relative;" onclick="startScanner()">
                                <i data-lucide="printer" style="width: 24px; height: 24px; color: #0f172a; margin-bottom: 4px;"></i>
                                <div style="font-size:12px; font-weight:700; color:#0f172a;">Printer Scan</div>
                                <div style="font-size:10.5px; color:#64748b;">Hardware Capture</div>
                                <div id="scan-status" style="margin-top: 6px; font-weight: bold; color: #16a34a; font-size: 11.5px;"></div>
                            </div>
                        </div>
                        <div id="scanned-preview-container" style="display:flex; flex-wrap:wrap; gap:8px; margin-top:12px;"></div>
                    </div>

                    <button type="submit" class="btn btn-primary" id="upload-btn" style="width: 100%; padding: 11px; font-weight:800; background:#0f172a; border:none; font-size:13px;"><i data-lucide="upload"></i> Upload &amp; Save Document</button>
                </form>
            </div>

            <!-- BULK UPLOADER PANEL -->
            <div id="upload-bulk-panel" style="display: none;">
                <div style="border: 1.5px dashed #94a3b8; border-radius: 8px; padding: 16px; background: #f8fafc; text-align: center; cursor: pointer;" onclick="document.getElementById('bulk-files-input').click()" ondragover="event.preventDefault();" ondrop="handleBulkFileDrop(event)">
                    <i data-lucide="folder-up" style="width: 32px; height: 32px; color: #0f172a; margin-bottom: 6px;"></i>
                    <div style="font-weight: 800; font-size: 13.5px; color: #0f172a;">Drop Client Folder Here</div>
                    <div style="font-size: 11.5px; color: #64748b; margin-top: 4px;">Auto-detects PAN, Salary Slips, Aadhaar, Bank Statements from filenames</div>
                    <input type="file" id="bulk-files-input" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" style="display:none;" onchange="handleBulkFilesSelected(this)">
                </div>
                <div id="bulk-files-preview-list" style="margin-top: 14px; display: none;"></div>
            </div>
        </div>

        <!-- RIGHT COLUMN: SMART CHECKLIST & UPLOADED VAULT (65%) -->
        <div style="display: flex; flex-direction: column; gap: 20px;">
            
            <!-- Smart Dynamic Checklist Card -->
            <div class="card" style="padding: 18px;">
                <!-- Main Header Toolbar Row -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 10px;">
                    <h3 style="color: #0f172a; margin: 0; font-size: 15px; font-weight: 800; display: flex; align-items: center; gap: 8px; font-family:'Outfit', sans-serif;">
                        <i data-lucide="check-square" style="width:18px; height:18px; color:#0f172a;"></i> Recommended Checklist for <?php echo htmlspecialchars($applicant['loan_type'] ?? 'Loan'); ?>
                    </h3>
                    
                    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                        <!-- Target Bank Filter Pill (Eligible Banks Only) -->
                        <div style="display: flex; align-items: center; gap: 8px; background: #ffffff; padding: 0 10px; border-radius: 6px; border: 1px solid #cbd5e1; height: 34px; box-sizing: border-box; box-shadow: 0 1px 2px rgba(15,23,42,0.04);">
                            <i data-lucide="landmark" style="width: 14px; height: 14px; color: #0f172a; flex-shrink: 0;"></i>
                            <span style="font-size: 11.5px; font-weight: 700; color: #475569; white-space: nowrap;">Target Bank:</span>
                            <select id="target-bank-filter" onchange="setTargetBankFilter(this.value)" style="border: 1px solid #cbd5e1; background: #ffffff; font-size: 11.5px; font-weight: 700; color: #0f172a; cursor: pointer; outline: none; padding: 3px 8px; border-radius: 5px; font-family: 'Outfit', sans-serif; height: 26px; transition: all 0.15s ease;" onfocus="this.style.borderColor='#0f172a'" onblur="this.style.borderColor='#cbd5e1'">
                                <option value="ALL" style="padding: 6px 10px; font-weight: 700; color: #0f172a; background: #ffffff;">All Eligible Banks (<?php echo count($eligible_banks); ?>)</option>
                                <?php foreach ($eligible_banks as $b_name): ?>
                                    <option value="<?php echo htmlspecialchars($b_name); ?>" style="padding: 6px 10px; font-weight: 600; color: #0f172a; background: #ffffff;">
                                        <?php echo htmlspecialchars($b_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- 🛡️ Standalone High-Visibility Eligible Lenders Badge -->
                        <div style="display: inline-flex; align-items: center; gap: 6px; background: #ecfdf5; border: 1px solid #86efac; color: #047857; height: 34px; padding: 0 12px; border-radius: 6px; font-size: 12px; font-weight: 800; white-space: nowrap; box-sizing: border-box; box-shadow: 0 1px 2px rgba(16,185,129,0.08); font-family: 'Outfit', sans-serif;" title="Matched for <?php echo htmlspecialchars($applicant['loan_type'] ?? 'Loan'); ?><?php echo !empty($applicant['loan_amount_requested']) ? ' • ₹' . number_format($applicant['loan_amount_requested']) : ''; ?> (<?php echo $ineligible_bankers_count; ?> non-matching lenders excluded)">
                            <i data-lucide="shield-check" style="width: 15px; height: 15px; color: #059669; flex-shrink: 0;"></i>
                            <span><?php echo count($eligible_banks); ?> Eligible</span>
                        </div>

                        <!-- Collapse Toggle Button -->
                        <button type="button" onclick="toggleChecklistBody()" id="checklist-toggle-btn" class="btn" style="background:#0f172a; color:#ffffff; border:1px solid #0f172a; padding:0 14px; height:34px; border-radius:6px; font-weight:700; font-size:11.5px; cursor:pointer; display:inline-flex; align-items:center; gap:6px; transition:all 0.15s ease; box-sizing:border-box; white-space:nowrap;">
                            <i data-lucide="chevron-up" id="chk-toggle-icon" style="width:14px; height:14px;"></i> Collapse All
                        </button>
                    </div>
                </div>

                <!-- Collapsible Wrapper for Filter Tabs and Cards Grid -->
                <div id="checklist-collapsible-wrapper">
                    <!-- Checklist Sub-Toolbar Row: Owner Filter Tabs (Left) + Mandatory Counter Pill (Right) -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px; flex-wrap: wrap; gap: 10px;">
                        <div id="checklist-filter-tabs" style="display: flex; gap: 8px; overflow-x: auto;">
                            <!-- Filter tabs dynamically rendered via JS -->
                        </div>
                        <div id="checklist-progress-pill" style="font-size: 11.5px; font-weight: 700; color: #0f172a; background: #f1f5f9; padding: 4px 12px; border-radius: 20px; border: 1px solid #cbd5e1; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap;">
                            <i data-lucide="check-circle" style="width:13px; height:13px; color:#059669;"></i>
                            <span id="checklist-progress-text">Calculating requirements...</span>
                        </div>
                    </div>

                    <div id="smart-checklist-grid" style="display: flex; flex-direction: column; gap: 10px;">
                        <div style="color:var(--text-muted); font-size:12px;">Loading tailored checklist...</div>
                    </div>
                </div>
            </div>

            <!-- Uploaded Documents Vault Table Card -->
            <div class="card" style="padding: 18px;">
                <!-- Physical Storage Location Header Strip -->
                <div style="background: #09090b; border: 1px solid #27272a; border-radius: 8px; padding: 9px 14px; margin-bottom: 14px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                        <span style="background: #15803d; color: #ffffff; font-size: 10.5px; font-weight: 800; padding: 2px 8px; border-radius: 12px; display: inline-flex; align-items: center; gap: 4px;">
                            <i data-lucide="hard-drive" style="width:11px; height:11px;"></i> F:\BFS Active
                        </span>
                        <span style="color: #a1a1aa; font-size: 11.5px;">
                            Local Vault: <code style="color: #38bdf8; font-family: monospace; font-size: 11px;">F:\BFS\Vault_Leads\Lead_<?php echo $id; ?>_...</code>
                        </span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <button type="button" onclick="openWindowsVaultFolder(<?php echo $id; ?>)" class="btn" style="background: #18181b; color: #38bdf8; border: 1px solid #0284c7; padding: 4px 10px; font-size: 11px; font-weight: 700; border-radius: 5px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">
                            <i data-lucide="folder" style="width:12px; height:12px;"></i> 📂 Reveal in Windows Explorer
                        </button>
                    </div>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 14px; border-bottom: 1px solid var(--border); padding-bottom: 10px;">
                    <h3 style="margin:0; font-size:15px; color:#0f172a; font-family:'Outfit', sans-serif;"><i data-lucide="folder-check" style="width:17px; vertical-align:-2px;"></i> Permanent Vault (<span id="uploaded-count">0</span> Files Uploaded)</h3>
                    <span id="doc-completion-badge" class="badge badge-info" style="font-weight:700;">0% Complete</span>
                </div>
                <div class="table-responsive">
                    <table class="data-table" style="font-size:12.5px;">
                        <thead>
                            <tr>
                                <th>Owner</th>
                                <th>Category</th>
                                <th>Document Name</th>
                                <th>QA Status</th>
                                <th>Uploaded Date</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="docs-list">
                            <tr><td colspan="6" style="text-align:center;">Loading documents...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

</div>


<!-- ⚡ 1-CLICK ADVANCED BANK SUBMISSION DOCKET MODAL -->
<div id="bank-docket-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.88); z-index:99999; justify-content:center; align-items:center; backdrop-filter:blur(6px);">
    <div style="background:#09090b; border:1px solid #27272a; border-radius:14px; width:92%; max-width:620px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.6); overflow:hidden; color:#ffffff; font-family:'Outfit', sans-serif;">
        <!-- Header -->
        <div style="padding:16px 22px; background:#18181b; border-bottom:1px solid #27272a; display:flex; justify-content:space-between; align-items:center;">
            <div style="display:flex; align-items:center; gap:10px;">
                <div style="width:36px; height:36px; border-radius:8px; background:#047857; display:flex; align-items:center; justify-content:center;">
                    <i data-lucide="file-check-2" style="width:20px; height:20px; color:#ffffff;"></i>
                </div>
                <div>
                    <h3 style="margin:0; font-size:16px; font-weight:800; color:#ffffff;">⚡ 1-Click Master Bank Docket</h3>
                    <div style="font-size:11.5px; color:#a1a1aa;">Compiles all verified documents into a single bank-ready dossier with BFS cover sheet</div>
                </div>
            </div>
            <button type="button" onclick="closeBankDocketModal()" style="background:none; border:none; color:#a1a1aa; cursor:pointer; font-size:24px; line-height:1;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#a1a1aa'">&times;</button>
        </div>

        <!-- Body -->
        <div style="padding:22px; max-height:75vh; overflow-y:auto;">
            <!-- Target Bank Selection -->
            <div style="margin-bottom:16px;">
                <label style="display:block; font-size:12px; font-weight:700; color:#e4e4e7; margin-bottom:6px;">Target Bank / Lending Institution</label>
                <select id="docket-bank-select" style="width:100%; padding:10px 14px; background:#18181b; border:1px solid #3f3f46; border-radius:8px; color:#ffffff; font-size:13px; font-weight:600; outline:none;">
                    <?php foreach ($all_banks_list as $b_name): ?>
                        <option value="<?php echo htmlspecialchars($b_name); ?>" <?php echo (!empty($applicant['bank_name']) && $applicant['bank_name'] === $b_name) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($b_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Anti-Fraud Security Watermark Option -->
            <div style="background:#18181b; border:1px solid #27272a; border-radius:8px; padding:12px 14px; margin-bottom:16px; display:flex; align-items:center; justify-content:space-between;">
                <div>
                    <div style="font-size:12.5px; font-weight:700; color:#ffffff; display:flex; align-items:center; gap:6px;">
                        <i data-lucide="shield-check" style="width:15px; height:15px; color:#10b981;"></i> Anti-Fraud Security Watermark
                    </div>
                    <div style="font-size:11px; color:#a1a1aa; margin-top:2px;">Stamps "BFS CONFIDENTIAL - SUBMITTED FOR LOAN EVALUATION TO [BANK]" across pages</div>
                </div>
                <input type="checkbox" id="docket-watermark-toggle" checked style="width:20px; height:20px; accent-color:#10b981; cursor:pointer;">
            </div>

            <!-- Documents to be compiled preview list -->
            <div style="margin-bottom:18px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                    <span style="font-size:12px; font-weight:700; color:#e4e4e7;">Documents to be Stitched (<span id="docket-docs-count">0</span>):</span>
                    <span style="font-size:11px; color:#38bdf8;">Saved directly on F:\BFS</span>
                </div>
                <div id="docket-docs-preview" style="background:#121214; border:1px solid #27272a; border-radius:8px; padding:10px; max-height:140px; overflow-y:auto; font-size:11.5px; display:flex; flex-direction:column; gap:6px;">
                    <!-- Rendered via JS -->
                </div>
            </div>

            <!-- Status / Result Banner -->
            <div id="docket-result-box" style="display:none; background:#064e3b; border:1px solid #059669; border-radius:8px; padding:12px; margin-bottom:16px;">
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">
                    <span style="font-weight:800; font-size:13px; color:#a7f3d0; display:flex; align-items:center; gap:6px;">
                        <i data-lucide="check-circle" style="width:16px; height:16px;"></i> Dossier Compiled Successfully!
                    </span>
                    <span id="docket-result-pages" style="font-size:11.5px; font-weight:700; background:#047857; color:#fff; padding:2px 8px; border-radius:12px;">0 Pages</span>
                </div>
                <div id="docket-result-path" style="font-family:monospace; font-size:10.5px; color:#d1fae5; word-break:break-all; margin-bottom:10px;"></div>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <a id="docket-preview-link" href="#" target="_blank" class="btn" style="background:#ffffff; color:#000000; padding:6px 14px; border-radius:6px; font-size:12px; font-weight:800; text-decoration:none; display:inline-flex; align-items:center; gap:5px;">
                        <i data-lucide="eye" style="width:13px; height:13px;"></i> View Docket
                    </a>
                    <a id="docket-download-link" href="#" download class="btn" style="background:#10b981; color:#ffffff; padding:6px 14px; border-radius:6px; font-size:12px; font-weight:800; text-decoration:none; display:inline-flex; align-items:center; gap:5px;">
                        <i data-lucide="download" style="width:13px; height:13px;"></i> Download PDF
                    </a>
                    <button type="button" onclick="openWindowsVaultFolder(<?php echo $id; ?>)" class="btn" style="background:#18181b; color:#38bdf8; border:1px solid #0284c7; padding:6px 12px; border-radius:6px; font-size:12px; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:5px;">
                        <i data-lucide="folder" style="width:13px; height:13px;"></i> Open in Explorer
                    </button>
                </div>
            </div>

            <!-- Submit Button / Progress Indicator -->
            <button type="button" id="btn-compile-docket" onclick="executeCompileDocket()" style="width:100%; background:#047857; color:#ffffff; border:none; padding:12px; border-radius:8px; font-weight:800; font-size:13.5px; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; box-shadow:0 4px 15px rgba(4,120,87,0.3); transition:all 0.15s ease;">
                <i data-lucide="zap" style="width:16px; height:16px;"></i> Compile &amp; Save Bank Dossier (F:\BFS)
            </button>
        </div>
    </div>
</div>


<!-- ✏️ EDIT / RENAME CHECKLIST DOCUMENT NAME MODAL -->
<div id="edit-doc-name-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.85); z-index:999999; justify-content:center; align-items:center; backdrop-filter:blur(4px);">
    <div style="background:#ffffff; border-radius:12px; width:92%; max-width:480px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.3); overflow:hidden; border:1px solid #e2e8f0; font-family:'Outfit', sans-serif;">
        <div style="padding:16px 20px; background:#0f172a; color:#ffffff; display:flex; justify-content:space-between; align-items:center;">
            <div style="display:flex; align-items:center; gap:8px;">
                <i data-lucide="edit-3" style="width:17px; height:17px; color:#38bdf8;"></i>
                <span style="font-weight:800; font-size:15px;">Rename Document Requirement</span>
            </div>
            <button type="button" onclick="closeEditDocNameModal()" style="background:none; border:none; color:#a1a1aa; cursor:pointer; font-size:22px; line-height:1;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#a1a1aa'">&times;</button>
        </div>
        <form id="edit-doc-name-form" onsubmit="submitRenameDocName(event)" style="padding:20px;">
            <input type="hidden" id="edit-orig-doc-name">
            <input type="hidden" id="edit-doc-category">

            <div style="margin-bottom:14px;">
                <label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:5px;">Original Document Name</label>
                <div id="edit-orig-name-display" style="font-size:12.5px; font-weight:600; color:#64748b; background:#f1f5f9; padding:8px 12px; border-radius:6px; border:1px solid #cbd5e1;"></div>
            </div>

            <div style="margin-bottom:14px;">
                <label class="required" style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:5px;">Custom / New Document Title</label>
                <input type="text" id="edit-new-doc-name" required style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; font-weight:600; color:#0f172a; outline:none; box-sizing:border-box;">
            </div>

            <div style="margin-bottom:16px;">
                <label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:5px;">Requirement Description / Instructions (Hint)</label>
                <input type="text" id="edit-doc-hint" placeholder="e.g. Color copy with both sides" style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:12.5px; color:#0f172a; outline:none; box-sizing:border-box;">
            </div>

            <div style="margin-bottom:18px; background:#f8fafc; padding:10px 12px; border-radius:6px; border:1px solid #e2e8f0; display:flex; align-items:center; justify-content:space-between;">
                <div>
                    <div style="font-size:12px; font-weight:700; color:#0f172a;">Mandatory Requirement</div>
                    <div style="font-size:11px; color:#64748b;">Lock Phase 3 until this document is uploaded</div>
                </div>
                <input type="checkbox" id="edit-is-mandatory" style="width:18px; height:18px; accent-color:#0f172a; cursor:pointer;">
            </div>

            <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                <button type="button" onclick="deleteChecklistItem()" class="btn" style="background:#fee2e2; color:#991b1b; border:1px solid #fecaca; padding:8px 14px; font-size:12px; font-weight:700; border-radius:6px; cursor:pointer; display:inline-flex; align-items:center; gap:4px;">
                    <i data-lucide="trash-2" style="width:13px; height:13px;"></i> Remove from Checklist
                </button>
                <div style="display:flex; gap:8px;">
                    <button type="button" onclick="closeEditDocNameModal()" class="btn" style="background:#f1f5f9; color:#475569; border:1px solid #cbd5e1; padding:8px 14px; font-size:12px; font-weight:700; border-radius:6px; cursor:pointer;">Cancel</button>
                    <button type="submit" class="btn" style="background:#0f172a; color:#ffffff; border:none; padding:8px 18px; font-size:12px; font-weight:800; border-radius:6px; cursor:pointer; display:inline-flex; align-items:center; gap:4px;">Save Name</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- ➕ ADD CUSTOM DOCUMENT TO CHECKLIST MODAL -->
<div id="add-custom-doc-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.85); z-index:999999; justify-content:center; align-items:center; backdrop-filter:blur(4px);">
    <div style="background:#ffffff; border-radius:12px; width:92%; max-width:480px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.3); overflow:hidden; border:1px solid #e2e8f0; font-family:'Outfit', sans-serif;">
        <div style="padding:16px 20px; background:#0f172a; color:#ffffff; display:flex; justify-content:space-between; align-items:center;">
            <div style="display:flex; align-items:center; gap:8px;">
                <i data-lucide="plus-circle" style="width:18px; height:18px; color:#10b981;"></i>
                <span style="font-weight:800; font-size:15px;">Add Custom Document Requirement</span>
            </div>
            <button type="button" onclick="closeAddCustomDocModal()" style="background:none; border:none; color:#a1a1aa; cursor:pointer; font-size:22px; line-height:1;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#a1a1aa'">&times;</button>
        </div>
        <form id="add-custom-doc-form" onsubmit="submitAddCustomDoc(event)" style="padding:20px;">
            <div style="margin-bottom:14px;">
                <label class="required" style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:5px;">Category</label>
                <select id="custom-doc-category" required style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:12.5px; color:#0f172a;">
                    <option value="Basic KYC">🪪 Basic KYC</option>
                    <option value="Income Proof">💼 Income Proof</option>
                    <option value="Business Proof">🏢 Business Proof</option>
                    <option value="Property / Asset Docs">🏠 Property / Asset Docs</option>
                    <option value="Vehicle Docs">🚗 Vehicle Docs</option>
                    <option value="Existing Loan Obligations">🏦 Existing Loan Obligations</option>
                    <option value="Co-Applicant Docs">👥 Co-Applicant Docs</option>
                    <option value="Bank Login & Fee Receipts">💳 Bank Receipts</option>
                    <option value="Other">📁 Other Custom Docs</option>
                </select>
            </div>

            <div style="margin-bottom:14px;">
                <label class="required" style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:5px;">Document Name / Title</label>
                <input type="text" id="custom-doc-title" placeholder="e.g. Khasra Khatoni / Rent Agreement" required style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; font-weight:600; color:#0f172a; outline:none; box-sizing:border-box;">
            </div>

            <div style="margin-bottom:16px;">
                <label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:5px;">Requirement Description / Hint</label>
                <input type="text" id="custom-doc-hint" placeholder="e.g. Latest certified copy under 3 months" style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:12.5px; color:#0f172a; outline:none; box-sizing:border-box;">
            </div>

            <div style="margin-bottom:18px; background:#f8fafc; padding:10px 12px; border-radius:6px; border:1px solid #e2e8f0; display:flex; align-items:center; justify-content:space-between;">
                <div>
                    <div style="font-size:12px; font-weight:700; color:#0f172a;">Is this Mandatory?</div>
                    <div style="font-size:11px; color:#64748b;">Must be uploaded before Phase 3 proceed</div>
                </div>
                <input type="checkbox" id="custom-doc-mandatory" style="width:18px; height:18px; accent-color:#0f172a; cursor:pointer;">
            </div>

            <div style="display:flex; justify-content:flex-end; gap:8px;">
                <button type="button" onclick="closeAddCustomDocModal()" class="btn" style="background:#f1f5f9; color:#475569; border:1px solid #cbd5e1; padding:8px 14px; font-size:12px; font-weight:700; border-radius:6px; cursor:pointer;">Cancel</button>
                <button type="submit" class="btn" style="background:#059669; color:#ffffff; border:none; padding:8px 18px; font-size:12px; font-weight:800; border-radius:6px; cursor:pointer; display:inline-flex; align-items:center; gap:4px;">
                    <i data-lucide="plus" style="width:13px; height:13px;"></i> Add to Checklist
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Instant Document Preview Modal -->
<div id="doc-preview-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.85); z-index:9999; justify-content:center; align-items:center; backdrop-filter:blur(4px);">
    <div style="background:#fff; border-radius:12px; width:90%; max-width:900px; height:85vh; display:flex; flex-direction:column; overflow:hidden; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);">
        <div style="padding:14px 20px; background:#0f172a; color:#fff; display:flex; justify-content:space-between; align-items:center;">
            <div style="display:flex; align-items:center; gap:10px;">
                <i data-lucide="file-text" style="width:18px;"></i>
                <span id="preview-modal-title" style="font-weight:700; font-size:15px;">Document Preview</span>
            </div>
            <div style="display:flex; gap:10px; align-items:center;">
                <a id="preview-download-btn" href="#" download class="btn btn-secondary" style="padding:4px 10px; font-size:12px; background:rgba(255,255,255,0.15); color:#fff; border:none;" target="_blank"><i data-lucide="download" style="width:14px;"></i> Download</a>
                <button onclick="closePreviewModal()" style="background:none; border:none; color:#fff; cursor:pointer; font-size:20px; line-height:1;">&times;</button>
            </div>
        </div>
        <div id="preview-modal-body" style="flex:1; background:#f8fafc; display:flex; justify-content:center; align-items:center; overflow:auto; padding:10px;">
            <!-- Rendered PDF or Image -->
        </div>
    </div>
</div>

<!-- 🖼️ 1-CLICK PHOTO LIGHTBOX GALLERY MODAL (CAROUSEL) -->
<div id="photo-gallery-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.95); z-index:99999; flex-direction:column; justify-content:space-between; backdrop-filter:blur(6px);">
    <!-- Topbar -->
    <div style="padding:14px 24px; background:rgba(0,0,0,0.6); display:flex; justify-content:space-between; align-items:center; color:#fff; border-bottom:1px solid rgba(255,255,255,0.1);">
        <div style="display:flex; align-items:center; gap:12px;">
            <i data-lucide="images" style="width:22px; color:#38bdf8;"></i>
            <div>
                <div id="gallery-doc-title" style="font-weight:800; font-size:16px; color:#fff;">Document Photo Gallery</div>
                <div id="gallery-counter-label" style="font-size:12px; color:#94a3b8;">Page 1 of 1</div>
            </div>
        </div>
        <div style="display:flex; align-items:center; gap:8px;">
            <button type="button" onclick="rotateGalleryImage()" class="btn btn-secondary" style="padding:6px 12px; font-size:12px; background:rgba(255,255,255,0.12); color:#fff; border:none;" title="Rotate 90°"><i data-lucide="rotate-cw" style="width:14px;"></i> Rotate</button>
            <a id="gallery-download-btn" href="#" download target="_blank" class="btn btn-primary" style="padding:6px 14px; font-size:12px; background:#10b981; border:none; font-weight:700;"><i data-lucide="download" style="width:14px;"></i> Download</a>
            <button type="button" onclick="closePhotoGalleryModal()" style="background:none; border:none; color:#fff; cursor:pointer; font-size:28px; line-height:1; margin-left:12px;">&times;</button>
        </div>
    </div>

    <!-- Main Stage -->
    <div style="flex:1; display:flex; align-items:center; justify-content:space-between; padding:20px; position:relative; overflow:hidden;">
        <button type="button" onclick="prevGalleryImage()" style="background:rgba(255,255,255,0.15); border:1px solid rgba(255,255,255,0.25); color:#fff; width:48px; height:48px; border-radius:50%; font-size:22px; cursor:pointer; display:flex; align-items:center; justify-content:center; backdrop-filter:blur(4px); z-index:10; transition:all 0.15s;" onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.15)'">&larr;</button>

        <div id="gallery-stage-body" style="flex:1; display:flex; flex-direction:column; justify-content:center; align-items:center; height:100%; overflow:auto; padding:10px;">
            <!-- Rendered image / PDF preview -->
        </div>

        <button type="button" onclick="nextGalleryImage()" style="background:rgba(255,255,255,0.15); border:1px solid rgba(255,255,255,0.25); color:#fff; width:48px; height:48px; border-radius:50%; font-size:22px; cursor:pointer; display:flex; align-items:center; justify-content:center; backdrop-filter:blur(4px); z-index:10; transition:all 0.15s;" onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.15)'">&rarr;</button>
    </div>

    <!-- Bottom Thumbnail Strip -->
    <div id="gallery-thumb-strip" style="padding:10px 20px; background:rgba(0,0,0,0.7); display:flex; gap:10px; overflow-x:auto; border-top:1px solid rgba(255,255,255,0.1); justify-content:center; align-items:center; min-height:80px;">
        <!-- Thumbnails inserted dynamically -->
    </div>
</div>

<!-- 📄 1-CLICK COMBINED MASTER PDF LIVE PREVIEW MODAL -->
<div id="master-pdf-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.9); z-index:99999; justify-content:center; align-items:center; backdrop-filter:blur(6px);">
    <div style="background:#fff; border-radius:14px; width:92%; max-width:980px; height:88vh; display:flex; flex-direction:column; overflow:hidden; box-shadow:0 25px 50px -12px rgba(0,0,0,0.3);">
        <div style="padding:14px 20px; background:#0f766e; color:#fff; display:flex; justify-content:space-between; align-items:center;">
            <div style="display:flex; align-items:center; gap:10px;">
                <i data-lucide="file-check" style="width:20px; color:#5eead4;"></i>
                <span style="font-weight:800; font-size:16px;">Combined Master Bank Dossier PDF (Live Preview)</span>
            </div>
            <div style="display:flex; gap:8px; align-items:center;">
                <button type="button" onclick="downloadMasterPdfFile()" class="btn btn-primary" style="padding:6px 14px; font-size:12px; background:#10b981; border:none; font-weight:700;"><i data-lucide="download" style="width:14px;"></i> Download PDF</button>
                <button type="button" onclick="printMasterPdfFrame()" class="btn btn-secondary" style="padding:6px 12px; font-size:12px; background:rgba(255,255,255,0.15); color:#fff; border:none;"><i data-lucide="printer" style="width:14px;"></i> Print</button>
                <button type="button" onclick="closeMasterPdfModal()" style="background:none; border:none; color:#fff; cursor:pointer; font-size:24px; line-height:1; margin-left:8px;">&times;</button>
            </div>
        </div>
        <div id="master-pdf-preview-body" style="flex:1; background:#1e293b; overflow-y:auto; padding:24px; display:flex; justify-content:center; align-items:flex-start;"></div>
    </div>
</div>

<!-- 📁/🖨️ SMART DOCUMENT CAPTURE CHOICE MODAL -->
<div id="doc-capture-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.85); z-index:999999; justify-content:center; align-items:center; backdrop-filter:blur(6px);">
    <div style="background:#ffffff; border-radius:14px; width:92%; max-width:460px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.3); overflow:hidden; border:1px solid #e2e8f0;">
        <div style="padding:16px 20px; background:#0f172a; color:#ffffff; display:flex; justify-content:space-between; align-items:center;">
            <div>
                <div style="font-weight:800; font-size:15px; display:flex; align-items:center; gap:8px;">
                    <i data-lucide="upload-cloud" style="width:18px; color:#38bdf8;"></i>
                    <span id="capture-modal-title">Upload Document</span>
                </div>
                <div id="capture-modal-subtitle" style="font-size:12px; color:#94a3b8; margin-top:2px;">Select document source method</div>
            </div>
            <button type="button" onclick="closeDocCaptureModal()" style="background:none; border:none; color:#ffffff; cursor:pointer; font-size:24px; line-height:1;">&times;</button>
        </div>
        
        <div style="padding:20px; display:flex; flex-direction:column; gap:12px; background:#f8fafc;">
            <!-- Option 1: File Explorer -->
            <button type="button" onclick="chooseCaptureMethod('explorer')" class="capture-opt-btn">
                <div class="capture-opt-icon" style="background:#e0f2fe; color:#0284c7;">
                    <i data-lucide="folder-open" style="width:22px; height:22px;"></i>
                </div>
                <div style="text-align:left; flex:1;">
                    <div style="font-weight:700; font-size:13.5px; color:#0f172a;">Upload File from Explorer</div>
                    <div style="font-size:11.5px; color:#64748b; margin-top:2px;">Select PDF, JPG, PNG from device</div>
                </div>
                <i data-lucide="chevron-right" style="width:18px; color:#94a3b8;"></i>
            </button>

            <!-- Option 2: Hardware Scanner -->
            <button type="button" onclick="chooseCaptureMethod('scanner')" class="capture-opt-btn">
                <div class="capture-opt-icon" style="background:#ffedd5; color:#ea580c;">
                    <i data-lucide="printer" style="width:22px; height:22px;"></i>
                </div>
                <div style="text-align:left; flex:1;">
                    <div style="font-weight:700; font-size:13.5px; color:#0f172a;">Scan from Hardware Printer</div>
                    <div style="font-size:11.5px; color:#64748b; margin-top:2px;">Direct capture from connected scanner</div>
                </div>
                <i data-lucide="chevron-right" style="width:18px; color:#94a3b8;"></i>
            </button>
        </div>
    </div>
</div>

<!-- 📁 MULTI-FILE LIST PREVIEW MODAL FOR A CHECKLIST CARD -->
<div id="card-files-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.85); z-index:999999; justify-content:center; align-items:center; backdrop-filter:blur(6px);">
    <div style="background:#ffffff; border-radius:14px; width:92%; max-width:600px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.3); overflow:hidden; border:1px solid #e2e8f0;">
        <div style="padding:16px 20px; background:#0f172a; color:#ffffff; display:flex; justify-content:space-between; align-items:center;">
            <div>
                <div style="font-weight:800; font-size:15px; display:flex; align-items:center; gap:8px;">
                    <i data-lucide="files" style="width:18px; color:#38bdf8;"></i>
                    <span id="card-files-modal-title">Uploaded Files for Document</span>
                </div>
                <div id="card-files-modal-subtitle" style="font-size:12px; color:#94a3b8; margin-top:2px;">All files associated with this requirement</div>
            </div>
            <button type="button" onclick="closeCardFilesModal()" style="background:none; border:none; color:#ffffff; cursor:pointer; font-size:24px; line-height:1;">&times;</button>
        </div>
        
        <div id="card-files-modal-body" style="padding:20px; display:flex; flex-direction:column; gap:10px; max-height:60vh; overflow-y:auto; background:#f8fafc;">
            <!-- Rendered list of files -->
        </div>

        <div style="padding:14px 20px; background:#ffffff; border-top:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
            <span style="font-size:12px; color:#64748b;">Need to add another page or file?</span>
            <button type="button" id="card-files-add-more-btn" class="btn btn-primary" style="padding:6px 14px; font-size:12px; background:#0f172a; border:none; font-weight:700; display:inline-flex; align-items:center; gap:6px;">
                <i data-lucide="plus" style="width:14px;"></i> Upload Additional File
            </button>
        </div>
    </div>
</div>

<style>
.capture-opt-btn {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    cursor: pointer;
    text-align: left;
    width: 100%;
    transition: all 0.15s ease;
}
.capture-opt-btn:hover {
    background: #ffffff;
    border-color: #0f172a;
    box-shadow: 0 4px 14px rgba(15,23,42,0.08);
    transform: translateY(-1px);
}
.capture-opt-icon {
    width: 42px;
    height: 42px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
</style>

<style>
.chk-tab-btn {
    padding: 6px 12px;
    font-size: 12px;
    font-weight: 600;
    border-radius: 6px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #475569;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    white-space: nowrap;
    transition: all 0.15s ease;
}
.chk-tab-btn:hover {
    background: #f1f5f9;
    color: #0f172a;
}
.chk-tab-btn.active {
    background: #0f172a;
    color: #ffffff;
    border-color: #0f172a;
}
</style>

<!-- html2pdf Library for Master PDF Generation -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<script>
    const loanType = "<?php echo addslashes($applicant['loan_type']); ?>";
    const empType = "<?php echo addslashes($applicant['employment_type']); ?>";
    const applicantId = <?php echo $id; ?>;
    window.currentChecklistFilter = 'all';
    window.currentTargetBank = 'ALL';

    function switchUploadTab(tab) {
        const singlePanel = document.getElementById('upload-single-panel');
        const bulkPanel = document.getElementById('upload-bulk-panel');
        const singleBtn = document.getElementById('tab-btn-single');
        const bulkBtn = document.getElementById('tab-btn-bulk');

        if (tab === 'single') {
            if (singlePanel) singlePanel.style.display = 'block';
            if (bulkPanel) bulkPanel.style.display = 'none';
            if (singleBtn) {
                singleBtn.style.background = '#ffffff';
                singleBtn.style.color = '#0f172a';
                singleBtn.style.boxShadow = '0 1px 3px rgba(15,23,42,0.08)';
                singleBtn.style.fontWeight = '800';
            }
            if (bulkBtn) {
                bulkBtn.style.background = 'transparent';
                bulkBtn.style.color = '#64748b';
                bulkBtn.style.boxShadow = 'none';
                bulkBtn.style.fontWeight = '700';
            }
        } else {
            if (singlePanel) singlePanel.style.display = 'none';
            if (bulkPanel) bulkPanel.style.display = 'block';
            if (bulkBtn) {
                bulkBtn.style.background = '#ffffff';
                bulkBtn.style.color = '#0f172a';
                bulkBtn.style.boxShadow = '0 1px 3px rgba(15,23,42,0.08)';
                bulkBtn.style.fontWeight = '800';
            }
            if (singleBtn) {
                singleBtn.style.background = 'transparent';
                singleBtn.style.color = '#64748b';
                singleBtn.style.boxShadow = 'none';
                singleBtn.style.fontWeight = '700';
            }
        }
    }

    function setTargetBankFilter(bank) {
        window.currentTargetBank = bank;
        if (window.currentDocAppData) {
            renderChecklistGrid(window.currentDocAppData);
        }
        showNotification(`Checklist updated for target bank: ${bank}`, 'info');
    }

    async function sendClientSelfUploadLink(appId) {
        const data = window.currentDocAppData || {};
        let token = data.tracking_token || "<?php echo addslashes($applicant['tracking_token'] ?? ''); ?>";
        
        if (!token) {
            try {
                const fd = new FormData();
                fd.append('applicant_id', appId);
                const res = await fetch('api.php?api=generate_tracking_token', { method: 'POST', body: fd });
                const json = await res.json();
                if (json && json.token) {
                    token = json.token;
                    if (window.currentDocAppData) window.currentDocAppData.tracking_token = token;
                }
            } catch (e) {
                console.error(e);
            }
        }

        const portalUrl = token 
            ? `${window.location.origin}/track.php?token=${token}`
            : `${window.location.origin}/client_upload.php?id=${appId}`;
        
        const input = document.getElementById('client-portal-link-input');
        const openBtn = document.getElementById('client-portal-open-btn');
        const modal = document.getElementById('client-upload-link-modal');

        if (input) input.value = portalUrl;
        if (openBtn) openBtn.href = portalUrl;
        if (modal) modal.style.display = 'flex';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function closeClientSelfUploadModal() {
        const modal = document.getElementById('client-upload-link-modal');
        if (modal) modal.style.display = 'none';
    }

    function copyClientPortalLink() {
        const input = document.getElementById('client-portal-link-input');
        if (input) {
            input.select();
            navigator.clipboard.writeText(input.value);
            showNotification('✅ Portal link copied to clipboard!', 'success');
        }
    }

    function sendClientPortalWhatsApp() {
        const data = window.currentDocAppData || {};
        const customerName = data.customer_name || 'Applicant';
        const mobile = (data.mobile || '').replace(/[^0-9]/g, '');
        const portalUrl = document.getElementById('client-portal-link-input').value;

        const msg = `Dear ${customerName},\n\nGreetings from BFS Circle!\n\nPlease upload your pending loan documents directly using your dedicated client link:\n\n🔗 ${portalUrl}\n\nYou can attach PDFs or snap photos directly using your phone camera.\n\nThank you,\nBFS Financial Services`;

        if (mobile) {
            window.open(`https://wa.me/91${mobile}?text=${encodeURIComponent(msg)}`, '_blank');
            showNotification('WhatsApp window opened!', 'success');
        } else {
            alert('Applicant mobile number is missing. Link copied instead.');
            copyClientPortalLink();
        }
    }

    // ===== DSA BULK MULTI-FILE SMART UPLOADER ENGINE =====
    let bulkSelectedFiles = [];

    function autoDetectDocCategoryAndName(filename) {
        const f = filename.toLowerCase();
        
        // Smart Co-Applicant Owner Auto-Detection
        let ownerVal = 'Applicant_0';
        if (window.currentDocAppData && window.currentDocAppData.co_applicants) {
            window.currentDocAppData.co_applicants.forEach(co => {
                const nameParts = (co.full_name || '').toLowerCase().split(' ');
                if (nameParts.some(part => part.length > 2 && f.includes(part)) || f.includes('coapp') || f.includes('co_app') || f.includes('spouse') || f.includes('guarantor')) {
                    ownerVal = `CoApplicant_${co.id}`;
                }
            });
        }

        // Smart Category & Specific Doc Name Regex Engine
        if (f.includes('pan')) return { cat: 'Basic KYC', name: 'PAN Card Front', owner: ownerVal };
        if (f.includes('aadhaar') || f.includes('adhar')) return { cat: 'Basic KYC', name: 'Aadhaar Card (Front & Back)', owner: ownerVal };
        if (f.includes('photo') || f.includes('passport')) return { cat: 'Basic KYC', name: 'Passport Size Photo', owner: ownerVal };
        if (f.includes('voter')) return { cat: 'Basic KYC', name: 'Voter ID Card', owner: ownerVal };
        if (f.includes('driving') || f.includes('dl')) return { cat: 'Basic KYC', name: 'Driving License', owner: ownerVal };
        
        if (f.includes('salary') || f.includes('payslip')) return { cat: 'Income Proof', name: 'Last 3 Months Salary Slips', owner: ownerVal };
        if (f.includes('statement') || f.includes('bank') || f.includes('passbook')) return { cat: 'Income Proof', name: 'Last 6 Months Salary Bank Statement', owner: ownerVal };
        if (f.includes('itr') || f.includes('form16') || f.includes('form 16') || f.includes('computation')) return { cat: 'Income Proof', name: 'Latest Form 16 / ITR', owner: ownerVal };
        
        if (f.includes('registry') || f.includes('deed') || f.includes('map') || f.includes('property') || f.includes('tax')) return { cat: 'Property / Asset Docs', name: 'Property Sale Deed / Registry', owner: ownerVal };
        if (f.includes('bill') || f.includes('electricity') || f.includes('utility')) return { cat: 'Property / Asset Docs', name: 'Latest Utility Bill', owner: ownerVal };
        
        if (f.includes('gst') || f.includes('trade') || f.includes('msme') || f.includes('udyam')) return { cat: 'Business Proof', name: 'GST Registration Certificate', owner: ownerVal };
        if (f.includes('rc') || f.includes('vehicle')) return { cat: 'Vehicle Docs', name: 'Vehicle RC Copy', owner: ownerVal };
        if (f.includes('receipt') || f.includes('cibil') || f.includes('fee')) return { cat: 'Bank Login & Fee Receipts', name: 'Bank Login / Processing Fee Receipt', owner: ownerVal };
        
        return { cat: 'Basic KYC', name: filename.replace(/\.[^/.]+$/, ""), owner: ownerVal };
    }

    function handleBulkFileDrop(e) {
        e.preventDefault();
        const dropZone = document.getElementById('bulk-drop-zone');
        if (dropZone) dropZone.style.borderColor = '#38bdf8';
        if (e.dataTransfer && e.dataTransfer.files) {
            processFilesArray(Array.from(e.dataTransfer.files));
        }
    }

    function handleBulkFilesSelected(input) {
        if (input.files && input.files.length > 0) {
            processFilesArray(Array.from(input.files));
        }
    }

    function processFilesArray(files) {
        bulkSelectedFiles = files.map(file => {
            const detected = autoDetectDocCategoryAndName(file.name);
            return {
                file: file,
                category: detected.cat,
                name: detected.name,
                owner: detected.owner || 'Applicant_0'
            };
        });
        renderBulkFilesPreview();
    }

    function renderBulkFilesPreview() {
        const previewList = document.getElementById('bulk-files-preview-list');
        if (!previewList) return;

        if (bulkSelectedFiles.length === 0) {
            previewList.style.display = 'none';
            return;
        }

        const appData = window.currentDocAppData || {};
        const coApps = appData.co_applicants || [];
        const primaryName = appData.customer_name || 'Primary Applicant';

        let html = `
            <div style="background:#ffffff; border:1px solid #0f172a; border-radius:10px; padding:14px; box-shadow:0 4px 14px rgba(15,23,42,0.06);">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; border-bottom:1px solid #e2e8f0; padding-bottom:10px; flex-wrap:wrap; gap:10px;">
                    <div>
                        <div style="font-weight:800; font-size:14px; color:#0f172a; font-family:'Outfit',sans-serif;">
                            ⚡ Ready to Batch Upload (${bulkSelectedFiles.length} Files)
                        </div>
                        <div style="font-size:11px; color:#64748b;">AI Auto-Detected categories & document owners below</div>
                    </div>
                    <button type="button" onclick="processBatchBulkUpload()" id="batch-upload-btn" class="btn btn-primary" style="background:#0f172a; border:none; padding:8px 18px; font-weight:800; font-size:12.5px; display:inline-flex; align-items:center; gap:6px; border-radius:6px; cursor:pointer;">
                        <i data-lucide="upload-cloud" style="width:14px;"></i> Upload All ${bulkSelectedFiles.length} Files Now
                    </button>
                </div>

                <!-- Live Batch Progress Bar Container -->
                <div id="bulk-progress-bar-container" style="display:none; margin-bottom:12px; background:#f1f5f9; border-radius:8px; padding:8px 12px; border:1px solid #cbd5e1;">
                    <div style="display:flex; justify-content:space-between; font-size:11.5px; font-weight:700; color:#0f172a; margin-bottom:4px;">
                        <span id="bulk-progress-status-text">Uploading Batch...</span>
                        <span id="bulk-progress-pct-text">0%</span>
                    </div>
                    <div style="width:100%; height:8px; background:#e2e8f0; border-radius:4px; overflow:hidden;">
                        <div id="bulk-progress-fill" style="width:0%; height:100%; background:#0f172a; transition:width 0.2s ease;"></div>
                    </div>
                </div>

                <div style="display:flex; flex-direction:column; gap:8px; max-height:300px; overflow-y:auto; padding-right:4px;">
        `;

        bulkSelectedFiles.forEach((item, idx) => {
            const ext = item.file.name.split('.').pop().toLowerCase();
            const isImg = ['jpg', 'jpeg', 'png', 'webp'].includes(ext);
            const thumbSrc = isImg ? URL.createObjectURL(item.file) : '';

            let ownerOptionsHtml = `<option value="Applicant_0" ${item.owner === 'Applicant_0' ? 'selected' : ''}>Primary: ${primaryName}</option>`;
            if (coApps.length > 0) {
                coApps.forEach(co => {
                    const optKey = `CoApplicant_${co.id}`;
                    ownerOptionsHtml += `<option value="${optKey}" ${item.owner === optKey ? 'selected' : ''}>Co-App: ${co.full_name}</option>`;
                });
            }

            html += `
                <div style="display:grid; grid-template-columns: 2fr 1.3fr 1.5fr 1.3fr 32px; gap:8px; align-items:center; background:#f8fafc; padding:8px 12px; border-radius:6px; border:1px solid #cbd5e1; font-size:12px;">
                    <div style="display:flex; align-items:center; gap:8px; overflow:hidden;">
                        ${isImg ? `<img src="${thumbSrc}" style="width:32px; height:32px; object-fit:cover; border-radius:4px; border:1px solid #cbd5e1; flex-shrink:0;">` : `<div style="width:32px; height:32px; border-radius:4px; background:#e2e8f0; display:flex; align-items:center; justify-content:center; flex-shrink:0; font-size:10px; font-weight:800; color:#0f172a;">PDF</div>`}
                        <div style="overflow:hidden;">
                            <strong style="color:#0f172a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; display:block;" title="${item.file.name}">${item.file.name}</strong>
                            <div style="font-size:10.5px; color:#64748b;">${Math.round(item.file.size / 1024)} KB</div>
                        </div>
                    </div>
                    <div>
                        <select onchange="bulkSelectedFiles[${idx}].category = this.value" style="width:100%; padding:4px 6px; font-size:11px; border-radius:4px; border:1px solid #cbd5e1; background:#ffffff; font-weight:600;">
                            <option value="Basic KYC" ${item.category === 'Basic KYC' ? 'selected' : ''}>Basic KYC</option>
                            <option value="Income Proof" ${item.category === 'Income Proof' ? 'selected' : ''}>Income Proof</option>
                            <option value="Business Proof" ${item.category === 'Business Proof' ? 'selected' : ''}>Business Proof</option>
                            <option value="Property / Asset Docs" ${item.category === 'Property / Asset Docs' ? 'selected' : ''}>Property Docs</option>
                            <option value="Vehicle Docs" ${item.category === 'Vehicle Docs' ? 'selected' : ''}>Vehicle Docs</option>
                            <option value="Existing Loan Obligations" ${item.category === 'Existing Loan Obligations' ? 'selected' : ''}>Loan SOA</option>
                            <option value="Co-Applicant Docs" ${item.category === 'Co-Applicant Docs' ? 'selected' : ''}>Co-App Docs</option>
                            <option value="Bank Login & Fee Receipts" ${item.category === 'Bank Login & Fee Receipts' ? 'selected' : ''}>Bank Receipts</option>
                            <option value="Other" ${item.category === 'Other' ? 'selected' : ''}>Other</option>
                        </select>
                    </div>
                    <div>
                        <input type="text" value="${item.name.replace(/"/g, '&quot;')}" onchange="bulkSelectedFiles[${idx}].name = this.value" style="width:100%; padding:4px 6px; font-size:11px; border-radius:4px; border:1px solid #cbd5e1; background:#ffffff; font-weight:600;" placeholder="Specific Doc Name">
                    </div>
                    <div>
                        <select onchange="bulkSelectedFiles[${idx}].owner = this.value" style="width:100%; padding:4px 6px; font-size:11px; border-radius:4px; border:1px solid #cbd5e1; background:#ffffff; font-weight:600;">
                            ${ownerOptionsHtml}
                        </select>
                    </div>
                    <div style="text-align:right;">
                        <button type="button" onclick="bulkSelectedFiles.splice(${idx}, 1); renderBulkFilesPreview();" style="background:none; border:none; color:#dc2626; cursor:pointer; font-weight:bold; font-size:16px;" title="Remove File">&times;</button>
                    </div>
                </div>
            `;
        });

        html += `</div></div>`;
        previewList.innerHTML = html;
        previewList.style.display = 'block';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    async function processBatchBulkUpload() {
        if (bulkSelectedFiles.length === 0) return;
        
        const btn = document.getElementById('batch-upload-btn');
        const progContainer = document.getElementById('bulk-progress-bar-container');
        const progStatusText = document.getElementById('bulk-progress-status-text');
        const progPctText = document.getElementById('bulk-progress-pct-text');
        const progFill = document.getElementById('bulk-progress-fill');

        if (btn) btn.disabled = true;
        if (progContainer) progContainer.style.display = 'block';

        let successCount = 0;
        const total = bulkSelectedFiles.length;

        for (let i = 0; i < total; i++) {
            const item = bulkSelectedFiles[i];
            const currentPct = Math.round(((i) / total) * 100);

            if (progStatusText) progStatusText.innerText = `Uploading File ${i + 1} of ${total}: ${item.name}...`;
            if (progPctText) progPctText.innerText = `${currentPct}%`;
            if (progFill) progFill.style.width = `${currentPct}%`;

            const formData = new FormData();
            formData.append('applicant_id', applicantId);
            formData.append('document_category', item.category);
            formData.append('document_name', item.name);
            formData.append('owner_val', item.owner);
            formData.append('document_file[]', item.file);

            try {
                const res = await fetch('api.php?api=upload_applicant_document', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    successCount++;
                }
            } catch (e) {
                console.error(e);
            }
        }

        if (progStatusText) progStatusText.innerText = `✅ Completed! ${successCount} of ${total} Files Saved.`;
        if (progPctText) progPctText.innerText = `100%`;
        if (progFill) {
            progFill.style.width = `100%`;
            progFill.style.background = `#16a34a`;
        }

        showNotification(`⚡ Bulk Upload Complete! ${successCount} of ${total} files saved.`, 'success');
        
        setTimeout(() => {
            bulkSelectedFiles = [];
            renderBulkFilesPreview();
            loadDocuments();
        }, 800);
    }

    function setChecklistFilter(filter) {
        window.currentChecklistFilter = filter;
        if (window.currentDocAppData) {
            renderChecklistGrid(window.currentDocAppData);
        }
    }

    function setTargetBankFilter(bankVal) {
        window.currentTargetBankFilter = bankVal;
        showNotification(bankVal === 'ALL' ? 'Showing checklist for All Eligible Banks' : `Target Bank Filter: ${bankVal} (Eligible Lender)`, 'info');
        if (window.currentDocAppData) {
            renderChecklistGrid(window.currentDocAppData);
        }
    }

    function renderChecklistTabs(appData) {
        const tabsContainer = document.getElementById('checklist-filter-tabs');
        if (!tabsContainer) return;

        const coApps = appData.co_applicants || [];
        const primaryName = appData.customer_name || 'Primary Applicant';

        let html = `
            <button type="button" onclick="setChecklistFilter('all')" class="chk-tab-btn ${window.currentChecklistFilter === 'all' ? 'active' : ''}">
                <i data-lucide="layers" style="width:13px;"></i> All Checklist Items
            </button>
            <button type="button" onclick="setChecklistFilter('Applicant')" class="chk-tab-btn ${window.currentChecklistFilter === 'Applicant' ? 'active' : ''}">
                <i data-lucide="user" style="width:13px;"></i> Primary: ${primaryName}
            </button>
        `;

        if (coApps.length > 0) {
            coApps.forEach(co => {
                const isFin = (co.is_financial === 'Yes' || co.is_financial == 1);
                const filterKey = `CoApplicant_${co.id}`;
                html += `
                    <button type="button" onclick="setChecklistFilter('${filterKey}')" class="chk-tab-btn ${window.currentChecklistFilter === filterKey ? 'active' : ''}">
                        <i data-lucide="users" style="width:13px;"></i> Co-App: ${co.full_name} ${isFin ? '(Financial)' : ''}
                    </button>
                `;
            });
        }

        tabsContainer.innerHTML = html;
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function updateOwnerDropdown(coApps, primaryName) {
        const ownerSelect = document.getElementById('owner_val');
        if (!ownerSelect) return;
        const currentVal = window.pendingSelectCoApp || ownerSelect.value;

        let optionsHtml = `<option value="Applicant_0">Primary Applicant (${primaryName})</option>`;
        if (coApps && coApps.length > 0) {
            coApps.forEach(co => {
                const finLabel = (co.is_financial === 'Yes' || co.is_financial == 1) ? ' [Financial]' : '';
                optionsHtml += `<option value="CoApplicant_${co.id}">Co-Applicant: ${co.full_name} (${co.relationship || 'Co-App'})${finLabel}</option>`;
            });
        }
        ownerSelect.innerHTML = optionsHtml;
        if (currentVal && ownerSelect.querySelector(`option[value="${currentVal}"]`)) {
            ownerSelect.value = currentVal;
        }
        window.pendingSelectCoApp = null;
    }

    function handleRecommendedDocSelect(valJson) {
        if (!valJson) return;
        try {
            const item = JSON.parse(decodeURIComponent(valJson));
            prefillUpload(item.category, item.docName, item.ownerType, item.ownerId);
            showNotification(`Selected: ${item.docName}`, 'info');
        } catch(e) {
            console.error(e);
        }
    }

    function getCategoryIcon(catName) {
        const c = (catName || '').toLowerCase();
        if (c.includes('kyc') || c.includes('identity')) return 'shield-check';
        if (c.includes('income') || c.includes('financial') || c.includes('salary')) return 'banknote';
        if (c.includes('property') || c.includes('asset') || c.includes('deed')) return 'home';
        if (c.includes('business') || c.includes('gst') || c.includes('tax')) return 'briefcase';
        if (c.includes('loan') || c.includes('obligation') || c.includes('soa')) return 'credit-card';
        if (c.includes('vehicle') || c.includes('rc')) return 'car';
        return 'folder-open';
    }

    function toggleCatGroup(groupIdx) {
        const body = document.getElementById(`cat-group-body-${groupIdx}`);
        const iconContainer = document.getElementById(`cat-group-icon-${groupIdx}`);
        if (!body || !iconContainer) return;

        if (body.style.display === 'none') {
            body.style.display = 'grid';
            iconContainer.style.transform = 'rotate(0deg)';
        } else {
            body.style.display = 'none';
            iconContainer.style.transform = 'rotate(-180deg)';
        }
    }

    function toggleChecklistBody() {
        const wrapper = document.getElementById('checklist-collapsible-wrapper');
        const btn = document.getElementById('checklist-toggle-btn');

        if (wrapper.style.display === 'none') {
            wrapper.style.display = 'block';
            if (btn) btn.innerHTML = '<i data-lucide="chevron-up" id="chk-toggle-icon" style="width:14px; height:14px;"></i> Collapse All';
        } else {
            wrapper.style.display = 'none';
            if (btn) btn.innerHTML = '<i data-lucide="chevron-down" id="chk-toggle-icon" style="width:14px; height:14px;"></i> Expand All';
        }
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function renderChecklistGrid(appData) {
        renderChecklistTabs(appData);
        const grid = document.getElementById('smart-checklist-grid');
        const progressText = document.getElementById('checklist-progress-text');
        const checklist = appData.phase2_checklist || [];
        
        let mandatoryUploadedCount = 0;
        let mandatoryTotalCount = 0;

        checklist.forEach(item => {
            if (item.is_mandatory) mandatoryTotalCount++;
            if (item.is_mandatory && item.uploaded) mandatoryUploadedCount++;
        });

        // Filter checklist items based on current tab
        let filteredChecklist = checklist;
        if (window.currentChecklistFilter === 'Applicant') {
            filteredChecklist = checklist.filter(item => item.owner_type === 'Applicant' || !item.owner_type);
        } else if (window.currentChecklistFilter.startsWith('CoApplicant_')) {
            const targetCoId = parseInt(window.currentChecklistFilter.replace('CoApplicant_', ''));
            filteredChecklist = checklist.filter(item => item.owner_type === 'CoApplicant' && (item.owner_id == targetCoId || !item.owner_id));
        }

        if (grid && filteredChecklist.length > 0) {
            // Group items by category for clean accordion layout
            const categoryGroups = {};
            filteredChecklist.forEach(item => {
                const catName = item.category || 'General KYC / Loan Docs';
                if (!categoryGroups[catName]) categoryGroups[catName] = [];
                categoryGroups[catName].push(item);
            });

            let accordionHtml = '';
            let groupIdx = 0;

            for (const catName in categoryGroups) {
                groupIdx++;
                const groupItems = categoryGroups[catName];
                const uploadedCount = groupItems.filter(i => i.uploaded || (i.matched_docs && i.matched_docs.length > 0)).length;
                const isAllUploaded = uploadedCount === groupItems.length;
                const catIcon = getCategoryIcon(catName);

                const safeCatName = encodeURIComponent(catName);

                accordionHtml += `
                    <div style="background:#ffffff; border:1px solid ${isAllUploaded ? '#cbd5e1' : '#cbd5e1'}; border-radius:10px; overflow:hidden; margin-bottom:4px; box-shadow:0 1px 3px rgba(15,23,42,0.03); transition:all 0.2s ease;">
                        <div onclick="toggleCatGroup(${groupIdx})" style="padding:12px 16px; background:${isAllUploaded ? '#f8fafc' : '#ffffff'}; font-weight:700; font-size:13.5px; color:#0f172a; cursor:pointer; display:flex; justify-content:space-between; align-items:center; user-select:none; border-bottom:1px solid #f1f5f9; transition:background 0.15s ease;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='${isAllUploaded ? '#f8fafc' : '#ffffff'}'">
                            <div style="display:flex; align-items:center; gap:10px;">
                                <div style="width:32px; height:32px; border-radius:6px; background:${isAllUploaded ? '#ecfdf5' : '#f1f5f9'}; color:${isAllUploaded ? '#059669' : '#0f172a'}; display:flex; align-items:center; justify-content:center; border:1px solid ${isAllUploaded ? '#a7f3d0' : '#e2e8f0'}; flex-shrink:0;">
                                    <i data-lucide="${catIcon}" style="width:16px; height:16px;"></i>
                                </div>
                                <div>
                                    <div style="font-family:'Outfit', sans-serif; font-weight:800; font-size:13.5px; color:#0f172a;">${catName}</div>
                                    <div style="font-size:11px; color:#64748b; font-weight:500;">${groupItems.length} requirement(s) listed</div>
                                </div>
                            </div>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <button type="button" onclick="event.stopPropagation(); openAddCustomDocModal('${safeCatName}')" style="background:#ffffff; color:#0f172a; border:1px solid #cbd5e1; font-size:11px; font-weight:700; padding:3px 9px; border-radius:5px; cursor:pointer; display:inline-flex; align-items:center; gap:4px; box-shadow:0 1px 2px rgba(0,0,0,0.05);" title="Add new custom document requirement to ${catName}">
                                    <i data-lucide="plus" style="width:12px; height:12px; color:#059669;"></i> Add Doc
                                </button>
                                <span style="font-size:11px; font-weight:700; padding:4px 10px; border-radius:20px; ${isAllUploaded ? 'background:#dcfce7; color:#15803d; border:1px solid #86efac;' : 'background:#f1f5f9; color:#475569; border:1px solid #cbd5e1;'}">
                                    ${isAllUploaded ? '✓ Complete (' + uploadedCount + '/' + groupItems.length + ')' : uploadedCount + ' of ' + groupItems.length + ' Uploaded'}
                                </span>
                                <div id="cat-group-icon-${groupIdx}" style="width:26px; height:26px; border-radius:50%; background:#f8fafc; border:1px solid #cbd5e1; display:flex; align-items:center; justify-content:center; transition:transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);">
                                    <i data-lucide="chevron-down" style="width:14px; height:14px; color:#0f172a;"></i>
                                </div>
                            </div>
                        </div>
                        <div id="cat-group-body-${groupIdx}" style="padding:14px; display:grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap:10px; background:#f8fafc;">
                `;

                groupItems.forEach(item => {
                    const matchedDocs = item.matched_docs || (item.matched_doc ? [item.matched_doc] : []);
                    const matchCount = matchedDocs.length;
                    const isUploaded = item.uploaded || matchCount > 0;
                    const hasVault = !isUploaded && item.vault_match;
                    
                    const safeDocName = encodeURIComponent(item.doc_name || '').replace(/'/g, "%27");
                    const safeHint = encodeURIComponent(item.hint || '').replace(/'/g, "%27");
                    const safeCat = encodeURIComponent(item.category || '').replace(/'/g, "%27");
                    const safeOrigName = encodeURIComponent(item.original_doc_name || item.doc_name || '').replace(/'/g, "%27");
                    
                    let statusBadge = '';
                    if (matchCount > 1) {
                        statusBadge = `<span style="background:#f0fdf4; color:#15803d; border:1px solid #86efac; font-weight:700; font-size:10.5px; padding:3px 9px; border-radius:20px; display:inline-flex; align-items:center; gap:4px;"><i data-lucide="check-circle-2" style="width:12px; height:12px;"></i> ${matchCount} Files Uploaded</span>`;
                    } else if (isUploaded) {
                        statusBadge = `<span style="background:#f0fdf4; color:#15803d; border:1px solid #86efac; font-weight:700; font-size:10.5px; padding:3px 9px; border-radius:20px; display:inline-flex; align-items:center; gap:4px;"><i data-lucide="check-circle-2" style="width:12px; height:12px;"></i> Uploaded</span>`;
                    } else if (hasVault) {
                        statusBadge = `<span style="background:#faf5ff; color:#7e22ce; border:1px solid #d8b4fe; font-weight:700; font-size:10.5px; padding:3px 9px; border-radius:20px; display:inline-flex; align-items:center; gap:4px;"><i data-lucide="sparkles" style="width:12px; height:12px;"></i> in Client Vault</span>`;
                    } else {
                        statusBadge = `<span style="background:#fef2f2; color:#dc2626; border:1px solid #fca5a5; font-weight:700; font-size:10.5px; padding:3px 9px; border-radius:20px; display:inline-flex; align-items:center; gap:4px;"><i data-lucide="alert-circle" style="width:12px; height:12px;"></i> Missing</span>`;
                    }

                    const ownerBadge = item.owner_type === 'CoApplicant' ? `<span style="background:#e2e8f0; color:#334155; font-size:10px; padding:2px 6px; border-radius:4px; font-weight:700;">Co-App: ${item.coapp_name || ''}</span>` : '';
                    
                    let actionBtn = '';
                    if (matchCount > 1) {
                        actionBtn = `
                            <div style="display:flex; gap:4px;">
                                <button type="button" onclick="openCardFileListModal('${encodeURIComponent(JSON.stringify(matchedDocs))}', '${item.doc_name.replace(/'/g, "\\'")}', '${item.category}', '${item.owner_type}', '${item.owner_id || 0}')" class="btn btn-secondary" style="padding:4px 8px; font-size:11px; display:inline-flex; align-items:center; gap:4px; background:#0f172a; color:#fff; border:none;"><i data-lucide="files" style="width:12px;"></i> View ${matchCount} Files</button>
                                <button type="button" onclick="prefillUpload('${item.category}', '${item.doc_name.replace(/'/g, "\\'")}', '${item.owner_type}', '${item.owner_id || 0}')" class="btn btn-secondary" style="padding:4px 6px; font-size:11px;" title="Add another file"><i data-lucide="plus" style="width:12px;"></i></button>
                            </div>
                        `;
                    } else if (isUploaded) {
                        actionBtn = `
                            <div style="display:flex; gap:4px;">
                                <button type="button" onclick="openPreviewModal('${item.matched_doc.file_path}', '${encodeURIComponent(item.matched_doc.document_name)}')" class="btn btn-secondary" style="padding:4px 8px; font-size:11px; display:inline-flex; align-items:center; gap:4px;"><i data-lucide="eye" style="width:12px;"></i> View File</button>
                                <button type="button" onclick="prefillUpload('${item.category}', '${item.doc_name.replace(/'/g, "\\'")}', '${item.owner_type}', '${item.owner_id || 0}')" class="btn btn-secondary" style="padding:4px 6px; font-size:11px;" title="Add another file"><i data-lucide="plus" style="width:12px;"></i></button>
                            </div>
                        `;
                    } else if (hasVault) {
                        actionBtn = `<button type="button" onclick="attachFromVault(${item.vault_match.id})" class="btn btn-primary" style="padding:4px 8px; font-size:11px; background:#9333ea; border:none; display:inline-flex; align-items:center; gap:4px;" title="Source: ${item.vault_match.source_loan_id}"><i data-lucide="link-2" style="width:12px;"></i> Attach from Vault</button>`;
                    } else {
                        actionBtn = `<button type="button" onclick="prefillUpload('${item.category}', '${item.doc_name.replace(/'/g, "\\'")}', '${item.owner_type}', '${item.owner_id || 0}')" class="btn btn-primary" style="padding:4px 8px; font-size:11px; display:inline-flex; align-items:center; gap:4px; background:#0f172a; border:none;"><i data-lucide="upload" style="width:12px;"></i> Upload This</button>`;
                    }

                    accordionHtml += `
                        <div style="background:#ffffff; border:1px solid ${isUploaded ? '#cbd5e1' : (hasVault ? '#e9d5ff' : '#cbd5e1')}; border-radius:8px; padding:12px; display:flex; flex-direction:column; justify-content:space-between; gap:10px; box-shadow:0 1px 2px rgba(15,23,42,0.03); transition:border-color 0.15s ease;" onmouseover="this.style.borderColor='#94a3b8'" onmouseout="this.style.borderColor='${isUploaded ? '#cbd5e1' : (hasVault ? '#e9d5ff' : '#cbd5e1')}'">
                            <div>
                                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:4px; gap:6px;">
                                    <div style="display:inline-flex; align-items:center; gap:6px; flex-wrap:wrap;">
                                        <span style="font-weight:700; color:#0f172a; font-size:13px; line-height:1.3;">
                                            ${item.doc_name} ${item.is_mandatory ? '<span style="color:#ef4444; font-weight:bold;" title="Mandatory">*</span>' : ''}
                                        </span>
                                        <button type="button" onclick="event.stopPropagation(); openEditDocNameModal('${safeDocName}', '${safeHint}', '${safeCat}', ${item.is_mandatory ? 1 : 0}, '${safeOrigName}')" style="background:none; border:none; color:#94a3b8; cursor:pointer; padding:2px; border-radius:3px; display:inline-flex; align-items:center; transition:color 0.15s ease;" onmouseover="this.style.color='#0f172a'" onmouseout="this.style.color='#94a3b8'" title="Rename / Edit Document Title">
                                            <i data-lucide="edit-2" style="width:11px; height:11px;"></i>
                                        </button>
                                    </div>
                                    ${ownerBadge}
                                </div>
                                <div style="font-size:11px; color:#64748b; line-height:1.3;">${item.hint || item.category}</div>
                            </div>
                            <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid #f1f5f9; padding-top:8px; margin-top:2px;">
                                <div>${statusBadge}</div>
                                <div>${actionBtn}</div>
                            </div>
                        </div>
                    `;
                });

                accordionHtml += `</div></div>`;
            }

            grid.innerHTML = accordionHtml;
        } else if (grid) {
            grid.innerHTML = '<div style="color:var(--text-muted); font-size:12px; padding:12px;">No checklist items found for this selection.</div>';
        }

        if (progressText) {
            progressText.innerText = `${mandatoryUploadedCount} of ${mandatoryTotalCount} Mandatory Docs Uploaded`;
        }
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    async function loadDocuments() {
        try {
            const appRes = await fetch(`api.php?api=applicant_full_details&id=${applicantId}`);
            const appData = await appRes.json();
            
            if (appData && !appData.error) {
                window.currentDocAppData = appData;
                // Update Phase 3 Proceed button
                const btn = document.getElementById('proceed-btn') || document.getElementById('phase3-proceed-btn');
                const sub = document.getElementById('proceed-btn-sub');
                const compBadge = document.getElementById('doc-completion-badge');
                if (compBadge) {
                    compBadge.innerText = `${appData.phase2_completion}% Complete`;
                    compBadge.className = `badge ${appData.phase2_completion === 100 ? 'badge-success' : 'badge-warning'}`;
                }

                if (btn) {
                    if (appData.phase2_completion === 100) {
                        btn.style.pointerEvents = 'auto';
                        btn.style.opacity = '1';
                        btn.classList.remove('btn-secondary');
                        btn.classList.add('btn-primary');
                        if (sub) sub.innerText = "All mandatory documents verified";
                    } else {
                        btn.style.pointerEvents = 'none';
                        btn.style.opacity = '0.6';
                        btn.classList.remove('btn-primary');
                        btn.classList.add('btn-secondary');
                        if (sub) sub.innerText = `Upload mandatory docs to unlock (${appData.phase2_completion}%)`;
                    }
                }

                // Render Owner select dropdown
                updateOwnerDropdown(appData.co_applicants, appData.customer_name);

                // Render Smart Checklist Matrix Cards with active filter
                renderChecklistGrid(appData);

                // Update AI Document Intelligence Banner
                updateAiDocBanner(appData);

                // Render Uploaded Documents Table
                const docs = appData.documents || [];
                const tbody = document.getElementById('docs-list');
                const countSpan = document.getElementById('uploaded-count');
                if (countSpan) countSpan.innerText = docs.length;

                if (tbody) {
                    if (docs && docs.length > 0) {
                        tbody.innerHTML = '';
                        docs.forEach(doc => {
                            const uploadDate = new Date(doc.uploaded_at);
                            const dateStr = uploadDate.toLocaleDateString();
                            const ageDays = Math.floor((new Date() - uploadDate) / (1000 * 60 * 60 * 24));

                            let statusBadge = '';
                            if (doc.qa_status === 'Approved' || doc.status === 'Verified') {
                                statusBadge = '<span class="badge" style="background:#dcfce7; color:#166534; border:1px solid #bbf7d0; font-size:11px; padding:3px 8px; font-weight:700;"><i data-lucide="check-circle" style="width:12px; vertical-align:-1px;"></i> Approved</span>';
                            } else if (doc.qa_status === 'Rejected' || doc.reupload_requested == 1 || doc.status === 'Rejected') {
                                const rejReason = doc.rejection_reason || doc.notes || 'Re-upload requested';
                                statusBadge = `<span class="badge" style="background:#fee2e2; color:#991b1b; border:1px solid #fecaca; font-size:11px; padding:3px 8px; font-weight:700;" title="Rejection Reason: ${rejReason}"><i data-lucide="x-circle" style="width:12px; vertical-align:-1px;"></i> Re-upload Requested</span>`;
                            } else {
                                statusBadge = '<span class="badge" style="background:#e0f2fe; color:#0369a1; border:1px solid #bae6fd; font-size:11px; padding:3px 8px; font-weight:700;"><i data-lucide="clock" style="width:12px; vertical-align:-1px;"></i> Under Review</span>';
                            }

                            let expiryBadge = '';
                            if (ageDays > 60 && ['Income Proof', 'Property / Asset Docs', 'Basic KYC'].includes(doc.document_category)) {
                                expiryBadge = `<span class="badge" style="background:#fff7ed; color:#c2410c; border:1px solid #ffedd5; font-size:10px; font-weight:700; margin-left:4px;" title="Uploaded over 60 days ago. Bank may require fresh copy."><i data-lucide="alert-triangle" style="width:10px; vertical-align:-1px;"></i> Expired (${ageDays}d old)</span>`;
                            }
                            
                            const notesHtml = (doc.rejection_reason || doc.notes) ? `<div style="font-size:11px; color:#b91c1c; margin-top:2px;"><strong>Remark:</strong> ${doc.rejection_reason || doc.notes}</div>` : '';
                            const ownerLabel = doc.owner_type === 'CoApplicant' && doc.coapp_name 
                                ? `<span style="color:var(--text-primary); font-weight:600;"><i data-lucide="users" style="width:14px;height:14px;vertical-align:middle;margin-right:4px;"></i> Co-App: ${doc.coapp_name}</span>` 
                                : `<span style="color:var(--primary); font-weight:600;"><i data-lucide="user" style="width:14px;height:14px;vertical-align:middle;margin-right:4px;"></i> Primary Applicant</span>`;
                            
                            tbody.innerHTML += `
                                <tr>
                                    <td>${ownerLabel}</td>
                                    <td><span class="badge badge-info">${doc.document_category}</span></td>
                                    <td>
                                        <strong>${doc.document_name}</strong>
                                        ${doc.file_size ? `<span style="font-size:10.5px; color:#64748b; margin-left:6px;">(${Math.round(doc.file_size/1024)} KB)</span>` : ''}
                                        ${expiryBadge}
                                        ${notesHtml}
                                    </td>
                                    <td>${statusBadge}</td>
                                    <td style="color:var(--text-muted); font-size:12px;">${dateStr}</td>
                                    <td style="text-align:right; white-space:nowrap;">
                                        <button onclick="openPreviewModal('${doc.file_path}', '${encodeURIComponent(doc.document_name)}', '${doc.file_ext || ''}')" class="btn btn-secondary" style="padding: 4px 7px; font-size: 11px;" title="View Preview"><i data-lucide="eye" style="width:13px; height:13px;"></i></button>
                                        <a href="vault_stream.php?id=${doc.id}&download=1" download class="btn btn-secondary" style="padding: 4px 7px; font-size: 11px; margin-left:3px; text-decoration:none; display:inline-flex; align-items:center;" title="Direct Download"><i data-lucide="download" style="width:13px; height:13px;"></i></a>
                                        <button onclick="approveDocument(${doc.id}, '${encodeURIComponent(doc.document_name)}')" class="btn" style="padding: 4px 8px; font-size: 11px; background:#16a34a; color:#fff; border:none; margin-left:3px; font-weight:700; cursor:pointer;" title="Approve & Lock Document"><i data-lucide="check" style="width:12px; vertical-align:-1px;"></i> Accept</button>
                                        <button onclick="requestReuploadDocument(${doc.id}, '${encodeURIComponent(doc.document_name)}')" class="btn" style="padding: 4px 8px; font-size: 11px; background:#dc2626; color:#fff; border:none; margin-left:3px; font-weight:700; cursor:pointer;" title="Reject & Request Re-upload from Client"><i data-lucide="refresh-cw" style="width:12px; vertical-align:-1px;"></i> Reject</button>
                                        <button onclick="deleteDoc(${doc.id})" class="btn btn-danger" style="padding: 4px 7px; font-size: 11px; margin-left: 3px;" title="Delete"><i data-lucide="trash" style="width:13px; height:13px;"></i></button>
                                    </td>
                                </tr>
                            `;
                        });
                    } else {
                        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; color:var(--text-muted); padding:24px;">No documents uploaded yet. Choose from the recommended checklist above to upload.</td></tr>';
                    }
                }

                if (typeof lucide !== 'undefined') lucide.createIcons();
            }
        } catch (e) {
            console.error(e);
        }
    }

    window.pendingCaptureData = null;

    function prefillUpload(category, docName, ownerType, ownerId) {
        window.pendingCaptureData = { category, docName, ownerType, ownerId };
        
        const modal = document.getElementById('doc-capture-modal');
        const titleSpan = document.getElementById('capture-modal-title');
        const subSpan = document.getElementById('capture-modal-subtitle');
        
        if (titleSpan) titleSpan.innerText = `Upload: ${docName}`;
        if (subSpan) subSpan.innerText = `Category: ${category} | Owner: ${ownerType === 'CoApplicant' ? 'Co-Applicant' : 'Primary Applicant'}`;
        
        if (modal) modal.style.display = 'flex';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function closeDocCaptureModal() {
        const modal = document.getElementById('doc-capture-modal');
        if (modal) modal.style.display = 'none';
    }

    function chooseCaptureMethod(method) {
        closeDocCaptureModal();
        if (!window.pendingCaptureData) return;
        
        const { category, docName, ownerType, ownerId } = window.pendingCaptureData;
        
        const catSelect = document.getElementById('doc_category');
        const nameInput = document.getElementById('doc_name_input');
        const ownerSelect = document.getElementById('owner_val');
        const fileInput = document.getElementById('file-input');

        if (catSelect) catSelect.value = category;
        if (nameInput) nameInput.value = docName;
        if (ownerSelect) {
            if (ownerType === 'CoApplicant' && ownerId > 0) {
                ownerSelect.value = `CoApplicant_${ownerId}`;
            } else {
                ownerSelect.value = 'Applicant_0';
            }
        }

        const titleSpan = document.getElementById('upload-box-title');
        if (titleSpan) titleSpan.innerText = `Upload: ${docName}`;

        if (method === 'explorer') {
            window.autoSubmitOnPick = true;
            if (fileInput) fileInput.click();
        } else if (method === 'scanner') {
            window.autoSubmitOnPick = false;
            const formWrapper = document.getElementById('upload-form-wrapper');
            if (formWrapper) {
                formWrapper.scrollIntoView({ behavior: 'smooth', block: 'center' });
                formWrapper.style.boxShadow = '0 0 0 3px rgba(255,122,0,0.3)';
                setTimeout(() => { formWrapper.style.boxShadow = 'none'; }, 2000);
            }
            startScanner();
        }
    }

    async function attachFromVault(sourceDocId) {
        if (!confirm("Attach this document from Client's Permanent Vault to this loan application?")) return;
        const formData = new FormData();
        formData.append('applicant_id', applicantId);
        formData.append('source_doc_id', sourceDocId);

        try {
            const res = await fetch('api.php?api=attach_vault_document', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data && data.success) {
                showNotification(data.message, 'success');
                loadDocuments();
            } else {
                showNotification(data.error || 'Failed to attach from vault', 'error');
            }
        } catch (e) {
            showNotification('Connection error while attaching from vault', 'error');
        }
    }

    function openPreviewModal(filePath, docName, docExt = '') {
        const modal = document.getElementById('doc-preview-modal');
        const title = document.getElementById('preview-modal-title');
        const body = document.getElementById('preview-modal-body');
        const downloadBtn = document.getElementById('preview-download-btn');

        if (!modal || !body) return;

        const cleanName = decodeURIComponent(docName || 'Document Preview');
        title.innerText = cleanName;
        downloadBtn.href = filePath.includes('?') ? filePath + '&download=1' : filePath;
        
        let ext = (docExt || '').toLowerCase();
        if (!ext && filePath.includes('.')) {
            ext = filePath.split('.').pop().split('?')[0].toLowerCase();
        }
        if (!ext || ext === 'php') {
            if (cleanName.toLowerCase().includes('pdf') || cleanName.toLowerCase().includes('docket') || cleanName.toLowerCase().includes('itr') || cleanName.toLowerCase().includes('statement')) {
                ext = 'pdf';
            } else {
                ext = 'jpg';
            }
        }
        if (ext === 'pdf') {
            body.innerHTML = `
                <object data="${filePath}#toolbar=1" type="application/pdf" style="width:100%; height:100%; border:none; border-radius:8px;">
                    <embed src="${filePath}#toolbar=1" type="application/pdf" style="width:100%; height:100%; border:none; border-radius:8px;" />
                    <div style="padding:40px; text-align:center; color:#475569;">
                        <i data-lucide="file-text" style="width:48px; height:48px; margin-bottom:12px; color:#0284c7;"></i><br>
                        <h4 style="margin:0 0 8px 0; font-size:16px;">${cleanName}</h4>
                        <p style="font-size:13px; color:#64748b; margin-bottom:16px;">PDF Preview attached. If browser preview is disabled, click below:</p>
                        <a href="${filePath}" target="_blank" class="btn btn-primary" style="padding:8px 16px; font-weight:700;"><i data-lucide="external-link"></i> Open PDF in New Tab</a>
                    </div>
                </object>`;
        } else if (['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'].includes(ext)) {
            body.innerHTML = `<img src="${filePath}" style="max-width:100%; max-height:100%; object-fit:contain; border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,0.1);">`;
        } else {
            body.innerHTML = `
                <div style="padding:40px; text-align:center; color:#475569;">
                    <i data-lucide="file-text" style="width:48px; height:48px; margin-bottom:12px; color:#0284c7;"></i><br>
                    <h4 style="margin:0 0 8px 0; font-size:16px;">${cleanName}</h4>
                    <p style="font-size:13px; color:#64748b; margin-bottom:16px;">Document format: ${ext.toUpperCase()}</p>
                    <a href="${filePath}" target="_blank" download class="btn btn-primary" style="padding:8px 16px; font-weight:700;"><i data-lucide="download"></i> Download File</a>
                </div>`;
        }

        modal.style.display = 'flex';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function closePreviewModal() {
        const modal = document.getElementById('doc-preview-modal');
        if (modal) modal.style.display = 'none';
    }

    function openCardFileListModal(encodedDocsJson, docName, category, ownerType, ownerId) {
        let docs = [];
        try {
            docs = JSON.parse(decodeURIComponent(encodedDocsJson));
        } catch(e) {
            console.error(e);
        }

        const modal = document.getElementById('card-files-modal');
        const titleSpan = document.getElementById('card-files-modal-title');
        const body = document.getElementById('card-files-modal-body');
        const addMoreBtn = document.getElementById('card-files-add-more-btn');

        if (titleSpan) titleSpan.innerText = `Uploaded Files for ${docName} (${docs.length})`;
        
        if (addMoreBtn) {
            addMoreBtn.onclick = function() {
                closeCardFilesModal();
                prefillUpload(category, docName, ownerType, ownerId);
            };
        }

        if (body && docs && docs.length > 0) {
            let html = '';
            docs.forEach((d, idx) => {
                const date = new Date(d.uploaded_at).toLocaleDateString();
                const ext = (d.file_ext || d.file_path.split('.').pop()).toUpperCase();
                const sz = d.file_size ? `${Math.round(d.file_size/1024)} KB` : '';
                html += `
                    <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:8px; padding:12px; display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <div style="font-weight:700; font-size:13px; color:#0f172a;">${d.document_name}</div>
                            <div style="font-size:11px; color:#64748b; margin-top:2px;">Format: ${ext} ${sz ? ' | ' + sz : ''} | Uploaded: ${date}</div>
                        </div>
                        <div style="display:flex; gap:6px;">
                            <button type="button" onclick="openPreviewModal('${d.file_path}', '${encodeURIComponent(d.document_name)}')" class="btn btn-secondary" style="padding:4px 8px; font-size:11px; display:inline-flex; align-items:center; gap:4px;"><i data-lucide="eye" style="width:12px;"></i> View</button>
                            <button type="button" onclick="deleteDoc(${d.id})" class="btn btn-danger" style="padding:4px 8px; font-size:11px;" title="Delete file"><i data-lucide="trash" style="width:12px;"></i></button>
                        </div>
                    </div>
                `;
            });
            body.innerHTML = html;
        }

        if (modal) modal.style.display = 'flex';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function closeCardFilesModal() {
        const modal = document.getElementById('card-files-modal');
        if (modal) modal.style.display = 'none';
    }

    // ===== EXPANDED CATEGORIES & QUICK SUGGESTIONS =====
    const docCategorySuggestions = {
        'Basic KYC': ['PAN Card Front', 'PAN Card Back', 'Aadhaar Card Front', 'Aadhaar Card Back', 'Passport Size Photo', 'Current Address / Electricity Bill', 'Voter ID Card', 'Driving License'],
        'Income Proof': ['Last 3 Months Salary Slips', 'Last 6 Months Salary Bank Statement', 'Form 16 Part A & B', 'ITR FY 2024-25', 'ITR FY 2023-24', 'Computation of Income', 'Audited Balance Sheet & P&L'],
        'Business Proof': ['GST Registration Certificate', 'Shop Act / Trade License', 'MSME / Udyam Certificate', 'Partnership Deed / MOA / AOA', 'Business Bank Statement 12 Months', 'Appointment / Offer Letter'],
        'Property / Asset Docs': ['Property Sale Deed / Registry', 'Chain Agreements', 'Approved Building Map', 'Property Tax Paid Receipt', 'Latest Utility Bill', 'Builder Allotment Letter / NOC'],
        'Vehicle Docs': ['RC Copy', 'Vehicle Quotation / Performa Invoice', 'Vehicle Insurance Policy'],
        'Existing Loan Obligations': ['Sanction Letters of Existing Loans', 'Loan SOA (Statement of Account)', 'No Dues Certificate (NOC)'],
        'Co-Applicant Docs': ['Co-Applicant PAN Card', 'Co-Applicant Aadhaar Card', 'Co-Applicant Salary Slips', 'Co-Applicant Bank Statement', 'Relationship Proof'],
        'Bank Login & Fee Receipts': ['Bank Login Fee Receipt', 'Processing Fee Cheque Copy', 'Property Valuation Report', 'Legal Search Report', 'CIBIL Credit Report'],
        'Other': ['CAM Memo', 'Project Report / CMA Data', 'Internal Verification Report', 'Custom Document']
    };

    function updateDocSuggestions(cat) {
        const container = document.getElementById('quick-doc-chips');
        if (!container) return;
        
        const list = docCategorySuggestions[cat] || [];
        container.innerHTML = list.map(item => `
            <span onclick="selectDocSuggestion('${item.replace(/'/g, "\\'")}')" style="background:#f1f5f9; border:1px solid #cbd5e1; color:#334155; border-radius:12px; padding:3px 10px; font-size:11px; font-weight:600; cursor:pointer; transition:all 0.15s;" onmouseover="this.style.background='#0f172a';this.style.color='#fff';" onmouseout="this.style.background='#f1f5f9';this.style.color='#334155';">+ ${item}</span>
        `).join('');
    }

    function selectDocSuggestion(name) {
        const input = document.getElementById('doc_name_input');
        if (input) input.value = name;
    }

    // ===== 1-CLICK PHOTO LIGHTBOX GALLERY (CAROUSEL) =====
    let galleryDocsList = [];
    let currentGalleryIdx = 0;
    let currentGalleryRotation = 0;

    function openPhotoGalleryModal() {
        const appData = window.currentDocAppData;
        if (!appData || !appData.documents || appData.documents.length === 0) {
            showNotification('No documents uploaded yet to view in gallery.', 'warning');
            return;
        }

        galleryDocsList = appData.documents;
        currentGalleryIdx = 0;
        currentGalleryRotation = 0;

        const modal = document.getElementById('photo-gallery-modal');
        if (!modal) return;
        modal.style.display = 'flex';
        renderGalleryStage();
        renderGalleryThumbs();

        document.removeEventListener('keydown', handleGalleryKeydown);
        document.addEventListener('keydown', handleGalleryKeydown);
    }

    function closePhotoGalleryModal() {
        const modal = document.getElementById('photo-gallery-modal');
        if (modal) modal.style.display = 'none';
        document.removeEventListener('keydown', handleGalleryKeydown);
    }

    function handleGalleryKeydown(e) {
        const modal = document.getElementById('photo-gallery-modal');
        if (!modal || modal.style.display === 'none') return;
        if (e.key === 'ArrowLeft') prevGalleryImage();
        if (e.key === 'ArrowRight') nextGalleryImage();
        if (e.key === 'Escape') closePhotoGalleryModal();
    }

    function renderGalleryStage() {
        if (!galleryDocsList.length) return;
        const doc = galleryDocsList[currentGalleryIdx];
        currentGalleryRotation = 0;

        document.getElementById('gallery-doc-title').innerText = `${doc.document_name} (${doc.document_category})`;
        document.getElementById('gallery-counter-label').innerText = `Document ${currentGalleryIdx + 1} of ${galleryDocsList.length} • Owner: ${doc.coapp_name || 'Primary Applicant'}`;
        document.getElementById('gallery-download-btn').href = doc.file_path;

        const stage = document.getElementById('gallery-stage-body');
        const ext = (doc.file_path || '').split('.').pop().toLowerCase();

        if (ext === 'pdf') {
            stage.innerHTML = `<iframe src="${doc.file_path}" style="width:90%; height:90%; border:none; border-radius:10px; background:#fff; box-shadow:0 10px 30px rgba(0,0,0,0.5);"></iframe>`;
        } else {
            stage.innerHTML = `<img id="gallery-img-element" src="${doc.file_path}" style="max-width:90%; max-height:85vh; object-fit:contain; border-radius:8px; box-shadow:0 10px 30px rgba(0,0,0,0.5); transition:transform 0.2s ease;">`;
        }

        document.querySelectorAll('.g-thumb').forEach((t, i) => {
            t.style.border = (i === currentGalleryIdx) ? '3px solid #38bdf8' : '2px solid transparent';
            t.style.opacity = (i === currentGalleryIdx) ? '1' : '0.6';
        });
    }

    function renderGalleryThumbs() {
        const strip = document.getElementById('gallery-thumb-strip');
        if (!strip) return;

        strip.innerHTML = galleryDocsList.map((doc, idx) => {
            const ext = (doc.file_path || '').split('.').pop().toLowerCase();
            const content = ext === 'pdf'
                ? `<div style="width:60px; height:50px; background:#1e293b; color:#38bdf8; display:flex; flex-direction:column; justify-content:center; align-items:center; border-radius:6px; font-size:10px; font-weight:700;"><i data-lucide="file-text" style="width:16px;"></i> PDF</div>`
                : `<img src="${doc.file_path}" style="width:60px; height:50px; object-fit:cover; border-radius:6px;">`;

            return `<div class="g-thumb" onclick="jumpToGalleryIdx(${idx})" style="cursor:pointer; flex-shrink:0; border-radius:8px; transition:all 0.15s;" title="${doc.document_name}">
                ${content}
            </div>`;
        }).join('');

        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function jumpToGalleryIdx(idx) {
        currentGalleryIdx = idx;
        renderGalleryStage();
    }

    function nextGalleryImage() {
        if (!galleryDocsList.length) return;
        currentGalleryIdx = (currentGalleryIdx + 1) % galleryDocsList.length;
        renderGalleryStage();
    }

    function prevGalleryImage() {
        if (!galleryDocsList.length) return;
        currentGalleryIdx = (currentGalleryIdx - 1 + galleryDocsList.length) % galleryDocsList.length;
        renderGalleryStage();
    }

    function rotateGalleryImage() {
        const img = document.getElementById('gallery-img-element');
        if (!img) return;
        currentGalleryRotation = (currentGalleryRotation + 90) % 360;
        img.style.transform = `rotate(${currentGalleryRotation}deg)`;
    }

    // ===== 1-CLICK COMBINED MASTER PDF PREVIEW & GENERATION =====
    async function generateAndPreviewMasterPdf() {
        const appData = window.currentDocAppData;
        if (!appData || !appData.documents || appData.documents.length === 0) {
            showNotification('Please upload documents first before previewing Master PDF.', 'warning');
            return;
        }

        const modal = document.getElementById('master-pdf-modal');
        const body = document.getElementById('master-pdf-preview-body');
        if (!modal || !body) return;

        modal.style.display = 'flex';
        body.innerHTML = `
            <div style="color:#fff; text-align:center; margin-top:100px;">
                <div style="font-size:18px; font-weight:700; margin-bottom:10px;">Generating Single Master Bank Dossier PDF...</div>
                <div style="font-size:13px; color:#94a3b8;">Merging applicant metadata, document index table, and photos...</div>
            </div>`;

        const docs = appData.documents;
        const dateStr = new Date().toLocaleDateString('en-IN', {day:'2-digit', month:'short', year:'numeric'});

        let html = `
        <div id="master-pdf-render-area" style="width:800px; background:#ffffff; color:#0f172a; font-family:'Inter',sans-serif; padding:32px; box-sizing:border-box; border-radius:8px; box-shadow:0 10px 30px rgba(0,0,0,0.3);">
            <!-- Header Letterhead -->
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:3px solid #0f172a; padding-bottom:14px; margin-bottom:20px;">
                <div>
                    <div style="font-size:24px; font-weight:900; color:#0f172a; font-family:'Outfit',sans-serif; letter-spacing:-0.5px;">BFS FINANCIAL SERVICES</div>
                    <div style="font-size:12px; color:#64748b; font-weight:600;">CONFIDENTIAL BANK DOSSIER &amp; CREDIT ASSESSMENT FILE</div>
                </div>
                <div style="text-align:right; font-size:12px; color:#475569;">
                    <div><strong>Date:</strong> ${dateStr}</div>
                    <div><strong>Loan Ref ID:</strong> ${appData.loan_id || 'L-DOSSIER'}</div>
                </div>
            </div>

            <!-- Applicant Summary Card -->
            <div style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:10px; padding:16px; margin-bottom:24px;">
                <div style="font-size:18px; font-weight:800; color:#0f172a; margin-bottom:8px;">${appData.customer_name || 'Applicant'}</div>
                <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px; font-size:12px; color:#334155;">
                    <div><strong>Loan Type:</strong> ${appData.loan_type || '—'}</div>
                    <div><strong>Sub-Type:</strong> ${appData.loan_sub_type || 'General'}</div>
                    <div><strong>Requested Amount:</strong> ₹${new Intl.NumberFormat('en-IN').format(appData.loan_amount_requested || 0)}</div>
                    <div><strong>Mobile:</strong> ${appData.mobile || '—'}</div>
                    <div><strong>Employment:</strong> ${appData.employment_type || '—'}</div>
                    <div><strong>Assigned RM:</strong> ${appData.assigned_to || 'System'}</div>
                </div>
            </div>

            <!-- Master Document Index Table -->
            <div style="margin-bottom:28px;">
                <div style="font-size:14px; font-weight:800; color:#0f172a; margin-bottom:10px; text-transform:uppercase; letter-spacing:0.5px;">📁 Master Document Index (${docs.length} Items Attached)</div>
                <table style="width:100%; border-collapse:collapse; font-size:12px;">
                    <thead>
                        <tr style="background:#0f172a; color:#ffffff;">
                            <th style="padding:8px 10px; text-align:left;">#</th>
                            <th style="padding:8px 10px; text-align:left;">Category</th>
                            <th style="padding:8px 10px; text-align:left;">Document Name</th>
                            <th style="padding:8px 10px; text-align:left;">Owner</th>
                            <th style="padding:8px 10px; text-align:left;">Upload Date</th>
                            <th style="padding:8px 10px; text-align:right;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${docs.map((d, i) => `
                            <tr style="border-bottom:1px solid #e2e8f0; background:${i % 2 === 0 ? '#ffffff' : '#f8fafc'};">
                                <td style="padding:7px 10px;">${i + 1}</td>
                                <td style="padding:7px 10px; font-weight:600; color:#0284c7;">${d.document_category}</td>
                                <td style="padding:7px 10px; font-weight:700;">${d.document_name}</td>
                                <td style="padding:7px 10px;">${d.coapp_name || 'Primary Applicant'}</td>
                                <td style="padding:7px 10px; color:#64748b;">${new Date(d.uploaded_at).toLocaleDateString()}</td>
                                <td style="padding:7px 10px; text-align:right; font-weight:700; color:${d.status === 'Verified' ? '#16a34a' : '#d97706'};">${d.status}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>

            <!-- Document Attachments Pages -->
            <div style="margin-top:20px;">
                ${docs.map((d, i) => {
                    const ext = (d.file_path || '').split('.').pop().toLowerCase();
                    const mediaContent = (ext === 'pdf')
                        ? `<div style="background:#f1f5f9; padding:30px; text-align:center; border-radius:8px; border:1px solid #cbd5e1;"><i data-lucide="file-text" style="width:32px;"></i><br>PDF Document Attached: <strong>${d.document_name}</strong><br><a href="${d.file_path}" target="_blank" style="color:#0284c7; font-weight:600;">Open PDF File</a></div>`
                        : `<div style="text-align:center;"><img src="${d.file_path}" style="max-width:100%; max-height:800px; object-fit:contain; border:1px solid #e2e8f0; border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,0.06);"></div>`;

                    return `
                    <div style="page-break-before:always; padding-top:20px; border-top:2px dashed #cbd5e1; margin-top:24px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; background:#f8fafc; border:1px solid #e2e8f0; padding:10px 14px; border-radius:8px; margin-bottom:14px;">
                            <div>
                                <span style="font-weight:800; font-size:13px; color:#0f172a;">Doc #${i+1}: ${d.document_name}</span>
                                <span style="font-size:11px; color:#64748b; margin-left:8px;">(${d.document_category})</span>
                            </div>
                            <span style="font-size:11px; font-weight:700; color:#10b981;">Owner: ${d.coapp_name || 'Primary Applicant'}</span>
                        </div>
                        ${mediaContent}
                    </div>`;
                }).join('')}
            </div>
        </div>`;

        body.innerHTML = html;
        window.masterPdfRenderElement = document.getElementById('master-pdf-render-area');
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function closeMasterPdfModal() {
        const modal = document.getElementById('master-pdf-modal');
        if (modal) modal.style.display = 'none';
    }

    function downloadMasterPdfFile() {
        const element = window.masterPdfRenderElement || document.getElementById('master-pdf-render-area');
        if (!element) return;

        const opt = {
            margin: [0.3, 0.3, 0.3, 0.3],
            filename: `BFS_Master_Bank_Dossier_${applicantId}.pdf`,
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2, useCORS: true },
            jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
        };

        html2pdf().set(opt).from(element).save();
    }

    function printMasterPdfFrame() {
        const element = window.masterPdfRenderElement || document.getElementById('master-pdf-render-area');
        if (!element) return;
        const win = window.open('', '_blank');
        win.document.write(`<html><head><title>Print Master PDF Dossier</title><style>body{font-family:sans-serif;}</style></head><body>${element.outerHTML}</body></html>`);
        win.document.close();
        win.print();
    }

    let scannedImagesArray = [];

    function startScanner() {
        if(typeof scanner === 'undefined') {
            showNotification("Scanner.js is loading. Please wait.", "error");
            return;
        }
        scanner.scan(displayImagesOnPage, {
            "output_settings": [ { "type": "return-base64", "format": "jpg" } ]
        });
    }

    function displayImagesOnPage(successful, mesg, response) {
        if(!successful) {
            console.error('Failed: ' + mesg);
            if(mesg.toLowerCase().indexOf('user cancel') === -1) showNotification('Scanner error: ' + mesg, 'error');
            return;
        }
        const scannedImages = scanner.getScannedImages(response, true, false);
        if(scannedImages.length > 0) {
            scannedImages.forEach(img => {
                scannedImagesArray.push(img.src);
            });
            
            renderScannedPreviews();
            
            document.getElementById('scan-status').innerText = scannedImagesArray.length + ' page(s) scanned.';
            document.getElementById('file-name-display').innerText = ''; 
            document.getElementById('file-input').value = ''; 
            document.getElementById('file-input').removeAttribute('required');
            showNotification("Scan captured successfully", "success");
        }
    }

    function renderScannedPreviews() {
        const container = document.getElementById('scanned-preview-container');
        container.innerHTML = '';
        scannedImagesArray.forEach((imgSrc, index) => {
            container.innerHTML += `
                <div style="position:relative; width:120px; height:160px; border:1px solid var(--border); border-radius:4px; overflow:hidden; background:#f8fafc; margin-right:10px; margin-bottom:10px; display:inline-block; box-shadow:0 2px 5px rgba(0,0,0,0.1);">
                    <img src="${imgSrc}" style="width:100%; height:100%; object-fit:contain; background:#000;">
                    <div style="position:absolute; top:4px; left:4px; background:rgba(0,0,0,0.7); color:#fff; font-size:11px; padding:2px 6px; border-radius:12px; font-weight:bold;">Page ${index + 1}</div>
                    <button type="button" onclick="removeScannedImage(${index})" style="position:absolute; top:4px; right:4px; border:none; background:var(--danger); color:white; width:22px; height:22px; border-radius:50%; font-size:12px; font-weight:bold; cursor:pointer; display:flex; align-items:center; justify-content:center; padding:0;" title="Remove this page">×</button>
                    <button type="button" onclick="removeScannedImage(${index})" style="position:absolute; bottom:0; width:100%; border:none; background:var(--danger); color:white; padding:6px; font-size:11px; font-weight:bold; cursor:pointer; opacity:0.9;">️ Delete Scan</button>
                </div>
            `;
        });
        
        if (scannedImagesArray.length === 0) {
            document.getElementById('scan-status').innerText = '';
            document.getElementById('file-input').setAttribute('required', 'required');
        } else {
            document.getElementById('scan-status').innerText = scannedImagesArray.length + ' page(s) scanned.';
        }
    }

    function removeScannedImage(index) {
        if(confirm("Remove this scanned page?")) {
            scannedImagesArray.splice(index, 1);
            renderScannedPreviews();
        }
    }

    function updateFileName(input) {
        const display = document.getElementById('file-name-display');
        if (input.files && input.files.length > 0) {
            if (input.files.length === 1) {
                display.innerText = 'Selected File: ' + input.files[0].name;
            } else {
                const names = Array.from(input.files).map(f => f.name).join(', ');
                display.innerText = `Selected ${input.files.length} Files: ${names}`;
            }
            document.getElementById('scan-status').innerText = '';
            scannedImagesArray = [];
            renderScannedPreviews();

            // Auto-upload if triggered by card "Upload This" button
            if (window.autoSubmitOnPick) {
                window.autoSubmitOnPick = false;
                const form = document.getElementById('doc-upload-form');
                if (form) {
                    form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
                }
            }
        } else {
            if (display) display.innerText = '';
        }
    }

    async function uploadDocument(e) {
        e.preventDefault();
        const form = document.getElementById('doc-upload-form');
        const btn = document.getElementById('upload-btn');
        const fileInput = document.getElementById('file-input');
        const formData = new FormData(form);
        
        btn.disabled = true;
        btn.innerHTML = 'Processing...';

        if (scannedImagesArray.length > 0) {
            try {
                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF('p', 'mm', 'a4');
                const pageWidth = pdf.internal.pageSize.getWidth();
                const pageHeight = pdf.internal.pageSize.getHeight();

                for (let i = 0; i < scannedImagesArray.length; i++) {
                    if (i > 0) pdf.addPage();
                    pdf.addImage(scannedImagesArray[i], 'JPEG', 0, 0, pageWidth, pageHeight);
                }
                
                const pdfBlob = pdf.output('blob');
                const pdfFile = new File([pdfBlob], "scanned_document_" + Date.now() + ".pdf", { type: "application/pdf" });
                formData.append('document_file[]', pdfFile);
            } catch (err) {
                console.error(err);
                showNotification('Error generating PDF from scanner', 'error');
                btn.disabled = false;
                btn.innerHTML = '<i data-lucide="upload"></i> Upload & Save to Permanent Vault';
                if (typeof lucide !== 'undefined') lucide.createIcons();
                return;
            }
        }

        const hasFiles = fileInput && fileInput.files && fileInput.files.length > 0;
        const hasScanned = scannedImagesArray.length > 0;

        if (!hasFiles && !hasScanned) {
            showNotification('Please upload a file or scan a document', 'error');
            btn.disabled = false;
            btn.innerHTML = '<i data-lucide="upload"></i> Upload & Save to Permanent Vault';
            if (typeof lucide !== 'undefined') lucide.createIcons();
            return;
        }
        
        btn.innerHTML = 'Uploading...';
        
        try {
            const res = await fetch('api.php?api=upload_applicant_document', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (res.ok && data.success) {
                showNotification(data.message, 'success');
                form.reset();
                window.autoSubmitOnPick = false;
                scannedImagesArray = [];
                renderScannedPreviews();
                if (fileInput) fileInput.value = '';
                document.getElementById('file-name-display').innerText = '';
                loadDocuments();
            } else {
                showNotification(data.error || 'Upload failed', 'error');
            }
        } catch (err) {
            showNotification('Connection error during upload', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i data-lucide="upload"></i> Upload & Save to Permanent Vault';
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
    }

    async function deleteDoc(id) {
        if(!confirm("Are you sure you want to delete this document?")) return;
        
        const formData = new FormData();
        formData.append('id', id);
        
        try {
            const res = await fetch('api.php?api=delete_applicant_document', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if(data.success) {
                showNotification("Document deleted", "success");
                loadDocuments();
            }
        } catch (e) {
            showNotification('Connection error', 'error');
        }
    }

    async function updateDocStatus(id) {
        const status = prompt("Enter new status (Pending, Verified, Rejected):", "Verified");
        if (!status) return;
        
        let notes = "";
        if (status === "Rejected") {
            notes = prompt("Enter reason for rejection:");
        }
        
        const formData = new FormData();
        formData.append('id', id);
        formData.append('status', status);
        formData.append('notes', notes);
        
        try {
            const res = await fetch('api.php?api=update_document_status', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if(data.success) {
                showNotification("Status updated", "success");
                loadDocuments();
            }
        } catch (e) {
            showNotification('Connection error', 'error');
        }
    }

    async function approveDocument(docId, docNameEncoded) {
        const docName = decodeURIComponent(docNameEncoded || 'Document');
        if (!confirm(`Are you sure you want to approve "${docName}"? This will mark it as Verified & Approved, and keep it locked on the client portal.`)) return;

        const formData = new FormData();
        formData.append('doc_id', docId);
        formData.append('status', 'Approved');

        try {
            const res = await fetch('api.php?api=update_doc_qa_status', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data && data.success) {
                showNotification(data.message || "Document Approved & Verified!", "success");
                loadDocuments();
            } else {
                showNotification(data.error || "Failed to approve document", "error");
            }
        } catch (e) {
            showNotification('Connection error while approving document', 'error');
        }
    }

    async function requestReuploadDocument(docId, docNameEncoded) {
        const docName = decodeURIComponent(docNameEncoded || 'Document');
        const reason = prompt(`Specify rejection reason / instruction for requesting re-upload of "${docName}" (e.g. Photo is blurry, wrong document uploaded):`);
        if (reason === null) return;
        if (!reason.trim()) {
            showNotification('Re-upload reason is required so the client knows what to fix.', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('doc_id', docId);
        formData.append('status', 'Rejected');
        formData.append('reason', reason.trim());

        try {
            const res = await fetch('api.php?api=update_doc_qa_status', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data && data.success) {
                showNotification(data.message || "Re-upload requested! Client portal unlocked.", "success");
                loadDocuments();
            } else {
                showNotification(data.error || "Failed to request re-upload", "error");
            }
        } catch (e) {
            showNotification('Connection error while requesting re-upload', 'error');
        }
    }

    // --- EMAIL PENDENCY MODAL & DISPATCH ---
    function openEmailPendencyModal() {
        const data = window.currentDocAppData || {};
        const checklist = data.phase2_checklist || [];
        
        let receivedList = [];
        let pendingList = [];
        
        checklist.forEach(item => {
            const ownerTag = item.owner_type === 'CoApplicant' ? ' (Co-App)' : '';
            if (item.uploaded) {
                receivedList.push(item.doc_name + ownerTag);
            } else {
                pendingList.push(item.doc_name + ownerTag + (item.is_mandatory ? ' *' : ''));
            }
        });

        const summaryEl = document.getElementById('pendency_modal_summary');
        if (summaryEl) {
            summaryEl.innerHTML = `
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
                    <div style="background:#f0fdf4; border:1px solid #bbf7d0; padding:10px; border-radius:8px; max-height:160px; overflow-y:auto;">
                        <div style="font-weight:700; color:#166534; font-size:12px; margin-bottom:6px;"><i data-lucide="check-circle" style="width:13px; vertical-align:-2px;"></i> Received & Verified (${receivedList.length})</div>
                        <ul style="margin:0; padding-left:16px; font-size:11.5px; color:#15803d; line-height:1.4;">
                            ${receivedList.length > 0 ? receivedList.map(r => `<li>${r}</li>`).join('') : '<li style="color:#64748b;">No documents uploaded yet</li>'}
                        </ul>
                    </div>
                    <div style="background:#fef2f2; border:1px solid #fecaca; padding:10px; border-radius:8px; max-height:160px; overflow-y:auto;">
                        <div style="font-weight:700; color:#991b1b; font-size:12px; margin-bottom:6px;"><i data-lucide="alert-circle" style="width:13px; vertical-align:-2px;"></i> Missing / Pending (${pendingList.length})</div>
                        <ul style="margin:0; padding-left:16px; font-size:11.5px; color:#b91c1c; line-height:1.4;">
                            ${pendingList.length > 0 ? pendingList.map(p => `<li>${p}</li>`).join('') : '<li style="color:#16a34a;">100% complete! No pending documents</li>'}
                        </ul>
                    </div>
                </div>
            `;
        }

        document.getElementById('email-pendency-modal').style.display = 'flex';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function closeEmailPendencyModal() {
        document.getElementById('email-pendency-modal').style.display = 'none';
    }

    async function submitEmailPendency(e) {
        e.preventDefault();
        const note = document.getElementById('pendency_custom_note').value;
        const btn = document.getElementById('pendency_send_btn');
        btn.disabled = true;
        btn.innerHTML = 'Dispatching Email...';

        try {
            const formData = new FormData();
            formData.append('applicant_id', applicantId);
            formData.append('stage_type', 'Pendency');
            formData.append('custom_note', note);

            const res = await fetch('api.php?api=send_customer_stage_email', {
                method: 'POST',
                body: formData
            });
            const json = await res.json();
            if (json && json.success) {
                showNotification(json.message, 'success');
                closeEmailPendencyModal();
            } else {
                showNotification(json.error || 'Failed to send email', 'error');
            }
        } catch(err) {
            showNotification('Network error while dispatching email', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i data-lucide="send" style="width:14px;"></i> Dispatch Pendency Email';
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
    }

    // ===== AI DOCUMENT INTELLIGENCE & WHATSAPP AUTOMATION ENGINE =====
    function updateAiDocBanner(appData) {
        const completenessEl = document.getElementById('ai-completeness-text');
        const readinessEl = document.getElementById('ai-bank-readiness-text');
        const waTrackerEl = document.getElementById('ai-wa-tracker-text');

        if (!completenessEl || !readinessEl || !waTrackerEl) return;

        const checklist = appData.phase2_checklist || [];
        const missingMandatory = checklist.filter(item => item.is_mandatory && !item.uploaded);
        const mandatoryCount = checklist.filter(item => item.is_mandatory).length;
        const uploadedMandatory = mandatoryCount - missingMandatory.length;

        const compPct = appData.phase2_completion !== undefined ? appData.phase2_completion : (mandatoryCount > 0 ? Math.round((uploadedMandatory / mandatoryCount) * 100) : 100);

        completenessEl.innerHTML = `${compPct}% Overall <span style="font-size:12px; font-weight:600; color:#a1a1aa;">(${missingMandatory.length} Mandatory Missing)</span>`;

        if (missingMandatory.length === 0) {
            readinessEl.innerHTML = `<span style="color:#ffffff; font-weight:700; display:inline-flex; align-items:center; gap:5px;"><i data-lucide="check-circle-2" style="width:14px; height:14px; color:#ffffff;"></i> Ready for Bank Sanction Submission</span>`;
            waTrackerEl.innerHTML = `<span style="color:#a1a1aa;">All Required Docs Received</span>`;
        } else {
            readinessEl.innerHTML = `<span style="color:#ffffff; font-weight:700; display:inline-flex; align-items:center; gap:5px;"><i data-lucide="alert-circle" style="width:14px; height:14px; color:#ef4444;"></i> Hold: ${missingMandatory.length} Mandatory Doc(s) Required</span>`;
            waTrackerEl.innerHTML = `<span style="color:#a1a1aa;">Cycle: 2-3 Days Auto-Reminder Active (${missingMandatory.length} Pending)</span>`;
        }

        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    async function triggerAiMissingDocsWhatsApp(appId) {
        const appData = window.currentDocAppData;
        if (!appData) {
            showNotification('Loading applicant data...', 'info');
            return;
        }

        const checklist = appData.phase2_checklist || [];
        const missingMandatory = checklist.filter(item => item.is_mandatory && !item.uploaded);
        const customerName = appData.customer_name || 'Applicant';
        const mobile = (appData.mobile || '').replace(/[^0-9]/g, '');

        if (!mobile) {
            showNotification('Applicant mobile number is missing!', 'error');
            return;
        }

        let docListText = '';
        if (missingMandatory.length > 0) {
            docListText = missingMandatory.map((m, idx) => `${idx + 1}. ${m.doc_name} (${m.category || 'KYC/Income'})`).join('\n');
        } else {
            docListText = '1. Additional verification documents if requested by bank';
        }

        const msg = `Dear ${customerName},\n\nGreetings from BFS Circle!\n\nYour ${appData.loan_type || 'Loan'} application (${appData.loan_id || ''}) is currently under credit evaluation. To proceed with bank sanction submission, kindly share the following pending document(s):\n\n${docListText}\n\nPlease reply or upload directly via your client link.\n\nThank you,\nBFS Financial Services`;

        try {
            const formData = new FormData();
            formData.append('applicant_id', appId);
            formData.append('message', msg);
            formData.append('reminder_type', '2_day_missing_docs_reminder');

            const res = await fetch('api.php?api=send_whatsapp_api', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            const waUrl = `https://wa.me/91${mobile}?text=${encodeURIComponent(msg)}`;
            window.open(waUrl, '_blank');

            if (data && data.success) {
                showNotification('Missing Docs WhatsApp Follow-up logged & opened!', 'success');
            } else {
                showNotification('WhatsApp window opened (Logged locally)', 'success');
            }
        } catch (e) {
            const waUrl = `https://wa.me/91${mobile}?text=${encodeURIComponent(msg)}`;
            window.open(waUrl, '_blank');
            showNotification('WhatsApp link opened successfully', 'success');
        }
    }

    function runAiDocAudit(appId) {
        showNotification('Running AI Document Audit & Completeness Check...', 'info');
        loadDocuments();
        setTimeout(() => {
            showNotification('AI Audit complete! Document readiness matrix updated.', 'success');
        }, 600);
    }

    async function generateAiEmailContent(stageType = 'Pendency', targetAreaId = 'pendency_custom_note') {
        const targetEl = document.getElementById(targetAreaId);
        if (!targetEl) return;

        const btn = event ? event.currentTarget : null;
        const oldHtml = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = `<i data-lucide="sparkles" style="width:12px; height:12px;" class="spin"></i> AI Writing Email...`;
        }

        showNotification('✨ AI is writing your professional email...', 'info');

        try {
            const formData = new FormData();
            formData.append('applicant_id', applicantId);
            formData.append('stage_type', stageType);

            const res = await fetch('api.php?api=generate_ai_email', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (data && data.success) {
                targetEl.value = data.body || data.content || '';
                showNotification('✨ AI Email drafted! Review and click Dispatch.', 'success');
            } else {
                showNotification(data.error || 'Failed to generate AI email.', 'error');
            }
        } catch (e) {
            showNotification('Connection error while generating AI email.', 'error');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = oldHtml;
                if (typeof lucide !== 'undefined') lucide.createIcons();
            }
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        loadDocuments();
    });
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdn.asprise.com/scannerjs/scanner.js"></script>
<script>
    // ===== BFS 10X VAULT & BANK DOCKET SCRIPTS =====
    async function openWindowsVaultFolder(appId) {
        try {
            const res = await fetch(`open_vault_folder.php?id=${appId}&ajax=1`);
            const data = await res.json();
            if (data.success) {
                if (typeof showCustomAlert === 'function') {
                    showCustomAlert("📂 Vault Opened", "Physical folder revealed in Windows Explorer:\n" + data.path);
                } else {
                    alert("📂 Folder opened in Windows Explorer:\n" + data.path);
                }
            } else {
                alert(data.error || "Failed to open folder");
            }
        } catch (e) {
            console.error(e);
            window.open(`open_vault_folder.php?id=${appId}`, '_blank');
        }
    }

    function openBankDocketModal() {
        const modal = document.getElementById('bank-docket-modal');
        const preview = document.getElementById('docket-docs-preview');
        const countSpan = document.getElementById('docket-docs-count');
        const resBox = document.getElementById('docket-result-box');
        if (resBox) resBox.style.display = 'none';

        const docs = (window.currentDocAppData && window.currentDocAppData.documents) ? window.currentDocAppData.documents : [];
        if (countSpan) countSpan.innerText = docs.length;

        if (preview) {
            if (docs.length === 0) {
                preview.innerHTML = '<div style="color:#71717a; text-align:center; padding:10px;">No documents uploaded yet. Upload KYC or Income proof first.</div>';
            } else {
                preview.innerHTML = docs.map((d, i) => `
                    <div style="display:flex; justify-content:space-between; align-items:center; background:#18181b; padding:6px 10px; border-radius:5px; border:1px solid #27272a;">
                        <span style="font-weight:600; color:#e4e4e7;"><i data-lucide="file" style="width:12px; height:12px; vertical-align:-1px; margin-right:4px;"></i> ${d.document_name}</span>
                        <span style="color:#a1a1aa; font-size:10.5px;">${d.document_category}</span>
                    </div>
                `).join('');
            }
        }

        if (modal) modal.style.display = 'flex';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function closeBankDocketModal() {
        const modal = document.getElementById('bank-docket-modal');
        if (modal) modal.style.display = 'none';
    }

    async function executeCompileDocket() {
        const btn = document.getElementById('btn-compile-docket');
        const bank = document.getElementById('docket-bank-select').value;
        const wm = document.getElementById('docket-watermark-toggle').checked ? 1 : 0;
        const resBox = document.getElementById('docket-result-box');

        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i data-lucide="loader" class="animate-spin" style="width:16px; height:16px;"></i> Compiling with PyMuPDF Engine on F:\BFS...';
        if (typeof lucide !== 'undefined') lucide.createIcons();

        try {
            const fd = new FormData();
            fd.append('id', applicantId);
            fd.append('bank_name', bank);
            fd.append('watermark', wm);

            const res = await fetch('compile_bank_docket.php', { method: 'POST', body: fd });
            const data = await res.json();

            if (data.success) {
                if (resBox) {
                    resBox.style.display = 'block';
                    document.getElementById('docket-result-pages').innerText = `${data.total_pages} Pages`;
                    document.getElementById('docket-result-path').innerText = data.file_path;
                    document.getElementById('docket-preview-link').href = data.stream_url;
                    document.getElementById('docket-download-link').href = data.download_url;
                }
                loadDocuments();
            } else {
                alert(data.error || 'Failed to compile docket');
            }
        } catch (e) {
            console.error(e);
            alert('Server error occurred during PDF compilation.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
    }


    // ===== CHECKLIST CUSTOMIZATION & RENAMING SCRIPTS =====
    function openEditDocNameModal(docNameEnc, hintEnc, catEnc, isMandatory, origNameEnc) {
        let docName = docNameEnc, hint = hintEnc || '', cat = catEnc || '', origName = origNameEnc || docNameEnc;
        try { docName = decodeURIComponent(docNameEnc); } catch(e){}
        try { hint = decodeURIComponent(hintEnc || ''); } catch(e){}
        try { cat = decodeURIComponent(catEnc || ''); } catch(e){}
        try { origName = decodeURIComponent(origNameEnc || docName); } catch(e){}

        document.getElementById('edit-orig-doc-name').value = origName;
        document.getElementById('edit-doc-category').value = cat;
        document.getElementById('edit-orig-name-display').innerText = origName;
        document.getElementById('edit-new-doc-name').value = docName;
        document.getElementById('edit-doc-hint').value = hint;
        document.getElementById('edit-is-mandatory').checked = (isMandatory == 1);

        const modal = document.getElementById('edit-doc-name-modal');
        if (modal) modal.style.display = 'flex';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function closeEditDocNameModal() {
        const modal = document.getElementById('edit-doc-name-modal');
        if (modal) modal.style.display = 'none';
    }

    async function submitRenameDocName(e) {
        e.preventDefault();
        const origName = document.getElementById('edit-orig-doc-name').value;
        const cat = document.getElementById('edit-doc-category').value;
        const newName = document.getElementById('edit-new-doc-name').value.trim();
        const hint = document.getElementById('edit-doc-hint').value.trim();
        const isMand = document.getElementById('edit-is-mandatory').checked ? 1 : 0;

        if (!newName) return;

        try {
            const fd = new FormData();
            fd.append('applicant_id', applicantId);
            fd.append('original_name', origName);
            fd.append('new_name', newName);
            fd.append('category', cat);
            fd.append('hint', hint);
            fd.append('is_mandatory', isMand);
            fd.append('action', 'rename');

            const res = await fetch('api.php?api=edit_checklist_doc_name', { method: 'POST', body: fd });
            const data = await res.json();

            if (data.success) {
                closeEditDocNameModal();
                showNotification(data.message || 'Document name updated!', 'success');
                loadDocuments();
            } else {
                alert(data.error || 'Failed to update name');
            }
        } catch (err) {
            console.error(err);
            alert('Error updating document name');
        }
    }

    async function deleteChecklistItem() {
        const origName = document.getElementById('edit-orig-doc-name').value;
        const cat = document.getElementById('edit-doc-category').value;

        if (!confirm(`Are you sure you want to remove '${origName}' from this applicant's checklist?`)) return;

        try {
            const fd = new FormData();
            fd.append('applicant_id', applicantId);
            fd.append('original_name', origName);
            fd.append('new_name', origName);
            fd.append('category', cat);
            fd.append('action', 'delete');

            const res = await fetch('api.php?api=edit_checklist_doc_name', { method: 'POST', body: fd });
            const data = await res.json();

            if (data.success) {
                closeEditDocNameModal();
                showNotification(`Removed '${origName}' from checklist!`, 'info');
                loadDocuments();
            } else {
                alert(data.error || 'Failed to remove');
            }
        } catch (err) {
            console.error(err);
            alert('Error removing checklist item');
        }
    }

    function openAddCustomDocModal(category = '') {
        try { if (category) category = decodeURIComponent(category); } catch(e){}
        if (category) {
            const sel = document.getElementById('custom-doc-category');
            if (sel) {
                for (let opt of sel.options) {
                    if (opt.value.toLowerCase().includes(category.toLowerCase()) || category.toLowerCase().includes(opt.value.toLowerCase())) {
                        sel.value = opt.value;
                        break;
                    }
                }
            }
        }
        document.getElementById('custom-doc-title').value = '';
        document.getElementById('custom-doc-hint').value = '';
        document.getElementById('custom-doc-mandatory').checked = false;

        const modal = document.getElementById('add-custom-doc-modal');
        if (modal) modal.style.display = 'flex';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function closeAddCustomDocModal() {
        const modal = document.getElementById('add-custom-doc-modal');
        if (modal) modal.style.display = 'none';
    }

    async function submitAddCustomDoc(e) {
        e.preventDefault();
        const cat = document.getElementById('custom-doc-category').value;
        const title = document.getElementById('custom-doc-title').value.trim();
        const hint = document.getElementById('custom-doc-hint').value.trim();
        const isMand = document.getElementById('custom-doc-mandatory').checked ? 1 : 0;

        if (!title) return;

        try {
            const fd = new FormData();
            fd.append('applicant_id', applicantId);
            fd.append('category', cat);
            fd.append('doc_name', title);
            fd.append('hint', hint);
            fd.append('is_mandatory', isMand);

            const res = await fetch('api.php?api=add_custom_checklist_item', { method: 'POST', body: fd });
            const data = await res.json();

            if (data.success) {
                closeAddCustomDocModal();
                showNotification(`Added '${title}' to checklist!`, 'success');
                loadDocuments();
            } else {
                alert(data.error || 'Failed to add custom doc');
            }
        } catch (err) {
            console.error(err);
            alert('Error adding custom document');
        }
    }

</script>

<!-- Email Pendency Modal Dialog -->
<div id="email-pendency-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.8); z-index:9999; justify-content:center; align-items:center; backdrop-filter:blur(4px);">
    <div style="background:#fff; border-radius:12px; width:90%; max-width:620px; max-height:92vh; overflow-y:auto; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);">
        <div style="padding:16px 20px; background:#0f172a; color:#fff; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #1e293b;">
            <div style="display:flex; align-items:center; gap:8px;">
                <i data-lucide="mail" style="width:18px; color:#38bdf8;"></i>
                <span style="font-weight:700; font-size:15px;">Email Document Status & Pendency to Customer</span>
            </div>
            <button onclick="closeEmailPendencyModal()" style="background:none; border:none; color:#fff; cursor:pointer; font-size:20px; line-height:1;">&times;</button>
        </div>
        <form onsubmit="submitEmailPendency(event)" style="padding:20px;">
            <div style="background:#f8fafc; border:1px solid #e2e8f0; padding:12px 14px; border-radius:8px; margin-bottom:16px; display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <div style="font-size:11px; color:#64748b; font-weight:700; text-transform:uppercase;">Customer</div>
                    <div style="font-size:14px; font-weight:700; color:#0f172a; margin-top:2px;"><?php echo htmlspecialchars($applicant['customer_name']); ?></div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:11px; color:#64748b; font-weight:700; text-transform:uppercase;">Registered Email</div>
                    <div style="font-size:13px; font-weight:600; color:#2563eb; margin-top:2px;"><?php echo htmlspecialchars($applicant['email'] ?: 'No email registered'); ?></div>
                </div>
            </div>

            <?php if (empty($applicant['email'])): ?>
            <div style="background:#fee2e2; border:1px solid #fca5a5; color:#991b1b; padding:10px 14px; border-radius:8px; font-size:12.5px; margin-bottom:16px;">
                <i data-lucide="alert-triangle" style="width:14px; vertical-align:-2px;"></i> <strong>Missing Email:</strong> Please add email in applicant KYC before dispatching email.
            </div>
            <?php endif; ?>

            <label style="font-size:12px; font-weight:700; color:#334155; margin-bottom:6px; display:block;">Live Checklist Snapshot</label>
            <div id="pendency_modal_summary">
                <!-- Summary of received and pending items inserted dynamically -->
            </div>

            <div class="form-group" style="margin-bottom:20px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                    <label style="font-size:12px; font-weight:700; color:#334155; margin:0;">Custom Message / Instructions to Customer <span style="font-weight:normal; color:#64748b;">(Optional)</span></label>
                    <button type="button" onclick="generateAiEmailContent('Pendency', 'pendency_custom_note')" style="background:linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color:#fbbf24; border:1px solid #f59e0b; border-radius:12px; padding:3px 10px; font-size:11px; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:4px; box-shadow:0 2px 6px rgba(0,0,0,0.15);" title="Auto-write email content using AI">
                        <i data-lucide="sparkles" style="width:12px; height:12px; color:#f59e0b;"></i> ✨ AI Write Email
                    </button>
                </div>
                <textarea id="pendency_custom_note" rows="3" placeholder="e.g. Please arrange password-protected PDF bank statements and clear photo of Aadhaar back by tomorrow 4 PM." style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; box-sizing:border-box; line-height:1.4;"></textarea>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:center;">
                <span style="font-size:11.5px; color:#64748b;"><i data-lucide="shield-check" style="width:13px; vertical-align:-2px;"></i> Auto-formats as official BFS branded email</span>
                <div style="display:flex; gap:10px;">
                    <button type="button" onclick="closeEmailPendencyModal()" class="btn btn-secondary" style="padding:8px 16px;">Cancel</button>
                    <button type="submit" id="pendency_send_btn" <?php echo empty($applicant['email']) ? 'disabled' : ''; ?> class="btn btn-primary" style="padding:8px 20px; font-weight:700; background:#0f172a; border:none;"><i data-lucide="send" style="width:14px;"></i> Dispatch Pendency Email</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- 🔗 CLIENT SELF-UPLOAD PORTAL LINK MODAL -->
<div id="client-upload-link-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.85); z-index:99999; justify-content:center; align-items:center; backdrop-filter:blur(4px);">
    <div style="background:#ffffff; border-radius:12px; width:90%; max-width:540px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.3); overflow:hidden; border:1px solid #cbd5e1;">
        <div style="padding:16px 20px; background:#0f172a; color:#ffffff; display:flex; justify-content:space-between; align-items:center;">
            <div style="display:flex; align-items:center; gap:8px;">
                <i data-lucide="link-2" style="width:18px; color:#38bdf8;"></i>
                <span style="font-weight:800; font-size:15px;">Client Self-Upload Portal Link</span>
            </div>
            <button type="button" onclick="closeClientSelfUploadModal()" style="background:none; border:none; color:#ffffff; cursor:pointer; font-size:24px; line-height:1;">&times;</button>
        </div>
        <div style="padding:20px; background:#ffffff;">
            <p style="font-size:13px; color:#475569; margin-bottom:14px; line-height:1.5;">Share this secure link with <strong style="color:#0f172a;"><?php echo htmlspecialchars($applicant['customer_name']); ?></strong> to let them upload KYC &amp; income documents directly from their mobile device camera.</p>
            
            <label style="font-size:11.5px; font-weight:700; color:#334155; display:block; margin-bottom:6px;">Unique Upload Link</label>
            <div style="display:flex; gap:8px; margin-bottom:18px;">
                <input type="text" id="client-portal-link-input" readonly style="flex:1; padding:10px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px; font-weight:600; color:#0f172a; background:#f8fafc;" value="">
                <button type="button" onclick="copyClientPortalLink()" class="btn" style="background:#0f172a; color:#ffffff; border:none; padding:10px 16px; border-radius:8px; font-weight:700; font-size:12.5px; display:inline-flex; align-items:center; gap:6px; cursor:pointer;">
                    <i data-lucide="copy" style="width:14px;"></i> Copy
                </button>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                <button type="button" onclick="sendClientPortalWhatsApp()" class="btn" style="background:#16a34a; color:#ffffff; border:none; padding:12px; border-radius:8px; font-weight:700; font-size:13px; display:flex; align-items:center; justify-content:center; gap:6px; cursor:pointer;">
                    <i data-lucide="message-circle" style="width:16px;"></i> Send via WhatsApp
                </button>
                <a id="client-portal-open-btn" href="#" target="_blank" class="btn" style="background:#ffffff; color:#0f172a; border:1px solid #cbd5e1; padding:12px; border-radius:8px; font-weight:700; font-size:13px; display:flex; align-items:center; justify-content:center; gap:6px; text-decoration:none;">
                    <i data-lucide="external-link" style="width:16px;"></i> Test Open Link
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
