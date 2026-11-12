/**
 * Shared contact-name resolution and template-spacing helpers used across
 * AI Agent auto-replies, Inbox AI drafts, Flows, and Automations so every
 * customer-facing surface addresses the customer consistently by name.
 */

export interface UsableContactName {
  fullName: string
  firstName: string
}

const PLACEHOLDER_NAMES = new Set([
  'unknown',
  'guest',
  'user',
  'customer',
  'whatsapp user',
  'na',
  'n/a',
  'none',
  'null',
  'undefined',
  'test',
])

const HONORIFIC_PREFIXES = new Set([
  'mr',
  'mr.',
  'mrs',
  'mrs.',
  'ms',
  'ms.',
  'miss',
  'dr',
  'dr.',
  'shri',
  'smt',
  'smt.',
  'er',
  'er.',
  'prof',
  'prof.',
])

function digitsOnly(value: string): string {
  return value.replace(/\D/g, '')
}

/**
 * Extract a usable `{ fullName, firstName }` from a CRM contact record,
 * returning `null` if the stored name is empty, a generic placeholder,
 * or just the customer's phone number.
 */
export function resolveUsableContactName(
  rawName: string | null | undefined,
  rawPhone?: string | null,
): UsableContactName | null {
  if (!rawName || typeof rawName !== 'string') return null

  const cleaned = rawName
    .replace(/^[\s~._\-–—•|]+|[\s~._\-–—•|]+$/g, '')
    .replace(/\s+/g, ' ')
    .trim()

  if (!cleaned) return null
  if (PLACEHOLDER_NAMES.has(cleaned.toLowerCase())) return null

  // Must contain at least one letter (Latin, Devanagari, etc.)
  if (!/\p{L}/u.test(cleaned)) return null

  // Reject values that are predominantly phone numbers
  const nameDigits = digitsOnly(cleaned)
  if (nameDigits.length >= 7) {
    const phoneDigits = rawPhone ? digitsOnly(rawPhone) : ''
    if (phoneDigits && (nameDigits === phoneDigits || phoneDigits.endsWith(nameDigits) || nameDigits.endsWith(phoneDigits))) {
      return null
    }
    const letterCount = (cleaned.match(/\p{L}/gu) ?? []).length
    if (letterCount < 3) return null
  }

  const tokens = cleaned.split(' ').filter(Boolean)
  if (tokens.length === 0) return null

  let firstName = tokens[0]
  if (
    tokens.length > 1 &&
    HONORIFIC_PREFIXES.has(tokens[0].toLowerCase()) &&
    /\p{L}/u.test(tokens[1])
  ) {
    firstName = `${tokens[0]} ${tokens[1]}`
  }

  return {
    fullName: cleaned,
    firstName,
  }
}

/**
 * Clean up awkward double horizontal spaces or space-before-punctuation
 * caused when an optional name placeholder resolves to empty string `""`.
 * Preserves line breaks (`\n`).
 */
export function collapseEmptyVarSpaces(text: string): string {
  return text
    .split('\n')
    .map((line) =>
      line
        .replace(/[ \t]{2,}/g, ' ')
        .replace(/ +([,!?।])/g, '$1')
        .trim(),
    )
    .join('\n')
}
