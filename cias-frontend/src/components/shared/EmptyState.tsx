import { Paragraph } from '../ui/typography'

type EmptyStateProps = {
  title?: string
}

export function EmptyState({ title = 'No data yet.' }: EmptyStateProps) {
  return (
    <div className="state-box">
      <Paragraph>{title}</Paragraph>
    </div>
  )
}
