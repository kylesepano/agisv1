import {
  Activity,
  Archive,
  BadgeCheck,
  Bell,
  Bot,
  Blocks,
  BriefcaseBusiness,
  Building2,
  CalendarDays,
  CalendarRange,
  CalendarClock,
  ChartColumnBig,
  ChartNoAxesCombined,
  ClipboardCheck,
  ClipboardList,
  Database,
  FileCheck2,
  FileText,
  Files,
  FileBarChart,
  KeyRound,
  LayoutDashboard,
  ListChecks,
  Link2,
  MessageSquareText,
  Network,
  Play,
  Settings,
  ShieldAlert,
  ShieldCheck,
  SquareCheckBig,
  Target,
  UserRound,
  Upload,
  UsersRound,
  Workflow,
} from "lucide-react";

export const iapPages = [
  {
    label: "IAP Dashboard",
    path: "/internal-audit-planning/dashboard",
    permission: "iap.view",
    icon: LayoutDashboard,
  },
  {
    label: "Strategic Audit Plan",
    path: "/internal-audit-planning/strategic-plan",
    permission: "iap.view",
    icon: ChartNoAxesCombined,
  },
  {
    label: "Audit Universe",
    path: "/internal-audit-planning/audit-universe",
    permission: "iap.view",
    icon: Blocks,
  },
  {
    label: "Baseline Assessment (BAICS)",
    path: "/internal-audit-planning/baics",
    permission: "iap.baics.view",
    icon: ClipboardCheck,
  },
  {
    label: "Control Universe & BAR",
    path: "/internal-audit-planning/baics/control-universe",
    permission: "iap.baics.view",
    icon: ShieldCheck,
  },
  {
    label: "BAICS IAP Integration",
    path: "/internal-audit-planning/baics/integration",
    permission: "iap.baics.view",
    icon: Link2,
  },
  {
    label: "Risk Assessment",
    path: "/internal-audit-planning/risk-assessment",
    permission: "iap.view",
    icon: ShieldAlert,
  },
  {
    label: "Audit Prioritization",
    path: "/internal-audit-planning/prioritization",
    permission: "iap.view",
    icon: ListChecks,
  },
  {
    label: "Annual Audit Plan",
    path: "/internal-audit-planning",
    permission: "iap.view",
    icon: CalendarDays,
    end: true,
  },
  {
    label: "Audit Scheduling",
    path: "/internal-audit-planning/scheduling",
    permission: "iap.view",
    icon: CalendarDays,
  },
  {
    label: "IAP Reports",
    path: "/internal-audit-planning/reports",
    permission: "iap.view",
    icon: FileBarChart,
  },
];

export const aemsPages = [
  {
    screenId: "AEMS-DASHBOARD",
    scrId: "AEMS-DASHBOARD",
    group: "Portfolio",
    label: "AEMS Dashboard",
    scrLabel: "AEMS Dashboard",
    path: "/audit-engagement-management/dashboard",
    permission: "aems.engagement.view",
    icon: LayoutDashboard,
  },
  {
    screenId: "AEMS-REGISTRY",
    scrId: "SCR-210",
    group: "Foundation",
    label: "Audit Engagement Workspace",
    scrLabel: "Audit Engagement Workspace",
    path: "/audit-engagement-management",
    permission: "aems.engagement.view",
    icon: BriefcaseBusiness,
    end: true,
  },
  {
    screenId: "AEMS-SCOPE",
    scrId: "AEMS-SCOPE",
    group: "Foundation",
    label: "Audit Scope",
    scrLabel: "Define Engagement Scope",
    path: "/audit-engagement-management/scope",
    permission: "aems.foundation.view",
    icon: Building2,
  },
  {
    screenId: "AEMS-TEAM",
    scrId: "SCR-213",
    group: "Foundation",
    label: "Audit Team",
    scrLabel: "Assign Audit Team",
    path: "/audit-engagement-management/team",
    permission: "aems.team.view",
    icon: UsersRound,
  },
  {
    screenId: "AEMS-AEO",
    scrId: "SCR-214",
    group: "Foundation",
    label: "Engagement Orders",
    scrLabel: "Prepare Audit Engagement Order",
    path: "/audit-engagement-management/aeo",
    permission: "aems.aeo.view",
    icon: FileText,
  },
  {
    screenId: "AEMS-PLANNING-PACKAGE",
    scrId: "SCR-221",
    group: "Planning",
    label: "Planning Workspace",
    scrLabel: "Engagement Planning Workspace",
    path: "/audit-engagement-management/planning-package",
    permission: "aems.planning-package.view",
    icon: ClipboardCheck,
  },
  {
    screenId: "AEMS-AEP",
    scrId: "SCR-222",
    group: "Planning",
    label: "Engagement Plan",
    scrLabel: "Prepare Audit Engagement Plan",
    path: "/audit-engagement-management/aep",
    permission: "aems.aep.view",
    icon: SquareCheckBig,
  },
  {
    screenId: "AEMS-PROGRAM",
    scrId: "SCR-223",
    group: "Planning",
    label: "Audit Program",
    scrLabel: "Audit Program",
    path: "/audit-engagement-management/audit-program",
    permission: "aems.program.view",
    icon: ListChecks,
  },
  {
    screenId: "AEMS-PROCEDURE",
    scrId: "AEMS-PROCEDURE",
    group: "Planning",
    label: "Audit Procedure Details",
    scrLabel: "Audit Procedure Details",
    path: "/audit-engagement-management/audit-procedure-details",
    permission: "aems.program.view",
    icon: FileCheck2,
  },
  {
    screenId: "AEMS-CONFERENCE-DIALOGUE",
    scrId: "SCR-225",
    group: "Conferences",
    label: "Conference Management",
    scrLabel: "Conference Management",
    path: "/audit-engagement-management/conferences",
    permission: "aems.conference.view",
    icon: MessageSquareText,
  },
  {
    screenId: "AEMS-ENTRY-CONFERENCE",
    scrId: "AEMS-ENTRY-CONFERENCE",
    group: "Conferences",
    label: "Entry Conferences",
    scrLabel: "Entry Conference",
    path: "/audit-engagement-management/entry-conferences",
    permission: "aems.entry-conference.view",
    icon: CalendarClock,
  },
  {
    screenId: "AEMS-EXIT-CONFERENCE",
    scrId: "AEMS-EXIT-CONFERENCE",
    group: "Conferences",
    label: "Exit Conferences",
    scrLabel: "Exit Conference",
    path: "/audit-engagement-management/exit-conferences",
    permission: "aems.conference.view",
    icon: CalendarClock,
  },
  {
    screenId: "AEMS-EXECUTION",
    scrId: "SCR-226",
    group: "Execution",
    label: "Execution Workspace",
    scrLabel: "Audit Execution Workspace",
    path: "/audit-engagement-management/execution",
    permission: "aems.fieldwork.view",
    icon: Play,
  },
  {
    screenId: "AEMS-WORKING-PAPERS",
    scrId: "SCR-228",
    group: "Execution",
    label: "Working Papers & Evidence",
    scrLabel: "Working Papers Workspace",
    path: "/audit-engagement-management/working-papers",
    permission: "aems.working-paper.view",
    icon: Files,
  },
  {
    screenId: "AEMS-EVIDENCE",
    scrId: "SCR-229",
    group: "Execution",
    label: "Evidence Management",
    scrLabel: "Audit Evidence Management",
    path: "/audit-engagement-management/evidence",
    permission: "aems.evidence-request.view",
    icon: FileCheck2,
  },
  {
    screenId: "AEMS-ISSUES",
    scrId: "SCR-230",
    group: "Issues & AFR",
    label: "Audit Issues",
    scrLabel: "Audit Issues Workspace",
    path: "/audit-engagement-management/issues",
    permission: "aems.issue.view",
    icon: ShieldAlert,
  },
  {
    screenId: "AEMS-FINDINGS",
    scrId: "SCR-240",
    group: "Issues & AFR",
    label: "Findings & Recommendations",
    scrLabel: "Audit Finding and Recommendation Details",
    path: "/audit-engagement-management/findings",
    permission: "aems.finding.view",
    icon: ClipboardCheck,
  },
  {
    screenId: "AEMS-RESPONSES",
    scrId: "SCR-241",
    group: "Issues & AFR",
    label: "Auditee Responses",
    scrLabel: "Management Comment Workspace",
    path: "/audit-engagement-management/auditee-responses",
    permission: "aems.management-response.view",
    icon: MessageSquareText,
  },
  {
    screenId: "AEMS-REPORTS",
    scrId: "SCR-250",
    group: "Reporting",
    label: "Audit Reporting Workspace",
    scrLabel: "Audit Reporting Workspace",
    path: "/audit-engagement-management/reports",
    permission: ["aems.report.view", "aems.report.view_issued"],
    icon: FileBarChart,
  },
  {
    screenId: "AEMS-WORK-QUEUES",
    scrId: "AEMS-WORK-QUEUES",
    group: "Operations",
    label: "Operational Work Queues",
    scrLabel: "Operational Work Queues",
    path: "/audit-engagement-management/work-queues",
    permission: "aems.task.view",
    icon: ClipboardList,
  },
  {
    screenId: "AEMS-CALENDAR",
    scrId: "AEMS-CALENDAR",
    group: "Operations",
    label: "Audit Calendar",
    scrLabel: "Audit Calendar and Milestones",
    path: "/audit-engagement-management/calendar",
    permission: "aems.calendar.view",
    icon: CalendarClock,
  },
  {
    screenId: "AEMS-REGISTERS",
    scrId: "AEMS-REGISTERS",
    group: "Reporting",
    label: "Registers & Exports",
    scrLabel: "AEMS Registers and Protected Exports",
    path: "/audit-engagement-management/registers",
    permission: "aems.engagement.view",
    icon: FileBarChart,
  },
  {
    screenId: "AEMS-RECORDS-CLOSURE",
    scrId: "AEMS-RECORDS-CLOSURE",
    group: "Completion & Transfer",
    label: "Records & Administrative Closure",
    scrLabel: "Records and Administrative Closure Workspace",
    path: "/audit-engagement-management/records-closure",
    permission: ["aems.closure.view", "aems.records.view", "aems.retention.view"],
    icon: Archive,
  },
];

/**
 * DGM screens that are intentionally opened inside the engagement workspace
 * or from an action on a parent workspace. They are part of the SCR contract,
 * but are not standalone sidebar destinations.
 */
export const aemsContextualScreens = [
  {
    id: "SCR-211",
    label: "Create / Activate Audit Engagement",
    route: "/audit-engagement-management",
    group: "Foundation",
    parentTab: "Overview",
    permission: "aems.engagement.create",
  },
  {
    id: "SCR-212",
    label: "Define Engagement Scope",
    route: "/audit-engagement-management/scope?engagementId=:engagementId",
    group: "Foundation",
    parentTab: "Overview",
    permission: "aems.engagement.update",
  },
  {
    id: "SCR-220",
    label: "Audit Engagement Details",
    route: "/audit-engagement-management/:engagementId",
    group: "Portfolio",
    parentTab: "Overview",
    permission: "aems.engagement.view",
  },
  {
    id: "SCR-224",
    label: "Audit Procedure Details",
    route: "/audit-engagement-management/audit-procedure-details",
    group: "Planning",
    parentTab: "Planning",
    permission: "aems.program.view",
  },
  {
    id: "SCR-227",
    label: "Fieldwork Record",
    route: "/audit-engagement-management/execution",
    group: "Execution",
    parentTab: "Execution",
    permission: "aems.fieldwork.view",
  },
  {
    id: "SCR-231",
    label: "Create Audit Issue",
    route: "/audit-engagement-management/issues",
    group: "Issues & AFR",
    parentTab: "Audit Issues",
    permission: "aems.issue.create",
  },
  {
    id: "SCR-232",
    label: "Audit Issue Details",
    route: "/audit-engagement-management/issues",
    group: "Issues & AFR",
    parentTab: "Audit Issues",
    permission: "aems.issue.view",
  },
  {
    id: "SCR-242",
    label: "Submit Management Comment",
    route: "/audit-engagement-management/auditee-responses",
    group: "Issues & AFR",
    parentTab: "AFRs",
    permission: "aems.management-response.create",
  },
  {
    id: "SCR-243",
    label: "Reserved",
    route: null,
    group: "Issues & AFR",
    parentTab: "AFRs",
    permission: null,
    status: "reserved",
  },
  {
    id: "SCR-244",
    label: "Prepare Auditor's Rejoinder",
    route: "/audit-engagement-management/auditee-responses",
    group: "Issues & AFR",
    parentTab: "AFRs",
    permission: "aems.management-response.create",
  },
  {
    id: "SCR-251",
    label: "Interim Audit Report",
    route: "/audit-engagement-management/reports?stage=INTERIM_REPORT",
    group: "Reporting",
    parentTab: "Audit Reports",
    permission: ["aems.report.view", "aems.report.view_issued"],
  },
  {
    id: "SCR-252",
    label: "Draft Audit Report",
    route: "/audit-engagement-management/reports?stage=DRAFT_REPORT",
    group: "Reporting",
    parentTab: "Audit Reports",
    permission: ["aems.report.view", "aems.report.view_issued"],
  },
  {
    id: "SCR-253",
    label: "Final Audit Report",
    route: "/audit-engagement-management/reports?stage=FINAL_REPORT",
    group: "Reporting",
    parentTab: "Audit Reports",
    permission: ["aems.report.view", "aems.report.view_issued"],
  },
  {
    id: "SCR-254",
    label: "Report Distribution",
    route: "/audit-engagement-management/reports?stage=DISTRIBUTION",
    group: "Reporting",
    parentTab: "Audit Reports",
    permission: ["aems.report.view", "aems.report.view_issued"],
  },
  {
    id: "SCR-260",
    label: "Engagement Completion and Closure",
    route: "/audit-engagement-management/:engagementId?tab=completion-assessment",
    group: "Completion and Transfer",
    parentTab: "Completion & Transfer",
    permission: "aems.completion-assessment.view",
  },
  {
    id: "SCR-261",
    label: "CMS Transfer and Reconciliation",
    route: "/audit-engagement-management/:engagementId?tab=completion-transfer",
    group: "Completion and Transfer",
    parentTab: "Completion & Transfer",
    permission: "aems.completion-transfer.view",
  },
  {
    id: "SCR-262",
    label: "Completion Checklist and Assessment",
    route: "/audit-engagement-management/:engagementId?tab=completion-assessment",
    group: "Completion and Transfer",
    parentTab: "Completion & Transfer",
    permission: "aems.completion-assessment.view",
  },
  {
    id: "SCR-263",
    label: "Close / Reopen Engagement",
    route: "/audit-engagement-management/:engagementId?tab=closure",
    group: "Completion and Transfer",
    parentTab: "Completion & Transfer",
    permission: "aems.closure.view",
  },
].map((screen) => ({ ...screen, contextual: true }));

function aemsParentTab(page) {
  if (page.group === "Portfolio" || page.group === "Foundation") {
    return "Overview";
  }
  if (page.screenId === "AEMS-ISSUES") return "Audit Issues";
  if (page.group === "Issues & AFR") return "AFRs";
  if (page.group === "Reporting") return "Audit Reports";
  return page.group;
}

/**
 * The controlled AEMS screen registry. This is a frontend usability contract
 * only; permissions and engagement scope remain authoritative in Laravel.
 */
export const aemsScreenRegistry = [
  ...aemsPages.map((page) => ({
    id: page.scrId ?? page.screenId,
    legacyId: page.screenId,
    label: page.scrLabel ?? page.label,
    navigationLabel: page.label,
    route: page.href ?? page.path,
    canonicalRoute: page.path,
    group: page.group,
    permission: page.permission,
    parentTab: aemsParentTab(page),
    parentScreenId: page.scrParentId ?? null,
    contextual: false,
  })),
  ...aemsContextualScreens,
];

export const cmsPages = [
  {
    label: "CMS Dashboard",
    path: "/compliance-management/dashboard",
    permission: "cms.dashboard.view",
    icon: LayoutDashboard,
  },
  {
    label: "Recommendation Registry",
    path: "/compliance-management/recommendations",
    permission: "cms.recommendation.view",
    icon: ClipboardCheck,
  },
  {
    label: "AEO Acknowledgements",
    path: "/compliance-management/aeo-acknowledgements",
    permission: "aems.aeo.acknowledge",
    icon: FileCheck2,
  },
  {
    label: "Entry Conference Acknowledgements",
    path: "/compliance-management/entry-conference-acknowledgements",
    permission: "aems.entry-conference.view",
    icon: CalendarClock,
  },
  {
    label: "Evidence Requests",
    path: "/compliance-management/evidence-requests",
    permission: "aems.evidence-request.view",
    icon: Upload,
  },
  {
    label: "Automation & Candidates",
    path: "/compliance-management/automation",
    permission: "cms.automation.view",
    icon: Bot,
  },
  {
    label: "Reports & Exports",
    path: "/compliance-management/reports",
    permission: "cms.report.view",
    icon: FileBarChart,
  },
];

export const armisPages = [
  {
    label: "Resource Registry",
    path: "/audit-resource-management/resources",
    permission: "armis.resource.view",
    icon: UsersRound,
  },
  {
    label: "Competencies & Certifications",
    path: "/audit-resource-management/competencies",
    permission: "armis.competency.view",
    icon: BadgeCheck,
  },
  {
    label: "Planning & Utilization",
    path: "/audit-resource-management/planning",
    permission: "armis.availability.view",
    icon: CalendarRange,
  },
  {
    label: "Assignments & Actuals",
    path: "/audit-resource-management/assignments",
    permission: "armis.assignment.view",
    icon: ClipboardCheck,
  },
  {
    label: "Provider Monitoring",
    path: "/audit-resource-management/provider-monitoring",
    permission: "armis.provider.view",
    icon: Activity,
  },
  {
    label: "Reports & Administration",
    path: "/audit-resource-management/reports",
    permission: "armis.report.view",
    icon: FileBarChart,
  },
];

export const aisPages = [
  {
    label: "AIS Dashboard",
    path: "/audit-intelligence-system",
    permission: "ais.view",
    icon: LayoutDashboard,
  },
  {
    label: "Integration Health",
    path: "/audit-intelligence-system/integration-health",
    permission: "ais.view",
    icon: Activity,
  },
  {
    label: "AIS Reports & Exports",
    path: "/audit-intelligence-system/reports",
    permission: "ais.view",
    icon: FileBarChart,
  },
];

export const modules = [
  {
    key: "core",
    code: "CORE",
    label: "Core Records",
    path: "/office-registry",
    permission: "offices.view",
    icon: Blocks,
    value: 43,
    note: "Registered Offices",
    tone: "blue",
  },
  {
    key: "iap",
    code: "IAP",
    label: "Internal Audit Planning",
    path: "/internal-audit-planning/dashboard",
    permission: "iap.view",
    icon: CalendarDays,
    value: 3,
    note: "Plans in Progress",
    tone: "blue",
    children: iapPages,
  },
  {
    key: "aem",
    code: "AEMS",
    label: "Audit Engagement Management",
    path: "/audit-engagement-management/dashboard",
    permission: "aems.engagement.view",
    icon: ShieldCheck,
    value: 18,
    note: "Active Engagements",
    tone: "green",
    children: aemsPages,
  },
  {
    key: "afr",
    code: "AFR",
    label: "Audit Finding & Recommendation",
    path: "/audit-findings-recommendations",
    permission: "afr.view",
    icon: ShieldAlert,
    value: 43,
    note: "Findings Issued",
    tone: "orange",
  },
  {
    key: "cms",
    code: "CMS",
    label: "Compliance Management",
    path: "/compliance-management/dashboard",
    permission: ["cms.dashboard.view", "cms.recommendation.view"],
    icon: SquareCheckBig,
    tone: "purple",
    children: cmsPages,
  },
  {
    key: "arms",
    code: "ARMIS",
    label: "Audit Resource Management",
    path: "/audit-resource-management/resources",
    permission: "armis.resource.view",
    icon: UsersRound,
    value: 12,
    note: "Active Auditors",
    tone: "teal",
    children: armisPages,
  },
  {
    key: "ais",
    code: "AIS",
    label: "Audit Intelligence System",
    path: "/audit-intelligence-system",
    permission: "ais.view",
    icon: ChartNoAxesCombined,
    value: "—",
    note: "Analytics and reports",
    tone: "yellow",
    children: aisPages,
  },
];

export const navigationSections = [
  {
    key: "primary",
    items: [
      {
        label: "Dashboard",
        path: "/dashboard",
        permission: "dashboard.view",
        icon: LayoutDashboard,
      },
      ...modules.filter(
        (module) => module.key !== "afr" && module.key !== "core",
      ),
    ],
  },
  {
    key: "registries",
    title: "Master Registries",
    items: [
      {
        label: "Office Registry",
        path: "/office-registry",
        permission: "offices.view",
        icon: Building2,
      },
      {
        label: "Audit Area Registry",
        path: "/audit-area-registry",
        permission: "audit_areas.view",
        icon: Network,
      },
      {
        label: "Audit Focus Registry",
        path: "/audit-focus-registry",
        permission: "audit_focus.view",
        icon: Target,
      },
      {
        label: "User Registry",
        path: "/user-registry",
        permission: "users.view",
        icon: UserRound,
      },
      {
        label: "Access Role Registry",
        path: "/access-role-registry",
        permission: "roles.view",
        icon: KeyRound,
      },
      {
        label: "Permission Registry",
        path: "/permission-registry",
        permission: "permissions.view",
        icon: ListChecks,
      },
      {
        label: "Master Lists",
        path: "/master-lists",
        permission: "master_lists.view",
        icon: Database,
      },
    ],
  },
  {
    key: "administration",
    title: "Administration",
    items: [
      {
        label: "Document Management",
        path: "/document-management",
        permission: "documents.view",
        icon: FileText,
      },
      {
        label: "Notifications",
        path: "/notifications",
        permission: "notifications.view",
        icon: Bell,
      },
      {
        label: "Workflow Management",
        path: "/workflow-management",
        permission: "workflows.view",
        icon: Workflow,
      },
      {
        label: "Activity Log",
        path: "/activity-log",
        permission: "activity_logs.view",
        icon: Activity,
      },
      {
        label: "Audit Trail",
        path: "/audit-trail",
        permission: "audit_logs.view",
        icon: Workflow,
      },
      {
        label: "System Configuration",
        path: "/system-configuration",
        permission: "system_configuration.view",
        icon: Settings,
      },
      {
        label: "Administrative Reports",
        path: "/administrative-reports",
        permission: "administrative_reports.view",
        icon: ChartColumnBig,
      },
    ],
  },
];

export const pageRoutes = [
  ...modules.flatMap((module) => [module, ...(module.children ?? [])]),
  ...navigationSections.flatMap((section) => section.items),
].filter(
  (item, index, items) =>
    item.path !== "/dashboard" &&
    items.findIndex((candidate) => candidate.path === item.path) === index,
);

export const allNavigationItems = navigationSections.flatMap(
  (section) => section.items,
);

export function hasPermission(user, permission) {
  if (Array.isArray(permission)) {
    return permission.some((code) => user?.permissions?.includes(code));
  }
  return Boolean(user?.permissions?.includes(permission));
}

export function notificationPathForUser(user, notification) {
  const type = String(notification?.type ?? "").toUpperCase();
  const title = String(notification?.title ?? "").toUpperCase();
  const metadata = notification?.metadata ?? {};
  const isAuditeeRecipient = hasPermission(user, "aems.aeo.acknowledge")
    && !hasPermission(user, "aems.engagement.view");
  const isAuthorizationNotice = type.includes("PREPARE_AUTHORIZATION")
    || type.includes("AUTHORIZATION_PREPARATION")
    || String(metadata.toStatus ?? "").toUpperCase() === "AUTHORIZATION_PREPARATION"
    || title.includes("AUTHORIZATION PREPARATION");

  if (isAuditeeRecipient && isAuthorizationNotice) {
    return "/compliance-management/aeo-acknowledgements";
  }

  return notification?.actionUrl || "/notifications";
}

export function visibleFor(user, items) {
  return items.filter((item) => hasPermission(user, item.permission));
}

export function pageForPath(pathname) {
  if (/^\/audit-resource-management\/competencies(?:\/\d+)?$/.test(pathname)) {
    return {
      label: "ARMIS Competencies & Certifications",
      icon: BadgeCheck,
      permission: "armis.competency.view",
    };
  }

  if (pathname === "/audit-resource-management/planning") {
    return {
      label: "ARMIS Planning & Utilization",
      icon: CalendarRange,
      permission: "armis.availability.view",
    };
  }

  if (pathname === "/audit-resource-management/assignments") {
    return {
      label: "ARMIS Assignments & Actuals",
      icon: ClipboardCheck,
      permission: "armis.assignment.view",
    };
  }

  if (pathname === "/audit-resource-management/reports") {
    return {
      label: "ARMIS Reports & Administration",
      icon: FileBarChart,
      permission: "armis.report.view",
    };
  }

  if (pathname === "/audit-resource-management/provider-monitoring") {
    return {
      label: "ARMIS Provider Monitoring",
      icon: Activity,
      permission: "armis.provider.view",
    };
  }

  if (/^\/audit-resource-management\/resources(?:\/\d+)?$/.test(pathname)) {
    return {
      label: "ARMIS Resource Registry",
      icon: UsersRound,
      permission: "armis.resource.view",
    };
  }

  if (/^\/internal-audit-planning\/\d+$/.test(pathname)) {
    return {
      label: "Annual Audit Plan",
      icon: CalendarDays,
      permission: "iap.view",
    };
  }

  if (/^\/audit-engagement-management\/\d+$/.test(pathname)) {
    return {
      label: "Engagement Details",
      icon: ShieldCheck,
      permission: "aems.engagement.view",
    };
  }

  if (
    /^\/compliance-management\/recommendations\/\d+\/action-plan$/.test(
      pathname,
    )
  ) {
    return {
      label: "Corrective Action Plan",
      icon: ListChecks,
      permission: "cms.action-plan.view",
    };
  }

  if (
    /^\/compliance-management\/recommendations\/\d+\/progress-updates(?:\/\d+)?$/.test(
      pathname,
    )
  ) {
    return {
      label: "Progress Updates",
      icon: ClipboardCheck,
      permission: "cms.progress.view",
    };
  }

  if (
    /^\/compliance-management\/recommendations\/\d+\/validations(?:\/\d+)?$/.test(
      pathname,
    )
  ) {
    return {
      label: "Independent Validation",
      icon: ClipboardCheck,
      permission: "cms.validation.view",
    };
  }

  if (/^\/compliance-management\/recommendations\/\d+$/.test(pathname)) {
    return {
      label: "Recommendation Details",
      icon: ClipboardCheck,
      permission: "cms.recommendation.view",
    };
  }

  if (pathname === "/profile") {
    return {
      label: "My Profile",
      icon: UserRound,
      permission: "profile.view",
    };
  }

  if (pathname === "/office-registry") {
    return navigationSections
      .flatMap((section) => section.items)
      .find((item) => item.path === pathname);
  }

  if (pathname === "/dashboard") {
    return {
      label: "Dashboard",
      icon: LayoutDashboard,
      permission: "dashboard.view",
    };
  }

  return pageRoutes.find((item) => item.path === pathname) ?? null;
}
