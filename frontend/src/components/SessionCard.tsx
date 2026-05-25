'use client'

import type { Session } from '@/types'
import { formatDate } from '@/lib/formatDate'

// ─── Language flag map ────────────────────────────────────────────────────────

const FLAG_MAP: Record<string, string> = {
  english:  '🇬🇧',
  french:   '🇫🇷',
  spanish:  '🇪🇸',
  german:   '🇩🇪',
  italian:  '🇮🇹',
  portuguese:'🇵🇹',
  dutch:    '🇳🇱',
  arabic:   '🇸🇦',
  chinese:  '🇨🇳',
  japanese: '🇯🇵',
}

function getFlag(language: string) {
  return FLAG_MAP[language.toLowerCase()] ?? '🌐'
}

// ─── Seat bar ─────────────────────────────────────────────────────────────────

function SeatBar({ available, total }: { available: number; total: number }) {
  const pct = total > 0 ? Math.round((available / total) * 100) : 0
  const color = pct > 50 ? 'bg-emerald-500' : pct > 20 ? 'bg-amber-500' : 'bg-red-500'

  return (
    <div className="space-y-1">
      <div className="flex justify-between text-xs text-slate-500">
        <span>{available} seats left</span>
        <span>{total} total</span>
      </div>
      <div className="h-1.5 w-full overflow-hidden rounded-full bg-slate-200">
        <div className={`h-full rounded-full transition-all ${color}`} style={{ width: `${pct}%` }} />
      </div>
    </div>
  )
}

// ─── Props ────────────────────────────────────────────────────────────────────

interface SessionCardProps {
  session:    Session
  isBooked?:  boolean
  onBook?:    (session: Session) => void
  onEdit?:    (session: Session) => void
  onDelete?:  (session: Session) => void
  isAdmin?:   boolean
  loading?:   boolean
}

export default function SessionCard({
  session,
  isBooked  = false,
  onBook,
  onEdit,
  onDelete,
  isAdmin   = false,
  loading   = false,
}: SessionCardProps) {
  const isFull = session.availableSeats === 0

  return (
    <div className="card-hover flex flex-col overflow-hidden animate-slide-up">

      {/* ── Colour header strip ── */}
      <div className="flex items-center gap-3 bg-gradient-to-r from-ets-blue to-ets-blue-mid px-5 py-4">
        <span className="text-3xl">{getFlag(session.language)}</span>
        <div className="min-w-0">
          <p className="font-bold text-white truncate">{session.language}</p>
          <p className="text-xs text-blue-200">Language exam</p>
        </div>
        {isBooked && (
          <span className="ml-auto shrink-0 rounded-full bg-emerald-400 px-2.5 py-0.5 text-xs font-semibold text-emerald-900">
            ✓ Booked
          </span>
        )}
        {isFull && !isBooked && (
          <span className="ml-auto shrink-0 badge-red">Full</span>
        )}
      </div>

      {/* ── Body ── */}
      <div className="flex flex-1 flex-col gap-4 p-5">

        {/* Details grid */}
        <div className="grid grid-cols-2 gap-3 text-sm">
          <div className="flex items-start gap-2">
            <span className="mt-0.5 text-ets-orange">📅</span>
            <div>
              <p className="font-medium text-slate-800">{formatDate(session.date)}</p>
              <p className="text-xs text-slate-500">Date</p>
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

        {/* Seat bar */}
        <SeatBar available={session.availableSeats} total={session.totalSeats} />

        {/* Actions */}
        <div className="mt-auto flex items-center gap-2 pt-1">
          {!isAdmin && (
            <button
              onClick={() => onBook?.(session)}
              disabled={loading || isFull || isBooked}
              className={`btn flex-1 ${
                isBooked
                  ? 'btn-outline cursor-default opacity-60'
                  : isFull
                  ? 'btn-outline cursor-not-allowed opacity-60'
                  : 'btn-primary'
              }`}
            >
              {loading ? (
                <span className="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white" />
              ) : isBooked ? (
                'Already booked'
              ) : isFull ? (
                'No seats available'
              ) : (
                'Book this session'
              )}
            </button>
          )}

          {isAdmin && (
            <>
              <button
                onClick={() => onEdit?.(session)}
                className="btn btn-outline btn-sm flex-1"
              >
                ✏️ Edit
              </button>
              <button
                onClick={() => onDelete?.(session)}
                className="btn btn-danger-outline btn-sm flex-1"
              >
                🗑️ Delete
              </button>
            </>
          )}
        </div>
      </div>
    </div>
  )
}
