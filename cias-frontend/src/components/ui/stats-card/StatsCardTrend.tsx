import type { HTMLAttributes, ReactNode } from 'react'
import { Text } from '../typography'
import { classNames } from '../../../utils/helpers'

type StatsCardTrendProps = HTMLAttributes<HTMLDivElement> & {
  children: ReactNode
  tone?: 'positive' | 'neutral' | 'negative'
}

export function StatsCardTrend({
  children,
  className = '',
  tone = 'positive',
  ...props
}: StatsCardTrendProps) {
  const toneClasses = {
    positive: 'text-[#2f855a]',
    neutral: 'text-[#52606d]',
    negative: 'text-[#c53030]',
  } as const

  return (
    <div className={classNames('mt-3 flex items-center gap-2', className)} {...props}>
      <span
        className={classNames(
          'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
          tone === 'positive' && 'bg-[rgba(47,133,90,0.12)] text-[#2f855a]',
          tone === 'neutral' && 'bg-[rgba(82,96,109,0.12)] text-[#52606d]',
          tone === 'negative' && 'bg-[rgba(197,48,48,0.12)] text-[#c53030]',
        )}
      >
        Trend
      </span>
      <Text className={classNames(toneClasses[tone])}>{children}</Text>
    </div>
  )
}
