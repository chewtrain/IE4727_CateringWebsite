import { useEffect, useState } from 'react'
import { Link, NavLink } from 'react-router'
import { AnimatePresence, motion } from 'motion/react'
import { cart, useCart } from '../lib/cart'
import { BagIcon } from './Icons'

const links = [
  { to: '/buffets', label: 'Buffets' },
  { to: '/track', label: 'Track order' },
]

export function Nav() {
  const lines = useCart()
  const [scrolled, setScrolled] = useState(false)

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 8)
    onScroll()
    window.addEventListener('scroll', onScroll, { passive: true })
    return () => window.removeEventListener('scroll', onScroll)
  }, [])

  return (
    <header
      // Own view-transition layer: stays still and on top while pages change.
      style={{ viewTransitionName: 'site-nav' }}
      className={`sticky top-0 z-40 border-b bg-white/75 backdrop-blur-xl backdrop-saturate-150 transition-colors duration-300 ${
        scrolled ? 'border-black/10' : 'border-transparent'
      }`}
    >
      <nav className="container-page flex h-13 items-center gap-6" aria-label="Main">
        <Link to="/" className="text-[19px] font-semibold tracking-tight text-ink">
          Makan &amp; Co.
        </Link>
        <ul className="ml-auto flex items-center gap-5 sm:gap-7">
          {links.map((l) => (
            <li key={l.to}>
              <NavLink
                to={l.to}
                className={({ isActive }) =>
                  `text-[13px] transition hover:opacity-100 ${isActive ? 'font-semibold opacity-100' : 'opacity-75'}`
                }
              >
                {l.label}
              </NavLink>
            </li>
          ))}
        </ul>
        <button
          type="button"
          onClick={cart.open}
          className="relative -mr-1 grid h-9 w-9 place-items-center rounded-full transition hover:bg-mist"
          aria-label={`Open cart, ${lines.length} ${lines.length === 1 ? 'package' : 'packages'}`}
        >
          <BagIcon />
          <AnimatePresence>
            {lines.length > 0 && (
              <motion.span
                key="badge"
                initial={{ scale: 0.4, opacity: 0 }}
                animate={{ scale: 1, opacity: 1 }}
                exit={{ scale: 0.4, opacity: 0 }}
                transition={{ type: 'spring', stiffness: 500, damping: 18 }}
                className="absolute -right-0.5 -top-0.5 grid h-[18px] min-w-[18px] place-items-center rounded-full bg-accent px-1 text-[10px] font-semibold text-white"
                aria-hidden="true"
              >
                {/* Re-keyed on every change so the number pops when a package is added. */}
                <motion.span
                  key={lines.length}
                  initial={{ scale: 1.7 }}
                  animate={{ scale: 1 }}
                  transition={{ type: 'spring', stiffness: 500, damping: 15 }}
                >
                  {lines.length}
                </motion.span>
              </motion.span>
            )}
          </AnimatePresence>
        </button>
      </nav>
    </header>
  )
}
