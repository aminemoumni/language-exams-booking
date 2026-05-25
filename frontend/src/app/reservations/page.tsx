'use client'

import { useCallback, useEffect, useState } from 'react'
import { apiClient } from '@/lib/axios'
import type { Reservation } from '@/types'
import ReservationCard from '@/components/ReservationCard'
import { PageSpinner } from '@/components/Spinner'

// ─── Helpers ──────────────────────────────────────────────────────────────────

function extractMessage(err: unknown, fallback = 'Something went wrong.'): string {
  if (err && typeof err === 'object' && 'response' in err) {
    const res = (err as { response?: { data?: { message?: string } } }).response
    if (res?.data?.message) return res.data.message
  }
  return fallback
}

// ─── Tabs ─────────────────────────────────────────────────────────────────────

type Tab = 'active' | 'all'

// ─── Component ────────────────────────────────────────────────────────────────

// NOTE: GET /api/reservations/me returns a plain Reservation[] (no pagination).
// The `active` query param controls whether only active bookings are returned.

export default function ReservationsPage() {
  const [reservations, setReservations] = useState<Reservation[]>([])
  const [tab,          setTab]          = useState<Tab>('active')
  const [pageLoading,  setPageLoading]  = useState(true)
  const [cancellingId, setCancellingId] = useState<string | null>(null)
  const [error,        setError]        = useState<string | null>(null)
  const [success,      setSuccess]      = useState<string | null>(null)

  // ─── Fetch ────────────────────────────────────────────────────────────────

  const fetchReservations = useCallback(async () => {
    setPageLoading(true)
    setError(null)
    try {
      // active=false → backend returns ALL reservations (active + cancelled)
      // active=true  → backend returns active reservations only (default)
      const params = tab === 'all' ? { active: 'false' } : {}
      const { data } = await apiClient.get<Reservation[]>('/reservations/me', { params })
      setReservations(Array.isArray(data) ? data : [])
    } catch (err) {
      setError(extractMessage(err, 'Failed to load reservations.'))
    } finally {
      setPageLoading(false)
    }
  }, [tab])

  useEffect(() => {
    fetchReservations()
  }, [fetchReservations])

  // ─── Cancel ───────────────────────────────────────────────────────────────

  async function handleCancel(reservation: Reservation) {
    if (!confirm('Cancel this booking? This cannot be undone.')) return
    setCancellingId(reservation.id)
    setError(null)
    setSuccess(null)
    try {
      await apiClient.delete(`/reservations/${reservation.id}`)
      setSuccess('Booking cancelled successfully.')
      // Update list optimistically
      if (tab === 'active') {
        setReservations((prev) => prev.filter((r) => r.id !== reservation.id))
      } else {
        setReservations((prev) =>
          prev.map((r) =>
            r.id === reservation.id
              ? { ...r, active: false, cancelledAt: new Date().toISOString() }
              : r,
          ),
        )
      }
    } catch (err) {
      setError(extractMessage(err, 'Could not cancel booking.'))
    } finally {
      setCancellingId(null)
    }
  }


  // ─── Render ───────────────────────────────────────────────────────────────

  return (
    <div className="page-container py-10">

      {/* ── Header ── */}
      <div className="mb-8">
        <h1 className="section-title">My Bookings</h1>
        <p className="mt-1 text-sm text-slate-500">
          Track and manage your language exam reservations.
        </p>
      </div>

      {/* ── Tab bar ── */}
      <div className="mb-6 flex gap-1 rounded-xl bg-slate-100 p-1 w-fit">
        {(['active', 'all'] as Tab[]).map((t) => (
          <button
            key={t}
            onClick={() => setTab(t)}
            className={`rounded-lg px-5 py-2 text-sm font-medium transition-all ${
              tab === t
                ? 'bg-white text-slate-900 shadow-sm'
                : 'text-slate-500 hover:text-slate-800'
            }`}
          >
            {t === 'active' ? 'Active bookings' : 'All bookings'}
          </button>
        ))}
      </div>

      {/* ── Alerts ── */}
      {error   && <div className="alert-error   mb-6">{error}</div>}
      {success && <div className="alert-success mb-6">{success}</div>}

      {/* ── Content ── */}
      {pageLoading ? (
        <PageSpinner />
      ) : reservations.length === 0 ? (
        <div className="flex flex-col items-center justify-center py-24 text-center">
          <span className="text-5xl mb-4">🗓️</span>
          <p className="text-lg font-semibold text-slate-700">No bookings yet</p>
          <p className="mt-1 text-sm text-slate-400">
            {tab === 'active'
              ? 'You have no active reservations.'
              : 'You have not made any reservations yet.'}
          </p>
          <a href="/sessions" className="btn btn-primary mt-6">
            Browse sessions
          </a>
        </div>
      ) : (
        <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          {reservations.map((r) => (
            <ReservationCard
              key={r.id}
              reservation={r}
              onCancel={handleCancel}
              loading={cancellingId === r.id}
            />
          ))}
        </div>
      )}

      {/* Pagination removed — /api/reservations/me returns a plain array,
          not a paginated response. All reservations are returned at once. */}
      {false && (
        <div className="mt-10 flex items-center justify-center gap-2">
          <button className="btn btn-outline btn-sm">
            ← Previous
          </button>
          <span className="text-sm text-slate-600" />
          <button className="btn btn-outline btn-sm">
            Next →
          </button>
        </div>
      )}
    </div>
  )
}
