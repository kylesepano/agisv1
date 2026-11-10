import type { HTMLAttributes, ReactNode } from 'react'
import { classNames } from '../../../utils/helpers'

type StatsCardHeaderProps = HTMLAttributes<HTMLDivElement> & {
  children: ReactNode
}

export function StatsCardHeader({
  children,
  className = '',
  ...props
}: StatsCardHeaderProps) {
  return (
    <div
      className={classNames('mb-4 flex items-start justify-between gap-3', className)}
      {...props}
    >
      {children}
    </div>
  )
}
