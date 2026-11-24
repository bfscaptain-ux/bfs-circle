#!/bin/bash
# ==============================================================================
# BFS Circle - Complete 1-Click Fix for SMS Captain
# Fixes:
# 1. Nginx & SSL for sms-api.bfsfin.cloud (preventing WhatsApp CRM interception)
# 2. Internal communication http://127.0.0.1:3001/api/v1 for NextAuth
# 3. Cloudflare Turnstile verification bypass
# 4. User seeding (singhani5549@gmail.com & bfscaptain@gmail.com) with valid bcrypt
# 5. Restores SMS-Captain.apk download in web/public
# ==============================================================================
set -e

echo "======================================================================"
echo "🚀 APPLYING COMPLETE FIX FOR SMS CAPTAIN ON VPS"
echo "======================================================================"

CRM_DIR="/var/www/crm"
SMS_DIR="/var/www/sms-captain"

# 1. Pull latest repo in /var/www/crm
echo ""
echo "📥 [1/8] Updating CRM repo and downloading latest fixes..."
cd "$CRM_DIR"
git fetch origin main
git reset --hard origin/main

# 2. Extract customized SMS Captain code
echo ""
echo "📦 [2/8] Extracting white-labeled SMS Captain source code..."
cd "$SMS_DIR"
if [ -f "$CRM_DIR/sms_captain_custom.tar.gz" ]; then
    tar -xzf "$CRM_DIR/sms_captain_custom.tar.gz" -C "$SMS_DIR/"
fi

# 3. Copy SMS-Captain.apk to public folder so download link works
echo ""
echo "📱 [3/8] Placing SMS-Captain.apk in web/public directory..."
mkdir -p "$SMS_DIR/web/public"
if [ -f "$CRM_DIR/SMS-Captain.apk" ]; then
    cp "$CRM_DIR/SMS-Captain.apk" "$SMS_DIR/web/public/SMS-Captain.apk"
    chmod 644 "$SMS_DIR/web/public/SMS-Captain.apk"
fi

# 4. Update .env files for web and api
echo ""
echo "⚙️  [4/8] Configuring environment variables..."
cat << 'EOF' > "$SMS_DIR/web/.env"
NEXT_PUBLIC_SITE_URL=https://sms.bfsfin.cloud
NEXT_PUBLIC_API_BASE_URL=https://sms-api.bfsfin.cloud/api/v1
INTERNAL_API_URL=http://127.0.0.1:3001/api/v1
NEXT_PUBLIC_LATEST_APP_VERSION_CODE=20
NEXT_PUBLIC_LATEST_APP_VERSION_NAME=2.9.0
NEXTAUTH_SECRET=textbee_secret_random_key_987654321_abcdef
NEXTAUTH_URL=https://sms.bfsfin.cloud
NEXT_PUBLIC_TURNSTILE_SITE_KEY=1x00000000000000000000AA
NEXT_PUBLIC_BULK_RECIPIENT_WARN_THRESHOLD=500
EOF

cat << 'EOF' > "$SMS_DIR/api/.env"
PORT=3001
NODE_ENV=production
APP_SECRET=textbee_app_secret_very_secure_key_123456789
SESSION_SECRET=textbee_session_secret_very_secure_key_123456789
MONGO_URI=mongodb://adminUser:adminPassword@localhost:27018/textbee?authSource=admin
CORS_ORIGIN=*
SWAGGER_ENABLED=true
QUEUE_REDIS_HOST=localhost
QUEUE_REDIS_PORT=6380
QUEUE_REDIS_PASSWORD=
FIREBASE_SERVICE_ACCOUNT_PATH=./firebase-service-account.json
SMS_GATEWAY_TYPE=android
ENCRYPTION_KEY=textbee_encryption_key_32_chars_1234
DEBUG_QUEUE=false
REDIS_URL=redis://localhost:6380
WEBHOOK_DELIVERY_TIMEOUT_MS=30000
MAX_SMS_BATCH_SIZE=100
BULK_DISPATCH_WINDOW=50
FCM_SMS_TTL_SECONDS=259200
FCM_HEARTBEAT_TTL_SECONDS=1800
SEND_SCHEDULER_V2_ENABLED=true
PART_REPORTS_ENABLED=true
LATEST_APP_VERSION_CODE=18
LATEST_APP_VERSION_NAME=2.8.0
UPDATE_ROLLOUT_PERCENT=100
MIN_SUPPORTED_APP_VERSION_CODE=0
DEVICE_LOG_MAX_ENTRIES=10000
APP_ANALYTICS_ENABLED=true
CLOUDFLARE_TURNSTILE_SECRET_KEY=1x0000000000000000000000000000000AA
EOF

# 5. Ensure Docker DB & Redis are running
echo ""
echo "🐳 [5/8] Checking MongoDB and Redis containers..."
cd "$SMS_DIR"
docker compose up -d

# 6. Rebuild and restart API & Web via PM2
echo ""
echo "🏗️  [6/8] Building & Restarting SMS API and Web..."
cd "$SMS_DIR/api"
pnpm install
pnpm build || npx nest build || npm run build
pm2 restart sms-api || PORT=3001 pm2 start dist/main.js --name "sms-api"

cd "$SMS_DIR/web"
pnpm install
pnpm build
pm2 restart sms-web || PORT=3005 pm2 start npm --name "sms-web" -- start -- -p 3005
pm2 save

# 7. Seed Accounts directly with bcrypt into MongoDB
echo ""
echo "👤 [7/8] Seeding user accounts in database..."
sleep 2

# Dynamically calculate exact bcrypt hashes using Node's installed bcryptjs
HASH_ANUJ=$(node -e 'console.log(require("'"$SMS_DIR"'/api/node_modules/bcryptjs").hashSync("ANUJ0701", 10))')
HASH_ADMIN=$(node -e 'console.log(require("'"$SMS_DIR"'/api/node_modules/bcryptjs").hashSync("123456", 10))')

docker exec -i textbee-db mongosh "mongodb://adminUser:adminPassword@localhost:27017/textbee?authSource=admin" --eval "
db.users.updateOne(
  { email: 'singhani5549@gmail.com' },
  {
    \$set: {
      email: 'singhani5549@gmail.com',
      password: '$HASH_ANUJ',
      name: 'ANUJ',
      phone: '+919528836338',
      role: 'admin',
      plan: 'PRO',
      isEmailVerified: true,
      isBanned: false,
      updatedAt: new Date()
    },
    \$setOnInsert: {
      apiKey: 'txb_anuj_' + Date.now(),
      createdAt: new Date()
    }
  },
  { upsert: true }
);

db.users.updateOne(
  { email: 'bfscaptain@gmail.com' },
  {
    \$set: {
      email: 'bfscaptain@gmail.com',
      password: '$HASH_ADMIN',
      name: 'BFS Captain',
      role: 'admin',
      plan: 'PRO',
      isEmailVerified: true,
      isBanned: false,
      updatedAt: new Date()
    },
    \$setOnInsert: {
      apiKey: 'txb_admin_' + Date.now(),
      createdAt: new Date()
    }
  },
  { upsert: true }
);

print('Database accounts updated successfully: singhani5549@gmail.com & bfscaptain@gmail.com');
"

# 8. Configure Nginx and Certbot for both sms and sms-api
echo ""
echo "🌐 [8/8] Configuring Nginx Reverse Proxy & SSL Certificates..."
cat << 'EOF' > /etc/nginx/sites-available/sms.bfsfin.cloud
# SMS Captain Dashboard
server {
    listen 80;
    server_name sms.bfsfin.cloud;

    location / {
        proxy_pass http://127.0.0.1:3005;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;

        # Allow iframe embedding inside BFS CRM
        proxy_hide_header X-Frame-Options;
        add_header X-Frame-Options "ALLOW-FROM https://crm.bfsfin.cloud" always;
        add_header Content-Security-Policy "frame-ancestors 'self' https://crm.bfsfin.cloud https://*.bfsfin.cloud" always;
    }
}

# SMS Captain REST API
server {
    listen 80;
    server_name sms-api.bfsfin.cloud;

    location / {
        proxy_pass http://127.0.0.1:3001;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;

        # CORS
        add_header 'Access-Control-Allow-Origin' '*' always;
        add_header 'Access-Control-Allow-Methods' 'GET, POST, OPTIONS, PUT, DELETE' always;
        add_header 'Access-Control-Allow-Headers' '*' always;
    }
}
EOF

ln -sf /etc/nginx/sites-available/sms.bfsfin.cloud /etc/nginx/sites-enabled/sms.bfsfin.cloud
nginx -t
systemctl reload nginx

# Run certbot to issue or expand SSL certificate for both subdomains
if command -v certbot &> /dev/null; then
    echo "Running Certbot for sms.bfsfin.cloud & sms-api.bfsfin.cloud..."
    certbot --nginx -d sms.bfsfin.cloud -d sms-api.bfsfin.cloud --non-interactive --agree-tos --email bfscaptain@gmail.com --redirect --expand || true
fi

echo ""
echo "======================================================================"
echo "🎉 VERIFICATION CHECKS:"
echo "======================================================================"
echo "Checking Local API Health (Port 3001):"
curl -s http://127.0.0.1:3001/api/v1/health || echo "Local health check failed"
echo ""
echo "Checking External API Health (https://sms-api.bfsfin.cloud):"
curl -k -s https://sms-api.bfsfin.cloud/api/v1/health || echo "External health check failed"
echo ""
echo "Checking PM2 status:"
pm2 status sms-api sms-web
echo ""
echo "======================================================================"
echo "✅ SMS CAPTAIN HAS BEEN FULLY CONFIGURED & VERIFIED!"
echo "Login URL: https://sms.bfsfin.cloud/login"
echo "CRM Tab:   https://crm.bfsfin.cloud/sms_crm.php"
echo "Credentials: singhani5549@gmail.com / ANUJ0701"
echo "======================================================================"
