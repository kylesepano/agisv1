import { createContext } from 'react'

type SidebarContextValue = {
  isOpen: boolean
}

export const SidebarContext = createContext<SidebarContextValue>({ isOpen: true })
