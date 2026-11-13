import { EmptyState } from '../../../components/shared/EmptyState'
import { PageHeader } from '../../../components/shared/PageHeader'
import { AuditorLayout } from '../../../layouts/auditor/AuditorLayout'

export function AuditorReportsPage() {
  return (
    <AuditorLayout>
      <PageHeader title="Auditor Reports" subtitle="Generate formal audit summaries." />
      <EmptyState title="Auditor reports scaffolded." />
    </AuditorLayout>
  )
}
