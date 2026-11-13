import { describe, it, expect } from 'vitest'
import { isOutsideBusinessHours, buildNightModeMessage } from './night-mode'

describe('isOutsideBusinessHours', () => {
  it('returns true on Sunday regardless of time', () => {
    // 2026-11-15 is Sunday. 12:00 PM IST is 06:30 UTC.
    const sundayNoonUtc = new Date('2026-11-15T06:30:00Z')
    expect(isOutsideBusinessHours(sundayNoonUtc)).toBe(true)
  })

  it('returns false during business hours on a weekday (e.g. 2:00 PM IST)', () => {
    // 2026-11-13 is Friday. 14:00 IST is 08:30 UTC.
    const friday2pmUtc = new Date('2026-11-13T08:30:00Z')
    expect(isOutsideBusinessHours(friday2pmUtc)).toBe(false)
  })

  it('returns false right at 9:30 AM IST on a weekday', () => {
    // 09:30 IST is 04:00 UTC.
    const friday930amUtc = new Date('2026-11-13T04:00:00Z')
    expect(isOutsideBusinessHours(friday930amUtc)).toBe(false)
  })

  it('returns true before 9:30 AM IST (e.g. 8:00 AM IST)', () => {
    // 08:00 IST is 02:30 UTC.
    const friday8amUtc = new Date('2026-11-13T02:30:00Z')
    expect(isOutsideBusinessHours(friday8amUtc)).toBe(true)
  })

  it('returns true after 7:00 PM IST (e.g. 10:00 PM IST / 2:00 AM IST)', () => {
    // 22:00 IST is 16:30 UTC.
    const friday10pmUtc = new Date('2026-11-13T16:30:00Z')
    expect(isOutsideBusinessHours(friday10pmUtc)).toBe(true)

    // 02:00 IST next day is 20:30 UTC.
    const sat2amUtc = new Date('2026-11-13T20:30:00Z')
    expect(isOutsideBusinessHours(sat2amUtc)).toBe(true)
  })
})

describe('buildNightModeMessage', () => {
  it('builds warm personalized message with customer name', () => {
    const msg = buildNightModeMessage('Annuuujjj')
    expect(msg).toContain('Namaste Annuuujjj ji!')
    expect(msg).toContain('subah 9:30 AM se shaam 7:00 PM')
  })

  it('builds generic polite message when no name is provided', () => {
    const msg = buildNightModeMessage(null)
    expect(msg).toContain('Namaste ji!')
  })
})
