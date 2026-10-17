import { AiError, type ProviderResult } from '../types'
import { MAX_OUTPUT_TOKENS } from '../defaults'
import {
  mergeConsecutive,
  normalizeUsage,
  providerHttpError,
  toNetworkError,
  type ProviderArgs,
} from './shared'

interface GeminiResponse {
  candidates?: {
    content?: {
      parts?: { text?: string }[]
      role?: string
    }
    finishReason?: string
  }[]
  usageMetadata?: {
    promptTokenCount?: number
    candidatesTokenCount?: number
    totalTokenCount?: number
  }
  error?: {
    code?: number
    message?: string
    status?: string
  }
}

/**
 * Call Google Gemini's generateContent endpoint with the caller's own key.
 * Returns raw assistant text + token usage.
 */
export async function generateGemini(args: ProviderArgs): Promise<ProviderResult> {
  const { apiKey, model, systemPrompt, messages, timeoutMs } = args
  const cleanModel = (model || 'gemini-3.6-flash').replace(/^gemini:/, '')
  const url = `https://generativelanguage.googleapis.com/v1beta/models/${cleanModel}:generateContent?key=${apiKey}`

  const contents = mergeConsecutive(messages).map((m) => ({
    role: m.role === 'assistant' ? 'model' : 'user',
    parts: [{ text: m.content }],
  }))

  const payload = {
    ...(systemPrompt
      ? {
          systemInstruction: {
            parts: [{ text: systemPrompt }],
          },
        }
      : {}),
    contents,
    generationConfig: {
      maxOutputTokens: MAX_OUTPUT_TOKENS,
    },
  }

  let res: Response | null = null
  let attempts = 0
  const maxAttempts = 3

  while (attempts < maxAttempts) {
    attempts++
    try {
      res = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(payload),
        signal: AbortSignal.timeout(timeoutMs),
      })
    } catch (err) {
      if (attempts >= maxAttempts) throw toNetworkError(err)
      await new Promise((r) => setTimeout(r, 1500))
      continue
    }

    if (res.status === 429 && attempts < maxAttempts) {
      // Free tier rate limit bucket brief cooldown: wait 2.5s and retry
      await new Promise((r) => setTimeout(r, 2500))
      continue
    }
    break
  }

  if (!res || !res.ok) {
    throw await providerHttpError('Google Gemini', res!)
  }

  const data = (await res.json().catch(() => null)) as GeminiResponse | null
  if (data?.error) {
    throw new AiError(data.error.message || 'Gemini API error', {
      code: 'provider_error',
      status: data.error.code,
    })
  }

  const candidate = data?.candidates?.[0]
  const text = candidate?.content?.parts
    ?.map((p) => p.text || '')
    .join('')
    .trim()

  if (!text) {
    throw new AiError('Google Gemini returned an empty response.', {
      code: 'empty_response',
    })
  }

  const usage = normalizeUsage({
    prompt: data?.usageMetadata?.promptTokenCount,
    completion: data?.usageMetadata?.candidatesTokenCount,
    total: data?.usageMetadata?.totalTokenCount,
  })

  return { text, usage }
}
