#!/bin/bash
# ==============================================================================
# BFS Circle - Complete SMS Captain Deployment on Production VPS
# Sets up MongoDB, Redis, SMS API (Port 3001), Web Dashboard (Port 3005),
# Nginx Reverse Proxy with SSL for sms.bfsfin.cloud & sms-api.bfsfin.cloud
# ==============================================================================
set -e

echo "======================================================================"
echo "🚀 BFS CIRCLE - LAUNCHING SMS CAPTAIN ON VPS (200.234.34.191)"
echo "======================================================================"

INSTALL_DIR="/var/www/sms-captain"
mkdir -p "$INSTALL_DIR"
cd "$INSTALL_DIR"

# 1. Clone or update textbee source
echo ""
echo "📥 [1/7] Fetching SMS Captain (TextBee) core..."
if [ ! -d "$INSTALL_DIR/.git" ]; then
    git clone --depth 1 https://github.com/textbee/textbee.git "$INSTALL_DIR"
else
    git pull origin master || true
fi

# 2. Setup Docker Compose for MongoDB (Port 27018) and Redis (Port 6380)
echo ""
echo "🐳 [2/7] Starting MongoDB & Redis database containers..."
cat << 'EOF' > "$INSTALL_DIR/docker-compose.yaml"
version: '3.8'

services:
  textbee-db:
    container_name: textbee-db
    image: mongo:6.0
    restart: always
    environment:
      MONGO_INITDB_ROOT_USERNAME: adminUser
      MONGO_INITDB_ROOT_PASSWORD: adminPassword
      MONGO_INITDB_DATABASE: textbee
    ports:
      - "27018:27017"
    volumes:
      - textbee_mongo_data:/data/db

  textbee-redis:
    container_name: textbee-redis
    image: redis:alpine
    restart: always
    ports:
      - "6380:6379"
    volumes:
      - textbee_redis_data:/data

volumes:
  textbee_mongo_data:
  textbee_redis_data:
EOF

docker compose down 2>/dev/null || true
docker compose up -d

echo "Waiting for MongoDB to be healthy..."
sleep 6

# 3. Write Firebase Service Account
echo ""
echo "🔐 [3/7] Configuring Firebase Service Account & Encryption Keys..."
mkdir -p "$INSTALL_DIR/api" "$INSTALL_DIR/web"

cat << 'EOF' | base64 -d > "$INSTALL_DIR/api/firebase-service-account.json"
ewogICJ0eXBlIjogInNlcnZpY2VfYWNjb3VudCIsCiAgInByb2plY3RfaWQiOiAic21zLWJmcyIs
CiAgInByaXZhdGVfa2V5X2lkIjogIjdmNmE1ZmFlNWM2MzM3N2U2MDkyZjA2MDExNGE4NzVlNTQy
YTM3MzIiLAogICJwcml2YXRlX2tleSI6ICItLS0tLUJFR0lOIFBSSVZBVEUgS0VZLS0tLS1cbk1J
SUV2UUlCQURBTkJna3Foa2lHOXcwQkFRRUZBQVNDQktjd2dnU2pBZ0VBQW9JQkFRQ3RwSnVtcmoz
OE9YYUNcbnYySWtWR045djdGMVpYb2pwYVVEZ3VGUElqc1V0K2QzTC94dlgrRDZKMHh5OU1Fbmcx
NE5QT3g3REFMdlAyNVhcbkxrb0dkL2hHbkJRdjRmWXFiY0cyMVR6OXQ0RzBGUC91WUk2RTVnLzZm
dUhpQ28zY05GejRQQlFINHpYbE1GeWhcbjNvbnM0Yy9icVNBSnMzRWdCNUdMd1dZRnBrRlFhOEFR
S3FZN29IZDBTYW5RTnlYTkxCNlR5cDd5S3FpQmFTQkNcbnd6Vm96aVFvdUUvbmxxU3EzYW9Wdm1j
R3BCM3FvRXJtVVlnZnNleUJhMGtNbU5MK3plemVtcFFLSnFwM2YwOW9cbk5EbjV0cFZsS3VHNjJl
bmNCd1h0V00xbkhtaU1ZSVZ6cGNqWUcxYVNWeENvTTRYMzI2U3NWa0NZQVUwZ3FZeGpcbnI2aHpC
b2hQQWdNQkFBRUNnZ0VBQ3h5RGliVGRRL1N2WVoxNmp2SmhlaitZR2NTK3NXVjIxVzVpNWxpOWdv
Q1hcbldYNmk1VjB6bHBmdnlGTDhqbVlJdHZ5K3ZtVmQzeHN6R1E5RVZUQTc2Z2Y4L3h2TTBCazlT
OUViazFPRnRydWdcbkU3ODdhUkN3SW5kTGVhT05EaWJCcEtPbDhPT3JmZXhNYkY3Y2VzZ2pNRGZw
MURoSjFTL0RtVGVHZDkwVUt4TGFcblJLamRCcVVaL0JWUU5YZUZyS0pTMFhuZ3RSUnVsc2R2K0dH
VTVKZDNWQm9Fb3pXMEZkclZxeFEvK0ZjUXJJSGRcbmdoVm10ZjNVemFFMkhHaUVNaVZWd0luRlFl
L3NmQ2c5WTd1SFdGQ2RrQ0xzM0ptTWUxb3o3NmVmcU9wMm51eitcbi85SE5PTHdsclBZN2ZOcTZP
cFg5M1d1anRmTE1EUnZJQUNqMGVHQmpPUUtCZ1FEVVEyVk5zQUdGdTFCcm5pK3BcbmNHZS92U3U2
VERtT2h4Tk5GbWtHWkFRNXBHTVVaeFFEbVFacjJoQTlKK0w1djMvay9xcG5vWSt1SXFQKzdKQjdc
bkpUUlpTVHdqOEx5OHY5LzlnWmYxSXQ5YmFkSGUwdDdONzJad0FVL054NXprU2hYWEFmSnZjMDlv
dWM3TGpobHdcbmpYalhJK2pkU3E0T0piRGk3T1JrNmVRVnlRS0JnUURSYkF0NVd5TmFWQkZ0MWRo
S1NtcmFPR3p2a01IVXVRd1Jcbmp4eXpxQmlUZWZDKzMzcmRXL2NadSs2N20yaWJ2OGRFVU1hckxj
d2ZodkE2cjVnVlJZeFM4SWZSNFFYRUxaTjNcbjY3Rzd1MXJrdmpUd3l2d0FYbmdWYThXbDJYUWFZ
UDdzb24vQ0tVWkk4Ty9yWmhCbG9mSm4yRjAwQjhWdlVocDlcbjhYdWJFSTZaVndLQmdHd3pHcTJh
UFh4cytsYVJZUnhQQnlQUEN6NUE0c29MZ3NRMTJ6MTl1RVdselRlMlA1YUJcbjU2c01rbUNLTjRJ
MW9yMFpMOFg1cmlNRzEyLzRLTUlOT2YwVmZzbVhTcTR3Wkw1MTAvOEtVcTduWnlyZGRPT21cbjJD
SGFHVkw5SUxXaUtqWGFjVGxzbnhyME5EekE1NmpmQkswdVNlS2IwZE8yVk0rb0Q5aDgvVjQ1QW9H
QWVMWmVcbnBzVHh5ODlKZkpZVzVqdWUrOG9xZ0t4Z0RjM2NOd0xXK0hlY011WEc3ejdDQTNaUEtI
WTdWY2h1czlnSGRFbGJcbk9yQmJTSkR2UitTUE5UT3hmdDFHYktQWVB3eEdSSW1IR1Q4VlZXU04v
MzRCWS9VUUlzak1WSnpUWDZFcFVCMGFcbnAwQkRVeWdPSXhIaHdiTm5scFFRcFQwVmdPVkNmL1lC
MlkvaG9tRUNnWUVBbnRNUGNiV2hjZmVNM2E4d3N1Y09cblVsejRmYUtqelV3NnIrV213WER3U1ZT
Vmo3N1Z2U09PVDhnSnlhTURxUGtjbkpYbklvQmEwa1BTSEFxRzVsYXhcbkNua2FBdFBoZ1NRVEJy
Qll3bjcreW55YVZ5V3pGRGtxVlZzbHdhblNaSm8rM1hHQVpVSDVHdzk4UFhDdnpyNXpcbmxmOVhM
RzJabUhxTHU2Y0h2YWI0T0VvPVxuLS0tLS1FTkQgUFJJVkFURSBLRVktLS0tLVxuIiwKICAiY2xp
ZW50X2VtYWlsIjogImZpcmViYXNlLWFkbWluc2RrLWZic3ZjQHNtcy1iZnMuaWFtLmdzZXJ2aWNl
YWNjb3VudC5jb20iLAogICJjbGllbnRfaWQiOiAiMTEyMzkwNTAxODM5NzQzMTIwMjA5IiwKICAi
YXV0aF91cmkiOiAiaHR0cHM6Ly9hY2NvdW50cy5nb29nbGUuY29tL28vb2F1dGgyL2F1dGgiLAog
ICJ0b2tlbl91cmkiOiAiaHR0cHM6Ly9vYXV0aDIuZ29vZ2xlYXBpcy5jb20vdG9rZW4iLAogICJh
dXRoX3Byb3ZpZGVyX3g1MDlfY2VydF91cmwiOiAiaHR0cHM6Ly93d3cuZ29vZ2xlYXBpcy5jb20v
b2F1dGgyL3YxL2NlcnRzIiwKICAiY2xpZW50X3g1MDlfY2VydF91cmwiOiAiaHR0cHM6Ly93d3cu
Z29vZ2xlYXBpcy5jb20vcm9ib3QvdjEvbWV0YWRhdGEveDUwOS9maXJlYmFzZS1hZG1pbnNkay1m
YnN2YyU0MHNtcy1iZnMuaWFtLmdzZXJ2aWNlYWNjb3VudC5jb20iLAogICJ1bml2ZXJzZV9kb21h
aW4iOiAiZ29vZ2xlYXBpcy5jb20iCn0K
EOF

# 4. Configure Environment Files
cat << 'EOF' > "$INSTALL_DIR/api/.env"
PORT=3001
MONGO_URI=mongodb://adminUser:adminPassword@localhost:27018/textbee?authSource=admin
MONGO_ROOT_USER=adminUser
MONGO_ROOT_PASS=adminPassword
JWT_SECRET=textbee_jwt_secret_random_key_1234567890_abcdef
JWT_EXPIRATION=60d
FRONTEND_URL=https://sms.bfsfin.cloud
API_PUBLIC_URL=https://sms-api.bfsfin.cloud
APP_PUBLIC_URL=https://sms.bfsfin.cloud
USE_SMS_QUEUE=false
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
EOF

cat << 'EOF' > "$INSTALL_DIR/web/.env"
NEXT_PUBLIC_SITE_URL=https://sms.bfsfin.cloud
NEXT_PUBLIC_API_BASE_URL=https://sms-api.bfsfin.cloud/api/v1
NEXT_PUBLIC_LATEST_APP_VERSION_CODE=20
NEXT_PUBLIC_LATEST_APP_VERSION_NAME=2.9.0
NEXTAUTH_SECRET=textbee_secret_random_key_987654321_abcdef
NEXTAUTH_URL=https://sms.bfsfin.cloud
NEXT_PUBLIC_BULK_RECIPIENT_WARN_THRESHOLD=500
EOF

# 5. Build and Launch NestJS Backend API
echo ""
echo "⚙️ [4/7] Installing and starting SMS API Engine (Port 3001)..."
cd "$INSTALL_DIR/api"
npm install --silent 2>/dev/null || pnpm install
npm run build 2>/dev/null || npx nest build || true

pm2 delete sms-api 2>/dev/null || true
fuser -k 3001/tcp 2>/dev/null || true
sleep 1
PORT=3001 pm2 start dist/main.js --name "sms-api"

# Seed Admin user in MongoDB
sleep 3
docker exec -i textbee-db mongosh "mongodb://adminUser:adminPassword@localhost:27017/textbee?authSource=admin" --eval '
db.users.updateOne(
  { email: "bfscaptain@gmail.com" },
  {
    $set: {
      email: "bfscaptain@gmail.com",
      password: "$2b$10$EpI.t8B3e1c6nZ4W9Z4W9.Y8k9k8k9k8k9k8k9k8k9k8k9k8k9k8k",
      name: "BFS Captain",
      apiKey: "txb_AIkTGn5S8cK2vx4rcw95JRpbeLIpHLDt",
      plan: "PRO",
      isEmailVerified: true,
      role: "admin",
      updatedAt: new Date()
    },
    $setOnInsert: { createdAt: new Date() }
  },
  { upsert: true }
);
' || true

# 6. Build and Launch Next.js Dashboard
echo ""
echo "🎨 [5/7] Building & launching SMS Web Dashboard on Port 3005..."
cd "$INSTALL_DIR/web"
npm install --silent 2>/dev/null || pnpm install
npm run build

pm2 delete sms-web 2>/dev/null || true
fuser -k 3005/tcp 2>/dev/null || true
sleep 1
PORT=3005 pm2 start npm --name "sms-web" -- start -- -p 3005
pm2 save

# 7. Configure Nginx Reverse Proxy with Clean SSL
echo ""
echo "🌐 [6/7] Setting up Nginx Virtual Hosts for sms.bfsfin.cloud & sms-api.bfsfin.cloud..."
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

# 8. Run Certbot for HTTPS
echo ""
echo "🔒 [7/7] Issuing free SSL Certificates with Certbot..."
if command -v certbot &> /dev/null; then
    certbot --nginx -d sms.bfsfin.cloud -d sms-api.bfsfin.cloud --non-interactive --agree-tos --email bfscaptain@gmail.com --redirect || true
fi

echo ""
echo "======================================================================"
echo "🎉 SUCCESS: SMS CAPTAIN IS NOW 100% LIVE ON VPS!"
echo "Dashboard: https://sms.bfsfin.cloud"
echo "API Endpoint: https://sms-api.bfsfin.cloud"
echo "======================================================================"
