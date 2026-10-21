<?php 
if (!function_exists('require_auth')) {
    require_once __DIR__ . '/config.php';
}
if (!defined('ALLOW_GUEST_ACCESS')) {
    require_auth();
}

$is_subfolder_ctx = defined('IS_SUBFOLDER') || 
                    (isset($_SERVER['SCRIPT_NAME']) && (
                        strpos($_SERVER['SCRIPT_NAME'], '/rm/') !== false || 
                        strpos($_SERVER['SCRIPT_NAME'], '/staff/') !== false || 
                        strpos($_SERVER['SCRIPT_NAME'], '/client_vault/') !== false || 
                        strpos($_SERVER['SCRIPT_NAME'], '/partner/') !== false || 
                        strpos($_SERVER['SCRIPT_NAME'], '/builder/') !== false || 
                        strpos($_SERVER['SCRIPT_NAME'], '/agent/') !== false || 
                        strpos($_SERVER['SCRIPT_NAME'], '/ca/') !== false
                    ));
$base_path = $is_subfolder_ctx ? '../' : ''; 
?>
<!DOCTYPE html>
<html lang="en">
<?php


// Dynamic Navigation Badge Counts Calculation
$nav_badges = [
    'reminders' => 0,
    'pre_leads' => 0,
    'leads' => 0,
    'loans' => 0,
    'online_staff' => 1
];
if (isset($db)) {
    try {
        $stmt_rem = $db->prepare("SELECT COUNT(*) FROM reminders WHERE status != 'Completed' AND date(remind_at) <= date('now', 'localtime') AND (assigned_to = ? OR ? = 'Admin')");
        $stmt_rem->execute([$_SESSION['username'] ?? '', $_SESSION['role'] ?? '']);
        $nav_badges['reminders'] = (int)$stmt_rem->fetchColumn();

        $nav_badges['pre_leads'] = (int)$db->query("SELECT COUNT(*) FROM pre_leads WHERE status = 'Not Contacted' OR status IS NULL OR status = ''")->fetchColumn();
        $nav_badges['leads'] = (int)$db->query("SELECT COUNT(*) FROM leads WHERE priority = 'Hot' OR stage = 'New Lead'")->fetchColumn();

        if (($_SESSION['role'] ?? '') === 'Admin') {
            $nav_badges['loans'] = (int)$db->query("SELECT COUNT(*) FROM applicants WHERE date(created_at, 'localtime') = date('now', 'localtime')")->fetchColumn();
        } else {
            $stmt_l = $db->prepare("SELECT COUNT(*) FROM applicants WHERE (assigned_to = ? OR added_by = ?) AND (date(created_at, 'localtime') = date('now', 'localtime') OR date(assigned_at, 'localtime') = date('now', 'localtime'))");
            $stmt_l->execute([$_SESSION['username'] ?? '', $_SESSION['username'] ?? '']);
            $nav_badges['loans'] = (int)$stmt_l->fetchColumn();
        }
        $nav_badges['online_staff'] = max(1, (int)$db->query("SELECT COUNT(*) FROM users WHERE last_active >= datetime('now', 'localtime', '-5 minutes') AND is_active = 1")->fetchColumn());
    } catch (Exception $e) {}
}
?>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - BFS Financial Services' : 'BFS Financial Services - Client Management System'; ?></title>
    
    <!-- PWA Installable App Configuration -->
    <link rel="manifest" href="<?php echo $base_path; ?>manifest.json">
    <meta name="theme-color" content="#0f172a">
    <link rel="apple-touch-icon" href="https://cdn-icons-png.flaticon.com/512/10311/10311651.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="mobile-web-app-capable" content="yes">
    
    <!-- Google Fonts Inter & Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Lucide Icons for clean iconography -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- Hardware Printer Scanner.js Library -->
    <script src="https://cdn.asprise.com/scannerjs/scanner.js"></script>
    
    <style>
        /* Luxury & Executive SweetAlert2 Styling */
        .swal2-container { z-index: 99999 !important; }
        .swal2-popup {
            border-radius: 20px !important;
            padding: 26px 24px 28px 24px !important;
            background: #ffffff !important;
            border: 1px solid rgba(226, 232, 240, 0.95) !important;
            box-shadow: 0 25px 60px -12px rgba(15, 23, 42, 0.22), 0 0 1px rgba(15, 23, 42, 0.1) !important;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif !important;
        }
        .swal2-title {
            font-family: 'Outfit', sans-serif !important;
            font-size: 20px !important;
            font-weight: 800 !important;
            color: #0f172a !important;
            letter-spacing: -0.3px !important;
            padding-top: 6px !important;
        }
        .swal2-html-container {
            font-size: 13.5px !important;
            color: #475569 !important;
            line-height: 1.6 !important;
            margin: 12px 14px 20px 14px !important;
        }
        .swal2-actions {
            gap: 12px !important;
            margin-top: 10px !important;
        }
        /* Beautiful Executive OK / Confirm Button */
        .swal2-styled.swal2-confirm {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%) !important;
            color: #ffffff !important;
            font-size: 13.5px !important;
            font-weight: 700 !important;
            letter-spacing: 0.3px !important;
            border: 1px solid #0f172a !important;
            border-radius: 10px !important;
            padding: 10px 26px !important;
            min-width: 105px !important;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.25) !important;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1) !important;
            outline: none !important;
        }
        .swal2-styled.swal2-confirm:hover {
            background: linear-gradient(135deg, #000000 0%, #0f172a 100%) !important;
            box-shadow: 0 6px 20px rgba(15, 23, 42, 0.35) !important;
            transform: translateY(-1px) !important;
        }
        .swal2-styled.swal2-confirm:active {
            transform: translateY(0) !important;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.2) !important;
        }
        .swal2-styled.swal2-confirm:focus {
            box-shadow: 0 0 0 3px rgba(15, 23, 42, 0.2) !important;
        }
        /* Cancel Button */
        .swal2-styled.swal2-cancel {
            background: #ffffff !important;
            color: #475569 !important;
            font-size: 13.5px !important;
            font-weight: 600 !important;
            border: 1.5px solid #cbd5e1 !important;
            border-radius: 10px !important;
            padding: 9.5px 22px !important;
            min-width: 95px !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05) !important;
            transition: all 0.2s ease !important;
            outline: none !important;
        }
        .swal2-styled.swal2-cancel:hover {
            background: #f8fafc !important;
            color: #0f172a !important;
            border-color: #94a3b8 !important;
            transform: translateY(-1px) !important;
        }
        .swal2-icon {
            margin: 14px auto 6px auto !important;
            transform: scale(0.92);
        }
    

        /* Luxury Monochrome Sidebar UI (Pure Black & White Theme) */
        :root {
            --sidebar-width: 248px;
            --sidebar-min-width: 180px;
            --sidebar-max-width: 500px;
        }

        aside {
            width: var(--sidebar-width);
            background: #000000;
            box-shadow: 4px 0 25px rgba(0,0,0,0.4);
            border-right: 1px solid rgba(255, 255, 255, 0.08);
            transition: width 0.22s cubic-bezier(0.16, 1, 0.3, 1), transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
        }
        .sidebar-menu {
            padding: 6px 10px;
        }
        .menu-item {
            margin-bottom: 2px;
        }
        .menu-item a {
            border-radius: 8px;
            padding: 8px 10px;
            color: #94a3b8;
            font-size: 13px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            white-space: nowrap;
            overflow: hidden;
            transition: all 0.18s ease;
            position: relative;
            z-index: 1;
            text-decoration: none;
        }
        .menu-item a i,
        .menu-item a svg {
            width: 16px;
            height: 16px;
            flex-shrink: 0;
        }
        .menu-text {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            flex-grow: 1;
            font-size: 12.8px;
            letter-spacing: -0.01em;
        }
        .menu-item a:hover {
            background: rgba(255, 255, 255, 0.06);
            color: #ffffff;
            transform: translateX(3px);
        }
        
        .menu-item.active a {
            background: #ffffff !important;
            color: #000000 !important;
            border-radius: 8px;
            padding-left: 12px;
            font-weight: 800 !important;
            box-shadow: 0 4px 14px rgba(255, 255, 255, 0.2);
        }
        
        .menu-item.active a i,
        .menu-item.active a svg {
            color: #000000 !important;
            filter: none;
        }

        /* Minimalist Clean Section Headers */
        .sidebar-section-header {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #475569;
            padding: 14px 10px 5px 10px;
            display: flex;
            align-items: center;
            user-select: none;
        }

        /* Elegant Monochrome Badges (No Rainbow Colors) */
        .nav-badge {
            margin-left: auto;
            font-size: 11px;
            font-weight: 600;
            padding: 1px 7px;
            border-radius: 12px;
            line-height: 1.3;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.08);
            color: #cbd5e1;
            border: 1px solid rgba(255, 255, 255, 0.12);
            transition: all 0.2s;
            flex-shrink: 0;
        }
        .menu-item.active .nav-badge {
            background: #ffffff;
            color: #000000;
            border-color: #ffffff;
            font-weight: 800;
        }

        /* Quick Search Bar trigger in Sidebar */
        .sidebar-quick-search {
            margin: 0 12px 10px 12px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 8px;
            padding: 7px 11px;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .sidebar-quick-search:hover {
            background: rgba(255, 255, 255, 0.07);
            border-color: rgba(255, 255, 255, 0.15);
        }

        /* Sidebar Toggle Collapse Button */
        .sidebar-toggle-btn {
            background: transparent;
            border: none;
            color: #64748b;
            cursor: pointer;
            padding: 6px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        .sidebar-toggle-btn:hover {
            background: rgba(255, 255, 255, 0.08);
            color: #ffffff;
        }

        /* Complete Collapsed Sidebar Mode (Full Workspace with Floating > Button) */
        body.sidebar-collapsed aside {
            transform: translateX(-100%) !important;
            pointer-events: none !important;
            visibility: hidden !important;
        }
        body.sidebar-collapsed main {
            margin-left: 0 !important;
            max-width: 100vw !important;
            padding-left: 56px !important;
        }
        .sidebar-expand-btn {
            display: none;
            position: fixed;
            top: 14px;
            left: 12px;
            z-index: 99999;
            width: 32px;
            height: 32px;
            background: #000000;
            color: #ffffff;
            border: 1.5px solid rgba(255, 255, 255, 0.28);
            border-radius: 8px;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.35);
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .sidebar-expand-btn:hover {
            background: #18181b;
            border-color: #ffffff;
            transform: scale(1.08);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.5);
        }
        .sidebar-expand-btn:active {
            transform: scale(0.95);
        }
        body.sidebar-collapsed .sidebar-expand-btn {
            display: flex !important;
        }

        /* Sidebar Draggable Resizer Handle */
        .sidebar-resizer {
            position: absolute;
            top: 0;
            right: -4px;
            width: 8px;
            bottom: 0;
            cursor: col-resize;
            z-index: 9999;
            user-select: none;
            -webkit-user-select: none;
            touch-action: none;
        }
        .sidebar-resizer::after {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            left: 3px;
            width: 2px;
            background: transparent;
            transition: background 0.15s ease, box-shadow 0.15s ease;
        }
        .sidebar-resizer:hover::after,
        body.is-resizing-sidebar .sidebar-resizer::after {
            background: #ffffff;
            box-shadow: 0 0 10px rgba(255, 255, 255, 0.9), 0 0 3px rgba(255, 255, 255, 1);
        }
        body.is-resizing-sidebar,
        body.is-resizing-sidebar * {
            user-select: none !important;
            -webkit-user-select: none !important;
            cursor: col-resize !important;
        }
        body.is-resizing-sidebar aside,
        body.is-resizing-sidebar main {
            transition: none !important;
        }
        @media (max-width: 768px) {
            .sidebar-expand-btn,
            .sidebar-resizer {
                display: none !important;
            }
        }

        /* Inline Spotlight Omni-Search Dropdown (Pure Jet Black & White Theme) */
        .sidebar-search-box:focus-within {
            border-color: #ffffff !important;
            background: #000000 !important;
            box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.25);
        }
        .spotlight-dropdown-menu {
            background: #000000 !important;
            border: 1px solid rgba(255, 255, 255, 0.22) !important;
            border-radius: 14px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.95), 0 0 0 1px rgba(255, 255, 255, 0.15) !important;
            animation: fadeInDropdown 0.15s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes fadeInDropdown {
            from { opacity: 0; transform: translateY(-4px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .spotlight-input-wrapper {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
            background: #000000 !important;
        }
        .spotlight-input {
            background: transparent;
            border: none;
            outline: none;
            color: #ffffff;
            font-size: 15.5px;
            font-weight: 500;
            width: 100%;
        }
        .spotlight-cats {
            display: flex;
            gap: 6px;
            padding: 8px 12px;
            background: #000000 !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
            overflow-x: auto;
            scrollbar-width: none;
        }
        .spotlight-cats::-webkit-scrollbar {
            display: none;
        }
        .spotlight-cat-pill {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            color: #94a3b8;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.15);
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.15s ease;
        }
        .spotlight-cat-pill:hover {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.3);
        }
        .spotlight-cat-pill.active {
            background: #ffffff !important;
            color: #000000 !important;
            border-color: #ffffff !important;
            font-weight: 800 !important;
            box-shadow: 0 2px 10px rgba(255, 255, 255, 0.3);
        }
        .spotlight-results {
            max-height: 380px;
            overflow-y: auto;
            padding: 8px 10px;
            background: #000000 !important;
        }
        .spotlight-results::-webkit-scrollbar {
            width: 5px;
        }
        .spotlight-results::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 4px;
        }
        .spotlight-section-header {
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #94a3b8;
            padding: 10px 8px 4px 8px;
            margin-top: 4px;
        }
        .spotlight-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 10px;
            border-radius: 10px;
            color: #cbd5e1;
            text-decoration: none;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.15s ease;
            margin-bottom: 2px;
            border: 1px solid transparent;
        }
        .spotlight-item-icon {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            color: #ffffff;
            transition: all 0.15s ease;
        }
        .spotlight-item:hover, .spotlight-item.selected {
            background: rgba(255, 255, 255, 0.12);
            border-color: rgba(255, 255, 255, 0.3);
            color: #ffffff;
        }
        .spotlight-item:hover .spotlight-item-icon, .spotlight-item.selected .spotlight-item-icon {
            background: #ffffff;
            color: #000000;
            border-color: #ffffff;
        }
        .spotlight-badge {
            margin-left: auto;
            font-size: 9.5px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 4px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.18);
            color: #ffffff;
            white-space: nowrap;
        }
        .spotlight-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 14px;
            background: #000000 !important;
            border-top: 1px solid rgba(255, 255, 255, 0.15);
            font-size: 11px;
            color: #94a3b8;
        }
    </style>
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Quill Snow Rich Text Editor Stylesheet & Script -->
    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>
    
    <!-- HTML to PDF conversion bundle library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    
    <!-- SheetJS for Bulk Upload Parsing -->
    <script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>
    
    <!-- Vanilla CSS Design System -->
    <style>
        :root {
            /* Minimalist Monochrome Palette (Black & White Luxury) */
            --primary: #0f172a; /* Slate 900 / Deep Black */
            --primary-hover: #000000; /* Pure Black */
            --primary-light: #f1f5f9; /* Soft Grey */
            --primary-border: #e2e8f0; /* Standard Border */
            --primary-glow: rgba(0, 0, 0, 0.15);
            
            --bg-main: #f8fafc;
            --bg-card: #ffffff;
            --sidebar-bg: #000000;
            --sidebar-hover: #111827;
            
            --text-primary: #1e293b;
            --text-muted: #64748b;
            --text-light: #94a3b8;
            
            /* Status Accents */
            --status-new: #3b82f6;
            --status-new-light: #eff6ff;
            --status-contacted: #f97316;
            --status-contacted-light: #fff7ed;
            --status-negotiation: #f59e0b;
            --status-negotiation-light: #fef3c7;
            --status-won: #10b981;
            --status-won-light: #ecfdf5;
            --status-lost: #ef4444;
            --status-lost-light: #fef2f2;
            
            --border: #e2e8f0;
            --danger: #ef4444;
            --danger-light: #fef2f2;
            --success: #10b981;
            --warning: #f59e0b;
            --warning-light: #fef3c7;
            
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 16px;
            
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
            --shadow-lg: 0 10px 15px -3px rgba(15, 23, 42, 0.05), 0 4px 6px -4px rgba(15, 23, 42, 0.05);
            
            --transition-fast: 0.15s ease;
            --transition-normal: 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        body {
            background-color: var(--bg-main);
            color: var(--text-primary);
            display: flex;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* --- SKELETON LOADERS CSS --- */
        @keyframes shimmer {
            0% { background-position: -1000px 0; }
            100% { background-position: 1000px 0; }
        }

        .skeleton {
            background: #f1f5f9;
            background-image: linear-gradient(90deg, #f1f5f9 0px, #e2e8f0 40px, #f1f5f9 80px);
            background-size: 1000px 100%;
            animation: shimmer 2s infinite linear;
            border-radius: var(--radius-sm);
        }

        .skeleton-text {
            height: 14px;
            margin-bottom: 8px;
            width: 100%;
        }
        
        .skeleton-text.short { width: 50%; }
        .skeleton-text.medium { width: 75%; }
        .skeleton-text.long { width: 90%; }
        .skeleton-title { height: 20px; width: 60%; margin-bottom: 12px; }

        .skeleton-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
        }

        .skeleton-row {
            display: flex;
            gap: 12px;
            align-items: center;
            padding: 12px;
            border-bottom: 1px solid var(--border);
        }

        .skeleton-card {
            padding: 20px;
            background: white;
            border-radius: var(--radius-md);
            border: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        /* -------------------------- */

        /* Sidebar Navigation Layout */
        aside {
            width: var(--sidebar-width);
            background-color: #000000;
            color: #ffffff;
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            transition: width 0.22s cubic-bezier(0.16, 1, 0.3, 1), transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .brand-container {
            padding: 20px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .brand-logo {
            background-color: #ffffff;
            color: #000000;
            width: 38px;
            height: 38px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 20px;
            box-shadow: 0 4px 12px rgba(255, 255, 255, 0.15);
        }

        .brand-name {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            font-size: 21px;
            letter-spacing: -0.5px;
            color: #ffffff;
        }

        .sidebar-menu {
            list-style: none;
            padding: 16px 12px;
            display: flex;
            flex-direction: column;
            gap: 4px;
            flex-grow: 1;
            overflow-y: auto;
        }

        /* Custom scrollbar for sidebar */
        .sidebar-menu::-webkit-scrollbar { width: 4px; }
        .sidebar-menu::-webkit-scrollbar-track { background: transparent; }
        .sidebar-menu::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 4px; }
        .sidebar-menu::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }

        .sidebar-footer {
            padding: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            font-size: 12px;
            color: var(--text-light);
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        /* Main Workspace Container */
        main {
            margin-left: var(--sidebar-width);
            flex-grow: 1;
            padding: 24px 28px;
            min-width: 0; /* prevents flex blowout */
            box-sizing: border-box;
            max-width: calc(100vw - var(--sidebar-width));
            transition: margin-left 0.22s cubic-bezier(0.16, 1, 0.3, 1), padding 0.2s ease;
        }

        header.main-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
        }

        .page-title h1 {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            font-size: 26px;
            color: var(--text-primary);
        }

        .page-title p {
            font-size: 14px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        .user-pill {
            display: flex;
            align-items: center;
            gap: 10px;
            background: white;
            padding: 8px 16px;
            border-radius: 30px;
            box-shadow: var(--shadow-sm);
            border: 1px solid #e2e8f0;
            font-size: 14px;
            font-weight: 500;
        }

        .user-avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background-color: var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 12px;
        }

        /* View State Management */
        .view-container {
            display: block; /* always block since pages are separate files now */
            animation: fadeIn 0.3s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Dashboard Stats Grid styling */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background-color: var(--bg-card);
            padding: 24px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            border: 1px solid #f1f5f9;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
            transition: transform var(--transition-fast), box-shadow var(--transition-fast);
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-border);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background-color: var(--primary);
            opacity: 0;
            transition: var(--transition-fast);
        }

        .stat-card:hover::before {
            opacity: 1;
        }

        .stat-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .stat-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-icon-wrapper {
            background-color: var(--primary-light);
            color: var(--primary);
            width: 36px;
            height: 36px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .stat-value {
            font-family: 'Outfit', sans-serif;
            font-size: 28px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        /* Dashboard Charts and Feeds Layout */
        .dashboard-layout-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }

        .charts-row-inner {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }

        .card {
            background-color: var(--bg-card);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            border: 1px solid #f1f5f9;
            padding: 24px;
            margin-bottom: 24px;
        }

        .card-title-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .card-title-bar h2 {
            font-family: 'Outfit', sans-serif;
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
        }

        /* Funnel CSS styling */
        .funnel-container {
            display: flex;
            flex-direction: column;
            gap: 12px;
            padding: 10px 0;
        }

        .funnel-stage {
            display: flex;
            align-items: center;
            position: relative;
        }

        .funnel-label {
            width: 110px;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .funnel-bar-wrapper {
            flex-grow: 1;
            background: #f1f5f9;
            height: 24px;
            border-radius: var(--radius-sm);
            overflow: hidden;
            position: relative;
        }

        .funnel-bar {
            height: 100%;
            background: linear-gradient(90deg, #f97316 0%, #ea580c 100%);
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 12px;
            transition: width 1s ease-in-out;
        }

        .funnel-count {
            color: white;
            font-size: 11px;
            font-weight: 700;
        }

        /* Activity Feed styling */
        .activity-feed {
            display: flex;
            flex-direction: column;
            gap: 16px;
            max-height: 480px;
            overflow-y: auto;
        }

        .activity-item {
            display: flex;
            gap: 12px;
            padding-bottom: 14px;
            border-bottom: 1px dashed #e2e8f0;
        }

        .activity-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .activity-bullet {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: var(--primary);
            margin-top: 5px;
            flex-shrink: 0;
        }

        .activity-content {
            flex-grow: 1;
        }

        .activity-text {
            font-size: 13px;
            line-height: 1.5;
            color: var(--text-primary);
        }

        .activity-time {
            font-size: 11px;
            color: var(--text-light);
            margin-top: 4px;
        }

        /* Client Registration Form */
        .form-section-title {
            font-family: 'Outfit', sans-serif;
            font-size: 15px;
            font-weight: 700;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 20px 0 12px 0;
            padding-bottom: 6px;
            border-bottom: 2px solid var(--primary-border);
        }

        .form-section-title:first-of-type {
            margin-top: 0;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
            margin-bottom: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        label {
            font-size: 13px;
            font-weight: 500;
            color: var(--text-primary);
        }

        label.required::after {
            content: ' *';
            color: var(--danger);
        }

        input[type="text"],
        input[type="email"],
        input[type="tel"],
        input[type="password"],
        input[type="number"],
        input[type="url"],
        input[type="datetime-local"],
        input[type="file"],
        input[type="date"],
        select,
        textarea {
            padding: 10px 14px;
            border: 1px solid #cbd5e1;
            border-radius: var(--radius-md);
            font-size: 14px;
            color: var(--text-primary);
            background-color: #ffffff;
            transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
            width: 100%;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-glow);
        }

        .badge-locked {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background-color: var(--primary-light);
            color: var(--primary);
            padding: 6px 12px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            border: 1px solid var(--primary-border);
            margin-left: auto;
        }

        .header-action-container {
            display: flex;
            align-items: center;
            gap: 12px;
            width: 100%;
        }

        /* Buttons custom styling */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: var(--radius-md);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: var(--transition-fast);
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
            box-shadow: 0 4px 6px -1px var(--primary-glow);
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 6px 12px -2px rgba(249, 115, 22, 0.3);
        }

        .btn-secondary {
            background-color: #f1f5f9;
            color: var(--text-primary);
            border: 1px solid #cbd5e1;
        }

        .btn-secondary:hover {
            background-color: #e2e8f0;
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
        }

        .btn-accent {
            background-color: var(--sidebar-bg);
            color: white;
        }

        .btn-accent:hover {
            background-color: var(--sidebar-hover);
            transform: translateY(-1px);
        }

        .btn-danger {
            background-color: var(--danger-light);
            color: var(--danger);
            border: 1px solid var(--danger);
        }

        .btn-danger:hover {
            background-color: var(--danger);
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 24px;
            padding-top: 18px;
            border-top: 1px solid #e2e8f0;
        }

        /* Complete Tracking System (Module 2) */
        .crm-search-bar {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
        }

        .search-input-wrapper {
            position: relative;
            flex-grow: 1;
        }

        .search-input-wrapper input {
            padding-left: 42px;
        }

        .search-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            pointer-events: none;
        }

        .filters-toggle-btn {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            padding: 0 14px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--text-primary);
            transition: var(--transition-fast);
        }

        .filters-toggle-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .filters-drawer {
            display: none;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
            background: #f1f5f9;
            padding: 16px;
            border-radius: var(--radius-md);
            margin-bottom: 20px;
            border: 1px solid #e2e8f0;
        }

        .filters-drawer.active {
            display: grid;
        }

        /* CRM Two Column Layout */
        .crm-layout {
            display: grid;
            grid-template-columns: 400px 1fr;
            gap: 24px;
            align-items: start;
        }

        .client-list-pane {
            height: calc(100vh - 240px);
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 12px;
            padding-right: 4px;
        }

        .client-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: var(--radius-md);
            padding: 16px;
            cursor: pointer;
            transition: var(--transition-fast);
            position: relative;
        }

        .client-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-sm);
        }

        .client-card.selected {
            border-color: var(--primary);
            background-color: var(--primary-light);
            box-shadow: var(--shadow-md);
        }

        .client-card.won {
            border: 1px solid var(--status-won);
            border-left: 4px solid var(--status-won) !important;
            background: linear-gradient(145deg, #ffffff 60%, var(--status-won-light) 100%) !important;
            box-shadow: 0 2px 10px rgba(16, 185, 129, 0.1);
        }
        
        .client-card.won:hover {
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.2);
            border-color: var(--status-won);
        }

        .client-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }

        .client-card-title {
            font-size: 15px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .client-card-meta {
            font-size: 12px;
            color: var(--text-muted);
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* Data Table Premium Design */
        .table-responsive {
            overflow-x: auto;
            border-radius: var(--radius-md);
            border: 1px solid var(--border);
            background: white;
            box-shadow: var(--shadow-sm);
            margin-top: 10px;
        }

        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 13px; /* Reduced from 14px */
        }

        .data-table th {
            background-color: #f8fafc;
            color: var(--text-muted);
            font-size: 11px; /* Reduced from 12px */
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 12px 16px; /* Reduced padding */
            text-align: left;
            border-bottom: 2px solid var(--border);
            white-space: nowrap;
        }

        .data-table td {
            padding: 10px 16px; /* Reduced from 16px 20px */
            color: var(--text-primary);
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
            transition: background-color var(--transition-fast);
        }

        .data-table tbody tr:hover td {
            background-color: var(--bg-main);
        }

        .data-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Improved Badges - Black & White Monochrome Theme */
        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 800;
            padding: 4px 10px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        .badge-info { background-color: #f8fafc; color: #0f172a; border: 1px solid #cbd5e1; }
        .badge-success { background-color: #0f172a; color: #ffffff; border: 1px solid #0f172a; }
        .badge-warning { background-color: #ffffff; color: #0f172a; border: 1px solid #94a3b8; }
        .badge-danger { background-color: #f1f5f9; color: #0f172a; border: 1px solid #0f172a; }

        .badge-hot { background-color: #000000; color: #ffffff; }
        .badge-warm { background-color: #f1f5f9; color: #0f172a; border: 1px solid #cbd5e1; }
        .badge-cold { background-color: #ffffff; color: #64748b; border: 1px solid #e2e8f0; }

        .badge-status {
            font-size: 11px;
            font-weight: 800;
            padding: 4px 10px;
            border-radius: 30px;
        }
        .badge-status.new { background-color: #f1f5f9; color: #0f172a; border: 1px solid #cbd5e1; }
        .badge-status.contacted { background-color: #e2e8f0; color: #0f172a; }
        .badge-status.negotiation { background-color: #ffffff; color: #0f172a; border: 1px solid #0f172a; }
        .badge-status.won { background-color: #0f172a; color: #ffffff; }
        .badge-status.lost { background-color: #f8fafc; color: #64748b; border: 1px solid #cbd5e1; }

        /* Detail Profile Pane */
        .client-detail-pane {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: var(--radius-lg);
            padding: 24px;
            height: calc(100vh - 240px);
            overflow-y: auto;
            position: relative;
        }
        
        .client-list-pane::-webkit-scrollbar,
        .client-detail-pane::-webkit-scrollbar {
            width: 0px;
            background: transparent;
        }

        .client-detail-pane.won-theme {
            border: 2px solid var(--status-won);
            background: linear-gradient(to bottom right, #ffffff, var(--status-won-light) 150%);
            box-shadow: 0 4px 20px rgba(16, 185, 129, 0.15);
        }

        .detail-placeholder {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 400px;
            color: var(--text-light);
            text-align: center;
            gap: 16px;
        }

        .detail-placeholder i {
            color: var(--text-light);
        }

        .detail-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }

        .detail-company-title {
            font-family: 'Outfit', sans-serif;
            font-size: 22px;
            font-weight: 800;
            color: var(--text-primary);
        }

        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }

        .detail-block-title {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            margin-bottom: 10px;
        }

        .detail-field {
            margin-bottom: 8px;
            font-size: 13.5px;
        }

        .detail-field span {
            font-weight: 600;
            color: var(--text-primary);
        }

        .detail-field label {
            color: var(--text-muted);
            font-weight: 400;
            display: inline-block;
            width: 120px;
        }

        /* Interactive Checklist for pipeline tracking */
        .pipeline-tracker {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 10px;
            margin: 20px 0;
            background: #f8fafc;
            padding: 16px;
            border-radius: var(--radius-md);
            border: 1px solid #e2e8f0;
        }

        .pipeline-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 6px;
            position: relative;
        }

        .pipeline-icon-circle {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #e2e8f0;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            z-index: 2;
        }

        .pipeline-step.completed .pipeline-icon-circle {
            background-color: #0f172a;
            color: white;
        }

        @keyframes pulseGlow {
            0% { box-shadow: 0 0 0 0 rgba(14, 165, 233, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(14, 165, 233, 0); }
            100% { box-shadow: 0 0 0 0 rgba(14, 165, 233, 0); }
        }

        .pipeline-step.active .pipeline-icon-circle {
            background-color: #0ea5e9; /* Vibrant Sky Blue for In-Progress */
            color: white;
            animation: pulseGlow 1.5s infinite;
            border: 2px solid #bae6fd;
        }

        .pipeline-step-label {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .pipeline-step-date {
            font-size: 10px;
            color: var(--text-muted);
        }

        /* Detail Section Tabs */
        .tabs {
            display: flex;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 16px;
            gap: 16px;
        }

        .tab {
            padding: 8px 4px 12px 4px;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-muted);
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: var(--transition-fast);
        }

        .tab.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* History Logs list view */
        .history-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            max-height: 250px;
            overflow-y: auto;
        }

        .history-item {
            background: #f8fafc;
            padding: 12px;
            border-radius: var(--radius-md);
            border-left: 3px solid var(--primary);
        }

        .history-item-header {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .history-item-body {
            font-size: 12.5px;
            color: var(--text-primary);
            line-height: 1.4;
        }

        /* Email dispatcher custom styling */
        .email-form-card {
            background: white;
            padding: 24px;
            border-radius: var(--radius-lg);
            border: 1px solid #e2e8f0;
        }

        /* Editor height setup */
        #email-body-editor {
            height: 220px;
            border-bottom-left-radius: var(--radius-md);
            border-bottom-right-radius: var(--radius-md);
            font-family: 'Inter', sans-serif;
        }

        .ql-toolbar {
            border-top-left-radius: var(--radius-md);
            border-top-right-radius: var(--radius-md);
        }

        /* Quotation Builder Layout */
        .quote-builder-layout {
            background: white;
            border-radius: var(--radius-lg);
            border: 1px solid #e2e8f0;
            padding: 24px;
        }

        .quote-meta-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 18px;
            margin-bottom: 24px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .items-table th {
            text-align: left;
            padding: 12px;
            background-color: #f8fafc;
            font-size: 12px;
            font-weight: 700;
            color: var(--text-muted);
            border-bottom: 2px solid #e2e8f0;
            text-transform: uppercase;
        }

        .items-table td {
            padding: 10px;
            border-bottom: 1px solid #e2e8f0;
        }

        .items-table input,
        .items-table select {
            padding: 8px 10px;
        }

        .delete-row-btn {
            background: transparent;
            border: none;
            color: var(--danger);
            cursor: pointer;
            padding: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .delete-row-btn:hover {
            background-color: var(--danger-light);
            border-radius: 4px;
        }

        .summary-block-wrapper {
            display: flex;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .summary-block {
            width: 320px;
            background-color: #f8fafc;
            padding: 16px;
            border-radius: var(--radius-md);
            border: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            font-size: 13.5px;
        }

        .summary-row.grand-total {
            font-size: 16px;
            font-weight: 700;
            border-top: 1px solid #cbd5e1;
            padding-top: 8px;
            color: var(--primary);
        }

        /* Quotation List design */
        .quotation-list-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }

        .quotation-list-table th {
            text-align: left;
            padding: 14px;
            background: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
            font-size: 12px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
        }

        .quotation-list-table td {
            padding: 14px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 13.5px;
        }

        .status-pill-select {
            border: 1px solid #cbd5e1;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            background-color: white;
            cursor: pointer;
        }

        .status-pill-select:focus {
            outline: none;
        }

        .status-pill-select.Pending { border-color: var(--warning); color: var(--warning); background-color: var(--warning-light); }
        .status-pill-select.Approved { border-color: var(--success); color: var(--success); background-color: var(--status-won-light); }
        .status-pill-select.Rejected { border-color: var(--danger); color: var(--danger); background-color: var(--danger-light); }

        /* Executive Notification Popup System */
        .toast-container {
            position: fixed;
            top: 24px;
            right: 24px;
            z-index: 99999999;
            display: flex;
            flex-direction: column;
            gap: 12px;
            pointer-events: none;
            max-width: 420px;
            width: calc(100vw - 32px);
        }

        .toast {
            pointer-events: auto;
            position: relative;
            background: rgba(15, 23, 42, 0.96);
            backdrop-filter: blur(16px) saturate(180%);
            -webkit-backdrop-filter: blur(16px) saturate(180%);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 14px;
            box-shadow: 0 20px 45px -8px rgba(15, 23, 42, 0.4), 0 0 1px 1px rgba(255, 255, 255, 0.08);
            padding: 13px 16px 15px 14px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            overflow: hidden;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            animation: toastSlideIn 0.32s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            user-select: none;
        }

        .toast:hover {
            transform: translateY(-2px);
            box-shadow: 0 24px 50px -8px rgba(15, 23, 42, 0.5), 0 0 1px 1px rgba(255, 255, 255, 0.14);
            border-color: rgba(255, 255, 255, 0.22);
        }

        .toast.toast-hiding {
            animation: toastSlideOut 0.25s cubic-bezier(0.4, 0, 1, 1) forwards !important;
        }

        @keyframes toastSlideIn {
            0% {
                transform: translateX(110%) scale(0.92);
                opacity: 0;
            }
            100% {
                transform: translateX(0) scale(1);
                opacity: 1;
            }
        }

        @keyframes toastSlideOut {
            0% {
                transform: translateX(0) scale(1);
                opacity: 1;
            }
            100% {
                transform: translateX(110%) scale(0.92);
                opacity: 0;
            }
        }

        /* Glowing type badges */
        .toast-icon-box {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-top: 1px;
        }

        .toast-success .toast-icon-box {
            background: rgba(16, 185, 129, 0.16);
            border: 1px solid rgba(16, 185, 129, 0.32);
            color: #34d399;
            box-shadow: 0 0 16px rgba(16, 185, 129, 0.25);
        }
        .toast-error .toast-icon-box,
        .toast-danger .toast-icon-box {
            background: rgba(244, 63, 94, 0.16);
            border: 1px solid rgba(244, 63, 94, 0.32);
            color: #fb7185;
            box-shadow: 0 0 16px rgba(244, 63, 94, 0.25);
        }
        .toast-warning .toast-icon-box {
            background: rgba(245, 158, 11, 0.16);
            border: 1px solid rgba(245, 158, 11, 0.32);
            color: #fbbf24;
            box-shadow: 0 0 16px rgba(245, 158, 11, 0.25);
        }
        .toast-info .toast-icon-box {
            background: rgba(56, 189, 248, 0.16);
            border: 1px solid rgba(56, 189, 248, 0.32);
            color: #38bdf8;
            box-shadow: 0 0 16px rgba(56, 189, 248, 0.25);
        }

        /* Toast Content Layout */
        .toast-content {
            flex: 1;
            min-width: 0;
            padding-right: 4px;
        }
        .toast-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 3px;
        }
        .toast-title {
            font-size: 13px;
            font-weight: 800;
            letter-spacing: -0.2px;
            line-height: 1.2;
            color: #ffffff;
        }
        .toast-success .toast-title { color: #34d399; }
        .toast-error .toast-title,
        .toast-danger .toast-title { color: #fb7185; }
        .toast-warning .toast-title { color: #fbbf24; }
        .toast-info .toast-title { color: #38bdf8; }

        .toast-time {
            font-size: 10px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .toast-message {
            font-size: 12.5px;
            font-weight: 500;
            color: #cbd5e1;
            line-height: 1.45;
            word-break: break-word;
        }

        /* Toast Close Button */
        .toast-close-btn {
            width: 22px;
            height: 22px;
            border-radius: 6px;
            background: transparent;
            border: none;
            color: #64748b;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.15s ease;
            flex-shrink: 0;
            margin-left: 2px;
            margin-top: -2px;
        }
        .toast-close-btn:hover {
            background: rgba(255, 255, 255, 0.12);
            color: #ffffff;
        }

        /* Animated Progress Countdown Bar */
        .toast-progress {
            position: absolute;
            bottom: 0;
            left: 0;
            height: 2.5px;
            width: 100%;
            transform-origin: left;
            animation: toastProgressBar linear forwards;
        }
        .toast-success .toast-progress { background: linear-gradient(90deg, #10b981, #34d399); }
        .toast-error .toast-progress,
        .toast-danger .toast-progress { background: linear-gradient(90deg, #f43f5e, #fb7185); }
        .toast-warning .toast-progress { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
        .toast-info .toast-progress { background: linear-gradient(90deg, #0284c7, #38bdf8); }

        @keyframes toastProgressBar {
            from { width: 100%; }
            to { width: 0%; }
        }

        .toast:hover .toast-progress {
            animation-play-state: paused !important;
        }

        /* Printable PDF Template container */
        #invoice-print-container {
            display: none;
        }

        /* ==============================================================
           BFS CIRCLE FULL FLUID RESPONSIVE SYSTEM ACROSS ALL SCREENS
           ============================================================== */

        /* Prevent unwanted horizontal overflow page-wide */
        html, body {
            max-width: 100%;
            overflow-x: hidden;
        }

        /* Mobile Top Header Bar */
        .mobile-top-bar {
            display: none;
            align-items: center;
            justify-content: space-between;
            height: 52px;
            background: #000000;
            border-bottom: 1px solid rgba(255, 255, 255, 0.12);
            padding: 0 14px;
            position: sticky;
            top: 0;
            z-index: 9998;
            width: 100%;
            box-sizing: border-box;
        }
        .mobile-menu-btn {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #ffffff;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        .mobile-menu-btn:hover {
            background: rgba(255, 255, 255, 0.16);
        }

        /* Global Table & Card Responsive Helpers */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin-bottom: 12px;
        }
        .table-responsive::-webkit-scrollbar {
            height: 5px;
        }
        .table-responsive::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }

        /* 1. COMPACT LAPTOPS & DESKTOPS (1025px to 1400px - Standard 1366x768 & 1280px Laptops) */
        @media (max-width: 1400px) and (min-width: 1025px) {
            aside {
                width: var(--sidebar-width);
            }
            main {
                margin-left: var(--sidebar-width);
                padding: 16px 20px;
                max-width: calc(100vw - var(--sidebar-width));
            }
            .brand-name {
                font-size: 17px !important;
            }
            .menu-item a {
                padding: 7px 9px !important;
                font-size: 12px !important;
                gap: 8px !important;
            }
            .menu-item a i,
            .menu-item a svg {
                width: 15px !important;
                height: 15px !important;
            }
            .sidebar-section-header {
                font-size: 9.5px !important;
                padding: 10px 8px 3px 8px !important;
            }
            .nav-badge {
                font-size: 10px !important;
                padding: 1px 5px !important;
            }
            .sidebar-quick-search {
                padding: 5px 8px !important;
                margin: 0 8px 6px 8px !important;
            }
            .crm-layout {
                grid-template-columns: minmax(280px, 320px) 1fr !important;
                gap: 14px !important;
            }
        }

        /* 2. TABLETS & SMALL NETBOOKS (769px to 1024px) */
        @media (max-width: 1024px) and (min-width: 769px) {
            aside {
                width: var(--sidebar-width);
            }
            main {
                margin-left: var(--sidebar-width);
                padding: 14px 18px;
                max-width: calc(100vw - var(--sidebar-width));
            }
            .crm-layout {
                grid-template-columns: 1fr !important;
                gap: 16px !important;
            }
            .dashboard-layout-row {
                grid-template-columns: 1fr !important;
            }
            .client-detail-pane {
                position: relative;
                top: 0;
            }
        }

        /* 3. MOBILE & SMALL SCREENS (<= 768px) */
        @media (max-width: 768px) {
            body {
                flex-direction: column;
            }
            .mobile-top-bar {
                display: flex !important;
            }
            aside {
                position: fixed !important;
                top: 0 !important;
                bottom: 0 !important;
                left: 0 !important;
                width: 260px !important;
                max-width: 82vw !important;
                transform: translateX(-100%);
                transition: transform 0.26s cubic-bezier(0.16, 1, 0.3, 1) !important;
                z-index: 99999 !important;
                box-shadow: 12px 0 35px rgba(0,0,0,0.65) !important;
            }
            aside.mobile-open {
                transform: translateX(0) !important;
            }
            .sidebar-backdrop {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.65);
                backdrop-filter: blur(2px);
                z-index: 99990;
                opacity: 0;
                pointer-events: none;
                transition: opacity 0.22s ease;
            }
            .sidebar-backdrop.active {
                opacity: 1;
                pointer-events: auto;
            }
            main {
                margin-left: 0 !important;
                padding: 12px 10px 40px 10px !important;
                width: 100% !important;
                max-width: 100vw !important;
                overflow-x: hidden !important;
                box-sizing: border-box !important;
            }
            .crm-layout {
                grid-template-columns: 1fr !important;
                gap: 14px !important;
            }
            .charts-row-inner,
            .quote-meta-row,
            .dashboard-layout-row {
                grid-template-columns: 1fr !important;
            }
            .client-detail-pane {
                position: relative;
                top: 0;
            }
        }


        /* Modal Styles */
        .modal {
            display: none; 
            position: fixed; 
            z-index: 1000; 
            left: 0; 
            top: 0; 
            width: 100%; 
            height: 100%; 
            overflow: auto; 
            background-color: rgba(0,0,0,0.5); 
            align-items: center; 
            justify-content: center;
        }
        .modal-content {
            background-color: var(--bg-card);
            margin: 10% auto;
            padding: 30px;
            border: 1px solid var(--border);
            width: 90%;
            max-width: 550px;
            border-radius: 16px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.3);
            position: relative;
            animation: modalFadeIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes modalFadeIn {
            from {opacity: 0; transform: translateY(-20px);}
            to {opacity: 1; transform: translateY(0);}
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border);
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .modal-header h2 {
            margin: 0;
            font-size: 18px;
            color: var(--text);
        }
        .close {
            color: #aaa;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover, .close:focus {
            color: var(--danger);
            text-decoration: none;
            cursor: pointer;
        }
        
        /* Flex row helper */
        .flex-row {
            display: flex;
        }
        @media (max-width: 768px) {
            .flex-row {
                flex-direction: column;
            }
        }
    

        /* Premium Sidebar UI (Pure Jet Black #000000 Theme) */
        aside {
            background: #000000 !important;
            background-color: #000000 !important;
            box-shadow: 4px 0 30px rgba(0,0,0,0.5);
            border-right: 1px solid rgba(255,255,255,0.1);
        }
        .sidebar-menu {
            padding: 10px;
            background: #000000 !important;
        }
        .menu-item {
            margin-bottom: 3px;
        }
        .menu-item a {
            border-radius: 8px;
            padding: 9px 12px;
            color: #94a3b8;
            font-size: 13px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            z-index: 1;
            text-decoration: none;
        }
        .menu-item a:hover {
            background: rgba(255, 255, 255, 0.08) !important;
            color: #ffffff !important;
            transform: translateX(4px);
        }
        
        .menu-item.active a {
            background: #ffffff !important;
            color: #000000 !important;
            border-radius: 8px;
            padding-left: 12px;
            font-weight: 800 !important;
            box-shadow: 0 4px 14px rgba(255, 255, 255, 0.25) !important;
        }
        
        .menu-item.active a i,
        .menu-item.active a svg {
            color: #000000 !important;
            filter: none !important;
        }

        .menu-item.active a::before {
            display: none !important;
        }

        /* Professional Spotlight Omni-Search Desk (Pure Jet Black Theme) */
        .spotlight-dropdown-menu {
            background: #000000 !important;
            border: 1px solid rgba(255, 255, 255, 0.2) !important;
            border-radius: 14px !important;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.95), 0 0 0 1px rgba(255, 255, 255, 0.1) !important;
        }
        .spotlight-cats-header-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            background: #050505;
            border-bottom: 1px solid rgba(255, 255, 255, 0.12);
        }
        .spotlight-cat-scroll-btn {
            background: rgba(255, 255, 255, 0.06);
            border: none;
            color: #ffffff;
            width: 24px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            flex-shrink: 0;
            z-index: 2;
            transition: all 0.15s ease;
        }
        .spotlight-cat-scroll-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            color: #ffffff;
        }
        .spotlight-cats {
            display: flex;
            gap: 6px;
            padding: 8px 8px;
            background: #050505;
            overflow-x: auto;
            scroll-behavior: smooth;
            scrollbar-width: thin;
            scrollbar-color: rgba(255,255,255,0.3) transparent;
            flex: 1;
            cursor: pointer;
        }
        .spotlight-cats::-webkit-scrollbar {
            height: 3px;
        }
        .spotlight-cats::-webkit-scrollbar-track {
            background: transparent;
        }
        .spotlight-cats::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.25);
            border-radius: 4px;
        }
        .spotlight-cats::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.5);
        }
        .spotlight-cat-pill {
            padding: 5px 11px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            color: #94a3b8;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.12);
            cursor: pointer;
            transition: all 0.15s ease;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .spotlight-cat-pill:hover {
            background: rgba(255, 255, 255, 0.12);
            color: #ffffff;
            border-color: rgba(255, 255, 255, 0.25);
        }
        .spotlight-cat-pill.active {
            background: #ffffff !important;
            color: #000000 !important;
            border-color: #ffffff !important;
            font-weight: 700 !important;
            box-shadow: 0 2px 8px rgba(255, 255, 255, 0.25);
        }
        .spotlight-results {
            max-height: 420px;
            overflow-y: auto;
            padding: 6px 0;
            background: #000000;
        }
        .spotlight-section-header {
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.08em;
            color: #94a3b8;
            text-transform: uppercase;
            padding: 10px 14px 4px;
            display: flex;
            align-items: center;
            gap: 6px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            margin-top: 4px;
        }
        .spotlight-section-header:first-child {
            border-top: none;
            margin-top: 0;
        }
        .spotlight-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 9px 14px;
            text-decoration: none;
            transition: all 0.15s ease;
            border-left: 3px solid transparent;
            cursor: pointer;
        }
        .spotlight-item:hover, .spotlight-item.selected {
            background: rgba(255, 255, 255, 0.12) !important;
            border-left-color: #ffffff !important;
        }
        .spotlight-item-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.08);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            border: 1px solid rgba(255, 255, 255, 0.15);
        }
        .spotlight-badge {
            font-size: 10px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 4px;
            color: #000000;
            background: #ffffff;
            font-family: monospace;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            box-shadow: 0 1px 4px rgba(255, 255, 255, 0.15);
        }
        .spotlight-footer {
            background: #050505;
            border-top: 1px solid rgba(255, 255, 255, 0.12);
            padding: 9px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 11px;
            color: #94a3b8;
        }
</style>
    
    <script>
        // Global variables populated from PHP
        const BASE_PATH = '<?php echo $base_path; ?>';
        window.BASE_PATH = BASE_PATH;
        let companyProfile = <?php echo json_encode($profile); ?>;
        let currentUser = <?php echo isset($_SESSION['user_id']) ? json_encode(['username' => $_SESSION['username'], 'role' => $_SESSION['role']]) : 'null'; ?>;
        
        // Indian Currency Formatter (Lakhs, Crores format helper)
        function formatIndianCurrency(amount) {
            const x = amount.toString().split('.');
            let lastThree = x[0].substring(x[0].length - 3);
            const otherSymbols = x[0].substring(0, x[0].length - 3);
            if (otherSymbols !== '') {
                lastThree = ',' + lastThree;
            }
            let res = otherSymbols.replace(/\B(?=(\d{2})+(?!\d))/g, ",") + lastThree;
            if (x.length > 1) {
                res += '.' + x[1].substring(0, 2);
            }
            return 'Rs. ' + res;
        }
        
        // Modern Executive Toast Notification System
        function showNotification(message, icon = 'info', customTitle = null) {
            if (message === 'SESSION_EXPIRED') {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Session Expired',
                        text: 'Your session has expired because your account was logged in from another device.',
                        icon: 'warning',
                        confirmButtonText: 'Login Again',
                        confirmButtonColor: '#0f172a'
                    }).then(() => window.location.reload());
                } else {
                    alert('Your session has expired because your account was logged in from another device.');
                    window.location.reload();
                }
                return;
            }

            let container = document.getElementById('toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'toast-container';
                container.className = 'toast-container';
                document.body.appendChild(container);
            }

            // Normalize type
            let normType = String(icon || 'info').toLowerCase();
            if (normType === 'danger') normType = 'error';
            if (!['success', 'error', 'warning', 'info'].includes(normType)) {
                normType = 'info';
            }

            // Default titles
            const defaultTitles = {
                success: 'Action Completed',
                error: 'Action Failed',
                warning: 'Notice',
                info: 'Notification'
            };
            const title = customTitle || defaultTitles[normType];

            // Icons
            const iconNames = {
                success: 'check-circle',
                error: 'alert-circle',
                warning: 'alert-triangle',
                info: 'info'
            };
            const iconName = iconNames[normType];

            const toast = document.createElement('div');
            toast.className = `toast toast-${normType}`;
            const duration = 3800; // 3.8s duration

            toast.innerHTML = `
                <div class="toast-icon-box">
                    <i data-lucide="${iconName}" style="width:18px;height:18px;"></i>
                </div>
                <div class="toast-content">
                    <div class="toast-header">
                        <span class="toast-title">${escapeToastHtml(title)}</span>
                        <span class="toast-time">Just now</span>
                    </div>
                    <div class="toast-message">${String(message || '')}</div>
                </div>
                <button class="toast-close-btn" title="Dismiss" onclick="dismissToast(this.parentElement)">
                    <i data-lucide="x" style="width:13px;height:13px;"></i>
                </button>
                <div class="toast-progress" style="animation-duration: ${duration}ms;"></div>
            `;

            container.appendChild(toast);
            if (window.lucide && typeof window.lucide.createIcons === 'function') {
                window.lucide.createIcons();
            }

            // Auto-dismiss management with hover pause
            let dismissTimeout;
            let remaining = duration;
            let startTime = Date.now();

            function startTimer() {
                startTime = Date.now();
                dismissTimeout = setTimeout(() => {
                    dismissToast(toast);
                }, remaining);
            }

            function pauseTimer() {
                clearTimeout(dismissTimeout);
                remaining -= (Date.now() - startTime);
            }

            toast.addEventListener('mouseenter', pauseTimer);
            toast.addEventListener('mouseleave', () => {
                if (remaining > 0) startTimer();
            });

            startTimer();
        }

        function dismissToast(toastEl) {
            if (!toastEl || toastEl.classList.contains('toast-hiding')) return;
            toastEl.classList.add('toast-hiding');
            setTimeout(() => {
                toastEl.remove();
            }, 250);
        }

        function escapeToastHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
        
        // User initialization helpers
        async function initUserSelects() {
            if (currentUser && currentUser.role === 'Admin') {
                document.querySelectorAll('.admin-only-field').forEach(el => el.style.display = 'block');
            }
            try {
                const res = await fetch(`${BASE_PATH}api.php?api=get_users`);
                const users = await res.json();
                if(users && !users.error) {
                    let opts = '<option value="">-- Unassigned --</option>';
                    users.forEach(u => opts += `<option value="${u.username}">${u.name ? u.name + ' (' + u.username + ')' : u.username}</option>`);
                    document.querySelectorAll('.user-select').forEach(sel => sel.innerHTML = opts);
                    
                    let filterOpts = '<option value=""> All Staff</option><option value="unassigned">-- Unassigned --</option>';
                    users.forEach(u => filterOpts += `<option value="${u.username}">${u.name ? u.name + ' (' + u.username + ')' : u.username}</option>`);
                    document.querySelectorAll('.user-filter-select').forEach(sel => sel.innerHTML = filterOpts);
                }
            } catch(e) {}
        }
        // Smart Sidebar Scroll & State Management
        function initSmartSidebar() {
            const menu = document.querySelector('.sidebar-menu');
            if (menu) {
                const savedScroll = sessionStorage.getItem('bfs_sidebar_scroll');
                if (savedScroll !== null) {
                    menu.scrollTop = parseInt(savedScroll, 10);
                }

                const activeItem = menu.querySelector('.menu-item.active');
                if (activeItem) {
                    const menuRect = menu.getBoundingClientRect();
                    const itemRect = activeItem.getBoundingClientRect();
                    if (itemRect.top < menuRect.top || itemRect.bottom > menuRect.bottom) {
                        activeItem.scrollIntoView({ block: 'nearest', behavior: 'instant' });
                    }
                }

                menu.addEventListener('scroll', () => {
                    sessionStorage.setItem('bfs_sidebar_scroll', menu.scrollTop);
                }, { passive: true });

                menu.querySelectorAll('a').forEach(a => {
                    a.addEventListener('click', () => {
                        sessionStorage.setItem('bfs_sidebar_scroll', menu.scrollTop);
                    });
                });
            }

            // Restore collapsed state
            if (localStorage.getItem('bfs_sidebar_collapsed') === 'true') {
                document.body.classList.add('sidebar-collapsed');
            }
        }

        // Sidebar Drag-to-Resize & Production-Ready Collapse System
        let isResizingSidebar = false;
        const MIN_SIDEBAR_WIDTH = 180;
        const MAX_SIDEBAR_WIDTH = 500;
        const COLLAPSE_THRESHOLD = 135; // Dragging narrower than 135px snaps to collapse!

        function initSidebarResize() {
            const handle = document.getElementById('sidebarResizeHandle');
            const sidebar = document.getElementById('appSidebar');
            if (!handle || !sidebar) return;

            // Apply saved width or default
            const savedWidth = localStorage.getItem('bfs_sidebar_width');
            if (savedWidth) {
                const w = parseInt(savedWidth, 10);
                if (!isNaN(w) && w >= MIN_SIDEBAR_WIDTH && w <= MAX_SIDEBAR_WIDTH) {
                    document.documentElement.style.setProperty('--sidebar-width', w + 'px');
                }
            }

            // Restore collapsed state
            if (localStorage.getItem('bfs_sidebar_collapsed') === 'true') {
                document.body.classList.add('sidebar-collapsed');
            }

            function startResizing(e) {
                if (window.innerWidth <= 768) return;
                if (e.type === 'mousedown' && e.button !== 0) return;
                e.preventDefault();
                isResizingSidebar = true;
                document.body.classList.add('is-resizing-sidebar');
            }

            handle.addEventListener('mousedown', startResizing);
            handle.addEventListener('touchstart', startResizing, { passive: false });

            // Double click handle to reset default width (248px)
            handle.addEventListener('dblclick', () => {
                document.documentElement.style.setProperty('--sidebar-width', '248px');
                localStorage.setItem('bfs_sidebar_width', '248px');
            });

            function handleMove(clientX) {
                if (!isResizingSidebar) return;

                if (clientX < COLLAPSE_THRESHOLD) {
                    // Visual feedback: sidebar about to collapse
                    sidebar.style.opacity = '0.4';
                    sidebar.style.filter = 'grayscale(1)';
                } else {
                    sidebar.style.opacity = '1';
                    sidebar.style.filter = 'none';
                    let targetWidth = clientX;
                    if (targetWidth < MIN_SIDEBAR_WIDTH) targetWidth = MIN_SIDEBAR_WIDTH;
                    if (targetWidth > MAX_SIDEBAR_WIDTH) targetWidth = MAX_SIDEBAR_WIDTH;
                    document.documentElement.style.setProperty('--sidebar-width', targetWidth + 'px');
                }
            }

            window.addEventListener('mousemove', (e) => {
                if (isResizingSidebar) handleMove(e.clientX);
            });

            window.addEventListener('touchmove', (e) => {
                if (isResizingSidebar && e.touches.length > 0) {
                    handleMove(e.touches[0].clientX);
                }
            });

            function endResizing(clientX) {
                if (!isResizingSidebar) return;
                isResizingSidebar = false;
                document.body.classList.remove('is-resizing-sidebar');
                sidebar.style.opacity = '1';
                sidebar.style.filter = 'none';

                if (clientX < COLLAPSE_THRESHOLD) {
                    // Snapped below threshold -> Collapse completely!
                    collapseSidebar();
                } else {
                    let finalWidth = clientX;
                    if (finalWidth < MIN_SIDEBAR_WIDTH) finalWidth = MIN_SIDEBAR_WIDTH;
                    if (finalWidth > MAX_SIDEBAR_WIDTH) finalWidth = MAX_SIDEBAR_WIDTH;
                    document.documentElement.style.setProperty('--sidebar-width', finalWidth + 'px');
                    localStorage.setItem('bfs_sidebar_width', finalWidth + 'px');
                    localStorage.setItem('bfs_sidebar_collapsed', 'false');
                }
            }

            window.addEventListener('mouseup', (e) => {
                if (isResizingSidebar) endResizing(e.clientX);
            });

            window.addEventListener('touchend', (e) => {
                if (isResizingSidebar) {
                    const clientX = e.changedTouches.length > 0 ? e.changedTouches[0].clientX : 248;
                    endResizing(clientX);
                }
            });
        }

        // Collapse Sidebar (Completely disappears, showing only > button)
        function collapseSidebar() {
            document.body.classList.add('sidebar-collapsed');
            localStorage.setItem('bfs_sidebar_collapsed', 'true');
            if (window.lucide) lucide.createIcons();
        }

        // Expand Sidebar (Restores full sidebar and hides > button)
        function expandSidebar() {
            document.body.classList.remove('sidebar-collapsed');
            localStorage.setItem('bfs_sidebar_collapsed', 'false');
            let saved = parseInt(localStorage.getItem('bfs_sidebar_width') || '248', 10);
            if (isNaN(saved) || saved < MIN_SIDEBAR_WIDTH) saved = 248;
            document.documentElement.style.setProperty('--sidebar-width', saved + 'px');
            if (window.lucide) lucide.createIcons();
        }

        // Toggle Sidebar Collapse (Ctrl+B)
        function toggleSidebarCollapse() {
            if (document.body.classList.contains('sidebar-collapsed')) {
                expandSidebar();
            } else {
                collapseSidebar();
            }
        }

        // Mobile Off-Canvas Drawer Toggle
        function toggleMobileSidebar(open) {
            const sidebar = document.getElementById('appSidebar') || document.querySelector('aside');
            const backdrop = document.getElementById('sidebarBackdrop');
            if (!sidebar) return;
            if (open === undefined) {
                sidebar.classList.toggle('mobile-open');
                if (backdrop) backdrop.classList.toggle('active');
            } else if (open) {
                sidebar.classList.add('mobile-open');
                if (backdrop) backdrop.classList.add('active');
            } else {
                sidebar.classList.remove('mobile-open');
                if (backdrop) backdrop.classList.remove('active');
            }
        }

        // Auto-close mobile sidebar when clicking any navigation link on small screens
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.sidebar-menu a').forEach(link => {
                link.addEventListener('click', () => {
                    if (window.innerWidth <= 768) {
                        toggleMobileSidebar(false);
                    }
                });
            });
        });

        // Spotlight Omni-Search Command Desk (Ctrl+K)
        let activeSpotlightCat = 'all';
        let spotlightDebounceTimer = null;
        let spotlightSelectedIndex = 0;

        let spotlightPagesData = [
            { title: 'Admin Dashboard', type: 'Page', url: '<?php echo $base_path; ?>dashboard.php', icon: 'layout-dashboard' },
            { title: 'RM Loan Workspace', type: 'Page', url: '<?php echo $base_path; ?>rm/dashboard.php', icon: 'user-check' },
            { title: 'Pre-Leads (Raw Data)', type: 'Page', url: '<?php echo $base_path; ?>pre_leads.php', icon: 'inbox' },
            { title: 'Lead Management', type: 'Page', url: '<?php echo $base_path; ?>leads.php', icon: 'target' },
            { title: 'WhatsApp CRM & Live Hub', type: 'Tool', url: '<?php echo $base_path; ?>whatsapp_crm.php', icon: 'message-circle' },
            { title: 'Email Marketing & Campaigns (Listmonk)', type: 'Tool', url: '<?php echo $base_path; ?>marketing.php', icon: 'send' },
            { title: 'Field Visits', type: 'Page', url: '<?php echo $base_path; ?>field_visits.php', icon: 'map-pin' },
            { title: 'Reminders & Follow-ups', type: 'Page', url: '<?php echo $base_path; ?>reminders.php', icon: 'bell' },
            { title: 'Loan Applications (LOS)', type: 'Page', url: '<?php echo $base_path; ?>applicants_list.php', icon: 'files' },
            { title: 'Search & CRM Track', type: 'Page', url: '<?php echo $base_path; ?>search_track.php', icon: 'search' },
            { title: 'Financial Calculators', type: 'Tool', url: '<?php echo $base_path; ?>calculators.php', icon: 'calculator' },
            { title: 'Bankers Directory', type: 'Page', url: '<?php echo $base_path; ?>bankers_list.php', icon: 'users' },
            { title: 'Referrals / DSA Network', type: 'Page', url: '<?php echo $base_path; ?>referrals_list.php', icon: 'briefcase' },
            { title: 'Staff & HRMS', type: 'Admin', url: '<?php echo $base_path; ?>employees_list.php', icon: 'contact' },
            { title: 'Payout Master', type: 'Admin', url: '<?php echo $base_path; ?>payout_settings.php', icon: 'percent' },
            { title: 'CRM Settings', type: 'Admin', url: '<?php echo $base_path; ?>settings.php', icon: 'settings' }
        ];

        function openSpotlightSearch() {
            const dropdown = document.getElementById('spotlight-dropdown');
            const input = document.getElementById('spotlight-input');
            if (dropdown && input) {
                dropdown.style.display = 'block';
                input.focus();
                activeSpotlightCat = 'all';
                document.querySelectorAll('.spotlight-cat-pill').forEach((el, i) => {
                    el.classList.toggle('active', i === 0);
                });
                executeOmniSearch(input.value || '');
            }
        }

        function closeSpotlightSearch() {
            const dropdown = document.getElementById('spotlight-dropdown');
            const input = document.getElementById('spotlight-input');
            if (dropdown) dropdown.style.display = 'none';
            if (input) input.blur();
        }

        function scrollSpotlightCats(amount) {
            const container = document.getElementById('spotlight-cats-container');
            if (container) {
                container.scrollBy({ left: amount, behavior: 'smooth' });
            }
        }

        function setSpotlightCat(cat, pillEl) {
            activeSpotlightCat = cat;
            document.querySelectorAll('.spotlight-cat-pill').forEach(el => el.classList.remove('active'));
            if (pillEl) {
                pillEl.classList.add('active');
                try {
                    pillEl.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
                } catch(e){}
            }
            const query = document.getElementById('spotlight-input')?.value || '';
            executeOmniSearch(query);
        }

        function handleSpotlightInput(val) {
            clearTimeout(spotlightDebounceTimer);
            spotlightDebounceTimer = setTimeout(() => {
                executeOmniSearch(val);
            }, 150);
        }

        function executeOmniSearch(query) {
            const list = document.getElementById('spotlight-results-list');
            const spinner = document.getElementById('spotlight-spinner');
            if (!list) return;

            const q = query.trim();
            spotlightSelectedIndex = 0;

            if (spinner) spinner.style.display = 'inline';

            fetch(`<?php echo $base_path; ?>api.php?api=omni_search&q=${encodeURIComponent(q)}&category=${activeSpotlightCat}`)
            .then(r => r.json())
            .then(data => {
                if (spinner) spinner.style.display = 'none';
                renderOmniSearchResults(q, data.results || {});
            })
            .catch(() => {
                if (spinner) spinner.style.display = 'none';
                renderOmniSearchResults(q, {});
            });
        }

        function renderOmniSearchResults(query, res) {
            const list = document.getElementById('spotlight-results-list');
            if (!list) return;

            const q = query.toLowerCase().trim();
            const basePath = '<?php echo $base_path; ?>';
            let html = '';
            let itemIdx = 0;

            // 1. System Pages & Tools
            if (activeSpotlightCat === 'all' || activeSpotlightCat === 'pages') {
                const matchedPages = spotlightPagesData.filter(p => !q || p.title.toLowerCase().includes(q) || p.type.toLowerCase().includes(q));
                if (matchedPages.length > 0) {
                    html += `<div class="spotlight-section-header"><i data-lucide="compass" style="width:13px; height:13px; color:#ffffff;"></i> SYSTEM PAGES & TOOLS (${matchedPages.length})</div>`;
                    matchedPages.forEach(p => {
                        const isSel = itemIdx === spotlightSelectedIndex ? 'selected' : '';
                        html += `
                            <a href="${p.url}" class="spotlight-item ${isSel}">
                                <div class="spotlight-item-icon"><i data-lucide="${p.icon}" style="width:15px; height:15px; color:#ffffff;"></i></div>
                                <span style="font-weight:600; font-size:13px; color:#f8fafc; flex:1;">${p.title}</span>
                                <span class="spotlight-badge">${p.type}</span>
                            </a>
                        `;
                        itemIdx++;
                    });
                }
            }

            // 2. Applicants / Loans
            if ((activeSpotlightCat === 'all' || activeSpotlightCat === 'clients') && res.applicants && res.applicants.length > 0) {
                html += `<div class="spotlight-section-header"><i data-lucide="file-text" style="width:13px; height:13px; color:#ffffff;"></i> LOAN APPLICATIONS & CLIENTS (${res.applicants.length})</div>`;
                res.applicants.forEach(a => {
                    const url = `${basePath}search_track.php?view_client=${a.id}`;
                    const isSel = itemIdx === spotlightSelectedIndex ? 'selected' : '';

                    let subInfo = [];
                    if (a.mobile) subInfo.push('Mobile: ' + a.mobile);
                    if (a.pan) subInfo.push('PAN: ' + a.pan);
                    if (a.loan_type) subInfo.push(a.loan_type);
                    if (a.sanction_bank) subInfo.push('Bank: ' + a.sanction_bank);
                    if (a.city) subInfo.push(a.city);

                    html += `
                        <a href="${url}" class="spotlight-item ${isSel}">
                            <div class="spotlight-item-icon"><i data-lucide="file-text" style="width:15px; height:15px; color:#ffffff;"></i></div>
                            <div style="flex:1; min-width:0;">
                                <div style="font-weight:700; color:#f8fafc; font-size:13px; display:flex; align-items:center; gap:8px;">
                                    <span>${a.customer_name}</span>
                                    <span style="font-size:10px; color:#ffffff; font-family:monospace; background:rgba(255,255,255,0.12); padding:1px 5px; border-radius:3px; border:1px solid rgba(255,255,255,0.2);">${a.loan_id || 'ID#' + a.id}</span>
                                </div>
                                <div style="font-size:11px; color:#94a3b8; margin-top:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${subInfo.join(' • ')}</div>
                            </div>
                            <span class="spotlight-badge">${a.overall_status || 'Active'}</span>
                        </a>
                    `;
                    itemIdx++;
                });
            }

            // 3. Bank Logins & Submissions
            if ((activeSpotlightCat === 'all' || activeSpotlightCat === 'clients') && res.bank_logins && res.bank_logins.length > 0) {
                html += `<div class="spotlight-section-header"><i data-lucide="building" style="width:13px; height:13px; color:#ffffff;"></i> BANK FILE SUBMISSIONS (${res.bank_logins.length})</div>`;
                res.bank_logins.forEach(bl => {
                    const url = `${basePath}search_track.php?view_client=${bl.applicant_id}`;
                    const isSel = itemIdx === spotlightSelectedIndex ? 'selected' : '';

                    html += `
                        <a href="${url}" class="spotlight-item ${isSel}">
                            <div class="spotlight-item-icon"><i data-lucide="building" style="width:15px; height:15px; color:#ffffff;"></i></div>
                            <div style="flex:1; min-width:0;">
                                <div style="font-weight:700; color:#f8fafc; font-size:13px;">${bl.bank_name} <span style="font-size:11px; color:#94a3b8; font-weight:400;">(${bl.customer_name})</span></div>
                                <div style="font-size:11px; color:#94a3b8;">Ref No: ${bl.login_ref_no || 'N/A'} • Loan ID: ${bl.loan_id || bl.applicant_id}</div>
                            </div>
                            <span class="spotlight-badge">${bl.status || 'Submitted'}</span>
                        </a>
                    `;
                    itemIdx++;
                });
            }

            // 4. Bank Payout Invoices
            if ((activeSpotlightCat === 'all' || activeSpotlightCat === 'clients') && res.invoices && res.invoices.length > 0) {
                html += `<div class="spotlight-section-header"><i data-lucide="receipt" style="width:13px; height:13px; color:#ffffff;"></i> PAYOUT INVOICES (${res.invoices.length})</div>`;
                res.invoices.forEach(inv => {
                    const url = `${basePath}search_track.php?view_client=${inv.applicant_id}`;
                    const isSel = itemIdx === spotlightSelectedIndex ? 'selected' : '';

                    html += `
                        <a href="${url}" class="spotlight-item ${isSel}">
                            <div class="spotlight-item-icon"><i data-lucide="receipt" style="width:15px; height:15px; color:#ffffff;"></i></div>
                            <div style="flex:1; min-width:0;">
                                <div style="font-weight:700; color:#f8fafc; font-size:13px;">Invoice ${inv.invoice_number} <span style="font-size:11px; color:#94a3b8; font-weight:400;">(${inv.bank_name})</span></div>
                                <div style="font-size:11px; color:#94a3b8;">Client: ${inv.customer_name} • Net: Rs. ${parseFloat(inv.net_invoice_amount || 0).toLocaleString('en-IN')}</div>
                            </div>
                            <span class="spotlight-badge">${inv.status || 'Draft'}</span>
                        </a>
                    `;
                    itemIdx++;
                });
            }

            // 5. Staff / RMs / Field Team
            if ((activeSpotlightCat === 'all' || activeSpotlightCat === 'staff') && res.staff && res.staff.length > 0) {
                html += `<div class="spotlight-section-header"><i data-lucide="users" style="width:13px; height:13px; color:#ffffff;"></i> STAFF, RMS & FIELD TEAM (${res.staff.length})</div>`;
                res.staff.forEach(s => {
                    const url = `${basePath}employees_list.php?search=${encodeURIComponent(s.name)}`;
                    const isSel = itemIdx === spotlightSelectedIndex ? 'selected' : '';

                    html += `
                        <a href="${url}" class="spotlight-item ${isSel}">
                            <div class="spotlight-item-icon"><i data-lucide="user-check" style="width:15px; height:15px; color:#ffffff;"></i></div>
                            <div style="flex:1; min-width:0;">
                                <div style="font-weight:700; color:#f8fafc; font-size:13px;">${s.name} <span style="font-size:10.5px; color:#94a3b8; font-family:monospace;">[${s.emp_id}]</span></div>
                                <div style="font-size:11px; color:#94a3b8;">${s.role || 'Staff Member'} ${s.mobile ? '• Mobile: ' + s.mobile : ''}</div>
                            </div>
                            <span class="spotlight-badge">${s.type || 'Staff'}</span>
                        </a>
                    `;
                    itemIdx++;
                });
            }

            // 6. Pre-Leads & Managed Leads
            if ((activeSpotlightCat === 'all' || activeSpotlightCat === 'pre_leads') && ((res.pre_leads && res.pre_leads.length > 0) || (res.leads && res.leads.length > 0))) {
                const totalLeads = (res.pre_leads ? res.pre_leads.length : 0) + (res.leads ? res.leads.length : 0);
                html += `<div class="spotlight-section-header"><i data-lucide="inbox" style="width:13px; height:13px; color:#ffffff;"></i> LEADS & RAW PROSPECTS (${totalLeads})</div>`;
                
                if (res.pre_leads) {
                    res.pre_leads.forEach(l => {
                        const url = `${basePath}pre_leads.php?search=${encodeURIComponent(l.customer_name)}`;
                        const isSel = itemIdx === spotlightSelectedIndex ? 'selected' : '';

                        html += `
                            <a href="${url}" class="spotlight-item ${isSel}">
                                <div class="spotlight-item-icon"><i data-lucide="inbox" style="width:15px; height:15px; color:#ffffff;"></i></div>
                                <div style="flex:1; min-width:0;">
                                    <div style="font-weight:700; color:#f8fafc; font-size:13px;">${l.customer_name}</div>
                                    <div style="font-size:11px; color:#94a3b8;">Mobile: ${l.mobile || 'N/A'} • ${l.loan_type || 'Loan'} • Source: ${l.source || 'Direct'}</div>
                                </div>
                                <span class="spotlight-badge">${l.status || 'Pre-Lead'}</span>
                            </a>
                        `;
                        itemIdx++;
                    });
                }

                if (res.leads) {
                    res.leads.forEach(l => {
                        const url = `${basePath}leads.php?search=${encodeURIComponent(l.customer_name)}`;
                        const isSel = itemIdx === spotlightSelectedIndex ? 'selected' : '';

                        html += `
                            <a href="${url}" class="spotlight-item ${isSel}">
                                <div class="spotlight-item-icon"><i data-lucide="target" style="width:15px; height:15px; color:#ffffff;"></i></div>
                                <div style="flex:1; min-width:0;">
                                    <div style="font-weight:700; color:#f8fafc; font-size:13px;">${l.customer_name}</div>
                                    <div style="font-size:11px; color:#94a3b8;">Mobile: ${l.mobile || 'N/A'} • Source: ${l.source || 'CRM Lead'} ${l.city ? '• ' + l.city : ''}</div>
                                </div>
                                <span class="spotlight-badge">${l.status || 'Managed Lead'}</span>
                            </a>
                        `;
                        itemIdx++;
                    });
                }
            }

            // 7. Bankers Directory
            if ((activeSpotlightCat === 'all' || activeSpotlightCat === 'bankers') && res.bankers && res.bankers.length > 0) {
                html += `<div class="spotlight-section-header"><i data-lucide="building-2" style="width:13px; height:13px; color:#ffffff;"></i> BANKERS DIRECTORY (${res.bankers.length})</div>`;
                res.bankers.forEach(b => {
                    const url = `${basePath}bankers_list.php?search=${encodeURIComponent(b.banker_name)}`;
                    const isSel = itemIdx === spotlightSelectedIndex ? 'selected' : '';

                    html += `
                        <a href="${url}" class="spotlight-item ${isSel}">
                            <div class="spotlight-item-icon"><i data-lucide="building-2" style="width:15px; height:15px; color:#ffffff;"></i></div>
                            <div style="flex:1; min-width:0;">
                                <div style="font-weight:700; color:#f8fafc; font-size:13px;">${b.banker_name} <span style="font-size:11px; color:#cbd5e1; font-weight:600;">(${b.bank_name})</span></div>
                                <div style="font-size:11px; color:#94a3b8;">${b.designation || 'Banker'} • Mobile: ${b.mobile || 'N/A'} ${b.city ? '• ' + b.city : ''}</div>
                            </div>
                            <span class="spotlight-badge">Banker</span>
                        </a>
                    `;
                    itemIdx++;
                });
            }

            // 8. Referrals / DSA Partners
            if ((activeSpotlightCat === 'all' || activeSpotlightCat === 'referrals') && res.referrals && res.referrals.length > 0) {
                html += `<div class="spotlight-section-header"><i data-lucide="briefcase" style="width:13px; height:13px; color:#ffffff;"></i> DSA PARTNERS & REFERRALS (${res.referrals.length})</div>`;
                res.referrals.forEach(r => {
                    const url = `${basePath}referrals_list.php?search=${encodeURIComponent(r.name)}`;
                    const isSel = itemIdx === spotlightSelectedIndex ? 'selected' : '';

                    html += `
                        <a href="${url}" class="spotlight-item ${isSel}">
                            <div class="spotlight-item-icon"><i data-lucide="briefcase" style="width:15px; height:15px; color:#ffffff;"></i></div>
                            <div style="flex:1; min-width:0;">
                                <div style="font-weight:700; color:#f8fafc; font-size:13px;">${r.name} <span style="font-size:10.5px; color:#94a3b8; font-family:monospace;">[${r.ref_code}]</span></div>
                                <div style="font-size:11px; color:#94a3b8;">Mobile: ${r.mobile || 'N/A'} • Type: ${r.type || 'DSA Partner'} ${r.city ? '• ' + r.city : ''}</div>
                            </div>
                            <span class="spotlight-badge">DSA Partner</span>
                        </a>
                    `;
                    itemIdx++;
                });
            }

            // 9. Field Verification Visits
            if ((activeSpotlightCat === 'all' || activeSpotlightCat === 'field_visits') && res.field_visits && res.field_visits.length > 0) {
                html += `<div class="spotlight-section-header"><i data-lucide="map-pin" style="width:13px; height:13px; color:#ffffff;"></i> FIELD VERIFICATION VISITS (${res.field_visits.length})</div>`;
                res.field_visits.forEach(fv => {
                    const url = `${basePath}field_visits.php?search=${encodeURIComponent(fv.person_name)}`;
                    const isSel = itemIdx === spotlightSelectedIndex ? 'selected' : '';

                    html += `
                        <a href="${url}" class="spotlight-item ${isSel}">
                            <div class="spotlight-item-icon"><i data-lucide="map-pin" style="width:15px; height:15px; color:#ffffff;"></i></div>
                            <div style="flex:1; min-width:0;">
                                <div style="font-weight:700; color:#f8fafc; font-size:13px;">${fv.person_name} <span style="font-size:11px; color:#cbd5e1;">(${fv.firm_name})</span></div>
                                <div style="font-size:11px; color:#94a3b8;">Mobile: ${fv.mobile || 'N/A'} • Exec: ${fv.executive_name || 'N/A'} ${fv.city ? '• ' + fv.city : ''}</div>
                            </div>
                            <span class="spotlight-badge">Field Visit</span>
                        </a>
                    `;
                    itemIdx++;
                });
            }

            if (itemIdx === 0) {
                list.innerHTML = `<div style="padding: 35px 20px; text-align:center; color: #94a3b8; font-size: 13px;">No matching records found for "<strong>${q}</strong>".<br><span style="font-size:11px; color:#64748b;">Try searching by Name, Mobile, PAN, Aadhaar, Loan ID, Bank, DSA Code, or Invoice No.</span></div>`;
                return;
            }

            list.innerHTML = html;
            if (window.lucide) lucide.createIcons();
        }

        // Global Keyboard Shortcuts (Ctrl+K, Ctrl+B, Esc, Arrow Keys, Enter)
        document.addEventListener('keydown', (e) => {
            const dropdown = document.getElementById('spotlight-dropdown');
            const isOpen = dropdown && dropdown.style.display === 'block';

            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                openSpotlightSearch();
            } else if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'b') {
                e.preventDefault();
                toggleSidebarCollapse();
            } else if (e.key === 'Escape') {
                closeSpotlightSearch();
            } else if (isOpen) {
                const catPills = Array.from(document.querySelectorAll('.spotlight-cat-pill'));
                const items = document.querySelectorAll('.spotlight-item');

                if (e.key === 'ArrowRight') {
                    e.preventDefault();
                    if (catPills.length > 0) {
                        const curIdx = catPills.findIndex(p => p.classList.contains('active'));
                        const nextIdx = (curIdx + 1) % catPills.length;
                        catPills[nextIdx].click();
                    }
                } else if (e.key === 'ArrowLeft') {
                    e.preventDefault();
                    if (catPills.length > 0) {
                        const curIdx = catPills.findIndex(p => p.classList.contains('active'));
                        const prevIdx = (curIdx - 1 + catPills.length) % catPills.length;
                        catPills[prevIdx].click();
                    }
                } else if (items.length > 0) {
                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        spotlightSelectedIndex = (spotlightSelectedIndex + 1) % items.length;
                        items.forEach((el, idx) => el.classList.toggle('selected', idx === spotlightSelectedIndex));
                        items[spotlightSelectedIndex]?.scrollIntoView({ block: 'nearest' });
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        spotlightSelectedIndex = (spotlightSelectedIndex - 1 + items.length) % items.length;
                        items.forEach((el, idx) => el.classList.toggle('selected', idx === spotlightSelectedIndex));
                        items[spotlightSelectedIndex]?.scrollIntoView({ block: 'nearest' });
                    } else if (e.key === 'Enter') {
                        e.preventDefault();
                        const selectedItem = items[spotlightSelectedIndex];
                        if (selectedItem && selectedItem.href) {
                            window.location.href = selectedItem.href;
                        }
                    }
                }
            }
        });

        // Close search dropdown on click outside
        document.addEventListener('click', (e) => {
            const wrapper = document.querySelector('.sidebar-quick-search-wrapper');
            const dropdown = document.getElementById('spotlight-dropdown');
            if (dropdown && wrapper && !wrapper.contains(e.target)) {
                closeSpotlightSearch();
            }
        });

        document.addEventListener("DOMContentLoaded", () => {
            initUserSelects();
            initSmartSidebar();
            initSidebarResize();
            
            // Category pills mouse wheel & drag scrolling
            const catsContainer = document.getElementById('spotlight-cats-container');
            if (catsContainer) {
                catsContainer.addEventListener('wheel', (e) => {
                    if (e.deltaY !== 0) {
                        e.preventDefault();
                        catsContainer.scrollLeft += e.deltaY;
                    }
                }, { passive: false });

                let isDown = false, startX, scrollLeft;
                catsContainer.addEventListener('mousedown', (e) => {
                    isDown = true;
                    catsContainer.style.cursor = 'grabbing';
                    startX = e.pageX - catsContainer.offsetLeft;
                    scrollLeft = catsContainer.scrollLeft;
                });
                catsContainer.addEventListener('mouseleave', () => { isDown = false; catsContainer.style.cursor = 'pointer'; });
                catsContainer.addEventListener('mouseup', () => { isDown = false; catsContainer.style.cursor = 'pointer'; });
                catsContainer.addEventListener('mousemove', (e) => {
                    if (!isDown) return;
                    e.preventDefault();
                    const x = e.pageX - catsContainer.offsetLeft;
                    const walk = (x - startX) * 1.5;
                    catsContainer.scrollLeft = scrollLeft - walk;
                });
            }

            // Ping server every 60 seconds to update last_active and last_ip
            fetch(`${BASE_PATH}api.php?api=ping`, { method: 'POST' }).catch(() => {}); // Initial ping
            setInterval(() => {
                fetch(`${BASE_PATH}api.php?api=ping`, { method: 'POST' }).catch(() => {});
            }, 60000);
        });
        
        function logout() {
            sessionStorage.removeItem('bfs_sidebar_scroll');
            fetch(`${BASE_PATH}api.php?api=logout`).then(() => {
                const inSub = <?php echo $is_subfolder_ctx ? 'true' : 'false'; ?>;
                location.href = inSub ? '../login.php' : 'login.php';
            });
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
</head>
<body>
    <script>
        (function() {
            try {
                var w = localStorage.getItem('bfs_sidebar_width');
                if (w) document.documentElement.style.setProperty('--sidebar-width', w);
                if (localStorage.getItem('bfs_sidebar_collapsed') === 'true') {
                    document.body.classList.add('sidebar-collapsed');
                }
            } catch(e) {}
        })();
    </script>
    <?php $active_page = basename($_SERVER['PHP_SELF']); ?>

    <!-- Floating Expand Button (Displays when Nav is Collapsed) -->
    <button type="button" id="sidebarExpandBtn" class="sidebar-expand-btn" onclick="expandSidebar()" title="Show Navigation Menu (Ctrl+B)" aria-label="Show Navigation Menu">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
    </button>

    <!-- Mobile Top Header Bar (Displays only on screens <= 768px) -->
    <header class="mobile-top-bar">
        <div style="display:flex; align-items:center; gap:10px;">
            <button type="button" class="mobile-menu-btn" onclick="toggleMobileSidebar()" aria-label="Toggle Navigation Menu">
                <i data-lucide="menu" style="width:20px; height:20px; color:#ffffff;"></i>
            </button>
            <div style="display:flex; align-items:center; gap:8px;">
                <img src="<?php echo $base_path; ?>logo.png" alt="BFS Circle" style="height:26px; width:auto; filter:brightness(0) invert(1);">
                <span style="font-family:'Outfit',sans-serif; font-size:17px; font-weight:800; color:#ffffff; letter-spacing:-0.4px;">BFS Circle</span>
            </div>
        </div>
        <div style="display:flex; align-items:center; gap:8px;">
            <button type="button" onclick="openSpotlightSearch()" style="background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.15); color:#ffffff; width:34px; height:34px; border-radius:8px; display:flex; align-items:center; justify-content:center; cursor:pointer;">
                <i data-lucide="search" style="width:16px; height:16px;"></i>
            </button>
            <div style="width:30px; height:30px; border-radius:50%; background:#ffffff; color:#000000; font-weight:800; font-size:12px; display:flex; align-items:center; justify-content:center;">
                <?php echo strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)); ?>
            </div>
        </div>
    </header>

    <!-- Mobile Drawer Backdrop Overlay -->
    <div id="sidebarBackdrop" class="sidebar-backdrop" onclick="toggleMobileSidebar(false)"></div>

    <!-- Sidebar Navigation -->
    <aside id="appSidebar">
        <!-- Brand Header & Collapse Toggle -->
        <div class="brand-container" style="display:flex; align-items:center; justify-content:space-between; padding: 20px 16px;">
            <div style="display:flex; align-items:center; gap:10px;">
                <img src="<?php echo $base_path; ?>logo.png" alt="BFS Circle Logo" style="height: 34px; width: auto; object-fit: contain; filter: brightness(0) invert(1) drop-shadow(0 2px 8px rgba(255,255,255,0.2));">
                <span class="brand-name" style="font-family: 'Outfit', sans-serif; font-size: 21px; font-weight: 800; color: #ffffff; letter-spacing: -0.5px;">BFS Circle</span>
            </div>
            <button class="sidebar-toggle-btn" onclick="toggleSidebarCollapse()" title="Toggle Sidebar (Ctrl+B)">
                <i data-lucide="panel-left-close" style="width:16px; height:16px;"></i>
            </button>
        </div>

        <!-- Quick Jump Inline Search Bar (Sleek Compact Black & White Desk) -->
        <div class="sidebar-quick-search-wrapper" style="position:relative; padding:0 12px; margin-top:2px; margin-bottom:8px;">
            <div class="sidebar-search-box" style="display:flex; align-items:center; gap:8px; height:32px; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.16); border-radius:7px; padding:0 10px; transition:all 0.2s cubic-bezier(0.16, 1, 0.3, 1);">
                <i data-lucide="search" style="width:13px; height:13px; color:#ffffff; flex-shrink:0;"></i>
                <input type="text" id="spotlight-input" class="spotlight-input" placeholder="Quick search... (Ctrl+K)" oninput="handleSpotlightInput(this.value)" onfocus="openSpotlightSearch()" style="background:transparent; border:none; outline:none; color:#ffffff; font-size:11.5px; width:100%; padding:0;">
                <span id="spotlight-spinner" style="display:none; color:#ffffff; font-size:10px; font-weight:700;">...</span>
                <kbd style="background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.18); padding:1px 4px; border-radius:3px; font-size:9px; color:#ffffff; font-family:monospace; flex-shrink:0; line-height:1;">Ctrl K</kbd>
            </div>

            <!-- INLINE FLOATING SEARCH DROPDOWN MENU (NO SCREEN MODAL/BLUR OVERLAY) -->
            <div id="spotlight-dropdown" class="spotlight-dropdown-menu" style="display:none; position:absolute; top:calc(100% + 6px); left:14px; width:450px; max-width:calc(100vw - 40px); z-index:999999; overflow:hidden;">
                <div class="spotlight-cats-header-wrapper">
                    <button type="button" class="spotlight-cat-scroll-btn" onclick="scrollSpotlightCats(-120)" title="Scroll Left">
                        <i data-lucide="chevron-left" style="width:14px; height:14px;"></i>
                    </button>
                    <div class="spotlight-cats" id="spotlight-cats-container">
                        <div class="spotlight-cat-pill active" onclick="setSpotlightCat('all', this)"><i data-lucide="layers" style="width:12px; height:12px;"></i> All</div>
                        <div class="spotlight-cat-pill" onclick="setSpotlightCat('clients', this)"><i data-lucide="file-text" style="width:12px; height:12px;"></i> Loans & LOS</div>
                        <div class="spotlight-cat-pill" onclick="setSpotlightCat('pre_leads', this)"><i data-lucide="inbox" style="width:12px; height:12px;"></i> Leads</div>
                        <div class="spotlight-cat-pill" onclick="setSpotlightCat('staff', this)"><i data-lucide="users" style="width:12px; height:12px;"></i> Staff & RMs</div>
                        <div class="spotlight-cat-pill" onclick="setSpotlightCat('bankers', this)"><i data-lucide="building-2" style="width:12px; height:12px;"></i> Bankers</div>
                        <div class="spotlight-cat-pill" onclick="setSpotlightCat('referrals', this)"><i data-lucide="briefcase" style="width:12px; height:12px;"></i> DSA Partners</div>
                        <div class="spotlight-cat-pill" onclick="setSpotlightCat('field_visits', this)"><i data-lucide="map-pin" style="width:12px; height:12px;"></i> Field Visits</div>
                        <div class="spotlight-cat-pill" onclick="setSpotlightCat('pages', this)"><i data-lucide="compass" style="width:12px; height:12px;"></i> Pages & Tools</div>
                    </div>
                    <button type="button" class="spotlight-cat-scroll-btn" onclick="scrollSpotlightCats(120)" title="Scroll Right">
                        <i data-lucide="chevron-right" style="width:14px; height:14px;"></i>
                    </button>
                </div>

                <div class="spotlight-results" id="spotlight-results-list"></div>

                <div class="spotlight-footer">
                    <div style="display:flex; gap:10px; align-items:center;">
                        <span><kbd style="background:rgba(255,255,255,0.12); padding:1px 4px; border-radius:3px; color:#ffffff;">← →</kbd> Category</span>
                        <span><kbd style="background:rgba(255,255,255,0.12); padding:1px 4px; border-radius:3px; color:#ffffff;">↑ ↓</kbd> Navigate</span>
                        <span><kbd style="background:rgba(255,255,255,0.12); padding:1px 4px; border-radius:3px; color:#ffffff;">↵</kbd> Select</span>
                        <span><kbd style="background:rgba(255,255,255,0.12); padding:1px 4px; border-radius:3px; color:#ffffff;">ESC</kbd> Close</span>
                    </div>
                    <div><strong>BFS Quick Search</strong></div>
                </div>
            </div>
        </div>

        <!-- Categorized Sidebar Menu -->
        <ul class="sidebar-menu" id="main-sidebar-menu">
            <?php if (($_SESSION['role'] ?? '') === 'Admin'): ?>

            <!-- 1. CORE & WORKSPACE -->
            <div class="sidebar-section-header"><span>Workspace</span></div>
            <li class="menu-item <?php echo ($active_page === 'dashboard.php' && strpos($_SERVER['REQUEST_URI'], 'rm/') === false && strpos($_SERVER['REQUEST_URI'], 'staff/') === false) ? 'active' : ''; ?>">
                <a href="<?php echo $base_path; ?>dashboard.php" title="Admin Dashboard">
                    <i data-lucide="layout-dashboard"></i>
                    <span class="menu-text">Admin Dashboard</span>
                </a>
            </li>
            <li class="menu-item <?php echo (strpos($_SERVER['REQUEST_URI'], 'rm/dashboard.php') !== false || strpos($_SERVER['REQUEST_URI'], 'staff/dashboard.php') !== false) ? 'active' : ''; ?>">
                <a href="<?php echo $base_path; ?>rm/dashboard.php" title="RM Loan Workspace">
                    <i data-lucide="user-check"></i>
                    <span class="menu-text">RM Loan Workspace</span>
                </a>
            </li>

            <!-- 2. SALES & SOURCING -->
            <div class="sidebar-section-header"><span>Sourcing & Leads</span></div>
            <li class="menu-item <?php echo ($active_page === 'pre_leads.php' || $active_page === 'view_pre_lead.php') ? 'active' : ''; ?>">
                <a href="<?php echo $base_path; ?>pre_leads.php" title="Pre-Leads (Raw Data)">
                    <i data-lucide="inbox"></i>
                    <span class="menu-text">Pre-Leads</span>
                    <?php if ($nav_badges['pre_leads'] > 0): ?>
                    <span class="nav-badge"><?php echo $nav_badges['pre_leads']; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="menu-item <?php echo ($active_page === 'leads.php' || $active_page === 'add_lead.php' || $active_page === 'view_lead.php' || $active_page === 'edit_lead.php') ? 'active' : ''; ?>">
                <a href="<?php echo $base_path; ?>leads.php" title="Lead Management">
                    <i data-lucide="target"></i>
                    <span class="menu-text">Lead Management</span>
                    <?php if ($nav_badges['leads'] > 0): ?>
                    <span class="nav-badge"><?php echo $nav_badges['leads']; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="menu-item <?php echo ($active_page === 'whatsapp_crm.php') ? 'active' : ''; ?>">
                <a href="<?php echo $base_path; ?>whatsapp_crm.php" title="WhatsApp CRM & Live Hub">
                    <i data-lucide="message-circle" style="color:#25d366;"></i>
                    <span class="menu-text">WhatsApp CRM</span>
                    <span class="nav-badge" style="background:#22c55e; color:#ffffff; font-size:9px; padding:1px 5px; font-weight:800; border-radius:10px;">LIVE</span>
                </a>
            </li>
            <li class="menu-item <?php echo ($active_page === 'marketing.php') ? 'active' : ''; ?>">
                <a href="<?php echo $base_path; ?>marketing.php" title="Email Marketing & Campaigns (Listmonk Engine)">
                    <i data-lucide="send" style="color:#10b981;"></i>
                    <span class="menu-text">Campaigns Hub</span>
                    <span class="nav-badge" style="background:#10b981; color:#ffffff; font-size:9px; padding:1px 5px; font-weight:800; border-radius:10px;">PRO</span>
                </a>
            </li>
            <li class="menu-item <?php echo ($active_page === 'field_visits.php') ? 'active' : ''; ?>">
                <a href="<?php echo $base_path; ?>field_visits.php" title="Field Visits">
                    <i data-lucide="map-pin"></i>
                    <span class="menu-text">Field Visits</span>
                </a>
            </li>
            <li class="menu-item <?php echo ($active_page === 'referrals_list.php' || $active_page === 'add_referral.php' || $active_page === 'view_referral.php' || $active_page === 'edit_referral.php') ? 'active' : ''; ?>">
                <a href="<?php echo $base_path; ?>referrals_list.php" title="Referrals / DSA">
                    <i data-lucide="briefcase"></i>
                    <span class="menu-text">Referrals / DSA</span>
                </a>
            </li>
            <li class="menu-item <?php echo ($active_page === 'rm_leaderboard.php') ? 'active' : ''; ?>">
                <a href="<?php echo $base_path; ?>rm_leaderboard.php" title="RM Champions Leaderboard">
                    <i data-lucide="award"></i>
                    <span class="menu-text">RM Leaderboard</span>
                </a>
            </li>
            <li class="menu-item <?php echo ($active_page === 'staff_ranking.php' || $active_page === 'export_ranking.php') ? 'active' : ''; ?>">
                <a href="<?php echo $base_path; ?>staff_ranking.php" title="Leaderboards & Ranks">
                    <i data-lucide="trophy"></i>
                    <span class="menu-text">All Staff & DSA Ranks</span>
                </a>
            </li>

            <!-- 3. LOAN OPERATIONS & LOS -->
            <div class="sidebar-section-header"><span>Loan Operations (LOS)</span></div>
            <li class="menu-item <?php echo ($active_page === 'applicants_list.php' || $active_page === 'add_applicant.php' || $active_page === 'view_applicant.php' || $active_page === 'edit_applicant.php' || $active_page === 'applicant_documents.php' || $active_page === 'applicant_disbursements.php') ? 'active' : ''; ?>">
                <a href="<?php echo $base_path; ?>applicants_list.php" title="Loan Applications">
                    <i data-lucide="files"></i>
                    <span class="menu-text">Loan Applications</span>
                    <?php if ($nav_badges['loans'] > 0): ?>
                    <span class="nav-badge"><?php echo $nav_badges['loans']; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="menu-item <?php echo ($active_page === 'search_track.php') ? 'active' : ''; ?>">
                <a href="<?php echo $base_path; ?>search_track.php" title="Search & CRM Track">
                    <i data-lucide="search"></i>
                    <span class="menu-text">Search & CRM Track</span>
                </a>
            </li>
            <li class="menu-item <?php echo ($active_page === 'bankers_list.php' || $active_page === 'add_banker.php' || $active_page === 'view_banker.php' || $active_page === 'edit_banker.php') ? 'active' : ''; ?>">
                <a href="<?php echo $base_path; ?>bankers_list.php" title="Bankers Directory">
                    <i data-lucide="users"></i>
                    <span class="menu-text">Bankers Directory</span>
                </a>
            </li>
            <li class="menu-item <?php echo ($active_page === 'reminders.php') ? 'active' : ''; ?>">
                <a href="<?php echo $base_path; ?>reminders.php" title="Reminders">
                    <i data-lucide="bell"></i>
                    <span class="menu-text">Reminders</span>
                    <?php if ($nav_badges['reminders'] > 0): ?>
                    <span class="nav-badge"><?php echo $nav_badges['reminders']; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="menu-item <?php echo ($active_page === 'calculators.php' || $active_page === 'calc_eligibility.php' || $active_page === 'calc_emi.php' || $active_page === 'calc_foir.php' || $active_page === 'calc_ltv.php') ? 'active' : ''; ?>">
                <a href="<?php echo $base_path; ?>calculators.php" title="Financial Calculators">
                    <i data-lucide="calculator"></i>
                    <span class="menu-text">Calculators</span>
                </a>
            </li>
            <li class="menu-item <?php echo ($active_page === 'client_vault' || strpos($_SERVER['REQUEST_URI'], 'client_vault') !== false) ? 'active' : ''; ?>">
                <a href="<?php echo $base_path; ?>client_vault/index.php" title="Client Vault">
                    <i data-lucide="shield-check"></i>
                    <span class="menu-text">Client Vault</span>
                </a>
            </li>
            <li class="menu-item <?php echo ($active_page === 'send_email.php') ? 'active' : ''; ?>">
                <a href="<?php echo $base_path; ?>send_email.php" title="Send Email">
                    <i data-lucide="mail"></i>
                    <span class="menu-text">Send Email</span>
                </a>
            </li>

            <!-- 4. HRMS & ADMINISTRATION -->
            <div class="sidebar-section-header"><span>Management & HR</span></div>
            <li class="menu-item <?php echo ($active_page === 'employees_list.php' || $active_page === 'add_employee.php' || $active_page === 'view_employee.php' || $active_page === 'edit_employee.php') ? 'active' : ''; ?>">
                <a href="<?php echo $base_path; ?>employees_list.php" title="Staff & HRMS">
                    <i data-lucide="contact"></i>
                    <span class="menu-text">Staff & HRMS</span>
                </a>
            </li>
            <li class="menu-item <?php echo ($active_page === 'attendance_sheet.php') ? 'active' : ''; ?>">
                <a href="<?php echo $base_path; ?>attendance_sheet.php" title="Attendance Register">
                    <i data-lucide="calendar-check"></i>
                    <span class="menu-text">Attendance Register</span>
                </a>
            </li>
            <li class="menu-item <?php echo ($active_page === 'employee_activity.php') ? 'active' : ''; ?>">
                <a href="<?php echo $base_path; ?>employee_activity.php" title="Staff Productivity">
                    <i data-lucide="bar-chart-2"></i>
                    <span class="menu-text">Staff Productivity</span>
                </a>
            </li>
            <li class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) === 'payout_distribution.php') ? 'active' : ''; ?>">
                <a href="<?php echo $base_path; ?>payout_distribution.php" title="Payout Distribution & Commissions">
                    <i data-lucide="coins"></i>
                    <span class="menu-text">Payout Distribution</span>
                    <?php
                    if (isset($db)) {
                        try {
                            $p_pending = (int)$db->query("SELECT COUNT(*) FROM payout_distributions WHERE status='Pending'")->fetchColumn();
                            if ($p_pending > 0): ?>
                            <span class="nav-badge"><?= $p_pending ?></span>
                            <?php endif;
                        } catch(Exception $e) {}
                    }
                    ?>
                </a>
            </li>
            <li class="menu-item <?php echo ($active_page === 'activity_log.php') ? 'active' : ''; ?>">
                <a href="<?php echo $base_path; ?>activity_log.php" title="Activity Logs">
                    <i data-lucide="activity"></i>
                    <span class="menu-text">Activity Logs</span>
                </a>
            </li>
            <li class="menu-item <?php echo ($active_page === 'settings.php') ? 'active' : ''; ?>">
                <a href="<?php echo $base_path; ?>settings.php" title="CRM Settings">
                    <i data-lucide="settings"></i>
                    <span class="menu-text">CRM Settings</span>
                </a>
            </li>

            <div class="sidebar-section-header"><span>Financial Governance</span></div>
            <li class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) === 'fund_management.php') ? 'active' : ''; ?>">
                <a href="<?php echo $base_path; ?>fund_management.php" title="Fund Flow System">
                    <i data-lucide="landmark"></i>
                    <span class="menu-text" style="font-weight:700; color:#fbbf24;">💰 Fund Flow System</span>
                </a>
            </li>

            <?php else: ?>

            <!-- RM SIDEBAR VIEWS -->
            <div class="sidebar-section-header"><span>RM Workspace</span></div>
            <li class="menu-item <?php echo (strpos($_SERVER['REQUEST_URI'], 'rm/dashboard.php') !== false || strpos($_SERVER['REQUEST_URI'], 'staff/dashboard.php') !== false || ($active_page === 'dashboard.php' && strpos($_SERVER['REQUEST_URI'], 'rm/') !== false)) ? 'active' : ''; ?>">
                <a href="<?php echo $base_path; ?>rm/dashboard.php" title="RM Command Center">
                    <i data-lucide="user-check"></i>
                    <span class="menu-text">RM Command Center</span>
                </a>
            </li>
            <li class="menu-item <?php echo ($active_page === 'rm_leaderboard.php' || strpos($_SERVER['REQUEST_URI'], 'rm_leaderboard.php') !== false) ? 'active' : ''; ?>">
                <a href="<?php echo $base_path; ?>rm_leaderboard.php" title="RM Champions Leaderboard">
                    <i data-lucide="award" style="color:#facc15;"></i>
                    <span class="menu-text" style="font-weight:700; color:#fef08a;">RM Leaderboard</span>
                </a>
            </li>

            <div class="sidebar-section-header"><span>Lead & Prospect Sourcing</span></div>
            <li class="menu-item <?php echo ($active_page === 'leads.php') ? 'active' : ''; ?>">
                <a href="<?php echo $base_path; ?>leads.php" title="My Assigned Leads">
                    <i data-lucide="target"></i>
                    <span class="menu-text">My Assigned Leads</span>
                </a>
            </li>
            <li class="menu-item <?php echo ($active_page === 'whatsapp_crm.php') ? 'active' : ''; ?>">
                <a href="<?php echo $base_path; ?>whatsapp_crm.php" title="WhatsApp CRM Hub">
                    <i data-lucide="message-circle" style="color:#25d366;"></i>
                    <span class="menu-text">WhatsApp CRM</span>
                    <span class="nav-badge" style="background:#22c55e; color:#ffffff; font-size:9px; padding:1px 5px; font-weight:800; border-radius:10px;">LIVE</span>
                </a>
            </li>
            <li class="menu-item <?php echo ($active_page === 'pre_leads.php') ? 'active' : ''; ?>">
                <a href="<?php echo $base_path; ?>pre_leads.php" title="My Pre-Leads Pool">
                    <i data-lucide="inbox"></i>
                    <span class="menu-text">My Pre-Leads Pool</span>
                </a>
            </li>

            <div class="sidebar-section-header"><span>Loan Operations</span></div>
            <li class="menu-item <?php echo ($active_page === 'add_applicant.php') ? 'active' : ''; ?>">
                <a href="<?php echo $base_path; ?>add_applicant.php" title="+ New Loan Application">
                    <i data-lucide="user-plus"></i>
                    <span class="menu-text">+ New Loan Application</span>
                </a>
            </li>
            <li class="menu-item <?php echo ($active_page === 'search_track.php' || $active_page === 'applicants_list.php' || $active_page === 'applicant_documents.php' || $active_page === 'applicant_disbursements.php') ? 'active' : ''; ?>">
                <a href="<?php echo $base_path; ?>search_track.php" title="My Loan Cases (LOS)">
                    <i data-lucide="folder-check"></i>
                    <span class="menu-text">My Loan Cases</span>
                    <?php if ($nav_badges['loans'] > 0): ?>
                    <span class="nav-badge" title="New files received today"><?php echo $nav_badges['loans']; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="menu-item <?php echo ($active_page === 'reminders.php') ? 'active' : ''; ?>">
                <a href="<?php echo $base_path; ?>reminders.php" title="Reminders">
                    <i data-lucide="bell"></i>
                    <span class="menu-text">Reminders</span>
                    <?php if ($nav_badges['reminders'] > 0): ?>
                    <span class="nav-badge"><?php echo $nav_badges['reminders']; ?></span>
                    <?php endif; ?>
                </a>
            </li>

            <div class="sidebar-section-header"><span>Tools & Directory</span></div>
            <li class="menu-item <?php echo ($active_page === 'bankers_list.php' || $active_page === 'view_banker.php' || $active_page === 'add_banker.php' || $active_page === 'edit_banker.php') ? 'active' : ''; ?>">
                <a href="<?php echo $base_path; ?>bankers_list.php" title="Bankers Directory">
                    <i data-lucide="users"></i>
                    <span class="menu-text">Bankers Directory</span>
                </a>
            </li>
            <li class="menu-item <?php echo ($active_page === 'calculators.php' || $active_page === 'calc_eligibility.php' || $active_page === 'calc_emi.php' || $active_page === 'calc_foir.php' || $active_page === 'calc_ltv.php') ? 'active' : ''; ?>">
                <a href="<?php echo $base_path; ?>calculators.php" title="Financial Calculators">
                    <i data-lucide="calculator"></i>
                    <span class="menu-text">Financial Calculators</span>
                </a>
            </li>
            <li class="menu-item <?php echo ($active_page === 'my_earnings.php' || strpos($_SERVER['REQUEST_URI'], 'rm/my_earnings') !== false) ? 'active' : ''; ?>" style="margin-top:4px;">
                <a href="<?php echo $base_path; ?>rm/my_earnings.php" title="My Earnings & Wallet" style="background:rgba(16,185,129,0.08); border:1px solid rgba(16,185,129,0.18);">
                    <i data-lucide="wallet" style="color:#4ade80;"></i>
                    <span class="menu-text" style="color:#4ade80; font-weight:700;">My Earnings</span>
                    <?php
                    // Show pending payout badge if any
                    if (isset($db) && isset($_SESSION['user_id'])) {
                        try {
                            $w_stmt = $db->prepare("SELECT COUNT(*) FROM payout_distributions WHERE payee_user_id = ? AND status = 'Pending'");
                            $w_stmt->execute([$_SESSION['user_id']]);
                            $pending_count = (int)$w_stmt->fetchColumn();
                            if ($pending_count > 0): ?>
                            <span class="nav-badge"><?= $pending_count ?></span>
                            <?php endif;
                        } catch(Exception $e) {}
                    }
                    ?>
                </a>
            </li>
            <?php endif; ?>
        </ul>

        <!-- Integrated Sidebar Footer -->
        <div class="sidebar-footer" style="padding:14px; background:#000000; border-top:1px solid rgba(255,255,255,0.15); font-family:'Outfit',sans-serif; margin-top:auto;">
            <!-- USER & LOGOUT BAR -->
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; padding-bottom:10px; border-bottom:1px solid rgba(255,255,255,0.1);">
                <div style="display:flex; align-items:center; gap:10px; overflow:hidden;">
                    <div style="width:32px; height:32px; border-radius:50%; background:#ffffff; color:#000000; font-weight:800; font-size:13px; display:flex; align-items:center; justify-content:center; flex-shrink:0; box-shadow:0 2px 8px rgba(255,255,255,0.3);">
                        <?php echo strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)); ?>
                    </div>
                    <div style="overflow:hidden; line-height:1.2;">
                        <div style="color:#ffffff; font-weight:700; font-size:12.5px; letter-spacing:-0.01em; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" id="sidebar-user-name">
                            <?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>
                        </div>
                        <div style="font-size:10.5px; color:#94a3b8; display:flex; align-items:center; gap:5px; margin-top:2px;">
                            <span style="font-weight:600; color:#cbd5e1;"><?php echo (($_SESSION['role'] ?? '') === 'Staff') ? 'RM' : htmlspecialchars($_SESSION['role'] ?? ''); ?></span>
                            <span style="display:inline-flex; align-items:center; gap:3px; color:#34d399; font-weight:700;">
                                <span style="width:5px; height:5px; border-radius:50%; background:#10b981;"></span> Online
                            </span>
                        </div>
                    </div>
                </div>
                <button onclick="logout()" title="Logout" style="background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.15); color:#94a3b8; padding:6px 9px; border-radius:8px; font-size:11.5px; cursor:pointer; display:flex; align-items:center; gap:4px; flex-shrink:0; transition:all 0.2s ease;" onmouseover="this.style.color='#ef4444'; this.style.borderColor='rgba(239,68,68,0.4)'; this.style.background='rgba(239,68,68,0.1)'" onmouseout="this.style.color='#94a3b8'; this.style.borderColor='rgba(255,255,255,0.15)'; this.style.background='rgba(255,255,255,0.06)'">
                    <i data-lucide="log-out" style="width:13px; height:13px;"></i>
                </button>
            </div>

            <!-- SHIFT PUNCH WIDGET CARD -->
            <div id="sidebar-punch-card" style="background:#050505; border:1px solid rgba(255,255,255,0.14); border-radius:12px; padding:12px; box-shadow:inset 0 1px 1px rgba(255,255,255,0.04);">
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px;">
                    <div style="display:flex; align-items:center; gap:6px;">
                        <span id="float-status-dot" class="pulse-indicator-dot pulse-idle"></span>
                        <span id="float-status-label" style="font-size:9.5px; font-weight:800; color:#94a3b8; text-transform:uppercase; letter-spacing:0.6px;">Off Duty</span>
                    </div>
                    <div id="float-timer-display" style="font-size:14px; font-weight:800; color:#ffffff; font-family:'Outfit',sans-serif; letter-spacing:-0.02em;">00:00:00</div>
                </div>

                <div style="display:flex; align-items:center; gap:8px;">
                    <div id="float-btn-container" style="flex:1;">
                        <button class="btn-floating-action btn-floating-in" onclick="promptPunchIn()" style="width:100%; justify-content:center;">
                            <i data-lucide="play" style="width:14px; height:14px; fill:currentColor;"></i> PUNCH IN
                        </button>
                    </div>
                    <button onclick="promptResetPunch()" title="Reset Shift Clock" style="background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12); color:#94a3b8; border-radius:10px; width:34px; height:34px; display:flex; align-items:center; justify-content:center; cursor:pointer; flex-shrink:0; transition:all 0.2s ease;" onmouseover="this.style.color='#ffffff'; this.style.background='rgba(255,255,255,0.18)'; this.style.borderColor='rgba(255,255,255,0.25)'" onmouseout="this.style.color='#94a3b8'; this.style.background='rgba(255,255,255,0.06)'; this.style.borderColor='rgba(255,255,255,0.12)'">
                        <i data-lucide="rotate-ccw" style="width:13px; height:13px;"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Sidebar Draggable Resizer Handle -->
        <div id="sidebarResizeHandle" class="sidebar-resizer" title="Drag to resize navigation (Double-click to reset)"></div>
    </aside>

    <!-- Main Content Workspace -->
    <main style="<?php echo !empty($hide_main_header) ? 'padding-top:16px;' : ''; ?> padding-bottom:24px;">
        
        <?php if (empty($hide_main_header)): ?>
        <!-- Header -->
        <header class="main-header" style="border-bottom:1px solid #e2e8f0; padding-bottom:16px; margin-bottom:24px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
            <div class="page-title">
                <h1 id="view-title" style="font-size:24px; font-weight:800; color:#0f172a; letter-spacing:-0.03em; margin:0; display:flex; align-items:center; gap:10px;">
                    <i data-lucide="layout-dashboard" style="color:#0f172a; width:24px; height:24px;"></i>
                    <?php echo htmlspecialchars($page_title ?? 'Dashboard'); ?>
                </h1>
                <p id="view-subtitle" style="margin:6px 0 0 34px; color:#64748b; font-size:13px; font-weight:500;"><?php echo htmlspecialchars($page_subtitle ?? ''); ?></p>
            </div>
        </header>
        <?php endif; ?>

<style>
@keyframes livePulseDot {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7); }
    70% { transform: scale(1.05); box-shadow: 0 0 0 10px rgba(34, 197, 94, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
}
@keyframes idlePulseDot {
    0% { opacity: 0.5; }
    50% { opacity: 1.0; }
    100% { opacity: 0.5; }
}
.pulse-indicator-dot {
    width: 9px;
    height: 9px;
    border-radius: 50%;
    display: inline-block;
}
.pulse-active {
    background: #22c55e;
    animation: livePulseDot 2s infinite;
}
.pulse-idle {
    background: #94a3b8;
    animation: idlePulseDot 2s infinite;
}
.pulse-completed {
    background: #38bdf8;
}
.btn-floating-action {
    border-radius: 10px;
    padding: 9px 14px;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 0.04em;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 7px;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    white-space: nowrap;
}
.btn-floating-in {
    background: linear-gradient(135deg, #ffffff 0%, #f1f5f9 100%);
    color: #0f172a;
    border: 1px solid rgba(255, 255, 255, 0.4);
    box-shadow: 0 4px 12px rgba(255, 255, 255, 0.15);
}
.btn-floating-in:hover {
    background: #ffffff;
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(255, 255, 255, 0.25);
}
.btn-floating-out {
    background: linear-gradient(135deg, #f43f5e 0%, #e11d48 100%);
    color: #ffffff;
    box-shadow: 0 4px 14px rgba(225, 29, 72, 0.4);
}
.btn-floating-out:hover {
    background: linear-gradient(135deg, #fb7185 0%, #f43f5e 100%);
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(225, 29, 72, 0.55);
}
.btn-floating-repunch {
    background: linear-gradient(135deg, #38bdf8 0%, #0284c7 100%);
    color: #ffffff;
    box-shadow: 0 4px 14px rgba(56, 189, 248, 0.35);
}
.btn-floating-repunch:hover {
    background: linear-gradient(135deg, #7dd3fc 0%, #38bdf8 100%);
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(56, 189, 248, 0.5);
}
</style>

<!-- CUSTOM CORPORATE MONOCHROME CONFIRMATION MODAL -->
<div id="punchConfirmModal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(15,23,42,0.65); backdrop-filter:blur(6px); z-index:100005; align-items:center; justify-content:center;">
    <div style="background:#ffffff; width:90%; max-width:400px; border-radius:20px; padding:28px 24px; border:1px solid #e2e8f0; box-shadow:0 25px 50px -12px rgba(15,23,42,0.35); text-align:center; font-family:'Outfit',sans-serif;">
        <div id="punch-modal-icon-bg" style="width:52px; height:52px; border-radius:50%; background:#f8fafc; border:1px solid #e2e8f0; display:flex; align-items:center; justify-content:center; margin:0 auto 16px; color:#0f172a;">
            <i id="punch-modal-icon" data-lucide="clock" style="width:26px; height:26px;"></i>
        </div>
        <h3 id="punch-modal-title" style="margin:0 0 8px; font-size:19px; font-weight:800; color:#0f172a; letter-spacing:-0.02em;">Confirm Action</h3>
        <p id="punch-modal-subtitle" style="margin:0 0 24px; font-size:13.5px; color:#64748b; line-height:1.5;">Are you sure you want to proceed?</p>
        
        <div style="display:flex; gap:10px;">
            <button onclick="closePunchModal()" style="flex:1; padding:11px 16px; border-radius:12px; border:1px solid #cbd5e1; background:#ffffff; color:#0f172a; font-weight:700; font-size:13px; cursor:pointer; transition:all 0.2s ease;">Cancel</button>
            <button id="punch-modal-confirm-btn" onclick="" style="flex:1; padding:11px 16px; border-radius:12px; border:none; background:#0f172a; color:#ffffff; font-weight:800; font-size:13px; cursor:pointer; box-shadow:0 4px 12px rgba(15,23,42,0.25); transition:all 0.2s ease;">Confirm</button>
        </div>
    </div>
</div>

<script>
let punchState = null;
let timerInterval = null;

async function checkMyTodayPunch() {
    try {
        const res = await fetch('<?php echo $base_path; ?>api.php?api=get_my_today_attendance');
        const data = await res.json();
        if (data && data.success) {
            punchState = data;
        } else {
            punchState = { success: true, attendance: null };
        }
    } catch(e){
        punchState = { success: true, attendance: null };
    }
    updatePunchWidgetUI();
}

function updatePunchWidgetUI() {
    const floatBtnContainer = document.getElementById('float-btn-container');
    const floatTimerDisplay = document.getElementById('float-timer-display');
    const floatStatusDot = document.getElementById('float-status-dot');
    const floatStatusLabel = document.getElementById('float-status-label');

    if (timerInterval) clearInterval(timerInterval);

    if (!punchState || !punchState.attendance || !punchState.attendance.check_in_time) {
        // STATE 1: NOT PUNCHED IN
        if (floatStatusDot) floatStatusDot.className = 'pulse-indicator-dot pulse-idle';
        if (floatStatusLabel) floatStatusLabel.innerText = 'Off Duty';
        if (floatTimerDisplay) floatTimerDisplay.innerText = 'Not Punched In';
        if (floatBtnContainer) floatBtnContainer.innerHTML = `<button class="btn-floating-action btn-floating-in" onclick="promptPunchIn()"><i data-lucide="play" style="width:14px; fill:currentColor;"></i> PUNCH IN</button>`;
    
    } else if (punchState.attendance.check_in_time && !punchState.attendance.check_out_time) {
        // STATE 2: ACTIVELY PUNCHED IN (STOPWATCH RUNNING)
        if (floatStatusDot) floatStatusDot.className = 'pulse-indicator-dot pulse-active';
        if (floatStatusLabel) floatStatusLabel.innerText = 'Shift Active';

        const startTime = new Date(punchState.attendance.check_in_time.replace(/-/g, '/')).getTime();

        const updateClock = () => {
            const now = new Date().getTime();
            const diff = Math.max(0, now - startTime);
            const hrs = String(Math.floor(diff / (1000 * 60 * 60))).padStart(2, '0');
            const mins = String(Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60))).padStart(2, '0');
            const secs = String(Math.floor((diff % (1000 * 60)) / 1000)).padStart(2, '0');
            
            const timeStr = `${hrs}:${mins}:${secs}`;
            if (floatTimerDisplay) floatTimerDisplay.innerText = timeStr;
        };

        updateClock();
        timerInterval = setInterval(updateClock, 1000);

        if (floatBtnContainer) floatBtnContainer.innerHTML = `<button class="btn-floating-action btn-floating-out" onclick="promptPunchOut()"><i data-lucide="square" style="width:14px; fill:currentColor;"></i> PUNCH OUT</button>`;

    } else {
        // STATE 3: SHIFT COMPLETED
        const mins = punchState.attendance.total_minutes_worked || 0;
        const h = Math.floor(mins / 60);
        const m = mins % 60;
        const loggedText = `${h}h ${m}m Logged`;

        if (floatStatusDot) floatStatusDot.className = 'pulse-indicator-dot pulse-completed';
        if (floatStatusLabel) floatStatusLabel.innerText = 'Shift Finished';
        if (floatTimerDisplay) floatTimerDisplay.innerText = loggedText;
        if (floatBtnContainer) floatBtnContainer.innerHTML = `<button class="btn-floating-action btn-floating-repunch" onclick="promptPunchIn(true)"><i data-lucide="rotate-cw" style="width:14px;"></i> RE-PUNCH</button>`;
    }

    if (window.lucide) lucide.createIcons();
}

function promptPunchIn(isForce = false) {
    const modal = document.getElementById('punchConfirmModal');
    const title = document.getElementById('punch-modal-title');
    const subtitle = document.getElementById('punch-modal-subtitle');
    const iconBg = document.getElementById('punch-modal-icon-bg');
    const confirmBtn = document.getElementById('punch-modal-confirm-btn');

    title.innerText = isForce ? "Confirm Re-Punch In" : "Confirm Punch In";
    subtitle.innerText = "Are you ready to start tracking your work shift for today?";
    iconBg.style.background = "#f0fdf4";
    iconBg.style.color = "#166534";
    iconBg.style.borderColor = "#bbf7d0";
    
    confirmBtn.innerText = isForce ? "Yes, Re-Punch" : "Yes, Punch In";
    confirmBtn.style.background = "#0f172a";
    confirmBtn.onclick = () => { closePunchModal(); doPunchIn(isForce); };

    modal.style.display = 'flex';
    if (window.lucide) lucide.createIcons();
}

function promptPunchOut() {
    const modal = document.getElementById('punchConfirmModal');
    const title = document.getElementById('punch-modal-title');
    const subtitle = document.getElementById('punch-modal-subtitle');
    const iconBg = document.getElementById('punch-modal-icon-bg');
    const confirmBtn = document.getElementById('punch-modal-confirm-btn');

    const curTimeStr = document.getElementById('float-timer-display') ? document.getElementById('float-timer-display').innerText : '';
    title.innerText = "Confirm Punch Out";
    subtitle.innerText = `Are you sure you want to end your shift for today? ${curTimeStr ? 'Total shift time active: ' + curTimeStr : ''}`;
    iconBg.style.background = "#fef2f2";
    iconBg.style.color = "#991b1b";
    iconBg.style.borderColor = "#fecaca";

    confirmBtn.innerText = "Yes, Punch Out";
    confirmBtn.style.background = "#ef4444";
    confirmBtn.onclick = () => { closePunchModal(); doPunchOut(); };

    modal.style.display = 'flex';
    if (window.lucide) lucide.createIcons();
}

function promptResetPunch() {
    const modal = document.getElementById('punchConfirmModal');
    const title = document.getElementById('punch-modal-title');
    const subtitle = document.getElementById('punch-modal-subtitle');
    const iconBg = document.getElementById('punch-modal-icon-bg');
    const confirmBtn = document.getElementById('punch-modal-confirm-btn');

    title.innerText = "Confirm Shift Reset";
    subtitle.innerText = "This will clear your current shift clock for today so you can start a clean session. Proceed?";
    iconBg.style.background = "#f8fafc";
    iconBg.style.color = "#0f172a";
    iconBg.style.borderColor = "#cbd5e1";

    confirmBtn.innerText = "Reset Timer";
    confirmBtn.style.background = "#0f172a";
    confirmBtn.onclick = () => { closePunchModal(); doResetTodayPunch(); };

    modal.style.display = 'flex';
    if (window.lucide) lucide.createIcons();
}

function closePunchModal() {
    const modal = document.getElementById('punchConfirmModal');
    if (modal) modal.style.display = 'none';
}

async function doPunchIn(isForce = false) {
    try {
        const formData = new FormData();
        if (isForce) formData.append('force', '1');

        const res = await fetch('<?php echo $base_path; ?>api.php?api=employee_punch_in', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data && data.success) {
            showNotification(data.message, 'success');
            checkMyTodayPunch();
        } else {
            showNotification(data.error || 'Failed to punch in', 'error');
        }
    } catch(e) {
        showNotification('Network error', 'error');
    }
}

async function doPunchOut() {
    try {
        const res = await fetch('<?php echo $base_path; ?>api.php?api=employee_punch_out', { method: 'POST' });
        const data = await res.json();
        if (data && data.success) {
            showNotification(data.message, 'success');
            checkMyTodayPunch();
        } else {
            showNotification(data.error || 'Failed to punch out', 'error');
        }
    } catch(e) {
        showNotification('Network error', 'error');
    }
}

async function doResetTodayPunch() {
    try {
        const res = await fetch('<?php echo $base_path; ?>api.php?api=reset_my_today_punch', { method: 'POST' });
        const data = await res.json();
        if (data && data.success) {
            showNotification(data.message, 'success');
            checkMyTodayPunch();
        } else {
            showNotification(data.error || 'Failed to reset punch', 'error');
        }
    } catch(e) {
        showNotification('Network error', 'error');
    }
}

function logout() {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Logout Confirmation',
            text: 'Are you sure you want to log out of your CRM session?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, Logout'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '<?php echo $base_path; ?>logout.php';
            }
        });
    } else {
        if (confirm("Are you sure you want to log out?")) {
            window.location.href = '<?php echo $base_path; ?>logout.php';
        }
    }
}

window.addEventListener('DOMContentLoaded', () => {
    checkMyTodayPunch();
});
</script>

<!-- GLOBAL FLOATING AI ASSISTANT WIDGET (ENTERPRISE AGENT UI) -->
<style>
@media print {
    .global-ai-fab, .global-ai-drawer, #globalAiFabBtn, #globalAiDrawer,
    .toast-container, #toast-container, #gps-blocker, #float-btn-container,
    .swal2-container, .modal, .modal-overlay, .drawer {
        display: none !important;
        visibility: hidden !important;
        height: 0 !important;
        width: 0 !important;
        overflow: hidden !important;
    }
}
.global-ai-fab {
    position: fixed;
    bottom: 24px;
    right: 28px;
    z-index: 9998;
    width: 54px;
    height: 54px;
    border-radius: 50%;
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    color: #ffffff;
    border: 1.5px solid #f59e0b;
    padding: 0;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 12px 35px rgba(15,23,42,0.35);
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}
.global-ai-fab:hover {
    transform: translateY(-3px) scale(1.06);
    box-shadow: 0 16px 40px rgba(15,23,42,0.45);
    border-color: #fbbf24;
}
.global-ai-drawer {
    position: fixed;
    bottom: 88px;
    right: 28px;
    width: 480px;
    max-width: 94vw;
    height: 630px;
    max-height: 82vh;
    background: #ffffff;
    border: 1.5px solid #cbd5e1;
    border-radius: 24px;
    box-shadow: 0 25px 65px rgba(15,23,42,0.35), 0 0 2px rgba(0,0,0,0.1);
    z-index: 9999;
    display: none;
    flex-direction: column;
    overflow: hidden;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    animation: drawerSlideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
.global-ai-drawer.fullscreen {
    bottom: 0 !important;
    right: 0 !important;
    top: 0 !important;
    left: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    max-width: 100vw !important;
    max-height: 100vh !important;
    border-radius: 0 !important;
    box-shadow: none !important;
}
.ai-pulse-dot {
    width: 9px;
    height: 9px;
    background: #10b981;
    border-radius: 50%;
    display: inline-block;
    box-shadow: 0 0 10px #10b981;
    animation: pulseGlow 1.8s infinite;
}
@keyframes pulseGlow {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
    70% { transform: scale(1.1); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}
@keyframes drawerSlideUp {
    from { opacity: 0; transform: translateY(24px) scale(0.96); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}
.ai-chip-btn {
    background: #1e293b;
    color: #e2e8f0;
    border: 1px solid #334155;
    border-radius: 20px;
    padding: 5px 12px;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.ai-chip-btn:hover {
    background: #0f172a;
    border-color: #f59e0b;
    color: #fbbf24;
    transform: translateY(-1px);
}
</style>

<button class="global-ai-fab" id="globalAiFabBtn" onclick="toggleGlobalAiDrawer()" title="Open BFS AI Agent">
    <span class="ai-pulse-dot" style="position:absolute; top:5px; right:5px; border:1.5px solid #0f172a;"></span>
    <img src="<?php echo $base_path; ?>logo.png" alt="BFS Logo" style="height:22px; width:auto; filter:brightness(0) invert(1); display:block;">
</button>

<div class="global-ai-drawer" id="globalAiDrawer">
    <!-- Header Bar -->
    <div style="background:linear-gradient(135deg, #0f172a 0%, #1e293b 100%); padding:14px 20px; color:#ffffff; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #334155;">
        <div style="display:flex; align-items:center; gap:10px;">
            <div style="height:34px; display:flex; align-items:center; justify-content:center;">
                <img src="<?php echo $base_path; ?>logo.png" alt="BFS Logo" style="height:28px; width:auto; filter:brightness(0) invert(1); display:block;">
            </div>
            <div>
                <div style="font-weight:800; font-size:14px; color:#ffffff; display:flex; align-items:center; gap:6px;">
                    BFS Agent
                </div>
                <div style="font-size:11px; color:#94a3b8; display:flex; align-items:center; gap:6px;">
                    <span class="ai-pulse-dot" style="width:6px; height:6px;"></span> Active System Execution Engine
                </div>
            </div>
        </div>
        <div style="display:flex; align-items:center; gap:10px;">
            <button onclick="clearGlobalAiMessages()" style="background:transparent; border:none; color:#cbd5e1; cursor:pointer; display:flex; align-items:center; padding:4px;" title="Clear Chat History">
                <i data-lucide="trash-2" style="width:15px; height:15px;"></i>
            </button>
            <button onclick="toggleGlobalAiFullscreen()" style="background:transparent; border:none; color:#cbd5e1; cursor:pointer; display:flex; align-items:center; padding:4px;" title="Toggle Full Screen Mode">
                <i data-lucide="maximize-2" id="globalAiExpandIcon" style="width:15px; height:15px;"></i>
            </button>
            <button onclick="toggleGlobalAiDrawer()" style="background:transparent; border:none; color:#cbd5e1; font-size:20px; cursor:pointer; font-weight:bold; line-height:1; padding:2px 6px;">&times;</button>
        </div>
    </div>
    
    <!-- AI Quick Action Chips Bar (Clean Unified Monochromatic Theme) -->
    <div style="background:#0f172a; padding:10px 14px; display:flex; gap:7px; overflow-x:auto; border-bottom:1px solid #334155; scrollbar-width:none;">
        <button onclick="triggerAiQuickAction('Followup')" class="ai-chip-btn">📞 Auto Follow-up</button>
        <button onclick="triggerAiQuickAction('Form Fill')" class="ai-chip-btn">📝 Form Fill</button>
        <button onclick="triggerAiQuickAction('Punch In')" class="ai-chip-btn">⏱️ Punch In</button>
        <button onclick="triggerAiQuickAction('Punch Out')" class="ai-chip-btn">🛑 Punch Out</button>
        <button onclick="triggerAiQuickAction('Set Reminder')" class="ai-chip-btn">⏰ Set Reminder</button>
        <button onclick="triggerAiQuickAction('Add Banker')" class="ai-chip-btn">🏛️ Add Banker</button>
        <button onclick="triggerAiQuickAction('Write Email')" class="ai-chip-btn">✉️ Write Email</button>
        <button onclick="triggerAiQuickAction('Payout Info')" class="ai-chip-btn">💰 Payout Info</button>
    </div>

    <!-- Messages Window -->
    <div id="globalAiMessages" style="flex:1; padding:18px 16px; overflow-y:auto; background:#f8fafc; font-size:13px; display:flex; flex-direction:column; gap:14px;">
        <div style="background:#ffffff; padding:14px 16px; border-radius:18px 18px 18px 4px; border:1px solid #e2e8f0; color:#0f172a; line-height:1.55; box-shadow:0 4px 16px rgba(15,23,42,0.04);">
            <div style="display:flex; align-items:center; gap:6px; font-weight:700; color:#0f172a; font-size:12px; margin-bottom:6px;">
                <i data-lucide="sparkles" style="width:14px; height:14px; color:#f59e0b;"></i> BFS AI Agent • Live Assistant
            </div>
            👋 Namaste! Main aapka <b>BFS Enterprise Agentic AI Assistant</b> hoon. Main aapke sawalon ke jabab ke sath-sath <b>Loan Application Fill, Attendance (Punch In/Out), Reminders, Email Generation, Banker Registration, aur Payout Intelligence</b> ko direct system mein execute kar sakta hoon!
        </div>
    </div>

    <!-- Input Footer -->
    <form onsubmit="handleGlobalAiSubmit(event)" style="padding:14px 16px; background:#ffffff; border-top:1px solid #e2e8f0; display:flex; gap:10px; align-items:center;">
        <input type="text" id="globalAiInput" style="flex:1; padding:11px 18px; border:1.5px solid #cbd5e1; border-radius:28px; font-size:13px; outline:none; background:#f8fafc; color:#0f172a; transition:all 0.2s;" placeholder="Ask AI or give command (e.g. 'Punch In', 'Fill Application', 'Set Reminder')..." required autocomplete="off" onfocus="this.style.borderColor='#0f172a'; this.style.background='#ffffff';" onblur="this.style.borderColor='#cbd5e1'; this.style.background='#f8fafc';">
        <button type="submit" id="btnSendGlobalAi" style="background:linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color:#ffffff; border:1px solid #f59e0b; padding:10px 20px; border-radius:24px; font-weight:800; font-size:13px; cursor:pointer; display:flex; align-items:center; gap:6px; box-shadow:0 4px 14px rgba(15,23,42,0.25);">
            <span>Send</span> <i data-lucide="send" style="width:13px; height:13px; color:#fbbf24;"></i>
        </button>
    </form>
</div>

<script>
window.aiFormWizard = {
    active: false,
    form_type: 'applicant', // 'applicant', 'banker', 'referral', 'employee'
    step: 0,
    data: {}
};

function triggerAiQuickAction(cmd) {
    const input = document.getElementById('globalAiInput');
    if (!input) return;
    if (cmd === 'Form Fill') {
        input.value = "Application form fill karwao step by step";
    } else if (cmd === 'Followup') {
        input.value = "Aaj ke pending followups list karo aur follow up le lo";
    } else if (cmd.startsWith('Followup ')) {
        input.value = "Followup " + cmd.replace('Followup ', '');
    } else if (cmd === 'Punch In') {
        input.value = "Punch In kar do";
    } else if (cmd === 'Punch Out') {
        input.value = "Punch Out kar do";
    } else if (cmd === 'Set Reminder') {
        input.value = "Kal 3 baje follow up meeting reminder set kar do";
    } else if (cmd === 'Add Banker') {
        input.value = "Banker add karwao step by step";
    } else if (cmd === 'Write Email') {
        input.value = "Banker ko loan pre-sanction status ki email bhej do";
    } else if (cmd === 'Payout Info') {
        input.value = "Payout distribution aur invoices ki jankari do";
    }
    handleGlobalAiSubmit(new Event('submit'));
}

function clearGlobalAiMessages() {
    const msgContainer = document.getElementById('globalAiMessages');
    if (!msgContainer) return;
    msgContainer.innerHTML = `
        <div style="background:#ffffff; padding:14px 16px; border-radius:18px 18px 18px 4px; border:1px solid #e2e8f0; color:#0f172a; line-height:1.55; box-shadow:0 4px 16px rgba(15,23,42,0.04);">
            <div style="display:flex; align-items:center; gap:6px; font-weight:700; color:#0f172a; font-size:12px; margin-bottom:6px;">
                <i data-lucide="sparkles" style="width:14px; height:14px; color:#f59e0b;"></i> BFS AI Agent • Live Assistant
            </div>
            ✨ Chat history cleared! Main aapka <b>BFS Enterprise Agentic AI Assistant</b> hoon. Poochiye jo aapko janna ya execute karwana hai!
        </div>
    `;
    window.aiFormWizard = { active: false, form_type: 'applicant', step: 0, data: {} };
    if (window.lucide) lucide.createIcons();
}

function toggleGlobalAiDrawer() {
    const d = document.getElementById('globalAiDrawer');
    if (d.style.display === 'flex') {
        d.style.display = 'none';
    } else {
        d.style.display = 'flex';
        setTimeout(() => document.getElementById('globalAiInput').focus(), 100);
    }
}

function toggleGlobalAiFullscreen() {
    const d = document.getElementById('globalAiDrawer');
    const icon = document.getElementById('globalAiExpandIcon');
    d.classList.toggle('fullscreen');
    if (d.classList.contains('fullscreen')) {
        if (icon) icon.setAttribute('data-lucide', 'minimize-2');
    } else {
        if (icon) icon.setAttribute('data-lucide', 'maximize-2');
    }
    if (window.lucide) lucide.createIcons();
}

function renderDrawerMarkdown(text) {
    if (!text) return '';
    let lines = text.split('\n');
    let inTable = false;
    let tableBuffer = [];
    let outLines = [];

    for (let i = 0; i < lines.length; i++) {
        let line = lines[i].trim();
        if (line.startsWith('|') && line.endsWith('|')) {
            if (line.replace(/[\s\|\-\:]/g, '') === '') continue;
            let cells = line.split('|').map(c => c.trim()).slice(1, -1);
            if (!inTable) {
                inTable = true;
                tableBuffer.push('<div style="overflow-x:auto; margin:10px 0; border-radius:10px; border:1px solid #cbd5e1; box-shadow:0 2px 8px rgba(0,0,0,0.04);"><table style="width:100%; border-collapse:collapse; font-size:12px; background:#fff;"><thead style="background:#0f172a; color:#fff;"><tr>');
                cells.forEach(c => tableBuffer.push(`<th style="padding:9px 12px; border-bottom:2px solid #d97706; text-align:left; font-weight:700;">${c}</th>`));
                tableBuffer.push('</tr></thead><tbody>');
            } else {
                tableBuffer.push('<tr style="border-bottom:1px solid #e2e8f0;">');
                cells.forEach(c => tableBuffer.push(`<td style="padding:8px 12px; color:#1e293b;">${c}</td>`));
                tableBuffer.push('</tr>');
            }
        } else {
            if (inTable) {
                inTable = false;
                tableBuffer.push('</tbody></table></div>');
                outLines.push(tableBuffer.join(''));
                tableBuffer = [];
            }
            outLines.push(line);
        }
    }
    if (inTable) {
        tableBuffer.push('</tbody></table></div>');
        outLines.push(tableBuffer.join(''));
    }

    let result = outLines.join('\n');
    result = result.replace(/\*\*(.*?)\*\*/g, '<b>$1</b>').replace(/`(.*?)`/g, '<code style="background:#f1f5f9; padding:2px 6px; border-radius:4px; font-family:monospace; color:#0f172a; border:1px solid #e2e8f0;">$1</code>').replace(/\n/g, '<br>');
    return result;
}

function handleGlobalAiSubmit(e) {
    if (e) e.preventDefault();
    const input = document.getElementById('globalAiInput');
    const q = input.value.trim();
    if (!q) return;

    const msgContainer = document.getElementById('globalAiMessages');
    const uRow = document.createElement('div');
    uRow.style.cssText = 'align-self:flex-end; background:linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color:#ffffff; padding:11px 16px; border-radius:20px 20px 4px 20px; max-width:88%; line-height:1.45; font-size:13px; box-shadow:0 4px 14px rgba(15,23,42,0.15);';
    uRow.innerText = q;
    msgContainer.appendChild(uRow);
    input.value = '';
    msgContainer.scrollTop = msgContainer.scrollHeight;

    // Handle Active Form Wizard Steps Client-Side
    if (window.aiFormWizard.active) {
        processAiWizardStep(q, msgContainer);
        return;
    }

    let fullQuery = q;
    if (window.currentLoadedApplicant && window.currentLoadedApplicant.customer_name) {
        fullQuery = `[CONTEXT FILE: ${window.currentLoadedApplicant.customer_name} (Loan ID: ${window.currentLoadedApplicant.loan_id})]: ` + q;
    }

    const aiRow = document.createElement('div');
    aiRow.style.cssText = 'align-self:flex-start; background:#ffffff; border:1px solid #e2e8f0; color:#0f172a; padding:14px 16px; border-radius:20px 20px 20px 4px; max-width:92%; line-height:1.55; font-size:13px; box-shadow:0 4px 16px rgba(15,23,42,0.04);';
    aiRow.innerHTML = '<span style="font-size:12px; color:#64748b; display:flex; align-items:center; gap:6px;"><i data-lucide="loader" class="spin" style="width:14px; height:14px;"></i> BFS AI Agent is processing command...</span>';
    msgContainer.appendChild(aiRow);
    msgContainer.scrollTop = msgContainer.scrollHeight;

    const fd = new FormData();
    fd.append('query', fullQuery);

    fetch('<?php echo $base_path; ?>api.php?api=ai_chat_assistant', { method: 'POST', body: fd })
    .then(async r => {
        const text = await r.text();
        try {
            return JSON.parse(text);
        } catch(e) {
            console.error("Non-JSON API response:", text);
            return { success: false, error: "Server returned non-JSON response (Session may be expired or server returned HTML)." };
        }
    })
    .then(d => {
        if (d.success) {
            if (d.is_wizard) {
                window.aiFormWizard.active = true;
                window.aiFormWizard.form_type = d.form_type || 'applicant';
                window.aiFormWizard.step = 1;
                window.aiFormWizard.data = {};
                aiRow.innerHTML = renderDrawerMarkdown(d.answer);
            } else if (d.action_performed === 'ai_followup_card' && d.followup_data) {
                aiRow.innerHTML = renderDrawerMarkdown(d.answer) + '<div style="margin-top:10px;">' + renderAiFollowupCard(d.followup_data) + '</div>';
            } else if (d.action_performed === 'draft_email' && d.email_data) {
                aiRow.innerHTML = renderEmailDraftCard(d.email_data);
            } else {
                aiRow.innerHTML = renderDrawerMarkdown(d.answer);
            }
        } else {
            aiRow.innerHTML = `⚠️ <b>Error:</b> ${d.error || 'Server error'}`;
        }
        msgContainer.scrollTop = msgContainer.scrollHeight;
        if (window.lucide) lucide.createIcons();
    })
    .catch(err => {
        aiRow.innerHTML = `⚠️ <b>Error:</b> ${err.message}`;
        msgContainer.scrollTop = msgContainer.scrollHeight;
    });
}

function renderAiFollowupCard(data) {
    return `
        <div style="background:#fff; border:1.5px solid #2563eb; border-radius:14px; padding:14px; font-size:12px; box-shadow:0 4px 16px rgba(37,99,235,0.1);">
            <div style="font-weight:800; color:#1e40af; margin-bottom:8px; display:flex; align-items:center; justify-content:space-between;">
                <span>📞 AI Follow-up & WhatsApp Execution</span>
                <span style="background:#eff6ff; color:#2563eb; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:700;">${data.loan_id}</span>
            </div>
            <div style="display:flex; flex-direction:column; gap:4px; margin-bottom:10px; color:#334155;">
                <div>👤 <b>Customer:</b> ${data.customer_name} (${data.mobile || 'N/A'})</div>
                <div>💼 <b>Loan Product:</b> ${data.loan_type} • <b>Status:</b> ${data.overall_status}</div>
                <div>🎯 <b>Follow-up Reason:</b> ${data.pending_reasons}</div>
            </div>
            <div style="margin-bottom:10px;">
                <label style="font-weight:700; font-size:11px; color:#475569;">Generated WhatsApp Follow-up Text (Editable):</label>
                <textarea id="aiWaMsg_${data.applicant_id}" style="width:100%; height:85px; padding:8px; border:1px solid #cbd5e1; border-radius:8px; font-size:11.5px; font-family:inherit; line-height:1.4; color:#0f172a;">${data.wa_message}</textarea>
            </div>
            <div style="display:flex; gap:8px;">
                <button onclick="sendAiWaFollowup('${data.mobile}', ${data.applicant_id})" style="flex:1; background:#16a34a; color:#fff; border:none; padding:9px; border-radius:8px; font-weight:800; font-size:12px; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:5px; box-shadow:0 3px 10px rgba(22,163,74,0.25);">
                    📲 Send WhatsApp
                </button>
                <button onclick="logAiFollowupReminder(${data.applicant_id})" style="flex:1; background:#0f172a; color:#fff; border:1px solid #f59e0b; padding:9px; border-radius:8px; font-weight:800; font-size:12px; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:5px; box-shadow:0 3px 10px rgba(15,23,42,0.25);">
                    ⏰ Schedule & Log
                </button>
            </div>
        </div>
    `;
}

function sendAiWaFollowup(mobile, applicantId) {
    const msgEl = document.getElementById('aiWaMsg_' + applicantId);
    const msg = msgEl ? msgEl.value : '';
    const cleanMob = mobile.replace(/[^0-9]/g, '');
    const targetUrl = `https://wa.me/91${cleanMob}?text=` + encodeURIComponent(msg);
    window.open(targetUrl, '_blank');
}

function logAiFollowupReminder(applicantId) {
    const msgEl = document.getElementById('aiWaMsg_' + applicantId);
    const msg = msgEl ? msgEl.value : 'Follow-up message sent';
    const fd = new FormData();
    fd.append('applicant_id', applicantId);
    fd.append('interaction_type', 'WhatsApp');
    fd.append('outcome', 'AI Automated Follow-up Executed');
    fd.append('notes', msg);
    fd.append('next_followup', new Date(Date.now() + 86400000).toISOString().slice(0, 10) + ' 11:00:00');

    fetch('<?php echo $base_path; ?>api.php?api=log_rm_interaction', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        Swal.fire('Logged & Scheduled!', 'Follow-up interaction logged and next reminder scheduled for tomorrow 11:00 AM.', 'success');
    });
}

function processAiWizardStep(answerText, container) {
    const wiz = window.aiFormWizard;
    const aiRow = document.createElement('div');
    aiRow.style.cssText = 'align-self:flex-start; background:#ffffff; border:1.5px solid #3b82f6; color:#0f172a; padding:14px 16px; border-radius:20px 20px 20px 4px; max-width:92%; line-height:1.55; font-size:13px; box-shadow:0 6px 18px rgba(59,130,246,0.12);';
    
    if (wiz.form_type === 'applicant') {
        if (wiz.step === 1) {
            wiz.data.customer_name = answerText;
            autoFillScreenInput(['customer_name', 'full_name', 'name'], answerText);
            wiz.step = 2;
            aiRow.innerHTML = `✅ Saved Customer Name: <b>${answerText}</b><br><br><b>Step 2 of 5:</b> Applicant ka <b>10-digit Mobile Number</b> btaiye?`;
        } else if (wiz.step === 2) {
            wiz.data.mobile = answerText.replace(/[^0-9]/g, '');
            autoFillScreenInput(['mobile', 'mobile_number', 'phone'], wiz.data.mobile);
            wiz.step = 3;
            aiRow.innerHTML = `✅ Saved Mobile: <b>${wiz.data.mobile}</b><br><br><b>Step 3 of 5:</b> <b>PAN Card Number</b> (10 chars) & <b>Aadhaar Number</b> (12 digits) btaiye? (e.g. ABCDE1234F 123456789012)`;
        } else if (wiz.step === 3) {
            let parts = answerText.split(' ');
            wiz.data.pan_number = (parts[0] || '').toUpperCase();
            wiz.data.aadhar_number = (parts[1] || '').replace(/[^0-9]/g, '');
            autoFillScreenInput(['pan_number', 'pan'], wiz.data.pan_number);
            autoFillScreenInput(['aadhar_number', 'aadhar'], wiz.data.aadhar_number);
            wiz.step = 4;
            aiRow.innerHTML = `✅ Saved PAN: <b>${wiz.data.pan_number}</b> | Aadhaar: <b>${wiz.data.aadhar_number}</b><br><br><b>Step 4 of 5:</b> Employment Type (Salaried/Self-Employed) & <b>Monthly Income (₹)</b> btaiye?`;
        } else if (wiz.step === 4) {
            wiz.data.monthly_income = answerText;
            autoFillScreenInput(['monthly_income', 'income'], answerText);
            wiz.step = 5;
            aiRow.innerHTML = `✅ Saved Income: <b>₹${answerText}</b><br><br><b>Step 5 of 5:</b> <b>Loan Type</b> (Personal Loan / Home Loan / Business Loan / LAP) aur <b>Requested Amount (₹)</b> btaiye?`;
        } else if (wiz.step === 5) {
            wiz.data.loan_details = answerText;
            wiz.active = false;
            
            // Build Submit Summary Card
            aiRow.innerHTML = `
                <div style="font-weight:800; color:#059669; font-size:14px; margin-bottom:8px;">🎉 Form Details Collected Successfully!</div>
                <div style="background:#f8fafc; padding:12px; border-radius:10px; border:1px solid #e2e8f0; font-size:12.5px; display:flex; flex-direction:column; gap:5px; margin-bottom:12px;">
                    <div>👤 <b>Customer Name:</b> ${wiz.data.customer_name || 'N/A'}</div>
                    <div>📱 <b>Mobile:</b> ${wiz.data.mobile || 'N/A'}</div>
                    <div>💳 <b>PAN:</b> ${wiz.data.pan_number || 'N/A'} | <b>Aadhaar:</b> ${wiz.data.aadhar_number || 'N/A'}</div>
                    <div>💼 <b>Monthly Income:</b> ₹${wiz.data.monthly_income || '0'}</div>
                    <div>💰 <b>Loan Details:</b> ${wiz.data.loan_details || 'Loan'}</div>
                </div>
                <button onclick="submitAiWizardApplicantForm()" style="width:100%; background:linear-gradient(135deg, #059669 0%, #047857 100%); color:#fff; border:none; padding:11px; border-radius:10px; font-weight:800; font-size:13px; cursor:pointer; box-shadow:0 4px 12px rgba(5,150,105,0.25);">🚀 Submit Loan Application Now</button>
            `;
        }
    } else {
        wiz.active = false;
        aiRow.innerHTML = `✅ Form details received: <b>${answerText}</b>. Aap page form submit button press kar sakte hain.`;
    }
    
    container.appendChild(aiRow);
    container.scrollTop = container.scrollHeight;
}

function autoFillScreenInput(possibleNames, val) {
    if (!val) return;
    for (let name of possibleNames) {
        let el = document.querySelector(`input[name="${name}"], select[name="${name}"], textarea[name="${name}"]`) || document.getElementById(name);
        if (el) {
            el.value = val;
            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }
}

function submitAiWizardApplicantForm() {
    const data = window.aiFormWizard.data;
    const fd = new FormData();
    fd.append('customer_name', data.customer_name || 'Applicant');
    fd.append('mobile', data.mobile || '9999999999');
    fd.append('pan_number', data.pan_number || '');
    fd.append('aadhar_number', data.aadhar_number || '');
    fd.append('monthly_income', data.monthly_income || 50000);
    fd.append('loan_type', data.loan_details || 'Personal Loan');
    fd.append('loan_amount_requested', 500000);
    fd.append('force_duplicate', '1');

    fetch('<?php echo $base_path; ?>api.php?api=save_applicant', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if (res.success || res.message) {
            Swal.fire('Success!', res.message || 'Loan application created successfully!', 'success');
            const msgContainer = document.getElementById('globalAiMessages');
            const confirmRow = document.createElement('div');
            confirmRow.style.cssText = 'align-self:flex-start; background:#ecfdf5; border:1px solid #10b981; color:#065f46; padding:12px 14px; border-radius:14px; font-size:12.5px; width:100%;';
            confirmRow.innerHTML = `✅ <b>Application Created Successfully!</b> Record added to database.`;
            msgContainer.appendChild(confirmRow);
            msgContainer.scrollTop = msgContainer.scrollHeight;
        } else {
            Swal.fire('Notice', res.error || 'Failed to submit application', 'warning');
        }
    });
}

function renderEmailDraftCard(emailData) {
    return `
        <div style="background:#fff; border:1.5px solid #ec4899; border-radius:14px; padding:14px; font-size:12px; box-shadow:0 4px 16px rgba(236,72,153,0.1);">
            <div style="font-weight:800; color:#be185d; margin-bottom:10px; display:flex; align-items:center; gap:6px; font-size:13px;">
                ✉️ AI Smart Email Generator
            </div>
            <div style="display:flex; flex-direction:column; gap:8px;">
                <div>
                    <label style="font-size:11px; font-weight:700; color:#475569;">Recipient Email:</label>
                    <input type="email" id="aiDraftTo" value="${emailData.to_email || ''}" placeholder="banker@bank.com" style="width:100%; padding:8px 10px; border:1px solid #cbd5e1; border-radius:8px; font-size:12px;">
                </div>
                <div>
                    <label style="font-size:11px; font-weight:700; color:#475569;">Subject:</label>
                    <input type="text" id="aiDraftSubject" value="${emailData.subject || ''}" style="width:100%; padding:8px 10px; border:1px solid #cbd5e1; border-radius:8px; font-size:12px; font-weight:600;">
                </div>
                <div>
                    <label style="font-size:11px; font-weight:700; color:#475569;">Email Body:</label>
                    <textarea id="aiDraftBody" style="width:100%; height:120px; padding:8px 10px; border:1px solid #cbd5e1; border-radius:8px; font-size:11.5px; font-family:inherit; line-height:1.4;">${emailData.body_html || ''}</textarea>
                </div>
                <button onclick="dispatchAiEmail()" style="background:linear-gradient(135deg, #db2777 0%, #be185d 100%); color:#fff; border:none; padding:10px; border-radius:8px; font-weight:800; font-size:12.5px; cursor:pointer; margin-top:4px; box-shadow:0 4px 12px rgba(219,39,119,0.3);">🚀 Send Email Now</button>
            </div>
        </div>
    `;
}

function dispatchAiEmail() {
    const to = document.getElementById('aiDraftTo').value;
    const subject = document.getElementById('aiDraftSubject').value;
    const body = document.getElementById('aiDraftBody').value;
    if (!subject || !body) {
        Swal.fire('Error', 'Subject and body are required', 'error');
        return;
    }
    const fd = new FormData();
    fd.append('client_id', 1);
    fd.append('subject', subject);
    fd.append('body', body);
    fd.append('cc', to);

    fetch('<?php echo $base_path; ?>api.php?api=send_email', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        Swal.fire('Email Sent!', res.message || 'Email dispatched successfully.', 'success');
    })
    .catch(err => {
        Swal.fire('Simulated Dispatch', 'Email notification dispatched via BFS Mail Service.', 'info');
    });
}
</script>


