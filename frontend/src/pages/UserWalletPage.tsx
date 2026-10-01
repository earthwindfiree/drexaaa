import { useEffect, useState, type FormEvent } from 'react'
import { ApiError, apiRequest, initializeCsrfCookie } from '../lib/api'
import { useDocumentTitle } from '../hooks/useDocumentTitle'
import type {
  UserWalletAsset,
  UserWalletData,
  UserWalletDeposit,
  UserWalletResponse,
  UserWalletWithdrawal,
} from '../types/api'

type WalletNotice = {
  type: 'deposit' | 'withdrawal'
  id: number
  cryptoAmount?: string | null
  priceSnapshot?: string | null
}

function UserWalletPage() {
  const [wallet, setWallet] = useState<UserWalletData | null>(null)
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [selectedDepositAssetId, setSelectedDepositAssetId] = useState('')
  const [selectedWithdrawalAssetId, setSelectedWithdrawalAssetId] = useState('')
  const [cryptoAmount, setCryptoAmount] = useState('')
  const [transactionReference, setTransactionReference] = useState('')
  const [withdrawalAmount, setWithdrawalAmount] = useState('')
  const [destinationWallet, setDestinationWallet] = useState('')
  const [depositError, setDepositError] = useState<string | null>(null)
  const [withdrawalError, setWithdrawalError] = useState<string | null>(null)
  const [depositSubmitting, setDepositSubmitting] = useState(false)
  const [withdrawalSubmitting, setWithdrawalSubmitting] = useState(false)
  const [notice, setNotice] = useState<WalletNotice | null>(null)

  useDocumentTitle('Wallet')

  useEffect(() => {
    let active = true

    void fetchWallet()
      .then((data) => {
        if (!active) return
        setWallet(data)
        setSelectedDepositAssetId(String(data.assets[0]?.id ?? ''))
        setSelectedWithdrawalAssetId(String(data.assets[0]?.id ?? ''))
      })
      .catch((requestError: unknown) => {
        if (!active) return
        setLoadError(walletErrorMessage(requestError))
      })
      .finally(() => {
        if (active) setLoading(false)
      })

    return () => {
      active = false
    }
  }, [])

  const depositAsset = wallet?.assets.find((asset) => String(asset.id) === selectedDepositAssetId) ?? null
  const withdrawalAsset = wallet?.assets.find((asset) => String(asset.id) === selectedWithdrawalAssetId) ?? null
  const depositWallet = activeDepositWallet(depositAsset)
  const requestedAmount = parseAmount(withdrawalAmount)
  const withdrawableAmount = parseAmount(wallet?.account.withdrawable_amount)
  const exceedsWithdrawable = requestedAmount !== null && withdrawableAmount !== null && requestedAmount > withdrawableAmount

  async function submitDeposit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setDepositError(null)
    setNotice(null)

    if (!depositAsset) {
      setDepositError('Select an available asset before submitting a deposit.')
      return
    }

    if (!depositWallet) {
      setDepositError('This asset has no usable admin-configured deposit wallet right now.')
      return
    }

    setDepositSubmitting(true)
    try {
      await initializeCsrfCookie()
      const response = await apiRequest<{ data: UserWalletDeposit }>('/api/deposits', {
        method: 'POST',
        body: JSON.stringify({
          asset_id: depositAsset.id,
          crypto_amount: cryptoAmount,
          transaction_reference: transactionReference,
        }),
      })

      setNotice({ type: 'deposit', id: response.data.id })
      setCryptoAmount('')
      setTransactionReference('')
      await refreshWallet()
    } catch (requestError: unknown) {
      setDepositError(submissionErrorMessage(requestError, 'Unable to submit this deposit.'))
    } finally {
      setDepositSubmitting(false)
    }
  }

  async function submitWithdrawal(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setWithdrawalError(null)
    setNotice(null)

    if (!withdrawalAsset) {
      setWithdrawalError('Select an available asset before submitting a withdrawal.')
      return
    }

    if (exceedsWithdrawable) {
      setWithdrawalError('The requested amount exceeds your current withdrawable amount.')
      return
    }

    setWithdrawalSubmitting(true)
    try {
      await initializeCsrfCookie()
      const response = await apiRequest<{ data: UserWalletWithdrawal }>('/api/withdrawals', {
        method: 'POST',
        body: JSON.stringify({
          asset_id: withdrawalAsset.id,
          amount: withdrawalAmount,
          destination_wallet: destinationWallet,
        }),
      })

      setNotice({
        type: 'withdrawal',
        id: response.data.id,
        cryptoAmount: response.data.crypto_amount,
        priceSnapshot: response.data.price_snapshot,
      })
      setWithdrawalAmount('')
      setDestinationWallet('')
      await refreshWallet()
    } catch (requestError: unknown) {
      setWithdrawalError(submissionErrorMessage(requestError, 'Unable to submit this withdrawal.'))
    } finally {
      setWithdrawalSubmitting(false)
    }
  }

  async function refreshWallet() {
    try {
      const data = await fetchWallet()
      setWallet(data)
      if (!data.assets.some((asset) => String(asset.id) === selectedDepositAssetId)) {
        setSelectedDepositAssetId(String(data.assets[0]?.id ?? ''))
      }
      if (!data.assets.some((asset) => String(asset.id) === selectedWithdrawalAssetId)) {
        setSelectedWithdrawalAssetId(String(data.assets[0]?.id ?? ''))
      }
    } catch {
      setLoadError('Your request was submitted, but wallet information could not be refreshed. Reload to see updated balances and history.')
    }
  }

  return (
    <section className="space-y-6 pb-10 md:space-y-8">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="mb-2 text-[10px] font-semibold uppercase tracking-[0.2em] text-cyan-200/70">Account funding</p>
          <h1 className="font-display text-2xl text-white sm:text-[30px]">Wallet</h1>
          <p className="mt-2 max-w-xl text-sm text-slate-400">Manage manual deposits and withdrawal requests for your simulated account.</p>
        </div>
        <span className="demo-disclosure">
          <span className="demo-disclosure-mark" aria-hidden="true" />
          Manual funding · Demo account
        </span>
      </div>

      {loading && <WalletLoading />}

      {!loading && loadError && !wallet && (
        <div role="alert" className="dashboard-message dashboard-error">
          <p className="text-sm font-medium text-rose-100">Wallet information unavailable</p>
          <p className="mt-1 text-sm text-rose-200/70">{loadError}</p>
        </div>
      )}

      {!loading && wallet && (
        <>
          {loadError && (
            <div role="status" className="wallet-refresh-warning">{loadError}</div>
          )}
          <WalletOverview account={wallet.account} />
          {notice && <SubmissionNotice notice={notice} />}

          <div className="wallet-workflows">
            <DepositForm
              assets={wallet.assets}
              selectedAssetId={selectedDepositAssetId}
              selectedAsset={depositAsset}
              selectedWallet={depositWallet}
              amount={cryptoAmount}
              reference={transactionReference}
              submitting={depositSubmitting}
              error={depositError}
              onAssetChange={setSelectedDepositAssetId}
              onAmountChange={setCryptoAmount}
              onReferenceChange={setTransactionReference}
              onSubmit={submitDeposit}
            />
            <WithdrawalForm
              assets={wallet.assets}
              account={wallet.account}
              selectedAssetId={selectedWithdrawalAssetId}
              selectedAsset={withdrawalAsset}
              amount={withdrawalAmount}
              destination={destinationWallet}
              submitting={withdrawalSubmitting}
              exceedsWithdrawable={exceedsWithdrawable}
              error={withdrawalError}
              onAssetChange={setSelectedWithdrawalAssetId}
              onAmountChange={setWithdrawalAmount}
              onDestinationChange={setDestinationWallet}
              onSubmit={submitWithdrawal}
            />
          </div>

          <div className="wallet-history-grid">
            <DepositHistory deposits={wallet.deposits} />
            <WithdrawalHistory withdrawals={wallet.withdrawals} />
          </div>
        </>
      )}
    </section>
  )
}

function WalletOverview({ account }: { account: UserWalletData['account'] }) {
  return (
    <article className="wallet-overview dashboard-surface">
      <div className="wallet-overview-primary">
        <p className="text-xs font-medium text-slate-400">Managed balance</p>
        <p key={account.managed_balance} className="animated-value mt-3 break-all font-display text-4xl leading-none text-white sm:text-6xl">
          {formatUsd(account.managed_balance)}
        </p>
        <p className="mt-3 text-[10px] text-slate-500">Confirmed deposits less approved withdrawals</p>
      </div>
      <div className="wallet-overview-secondary">
        <BalanceMetric label="Pending balance" value={account.pending_balance} detail="Pending deposit value; not yet managed" />
        <BalanceMetric label="Withdrawable now" value={account.withdrawable_amount} detail="After pending withdrawal reservations" />
      </div>
    </article>
  )
}

function BalanceMetric({ label, value, detail }: { label: string; value: string; detail: string }) {
  return (
    <div className="wallet-balance-metric">
      <p className="text-[11px] font-medium text-slate-400">{label}</p>
      <p key={value} className="animated-value mt-2 text-xl font-semibold tabular-nums text-slate-100">{formatUsd(value)}</p>
      <p className="mt-1 text-[10px] leading-4 text-slate-500">{detail}</p>
    </div>
  )
}

function DepositForm({
  assets,
  selectedAssetId,
  selectedAsset,
  selectedWallet,
  amount,
  reference,
  submitting,
  error,
  onAssetChange,
  onAmountChange,
  onReferenceChange,
  onSubmit,
}: {
  assets: UserWalletAsset[]
  selectedAssetId: string
  selectedAsset: UserWalletAsset | null
  selectedWallet: UserWalletAsset['wallets'][number] | null
  amount: string
  reference: string
  submitting: boolean
  error: string | null
  onAssetChange: (value: string) => void
  onAmountChange: (value: string) => void
  onReferenceChange: (value: string) => void
  onSubmit: (event: FormEvent<HTMLFormElement>) => void
}) {
  return (
    <article className="wallet-workflow dashboard-surface">
      <div className="wallet-workflow-heading">
        <span className="wallet-step-mark">01</span>
        <div>
          <p className="text-[10px] font-semibold uppercase tracking-[0.16em] text-cyan-100/60">Add funds</p>
          <h2 className="mt-1 text-lg font-medium text-white">Submit a deposit</h2>
        </div>
      </div>

      <form className="wallet-form" onSubmit={(event) => void onSubmit(event)}>
        <label className="wallet-field">
          <span>Asset</span>
          <select required value={selectedAssetId} onChange={(event) => onAssetChange(event.target.value)} disabled={assets.length === 0}>
            {assets.length === 0 && <option value="">No active assets available</option>}
            {assets.map((asset) => <option key={asset.id} value={asset.id}>{asset.symbol} · {asset.name}</option>)}
          </select>
        </label>

        {selectedAsset && (
          <div className={`wallet-address-box${selectedWallet ? '' : ' is-unavailable'}`}>
            <div className="wallet-address-heading">
              <span>Admin-configured deposit address</span>
              <span className="wallet-config-status">{selectedWallet ? 'Configured' : 'Unavailable'}</span>
            </div>
            {selectedWallet ? (
              <>
                <code>{selectedWallet.wallet_address}</code>
                {selectedAsset.symbol === 'USDT' && (
                  <p className="wallet-network-line">Network: {selectedWallet.network || 'Not specified'}</p>
                )}
                {selectedAsset.symbol !== 'USDT' && selectedWallet.network && (
                  <p className="wallet-network-line">Network: {selectedWallet.network}</p>
                )}
              </>
            ) : (
              <p className="mt-2 text-xs leading-5 text-slate-400">Deposit submissions are unavailable until an active wallet configuration is available.</p>
            )}
          </div>
        )}

        <p className="wallet-instruction">Send crypto to this address externally, then submit the crypto amount and transaction reference below. Transfers are reviewed manually; this demo does not verify blockchain activity.</p>

        <label className="wallet-field">
          <span>Crypto amount</span>
          <input required value={amount} onChange={(event) => onAmountChange(event.target.value)} inputMode="decimal" maxLength={40} placeholder="0.00" />
        </label>
        <label className="wallet-field">
          <span>Transaction reference</span>
          <input required value={reference} onChange={(event) => onReferenceChange(event.target.value)} maxLength={255} placeholder="Enter your transaction reference" />
        </label>

        {error && <p className="wallet-form-error" role="alert">{error}</p>}
        <button type="submit" className="wallet-submit-button" disabled={submitting || assets.length === 0 || !selectedWallet}>
          {submitting ? 'Submitting deposit…' : 'Submit deposit for review'}
        </button>
        <p className="wallet-form-footnote">Submitted deposits are added to Pending Balance until reviewed and confirmed.</p>
      </form>
    </article>
  )
}

function WithdrawalForm({
  assets,
  account,
  selectedAssetId,
  selectedAsset,
  amount,
  destination,
  submitting,
  exceedsWithdrawable,
  error,
  onAssetChange,
  onAmountChange,
  onDestinationChange,
  onSubmit,
}: {
  assets: UserWalletAsset[]
  account: UserWalletData['account']
  selectedAssetId: string
  selectedAsset: UserWalletAsset | null
  amount: string
  destination: string
  submitting: boolean
  exceedsWithdrawable: boolean
  error: string | null
  onAssetChange: (value: string) => void
  onAmountChange: (value: string) => void
  onDestinationChange: (value: string) => void
  onSubmit: (event: FormEvent<HTMLFormElement>) => void
}) {
  return (
    <article className="wallet-workflow dashboard-surface">
      <div className="wallet-workflow-heading">
        <span className="wallet-step-mark">02</span>
        <div>
          <p className="text-[10px] font-semibold uppercase tracking-[0.16em] text-cyan-100/60">Request funds</p>
          <h2 className="mt-1 text-lg font-medium text-white">Submit a withdrawal</h2>
        </div>
      </div>

      <div className="wallet-available-row">
        <span>Authoritative withdrawable amount</span>
        <strong>{formatUsd(account.withdrawable_amount)}</strong>
      </div>

      <form className="wallet-form" onSubmit={(event) => void onSubmit(event)}>
        <label className="wallet-field">
          <span>Asset</span>
          <select required value={selectedAssetId} onChange={(event) => onAssetChange(event.target.value)} disabled={assets.length === 0}>
            {assets.length === 0 && <option value="">No active assets available</option>}
            {assets.map((asset) => <option key={asset.id} value={asset.id}>{asset.symbol} · {asset.name}</option>)}
          </select>
        </label>
        {selectedAsset?.market_price?.current_price && (
          <p className="wallet-price-context">Current admin-configured quote: {formatUsd(selectedAsset.market_price.current_price)} per {selectedAsset.symbol}. Final conversion is recorded by the server when submitted.</p>
        )}
        <label className="wallet-field">
          <span>Withdrawal amount (USD)</span>
          <input
            required
            value={amount}
            onChange={(event) => onAmountChange(event.target.value)}
            inputMode="decimal"
            placeholder="0.00"
            aria-describedby="withdrawal-reservation-note"
          />
        </label>
        {exceedsWithdrawable && (
          <p className="wallet-form-error" role="alert">This amount is above the current withdrawable amount. Reduce it or check for pending withdrawal reservations.</p>
        )}
        <label className="wallet-field">
          <span>Destination wallet address</span>
          <input required value={destination} onChange={(event) => onDestinationChange(event.target.value)} maxLength={255} placeholder="Enter your destination wallet" />
        </label>
        <p id="withdrawal-reservation-note" className="wallet-instruction">A pending request reserves part of your withdrawable amount but does not reduce Managed Balance. Approval or rejection is handled manually.</p>
        {error && <p className="wallet-form-error" role="alert">{error}</p>}
        <button type="submit" className="wallet-submit-button is-secondary" disabled={submitting || assets.length === 0 || exceedsWithdrawable}>
          {submitting ? 'Submitting request…' : 'Submit withdrawal for review'}
        </button>
        <p className="wallet-form-footnote">Crypto amount and price snapshot appear after the server records the request.</p>
      </form>
    </article>
  )
}

function DepositHistory({ deposits }: { deposits: UserWalletDeposit[] }) {
  return (
    <HistoryPanel title="Deposit history" subtitle="Manual deposit submissions and review status">
      {deposits.length === 0 ? (
        <HistoryEmpty>No deposit submissions yet.</HistoryEmpty>
      ) : deposits.map((deposit) => (
        <article key={deposit.id} className="wallet-history-entry">
          <div className="wallet-history-entry-main">
            <span className="wallet-history-asset">{deposit.asset ? `${deposit.asset.symbol} · ${deposit.asset.name}` : 'Asset unavailable'}</span>
            <span className="wallet-history-detail">{formatCrypto(deposit.crypto_amount, deposit.asset?.symbol)} · {formatUsd(deposit.usd_value)}</span>
            <span className="wallet-history-reference">Reference: {deposit.transaction_reference || 'Unavailable'}</span>
          </div>
          <div className="wallet-history-entry-meta">
            <StatusBadge status={deposit.status} />
            <time>{formatDate(deposit.submitted_at)}</time>
          </div>
          {deposit.rejection_reason && <p className="wallet-rejection-note">{deposit.rejection_reason}</p>}
        </article>
      ))}
    </HistoryPanel>
  )
}

function WithdrawalHistory({ withdrawals }: { withdrawals: UserWalletWithdrawal[] }) {
  return (
    <HistoryPanel title="Withdrawal history" subtitle="Requests remain pending until manually reviewed">
      {withdrawals.length === 0 ? (
        <HistoryEmpty>No withdrawal requests yet.</HistoryEmpty>
      ) : withdrawals.map((withdrawal) => (
        <article key={withdrawal.id} className="wallet-history-entry">
          <div className="wallet-history-entry-main">
            <span className="wallet-history-asset">{withdrawal.asset ? `${withdrawal.asset.symbol} · ${withdrawal.asset.name}` : 'Asset unavailable'}</span>
            <span className="wallet-history-detail">{formatUsd(withdrawal.amount)} requested</span>
            <span className="wallet-history-reference">Destination: {withdrawal.destination_wallet || 'Unavailable'}</span>
            {withdrawal.crypto_amount && (
              <span className="wallet-history-reference">
                Server-recorded amount: {formatCrypto(withdrawal.crypto_amount, withdrawal.asset?.symbol)}
                {withdrawal.price_snapshot ? ` · Price snapshot ${formatUsd(withdrawal.price_snapshot)}` : ''}
              </span>
            )}
          </div>
          <div className="wallet-history-entry-meta">
            <StatusBadge status={withdrawal.status} />
            <time>{formatDate(withdrawal.submitted_at)}</time>
          </div>
          {withdrawal.rejection_reason && <p className="wallet-rejection-note">{withdrawal.rejection_reason}</p>}
        </article>
      ))}
    </HistoryPanel>
  )
}

function HistoryPanel({ title, subtitle, children }: { title: string; subtitle: string; children: React.ReactNode }) {
  return (
    <article className="wallet-history-panel dashboard-surface">
      <div className="wallet-history-heading">
        <div>
          <h2 className="text-base font-medium text-white">{title}</h2>
          <p className="mt-1 text-[10px] text-slate-500">{subtitle}</p>
        </div>
      </div>
      <div className="wallet-history-list">{children}</div>
    </article>
  )
}

function HistoryEmpty({ children }: { children: string }) {
  return <p className="wallet-history-empty">{children}</p>
}

function SubmissionNotice({ notice }: { notice: WalletNotice }) {
  return (
    <div className="wallet-submission-notice" role="status">
      <span className="wallet-notice-mark" aria-hidden="true">✓</span>
      <div>
        <p className="text-xs font-medium text-cyan-50">
          {notice.type === 'deposit' ? 'Deposit' : 'Withdrawal'} #{notice.id} submitted · Pending review
        </p>
        {notice.type === 'withdrawal' && notice.cryptoAmount && (
          <p className="mt-1 text-[10px] text-slate-400">
            Server-recorded amount: {notice.cryptoAmount}
            {notice.priceSnapshot ? ` · Price snapshot ${formatUsd(notice.priceSnapshot)}` : ''}
          </p>
        )}
      </div>
    </div>
  )
}

function StatusBadge({ status }: { status: unknown }) {
  const safeStatus = typeof status === 'string' ? status : ''
  const normalized = safeStatus.toLowerCase()
  const className = normalized === 'pending'
    ? 'is-pending'
    : ['confirmed', 'approved'].includes(normalized)
      ? 'is-complete'
      : normalized === 'rejected'
        ? 'is-rejected'
        : 'is-unknown'

  return <span className={`wallet-status ${className}`}>{safeStatus ? humanize(safeStatus) : 'Status unavailable'}</span>
}

function WalletLoading() {
  return (
    <div className="space-y-5" role="status" aria-label="Loading wallet">
      <div className="dashboard-skeleton h-[190px]" />
      <div className="grid gap-5 xl:grid-cols-2">
        <div className="dashboard-skeleton h-[520px]" />
        <div className="dashboard-skeleton h-[520px]" />
      </div>
      <span className="sr-only">Loading wallet information</span>
    </div>
  )
}

async function fetchWallet(): Promise<UserWalletData> {
  const response = await apiRequest<UserWalletResponse>('/api/wallet')
  const data = response?.data
  if (!data || typeof data !== 'object' || !data.account || typeof data.account !== 'object') {
    throw new Error('The wallet response is incomplete.')
  }

  return {
    account: data.account,
    assets: Array.isArray(data.assets) ? data.assets.filter(isWalletAsset) : [],
    deposits: Array.isArray(data.deposits) ? data.deposits.filter(isWalletDeposit) : [],
    withdrawals: Array.isArray(data.withdrawals) ? data.withdrawals.filter(isWalletWithdrawal) : [],
  }
}

function activeDepositWallet(asset: UserWalletAsset | null): UserWalletAsset['wallets'][number] | null {
  if (!asset || !Array.isArray(asset.wallets) || asset.wallets.length !== 1) return null
  const wallet = asset.wallets[0]
  return typeof wallet?.wallet_address === 'string' && wallet.wallet_address.trim() ? wallet : null
}

function isWalletAsset(value: unknown): value is UserWalletAsset {
  if (!value || typeof value !== 'object') return false
  const asset = value as Partial<UserWalletAsset>
  return Number.isInteger(asset.id)
    && typeof asset.symbol === 'string'
    && Boolean(asset.symbol.trim())
    && typeof asset.name === 'string'
    && Boolean(asset.name.trim())
}

function isWalletDeposit(value: unknown): value is UserWalletDeposit {
  return Boolean(value && typeof value === 'object' && Number.isInteger((value as UserWalletDeposit).id))
}

function isWalletWithdrawal(value: unknown): value is UserWalletWithdrawal {
  return Boolean(value && typeof value === 'object' && Number.isInteger((value as UserWalletWithdrawal).id))
}

function parseAmount(value: unknown): number | null {
  if (typeof value !== 'string' && typeof value !== 'number') return null
  if (typeof value === 'string' && !value.trim()) return null
  const amount = Number(value)
  return Number.isFinite(amount) ? amount : null
}

function formatUsd(value: unknown): string {
  const amount = parseAmount(value)
  if (amount === null) return 'Unavailable'
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(amount)
}

function formatCrypto(value: unknown, symbol?: string): string {
  const amount = parseAmount(value)
  if (amount === null) return `Amount unavailable${symbol ? ` · ${symbol}` : ''}`
  return `${new Intl.NumberFormat('en-US', { maximumFractionDigits: 8 }).format(amount)} ${symbol || ''}`.trim()
}

function formatDate(value: string | null): string {
  if (!value) return 'Date unavailable'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return 'Date unavailable'
  return new Intl.DateTimeFormat('en-US', { dateStyle: 'medium', timeStyle: 'short' }).format(date)
}

function humanize(value: string): string {
  return value.replace(/[_-]+/g, ' ').replace(/\b\w/g, (character) => character.toUpperCase())
}

function walletErrorMessage(error: unknown): string {
  if (error instanceof ApiError && error.status === 401) return 'Your session has expired. Please sign in again.'
  if (error instanceof ApiError && error.status === 403) return 'You are not authorized to view this wallet.'
  return error instanceof Error ? error.message : 'Unable to load wallet information.'
}

function submissionErrorMessage(error: unknown, fallback: string): string {
  if (error instanceof ApiError && Object.values(error.errors).length > 0) {
    return Object.values(error.errors).flat().join(' ')
  }
  if (error instanceof ApiError && error.status === 401) return 'Your session has expired. Please sign in again.'
  if (error instanceof ApiError && error.status === 403) return 'You are not authorized to submit this request.'
  return error instanceof Error ? error.message : fallback
}

export default UserWalletPage