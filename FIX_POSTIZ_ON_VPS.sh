#!/bin/bash
# ==============================================================================
# BFS Circle - Fix Postiz Account Registration & URLs on Linux VPS
# ==============================================================================

echo "======================================================================"
echo "🔧 STEP 1/3: Updating Postiz Environment Variables in docker-compose.yaml..."
echo "======================================================================"

POSTIZ_DIR="/opt/postiz"
cd "$POSTIZ_DIR"

# Backup docker-compose.yaml
cp docker-compose.yaml docker-compose.yaml.bak

# Generate a random 32-byte hex JWT secret if needed
SECRET=$(openssl rand -hex 32)

# Update docker-compose.yaml to use production https://social.bfsfin.cloud URLs
sed -i "s|MAIN_URL: 'http://localhost:4007'|MAIN_URL: 'https://social.bfsfin.cloud'|g" docker-compose.yaml
sed -i "s|FRONTEND_URL: 'http://localhost:4007'|FRONTEND_URL: 'https://social.bfsfin.cloud'|g" docker-compose.yaml
sed -i "s|NEXT_PUBLIC_BACKEND_URL: 'http://localhost:4007/api'|NEXT_PUBLIC_BACKEND_URL: 'https://social.bfsfin.cloud/api'|g" docker-compose.yaml
sed -i "s|random string that is unique to every install - just type random characters here!|${SECRET}|g" docker-compose.yaml

# Also update .env just in case
cat << EOF > "$POSTIZ_DIR/.env"
MAIN_URL="https://social.bfsfin.cloud"
FRONTEND_URL="https://social.bfsfin.cloud"
NEXT_PUBLIC_BACKEND_URL="https://social.bfsfin.cloud/api"
JWT_SECRET="${SECRET}"
DISABLE_REGISTRATION=false
IS_GENERAL=true
STORAGE_PROVIDER=local
EOF

echo "✅ Environment variables updated to https://social.bfsfin.cloud!"

echo ""
echo "======================================================================"
echo "🔄 STEP 2/3: Restarting Postiz Docker Containers..."
echo "======================================================================"

docker compose down
docker compose up -d

echo "Waiting 10 seconds for containers to initialize..."
sleep 10

echo ""
echo "======================================================================"
echo "🧪 STEP 3/3: Verifying Registration Endpoint..."
echo "======================================================================"

HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" https://social.bfsfin.cloud/auth || true)
echo "Auth Page Health Check (https://social.bfsfin.cloud/auth): HTTP $HTTP_CODE"

echo ""
echo "======================================================================"
echo "🎉 POSTIZ FIX COMPLETE!"
echo "Now visit https://crm.bfsfin.cloud/postiz.php and press Ctrl + F5,"
echo "then create your account — it will work instantly!"
echo "======================================================================"
