import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { ApiError, apiRequest, initializeCsrfCookie } from '../lib/api'
import { useAuth } from '../features/auth/AuthContext'

function EmailVerificationPage() {
  const { user, refreshUser, logout } = useAuth()
  const navigate = useNavigate()
  const [sending, setSending] = useState(false)
  const [checking, setChecking] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [message, setMessage] = useState<string | null>(null)

  async function resendVerification() {
    setSending(true)
    setError(null)
    setMessage(null)

    try {
      await initializeCsrfCookie()
      const response = await apiRequest<{ message: string }>('/api/auth/verification-notification', { method: 'POST' })
      setMessage(response.message)
    } catch (requestError: unknown) {
      setError(errorMessage(requestError, 'Unable to send a verification email.'))
    } finally {
      setSending(false)
    }
  }

  async function checkVerification() {
    setChecking(true)
    setError(null)

    try {
      const currentUser = await refreshUser()
      if (currentUser?.email_verified_at) {
        navigate('/dashboard', { replace: true })
      } else {
        setError('Email verification has not been detected yet.')
      }
    } catch (requestError: unknown) {
      setError(errorMessage(requestError, 'Unable to check email verification.'))
    } finally {
      setChecking(false)
    }
  }

  async function signOut() {
    try {
      await logout()
      navigate('/login', { replace: true })
    } catch (requestError: unknown) {
      setError(errorMessage(requestError, 'Unable to log out.'))
    }
  }

  return (
    <section className="mx-auto w-full max-w-xl px-6 py-12 sm:py-16">
      <div className="public-card p-6 shadow-xl shadow-black/20 sm:p-8">
        <p className="public-kicker inline-flex rounded-full px-3 py-1.5 text-xs font-medium uppercase tracking-[0.2em]">Demo Account · Simulated Performance</p>
        <h1 className="mt-3 text-3xl font-semibold text-[var(--theme-heading)]">Verify your email</h1>
        <p className="mt-3 text-sm leading-6 text-[var(--theme-body)]">
          A verification link was sent to <span className="font-medium text-[var(--theme-heading)]">{user?.email}</span>. Verify your address before continuing to the user platform.
        </p>

        {error && <p role="alert" className="public-error mt-5 rounded-lg px-3 py-2.5 text-sm">{error}</p>}
        {message && <p role="status" className="mt-5 rounded-lg border border-[rgba(155,211,179,0.24)] bg-[rgba(155,211,179,0.1)] px-3 py-2.5 text-sm text-[var(--theme-success)]">{message}</p>}

        <div className="mt-6 flex flex-wrap gap-3">
          <button type="button" onClick={() => void checkVerification()} disabled={checking} className="public-primary-action rounded-lg px-4 py-2.5 text-sm font-semibold disabled:opacity-60">
            {checking ? 'Checking...' : 'I verified my email'}
          </button>
          <button type="button" onClick={() => void resendVerification()} disabled={sending} className="public-secondary-action rounded-lg px-4 py-2.5 text-sm font-semibold disabled:opacity-60">
            {sending ? 'Sending...' : 'Resend verification email'}
          </button>
          <button type="button" onClick={() => void signOut()} className="px-3 py-2.5 text-sm font-medium text-[var(--theme-body)] hover:text-[var(--theme-heading)]">
            Log out
          </button>
        </div>
      </div>
    </section>
  )
}

function errorMessage(error: unknown, fallback: string): string {
  if (error instanceof ApiError && Object.values(error.errors).length > 0) {
    return Object.values(error.errors).flat().join(' ')
  }

  return error instanceof Error ? error.message : fallback
}

export default EmailVerificationPage
