import { EmptyState } from '../../../components/shared/EmptyState'
import { PageHeader } from '../../../components/shared/PageHeader'
import { SuperAdminLayout } from '../../../layouts/super-admin/SuperAdminLayout'

export function SuperAdminDashboardPage() {
  return (
    <SuperAdminLayout>
      <PageHeader title="Super Admin Dashboard" subtitle="Tenant-wide system control." />
      <EmptyState title="Super admin dashboard scaffolded." />
    </SuperAdminLayout>
  )
}
