import { useEffect, useState } from 'react'
import type { ApiErrorBody, CustomerDetails, Order, Package, Rules } from './types'

// AJAX client for the Go API. GET responses are cached for the session, so going
// back to a page is instant and the shared photo transition has its data ready.

export class ApiError extends Error {
  readonly status: number
  readonly body: ApiErrorBody

  constructor(status: number, body: ApiErrorBody) {
    super(body.error)
    this.status = status
    this.body = body
  }
}

async function request<T>(path: string, init?: RequestInit): Promise<T> {
  let res: Response
  try {
    res = await fetch(`/api${path}`, {
      ...init,
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...init?.headers },
    })
  } catch {
    throw new ApiError(0, { error: "We can't reach our server right now. Check your connection and try again." })
  }
  const body = await res.json().catch(() => null)
  if (!res.ok) {
    throw new ApiError(res.status, body ?? { error: `Unexpected error (${res.status}). Please try again.` })
  }
  return body as T
}

const pending = new Map<string, Promise<unknown>>()
const resolved = new Map<string, unknown>()

/** Fetch a GET endpoint once and remember the answer. */
export function cachedGet<T>(path: string): Promise<T> {
  let promise = pending.get(path) as Promise<T> | undefined
  if (!promise) {
    promise = request<T>(path).then(
      (data) => {
        resolved.set(path, data)
        return data
      },
      (err) => {
        pending.delete(path) // allow a retry
        throw err
      },
    )
    pending.set(path, promise)
  }
  return promise
}

export function peek<T>(path: string): T | undefined {
  return resolved.get(path) as T | undefined
}

/** Start loading something the user is likely to open next (e.g. on hover). */
export function prefetch(path: string) {
  cachedGet(path).catch(() => {})
}

interface Loadable<T> {
  data: T | undefined
  error: ApiError | undefined
  retry: () => void
}

/** React hook around cachedGet: cached data is available on the first render. */
export function useApi<T>(path: string | null): Loadable<T> {
  const [state, setState] = useState<{ path: string | null; data?: T; error?: ApiError }>(() => ({
    path,
    data: path ? peek<T>(path) : undefined,
  }))
  const [attempt, setAttempt] = useState(0)

  // Reset when the path changes (render-time update avoids a flash of old data).
  if (state.path !== path) {
    setState({ path, data: path ? peek<T>(path) : undefined })
  }

  useEffect(() => {
    if (!path) return
    let live = true
    cachedGet<T>(path).then(
      (data) => live && setState({ path, data }),
      (error: ApiError) => live && setState({ path, error }),
    )
    return () => {
      live = false
    }
  }, [path, attempt])

  return {
    data: state.path === path ? state.data : undefined,
    error: state.path === path ? state.error : undefined,
    retry: () => setAttempt((n) => n + 1),
  }
}

export const api = {
  rules: () => cachedGet<Rules>('/rules'),
  packages: () => cachedGet<Package[]>('/packages'),
  packagePath: (id: number | string) => `/packages/${id}`,

  createOrder: (customer: CustomerDetails, items: { packageId: number; guests: number; dishIds: number[] }[]) =>
    request<{ ref: string; total: number }>('/orders', {
      method: 'POST',
      body: JSON.stringify({ customer, items }),
    }),

  getOrder: (ref: string, email: string) =>
    request<Order>(`/orders/${encodeURIComponent(ref)}?email=${encodeURIComponent(email)}`),

  pay: (ref: string, email: string, outcome: 'success' | 'failed') =>
    request<{ order: Order; emailSent: boolean }>(`/orders/${encodeURIComponent(ref)}/payment`, {
      method: 'POST',
      body: JSON.stringify({ email, outcome }),
    }),

  eventsUrl: (ref: string, email: string) =>
    `/api/orders/${encodeURIComponent(ref)}/events?email=${encodeURIComponent(email)}`,
}

/** Photos live in the base version's images folder; the API serves them at /images. */
export function imageUrl(path: string) {
  return '/' + path.replace(/^\/+/, '')
}
