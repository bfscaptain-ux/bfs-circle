@echo off
title BFS Circle - Deploy News Editorial Studio to VPS
color 0A
cls
echo ======================================================================
echo       BFS CIRCLE - DEPLOY UPGRADED NEWS EDITORIAL STUDIO TO VPS
echo ======================================================================
echo.
echo VPS IP      : 200.234.34.191
echo Live Domain : https://crm.bfsfin.cloud
echo Target Path : /var/www/crm/
echo.
echo ======================================================================
echo Kya update hoga:
echo   1. Luxury 2-Column News Editorial Studio (settings.php)
echo   2. Fixed unescaped HTML quotes & layout overflow bug
echo   3. 1-Click Fast-Track Blueprints (RBI, Festive Cut, MSME, CIBIL)
echo   4. Live Website Preview Mode & Working Rich Snippet Toolbar
echo   5. Website News Bridge API (api_website_news.php)
echo ======================================================================
echo.
echo Enter your VPS root password when prompted:
echo.

echo [1/2] Uploading latest settings.php & api_website_news.php via SCP...
scp "e:\Downloads\client mgmt2\settings.php" "e:\Downloads\client mgmt2\api_website_news.php" root@200.234.34.191:/var/www/crm/

if %errorlevel% neq 0 (
    echo.
    echo [ERROR] SCP upload failed! Please check your password or connection.
    pause
    exit /b
)

echo.
echo [2/2] Updating permissions & pulling latest Git commit on VPS...
ssh -t root@200.234.34.191 "cd /var/www/crm && chown www-data:www-data settings.php api_website_news.php && git pull origin main"

echo.
echo ======================================================================
echo   🎉 DEPLOYMENT COMPLETED! NEWS STUDIO IS NOW LIVE ON CRM!
echo ======================================================================
echo.
echo Ab aap browser me https://crm.bfsfin.cloud/settings.php?tab=news
echo ko refresh (Ctrl + F5) karke "Publish New Article" par click karein!
echo.
pause
