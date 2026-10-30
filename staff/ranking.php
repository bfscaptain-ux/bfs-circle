<?php
define('IS_SUBFOLDER', true);
require_once __DIR__ . '/../config.php';
require_role(['Staff']);


$username = $_SESSION['username'] ?? '';

$stmt = $db->query("
    SELECT executive_name, COUNT(*) as total_visits 
    FROM field_visits 
    GROUP BY executive_name 
    ORDER BY total_visits DESC
");
$rankings = $stmt->fetchAll(PDO::FETCH_ASSOC);

$my_rank = 0;
$my_score = 0;
foreach ($rankings as $index => $staff) {
    if ($staff['executive_name'] === $username) {
        $my_rank = $index + 1;
        $my_score = $staff['total_visits'];
        break;
    }
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
    <title>Leaderboard - BFS Financial Services</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #FF7A00;
            --bg-color: #F1F5F9;
            --text-main: #0F172A;
            --gold: #F59E0B;
            --silver: #94A3B8;
            --bronze: #B45309;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background-color: var(--bg-color); color: var(--text-main); padding-bottom: 90px; }
        
        .header {
            background: linear-gradient(135deg, #FF7A00, #C2410C);
            padding: 30px 20px 50px 20px;
            color: white;
            border-bottom-left-radius: 30px;
            border-bottom-right-radius: 30px;
            text-align: center;
            position: relative;
            box-shadow: 0 10px 25px rgba(255, 122, 0, 0.2);
        }
        .header-top { display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; }
        .header-top a { color:white; font-size:20px; text-decoration:none; }
        
        .header h1 { font-size: 28px; font-weight: 800; margin-bottom: 8px; letter-spacing: -0.5px; }
        .header p { font-size: 14px; color: rgba(255,255,255,0.8); font-weight: 500; }
        
        .my-rank-card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            margin: -40px 20px 24px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.08);
            position: relative;
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border: 1px solid rgba(255, 122, 0, 0.1);
        }
        .my-rank-info { display: flex; flex-direction: column; }
        .my-rank-label { font-size: 12px; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
        .my-rank-value { font-size: 32px; font-weight: 800; color: var(--primary); line-height: 1; }
        .my-score-box { background: #F8FAFC; color: var(--text-main); padding: 10px 16px; border-radius: 12px; font-weight: 700; font-size: 18px; border: 1px solid #E2E8F0; }

        .leaderboard-list { padding: 0 20px; }
        
        .rank-card {
            background: white;
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.03);
            position: relative;
            overflow: hidden;
            border: 1px solid #F1F5F9;
            transition: transform 0.2s;
        }
        
        .rank-badge {
            width: 48px; height: 48px;
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; font-weight: 800;
            background: #F8FAFC; color: #64748b;
            border: 1px solid #E2E8F0;
        }
        
        .rank-card.top-1 { background: linear-gradient(to right, white, #FEF3C7); box-shadow: 0 10px 25px rgba(245, 158, 11, 0.15); border: 1px solid #FDE68A; }
        .rank-card.top-1 .rank-badge { background: linear-gradient(135deg, #F59E0B, #D97706); color: white; font-size: 20px; border: none; box-shadow: 0 4px 10px rgba(245, 158, 11, 0.4); }
        
        .rank-card.top-2 { background: linear-gradient(to right, white, #F1F5F9); border: 1px solid #E2E8F0; }
        .rank-card.top-2 .rank-badge { background: linear-gradient(135deg, #94A3B8, #64748B); color: white; border: none; }
        
        .rank-card.top-3 { background: linear-gradient(to right, white, #FFEDD5); border: 1px solid #FED7AA; }
        .rank-card.top-3 .rank-badge { background: linear-gradient(135deg, #F97316, #C2410C); color: white; border: none; }
        
        .staff-info { flex: 1; }
        .staff-name { font-weight: 700; font-size: 16px; color: var(--text-main); margin-bottom: 2px; display: flex; align-items: center; gap: 6px; }
        .staff-visits { font-size: 13px; color: #64748b; font-weight: 500; }
        .staff-score { font-size: 22px; font-weight: 800; color: var(--text-main); }
        .top-1 .staff-score { color: #D97706; }

        .crown-icon { color: var(--gold); font-size: 14px; }
    </style>
</head>
<body>

    <div class="header">
        <div class="header-top">
            <a href="index.php"><i class="fas fa-arrow-left"></i></a>
            <div style="font-size:16px; font-weight:600;"><i class="fas fa-trophy" style="color:var(--gold);"></i> Leaderboard</div>
            <div style="width:20px;"></div>
        </div>
        <h1>Top Performers</h1>
        <p>Rankings based on total visits generated</p>
    </div>

    <div class="my-rank-card">
        <div class="my-rank-info">
            <span class="my-rank-label">Your Current Rank</span>
            <span class="my-rank-value">#<?= $my_rank > 0 ? $my_rank : '-' ?></span>
        </div>
        <div class="my-score-box">
            <?= $my_score ?> Visits
        </div>
    </div>

    <div class="leaderboard-list">
        <?php 
        $r = 1;
        foreach($rankings as $staff): 
            $is_me = ($staff['executive_name'] === $username);
            $cardClass = '';
            if($r === 1) $cardClass = 'top-1';
            else if($r === 2) $cardClass = 'top-2';
            else if($r === 3) $cardClass = 'top-3';
        ?>
            <div class="rank-card <?= $cardClass ?>">
                
                <div class="rank-badge">
                    <?php if($r === 1): ?><i class="fas fa-trophy"></i>
                    <?php else: ?><?= $r ?><?php endif; ?>
                </div>
                
                <div class="staff-info">
                    <div class="staff-name">
                        <?php if($r === 1): ?><i class="fas fa-crown crown-icon"></i><?php endif; ?>
                        <?= htmlspecialchars($staff['executive_name']) ?>
                        <?php if($is_me): ?> <span style="background:var(--primary); color:white; font-size:9px; padding:2px 6px; border-radius:10px; margin-left:4px;">YOU</span> <?php endif; ?>
                    </div>
                    <div class="staff-visits">Total Visits</div>
                </div>
                
                <div class="staff-score">
                    <?= $staff['total_visits'] ?>
                </div>
            </div>
        <?php 
            $r++;
        endforeach; 
        
        if (count($rankings) === 0) {
            echo "<div style='text-align:center; padding:30px; color:#94a3b8;'>No visits recorded yet. Be the first!</div>";
        }
        ?>
    </div>

</body>
</html>
