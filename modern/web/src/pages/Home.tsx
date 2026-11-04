import { Fragment, useRef } from 'react'
import { Link } from 'react-router'
import { motion, useScroll, useTransform } from 'motion/react'
import { useApi } from '../lib/api'
import { useTitle } from '../lib/hooks'
import { money } from '../lib/format'
import type { Package, Rules } from '../lib/types'
import { PackageCard, PackageCardSkeleton } from '../components/PackageCard'
import { AmbientBackground, Reveal, easeApple } from '../components/Motion'
import { ArrowRightIcon } from '../components/Icons'

const headline = ['Buffets,', 'made', 'effortless.']

const steps = [
  { title: 'Choose a package', text: 'Local favourites, Nusantara classics or a dim sum high tea, priced per guest.' },
  { title: 'Pick every dish', text: 'Choose your mains, sides and desserts, and watch your buffet line come together.' },
  { title: 'Pay, then track it live', text: 'Each update from our kitchen appears on your order page the moment it happens.' },
]

const occasions = [
  { image: '/images/occasion-corporate.jpg', alt: 'A buffet table with chafing dishes at an outdoor event', title: 'Corporate events', text: 'Meetings, seminars and launches, set up before you break.' },
  { image: '/images/occasion-family.jpg', alt: 'A family-style table of Asian dishes seen from above', title: 'Family gatherings', text: 'Home parties of 20 guests or more.' },
  { image: '/images/occasion-celebration.jpg', alt: 'Red and pink kueh apam on a cooling rack', title: 'Celebrations', text: 'Birthdays, weddings and festivals.' },
]

export default function Home() {
  useTitle(undefined)
  const { data: packages, error, retry } = useApi<Package[]>('/packages')
  const { data: rules } = useApi<Rules>('/rules')
  const featured = packages?.filter((p) => p.isFeatured)

  // The hero photo eases from slightly zoomed in to full size as it scrolls into view.
  const heroPhoto = useRef<HTMLDivElement>(null)
  const { scrollYProgress } = useScroll({ target: heroPhoto, offset: ['start end', 'end start'] })
  const heroScale = useTransform(scrollYProgress, [0, 0.55], [1.18, 1])
  const heroY = useTransform(scrollYProgress, [0, 1], ['-4%', '6%'])

  const freshPhoto = useRef<HTMLDivElement>(null)
  const fresh = useScroll({ target: freshPhoto, offset: ['start end', 'end start'] })
  const freshY = useTransform(fresh.scrollYProgress, [0, 1], ['-8%', '8%'])

  return (
    <>
      <section className="relative isolate overflow-hidden pb-10 pt-20 sm:pt-28">
        <AmbientBackground />
        <div className="container-page text-center">
          <motion.p
            className="eyebrow"
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, ease: easeApple }}
          >
            Buffet catering across Singapore
          </motion.p>
          <h1 className="mx-auto mt-4 max-w-4xl text-5xl font-semibold leading-[1.02] tracking-tight sm:text-7xl lg:text-8xl">
            {/* Real spaces between the animated words, so screen readers and
                copy-paste see "Buffets, made effortless." */}
            {headline.map((word, i) => (
              <Fragment key={word}>
                {i > 0 && ' '}
                <motion.span
                  className="inline-block"
                  initial={{ opacity: 0, y: 40, filter: 'blur(8px)' }}
                  animate={{ opacity: 1, y: 0, filter: 'blur(0px)' }}
                  transition={{ duration: 0.9, ease: easeApple, delay: 0.1 + i * 0.12 }}
                >
                  {word}
                </motion.span>
              </Fragment>
            ))}
          </h1>
          <motion.p
            className="mx-auto mt-6 max-w-2xl text-lg text-muted sm:text-xl"
            initial={{ opacity: 0, y: 16 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.8, ease: easeApple, delay: 0.5 }}
          >
            Choose a package, pick every dish, and follow your order live from our kitchen to your table.
          </motion.p>
          <motion.div
            className="mt-8 flex flex-wrap items-center justify-center gap-3"
            initial={{ opacity: 0, y: 16 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.8, ease: easeApple, delay: 0.65 }}
          >
            <Link to="/buffets" className="btn-primary px-6 py-3">
              Browse buffets
            </Link>
            <Link to="/track" className="btn-ghost px-6 py-3">
              Track an order <ArrowRightIcon width={16} height={16} />
            </Link>
          </motion.div>
        </div>

        <div className="container-page mt-14 sm:mt-20">
          <motion.div
            ref={heroPhoto}
            className="relative aspect-[16/10] overflow-hidden rounded-card bg-mist sm:aspect-[21/9]"
            initial={{ opacity: 0, y: 40 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 1.1, ease: easeApple, delay: 0.4 }}
          >
            <motion.img
              src="/images/hero-spread.jpg"
              alt="A table of noodles, curries, spring rolls and prawns seen from above"
              className="h-full w-full object-cover"
              style={{ scale: heroScale, y: heroY }}
            />
          </motion.div>
        </div>
      </section>

      <section className="container-page py-20" aria-labelledby="featured-title">
        <Reveal className="mb-10 text-center">
          <p className="eyebrow">Our packages</p>
          <h2 id="featured-title" className="headline mt-3">
            Three ways to feast.
          </h2>
        </Reveal>
        {error ? (
          <div className="rounded-card bg-mist p-10 text-center">
            <p>{error.message}</p>
            <button type="button" onClick={retry} className="btn-outline mt-4">
              Try again
            </button>
          </div>
        ) : (
          <ul className="grid gap-6 md:grid-cols-3">
            {featured
              ? featured.map((pkg, i) => (
                  <Reveal as="li" key={pkg.id} delay={i * 0.1}>
                    <PackageCard pkg={pkg} />
                  </Reveal>
                ))
              : [0, 1, 2].map((i) => (
                  <li key={i}>
                    <PackageCardSkeleton />
                  </li>
                ))}
          </ul>
        )}
      </section>

      <section className="bg-mist py-24" aria-labelledby="how-title">
        <div className="container-page">
          <Reveal className="text-center">
            <p className="eyebrow">How it works</p>
            <h2 id="how-title" className="headline mt-3">
              Three steps. No phone calls.
            </h2>
          </Reveal>
          <ol className="mt-14 grid gap-6 md:grid-cols-3">
            {steps.map((s, i) => (
              <Reveal as="li" key={s.title} delay={i * 0.12} className="rounded-card bg-white p-8">
                <span className="grid h-10 w-10 place-items-center rounded-full bg-accent-soft text-lg font-semibold text-accent">
                  {i + 1}
                </span>
                <h3 className="mt-6 text-xl font-semibold tracking-tight">{s.title}</h3>
                <p className="mt-2 text-muted">{s.text}</p>
              </Reveal>
            ))}
          </ol>
        </div>
      </section>

      <section className="container-page py-24" aria-labelledby="occasions-title">
        <Reveal className="mb-12 max-w-2xl">
          <p className="eyebrow">For every occasion</p>
          <h2 id="occasions-title" className="headline mt-3">
            From boardrooms to backyards.
          </h2>
        </Reveal>
        <ul className="grid gap-5 md:grid-cols-3">
          {occasions.map((o, i) => (
            <Reveal as="li" key={o.title} delay={i * 0.1}>
              <figure className="group relative aspect-[3/4] overflow-hidden rounded-card bg-mist">
                <img
                  src={o.image}
                  alt={o.alt}
                  loading="lazy"
                  className="h-full w-full object-cover transition duration-[1.2s] ease-apple group-hover:scale-105"
                />
                <figcaption className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 via-black/30 to-transparent p-7 pt-24 text-white">
                  <p className="text-xl font-semibold tracking-tight">{o.title}</p>
                  <p className="mt-1 text-[15px] text-white/80">{o.text}</p>
                </figcaption>
              </figure>
            </Reveal>
          ))}
        </ul>
      </section>

      <section className="container-page grid items-center gap-12 py-12 lg:grid-cols-2" aria-labelledby="fresh-title">
        <Reveal>
          <div ref={freshPhoto} className="aspect-[4/3] overflow-hidden rounded-card bg-mist">
            <motion.img
              src="/images/feature-fresh.jpg"
              alt="Steaming plates of stir-fried vegetables, tofu and rice"
              loading="lazy"
              className="h-[116%] w-full object-cover"
              style={{ y: freshY }}
            />
          </div>
        </Reveal>
        <Reveal delay={0.1}>
          <p className="eyebrow">Cooked fresh</p>
          <h2 id="fresh-title" className="headline mt-3">
            Made the same morning.
          </h2>
          <p className="mt-5 max-w-lg text-lg text-muted">
            Every buffet is cooked on the day and delivered hot, with chafing dishes, serving ware and a crew who set
            it all up for you.
          </p>
          <dl className="mt-10 grid grid-cols-3 gap-6">
            <div>
              <dt className="text-[13px] text-muted">Notice needed</dt>
              <dd className="mt-1 text-3xl font-semibold tracking-tight">{rules?.leadDays ?? 3} days</dd>
            </div>
            <div>
              <dt className="text-[13px] text-muted">Free delivery from</dt>
              <dd className="mt-1 text-3xl font-semibold tracking-tight">
                {money(rules?.freeDeliveryMin ?? 500).replace('.00', '')}
              </dd>
            </div>
            <div>
              <dt className="text-[13px] text-muted">Guests</dt>
              <dd className="mt-1 text-3xl font-semibold tracking-tight">20–{rules?.maxGuests ?? 500}</dd>
            </div>
          </dl>
        </Reveal>
      </section>

      <section className="container-page pt-20">
        <Reveal className="relative isolate overflow-hidden rounded-card bg-ink px-8 py-16 text-center text-white sm:px-16">
          <div
            aria-hidden="true"
            className="absolute -top-32 left-1/2 -z-10 h-80 w-[40rem] -translate-x-1/2 rounded-full bg-accent/40 blur-3xl"
          />
          <h2 className="text-3xl font-semibold tracking-tight sm:text-5xl">Planning something?</h2>
          <p className="mx-auto mt-4 max-w-xl text-lg text-white/70">
            Build your buffet in a few minutes. No account needed; we email your confirmation.
          </p>
          <Link to="/buffets" className="btn mt-8 bg-white px-6 py-3 text-ink hover:bg-white/90">
            Start your order
          </Link>
        </Reveal>
      </section>
    </>
  )
}
