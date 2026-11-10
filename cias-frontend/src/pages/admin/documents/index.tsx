import { EmptyState } from '../../../components/shared/EmptyState'
import { PageHeader } from '../../../components/shared/PageHeader'
import { AdminLayout } from '../../../layouts/admin/AdminLayout'

export function AdminDocumentsPage() {
  return (
    <AdminLayout>
      <PageHeader title="Documents" subtitle="Manage evidence and uploads." />
      <EmptyState title="Documents page scaffolded." />
    </AdminLayout>
  )
}
