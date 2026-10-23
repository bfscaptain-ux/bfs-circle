#!/bin/bash
# ==============================================================================
# BFS Circle - Master WhatsApp CRM Fixer (Dedicated Port 3001)
# Port 3000 is 100% UNTOUCHED and PRESERVED for user's main website!
# ==============================================================================

LOG_FILE="/var/www/crm/wa_fix.txt"

{
echo "======================================================================"
echo "🛡️ SAFEGUARD CHECK: Preserving Port 3000 for your main website..."
echo "======================================================================"

# Check if port 3000 has a service running
SITE_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:3000/ || true)
echo "Port 3000 (Main Website) Status: HTTP $SITE_CODE (will NOT be touched)"

echo ""
echo "======================================================================"
echo "🚀 1/4: Launching WhatsApp Next.js Engine on Dedicated Port 3001..."
echo "======================================================================"

cd /var/www/crm/wa

# Clean ONLY WhatsApp process and Port 3001
pm2 delete bfs-wa 2>/dev/null || true
fuser -k 3001/tcp 2>/dev/null || true
sleep 1

# Start WhatsApp engine strictly on PORT 3001
PORT=3001 pm2 start npm --name "bfs-wa" --cwd /var/www/crm/wa -- start -- -p 3001
pm2 save

echo "Waiting for WhatsApp engine to boot on port 3001..."
for i in {1..12}; do
    LOCAL_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:3001/login || true)
    if [ "$LOCAL_CODE" = "200" ] || [ "$LOCAL_CODE" = "307" ]; then
        echo "✅ WhatsApp engine is ONLINE on port 3001 (HTTP $LOCAL_CODE)!"
        break
    fi
    sleep 1
done

echo ""
echo "======================================================================"
echo "🔧 2/4: Configuring Nginx Buffers & wa.bfsfin.cloud on Port 3001..."
echo "======================================================================"

# Add global buffer config in conf.d to prevent 502 header overflows cleanly
cat << 'EOF' > /etc/nginx/conf.d/wa_buffers.conf
# Buffer optimizations for Next.js and WebSocket apps
proxy_buffer_size 128k;
proxy_buffers 4 256k;
proxy_busy_buffers_size 256k;
EOF

# Ensure Nginx config for wa points to port 3001
for conf in /etc/nginx/sites-available/wa /etc/nginx/sites-available/wa.bfsfin.cloud; do
    if [ -f "$conf" ]; then
        echo "Checking proxy target in $conf..."
        sed -i 's|proxy_pass http://localhost:3000;|proxy_pass http://127.0.0.1:3001;|g' "$conf"
        sed -i 's|proxy_pass http://localhost:3001;|proxy_pass http://127.0.0.1:3001;|g' "$conf"
        sed -i 's|proxy_pass http://127.0.0.1:3000;|proxy_pass http://127.0.0.1:3001;|g' "$conf"
        # Ensure iframe embedding allowed
        if ! grep -q "proxy_hide_header X-Frame-Options;" "$conf"; then
            sed -i '/proxy_pass/a \        proxy_hide_header X-Frame-Options;' "$conf"
        fi
    fi
done

echo "Testing Nginx configuration syntax..."
nginx -t
if [ $? -eq 0 ]; then
    echo "Reloading Nginx..."
    systemctl reload nginx
    echo "✅ Nginx reloaded successfully!"
else
    echo "⚠️ Nginx test had an issue. Checking syntax details..."
fi

echo ""
echo "======================================================================"
echo "🧪 3/4: Health Verification..."
echo "======================================================================"

HTTP_LOCAL=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:3001/login || true)
HTTP_PUBLIC=$(curl -s -o /dev/null -w "%{http_code}" https://wa.bfsfin.cloud/login || true)
HTTP_WEBSITE=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:3000/ || true)

echo "Main Website   (Port 3000)               : HTTP $HTTP_WEBSITE"
echo "WhatsApp Local (http://127.0.0.1:3001)    : HTTP $HTTP_LOCAL"
echo "WhatsApp Public (https://wa.bfsfin.cloud) : HTTP $HTTP_PUBLIC"

if [ "$HTTP_PUBLIC" = "200" ] || [ "$HTTP_PUBLIC" = "307" ]; then
    echo ""
    echo "======================================================================"
    echo "🎉 SUCCESS: WhatsApp CRM is LIVE on PORT 3001!"
    echo "Port 3000 is 100% untouched for your main website."
    echo "======================================================================"
    echo "👉 Direct WhatsApp: https://wa.bfsfin.cloud"
    echo "👉 CRM Hub        : https://crm.bfsfin.cloud/whatsapp_crm.php"
else
    echo ""
    echo "⚠️ Public returned HTTP $HTTP_PUBLIC. Error log:"
    tail -n 20 /var/log/nginx/error.log
fi

} > "$LOG_FILE" 2>&1

chmod 644 "$LOG_FILE"
cat "$LOG_FILE"
