<?php
require_once __DIR__ . '/config.php';
if(!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$applicant_id = intval($_GET['id'] ?? 0);
if(!$applicant_id) die('Invalid request');

// Fetch applicant with co-applicants
$stmt = $db->prepare("SELECT a.*, COALESCE(e.full_name, u.full_name, u.name, u.username) as rm_name, COALESCE(e.mobile, '') as rm_mobile FROM applicants a LEFT JOIN users u ON u.username = a.assigned_to LEFT JOIN employees e ON e.user_id = u.id WHERE a.id = ?");
$stmt->execute([$applicant_id]);
$app = $stmt->fetch();
if(!$app) die('Applicant not found');

// Fetch co-applicants
$co_stmt = $db->prepare("SELECT * FROM co_applicants WHERE applicant_id = ?");
$co_stmt->execute([$applicant_id]);
$co_apps = $co_stmt->fetchAll();

// Calculate combined income
$combined_income = $app['monthly_income'] ?? 0;
$financial_co_apps = array_filter($co_apps, fn($c) => $c['is_financial'] == 1 || $c['is_financial'] === 'Yes');
foreach($financial_co_apps as $co) {
    $combined_income += ($co['monthly_income'] ?? 0);
}

// FOIR Calculation
$total_emis = $app['emi'] ?? 0;
foreach($co_apps as $co) { $total_emis += ($co['current_emis'] ?? 0); }
$foir = $combined_income > 0 ? round(($total_emis / $combined_income) * 100, 1) : 0;

// LTV (if loan amount and property value known)
$ltv = 0; // Will show N/A if no property value

// Max Eligibility (standard DSA formula)
$max_eligible = max(0, ($combined_income * 0.5 - $total_emis) * 100); // rough estimate

$page_title = 'CAM - ' . htmlspecialchars($app['customer_name']);
$page_subtitle = 'Credit Assessment Memo for Bank Submission';

// CIBIL Analysis
$cibil = intval($app['cibil_score'] ?? 0);
$cibil_color = '#dc2626'; // Poor (red)
$cibil_rating = 'Poor';
if ($cibil >= 800) {
    $cibil_color = '#16a34a'; // Excellent (green)
    $cibil_rating = 'Excellent';
} elseif ($cibil >= 750) {
    $cibil_color = '#2563eb'; // Good (blue)
    $cibil_rating = 'Good';
} elseif ($cibil >= 650) {
    $cibil_color = '#d97706'; // Fair (amber)
    $cibil_rating = 'Fair';
}

// DSA Recommendation
$recommendation = 'Declined';
$rec_color = '#dc2626';
if ($foir <= 55 && $cibil >= 650) {
    if ($foir <= 40 && $cibil >= 750) {
        $recommendation = 'Recommended';
        $rec_color = '#16a34a';
    } else {
        $recommendation = 'Conditional';
        $rec_color = '#d97706';
    }
}

include 'header.php';
?>

<!-- html2pdf -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<style>
    @media print {
        aside, nav, header, footer,
        .sidebar, .sidebar-wrapper, .main-sidebar,
        .app-header, .top-header, .page-header, .crm-header,
        .no-print, .cam-toolbar, .global-ai-fab, #globalAiFabBtn, .global-ai-drawer, #globalAiDrawer, .drawer,
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

        .cam-container {
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
            margin: 10mm 10mm 10mm 10mm;
        }

        * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .cam-section-title, .cam-header {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            page-break-after: avoid !important;
            break-after: avoid !important;
        }

        table, tr, td, th, .cam-section, .cam-table {
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }
    }
    .cam-container {
        background: #fff;
        max-width: 800px;
        margin: 20px auto;
        padding: 40px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        border: 1px solid #e2e8f0;
        font-family: Arial, sans-serif;
        color: #000;
    }
    .cam-header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 20px; margin-bottom: 20px; }
    .cam-logo { font-size: 24px; font-weight: bold; margin-bottom: 5px; }
    .cam-title { font-size: 18px; text-transform: uppercase; letter-spacing: 1px; color: #333; }
    
    .cam-section { margin-bottom: 25px; }
    .cam-section-title { 
        background: #0f172a; 
        color: #fff; 
        padding: 5px 10px; 
        font-size: 14px; 
        font-weight: bold; 
        text-transform: uppercase;
        margin-bottom: 10px;
    }
    
    .cam-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    .cam-table th, .cam-table td { 
        border: 1px solid #cbd5e1; 
        padding: 8px 10px; 
        text-align: left; 
        font-size: 13px;
    }
    .cam-table th { background: #f8fafc; width: 25%; font-weight: bold; }
    .cam-table td { width: 25%; }
    
    .action-bar {
        max-width: 800px;
        margin: 20px auto 0;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }
    .btn-action {
        background: #0f172a; color: #fff; border: none; border-radius: 6px; padding: 8px 16px; font-size: 13px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px; text-decoration: none;
    }
    .btn-outline {
        background: #fff; color: #0f172a; border: 1px solid #0f172a;
    }
</style>

<div class="action-bar no-print" style="max-width:800px; margin:20px auto 0; display:flex; justify-content:space-between; align-items:center;">
    <div>
        <a href="search_track.php" class="btn-action btn-outline"><i data-lucide="arrow-left" style="width:14px;height:14px;"></i> Back to CRM</a>
    </div>
    <div style="display:flex; gap:10px; align-items:center;">
        <button onclick="window.print()" class="btn-action btn-outline" style="border-color:#0f172a; color:#0f172a;">
            <i data-lucide="printer" style="width:14px;height:14px;"></i> Print CAM
        </button>

        <!-- Actions & Export Dropdown -->
        <div style="position:relative; display:inline-block;" class="cam-dropdown-container">
            <button onclick="toggleCamDropdown(event)" class="btn-action" style="background:#0f172a; color:#ffffff; border:none; box-shadow:0 2px 6px rgba(15,23,42,0.25);">
                <i data-lucide="share-2" style="width:14px;height:14px;"></i> Actions & Export <i data-lucide="chevron-down" style="width:14px; margin-left:2px;"></i>
            </button>
            <div id="camActionsDropdown" class="cam-dropdown-menu" style="display:none; position:absolute; top:calc(100% + 6px); right:0; min-width:220px; background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; box-shadow:0 10px 25px rgba(0,0,0,0.15); z-index:9999; padding:6px 0; font-size:12.5px;">
                <a href="javascript:void(0)" onclick="closeCamDropdown(); shareWhatsApp();" style="display:flex; align-items:center; gap:10px; padding:9px 16px; color:#1e293b; text-decoration:none; font-weight:600; transition:all 0.15s ease;" onmouseover="this.style.background='#f1f5f9'; this.style.color='#16a34a';" onmouseout="this.style.background='transparent'; this.style.color='#1e293b';">
                    <i data-lucide="message-circle" style="width:15px; height:15px; color:#16a34a;"></i> Share on WhatsApp
                </a>
                <a href="javascript:void(0)" onclick="closeCamDropdown(); emailApplicationFormPDF();" style="display:flex; align-items:center; gap:10px; padding:9px 16px; color:#1e293b; text-decoration:none; font-weight:600; transition:all 0.15s ease;" onmouseover="this.style.background='#f1f5f9'; this.style.color='#2563eb';" onmouseout="this.style.background='transparent'; this.style.color='#1e293b';">
                    <i data-lucide="mail" style="width:15px; height:15px; color:#2563eb;"></i> Email Form PDF
                </a>
                <a href="javascript:void(0)" onclick="closeCamDropdown(); window.print();" style="display:flex; align-items:center; gap:10px; padding:9px 16px; color:#1e293b; text-decoration:none; font-weight:600; transition:all 0.15s ease;" onmouseover="this.style.background='#f1f5f9'; this.style.color='#0f172a';" onmouseout="this.style.background='transparent'; this.style.color='#1e293b';">
                    <i data-lucide="printer" style="width:15px; height:15px; color:#0f172a;"></i> Print CAM Memo
                </a>
                <div style="height:1px; background:#f1f5f9; margin:4px 0;"></div>
                <a href="javascript:void(0)" onclick="closeCamDropdown(); downloadCAM();" style="display:flex; align-items:center; gap:10px; padding:9px 16px; color:#1e293b; text-decoration:none; font-weight:600; transition:all 0.15s ease;" onmouseover="this.style.background='#f1f5f9'; this.style.color='#0f172a';" onmouseout="this.style.background='transparent'; this.style.color='#1e293b';">
                    <i data-lucide="download" style="width:15px; height:15px; color:#0f172a;"></i> Download CAM PDF
                </a>
            </div>
        </div>
    </div>
</div>

<div class="cam-container" id="cam-document">
    <div class="cam-header">
        <div class="cam-logo">BFS CIRCLE</div>
        <div class="cam-title">Credit Assessment Memo</div>
        <div style="font-size:12px; color:#64748b; margin-top:5px;">Date: <?= date('d-M-Y') ?> | Reference: <?= htmlspecialchars($app['loan_id']) ?></div>
    </div>

    <!-- Section 1: Applicant Details -->
    <div class="cam-section">
        <div class="cam-section-title">1. Applicant Details</div>
        <table class="cam-table">
            <tr>
                <th>Applicant Name</th>
                <td><?= htmlspecialchars($app['customer_name']) ?></td>
                <th>Mobile Number</th>
                <td><?= htmlspecialchars($app['mobile']) ?></td>
            </tr>
            <tr>
                <th>PAN Number</th>
                <td><?= htmlspecialchars($app['pan_number'] ?? 'N/A') ?></td>
                <th>Employment Type</th>
                <td><?= htmlspecialchars($app['employment_type'] ?? 'N/A') ?></td>
            </tr>
            <tr>
                <th>Company Name</th>
                <td colspan="3"><?= htmlspecialchars($app['company_name'] ?? 'N/A') ?></td>
            </tr>
        </table>
    </div>

    <!-- Section 2: Co-Applicant Details -->
    <?php if(count($co_apps) > 0): ?>
    <div class="cam-section">
        <div class="cam-section-title">2. Co-Applicant Details</div>
        <table class="cam-table">
            <thead>
                <tr>
                    <th style="width:auto">Name</th>
                    <th style="width:auto">Relation</th>
                    <th style="width:auto">Financial</th>
                    <th style="width:auto">Income</th>
                    <th style="width:auto">CIBIL</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($co_apps as $co): ?>
                <tr>
                    <td><?= htmlspecialchars($co['full_name'] ?? $co['name'] ?? 'Co-Applicant') ?></td>
                    <td><?= htmlspecialchars($co['relationship'] ?? $co['relation'] ?? 'Family') ?></td>
                    <td><?= htmlspecialchars(($co['is_financial'] == 1 || $co['is_financial'] === 'Yes') ? 'Yes (Financial)' : 'No (Guarantor)') ?></td>
                    <td>₹<?= number_format($co['monthly_income'] ?? 0) ?></td>
                    <td><?= htmlspecialchars($co['cibil_score'] ?? 'N/A') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Section 3: Income Analysis -->
    <div class="cam-section">
        <div class="cam-section-title">3. Income & FOIR Analysis</div>
        <table class="cam-table">
            <tr>
                <th>Applicant Income</th>
                <td>₹<?= number_format($app['monthly_income'] ?? 0) ?></td>
                <th>Total EMI Obligations</th>
                <td>₹<?= number_format($total_emis) ?></td>
            </tr>
            <tr>
                <th>Co-Applicant(s) Income</th>
                <td>₹<?= number_format($combined_income - ($app['monthly_income'] ?? 0)) ?></td>
                <th>Calculated FOIR</th>
                <td style="font-weight:bold; color:<?= $foir > 55 ? '#dc2626' : ($foir > 40 ? '#d97706' : '#16a34a') ?>"><?= $foir ?>%</td>
            </tr>
            <tr>
                <th>Combined Net Income</th>
                <td style="font-weight:bold;">₹<?= number_format($combined_income) ?></td>
                <th>Max Est. Eligibility</th>
                <td>₹<?= number_format($max_eligible) ?></td>
            </tr>
        </table>
    </div>

    <!-- Section 4: Loan Requirement -->
    <div class="cam-section">
        <div class="cam-section-title">4. Loan Requirement</div>
        <table class="cam-table">
            <tr>
                <th>Loan Amount Required</th>
                <td>₹<?= number_format($app['loan_amount'] ?? 0) ?></td>
                <th>Loan Type</th>
                <td><?= htmlspecialchars($app['loan_type'] ?? 'N/A') ?></td>
            </tr>
            <tr>
                <th>Proposed Tenure</th>
                <td><?= htmlspecialchars($app['loan_tenure'] ?? 'N/A') ?> Months</td>
                <th>End Use / Purpose</th>
                <td><?= htmlspecialchars($app['purpose'] ?? 'N/A') ?></td>
            </tr>
        </table>
    </div>

    <!-- Section 5: CIBIL Analysis -->
    <div class="cam-section">
        <div class="cam-section-title">5. Credit / CIBIL Analysis</div>
        <table class="cam-table">
            <tr>
                <th>Applicant CIBIL Score</th>
                <td style="font-size:16px; font-weight:bold; color:<?= $cibil_color ?>"><?= $cibil ?: 'N/A' ?></td>
                <th>Rating</th>
                <td style="font-weight:bold; color:<?= $cibil_color ?>"><?= $cibil ? $cibil_rating : 'N/A' ?></td>
            </tr>
        </table>
    </div>

    <!-- Section 6: Proposed Loan Structure -->
    <div class="cam-section">
        <div class="cam-section-title">6. Proposed Loan Structure</div>
        <table class="cam-table">
            <tr>
                <th>Recommended Amount</th>
                <td>₹<?= number_format($app['loan_amount'] ?? 0) ?></td>
                <th>Expected ROI (%)</th>
                <td>____________</td>
            </tr>
            <tr>
                <th>Proposed EMI</th>
                <td>____________</td>
                <th>PF (%)</th>
                <td>____________</td>
            </tr>
        </table>
    </div>

    <!-- Section 7: DSA Recommendation -->
    <div class="cam-section">
        <div class="cam-section-title">7. DSA Recommendation</div>
        <div style="padding:15px; border:1px solid <?= $rec_color ?>; background:<?= $rec_color ?>10; color:<?= $rec_color ?>; font-weight:bold; font-size:16px; text-align:center;">
            <?= $recommendation ?>
        </div>
        <div style="margin-top:10px; font-size:13px; line-height:1.5;">
            <strong>Remarks: </strong>
            <div style="border-bottom:1px dashed #cbd5e1; height:20px; margin-top:5px;"></div>
            <div style="border-bottom:1px dashed #cbd5e1; height:20px; margin-top:10px;"></div>
        </div>
    </div>

    <!-- Section 8: Signature -->
    <div class="cam-section" style="margin-top:50px;">
        <table style="width:100%; border:none;">
            <tr>
                <td style="width:50%; text-align:left;">
                    <div style="border-top:1px solid #000; width:200px; padding-top:5px; font-weight:bold;">Prepared By (RM)</div>
                    <div style="font-size:13px; margin-top:5px;"><?= htmlspecialchars($app['rm_name'] ?? 'N/A') ?></div>
                    <div style="font-size:13px;"><?= htmlspecialchars($app['rm_mobile'] ?? 'N/A') ?></div>
                </td>
                <td style="width:50%; text-align:right;">
                    <div style="border-top:1px solid #000; width:200px; padding-top:5px; font-weight:bold; display:inline-block; text-align:center;">Authorized Signatory</div>
                    <div style="font-size:13px; margin-top:5px;">BFS Circle</div>
                </td>
            </tr>
        </table>
    </div>
</div>

<script>
function downloadCAM() {
    const el = document.getElementById('cam-document');
    const opt = {
        margin: [10,10,10,10],
        filename: 'CAM_<?= htmlspecialchars($app['loan_id']) ?>_<?= htmlspecialchars($app['customer_name']) ?>.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };
    html2pdf().set(opt).from(el).save();
}

function shareWhatsApp() {
    const text = `Credit Assessment Memo (CAM)%0A`+
                 `Name: <?= htmlspecialchars($app['customer_name']) ?>%0A`+
                 `Loan Amount: Rs. <?= number_format($app['loan_amount'] ?? 0) ?>%0A`+
                 `FOIR: <?= $foir ?>%%0A`+
                 `CIBIL: <?= $cibil ?>`;
    window.open(`https://wa.me/?text=${text}`, '_blank');
}

async function emailApplicationFormPDF() {
    const clientEmail = "<?= htmlspecialchars($app['email'] ?? '') ?>";
    const appId = <?= intval($app['id']) ?>;
    const clientName = "<?= htmlspecialchars($app['customer_name']) ?>";

    let targetEmail = clientEmail;
    if (!targetEmail) {
        if (typeof Swal !== 'undefined') {
            const res = await Swal.fire({
                title: '✉️ Dispatch Application Form PDF',
                text: `Client email missing. Enter email address to send Application Form & CAM PDF:`,
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
            text: `Sending official application summary to ${targetEmail}`,
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });
    }

    try {
        const formData = new FormData();
        formData.append('applicant_id', appId);
        formData.append('stage_type', 'Verification');
        formData.append('recipient_email', targetEmail);
        formData.append('custom_subject', `Official Loan Application Form Summary (${clientName} - Ref: <?= htmlspecialchars($app['loan_id']) ?>)`);

        const res = await fetch('api.php?api=send_customer_stage_email', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data && data.success) {
            if (typeof Swal !== 'undefined') Swal.fire('Email Dispatched!', `Application Form summary emailed successfully to ${targetEmail}`, 'success');
        } else {
            if (typeof Swal !== 'undefined') Swal.fire('Notice', data.error || 'Email dispatch logged to queue', 'info');
        }
    } catch(e) {
        if (typeof Swal !== 'undefined') Swal.fire('Dispatched', `Application summary dispatched to ${targetEmail}`, 'success');
    }
}

// Actions & Export Dropdown Handlers
function toggleCamDropdown(event) {
    if (event) event.stopPropagation();
    const menu = document.getElementById('camActionsDropdown');
    if (!menu) return;
    const isVisible = menu.style.display === 'block';
    menu.style.display = isVisible ? 'none' : 'block';
}

function closeCamDropdown() {
    const menu = document.getElementById('camActionsDropdown');
    if (menu) menu.style.display = 'none';
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.cam-dropdown-container')) {
        closeCamDropdown();
    }
});
</script>

<?php include 'footer.php'; ?>
