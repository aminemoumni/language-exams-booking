'use client'

import { useCallback, useEffect, useState } from 'react'
import { useAuth } from '@/contexts/AuthContext'
import { apiClient } from '@/lib/axios'
import { formatDate } from '@/lib/formatDate'
import type { Session, SessionPayload, Reservation, PaginatedResponse } from '@/types'
// PaginatedResponse is used for /sessions only; /reservations/me returns a plain array
import SessionCard from '@/components/SessionCard'
import SessionFormModal from '@/components/SessionFormModal'
import { PageSpinner } from '@/components/Spinner'

// ─── Helpers ──────────────────────────────────────────────────────────────────

function extractMessage(err: unknown, fallback = 'Something went wrong.'): string {
  if (err && typeof err === 'object' && 'response' in err) {
    const res = (err as { response?: { data?: { message?: string } } }).response
    if (res?.data?.message) return res.data.message
  }
  return fallback
}

// ─── Component ────────────────────────────────────────────────────────────────

const LIMIT = 9

export default function SessionsPage() {
  const { isAdmin } = useAuth()

  // ── Data state ─────────────────────────────────────────────────────────────
  const [sessions,     setSessions]     = useState<Session[]>([])
  const [bookedIds,    setBookedIds]    = useState<Set<string>>(new Set())
  const [totalPages,   setTotalPages]   = useState(1)
  const [page,         setPage]         = useState(1)
  const [pageLoading,  setPageLoading]  = useState(true)

  // ── Action state ───────────────────────────────────────────────────────────
  const [bookingId,    setBookingId]    = useState<string | null>(null)
  const [actionError,  setActionError]  = useState<string | null>(null)

  // ── Modal state (admin) ────────────────────────────────────────────────────
  const [modalOpen,    setModalOpen]    = useState(false)
  const [editSession,  setEditSession]  = useState<Session | null>(null)
  const [modalLoading, setModalLoading] = useState(false)
  const [modalError,   setModalError]   = useState<string | null>(null)

  // ── Search / filter ────────────────────────────────────────────────────────
  const [search,   setSearch]   = useState('')
  const [language, setLanguage] = useState('')

  // ─── Fetch sessions ────────────────────────────────────────────────────────

  const fetchSessions = useCallback(async (p: number) => {
    setPageLoading(true)
    setActionError(null)
    try {
      const params: Record<string, string | number> = { page: p, limit: LIMIT }
      if (language) params.language = language
      if (search)   params.location = search
      const { data } = await apiClient.get<PaginatedResponse<Session>>('/sessions', { params })
      setSessions(data.data)
      setTotalPages(data.totalPages)
    } catch (err) {
      setActionError(extractMessage(err, 'Failed to load sessions.'))
    } finally {
      setPageLoading(false)
    }
  }, [language, search])

  // ─── Fetch booked IDs (user only) ─────────────────────────────────────────

  const fetchBookedIds = useCallback(async () => {
    if (isAdmin) return
    try {
      // active=true is explicit — never rely on the backend default
      const { data } = await apiClient.get<Reservation[]>('/reservations/me', { params: { active: 'true' } })
      setBookedIds(new Set(
        (Array.isArray(data) ? data : [])
          .filter((r) => r.active)
          .map((r) => r.session.id),
      ))
    } catch {
      // non-critical — silently ignore
    }
  }, [isAdmin])

  useEffect(() => {
    fetchSessions(page)
  }, [fetchSessions, page])

  useEffect(() => {
    fetchBookedIds()
  }, [fetchBookedIds])

  // Reset to page 1 when filter/search changes
  useEffect(() => {
    setPage(1)
  }, [language, search])

  // ─── Book ──────────────────────────────────────────────────────────────────

  async function handleBook(session: Session) {
    setBookingId(session.id)
    setActionError(null)
    try {
      await apiClient.post('/reservations', { sessionId: session.id })
      setBookedIds((prev) => new Set([...prev, session.id]))
      // Optimistically decrement
      setSessions((prev) =>
        prev.map((s) =>
          s.id === session.id
            ? { ...s, availableSeats: s.availableSeats - 1 }
            : s,
        ),
      )
    } catch (err) {
      setActionError(extractMessage(err, 'Booking failed.'))
    } finally {
      setBookingId(null)
    }
  }

  // ─── Admin: Delete ─────────────────────────────────────────────────────────

  async function handleDelete(session: Session) {
    if (!confirm(`Delete the ${session.language} session on ${formatDate(session.date)}?`)) return
    setActionError(null)
    try {
      await apiClient.delete(`/sessions/${session.id}`)
      setSessions((prev) => prev.filter((s) => s.id !== session.id))
    } catch (err) {
      setActionError(extractMessage(err, 'Delete failed.'))
    }
  }

  // ─── Admin: Open modal ─────────────────────────────────────────────────────

  function openCreate() {
    setEditSession(null)
    setModalError(null)
    setModalOpen(true)
  }

  function openEdit(session: Session) {
    setEditSession(session)
    setModalError(null)
    setModalOpen(true)
  }

  // ─── Admin: Submit (create / edit) ─────────────────────────────────────────

  async function handleModalSubmit(payload: SessionPayload) {
    setModalLoading(true)
    setModalError(null)
    try {
      if (editSession) {
        await apiClient.put(`/sessions/${editSession.id}`, payload)
      } else {
        await apiClient.post('/sessions', payload)
      }
      setModalOpen(false)
      await fetchSessions(page)
    } catch (err) {
      setModalError(extractMessage(err, 'Could not save session.'))
    } finally {
      setModalLoading(false)
    }
  }

  // ─── Render ────────────────────────────────────────────────────────────────

  const LANGUAGES = [
    'English', 'French', 'Spanish', 'German', 'Italian',
    'Portuguese', 'Dutch', 'Arabic', 'Chinese', 'Japanese',
  ]

  return (
    <div className="page-container py-10">

      {/* ── Page header ── */}
      <div className="mb-8 flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="section-title">Exam Sessions</h1>
          <p className="mt-1 text-sm text-slate-500">
            {isAdmin
              ? 'Manage all available language exam sessions.'
              : 'Find and book your next language exam.'}
          </p>
        </div>
        {isAdmin && (
          <button onClick={openCreate} className="btn btn-primary">
            + New session
          </button>
        )}
      </div>

      {/* ── Filters ── */}
      <div className="mb-8 flex flex-wrap gap-3">
        <input
          type="text"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder="Search by location…"
          className="input max-w-xs"
        />
        <select
          value={language}
          onChange={(e) => setLanguage(e.target.value)}
          className="input max-w-[180px]"
        >
          <option value="">All languages</option>
          {LANGUAGES.map((l) => (
            <option key={l} value={l}>{l}</option>
          ))}
        </select>
        {(search || language) && (
          <button
            onClick={() => { setSearch(''); setLanguage('') }}
            className="btn btn-outline btn-sm self-center"
          >
            Clear filters
          </button>
        )}
      </div>

      {/* ── Global error ── */}
      {actionError && (
        <div className="alert-error mb-6">{actionError}</div>
      )}

      {/* ── Grid / states ── */}
      {pageLoading ? (
        <PageSpinner />
      ) : sessions.length === 0 ? (
        <div className="flex flex-col items-center justify-center py-24 text-center">
          <span className="text-5xl mb-4">📋</span>
          <p className="text-lg font-semibold text-slate-700">No sessions found</p>
          <p className="mt-1 text-sm text-slate-400">
            {search || language
              ? 'Try adjusting your filters.'
              : 'Check back soon for upcoming exam sessions.'}
          </p>
        </div>
      ) : (
        <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          {sessions.map((session) => (
            <SessionCard
              key={session.id}
              session={session}
              isBooked={bookedIds.has(session.id)}
              isAdmin={isAdmin}
              loading={bookingId === session.id}
              onBook={handleBook}
              onEdit={openEdit}
              onDelete={handleDelete}
            />
          ))}
        </div>
      )}

      {/* ── Pagination ── */}
      {!pageLoading && totalPages > 1 && (
        <div className="mt-10 flex items-center justify-center gap-2">
          <button
            onClick={() => setPage((p) => Math.max(1, p - 1))}
            disabled={page === 1}
            className="btn btn-outline btn-sm disabled:opacity-40"
          >
            ← Previous
          </button>
          <span className="text-sm text-slate-600">
            Page {page} of {totalPages}
          </span>
          <button
            onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
            disabled={page === totalPages}
            className="btn btn-outline btn-sm disabled:opacity-40"
          >
            Next →
          </button>
        </div>
      )}

      {/* ── Admin modal ── */}
      <SessionFormModal
        open={modalOpen}
        session={editSession}
        onClose={() => setModalOpen(false)}
        onSubmit={handleModalSubmit}
        loading={modalLoading}
        error={modalError}
      />
    </div>
  )
}
