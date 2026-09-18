import {
  Activity,
  BriefcaseBusiness,
  CalendarCheck2,
  ClipboardCheck,
  FileBarChart,
  Files,
  ListChecks,
  LockKeyhole,
  ShieldAlert,
} from "lucide-react";
import { Link, useLocation } from "react-router";
import { useAuth } from "../../auth/auth-context";
import { hasPermission } from "../../config/navigation";
import {
  getAemsWorkspaceGate,
} from "./aemsPhaseGates";

const tabs = [
  {
    key: "overview",
    label: "Overview",
    icon: BriefcaseBusiness,
    href: ({ id }) => `/audit-engagement-management/${id}`,
  },
  {
    key: "planning",
    label: "Planning Workspace",
    icon: ListChecks,
    permission: [
      "aems.planning-package.view",
      "aems.aep.view",
      "aems.program.view",
    ],
    href: ({ id }) =>
      `/audit-engagement-management/planning-package?engagementId=${id}`,
    paths: [
      "/audit-engagement-management/planning-package",
      "/audit-engagement-management/aep",
      "/audit-engagement-management/audit-program",
      "/audit-engagement-management/audit-procedure-details",
    ],
  },
  {
    key: "execution",
    label: "Execution",
    icon: Files,
    permission: ["aems.fieldwork.view", "aems.evidence-request.view"],
    href: ({ id }) =>
      `/audit-engagement-management/execution?engagementId=${id}`,
    paths: [
      "/audit-engagement-management/execution",
      "/audit-engagement-management/working-papers",
      "/audit-engagement-management/evidence",
    ],
  },
  {
    key: "issues",
    label: "Audit Issues",
    icon: ShieldAlert,
    permission: "aems.issue.view",
    href: ({ id }) => `/audit-engagement-management/issues?engagementId=${id}`,
    paths: ["/audit-engagement-management/issues"],
  },
  {
    key: "afrs",
    label: "AFRs",
    icon: ClipboardCheck,
    permission: "aems.finding.view",
    href: ({ id }) =>
      `/audit-engagement-management/findings?engagementId=${id}`,
    paths: [
      "/audit-engagement-management/findings",
      "/audit-engagement-management/auditee-responses",
    ],
  },
  {
    key: "conferences",
    label: "Conferences",
    icon: CalendarCheck2,
    permission: "aems.conference.view",
    href: ({ id }) =>
      `/audit-engagement-management/conferences?engagementId=${id}`,
    paths: [
      "/audit-engagement-management/entry-conferences",
      "/audit-engagement-management/entry-conference",
      "/audit-engagement-management/exit-conferences",
      "/audit-engagement-management/conferences",
    ],
  },
  {
    key: "reports",
    label: "Audit Reporting Workspace",
    icon: FileBarChart,
    permission: ["aems.report.view", "aems.report.view_issued"],
    href: ({ id }) => `/audit-engagement-management/reports?engagementId=${id}`,
    paths: ["/audit-engagement-management/reports"],
  },
  {
    key: "completion",
    label: "Completion & Transfer",
    icon: BriefcaseBusiness,
    permission: [
      "aems.completion-assessment.view",
      "aems.records.view",
      "aems.calendar.view",
    ],
    href: ({ id }) =>
      `/audit-engagement-management/${id}?tab=completion-assessment`,
    queryTabs: [
      "completion-assessment",
      "closure",
      "document-index",
      "retention",
      "records",
      "calendar",
      "lessons-learned",
    ],
  },
  {
    key: "activity",
    label: "Activity",
    icon: Activity,
    permission: ["activity_logs.view", "audit_logs.view"],
    href: ({ id }) => `/audit-trail?module=AEMS&recordId=${id}`,
    paths: ["/audit-trail", "/activity-log"],
  },
];

function isCurrentTab(tab, pathname, searchParams, engagementId) {
  if (tab.key === "overview") {
    return (
      pathname === `/audit-engagement-management/${engagementId}` &&
      !searchParams.get("tab")
    );
  }

  // Query-tab names belong to the engagement detail route.  Do not let a
  // stale `tab` query string on a standalone workspace (for example Planning
  // Workspace) light up Completion & Transfer at the same time.
  if (
    tab.queryTabs?.includes(searchParams.get("tab")) &&
    pathname === `/audit-engagement-management/${engagementId}`
  ) {
    return true;
  }
  if (
    tab.paths?.some((path) => pathname === path || pathname.startsWith(path))
  ) {
    const contextId =
      searchParams.get("engagementId") ?? searchParams.get("recordId");
    return contextId === String(engagementId);
  }
  return false;
}

export default function AemsEngagementWorkspaceNav({
  engagementId,
  engagement,
  engagementStatus,
}) {
  const { user } = useAuth();
  const location = useLocation();
  const searchParams = new URLSearchParams(location.search);
  const visibleTabs = tabs.filter(
    (tab) => !tab.permission || hasPermission(user, tab.permission),
  );

  return (
    <nav
      aria-label="Engagement workspace tabs"
      className="aems-workspace-tabs mb-4 min-w-0 max-w-full overflow-hidden rounded-xl border border-slate-200 bg-white p-1 shadow-sm"
      data-testid="aems-engagement-tabs"
    >
      <div className="aems-workspace-tabs-scroll flex min-w-0 max-w-full flex-nowrap gap-1 overflow-x-scroll overflow-y-hidden sm:flex-wrap sm:overflow-visible">
        {visibleTabs.map((tab) => {
          const Icon = tab.icon;
          const active = isCurrentTab(
            tab,
            location.pathname,
            searchParams,
            engagementId,
          );
          const gate = getAemsWorkspaceGate(
            tab.key,
            engagement ?? engagementStatus,
          );
          const locked = !gate.unlocked;
          return (
            locked ? (
              <span
                aria-disabled="true"
                className="inline-flex min-h-10 min-w-max shrink-0 cursor-not-allowed items-center justify-center gap-2 px-3 text-center text-xs font-bold leading-4 text-slate-400 sm:flex-none sm:px-4 sm:text-sm"
                key={tab.key}
                title={`${gate.title}: ${gate.reason}`}
              >
                <LockKeyhole size={14} />
                <Icon size={15} />
                {tab.label}
              </span>
            ) : (
              <Link
                aria-current={active ? "page" : undefined}
                className={`inline-flex min-h-10 min-w-max shrink-0 items-center justify-center gap-2 px-3 text-center text-xs font-bold leading-4 transition sm:flex-none sm:px-4 sm:text-sm ${
                  active
                    ? "bg-sky-700 text-white shadow-sm"
                    : "text-slate-600 hover:bg-slate-100 hover:text-sky-800"
                }`}
                key={tab.key}
                to={tab.href({ id: engagementId })}
              >
                <Icon size={15} />
                {tab.label}
              </Link>
            )
          );
        })}
      </div>
    </nav>
  );
}
