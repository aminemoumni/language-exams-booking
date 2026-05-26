import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import SessionCard from '@/components/SessionCard'
import type { Session } from '@/types'

// ─── Fixture ──────────────────────────────────────────────────────────────────

const session: Session = {
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

const fullSession: Session = { ...session, availableSeats: 0 }

// ─── Tests ────────────────────────────────────────────────────────────────────

describe('SessionCard', () => {
  describe('content rendering', () => {
    it('displays language, formatted date, time, and location', () => {
      render(<SessionCard session={session} />)
      expect(screen.getByText('English')).toBeInTheDocument()
      expect(screen.getByText('15 August 2027')).toBeInTheDocument()
      expect(screen.getByText('09:00')).toBeInTheDocument()
      expect(screen.getByText('Paris – Centre Diderot')).toBeInTheDocument()
    })

    it('shows available and total seat count', () => {
      render(<SessionCard session={session} />)
      expect(screen.getByText('15 seats left')).toBeInTheDocument()
      expect(screen.getByText('20 total')).toBeInTheDocument()
    })

    it('shows "✓ Booked" badge when isBooked is true', () => {
      render(<SessionCard session={session} isBooked />)
      expect(screen.getByText('✓ Booked')).toBeInTheDocument()
    })

    it('shows "Full" badge when availableSeats is 0', () => {
      render(<SessionCard session={fullSession} />)
      expect(screen.getByText('Full')).toBeInTheDocument()
    })
  })

  describe('regular user actions', () => {
    it('renders "Book this session" button for regular user', () => {
      render(<SessionCard session={session} />)
      expect(screen.getByRole('button', { name: /book this session/i })).toBeInTheDocument()
    })

    it('calls onBook with the session when the book button is clicked', async () => {
      const onBook = jest.fn()
      render(<SessionCard session={session} onBook={onBook} />)
      await userEvent.click(screen.getByRole('button', { name: /book this session/i }))
      expect(onBook).toHaveBeenCalledTimes(1)
      expect(onBook).toHaveBeenCalledWith(session)
    })

    it('disables the book button when loading is true', () => {
      render(<SessionCard session={session} loading />)
      expect(screen.getByRole('button')).toBeDisabled()
    })

    it('shows "Already booked" and disables button when isBooked', () => {
      render(<SessionCard session={session} isBooked />)
      expect(screen.getByRole('button', { name: /already booked/i })).toBeDisabled()
    })

    it('shows "No seats available" and disables button when session is full', () => {
      render(<SessionCard session={fullSession} />)
      expect(screen.getByRole('button', { name: /no seats available/i })).toBeDisabled()
    })
  })

  describe('admin actions', () => {
    const adminProps = { isAdmin: true, onEdit: jest.fn(), onDelete: jest.fn() }

    it('does not render a book button for admin', () => {
      render(<SessionCard session={session} {...adminProps} />)
      expect(screen.queryByRole('button', { name: /book/i })).not.toBeInTheDocument()
    })

    it('renders Edit and Delete buttons for admin', () => {
      render(<SessionCard session={session} {...adminProps} />)
      expect(screen.getByRole('button', { name: /edit/i })).toBeInTheDocument()
      expect(screen.getByRole('button', { name: /delete/i })).toBeInTheDocument()
    })

    it('calls onEdit with the session when Edit is clicked', async () => {
      const onEdit = jest.fn()
      render(<SessionCard session={session} isAdmin onEdit={onEdit} onDelete={jest.fn()} />)
      await userEvent.click(screen.getByRole('button', { name: /edit/i }))
      expect(onEdit).toHaveBeenCalledWith(session)
    })

    it('calls onDelete with the session when Delete is clicked', async () => {
      const onDelete = jest.fn()
      render(<SessionCard session={session} isAdmin onEdit={jest.fn()} onDelete={onDelete} />)
      await userEvent.click(screen.getByRole('button', { name: /delete/i }))
      expect(onDelete).toHaveBeenCalledWith(session)
    })
  })
})
