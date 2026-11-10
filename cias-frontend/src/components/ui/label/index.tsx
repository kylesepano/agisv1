import type { LabelHTMLAttributes } from 'react'
import { cn } from '../../../utils/helpers'

export function Label(props: LabelHTMLAttributes<HTMLLabelElement>) {
  const { className, ...rest } = props

  return (
    <label className={cn('text-sm font-medium text-slate-700', className)} {...rest} />
  )
}
