import { aemsContextualScreens, aemsPages } from "../../../src/config/navigation.js";

const pagePermissions = [
  ...aemsPages.flatMap((page) =>
    Array.isArray(page.permission) ? page.permission : [page.permission],
  ),
  ...aemsContextualScreens.flatMap((screen) =>
    Array.isArray(screen.permission) ? screen.permission : [screen.permission],
  ),
].filter(Boolean);

const allAemsPermissions = [...new Set(pagePermissions)];

/**
 * Role-by-role navigation contract. The permission arrays intentionally use
 * the same stable codes seeded by RolePermissionSeeder; the UI remains
 * permission-driven and does not special-case role names.
 */
export const aemsRoleNavigationMatrix = [
  {
    role: "platform_admin",
    permissions: [
      "aems.engagement.view",
      "aems.foundation.view",
      "aems.planning-package.view",
      "aems.team.view",
      "aems.fieldwork.view",
      "aems.entry-conference.view",
      "aems.report.view_issued",
      "aems.completion-assessment.view",
      "aems.completion-transfer.view",
      "aems.closure.view",
      "aems.document-index.view",
      "aems.retention.view",
      "aems.records.view",
      "aems.calendar.view",
    ],
    visiblePages: ["AEMS Dashboard", "Engagement Registry", "Planning Package", "Audit Reporting Workspace"],
    hiddenPages: ["Engagement Orders", "Audit Program"],
  },
  {
    role: "agis_admin",
    permissions: [
      "aems.engagement.view",
      "aems.team.view",
      "aems.foundation.view",
      "aems.planning-package.view",
      "aems.report.view_issued",
      "aems.completion-assessment.view",
      "aems.closure.view",
      "aems.document-index.view",
      "aems.retention.view",
      "aems.records.view",
      "aems.calendar.view",
    ],
    visiblePages: ["AEMS Dashboard", "Engagement Registry", "Planning Package", "Audit Reporting Workspace"],
    hiddenPages: ["Engagement Orders", "Audit Program"],
  },
  {
    role: "cias_management",
    permissions: allAemsPermissions,
    visiblePages: ["AEMS Dashboard", "Engagement Orders", "Audit Program", "Audit Reporting Workspace"],
    hiddenPages: [],
  },
  {
    role: "agis_user",
    permissions: allAemsPermissions,
    visiblePages: ["AEMS Dashboard", "Working Papers & Evidence", "Audit Issues", "Findings & Recommendations"],
    hiddenPages: [],
  },
  {
    role: "auditee_representative",
    permissions: [
      "aems.finding.view",
      "aems.afr.view",
      "aems.management-response.view",
      "aems.entry-conference.view",
      "aems.conference.view",
      "aems.evidence-request.view",
      "aems.report.view_issued",
    ],
    visiblePages: ["Findings & Recommendations", "Auditee Responses", "Audit Reporting Workspace"],
    hiddenPages: ["Engagement Registry", "Audit Program", "Working Papers & Evidence"],
  },
  {
    role: "read_only",
    permissions: ["aems.report.view_issued", "aems.review-note.view", "aems.task.view"],
    visiblePages: ["Audit Reporting Workspace"],
    hiddenPages: ["Engagement Registry", "Audit Issues", "Working Papers & Evidence"],
  },
];

