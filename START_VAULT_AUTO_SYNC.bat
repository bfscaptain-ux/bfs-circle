@echo off
title BFS Vault Auto-Sync Engine (F:\BFS)
color 0B
cd /d "%~dp0"

echo ======================================================================
echo       BFS CIRCLE - ON-PREMISE VAULT REAL-TIME AUTO-SYNC DAEMON
echo ======================================================================
echo Target Storage : F:\BFS\Vault_Leads
echo Polling Cycle  : Every 60 seconds
echo Status         : Active in background
echo ======================================================================
echo.

:loop
"%~dp0php-portable\php.exe" "%~dp0bfs_vault_daemon.php" >nul 2>&1
timeout /t 60 /nobreak >nul
goto loop
