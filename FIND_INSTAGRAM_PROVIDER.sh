#!/bin/bash
CONTAINER_ID=$(docker compose -f /opt/postiz/docker-compose.yaml ps -q postiz 2>/dev/null || docker ps -q -f name=postiz | head -n 1)

echo "Searching for instagram files in container $CONTAINER_ID..."
docker exec "$CONTAINER_ID" grep -rn "instagram.com/oauth/authorize" /app/apps/backend/dist > /var/www/crm/ig_search.txt 2>&1
chmod 644 /var/www/crm/ig_search.txt

echo "Search results:"
cat /var/www/crm/ig_search.txt
