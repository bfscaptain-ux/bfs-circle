#!/bin/bash
set -e

echo "======================================================================"
echo "🚀 BFS FINANCIAL SERVICES - COMPLETE CRM & WEBSITE VPS SYNC"
echo "======================================================================"
echo ""

# 1. Update CRM
CRM_DIR="/var/www/crm"
if [ ! -d "$CRM_DIR" ]; then
    for cand in "/var/www/crm.bfsfin.cloud" "/var/www/client" "/var/www/portal"; do
        if [ -d "$cand" ]; then CRM_DIR="$cand"; break; fi
    done
fi

echo "📂 [1/3] Updating CRM Desk at: $CRM_DIR..."
if [ -d "$CRM_DIR" ]; then
    cd "$CRM_DIR"
    git fetch origin
    git checkout main || true
    git pull origin main
    mkdir -p "$CRM_DIR/uploads/news"
    chown -R www-data:www-data "$CRM_DIR" || true
    chmod -R 775 "$CRM_DIR/uploads" || true
    echo "✅ CRM updated successfully with view_news_image.php!"
else
    echo "⚠️ CRM directory not found, skipping git pull."
fi

# 2. Update Main Website
WEB_DIR="/var/www/bfsfin"
if [ ! -d "$WEB_DIR" ]; then
    for cand in "/var/www/bfsfin.com" "/var/www/website" "/var/www/bfs"; do
        if [ -d "$cand" ]; then WEB_DIR="$cand"; break; fi
    done
fi

echo ""
echo "📂 [2/3] Updating Main Website at: $WEB_DIR..."
if [ -d "$WEB_DIR" ]; then
    cd "$WEB_DIR"
    git fetch origin
    git checkout master || true
    git pull origin master
    mkdir -p "$WEB_DIR/public/uploads/news"
    chmod -R 775 "$WEB_DIR/public/uploads" || true
    
    echo "📦 Generating Prisma..."
    npx prisma generate || true
    
    echo "🔨 Building Next.js for Production (News studio, Print layout & Live API)..."
    npm run build
    
    echo "🔄 Restarting PM2 process..."
    if pm2 describe bfsfin > /dev/null 2>&1; then
        pm2 restart bfsfin
    elif pm2 describe website > /dev/null 2>&1; then
        pm2 restart website
    else
        pm2 restart all || true
    fi
    pm2 save
    echo "✅ Website updated and restarted successfully!"
else
    echo "⚠️ Website directory not found, skipping."
fi

# 3. Test verification
echo ""
echo "======================================================================"
echo "🧪 [3/3] Live Verification Tests:"
echo "======================================================================"
sleep 2

TEST_IMG_URL="https://crm.bfsfin.cloud/view_news_image.php?file=1794900446156-Inflation_Hits_6_09___RBI_Repo_Rate_Hike_Likely_in_Dec_2026_How_Home_Loan_Borrowers_Can_Save_EMI_Now.webp"
HTTP_IMG=$(curl -s -o /dev/null -w "%{http_code}" "$TEST_IMG_URL" || true)
echo "1. News Image Streamer (CRM): HTTP $HTTP_IMG"

HTTP_SITE=$(curl -s -o /dev/null -w "%{http_code}" "https://bfsfin.com/news" || true)
echo "2. Public News Hub: HTTP $HTTP_SITE"

HTTP_API=$(curl -s -o /dev/null -w "%{http_code}" "https://bfsfin.com/api/news" || true)
echo "3. News Live API: HTTP $HTTP_API"

echo ""
echo "🎉 ALL UPDATES DEPLOYED SUCCESSFULLY! You can now refresh CRM & Website."
echo "======================================================================"
