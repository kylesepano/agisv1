import { EmptyState } from '../../../components/shared/EmptyState'
import { PageHeader } from '../../../components/shared/PageHeader'
import { StaffLayout } from '../../../layouts/staff/StaffLayout'

export function StaffDashboardPage() {
  return (
    <StaffLayout>
      <PageHeader title="Staff Dashboard" subtitle="Daily tasks and updates." />
      <EmptyState title="Staff dashboard scaffolded." />
    </StaffLayout>
  )
}
