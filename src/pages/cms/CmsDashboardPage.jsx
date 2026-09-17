import { useCallback, useEffect, useState } from "react";
import {
  AlertTriangle,
  CalendarClock,
  CheckCircle2,
  ClipboardCheck,
  Clock3,
  FileCheck2,
  Hourglass,
  MessageSquareText,
  Eye,
  FileWarning,
  RefreshCw,
  RotateCcw,
  ShieldAlert,
  SquareCheckBig,
  UserRoundCheck,
  UserRoundX,
  XCircle,
} from "lucide-react";
import { Link } from "react-router";
import { CmsOverdueBadge, CmsRiskBadge } from "../../components/cms/CmsBadges";
import RegistryHeader from "../../components/ui/RegistryHeader";
import SummaryCard from "../../components/ui/SummaryCard";
import StatusBadge from "../../components/ui/StatusBadge";
import { cmsApi } from "../../services/api";

const metrics = [
  ["totalVisibleCases", "Total visible cases", Eye, "sky"],
  ["transferredOpenCases", "Transferred/open", ClipboardCheck, "slate"],
  ["assignedCases", "Assigned cases", UserRoundCheck, "emerald"],
  ["unassignedCases", "Unassigned cases", UserRoundX, "amber"],
  ["overdueCases", "Overdue cases", AlertTriangle, "red"],
  ["withoutTargetDate", "Without target date", CalendarClock, "amber"],
  ["transferredThisMonth", "Transferred this month", Clock3, "sky"],
  ["highRiskCases", "High-risk cases", ShieldAlert, "amber"],
  ["highRiskOverdueCases", "High-risk overdue", FileWarning, "red"],
  [
    "monitoringCasesWithoutRecordedProgress",
    "Monitoring without recorded progress",
    Hourglass,
    "amber",
  ],
  [
    "progressUpdatesAwaitingReview",
    "Progress updates awaiting review",
    FileCheck2,
    "sky",
  ],
  ["recordedProgressUpdates", "Recorded management updates", ClipboardCheck, "emerald"],
  [
    "managementReportedCompleteAwaitingValidation",
    "Reported complete awaiting validation",
    FileCheck2,
    "amber",
  ],
  ["casesAwaitingValidationAssignment", "Cases awaiting validator assignment", UserRoundX, "amber"],
  ["activeValidations", "Active validations", ClipboardCheck, "sky"],
  ["validationsAwaitingSupervisoryReview", "Validations awaiting supervisory review", FileCheck2, "warning"],
  ["returnedValidations", "Returned validations", FileWarning, "red"],
  ["extensionRequestsInDraft", "Extension drafts", FileCheck2, "slate"],
  ["extensionRequestsAwaitingReview", "Extensions awaiting review", Clock3, "amber"],
  ["extensionRequestsAwaitingApproval", "Extensions awaiting approval", Clock3, "warning"],
  ["returnedExtensionRequests", "Extensions returned", FileWarning, "red"],
  ["approvedExtensions", "Approved extensions", CheckCircle2, "emerald"],
  ["rejectedExtensionRequests", "Rejected extensions", XCircle, "red"],
  ["recommendationsEligibleForEscalation", "Recommendations eligible for escalation", ShieldAlert, "amber"],
  ["activeEscalations", "Active escalations", ShieldAlert, "red"],
  ["noticesAwaitingReview", "Escalation notices awaiting review", FileCheck2, "warning"],
  ["issuedNoticesAwaitingAcknowledgement", "Issued notices awaiting acknowledgement", UserRoundCheck, "amber"],
  ["responsesOverdue", "Escalation responses overdue", AlertTriangle, "red"],
  ["responsesAwaitingReview", "Escalation responses awaiting review", MessageSquareText, "sky"],
  ["escalationsInFollowUp", "Escalations in follow-up", Clock3, "emerald"],
  ["resolvedEscalations", "Escalations resolved (process only)", CheckCircle2, "slate"],
  ["implementedRecommendationsEligibleForClosure", "Implemented eligible for closure", ShieldAlert, "emerald"],
  ["closureRequestsInDraft", "Closure drafts", FileCheck2, "slate"],
  ["closureRequestsAwaitingReview", "Closures awaiting review", UserRoundCheck, "amber"],
  ["closureRequestsAwaitingDecision", "Closures awaiting decision", Clock3, "warning"],
  ["returnedClosureRequests", "Closures returned", FileWarning, "red"],
  ["recentlyClosedRecommendations", "Recently closed", CheckCircle2, "emerald"],
  ["totalClosedRecommendations", "Total formally closed", CheckCircle2, "sky"],
  ["terminalRecommendationsEligibleForReopening", "Terminal recommendations eligible for reopening", RotateCcw, "amber"],
  ["reopeningDrafts", "Reopening drafts", FileCheck2, "slate"],
  ["reopeningRequestsAwaitingReview", "Reopenings awaiting review", UserRoundCheck, "warning"],
  ["reopeningRequestsAwaitingDecision", "Reopenings awaiting decision", Clock3, "warning"],
  ["returnedReopeningRequests", "Reopenings returned", FileWarning, "red"],
  ["rejectedReopeningRequests", "Reopenings rejected", XCircle, "red"],
  ["recentlyReopenedRecommendations", "Recently reopened", RotateCcw, "emerald"],
];

const attention = [
  {
    key: "overdueCases",
    label: "Overdue recommendations",
    description: "Target dates have passed and the case remains unresolved.",
    query: "overdue=1",
    icon: AlertTriangle,
  },
  {
    key: "highRiskOverdueCases",
    label: "High-risk overdue",
    description: "High-priority recommendations requiring immediate attention.",
    query: "risk=HIGH&overdue=1",
    icon: ShieldAlert,
  },
  {
    key: "unassignedCases",
    label: "Unassigned recommendations",
    description: "Cases without a current Compliance Monitor.",
    query: "assigned=0",
    icon: UserRoundX,
  },
  {
    key: "withoutTargetDate",
    label: "Without target dates",
    description: "Recommendations that do not yet have an effective target date.",
    query: "hasTargetDate=0",
    icon: CalendarClock,
  },
];

function formatDateTime(value) {
  if (!value) return "Evaluation time unavailable";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "Evaluation time unavailable";
  return new Intl.DateTimeFormat("en-PH", {
    dateStyle: "long",
    timeStyle: "short",
  }).format(date);
}

function DashboardSkeleton() {
  return (
    <div aria-label="Loading CMS dashboard" className="grid gap-5">
      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        {Array.from({ length: 6 }).map((_, index) => (
          <div
            className="h-24 animate-pulse rounded-xl bg-slate-200"
            key={index}
          />
        ))}
      </div>
      <div className="h-56 animate-pulse rounded-xl bg-slate-200" />
    </div>
  );
}

function ErrorState({ message, onRetry }) {
  return (
    <div className="rounded-2xl border border-red-200 bg-red-50 px-6 py-12 text-center">
      <AlertTriangle className="mx-auto text-red-600" size={34} />
      <h3 className="mt-3 font-bold text-red-900">Dashboard unavailable</h3>
      <p className="mx-auto mt-2 max-w-xl text-sm text-red-700">{message}</p>
      <button
        className="mt-5 inline-flex h-10 items-center gap-2 rounded-lg bg-red-700 px-4 text-sm font-bold text-white hover:bg-red-800"
        onClick={onRetry}
        type="button"
      >
        <RefreshCw size={16} /> Retry
      </button>
    </div>
  );
}

function GroupPanel({ title, items = [] }) {
  const maximum = Math.max(1, ...items.map((item) => item.count));

  return (
    <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
      <h3 className="font-bold text-slate-800">{title}</h3>
      {items.length === 0 ? (
        <p className="mt-5 text-sm text-slate-500">No grouped records.</p>
      ) : (
        <div className="mt-4 grid gap-3">
          {items.map((item) => (
            <div key={`${item.code}-${item.id ?? "none"}`}>
              <div className="flex items-center justify-between gap-3 text-sm">
                <span className="min-w-0 truncate text-slate-600" title={item.label}>
                  {item.label}
                </span>
                <strong className="text-slate-800">{item.count}</strong>
              </div>
              <div className="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100">
                <div
                  className="h-full rounded-full bg-sky-600"
                  style={{ width: `${Math.max(4, (item.count / maximum) * 100)}%` }}
                />
              </div>
            </div>
          ))}
        </div>
      )}
    </section>
  );
}

function RecommendationList({ title, records = [], emptyMessage }) {
  return (
    <section className="rounded-xl border border-slate-200 bg-white shadow-sm">
      <header className="border-b border-slate-200 px-4 py-3">
        <h3 className="font-bold text-slate-800">{title}</h3>
      </header>
      {records.length === 0 ? (
        <p className="px-5 py-10 text-center text-sm text-slate-500">
          {emptyMessage}
        </p>
      ) : (
        <div className="divide-y divide-slate-100">
          {records.map((record) => (
            <Link
              className="block px-4 py-3 transition hover:bg-sky-50 focus-visible:outline-2 focus-visible:outline-sky-600"
              key={record.id}
              to={`/compliance-management/recommendations/${record.id}`}
            >
              <div className="flex flex-wrap items-center justify-between gap-2">
                <strong className="text-sm text-sky-800">
                  {record.cmsRecommendationCode}
                </strong>
                <div className="flex flex-wrap gap-2">
                  <CmsRiskBadge risk={record.risk} />
                  <CmsOverdueBadge overdue={record.isOverdue} />
                </div>
              </div>
              <p className="mt-1 text-xs text-slate-500">
                {record.responsibleOffice?.name || "No responsible office"} ·
                Target: {record.effectiveTargetDate || "Not set"}
              </p>
            </Link>
          ))}
        </div>
      )}
    </section>
  );
}

export default function CmsDashboardPage() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  const load = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      setData(await cmsApi.getDashboard());
    } catch (requestError) {
      setError(requestError.message || "The CMS dashboard could not be loaded.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    let active = true;
    cmsApi
      .getDashboard()
      .then((result) => {
        if (active) setData(result);
      })
      .catch((requestError) => {
        if (active) {
          setError(
            requestError.message || "The CMS dashboard could not be loaded.",
          );
        }
      })
      .finally(() => {
        if (active) setLoading(false);
      });
    return () => {
      active = false;
    };
  }, []);

  const scopeLabel = data?.scope?.portfolioWide
    ? "Portfolio-wide scope"
    : data?.scope?.officeId
      ? "Responsible-office scope"
      : data?.scope?.assignmentScoped
        ? "Assigned-case scope"
        : "Authorized scope";

  return (
    <main className="min-w-0 p-4 sm:p-5 lg:p-6">
      <RegistryHeader
        actions={
          <>
            <button
              aria-label="Refresh CMS dashboard"
              className="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700 hover:bg-slate-50"
              disabled={loading}
              onClick={load}
              type="button"
            >
              <RefreshCw className={loading ? "animate-spin" : ""} size={16} />
              Refresh
            </button>
            <Link
              className="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white hover:bg-sky-800"
              to="/compliance-management/recommendations"
            >
              <ClipboardCheck size={16} /> Recommendation Registry
            </Link>
          </>
        }
        description="Monitor recommendations transferred from issued AEMS reports within your authorized scope."
        icon={SquareCheckBig}
        title="Compliance Management"
      />

      {loading && !data ? (
        <DashboardSkeleton />
      ) : error && !data ? (
        <ErrorState message={error} onRetry={load} />
      ) : (
        <div className="grid gap-5">
          {error && (
            <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
              Refresh failed. Showing the last successfully loaded dashboard.
            </div>
          )}

          <div className="flex flex-wrap items-center gap-2 text-xs text-slate-600">
            <span className="rounded-full bg-slate-100 px-3 py-1.5 font-semibold">
              {scopeLabel}
            </span>
            <span className="rounded-full bg-slate-100 px-3 py-1.5">
              Evaluated {formatDateTime(data?.evaluationDateTime)}
            </span>
          </div>

          <section aria-label="CMS summary metrics" className="grid grid-cols-[repeat(auto-fit,minmax(220px,1fr))] gap-3">
            {metrics
              .filter(([key]) =>
                Object.prototype.hasOwnProperty.call(data?.cards ?? {}, key),
              )
              .map(([key, label, Icon, tone]) => (
                <SummaryCard
                  icon={Icon}
                  key={key}
                  label={label}
                  tone={tone}
                  value={data.cards[key]}
                />
              ))}
          </section>

          <section>
            <h3 className="mb-3 text-lg font-bold text-slate-800">
              Needs Attention
            </h3>
            <div className="grid grid-cols-[repeat(auto-fit,minmax(220px,1fr))] gap-3">
              {attention.map((item) => {
                const Icon = item.icon;
                return (
                  <Link
                    className="group rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-sky-300 hover:shadow-md"
                    key={item.key}
                    to={`/compliance-management/recommendations?${item.query}`}
                  >
                    <div className="flex items-center justify-between gap-3">
                      <span className="grid h-9 w-9 place-items-center rounded-lg bg-amber-100 text-amber-700">
                        <Icon size={18} />
                      </span>
                      <strong className="text-2xl text-slate-900">
                        {data?.cards?.[item.key] ?? 0}
                      </strong>
                    </div>
                    <h4 className="mt-3 font-bold text-slate-800 group-hover:text-sky-800">
                      {item.label}
                    </h4>
                    <p className="mt-1 text-xs leading-5 text-slate-500">
                      {item.description}
                    </p>
                  </Link>
                );
              })}
            </div>
          </section>

          {data?.cards?.finalizedValidationConclusions && (
            <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <h3 className="font-bold text-slate-800">Finalized validation conclusions</h3>
                  <p className="mt-1 text-xs leading-5 text-slate-500">Professional conclusions are shown separately from recommendation closure. Implemented does not mean closed.</p>
                </div>
                <StatusBadge tone="warning">Closure workflow pending</StatusBadge>
              </div>
              <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                {[["NOT_IMPLEMENTED", "Not Implemented"], ["PARTIALLY_IMPLEMENTED", "Partially Implemented"], ["IMPLEMENTED", "Implemented"], ["INADEQUATE_BASIS", "Inadequate Basis"]].map(([code, label]) => (
                  <div className="rounded-lg border border-slate-200 bg-slate-50 p-3" key={code}>
                    <p className="text-xs font-bold uppercase tracking-wide text-slate-500">{label}</p>
                    <p className="mt-1 text-2xl font-bold text-slate-800">{data.cards.finalizedValidationConclusions[code] ?? 0}</p>
                  </div>
                ))}
              </div>
            </section>
          )}

          {data?.dueSoon?.available === false && (
            <div className="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
              <strong>Due-soon metric unavailable.</strong>{" "}
              {data.dueSoon.reason}
            </div>
          )}

          <section>
            <h3 className="mb-3 text-lg font-bold text-slate-800">
              Portfolio summaries
            </h3>
            <div className="grid grid-cols-[repeat(auto-fit,minmax(240px,1fr))] gap-4">
              <GroupPanel
                items={data?.groups?.byResponsibleOffice}
                title="Responsible office"
              />
              <GroupPanel items={data?.groups?.byRiskLevel} title="Risk level" />
              <GroupPanel
                items={data?.groups?.byConfidentialityLevel}
                title="Confidentiality"
              />
              <GroupPanel
                items={data?.groups?.byAssignedMonitor}
                title="Assigned monitor"
              />
            </div>
          </section>

          <section className="grid gap-4 xl:grid-cols-2">
            <RecommendationList
              emptyMessage="No recommendations have been transferred in your scope."
              records={data?.recentlyTransferred}
              title="Recently transferred"
            />
            <RecommendationList
              emptyMessage="No unresolved recommendations with target dates."
              records={data?.oldestUnresolvedTargetDates}
              title="Oldest unresolved target dates"
            />
          </section>

          {(data?.dataLimitations?.length ?? 0) > 0 && (
            <section className="rounded-xl border border-slate-200 bg-slate-50 p-4">
              <h3 className="text-sm font-bold text-slate-700">
                Current data limitations
              </h3>
              <ul className="mt-2 list-disc space-y-1 pl-5 text-xs leading-5 text-slate-600">
                {data.dataLimitations.map((limitation) => (
                  <li key={limitation}>{limitation}</li>
                ))}
              </ul>
            </section>
          )}
        </div>
      )}
    </main>
  );
}
