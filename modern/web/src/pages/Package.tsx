import { Suspense, ViewTransition, lazy, useRef, useState } from 'react'
import { Link, useParams } from 'react-router'
import { AnimatePresence, motion, useAnimate, useInView } from 'motion/react'
import { api, imageUrl, peek, useApi } from '../lib/api'
import { cart, lineTotal } from '../lib/cart'
import { hasWebGL, useMediaQuery, useTitle } from '../lib/hooks'
import { money, plural } from '../lib/format'
import type { Dish, MenuCategory, Package, PackageDetail, Rules } from '../lib/types'
import { GuestStepper } from '../components/GuestStepper'
import { AnimatedMoney, PageIntro, easeApple } from '../components/Motion'
import { CheckIcon, ChiliIcon, CubeIcon, LeafIcon } from '../components/Icons'

// three.js is only downloaded when this component is actually shown (desktop).
const BuffetPreview = lazy(() => import('../components/BuffetPreview'))

export default function PackagePage() {
  const { id = '' } = useParams()
  const { data: detail, error, retry } = useApi<PackageDetail>(api.packagePath(id))
  const { data: rules } = useApi<Rules>('/rules')

  // Show the header straight away from the package list (already loaded on the
  // previous page), so the photo can glide in while the menu loads.
  const fromList = peek<Package[]>('/packages')?.find((p) => String(p.id) === id)
  const pkg = detail?.package ?? fromList
  useTitle(pkg?.name ?? 'Package')

  if (error) {
    return (
      <div className="container-page py-28 text-center">
        <h1 className="headline">{error.status === 404 ? 'Package not found.' : 'Something went wrong.'}</h1>
        <p className="mt-4 text-lg text-muted">{error.message}</p>
        <div className="mt-8 flex justify-center gap-3">
          {error.status !== 404 && (
            <button type="button" onClick={retry} className="btn-outline">
              Try again
            </button>
          )}
          <Link to="/buffets" className="btn-primary">
            See all buffets
          </Link>
        </div>
      </div>
    )
  }

  return (
    <div className="container-page pt-10 sm:pt-14">
      <nav aria-label="Breadcrumb" className="mb-6 text-[13px] text-muted">
        <Link to="/buffets" className="hover:text-ink">
          Buffets
        </Link>
        <span className="mx-2">/</span>
        <span className="text-ink">{pkg?.name ?? '…'}</span>
      </nav>

      <section className="grid items-center gap-8 lg:grid-cols-[1.15fr_1fr] lg:gap-14">
        <ViewTransition name={`pkg-photo-${id}`}>
          <div className="aspect-[4/3] overflow-hidden rounded-card bg-mist">
            {pkg && <img src={imageUrl(pkg.image)} alt={pkg.imageAlt} className="h-full w-full object-cover" />}
          </div>
        </ViewTransition>
        <PageIntro>
          {pkg ? (
            <>
              <p className="eyebrow">{money(pkg.pricePerPax)} per guest</p>
              <h1 className="headline mt-3">{pkg.name}</h1>
              <p className="mt-3 text-xl text-muted">{pkg.tagline}</p>
              <p className="mt-5 max-w-lg text-[17px] leading-relaxed">{pkg.description}</p>
              <ul className="mt-6 flex flex-wrap gap-2 text-[13px]">
                <li className="rounded-full bg-mist px-3 py-1.5">Min. {pkg.minPax} guests</li>
                <li className="rounded-full bg-mist px-3 py-1.5">Choose {pkg.dishCount} dishes</li>
                <li className="rounded-full bg-mist px-3 py-1.5">Delivery &amp; set-up included from $500</li>
              </ul>
            </>
          ) : (
            <div className="space-y-4" aria-hidden="true">
              <div className="h-5 w-32 animate-pulse rounded bg-mist" />
              <div className="h-14 w-3/4 animate-pulse rounded bg-mist" />
              <div className="h-20 w-full animate-pulse rounded bg-mist" />
            </div>
          )}
        </PageIntro>
      </section>

      {detail && rules ? (
        <Builder key={detail.package.id} detail={detail} rules={rules} />
      ) : (
        <div className="mt-16 grid gap-4" aria-busy="true" aria-label="Loading the menu">
          {[0, 1, 2].map((i) => (
            <div key={i} className="h-40 animate-pulse rounded-card bg-mist" />
          ))}
        </div>
      )}
    </div>
  )
}

function Builder({ detail, rules }: { detail: PackageDetail; rules: Rules }) {
  const { package: pkg, menu } = detail
  const [guests, setGuests] = useState(pkg.minPax)
  const [selected, setSelected] = useState<number[]>([]) // dish IDs in the order they were picked
  const [added, setAdded] = useState(false)
  const desktop = useMediaQuery('(min-width: 1024px)')
  const show3D = desktop && hasWebGL()
  const sectionRef = useRef<HTMLElement>(null)
  const summaryRef = useRef<HTMLDivElement>(null)
  const sectionInView = useInView(sectionRef)
  const summaryInView = useInView(summaryRef, { amount: 0.4 })
  const showBar = !desktop && sectionInView && !summaryInView

  const pickedIn = (cat: MenuCategory) => cat.dishes.filter((d) => selected.includes(d.id)).length
  const totalNeeded = menu.reduce((n, c) => n + c.choose, 0)
  const totalPicked = menu.reduce((n, c) => n + Math.min(pickedIn(c), c.choose), 0)
  const missing = menu.filter((c) => pickedIn(c) !== c.choose)
  const complete = missing.length === 0
  const total = lineTotal({ pricePerPax: pkg.pricePerPax, guests })

  const toggle = (cat: MenuCategory, dish: Dish) => {
    setAdded(false)
    setSelected((prev) => {
      if (prev.includes(dish.id)) return prev.filter((id) => id !== dish.id)
      const inCat = prev.filter((id) => cat.dishes.some((d) => d.id === id))
      // A "choose 1" course behaves like radio buttons: the new pick replaces the old.
      if (cat.choose === 1 && inCat.length === 1) return [...prev.filter((id) => id !== inCat[0]), dish.id]
      if (inCat.length >= cat.choose) return prev
      return [...prev, dish.id]
    })
  }

  const addToCart = () => {
    if (!complete) return
    const names = menu.flatMap((c) => c.dishes.filter((d) => selected.includes(d.id)).map((d) => d.name))
    cart.add({
      packageId: pkg.id,
      name: pkg.name,
      image: pkg.image,
      imageAlt: pkg.imageAlt,
      pricePerPax: pkg.pricePerPax,
      minPax: pkg.minPax,
      guests,
      dishIds: menu.flatMap((c) => c.dishes.filter((d) => selected.includes(d.id)).map((d) => d.id)),
      dishNames: names,
    })
    setAdded(true)
    setSelected([])
    cart.open()
  }

  const summary = (
    <div className="rounded-card bg-mist p-6">
      <div className="flex items-center justify-between gap-4">
        <span className="text-[15px] font-medium">Guests</span>
        <GuestStepper id="guests" value={guests} min={pkg.minPax} max={rules.maxGuests} onChange={setGuests} />
      </div>
      <p className="mt-2 text-right text-[13px] text-muted">
        Between {pkg.minPax} and {rules.maxGuests}
      </p>
      <dl className="mt-5 space-y-2 border-t border-line/70 pt-5 text-[15px]">
        <div className="flex justify-between">
          <dt className="text-muted">Price per guest</dt>
          <dd className="tabular-nums">{money(pkg.pricePerPax)}</dd>
        </div>
        <div className="flex justify-between">
          <dt className="text-muted">Dishes chosen</dt>
          <dd className="tabular-nums">
            {totalPicked} of {totalNeeded}
          </dd>
        </div>
        <div className="flex items-baseline justify-between pt-2">
          <dt className="font-semibold">Estimated total</dt>
          <dd>
            <AnimatedMoney value={total} className="text-2xl font-semibold tracking-tight tabular-nums" />
          </dd>
        </div>
      </dl>
      <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-white" aria-hidden="true">
        <motion.div
          className="h-full rounded-full bg-accent"
          initial={false}
          animate={{ width: `${(totalPicked / totalNeeded) * 100}%` }}
          transition={{ duration: 0.5, ease: easeApple }}
        />
      </div>
      <button type="button" onClick={addToCart} disabled={!complete} className="btn-primary mt-6 w-full py-3">
        {complete ? 'Add to cart' : `Choose ${plural(totalNeeded - totalPicked, 'more dish', 'more dishes')}`}
      </button>
      <p className="mt-3 min-h-5 text-center text-[13px] text-muted" aria-live="polite">
        {added
          ? 'Added to your cart.'
          : complete
            ? 'Delivery is free for orders from $500.'
            : `Still to choose: ${missing.map((c) => c.name).join(', ')}`}
      </p>
    </div>
  )

  return (
    <section ref={sectionRef} className="mt-16 grid gap-10 lg:grid-cols-[1fr_400px]" aria-labelledby="menu-title">
      <div>
        <h2 id="menu-title" className="text-3xl font-semibold tracking-tight">
          Choose your dishes
        </h2>
        <p className="mt-2 text-muted">Pick the number shown for each course. Tap a dish again to remove it.</p>
        <div className="mt-8 space-y-12">
          {menu.map((cat) => (
            <CourseGroup key={cat.id} cat={cat} selected={selected} onToggle={toggle} />
          ))}
        </div>
      </div>

      <aside className="lg:sticky lg:top-20 lg:self-start" aria-label="Your selection">
        {show3D && (
          <div className="mb-5 overflow-hidden rounded-card bg-gradient-to-b from-mist to-white">
            <div className="flex items-center gap-2 px-5 pt-4 text-[13px] font-medium text-muted">
              <CubeIcon width={16} height={16} /> Your buffet line · drag to turn
            </div>
            <div className="h-[260px]">
              <Suspense fallback={<div className="h-full animate-pulse" />}>
                <BuffetPreview menu={menu} selected={selected} guests={guests} />
              </Suspense>
            </div>
          </div>
        )}
        <div ref={summaryRef}>{summary}</div>
      </aside>

      {/* Phones and tablets: keep the total and the button within reach while
          choosing; it slides away once the full summary card is on screen. */}
      <AnimatePresence>
        {showBar && (
          <motion.div
            className="fixed inset-x-0 bottom-0 z-30 border-t border-line/60 bg-white/85 px-4 py-3 backdrop-blur-xl lg:hidden"
            initial={{ y: '100%' }}
            animate={{ y: 0 }}
            exit={{ y: '100%' }}
            transition={{ duration: 0.3, ease: easeApple }}
          >
            <div className="mx-auto flex max-w-6xl items-center justify-between gap-4">
              <div>
                <p className="text-[13px] text-muted">
                  {totalPicked}/{totalNeeded} dishes · {guests} guests
                </p>
                <AnimatedMoney value={total} className="text-lg font-semibold tabular-nums" />
              </div>
              <button type="button" onClick={addToCart} disabled={!complete} className="btn-primary">
                Add to cart
              </button>
            </div>
          </motion.div>
        )}
      </AnimatePresence>
    </section>
  )
}

function CourseGroup({
  cat,
  selected,
  onToggle,
}: {
  cat: MenuCategory
  selected: number[]
  onToggle: (cat: MenuCategory, dish: Dish) => void
}) {
  const picked = cat.dishes.filter((d) => selected.includes(d.id)).length
  const full = picked >= cat.choose
  const [counter, animateCounter] = useAnimate()

  const handle = (dish: Dish) => {
    const isSelected = selected.includes(dish.id)
    if (!isSelected && full && cat.choose > 1) {
      // Course is full: nudge the counter so the customer sees why nothing happened.
      animateCounter(counter.current, { x: [0, -6, 6, -4, 4, 0] }, { duration: 0.4 })
      return
    }
    onToggle(cat, dish)
  }

  return (
    <fieldset>
      <legend className="flex w-full items-baseline justify-between gap-4">
        <span className="text-xl font-semibold tracking-tight">{cat.name}</span>
        <span ref={counter} className={`text-[14px] font-medium ${full ? 'text-success' : 'text-muted'}`}>
          {full ? (
            <span className="inline-flex items-center gap-1">
              <CheckIcon width={15} height={15} /> {picked} of {cat.choose} chosen
            </span>
          ) : (
            `Choose ${cat.choose} · ${picked} chosen`
          )}
        </span>
      </legend>
      <ul className="mt-4 grid gap-3 sm:grid-cols-2">
        {cat.dishes.map((dish) => {
          const isSelected = selected.includes(dish.id)
          const dimmed = full && !isSelected && cat.choose > 1
          return (
            <li key={dish.id}>
              <button
                type="button"
                aria-pressed={isSelected}
                onClick={() => handle(dish)}
                className={`relative flex h-full w-full flex-col items-start rounded-2xl border p-4 text-left transition duration-200 ease-apple ${
                  isSelected
                    ? 'border-accent bg-accent-soft/60 shadow-[0_0_0_1px_var(--color-accent)]'
                    : 'border-line/80 bg-white hover:border-ink/40'
                } ${dimmed ? 'opacity-45' : ''}`}
              >
                <span className="pr-8 font-medium">{dish.name}</span>
                <span className="mt-1 text-[13px] text-muted">{dish.description}</span>
                {(dish.isVegetarian || dish.isSpicy) && (
                  <span className="mt-2 flex gap-2 text-[12px]">
                    {dish.isVegetarian && (
                      <span className="inline-flex items-center gap-1 text-success">
                        <LeafIcon width={14} height={14} /> Vegetarian
                      </span>
                    )}
                    {dish.isSpicy && (
                      <span className="inline-flex items-center gap-1 text-danger">
                        <ChiliIcon width={14} height={14} /> Spicy
                      </span>
                    )}
                  </span>
                )}
                <AnimatePresence>
                  {isSelected && (
                    <motion.span
                      className="absolute right-3 top-3 grid h-6 w-6 place-items-center rounded-full bg-accent text-white"
                      initial={{ scale: 0, rotate: -45 }}
                      animate={{ scale: 1, rotate: 0 }}
                      exit={{ scale: 0 }}
                      transition={{ type: 'spring', stiffness: 500, damping: 22 }}
                      aria-hidden="true"
                    >
                      <CheckIcon width={14} height={14} strokeWidth={2.6} />
                    </motion.span>
                  )}
                </AnimatePresence>
              </button>
            </li>
          )
        })}
      </ul>
    </fieldset>
  )
}
