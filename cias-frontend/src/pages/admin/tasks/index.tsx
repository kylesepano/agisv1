import { EmptyState } from '../../../components/shared/EmptyState'
import { PageHeader } from '../../../components/shared/PageHeader'
import { AdminLayout } from '../../../layouts/admin/AdminLayout'

export function AdminTasksPage() {
  return (
    <AdminLayout>
      <PageHeader
        title="Tasks"
        subtitle="Coordinate compliance tasks and operational follow-ups."
      />
      <EmptyState title="Tasks page scaffolded." />
    </AdminLayout>
  )
}
