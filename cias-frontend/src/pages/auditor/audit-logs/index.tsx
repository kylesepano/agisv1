import { EmptyState } from '../../../components/shared/EmptyState'
import { PageHeader } from '../../../components/shared/PageHeader'
import { AuditorLayout } from '../../../layouts/auditor/AuditorLayout'

export function AuditorAuditLogsPage() {
  return (
    <AuditorLayout>
      <PageHeader title="Audit Logs" subtitle="Review traceability and changes." />
      <EmptyState title="Audit logs scaffolded." />
    </AuditorLayout>
  )
}
