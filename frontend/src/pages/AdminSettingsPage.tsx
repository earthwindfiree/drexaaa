import { useState, type FormEvent } from 'react'
import { ApiError, apiRequest, initializeCsrfCookie } from '../lib/api'
import { useAuth } from '../features/auth/AuthContext'

interface ProfileForm {
  name: string
  email: string
  country: string
  phone: string
}

function AdminSettingsPage() {
  const { user, refreshUser } = useAuth()
  const [profile, setProfile] = useState<ProfileForm>(() => ({
    name: user?.name ?? '',
    email: user?.email ?? '',
    country: user?.country ?? '',
    phone: user?.phone ?? '',
  }))
  const [currentPassword, setCurrentPassword] = useState('')
  const [newPassword, setNewPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')
  const [profileSaving, setProfileSaving] = useState(false)
  const [passwordSaving, setPasswordSaving] = useState(false)
  const [verificationSending, setVerificationSending] = useState(false)
  const [profileError, setProfileError] = useState<string | null>(null)
  const [profileMessage, setProfileMessage] = useState<string | null>(null)
  const [passwordError, setPasswordError] = useState<string | null>(null)
  const [passwordMessage, setPasswordMessage] = useState<string | null>(null)
  const [verificationError, setVerificationError] = useState<string | null>(null)
  const [verificationMessage, setVerificationMessage] = useState<string | null>(null)

  async function saveProfile(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setProfileSaving(true)
    setProfileError(null)
    setProfileMessage(null)

    try {
      await initializeCsrfCookie()
      await apiRequest('/api/profile', {
        method: 'PATCH',
        body: JSON.stringify({ ...profile, phone: profile.phone || null }),
      })
      await refreshUser()
      setProfileMessage('Profile updated.')
    } catch (error: unknown) {
      setProfileError(errorMessage(error, 'Unable to update profile.'))
    } finally {
      setProfileSaving(false)
    }
  }

  async function changePassword(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setPasswordSaving(true)
    setPasswordError(null)
    setPasswordMessage(null)

    try {
      await initializeCsrfCookie()
      await apiRequest('/api/profile/password', {
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
      setPasswordMessage('Password updated.')
    } catch (error: unknown) {
      setPasswordError(errorMessage(error, 'Unable to update password.'))
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
      setVerificationMessage(response.message)
    } catch (error: unknown) {
      setVerificationError(errorMessage(error, 'Unable to send verification email.'))
    } finally {
      setVerificationSending(false)
    }
  }

  return (
    <section className="mx-auto max-w-5xl px-6 py-10 lg:px-8">
      <header className="mb-8">
        <p className="text-sm font-semibold uppercase tracking-[0.18em] text-cyan-700">Account access</p>
        <h1 className="mt-2 text-3xl font-semibold text-slate-950">Admin settings</h1>
      </header>

      <div className="grid gap-6 lg:grid-cols-2">
        <form onSubmit={(event) => void saveProfile(event)} className="border border-slate-200 bg-white p-5 shadow-sm">
          <h2 className="text-xl font-semibold text-slate-950">Profile</h2>
          <div className="mt-5 space-y-4">
            <SettingsField label="Full name" value={profile.name} onChange={(value) => setProfile({ ...profile, name: value })} autoComplete="name" />
            <SettingsField label="Email" type="email" value={profile.email} onChange={(value) => setProfile({ ...profile, email: value })} autoComplete="email" />
            <SettingsField label="Country code" value={profile.country} onChange={(value) => setProfile({ ...profile, country: value.toUpperCase() })} maxLength={2} autoComplete="country" />
            <SettingsField label="Phone" type="tel" value={profile.phone} onChange={(value) => setProfile({ ...profile, phone: value })} autoComplete="tel" />
          </div>

          {user?.email_verified_at ? (
            <p className="mt-4 text-sm text-emerald-700">Email verified</p>
          ) : (
            <div className="mt-4 border-l-2 border-amber-500 pl-3">
              <p className="text-sm font-medium text-amber-800">Email not verified</p>
              <button type="button" onClick={() => void resendVerification()} disabled={verificationSending} className="mt-2 text-sm font-semibold text-cyan-800 underline disabled:opacity-60">
                {verificationSending ? 'Sending...' : 'Resend verification email'}
              </button>
              {verificationMessage && <p role="status" className="mt-2 text-sm text-emerald-700">{verificationMessage}</p>}
              {verificationError && <p role="alert" className="mt-2 text-sm text-rose-700">{verificationError}</p>}
            </div>
          )}

          {profileError && <p role="alert" className="mt-4 text-sm text-rose-700">{profileError}</p>}
          {profileMessage && <p role="status" className="mt-4 text-sm text-emerald-700">{profileMessage}</p>}
          <button type="submit" disabled={profileSaving} className="mt-5 rounded-md bg-cyan-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60">
            {profileSaving ? 'Saving...' : 'Save profile'}
          </button>
        </form>

        <form onSubmit={(event) => void changePassword(event)} className="border border-slate-200 bg-white p-5 shadow-sm">
          <h2 className="text-xl font-semibold text-slate-950">Security</h2>
          <div className="mt-5 space-y-4">
            <SettingsField label="Current password" type="password" value={currentPassword} onChange={setCurrentPassword} autoComplete="current-password" />
            <SettingsField label="New password" type="password" value={newPassword} onChange={setNewPassword} autoComplete="new-password" />
            <SettingsField label="Confirm new password" type="password" value={passwordConfirmation} onChange={setPasswordConfirmation} autoComplete="new-password" />
          </div>
          {passwordError && <p role="alert" className="mt-4 text-sm text-rose-700">{passwordError}</p>}
          {passwordMessage && <p role="status" className="mt-4 text-sm text-emerald-700">{passwordMessage}</p>}
          <button type="submit" disabled={passwordSaving} className="mt-5 rounded-md bg-slate-950 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60">
            {passwordSaving ? 'Updating...' : 'Update password'}
          </button>
        </form>
      </div>
    </section>
  )
}

function SettingsField({ label, value, onChange, type = 'text', autoComplete, maxLength }: {
  label: string
  value: string
  onChange: (value: string) => void
  type?: string
  autoComplete?: string
  maxLength?: number
}) {
  return (
    <label className="block text-sm font-medium text-slate-700">
      {label}
      <input type={type} value={value} onChange={(event) => onChange(event.target.value)} autoComplete={autoComplete} maxLength={maxLength} required={label !== 'Phone'} className="mt-1 block w-full border border-slate-300 px-3 py-2 text-sm text-slate-950 outline-none focus:border-cyan-700" />
    </label>
  )
}

function errorMessage(error: unknown, fallback: string): string {
  if (error instanceof ApiError && Object.values(error.errors).length > 0) {
    return Object.values(error.errors).flat().join(' ')
  }

  return error instanceof Error ? error.message : fallback
}

export default AdminSettingsPage
