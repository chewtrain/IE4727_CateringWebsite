import { Link } from 'react-router'
import { useApi } from '../lib/api'
import type { Rules } from '../lib/types'

// The PHP base version: About, Contact and the staff area live there, and it
// uses the same database, so staff manage orders placed in this app too.
export const classicUrl = (import.meta.env.VITE_CLASSIC_URL as string | undefined) ?? 'http://localhost/makanco/'

export function Footer() {
  const { data: rules } = useApi<Rules>('/rules')
  return (
    <footer className="mt-24 bg-mist text-[13px] text-muted">
      <div className="container-page grid gap-8 py-12 sm:grid-cols-3">
        <div>
          <p className="text-[15px] font-semibold text-ink">Makan &amp; Co.</p>
          <p className="mt-2 max-w-xs">Buffet catering for offices, families and celebrations across Singapore.</p>
        </div>
        <div>
          <p className="font-semibold text-ink">Order</p>
          <ul className="mt-2 space-y-1.5">
            <li>
              <Link to="/buffets" className="hover:text-ink">
                Buffet packages
              </Link>
            </li>
            <li>
              <Link to="/track" className="hover:text-ink">
                Track an order
              </Link>
            </li>
            <li>
              <a href={`${classicUrl}about.php`} className="hover:text-ink">
                Delivery terms &amp; FAQ
              </a>
            </li>
            <li>
              <a href={`${classicUrl}contact.php`} className="hover:text-ink">
                Contact &amp; feedback
              </a>
            </li>
          </ul>
        </div>
        <div>
          <p className="font-semibold text-ink">Talk to us</p>
          <ul className="mt-2 space-y-1.5">
            <li>{rules?.contactPhone ?? '+65 6123 4567'}</li>
            <li>{rules?.contactEmail ?? 'orders@makanco.local'}</li>
            <li>
              <a href={`${classicUrl}admin.php`} className="hover:text-ink">
                Staff area
              </a>
            </li>
          </ul>
        </div>
      </div>
      <div className="container-page border-t border-line/70 py-5">
        <p>
          © {new Date().getFullYear()} Makan &amp; Co. · IE4727 project, modern version (React, Tailwind CSS, Go).{' '}
          <a href={classicUrl} className="underline-offset-2 hover:text-ink hover:underline">
            View the classic site
          </a>
        </p>
      </div>
    </footer>
  )
}
