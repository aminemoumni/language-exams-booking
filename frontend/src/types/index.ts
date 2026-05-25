// ─── User ─────────────────────────────────────────────────────────────────────

export interface User {
  id: string
  name: string
  email: string
  createdAt: string
}

export interface UpdateUserPayload {
  name?: string
  email?: string
}

// ─── Session ──────────────────────────────────────────────────────────────────

export interface Session {
  id: string
  language: string
  date: string          // "YYYY-MM-DD" — serialized with DateTimeNormalizer FORMAT_KEY 'Y-m-d'
  time: string          // "HH:MM"
  location: string
  totalSeats: number
  availableSeats: number
  active: boolean
  createdAt: string     // ISO-8601 datetime e.g. "2025-06-15T00:00:00+00:00"
}

export interface SessionPayload {
  language: string
  date: string          // "YYYY-MM-DD"
  time: string          // "HH:MM"
  location: string
  totalSeats: number
}

// ─── Reservation ──────────────────────────────────────────────────────────────

export interface Reservation {
  id: string
  session: Session      // fully hydrated — backend initializes proxy before serialization
  active: boolean
  reservedAt: string    // ISO-8601 datetime e.g. "2025-06-15T09:00:00+00:00"
  cancelledAt?: string  // ISO-8601 datetime — present only when active=false
}

// ─── API responses ────────────────────────────────────────────────────────────

export interface PaginatedResponse<T> {
  data: T[]
  total: number
  page: number
  limit: number
  totalPages: number
}

export interface ApiError {
  message: string
  errors?: Record<string, string[]>
}

export interface LoginResponse {
  token: string
}
