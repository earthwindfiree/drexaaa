import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { ApiError, apiRequest } from '../lib/api'
import type {
  UserDashboardAccount,
  UserDashboardResponse,
  UserDashboardTransaction,
  UserPerformanceSnapshot,
  UserPortfolioResponse,
} from '../types/api'
import { useDocumentTitle } from '../hooks/useDocumentTitle'

type HistoryState = 'loading' | 'ready' | 'unavailable'

function UserDashboardPage() {
  const [account, setAccount] = useState<UserDashboardAccount | null>(null)
  const [transactions, setTransactions] = useState<UserDashboardTransaction[]>([])
  const [history, setHistory] = useState<UserPerformanceSnapshot[]>([])
  const [loading, setLoading] = useState(true)
  const [historyState, setHistoryState] = useState<HistoryState>('loading')
  const [error, setError] = useState<string | null>(null)

  useDocumentTitle('Overview')

  useEffect(() => {
    let active = true

    void apiRequest<UserDashboardResponse>('/api/dashboard')
      .then((response) => {
        if (!active) return
        setAccount(response.data.account)
        setTransactions(response.data.recent_transactions)
      })
      .catch((requestError: unknown) => {
        if (!active) return

        if (requestError instanceof ApiError && requestError.status === 401) {
          setError('Your session has expired. Please sign in again.')
        } else if (requestError instanceof ApiError && requestError.status === 403) {
          setError('You are not authorized to view this dashboard.')
        } else {
          setError(requestError instanceof Error ? requestError.message : 'Unable to load your account overview.')
        }
      })
      .finally(() => {
        if (active) setLoading(false)
      })

    void apiRequest<UserPortfolioResponse>('/api/portfolio')
      .then((response) => {
        if (!active) return
        setHistory(response.data.performance_history)
        setHistoryState('ready')
      })
      .catch(() => {
        if (active) setHistoryState('unavailable')
      })

    return () => {
      active = false
    }
  }, [])

  return (
    <section className="space-y-6 pb-10 md:space-y-8">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="mb-2 text-[10px] font-semibold uppercase tracking-[0.2em] text-cyan-200/70">Account overview</p>
          <h1 className="font-display text-2xl text-white sm:text-[30px]">Your managed account</h1>
          <p className="mt-2 max-w-xl text-sm text-slate-400">A clear view of your account balance, assigned strategy, and recorded performance.</p>
        </div>
        <span className="demo-disclosure">
          <span className="demo-disclosure-mark" aria-hidden="true" />
          Demo Account · Simulated Performance
        </span>
      </div>

      {loading && <DashboardLoading />}

      {!loading && error && (
        <div role="alert" className="dashboard-message dashboard-error">
          <p className="text-sm font-medium text-rose-100">Account overview unavailable</p>
          <p className="mt-1 text-sm text-rose-200/70">{error}</p>
        </div>
      )}

      {!loading && !error && account && (
        <>
          <div className="grid gap-5 xl:grid-cols-[minmax(0,1.55fr)_minmax(290px,0.85fr)]">
            <BalancePanel account={account} />
            <StrategyPanel account={account} />
          </div>

          <div className="grid gap-5 xl:grid-cols-[minmax(0,1.55fr)_minmax(290px,0.85fr)]">
            <PerformancePanel history={history} state={historyState} />
            <ActivityPanel transactions={transactions} />
          </div>
        </>
      )}
    </section>
  )
}

function BalancePanel({ account }: { account: UserDashboardAccount }) {
  const profitLoss = Number(account.total_profit_loss)
  const percentage = Number(account.performance_percentage)
  const tradingActive = account.trading_status?.toLowerCase() === 'active'

  return (
    <article className="balance-panel dashboard-surface dashboard-hover">
      <div className="relative z-10 flex flex-wrap items-start justify-between gap-5">
        <div>
          <p className="text-xs font-medium text-slate-400">Managed balance</p>
          <p key={account.managed_balance} className="animated-value mt-3 break-all font-display text-4xl leading-none text-white sm:text-6xl">
            {formatCurrency(account.managed_balance)}
          </p>
        </div>
        <span className={`trading-status${tradingActive ? ' is-active' : ''}`}>
          <span className="trading-status-indicator" aria-hidden="true" />
          {account.trading_status ? humanize(account.trading_status) : 'Status unavailable'}
        </span>
      </div>

      <div className="relative z-10 mt-9 grid grid-cols-2 gap-5 border-t border-white/[0.09] pt-5 sm:mt-11 sm:gap-8">
        <div>
          <p className="text-[11px] font-medium text-slate-400">Total profit / loss</p>
          <p
            key={account.total_profit_loss}
            className={`animated-value mt-2 text-lg font-semibold tabular-nums ${movementColor(profitLoss)}`}
          >
            {formatCurrency(account.total_profit_loss, true)}
          </p>
        </div>
        <div>
          <p className="text-[11px] font-medium text-slate-400">Performance</p>
          <p
            key={account.performance_percentage}
            className={`animated-value mt-2 text-lg font-semibold tabular-nums ${movementColor(percentage)}`}
          >
            {formatPercentage(account.performance_percentage)}
          </p>
        </div>
      </div>
      <span className="balance-orbit balance-orbit-one" aria-hidden="true" />
      <span className="balance-orbit balance-orbit-two" aria-hidden="true" />
    </article>
  )
}

function StrategyPanel({ account }: { account: UserDashboardAccount }) {
  const tier = account.tier
  const strategy = tier?.strategy

  return (
    <article className="strategy-panel dashboard-surface dashboard-hover">
      <div className="flex items-start justify-between gap-4">
        <div>
          <p className="text-[10px] font-semibold uppercase tracking-[0.18em] text-cyan-100/60">Strategy identity</p>
          <h2 className="mt-3 font-display text-2xl text-white">{tier?.name || 'Tier not assigned'}</h2>
        </div>
        <span className="strategy-index" aria-hidden="true">01</span>
      </div>

      <div className="mt-7 border-t border-white/[0.08] pt-5">
        <p className="text-[10px] font-medium uppercase tracking-[0.15em] text-slate-500">Assigned strategy</p>
        <p className="mt-2 text-sm font-medium text-slate-100">{strategy?.name || 'Strategy not assigned'}</p>
        {strategy?.risk_profile && (
          <p className="mt-2 inline-flex border border-white/[0.09] px-2 py-1 text-[10px] text-slate-400">
            {humanize(strategy.risk_profile)} risk profile
          </p>
        )}
      </div>

      <p className="mt-5 min-h-10 text-xs leading-5 text-slate-400">
        {strategy?.description || tier?.description || 'Tier and strategy details will appear here when they are available for your account.'}
      </p>

      <div className="mt-6 flex items-center justify-between gap-3 border-t border-white/[0.08] pt-4">
        <span className="text-[10px] uppercase tracking-[0.13em] text-slate-500">Qualification threshold</span>
        <span className="text-xs font-medium tabular-nums text-slate-200">
          {tier?.minimum_balance ? formatCurrency(tier.minimum_balance) : 'Not specified'}
        </span>
      </div>
    </article>
  )
}

function PerformancePanel({ history, state }: { history: UserPerformanceSnapshot[]; state: HistoryState }) {
  const validHistory = Array.isArray(history)
    ? history.filter((point) => {
      const value = point?.account_value
      return typeof value === 'string' && value.trim() !== '' && Number.isFinite(Number(value))
    })
    : []
  const geometry = validHistory.length >= 2 ? buildChart(validHistory) : null

  return (
    <article className="dashboard-surface dashboard-hover overflow-hidden">
      <div className="flex flex-wrap items-start justify-between gap-3 px-5 pt-5 sm:px-6 sm:pt-6">
        <div>
          <p className="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Account history</p>
          <h2 className="mt-2 text-base font-medium text-white">Performance over time</h2>
        </div>
        <span className="history-label">Simulated</span>
      </div>

      <div className="performance-chart" aria-live="polite">
        {state === 'loading' && <ChartMessage>Loading recorded performance…</ChartMessage>}
        {state === 'unavailable' && <ChartMessage>Performance history is unavailable right now.</ChartMessage>}
        {state === 'ready' && !geometry && (
          <ChartMessage>
            {validHistory.length === 1
              ? 'One persisted snapshot is available. A trend will appear after more snapshots are recorded.'
              : 'No historical snapshots are available yet.'}
          </ChartMessage>
        )}
        {state === 'ready' && geometry && (
          <>
            <svg className="performance-svg" viewBox="0 0 800 220" role="img" aria-label="Simulated account value from persisted performance snapshots">
              <defs>
                <linearGradient id="performance-area" x1="0" x2="0" y1="0" y2="1">
                  <stop offset="0%" stopColor="#73d6d2" stopOpacity="0.2" />
                  <stop offset="100%" stopColor="#73d6d2" stopOpacity="0" />
                </linearGradient>
              </defs>
              <path d="M 0 38 H 800 M 0 104 H 800 M 0 170 H 800" className="chart-grid-line" />
              <path d={geometry.area} fill="url(#performance-area)" />
              <path d={geometry.line} pathLength="1000" className="performance-line" />
              <circle cx={geometry.last.x} cy={geometry.last.y} r="4" className="performance-point" />
            </svg>
            <div className="chart-range-labels">
              <span>{formatDate(validHistory[0].snapshot_at)}</span>
              <span>{formatDate(validHistory[validHistory.length - 1].snapshot_at)}</span>
            </div>
          </>
        )}
      </div>
      <div className="flex items-center gap-2 border-t border-white/[0.07] px-5 py-3 text-[10px] text-slate-500 sm:px-6">
        <span className="chart-legend-mark" aria-hidden="true" />
        Persisted account-value snapshots · Demo data
      </div>
    </article>
  )
}

function ActivityPanel({ transactions }: { transactions: UserDashboardTransaction[] }) {
  return (
    <article className="dashboard-surface dashboard-hover overflow-hidden">
      <div className="flex items-center justify-between gap-3 px-5 pt-5 sm:px-6 sm:pt-6">
        <div>
          <p className="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Account activity</p>
          <h2 className="mt-2 text-base font-medium text-white">Recent transactions</h2>
        </div>
        <Link to="/transactions" className="text-[11px] text-cyan-100/70 transition hover:text-cyan-100">View all</Link>
      </div>

      {transactions.length === 0 ? (
        <div className="px-5 py-9 text-center text-xs text-slate-500 sm:px-6">No recent transactions to display.</div>
      ) : (
        <ul className="mt-4 divide-y divide-white/[0.06]">
          {transactions.map((transaction) => (
            <li key={transaction.id} className="transaction-row">
              <span className="transaction-mark" aria-hidden="true">{transaction.type.slice(0, 1).toUpperCase()}</span>
              <span className="min-w-0 flex-1">
                <span className="block truncate text-xs font-medium text-slate-200">{humanize(transaction.type)}</span>
                <span className="mt-1 block truncate text-[10px] text-slate-500">
                  {transaction.description || transaction.asset?.symbol || transaction.reference}
                </span>
              </span>
              <span className="shrink-0 text-right">
                <span className="block text-xs font-medium tabular-nums text-slate-200">{formatCurrency(transaction.usd_amount)}</span>
                <span className="mt-1 block text-[10px] text-slate-500">{formatDate(transaction.occurred_at)}</span>
              </span>
            </li>
          ))}
        </ul>
      )}
    </article>
  )
}

function DashboardLoading() {
  return (
    <div className="space-y-5" aria-label="Loading account overview" role="status">
      <div className="grid gap-5 xl:grid-cols-[minmax(0,1.55fr)_minmax(290px,0.85fr)]">
        <div className="dashboard-skeleton h-[270px]" />
        <div className="dashboard-skeleton h-[270px]" />
      </div>
      <div className="grid gap-5 xl:grid-cols-[minmax(0,1.55fr)_minmax(290px,0.85fr)]">
        <div className="dashboard-skeleton h-[300px]" />
        <div className="dashboard-skeleton h-[300px]" />
      </div>
      <span className="sr-only">Loading account overview</span>
    </div>
  )
}

function ChartMessage({ children }: { children: string }) {
  return <div className="chart-message">{children}</div>
}

function buildChart(points: UserPerformanceSnapshot[]) {
  if (points.length < 2) return null

  const values = points.map((point) => Number(point.account_value))
  if (values.some((value) => !Number.isFinite(value))) return null

  const minimum = Math.min(...values)
  const maximum = Math.max(...values)
  const padding = maximum === minimum ? Math.abs(maximum) * 0.02 || 1 : (maximum - minimum) * 0.12
  const range = maximum - minimum + padding * 2 || 1
  const coordinates = points.map((point, index) => ({
    x: 16 + (index / (points.length - 1)) * 768,
    y: 18 + ((maximum + padding - Number(point.account_value)) / range) * 164,
  }))
  const line = coordinates.map((point, index) => `${index === 0 ? 'M' : 'L'} ${point.x.toFixed(2)} ${point.y.toFixed(2)}`).join(' ')
  const first = coordinates[0]
  const last = coordinates[coordinates.length - 1]
  if (!first || !last) return null

  return {
    line,
    area: `${line} L ${last.x.toFixed(2)} 190 L ${first.x.toFixed(2)} 190 Z`,
    last,
  }
}

function formatCurrency(value: string, signed = false): string {
  const amount = Number(value)
  if (!Number.isFinite(amount)) return '--'

  const formatted = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount)

  return signed && amount > 0 ? `+${formatted}` : formatted
}

function formatPercentage(value: string): string {
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
  return new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric' }).format(date)
}

export default UserDashboardPage