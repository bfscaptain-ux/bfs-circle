import { NextResponse } from 'next/server'
import { createClient } from '@supabase/supabase-js'
import {
  findSilentConversations,
  dispatchFollowUpNudge,
} from '@/lib/ai/silent-followup'

let _adminClient: any = null
function supabaseAdmin() {
  if (!_adminClient) {
    _adminClient = createClient(
      process.env.NEXT_PUBLIC_SUPABASE_URL!,
      process.env.SUPABASE_SERVICE_ROLE_KEY!
    )
  }
  return _adminClient
}

/**
 * GET /api/cron/follow-up
 * Triggered by cron job every 30-60 minutes during business hours (10 AM to 6 PM IST).
 * Re-engages leads that went silent for >= 2 hours.
 */
export async function GET(request: Request) {
  try {
    const authHeader = request.headers.get('authorization')
    const cronSecret = process.env.CRON_SECRET

    // Optional secret check if configured
    if (cronSecret && authHeader !== `Bearer ${cronSecret}`) {
      return NextResponse.json({ error: 'Unauthorized' }, { status: 401 })
    }

    const db = supabaseAdmin()

    // Fetch active WhatsApp configs to know which accounts to process
    const { data: configs, error: configErr } = await db
      .from('whatsapp_config')
      .select('account_id, user_id')

    if (configErr || !configs || configs.length === 0) {
      return NextResponse.json({ status: 'no_active_configs' })
    }

    let totalNudgesSent = 0

    for (const conf of configs) {
      const candidates = await findSilentConversations(db, conf.account_id, 2)
      for (const candidate of candidates) {
        const sent = await dispatchFollowUpNudge({
          ...candidate,
          configOwnerUserId: conf.user_id,
        })
        if (sent) totalNudgesSent++
      }
    }

    return NextResponse.json({
      status: 'success',
      total_nudges_sent: totalNudgesSent,
    })
  } catch (err) {
    console.error('[cron/follow-up] error running silent follow-ups:', err)
    return NextResponse.json(
      { error: (err as Error)?.message || 'Internal Server Error' },
      { status: 500 }
    )
  }
}
