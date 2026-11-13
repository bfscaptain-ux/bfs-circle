import { describe, it, expect, vi, beforeEach } from 'vitest'
import { extractLoanDetails, syncLeadToMainCrm } from './crm-sync'

describe('extractLoanDetails', () => {
  it('extracts crore amounts correctly', () => {
    const details = extractLoanDetails('7 cr ka lone chahiye home loan ke liye')
    expect(details.loanAmount).toBe('₹7 Crore')
    expect(details.loanType).toBe('Home Loan')
  })

  it('extracts lakh amounts and business loan', () => {
    const details = extractLoanDetails('25 lakh business loan chahiye shop ke liye')
    expect(details.loanAmount).toBe('₹25 Lakh')
    expect(details.loanType).toBe('Business Loan')
  })

  it('extracts CIBIL score if mentioned', () => {
    const details = extractLoanDetails('Mera cibil 810 he')
    expect(details.cibil).toBe('810')
  })
})

describe('syncLeadToMainCrm', () => {
  const originalFetch = global.fetch

  beforeEach(() => {
    global.fetch = vi.fn()
  })

  it('sends POST request to crm.bfsfin.cloud/api_wa_lead.php', async () => {
    ;(global.fetch as any).mockResolvedValue({
      ok: true,
      json: () => Promise.resolve({ success: true, lead_id: 1234 }),
    })

    const result = await syncLeadToMainCrm({
      name: 'Annuuujjj',
      phone: '9528896338',
      loan_amount: '₹7 Crore',
      loan_type: 'Home Loan',
      notes: 'CIBIL: 810',
    })

    expect(result.success).toBe(true)
    expect(result.lead_id).toBe(1234)
    expect(global.fetch).toHaveBeenCalledWith(
      expect.stringContaining('/api_wa_lead.php'),
      expect.objectContaining({
        method: 'POST',
        body: expect.stringContaining('7 Crore'),
      }),
    )
  })

  it('handles fetch failure gracefully without throwing', async () => {
    ;(global.fetch as any).mockRejectedValue(new Error('Network offline'))

    const result = await syncLeadToMainCrm({
      name: 'Test',
      phone: '919999999999',
    })

    expect(result.success).toBe(false)
    expect(result.message).toContain('Network offline')
  })
})
