import type { HTMLAttributes, ReactNode } from 'react'
import { classNames } from '../../../utils/helpers'

type ParagraphProps = HTMLAttributes<HTMLParagraphElement> & {
  children: ReactNode
}

export function Paragraph({ children, className = '', ...props }: ParagraphProps) {
  return (
    <p
      className={classNames(
        'font-["Segoe_UI","Helvetica_Neue",Arial,sans-serif] text-[0.88rem] font-normal leading-[1.4] text-[#243b53]',
        className,
      )}
      {...props}
    >
      {children}
    </p>
  )
}
