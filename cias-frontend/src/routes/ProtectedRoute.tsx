import type { ReactNode } from 'react'

type ProtectedRouteProps = {
  children: ReactNode
  isAllowed?: boolean
}

export function ProtectedRoute({
  children,
  isAllowed = true,
}: ProtectedRouteProps) {
  return isAllowed ? <>{children}</> : <div className="state-box error">Access denied.</div>
}
