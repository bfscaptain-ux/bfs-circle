@echo off
title BFS Vault Auto-Sync Engine (F:\BFS\Vault_Leads)
color 0B
cd /d "%~dp0"

echo ======================================================================
echo       BFS CIRCLE - ON-PREMISE VAULT REAL-TIME AUTO-SYNC DAEMON
echo ======================================================================
echo Target Physical Storage : F:\BFS\Vault_Leads
echo Cloud VPS Endpoint      : https://crm.bfsfin.cloud
echo Polling Frequency       : Every 60 Seconds
echo ======================================================================
echo.

:loop
"%~dp0php-portable\php.exe" "%~dp0bfs_vault_daemon.php"
echo.
echo Waiting 60 seconds before next sync cycle... (Press Ctrl+C to stop)
timeout /t 60 /nobreak >nul
goto loop
