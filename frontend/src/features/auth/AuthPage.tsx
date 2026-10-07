import { useEffect, useState, type FormEvent } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { ApiError, apiRequest } from '../../lib/api'
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

interface CountryOption {
  code: string
  name: string
}

const emptyForm: FormValues = {
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  country: '',
  phone: '',
}

const inputClassName = 'mt-1 block w-full rounded-lg border border-[var(--theme-border)] bg-[var(--theme-surface)] px-3 py-2.5 text-sm text-[var(--theme-heading)] outline-none transition placeholder:text-[var(--theme-muted)] focus:border-[var(--theme-focus)] focus:ring-2 focus:ring-[rgba(130,204,197,0.18)]'

function AuthPage({ mode }: { mode: AuthMode }) {
  const isRegister = mode === 'register'
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const { login, register, error: sessionError } = useAuth()
  const [form, setForm] = useState(emptyForm)
  const [countries, setCountries] = useState<CountryOption[]>([])
  const [countrySearch, setCountrySearch] = useState('')
  const [countriesLoading, setCountriesLoading] = useState(isRegister)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

  useEffect(() => {
    if (!isRegister) return
    let active = true

    void apiRequest<{ data: CountryOption[] }>('/api/auth/countries')
      .then((response) => {
        if (active) setCountries(response.data)
      })
      .catch((requestError: unknown) => {
        if (active) setFormError(requestError instanceof Error ? requestError.message : 'Unable to load countries.')
      })
      .finally(() => {
        if (active) setCountriesLoading(false)
      })

    return () => { active = false }
  }, [isRegister])

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
      const sessionUser = isRegister
        ? await register({
          name: form.name,
          email: form.email,
          password: form.password,
          password_confirmation: form.password_confirmation,
          country: form.country,
          phone: form.phone || undefined,
        })
        : await login({ email: form.email, password: form.password })

      navigate(sessionUser.role === 'admin' || sessionUser.role === 'super_admin' ? '/admin' : '/dashboard', { replace: true })
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
      <div className="public-card p-6 shadow-xl shadow-black/20 sm:p-8">
        <p className="public-kicker inline-flex rounded-full px-3 py-1.5 text-xs font-medium uppercase tracking-[0.2em]">Demo Account · Simulated Performance</p>
        <h1 className="mt-3 text-3xl font-semibold text-[var(--theme-heading)]">{isRegister ? 'Create your account' : 'Welcome back'}</h1>
        <p className="mt-2 text-sm text-[var(--theme-muted)]">{isRegister ? 'Register to access the demo platform.' : 'Sign in to continue to the demo platform.'}</p>

        {visibleError && (
          <div role="alert" className="mt-5 rounded-lg border border-rose-500/30 bg-rose-500/10 px-3 py-2.5 text-sm text-rose-200">
            {visibleError}
          </div>
        )}

        {!isRegister && searchParams.get('verified') === '1' && (
          <p role="status" className="mt-5 border border-emerald-500/30 bg-emerald-500/10 px-3 py-2.5 text-sm text-emerald-200">
            Email address verified. You can now log in.
          </p>
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
                Search countries
                <input
                  type="search"
                  value={countrySearch}
                  onChange={(event) => setCountrySearch(event.target.value)}
                  autoComplete="off"
                  className={inputClassName}
                  aria-label="Search countries"
                />
              </label>
              <label className="block text-sm font-medium text-slate-200">
                Country
                <select
                  required
                  name="country"
                  value={form.country}
                  disabled={countriesLoading}
                  onChange={(event) => updateField('country', event.target.value)}
                  className={inputClassName}
                  aria-invalid={Boolean(fieldError('country'))}
                >
                  <option value="">{countriesLoading ? 'Loading countries...' : 'Select a country'}</option>
                  {countries
                    .filter((country) => `${country.name} ${country.code}`.toLowerCase().includes(countrySearch.toLowerCase()))
                    .map((country) => <option key={country.code} value={country.code}>{country.name} ({country.code})</option>)}
                </select>
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
            className="public-primary-action inline-flex w-full items-center justify-center rounded-lg px-4 py-3 text-sm font-semibold transition disabled:cursor-wait disabled:opacity-60"
          >
            {submitting ? 'Please wait…' : isRegister ? 'Create account' : 'Log in'}
          </button>
        </form>

        {!isRegister && (
          <p className="mt-4 text-right text-sm">
            <Link to="/forgot-password" className="font-medium text-cyan-300 hover:text-cyan-200">Forgot password?</Link>
          </p>
        )}

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