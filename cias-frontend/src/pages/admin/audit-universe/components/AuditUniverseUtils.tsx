import { Badge } from '../../../../components/ui/badge'
import type { AuditUniverseRecord } from '../audit-universe-types'
import {
  auditUniverseCategoryClass,
  auditUniverseRiskClass,
} from './audit-universe-theme'

export function AuditUniverseCategoryBadge({
  category,
}: {
  category: AuditUniverseRecord['category']
}) {
  return (
    <Badge className={auditUniverseCategoryClass(category)} tone="neutral">
      {category}
    </Badge>
  )
}

export function AuditUniverseRiskBadge({
  riskLevel,
}: {
  riskLevel: AuditUniverseRecord['riskLevel']
}) {
  return (
    <Badge className={auditUniverseRiskClass(riskLevel)} tone="neutral">
      {riskLevel}
    </Badge>
  )
}
