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
    <title>FOIR Calculator - BFS Financial Services</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
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
            box-shadow: 0 10px 20px rgba(99, 102, 241, 0.2);
        }
        .result-label { font-size: 14px; opacity: 0.9; margin-bottom: 4px; }
        .result-value { font-size: 38px; font-weight: 700; }
        
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

    </style>
</head>
<body>

    <div class="header">
        <a href="calculators.php"><i class="fas fa-arrow-left"></i></a>
        <h1>FOIR Calculator</h1>
    </div>

    <div class="calc-container">
        <div class="result-card">
            <div class="result-label">Current FOIR</div>
            <div class="result-value" id="foir_result">0%</div>
            <div style="font-size:12px; margin-top:8px; opacity:0.8;" id="foir_status">Ideal FOIR is typically < 50%</div>
        </div>
        
        <div class="input-card">
            <div class="input-group">
                <div class="label-row">
                    <label>Total Monthly Income</label>
                </div>
                <div class="input-wrapper">
                    <span class="prefix">₹</span>
                    <input type="number" id="income" value="60000" oninput="calculate()">
                </div>
            </div>
            
            <div class="input-group">
                <div class="label-row">
                    <label>Total Monthly Obligations (EMIs)</label>
                </div>
                <div class="input-wrapper">
                    <span class="prefix">₹</span>
                    <input type="number" id="obligations" value="15000" oninput="calculate()">
                </div>
            </div>
            
        </div>
    </div>

    <script>
        function calculate() {
            const income = parseFloat(document.getElementById('income').value) || 0;
            const obligations = parseFloat(document.getElementById('obligations').value) || 0;
            
            let foir = 0;
            if (income > 0) {
                foir = (obligations / income) * 100;
            }
            
            document.getElementById('foir_result').innerText = foir.toFixed(1) + '%';
            
            const statusEl = document.getElementById('foir_status');
            if (foir > 60) {
                statusEl.innerText = "High Risk - Very difficult to get a loan";
            } else if (foir > 50) {
                statusEl.innerText = "Moderate Risk - Borderline eligibility";
            } else {
                statusEl.innerText = "Good FOIR - Ideal for new loans";
            }
        }
        
        calculate();
    </script>
</body>
</html>

