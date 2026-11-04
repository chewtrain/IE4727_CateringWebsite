import { useEffect, useState, type FormEvent } from 'react'
import { Link, useNavigate, useParams, useSearchParams } from 'react-router'
import { AnimatePresence, motion } from 'motion/react'
import { ApiError, api, imageUrl } from '../lib/api'
import { dateTime, longDate, money } from '../lib/format'
import { useTitle } from '../lib/hooks'
import { emailForOrder, recentOrders, rememberOrder } from '../lib/storage'
import type { Order } from '../lib/types'
import { orderRefRe, validEmail } from '../lib/validation'
import { Field, describedBy } from '../components/Field'
import { PageIntro, easeApple } from '../components/Motion'
import { StatusTimeline } from '../components/StatusTimeline'
import { resumePayment } from './Checkout'

export default function Track() {
  const { ref: refParam } = useParams()
  const [search] = useSearchParams()
  const navigate = useNavigate()
  const ref = refParam?.toUpperCase()

  // Links in our emails carry ?ref=...&email=...: remember them, then tidy the URL.
  useEffect(() => {
    const r = search.get('ref')?.trim().toUpperCase()
    const e = search.get('email')?.trim().toLowerCase()
    if (r && e && orderRefRe.test(r)) {
      rememberOrder(r, e)
      navigate(`/track/${r}`, { replace: true })
    }
  }, [search, navigate])

  const email = ref ? emailForOrder(ref) : undefined
  if (ref && email) return <LiveOrder key={ref} orderRef={ref} email={email} />
  return <Lookup initialRef={ref ?? search.get('ref') ?? ''} />
}

function Lookup({ initialRef }: { initialRef: string }) {
  useTitle('Track your order')
  const navigate = useNavigate()
  const [ref, setRef] = useState(initialRef.toUpperCase())
  const [email, setEmail] = useState('')
  const [errors, setErrors] = useState<{ ref?: string; email?: string; form?: string }>({})
  const [busy, setBusy] = useState(false)
  const recent = recentOrders()

  const submit = async (e: FormEvent) => {
    e.preventDefault()
    const r = ref.trim().toUpperCase()
    const em = email.trim().toLowerCase()
    const next: typeof errors = {}
    if (!orderRefRe.test(r)) next.ref = 'Order numbers look like MC-7K3QX2.'
    if (!validEmail(em)) next.email = 'Enter the email you used to order.'
    setErrors(next)
    if (next.ref || next.email) return
    setBusy(true)
    try {
      await api.getOrder(r, em)
      rememberOrder(r, em)
      navigate(`/track/${r}`)
    } catch (err) {
      setErrors({ form: (err as ApiError).message })
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="container-page grid gap-12 pt-16 sm:pt-20 lg:grid-cols-2 lg:items-center">
      <PageIntro>
        <p className="eyebrow">Order tracking</p>
        <h1 className="headline mt-3">Where's my buffet?</h1>
        <p className="mt-5 max-w-md text-lg text-muted">
          Enter your order number and email. Your order page updates live as our kitchen and delivery crew move it
          along.
        </p>
        <form onSubmit={submit} noValidate className="mt-10 max-w-md space-y-5">
          <AnimatePresence>
            {errors.form && (
              <motion.p
                role="alert"
                className="rounded-2xl bg-danger/8 px-4 py-3 text-[15px] text-danger"
                initial={{ opacity: 0, y: -6 }}
                animate={{ opacity: 1, y: 0 }}
                exit={{ opacity: 0 }}
              >
                {errors.form}
              </motion.p>
            )}
          </AnimatePresence>
          <Field id="ref" label="Order number" error={errors.ref}>
            <input
              id="ref"
              value={ref}
              onChange={(e) => setRef(e.target.value.toUpperCase())}
              placeholder="MC-7K3QX2"
              className="field-input font-mono uppercase"
              autoComplete="off"
              maxLength={9}
              {...describedBy('ref', errors.ref)}
            />
          </Field>
          <Field id="track-email" label="Email" error={errors.email}>
            <input
              id="track-email"
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              className="field-input"
              autoComplete="email"
              {...describedBy('track-email', errors.email)}
            />
          </Field>
          <button type="submit" disabled={busy} className="btn-primary w-full py-3">
            {busy ? 'Finding your order…' : 'Track order'}
          </button>
        </form>
        {recent.length > 0 && (
          <div className="mt-10 max-w-md">
            <p className="text-[13px] font-medium text-muted">Recent orders on this device</p>
            <ul className="mt-3 flex flex-wrap gap-2">
              {recent.map((o) => (
                <li key={o.ref}>
                  <Link to={`/track/${o.ref}`} className="inline-block rounded-full bg-mist px-4 py-2 font-mono text-[14px] hover:bg-line/50">
                    {o.ref}
                  </Link>
                </li>
              ))}
            </ul>
          </div>
        )}
      </PageIntro>
      <motion.div
        className="hidden aspect-[4/5] overflow-hidden rounded-card bg-mist lg:block"
        initial={{ opacity: 0, scale: 0.96 }}
        animate={{ opacity: 1, scale: 1 }}
        transition={{ duration: 0.9, ease: easeApple }}
      >
        <img src="/images/feature-fresh.jpg" alt="Steaming plates of stir-fried vegetables, tofu and rice" className="h-full w-full object-cover" />
      </motion.div>
    </div>
  )
}

type Connection = 'connecting' | 'live' | 'reconnecting'

function LiveOrder({ orderRef, email }: { orderRef: string; email: string }) {
  const navigate = useNavigate()
  const [order, setOrder] = useState<Order | null>(null)
  const [error, setError] = useState<ApiError | null>(null)
  const [connection, setConnection] = useState<Connection>('connecting')
  const [flash, setFlash] = useState<string | null>(null)
  useTitle(`Order ${orderRef}`)

  // Load the order, then keep a Server-Sent Events stream open. The API pushes the
  // order again whenever its status changes (for example, from the PHP staff area).
  useEffect(() => {
    let source: EventSource | null = null
    let cancelled = false
    let last: Order | null = null

    api
      .getOrder(orderRef, email)
      .then((o) => {
        if (cancelled) return
        last = o
        setOrder(o)
        source = new EventSource(api.eventsUrl(orderRef, email))
        source.addEventListener('order', (e) => {
          const next = JSON.parse((e as MessageEvent<string>).data) as Order
          if (last && last.status !== next.status) setFlash(`Status updated: ${next.statusLabel}`)
          last = next
          setOrder(next)
          setConnection('live')
        })
        source.addEventListener('open', () => setConnection('live'))
        source.addEventListener('error', () => setConnection('reconnecting'))
      })
      .catch((err: ApiError) => !cancelled && setError(err))

    return () => {
      cancelled = true
      source?.close()
    }
  }, [orderRef, email])

  useEffect(() => {
    if (!flash) return
    const t = setTimeout(() => setFlash(null), 6000)
    return () => clearTimeout(t)
  }, [flash])

  if (error) {
    return (
      <div className="container-page py-28 text-center">
        <h1 className="headline">We couldn't open that order.</h1>
        <p className="mt-4 text-lg text-muted">{error.message}</p>
        <Link to="/track" className="btn-primary mt-8 px-6 py-3">
          Look up an order
        </Link>
      </div>
    )
  }

  if (!order) return <div className="container-page py-28" aria-busy="true" />

  const photo = order.items[0]?.image ?? 'images/hero-spread.jpg'
  const finished = order.status === 'delivered' || order.status === 'cancelled'

  return (
    <div className="container-page pt-12 sm:pt-16">
      <PageIntro className="grid gap-8 lg:grid-cols-[1fr_auto] lg:items-end">
        <div>
          <p className="eyebrow">Order {order.ref}</p>
          <h1 className="headline mt-3">{headlineFor(order)}</h1>
          <p className="mt-4 text-lg text-muted">
            {longDate(order.eventDate)}, {order.timeSlot}
          </p>
        </div>
        <div className="flex items-center gap-2 text-[13px] font-medium" aria-live="polite">
          {finished ? (
            <span className="text-muted">Order {order.status === 'delivered' ? 'completed' : 'closed'}</span>
          ) : connection === 'live' ? (
            <>
              <span className="relative flex h-2.5 w-2.5">
                <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-success opacity-60" />
                <span className="relative inline-flex h-2.5 w-2.5 rounded-full bg-success" />
              </span>
              <span className="text-success">Live updates on</span>
            </>
          ) : (
            <>
              <span className="h-2.5 w-2.5 rounded-full bg-line" />
              <span className="text-muted">{connection === 'connecting' ? 'Connecting…' : 'Reconnecting…'}</span>
            </>
          )}
        </div>
      </PageIntro>

      <AnimatePresence>
        {flash && (
          <motion.div
            role="status"
            className="mt-6 flex items-center justify-between gap-4 rounded-2xl bg-accent px-5 py-4 text-white shadow-lg"
            initial={{ opacity: 0, y: -16, scale: 0.97 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            exit={{ opacity: 0, y: -10 }}
            transition={{ type: 'spring', stiffness: 380, damping: 28 }}
          >
            <span className="font-medium">{flash}</span>
            <button type="button" onClick={() => setFlash(null)} className="text-[14px] text-white/80 hover:text-white">
              Dismiss
            </button>
          </motion.div>
        )}
      </AnimatePresence>

      <div className="mt-10 grid gap-10 lg:grid-cols-[1fr_380px]">
        <section className="rounded-card border border-line/70 p-6 sm:p-8" aria-labelledby="progress-title">
          <h2 id="progress-title" className="mb-6 text-xl font-semibold tracking-tight">
            Progress
          </h2>
          {order.status === 'cancelled' ? (
            <p className="rounded-2xl bg-danger/8 px-5 py-4 text-danger">
              This order was cancelled. If you weren't expecting this, please call us.
            </p>
          ) : (
            <StatusTimeline order={order} />
          )}
          {order.payable && (
            <div className="mt-8 rounded-2xl bg-accent-soft px-5 py-4">
              <p className="font-medium text-accent-dark">
                {order.status === 'payment_failed' ? 'The last payment was declined.' : 'This order is waiting for payment.'}
              </p>
              <button
                type="button"
                className="btn-primary mt-3"
                onClick={() => {
                  resumePayment(order.ref, order.email)
                  navigate('/checkout')
                }}
              >
                Complete payment
              </button>
            </div>
          )}
          <details className="mt-8 text-[14px]">
            <summary className="cursor-pointer font-medium text-muted hover:text-ink">Full history</summary>
            <ul className="mt-3 space-y-2">
              {order.history.map((h, i) => (
                <li key={i} className="flex justify-between gap-4">
                  <span>
                    {h.label}
                    {h.note && <span className="text-muted"> · {h.note}</span>}
                  </span>
                  <span className="flex-none text-muted">{dateTime(h.changedAt)}</span>
                </li>
              ))}
            </ul>
          </details>
        </section>

        <aside className="space-y-5" aria-label="Order details">
          <div className="aspect-[16/10] overflow-hidden rounded-card bg-mist">
            <img src={imageUrl(photo)} alt="" className="h-full w-full object-cover" />
          </div>
          <div className="rounded-card bg-mist p-6">
            <h2 className="text-lg font-semibold">What's coming</h2>
            <ul className="mt-4 space-y-5">
              {order.items.map((item, i) => (
                <li key={i}>
                  <div className="flex justify-between gap-3 text-[15px]">
                    <p className="font-medium">
                      {item.packageName} <span className="text-muted">× {item.guests}</span>
                    </p>
                    <p className="tabular-nums">{money(item.lineTotal)}</p>
                  </div>
                  <p className="mt-1 text-[13px] text-muted">{item.dishes.map((d) => d.name).join(' · ')}</p>
                </li>
              ))}
            </ul>
            <dl className="mt-5 space-y-1.5 border-t border-line/70 pt-4 text-[15px]">
              <div className="flex justify-between">
                <dt className="text-muted">Delivery &amp; set-up</dt>
                <dd>{order.deliveryFee ? money(order.deliveryFee) : 'Free'}</dd>
              </div>
              <div className="flex justify-between text-lg font-semibold">
                <dt>Total</dt>
                <dd className="tabular-nums">{money(order.total)}</dd>
              </div>
            </dl>
          </div>
          <div className="rounded-card bg-mist p-6 text-[15px]">
            <h2 className="text-lg font-semibold">Delivery</h2>
            <p className="mt-3">{order.customerName}</p>
            <p className="text-muted">
              {order.address}, Singapore {order.postalCode}
            </p>
            {order.notes && <p className="mt-2 text-muted">“{order.notes}”</p>}
          </div>
        </aside>
      </div>
    </div>
  )
}

function headlineFor(order: Order) {
  switch (order.status) {
    case 'pending_payment':
      return 'Almost there.'
    case 'payment_failed':
      return 'Payment needed.'
    case 'confirmed':
      return "You're booked."
    case 'preparing':
      return 'In the kitchen.'
    case 'out_for_delivery':
      return 'On its way.'
    case 'delivered':
      return 'Enjoy your buffet.'
    case 'cancelled':
      return 'Order cancelled.'
  }
}
