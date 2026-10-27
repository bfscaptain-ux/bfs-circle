@echo off
title BFS Circle - Stop All Services
color 0C
cd /d "%~dp0"

echo ======================================================================
echo         STOPPING ALL BFS CRM & WHATSAPP BACKGROUND SERVICES
echo ======================================================================
echo.

:: 1. Stop PHP Server on port 8000
for /f "tokens=5" %%a in ('netstat -ano ^| findstr :8000 ^| findstr LISTENING') do (
    echo Stopping PHP server (PID %%a)...
    taskkill /F /PID %%a >nul 2>&1
)

:: 2. Stop WhatsApp Node Engine on port 3001 (Port 3000 is left untouched for website)
for /f "tokens=5" %%a in ('netstat -ano ^| findstr :3001 ^| findstr LISTENING') do (
    echo Stopping WhatsApp engine (PID %%a)...
    taskkill /F /PID %%a >nul 2>&1
)

:: Close background command windows if any remain
taskkill /F /FI "WINDOWTITLE eq BFS_PHP_SERVER*" >nul 2>&1
taskkill /F /FI "WINDOWTITLE eq BFS_WA_ENGINE*" >nul 2>&1

echo.
echo [DONE] All CRM & WhatsApp services stopped successfully.
ping 127.0.0.1 -n 3 >nul
exit
