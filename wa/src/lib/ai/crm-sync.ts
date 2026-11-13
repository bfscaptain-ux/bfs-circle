/**
 * Syncs qualified leads from WhatsApp conversations directly to the Main CRM (crm.bfsfin.cloud).
 */

export interface SyncLeadPayload {
  name: string
  phone: string
  loan_type?: string
  loan_amount?: string
  city?: string
  notes?: string
  contact_id?: string
}

export interface SyncLeadResult {
  success: boolean
  message?: string
  lead_id?: number
}

/**
 * Extract loan amount string (e.g. "7 Crore", "25 Lakh") from conversation text.
 */
export function extractLoanDetails(text: string): {
  loanAmount?: string
  loanType?: string
  cibil?: string
} {
  const result: { loanAmount?: string; loanType?: string; cibil?: string } = {}

  // Amount extraction
  const croreMatch = text.match(/(\d+(?:\.\d+)?)\s*(?:cr|crore|karod)/i)
  if (croreMatch) {
    result.loanAmount = `₹${croreMatch[1]} Crore`
  } else {
    const lakhMatch = text.match(/(\d+(?:\.\d+)?)\s*(?:lakh|lac|lacs)/i)
    if (lakhMatch) {
      result.loanAmount = `₹${lakhMatch[1]} Lakh`
    }
  }

  // Loan type extraction
  if (/\b(home loan|house loan|makan|flat)\b/i.test(text)) {
    result.loanType = 'Home Loan'
  } else if (/\b(business loan|dukaan|shop|vyapar)\b/i.test(text)) {
    result.loanType = 'Business Loan'
  } else if (/\b(lap|property loan|plot loan)\b/i.test(text)) {
    result.loanType = 'Loan Against Property'
  } else if (/\b(personal loan)\b/i.test(text)) {
    result.loanType = 'Personal Loan'
  }

  // CIBIL extraction
  const cibilMatch = text.match(/\b(?:cibil|cibil score)\s*(?:is|hai|he)?\s*[:=]?\s*(\d{3})\b/i)
  if (cibilMatch) {
    result.cibil = cibilMatch[1]
  }

  return result
}

/**
 * Push or update the lead record in the Main CRM (crm.bfsfin.cloud).
 */
export async function syncLeadToMainCrm(payload: SyncLeadPayload): Promise<SyncLeadResult> {
  const baseUrl = process.env.NEXT_PUBLIC_CRM_URL || 'https://crm.bfsfin.cloud'
  const endpoint = `${baseUrl}/api_wa_lead.php`

  try {
    const res = await fetch(endpoint, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: JSON.stringify({
        name: payload.name,
        phone: payload.phone,
        loan_type: payload.loan_type || 'Loan Inquiry',
        loan_amount: payload.loan_amount || '',
        city: payload.city || 'Agra',
        notes: payload.notes || 'Captured via WhatsApp AI Assistant',
        contact_id: payload.contact_id,
      }),
      signal: AbortSignal.timeout(8000),
    })

    if (!res.ok) {
      return { success: false, message: `CRM sync failed with HTTP ${res.status}` }
    }

    const data = await res.json().catch(() => null)
    return {
      success: true,
      message: data?.message || 'Lead synced successfully',
      lead_id: data?.lead_id || data?.applicant?.id,
    }
  } catch (err) {
    const msg = (err as Error)?.message || String(err)
    console.warn('[crm-sync] sync to crm.bfsfin.cloud failed:', msg)
    return { success: false, message: msg }
  }
}
