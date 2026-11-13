/**
 * Night Mode / Business Hours management for WhatsApp CRM.
 * Office timing: 9:30 AM to 7:00 PM IST (Indian Standard Time, UTC+5:30), Mon-Sat.
 * Off-hours: Before 9:30 AM, after 7:00 PM, or all day Sunday.
 */

export interface BusinessHoursResult {
  isOffHours: boolean
  istTimeString: string
  dayName: string
}

/**
 * Returns true if the given date (default current time) is outside IST business hours.
 */
export function isOutsideBusinessHours(date = new Date()): boolean {
  // IST is UTC + 5:30
  const istOffset = 5.5 * 60 * 60 * 1000
  const istDate = new Date(date.getTime() + istOffset)

  const day = istDate.getUTCDay() // 0 = Sunday, 1 = Monday, ..., 6 = Saturday
  const hours = istDate.getUTCHours()
  const minutes = istDate.getUTCMinutes()
  const timeInHours = hours + minutes / 60

  // Sunday is completely off-hours
  if (day === 0) return true

  // Mon-Sat: Business hours are 9:30 AM (9.5) to 7:00 PM (19.0)
  return timeInHours < 9.5 || timeInHours >= 19.0
}

/**
 * Build the polite, professional Hindi/Hinglish out-of-office message.
 */
export function buildNightModeMessage(customerFirstName?: string | null): string {
  const greeting = customerFirstName ? `${customerFirstName} ji` : 'ji'
  return (
    `Namaste ${greeting}! 🙏 Bhardwaj Financial Services mein sampark karne ke liye dhanyawad.\n\n` +
    `Abhi hamara office band hai (Office timing: subah 9:30 AM se shaam 7:00 PM, Somwar se Shanivar).\n\n` +
    `Aapka message safely record ho gaya hai — kal subah 10:00 AM hamare Senior Loan Advisor priority par aapse connect karenge.`
  )
}
