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
    <title>EMI Calculator - BFS Financial Services</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #FF7A00;
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
            box-shadow: 0 10px 20px rgba(255, 122, 0, 0.2);
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
        .val-display { font-weight: 700; color: var(--primary); background: rgba(255,122,0,0.1); padding: 4px 8px; border-radius: 6px; font-size: 14px; }
        
        input[type=range] {
            -webkit-appearance: none;
            width: 100%;
            background: transparent;
            margin-top: 10px;
        }
        input[type=range]::-webkit-slider-thumb {
            -webkit-appearance: none;
            height: 20px; width: 20px;
            border-radius: 50%;
            background: var(--primary);
            cursor: pointer;
            margin-top: -8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        input[type=range]::-webkit-slider-runnable-track {
            width: 100%; height: 6px;
            background: #e2e8f0;
            border-radius: 3px;
        }
        
        .chart-container { margin-top: 20px; height: 200px; display: flex; justify-content: center; }
        
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
        <h1>EMI Calculator</h1>
    </div>

    <div class="calc-container">
        <div class="result-card">
            <div class="result-label">Monthly EMI</div>
            <div class="result-value" id="emi_result">₹0</div>
        </div>
        
        <div class="input-card">
            <div class="input-group">
                <div class="label-row">
                    <label>Loan Amount</label>
                    <div class="val-display" id="amt_val">₹10,00,000</div>
                </div>
                <input type="range" id="amount" min="100000" max="50000000" step="100000" value="1000000" oninput="calculate()">
            </div>
            
            <div class="input-group">
                <div class="label-row">
                    <label>Interest Rate (p.a.)</label>
                    <div class="val-display" id="rate_val">10.5%</div>
                </div>
                <input type="range" id="rate" min="5" max="30" step="0.1" value="10.5" oninput="calculate()">
            </div>
            
            <div class="input-group">
                <div class="label-row">
                    <label>Tenure (Years)</label>
                    <div class="val-display" id="tenure_val">20 Years</div>
                </div>
                <input type="range" id="tenure" min="1" max="30" step="1" value="20" oninput="calculate()">
            </div>
            
            <div class="chart-container">
                <canvas id="emiChart"></canvas>
            </div>
            
            <div class="details-grid">
                <div class="detail-box">
                    <div>Total Interest</div>
                    <div id="tot_int_val">₹0</div>
                </div>
                <div class="detail-box">
                    <div>Total Payment</div>
                    <div id="tot_pay_val">₹0</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let chart = null;
        
        function formatMoney(num) {
            return new Intl.NumberFormat('en-IN').format(Math.round(num));
        }

        function calculate() {
            const p = parseFloat(document.getElementById('amount').value);
            const r = parseFloat(document.getElementById('rate').value);
            const t = parseFloat(document.getElementById('tenure').value);
            
            document.getElementById('amt_val').innerText = '₹' + formatMoney(p);
            document.getElementById('rate_val').innerText = r + '%';
            document.getElementById('tenure_val').innerText = t + ' Years';
            
            const r_monthly = r / (12 * 100);
            const n = t * 12;
            
            let emi = 0;
            let totalInt = 0;
            let totalPay = 0;
            
            if (r > 0) {
                emi = (p * r_monthly * Math.pow(1 + r_monthly, n)) / (Math.pow(1 + r_monthly, n) - 1);
                totalPay = emi * n;
                totalInt = totalPay - p;
            } else {
                emi = p / n;
                totalPay = p;
                totalInt = 0;
            }
            
            document.getElementById('emi_result').innerText = '₹' + formatMoney(emi);
            document.getElementById('tot_int_val').innerText = '₹' + formatMoney(totalInt);
            document.getElementById('tot_pay_val').innerText = '₹' + formatMoney(totalPay);
            
            updateChart(p, totalInt);
        }
        
        function updateChart(principal, interest) {
            const ctx = document.getElementById('emiChart').getContext('2d');
            if (chart) chart.destroy();
            
            chart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Principal', 'Interest'],
                    datasets: [{
                        data: [principal, interest],
                        backgroundColor: ['#3b82f6', '#cbd5e1'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 12 } } }
                    }
                }
            });
        }
        
        calculate();
    </script>
</body>
</html>

