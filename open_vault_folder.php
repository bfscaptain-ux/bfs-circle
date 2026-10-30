<?php
/**
 * BFS 10x On-Premise Vault - Instant Windows Explorer Launcher
 * 
 * Pops open the exact local physical folder on F:\BFS in Windows Explorer
 */

require_once 'config.php';
require_once 'bfs_vault_engine.php';
require_role(['Admin', 'RM']);

$applicantId = (int)($_GET['id'] ?? 0);
if ($applicantId <= 0) {
    // Open Root Vault directory F:\BFS\Vault_Leads
    $rootPath = BfsVaultEngine::getActiveBasePath();
    $cmd = 'explorer.exe ' . escapeshellarg($rootPath);
    @pclose(@popen("start \"\" " . $cmd, "r"));
    $result = [
        'success' => true,
        'path'    => $rootPath,
        'message' => 'Windows Explorer opened to ' . $rootPath
    ];

    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
              || (isset($_GET['ajax']) && $_GET['ajax'] == 1);

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    }
    $applicant = ['customer_name' => 'Root Vault (F:\\BFS)', 'loan_id' => 'ALL-LEADS'];
} else {
    $stmt = $db->prepare("SELECT id, customer_name, loan_id FROM applicants WHERE id = ?");
    $stmt->execute([$applicantId]);
    $applicant = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$applicant) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Applicant not found']);
        exit;
    }

    $result = BfsVaultEngine::openExplorer($applicant['id'], $applicant['customer_name']);
}

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
          || (isset($_GET['ajax']) && $_GET['ajax'] == 1);

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Official BFS Circle Monochrome Favicon & Branding -->
    <link rel="icon" type="image/png" sizes="64x64" href="<?php echo isset($base_path) ? $base_path : ""; ?>favicon.png?v=4">
    <link rel="icon" type="image/x-icon" href="<?php echo isset($base_path) ? $base_path : ""; ?>favicon.ico?v=4">
    <link rel="shortcut icon" href="<?php echo isset($base_path) ? $base_path : ""; ?>favicon.ico?v=4">
    <link rel="apple-touch-icon" href="<?php echo isset($base_path) ? $base_path : ""; ?>favicon.png?v=4">
    <meta charset="UTF-8">
    <title>Opening Local Vault Folder...</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #09090b;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
        }
        .card {
            background: #18181b;
            border: 1px solid #27272a;
            border-radius: 12px;
            padding: 30px;
            max-width: 480px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }
        .icon { font-size: 40px; margin-bottom: 15px; }
        .path {
            background: #09090b;
            border: 1px solid #3f3f46;
            padding: 10px 14px;
            border-radius: 6px;
            font-family: monospace;
            font-size: 13px;
            color: #38bdf8;
            margin: 15px 0;
            word-break: break-all;
        }
        .btn {
            background: #ffffff;
            color: #000000;
            border: none;
            padding: 8px 18px;
            border-radius: 6px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">📂</div>
        <h2 style="margin:0 0 8px 0; font-size: 20px;">Folder Opened in Windows Explorer</h2>
        <p style="color: #a1a1aa; font-size: 13px; margin: 0 0 10px 0;">
            Applicant: <strong><?php echo htmlspecialchars($applicant['customer_name']); ?></strong> (Ref: <?php echo htmlspecialchars($applicant['loan_id']); ?>)
        </p>
        <div class="path"><?php echo htmlspecialchars($result['path'] ?? 'F:\\BFS\\Vault_Leads'); ?></div>
        <p style="color: #71717a; font-size: 12px; margin-bottom: 20px;">
            Your local file manager has been launched to this physical folder on Drive F:. You can close this tab.
        </p>
        <button onclick="window.close()" class="btn">Close Tab</button>
    </div>
    <script>
        setTimeout(() => {
            if (window.opener) {
                window.close();
            }
        }, 3000);
    </script>
</body>
</html>
