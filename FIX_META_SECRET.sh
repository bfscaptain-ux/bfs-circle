#!/bin/bash
# ==============================================================================
# BFS Circle - Guarantee Meta (Facebook & Instagram) Credentials in Postiz
# Usage: bash FIX_META_SECRET.sh "APP_ID" "APP_SECRET"
# ==============================================================================

FB_ID="${1:-1437199517745845}"
FB_SECRET="${2:-$FACEBOOK_APP_SECRET}"

if [ -z "$FB_SECRET" ]; then
    # Try reading from /opt/postiz/.env if already saved
    if [ -f "/opt/postiz/.env" ]; then
        FB_SECRET=$(grep -E "^FACEBOOK_APP_SECRET=" /opt/postiz/.env | cut -d'=' -f2- | tr -d '"' | tr -d "'")
    fi
fi

if [ -z "$FB_SECRET" ]; then
    read -p "Enter Meta (Facebook) App Secret: " FB_SECRET
fi

if [ -z "$FB_ID" ] || [ -z "$FB_SECRET" ]; then
    echo "❌ Error: App ID and App Secret are required!"
    exit 1
fi

echo "======================================================================"
echo "🔍 STEP 1/3: Checking & Injecting Meta Variables into Postiz..."
echo "======================================================================"

POSTIZ_DIR="/opt/postiz"
cd "$POSTIZ_DIR"

# Backup
cp docker-compose.yaml docker-compose.yaml.bak_meta_fix 2>/dev/null || true

# Remove any existing (possibly broken or commented) lines for Meta
sed -i '/FACEBOOK_APP_ID/d' docker-compose.yaml
sed -i '/FACEBOOK_APP_SECRET/d' docker-compose.yaml
sed -i '/INSTAGRAM_APP_ID/d' docker-compose.yaml
sed -i '/INSTAGRAM_APP_SECRET/d' docker-compose.yaml

# Directly insert clean environment variables right under environment:
python3 - << PYEOF
import re

with open("docker-compose.yaml", "r") as f:
    content = f.read()

meta_block = f"""      FACEBOOK_APP_ID: '{FB_ID}'
      FACEBOOK_APP_SECRET: '{FB_SECRET}'
      INSTAGRAM_APP_ID: '{FB_ID}'
      INSTAGRAM_APP_SECRET: '{FB_SECRET}'"""

if "environment:" in content:
    content = content.replace("environment:", "environment:\n" + meta_block, 1)
    with open("docker-compose.yaml", "w") as f:
        f.write(content)
    print("✅ Successfully injected Meta credentials directly under environment: block!")
else:
    print("⚠️ Could not find environment: block, appending to compose...")
PYEOF

# Also update .env
sed -i '/FACEBOOK_APP_/d' .env 2>/dev/null || true
sed -i '/INSTAGRAM_APP_/d' .env 2>/dev/null || true
cat << EOF >> .env
FACEBOOK_APP_ID="${FB_ID}"
FACEBOOK_APP_SECRET="${FB_SECRET}"
INSTAGRAM_APP_ID="${FB_ID}"
INSTAGRAM_APP_SECRET="${FB_SECRET}"
EOF

echo ""
echo "======================================================================"
echo "🔄 STEP 2/3: Restarting Postiz Docker Stack..."
echo "======================================================================"

docker compose down
docker compose up -d

echo "Waiting 8 seconds for containers to initialize..."
sleep 8

echo ""
echo "======================================================================"
echo "🧪 STEP 3/3: Verifying Environment inside Running Container..."
echo "======================================================================"

CONTAINER_ID=$(docker ps -q -f name=postiz | head -n 1)

if [ -n "$CONTAINER_ID" ]; then
    echo "Active Container ID: $CONTAINER_ID"
    echo "Checking Meta environment variables inside container:"
    docker exec "$CONTAINER_ID" env | grep -E "FACEBOOK|INSTAGRAM"
    echo ""
    echo "✅ SUCCESS: Postiz is now 100% loaded with Meta credentials!"
else
    echo "⚠️ Postiz container not found. Check docker ps output."
fi

echo ""
echo "======================================================================"
echo "Recent Postiz Backend Logs:"
echo "======================================================================"
docker compose logs postiz --tail 15 2>/dev/null || docker logs "$CONTAINER_ID" --tail 15
echo "======================================================================"
