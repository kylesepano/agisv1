import type {
  AnnualAuditPlanRecord,
  AnnualAuditPlanningSummary,
  AuditCalendarItem,
  PlanStatus,
} from "./annual-audit-planning-types";

function parseDateValue(value: string) {
  const normalized = value.includes("T") ? value : `${value}T00:00:00`;
  const date = new Date(normalized);

  return Number.isNaN(date.getTime()) ? null : date;
}

export function getAnnualAuditPlanningSummary(
  records: AnnualAuditPlanRecord[],
): AnnualAuditPlanningSummary {
  return {
    totalPlannedAudits: records.length,
    completed: records.filter((record) => record.status === "Completed").length,
    inProgress: records.filter((record) => record.status === "In Progress").length,
    planned: records.filter((record) => record.status === "Planned").length,
    cancelled: records.filter((record) => record.status === "Cancelled").length,
  };
}

export function formatAuditScheduleDate(value: string) {
  const date = parseDateValue(value);

  if (!date) {
    return "Invalid date";
  }

  return new Intl.DateTimeFormat("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
  }).format(date);
}

export function groupCalendarItemsByQuarter(items: AuditCalendarItem[]) {
  return ["Q1", "Q2", "Q3", "Q4"].map((quarter) => ({
    quarter,
    items: items.filter((item) => item.quarter === quarter),
  }));
}

export function formatAuditCalendarPanelDate(value: string) {
  const date = parseDateValue(value);

  if (!date) {
    return "Select a date";
  }

  return new Intl.DateTimeFormat("en-US", {
    weekday: "long",
    month: "long",
    day: "numeric",
    year: "numeric",
  }).format(date);
}

export function getPlanStatusColor(status: PlanStatus) {
  if (status === "Completed") {
    return "#22c55e";
  }
  if (status === "In Progress") {
    return "#2563eb";
  }
  if (status === "Planned") {
    return "#8b5cf6";
  }
  return "#ef4444";
}
