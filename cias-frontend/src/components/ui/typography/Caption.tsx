import type { HTMLAttributes, ReactNode } from 'react'
import { classNames } from '../../../utils/helpers'

type CaptionProps = HTMLAttributes<HTMLSpanElement> & {
  children: ReactNode
}

export function Caption({ children, className = '', ...props }: CaptionProps) {
  return (
    <span
      className={classNames(
        'font-["Segoe_UI","Helvetica_Neue",Arial,sans-serif] text-[0.66rem] font-semibold uppercase tracking-[0.08em] text-[#52606d]',
        className,
      )}
      {...props}
    >
      {children}
    </span>
  )
}
