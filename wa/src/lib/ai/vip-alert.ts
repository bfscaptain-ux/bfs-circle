import { sendTextMessage } from '@/lib/whatsapp/meta-api'
import { loadAccountMetaCredentials } from '@/lib/flows/meta-send'
import type { SupabaseClient } from '@supabase/supabase-js'

export interface VipAlertArgs {
  db: SupabaseClient
  accountId: string
  customerName?: string | null
  customerPhone?: string | null
  loanDetails: string
  conversationId: string
  alertPhone?: string
}

const HIGH_TICKET_REGEX = /\b(7\s*cr|7\s*crore|1\s*cr|1\s*crore|crore|karod|vip|bada\s*loan|high\s*ticket)\b/i

/**
 * Checks if a customer turn or context indicates a high-ticket / VIP loan inquiry.
 */
export function isVipLeadInquiry(text: string): boolean {
  if (!text) return false
  if (HIGH_TICKET_REGEX.test(text)) return true

  // Check for large numeric amounts >= 1,00,00,000 (1 Cr)
  const numbers = text.match(/\d[\d,.]*/g)
  if (numbers) {
    for (const numStr of numbers) {
      const cleaned = Number(numStr.replace(/,/g, ''))
      if (!isNaN(cleaned) && cleaned >= 10000000) {
        return true
      }
    }
  }
  return false
}

/**
 * Sends an instant WhatsApp notification to the Admin / Senior Loan Advisor phone.
 */
export async function sendVipLeadAlert(args: VipAlertArgs): Promise<boolean> {
  const {
    db,
    accountId,
    customerName,
    customerPhone,
    loanDetails,
    conversationId,
  } = args

  // Destination alert number (default to official BFS Senior Loan Desk: 919258724227)
  const targetPhone =
    args.alertPhone || process.env.ADMIN_ALERT_PHONE || '919258724227'

  try {
    const { phoneNumberId, accessToken } = await loadAccountMetaCredentials(
      db,
      accountId,
    )

    const alertMessage =
      `🚨 *VIP LEAD ALERT (BFS Circle)*\n\n` +
      `👤 *Customer:* ${customerName || 'WhatsApp User'}\n` +
      `📱 *Phone:* ${customerPhone || 'N/A'}\n` +
      `💰 *Requirement:* ${loanDetails}\n` +
      `🔗 *Chat Link:* https://wa.bfsfin.cloud/inbox?c=${conversationId}\n\n` +
      `⚡ *Action:* Please take over immediately on priority!`

    await sendTextMessage({
      phoneNumberId,
      accessToken,
      to: targetPhone,
      text: alertMessage,
    })
    return true
  } catch (err) {
    console.warn('[vip alert] failed to send VIP alert WhatsApp message:', err)
    return false
  }
}
