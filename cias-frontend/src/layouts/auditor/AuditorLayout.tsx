import type { ReactNode } from 'react'
import { AuditorNavbar } from './components/AuditorNavbar'
import { AuditorSidebar } from './components/AuditorSidebar'

type AuditorLayoutProps = {
  children: ReactNode
}

export function AuditorLayout({ children }: AuditorLayoutProps) {
  return (
    <div className="app-shell">
      <AuditorSidebar />
      <div className="content-shell">
        <AuditorNavbar />
        <main className="content">{children}</main>
      </div>
    </div>
  )
}
