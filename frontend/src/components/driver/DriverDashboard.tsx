import { useCallback, useEffect, useState } from 'react'
import { toApiError, type ApiError } from '../../api/client'
import { driverApi } from '../../api/services'
import type { Driver, DriverAvailability, OpenRide, Ride } from '../../api/types'
import { usePolling } from '../../hooks/usePolling'
import { categoryLabels, formatDistance, formatMoney, statusLabels } from '../../lib/format'
import { Alert, Button, Card, RouteSummary, Skeleton, Spinner } from '../ui'

const POLL_MS = 4000

/**
 * Demo console for drivers: go online, see eligible requests, accept, start and
 * complete trips. Every action goes to the real API; 409 conflicts (another
 * driver was faster) are shown as notices and the feed is refreshed.
 */
export function DriverDashboard({ driver }: { driver: Driver }) {
  const [availability, setAvailability] = useState<DriverAvailability>(driver.availability_status)
  const [currentRide, setCurrentRide] = useState<Ride | null>(null)
  const [openRides, setOpenRides] = useState<OpenRide[]>([])
  const [loaded, setLoaded] = useState(false)
  const [loadError, setLoadError] = useState<ApiError | null>(null)
  const [notice, setNotice] = useState<{ tone: 'error' | 'success' | 'warning'; text: string } | null>(null)
  const [pending, setPending] = useState<string | null>(null)

  const fetchState = useCallback(() => Promise.all([driverApi.currentRide(), driverApi.openRides()]), [])

  const applyState = useCallback(([current, feed]: Awaited<ReturnType<typeof fetchState>>) => {
    setCurrentRide(current)
    setOpenRides(feed.rides)
    setAvailability(feed.availability)
    setLoaded(true)
    setLoadError(null)
  }, [])

  const refresh = useCallback(async () => applyState(await fetchState()), [fetchState, applyState])

  useEffect(() => {
    fetchState()
      .then(applyState)
      .catch((error: unknown) => setLoadError(toApiError(error)))
  }, [fetchState, applyState])

  const pollError = usePolling(refresh, POLL_MS, loaded)

  async function run(key: string, action: () => Promise<void>, success?: string) {
    setPending(key)
    setNotice(null)
    try {
      await action()
      if (success) setNotice({ tone: 'success', text: success })
    } catch (error) {
      const apiError = toApiError(error)
      setNotice({ tone: apiError.status === 409 ? 'warning' : 'error', text: apiError.message })
    } finally {
      setPending(null)
      refresh().catch(() => undefined)
    }
  }

  const toggleAvailability = () =>
    run('availability', async () => {
      const updated = await driverApi.setAvailability(availability === 'available' ? 'offline' : 'available')
      setAvailability(updated.availability_status)
    })

  const accept = (ride: OpenRide) =>
    run(`accept-${ride.id}`, async () => setCurrentRide(await driverApi.accept(ride.id)), `You accepted ride #${ride.id}. Head to the pickup.`)

  const start = (ride: Ride) => run('start', async () => setCurrentRide(await driverApi.start(ride.id)), 'Trip started.')

  const complete = (ride: Ride) =>
    run('complete', async () => {
      const done = await driverApi.complete(ride.id)
      setCurrentRide(null)
      setNotice({ tone: 'success', text: `Trip complete. Collect ${formatMoney(done.fare.final ?? done.fare.estimated, done.fare.currency)} in cash.` })
    })

  return (
    <div className="mx-auto max-w-3xl space-y-4 px-4 py-4 lg:py-6">
      <Card className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <p className="text-sm text-slate-500">
            {driver.vehicle.model} · <span className="font-mono font-semibold">{driver.vehicle.plate}</span> · {categoryLabels[driver.vehicle.category].name}
          </p>
          <h1 className="text-xl font-bold text-slate-900">
            {availability === 'busy' ? 'On a ride' : availability === 'available' ? 'You’re online' : 'You’re offline'}
          </h1>
        </div>
        <Button
          variant={availability === 'available' ? 'secondary' : 'primary'}
          onClick={toggleAvailability}
          loading={pending === 'availability'}
          disabled={availability === 'busy' || !loaded}
          title={availability === 'busy' ? 'Finish your ride first' : undefined}
        >
          {availability === 'available' ? 'Go offline' : 'Go online'}
        </Button>
      </Card>

      {notice && <Alert tone={notice.tone}>{notice.text}</Alert>}
      {pollError && (
        <p className="flex items-center gap-2 text-xs text-amber-800" role="status">
          <Spinner className="size-3" /> Connection problem: retrying…
        </p>
      )}

      {!loaded && !loadError && (
        <Card className="space-y-3" aria-busy="true">
          <p className="sr-only">Loading…</p>
          <Skeleton className="h-6 w-48" />
          <Skeleton className="h-24" />
        </Card>
      )}

      {loadError && !loaded && (
        <Alert title="Couldn’t load your dashboard" action={<Button variant="secondary" onClick={() => refresh().catch((e: unknown) => setLoadError(toApiError(e)))}>Retry</Button>}>
          {loadError.message}
        </Alert>
      )}

      {loaded && currentRide && (
        <Card className="space-y-4 ring-2 ring-brand-600">
          <div className="flex items-start justify-between gap-3">
            <div>
              <p className="text-xs font-medium tracking-wide text-slate-500 uppercase">Current ride #{currentRide.id}</p>
              <h2 className="text-lg font-bold">{currentRide.status === 'accepted' ? 'Pick up your rider' : statusLabels[currentRide.status]}</h2>
              {currentRide.rider && <p className="text-sm text-slate-600">Rider: {currentRide.rider.name}</p>}
            </div>
            <p className="text-right text-lg font-bold">{formatMoney(currentRide.fare.estimated, currentRide.fare.currency)}</p>
          </div>
          <RouteSummary pickup={currentRide.pickup.address} destination={currentRide.destination.address} />
          {currentRide.status === 'accepted' && (
            <Button block onClick={() => start(currentRide)} loading={pending === 'start'} loadingText="Starting…">
              Rider picked up · Start trip
            </Button>
          )}
          {currentRide.status === 'in_progress' && (
            <Button block onClick={() => complete(currentRide)} loading={pending === 'complete'} loadingText="Completing…">
              Arrived · Complete trip
            </Button>
          )}
        </Card>
      )}

      {loaded && !currentRide && (
        <section aria-labelledby="requests-heading" className="space-y-3">
          <h2 id="requests-heading" className="px-1 text-sm font-semibold text-slate-700">
            Nearby ride requests
          </h2>

          {availability === 'offline' && (
            <Card className="text-center">
              <p className="font-semibold">You’re offline</p>
              <p className="mt-1 text-sm text-slate-600">Go online to start receiving ride requests.</p>
            </Card>
          )}

          {availability === 'available' && openRides.length === 0 && (
            <Card className="text-center">
              <p className="font-semibold">No requests nearby right now</p>
              <p className="mt-1 text-sm text-slate-600">New {categoryLabels[driver.vehicle.category].name} requests appear here automatically.</p>
            </Card>
          )}

          {availability === 'available' &&
            openRides.map((ride) => (
              <Card key={ride.id} className="space-y-3" aria-label={`Ride request #${ride.id} from ${ride.rider?.name ?? 'a rider'}`}>
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <p className="text-sm font-semibold text-slate-900">{ride.rider?.name ?? 'Rider'}</p>
                    <p className="text-xs text-slate-500">
                      {formatDistance(ride.pickup_distance_km)} to pickup · {formatDistance(ride.distance_km)} trip
                    </p>
                  </div>
                  <p className="text-lg font-bold">{formatMoney(ride.fare.estimated, ride.fare.currency)}</p>
                </div>
                <RouteSummary pickup={ride.pickup.address} destination={ride.destination.address} />
                <Button block onClick={() => accept(ride)} loading={pending === `accept-${ride.id}`} loadingText="Accepting…" disabled={pending !== null}>
                  Accept ride
                </Button>
              </Card>
            ))}
        </section>
      )}
    </div>
  )
}
