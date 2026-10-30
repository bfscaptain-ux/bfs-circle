<?php
require_once '../config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Partner') {
    header("Location: ../login.php");
    exit;
}

$stmt = $db->prepare("SELECT * FROM referrals WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$partner = $stmt->fetch();

$user_id = $_SESSION['user_id'];

// Get Projects
$stmt_proj = $db->prepare("SELECT * FROM partner_projects WHERE partner_user_id = ? ORDER BY created_at DESC");
$stmt_proj->execute([$user_id]);
$projects = $stmt_proj->fetchAll();

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>My Projects</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <style>
        :root {
            --primary: #f97316;
            --bg-main: #f8fafc;
            --bg-card: #ffffff;
            --text-primary: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --success: #10b981;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; -webkit-tap-highlight-color: transparent; }
        
        body { background-color: var(--bg-main); color: var(--text-primary); padding-bottom: 80px; }

        .header {
            background: var(--bg-card);
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .header h1 { font-family: 'Outfit', sans-serif; font-size: 20px; font-weight: 700; color: var(--text-primary); }
        .back-btn { color: var(--text-primary); text-decoration: none; display: flex; align-items: center; }

        .container { padding: 20px; }

        .project-card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 16px;
            border: 1px solid var(--border);
            margin-bottom: 16px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.02);
        }
        .project-card h3 { font-size: 16px; margin-bottom: 4px; }
        .project-card p { font-size: 13px; color: var(--text-muted); display: flex; align-items: center; gap: 4px; margin-bottom: 8px;}
        .badge { background: rgba(16, 185, 129, 0.1); color: var(--success); padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; display: inline-block; }

        /* Floating Action Button */
        .fab {
            position: fixed;
            bottom: 90px;
            right: 20px;
            width: 56px;
            height: 56px;
            background: var(--primary);
            color: white;
            border-radius: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(249, 115, 22, 0.4);
            cursor: pointer;
            z-index: 90;
        }

        /* Bottom Nav */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background: var(--bg-card);
            display: flex;
            justify-content: space-around;
            padding: 12px 0 calc(12px + env(safe-area-inset-bottom));
            border-top: 1px solid var(--border);
            box-shadow: 0 -4px 20px rgba(0,0,0,0.05);
            z-index: 1000;
        }
        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 11px;
            font-weight: 500;
        }
        .nav-item.active { color: var(--primary); }
        .nav-item i { width: 22px; height: 22px; }

        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; align-items: flex-end; }
        .modal.active { display: flex; }
        .modal-content { background: var(--bg-card); width: 100%; border-radius: 24px 24px 0 0; padding: 24px; animation: slideUp 0.3s ease-out; }
        @keyframes slideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
        
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 13px; font-weight: 500; margin-bottom: 6px; color: var(--text-primary); }
        .form-group input, .form-group textarea { width: 100%; padding: 12px 16px; border: 1px solid var(--border); border-radius: 12px; font-size: 15px; outline: none; }
        
        .btn-submit { width: 100%; padding: 14px; background: var(--primary); color: white; border: none; border-radius: 12px; font-size: 16px; font-weight: 600; cursor: pointer; margin-top: 8px; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-header h2 { font-size: 18px; }
        .close-btn { background: none; border: none; font-size: 24px; color: var(--text-muted); cursor: pointer; }
    </style>
</head>
<body>

    <div class="header">
        <a href="index.php" class="back-btn"><i data-lucide="arrow-left"></i></a>
        <h1>My Projects</h1>
    </div>

    <div class="container">
        <?php if(empty($projects)): ?>
            <div style="text-align:center; padding: 40px 20px; color: var(--text-muted);">
                <i data-lucide="building-2" style="width:48px; height:48px; opacity:0.5; margin-bottom:16px;"></i>
                <p>No projects added yet.</p>
                <p style="font-size:12px; margin-top:8px;">Add your active construction sites here.</p>
            </div>
        <?php else: ?>
            <?php foreach($projects as $p): ?>
            <div class="project-card">
                <h3><?php echo htmlspecialchars($p['project_name']); ?></h3>
                <p><i data-lucide="map-pin" style="width:14px;height:14px;"></i> <?php echo htmlspecialchars($p['location']); ?></p>
                <div class="badge"><?php echo htmlspecialchars($p['status']); ?></div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="fab" onclick="document.getElementById('projectModal').classList.add('active')">
        <i data-lucide="plus"></i>
    </div>

    <div class="bottom-nav">
        <a href="index.php" class="nav-item">
            <i data-lucide="home"></i>
            <span>Home</span>
        </a>
        <a href="projects.php" class="nav-item active">
            <i data-lucide="building"></i>
            <span>Projects</span>
        </a>
        <a href="../logout.php" class="nav-item">
            <i data-lucide="log-out"></i>
            <span>Logout</span>
        </a>
    </div>

    <!-- Add Project Modal -->
    <div id="projectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Project</h2>
                <button class="close-btn" onclick="document.getElementById('projectModal').classList.remove('active')">&times;</button>
            </div>
            <form id="projectForm" onsubmit="submitProject(event)">
                <div class="form-group">
                    <label>Project Name</label>
                    <input type="text" name="project_name" required placeholder="e.g. Skyline Apartments">
                </div>
                <div class="form-group">
                    <label>Location / Address</label>
                    <textarea name="location" rows="3" required placeholder="Project Address"></textarea>
                </div>
                <input type="hidden" name="action" value="partner_add_project">
                <button type="submit" class="btn-submit">Add Project</button>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();

        async function submitProject(e) {
            e.preventDefault();
            const btn = e.target.querySelector('button[type="submit"]');
            btn.innerHTML = 'Adding...';
            btn.disabled = true;

            const fd = new FormData(e.target);
            
            try {
                const res = await fetch('partner_api.php', { method: 'POST', body: fd });
                const data = await res.json();
                if(data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.error);
                }
            } catch(err) {
                alert('Connection error');
            } finally {
                btn.innerHTML = 'Add Project';
                btn.disabled = false;
            }
        }
    </script>
</body>
</html>
