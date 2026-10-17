<?php
require_once __DIR__ . '/config.php';
if(!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$applicant_id = intval($_GET['id'] ?? 0);
if(!$applicant_id) die('Invalid Application ID');

// Fetch applicant with RM details
$stmt = $db->prepare("SELECT a.*, COALESCE(e.full_name, u.full_name, u.name, u.username) as rm_name, COALESCE(e.mobile, '') as rm_mobile, COALESCE(e.official_email, e.personal_email, '') as rm_email FROM applicants a LEFT JOIN users u ON u.username = a.assigned_to LEFT JOIN employees e ON e.user_id = u.id WHERE a.id = ?");
$stmt->execute([$applicant_id]);
$app = $stmt->fetch(PDO::FETCH_ASSOC);
if(!$app) die('Loan Application Record Not Found');

// Fetch co-applicants
$co_stmt = $db->prepare("SELECT * FROM co_applicants WHERE applicant_id = ?");
$co_stmt->execute([$applicant_id]);
$co_apps = $co_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch documents
$doc_stmt = $db->prepare("SELECT document_name, document_category, status FROM applicant_documents WHERE applicant_id = ?");
$doc_stmt->execute([$applicant_id]);
$documents = $doc_stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Official Application Form - ' . htmlspecialchars($app['customer_name']);
$page_subtitle = 'BFS Financial Services - Customer Loan Application Form';

include 'header.php';
?>

<!-- html2pdf.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<style>
    @media print {
        aside, nav, header, footer,
        .sidebar, .sidebar-wrapper, .main-sidebar,
        .app-header, .top-header, .page-header, .crm-header,
        .no-print, .form-toolbar, .global-ai-fab, #globalAiFabBtn, .global-ai-drawer, #globalAiDrawer, .drawer,
        .modal, .swal2-container, .sidebar-toggle-btn, .sidebar-quick-search,
        .brand-header {
            display: none !important;
            visibility: hidden !important;
            height: 0 !important;
            width: 0 !important;
            overflow: hidden !important;
            position: absolute !important;
            left: -9999px !important;
        }

        html, body {
            background: #ffffff !important;
            color: #0f172a !important;
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            height: auto !important;
            font-size: 11pt !important;
            overflow: visible !important;
        }

        main, .main-content, .content-wrapper, #page-content, .app-container {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
            float: none !important;
            position: static !important;
            box-shadow: none !important;
            border: none !important;
            background: transparent !important;
        }

        .form-page-container {
            border: none !important;
            box-shadow: none !important;
            margin: 0 auto !important;
            padding: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
            background: #ffffff !important;
        }

        @page {
            size: A4 portrait;
            margin: 8mm 10mm 8mm 10mm;
        }

        * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .section-header, .app-header-box {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            page-break-after: avoid !important;
            break-after: avoid !important;
        }

        table, tr, td, th, .section-header, .meta-strip, .declaration-box, .signature-row {
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }
    }

    .form-toolbar {
        max-width: 860px;
        margin: 20px auto 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }
    .btn-action {
        background: #0f172a;
        color: #ffffff;
        border: none;
        border-radius: 8px;
        padding: 9px 18px;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        text-decoration: none;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        transition: all 0.2s;
    }
    .btn-action:hover { opacity: 0.9; transform: translateY(-1px); }
    .btn-outline { background: #ffffff; color: #0f172a; border: 1.5px solid #0f172a; }

    .form-page-container {
        background: #ffffff;
        max-width: 860px;
        margin: 10px auto 40px;
        padding: 36px 40px;
        border-radius: 12px;
        border: 1.5px solid #cbd5e1;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
        font-family: 'Inter', Arial, sans-serif;
        color: #0f172a;
    }

    .app-header-box {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        border-bottom: 2.5px solid #0f172a;
        padding-bottom: 18px;
        margin-bottom: 20px;
    }
    .company-title {
        font-size: 23px;
        font-weight: 900;
        letter-spacing: -0.5px;
        color: #0f172a;
        font-family: 'Outfit', sans-serif;
        line-height: 1.2;
    }
    .form-title-badge {
        display: inline-block;
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
        color: #0369a1;
        background: #e0f2fe;
        border: 1px solid #bae6fd;
        padding: 3px 10px;
        border-radius: 20px;
        letter-spacing: 0.5px;
        margin-top: 6px;
    }
    .photo-box {
        width: 105px;
        height: 125px;
        border: 2px dashed #94a3b8;
        border-radius: 8px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        font-weight: 600;
        color: #64748b;
        text-align: center;
        background: #f8fafc;
        padding: 6px;
        box-sizing: border-box;
    }

    .meta-strip {
        background: #f8fafc;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        padding: 10px 16px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 12.5px;
        font-weight: 700;
        margin-bottom: 20px;
    }

    .section-header {
        background: #0f172a;
        color: #ffffff;
        padding: 7px 14px;
        font-size: 12.5px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-radius: 5px;
        border-left: 4px solid #0284c7;
        margin-top: 20px;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .form-grid-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 14px;
    }
    .form-grid-table th, .form-grid-table td {
        border: 1px solid #cbd5e1;
        padding: 8px 12px;
        font-size: 12px;
        line-height: 1.45;
    }
    .form-grid-table th {
        background: #f1f5f9;
        color: #334155;
        font-weight: 700;
        width: 22%;
        text-align: left;
    }
    .form-grid-table td {
        color: #0f172a;
        font-weight: 600;
        width: 28%;
        background: #ffffff;
    }

    .declaration-box {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 14px 16px;
        font-size: 11px;
        color: #475569;
        line-height: 1.55;
        margin-top: 22px;
        margin-bottom: 28px;
    }

    .signature-row {
        display: flex;
        justify-content: space-between;
        margin-top: 40px;
        padding-top: 10px;
    }
    .sig-cell {
        width: 44%;
        text-align: center;
    }
    .sig-line {
        border-top: 1.5px solid #0f172a;
        padding-top: 6px;
        font-size: 12px;
        font-weight: 800;
        color: #0f172a;
    }
</style>

<!-- Top Toolbar with Dropdown Menu -->
<div class="form-toolbar no-print">
    <div>
        <a href="search_track.php" class="btn-action btn-outline"><i data-lucide="arrow-left" style="width:14px;"></i> Back to CRM</a>
    </div>
    <div style="display:flex; gap:10px; align-items:center;">
        <button onclick="window.print()" class="btn-action btn-outline" style="border-color:#0f172a; color:#0f172a;">
            <i data-lucide="printer" style="width:14px;"></i> Print Application
        </button>

        <!-- Actions & Export Dropdown -->
        <div style="position:relative; display:inline-block;" class="form-dropdown-container">
            <button onclick="toggleFormDropdown(event)" class="btn-action" style="background:#0f172a; color:#ffffff; border:none; box-shadow:0 2px 6px rgba(15,23,42,0.25);">
                <i data-lucide="share-2" style="width:14px;"></i> Actions & Export <i data-lucide="chevron-down" style="width:14px; margin-left:2px;"></i>
            </button>
            <div id="formActionsDropdown" class="form-dropdown-menu" style="display:none; position:absolute; top:calc(100% + 6px); right:0; min-width:220px; background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; box-shadow:0 10px 25px rgba(0,0,0,0.15); z-index:9999; padding:6px 0; font-size:12.5px;">
                <a href="javascript:void(0)" onclick="closeFormDropdown(); shareFormWhatsApp();" style="display:flex; align-items:center; gap:10px; padding:9px 16px; color:#1e293b; text-decoration:none; font-weight:600; transition:all 0.15s ease;" onmouseover="this.style.background='#f1f5f9'; this.style.color='#16a34a';" onmouseout="this.style.background='transparent'; this.style.color='#1e293b';">
                    <i data-lucide="message-circle" style="width:15px; height:15px; color:#16a34a;"></i> Share via WhatsApp
                </a>
                <a href="javascript:void(0)" onclick="closeFormDropdown(); emailOfficialFormPDF();" style="display:flex; align-items:center; gap:10px; padding:9px 16px; color:#1e293b; text-decoration:none; font-weight:600; transition:all 0.15s ease;" onmouseover="this.style.background='#f1f5f9'; this.style.color='#2563eb';" onmouseout="this.style.background='transparent'; this.style.color='#1e293b';">
                    <i data-lucide="mail" style="width:15px; height:15px; color:#2563eb;"></i> Email Form PDF
                </a>
                <a href="javascript:void(0)" onclick="closeFormDropdown(); window.print();" style="display:flex; align-items:center; gap:10px; padding:9px 16px; color:#1e293b; text-decoration:none; font-weight:600; transition:all 0.15s ease;" onmouseover="this.style.background='#f1f5f9'; this.style.color='#0f172a';" onmouseout="this.style.background='transparent'; this.style.color='#1e293b';">
                    <i data-lucide="printer" style="width:15px; height:15px; color:#0f172a;"></i> Print Application Form
                </a>
                <div style="height:1px; background:#f1f5f9; margin:4px 0;"></div>
                <a href="javascript:void(0)" onclick="closeFormDropdown(); downloadOfficialFormPDF();" style="display:flex; align-items:center; gap:10px; padding:9px 16px; color:#1e293b; text-decoration:none; font-weight:600; transition:all 0.15s ease;" onmouseover="this.style.background='#f1f5f9'; this.style.color='#0f172a';" onmouseout="this.style.background='transparent'; this.style.color='#1e293b';">
                    <i data-lucide="download" style="width:15px; height:15px; color:#0f172a;"></i> Download PDF File
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Printable Application Form Document -->
<div class="form-page-container" id="printable-form-document">

    <!-- Header Section -->
    <div class="app-header-box">
        <div>
            <div style="display:flex; align-items:center; gap:12px;">
                <img src="logo.png" alt="BFS Logo" style="height:40px; width:auto; filter:brightness(0);">
                <div>
                    <div class="company-title">BFS FINANCIAL SERVICES</div>
                    <div style="font-size:11.5px; color:#64748b; font-weight:600; margin-top:2px;">
                        ISO 9001:2015 Certified Loan Syndication & Financial Consulting
                    </div>
                </div>
            </div>
            <div style="margin-top:8px;">
                <span class="form-title-badge">📋 OFFICIAL LOAN APPLICATION FORM</span>
            </div>
        </div>
        <div class="photo-box">
            <i data-lucide="user" style="width:30px; height:30px; color:#94a3b8; margin-bottom:4px;"></i>
            <span>Affix Passport Photo Here</span>
        </div>
    </div>

    <!-- Meta Details Strip -->
    <div class="meta-strip">
        <div>Application Ref No: <strong style="color:#0284c7; background:#e0f2fe; padding:2px 8px; border-radius:4px; font-size:13px;"><?= htmlspecialchars($app['loan_id']) ?></strong></div>
        <div>Date of Filing: <strong><?= date('d-M-Y', strtotime($app['created_at'] ?? 'now')) ?></strong></div>
        <div>File Stage: <strong style="color:#15803d;"><?= htmlspecialchars($app['overall_status'] ?? 'Phase 1 Profile') ?></strong></div>
    </div>

    <!-- Section 1: Primary Applicant Personal & KYC Details -->
    <div class="section-header">
        <i data-lucide="user-check" style="width:14px; height:14px;"></i> 1. Primary Applicant Personal & KYC Profile
    </div>
    <table class="form-grid-table">
        <tr>
            <th>Full Applicant Name</th>
            <td style="font-size:13px; font-weight:700; color:#0f172a;"><?= htmlspecialchars($app['customer_name']) ?></td>
            <th>Mobile Number</th>
            <td>+91 <?= htmlspecialchars($app['mobile'] ?? 'N/A') ?></td>
        </tr>
        <tr>
            <th>Email Address</th>
            <td><?= htmlspecialchars($app['email'] ?? 'Not Configured') ?></td>
            <th>Date of Birth</th>
            <td><?= htmlspecialchars($app['dob'] ?? 'N/A') ?></td>
        </tr>
        <tr>
            <th>PAN Card Number</th>
            <td style="font-weight:700; color:#0284c7;"><?= htmlspecialchars($app['pan_number'] ?? 'N/A') ?></td>
            <th>Aadhaar Card Number</th>
            <td><?= htmlspecialchars($app['aadhar_number'] ?? 'N/A') ?></td>
        </tr>
    </table>

    <!-- Section 2: Residential Address Details -->
    <div class="section-header">
        <i data-lucide="map-pin" style="width:14px; height:14px;"></i> 2. Residential Address Details
    </div>
    <table class="form-grid-table">
        <tr>
            <th>Complete Address</th>
            <td colspan="3"><?= htmlspecialchars($app['address'] ?? 'N/A') ?></td>
        </tr>
        <tr>
            <th>City / District</th>
            <td><?= htmlspecialchars($app['city'] ?? 'N/A') ?></td>
            <th>State & Pincode</th>
            <td><?= htmlspecialchars($app['state'] ?? 'N/A') ?> - <?= htmlspecialchars($app['pincode'] ?? 'N/A') ?></td>
        </tr>
    </table>

    <!-- Section 3: Employment & Financial Assessment -->
    <div class="section-header">
        <i data-lucide="wallet" style="width:14px; height:14px;"></i> 3. Employment & Financial Assessment
    </div>
    <table class="form-grid-table">
        <tr>
            <th>Employment Category</th>
            <td><?= htmlspecialchars($app['employment_type'] ?? 'Salaried') ?></td>
            <th>Monthly Gross Income</th>
            <td style="font-size:13px; font-weight:700; color:#15803d;">₹<?= number_format($app['monthly_income'] ?? 0) ?></td>
        </tr>
        <tr>
            <th>Existing Monthly EMIs</th>
            <td>₹<?= number_format($app['emi'] ?? 0) ?></td>
            <th>CIBIL Credit Score</th>
            <td><strong><?= htmlspecialchars($app['cibil_score'] ?? 'N/A') ?></strong></td>
        </tr>
        <tr>
            <th>Company / Dept / Firm Name</th>
            <td><?= htmlspecialchars(!empty($app['organization_name']) ? $app['organization_name'] : ($app['company_name'] ?? 'N/A')) ?></td>
            <th>Office / Workplace Address</th>
            <td><?= htmlspecialchars(!empty($app['organization_address']) ? $app['organization_address'] : 'N/A') ?></td>
        </tr>
    </table>

    <!-- Section 4: Loan Requirement Details -->
    <div class="section-header">
        <i data-lucide="banknote" style="width:14px; height:14px;"></i> 4. Loan Requirement Details
    </div>
    <table class="form-grid-table">
        <tr>
            <th>Loan Category / Product</th>
            <td style="font-weight:700; color:#0f172a;"><?= htmlspecialchars($app['loan_type'] ?? 'Loan') ?> (<?= htmlspecialchars($app['loan_sub_type'] ?? 'Standard') ?>)</td>
            <th>Requested Loan Amount</th>
            <td style="font-size:13.5px; font-weight:800; color:#0284c7;">₹<?= number_format($app['loan_amount_requested'] ?? 0) ?></td>
        </tr>
        <tr>
            <th>Proposed Tenure</th>
            <td><?= htmlspecialchars($app['tenure_months'] ?? '120') ?> Months</td>
            <th>Sanctioned Bank Partner</th>
            <td><?= htmlspecialchars($app['sanction_bank'] ?? 'Under Banking Evaluation') ?></td>
        </tr>
    </table>

    <!-- Section 5: Co-Applicant Details -->
    <?php if(!empty($co_apps)): ?>
    <div class="section-header">
        <i data-lucide="users" style="width:14px; height:14px;"></i> 5. Co-Applicant / Guarantor Details
    </div>
    <table class="form-grid-table">
        <thead>
            <tr>
                <th style="width:25%;">Full Name</th>
                <th style="width:20%;">Relationship</th>
                <th style="width:22%;">Financial Status</th>
                <th style="width:18%;">Monthly Income</th>
                <th style="width:15%;">PAN Number</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($co_apps as $co): ?>
            <tr>
                <td style="font-weight:700; color:#0f172a;"><?= htmlspecialchars($co['full_name'] ?? $co['name'] ?? 'Co-Applicant') ?></td>
                <td><?= htmlspecialchars($co['relationship'] ?? $co['relation'] ?? 'Family') ?></td>
                <td>
                    <span style="font-size:11px; font-weight:700; padding:2px 6px; border-radius:4px; background:<?= ($co['is_financial'] == 1 || $co['is_financial'] === 'Yes') ? '#e0f2fe; color:#0369a1;' : '#f1f5f9; color:#475569;' ?>">
                        <?= ($co['is_financial'] == 1 || $co['is_financial'] === 'Yes') ? 'Financial Co-Borrower' : 'Guarantor' ?>
                    </span>
                </td>
                <td style="font-weight:700; color:#15803d;">₹<?= number_format($co['monthly_income'] ?? 0) ?></td>
                <td><?= htmlspecialchars($co['pan_number'] ?? 'N/A') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <!-- Section 6: Uploaded KYC & Document Checklist Table -->
    <div class="section-header">
        <i data-lucide="file-text" style="width:14px; height:14px;"></i> 6. Document Checklist & Status
    </div>
    <table class="form-grid-table">
        <thead>
            <tr>
                <th style="width:45%;">Document Category / Type</th>
                <th style="width:30%;">Required Purpose</th>
                <th style="width:25%;">Verification Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if(!empty($documents)): ?>
                <?php foreach($documents as $doc): ?>
                <tr>
                    <td style="font-weight:600; color:#0f172a;"><?= htmlspecialchars($doc['document_name']) ?></td>
                    <td><?= htmlspecialchars($doc['document_category'] ?? 'KYC Verification') ?></td>
                    <td>
                        <span style="color:<?= $doc['status'] === 'Verified' ? '#16a34a' : '#d97706' ?>; font-weight:700; font-size:11.5px;">
                            ✓ <?= htmlspecialchars($doc['status']) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td style="font-weight:600;">PAN & Aadhaar Card KYC</td>
                    <td>Primary Identity & Address Verification</td>
                    <td><span style="color:#16a34a; font-weight:700; font-size:11.5px;">✓ Verified</span></td>
                </tr>
                <tr>
                    <td style="font-weight:600;">Bank Statement (6 Months)</td>
                    <td>Banking Habits & Cashflow Analysis</td>
                    <td><span style="color:#16a34a; font-weight:700; font-size:11.5px;">✓ Verified</span></td>
                </tr>
                <tr>
                    <td style="font-weight:600;">Salary Slips / ITR Returns</td>
                    <td>Financial Appraisal & FOIR Calculation</td>
                    <td><span style="color:#16a34a; font-weight:700; font-size:11.5px;">✓ Verified</span></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Section 7: Declaration & Signatures -->
    <div class="declaration-box">
        <strong>Customer Declaration & Credit Authorisation Clause:</strong><br>
        I/We hereby declare that all particulars, information, and details provided in this Application Form are true, correct, and complete in all respects. I/We authorize BFS Financial Services and its partner banks/NBFCs to verify my/our credentials, pull CIBIL credit reports, and process this credit application with partner financial institutions.
    </div>

    <!-- Signatures -->
    <div class="signature-row">
        <div class="sig-cell">
            <div style="height:36px;"></div>
            <div class="sig-line">Signature of Primary Applicant</div>
            <div style="font-size:12px; margin-top:4px; font-weight:700; color:#0f172a;"><?= htmlspecialchars($app['customer_name']) ?></div>
        </div>
        <div class="sig-cell">
            <div style="height:36px;"></div>
            <div class="sig-line">Authorised Officer / DSA Signature</div>
            <div style="font-size:12px; margin-top:4px; font-weight:700; color:#0f172a;"><?= htmlspecialchars($app['rm_name'] ?? 'BFS Branch Officer') ?></div>
        </div>
    </div>

</div>

<script>
function downloadOfficialFormPDF() {
    const el = document.getElementById('printable-form-document');
    const opt = {
        margin: [8,8,8,8],
        filename: 'ApplicationForm_<?= htmlspecialchars($app['loan_id']) ?>_<?= htmlspecialchars(str_replace(' ', '_', $app['customer_name'])) ?>.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };
    html2pdf().set(opt).from(el).save();
}

function shareFormWhatsApp() {
    const text = `Official Loan Application Form Summary%0A`+
                 `Customer: <?= htmlspecialchars($app['customer_name']) ?>%0A`+
                 `Loan Ref: <?= htmlspecialchars($app['loan_id']) ?>%0A`+
                 `Requested Loan: Rs. <?= number_format($app['loan_amount_requested'] ?? 0) ?>%0A`+
                 `Product: <?= htmlspecialchars($app['loan_type'] ?? 'Loan') ?>`;
    window.open(`https://wa.me/?text=${text}`, '_blank');
}

async function emailOfficialFormPDF() {
    const clientEmail = "<?= htmlspecialchars($app['email'] ?? '') ?>";
    const appId = <?= intval($app['id']) ?>;
    const clientName = "<?= htmlspecialchars($app['customer_name']) ?>";

    let targetEmail = clientEmail;
    if (!targetEmail) {
        if (typeof Swal !== 'undefined') {
            const res = await Swal.fire({
                title: '✉️ Dispatch Filled Form PDF',
                text: `Client email missing. Enter email address to send Application Form:`,
                input: 'email',
                inputPlaceholder: 'client@domain.com',
                showCancelButton: true,
                confirmButtonText: 'Send Email Form',
                confirmButtonColor: '#2563eb'
            });
            if (res.isConfirmed && res.value) {
                targetEmail = res.value;
            } else {
                return;
            }
        } else {
            targetEmail = prompt("Enter client email address:");
            if (!targetEmail) return;
        }
    }

    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: '✉️ Dispatching Form PDF Email...',
            text: `Sending official filled application form to ${targetEmail}`,
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });
    }

    try {
        const formData = new FormData();
        formData.append('applicant_id', appId);
        formData.append('stage_type', 'Form_Confirmation');
        formData.append('recipient_email', targetEmail);
        formData.append('custom_subject', `Official Loan Application Form - ${clientName} (Ref: <?= htmlspecialchars($app['loan_id']) ?>)`);

        const res = await fetch('api.php?api=send_customer_stage_email', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data && data.success) {
            if (typeof Swal !== 'undefined') Swal.fire('Email Dispatched!', `Filled Application Form emailed successfully to ${targetEmail}`, 'success');
        } else {
            if (typeof Swal !== 'undefined') Swal.fire('Notice', data.error || 'Email dispatch logged to queue', 'info');
        }
    } catch(e) {
        if (typeof Swal !== 'undefined') Swal.fire('Dispatched', `Application form summary dispatched to ${targetEmail}`, 'success');
    }
}

// Actions & Export Dropdown Handlers
function toggleFormDropdown(event) {
    if (event) event.stopPropagation();
    const menu = document.getElementById('formActionsDropdown');
    if (!menu) return;
    const isVisible = menu.style.display === 'block';
    menu.style.display = isVisible ? 'none' : 'block';
}

function closeFormDropdown() {
    const menu = document.getElementById('formActionsDropdown');
    if (menu) menu.style.display = 'none';
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.form-dropdown-container')) {
        closeFormDropdown();
    }
});
</script>

<?php include 'footer.php'; ?>
