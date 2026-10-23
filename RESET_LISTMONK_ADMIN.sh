#!/bin/bash
# ==============================================================================
# BFS Circle - Reset Listmonk Admin Password & Fix Localhost:9000 Redirects
# ==============================================================================

OUT="/var/www/crm/listmonk_reset.txt"

{
echo "======================================================================"
echo "🔍 STEP 1: Current Listmonk Users & Root URL in Database..."
echo "======================================================================"

echo "--- Current Users ---"
docker exec listmonk_db psql -U listmonk -d listmonk -c "SELECT id, username, email, type, password_login, status FROM users;" 2>&1

echo ""
echo "--- Current Settings for URLs ---"
docker exec listmonk_db psql -U listmonk -d listmonk -c "SELECT key, value FROM settings WHERE key IN ('app.root_url', 'app.site_name');" 2>&1

echo ""
echo "======================================================================"
echo "🔧 STEP 2: Updating Root URL to https://mailer.bfsfin.cloud..."
echo "======================================================================"

# Update root_url so links like 'Forgot Password' never point to localhost:9000
docker exec listmonk_db psql -U listmonk -d listmonk -c "
UPDATE settings 
SET value = '\"https://mailer.bfsfin.cloud\"'::jsonb 
WHERE key = 'app.root_url';
" 2>&1

echo ""
echo "======================================================================"
echo "🔑 STEP 3: Resetting Admin Password to 'admin123'..."
echo "======================================================================"

# Generate standard bcrypt hash using PHP
HASH=$(php -r "echo password_hash('admin123', PASSWORD_BCRYPT);")
echo "Generated BCrypt Hash: $HASH"

# Reset password for existing 'listmonk' user
docker exec listmonk_db psql -U listmonk -d listmonk -c "
UPDATE users 
SET password = '$HASH', password_login = true, status = 'enabled' 
WHERE username = 'listmonk';
" 2>&1

# Also ensure 'admin' user exists with same password
docker exec listmonk_db psql -U listmonk -d listmonk -c "
INSERT INTO users (username, password_login, password, email, name, type, user_role_id, status, twofa_type)
VALUES ('admin', true, '$HASH', 'admin@bfsfin.cloud', 'Administrator', 'admin', 1, 'enabled', 'none')
ON CONFLICT (username) DO UPDATE 
SET password = '$HASH', password_login = true, status = 'enabled';
" 2>&1

echo ""
echo "======================================================================"
echo "🔄 STEP 4: Restarting Listmonk App Container..."
echo "======================================================================"

docker restart listmonk_app
sleep 6

echo ""
echo "======================================================================"
echo "🧪 STEP 5: Verifying HTML Output (Checking for localhost:9000)..."
echo "======================================================================"

LOGIN_HTML=$(curl -s https://mailer.bfsfin.cloud/admin)
if echo "$LOGIN_HTML" | grep -q "localhost:9000"; then
    echo "⚠️ Warning: Found localhost:9000 still present in HTML"
else
    echo "✅ SUCCESS: All links now point to https://mailer.bfsfin.cloud!"
fi

echo ""
echo "======================================================================"
echo "🎉 LISTMONK LOGIN CREDENTIALS READY:"
echo "URL      : https://mailer.bfsfin.cloud/admin"
echo "Username : listmonk   (OR admin)"
echo "Password : admin123"
echo "======================================================================"

} > "$OUT" 2>&1

chmod 644 "$OUT"
cat "$OUT"
