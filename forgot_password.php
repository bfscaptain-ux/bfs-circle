<?php
require_once 'config.php';

// If already logged in, redirect to dashboard
if (!empty($_SESSION['user_id'])) {
    header("Location: " . get_role_dashboard_url($_SESSION['role'] ?? 'Staff', false));
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - BFS Financial Services</title>
    
    <!-- Instant Image Preloads with High Fetch Priority -->
    <link rel="preload" as="image" href="login.webp" type="image/webp" fetchpriority="high">
    <link rel="preload" as="image" href="logo.png" type="image/png" fetchpriority="high">
    
    <!-- Google Fonts Inter & Outfit with display=swap -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet">
    
    <!-- FontAwesome & SweetAlert2 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root {
            --primary: #10b981;
            --primary-hover: #059669;
            --secondary: #f97316;
            --primary-glow: rgba(16, 185, 129, 0.25);
            --bg-main: #000000;
            --bg-card: rgba(10, 10, 10, 0.75);
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
            overflow-x: hidden;
            position: relative;
        }

        /* Dedicated GPU Compositor Layer for Background Stage (Zero repaints) */
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
        .auth-card-wrapper {
            position: relative;
            width: 100%;
            max-width: 440px;
            z-index: 10;
            margin: auto;
        }

        /* 1. Luminous Diffused Ambient Aura (Continuously rotating behind the card) */
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
        }

        /* 2. Razor-Sharp Rotating Border Laser Beam */
        .card-border-beam {
            position: relative;
            border-radius: 24px;
            padding: 1.8px;
            overflow: hidden;
            z-index: 1;
            box-shadow: 0 25px 60px -10px rgba(0, 0, 0, 0.95);
            background: rgba(255, 255, 255, 0.08);
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
        }

        @keyframes rotateLaser {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* 3. Obsidian Pitch Black Card Interior */
        .auth-container {
            position: relative;
            z-index: 2;
            width: 100%;
            border-radius: 22px;
            background: rgba(0, 0, 0, 0.96); /* Deep Pure Dark Black */
            backdrop-filter: blur(40px) saturate(200%);
            -webkit-backdrop-filter: blur(40px) saturate(200%);
            padding: 36px 32px;
            border: 1px solid rgba(255, 255, 255, 0.14); /* Crisp White Border */
            box-shadow: 
                inset 0 1px 1px rgba(255, 255, 255, 0.25),
                inset 0 -1px 1px rgba(0, 0, 0, 0.9),
                0 30px 80px rgba(0, 0, 0, 0.98);
        }

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

        .brand-name {
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 21px;
            letter-spacing: -0.4px;
            color: #ffffff; /* Crisp Pure White */
        }

        .step-badge-row {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-bottom: 22px;
        }

        .step-dot {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-muted);
            border: 1px solid var(--border);
            transition: all 0.3s ease;
        }

        .step-dot.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            box-shadow: 0 0 12px rgba(16, 185, 129, 0.4);
        }

        .step-dot.done {
            background: #059669;
            color: white;
            border-color: #059669;
        }

        .step-line {
            width: 32px;
            height: 2px;
            background: rgba(255, 255, 255, 0.15);
        }

        .step-line.active {
            background: var(--primary);
        }

        h2 {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            font-size: 24px;
            text-align: center;
            margin-bottom: 6px;
            color: #ffffff; /* Crisp Pure White */
        }

        .step-desc {
            font-size: 13.5px;
            color: #cbd5e1; /* Clean High-Contrast Light White */
            text-align: center;
            margin-bottom: 24px;
            line-height: 1.5;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 18px;
        }

        label {
            font-size: 12.5px;
            font-weight: 600;
            color: #ffffff; /* Crisp Pure White */
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"] {
            padding: 12px 14px;
            border: 1.2px solid rgba(255, 255, 255, 0.22);
            border-radius: var(--radius-md);
            font-size: 14px;
            color: #ffffff;
            background-color: #000000; /* Pure Dark Black */
            transition: border-color 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
            width: 100%;
        }

        input:focus {
            outline: none;
            border-color: #ffffff;
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.2), 0 0 16px rgba(16, 185, 129, 0.25);
            background-color: #050505;
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

        .otp-input-field {
            font-family: 'Courier New', monospace;
            font-size: 28px !important;
            letter-spacing: 12px;
            text-align: center;
            font-weight: 800;
            color: #34d399 !important;
            padding: 12px 10px !important;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 13px;
            border-radius: var(--radius-md);
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            border: none;
            background: linear-gradient(135deg, var(--secondary) 0%, var(--primary) 100%);
            background-size: 200% auto;
            color: white;
            transition: background-position 0.5s ease, transform 0.2s, opacity 0.2s;
            width: 100%;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }

        .btn:hover {
            background-position: right center;
        }

        .btn:active {
            transform: scale(0.98);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.08);
            color: #ffffff;
            border: 1px solid var(--border);
            margin-top: 10px;
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.14);
        }

        .password-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .eye-icon {
            position: absolute;
            right: 14px;
            cursor: pointer;
            color: #94a3b8;
            font-size: 15px;
            user-select: none;
        }
        
        .eye-icon:hover {
            color: #ffffff;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: var(--text-muted);
            font-size: 13px;
            text-decoration: none;
            transition: color 0.2s;
        }

        .back-link:hover {
            color: var(--primary);
        }

        .divider-or {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 20px 0;
            color: var(--text-muted);
            font-size: 12px;
            font-weight: 600;
        }

        .divider-or::before,
        .divider-or::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid var(--border);
        }

        .divider-or::before { margin-right: 12px; }
        .divider-or::after { margin-left: 12px; }

        .user-greeting-card {
            background: rgba(16, 185, 129, 0.12);
            border: 1px solid rgba(16, 185, 129, 0.3);
            border-radius: var(--radius-md);
            padding: 12px 16px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .step-section {
            display: none;
        }

        .step-section.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Card Angry Violent Shake & Red Alert on Error */
        .auth-card-wrapper.card-angry {
            animation: cardAngryShake 0.65s cubic-bezier(0.36, 0.07, 0.19, 0.97) both;
        }

        @keyframes cardAngryShake {
            10%, 90% { transform: translate3d(-8px, 0, 0); }
            20%, 80% { transform: translate3d(10px, 0, 0); }
            30%, 50%, 70% { transform: translate3d(-14px, 0, 0); }
            40%, 60% { transform: translate3d(14px, 0, 0); }
        }

        /* Red / Crimson warning laser when angry */
        .auth-card-wrapper.card-angry .card-ambient-aura {
            background: conic-gradient(
                from 0deg,
                rgba(239, 68, 68, 0.90) 0deg,
                rgba(220, 38, 38, 0.8) 90deg,
                rgba(249, 115, 22, 0.8) 180deg,
                rgba(239, 68, 68, 0.90) 360deg
            );
            filter: blur(35px);
            opacity: 1;
            animation: rotateLaser 1.6s linear infinite !important;
        }

        .auth-card-wrapper.card-angry .card-border-beam::before {
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

        .auth-card-wrapper.card-angry .auth-container {
            border-color: rgba(239, 68, 68, 0.65);
            box-shadow: 
                inset 0 1px 1px rgba(239, 68, 68, 0.5),
                0 0 45px rgba(239, 68, 68, 0.4),
                0 30px 80px rgba(0, 0, 0, 0.98);
        }
    </style>
</head>
<body>
    <!-- Hardware-Accelerated Static Background Stage (Zero repaints) -->
    <div class="bg-stage"></div>

    <!-- Interactive Canvas Particles -->
    <canvas id="particles-canvas"></canvas>

    <div class="auth-card-wrapper">
        <!-- Ambient Diffused Glow Halo -->
        <div class="card-ambient-aura"></div>

        <!-- Rotating Laser Border Beam -->
        <div class="card-border-beam">
            <div class="auth-container">
        <!-- Brand Header -->
        <div class="brand-container">
            <img src="logo.png" alt="BFS Financial Services Logo" class="brand-logo-img">
            <div class="brand-name">BFS Financial Services</div>
        </div>

        <!-- Stepper Indicator -->
        <div class="step-badge-row">
            <div class="step-dot active" id="dotStep1">1</div>
            <div class="step-line" id="line1"></div>
            <div class="step-dot" id="dotStep2">2</div>
            <div class="step-line" id="line2"></div>
            <div class="step-dot" id="dotStep3">3</div>
        </div>

        <!-- ================= STEP 1: ENTER EMAIL ================= -->
        <div class="step-section active" id="sectionStep1">
            <h2>Forgot Password?</h2>
            <p class="step-desc">Enter your registered email address. We will verify your identity by sending a 6-digit OTP code.</p>

            <form onsubmit="handleRequestOtp(event)">
                <div class="form-group">
                    <label>Registered Email Address</label>
                    <input type="email" id="input_email" placeholder="e.g. yourname@example.com" required autofocus>
                </div>
                <button type="submit" class="btn" id="btnSendOtp">
                    <i class="fas fa-paper-plane"></i>
                    <span>Send Verification Code</span>
                </button>
            </form>

            <a href="login.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Login
            </a>
        </div>

        <!-- ================= STEP 2: ENTER OTP ================= -->
        <div class="step-section" id="sectionStep2">
            <h2>Enter 6-Digit OTP</h2>
            <p class="step-desc">
                We sent a verification code to <br>
                <strong id="displayMaskedEmail" style="color: #34d399;">your email</strong>
            </p>

            <form onsubmit="handleVerifyOtp(event)">
                <div class="form-group">
                    <label>6-Digit Verification Code</label>
                    <input type="text" id="input_otp" class="otp-input-field" maxlength="6" pattern="[0-9]{6}" placeholder="------" required autocomplete="off">
                </div>

                <button type="submit" class="btn" id="btnVerifyOtp">
                    <i class="fas fa-check-circle"></i>
                    <span>Verify Code</span>
                </button>
            </form>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 18px; font-size: 13px;">
                <span style="color: var(--text-muted);" id="resendCountdownText">Resend code in <strong id="countdownSeconds" style="color:#ffffff;">60</strong>s</span>
                <a href="#" id="linkResendOtp" style="color: var(--primary); text-decoration: none; font-weight: 600; display: none;" onclick="resendOtp(event)">Resend Code</a>
                <a href="#" style="color: var(--text-muted); text-decoration: none;" onclick="goToStep(1)">Change Email</a>
            </div>
        </div>

        <!-- ================= STEP 3: RESET OR CONTINUE ================= -->
        <div class="step-section" id="sectionStep3">
            <h2>Identity Verified!</h2>
            <p class="step-desc" style="margin-bottom: 16px;">Set a new password for future logins or proceed directly into your dashboard.</p>

            <!-- User Info Badge -->
            <div class="user-greeting-card">
                <div style="width: 36px; height: 36px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 16px;">
                    <i class="fas fa-user-check"></i>
                </div>
                <div>
                    <div style="font-weight: 700; font-size: 14px; color: #ffffff;" id="displayUserName">User</div>
                    <div style="font-size: 12px; color: #a7f3d0;" id="displayUserRole">Portal Member</div>
                </div>
            </div>

            <!-- Option 1: Recreate Password & Login -->
            <form onsubmit="handleComplete(event, 'reset_and_login')">
                <div class="form-group">
                    <label>New Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="input_new_password" placeholder="At least 4 characters" minlength="4">
                        <i class="fas fa-eye eye-icon" onclick="togglePassword('input_new_password', this)"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label>Confirm New Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="input_confirm_password" placeholder="Re-enter new password" minlength="4">
                        <i class="fas fa-eye eye-icon" onclick="togglePassword('input_confirm_password', this)"></i>
                    </div>
                </div>

                <button type="submit" class="btn" id="btnSavePassword">
                    <i class="fas fa-key"></i>
                    <span>Set New Password & Login</span>
                </button>
            </form>

            <div class="divider-or">OR KEEP CURRENT PASSWORD</div>

            <!-- Option 2: Direct Continue / Skip -->
            <button type="button" class="btn btn-secondary" id="btnContinueDirect" onclick="handleComplete(event, 'direct_continue')">
                <i class="fas fa-bolt" style="color: #fbbf24;"></i>
                <span>Continue Directly to Dashboard</span>
            </button>
        </div>
    </div>
    </div>
    </div>

    <script>
        let currentEmail = '';
        let currentResetToken = '';
        let resendInterval = null;

        function goToStep(stepNum) {
            document.querySelectorAll('.step-section').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.step-dot').forEach(el => el.classList.remove('active', 'done'));
            document.querySelectorAll('.step-line').forEach(el => el.classList.remove('active'));

            const sec = document.getElementById('sectionStep' + stepNum);
            if (sec) sec.classList.add('active');

            if (stepNum === 1) {
                document.getElementById('dotStep1').classList.add('active');
            } else if (stepNum === 2) {
                document.getElementById('dotStep1').classList.add('done');
                document.getElementById('line1').classList.add('active');
                document.getElementById('dotStep2').classList.add('active');
                document.getElementById('input_otp').focus();
            } else if (stepNum === 3) {
                document.getElementById('dotStep1').classList.add('done');
                document.getElementById('line1').classList.add('active');
                document.getElementById('dotStep2').classList.add('done');
                document.getElementById('line2').classList.add('active');
                document.getElementById('dotStep3').classList.add('active');
            }
        }

        function togglePassword(inputId, icon) {
            const el = document.getElementById(inputId);
            if (!el) return;
            if (el.type === 'password') {
                el.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                el.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        function startResendCountdown() {
            let seconds = 60;
            const textEl = document.getElementById('resendCountdownText');
            const linkEl = document.getElementById('linkResendOtp');
            const countEl = document.getElementById('countdownSeconds');

            textEl.style.display = 'inline';
            linkEl.style.display = 'none';

            if (resendInterval) clearInterval(resendInterval);

            resendInterval = setInterval(() => {
                seconds--;
                if (countEl) countEl.innerText = seconds;
                if (seconds <= 0) {
                    clearInterval(resendInterval);
                    textEl.style.display = 'none';
                    linkEl.style.display = 'inline';
                }
            }, 1000);
        }

        // STEP 1: Request OTP
        async function handleRequestOtp(e) {
            e.preventDefault();
            const emailInput = document.getElementById('input_email');
            const email = emailInput.value.trim();
            const btn = document.getElementById('btnSendOtp');

            if (!email) return;

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Searching & Sending OTP...</span>';

            const fd = new FormData();
            fd.append('email', email);

            try {
                const res = await fetch('api.php?api=forgot_password_request_otp', {
                    method: 'POST',
                    body: fd
                });
                const data = await res.json();

                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-paper-plane"></i> <span>Send Verification Code</span>';

                if (data.success) {
                    currentEmail = email;
                    currentResetToken = data.reset_token;
                    document.getElementById('displayMaskedEmail').innerText = data.masked_email || email;

                    Swal.fire({
                        icon: 'success',
                        title: 'OTP Sent!',
                        text: data.message || 'Please check your email inbox (and spam folder).',
                        timer: 2000,
                        showConfirmButton: false
                    });

                    goToStep(2);
                    startResendCountdown();
                } else {
                    if (window.triggerAngryParticles) window.triggerAngryParticles();
                    Swal.fire({
                        icon: 'error',
                        title: 'Account Not Found',
                        text: data.error || 'No account is linked to this email address.'
                    });
                }
            } catch (err) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-paper-plane"></i> <span>Send Verification Code</span>';
                if (window.triggerAngryParticles) window.triggerAngryParticles();
                Swal.fire({
                    icon: 'error',
                    title: 'Connection Error',
                    text: 'Unable to reach the server. Please check your internet.'
                });
            }
        }

        // Resend OTP
        async function resendOtp(e) {
            e.preventDefault();
            if (!currentEmail) return;

            const fd = new FormData();
            fd.append('email', currentEmail);

            const res = await fetch('api.php?api=forgot_password_request_otp', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                currentResetToken = data.reset_token;
                Swal.fire({
                    icon: 'success',
                    title: 'New OTP Dispatched!',
                    text: 'A fresh OTP code has been sent to your email.',
                    timer: 1800,
                    showConfirmButton: false
                });
                startResendCountdown();
            } else {
                if (window.triggerAngryParticles) window.triggerAngryParticles();
                Swal.fire({ icon: 'error', title: 'Error', text: data.error });
            }
        }

        // STEP 2: Verify OTP
        async function handleVerifyOtp(e) {
            e.preventDefault();
            const otp = document.getElementById('input_otp').value.trim();
            const btn = document.getElementById('btnVerifyOtp');

            if (otp.length !== 6) {
                if (window.triggerAngryParticles) window.triggerAngryParticles();
                Swal.fire({ icon: 'warning', title: 'Invalid Code', text: 'Please enter the complete 6-digit OTP code.' });
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Verifying...</span>';

            const fd = new FormData();
            fd.append('email', currentEmail);
            fd.append('otp_code', otp);
            fd.append('reset_token', currentResetToken);

            try {
                const res = await fetch('api.php?api=forgot_password_verify_otp', {
                    method: 'POST',
                    body: fd
                });
                const data = await res.json();

                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check-circle"></i> <span>Verify Code</span>';

                if (data.success) {
                    if (data.reset_token) currentResetToken = data.reset_token;
                    document.getElementById('displayUserName').innerText = data.user_name || 'Member';
                    document.getElementById('displayUserRole').innerText = data.role ? `${data.role} Account` : 'Verified Account';

                    goToStep(3);
                } else {
                    if (window.triggerAngryParticles) window.triggerAngryParticles();
                    Swal.fire({
                        icon: 'error',
                        title: 'Verification Failed',
                        text: data.error || 'The entered OTP code is incorrect or expired.'
                    });
                }
            } catch (err) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check-circle"></i> <span>Verify Code</span>';
                if (window.triggerAngryParticles) window.triggerAngryParticles();
                Swal.fire({ icon: 'error', title: 'Connection Error', text: 'Could not connect to server.' });
            }
        }

        // STEP 3: Complete (Reset or Continue)
        async function handleComplete(e, actionType) {
            if (e) e.preventDefault();

            const fd = new FormData();
            fd.append('reset_token', currentResetToken);
            fd.append('action_type', actionType);

            if (actionType === 'reset_and_login') {
                const newPwd = document.getElementById('input_new_password').value;
                const confirmPwd = document.getElementById('input_confirm_password').value;

                if (!newPwd || newPwd.length < 4) {
                    if (window.triggerAngryParticles) window.triggerAngryParticles();
                    Swal.fire({ icon: 'warning', title: 'Weak Password', text: 'Password must be at least 4 characters long.' });
                    return;
                }
                if (newPwd !== confirmPwd) {
                    if (window.triggerAngryParticles) window.triggerAngryParticles();
                    Swal.fire({ icon: 'error', title: 'Password Mismatch', text: 'New password and confirm password do not match.' });
                    return;
                }

                fd.append('new_password', newPwd);
                fd.append('confirm_password', confirmPwd);

                const btn = document.getElementById('btnSavePassword');
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Saving & Logging in...</span>';
            } else {
                const btn = document.getElementById('btnContinueDirect');
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Opening Dashboard...</span>';
            }

            try {
                const res = await fetch('api.php?api=forgot_password_complete', {
                    method: 'POST',
                    body: fd
                });
                const data = await res.json();

                if (data.success) {
                    if (window.triggerParticleExplosion) window.triggerParticleExplosion();
                    Swal.fire({
                        icon: 'success',
                        title: 'Welcome Back!',
                        text: data.message || 'Access verified. Redirecting...',
                        timer: 1600,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.href = data.redirect_url || 'dashboard.php';
                    });
                } else {
                    if (window.triggerAngryParticles) window.triggerAngryParticles();
                    Swal.fire({ icon: 'error', title: 'Action Failed', text: data.error || 'Session expired. Please try again.' });
                }
            } catch (err) {
                if (window.triggerAngryParticles) window.triggerAngryParticles();
                Swal.fire({ icon: 'error', title: 'Network Error', text: 'Failed to communicate with server.' });
            }
        }

        // 3D Perspective Particle Interactive Engine
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

            const fl = 480;
            let rotX = 0, rotY = 0;
            let targetRotX = 0, targetRotY = 0;

            const colors = [
                { main: 'rgba(16, 185, 129, ', glow: 'rgba(5, 150, 105, ' },
                { main: 'rgba(52, 211, 153, ', glow: 'rgba(16, 185, 129, ' },
                { main: 'rgba(249, 115, 22, ', glow: 'rgba(234, 88, 12, ' },
                { main: 'rgba(251, 191, 36, ', glow: 'rgba(245, 158, 11, ' },
                { main: 'rgba(56, 189, 248, ', glow: 'rgba(14, 165, 233, ' },
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
                    const radius = Math.random() * 520 + 90;
                    const theta = Math.random() * Math.PI * 2;
                    const phi = Math.acos((Math.random() * 2) - 1);

                    this.x = radius * Math.sin(phi) * Math.cos(theta);
                    this.y = (radius * Math.sin(phi) * Math.sin(theta)) * 0.75;
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
                    // Supernova Expansion mode on successful reset!
                    if (isExploding) {
                        this.x += this.vx;
                        this.y += this.vy;
                        this.z += this.vz;
                        this.vx *= 1.035;
                        this.vy *= 1.035;
                        this.vz *= 1.035;
                        this.baseSize *= 1.022;
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

                        // Centripetal tension
                        this.vx -= this.x * 0.006;
                        this.vy -= this.y * 0.006;
                        this.vz -= this.z * 0.006;
                        this.pulse += 0.22;
                        return;
                    }

                    this.pulse += this.pulseSpeed;

                    const cosO = Math.cos(this.orbitSpeed);
                    const sinO = Math.sin(this.orbitSpeed);
                    const ox = this.x * cosO - this.z * sinO;
                    const oz = this.z * cosO + this.x * sinO;
                    this.x = ox + this.vx;
                    this.y += this.vy;
                    this.z = oz + this.vz;

                    this.vx += (Math.random() - 0.5) * 0.06;
                    this.vy += (Math.random() - 0.5) * 0.06;
                    this.vz += (Math.random() - 0.5) * 0.06;
                    this.vx *= 0.98;
                    this.vy *= 0.98;
                    this.vz *= 0.98;

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

            // Global trigger for Supernova Particle Explosion
            window.triggerParticleExplosion = function() {
                isExploding = true;
                isAngry = false;
                
                for (let i = 0; i < particles.length; i++) {
                    const p = particles[i];
                    const dist = Math.sqrt(p.x * p.x + p.y * p.y + p.z * p.z) || 1;
                    const blastSpeed = Math.random() * 32 + 20;
                    p.vx = (p.x / dist) * blastSpeed;
                    p.vy = (p.y / dist) * blastSpeed;
                    p.vz = (p.z / dist) * blastSpeed + 18;
                    p.baseSize = Math.max(p.baseSize, 4.0);
                }

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

            // Global trigger for Angry Security Alert Particle Mode on error
            window.triggerAngryParticles = function() {
                isAngry = true;

                for (let i = 0; i < particles.length; i++) {
                    const p = particles[i];
                    p.vx = (Math.random() - 0.5) * 18;
                    p.vy = (Math.random() - 0.5) * 18;
                    p.vz = (Math.random() - 0.5) * 20;
                }

                const cardWrapper = document.querySelector('.auth-card-wrapper');
                if (cardWrapper) {
                    cardWrapper.classList.remove('card-angry');
                    void cardWrapper.offsetWidth; // Force reflow
                    cardWrapper.classList.add('card-angry');
                }

                if (angryTimer) clearTimeout(angryTimer);
                angryTimer = setTimeout(() => {
                    isAngry = false;
                    if (cardWrapper) cardWrapper.classList.remove('card-angry');
                }, 3200);
            };

            // Calm down if user starts typing in any input field
            document.addEventListener('input', () => {
                if (isAngry) {
                    isAngry = false;
                    const cardWrapper = document.querySelector('.auth-card-wrapper');
                    if (cardWrapper) cardWrapper.classList.remove('card-angry');
                }
            });

            function animate() {
                ctx.clearRect(0, 0, width, height);

                mouse.x += (mouse.targetX - mouse.x) * 0.08;
                mouse.y += (mouse.targetY - mouse.y) * 0.08;

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

                    const x1 = p.x * cosY - p.z * sinY;
                    const z1 = p.z * cosY + p.x * sinY;

                    const y1 = p.y * cosX - z1 * sinX;
                    const z2 = z1 * cosX + p.y * sinX;

                    const depth = z2 + 480;
                    if (depth < 15) continue;
                    const scale = fl / depth;

                    const sx = cx + x1 * scale;
                    const sy = cy + y1 * scale;

                    let mouseDist = 9999;
                    let mouseEffect = 0;
                    if (mouse.active && !isExploding) {
                        const mdx = sx - mouse.x;
                        const mdy = sy - mouse.y;
                        mouseDist = Math.sqrt(mdx * mdx + mdy * mdy);

                        if (mouseDist < mouse.radius) {
                            mouseEffect = (1 - mouseDist / mouse.radius);
                            p.vx += (mdx / (mouseDist + 1)) * mouseEffect * (isAngry ? 2.5 : 1.35);
                            p.vy += (mdy / (mouseDist + 1)) * mouseEffect * (isAngry ? 2.5 : 1.35);
                            p.vz -= mouseEffect * (isAngry ? 3.0 : 1.8);
                        }
                    }

                    const breathScale = 1.0 + Math.sin(p.pulse) * 0.52;
                    const renderSize = Math.max(0.9, p.baseSize * scale * breathScale * (1.0 + mouseEffect * 2.4));
                    const alpha = Math.min(1.0, Math.max(0.12, (scale * 0.85) + (mouseEffect * 0.55)));

                    projected.push({
                        sx, sy, z: z2, renderSize, alpha, color: p.color, sprite: p.sprite, angrySprite: p.angrySprite, mouseDist
                    });
                }

                projected.sort((a, b) => b.z - a.z);

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

                for (let i = 0; i < projected.length; i++) {
                    const pi = projected[i];
                    const rad = Math.max(1.5, pi.renderSize * (isExploding ? 4.2 : (isAngry ? 2.8 : 2.4)));

                    if (isExploding) {
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
    </script>
</body>
</html>
