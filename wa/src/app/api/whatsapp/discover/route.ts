import { NextResponse } from 'next/server';
import { createClient } from '@/lib/supabase/server';

const META_API_VERSION = 'v21.0';
const META_API_BASE = `https://graph.facebook.com/${META_API_VERSION}`;

interface DiscoveredPhoneNumber {
  id: string;
  display_phone_number: string;
  verified_name?: string;
  quality_rating?: string;
  code_verification_status?: string;
}

interface DiscoveredWaba {
  id: string;
  name?: string;
  phoneNumbers: DiscoveredPhoneNumber[];
}

export async function POST(request: Request) {
  try {
    const supabase = await createClient();
    const {
      data: { user },
      error: authError,
    } = await supabase.auth.getUser();

    if (authError || !user) {
      return NextResponse.json({ error: 'Unauthorized' }, { status: 401 });
    }

    const body = await request.json().catch(() => ({}));
    const rawToken = typeof body.accessToken === 'string' ? body.accessToken.trim() : '';
    const fallbackWabaId = typeof body.wabaId === 'string' ? body.wabaId.trim() : '';

    if (!rawToken) {
      return NextResponse.json(
        { error: 'Please enter a Meta Access Token to discover accounts.' },
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
    // For System Users: /me/assigned_whatsapp_business_accounts or /{system_user_id}/assigned_whatsapp_business_accounts
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
          console.log(`[whatsapp/discover] Endpoint ${ep} returned:`, resJson);
          for (const item of resJson?.data || []) {
            if (item?.id) candidateWabaIds.add(String(item.id));
          }
        } else {
          const errData = await r.json().catch(() => ({}));
          console.log(`[whatsapp/discover] Endpoint ${ep} response:`, errData?.error?.message);
        }
      } catch (e) {
        console.warn(`[whatsapp/discover] Endpoint ${ep} error:`, e);
      }
    }

    // 3. Also check /me/businesses if accessible
    try {
      const bizRes = await fetch(
        `${META_API_BASE}/me/businesses?fields=id,name,owned_whatsapp_business_accounts{id,name},client_whatsapp_business_accounts{id,name}&limit=10`,
        { headers: { Authorization: `Bearer ${rawToken}` } }
      );
      if (bizRes.ok) {
        const bizData = await bizRes.json();
        for (const b of bizData?.data || []) {
          for (const item of b?.owned_whatsapp_business_accounts?.data || []) {
            if (item?.id) candidateWabaIds.add(String(item.id));
          }
          for (const item of b?.client_whatsapp_business_accounts?.data || []) {
            if (item?.id) candidateWabaIds.add(String(item.id));
          }
        }
      }
    } catch (e) {
      console.warn('[whatsapp/discover] /me/businesses error:', e);
    }

    console.log('[whatsapp/discover] Discovered Candidate WABA IDs:', Array.from(candidateWabaIds));

    // 4. For each discovered WABA ID, fetch phone numbers
    const discoveredWabas: DiscoveredWaba[] = [];
    let metaFetchError: string | null = null;

    for (const wId of Array.from(candidateWabaIds)) {
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
          console.log(`[whatsapp/discover] WABA ${wId} phone numbers:`, pData);
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
            discoveredWabas.push({
              id: wId,
              name: wabaName,
              phoneNumbers: pList,
            });
          }
        } else {
          const errData = await phonesRes.json().catch(() => ({}));
          console.warn(`[whatsapp/discover] WABA ${wId} phone fetch failed:`, errData);
          if (errData?.error?.message) {
            metaFetchError = errData.error.message;
          }
        }
      } catch (err: unknown) {
        console.warn(`[whatsapp/discover] WABA ${wId} error:`, err);
      }
    }

    // 5. If candidate IDs yielded 0 phone numbers, check if the fallback ID was itself a Phone Number ID
    if (discoveredWabas.length === 0 && fallbackWabaId && /^\d+$/.test(fallbackWabaId)) {
      try {
        const singlePhoneRes = await fetch(
          `${META_API_BASE}/${fallbackWabaId}?fields=id,display_phone_number,verified_name,quality_rating,whatsapp_business_account{id,name}`,
          { headers: { Authorization: `Bearer ${rawToken}` } }
        );
        if (singlePhoneRes.ok) {
          const sData = await singlePhoneRes.json();
          const parentWaba = sData?.whatsapp_business_account;
          discoveredWabas.push({
            id: parentWaba?.id || fallbackWabaId || '',
            name: parentWaba?.name || 'WhatsApp Business Account',
            phoneNumbers: [
              {
                id: sData.id,
                display_phone_number: sData.display_phone_number,
                verified_name: sData.verified_name,
                quality_rating: sData.quality_rating,
              },
            ],
          });
        }
      } catch {
        // ignore
      }
    }

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
    const msg = error instanceof Error ? error.message : 'Failed to discover WhatsApp credentials';
    console.error('[whatsapp/discover] Fatal error:', error);
    return NextResponse.json({ error: msg }, { status: 500 });
  }
}
