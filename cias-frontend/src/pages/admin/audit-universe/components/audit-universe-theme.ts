import type { AuditUniverseRecord } from '../audit-universe-types'

export function auditUniverseCategoryClass(category: AuditUniverseRecord['category']) {
  if (category === 'System') return 'bg-violet-100 text-violet-700'
  if (category === 'Process') return 'bg-emerald-100 text-emerald-700'
  if (category === 'Department') return 'bg-blue-100 text-blue-700'
  return 'bg-amber-100 text-amber-700'
}

export function auditUniverseRiskClass(riskLevel: AuditUniverseRecord['riskLevel']) {
  if (riskLevel === 'High') return 'bg-rose-100 text-rose-700'
  if (riskLevel === 'Medium') return 'bg-amber-100 text-amber-700'
  return 'bg-emerald-100 text-emerald-700'
}
