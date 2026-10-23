#!/bin/bash
# ==============================================================================
# BFS Circle - Set Listmonk Password for 'admin' & 'listmonk' to 'admin123'
# ==============================================================================

OUT="/var/www/crm/admin_pass_set.txt"

{
echo "======================================================================"
echo "🔑 Updating Password for Admin Users in Listmonk Database..."
echo "======================================================================"

# Generate BCrypt hash using PHP
HASH=$(php -r "echo password_hash('admin123', PASSWORD_BCRYPT);")
echo "Generated Hash: $HASH"

# 1. Update user 'admin' (id 2)
docker exec listmonk_db psql -U listmonk -d listmonk -c "
UPDATE users 
SET password = '$HASH', password_login = true, status = 'enabled' 
WHERE username = 'admin' OR id = 2;
"

# 2. Also create/update user 'listmonk' with type 'user'
docker exec listmonk_db psql -U listmonk -d listmonk -c "
INSERT INTO users (username, password_login, password, email, name, type, user_role_id, status, twofa_type)
VALUES ('listmonk', true, '$HASH', 'admin@bfscircle.com', 'Listmonk SuperAdmin', 'user', 1, 'enabled', 'none')
ON CONFLICT (username) DO UPDATE 
SET password = '$HASH', password_login = true, status = 'enabled';
"

# 3. Restart container to flush user authentication cache
echo "Restarting listmonk_app..."
docker restart listmonk_app
sleep 4

echo ""
echo "======================================================================"
echo "✅ Current Listmonk Users in Database:"
echo "======================================================================"
docker exec listmonk_db psql -U listmonk -d listmonk -c "
SELECT id, username, email, type, password_login, status FROM users;
"

echo ""
echo "======================================================================"
echo "🎉 SUCCESS: Both 'admin' and 'listmonk' are ACTIVE with password: admin123"
echo "URL: https://mailer.bfsfin.cloud/admin"
echo "======================================================================"

} > "$OUT" 2>&1

chmod 644 "$OUT"
cat "$OUT"
