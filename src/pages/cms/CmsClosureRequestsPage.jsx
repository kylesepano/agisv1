import { useCallback, useEffect, useState } from "react";
import {
  AlertTriangle,
  ArrowLeft,
  CheckCircle2,
  ClipboardCheck,
  Download,
  FileText,
  History,
  Link2,
  RefreshCw,
  Save,
  Send,
  ShieldCheck,
  RotateCcw,
  UserCheck,
  XCircle,
} from "lucide-react";
import { Link, useNavigate, useParams } from "react-router";
import { useAuth } from "../../auth/auth-context";
import { hasPermission } from "../../config/navigation";
import { ApiError, cmsApi } from "../../services/api";
import FormField from "../../components/ui/FormField";
import RegistryHeader from "../../components/ui/RegistryHeader";
import StatusBadge from "../../components/ui/StatusBadge";
import { useToast } from "../../ui/toast-context";

const inputClass = "mt-1 w-full rounded-lg border border-slate-300 bg-white p-2.5 text-sm text-slate-800 outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100 disabled:bg-slate-100";
const textClass = `${inputClass} min-h-28`;
const narrativeFields = [
  ["closureRequestSummary", "Closure request summary"],
  ["implementationBasis", "Implementation basis"],
  ["validatedImplementationSummary", "Validated implementation summary"],
  ["residualMattersSummary", "Residual matters summary"],
  ["residualRiskStatement", "Residual risk statement"],
  ["ongoingMonitoringRequirements", "Ongoing monitoring requirements"],
  ["recordsAndDocumentationSummary", "Records and documentation summary"],
  ["resolvedEscalationSummary", "Resolved escalation summary"],
  ["managementConfirmation", "Management confirmation"],
  ["complianceMonitorRecommendationSummary", "Compliance Monitor recommendation summary"],
  ["noAdditionalEvidenceExplanation", "No-additional-evidence explanation"],
];

const statusMeta = {
  DRAFT: ["Draft", "info"], SUBMITTED: ["Submitted", "warning"], UNDER_REVIEW: ["Under review", "warning"],
  RETURNED: ["Returned for revision", "danger"], FOR_DECISION: ["Awaiting final decision", "warning"],
  APPROVED: ["Approved", "success"], REJECTED: ["Rejected — remains implemented", "danger"],
};

function dateLabel(value, time = false) {
  if (!value) return "Not available";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "Not available";
  return new Intl.DateTimeFormat("en-PH", { dateStyle: "medium", ...(time ? { timeStyle: "short" } : {}) }).format(date);
}

function fieldError(errors, name) {
  const snake = name.replace(/[A-Z]/g, (letter) => `_${letter.toLowerCase()}`);
  const value = errors?.[name] || errors?.[snake];
  return Array.isArray(value) ? value[0] : value || "";
}

function ClosureStatus({ status }) {
  const [label, tone] = statusMeta[status] || [status || "Unknown", "inactive"];
  return <StatusBadge tone={tone}>{label}</StatusBadge>;
}

function Field({ name, label, value, onChange, error, disabled, required = false }) {
  return <FormField error={error} htmlFor={`closure-${name}`} label={label} required={required}><textarea className={textClass} disabled={disabled} id={`closure-${name}`} onChange={(event) => onChange(name, event.target.value)} value={value || ""} /></FormField>;
}

function Loading() {
  return <div className="grid gap-4" aria-label="Loading closure workspace"><div className="h-24 animate-pulse rounded-xl bg-slate-200" /><div className="h-64 animate-pulse rounded-xl bg-slate-200" /></div>;
}

function EmptyState({ onCreate, canCreate, reasons = [] }) {
  return <section className="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center"><ClipboardCheck className="mx-auto text-sky-600" size={30} /><h2 className="mt-3 text-lg font-bold text-slate-800">No Closure Requests</h2><p className="mx-auto mt-2 max-w-xl text-sm leading-6 text-slate-500">A formal Closure Request requires a finalized independent Validation conclusion of IMPLEMENTED. Management-reported completion is not formal closure.</p>{canCreate ? <button className="mt-5 inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white" onClick={onCreate} type="button"><ClipboardCheck size={16} /> Create Closure Request</button> : reasons.length > 0 && <div className="mx-auto mt-5 max-w-xl rounded-lg border border-amber-200 bg-amber-50 p-3 text-left text-sm text-amber-900"><strong>Creation unavailable</strong><ul className="mt-2 list-disc space-y-1 pl-5">{reasons.map((reason) => <li key={reason}>{reason}</li>)}</ul></div>}</section>;
}

function ReadinessPanel({ readiness }) {
  const checklist = readiness?.checklist || [];
  return <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><div className="flex items-start gap-3"><span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-sky-100 text-sky-700"><ShieldCheck size={20} /></span><div><h2 className="font-bold text-slate-800">Closure Readiness</h2><p className="mt-1 text-sm text-slate-500">These backend-evaluated criteria are authoritative. Failed blocking criteria cannot be overridden in the client.</p></div></div>{checklist.length === 0 ? <p className="mt-4 text-sm text-slate-500">Readiness information is unavailable in the current scope.</p> : <ul className="mt-4 grid gap-2">{checklist.map((item) => <li className={`flex items-start gap-3 rounded-lg border p-3 ${item.passed ? "border-emerald-200 bg-emerald-50" : item.blocking ? "border-red-200 bg-red-50" : "border-amber-200 bg-amber-50"}`} key={item.code}><span className={`mt-0.5 ${item.passed ? "text-emerald-700" : item.blocking ? "text-red-700" : "text-amber-700"}`}>{item.passed ? <CheckCircle2 size={17} /> : <AlertTriangle size={17} />}</span><div className="min-w-0"><p className="text-sm font-bold text-slate-800">{item.label}{item.blocking && !item.passed ? <span className="ml-2 text-xs font-semibold uppercase text-red-700">Blocking</span> : null}</p><p className="mt-1 text-xs leading-5 text-slate-600">{item.explanation}</p></div></li>)}</ul>}</section>;
}

function Context({ context, request, version }) {
  const closed = context?.status === "CLOSED";
  if (closed) {
    return <section className="rounded-xl border border-emerald-300 bg-emerald-50 p-4 shadow-sm"><div className="flex flex-wrap items-start justify-between gap-3"><div><p className="text-xs font-bold uppercase tracking-wide text-sky-700">{request?.displayCode || "Closure workspace"}</p><h2 className="mt-1 text-xl font-bold text-slate-900">Formally closed</h2><p className="mt-1 text-sm leading-6 text-slate-600">This recommendation is formally closed. Any reopening must use the separate controlled Reopening workspace.</p></div><ClosureStatus status={version?.status} /></div><dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4"><div><dt className="text-xs font-bold uppercase text-slate-500">Case status</dt><dd className="mt-1 font-semibold text-slate-800">{context?.status}</dd></div><div><dt className="text-xs font-bold uppercase text-slate-500">Responsible office</dt><dd className="mt-1 text-slate-700">{context?.responsibleOffice?.name || "Not available"}</dd></div><div><dt className="text-xs font-bold uppercase text-slate-500">Request version</dt><dd className="mt-1 text-slate-700">V{version?.versionNumber || "-"}</dd></div><div><dt className="text-xs font-bold uppercase text-slate-500">Closed date</dt><dd className="mt-1 text-slate-700">{dateLabel(context?.closedAt)}</dd></div></dl></section>;
  }
  return <section className={`rounded-xl border p-4 shadow-sm ${closed ? "border-emerald-300 bg-emerald-50" : "border-slate-200 bg-white"}`}><div className="flex flex-wrap items-start justify-between gap-3"><div><p className="text-xs font-bold uppercase tracking-wide text-sky-700">{request?.displayCode || "Closure workspace"}</p><h2 className="mt-1 text-xl font-bold text-slate-900">{closed ? "Formally closed" : context?.status === "FOR_CLOSURE" ? "Closure request in progress" : "Eligible for formal closure"}</h2><p className="mt-1 text-sm text-slate-600">{closed ? "This recommendation is formally closed. Any reopening must use the separate controlled Reopening workspace." : "Independently validated as implemented does not by itself close the recommendation."}</p></div><ClosureStatus status={version?.status} /></div><dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4"><div><dt className="text-xs font-bold uppercase text-slate-500">Case status</dt><dd className="mt-1 font-semibold text-slate-800">{context?.status || "Not available"}</dd></div><div><dt className="text-xs font-bold uppercase text-slate-500">Responsible office</dt><dd className="mt-1 text-slate-700">{context?.responsibleOffice?.name || "Not available"}</dd></div><div><dt className="text-xs font-bold uppercase text-slate-500">Request version</dt><dd className="mt-1 text-slate-700">V{version?.versionNumber || "—"}</dd></div><div><dt className="text-xs font-bold uppercase text-slate-500">Closed date</dt><dd className="mt-1 text-slate-700">{dateLabel(context?.closedAt)}</dd></div></dl></section>;
}

function VersionCard({ version, onSelect, selected }) {
  return <button className={`w-full rounded-xl border p-4 text-left transition hover:border-sky-300 hover:shadow-sm ${selected ? "border-sky-500 bg-sky-50" : "border-slate-200 bg-white"}`} onClick={onSelect} type="button"><div className="flex flex-wrap items-center justify-between gap-2"><span className="font-bold text-slate-800">Version {version.versionNumber}</span><ClosureStatus status={version.status} /></div><p className="mt-2 text-xs text-slate-500">Prepared {dateLabel(version.submittedAt || version.reviewStartedAt || version.returnedAt, true)}</p></button>;
}

export default function CmsClosureRequestsPage() {
  const { recommendationId, closureRequestId } = useParams();
  const navigate = useNavigate();
  const { user } = useAuth();
  const toast = useToast();
  const detailMode = Boolean(closureRequestId);
  const canRequest = hasPermission(user, "cms.closure.request");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [listData, setListData] = useState(null);
  const [options, setOptions] = useState(null);
  const [detail, setDetail] = useState(null);
  const [caseContext, setCaseContext] = useState(null);
  const [tab, setTab] = useState("overview");
  const [form, setForm] = useState({});
  const [errors, setErrors] = useState({});
  const [busy, setBusy] = useState(false);
  const [workflow, setWorkflow] = useState(null);
  const [workflowValues, setWorkflowValues] = useState({ comment: "", recommendationCode: "RECOMMEND_APPROVAL", confirmation: false, overrideReason: "" });
  const [conflict, setConflict] = useState("");

  const currentVersion = detail?.currentVersion || detail?.versions?.find((version) => version.isCurrent) || detail?.versions?.[0];
  const actions = currentVersion?.availableActions || detail?.availableActions || [];
  const readOnly = !currentVersion || currentVersion.status !== "DRAFT" || !actions.includes("update");
  const readiness = options?.readiness;

  const loadList = useCallback(async () => {
    setLoading(true); setError("");
    try { const [requests, closureOptions] = await Promise.all([cmsApi.getClosureRequests(recommendationId), canRequest ? cmsApi.getClosureOptions(recommendationId).catch(() => null) : Promise.resolve(null)]); setListData(requests); setOptions(closureOptions); setCaseContext(requests?.caseContext || closureOptions?.caseContext); }
    catch (requestError) { setError(requestError.status === 404 ? "This recommendation or its Closure Requests are unavailable or outside your authorized scope." : requestError.message || "The Closure Requests could not be loaded."); }
    finally { setLoading(false); }
  }, [canRequest, recommendationId]);

  const loadDetail = useCallback(async () => {
    setLoading(true); setError("");
    try { const [result, closureOptions] = await Promise.all([cmsApi.getClosureRequest(closureRequestId), canRequest ? cmsApi.getClosureOptions(recommendationId).catch(() => null) : Promise.resolve(null)]); setDetail(result?.request || null); setCaseContext(result?.caseContext || null); setOptions(closureOptions); const version = result?.request?.currentVersion; setForm(version?.narratives || {}); }
    catch (requestError) { setError(requestError.status === 404 ? "This Closure Request is unavailable or outside your authorized scope." : requestError.message || "The Closure Request could not be loaded."); }
    finally { setLoading(false); }
  }, [canRequest, closureRequestId, recommendationId]);

  // Loading is an intentional synchronization with the route's authoritative API state.
  // eslint-disable-next-line react-hooks/set-state-in-effect
  useEffect(() => { if (detailMode) loadDetail(); else loadList(); }, [detailMode, loadDetail, loadList]);

  function setField(name, value) { setForm((current) => ({ ...current, [name]: value })); }
  async function createRequest() {
    if (!options?.canCreate || busy) return;
    setBusy(true); setErrors({});
    try { const created = await cmsApi.createClosureRequest(recommendationId, { initiatorTypeCode: options.initiatorTypes?.[0] }); toast.success("Closure Request draft created."); navigate(`/compliance-management/recommendations/${recommendationId}/closure-requests/${created.id}`); }
    catch (requestError) { setErrors(requestError.errors || {}); toast.error(requestError.message || "The Closure Request could not be created."); }
    finally { setBusy(false); }
  }

  async function saveDraft() {
    if (!detail || !currentVersion || busy) return;
    setBusy(true); setErrors({}); setConflict("");
    try { const saved = await cmsApi.updateClosureRequest(detail.id, currentVersion.id, { ...form, lockVersion: currentVersion.lockVersion }); setDetail(saved); setForm(saved.currentVersion?.narratives || form); toast.success("Closure draft saved."); }
    catch (requestError) { setErrors(requestError.errors || {}); if (requestError instanceof ApiError && requestError.status === 409) { setConflict("This draft changed elsewhere. Your local values are preserved; reload the latest version before retrying."); } toast.error(requestError.message || "The Closure draft could not be saved."); }
    finally { setBusy(false); }
  }

  async function runWorkflow() {
    if (!detail || !currentVersion || busy || !workflow) return;
    const values = workflowValues;
    const required = ["return", "recommend", "approve", "reject", "revise"].includes(workflow);
    if (required && !values.comment.trim()) { setErrors({ comment: ["A comment is required."] }); return; }
    if (!values.confirmation) { setErrors({ confirmation: ["Confirmation is required."] }); return; }
    setBusy(true); setErrors({});
    try {
      const payload = { lockVersion: currentVersion.lockVersion, confirmation: true };
      let saved;
      if (workflow === "submit") saved = await cmsApi.submitClosureRequest(detail.id, currentVersion.id, payload);
      if (workflow === "start-review") saved = await cmsApi.startClosureReview(detail.id, currentVersion.id, { ...payload, reviewComment: values.comment || null });
      if (workflow === "return") saved = await cmsApi.returnClosureRequest(detail.id, currentVersion.id, { ...payload, returnReason: values.comment });
      if (workflow === "recommend") saved = await cmsApi.recommendClosure(detail.id, currentVersion.id, { ...payload, recommendationCode: values.recommendationCode, readinessSummary: values.comment, validationLineageAssessment: values.validationLineageAssessment, documentAndEvidenceAssessment: values.documentAndEvidenceAssessment, residualMatterAssessment: values.residualMatterAssessment, escalationAndExtensionAssessment: values.escalationAndExtensionAssessment, recordsCompletenessAssessment: values.recordsCompletenessAssessment, conditionsOrObservations: values.conditionsOrObservations || null });
      if (workflow === "approve") saved = await cmsApi.approveClosure(detail.id, currentVersion.id, { ...payload, decisionComment: values.comment, overrideReason: values.overrideReason || null });
      if (workflow === "reject") saved = await cmsApi.rejectClosure(detail.id, currentVersion.id, { ...payload, decisionComment: values.comment, overrideReason: values.overrideReason || null });
      if (workflow === "revise") saved = await cmsApi.createClosureRevision(detail.id, currentVersion.id, { ...payload, revisionReason: values.comment });
      setDetail(saved); setCaseContext((current) => ({ ...current, status: workflow === "approve" ? "CLOSED" : workflow === "reject" ? "IMPLEMENTED" : current?.status === "IMPLEMENTED" && workflow === "submit" ? "FOR_CLOSURE" : current?.status })); setWorkflow(null); setWorkflowValues({ comment: "", recommendationCode: "RECOMMEND_APPROVAL", confirmation: false, overrideReason: "" }); toast.success(workflow === "approve" ? "Recommendation formally closed." : workflow === "reject" ? "Closure rejected; recommendation remains implemented." : "Closure workflow action completed.");
    } catch (requestError) { setErrors(requestError.errors || {}); if (requestError instanceof ApiError && requestError.status === 409) setConflict("The authoritative Closure Request changed. Reload latest before retrying this action."); toast.error(requestError.message || "The Closure workflow action could not be completed."); }
    finally { setBusy(false); }
  }

  function openWorkflow(action) { setErrors({}); setWorkflow(action); setWorkflowValues({ comment: "", recommendationCode: "RECOMMEND_APPROVAL", confirmation: false, overrideReason: "" }); }

  if (loading && !(detailMode ? detail : listData)) return <main className="mx-auto max-w-[1500px] p-4 sm:p-6"><Loading /></main>;
  if (error && !(detailMode ? detail : listData)) return <main className="mx-auto max-w-[1500px] p-4 sm:p-6"><section className="rounded-xl border border-red-200 bg-red-50 p-8 text-center text-red-800"><AlertTriangle className="mx-auto" /><p className="mt-3 font-semibold">{error}</p><button className="mt-4 inline-flex h-10 items-center gap-2 rounded-lg bg-red-700 px-4 text-sm font-bold text-white" onClick={detailMode ? loadDetail : loadList} type="button"><RefreshCw size={15} /> Retry</button></section></main>;

  if (!detailMode) {
    const requests = listData?.requests || [];
    return <main className="mx-auto max-w-[1500px] p-4 sm:p-6"><RegistryHeader icon={ClipboardCheck} eyebrow="Compliance Management" title="Recommendation Closure" description="Prepare, review, and decide formal recommendation closure. Independent implementation is not formal closure." actions={<button className="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700" onClick={loadList} type="button"><RefreshCw size={15} /> Refresh</button>} /><div className="mt-4 grid gap-4 lg:grid-cols-[1.25fr_1fr]"><Context context={caseContext} /><ReadinessPanel readiness={readiness} /></div><section className="mt-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><div className="mb-4 flex flex-wrap items-center justify-between gap-3"><div><h2 className="text-lg font-bold text-slate-800">Closure Request history</h2><p className="mt-1 text-sm text-slate-500">{requests.length} request family{requests.length === 1 ? "" : "ies"}; historical versions remain immutable.</p></div>{options?.canCreate && canRequest && <button className="inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white" disabled={busy} onClick={createRequest} type="button"><ClipboardCheck size={16} /> Create Closure Request</button>}</div>{requests.length === 0 ? <EmptyState canCreate={Boolean(options?.canCreate && canRequest)} onCreate={createRequest} reasons={options?.reasons || []} /> : <div className="grid gap-3">{requests.map((request) => <Link className="rounded-xl border border-slate-200 bg-white p-4 transition hover:-translate-y-0.5 hover:border-sky-300 hover:shadow-md" key={request.id} to={`/compliance-management/recommendations/${recommendationId}/closure-requests/${request.id}`}><div className="flex flex-wrap items-start justify-between gap-3"><div><p className="text-xs font-bold uppercase tracking-wide text-sky-700">{request.displayCode}</p><h3 className="mt-1 font-bold text-slate-800">Request sequence {request.requestSequence}</h3><p className="mt-1 text-xs text-slate-500">Initiated by {request.initiatorTypeCode === "RESPONSIBLE_OFFICE" ? "Responsible Office" : "Compliance Monitor"}</p></div><ClosureStatus status={request.currentVersion?.status} /></div><dl className="mt-4 grid gap-3 text-xs text-slate-600 sm:grid-cols-2 lg:grid-cols-4"><div><dt className="font-bold text-slate-500">Validation version</dt><dd className="mt-1">{request.currentVersion?.source?.validationVersionId || "Not available"}</dd></div><div><dt className="font-bold text-slate-500">Current version</dt><dd className="mt-1">V{request.currentVersion?.versionNumber || "—"}</dd></div><div><dt className="font-bold text-slate-500">Decision</dt><dd className="mt-1">{request.currentVersion?.decision?.decisionCode || "Not decided"}</dd></div><div><dt className="font-bold text-slate-500">Resolved</dt><dd className="mt-1">{request.isResolved ? dateLabel(request.resolvedAt) : "Open"}</dd></div></dl></Link>)}</div>}</section></main>;
  }

  const versionList = detail?.versions || (currentVersion ? [currentVersion] : []);
  const evidence = currentVersion?.evidence || [];
  const closed = caseContext?.status === "CLOSED";

  return (
    <main className="mx-auto max-w-[1500px] p-4 sm:p-6">
      <RegistryHeader
        icon={ClipboardCheck}
        eyebrow="Recommendation Closure"
        title={detail?.displayCode || "Closure Request"}
        description="A formal closure decision is required after independent validation."
        actions={<div className="flex flex-wrap gap-2"><Link className="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700" to={`/compliance-management/recommendations/${recommendationId}/closure-requests`}><ArrowLeft size={15} /> All requests</Link><button className="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700" onClick={loadDetail} type="button"><RefreshCw size={15} /> Refresh</button></div>}
      />
      <div className="mt-4"><Context context={caseContext} request={detail} version={currentVersion} /></div>
      {conflict && <div className="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900" role="alert"><strong>Reload required:</strong> {conflict}</div>}
      <div className="mt-4 flex gap-2 overflow-x-auto border-b border-slate-200" role="tablist">
        {[["overview", "Overview", FileText], ["readiness", "Closure Readiness", ShieldCheck], ["lineage", "Source & Lineage", Link2], ["evidence", "Closure Evidence", FileText], ["assessment", "Review Assessment", UserCheck], ["decision", "Final Decision", CheckCircle2], ["history", "Versions & History", History]].map(([key, label, Icon]) => <button className={`inline-flex shrink-0 items-center gap-2 border-b-2 px-3 py-3 text-sm font-bold ${tab === key ? "border-sky-600 text-sky-700" : "border-transparent text-slate-500 hover:text-slate-800"}`} key={key} onClick={() => setTab(key)} role="tab" type="button"><Icon size={15} />{label}</button>)}
      </div>
      <section className="mt-4">
        {tab === "overview" && <div className="grid gap-4 lg:grid-cols-[1.4fr_1fr]"><section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><div className="mb-4 flex flex-wrap items-center justify-between gap-2"><div><h2 className="text-lg font-bold text-slate-800">Closure narrative</h2><p className="mt-1 text-sm text-slate-500">Only draft versions can be edited.</p></div>{!readOnly && !closed && <button className="inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white" disabled={busy} onClick={saveDraft} type="button"><Save size={15} /> Save draft</button>}</div><div className="grid gap-4 md:grid-cols-2">{narrativeFields.map(([name, label]) => <Field disabled={readOnly || closed || busy} error={fieldError(errors, name)} key={name} label={label} name={name} onChange={setField} required={["closureRequestSummary", "implementationBasis", "validatedImplementationSummary", "recordsAndDocumentationSummary", "noAdditionalEvidenceExplanation"].includes(name)} value={form[name]} />)}</div></section><aside className="grid content-start gap-4"><ReadinessPanel readiness={readiness} /><section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><h2 className="font-bold text-slate-800">Source validation</h2><p className="mt-2 text-sm text-slate-600">Validation Version <strong>{currentVersion?.source?.validationVersionId || "Not available"}</strong> · conclusion <strong>IMPLEMENTED</strong>.</p><p className="mt-2 text-xs leading-5 text-slate-500">The finalized Validation, accepted Action Plan, and recorded Progress Update are selected by the backend and cannot be replaced here.</p></section></aside></div>}
        {tab === "readiness" && <ReadinessPanel readiness={readiness} />}
        {tab === "lineage" && <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><h2 className="text-lg font-bold text-slate-800">Immutable source lineage</h2><dl className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4"><div><dt className="text-xs font-bold uppercase text-slate-500">Validation Review</dt><dd className="mt-1 text-sm">{currentVersion?.source?.validationReviewId || "Not available"}</dd></div><div><dt className="text-xs font-bold uppercase text-slate-500">Validation Version</dt><dd className="mt-1 text-sm">{currentVersion?.source?.validationVersionId || "Not available"}</dd></div><div><dt className="text-xs font-bold uppercase text-slate-500">Accepted Action Plan</dt><dd className="mt-1 text-sm">{currentVersion?.source?.actionPlanVersionId || "Not available"}</dd></div><div><dt className="text-xs font-bold uppercase text-slate-500">Recorded Progress Update</dt><dd className="mt-1 text-sm">{currentVersion?.source?.progressUpdateVersionId || "Not available"}</dd></div></dl><p className="mt-5 rounded-lg border border-sky-200 bg-sky-50 p-3 text-sm text-sky-900">Independent validation conclusion: <strong>IMPLEMENTED</strong>. This is not the final Closure Decision.</p></section>}
        {tab === "evidence" && <section className="grid gap-4"><section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><h2 className="text-lg font-bold text-slate-800">Closure Evidence</h2><p className="mt-1 text-sm text-slate-500">Closure evidence references exact Core Document Versions. Internal storage paths are never displayed.</p>{!readOnly && !closed && actions.includes("update") && <EvidenceForm detail={detail} version={currentVersion} busy={busy} onSaved={(saved) => setDetail(saved)} />}</section>{evidence.length === 0 ? <div className="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">No closure evidence is linked to this version.</div> : <div className="grid gap-3">{evidence.map((item) => <article className="rounded-xl border border-slate-200 bg-white p-4" key={item.id}><div className="flex flex-wrap items-start justify-between gap-3"><div><h3 className="font-bold text-slate-800">{item.title}</h3><p className="mt-1 text-xs text-slate-500">{item.evidenceCategory} · {item.sourceOrCustodian || "Custodian not recorded"}</p></div><button className="inline-flex h-9 items-center gap-2 rounded-lg border border-slate-300 px-3 text-xs font-bold text-slate-700" onClick={() => cmsApi.downloadClosureEvidence(item.id, item.title).catch((requestError) => toast.error(requestError.message || "Evidence download failed."))} type="button"><Download size={14} /> Protected download</button></div><dl className="mt-4 grid gap-3 text-xs text-slate-600 sm:grid-cols-2 lg:grid-cols-4"><div><dt className="font-bold text-slate-500">Core version</dt><dd className="mt-1">{item.documentVersionId || "Not available"}</dd></div><div><dt className="font-bold text-slate-500">Checksum</dt><dd className="mt-1 break-all font-mono">{item.checksumSha256 || "Not available"}</dd></div><div><dt className="font-bold text-slate-500">Confidentiality</dt><dd className="mt-1">{item.confidentialityCodeSnapshot || "Not available"}</dd></div><div><dt className="font-bold text-slate-500">Linked</dt><dd className="mt-1">{dateLabel(item.linkedAt, true)}</dd></div></dl></article>)}</div>}</section>}
        {tab === "assessment" && <AssessmentPanel assessment={currentVersion?.assessment} />}
        {tab === "decision" && <DecisionPanel version={currentVersion} />}
        {tab === "history" && <div className="grid gap-3">{versionList.map((version) => <VersionCard key={version.id} selected={version.id === currentVersion?.id} version={version} onSelect={() => setDetail((current) => ({ ...current, currentVersion: version }))} />)}</div>}
      </section>
      <WorkflowBar actions={actions} closed={closed} busy={busy} onAction={openWorkflow} />
      <WorkflowDialog action={workflow} values={workflowValues} setValues={setWorkflowValues} errors={errors} busy={busy} onCancel={() => setWorkflow(null)} onConfirm={runWorkflow} />
    </main>
  );
}

function EvidenceForm({ detail, version, busy, onSaved }) {
  const [values, setValues] = useState({ documentVersionId: "", title: "", evidenceCategory: "CLOSURE_SUPPORT", description: "", sourceOrCustodian: "" });
  const toast = useToast();
  async function submit() { const data = new FormData(); Object.entries({ ...values, lockVersion: version.lockVersion }).forEach(([key, value]) => data.set(key, value)); try { onSaved(await cmsApi.uploadClosureEvidence(detail.id, version.id, data)); setValues({ documentVersionId: "", title: "", evidenceCategory: "CLOSURE_SUPPORT", description: "", sourceOrCustodian: "" }); toast.success("Core Document Version linked to the draft."); } catch (requestError) { toast.error(requestError.message || "Closure evidence could not be linked."); } }
  return <div className="mt-4 grid gap-3 rounded-lg border border-sky-200 bg-sky-50 p-3 md:grid-cols-2"><FormField htmlFor="closure-document-version" label="Core Document Version ID" required><input className={inputClass} id="closure-document-version" onChange={(event) => setValues({ ...values, documentVersionId: event.target.value })} value={values.documentVersionId} /></FormField><FormField htmlFor="closure-evidence-title" label="Evidence title" required><input className={inputClass} id="closure-evidence-title" onChange={(event) => setValues({ ...values, title: event.target.value })} value={values.title} /></FormField><FormField htmlFor="closure-evidence-category" label="Category"><input className={inputClass} id="closure-evidence-category" onChange={(event) => setValues({ ...values, evidenceCategory: event.target.value })} value={values.evidenceCategory} /></FormField><FormField htmlFor="closure-evidence-source" label="Source or custodian"><input className={inputClass} id="closure-evidence-source" onChange={(event) => setValues({ ...values, sourceOrCustodian: event.target.value })} value={values.sourceOrCustodian} /></FormField><FormField htmlFor="closure-evidence-description" label="Description"><textarea className={textClass} id="closure-evidence-description" onChange={(event) => setValues({ ...values, description: event.target.value })} value={values.description} /></FormField><div className="flex items-end"><button className="inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white disabled:opacity-60" disabled={busy || !values.documentVersionId || !values.title.trim()} onClick={submit} type="button"><Link2 size={15} /> Link Core version</button></div></div>;
}

function AssessmentPanel({ assessment }) {
  if (!assessment) return <section className="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">No independent Closure Review Assessment has been recorded.</section>;
  return <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><div className="flex flex-wrap items-center justify-between gap-3"><h2 className="text-lg font-bold text-slate-800">Independent review assessment</h2><StatusBadge tone={assessment.recommendationCode === "RECOMMEND_APPROVAL" ? "success" : "danger"}>{assessment.recommendationCode === "RECOMMEND_APPROVAL" ? "Recommend approval" : "Recommend rejection"}</StatusBadge></div><p className="mt-2 text-sm text-slate-500">This professional recommendation is not the final Closure Decision.</p><dl className="mt-4 grid gap-4 md:grid-cols-2">{[["Readiness summary", assessment.readinessSummary], ["Validation lineage", assessment.validationLineageAssessment], ["Document and evidence", assessment.documentAndEvidenceAssessment], ["Residual matters", assessment.residualMatterAssessment], ["Extensions and escalations", assessment.escalationAndExtensionAssessment], ["Records completeness", assessment.recordsCompletenessAssessment], ["Conditions or observations", assessment.conditionsOrObservations]].map(([label, value]) => <div className="rounded-lg border border-slate-200 bg-slate-50 p-3" key={label}><dt className="text-xs font-bold uppercase text-slate-500">{label}</dt><dd className="mt-1 whitespace-pre-wrap text-sm leading-6 text-slate-700">{value || "Not recorded"}</dd></div>)}</dl></section>;
}

function DecisionPanel({ version }) {
  if (!version?.decision) return <section className="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">No final Closure Decision has been recorded.</section>;
  const approved = version.decision.decisionCode === "APPROVED";
  return <section className={`rounded-xl border p-5 ${approved ? "border-emerald-300 bg-emerald-50" : "border-red-200 bg-red-50"}`}><div className="flex items-center gap-3">{approved ? <CheckCircle2 className="text-emerald-700" /> : <XCircle className="text-red-700" />}<div><h2 className="text-lg font-bold text-slate-800">Closure {approved ? "Approved" : "Rejected"}</h2><p className="mt-1 text-sm text-slate-600">{approved ? "The recommendation is formally closed." : "The recommendation remains independently validated as implemented, but is not formally closed."}</p></div></div><dl className="mt-5 grid gap-4 sm:grid-cols-2"><div><dt className="text-xs font-bold uppercase text-slate-500">Decision date</dt><dd className="mt-1 text-sm">{dateLabel(version.decision.decidedAt, true)}</dd></div><div><dt className="text-xs font-bold uppercase text-slate-500">Final case status</dt><dd className="mt-1 text-sm font-bold">{version.decision.newCaseStatus}</dd></div><div className="sm:col-span-2"><dt className="text-xs font-bold uppercase text-slate-500">Decision comment</dt><dd className="mt-1 whitespace-pre-wrap text-sm leading-6">{version.decision.decisionComment}</dd></div></dl>{approved && <p className="mt-4 rounded-lg border border-emerald-200 bg-white/70 p-3 text-sm text-emerald-900">Any reopening must use the separate controlled Reopening workspace; the historical closure decision remains immutable.</p>}</section>;
}

function WorkflowBar({ actions, closed, busy, onAction }) {
  if (closed) return <div className="mt-5 rounded-xl border border-emerald-300 bg-emerald-50 p-4 text-sm text-emerald-900"><strong>This recommendation is formally closed.</strong> Historical records remain available according to scope. Any reopening must use the separate controlled Reopening workspace.</div>;
  const labels = { update: ["Save draft", Save], submit: ["Submit for closure", Send], "start-review": ["Start review", UserCheck], return: ["Return", RotateCcw], recommend: ["Record assessment", ClipboardCheck], approve: ["Approve closure", CheckCircle2], reject: ["Reject closure", XCircle], revise: ["Create revision", RotateCcw] };
  return <div className="sticky bottom-3 z-10 mt-5 flex flex-wrap gap-2 rounded-xl border border-slate-200 bg-white/95 p-3 shadow-lg backdrop-blur">{actions.map((action) => { const [label, Icon] = labels[action] || [action, FileText]; return <button className={`inline-flex h-10 items-center gap-2 rounded-lg px-4 text-sm font-bold ${["approve", "submit", "recommend"].includes(action) ? "bg-sky-700 text-white" : action === "reject" ? "border border-red-300 text-red-700" : "border border-slate-300 text-slate-700"}`} disabled={busy} key={action} onClick={() => onAction(action)} type="button"><Icon size={15} />{label}</button>; })}</div>;
}

function WorkflowDialog({ action, values, setValues, errors, busy, onCancel, onConfirm }) {
  if (!action) return null;
  const assessment = action === "recommend";
  const required = ["return", "recommend", "approve", "reject", "revise"].includes(action);
  const title = { submit: "Submit Closure Request", "start-review": "Start independent Closure Review", return: "Return Closure Request", recommend: "Record Closure Review Assessment", approve: "Approve formal closure", reject: "Reject formal closure", revise: "Create Closure revision" }[action] || "Closure workflow action";
  return <div aria-label={`${title} dialog`} className="fixed inset-0 z-50 grid place-items-center bg-slate-900/40 p-4"><div className="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-5 shadow-2xl"><div className="flex items-start justify-between gap-4"><div><h2 className="text-lg font-bold text-slate-900">{title}</h2><p className="mt-1 text-sm text-slate-500">The backend remains authoritative and the current lock version will be sent.</p></div><button aria-label="Close dialog" className="text-slate-500" onClick={onCancel} type="button">×</button></div><div className="mt-5 grid gap-4">{assessment && <FormField htmlFor="closure-recommendation" label="Review recommendation" required><select className={inputClass} id="closure-recommendation" onChange={(event) => setValues({ ...values, recommendationCode: event.target.value })} value={values.recommendationCode}><option value="RECOMMEND_APPROVAL">Recommend approval</option><option value="RECOMMEND_REJECTION">Recommend rejection</option></select></FormField>}<FormField error={errors.comment?.[0]} htmlFor="closure-workflow-comment" label={action === "return" ? "Return reason" : action === "revise" ? "Revision reason" : action === "reject" ? "Decision comment" : "Professional comment"} required={required}><textarea className={textClass} id="closure-workflow-comment" onChange={(event) => setValues({ ...values, comment: event.target.value })} value={values.comment} /></FormField>{assessment && <div className="grid gap-3 md:grid-cols-2">{[["validationLineageAssessment", "Validation lineage assessment"], ["documentAndEvidenceAssessment", "Document and evidence assessment"], ["residualMatterAssessment", "Residual matter assessment"], ["escalationAndExtensionAssessment", "Escalation and extension assessment"], ["recordsCompletenessAssessment", "Records completeness assessment"], ["conditionsOrObservations", "Conditions or observations"]].map(([name, label]) => <FormField htmlFor={`closure-${name}`} key={name} label={label} required={!name.includes("conditions")}><textarea className={textClass} id={`closure-${name}`} onChange={(event) => setValues({ ...values, [name]: event.target.value })} value={values[name] || ""} /></FormField>)}</div>}{["approve", "reject"].includes(action) && <FormField htmlFor="closure-override" label="Override reason when decision differs from review recommendation"><textarea className={textClass} id="closure-override" onChange={(event) => setValues({ ...values, overrideReason: event.target.value })} value={values.overrideReason} /></FormField>}<label className="flex items-start gap-2 text-sm text-slate-700"><input checked={values.confirmation} onChange={(event) => setValues({ ...values, confirmation: event.target.checked })} type="checkbox" /> <span>I confirm this action and understand that formal closure is distinct from independent validation.</span></label>{errors.confirmation && <p className="text-sm font-semibold text-red-700">{errors.confirmation[0]}</p>}</div><div className="mt-6 flex justify-end gap-2"><button className="h-10 rounded-lg border border-slate-300 px-4 text-sm font-bold text-slate-700" disabled={busy} onClick={onCancel} type="button">Cancel</button><button className="h-10 rounded-lg bg-sky-700 px-5 text-sm font-bold text-white disabled:opacity-60" disabled={busy} onClick={onConfirm} type="button">{busy ? "Saving..." : "Confirm"}</button></div></div></div>;
}
