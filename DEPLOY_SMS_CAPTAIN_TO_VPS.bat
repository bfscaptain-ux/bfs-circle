@echo off
title BFS Circle - Deploy SMS Captain to VPS
color 0A
cls
echo ======================================================================
echo           BFS CIRCLE - DEPLOY SMS CAPTAIN TO PRODUCTION VPS
echo ======================================================================
echo.
echo VPS IP      : 200.234.34.191
echo Subdomains  : 
echo   - Web Dashboard : https://sms.bfsfin.cloud
echo   - API Gateway   : https://sms-api.bfsfin.cloud
echo.
echo ======================================================================
echo Prerequisites (Before running):
echo   Ensure DNS A Records exist in Cloudflare / Domain registrar:
echo     - sms.bfsfin.cloud      -> 200.234.34.191
echo     - sms-api.bfsfin.cloud  -> 200.234.34.191
echo ======================================================================
echo.
set /p confirm="Do you want to proceed with SMS Captain deployment to VPS? (y/n): "
if /i not "%confirm%"=="y" goto cancel

echo.
echo ======================================================================
echo [1/3] Creating directory on VPS...
echo ======================================================================
ssh root@200.234.34.191 "mkdir -p /var/www/sms-captain"

echo.
echo ======================================================================
echo [2/3] Syncing SMS Captain source files to VPS (excluding node_modules)...
echo ======================================================================
scp -r "G:\SMS-Captain-Complete\SMS-Captain-Project\api" root@200.234.34.191:/var/www/sms-captain/
scp -r "G:\SMS-Captain-Complete\SMS-Captain-Project\web" root@200.234.34.191:/var/www/sms-captain/
scp "e:\Downloads\client mgmt2\DEPLOY_SMS_CAPTAIN_ON_VPS.sh" root@200.234.34.191:/var/www/sms-captain/setup.sh

echo.
echo ======================================================================
echo [3/3] Running VPS Setup & Startup...
echo ======================================================================
ssh -t root@200.234.34.191 "bash /var/www/sms-captain/setup.sh"

echo.
echo ======================================================================
echo   🎉 DEPLOYMENT COMPLETE!
echo ======================================================================
echo.
echo Access SMS Captain live at:
echo   Dashboard : https://sms.bfsfin.cloud
echo   API       : https://sms-api.bfsfin.cloud
echo.
pause
exit /b

:cancel
echo Deployment cancelled.
pause
