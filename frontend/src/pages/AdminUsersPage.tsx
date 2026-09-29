import { useEffect, useState, type FormEvent } from 'react'
import { useSearchParams } from 'react-router-dom'
import { ApiError, apiRequest, initializeCsrfCookie } from '../lib/api'
import { useAuth } from '../features/auth/AuthContext'
import type { AdminUser, AdminUsersResponse } from '../types/api'

function AdminUsersPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const { user: currentUser } = useAuth()
  const [users, setUsers] = useState<AdminUser[]>([])
  const [meta, setMeta] = useState<AdminUsersResponse['meta'] | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [searchInput, setSearchInput] = useState(searchParams.get('search') ?? '')
  const [tierInput, setTierInput] = useState(searchParams.get('tier') ?? '')
  const [refreshToken, setRefreshToken] = useState(0)

  useEffect(() => {
    let active = true
    const query = searchParams.toString()
    const path = query ? `/api/admin/users?${query}` : '/api/admin/users'

    void apiRequest<AdminUsersResponse>(path)
      .then((response) => {
        if (!active) return

        setUsers(response.data)
        setMeta(response.meta)
      })
      .catch((requestError: unknown) => {
        if (!active) return

        if (requestError instanceof ApiError && requestError.status === 401) {
          setError('Your session has expired. Please sign in again.')
        } else if (requestError instanceof ApiError && requestError.status === 403) {
          setError('You are not authorized to view the users list.')
        } else {
          setError(requestError instanceof Error ? requestError.message : 'Unable to load users.')
        }
        setUsers([])
        setMeta(null)
      })
      .finally(() => {
        if (active) setLoading(false)
      })

    return () => {
      active = false
    }
  }, [searchParams, refreshToken])

  function applyFilters(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setLoading(true)
    setError(null)
    const nextParams = new URLSearchParams()
    const search = searchInput.trim()
    const tier = tierInput.trim()

    if (search) nextParams.set('search', search)
    if (status) nextParams.set('status', status)
    if (role) nextParams.set('role', role)
    if (tier) nextParams.set('tier', tier)
    nextParams.set('page', '1')
    setSearchParams(nextParams)
  }

  const status = searchParams.get('status') ?? ''
  const role = searchParams.get('role') ?? ''

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
        <p className="text-sm font-semibold uppercase tracking-[0.18em] text-cyan-700">User management</p>
        <h1 className="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Users</h1>
        <p className="mt-2 max-w-2xl text-sm text-slate-600">Review account access, balances, tiers, and trading status.</p>
      </div>

      <form onSubmit={applyFilters} className="mb-6 grid gap-3 border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-[minmax(0,2fr)_1fr_1fr_minmax(0,1fr)_auto_auto]">
        <label className="text-sm text-slate-600">
          Search
          <input
            value={searchInput}
            onChange={(event) => setSearchInput(event.target.value)}
            placeholder="Name or email"
            className="mt-1 block w-full border border-slate-300 px-3 py-2 text-sm text-slate-950 outline-none focus:border-cyan-600"
          />
        </label>
        <label className="text-sm text-slate-600">
          Status
          <select value={status} onChange={(event) => updateFilter('status', event.target.value)} className="mt-1 block w-full border border-slate-300 bg-white px-3 py-2 text-sm text-slate-950 outline-none focus:border-cyan-600">
            <option value="">All statuses</option>
            <option value="active">Active</option>
            <option value="suspended">Suspended</option>
          </select>
        </label>
        <label className="text-sm text-slate-600">
          Role
          <select value={role} onChange={(event) => updateFilter('role', event.target.value)} className="mt-1 block w-full border border-slate-300 bg-white px-3 py-2 text-sm text-slate-950 outline-none focus:border-cyan-600">
            <option value="">All roles</option>
            <option value="user">User</option>
            <option value="admin">Admin</option>
            <option value="super_admin">Super admin</option>
          </select>
        </label>
        <label className="text-sm text-slate-600">
          Tier
          <input
            value={tierInput}
            onChange={(event) => setTierInput(event.target.value)}
            placeholder="Tier name"
            className="mt-1 block w-full border border-slate-300 px-3 py-2 text-sm text-slate-950 outline-none focus:border-cyan-600"
          />
        </label>
        <button type="submit" className="self-end bg-slate-950 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800">Apply</button>
        <button type="button" onClick={clearFilters} className="self-end border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-500">Clear</button>
      </form>

      {loading && <p className="border border-slate-200 bg-white p-6 text-sm text-slate-600">Loading users...</p>}

      {!loading && error && <div role="alert" className="border border-rose-200 bg-rose-50 p-6 text-sm text-rose-800">{error}</div>}

      {!loading && !error && users.length === 0 && (
        <div className="border border-slate-200 bg-white p-10 text-center shadow-sm">
          <h2 className="text-lg font-semibold text-slate-950">No users found</h2>
          <p className="mt-2 text-sm text-slate-600">Try changing the search or filters.</p>
        </div>
      )}

      {!loading && !error && users.length > 0 && (
        <>
          <div className="overflow-x-auto border border-slate-200 bg-white shadow-sm">
            <table className="w-full min-w-[1200px] text-left text-sm">
              <thead className="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-[0.12em] text-slate-500">
                <tr>
                  <th className="px-4 py-3 font-semibold">User</th>
                  <th className="px-4 py-3 font-semibold">Role / status</th>
                  <th className="px-4 py-3 font-semibold">Managed balance</th>
                  <th className="px-4 py-3 font-semibold">Pending balance</th>
                  <th className="px-4 py-3 font-semibold">Tier</th>
                  <th className="px-4 py-3 font-semibold">Trading</th>
                  <th className="px-4 py-3 font-semibold">Joined</th>
                  {currentUser?.role === 'super_admin' && <th className="px-4 py-3 font-semibold">Access management</th>}
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {users.map((user) => <UserRow key={user.id} user={user} canManageAccess={currentUser?.role === 'super_admin'} onAccessUpdated={() => setRefreshToken((value) => value + 1)} />)}
              </tbody>
            </table>
          </div>
          {meta && <Pagination meta={meta} onPageChange={goToPage} />}
        </>
      )}
    </section>
  )

  function updateFilter(name: 'status' | 'role', value: string) {
    setLoading(true)
    setError(null)
    const nextParams = new URLSearchParams(searchParams)
    if (value) nextParams.set(name, value)
    else nextParams.delete(name)
    nextParams.set('page', '1')
    setSearchParams(nextParams)
  }
}

function UserRow({ user, canManageAccess, onAccessUpdated }: { user: AdminUser; canManageAccess: boolean; onAccessUpdated: () => void }) {
  return (
    <tr className="align-top text-slate-700">
      <td className="px-4 py-4">
        <p className="font-semibold text-slate-950">{user.name}</p>
        <p className="mt-1 text-xs text-slate-500">{user.email}</p>
      </td>
      <td className="px-4 py-4">
        <p className="font-medium capitalize text-slate-950">{user.role.replace('_', ' ')}</p>
        <StatusBadge status={user.status} />
      </td>
      <td className="px-4 py-4 font-medium text-slate-950">${user.account?.managed_balance ?? '0.00'}</td>
      <td className="px-4 py-4">${user.account?.pending_balance ?? '0.00'}</td>
      <td className="px-4 py-4">{user.account?.tier?.name ?? 'Unassigned'}</td>
      <td className="px-4 py-4 capitalize">{user.account?.trading_status ?? 'Not configured'}</td>
      <td className="px-4 py-4 whitespace-nowrap">{formatDate(user.created_at)}</td>
      {canManageAccess && <td className="px-4 py-4"><UserAccessControls user={user} onSaved={onAccessUpdated} /></td>}
    </tr>
  )
}

function UserAccessControls({ user, onSaved }: { user: AdminUser; onSaved: () => void }) {
  const [role, setRole] = useState(user.role)
  const [status, setStatus] = useState(user.status)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function save(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const changes: Record<string, string> = {}
    if (role !== user.role) changes.role = role
    if (['admin', 'super_admin'].includes(user.role) && status !== user.status) changes.status = status
    if (Object.keys(changes).length === 0) return

    setSaving(true)
    setError(null)

    try {
      await initializeCsrfCookie()
      await apiRequest(`/api/admin/users/${user.id}/access`, {
        method: 'PATCH',
        body: JSON.stringify(changes),
      })
      onSaved()
    } catch (requestError: unknown) {
      setError(errorMessage(requestError, 'Unable to update user access.'))
    } finally {
      setSaving(false)
    }
  }

  const changed = role !== user.role || (['admin', 'super_admin'].includes(user.role) && status !== user.status)

  return (
    <form onSubmit={(event) => void save(event)} className="min-w-52 space-y-2">
      <select aria-label={`Role for ${user.email}`} value={role} onChange={(event) => setRole(event.target.value)} className="block w-full border border-slate-300 bg-white px-2 py-1.5 text-xs text-slate-900">
        <option value="user">User</option>
        <option value="admin">Admin</option>
        <option value="super_admin">Super admin</option>
      </select>
      {['admin', 'super_admin'].includes(user.role) && (
        <select aria-label={`Admin account status for ${user.email}`} value={status} onChange={(event) => setStatus(event.target.value)} className="block w-full border border-slate-300 bg-white px-2 py-1.5 text-xs text-slate-900">
          <option value="active">Active</option>
          <option value="suspended">Suspended</option>
        </select>
      )}
      <button type="submit" disabled={!changed || saving} className="border border-slate-300 px-2 py-1.5 text-xs font-semibold text-slate-700 disabled:opacity-50">
        {saving ? 'Saving...' : 'Save access'}
      </button>
      {error && <p role="alert" className="max-w-52 text-xs text-rose-700">{error}</p>}
    </form>
  )
}

function StatusBadge({ status }: { status: string }) {
  const style = status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'

  return <span className={`mt-2 inline-block px-2 py-1 text-xs font-semibold capitalize ${style}`}>{status}</span>
}

function Pagination({ meta, onPageChange }: { meta: NonNullable<AdminUsersResponse['meta']>; onPageChange: (page: number) => void }) {
  return (
    <div className="flex flex-wrap items-center justify-between gap-3 px-1 py-4 text-sm text-slate-600">
      <p>{meta.from ?? 0}-{meta.to ?? 0} of {meta.total}</p>
      <div className="flex items-center gap-2">
        <button type="button" disabled={meta.current_page <= 1} onClick={() => onPageChange(meta.current_page - 1)} className="border border-slate-300 px-3 py-2 font-medium disabled:cursor-not-allowed disabled:opacity-40">Previous</button>
        <span className="px-2">Page {meta.current_page} of {meta.last_page}</span>
        <button type="button" disabled={meta.current_page >= meta.last_page} onClick={() => onPageChange(meta.current_page + 1)} className="border border-slate-300 px-3 py-2 font-medium disabled:cursor-not-allowed disabled:opacity-40">Next</button>
      </div>
    </div>
  )
}

function formatDate(value: string): string {
  return new Intl.DateTimeFormat('en', { dateStyle: 'medium' }).format(new Date(value))
}

function errorMessage(error: unknown, fallback: string): string {
  if (error instanceof ApiError && Object.values(error.errors).length > 0) {
    return Object.values(error.errors).flat().join(' ')
  }

  return error instanceof Error ? error.message : fallback
}

export default AdminUsersPage