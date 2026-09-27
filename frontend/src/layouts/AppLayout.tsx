import { useState } from 'react'
import { Link, Outlet, useNavigate } from 'react-router-dom'
import { useAuth } from '../features/auth/AuthContext'

const guestNavigation = [
  { label: 'Home', to: '/' },
  { label: 'Login', to: '/login' },
  { label: 'Register', to: '/register' },
]

const userNavigation = [
  { label: 'Home', to: '/' },
  { label: 'Dashboard', to: '/dashboard' },
  { label: 'Admin', to: '/admin' },
]

function AppLayout() {
  const { user, logout } = useAuth()
  const navigate = useNavigate()
  const [logoutError, setLogoutError] = useState<string | null>(null)
  const [loggingOut, setLoggingOut] = useState(false)
  const navigation = user
    ? user.role === 'admin' || user.role === 'super_admin'
      ? userNavigation
      : userNavigation.filter((item) => item.to !== '/admin')
    : guestNavigation

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
    <div className="flex min-h-screen flex-col bg-slate-950 text-slate-100">
      <header className="border-b border-slate-800">
        <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-6 py-5 lg:px-8">
          <Link to="/" className="text-lg font-semibold tracking-tight text-white">
            Mercury Managed
          </Link>
          <nav aria-label="Application navigation" className="flex flex-wrap gap-x-5 gap-y-2 text-sm text-slate-300">
            {navigation.map((item) => (
              <Link key={item.to} to={item.to} className="transition hover:text-white">
                {item.label}
              </Link>
            ))}
            {user && (
              <button
                type="button"
                onClick={() => void handleLogout()}
                disabled={loggingOut}
                className="text-slate-300 transition hover:text-white disabled:opacity-60"
              >
                {loggingOut ? 'Signing out…' : 'Log out'}
              </button>
            )}
          </nav>
        </div>
      </header>
      {logoutError && <p role="alert" className="mx-auto mt-4 w-full max-w-7xl px-6 text-sm text-rose-300">{logoutError}</p>}
      <main className="flex-1">
        <Outlet />
      </main>
      <footer className="border-t border-slate-800 px-6 py-5 text-center text-xs text-slate-400">
        Demo Account · Simulated Performance
      </footer>
    </div>
  )
}

export default AppLayout