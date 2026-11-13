import { describe, it, expect, vi, beforeEach } from 'vitest'
import { isVipLeadInquiry, sendVipLeadAlert } from './vip-alert'

const mockSendTextMessage = vi.fn()
const mockLoadAccountMetaCredentials = vi.fn()

vi.mock('@/lib/whatsapp/meta-api', () => ({
  sendTextMessage: (args: unknown) => mockSendTextMessage(args),
}))

vi.mock('@/lib/flows/meta-send', () => ({
  loadAccountMetaCredentials: (db: unknown, accountId: string) =>
    mockLoadAccountMetaCredentials(db, accountId),
}))

describe('isVipLeadInquiry', () => {
  it('detects 7cr / 7 crore keywords', () => {
    expect(isVipLeadInquiry('7 cr ka lone chahiye')).toBe(true)
    expect(isVipLeadInquiry('7crore loan')).toBe(true)
    expect(isVipLeadInquiry('1 Crore business loan')).toBe(true)
  })

  it('detects large numeric amounts >= 1 Crore', () => {
    expect(isVipLeadInquiry('mujhe 15000000 chahiye')).toBe(true)
    expect(isVipLeadInquiry('amount 70,00,0000')).toBe(true)
  })

  it('returns false for small or normal loan amounts', () => {
    expect(isVipLeadInquiry('mujhe 5 lakh ka loan chahiye')).toBe(false)
    expect(isVipLeadInquiry('hello sir')).toBe(false)
  })
})

describe('sendVipLeadAlert', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockLoadAccountMetaCredentials.mockResolvedValue({
      phoneNumberId: 'pn-123',
      accessToken: 'test-token',
    })
    mockSendTextMessage.mockResolvedValue({ messageId: 'msg-alert-1' })
  })

  it('dispatches structured alert WhatsApp message to admin number', async () => {
    const success = await sendVipLeadAlert({
      db: {} as any,
      accountId: 'acc-1',
      customerName: 'Annuuujjj',
      customerPhone: '919528896338',
      loanDetails: '₹7 Crore Home Loan',
      conversationId: 'conv-8f5e',
      alertPhone: '919258724227',
    })

    expect(success).toBe(true)
    expect(mockSendTextMessage).toHaveBeenCalledWith(
      expect.objectContaining({
        to: '919258724227',
        text: expect.stringContaining('VIP LEAD ALERT'),
      }),
    )
    expect(mockSendTextMessage).toHaveBeenCalledWith(
      expect.objectContaining({
        text: expect.stringContaining('Annuuujjj'),
      }),
    )
  })
})
