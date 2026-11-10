import { Caption, Heading, Paragraph } from '../ui/typography'

type PageHeaderProps = {
  title: string
  subtitle?: string
}

export function PageHeader({ title, subtitle }: PageHeaderProps) {
  return (
    <header className="mb-6 flex items-end justify-between gap-4 max-[640px]:items-start max-[640px]:flex-col">
      <div>
        <Caption className="mb-2 text-[#52606d]">CIAS System</Caption>
        <Heading as={1}>{title}</Heading>
      </div>
      {subtitle ? (
        <Paragraph className="max-w-xl text-[#52606d]">{subtitle}</Paragraph>
      ) : null}
    </header>
  )
}
