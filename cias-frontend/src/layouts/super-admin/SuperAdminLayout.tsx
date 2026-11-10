import type { ReactNode } from 'react'
import { SuperAdminNavbar } from './components/SuperAdminNavbar'
import { SuperAdminSidebar } from './components/SuperAdminSidebar'

type SuperAdminLayoutProps = {
  children: ReactNode
}

export function SuperAdminLayout({ children }: SuperAdminLayoutProps) {
  return (
    <div className="app-shell">
      <SuperAdminSidebar />
      <div className="content-shell">
        <SuperAdminNavbar />
        <main className="content">{children}</main>
      </div>
    </div>
  )
}
