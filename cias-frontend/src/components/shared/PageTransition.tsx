import { useEffect, type ReactNode } from 'react'
import { cn } from '../../utils/helpers'

type PageTransitionProps = {
  children: ReactNode
  transitionKey: string
  className?: string
}

let previousTransitionKey: string | null = null

export function PageTransition({
  children,
  transitionKey,
  className,
}: PageTransitionProps) {
  const animationClass =
    previousTransitionKey === '/admin/audit-universe' &&
    transitionKey === '/admin/audit-universe/create'
      ? 'animate-[page-slide-forward_320ms_cubic-bezier(0.22,1,0.36,1)]'
      : previousTransitionKey === '/admin/audit-universe/create' &&
          transitionKey === '/admin/audit-universe'
        ? 'animate-[page-slide-back_320ms_cubic-bezier(0.22,1,0.36,1)]'
        : 'animate-[page-enter_320ms_cubic-bezier(0.22,1,0.36,1)]'

  useEffect(() => {
    previousTransitionKey = transitionKey
  }, [transitionKey])

  return (
    <div
      key={transitionKey}
      className={cn(
        'h-full min-h-full motion-reduce:animate-none',
        animationClass,
        className,
      )}
    >
      {children}
    </div>
  )
}
