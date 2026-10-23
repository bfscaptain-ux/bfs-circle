#!/bin/bash
OUT="/var/www/crm/nginx_wa.txt"
{
    echo "=== NGINX SITES-ENABLED ==="
    ls -la /etc/nginx/sites-enabled/
    echo ""
    echo "=== ALL SITES WITH wa.bfsfin.cloud ==="
    grep -rn "wa.bfsfin.cloud" /etc/nginx/ 2>/dev/null
    echo ""
    echo "=== FULL CONFIG FILES MATCHING wa ==="
    for f in $(grep -rl "wa.bfsfin.cloud" /etc/nginx/sites-enabled/ /etc/nginx/sites-available/ 2>/dev/null); do
        echo "FILE: $f"
        cat "$f"
        echo "-----------------------------------"
    done
    echo ""
    echo "=== NGINX ERROR LOG (Last 25 lines) ==="
    tail -n 25 /var/log/nginx/error.log
} > "$OUT" 2>&1
chmod 644 "$OUT"
cat "$OUT"
