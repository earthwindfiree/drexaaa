import { useEffect, useState } from 'react'
import { ApiError, apiRequest, initializeCsrfCookie } from '../lib/api'
import { useDocumentTitle } from '../hooks/useDocumentTitle'
import type {
  UserNotification,
  UserNotificationsListResponse,
  UserNotificationsMarkAllResponse,
} from '../types/api'

const pageSize = 15

function UserNotificationsPage() {
  const [notifications, setNotifications] = useState<UserNotification[]>([])
  const [unreadCount, setUnreadCount] = useState<number | null>(null)
  const [meta, setMeta] = useState<UserNotificationsListResponse['meta'] | null>(null)
  const [statusFilter, setStatusFilter] = useState('all')
  const [page, setPage] = useState(1)
  const [refreshKey, setRefreshKey] = useState(0)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [actionError, setActionError] = useState<string | null>(null)
  const [actionNotice, setActionNotice] = useState<string | null>(null)
  const [busyNotificationId, setBusyNotificationId] = useState<number | null>(null)
  const [markingAll, setMarkingAll] = useState(false)

  useDocumentTitle('Notifications')

  useEffect(() => {
    let active = true
    const params = new URLSearchParams({ status: statusFilter, page: String(page), per_page: String(pageSize) })

    void apiRequest<UserNotificationsListResponse>(`/api/notifications?${params.toString()}`)
      .then((response) => {
        if (!active) return
        if (!response || !Array.isArray(response.data)) {
          setNotifications([])
          setUnreadCount(null)
          setMeta(null)
          setError('The notification response is incomplete.')
          return
        }

        setNotifications(response.data.filter(isNotification))
        setUnreadCount(validUnreadCount(response.unread_count))
        setMeta(isPaginationMeta(response.meta) ? response.meta : null)
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
  }, [statusFilter, page, refreshKey])

  function changeStatusFilter(value: string) {
    setError(null)
    setActionError(null)
    setActionNotice(null)
    setLoading(true)
    setPage(1)
    setStatusFilter(value)
  }

  function changePage(nextPage: number) {
    setError(null)
    setActionError(null)
    setActionNotice(null)
    setLoading(true)
    setPage(nextPage)
  }

  async function markNotificationRead(notificationId: number) {
    setBusyNotificationId(notificationId)
    setActionError(null)
    setActionNotice(null)

    try {
      await initializeCsrfCookie()
      await apiRequest(`/api/notifications/${notificationId}/read`, { method: 'PATCH' })
      setActionNotice('Notification marked as read.')
      setPage(1)
      setLoading(true)
      setRefreshKey((current) => current + 1)
    } catch (requestError: unknown) {
      setActionError(actionErrorMessage(requestError, 'Unable to mark this notification as read.'))
    } finally {
      setBusyNotificationId(null)
    }
  }

  async function markAllRead() {
    setMarkingAll(true)
    setActionError(null)
    setActionNotice(null)

    try {
      await initializeCsrfCookie()
      const response = await apiRequest<UserNotificationsMarkAllResponse>('/api/notifications/read-all', { method: 'PATCH' })
      const updated = Number.isInteger(response?.data?.updated) ? response.data.updated : null
      setActionNotice(updated === null
        ? 'Unread notifications marked as read.'
        : `${updated} ${updated === 1 ? 'notification' : 'notifications'} marked as read.`)
      setPage(1)
      setLoading(true)
      setRefreshKey((current) => current + 1)
    } catch (requestError: unknown) {
      setActionError(actionErrorMessage(requestError, 'Unable to mark notifications as read.'))
    } finally {
      setMarkingAll(false)
    }
  }

  const hasNotifications = notifications.length > 0
  const isUnreadView = statusFilter === 'unread'

  return (
    <section className="space-y-6 pb-10 md:space-y-8">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="mb-2 text-[10px] font-semibold uppercase tracking-[0.2em] text-cyan-200/70">Account updates</p>
          <h1 className="font-display text-2xl text-white sm:text-[30px]">Notifications</h1>
          <p className="mt-2 max-w-xl text-sm text-slate-400">Persistent updates about account activity, reviews, and security.</p>
        </div>
        <span className="demo-disclosure">
          <span className="demo-disclosure-mark" aria-hidden="true" />
          Account notifications
        </span>
      </div>

      <div className="notifications-toolbar dashboard-surface">
        <div className="notifications-summary">
          <span className="notifications-summary-mark" aria-hidden="true"><NotificationIcon /></span>
          <div>
            <p className="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Inbox</p>
            <p className="mt-1 text-sm font-medium text-slate-200">
              {unreadCount === null ? 'Unread count unavailable' : `${unreadCount} unread ${unreadCount === 1 ? 'notification' : 'notifications'}`}
            </p>
          </div>
        </div>
        <div className="notifications-toolbar-actions">
          <label className="notification-filter">
            <span>Show</span>
            <select value={statusFilter} onChange={(event) => changeStatusFilter(event.target.value)}>
              <option value="all">All notifications</option>
              <option value="unread">Unread</option>
              <option value="read">Read</option>
            </select>
          </label>
          <button
            type="button"
            className="notification-mark-all"
            onClick={() => void markAllRead()}
            disabled={markingAll || loading || unreadCount === null || unreadCount === 0}
          >
            {markingAll ? 'Marking…' : 'Mark all as read'}
          </button>
        </div>
      </div>

      {actionNotice && <p className="notification-action-notice" role="status">{actionNotice}</p>}
      {actionError && <p className="notification-action-error" role="alert">{actionError}</p>}

      {loading && <NotificationsLoading />}

      {!loading && error && (
        <div role="alert" className="dashboard-message dashboard-error">
          <p className="text-sm font-medium text-rose-100">Notifications unavailable</p>
          <p className="mt-1 text-sm text-rose-200/70">{error}</p>
        </div>
      )}

      {!loading && !error && !hasNotifications && (
        <div className="notifications-empty dashboard-surface">
          <span className="notifications-empty-mark" aria-hidden="true"><NotificationIcon /></span>
          <p className="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">
            {statusFilter === 'unread' ? 'All caught up' : statusFilter === 'read' ? 'No read notifications' : 'Inbox clear'}
          </p>
          <h2 className="mt-2 font-display text-xl text-white">
            {statusFilter === 'unread' ? 'You have no unread notifications.' : statusFilter === 'read' ? 'No notifications have been marked as read yet.' : 'No notifications have been recorded yet.'}
          </h2>
          <p className="mt-2 max-w-lg text-xs leading-5 text-slate-400">Account updates will appear here when they are recorded.</p>
        </div>
      )}

      {!loading && !error && hasNotifications && (
        <>
          <div className="notifications-list" aria-live="polite">
            {notifications.map((notification) => (
              <NotificationCard
                key={notification.id}
                notification={notification}
                busy={busyNotificationId === notification.id}
                onMarkRead={() => void markNotificationRead(notification.id)}
              />
            ))}
          </div>
          {meta && meta.last_page > 1 && (
            <NotificationsPagination meta={meta} page={page} onPageChange={changePage} />
          )}
        </>
      )}
      {isUnreadView && !loading && unreadCount === 0 && hasNotifications && (
        <p className="sr-only">No unread notifications remain on this page.</p>
      )}
    </section>
  )
}

function NotificationCard({
  notification,
  busy,
  onMarkRead,
}: {
  notification: UserNotification
  busy: boolean
  onMarkRead: () => void
}) {
  const unread = notification.read_at === null
  const title = safeText(notification.title, 'Notification')
  const message = safeText(notification.message, 'Notification details unavailable.')
  const category = safeText(notification.category, 'Category unavailable')
  const createdAt = validDate(notification.created_at)

  return (
    <article className={`notification-card dashboard-surface${unread ? ' is-unread' : ''}`}>
      <span className="notification-unread-mark" aria-hidden="true" />
      <div className="notification-card-content">
        <div className="notification-card-heading">
          <div className="min-w-0">
            <div className="notification-card-labels">
              <span className="notification-category">{humanize(category)}</span>
              {unread && <span className="notification-unread-label">Unread</span>}
            </div>
            <h2 className="notification-title">{title}</h2>
          </div>
          <time className="notification-time" dateTime={createdAt?.toISOString()}>
            {createdAt ? formatDate(createdAt) : 'Time unavailable'}
          </time>
        </div>
        <p className="notification-message">{message}</p>
        {unread && (
          <button
            type="button"
            className="notification-mark-read"
            onClick={onMarkRead}
            disabled={busy}
          >
            {busy ? 'Marking…' : 'Mark as read'}
          </button>
        )}
      </div>
    </article>
  )
}

function NotificationsPagination({
  meta,
  page,
  onPageChange,
}: {
  meta: UserNotificationsListResponse['meta']
  page: number
  onPageChange: (page: number) => void
}) {
  return (
    <nav className="notification-pagination" aria-label="Notification pages">
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

function NotificationsLoading() {
  return (
    <div className="notifications-loading dashboard-surface" role="status" aria-label="Loading notifications">
      <div />
      <div />
      <div />
      <span className="sr-only">Loading notifications</span>
    </div>
  )
}

function NotificationIcon() {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" />
    </svg>
  )
}

function isNotification(value: unknown): value is UserNotification {
  return Boolean(value && typeof value === 'object' && Number.isInteger((value as UserNotification).id))
}

function validUnreadCount(value: unknown): number | null {
  return Number.isInteger(value) && Number(value) >= 0 ? Number(value) : null
}

function isPaginationMeta(value: unknown): value is UserNotificationsListResponse['meta'] {
  if (!value || typeof value !== 'object') return false
  const meta = value as UserNotificationsListResponse['meta']
  return Number.isInteger(meta.current_page) && meta.current_page > 0
    && Number.isInteger(meta.last_page) && meta.last_page > 0
    && Number.isInteger(meta.per_page) && meta.per_page > 0
    && Number.isInteger(meta.total) && meta.total >= 0
    && (meta.from === null || Number.isInteger(meta.from))
    && (meta.to === null || Number.isInteger(meta.to))
}

function safeText(value: unknown, fallback: string): string {
  return typeof value === 'string' && value.trim() ? value : fallback
}

function humanize(value: string): string {
  return value.replace(/[_-]+/g, ' ').replace(/\b\w/g, (character) => character.toUpperCase())
}

function validDate(value: unknown): Date | null {
  if (typeof value !== 'string' || !value) return null
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? null : date
}

function formatDate(value: Date): string {
  return new Intl.DateTimeFormat('en-US', { dateStyle: 'medium', timeStyle: 'short' }).format(value)
}

function errorMessage(error: unknown): string {
  if (error instanceof ApiError && error.status === 401) return 'Your session has expired. Please sign in again.'
  if (error instanceof ApiError && error.status === 403) return 'You are not authorized to view notifications.'
  return error instanceof Error ? error.message : 'Unable to load notifications.'
}

function actionErrorMessage(error: unknown, fallback: string): string {
  if (error instanceof ApiError && Object.values(error.errors).length > 0) {
    return Object.values(error.errors).flat().join(' ')
  }
  if (error instanceof ApiError && error.status === 401) return 'Your session has expired. Please sign in again.'
  if (error instanceof ApiError && error.status === 403) return 'You are not authorized to update these notifications.'
  if (error instanceof ApiError && error.status === 404) return 'This notification is no longer available. Refresh the list and try again.'
  return error instanceof Error ? error.message : fallback
}

export default UserNotificationsPage