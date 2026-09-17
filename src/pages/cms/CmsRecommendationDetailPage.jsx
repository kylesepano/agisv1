import { useCallback, useEffect, useMemo, useState } from "react";
import {
  AlertTriangle,
  ArrowLeft,
  CalendarClock,
  ClipboardCheck,
  FileCheck2,
  History,
  Link2,
  ListChecks,
  RefreshCw,
  ShieldCheck,
  ShieldAlert,
  RotateCcw,
  UserRound,
  UserRoundMinus,
  UserRoundPlus,
} from "lucide-react";
import { Link, useParams, useSearchParams } from "react-router";
import { useAuth } from "../../auth/auth-context";
import {
  CmsOverdueBadge,
  CmsRiskBadge,
  CmsStatusBadge,
} from "../../components/cms/CmsBadges";
import { labelFor } from "../../components/cms/cms-format";
import CmsMonitorAssignmentDialog from "../../components/cms/CmsMonitorAssignmentDialog";
import CmsRecommendationTimeline from "../../components/cms/CmsRecommendationTimeline";
import ConfirmDialog from "../../components/ui/ConfirmDialog";
import FormField from "../../components/ui/FormField";
import RegistryHeader from "../../components/ui/RegistryHeader";
import { hasPermission } from "../../config/navigation";
import { ApiError, cmsApi } from "../../services/api";
import { useToast } from "../../ui/toast-context";

const tabs = [
  ["overview", "Overview", ClipboardCheck],
  ["source", "Source & Lineage", Link2],
  ["assignments", "Assignments", UserRound],
  ["history", "History", History],
  ["closure", "Closure", ShieldCheck],
  ["dispositions", "Dispositions", ShieldAlert],
  ["reopening", "Reopening", RotateCcw],
];

function displayDate(value, includeTime = false) {
  if (!value) return "Not available";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "Not available";
  return new Intl.DateTimeFormat("en-PH", {
    dateStyle: "medium",
    ...(includeTime ? { timeStyle: "short" } : {}),
  }).format(date);
}

function valueOrFallback(value) {
  return value || "Not available";
}

function DetailSkeleton() {
  return (
    <div aria-label="Loading recommendation details" className="grid gap-4">
      <div className="h-24 animate-pulse rounded-xl bg-slate-200" />
      <div className="h-12 animate-pulse rounded-xl bg-slate-200" />
      <div className="h-80 animate-pulse rounded-xl bg-slate-200" />
    </div>
  );
}

function ReadOnlyField({ label, children, mono = false, wide = false }) {
  return (
    <div
      className={`min-w-0 rounded-lg border border-slate-200 bg-slate-50 p-3 ${wide ? "sm:col-span-2" : ""}`}
    >
      <dt className="text-xs font-bold uppercase tracking-wide text-slate-500">
        {label}
      </dt>
      <dd
        className={`mt-1 break-words text-sm text-slate-800 ${mono ? "font-mono text-xs" : ""}`}
      >
        {children}
      </dd>
    </div>
  );
}

function AssignmentCard({ assignment }) {
  return (
    <article className="rounded-xl border border-slate-200 bg-white p-4">
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div>
          <h4 className="font-bold text-slate-800">
            {assignment.user?.name || "Unknown user"}
          </h4>
          <p className="text-xs text-slate-500">
            {assignment.user?.employeeId || "Employee ID unavailable"}
          </p>
        </div>
        <span
          className={`rounded-full px-2.5 py-1 text-xs font-bold ${
            assignment.isCurrent
              ? "bg-emerald-100 text-emerald-700"
              : "bg-slate-100 text-slate-600"
          }`}
        >
          {assignment.isCurrent ? "Current" : "Ended"}
        </span>
      </div>
      <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2">
        <div>
          <dt className="text-xs font-semibold text-slate-500">Assigned</dt>
          <dd className="mt-0.5 text-slate-700">
            {displayDate(assignment.assignedAt, true)}
          </dd>
        </div>
        <div>
          <dt className="text-xs font-semibold text-slate-500">Assigned by</dt>
          <dd className="mt-0.5 text-slate-700">
            {assignment.assignedBy?.name || "System"}
          </dd>
        </div>
        <div className="sm:col-span-2">
          <dt className="text-xs font-semibold text-slate-500">Reason</dt>
          <dd className="mt-0.5 whitespace-pre-wrap text-slate-700">
            {assignment.assignmentReason || "No reason recorded"}
          </dd>
        </div>
        {!assignment.isCurrent && (
          <>
            <div>
              <dt className="text-xs font-semibold text-slate-500">Ended</dt>
              <dd className="mt-0.5 text-slate-700">
                {displayDate(assignment.endedAt, true)}
              </dd>
            </div>
            <div>
              <dt className="text-xs font-semibold text-slate-500">Ended by</dt>
              <dd className="mt-0.5 text-slate-700">
                {assignment.endedBy?.name || "System"}
              </dd>
            </div>
            <div className="sm:col-span-2">
              <dt className="text-xs font-semibold text-slate-500">
                End or replacement reason
              </dt>
              <dd className="mt-0.5 whitespace-pre-wrap text-slate-700">
                {assignment.endReason || "No reason recorded"}
              </dd>
            </div>
          </>
        )}
      </dl>
    </article>
  );
}

export default function CmsRecommendationDetailPage() {
  const { recommendationId } = useParams();
  const [searchParams, setSearchParams] = useSearchParams();
  const { user } = useAuth();
  const toast = useToast();
  const canAssign = hasPermission(user, "cms.recommendation.assign");
  const canViewActionPlan = hasPermission(user, "cms.action-plan.view");
  const canViewProgress = hasPermission(user, "cms.progress.view");
  const canViewValidation = hasPermission(user, "cms.validation.view");
  const canViewExtension = hasPermission(user, "cms.extension.view");
  const canViewEscalation = hasPermission(user, "cms.escalation.view");
  const canViewClosure = hasPermission(user, "cms.closure.view");
  const canViewDisposition = hasPermission(user, "cms.disposition.view");
  const canViewReopening = hasPermission(user, "cms.reopening.view");
  const visibleTabs = tabs.filter(([key]) => (key !== "closure" || canViewClosure) && (key !== "dispositions" || canViewDisposition) && (key !== "reopening" || canViewReopening));
  const activeTab = visibleTabs.some(([key]) => key === searchParams.get("tab"))
    ? searchParams.get("tab")
    : "overview";
  const [record, setRecord] = useState(null);
  const [assignmentData, setAssignmentData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [assignmentOpen, setAssignmentOpen] = useState(false);
  const [assignmentBusy, setAssignmentBusy] = useState(false);
  const [assignmentErrors, setAssignmentErrors] = useState({});
  const [assignmentMessage, setAssignmentMessage] = useState("");
  const [endOpen, setEndOpen] = useState(false);
  const [endReason, setEndReason] = useState("");
  const [endError, setEndError] = useState("");
  const [endBusy, setEndBusy] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const requests = [cmsApi.getRecommendation(recommendationId)];
      if (canAssign) requests.push(cmsApi.getAssignments(recommendationId));
      const [recommendation, assignments] = await Promise.all(requests);
      setRecord(recommendation);
      setAssignmentData(assignments ?? null);
    } catch (requestError) {
      setError(
        requestError.status === 404
          ? "This recommendation is unavailable or outside your authorized scope."
          : requestError.message || "The recommendation could not be loaded.",
      );
    } finally {
      setLoading(false);
    }
  }, [canAssign, recommendationId]);

  useEffect(() => {
    let active = true;
    const requests = [cmsApi.getRecommendation(recommendationId)];
    if (canAssign) requests.push(cmsApi.getAssignments(recommendationId));
    Promise.all(requests)
      .then(([recommendation, assignments]) => {
        if (!active) return;
        setRecord(recommendation);
        setAssignmentData(assignments ?? null);
      })
      .catch((requestError) => {
        if (!active) return;
        setError(
          requestError.status === 404
            ? "This recommendation is unavailable or outside your authorized scope."
            : requestError.message || "The recommendation could not be loaded.",
        );
      })
      .finally(() => {
        if (active) setLoading(false);
      });
    return () => {
      active = false;
    };
  }, [canAssign, recommendationId]);

  const assignments = useMemo(
    () => assignmentData?.assignments ?? record?.assignments ?? [],
    [assignmentData, record],
  );
  const currentMonitor =
    assignments.find((assignment) => assignment.isCurrent) ??
    record?.currentMonitor ??
    null;
  const lockVersion = assignmentData?.lockVersion ?? record?.lockVersion;

  async function refreshAfterConflict(error) {
    const lockErrors = error?.errors?.lockVersion;
    const conflict =
      error instanceof ApiError &&
      (error.status === 409 || Boolean(lockErrors));
    if (!conflict) return false;
    toast.warning(
      "This recommendation changed while you were working. Current data has been reloaded.",
    );
    setAssignmentOpen(false);
    setEndOpen(false);
    await load();
    return true;
  }

  async function submitAssignment(values) {
    setAssignmentBusy(true);
    setAssignmentErrors({});
    setAssignmentMessage("");
    try {
      await cmsApi.assignMonitor(recommendationId, {
        userId: Number(values.userId),
        reason: values.reason.trim() || null,
        effectiveFrom: values.effectiveFrom || null,
        effectiveUntil: values.effectiveUntil || null,
        lockVersion,
      });
      toast.success(
        currentMonitor
          ? "Compliance Monitor replaced successfully."
          : "Compliance Monitor assigned successfully.",
      );
      setAssignmentOpen(false);
      await load();
    } catch (requestError) {
      if (await refreshAfterConflict(requestError)) return;
      setAssignmentErrors(requestError.errors ?? {});
      setAssignmentMessage(
        requestError.message || "The assignment could not be saved.",
      );
    } finally {
      setAssignmentBusy(false);
    }
  }

  async function endAssignment() {
    if (!endReason.trim()) {
      setEndError("An end reason is required.");
      return;
    }
    setEndBusy(true);
    setEndError("");
    try {
      await cmsApi.endMonitorAssignment(
        recommendationId,
        currentMonitor.id,
        {
          reason: endReason.trim(),
          lockVersion,
        },
      );
      toast.success("Compliance Monitor assignment ended successfully.");
      setEndOpen(false);
      setEndReason("");
      await load();
    } catch (requestError) {
      if (await refreshAfterConflict(requestError)) return;
      const reasonError = requestError.errors?.reason;
      setEndError(
        (Array.isArray(reasonError) ? reasonError[0] : reasonError) ||
          requestError.message ||
          "The assignment could not be ended.",
      );
    } finally {
      setEndBusy(false);
    }
  }

  if (loading && !record) return <DetailSkeleton />;

  if (error && !record) {
    return (
      <div className="rounded-2xl border border-red-200 bg-red-50 px-6 py-14 text-center">
        <AlertTriangle className="mx-auto text-red-600" size={36} />
        <h2 className="mt-3 text-xl font-bold text-slate-800">
          Recommendation unavailable
        </h2>
        <p className="mx-auto mt-2 max-w-xl text-sm text-slate-600">{error}</p>
        <div className="mt-5 flex flex-wrap justify-center gap-2">
          <Link
            className="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700"
            to="/compliance-management/recommendations"
          >
            <ArrowLeft size={16} /> Back to Registry
          </Link>
          <button
            className="inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white"
            onClick={load}
            type="button"
          >
            <RefreshCw size={16} /> Retry
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="min-w-0">
      <RegistryHeader
        actions={
          <div className="flex flex-wrap gap-2">
            {canViewActionPlan && (
              <Link
                className="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white hover:bg-sky-800"
                to={`/compliance-management/recommendations/${recommendationId}/action-plan`}
              >
                <ListChecks size={16} />
                {record?.actionPlanSummary?.hasActionPlan
                  ? "Open Action Plan"
                  : "Prepare Action Plan"}
              </Link>
            )}
            {canViewProgress && (
              <Link
                className="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-sky-300 bg-sky-50 px-4 text-sm font-bold text-sky-800 hover:bg-sky-100"
                to={`/compliance-management/recommendations/${recommendationId}/progress-updates`}
              >
                <FileCheck2 size={16} /> Progress Updates
              </Link>
            )}
            {canViewValidation && (
              <Link
                className="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-emerald-300 bg-emerald-50 px-4 text-sm font-bold text-emerald-800 hover:bg-emerald-100"
                to={`/compliance-management/recommendations/${recommendationId}/validations`}
              >
                <ClipboardCheck size={16} /> Validations
              </Link>
            )}
            {canViewExtension && (
              <Link
                className="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-amber-300 bg-amber-50 px-4 text-sm font-bold text-amber-800 hover:bg-amber-100"
                to={`/compliance-management/recommendations/${recommendationId}/extensions`}
              >
                <CalendarClock size={16} /> Target-Date Extensions
              </Link>
            )}
            {canViewEscalation && (
              <Link
                className="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-red-300 bg-red-50 px-4 text-sm font-bold text-red-800 hover:bg-red-100"
                to={`/compliance-management/recommendations/${recommendationId}/escalations`}
              >
                <ShieldCheck size={16} /> Escalations
              </Link>
            )}
            <Link
              className="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700 hover:bg-slate-50"
              to="/compliance-management/recommendations"
            >
              <ArrowLeft size={16} /> Back to Registry
            </Link>
            <button
              aria-label="Refresh recommendation details"
              className="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700 hover:bg-slate-50"
              disabled={loading}
              onClick={load}
              type="button"
            >
              <RefreshCw className={loading ? "animate-spin" : ""} size={16} />
              Refresh
            </button>
          </div>
        }
        description="Read-only recommendation intake, source lineage, monitor assignments, and event history."
        icon={ShieldCheck}
        title={record.cmsRecommendationCode}
      />

      {error && (
        <div className="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
          Refresh failed. Showing the last successfully loaded record.
        </div>
      )}

      <section className="mb-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div className="flex flex-wrap items-center gap-2">
          <CmsStatusBadge status={record.status} />
          <CmsRiskBadge risk={record.risk} />
          <CmsOverdueBadge overdue={record.isOverdue} />
        </div>
        <div className="mt-4 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
          <HeaderDatum
            label="Responsible office"
            value={record.responsibleOffice?.name || "Not assigned"}
          />
          <HeaderDatum
            label="Effective target date"
            value={displayDate(record.effectiveTargetDate)}
          />
          <HeaderDatum
            label="Current Compliance Monitor"
            value={currentMonitor?.user?.name || "Unassigned"}
          />
          <HeaderDatum
            label="Transferred/opened"
            value={displayDate(record.transferredAt || record.openedAt, true)}
          />
        </div>
      </section>

      <div
        aria-label="Recommendation workspace sections"
        className="mb-4 flex overflow-x-auto rounded-xl border border-slate-200 bg-white p-1 shadow-sm"
        role="tablist"
      >
        {visibleTabs.map(([key, label, Icon]) => (
          <button
            aria-selected={activeTab === key}
            className={`inline-flex min-w-max flex-1 items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-bold transition focus-visible:outline-2 focus-visible:outline-sky-600 ${
              activeTab === key
                ? "bg-sky-700 text-white"
                : "text-slate-600 hover:bg-slate-50"
            }`}
            key={key}
            onClick={() => {
              const next = new URLSearchParams(searchParams);
              next.set("tab", key);
              setSearchParams(next, { replace: true });
            }}
            role="tab"
            type="button"
          >
            <Icon size={16} /> {label}
          </button>
        ))}
      </div>

      <section
        aria-label={`${labelFor(activeTab)} details`}
        className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5"
        role="tabpanel"
      >
        {activeTab === "overview" && (
            <Overview
              canViewActionPlan={canViewActionPlan}
              canViewValidation={canViewValidation}
              canViewExtension={canViewExtension}
              canViewEscalation={canViewEscalation}
              canViewClosure={canViewClosure}
              canViewDisposition={canViewDisposition}
              currentMonitor={currentMonitor}
              record={record}
          />
        )}
        {activeTab === "source" && <SourceLineage record={record} />}
        {activeTab === "assignments" && (
          <div>
            <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
              <div>
                <h3 className="text-lg font-bold text-slate-800">
                  Compliance Monitor assignments
                </h3>
                <p className="mt-1 text-sm text-slate-500">
                  Assignment history is retained when a monitor is replaced or
                  ended.
                </p>
              </div>
              {canAssign && (
                <div className="flex flex-wrap gap-2">
                  <button
                    className="inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white hover:bg-sky-800"
                    onClick={() => {
                      setAssignmentErrors({});
                      setAssignmentMessage("");
                      setAssignmentOpen(true);
                    }}
                    type="button"
                  >
                    <UserRoundPlus size={16} />
                    {currentMonitor ? "Replace Monitor" : "Assign Monitor"}
                  </button>
                  {currentMonitor && (
                    <button
                      className="inline-flex h-10 items-center gap-2 rounded-lg border border-red-300 bg-white px-4 text-sm font-bold text-red-700 hover:bg-red-50"
                      onClick={() => {
                        setEndReason("");
                        setEndError("");
                        setEndOpen(true);
                      }}
                      type="button"
                    >
                      <UserRoundMinus size={16} /> End Assignment
                    </button>
                  )}
                </div>
              )}
            </div>
            {assignments.length === 0 ? (
              <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-12 text-center text-sm text-slate-500">
                No Compliance Monitor assignments have been recorded.
              </div>
            ) : (
              <div className="grid gap-3 lg:grid-cols-2">
                {assignments.map((assignment) => (
                  <AssignmentCard assignment={assignment} key={assignment.id} />
                ))}
              </div>
            )}
          </div>
        )}
        {activeTab === "history" && (
          <div>
            <h3 className="mb-5 text-lg font-bold text-slate-800">
              Recommendation event history
            </h3>
            <CmsRecommendationTimeline events={record.timeline} />
          </div>
        )}
        {activeTab === "closure" && canViewClosure && (
          <ClosureSummary record={record} />
        )}
        {activeTab === "dispositions" && canViewDisposition && (
          <DispositionSummary record={record} />
        )}
        {activeTab === "reopening" && canViewReopening && (
          <div>
            <div className="rounded-xl border border-amber-200 bg-amber-50 p-4">
              <h3 className="text-lg font-bold text-slate-800">Controlled recommendation reopening</h3>
              <p className="mt-1 max-w-3xl text-sm leading-6 text-slate-700">
                Historical Closure and Disposition Decisions remain immutable. Only an approved Reopening Decision can start a new active compliance cycle.
              </p>
            </div>
            <div className="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-4">
              <div>
                <p className="text-xs font-bold uppercase tracking-wide text-sky-700">Reopening Requests</p>
                <p className="mt-1 text-sm text-slate-600">{record.reopeningSummary?.priorRequestCount ?? 0} request families · active cycle {record.reopeningSummary?.activeCycleNumber ?? record.activeCycleNumber ?? 1}</p>
              </div>
              <Link className="inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white hover:bg-sky-800" to={`/compliance-management/recommendations/${record.id}/reopening-requests`}><RotateCcw size={16} /> Open Reopening workspace</Link>
            </div>
          </div>
        )}
      </section>

      {assignmentOpen && (
        <CmsMonitorAssignmentDialog
          busy={assignmentBusy}
          currentMonitor={currentMonitor}
          eligibleMonitors={assignmentData?.eligibleMonitors}
          errors={assignmentErrors}
          message={assignmentMessage}
          onClose={() => setAssignmentOpen(false)}
          onSubmit={submitAssignment}
          open
        />
      )}

      <ConfirmDialog
        busy={endBusy}
        confirmLabel="End assignment"
        description={`End the current Compliance Monitor assignment for ${currentMonitor?.user?.name || "this user"}? The assignment will remain in history.`}
        onCancel={() => setEndOpen(false)}
        onConfirm={endAssignment}
        open={endOpen}
        title="End Compliance Monitor assignment"
        tone="warning"
      >
        <div className="mt-4">
          <FormField
            error={endError}
            htmlFor="cms-end-reason"
            label="End reason"
            required
          >
            <textarea
              className="mt-1 min-h-24 w-full rounded-lg border border-slate-300 bg-white p-3 text-sm text-slate-800 outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100"
              id="cms-end-reason"
              maxLength={2000}
              onChange={(event) => setEndReason(event.target.value)}
              value={endReason}
            />
          </FormField>
        </div>
      </ConfirmDialog>
    </div>
  );
}

function HeaderDatum({ label, value }) {
  return (
    <div>
      <p className="text-xs font-bold uppercase tracking-wide text-slate-500">
        {label}
      </p>
      <p className="mt-1 font-semibold text-slate-800">{value}</p>
    </div>
  );
}

function Overview({ record: sourceRecord, currentMonitor, canViewActionPlan, canViewValidation, canViewExtension, canViewEscalation, canViewClosure, canViewDisposition }) {
  const record = sourceRecord.dispositionSummary?.acceptedRisk
    ? { ...sourceRecord, dispositionSummary: { ...sourceRecord.dispositionSummary, acceptedRisk: false, activeRequestStatus: "Accepted Risk" } }
    : sourceRecord;
  const actionPlan = record.actionPlanSummary;

  return (
    <div>
      <h3 className="text-lg font-bold text-slate-800">Recommendation overview</h3>
      <div className="mt-4 rounded-xl border border-sky-100 bg-sky-50 p-4">
        <p className="text-xs font-bold uppercase tracking-wide text-sky-700">
          Exact recommendation wording
        </p>
        <p className="mt-2 whitespace-pre-wrap text-sm leading-6 text-slate-800">
          {record.recommendation || "Recommendation wording is unavailable."}
        </p>
      </div>
      <dl className="mt-4 grid gap-3 sm:grid-cols-2">
        <ReadOnlyField label="Finding">
          {valueOrFallback(record.finding?.code)} —{" "}
          {valueOrFallback(record.finding?.title)}
        </ReadOnlyField>
        <ReadOnlyField label="Lead responsible office">
          {record.officeAccountability?.leadResponsibleOffice?.name ||
            record.responsibleOffice?.name ||
            "Not assigned"}
        </ReadOnlyField>
        <ReadOnlyField label="Original target date">
          {displayDate(record.intake?.originalTargetDate)}
        </ReadOnlyField>
        <ReadOnlyField label="Current effective target date">
          {displayDate(record.effectiveTargetDate)}
          {record.isOverdue && <span className="ml-2 text-red-700">(Overdue)</span>}
        </ReadOnlyField>
        <ReadOnlyField label="Transfer/opened date">
          {displayDate(record.transferredAt || record.openedAt, true)}
        </ReadOnlyField>
        <ReadOnlyField label="Current status">
          {labelFor(record.status)}
        </ReadOnlyField>
        <ReadOnlyField label="Risk">
          {record.risk?.label || record.risk?.code || "Not rated"}
        </ReadOnlyField>
        <ReadOnlyField label="Confidentiality">
          {record.confidentiality?.label ||
            record.confidentiality?.code ||
            "Not specified"}
        </ReadOnlyField>
        <ReadOnlyField label="Current Compliance Monitor" wide>
          {currentMonitor?.user?.name
            ? `${currentMonitor.user.name} (${currentMonitor.user.employeeId || "ID unavailable"})`
            : "Unassigned"}
        </ReadOnlyField>
      </dl>
      {canViewActionPlan && (
        <div className="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div>
              <p className="text-xs font-bold uppercase tracking-wide text-slate-500">
                Corrective Action Plan
              </p>
              <p className="mt-1 text-sm font-semibold text-slate-800">
                {actionPlan?.hasActionPlan
                  ? `Current version ${actionPlan.currentVersionNumber ?? "unavailable"} · ${labelFor(actionPlan.currentVersionStatus)}`
                  : "No Action Plan has been created."}
              </p>
              {actionPlan?.acceptedVersionNumber && (
                <p className="mt-1 text-xs text-emerald-700">
                  Version {actionPlan.acceptedVersionNumber} is the accepted
                  monitoring baseline.
                </p>
              )}
            </div>
            <Link
              className="inline-flex h-10 items-center gap-2 rounded-lg border border-sky-300 bg-white px-4 text-sm font-bold text-sky-800 hover:bg-sky-50"
              to={`/compliance-management/recommendations/${record.id}/action-plan`}
            >
              <ListChecks size={16} />
              {actionPlan?.hasActionPlan ? "Open workspace" : "Prepare plan"}
            </Link>
          </div>
        </div>
      )}
      {canViewValidation && (
        <div className="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div>
              <p className="text-xs font-bold uppercase tracking-wide text-emerald-700">
                Independent validation
              </p>
              <p className="mt-1 text-sm font-semibold text-slate-800">
                {record.validationSummary?.hasValidationReviews
                  ? `${record.validationSummary.latestValidationReviewId ? "Validation Review available" : "Validation Review"} · ${labelFor(record.validationSummary.latestValidationVersionStatus)}`
                  : "No Validation Review has been created."}
              </p>
              {record.validationSummary?.latestFinalizedConclusion === "IMPLEMENTED" && (
                <p className="mt-1 text-xs font-semibold text-emerald-800">
                  Independently validated as implemented — closure not yet completed.
                </p>
              )}
            </div>
            <Link
              className="inline-flex h-10 items-center gap-2 rounded-lg border border-emerald-300 bg-white px-4 text-sm font-bold text-emerald-800 hover:bg-emerald-50"
              to={`/compliance-management/recommendations/${record.id}/validations`}
            >
              <ClipboardCheck size={16} /> Open workspace
            </Link>
          </div>
        </div>
      )}
      {canViewExtension && (
        <div className="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div>
              <p className="text-xs font-bold uppercase tracking-wide text-amber-700">Target-date extensions</p>
              <p className="mt-1 text-sm font-semibold text-slate-800">
                {record.targetDateExtensionSummary?.requestCount
                  ? `${record.targetDateExtensionSummary.requestCount} request${record.targetDateExtensionSummary.requestCount === 1 ? "" : "s"} · ${record.targetDateExtensionSummary.openRequestCount || 0} unresolved`
                  : "No target-date extension requests have been created."}
              </p>
              <p className="mt-1 text-xs text-slate-600">Original target: {displayDate(record.targetDateExtensionSummary?.originalTargetDate || record.intake?.originalTargetDate)} · Current effective: {displayDate(record.targetDateExtensionSummary?.currentEffectiveTargetDate || record.effectiveTargetDate)}</p>
            </div>
            <Link className="inline-flex h-10 items-center gap-2 rounded-lg border border-amber-300 bg-white px-4 text-sm font-bold text-amber-800 hover:bg-amber-50" to={`/compliance-management/recommendations/${record.id}/extensions`}><CalendarClock size={16} /> Open workspace</Link>
          </div>
        </div>
      )}
      {canViewEscalation && (
        <div className="mt-4 rounded-xl border border-red-200 bg-red-50 p-4">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div>
              <p className="text-xs font-bold uppercase tracking-wide text-red-700">Escalation activity</p>
              <p className="mt-1 text-sm font-semibold text-slate-800">
                {record.escalationSummary?.hasEscalations
                  ? `${record.escalationSummary.activeEscalationId ? `Escalation #${record.escalationSummary.activeEscalationId}` : "Active escalation"} · ${labelFor(record.escalationSummary.activeOperationalStatus)}`
                  : "No formal escalation has been recorded."}
              </p>
              <p className="mt-1 text-xs text-slate-600">Escalation resolution never closes the recommendation or establishes implementation.</p>
            </div>
            <Link className="inline-flex h-10 items-center gap-2 rounded-lg border border-red-300 bg-white px-4 text-sm font-bold text-red-800 hover:bg-red-100" to={`/compliance-management/recommendations/${record.id}/escalations`}><ShieldCheck size={16} /> Open workspace</Link>
          </div>
        </div>
      )}
      {canViewClosure && (
        <div className="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div>
              <p className="text-xs font-bold uppercase tracking-wide text-emerald-700">Formal recommendation closure</p>
              <p className="mt-1 text-sm font-semibold text-slate-800">
                {record.closureSummary?.formallyClosed
                  ? "Formally closed"
                  : record.closureSummary?.activeRequestStatus
                    ? `Closure Request · ${labelFor(record.closureSummary.activeRequestStatus)}`
                    : record.status === "IMPLEMENTED"
                      ? "Eligible for formal closure when readiness passes"
                      : "Closure is not currently available"}
              </p>
              <p className="mt-1 text-xs text-slate-600">Implemented validation is distinct from a final Closure Decision.</p>
            </div>
            <Link className="inline-flex h-10 items-center gap-2 rounded-lg border border-emerald-300 bg-white px-4 text-sm font-bold text-emerald-800 hover:bg-emerald-50" to={`/compliance-management/recommendations/${record.id}/closure-requests`}><ShieldCheck size={16} /> Open closure workspace</Link>
          </div>
        </div>
      )}
      {canViewDisposition && (
        <div className={`mt-4 rounded-xl border p-4 ${record.dispositionSummary?.acceptedRisk ? "border-amber-200 bg-amber-50" : record.dispositionSummary?.noLongerApplicable ? "border-violet-200 bg-violet-50" : "border-sky-200 bg-sky-50"}`}>
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div>
              <p className="text-xs font-bold uppercase tracking-wide text-sky-700">Recommendation dispositions</p>
              <p className="mt-1 text-sm font-semibold text-slate-800">
                {record.dispositionSummary?.acceptedRisk ? "Accepted Risk" : record.dispositionSummary?.noLongerApplicable ? "No Longer Applicable" : record.dispositionSummary?.activeRequestStatus ? `Disposition Request · ${labelFor(record.dispositionSummary.activeRequestStatus)}` : "No disposition request has been created."}
              </p>
              <p className="mt-1 text-xs text-slate-600">Accepted Risk and No Longer Applicable are not implementation or ordinary closure.</p>
            </div>
            <Link className="inline-flex h-10 items-center gap-2 rounded-lg border border-sky-300 bg-white px-4 text-sm font-bold text-sky-800 hover:bg-sky-50" to={`/compliance-management/recommendations/${record.id}/dispositions`}><ShieldAlert size={16} /> Open workspace</Link>
          </div>
        </div>
      )}
    </div>
  );
}

function ClosureSummary({ record }) {
  const summary = record.closureSummary || {};
  if (summary.formallyClosed) {
    return <div><div className="rounded-xl border border-emerald-300 bg-emerald-50 p-4"><div className="flex items-start gap-3"><ShieldCheck className="text-emerald-700" /><div><h3 className="text-lg font-bold text-slate-800">This recommendation is formally closed</h3><p className="mt-1 text-sm leading-6 text-slate-600">The approved Closure Decision is immutable. Any reopening must use the separate controlled Reopening workspace.</p></div></div></div><div className="mt-5"><Link className="inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white" to={`/compliance-management/recommendations/${record.id}/reopening-requests`}><RotateCcw size={16} /> Open Reopening workspace</Link></div></div>;
  }
  return <div><div className={`rounded-xl border p-4 ${summary.formallyClosed ? "border-emerald-300 bg-emerald-50" : "border-sky-200 bg-sky-50"}`}><div className="flex items-start gap-3"><ShieldCheck className={summary.formallyClosed ? "text-emerald-700" : "text-sky-700"} /><div><h3 className="text-lg font-bold text-slate-800">{summary.formallyClosed ? "This recommendation is formally closed" : "Recommendation closure"}</h3><p className="mt-1 text-sm leading-6 text-slate-600">{summary.formallyClosed ? "The approved Closure Decision is immutable. Any reopening must use the separate controlled Reopening workspace." : "A finalized independent IMPLEMENTED validation is not itself formal closure."}</p></div></div></div><dl className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4"><ReadOnlyField label="Case status">{record.status}</ReadOnlyField><ReadOnlyField label="Active Closure Request">{summary.activeRequestId || "None"}</ReadOnlyField><ReadOnlyField label="Active request status">{summary.activeRequestStatus || "None"}</ReadOnlyField><ReadOnlyField label="Prior request count">{summary.priorRequestCount ?? 0}</ReadOnlyField><ReadOnlyField label="Closed date">{displayDate(summary.closedAt)}</ReadOnlyField><ReadOnlyField label="Closure Decision">{summary.closureDecisionId || "Not recorded"}</ReadOnlyField></dl><div className="mt-5"><Link className="inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white" to={`/compliance-management/recommendations/${record.id}/closure-requests`}><ShieldCheck size={16} /> View Closure Requests</Link></div></div>;
}

function DispositionSummary({ record }) {
  const summary = record.dispositionSummary || {};
  const type = summary.activeDispositionCode;
  const terminal = summary.acceptedRisk || summary.noLongerApplicable;
  if (summary.acceptedRisk) {
    return <div><div className="rounded-xl border border-amber-300 bg-amber-50 p-4"><div className="flex items-start gap-3"><ShieldAlert className="text-amber-700" /><div><h3 className="text-lg font-bold text-slate-800">Accepted Risk</h3><p className="mt-1 text-sm leading-6 text-slate-600">Residual risk was formally accepted through an authorized disposition decision. This does not represent implementation or ordinary recommendation closure.</p></div></div></div><div className="mt-5"><Link className="inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white" to={`/compliance-management/recommendations/${record.id}/dispositions`}><ShieldAlert size={16} /> Open Dispositions workspace</Link></div></div>;
  }
  return <div><div className={`rounded-xl border p-4 ${summary.acceptedRisk ? "border-amber-300 bg-amber-50" : summary.noLongerApplicable ? "border-violet-300 bg-violet-50" : "border-sky-200 bg-sky-50"}`}><div className="flex items-start gap-3"><ShieldAlert className={summary.acceptedRisk ? "text-amber-700" : summary.noLongerApplicable ? "text-violet-700" : "text-sky-700"} /><div><h3 className="text-lg font-bold text-slate-800">{summary.acceptedRisk ? "Accepted Risk" : summary.noLongerApplicable ? "No Longer Applicable" : "Controlled recommendation disposition"}</h3><p className="mt-1 text-sm leading-6 text-slate-600">{summary.acceptedRisk ? "Residual risk was formally accepted through an authorized disposition decision. This does not represent implementation or ordinary recommendation closure." : summary.noLongerApplicable ? "The recommendation was formally determined to be no longer applicable based on an authorized documented change in circumstances." : "Accepted Risk and No Longer Applicable remain distinct from implementation and ordinary closure."}</p></div></div></div><dl className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4"><ReadOnlyField label="Case status">{record.status}</ReadOnlyField><ReadOnlyField label="Active request">{summary.activeRequestId || "None"}</ReadOnlyField><ReadOnlyField label="Active request status">{summary.activeRequestStatus || "None"}</ReadOnlyField><ReadOnlyField label="Disposition type">{type || (terminal ? record.status : "None")}</ReadOnlyField><ReadOnlyField label="Prior request count">{summary.priorRequestCount ?? 0}</ReadOnlyField></dl><div className="mt-5"><Link className="inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white" to={`/compliance-management/recommendations/${record.id}/dispositions`}><ShieldAlert size={16} /> Open Dispositions workspace</Link></div></div>;
}

function SourceLineage({ record }) {
  const lineage = record.sourceLineage ?? {};
  const intake = record.intake ?? {};
  return (
    <div>
      <div className="flex items-start gap-3">
        <span className="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-slate-100 text-slate-700">
          <FileCheck2 size={19} />
        </span>
        <div>
          <h3 className="text-lg font-bold text-slate-800">
            Immutable AEMS source lineage
          </h3>
          <p className="mt-1 text-sm text-slate-500">
            Read-only identifiers preserved when the issued report recommendation
            transferred to CMS.
          </p>
        </div>
      </div>
      <dl className="mt-5 grid gap-3 sm:grid-cols-2">
        <ReadOnlyField label="AEMS engagement">
          {valueOrFallback(lineage.engagement?.code)} —{" "}
          {valueOrFallback(lineage.engagement?.title)}
        </ReadOnlyField>
        <ReadOnlyField label="Finding">
          {valueOrFallback(lineage.finding?.code)} —{" "}
          {valueOrFallback(lineage.finding?.title)}
        </ReadOnlyField>
        <ReadOnlyField label="Recommendation code">
          {valueOrFallback(lineage.recommendation?.code)}
        </ReadOnlyField>
        <ReadOnlyField label="Final report number">
          {valueOrFallback(lineage.report?.finalReportNumber)}
        </ReadOnlyField>
        <ReadOnlyField label="Report version">
          {lineage.report?.versionNumber
            ? `Version ${lineage.report.versionNumber} (ID ${lineage.report.versionId})`
            : "Not available"}
        </ReadOnlyField>
        <ReadOnlyField label="Report issued">
          {displayDate(lineage.report?.issuedAt, true)}
        </ReadOnlyField>
        <ReadOnlyField label="Transferred by">
          {intake.transferredBy?.name || "System"}
        </ReadOnlyField>
        <ReadOnlyField label="Transferred at">
          {displayDate(intake.transferredAt, true)}
        </ReadOnlyField>
        <ReadOnlyField label="Transfer key" mono>
          {valueOrFallback(intake.transferKey)}
        </ReadOnlyField>
        <ReadOnlyField label="Source schema version">
          {valueOrFallback(intake.sourceSchemaVersion)}
        </ReadOnlyField>
        <ReadOnlyField label="Report SHA-256 checksum" mono wide>
          {valueOrFallback(lineage.report?.checksumSha256)}
        </ReadOnlyField>
      </dl>
    </div>
  );
}
