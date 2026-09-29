import { useEffect, useState, type FormEvent } from 'react'
import { ApiError, apiRequest } from '../lib/api'
import type { AdminStrategy, AdminTier, AdminTiersResponse } from '../types/api'

function AdminTiersPage() {
  const [tiers, setTiers] = useState<AdminTier[]>([])
  const [strategies, setStrategies] = useState<AdminStrategy[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  async function refresh() {
    const response = await apiRequest<AdminTiersResponse>('/api/admin/tiers')
    setTiers(response.data.tiers)
    setStrategies(response.data.strategies)
  }

  useEffect(() => {
    void apiRequest<AdminTiersResponse>('/api/admin/tiers')
      .then((response) => {
        setTiers(response.data.tiers)
        setStrategies(response.data.strategies)
      })
      .catch((requestError: unknown) => setError(errorMessage(requestError, 'Unable to load tier configuration.')))
      .finally(() => setLoading(false))
  }, [])

  return (
    <section className="mx-auto max-w-7xl px-6 py-10 lg:px-8">
      <div className="mb-8">
        <p className="text-sm font-semibold uppercase tracking-[0.18em] text-cyan-700">Tier configuration</p>
        <h1 className="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Tiers and strategies</h1>
        <p className="mt-2 max-w-2xl text-sm text-slate-600">
          Configure automatic managed-balance thresholds, strategy assignments, and the descriptive profile used by each tier.
        </p>
      </div>

      {loading && <p className="border border-slate-200 bg-white p-6 text-sm text-slate-600">Loading tiers...</p>}
      {!loading && error && <div role="alert" className="border border-rose-200 bg-rose-50 p-6 text-sm text-rose-800">{error}</div>}
      {!loading && !error && (
        <div className="space-y-6">
          {tiers.map((tier) => <TierEditor key={tier.id} tier={tier} strategies={strategies} onRefresh={refresh} />)}

          <div className="space-y-4">
            <div>
              <p className="text-sm font-semibold uppercase tracking-[0.18em] text-cyan-700">Strategy profiles</p>
              <h2 className="mt-2 text-2xl font-semibold text-slate-950">Live strategy configuration</h2>
            </div>
            {strategies.map((strategy) => <StrategyEditor key={strategy.id} strategy={strategy} onRefresh={refresh} />)}
          </div>
        </div>
      )}
    </section>
  )
}

function TierEditor({ tier, strategies, onRefresh }: { tier: AdminTier; strategies: AdminStrategy[]; onRefresh: () => Promise<void> }) {
  const [name, setName] = useState(tier.name)
  const [minimumBalance, setMinimumBalance] = useState(tier.minimum_balance)
  const [strategyId, setStrategyId] = useState(String(tier.strategy?.id ?? ''))
  const [description, setDescription] = useState(tier.description)
  const [benefits, setBenefits] = useState(JSON.stringify(tier.benefits ?? [], null, 2))
  const [featureAccess, setFeatureAccess] = useState(JSON.stringify(tier.feature_access ?? [], null, 2))
  const [displaySettings, setDisplaySettings] = useState(JSON.stringify(tier.display_settings ?? {}, null, 2))
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [saved, setSaved] = useState(false)

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setSaving(true)
    setSaved(false)
    setError(null)

    let benefitsValue: unknown
    let featureAccessValue: unknown
    let displaySettingsValue: unknown

    try {
      benefitsValue = JSON.parse(benefits)
      featureAccessValue = JSON.parse(featureAccess)
      displaySettingsValue = JSON.parse(displaySettings)
    } catch {
      setError('Benefits, feature access, and display settings must contain valid JSON.')
      setSaving(false)
      return
    }

    try {
      await apiRequest(`/api/admin/tiers/${tier.id}`, {
        method: 'PATCH',
        body: JSON.stringify({
          name,
          minimum_balance: minimumBalance,
          strategy_id: Number(strategyId),
          description,
          benefits: benefitsValue,
          feature_access: featureAccessValue,
          display_settings: displaySettingsValue,
        }),
      })
      await onRefresh()
      setSaved(true)
    } catch (requestError: unknown) {
      setError(errorMessage(requestError, 'Unable to save tier configuration.'))
    } finally {
      setSaving(false)
    }
  }

  const strategy = strategies.find((item) => String(item.id) === strategyId) ?? tier.strategy

  return (
    <form onSubmit={submit} className="border border-slate-200 bg-white p-5 shadow-sm">
      <div className="mb-5 flex flex-wrap items-start justify-between gap-4">
        <div>
          <p className="text-sm font-semibold uppercase tracking-[0.18em] text-cyan-700">Tier #{tier.id}</p>
          <h2 className="mt-2 text-2xl font-semibold text-slate-950">{tier.name}</h2>
          <p className="mt-1 text-sm text-slate-500">Automatic qualification uses managed balance only.</p>
        </div>
        <span className="bg-slate-100 px-3 py-2 text-xs font-semibold uppercase tracking-[0.12em] text-slate-600">Read/write configuration</span>
      </div>

      <div className="grid gap-4 md:grid-cols-2">
        <Field label="Tier name" value={name} onChange={setName} />
        <Field label="Minimum managed balance" value={minimumBalance} onChange={setMinimumBalance} inputMode="decimal" />
        <label className="text-sm font-medium text-slate-700">
          Assigned strategy
          <select
            value={strategyId}
            onChange={(event) => setStrategyId(event.target.value)}
            className="mt-1 block w-full border border-slate-300 bg-white px-3 py-2 text-sm text-slate-950 outline-none focus:border-cyan-700"
          >
            {strategies.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
          </select>
        </label>
        <div className="border border-slate-200 bg-slate-50 p-3 text-sm">
          <p className="font-medium text-slate-500">Strategy profile</p>
          <p className="mt-1 font-semibold text-slate-950">{strategy?.name ?? 'Not assigned'}</p>
          <p className="mt-1 text-slate-600">{strategy?.risk_profile ?? 'No risk profile'}</p>
          <p className="mt-2 text-xs text-slate-500">{strategy?.description ?? 'No strategy description'}</p>
        </div>
      </div>

      <label className="mt-4 block text-sm font-medium text-slate-700">
        Tier description
        <textarea value={description} onChange={(event) => setDescription(event.target.value)} rows={3} className="mt-1 block w-full border border-slate-300 px-3 py-2 text-sm text-slate-950 outline-none focus:border-cyan-700" />
      </label>

      <div className="mt-4 grid gap-4 lg:grid-cols-3">
        <JsonField label="Benefits" value={benefits} onChange={setBenefits} />
        <JsonField label="Feature access" value={featureAccess} onChange={setFeatureAccess} />
        <JsonField label="Display settings" value={displaySettings} onChange={setDisplaySettings} />
      </div>

      {error && <p role="alert" className="mt-4 text-sm text-rose-700">{error}</p>}
      {saved && <p className="mt-4 text-sm text-emerald-700">Tier configuration saved.</p>}
      <button type="submit" disabled={saving} className="mt-5 inline-flex items-center justify-center rounded-md bg-cyan-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-cyan-800 disabled:opacity-60">
        {saving ? 'Saving...' : 'Save tier'}
      </button>
    </form>
  )
}

function StrategyEditor({ strategy, onRefresh }: { strategy: AdminStrategy; onRefresh: () => Promise<void> }) {
  const [name, setName] = useState(strategy.name)
  const [description, setDescription] = useState(strategy.description)
  const [riskProfile, setRiskProfile] = useState(strategy.risk_profile)
  const [active, setActive] = useState(strategy.active)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [saved, setSaved] = useState(false)

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setSaving(true)
    setError(null)
    setSaved(false)

    try {
      await apiRequest(`/api/admin/strategies/${strategy.id}`, {
        method: 'PATCH',
        body: JSON.stringify({
          name,
          description,
          risk_profile: riskProfile,
          active,
        }),
      })
      await onRefresh()
      setSaved(true)
    } catch (requestError: unknown) {
      setError(errorMessage(requestError, 'Unable to save strategy profile.'))
    } finally {
      setSaving(false)
    }
  }

  return (
    <form onSubmit={submit} className="border border-slate-200 bg-white p-5 shadow-sm">
      <div className="mb-4 flex items-center justify-between gap-3">
        <div>
          <p className="text-sm font-semibold uppercase tracking-[0.18em] text-cyan-700">Strategy profile</p>
          <h3 className="mt-2 text-xl font-semibold text-slate-950">{strategy.name}</h3>
        </div>
        <label className="flex items-center gap-2 text-sm font-medium text-slate-700">
          Active
          <input type="checkbox" checked={active} onChange={(event) => setActive(event.target.checked)} className="h-4 w-4" />
        </label>
      </div>

      <div className="grid gap-4 md:grid-cols-2">
        <label className="text-sm font-medium text-slate-700">
          Strategy name
          <input value={name} onChange={(event) => setName(event.target.value)} className="mt-1 block w-full border border-slate-300 px-3 py-2 text-sm text-slate-950 outline-none focus:border-cyan-700" />
        </label>
        <label className="text-sm font-medium text-slate-700">
          Risk profile
          <input value={riskProfile} onChange={(event) => setRiskProfile(event.target.value)} className="mt-1 block w-full border border-slate-300 px-3 py-2 text-sm text-slate-950 outline-none focus:border-cyan-700" />
        </label>
      </div>

      <label className="mt-4 block text-sm font-medium text-slate-700">
        Strategy description
        <textarea value={description} onChange={(event) => setDescription(event.target.value)} rows={4} className="mt-1 block w-full border border-slate-300 px-3 py-2 text-sm text-slate-950 outline-none focus:border-cyan-700" />
      </label>

      {error && <p role="alert" className="mt-4 text-sm text-rose-700">{error}</p>}
      {saved && <p className="mt-4 text-sm text-emerald-700">Strategy profile saved.</p>}
      <button type="submit" disabled={saving} className="mt-5 inline-flex items-center justify-center rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:opacity-60">
        {saving ? 'Saving...' : 'Save strategy'}
      </button>
    </form>
  )
}

function Field({ label, value, onChange, inputMode }: { label: string; value: string; onChange: (value: string) => void; inputMode?: 'decimal' }) {
  return (
    <label className="text-sm font-medium text-slate-700">
      {label}
      <input value={value} onChange={(event) => onChange(event.target.value)} inputMode={inputMode} className="mt-1 block w-full border border-slate-300 px-3 py-2 text-sm text-slate-950 outline-none focus:border-cyan-700" />
    </label>
  )
}

function JsonField({ label, value, onChange }: { label: string; value: string; onChange: (value: string) => void }) {
  return (
    <label className="text-sm font-medium text-slate-700">
      {label}
      <textarea value={value} onChange={(event) => onChange(event.target.value)} rows={7} className="mt-1 block w-full border border-slate-300 bg-slate-50 px-3 py-2 font-mono text-xs text-slate-950 outline-none focus:border-cyan-700" />
    </label>
  )
}

function errorMessage(error: unknown, fallback: string): string {
  if (error instanceof ApiError && Object.values(error.errors).length > 0) {
    return Object.values(error.errors).flat().join(' ')
  }

  return error instanceof Error ? error.message : fallback
}

export default AdminTiersPage
