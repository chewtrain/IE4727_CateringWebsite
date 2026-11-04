import { ViewTransition } from 'react'
import { Link } from 'react-router'
import { motion } from 'motion/react'
import { api, imageUrl, prefetch } from '../lib/api'
import { cents, money } from '../lib/format'
import type { Package } from '../lib/types'
import { ArrowRightIcon } from './Icons'

/**
 * A package in a grid. The photo carries a view-transition name, so when the
 * visitor opens the package it glides into the package page's header photo.
 */
export function PackageCard({ pkg, guests }: { pkg: Package; guests?: number }) {
  const belowMin = guests !== undefined && guests < pkg.minPax
  const estimate = guests !== undefined ? (cents(pkg.pricePerPax) * Math.max(guests, pkg.minPax)) / 100 : undefined

  return (
    <Link
      to={`/buffets/${pkg.id}`}
      onMouseEnter={() => prefetch(api.packagePath(pkg.id))}
      onFocus={() => prefetch(api.packagePath(pkg.id))}
      className="group block h-full rounded-card bg-mist p-3 transition duration-300 ease-apple hover:-translate-y-1 hover:shadow-[0_18px_40px_-20px_rgba(0,0,0,0.25)]"
    >
      <ViewTransition name={`pkg-photo-${pkg.id}`}>
        <div className="aspect-[4/3] overflow-hidden rounded-[16px] bg-line/40">
          <img
            src={imageUrl(pkg.image)}
            alt={pkg.imageAlt}
            loading="lazy"
            className="h-full w-full object-cover transition duration-700 ease-apple group-hover:scale-[1.04]"
          />
        </div>
      </ViewTransition>
      <div className="px-3 pb-3 pt-5">
        <h3 className="text-2xl font-semibold tracking-tight">{pkg.name}</h3>
        <p className="mt-1 text-muted">{pkg.tagline}</p>
        <dl className="mt-4 flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted">
          <div>
            <dt className="sr-only">Price</dt>
            <dd>
              <span className="font-semibold text-ink">{money(pkg.pricePerPax)}</span> / guest
            </dd>
          </div>
          <div>
            <dt className="sr-only">Minimum</dt>
            <dd>Min. {pkg.minPax} guests</dd>
          </div>
          <div>
            <dt className="sr-only">Dishes</dt>
            <dd>Choose {pkg.dishCount} dishes</dd>
          </div>
        </dl>
        {estimate !== undefined && (
          <motion.p layout="position" className="mt-4 text-sm">
            {belowMin ? (
              <span className="text-accent-dark">
                Needs at least {pkg.minPax} guests · from <strong>{money(estimate)}</strong>
              </span>
            ) : (
              <span>
                About <strong className="text-ink">{money(estimate)}</strong>{' '}
                <span className="text-muted">for {guests} guests</span>
              </span>
            )}
          </motion.p>
        )}
        <span className="mt-5 inline-flex items-center gap-1 text-[15px] font-medium text-accent">
          Choose dishes
          <ArrowRightIcon width={16} height={16} className="transition group-hover:translate-x-1" />
        </span>
      </div>
    </Link>
  )
}

export function PackageCardSkeleton() {
  return (
    <div className="h-full animate-pulse rounded-card bg-mist p-3" aria-hidden="true">
      <div className="aspect-[4/3] rounded-[16px] bg-line/50" />
      <div className="space-y-3 px-3 pb-6 pt-5">
        <div className="h-6 w-2/3 rounded bg-line/60" />
        <div className="h-4 w-1/2 rounded bg-line/40" />
        <div className="h-4 w-3/4 rounded bg-line/40" />
      </div>
    </div>
  )
}
