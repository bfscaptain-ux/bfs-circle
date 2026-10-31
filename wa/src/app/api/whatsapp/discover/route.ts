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

    // 1. Inspect the token with Meta debug_token
    let appId = '';
    let granularScopes: Array<{ scope: string; target_ids?: string[] }> = [];
    let scopes: string[] = [];
    let isValid = false;

    try {
      const debugRes = await fetch(
        `${META_API_BASE}/debug_token?input_token=${encodeURIComponent(rawToken)}&access_token=${encodeURIComponent(rawToken)}`
      );
      if (debugRes.ok) {
        const debugData = await debugRes.json();
        const info = debugData?.data;
        if (info) {
          isValid = Boolean(info.is_valid);
          appId = info.app_id || '';
          scopes = info.scopes || [];
          granularScopes = info.granular_scopes || [];
        }
      }
    } catch (e) {
      console.warn('[whatsapp/discover] debug_token inspect error:', e);
    }

    // Check for necessary permissions
    const requiredScopes = ['whatsapp_business_messaging', 'whatsapp_business_management'];
    const missingScopes = requiredScopes.filter((s) => !scopes.includes(s));

    // 2. Collect potential WABA IDs from:
    // a) granular_scopes target_ids
    // b) fallbackWabaId supplied by user
    // c) /me/whatsapp_business_accounts
    // d) /me/businesses
    const candidateWabaIds = new Set<string>();
    if (fallbackWabaId && /^\d+$/.test(fallbackWabaId)) {
      candidateWabaIds.add(fallbackWabaId);
    }

    for (const gs of granularScopes) {
      if (gs.target_ids && Array.isArray(gs.target_ids)) {
        for (const tid of gs.target_ids) {
          if (/^\d+$/.test(tid)) {
            candidateWabaIds.add(tid);
          }
        }
      }
    }

    // Try fetching /me/whatsapp_business_accounts
    try {
      const wabaRes = await fetch(
        `${META_API_BASE}/me/whatsapp_business_accounts?fields=id,name&limit=50`,
        { headers: { Authorization: `Bearer ${rawToken}` } }
      );
      if (wabaRes.ok) {
        const wabaData = await wabaRes.json();
        for (const item of wabaData?.data || []) {
          if (item?.id) candidateWabaIds.add(item.id);
        }
      }
    } catch (e) {
      console.warn('[whatsapp/discover] /me/whatsapp_business_accounts error:', e);
    }

    // Try fetching /me/businesses
    try {
      const bizRes = await fetch(
        `${META_API_BASE}/me/businesses?fields=id,name,owned_whatsapp_business_accounts{id,name},client_whatsapp_business_accounts{id,name}&limit=20`,
        { headers: { Authorization: `Bearer ${rawToken}` } }
      );
      if (bizRes.ok) {
        const bizData = await bizRes.json();
        for (const b of bizData?.data || []) {
          for (const item of b?.owned_whatsapp_business_accounts?.data || []) {
            if (item?.id) candidateWabaIds.add(item.id);
          }
          for (const item of b?.client_whatsapp_business_accounts?.data || []) {
            if (item?.id) candidateWabaIds.add(item.id);
          }
        }
      }
    } catch (e) {
      console.warn('[whatsapp/discover] /me/businesses error:', e);
    }

    // 3. For each candidate WABA ID, fetch phone numbers and WABA info
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
          if (errData?.error?.message) {
            metaFetchError = errData.error.message;
          }
        }
      } catch (err: unknown) {
        console.warn(`[whatsapp/discover] WABA ${wId} error:`, err);
      }
    }

    // 4. If no WABA had phone numbers via list, but user provided an ID that might directly be a Phone Number ID:
    if (discoveredWabas.length === 0 && fallbackWabaId && /^\d+$/.test(fallbackWabaId)) {
      try {
        const singlePhoneRes = await fetch(
          `${META_API_BASE}/${fallbackWabaId}?fields=id,display_phone_number,verified_name,quality_rating,whatsapp_business_account{id,name}`,
          { headers: { Authorization: `Bearer ${rawToken}` } }
        );
        if (singlePhoneRes.ok) {
          const sData = await singlePhoneRes.json();
          const parentWaba = sData?.whatsapp_business_account;
          const assignedWabaId = parentWaba?.id || 'Unknown';
          discoveredWabas.push({
            id: assignedWabaId,
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
        // Ignore single phone probe
      }
    }

    // Check if we found anything
    const totalPhoneNumbers = discoveredWabas.reduce((sum, w) => sum + w.phoneNumbers.length, 0);

    return NextResponse.json({
      success: true,
      isValidToken: isValid || scopes.length > 0 || discoveredWabas.length > 0,
      appId,
      scopes,
      missingScopes,
      hasRequiredScopes: missingScopes.length === 0,
      wabas: discoveredWabas,
      totalPhoneNumbers,
      errorHint: totalPhoneNumbers === 0 ? metaFetchError : null,
    });
  } catch (error: unknown) {
    const msg = error instanceof Error ? error.message : 'Failed to discover WhatsApp credentials';
    console.error('[whatsapp/discover] Fatal error:', error);
    return NextResponse.json({ error: msg }, { status: 500 });
  }
}
