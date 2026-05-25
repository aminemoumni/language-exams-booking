'use client'

import { useEffect, useState } from 'react'
import { useAuth } from '@/contexts/AuthContext'
import { apiClient } from '@/lib/axios'
import { PageSpinner } from '@/components/Spinner'
import { formatDate } from '@/lib/formatDate'

// ─── Helpers ──────────────────────────────────────────────────────────────────

function extractMessage(err: unknown, fallback = 'Something went wrong.'): string {
  if (err && typeof err === 'object' && 'response' in err) {
    const res = (err as { response?: { data?: { message?: string } } }).response
    if (res?.data?.message) return res.data.message
  }
  return fallback
}

// ─── Component ────────────────────────────────────────────────────────────────

export default function AccountPage() {
  const { user, isLoading, updateUser } = useAuth()

  const [name,    setName]    = useState('')
  const [email,   setEmail]   = useState('')
  const [loading, setLoading] = useState(false)
  const [error,   setError]   = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)
  const [dirty,   setDirty]   = useState(false)

  // Seed form from auth context
  useEffect(() => {
    if (user) {
      setName(user.name)
      setEmail(user.email)
    }
  }, [user])

  // Track unsaved changes
  useEffect(() => {
    if (!user) return
    setDirty(name !== user.name || email !== user.email)
  }, [name, email, user])

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    setLoading(true)
    setError(null)
    setSuccess(null)
    try {
      await apiClient.put('/me', { name, email })
      updateUser({ name, email })
      setSuccess('Profile updated successfully.')
      setDirty(false)
    } catch (err) {
      setError(extractMessage(err, 'Could not update profile.'))
    } finally {
      setLoading(false)
    }
  }

  function handleReset() {
    if (!user) return
    setName(user.name)
    setEmail(user.email)
    setError(null)
    setSuccess(null)
  }

  if (isLoading || !user) return <PageSpinner />

  return (
    <div className="page-container py-10">

      {/* ── Page header ── */}
      <div className="mb-8">
        <h1 className="section-title">My Account</h1>
        <p className="mt-1 text-sm text-slate-500">
          Manage your personal information.
        </p>
      </div>

      <div className="mx-auto max-w-2xl space-y-6">

        {/* ── Profile card ── */}
        <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">

          {/* Avatar strip */}
          <div className="flex items-center gap-5 bg-gradient-to-r from-ets-blue to-ets-blue-mid px-6 py-6">
            <div className="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-ets-orange text-2xl font-bold text-white uppercase shadow">
              {user.name.charAt(0)}
            </div>
            <div className="min-w-0">
              <p className="truncate text-lg font-bold text-white">{user.name}</p>
              <p className="truncate text-sm text-blue-200">{user.email}</p>
              <p className="mt-1 text-xs text-blue-300">
                Member since {formatDate(user.createdAt)}
              </p>
            </div>
          </div>

          {/* Form */}
          <form onSubmit={handleSubmit} className="space-y-5 px-6 py-6">

            {error   && <div className="alert-error">{error}</div>}
            {success && <div className="alert-success">{success}</div>}

            <div>
              <label className="mb-1.5 block text-sm font-medium text-slate-700">
                Full name
              </label>
              <input
                type="text"
                value={name}
                onChange={(e) => setName(e.target.value)}
                className="input"
                placeholder="Jane Doe"
                required
              />
            </div>

            <div>
              <label className="mb-1.5 block text-sm font-medium text-slate-700">
                Email address
              </label>
              <input
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                className="input"
                placeholder="you@example.com"
                required
              />
            </div>

            <div className="flex items-center gap-3 pt-1">
              <button
                type="submit"
                disabled={loading || !dirty}
                className="btn btn-primary disabled:opacity-50"
              >
                {loading ? (
                  <span className="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white" />
                ) : (
                  'Save changes'
                )}
              </button>
              {dirty && (
                <button
                  type="button"
                  onClick={handleReset}
                  className="btn btn-outline"
                >
                  Discard
                </button>
              )}
            </div>
          </form>
        </div>

        {/* ── Info card ── */}
        <div className="rounded-2xl border border-slate-200 bg-white px-6 py-5 shadow-sm">
          <h2 className="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">
            Account details
          </h2>
          <dl className="space-y-3 text-sm">
            <div className="flex justify-between">
              <dt className="text-slate-500">Account ID</dt>
              <dd className="font-mono text-xs text-slate-700 truncate max-w-[200px]">{user.id}</dd>
            </div>
            <div className="flex justify-between">
              <dt className="text-slate-500">Member since</dt>
              <dd className="text-slate-700">{formatDate(user.createdAt)}</dd>
            </div>
          </dl>
        </div>

      </div>
    </div>
  )
}
