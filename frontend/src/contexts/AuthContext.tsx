'use client'

import {
  createContext,
  useContext,
  useState,
  useEffect,
  useCallback,
  useMemo,
  type ReactNode,
} from 'react'
import Cookies from 'js-cookie'
import { apiClient, TOKEN_KEY } from '@/lib/axios'
import type { User, UpdateUserPayload } from '@/types'

// ─── JWT decode (payload is public base64url, not encrypted) ─────────────────

function decodeJwtPayload(token: string): Record<string, unknown> {
  try {
    const base64 = token.split('.')[1].replace(/-/g, '+').replace(/_/g, '/')
    return JSON.parse(atob(base64))
  } catch {
    return {}
  }
}

// ─── Types ────────────────────────────────────────────────────────────────────

interface AuthContextType {
  user:      User | null
  token:     string | null
  isLoading: boolean
  isAdmin:   boolean
  login:     (email: string, password: string) => Promise<void>
  logout:    () => void
  updateUser:(data: UpdateUserPayload) => void
}

// ─── Context ──────────────────────────────────────────────────────────────────

const AuthContext = createContext<AuthContextType | null>(null)

// ─── Provider ─────────────────────────────────────────────────────────────────

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user,      setUser]      = useState<User | null>(null)
  const [token,     setToken]     = useState<string | null>(null)
  const [isLoading, setIsLoading] = useState(true)

  /** Derive admin status from JWT roles claim — no extra API call needed */
  const isAdmin = useMemo(() => {
    if (!token) return false
    const payload = decodeJwtPayload(token)
    const roles = Array.isArray(payload.roles) ? (payload.roles as string[]) : []
    return roles.includes('ROLE_ADMIN')
  }, [token])

  /** Fetch /api/me and hydrate user state */
  const fetchCurrentUser = useCallback(async (jwt: string) => {
    try {
      const { data } = await apiClient.get<User>('/me', {
        headers: { Authorization: `Bearer ${jwt}` },
      })
      setUser(data)
    } catch {
      // Token expired / invalid — clean up silently
      Cookies.remove(TOKEN_KEY)
      setToken(null)
      setUser(null)
    } finally {
      setIsLoading(false)
    }
  }, [])

  // On mount: restore session from cookie
  useEffect(() => {
    const stored = Cookies.get(TOKEN_KEY)
    if (stored) {
      setToken(stored)
      fetchCurrentUser(stored)
    } else {
      setIsLoading(false)
    }
  }, [fetchCurrentUser])

  async function login(email: string, password: string) {
    const { data } = await apiClient.post<{ token: string }>('/auth/login', { email, password })
    const jwt = data.token
    Cookies.set(TOKEN_KEY, jwt, { expires: 1, sameSite: 'strict' })
    setToken(jwt)
    await fetchCurrentUser(jwt)
  }

  function logout() {
    Cookies.remove(TOKEN_KEY)
    setToken(null)
    setUser(null)
  }

  function updateUser(data: UpdateUserPayload) {
    setUser((prev) => (prev ? { ...prev, ...data } : null))
  }

  return (
    <AuthContext.Provider value={{ user, token, isLoading, isAdmin, login, logout, updateUser }}>
      {children}
    </AuthContext.Provider>
  )
}

// ─── Hook ─────────────────────────────────────────────────────────────────────

export function useAuth(): AuthContextType {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth must be used within <AuthProvider />')
  return ctx
}
