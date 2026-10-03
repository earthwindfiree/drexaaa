import { useEffect, useState, type FormEvent } from 'react'
import { ApiError, apiRequest, initializeCsrfCookie } from '../lib/api'
import { useAuth } from '../features/auth/AuthContext'
import { useDocumentTitle } from '../hooks/useDocumentTitle'
import type { AuthUser } from '../types/auth'

interface ProfileForm {
  name: string
  email: string
  country: string
  phone: string
}

interface CountryOption {
  code: string
  name: string
}

interface CountriesResponse {
  data: CountryOption[]
}

function UserSettingsPage() {
  const { refreshUser } = useAuth()
  const [user, setUser] = useState<AuthUser | null>(null)
  const [profile, setProfile] = useState<ProfileForm>({ name: '', email: '', country: '', phone: '' })
  const [countries, setCountries] = useState<CountryOption[]>([])
  const [profileLoading, setProfileLoading] = useState(true)
  const [countriesLoading, setCountriesLoading] = useState(true)
  const [countriesError, setCountriesError] = useState<string | null>(null)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [profileSaving, setProfileSaving] = useState(false)
  const [profileError, setProfileError] = useState<string | null>(null)
  const [profileFieldErrors, setProfileFieldErrors] = useState<Record<string, string[]>>({})
  const [profileMessage, setProfileMessage] = useState<string | null>(null)
  const [currentPassword, setCurrentPassword] = useState('')
  const [newPassword, setNewPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')
  const [passwordSaving, setPasswordSaving] = useState(false)
  const [passwordError, setPasswordError] = useState<string | null>(null)
  const [passwordFieldErrors, setPasswordFieldErrors] = useState<Record<string, string[]>>({})
  const [passwordMessage, setPasswordMessage] = useState<string | null>(null)
  const [verificationSending, setVerificationSending] = useState(false)
  const [verificationError, setVerificationError] = useState<string | null>(null)
  const [verificationMessage, setVerificationMessage] = useState<string | null>(null)

  useDocumentTitle('Settings')

  useEffect(() => {
    let active = true

    void apiRequest<unknown>('/api/profile')
      .then((response) => {
        const profileUser = getProfileUser(response)
        if (!active) return
        if (!profileUser) {
          setLoadError('The profile response is incomplete.')
          return
        }
        setUser(profileUser)
        setProfile({
          name: profileUser.name,
          email: profileUser.email,
          country: profileUser.country ?? '',
          phone: profileUser.phone ?? '',
        })
      })
      .catch((requestError: unknown) => {
        if (active) setLoadError(validationErrorMessage(requestError, 'Unable to load your profile.'))
      })
      .finally(() => {
        if (active) setProfileLoading(false)
      })

    void apiRequest<CountriesResponse>('/api/auth/countries')
      .then((response) => {
        if (!active) return
        if (!Array.isArray(response?.data)) {
          setCountriesError('Country options are unavailable. Enter your two-letter country code instead.')
          return
        }
        setCountries(response.data.filter(isCountryOption))
      })
      .catch(() => {
        if (active) setCountriesError('Country options are unavailable. Enter your two-letter country code instead.')
      })
      .finally(() => {
        if (active) setCountriesLoading(false)
      })

    return () => {
      active = false
    }
  }, [])

  function updateProfileField(field: keyof ProfileForm, value: string) {
    setProfile((current) => ({ ...current, [field]: field === 'country' ? value.toUpperCase().slice(0, 2) : value }))
    setProfileFieldErrors((current) => ({ ...current, [field]: [] }))
    setProfileError(null)
    setProfileMessage(null)
  }

  function profileFieldError(field: keyof ProfileForm): string | undefined {
    return profileFieldErrors[field]?.[0]
  }

  function passwordFieldError(field: string): string | undefined {
    return passwordFieldErrors[field]?.[0]
  }

  async function saveProfile(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const emailChanged = profile.email !== user?.email
    setProfileSaving(true)
    setProfileError(null)
    setProfileFieldErrors({})
    setProfileMessage(null)

    try {
      await initializeCsrfCookie()
      const response = await apiRequest<unknown>('/api/profile', {
        method: 'PATCH',
        body: JSON.stringify({ ...profile, phone: profile.phone || null }),
      })
      const updatedUser = getProfileUser(response)
      if (!updatedUser) throw new Error('Profile update response is incomplete.')

      setUser(updatedUser)
      setProfile({
        name: updatedUser.name,
        email: updatedUser.email,
        country: updatedUser.country ?? '',
        phone: updatedUser.phone ?? '',
      })

      try {
        const currentUser = await refreshUser()
        if (!currentUser) {
          setProfileMessage('Profile saved, but your session could not be refreshed. Sign in again to continue.')
          return
        }
        setUser(currentUser)
      } catch {
        setProfileMessage('Profile saved. Session details could not be refreshed; reload to update account access status.')
        return
      }

      setProfileMessage(emailChanged && updatedUser.email_verified_at === null
        ? 'Profile updated. A verification link was sent to your new email address.'
        : 'Profile updated successfully.')
    } catch (requestError: unknown) {
      if (requestError instanceof ApiError) setProfileFieldErrors(requestError.errors)
      setProfileError(validationErrorMessage(requestError, 'Unable to update your profile.'))
    } finally {
      setProfileSaving(false)
    }
  }

  async function updatePassword(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setPasswordSaving(true)
    setPasswordError(null)
    setPasswordFieldErrors({})
    setPasswordMessage(null)

    try {
      await initializeCsrfCookie()
      const response = await apiRequest<{ message: string }>('/api/profile/password', {
        method: 'PATCH',
        body: JSON.stringify({
          current_password: currentPassword,
          password: newPassword,
          password_confirmation: passwordConfirmation,
        }),
      })
      setCurrentPassword('')
      setNewPassword('')
      setPasswordConfirmation('')
      setPasswordMessage(typeof response?.message === 'string' ? response.message : 'Password updated successfully.')
    } catch (requestError: unknown) {
      if (requestError instanceof ApiError) setPasswordFieldErrors(requestError.errors)
      setPasswordError(validationErrorMessage(requestError, 'Unable to update your password.'))
    } finally {
      setPasswordSaving(false)
    }
  }

  async function resendVerification() {
    setVerificationSending(true)
    setVerificationError(null)
    setVerificationMessage(null)

    try {
      await initializeCsrfCookie()
      const response = await apiRequest<{ message: string }>('/api/auth/verification-notification', { method: 'POST' })
      setVerificationMessage(typeof response?.message === 'string' ? response.message : 'Verification email request completed.')
    } catch (requestError: unknown) {
      setVerificationError(validationErrorMessage(requestError, 'Unable to send a verification email.'))
    } finally {
      setVerificationSending(false)
    }
  }

  return (
    <section className="space-y-6 pb-10 md:space-y-8">
      <header>
        <p className="mb-2 text-[10px] font-semibold uppercase tracking-[0.2em] text-cyan-200/70">Account preferences</p>
        <h1 className="font-display text-2xl text-white sm:text-[30px]">Settings</h1>
        <p className="mt-2 max-w-xl text-sm text-slate-400">Manage your profile details and account security.</p>
      </header>

      {profileLoading && <SettingsLoading />}
      {!profileLoading && loadError && (
        <div role="alert" className="dashboard-message dashboard-error">
          <p className="text-sm font-medium text-rose-100">Settings unavailable</p>
          <p className="mt-1 text-sm text-rose-200/70">{loadError}</p>
        </div>
      )}

      {!profileLoading && !loadError && user && (
        <>
          <div className="settings-layout">
            <form className="settings-panel dashboard-surface" onSubmit={(event) => void saveProfile(event)}>
              <div className="settings-panel-heading">
                <div>
                  <p className="text-[10px] font-semibold uppercase tracking-[0.16em] text-cyan-100/60">Personal details</p>
                  <h2 className="mt-2 text-base font-medium text-white">Profile</h2>
                </div>
                <span className="settings-section-mark">01</span>
              </div>

              <div className="settings-fields">
                <SettingsField label="Full name" value={profile.name} onChange={(value) => updateProfileField('name', value)} error={profileFieldError('name')} autoComplete="name" />
                <SettingsField label="Email address" type="email" value={profile.email} onChange={(value) => updateProfileField('email', value)} error={profileFieldError('email')} autoComplete="email" />
                <div className="settings-field">
                  <label htmlFor="settings-country">Country</label>
                  {countriesError ? (
                    <input
                      id="settings-country"
                      required
                      value={profile.country}
                      onChange={(event) => updateProfileField('country', event.target.value)}
                      maxLength={2}
                      autoComplete="country"
                      placeholder="Two-letter code"
                      aria-invalid={Boolean(profileFieldError('country'))}
                    />
                  ) : (
                    <select
                      id="settings-country"
                      required
                      value={profile.country}
                      onChange={(event) => updateProfileField('country', event.target.value)}
                      disabled={countriesLoading || profileSaving}
                      aria-invalid={Boolean(profileFieldError('country'))}
                    >
                      <option value="">{countriesLoading ? 'Loading countries…' : 'Select country'}</option>
                      {profile.country && !countries.some((country) => country.code === profile.country) && (
                        <option value={profile.country}>{profile.country} · Current value</option>
                      )}
                      {countries.map((country) => <option key={country.code} value={country.code}>{country.name}</option>)}
                    </select>
                  )}
                  {countriesError && <p className="settings-field-hint">{countriesError}</p>}
                  {profileFieldError('country') && <p className="settings-field-error">{profileFieldError('country')}</p>}
                </div>
                <SettingsField label="Phone" type="tel" value={profile.phone} onChange={(value) => updateProfileField('phone', value)} error={profileFieldError('phone')} autoComplete="tel" optional />
              </div>

              {profileError && <p role="alert" className="settings-feedback is-error">{profileError}</p>}
              {profileMessage && <p role="status" className="settings-feedback is-success">{profileMessage}</p>}
              <div className="settings-form-footer">
                <p>Changing your email address resets verification and sends a verification link to the new address.</p>
                <button type="submit" className="settings-primary-button" disabled={profileSaving || countriesLoading}>
                  {profileSaving ? 'Saving profile…' : 'Save profile'}
                </button>
              </div>
            </form>

            <AccountStatusPanel user={user} onResend={resendVerification} sending={verificationSending} message={verificationMessage} error={verificationError} />
          </div>

          <SecurityPanel
            currentPassword={currentPassword}
            newPassword={newPassword}
            passwordConfirmation={passwordConfirmation}
            saving={passwordSaving}
            error={passwordError}
            message={passwordMessage}
            fieldError={passwordFieldError}
            onCurrentPasswordChange={setCurrentPassword}
            onNewPasswordChange={setNewPassword}
            onConfirmationChange={setPasswordConfirmation}
            onSubmit={updatePassword}
          />
        </>
      )}
    </section>
  )
}

function SettingsField({
  label,
  value,
  onChange,
  error,
  type = 'text',
  autoComplete,
  optional = false,
}: {
  label: string
  value: string
  onChange: (value: string) => void
  error?: string
  type?: string
  autoComplete?: string
  optional?: boolean
}) {
  const inputId = `settings-${label.toLowerCase().replace(/\s+/g, '-')}`

  return (
    <div className="settings-field">
      <label htmlFor={inputId}>{label}{optional && <span className="settings-optional"> · Optional</span>}</label>
      <input
        id={inputId}
        type={type}
        value={value}
        onChange={(event) => onChange(event.target.value)}
        autoComplete={autoComplete}
        required={!optional}
        aria-invalid={Boolean(error)}
        aria-describedby={error ? `${inputId}-error` : undefined}
      />
      {error && <p id={`${inputId}-error`} className="settings-field-error">{error}</p>}
    </div>
  )
}

function AccountStatusPanel({
  user,
  onResend,
  sending,
  message,
  error,
}: {
  user: AuthUser
  onResend: () => void
  sending: boolean
  message: string | null
  error: string | null
}) {
  const verified = Boolean(user.email_verified_at)

  return (
    <aside className="settings-status-panel dashboard-surface">
      <div className="settings-panel-heading">
        <div>
          <p className="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Read only</p>
          <h2 className="mt-2 text-base font-medium text-white">Account status</h2>
        </div>
        <span className="settings-section-mark">02</span>
      </div>

      <dl className="settings-status-list">
        <StatusItem label="Account access" value={user.status ? humanize(user.status) : 'Unavailable'} />
        <StatusItem label="Email verification" value={verified ? 'Verified' : 'Not verified'} state={verified ? 'verified' : 'unverified'} />
        <StatusItem label="Country" value={user.country || 'Not provided'} />
      </dl>

      {!verified && (
        <div className="settings-verification-action">
          <p className="text-xs leading-5 text-slate-400">Verify your current email address to continue using the user platform.</p>
          <button type="button" onClick={() => void onResend()} disabled={sending} className="settings-secondary-button">
            {sending ? 'Sending verification…' : 'Resend verification email'}
          </button>
          {error && <p role="alert" className="settings-field-error">{error}</p>}
          {message && <p role="status" className="settings-inline-success">{message}</p>}
        </div>
      )}
      {verified && <p className="settings-verified-note">Your email address is verified.</p>}
    </aside>
  )
}

function StatusItem({ label, value, state }: { label: string; value: string; state?: 'verified' | 'unverified' }) {
  return (
    <div className="settings-status-item">
      <dt>{label}</dt>
      <dd className={state ? `is-${state}` : ''}>{value}</dd>
    </div>
  )
}

function SecurityPanel({
  currentPassword,
  newPassword,
  passwordConfirmation,
  saving,
  error,
  message,
  fieldError,
  onCurrentPasswordChange,
  onNewPasswordChange,
  onConfirmationChange,
  onSubmit,
}: {
  currentPassword: string
  newPassword: string
  passwordConfirmation: string
  saving: boolean
  error: string | null
  message: string | null
  fieldError: (field: string) => string | undefined
  onCurrentPasswordChange: (value: string) => void
  onNewPasswordChange: (value: string) => void
  onConfirmationChange: (value: string) => void
  onSubmit: (event: FormEvent<HTMLFormElement>) => void
}) {
  return (
    <form className="settings-security-panel dashboard-surface" onSubmit={(event) => void onSubmit(event)}>
      <div className="settings-panel-heading">
        <div>
          <p className="text-[10px] font-semibold uppercase tracking-[0.16em] text-cyan-100/60">Credentials</p>
          <h2 className="mt-2 text-base font-medium text-white">Security</h2>
          <p className="mt-1 text-xs text-slate-500">Change your password using your current credentials.</p>
        </div>
        <span className="settings-section-mark">03</span>
      </div>

      <div className="settings-password-fields">
        <SettingsField label="Current password" type="password" value={currentPassword} onChange={onCurrentPasswordChange} error={fieldError('current_password')} autoComplete="current-password" />
        <SettingsField label="New password" type="password" value={newPassword} onChange={onNewPasswordChange} error={fieldError('password')} autoComplete="new-password" />
        <SettingsField label="Confirm new password" type="password" value={passwordConfirmation} onChange={onConfirmationChange} error={fieldError('password_confirmation')} autoComplete="new-password" />
      </div>

      {error && <p role="alert" className="settings-feedback is-error">{error}</p>}
      {message && <p role="status" className="settings-feedback is-success">{message}</p>}
      <div className="settings-form-footer">
        <p>All three password fields are required to update credentials.</p>
        <button type="submit" className="settings-primary-button is-secondary" disabled={saving}>
          {saving ? 'Updating password…' : 'Update password'}
        </button>
      </div>
    </form>
  )
}

function SettingsLoading() {
  return (
    <div className="settings-loading" role="status" aria-label="Loading settings">
      <div className="dashboard-skeleton h-[360px]" />
      <div className="dashboard-skeleton h-[250px]" />
      <span className="sr-only">Loading settings</span>
    </div>
  )
}

function getProfileUser(response: unknown): AuthUser | null {
  if (!response || typeof response !== 'object' || !('user' in response)) return null
  const user = response.user
  if (!user || typeof user !== 'object') return null
  const candidate = user as Partial<AuthUser>

  if (!Number.isInteger(candidate.id) || typeof candidate.name !== 'string' || typeof candidate.email !== 'string') return null
  if (typeof candidate.status !== 'string' || typeof candidate.role !== 'string') return null
  if (candidate.country !== null && typeof candidate.country !== 'string') return null
  if (candidate.phone !== null && typeof candidate.phone !== 'string') return null
  if (candidate.email_verified_at !== null && typeof candidate.email_verified_at !== 'string') return null

  return candidate as AuthUser
}

function isCountryOption(value: unknown): value is CountryOption {
  return Boolean(value && typeof value === 'object'
    && typeof (value as CountryOption).code === 'string'
    && typeof (value as CountryOption).name === 'string')
}

function humanize(value: string): string {
  return value.replace(/[_-]+/g, ' ').replace(/\b\w/g, (character) => character.toUpperCase())
}

function validationErrorMessage(error: unknown, fallback: string): string {
  if (error instanceof ApiError && Object.values(error.errors).length > 0) {
    return Object.values(error.errors).flat().join(' ')
  }
  if (error instanceof ApiError && error.status === 401) return 'Your session has expired. Please sign in again.'
  if (error instanceof ApiError && error.status === 403) return 'You are not authorized to perform this action.'
  return error instanceof Error ? error.message : fallback
}

export default UserSettingsPage