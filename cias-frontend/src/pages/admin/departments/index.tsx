import { EmptyState } from '../../../components/shared/EmptyState'
import { PageHeader } from '../../../components/shared/PageHeader'
import { AdminLayout } from '../../../layouts/admin/AdminLayout'

export function AdminDepartmentsPage() {
  return (
    <AdminLayout>
      <PageHeader title="Departments" subtitle="Organize teams and ownership." />
      <EmptyState title="Departments page scaffolded." />
    </AdminLayout>
  )
}
