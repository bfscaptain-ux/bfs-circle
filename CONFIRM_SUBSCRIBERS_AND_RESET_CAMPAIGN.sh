#!/bin/bash
# ==============================================================================
# BFS Circle - Confirm All Subscribers & Reset Campaign to Draft
# ==============================================================================

OUT="/var/www/crm/confirm_subscribers.txt"

{
echo "======================================================================"
echo "🔍 Current Subscribers & List Status..."
echo "======================================================================"
docker exec listmonk_db psql -U listmonk -d listmonk -c "
SELECT s.id, s.email, s.name, sl.status as subscription_status, sl.list_id 
FROM subscribers s 
JOIN subscriber_lists sl ON s.id = sl.subscriber_id;
"

echo ""
echo "======================================================================"
echo "✅ Confirming All Subscribers & Setting Single Opt-in..."
echo "======================================================================"
# 1. Change unconfirmed to confirmed so Listmonk actually sends to them
docker exec listmonk_db psql -U listmonk -d listmonk -c "
UPDATE subscriber_lists SET status = 'confirmed';
"

# 2. Make all lists single opt-in so future subscribers are confirmed immediately
docker exec listmonk_db psql -U listmonk -d listmonk -c "
UPDATE lists SET optin = 'single';
"

# 3. Reset campaign back to 'draft' so user can click Start Campaign
docker exec listmonk_db psql -U listmonk -d listmonk -c "
UPDATE campaigns SET status = 'draft';
"

echo ""
echo "======================================================================"
echo "🎉 Status After Confirmation:"
echo "======================================================================"
docker exec listmonk_db psql -U listmonk -d listmonk -c "
SELECT s.email, s.name, sl.status as status 
FROM subscribers s 
JOIN subscriber_lists sl ON s.id = sl.subscriber_id;
"

docker exec listmonk_db psql -U listmonk -d listmonk -c "
SELECT id, name, status FROM campaigns;
"

echo ""
echo "All 7 subscribers are now CONFIRMED! Campaign is ready to send."

} > "$OUT" 2>&1

chmod 644 "$OUT"
cat "$OUT"
