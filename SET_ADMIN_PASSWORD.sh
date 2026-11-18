#!/bin/bash
# ==============================================================================
# BFS Mailer - Universal Multi-Username Password Reset Script
# Sets password 'admin123' for: admin, listmonk, bfscaptain, bfscaptain@gmail.com
# ==============================================================================

set -e

NEW_PASS="${1:-admin123}"

echo "======================================================================"
echo "🔑 Provisioning All Admin Usernames with Password: '$NEW_PASS'..."
echo "======================================================================"

docker exec listmonk_db psql -U listmonk -d listmonk -c "
CREATE EXTENSION IF NOT EXISTS pgcrypto;

-- 1. admin
INSERT INTO users (username, password_login, password, email, name, type, user_role_id, status, twofa_type)
VALUES ('admin', true, crypt('$NEW_PASS', gen_salt('bf')), 'admin@bfsfin.cloud', 'BFS Admin', 'admin', 1, 'enabled', 'none')
ON CONFLICT (username) DO UPDATE 
SET password = crypt('$NEW_PASS', gen_salt('bf')), password_login = true, status = 'enabled';

-- 2. listmonk
INSERT INTO users (username, password_login, password, email, name, type, user_role_id, status, twofa_type)
VALUES ('listmonk', true, crypt('$NEW_PASS', gen_salt('bf')), 'listmonk@bfsfin.cloud', 'Listmonk Super Admin', 'admin', 1, 'enabled', 'none')
ON CONFLICT (username) DO UPDATE 
SET password = crypt('$NEW_PASS', gen_salt('bf')), password_login = true, status = 'enabled';

-- 3. bfscaptain@gmail.com
INSERT INTO users (username, password_login, password, email, name, type, user_role_id, status, twofa_type)
VALUES ('bfscaptain@gmail.com', true, crypt('$NEW_PASS', gen_salt('bf')), 'bfscaptain@gmail.com', 'BFS Captain Admin', 'admin', 1, 'enabled', 'none')
ON CONFLICT (username) DO UPDATE 
SET password = crypt('$NEW_PASS', gen_salt('bf')), password_login = true, status = 'enabled';

-- 4. bfscaptain
INSERT INTO users (username, password_login, password, email, name, type, user_role_id, status, twofa_type)
VALUES ('bfscaptain', true, crypt('$NEW_PASS', gen_salt('bf')), 'bfscaptain@gmail.com', 'BFS Captain', 'admin', 1, 'enabled', 'none')
ON CONFLICT (username) DO UPDATE 
SET password = crypt('$NEW_PASS', gen_salt('bf')), password_login = true, status = 'enabled';
"

echo "🔄 Restarting BFS Mailer App Container..."
docker restart listmonk_app
sleep 4

echo ""
echo "======================================================================"
echo "📋 Verified Users in Database:"
echo "======================================================================"
docker exec listmonk_db psql -U listmonk -d listmonk -c "
SELECT id, username, email, type, password_login, status FROM users;
"

echo ""
echo "======================================================================"
echo "🎉 LOGIN READY! You can use ANY of these usernames:"
echo "----------------------------------------------------------------------"
echo "URL       : https://mailer.bfsfin.cloud/admin/login"
echo "Username 1: admin"
echo "Username 2: bfscaptain"
echo "Username 3: bfscaptain@gmail.com"
echo "Username 4: listmonk"
echo "Password  : $NEW_PASS"
echo "======================================================================"
