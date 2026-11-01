import { http } from './client'
import type {
  CreateRideInput,
  CreateRideResult,
  Driver,
  DriverAvailability,
  FareEstimate,
  OpenRide,
  Place,
  Ride,
  RouteInput,
  Session,
} from './types'

type Envelope<T> = { data: T }

export const authApi = {
  async login(role: Session['role'], email: string, password: string): Promise<Session> {
    const { data } = await http.post<Envelope<Session & { token_type: string }>>(`/auth/${role}/login`, { email, password })
    return { role: data.data.role, token: data.data.token, user: data.data.user } as Session
  },

  async logout(): Promise<void> {
    await http.post('/auth/logout')
  },
}

export const metaApi = {
  async locations(): Promise<Place[]> {
    const { data } = await http.get<Envelope<Place[]>>('/locations')
    return data.data
  },
}

export const ridesApi = {
  async estimate(route: RouteInput, signal?: AbortSignal): Promise<FareEstimate[]> {
    const { data } = await http.post<Envelope<FareEstimate[]>>('/fare-estimates', route, { signal })
    return data.data
  },

  /**
   * The same idempotencyKey must be reused when retrying one booking attempt,
   * so a retry after a timeout can never create a second ride.
   */
  async create(input: CreateRideInput, idempotencyKey: string): Promise<CreateRideResult> {
    const response = await http.post<Envelope<Ride>>('/rides', input, {
      headers: { 'Idempotency-Key': idempotencyKey },
    })
    return { ride: response.data.data, replayed: response.headers['idempotent-replayed'] === 'true' }
  },

  async get(id: number): Promise<Ride> {
    const { data } = await http.get<Envelope<Ride>>(`/rides/${id}`)
    return data.data
  },

  async active(): Promise<Ride | null> {
    const { data } = await http.get<Envelope<Ride[]>>('/rides', { params: { status: 'active' } })
    return data.data[0] ?? null
  },

  async cancel(id: number, reason?: string): Promise<Ride> {
    const { data } = await http.post<Envelope<Ride>>(`/rides/${id}/cancel`, reason ? { reason } : {})
    return data.data
  },
}

export const driverApi = {
  async openRides(): Promise<{ rides: OpenRide[]; availability: DriverAvailability }> {
    const { data } = await http.get<Envelope<OpenRide[]> & { meta: { availability_status: DriverAvailability } }>(
      '/driver/rides/available',
    )
    return { rides: data.data, availability: data.meta.availability_status }
  },

  async currentRide(): Promise<Ride | null> {
    const { data } = await http.get<Envelope<Ride | null>>('/driver/rides/current')
    return data.data
  },

  async setAvailability(status: Exclude<DriverAvailability, 'busy'>): Promise<Driver> {
    const { data } = await http.put<Envelope<Driver>>('/driver/availability', { status })
    return data.data
  },

  async accept(id: number): Promise<Ride> {
    const { data } = await http.post<Envelope<Ride>>(`/rides/${id}/accept`)
    return data.data
  },

  async start(id: number): Promise<Ride> {
    const { data } = await http.post<Envelope<Ride>>(`/rides/${id}/start`)
    return data.data
  },

  async complete(id: number): Promise<Ride> {
    const { data } = await http.post<Envelope<Ride>>(`/rides/${id}/complete`)
    return data.data
  },
}
