#!/bin/bash
# ==============================================================================
# BFS Circle - Activate Dedicated Instagram Credentials on Postiz
# ==============================================================================

IG_ID="1095493249921061"
IG_SECRET="38f1fbb644d2c7d0c8c9863c9732b80c"

echo "======================================================================"
echo "🚀 Configuring Dedicated Instagram Credentials..."
echo "Instagram App ID    : $IG_ID"
echo "Instagram App Secret: $IG_SECRET"
echo "======================================================================"

POSTIZ_DIR="/opt/postiz"
cd "$POSTIZ_DIR" || exit 1

# Backup docker-compose.yaml
cp docker-compose.yaml docker-compose.yaml.bak_ig 2>/dev/null || true

# Update INSTAGRAM variables in docker-compose.yaml
sed -i "s|INSTAGRAM_APP_ID:.*|INSTAGRAM_APP_ID: '${IG_ID}'|g" docker-compose.yaml
sed -i "s|INSTAGRAM_APP_SECRET:.*|INSTAGRAM_APP_SECRET: '${IG_SECRET}'|g" docker-compose.yaml

# Also update in .env
sed -i '/INSTAGRAM_APP_/d' .env 2>/dev/null || true
cat << EOF >> .env
INSTAGRAM_APP_ID="${IG_ID}"
INSTAGRAM_APP_SECRET="${IG_SECRET}"
EOF

echo ""
echo "======================================================================"
echo "🔄 Restarting Postiz Stack with Dedicated Instagram..."
echo "======================================================================"

docker compose down
docker compose up -d

echo "Waiting 8 seconds for containers to initialize..."
sleep 8

CONTAINER_ID=$(docker compose ps -q postiz 2>/dev/null || docker ps -q -f name=postiz | head -n 1)

echo ""
echo "======================================================================"
echo "🧪 Verifying Instagram Environment inside Postiz Container..."
echo "======================================================================"

docker exec "$CONTAINER_ID" env | grep -E "INSTAGRAM"

# Also copy instagram.provider.js for inspection
docker cp "$CONTAINER_ID:/app/apps/backend/dist/libraries/nestjs-libraries/src/integrations/social/instagram.provider.js" /var/www/crm/instagram_provider.js 2>/dev/null || true
chmod 644 /var/www/crm/instagram_provider.js 2>/dev/null || true

echo ""
echo "======================================================================"
echo "🎉 INSTAGRAM CREDENTIALS ARE NOW LIVE ON POSTIZ!"
echo "======================================================================"
