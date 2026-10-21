@echo off
title Transfer Local Data to Hostinger VPS
color 0B
echo ======================================================================
echo       BFS CIRCLE - TRANSFER LOCAL DATABASE TO VPS
echo ======================================================================
echo.
echo VPS IP  : 200.234.34.191
echo Domain  : crm.bfsfin.cloud
echo File    : e:\Downloads\client mgmt2\crm.db (~33 MB)
echo.
echo ======================================================================
echo Step 1/2: Uploading crm.db to VPS...
echo (Enter your VPS root password when prompted)
echo ======================================================================
echo.
scp "e:\Downloads\client mgmt2\crm.db" root@200.234.34.191:/var/www/crm/crm.db

if %errorlevel% neq 0 (
    echo.
    echo [ERROR] Upload failed. Please verify password and internet connection.
    pause
    exit /b
)

echo.
echo ======================================================================
echo Step 2/2: Setting Web Server Permissions on VPS...
echo (Enter your VPS root password once more if prompted)
echo ======================================================================
ssh root@200.234.34.191 "chown -R www-data:www-data /var/www/crm/crm.db /var/www/crm/uploads && chmod 664 /var/www/crm/crm.db"

echo.
echo ======================================================================
echo   [SUCCESS] DATABASE SYNCED SUCCESSFULLY!
echo ======================================================================
echo.
echo Ab aap browser me https://crm.bfsfin.cloud/dashboard.php open karke
echo Refresh (F5) karein - Saara local data turant show ho jayega!
echo.
pause
