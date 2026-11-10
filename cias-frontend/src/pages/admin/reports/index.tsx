import { EmptyState } from '../../../components/shared/EmptyState'
import { PageHeader } from '../../../components/shared/PageHeader'
import { AdminLayout } from '../../../layouts/admin/AdminLayout'

export function AdminReportsPage() {
  return (
    <AdminLayout>
      <PageHeader title="Reports" subtitle="Generate compliance insights." />
      <EmptyState title="Reports page scaffolded." />
    </AdminLayout>
  )
}
