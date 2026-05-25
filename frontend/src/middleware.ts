import { NextResponse } from 'next/server'
import type { NextRequest } from 'next/server'

/** Routes accessible without authentication */
const PUBLIC_PATHS = ['/login']

export function middleware(request: NextRequest) {
  const token = request.cookies.get('jwt_token')?.value
  const { pathname } = request.nextUrl

  const isPublic = PUBLIC_PATHS.some((p) => pathname.startsWith(p))

  // Not authenticated → redirect to /login
  if (!token && !isPublic) {
    const loginUrl = new URL('/login', request.url)
    loginUrl.searchParams.set('from', pathname)
    return NextResponse.redirect(loginUrl)
  }

  // Already authenticated → redirect away from /login
  if (token && pathname === '/login') {
    return NextResponse.redirect(new URL('/sessions', request.url))
  }

  return NextResponse.next()
}

export const config = {
  /**
   * Apply middleware to all routes except:
   * - Next.js internals (_next/*)
   * - Static files (images, favicon, etc.)
   */
  matcher: ['/((?!_next/static|_next/image|favicon.ico|.*\\.(?:svg|png|jpg|jpeg|gif|webp)$).*)'],
}
