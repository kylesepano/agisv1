import { useCallback, useEffect, useMemo, useState } from "react";
import {
  AlertTriangle,
  ArrowLeft,
  CalendarDays,
  CheckCircle2,
  FileCheck2,
  FilePenLine,
  History,
  ListChecks,
  LoaderCircle,
  Play,
  RefreshCw,
  RotateCcw,
  Save,
  Send,
  ShieldCheck,
} from "lucide-react";
import { Link, useNavigate, useParams } from "react-router";
import { useAuth } from "../../auth/auth-context";
import {
  CmsProgressStatusBadge,
  CmsStatusBadge,
} from "../../components/cms/CmsBadges";
import CmsProgressEvidencePanel from "../../components/cms/CmsProgressEvidencePanel";
import CmsProgressUpdateForm from "../../components/cms/CmsProgressUpdateForm";
import FormField from "../../components/ui/FormField";
import Modal from "../../components/ui/Modal";
import RegistryHeader from "../../components/ui/RegistryHeader";
import StatusBadge from "../../components/ui/StatusBadge";
import { hasPermission } from "../../config/navigation";
import { ApiError, cmsApi, documentApi } from "../../services/api";
import { useToast } from "../../ui/toast-context";

const textareaClass =
  "mt-1 min-h-24 w-full rounded-lg border border-slate-300 bg-white p-3 text-sm text-slate-800 outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100 disabled:bg-slate-100";

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
  return value === null || value === undefined || value === "" ? "Not available" : value;
}

function firstError(errors, key) {
  const value = errors?.[key];
  return Array.isArray(value) ? value[0] : value || "";
}

function createMilestoneEntry(milestone, index) {
  return {
    actionPlanMilestoneId: milestone.id,
    managementReportedStatusCode: "NOT_STARTED",
    managementReportedPercentage: 0,
    accomplishmentDescription: "",
    issuesAndConstraints: "",
    nextStep: "",
    forecastCompletionDate: "",
    noEvidenceExplanation: "",
    displayOrder: milestone.displayOrder ?? index + 1,
  };
}

function emptyForm({ baselineWeighted = false, milestones = [], reportingPeriodStart = "", reportingPeriodEnd = "" } = {}) {
  return {
    reportingPeriodStart,
    reportingPeriodEnd,
    accomplishmentSummary: "",
    managementReportedOverallPercentage: baselineWeighted ? null : "",
    issuesAndConstraints: "",
    correctiveActionsForDelays: "",
    nextSteps: "",
    forecastCompletionDate: "",
    managementDeclaration: "",
    generalEvidenceExplanation: "",
    milestoneProgress: milestones.map(createMilestoneEntry),
    baselineWeighted,
    systemCalculatedWeightedReportedPercentage: null,
  };
}

function formFromVersion(family, version) {
  return {
    reportingPeriodStart: family.reportingPeriodStart ?? "",
    reportingPeriodEnd: family.reportingPeriodEnd ?? "",
    accomplishmentSummary: version.accomplishmentSummary ?? "",
    managementReportedOverallPercentage: version.managementReportedOverallPercentage ?? "",
    issuesAndConstraints: version.issuesAndConstraints ?? "",
    correctiveActionsForDelays: version.correctiveActionsForDelays ?? "",
    nextSteps: version.nextSteps ?? "",
    forecastCompletionDate: version.forecastCompletionDate ?? "",
    managementDeclaration: version.managementDeclaration ?? "",
    generalEvidenceExplanation: version.generalEvidenceExplanation ?? "",
    milestoneProgress: (version.milestoneProgress ?? []).map((item) => ({
      id: item.id,
      actionPlanMilestoneId: item.actionPlanMilestoneId,
      milestoneSequence: item.milestoneSequence,
      milestoneSnapshot: item.milestoneSnapshot,
      managementReportedStatusCode: item.managementReportedStatusCode,
      managementReportedPercentage: item.managementReportedPercentage,
      accomplishmentDescription: item.accomplishmentDescription ?? "",
      issuesAndConstraints: item.issuesAndConstraints ?? "",
      nextStep: item.nextStep ?? "",
      forecastCompletionDate: item.forecastCompletionDate ?? "",
      noEvidenceExplanation: item.noEvidenceExplanation ?? "",
      displayOrder: item.displayOrder,
    })),
    baselineWeighted: Boolean(version.baselineWeighted),
    systemCalculatedWeightedReportedPercentage: version.systemCalculatedWeightedReportedPercentage,
  };
}

function Skeleton({ label = "Loading Progress Updates" }) {
  return (
    <div aria-label={label} className="grid gap-4">
      <div className="h-24 animate-pulse rounded-xl bg-slate-200" />
      <div className="h-44 animate-pulse rounded-xl bg-slate-200" />
      <div className="h-72 animate-pulse rounded-xl bg-slate-200" />
    </div>
  );
}

function ErrorState({ message, onRetry }) {
  return (
    <div className="rounded-2xl border border-red-200 bg-red-50 px-6 py-14 text-center">
      <AlertTriangle className="mx-auto text-red-600" size={36} />
      <h2 className="mt-3 text-xl font-bold text-slate-800">Progress Updates unavailable</h2>
      <p className="mx-auto mt-2 max-w-xl text-sm leading-6 text-slate-600">{message}</p>
      <button className="mt-5 inline-flex h-10 items-center gap-2 rounded-lg bg-red-700 px-4 text-sm font-bold text-white hover:bg-red-800" onClick={onRetry} type="button"><RefreshCw size={16} /> Retry</button>
    </div>
  );
}

function ContextPanel({ context, family, recommendation, baselineVersion, listMode = false }) {
  const acceptedVersion = family?.acceptedActionPlanVersion ?? baselineVersion ?? context?.acceptedActionPlanVersion;
  const currentAcceptedVersionId = recommendation?.actionPlanSummary?.acceptedVersionId;
  return (
    <section className="mb-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0">
          <p className="text-xs font-bold uppercase tracking-wide text-sky-700">{context?.cmsRecommendationCode || recommendation?.cmsRecommendationCode || "CMS recommendation"}</p>
          <p className="mt-2 max-w-4xl whitespace-pre-wrap text-sm leading-6 text-slate-800">{context?.recommendation || recommendation?.recommendation || "Recommendation wording is unavailable."}</p>
        </div>
        <div className="flex flex-wrap gap-2">
          <CmsStatusBadge status={context?.status || recommendation?.status} />
          <StatusBadge tone="warning"><ShieldCheck className="mr-1" size={13} /> Management-reported</StatusBadge>
        </div>
      </div>
      <dl className="mt-5 grid gap-4 text-sm sm:grid-cols-2 xl:grid-cols-5">
        <ContextDatum label="Responsible office" value={context?.responsibleOffice?.name || recommendation?.responsibleOffice?.name || "Not assigned"} />
        <ContextDatum label="Effective target date" value={displayDate(context?.effectiveTargetDate || recommendation?.effectiveTargetDate)} />
        <ContextDatum label="Compliance Monitor" value={context?.currentMonitor?.name || recommendation?.currentMonitor?.user?.name || "Unassigned"} />
        <ContextDatum label="Accepted baseline" value={acceptedVersion ? `Version ${acceptedVersion.versionNumber}` : context?.acceptedActionPlanVersionId ? `Version ID ${context.acceptedActionPlanVersionId}` : "None"} />
        {!listMode && <ContextDatum label="Reporting period" value={`${displayDate(family?.reportingPeriodStart)} – ${displayDate(family?.reportingPeriodEnd)}`} />}
      </dl>
      {acceptedVersion?.id && currentAcceptedVersionId && acceptedVersion.id !== currentAcceptedVersionId && (
        <div className="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs leading-5 text-amber-900">This historical Progress Update uses an older accepted Action Plan baseline. Historical records remain pinned to that baseline and are not remapped.</div>
      )}
    </section>
  );
}

function ContextDatum({ label, value }) {
  return <div><dt className="text-xs font-bold uppercase tracking-wide text-slate-500">{label}</dt><dd className="mt-1 break-words text-slate-800">{valueOrFallback(value)}</dd></div>;
}

function ListCard({ update, onOpen }) {
  const version = update.currentVersion;
  const recorded = update.recordedVersion;
  return (
    <button className="w-full rounded-xl border border-slate-200 bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-sky-300 hover:shadow-md focus-visible:outline-2 focus-visible:outline-sky-600" onClick={onOpen} type="button">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p className="text-xs font-bold uppercase tracking-wide text-sky-700">{update.displayCode}</p>
          <h3 className="mt-1 text-base font-bold text-slate-800">Reporting period {displayDate(update.reportingPeriodStart)} – {displayDate(update.reportingPeriodEnd)}</h3>
        </div>
        {version && <CmsProgressStatusBadge status={version.status} />}
      </div>
      <dl className="mt-4 grid gap-3 text-xs text-slate-600 sm:grid-cols-2 xl:grid-cols-4">
        <div><dt className="font-bold text-slate-500">Current version</dt><dd className="mt-1">Version {version?.versionNumber ?? "–"}</dd></div>
        <div><dt className="font-bold text-slate-500">Accepted baseline</dt><dd className="mt-1">Version {update.acceptedActionPlanVersion?.versionNumber ?? "–"}</dd></div>
        <div><dt className="font-bold text-slate-500">Reported progress</dt><dd className="mt-1">{version?.managementReportedOverallPercentage ?? version?.systemCalculatedWeightedReportedPercentage ?? "Not reported"}%</dd></div>
        <div><dt className="font-bold text-slate-500">Evidence</dt><dd className="mt-1">{version?.evidence?.length ?? 0} linked document{version?.evidence?.length === 1 ? "" : "s"}</dd></div>
      </dl>
      <div className="mt-4 flex flex-wrap items-center gap-2 text-xs">
        {recorded && <StatusBadge tone="success">Recorded version {recorded.versionNumber}</StatusBadge>}
        {version?.reportedCompleteAwaitingValidation && <StatusBadge tone="warning">Reported complete · awaiting independent validation</StatusBadge>}
        {version?.isSuperseded && <StatusBadge tone="warning">Superseded recorded version</StatusBadge>}
      </div>
    </button>
  );
}

function ReadOnlyField({ label, value, wide = false }) {
  return <div className={`rounded-lg border border-slate-200 bg-slate-50 p-3 ${wide ? "sm:col-span-2" : ""}`}><dt className="text-xs font-bold uppercase tracking-wide text-slate-500">{label}</dt><dd className="mt-1 whitespace-pre-wrap break-words text-sm leading-6 text-slate-800">{valueOrFallback(value)}</dd></div>;
}

function OverviewPanel({ version }) {
  const reported = version?.managementReportedOverallPercentage;
  const weighted = version?.systemCalculatedWeightedReportedPercentage;
  return (
    <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
      <div className="flex flex-wrap items-start justify-between gap-3"><div><h3 className="text-lg font-bold text-slate-800">Management-reported overview</h3><p className="mt-1 text-sm text-slate-500">A recorded submission is retained for monitoring and has not been independently validated.</p></div>{version?.reportedCompleteAwaitingValidation && <StatusBadge tone="warning">Management reports completion · awaiting independent validation</StatusBadge>}</div>
      <dl className="mt-5 grid gap-3 sm:grid-cols-2">
        <ReadOnlyField label="Accomplishment summary" value={version?.accomplishmentSummary} wide />
        <ReadOnlyField label="Overall management-reported percentage" value={reported === null || reported === undefined ? "Not reported" : `${reported}%`} />
        <ReadOnlyField label="System-calculated weighted reported percentage" value={weighted === null || weighted === undefined ? "Not applicable" : `${weighted}%`} />
        <ReadOnlyField label="Baseline weighting" value={version?.baselineWeighted ? "Weighted Action Plan baseline" : "Unweighted Action Plan baseline"} />
        <ReadOnlyField label="Issues and constraints" value={version?.issuesAndConstraints} />
        <ReadOnlyField label="Corrective actions for delays" value={version?.correctiveActionsForDelays} />
        <ReadOnlyField label="Next steps" value={version?.nextSteps} />
        <ReadOnlyField label="Forecast completion date" value={displayDate(version?.forecastCompletionDate)} />
        <ReadOnlyField label="Management declaration" value={version?.managementDeclaration} wide />
        <ReadOnlyField label="General evidence explanation" value={version?.generalEvidenceExplanation} wide />
      </dl>
      <dl className="mt-5 grid gap-3 border-t border-slate-200 pt-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
        <ReadOnlyField label="Prepared by" value={version?.preparedBy?.name} />
        <ReadOnlyField label="Submitted" value={version?.submittedAt ? `${version.submittedBy?.name || "User"} · ${displayDate(version.submittedAt, true)}` : "Not submitted"} />
        <ReadOnlyField label="Review started" value={version?.reviewStartedAt ? `${version.reviewStartedBy?.name || "Reviewer"} · ${displayDate(version.reviewStartedAt, true)}` : "Not started"} />
        <ReadOnlyField label="Recorded" value={version?.recordedAt ? `${version.recordedBy?.name || "Reviewer"} · ${displayDate(version.recordedAt, true)}` : "Not recorded"} />
        {version?.returnReason && <ReadOnlyField label="Return reason" value={version.returnReason} wide />}
        {version?.recordingComment && <ReadOnlyField label="Recording comment" value={version.recordingComment} wide />}
      </dl>
    </section>
  );
}

function MilestoneReadOnly({ item }) {
  const snapshot = item.milestoneSnapshot ?? {};
  const status = {
    NOT_STARTED: "Not started",
    IN_PROGRESS: "In progress",
    REPORTED_COMPLETED: "Reported completed",
    DELAYED: "Delayed",
    ON_HOLD: "On hold",
  }[item.managementReportedStatusCode] ?? item.managementReportedStatusCode;
  return <article className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><div className="flex flex-wrap items-start justify-between gap-3"><div><p className="text-xs font-bold uppercase tracking-wide text-sky-700">Milestone {item.milestoneSequence}</p><h4 className="mt-1 font-bold text-slate-800">{snapshot.title || "Milestone"}</h4><p className="mt-1 whitespace-pre-wrap text-sm leading-6 text-slate-600">{snapshot.description || snapshot.expectedOutput || "No milestone description available."}</p></div><div className="flex flex-wrap gap-2"><StatusBadge tone={item.managementReportedStatusCode === "REPORTED_COMPLETED" ? "success" : "info"}>{status}</StatusBadge><StatusBadge tone="sky">{item.managementReportedPercentage}% reported</StatusBadge></div></div><dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2"><ReadOnlyField label="Accomplishment" value={item.accomplishmentDescription} wide /><ReadOnlyField label="Issues and constraints" value={item.issuesAndConstraints} /><ReadOnlyField label="Next step" value={item.nextStep} /><ReadOnlyField label="Forecast completion" value={displayDate(item.forecastCompletionDate)} /><ReadOnlyField label="No-evidence explanation" value={item.noEvidenceExplanation} /></dl></article>;
}

function VersionHistory({ versions = [], selectedId, onSelect, family }) {
  return <aside className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><div className="flex items-center gap-2"><History className="text-sky-700" size={18} /><h3 className="font-bold text-slate-800">Versions & history</h3></div><p className="mt-1 text-xs leading-5 text-slate-500">Historical versions remain immutable. A prior recorded version remains authoritative until a correction is recorded.</p><div className="mt-4 grid gap-2">{versions.length === 0 ? <p className="text-sm text-slate-500">No version history available.</p> : versions.map((version) => <button className={`rounded-lg border p-3 text-left transition ${selectedId === version.id ? "border-sky-400 bg-sky-50" : "border-slate-200 hover:border-sky-300 hover:bg-slate-50"}`} key={version.id} onClick={() => onSelect(version.id)} type="button"><div className="flex flex-wrap items-center justify-between gap-2"><strong className="text-sm text-slate-800">Version {version.versionNumber}</strong><CmsProgressStatusBadge status={version.status} /></div><p className="mt-2 text-xs text-slate-500">Created {displayDate(version.createdAt, true)}</p><div className="mt-2 flex flex-wrap gap-1">{version.isCurrent && <StatusBadge tone="info">Current</StatusBadge>}{version.isRecordedCurrent && <StatusBadge tone="success">Recorded current</StatusBadge>}{version.isSuperseded && <StatusBadge tone="warning">Superseded</StatusBadge>}</div></button>)}</div>{family?.recordedVersionId && <p className="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-xs leading-5 text-emerald-900">Recorded version pointer: Version {versions.find((version) => version.id === family.recordedVersionId)?.versionNumber ?? "unavailable"}.</p>}</aside>;
}

function WorkflowDialog({ action, version, busy, errors, comment, confirmed, setComment, setConfirmed, onClose, onConfirm }) {
  if (!action) return null;
  const copy = {
    submit: { title: "Submit Progress Update", confirm: "Submit Progress Update", description: "Submission makes this version and its evidence links immutable. It records management-reported information; it does not independently validate implementation." },
    "start-review": { title: "Start Progress Update review", confirm: "Start review", description: "Review confirms completeness of the management submission. It is not an implementation validation." },
    return: { title: "Return Progress Update", confirm: "Return Progress Update", description: "The returned version remains immutable. Corrections require a new draft revision." },
    record: { title: "Record Progress Update", confirm: "Record for monitoring", description: "Recording confirms that the management submission was reviewed for completeness. It does not independently validate implementation." },
    revise: { title: "Create Progress Update revision", confirm: "Create revision", description: "The source version remains immutable. A prior recorded version remains authoritative until this revision is recorded." },
  }[action];
  const field = action === "return" ? "returnReason" : action === "record" ? "recordingComment" : action === "revise" ? "revisionReason" : action === "start-review" ? "reviewComment" : null;
  const required = ["return", "record", "revise"].includes(action);
  return <Modal footer={<><button className="h-10 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700" disabled={busy} onClick={onClose} type="button">Cancel</button><button className="h-10 rounded-lg bg-sky-700 px-5 text-sm font-bold text-white disabled:opacity-60" disabled={busy} onClick={onConfirm} type="button">{busy ? "Please wait..." : copy.confirm}</button></>} onClose={() => !busy && onClose()} open size="md" title={copy.title}>
    <div className="grid gap-4"><p className="text-sm leading-6 text-slate-600">{copy.description}</p>{action === "submit" && <div className="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">Version {version?.versionNumber} · {version?.milestoneProgress?.length ?? 0} milestones · {version?.evidence?.length ?? 0} evidence links</div>}{field && <FormField error={firstError(errors, field)} htmlFor="progress-workflow-comment" label={action === "return" ? "Return reason" : action === "record" ? "Recording comment" : action === "revise" ? "Revision reason" : "Review comment"} required={required}><textarea className={textareaClass} id="progress-workflow-comment" maxLength={5000} onChange={(event) => setComment(event.target.value)} value={comment} /></FormField>}{["submit", "record"].includes(action) && <label className="flex items-start gap-2 text-sm text-slate-700"><input checked={confirmed} className="mt-1" onChange={(event) => setConfirmed(event.target.checked)} type="checkbox" />I understand this action preserves management-reported information and does not establish an independent implementation conclusion.</label>}{errors?.confirmation && <p className="text-xs font-medium text-red-600">{firstError(errors, "confirmation")}</p>}</div>
  </Modal>;
}

export default function CmsProgressUpdatesPage() {
  const { recommendationId, progressUpdateId } = useParams();
  const navigate = useNavigate();
  const { user } = useAuth();
  const toast = useToast();
  const detailMode = Boolean(progressUpdateId);
  const [recommendation, setRecommendation] = useState(null);
  const [listData, setListData] = useState(null);
  const [family, setFamily] = useState(null);
  const [plan, setPlan] = useState(null);
  const [selectedVersionId, setSelectedVersionId] = useState(null);
  const [form, setForm] = useState(null);
  const [creating, setCreating] = useState(false);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [conflict, setConflict] = useState("");
  const [uncertain, setUncertain] = useState("");
  const [formErrors, setFormErrors] = useState({});
  const [selectedTab, setSelectedTab] = useState("overview");
  const [workflowAction, setWorkflowAction] = useState("");
  const [workflowComment, setWorkflowComment] = useState("");
  const [workflowConfirmed, setWorkflowConfirmed] = useState(false);
  const [workflowErrors, setWorkflowErrors] = useState({});
  const [confidentialityLevels, setConfidentialityLevels] = useState([]);

  const currentVersion = family?.currentVersion;
  const selectedVersion = useMemo(() => family?.versions?.find((version) => version.id === selectedVersionId) ?? (selectedVersionId === currentVersion?.id ? currentVersion : family?.recordedVersion) ?? currentVersion, [currentVersion, family, selectedVersionId]);
  const context = family?.caseContext ?? listData?.caseContext ?? recommendation ?? {};
  const canCreate = hasPermission(user, "cms.progress.create") && listData?.permittedActions?.includes("create");
  const actions = selectedVersion?.availableActions ?? [];
  const canEdit = actions.includes("update") && hasPermission(user, "cms.progress.update") && selectedVersion?.isCurrent;
  const canSubmit = actions.includes("submit") && hasPermission(user, "cms.progress.submit") && selectedVersion?.isCurrent;
  const canStartReview = actions.includes("start-review") && hasPermission(user, "cms.progress.review");
  const canReturn = actions.includes("return") && hasPermission(user, "cms.progress.return");
  const canRecord = actions.includes("record") && hasPermission(user, "cms.progress.record");
  const canRevise = actions.includes("revise") && hasPermission(user, "cms.progress.revise");

  const loadList = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const [progress, recommendationRecord] = await Promise.all([
        cmsApi.getProgressUpdates(recommendationId),
        cmsApi.getRecommendation(recommendationId),
      ]);
      setListData(progress ?? { progressUpdates: [], permittedActions: [] });
      setRecommendation(recommendationRecord);
      try {
        setPlan(await cmsApi.getActionPlanForRecommendation(recommendationId));
      } catch (planError) {
        if (!(planError instanceof ApiError && planError.status === 404)) throw planError;
        setPlan(null);
      }
    } catch (requestError) {
      setError(requestError.status === 404 ? "This recommendation or its Progress Updates are unavailable within your authorized scope." : requestError.message || "The Progress Updates could not be loaded.");
    } finally {
      setLoading(false);
    }
  }, [recommendationId]);

  const loadDetail = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const result = await cmsApi.getProgressUpdate(progressUpdateId);
      setFamily(result);
      const version = result?.currentVersion ?? result?.recordedVersion ?? result?.versions?.[0];
      setSelectedVersionId(version?.id ?? null);
      if (version) setForm(formFromVersion(result, version));
      try {
        const documents = await documentApi.list();
        setConfidentialityLevels(documents.confidentialityLevels);
      } catch {
        setConfidentialityLevels([]);
      }
    } catch (requestError) {
      setError(requestError.status === 404 ? "This Progress Update is unavailable or outside your authorized scope." : requestError.message || "The Progress Update could not be loaded.");
    } finally {
      setLoading(false);
    }
  }, [progressUpdateId]);

  useEffect(() => {
    const timer = window.setTimeout(() => {
      void (detailMode ? loadDetail() : loadList());
    }, 0);
    return () => window.clearTimeout(timer);
  }, [detailMode, loadDetail, loadList]);

  function beginCreate() {
    const milestones = plan?.acceptedVersion?.milestones ?? [];
    if (!milestones.length) {
      toast.warning("An accepted Action Plan baseline with milestones is required before progress reporting can begin.");
      return;
    }
    setForm(emptyForm({ baselineWeighted: milestones.every((milestone) => milestone.weightPercentage !== null && milestone.weightPercentage !== undefined), milestones }));
    setFormErrors({});
    setConflict("");
    setCreating(true);
  }

  function leaveCreate() {
    if (form && (form.accomplishmentSummary || form.milestoneProgress?.some((entry) => entry.accomplishmentDescription))) {
      if (!window.confirm("Discard this unsaved Progress Update draft?")) return;
    }
    setCreating(false);
    setForm(null);
  }

  async function saveCreate() {
    if (!form || saving) return;
    setSaving(true); setFormErrors({}); setConflict(""); setUncertain("");
    try {
      const created = await cmsApi.createProgressUpdate(recommendationId, { ...form, lockVersion: recommendation?.lockVersion, managementReportedOverallPercentage: form.baselineWeighted ? null : form.managementReportedOverallPercentage });
      toast.success("Progress Update draft created.");
      navigate(`/compliance-management/recommendations/${recommendationId}/progress-updates/${created.id}`);
    } catch (requestError) {
      setFormErrors(requestError.errors ?? {});
      if (requestError instanceof ApiError && requestError.status === 409) setConflict("The recommendation changed while the draft was being created. Reload the latest recommendation before retrying.");
      if (requestError.status === 0) setUncertain("The create result was uncertain. Reload the recommendation before retrying.");
      toast.error(requestError.message || "The Progress Update draft could not be created.");
    } finally { setSaving(false); }
  }

  async function saveDraft() {
    if (!family || !selectedVersion || !form || saving) return;
    setSaving(true); setFormErrors({}); setConflict(""); setUncertain("");
    try {
      const saved = await cmsApi.updateProgressUpdate(family.id, selectedVersion.id, { ...form, lockVersion: selectedVersion.lockVersion, managementReportedOverallPercentage: form.baselineWeighted ? null : form.managementReportedOverallPercentage });
      setFamily(saved); setSelectedVersionId(saved.currentVersion?.id ?? selectedVersion.id); setForm(formFromVersion(saved, saved.currentVersion ?? selectedVersion));
      toast.success("Progress Update draft saved.");
    } catch (requestError) {
      setFormErrors(requestError.errors ?? {});
      if (requestError instanceof ApiError && (requestError.status === 409 || requestError.errors?.lockVersion)) setConflict("Another user or process updated this Progress Update. Your local draft remains here; reload latest before retrying.");
      if (requestError.status === 0) { setUncertain("The save result was uncertain. Reload latest authoritative data before retrying."); await loadDetail(); }
      toast.error(requestError.message || "The Progress Update draft could not be saved.");
    } finally { setSaving(false); }
  }

  function selectVersion(id) {
    const version = family?.versions?.find((item) => item.id === id);
    if (!version) return;
    setSelectedVersionId(id); setSelectedTab("overview"); setConflict(""); setFormErrors({});
    setForm(formFromVersion(family, version));
  }

  function openWorkflow(action) {
    setWorkflowAction(action); setWorkflowComment(""); setWorkflowConfirmed(false); setWorkflowErrors({});
  }

  async function confirmWorkflow() {
    if (!family || !selectedVersion || saving) return;
    const localErrors = {};
    if (workflowAction === "return" && !workflowComment.trim()) localErrors.returnReason = ["Return instructions are required."];
    if (workflowAction === "record" && !workflowComment.trim()) localErrors.recordingComment = ["A recording comment is required."];
    if (workflowAction === "revise" && !workflowComment.trim()) localErrors.revisionReason = ["A revision reason is required."];
    if (["submit", "record"].includes(workflowAction) && !workflowConfirmed) localErrors.confirmation = ["Confirm that this remains management-reported and unvalidated."];
    if (Object.keys(localErrors).length) { setWorkflowErrors(localErrors); return; }
    setSaving(true); setWorkflowErrors({});
    try {
      const payload = { lockVersion: selectedVersion.lockVersion };
      if (workflowAction === "submit") { payload.confirmation = true; await cmsApi.submitProgressUpdate(family.id, selectedVersion.id, payload); }
      if (workflowAction === "start-review") { payload.reviewComment = workflowComment.trim() || null; await cmsApi.startProgressReview(family.id, selectedVersion.id, payload); }
      if (workflowAction === "return") { payload.returnReason = workflowComment.trim(); await cmsApi.returnProgressUpdate(family.id, selectedVersion.id, payload); }
      if (workflowAction === "record") { payload.recordingComment = workflowComment.trim(); payload.confirmation = true; await cmsApi.recordProgressUpdate(family.id, selectedVersion.id, payload); }
      if (workflowAction === "revise") { payload.revisionReason = workflowComment.trim(); const revised = await cmsApi.createProgressRevision(family.id, selectedVersion.id, payload); setFamily(revised); setSelectedVersionId(revised.currentVersion?.id); setForm(formFromVersion(revised, revised.currentVersion)); setWorkflowAction(""); toast.success("Progress Update revision created as a new draft."); return; }
      setWorkflowAction(""); toast.success(workflowAction === "record" ? "Progress Update recorded for monitoring; not independently validated." : `Progress Update ${workflowAction === "start-review" ? "review started" : workflowAction === "return" ? "returned" : "submitted"}.`); await loadDetail();
    } catch (requestError) {
      setWorkflowErrors(requestError.errors ?? {});
      if (requestError instanceof ApiError && (requestError.status === 409 || requestError.errors?.lockVersion)) setConflict("The Progress Update changed while this action was open. No retry was sent; reload latest authoritative data.");
      if (requestError.status === 0) { setUncertain("The action result was uncertain. Reload latest authoritative data before retrying."); await loadDetail(); }
      toast.error(requestError.message || "The Progress Update action could not be completed.");
    } finally { setSaving(false); }
  }

  if (loading && (!detailMode ? !listData : !family)) return <Skeleton />;
  if (error && (!detailMode ? !listData : !family)) return <ErrorState message={error} onRetry={detailMode ? loadDetail : loadList} />;

  if (!detailMode) {
    const updates = listData?.progressUpdates ?? [];
    const listContext = { ...(listData?.caseContext ?? recommendation ?? {}), acceptedActionPlanVersion: plan?.acceptedVersion };
    return <div className="min-w-0"><RegistryHeader actions={<div className="flex flex-wrap gap-2"><Link className="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700 hover:bg-slate-50" to={`/compliance-management/recommendations/${recommendationId}`}><ArrowLeft size={16} /> Recommendation</Link><button aria-label="Refresh Progress Updates" className="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700 hover:bg-slate-50 disabled:opacity-60" disabled={loading} onClick={loadList} type="button"><RefreshCw className={loading ? "animate-spin" : ""} size={16} /> Refresh</button>{canCreate && !creating && <button className="inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white hover:bg-sky-800" onClick={beginCreate} type="button"><FilePenLine size={16} /> Create Progress Update</button>}</div>} description="Record management-reported corrective-action progress against the accepted Action Plan baseline." icon={FileCheck2} title="Progress Updates" />{error && <div className="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">Refresh failed. Showing the last successfully loaded workspace.</div>}<ContextPanel context={listContext} recommendation={recommendation} listMode />{creating ? <div><div className="mb-4 flex flex-wrap items-center justify-between gap-3"><div><h2 className="text-lg font-bold text-slate-800">Create management progress draft</h2><p className="text-sm text-slate-500">The server-selected accepted Action Plan baseline is displayed in the milestone editor.</p></div><button className="inline-flex h-9 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-700" onClick={leaveCreate} type="button"><ArrowLeft size={14} /> Back to list</button></div><CmsProgressUpdateForm busy={saving} errors={formErrors} form={form} isCreate milestones={plan?.acceptedVersion?.milestones ?? []} onCancel={leaveCreate} onSave={saveCreate} setForm={setForm} /></div> : !listData?.caseContext?.acceptedActionPlanVersionId && !plan?.acceptedVersion ? <section className="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center shadow-sm"><ListChecks className="mx-auto text-slate-300" size={42} /><h2 className="mt-3 text-lg font-bold text-slate-800">No accepted Action Plan baseline</h2><p className="mx-auto mt-2 max-w-xl text-sm leading-6 text-slate-500">Progress reporting becomes available only after an Action Plan Version is accepted as the monitoring baseline.</p><Link className="mt-5 inline-flex h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700" to={`/compliance-management/recommendations/${recommendationId}/action-plan`}><ListChecks size={16} /> Open Action Plan</Link></section> : updates.length === 0 ? <section className="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center shadow-sm"><CalendarDays className="mx-auto text-slate-300" size={42} /><h2 className="mt-3 text-lg font-bold text-slate-800">No Progress Updates yet</h2><p className="mx-auto mt-2 max-w-xl text-sm leading-6 text-slate-500">No management-reported progress has been recorded for this recommendation within your authorized scope.</p>{canCreate ? <button className="mt-5 inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white" onClick={beginCreate} type="button"><FilePenLine size={16} /> Create Progress Update</button> : <p className="mt-4 text-xs text-slate-500">You may view this workspace, but your current authority does not permit creating a management report.</p>}</section> : <div className="grid gap-3">{updates.map((update) => <ListCard key={update.id} onOpen={() => navigate(`/compliance-management/recommendations/${recommendationId}/progress-updates/${update.id}`)} update={update} />)}</div>}</div>;
  }

  if (error && family) return <div className="min-w-0"><ErrorState message={error} onRetry={loadDetail} /></div>;
  if (!family || !selectedVersion) return <ErrorState message="The Progress Update version is unavailable." onRetry={loadDetail} />;

  const baselineVersionId = family.acceptedActionPlanVersionId;
  return <div className="min-w-0"><RegistryHeader actions={<div className="flex flex-wrap gap-2"><button className="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700" onClick={() => navigate(`/compliance-management/recommendations/${recommendationId}/progress-updates`)} type="button"><ArrowLeft size={16} /> Progress Updates</button><button aria-label="Refresh Progress Update" className="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700 disabled:opacity-60" disabled={loading || saving} onClick={loadDetail} type="button"><RefreshCw className={loading ? "animate-spin" : ""} size={16} /> Refresh</button></div>} description="Review management-reported progress, supporting evidence, completeness workflow, and immutable version history." icon={FileCheck2} title={family.displayCode || "Progress Update"} />{(conflict || uncertain || (error && family)) && <div className="mb-4 grid gap-2">{conflict && <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900"><span>{conflict}</span><button className="inline-flex h-9 items-center gap-2 rounded-lg bg-amber-700 px-3 text-xs font-bold text-white" onClick={loadDetail} type="button"><RefreshCw size={14} /> Reload latest</button></div>}{uncertain && <div className="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">{uncertain}</div>}{error && <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{error}</div>}</div>}<ContextPanel context={context} family={family} recommendation={recommendation} /><section className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><div><div className="flex flex-wrap items-center gap-2"><strong className="text-slate-800">Version {selectedVersion.versionNumber}</strong><CmsProgressStatusBadge status={selectedVersion.status} />{selectedVersion.isCurrent && <StatusBadge tone="info">Current version</StatusBadge>}{selectedVersion.isRecordedCurrent && <StatusBadge tone="success">Current recorded version</StatusBadge>}{selectedVersion.isSuperseded && <StatusBadge tone="warning">Superseded recorded version</StatusBadge>}</div><p className="mt-2 text-xs leading-5 text-slate-500">{selectedVersion.statusMeaning || "Management-reported information remains distinct from independent validation."}</p></div><div className="flex flex-wrap gap-2">{canEdit && <button className="inline-flex h-10 items-center gap-2 rounded-lg border border-sky-300 bg-sky-50 px-4 text-sm font-bold text-sky-800" onClick={() => setSelectedTab("overview")} type="button"><Save size={16} /> Edit draft</button>}{canSubmit && <button className="inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white disabled:opacity-60" disabled={saving} onClick={() => openWorkflow("submit")} type="button"><Send size={16} /> Submit</button>}{canStartReview && <button className="inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white disabled:opacity-60" disabled={saving} onClick={() => openWorkflow("start-review")} type="button"><Play size={16} /> Start review</button>}{canReturn && <button className="inline-flex h-10 items-center gap-2 rounded-lg border border-amber-300 bg-white px-4 text-sm font-bold text-amber-800 disabled:opacity-60" disabled={saving} onClick={() => openWorkflow("return")} type="button"><RotateCcw size={16} /> Return</button>}{canRecord && <button className="inline-flex h-10 items-center gap-2 rounded-lg bg-emerald-700 px-4 py-2 text-sm font-bold text-white disabled:opacity-60" disabled={saving} onClick={() => openWorkflow("record")} type="button"><CheckCircle2 size={16} /> Record for monitoring</button>}{canRevise && <button className="inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white disabled:opacity-60" disabled={saving} onClick={() => openWorkflow("revise")} type="button"><FilePenLine size={16} /> Create revision</button>}{saving && <span className="inline-flex items-center gap-2 text-sm font-semibold text-slate-500"><LoaderCircle className="animate-spin" size={16} /> Working...</span>}</div></section><div className="mb-4 flex overflow-x-auto rounded-xl border border-slate-200 bg-white p-1 shadow-sm" role="tablist">{[["overview", "Overview", ShieldCheck], ["milestones", "Milestone Progress", ListChecks], ["evidence", "Evidence", FileCheck2], ["history", "Versions & History", History]].map(([key, label, Icon]) => <button aria-selected={selectedTab === key} className={`inline-flex min-w-max flex-1 items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-bold transition focus-visible:outline-2 focus-visible:outline-sky-600 ${selectedTab === key ? "bg-sky-700 text-white" : "text-slate-600 hover:bg-slate-50"}`} key={key} onClick={() => setSelectedTab(key)} role="tab" type="button"><Icon size={16} /> {label}</button>)}</div><div className="grid items-start gap-4 xl:grid-cols-[minmax(0,1fr)_20rem]"><main className="min-w-0">{selectedTab === "overview" && canEdit ? <CmsProgressUpdateForm busy={saving} errors={formErrors} form={form} onSave={saveDraft} setForm={setForm} /> : selectedTab === "overview" ? <OverviewPanel version={selectedVersion} /> : selectedTab === "milestones" ? <section className="grid gap-4">{(selectedVersion.milestoneProgress ?? []).map((item) => <MilestoneReadOnly item={item} key={item.id} />)}</section> : selectedTab === "evidence" ? <CmsProgressEvidencePanel confidentialityLevels={confidentialityLevels} milestoneProgress={selectedVersion.milestoneProgress ?? []} onRefresh={loadDetail} updateId={family.id} user={user} version={selectedVersion} /> : <VersionHistory family={family} onSelect={selectVersion} selectedId={selectedVersion.id} versions={family.versions ?? []} />}</main><aside className="min-w-0 xl:sticky xl:top-20"><VersionHistory family={family} onSelect={selectVersion} selectedId={selectedVersion.id} versions={family.versions ?? []} /><div className="mt-4 rounded-xl border border-sky-200 bg-sky-50 p-4 text-xs leading-5 text-sky-900"><strong>Baseline reference</strong><p className="mt-1">Accepted Action Plan Version ID: {baselineVersionId ?? "Not available"}</p><p className="mt-2">This Progress Update remains linked to that exact accepted baseline.</p></div></aside></div><WorkflowDialog action={workflowAction} busy={saving} comment={workflowComment} confirmed={workflowConfirmed} errors={workflowErrors} onClose={() => !saving && setWorkflowAction("")} onConfirm={confirmWorkflow} setComment={setWorkflowComment} setConfirmed={setWorkflowConfirmed} version={selectedVersion} /></div>;
}
