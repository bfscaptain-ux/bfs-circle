#!/bin/bash
# ==============================================================================
# BFS Circle - Instant WhatsApp Engine Fix & Reload
# ==============================================================================

echo "======================================================================"
echo "🔄 STEP 1/3: Checking Current Status & Freeing Port 3000..."
echo "======================================================================"

STATUS_FILE="/var/www/crm/wa_status.txt"
{
    echo "=== BEFORE RESTART ==="
    pm2 list
    ss -tlpn | grep -E "3000|3001"
} > "$STATUS_FILE" 2>&1

# Stop and restart cleanly on port 3000
pm2 delete bfs-wa 2>/dev/null || true
fuser -k 3000/tcp 2>/dev/null || true
fuser -k 3001/tcp 2>/dev/null || true
sleep 1

echo ""
echo "======================================================================"
echo "🚀 STEP 2/3: Starting WhatsApp Engine on Port 3000..."
echo "======================================================================"
cd /var/www/crm/wa
PORT=3000 pm2 start npm --name "bfs-wa" --cwd /var/www/crm/wa -- start -- -p 3000
pm2 save

sleep 4

echo ""
echo "======================================================================"
echo "🧪 STEP 3/3: Verifying WhatsApp Status..."
echo "======================================================================"
systemctl reload nginx 2>/dev/null || true

HTTP_LOCAL=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:3000/login || true)
HTTP_PUBLIC=$(curl -s -o /dev/null -w "%{http_code}" https://wa.bfsfin.cloud/login || true)

echo "Local Status  (127.0.0.1:3000): HTTP $HTTP_LOCAL"
echo "Public Status (wa.bfsfin.cloud): HTTP $HTTP_PUBLIC"

{
    echo ""
    echo "=== AFTER RESTART ==="
    pm2 list
    echo "Local: $HTTP_LOCAL, Public: $HTTP_PUBLIC"
    echo ""
    echo "=== PM2 LOGS (Last 25) ==="
    pm2 logs bfs-wa --lines 25 --nostream
} >> "$STATUS_FILE" 2>&1

chmod 644 "$STATUS_FILE"

if [ "$HTTP_LOCAL" = "200" ] || [ "$HTTP_PUBLIC" = "200" ]; then
    echo ""
    echo "======================================================================"
    echo "🎉 SUCCESS: WhatsApp CRM is 100% ONLINE & LIVE!"
    echo "Ab browser me https://crm.bfsfin.cloud/whatsapp_crm.php open karein"
    echo "======================================================================"
else
    echo "⚠️ Something failed. Showing logs:"
    pm2 logs bfs-wa --lines 20 --nostream
fi
