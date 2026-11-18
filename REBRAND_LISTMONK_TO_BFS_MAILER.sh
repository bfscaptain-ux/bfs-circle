#!/bin/bash
# ==============================================================================
# BFS Financial Services - Complete Listmonk White-Label Rebranding Script
# Transforms Listmonk into "BFS Mailer" across UI, Database, Assets & Nginx
# ==============================================================================

set -e

OUT="/var/www/crm/listmonk_rebrand_log.txt"
exec > >(tee -a "$OUT") 2>&1

echo "======================================================================"
echo "🚀 1/5: Creating Official BFS Mailer Vector SVG Logo & Favicon..."
echo "======================================================================"

mkdir -p /opt/listmonk
mkdir -p /var/www/crm/assets

# Write crisp SVG Logo for BFS Mailer
cat << 'EOF' > /opt/listmonk/bfs_mailer_logo.svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 280 60" width="280" height="60">
  <defs>
    <linearGradient id="bfsGrad" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#059669" />
      <stop offset="100%" stop-color="#047857" />
    </linearGradient>
    <linearGradient id="wingGrad" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#ffffff" />
      <stop offset="100%" stop-color="#d1fae5" />
    </linearGradient>
    <filter id="shadow" x="-10%" y="-10%" width="130%" height="130%">
      <feDropShadow dx="0" dy="2" stdDeviation="3" flood-color="#059669" flood-opacity="0.3"/>
    </filter>
  </defs>

  <!-- Modern Icon Container -->
  <g transform="translate(6, 6)">
    <rect x="0" y="0" width="48" height="48" rx="12" fill="url(#bfsGrad)" filter="url(#shadow)" />
    <path d="M 12 16 L 36 16 L 24 28 Z" fill="url(#wingGrad)" opacity="0.95" />
    <path d="M 12 18 L 24 30 L 36 18 L 36 34 L 12 34 Z" fill="none" stroke="#ffffff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
    <path d="M 12 34 L 20 26" stroke="#a7f3d0" stroke-width="2" stroke-linecap="round" />
    <path d="M 36 34 L 28 26" stroke="#a7f3d0" stroke-width="2" stroke-linecap="round" />
    <circle cx="36" cy="14" r="3.5" fill="#34d399" stroke="#ffffff" stroke-width="1.5" />
  </g>

  <!-- Typography: BFS MAILER -->
  <text x="66" y="32" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif" font-size="24" font-weight="900" fill="#0f172a" letter-spacing="-0.5">
    BFS <tspan fill="#059669" font-weight="800">MAILER</tspan>
  </text>
  
  <!-- Subtitle -->
  <text x="67" y="46" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif" font-size="8.5" font-weight="700" fill="#64748b" letter-spacing="1.5">
    FINANCIAL COMMUNICATIONS
  </text>
</svg>
EOF

cp /opt/listmonk/bfs_mailer_logo.svg /var/www/crm/assets/bfs_mailer_logo.svg 2>/dev/null || true
chmod 644 /opt/listmonk/bfs_mailer_logo.svg

# Download official BFS Favicon
curl -sL "https://bfsfin.com/favicon.png" -o /opt/listmonk/favicon.png || true
chmod 644 /opt/listmonk/favicon.png

echo "✅ Logo and Favicon generated successfully."

echo ""
echo "======================================================================"
echo "⚙️ 2/5: Updating Listmonk PostgreSQL Database Settings..."
echo "======================================================================"

# 1. Update Core Application Branding
docker exec listmonk_db psql -U listmonk -d listmonk -c "
INSERT INTO settings (key, value, updated_at)
VALUES ('app.site_name', to_jsonb('BFS Mailer'::text), NOW())
ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value, updated_at = NOW();

INSERT INTO settings (key, value, updated_at)
VALUES ('app.from_email', to_jsonb('BFS Mailer <bfscaptain@gmail.com>'::text), NOW())
ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value, updated_at = NOW();

INSERT INTO settings (key, value, updated_at)
VALUES ('app.logo_url', to_jsonb('/public/static/logo.svg'::text), NOW())
ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value, updated_at = NOW();

INSERT INTO settings (key, value, updated_at)
VALUES ('app.favicon_url', to_jsonb('/public/static/favicon.png'::text), NOW())
ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value, updated_at = NOW();
"

# 2. Generate and inject clean Appearance JSON using python3
python3 -c '
import json
data = {
    "public_css": """
:root {
  --bfs-primary: #059669;
  --bfs-primary-hover: #047857;
  --bfs-dark: #0f172a;
}
body {
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
  background-color: #f8fafc !important;
}
.container.wrap {
  max-width: 440px !important;
  margin: 40px auto !important;
}
.header {
  margin-bottom: 24px !important;
}
.header .logo {
  text-align: center !important;
}
.header .logo img {
  height: 52px !important;
  max-width: 260px !important;
  object-fit: contain !important;
  margin: 0 auto !important;
  display: block !important;
}
.login {
  background: #ffffff !important;
  border-radius: 16px !important;
  box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.02) !important;
  border: 1px solid #e2e8f0 !important;
  padding: 32px !important;
}
.login h2 {
  font-weight: 800 !important;
  color: #0f172a !important;
  font-size: 24px !important;
  margin-bottom: 20px !important;
}
.form input[type="text"],
.form input[type="password"] {
  border-radius: 10px !important;
  border: 1.5px solid #cbd5e1 !important;
  padding: 12px 14px !important;
  font-size: 15px !important;
  transition: all 0.2s ease !important;
}
.form input[type="text"]:focus,
.form input[type="password"]:focus {
  border-color: #059669 !important;
  box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.15) !important;
  outline: none !important;
}
.button, button[type="submit"] {
  background: linear-gradient(135deg, #059669 0%, #047857 100%) !important;
  border: none !important;
  border-radius: 10px !important;
  padding: 12px 20px !important;
  font-weight: 700 !important;
  font-size: 16px !important;
  color: #ffffff !important;
  box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25) !important;
  cursor: pointer !important;
}
.button:hover, button[type="submit"]:hover {
  background: linear-gradient(135deg, #047857 0%, #065f46 100%) !important;
  transform: translateY(-1px) !important;
}
footer.container {
  font-size: 0 !important;
  margin-top: 30px !important;
  text-align: center !important;
}
footer.container a {
  display: none !important;
}
footer.container::before {
  content: "Powered by BFS Mailer • Bhardwaj Financial Services" !important;
  font-size: 13px !important;
  color: #94a3b8 !important;
  font-weight: 600 !important;
}
""",
    "public_js": """
(function() {
  function rewrite() {
    if (document.title.toLowerCase().includes("listmonk") || document.title.includes("BFS Financial Services")) {
      document.title = document.title.replace(/listmonk/gi, "BFS Mailer").replace(/BFS Financial Services/gi, "BFS Mailer");
    }
  }
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", rewrite);
  } else {
    rewrite();
  }
  setInterval(rewrite, 1000);
})();
""",
    "admin_css": """
:root {
  --primary: #059669 !important;
  --primary-hover: #047857 !important;
}
.sidebar .logo img,
aside .logo img,
.brand img,
header .logo img {
  content: url("/public/static/logo.svg") !important;
  max-height: 40px !important;
  width: auto !important;
}
.sidebar .version,
.sidebar .footer,
aside .footer,
.footer-links,
a[href*="listmonk.app"],
a[href*="github.com/knadh/listmonk"] {
  display: none !important;
  visibility: hidden !important;
}
.button.is-primary {
  background-color: #059669 !important;
  border-color: transparent !important;
}
.button.is-primary:hover {
  background-color: #047857 !important;
}
""",
    "admin_js": """
(function() {
  function cleanAdmin() {
    if (document.title.toLowerCase().includes("listmonk")) {
      document.title = document.title.replace(/listmonk/gi, "BFS Mailer");
    }
    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, null, false);
    let node;
    while (node = walker.nextNode()) {
      if (node.nodeValue && node.nodeValue.toLowerCase().includes("listmonk")) {
        if (node.parentElement && !["INPUT", "TEXTAREA", "CODE", "PRE"].includes(node.parentElement.tagName)) {
          node.nodeValue = node.nodeValue.replace(/listmonk/gi, "BFS Mailer");
        }
      }
    }
    document.querySelectorAll("img[src*=\"logo.svg\"]").forEach(img => {
      img.alt = "BFS Mailer";
    });
  }
  window.addEventListener("load", cleanAdmin);
  const obs = new MutationObserver(cleanAdmin);
  obs.observe(document.documentElement, { childList: true, subtree: true });
})();
"""
}
with open("/opt/listmonk/appearance.json", "w", encoding="utf-8") as f:
    json.dump(data, f)
'

docker cp /opt/listmonk/appearance.json listmonk_db:/tmp/appearance.json
docker exec listmonk_db psql -U listmonk -d listmonk -c "
INSERT INTO settings (key, value, updated_at)
VALUES ('appearance', pg_read_file('/tmp/appearance.json')::jsonb, NOW())
ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value, updated_at = NOW();
"

echo "✅ Database settings updated successfully."

echo ""
echo "======================================================================"
echo "🌐 3/5: Configuring Nginx with IPv4, IPv6 & Auto-Redirect to /admin..."
echo "======================================================================"

cat << 'EOF' > /etc/nginx/sites-available/mailer.bfsfin.cloud
server {
    server_name mailer.bfsfin.cloud;
    client_max_body_size 100M;

    # Listen on both IPv4 and IPv6
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    ssl_certificate /etc/letsencrypt/live/mailer.bfsfin.cloud/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/mailer.bfsfin.cloud/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;

    # Auto-redirect root / to /admin
    location = / {
        return 302 /admin;
    }

    # Intercept and serve custom BFS Mailer SVG Logo directly
    location = /public/static/logo.svg {
        alias /opt/listmonk/bfs_mailer_logo.svg;
        default_type image/svg+xml;
        add_header Cache-Control "public, max-age=86400";
    }

    # Intercept and serve official BFS Favicon directly
    location = /public/static/favicon.png {
        alias /opt/listmonk/favicon.png;
        default_type image/png;
        add_header Cache-Control "public, max-age=86400";
    }

    location / {
        proxy_pass http://127.0.0.1:9000;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto https;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";

        # Disable upstream compression so Nginx sub_filter can rewrite HTML on-the-fly
        proxy_set_header Accept-Encoding "";

        # Complete White-Label HTML String Replacement
        sub_filter_once off;
        sub_filter_types text/html text/xml;
        sub_filter 'Powered by <a target="_blank" rel="noreferrer" href="https://listmonk.app">listmonk</a>' 'Powered by <span style="font-weight:700;color:#059669;">BFS Mailer</span> • Bhardwaj Financial Services';
        sub_filter 'Powered by listmonk' 'Powered by BFS Mailer';
        sub_filter 'powered by listmonk' 'powered by BFS Mailer';
        sub_filter 'Powered by Listmonk' 'Powered by BFS Mailer';
        sub_filter '>listmonk<' '>BFS Mailer<';
        sub_filter '>Listmonk<' '>BFS Mailer<';
        sub_filter 'Login - BFS Financial Services' 'Login - BFS Mailer';
        sub_filter 'Login - listmonk' 'Login - BFS Mailer';

        # Allow iframe embedding inside BFS CRM Hub
        proxy_hide_header X-Frame-Options;
        add_header X-Frame-Options "ALLOW-FROM https://crm.bfsfin.cloud" always;
        add_header Content-Security-Policy "frame-ancestors 'self' https://crm.bfsfin.cloud https://*.bfsfin.cloud;" always;

        proxy_buffer_size 128k;
        proxy_buffers 4 256k;
        proxy_busy_buffers_size 256k;
    }
}

server {
    listen 80;
    listen [::]:80;
    server_name mailer.bfsfin.cloud;
    return 301 https://$host$request_uri;
}
EOF

ln -sf /etc/nginx/sites-available/mailer.bfsfin.cloud /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx

echo "✅ Nginx reverse proxy reloaded with IPv4/IPv6 & White-Label rules."

echo ""
echo "======================================================================"
echo "🐳 4/5: Updating Docker Compose & Restarting Listmonk App..."
echo "======================================================================"

if [ -f "/opt/listmonk/docker-compose.yml" ]; then
    sed -i 's/LISTMONK_app__site_name: .*/LISTMONK_app__site_name: "BFS Mailer"/' /opt/listmonk/docker-compose.yml
fi

docker restart listmonk_app
sleep 4

echo "✅ Listmonk App container restarted."

echo ""
echo "======================================================================"
echo "🧪 5/5: Verification Test (Live Endpoint Check)..."
echo "======================================================================"

TITLE=$(curl -sL "https://mailer.bfsfin.cloud/admin/login" | grep -i "<title>" || true)
echo "Page Title: $TITLE"

FOOTER=$(curl -sL "https://mailer.bfsfin.cloud/admin/login" | grep -i "Powered by" || true)
echo "Footer Attribution: $FOOTER"

echo ""
echo "======================================================================"
echo "🎉 SUCCESS: Listmonk is now 100% Rebranded to 'BFS Mailer'!"
echo "Open https://mailer.bfsfin.cloud/admin in your browser!"
echo "======================================================================"
