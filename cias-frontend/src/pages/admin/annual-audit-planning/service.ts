import {
  annualAuditPlanRecords,
  auditCalendarItems,
} from "./annual-audit-planning-data";
import { getAnnualAuditPlanningSummary } from "./annual-audit-planning-utils";

export function getAnnualAuditPlanRecords() {
  return annualAuditPlanRecords;
}

export function getAuditCalendarItems() {
  return auditCalendarItems;
}

export function getAnnualAuditPlanningSummaryMetrics() {
  return getAnnualAuditPlanningSummary(annualAuditPlanRecords);
}
