import { useEffect, useRef, useState } from 'react'
import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom'
import { apiRequest } from '../lib/api'
import { useAuth } from '../features/auth/AuthContext'
import type { UserNotificationsResponse } from '../types/api'
import type { AuthUser } from '../types/auth'

const guestNavigation = [
  { label: 'Home', to: '/' },
  { label: 'Login', to: '/login' },
  { label: 'Register', to: '/register' },
]

const userNavigation = [
  { label: 'Overview', to: '/dashboard', icon: 'overview' },
  { label: 'Portfolio', to: '/portfolio', icon: 'portfolio' },
  { label: 'Markets', to: '/markets', icon: 'markets' },
  { label: 'Wallet', to: '/wallet', icon: 'wallet' },
  { label: 'Transactions', to: '/transactions', icon: 'transactions' },
  { label: 'Notifications', to: '/notifications', icon: 'notifications' },
  { label: 'Settings', to: '/settings', icon: 'settings' },
]

function AppLayout() {
  const { user, logout } = useAuth()
  const location = useLocation()
  const navigate = useNavigate()
  const [logoutError, setLogoutError] = useState<string | null>(null)
  const [loggingOut, setLoggingOut] = useState(false)
  const memberShell = Boolean(user && !['admin', 'super_admin'].includes(user.role) && location.pathname !== '/verify-email')

  async function handleLogout() {
    setLoggingOut(true)
    setLogoutError(null)

    try {
      await logout()
      navigate('/login', { replace: true })
    } catch (error) {
      setLogoutError(error instanceof Error ? error.message : 'Unable to log out.')
    } finally {
      setLoggingOut(false)
    }
  }

  if (memberShell && user) {
    return (
      <MemberLayout
        user={user}
        logoutError={logoutError}
        loggingOut={loggingOut}
        onLogout={() => void handleLogout()}
      />
    )
  }

  const navigation = user
    ? user.role === 'admin' || user.role === 'super_admin'
      ? [{ label: 'Home', to: '/' }, { label: 'Dashboard', to: '/dashboard' }, { label: 'Admin', to: '/admin' }]
      : [{ label: 'Home', to: '/' }, { label: 'Dashboard', to: '/dashboard' }]
    : guestNavigation

  return (
    <div className="public-shell flex flex-col text-[var(--theme-body)]">
      <header className="border-b border-[var(--theme-border)] bg-[rgba(10,14,17,0.56)] backdrop-blur-sm">
        <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-6 py-5 lg:px-8">
          <Link to="/" className="text-lg font-semibold tracking-tight text-[var(--theme-heading)]">
            Mercury Managed
          </Link>
          <nav aria-label="Application navigation" className="flex flex-wrap gap-x-5 gap-y-2 text-sm text-[var(--theme-body)]">
            {navigation.map((item) => (
              <Link key={item.to} to={item.to} className="transition hover:text-[var(--theme-heading)]">
                {item.label}
              </Link>
            ))}
            {user && (
              <button
                type="button"
                onClick={() => void handleLogout()}
                disabled={loggingOut}
                className="text-[var(--theme-body)] transition hover:text-[var(--theme-heading)] disabled:opacity-60"
              >
                {loggingOut ? 'Signing out…' : 'Log out'}
              </button>
            )}
          </nav>
        </div>
      </header>
      {logoutError && <p role="alert" className="mx-auto mt-4 w-full max-w-7xl px-6 text-sm text-[var(--theme-danger)]">{logoutError}</p>}
      <main className="flex-1">
        <Outlet />
      </main>
      <footer className="border-t border-[var(--theme-border)] px-6 py-5 text-center text-xs text-[var(--theme-muted)]">
        <nav aria-label="Legal and contact" className="mb-3 flex flex-wrap justify-center gap-x-5 gap-y-2">
          <Link to="/contact" className="transition hover:text-[var(--theme-heading)]">Contact</Link>
          <Link to="/terms" className="transition hover:text-[var(--theme-heading)]">Terms</Link>
          <Link to="/privacy" className="transition hover:text-[var(--theme-heading)]">Privacy</Link>
        </nav>
        <p>Demo Account · Simulated Performance</p>
      </footer>
    </div>
  )
}

function MemberLayout({
  user,
  logoutError,
  loggingOut,
  onLogout,
}: {
  user: AuthUser
  logoutError: string | null
  loggingOut: boolean
  onLogout: () => void
}) {
  const [mobileNavOpen, setMobileNavOpen] = useState(false)
  const [sidebarCollapsed, setSidebarCollapsed] = useState(false)
  const [unreadCount, setUnreadCount] = useState<number | null>(null)
  const mobileMenuTriggerRef = useRef<HTMLButtonElement>(null)
  const mobileDrawerCloseRef = useRef<HTMLButtonElement>(null)
  const mobileDrawerRef = useRef<HTMLElement>(null)

  function closeMobileNavigation() {
    setMobileNavOpen(false)
    mobileMenuTriggerRef.current?.focus()
  }

  useEffect(() => {
    if (!mobileNavOpen) return

    function closeOnEscape(event: KeyboardEvent) {
      if (event.key === 'Escape') {
        closeMobileNavigation()
        return
      }

      if (event.key !== 'Tab') return
      const focusableItems = mobileDrawerRef.current?.querySelectorAll<HTMLElement>('a[href], button:not([disabled])')
      if (!focusableItems?.length) return

      const firstItem = focusableItems[0]
      const lastItem = focusableItems[focusableItems.length - 1]
      if (event.shiftKey && document.activeElement === firstItem) {
        event.preventDefault()
        lastItem?.focus()
      } else if (!event.shiftKey && document.activeElement === lastItem) {
        event.preventDefault()
        firstItem?.focus()
      }
    }

    window.addEventListener('keydown', closeOnEscape)
    mobileDrawerCloseRef.current?.focus()

    return () => window.removeEventListener('keydown', closeOnEscape)
  }, [mobileNavOpen])

  useEffect(() => {
    let active = true

    void apiRequest<UserNotificationsResponse>('/api/notifications?per_page=1')
      .then((response) => {
        if (active && Number.isFinite(response.unread_count)) setUnreadCount(response.unread_count)
      })
      .catch(() => {
        if (active) setUnreadCount(null)
      })

    return () => {
      active = false
    }
  }, [])

  const initials = user.name
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map((part) => part[0])
    .join('')
    .toUpperCase()

  const greetingName = user.name.trim().split(/\s+/)[0]

  return (
    <div className={`managed-shell min-h-screen text-slate-100${sidebarCollapsed ? ' is-sidebar-collapsed' : ''}`}>
      <div className="managed-ambient" aria-hidden="true" />

      <aside className={`managed-sidebar fixed inset-y-0 left-0 z-30 flex-col border-r border-white/[0.08] py-6${sidebarCollapsed ? ' is-collapsed' : ''}`}>
        <div className="managed-sidebar-header">
          <Link to="/dashboard" className="managed-brand-link" aria-label="Mercury Managed overview" title={sidebarCollapsed ? 'Mercury Managed' : undefined}>
            <span className="managed-brand-mark" aria-hidden="true">M</span>
            <span className="managed-brand-copy">
              <span className="block text-[15px] font-semibold text-white">Mercury Managed</span>
              <span className="mt-0.5 block text-[9px] font-medium uppercase tracking-[0.2em] text-slate-500">Account workspace</span>
            </span>
          </Link>
          <button
            type="button"
            className="managed-sidebar-toggle"
            aria-label={sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'}
            aria-expanded={!sidebarCollapsed}
            title={sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'}
            onClick={() => setSidebarCollapsed((collapsed) => !collapsed)}
          >
            <SidebarToggleIcon collapsed={sidebarCollapsed} />
          </button>
        </div>

        <p className="managed-section-label">Workspace</p>
        <NavigationLinks unreadCount={unreadCount} collapsed={sidebarCollapsed} />

        <div className="managed-sidebar-footer">
          <div className="managed-demo-note" title={sidebarCollapsed ? 'Demo account · Simulated performance' : undefined}>
            <span className="managed-demo-copy">
              <span className="block text-[10px] font-semibold uppercase tracking-[0.13em] text-cyan-100/80">Demo account</span>
              <span className="mt-1 block text-xs text-slate-400">Simulated performance</span>
            </span>
            <span className="managed-demo-mark" aria-hidden="true">D</span>
          </div>
          <ProfileLink user={user} initials={initials} compact={sidebarCollapsed} />
          <button
            type="button"
            onClick={onLogout}
            disabled={loggingOut}
            className="managed-signout"
            aria-label={loggingOut ? 'Signing out' : 'Sign out'}
            title={sidebarCollapsed ? 'Sign out' : undefined}
          >
            <SignOutIcon />
            <span>{loggingOut ? 'Signing out…' : 'Sign out'}</span>
          </button>
        </div>
      </aside>

      <div className="managed-workspace">
        <header className="managed-mobile-bar">
          <div className="managed-mobile-brand-group">
            <button
              ref={mobileMenuTriggerRef}
              type="button"
              className="managed-menu-button"
              aria-expanded={mobileNavOpen}
              aria-controls="mobile-user-navigation"
              aria-label={mobileNavOpen ? 'Close navigation' : 'Open navigation'}
              onClick={() => setMobileNavOpen((open) => !open)}
            >
              <MenuIcon open={mobileNavOpen} />
            </button>
            <Link to="/dashboard" className="flex items-center gap-2.5" aria-label="Mercury Managed overview">
              <span className="managed-brand-mark managed-brand-mark-small" aria-hidden="true">M</span>
              <span className="text-sm font-semibold text-white">Mercury Managed</span>
            </Link>
          </div>
          <div className="managed-mobile-actions">
            <Link
              to="/notifications"
              className="managed-icon-link relative"
              aria-label={unreadCount ? `Notifications, ${unreadCount} unread` : 'Notifications'}
              title="Notifications"
            >
              <BellIcon />
              {unreadCount !== null && unreadCount > 0 && <span className="managed-notification-dot" aria-hidden="true" />}
            </Link>
            <ProfileLink user={user} initials={initials} compact />
          </div>
        </header>

        {mobileNavOpen && (
          <div className="managed-mobile-drawer-layer">
            <button
              type="button"
              className="managed-mobile-backdrop"
              aria-label="Close navigation"
              onClick={() => {
                setMobileNavOpen(false)
                mobileMenuTriggerRef.current?.focus()
              }}
            />
            <aside
              ref={mobileDrawerRef}
              id="mobile-user-navigation"
              className="managed-mobile-drawer"
              role="dialog"
              aria-modal="true"
              aria-label="User navigation"
            >
              <div className="managed-mobile-drawer-header">
                <span className="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">Navigation</span>
                <button
                  ref={mobileDrawerCloseRef}
                  type="button"
                  className="managed-drawer-close"
                  aria-label="Close navigation"
                  onClick={closeMobileNavigation}
                >
                  <MenuIcon open />
                </button>
              </div>
              <NavigationLinks
                unreadCount={unreadCount}
                onNavigate={closeMobileNavigation}
              />
              <ProfileLink user={user} initials={initials} onNavigate={closeMobileNavigation} />
              <button type="button" onClick={onLogout} disabled={loggingOut} className="managed-mobile-signout">
                <SignOutIcon />
                {loggingOut ? 'Signing out…' : 'Sign out'}
              </button>
            </aside>
          </div>
        )}

        <header className="managed-topbar">
          <div>
            <p className="text-sm font-medium text-white">
              {greetingName ? `Welcome back, ${greetingName}` : 'Welcome back'}
            </p>
            <p className="mt-1 text-xs text-slate-500">Your simulated managed account overview</p>
          </div>
          <div className="managed-topbar-actions flex items-center gap-3 sm:gap-5">
            <Link
              to="/notifications"
              className="managed-icon-link relative"
              aria-label={unreadCount ? `Notifications, ${unreadCount} unread` : 'Notifications'}
              title="Notifications"
            >
              <BellIcon />
              {unreadCount !== null && unreadCount > 0 && <span className="managed-notification-dot" aria-hidden="true" />}
            </Link>
            <ProfileLink user={user} initials={initials} compact />
          </div>
        </header>

        {logoutError && <p role="alert" className="mx-5 mt-4 text-sm text-rose-300 sm:mx-8 lg:mx-12">{logoutError}</p>}
        <main className="managed-content">
          <Outlet />
        </main>
      </div>
    </div>
  )
}

function NavigationLinks({
  unreadCount,
  collapsed = false,
  onNavigate,
}: {
  unreadCount: number | null
  collapsed?: boolean
  onNavigate?: () => void
}) {
  return (
    <nav aria-label="User navigation" className={`managed-nav${collapsed ? ' is-collapsed' : ''}`}>
      {userNavigation.map((item) => (
        <NavLink
          key={item.to}
          to={item.to}
          end
          onClick={onNavigate}
          aria-label={item.to === '/notifications' && unreadCount !== null && unreadCount > 0
            ? `${item.label}, ${unreadCount} unread`
            : item.label}
          title={collapsed ? item.label : undefined}
          data-tooltip={item.label}
          className={({ isActive }) => `managed-nav-link${isActive ? ' is-active' : ''}`}
        >
          <NavigationIcon name={item.icon} />
          <span className="managed-nav-label">{item.label}</span>
          {item.to === '/notifications' && unreadCount !== null && unreadCount > 0 && (
            <span className="managed-nav-count" aria-label={`${unreadCount} unread notifications`}>
              {unreadCount > 99 ? '99+' : unreadCount}
            </span>
          )}
        </NavLink>
      ))}
    </nav>
  )
}

function NavigationIcon({ name }: { name: (typeof userNavigation)[number]['icon'] }) {
  return (
    <svg className="managed-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      {name === 'overview' && <><rect x="4" y="4" width="6" height="6" rx="1" /><rect x="14" y="4" width="6" height="6" rx="1" /><rect x="4" y="14" width="6" height="6" rx="1" /><rect x="14" y="14" width="6" height="6" rx="1" /></>}
      {name === 'portfolio' && <><path d="M4 19V5" /><path d="M4 19h16" /><path d="m7 15 4-4 3 2 5-6" /><path d="M16 7h3v3" /></>}
      {name === 'markets' && <><circle cx="12" cy="12" r="8" /><path d="M4 12h16M12 4a12 12 0 0 1 0 16M12 4a12 12 0 0 0 0 16" /></>}
      {name === 'wallet' && <><rect x="4" y="6" width="16" height="13" rx="2" /><path d="M4 9h14a2 2 0 0 1 2 2v4h-5a2 2 0 0 1 0-4h5" /><path d="M16 13h.01" /></>}
      {name === 'transactions' && <><path d="M5 7h14M5 17h14" /><path d="m15 4 4 3-4 3M9 14l-4 3 4 3" /></>}
      {name === 'notifications' && <><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" /></>}
      {name === 'settings' && <><circle cx="12" cy="12" r="3" /><path d="m19.4 15 .1.1 1.2 1-1.3 2.2-1.5-.6a8 8 0 0 1-1.6.9l-.3 1.6h-2.6l-.3-1.6a8 8 0 0 1-1.6-.9l-1.5.6-1.3-2.2 1.2-1a7 7 0 0 1 0-1.9l-1.2-1 1.3-2.2 1.5.6a8 8 0 0 1 1.6-.9l.3-1.6h2.6l.3 1.6a8 8 0 0 1 1.6.9l1.5-.6 1.3 2.2-1.2 1a7 7 0 0 1 0 1.9Z" transform="translate(-1 -1) scale(1.08)" /></>}
    </svg>
  )
}

function SidebarToggleIcon({ collapsed }: { collapsed: boolean }) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <rect x="3.5" y="4" width="17" height="16" rx="2" />
      <path d="M9 4v16M14 9l-3 3 3 3" transform={collapsed ? 'translate(24 0) scale(-1 1)' : undefined} />
    </svg>
  )
}

function MenuIcon({ open }: { open: boolean }) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" aria-hidden="true">
      {open ? <path d="m6 6 12 12M18 6 6 18" /> : <path d="M4 7h16M4 12h16M4 17h16" />}
    </svg>
  )
}

function SignOutIcon() {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <path d="M10 5H5v14h5M14 8l4 4-4 4M8 12h10" />
    </svg>
  )
}

function ProfileLink({
  user,
  initials,
  compact = false,
  onNavigate,
}: {
  user: AuthUser
  initials: string
  compact?: boolean
  onNavigate?: () => void
}) {
  return (
    <Link
      to="/settings"
      onClick={onNavigate}
      className={`managed-profile${compact ? ' managed-profile-compact' : ''}`}
      aria-label={`${user.name || 'Account'} settings`}
      title={compact ? `${user.name || 'Account'} settings` : undefined}
    >
      <span className="managed-avatar" aria-hidden="true">{initials || 'U'}</span>
      {!compact && (
        <span className="min-w-0 flex-1">
          <span className="block truncate text-xs font-medium text-slate-200">{user.name || 'Account'}</span>
          <span className="mt-0.5 block truncate text-[10px] text-slate-500">{user.email}</span>
        </span>
      )}
      {compact && <span className="managed-profile-name">Account</span>}
    </Link>
  )
}

function BellIcon() {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" aria-hidden="true">
      <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  )
}

export default AppLayout