'use client'

import { useEffect, useState } from 'react'
import type { Session, SessionPayload } from '@/types'

// ─── Constants ────────────────────────────────────────────────────────────────

const LANGUAGES = [
  'English', 'French', 'Spanish', 'German', 'Italian',
  'Portuguese', 'Dutch', 'Arabic', 'Chinese', 'Japanese',
]

const DEFAULT_FORM: SessionPayload = {
  language:   'English',
  date:       '',
  time:       '09:00',
  location:   '',
  totalSeats: 20,
}

// ─── Props ────────────────────────────────────────────────────────────────────

interface SessionFormModalProps {
  open:      boolean
  session?:  Session | null   // null / undefined = create mode
  onClose:   () => void
  onSubmit:  (data: SessionPayload) => Promise<void>
  loading?:  boolean
  error?:    string | null
}

// ─── Component ────────────────────────────────────────────────────────────────

export default function SessionFormModal({
  open,
  session,
  onClose,
  onSubmit,
  loading = false,
  error   = null,
}: SessionFormModalProps) {
  const [form,       setForm]       = useState<SessionPayload>(DEFAULT_FORM)
  const [localError, setLocalError] = useState<string | null>(null)

  /* Reset / seed the form whenever the modal opens */
  useEffect(() => {
    if (!open) return
    setLocalError(null)
    if (session) {
      setForm({
        language:   session.language,
        date:       session.date.slice(0, 10),   // keep only "YYYY-MM-DD"
        time:       session.time,
        location:   session.location,
        totalSeats: session.totalSeats,
      })
    } else {
      setForm(DEFAULT_FORM)
    }
  }, [session, open])

  function set<K extends keyof SessionPayload>(key: K, value: SessionPayload[K]) {
    setForm((prev) => ({ ...prev, [key]: value }))
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    setLocalError(null)

    const now   = new Date()
    const today = now.toISOString().slice(0, 10) // "YYYY-MM-DD"

    // 1. Past date
    if (form.date && form.date < today) {
      setLocalError('The session date cannot be in the past.')
      return
    }

    // 2. Today's date with a past time
    if (form.date === today && form.time) {
      const [h, m]        = form.time.split(':').map(Number)
      const sessionTime   = new Date()
      sessionTime.setHours(h, m, 0, 0)
      if (sessionTime <= now) {
        setLocalError('The session time has already passed.')
        return
      }
    }

    await onSubmit(form)
  }

  if (!open) return null

  const isEdit   = Boolean(session)
  const now      = new Date()
  const today    = now.toISOString().slice(0, 10)
  // When today is selected, block times that have already passed
  const minTime  = form.date === today
    ? `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`
    : undefined

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">

      {/* Backdrop */}
      <div
        className="absolute inset-0 bg-slate-900/60 backdrop-blur-sm"
        onClick={onClose}
      />

      {/* Panel */}
      <div className="relative w-full max-w-lg animate-slide-up rounded-2xl bg-white shadow-2xl">

        {/* ── Header ── */}
        <div className="flex items-center justify-between border-b border-slate-100 px-6 py-5">
          <div>
            <h2 className="text-lg font-bold text-slate-900">
              {isEdit ? 'Edit session' : 'New session'}
            </h2>
            <p className="mt-0.5 text-sm text-slate-500">
              {isEdit
                ? 'Update the exam session details.'
                : 'Fill in the details to create a new exam session.'}
            </p>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="rounded-lg p-2 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700"
            aria-label="Close"
          >
            <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        {/* ── Form ── */}
        <form onSubmit={handleSubmit} className="space-y-4 px-6 py-5">

          {(localError || error) && (
            <div className="alert-error">{localError ?? error}</div>
          )}

          {/* Language */}
          <div>
            <label className="mb-1.5 block text-sm font-medium text-slate-700">
              Language
            </label>
            <select
              value={form.language}
              onChange={(e) => set('language', e.target.value)}
              className="input"
              required
            >
              {LANGUAGES.map((l) => (
                <option key={l} value={l}>{l}</option>
              ))}
            </select>
          </div>

          {/* Date & Time */}
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="mb-1.5 block text-sm font-medium text-slate-700">Date</label>
              <input
                type="date"
                value={form.date}
                min={today}
                onChange={(e) => set('date', e.target.value)}
                className="input"
                required
              />
            </div>
            <div>
              <label className="mb-1.5 block text-sm font-medium text-slate-700">Time</label>
              <input
                type="time"
                value={form.time}
                min={minTime}
                onChange={(e) => set('time', e.target.value)}
                className="input"
                required
              />
            </div>
          </div>

          {/* Location */}
          <div>
            <label className="mb-1.5 block text-sm font-medium text-slate-700">Location</label>
            <input
              type="text"
              value={form.location}
              onChange={(e) => set('location', e.target.value)}
              placeholder="e.g. Paris – Centre Diderot"
              className="input"
              required
            />
          </div>

          {/* Total seats */}
          <div>
            <label className="mb-1.5 block text-sm font-medium text-slate-700">
              Total seats
            </label>
            <input
              type="number"
              value={form.totalSeats}
              onChange={(e) => set('totalSeats', Number(e.target.value))}
              className="input"
              min={1}
              max={500}
              required
            />
          </div>

          {/* Actions */}
          <div className="flex items-center justify-end gap-3 pt-2">
            <button type="button" onClick={onClose} className="btn btn-outline">
              Cancel
            </button>
            <button type="submit" disabled={loading} className="btn btn-primary">
              {loading ? (
                <span className="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white" />
              ) : isEdit ? (
                'Save changes'
              ) : (
                'Create session'
              )}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}
