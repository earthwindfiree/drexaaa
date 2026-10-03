import { Link } from 'react-router-dom'
import { useAuth } from '../features/auth/AuthContext'
import { useDocumentTitle } from '../hooks/useDocumentTitle'

function NotFoundPage() {
  const { user } = useAuth()
  useDocumentTitle('Page not found')

  const destination = user ? '/dashboard' : '/'
  const destinationLabel = user ? 'Return to dashboard' : 'Return home'

  return (
    <main className="public-shell flex min-h-screen items-center justify-center px-6 py-16 text-slate-100">
      <section className="not-found-panel dashboard-surface">
        <p className="text-[10px] font-semibold uppercase tracking-[0.2em] text-cyan-200/70">404 · Not found</p>
        <h1 className="mt-4 font-display text-3xl text-white">This page could not be found.</h1>
        <p className="mt-3 text-sm leading-6 text-slate-400">The address may be incorrect, or the page may have moved.</p>
        <Link to={destination} className="public-primary-action mt-7 inline-flex min-h-10 items-center justify-center px-4 text-sm font-semibold transition">
          {destinationLabel}
        </Link>
      </section>
    </main>
  )
}

export default NotFoundPage