#!/bin/bash
# ==============================================================================
# BFS Circle - Test Gmail SMTP Directly from VPS
# ==============================================================================

OUT="/var/www/crm/vps_gmail_test.txt"

{
echo "======================================================================"
echo "🧪 1. Testing Python smtplib from VPS to smtp.gmail.com:587..."
echo "======================================================================"

python3 - << 'EOF'
import smtplib
from email.mime.text import MIMEText

user = "bfscaptain@gmail.com"
password = "agftujxnvcscrgyf"
to_email = "bfscaptain@gmail.com"

try:
    print(f"Connecting to smtp.gmail.com:587 from VPS...")
    server = smtplib.SMTP("smtp.gmail.com", 587, timeout=10)
    server.set_debuglevel(1)
    print("Sending STARTTLS...")
    server.starttls()
    print("Attempting login...")
    server.login(user, password)
    print("✅ SUCCESS: VPS can connect and authenticate to Gmail SMTP!")

    msg = MIMEText("Hello from BFS Listmonk VPS! Your Gmail SMTP is 100% active and working.")
    msg["Subject"] = "BFS Circle Listmonk SMTP Test (Direct VPS)"
    msg["From"] = f"BFS Financial Services <{user}>"
    msg["To"] = to_email

    server.sendmail(user, [to_email], msg.as_string())
    server.quit()
    print(f"✅ SUCCESS: Test email dispatched to {to_email}!")
except Exception as e:
    print(f"❌ FAILED: {e}")
EOF

echo ""
echo "======================================================================"
echo "🧪 2. Checking Listmonk Saved SMTP in Database..."
echo "======================================================================"
docker exec listmonk_db psql -U listmonk -d listmonk -c "
SELECT key, value FROM settings WHERE key = 'smtp';
"

} > "$OUT" 2>&1

chmod 644 "$OUT"
cat "$OUT"
