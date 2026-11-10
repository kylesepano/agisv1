import { EmptyState } from '../../../components/shared/EmptyState'
import { PageHeader } from '../../../components/shared/PageHeader'
import { AdminLayout } from '../../../layouts/admin/AdminLayout'

export function AdminTrainingPage() {
  return (
    <AdminLayout>
      <PageHeader
        title="Training"
        subtitle="Track training programs, assignments, and completion."
      />
      <EmptyState title="Training page scaffolded." />
    </AdminLayout>
  )
}
