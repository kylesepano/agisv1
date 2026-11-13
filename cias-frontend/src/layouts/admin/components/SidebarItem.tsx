import type { ReactNode } from 'react'
import { Text } from '../../../components/ui/typography'
import { navigateTo } from '../../../routes'
import { classNames } from '../../../utils/helpers'

type SidebarItemProps = {
  label: string
  icon: ReactNode
  active?: boolean
  path: string
  onNavigate?: () => void
}

export function SidebarItem({
  label,
  icon,
  active = false,
  path,
  onNavigate,
}: SidebarItemProps) {
  return (
    <li className="list-none">
      <a
        className={classNames(
          'flex w-full items-center gap-3 rounded-[0.85rem] border border-transparent bg-transparent px-[0.7rem] py-[0.85rem] text-left text-[0.98rem] font-medium leading-[1.3] text-[#e8eefc] outline-none transition hover:bg-white/5 focus:outline-none focus:ring-0',
          active &&
            'border-[rgba(92,145,255,0.18)] bg-[linear-gradient(180deg,rgba(47,121,255,0.18),rgba(36,87,223,0.12))] text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.03)]',
        )}
        href={path}
        onClick={(event) => {
          event.preventDefault()
          navigateTo(path)
          onNavigate?.()
        }}
      >
        <span
          className={classNames(
            'inline-grid h-[1.3rem] w-[1.3rem] min-w-[1.3rem] place-items-center text-[#a9bfdc]',
            active && 'text-[#e5efff]',
          )}
          aria-hidden="true"
        >
          {icon}
        </span>
        <Text className={classNames('text-[#e8eefc]', active && 'text-white')}>
          {label}
        </Text>
        <span
          className={classNames(
            'ml-auto inline-grid h-[0.95rem] w-[0.95rem] place-items-center text-base font-normal text-[#7f95bc]',
            active && 'text-[#b8cef5]',
          )}
          aria-hidden="true"
        >
          <svg viewBox="0 0 20 20" fill="none" className="h-full w-full">
            <path d="M7 5L12 10L7 15" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" />
          </svg>
        </span>
      </a>
    </li>
  )
}
