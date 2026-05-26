import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import SessionFormModal from '@/components/SessionFormModal'
import type { Session } from '@/types'

// ─── Fixtures ─────────────────────────────────────────────────────────────────

// A date clearly in the future so "edit mode" tests never hit the past-date guard
const FUTURE_DATE = '2030-06-15'

const editSession: Session = {
  id: 's1',
  language: 'Spanish',
  date: FUTURE_DATE,
  time: '10:00',
  location: 'Madrid – Centro Cervantes',
  totalSeats: 25,
  availableSeats: 10,
  active: true,
  createdAt: '2026-01-01T00:00:00+00:00',
}

const baseProps = {
  open: true,
  onClose: jest.fn(),
  onSubmit: jest.fn(),
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

/** Grab the date/time/number inputs by their HTML type attribute */
function getDateInput(container: HTMLElement) {
  return container.querySelector('input[type="date"]') as HTMLInputElement
}
function getTimeInput(container: HTMLElement) {
  return container.querySelector('input[type="time"]') as HTMLInputElement
}
function getTextInput(container: HTMLElement) {
  // The first text input in the form is the location field
  return container.querySelector('input[type="text"]') as HTMLInputElement
}

// ─── Tests ────────────────────────────────────────────────────────────────────

describe('SessionFormModal', () => {
  beforeEach(() => jest.clearAllMocks())

  it('renders nothing when open is false', () => {
    const { container } = render(<SessionFormModal {...baseProps} open={false} />)
    expect(container).toBeEmptyDOMElement()
  })

  // ── Create mode ────────────────────────────────────────────────────────────

  describe('create mode', () => {
    it('shows "New session" title and "Create session" submit button', () => {
      render(<SessionFormModal {...baseProps} />)
      expect(screen.getByText('New session')).toBeInTheDocument()
      expect(screen.getByRole('button', { name: /create session/i })).toBeInTheDocument()
    })

    it('rejects a past date without calling onSubmit', async () => {
      const onSubmit = jest.fn()
      const { container } = render(<SessionFormModal {...baseProps} onSubmit={onSubmit} />)

      fireEvent.change(getDateInput(container),  { target: { value: '2020-01-01' } })
      fireEvent.change(getTextInput(container),  { target: { value: 'Any Location' } })
      await userEvent.click(screen.getByRole('button', { name: /create session/i }))

      expect(await screen.findByText(/cannot be in the past/i)).toBeInTheDocument()
      expect(onSubmit).not.toHaveBeenCalled()
    })

    it("rejects today's date with a past time without calling onSubmit", async () => {
      const onSubmit = jest.fn()
      const { container } = render(<SessionFormModal {...baseProps} onSubmit={onSubmit} />)

      const today = new Date().toISOString().slice(0, 10)
      fireEvent.change(getDateInput(container),  { target: { value: today } })
      fireEvent.change(getTimeInput(container),  { target: { value: '00:01' } }) // always past
      fireEvent.change(getTextInput(container),  { target: { value: 'Any Location' } })
      await userEvent.click(screen.getByRole('button', { name: /create session/i }))

      expect(await screen.findByText(/already passed/i)).toBeInTheDocument()
      expect(onSubmit).not.toHaveBeenCalled()
    })

    it('calls onSubmit with the correct payload for a valid future date', async () => {
      const onSubmit = jest.fn().mockResolvedValue(undefined)
      const { container } = render(<SessionFormModal {...baseProps} onSubmit={onSubmit} />)

      fireEvent.change(getDateInput(container),  { target: { value: FUTURE_DATE } })
      fireEvent.change(getTimeInput(container),  { target: { value: '14:30' } })
      fireEvent.change(getTextInput(container),  { target: { value: 'Berlin – Zentrum' } })
      await userEvent.click(screen.getByRole('button', { name: /create session/i }))

      await waitFor(() =>
        expect(onSubmit).toHaveBeenCalledWith(
          expect.objectContaining({ date: FUTURE_DATE, time: '14:30', location: 'Berlin – Zentrum' }),
        ),
      )
    })
  })

  // ── Edit mode ──────────────────────────────────────────────────────────────

  describe('edit mode', () => {
    it('shows "Edit session" title and "Save changes" submit button', () => {
      render(<SessionFormModal {...baseProps} session={editSession} />)
      expect(screen.getByText('Edit session')).toBeInTheDocument()
      expect(screen.getByRole('button', { name: /save changes/i })).toBeInTheDocument()
    })

    it('pre-fills the form with the existing session values', () => {
      render(<SessionFormModal {...baseProps} session={editSession} />)
      expect(screen.getByDisplayValue('Spanish')).toBeInTheDocument()
      expect(screen.getByDisplayValue(FUTURE_DATE)).toBeInTheDocument()
      expect(screen.getByDisplayValue('Madrid – Centro Cervantes')).toBeInTheDocument()
      expect(screen.getByDisplayValue('25')).toBeInTheDocument() // totalSeats
    })
  })

  // ── Interactions ───────────────────────────────────────────────────────────

  describe('modal interactions', () => {
    it('calls onClose when the X button is clicked', async () => {
      const onClose = jest.fn()
      render(<SessionFormModal {...baseProps} onClose={onClose} />)
      await userEvent.click(screen.getByRole('button', { name: /close/i }))
      expect(onClose).toHaveBeenCalled()
    })

    it('calls onClose when the Cancel button is clicked', async () => {
      const onClose = jest.fn()
      render(<SessionFormModal {...baseProps} onClose={onClose} />)
      await userEvent.click(screen.getByRole('button', { name: /^cancel$/i }))
      expect(onClose).toHaveBeenCalled()
    })

    it('displays the API error passed via the error prop', () => {
      render(<SessionFormModal {...baseProps} error="Server error occurred." />)
      expect(screen.getByText('Server error occurred.')).toBeInTheDocument()
    })

    it('disables the submit button while loading', () => {
      const { container } = render(<SessionFormModal {...baseProps} loading />)
      // When loading=true the button shows a spinner instead of text, so its
      // accessible name is "". Query by type attribute to stay unambiguous.
      expect(container.querySelector('button[type="submit"]')).toBeDisabled()
    })
  })
})
