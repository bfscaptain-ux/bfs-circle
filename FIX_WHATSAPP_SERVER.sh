#!/bin/bash
# ==============================================================================
# BFS Circle - Master WhatsApp CRM 502 Bad Gateway Fixer
# ==============================================================================

LOG_FILE="/var/www/crm/wa_fix.txt"

{
echo "======================================================================"
echo "🚀 1/4: Ensuring WhatsApp Next.js Engine is Running on Port 3000..."
echo "======================================================================"

cd /var/www/crm/wa

# Kill any conflicting or zombie process on ports 3000 and 3001
pm2 delete bfs-wa 2>/dev/null || true
fuser -k 3000/tcp 2>/dev/null || true
fuser -k 3001/tcp 2>/dev/null || true
sleep 1

# Start WhatsApp engine on port 3000 with PM2
PORT=3000 pm2 start npm --name "bfs-wa" --cwd /var/www/crm/wa -- start -- -p 3000
pm2 save

echo "Waiting for Next.js engine to initialize..."
for i in {1..12}; do
    LOCAL_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:3000/login || true)
    if [ "$LOCAL_CODE" = "200" ] || [ "$LOCAL_CODE" = "307" ]; then
        echo "✅ Next.js engine is ONLINE and healthy on port 3000 (HTTP $LOCAL_CODE)!"
        break
    fi
    sleep 1
done

echo ""
echo "======================================================================"
echo "🔧 2/4: Fixing Nginx Configuration for wa.bfsfin.cloud..."
echo "======================================================================"

cd /var/www/crm
if command -v python3 &> /dev/null; then
    python3 fix_wa_nginx.py
else
    echo "python3 not found, using bash fallback..."
    NGINX_CONF=$(grep -rl "wa.bfsfin.cloud" /etc/nginx/sites-enabled/ /etc/nginx/sites-available/ 2>/dev/null | head -n 1)
    if [ -n "$NGINX_CONF" ]; then
        echo "Found Nginx config: $NGINX_CONF"
        sed -i 's|proxy_pass http://localhost:3000;|proxy_pass http://127.0.0.1:3000;|g' "$NGINX_CONF"
        sed -i 's|proxy_pass http://localhost:3001;|proxy_pass http://127.0.0.1:3000;|g' "$NGINX_CONF"
        sed -i 's|proxy_pass http://127.0.0.1:3001;|proxy_pass http://127.0.0.1:3000;|g' "$NGINX_CONF"
        nginx -t && systemctl reload nginx
    fi
fi

echo ""
echo "======================================================================"
echo "🔒 3/4: Checking SSL Certificate for wa.bfsfin.cloud..."
echo "======================================================================"

if ! [ -d "/etc/letsencrypt/live/wa.bfsfin.cloud" ]; then
    echo "SSL Certificate for wa.bfsfin.cloud not found. Generating with Certbot..."
    if command -v certbot &> /dev/null; then
        certbot --nginx -d wa.bfsfin.cloud --non-interactive --agree-tos -m bfscaptain@gmail.com --redirect || true
        systemctl reload nginx 2>/dev/null || true
    fi
else
    echo "✅ SSL Certificate for wa.bfsfin.cloud already exists!"
fi

echo ""
echo "======================================================================"
echo "🧪 4/4: Performing End-to-End Health Checks..."
echo "======================================================================"

HTTP_LOCAL=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:3000/login || true)
HTTP_PUBLIC=$(curl -s -o /dev/null -w "%{http_code}" https://wa.bfsfin.cloud/login || true)

echo "Local Health  (http://127.0.0.1:3000/login)  : HTTP $HTTP_LOCAL"
echo "Public Domain (https://wa.bfsfin.cloud/login): HTTP $HTTP_PUBLIC"

if [ "$HTTP_PUBLIC" = "200" ] || [ "$HTTP_PUBLIC" = "307" ]; then
    echo ""
    echo "======================================================================"
    echo "🎉 SUCCESS: WhatsApp CRM is LIVE and 502 BAD GATEWAY IS RESOLVED!"
    echo "======================================================================"
    echo "👉 Direct URL: https://wa.bfsfin.cloud"
    echo "👉 CRM Hub:    https://crm.bfsfin.cloud/whatsapp_crm.php"
else
    echo ""
    echo "⚠️ Public check returned HTTP $HTTP_PUBLIC. Inspecting Nginx error log:"
    tail -n 25 /var/log/nginx/error.log
fi

} > "$LOG_FILE" 2>&1

chmod 644 "$LOG_FILE"
cat "$LOG_FILE"
