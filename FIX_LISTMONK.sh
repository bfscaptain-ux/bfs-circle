#!/bin/bash
# ==============================================================================
# BFS Circle - Listmonk Production Fix & Optimization Script
# ==============================================================================

LOG_FILE="/var/www/crm/listmonk_fix.txt"

{
echo "======================================================================"
echo "📧 1/4: Inspecting Listmonk Docker Containers..."
echo "======================================================================"

cd /opt/listmonk 2>/dev/null || true
docker ps --filter "name=listmonk"

echo ""
echo "======================================================================"
echo "🔧 2/4: Updating Listmonk Configuration (Root URL & Frame Permissions)..."
echo "======================================================================"

if [ -f "/opt/listmonk/docker-compose.yml" ]; then
    echo "Updating /opt/listmonk/docker-compose.yml with public root URL..."
    # Ensure LISTMONK_app__root_url is present
    if ! grep -q "LISTMONK_app__root_url" /opt/listmonk/docker-compose.yml; then
        sed -i '/LISTMONK_app__address: 0.0.0.0:9000/a \      LISTMONK_app__root_url: "https://mailer.bfsfin.cloud"' /opt/listmonk/docker-compose.yml
    fi
    docker compose up -d
fi

echo ""
echo "======================================================================"
echo "🌐 3/4: Updating Nginx Reverse Proxy for mailer.bfsfin.cloud..."
echo "======================================================================"

cat << 'EOF' > /etc/nginx/sites-available/mailer.bfsfin.cloud
server {
    server_name mailer.bfsfin.cloud;

    client_max_body_size 100M;

    location / {
        proxy_pass http://127.0.0.1:9000;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto https;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";

        # Allow iframe embedding inside BFS CRM Hub
        proxy_hide_header X-Frame-Options;
        add_header X-Frame-Options "ALLOW-FROM https://crm.bfsfin.cloud" always;
        add_header Content-Security-Policy "frame-ancestors 'self' https://crm.bfsfin.cloud https://*.bfsfin.cloud;" always;

        proxy_buffer_size 128k;
        proxy_buffers 4 256k;
        proxy_busy_buffers_size 256k;
    }

    listen 443 ssl http2;
    ssl_certificate /etc/letsencrypt/live/mailer.bfsfin.cloud/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/mailer.bfsfin.cloud/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;
}

server {
    listen 80;
    server_name mailer.bfsfin.cloud;
    return 301 https://$host$request_uri;
}
EOF

ln -sf /etc/nginx/sites-available/mailer.bfsfin.cloud /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx

echo ""
echo "======================================================================"
echo "🔑 4/4: Listing Listmonk Admin Users & Passwords in Database..."
echo "======================================================================"

if docker ps | grep -q "listmonk_db"; then
    echo "Current Listmonk Registered Users:"
    docker exec listmonk_db psql -U listmonk -d listmonk -c "
    SELECT id, username, email, type, password_login, status FROM users;
    " || true
fi

echo ""
echo "======================================================================"
echo "🧪 Testing Listmonk Public Endpoint..."
echo "======================================================================"

HTTP_PUBLIC=$(curl -s -o /dev/null -w "%{http_code}" https://mailer.bfsfin.cloud/admin || true)
echo "Public Status (https://mailer.bfsfin.cloud/admin): HTTP $HTTP_PUBLIC"

} > "$LOG_FILE" 2>&1

chmod 644 "$LOG_FILE"
cat "$LOG_FILE"
