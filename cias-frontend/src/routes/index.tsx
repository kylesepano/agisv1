import { useEffect, useState, type ReactNode } from 'react'
import { isAuthenticated } from '../lib/auth'
import { LoginPage } from '../pages/auth/login'
import { adminRoutes } from './AdminRoutes'
import { auditorRoutes } from './AuditorRoutes'
import { authRoutes } from './AuthRoutes'
import { staffRoutes } from './StaffRoutes'

export const appRoutes = [...authRoutes, ...adminRoutes, ...staffRoutes, ...auditorRoutes]

export function navigateTo(path: string) {
  if (window.location.pathname !== path) {
    window.history.pushState({}, '', path)
  }
  window.dispatchEvent(new PopStateEvent('popstate'))
}

export function useCurrentPath() {
  const [path, setPath] = useState(() => window.location.pathname)

  useEffect(() => {
    const handleLocationChange = () => setPath(window.location.pathname)

    window.addEventListener('popstate', handleLocationChange)
    return () => window.removeEventListener('popstate', handleLocationChange)
  }, [])

  return path
}

export function renderRoute(path: string, authenticated?: boolean): ReactNode {
  const hasToken = isAuthenticated()
  const loggedIn = Boolean(hasToken && (authenticated !== undefined ? authenticated : true))

  // 1. Unauthenticated users strictly default to the Login page
  if (!loggedIn) {
    if (path === '/forgot-password') {
      const forgotPassword = appRoutes.find((route) => route.path === '/forgot-password')
      return forgotPassword?.element ?? <LoginPage />
    }
    return <LoginPage />
  }

  // 2. Authenticated users visiting root ("/"), "/login", or empty path are directed to Dashboard
  if (path === '/' || path === '' || path === '/login') {
    const dashboard = appRoutes.find((route) => route.path === '/admin/dashboard')
    return dashboard?.element ?? <LoginPage />
  }

  // 3. Authenticated users visiting a valid matched route
  const match = appRoutes.find((route) => {
    if (route.path === path) {
      return true
    }
    const routeParts = route.path.split('/')
    const pathParts = path.split('/')
    if (routeParts.length !== pathParts.length) {
      return false
    }
    return routeParts.every((part, i) => part.startsWith(':') || part === pathParts[i])
  })
  if (match) {
    return match.element
  }

  // 4. Fallback for authenticated users visiting an unmapped route
  const fallback = appRoutes.find((route) => route.path === '/admin/dashboard')
  return fallback?.element ?? <LoginPage />
}
