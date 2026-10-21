#!/bin/bash
# ==============================================================================
# BFS Circle - Complete WhatsApp CRM VPS Auto-Fix & Rebuild Script
# ==============================================================================

echo "======================================================================"
echo "🔍 STEP 1/6: Checking Nginx port for wa.bfsfin.cloud..."
echo "======================================================================"

TARGET_PORT=""
NGINX_CONF=$(grep -rn "wa.bfsfin.cloud" /etc/nginx/sites-enabled/ 2>/dev/null | head -n 1 | cut -d: -f1)
if [ -z "$NGINX_CONF" ]; then
    NGINX_CONF=$(grep -rn "wa.bfsfin.cloud" /etc/nginx/sites-available/ 2>/dev/null | head -n 1 | cut -d: -f1)
fi

if [ -n "$NGINX_CONF" ]; then
    echo "Found Nginx config: $NGINX_CONF"
    TARGET_PORT=$(grep "proxy_pass" "$NGINX_CONF" | grep -oE "300[0-9]" | head -n 1)
fi

if [ -z "$TARGET_PORT" ]; then
    TARGET_PORT=3000
fi
echo "✅ Target port for WhatsApp Engine: $TARGET_PORT"

echo ""
echo "======================================================================"
echo "📥 STEP 2/6: Pulling latest codebase from GitHub..."
echo "======================================================================"
cd /var/www/crm
git stash || true
git pull origin main

echo ""
echo "======================================================================"
echo "🛑 STEP 3/6: Stopping old processes on Port $TARGET_PORT..."
echo "======================================================================"
pm2 delete bfs-wa 2>/dev/null || true
pm2 delete wa-crm 2>/dev/null || true

# Kill any lingering node process holding the port
fuser -k ${TARGET_PORT}/tcp 2>/dev/null || true
sleep 2

echo ""
echo "======================================================================"
echo "🔨 STEP 4/6: Building Next.js WhatsApp App (Clean Production Build)..."
echo "======================================================================"
cd /var/www/crm/wa

# Clean .next to ensure fresh build artifacts
rm -rf .next 2>/dev/null || true

# Run production build
npm run build

echo ""
echo "======================================================================"
echo "🚀 STEP 5/6: Launching WhatsApp Engine with PM2 on Port $TARGET_PORT..."
echo "======================================================================"
PORT=$TARGET_PORT pm2 start npm --name "bfs-wa" --cwd /var/www/crm/wa -- start -- -p $TARGET_PORT
pm2 save

sleep 6

echo ""
echo "======================================================================"
echo "🧪 STEP 6/6: Verifying Health..."
echo "======================================================================"
HTTP_LOCAL=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:$TARGET_PORT/login || true)
echo "Local Health Check (http://127.0.0.1:$TARGET_PORT/login): HTTP $HTTP_LOCAL"

HTTP_PUBLIC=$(curl -s -o /dev/null -w "%{http_code}" https://wa.bfsfin.cloud/login || true)
echo "Public Domain Health Check (https://wa.bfsfin.cloud/login): HTTP $HTTP_PUBLIC"

nginx -t 2>/dev/null && systemctl reload nginx

if [ "$HTTP_LOCAL" = "200" ] || [ "$HTTP_LOCAL" = "307" ] || [ "$HTTP_PUBLIC" = "200" ] || [ "$HTTP_PUBLIC" = "307" ]; then
    echo ""
    echo "======================================================================"
    echo "🎉 SUCCESS! WhatsApp CRM is LIVE and HEALTHY!"
    echo "Open https://crm.bfsfin.cloud/whatsapp_crm.php and press Ctrl + F5"
    echo "======================================================================"
else
    echo ""
    echo "⚠️ PM2 Logs for diagnosis:"
    pm2 logs bfs-wa --lines 20 --nostream || true
fi

