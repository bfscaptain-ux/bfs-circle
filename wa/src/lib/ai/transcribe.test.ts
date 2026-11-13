import { describe, it, expect, vi, beforeEach } from 'vitest'
import { normalizeAudioMimeType, transcribeAudioWithGemini } from './transcribe'

describe('normalizeAudioMimeType', () => {
  it('strips parameters from audio/ogg; codecs=opus', () => {
    expect(normalizeAudioMimeType('audio/ogg; codecs=opus')).toBe('audio/ogg')
  })

  it('handles standard audio formats', () => {
    expect(normalizeAudioMimeType('audio/mp4')).toBe('audio/mp4')
    expect(normalizeAudioMimeType('audio/mpeg')).toBe('audio/mpeg')
    expect(normalizeAudioMimeType('audio/wav')).toBe('audio/wav')
  })

  it('defaults to audio/ogg for unknown or missing mime types', () => {
    expect(normalizeAudioMimeType(undefined)).toBe('audio/ogg')
    expect(normalizeAudioMimeType('application/octet-stream')).toBe('audio/ogg')
  })
})

describe('transcribeAudioWithGemini', () => {
  const originalFetch = global.fetch

  beforeEach(() => {
    global.fetch = vi.fn()
  })

  it('sends audioBase64 in inlineData and returns transcribed text', async () => {
    ;(global.fetch as any).mockResolvedValue({
      ok: true,
      json: () =>
        Promise.resolve({
          candidates: [
            {
              content: {
                parts: [{ text: 'Bhaiya mujhe 25 lakh ka loan chahiye' }],
              },
            },
          ],
        }),
    })

    const result = await transcribeAudioWithGemini({
      audioBase64: 'UklGRi...',
      mimeType: 'audio/ogg; codecs=opus',
      apiKey: 'test-key',
    })

    expect(result).toBe('Bhaiya mujhe 25 lakh ka loan chahiye')
    expect(global.fetch).toHaveBeenCalledWith(
      expect.stringContaining('models/gemini-2.0-flash:generateContent?key=test-key'),
      expect.objectContaining({
        method: 'POST',
        body: expect.stringContaining('audio/ogg'),
      }),
    )
  })

  it('returns null safely on HTTP failure', async () => {
    ;(global.fetch as any).mockResolvedValue({
      ok: false,
      status: 400,
    })

    const result = await transcribeAudioWithGemini({
      audioBase64: 'UklGRi...',
      apiKey: 'test-key',
    })

    expect(result).toBeNull()
  })
})
