import { EmptyState } from '../../../components/shared/EmptyState'
import { PageHeader } from '../../../components/shared/PageHeader'
import { StaffLayout } from '../../../layouts/staff/StaffLayout'

export function StaffDocumentsPage() {
  return (
    <StaffLayout>
      <PageHeader title="Staff Documents" subtitle="View and manage assigned files." />
      <EmptyState title="Staff documents scaffolded." />
    </StaffLayout>
  )
}
