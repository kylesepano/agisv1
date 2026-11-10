import type { HTMLAttributes, ReactNode } from 'react'
import { cn } from '../../../utils/helpers'

type BadgeProps = HTMLAttributes<HTMLSpanElement> & {
  children: ReactNode
  tone?: 'default' | 'success' | 'warning' | 'neutral'
}

const toneClasses = {
  default: 'bg-orange-100 text-orange-700',
  success: 'bg-emerald-100 text-emerald-700',
  warning: 'bg-amber-100 text-amber-700',
  neutral: 'bg-slate-100 text-slate-700',
} as const

export function Badge({
  children,
  className,
  tone = 'default',
  ...props
}: BadgeProps) {
  return (
    <span
      className={cn(
        'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium',
        toneClasses[tone],
        className,
      )}
      {...props}
    >
      {children}
    </span>
  )
}
