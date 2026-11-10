import type { HTMLAttributes, ReactNode } from 'react'
import { Heading } from '../typography'
import { classNames } from '../../../utils/helpers'

type StatsCardValueProps = HTMLAttributes<HTMLDivElement> & {
  children: ReactNode
}

export function StatsCardValue({
  children,
  className = '',
  ...props
}: StatsCardValueProps) {
  return (
    <div className={classNames('mt-3', className)} {...props}>
      <Heading as={2} className="text-[2rem] leading-none text-[#102a43]">
        {children}
      </Heading>
    </div>
  )
}
