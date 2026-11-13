import type { ReactNode } from 'react'
import { StaffFooter } from './components/StaffFooter'
import { StaffNavbar } from './components/StaffNavbar'
import { StaffSidebar } from './components/StaffSidebar'

type StaffLayoutProps = {
  children: ReactNode
}

export function StaffLayout({ children }: StaffLayoutProps) {
  return (
    <div className="app-shell">
      <StaffSidebar />
      <div className="content-shell">
        <StaffNavbar />
        <main className="content">{children}</main>
        <StaffFooter />
      </div>
    </div>
  )
}
