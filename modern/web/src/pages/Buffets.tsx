import { useMemo, useState } from 'react'
import { Link } from 'react-router'
import { LayoutGroup, motion } from 'motion/react'
import { useApi } from '../lib/api'
import { useTitle } from '../lib/hooks'
import { cents, money } from '../lib/format'
import type { Package } from '../lib/types'
import { PackageCard, PackageCardSkeleton } from '../components/PackageCard'
import { GuestStepper } from '../components/GuestStepper'
import { PageIntro, Reveal, easeApple } from '../components/Motion'

type SortKey = 'recommended' | 'price-asc' | 'price-desc'

const sorts: { key: SortKey; label: string }[] = [
  { key: 'recommended', label: 'Recommended' },
  { key: 'price-asc', label: 'Price: low to high' },
  { key: 'price-desc', label: 'Price: high to low' },
]

export default function Buffets() {
  useTitle('Buffet packages')
  const { data: packages, error, retry } = useApi<Package[]>('/packages')
  const [guests, setGuests] = useState(40)
  const [sort, setSort] = useState<SortKey>('recommended')

  const sorted = useMemo(() => {
    if (!packages) return undefined
    const list = [...packages]
    if (sort === 'price-asc') list.sort((a, b) => a.pricePerPax - b.pricePerPax)
    if (sort === 'price-desc') list.sort((a, b) => b.pricePerPax - a.pricePerPax)
    return list
  }, [packages, sort])

  return (
    <div className="container-page pt-16 sm:pt-20">
      <PageIntro className="max-w-3xl">
        <p className="eyebrow">Buffet packages</p>
        <h1 className="headline mt-3">Every buffet, your way.</h1>
        <p className="mt-5 text-lg text-muted sm:text-xl">
          Each package is priced per guest. Pick the one that suits your event, then choose exactly which dishes go on
          the table.
        </p>
      </PageIntro>

      {/* Estimator and sorting */}
      <div className="mt-12 flex flex-col gap-5 rounded-card bg-mist p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
        <div className="flex flex-wrap items-center gap-4">
          <span id="estimate-label" className="text-[15px] font-medium">
            How many guests?
          </span>
          <GuestStepper id="estimate-guests" label="Number of guests for the estimate" value={guests} min={10} max={500} onChange={setGuests} />
        </div>
        <LayoutGroup id="sort">
          <div role="radiogroup" aria-label="Sort packages" className="flex flex-wrap gap-1 rounded-full bg-white p-1">
            {sorts.map((s) => {
              const active = sort === s.key
              return (
                <button
                  key={s.key}
                  type="button"
                  role="radio"
                  aria-checked={active}
                  onClick={() => setSort(s.key)}
                  className={`relative rounded-full px-4 py-2 text-[13px] font-medium transition-colors ${
                    active ? 'text-white' : 'text-muted hover:text-ink'
                  }`}
                >
                  {active && (
                    <motion.span
                      layoutId="sort-pill"
                      className="absolute inset-0 rounded-full bg-ink"
                      transition={{ type: 'spring', stiffness: 420, damping: 34 }}
                    />
                  )}
                  <span className="relative">{s.label}</span>
                </button>
              )
            })}
          </div>
        </LayoutGroup>
      </div>

      {error ? (
        <div className="mt-10 rounded-card bg-mist p-10 text-center">
          <p>{error.message}</p>
          <button type="button" onClick={retry} className="btn-outline mt-4">
            Try again
          </button>
        </div>
      ) : (
        <ul className="mt-10 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
          {sorted
            ? sorted.map((pkg) => (
                <motion.li
                  key={pkg.id}
                  layout
                  transition={{ layout: { duration: 0.6, ease: easeApple } }}
                  initial={{ opacity: 0, y: 24 }}
                  animate={{ opacity: 1, y: 0 }}
                >
                  <PackageCard pkg={pkg} guests={guests} />
                </motion.li>
              ))
            : [0, 1, 2].map((i) => (
                <li key={i}>
                  <PackageCardSkeleton />
                </li>
              ))}
        </ul>
      )}

      {sorted && sorted.length > 0 && (
        <Reveal as="section" className="mt-24">
          <h2 className="text-3xl font-semibold tracking-tight">Compare at a glance</h2>
          <p className="mt-2 text-muted">Estimates for {guests} guests, before delivery. Delivery is free from $500.</p>
          <div className="mt-6 overflow-x-auto rounded-card border border-line/70">
            <table className="w-full min-w-[560px] text-left text-[15px]">
              <caption className="sr-only">Buffet packages compared by price, minimum guests and dishes</caption>
              <thead className="bg-mist text-[13px] text-muted">
                <tr>
                  <th scope="col" className="px-5 py-3 font-medium">Package</th>
                  <th scope="col" className="px-5 py-3 font-medium">Per guest</th>
                  <th scope="col" className="px-5 py-3 font-medium">Minimum</th>
                  <th scope="col" className="px-5 py-3 font-medium">Dishes</th>
                  <th scope="col" className="px-5 py-3 text-right font-medium">For {guests} guests</th>
                </tr>
              </thead>
              <tbody>
                {sorted.map((p) => {
                  const ok = guests >= p.minPax
                  return (
                    <tr key={p.id} className="border-t border-line/60">
                      <th scope="row" className="px-5 py-4 font-semibold">
                        <Link to={`/buffets/${p.id}`} className="hover:text-accent">
                          {p.name}
                        </Link>
                      </th>
                      <td className="px-5 py-4 tabular-nums">{money(p.pricePerPax)}</td>
                      <td className="px-5 py-4">{p.minPax} guests</td>
                      <td className="px-5 py-4">{p.dishCount}</td>
                      <td className="px-5 py-4 text-right tabular-nums">
                        {ok ? money((cents(p.pricePerPax) * guests) / 100) : <span className="text-muted">Min. {p.minPax}</span>}
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        </Reveal>
      )}
    </div>
  )
}
