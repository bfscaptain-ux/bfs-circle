@echo off
title BFS Circle - Deploy News Editorial Studio & Website Sync to VPS
color 0A
cls
echo ======================================================================
echo       BFS CIRCLE - DEPLOY UPGRADED NEWS EDITORIAL STUDIO TO VPS
echo ======================================================================
echo.
echo VPS IP      : 200.234.34.191
echo CRM Domain  : https://crm.bfsfin.cloud
echo Web Domain  : https://bfsfin.com
echo Target CRM  : /var/www/crm/
echo.
echo ======================================================================
echo Kya update hoga:
echo   1. Luxury 2-Column News Editorial Studio (settings.php)
echo   2. Production Next.js API Bridge (api_website_news.php)
echo   3. Dynamic on-demand news pages on bfsfin.com
echo   4. Auto-sync of Inflation Hits 6.09% RBI Repo Rate article
echo ======================================================================
echo.
echo Enter your VPS root password when prompted:
echo.

echo [1/3] Uploading latest settings.php and api_website_news.php to CRM...
scp "e:\Downloads\client mgmt2\settings.php" "e:\Downloads\client mgmt2\api_website_news.php" root@200.234.34.191:/var/www/crm/

if %errorlevel% neq 0 (
    echo.
    echo [ERROR] SCP upload failed! Please check your password or connection.
    pause
    exit /b
)

echo.
echo [2/3] Updating CRM permissions on VPS...
ssh -t root@200.234.34.191 "cd /var/www/crm && chown www-data:www-data settings.php api_website_news.php && git pull origin main"

echo.
echo [3/3] Pulling latest website code and restarting Next.js on VPS...
ssh -t root@200.234.34.191 "bash /root/UPDATE_WEBSITE_ON_VPS.sh || (cd /var/www/bfsfin && git pull origin master && npm run build && pm2 restart bfsfin)"

echo.
echo ======================================================================
echo   🎉 DEPLOYMENT COMPLETED! NEWS IS NOW LIVE ON BOTH CRM & WEBSITE!
echo ======================================================================
echo.
echo Ab aap:
echo   1. CRM Desk : https://crm.bfsfin.cloud/settings.php?tab=news
echo   2. Public   : https://bfsfin.com/news
echo par jaakar check kar sakte hain!
echo.
pause
