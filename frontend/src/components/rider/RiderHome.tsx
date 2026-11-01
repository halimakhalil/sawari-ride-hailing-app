import { useCallback, useEffect, useState } from 'react'
import { toApiError, type ApiError } from '../../api/client'
import { metaApi, ridesApi } from '../../api/services'
import type { Place, Ride, RoutePoint } from '../../api/types'
import { MapPlaceholder } from '../MapPlaceholder'
import { Alert, Button, Card, Skeleton } from '../ui'
import { BookingForm } from './BookingForm'
import { RideStatusCard } from './RideStatusCard'

type Load<T> = { state: 'loading' } | { state: 'ready'; data: T } | { state: 'error'; error: ApiError }

export function RiderHome() {
  const [places, setPlaces] = useState<Load<Place[]>>({ state: 'loading' })
  const [ride, setRide] = useState<Ride | null>(null)
  const [checkingActive, setCheckingActive] = useState(true)
  const [route, setRoute] = useState<{ pickup: RoutePoint | null; destination: RoutePoint | null }>({ pickup: null, destination: null })

  const fetchPlaces = useCallback(() => {
    metaApi
      .locations()
      .then((data) => setPlaces({ state: 'ready', data }))
      .catch((error: unknown) => setPlaces({ state: 'error', error: toApiError(error) }))
  }, [])

  const retryPlaces = () => {
    setPlaces({ state: 'loading' })
    fetchPlaces()
  }

  useEffect(() => {
    fetchPlaces()
    // Resume an ongoing ride after a reload. If this check fails the booking form
    // still works: the server answers ACTIVE_RIDE_EXISTS and we load that ride then.
    ridesApi
      .active()
      .then(setRide)
      .catch(() => undefined)
      .finally(() => setCheckingActive(false))
  }, [fetchPlaces])

  const showRide = useCallback((id: number) => {
    ridesApi.get(id).then(setRide).catch(() => undefined)
  }, [])

  const onRouteChange = useCallback((pickup: RoutePoint | null, destination: RoutePoint | null) => setRoute({ pickup, destination }), [])

  const mapPickup = ride ? ride.pickup : route.pickup
  const mapDestination = ride ? ride.destination : route.destination

  return (
    <div className="mx-auto grid max-w-6xl gap-4 px-4 py-4 lg:grid-cols-[minmax(0,420px)_1fr] lg:gap-6 lg:py-6">
      <MapPlaceholder
        places={places.state === 'ready' ? places.data : []}
        pickup={mapPickup}
        destination={mapDestination}
        className="h-52 rounded-2xl ring-1 ring-slate-200 sm:h-64 lg:sticky lg:top-20 lg:order-2 lg:h-[calc(100vh-7rem)]"
      />

      <div className="lg:order-1">
        {checkingActive ? (
          <Card className="space-y-3" aria-busy="true">
            <p className="sr-only">Loading…</p>
            <Skeleton className="h-6 w-40" />
            <Skeleton className="h-12" />
            <Skeleton className="h-12" />
            <Skeleton className="h-24" />
          </Card>
        ) : ride ? (
          <RideStatusCard ride={ride} onChange={setRide} onDismiss={() => setRide(null)} />
        ) : (
          <Card>
            <h1 className="text-2xl font-bold text-slate-900">Where to?</h1>
            <p className="mt-1 mb-5 text-sm text-slate-600">Rides across Lahore, upfront prices.</p>

            {places.state === 'loading' && (
              <div className="space-y-3" aria-busy="true">
                <p className="sr-only">Loading locations…</p>
                <Skeleton className="h-12" />
                <Skeleton className="h-12" />
                <Skeleton className="h-32" />
              </div>
            )}

            {places.state === 'error' && (
              <Alert title="Couldn’t load locations" action={<Button variant="secondary" onClick={retryPlaces}>Retry</Button>}>
                {places.error.message}
              </Alert>
            )}

            {places.state === 'ready' && places.data.length === 0 && (
              <Alert tone="info" title="No pickup locations yet">
                We don’t serve any locations right now. Please check back soon.
              </Alert>
            )}

            {places.state === 'ready' && places.data.length > 0 && (
              <BookingForm places={places.data} onBooked={setRide} onActiveRideExists={showRide} onRouteChange={onRouteChange} />
            )}
          </Card>
        )}
      </div>
    </div>
  )
}
