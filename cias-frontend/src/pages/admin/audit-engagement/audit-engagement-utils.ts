import type {
  AuditEngagementRecord,
  AuditEngagementSummary,
} from "./audit-engagement-types";

export function getAuditEngagementSummary(
  records: AuditEngagementRecord[],
): AuditEngagementSummary {
  const activeCount = records.filter((record) => record.status === "Active").length;
  const forApprovalCount = records.filter(
    (record) => record.status === "For Approval",
  ).length;
  const onHoldCount = records.filter((record) => record.status === "On Hold").length;
  const completedCount = records.filter(
    (record) => record.status === "Completed",
  ).length;
  const closedCount = records.filter((record) => record.status === "Closed").length;
  const draftCount = records.filter((record) => record.status === "Draft").length;
  const approvedCount = records.filter(
    (record) => record.status === "Approved",
  ).length;
  const cancelledCount = records.filter(
    (record) => record.status === "Cancelled",
  ).length;

  return {
    totalEngagements: records.length,
    active: activeCount,
    forApproval: forApprovalCount,
    onHold: onHoldCount,
    completed: completedCount,
    closed: closedCount,
    draft: draftCount,
    approved: approvedCount,
    cancelled: cancelledCount,
    // Legacy aliases
    planned: draftCount + approvedCount,
    ongoing: activeCount,
  };
}

export function formatEngagementDate(value: string) {
  if (!value) return "N/A";
  const date = new Date(`${value}T00:00:00`);

  return new Intl.DateTimeFormat("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
  }).format(date);
}
