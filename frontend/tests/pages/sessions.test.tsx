import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import SessionsPage from '@/app/sessions/page'
import { useAuth } from '@/contexts/AuthContext'
import { apiClient } from '@/lib/axios'
import type { Session, PaginatedResponse } from '@/types'

// ─── Mocks ────────────────────────────────────────────────────────────────────

jest.mock('@/contexts/AuthContext', () => ({ useAuth: jest.fn() }))

jest.mock('@/lib/axios', () => ({
  apiClient: { get: jest.fn(), post: jest.fn(), put: jest.fn(), delete: jest.fn() },
  TOKEN_KEY: 'auth_token',
}))

jest.mock('next/navigation', () => ({
  useRouter:   () => ({ push: jest.fn(), replace: jest.fn() }),
  usePathname: () => '/sessions',
}))

// ─── Fixtures ─────────────────────────────────────────────────────────────────

const mockSession: Session = {
  id: 's1',
  language: 'English',
  date: '2027-08-15',
  time: '09:00',
  location: 'Paris – Centre Diderot',
  totalSeats: 20,
  availableSeats: 15,
  active: true,
  createdAt: '2026-01-01T00:00:00+00:00',
}

const sessionPage: { data: PaginatedResponse<Session> } = {
  data: { data: [mockSession], total: 1, page: 1, limit: 9, totalPages: 1 },
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

// ─── Helpers ──────────────────────────────────────────────────────────────────

/** Default double-GET mock: sessions + empty booked list */
function mockDefaultApi() {
  ;(apiClient.get as jest.Mock).mockImplementation((url: string) => {
    if (url === '/sessions')        return Promise.resolve(sessionPage)
    if (url === '/reservations/me') return Promise.resolve({ data: [] })
    return Promise.reject(new Error(`Unexpected GET ${url}`))
  })
}

// ─── Tests ────────────────────────────────────────────────────────────────────

describe('SessionsPage', () => {
  beforeEach(() => {
    jest.clearAllMocks()
    ;(useAuth as jest.Mock).mockReturnValue(userAuth)
    mockDefaultApi()
  })

  it('shows a loading spinner before data arrives', () => {
    // API never resolves → spinner stays visible
    ;(apiClient.get as jest.Mock).mockImplementation(() => new Promise(() => {}))
    render(<SessionsPage />)
    expect(screen.getByRole('status', { name: /loading/i })).toBeInTheDocument()
  })

  it('renders session cards after the data loads', async () => {
    render(<SessionsPage />)
    // Wait for a text that only appears inside a session card (not the filter dropdown)
    expect(await screen.findByText('Paris – Centre Diderot')).toBeInTheDocument()
    expect(screen.getByText('15 August 2027')).toBeInTheDocument()
    expect(screen.getByText('09:00')).toBeInTheDocument()
  })

  it('shows an empty-state message when no sessions are returned', async () => {
    ;(apiClient.get as jest.Mock).mockImplementation((url: string) => {
      if (url === '/sessions')
        return Promise.resolve({ data: { data: [], total: 0, page: 1, limit: 9, totalPages: 1 } })
      if (url === '/reservations/me') return Promise.resolve({ data: [] })
    })
    render(<SessionsPage />)
    expect(await screen.findByText(/no sessions found/i)).toBeInTheDocument()
  })

  it('calls POST /reservations when the Book button is clicked', async () => {
    ;(apiClient.post as jest.Mock).mockResolvedValue({ data: {} })
    render(<SessionsPage />)

    await userEvent.click(await screen.findByRole('button', { name: /book this session/i }))

    await waitFor(() =>
      expect(apiClient.post).toHaveBeenCalledWith('/reservations', { sessionId: 's1' }),
    )
  })

  it('shows the error message when a booking fails', async () => {
    ;(apiClient.post as jest.Mock).mockRejectedValue({
      response: { data: { message: 'No seats available.' } },
    })
    render(<SessionsPage />)

    await userEvent.click(await screen.findByRole('button', { name: /book this session/i }))

    expect(await screen.findByText('No seats available.')).toBeInTheDocument()
  })

  it('shows the "+ New session" button only for admins', async () => {
    ;(useAuth as jest.Mock).mockReturnValue({ ...userAuth, isAdmin: true })
    render(<SessionsPage />)
    expect(await screen.findByRole('button', { name: /new session/i })).toBeInTheDocument()
  })

  it('does not show the "+ New session" button for regular users', async () => {
    render(<SessionsPage />)
    await screen.findByText('Paris – Centre Diderot') // wait for cards to load
    expect(screen.queryByRole('button', { name: /new session/i })).not.toBeInTheDocument()
  })

  it('shows pagination controls when totalPages > 1', async () => {
    ;(apiClient.get as jest.Mock).mockImplementation((url: string) => {
      if (url === '/sessions')
        return Promise.resolve({ data: { data: [mockSession], total: 20, page: 1, limit: 9, totalPages: 3 } })
      if (url === '/reservations/me') return Promise.resolve({ data: [] })
    })
    render(<SessionsPage />)
    expect(await screen.findByText('Page 1 of 3')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /next/i })).toBeInTheDocument()
  })

  it('calls DELETE /sessions/:id when admin confirms deletion', async () => {
    window.confirm = jest.fn(() => true)
    ;(useAuth as jest.Mock).mockReturnValue({ ...userAuth, isAdmin: true })
    ;(apiClient.delete as jest.Mock).mockResolvedValue({})

    render(<SessionsPage />)
    await userEvent.click(await screen.findByRole('button', { name: /delete/i }))

    await waitFor(() =>
      expect(apiClient.delete).toHaveBeenCalledWith('/sessions/s1'),
    )
  })

  it('does not call DELETE when admin dismisses the confirmation dialog', async () => {
    window.confirm = jest.fn(() => false)
    ;(useAuth as jest.Mock).mockReturnValue({ ...userAuth, isAdmin: true })

    render(<SessionsPage />)
    await userEvent.click(await screen.findByRole('button', { name: /delete/i }))

    expect(apiClient.delete).not.toHaveBeenCalled()
  })
})
