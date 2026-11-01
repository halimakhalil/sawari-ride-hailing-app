import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { ridesApi } from '../../api/services'
import type { Ride } from '../../api/types'
import { apiError, makeRide } from '../../test/fixtures'
import { RideStatusCard } from './RideStatusCard'

vi.mock('../../api/services', () => ({
  ridesApi: { get: vi.fn(), cancel: vi.fn() },
}))

const cancel = vi.mocked(ridesApi.cancel)
const get = vi.mocked(ridesApi.get)

/** Mirrors the parent: onChange replaces the ride shown by the card. */
function setup(initial: Ride) {
  const user = userEvent.setup()
  const onChange = vi.fn()
  const onDismiss = vi.fn()
  const view = render(<RideStatusCard ride={initial} onChange={onChange} onDismiss={onDismiss} />)
  onChange.mockImplementation((ride: Ride) => view.rerender(<RideStatusCard ride={ride} onChange={onChange} onDismiss={onDismiss} />))
  return { user, onChange, onDismiss }
}

describe('RideStatusCard', () => {
  it('shows the booking confirmation with route and fare', () => {
    setup(makeRide())

    expect(screen.getByRole('heading', { name: /finding your driver/i })).toBeInTheDocument()
    expect(screen.getByText('Ride #42')).toBeInTheDocument()
    expect(screen.getByText('Liberty Market')).toBeInTheDocument()
    expect(screen.getByText('Johar Town')).toBeInTheDocument()
    expect(screen.getByText('PKR 600')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Cancel ride' })).toBeInTheDocument()
  })

  it('shows the assigned driver once accepted', () => {
    setup(
      makeRide({
        status: 'accepted',
        driver: { id: 3, name: 'Usman Tariq', phone: '+923211111111', vehicle: { category: 'economy', model: 'Suzuki Alto', plate: 'LEA-1234' } },
      }),
    )

    expect(screen.getByRole('heading', { name: /driver on the way/i })).toBeInTheDocument()
    expect(screen.getByText('Usman Tariq')).toBeInTheDocument()
    expect(screen.getByText('LEA-1234')).toBeInTheDocument()
  })

  it('cancels after confirmation', async () => {
    cancel.mockResolvedValue(makeRide({ status: 'cancelled', can_cancel: false }))
    const { user, onChange } = setup(makeRide())

    await user.click(screen.getByRole('button', { name: 'Cancel ride' }))
    expect(cancel).not.toHaveBeenCalled()
    await user.click(screen.getByRole('button', { name: 'Yes, cancel' }))

    expect(cancel).toHaveBeenCalledWith(42)
    await waitFor(() => expect(onChange).toHaveBeenCalledWith(expect.objectContaining({ status: 'cancelled' })))
    expect(await screen.findByRole('heading', { name: /ride cancelled/i })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Cancel ride' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Book another ride' })).toBeInTheDocument()
  })

  it('can back out of cancelling', async () => {
    const { user } = setup(makeRide())

    await user.click(screen.getByRole('button', { name: 'Cancel ride' }))
    await user.click(screen.getByRole('button', { name: 'Keep ride' }))

    expect(cancel).not.toHaveBeenCalled()
    expect(screen.getByRole('button', { name: 'Cancel ride' })).toBeInTheDocument()
  })

  it('reports a rejected cancellation and refreshes the ride', async () => {
    cancel.mockRejectedValue(apiError(409, 'INVALID_RIDE_TRANSITION', 'A ride that is in_progress cannot become cancelled.'))
    get.mockResolvedValue(makeRide({ status: 'in_progress', can_cancel: false }))
    const { user } = setup(makeRide({ status: 'accepted' }))

    await user.click(screen.getByRole('button', { name: 'Cancel ride' }))
    await user.click(screen.getByRole('button', { name: 'Yes, cancel' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('cannot become cancelled')
    expect(await screen.findByRole('heading', { name: /on your trip/i })).toBeInTheDocument()
  })

  it('does not offer cancellation once the trip has started', () => {
    setup(makeRide({ status: 'in_progress', can_cancel: false }))

    expect(screen.queryByRole('button', { name: 'Cancel ride' })).not.toBeInTheDocument()
  })

  it('shows the final fare when completed', () => {
    setup(makeRide({ status: 'completed', can_cancel: false, fare: { currency: 'PKR', estimated: '600.00', final: '600.00' } }))

    expect(screen.getByText('Final fare')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Book another ride' })).toBeInTheDocument()
  })
})
