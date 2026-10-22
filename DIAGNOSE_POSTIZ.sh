#!/bin/bash
# ==============================================================================
# BFS Circle - Dump Postiz Container Logs & Meta Env for Instant Diagnosis
# ==============================================================================

echo "======================================================================"
echo "📋 Collecting Postiz Logs & Diagnostics..."
echo "======================================================================"

CONTAINER_ID=$(docker ps -q -f name=postiz | head -n 1)

if [ -z "$CONTAINER_ID" ]; then
    echo "❌ Postiz container is not running!"
    exit 1
fi

echo "Active Container ID: $CONTAINER_ID"

# Dump logs to web root
docker logs "$CONTAINER_ID" --tail 100 > /var/www/crm/postiz_log.txt 2>&1
docker exec "$CONTAINER_ID" env | grep -E "URL|FACEBOOK|INSTAGRAM|NODE|JWT" > /var/www/crm/postiz_env.txt 2>&1
chmod 644 /var/www/crm/postiz_log.txt /var/www/crm/postiz_env.txt 2>/dev/null || true

echo ""
echo "======================================================================"
echo "Recent Error / Auth Log Output (Last 30 lines):"
echo "======================================================================"
tail -n 30 /var/www/crm/postiz_log.txt
echo "======================================================================"
echo "✅ Diagnostic dump complete!"
