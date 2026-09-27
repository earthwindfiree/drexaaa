import { useEffect, useState, type FormEvent } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { ApiError, apiRequest } from '../lib/api'
import type { AdminDepositListItem, AdminDepositsResponse, AdminMarketAsset } from '../types/api'

function AdminDepositsPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [deposits, setDeposits] = useState<AdminDepositListItem[]>([])
  const [assets, setAssets] = useState<AdminMarketAsset[]>([])
  const [meta, setMeta] = useState<AdminDepositsResponse['meta'] | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [searchInput, setSearchInput] = useState(searchParams.get('search') ?? '')

  useEffect(() => {
    void apiRequest<{ data: AdminMarketAsset[] }>('/api/admin/markets')
      .then((response) => setAssets(response.data))
      .catch(() => undefined)
  }, [])

  useEffect(() => {
    let active = true
    const query = searchParams.toString()
    const path = query ? `/api/admin/deposits?${query}` : '/api/admin/deposits'

    void apiRequest<AdminDepositsResponse>(path)
      .then((response) => {
        if (!active) return
        setDeposits(response.data)
        setMeta(response.meta)
      })
      .catch((requestError: unknown) => {
        if (!active) return
        setError(errorMessage(requestError, 'Unable to load deposits.'))
        setDeposits([])
        setMeta(null)
      })
      .finally(() => {
        if (active) setLoading(false)
      })

    return () => { active = false }
  }, [searchParams])

  const status = searchParams.get('status') ?? 'pending'
  const assetId = searchParams.get('asset_id') ?? ''

  function applyFilters(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setLoading(true)
    setError(null)
    const nextParams = new URLSearchParams()
    const search = searchInput.trim()
    if (status) nextParams.set('status', status)
    if (assetId) nextParams.set('asset_id', assetId)
    if (search) nextParams.set('search', search)
    nextParams.set('page', '1')
    setSearchParams(nextParams)
  }

  function updateFilter(name: 'status' | 'asset_id', value: string) {
    setLoading(true)
    setError(null)
    const nextParams = new URLSearchParams(searchParams)
    if (value) nextParams.set(name, value)
    else nextParams.delete(name)
    nextParams.set('page', '1')
    setSearchParams(nextParams)
  }

  function clearFilters() {
    setLoading(true)
    setError(null)
    setSearchInput('')
    setSearchParams({ status: 'pending', page: '1' })
  }

  function goToPage(page: number) {
    setLoading(true)
    setError(null)
    const nextParams = new URLSearchParams(searchParams)
    nextParams.set('page', String(page))
    setSearchParams(nextParams)
  }

  return <section className="mx-auto max-w-7xl px-6 py-10 lg:px-8"><div className="mb-8"><p className="text-sm font-semibold uppercase tracking-[0.18em] text-cyan-700">Deposit review</p><h1 className="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Deposits</h1><p className="mt-2 max-w-2xl text-sm text-slate-600">Review user-submitted deposits using their stored historical price and value snapshots.</p></div><form onSubmit={applyFilters} className="mb-6 grid gap-3 border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-[minmax(0,2fr)_1fr_1fr_auto_auto]"><label className="text-sm text-slate-600">User search<input value={searchInput} onChange={(event) => setSearchInput(event.target.value)} placeholder="Name or email" className="mt-1 block w-full border border-slate-300 px-3 py-2 text-sm text-slate-950 outline-none focus:border-cyan-600" /></label><label className="text-sm text-slate-600">Status<select value={status} onChange={(event) => updateFilter('status', event.target.value)} className="mt-1 block w-full border border-slate-300 bg-white px-3 py-2 text-sm text-slate-950 outline-none focus:border-cyan-600"><option value="">All statuses</option><option value="pending">Pending</option><option value="confirmed">Confirmed</option><option value="rejected">Rejected</option></select></label><label className="text-sm text-slate-600">Asset<select value={assetId} onChange={(event) => updateFilter('asset_id', event.target.value)} className="mt-1 block w-full border border-slate-300 bg-white px-3 py-2 text-sm text-slate-950 outline-none focus:border-cyan-600"><option value="">All assets</option>{assets.map((asset) => <option key={asset.id} value={asset.id}>{asset.symbol}</option>)}</select></label><button type="submit" className="self-end bg-slate-950 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800">Apply</button><button type="button" onClick={clearFilters} className="self-end border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-500">Clear</button></form>{loading && <p className="border border-slate-200 bg-white p-6 text-sm text-slate-600">Loading deposits...</p>}{!loading && error && <div role="alert" className="border border-rose-200 bg-rose-50 p-6 text-sm text-rose-800">{error}</div>}{!loading && !error && deposits.length === 0 && <div className="border border-slate-200 bg-white p-10 text-center shadow-sm"><h2 className="text-lg font-semibold text-slate-950">No deposits found</h2><p className="mt-2 text-sm text-slate-600">Try changing the status, asset, or user search.</p></div>}{!loading && !error && deposits.length > 0 && <><div className="overflow-x-auto border border-slate-200 bg-white shadow-sm"><table className="w-full min-w-[1050px] text-left text-sm"><thead className="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-[0.12em] text-slate-500"><tr><th className="px-4 py-3 font-semibold">User</th><th className="px-4 py-3 font-semibold">Asset</th><th className="px-4 py-3 font-semibold">Crypto amount</th><th className="px-4 py-3 font-semibold">USD value</th><th className="px-4 py-3 font-semibold">Status</th><th className="px-4 py-3 font-semibold">Submitted</th><th className="px-4 py-3 font-semibold">Reference</th></tr></thead><tbody className="divide-y divide-slate-100">{deposits.map((deposit) => <DepositRow key={deposit.id} deposit={deposit} />)}</tbody></table></div>{meta && <Pagination meta={meta} onPageChange={goToPage} />}</>}</section>
}

function DepositRow({ deposit }: { deposit: AdminDepositListItem }) { return <tr className="align-top text-slate-700"><td className="px-4 py-4"><Link to={`/admin/deposits/${deposit.id}`} className="font-semibold text-cyan-700 hover:text-cyan-900">{deposit.user?.name ?? 'Unknown user'}</Link><p className="mt-1 text-xs text-slate-500">{deposit.user?.email ?? 'No email'}</p></td><td className="px-4 py-4 font-medium text-slate-950">{deposit.asset?.symbol ?? 'N/A'}<p className="mt-1 text-xs text-slate-500">{deposit.wallet?.network ?? 'No network'}</p></td><td className="px-4 py-4">{deposit.crypto_amount}</td><td className="px-4 py-4 font-medium text-slate-950">${deposit.usd_value}</td><td className="px-4 py-4"><StatusBadge status={deposit.status} /></td><td className="px-4 py-4 whitespace-nowrap">{formatDate(deposit.created_at)}</td><td className="px-4 py-4">{deposit.transaction_reference}</td></tr> }
function StatusBadge({ status }: { status: string }) { const style = status === 'pending' ? 'bg-amber-50 text-amber-700' : status === 'confirmed' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'; return <span className={`inline-block px-2 py-1 text-xs font-semibold capitalize ${style}`}>{status}</span> }
function Pagination({ meta, onPageChange }: { meta: AdminDepositsResponse['meta']; onPageChange: (page: number) => void }) { return <div className="flex flex-wrap items-center justify-between gap-3 px-1 py-4 text-sm text-slate-600"><p>{meta.from ?? 0}-{meta.to ?? 0} of {meta.total}</p><div className="flex items-center gap-2"><button type="button" disabled={meta.current_page <= 1} onClick={() => onPageChange(meta.current_page - 1)} className="border border-slate-300 px-3 py-2 font-medium disabled:cursor-not-allowed disabled:opacity-40">Previous</button><span className="px-2">Page {meta.current_page} of {meta.last_page}</span><button type="button" disabled={meta.current_page >= meta.last_page} onClick={() => onPageChange(meta.current_page + 1)} className="border border-slate-300 px-3 py-2 font-medium disabled:cursor-not-allowed disabled:opacity-40">Next</button></div></div> }
function formatDate(value: string): string { return new Intl.DateTimeFormat('en', { dateStyle: 'medium' }).format(new Date(value)) }
function errorMessage(error: unknown, fallback: string): string { if (error instanceof ApiError && Object.values(error.errors).length > 0) return Object.values(error.errors).flat().join(' '); return error instanceof Error ? error.message : fallback }

export default AdminDepositsPage