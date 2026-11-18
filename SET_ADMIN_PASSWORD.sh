#!/bin/bash
# ==============================================================================
# BFS Mailer - Instant Admin Password Reset Script
# Sets password for both 'admin' and 'bfscaptain@gmail.com'
# ==============================================================================

set -e

NEW_PASS="${1:-admin123}"

echo "======================================================================"
echo "🔑 Setting BFS Mailer Admin Password to: '$NEW_PASS'..."
echo "======================================================================"

docker exec listmonk_db psql -U listmonk -d listmonk -c "
CREATE EXTENSION IF NOT EXISTS pgcrypto;

-- 1. Update any existing users matching admin or bfscaptain
UPDATE users 
SET password = crypt('$NEW_PASS', gen_salt('bf', 10)), 
    password_login = true, 
    status = 'enabled' 
WHERE username IN ('admin', 'listmonk', 'bfscaptain@gmail.com') 
   OR email IN ('admin@bfsfin.cloud', 'bfscaptain@gmail.com');

-- 2. Ensure bfscaptain@gmail.com exists as Super Admin
INSERT INTO users (username, password_login, password, email, name, type, user_role_id, status, twofa_type)
VALUES ('bfscaptain@gmail.com', true, crypt('$NEW_PASS', gen_salt('bf', 10)), 'bfscaptain@gmail.com', 'BFS Captain Admin', 'admin', 1, 'enabled', 'none')
ON CONFLICT (username) DO UPDATE 
SET password = crypt('$NEW_PASS', gen_salt('bf', 10)), 
    password_login = true, 
    status = 'enabled';

-- 3. Ensure admin user exists as Super Admin
INSERT INTO users (username, password_login, password, email, name, type, user_role_id, status, twofa_type)
VALUES ('admin', true, crypt('$NEW_PASS', gen_salt('bf', 10)), 'admin@bfsfin.cloud', 'Administrator', 'admin', 1, 'enabled', 'none')
ON CONFLICT (username) DO UPDATE 
SET password = crypt('$NEW_PASS', gen_salt('bf', 10)), 
    password_login = true, 
    status = 'enabled';
"

echo "🔄 Restarting BFS Mailer App Container..."
docker restart listmonk_app
sleep 4

echo ""
echo "======================================================================"
echo "📋 Registered Users in Database:"
echo "======================================================================"
docker exec listmonk_db psql -U listmonk -d listmonk -c "
SELECT id, username, email, type, password_login, status FROM users;
"

echo ""
echo "======================================================================"
echo "🎉 PASSWORD SET SUCCESSFULLY!"
echo "Login URL : https://mailer.bfsfin.cloud/admin/login"
echo "Username  : bfscaptain@gmail.com   (OR: admin)"
echo "Password  : $NEW_PASS"
echo "======================================================================"
