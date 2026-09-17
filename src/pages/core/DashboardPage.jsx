import {
  ArrowRight,
  BriefcaseBusiness,
  CalendarDays,
  FileText,
  Plus,
  Search,
} from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import { useNavigate, useOutletContext } from "react-router";
import { Cell, Pie, PieChart, ResponsiveContainer, Tooltip } from "recharts";
import { useAuth } from "../../auth/auth-context";
import { hasPermission, modules, visibleFor } from "../../config/navigation";
import { coreDashboardApi } from "../../services/api";
import { useToast } from "../../ui/toast-context";

const permissionContent = {
  "access.manage_system_roles": {
    greeting: "Keep AGIS secure, available, and ready for every audit team.",
    tasks: [
      ["Review new user access request", "CORE-2025-004", "Today"],
      ["Publish updated permission matrix", "CORE-2025-005", "Jul 24"],
      ["Verify nightly data refresh", "AIS-2025-006", "Jul 25"],
      ["Archive superseded workflow", "CORE-2025-007", "Jul 26"],
    ],
  },
  "iap.manage_engagements": {
    greeting: "Here’s what’s happening with your audit activities today.",
    tasks: [
      ["Prepare Annual Internal Audit Plan", "IAP-2025", "May 20"],
      ["Review Working Papers", "AEMS-2025-007", "May 21"],
      ["Validate Recommendation Actions", "REC-2025-041", "May 22"],
      ["Respond to Management Comment", "FND-2025-038", "May 23"],
    ],
  },
  "aems.fieldwork.create": {
    greeting: "Your assignments, working papers, and deadlines are ready.",
    tasks: [
      ["Complete cash receipt testing", "AEMS-2025-004", "Today"],
      ["Revise WP-REV-04", "AEMS-2025-005", "Jul 24"],
      ["Upload supporting evidence", "AEMS-2025-006", "Jul 25"],
      ["Submit weekly time record", "ARMIS-2025-007", "Jul 26"],
    ],
  },
  "roles.update": {
    greeting:
      "Core registries, access controls, and platform monitoring are ready.",
    tasks: [
      ["Review new user access request", "CORE-2026-004", "Today"],
      ["Validate office registry changes", "CORE-2026-005", "Jul 24"],
      ["Review role permission assignments", "CORE-2026-006", "Jul 25"],
      ["Verify configuration baseline", "CORE-2026-007", "Jul 26"],
    ],
  },
  "access.auditee_scope": {
    greeting:
      "Your office’s audit requests, responses, and deadlines are ready.",
    tasks: [
      ["Review current audit request", "AEMS-2026-014", "Today"],
      ["Upload requested supporting document", "DOC-2026-088", "Jul 24"],
      ["Update management action", "REC-2026-031", "Jul 25"],
      ["Confirm office representative details", "CORE-2026-019", "Jul 26"],
    ],
  },
  "access.read_only_scope": {
    greeting:
      "City audit status and authorized reports are available for review.",
    tasks: [
      ["Review executive audit summary", "AIS-2026-011", "Today"],
      ["View open recommendations", "CMS-2026-008", "Jul 24"],
      ["Review engagement progress", "AEMS-2026-021", "Jul 25"],
      ["View administrative report", "RPT-2026-014", "Jul 26"],
    ],
  },
};

const upcomingActivities = [
  ["Entrance Conference", "AEMS-2025-009 • CHUDD", "May 21", "10:00 AM"],
  [
    "Exit Conference",
    "AEMS-2025-005 • City Treasurer’s Office",
    "May 22",
    "2:00 PM",
  ],
  ["Draft Audit Report Due", "AEMS-2025-006 • CIO", "May 23", "5:00 PM"],
  ["Team Meeting", "CIAS Conference Room", "May 26", "9:00 AM"],
];

const recentEngagements = [
  ["AEMS-2025-009", "CHUDD", "Planning", "15%", "Medium"],
  ["AEMS-2025-008", "City Engineer’s Office", "Execution", "45%", "High"],
  ["AEMS-2025-007", "BPLD", "Execution", "68%", "Medium"],
  ["AEMS-2025-006", "CIO", "Reporting", "85%", "High"],
  ["AEMS-2025-005", "City Treasurer’s Office", "Reporting", "90%", "Medium"],
];

const toneClasses = {
  blue: {
    card: "border-blue-200 bg-blue-100/80",
    text: "text-blue-700",
    button: "border-blue-400 text-blue-700 hover:bg-blue-600 hover:text-white",
  },
  green: {
    card: "border-green-200 bg-green-100/80",
    text: "text-green-700",
    button:
      "border-green-500 text-green-700 hover:bg-green-600 hover:text-white",
  },
  orange: {
    card: "border-orange-200 bg-orange-100/85",
    text: "text-orange-600",
    button:
      "border-orange-500 text-orange-600 hover:bg-orange-500 hover:text-white",
  },
  purple: {
    card: "border-purple-200 bg-purple-100/80",
    text: "text-purple-700",
    button:
      "border-purple-500 text-purple-700 hover:bg-purple-600 hover:text-white",
  },
  teal: {
    card: "border-cyan-200 bg-cyan-50",
    text: "text-cyan-700",
    button: "border-cyan-600 text-cyan-700 hover:bg-cyan-700 hover:text-white",
  },
  yellow: {
    card: "border-yellow-300 bg-yellow-100/80",
    text: "text-amber-700",
    button:
      "border-amber-500 text-amber-700 hover:bg-amber-600 hover:text-white",
  },
};

function ModuleCard({ module, onOpen }) {
  const tone = toneClasses[module.tone];
  const ModuleIcon = module.icon;

  return (
    <article
      className={`group flex min-h-64 flex-col items-center rounded-xl border p-4 text-center shadow-sm transition duration-300 hover:-translate-y-1.5 hover:shadow-xl ${tone.card}`}
    >
      <span
        className={`mt-2 transition duration-300 group-hover:scale-110 ${tone.text}`}
      >
        <ModuleIcon size={51} strokeWidth={1.6} />
      </span>
      <h2
        className={`mt-5 min-h-12 text-sm font-bold leading-tight ${tone.text}`}
      >
        {module.label} ({module.code})
      </h2>
      <strong className={`mt-auto text-3xl ${tone.text}`}>
        {module.value}
      </strong>
      <span className={`mt-1 text-[11px] ${tone.text}`}>{module.note}</span>
      <button
        className={`mt-4 flex h-10 w-full items-center justify-between rounded-md border bg-white/80 px-3 text-xs font-bold transition ${tone.button}`}
        type="button"
        onClick={() => onOpen(module.path)}
      >
        Open {module.code}
        <ArrowRight size={16} />
      </button>
    </article>
  );
}

function Panel({ title, action, children, className = "" }) {
  return (
    <article
      className={`overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition hover:shadow-md ${className}`}
    >
      <header className="flex min-h-11 items-center justify-between border-b border-slate-200 px-4">
        <h2 className="text-sm font-bold text-slate-700">{title}</h2>
        {action && (
          <span className="text-xs font-semibold text-blue-700">{action}</span>
        )}
      </header>
      {children}
    </article>
  );
}

function DonutPanel({ title, total, segments }) {
  return (
    <Panel title={title} action="View Report">
      <div className="flex min-h-48 flex-wrap items-center justify-center gap-7 p-5">
        <div className="relative h-32 w-32 shrink-0 transition duration-300 hover:rotate-3 hover:scale-105 ">
          <div className="absolute inset-0 z-20">
            <ResponsiveContainer width="100%" height="100%">
              <PieChart accessibilityLayer>
                <Pie
                  data={segments}
                  dataKey="percent"
                  nameKey="label"
                  cx="50%"
                  cy="50%"
                  innerRadius={40}
                  outerRadius={62}
                  startAngle={90}
                  endAngle={-270}
                  stroke="none"
                  isAnimationActive="auto"
                  animationBegin={120}
                  animationDuration={1200}
                  animationEasing="ease-out"
                >
                  {segments.map((segment) => (
                    <Cell key={segment.label} fill={segment.color} />
                  ))}
                </Pie>
                <Tooltip
                  formatter={(percent, label) => [`${percent}%`, label]}
                  contentStyle={{
                    borderRadius: "8px",
                    borderColor: "#e2e8f0",
                    fontSize: "12px",
                  }}
                />
              </PieChart>
            </ResponsiveContainer>
          </div>
          <div className="pointer-events-none z-10 absolute inset-0 m-auto grid h-20 w-20 place-content-center rounded-full bg-white text-center shadow-inner">
            <strong className="text-2xl text-slate-600">{total}</strong>
            <span className="text-[11px] text-slate-500">Total</span>
          </div>
        </div>
        <div className="grid min-w-40 gap-2 text-xs">
          {segments.map((segment) => (
            <div
              className="grid grid-cols-[8px_1fr_auto] items-center gap-2"
              key={segment.label}
            >
              <i
                className="h-2 w-2 rounded-full"
                style={{ backgroundColor: segment.color }}
              />
              <span className="text-slate-600">{segment.label}</span>
              <strong className="font-medium text-slate-700">
                {segment.value}
              </strong>
            </div>
          ))}
        </div>
      </div>
    </Panel>
  );
}

/**
 * Renders the permission-aware AGIS landing dashboard and its operational summaries.
 * Module cards route only to features exposed by the authenticated user's permissions.
 */
export default function DashboardPage() {
  const { user } = useAuth();
  const { dateLabel } = useOutletContext();
  const toast = useToast();
  const navigate = useNavigate();
  const [live, setLive] = useState(null);
  const [dashboardError, setDashboardError] = useState("");
  const content =
    Object.entries(permissionContent).find(([permission]) =>
      hasPermission(user, permission),
    )?.[1] ?? permissionContent["aems.fieldwork.create"];
  useEffect(() => {
    let active = true;
    coreDashboardApi
      .show()
      .then((data) => {
        if (active) setLive(data);
      })
      .catch((error) => {
        if (active)
          setDashboardError(
            error.message || "Live dashboard data is unavailable.",
          );
      });
    return () => {
      active = false;
    };
  }, []);

  const liveModules = useMemo(
    () => new Map((live?.modules || []).map((module) => [module.key, module])),
    [live],
  );
  const allowedModules = visibleFor(user, modules)
    .filter((module) => module.key !== "core")
    .map((module) => ({ ...module, ...(liveModules.get(module.key) || {}) }));
  const tasks = live?.tasks?.length
    ? live.tasks.map((task) => [task.title, task.code, task.due || "Open"])
    : content.tasks;
  const activities = live?.upcomingActivities?.length
    ? live.upcomingActivities
    : upcomingActivities;
  const recent = live?.recentEngagements?.length
    ? live.recentEngagements.map((item) => [
        item.number,
        item.office || "—",
        item.phase || item.status || "—",
        "—",
        "—",
      ])
    : recentEngagements;
  const quickActions = live?.quickActions || [];
  const engagementStatus = live?.engagementStatus || {};
  const recommendationStatus = live?.recommendationStatus || {};
  const liveEngagementTotal = Object.values(engagementStatus).reduce(
    (sum, value) => sum + Number(value || 0),
    0,
  );
  const liveRecommendationTotal = Object.values(recommendationStatus).reduce(
    (sum, value) => sum + Number(value || 0),
    0,
  );
  const engagementSegments = Object.entries(engagementStatus).map(
    ([label, value], index) => ({
      label: label.replaceAll("_", " "),
      value: String(value),
      percent: liveEngagementTotal
        ? Math.round((Number(value) / liveEngagementTotal) * 100)
        : 0,
      color: ["#4775b8", "#16ad62", "#ffa01c", "#6840a2", "#a9b5c5"][index % 5],
    }),
  );
  const recommendationSegments = Object.entries(recommendationStatus).map(
    ([label, value], index) => ({
      label: label.replaceAll("_", " "),
      value: String(value),
      percent: liveRecommendationTotal
        ? Math.round((Number(value) / liveRecommendationTotal) * 100)
        : 0,
      color: ["#16ad62", "#4775b8", "#ffa01c", "#a9b5c5"][index % 4],
    }),
  );
  const moduleCount = Math.min(Math.max(allowedModules.length, 1), 6);
  const moduleGridClasses = {
    1: "grid-cols-1",
    2: "sm:grid-cols-2",
    3: "sm:grid-cols-2 lg:grid-cols-3",
    4: "sm:grid-cols-2 lg:grid-cols-4",
    5: "sm:grid-cols-2 lg:grid-cols-5",
    6: "sm:grid-cols-2 lg:grid-cols-6",
  }[moduleCount];

  return (
    <div className="p-3 sm:p-5">
      <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 className="text-2xl font-semibold text-slate-700">
            Welcome back, {user.name}!
          </h2>
          <p className="mt-1 text-sm text-slate-500">{content.greeting}</p>
        </div>
        <time className="flex items-center gap-2 text-xs text-slate-500">
          <CalendarDays size={16} />
          {dateLabel}
        </time>
      </div>
      {dashboardError && (
        <div className="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
          Live metrics are temporarily unavailable; showing the last-known
          dashboard layout.
        </div>
      )}

      <section className={`grid gap-3 ${moduleGridClasses}`}>
        {allowedModules.map((module) => (
          <ModuleCard key={module.key} module={module} onOpen={navigate} />
        ))}
      </section>

      <section className="mt-3 grid gap-3 xl:grid-cols-[1fr_1fr_1.35fr]">
        <Panel title="My Tasks" action="View All">
          <div className="divide-y divide-slate-100">
            {tasks.map(([title, code, due]) => (
              <button
                className="flex min-h-16 w-full items-center gap-3 px-4 text-left transition hover:bg-blue-50 hover:pl-5"
                type="button"
                key={title}
                onClick={() => toast.info(`${title} is coming soon.`)}
              >
                <span className="text-slate-500">
                  <BriefcaseBusiness size={21} />
                </span>
                <span className="min-w-0 flex-1">
                  <strong className="block truncate text-sm text-slate-700">
                    {title}
                  </strong>
                  <small className="block text-[11px] text-slate-400">
                    {code}
                  </small>
                </span>
                <time className="text-xs text-slate-500">{due}</time>
              </button>
            ))}
          </div>
        </Panel>

        <Panel title="Upcoming Activities" action="View Calendar">
          <div className="divide-y divide-slate-100">
            {activities.map(([title, detail, date, time]) => (
              <div
                className="flex min-h-16 items-center gap-3 px-4"
                key={title}
              >
                <span className="text-slate-500">
                  <CalendarDays size={21} />
                </span>
                <span className="min-w-0 flex-1">
                  <strong className="block truncate text-sm text-slate-700">
                    {title}
                  </strong>
                  <small className="block truncate text-[11px] text-slate-400">
                    {detail}
                  </small>
                </span>
                <time className="text-right text-xs text-slate-600">
                  <strong className="block">{date}</strong>
                  <span>{time}</span>
                </time>
              </div>
            ))}
          </div>
        </Panel>

        <Panel
          title="Recent Audit Engagements"
          action="View All"
          className="overflow-x-auto"
        >
          <div className="min-w-[620px] p-3 text-xs">
            <div className="grid grid-cols-[1.1fr_1.25fr_.7fr_.5fr_.5fr] gap-3 border-b border-slate-200 px-2 py-2 font-bold text-slate-600">
              <span>Engagement No.</span>
              <span>Office</span>
              <span>Status</span>
              <span>Progress</span>
              <span>Risk Level</span>
            </div>
            {recent.map(([number, office, status, progress, risk]) => (
              <div
                className="grid min-h-10 grid-cols-[1.1fr_1.25fr_.7fr_.5fr_.5fr] items-center gap-3 border-b border-slate-100 px-2 text-slate-500"
                key={number}
              >
                <span>{number}</span>
                <span>{office}</span>
                <span>{status}</span>
                <span>{progress}</span>
                <span className={risk === "High" ? "text-red-500" : ""}>
                  {risk}
                </span>
              </div>
            ))}
          </div>
        </Panel>
      </section>

      <section className="mt-3 grid gap-3 xl:grid-cols-[1fr_1fr_.62fr_.65fr]">
        <DonutPanel
          title="Audit Engagement Status"
          total={live ? liveEngagementTotal : 18}
          segments={
            live
              ? engagementSegments
              : [
                  {
                    label: "Planning",
                    value: "4 (22%)",
                    percent: 22,
                    color: "#4775b8",
                  },
                  {
                    label: "Execution",
                    value: "7 (39%)",
                    percent: 39,
                    color: "#16ad62",
                  },
                  {
                    label: "Reporting",
                    value: "4 (22%)",
                    percent: 22,
                    color: "#ffa01c",
                  },
                  {
                    label: "Completed",
                    value: "2 (11%)",
                    percent: 11,
                    color: "#6840a2",
                  },
                  {
                    label: "Closed",
                    value: "1 (6%)",
                    percent: 6,
                    color: "#a9b5c5",
                  },
                ]
          }
        />
        <DonutPanel
          title="Recommendation Status (CMS)"
          total={live ? liveRecommendationTotal : 21}
          segments={
            live
              ? recommendationSegments
              : [
                  {
                    label: "Completed",
                    value: "7 (33%)",
                    percent: 33,
                    color: "#16ad62",
                  },
                  {
                    label: "In Progress",
                    value: "8 (38%)",
                    percent: 38,
                    color: "#4775b8",
                  },
                  {
                    label: "Overdue",
                    value: "4 (19%)",
                    percent: 19,
                    color: "#ffa01c",
                  },
                  {
                    label: "Closed",
                    value: "2 (10%)",
                    percent: 10,
                    color: "#a9b5c5",
                  },
                ]
          }
        />

        <Panel title="Overdue Recommendations">
          <div className="p-4">
            <strong className="text-2xl text-red-600">
              {live?.overdueRecommendations ?? 4}
            </strong>
            <span className="ml-2 text-xs text-slate-500">Overdue Items</span>
            <ul className="mt-4 grid gap-3 text-xs text-slate-600">
              {[
                "REC-2025-021 — BPLD — 15 days",
                "REC-2025-015 — CHUDD — 10 days",
                "REC-2025-011 — CTO — 7 days",
                "REC-2025-008 — CEO — 3 days",
              ].map((item) => (
                <li className="flex gap-2" key={item}>
                  <i className="mt-1.5 h-1.5 w-1.5 rounded-full bg-red-500" />
                  {item}
                </li>
              ))}
            </ul>
          </div>
        </Panel>

        <Panel title="Quick Actions">
          <div className="grid gap-2 p-3">
            {quickActions.map((action, index) => {
              const ActionIcon = [Plus, Plus, Search, FileText][index];

              return (
                <button
                  className={`flex min-h-10 items-center gap-3 rounded-md px-3 text-left text-xs text-slate-600 transition hover:translate-x-1 ${["bg-emerald-100", "bg-orange-100", "bg-blue-100", "bg-cyan-100"][index]}`}
                  type="button"
                  key={action.path || action.label}
                  onClick={() => navigate(action.path)}
                >
                  <ActionIcon size={17} />
                  {action.label}
                </button>
              );
            })}
            {!quickActions.length && (
              <p className="px-3 py-2 text-xs text-slate-500">
                No authorized quick actions.
              </p>
            )}
          </div>
        </Panel>
      </section>
    </div>
  );
}
