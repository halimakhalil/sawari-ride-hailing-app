import type { ButtonHTMLAttributes, HTMLAttributes, ReactNode } from 'react'

type Variant = 'primary' | 'secondary' | 'danger' | 'ghost'

const variants: Record<Variant, string> = {
  primary: 'bg-brand-700 text-white hover:bg-brand-800 disabled:bg-brand-700/50',
  secondary: 'bg-white text-slate-800 ring-1 ring-slate-300 hover:bg-slate-50 disabled:text-slate-400',
  danger: 'bg-white text-red-700 ring-1 ring-red-200 hover:bg-red-50 disabled:text-red-300',
  ghost: 'text-slate-600 hover:bg-slate-100 hover:text-slate-900',
}

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: Variant
  loading?: boolean
  loadingText?: string
  block?: boolean
}

export function Button({ variant = 'primary', loading = false, loadingText, block, className = '', children, disabled, ...props }: ButtonProps) {
  return (
    <button
      type="button"
      {...props}
      disabled={disabled || loading}
      aria-busy={loading || undefined}
      className={`inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition-colors disabled:cursor-not-allowed ${variants[variant]} ${block ? 'w-full' : ''} ${className}`}
    >
      {loading && <Spinner />}
      {loading && loadingText ? loadingText : children}
    </button>
  )
}

export function Spinner({ className = 'size-4' }: { className?: string }) {
  return (
    <svg className={`animate-spin ${className}`} viewBox="0 0 24 24" fill="none" aria-hidden="true">
      <circle cx="12" cy="12" r="10" stroke="currentColor" strokeOpacity="0.25" strokeWidth="4" />
      <path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" strokeWidth="4" strokeLinecap="round" />
    </svg>
  )
}

type Tone = 'error' | 'warning' | 'info' | 'success'

const tones: Record<Tone, string> = {
  error: 'bg-red-50 text-red-800 ring-red-200',
  warning: 'bg-amber-50 text-amber-900 ring-amber-200',
  info: 'bg-sky-50 text-sky-900 ring-sky-200',
  success: 'bg-brand-50 text-brand-900 ring-brand-200',
}

export function Alert({ tone = 'error', title, children, action }: { tone?: Tone; title?: string; children?: ReactNode; action?: ReactNode }) {
  return (
    <div role={tone === 'error' ? 'alert' : 'status'} className={`flex items-start gap-3 rounded-xl p-3 text-sm ring-1 ${tones[tone]}`}>
      <div className="min-w-0 flex-1">
        {title && <p className="font-semibold">{title}</p>}
        {children && <div className={title ? 'mt-0.5' : ''}>{children}</div>}
      </div>
      {action}
    </div>
  )
}

export function Card({ children, className = '', ...props }: HTMLAttributes<HTMLElement>) {
  return (
    <section {...props} className={`rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 ${className}`}>
      {children}
    </section>
  )
}

export function Skeleton({ className = '' }: { className?: string }) {
  return <div className={`animate-pulse rounded-lg bg-slate-200 ${className}`} aria-hidden="true" />
}

export function RouteSummary({ pickup, destination }: { pickup: string; destination: string }) {
  return (
    <ol className="relative space-y-3 pl-6 text-sm">
      <span className="absolute top-2 bottom-2 left-[5px] w-px bg-slate-300" aria-hidden="true" />
      <li className="relative">
        <span className="absolute top-1 -left-6 size-3 rounded-full border-[3px] border-brand-600 bg-white" aria-hidden="true" />
        <span className="block text-xs text-slate-500">Pickup</span>
        <span className="font-medium text-slate-900">{pickup}</span>
      </li>
      <li className="relative">
        <span className="absolute top-1 -left-6 size-3 rounded-sm bg-slate-900" aria-hidden="true" />
        <span className="block text-xs text-slate-500">Destination</span>
        <span className="font-medium text-slate-900">{destination}</span>
      </li>
    </ol>
  )
}
