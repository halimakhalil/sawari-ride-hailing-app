import type { RideCategory, RideStatus } from '../api/types'

export function formatMoney(amount: string | number, currency = 'PKR'): string {
  const value = typeof amount === 'string' ? Number(amount) : amount
  return `${currency} ${value.toLocaleString('en-PK', { maximumFractionDigits: 0 })}`
}

export function formatEta(minutes: number | null): string {
  if (minutes === null) return 'No drivers nearby'
  return minutes <= 1 ? '1 min away' : `${minutes} min away`
}

export function formatDistance(km: number): string {
  return `${km.toFixed(1)} km`
}

export const categoryLabels: Record<RideCategory, { name: string; blurb: string }> = {
  economy: { name: 'Economy', blurb: 'Affordable everyday rides' },
  comfort: { name: 'Comfort', blurb: 'Newer cars, extra legroom' },
}

export const statusLabels: Record<RideStatus, string> = {
  requested: 'Finding your driver',
  accepted: 'Driver on the way',
  in_progress: 'On your trip',
  completed: 'Trip completed',
  cancelled: 'Ride cancelled',
}

export function isTerminal(status: RideStatus): boolean {
  return status === 'completed' || status === 'cancelled'
}

export function newIdempotencyKey(): string {
  return typeof crypto !== 'undefined' && 'randomUUID' in crypto
    ? crypto.randomUUID()
    : `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 12)}`
}
