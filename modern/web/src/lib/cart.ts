import { useSyncExternalStore } from 'react'
import { cents } from './format'
import { load, save } from './storage'

// The cart lives in the browser (localStorage), unlike the PHP site's session
// cart. Prices shown here are for display only: the API re-prices every line
// from the database when the order is placed.

export interface CartLine {
  id: string
  packageId: number
  name: string
  image: string
  imageAlt: string
  pricePerPax: number
  minPax: number
  guests: number
  dishIds: number[]
  dishNames: string[]
}

const KEY = 'makanco.cart'
let lines: CartLine[] = load<CartLine[]>(KEY, [])
let drawerOpen = false
const listeners = new Set<() => void>()

function emit() {
  listeners.forEach((fn) => fn())
}

function setLines(next: CartLine[]) {
  lines = next
  save(KEY, lines)
  emit()
}

function subscribe(fn: () => void) {
  listeners.add(fn)
  return () => listeners.delete(fn)
}

// Keep tabs in sync when the cart changes in another tab.
if (typeof window !== 'undefined') {
  window.addEventListener('storage', (e) => {
    if (e.key === KEY) {
      lines = load<CartLine[]>(KEY, [])
      emit()
    }
  })
}

export const cart = {
  add(line: Omit<CartLine, 'id'>) {
    setLines([...lines, { ...line, id: crypto.randomUUID() }])
  },
  setGuests(id: string, guests: number) {
    setLines(lines.map((l) => (l.id === id ? { ...l, guests } : l)))
  },
  remove(id: string) {
    setLines(lines.filter((l) => l.id !== id))
  },
  clear() {
    setLines([])
  },
  open() {
    drawerOpen = true
    emit()
  },
  close() {
    drawerOpen = false
    emit()
  },
}

export function useCart() {
  return useSyncExternalStore(subscribe, () => lines)
}

export function useCartOpen() {
  return useSyncExternalStore(subscribe, () => drawerOpen)
}

export function lineTotal(line: Pick<CartLine, 'pricePerPax' | 'guests'>) {
  return (cents(line.pricePerPax) * line.guests) / 100
}

export function cartTotals(items: CartLine[], deliveryFee: number, freeDeliveryMin: number) {
  const subtotal = items.reduce((sum, l) => sum + cents(l.pricePerPax) * l.guests, 0) / 100
  const delivery = subtotal === 0 || subtotal >= freeDeliveryMin ? 0 : deliveryFee
  return { subtotal, delivery, total: subtotal + delivery }
}
