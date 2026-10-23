#!/usr/bin/env python3
import os
import sys
import glob
import re
import subprocess
import shutil

print("=" * 60)
print("🔧 BFS WhatsApp Nginx Auto-Configurator & Fixer")
print("=" * 60)

# 1. Search for existing nginx configs containing wa.bfsfin.cloud
sites_available = "/etc/nginx/sites-available"
sites_enabled = "/etc/nginx/sites-enabled"
conf_d = "/etc/nginx/conf.d"

search_dirs = [sites_enabled, sites_available, conf_d]
matched_files = []

for sdir in search_dirs:
    if os.path.exists(sdir):
        for root, _, files in os.walk(sdir):
            for fname in files:
                fpath = os.path.join(root, fname)
                try:
                    with open(fpath, "r", encoding="utf-8", errors="ignore") as f:
                        content = f.read()
                        if "wa.bfsfin.cloud" in content:
                            matched_files.append(fpath)
                except Exception as e:
                    pass

print(f"📁 Found {len(matched_files)} Nginx config file(s) mentioning wa.bfsfin.cloud:")
for mf in matched_files:
    print(f"   - {mf}")

# 2. Check certbot certificates
cert_path = ""
key_path = ""
for domain in ["wa.bfsfin.cloud", "bfsfin.cloud", "crm.bfsfin.cloud", "social.bfsfin.cloud"]:
    cp = f"/etc/letsencrypt/live/{domain}/fullchain.pem"
    kp = f"/etc/letsencrypt/live/{domain}/privkey.pem"
    if os.path.isfile(cp) and os.path.isfile(kp):
        cert_path = cp
        key_path = kp
        print(f"🔒 Found SSL Certificate for {domain}: {cp}")
        break

if not cert_path:
    print("⚠️ No existing certbot SSL certificate found in /etc/letsencrypt/live/")

# 3. If matched files exist, update them
updated_any = False

proxy_block = """
        proxy_pass http://127.0.0.1:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;

        # Prevent 502 Bad Gateway from large Next.js auth headers
        proxy_buffer_size 128k;
        proxy_buffers 4 256k;
        proxy_busy_buffers_size 256k;

        # WebSocket & long polling timeouts
        proxy_read_timeout 86400s;
        proxy_send_timeout 86400s;

        # Allow iframe embedding inside crm.bfsfin.cloud
        proxy_hide_header X-Frame-Options;
"""

for mf in matched_files:
    # Resolve real path if symlink
    real_path = os.path.realpath(mf)
    print(f"\n⚙️ Analyzing: {real_path}")
    
    with open(real_path, "r", encoding="utf-8", errors="ignore") as f:
        orig = f.read()
    
    # Backup
    bak = real_path + ".bak_wafix"
    if not os.path.exists(bak):
        shutil.copy2(real_path, bak)
        print(f"   Backup saved: {bak}")
        
    modified = orig
    
    # Fix proxy_pass targeting localhost or port 3001
    modified = re.sub(r'proxy_pass\s+http://(localhost|127\.0\.0\.1):(3000|3001)[^;]*;', 'proxy_pass http://127.0.0.1:3000;', modified)
    
    # Check if proxy buffers are already configured
    if "proxy_buffer_size" not in modified:
        # Insert proxy buffers before closing brace of location /
        if "location / {" in modified:
            modified = modified.replace("location / {", "location / {\n" + proxy_block.strip() + "\n# --- original content below ---\n", 1)
        elif "location /" in modified:
            modified = re.sub(r'(location\s+/[^{]*\{)', r'\1\n' + proxy_block.strip() + '\n', modified, count=1)
            
    # Make sure proxy_hide_header X-Frame-Options is present
    if "proxy_hide_header X-Frame-Options" not in modified:
        modified = modified.replace("proxy_pass http://127.0.0.1:3000;", "proxy_pass http://127.0.0.1:3000;\n        proxy_hide_header X-Frame-Options;\n        proxy_buffer_size 128k;\n        proxy_buffers 4 256k;\n        proxy_busy_buffers_size 256k;")

    if modified != orig:
        with open(real_path, "w", encoding="utf-8") as f:
            f.write(modified)
        print(f"   ✅ Updated proxy directives in {real_path}")
        updated_any = True
    else:
        print(f"   ℹ️ Directives in {real_path} already match.")

# 4. If no config existed, create a brand new one
target_conf = "/etc/nginx/sites-available/wa.bfsfin.cloud"
enabled_conf = "/etc/nginx/sites-enabled/wa.bfsfin.cloud"

if not matched_files:
    print(f"\n📝 No existing config found for wa.bfsfin.cloud. Creating {target_conf}...")
    
    if cert_path and key_path:
        new_conf = f"""# BFS Circle - WhatsApp CRM Reverse Proxy
server {{
    listen 80;
    listen [::]:80;
    server_name wa.bfsfin.cloud;
    return 301 https://$host$request_uri;
}}

server {{
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name wa.bfsfin.cloud;

    ssl_certificate {cert_path};
    ssl_certificate_key {key_path};

    client_max_body_size 100M;

    location / {{
{proxy_block}
    }}
}}
"""
    else:
        new_conf = f"""# BFS Circle - WhatsApp CRM Reverse Proxy (HTTP initial)
server {{
    listen 80;
    listen [::]:80;
    server_name wa.bfsfin.cloud;

    client_max_body_size 100M;

    location / {{
{proxy_block}
    }}
}}
"""
    with open(target_conf, "w", encoding="utf-8") as f:
        f.write(new_conf)
    
    if not os.path.exists(enabled_conf):
        os.symlink(target_conf, enabled_conf)
    
    print(f"   ✅ Created {target_conf} and linked to {enabled_conf}")
    updated_any = True

# 5. Test nginx syntax
print("\n🧪 Testing Nginx configuration syntax (nginx -t)...")
res = subprocess.run(["nginx", "-t"], capture_output=True, text=True)
print(res.stderr.strip() or res.stdout.strip())

if res.returncode == 0:
    print("\n🔄 Reloading Nginx...")
    subprocess.run(["systemctl", "reload", "nginx"], check=True)
    print("✅ Nginx reloaded successfully!")
else:
    print("\n❌ Nginx test failed! Restoring backups...")
    for mf in matched_files:
        real_path = os.path.realpath(mf)
        bak = real_path + ".bak_wafix"
        if os.path.exists(bak):
            shutil.copy2(bak, real_path)
            print(f"   Restored: {real_path}")
    sys.exit(1)

print("\n🎉 WhatsApp Nginx configuration completed.")
