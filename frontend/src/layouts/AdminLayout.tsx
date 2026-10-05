import { useState } from 'react'
import { Link, NavLink, Outlet, useNavigate } from 'react-router-dom'
import { useAuth } from '../features/auth/AuthContext'

const adminNavigation = [
  { label: 'Dashboard', to: '/admin' },
  { label: 'Users', to: '/admin/users' },
  { label: 'Accounts', to: '/admin/accounts' },
  { label: 'Markets', to: '/admin/markets' },
  { label: 'Deposits', to: '/admin/deposits' },
  { label: 'Withdrawals', to: '/admin/withdrawals' },
  { label: 'Transactions', to: '/admin/transactions' },
  { label: 'Notifications', to: '/admin/notifications' },
  { label: 'Audit Logs', to: '/admin/audit-logs' },
  { label: 'Tiers', to: '/admin/tiers' },
  { label: 'Settings', to: '/admin/settings' },
]

function AdminLayout() {
  const { logout } = useAuth()
  const navigate = useNavigate()
  const [loggingOut, setLoggingOut] = useState(false)
  const [logoutError, setLogoutError] = useState<string | null>(null)

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

  return (
    <div className="min-h-screen bg-slate-100 text-slate-950">
      <header className="border-b border-slate-200 bg-white">
        <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-6 py-5 lg:px-8">
          <div>
            <Link to="/" className="text-lg font-semibold tracking-tight text-slate-950">
              Mercury Managed
            </Link>
            <p className="mt-1 text-xs font-medium uppercase tracking-[0.18em] text-cyan-700">Operations console</p>
          </div>
          <nav aria-label="Admin navigation" className="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-slate-600">
            {adminNavigation.map((item) => (
              <NavLink
                key={item.to}
                to={item.to}
                end={item.to === '/admin'}
                className={({ isActive }) => isActive ? 'font-semibold text-cyan-700' : 'transition hover:text-slate-950'}
              >
                {item.label}
              </NavLink>
            ))}
            <button
              type="button"
              onClick={() => void handleLogout()}
              disabled={loggingOut}
              className="font-medium text-slate-600 transition hover:text-slate-950 disabled:opacity-60"
            >
              {loggingOut ? 'Signing out...' : 'Log out'}
            </button>
          </nav>
        </div>
      </header>
      {logoutError && <p role="alert" className="mx-auto mt-4 w-full max-w-7xl px-6 text-sm text-rose-700">{logoutError}</p>}
      <main>
        <Outlet />
      </main>
    </div>
  )
}

export default AdminLayout