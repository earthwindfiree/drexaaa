import { useState } from 'react'
import { Navigate, Outlet, useLocation, useNavigate } from 'react-router-dom'
import { useAuth } from './AuthContext'

function ProtectedRoute() {
  const { user, loading, error, refreshUser, logout } = useAuth()
  const navigate = useNavigate()
  const [loggingOut, setLoggingOut] = useState(false)
  const [logoutError, setLogoutError] = useState<string | null>(null)

  if (loading) {
    return <p className="p-8 text-center text-sm text-slate-300">Checking your session…</p>
  }

  if (error) {
    return (
      <main className="mx-auto max-w-xl px-6 py-16 text-center text-slate-100">
        <p role="alert" className="text-sm text-rose-300">Unable to verify your session: {error}</p>
        <button
          type="button"
          onClick={() => void refreshUser().catch(() => undefined)}
          className="mt-5 rounded-lg border border-slate-700 px-4 py-2 text-sm hover:border-slate-500"
        >
          Retry
        </button>
      </main>
    )
  }

  if (!user) return <Navigate to="/login" replace />

  if (user.status === 'suspended') {
    async function handleLogout() {
      setLoggingOut(true)
      setLogoutError(null)

      try {
        await logout()
        navigate('/login', { replace: true })
      } catch (requestError) {
        setLogoutError(requestError instanceof Error ? requestError.message : 'Unable to log out.')
      } finally {
        setLoggingOut(false)
      }
    }

    return (
      <main className="mx-auto max-w-xl px-6 py-16 text-center text-slate-100">
        <h1 className="text-xl font-semibold">Account suspended</h1>
        <p className="mt-2 text-sm text-slate-400">This account cannot access the platform while suspended.</p>
        {logoutError && <p role="alert" className="mt-4 text-sm text-rose-300">{logoutError}</p>}
        <button type="button" onClick={() => void handleLogout()} disabled={loggingOut} className="mt-5 rounded-lg border border-slate-700 px-4 py-2 text-sm hover:border-slate-500 disabled:opacity-60">
          {loggingOut ? 'Signing out...' : 'Log out'}
        </button>
      </main>
    )
  }

  return <Outlet />
}

function AdminRoute() {
  const { user } = useAuth()

  if (!user || user.status !== 'active' || !['admin', 'super_admin'].includes(user.role)) {
    return <Navigate to="/dashboard" replace />
  }

  return <Outlet />
}

function VerifiedUserRoute() {
  const { user } = useAuth()

  if (user && !user.email_verified_at) {
    return <Navigate to="/verify-email" replace />
  }

  return <Outlet />
}

function EmailVerificationRoute() {
  const { user } = useAuth()

  if (user?.email_verified_at) {
    return <Navigate to="/dashboard" replace />
  }

  return <Outlet />
}

function GuestRoute() {
  const { user, loading } = useAuth()
  const location = useLocation()

  if (loading) {
    return <p className="p-8 text-center text-sm text-slate-300">Checking your session…</p>
  }

  if (user) return <Navigate to="/dashboard" replace state={{ from: location }} />

  return <Outlet />
}

export { AdminRoute, EmailVerificationRoute, GuestRoute, ProtectedRoute, VerifiedUserRoute }