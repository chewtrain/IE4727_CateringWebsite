import type { CustomerDetails, CustomerField, Rules } from './types'

// Client-side checks with the same rules and messages as the Go API (validate.go)
// and the PHP site. They give instant feedback; the server checks everything again.

const nameRe = /^[A-Za-z][A-Za-z .'/-]{1,59}$/
const emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
const phoneRe = /^[689]\d{7}$/
export const orderRefRe = /^MC-[A-Z0-9]{6}$/

export function validEmail(email: string) {
  return email.length <= 120 && emailRe.test(email)
}

export function validPostalCode(code: string) {
  if (!/^\d{6}$/.test(code)) return false
  const sector = Number(code.slice(0, 2))
  return sector >= 1 && sector <= 82 && sector !== 74
}

function byteLength(s: string) {
  return new TextEncoder().encode(s).length
}

export function normalise(d: CustomerDetails): CustomerDetails {
  return {
    ...d,
    name: d.name.trim(),
    email: d.email.trim().toLowerCase(),
    emailConfirm: d.emailConfirm.trim().toLowerCase(),
    phone: d.phone.trim().replace(/ /g, ''),
    address: d.address.trim(),
    postalCode: d.postalCode.trim(),
    notes: d.notes.trim(),
  }
}

export function validateCustomer(raw: CustomerDetails, rules: Rules) {
  const d = normalise(raw)
  const errors: Partial<Record<CustomerField, string>> = {}
  if (!nameRe.test(d.name)) errors.name = 'Enter your full name using letters only.'
  if (!validEmail(d.email)) errors.email = 'Enter a valid email address.'
  else if (d.email !== d.emailConfirm) errors.emailConfirm = 'The two email addresses do not match.'
  if (!phoneRe.test(d.phone)) errors.phone = 'Enter an 8-digit Singapore number starting with 6, 8 or 9.'
  const addressLength = byteLength(d.address)
  if (addressLength < 5 || addressLength > 200) errors.address = 'Enter the delivery address (block, street and unit).'
  if (!validPostalCode(d.postalCode)) errors.postalCode = 'Enter a valid 6-digit Singapore postal code.'
  // ISO dates compare correctly as strings.
  if (!/^\d{4}-\d{2}-\d{2}$/.test(d.eventDate) || d.eventDate < rules.earliestDate || d.eventDate > rules.latestDate) {
    errors.eventDate = 'Choose a date within the booking window shown.'
  }
  if (!rules.timeSlots.includes(d.timeSlot)) errors.timeSlot = 'Choose a delivery time slot.'
  if (byteLength(d.notes) > 500) errors.notes = 'Keep notes under 500 characters.'
  if (!d.terms) errors.terms = 'Please accept the delivery terms.'
  return errors
}
