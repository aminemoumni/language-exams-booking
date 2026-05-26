import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import ReservationsPage from '@/app/reservations/page'
import { useAuth } from '@/contexts/AuthContext'
import { apiClient } from '@/lib/axios'
import type { Reservation } from '@/types'

// ─── Mocks ────────────────────────────────────────────────────────────────────

jest.mock('@/contexts/AuthContext', () => ({ useAuth: jest.fn() }))

jest.mock('@/lib/axios', () => ({
  apiClient: { get: jest.fn(), post: jest.fn(), delete: jest.fn() },
  TOKEN_KEY: 'auth_token',
}))

jest.mock('next/navigation', () => ({
  useRouter:   () => ({ push: jest.fn(), replace: jest.fn() }),
  usePathname: () => '/reservations',
}))

// ─── Fixtures ─────────────────────────────────────────────────────────────────

const activeReservation: Reservation = {
  id: 'r1',
  session: {
    id: 's1',
    language: 'German',
    date: '2027-10-20',
    time: '11:00',
    location: 'Berlin – Zentrum',
    totalSeats: 30,
    availableSeats: 10,
    active: true,
    createdAt: '2026-01-01T00:00:00+00:00',
  },
  active: true,
  reservedAt: '2026-05-10T08:00:00+00:00',
}

const cancelledReservation: Reservation = {
  ...activeReservation,
  id: 'r2',
  active: false,
  cancelledAt: '2026-05-15T09:00:00+00:00',
}

const userAuth = {
  isAdmin:    false,
  isLoading:  false,
  token:      null,
  user:       { id: 'u1', name: 'Regular User', email: 'user@ets.com', createdAt: '2026-01-01' },
  login:      jest.fn(),
  logout:     jest.fn(),
  updateUser: jest.fn(),
}

// ─── Tests ────────────────────────────────────────────────────────────────────

describe('ReservationsPage', () => {
  beforeEach(() => {
    jest.clearAllMocks()
    ;(useAuth as jest.Mock).mockReturnValue(userAuth)
    ;(apiClient.get as jest.Mock).mockResolvedValue({ data: [activeReservation] })
    window.confirm = jest.fn(() => true)
  })

  it('shows a loading spinner before data arrives', () => {
    ;(apiClient.get as jest.Mock).mockImplementation(() => new Promise(() => {}))
    render(<ReservationsPage />)
    expect(screen.getByRole('status', { name: /loading/i })).toBeInTheDocument()
  })

  it('renders active reservation cards after loading', async () => {
    render(<ReservationsPage />)
    expect(await screen.findByText('German')).toBeInTheDocument()
    expect(screen.getByText('Berlin – Zentrum')).toBeInTheDocument()
    expect(screen.getByText('20 October 2027')).toBeInTheDocument()
  })

  it('shows the empty-state when there are no active reservations', async () => {
    ;(apiClient.get as jest.Mock).mockResolvedValue({ data: [] })
    render(<ReservationsPage />)
    expect(await screen.findByText(/no bookings yet/i)).toBeInTheDocument()
    expect(screen.getByText(/no active reservations/i)).toBeInTheDocument()
  })

  it('calls DELETE /reservations/:id after the user confirms cancellation', async () => {
    ;(apiClient.delete as jest.Mock).mockResolvedValue({})
    render(<ReservationsPage />)

    await userEvent.click(await screen.findByRole('button', { name: /cancel booking/i }))

    await waitFor(() =>
      expect(apiClient.delete).toHaveBeenCalledWith('/reservations/r1'),
    )
  })

  it('removes the card from the active list after successful cancellation', async () => {
    ;(apiClient.delete as jest.Mock).mockResolvedValue({})
    render(<ReservationsPage />)

    await screen.findByText('German') // wait for initial load
    await userEvent.click(screen.getByRole('button', { name: /cancel booking/i }))

    await waitFor(() =>
      expect(screen.queryByText('German')).not.toBeInTheDocument(),
    )
  })

  it('shows a success message after cancellation', async () => {
    ;(apiClient.delete as jest.Mock).mockResolvedValue({})
    render(<ReservationsPage />)

    await userEvent.click(await screen.findByRole('button', { name: /cancel booking/i }))

    expect(await screen.findByText(/booking cancelled successfully/i)).toBeInTheDocument()
  })

  it('shows an error when cancellation fails', async () => {
    ;(apiClient.delete as jest.Mock).mockRejectedValue({
      response: { data: { message: 'Reservation already cancelled.' } },
    })
    render(<ReservationsPage />)

    await userEvent.click(await screen.findByRole('button', { name: /cancel booking/i }))

    expect(await screen.findByText('Reservation already cancelled.')).toBeInTheDocument()
  })

  it('fetches all reservations (including cancelled) when "All bookings" tab is clicked', async () => {
    ;(apiClient.get as jest.Mock)
      .mockResolvedValueOnce({ data: [activeReservation] })           // active tab (initial)
      .mockResolvedValueOnce({ data: [activeReservation, cancelledReservation] }) // all tab

    render(<ReservationsPage />)
    await screen.findByText('German') // wait for active tab to load

    await userEvent.click(screen.getByRole('button', { name: /all bookings/i }))

    expect(await screen.findByText('✕ Cancelled')).toBeInTheDocument()
    // The second GET call should include active=false
    expect(apiClient.get).toHaveBeenCalledWith(
      '/reservations/me',
      expect.objectContaining({ params: { active: 'false' } }),
    )
  })
})
