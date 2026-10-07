import { useEffect, useState } from 'react'
import { ApiError, apiRequest } from '../lib/api'
import { useDocumentTitle } from '../hooks/useDocumentTitle'
import type { MarketHistoryResponse, UserMarketAsset, UserMarketsResponse } from '../types/api'

type RangeKey = '24h' | '7d' | '30d'

function UserMarketsPage() {
  const [assets, setAssets] = useState<UserMarketAsset[]>([])
  const [selectedId, setSelectedId] = useState<number | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useDocumentTitle('Markets')

  useEffect(() => {
    let active = true

    void apiRequest<UserMarketsResponse>('/api/markets')
      .then((response) => {
        if (!active) return

        const receivedAssets = Array.isArray(response?.data) ? response.data.filter(isMarketAsset) : []
        const activeAssets = receivedAssets.filter((asset) => asset.active)
        setAssets(activeAssets)
        setSelectedId((currentId) => activeAssets.some((asset) => asset.id === currentId)
          ? currentId
          : activeAssets[0]?.id ?? null)
      })
      .catch((requestError: unknown) => {
        if (!active) return

        if (requestError instanceof ApiError && requestError.status === 401) {
          setError('Your session has expired. Please sign in again.')
        } else if (requestError instanceof ApiError && requestError.status === 403) {
          setError('You are not authorized to view simulated markets.')
        } else {
          setError(requestError instanceof Error ? requestError.message : 'Unable to load simulated markets.')
        }
      })
      .finally(() => {
        if (active) setLoading(false)
      })

    return () => {
      active = false
    }
  }, [])

  const selectedAsset = assets.find((asset) => asset.id === selectedId) ?? assets[0] ?? null

  return (
    <section className="space-y-6 pb-10 md:space-y-8">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="mb-2 text-[10px] font-semibold uppercase tracking-[0.2em] text-cyan-200/70">Market overview</p>
          <h1 className="font-display text-2xl text-white sm:text-[30px]">Supported markets</h1>
          <p className="mt-2 max-w-xl text-sm text-slate-400">
            Review the admin-configured prices and reported 24-hour movement for supported demo assets.
          </p>
        </div>
        <span className="demo-disclosure">
          <span className="demo-disclosure-mark" aria-hidden="true" />
          Simulated · Admin-controlled values
        </span>
      </div>

      {loading && <MarketsLoading />}

      {!loading && error && (
        <div role="alert" className="dashboard-message dashboard-error">
          <p className="text-sm font-medium text-rose-100">Market data unavailable</p>
          <p className="mt-1 text-sm text-rose-200/70">{error}</p>
        </div>
      )}

      {!loading && !error && assets.length === 0 && (
        <div className="markets-empty dashboard-surface">
          <span className="market-empty-mark" aria-hidden="true"><MarketIcon /></span>
          <p className="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">No active markets</p>
          <h2 className="mt-2 font-display text-xl text-white">There are no supported assets available right now.</h2>
          <p className="mt-2 max-w-lg text-xs leading-5 text-slate-400">Admin-configured market values will appear here when assets are active.</p>
        </div>
      )}

      {!loading && !error && selectedAsset && (
        <>
          <div className="markets-layout">
            <AssetMarketList assets={assets} selectedId={selectedAsset.id} onSelect={setSelectedId} />
            <SelectedMarket asset={selectedAsset} />
          </div>
          <MarketHistoryPanel assetId={selectedAsset.id} symbol={selectedAsset.symbol} name={selectedAsset.name} />
          <MarketDataCoverage />
        </>
      )}
    </section>
  )
}

function AssetMarketList({
  assets,
  selectedId,
  onSelect,
}: {
  assets: UserMarketAsset[]
  selectedId: number
  onSelect: (id: number) => void
}) {
  return (
    <article className="market-list dashboard-surface">
      <div className="market-list-heading">
        <div>
          <p className="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Supported assets</p>
          <h2 className="mt-2 text-base font-medium text-white">Market watch</h2>
        </div>
        <span className="history-label">{assets.length} active</span>
      </div>
      <ul className="market-asset-list">
        {assets.map((asset) => {
          const change = parseMarketNumber(asset.market_price?.change_24h_percentage)
          const price = formatMarketPrice(asset.market_price?.current_price)

          return (
            <li key={asset.id}>
              <button
                type="button"
                className={`market-asset-row${asset.id === selectedId ? ' is-selected' : ''}`}
                aria-pressed={asset.id === selectedId}
                onClick={() => onSelect(asset.id)}
              >
                <span className="market-asset-mark" aria-hidden="true">{asset.symbol.slice(0, 4)}</span>
                <span className="market-asset-identity">
                  <span className="market-asset-symbol">{asset.symbol}</span>
                  <span className="market-asset-name">{asset.name}</span>
                </span>
                <span className="market-asset-quote">
                  <span className="market-asset-price">{price}</span>
                  <span className={`market-asset-change ${movementClass(change)}`}>
                    {formatMarketChange(asset.market_price?.change_24h_percentage)}
                  </span>
                </span>
              </button>
            </li>
          )
        })}
      </ul>
    </article>
  )
}

function SelectedMarket({ asset }: { asset: UserMarketAsset }) {
  const price = formatMarketPrice(asset.market_price?.current_price)
  const change = parseMarketNumber(asset.market_price?.change_24h_percentage)

  return (
    <article className="selected-market dashboard-surface dashboard-hover" aria-live="polite">
      <div className="selected-market-orbit" aria-hidden="true" />
      <div className="selected-market-heading">
        <div className="market-asset-mark market-asset-mark-large" aria-hidden="true">{asset.symbol.slice(0, 4)}</div>
        <div className="min-w-0">
          <p className="text-[10px] font-semibold uppercase tracking-[0.16em] text-cyan-100/60">Selected asset</p>
          <h2 className="mt-1 truncate text-sm font-medium text-slate-200">{asset.name} <span className="text-slate-500">/ {asset.symbol}</span></h2>
        </div>
        <span className="market-active-indicator"><span />Active</span>
      </div>

      <div className="selected-market-price-block">
        <p className="text-xs text-slate-400">Current simulated price</p>
        <p key={`${asset.id}-${price}`} className="market-feature-price animated-value">{price}</p>
      </div>

      <div className="selected-market-change-block">
        <span className="text-xs text-slate-400">Reported 24h change</span>
        <span key={`${asset.id}-${asset.market_price?.change_24h_percentage}`} className={`market-feature-change animated-value ${movementClass(change)}`}>
          {formatMarketChange(asset.market_price?.change_24h_percentage)}
        </span>
      </div>

      <p className="selected-market-disclaimer">Admin-controlled demo quote. Not sourced from a live exchange.</p>
    </article>
  )
}

function MarketHistoryPanel({ assetId, symbol, name }: { assetId: number; symbol: string; name: string }) {
  const [range, setRange] = useState<RangeKey>('7d')
  const [history, setHistory] = useState<{
    requestKey: string
    points: MarketHistoryResponse['data']['points']
    error: string | null
  } | null>(null)
  const requestKey = `${assetId}:${range}`

  useEffect(() => {
    let active = true

    void apiRequest<MarketHistoryResponse>(`/api/markets/${assetId}/history?range=${range}`)
      .then((response) => {
        if (!active) return
        setHistory({
          requestKey,
          points: Array.isArray(response?.data?.points) ? response.data.points : [],
          error: null,
        })
      })
      .catch((requestError: unknown) => {
        if (!active) return
        let error: string
        if (requestError instanceof ApiError && requestError.status === 401) {
          error = 'Your session has expired. Please sign in again.'
        } else {
          error = requestError instanceof Error ? requestError.message : 'Unable to load market history.'
        }
        setHistory({ requestKey, points: [], error })
      })

    return () => {
      active = false
    }
  }, [assetId, range, requestKey])

  const currentHistory = history?.requestKey === requestKey ? history : null
  const loading = currentHistory === null
  const error = currentHistory?.error ?? null
  const points = currentHistory && !error ? currentHistory.points : []
  const chart = buildMarketChart(points)

  return (
    <article className="market-history-panel dashboard-surface">
      <div className="market-history-header">
        <div>
          <p className="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Historical view</p>
          <h2 className="mt-2 text-base font-medium text-white">{name} ({symbol})</h2>
        </div>
        <div className="market-range-toggle" role="group" aria-label="Market history range">
          {(['24h', '7d', '30d'] as const).map((option) => (
            <button
              key={option}
              type="button"
              className={option === range ? 'is-active' : ''}
              aria-pressed={option === range}
              onClick={() => setRange(option)}
            >
              {option}
            </button>
          ))}
        </div>
      </div>

      <div className="market-history-chart" aria-live="polite">
        {loading && <ChartMessageShell>Loading historical price data…</ChartMessageShell>}
        {!loading && error && <ChartMessageShell>{error}</ChartMessageShell>}
        {!loading && !error && points.length === 0 && <ChartMessageShell>No historical points are available for this view.</ChartMessageShell>}
        {!loading && !error && points.length === 1 && (
          <ChartMessageShell>
            {`Only one historical point is available (${formatMarketPrice(points[0]?.price)}). A trend will appear after additional points are recorded.`}
          </ChartMessageShell>
        )}
        {!loading && !error && points.length > 1 && !chart && (
          <ChartMessageShell>The available historical points cannot be plotted safely.</ChartMessageShell>
        )}
        {!loading && !error && points.length > 1 && chart && (
          <>
            <svg className="market-history-svg" viewBox="0 0 820 240" preserveAspectRatio="xMidYMid meet" role="img" aria-label={`Simulated ${symbol} price history for the last ${range}`}>
              <defs>
                <linearGradient id={`history-fill-${assetId}`} x1="0" x2="0" y1="0" y2="1">
                  <stop offset="0%" stopColor="#73d6d2" stopOpacity="0.25" />
                  <stop offset="100%" stopColor="#73d6d2" stopOpacity="0" />
                </linearGradient>
              </defs>
              <path d="M 0 38 H 820 M 0 92 H 820 M 0 146 H 820 M 0 200 H 820" className="chart-grid-line" />
              <path d={chart.area} fill={`url(#history-fill-${assetId})`} />
              <path d={chart.line} pathLength="1000" className="performance-line" />
              <circle cx={chart.last.x} cy={chart.last.y} r="4" className="performance-point" />
            </svg>
            <div className="chart-range-labels">
              <span>{formatChartDate(points[0]?.timestamp)}</span>
              <span>{formatChartDate(points[points.length - 1]?.timestamp)}</span>
            </div>
          </>
        )}
      </div>
    </article>
  )
}

function MarketDataCoverage() {
  return (
    <aside className="market-data-note">
      <span className="market-data-note-mark" aria-hidden="true">i</span>
      <div>
        <p className="text-xs font-medium text-slate-300">Simulated market history</p>
        <p className="mt-1 text-[11px] leading-5 text-slate-500">
          This chart is generated from demo market data and preserves the product’s simulated pricing disclaimer.
        </p>
      </div>
    </aside>
  )
}

function MarketsLoading() {
  return (
    <div className="grid gap-5 xl:grid-cols-[minmax(0,1.12fr)_minmax(0,0.88fr)]" role="status" aria-label="Loading markets">
      <div className="dashboard-skeleton h-[360px]" />
      <div className="dashboard-skeleton h-[360px]" />
      <span className="sr-only">Loading simulated markets</span>
    </div>
  )
}

function MarketIcon() {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <circle cx="12" cy="12" r="8" />
      <path d="M4 12h16M12 4a12 12 0 0 1 0 16M12 4a12 12 0 0 0 0 16" />
    </svg>
  )
}

function isMarketAsset(value: unknown): value is UserMarketAsset {
  if (!value || typeof value !== 'object') return false
  const asset = value as Partial<UserMarketAsset>
  return Number.isInteger(asset.id)
    && typeof asset.symbol === 'string'
    && typeof asset.name === 'string'
    && typeof asset.active === 'boolean'
}

function parseMarketNumber(value: unknown): number | null {
  if (typeof value !== 'string' && typeof value !== 'number') return null
  if (typeof value === 'string' && value.trim() === '') return null
  const parsed = Number(value)
  return Number.isFinite(parsed) ? parsed : null
}

function formatMarketPrice(value: unknown): string {
  const price = parseMarketNumber(value)
  if (price === null) return 'Price unavailable'
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(price)
}

function formatMarketChange(value: unknown): string {
  const change = parseMarketNumber(value)
  if (change === null) return 'Change unavailable'
  return `${change > 0 ? '+' : ''}${change.toFixed(2)}%`
}

function movementClass(value: number | null): string {
  if (value === null || value === 0) return 'is-neutral'
  return value > 0 ? 'is-positive' : 'is-negative'
}

function buildMarketChart(points: MarketHistoryResponse['data']['points']) {
  if (!Array.isArray(points) || points.length < 2) return null

  const values = points.map((point) => Number(point.price)).filter((value) => Number.isFinite(value))
  if (values.length < 2) return null

  const minimum = Math.min(...values)
  const maximum = Math.max(...values)
  const padding = maximum === minimum ? Math.abs(maximum) * 0.04 || 1 : (maximum - minimum) * 0.16
  const range = maximum - minimum + padding * 2 || 1

  const coordinates = points.map((point, index) => {
    const price = Number(point.price)
    if (!Number.isFinite(price)) return null
    return {
      x: 18 + (index / (points.length - 1)) * 784,
      y: 18 + ((maximum + padding - price) / range) * 182,
    }
  }).filter((point): point is { x: number; y: number } => point !== null)

  if (coordinates.length < 2) return null

  const line = coordinates.map((point, index) => `${index === 0 ? 'M' : 'L'} ${point.x.toFixed(2)} ${point.y.toFixed(2)}`).join(' ')
  const first = coordinates[0]
  const last = coordinates[coordinates.length - 1]
  if (!first || !last) return null

  return {
    line,
    area: `${line} L ${last.x.toFixed(2)} 212 L ${first.x.toFixed(2)} 212 Z`,
    last,
  }
}

function formatChartDate(value: string | undefined): string {
  if (!value) return 'N/A'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return 'N/A'
  return new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric' }).format(date)
}

function ChartMessageShell({ children }: { children: string }) {
  return <div className="chart-message">{children}</div>
}

export default UserMarketsPage