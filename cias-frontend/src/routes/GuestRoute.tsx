import type { ReactNode } from 'react'

type GuestRouteProps = {
  children: ReactNode
  isGuest?: boolean
}

export function GuestRoute({ children, isGuest = true }: GuestRouteProps) {
  return isGuest ? <>{children}</> : <div className="state-box">Already authenticated.</div>
}
