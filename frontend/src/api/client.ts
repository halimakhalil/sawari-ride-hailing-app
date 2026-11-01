import axios, { AxiosError } from 'axios'
import type { ApiErrorBody } from './types'

/**
 * Every failed API call surfaces as an ApiError, so components never inspect
 * Axios internals. `code` is the server's machine-readable error code, or one of
 * NETWORK_ERROR / TIMEOUT / UNKNOWN_ERROR for failures that never got a response.
 */
export class ApiError extends Error {
  readonly status: number
  readonly code: string
  readonly details: Record<string, unknown>
  readonly requestId: string | null

  constructor(params: { status: number; code: string; message: string; details?: Record<string, unknown>; requestId?: string | null }) {
    super(params.message)
    this.name = 'ApiError'
    this.status = params.status
    this.code = params.code
    this.details = params.details ?? {}
    this.requestId = params.requestId ?? null
  }

  /** No response arrived, so the request may or may not have reached the server. */
  get isNetworkError(): boolean {
    return this.status === 0
  }

  /** Field → first message, for 422 validation errors. */
  get fieldErrors(): Record<string, string> {
    if (this.code !== 'VALIDATION_FAILED') return {}
    return Object.fromEntries(
      Object.entries(this.details).map(([field, messages]) => [field, Array.isArray(messages) ? String(messages[0]) : String(messages)]),
    )
  }
}

export function toApiError(error: unknown): ApiError {
  if (error instanceof ApiError) return error

  // isAxiosError checks a flag rather than the prototype, so it also holds when
  // two copies of axios are loaded (e.g. after Vite re-optimises dependencies).
  if (axios.isAxiosError(error)) {
    const body = error.response?.data as Partial<ApiErrorBody> | undefined

    if (error.response && body?.error) {
      return new ApiError({
        status: error.response.status,
        code: body.error.code,
        message: body.error.message,
        details: body.error.details,
        requestId: body.error.request_id,
      })
    }

    if (error.response) {
      return new ApiError({ status: error.response.status, code: 'HTTP_ERROR', message: 'The server returned an unexpected response.' })
    }

    if (error.code === AxiosError.ECONNABORTED || error.code === AxiosError.ETIMEDOUT) {
      return new ApiError({ status: 0, code: 'TIMEOUT', message: 'The server took too long to respond.' })
    }

    return new ApiError({ status: 0, code: 'NETWORK_ERROR', message: 'Could not reach the server. Check your connection.' })
  }

  return new ApiError({ status: 0, code: 'UNKNOWN_ERROR', message: 'Something unexpected happened.' })
}

export const http = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL ?? '/api',
  timeout: 15_000,
  headers: { Accept: 'application/json' },
})

let authToken: string | null = null
let onUnauthenticated: (() => void) | null = null

export function setAuthToken(token: string | null): void {
  authToken = token
}

/** Called once by the auth provider so an expired token logs the user out everywhere. */
export function setUnauthenticatedHandler(handler: (() => void) | null): void {
  onUnauthenticated = handler
}

http.interceptors.request.use((config) => {
  if (authToken) config.headers.Authorization = `Bearer ${authToken}`
  return config
})

http.interceptors.response.use(
  (response) => {
    // Every endpoint returns a JSON object. Anything else (an HTML error page from a
    // proxy or a crashed PHP process, even with status 200) must not count as success.
    if (typeof response.data !== 'object' || response.data === null) {
      return Promise.reject(new ApiError({ status: response.status, code: 'INVALID_RESPONSE', message: 'The server returned an unexpected response.' }))
    }
    return response
  },
  (error: unknown) => {
    const apiError = toApiError(error)
    if (apiError.status === 401 && authToken) onUnauthenticated?.()
    return Promise.reject(apiError)
  },
)
