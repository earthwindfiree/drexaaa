import { Link } from 'react-router-dom'
import { useDocumentTitle } from '../hooks/useDocumentTitle'

function PublicContactPage() {
  useDocumentTitle('Contact')

  return (
    <section className="mx-auto w-full max-w-5xl px-6 py-12 sm:py-16 lg:px-8">
      <header className="max-w-3xl">
        <p className="public-kicker inline-flex rounded-full px-3 py-1.5 text-xs font-medium uppercase tracking-[0.2em]">
          Mercury Managed · Demo Platform
        </p>
        <h1 className="mt-4 text-3xl font-semibold tracking-tight text-[var(--theme-heading)] sm:text-4xl">Contact</h1>
        <p className="mt-4 text-base leading-7 text-[var(--theme-body)]">
          This site is a demonstration environment. Contact details and a public message-submission channel have not been configured for this instance.
        </p>
      </header>

      <div className="public-card mt-8 max-w-3xl p-6 sm:p-8">
        <h2 className="text-lg font-semibold text-[var(--theme-heading)]">Need to reach the team operating this demo?</h2>
        <p className="mt-3 text-sm leading-7 text-[var(--theme-muted)]">
          Please use a contact channel provided directly by the organization or person who gave you access. We do not display a support email address or collect contact-form submissions here.
        </p>
        <Link
          to="/"
          className="public-secondary-action mt-6 inline-flex min-h-11 items-center justify-center rounded-full px-5 text-sm font-medium transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--theme-focus)]"
        >
          Return to home
        </Link>
      </div>
    </section>
  )
}

export default PublicContactPage
