#!/bin/bash
CONTAINER_ID=$(docker ps -q -f name=postiz | head -n 1)
docker cp "$CONTAINER_ID:/app/apps/backend/dist/libraries/nestjs-libraries/src/integrations/social/facebook.provider.js" /var/www/crm/fb_provider.js
sed -n '240,285p' /var/www/crm/fb_provider.js > /var/www/crm/fb_provider_snippet.txt
chmod 644 /var/www/crm/fb_provider.js /var/www/crm/fb_provider_snippet.txt
echo "Copied facebook.provider.js. Snippet (lines 240-285):"
cat /var/www/crm/fb_provider_snippet.txt
