import { EmptyState } from '../../../components/shared/EmptyState'
import { PageHeader } from '../../../components/shared/PageHeader'
import { SuperAdminLayout } from '../../../layouts/super-admin/SuperAdminLayout'

export function SuperAdminRolesPage() {
  return (
    <SuperAdminLayout>
      <PageHeader title="Roles" subtitle="Manage platform roles and policies." />
      <EmptyState title="Super admin roles scaffolded." />
    </SuperAdminLayout>
  )
}
