import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { metaApi, ridesApi } from '../../api/services'
import { apiError, makeRide, places } from '../../test/fixtures'
import { RiderHome } from './RiderHome'

vi.mock('../../api/services', () => ({
  metaApi: { locations: vi.fn() },
  ridesApi: { active: vi.fn(), get: vi.fn(), estimate: vi.fn(), create: vi.fn(), cancel: vi.fn() },
}))

const locations = vi.mocked(metaApi.locations)
const active = vi.mocked(ridesApi.active)

describe('RiderHome', () => {
  beforeEach(() => {
    active.mockResolvedValue(null)
  })

  it('shows a loading state, then the booking form', async () => {
    locations.mockResolvedValue(places)
    render(<RiderHome />)

    expect(screen.getByText('Loading…')).toBeInTheDocument()
    expect(await screen.findByRole('heading', { name: 'Where to?' })).toBeInTheDocument()
    expect(await screen.findByRole('form', { name: 'Book a ride' })).toBeInTheDocument()
  })

  it('shows an error with retry when locations fail to load', async () => {
    locations.mockRejectedValueOnce(apiError(0, 'NETWORK_ERROR', 'Could not reach the server. Check your connection.')).mockResolvedValueOnce(places)
    render(<RiderHome />)

    const alert = await screen.findByRole('alert')
    expect(alert).toHaveTextContent('Couldn’t load locations')
    expect(alert).toHaveTextContent('Could not reach the server.')

    await userEvent.click(screen.getByRole('button', { name: 'Retry' }))
    expect(await screen.findByRole('form', { name: 'Book a ride' })).toBeInTheDocument()
  })

  it('shows an empty state when no locations are served', async () => {
    locations.mockResolvedValue([])
    render(<RiderHome />)

    expect(await screen.findByText('No pickup locations yet')).toBeInTheDocument()
  })

  it('resumes an ongoing ride instead of showing the form', async () => {
    locations.mockResolvedValue(places)
    active.mockResolvedValue(makeRide({ status: 'accepted' }))
    render(<RiderHome />)

    expect(await screen.findByRole('heading', { name: /driver on the way/i })).toBeInTheDocument()
    expect(screen.queryByRole('form', { name: 'Book a ride' })).not.toBeInTheDocument()
  })
})
