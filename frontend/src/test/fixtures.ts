import { ApiError } from '../api/client'
import type { FareEstimate, Place, Ride } from '../api/types'

export const places: Place[] = [
  { id: 'liberty-market', name: 'Liberty Market', area: 'Gulberg', latitude: 31.5104, longitude: 74.3446 },
  { id: 'johar-town', name: 'Johar Town', area: 'Johar Town', latitude: 31.4697, longitude: 74.2728 },
  { id: 'dha-phase-5', name: 'DHA Phase 5', area: 'Defence', latitude: 31.4626, longitude: 74.4085 },
]

export const estimates: FareEstimate[] = [
  { category: 'economy', label: 'Economy', distance_km: 10.63, currency: 'PKR', estimated_fare: '600.00', estimated_pickup_minutes: 4 },
  { category: 'comfort', label: 'Comfort', distance_km: 10.63, currency: 'PKR', estimated_fare: '870.00', estimated_pickup_minutes: 9 },
]

export function makeRide(overrides: Partial<Ride> = {}): Ride {
  return {
    id: 42,
    status: 'requested',
    category: 'economy',
    pickup: { address: 'Liberty Market', latitude: 31.5104, longitude: 74.3446 },
    destination: { address: 'Johar Town', latitude: 31.4697, longitude: 74.2728 },
    distance_km: 10.63,
    fare: { currency: 'PKR', estimated: '600.00', final: null },
    estimated_pickup_minutes: 4,
    can_cancel: true,
    rider: { id: 1, name: 'Ayesha Khan' },
    driver: null,
    cancellation_reason: null,
    requested_at: '2026-11-01T10:00:00+00:00',
    accepted_at: null,
    started_at: null,
    completed_at: null,
    cancelled_at: null,
    ...overrides,
  }
}

export function apiError(status: number, code: string, message: string, details: Record<string, unknown> = {}): ApiError {
  return new ApiError({ status, code, message, details })
}
