export interface AuthUser {
  id: number
  name: string
  email: string
  country: string | null
  phone: string | null
  role: string
  status: string
}

export interface LoginCredentials {
  email: string
  password: string
}

export interface RegistrationDetails extends LoginCredentials {
  name: string
  password_confirmation: string
  country: string
  phone?: string
}