import { useEffect, useState } from 'react'
import { ApiError, apiRequest } from '../lib/api'

interface AdminDashboardSummary {
  total_users: number
  active_users: number
  suspended_users: number
  total_managed_balance: string
  total_pending_balance: string
  total_pending_deposits: number
  total_pending_withdrawals: number
  total_completed_deposits: number
  total_approved_withdrawals: number
  total_assets: number
  active_assets: number
  total_tiers: number
  total_accounts: number
}

interface SummaryResponse {
  data: AdminDashboardSummary
}

function AdminDashboardPage() {
  const [summary, setSummary] = useState<AdminDashboardSummary | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    let active = true

    void apiRequest<SummaryResponse>('/api/admin/dashboard/summary')
      .then((response) => {
        if (!active) return

        setSummary(response.data)
      })
      .catch((requestError: unknown) => {
        if (!active) return

        if (requestError instanceof ApiError && requestError.status === 401) {
          setError('Your session has expired. Please sign in again.')
        } else if (requestError instanceof ApiError && requestError.status === 403) {
          setError('You are not authorized to view the admin dashboard.')
        } else {
          setError(requestError instanceof Error ? requestError.message : 'Unable to load the admin dashboard.')
        }
      })
      .finally(() => {
        if (active) setLoading(false)
      })

    return () => {
      active = false
    }
  }, [])

  return (
    <section className="mx-auto max-w-7xl px-6 py-10 lg:px-8">
      <div className="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="text-sm font-semibold uppercase tracking-[0.18em] text-[var(--theme-accent)]">Admin dashboard</p>
          <h1 className="mt-2 text-3xl font-semibold tracking-tight text-[var(--theme-heading)]">Operational summary</h1>
          <p className="mt-2 max-w-2xl text-sm text-[var(--theme-muted)]">A live view of persisted account, funding, and platform configuration data.</p>
        </div>
        <span className="border border-[rgba(129,201,195,0.22)] bg-[rgba(129,201,195,0.08)] px-3 py-2 text-xs font-semibold uppercase tracking-[0.14em] text-[var(--theme-accent)]">Demo environment</span>
      </div>

      {loading && <p className="public-card p-6 text-sm text-[var(--theme-body)]">Loading operational summary...</p>}

      {!loading && error && (
        <div role="alert" className="public-card border border-[rgba(227,155,155,0.22)] bg-[rgba(127,29,29,0.10)] p-6 text-sm text-[var(--theme-heading)]">
          {error}
        </div>
      )}

      {!loading && !error && summary && (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <SummaryCard label="Managed balance" value={`$${summary.total_managed_balance}`} detail="Across all accounts" />
          <SummaryCard label="Pending balance" value={`$${summary.total_pending_balance}`} detail="Awaiting account review" />
          <SummaryCard label="Total users" value={summary.total_users.toLocaleString()} detail={`${summary.active_users} active · ${summary.suspended_users} suspended`} />
          <SummaryCard label="Accounts" value={summary.total_accounts.toLocaleString()} detail="Persisted account records" />
          <SummaryCard label="Pending deposits" value={summary.total_pending_deposits.toLocaleString()} detail={`${summary.total_completed_deposits} completed`} />
          <SummaryCard label="Pending withdrawals" value={summary.total_pending_withdrawals.toLocaleString()} detail={`${summary.total_approved_withdrawals} approved`} />
          <SummaryCard label="Assets" value={summary.total_assets.toLocaleString()} detail={`${summary.active_assets} active`} />
          <SummaryCard label="Tiers" value={summary.total_tiers.toLocaleString()} detail="Configured qualification tiers" />
        </div>
      )}
    </section>
  )
}

function SummaryCard({ label, value, detail }: { label: string; value: string; detail: string }) {
  return (
    <article className="public-card p-5">
      <p className="text-sm font-medium text-[var(--theme-muted)]">{label}</p>
      <p className="mt-3 text-2xl font-semibold tracking-tight text-[var(--theme-heading)]">{value}</p>
      <p className="mt-2 text-xs text-[var(--theme-muted)]">{detail}</p>
    </article>
  )
}

export default AdminDashboardPage