#!/bin/bash
# ==============================================================================
# BFS Circle - Instantly Unlock CRM Rate Limits & Set Admin Password
# ==============================================================================

OUT="/var/www/crm/unlock_result.txt"

{
echo "======================================================================"
echo "🔓 STEP 1: Clearing Rate Limits & Updating Admin Password..."
echo "======================================================================"

php -r "
require '/var/www/crm/config.php';
try {
    \$db->exec('DELETE FROM login_rate_limits;');
    \$pwd = 'BFSaNuJ@070123';
    \$hash = password_hash(\$pwd, PASSWORD_DEFAULT);
    \$db->prepare('UPDATE users SET password_hash = ?, plain_password = ?, email = ? WHERE LOWER(username) = ? OR role = ?')->execute([\$hash, \$pwd, 'bfscaptain@gmail.com', 'admin', 'Admin']);
    echo 'SUCCESS: Rate limits cleared & Admin password set to BFSaNuJ@070123' . PHP_EOL;
} catch (Exception \$e) {
    echo 'ERROR: ' . \$e->getMessage() . PHP_EOL;
}
"

echo ""
echo "======================================================================"
echo "✅ STEP 2: Verifying Admin User in Database..."
echo "======================================================================"

php -r "
require '/var/www/crm/config.php';
\$u = \$db->query('SELECT id, username, email, role, plain_password FROM users WHERE LOWER(username) = \"admin\"')->fetch(PDO::FETCH_ASSOC);
print_r(\$u);
"

echo ""
echo "Lockout is 100% CLEARED! You can log in immediately with:"
echo "Username: admin"
echo "Password: BFSaNuJ@070123"

} > "$OUT" 2>&1

chmod 644 "$OUT"
cat "$OUT"
