import { supabaseAdmin } from './admin-client'
import { loadAiConfig } from './config'
import { buildConversationContext } from './context'
import { retrieveKnowledge } from './knowledge'
import { generateReply } from './generate'
import { buildSystemPrompt } from './defaults'
import { buildHandoffSummary } from './handoff'
import { logAiUsage } from './usage'
import { latestUserMessage } from './query'
import { resolveUsableContactName } from '@/lib/contacts/contact-name'
import {
  engineSendText,
  loadAccountMetaCredentials,
} from '@/lib/flows/meta-send'
import { sendTypingIndicator } from '@/lib/whatsapp/meta-api'
import { checkRateLimit, RATE_LIMITS } from '@/lib/rate-limit'

interface DispatchArgs {
  /** Tenancy key — drives config, contact, and whatsapp_config lookups. */
  accountId: string
  conversationId: string
  contactId: string
  /** The account's WhatsApp config owner, used for the outbound send's
   *  audit columns (mirrors how the flow runner passes it through). */
  configOwnerUserId: string
  /** Meta's wamid of the customer message we're replying to. When set,
   *  a typing indicator (which also marks it read) is shown while the
   *  reply is generated. Optional so older callers keep working. */
  inboundMessageId?: string
}

interface InboundTracking {
  latestInboundId?: string
  receivedAt: number
}

// In-memory per-conversation debounce and active processing locks.
// Coalesces rapid multi-message bursts (e.g. "7cr", "?", "batao", "yrrr", "jaldi")
// into a single coordinated AI reply, preventing duplicate concurrent calls.
const pendingInboundByConv = new Map<string, InboundTracking>()
const activeProcessingConvs = new Set<string>()

/**
 * AI auto-reply for a freshly-arrived inbound message.
 *
 * Invoked from the WhatsApp webhook's `after()` block, only when no
 * deterministic flow consumed the message (flows win). Mirrors the flow
 * runner's contract: it owns its try/catch and NEVER throws — a failing
 * or slow LLM call must not affect the webhook's 200 to Meta.
 *
 * Eligibility gates (any → silent no-op):
 *   - AI off / auto-reply disabled for the account
 *   - a human agent is assigned (they own the thread)
 *   - auto-reply was disabled for this conversation (prior handoff)
 *   - the per-conversation reply cap is reached
 *   - there's nothing to reply to (or last message is already from assistant)
 *   - another inbound in the same burst is newer (debounced)
 *
 * The 24h WhatsApp session window is inherently open here — we're
 * reacting to a customer message that just landed — so no separate
 * window check is needed.
 */
export async function dispatchInboundToAiReply(
  args: DispatchArgs,
): Promise<void> {
  const {
    accountId,
    conversationId,
    contactId,
    configOwnerUserId,
    inboundMessageId,
  } = args

  // 1. Record this inbound as the latest pending for this conversation
  const now = Date.now()
  pendingInboundByConv.set(conversationId, {
    latestInboundId: inboundMessageId,
    receivedAt: now,
  })

  // 2. Debounce window: wait for rapid burst messages to finish arriving.
  // In test environment, bypass delay (0ms) so unit tests run fast.
  const debounceMs =
    process.env.NODE_ENV === 'test'
      ? 0
      : Number(process.env.AI_DEBOUNCE_MS || 2500)

  if (debounceMs > 0) {
    await new Promise((resolve) => setTimeout(resolve, debounceMs))
  }

  // 3. In-memory burst check: if a newer inbound arrived during debounce, yield to it.
  const latestPending = pendingInboundByConv.get(conversationId)
  if (
    inboundMessageId &&
    latestPending &&
    latestPending.latestInboundId &&
    latestPending.latestInboundId !== inboundMessageId
  ) {
    return
  }

  // 4. In-flight Lock: If an AI response is ALREADY being generated for this conversation,
  // wait up to 4s for it to finish.
  if (activeProcessingConvs.has(conversationId)) {
    let waitCount = 0
    while (activeProcessingConvs.has(conversationId) && waitCount < 8) {
      await new Promise((resolve) => setTimeout(resolve, 500))
      waitCount++
      const cur = pendingInboundByConv.get(conversationId)
      if (
        inboundMessageId &&
        cur &&
        cur.latestInboundId &&
        cur.latestInboundId !== inboundMessageId
      ) {
        return // yield to newer message
      }
    }
    if (activeProcessingConvs.has(conversationId)) {
      return // still locked, exit to prevent duplicate
    }
  }

  activeProcessingConvs.add(conversationId)

  try {
    const db = supabaseAdmin()

    // 5. Cross-process / DB check: verify no newer customer message exists in DB
    if (inboundMessageId) {
      try {
        const { data: latestCustomerMsg } = await db
          .from('messages')
          .select('message_id')
          .eq('conversation_id', conversationId)
          .eq('sender_type', 'customer')
          .order('created_at', { ascending: false })
          .limit(1)
          .maybeSingle()

        if (
          latestCustomerMsg &&
          latestCustomerMsg.message_id &&
          latestCustomerMsg.message_id !== inboundMessageId
        ) {
          // A newer message exists in the DB; let that invocation respond
          return
        }
      } catch {
        // Fall through safely if query is unmocked or transiently fails
      }
    }

    const config = await loadAiConfig(db, accountId)
    if (!config || !config.autoReplyEnabled) return

    // Deterministic, user-configured responders win over the LLM — the
    // caller already excludes messages a Flow consumed. Message-level
    // automations (`new_message_received` / `keyword_match`) are
    // dispatched independently for this same inbound and may send their
    // own reply, so if the account has any active one we stand down to
    // avoid double-texting the customer.
    const fifteenSecAgo = new Date(Date.now() - 15000).toISOString()
    const [{ data: recentRuns }, { data: conv, error: convErr }, messages] =
      await Promise.all([
        db
          .from('automation_logs')
          .select('id, steps_executed')
          .eq('account_id', accountId)
          .eq('contact_id', contactId)
          .gte('created_at', fifteenSecAgo)
          .limit(5),
        db
          .from('conversations')
          .select('assigned_agent_id, ai_autoreply_disabled, ai_reply_count')
          .eq('id', conversationId)
          .maybeSingle(),
        buildConversationContext(db, conversationId),
      ])

    if (automationSentOutbound(recentRuns)) return
    if (convErr || !conv) return
    if (conv.assigned_agent_id) return // a human owns this thread
    if (conv.ai_autoreply_disabled) return // handed off / turned off here
    // Cheap early-out; the authoritative cap check is the atomic claim below
    if (conv.ai_reply_count >= config.autoReplyMaxPerConversation) return
    if (messages.length === 0) return

    // 6. Anti-duplicate turn guard: If the last message is already from
    // the assistant (bot or agent), the turn has already been answered.
    if (messages[messages.length - 1].role !== 'user') {
      return
    }

    // Account-wide throttle on the shared BYO key.
    const acctLimit = checkRateLimit(
      `ai-autoreply:${accountId}`,
      RATE_LIMITS.aiAutoReplyAccount,
    )
    if (!acctLimit.success) {
      console.warn(
        `[ai auto-reply] account ${accountId} hit the per-account rate limit — skipping this inbound.`,
      )
      return
    }

    // Show "typing…" immediately in parallel with knowledge retrieval and
    // customer-name lookup so all independent I/O overlaps before calling the LLM.
    const [, knowledge, customerName] = await Promise.all([
      inboundMessageId
        ? showTypingIndicator(db, accountId, inboundMessageId)
        : Promise.resolve(),
      retrieveKnowledge(
        db,
        accountId,
        config,
        latestUserMessage(messages),
      ),
      (async () => {
        try {
          const { data: contactRow } = await db
            .from('contacts')
            .select('name, phone')
            .eq('id', contactId)
            .maybeSingle()
          return resolveUsableContactName(contactRow?.name, contactRow?.phone)
        } catch {
          return null
        }
      })(),
    ])

    const systemPrompt = buildSystemPrompt({
      userPrompt: config.systemPrompt,
      mode: 'auto_reply',
      knowledge,
      customerName,
    })

    const { text, handoff, usage } = await generateReply({
      config,
      systemPrompt,
      messages,
    })

    // Record token spend on the account's BYO key.
    void logAiUsage(db, {
      accountId,
      conversationId,
      mode: 'auto_reply',
      provider: config.provider,
      model: config.model,
      usage,
    })

    if (handoff || !text) {
      const summary = buildHandoffSummary({
        messages,
        replyCount: conv.ai_reply_count ?? 0,
      })
      const update: Record<string, unknown> = {
        ai_autoreply_disabled: true,
        ai_handoff_summary: summary,
      }
      if (config.handoffAgentId && !conv.assigned_agent_id) {
        update.assigned_agent_id = config.handoffAgentId
      }
      await db.from('conversations').update(update).eq('id', conversationId)

      // Send explanatory handoff text if any (supports natural multi-bubble)
      if (text) {
        await sendNaturalBubbles({
          db,
          accountId,
          userId: configOwnerUserId,
          conversationId,
          contactId,
          text,
          inboundMessageId,
        })
      }
      return
    }

    // Atomically claim a reply slot: the cap check + increment happen in
    // one UPDATE, so concurrent inbounds can never overshoot the cap.
    const { data: claimed, error: claimErr } = await db.rpc(
      'claim_ai_reply_slot',
      {
        conversation_id: conversationId,
        max_replies: config.autoReplyMaxPerConversation,
      },
    )
    if (claimErr) {
      console.error('[ai auto-reply] claim_ai_reply_slot failed:', claimErr)
      return
    }
    if (claimed !== true) return // lost the per-conversation cap race

    // Deliver reply using natural human WhatsApp bubbles
    await sendNaturalBubbles({
      db,
      accountId,
      userId: configOwnerUserId,
      conversationId,
      contactId,
      text,
      inboundMessageId,
    })
  } catch (err) {
    console.error('[ai auto-reply] dispatch failed:', err)
  } finally {
    activeProcessingConvs.delete(conversationId)
    const cur = pendingInboundByConv.get(conversationId)
    if (cur?.latestInboundId === inboundMessageId) {
      pendingInboundByConv.delete(conversationId)
    }
  }
}

/**
 * Deliver reply as 1 or at most 2 natural human-like WhatsApp bubbles.
 * If the model separated bubbles with `---`, send bubble 1, pause with a
 * natural typing indicator, then send bubble 2.
 */
async function sendNaturalBubbles(args: {
  db: ReturnType<typeof supabaseAdmin>
  accountId: string
  userId: string
  conversationId: string
  contactId: string
  text: string
  inboundMessageId?: string
}): Promise<void> {
  const { db, accountId, userId, conversationId, contactId, text, inboundMessageId } = args

  // Split on --- separator (at most 2 bubbles to maintain conversational focus)
  const rawBubbles = text
    .split(/\n\s*---\s*\n/)
    .map((b) => b.trim())
    .filter(Boolean)

  const bubbles = rawBubbles.slice(0, 2)
  if (bubbles.length === 0) return

  for (let i = 0; i < bubbles.length; i++) {
    if (i > 0) {
      // Natural human typing delay between bubbles
      if (inboundMessageId) {
        void showTypingIndicator(db, accountId, inboundMessageId)
      }
      await new Promise((resolve) => setTimeout(resolve, 1200))
    }

    await engineSendText({
      accountId,
      userId,
      conversationId,
      contactId,
      text: bubbles[i],
      aiGenerated: true,
    })
  }
}

const AUTOMATION_SEND_STEP_TYPES = new Set([
  'send_message',
  'send_buttons',
  'send_list',
  'send_template',
])

interface AutomationLogProbeRow {
  id: string
  steps_executed?: { step_type?: string; status?: string }[] | null
}

/**
 * Only treat an automation log as an auto-responder stand-down when it actually
 * executed an outbound send step; condition-only no-ops (`branch=no`) leave
 * the AI free to answer.
 */
function automationSentOutbound(
  rows: AutomationLogProbeRow[] | null | undefined,
): boolean {
  if (!rows || rows.length === 0) return false
  return rows.some((row) => {
    if (row.steps_executed == null) return true
    return (
      Array.isArray(row.steps_executed) &&
      row.steps_executed.some(
        (s) =>
          s?.status === 'success' &&
          Boolean(s?.step_type && AUTOMATION_SEND_STEP_TYPES.has(s.step_type)),
      )
    )
  })
}

/**
 * Best-effort "typing…" for the inbound we're about to answer. Swallows
 * every failure (no WhatsApp config, bad token, Meta 4xx) with a warning
 * — the indicator is cosmetic, the reply is not.
 */
async function showTypingIndicator(
  db: ReturnType<typeof supabaseAdmin>,
  accountId: string,
  inboundMessageId: string,
): Promise<void> {
  try {
    const { phoneNumberId, accessToken } = await loadAccountMetaCredentials(
      db,
      accountId,
    )
    await sendTypingIndicator({
      phoneNumberId,
      accessToken,
      messageId: inboundMessageId,
    })
  } catch (err) {
    console.warn('[ai auto-reply] typing indicator failed (continuing):', err)
  }
}
