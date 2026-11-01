import { useState } from 'react'
import { useAuth } from './auth/context'
import { LoginScreen } from './components/LoginScreen'
import { DriverDashboard } from './components/driver/DriverDashboard'
import { RiderHome } from './components/rider/RiderHome'

export default function App() {
  const { session, logout } = useAuth()
  const [signingOut, setSigningOut] = useState(false)

  if (!session) return <LoginScreen />

  async function signOut() {
    setSigningOut(true)
    await logout()
    setSigningOut(false)
  }

  return (
    <div className="min-h-screen">
      <header className="sticky top-0 z-10 border-b border-slate-200 bg-white/90 backdrop-blur">
        <div className="mx-auto flex h-14 max-w-6xl items-center justify-between gap-3 px-4">
          <div className="flex items-center gap-2">
            <img src="/favicon.svg" alt="" className="size-8" />
            <span className="text-lg font-bold tracking-tight text-slate-900">Sawari</span>
            <span className="rounded-full bg-brand-50 px-2 py-0.5 text-xs font-semibold text-brand-800 capitalize ring-1 ring-brand-200">
              {session.role}
            </span>
          </div>
          <div className="flex items-center gap-3 text-sm">
            <span className="hidden text-slate-600 sm:inline">{session.user.name}</span>
            <button
              type="button"
              onClick={signOut}
              disabled={signingOut}
              className="rounded-lg px-2.5 py-1.5 font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900"
            >
              Sign out
            </button>
          </div>
        </div>
      </header>

      <main>{session.role === 'rider' ? <RiderHome /> : <DriverDashboard driver={session.user} />}</main>
    </div>
  )
}
