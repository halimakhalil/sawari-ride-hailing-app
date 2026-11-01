// Mirrors the Laravel API contract (backend/public/openapi.yaml).

export type Role = 'rider' | 'driver'
export type RideCategory = 'economy' | 'comfort'
export type RideStatus = 'requested' | 'accepted' | 'in_progress' | 'completed' | 'cancelled'
export type DriverAvailability = 'available' | 'busy' | 'offline'

export interface Place {
  id: string
  name: string
  area: string
  latitude: number
  longitude: number
}

export interface RoutePoint {
  address: string
  latitude: number
  longitude: number
}

export interface RouteInput {
  pickup: RoutePoint
  destination: RoutePoint
}

export interface CreateRideInput extends RouteInput {
  category: RideCategory
}

export interface FareEstimate {
  category: RideCategory
  label: string
  distance_km: number
  currency: string
  estimated_fare: string
  estimated_pickup_minutes: number | null
}

export interface Vehicle {
  category: RideCategory
  model: string
  plate: string
}

export interface DriverProfile {
  id: number
  name: string
  phone: string
  vehicle: Vehicle
}

export interface Driver extends DriverProfile {
  email: string
  availability_status: DriverAvailability
  current_location: { latitude: number; longitude: number } | null
}

export interface Rider {
  id: number
  name: string
  email: string
  phone: string
}

export interface Ride {
  id: number
  status: RideStatus
  category: RideCategory
  pickup: RoutePoint
  destination: RoutePoint
  distance_km: number
  fare: { currency: string; estimated: string; final: string | null }
  estimated_pickup_minutes: number | null
  can_cancel: boolean
  rider?: { id: number; name: string }
  driver?: DriverProfile | null
  cancellation_reason: string | null
  requested_at: string
  accepted_at: string | null
  started_at: string | null
  completed_at: string | null
  cancelled_at: string | null
}

export interface OpenRide extends Ride {
  pickup_distance_km: number
}

export type Session =
  | { role: 'rider'; token: string; user: Rider }
  | { role: 'driver'; token: string; user: Driver }

export interface CreateRideResult {
  ride: Ride
  /** True when the server replayed an earlier request with the same Idempotency-Key. */
  replayed: boolean
}

export interface ApiErrorBody {
  error: {
    code: string
    message: string
    details: Record<string, unknown>
    request_id?: string | null
  }
}
