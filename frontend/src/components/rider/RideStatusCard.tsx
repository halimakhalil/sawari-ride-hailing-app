import { useCallback, useState } from 'react'
import { toApiError, type ApiError } from '../../api/client'
import { ridesApi } from '../../api/services'
import type { Ride, RideStatus } from '../../api/types'
import { usePolling } from '../../hooks/usePolling'
import { categoryLabels, formatDistance, formatEta, formatMoney, isTerminal, statusLabels } from '../../lib/format'
import { Alert, Button, Card, RouteSummary, Spinner } from '../ui'

const POLL_MS = 4000
const STEPS: { status: RideStatus; label: string }[] = [
  { status: 'requested', label: 'Requested' },
  { status: 'accepted', label: 'Driver assigned' },
  { status: 'in_progress', label: 'On trip' },
  { status: 'completed', label: 'Arrived' },
]

interface Props {
  ride: Ride
  onChange: (ride: Ride) => void
  onDismiss: () => void
}

export function RideStatusCard({ ride, onChange, onDismiss }: Props) {
  const [confirmingCancel, setConfirmingCancel] = useState(false)
  const [cancelling, setCancelling] = useState(false)
  const [actionError, setActionError] = useState<ApiError | null>(null)

  const refresh = useCallback(async () => onChange(await ridesApi.get(ride.id)), [ride.id, onChange])
  const pollError = usePolling(refresh, POLL_MS, !isTerminal(ride.status))

  async function cancel() {
    setCancelling(true)
    setActionError(null)
    try {
      onChange(await ridesApi.cancel(ride.id))
      setConfirmingCancel(false)
    } catch (error) {
      const apiError = toApiError(error)
      setActionError(apiError)
      setConfirmingCancel(false)
      // The ride moved on (e.g. the trip started): show its real state.
      if (apiError.status === 409) refresh().catch(() => undefined)
    } finally {
      setCancelling(false)
    }
  }

  const stepIndex = STEPS.findIndex((s) => s.status === ride.status)
  const shownFare = ride.fare.final ?? ride.fare.estimated

  return (
    <Card className="space-y-5">
      <header className="flex items-start justify-between gap-3">
        <div>
          <p className="text-xs font-medium tracking-wide text-slate-500 uppercase">Ride #{ride.id}</p>
          <h2 className="mt-0.5 flex items-center gap-2 text-xl font-bold text-slate-900" aria-live="polite">
            {ride.status === 'requested' && <Spinner className="size-5 text-brand-600" />}
            {statusLabels[ride.status]}
          </h2>
          {ride.status === 'requested' && (
            <p className="mt-1 text-sm text-slate-600">
              {ride.estimated_pickup_minutes === null
                ? 'No drivers are nearby yet. We’ll keep looking.'
                : `Matching you with a nearby driver (about ${ride.estimated_pickup_minutes} min away)…`}
            </p>
          )}
          {ride.status === 'accepted' && ride.estimated_pickup_minutes !== null && (
            <p className="mt-1 text-sm text-slate-600">Your driver is {formatEta(ride.estimated_pickup_minutes)}.</p>
          )}
        </div>
        <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{categoryLabels[ride.category].name}</span>
      </header>

      {ride.status === 'cancelled' ? (
        <Alert tone="warning" title="This ride was cancelled">
          {ride.cancellation_reason ?? 'You won’t be charged.'}
        </Alert>
      ) : (
        <ol className="grid grid-cols-4 gap-1" aria-label="Ride progress">
          {STEPS.map((step, index) => {
            const done = index <= stepIndex
            return (
              <li key={step.status} className="space-y-1.5" aria-current={index === stepIndex ? 'step' : undefined}>
                <div className={`h-1.5 rounded-full ${done ? 'bg-brand-600' : 'bg-slate-200'}`} />
                <p className={`text-[11px] leading-tight ${done ? 'font-semibold text-slate-900' : 'text-slate-500'}`}>{step.label}</p>
              </li>
            )
          })}
        </ol>
      )}

      {ride.driver && (
        <div className="flex items-center gap-3 rounded-xl bg-slate-50 p-3 ring-1 ring-slate-200">
          <span className="flex size-11 items-center justify-center rounded-full bg-brand-700 text-base font-bold text-white" aria-hidden="true">
            {ride.driver.name.charAt(0)}
          </span>
          <div className="min-w-0 flex-1">
            <p className="font-semibold text-slate-900">{ride.driver.name}</p>
            <p className="truncate text-sm text-slate-600">
              {ride.driver.vehicle.model} · <span className="font-mono font-semibold">{ride.driver.vehicle.plate}</span>
            </p>
          </div>
          <a href={`tel:${ride.driver.phone}`} className="rounded-lg px-3 py-2 text-sm font-semibold text-brand-700 hover:bg-brand-50">
            Call
          </a>
        </div>
      )}

      <RouteSummary pickup={ride.pickup.address} destination={ride.destination.address} />

      <dl className="grid grid-cols-2 gap-3 rounded-xl bg-slate-50 p-3 text-sm">
        <div>
          <dt className="text-slate-500">{ride.fare.final ? 'Final fare' : 'Estimated fare'}</dt>
          <dd className="text-lg font-bold text-slate-900">{formatMoney(shownFare, ride.fare.currency)}</dd>
        </div>
        <div>
          <dt className="text-slate-500">Distance</dt>
          <dd className="text-lg font-bold text-slate-900">{formatDistance(ride.distance_km)}</dd>
        </div>
      </dl>

      {pollError && (
        <p className="flex items-center gap-2 text-xs text-amber-800" role="status">
          <Spinner className="size-3" /> Connection problem: reconnecting to update your ride status…
        </p>
      )}

      {actionError && <Alert title="Couldn’t cancel the ride">{actionError.message}</Alert>}

      {ride.can_cancel && !confirmingCancel && (
        <Button variant="danger" block onClick={() => setConfirmingCancel(true)}>
          Cancel ride
        </Button>
      )}

      {ride.can_cancel && confirmingCancel && (
        <div className="space-y-3 rounded-xl p-3 ring-1 ring-red-200" role="group" aria-label="Confirm cancellation">
          <p className="text-sm font-medium text-slate-800">Cancel this ride?{ride.driver ? ' Your driver is already on the way.' : ''}</p>
          <div className="grid grid-cols-2 gap-2">
            <Button variant="secondary" onClick={() => setConfirmingCancel(false)} disabled={cancelling}>
              Keep ride
            </Button>
            <Button variant="danger" onClick={cancel} loading={cancelling} loadingText="Cancelling…">
              Yes, cancel
            </Button>
          </div>
        </div>
      )}

      {isTerminal(ride.status) && (
        <Button block onClick={onDismiss}>
          Book another ride
        </Button>
      )}
    </Card>
  )
}
