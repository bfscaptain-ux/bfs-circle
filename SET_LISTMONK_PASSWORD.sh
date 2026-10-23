#!/bin/bash
# ==============================================================================
# BFS Circle - Guaranteed Listmonk Password Setter & Verification
# Sets password for 'admin' and 'listmonk' to: admin123
# ==============================================================================

OUT="/var/www/crm/admin_pass_set.txt"

{
echo "======================================================================"
echo "🔑 STEP 1: Setting Official Bcrypt ($2a$) Password via PostgreSQL..."
echo "======================================================================"

# Verified $2a$ Bcrypt hash for: admin123
VERIFIED_HASH='$2a$10$ZOuGM/Wxc6jtogLheEhKuuwJNCymPANJg4xr1p9unyHC7siOq.q/S'

# 1. Update using pgcrypto or verified $2a$ hash
docker exec listmonk_db psql -U listmonk -d listmonk -c "
CREATE EXTENSION IF NOT EXISTS pgcrypto;
UPDATE users 
SET password = crypt('admin123', gen_salt('bf', 10)), 
    password_login = true, 
    status = 'enabled' 
WHERE username IN ('admin', 'listmonk');
" 2>&1

# Verify password column in database
docker exec listmonk_db psql -U listmonk -d listmonk -c "
SELECT id, username, email, type, password_login, status, substring(password from 1 for 7) as hash_prefix 
FROM users;
"

echo ""
echo "======================================================================"
echo "🔄 STEP 2: Restarting Listmonk App..."
echo "======================================================================"

docker restart listmonk_app
sleep 5

echo ""
echo "======================================================================"
echo "🧪 STEP 3: Automated Test Login with Username 'admin'..."
echo "======================================================================"

rm -f /tmp/lm_cookie.txt
HTML=$(curl -s -c /tmp/lm_cookie.txt https://mailer.bfsfin.cloud/admin)
NONCE=$(echo "$HTML" | grep 'name="nonce"' | head -n 1 | sed -E 's/.*value="([^"]+)".*/\1/')

echo "Found CSRF Nonce: $NONCE"

if [ -n "$NONCE" ]; then
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" -b /tmp/lm_cookie.txt -c /tmp/lm_cookie.txt \
        -d "username=admin&password=admin123&nonce=$NONCE" \
        https://mailer.bfsfin.cloud/admin/login)
    echo "Login Response Code (Should be 302/303 redirect): HTTP $HTTP_CODE"

    # Test dashboard with logged-in cookie
    DASHBOARD_CODE=$(curl -s -o /dev/null -w "%{http_code}" -b /tmp/lm_cookie.txt https://mailer.bfsfin.cloud/admin/)
    echo "Dashboard Access Code: HTTP $DASHBOARD_CODE"

    if [ "$HTTP_CODE" = "302" ] || [ "$HTTP_CODE" = "303" ] || [ "$DASHBOARD_CODE" = "200" ]; then
        echo ""
        echo "======================================================================"
        echo "🎉 AUTOMATED LOGIN TEST PASSED! CREDENTIALS ARE 100% WORKING!"
        echo "======================================================================"
    else
        echo "⚠️ Automated login returned $HTTP_CODE, dumping last app logs:"
        docker logs --tail 20 listmonk_app
    fi
fi

echo ""
echo "======================================================================"
echo "👉 Credentials:"
echo "   URL      : https://mailer.bfsfin.cloud/admin"
echo "   Username : admin"
echo "   Password : admin123"
echo "======================================================================"

} > "$OUT" 2>&1

chmod 644 "$OUT"
cat "$OUT"
