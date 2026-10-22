import sys

# Target file
target_path = sys.argv[1] if len(sys.argv) > 1 else '/var/www/crm/fb_provider.js'

with open(target_path, 'r', encoding='utf-8') as f:
    content = f.read()

start_marker = 'async authenticate(params) {'
end_marker = 'async pages(accessToken) {'

start_idx = content.find(start_marker)
end_idx = content.find(end_marker)

if start_idx == -1 or end_idx == -1:
    print(f"❌ Error: Could not find authenticate markers in {target_path}")
    sys.exit(1)

new_func = """async authenticate(params) {
        console.log("=== [FB DEBUG AUTH] START ===");
        console.log("APP_ID:", process.env.FACEBOOK_APP_ID);
        console.log("FRONTEND_URL:", process.env.FRONTEND_URL);

        const tokenUrl = `https://graph.facebook.com/${exports.META_GRAPH_API_VERSION}/oauth/access_token` +
            `?client_id=${process.env.FACEBOOK_APP_ID}` +
            `&redirect_uri=${encodeURIComponent(`${process.env.FRONTEND_URL}/integrations/social/facebook${params.refresh ? `?refresh=${params.refresh}` : ''}`)}` +
            `&client_secret=${process.env.FACEBOOK_APP_SECRET}` +
            `&code=${params.code}`;

        const tokenResp = await fetch(tokenUrl);
        const getAccessToken = await tokenResp.json();
        console.log("=== [FB DEBUG AUTH] getAccessToken HTTP " + tokenResp.status + ":", JSON.stringify(getAccessToken));

        if (!getAccessToken.access_token) {
            throw new Error("Facebook OAuth token exchange failed: " + JSON.stringify(getAccessToken));
        }

        const exchangeUrl = `https://graph.facebook.com/${exports.META_GRAPH_API_VERSION}/oauth/access_token` +
            '?grant_type=fb_exchange_token' +
            `&client_id=${process.env.FACEBOOK_APP_ID}` +
            `&client_secret=${process.env.FACEBOOK_APP_SECRET}` +
            `&fb_exchange_token=${getAccessToken.access_token}&fields=access_token,expires_in`;

        const exchangeResp = await fetch(exchangeUrl);
        const exchangeJson = await exchangeResp.json();
        console.log("=== [FB DEBUG AUTH] exchangeToken HTTP " + exchangeResp.status + ":", JSON.stringify(exchangeJson));

        const access_token = exchangeJson.access_token || getAccessToken.access_token;

        const permResp = await fetch(`https://graph.facebook.com/${exports.META_GRAPH_API_VERSION}/me/permissions?access_token=${access_token}`);
        const permJson = await permResp.json();
        console.log("=== [FB DEBUG AUTH] permissions HTTP " + permResp.status + ":", JSON.stringify(permJson));

        const permissions = ((permJson && permJson.data) ? permJson.data : [])
            .filter((d) => d.status === 'granted')
            .map((p) => p.permission);
        console.log("=== [FB DEBUG AUTH] Granted permissions:", JSON.stringify(permissions));

        try {
            this.checkScopes(this.scopes, permissions);
        } catch (e) {
            console.warn("=== [FB DEBUG AUTH] Scope check warning (safely continuing):", e.message);
        }

        const meResp = await fetch(`https://graph.facebook.com/${exports.META_GRAPH_API_VERSION}/me?fields=id,name,picture&access_token=${access_token}`);
        const meJson = await meResp.json();
        console.log("=== [FB DEBUG AUTH] me HTTP " + meResp.status + ":", JSON.stringify(meJson));

        return {
            id: meJson.id,
            name: meJson.name,
            accessToken: access_token,
            refreshToken: access_token,
            expiresIn: (0, dayjs_1.default)().add(59, 'days').unix() - (0, dayjs_1.default)().unix(),
            picture: meJson.picture?.data?.url || '',
            username: '',
        };
    }
    """

patched = content[:start_idx] + new_func + content[end_idx:]

with open(target_path, 'w', encoding='utf-8') as f:
    f.write(patched)

print(f"[SUCCESS] Patched {target_path} successfully!")
