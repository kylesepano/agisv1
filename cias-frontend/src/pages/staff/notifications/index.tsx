import { EmptyState } from '../../../components/shared/EmptyState'
import { PageHeader } from '../../../components/shared/PageHeader'
import { StaffLayout } from '../../../layouts/staff/StaffLayout'

export function StaffNotificationsPage() {
  return (
    <StaffLayout>
      <PageHeader title="Notifications" subtitle="Track reminders and announcements." />
      <EmptyState title="Notifications scaffolded." />
    </StaffLayout>
  )
}
