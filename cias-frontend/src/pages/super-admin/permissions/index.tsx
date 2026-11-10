import { EmptyState } from '../../../components/shared/EmptyState'
import { PageHeader } from '../../../components/shared/PageHeader'
import { SuperAdminLayout } from '../../../layouts/super-admin/SuperAdminLayout'

export function SuperAdminPermissionsPage() {
  return (
    <SuperAdminLayout>
      <PageHeader title="Permissions" subtitle="Control feature-level access." />
      <EmptyState title="Super admin permissions scaffolded." />
    </SuperAdminLayout>
  )
}
