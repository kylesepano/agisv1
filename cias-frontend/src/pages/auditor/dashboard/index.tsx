import { EmptyState } from '../../../components/shared/EmptyState'
import { PageHeader } from '../../../components/shared/PageHeader'
import { AuditorLayout } from '../../../layouts/auditor/AuditorLayout'

export function AuditorDashboardPage() {
  return (
    <AuditorLayout>
      <PageHeader title="Auditor Dashboard" subtitle="Audit trail and findings overview." />
      <EmptyState title="Auditor dashboard scaffolded." />
    </AuditorLayout>
  )
}
