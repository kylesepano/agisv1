import type { HTMLAttributes, TableHTMLAttributes, TdHTMLAttributes, ThHTMLAttributes } from 'react'
import { cn } from '../../../utils/helpers'

export function Table({
  className,
  ...props
}: TableHTMLAttributes<HTMLTableElement>) {
  return (
    <div className="w-full overflow-auto">
      <table className={cn('w-full caption-bottom text-sm', className)} {...props} />
    </div>
  )
}

export function TableHeader({
  className,
  ...props
}: HTMLAttributes<HTMLTableSectionElement>) {
  return <thead className={cn('[&_tr]:border-b', className)} {...props} />
}

export function TableBody({
  className,
  ...props
}: HTMLAttributes<HTMLTableSectionElement>) {
  return <tbody className={cn('[&_tr:last-child]:border-0', className)} {...props} />
}

export function TableRow({
  className,
  ...props
}: HTMLAttributes<HTMLTableRowElement>) {
  return (
    <tr
      className={cn('border-b border-slate-100 transition-colors hover:bg-slate-50/70', className)}
      {...props}
    />
  )
}

export function TableHead({
  className,
  ...props
}: ThHTMLAttributes<HTMLTableCellElement>) {
  return (
    <th
      className={cn(
        'h-11 px-4 text-left align-middle text-xs font-semibold uppercase tracking-[0.16em] text-slate-500',
        className,
      )}
      {...props}
    />
  )
}

export function TableCell({
  className,
  ...props
}: TdHTMLAttributes<HTMLTableCellElement>) {
  return <td className={cn('p-4 align-middle text-slate-700', className)} {...props} />
}
