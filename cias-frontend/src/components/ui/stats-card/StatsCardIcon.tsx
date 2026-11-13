import type { HTMLAttributes, ReactNode } from 'react'
import { classNames } from '../../../utils/helpers'

type StatsCardIconProps = HTMLAttributes<HTMLDivElement> & {
  children: ReactNode
}

export function StatsCardIcon({
  children,
  className = '',
  ...props
}: StatsCardIconProps) {
  return (
    <div
      className={classNames(
        'inline-grid h-12 w-12 place-items-center rounded-2xl bg-[linear-gradient(180deg,#2f79ff_0%,#2457df_100%)] text-white shadow-[0_18px_30px_rgba(36,87,223,0.25)]',
        className,
      )}
      {...props}
    >
      {children}
    </div>
  )
}
