#!/bin/bash
# ==============================================================================
# BFS Circle - Complete WhatsApp CRM VPS Diagnostic & Auto-Fix Script
# ==============================================================================

set -e

echo "======================================================================"
echo "🔍 STEP 1/5: Checking Port 3000 & 3001 on VPS..."
echo "======================================================================"
echo "Listening Ports:"
ss -tlpn | grep -E "3000|3001" || netstat -tlpn | grep -E "3000|3001" || true

echo ""
echo "======================================================================"
echo "🔍 STEP 2/5: Checking Nginx configuration for wa.bfsfin.cloud..."
echo "======================================================================"
grep -rn "proxy_pass" /etc/nginx/sites-enabled/ 2>/dev/null || true

echo ""
echo "======================================================================"
echo "📥 STEP 3/5: Pulling latest fixes from GitHub..."
echo "======================================================================"
cd /var/www/crm
git pull origin main

echo ""
echo "======================================================================"
echo "🔨 STEP 4/5: Building Next.js WhatsApp App (Clean Production Build)..."
echo "======================================================================"
cd /var/www/crm/wa

# Clean old cache to eliminate Server Action reference errors
rm -rf .next/cache 2>/dev/null || true

# Run clean production build
npm run build

echo ""
echo "======================================================================"
echo "🚀 STEP 5/5: Restarting PM2 process for WhatsApp..."
echo "======================================================================"

# Determine if Nginx uses 3000 or 3001
TARGET_PORT=3000
if grep -rn "proxy_pass.*3001" /etc/nginx/sites-enabled/ >/dev/null 2>&1; then
    TARGET_PORT=3001
fi
echo "Target port for Next.js based on Nginx config: $TARGET_PORT"

# Stop any colliding old processes on this port
pm2 delete bfs-wa 2>/dev/null || true
pm2 delete wa-crm 2>/dev/null || true

# Start fresh with PM2 in production mode
PORT=$TARGET_PORT pm2 start npm --name "bfs-wa" -- start -- -p $TARGET_PORT
pm2 save

sleep 5

echo ""
echo "======================================================================"
echo "🧪 TESTING LOCAL HEALTH (Port $TARGET_PORT)..."
echo "======================================================================"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:$TARGET_PORT/login || true)
echo "Local Health check on http://127.0.0.1:$TARGET_PORT/login: HTTP $HTTP_CODE"

if [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "307" ]; then
    echo "✅ WhatsApp Engine is HEALTHY and listening on Port $TARGET_PORT!"
else
    echo "⚠️ Local Health Check returned $HTTP_CODE. Checking PM2 logs:"
    pm2 logs bfs-wa --lines 15 --nostream || true
fi

nginx -t && systemctl reload nginx

echo ""
echo "======================================================================"
echo "🎉 AUTO-FIX COMPLETED!"
echo "Now visit https://crm.bfsfin.cloud/whatsapp_crm.php and press Ctrl + F5!"
echo "======================================================================"
