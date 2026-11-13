import { EmptyState } from '../../../components/shared/EmptyState'
import { PageHeader } from '../../../components/shared/PageHeader'
import { SuperAdminLayout } from '../../../layouts/super-admin/SuperAdminLayout'

export function SuperAdminUsersPage() {
  return (
    <SuperAdminLayout>
      <PageHeader title="Super Admin Users" subtitle="Cross-tenant user administration." />
      <EmptyState title="Super admin users scaffolded." />
    </SuperAdminLayout>
  )
}
