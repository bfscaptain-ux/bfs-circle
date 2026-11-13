import type { SupabaseClient } from '@supabase/supabase-js'
import { engineSendText } from '@/lib/flows/meta-send'
import { isOutsideBusinessHours } from './night-mode'

export interface FollowUpCandidate {
  conversationId: string
  contactId: string
  contactName?: string | null
  contactPhone?: string | null
  accountId: string
  configOwnerUserId: string
  lastMessageAt: string
}

/**
 * Builds the gentle, respectful follow-up nudge message.
 */
export function buildFollowUpNudge(customerName?: string | null): string {
  const greeting = customerName ? `${customerName} ji` : 'ji'
  return (
    `Namaste ${greeting}! 🙏\n\n` +
    `Aapke loan query ke silsile mein sampark kiya tha — kya aapko details samajhne ya eligibility check karne mein koi sahayata chahiye?\n\n` +
    `Agar koi bhi doubt ya sawal ho toh bejhijhak bataiye, main turant guide karta hoon!`
  )
}

/**
 * Finds conversations where customer has gone silent for >= 2 hours during daytime.
 */
export async function findSilentConversations(
  db: SupabaseClient,
  accountId: string,
  minSilenceHours = 2,
): Promise<FollowUpCandidate[]> {
  // Do not send follow-up nudges during night mode / off-hours
  if (isOutsideBusinessHours()) {
    return []
  }

  const now = Date.now()
  const minSilenceTime = new Date(now - minSilenceHours * 60 * 60 * 1000).toISOString()
  const maxSilenceTime = new Date(now - 23 * 60 * 60 * 1000).toISOString() // Stay within 24h Meta window

  const { data: convs, error } = await db
    .from('conversations')
    .select('id, contact_id, updated_at, ai_autoreply_disabled, assigned_agent_id, contacts(name, phone)')
    .eq('account_id', accountId)
    .eq('ai_autoreply_disabled', false)
    .is('assigned_agent_id', null)
    .lte('updated_at', minSilenceTime)
    .gte('updated_at', maxSilenceTime)
    .limit(20)

  if (error || !convs) return []

  const candidates: FollowUpCandidate[] = []

  for (const c of convs) {
    // Check if the very last message was from bot/agent
    const { data: lastMsg } = await db
      .from('messages')
      .select('sender_type, content_text, created_at')
      .eq('conversation_id', c.id)
      .order('created_at', { ascending: false })
      .limit(1)
      .maybeSingle()

    // Only nudge if the bot/agent spoke last and has not already sent a follow-up nudge
    if (
      lastMsg &&
      ['bot', 'agent'].includes(lastMsg.sender_type) &&
      !lastMsg.content_text?.includes('silsile mein sampark kiya tha')
    ) {
      const contactObj = c.contacts as { name?: string; phone?: string } | null
      candidates.push({
        conversationId: c.id,
        contactId: c.contact_id,
        contactName: contactObj?.name,
        contactPhone: contactObj?.phone,
        accountId,
        configOwnerUserId: '',
        lastMessageAt: lastMsg.created_at,
      })
    }
  }

  return candidates
}

/**
 * Executes follow-up nudge for a candidate conversation.
 */
export async function dispatchFollowUpNudge(
  args: FollowUpCandidate & { configOwnerUserId: string },
): Promise<boolean> {
  try {
    const text = buildFollowUpNudge(args.contactName)
    await engineSendText({
      accountId: args.accountId,
      userId: args.configOwnerUserId,
      conversationId: args.conversationId,
      contactId: args.contactId,
      text,
      aiGenerated: true,
    })
    return true
  } catch (err) {
    console.warn('[silent-followup] failed to send nudge:', err)
    return false
  }
}
