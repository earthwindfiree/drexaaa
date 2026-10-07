import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { ApiError, apiRequest } from '../lib/api'
import { useDocumentTitle } from '../hooks/useDocumentTitle'
import type { PublicStrategy, PublicStrategyResponse } from '../types/api'

type LoadState = 'loading' | 'ready' | 'not-found' | 'error'

type StrategyResult =
  | { strategyId: string; state: 'loading' }
  | { strategyId: string; state: 'ready'; strategy: PublicStrategy }
  | { strategyId: string; state: 'not-found' }
  | { strategyId: string; state: 'error'; error: string }

function PublicStrategyDetailsPage() {
  const { strategyId } = useParams()
  const [result, setResult] = useState<StrategyResult>(() => ({ strategyId: strategyId ?? '', state: 'loading' }))
  const [retryKey, setRetryKey] = useState(0)
  const currentResult: StrategyResult = result.strategyId === strategyId
    ? result
    : { strategyId: strategyId ?? '', state: 'loading' }
  const state: LoadState = currentResult.state
  const strategy = currentResult.state === 'ready' ? currentResult.strategy : null

  useDocumentTitle(strategy?.name ?? 'Strategy Details')

  useEffect(() => {
    let active = true
    const requestedStrategyId = strategyId ?? ''

    void apiRequest<PublicStrategyResponse>(`/api/strategies/${encodeURIComponent(requestedStrategyId)}`)
      .then((response) => {
        if (!active) return
        if (!response?.data || !isStrategy(response.data)) {
          setResult({ strategyId: requestedStrategyId, state: 'not-found' })
          return
        }

        setResult({ strategyId: requestedStrategyId, state: 'ready', strategy: response.data })
      })
      .catch((requestError: unknown) => {
        if (!active) return
        if (requestError instanceof ApiError && requestError.status === 404) {
          setResult({ strategyId: requestedStrategyId, state: 'not-found' })
          return
        }

        setResult({ strategyId: requestedStrategyId, state: 'error', error: errorMessage(requestError) })
      })

    return () => {
      active = false
    }
  }, [strategyId, retryKey])

  return (
    <div className="public-shell min-h-screen text-[var(--theme-heading)]">
      <header className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-6 py-6 lg:px-8">
        <Link to="/" className="flex items-center gap-3" aria-label="Mercury Managed home">
          <span className="public-brand-mark">M</span>
          <span>
            <span className="block text-lg font-semibold tracking-tight text-[var(--theme-heading)]">Mercury Managed</span>
            <span className="block text-[10px] uppercase tracking-[0.24em] text-[var(--theme-muted)]">Demo Platform</span>
          </span>
        </Link>
        <div className="flex items-center gap-3">
          <a href="/#strategies" className="public-secondary-action rounded-full px-4 py-2 text-sm transition">
            All strategies
          </a>
          <Link to="/register" className="public-primary-action inline-flex rounded-full px-4 py-2 text-sm font-medium transition">
            Get Started
          </Link>
        </div>
      </header>

      <main className="mx-auto max-w-7xl px-6 pb-20 pt-8 lg:px-8">
        <a href="/#strategies" className="inline-flex min-h-10 items-center text-sm text-[var(--theme-accent)] transition hover:text-[var(--theme-heading)]">
          <span aria-hidden="true" className="mr-2">←</span> Back to strategies
        </a>

        {state === 'loading' && (
          <div role="status" aria-label="Loading strategy details" className="mt-8 animate-pulse space-y-6">
            <div className="h-4 w-40 rounded bg-[var(--theme-surface)]" />
            <div className="h-12 w-2/3 rounded bg-[var(--theme-surface-raised)]" />
            <div className="public-card h-40 rounded-3xl" />
            <span className="sr-only">Loading strategy details...</span>
          </div>
        )}

        {state === 'not-found' && (
          <section className="public-card mx-auto mt-12 max-w-2xl p-7 sm:p-10">
            <p className="text-xs font-semibold uppercase tracking-[0.2em] text-[var(--theme-muted)]">404 · Strategy not found</p>
            <h1 className="mt-4 font-display text-3xl text-[var(--theme-heading)]">This strategy is not available.</h1>
            <p className="mt-3 text-sm leading-6 text-[var(--theme-muted)]">It may have been removed, deactivated, or the address may be incorrect.</p>
            <a href="/#strategies" className="public-primary-action mt-7 inline-flex min-h-11 items-center justify-center rounded-full px-5 text-sm font-semibold">
              Browse available strategies
            </a>
          </section>
        )}

        {state === 'error' && (
          <section role="alert" className="public-error mx-auto mt-12 max-w-2xl rounded-3xl p-7 sm:p-10">
            <p className="text-xs font-semibold uppercase tracking-[0.2em]">Strategy details unavailable</p>
            <h1 className="mt-4 font-display text-3xl text-[var(--theme-heading)]">We could not load this strategy.</h1>
            <p className="mt-3 text-sm leading-6">{currentResult.state === 'error' ? currentResult.error : ''}</p>
            <button
              type="button"
              onClick={() => {
                setResult({ strategyId: strategyId ?? '', state: 'loading' })
                setRetryKey((current) => current + 1)
              }}
              className="mt-7 inline-flex min-h-11 items-center justify-center rounded-full border border-[rgba(227,155,155,0.35)] px-5 text-sm font-semibold text-[var(--theme-danger)] transition hover:bg-[rgba(227,155,155,0.08)]"
            >
              Try again
            </button>
          </section>
        )}

        {state === 'ready' && strategy && (
          <>
            <section className="public-card relative mt-8 overflow-hidden rounded-[2rem] p-6 sm:p-10 lg:p-14">
              <div className="public-hero-light pointer-events-none absolute inset-0" />
              <div className="relative max-w-3xl">
                <span className="public-kicker inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-medium uppercase tracking-[0.16em]">
                  Demo strategy profile
                </span>
                <h1 className="mt-6 font-display text-4xl tracking-tight text-[var(--theme-heading)] sm:text-5xl">{strategy.name}</h1>
                <p className="mt-5 text-base leading-7 text-[var(--theme-body)] sm:text-lg">{strategy.description}</p>
                <div className="mt-8 grid gap-4 sm:grid-cols-2">
                  <div className="public-surface rounded-2xl p-5">
                    <p className="text-xs uppercase tracking-[0.16em] text-[var(--theme-muted)]">Risk / strategy profile</p>
                    <p className="mt-2 text-xl font-semibold capitalize text-[var(--theme-heading)]">{strategy.risk_profile}</p>
                  </div>
                  <div className="public-surface rounded-2xl p-5">
                    <p className="text-xs uppercase tracking-[0.16em] text-[var(--theme-muted)]">Minimum qualifying balance</p>
                    <p className="mt-2 text-xl font-semibold text-[var(--theme-heading)]">{formatMinimum(strategy.tiers[0]?.minimum_balance)}</p>
                  </div>
                </div>
              </div>
            </section>

            <section className="mt-10">
              <div className="mb-6">
                <p className="text-xs uppercase tracking-[0.2em] text-[var(--theme-accent)]">Qualification</p>
                <h2 className="mt-3 font-display text-2xl text-[var(--theme-heading)] sm:text-3xl">Requirements and included features</h2>
                <p className="mt-3 max-w-3xl text-sm leading-6 text-[var(--theme-muted)]">
                  Qualification is based on the configured managed-balance threshold only. Pending deposits and pending withdrawals do not change tier qualification.
                </p>
              </div>

              <div className="grid gap-5 lg:grid-cols-2">
                {strategy.tiers.map((tier) => (
                  <article key={tier.id} className="public-card rounded-3xl p-5 sm:p-7">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                      <div>
                        <p className="text-xs uppercase tracking-[0.16em] text-[var(--theme-muted)]">Qualification tier</p>
                        <h3 className="mt-2 font-display text-2xl text-[var(--theme-heading)]">{tier.name}</h3>
                      </div>
                      <span className="public-kicker rounded-full px-3 py-1.5 text-sm font-semibold">
                        {formatMinimum(tier.minimum_balance)}
                      </span>
                    </div>
                    <p className="mt-4 text-sm leading-6 text-[var(--theme-body)]">{tier.description}</p>
                    <div className="mt-6 grid gap-6 sm:grid-cols-2">
                      <DetailList title="Benefits" items={tier.benefits} emptyText="No benefits are currently configured." />
                      <DetailList title="Features" items={tier.features} emptyText="No additional features are currently configured." />
                    </div>
                  </article>
                ))}
              </div>
            </section>

            <aside className="public-info-card mt-8 p-5 sm:p-6">
              <p className="text-sm font-semibold text-[var(--theme-accent)]">Demo Account · Simulated Performance</p>
              <p className="mt-2 text-sm leading-6 text-[var(--theme-muted)]">
                Strategy descriptions and account performance are illustrative demo information. No live trading is performed and no returns are guaranteed.
              </p>
            </aside>
          </>
        )}
      </main>
    </div>
  )
}

function DetailList({ title, items, emptyText }: { title: string; items: string[]; emptyText: string }) {
  return (
    <div>
      <h4 className="text-xs font-semibold uppercase tracking-[0.16em] text-[var(--theme-muted)]">{title}</h4>
      {items.length > 0 ? (
        <ul className="mt-3 space-y-2">
          {items.map((item, index) => (
            <li key={`${item}-${index}`} className="flex gap-2 text-sm leading-5 text-[var(--theme-body)]">
              <span aria-hidden="true" className="text-[var(--theme-accent)]">•</span>
              <span>{item}</span>
            </li>
          ))}
        </ul>
      ) : <p className="mt-3 text-sm text-[var(--theme-muted)]">{emptyText}</p>}
    </div>
  )
}

function isStrategy(value: PublicStrategy): boolean {
  return Number.isInteger(value.id)
    && typeof value.name === 'string'
    && typeof value.description === 'string'
    && typeof value.risk_profile === 'string'
    && Array.isArray(value.tiers)
}

function formatMinimum(value: string | undefined): string {
  if (!value || !/^\d+(?:\.\d{1,2})?$/.test(value)) return 'Threshold unavailable'
  const [whole, fraction] = value.split('.')
  const groupedWhole = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',')
  const amount = fraction && Number(fraction) > 0 ? `${groupedWhole}.${fraction.padEnd(2, '0')}` : groupedWhole

  return `$${amount}+`
}

function errorMessage(error: unknown): string {
  if (error instanceof ApiError && error.status === 401) return 'This public page unexpectedly requires authentication.'
  return error instanceof Error ? error.message : 'Please try again in a moment.'
}

export default PublicStrategyDetailsPage
