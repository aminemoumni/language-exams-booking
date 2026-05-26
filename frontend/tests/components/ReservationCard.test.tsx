import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import ReservationCard from '@/components/ReservationCard'
import type { Reservation } from '@/types'

// ─── Fixtures ─────────────────────────────────────────────────────────────────

const activeReservation: Reservation = {
  id: 'r1',
  session: {
    id: 's1',
    language: 'French',
    date: '2027-09-10',
    time: '14:00',
    location: 'Lyon – Centre Paul Bocuse',
    totalSeats: 30,
    availableSeats: 10,
    active: true,
    createdAt: '2026-01-01T00:00:00+00:00',
  },
  active: true,
  reservedAt: '2026-05-20T10:00:00+00:00',
}

const cancelledReservation: Reservation = {
  ...activeReservation,
  id: 'r2',
  active: false,
  cancelledAt: '2026-05-21T09:00:00+00:00',
}

// ─── Tests ────────────────────────────────────────────────────────────────────

describe('ReservationCard', () => {
  describe('active reservation', () => {
    it('renders session language, formatted date, time, and location', () => {
      render(<ReservationCard reservation={activeReservation} />)
      expect(screen.getByText('French')).toBeInTheDocument()
      expect(screen.getByText('10 September 2027')).toBeInTheDocument()
      expect(screen.getByText('14:00')).toBeInTheDocument()
      expect(screen.getByText('Lyon – Centre Paul Bocuse')).toBeInTheDocument()
    })

    it('shows "✓ Booked" badge', () => {
      render(<ReservationCard reservation={activeReservation} />)
      expect(screen.getByText('✓ Booked')).toBeInTheDocument()
    })

    it('shows "Booked on" with the formatted reservation date', () => {
      render(<ReservationCard reservation={activeReservation} />)
      expect(screen.getByText(/booked on/i)).toBeInTheDocument()
      expect(screen.getByText('20 May 2026')).toBeInTheDocument()
    })

    it('renders the "Cancel booking" button', () => {
      render(<ReservationCard reservation={activeReservation} />)
      expect(screen.getByRole('button', { name: /cancel booking/i })).toBeInTheDocument()
    })

    it('calls onCancel with the reservation when cancel is clicked', async () => {
      const onCancel = jest.fn()
      render(<ReservationCard reservation={activeReservation} onCancel={onCancel} />)
      await userEvent.click(screen.getByRole('button', { name: /cancel booking/i }))
      expect(onCancel).toHaveBeenCalledTimes(1)
      expect(onCancel).toHaveBeenCalledWith(activeReservation)
    })

    it('disables the cancel button when loading is true', () => {
      render(<ReservationCard reservation={activeReservation} loading />)
      expect(screen.getByRole('button')).toBeDisabled()
    })
  })

  describe('cancelled reservation', () => {
    it('shows "✕ Cancelled" badge', () => {
      render(<ReservationCard reservation={cancelledReservation} />)
      expect(screen.getByText('✕ Cancelled')).toBeInTheDocument()
    })

    it('does not render the cancel button', () => {
      render(<ReservationCard reservation={cancelledReservation} />)
      expect(screen.queryByRole('button', { name: /cancel/i })).not.toBeInTheDocument()
    })

    it('shows "Cancelled on" with the formatted cancellation date', () => {
      render(<ReservationCard reservation={cancelledReservation} />)
      expect(screen.getByText(/cancelled on/i)).toBeInTheDocument()
      expect(screen.getByText('21 May 2026')).toBeInTheDocument()
    })
  })
})
