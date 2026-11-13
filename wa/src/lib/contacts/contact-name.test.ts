import { describe, it, expect } from 'vitest'
import {
  resolveUsableContactName,
  collapseEmptyVarSpaces,
} from './contact-name'

describe('resolveUsableContactName', () => {
  it('extracts fullName and firstName for normal names', () => {
    expect(resolveUsableContactName('Rahul Sharma', '919876543210')).toEqual({
      fullName: 'Rahul Sharma',
      firstName: 'Rahul',
    })
  })

  it('keeps honorific with next word when present', () => {
    expect(resolveUsableContactName('Dr. Amit Verma', '919876543210')).toEqual({
      fullName: 'Dr. Amit Verma',
      firstName: 'Dr. Amit',
    })
  })

  it('supports Devanagari / Hindi names', () => {
    expect(resolveUsableContactName('राहुल शर्मा', '919876543210')).toEqual({
      fullName: 'राहुल शर्मा',
      firstName: 'राहुल',
    })
  })

  it('normalizes Devanagari honorific abbreviations like एड0', () => {
    expect(resolveUsableContactName('एड0 प्रवीन भारद्वाज', '919258724227')).toEqual({
      fullName: 'एड0 प्रवीन भारद्वाज',
      firstName: 'एडवोकेट प्रवीन',
    })
  })

  it('rejects phone numbers stored as names', () => {
    expect(resolveUsableContactName('+91 9876543210', '919876543210')).toBeNull()
    expect(resolveUsableContactName('9876543210', '919876543210')).toBeNull()
  })

  it('rejects placeholder / blank / symbol-only values', () => {
    expect(resolveUsableContactName('', '919876543210')).toBeNull()
    expect(resolveUsableContactName('   ', '919876543210')).toBeNull()
    expect(resolveUsableContactName('Unknown', '919876543210')).toBeNull()
    expect(resolveUsableContactName('WhatsApp User', '919876543210')).toBeNull()
    expect(resolveUsableContactName('...', '919876543210')).toBeNull()
    expect(resolveUsableContactName('🙏🙏', '919876543210')).toBeNull()
  })

  it('strips leading/trailing tilde or punctuation artifacts', () => {
    expect(resolveUsableContactName('~ Priya Singh ~', '919876543210')).toEqual({
      fullName: 'Priya Singh',
      firstName: 'Priya',
    })
  })
})

describe('collapseEmptyVarSpaces', () => {
  it('removes double spaces and space-before-punctuation while preserving newlines', () => {
    const input = 'Namaste  ji!\nWelcome  , how can we help?'
    expect(collapseEmptyVarSpaces(input)).toBe(
      'Namaste ji!\nWelcome, how can we help?',
    )
  })
})
