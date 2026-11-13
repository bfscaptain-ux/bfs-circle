/**
 * Speech-to-Text Transcription for WhatsApp Voice Notes using Google Gemini.
 * Gemini natively supports audio/ogg, audio/mp3, audio/wav, and audio/aac.
 */

export interface TranscribeArgs {
  /** Base64-encoded audio bytes */
  audioBase64: string
  /** MIME type of the audio (e.g. 'audio/ogg', 'audio/ogg; codecs=opus') */
  mimeType?: string
  apiKey: string
  model?: string
  timeoutMs?: number
}

/**
 * Normalizes WhatsApp audio MIME types for Gemini.
 * Drops parameter suffixes like '; codecs=opus'.
 */
export function normalizeAudioMimeType(rawMime?: string): string {
  if (!rawMime) return 'audio/ogg'
  const clean = rawMime.split(';')[0].trim().toLowerCase()
  if (['audio/ogg', 'audio/opus', 'audio/mp4', 'audio/mpeg', 'audio/wav', 'audio/aac'].includes(clean)) {
    return clean === 'audio/opus' ? 'audio/ogg' : clean
  }
  return 'audio/ogg'
}

/**
 * Transcribe a WhatsApp audio voice message using Gemini's native audio capability.
 */
export async function transcribeAudioWithGemini(args: TranscribeArgs): Promise<string | null> {
  const { audioBase64, mimeType, apiKey, model } = args
  if (!audioBase64 || !apiKey) return null

  const targetMime = normalizeAudioMimeType(mimeType)
  const cleanModel = (model || 'gemini-2.0-flash').replace(/^gemini:/, '')
  const url = `https://generativelanguage.googleapis.com/v1beta/models/${cleanModel}:generateContent?key=${apiKey}`

  const promptText =
    'Transcribe this WhatsApp audio voice note accurately word for word in the spoken language (Hindi, Hinglish, or English). ' +
    'Do not translate, do not summarize, and do not add quotation marks or preamble. Output only the exact transcribed speech.'

  const payload = {
    contents: [
      {
        parts: [
          { text: promptText },
          {
            inlineData: {
              mimeType: targetMime,
              data: audioBase64,
            },
          },
        ],
      },
    ],
    generationConfig: {
      temperature: 0.1,
      maxOutputTokens: 1024,
    },
  }

  try {
    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(payload),
      signal: AbortSignal.timeout(args.timeoutMs || 15000),
    })

    if (!res.ok) {
      console.warn(`[transcribe] Gemini audio transcription HTTP ${res.status}`)
      return null
    }

    const data = await res.json().catch(() => null)
    const text = data?.candidates?.[0]?.content?.parts?.[0]?.text
    return text ? text.trim() : null
  } catch (err) {
    console.warn('[transcribe] Gemini transcription request failed:', err)
    return null
  }
}
