import type { ReactNode } from 'react'
import { createPortal } from 'react-dom'
import { X } from 'lucide-react'
import { cn } from '../../utils/helpers'

type DetailsDrawerProps = {
  title: string
  isOpen: boolean
  onClose: () => void
  children: ReactNode
  className?: string
}

export function DetailsDrawer({
  title,
  isOpen,
  onClose,
  children,
  className,
}: DetailsDrawerProps) {
  if (!isOpen) {
    return null
  }

  const drawer = (
    <>
      <button
        aria-label="Close details drawer overlay"
        className="fixed inset-0 z-30 bg-slate-950/25 backdrop-blur-[1px]"
        type="button"
        onClick={onClose}
      />

      <aside
        className={cn(
          'fixed inset-y-0 right-0 z-40 w-full max-w-[420px] border-l border-slate-200 bg-white shadow-2xl animate-[drawer-in_260ms_cubic-bezier(0.22,1,0.36,1)]',
          className,
        )}
      >
        <div className="flex h-full flex-col">
          <div className="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <h2 className="text-lg font-semibold tracking-tight text-slate-950">{title}</h2>
            <button
              aria-label="Close details drawer"
              className="grid h-9 w-9 place-items-center rounded-xl text-slate-500 transition hover:bg-slate-100 hover:text-slate-900"
              type="button"
              onClick={onClose}
            >
              <X size={18} />
            </button>
          </div>

          <div className="flex-1 overflow-y-auto px-5 py-4">{children}</div>
        </div>
      </aside>
    </>
  )

  return createPortal(drawer, document.body)
}
