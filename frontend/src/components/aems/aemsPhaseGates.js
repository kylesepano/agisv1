/**
 * Frontend presentation gates for the aggregate engagement lifecycle.
 *
 * These gates do not replace backend authorization or transition checks. They
 * keep users from opening a later workspace before its prerequisite phase and
 * explain the action that will unlock it.
 */
const STATUS_ORDER = [
  "DRAFT",
  "AUTHORIZATION_PREPARATION",
  "RETURNED_FOR_REVISION",
  "AUTHORIZED",
  "ENGAGEMENT_PLANNING",
  "ENTRY_CONFERENCE",
  "FIELDWORK",
  "FINDINGS_COMMUNICATION",
  "EXIT_CONFERENCE",
  "REPORTING",
  "ISSUED",
  "CLOSURE_REVIEW",
  "COMPLETED",
  "CLOSED",
];

const labels = {
  DRAFT: "Draft",
  AUTHORIZATION_PREPARATION: "Authorization Preparation",
  RETURNED_FOR_REVISION: "Returned for Revision",
  AUTHORIZED: "Authorized",
  ENGAGEMENT_PLANNING: "Engagement Planning",
  ENTRY_CONFERENCE: "Entry Conference",
  FIELDWORK: "Fieldwork",
  FINDINGS_COMMUNICATION: "Findings Communication",
  EXIT_CONFERENCE: "Exit Conference",
  REPORTING: "Reporting",
  ISSUED: "Issued",
  CLOSURE_REVIEW: "Closure Review",
  COMPLETED: "Completed",
  CLOSED: "Closed",
  SUSPENDED: "Suspended",
  CANCELLED: "Cancelled",
};

const gates = {
  planning: {
    minimumStatus: "ENGAGEMENT_PLANNING",
    title: "Planning Workspace is locked",
    reason:
      "Approve and issue the Engagement Order, then record the auditee office acknowledgement before planning can begin.",
    actionLabel: "Open Engagement Order",
    action: "aeo",
  },
  execution: {
    minimumStatus: "FIELDWORK",
    title: "Execution is locked",
    reason:
      "Complete and approve the Planning Workspace, conduct the Entry Conference, and move the engagement to Fieldwork before execution records can be created.",
    actionLabel: "Open Lifecycle",
    action: "lifecycle",
  },
  issues: {
    minimumStatus: "FIELDWORK",
    title: "Audit Issues are locked",
    reason:
      "Complete and approve the Planning Workspace, conduct the Entry Conference, and move the engagement to Fieldwork before recording audit issues.",
    actionLabel: "Open Lifecycle",
    action: "lifecycle",
  },
  afrs: {
    minimumStatus: "FINDINGS_COMMUNICATION",
    title: "AFRs are locked",
    reason:
      "Complete fieldwork and move the engagement to Findings Communication before findings and recommendations can be prepared.",
    actionLabel: "Open Lifecycle",
    action: "lifecycle",
  },
  conferences: {
    minimumStatus: "ENTRY_CONFERENCE",
    title: "Conferences are locked",
    reason:
      "Authorize the engagement and move it to the Entry Conference phase before scheduling conference records.",
    actionLabel: "Open Lifecycle",
    action: "lifecycle",
  },
  reports: {
    minimumStatus: "REPORTING",
    title: "Audit Reporting Workspace is locked",
    reason:
      "Complete findings communication and move the engagement to Reporting before assembling a report.",
    actionLabel: "Open Lifecycle",
    action: "lifecycle",
  },
  completion: {
    minimumStatus: "CLOSURE_REVIEW",
    title: "Completion & Transfer is locked",
    reason:
      "Issue the final report and move the engagement to Closure Review before completion, CMS transfer, and records closure.",
    actionLabel: "Open Lifecycle",
    action: "lifecycle",
  },
};

function statusFor(engagementOrStatus) {
  return typeof engagementOrStatus === "string"
    ? engagementOrStatus
    : engagementOrStatus?.status;
}

export function statusLabel(status) {
  return labels[status] ?? status ?? "Unknown";
}

export function getAemsWorkspaceGate(key, engagementOrStatus) {
  const gate = gates[key];
  if (!gate) return { unlocked: true, key };

  const status = statusFor(engagementOrStatus);
  // Standalone workspaces load their engagement asynchronously. Do not flash
  // a locked navigation state while the context record is still loading.
  if (!status) {
    return { ...gate, key, status, unlocked: true, pending: true };
  }
  const currentIndex = STATUS_ORDER.indexOf(status);
  const minimumIndex = STATUS_ORDER.indexOf(gate.minimumStatus);
  const unlocked =
    currentIndex >= 0 && minimumIndex >= 0 && currentIndex >= minimumIndex;

  return {
    ...gate,
    key,
    status,
    unlocked,
    currentStatusLabel: statusLabel(status),
  };
}

export function workspaceActionPath(action, engagementId) {
  const id = encodeURIComponent(engagementId);
  if (action === "aeo") {
    return `/audit-engagement-management/aeo?engagementId=${id}`;
  }
  if (action === "lifecycle") {
    return `/audit-engagement-management/${id}?tab=lifecycle`;
  }
  if (action === "planning") {
    return `/audit-engagement-management/planning-package?engagementId=${id}`;
  }
  return `/audit-engagement-management/${id}`;
}

export const AEMS_WORKSPACE_GATES = gates;
