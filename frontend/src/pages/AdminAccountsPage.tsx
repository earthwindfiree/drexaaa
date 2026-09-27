import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { ApiError, apiRequest } from '../lib/api'
import type { AdminAccountsResponse, AdminAccountListItem } from '../types/api'

function AdminAccountsPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [accounts, setAccounts] = useState<AdminAccountListItem[]>([])
  const [meta, setMeta] = useState<AdminAccountsResponse['meta'] | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [searchInput, setSearchInput] = useState(searchParams.get('search') ?? '')
  const [tierInput, setTierInput] = useState(searchParams.get('tier') ?? '')

  useEffect(() => {
    let active = true
    const query = searchParams.toString()
    const path = query ? `/api/admin/accounts?${query}` : '/api/admin/accounts'

    void apiRequest<AdminAccountsResponse>(path)
      .then((response) => {
        if (!active) return

        setAccounts(response.data)
        setMeta(response.meta)
      })
      .catch((requestError: unknown) => {
        if (!active) return

        if (requestError instanceof ApiError && requestError.status === 401) {
          setError('Your session has expired. Please sign in again.')
        } else if (requestError instanceof ApiError && requestError.status === 403) {
          setError('You are not authorized to view the accounts list.')
        } else {
          setError(requestError instanceof Error ? requestError.message : 'Unable to load accounts.')
        }
        setAccounts([])
        setMeta(null)
      })
      .finally(() => {
        if (active) setLoading(false)
      })

    return () => {
      active = false
    }
  }, [searchParams])

  const tradingStatus = searchParams.get('trading_status') ?? ''

  function applyFilters(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setLoading(true)
    setError(null)
    const nextParams = new URLSearchParams()
    const search = searchInput.trim()
    const tier = tierInput.trim()

    if (search) nextParams.set('search', search)
    if (tier) nextParams.set('tier', tier)
    if (tradingStatus) nextParams.set('trading_status', tradingStatus)
    nextParams.set('page', '1')
    setSearchParams(nextParams)
  }

  function updateTradingStatus(value: string) {
    setLoading(true)
    setError(null)
    const nextParams = new URLSearchParams(searchParams)
    if (value) nextParams.set('trading_status', value)
    else nextParams.delete('trading_status')
    nextParams.set('page', '1')
    setSearchParams(nextParams)
  }

  function clearFilters() {
    setLoading(true)
    setError(null)
    setSearchInput('')
    setTierInput('')
    setSearchParams({ page: '1' })
  }

  function goToPage(page: number) {
    setLoading(true)
    setError(null)
    const nextParams = new URLSearchParams(searchParams)
    nextParams.set('page', String(page))
    setSearchParams(nextParams)
  }

  return (
    <section className="mx-auto max-w-7xl px-6 py-10 lg:px-8">
      <div className="mb-8">
        <p className="text-sm font-semibold uppercase tracking-[0.18em] text-cyan-700">Account simulation</p>
        <h1 className="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Accounts</h1>
        <p className="mt-2 max-w-2xl text-sm text-slate-600">Review simulated account state and open a read-only account history.</p>
      </div>

      <form onSubmit={applyFilters} className="mb-6 grid gap-3 border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_1fr_auto_auto]">
        <label className="text-sm text-slate-600">
          Search
          <input value={searchInput} onChange={(event) => setSearchInput(event.target.value)} placeholder="User name or email" className="mt-1 block w-full border border-slate-300 px-3 py-2 text-sm text-slate-950 outline-none focus:border-cyan-600" />
        </label>
        <label className="text-sm text-slate-600">
          Tier
          <input value={tierInput} onChange={(event) => setTierInput(event.target.value)} placeholder="Tier name" className="mt-1 block w-full border border-slate-300 px-3 py-2 text-sm text-slate-950 outline-none focus:border-cyan-600" />
        </label>
        <label className="text-sm text-slate-600">
          Trading status
          <select value={tradingStatus} onChange={(event) => updateTradingStatus(event.target.value)} className="mt-1 block w-full border border-slate-300 bg-white px-3 py-2 text-sm text-slate-950 outline-none focus:border-cyan-600">
            <option value="">All statuses</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </label>
        <button type="submit" className="self-end bg-slate-950 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800">Apply</button>
        <button type="button" onClick={clearFilters} className="self-end border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-500">Clear</button>
      </form>

      {loading && <p className="border border-slate-200 bg-white p-6 text-sm text-slate-600">Loading accounts...</p>}
      {!loading && error && <div role="alert" className="border border-rose-200 bg-rose-50 p-6 text-sm text-rose-800">{error}</div>}
      {!loading && !error && accounts.length === 0 && <div className="border border-slate-200 bg-white p-10 text-center shadow-sm"><h2 className="text-lg font-semibold text-slate-950">No accounts found</h2><p className="mt-2 text-sm text-slate-600">Try changing the search or filters.</p></div>}

      {!loading && !error && accounts.length > 0 && (
        <>
          <div className="overflow-x-auto border border-slate-200 bg-white shadow-sm">
            <table className="w-full min-w-[1050px] text-left text-sm">
              <thead className="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-[0.12em] text-slate-500">
                <tr>
                  <th className="px-4 py-3 font-semibold">Account / user</th>
                  <th className="px-4 py-3 font-semibold">Managed balance</th>
                  <th className="px-4 py-3 font-semibold">Pending balance</th>
                  <th className="px-4 py-3 font-semibold">P/L</th>
                  <th className="px-4 py-3 font-semibold">Performance</th>
                  <th className="px-4 py-3 font-semibold">Tier</th>
                  <th className="px-4 py-3 font-semibold">Trading</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {accounts.map((account) => <AccountRow key={account.id} account={account} />)}
              </tbody>
            </table>
          </div>
          {meta && <Pagination meta={meta} onPageChange={goToPage} />}
        </>
      )}
    </section>
  )
}

function AccountRow({ account }: { account: AdminAccountListItem }) {
  return (
    <tr className="align-top text-slate-700">
      <td className="px-4 py-4"><Link to={`/admin/accounts/${account.id}`} className="font-semibold text-cyan-700 hover:text-cyan-900">{account.user?.name ?? 'Unknown user'}</Link><p className="mt-1 text-xs text-slate-500">Account #{account.id} · {account.user?.email ?? 'No email'}</p></td>
      <td className="px-4 py-4 font-medium text-slate-950">${account.managed_balance}</td>
      <td className="px-4 py-4">${account.pending_balance}</td>
      <td className="px-4 py-4">${account.total_profit_loss}</td>
      <td className="px-4 py-4">{account.performance_percentage}%</td>
      <td className="px-4 py-4">{account.tier?.name ?? 'Unassigned'}</td>
      <td className="px-4 py-4 capitalize">{account.trading_status}</td>
    </tr>
  )
}

function Pagination({ meta, onPageChange }: { meta: AdminAccountsResponse['meta']; onPageChange: (page: number) => void }) {
  return <div className="flex flex-wrap items-center justify-between gap-3 px-1 py-4 text-sm text-slate-600"><p>{meta.from ?? 0}-{meta.to ?? 0} of {meta.total}</p><div className="flex items-center gap-2"><button type="button" disabled={meta.current_page <= 1} onClick={() => onPageChange(meta.current_page - 1)} className="border border-slate-300 px-3 py-2 font-medium disabled:cursor-not-allowed disabled:opacity-40">Previous</button><span className="px-2">Page {meta.current_page} of {meta.last_page}</span><button type="button" disabled={meta.current_page >= meta.last_page} onClick={() => onPageChange(meta.current_page + 1)} className="border border-slate-300 px-3 py-2 font-medium disabled:cursor-not-allowed disabled:opacity-40">Next</button></div></div>
}

export default AdminAccountsPage