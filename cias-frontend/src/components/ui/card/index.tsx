import type { HTMLAttributes, ReactNode } from 'react'
import { cn } from '../../../utils/helpers'

type CardProps = {
  title?: string
  subtitle?: string
  children: ReactNode
  className?: string
}

export function Card({ title, subtitle, children, className }: CardProps) {
  return (
    <section
      className={cn(
        'rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm backdrop-blur',
        className,
      )}
    >
      {(title || subtitle) && (
        <header className="mb-5 space-y-1">
          {title && (
            <h3 className="text-lg font-semibold tracking-tight text-slate-900">{title}</h3>
          )}

          {subtitle && (
            <p className="text-sm text-slate-500">{subtitle}</p>
          )}
        </header>
      )}

      {children}
    </section>
  )
}

export function CardHeader({
  className,
  ...props
}: HTMLAttributes<HTMLDivElement>) {
  return <div className={cn('flex flex-col space-y-1.5', className)} {...props} />
}

export function CardTitle({
  className,
  ...props
}: HTMLAttributes<HTMLHeadingElement>) {
  return <h3 className={cn('text-lg font-semibold tracking-tight text-slate-900', className)} {...props} />
}

export function CardDescription({
  className,
  ...props
}: HTMLAttributes<HTMLParagraphElement>) {
  return <p className={cn('text-sm text-slate-500', className)} {...props} />
}

export function CardContent({
  className,
  ...props
}: HTMLAttributes<HTMLDivElement>) {
  return <div className={cn(className)} {...props} />
}

export function CardFooter({
  className,
  ...props
}: HTMLAttributes<HTMLDivElement>) {
  return <div className={cn('flex items-center', className)} {...props} />
}
