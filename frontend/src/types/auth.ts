export interface AuthUser {
  id: number
  name: string
  email: string
  country: string | null
  phone: string | null
  role: string
  status: string
  email_verified_at: string | null
  two_factor_enabled?: boolean
}

export interface LoginCredentials {
  email: string
  password: string
  two_factor_code?: string
}

export interface RegistrationDetails extends LoginCredentials {
  name: string
  password_confirmation: string
  country: string
  phone?: string
}