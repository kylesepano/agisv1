import { useCallback, useEffect, useMemo, useState } from "react";
import {
  AlertTriangle,
  ArrowRight,
  Blocks,
  CalendarCheck2,
  CalendarClock,
  ChartNoAxesCombined,
  CheckCircle2,
  CircleGauge,
  ClipboardCheck,
  Clock3,
  ListChecks,
  RefreshCw,
  ShieldAlert,
  Sparkles,
  UserRoundCheck,
  UsersRound,
} from "lucide-react";
import { Link } from "react-router";
import {
  Bar,
  BarChart,
  CartesianGrid,
  Cell,
  Pie,
  PieChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts";
import RegistryHeader from "../../components/ui/RegistryHeader";
import StatusBadge from "../../components/ui/StatusBadge";
import { iapDashboardApi } from "../../services/api";

const planningSteps = [
  {
    number: 1,
    title: "Audit Universe",
    description: "Maintain the complete inventory of auditable subjects.",
    path: "/internal-audit-planning/audit-universe",
    icon: Blocks,
  },
  {
    number: 2,
    title: "Risk Assessment",
    description: "Score inherent risk, controls, and residual exposure.",
    path: "/internal-audit-planning/risk-assessment",
    icon: ShieldAlert,
  },
  {
    number: 3,
    title: "Prioritization",
    description: "Rank subjects and record selection or deferral decisions.",
    path: "/internal-audit-planning/prioritization",
    icon: ListChecks,
  },
  {
    number: 4,
    title: "Annual Audit Plan",
    description: "Convert selected subjects into planned engagements.",
    path: "/internal-audit-planning",
    icon: ClipboardCheck,
  },
  {
    number: 5,
    title: "Scheduling",
    description: "Set dates, proposed team leaders, and resolve conflicts.",
    path: "/internal-audit-planning/scheduling",
    icon: CalendarClock,
  },
  {
    number: 6,
    title: "ARMIS Resources",
    description:
      "Open ARMIS for authoritative person-days, availability, workload, and skills.",
    path: "/audit-resource-management/planning",
    icon: UsersRound,
  },
  {
    number: 7,
    title: "Approval",
    description: "Review, return, resubmit, approve, and activate the plan.",
    path: "/internal-audit-planning",
    icon: CalendarCheck2,
  },
];

const statusLabels = {
  DRAFT: "Draft",
  PENDING_REVIEW: "Pending Review",
  RETURNED_FOR_REVISION: "Returned for Revision",
  RESUBMITTED: "Resubmitted",
  APPROVED: "Approved",
  ACTIVE: "Active",
  COMPLETED: "Completed",
  REJECTED: "Rejected",
};

const statusTones = {
  DRAFT: "inactive",
  PENDING_REVIEW: "warning",
  RETURNED_FOR_REVISION: "danger",
  RESUBMITTED: "warning",
  APPROVED: "success",
  ACTIVE: "info",
  COMPLETED: "active",
  REJECTED: "danger",
};

const riskColors = {
  CRITICAL: "#dc2626",
  HIGH: "#f97316",
  MEDIUM: "#f59e0b",
  LOW: "#10b981",
};

const decisionColors = {
  PLANNED: "#0284c7",
  UNPLANNED: "#f59e0b",
  DEFERRED: "#7c3aed",
  NOT_SELECTED: "#94a3b8",
};

function formatDate(value) {
  if (!value) return "Not scheduled";
  return new Intl.DateTimeFormat("en-PH", {
    month: "short",
    day: "numeric",
    year: "numeric",
  }).format(new Date(`${value}T00:00:00`));
}

function formatDateTime(value) {
  if (!value) return "Not recorded";
  return new Intl.DateTimeFormat("en-PH", {
    month: "short",
    day: "numeric",
    year: "numeric",
    hour: "numeric",
    minute: "2-digit",
  }).format(new Date(value));
}

function MetricCard({ icon: Icon, label, value, note, tone = "sky", loading }) {
  const tones = {
    sky: "border-sky-200 bg-sky-50 text-sky-700",
    red: "border-red-200 bg-red-50 text-red-700",
    amber: "border-amber-200 bg-amber-50 text-amber-700",
    emerald: "border-emerald-200 bg-emerald-50 text-emerald-700",
    violet: "border-violet-200 bg-violet-50 text-violet-700",
    slate: "border-slate-200 bg-slate-50 text-slate-700",
  };

  return (
    <article
      className={`flex min-h-28 min-w-0 items-start gap-3 rounded-xl border p-4 shadow-sm transition hover:-translate-y-1 hover:shadow-md ${tones[tone]}`}
    >
      <span className="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-white/80 shadow-sm ring-1 ring-black/5">
        <Icon size={21} />
      </span>
      <div className="min-w-0">
        {loading ? (
          <span className="mt-1 block h-7 w-16 animate-pulse rounded bg-slate-200" />
        ) : (
          <strong className="block text-2xl leading-none text-slate-950">
            {value}
          </strong>
        )}
        <span className="mt-2 block text-xs font-bold uppercase tracking-wide">
          {label}
        </span>
        <span className="mt-1 block text-xs leading-4 opacity-80">{note}</span>
      </div>
    </article>
  );
}

function ChartPanel({ title, description, children, action }) {
  return (
    <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
      <header className="flex min-h-16 items-center justify-between gap-3 border-b border-slate-200 px-5 py-3">
        <div>
          <h2 className="text-sm font-bold text-slate-800">{title}</h2>
          <p className="mt-0.5 text-xs text-slate-500">{description}</p>
        </div>
        {action}
      </header>
      {children}
    </section>
  );
}

/**
 * Aggregates live IAP risk, plan, schedule, conflict, and capacity indicators
 * into the planning module's dedicated dashboard.
 */
export default function IapDashboardPage() {
  const [dashboard, setDashboard] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  const load = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      setDashboard(await iapDashboardApi.show());
    } catch (requestError) {
      setError(
        requestError instanceof Error
          ? requestError.message
          : "Unable to load the IAP dashboard.",
      );
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    let active = true;
    iapDashboardApi
      .show()
      .then((data) => {
        if (active) {
          setDashboard(data);
          setError("");
        }
      })
      .catch((requestError) => {
        if (active) {
          setError(
            requestError instanceof Error
              ? requestError.message
              : "Unable to load the IAP dashboard.",
          );
        }
      })
      .finally(() => active && setLoading(false));

    return () => {
      active = false;
    };
  }, []);

  const metrics = dashboard?.metrics ?? {};
  const plan = dashboard?.plan ?? null;
  const riskData = useMemo(
    () =>
      (dashboard?.riskDistribution ?? []).map((item) => ({
        ...item,
        color: riskColors[item.code] ?? "#94a3b8",
      })),
    [dashboard?.riskDistribution],
  );
  const decisionData = useMemo(
    () =>
      (dashboard?.decisionDistribution ?? []).map((item) => ({
        ...item,
        color: decisionColors[item.code] ?? "#94a3b8",
      })),
    [dashboard?.decisionDistribution],
  );
  const riskTotal = riskData.reduce((total, item) => total + item.value, 0);

  const cards = [
    {
      icon: Blocks,
      label: "Total Audit Universe",
      value: metrics.totalAuditUniverse ?? 0,
      note: "Active auditable subjects",
      tone: "sky",
    },
    {
      icon: ShieldAlert,
      label: "Critical Risk",
      value: metrics.criticalRiskSubjects ?? 0,
      note: "Validated residual-risk subjects",
      tone: "red",
    },
    {
      icon: ShieldAlert,
      label: "High Risk",
      value: metrics.highRiskSubjects ?? 0,
      note: "Validated residual-risk subjects",
      tone: "amber",
    },
    {
      icon: CheckCircle2,
      label: "Selected Subjects",
      value: metrics.selectedSubjects ?? 0,
      note: "Final prioritization decision",
      tone: "emerald",
    },
    {
      icon: Clock3,
      label: "Deferred Subjects",
      value: metrics.deferredSubjects ?? 0,
      note: "Deferred with recorded reasons",
      tone: "violet",
    },
    {
      icon: ClipboardCheck,
      label: "Planned Audits",
      value: metrics.plannedAudits ?? 0,
      note: "Active plan engagements",
      tone: "sky",
    },
    {
      icon: ListChecks,
      label: "Unplanned Audits",
      value: metrics.unplannedAudits ?? 0,
      note: "Selected subjects not imported",
      tone: "amber",
    },
    {
      icon: UsersRound,
      label: "Available Person-Days",
      value: Number(metrics.availablePersonDays ?? 0).toLocaleString(),
      note: `${metrics.availableAuditors ?? 0} available auditors`,
      tone: "emerald",
    },
    {
      icon: UserRoundCheck,
      label: "Allocated Person-Days",
      value: Number(metrics.allocatedPersonDays ?? 0).toLocaleString(),
      note: `${Number(metrics.remainingPersonDays ?? 0).toLocaleString()} remaining`,
      tone: "sky",
    },
    {
      icon: CircleGauge,
      label: "Capacity Utilization",
      value: `${metrics.capacityUtilization ?? 0}%`,
      note: `${metrics.overallocatedAuditors ?? 0} overallocated auditors`,
      tone: Number(metrics.capacityUtilization ?? 0) > 100 ? "red" : "violet",
    },
    {
      icon: ChartNoAxesCombined,
      label: "Plan Accomplishment",
      value: `${metrics.planAccomplishment ?? 0}%`,
      note: `${metrics.implementedEngagements ?? 0} engagements linked to AEM`,
      tone: "emerald",
    },
    {
      icon: CalendarClock,
      label: "Upcoming Audits",
      value: metrics.upcomingAudits ?? 0,
      note: "Future scheduled start dates",
      tone: "sky",
    },
    {
      icon: AlertTriangle,
      label: "Schedule Warnings",
      value: metrics.scheduleConflictWarnings ?? 0,
      note: "Live scheduling conflict checks",
      tone:
        Number(metrics.scheduleConflictWarnings ?? 0) > 0 ? "red" : "emerald",
    },
  ];

  return (
    <div className="p-4 sm:p-6">
      <RegistryHeader
        actions={
          <button
            className="inline-flex min-h-11 items-center gap-2 rounded-lg border border-sky-200 bg-white px-4 text-sm font-bold text-sky-700 transition hover:-translate-y-0.5 hover:bg-sky-50 hover:shadow-sm disabled:opacity-60"
            disabled={loading}
            onClick={load}
            type="button"
          >
            <RefreshCw className={loading ? "animate-spin" : ""} size={17} />
            Refresh live data
          </button>
        }
        description="Live risk, prioritization, annual-plan, schedule, approval, and ARMIS resource information."
        icon={CircleGauge}
        title="Internal Audit Planning Dashboard"
      />

      {error && (
        <div className="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700">
          {error}
        </div>
      )}

      <div className="mb-4 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-xs text-slate-500 shadow-sm">
        <span>
          Reporting year:{" "}
          <strong className="text-slate-800">
            {dashboard?.context?.fiscalYear ?? new Date().getFullYear()}
          </strong>
          {dashboard?.context?.riskPeriod && (
            <>
              {" "}
              · Risk source:{" "}
              <strong className="text-slate-800">
                {dashboard.context.riskPeriod.periodCode}
              </strong>
            </>
          )}
          {dashboard?.context?.prioritization && (
            <>
              {" "}
              · Prioritization:{" "}
              <strong className="text-slate-800">
                {dashboard.context.prioritization.runCode}
              </strong>
            </>
          )}
        </span>
        <span>Updated {formatDateTime(dashboard?.context?.generatedAt)}</span>
      </div>

      <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        {cards.map((card) => (
          <MetricCard {...card} key={card.label} loading={loading} />
        ))}
      </section>

      <div className="mt-5 grid gap-5 xl:grid-cols-2">
        <ChartPanel
          action={
            <Link
              className="text-xs font-bold text-sky-700 hover:text-sky-900"
              to="/internal-audit-planning/risk-assessment"
            >
              View assessments
            </Link>
          }
          description="Residual risk from the linked validated or locked assessment period."
          title="Risk distribution"
        >
          <div className="grid min-h-72 gap-4 p-5 sm:grid-cols-[minmax(0,1fr)_12rem]">
            {loading ? (
              <div className="col-span-full m-auto h-48 w-48 animate-pulse rounded-full bg-slate-100" />
            ) : (
              <>
                <div className="relative h-56 min-w-0">
                  <ResponsiveContainer height="100%" width="100%">
                    <PieChart accessibilityLayer>
                      <Pie
                        animationBegin={100}
                        animationDuration={1100}
                        cx="50%"
                        cy="50%"
                        data={riskData}
                        dataKey="value"
                        endAngle={-270}
                        innerRadius={62}
                        isAnimationActive
                        nameKey="label"
                        outerRadius={92}
                        startAngle={90}
                        stroke="none"
                      >
                        {riskData.map((item) => (
                          <Cell fill={item.color} key={item.code} />
                        ))}
                      </Pie>
                      <Tooltip
                        contentStyle={{
                          borderColor: "#e2e8f0",
                          borderRadius: "10px",
                          fontSize: "12px",
                        }}
                        formatter={(value, name) => [
                          `${value} subject${Number(value) === 1 ? "" : "s"}`,
                          name,
                        ]}
                      />
                    </PieChart>
                  </ResponsiveContainer>
                  <div className="pointer-events-none absolute inset-0 grid place-content-center text-center">
                    <strong className="text-3xl text-slate-800">
                      {riskTotal}
                    </strong>
                    <span className="text-xs text-slate-500">assessed</span>
                  </div>
                </div>
                <div className="grid content-center gap-3">
                  {riskData.map((item) => (
                    <div
                      className="grid grid-cols-[10px_1fr_auto] items-center gap-2 text-sm"
                      key={item.code}
                    >
                      <i
                        className="h-2.5 w-2.5 rounded-full"
                        style={{ backgroundColor: item.color }}
                      />
                      <span className="text-slate-600">{item.label}</span>
                      <strong className="text-slate-900">{item.value}</strong>
                    </div>
                  ))}
                </div>
              </>
            )}
          </div>
        </ChartPanel>

        <ChartPanel
          action={
            <Link
              className="text-xs font-bold text-sky-700 hover:text-sky-900"
              to="/internal-audit-planning/prioritization"
            >
              View prioritization
            </Link>
          }
          description="Finalized selections and their current annual-plan coverage."
          title="Planning decision distribution"
        >
          <div className="h-72 p-5">
            {loading ? (
              <div className="h-full animate-pulse rounded-xl bg-slate-100" />
            ) : (
              <ResponsiveContainer height="100%" width="100%">
                <BarChart
                  accessibilityLayer
                  data={decisionData}
                  margin={{ left: -18, right: 8, top: 10, bottom: 8 }}
                >
                  <CartesianGrid stroke="#e2e8f0" strokeDasharray="3 3" />
                  <XAxis
                    axisLine={false}
                    dataKey="label"
                    fontSize={11}
                    tickLine={false}
                  />
                  <YAxis
                    allowDecimals={false}
                    axisLine={false}
                    fontSize={11}
                    tickLine={false}
                  />
                  <Tooltip
                    contentStyle={{
                      borderColor: "#e2e8f0",
                      borderRadius: "10px",
                      fontSize: "12px",
                    }}
                    cursor={{ fill: "#f1f5f9" }}
                  />
                  <Bar
                    animationBegin={150}
                    animationDuration={1000}
                    dataKey="value"
                    name="Subjects"
                    radius={[7, 7, 0, 0]}
                  >
                    {decisionData.map((item) => (
                      <Cell fill={item.color} key={item.code} />
                    ))}
                  </Bar>
                </BarChart>
              </ResponsiveContainer>
            )}
          </div>
        </ChartPanel>
      </div>

      <div className="mt-5 grid gap-5 xl:grid-cols-[1.05fr_.95fr]">
        <ChartPanel
          action={
            <Link
              className="text-xs font-bold text-sky-700 hover:text-sky-900"
              to="/internal-audit-planning"
            >
              View all plans
            </Link>
          }
          description="Latest current plan revision visible to your role."
          title="Plan approval and accomplishment"
        >
          <div className="p-5">
            {loading ? (
              <div className="h-48 animate-pulse rounded-xl bg-slate-100" />
            ) : plan ? (
              <div>
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <strong className="text-lg text-slate-900">
                      {plan.title}
                    </strong>
                    <p className="mt-1 text-sm font-semibold text-sky-700">
                      {plan.planCode} · Fiscal year {plan.fiscalYear} · Revision{" "}
                      {plan.revisionNumber}
                    </p>
                  </div>
                  <StatusBadge tone={statusTones[plan.status]}>
                    {statusLabels[plan.status] ?? plan.status}
                  </StatusBadge>
                </div>

                <div className="mt-5 grid gap-4">
                  <div>
                    <div className="mb-1.5 flex items-center justify-between text-xs font-bold text-slate-600">
                      <span>Approval lifecycle</span>
                      <span>{plan.approvalProgress}%</span>
                    </div>
                    <div className="h-2.5 overflow-hidden rounded-full bg-slate-100">
                      <div
                        className="h-full rounded-full bg-sky-600 transition-all duration-700"
                        style={{ width: `${plan.approvalProgress}%` }}
                      />
                    </div>
                  </div>
                  <div>
                    <div className="mb-1.5 flex items-center justify-between text-xs font-bold text-slate-600">
                      <span>AEM implementation accomplishment</span>
                      <span>{metrics.planAccomplishment ?? 0}%</span>
                    </div>
                    <div className="h-2.5 overflow-hidden rounded-full bg-slate-100">
                      <div
                        className="h-full rounded-full bg-emerald-600 transition-all duration-700"
                        style={{
                          width: `${Math.min(
                            100,
                            Number(metrics.planAccomplishment ?? 0),
                          )}%`,
                        }}
                      />
                    </div>
                  </div>
                </div>

                <dl className="mt-5 grid gap-3 sm:grid-cols-3">
                  <div className="rounded-lg bg-slate-50 p-3">
                    <dt className="text-xs font-bold uppercase text-slate-500">
                      Prepared by
                    </dt>
                    <dd className="mt-1 text-sm font-bold text-slate-800">
                      {plan.preparedBy?.name ?? "Not recorded"}
                    </dd>
                  </div>
                  <div className="rounded-lg bg-slate-50 p-3">
                    <dt className="text-xs font-bold uppercase text-slate-500">
                      Submitted
                    </dt>
                    <dd className="mt-1 text-sm font-bold text-slate-800">
                      {formatDateTime(plan.submittedAt)}
                    </dd>
                  </div>
                  <div className="rounded-lg bg-slate-50 p-3">
                    <dt className="text-xs font-bold uppercase text-slate-500">
                      Approved
                    </dt>
                    <dd className="mt-1 text-sm font-bold text-slate-800">
                      {formatDateTime(plan.approvedAt)}
                    </dd>
                  </div>
                </dl>
                <Link
                  className="mt-4 inline-flex items-center gap-2 rounded-lg bg-sky-700 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-sky-800"
                  to={`/internal-audit-planning/${plan.id}`}
                >
                  Open current plan <ArrowRight size={16} />
                </Link>
              </div>
            ) : (
              <div className="py-12 text-center">
                <ClipboardCheck className="mx-auto text-slate-300" size={38} />
                <p className="mt-3 text-sm font-semibold text-slate-600">
                  No current annual plan is visible to your role.
                </p>
              </div>
            )}
          </div>
        </ChartPanel>

        <ChartPanel
          action={
            <Link
              className="text-xs font-bold text-sky-700 hover:text-sky-900"
              to="/internal-audit-planning/scheduling"
            >
              Open calendar
            </Link>
          }
          description="Future scheduled engagements from the current plan."
          title="Upcoming audits"
        >
          <div className="divide-y divide-slate-100">
            {loading ? (
              <div className="m-5 h-40 animate-pulse rounded-xl bg-slate-100" />
            ) : dashboard?.upcomingAudits?.length ? (
              dashboard.upcomingAudits.map((audit) => (
                <div
                  className="flex items-start justify-between gap-4 px-5 py-3.5"
                  key={audit.id}
                >
                  <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                      <strong className="text-sm text-slate-800">
                        {audit.title}
                      </strong>
                      {audit.riskLevel && (
                        <StatusBadge
                          tone={
                            ["CRITICAL", "HIGH"].includes(audit.riskLevel.code)
                              ? "danger"
                              : audit.riskLevel.code === "MEDIUM"
                                ? "warning"
                                : "success"
                          }
                        >
                          {audit.riskLevel.label}
                        </StatusBadge>
                      )}
                    </div>
                    <p className="mt-1 text-xs text-slate-500">
                      {audit.engagementCode} ·{" "}
                      {audit.offices.map((office) => office.code).join(", ") ||
                        "Office not assigned"}
                    </p>
                    <p className="mt-1 text-xs text-slate-500">
                      Team:{" "}
                      {audit.team.map((member) => member.name).join(", ") ||
                        "Not assigned"}
                    </p>
                  </div>
                  <span className="shrink-0 text-right text-xs font-bold text-sky-700">
                    {formatDate(audit.plannedStartDate)}
                    <span className="mt-1 block font-normal text-slate-400">
                      to {formatDate(audit.plannedEndDate)}
                    </span>
                  </span>
                </div>
              ))
            ) : (
              <p className="px-5 py-12 text-center text-sm text-slate-500">
                No future audit dates are scheduled in the current plan.
              </p>
            )}
          </div>
        </ChartPanel>
      </div>

      <div className="mt-5 grid gap-5 xl:grid-cols-2">
        <ChartPanel
          action={
            <Link
              className="text-xs font-bold text-sky-700 hover:text-sky-900"
              to="/internal-audit-planning/scheduling"
            >
              Resolve warnings
            </Link>
          }
          description="Recomputed using auditor dates, office overlap, capacity, and skill requirements."
          title="Schedule-conflict warnings"
        >
          <div className="divide-y divide-slate-100">
            {loading ? (
              <div className="m-5 h-32 animate-pulse rounded-xl bg-slate-100" />
            ) : dashboard?.conflictWarnings?.length ? (
              dashboard.conflictWarnings.map((warning, index) => (
                <div
                  className="flex items-start gap-3 px-5 py-3.5"
                  key={`${warning.sourceEngagementId}-${warning.type}-${index}`}
                >
                  <span
                    className={`grid h-9 w-9 shrink-0 place-items-center rounded-lg ${
                      warning.severity === "danger"
                        ? "bg-red-100 text-red-700"
                        : "bg-amber-100 text-amber-700"
                    }`}
                  >
                    <AlertTriangle size={17} />
                  </span>
                  <div>
                    <strong className="text-sm text-slate-800">
                      {warning.sourceEngagementCode}
                    </strong>
                    <p className="mt-1 text-xs leading-5 text-slate-600">
                      {warning.message}
                    </p>
                  </div>
                </div>
              ))
            ) : (
              <div className="grid min-h-40 place-items-center p-6 text-center">
                <div>
                  <CheckCircle2
                    className="mx-auto text-emerald-500"
                    size={34}
                  />
                  <strong className="mt-3 block text-sm text-slate-700">
                    No current schedule conflicts
                  </strong>
                  <p className="mt-1 text-xs text-slate-500">
                    Current dates, teams, offices, skills, and capacity passed
                    the conflict checks.
                  </p>
                </div>
              </div>
            )}
          </div>
        </ChartPanel>

        <ChartPanel
          action={
            <Link
              className="text-xs font-bold text-sky-700 hover:text-sky-900"
              to="/internal-audit-planning"
            >
              Add to annual plan
            </Link>
          }
          description="Selected subjects not yet converted into plan engagements."
          title="Unplanned selected subjects"
        >
          <div className="divide-y divide-slate-100">
            {loading ? (
              <div className="m-5 h-32 animate-pulse rounded-xl bg-slate-100" />
            ) : dashboard?.unplannedSubjects?.length ? (
              dashboard.unplannedSubjects.map((subject) => (
                <div
                  className="flex items-start justify-between gap-4 px-5 py-3.5"
                  key={subject.id}
                >
                  <div>
                    <strong className="text-sm text-slate-800">
                      {subject.subjectName}
                    </strong>
                    <p className="mt-1 text-xs text-slate-500">
                      {subject.subjectCode} ·{" "}
                      {subject.officeCode ?? "No office"}
                    </p>
                  </div>
                  <div className="shrink-0 text-right">
                    <StatusBadge
                      tone={
                        ["CRITICAL", "HIGH"].includes(subject.riskLevelCode)
                          ? "danger"
                          : "warning"
                      }
                    >
                      {subject.riskLevelCode}
                    </StatusBadge>
                    <span className="mt-1 block text-xs text-slate-500">
                      Rank #{subject.finalRank}
                    </span>
                  </div>
                </div>
              ))
            ) : (
              <div className="grid min-h-40 place-items-center p-6 text-center">
                <div>
                  <CheckCircle2
                    className="mx-auto text-emerald-500"
                    size={34}
                  />
                  <strong className="mt-3 block text-sm text-slate-700">
                    All selected subjects are planned
                  </strong>
                  <p className="mt-1 text-xs text-slate-500">
                    There are no selected prioritization items waiting for
                    import.
                  </p>
                </div>
              </div>
            )}
          </div>
        </ChartPanel>
      </div>

      <section className="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <header className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
          <div>
            <h2 className="text-base font-bold text-slate-800">
              Risk-based IAP workflow
            </h2>
            <p className="mt-1 text-sm text-slate-500">
              Open each planning workspace without leaving the IAP module.
            </p>
          </div>
          <Link
            className="inline-flex items-center gap-2 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-sm font-bold text-sky-700 transition hover:-translate-y-0.5 hover:bg-sky-100"
            to="/internal-audit-planning/strategic-plan"
          >
            <Sparkles size={17} />
            Strategic Internal Audit Plan
          </Link>
        </header>
        <div className="grid gap-3 p-4 md:grid-cols-2 xl:grid-cols-4">
          {planningSteps.map((step) => {
            const Icon = step.icon;
            return (
              <Link
                className="group flex min-h-32 gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 transition hover:-translate-y-1 hover:border-sky-300 hover:bg-sky-50 hover:shadow-md"
                key={step.title}
                to={step.path}
              >
                <span className="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-sky-100 font-bold text-sky-700">
                  {step.number}
                </span>
                <span className="min-w-0">
                  <span className="flex items-center gap-2">
                    <Icon className="text-sky-700" size={18} />
                    <strong className="text-sm text-slate-800">
                      {step.title}
                    </strong>
                  </span>
                  <span className="mt-2 block text-xs leading-5 text-slate-500">
                    {step.description}
                  </span>
                  <span className="mt-2 inline-flex items-center gap-1 text-xs font-bold text-sky-700">
                    Open workspace
                    <ArrowRight
                      className="transition group-hover:translate-x-1"
                      size={14}
                    />
                  </span>
                </span>
              </Link>
            );
          })}
        </div>
      </section>
    </div>
  );
}
