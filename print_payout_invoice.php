<?php
require_once __DIR__ . '/config.php';
require_role(['Admin'], false);


$id = intval($_GET['id'] ?? 0);
$invoice_number = trim($_GET['invoice_number'] ?? '');
$bank_name = trim($_GET['bank_name'] ?? '');

$invoice = null;
$bank_payouts = [];
$bank_info = null;

if ($id > 0) {
    $stmt = $db->prepare("SELECT * FROM bank_payout_invoices WHERE id = ?");
    $stmt->execute([$id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif (!empty($invoice_number)) {
    $stmt = $db->prepare("SELECT * FROM bank_payout_invoices WHERE invoice_number = ?");
    $stmt->execute([$invoice_number]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($invoice) {
    $bank_name = $invoice['bank_name'];
}

if (!empty($bank_name)) {
    $b_stmt = $db->prepare("SELECT * FROM bankers WHERE bank_name LIKE ? ORDER BY id DESC LIMIT 1");
    $b_stmt->execute(["%$bank_name%"]);
    $bank_info = $b_stmt->fetch(PDO::FETCH_ASSOC);

    $p_stmt = $db->prepare("SELECT * FROM bank_payout_invoices WHERE bank_name LIKE ? ORDER BY created_at DESC");
    $p_stmt->execute(["%$bank_name%"]);
    $bank_payouts = $p_stmt->fetchAll(PDO::FETCH_ASSOC);
}

if (!$invoice && empty($bank_payouts)) {
    die("Payout record or Bank statement not found.");
}

// Function to convert numbers into Indian Rupees words format
function convertNumberToWords($amount) {
    $amount = round(floatval($amount));
    if ($amount <= 0) return "Zero Rupees Only";
    
    $words = array(
        0 => '', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five',
        6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten',
        11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen',
        16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen',
        20 => 'Twenty', 30 => 'Thirty', 40 => 'Forty', 50 => 'Fifty',
        60 => 'Sixty', 70 => 'Seventy', 80 => 'Eighty', 90 => 'Ninety'
    );
    
    $digits = array('', 'Hundred', 'Thousand', 'Lakh', 'Crore');
    
    $num_str = (string)$amount;
    $len = strlen($num_str);
    
    if ($len > 9) return "Rupees " . number_format($amount) . " Only";
    
    $result = "";
    
    // Crore
    if ($amount >= 10000000) {
        $crores = floor($amount / 10000000);
        $amount %= 10000000;
        $result .= convertNumberToWords($crores) . " Crore ";
    }
    // Lakh
    if ($amount >= 100000) {
        $lakhs = floor($amount / 100000);
        $amount %= 100000;
        if ($lakhs < 20) {
            $result .= $words[$lakhs] . " Lakh ";
        } else {
            $result .= $words[floor($lakhs/10)*10] . " " . $words[$lakhs%10] . " Lakh ";
        }
    }
    // Thousand
    if ($amount >= 1000) {
        $thousands = floor($amount / 1000);
        $amount %= 1000;
        if ($thousands < 20) {
            $result .= $words[$thousands] . " Thousand ";
        } else {
            $result .= $words[floor($thousands/10)*10] . " " . $words[$thousands%10] . " Thousand ";
        }
    }
    // Hundred
    if ($amount >= 100) {
        $hundreds = floor($amount / 100);
        $amount %= 100;
        $result .= $words[$hundreds] . " Hundred ";
    }
    // Tens & Units
    if ($amount > 0) {
        if ($amount < 20) {
            $result .= $words[$amount] . " ";
        } else {
            $result .= $words[floor($amount/10)*10] . " " . $words[$amount%10] . " ";
        }
    }
    
    return trim($result) . " Rupees Only";
}

$page_title = $invoice ? 'Payout Invoice Receipt - ' . $invoice['invoice_number'] : 'Bank Payout Ledger Statement - ' . $bank_name;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Official BFS Circle Monochrome Favicon & Branding -->
    <link rel="icon" type="image/png" sizes="64x64" href="<?php echo isset($base_path) ? $base_path : ""; ?>favicon.png?v=3">
    <link rel="icon" type="image/x-icon" href="<?php echo isset($base_path) ? $base_path : ""; ?>favicon.ico?v=3">
    <link rel="shortcut icon" href="<?php echo isset($base_path) ? $base_path : ""; ?>favicon.ico?v=3">
    <link rel="apple-touch-icon" href="<?php echo isset($base_path) ? $base_path : ""; ?>favicon.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #f4f4f5;
            color: #09090b;
            margin: 0;
            padding: 20px;
        }

        .no-print-toolbar {
            max-width: 850px;
            margin: 0 auto 20px auto;
            background: #09090b;
            color: #ffffff;
            padding: 12px 20px;
            border-radius: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 14px rgba(0,0,0,0.15);
        }

        .no-print-toolbar button, .no-print-toolbar a {
            background: #ffffff;
            color: #09090b;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .no-print-toolbar button.btn-secondary {
            background: #27272a;
            color: #ffffff;
            border: 1px solid #3f3f46;
        }

        .invoice-card {
            max-width: 850px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid #18181b;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            position: relative;
        }

        .company-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #09090b;
            padding-bottom: 20px;
            margin-bottom: 24px;
        }

        .company-logo-title h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 800;
            color: #09090b;
            letter-spacing: -0.5px;
            text-transform: uppercase;
        }

        .company-logo-title p {
            margin: 4px 0 0 0;
            font-size: 12px;
            color: #52525b;
            font-weight: 600;
        }

        .receipt-badge {
            background: #09090b;
            color: #ffffff;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 6px 14px;
            border-radius: 6px;
            display: inline-block;
        }

        .meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            background: #fafafa;
            border: 1px solid #e4e4e7;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
            font-size: 13px;
        }

        .meta-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .meta-item span.label {
            font-size: 11px;
            font-weight: 700;
            color: #71717a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .meta-item span.val {
            font-size: 14px;
            font-weight: 700;
            color: #09090b;
        }

        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
            font-size: 13px;
        }

        .invoice-table th {
            background: #09090b;
            color: #ffffff;
            padding: 10px 14px;
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }

        .invoice-table td {
            padding: 12px 14px;
            border-bottom: 1px solid #e4e4e7;
            color: #09090b;
        }

        .total-box {
            background: #fafafa;
            border: 1px solid #18181b;
            border-radius: 8px;
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .total-box .amount-words {
            font-size: 13px;
            font-weight: 600;
            color: #52525b;
            font-style: italic;
        }

        .total-box .amount-val {
            font-size: 24px;
            font-weight: 800;
            color: #09090b;
            font-family: 'Outfit', sans-serif;
        }

        .signature-block {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            padding-top: 20px;
            border-top: 1px dashed #d4d4d8;
        }

        .sig-box {
            text-align: center;
            width: 200px;
        }

        .sig-box .line {
            border-bottom: 1.5px solid #09090b;
            margin-bottom: 6px;
            height: 40px;
        }

        .sig-box p {
            margin: 0;
            font-size: 12px;
            font-weight: 700;
            color: #09090b;
        }

        @media print {
            body { background: #ffffff !important; padding: 0 !important; }
            .no-print-toolbar { display: none !important; }
            .invoice-card { border: none !important; box-shadow: none !important; padding: 0 !important; width: 100% !important; max-width: 100% !important; }
        }
    </style>
</head>
<body>

    <!-- Non-Print Toolbar -->
    <div class="no-print-toolbar">
        <div style="font-weight:700; font-size:14px; display:flex; align-items:center; gap:8px;">
            📄 Official Bank Payout Invoice Voucher
        </div>
        <div style="display:flex; gap:10px;">
            <button onclick="window.print()" class="btn">🖨️ Print Voucher</button>
            <button onclick="downloadPDF()" class="btn btn-secondary">📥 Download PDF</button>
            <a href="javascript:window.close();" class="btn btn-secondary">❌ Close</a>
        </div>
    </div>

    <!-- Printable Invoice Voucher Card -->
    <div class="invoice-card" id="printable-payout-area">
        
        <!-- Header -->
        <div class="company-header">
            <div class="company-logo-title">
                <h1>BHARDWAJ FINANCIAL SERVICES</h1>
                <p>RBI Registered Corporate Loan & Financial Advisory Partner</p>
                <p style="font-size:11px; color:#71717a; margin-top:2px;">Head Office: Main Financial District &bull; Support: info@bfsfinancials.com</p>
            </div>
            <div style="text-align:right;">
                <div class="receipt-badge"><?php echo $invoice ? 'PAYOUT RECEIPT' : 'BANK PAYOUT STATEMENT'; ?></div>
                <div style="margin-top:8px; font-size:12px; font-weight:700; color:#52525b;">
                    Date: <?php echo date('d M Y'); ?>
                </div>
            </div>
        </div>

        <?php if ($invoice): ?>
            <!-- Single Invoice Receipt Mode -->
            <div class="meta-grid">
                <div class="meta-item">
                    <span class="label">Bank Partner Name</span>
                    <span class="val">🏦 <?php echo htmlspecialchars($invoice['bank_name']); ?></span>
                </div>
                <div class="meta-item">
                    <span class="label">Invoice Voucher Number</span>
                    <span class="val">#<?php echo htmlspecialchars($invoice['invoice_number']); ?></span>
                </div>
                <div class="meta-item">
                    <span class="label">Bank UTR / Ref Number</span>
                    <span class="val"><?php echo htmlspecialchars($invoice['bank_utr'] ?: 'N/A'); ?></span>
                </div>
                <div class="meta-item">
                    <span class="label">Payout Received Date</span>
                    <span class="val"><?php echo !empty($invoice['received_date']) ? date('d M Y', strtotime($invoice['received_date'])) : date('d M Y', strtotime($invoice['created_at'])); ?></span>
                </div>
            </div>

            <table class="invoice-table">
                <thead>
                    <tr>
                        <th>Particulars / Description</th>
                        <th>Disbursed Volume (₹)</th>
                        <th>Commission Rate (%)</th>
                        <th>Net Commission Amount (₹)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <strong>Bank Commission Payout Credit</strong><br>
                            <span style="font-size:11px; color:#64748b;">Bank Payout Received for loan disbursals with <?php echo htmlspecialchars($invoice['bank_name']); ?></span>
                        </td>
                        <td style="font-weight:700;">₹ <?php echo number_format(floatval($invoice['disbursed_amount'])); ?></td>
                        <td style="font-weight:700;"><?php echo htmlspecialchars($invoice['commission_pct']); ?>%</td>
                        <td style="font-weight:800; color:#09090b;">₹ <?php echo number_format(floatval($invoice['received_amount'] ?: $invoice['net_invoice_amount'])); ?></td>
                    </tr>
                </tbody>
            </table>

            <div class="total-box">
                <div>
                    <div style="font-size:11px; font-weight:800; color:#71717a; text-transform:uppercase;">Total Amount In Words</div>
                    <div class="amount-words">"<?php echo convertNumberToWords($invoice['received_amount'] ?: $invoice['net_invoice_amount']); ?>"</div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:11px; font-weight:800; color:#71717a; text-transform:uppercase;">Net Amount Received</div>
                    <div class="amount-val">₹ <?php echo number_format(floatval($invoice['received_amount'] ?: $invoice['net_invoice_amount'])); ?></div>
                </div>
            </div>

        <?php else: ?>
            <!-- Full Bank Payout Ledger Statement Mode -->
            <div class="meta-grid">
                <div class="meta-item">
                    <span class="label">Bank Name</span>
                    <span class="val">🏦 <?php echo htmlspecialchars($bank_name); ?></span>
                </div>
                <div class="meta-item">
                    <span class="label">Statement Period</span>
                    <span class="val">All Logged Commission Payouts</span>
                </div>
                <div class="meta-item">
                    <span class="label">Official Contact / Nodal RM</span>
                    <span class="val"><?php echo htmlspecialchars($bank_info['official_email'] ?? $bank_info['bank_official_email'] ?? 'Registered RM'); ?></span>
                </div>
                <div class="meta-item">
                    <span class="label">Total Invoices Recorded</span>
                    <span class="val"><?php echo count($bank_payouts); ?> Payout Invoices</span>
                </div>
            </div>

            <table class="invoice-table">
                <thead>
                    <tr>
                        <th>Invoice Ref #</th>
                        <th>Disbursed Volume (₹)</th>
                        <th>Payout %</th>
                        <th>Amount Received (₹)</th>
                        <th>Bank UTR #</th>
                        <th>Received Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $sum_disbursed = 0;
                    $sum_received = 0;
                    foreach ($bank_payouts as $p): 
                        $amt = floatval($p['received_amount'] ?: $p['net_invoice_amount']);
                        $disb = floatval($p['disbursed_amount']);
                        $sum_disbursed += $disb;
                        $sum_received += $amt;
                    ?>
                    <tr>
                        <td style="font-weight:700; font-family:monospace;"><?php echo htmlspecialchars($p['invoice_number']); ?></td>
                        <td>₹ <?php echo number_format($disb); ?></td>
                        <td><?php echo htmlspecialchars($p['commission_pct']); ?>%</td>
                        <td style="font-weight:800; color:#09090b;">₹ <?php echo number_format($amt); ?></td>
                        <td style="font-family:monospace; font-weight:700;"><?php echo htmlspecialchars($p['bank_utr'] ?: '—'); ?></td>
                        <td style="font-size:12px; color:#52525b;"><?php echo !empty($p['received_date']) ? date('d M Y', strtotime($p['received_date'])) : date('d M Y', strtotime($p['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="total-box">
                <div>
                    <div style="font-size:11px; font-weight:800; color:#71717a; text-transform:uppercase;">Total Commission Received In Words</div>
                    <div class="amount-words">"<?php echo convertNumberToWords($sum_received); ?>"</div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:11px; font-weight:800; color:#71717a; text-transform:uppercase;">Total Payout Collected</div>
                    <div class="amount-val">₹ <?php echo number_format($sum_received); ?></div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Footer Signatures -->
        <div class="signature-block">
            <div class="sig-box">
                <div class="line"></div>
                <p>Prepared By (Accounts)</p>
            </div>
            <div style="text-align:center;">
                <div style="border: 2px solid #09090b; padding: 6px 16px; border-radius: 6px; font-size:11px; font-weight:800; color:#09090b; letter-spacing:1px; text-transform:uppercase; display:inline-block;">
                    SEAL & VERIFIED
                </div>
            </div>
            <div class="sig-box">
                <div class="line"></div>
                <p>Authorized Signatory</p>
            </div>
        </div>

    </div>

    <script>
        function downloadPDF() {
            const element = document.getElementById('printable-payout-area');
            const opt = {
                margin:       10,
                filename:     '<?php echo strtolower(str_replace(' ', '_', $page_title)); ?>.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            html2pdf().set(opt).from(element).save();
        }
    </script>
</body>
</html>
