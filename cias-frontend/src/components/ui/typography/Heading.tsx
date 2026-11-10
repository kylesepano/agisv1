import type { HTMLAttributes, ReactNode } from 'react'
import { classNames } from '../../../utils/helpers'

type HeadingLevel = 1 | 2 | 3 | 4 | 5 | 6

type HeadingProps = HTMLAttributes<HTMLHeadingElement> & {
  as?: HeadingLevel
  children: ReactNode
}

export function Heading({ as = 2, children, className = '', ...props }: HeadingProps) {
  const Tag = `h${as}` as const
  const sizeClasses = {
    1: 'text-[clamp(1.95rem,4vw,2.7rem)] font-bold tracking-[-0.03em] leading-[1]',
    2: 'text-[clamp(1.55rem,3.1vw,2.05rem)] font-bold tracking-[-0.03em] leading-[1.02]',
    3: 'text-[1.2rem] font-bold tracking-[-0.025em] leading-[1.05]',
    4: 'text-[0.98rem] font-semibold tracking-[-0.02em] leading-[1.08]',
    5: 'text-[0.88rem] font-semibold tracking-[-0.01em] leading-[1.1]',
    6: 'text-[0.76rem] font-semibold tracking-[-0.01em] leading-[1.1]',
  } satisfies Record<HeadingLevel, string>

  return (
    <Tag
      className={classNames(
        'font-["Segoe_UI","Helvetica_Neue",Arial,sans-serif] text-[#102a43]',
        sizeClasses[as],
        className,
      )}
      {...props}
    >
      {children}
    </Tag>
  )
}
