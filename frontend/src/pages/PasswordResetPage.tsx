import { useState, type FormEvent } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { ApiError, apiRequest, initializeCsrfCookie } from '../lib/api'

type PasswordResetMode = 'request' | 'reset'

function PasswordResetPage({ mode }: { mode: PasswordResetMode }) {
  const [searchParams] = useSearchParams()
  const [email, setEmail] = useState(searchParams.get('email') ?? '')
  const [password, setPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [message, setMessage] = useState<string | null>(null)
  const isReset = mode === 'reset'

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setSubmitting(true)
    setError(null)
    setMessage(null)

    try {
      await initializeCsrfCookie()
      if (isReset) {
        await apiRequest('/api/auth/reset-password', {
          method: 'POST',
          body: JSON.stringify({
            email,
            token: searchParams.get('token'),
            password,
            password_confirmation: passwordConfirmation,
          }),
        })
        setMessage('Password reset. You can now log in.')
      } else {
        const response = await apiRequest<{ message: string }>('/api/auth/forgot-password', {
          method: 'POST',
          body: JSON.stringify({ email }),
        })
        setMessage(response.message)
      }
    } catch (requestError: unknown) {
      setError(errorMessage(requestError, 'Unable to complete the password request.'))
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <section className="mx-auto w-full max-w-md px-6 py-12 sm:py-16">
      <div className="public-card p-6 shadow-xl shadow-black/20 sm:p-8">
        <p className="public-kicker inline-flex rounded-full px-3 py-1.5 text-xs font-medium uppercase tracking-[0.2em]">Demo Account · Simulated Performance</p>
        <h1 className="mt-3 text-3xl font-semibold text-[var(--theme-heading)]">{isReset ? 'Set a new password' : 'Reset your password'}</h1>
        <form onSubmit={(event) => void submit(event)} className="mt-6 space-y-4">
          <label className="block text-sm font-medium text-[var(--theme-body)]">
            Email
            <input type="email" required autoComplete="email" value={email} onChange={(event) => setEmail(event.target.value)} className={inputClassName} />
          </label>
          {isReset && (
            <>
              <label className="block text-sm font-medium text-[var(--theme-body)]">
                New password
                <input type="password" required minLength={8} autoComplete="new-password" value={password} onChange={(event) => setPassword(event.target.value)} className={inputClassName} />
              </label>
              <label className="block text-sm font-medium text-[var(--theme-body)]">
                Confirm new password
                <input type="password" required minLength={8} autoComplete="new-password" value={passwordConfirmation} onChange={(event) => setPasswordConfirmation(event.target.value)} className={inputClassName} />
              </label>
            </>
          )}
          {error && <p role="alert" className="public-error rounded-lg px-3 py-2.5 text-sm">{error}</p>}
          {message && <p role="status" className="rounded-lg border border-[rgba(155,211,179,0.24)] bg-[rgba(155,211,179,0.1)] px-3 py-2.5 text-sm text-[var(--theme-success)]">{message}</p>}
          <button type="submit" disabled={submitting} className="public-primary-action inline-flex w-full items-center justify-center rounded-lg px-4 py-3 text-sm font-semibold transition disabled:opacity-60">
            {submitting ? 'Please wait...' : isReset ? 'Reset password' : 'Send reset link'}
          </button>
        </form>
        <p className="mt-6 text-center text-sm text-[var(--theme-muted)]">
          <Link to="/login" className="font-medium text-[var(--theme-accent)] hover:text-[var(--theme-accent-hover)]">Back to login</Link>
        </p>
      </div>
    </section>
  )
}

const inputClassName = 'mt-1 block w-full rounded-lg border border-[var(--theme-border)] bg-[var(--theme-surface)] px-3 py-2.5 text-sm text-[var(--theme-heading)] outline-none transition placeholder:text-[var(--theme-muted)] focus:border-[var(--theme-focus)] focus:ring-2 focus:ring-[rgba(130,204,197,0.18)]'

function errorMessage(error: unknown, fallback: string): string {
  if (error instanceof ApiError && Object.values(error.errors).length > 0) {
    return Object.values(error.errors).flat().join(' ')
  }

  return error instanceof Error ? error.message : fallback
}

export default PasswordResetPage
