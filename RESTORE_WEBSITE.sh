#!/bin/bash
# ==============================================================================
# BFS Circle - Restore Main Website (bfsfin.com) on Port 3000
# ==============================================================================

OUT="/var/www/crm/restore_web.txt"

{
echo "======================================================================"
echo "🔍 STEP 1: Finding Nginx Configuration for bfsfin.com..."
echo "======================================================================"

for f in $(grep -rl "bfsfin.com" /etc/nginx/sites-enabled/ /etc/nginx/sites-available/ 2>/dev/null | sort -u); do
    echo "--- File: $f ---"
    cat "$f"
    echo ""
done

echo "======================================================================"
echo "🔍 STEP 2: Searching for Website Directories on Server..."
echo "======================================================================"
ls -la /var/www/
find /var/www/ -maxdepth 2 -type d 2>/dev/null

echo ""
echo "======================================================================"
echo "🔍 STEP 3: Searching Bash History & PM2 History for 'bfsfin' / Port 3000..."
echo "======================================================================"
grep -E "pm2 start|PORT=3000|bfsfin" /root/.bash_history 2>/dev/null | tail -n 25

if [ -f "/root/.pm2/dump.pm2" ]; then
    echo "--- Current PM2 Dump ---"
    cat /root/.pm2/dump.pm2
    echo ""
fi

if [ -f "/root/.pm2/dump.pm2.bak" ]; then
    echo "--- Backup PM2 Dump ---"
    cat /root/.pm2/dump.pm2.bak
    echo ""
fi

echo "======================================================================"
echo "🚀 STEP 4: Attempting Auto-Start of Main Website..."
echo "======================================================================"

# Check common website directory candidates
WEB_DIR=""
for candidate in "/var/www/bfsfin" "/var/www/bfsfin.com" "/var/www/bfs" "/var/www/frontend" "/var/www/main" "/var/www/website" "/var/www/client"; do
    if [ -d "$candidate" ] && [ -f "$candidate/package.json" ]; then
        WEB_DIR="$candidate"
        echo "Found website candidate with package.json: $WEB_DIR"
        break
    fi
done

if [ -n "$WEB_DIR" ]; then
    cd "$WEB_DIR"
    echo "Starting website in $WEB_DIR with PM2 on port 3000..."
    pm2 delete bfsfin 2>/dev/null || true
    PORT=3000 pm2 start npm --name "bfsfin" -- start -- -p 3000 || PORT=3000 pm2 start npm --name "bfsfin" -- run start
    pm2 save
    sleep 3
fi

echo ""
echo "======================================================================"
echo "🧪 STEP 5: Verification of bfsfin.com..."
echo "======================================================================"

HTTP_LOCAL=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:3000/ || true)
HTTP_PUBLIC=$(curl -s -o /dev/null -w "%{http_code}" https://bfsfin.com/ || true)

echo "Local Status  (127.0.0.1:3000): HTTP $HTTP_LOCAL"
echo "Public Status (bfsfin.com)    : HTTP $HTTP_PUBLIC"

pm2 list

} > "$OUT" 2>&1

chmod 644 "$OUT"
cat "$OUT"
