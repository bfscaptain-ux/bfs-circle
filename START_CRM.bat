@echo off
title BFS Circle - CRM & WhatsApp Master Launcher
color 0A
cd /d "%~dp0"

echo ======================================================================
echo           BFS CIRCLE - CRM & WHATSAPP MASTER LAUNCHER
echo ======================================================================
echo.

:: 1. Check & Launch PHP CRM Server (Port 8000)
netstat -ano | findstr :8000 | findstr LISTENING >nul 2>&1
if %errorlevel% equ 0 (
    echo [OK] Main CRM Server is already running on port 8000.
) else (
    echo [STARTING] Launching PHP CRM Server on port 8000...
    set PHP_CLI_SERVER_WORKERS=4
    start "BFS_PHP_SERVER" /min "%~dp0php-portable\php.exe" -S 0.0.0.0:8000
    ping 127.0.0.1 -n 2 >nul
)

:: 2. Check & Launch WhatsApp CRM Node Engine (Dedicated Port 3001 - Port 3000 is preserved for main website)
netstat -ano | findstr :3001 | findstr LISTENING >nul 2>&1
if %errorlevel% equ 0 (
    echo [OK] WhatsApp CRM Engine is already running on port 3001.
) else (
    echo [STARTING] Launching WhatsApp CRM Engine on port 3001...
    start "BFS_WA_ENGINE" /min cmd /c "cd /d "%~dp0wa" && npm run dev -- --webpack -p 3001"
    ping 127.0.0.1 -n 3 >nul
)

echo.
echo ======================================================================
echo   [SUCCESS] Both CRM Services are Active! Opening in Browser...
echo ======================================================================
echo.
start http://localhost:8000

echo Launch complete. This window will auto-close in 3 seconds.
ping 127.0.0.1 -n 4 >nul
exit
