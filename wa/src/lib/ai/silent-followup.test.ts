import { describe, it, expect, vi, beforeEach } from 'vitest'
import { buildFollowUpNudge, dispatchFollowUpNudge } from './silent-followup'

const mockEngineSendText = vi.fn()

vi.mock('@/lib/flows/meta-send', () => ({
  engineSendText: (args: unknown) => mockEngineSendText(args),
}))

describe('buildFollowUpNudge', () => {
  it('builds warm personal nudge with customer name', () => {
    const text = buildFollowUpNudge('Rahul')
    expect(text).toContain('Namaste Rahul ji!')
    expect(text).toContain('loan query ke silsile mein')
  })

  it('builds polite generic nudge when name is null', () => {
    const text = buildFollowUpNudge(null)
    expect(text).toContain('Namaste ji!')
  })
})

describe('dispatchFollowUpNudge', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockEngineSendText.mockResolvedValue({ whatsapp_message_id: 'm-fup-1' })
  })

  it('sends nudge text via engineSendText', async () => {
    const success = await dispatchFollowUpNudge({
      conversationId: 'c-1',
      contactId: 'ct-1',
      contactName: 'Annuuujjj',
      accountId: 'acc-1',
      configOwnerUserId: 'u-1',
      lastMessageAt: new Date().toISOString(),
    })

    expect(success).toBe(true)
    expect(mockEngineSendText).toHaveBeenCalledWith(
      expect.objectContaining({
        conversationId: 'c-1',
        text: expect.stringContaining('Annuuujjj ji'),
      }),
    )
  })
})
