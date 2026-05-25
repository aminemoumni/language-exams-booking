'use client'

import { useState } from 'react'
import Link from 'next/link'
import { usePathname, useRouter } from 'next/navigation'
import { useAuth } from '@/contexts/AuthContext'

export default function Navbar() {
  const { user, isAdmin, logout } = useAuth()
  const pathname = usePathname()
  const router   = useRouter()
  const [menuOpen, setMenuOpen] = useState(false)

  // Do not render the navbar on the login/register page
  if (pathname === '/login') return null

  function handleLogout() {
    logout()
    router.push('/login')
  }

  const navLinks = [
    { href: '/sessions',     label: 'Sessions',    adminOnly: false },
    { href: '/reservations', label: 'My Bookings', adminOnly: false, hideForAdmin: true },
    { href: '/account',      label: 'Account',     adminOnly: false },
  ].filter((link) => !(link.hideForAdmin && isAdmin))

  return (
    <header className="sticky top-0 z-50 border-b border-white/10 bg-ets-blue shadow-md">
      <div className="page-container">
        <div className="flex h-16 items-center justify-between">

          {/* ── Logo ── */}
          <Link href="/sessions" className="flex items-center gap-3 group">
            <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-ets-orange shadow-sm group-hover:bg-ets-orange-mid transition-colors">
              <span className="text-lg font-black text-white tracking-tight">E</span>
            </div>
            <div className="hidden sm:block">
              <p className="text-sm font-bold uppercase tracking-widest text-white">ETS Global</p>
              <p className="text-[10px] uppercase tracking-wider text-blue-200">Language Exams</p>
            </div>
          </Link>

          {/* ── Desktop nav ── */}
          <nav className="hidden md:flex items-center gap-1">
            {navLinks.map(({ href, label }) => {
              const active = pathname.startsWith(href)
              return (
                <Link
                  key={href}
                  href={href}
                  className={`rounded-lg px-4 py-2 text-sm font-medium transition-colors ${
                    active
                      ? 'bg-white/15 text-white'
                      : 'text-blue-100 hover:bg-white/10 hover:text-white'
                  }`}
                >
                  {label}
                </Link>
              )
            })}
            {isAdmin && (
              <span className="ml-1 rounded-full bg-ets-orange px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-white">
                Admin
              </span>
            )}
          </nav>

          {/* ── Desktop user ── */}
          <div className="hidden md:flex items-center gap-3">
            {user && (
              <div className="text-right">
                <p className="text-sm font-medium text-white">{user.name}</p>
                <p className="text-xs text-blue-300">{user.email}</p>
              </div>
            )}
            <button
              onClick={handleLogout}
              className="rounded-lg border border-white/20 px-4 py-2 text-sm font-medium text-white hover:bg-white/10 transition-colors"
            >
              Sign out
            </button>
          </div>

          {/* ── Mobile hamburger ── */}
          <button
            className="md:hidden rounded-lg p-2 text-white hover:bg-white/10"
            onClick={() => setMenuOpen((v) => !v)}
            aria-label="Toggle menu"
          >
            {menuOpen ? (
              <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            ) : (
              <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M4 6h16M4 12h16M4 18h16" />
              </svg>
            )}
          </button>
        </div>
      </div>

      {/* ── Mobile menu ── */}
      {menuOpen && (
        <div className="border-t border-white/10 bg-ets-blue md:hidden animate-fade-in">
          <div className="page-container py-3 space-y-1">
            {navLinks.map(({ href, label }) => (
              <Link
                key={href}
                href={href}
                onClick={() => setMenuOpen(false)}
                className={`block rounded-lg px-4 py-2.5 text-sm font-medium transition-colors ${
                  pathname.startsWith(href)
                    ? 'bg-white/15 text-white'
                    : 'text-blue-100 hover:bg-white/10 hover:text-white'
                }`}
              >
                {label}
              </Link>
            ))}
            <div className="pt-2 border-t border-white/10">
              {user && (
                <p className="px-4 py-1 text-xs text-blue-300">{user.email}</p>
              )}
              <button
                onClick={handleLogout}
                className="block w-full text-left rounded-lg px-4 py-2.5 text-sm font-medium text-blue-100 hover:bg-white/10 hover:text-white transition-colors"
              >
                Sign out
              </button>
            </div>
          </div>
        </div>
      )}
    </header>
  )
}
