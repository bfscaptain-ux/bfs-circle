import { NextResponse } from 'next/server';
import { createClient } from '@/lib/supabase/server';
import { decrypt } from '@/lib/whatsapp/encryption';

interface DiscoveredPhoneNumber {
  id: string;
  display_phone_number: string;
  verified_name?: string;
  quality_rating?: string;
  code_verification_status?: string;
}

interface DiscoveredWaba {
  id: string;
  name: string;
  phoneNumbers: DiscoveredPhoneNumber[];
}

const META_API_BASE = 'https://graph.facebook.com/v21.0';

/**
 * POST /api/whatsapp/discover
 *
 * Smart Meta Cloud API v21+ Auto-Discovery Engine:
 * Accepts a Meta Permanent Access Token (and optional fallback WABA ID),
 * queries the Meta Graph API v21+ endpoints to discover all WhatsApp Business
 * Accounts and verified Phone Number IDs associated with the token.
 */
export async function POST(request: Request) {
  try {
    const body = await request.json().catch(() => ({}));
    let rawToken =
      (typeof body?.access_token === 'string' && body.access_token.trim()) ||
      (typeof body?.accessToken === 'string' && body.accessToken.trim()) ||
      '';
    const fallbackWabaId =
      (typeof body?.waba_id === 'string' && body.waba_id.trim()) ||
      (typeof body?.wabaId === 'string' && body.wabaId.trim()) ||
      '';

    // If token passed is empty or masked ("••••"), fallback to saved token in DB
    if (!rawToken || rawToken.startsWith('••') || rawToken.includes('••••')) {
      try {
        const supabase = await createClient();
        const {
          data: { user },
        } = await supabase.auth.getUser();
        if (user) {
          const { data: profile } = await supabase
            .from('profiles')
            .select('account_id')
            .eq('user_id', user.id)
            .maybeSingle();

          if (profile?.account_id) {
            const { data: cfg } = await supabase
              .from('whatsapp_config')
              .select('access_token')
              .eq('account_id', profile.account_id)
              .maybeSingle();

            if (cfg?.access_token) {
              rawToken = decrypt(cfg.access_token);
            }
          }
        }
      } catch (dbErr) {
        console.warn('[whatsapp/discover] fallback token fetch failed:', dbErr);
      }
    }

    if (!rawToken) {
      return NextResponse.json(
        { error: 'Meta Access Token is required for auto-discovery. Please paste your token or save credentials first.' },
        { status: 400 }
      );
    }

    const candidateWabaIds = new Set<string>();
    let systemUserId = '';
    let systemUserName = '';

    // If user provided a WABA ID, prioritize it
    if (fallbackWabaId && /^\d+$/.test(fallbackWabaId)) {
      candidateWabaIds.add(fallbackWabaId);
    }
    // Known BFS WABA ID
    candidateWabaIds.add('1701808357546633');

    // 1. Identify token owner via /me
    try {
      const meRes = await fetch(`${META_API_BASE}/me?fields=id,name`, {
        headers: { Authorization: `Bearer ${rawToken}` },
      });
      if (meRes.ok) {
        const meData = await meRes.json();
        systemUserId = meData?.id || '';
        systemUserName = meData?.name || '';
        console.log('[whatsapp/discover] Token owner:', { systemUserId, systemUserName });
      } else {
        const errJson = await meRes.json().catch(() => ({}));
        console.warn('[whatsapp/discover] /me failed:', errJson);
      }
    } catch (e) {
      console.warn('[whatsapp/discover] /me exception:', e);
    }

    // 2. Query System User's assigned WhatsApp Business Accounts
    const endpointsToTry = [
      `${META_API_BASE}/me/assigned_whatsapp_business_accounts?fields=id,name&limit=50`,
      `${META_API_BASE}/me/whatsapp_business_accounts?fields=id,name&limit=50`,
    ];
    if (systemUserId) {
      endpointsToTry.push(
        `${META_API_BASE}/${systemUserId}/assigned_whatsapp_business_accounts?fields=id,name&limit=50`
      );
    }

    for (const ep of endpointsToTry) {
      try {
        const r = await fetch(ep, {
          headers: { Authorization: `Bearer ${rawToken}` },
        });
        if (r.ok) {
          const resJson = await r.json();
          for (const item of resJson?.data || []) {
            if (item?.id) candidateWabaIds.add(String(item.id));
          }
        }
      } catch (e) {
        console.warn(`[whatsapp/discover] Endpoint ${ep} error:`, e);
      }
    }

    const discoveredWabasMap = new Map<string, DiscoveredWaba>();

    // 3. Also check business portfolios (including BFS portfolio 964140506785086)
    const bizUrls = [
      `${META_API_BASE}/964140506785086?fields=id,name,owned_whatsapp_business_accounts{id,name,phone_numbers{id,display_phone_number,verified_name,quality_rating}},client_whatsapp_business_accounts{id,name,phone_numbers{id,display_phone_number,verified_name,quality_rating}}`,
      `${META_API_BASE}/me/businesses?fields=id,name,owned_whatsapp_business_accounts{id,name},client_whatsapp_business_accounts{id,name}&limit=10`,
    ];

    for (const bUrl of bizUrls) {
      try {
        const bizRes = await fetch(bUrl, {
          headers: { Authorization: `Bearer ${rawToken}` },
        });
        if (bizRes.ok) {
          const bizData = await bizRes.json();
          const bizList = Array.isArray(bizData?.data) ? bizData.data : [bizData];
          for (const b of bizList) {
            for (const item of b?.owned_whatsapp_business_accounts?.data || []) {
              if (item?.id) candidateWabaIds.add(String(item.id));
              if (item?.phone_numbers?.data && item.phone_numbers.data.length > 0) {
                const pList: DiscoveredPhoneNumber[] = item.phone_numbers.data.map(
                  (p: Record<string, unknown>) => ({
                    id: String(p.id || ''),
                    display_phone_number: String(p.display_phone_number || ''),
                    verified_name: p.verified_name ? String(p.verified_name) : undefined,
                    quality_rating: p.quality_rating ? String(p.quality_rating) : undefined,
                  })
                );
                discoveredWabasMap.set(String(item.id), {
                  id: String(item.id),
                  name: item.name || `WABA (${item.id})`,
                  phoneNumbers: pList,
                });
              }
            }
            for (const item of b?.client_whatsapp_business_accounts?.data || []) {
              if (item?.id) candidateWabaIds.add(String(item.id));
            }
          }
        }
      } catch (e) {
        console.warn('[whatsapp/discover] business query error:', e);
      }
    }

    let metaFetchError: string | null = null;

    // 4. For each discovered WABA ID not yet fetched, fetch phone numbers
    for (const wId of Array.from(candidateWabaIds)) {
      if (discoveredWabasMap.has(wId)) continue;

      try {
        const [wabaInfoRes, phonesRes] = await Promise.all([
          fetch(`${META_API_BASE}/${wId}?fields=id,name`, {
            headers: { Authorization: `Bearer ${rawToken}` },
          }),
          fetch(
            `${META_API_BASE}/${wId}/phone_numbers?fields=id,display_phone_number,verified_name,quality_rating,code_verification_status&limit=50`,
            {
              headers: { Authorization: `Bearer ${rawToken}` },
            }
          ),
        ]);

        let wabaName = `WABA (${wId})`;
        if (wabaInfoRes.ok) {
          const wInfo = await wabaInfoRes.json();
          if (wInfo?.name) wabaName = wInfo.name;
        }

        if (phonesRes.ok) {
          const pData = await phonesRes.json();
          const pList: DiscoveredPhoneNumber[] = (pData?.data || []).map(
            (p: Record<string, unknown>) => ({
              id: String(p.id || ''),
              display_phone_number: String(p.display_phone_number || ''),
              verified_name: p.verified_name ? String(p.verified_name) : undefined,
              quality_rating: p.quality_rating ? String(p.quality_rating) : undefined,
              code_verification_status: p.code_verification_status
                ? String(p.code_verification_status)
                : undefined,
            })
          );

          if (pList.length > 0) {
            discoveredWabasMap.set(wId, {
              id: wId,
              name: wabaName,
              phoneNumbers: pList,
            });
          }
        } else {
          const errData = await phonesRes.json().catch(() => ({}));
          if (errData?.error?.message) {
            metaFetchError = errData.error.message;
          }
        }
      } catch (err: unknown) {
        console.warn(`[whatsapp/discover] WABA ${wId} error:`, err);
      }
    }

    const discoveredWabas = Array.from(discoveredWabasMap.values());
    const totalPhoneNumbers = discoveredWabas.reduce((sum, w) => sum + w.phoneNumbers.length, 0);

    return NextResponse.json({
      success: true,
      systemUserId,
      systemUserName,
      wabas: discoveredWabas,
      totalPhoneNumbers,
      candidateWabaIds: Array.from(candidateWabaIds),
      errorHint: totalPhoneNumbers === 0 ? metaFetchError : null,
    });
  } catch (error: unknown) {
    const message = error instanceof Error ? error.message : 'Discovery failed';
    console.error('[whatsapp/discover] Exception:', error);
    return NextResponse.json({ error: message }, { status: 500 });
  }
}
