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
    <title>Financial Tools - BFS Financial Services</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #FF7A00;
            --bg-color: #F1F5F9;
            --text-main: #0F172A;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background-color: var(--bg-color); color: var(--text-main); padding-bottom: 70px; }
        
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
        
        .tools-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            padding: 20px 16px;
        }
        
        .tool-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.04);
            text-decoration: none;
            display: flex;
            flex-direction: column;
            gap: 12px;
            border: 1px solid #e2e8f0;
            align-items: center;
            text-align: center;
        }
        
        .tool-card .icon {
            width: 50px; height: 50px;
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px;
        }
        
        .tool-card .title { color: var(--text-main); font-weight: 600; font-size: 14px; }
        
    </style>
</head>
<body>

    <div class="header">
        <a href="index.php"><i class="fas fa-arrow-left"></i></a>
        <h1>Financial Tools</h1>
    </div>

    <div class="tools-grid">
        <a href="calc_emi.php" class="tool-card">
            <div class="icon" style="background:rgba(255,122,0,0.1); color:#FF7A00;"><i class="fas fa-calculator"></i></div>
            <div class="title">EMI<br>Calculator</div>
        </a>
        <a href="calc_eligibility.php" class="tool-card">
            <div class="icon" style="background:rgba(16, 185, 129, 0.1); color:#10b981;"><i class="fas fa-check-circle"></i></div>
            <div class="title">Loan<br>Eligibility</div>
        </a>
        <a href="calc_foir.php" class="tool-card">
            <div class="icon" style="background:rgba(99, 102, 241, 0.1); color:#6366f1;"><i class="fas fa-percentage"></i></div>
            <div class="title">FOIR<br>Calculator</div>
        </a>
        <a href="calc_ltv.php" class="tool-card">
            <div class="icon" style="background:rgba(236, 72, 153, 0.1); color:#ec4899;"><i class="fas fa-home"></i></div>
            <div class="title">LTV<br>Calculator</div>
        </a>
        <a href="#" class="tool-card" onclick="alert('Coming soon!')">
            <div class="icon" style="background:rgba(234, 179, 8, 0.1); color:#eab308;"><i class="fas fa-coins"></i></div>
            <div class="title">Gold Loan<br>Calculator</div>
        </a>
        <a href="#" class="tool-card" onclick="alert('Coming soon!')">
            <div class="icon" style="background:rgba(59, 130, 246, 0.1); color:#3b82f6;"><i class="fas fa-chart-line"></i></div>
            <div class="title">Max Tenure<br>Calculator</div>
        </a>
    </div>

</body>
</html>
