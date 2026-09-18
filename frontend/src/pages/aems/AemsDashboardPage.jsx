import { useCallback, useEffect, useMemo, useState } from "react";
import {
  AlertTriangle,
  ArrowRight,
  BriefcaseBusiness,
  CheckCircle2,
  ChevronDown,
  ChevronUp,
  ClipboardClock,
  Download,
  FileCheck2,
  FileClock,
  FileInput,
  FileWarning,
  Gauge,
  ListChecks,
  ListTodo,
  MessageSquareWarning,
  Network,
  PlugZap,
  RefreshCw,
  Search,
  SearchCheck,
  ShieldCheck,
  ShieldAlert,
  TimerOff,
  BellRing,
  CalendarClock,
  X,
} from "lucide-react";
import { Link } from "react-router";
import RegistryHeader from "../../components/ui/RegistryHeader";
import StatusBadge from "../../components/ui/StatusBadge";
import { aemsDashboardApi } from "../../services/api";

const cardDefinitions = [
  {
    key: "activeEngagements",
    label: "Active engagements",
    note: "Open portfolio",
    icon: BriefcaseBusiness,
    tone: "sky",
  },
  {
    key: "engagementsInPlanning",
    label: "In planning",
    note: "Through AEP",
    icon: ClipboardClock,
    tone: "indigo",
  },
  {
    key: "engagementsInFieldwork",
    label: "In fieldwork",
    note: "Active execution",
    icon: Gauge,
    tone: "cyan",
  },
  {
    key: "overdueProcedures",
    label: "Overdue procedures",
    note: "Past target date",
    icon: TimerOff,
    tone: "rose",
  },
  {
    key: "workingPapersAwaitingReview",
    label: "WP awaiting review",
    note: "Submitted or resubmitted",
    icon: FileClock,
    tone: "amber",
  },
  {
    key: "reportsPendingApproval",
    label: "Reports pending approval",
    note: "In review queue",
    icon: FileCheck2,
    tone: "blue",
  },
  {
    key: "engagementsReadyForClosure",
    label: "Ready for closure",
    note: "Pre-closure gates met",
    icon: CheckCircle2,
    tone: "emerald",
  },
  {
    key: "evidenceRequestsAwaitingResponse",
    label: "Evidence requests",
    note: "Awaiting response",
    icon: FileInput,
    tone: "teal",
  },
  {
    key: "evidenceGaps",
    label: "Evidence gaps",
    note: "Restricted or limited",
    icon: FileWarning,
    tone: "fuchsia",
  },
  {
    key: "findingsAwaitingReview",
    label: "Findings awaiting review",
    note: "Pending or resubmitted",
    icon: SearchCheck,
    tone: "purple",
  },
  {
    key: "findingsAwaitingManagementResponse",
    label: "Findings awaiting response",
    note: "Management dialogue",
    icon: MessageSquareWarning,
    tone: "orange",
  },
  {
    key: "upcomingConferences",
    label: "Upcoming conferences",
    note: "Entry and exit · 30 days",
    icon: CalendarClock,
    tone: "violet",
  },
  {
    key: "cmsTransferExceptions",
    label: "CMS transfer exceptions",
    note: "Open reconciliation items",
    icon: ShieldAlert,
    tone: "red",
  },
  {
    key: "reviewNotesAwaitingReview",
    label: "Review Notes",
    note: "Draft notes",
    icon: ListChecks,
    tone: "slate",
  },
  {
    key: "openTasks",
    label: "Open tasks",
    note: "Due and in progress",
    icon: ListTodo,
    tone: "lime",
  },
  {
    key: "escalationCandidates",
    label: "Escalation candidates",
    note: "Reviewable signals",
    icon: BellRing,
    tone: "yellow",
  },
];

const toneClasses = {
  sky: "border-sky-200 bg-sky-50 text-sky-700",
  indigo: "border-indigo-200 bg-indigo-50 text-indigo-700",
  cyan: "border-cyan-200 bg-cyan-50 text-cyan-700",
  rose: "border-rose-200 bg-rose-50 text-rose-700",
  amber: "border-amber-200 bg-amber-50 text-amber-700",
  orange: "border-orange-200 bg-orange-50 text-orange-700",
  violet: "border-violet-200 bg-violet-50 text-violet-700",
  blue: "border-blue-200 bg-blue-50 text-blue-700",
  emerald: "border-emerald-200 bg-emerald-50 text-emerald-700",
  teal: "border-teal-200 bg-teal-50 text-teal-700",
  fuchsia: "border-fuchsia-200 bg-fuchsia-50 text-fuchsia-700",
  purple: "border-purple-200 bg-purple-50 text-purple-700",
  red: "border-red-200 bg-red-50 text-red-700",
  slate: "border-slate-200 bg-slate-50 text-slate-700",
  lime: "border-lime-200 bg-lime-50 text-lime-700",
  yellow: "border-yellow-200 bg-yellow-50 text-yellow-700",
};

const statusTones = {
  COMPLETE: "success",
  READY: "success",
  NOT_APPLICABLE: "inactive",
  AWAITING_REVIEW: "warning",
  SCHEDULED: "info",
  IN_PROGRESS: "info",
  RETURNED: "danger",
  OVERDUE: "danger",
  BLOCKED: "danger",
  NOT_STARTED: "inactive",
};

const healthLabels = {
  ON_TRACK: "On track",
  OVERDUE: "Needs attention",
  BLOCKED: "Blocked",
  READY_FOR_CLOSURE: "Ready for closure",
};

const healthTones = {
  ON_TRACK: "info",
  OVERDUE: "danger",
  BLOCKED: "danger",
  READY_FOR_CLOSURE: "success",
};

function titleCase(value) {
  return String(value ?? "")
    .toLowerCase()
    .replaceAll("_", " ")
    .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function formatDate(value) {
  if (!value) return "Not set";
  return new Intl.DateTimeFormat("en-PH", {
    month: "short",
    day: "numeric",
    year: "numeric",
  }).format(new Date(`${value}T00:00:00`));
}

function MetricCard({ definition, value, loading }) {
  const Icon = definition.icon;
  return (
    <article
      className={`flex min-h-28 min-w-0 items-start gap-3 rounded-xl border p-4 shadow-sm transition duration-200 hover:-translate-y-1 hover:shadow-md ${toneClasses[definition.tone]}`}
    >
      <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-white/90 shadow-sm ring-1 ring-black/5">
        <Icon size={19} />
      </span>
      <div className="min-w-0">
        <strong className="block text-2xl leading-none text-slate-950">
          {loading ? "—" : value}
        </strong>
        <span className="mt-2 block text-xs font-bold uppercase leading-4 tracking-wide">
          {definition.label}
        </span>
        <span className="mt-1 block text-xs leading-4 opacity-80">
          {definition.note}
        </span>
      </div>
    </article>
  );
}

function queueItemUrl(queue, item) {
  if (item.route) return item.route;
  if (!queue.route) return "#";
  return item.engagement?.id
    ? `${queue.route}${queue.route.includes("?") ? "&" : "?"}engagementId=${item.engagement.id}`
    : queue.route;
}

function WorkQueuePanel({ queue, loading }) {
  if (loading) {
    return (
      <div className="animate-pulse rounded-xl border border-slate-200 bg-white p-4">
        <div className="h-4 w-1/2 rounded bg-slate-200" />
        <div className="mt-4 h-10 rounded bg-slate-100" />
        <div className="mt-2 h-10 rounded bg-slate-100" />
      </div>
    );
  }

  return (
    <article className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-sky-200 hover:shadow-md">
      <div className="flex items-start justify-between gap-3">
        <div>
          <h3 className="text-sm font-bold text-slate-900">{queue.label}</h3>
          <p className="mt-1 text-xs text-slate-500">
            {queue.count} item{queue.count === 1 ? "" : "s"} in your scope
          </p>
        </div>
        <span
          className={`grid h-9 min-w-9 place-items-center rounded-lg text-sm font-extrabold ${queue.count > 0 ? "bg-amber-50 text-amber-700" : "bg-slate-100 text-slate-500"}`}
        >
          {queue.count}
        </span>
      </div>
      {queue.items?.length > 0 ? (
        <div className="mt-3 space-y-2">
          {queue.items.slice(0, 3).map((item) => (
            <Link
              className="block rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 transition hover:border-sky-200 hover:bg-sky-50"
              key={`${queue.key}-${item.id}`}
              to={queueItemUrl(queue, item)}
            >
              <span className="block truncate text-xs font-bold text-slate-800">
                {item.code || item.title || "Queue item"}
              </span>
              <span className="mt-0.5 block truncate text-[11px] text-slate-500">
                {item.engagement?.code || item.title || item.status || "Open"}
              </span>
            </Link>
          ))}
        </div>
      ) : (
        <p className="mt-4 rounded-lg bg-slate-50 px-3 py-3 text-xs text-slate-500">
          Nothing needs attention here.
        </p>
      )}
      {queue.count > 0 && queue.route && (
        <Link
          className="mt-3 inline-flex items-center gap-1 text-xs font-bold text-sky-700 hover:text-sky-900"
          to={queue.route}
        >
          Open queue <ArrowRight size={13} />
        </Link>
      )}
    </article>
  );
}

function PhaseSummary({ phases, loading }) {
  return (
    <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div>
          <h2 className="font-bold text-slate-900">Engagements by phase</h2>
          <p className="mt-1 text-xs text-slate-500">
            Scope-aware portfolio distribution.
          </p>
        </div>
        <Network className="text-sky-600" size={18} />
      </div>
      <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        {(loading
          ? Array.from({ length: 5 }, (_, index) => ({
              key: index,
              label: "Loading",
              count: "—",
            }))
          : phases
        ).map((phase) => (
          <div className="rounded-lg bg-slate-50 px-3 py-3" key={phase.key}>
            <strong className="block text-xl text-slate-900">
              {phase.count}
            </strong>
            <span className="mt-1 block text-xs font-bold uppercase tracking-wide text-slate-500">
              {phase.label}
            </span>
          </div>
        ))}
      </div>
    </section>
  );
}

function NotificationPanel({ notifications, loading }) {
  return (
    <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="font-bold text-slate-900">Notification monitoring</h2>
          <p className="mt-1 text-xs text-slate-500">
            Your actionable AEMS and system notifications.
          </p>
        </div>
        <Link
          className="inline-flex items-center gap-1 text-xs font-bold text-sky-700"
          to="/notifications"
        >
          Open notifications <ArrowRight size={13} />
        </Link>
      </div>
      {loading ? (
        <div className="mt-4 h-16 animate-pulse rounded-lg bg-slate-100" />
      ) : (
        <>
          <div className="mt-4 grid gap-2 sm:grid-cols-3">
            <div className="rounded-lg bg-sky-50 px-3 py-3">
              <strong className="block text-lg text-sky-800">
                {notifications.unread ?? 0}
              </strong>
              <span className="text-xs font-bold text-sky-700">Unread</span>
            </div>
            <div className="rounded-lg bg-indigo-50 px-3 py-3">
              <strong className="block text-lg text-indigo-800">
                {notifications.aemsUnread ?? 0}
              </strong>
              <span className="text-xs font-bold text-indigo-700">
                AEMS unread
              </span>
            </div>
            <div className="rounded-lg bg-rose-50 px-3 py-3">
              <strong className="block text-lg text-rose-800">
                {notifications.overdue ?? 0}
              </strong>
              <span className="text-xs font-bold text-rose-700">Overdue</span>
            </div>
          </div>
          {notifications.recent?.length > 0 && (
            <div className="mt-3 space-y-2">
              {notifications.recent.slice(0, 3).map((notification) => (
                <Link
                  className="block rounded-lg border border-slate-100 px-3 py-2 hover:bg-slate-50"
                  key={notification.id}
                  to={notification.actionUrl || "/notifications"}
                >
                  <span className="block truncate text-xs font-bold text-slate-800">
                    {notification.title}
                  </span>
                  <span className="mt-0.5 block truncate text-[11px] text-slate-500">
                    {notification.message}
                  </span>
                </Link>
              ))}
            </div>
          )}
        </>
      )}
    </section>
  );
}

function IntegrationStrip({ integrations, loading }) {
  const items = [
    {
      key: "core",
      label: "Core services",
      detail: `${integrations.core?.capabilities?.length ?? 0} shared capabilities`,
      healthy: integrations.core?.available,
    },
    {
      key: "iap",
      label: "IAP source",
      detail:
        integrations.iap?.eligibleEngagements == null
          ? "Approved-plan import connected"
          : `${integrations.iap.eligibleEngagements} approved engagement options`,
      healthy: integrations.iap?.available,
    },
    {
      key: "cms",
      label: "CMS intake",
      detail:
        integrations.cms?.transferredRecommendations == null
          ? "Immutable intake connected"
          : `${integrations.cms.transferredRecommendations} recommendations transferred`,
      healthy: integrations.cms?.available,
    },
    {
      key: "armis",
      label: "Resource provider",
      detail: "ARMIS authoritative",
      healthy: integrations.armis?.available,
    },
  ];

  return (
    <section className="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
      <header className="flex items-center gap-2 border-b border-slate-200 px-4 py-3 text-sm font-bold text-slate-800">
        <PlugZap className="text-sky-700" size={17} />
        Integration boundaries
      </header>
      <div className="grid divide-y divide-slate-200 sm:grid-cols-2 sm:divide-x sm:divide-y-0 xl:grid-cols-4">
        {items.map((item) => (
          <div className="flex min-w-0 items-start gap-3 p-3.5" key={item.key}>
            <span
              className={`grid h-8 w-8 shrink-0 place-items-center rounded-lg ${
                item.healthy
                  ? "bg-emerald-100 text-emerald-700"
                  : "bg-rose-100 text-rose-700"
              }`}
            >
              <Network size={16} />
            </span>
            <span className="min-w-0">
              <strong className="block text-xs text-slate-800">
                {item.label}
              </strong>
              <span className="mt-1 block truncate text-[11px] text-slate-500">
                {loading ? "Checking provider…" : item.detail}
              </span>
            </span>
          </div>
        ))}
      </div>
    </section>
  );
}

function ProgressBar({ percent, status, compact = false }) {
  const fill =
    status === "OVERDUE" || status === "BLOCKED" || status === "RETURNED"
      ? "bg-rose-500"
      : status === "COMPLETE" ||
          status === "READY" ||
          status === "NOT_APPLICABLE"
        ? "bg-emerald-500"
        : status === "AWAITING_REVIEW"
          ? "bg-amber-500"
          : "bg-sky-600";

  return (
    <div
      aria-label={`${percent}% complete`}
      className={`overflow-hidden rounded-full bg-slate-200 ${compact ? "h-1.5" : "h-2.5"}`}
      role="progressbar"
      aria-valuemax="100"
      aria-valuemin="0"
      aria-valuenow={percent}
    >
      <div
        className={`h-full rounded-full transition-all ${fill}`}
        style={{ width: `${percent}%` }}
      />
    </div>
  );
}

function StageCard({ engagementId, stage }) {
  const target = `${stage.route}?engagementId=${engagementId}`;
  return (
    <Link
      className="group min-w-0 rounded-lg border border-slate-200 bg-white p-3 transition hover:border-sky-300 hover:bg-sky-50"
      to={target}
    >
      <div className="flex min-w-0 items-start justify-between gap-2">
        <strong className="min-w-0 truncate text-xs text-slate-800">
          {stage.label}
        </strong>
        <span className="shrink-0 text-xs font-bold text-slate-600">
          {stage.percent}%
        </span>
      </div>
      <div className="mt-2">
        <ProgressBar compact percent={stage.percent} status={stage.status} />
      </div>
      <div className="mt-2 flex min-w-0 items-center justify-between gap-2">
        <StatusBadge tone={statusTones[stage.status] ?? "info"}>
          {titleCase(stage.status)}
        </StatusBadge>
        <ArrowRight
          className="shrink-0 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-sky-700"
          size={14}
        />
      </div>
      <p className="mt-2 line-clamp-2 text-[11px] leading-4 text-slate-500">
        {stage.detail}
      </p>
    </Link>
  );
}

function EngagementTrackerCard({ engagement, expanded, onToggle }) {
  return (
    <article className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
      <div className="p-4 sm:p-5">
        <div className="flex min-w-0 flex-col gap-4 xl:flex-row xl:items-start">
          <div className="min-w-0 flex-1">
            <div className="flex flex-wrap items-center gap-2">
              <Link
                className="truncate text-base font-bold text-slate-900 hover:text-sky-700"
                to={`/audit-engagement-management/${engagement.id}`}
              >
                {engagement.engagementCode} · {engagement.title}
              </Link>
              <StatusBadge tone="info">
                {titleCase(engagement.status)}
              </StatusBadge>
              <StatusBadge tone={healthTones[engagement.health] ?? "info"}>
                {healthLabels[engagement.health] ??
                  titleCase(engagement.health)}
              </StatusBadge>
            </div>
            <p className="mt-2 truncate text-xs text-slate-500">
              {engagement.offices.map((office) => office.name).join(", ") ||
                "No office linked"}
              {" · "}
              {engagement.sourceType === "PLANNED"
                ? "Approved IAP"
                : "Special engagement"}
            </p>
            <div className="mt-3 grid gap-2 text-xs text-slate-600 sm:grid-cols-3">
              <span>
                Start:{" "}
                <strong className="text-slate-800">
                  {formatDate(engagement.plannedStartDate)}
                </strong>
              </span>
              <span>
                Planned end:{" "}
                <strong className="text-slate-800">
                  {formatDate(engagement.plannedEndDate)}
                </strong>
              </span>
              <span>
                Expected report:{" "}
                <strong className="text-slate-800">
                  {formatDate(engagement.expectedReportDate)}
                </strong>
              </span>
            </div>
          </div>

          <div className="w-full shrink-0 xl:w-72">
            <div className="flex items-center justify-between text-xs">
              <span className="font-bold uppercase tracking-wide text-slate-500">
                Overall progress
              </span>
              <strong className="text-lg text-slate-900">
                {engagement.overallProgress}%
              </strong>
            </div>
            <div className="mt-2">
              <ProgressBar
                percent={engagement.overallProgress}
                status={
                  engagement.health === "OVERDUE"
                    ? "OVERDUE"
                    : engagement.health === "READY_FOR_CLOSURE"
                      ? "READY"
                      : "IN_PROGRESS"
                }
              />
            </div>
          </div>
        </div>

        {engagement.alerts.length > 0 && (
          <div className="mt-4 flex flex-wrap gap-2">
            {engagement.alerts.map((alert) => (
              <span
                className="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 ring-1 ring-rose-200"
                key={alert}
              >
                <AlertTriangle size={13} />
                {alert}
              </span>
            ))}
          </div>
        )}

        <button
          className="mt-4 inline-flex min-h-9 items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-700 hover:border-sky-300 hover:bg-sky-50 hover:text-sky-800"
          onClick={onToggle}
          type="button"
        >
          <ListChecks size={15} />
          {expanded ? "Hide workflow details" : "View all workflow stages"}
          {expanded ? <ChevronUp size={14} /> : <ChevronDown size={14} />}
        </button>
      </div>

      {expanded && (
        <div className="border-t border-slate-200 bg-slate-50 p-4 sm:p-5">
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
            {engagement.stages.map((stage) => (
              <StageCard
                engagementId={engagement.id}
                key={stage.key}
                stage={stage}
              />
            ))}
          </div>

          <div className="mt-4 rounded-xl border border-slate-200 bg-white p-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <div>
                <h3 className="text-sm font-bold text-slate-800">
                  Pre-closure readiness
                </h3>
                <p className="mt-1 text-xs text-slate-500">
                  Operational gates derived from issued records and terminal
                  child workflows.
                </p>
              </div>
              <StatusBadge
                tone={engagement.closure.isReady ? "success" : "warning"}
              >
                {engagement.closure.isReady
                  ? "Ready for closure"
                  : `${engagement.closure.blockers.length} outstanding`}
              </StatusBadge>
            </div>
            <div className="mt-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-5">
              {engagement.closure.gates.map((gate) => (
                <span
                  className={`flex items-start gap-2 rounded-lg px-2.5 py-2 text-xs font-semibold ${
                    gate.met
                      ? "bg-emerald-50 text-emerald-700"
                      : "bg-slate-100 text-slate-600"
                  }`}
                  key={gate.key}
                >
                  {gate.met ? (
                    <CheckCircle2 className="mt-0.5 shrink-0" size={14} />
                  ) : (
                    <AlertTriangle className="mt-0.5 shrink-0" size={14} />
                  )}
                  {gate.label}
                </span>
              ))}
            </div>
          </div>
        </div>
      )}
    </article>
  );
}

export default function AemsDashboardPage() {
  const [dashboard, setDashboard] = useState({
    cards: {},
    engagements: [],
    pagination: { currentPage: 1, lastPage: 1, total: 0 },
    filters: { statuses: [], offices: [] },
    integrations: {},
    capabilities: { canExport: false },
    phaseCounts: [],
    workQueues: {},
    notifications: { unread: 0, aemsUnread: 0, overdue: 0, recent: [] },
    reminderRules: {},
  });
  const [filters, setFilters] = useState({
    search: "",
    status: "",
    officeId: "",
    phase: "",
    page: 1,
    perPage: 10,
  });
  const [draftFilters, setDraftFilters] = useState({
    search: "",
    status: "",
    officeId: "",
    phase: "",
  });
  const [expandedIds, setExpandedIds] = useState(new Set());
  const [loading, setLoading] = useState(true);
  const [exporting, setExporting] = useState(false);
  const [exportingQueues, setExportingQueues] = useState(false);
  const [error, setError] = useState("");
  const [unauthorized, setUnauthorized] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError("");
    setUnauthorized(false);
    try {
      const data = await aemsDashboardApi.show(filters);
      setDashboard({
        cards: data?.cards ?? {},
        engagements: Array.isArray(data?.engagements) ? data.engagements : [],
        pagination: data?.pagination ?? {
          currentPage: 1,
          lastPage: 1,
          total: 0,
        },
        filters: data?.filters ?? { statuses: [], offices: [] },
        integrations: data?.integrations ?? {},
        capabilities: data?.capabilities ?? { canExport: false },
        phaseCounts: Array.isArray(data?.phaseCounts) ? data.phaseCounts : [],
        workQueues: data?.workQueues ?? {},
        notifications: data?.notifications ?? {
          unread: 0,
          aemsUnread: 0,
          overdue: 0,
          recent: [],
        },
        reminderRules: data?.reminderRules ?? {},
      });
    } catch (requestError) {
      setUnauthorized([401, 403].includes(requestError?.status));
      setError(
        [401, 403].includes(requestError?.status)
          ? "You are not authorized to view the AEMS dashboard in this scope."
          : requestError instanceof Error
            ? requestError.message
            : "Unable to load the engagement tracker.",
      );
    } finally {
      setLoading(false);
    }
  }, [filters]);

  useEffect(() => {
    const timer = window.setTimeout(load, 0);
    return () => window.clearTimeout(timer);
  }, [load]);

  const filtersActive = useMemo(
    () =>
      Boolean(
        filters.search || filters.status || filters.officeId || filters.phase,
      ),
    [filters],
  );

  function applyFilters(event) {
    event.preventDefault();
    setFilters((current) => ({
      ...current,
      ...draftFilters,
      page: 1,
    }));
  }

  function clearFilters() {
    const cleared = { search: "", status: "", officeId: "", phase: "" };
    setDraftFilters(cleared);
    setFilters((current) => ({ ...current, ...cleared, page: 1 }));
  }

  function toggleExpanded(id) {
    setExpandedIds((current) => {
      const next = new Set(current);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  }

  async function exportReport() {
    setExporting(true);
    setError("");
    try {
      await aemsDashboardApi.export({
        search: filters.search,
        status: filters.status,
        officeId: filters.officeId,
      });
    } catch (requestError) {
      setError(
        requestError instanceof Error
          ? requestError.message
          : "Unable to export the Engagement Progress Report.",
      );
    } finally {
      setExporting(false);
    }
  }

  async function exportQueues() {
    setExportingQueues(true);
    setError("");
    try {
      await aemsDashboardApi.exportQueues({
        search: filters.search,
        status: filters.status,
        phase: filters.phase,
        officeId: filters.officeId,
      });
    } catch (requestError) {
      setError(
        requestError instanceof Error
          ? requestError.message
          : "Unable to export the AEMS work queues.",
      );
    } finally {
      setExportingQueues(false);
    }
  }

  return (
    <main className="min-w-0 p-4 sm:p-5 lg:p-6">
      <RegistryHeader
        icon={ShieldCheck}
        title="AEMS Dashboard"
        description="Monitor every AEMS engagement from authorization through CMS transfer and closure readiness."
        actions={
          <>
            {dashboard.capabilities.canExport && (
              <div className="flex flex-wrap gap-2">
                <button
                  className="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:opacity-60"
                  disabled={exporting}
                  onClick={exportReport}
                  type="button"
                >
                  <Download size={17} />
                  {exporting ? "Exporting..." : "Export Progress CSV"}
                </button>
                <button
                  className="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:opacity-60"
                  disabled={exportingQueues}
                  onClick={exportQueues}
                  type="button"
                >
                  <ListChecks size={17} />
                  {exportingQueues ? "Exporting..." : "Export Queues CSV"}
                </button>
              </div>
            )}
            <Link
              className="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-sky-800 hover:shadow-md"
              to="/audit-engagement-management"
            >
              <BriefcaseBusiness size={17} />
              Open Audit Engagement Workspace
            </Link>
          </>
        }
      />

      {error && (
        <div
          className={`mb-4 flex flex-col gap-3 rounded-xl border p-4 text-sm font-semibold sm:flex-row sm:items-center sm:justify-between ${unauthorized ? "border-amber-200 bg-amber-50 text-amber-800" : "border-red-200 bg-red-50 text-red-700"}`}
        >
          <span>{error}</span>
          <button
            className="inline-flex items-center justify-center gap-2 rounded-lg border border-red-300 bg-white px-3 py-2"
            onClick={load}
            type="button"
          >
            <RefreshCw size={15} />
            Retry
          </button>
        </div>
      )}

      <section className="grid grid-cols-[repeat(auto-fit,minmax(220px,1fr))] gap-3">
        {cardDefinitions.map((definition) => (
          <MetricCard
            definition={definition}
            key={definition.key}
            loading={loading}
            value={dashboard.cards[definition.key] ?? 0}
          />
        ))}
      </section>

      <IntegrationStrip
        integrations={dashboard.integrations}
        loading={loading}
      />

      <div className="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1.45fr)_minmax(20rem,0.8fr)]">
        <PhaseSummary loading={loading} phases={dashboard.phaseCounts} />
        <NotificationPanel
          loading={loading}
          notifications={dashboard.notifications}
        />
      </div>

      <section className="mt-5 rounded-xl border border-slate-200 bg-slate-50/60 p-3 shadow-sm sm:p-4">
        <div className="mb-3 flex flex-wrap items-end justify-between gap-2 px-1">
          <div>
            <h2 className="font-bold text-slate-900">Needs attention</h2>
            <p className="mt-1 text-xs text-slate-500">
              Operational work queues calculated from your authorized AEMS
              scope.
            </p>
          </div>
          <span className="inline-flex items-center gap-1 text-xs font-semibold text-slate-500">
            <PlugZap size={14} /> Live workflow data
          </span>
        </div>
        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
          {Object.values(dashboard.workQueues).map((queue) => (
            <WorkQueuePanel key={queue.key} loading={loading} queue={queue} />
          ))}
          {!loading && Object.keys(dashboard.workQueues).length === 0 && (
            <div className="rounded-lg border border-dashed border-slate-300 bg-white p-6 text-center text-sm text-slate-500 md:col-span-2 xl:col-span-3">
              No work queues are available for this account.
            </div>
          )}
        </div>
      </section>

      <section className="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div className="border-b border-slate-200 p-4 sm:p-5">
          <div className="flex flex-wrap items-end justify-between gap-3">
            <div>
              <h2 className="font-bold text-slate-900">Engagement progress</h2>
              <p className="mt-1 text-xs text-slate-500">
                {dashboard.pagination.total ?? 0} visible engagement
                {(dashboard.pagination.total ?? 0) === 1 ? "" : "s"} · Click an
                engagement to inspect all 13 tracked stages.
              </p>
            </div>
            <button
              className="inline-flex min-h-9 items-center gap-2 rounded-lg border border-slate-200 px-3 text-xs font-bold text-slate-600 hover:bg-slate-50"
              disabled={loading}
              onClick={load}
              type="button"
            >
              <RefreshCw className={loading ? "animate-spin" : ""} size={14} />
              Refresh
            </button>
          </div>

          <form
            className="mt-4 grid gap-3 md:grid-cols-[minmax(12rem,1fr)_12rem_12rem_14rem_auto]"
            onSubmit={applyFilters}
          >
            <label className="relative min-w-0">
              <span className="sr-only">Search engagements</span>
              <Search
                className="pointer-events-none absolute left-3 top-3 text-slate-400"
                size={17}
              />
              <input
                className="min-h-11 w-full rounded-lg border border-slate-300 bg-white pl-10 pr-3 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100"
                onChange={(event) =>
                  setDraftFilters((current) => ({
                    ...current,
                    search: event.target.value,
                  }))
                }
                placeholder="Search code, title, or office"
                value={draftFilters.search}
              />
            </label>
            <label>
              <span className="sr-only">Engagement status</span>
              <select
                className="min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100"
                onChange={(event) =>
                  setDraftFilters((current) => ({
                    ...current,
                    status: event.target.value,
                  }))
                }
                value={draftFilters.status}
              >
                <option value="">All statuses</option>
                {dashboard.filters.statuses.map((status) => (
                  <option key={status} value={status}>
                    {titleCase(status)}
                  </option>
                ))}
              </select>
            </label>
            <label>
              <span className="sr-only">Responsible office</span>
              <select
                className="min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100"
                onChange={(event) =>
                  setDraftFilters((current) => ({
                    ...current,
                    officeId: event.target.value,
                  }))
                }
                value={draftFilters.officeId}
              >
                <option value="">All offices</option>
                {dashboard.filters.offices.map((office) => (
                  <option key={office.id} value={office.id}>
                    {office.code} · {office.name}
                  </option>
                ))}
              </select>
            </label>
            <label>
              <span className="sr-only">Engagement phase</span>
              <select
                className="min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100"
                onChange={(event) =>
                  setDraftFilters((current) => ({
                    ...current,
                    phase: event.target.value,
                  }))
                }
                value={draftFilters.phase}
              >
                <option value="">All phases</option>
                {(dashboard.filters.phases ?? []).map((phase) => (
                  <option key={phase} value={phase}>
                    {titleCase(phase)}
                  </option>
                ))}
              </select>
            </label>
            <div className="flex gap-2">
              <button
                className="inline-flex min-h-11 flex-1 items-center justify-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white hover:bg-sky-800"
                type="submit"
              >
                <Search size={15} />
                Apply
              </button>
              {filtersActive && (
                <button
                  aria-label="Clear filters"
                  className="grid min-h-11 w-11 place-items-center rounded-lg border border-slate-300 bg-white text-slate-600 hover:bg-slate-50"
                  onClick={clearFilters}
                  type="button"
                >
                  <X size={17} />
                </button>
              )}
            </div>
          </form>
        </div>

        <div className="space-y-4 bg-slate-50/70 p-3 sm:p-4">
          {loading &&
            Array.from({ length: 3 }, (_, index) => (
              <div
                className="animate-pulse rounded-xl border border-slate-200 bg-white p-5"
                key={index}
              >
                <div className="h-5 w-2/3 rounded bg-slate-200" />
                <div className="mt-3 h-3 w-1/3 rounded bg-slate-100" />
                <div className="mt-5 h-2.5 rounded bg-slate-200" />
              </div>
            ))}

          {!loading &&
            dashboard.engagements.map((engagement) => (
              <EngagementTrackerCard
                engagement={engagement}
                expanded={expandedIds.has(engagement.id)}
                key={engagement.id}
                onToggle={() => toggleExpanded(engagement.id)}
              />
            ))}

          {!loading && dashboard.engagements.length === 0 && (
            <div className="grid min-h-64 place-items-center rounded-xl border border-dashed border-slate-300 bg-white p-6 text-center">
              <div>
                <BriefcaseBusiness
                  className="mx-auto text-slate-300"
                  size={38}
                />
                <p className="mt-3 text-sm font-bold text-slate-700">
                  No engagements match this view.
                </p>
                <p className="mt-1 text-xs text-slate-500">
                  Clear the filters or create the engagement in the registry.
                </p>
                {filtersActive && (
                  <button
                    className="mt-3 inline-flex items-center gap-2 text-sm font-bold text-sky-700"
                    onClick={clearFilters}
                    type="button"
                  >
                    Clear filters
                    <ArrowRight size={15} />
                  </button>
                )}
              </div>
            </div>
          )}
        </div>

        {!loading && (dashboard.pagination.lastPage ?? 1) > 1 && (
          <div className="flex items-center justify-between gap-3 border-t border-slate-200 px-4 py-3 text-xs text-slate-600 sm:px-5">
            <span>
              Showing {dashboard.pagination.from}–{dashboard.pagination.to} of{" "}
              {dashboard.pagination.total}
            </span>
            <div className="flex gap-2">
              <button
                className="min-h-9 rounded-lg border border-slate-300 px-3 font-bold disabled:cursor-not-allowed disabled:opacity-40"
                disabled={dashboard.pagination.currentPage <= 1}
                onClick={() =>
                  setFilters((current) => ({
                    ...current,
                    page: current.page - 1,
                  }))
                }
                type="button"
              >
                Previous
              </button>
              <button
                className="min-h-9 rounded-lg border border-slate-300 px-3 font-bold disabled:cursor-not-allowed disabled:opacity-40"
                disabled={
                  dashboard.pagination.currentPage >=
                  dashboard.pagination.lastPage
                }
                onClick={() =>
                  setFilters((current) => ({
                    ...current,
                    page: current.page + 1,
                  }))
                }
                type="button"
              >
                Next
              </button>
            </div>
          </div>
        )}
      </section>
    </main>
  );
}
