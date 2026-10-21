#!/bin/bash
# ==============================================================================
# BFS Circle - Setup Nginx for Mailer & Restore WhatsApp Node Engine on VPS
# ==============================================================================

set -e

echo "======================================================================"
echo "🚀 1/3: Configuring Nginx Reverse Proxy for mailer.bfsfin.cloud..."
echo "======================================================================"

cat << 'EOF' > /etc/nginx/sites-available/mailer.bfsfin.cloud
server {
    listen 80;
    server_name mailer.bfsfin.cloud;

    client_max_body_size 50M;

    location / {
        proxy_pass http://127.0.0.1:9000;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
    }
}
EOF

ln -sf /etc/nginx/sites-available/mailer.bfsfin.cloud /etc/nginx/sites-enabled/
nginx -t
systemctl reload nginx

echo "======================================================================"
echo "🔒 2/3: Generating Free SSL Certificate for mailer.bfsfin.cloud..."
echo "======================================================================"
if command -v certbot &> /dev/null; then
    certbot --nginx -d mailer.bfsfin.cloud --non-interactive --agree-tos -m bfscaptain@gmail.com --redirect || true
    systemctl reload nginx
else
    echo "⚠️ Certbot not installed. Installing certbot & python3-certbot-nginx..."
    apt-get update -y
    apt-get install -y certbot python3-certbot-nginx
    certbot --nginx -d mailer.bfsfin.cloud --non-interactive --agree-tos -m bfscaptain@gmail.com --redirect || true
    systemctl reload nginx
fi

echo "======================================================================"
echo "⚡ 3/3: Fixing WhatsApp 502 Bad Gateway (Starting WhatsApp Engine)..."
echo "======================================================================"

cd /var/www/crm/wa

# Check if PM2 is installed
if command -v pm2 &> /dev/null; then
    echo "Restarting WhatsApp via PM2..."
    pm2 restart all || pm2 start npm --name "wa-crm" -- run dev
    pm2 save
else
    echo "Starting WhatsApp Node engine in background..."
    nohup npm run dev > /var/log/wa_engine.log 2>&1 &
fi

echo ""
echo "======================================================================"
echo "✅ SETUP COMPLETE!"
echo "1. Listmonk Mailer: https://mailer.bfsfin.cloud (Port 9000)"
echo "2. WhatsApp Engine : https://wa.bfsfin.cloud (Port 3000)"
echo "======================================================================"
