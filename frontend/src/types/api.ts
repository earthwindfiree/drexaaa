export interface ApiErrorPayload {
  message?: string
  errors?: Record<string, string[]>
}