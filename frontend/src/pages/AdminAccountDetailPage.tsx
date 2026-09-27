import { useEffect, useState, type FormEvent } from 'react'
import { Link, useParams } from 'react-router-dom'
import { ApiError, apiRequest, initializeCsrfCookie } from '../lib/api'
import type { AdminAccountDetail, AdminAccountResponse, AdminAccountSimulationResponse } from '../types/api'

function AdminAccountDetailPage() {
  const { accountId } = useParams<{ accountId: string }>()
  const [account, setAccount] = useState<AdminAccountDetail | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [reloadToken, setReloadToken] = useState(0)
  const displayError = accountId ? error : 'This account could not be identified.'
  const isLoading = Boolean(accountId) && loading

  useEffect(() => {
    let active = true

    if (!accountId) {
      return () => { active = false }
    }

    void apiRequest<AdminAccountResponse>(`/api/admin/accounts/${accountId}`)
      .then((response) => {
        if (active) setAccount(response.data)
      })
      .catch((requestError: unknown) => {
        if (!active) return

        if (requestError instanceof ApiError && requestError.status === 401) setError('Your session has expired. Please sign in again.')
        else if (requestError instanceof ApiError && requestError.status === 403) setError('You are not authorized to view this account.')
        else if (requestError instanceof ApiError && requestError.status === 404) setError('This account could not be found.')
        else setError(requestError instanceof Error ? requestError.message : 'Unable to load this account.')
      })
      .finally(() => {
        if (active) setLoading(false)
      })

    return () => { active = false }
  }, [accountId, reloadToken])

  return (
    <section className="mx-auto max-w-7xl px-6 py-10 lg:px-8">
      <Link to="/admin/accounts" className="text-sm font-semibold text-cyan-700 hover:text-cyan-900">Back to accounts</Link>
      {isLoading && <p className="mt-6 border border-slate-200 bg-white p-6 text-sm text-slate-600">Loading account...</p>}
      {!isLoading && displayError && <div role="alert" className="mt-6 border border-rose-200 bg-rose-50 p-6 text-sm text-rose-800">{displayError}</div>}
      {!isLoading && !displayError && account && <AccountDetail key={`${account.id}-${account.updated_at}`} account={account} onUpdated={() => setReloadToken((value) => value + 1)} />}
    </section>
  )
}

function AccountDetail({ account, onUpdated }: { account: AdminAccountDetail; onUpdated: () => void }) {
  return (
    <>
      <div className="mb-8 mt-5 flex flex-wrap items-end justify-between gap-4"><div><p className="text-sm font-semibold uppercase tracking-[0.18em] text-cyan-700">Account #{account.id}</p><h1 className="mt-2 text-3xl font-semibold tracking-tight text-slate-950">{account.user?.name ?? 'Unknown user'}</h1><p className="mt-2 text-sm text-slate-600">Read-only simulated account view.</p></div><span className="border border-slate-300 bg-white px-3 py-2 text-xs font-semibold uppercase tracking-[0.14em] text-slate-600">{account.trading_status}</span></div>
      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"><Metric label="Managed balance" value={`$${account.managed_balance}`} /><Metric label="Pending balance" value={`$${account.pending_balance}`} /><Metric label="Total P/L" value={`$${account.total_profit_loss}`} /><Metric label="Performance" value={`${account.performance_percentage}%`} /></div>
      <SimulationControls account={account} onUpdated={onUpdated} />
      <div className="mt-8 grid gap-6 lg:grid-cols-2"><InfoPanel title="User information"><InfoLine label="Name" value={account.user?.name ?? 'Not available'} /><InfoLine label="Email" value={account.user?.email ?? 'Not available'} /><InfoLine label="Country" value={account.user?.country ?? 'Not provided'} /><InfoLine label="Role / status" value={account.user ? `${account.user.role} · ${account.user.status}` : 'Not available'} /></InfoPanel><InfoPanel title="Tier and strategy"><InfoLine label="Tier" value={account.tier?.name ?? 'Unassigned'} /><InfoLine label="Minimum balance" value={account.tier ? `$${account.tier.minimum_balance}` : 'Not available'} /><InfoLine label="Strategy" value={account.tier?.strategy?.name ?? 'Not assigned'} /><InfoLine label="Risk profile" value={account.tier?.strategy?.risk_profile ?? 'Not available'} /></InfoPanel></div>
      <HistoryTable title="Performance history" emptyLabel="No performance snapshots are recorded." headers={['Snapshot', 'Managed balance', 'P/L', 'Performance']} rows={account.performance_snapshots.map((snapshot) => [formatDate(snapshot.snapshot_at), `$${snapshot.managed_balance}`, `$${snapshot.total_profit_loss}`, `${snapshot.performance_percentage}%`])} />
      <HistoryTable title="Transactions" emptyLabel="No transactions are recorded." headers={['Date', 'Type', 'Asset', 'USD value', 'Status', 'Reference']} rows={account.transactions.map((transaction) => [formatDate(transaction.occurred_at), transaction.type, transaction.asset?.symbol ?? 'N/A', `$${transaction.usd_amount}`, transaction.status, transaction.reference])} />
      <HistoryTable title="Deposits" emptyLabel="No deposits are recorded." headers={['Date', 'Asset', 'Crypto amount', 'USD value', 'Status', 'Reference']} rows={account.deposits.map((deposit) => [formatDate(deposit.created_at), deposit.asset?.symbol ?? 'N/A', deposit.crypto_amount, `$${deposit.usd_value}`, deposit.status, deposit.transaction_reference])} />
      <HistoryTable title="Withdrawals" emptyLabel="No withdrawals are recorded." headers={['Date', 'Asset', 'Amount', 'Crypto amount', 'Status', 'Destination']} rows={account.withdrawals.map((withdrawal) => [formatDate(withdrawal.created_at), withdrawal.asset?.symbol ?? 'N/A', `$${withdrawal.amount}`, withdrawal.crypto_amount ?? 'N/A', withdrawal.status, withdrawal.destination_wallet])} />
    </>
  )
}

function SimulationControls({ account, onUpdated }: { account: AdminAccountDetail; onUpdated: () => void }) {
  const [managedBalance, setManagedBalance] = useState(account.managed_balance)
  const [totalProfitLoss, setTotalProfitLoss] = useState(account.total_profit_loss)
  const [performancePercentage, setPerformancePercentage] = useState(account.performance_percentage)
  const [tradingStatus, setTradingStatus] = useState(account.trading_status)
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState(false)

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setError(null)
    setSuccess(false)

    if (!window.confirm(`Apply simulated account changes?\n\nManaged balance: $${managedBalance}\nTotal P/L: $${totalProfitLoss}\nPerformance: ${performancePercentage}%\nTrading status: ${tradingStatus}`)) return

    setSubmitting(true)

    try {
      await initializeCsrfCookie()
      const response = await apiRequest<AdminAccountSimulationResponse>(`/api/admin/accounts/${account.id}/simulation`, {
        method: 'PATCH',
        body: JSON.stringify({ managed_balance: managedBalance, total_profit_loss: totalProfitLoss, performance_percentage: performancePercentage, trading_status: tradingStatus }),
      })
      setManagedBalance(response.data.managed_balance)
      setTotalProfitLoss(response.data.total_profit_loss)
      setPerformancePercentage(response.data.performance_percentage)
      setTradingStatus(response.data.trading_status)
      setSuccess(true)
      onUpdated()
    } catch (requestError: unknown) {
      if (requestError instanceof ApiError && requestError.status === 401) setError('Your session has expired. Please sign in again.')
      else if (requestError instanceof ApiError && requestError.status === 403) setError('You are not authorized to change this account.')
      else if (requestError instanceof ApiError && Object.values(requestError.errors).length > 0) setError(Object.values(requestError.errors).flat().join(' '))
      else setError(requestError instanceof Error ? requestError.message : 'Unable to update simulated account state.')
    } finally {
      setSubmitting(false)
    }
  }

  return <section className="mt-8 border border-cyan-200 bg-cyan-50 p-5"><div className="mb-5"><p className="text-sm font-semibold uppercase tracking-[0.18em] text-cyan-800">Simulation controls</p><h2 className="mt-2 text-xl font-semibold text-slate-950">Update simulated account state</h2><p className="mt-2 text-sm text-slate-700">Values are absolute targets. Managed balance changes recalculate the account tier automatically. Pending balance and tier cannot be edited here.</p></div><form onSubmit={submit} className="grid gap-4 md:grid-cols-2 xl:grid-cols-4"><label className="text-sm font-medium text-slate-700">Managed balance<input value={managedBalance} onChange={(event) => setManagedBalance(event.target.value)} inputMode="decimal" className="mt-1 block w-full border border-slate-300 bg-white px-3 py-2 text-sm text-slate-950 outline-none focus:border-cyan-700" /></label><label className="text-sm font-medium text-slate-700">Total P/L<input value={totalProfitLoss} onChange={(event) => setTotalProfitLoss(event.target.value)} inputMode="decimal" className="mt-1 block w-full border border-slate-300 bg-white px-3 py-2 text-sm text-slate-950 outline-none focus:border-cyan-700" /></label><label className="text-sm font-medium text-slate-700">Performance %<input value={performancePercentage} onChange={(event) => setPerformancePercentage(event.target.value)} inputMode="decimal" className="mt-1 block w-full border border-slate-300 bg-white px-3 py-2 text-sm text-slate-950 outline-none focus:border-cyan-700" /></label><label className="text-sm font-medium text-slate-700">Trading status<select value={tradingStatus} onChange={(event) => setTradingStatus(event.target.value)} className="mt-1 block w-full border border-slate-300 bg-white px-3 py-2 text-sm text-slate-950 outline-none focus:border-cyan-700"><option value="inactive">Inactive</option><option value="active">Active</option></select></label><div className="md:col-span-2 xl:col-span-4 flex flex-wrap items-center gap-3"><button type="submit" disabled={submitting} className="bg-slate-950 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:opacity-60">{submitting ? 'Applying...' : 'Apply simulation changes'}</button>{success && <p role="status" className="text-sm font-medium text-emerald-700">Account state updated.</p>}{error && <p role="alert" className="text-sm font-medium text-rose-700">{error}</p>}</div></form></section>
}

function Metric({ label, value }: { label: string; value: string }) { return <article className="border border-slate-200 bg-white p-5 shadow-sm"><p className="text-sm font-medium text-slate-500">{label}</p><p className="mt-3 text-2xl font-semibold tracking-tight text-slate-950">{value}</p></article> }
function InfoPanel({ title, children }: { title: string; children: React.ReactNode }) { return <article className="border border-slate-200 bg-white p-5 shadow-sm"><h2 className="text-lg font-semibold text-slate-950">{title}</h2><div className="mt-4 divide-y divide-slate-100">{children}</div></article> }
function InfoLine({ label, value }: { label: string; value: string }) { return <div className="flex flex-wrap justify-between gap-3 py-3 text-sm"><span className="text-slate-500">{label}</span><span className="font-medium text-slate-950">{value}</span></div> }
function HistoryTable({ title, emptyLabel, headers, rows }: { title: string; emptyLabel: string; headers: string[]; rows: string[][] }) { return <section className="mt-8"><h2 className="mb-3 text-xl font-semibold text-slate-950">{title}</h2>{rows.length === 0 ? <div className="border border-slate-200 bg-white p-6 text-sm text-slate-600">{emptyLabel}</div> : <div className="overflow-x-auto border border-slate-200 bg-white shadow-sm"><table className="w-full min-w-[720px] text-left text-sm"><thead className="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-[0.12em] text-slate-500"><tr>{headers.map((header) => <th key={header} className="px-4 py-3 font-semibold">{header}</th>)}</tr></thead><tbody className="divide-y divide-slate-100">{rows.map((row, rowIndex) => <tr key={`${title}-${rowIndex}`} className="text-slate-700">{row.map((value, cellIndex) => <td key={`${title}-${rowIndex}-${cellIndex}`} className="px-4 py-3">{value}</td>)}</tr>)}</tbody></table></div>}</section> }
function formatDate(value: string | null): string { return value ? new Intl.DateTimeFormat('en', { dateStyle: 'medium' }).format(new Date(value)) : 'Not available' }

export default AdminAccountDetailPage