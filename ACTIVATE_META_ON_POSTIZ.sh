#!/bin/bash
# ==============================================================================
# BFS Circle - Activate Facebook & Instagram (Meta) on Postiz
# Usage: bash ACTIVATE_META_ON_POSTIZ.sh "APP_ID" "APP_SECRET"
# ==============================================================================

FB_ID="${1:-$FACEBOOK_APP_ID}"
FB_SECRET="${2:-$FACEBOOK_APP_SECRET}"

if [ -z "$FB_ID" ]; then
    read -p "Enter Meta (Facebook) App ID: " FB_ID
fi

if [ -z "$FB_SECRET" ]; then
    read -p "Enter Meta (Facebook) App Secret: " FB_SECRET
fi

if [ -z "$FB_ID" ] || [ -z "$FB_SECRET" ]; then
    echo "❌ Error: Both Meta App ID and App Secret are required!"
    exit 1
fi

echo "======================================================================"
echo "🚀 STEP 1/2: Configuring Meta (Facebook & Instagram) Credentials..."
echo "======================================================================"

POSTIZ_DIR="/opt/postiz"
cd "$POSTIZ_DIR"

# Backup
cp docker-compose.yaml docker-compose.yaml.bak_meta 2>/dev/null || true

# Inject Facebook & Instagram credentials into docker-compose.yaml
sed -i "s|FACEBOOK_APP_ID:.*|FACEBOOK_APP_ID: '${FB_ID}'|g" docker-compose.yaml
sed -i "s|FACEBOOK_APP_SECRET:.*|FACEBOOK_APP_SECRET: '${FB_SECRET}'|g" docker-compose.yaml

# Also add INSTAGRAM_APP_ID if not present
if ! grep -q "INSTAGRAM_APP_ID" docker-compose.yaml; then
    sed -i "/FACEBOOK_APP_SECRET:.*/a \      INSTAGRAM_APP_ID: '${FB_ID}'\n      INSTAGRAM_APP_SECRET: '${FB_SECRET}'" docker-compose.yaml
else
    sed -i "s|INSTAGRAM_APP_ID:.*|INSTAGRAM_APP_ID: '${FB_ID}'|g" docker-compose.yaml
    sed -i "s|INSTAGRAM_APP_SECRET:.*|INSTAGRAM_APP_SECRET: '${FB_SECRET}'|g" docker-compose.yaml
fi

# Append to .env
cat << EOF >> "$POSTIZ_DIR/.env"
FACEBOOK_APP_ID="${FB_ID}"
FACEBOOK_APP_SECRET="${FB_SECRET}"
INSTAGRAM_APP_ID="${FB_ID}"
INSTAGRAM_APP_SECRET="${FB_SECRET}"
EOF

echo "✅ Meta App ID & Secret successfully applied!"

echo ""
echo "======================================================================"
echo "🔄 STEP 2/2: Restarting Postiz Stack with Meta Integration..."
echo "======================================================================"

docker compose down
docker compose up -d

echo "Waiting 8 seconds for containers to initialize..."
sleep 8

echo ""
echo "======================================================================"
echo "🎉 META INTEGRATION SUCCESSFULLY ACTIVATED!"
echo "Now Instagram and Facebook Pages can be connected seamlessly on:"
echo "https://social.bfsfin.cloud"
echo "======================================================================"
