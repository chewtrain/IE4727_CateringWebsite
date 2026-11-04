// Display helpers. Amounts are SGD; dates from the API are Singapore local time.

export function money(amount: number) {
  return (
    '$' +
    amount.toLocaleString('en-SG', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })
  )
}

/** Round to cents the same way the server does. */
export function cents(amount: number) {
  return Math.round(amount * 100)
}

/** "2026-11-20" -> Date at local midnight (no time-zone shift). */
export function parseDate(value: string) {
  const [y, m, d] = value.split('-').map(Number)
  return new Date(y, m - 1, d)
}

/** "2026-11-04 20:02:00" -> Date in local time. */
export function parseDateTime(value: string) {
  const [date, time = '00:00:00'] = value.split(' ')
  const [y, m, d] = date.split('-').map(Number)
  const [hh, mm, ss] = time.split(':').map(Number)
  return new Date(y, m - 1, d, hh, mm, ss)
}

export function longDate(value: string) {
  return parseDate(value).toLocaleDateString('en-SG', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
}

export function shortDate(value: string) {
  return parseDate(value).toLocaleDateString('en-SG', {
    weekday: 'short',
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  })
}

export function dateTime(value: string) {
  return parseDateTime(value).toLocaleString('en-SG', {
    day: 'numeric',
    month: 'short',
    hour: 'numeric',
    minute: '2-digit',
  })
}

export function plural(n: number, one: string, many = one + 's') {
  return `${n} ${n === 1 ? one : many}`
}
