@echo off
title BFS Circle - Deploy CRM Update to VPS
color 0A
cls
echo ======================================================================
echo           BFS CIRCLE - DEPLOY LATEST CRM CODE TO VPS (crm.bfsfin.cloud)
echo ======================================================================
echo.
echo VPS IP      : 200.234.34.191
echo Live Domain : https://crm.bfsfin.cloud
echo Git Branch  : main (Latest commit pushed: SMS Captain 4 LIVE)
echo.
echo ======================================================================
echo Kya kya update hoga:
echo   - BFS Circle Tools: 4 LIVE (Added BFS SMS Gateway - sms_crm.php)
echo   - SMS Helper backend (sms_helper.php)
echo   - Spotlight Search Omnibox indexing
echo ======================================================================
echo.
set /p confirm="Do you want to pull latest code on VPS? (y/n): "
if /i not "%confirm%"=="y" goto cancel

echo.
echo ======================================================================
echo Connecting to VPS and pulling latest code from GitHub...
echo (Enter VPS root password when prompted)
echo ======================================================================
ssh -t root@200.234.34.191 "cd /var/www/crm && git pull origin main && chown -R www-data:www-data /var/www/crm"

if %errorlevel% neq 0 (
    echo.
    echo [ERROR] Git pull failed on VPS! Please check password / connection.
    pause
    exit /b
)

echo.
echo ======================================================================
echo   🎉 CRM UPDATE DEPLOYED TO VPS SUCCESSFULLY!
echo ======================================================================
echo.
echo Check live CRM at: https://crm.bfsfin.cloud
echo.
pause
exit /b

:cancel
echo.
echo Deployment cancelled.
pause
