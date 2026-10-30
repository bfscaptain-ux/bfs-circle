<?php
require_once __DIR__ . '/bfs_client_security.php';
bfs_start_output_sanitizer();
require_once 'config.php';

// If user already has an active session, send them to their dashboard
if (!empty($_SESSION['user_id'])) {
    header("Location: " . get_role_dashboard_url($_SESSION['role'] ?? 'Staff', false));
    exit;
}

// Check if client IP is currently rate-limited/locked
$client_ip = function_exists('get_client_ip_address') ? get_client_ip_address() : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
$ip_lock_seconds = 0;
try {
    $db->exec("CREATE TABLE IF NOT EXISTS login_rate_limits (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        identifier VARCHAR(100) NOT NULL,
        type VARCHAR(20) NOT NULL,
        failed_attempts INTEGER DEFAULT 0,
        first_failed_at DATETIME,
        last_failed_at DATETIME,
        locked_until DATETIME,
        lockout_count INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    CREATE INDEX IF NOT EXISTS idx_login_rate_limits_lookup ON login_rate_limits(identifier, type);");

    $chk_ip = $db->prepare("SELECT locked_until FROM login_rate_limits WHERE identifier = ? AND type = 'ip' AND locked_until > datetime('now', 'localtime') LIMIT 1");
    $chk_ip->execute([$client_ip]);
    $lock_time = $chk_ip->fetchColumn();
    if ($lock_time) {
        $ip_lock_seconds = max(0, strtotime($lock_time) - time());
    }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?php bfs_render_anti_inspect_shield(); ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BFS Financial Services - Secure Login</title>
    
    <!-- Official BFS Circle Monochrome Favicon & Branding -->
    <link rel="icon" type="image/png" sizes="64x64" href="favicon.png?v=4">
    <link rel="icon" type="image/x-icon" href="favicon.ico?v=4">
    <link rel="shortcut icon" href="favicon.ico?v=4">
    <link rel="apple-touch-icon" href="favicon.png?v=4">
    
    <!-- Instant Image Preloads with High Fetch Priority -->
    <link rel="preload" as="image" href="login.webp" type="image/webp" fetchpriority="high">
    <link rel="preload" as="image" href="logo.png" type="image/png" fetchpriority="high">
    
    <!-- Google Fonts with Non-blocking display=swap -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #10b981;
            --primary-hover: #059669;
            --secondary: #f97316;
            --primary-glow: rgba(16, 185, 129, 0.25);
            --bg-main: #000000;
            --bg-card: rgba(10, 10, 10, 0.68);
            --text-primary: #ffffff;
            --text-muted: #a1a1aa;
            --border: rgba(255, 255, 255, 0.12);
            --radius-md: 10px;
            --radius-lg: 16px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        body {
            background-color: var(--bg-main);
            color: var(--text-primary);
            display: flex;
            min-height: 100vh;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow: hidden;
            position: relative;
        }

        /* Dedicated GPU Compositor Layer for Background Stage (Instant load, zero repaint lag) */
        .bg-stage {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: #000000 url('login.webp') no-repeat center center;
            background-size: cover;
            z-index: 0;
            transform: translate3d(0, 0, 0);
            will-change: transform;
            backface-visibility: hidden;
            pointer-events: none;
        }

        #particles-canvas {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: 1;
            pointer-events: none;
            transform: translate3d(0, 0, 0);
            will-change: transform;
        }

        /* Card Outer Wrapper with dynamic animated perimeter glow */
        .login-card-wrapper {
            position: relative;
            width: 100%;
            max-width: 416px;
            z-index: 10;
            margin: auto;
        }

        /* 1. Luminous Diffused Ambient Aura (Continuously rotating behind the card on GPU) */
        .card-ambient-aura {
            position: absolute;
            inset: -14px;
            border-radius: 36px;
            background: conic-gradient(
                from 0deg,
                rgba(16, 185, 129, 0.55) 0deg,
                rgba(52, 211, 153, 0.45) 60deg,
                transparent 120deg,
                rgba(249, 115, 22, 0.55) 180deg,
                rgba(251, 191, 36, 0.45) 240deg,
                transparent 300deg,
                rgba(16, 185, 129, 0.55) 360deg
            );
            filter: blur(28px);
            opacity: 0.85;
            animation: rotateLaser 6s linear infinite;
            z-index: 0;
            pointer-events: none;
            transform: translate3d(0, 0, 0);
            will-change: transform;
            backface-visibility: hidden;
        }

        /* 2. Razor-Sharp Rotating Border Laser Beam (Hardware Accelerated) */
        .card-border-beam {
            position: relative;
            border-radius: 24px;
            padding: 1.8px;
            overflow: hidden;
            z-index: 1;
            box-shadow: 0 25px 60px -10px rgba(0, 0, 0, 0.95);
            background: rgba(255, 255, 255, 0.08);
            transform: translate3d(0, 0, 0);
            backface-visibility: hidden;
        }

        .card-border-beam::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: conic-gradient(
                from 0deg,
                transparent 0deg,
                transparent 60deg,
                rgba(16, 185, 129, 0.3) 85deg,
                #10b981 115deg,
                #a7f3d0 135deg,
                #10b981 155deg,
                transparent 180deg,
                transparent 240deg,
                rgba(249, 115, 22, 0.3) 265deg,
                #f97316 295deg,
                #fef08a 315deg,
                #f97316 335deg,
                transparent 360deg
            );
            animation: rotateLaser 5s linear infinite;
            z-index: 1;
            transform: translate3d(-50%, -50%, 0);
            will-change: transform;
            backface-visibility: hidden;
        }

        @keyframes rotateLaser {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* 3. Obsidian Pitch Black Card Interior (Dark Black & Pure White Contrast) */
        .login-container {
            position: relative;
            z-index: 2;
            width: 100%;
            border-radius: 22px;
            background: rgba(0, 0, 0, 0.96); /* Deep Pure Dark Black */
            backdrop-filter: blur(40px) saturate(200%);
            -webkit-backdrop-filter: blur(40px) saturate(200%);
            padding: 40px 34px 32px;
            border: 1px solid rgba(255, 255, 255, 0.14); /* Crisp White Border */
            box-shadow: 
                inset 0 1px 1px rgba(255, 255, 255, 0.25),
                inset 0 -1px 1px rgba(0, 0, 0, 0.9),
                0 30px 80px rgba(0, 0, 0, 0.98);
        }

        /* Brand Styling - Clean logo without square box */
        .brand-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 14px;
            margin-bottom: 24px;
        }

        .brand-logo-img {
            width: 44px;
            height: 44px;
            object-fit: contain;
            filter: brightness(0) invert(1) drop-shadow(0 2px 10px rgba(255, 255, 255, 0.25));
            flex-shrink: 0;
        }

        .brand-text-block {
            display: flex;
            flex-direction: column;
        }

        .brand-name {
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 21px;
            letter-spacing: -0.4px;
            color: #ffffff; /* Crisp Pure White */
            line-height: 1.15;
        }

        .brand-subname {
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: 0.14em;
            color: #10b981;
            text-transform: uppercase;
        }

        /* Welcome text */
        .welcome-block {
            text-align: center;
            margin-bottom: 24px;
        }

        .welcome-block h2 {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            font-size: 24px;
            letter-spacing: -0.02em;
            color: #ffffff; /* Crisp Pure White */
            margin-bottom: 4px;
        }

        .welcome-block .auth-subtitle {
            font-size: 13.5px;
            color: #cbd5e1; /* Clean High-Contrast Light White */
            font-weight: 400;
        }

        /* Inline Error Alert */
        .login-alert {
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.4);
            color: #fca5a5;
            animation: fadeIn 0.2s ease;
        }

        .login-alert.warning-alert {
            background: rgba(245, 158, 11, 0.15);
            border: 1px solid rgba(245, 158, 11, 0.45);
            color: #fcd34d;
        }

        .login-alert.lockout-alert {
            background: rgba(220, 38, 38, 0.22);
            border: 1.5px solid rgba(239, 68, 68, 0.65);
            color: #ffffff;
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
            padding: 14px 16px;
            box-shadow: 0 0 30px rgba(239, 68, 68, 0.35);
        }

        .lockout-countdown-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(0, 0, 0, 0.55);
            border: 1px solid rgba(239, 68, 68, 0.7);
            color: #fca5a5;
            font-family: 'Outfit', monospace;
            font-size: 13.5px;
            font-weight: 800;
            padding: 4px 12px;
            border-radius: 8px;
            letter-spacing: 0.5px;
        }

        .btn-login.is-locked {
            background: #27272a !important;
            border-color: #3f3f46 !important;
            color: #71717a !important;
            cursor: not-allowed !important;
            box-shadow: none !important;
            transform: none !important;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-4px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Form Controls */
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 7px;
            margin-bottom: 18px;
        }

        .label-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        label {
            font-size: 13px;
            font-weight: 600;
            color: #ffffff; /* Crisp Pure White */
            letter-spacing: 0.01em;
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .label-icon {
            color: #10b981;
            font-size: 12.5px;
        }

        .forgot-link {
            font-size: 12px;
            font-weight: 600;
            color: #34d399;
            text-decoration: none;
            transition: all 0.2s;
        }

        .forgot-link:hover {
            color: #6ee7b7;
            text-shadow: 0 0 10px rgba(16, 185, 129, 0.5);
            text-decoration: underline;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            height: 48px;
            padding: 0 16px;
            border: 1.3px solid rgba(255, 255, 255, 0.22); /* Clean White Border */
            border-radius: 12px;
            font-size: 14.5px;
            font-weight: 500;
            color: #ffffff; /* Pure White text */
            background-color: #000000; /* Pure Dark Black background */
            transition: all 0.2s ease;
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #ffffff; /* Pure White on focus */
            background-color: #050505;
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.22), 0 0 16px rgba(16, 185, 129, 0.25);
        }

        input::placeholder {
            color: #64748b;
            font-weight: 400;
        }

        /* Prevent Chrome / Edge pale bluish autofill background */
        input:-webkit-autofill,
        input:-webkit-autofill:hover, 
        input:-webkit-autofill:focus,
        input:-webkit-autofill:active {
            -webkit-text-fill-color: #ffffff !important;
            -webkit-box-shadow: 0 0 0px 1000px #000000 inset !important;
            box-shadow: 0 0 0px 1000px #000000 inset !important;
            caret-color: #ffffff !important;
            transition: background-color 5000s ease-in-out 0s;
        }

        .eye-toggle-btn {
            position: absolute;
            right: 12px;
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            padding: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.2s;
            font-size: 15px;
        }

        .eye-toggle-btn:hover {
            color: #ffffff;
        }

        /* Luxury CTA Button with Shimmer and Explosive Verification Pulse */
        .btn-login {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            height: 50px;
            margin-top: 12px;
            border-radius: 12px;
            font-family: 'Outfit', sans-serif;
            font-size: 15.5px;
            font-weight: 700;
            letter-spacing: 0.02em;
            cursor: pointer;
            border: none;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #ffffff;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 24px -4px rgba(16, 185, 129, 0.5), inset 0 1px 0 rgba(255, 255, 255, 0.35);
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }

        /* Continuous light shimmer traveling over the button */
        .btn-login::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -60%;
            width: 45%;
            height: 200%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            transform: rotate(25deg);
            animation: btnShimmerSweep 3.5s infinite;
        }

        @keyframes btnShimmerSweep {
            0% { left: -70%; }
            30% { left: 140%; }
            100% { left: 140%; }
        }

        .btn-login:hover {
            transform: translateY(-2px);
            background: linear-gradient(135deg, #34d399 0%, #10b981 100%);
            box-shadow: 0 14px 30px -4px rgba(16, 185, 129, 0.7), inset 0 1px 0 rgba(255, 255, 255, 0.45);
        }

        .btn-login:active {
            transform: scale(0.98);
        }

        .btn-icon {
            transition: transform 0.2s ease;
        }

        .btn-login:hover .btn-icon {
            transform: translateX(4px);
        }

        /* Button verification / success states */
        .btn-login.is-verifying {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            cursor: wait;
        }

        .btn-login.is-success {
            background: linear-gradient(135deg, #34d399 0%, #10b981 50%, #059669 100%) !important;
            box-shadow: 0 0 40px rgba(16, 185, 129, 1), 0 0 80px rgba(16, 185, 129, 0.7) !important;
            animation: btnSuccessPulse 0.45s ease-in-out infinite alternate !important;
            transform: scale(1.03);
        }

        @keyframes btnSuccessPulse {
            0% { transform: scale(1.02); box-shadow: 0 0 30px rgba(16, 185, 129, 0.85); }
            100% { transform: scale(1.06); box-shadow: 0 0 55px rgba(52, 211, 153, 1), 0 0 95px rgba(16, 185, 129, 0.8); }
        }

        /* Card warp on login success */
        .login-card-wrapper.login-success {
            transform: scale(1.03);
            filter: brightness(1.2);
            transition: all 0.7s cubic-bezier(0.16, 1, 0.3, 1);
        }

        /* Card Angry Violent Shake & Red Security Alert on Invalid Password */
        .login-card-wrapper.card-angry {
            animation: cardAngryShake 0.65s cubic-bezier(0.36, 0.07, 0.19, 0.97) both;
        }

        @keyframes cardAngryShake {
            10%, 90% { transform: translate3d(-8px, 0, 0); }
            20%, 80% { transform: translate3d(10px, 0, 0); }
            30%, 50%, 70% { transform: translate3d(-14px, 0, 0); }
            40%, 60% { transform: translate3d(14px, 0, 0); }
        }

        /* Red / Crimson warning laser when angry */
        .login-card-wrapper.card-angry .card-ambient-aura {
            background: conic-gradient(
                from 0deg,
                rgba(239, 68, 68, 0.90) 0deg,
                rgba(220, 38, 38, 0.8) 90deg,
                rgba(249, 115, 22, 0.8) 180deg,
                rgba(239, 68, 68, 0.90) 360deg
            );
            filter: blur(35px);
            opacity: 1;
            animation: rotateLaser 1.6s linear infinite !important; /* Rapid security siren rotation */
        }

        .login-card-wrapper.card-angry .card-border-beam::before {
            background: conic-gradient(
                from 0deg,
                transparent 0deg,
                rgba(239, 68, 68, 0.5) 60deg,
                #ef4444 110deg,
                #fca5a5 135deg,
                #dc2626 160deg,
                transparent 190deg,
                transparent 240deg,
                rgba(249, 115, 22, 0.5) 270deg,
                #f97316 300deg,
                #fecaca 315deg,
                #ef4444 330deg,
                transparent 360deg
            );
            animation: rotateLaser 1.6s linear infinite !important;
        }

        .login-card-wrapper.card-angry .login-container {
            border-color: rgba(239, 68, 68, 0.65);
            box-shadow: 
                inset 0 1px 1px rgba(239, 68, 68, 0.5),
                0 0 45px rgba(239, 68, 68, 0.4),
                0 30px 80px rgba(0, 0, 0, 0.98);
        }

        /* Banking Security Badge */
        .security-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            margin-top: 20px;
            font-size: 12px;
            font-weight: 500;
            color: #94a3b8;
        }

        .security-badge i {
            color: #10b981;
            font-size: 13px;
        }

        /* 2FA UI Styles & Transitions */
        .step-transition {
            animation: stepFadeIn 0.35s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        @keyframes stepFadeIn {
            from { opacity: 0; transform: translateY(8px) scale(0.985); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .otp-shield-icon {
            width: 54px;
            height: 54px;
            margin: 0 auto 12px;
            border-radius: 16px;
            background: rgba(16, 185, 129, 0.12);
            border: 1px solid rgba(16, 185, 129, 0.35);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: #10b981;
            box-shadow: 0 0 25px rgba(16, 185, 129, 0.25);
            animation: shieldPulse 2.8s ease-in-out infinite;
        }

        @keyframes shieldPulse {
            0%, 100% { transform: scale(1); box-shadow: 0 0 20px rgba(16, 185, 129, 0.2); }
            50% { transform: scale(1.05); box-shadow: 0 0 35px rgba(16, 185, 129, 0.45); }
        }

        .otp-input-field {
            width: 100%;
            height: 54px;
            text-align: center;
            font-family: 'Outfit', monospace;
            font-size: 26px;
            font-weight: 800;
            letter-spacing: 12px;
            color: #ffffff;
            background-color: #050505;
            border: 1.5px solid rgba(16, 185, 129, 0.45);
            border-radius: 14px;
            padding-left: 12px;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.8), 0 0 15px rgba(16, 185, 129, 0.15);
            transition: all 0.25s ease;
        }

        .otp-input-field:focus {
            outline: none;
            border-color: #10b981;
            background-color: #000000;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.25), 0 0 25px rgba(16, 185, 129, 0.35);
        }

        .twofa-actions-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 18px;
            padding: 0 2px;
        }

        .twofa-link-btn {
            background: none;
            border: none;
            color: #94a3b8;
            font-size: 12.5px;
            font-weight: 600;
            cursor: pointer;
            padding: 6px 8px;
            border-radius: 8px;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .twofa-link-btn:hover {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.05);
        }

        .twofa-link-btn.highlight {
            color: #34d399;
        }

        .twofa-link-btn.highlight:hover {
            color: #6ee7b7;
            text-shadow: 0 0 10px rgba(16, 185, 129, 0.5);
        }

        .twofa-link-btn:disabled {
            color: #64748b;
            cursor: not-allowed;
            text-shadow: none;
            background: none;
        }
    </style>
    <!-- Add FontAwesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Hardware-Accelerated Static Background Stage (Zero repaints) -->
    <div class="bg-stage"></div>

    <!-- Interactive Canvas Particles -->
    <canvas id="particles-canvas"></canvas>

    <div class="login-card-wrapper">
        <!-- Ambient Diffused Glow Halo -->
        <div class="card-ambient-aura"></div>

        <!-- Rotating Laser Border Beam -->
        <div class="card-border-beam">
            <div class="login-container">
                <div class="brand-container">
                    <img src="logo.png" alt="BFS Financial Services Logo" class="brand-logo-img">
                    <div class="brand-text-block">
                        <div class="brand-name">BFS Financial</div>
                        <div class="brand-subname">Services Portal</div>
                    </div>
                </div>

                <!-- STEP 1: Credentials Form -->
                <div id="step-credentials">
                    <div class="welcome-block">
                        <h2>Welcome Back</h2>
                        <p class="auth-subtitle">Enter your credentials to access your terminal</p>
                    </div>

                    <?php if (!empty($_GET['deactivated'])): ?>
                    <div id="login-error-msg" class="login-alert" style="display: flex;">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <span>Your partner account has been deactivated by Admin. You cannot access the dashboard.</span>
                    </div>
                    <?php else: ?>
                    <div id="login-error-msg" class="login-alert" style="display: none;"></div>
                    <?php endif; ?>

                    <form id="login-form" onsubmit="handleLogin(event)">
                        <!-- Invisible Honeypot Anti-Bot Field -->
                        <input type="text" name="hp_field_check" id="hp_field_check" style="display:none !important; position:absolute; left:-9999px;" tabindex="-1" autocomplete="off" aria-hidden="true">
                        <div class="form-group">
                            <label for="username">
                                <i class="fa-regular fa-user label-icon"></i> Username
                            </label>
                            <div class="input-wrapper">
                                <input type="text" id="username" name="username" placeholder="Enter your username" autocomplete="username" required autofocus>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="label-row">
                                <label for="password_input">
                                    <i class="fa-solid fa-lock label-icon"></i> Password
                                </label>
                                <a href="forgot_password.php" class="forgot-link">Forgot Password?</a>
                            </div>
                            <div class="input-wrapper">
                                <input type="password" id="password_input" name="password" placeholder="••••••••" autocomplete="current-password" required>
                                <button type="button" class="eye-toggle-btn" onclick="togglePasswordVisibility()" tabindex="-1" aria-label="Toggle password visibility">
                                    <i class="fas fa-eye" id="togglePassword"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" id="login-submit-btn" class="btn-login">
                            <span>Secure Sign In</span>
                            <i class="fa-solid fa-arrow-right-long btn-icon"></i>
                        </button>

                        <div class="security-badge">
                            <i class="fa-solid fa-shield-halved"></i>
                            <span>256-Bit SSL Encrypted Banking Security</span>
                        </div>
                    </form>
                </div>

                <!-- STEP 2: Super Admin 2FA Verification Form -->
                <div id="step-2fa" style="display: none;">
                    <div class="otp-shield-icon">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>

                    <div class="welcome-block" style="margin-bottom: 18px;">
                        <h2>Admin Verification</h2>
                        <p class="auth-subtitle" style="font-size: 13px; line-height: 1.5; margin-top: 4px;">
                            A 6-digit one-time code has been sent to:<br>
                            <span id="twofa-target-email" style="color: #34d399; font-weight: 700; letter-spacing: 0.3px;">bfscaptain@gmail.com</span>
                        </p>
                    </div>

                    <div id="twofa-error-msg" class="login-alert" style="display: none;"></div>

                    <form id="twofa-form" onsubmit="handleVerify2FA(event)">
                        <div class="form-group" style="margin-bottom: 20px;">
                            <label for="otp_code_input" style="justify-content: center; margin-bottom: 8px;">
                                <i class="fa-solid fa-key label-icon"></i> Enter 6-Digit Admin OTP
                            </label>
                            <div class="input-wrapper">
                                <input type="text" id="otp_code_input" name="otp_code" class="otp-input-field" maxlength="6" inputmode="numeric" pattern="[0-9]*" placeholder="••••••" autocomplete="one-time-code" required>
                            </div>
                        </div>

                        <button type="submit" id="twofa-submit-btn" class="btn-login">
                            <span>Authorize & Enter Terminal</span>
                            <i class="fa-solid fa-arrow-right-long btn-icon"></i>
                        </button>

                        <div class="twofa-actions-row">
                            <button type="button" onclick="cancel2FA()" class="twofa-link-btn">
                                <i class="fa-solid fa-arrow-left"></i> <span>Back</span>
                            </button>
                            <button type="button" id="resend-otp-btn" onclick="handleResend2FA()" class="twofa-link-btn highlight">
                                <i class="fa-solid fa-rotate-right"></i> <span id="resend-otp-label">Resend Code</span>
                            </button>
                        </div>

                        <div class="security-badge" style="margin-top: 18px;">
                            <i class="fa-solid fa-lock"></i>
                            <span>Admin Terminal • Single-Use 10-Min OTP</span>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- 3D Perspective Particle Interactive Engine -->
    <script>
        (function() {
            const canvas = document.getElementById('particles-canvas');
            if (!canvas) return;
            const ctx = canvas.getContext('2d');

            let width = 0;
            let height = 0;
            const dpr = Math.min(window.devicePixelRatio || 1, 2);

            function resize() {
                width = window.innerWidth;
                height = window.innerHeight;
                canvas.width = width * dpr;
                canvas.height = height * dpr;
                ctx.scale(dpr, dpr);
            }
            resize();
            window.addEventListener('resize', resize);

            // Mouse coordinates & smooth interpolation
            const mouse = {
                x: width / 2,
                y: height / 2,
                targetX: width / 2,
                targetY: height / 2,
                radius: 170,
                active: false
            };

            window.addEventListener('mousemove', (e) => {
                mouse.targetX = e.clientX;
                mouse.targetY = e.clientY;
                mouse.active = true;
            });

            window.addEventListener('mouseleave', () => {
                mouse.active = false;
            });

            // Touch interaction for mobile
            window.addEventListener('touchmove', (e) => {
                if (e.touches && e.touches[0]) {
                    mouse.targetX = e.touches[0].clientX;
                    mouse.targetY = e.touches[0].clientY;
                    mouse.active = true;
                }
            }, { passive: true });

            window.addEventListener('touchend', () => {
                mouse.active = false;
            });

            // 3D Camera / World Projection parameters
            const fl = 480; // Focal length
            let rotX = 0, rotY = 0;
            let targetRotX = 0, targetRotY = 0;

            // Brand luxury color palette (Emerald, Mint, Amber, Golden, Cyan)
            const colors = [
                { main: 'rgba(16, 185, 129, ', glow: 'rgba(5, 150, 105, ' },    // Emerald Green
                { main: 'rgba(52, 211, 153, ', glow: 'rgba(16, 185, 129, ' },   // Mint
                { main: 'rgba(249, 115, 22, ', glow: 'rgba(234, 88, 12, ' },    // Amber Orange
                { main: 'rgba(251, 191, 36, ', glow: 'rgba(245, 158, 11, ' },   // Gold
                { main: 'rgba(56, 189, 248, ', glow: 'rgba(14, 165, 233, ' },   // Cyan
            ];

            // Pre-render glowing bokeh orb sprites onto offscreen canvases (100x faster GPU blitting)
            const spriteSize = 64;
            const sprites = colors.map(col => {
                const sc = document.createElement('canvas');
                sc.width = spriteSize;
                sc.height = spriteSize;
                const sctx = sc.getContext('2d');
                const grad = sctx.createRadialGradient(spriteSize / 2, spriteSize / 2, 0, spriteSize / 2, spriteSize / 2, spriteSize / 2);
                grad.addColorStop(0, 'rgba(255, 255, 255, 1)');
                grad.addColorStop(0.32, col.main + '0.92)');
                grad.addColorStop(0.72, col.glow + '0.38)');
                grad.addColorStop(1, col.glow + '0)');
                sctx.fillStyle = grad;
                sctx.beginPath();
                sctx.arc(spriteSize / 2, spriteSize / 2, spriteSize / 2, 0, Math.PI * 2);
                sctx.fill();
                return sc;
            });

            // Angry / Security Alert Red Plasma Sprites (Blood Red, Crimson, Lava Orange)
            const angryColors = [
                { main: 'rgba(239, 68, 68, ', glow: 'rgba(185, 28, 28, ' },    // Crimson Red
                { main: 'rgba(244, 63, 94, ', glow: 'rgba(225, 29, 72, ' },    // Rose Neon
                { main: 'rgba(220, 38, 38, ', glow: 'rgba(153, 27, 27, ' },    // Dark Blood Red
                { main: 'rgba(249, 115, 22, ', glow: 'rgba(194, 65, 12, ' },   // Flare Orange-Red
            ];
            const angrySprites = angryColors.map(col => {
                const sc = document.createElement('canvas');
                sc.width = spriteSize;
                sc.height = spriteSize;
                const sctx = sc.getContext('2d');
                const grad = sctx.createRadialGradient(spriteSize / 2, spriteSize / 2, 0, spriteSize / 2, spriteSize / 2, spriteSize / 2);
                grad.addColorStop(0, 'rgba(255, 255, 255, 1)');
                grad.addColorStop(0.28, col.main + '0.98)');
                grad.addColorStop(0.70, col.glow + '0.50)');
                grad.addColorStop(1, col.glow + '0)');
                sctx.fillStyle = grad;
                sctx.beginPath();
                sctx.arc(spriteSize / 2, spriteSize / 2, spriteSize / 2, 0, Math.PI * 2);
                sctx.fill();
                return sc;
            });

            const count = Math.min(Math.floor((window.innerWidth * window.innerHeight) / 8500), 160);
            const particles = [];

            class Particle3D {
                constructor() {
                    this.reset(true);
                }

                reset(init = false) {
                    // Spherical 3D distribution around viewport center
                    const radius = Math.random() * 520 + 90;
                    const theta = Math.random() * Math.PI * 2;
                    const phi = Math.acos((Math.random() * 2) - 1);

                    this.x = radius * Math.sin(phi) * Math.cos(theta);
                    this.y = (radius * Math.sin(phi) * Math.sin(theta)) * 0.75; // slight oval
                    this.z = radius * Math.cos(phi);

                    this.vx = (Math.random() - 0.5) * 0.5;
                    this.vy = (Math.random() - 0.5) * 0.5;
                    this.vz = (Math.random() - 0.5) * 0.5;

                    this.baseSize = Math.random() * 3.6 + 2.0;
                    const colIdx = Math.floor(Math.random() * colors.length);
                    this.color = colors[colIdx];
                    this.sprite = sprites[colIdx];
                    this.angrySprite = angrySprites[colIdx % angrySprites.length];
                    this.pulseSpeed = Math.random() * 0.028 + 0.014;
                    this.pulse = Math.random() * Math.PI * 2;
                    this.orbitSpeed = (Math.random() * 0.0025 + 0.0008) * (Math.random() > 0.5 ? 1 : -1);
                }

                update() {
                    // Supernova Expansion mode on successful login!
                    if (isExploding) {
                        this.x += this.vx;
                        this.y += this.vy;
                        this.z += this.vz;
                        this.vx *= 1.035; // Accelerate outwards
                        this.vy *= 1.035;
                        this.vz *= 1.035;
                        this.baseSize *= 1.022; // Continuously expand size
                        return;
                    }

                    // ANGRY RAGE BEHAVIOR: Rapid erratic agitation & buzzing jitter!
                    if (isAngry) {
                        this.x += this.vx;
                        this.y += this.vy;
                        this.z += this.vz;

                        // Violent high-frequency electric vibration
                        this.vx += (Math.random() - 0.5) * 4.2;
                        this.vy += (Math.random() - 0.5) * 4.2;
                        this.vz += (Math.random() - 0.5) * 4.2;

                        // Agitated damping (buzzing swarm feel)
                        this.vx *= 0.88;
                        this.vy *= 0.88;
                        this.vz *= 0.88;

                        // Swarming gravitational tension towards center
                        this.vx -= this.x * 0.006;
                        this.vy -= this.y * 0.006;
                        this.vz -= this.z * 0.006;
                        this.pulse += 0.22; // Rapid angry flashing pulse
                        return;
                    }

                    this.pulse += this.pulseSpeed;

                    // 3D Orbital rotation around Y
                    const cosO = Math.cos(this.orbitSpeed);
                    const sinO = Math.sin(this.orbitSpeed);
                    const ox = this.x * cosO - this.z * sinO;
                    const oz = this.z * cosO + this.x * sinO;
                    this.x = ox + this.vx;
                    this.y += this.vy;
                    this.z = oz + this.vz;

                    // Subtle velocity damping & wander
                    this.vx += (Math.random() - 0.5) * 0.06;
                    this.vy += (Math.random() - 0.5) * 0.06;
                    this.vz += (Math.random() - 0.5) * 0.06;
                    this.vx *= 0.98;
                    this.vy *= 0.98;
                    this.vz *= 0.98;

                    // Keep bound within 3D sphere
                    const distSq = this.x * this.x + this.y * this.y + this.z * this.z;
                    if (distSq > 380000 || distSq < 3000) {
                        this.vx -= this.x * 0.002;
                        this.vy -= this.y * 0.002;
                        this.vz -= this.z * 0.002;
                    }
                }
            }

            for (let i = 0; i < count; i++) {
                particles.push(new Particle3D());
            }

            let isExploding = false;
            let isAngry = false;
            let angryTimer = null;

            // Global trigger for Supernova Particle Explosion on login
            window.triggerParticleExplosion = function() {
                isExploding = true;
                isAngry = false;
                
                // 1. Catapult existing particles outward in 3D
                for (let i = 0; i < particles.length; i++) {
                    const p = particles[i];
                    const dist = Math.sqrt(p.x * p.x + p.y * p.y + p.z * p.z) || 1;
                    const blastSpeed = Math.random() * 32 + 20;
                    p.vx = (p.x / dist) * blastSpeed;
                    p.vy = (p.y / dist) * blastSpeed;
                    p.vz = (p.z / dist) * blastSpeed + 18; // Rush towards viewer
                    p.baseSize = Math.max(p.baseSize, 4.0);
                }

                // 2. Spawn 120 extra hyperspace burst particles right from center
                const burstCount = 120;
                for (let i = 0; i < burstCount; i++) {
                    const bp = new Particle3D();
                    bp.x = (Math.random() - 0.5) * 40;
                    bp.y = (Math.random() - 0.5) * 40;
                    bp.z = (Math.random() - 0.5) * 40;
                    const theta = Math.random() * Math.PI * 2;
                    const phi = Math.acos((Math.random() * 2) - 1);
                    const speed = Math.random() * 38 + 24;
                    bp.vx = speed * Math.sin(phi) * Math.cos(theta);
                    bp.vy = speed * Math.sin(phi) * Math.sin(theta);
                    bp.vz = speed * Math.cos(phi) + 20;
                    bp.baseSize = Math.random() * 5.0 + 3.0;
                    particles.push(bp);
                }
            };

            // Global trigger for Angry Security Alert Particle Mode on wrong password
            window.triggerAngryParticles = function() {
                isAngry = true;

                // Violent kick in velocity for all particles
                for (let i = 0; i < particles.length; i++) {
                    const p = particles[i];
                    p.vx = (Math.random() - 0.5) * 18;
                    p.vy = (Math.random() - 0.5) * 18;
                    p.vz = (Math.random() - 0.5) * 20;
                }

                // Shake card & turn border laser into emergency siren red
                const cardWrapper = document.querySelector('.login-card-wrapper');
                if (cardWrapper) {
                    cardWrapper.classList.remove('card-angry');
                    void cardWrapper.offsetWidth; // Force CSS reflow to re-trigger shake
                    cardWrapper.classList.add('card-angry');
                }

                // Return to calm luxury mode after 3.2 seconds
                if (angryTimer) clearTimeout(angryTimer);
                angryTimer = setTimeout(() => {
                    isAngry = false;
                    if (cardWrapper) {
                        cardWrapper.classList.remove('card-angry');
                    }
                }, 3200);
            };

            // Calm down immediately if user starts correcting credentials
            document.addEventListener('input', (e) => {
                if (isAngry && (e.target.name === 'username' || e.target.name === 'password')) {
                    isAngry = false;
                    const cardWrapper = document.querySelector('.login-card-wrapper');
                    if (cardWrapper) cardWrapper.classList.remove('card-angry');
                }
            });

            function animate() {
                ctx.clearRect(0, 0, width, height);

                // Smooth mouse interpolation
                mouse.x += (mouse.targetX - mouse.x) * 0.08;
                mouse.y += (mouse.targetY - mouse.y) * 0.08;

                // 3D Parallax Camera Tilt from Mouse Position
                targetRotY = ((mouse.x / width) - 0.5) * 0.85;
                targetRotX = -((mouse.y / height) - 0.5) * 0.65;
                rotX += (targetRotX - rotX) * 0.05;
                rotY += (targetRotY - rotY) * 0.05;

                const cosY = Math.cos(rotY), sinY = Math.sin(rotY);
                const cosX = Math.cos(rotX), sinX = Math.sin(rotX);
                const cx = width / 2, cy = height / 2;

                const projected = [];

                for (let i = 0; i < particles.length; i++) {
                    const p = particles[i];
                    p.update();

                    // Rotate around Y axis
                    const x1 = p.x * cosY - p.z * sinY;
                    const z1 = p.z * cosY + p.x * sinY;

                    // Rotate around X axis
                    const y1 = p.y * cosX - z1 * sinX;
                    const z2 = z1 * cosX + p.y * sinX;

                    // 3D Perspective depth scaling
                    const depth = z2 + 480;
                    if (depth < 15) continue; // behind camera
                    const scale = fl / depth;

                    const sx = cx + x1 * scale;
                    const sy = cy + y1 * scale;

                    // Screen distance to mouse cursor
                    let mouseDist = 9999;
                    let mouseEffect = 0;
                    if (mouse.active && !isExploding) {
                        const mdx = sx - mouse.x;
                        const mdy = sy - mouse.y;
                        mouseDist = Math.sqrt(mdx * mdx + mdy * mdy);

                        if (mouseDist < mouse.radius) {
                            mouseEffect = (1 - mouseDist / mouse.radius);
                            // Magnetic 3D repulsion away from mouse
                            p.vx += (mdx / (mouseDist + 1)) * mouseEffect * (isAngry ? 2.5 : 1.35);
                            p.vy += (mdy / (mouseDist + 1)) * mouseEffect * (isAngry ? 2.5 : 1.35);
                            p.vz -= mouseEffect * (isAngry ? 3.0 : 1.8);
                        }
                    }

                    // CHOTE SE BADE EFFECT & SUPERNOVA EXPANSION:
                    const breathScale = 1.0 + Math.sin(p.pulse) * 0.52;
                    const renderSize = Math.max(0.9, p.baseSize * scale * breathScale * (1.0 + mouseEffect * 2.4));
                    const alpha = Math.min(1.0, Math.max(0.12, (scale * 0.85) + (mouseEffect * 0.55)));

                    projected.push({
                        sx, sy, z: z2, renderSize, alpha, color: p.color, sprite: p.sprite, angrySprite: p.angrySprite, mouseDist
                    });
                }

                // Sort by 3D depth for realistic occlusion
                projected.sort((a, b) => b.z - a.z);

                // Draw 3D constellation links between nearby particles (turn into fiery electric sparks when angry)
                if (!isExploding) {
                    const maxLineDist = isAngry ? 120 : 95;
                    for (let i = 0; i < projected.length; i++) {
                        const pi = projected[i];
                        for (let j = i + 1; j < Math.min(i + (isAngry ? 18 : 14), projected.length); j++) {
                            const pj = projected[j];
                            const dx = pi.sx - pj.sx;
                            const dy = pi.sy - pj.sy;
                            const d = Math.sqrt(dx * dx + dy * dy);

                            if (d < maxLineDist) {
                                const lineAlpha = (1 - d / maxLineDist) * (isAngry ? 0.45 : 0.24) * Math.min(pi.alpha, pj.alpha);
                                ctx.beginPath();
                                ctx.moveTo(pi.sx, pi.sy);
                                ctx.lineTo(pj.sx, pj.sy);
                                ctx.strokeStyle = isAngry 
                                    ? `rgba(239, 68, 68, ${lineAlpha})` 
                                    : `rgba(16, 185, 129, ${lineAlpha})`;
                                ctx.lineWidth = isAngry ? 1.2 : 0.8;
                                ctx.stroke();
                            }
                        }

                        // Draw interactive energy beam from mouse cursor to nearby particles
                        if (mouse.active && pi.mouseDist < 150) {
                            const mAlpha = (1 - pi.mouseDist / 150) * 0.42;
                            ctx.beginPath();
                            ctx.moveTo(pi.sx, pi.sy);
                            ctx.lineTo(mouse.x, mouse.y);
                            ctx.strokeStyle = isAngry ? `rgba(239, 68, 68, ${mAlpha})` : `rgba(249, 115, 22, ${mAlpha})`;
                            ctx.lineWidth = isAngry ? 1.4 : 1.1;
                            ctx.stroke();
                        }
                    }
                }

                // Render luminous 3D Bokeh Orbs & Hyperspace Trails using GPU Sprite Blitting
                for (let i = 0; i < projected.length; i++) {
                    const pi = projected[i];
                    const rad = Math.max(1.5, pi.renderSize * (isExploding ? 4.2 : (isAngry ? 2.8 : 2.4)));

                    if (isExploding) {
                        // Draw light streak zooming outward from center
                        ctx.beginPath();
                        ctx.moveTo(cx + (pi.sx - cx) * 0.72, cy + (pi.sy - cy) * 0.72);
                        ctx.lineTo(pi.sx, pi.sy);
                        ctx.strokeStyle = `rgba(255, 255, 255, ${Math.min(1, pi.alpha * 0.85)})`;
                        ctx.lineWidth = Math.max(1.2, pi.renderSize * 0.6);
                        ctx.stroke();
                    }

                    ctx.globalAlpha = Math.min(1.0, Math.max(0.04, pi.alpha * (isAngry ? 1.25 : 1.0)));
                    const diam = rad * 2;
                    const activeSprite = isAngry ? pi.angrySprite : pi.sprite;
                    ctx.drawImage(activeSprite, pi.sx - rad, pi.sy - rad, diam, diam);
                }
                ctx.globalAlpha = 1.0;

                requestAnimationFrame(animate);
            }
            animate();
        })();

        // Live Lockout Countdown Engine
        let lockoutTimerInterval = null;

        function startLockoutCountdown(totalSeconds) {
            const btn = document.getElementById('login-submit-btn');
            const alertBox = document.getElementById('login-error-msg');
            const usernameInput = document.getElementById('username');
            const passwordInput = document.getElementById('password_input');
            
            if (lockoutTimerInterval) clearInterval(lockoutTimerInterval);
            
            if (usernameInput) usernameInput.disabled = true;
            if (passwordInput) passwordInput.disabled = true;
            if (btn) {
                btn.disabled = true;
                btn.classList.add('is-locked');
            }

            let remaining = Math.max(1, parseInt(totalSeconds, 10));

            function render() {
                const mins = Math.floor(remaining / 60);
                const secs = remaining % 60;
                const formatted = `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
                
                if (btn) {
                    btn.innerHTML = `<i class="fa-solid fa-lock" style="color: #ef4444; margin-right: 6px;"></i> <span>Locked • Retry in ${formatted}</span>`;
                }

                if (alertBox) {
                    alertBox.className = 'login-alert lockout-alert';
                    alertBox.style.display = 'flex';
                    alertBox.innerHTML = `
                        <div style="display:flex; align-items:center; gap:8px; width:100%;">
                            <i class="fa-solid fa-shield-halved" style="font-size: 18px; color: #ef4444;"></i>
                            <strong style="font-size: 13.5px; font-weight:700;">Security Lockout Activated</strong>
                        </div>
                        <div style="font-size: 12.5px; color: #fca5a5; line-height: 1.4;">
                            5 consecutive failed attempts detected. Access is temporarily restricted to protect server resources against brute-force attacks.
                        </div>
                        <div style="display:flex; align-items:center; justify-content:space-between; width:100%; padding-top:4px;">
                            <span style="font-size:11.5px; color:#cbd5e1;">Cooldown countdown:</span>
                            <span class="lockout-countdown-badge">
                                <i class="fa-regular fa-clock"></i> ${formatted}
                            </span>
                        </div>
                    `;
                }

                if (remaining <= 0) {
                    clearInterval(lockoutTimerInterval);
                    lockoutTimerInterval = null;
                    if (usernameInput) usernameInput.disabled = false;
                    if (passwordInput) passwordInput.disabled = false;
                    if (btn) {
                        btn.disabled = false;
                        btn.classList.remove('is-locked');
                        btn.innerHTML = '<span>Secure Sign In</span> <i class="fa-solid fa-arrow-right-long btn-icon"></i>';
                    }
                    if (alertBox) {
                        alertBox.className = 'login-alert warning-alert';
                        alertBox.innerHTML = '<i class="fa-solid fa-circle-check" style="color:#10b981;"></i> <span>Lockout cooldown expired. You can now try logging in again.</span>';
                    }
                }
                remaining--;
            }

            render();
            lockoutTimerInterval = setInterval(render, 1000);
        }

        const INITIAL_IP_LOCKOUT = <?php echo (int)$ip_lock_seconds; ?>;
        if (INITIAL_IP_LOCKOUT > 0) {
            document.addEventListener('DOMContentLoaded', () => {
                startLockoutCountdown(INITIAL_IP_LOCKOUT);
            });
        }

        // Form & Login handling
        function togglePasswordVisibility() {
            const passwordInput = document.getElementById('password_input');
            const toggleIcon = document.getElementById('togglePassword');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        async function handleLogin(e) {
            e.preventDefault();
            const btn = document.getElementById('login-submit-btn');
            const alertBox = document.getElementById('login-error-msg');
            const cardWrapper = document.querySelector('.login-card-wrapper');
            const originalBtnHtml = btn.innerHTML;
            
            // Clear previous error
            if (alertBox) {
                alertBox.style.display = 'none';
                alertBox.innerHTML = '';
            }

            // Loading state
            btn.disabled = true;
            btn.classList.add('is-verifying');
            btn.innerHTML = '<span>Verifying Credentials...</span> <i class="fa-solid fa-circle-notch fa-spin"></i>';

            const fd = new FormData(e.target);
            fetch('api.php?api=login', {method: 'POST', body: fd})
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Check if Super Admin requires Two-Factor Authentication (Email OTP)
                    if (data.requires_2fa) {
                        btn.disabled = false;
                        btn.classList.remove('is-verifying');
                        btn.innerHTML = originalBtnHtml;

                        window.CURRENT_2FA_TOKEN = data.otp_token || '';
                        if (data.email_target) {
                            const emailEl = document.getElementById('twofa-target-email');
                            if (emailEl) emailEl.innerText = data.email_target;
                        }

                        const stepCreds = document.getElementById('step-credentials');
                        const step2fa = document.getElementById('step-2fa');
                        const twofaAlert = document.getElementById('twofa-error-msg');
                        if (twofaAlert) {
                            twofaAlert.style.display = 'none';
                            twofaAlert.innerHTML = '';
                        }

                        stepCreds.style.display = 'none';
                        step2fa.style.display = 'block';
                        step2fa.classList.add('step-transition');

                        const otpInput = document.getElementById('otp_code_input');
                        if (otpInput) {
                            otpInput.value = '';
                            setTimeout(() => otpInput.focus(), 150);
                        }
                        return;
                    }

                    // 1. Triumphant button animation
                    btn.classList.remove('is-verifying');
                    btn.classList.add('is-success');
                    btn.innerHTML = '<i class="fa-solid fa-circle-check" style="font-size: 19px;"></i> <span>Access Granted • Entering...</span>';

                    // 2. Trigger Hyperspace 3D Particle Supernova Explosion!
                    if (window.triggerParticleExplosion) {
                        window.triggerParticleExplosion();
                    }

                    // 3. Elevate & brighten login card
                    if (cardWrapper) {
                        cardWrapper.classList.add('login-success');
                    }

                    // 4. Smooth cinematic redirection after 850ms
                    setTimeout(() => {
                        if (data.role === 'RM') {
                            location.href = 'rm/dashboard.php';
                        } else if (data.role === 'Staff') {
                            location.href = 'staff/index.php';
                        } else if (['Partner', 'DSA', 'Connector', 'Individual', 'Financial Advisor / DSA'].includes(data.role)) {
                            location.href = 'partner/index.php';
                        } else if (['Builder', 'Builder / Real Estate'].includes(data.role)) {
                            location.href = 'builder/index.php';
                        } else if (['Agent', 'Individual Agent'].includes(data.role)) {
                            location.href = 'agent/index.php';
                        } else if (['CA', 'CA/CS', 'Chartered Accountant (CA)'].includes(data.role)) {
                            location.href = 'ca/index.php';
                        } else {
                            location.href = 'dashboard.php';
                        }
                    }, 850);
                } else {
                    btn.disabled = false;
                    btn.classList.remove('is-verifying');
                    btn.innerHTML = originalBtnHtml;

                    // Trigger Angry Rage Particle Behavior on Invalid Password!
                    if (window.triggerAngryParticles) {
                        window.triggerAngryParticles();
                    }

                    if (data.locked) {
                        startLockoutCountdown(data.retry_after || 900);
                    } else if (data.attempts_left !== undefined) {
                        if (alertBox) {
                            alertBox.className = (data.attempts_left <= 2) ? 'login-alert' : 'login-alert warning-alert';
                            alertBox.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> <span>' + (data.error || 'Invalid credentials') + '</span>';
                            alertBox.style.display = 'flex';
                        }
                    } else {
                        if (alertBox) {
                            alertBox.className = 'login-alert';
                            alertBox.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> <span>' + (data.error || 'Invalid username or password') + '</span>';
                            alertBox.style.display = 'flex';
                        } else {
                            alert(data.error);
                        }
                    }
                }
            })
            .catch(err => {
                console.error(err);
                btn.disabled = false;
                btn.classList.remove('is-verifying');
                btn.innerHTML = originalBtnHtml;

                // Trigger Angry Particle Behavior on connection error too
                if (window.triggerAngryParticles) {
                    window.triggerAngryParticles();
                }

                if (alertBox) {
                    alertBox.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> <span>Connection failed. Please check your network.</span>';
                    alertBox.style.display = 'flex';
                } else {
                    alert("Connection failed.");
                }
            });
        }

        // Super Admin 2FA Handlers
        window.CURRENT_2FA_TOKEN = '';
        let resendCooldownTimer = null;

        function cancel2FA() {
            const stepCreds = document.getElementById('step-credentials');
            const step2fa = document.getElementById('step-2fa');
            const twofaAlert = document.getElementById('twofa-error-msg');
            if (twofaAlert) twofaAlert.style.display = 'none';
            step2fa.style.display = 'none';
            stepCreds.style.display = 'block';
            stepCreds.classList.add('step-transition');
            window.CURRENT_2FA_TOKEN = '';
            const userInp = document.getElementById('username');
            if (userInp) userInp.focus();
        }

        async function handleVerify2FA(e) {
            e.preventDefault();
            const btn = document.getElementById('twofa-submit-btn');
            const alertBox = document.getElementById('twofa-error-msg');
            const otpInput = document.getElementById('otp_code_input');
            const cardWrapper = document.querySelector('.login-card-wrapper');
            const originalBtnHtml = btn.innerHTML;

            if (alertBox) {
                alertBox.style.display = 'none';
                alertBox.innerHTML = '';
            }

            const otpVal = otpInput.value.trim();
            if (otpVal.length !== 6) {
                if (alertBox) {
                    alertBox.className = 'login-alert';
                    alertBox.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> <span>Please enter the 6-digit verification code.</span>';
                    alertBox.style.display = 'flex';
                }
                otpInput.focus();
                return;
            }

            btn.disabled = true;
            btn.classList.add('is-verifying');
            btn.innerHTML = '<span>Verifying Admin Code...</span> <i class="fa-solid fa-circle-notch fa-spin"></i>';

            const fd = new FormData();
            fd.append('otp_token', window.CURRENT_2FA_TOKEN || '');
            fd.append('otp_code', otpVal);

            fetch('api.php?api=verify_admin_2fa', {method: 'POST', body: fd})
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    btn.classList.remove('is-verifying');
                    btn.classList.add('is-success');
                    btn.innerHTML = '<i class="fa-solid fa-circle-check" style="font-size: 19px;"></i> <span>Admin Access Authorized • Entering...</span>';

                    if (window.triggerParticleExplosion) {
                        window.triggerParticleExplosion();
                    }

                    if (cardWrapper) {
                        cardWrapper.classList.add('login-success');
                    }

                    setTimeout(() => {
                        location.href = 'dashboard.php';
                    }, 850);
                } else {
                    btn.disabled = false;
                    btn.classList.remove('is-verifying');
                    btn.innerHTML = originalBtnHtml;

                    if (window.triggerAngryParticles) {
                        window.triggerAngryParticles();
                    }

                    if (alertBox) {
                        alertBox.className = 'login-alert';
                        alertBox.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> <span>' + (data.error || 'Invalid verification code') + '</span>';
                        alertBox.style.display = 'flex';
                    }
                    otpInput.select();
                    otpInput.focus();
                }
            })
            .catch(err => {
                console.error(err);
                btn.disabled = false;
                btn.classList.remove('is-verifying');
                btn.innerHTML = originalBtnHtml;

                if (window.triggerAngryParticles) {
                    window.triggerAngryParticles();
                }

                if (alertBox) {
                    alertBox.className = 'login-alert';
                    alertBox.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> <span>Connection failed. Please retry.</span>';
                    alertBox.style.display = 'flex';
                }
            });
        }

        function handleResend2FA() {
            const resendBtn = document.getElementById('resend-otp-btn');
            const resendLabel = document.getElementById('resend-otp-label');
            const alertBox = document.getElementById('twofa-error-msg');

            if (resendBtn.disabled) return;

            resendBtn.disabled = true;
            resendLabel.innerText = 'Sending...';

            const fd = new FormData();
            fd.append('otp_token', window.CURRENT_2FA_TOKEN || '');

            fetch('api.php?api=resend_admin_2fa', {method: 'POST', body: fd})
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.CURRENT_2FA_TOKEN = data.otp_token;
                    if (alertBox) {
                        alertBox.className = 'login-alert warning-alert';
                        alertBox.innerHTML = '<i class="fa-solid fa-circle-check" style="color:#10b981;"></i> <span>Fresh verification code dispatched to ' + (data.email_target || 'bfscaptain@gmail.com') + '!</span>';
                        alertBox.style.display = 'flex';
                    }

                    let cooldown = 60;
                    resendLabel.innerText = `Resend in ${cooldown}s`;
                    if (resendCooldownTimer) clearInterval(resendCooldownTimer);
                    resendCooldownTimer = setInterval(() => {
                        cooldown--;
                        if (cooldown <= 0) {
                            clearInterval(resendCooldownTimer);
                            resendBtn.disabled = false;
                            resendLabel.innerText = 'Resend Code';
                        } else {
                            resendLabel.innerText = `Resend in ${cooldown}s`;
                        }
                    }, 1000);
                } else {
                    resendBtn.disabled = false;
                    resendLabel.innerText = 'Resend Code';
                    if (alertBox) {
                        alertBox.className = 'login-alert';
                        alertBox.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> <span>' + (data.error || 'Failed to dispatch code') + '</span>';
                        alertBox.style.display = 'flex';
                    }
                }
            })
            .catch(err => {
                console.error(err);
                resendBtn.disabled = false;
                resendLabel.innerText = 'Resend Code';
            });
        }

        // Auto digit filtering & Auto-submit on 6 digits
        document.addEventListener('DOMContentLoaded', () => {
            const otpInput = document.getElementById('otp_code_input');
            if (otpInput) {
                otpInput.addEventListener('input', (e) => {
                    e.target.value = e.target.value.replace(/[^0-9]/g, '');
                    if (e.target.value.length === 6) {
                        const form = document.getElementById('twofa-form');
                        if (form) {
                            form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>
