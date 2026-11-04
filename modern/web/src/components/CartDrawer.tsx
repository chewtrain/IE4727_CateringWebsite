import { useEffect, useRef } from 'react'
import { Link, useNavigate } from 'react-router'
import { AnimatePresence, motion } from 'motion/react'
import { imageUrl, useApi } from '../lib/api'
import { cart, cartTotals, lineTotal, useCart, useCartOpen } from '../lib/cart'
import { money, plural } from '../lib/format'
import type { Rules } from '../lib/types'
import { GuestStepper } from './GuestStepper'
import { CloseIcon, TrashIcon } from './Icons'
import { AnimatedMoney, easeApple } from './Motion'

/** Slide-out cart, available from every page. */
export function CartDrawer() {
  const open = useCartOpen()
  const lines = useCart()
  const { data: rules } = useApi<Rules>('/rules')
  const navigate = useNavigate()
  const closeRef = useRef<HTMLButtonElement>(null)
  const lastFocus = useRef<HTMLElement | null>(null)

  // Focus handling, Escape to close, and no page scrolling behind the drawer.
  useEffect(() => {
    if (!open) return
    lastFocus.current = document.activeElement as HTMLElement | null
    closeRef.current?.focus()
    const onKey = (e: KeyboardEvent) => e.key === 'Escape' && cart.close()
    document.addEventListener('keydown', onKey)
    const overflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    return () => {
      document.removeEventListener('keydown', onKey)
      document.body.style.overflow = overflow
      lastFocus.current?.focus()
    }
  }, [open])

  const totals = cartTotals(lines, rules?.deliveryFee ?? 30, rules?.freeDeliveryMin ?? 500)
  const freeMin = rules?.freeDeliveryMin ?? 500
  const toFree = Math.max(0, freeMin - totals.subtotal)

  return (
    <AnimatePresence>
      {open && (
        <div className="fixed inset-0 z-50">
          <motion.div
            className="absolute inset-0 bg-black/30 backdrop-blur-[2px]"
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            onClick={cart.close}
          />
          <motion.aside
            role="dialog"
            aria-modal="true"
            aria-labelledby="cart-title"
            className="absolute inset-y-0 right-0 flex w-full max-w-md flex-col bg-white shadow-2xl"
            initial={{ x: '100%' }}
            animate={{ x: 0 }}
            exit={{ x: '100%' }}
            transition={{ type: 'spring', stiffness: 380, damping: 40 }}
          >
            <div className="flex items-center justify-between border-b border-line/60 px-6 py-4">
              <h2 id="cart-title" className="text-xl font-semibold tracking-tight">
                Your cart
              </h2>
              <button
                ref={closeRef}
                type="button"
                onClick={cart.close}
                className="grid h-9 w-9 place-items-center rounded-full hover:bg-mist"
                aria-label="Close cart"
              >
                <CloseIcon />
              </button>
            </div>

            {lines.length === 0 ? (
              <div className="flex flex-1 flex-col items-center justify-center gap-4 px-8 text-center">
                <p className="text-lg font-medium">Your cart is empty.</p>
                <p className="text-muted">Choose a buffet package and pick your dishes to get started.</p>
                <Link to="/buffets" onClick={cart.close} className="btn-primary mt-2">
                  Browse buffets
                </Link>
              </div>
            ) : (
              <>
                <ul className="flex-1 space-y-4 overflow-y-auto px-6 py-5">
                  <AnimatePresence initial={false}>
                    {lines.map((line) => (
                      <motion.li
                        key={line.id}
                        layout
                        initial={{ opacity: 0, y: 12 }}
                        animate={{ opacity: 1, y: 0 }}
                        exit={{ opacity: 0, x: 40, transition: { duration: 0.25 } }}
                        transition={{ duration: 0.35, ease: easeApple }}
                        className="rounded-2xl bg-mist p-4"
                      >
                        <div className="flex gap-4">
                          <img
                            src={imageUrl(line.image)}
                            alt=""
                            className="h-16 w-16 flex-none rounded-xl object-cover"
                          />
                          <div className="min-w-0 flex-1">
                            <div className="flex items-start justify-between gap-2">
                              <p className="font-semibold">{line.name}</p>
                              <button
                                type="button"
                                onClick={() => cart.remove(line.id)}
                                className="-mr-1 -mt-1 grid h-8 w-8 flex-none place-items-center rounded-full text-muted hover:bg-white hover:text-danger"
                                aria-label={`Remove ${line.name}`}
                              >
                                <TrashIcon width={17} height={17} />
                              </button>
                            </div>
                            <p className="mt-0.5 line-clamp-2 text-[13px] text-muted">{line.dishNames.join(' · ')}</p>
                          </div>
                        </div>
                        <div className="mt-3 flex items-center justify-between">
                          <GuestStepper
                            id={`guests-${line.id}`}
                            label={`Guests for ${line.name}`}
                            compact
                            value={line.guests}
                            min={line.minPax}
                            max={rules?.maxGuests ?? 500}
                            onChange={(g) => cart.setGuests(line.id, g)}
                          />
                          <AnimatedMoney value={lineTotal(line)} className="font-semibold tabular-nums" />
                        </div>
                      </motion.li>
                    ))}
                  </AnimatePresence>
                </ul>

                <div className="border-t border-line/60 px-6 py-5">
                  <div className="mb-4" aria-live="polite">
                    {toFree > 0 ? (
                      <p className="text-[13px] text-muted">
                        Add <strong className="text-ink">{money(toFree)}</strong> more for free delivery.
                      </p>
                    ) : (
                      <p className="text-[13px] font-medium text-success">Free delivery and set-up included.</p>
                    )}
                    <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-mist">
                      <motion.div
                        className="h-full rounded-full bg-accent"
                        initial={false}
                        animate={{ width: `${Math.min(100, (totals.subtotal / freeMin) * 100)}%` }}
                        transition={{ duration: 0.6, ease: easeApple }}
                      />
                    </div>
                  </div>
                  <dl className="space-y-1.5 text-[15px]">
                    <div className="flex justify-between">
                      <dt className="text-muted">Subtotal ({plural(lines.length, 'package')})</dt>
                      <dd className="tabular-nums">{money(totals.subtotal)}</dd>
                    </div>
                    <div className="flex justify-between">
                      <dt className="text-muted">Delivery &amp; set-up</dt>
                      <dd className="tabular-nums">{totals.delivery ? money(totals.delivery) : 'Free'}</dd>
                    </div>
                    <div className="flex justify-between pt-2 text-lg font-semibold">
                      <dt>Total</dt>
                      <dd>
                        <AnimatedMoney value={totals.total} className="tabular-nums" />
                      </dd>
                    </div>
                  </dl>
                  <button
                    type="button"
                    className="btn-primary mt-5 w-full py-3"
                    onClick={() => {
                      cart.close()
                      navigate('/checkout')
                    }}
                  >
                    Check out
                  </button>
                </div>
              </>
            )}
          </motion.aside>
        </div>
      )}
    </AnimatePresence>
  )
}
