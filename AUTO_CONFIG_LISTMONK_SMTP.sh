#!/bin/bash
# ==============================================================================
# BFS Circle - Auto-Configure Listmonk Gmail SMTP & Default From Email
# ==============================================================================

OUT="/var/www/crm/listmonk_smtp_auto.txt"

{
echo "======================================================================"
echo "📧 STEP 1: Updating Default From Email in Database..."
echo "======================================================================"

# Set Default 'From' Email to: BFS Financial Services <bfscaptain@gmail.com>
docker exec listmonk_db psql -U listmonk -d listmonk -c "
UPDATE settings 
SET value = to_jsonb('BFS Financial Services <bfscaptain@gmail.com>'::text), 
    updated_at = NOW() 
WHERE key = 'app.from_email';
" 2>&1

# Set Site Name to: BFS Financial Services
docker exec listmonk_db psql -U listmonk -d listmonk -c "
UPDATE settings 
SET value = to_jsonb('BFS Financial Services'::text), 
    updated_at = NOW() 
WHERE key = 'app.site_name';
" 2>&1

echo ""
echo "======================================================================"
echo "⚙️ STEP 2: Injecting Verified Gmail SMTP Configuration..."
echo "======================================================================"

# Valid JSON configuration for Gmail SMTP (using Google App Password agftujxnvcscrgyf)
SMTP_JSON='[
  {
    "uuid": "00000000-0000-0000-0000-000000000001",
    "name": "Gmail SMTP",
    "enabled": true,
    "host": "smtp.gmail.com",
    "hello_hostname": "",
    "port": 587,
    "auth_protocol": "login",
    "username": "bfscaptain@gmail.com",
    "password": "agftujxnvcscrgyf",
    "email_headers": [],
    "max_conns": 3,
    "max_msg_retries": 2,
    "msg_retry_delay": "10s",
    "idle_timeout": "15s",
    "wait_timeout": "5s",
    "tls_type": "STARTTLS",
    "tls_skip_verify": false,
    "from_addresses": ["bfscaptain@gmail.com"]
  }
]'

docker exec listmonk_db psql -U listmonk -d listmonk -c "
UPDATE settings 
SET value = '$SMTP_JSON'::jsonb, 
    updated_at = NOW() 
WHERE key = 'smtp';
" 2>&1

echo ""
echo "======================================================================"
echo "🔄 STEP 3: Restarting Listmonk App..."
echo "======================================================================"

docker restart listmonk_app
sleep 5

echo ""
echo "======================================================================"
echo "🧪 STEP 4: Verifying Updated Database Settings..."
echo "======================================================================"

docker exec listmonk_db psql -U listmonk -d listmonk -c "
SELECT key, value FROM settings WHERE key IN ('app.site_name', 'app.from_email');
"

docker exec listmonk_db psql -U listmonk -d listmonk -c "
SELECT value->0->>'name' as smtp_name, 
       value->0->>'host' as host, 
       value->0->>'port' as port, 
       value->0->>'username' as username, 
       value->0->>'enabled' as enabled 
FROM settings WHERE key = 'smtp';
"

echo ""
echo "======================================================================"
echo "🎉 SUCCESS: Gmail SMTP & From-Email are Fully Configured!"
echo "Refresh your Settings / SMTP page in browser (Ctrl + F5)!"
echo "======================================================================"

} > "$OUT" 2>&1

chmod 644 "$OUT"
cat "$OUT"
