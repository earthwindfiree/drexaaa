import { createContext, useContext } from 'react'
import type { AuthUser, LoginCredentials, RegistrationDetails } from '../../types/auth'

export interface AuthContextValue {
  user: AuthUser | null
  loading: boolean
  authenticated: boolean
  error: string | null
  login: (credentials: LoginCredentials) => Promise<AuthUser>
  register: (details: RegistrationDetails) => Promise<AuthUser>
  logout: () => Promise<void>
  refreshUser: () => Promise<AuthUser | null>
}

const AuthContext = createContext<AuthContextValue | null>(null)

function useAuth(): AuthContextValue {
  const context = useContext(AuthContext)

  if (!context) {
    throw new Error('useAuth must be used inside AuthProvider.')
  }

  return context
}

export { AuthContext, useAuth }