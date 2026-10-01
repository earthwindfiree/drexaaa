import { useEffect, useState } from 'react'
import { ApiError, apiRequest } from '../lib/api'
import { useDocumentTitle } from '../hooks/useDocumentTitle'
import type { UserTransaction, UserTransactionsResponse } from '../types/api'

const pageSize = 15

function UserTransactionsPage() {
  const [transactions, setTransactions] = useState<UserTransaction[]>([])
  const [meta, setMeta] = useState<UserTransactionsResponse['meta'] | null>(null)
  const [typeFilter, setTypeFilter] = useState('')
  const [statusFilter, setStatusFilter] = useState('')
  const [page, setPage] = useState(1)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useDocumentTitle('Transactions')

  useEffect(() => {
    let active = true
    const params = new URLSearchParams({ page: String(page), per_page: String(pageSize) })
    if (typeFilter) params.set('type', typeFilter)
    if (statusFilter) params.set('status', statusFilter)

    void apiRequest<UserTransactionsResponse>(`/api/transactions?${params.toString()}`)
      .then((response) => {
        if (!active) return
        if (!response || !Array.isArray(response.data)) {
          setTransactions([])
          setMeta(null)
          setError('The transaction history response is incomplete.')
          return
        }

        setTransactions(response.data.filter(isTransaction))
        setMeta(isPaginationMeta(response.meta) ? response.meta : null)
      })
      .catch((requestError: unknown) => {
        if (!active) return
        setTransactions([])
        setMeta(null)
        setError(errorMessage(requestError))
      })
      .finally(() => {
        if (active) setLoading(false)
      })

    return () => {
      active = false
    }
  }, [page, typeFilter, statusFilter])

  function changeType(value: string) {
    setLoading(true)
    setError(null)
    setPage(1)
    setTypeFilter(value)
  }

  function changeStatus(value: string) {
    setLoading(true)
    setError(null)
    setPage(1)
    setStatusFilter(value)
  }

  function changePage(value: number) {
    setLoading(true)
    setError(null)
    setPage(value)
  }

  const hasFilters = Boolean(typeFilter || statusFilter)

  return (
    <section className="space-y-6 pb-10 md:space-y-8">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="mb-2 text-[10px] font-semibold uppercase tracking-[0.2em] text-cyan-200/70">Account activity</p>
          <h1 className="font-display text-2xl text-white sm:text-[30px]">Transactions</h1>
          <p className="mt-2 max-w-xl text-sm text-slate-400">A unified record of completed, pending, and rejected account activity.</p>
        </div>
        <span className="demo-disclosure">
          <span className="demo-disclosure-mark" aria-hidden="true" />
          Demo account · Simulated activity
        </span>
      </div>

      <div className="transactions-context dashboard-surface">
        <div>
          <p className="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Transaction history</p>
          <p className="mt-2 text-sm text-slate-300">
            {meta ? `${meta.total.toLocaleString()} records in account history` : 'History supplied by your account records'}
          </p>
        </div>
        <div className="transactions-filters" aria-label="Filter transaction history">
          <label className="transaction-filter">
            <span>Type</span>
            <select value={typeFilter} onChange={(event) => changeType(event.target.value)}>
              <option value="">All types</option>
              <option value="deposit">Deposits</option>
              <option value="withdrawal">Withdrawals</option>
              <option value="adjustment">Account adjustments</option>
            </select>
          </label>
          <label className="transaction-filter">
            <span>Status</span>
            <select value={statusFilter} onChange={(event) => changeStatus(event.target.value)}>
              <option value="">All statuses</option>
              <option value="completed">Completed</option>
              <option value="pending">Pending</option>
              <option value="rejected">Rejected</option>
            </select>
          </label>
        </div>
      </div>

      {loading && <TransactionsLoading />}

      {!loading && error && (
        <div role="alert" className="dashboard-message dashboard-error">
          <p className="text-sm font-medium text-rose-100">Transaction history unavailable</p>
          <p className="mt-1 text-sm text-rose-200/70">{error}</p>
        </div>
      )}

      {!loading && !error && transactions.length === 0 && (
        <div className="transactions-empty dashboard-surface">
          <span className="transactions-empty-mark" aria-hidden="true"><TransactionIcon /></span>
          <p className="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">
            {hasFilters ? 'No matching records' : 'No activity yet'}
          </p>
          <h2 className="mt-2 font-display text-xl text-white">
            {hasFilters ? 'No transactions match these filters.' : 'No transactions have been recorded for this account.'}
          </h2>
          <p className="mt-2 max-w-lg text-xs leading-5 text-slate-400">
            {hasFilters ? 'Try a different type or status.' : 'Account deposits, withdrawals, and adjustments will appear here when recorded.'}
          </p>
        </div>
      )}

      {!loading && !error && transactions.length > 0 && (
        <>
          <div className="transactions-table-wrap dashboard-surface">
            <table className="transactions-table">
              <thead>
                <tr>
                  <th scope="col">Date &amp; time</th>
                  <th scope="col">Transaction</th>
                  <th scope="col">Asset</th>
                  <th scope="col" className="is-numeric">USD value</th>
                  <th scope="col">Crypto amount</th>
                  <th scope="col">Status</th>
                  <th scope="col">Reference</th>
                </tr>
              </thead>
              <tbody>
                {transactions.map((transaction) => <TransactionTableRow key={transaction.id} transaction={transaction} />)}
              </tbody>
            </table>
          </div>

          <div className="transactions-card-list">
            {transactions.map((transaction) => <TransactionCard key={transaction.id} transaction={transaction} />)}
          </div>

          {meta && meta.last_page > 1 && (
            <Pagination meta={meta} page={page} onPageChange={changePage} />
          )}
        </>
      )}
    </section>
  )
}

function TransactionTableRow({ transaction }: { transaction: UserTransaction }) {
  const type = transactionTypeLabel(transaction.type)
  const amount = formatTransactionUsd(transaction)
  const description = optionalText(transaction.description)

  return (
    <tr>
      <td className="transaction-date">{formatDate(transaction.occurred_at)}</td>
      <td>
        <span className={`transaction-type ${transactionTypeClass(transaction.type)}`}>{type}</span>
        {description && <span className="transaction-description">{description}</span>}
      </td>
      <td>{transaction.asset ? `${safeString(transaction.asset.symbol)} · ${safeString(transaction.asset.name)}` : 'Not applicable'}</td>
      <td className={`transaction-usd is-numeric ${amount.className}`}>{amount.label}</td>
      <td>{formatCrypto(transaction.crypto_amount, transaction.asset?.symbol)}</td>
      <td><TransactionStatus status={transaction.status} /></td>
      <td><span className="transaction-reference" title={safeString(transaction.reference)}>{safeString(transaction.reference)}</span></td>
    </tr>
  )
}

function TransactionCard({ transaction }: { transaction: UserTransaction }) {
  const amount = formatTransactionUsd(transaction)
  const description = optionalText(transaction.description)

  return (
    <article className="transaction-card dashboard-surface">
      <div className="transaction-card-heading">
        <div className="min-w-0">
          <span className={`transaction-type ${transactionTypeClass(transaction.type)}`}>{transactionTypeLabel(transaction.type)}</span>
          <p className="mt-1 truncate text-xs text-slate-400">
            {transaction.asset ? `${safeString(transaction.asset.symbol)} · ${safeString(transaction.asset.name)}` : 'Account-level transaction'}
          </p>
        </div>
        <TransactionStatus status={transaction.status} />
      </div>
      <div className="transaction-card-amount-row">
        <span className="text-[10px] uppercase tracking-[0.12em] text-slate-500">USD value</span>
        <strong className={`transaction-usd ${amount.className}`}>{amount.label}</strong>
      </div>
      <div className="transaction-card-details">
        <div><span>Date</span><strong>{formatDate(transaction.occurred_at)}</strong></div>
        <div><span>Crypto amount</span><strong>{formatCrypto(transaction.crypto_amount, transaction.asset?.symbol)}</strong></div>
        <div><span>Reference</span><strong className="transaction-card-reference">{safeString(transaction.reference)}</strong></div>
      </div>
      {description && <p className="transaction-card-description">{description}</p>}
    </article>
  )
}

function TransactionStatus({ status }: { status: unknown }) {
  const value = typeof status === 'string' ? status : ''
  const normalized = value.toLowerCase()
  const stateClass = normalized === 'completed'
    ? 'is-completed'
    : normalized === 'pending'
      ? 'is-pending'
      : normalized === 'rejected'
        ? 'is-rejected'
        : 'is-unknown'

  return <span className={`transaction-status ${stateClass}`}>{value ? humanize(value) : 'Status unavailable'}</span>
}

function Pagination({
  meta,
  page,
  onPageChange,
}: {
  meta: UserTransactionsResponse['meta']
  page: number
  onPageChange: (page: number) => void
}) {
  return (
    <nav className="transactions-pagination" aria-label="Transaction pages">
      <p>
        {meta.from !== null && meta.to !== null
          ? `Showing ${meta.from}–${meta.to} of ${meta.total}`
          : `Page ${meta.current_page} of ${meta.last_page}`}
      </p>
      <div>
        <button type="button" onClick={() => onPageChange(page - 1)} disabled={page <= 1}>Previous</button>
        <span aria-live="polite">Page {meta.current_page} of {meta.last_page}</span>
        <button type="button" onClick={() => onPageChange(page + 1)} disabled={page >= meta.last_page}>Next</button>
      </div>
    </nav>
  )
}

function TransactionsLoading() {
  return (
    <div className="transactions-loading dashboard-surface" role="status" aria-label="Loading transactions">
      <div className="transactions-loading-line" />
      <div className="transactions-loading-line" />
      <div className="transactions-loading-line" />
      <span className="sr-only">Loading transaction history</span>
    </div>
  )
}

function TransactionIcon() {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <path d="M5 7h14M5 17h14" />
      <path d="m15 4 4 3-4 3M9 14l-4 3 4 3" />
    </svg>
  )
}

function isTransaction(value: unknown): value is UserTransaction {
  return Boolean(value && typeof value === 'object' && Number.isInteger((value as UserTransaction).id))
}

function isPaginationMeta(value: unknown): value is UserTransactionsResponse['meta'] {
  if (!value || typeof value !== 'object') return false
  const meta = value as UserTransactionsResponse['meta']
  return Number.isInteger(meta.current_page) && meta.current_page > 0
    && Number.isInteger(meta.last_page) && meta.last_page > 0
    && Number.isInteger(meta.per_page) && meta.per_page > 0
    && Number.isInteger(meta.total) && meta.total >= 0
    && (meta.from === null || Number.isInteger(meta.from))
    && (meta.to === null || Number.isInteger(meta.to))
}

function safeString(value: unknown): string {
  return typeof value === 'string' && value.trim() ? value : 'Unavailable'
}

function optionalText(value: unknown): string | null {
  return typeof value === 'string' && value.trim() ? value : null
}

function transactionTypeLabel(type: unknown): string {
  if (type === 'deposit') return 'Deposit'
  if (type === 'withdrawal') return 'Withdrawal'
  if (type === 'adjustment') return 'Account adjustment'
  return 'Transaction type unavailable'
}

function transactionTypeClass(type: unknown): string {
  if (type === 'deposit') return 'is-deposit'
  if (type === 'withdrawal') return 'is-withdrawal'
  if (type === 'adjustment') return 'is-adjustment'
  return 'is-unknown'
}

function formatTransactionUsd(transaction: UserTransaction): { label: string; className: string } {
  const amount = parseNumber(transaction.usd_amount)
  if (amount === null) return { label: 'Unavailable', className: 'is-neutral' }
  const magnitude = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Math.abs(amount))

  if (transaction.type === 'deposit' && amount >= 0) return { label: `+${magnitude}`, className: 'is-positive' }
  if (transaction.type === 'withdrawal' && amount >= 0) return { label: `−${magnitude}`, className: 'is-negative' }
  if (amount > 0) return { label: `+${magnitude}`, className: 'is-positive' }
  if (amount < 0) return { label: `−${magnitude}`, className: 'is-negative' }
  return { label: magnitude, className: 'is-neutral' }
}

function formatCrypto(value: unknown, symbol?: unknown): string {
  const amount = parseNumber(value)
  if (amount === null) return 'Not applicable'
  const formatted = new Intl.NumberFormat('en-US', { maximumFractionDigits: 8 }).format(amount)
  return `${formatted} ${typeof symbol === 'string' && symbol.trim() ? symbol : ''}`.trim()
}

function parseNumber(value: unknown): number | null {
  if (typeof value !== 'string' && typeof value !== 'number') return null
  if (typeof value === 'string' && !value.trim()) return null
  const parsed = Number(value)
  return Number.isFinite(parsed) ? parsed : null
}

function formatDate(value: unknown): string {
  if (typeof value !== 'string' || !value) return 'Date unavailable'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return 'Date unavailable'
  return new Intl.DateTimeFormat('en-US', { dateStyle: 'medium', timeStyle: 'short' }).format(date)
}

function humanize(value: string): string {
  return value.replace(/[_-]+/g, ' ').replace(/\b\w/g, (character) => character.toUpperCase())
}

function errorMessage(error: unknown): string {
  if (error instanceof ApiError && error.status === 401) return 'Your session has expired. Please sign in again.'
  if (error instanceof ApiError && error.status === 403) return 'You are not authorized to view these transactions.'
  return error instanceof Error ? error.message : 'Unable to load transaction history.'
}

export default UserTransactionsPage