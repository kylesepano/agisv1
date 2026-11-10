import { Plus } from 'lucide-react'
import { Button } from '../../../../components/ui/button'
import { Heading, Paragraph } from '../../../../components/ui/typography'
import { navigateTo } from '../../../../routes'

export function AuditUniverseHeader() {
  return (
    <header className="flex items-start justify-between gap-3 max-[700px]:flex-col">
      <div>
        <Heading as={2} className="text-slate-950">
          Audit Universe
        </Heading>
        <Paragraph className="mt-0.5 text-slate-600">
          Manage all auditable entities within the LGU.
        </Paragraph>
      </div>
      <Button
        className="h-9 rounded-lg px-3.5"
        onClick={() => navigateTo('/admin/audit-universe/create')}
      >
        <Plus size={14} />
        Add Auditable Unit
      </Button>
    </header>
  )
}
