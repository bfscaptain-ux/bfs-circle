#!/bin/bash
# ==============================================================================
# BFS Circle Financial Services - Listmonk Production VPS Deployment Script
# ==============================================================================

set -e

echo "=========================================================="
echo "🚀 Installing & Deploying Listmonk Engine on Linux VPS..."
echo "=========================================================="

# 1. Update packages & install Docker if missing
if ! command -v docker &> /dev/null; then
    echo "📦 Installing Docker & Docker Compose plugin..."
    apt-get update -y
    apt-get install -y ca-certificates curl gnupg lsb-release
    mkdir -p /etc/apt/keyrings
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null
    apt-get update -y
    apt-get install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin
fi

# 2. Setup Listmonk Directory
LISTMONK_DIR="/opt/listmonk"
mkdir -p "$LISTMONK_DIR/uploads"
cd "$LISTMONK_DIR"

echo "📝 Creating docker-compose.yml for Listmonk & PostgreSQL 17..."
cat << 'EOF' > docker-compose.yml
services:
  app:
    image: listmonk/listmonk:latest
    container_name: listmonk_app
    restart: always
    ports:
      - "127.0.0.1:9000:9000"
    networks:
      - listmonk_net
    depends_on:
      db:
        condition: service_healthy
    command: [sh, -c, "./listmonk --install --idempotent --yes && ./listmonk --upgrade --yes && ./listmonk"]
    environment:
      LISTMONK_app__site_name: "BFS Circle Mailer"
      LISTMONK_app__address: 0.0.0.0:9000
      LISTMONK_db__user: listmonk
      LISTMONK_db__password: listmonk_vps_secure_db_pass
      LISTMONK_db__database: listmonk
      LISTMONK_db__host: db
      LISTMONK_db__port: 5432
      LISTMONK_db__ssl_mode: disable
      TZ: Asia/Kolkata
    volumes:
      - ./uploads:/listmonk/uploads:rw

  db:
    image: postgres:17-alpine
    container_name: listmonk_db
    restart: always
    environment:
      POSTGRES_USER: listmonk
      POSTGRES_PASSWORD: listmonk_vps_secure_db_pass
      POSTGRES_DB: listmonk
    networks:
      - listmonk_net
    volumes:
      - listmonk_pg_data:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U listmonk -d listmonk"]
      interval: 5s
      timeout: 5s
      retries: 5

networks:
  listmonk_net:
    driver: bridge

volumes:
  listmonk_pg_data:
EOF

echo "🚀 Starting Listmonk Docker Containers..."
docker compose down || true
docker compose up -d

echo "⏳ Waiting for Database & Schema to initialize (15s)..."
sleep 15

# 3. Create BFS CRM API User & Default Smart Lists
echo "🔑 Provisioning BFS CRM API User inside Listmonk Database..."
docker exec listmonk_db psql -U listmonk -d listmonk -c "
INSERT INTO users (username, password_login, password, email, name, type, user_role_id, status, twofa_type)
VALUES ('bfs_crm_api', false, '8f55c701a567490c2ef9ab1cd5f35684cc321bf762c3a12f7d191242cd9d6dd5', 'api@bfscircle.com', 'BFS CRM API Integration', 'api', 1, 'enabled', 'none')
ON CONFLICT (username) DO NOTHING;
"

# Restart app to cache new API user
docker restart listmonk_app
sleep 5

echo "=========================================================="
echo "✅ Listmonk Engine Successfully Deployed on VPS!"
echo "Port: 127.0.0.1:9000"
echo "API Username: bfs_crm_api"
echo "=========================================================="
echo ""
echo "👉 Next Step: Pull latest CRM code in /var/www/crm:"
echo "   cd /var/www/crm && git pull origin main"
echo ""
