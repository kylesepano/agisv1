import { useCallback, useEffect, useMemo, useState } from "react";
import {
  AlertTriangle,
  ArrowRight,
  BadgeCheck,
  Check,
  CheckCircle2,
  ClipboardCheck,
  Clock3,
  FileCheck2,
  FileClock,
  Files,
  GitCompareArrows,
  History,
  Link2,
  LockKeyhole,
  Plus,
  RefreshCw,
  Send,
  ShieldAlert,
  ShieldCheck,
  Upload,
} from "lucide-react";
import { Link, useSearchParams } from "react-router";
import { useAuth } from "../../auth/auth-context";
import AemsEngagementWorkspaceNav from "../../components/aems/AemsEngagementWorkspaceNav";
import AemsWorkspaceLockNotice from "../../components/aems/AemsWorkspaceLockNotice";
import { getAemsWorkspaceGate } from "../../components/aems/aemsPhaseGates";
import Modal from "../../components/ui/Modal";
import RegistryHeader from "../../components/ui/RegistryHeader";
import SearchableSelect from "../../components/ui/SearchableSelect";
import StatusBadge from "../../components/ui/StatusBadge";
import SummaryCard from "../../components/ui/SummaryCard";
import { hasPermission } from "../../config/navigation";
import {
  aemsEngagementApi,
  aemsEvidenceRequestApi,
  aemsWorkingPaperApi,
  ApiError,
} from "../../services/api";
import { useToast } from "../../ui/toast-context";

const inputClass =
  "h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100";
const textAreaClass = `${inputClass} min-h-24 resize-y py-2.5`;
const buttonPrimary =
  "inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white transition hover:bg-sky-800 disabled:cursor-not-allowed disabled:opacity-50";
const buttonSecondary =
  "inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700 transition hover:border-sky-300 hover:bg-sky-50 disabled:cursor-not-allowed disabled:opacity-50";
const contextLink =
  "inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-sky-700 transition hover:border-sky-300 hover:bg-sky-50";
const ratingOptions = ["", "YES", "NO", "PARTIAL", "HIGH", "MEDIUM", "LOW", "NOT_ASSESSED"];
const requestStages = [
  "DRAFT",
  "SUBMITTED",
  "SENT",
  "ACKNOWLEDGED",
  "PARTIALLY_RECEIVED",
  "RECEIVED",
  "FOR_REVIEW",
  "ASSESSED",
  "OVERDUE",
  "EXTENSION_REQUESTED",
  "EXTENDED",
  "ESCALATED",
  "CANCELLED",
  "CLOSED_WITHOUT_SUBMISSION",
  "CLOSED",
];
const statusTones = {
  DRAFT: "inactive",
  SUBMITTED: "warning",
  SENT: "info",
  PARTIALLY_RECEIVED: "warning",
  RECEIVED: "active",
  ASSESSED: "success",
  CLOSED: "success",
  VERIFIED: "success",
  LOCKED: "active",
  VOIDED: "danger",
  REGISTERED: "inactive",
  FOR_ASSESSMENT: "info",
  ACCEPTED: "success",
  LIMITED: "warning",
  ADDITIONAL_REQUIRED: "warning",
  REJECTED: "danger",
  DUPLICATE: "danger",
  ACKNOWLEDGED: "info",
  FOR_REVIEW: "info",
  OVERDUE: "danger",
  EXTENSION_REQUESTED: "warning",
  EXTENDED: "active",
  ESCALATED: "danger",
  CANCELLED: "danger",
  CLOSED_WITHOUT_SUBMISSION: "inactive",
};

const emptyRequest = {
  title: "",
  purpose: "",
  dueDate: "",
  requestedFromOfficeId: "",
  requestedFromUserId: "",
  requestedItems: "",
};

const emptyAssessment = {
  evidenceId: "",
  evidenceRequestId: "",
  documentVersionId: "",
  sufficiency: "",
  appropriateness: "",
  relevance: "",
  reliability: "",
  competence: "",
  accuracy: "",
  completeness: "",
  corroboration: "",
  contradiction: "",
  authenticity: "",
  integrity: "",
  confidentiality: "INTERNAL",
  isRestricted: false,
  accessRestrictions: "",
  limitations: "",
  evidenceGaps: "",
  exceptionRequired: false,
  exceptionReason: "",
  evidenceOutcome: "",
};

function label(value) {
  return String(value ?? "")
    .replaceAll("_", " ")
    .replace(/\b\w/g, (character) => character.toUpperCase());
}

function date(value, withTime = false) {
  if (!value) return "—";
  const parsed = new Date(withTime ? value : `${value}T00:00:00`);
  if (Number.isNaN(parsed.getTime())) return "—";
  return new Intl.DateTimeFormat("en-PH", {
    month: "short",
    day: "numeric",
    year: "numeric",
    ...(withTime ? { hour: "numeric", minute: "2-digit" } : {}),
  }).format(parsed);
}

function bytes(value) {
  const size = Number(value || 0);
  if (!size) return "—";
  if (size < 1024) return `${size} B`;
  if (size < 1024 ** 2) return `${(size / 1024).toFixed(1)} KB`;
  return `${(size / 1024 ** 2).toFixed(1)} MB`;
}

function Field({ label: fieldLabel, error, children, wide = false, hint }) {
  return (
    <label className={`text-sm font-semibold text-slate-700 ${wide ? "sm:col-span-2" : ""}`}>
      {fieldLabel}
      {hint && <span className="ml-1 text-xs font-normal text-slate-400">{hint}</span>}
      <span className="mt-1.5 block">{children}</span>
      {error && <small className="mt-1 block text-red-600">{error[0]}</small>}
    </label>
  );
}

function assessmentState(evidence) {
  const assessment = evidence?.assessment;
  if (!assessment) return { label: "Not assessed", tone: "warning" };
  if (assessment.isRestricted || assessment.accessRestrictions) {
    return assessment.exceptionApprovedAt
      ? { label: "Restricted • exception approved", tone: "active" }
      : { label: "Restricted", tone: "danger" };
  }
  if (assessment.eligibleForFinalizedFinding) return { label: "Accepted for reporting", tone: "success" };
  if (assessment.evidenceGaps || assessment.limitations) return { label: "Insufficient / gaps", tone: "warning" };
  return { label: "Assessed", tone: "info" };
}

function stageTimestamp(record, stage) {
  const map = {
    SUBMITTED: record.submittedAt,
    SENT: record.sentAt,
    PARTIALLY_RECEIVED: record.partiallyReceivedAt,
    RECEIVED: record.receivedAt,
    ASSESSED: record.assessedAt,
    FOR_REVIEW: record.receivedAt,
    ACKNOWLEDGED: record.acknowledgedAt,
    OVERDUE: record.overdueAt,
    EXTENDED: record.extensionApprovedAt,
    EXTENSION_REQUESTED: record.extensionRequestedAt,
    ESCALATED: record.escalatedAt,
    CANCELLED: record.cancelledAt,
    CLOSED_WITHOUT_SUBMISSION: record.closedAt,
    CLOSED: record.closedAt,
  };
  return map[stage];
}

function errorFields(requestError) {
  return requestError instanceof ApiError ? requestError.errors : {};
}

export default function AemsEvidenceManagementPage() {
  const { user } = useAuth();
  const toast = useToast();
  const [params, setParams] = useSearchParams();
  const [engagements, setEngagements] = useState([]);
  const [engagementId, setEngagementId] = useState(params.get("engagementId") ?? "");
  const [workspace, setWorkspace] = useState(null);
  const [evidenceWorkspace, setEvidenceWorkspace] = useState(null);
  const [tab, setTab] = useState(params.get("tab") ?? "requests");
  const [query, setQuery] = useState("");
  const [selectedRequestId, setSelectedRequestId] = useState(params.get("requestId") ?? "");
  const [selectedEvidenceId, setSelectedEvidenceId] = useState(params.get("evidenceId") ?? "");
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [errors, setErrors] = useState({});
  const [requestOpen, setRequestOpen] = useState(false);
  const [requestForm, setRequestForm] = useState(emptyRequest);
  const [receiveOpen, setReceiveOpen] = useState(false);
  const [receiveForm, setReceiveForm] = useState({ evidenceId: "", receiptNotes: "" });
  const [assessmentOpen, setAssessmentOpen] = useState(false);
  const [assessmentForm, setAssessmentForm] = useState(emptyAssessment);
  const [exceptionOpen, setExceptionOpen] = useState(false);
  const [exceptionForm, setExceptionForm] = useState({ lockVersion: "", comment: "" });

  const canCreate = hasPermission(user, "aems.evidence-request.create");
  const canSubmit = hasPermission(user, "aems.evidence-request.submit");
  const canSend = hasPermission(user, "aems.evidence-request.send");
  const canReceive = hasPermission(user, "aems.evidence-request.receive");
  const canAssess = hasPermission(user, "aems.evidence.assess");
  const canClose = hasPermission(user, "aems.evidence-request.close");
  const canAcknowledge = hasPermission(user, "aems.evidence-request.acknowledge");
  const canExtend = hasPermission(user, "aems.evidence-request.extend");
  const canApproveExtension = hasPermission(user, "aems.evidence-request.extension_approve");
  const canOverdue = hasPermission(user, "aems.evidence-request.overdue");
  const canEscalate = hasPermission(user, "aems.evidence-request.escalate");
  const canCancel = hasPermission(user, "aems.evidence-request.cancel");
  const canApproveException = hasPermission(user, "aems.evidence.exception_approve");

  const loadEngagements = useCallback(async () => {
    try {
      const data = await aemsEngagementApi.list({ perPage: 100, sortBy: "updated_at", sortDirection: "desc" });
      setEngagements(data.engagements);
      setEngagementId((current) => current || String(data.engagements[0]?.id ?? ""));
    } catch (requestError) {
      setError(requestError.message);
    }
  }, []);

  const loadWorkspace = useCallback(async () => {
    if (!engagementId) {
      setWorkspace(null);
      setEvidenceWorkspace(null);
      setLoading(false);
      return;
    }
    setLoading(true);
    setError("");
    try {
      const [requestResult, evidenceResult] = await Promise.allSettled([
        aemsEvidenceRequestApi.show(engagementId),
        aemsWorkingPaperApi.show(engagementId),
      ]);
      if (requestResult.status === "rejected") throw requestResult.reason;
      setWorkspace(requestResult.value);
      setEvidenceWorkspace(evidenceResult.status === "fulfilled" ? evidenceResult.value : null);
      const requests = requestResult.value?.requests ?? [];
      const assessments = requestResult.value?.assessments ?? [];
      const evidence = requestResult.value?.evidence ?? [];
      setSelectedRequestId((current) => requests.some((item) => String(item.id) === String(current)) ? current : String(requests[0]?.id ?? ""));
      setSelectedEvidenceId((current) => evidence.some((item) => String(item.id) === String(current)) ? current : String(evidence[0]?.id ?? ""));
      if (!assessments.length && tab === "assessments") setTab("evidence");
    } catch (requestError) {
      setWorkspace(null);
      setError(requestError.message);
    } finally {
      setLoading(false);
    }
  }, [engagementId, tab]);

  useEffect(() => {
    const timer = window.setTimeout(loadEngagements, 0);
    return () => window.clearTimeout(timer);
  }, [loadEngagements]);

  useEffect(() => {
    const timer = window.setTimeout(loadWorkspace, 0);
    return () => window.clearTimeout(timer);
  }, [loadWorkspace]);

  useEffect(() => {
    setParams({ engagementId, tab, ...(selectedRequestId ? { requestId: selectedRequestId } : {}), ...(selectedEvidenceId ? { evidenceId: selectedEvidenceId } : {}) }, { replace: true });
  }, [engagementId, selectedEvidenceId, selectedRequestId, setParams, tab]);

  const selectedRequest = (workspace?.requests ?? []).find((item) => String(item.id) === String(selectedRequestId));
  const evidenceMetadata = useMemo(() => new Map((evidenceWorkspace?.evidence ?? []).map((item) => [String(item.id), item])), [evidenceWorkspace]);
  const allEvidence = useMemo(() => (workspace?.evidence ?? []).map((item) => ({ ...item, ...(evidenceMetadata.get(String(item.id)) ?? {}) })), [evidenceMetadata, workspace]);
  const selectedEvidence = allEvidence.find((item) => String(item.id) === String(selectedEvidenceId));
  const linkedEvidenceIds = useMemo(() => new Set((workspace?.requests ?? []).flatMap((item) => (item.evidence ?? []).map((link) => String(link.evidenceId)))), [workspace]);
  const currentAssessments = workspace?.assessments ?? [];

  const filteredRequests = useMemo(() => {
    const normalized = query.trim().toLowerCase();
    if (!normalized) return workspace?.requests ?? [];
    return (workspace?.requests ?? []).filter((item) => [item.requestCode, item.title, item.purpose, item.status].some((value) => String(value ?? "").toLowerCase().includes(normalized)));
  }, [query, workspace]);

  const filteredEvidence = useMemo(() => {
    const normalized = query.trim().toLowerCase();
    if (!normalized) return allEvidence;
    return allEvidence.filter((item) => [item.evidenceCode, item.title, item.status, item.fileName, item.mimeType].some((value) => String(value ?? "").toLowerCase().includes(normalized)));
  }, [allEvidence, query]);

  const assessmentOptions = allEvidence.filter((item) => ["VERIFIED", "LOCKED"].includes(item.status)).map((item) => ({ value: item.id, label: `${item.evidenceCode} v${item.versionNumber} — ${item.title}`, description: `Core Document Version ${item.documentVersionId}` }));
  const engagementOptions = engagements.map((item) => ({ value: item.id, label: `${item.engagementCode} — ${item.title}` }));
  const requestOfficeOptions = (workspace?.requestReferences?.offices ?? []).map((item) => ({ value: item.id, label: `${item.code} — ${item.name}` }));
  const requestUserOptions = (workspace?.requestReferences?.users ?? []).map((item) => ({ value: item.id, label: item.name, description: item.officeId ? `Office #${item.officeId}` : "Engagement participant" }));
  const selectedEngagement = engagements.find(
    (item) => String(item.id) === String(engagementId),
  );
  const executionGate = getAemsWorkspaceGate("execution", selectedEngagement);
  const summary = {
    requests: workspace?.requests?.length ?? 0,
    received: (workspace?.requests ?? []).filter((item) => ["PARTIALLY_RECEIVED", "RECEIVED", "FOR_REVIEW", "ASSESSED", "CLOSED"].includes(item.status)).length,
    assessed: currentAssessments.length,
    restricted: currentAssessments.filter((item) => item.isRestricted || item.accessRestrictions).length,
    accepted: currentAssessments.filter((item) => item.eligibleForFinalizedFinding).length,
  };

  function showErrors(requestError) {
    setErrors(errorFields(requestError));
    setError(requestError.message);
  }

  async function refresh() {
    await loadWorkspace();
    toast.success("Evidence workspace refreshed.");
  }

  async function submitRequest(event) {
    event.preventDefault();
    setSaving(true); setErrors({});
    try {
      const record = await aemsEvidenceRequestApi.create(engagementId, { ...requestForm, requestedItems: requestForm.requestedItems.split("\n").map((item) => item.trim()).filter(Boolean) });
      setRequestOpen(false); setRequestForm(emptyRequest); setSelectedRequestId(String(record?.id ?? ""));
      await loadWorkspace();
      toast.success("Evidence Request draft created.");
    } catch (requestError) { showErrors(requestError); } finally { setSaving(false); }
  }

  async function transitionRequest(action, comment, extra = {}) {
    if (!selectedRequest) return;
    setSaving(true); setErrors({});
    try {
      await aemsEvidenceRequestApi.transition(engagementId, selectedRequest.id, { action, lockVersion: selectedRequest.lockVersion, comment: comment || undefined, ...extra });
      await loadWorkspace();
      toast.success(`Evidence Request ${label(action).toLowerCase()}.`);
    } catch (requestError) { showErrors(requestError); } finally { setSaving(false); }
  }

  async function receiveEvidence(event) {
    event.preventDefault();
    if (!selectedRequest) return;
    const evidence = allEvidence.find((item) => String(item.id) === String(receiveForm.evidenceId));
    if (!evidence) return;
    setSaving(true); setErrors({});
    try {
      await aemsEvidenceRequestApi.receive(engagementId, selectedRequest.id, { evidenceId: evidence.id, documentVersionId: evidence.documentVersionId, lockVersion: selectedRequest.lockVersion, receiptNotes: receiveForm.receiptNotes || undefined });
      setReceiveOpen(false); setReceiveForm({ evidenceId: "", receiptNotes: "" });
      await loadWorkspace();
      toast.success("Exact Evidence/Core Document Version received.");
    } catch (requestError) { showErrors(requestError); } finally { setSaving(false); }
  }

  function openAssessment(evidence, requestId = "") {
    const assessment = evidence?.assessment ?? {};
    setAssessmentForm({ ...emptyAssessment, evidenceId: evidence?.id ?? "", evidenceRequestId: requestId, documentVersionId: evidence?.documentVersionId ?? "", ...Object.fromEntries(Object.keys(emptyAssessment).filter((key) => key in assessment).map((key) => [key, assessment[key] ?? emptyAssessment[key]])) });
    setAssessmentOpen(true); setErrors({});
  }

  async function saveAssessment(event) {
    event.preventDefault();
    setSaving(true); setErrors({});
    try {
      await aemsEvidenceRequestApi.assess(engagementId, { ...assessmentForm, evidenceId: Number(assessmentForm.evidenceId), evidenceRequestId: assessmentForm.evidenceRequestId ? Number(assessmentForm.evidenceRequestId) : undefined, documentVersionId: Number(assessmentForm.documentVersionId) });
      setAssessmentOpen(false); await loadWorkspace();
      toast.success("Immutable evidence assessment recorded.");
    } catch (requestError) { showErrors(requestError); } finally { setSaving(false); }
  }

  async function approveException(event) {
    event.preventDefault();
    const assessment = selectedEvidence?.assessment;
    if (!assessment) return;
    setSaving(true); setErrors({});
    try {
      await aemsEvidenceRequestApi.approveException(engagementId, assessment.id, exceptionForm);
      setExceptionOpen(false); await loadWorkspace();
      toast.success("Restricted-evidence exception approved.");
    } catch (requestError) { showErrors(requestError); } finally { setSaving(false); }
  }

  function requestAction() {
    if (!selectedRequest) return null;
    const controlActions = <div className="flex flex-wrap gap-2">{canExtend && ["SENT", "ACKNOWLEDGED", "OVERDUE", "ESCALATED"].includes(selectedRequest.status) && <button className={buttonSecondary} disabled={saving} onClick={() => { const due = window.prompt("New extension due date (YYYY-MM-DD):"); const reason = window.prompt("Extension reason:"); if (due && reason) transitionRequest("REQUEST_EXTENSION", reason, { extensionDueDate: due }); }} type="button"><Clock3 size={16} /> Request extension</button>}{canOverdue && ["SENT", "ACKNOWLEDGED", "EXTENDED"].includes(selectedRequest.status) && <button className={buttonSecondary} disabled={saving} onClick={() => transitionRequest("MARK_OVERDUE", "Due date passed without complete receipt.")} type="button"><AlertTriangle size={16} /> Mark overdue</button>}{canEscalate && selectedRequest.status === "OVERDUE" && <button className={buttonSecondary} disabled={saving} onClick={() => transitionRequest("ESCALATE", "Evidence request requires escalation follow-up.")} type="button"><ShieldAlert size={16} /> Escalate</button>}{canCancel && ["SENT", "ACKNOWLEDGED", "OVERDUE", "ESCALATED"].includes(selectedRequest.status) && <button className={buttonSecondary} disabled={saving} onClick={() => transitionRequest("CANCEL", "Request cancelled by authorized owner.")} type="button">Cancel request</button>}{canClose && ["SENT", "ACKNOWLEDGED", "OVERDUE", "ESCALATED"].includes(selectedRequest.status) && <button className={buttonSecondary} disabled={saving} onClick={() => transitionRequest("CLOSE_WITHOUT_SUBMISSION", "No submission received after due process.")} type="button">Close without submission</button>}</div>;
    if (selectedRequest.status === "DRAFT" && canSubmit) return <button className={buttonPrimary} disabled={saving} onClick={() => transitionRequest("SUBMIT")} type="button"><Send size={16} /> Submit</button>;
    if (selectedRequest.status === "SUBMITTED" && canSend) return <button className={buttonPrimary} disabled={saving} onClick={() => transitionRequest("SEND")} type="button"><Send size={16} /> Send to custodian</button>;
    if (selectedRequest.status === "SENT" && canAcknowledge) return <><button className={buttonPrimary} disabled={saving} onClick={() => transitionRequest("ACKNOWLEDGE", "Custodian acknowledgement recorded.")} type="button"><Check size={16} /> Acknowledge</button>{canReceive && <button className={buttonSecondary} disabled={saving} onClick={() => transitionRequest("MARK_RECEIVED")} type="button"><Check size={16} /> Mark received</button>}{controlActions}</>;
    if (["ACKNOWLEDGED", "SENT", "PARTIALLY_RECEIVED", "OVERDUE", "EXTENDED", "ESCALATED"].includes(selectedRequest.status) && canReceive) return <><button className={buttonSecondary} disabled={saving} onClick={() => transitionRequest("MARK_PARTIALLY_RECEIVED")} type="button"><Clock3 size={16} /> Mark partial</button><button className={buttonPrimary} disabled={saving} onClick={() => transitionRequest("MARK_RECEIVED")} type="button"><Check size={16} /> Mark received</button>{controlActions}</>;
    if (selectedRequest.status === "RECEIVED" && canAssess) return <button className={buttonPrimary} disabled={saving} onClick={() => transitionRequest("FOR_REVIEW")} type="button"><BadgeCheck size={16} /> Queue assessment</button>;
    if (selectedRequest.status === "FOR_REVIEW" && canAssess) return <button className={buttonPrimary} disabled={saving} onClick={() => transitionRequest("ASSESS")} type="button"><BadgeCheck size={16} /> Complete assessment</button>;
    if (selectedRequest.status === "ASSESSED" && canClose) return <button className={buttonPrimary} disabled={saving} onClick={() => transitionRequest("CLOSE", "Evidence request reviewed and closed.")} type="button"><CheckCircle2 size={16} /> Close request</button>;
    if (["SENT", "ACKNOWLEDGED", "OVERDUE", "ESCALATED"].includes(selectedRequest.status)) return controlActions;
    if (selectedRequest.status === "EXTENSION_REQUESTED" && canApproveExtension) return <><button className={buttonPrimary} disabled={saving} onClick={() => transitionRequest("APPROVE_EXTENSION", "Extension approved after review.")} type="button"><Check size={16} /> Approve extension</button><button className={buttonSecondary} disabled={saving} onClick={() => transitionRequest("REJECT_EXTENSION", "Extension request declined.")} type="button">Decline</button></>;
    return null;
  }

  return (
    <main className="min-w-0 p-4 sm:p-5" data-testid="aems-evidence-workspace">
      {engagementId && (
        <AemsEngagementWorkspaceNav
          engagement={selectedEngagement}
          engagementId={engagementId}
        />
      )}
      <RegistryHeader icon={FileCheck2} title="Evidence Management" description="Track Evidence Requests, custody, assessment quality, restrictions, and reporting eligibility from one engagement workspace." readOnly={!canCreate && !canReceive && !canAssess} actions={<><button className={buttonSecondary} onClick={refresh} type="button"><RefreshCw size={16} /> Refresh</button>{executionGate.unlocked && canCreate && <button className={buttonPrimary} disabled={!engagementId} onClick={() => { setRequestForm(emptyRequest); setRequestOpen(true); }} type="button"><Plus size={16} /> New Evidence Request</button>}</>} />

      {selectedEngagement && !executionGate.unlocked && (
        <AemsWorkspaceLockNotice
          engagementId={engagementId}
          gate={executionGate}
        />
      )}

      <div className="mb-4 grid gap-3 rounded-xl border border-slate-200 bg-white p-3 shadow-sm lg:grid-cols-[minmax(0,1fr)_minmax(15rem,24rem)]">
        <SearchableSelect options={engagementOptions} placeholder="Select engagement" value={engagementId} onChange={(value) => { setEngagementId(String(value)); setSelectedRequestId(""); setSelectedEvidenceId(""); }} />
        <label className="flex h-11 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-slate-400 focus-within:border-sky-500 focus-within:ring-2 focus-within:ring-sky-100"><Files size={17} /><input className="min-w-0 flex-1 text-sm text-slate-800 outline-none" onChange={(event) => setQuery(event.target.value)} placeholder="Search requests, evidence, files, or status" value={query} /></label>
      </div>

      {executionGate.unlocked && (
        <>
      <div className="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <SummaryCard icon={ClipboardCheck} label="Evidence Requests" tone="sky" value={summary.requests} />
        <SummaryCard icon={Upload} label="Received / Partial" tone="amber" value={summary.received} />
        <SummaryCard icon={BadgeCheck} label="Assessed" tone="emerald" value={summary.assessed} />
        <SummaryCard icon={LockKeyhole} label="Restricted" tone="red" value={summary.restricted} />
        <SummaryCard icon={ShieldCheck} label="Accepted for Reporting" tone="emerald" value={summary.accepted} />
      </div>
      <div className="mb-5 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
        <EvidenceStateNote label="Requests needing response" value={(workspace?.requests ?? []).filter((item) => ["SENT", "ACKNOWLEDGED", "OVERDUE", "EXTENSION_REQUESTED", "ESCALATED"].includes(item.status)).length} tone="text-amber-700" />
        <EvidenceStateNote label="Overdue / escalated" value={(workspace?.requests ?? []).filter((item) => ["OVERDUE", "ESCALATED"].includes(item.status)).length} tone="text-red-700" />
        <EvidenceStateNote label="Evidence requiring action" value={allEvidence.filter((item) => ["REGISTERED", "FOR_ASSESSMENT", "ADDITIONAL_REQUIRED", "LIMITED"].includes(item.outcome)).length} tone="text-sky-700" />
        <EvidenceStateNote label="Direct report links" value={allEvidence.reduce((total, item) => total + (item.reportVersions?.length ?? 0), 0)} tone="text-emerald-700" />
      </div>

      <div className="mb-4 flex flex-wrap gap-2 border-b border-slate-200">
        {[ ["requests", "Evidence Requests", ClipboardCheck], ["evidence", "Evidence Register", Files], ["assessments", "Assessments & Gaps", BadgeCheck] ].map(([key, text, Icon]) => <button className={`inline-flex items-center gap-2 border-b-2 px-4 py-3 text-sm font-bold ${tab === key ? "border-sky-700 text-sky-700" : "border-transparent text-slate-500 hover:text-slate-800"}`} key={key} onClick={() => setTab(key)} type="button"><Icon size={17} />{text}</button>)}
      </div>

      {error && <div className="mb-4 flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><AlertTriangle className="mt-0.5 shrink-0" size={17} />{error}</div>}
      {tab === "evidence" && selectedEvidence && <EvidenceG5Traceability evidence={selectedEvidence} />}
      {!engagementId && !loading ? <section className="grid min-h-72 place-items-center rounded-xl border border-dashed border-slate-300 bg-white px-5 py-12 text-center shadow-sm"><div className="max-w-md"><FileCheck2 className="mx-auto text-sky-600" size={38} /><h3 className="mt-4 text-base font-bold text-slate-800">Select an engagement to begin</h3><p className="mt-2 text-sm leading-6 text-slate-500">Evidence Requests, exact document versions, assessments, custody, and reporting eligibility will load in one workspace.</p></div></section> : loading ? <div className="grid min-h-64 place-items-center rounded-xl border border-slate-200 bg-white text-sm text-slate-500">Loading Evidence Management workspace…</div> : tab === "requests" ? <RequestWorkspace requests={filteredRequests} selectedRequest={selectedRequest} selectedRequestId={selectedRequestId} setSelectedRequestId={setSelectedRequestId} requestAction={requestAction} canReceive={canReceive} canAssess={canAssess} openReceive={() => setReceiveOpen(true)} openAssessment={openAssessment} saving={saving} /> : tab === "evidence" ? <EvidenceWorkspace evidence={filteredEvidence} selectedEvidence={selectedEvidence} selectedEvidenceId={selectedEvidenceId} setSelectedEvidenceId={setSelectedEvidenceId} linkedEvidenceIds={linkedEvidenceIds} openAssessment={openAssessment} canAssess={canAssess} canApproveException={canApproveException} openException={() => { setExceptionForm({ lockVersion: selectedEvidence?.assessment?.lockVersion ?? "", comment: "" }); setExceptionOpen(true); }} evidenceMetadata={evidenceMetadata} engagementId={engagementId} /> : <AssessmentWorkspace assessments={currentAssessments} evidence={allEvidence} query={query} setSelectedEvidenceId={setSelectedEvidenceId} setTab={setTab} />}

      </>
      )}

      <Modal open={requestOpen} onClose={() => setRequestOpen(false)} title="New Evidence Request" size="lg"><form className="space-y-4" onSubmit={submitRequest}><div className="grid gap-4 sm:grid-cols-2"><Field error={errors.title} label="Request title" wide><input className={inputClass} required value={requestForm.title} onChange={(event) => setRequestForm((current) => ({ ...current, title: event.target.value }))} /></Field><Field error={errors.dueDate} label="Due date"><input className={inputClass} type="date" value={requestForm.dueDate} onChange={(event) => setRequestForm((current) => ({ ...current, dueDate: event.target.value }))} /></Field><Field error={errors.requestedFromOfficeId} label="Requested from office"><SearchableSelect options={requestOfficeOptions} placeholder="Select auditee office" value={requestForm.requestedFromOfficeId} onChange={(value) => setRequestForm((current) => ({ ...current, requestedFromOfficeId: value }))} /></Field><Field error={errors.requestedFromUserId} label="Requested from user"><SearchableSelect options={requestUserOptions} placeholder="Optional specific custodian" value={requestForm.requestedFromUserId} onChange={(value) => setRequestForm((current) => ({ ...current, requestedFromUserId: value }))} /></Field><Field error={errors.purpose} label="Purpose" wide><textarea className={textAreaClass} required value={requestForm.purpose} onChange={(event) => setRequestForm((current) => ({ ...current, purpose: event.target.value }))} /></Field><Field error={errors.requestedItems} label="Requested items" hint="one per line" wide><textarea className={textAreaClass} required value={requestForm.requestedItems} onChange={(event) => setRequestForm((current) => ({ ...current, requestedItems: event.target.value }))} /></Field></div><p className="rounded-lg bg-sky-50 p-3 text-xs leading-5 text-sky-800">Select the auditee office or a specific custodian so the request appears in the CMS recipient portal. If both are left blank, the engagement’s covered office is used as the fallback recipient.</p><ModalActions onCancel={() => setRequestOpen(false)} saving={saving} submitLabel="Create draft" /></form></Modal>
      <Modal open={receiveOpen} onClose={() => setReceiveOpen(false)} title="Record Evidence Receipt" size="lg"><form className="space-y-4" onSubmit={receiveEvidence}><p className="rounded-lg bg-sky-50 p-3 text-sm text-sky-800">Select the exact current Evidence/Core Document Version received for <strong>{selectedRequest?.requestCode}</strong>. The server validates custody and version integrity.</p><Field error={errors.evidenceId} label="Evidence"><SearchableSelect options={assessmentOptions} placeholder="Select verified or locked evidence" value={receiveForm.evidenceId} onChange={(value) => setReceiveForm((current) => ({ ...current, evidenceId: value }))} /></Field><Field error={errors.receiptNotes} label="Receipt notes"><textarea className={textAreaClass} value={receiveForm.receiptNotes} onChange={(event) => setReceiveForm((current) => ({ ...current, receiptNotes: event.target.value }))} /></Field><ModalActions onCancel={() => setReceiveOpen(false)} saving={saving} submitLabel="Record receipt" /></form></Modal>
      <Modal open={assessmentOpen} onClose={() => setAssessmentOpen(false)} title="Evidence Assessment" size="xl"><form className="space-y-4" onSubmit={saveAssessment}><AssessmentForm form={assessmentForm} setForm={setAssessmentForm} errors={errors} evidence={allEvidence.find((item) => String(item.id) === String(assessmentForm.evidenceId))} /><ModalActions onCancel={() => setAssessmentOpen(false)} saving={saving} submitLabel="Save immutable assessment" /></form></Modal>
      <Modal open={exceptionOpen} onClose={() => setExceptionOpen(false)} title="Approve Restricted-Evidence Exception" size="lg"><form className="space-y-4" onSubmit={approveException}><div className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800"><strong>Professional decision required.</strong> This approval is separate from assessment and allows restricted evidence to be used for finalized Finding validation.</div><Field error={errors.comment} label="Approval rationale"><textarea className={textAreaClass} required minLength={5} value={exceptionForm.comment} onChange={(event) => setExceptionForm((current) => ({ ...current, comment: event.target.value }))} /></Field><ModalActions onCancel={() => setExceptionOpen(false)} saving={saving} submitLabel="Approve exception" /></form></Modal>
    </main>
  );
}

function ModalActions({ onCancel, saving, submitLabel }) {
  return <div className="flex justify-end gap-2 border-t border-slate-200 pt-4"><button className={buttonSecondary} onClick={onCancel} type="button">Cancel</button><button className={buttonPrimary} disabled={saving} type="submit">{saving ? "Saving…" : submitLabel}</button></div>;
}

function EvidenceStateNote({ label: stateLabel, value, tone }) {
  return <div className="rounded-lg bg-slate-50 p-3"><span className="block text-[11px] font-bold uppercase tracking-wide text-slate-400">{stateLabel}</span><strong className={`mt-1 block text-xl ${tone}`}>{value}</strong></div>;
}

function EvidenceG5Traceability({ evidence }) {
  return <section className="mb-4 grid gap-3 rounded-xl border border-sky-100 bg-sky-50 p-4 text-sm sm:grid-cols-4"><div><span className="block text-[11px] font-bold uppercase tracking-wide text-sky-600">Outcome</span><strong className="mt-1 block text-slate-800">{label(evidence.outcome || "REGISTERED")}</strong><span className="text-xs text-slate-500">{evidence.assessment?.eligibleForFinalizedFinding ? "Accepted for reporting" : "Professional eligibility still required"}</span></div><div><span className="block text-[11px] font-bold uppercase tracking-wide text-sky-600">Acquisition</span><strong className="mt-1 block text-slate-800">{label(evidence.acquisitionMethod || "Not recorded")}</strong><span className="text-xs text-slate-500">{label(evidence.acquisitionForm || "Form not recorded")}</span></div><div><span className="block text-[11px] font-bold uppercase tracking-wide text-sky-600">Planning traceability</span><strong className="mt-1 block text-slate-800">{evidence.planningObjective?.objective_code || "Objective not linked"}</strong><span className="text-xs text-slate-500">Risk {evidence.riskMatrixItem?.risk_code || "not linked"} · Control {evidence.controlReference || "not linked"}</span></div><div><span className="block text-[11px] font-bold uppercase tracking-wide text-sky-600">Report traceability</span><strong className="mt-1 block text-slate-800">{evidence.reportVersions?.length || 0} report version link{evidence.reportVersions?.length === 1 ? "" : "s"}</strong><span className="text-xs text-slate-500">Direct report links are protected and immutable once issued.</span></div></section>;
}

function RequestWorkspace({ requests, selectedRequest, selectedRequestId, setSelectedRequestId, requestAction, canReceive, canAssess, openReceive, openAssessment, saving }) {
  return <div className="grid gap-4 xl:grid-cols-[minmax(18rem,0.8fr)_minmax(0,1.5fr)]"><div className="space-y-2 rounded-xl border border-slate-200 bg-white p-3 shadow-sm">{requests.map((item) => <button className={`w-full rounded-xl border p-3 text-left transition ${String(selectedRequestId) === String(item.id) ? "border-sky-300 bg-sky-50 ring-1 ring-sky-200" : "border-slate-200 hover:border-sky-200 hover:bg-slate-50"}`} key={item.id} onClick={() => setSelectedRequestId(String(item.id))} type="button"><span className="flex items-start justify-between gap-2"><span className="min-w-0"><strong className="block truncate text-sm text-slate-800">{item.requestCode}</strong><span className="mt-1 block text-xs text-slate-500">Due {date(item.dueDate)}</span></span><StatusBadge tone={statusTones[item.status]}>{label(item.status)}</StatusBadge></span><span className="mt-2 block line-clamp-2 text-sm text-slate-700">{item.title}</span></button>)}{!requests.length && <p className="px-3 py-12 text-center text-sm text-slate-500">No Evidence Requests match the current search.</p>}</div><div className="min-w-0 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">{selectedRequest ? <RequestDetail record={selectedRequest} requestAction={requestAction} canReceive={canReceive} canAssess={canAssess} openReceive={openReceive} openAssessment={openAssessment} saving={saving} /> : <EmptyPanel icon={ClipboardCheck} title="Select an Evidence Request" text="Inspect requested items, receipt tracking, correspondence history, and assessment readiness." />}</div></div>;
}

function RequestDetail({ record, requestAction, canReceive, canAssess, openReceive, openAssessment, saving }) {
  const stageIndex = requestStages.indexOf(record.status);
  return <div className="space-y-5"><div className="flex flex-col gap-3 border-b border-slate-200 pb-4 sm:flex-row sm:items-start sm:justify-between"><div><div className="flex flex-wrap items-center gap-2"><h3 className="text-lg font-bold text-slate-900">{record.requestCode}</h3><StatusBadge tone={statusTones[record.status]}>{label(record.status)}</StatusBadge><span className="text-xs font-bold text-slate-400">Version {record.currentVersionNumber}</span></div><p className="mt-1 text-sm text-slate-600">{record.title}</p></div><div className="flex flex-wrap gap-2">{["SENT", "ACKNOWLEDGED", "PARTIALLY_RECEIVED", "OVERDUE", "EXTENDED", "ESCALATED"].includes(record.status) && canReceive && <button className="button-secondary" disabled={saving} onClick={openReceive} type="button"><Upload size={16} /> Record receipt</button>}{["RECEIVED", "FOR_REVIEW"].includes(record.status) && canAssess && (record.evidence ?? []).map((link) => <button className="button-secondary" key={link.id} onClick={() => openAssessment(link.evidence ?? { id: link.evidenceId, documentVersionId: link.documentVersionId })} type="button"><BadgeCheck size={16} /> Assess evidence</button>)}{requestAction()}</div></div><div className="grid gap-3 sm:grid-cols-4 xl:grid-cols-8">{requestStages.map((stage, index) => <div className="min-w-0" key={stage}><div className={`flex items-center gap-2 text-xs font-bold ${index <= stageIndex ? "text-sky-700" : "text-slate-400"}`}><span className={`grid h-7 w-7 shrink-0 place-items-center rounded-full ${index < stageIndex ? "bg-sky-700 text-white" : index === stageIndex ? "bg-sky-100 text-sky-700 ring-2 ring-sky-200" : "bg-slate-100"}`}>{index < stageIndex ? <Check size={14} /> : index + 1}</span><span className="truncate">{label(stage)}</span></div>{stageTimestamp(record, stage) && <span className="ml-9 mt-1 block text-[11px] text-slate-400">{date(stageTimestamp(record, stage), true)}</span>}</div>)}</div><div className="grid gap-3 sm:grid-cols-2"><Info label="Purpose" value={record.purpose} /><Info label="Due date" value={date(record.extensionDueDate || record.dueDate)} /><Info label="Requested from office" value={record.requestedFromOffice?.name ?? "Not assigned"} /><Info label="Requested from user" value={record.requestedFromUser?.name ?? "Not assigned"} /></div><section className="rounded-xl border border-slate-200 bg-slate-50 p-4"><h4 className="flex items-center gap-2 text-sm font-bold text-slate-800"><Upload size={16} className="text-sky-700" /> Submission tracking</h4><div className="mt-3 grid gap-2 sm:grid-cols-3">{(record.evidence ?? []).map((link) => <div className="rounded-lg bg-white p-3 text-xs" key={link.id}><strong className="block text-slate-700">{link.evidenceCode ?? `Evidence #${link.evidenceId}`}</strong><span className="mt-1 block text-slate-500">Document Version {link.documentVersionId}</span><span className="mt-1 block text-slate-400">Received {date(link.receivedAt, true)}</span><span className="mt-2 block text-slate-600">{link.receiptNotes || "No receipt note."}</span><div className="mt-2 flex items-center justify-between gap-2"><StatusBadge tone={link.assessment?.eligibleForFinalizedFinding ? "success" : link.assessment ? "warning" : "inactive"}>{link.assessment?.eligibleForFinalizedFinding ? "Accepted" : link.assessment ? "Assessed / review" : "Awaiting assessment"}</StatusBadge><span className="truncate text-[11px] text-slate-400">{link.checksumSha256?.slice(0, 12) || "Checksum pending"}</span></div></div>)}{!(record.evidence ?? []).length && <p className="text-sm text-slate-500">No evidence has been received yet.</p>}</div></section><section className="rounded-xl border border-slate-200 bg-white p-4"><h4 className="flex items-center gap-2 text-sm font-bold text-slate-800"><History size={16} className="text-sky-700" /> Request correspondence and version history</h4><div className="mt-3 space-y-2">{(record.versions ?? []).map((version) => <div className="rounded-lg border border-slate-200 p-3 text-xs" key={version.id}><div className="flex flex-wrap items-center justify-between gap-2"><strong>Request version {version.versionNumber}</strong><span className="text-slate-400">{date(version.createdAt, true)}</span></div><p className="mt-1 text-slate-600">{version.requestedItems?.join(" • ") || "No item list recorded."}</p>{version.changeReason && <p className="mt-1 text-amber-700">Revision: {version.changeReason}</p>}</div>)}</div></section>{(record.events ?? []).length > 0 && <section className="rounded-xl border border-slate-200 bg-white p-4"><h4 className="flex items-center gap-2 text-sm font-bold text-slate-800"><History size={16} className="text-sky-700" /> Lifecycle audit trail</h4><div className="mt-3 space-y-2">{record.events.map((event) => <div className="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-slate-50 p-3 text-xs" key={event.id}><span><strong>{label(event.eventType)}</strong><span className="ml-2 text-slate-500">{event.fromStatus ? `${label(event.fromStatus)} → ` : ""}{label(event.toStatus)}</span>{event.reason && <span className="ml-2 text-slate-500">{event.reason}</span>}</span><span className="text-slate-400">{date(event.createdAt, true)}</span></div>)}</div></section>}</div>;
}

function EvidenceWorkspace({ evidence, selectedEvidence, selectedEvidenceId, setSelectedEvidenceId, linkedEvidenceIds, openAssessment, canAssess, canApproveException, openException, evidenceMetadata, engagementId }) {
  return <div className="grid gap-4 xl:grid-cols-[minmax(18rem,0.8fr)_minmax(0,1.5fr)]"><div className="space-y-2 rounded-xl border border-slate-200 bg-white p-3 shadow-sm">{evidence.map((item) => { const state = assessmentState(item); return <button className={`w-full rounded-xl border p-3 text-left transition ${String(selectedEvidenceId) === String(item.id) ? "border-sky-300 bg-sky-50 ring-1 ring-sky-200" : "border-slate-200 hover:border-sky-200 hover:bg-slate-50"}`} key={item.id} onClick={() => setSelectedEvidenceId(String(item.id))} type="button"><span className="flex items-start justify-between gap-2"><span className="min-w-0"><strong className="block truncate text-sm text-slate-800">{item.evidenceCode}</strong><span className="mt-1 block truncate text-xs text-slate-500">{item.fileName || "Document version linked"}</span></span><StatusBadge tone={statusTones[item.status]}>{label(item.status)}</StatusBadge></span><span className="mt-2 block truncate text-sm text-slate-700">{item.title}</span><span className="mt-2 flex flex-wrap items-center gap-1"><StatusBadge tone={state.tone}>{state.label}</StatusBadge>{linkedEvidenceIds.has(String(item.id)) && <span className="text-[11px] font-bold text-sky-700">Requested</span>}</span></button>; })}{!evidence.length && <p className="px-3 py-12 text-center text-sm text-slate-500">No evidence records match the current search.</p>}</div><div className="min-w-0 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">{selectedEvidence ? <EvidenceDetail evidence={selectedEvidence} openAssessment={openAssessment} canAssess={canAssess} canApproveException={canApproveException} openException={openException} evidenceMetadata={evidenceMetadata} engagementId={engagementId} /> : <EmptyPanel icon={Files} title="Select Evidence" text="Inspect custody, checksum, restrictions, assessment gaps, version history, and linked audit records." />}</div></div>;
}

function EvidenceDetail({ evidence, openAssessment, canAssess, canApproveException, openException, evidenceMetadata, engagementId }) {
  const state = assessmentState(evidence);
  const history = [...evidenceMetadata.values()].filter((item) => item.familyUuid && item.familyUuid === evidence.familyUuid).sort((a, b) => Number(b.versionNumber) - Number(a.versionNumber));
  const assessment = evidence.assessment;
  return <div className="space-y-5"><div className="flex flex-col gap-3 border-b border-slate-200 pb-4 sm:flex-row sm:items-start sm:justify-between"><div><div className="flex flex-wrap items-center gap-2"><h3 className="text-lg font-bold text-slate-900">{evidence.evidenceCode}</h3><StatusBadge tone={statusTones[evidence.status]}>{label(evidence.status)}</StatusBadge><StatusBadge tone={state.tone}>{state.label}</StatusBadge><span className="text-xs font-bold text-slate-400">v{evidence.versionNumber}</span></div><p className="mt-1 text-sm text-slate-600">{evidence.title}</p></div><div className="flex flex-wrap gap-2">{canAssess && ["VERIFIED", "LOCKED"].includes(evidence.status) && <button className={buttonPrimary} onClick={() => openAssessment(evidence)} type="button"><BadgeCheck size={16} /> {assessment ? "Correct assessment" : "Assess evidence"}</button>}{canApproveException && assessment && (assessment.isRestricted || assessment.accessRestrictions) && !assessment.exceptionApprovedAt && <button className={buttonSecondary} onClick={openException} type="button"><ShieldCheck size={16} /> Approve exception</button>}</div></div><div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3"><Info label="Evidence type" value={evidence.evidenceCategory?.label ?? "—"} /><Info label="Source" value={evidence.evidenceSourceType?.label ?? evidence.sourceDescription ?? "—"} /><Info label="Date obtained" value={date(evidence.dateObtained)} /><Info label="Custodian" value={evidence.custodianName ?? "—"} /><Info label="Confidentiality" value={evidence.confidentialityLevel?.label ?? "—"} /><Info label="Core Document Version" value={evidence.documentVersionId ? `#${evidence.documentVersionId}` : "—"} /></div><section className="rounded-xl border border-slate-200 bg-slate-50 p-4"><h4 className="flex items-center gap-2 text-sm font-bold text-slate-800"><LockKeyhole size={16} className="text-sky-700" /> Custody and file integrity</h4><div className="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4"><Info label="File" value={evidence.fileName ?? "—"} /><Info label="Size" value={bytes(evidence.fileSize)} /><Info label="MIME type" value={evidence.mimeType ?? "—"} /><Info label="Checksum SHA-256" value={evidence.checksumSha256 ?? "—"} mono /><Info label="Uploaded by" value={evidence.uploadedBy?.name ?? "—"} /><Info label="Verified by" value={evidence.verifiedBy?.name ?? "—"} /><Info label="Verified at" value={date(evidence.verifiedAt, true)} /><Info label="Locked at" value={date(evidence.lockedAt, true)} /></div></section><AssessmentCard assessment={assessment} /><section className="rounded-xl border border-slate-200 bg-white p-4"><h4 className="flex items-center gap-2 text-sm font-bold text-slate-800"><Link2 size={16} className="text-sky-700" /> Linked audit context</h4><div className="mt-3 flex flex-wrap gap-2"><Link className={contextLink} to={`/audit-engagement-management/working-papers?engagementId=${engagementId}&evidenceId=${evidence.id}`}><Files size={14} /> Working Papers</Link><Link className={contextLink} to={`/audit-engagement-management/execution?engagementId=${engagementId}`}><FileClock size={14} /> Fieldwork</Link><Link className={contextLink} to={`/audit-engagement-management/issues?engagementId=${engagementId}&evidenceId=${evidence.id}`}><ShieldAlert size={14} /> Issues</Link><Link className={contextLink} to={`/audit-engagement-management/findings?engagementId=${engagementId}&evidenceId=${evidence.id}`}><ClipboardCheck size={14} /> Findings</Link><Link className={contextLink} to={`/audit-engagement-management/reports?engagementId=${engagementId}`}><FileCheck2 size={14} /> Reports</Link></div><div className="mt-3 grid gap-2 sm:grid-cols-2"><Info label="Working Paper links" value={(evidence.workingPapers ?? []).map((item) => item.workingPaperCode).join(", ") || "None"} /><Info label="Finding links" value={(evidence.findings ?? []).map((item) => item.findingCode).join(", ") || "None"} /></div></section><section className="rounded-xl border border-slate-200 bg-white p-4"><h4 className="flex items-center gap-2 text-sm font-bold text-slate-800"><GitCompareArrows size={16} className="text-sky-700" /> Evidence version comparison</h4><div className="mt-3 space-y-2">{history.map((item) => <div className={`flex flex-wrap items-center justify-between gap-2 rounded-lg border p-3 text-xs ${item.id === evidence.id ? "border-sky-300 bg-sky-50" : "border-slate-200"}`} key={item.id}><span><strong>Version {item.versionNumber}</strong><span className="ml-2 text-slate-500">{item.fileName || `Core version ${item.documentVersionId}`}</span></span><span className="font-mono text-slate-400">{item.checksumSha256?.slice(0, 16) || "—"}</span></div>)}</div></section></div>;
}

function AssessmentWorkspace({ assessments, evidence, query, setSelectedEvidenceId, setTab }) {
  const normalized = query.trim().toLowerCase();
  const rows = assessments.filter((item) => !normalized || [item.evidenceCode, item.status, item.evidenceGaps, item.limitations].some((value) => String(value ?? "").toLowerCase().includes(normalized)));
  return <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5"><div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"><div><h3 className="text-lg font-bold text-slate-900">Assessment and evidence gaps</h3><p className="mt-1 text-sm text-slate-500">Review sufficiency, restrictions, limitations, and reporting eligibility before Findings are validated.</p></div><span className="text-xs font-bold text-slate-400">{rows.length} assessment{rows.length === 1 ? "" : "s"}</span></div><div className="mt-4 grid gap-3 lg:grid-cols-2">{rows.map((assessment) => { const state = assessmentState({ assessment }); return <button className="rounded-xl border border-slate-200 p-4 text-left transition hover:border-sky-300 hover:bg-sky-50" key={assessment.id} onClick={() => { setSelectedEvidenceId(String(assessment.evidenceId)); setTab("evidence"); }} type="button"><div className="flex flex-wrap items-center justify-between gap-2"><strong className="text-sm text-slate-800">{assessment.evidenceCode}</strong><StatusBadge tone={state.tone}>{state.label}</StatusBadge></div><div className="mt-2 grid gap-2 text-xs text-slate-600 sm:grid-cols-3"><span>Version {assessment.versionNumber}</span><span>Core version #{assessment.documentVersionId}</span><span>Assessed {date(assessment.assessedAt, true)}</span></div>{(assessment.evidenceGaps || assessment.limitations) && <div className="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800"><strong>Evidence gaps / limitations</strong><p className="mt-1">{assessment.evidenceGaps || assessment.limitations}</p></div>}{assessment.isRestricted || assessment.accessRestrictions ? <div className="mt-3 rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-800"><strong>Restricted evidence</strong><p className="mt-1">{assessment.accessRestrictions || "Access restrictions recorded."}</p></div> : null}<span className="mt-3 inline-flex items-center gap-1 text-xs font-bold text-sky-700">Inspect evidence <ArrowRight size={14} /></span></button>; })}{!rows.length && <EmptyPanel icon={BadgeCheck} title="No assessments found" text={evidence.length ? "Select evidence from the register to create an assessment." : "Received evidence assessments will appear here."} />}</div></section>;
}

function AssessmentForm({ form, setForm, errors, evidence }) {
  const fields = ["sufficiency", "appropriateness", "relevance", "reliability", "competence", "accuracy", "completeness", "corroboration", "contradiction", "authenticity", "integrity"];
  return <div className="space-y-4"><div className="grid gap-4 sm:grid-cols-2"><Field label="Evidence"><input className={inputClass} readOnly value={evidence ? `${evidence.evidenceCode} — ${evidence.title}` : "Select evidence from the register"} /></Field><Field label="Exact Core Document Version"><input className={inputClass} readOnly value={form.documentVersionId ? `Version #${form.documentVersionId}` : "—"} /></Field></div><div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">{fields.map((field) => <Field error={errors[field]} label={label(field)} key={field}><select className={inputClass} value={form[field] ?? ""} onChange={(event) => setForm((current) => ({ ...current, [field]: event.target.value }))}>{ratingOptions.map((option) => <option key={option} value={option}>{option ? label(option) : "Not recorded"}</option>)}</select></Field>)}<Field error={errors.confidentiality} label="Confidentiality"><select className={inputClass} value={form.confidentiality} onChange={(event) => setForm((current) => ({ ...current, confidentiality: event.target.value }))}>{["PUBLIC", "INTERNAL", "CONFIDENTIAL", "RESTRICTED"].map((option) => <option key={option}>{option}</option>)}</select></Field></div><div className="grid gap-4 sm:grid-cols-2"><Field error={errors.accessRestrictions} label="Access restrictions" wide><textarea className={textAreaClass} value={form.accessRestrictions} onChange={(event) => setForm((current) => ({ ...current, accessRestrictions: event.target.value }))} /></Field><Field error={errors.limitations} label="Limitations"><textarea className={textAreaClass} value={form.limitations} onChange={(event) => setForm((current) => ({ ...current, limitations: event.target.value }))} /></Field><Field error={errors.evidenceGaps} label="Evidence gaps"><textarea className={textAreaClass} value={form.evidenceGaps} onChange={(event) => setForm((current) => ({ ...current, evidenceGaps: event.target.value }))} /></Field></div><label className="flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700"><input className="mt-1" type="checkbox" checked={form.isRestricted} onChange={(event) => setForm((current) => ({ ...current, isRestricted: event.target.checked }))} /><span><strong className="block">Restricted evidence</strong><span className="text-xs text-slate-500">Restricted or access-limited evidence cannot be used for finalized Findings until a separate exception is approved.</span></span></label><label className="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800"><input className="mt-1" type="checkbox" checked={form.exceptionRequired} onChange={(event) => setForm((current) => ({ ...current, exceptionRequired: event.target.checked }))} /><span><strong className="block">Request exception approval</strong><span className="text-xs">Provide the professional reason; an independent authorized user must decide it.</span></span></label>{form.exceptionRequired && <Field error={errors.exceptionReason} label="Exception reason" wide><textarea className={textAreaClass} required value={form.exceptionReason} onChange={(event) => setForm((current) => ({ ...current, exceptionReason: event.target.value }))} /></Field>}</div>;
}

function AssessmentCard({ assessment }) {
  if (!assessment) return <section className="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-5"><div className="flex items-center gap-2 text-sm font-bold text-slate-700"><BadgeCheck size={17} className="text-slate-400" /> Evidence not assessed</div><p className="mt-2 text-sm text-slate-500">This evidence cannot be accepted for reporting until a current assessment is recorded.</p></section>;
  const state = assessmentState({ assessment });
  return <section className="rounded-xl border border-slate-200 bg-white p-4"><div className="flex flex-wrap items-center justify-between gap-2"><h4 className="flex items-center gap-2 text-sm font-bold text-slate-800"><BadgeCheck size={16} className="text-sky-700" /> Professional assessment</h4><StatusBadge tone={state.tone}>{state.label}</StatusBadge></div><div className="mt-3 grid gap-2 text-xs sm:grid-cols-3"><Info label="Assessment version" value={`v${assessment.versionNumber}`} /><Info label="Assessed by" value={assessment.assessedBy?.name ?? "—"} /><Info label="Assessed at" value={date(assessment.assessedAt, true)} /></div><div className="mt-3 flex flex-wrap gap-2">{["sufficiency", "appropriateness", "relevance", "reliability", "competence", "accuracy", "completeness", "corroboration", "contradiction", "authenticity", "integrity"].map((key) => <span className="rounded-md bg-slate-100 px-2 py-1 text-[11px] font-bold text-slate-600" key={key}>{label(key)}: {assessment[key] || "—"}</span>)}</div>{(assessment.evidenceGaps || assessment.limitations) && <div className="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800"><strong>Evidence gaps / limitations</strong><p className="mt-1">{assessment.evidenceGaps || assessment.limitations}</p></div>}{assessment.isRestricted || assessment.accessRestrictions ? <div className="mt-3 rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-800"><strong>Access restricted</strong><p className="mt-1">{assessment.accessRestrictions || "Restricted evidence."}</p>{assessment.exceptionApprovedAt && <p className="mt-1 font-bold">Exception approved {date(assessment.exceptionApprovedAt, true)}.</p>}</div> : null}</section>;
}

function Info({ label: infoLabel, value, mono = false }) {
  return <div className="min-w-0 rounded-lg bg-slate-50 p-3"><span className="block text-[11px] font-bold uppercase tracking-wide text-slate-400">{infoLabel}</span><span className={`mt-1 block break-words text-sm text-slate-700 ${mono ? "font-mono text-xs" : ""}`}>{value || "—"}</span></div>;
}

function EmptyPanel({ icon: Icon, title, text }) {
  return <div className="grid min-h-64 place-items-center text-center"><div><Icon className="mx-auto text-sky-600" size={38} /><h3 className="mt-3 text-base font-bold text-slate-800">{title}</h3><p className="mt-2 max-w-sm text-sm leading-6 text-slate-500">{text}</p></div></div>;
}
