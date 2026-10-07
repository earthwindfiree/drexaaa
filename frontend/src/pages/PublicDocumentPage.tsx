import { Link } from 'react-router-dom'
import { useDocumentTitle } from '../hooks/useDocumentTitle'

export interface PublicDocumentSection {
  title: string
  paragraphs: string[]
}

function PublicDocumentPage({
  title,
  intro,
  updated,
  sections,
}: {
  title: string
  intro: string
  updated: string
  sections: PublicDocumentSection[]
}) {
  useDocumentTitle(title)

  return (
    <section className="mx-auto w-full max-w-5xl px-6 py-12 sm:py-16 lg:px-8">
      <header className="mb-8 max-w-3xl">
        <p className="public-kicker inline-flex rounded-full px-3 py-1.5 text-xs font-medium uppercase tracking-[0.2em]">
          Mercury Managed · Demo Platform
        </p>
        <h1 className="mt-4 text-3xl font-semibold tracking-tight text-[var(--theme-heading)] sm:text-4xl">{title}</h1>
        <p className="mt-4 text-base leading-7 text-[var(--theme-body)]">{intro}</p>
        <p className="mt-3 text-xs text-[var(--theme-muted)]">{updated}</p>
      </header>

      <div className="public-card divide-y divide-[var(--theme-border)] px-6 sm:px-8">
        {sections.map((section) => (
          <section key={section.title} className="py-6 first:pt-7 last:pb-7">
            <h2 className="text-lg font-semibold text-[var(--theme-heading)]">{section.title}</h2>
            <div className="mt-3 space-y-3 text-sm leading-7 text-[var(--theme-muted)]">
              {section.paragraphs.map((paragraph) => (
                <p key={paragraph}>{paragraph}</p>
              ))}
            </div>
          </section>
        ))}
      </div>

      <p className="mt-7 text-sm text-[var(--theme-muted)]">
        Questions about this notice? Visit the <Link to="/contact" className="text-[var(--theme-accent)] underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-[var(--theme-focus)]">contact page</Link>.
      </p>
    </section>
  )
}

export default PublicDocumentPage
