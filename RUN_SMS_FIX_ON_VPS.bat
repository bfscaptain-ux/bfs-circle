@echo off
title BFS Circle - Fix SMS Captain on VPS
color 0A
cls
echo ======================================================================
echo           BFS CIRCLE - 1-CLICK FIX FOR SMS CAPTAIN ON VPS
echo ======================================================================
echo.
echo VPS IP      : 200.234.34.191
echo SMS Web     : https://sms.bfsfin.cloud
echo SMS API     : https://sms-api.bfsfin.cloud
echo.
echo Kya kya fix hoga:
echo   1. Nginx & SSL for sms-api.bfsfin.cloud (WhatsApp 404 interception fix)
echo   2. Internal server auth connection (localhost:3001)
echo   3. Seed account: singhani5549@gmail.com / ANUJ0701
echo   4. Seed account: bfscaptain@gmail.com / 123456
echo   5. Restore SMS-Captain.apk download link
echo ======================================================================
echo.
echo Connecting to VPS (enter your VPS root password when prompted)...
echo.
ssh -t root@200.234.34.191 "cd /var/www/crm && git fetch origin main && git reset --hard origin/main && chmod +x FIX_SMS_ALL.sh && ./FIX_SMS_ALL.sh"

if %errorlevel% neq 0 (
    echo.
    echo [ERROR] Fix execution failed! Please check your VPS connection or password.
    pause
    exit /b
)

echo.
echo ======================================================================
echo   SUCCESS: SMS CAPTAIN FIX COMPLETED!
echo ======================================================================
echo.
echo Ab aap browser me https://sms.bfsfin.cloud/login open karke
echo Email: singhani5549@gmail.com
echo Password: ANUJ0701
echo se login kar sakte hain!
echo.
pause
