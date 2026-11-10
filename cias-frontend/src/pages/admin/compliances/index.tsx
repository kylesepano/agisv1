import { EmptyState } from '../../../components/shared/EmptyState'
import { PageHeader } from '../../../components/shared/PageHeader'
import { AdminLayout } from '../../../layouts/admin/AdminLayout'

export function AdminCompliancesPage() {
  return (
    <AdminLayout>
      <PageHeader title="Compliances" subtitle="Track compliance programs." />
      <EmptyState title="Compliances page scaffolded." />
    </AdminLayout>
  )
}
