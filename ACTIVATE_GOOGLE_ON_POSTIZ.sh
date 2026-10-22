#!/bin/bash
# ==============================================================================
# BFS Circle - Activate Google Login, YouTube & Google My Business on Postiz
# Usage: bash ACTIVATE_GOOGLE_ON_POSTIZ.sh "CLIENT_ID" "CLIENT_SECRET"
# ==============================================================================

GOOGLE_ID="${1:-$GOOGLE_CLIENT_ID}"
GOOGLE_SECRET="${2:-$GOOGLE_CLIENT_SECRET}"

if [ -z "$GOOGLE_ID" ]; then
    read -p "Enter Google OAuth Client ID: " GOOGLE_ID
fi

if [ -z "$GOOGLE_SECRET" ]; then
    read -p "Enter Google OAuth Client Secret: " GOOGLE_SECRET
fi

if [ -z "$GOOGLE_ID" ] || [ -z "$GOOGLE_SECRET" ]; then
    echo "❌ Error: Both Client ID and Client Secret are required!"
    exit 1
fi

echo "======================================================================"
echo "🚀 STEP 1/2: Configuring Google OAuth Credentials in Postiz Stack..."
echo "======================================================================"

POSTIZ_DIR="/opt/postiz"
cd "$POSTIZ_DIR"

# Backup
cp docker-compose.yaml docker-compose.yaml.bak_google 2>/dev/null || true

# Inject YouTube & Google Login credentials into docker-compose.yaml
sed -i "s|YOUTUBE_CLIENT_ID:.*|YOUTUBE_CLIENT_ID: '${GOOGLE_ID}'|g" docker-compose.yaml
sed -i "s|YOUTUBE_CLIENT_SECRET:.*|YOUTUBE_CLIENT_SECRET: '${GOOGLE_SECRET}'|g" docker-compose.yaml

# Inject Google My Business credentials if not present
if ! grep -q "GOOGLE_GMB_CLIENT_ID" docker-compose.yaml; then
    sed -i "/YOUTUBE_CLIENT_SECRET:.*/a \      GOOGLE_GMB_CLIENT_ID: '${GOOGLE_ID}'\n      GOOGLE_GMB_CLIENT_SECRET: '${GOOGLE_SECRET}'\n      GMB_CLIENT_ID: '${GOOGLE_ID}'\n      GMB_CLIENT_SECRET: '${GOOGLE_SECRET}'" docker-compose.yaml
else
    sed -i "s|GOOGLE_GMB_CLIENT_ID:.*|GOOGLE_GMB_CLIENT_ID: '${GOOGLE_ID}'|g" docker-compose.yaml
    sed -i "s|GOOGLE_GMB_CLIENT_SECRET:.*|GOOGLE_GMB_CLIENT_SECRET: '${GOOGLE_SECRET}'|g" docker-compose.yaml
    sed -i "s|GMB_CLIENT_ID:.*|GMB_CLIENT_ID: '${GOOGLE_ID}'|g" docker-compose.yaml
    sed -i "s|GMB_CLIENT_SECRET:.*|GMB_CLIENT_SECRET: '${GOOGLE_SECRET}'|g" docker-compose.yaml
fi

# Also ensure production URLs are set
sed -i "s|MAIN_URL: 'http://localhost:4007'|MAIN_URL: 'https://social.bfsfin.cloud'|g" docker-compose.yaml
sed -i "s|FRONTEND_URL: 'http://localhost:4007'|FRONTEND_URL: 'https://social.bfsfin.cloud'|g" docker-compose.yaml
sed -i "s|NEXT_PUBLIC_BACKEND_URL: 'http://localhost:4007/api'|NEXT_PUBLIC_BACKEND_URL: 'https://social.bfsfin.cloud/api'|g" docker-compose.yaml

# Append to .env
cat << EOF >> "$POSTIZ_DIR/.env"
YOUTUBE_CLIENT_ID="${GOOGLE_ID}"
YOUTUBE_CLIENT_SECRET="${GOOGLE_SECRET}"
GOOGLE_GMB_CLIENT_ID="${GOOGLE_ID}"
GOOGLE_GMB_CLIENT_SECRET="${GOOGLE_SECRET}"
GMB_CLIENT_ID="${GOOGLE_ID}"
GMB_CLIENT_SECRET="${GOOGLE_SECRET}"
GOOGLE_CLIENT_ID="${GOOGLE_ID}"
GOOGLE_CLIENT_SECRET="${GOOGLE_SECRET}"
EOF

echo "✅ Google Client ID & Secret successfully applied!"

echo ""
echo "======================================================================"
echo "🔄 STEP 2/2: Restarting Postiz with New Google Integration..."
echo "======================================================================"

docker compose down
docker compose up -d

echo "Waiting 8 seconds for containers to initialize..."
sleep 8

echo ""
echo "======================================================================"
echo "🎉 GOOGLE INTEGRATION SUCCESSFULLY ACTIVATED!"
echo "Now Google Login, YouTube & Google My Business are fully LIVE on:"
echo "https://social.bfsfin.cloud"
echo "======================================================================"
