import { motion } from 'motion/react'
import { dateTime } from '../lib/format'
import type { Order, OrderStatus } from '../lib/types'
import { CheckIcon } from './Icons'
import { easeApple } from './Motion'

// The normal journey of an order (same steps as the PHP tracking page).
const steps: { status: OrderStatus; label: string; detail: string }[] = [
  { status: 'pending_payment', label: 'Order placed', detail: 'We have your order.' },
  { status: 'confirmed', label: 'Confirmed', detail: 'Payment received; your date is booked.' },
  { status: 'preparing', label: 'Preparing', detail: 'Our kitchen is cooking your buffet.' },
  { status: 'out_for_delivery', label: 'Out for delivery', detail: 'On the way, with set-up crew.' },
  { status: 'delivered', label: 'Delivered', detail: 'Set up and ready. Enjoy!' },
]

export function StatusTimeline({ order }: { order: Order }) {
  // When each step was last reached, from the history the staff area writes.
  const reachedAt = new Map<string, string>()
  for (const e of order.history) reachedAt.set(e.status, e.changedAt)

  const current = Math.max(
    0,
    steps.findIndex((s) => s.status === order.status),
  )
  const progress = current / (steps.length - 1)

  return (
    <div>
      <div className="relative mb-8 h-1.5 overflow-hidden rounded-full bg-mist" aria-hidden="true">
        <motion.div
          className="absolute inset-y-0 left-0 rounded-full bg-accent"
          initial={false}
          animate={{ width: `${Math.max(4, progress * 100)}%` }}
          transition={{ duration: 0.9, ease: easeApple }}
        />
      </div>
      <ol className="space-y-0">
        {steps.map((step, i) => {
          const done = i < current || (i === current && order.status === 'delivered')
          const active = i === current && order.status !== 'delivered'
          const at = reachedAt.get(step.status)
          return (
            <li key={step.status} className="relative flex gap-4 pb-7 last:pb-0">
              {i < steps.length - 1 && (
                <span
                  className={`absolute left-[15px] top-8 h-[calc(100%-2rem)] w-0.5 transition-colors duration-700 ${
                    i < current ? 'bg-accent' : 'bg-line/70'
                  }`}
                  aria-hidden="true"
                />
              )}
              <motion.span
                layout
                className={`relative z-10 grid h-8 w-8 flex-none place-items-center rounded-full border-2 transition-colors duration-500 ${
                  done
                    ? 'border-accent bg-accent text-white'
                    : active
                      ? 'border-accent bg-white text-accent'
                      : 'border-line bg-white text-transparent'
                }`}
                aria-hidden="true"
              >
                {done && (
                  <motion.span initial={{ scale: 0 }} animate={{ scale: 1 }} transition={{ type: 'spring', stiffness: 400, damping: 18 }}>
                    <CheckIcon width={16} height={16} strokeWidth={2.4} />
                  </motion.span>
                )}
                {active && (
                  <>
                    <span className="h-2.5 w-2.5 rounded-full bg-accent" />
                    <motion.span
                      className="absolute inset-[-6px] rounded-full border-2 border-accent/40"
                      animate={{ scale: [0.85, 1.25], opacity: [0.8, 0] }}
                      transition={{ duration: 1.6, repeat: Infinity, ease: 'easeOut' }}
                    />
                  </>
                )}
              </motion.span>
              <div className="pt-0.5">
                <p className={`font-semibold ${done || active ? 'text-ink' : 'text-muted'}`}>
                  {step.label}
                  {active && <span className="sr-only"> (current step)</span>}
                  {done && <span className="sr-only"> (done)</span>}
                </p>
                <p className="text-[14px] text-muted">
                  {at && (done || active) ? `${dateTime(at)} · ` : ''}
                  {step.detail}
                </p>
              </div>
            </li>
          )
        })}
      </ol>
    </div>
  )
}
