#!/bin/bash
# ==============================================================================
# BFS Circle - Super Fix & Full Status Check for Postiz Facebook Integration
# ==============================================================================

echo "======================================================================"
echo "🔍 STEP 1/4: Locating Postiz Container..."
echo "======================================================================"

CONTAINER_ID=$(docker compose -f /opt/postiz/docker-compose.yaml ps -q postiz 2>/dev/null)
if [ -z "$CONTAINER_ID" ]; then
    CONTAINER_ID=$(docker ps --format '{{.ID}} {{.Image}}' | grep -i 'postiz-app' | awk '{print $1}')
fi
if [ -z "$CONTAINER_ID" ]; then
    CONTAINER_ID=$(docker ps -q -f name=postiz | head -n 1)
fi

if [ -z "$CONTAINER_ID" ]; then
    echo "❌ Error: Postiz container not found!"
    exit 1
fi

echo "Found Postiz App Container: $CONTAINER_ID"

# Save existing logs
docker logs "$CONTAINER_ID" --tail 100 > /var/www/crm/postiz_log.txt 2>&1
chmod 644 /var/www/crm/postiz_log.txt 2>/dev/null || true

echo ""
echo "======================================================================"
echo "🔧 STEP 2/4: Patching Facebook Provider in Postiz Container..."
echo "======================================================================"

# Copy current provider out
docker cp "$CONTAINER_ID:/app/apps/backend/dist/libraries/nestjs-libraries/src/integrations/social/facebook.provider.js" /tmp/fb_provider.js

if grep -q "FB DEBUG AUTH" /tmp/fb_provider.js; then
    echo "✅ Provider is already patched with debug & safe error handling!"
else
    echo "Applying Python patch to /tmp/fb_provider.js..."
    python3 /var/www/crm/PATCH_POSTIZ_FACEBOOK.py /tmp/fb_provider.js
    if [ $? -ne 0 ]; then
        echo "❌ Python patch failed!"
        exit 1
    fi
    # Copy back into container
    docker cp /tmp/fb_provider.js "$CONTAINER_ID:/app/apps/backend/dist/libraries/nestjs-libraries/src/integrations/social/facebook.provider.js"
    echo "✅ Patched file successfully copied into container!"
fi

echo ""
echo "======================================================================"
echo "🔄 STEP 3/4: Restarting Postiz Container..."
echo "======================================================================"

docker restart "$CONTAINER_ID"

echo "Waiting 6 seconds for container to initialize..."
sleep 6

# Dump fresh logs
docker logs "$CONTAINER_ID" --tail 100 > /var/www/crm/postiz_log.txt 2>&1
chmod 644 /var/www/crm/postiz_log.txt 2>/dev/null || true

echo ""
echo "======================================================================"
echo "🎉 STEP 4/4: COMPLETE! INSTRUCTIONS FOR CONNECTING:"
echo "======================================================================"
echo "⚠️ BOHOT ZAROORI (DO NOT REFRESH THE ERROR PAGE):"
echo "Browser me jo 'Could not add provider' wala page hai, use F5 / Refresh MAT karein!"
echo "Kyunki Facebook ka OAuth code single-use hota hai."
echo ""
echo "Abhi yeh karein:"
echo "1. Browser me naye tab me https://social.bfsfin.cloud kholein."
echo "2. Add Channel -> Facebook Page par click karein."
echo "3. Facebook popup aayega aur seedha connect ho jayega!"
echo "======================================================================"
