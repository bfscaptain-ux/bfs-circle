<?php
require_once '../config.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Eligibility Calculator - BFS Financial Services</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #10b981;
            --bg-color: #F1F5F9;
            --text-main: #0F172A;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background-color: var(--bg-color); color: var(--text-main); }
        
        .header {
            background: white;
            padding: 16px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .header a { color: var(--text-main); font-size: 20px; text-decoration: none; }
        .header h1 { font-size: 18px; font-weight: 600; }
        
        .calc-container { padding: 20px; }
        
        .result-card {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 16px;
            padding: 24px;
            color: white;
            text-align: center;
            margin-bottom: 24px;
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.2);
        }
        .result-label { font-size: 14px; opacity: 0.9; margin-bottom: 4px; }
        .result-value { font-size: 32px; font-weight: 700; }
        
        .input-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.04);
            margin-bottom: 20px;
        }
        
        .input-group { margin-bottom: 20px; }
        .input-group:last-child { margin-bottom: 0; }
        
        .label-row {
            display: flex; justify-content: space-between; margin-bottom: 8px;
        }
        .label-row label { font-weight: 600; font-size: 14px; }
        
        .input-wrapper {
            display: flex;
            align-items: center;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            overflow: hidden;
        }
        .prefix {
            background: #e2e8f0;
            padding: 12px 16px;
            color: #475569;
            font-weight: 600;
        }
        .input-wrapper input {
            flex: 1;
            border: none;
            padding: 12px 16px;
            background: transparent;
            outline: none;
            font-size: 16px;
            font-weight: 600;
        }
        
        .details-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 20px;
        }
        .detail-box {
            background: #f8fafc; padding: 12px; border-radius: 10px; text-align: center; border: 1px solid #e2e8f0;
        }
        .detail-box div:first-child { font-size: 12px; color: #64748b; margin-bottom: 4px; }
        .detail-box div:last-child { font-weight: 600; font-size: 15px; }

    </style>
</head>
<body>

    <div class="header">
        <a href="calculators.php"><i class="fas fa-arrow-left"></i></a>
        <h1>Loan Eligibility</h1>
    </div>

    <div class="calc-container">
        <div class="result-card">
            <div class="result-label">Eligible Loan Amount</div>
            <div class="result-value" id="el_result">₹0</div>
        </div>
        
        <div class="input-card">
            <div class="input-group">
                <div class="label-row">
                    <label>Monthly Income</label>
                </div>
                <div class="input-wrapper">
                    <span class="prefix">₹</span>
                    <input type="number" id="income" value="50000" oninput="calculate()">
                </div>
            </div>
            
            <div class="input-group">
                <div class="label-row">
                    <label>Existing EMIs</label>
                </div>
                <div class="input-wrapper">
                    <span class="prefix">₹</span>
                    <input type="number" id="existing_emi" value="5000" oninput="calculate()">
                </div>
            </div>
            
            <div class="input-group">
                <div class="label-row">
                    <label>Interest Rate (p.a.)</label>
                </div>
                <div class="input-wrapper">
                    <span class="prefix">%</span>
                    <input type="number" id="rate" value="10.5" step="0.1" oninput="calculate()">
                </div>
            </div>
            
            <div class="input-group">
                <div class="label-row">
                    <label>Tenure (Years)</label>
                </div>
                <div class="input-wrapper">
                    <span class="prefix">Yrs</span>
                    <input type="number" id="tenure" value="20" oninput="calculate()">
                </div>
            </div>
            
            <div class="details-grid">
                <div class="detail-box">
                    <div>FOIR (%)</div>
                    <div id="foir_val">50%</div>
                </div>
                <div class="detail-box">
                    <div>Max EMI allowed</div>
                    <div id="max_emi_val">₹0</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function formatMoney(num) {
            return new Intl.NumberFormat('en-IN').format(Math.round(num));
        }

        function calculate() {
            const income = parseFloat(document.getElementById('income').value) || 0;
            const obligations = parseFloat(document.getElementById('existing_emi').value) || 0;
            const r = parseFloat(document.getElementById('rate').value) || 0;
            const t = parseFloat(document.getElementById('tenure').value) || 0;
            
            let foir = 50;
            if (income > 150000) foir = 65;
            else if (income > 50000) foir = 60;
            
            document.getElementById('foir_val').innerText = foir + '%';
            
            const maxEmi = (income * (foir / 100)) - obligations;
            
            let maxLoan = 0;
            if (maxEmi > 0 && r > 0 && t > 0) {
                const r_monthly = r / (12 * 100);
                const n = t * 12;
                maxLoan = maxEmi * ((Math.pow(1 + r_monthly, n) - 1) / (r_monthly * Math.pow(1 + r_monthly, n)));
            }
            
            document.getElementById('max_emi_val').innerText = '₹' + formatMoney(Math.max(0, maxEmi));
            document.getElementById('el_result').innerText = '₹' + formatMoney(Math.max(0, maxLoan));
        }
        
        calculate();
    </script>
</body>
</html>

