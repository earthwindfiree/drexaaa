import { useEffect, useState, type ReactNode } from 'react'
import { ApiError, apiRequest, initializeCsrfCookie } from '../../lib/api'
import type { AuthUser, LoginCredentials, RegistrationDetails } from '../../types/auth'
import { AuthContext, type AuthContextValue } from './AuthContext'

interface AuthResponse {
  user: AuthUser
}

function errorMessage(error: unknown): string {
  return error instanceof Error ? error.message : 'An unexpected authentication error occurred.'
}

async function fetchCurrentUser(): Promise<AuthUser> {
  const response = await apiRequest<AuthResponse>('/api/auth/me')

  return response.user
}

function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<AuthUser | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    let active = true

    void fetchCurrentUser()
      .then((currentUser) => {
        if (active) setUser(currentUser)
      })
      .catch((requestError: unknown) => {
        if (!active) return

        if (requestError instanceof ApiError && requestError.status === 401) {
          setUser(null)
          return
        }

        setUser(null)
        setError(errorMessage(requestError))
      })
      .finally(() => {
        if (active) setLoading(false)
      })

    return () => {
      active = false
    }
  }, [])

  async function refreshUser(): Promise<AuthUser | null> {
    setError(null)

    try {
      const currentUser = await fetchCurrentUser()
      setUser(currentUser)

      return currentUser
    } catch (requestError) {
      if (requestError instanceof ApiError && requestError.status === 401) {
        setUser(null)

        return null
      }

      setUser(null)
      setError(errorMessage(requestError))
      throw requestError
    }
  }

  async function establishSession(path: string, payload: LoginCredentials | RegistrationDetails): Promise<AuthUser> {
    setError(null)
    await initializeCsrfCookie()
    await apiRequest<AuthResponse>(path, {
      method: 'POST',
      body: JSON.stringify(payload),
    })

    const currentUser = await refreshUser()

    if (!currentUser) {
      throw new Error('The session could not be confirmed. Please try again.')
    }

    return currentUser
  }

  async function login(credentials: LoginCredentials): Promise<AuthUser> {
    return establishSession('/api/auth/login', credentials)
  }

  async function register(details: RegistrationDetails): Promise<AuthUser> {
    return establishSession('/api/auth/register', details)
  }

  async function logout(): Promise<void> {
    await initializeCsrfCookie()
    await apiRequest<{ message: string }>('/api/auth/logout', { method: 'POST' })
    setUser(null)
    setError(null)
  }

  const value: AuthContextValue = {
    user,
    loading,
    authenticated: user !== null,
    error,
    login,
    register,
    logout,
    refreshUser,
  }

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export { AuthProvider }