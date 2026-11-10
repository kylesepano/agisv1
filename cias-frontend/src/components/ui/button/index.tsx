import type { ButtonHTMLAttributes, ReactNode } from 'react'
import { cn } from '../../../utils/helpers'

type ButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & {
  children: ReactNode
  variant?: 'primary' | 'secondary' | 'ghost' | 'outline'
}

const variantClasses = {
  primary:
    'bg-slate-900 text-white shadow-sm hover:bg-slate-800 focus-visible:ring-slate-400',
  secondary:
    'bg-orange-600 text-white shadow-sm hover:bg-orange-500 focus-visible:ring-orange-300',
  ghost:
    'bg-transparent text-slate-700 hover:bg-slate-100 focus-visible:ring-slate-300',
  outline:
    'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 focus-visible:ring-slate-300',
} as const

export function Button({
  children,
  className,
  variant = 'primary',
  type = 'button',
  ...props
}: ButtonProps) {
  return (
    <button
      type={type}
      className={cn(
        'inline-flex h-10 items-center justify-center rounded-md px-4 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50',
        variantClasses[variant],
        className,
      )}
      {...props}
    >
      {children}
    </button>
  )
}
