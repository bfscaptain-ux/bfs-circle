#!/bin/bash
# ==============================================================================
# BFS Circle - Diagnose WhatsApp CRM 502 Bad Gateway & Ports
# ==============================================================================

echo "======================================================================"
echo "📋 Collecting WhatsApp Diagnostics..."
echo "======================================================================"

DIAG_FILE="/var/www/crm/wa_diag.txt"

{
    echo "=== DATE & UPTIME ==="
    date
    uptime

    echo ""
    echo "=== PM2 STATUS ==="
    pm2 list 2>&1

    echo ""
    echo "=== LISTENING PORTS (3000, 3001, 4007) ==="
    ss -tlpn | grep -E "3000|3001|4007" || netstat -tlpn | grep -E "3000|3001|4007"

    echo ""
    echo "=== NGINX CONFIG FOR wa.bfsfin.cloud ==="
    grep -rn "wa.bfsfin.cloud" /etc/nginx/sites-enabled/ /etc/nginx/sites-available/ 2>/dev/null
    for conf in $(grep -rl "wa.bfsfin.cloud" /etc/nginx/sites-enabled/ 2>/dev/null); do
        echo "--- $conf ---"
        grep -E "server_name|proxy_pass|listen" "$conf"
    done

    echo ""
    echo "=== NGINX RECENT ERROR LOGS (Last 20) ==="
    tail -n 20 /var/log/nginx/error.log 2>/dev/null

    echo ""
    echo "=== PM2 LOGS FOR bfs-wa (Last 20) ==="
    pm2 logs bfs-wa --lines 20 --nostream 2>/dev/null

} > "$DIAG_FILE" 2>&1

chmod 644 "$DIAG_FILE"

echo "✅ Diagnostics saved to $DIAG_FILE"
cat "$DIAG_FILE"
