import { ViewTransition, useLayoutEffect } from 'react'
import { Link, Outlet, Route, Routes, useLocation, useNavigationType } from 'react-router'
import { MotionConfig } from 'motion/react'
import { Nav } from './components/Nav'
import { Footer } from './components/Footer'
import { CartDrawer } from './components/CartDrawer'
import { useTitle } from './lib/hooks'
import Home from './pages/Home'
import Buffets from './pages/Buffets'
import PackagePage from './pages/Package'
import Checkout from './pages/Checkout'
import Track from './pages/Track'

export default function App() {
  return (
    <Routes>
      <Route element={<Layout />}>
        <Route index element={<Home />} />
        <Route path="buffets" element={<Buffets />} />
        <Route path="buffets/:id" element={<PackagePage />} />
        <Route path="checkout" element={<Checkout />} />
        <Route path="track" element={<Track />} />
        <Route path="track/:ref" element={<Track />} />
        <Route path="*" element={<NotFound />} />
      </Route>
    </Routes>
  )
}

/**
 * Shared frame for every page. The <ViewTransition> boundary makes React run
 * page changes through the browser's View Transitions API: pages cross-fade,
 * and elements with the same transition name (package photos) morph between
 * pages. Browsers without support simply switch pages instantly.
 */
function Layout() {
  return (
    // Visitors who ask their system for reduced motion get no movement.
    <MotionConfig reducedMotion="user">
      <a
        href="#main"
        className="absolute left-3 top-[-60px] z-[60] rounded-lg bg-ink px-4 py-2 text-white focus:top-3"
      >
        Skip to content
      </a>
      <Nav />
      <ScrollToTop />
      <ViewTransition>
        <main id="main" className="min-h-[70vh]">
          <Outlet />
        </main>
      </ViewTransition>
      <Footer />
      <CartDrawer />
    </MotionConfig>
  )
}

/** New pages start at the top; Back and Forward keep the browser's position. */
function ScrollToTop() {
  const { pathname } = useLocation()
  const type = useNavigationType()
  useLayoutEffect(() => {
    if (type !== 'POP') window.scrollTo(0, 0)
  }, [pathname, type])
  return null
}

function NotFound() {
  useTitle('Page not found')
  return (
    <div className="container-page py-32 text-center">
      <p className="eyebrow">404</p>
      <h1 className="headline mt-3">This page isn't on the menu.</h1>
      <p className="mt-4 text-lg text-muted">The link may be old, or the address mistyped.</p>
      <Link to="/" className="btn-primary mt-8 px-6 py-3">
        Go to the home page
      </Link>
    </div>
  )
}
