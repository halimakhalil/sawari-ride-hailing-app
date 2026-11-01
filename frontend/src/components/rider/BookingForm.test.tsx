import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ridesApi } from '../../api/services'
import { apiError, estimates, makeRide, places } from '../../test/fixtures'
import { BookingForm } from './BookingForm'

vi.mock('../../api/services', () => ({
  ridesApi: { estimate: vi.fn(), create: vi.fn() },
}))

const estimate = vi.mocked(ridesApi.estimate)
const create = vi.mocked(ridesApi.create)

function setup() {
  const onBooked = vi.fn()
  const onActiveRideExists = vi.fn()
  const user = userEvent.setup()
  render(<BookingForm places={places} onBooked={onBooked} onActiveRideExists={onActiveRideExists} onRouteChange={vi.fn()} />)
  return { user, onBooked, onActiveRideExists }
}

async function chooseRoute(user: ReturnType<typeof userEvent.setup>) {
  await user.selectOptions(screen.getByLabelText('Pickup location'), 'liberty-market')
  await user.selectOptions(screen.getByLabelText('Destination'), 'johar-town')
  await screen.findByText('PKR 600')
}

describe('BookingForm', () => {
  beforeEach(() => {
    estimate.mockResolvedValue(estimates)
  })

  it('renders pickup, destination, both categories and the request button', () => {
    setup()

    expect(screen.getByLabelText('Pickup location')).toBeInTheDocument()
    expect(screen.getByLabelText('Destination')).toBeInTheDocument()
    expect(screen.getByRole('radio', { name: /economy/i })).toHaveAttribute('aria-checked', 'true')
    expect(screen.getByRole('radio', { name: /comfort/i })).toHaveAttribute('aria-checked', 'false')
    expect(screen.getByRole('button', { name: 'Request ride' })).toBeEnabled()
    expect(screen.getByText(/pick both locations to see fares/i)).toBeInTheDocument()
  })

  it('requires pickup and destination before calling the API', async () => {
    const { user } = setup()

    await user.click(screen.getByRole('button', { name: 'Request ride' }))

    expect(screen.getByText('Choose a pickup location.')).toBeInTheDocument()
    expect(screen.getByText('Choose a destination.')).toBeInTheDocument()
    expect(screen.getByLabelText('Pickup location')).toHaveAttribute('aria-invalid', 'true')
    expect(create).not.toHaveBeenCalled()
  })

  it('rejects identical pickup and destination', async () => {
    const { user } = setup()
    await user.selectOptions(screen.getByLabelText('Pickup location'), 'liberty-market')
    await user.selectOptions(screen.getByLabelText('Destination'), 'liberty-market')

    await user.click(screen.getByRole('button', { name: 'Request ride' }))

    expect(screen.getByText('Destination must be different from pickup.')).toBeInTheDocument()
    expect(create).not.toHaveBeenCalled()
    expect(estimate).not.toHaveBeenCalled()
  })

  it('shows server estimates and books the selected category', async () => {
    create.mockResolvedValue({ ride: makeRide({ category: 'comfort' }), replayed: false })
    const { user, onBooked } = setup()
    await chooseRoute(user)

    expect(screen.getByText('PKR 870')).toBeInTheDocument()
    await user.click(screen.getByRole('radio', { name: /comfort/i }))
    expect(screen.getByRole('radio', { name: /comfort/i })).toHaveAttribute('aria-checked', 'true')

    await user.click(screen.getByRole('button', { name: 'Request Comfort · PKR 870' }))

    expect(create).toHaveBeenCalledWith(
      {
        pickup: { address: 'Liberty Market', latitude: 31.5104, longitude: 74.3446 },
        destination: { address: 'Johar Town', latitude: 31.4697, longitude: 74.2728 },
        category: 'comfort',
      },
      expect.any(String),
    )
    await waitFor(() => expect(onBooked).toHaveBeenCalledWith(expect.objectContaining({ category: 'comfort' })))
  })

  it('shows a loading state and blocks double submission while requesting', async () => {
    create.mockReturnValue(new Promise(() => {}))
    const { user } = setup()
    await chooseRoute(user)

    await user.click(screen.getByRole('button', { name: /request economy/i }))

    const button = screen.getByRole('button', { name: /requesting your ride/i })
    expect(button).toBeDisabled()
    expect(button).toHaveAttribute('aria-busy', 'true')
    await user.click(button)
    expect(create).toHaveBeenCalledTimes(1)
  })

  it('displays API errors', async () => {
    create.mockRejectedValue(apiError(500, 'INTERNAL_ERROR', 'Something went wrong on our side. Please try again.'))
    const { user, onBooked } = setup()
    await chooseRoute(user)

    await user.click(screen.getByRole('button', { name: /request economy/i }))

    const alert = await screen.findByRole('alert')
    expect(alert).toHaveTextContent('Booking failed')
    expect(alert).toHaveTextContent('Something went wrong on our side.')
    expect(onBooked).not.toHaveBeenCalled()
  })

  it('retries a failed booking with the same idempotency key', async () => {
    create
      .mockRejectedValueOnce(apiError(0, 'NETWORK_ERROR', 'Could not reach the server.'))
      .mockResolvedValueOnce({ ride: makeRide(), replayed: true })
    const { user, onBooked } = setup()
    await chooseRoute(user)

    await user.click(screen.getByRole('button', { name: /request economy/i }))
    expect(await screen.findByText(/retrying is safe/i)).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Try again' }))

    await waitFor(() => expect(onBooked).toHaveBeenCalled())
    expect(create).toHaveBeenCalledTimes(2)
    expect(create.mock.calls[0][1]).toBe(create.mock.calls[1][1])
  })

  it('uses a fresh idempotency key after the request changes', async () => {
    create.mockRejectedValue(apiError(0, 'NETWORK_ERROR', 'Could not reach the server.'))
    const { user } = setup()
    await chooseRoute(user)

    await user.click(screen.getByRole('button', { name: /request economy/i }))
    await screen.findByRole('alert')
    await user.click(screen.getByRole('radio', { name: /comfort/i }))
    await user.click(screen.getByRole('button', { name: /request comfort/i }))

    await waitFor(() => expect(create).toHaveBeenCalledTimes(2))
    expect(create.mock.calls[0][1]).not.toBe(create.mock.calls[1][1])
  })

  it('maps server validation errors onto fields', async () => {
    create.mockRejectedValue(
      apiError(422, 'VALIDATION_FAILED', 'The given data was invalid.', {
        'pickup.latitude': ['The location must be inside our Lahore service area.'],
      }),
    )
    const { user } = setup()
    await chooseRoute(user)

    await user.click(screen.getByRole('button', { name: /request economy/i }))

    expect(await screen.findByText('The location must be inside our Lahore service area.')).toBeInTheDocument()
  })

  it('hands over to the existing ride when the rider already has one', async () => {
    create.mockRejectedValue(apiError(409, 'ACTIVE_RIDE_EXISTS', 'You already have a ride in progress.', { active_ride_id: 12 }))
    const { user, onActiveRideExists } = setup()
    await chooseRoute(user)

    await user.click(screen.getByRole('button', { name: /request economy/i }))

    await waitFor(() => expect(onActiveRideExists).toHaveBeenCalledWith(12))
  })

  it('still allows booking when estimates fail to load', async () => {
    estimate.mockRejectedValue(apiError(0, 'NETWORK_ERROR', 'Could not reach the server.'))
    const { user } = setup()
    await user.selectOptions(screen.getByLabelText('Pickup location'), 'liberty-market')
    await user.selectOptions(screen.getByLabelText('Destination'), 'johar-town')

    expect(await screen.findByText(/couldn't load fare estimates/i)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Request ride' })).toBeEnabled()
  })
})
