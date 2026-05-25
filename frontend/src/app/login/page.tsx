'use client'

// NOTE: we intentionally do NOT use useSearchParams() here.
// useSearchParams() inside a Suspense boundary causes Next.js to render the
// Suspense fallback on the server and the real component on the client,
// producing a hydration mismatch. Instead we read the ?from= param with
// window.location.search inside the redirect useEffect (client-only, safe).

import { useState, useEffect } from 'react'
import { useRouter } from 'next/navigation'
import { useAuth } from '@/contexts/AuthContext'

// ─── Helpers ──────────────────────────────────────────────────────────────────

function extractMessage(err: unknown): string {
  if (err && typeof err === 'object' && 'response' in err) {
    const res = (err as { response?: { data?: { message?: string } } }).response
    if (res?.data?.message) return res.data.message
  }
  return 'An unexpected error occurred. Please try again.'
}

// ─── Tabs ─────────────────────────────────────────────────────────────────────

type Tab = 'login' | 'register'

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function LoginPage() {
  const { login, user, isLoading } = useAuth()
  const router = useRouter()

  const [tab,      setTab]      = useState<Tab>('login')
  const [loading,  setLoading]  = useState(false)
  const [error,    setError]    = useState<string | null>(null)
  const [success,  setSuccess]  = useState<string | null>(null)

  // Login fields
  const [loginEmail,    setLoginEmail]    = useState('')
  const [loginPassword, setLoginPassword] = useState('')

  // Register fields
  const [regName,     setRegName]     = useState('')
  const [regEmail,    setRegEmail]    = useState('')
  const [regPassword, setRegPassword] = useState('')
  const [regConfirm,  setRegConfirm]  = useState('')

  // Redirect if already authenticated — read ?from= here (client-only)
  useEffect(() => {
    if (!isLoading && user) {
      const params = new URLSearchParams(window.location.search)
      const from   = params.get('from') ?? '/sessions'
      router.replace(from)
    }
  }, [user, isLoading, router])

  function resetState() {
    setError(null)
    setSuccess(null)
  }

  // ── Login submit ────────────────────────────────────────────────────────────

  async function handleLogin(e: React.FormEvent) {
    e.preventDefault()
    resetState()
    setLoading(true)
    try {
      await login(loginEmail, loginPassword)
      // AuthContext updates `user` → useEffect above handles redirect
    } catch (err) {
      setError(extractMessage(err))
    } finally {
      setLoading(false)
    }
  }

  // ── Register submit ─────────────────────────────────────────────────────────

  async function handleRegister(e: React.FormEvent) {
    e.preventDefault()
    resetState()

    if (regPassword !== regConfirm) {
      setError('Passwords do not match.')
      return
    }
    if (regPassword.length < 8) {
      setError('Password must be at least 8 characters.')
      return
    }

    setLoading(true)
    try {
      const { apiClient } = await import('@/lib/axios')
      await apiClient.post('/auth/register', {
        name:     regName,
        email:    regEmail,
        password: regPassword,
      })
      setSuccess('Account created! You can now sign in.')
      setTab('login')
      setLoginEmail(regEmail)
      setRegName('')
      setRegEmail('')
      setRegPassword('')
      setRegConfirm('')
    } catch (err) {
      setError(extractMessage(err))
    } finally {
      setLoading(false)
    }
  }

  // While auth is being restored or a redirect is in progress, render nothing
  // (same on server and client → no hydration mismatch)
  if (isLoading || user) return null

  return (
    <div className="flex min-h-screen items-center justify-center bg-gradient-to-br from-ets-blue via-ets-blue-mid to-slate-700 px-4 py-12">

      {/* Card */}
      <div className="w-full max-w-md animate-slide-up">

        {/* Logo */}
        <div className="mb-8 flex flex-col items-center gap-3">
          <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-ets-orange shadow-lg">
            <span className="text-2xl font-black text-white tracking-tight">E</span>
          </div>
          <div className="text-center">
            <p className="text-lg font-bold uppercase tracking-widest text-white">ETS Global</p>
            <p className="text-xs uppercase tracking-wider text-blue-300">Language Exams · EMEA</p>
          </div>
        </div>

        {/* Panel */}
        <div className="overflow-hidden rounded-2xl bg-white shadow-2xl">

          {/* Tab switcher */}
          <div className="grid grid-cols-2 border-b border-slate-100">
            {(['login', 'register'] as Tab[]).map((t) => (
              <button
                key={t}
                onClick={() => { setTab(t); resetState() }}
                className={`py-4 text-sm font-semibold transition-colors ${
                  tab === t
                    ? 'border-b-2 border-ets-orange text-ets-orange'
                    : 'text-slate-500 hover:text-slate-800'
                }`}
              >
                {t === 'login' ? 'Sign in' : 'Create account'}
              </button>
            ))}
          </div>

          <div className="px-8 py-8">

            {/* Alerts */}
            {error   && <div className="alert-error   mb-5">{error}</div>}
            {success && <div className="alert-success mb-5">{success}</div>}

            {/* ── Login form ── */}
            {tab === 'login' && (
              <form onSubmit={handleLogin} className="space-y-5">
                <div>
                  <label className="mb-1.5 block text-sm font-medium text-slate-700">
                    Email address
                  </label>
                  <input
                    type="email"
                    value={loginEmail}
                    onChange={(e) => setLoginEmail(e.target.value)}
                    className="input"
                    placeholder="you@example.com"
                    autoComplete="email"
                    required
                  />
                </div>
                <div>
                  <label className="mb-1.5 block text-sm font-medium text-slate-700">
                    Password
                  </label>
                  <input
                    type="password"
                    value={loginPassword}
                    onChange={(e) => setLoginPassword(e.target.value)}
                    className="input"
                    placeholder="••••••••"
                    autoComplete="current-password"
                    required
                  />
                </div>
                <button
                  type="submit"
                  disabled={loading}
                  className="btn btn-primary w-full"
                >
                  {loading ? (
                    <span className="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white" />
                  ) : (
                    'Sign in'
                  )}
                </button>

                <p className="text-center text-xs text-slate-500">
                  Demo — admin:{' '}
                  <span className="font-mono text-slate-700">admin@ets.com</span>
                  {' '}/{' '}
                  <span className="font-mono text-slate-700">Admin1234!</span>
                </p>
              </form>
            )}

            {/* ── Register form ── */}
            {tab === 'register' && (
              <form onSubmit={handleRegister} className="space-y-5">
                <div>
                  <label className="mb-1.5 block text-sm font-medium text-slate-700">
                    Full name
                  </label>
                  <input
                    type="text"
                    value={regName}
                    onChange={(e) => setRegName(e.target.value)}
                    className="input"
                    placeholder="Jane Doe"
                    autoComplete="name"
                    required
                  />
                </div>
                <div>
                  <label className="mb-1.5 block text-sm font-medium text-slate-700">
                    Email address
                  </label>
                  <input
                    type="email"
                    value={regEmail}
                    onChange={(e) => setRegEmail(e.target.value)}
                    className="input"
                    placeholder="you@example.com"
                    autoComplete="email"
                    required
                  />
                </div>
                <div>
                  <label className="mb-1.5 block text-sm font-medium text-slate-700">
                    Password
                  </label>
                  <input
                    type="password"
                    value={regPassword}
                    onChange={(e) => setRegPassword(e.target.value)}
                    className="input"
                    placeholder="Min. 8 characters"
                    autoComplete="new-password"
                    required
                  />
                </div>
                <div>
                  <label className="mb-1.5 block text-sm font-medium text-slate-700">
                    Confirm password
                  </label>
                  <input
                    type="password"
                    value={regConfirm}
                    onChange={(e) => setRegConfirm(e.target.value)}
                    className="input"
                    placeholder="••••••••"
                    autoComplete="new-password"
                    required
                  />
                </div>
                <button
                  type="submit"
                  disabled={loading}
                  className="btn btn-primary w-full"
                >
                  {loading ? (
                    <span className="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white" />
                  ) : (
                    'Create account'
                  )}
                </button>
              </form>
            )}
          </div>
        </div>
      </div>
    </div>
  )
}
