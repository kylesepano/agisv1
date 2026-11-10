import { EmptyState } from '../../../components/shared/EmptyState'
import { PageHeader } from '../../../components/shared/PageHeader'
import { AdminLayout } from '../../../layouts/admin/AdminLayout'

export function AdminSettingsPage() {
  return (
    <AdminLayout>
      <PageHeader title="Settings" subtitle="System-wide administration options." />
      <EmptyState title="Settings page scaffolded." />
    </AdminLayout>
  )
}
