import { EmptyState } from '../../../components/shared/EmptyState'
import { PageHeader } from '../../../components/shared/PageHeader'
import { AdminLayout } from '../../../layouts/admin/AdminLayout'

export function AdminPoliciesPage() {
  return (
    <AdminLayout>
      <PageHeader
        title="Policies"
        subtitle="Manage internal policies and policy acknowledgements."
      />
      <EmptyState title="Policies page scaffolded." />
    </AdminLayout>
  )
}
