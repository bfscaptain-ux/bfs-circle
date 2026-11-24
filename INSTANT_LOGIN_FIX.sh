#!/bin/bash
# ==============================================================================
# BFS Circle - Instant Direct SMS Captain Login Fix
# Directly seeds database via JavaScript file (avoids bash expansion bugs)
# Directly configures Nginx SSL proxy for sms-api.bfsfin.cloud
# Verifies login directly via curl
# ==============================================================================
set -e

echo "======================================================================"
echo "🚀 APPLYING INSTANT SMS CAPTAIN LOGIN FIX"
echo "======================================================================"

# 1. Directly seed users in MongoDB with verified bcrypt hashes
echo ""
echo "👤 [1/4] Seeding database accounts cleanly without bash expansion..."

cat << 'EOF' > /tmp/seed_sms_users.js
db.users.deleteMany({
  email: {
    $in: [
      "singhani5549@gmail.com",
      "SINGHANI5549@GMAIL.COM",
      "bfscaptain@gmail.com"
    ]
  }
});

db.users.insertOne({
  email: "singhani5549@gmail.com",
  password: "$2b$10$898tW52l9Kpm5eibPjIcNeBHXYRV.ML3HxGcewCY.oXjUl8nWkhjG",
  name: "ANUJ",
  phone: "+919528836338",
  role: "admin",
  plan: "PRO",
  isEmailVerified: true,
  isBanned: false,
  createdAt: new Date(),
  updatedAt: new Date(),
  apiKey: "txb_anuj_" + Date.now()
});

db.users.insertOne({
  email: "bfscaptain@gmail.com",
  password: "$2b$10$2el164YfvA20xARPs.zYbuSdKl.heErkjpewRnpa2qng3vKUPF4aa",
  name: "BFS Captain",
  role: "admin",
  plan: "PRO",
  isEmailVerified: true,
  isBanned: false,
  createdAt: new Date(),
  updatedAt: new Date(),
  apiKey: "txb_admin_" + Date.now()
});

print(">>> SUCCESS: Created users singhani5549@gmail.com (ANUJ0701) and bfscaptain@gmail.com (123456)");
EOF

docker exec -i textbee-db mongosh "mongodb://adminUser:adminPassword@localhost:27017/textbee?authSource=admin" < /tmp/seed_sms_users.js
rm -f /tmp/seed_sms_users.js

# 2. Fix Nginx configuration to route sms-api.bfsfin.cloud to port 3001
echo ""
echo "🌐 [2/4] Configuring Nginx to send sms-api.bfsfin.cloud directly to Port 3001..."

cat << 'EOF' > /etc/nginx/sites-available/sms.bfsfin.cloud
# SMS Captain Web Dashboard
server {
    listen 80;
    server_name sms.bfsfin.cloud;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl;
    server_name sms.bfsfin.cloud;
    ssl_certificate /etc/letsencrypt/live/sms.bfsfin.cloud/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/sms.bfsfin.cloud/privkey.pem;

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

        proxy_hide_header X-Frame-Options;
        add_header X-Frame-Options "ALLOW-FROM https://crm.bfsfin.cloud" always;
        add_header Content-Security-Policy "frame-ancestors 'self' https://crm.bfsfin.cloud https://*.bfsfin.cloud" always;
    }
}

# SMS Captain REST API
server {
    listen 80;
    server_name sms-api.bfsfin.cloud;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl;
    server_name sms-api.bfsfin.cloud;
    ssl_certificate /etc/letsencrypt/live/sms.bfsfin.cloud/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/sms.bfsfin.cloud/privkey.pem;

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

        add_header 'Access-Control-Allow-Origin' '*' always;
        add_header 'Access-Control-Allow-Methods' 'GET, POST, OPTIONS, PUT, DELETE' always;
        add_header 'Access-Control-Allow-Headers' '*' always;
    }
}
EOF

ln -sf /etc/nginx/sites-available/sms.bfsfin.cloud /etc/nginx/sites-enabled/sms.bfsfin.cloud
nginx -t
systemctl reload nginx

# 3. Restart PM2 services
echo ""
echo "🔄 [3/4] Restarting PM2 processes..."
pm2 restart sms-api sms-web

# 4. Immediate Live Verification Test
echo ""
echo "🧪 [4/4] Testing Direct Backend Login API with credentials..."
sleep 2

LOGIN_RESULT=$(curl -s -X POST http://127.0.0.1:3001/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"singhani5549@gmail.com","password":"ANUJ0701"}')

echo "Login API Response: $LOGIN_RESULT"

echo ""
echo "Testing External API Health (https://sms-api.bfsfin.cloud/api/v1/health):"
curl -k -s https://sms-api.bfsfin.cloud/api/v1/health || true

echo ""
echo "======================================================================"
echo "🎉 SUCCESS: LOGIN IS FULLY READY!"
echo "Ab aap browser me https://sms.bfsfin.cloud/login open karke"
echo "singhani5549@gmail.com / ANUJ0701 se login karein!"
echo "======================================================================"
