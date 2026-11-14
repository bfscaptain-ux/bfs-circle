@echo off
title BFS Circle - Sync Anurag Docs & Auto-Sync WhatsApp Media
color 0A
cls
echo ======================================================================
echo       BFS CIRCLE - CRM & WHATSAPP MEDIA AUTO-SYNC UPDATE
echo ======================================================================
echo.
echo VPS IP      : 200.234.34.191
echo Live Domain : https://crm.bfsfin.cloud
echo Target Path : /var/www/crm/
echo.
echo ======================================================================
echo Files being updated on VPS:
echo   1. config.php         (Whitelisted api_wa_lead from session redirect)
echo   2. api_wa_lead.php    (Auto-categorize documents: KYC, Banking, Income)
echo   3. sync_anurag_docs.php (Attach all 8 WhatsApp docs for Anurag Sikarwar)
echo ======================================================================
echo.
echo Enter VPS root password when prompted:
echo.

echo [1/3] Uploading core files to VPS via SCP...
scp "e:\Downloads\client mgmt2\config.php" "e:\Downloads\client mgmt2\api_wa_lead.php" "e:\Downloads\client mgmt2\sync_anurag_docs.php" root@200.234.34.191:/var/www/crm/

if %errorlevel% neq 0 (
    echo.
    echo [ERROR] SCP upload failed! Please check your password or connection.
    pause
    exit /b
)

echo.
echo [2/3] Setting permissions and verifying PHP syntax on VPS...
ssh -t root@200.234.34.191 "chown -R www-data:www-data /var/www/crm/config.php /var/www/crm/api_wa_lead.php /var/www/crm/sync_anurag_docs.php && chmod 664 /var/www/crm/config.php /var/www/crm/api_wa_lead.php /var/www/crm/sync_anurag_docs.php && php -l /var/www/crm/api_wa_lead.php"

echo.
echo [3/3] Pulling latest git repository & running Anurag doc sync...
ssh -t root@200.234.34.191 "cd /var/www/crm && git stash && git pull origin main && php sync_anurag_docs.php"

echo.
echo ======================================================================
echo   🎉 ALL 8 DOCUMENTS ATTACHED & WHATSAPP MEDIA BRIDGE LIVE!
echo ======================================================================
echo.
pause
