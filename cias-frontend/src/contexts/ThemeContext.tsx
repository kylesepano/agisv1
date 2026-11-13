import { createContext } from 'react'

type ThemeContextValue = {
  theme: 'light' | 'dark'
}

export const ThemeContext = createContext<ThemeContextValue>({ theme: 'light' })
