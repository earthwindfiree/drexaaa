import type { ApiErrorPayload } from '../types/api'

const apiBaseUrl = (import.meta.env.VITE_API_BASE_URL ?? '').replace(/\/+$/, '')
const safeMethods = new Set(['GET', 'HEAD', 'OPTIONS'])

export class ApiError extends Error {
  status: number
  errors: Record<string, string[]>

  constructor(message: string, status: number, errors: Record<string, string[]> = {}) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.errors = errors
  }
}

function apiUrl(path: string): string {
  return `${apiBaseUrl}${path.startsWith('/') ? path : `/${path}`}`
}

function xsrfToken(): string | undefined {
  const prefix = 'XSRF-TOKEN='
  const cookie = document.cookie.split('; ').find((value) => value.startsWith(prefix))

  return cookie ? decodeURIComponent(cookie.slice(prefix.length)) : undefined
}

export async function initializeCsrfCookie(): Promise<void> {
  const response = await fetch(apiUrl('/sanctum/csrf-cookie'), {
    credentials: 'include',
    headers: { Accept: 'application/json' },
  })

  if (!response.ok) {
    throw new Error(`Could not initialize CSRF protection (${response.status}).`)
  }
}

export async function apiRequest<T>(path: string, options: RequestInit = {}): Promise<T> {
  const method = (options.method ?? 'GET').toUpperCase()
  const headers = new Headers(options.headers)
  headers.set('Accept', 'application/json')

  if (typeof options.body === 'string' && !headers.has('Content-Type')) {
    headers.set('Content-Type', 'application/json')
  }

  if (!safeMethods.has(method)) {
    const token = xsrfToken()
    if (token) headers.set('X-XSRF-TOKEN', token)
  }

  const response = await fetch(apiUrl(path), {
    ...options,
    method,
    headers,
    credentials: 'include',
  })

  if (!response.ok) {
    const payload = await response.json().catch(() => null) as ApiErrorPayload | null
    throw new ApiError(
      payload?.message ?? `API request failed (${response.status}).`,
      response.status,
      payload?.errors,
    )
  }

  if (response.status === 204) return undefined as T

  return await response.json() as T
}