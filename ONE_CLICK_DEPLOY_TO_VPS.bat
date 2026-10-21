@echo off
title BFS Circle - One-Click VPS Deployment
color 0A
cls
echo ======================================================================
echo           BFS CIRCLE - ONE-CLICK PRODUCTION VPS DEPLOYMENT
echo ======================================================================
echo.
echo VPS IP      : 200.234.34.191
echo Live Domain : https://crm.bfsfin.cloud
echo Git Branch  : main (Latest commits already pushed to GitHub)
echo.
echo ======================================================================
echo Kya kya deploy hoga:
echo   1. Latest Code (marketing.php, login.php, api.php, header.php)
echo   2. Fresh Database (crm.db with new Admin & SMTP passwords)
echo   3. Correct Web Server Permissions (www-data:www-data)
echo ======================================================================
echo.
set /p confirm="Do you want to proceed with deployment? (y/n): "
if /i not "%confirm%"=="y" goto cancel

echo.
echo ======================================================================
echo [1/3] Uploading latest crm.db to VPS...
echo (Enter VPS root password when prompted)
echo ======================================================================
scp "e:\Downloads\client mgmt2\crm.db" root@200.234.34.191:/var/www/crm/crm.db

if %errorlevel% neq 0 (
    echo.
    echo [ERROR] crm.db upload failed! Please check password / connection.
    pause
    exit /b
)

echo.
echo ======================================================================
echo [2/3] Pulling latest Git code and setting permissions on VPS...
echo (Enter VPS root password when prompted)
echo ======================================================================
ssh root@200.234.34.191 "cd /var/www/crm && git pull origin main && chown -R www-data:www-data /var/www/crm/crm.db /var/www/crm/uploads && chmod 664 /var/www/crm/crm.db"

if %errorlevel% neq 0 (
    echo.
    echo [ERROR] Git pull or permissions failed on VPS.
    pause
    exit /b
)

echo.
echo ======================================================================
echo [3/3] VERIFYING DEPLOYMENT STATUS...
echo ======================================================================
ssh root@200.234.34.191 "echo '--- Git Status on VPS: ---' && cd /var/www/crm && git log -1 --oneline"

echo.
echo ======================================================================
echo   🎉 DEPLOYMENT TO VPS COMPLETED SUCCESSFULLY!
echo ======================================================================
echo.
echo Ab aap browser me https://crm.bfsfin.cloud open karke login kar sakte hain:
echo   - Username: admin
echo   - Password: BFSaNuJ@070123
echo   - OTP seedha bfscaptain@gmail.com par real-time aayega!
echo.
echo (Agar aapko Listmonk Docker Engine bhi VPS pe start karna ho, to
echo VPS terminal me ye command run karein: bash /var/www/crm/DEPLOY_LISTMONK_ON_VPS.sh)
echo.
pause
exit /b

:cancel
echo.
echo Deployment cancelled by user.
pause
