import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react'
import { setAuthToken, setUnauthenticatedHandler } from '../api/client'
import { authApi } from '../api/services'
import type { Role, Session } from '../api/types'
import { AuthContext } from './context'

const STORAGE_KEY = 'sawari.session'

// sessionStorage is per tab: a reload keeps you signed in, while another tab can
// sign in as a different account (e.g. rider in one tab, driver in the other)
// and signing out only affects the current tab. Production would use Sanctum's
// httpOnly cookie-based SPA auth instead of a bearer token in browser storage.
const storage = (): Storage => window.sessionStorage

function readStoredSession(): Session | null {
  try {
    const raw = storage().getItem(STORAGE_KEY)
    return raw ? (JSON.parse(raw) as Session) : null
  } catch {
    return null
  }
}

function storeSession(session: Session | null): void {
  try {
    if (session) storage().setItem(STORAGE_KEY, JSON.stringify(session))
    else storage().removeItem(STORAGE_KEY)
  } catch {
    // Storage unavailable (private mode): the session simply won't survive a reload.
  }
}

export function AuthProvider({ children }: { children: ReactNode }) {
  const [session, setSession] = useState<Session | null>(() => {
    const stored = readStoredSession()
    setAuthToken(stored?.token ?? null)
    return stored
  })

  const clear = useCallback(() => {
    setAuthToken(null)
    storeSession(null)
    setSession(null)
  }, [])

  useEffect(() => {
    setUnauthenticatedHandler(clear)
    return () => setUnauthenticatedHandler(null)
  }, [clear])

  const login = useCallback(async (role: Role, email: string, password: string) => {
    const next = await authApi.login(role, email, password)
    setAuthToken(next.token)
    storeSession(next)
    setSession(next)
  }, [])

  const logout = useCallback(async () => {
    try {
      await authApi.logout()
    } catch {
      // The token is discarded locally either way.
    }
    clear()
  }, [clear])

  const value = useMemo(() => ({ session, login, logout }), [session, login, logout])

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}
