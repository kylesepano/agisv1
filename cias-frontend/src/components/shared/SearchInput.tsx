import type { InputHTMLAttributes } from 'react'

export function SearchInput(props: InputHTMLAttributes<HTMLInputElement>) {
  return <input className="input" placeholder="Search..." type="search" {...props} />
}
