'use client'

import type { Reservation } from '@/types'
import { formatDate } from '@/lib/formatDate'

// ─── Helpers ──────────────────────────────────────────────────────────────────

const FLAG_MAP: Record<string, string> = {
  english:   '🇬🇧',
  french:    '🇫🇷',
  spanish:   '🇪🇸',
  german:    '🇩🇪',
  italian:   '🇮🇹',
  portuguese:'🇵🇹',
  dutch:     '🇳🇱',
  arabic:    '🇸🇦',
  chinese:   '🇨🇳',
  japanese:  '🇯🇵',
}

function getFlag(language: string) {
  return FLAG_MAP[language.toLowerCase()] ?? '🌐'
}



// ─── Props ────────────────────────────────────────────────────────────────────

interface ReservationCardProps {
  reservation: Reservation
  onCancel?:   (reservation: Reservation) => void
  loading?:    boolean
}

// ─── Component ────────────────────────────────────────────────────────────────

export default function ReservationCard({
  reservation,
  onCancel,
  loading = false,
}: ReservationCardProps) {
  const { session, active, reservedAt, cancelledAt } = reservation
  const isCancelled = !active

  return (
    <div className={`card-hover flex flex-col overflow-hidden animate-slide-up ${isCancelled ? 'opacity-60' : ''}`}>

      {/* ── Colour header strip ── */}
      <div
        className={`flex items-center gap-3 px-5 py-4 ${
          isCancelled
            ? 'bg-gradient-to-r from-slate-500 to-slate-400'
            : 'bg-gradient-to-r from-ets-blue to-ets-blue-mid'
        }`}
      >
        <span className="text-3xl">{getFlag(session.language)}</span>
        <div className="min-w-0 flex-1">
          <p className="font-bold text-white truncate">{session.language}</p>
          <p className="text-xs text-blue-200">Language exam</p>
        </div>
        <span
          className={`shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold ${
            isCancelled
              ? 'bg-slate-200 text-slate-700'
              : 'bg-emerald-400 text-emerald-900'
          }`}
        >
          {isCancelled ? '✕ Cancelled' : '✓ Booked'}
        </span>
      </div>

      {/* ── Body ── */}
      <div className="flex flex-1 flex-col gap-4 p-5">

        {/* Exam details */}
        <div className="grid grid-cols-2 gap-3 text-sm">
          <div className="flex items-start gap-2">
            <span className="mt-0.5 text-ets-orange">📅</span>
            <div>
              <p className="font-medium text-slate-800">{formatDate(session.date)}</p>
              <p className="text-xs text-slate-500">Exam date</p>
            </div>
          </div>
          <div className="flex items-start gap-2">
            <span className="mt-0.5 text-ets-orange">🕘</span>
            <div>
              <p className="font-medium text-slate-800">{session.time}</p>
              <p className="text-xs text-slate-500">Time</p>
            </div>
          </div>
          <div className="col-span-2 flex items-start gap-2">
            <span className="mt-0.5 text-ets-orange">📍</span>
            <div>
              <p className="font-medium text-slate-800">{session.location}</p>
              <p className="text-xs text-slate-500">Location</p>
            </div>
          </div>
        </div>

        {/* Booking meta */}
        <div className="rounded-lg bg-slate-50 px-4 py-3 text-xs text-slate-500 space-y-1">
          <p>
            Booked on{' '}
            <span className="font-medium text-slate-700">{formatDate(reservedAt)}</span>
          </p>
          {isCancelled && cancelledAt && (
            <p>
              Cancelled on{' '}
              <span className="font-medium text-red-600">{formatDate(cancelledAt)}</span>
            </p>
          )}
        </div>

        {/* Cancel action */}
        {!isCancelled && (
          <div className="mt-auto pt-1">
            <button
              onClick={() => onCancel?.(reservation)}
              disabled={loading}
              className="btn btn-danger-outline btn-sm w-full"
            >
              {loading ? (
                <span className="inline-block h-4 w-4 animate-spin rounded-full border-2 border-red-300 border-t-red-600" />
              ) : (
                'Cancel booking'
              )}
            </button>
          </div>
        )}
      </div>
    </div>
  )
}
