import { useEffect, useState, type FormEvent } from 'react'
import { useSearchParams } from 'react-router-dom'
import { ApiError, apiRequest } from '../lib/api'
import { useDocumentTitle } from '../hooks/useDocumentTitle'
import type { AdminNotification, AdminNotificationsResponse } from '../types/api'

const pageSize = 15

function AdminNotificationsPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [notifications, setNotifications] = useState<AdminNotification[]>([])
  const [meta, setMeta] = useState<AdminNotificationsResponse['meta'] | null>(null)
  const [searchInput, setSearchInput] = useState(searchParams.get('search') ?? '')
  const [categoryInput, setCategoryInput] = useState(searchParams.get('category') ?? '')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useDocumentTitle('Admin Notifications')

  useEffect(() => {
    let active = true
    const params = new URLSearchParams(searchParams)
    params.set('per_page', String(pageSize))

    void apiRequest<AdminNotificationsResponse>(`/api/admin/notifications?${params.toString()}`)
      .then((response) => {
        if (!active) return
        if (!response || !Array.isArray(response.data) || !isPaginationMeta(response.meta)) {
          setNotifications([])
          setMeta(null)
          setError('The notification response is incomplete.')
          return
        }

        setNotifications(response.data.filter(isAdminNotification))
        setMeta(response.meta)
      })
      .catch((requestError: unknown) => {
        if (!active) return
        setNotifications([])
        setMeta(null)
        setError(errorMessage(requestError))
      })
      .finally(() => {
        if (active) setLoading(false)
      })

    return () => {
      active = false
    }
  }, [searchParams])

  const status = searchParams.get('status') ?? 'all'

  function applySearch(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const next = new URLSearchParams(searchParams)
    const search = searchInput.trim()
    const category = categoryInput.trim()
    if (search) next.set('search', search)
    else next.delete('search')
    if (category) next.set('category', category)
    else next.delete('category')
    next.set('page', '1')
    setLoading(true)
    setError(null)
    setSearchParams(next)
  }

  function updateStatus(value: string) {
    const next = new URLSearchParams(searchParams)
    if (value && value !== 'all') next.set('status', value)
    else next.delete('status')
    next.set('page', '1')
    setLoading(true)
    setError(null)
    setSearchParams(next)
  }

  function clearFilters() {
    setSearchInput('')
    setCategoryInput('')
    setLoading(true)
    setError(null)
    setSearchParams({})
  }

  function goToPage(page: number) {
    const next = new URLSearchParams(searchParams)
    next.set('page', String(page))
    setLoading(true)
    setError(null)
    setSearchParams(next)
  }

  return (
    <section className="mx-auto max-w-7xl px-6 py-10 lg:px-8">
      <header className="mb-8">
        <p className="text-sm font-semibold uppercase tracking-[0.18em] text-cyan-700">Platform activity</p>
        <h1 className="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Notifications</h1>
        <p className="mt-2 max-w-2xl text-sm text-slate-600">
          Read-only history of notifications delivered to user accounts.
        </p>
      </header>

      <form onSubmit={applySearch} className="mb-6 grid gap-3 border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-[minmax(0,2fr)_1fr_1fr_auto_auto]">
        <label className="text-sm text-slate-600">
          Search users and content
          <input
            value={searchInput}
            onChange={(event) => setSearchInput(event.target.value)}
            placeholder="Name, email, title, or message"
            className="mt-1 block w-full border border-slate-300 px-3 py-2 text-sm text-slate-950 outline-none focus:border-cyan-600"
          />
        </label>
        <label className="text-sm text-slate-600">
          Category
          <input
            value={categoryInput}
            onChange={(event) => setCategoryInput(event.target.value)}
            placeholder="All categories"
            className="mt-1 block w-full border border-slate-300 px-3 py-2 text-sm text-slate-950 outline-none focus:border-cyan-600"
          />
        </label>
        <label className="text-sm text-slate-600">
          Read status
          <select
            value={status}
            onChange={(event) => updateStatus(event.target.value)}
            className="mt-1 block w-full border border-slate-300 bg-white px-3 py-2 text-sm text-slate-950 outline-none focus:border-cyan-600"
          >
            <option value="all">All notifications</option>
            <option value="unread">Unread</option>
            <option value="read">Read</option>
          </select>
        </label>
        <button type="submit" className="self-end bg-slate-950 px-4 py-2 text-sm font-semibold text-white">Search</button>
        <button type="button" onClick={clearFilters} className="self-end border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Clear</button>
      </form>

      {loading && (
        <div role="status" aria-label="Loading notifications" className="space-y-3">
          {[0, 1, 2].map((item) => (
            <div key={item} className="animate-pulse border border-slate-200 bg-white p-5">
              <div className="h-4 w-40 rounded bg-slate-200" />
              <div className="mt-3 h-3 w-full max-w-2xl rounded bg-slate-100" />
            </div>
          ))}
          <span className="sr-only">Loading notifications...</span>
        </div>
      )}

      {!loading && error && (
        <div role="alert" className="border border-rose-200 bg-rose-50 p-6 text-sm text-rose-800">{error}</div>
      )}

      {!loading && !error && notifications.length === 0 && (
        <div className="border border-slate-200 bg-white p-10 text-center shadow-sm">
          <h2 className="text-lg font-semibold text-slate-950">No notifications found</h2>
          <p className="mt-2 text-sm text-slate-600">No user notifications match the selected filters.</p>
        </div>
      )}

      {!loading && !error && notifications.length > 0 && (
        <>
          <div className="space-y-3" aria-live="polite">
            {notifications.map((notification) => (
              <NotificationCard key={notification.id} notification={notification} />
            ))}
          </div>
          {meta && meta.last_page > 1 && <Pagination meta={meta} onPageChange={goToPage} />}
        </>
      )}
    </section>
  )
}

function NotificationCard({ notification }: { notification: AdminNotification }) {
  const createdAt = validDate(notification.created_at)
  const readAt = validDate(notification.read_at)

  return (
    <article className="border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0">
          <div className="flex flex-wrap items-center gap-2">
            <span className="bg-slate-100 px-2 py-1 text-xs font-semibold capitalize text-slate-700">{humanize(notification.category)}</span>
            <span className={`px-2 py-1 text-xs font-semibold ${readAt ? 'bg-slate-100 text-slate-600' : 'bg-amber-50 text-amber-700'}`}>
              {readAt ? 'Read' : 'Unread'}
            </span>
          </div>
          <h2 className="mt-3 break-words text-base font-semibold text-slate-950">{notification.title}</h2>
        </div>
        <time className="text-xs text-slate-500" dateTime={createdAt?.toISOString()}>
          {createdAt ? formatDate(createdAt) : 'Time unavailable'}
        </time>
      </div>
      <p className="mt-2 whitespace-pre-wrap break-words text-sm leading-6 text-slate-700">{notification.message}</p>
      <div className="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3 text-xs text-slate-500">
        <p className="min-w-0 break-all">
          {notification.user ? (
            <><span className="font-semibold text-slate-700">{notification.user.name}</span> · {notification.user.email}</>
          ) : 'User unavailable'}
        </p>
        <span>{readAt ? `Read ${formatDate(readAt)}` : 'Not read'}</span>
      </div>
    </article>
  )
}

function Pagination({ meta, onPageChange }: { meta: AdminNotificationsResponse['meta']; onPageChange: (page: number) => void }) {
  return (
    <nav aria-label="Notification pages" className="flex flex-wrap items-center justify-between gap-3 px-1 py-4 text-sm text-slate-600">
      <p>{meta.from ?? 0}-{meta.to ?? 0} of {meta.total}</p>
      <div className="flex items-center gap-2">
        <button type="button" disabled={meta.current_page <= 1} onClick={() => onPageChange(meta.current_page - 1)} className="border border-slate-300 px-3 py-2 font-medium disabled:opacity-40">Previous</button>
        <span aria-live="polite" className="px-2">Page {meta.current_page} of {meta.last_page}</span>
        <button type="button" disabled={meta.current_page >= meta.last_page} onClick={() => onPageChange(meta.current_page + 1)} className="border border-slate-300 px-3 py-2 font-medium disabled:opacity-40">Next</button>
      </div>
    </nav>
  )
}

function isAdminNotification(value: unknown): value is AdminNotification {
  return Boolean(value && typeof value === 'object' && Number.isInteger((value as AdminNotification).id))
}

function isPaginationMeta(value: unknown): value is AdminNotificationsResponse['meta'] {
  if (!value || typeof value !== 'object') return false
  const meta = value as AdminNotificationsResponse['meta']
  return Number.isInteger(meta.current_page) && meta.current_page > 0
    && Number.isInteger(meta.last_page) && meta.last_page > 0
    && Number.isInteger(meta.per_page) && meta.per_page > 0
    && Number.isInteger(meta.total) && meta.total >= 0
    && (meta.from === null || Number.isInteger(meta.from))
    && (meta.to === null || Number.isInteger(meta.to))
}

function validDate(value: string | null): Date | null {
  if (!value) return null
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? null : date
}

function formatDate(value: Date): string {
  return new Intl.DateTimeFormat('en', { dateStyle: 'medium', timeStyle: 'short' }).format(value)
}

function humanize(value: string): string {
  return value.replace(/[_-]+/g, ' ').replace(/\b\w/g, (character) => character.toUpperCase())
}

function errorMessage(error: unknown): string {
  if (error instanceof ApiError && error.status === 401) return 'Your session has expired. Please sign in again.'
  if (error instanceof ApiError && error.status === 403) return 'You are not authorized to view platform notifications.'
  return error instanceof Error ? error.message : 'Unable to load platform notifications.'
}

export default AdminNotificationsPage
