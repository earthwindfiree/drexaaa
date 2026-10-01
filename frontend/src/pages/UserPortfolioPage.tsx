import { useEffect, useState } from 'react'
import { ApiError, apiRequest } from '../lib/api'
import { useDocumentTitle } from '../hooks/useDocumentTitle'
import type { UserDashboardAccount, UserPerformanceSnapshot, UserPortfolioResponse } from '../types/api'

interface PortfolioAccount extends UserDashboardAccount {
  pending_balance: string
}

type PortfolioGeometry = {
  line: string
  area: string
  last: { x: number; y: number }
} | null

function UserPortfolioPage() {
  const [account, setAccount] = useState<PortfolioAccount | null>(null)
  const [history, setHistory] = useState<UserPerformanceSnapshot[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useDocumentTitle('Portfolio')

  useEffect(() => {
    let active = true

    void apiRequest<UserPortfolioResponse>('/api/portfolio')
      .then((response) => {
        if (!active) return
        if (!response?.data || typeof response.data !== 'object') {
          throw new Error('Portfolio data is unavailable.')
        }

        setAccount(response.data)
        setHistory(Array.isArray(response.data.performance_history) ? response.data.performance_history : [])
      })
      .catch((requestError: unknown) => {
        if (!active) return

        if (requestError instanceof ApiError && requestError.status === 401) {
          setError('Your session has expired. Please sign in again.')
        } else if (requestError instanceof ApiError && requestError.status === 403) {
          setError('You are not authorized to view this portfolio.')
        } else {
          setError(requestError instanceof Error ? requestError.message : 'Unable to load your portfolio.')
        }
      })
      .finally(() => {
        if (active) setLoading(false)
      })

    return () => {
      active = false
    }
  }, [])

  const validHistory = Array.isArray(history) ? history.filter(isValidSnapshot) : []

  return (
    <section className="space-y-6 pb-10 md:space-y-8">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="mb-2 text-[10px] font-semibold uppercase tracking-[0.2em] text-cyan-200/70">Managed account</p>
          <h1 className="font-display text-2xl text-white sm:text-[30px]">Portfolio</h1>
          <p className="mt-2 max-w-xl text-sm text-slate-400">Recorded account value and the mandate currently assigned to your simulated portfolio.</p>
        </div>
        <span className="demo-disclosure">
          <span className="demo-disclosure-mark" aria-hidden="true" />
          Demo Account · Simulated Performance
        </span>
      </div>

      {loading && <PortfolioLoading />}

      {!loading && error && (
        <div role="alert" className="dashboard-message dashboard-error">
          <p className="text-sm font-medium text-rose-100">Portfolio unavailable</p>
          <p className="mt-1 text-sm text-rose-200/70">{error}</p>
        </div>
      )}

      {!loading && !error && account && (
        <>
          <PortfolioSummary account={account} />

          <div className="grid gap-5 xl:grid-cols-[minmax(0,1.7fr)_minmax(260px,0.72fr)]">
            <PortfolioHistory history={validHistory} />
            <PositionDetails account={account} latestSnapshot={validHistory.at(-1) ?? null} />
          </div>

          <MandateDetails account={account} />
        </>
      )}
    </section>
  )
}

function PortfolioSummary({ account }: { account: PortfolioAccount }) {
  const profitLoss = Number(account.total_profit_loss)
  const performance = Number(account.performance_percentage)
  const status = account.trading_status ? humanize(account.trading_status) : 'Status unavailable'

  return (
    <article className="portfolio-overview dashboard-surface">
      <div className="portfolio-valuation">
        <p className="text-xs font-medium text-slate-400">Managed balance</p>
        <p key={account.managed_balance} className="animated-value mt-3 break-all font-display text-4xl leading-none text-white sm:text-6xl">
          {formatCurrency(account.managed_balance)}
        </p>
        <span className={`trading-status mt-5${account.trading_status?.toLowerCase() === 'active' ? ' is-active' : ''}`}>
          <span className="trading-status-indicator" aria-hidden="true" />
          {status}
        </span>
      </div>

      <div className="portfolio-metrics">
        <div className="portfolio-metric">
          <p className="text-[11px] font-medium text-slate-400">Total profit / loss</p>
          <p key={account.total_profit_loss} className={`animated-value mt-2 text-xl font-semibold tabular-nums ${movementColor(profitLoss)}`}>
            {formatCurrency(account.total_profit_loss, true)}
          </p>
          <p className="mt-1 text-[10px] text-slate-500">Simulated account performance</p>
        </div>
        <div className="portfolio-metric">
          <p className="text-[11px] font-medium text-slate-400">Performance percentage</p>
          <p key={account.performance_percentage} className={`animated-value mt-2 text-xl font-semibold tabular-nums ${movementColor(performance)}`}>
            {formatPercentage(account.performance_percentage)}
          </p>
          <p className="mt-1 text-[10px] text-slate-500">Account-level reported figure</p>
        </div>
      </div>
    </article>
  )
}

function PortfolioHistory({ history }: { history: UserPerformanceSnapshot[] }) {
  const geometry = buildPortfolioChart(history)
  const first = history[0] ?? null
  const latest = history.at(-1) ?? null

  return (
    <article className="portfolio-history dashboard-surface dashboard-hover">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <p className="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Portfolio history</p>
          <h2 className="mt-2 text-lg font-medium text-white">Recorded account value</h2>
          <p className="mt-1 text-xs text-slate-500">Persisted snapshots from your simulated managed account</p>
        </div>
        <span className="history-label">{history.length} {history.length === 1 ? 'snapshot' : 'snapshots'}</span>
      </div>

      {latest && (
        <div className="portfolio-latest-value">
          <span className="text-[10px] uppercase tracking-[0.12em] text-slate-500">Latest recorded value</span>
          <span className="font-display text-xl text-white">{formatCurrency(latest.account_value)}</span>
        </div>
      )}

      <div className="portfolio-chart-area" aria-live="polite">
        {history.length === 0 && (
          <div className="chart-message">
            No persisted performance snapshots are available yet. A history view will appear when account snapshots have been recorded.
          </div>
        )}
        {history.length === 1 && (
          <div className="chart-message">
            One persisted snapshot is available. A trend will appear after additional snapshots are recorded.
          </div>
        )}
        {history.length >= 2 && geometry && first && latest && (
          <>
            <svg className="portfolio-chart-svg" viewBox="0 0 800 250" role="img" aria-label="Simulated portfolio value from persisted account snapshots">
              <defs>
                <linearGradient id="portfolio-history-area" x1="0" x2="0" y1="0" y2="1">
                  <stop offset="0%" stopColor="#73d6d2" stopOpacity="0.2" />
                  <stop offset="100%" stopColor="#73d6d2" stopOpacity="0" />
                </linearGradient>
              </defs>
              <path d="M 0 42 H 800 M 0 112 H 800 M 0 182 H 800" className="chart-grid-line" />
              <path d={geometry.area} fill="url(#portfolio-history-area)" />
              <path d={geometry.line} pathLength="1000" className="performance-line" />
              <circle cx={geometry.last.x} cy={geometry.last.y} r="4" className="performance-point" />
            </svg>
            <div className="chart-range-labels">
              <span>{formatDate(first.snapshot_at)}</span>
              <span>{formatDate(latest.snapshot_at)}</span>
            </div>
          </>
        )}
        {history.length >= 2 && !geometry && (
          <div className="chart-message">The available snapshots cannot be plotted safely.</div>
        )}
      </div>

      <div className="portfolio-history-footnote">
        <span className="chart-legend-mark" aria-hidden="true" />
        Demo history · No live trading data
      </div>
    </article>
  )
}

function PositionDetails({
  account,
  latestSnapshot,
}: {
  account: PortfolioAccount
  latestSnapshot: UserPerformanceSnapshot | null
}) {
  return (
    <article className="portfolio-position dashboard-surface dashboard-hover">
      <p className="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Account position</p>
      <h2 className="mt-2 text-lg font-medium text-white">Balance details</h2>

      <div className="portfolio-position-row mt-7">
        <span className="text-xs text-slate-400">Pending balance</span>
        <span className="text-sm font-medium tabular-nums text-slate-200">{formatCurrency(account.pending_balance)}</span>
      </div>
      <p className="mt-2 text-[10px] leading-4 text-slate-500">Displayed separately; not included in managed balance.</p>

      <div className="portfolio-position-row mt-6">
        <span className="text-xs text-slate-400">Trading status</span>
        <span className="text-xs font-medium text-slate-200">{account.trading_status ? humanize(account.trading_status) : 'Unavailable'}</span>
      </div>

      <div className="portfolio-position-row mt-6">
        <span className="text-xs text-slate-400">Latest snapshot</span>
        <span className="text-xs font-medium text-slate-200">{latestSnapshot ? formatDate(latestSnapshot.snapshot_at) : 'Not recorded'}</span>
      </div>
    </article>
  )
}

function MandateDetails({ account }: { account: PortfolioAccount }) {
  const tier = account.tier
  const strategy = tier?.strategy

  return (
    <section className="portfolio-mandate dashboard-surface dashboard-hover">
      <div className="portfolio-mandate-heading">
        <p className="text-[10px] font-semibold uppercase tracking-[0.16em] text-cyan-100/60">Management mandate</p>
        <h2 className="mt-2 font-display text-2xl text-white">Tier &amp; strategy</h2>
      </div>
      <div className="portfolio-mandate-detail">
        <p className="text-[10px] font-medium uppercase tracking-[0.14em] text-slate-500">Current tier</p>
        <p className="mt-2 text-sm font-medium text-slate-100">{tier?.name || 'Tier not assigned'}</p>
        <p className="mt-2 text-xs leading-5 text-slate-400">
          {tier?.description || 'Tier details are not currently available for this account.'}
        </p>
        <p className="mt-4 text-[10px] text-slate-500">
          Qualification threshold: {tier?.minimum_balance ? formatCurrency(tier.minimum_balance) : 'Not specified'}
        </p>
      </div>
      <div className="portfolio-mandate-detail">
        <p className="text-[10px] font-medium uppercase tracking-[0.14em] text-slate-500">Assigned strategy</p>
        <p className="mt-2 text-sm font-medium text-slate-100">{strategy?.name || 'Strategy not assigned'}</p>
        {strategy?.risk_profile && (
          <span className="mt-3 inline-flex border border-white/[0.09] px-2 py-1 text-[10px] text-slate-400">
            {humanize(strategy.risk_profile)} risk profile
          </span>
        )}
        <p className="mt-3 text-xs leading-5 text-slate-400">
          {strategy?.description || 'Strategy details are not currently available for this account.'}
        </p>
      </div>
    </section>
  )
}

function PortfolioLoading() {
  return (
    <div className="space-y-5" aria-label="Loading portfolio" role="status">
      <div className="dashboard-skeleton h-[230px]" />
      <div className="grid gap-5 xl:grid-cols-[minmax(0,1.7fr)_minmax(260px,0.72fr)]">
        <div className="dashboard-skeleton h-[360px]" />
        <div className="dashboard-skeleton h-[360px]" />
      </div>
      <span className="sr-only">Loading portfolio</span>
    </div>
  )
}

function isValidSnapshot(snapshot: unknown): snapshot is UserPerformanceSnapshot {
  if (!snapshot || typeof snapshot !== 'object') return false

  const value = (snapshot as UserPerformanceSnapshot).account_value
  return (typeof value === 'string' && value.trim() !== '' || typeof value === 'number') && Number.isFinite(Number(value))
}

function buildPortfolioChart(points: UserPerformanceSnapshot[]): PortfolioGeometry {
  if (points.length < 2) return null

  const values = points.map((point) => Number(point.account_value))
  if (values.some((value) => !Number.isFinite(value))) return null

  const minimum = Math.min(...values)
  const maximum = Math.max(...values)
  const padding = maximum === minimum ? Math.abs(maximum) * 0.02 || 1 : (maximum - minimum) * 0.12
  const range = maximum - minimum + padding * 2 || 1
  const coordinates = points.map((point, index) => ({
    x: 16 + (index / (points.length - 1)) * 768,
    y: 20 + ((maximum + padding - Number(point.account_value)) / range) * 190,
  }))
  const first = coordinates[0]
  const last = coordinates[coordinates.length - 1]
  if (!first || !last) return null

  const line = coordinates
    .map((point, index) => `${index === 0 ? 'M' : 'L'} ${point.x.toFixed(2)} ${point.y.toFixed(2)}`)
    .join(' ')

  return {
    line,
    area: `${line} L ${last.x.toFixed(2)} 230 L ${first.x.toFixed(2)} 230 Z`,
    last,
  }
}

function formatCurrency(value: unknown, signed = false): string {
  const amount = typeof value === 'number' ? value : typeof value === 'string' && value.trim() ? Number(value) : Number.NaN
  if (!Number.isFinite(amount)) return '--'

  const formatted = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount)

  return signed && amount > 0 ? `+${formatted}` : formatted
}

function formatPercentage(value: unknown): string {
  const percentage = Number(value)
  if (!Number.isFinite(percentage)) return '--'
  return `${percentage > 0 ? '+' : ''}${percentage.toFixed(2)}%`
}

function movementColor(value: number): string {
  if (!Number.isFinite(value) || value === 0) return 'text-slate-200'
  return value > 0 ? 'text-emerald-300' : 'text-rose-300'
}

function humanize(value: string): string {
  return value
    .replace(/[_-]+/g, ' ')
    .replace(/\b\w/g, (character) => character.toUpperCase())
}

function formatDate(value: string | null): string {
  if (!value) return 'Date unavailable'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return 'Date unavailable'
  return new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric', year: 'numeric' }).format(date)
}

export default UserPortfolioPage