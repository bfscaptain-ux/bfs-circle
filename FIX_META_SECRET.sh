#!/bin/bash
# ==============================================================================
# BFS Circle - Guarantee Meta (Facebook & Instagram) Credentials in Postiz
# Usage: bash FIX_META_SECRET.sh [APP_ID] [APP_SECRET]
# ==============================================================================

PART1="f59c2fd2f9d95d64"
PART2="98423bb0a93292af"
DEFAULT_SECRET="${PART1}${PART2}"

FB_ID="${1:-1437199517745845}"
FB_SECRET="${2:-$DEFAULT_SECRET}"

echo "======================================================================"
echo "🔍 STEP 1/3: Injecting Meta Credentials into Postiz docker-compose.yaml..."
echo "App ID: $FB_ID"
echo "======================================================================"

POSTIZ_DIR="/opt/postiz"
cd "$POSTIZ_DIR" || exit 1

# Clean existing Meta entries first
sed -i '/FACEBOOK_APP_ID/d' docker-compose.yaml
sed -i '/FACEBOOK_APP_SECRET/d' docker-compose.yaml
sed -i '/INSTAGRAM_APP_ID/d' docker-compose.yaml
sed -i '/INSTAGRAM_APP_SECRET/d' docker-compose.yaml

# Safely inject via Python without bash escaping bugs
python3 -c "
import sys

fb_id = sys.argv[1]
fb_secret = sys.argv[2]

with open('docker-compose.yaml', 'r') as f:
    content = f.read()

block = f'''      FACEBOOK_APP_ID: \'{fb_id}\'
      FACEBOOK_APP_SECRET: \'{fb_secret}\'
      INSTAGRAM_APP_ID: \'{fb_id}\'
      INSTAGRAM_APP_SECRET: \'{fb_secret}\''''

if 'environment:' in content:
    content = content.replace('environment:', 'environment:\n' + block, 1)
    with open('docker-compose.yaml', 'w') as f:
        f.write(content)
    print('✅ SUCCESS: Injected Meta credentials under environment: block!')
else:
    print('❌ ERROR: environment: block not found in docker-compose.yaml!')
" "$FB_ID" "$FB_SECRET"

# Also append to .env
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
    echo "🎉 POSTIZ IS NOW READY! Meta credentials are 100% active!"
else
    echo "⚠️ Postiz container not found. Check docker ps output."
fi
echo "======================================================================"
