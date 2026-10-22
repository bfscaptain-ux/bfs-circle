#!/bin/bash
# ==============================================================================
# BFS Circle - Apply Robust Facebook Provider Patch to Postiz
# ==============================================================================

CONTAINER_ID=$(docker ps -q -f name=postiz | head -n 1)

if [ -z "$CONTAINER_ID" ]; then
    echo "[ERROR] Postiz container not found!"
    exit 1
fi

echo "Active Postiz Container: $CONTAINER_ID"

# 1. Copy out current compiled file
docker cp "$CONTAINER_ID:/app/apps/backend/dist/libraries/nestjs-libraries/src/integrations/social/facebook.provider.js" /tmp/fb_provider.js

# 2. Run Python patch
python3 /var/www/crm/PATCH_POSTIZ_FACEBOOK.py /tmp/fb_provider.js

if [ $? -ne 0 ]; then
    echo "[ERROR] Failed to patch /tmp/fb_provider.js"
    exit 1
fi

# 3. Copy back patched file
docker cp /tmp/fb_provider.js "$CONTAINER_ID:/app/apps/backend/dist/libraries/nestjs-libraries/src/integrations/social/facebook.provider.js"

echo "Patched file copied into container."

# 4. Reload PM2 process or restart
docker exec "$CONTAINER_ID" pm2 restart all 2>/dev/null || docker restart "$CONTAINER_ID"

echo "[SUCCESS] Postiz Facebook Provider is now patched and active!"
echo "Now try connecting Facebook in Postiz (https://social.bfsfin.cloud)."
