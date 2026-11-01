import { useEffect, useMemo, useRef, useState, type FormEvent, type ReactNode } from 'react'
import { toApiError, type ApiError } from '../../api/client'
import { ridesApi } from '../../api/services'
import type { FareEstimate, Place, Ride, RideCategory, RoutePoint } from '../../api/types'
import { categoryLabels, formatDistance, formatEta, formatMoney, newIdempotencyKey } from '../../lib/format'
import { Alert, Button, Skeleton } from '../ui'

interface Props {
  places: Place[]
  onBooked: (ride: Ride) => void
  /** The server says this rider already has an ongoing ride; show it instead. */
  onActiveRideExists: (rideId: number) => void
  onRouteChange: (pickup: RoutePoint | null, destination: RoutePoint | null) => void
}

type Estimates = { state: 'idle' } | { state: 'loading' } | { state: 'ready'; data: FareEstimate[] } | { state: 'error'; error: ApiError }

/** Latest estimate response, tagged with the route it belongs to. */
type EstimateResult = { routeKey: string; data: FareEstimate[] } | { routeKey: string; error: ApiError }

type FieldErrors = Partial<Record<'pickup' | 'destination' | 'category', string>>

const CATEGORIES: RideCategory[] = ['economy', 'comfort']

function toPoint(place: Place | undefined): RoutePoint | null {
  return place ? { address: place.name, latitude: place.latitude, longitude: place.longitude } : null
}

export function BookingForm({ places, onBooked, onActiveRideExists, onRouteChange }: Props) {
  const [pickupId, setPickupId] = useState('')
  const [destinationId, setDestinationId] = useState('')
  const [category, setCategory] = useState<RideCategory>('economy')
  const [estimateResult, setEstimateResult] = useState<EstimateResult | null>(null)
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({})
  const [submitting, setSubmitting] = useState(false)
  const [submitError, setSubmitError] = useState<ApiError | null>(null)

  // One key per booking attempt: retries of the same attempt reuse it, so the
  // server can never create two rides for one tap. Any edit starts a new attempt.
  const idempotencyKey = useRef<string | null>(null)

  const pickup = useMemo(() => toPoint(places.find((p) => p.id === pickupId)), [places, pickupId])
  const destination = useMemo(() => toPoint(places.find((p) => p.id === destinationId)), [places, destinationId])
  const samePlace = pickupId !== '' && pickupId === destinationId
  const routeKey = pickup && destination && !samePlace ? `${pickupId}>${destinationId}` : null

  // Derived, so a route change shows "loading" immediately without an extra render.
  const estimates: Estimates =
    routeKey === null
      ? { state: 'idle' }
      : estimateResult?.routeKey !== routeKey
        ? { state: 'loading' }
        : 'data' in estimateResult
          ? { state: 'ready', data: estimateResult.data }
          : { state: 'error', error: estimateResult.error }

  useEffect(() => {
    idempotencyKey.current = null
  }, [pickupId, destinationId, category])

  useEffect(() => {
    onRouteChange(pickup, destination)
  }, [pickup, destination, onRouteChange])

  useEffect(() => {
    if (routeKey === null || !pickup || !destination) return

    const controller = new AbortController()
    ridesApi
      .estimate({ pickup, destination }, controller.signal)
      .then((data) => setEstimateResult({ routeKey, data }))
      .catch((error: unknown) => {
        if (!controller.signal.aborted) setEstimateResult({ routeKey, error: toApiError(error) })
      })

    return () => controller.abort()
  }, [routeKey, pickup, destination])

  function validate(): FieldErrors {
    const errors: FieldErrors = {}
    if (!pickupId) errors.pickup = 'Choose a pickup location.'
    if (!destinationId) errors.destination = 'Choose a destination.'
    else if (samePlace) errors.destination = 'Destination must be different from pickup.'
    return errors
  }

  async function submit(event?: FormEvent) {
    event?.preventDefault()
    const errors = validate()
    setFieldErrors(errors)
    if (Object.keys(errors).length > 0 || !pickup || !destination) return

    idempotencyKey.current ??= newIdempotencyKey()
    setSubmitting(true)
    setSubmitError(null)

    try {
      const { ride } = await ridesApi.create({ pickup, destination, category }, idempotencyKey.current)
      idempotencyKey.current = null
      onBooked(ride)
    } catch (error) {
      const apiError = toApiError(error)
      const activeRideId = apiError.details.active_ride_id

      if (apiError.code === 'ACTIVE_RIDE_EXISTS' && typeof activeRideId === 'number') {
        onActiveRideExists(activeRideId)
        return
      }

      if (apiError.code === 'VALIDATION_FAILED') {
        const server = apiError.fieldErrors
        const firstFor = (prefix: string) => Object.entries(server).find(([key]) => key === prefix || key.startsWith(`${prefix}.`))?.[1]
        setFieldErrors({ pickup: firstFor('pickup'), destination: firstFor('destination'), category: firstFor('category') })
      }

      setSubmitError(apiError)
    } finally {
      setSubmitting(false)
    }
  }

  function swap() {
    setPickupId(destinationId)
    setDestinationId(pickupId)
  }

  const estimateFor = (c: RideCategory) => (estimates.state === 'ready' ? estimates.data.find((e) => e.category === c) : undefined)
  const selectedEstimate = estimateFor(category)

  return (
    <form onSubmit={submit} noValidate className="space-y-5" aria-label="Book a ride">
      <div className="space-y-3">
        <LocationSelect
          id="pickup"
          label="Pickup location"
          placeholder="Where should we pick you up?"
          value={pickupId}
          onChange={setPickupId}
          places={places}
          error={fieldErrors.pickup}
          marker={<span className="size-3 rounded-full border-[3px] border-brand-600 bg-white" />}
        />
        <div className="flex justify-end">
          <button
            type="button"
            onClick={swap}
            className="-my-2 rounded-lg px-2 py-1 text-xs font-medium text-brand-700 hover:bg-brand-50"
            aria-label="Swap pickup and destination"
          >
            ⇅ Swap
          </button>
        </div>
        <LocationSelect
          id="destination"
          label="Destination"
          placeholder="Where to?"
          value={destinationId}
          onChange={setDestinationId}
          places={places}
          error={fieldErrors.destination}
          marker={<span className="size-3 rounded-sm bg-slate-900" />}
        />
      </div>

      <fieldset>
        <legend className="mb-2 text-sm font-semibold text-slate-800">Choose a ride</legend>
        <div className="grid gap-2" role="radiogroup" aria-label="Ride category">
          {CATEGORIES.map((c) => (
            <CategoryOption
              key={c}
              category={c}
              selected={category === c}
              onSelect={() => setCategory(c)}
              estimate={estimateFor(c)}
              loading={estimates.state === 'loading'}
            />
          ))}
        </div>
        {fieldErrors.category && <p className="mt-1 text-sm text-red-700">{fieldErrors.category}</p>}
        {estimates.state === 'idle' && <p className="mt-2 text-xs text-slate-500">Pick both locations to see fares and pickup times.</p>}
        {estimates.state === 'error' && (
          <p className="mt-2 text-xs text-amber-800" role="status">
            Couldn&apos;t load fare estimates ({estimates.error.message}). The fare is still calculated when you book.
          </p>
        )}
      </fieldset>

      {submitError && submitError.code !== 'VALIDATION_FAILED' && (
        <Alert
          title={submitError.isNetworkError ? 'We couldn’t confirm your booking' : 'Booking failed'}
          action={
            <Button variant="secondary" className="shrink-0 !px-3 !py-1.5" onClick={() => submit()}>
              Try again
            </Button>
          }
        >
          {submitError.isNetworkError ? 'Retrying is safe: you will never be booked twice for the same request.' : submitError.message}
        </Alert>
      )}

      <div className="space-y-2">
        <Button type="submit" block loading={submitting} loadingText="Requesting your ride…" className="!py-3.5 text-base">
          {selectedEstimate
            ? `Request ${categoryLabels[category].name} · ${formatMoney(selectedEstimate.estimated_fare, selectedEstimate.currency)}`
            : 'Request ride'}
        </Button>
        <p className="text-center text-xs text-slate-500">Pay the driver in cash at the end of the trip.</p>
      </div>
    </form>
  )
}

interface LocationSelectProps {
  id: string
  label: string
  placeholder: string
  value: string
  onChange: (id: string) => void
  places: Place[]
  error?: string
  marker: ReactNode
}

function LocationSelect({ id, label, placeholder, value, onChange, places, error, marker }: LocationSelectProps) {
  const errorId = `${id}-error`
  return (
    <div>
      <label htmlFor={id} className="mb-1 block text-sm font-semibold text-slate-800">
        {label}
      </label>
      <div className="relative">
        <span className="pointer-events-none absolute top-1/2 left-3.5 flex -translate-y-1/2" aria-hidden="true">
          {marker}
        </span>
        <select
          id={id}
          value={value}
          onChange={(e) => onChange(e.target.value)}
          aria-invalid={error ? true : undefined}
          aria-describedby={error ? errorId : undefined}
          className={`w-full appearance-none rounded-xl border bg-slate-50 py-3 pr-10 pl-10 text-sm transition-colors focus:bg-white ${
            error ? 'border-red-400' : 'border-slate-200'
          } ${value ? 'text-slate-900' : 'text-slate-500'}`}
        >
          <option value="">{placeholder}</option>
          {places.map((place) => (
            <option key={place.id} value={place.id}>
              {place.name} — {place.area}
            </option>
          ))}
        </select>
        <span className="pointer-events-none absolute top-1/2 right-3.5 -translate-y-1/2 text-slate-400" aria-hidden="true">
          ▾
        </span>
      </div>
      {error && (
        <p id={errorId} className="mt-1 text-sm text-red-700">
          {error}
        </p>
      )}
    </div>
  )
}

interface CategoryOptionProps {
  category: RideCategory
  selected: boolean
  onSelect: () => void
  estimate?: FareEstimate
  loading: boolean
}

function CategoryOption({ category, selected, onSelect, estimate, loading }: CategoryOptionProps) {
  const { name, blurb } = categoryLabels[category]
  return (
    <button
      type="button"
      role="radio"
      aria-checked={selected}
      onClick={onSelect}
      className={`flex items-center gap-3 rounded-xl border-2 p-3 text-left transition-colors ${
        selected ? 'border-brand-600 bg-brand-50' : 'border-slate-200 bg-white hover:border-slate-300'
      }`}
    >
      <span
        className={`flex size-11 shrink-0 items-center justify-center rounded-lg text-xl ${category === 'comfort' ? 'bg-slate-900' : 'bg-brand-100'}`}
        aria-hidden="true"
      >
        {category === 'comfort' ? '🚘' : '🚗'}
      </span>
      <span className="min-w-0 flex-1">
        <span className="block font-semibold text-slate-900">{name}</span>
        <span className="block truncate text-xs text-slate-500">
          {estimate ? `${formatEta(estimate.estimated_pickup_minutes)} · ${formatDistance(estimate.distance_km)}` : blurb}
        </span>
      </span>
      <span className="text-right">
        {loading ? (
          <Skeleton className="h-5 w-16" />
        ) : estimate ? (
          <span className="font-semibold text-slate-900">{formatMoney(estimate.estimated_fare, estimate.currency)}</span>
        ) : (
          <span className="text-sm text-slate-400">—</span>
        )}
      </span>
    </button>
  )
}
