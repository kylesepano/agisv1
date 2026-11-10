import type { LabelHTMLAttributes, ReactNode } from 'react'
import { classNames } from '../../../utils/helpers'

type TypographyLabelProps = LabelHTMLAttributes<HTMLLabelElement> & {
  children: ReactNode
}

export function Label({ children, className = '', ...props }: TypographyLabelProps) {
  return (
    <label
      className={classNames(
        'inline-block font-["Segoe_UI","Helvetica_Neue",Arial,sans-serif] text-[0.74rem] font-semibold tracking-[0.01em] text-[#102a43]',
        className,
      )}
      {...props}
    >
      {children}
    </label>
  )
}
