import { createContext, useContext } from 'react'
import type { Role, Session } from '../api/types'

export interface AuthContextValue {
  session: Session | null
  login: (role: Role, email: string, password: string) => Promise<void>
  logout: () => Promise<void>
}

export const AuthContext = createContext<AuthContextValue | null>(null)

export function useAuth(): AuthContextValue {
  const value = useContext(AuthContext)
  if (!value) throw new Error('useAuth must be used inside <AuthProvider>')
  return value
}
