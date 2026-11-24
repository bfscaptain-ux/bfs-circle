#!/bin/bash
set -e

echo "======================================================================"
echo "🚀 BFS CIRCLE - DEPLOY SMS CAPTAIN GATEWAY ON VPS (200.234.34.191)"
echo "======================================================================"

INSTALL_DIR="/var/www/sms-captain"
mkdir -p "$INSTALL_DIR"
cd "$INSTALL_DIR"

echo ""
echo "[1/6] Checking Docker & Docker Compose..."
if ! command -v docker &> /dev/null; then
    echo "Installing Docker..."
    curl -fsSL https://get.docker.com | sh
fi

echo ""
echo "[2/6] Starting MongoDB & Redis via Docker..."
# Ensure docker-compose.yaml is running
cat << 'EOF' > docker-compose.yaml
version: '3.8'

services:
  textbee-db:
    image: mongo:6.0
    container_name: textbee-db
    restart: always
    environment:
      MONGO_INITDB_ROOT_USERNAME: adminUser
      MONGO_INITDB_ROOT_PASSWORD: adminPassword
      MONGO_INITDB_DATABASE: textbee
    ports:
      - "27018:27017"
    volumes:
      - mongodb_data:/data/db

  textbee-redis:
    image: redis:7-alpine
    container_name: textbee-redis
    restart: always
    ports:
      - "6380:6379"
    volumes:
      - redis_data:/data

volumes:
  mongodb_data:
  redis_data:
EOF

docker compose up -d

echo ""
echo "[3/6] Setting up Node.js & pnpm..."
if ! command -v pnpm &> /dev/null; then
    npm install -g pnpm pm2
fi

echo ""
echo "[4/6] Installing API Dependencies & Starting Backend (Port 3001)..."
cd "$INSTALL_DIR/api"
pnpm install --prod=false
pm2 delete sms-api 2>/dev/null || true
PORT=3001 pm2 start dist/main.js --name "sms-api"

echo ""
echo "[5/6] Building & Starting Web Dashboard (Port 3005)..."
cd "$INSTALL_DIR/web"
pnpm install --prod=false
# Update environment for production domain
sed -i 's|http://localhost:3000|https://sms.bfsfin.cloud|g' .env 2>/dev/null || true
sed -i 's|http://localhost:3001/api/v1|https://sms-api.bfsfin.cloud/api/v1|g' .env 2>/dev/null || true
pnpm build
pm2 delete sms-web 2>/dev/null || true
PORT=3005 pm2 start npm --name "sms-web" -- start -- -p 3005

pm2 save

echo ""
echo "[6/6] Configuring Nginx Reverse Proxy for sms.bfsfin.cloud & sms-api.bfsfin.cloud..."
cat << 'EOF' > /etc/nginx/sites-available/sms.bfsfin.cloud
server {
    server_name sms.bfsfin.cloud;

    location / {
        proxy_pass http://127.0.0.1:3005;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}

server {
    server_name sms-api.bfsfin.cloud;

    location / {
        proxy_pass http://127.0.0.1:3001;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
EOF

ln -sf /etc/nginx/sites-available/sms.bfsfin.cloud /etc/nginx/sites-enabled/sms.bfsfin.cloud
nginx -t && systemctl reload nginx

# Issue SSL if certbot exists
if command -v certbot &> /dev/null; then
    certbot --nginx -d sms.bfsfin.cloud -d sms-api.bfsfin.cloud --non-interactive --agree-tos --email bfscaptain@gmail.com || true
fi

echo "======================================================================"
echo "🎉 SMS CAPTAIN SUCCESSFULLY DEPLOYED TO VPS!"
echo "Dashboard: https://sms.bfsfin.cloud"
echo "API Endpoint: https://sms-api.bfsfin.cloud"
echo "======================================================================"
