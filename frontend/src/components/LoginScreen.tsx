import { useState, type FormEvent } from 'react'
import { toApiError } from '../api/client'
import type { Role } from '../api/types'
import { useAuth } from '../auth/context'
import { Alert, Button, Card } from './ui'

// Seeded development accounts (see backend DatabaseSeeder). Not secrets.
const DEMO_ACCOUNTS: Record<Role, { label: string; email: string }[]> = {
  rider: [
    { label: 'Ayesha (rider)', email: 'rider@example.com' },
    { label: 'Bilal (rider)', email: 'rider2@example.com' },
  ],
  driver: [
    { label: 'Usman · Economy', email: 'driver@example.com' },
    { label: 'Hamza · Economy', email: 'driver2@example.com' },
    { label: 'Sana · Comfort', email: 'driver3@example.com' },
    { label: 'Imran · Comfort (offline)', email: 'driver4@example.com' },
    { label: 'Faisal · Economy (offline)', email: 'driver5@example.com' },
  ],
}

export function LoginScreen() {
  const { login } = useAuth()
  const [role, setRole] = useState<Role>('rider')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function submit(event: FormEvent) {
    event.preventDefault()
    if (!email || !password) {
      setError('Enter your email and password.')
      return
    }
    setSubmitting(true)
    setError(null)
    try {
      await login(role, email, password)
    } catch (err) {
      const apiError = toApiError(err)
      setError(apiError.fieldErrors.email ?? apiError.message)
      setSubmitting(false)
    }
  }

  function fillDemo(demoEmail: string) {
    setEmail(demoEmail)
    setPassword('password')
    setError(null)
  }

  return (
    <main className="flex min-h-screen items-center justify-center bg-gradient-to-br from-brand-800 to-brand-900 px-4 py-10">
      <div className="w-full max-w-md">
        <div className="mb-6 text-center text-white">
          <p className="text-4xl font-bold tracking-tight">Sawari</p>
          <p className="mt-1 text-brand-100">Rides across Lahore</p>
        </div>

        <Card className="space-y-5">
          <div className="grid grid-cols-2 gap-1 rounded-xl bg-slate-100 p-1" role="tablist" aria-label="Account type">
            {(['rider', 'driver'] as Role[]).map((r) => (
              <button
                key={r}
                type="button"
                role="tab"
                aria-selected={role === r}
                onClick={() => {
                  setRole(r)
                  setEmail('')
                  setPassword('')
                  setError(null)
                }}
                className={`rounded-lg py-2 text-sm font-semibold capitalize transition-colors ${role === r ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-800'}`}
              >
                {r === 'rider' ? 'I’m a rider' : 'I’m a driver'}
              </button>
            ))}
          </div>

          <form onSubmit={submit} className="space-y-4" noValidate>
            <div>
              <label htmlFor="email" className="mb-1 block text-sm font-semibold text-slate-800">Email</label>
              <input
                id="email"
                type="email"
                autoComplete="username"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                className="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm focus:bg-white"
              />
            </div>
            <div>
              <label htmlFor="password" className="mb-1 block text-sm font-semibold text-slate-800">Password</label>
              <input
                id="password"
                type="password"
                autoComplete="current-password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                className="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm focus:bg-white"
              />
            </div>
            {error && <Alert>{error}</Alert>}
            <Button type="submit" block loading={submitting} loadingText="Signing in…" className="!py-3">
              Sign in as {role}
            </Button>
          </form>

          <div className="border-t border-slate-100 pt-4">
            <p className="mb-2 text-xs font-medium text-slate-500">Demo accounts (password: <code>password</code>)</p>
            <div className="flex flex-wrap gap-2">
              {DEMO_ACCOUNTS[role].map((account) => (
                <button
                  key={account.email}
                  type="button"
                  onClick={() => fillDemo(account.email)}
                  className="rounded-full bg-brand-50 px-3 py-1 text-xs font-medium text-brand-800 ring-1 ring-brand-200 hover:bg-brand-100"
                >
                  {account.label}
                </button>
              ))}
            </div>
          </div>
        </Card>
        <p className="mt-4 text-center text-xs text-brand-100/80">Tip: open a second tab and sign in as a driver to accept your own ride.</p>
      </div>
    </main>
  )
}
