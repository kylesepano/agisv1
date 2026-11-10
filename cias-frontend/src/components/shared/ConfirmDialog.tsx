import { Button } from '../ui/button'
import { Heading, Paragraph } from '../ui/typography'

type ConfirmDialogProps = {
  title?: string
  description?: string
}

export function ConfirmDialog({
  title = 'Confirm action',
  description = 'Please review this action before continuing.',
}: ConfirmDialogProps) {
  return (
    <div className="card">
      <Heading as={4}>{title}</Heading>
      <Paragraph className="text-[#52606d]">{description}</Paragraph>
      <div className="button-row">
        <Button variant="ghost">Cancel</Button>
        <Button>Confirm</Button>
      </div>
    </div>
  )
}
