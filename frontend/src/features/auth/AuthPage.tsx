import { useState, type FormEvent } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { ApiError } from '../../lib/api'
import { useAuth } from './AuthContext'

type AuthMode = 'login' | 'register'
type FormValues = {
  name: string
  email: string
  password: string
  password_confirmation: string
  country: string
  phone: string
}

const emptyForm: FormValues = {
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  country: '',
  phone: '',
}

const inputClassName = 'mt-1 block w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white outline-none transition placeholder:text-slate-500 focus:border-cyan-400 focus:ring-2 focus:ring-cyan-400/20'

function AuthPage({ mode }: { mode: AuthMode }) {
  const isRegister = mode === 'register'
  const navigate = useNavigate()
  const { login, register, error: sessionError } = useAuth()
  const [form, setForm] = useState(emptyForm)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

  function updateField(field: keyof FormValues, value: string) {
    setForm((current) => ({ ...current, [field]: value }))
    setFieldErrors((current) => ({ ...current, [field]: [] }))
    setFormError(null)
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFieldErrors({})
    setFormError(null)
    setSubmitting(true)

    try {
      if (isRegister) {
        await register({
          name: form.name,
          email: form.email,
          password: form.password,
          password_confirmation: form.password_confirmation,
          country: form.country,
          phone: form.phone || undefined,
        })
      } else {
        await login({ email: form.email, password: form.password })
      }

      navigate('/dashboard', { replace: true })
    } catch (requestError) {
      if (requestError instanceof ApiError) {
        setFieldErrors(requestError.errors)
        setFormError(requestError.message)
      } else {
        setFormError(requestError instanceof Error ? requestError.message : 'Unable to complete the request.')
      }
    } finally {
      setSubmitting(false)
    }
  }

  function fieldError(field: keyof FormValues): string | undefined {
    return fieldErrors[field]?.[0]
  }

  const visibleError = formError ?? sessionError

  return (
    <section className="mx-auto w-full max-w-md px-6 py-12 sm:py-16">
      <div className="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/20 sm:p-8">
        <p className="text-xs font-medium uppercase tracking-[0.2em] text-cyan-300">Demo Account · Simulated Performance</p>
        <h1 className="mt-3 text-3xl font-semibold text-white">{isRegister ? 'Create your account' : 'Welcome back'}</h1>
        <p className="mt-2 text-sm text-slate-400">{isRegister ? 'Register to access the demo platform.' : 'Sign in to continue to the demo platform.'}</p>

        {visibleError && (
          <div role="alert" className="mt-5 rounded-lg border border-rose-500/30 bg-rose-500/10 px-3 py-2.5 text-sm text-rose-200">
            {visibleError}
          </div>
        )}

        <form onSubmit={(event) => void handleSubmit(event)} className="mt-6 space-y-4" noValidate>
          {isRegister && (
            <label className="block text-sm font-medium text-slate-200">
              Full name
              <input
                required
                autoComplete="name"
                name="name"
                value={form.name}
                onChange={(event) => updateField('name', event.target.value)}
                className={inputClassName}
                aria-invalid={Boolean(fieldError('name'))}
              />
              {fieldError('name') && <span className="mt-1 block text-xs text-rose-300">{fieldError('name')}</span>}
            </label>
          )}

          <label className="block text-sm font-medium text-slate-200">
            Email
            <input
              required
              type="email"
              autoComplete="email"
              name="email"
              value={form.email}
              onChange={(event) => updateField('email', event.target.value)}
              className={inputClassName}
              aria-invalid={Boolean(fieldError('email'))}
            />
            {fieldError('email') && <span className="mt-1 block text-xs text-rose-300">{fieldError('email')}</span>}
          </label>

          {isRegister && (
            <>
              <label className="block text-sm font-medium text-slate-200">
                Country code
                <input
                  required
                  maxLength={2}
                  autoComplete="country"
                  name="country"
                  placeholder="US"
                  value={form.country}
                  onChange={(event) => updateField('country', event.target.value.toUpperCase())}
                  className={inputClassName}
                  aria-invalid={Boolean(fieldError('country'))}
                />
                {fieldError('country') && <span className="mt-1 block text-xs text-rose-300">{fieldError('country')}</span>}
              </label>

              <label className="block text-sm font-medium text-slate-200">
                Phone <span className="font-normal text-slate-400">(optional)</span>
                <input
                  type="tel"
                  autoComplete="tel"
                  name="phone"
                  value={form.phone}
                  onChange={(event) => updateField('phone', event.target.value)}
                  className={inputClassName}
                  aria-invalid={Boolean(fieldError('phone'))}
                />
                {fieldError('phone') && <span className="mt-1 block text-xs text-rose-300">{fieldError('phone')}</span>}
              </label>
            </>
          )}

          <label className="block text-sm font-medium text-slate-200">
            Password
            <input
              required
              type="password"
              autoComplete={isRegister ? 'new-password' : 'current-password'}
              name="password"
              value={form.password}
              onChange={(event) => updateField('password', event.target.value)}
              className={inputClassName}
              aria-invalid={Boolean(fieldError('password'))}
            />
            {fieldError('password') && <span className="mt-1 block text-xs text-rose-300">{fieldError('password')}</span>}
          </label>

          {isRegister && (
            <label className="block text-sm font-medium text-slate-200">
              Confirm password
              <input
                required
                type="password"
                autoComplete="new-password"
                name="password_confirmation"
                value={form.password_confirmation}
                onChange={(event) => updateField('password_confirmation', event.target.value)}
                className={inputClassName}
                aria-invalid={Boolean(fieldError('password_confirmation'))}
              />
              {fieldError('password_confirmation') && <span className="mt-1 block text-xs text-rose-300">{fieldError('password_confirmation')}</span>}
            </label>
          )}

          <button
            type="submit"
            disabled={submitting}
            className="inline-flex w-full items-center justify-center rounded-lg bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300 disabled:cursor-wait disabled:opacity-60"
          >
            {submitting ? 'Please wait…' : isRegister ? 'Create account' : 'Log in'}
          </button>
        </form>

        <p className="mt-6 text-center text-sm text-slate-400">
          {isRegister ? 'Already registered?' : 'New to the demo?'}{' '}
          <Link to={isRegister ? '/login' : '/register'} className="font-medium text-cyan-300 hover:text-cyan-200">
            {isRegister ? 'Log in' : 'Create an account'}
          </Link>
        </p>
      </div>
    </section>
  )
}

export default AuthPage