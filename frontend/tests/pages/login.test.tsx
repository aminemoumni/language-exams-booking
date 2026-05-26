import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import LoginPage from '@/app/login/page'
import { useAuth } from '@/contexts/AuthContext'

// ─── Mocks ────────────────────────────────────────────────────────────────────

jest.mock('@/contexts/AuthContext', () => ({ useAuth: jest.fn() }))

jest.mock('next/navigation', () => ({
  useRouter: () => ({ push: jest.fn(), replace: jest.fn() }),
}))

// Dynamic import inside register handler also goes through Jest module registry
jest.mock('@/lib/axios', () => ({
  apiClient: { post: jest.fn() },
  TOKEN_KEY: 'auth_token',
}))

// ─── Fixture ──────────────────────────────────────────────────────────────────

const mockLogin = jest.fn()

const guestAuth = {
  user:       null,
  token:      null,
  isLoading:  false,
  isAdmin:    false,
  login:      mockLogin,
  logout:     jest.fn(),
  updateUser: jest.fn(),
}

// ─── Tests ────────────────────────────────────────────────────────────────────

describe('LoginPage', () => {
  beforeEach(() => {
    jest.clearAllMocks()
    ;(useAuth as jest.Mock).mockReturnValue(guestAuth)
  })

  it('renders the login form by default', () => {
    render(<LoginPage />)
    expect(screen.getByPlaceholderText(/you@example\.com/i)).toBeInTheDocument()
    expect(screen.getByPlaceholderText('••••••••')).toBeInTheDocument()
  })

  it('shows demo credentials for both roles', () => {
    render(<LoginPage />)
    expect(screen.getByText('admin@ets.com')).toBeInTheDocument()
    expect(screen.getByText('user@ets.com')).toBeInTheDocument()
  })

  it('calls login with email and password on form submit', async () => {
    mockLogin.mockResolvedValue(undefined)
    const { container } = render(<LoginPage />)

    await userEvent.type(screen.getByPlaceholderText(/you@example\.com/i), 'user@ets.com')
    await userEvent.type(screen.getByPlaceholderText('••••••••'), '0123456789')
    fireEvent.submit(container.querySelector('form')!)

    await waitFor(() =>
      expect(mockLogin).toHaveBeenCalledWith('user@ets.com', '0123456789'),
    )
  })

  it('shows the API error message when login fails', async () => {
    mockLogin.mockRejectedValue({
      response: { data: { message: 'Invalid credentials.' } },
    })
    const { container } = render(<LoginPage />)

    await userEvent.type(screen.getByPlaceholderText(/you@example\.com/i), 'wrong@email.com')
    await userEvent.type(screen.getByPlaceholderText('••••••••'), 'wrongpassword')
    fireEvent.submit(container.querySelector('form')!)

    expect(await screen.findByText('Invalid credentials.')).toBeInTheDocument()
  })

  it('switches to the register form when the "Create account" tab is clicked', async () => {
    render(<LoginPage />)
    // On login tab there is exactly one "Create account" button (the tab)
    await userEvent.click(screen.getByRole('button', { name: /create account/i }))
    expect(screen.getByPlaceholderText(/jane doe/i)).toBeInTheDocument()
  })

  it('shows a password-mismatch error on the register form', async () => {
    const { container } = render(<LoginPage />)
    await userEvent.click(screen.getByRole('button', { name: /create account/i }))

    await userEvent.type(screen.getByPlaceholderText(/jane doe/i),           'Test User')
    await userEvent.type(screen.getByPlaceholderText(/you@example\.com/i),   'test@test.com')
    await userEvent.type(screen.getByPlaceholderText(/min\. 8 characters/i), 'password123')
    await userEvent.type(screen.getByPlaceholderText('••••••••'),             'different456')
    fireEvent.submit(container.querySelector('form')!)

    expect(await screen.findByText(/do not match/i)).toBeInTheDocument()
  })

  it('renders nothing while auth is loading', () => {
    ;(useAuth as jest.Mock).mockReturnValue({ ...guestAuth, isLoading: true })
    const { container } = render(<LoginPage />)
    expect(container).toBeEmptyDOMElement()
  })

  it('renders nothing when the user is already authenticated', () => {
    ;(useAuth as jest.Mock).mockReturnValue({
      ...guestAuth,
      user: { id: 'u1', name: 'Alice', email: 'alice@test.com', createdAt: '2026-01-01' },
    })
    const { container } = render(<LoginPage />)
    expect(container).toBeEmptyDOMElement()
  })
})
