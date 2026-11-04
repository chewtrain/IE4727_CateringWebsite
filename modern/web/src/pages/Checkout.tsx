import { useEffect, useRef, useState, type FormEvent } from 'react'
import { Link, useNavigate } from 'react-router'
import { AnimatePresence, motion } from 'motion/react'
import { ApiError, api, imageUrl, useApi } from '../lib/api'
import { cart, cartTotals, lineTotal, useCart, type CartLine } from '../lib/cart'
import { longDate, money, shortDate } from '../lib/format'
import { useTitle } from '../lib/hooks'
import { rememberOrder } from '../lib/storage'
import type { CustomerDetails, CustomerField, Order, Rules } from '../lib/types'
import { normalise, validateCustomer } from '../lib/validation'
import { Field, describedBy } from '../components/Field'
import { PageIntro, easeApple } from '../components/Motion'

type Step = 'details' | 'payment' | 'done'

// The order waiting for payment survives a page refresh (this tab only).
const PENDING_KEY = 'makanco.pending'
function loadPending(): { ref: string; email: string } | null {
  try {
    return JSON.parse(sessionStorage.getItem(PENDING_KEY) ?? 'null')
  } catch {
    return null
  }
}
function savePending(value: { ref: string; email: string } | null) {
  try {
    if (value) sessionStorage.setItem(PENDING_KEY, JSON.stringify(value))
    else sessionStorage.removeItem(PENDING_KEY)
  } catch {
    /* ignore */
  }
}
/** Used by the tracking page's "Complete payment" button. */
export function resumePayment(ref: string, email: string) {
  savePending({ ref, email })
}

const emptyDetails: CustomerDetails = {
  name: '',
  email: '',
  emailConfirm: '',
  phone: '',
  address: '',
  postalCode: '',
  eventDate: '',
  timeSlot: '',
  notes: '',
  terms: false,
}

const stepNames: Record<Step, string> = { details: 'Details', payment: 'Payment', done: 'Confirmed' }

export default function Checkout() {
  useTitle('Checkout')
  const lines = useCart()
  const { data: rules } = useApi<Rules>('/rules')
  const [step, setStep] = useState<Step>('details')
  const [order, setOrder] = useState<Order | null>(null)
  const [emailSent, setEmailSent] = useState(false)
  const [resuming, setResuming] = useState(() => loadPending() !== null)

  // Pick up an order that is still waiting for payment.
  useEffect(() => {
    const pending = loadPending()
    if (!pending) return
    api
      .getOrder(pending.ref, pending.email)
      .then((o) => {
        if (o.payable) {
          setOrder(o)
          setStep('payment')
        } else savePending(null)
      })
      .catch(() => savePending(null))
      .finally(() => setResuming(false))
  }, [])

  useEffect(() => {
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }, [step])

  if (resuming || !rules) {
    return <div className="container-page py-28" aria-busy="true" />
  }

  if (step === 'details' && lines.length === 0) {
    return (
      <div className="container-page py-28 text-center">
        <h1 className="headline">Your cart is empty.</h1>
        <p className="mt-4 text-lg text-muted">Choose a buffet package and pick your dishes first.</p>
        <Link to="/buffets" className="btn-primary mt-8 px-6 py-3">
          Browse buffets
        </Link>
      </div>
    )
  }

  const steps: Step[] = ['details', 'payment', 'done']
  const current = steps.indexOf(step)

  return (
    <div className="container-page pt-12 sm:pt-16">
      <PageIntro>
        <h1 className="headline">{step === 'done' ? 'Thank you.' : 'Checkout.'}</h1>
        <p className="mt-3 text-lg text-muted">No account needed. We email your confirmation and a tracking link.</p>
      </PageIntro>

      <ol className="mt-8 flex items-center gap-3 text-[13px]" aria-label="Checkout progress">
        {steps.map((s, i) => (
          <li key={s} className="flex items-center gap-3" aria-current={i === current ? 'step' : undefined}>
            <span
              className={`grid h-6 w-6 place-items-center rounded-full text-[12px] font-semibold transition-colors duration-500 ${
                i <= current ? 'bg-accent text-white' : 'bg-mist text-muted'
              }`}
            >
              {i + 1}
            </span>
            <span className={i === current ? 'font-semibold' : 'text-muted'}>{stepNames[s]}</span>
            {i < steps.length - 1 && <span className="h-px w-8 bg-line sm:w-14" aria-hidden="true" />}
          </li>
        ))}
      </ol>

      <div className="mt-10 grid gap-10 lg:grid-cols-[1fr_380px]">
        <AnimatePresence mode="wait" initial={false}>
          <motion.div
            key={step}
            initial={{ opacity: 0, x: 40 }}
            animate={{ opacity: 1, x: 0 }}
            exit={{ opacity: 0, x: -40 }}
            transition={{ duration: 0.4, ease: easeApple }}
          >
            {step === 'details' && (
              <DetailsForm
                rules={rules}
                lines={lines}
                onPlaced={(o) => {
                  setOrder(o)
                  setStep('payment')
                }}
              />
            )}
            {step === 'payment' && order && (
              <PaymentStep
                order={order}
                onResult={(o, sent) => {
                  setOrder(o)
                  if (o.status === 'confirmed') {
                    setEmailSent(sent)
                    setStep('done')
                  }
                }}
              />
            )}
            {step === 'done' && order && <Confirmation order={order} emailSent={emailSent} />}
          </motion.div>
        </AnimatePresence>

        <aside className="lg:sticky lg:top-20 lg:self-start" aria-label="Order summary">
          {step === 'details' ? <CartSummary lines={lines} rules={rules} /> : order && <OrderSummary order={order} />}
        </aside>
      </div>
    </div>
  )
}

function DetailsForm({ rules, lines, onPlaced }: { rules: Rules; lines: CartLine[]; onPlaced: (o: Order) => void }) {
  const [values, setValues] = useState<CustomerDetails>(emptyDetails)
  const [touched, setTouched] = useState<Set<CustomerField>>(new Set())
  const [serverErrors, setServerErrors] = useState<Partial<Record<CustomerField, string>>>({})
  const [itemErrors, setItemErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const formRef = useRef<HTMLFormElement>(null)

  const clientErrors = validateCustomer(values, rules)
  const errorFor = (f: CustomerField) => (touched.has(f) ? (clientErrors[f] ?? serverErrors[f]) : undefined)

  const set = <K extends CustomerField>(field: K, value: CustomerDetails[K]) => {
    setValues((v) => ({ ...v, [field]: value }))
    setServerErrors((e) => ({ ...e, [field]: undefined }))
  }
  const touch = (field: CustomerField) => setTouched((t) => new Set(t).add(field))
  const input = (field: Exclude<CustomerField, 'terms'>) => ({
    id: field,
    name: field,
    value: values[field],
    onChange: (e: { target: { value: string } }) => set(field, e.target.value),
    onBlur: () => touch(field),
    ...describedBy(field, errorFor(field)),
  })

  const submit = async (e: FormEvent) => {
    e.preventDefault()
    const all = Object.keys(emptyDetails) as CustomerField[]
    setTouched(new Set(all))
    setFormError('')
    if (Object.keys(clientErrors).length > 0) {
      setFormError(`Please correct the ${Object.keys(clientErrors).length} highlighted field(s).`)
      requestAnimationFrame(() => formRef.current?.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus())
      return
    }
    setSubmitting(true)
    try {
      const details = normalise(values)
      const placed = await api.createOrder(
        details,
        lines.map((l) => ({ packageId: l.packageId, guests: l.guests, dishIds: l.dishIds })),
      )
      rememberOrder(placed.ref, details.email)
      savePending({ ref: placed.ref, email: details.email })
      onPlaced(await api.getOrder(placed.ref, details.email))
    } catch (err) {
      const apiErr = err as ApiError
      setFormError(apiErr.message)
      setServerErrors(apiErr.body?.fields ?? {})
      setItemErrors(apiErr.body?.items ?? {})
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <form ref={formRef} onSubmit={submit} noValidate className="space-y-10">
      <AnimatePresence>
        {formError && (
          <motion.div
            role="alert"
            className="rounded-2xl bg-danger/8 px-5 py-4 text-[15px] text-danger"
            initial={{ opacity: 0, y: -8 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0 }}
          >
            <p>{formError}</p>
            {Object.entries(itemErrors).map(([index, msgs]) => (
              <p key={index} className="mt-1">
                <strong>{lines[Number(index)]?.name ?? 'A package'}:</strong> {msgs.join(' ')}
              </p>
            ))}
          </motion.div>
        )}
      </AnimatePresence>

      <fieldset className="space-y-5">
        <legend className="mb-5 text-2xl font-semibold tracking-tight">Contact details</legend>
        <Field id="name" label="Full name" error={errorFor('name')}>
          <input {...input('name')} className="field-input" autoComplete="name" maxLength={60} required />
        </Field>
        <div className="grid gap-5 sm:grid-cols-2">
          <Field id="email" label="Email" error={errorFor('email')}>
            <input {...input('email')} type="email" className="field-input" autoComplete="email" maxLength={120} required />
          </Field>
          <Field id="emailConfirm" label="Confirm email" error={errorFor('emailConfirm')}>
            <input {...input('emailConfirm')} type="email" className="field-input" autoComplete="email" maxLength={120} required />
          </Field>
        </div>
        <Field id="phone" label="Mobile number" hint="8 digits, starting with 6, 8 or 9" error={errorFor('phone')}>
          <input {...input('phone')} type="tel" inputMode="numeric" className="field-input" autoComplete="tel-national" maxLength={9} required />
        </Field>
      </fieldset>

      <fieldset className="space-y-5">
        <legend className="mb-5 text-2xl font-semibold tracking-tight">Delivery</legend>
        <Field id="address" label="Address" hint="Block, street and unit number" error={errorFor('address')}>
          <input {...input('address')} className="field-input" autoComplete="street-address" maxLength={200} required />
        </Field>
        <div className="grid gap-5 sm:grid-cols-2">
          <Field id="postalCode" label="Postal code" error={errorFor('postalCode')}>
            <input {...input('postalCode')} inputMode="numeric" className="field-input" autoComplete="postal-code" maxLength={6} required />
          </Field>
          <Field
            id="eventDate"
            label="Event date"
            hint={`${shortDate(rules.earliestDate)} to ${shortDate(rules.latestDate)}`}
            error={errorFor('eventDate')}
          >
            <input {...input('eventDate')} type="date" min={rules.earliestDate} max={rules.latestDate} className="field-input" required />
          </Field>
        </div>
        <div role="radiogroup" aria-labelledby="slot-label" aria-describedby={errorFor('timeSlot') ? 'timeSlot-error' : undefined}>
          <p id="slot-label" className="mb-1.5 text-[14px] font-medium">
            Delivery time
          </p>
          <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
            {rules.timeSlots.map((slot) => {
              const active = values.timeSlot === slot
              return (
                <button
                  key={slot}
                  type="button"
                  role="radio"
                  aria-checked={active}
                  onClick={() => {
                    set('timeSlot', slot)
                    touch('timeSlot')
                  }}
                  className={`rounded-xl border px-3 py-3 text-[15px] tabular-nums transition ${
                    active ? 'border-accent bg-accent-soft font-semibold text-accent-dark' : 'border-line hover:border-ink/40'
                  }`}
                >
                  {slot.replace(' - ', '–')}
                </button>
              )
            })}
          </div>
          {errorFor('timeSlot') && (
            <p id="timeSlot-error" role="alert" className="mt-1.5 text-[13px] text-danger">
              {errorFor('timeSlot')}
            </p>
          )}
        </div>
        <Field id="notes" label="Notes for our team (optional)" hint={`${values.notes.length}/500`} error={errorFor('notes')}>
          <textarea {...input('notes')} rows={3} maxLength={500} className="field-input resize-y" placeholder="Gate code, dietary needs, set-up instructions" />
        </Field>
      </fieldset>

      <div>
        <label className="flex items-start gap-3 text-[15px]">
          <input
            type="checkbox"
            checked={values.terms}
            onChange={(e) => {
              set('terms', e.target.checked)
              touch('terms')
            }}
            className="mt-1 h-5 w-5 flex-none accent-[var(--color-accent)]"
            {...describedBy('terms', errorFor('terms'))}
          />
          <span>
            I agree to the delivery and set-up terms: a {rules.leadDays}-day lead time, a {money(rules.deliveryFee)} delivery fee for
            orders under {money(rules.freeDeliveryMin)}, and an adult to receive the delivery.
          </span>
        </label>
        {errorFor('terms') && (
          <p id="terms-error" role="alert" className="mt-1.5 text-[13px] text-danger">
            {errorFor('terms')}
          </p>
        )}
      </div>

      <button type="submit" disabled={submitting} className="btn-primary w-full py-3.5 text-[17px] sm:w-auto sm:px-10">
        {submitting ? 'Placing order…' : 'Continue to payment'}
      </button>
    </form>
  )
}

function PaymentStep({ order, onResult }: { order: Order; onResult: (o: Order, emailSent: boolean) => void }) {
  const [busy, setBusy] = useState<'success' | 'failed' | null>(null)
  const [error, setError] = useState('')
  const navigate = useNavigate()

  const pay = async (outcome: 'success' | 'failed') => {
    setBusy(outcome)
    setError('')
    try {
      const result = await api.pay(order.ref, order.email, outcome)
      if (outcome === 'success') {
        cart.clear()
        savePending(null)
      }
      onResult(result.order, result.emailSent)
    } catch (err) {
      const apiErr = err as ApiError
      if (apiErr.status === 409) {
        savePending(null)
        navigate(`/track/${order.ref}`)
        return
      }
      setError(apiErr.message)
    } finally {
      setBusy(null)
    }
  }

  return (
    <div className="mx-auto max-w-lg rounded-card border border-line/70 p-8 text-center shadow-[0_30px_60px_-30px_rgba(0,0,0,0.25)]">
      <p className="inline-block rounded-full bg-mist px-3 py-1 text-[12px] font-medium text-muted">
        Secure payment partner (simulation)
      </p>
      <h2 className="mt-5 text-4xl font-semibold tracking-tight">Pay {money(order.total)}</h2>
      <dl className="mx-auto mt-6 max-w-xs space-y-1.5 text-left text-[15px]">
        <div className="flex justify-between">
          <dt className="text-muted">Merchant</dt>
          <dd>Makan &amp; Co.</dd>
        </div>
        <div className="flex justify-between">
          <dt className="text-muted">Order</dt>
          <dd className="font-mono">{order.ref}</dd>
        </div>
        <div className="flex justify-between">
          <dt className="text-muted">Event date</dt>
          <dd>{shortDate(order.eventDate)}</dd>
        </div>
      </dl>

      <AnimatePresence>
        {order.status === 'payment_failed' && (
          <motion.p
            role="alert"
            className="mt-6 rounded-2xl bg-danger/8 px-4 py-3 text-[15px] text-danger"
            initial={{ opacity: 0, scale: 0.96 }}
            animate={{ opacity: 1, scale: 1, x: [0, -8, 8, -5, 5, 0] }}
            transition={{ duration: 0.45 }}
          >
            Your card was declined and nothing was charged. You can try again.
          </motion.p>
        )}
      </AnimatePresence>
      {error && (
        <p role="alert" className="mt-4 text-[15px] text-danger">
          {error}
        </p>
      )}

      <p className="mt-6 text-[14px] text-muted">
        On the live site this step happens on the payment provider's page. For this project, choose what the provider
        reports back:
      </p>
      <div className="mt-5 space-y-3">
        <button type="button" disabled={busy !== null} onClick={() => pay('success')} className="btn-primary w-full py-3">
          {busy === 'success' ? 'Processing…' : 'Payment succeeds'}
        </button>
        <button type="button" disabled={busy !== null} onClick={() => pay('failed')} className="btn-outline w-full py-3">
          {busy === 'failed' ? 'Processing…' : 'Card is declined'}
        </button>
      </div>
    </div>
  )
}

function Confirmation({ order, emailSent }: { order: Order; emailSent: boolean }) {
  return (
    <div className="rounded-card bg-mist p-8 text-center sm:p-12">
      <svg viewBox="0 0 52 52" className="mx-auto h-20 w-20" aria-hidden="true">
        <motion.circle
          cx="26"
          cy="26"
          r="24"
          fill="none"
          stroke="var(--color-success)"
          strokeWidth="2.5"
          initial={{ pathLength: 0 }}
          animate={{ pathLength: 1 }}
          transition={{ duration: 0.6, ease: easeApple }}
        />
        <motion.path
          d="M15 27 L23 34 L38 19"
          fill="none"
          stroke="var(--color-success)"
          strokeWidth="3"
          strokeLinecap="round"
          strokeLinejoin="round"
          initial={{ pathLength: 0 }}
          animate={{ pathLength: 1 }}
          transition={{ duration: 0.4, delay: 0.5, ease: easeApple }}
        />
      </svg>
      <h2 className="mt-6 text-3xl font-semibold tracking-tight">Your buffet is booked.</h2>
      <p className="mt-3 text-lg text-muted">
        Order <span className="font-mono text-ink">{order.ref}</span> for {longDate(order.eventDate)}, {order.timeSlot}.
      </p>
      <p className="mt-2 text-muted" aria-live="polite">
        {emailSent
          ? `A confirmation was emailed to ${order.email}.`
          : 'Your order is saved. (The confirmation email could not be sent right now.)'}
      </p>
      <div className="mt-8 flex flex-wrap justify-center gap-3">
        <Link to={`/track/${order.ref}`} className="btn-primary px-6 py-3">
          Track this order live
        </Link>
        <Link to="/" className="btn-ghost px-6 py-3">
          Back to home
        </Link>
      </div>
    </div>
  )
}

function CartSummary({ lines, rules }: { lines: CartLine[]; rules: Rules }) {
  const totals = cartTotals(lines, rules.deliveryFee, rules.freeDeliveryMin)
  return (
    <div className="rounded-card bg-mist p-6">
      <div className="flex items-center justify-between">
        <h2 className="text-lg font-semibold">Your order</h2>
        <button type="button" onClick={cart.open} className="text-[14px] font-medium text-accent hover:underline">
          Edit
        </button>
      </div>
      <ul className="mt-4 space-y-4">
        {lines.map((l) => (
          <li key={l.id} className="flex gap-3">
            <img src={imageUrl(l.image)} alt="" className="h-14 w-14 flex-none rounded-xl object-cover" />
            <div className="min-w-0 flex-1 text-[14px]">
              <p className="font-medium">{l.name}</p>
              <p className="text-muted">
                {l.guests} guests × {money(l.pricePerPax)}
              </p>
            </div>
            <p className="text-[14px] tabular-nums">{money(lineTotal(l))}</p>
          </li>
        ))}
      </ul>
      <Totals subtotal={totals.subtotal} delivery={totals.delivery} total={totals.total} />
    </div>
  )
}

function OrderSummary({ order }: { order: Order }) {
  return (
    <div className="rounded-card bg-mist p-6">
      <h2 className="text-lg font-semibold">Order {order.ref}</h2>
      <ul className="mt-4 space-y-4">
        {order.items.map((item, i) => (
          <li key={i} className="flex gap-3">
            <img src={imageUrl(item.image)} alt="" className="h-14 w-14 flex-none rounded-xl object-cover" />
            <div className="min-w-0 flex-1 text-[14px]">
              <p className="font-medium">{item.packageName}</p>
              <p className="text-muted">
                {item.guests} guests × {money(item.pricePerPax)}
              </p>
            </div>
            <p className="text-[14px] tabular-nums">{money(item.lineTotal)}</p>
          </li>
        ))}
      </ul>
      <Totals subtotal={order.subtotal} delivery={order.deliveryFee} total={order.total} />
      <p className="mt-4 text-[13px] text-muted">
        Delivering to {order.address}, Singapore {order.postalCode}
      </p>
    </div>
  )
}

function Totals({ subtotal, delivery, total }: { subtotal: number; delivery: number; total: number }) {
  return (
    <dl className="mt-5 space-y-1.5 border-t border-line/70 pt-4 text-[15px]">
      <div className="flex justify-between">
        <dt className="text-muted">Subtotal</dt>
        <dd className="tabular-nums">{money(subtotal)}</dd>
      </div>
      <div className="flex justify-between">
        <dt className="text-muted">Delivery &amp; set-up</dt>
        <dd className="tabular-nums">{delivery ? money(delivery) : 'Free'}</dd>
      </div>
      <div className="flex justify-between pt-1 text-lg font-semibold">
        <dt>Total</dt>
        <dd className="tabular-nums">{money(total)}</dd>
      </div>
    </dl>
  )
}
