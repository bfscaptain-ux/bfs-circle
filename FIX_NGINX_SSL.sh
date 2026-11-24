#!/bin/bash
set -e

echo "Configuring clean Nginx SSL blocks for sms.bfsfin.cloud & sms-api.bfsfin.cloud..."

# Obtain separate cert for sms-api if needed
certbot certonly --nginx -d sms-api.bfsfin.cloud --non-interactive --agree-tos --email bfscaptain@gmail.com || true

# Determine cert paths
CERT_WEB="/etc/letsencrypt/live/sms.bfsfin.cloud/fullchain.pem"
KEY_WEB="/etc/letsencrypt/live/sms.bfsfin.cloud/privkey.pem"

CERT_API="/etc/letsencrypt/live/sms-api.bfsfin.cloud/fullchain.pem"
KEY_API="/etc/letsencrypt/live/sms-api.bfsfin.cloud/privkey.pem"

if [ ! -f "$CERT_API" ]; then
    CERT_API="$CERT_WEB"
    KEY_API="$KEY_WEB"
fi

cat << EOF > /etc/nginx/sites-available/sms.bfsfin.cloud
# SMS Captain Web Dashboard
server {
    listen 80;
    server_name sms.bfsfin.cloud;
    return 301 https://\$host\$request_uri;
}

server {
    listen 443 ssl;
    server_name sms.bfsfin.cloud;
    ssl_certificate $CERT_WEB;
    ssl_certificate_key $KEY_WEB;

    location / {
        proxy_pass http://127.0.0.1:3005;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host \$host;
        proxy_cache_bypass \$http_upgrade;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;

        proxy_hide_header X-Frame-Options;
        add_header X-Frame-Options "ALLOW-FROM https://crm.bfsfin.cloud" always;
        add_header Content-Security-Policy "frame-ancestors 'self' https://crm.bfsfin.cloud https://*.bfsfin.cloud" always;
    }
}

# SMS Captain REST API
server {
    listen 80;
    server_name sms-api.bfsfin.cloud;
    return 301 https://\$host\$request_uri;
}

server {
    listen 443 ssl;
    server_name sms-api.bfsfin.cloud;
    ssl_certificate $CERT_API;
    ssl_certificate_key $KEY_API;

    location / {
        proxy_pass http://127.0.0.1:3001;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host \$host;
        proxy_cache_bypass \$http_upgrade;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;

        add_header 'Access-Control-Allow-Origin' '*' always;
        add_header 'Access-Control-Allow-Methods' 'GET, POST, OPTIONS, PUT, DELETE' always;
        add_header 'Access-Control-Allow-Headers' '*' always;
    }
}
EOF

ln -sf /etc/nginx/sites-available/sms.bfsfin.cloud /etc/nginx/sites-enabled/sms.bfsfin.cloud
nginx -t
systemctl reload nginx

echo ""
echo "Testing sms-api endpoint now:"
curl -k -i https://sms-api.bfsfin.cloud/api/v1/health
echo ""
echo "DONE! Nginx SSL is now 100% active for sms-api.bfsfin.cloud!"
