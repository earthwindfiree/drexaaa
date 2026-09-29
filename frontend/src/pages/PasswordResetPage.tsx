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
      <div className="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/20 sm:p-8">
        <p className="text-xs font-medium uppercase tracking-[0.2em] text-cyan-300">Demo Account · Simulated Performance</p>
        <h1 className="mt-3 text-3xl font-semibold text-white">{isReset ? 'Set a new password' : 'Reset your password'}</h1>
        <form onSubmit={(event) => void submit(event)} className="mt-6 space-y-4">
          <label className="block text-sm font-medium text-slate-200">
            Email
            <input type="email" required autoComplete="email" value={email} onChange={(event) => setEmail(event.target.value)} className={inputClassName} />
          </label>
          {isReset && (
            <>
              <label className="block text-sm font-medium text-slate-200">
                New password
                <input type="password" required minLength={8} autoComplete="new-password" value={password} onChange={(event) => setPassword(event.target.value)} className={inputClassName} />
              </label>
              <label className="block text-sm font-medium text-slate-200">
                Confirm new password
                <input type="password" required minLength={8} autoComplete="new-password" value={passwordConfirmation} onChange={(event) => setPasswordConfirmation(event.target.value)} className={inputClassName} />
              </label>
            </>
          )}
          {error && <p role="alert" className="text-sm text-rose-300">{error}</p>}
          {message && <p role="status" className="text-sm text-emerald-300">{message}</p>}
          <button type="submit" disabled={submitting} className="inline-flex w-full items-center justify-center rounded-lg bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300 disabled:opacity-60">
            {submitting ? 'Please wait...' : isReset ? 'Reset password' : 'Send reset link'}
          </button>
        </form>
        <p className="mt-6 text-center text-sm text-slate-400">
          <Link to="/login" className="font-medium text-cyan-300 hover:text-cyan-200">Back to login</Link>
        </p>
      </div>
    </section>
  )
}

const inputClassName = 'mt-1 block w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white outline-none transition placeholder:text-slate-500 focus:border-cyan-400 focus:ring-2 focus:ring-cyan-400/20'

function errorMessage(error: unknown, fallback: string): string {
  if (error instanceof ApiError && Object.values(error.errors).length > 0) {
    return Object.values(error.errors).flat().join(' ')
  }

  return error instanceof Error ? error.message : fallback
}

export default PasswordResetPage
