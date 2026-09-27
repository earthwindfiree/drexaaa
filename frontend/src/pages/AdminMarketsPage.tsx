import { useEffect, useState, type FormEvent } from 'react'
import { ApiError, apiRequest, initializeCsrfCookie } from '../lib/api'
import type { AdminMarketAsset, AdminMarketsResponse, AdminWallet } from '../types/api'

async function fetchMarkets(): Promise<AdminMarketsResponse> {
  return apiRequest<AdminMarketsResponse>('/api/admin/markets')
}

function AdminMarketsPage() {
  const [assets, setAssets] = useState<AdminMarketAsset[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    let active = true

    void fetchMarkets()
      .then((response) => {
        if (active) setAssets(response.data)
      })
      .catch((requestError: unknown) => {
        if (!active) return
        setError(errorMessage(requestError, 'Unable to load market configuration.'))
      })
      .finally(() => {
        if (active) setLoading(false)
      })

    return () => { active = false }
  }, [])

  async function refresh() {
    const response = await fetchMarkets()
    setAssets(response.data)
  }

  async function updateAssetStatus(asset: AdminMarketAsset) {
    setError(null)
    try {
      await initializeCsrfCookie()
      await apiRequest(`/api/admin/markets/${asset.id}/status`, { method: 'PATCH', body: JSON.stringify({ active: !asset.active }) })
      await refresh()
    } catch (requestError: unknown) {
      setError(errorMessage(requestError, 'Unable to update asset status.'))
    }
  }

  return (
    <section className="mx-auto max-w-7xl px-6 py-10 lg:px-8">
      <div className="mb-8"><p className="text-sm font-semibold uppercase tracking-[0.18em] text-cyan-700">Market configuration</p><h1 className="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Assets and simulated markets</h1><p className="mt-2 max-w-2xl text-sm text-slate-600">Admin-controlled demo assets, stored prices, and wallet configuration. No external market or blockchain connections are used.</p></div>
      {loading && <p className="border border-slate-200 bg-white p-6 text-sm text-slate-600">Loading market configuration...</p>}
      {!loading && error && <div role="alert" className="mb-6 border border-rose-200 bg-rose-50 p-6 text-sm text-rose-800">{error}</div>}
      {!loading && !error && assets.length === 0 && <div className="border border-slate-200 bg-white p-10 text-center text-sm text-slate-600">No assets are configured.</div>}
      {!loading && !error && assets.length > 0 && <div className="space-y-6">{assets.map((asset) => <AssetPanel key={asset.id} asset={asset} onRefresh={refresh} onToggleStatus={() => void updateAssetStatus(asset)} onError={setError} />)}</div>}
    </section>
  )
}

function AssetPanel({ asset, onRefresh, onToggleStatus, onError }: { asset: AdminMarketAsset; onRefresh: () => Promise<void>; onToggleStatus: () => void; onError: (message: string | null) => void }) {
  return <article className="border border-slate-200 bg-white p-5 shadow-sm"><div className="flex flex-wrap items-start justify-between gap-4"><div><div className="flex flex-wrap items-center gap-3"><h2 className="text-xl font-semibold text-slate-950">{asset.symbol}</h2><span className="text-sm text-slate-500">{asset.name}</span><StatusBadge active={asset.active} /></div><p className="mt-2 text-sm text-slate-600">{asset.has_usable_wallet ? 'Exactly one active wallet is ready for deposits.' : 'This asset is not currently ready for deposits.'}</p></div><button type="button" onClick={onToggleStatus} className="border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-500">{asset.active ? 'Deactivate asset' : 'Activate asset'}</button></div><div className="mt-6 grid gap-6 xl:grid-cols-[1fr_1.4fr]"><PriceEditor asset={asset} onRefresh={onRefresh} onError={onError} /><WalletEditor asset={asset} onRefresh={onRefresh} onError={onError} /></div></article>
}

function PriceEditor({ asset, onRefresh, onError }: { asset: AdminMarketAsset; onRefresh: () => Promise<void>; onError: (message: string | null) => void }) {
  const [price, setPrice] = useState(asset.market_price?.current_price ?? '')
  const [change, setChange] = useState(asset.market_price?.change_24h_percentage ?? '')
  const [saving, setSaving] = useState(false)
  const [saved, setSaved] = useState(false)

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setSaving(true)
    setSaved(false)
    onError(null)
    try {
      await initializeCsrfCookie()
      await apiRequest(`/api/admin/markets/${asset.id}/price`, { method: 'PATCH', body: JSON.stringify({ current_price: price, change_24h_percentage: change }) })
      await onRefresh()
      setSaved(true)
    } catch (requestError: unknown) {
      onError(errorMessage(requestError, 'Unable to update simulated price.'))
    } finally {
      setSaving(false)
    }
  }

  return <form onSubmit={submit} className="border border-slate-200 bg-slate-50 p-4"><div className="mb-4"><h3 className="font-semibold text-slate-950">Simulated market price</h3><p className="mt-1 text-xs text-slate-500">Stored admin values only. Price changes do not alter account state.</p></div><div className="grid gap-3 sm:grid-cols-2"><label className="text-sm font-medium text-slate-700">Current price<input value={price} onChange={(event) => setPrice(event.target.value)} inputMode="decimal" className="mt-1 block w-full border border-slate-300 bg-white px-3 py-2 text-sm text-slate-950 outline-none focus:border-cyan-700" /></label><label className="text-sm font-medium text-slate-700">24h change %<input value={change} onChange={(event) => setChange(event.target.value)} inputMode="decimal" className="mt-1 block w-full border border-slate-300 bg-white px-3 py-2 text-sm text-slate-950 outline-none focus:border-cyan-700" /></label></div><div className="mt-4 flex items-center gap-3"><button type="submit" disabled={saving} className="bg-slate-950 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:opacity-60">{saving ? 'Saving...' : 'Save price'}</button>{saved && <span role="status" className="text-sm font-medium text-emerald-700">Saved and refreshed.</span>}</div></form>
}

function WalletEditor({ asset, onRefresh, onError }: { asset: AdminMarketAsset; onRefresh: () => Promise<void>; onError: (message: string | null) => void }) {
  const [address, setAddress] = useState('')
  const [network, setNetwork] = useState('')
  const [active, setActive] = useState(false)
  const [saving, setSaving] = useState(false)

  async function addWallet(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setSaving(true)
    onError(null)
    try {
      await initializeCsrfCookie()
      await apiRequest(`/api/admin/markets/${asset.id}/wallets`, { method: 'POST', body: JSON.stringify({ wallet_address: address, network: network || null, active }) })
      setAddress('')
      setNetwork('')
      setActive(false)
      await onRefresh()
    } catch (requestError: unknown) {
      onError(errorMessage(requestError, 'Unable to add wallet configuration.'))
    } finally {
      setSaving(false)
    }
  }

  return <div className="border border-slate-200 p-4"><div className="mb-4"><h3 className="font-semibold text-slate-950">Wallet configuration</h3><p className="mt-1 text-xs text-slate-500">Demo addresses are admin-configured and are not externally verified.</p></div><div className="space-y-2">{asset.wallets.map((wallet) => <WalletRow key={wallet.id} wallet={wallet} onRefresh={onRefresh} onError={onError} />)}</div><form onSubmit={addWallet} className="mt-4 border-t border-slate-200 pt-4"><div className="grid gap-3 sm:grid-cols-[1.4fr_1fr_auto]"><label className="text-sm font-medium text-slate-700">Wallet address<input value={address} onChange={(event) => setAddress(event.target.value)} placeholder="Demo wallet address" className="mt-1 block w-full border border-slate-300 px-3 py-2 text-sm text-slate-950 outline-none focus:border-cyan-700" /></label><label className="text-sm font-medium text-slate-700">Network<input value={network} onChange={(event) => setNetwork(event.target.value)} placeholder="Optional" className="mt-1 block w-full border border-slate-300 px-3 py-2 text-sm text-slate-950 outline-none focus:border-cyan-700" /></label><label className="flex items-end gap-2 pb-2 text-sm text-slate-700"><input type="checkbox" checked={active} onChange={(event) => setActive(event.target.checked)} /> Active</label></div><button type="submit" disabled={saving} className="mt-3 border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-500 disabled:opacity-60">{saving ? 'Adding...' : 'Add wallet'}</button></form></div>
}

function WalletRow({ wallet, onRefresh, onError }: { wallet: AdminWallet; onRefresh: () => Promise<void>; onError: (message: string | null) => void }) {
  const [saving, setSaving] = useState(false)

  async function toggle() {
    setSaving(true)
    onError(null)
    try {
      await initializeCsrfCookie()
      await apiRequest(`/api/admin/wallets/${wallet.id}/status`, { method: 'PATCH', body: JSON.stringify({ active: !wallet.active }) })
      await onRefresh()
    } catch (requestError: unknown) {
      onError(errorMessage(requestError, 'Unable to update wallet status.'))
    } finally {
      setSaving(false)
    }
  }

  return <div className="flex flex-wrap items-center justify-between gap-3 bg-slate-50 px-3 py-3 text-sm"><div className="min-w-0"><p className="break-all font-medium text-slate-950">{wallet.wallet_address}</p><p className="mt-1 text-xs text-slate-500">{wallet.network ?? 'No network specified'} · Wallet #{wallet.id}</p></div><div className="flex items-center gap-3"><StatusBadge active={wallet.active} /><button type="button" disabled={saving} onClick={() => void toggle()} className="border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 disabled:opacity-50">{wallet.active ? 'Deactivate' : 'Activate'}</button></div></div>
}

function StatusBadge({ active }: { active: boolean }) { return <span className={`inline-block px-2 py-1 text-xs font-semibold ${active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-200 text-slate-600'}`}>{active ? 'Active' : 'Inactive'}</span> }
function errorMessage(error: unknown, fallback: string): string { if (error instanceof ApiError && Object.values(error.errors).length > 0) return Object.values(error.errors).flat().join(' '); return error instanceof Error ? error.message : fallback }

export default AdminMarketsPage