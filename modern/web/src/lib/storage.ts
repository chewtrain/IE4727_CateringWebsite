// localStorage can be unavailable (private windows, blocked site data), so every
// access is wrapped and the app keeps working with in-memory state instead.

export function load<T>(key: string, fallback: T): T {
  try {
    const raw = localStorage.getItem(key)
    return raw ? (JSON.parse(raw) as T) : fallback
  } catch {
    return fallback
  }
}

export function save(key: string, value: unknown) {
  try {
    localStorage.setItem(key, JSON.stringify(value))
  } catch {
    /* storage unavailable: state still lives in memory */
  }
}

/**
 * Orders this browser placed or looked up, with the email that unlocks them.
 * Mirrors rememberOrder() in the PHP site, which keeps the same list in the session.
 */
export interface RememberedOrder {
  ref: string
  email: string
  savedAt: number
}

const ORDERS_KEY = 'makanco.orders'

export function rememberOrder(ref: string, email: string) {
  const list = recentOrders().filter((o) => o.ref !== ref)
  list.unshift({ ref, email, savedAt: Date.now() })
  save(ORDERS_KEY, list.slice(0, 5))
}

export function recentOrders(): RememberedOrder[] {
  return load<RememberedOrder[]>(ORDERS_KEY, [])
}

export function emailForOrder(ref: string) {
  return recentOrders().find((o) => o.ref === ref)?.email
}
