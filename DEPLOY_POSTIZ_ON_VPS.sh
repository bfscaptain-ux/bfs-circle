#!/bin/bash
# ==============================================================================
# BFS Circle - Deploy Postiz Social Media Engine on Linux VPS
# ==============================================================================

set -e

echo "======================================================================"
echo "🚀 1/3: Installing Postiz via Official Docker Compose on VPS..."
echo "======================================================================"

POSTIZ_DIR="/opt/postiz"

if [ ! -d "$POSTIZ_DIR" ]; then
    echo "Cloning official Postiz Docker Compose stack to $POSTIZ_DIR..."
    git clone https://github.com/gitroomhq/postiz-docker-compose.git "$POSTIZ_DIR"
fi

cd "$POSTIZ_DIR"

# Configure production URLs and JWT secret in docker-compose.yaml
SECRET=$(openssl rand -hex 32)
sed -i "s|MAIN_URL: 'http://localhost:4007'|MAIN_URL: 'https://social.bfsfin.cloud'|g" docker-compose.yaml
sed -i "s|FRONTEND_URL: 'http://localhost:4007'|FRONTEND_URL: 'https://social.bfsfin.cloud'|g" docker-compose.yaml
sed -i "s|NEXT_PUBLIC_BACKEND_URL: 'http://localhost:4007/api'|NEXT_PUBLIC_BACKEND_URL: 'https://social.bfsfin.cloud/api'|g" docker-compose.yaml
sed -i "s|random string that is unique to every install - just type random characters here!|${SECRET}|g" docker-compose.yaml

cat << EOF > "$POSTIZ_DIR/.env"
MAIN_URL="https://social.bfsfin.cloud"
FRONTEND_URL="https://social.bfsfin.cloud"
NEXT_PUBLIC_BACKEND_URL="https://social.bfsfin.cloud/api"
JWT_SECRET="${SECRET}"
DISABLE_REGISTRATION=false
IS_GENERAL=true
STORAGE_PROVIDER=local
EOF

echo "Starting Postiz Docker containers..."
docker compose up -d

echo "======================================================================"
echo "🌐 2/3: Configuring Nginx Reverse Proxy for social.bfsfin.cloud..."
echo "======================================================================"

cat << 'EOF' > /etc/nginx/sites-available/social.bfsfin.cloud
server {
    listen 80;
    server_name social.bfsfin.cloud;

    client_max_body_size 100M;

    location / {
        proxy_pass http://127.0.0.1:4007;
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

ln -sf /etc/nginx/sites-available/social.bfsfin.cloud /etc/nginx/sites-enabled/
nginx -t
systemctl reload nginx

echo "======================================================================"
echo "🔒 3/3: Generating Free SSL Certificate for social.bfsfin.cloud..."
echo "======================================================================"
if command -v certbot &> /dev/null; then
    certbot --nginx -d social.bfsfin.cloud --non-interactive --agree-tos -m bfscaptain@gmail.com --redirect || true
    systemctl reload nginx
fi

echo ""
echo "======================================================================"
echo "✅ POSTIZ SOCIAL MEDIA HUB SUCCESSFULLY DEPLOYED!"
echo "URL : https://social.bfsfin.cloud"
echo "Port: 4007 (Docker container)"
echo "======================================================================"
