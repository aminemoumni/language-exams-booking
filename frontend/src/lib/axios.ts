import axios from 'axios'
import Cookies from 'js-cookie'

const TOKEN_KEY = 'jwt_token'

/**
 * Pre-configured Axios instance.
 * Automatically attaches JWT from cookie on every request.
 * Redirects to /login on 401 responses.
 */
export const apiClient = axios.create({
  baseURL: process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost/api',
  headers: {
    'Content-Type': 'application/json',
  },
})

// ─── Request interceptor — attach JWT ────────────────────────────────────────
apiClient.interceptors.request.use((config) => {
  const token = Cookies.get(TOKEN_KEY)
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// ─── Response interceptor — handle 401 ──────────────────────────────────────
// Only redirect to /login when a 401 arrives on an *authenticated* request
// (i.e. session expired). Auth endpoints (/auth/login, /auth/register) also
// return 401 on bad credentials — we must NOT redirect there, otherwise the
// error would flash and the page would immediately reload.
apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    const is401 = error.response?.status === 401
    const onLoginPage =
      typeof window !== 'undefined' &&
      window.location.pathname.startsWith('/login')

    if (is401 && !onLoginPage && typeof window !== 'undefined') {
      Cookies.remove(TOKEN_KEY)
      window.location.href = '/login'
    }
    return Promise.reject(error)
  },
)

export { TOKEN_KEY }
