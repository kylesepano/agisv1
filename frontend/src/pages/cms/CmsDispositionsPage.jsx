import { useCallback, useEffect, useState } from "react";
import {
  AlertTriangle,
  ArrowLeft,
  CheckCircle2,
  ClipboardCheck,
  Download,
  FilePenLine,
  History,
  Link2,
  RefreshCw,
  Save,
  Send,
  ShieldAlert,
  ShieldCheck,
  Upload,
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
const sharedFields = [
  ["dispositionSummary", "Disposition summary"],
  ["basisAndCriteria", "Basis and criteria"],
  ["riskImpactAssessment", "Risk impact assessment"],
  ["managementPosition", "Management position"],
  ["responsibleOfficeConfirmation", "Responsible-office confirmation"],
  ["noAdditionalEvidenceExplanation", "No-additional-evidence explanation"],
];

const statusMeta = {
  DRAFT: ["Draft", "info"],
  SUBMITTED: ["Submitted", "warning"],
  UNDER_REVIEW: ["Under review", "warning"],
  RETURNED: ["Returned for revision", "danger"],
  FOR_DECISION: ["Awaiting final decision", "warning"],
  APPROVED: ["Approved", "success"],
  REJECTED: ["Rejected", "danger"],
};

function value(obj, ...keys) {
  for (const key of keys) {
    if (obj?.[key] !== undefined && obj?.[key] !== null) return obj[key];
  }
  return null;
}

function dateLabel(raw, time = false) {
  if (!raw) return "Not available";
  const date = new Date(raw);
  if (Number.isNaN(date.getTime())) return "Not available";
  return new Intl.DateTimeFormat("en-PH", { dateStyle: "medium", ...(time ? { timeStyle: "short" } : {}) }).format(date);
}

function statusLabel(status) {
  return statusMeta[status]?.[0] || status || "Unknown";
}

function DispositionStatus({ status }) {
  const [label, tone] = statusMeta[status] || [statusLabel(status), "inactive"];
  return <StatusBadge tone={tone}>{label}</StatusBadge>;
}

function DispositionType({ code, large = false }) {
  const accepted = code === "ACCEPTED_RISK";
  return <span className={`inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-bold ${accepted ? "bg-amber-100 text-amber-800" : "bg-violet-100 text-violet-800"}`}><span aria-hidden="true">{accepted ? "◈" : "◇"}</span>{large ? accepted ? "Accepted Risk" : "No Longer Applicable" : code || "Disposition"}</span>;
}

function LoadingState() {
  return <div aria-label="Loading disposition workspace" className="grid gap-4"><div className="h-24 animate-pulse rounded-xl bg-slate-200" /><div className="h-64 animate-pulse rounded-xl bg-slate-200" /></div>;
}

function ErrorState({ message, onRetry }) {
  return <section className="rounded-2xl border border-red-200 bg-red-50 px-6 py-14 text-center"><AlertTriangle className="mx-auto text-red-600" size={36} /><h2 className="mt-3 text-xl font-bold text-slate-800">Disposition workspace unavailable</h2><p className="mx-auto mt-2 max-w-xl text-sm leading-6 text-slate-600">{message}</p><button className="mt-5 inline-flex h-10 items-center gap-2 rounded-lg bg-red-700 px-4 text-sm font-bold text-white" onClick={onRetry} type="button"><RefreshCw size={16} /> Retry</button></section>;
}

function ReadinessPanel({ readiness }) {
  const checklist = readiness?.checklist || [];
  return <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><div className="flex items-start gap-3"><span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-sky-100 text-sky-700"><ShieldCheck size={20} /></span><div><h2 className="font-bold text-slate-800">Readiness & eligibility</h2><p className="mt-1 text-sm text-slate-500">The backend readiness checklist is authoritative. Failed blocking criteria cannot be overridden in the client.</p></div></div>{checklist.length === 0 ? <p className="mt-4 text-sm text-slate-500">Readiness details are unavailable in this authorized scope.</p> : <ul className="mt-4 grid gap-2">{checklist.map((item) => <li className={`flex items-start gap-3 rounded-lg border p-3 ${item.passed ? "border-emerald-200 bg-emerald-50" : item.blocking ? "border-red-200 bg-red-50" : "border-amber-200 bg-amber-50"}`} key={item.code}><span className={`mt-0.5 ${item.passed ? "text-emerald-700" : item.blocking ? "text-red-700" : "text-amber-700"}`}>{item.passed ? <CheckCircle2 size={17} /> : <AlertTriangle size={17} />}</span><div className="min-w-0"><p className="text-sm font-bold text-slate-800">{item.label}{item.blocking && !item.passed && <span className="ml-2 text-xs uppercase text-red-700">Blocking</span>}</p><p className="mt-1 text-xs leading-5 text-slate-600">{item.explanation}</p></div></li>)}</ul>}</section>;
}

function CaseContext({ context, request, version }) {
  const status = context?.status;
  const terminal = status === "ACCEPTED_RISK" || status === "NO_LONGER_APPLICABLE";
  const accepted = status === "ACCEPTED_RISK";
  if (accepted && terminal) {
    return <section className="rounded-xl border border-amber-300 bg-amber-50 p-4 shadow-sm"><div className="flex flex-wrap items-start justify-between gap-3"><div><p className="text-xs font-bold uppercase tracking-wide text-sky-700">{request?.displayCode || "Disposition workspace"}</p><h2 className="mt-1 text-xl font-bold text-slate-900">Accepted Risk</h2><p className="mt-1 text-sm leading-6 text-slate-600">Residual risk was formally accepted through an authorized disposition decision. This does not represent implementation or ordinary recommendation closure.</p></div><DispositionStatus status={version?.statusCode || version?.status} /></div><dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4"><div><dt className="text-xs font-bold uppercase text-slate-500">Case status</dt><dd className="mt-1 font-semibold text-slate-800">{status}</dd></div><div><dt className="text-xs font-bold uppercase text-slate-500">Case lock</dt><dd className="mt-1 text-slate-700">{context?.lockVersion ?? "Not available"}</dd></div><div><dt className="text-xs font-bold uppercase text-slate-500">Request version</dt><dd className="mt-1 text-slate-700">V{version?.versionNumber || "-"}</dd></div><div><dt className="text-xs font-bold uppercase text-slate-500">Resolved</dt><dd className="mt-1 text-slate-700">{dateLabel(request?.resolvedAt)}</dd></div></dl></section>;
  }
  return <section className={`rounded-xl border p-4 shadow-sm ${accepted ? "border-amber-300 bg-amber-50" : status === "NO_LONGER_APPLICABLE" ? "border-violet-300 bg-violet-50" : terminal ? "border-slate-300 bg-slate-50" : "border-slate-200 bg-white"}`}><div className="flex flex-wrap items-start justify-between gap-3"><div className="min-w-0"><p className="text-xs font-bold uppercase tracking-wide text-sky-700">{request?.displayCode || "Disposition workspace"}</p><div className="mt-2 flex flex-wrap items-center gap-2"><DispositionType code={request?.dispositionCode} large /><DispositionStatus status={version?.statusCode || version?.status} /></div><h2 className="mt-3 text-xl font-bold text-slate-900">{accepted ? "Accepted Risk" : status === "NO_LONGER_APPLICABLE" ? "No Longer Applicable" : status === "FOR_DISPOSITION" ? "Disposition request in progress" : "Controlled disposition request"}</h2><p className="mt-1 max-w-3xl text-sm leading-6 text-slate-600">{accepted ? "Residual risk was formally accepted through an authorized disposition decision. This does not represent implementation or ordinary recommendation closure." : status === "NO_LONGER_APPLICABLE" ? "The recommendation was formally determined to be no longer applicable based on an authorized documented change in circumstances." : "Accepted Risk and No Longer Applicable are separate professional dispositions, not implementation or ordinary closure."}</p></div><div className="flex flex-wrap gap-2"><span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700">Case: {status || "Not available"}</span></div></div><dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4"><div><dt className="text-xs font-bold uppercase text-slate-500">Case lock</dt><dd className="mt-1 font-semibold text-slate-800">{context?.lockVersion ?? "Not available"}</dd></div><div><dt className="text-xs font-bold uppercase text-slate-500">Request version</dt><dd className="mt-1 text-slate-700">V{version?.versionNumber || "—"}</dd></div><div><dt className="text-xs font-bold uppercase text-slate-500">Previous case status</dt><dd className="mt-1 text-slate-700">{version?.previousCaseStatus || "Not available"}</dd></div><div><dt className="text-xs font-bold uppercase text-slate-500">Resolved</dt><dd className="mt-1 text-slate-700">{dateLabel(request?.resolvedAt)}</dd></div></dl></section>;
}

function NarrativeField({ name, label, value: fieldValue, onChange, error, disabled, required = false }) {
  const errorText = Array.isArray(error) ? error[0] : error;
  return <FormField error={errorText} htmlFor={`disposition-${name}`} label={label} required={required}><textarea className={textClass} disabled={disabled} id={`disposition-${name}`} onChange={(event) => onChange(name, event.target.value)} value={fieldValue || ""} /></FormField>;
}

function EvidencePanel({ version, busy, editable, onLink, onRemove, onDownload }) {
  const evidence = version?.evidence || [];
  const [form, setForm] = useState({ documentVersionId: "", evidenceCategory: "DISPOSITION_SUPPORT", title: "", description: "", sourceOrCustodian: "" });
  const submit = () => { if (!form.documentVersionId || !form.title.trim()) return; onLink(form, () => setForm({ documentVersionId: "", evidenceCategory: "DISPOSITION_SUPPORT", title: "", description: "", sourceOrCustodian: "" })); };
  return <div className="grid gap-4"><section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><div className="flex items-start gap-3"><span className="grid h-10 w-10 place-items-center rounded-xl bg-slate-100 text-slate-700"><Link2 size={19} /></span><div><h2 className="font-bold text-slate-800">Supporting Evidence</h2><p className="mt-1 text-sm text-slate-500">Link an exact Core Document Version. Internal storage paths and public URLs are never displayed.</p></div></div>{editable && <div className="mt-4 grid gap-3 sm:grid-cols-2"><FormField htmlFor="disposition-document-version" label="Core Document Version ID" required><input className={inputClass} id="disposition-document-version" inputMode="numeric" onChange={(event) => setForm({ ...form, documentVersionId: event.target.value })} value={form.documentVersionId} /></FormField><FormField htmlFor="disposition-evidence-category" label="Evidence category" required><input className={inputClass} id="disposition-evidence-category" onChange={(event) => setForm({ ...form, evidenceCategory: event.target.value })} value={form.evidenceCategory} /></FormField><FormField htmlFor="disposition-evidence-title" label="Title" required><input className={inputClass} id="disposition-evidence-title" onChange={(event) => setForm({ ...form, title: event.target.value })} value={form.title} /></FormField><FormField htmlFor="disposition-evidence-custodian" label="Source or custodian"><input className={inputClass} id="disposition-evidence-custodian" onChange={(event) => setForm({ ...form, sourceOrCustodian: event.target.value })} value={form.sourceOrCustodian} /></FormField><FormField htmlFor="disposition-evidence-description" label="Description" wide><textarea className={textClass} id="disposition-evidence-description" onChange={(event) => setForm({ ...form, description: event.target.value })} value={form.description} /></FormField><button className="inline-flex h-10 w-fit items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white disabled:opacity-60" disabled={busy || !form.documentVersionId || !form.title.trim()} onClick={submit} type="button"><Upload size={16} /> Link Core version</button></div>}</section>{evidence.length === 0 ? <div className="rounded-xl border border-dashed border-slate-300 bg-white px-5 py-12 text-center text-sm text-slate-500">No evidence is linked to this disposition version.</div> : <div className="grid gap-3">{evidence.map((item) => <article className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm" key={item.id}><div className="flex flex-wrap items-start justify-between gap-3"><div><h3 className="font-bold text-slate-800">{item.title}</h3><p className="mt-1 text-xs text-slate-500">{item.evidenceCategory} · {item.sourceOrCustodian || "Custodian not recorded"}</p></div>{item.removedAt && <StatusBadge tone="inactive">Removed from draft</StatusBadge>}</div><dl className="mt-4 grid gap-3 text-xs text-slate-600 sm:grid-cols-2 lg:grid-cols-4"><div><dt className="font-bold text-slate-500">Core document version</dt><dd className="mt-1">{item.documentVersionId || "Not available"}</dd></div><div><dt className="font-bold text-slate-500">Checksum</dt><dd className="mt-1 break-all font-mono">{item.checksumSha256 || "Not available"}</dd></div><div><dt className="font-bold text-slate-500">Confidentiality</dt><dd className="mt-1">{item.confidentialityCode || "Not available"}</dd></div><div><dt className="font-bold text-slate-500">Linked</dt><dd className="mt-1">{dateLabel(item.linkedAt, true)}</dd></div></dl><div className="mt-4 flex flex-wrap gap-2"><button className="inline-flex h-9 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-700" disabled={busy || Boolean(item.removedAt)} onClick={() => onDownload(item)} type="button"><Download size={14} /> Protected download</button>{editable && !item.removedAt && <button className="inline-flex h-9 items-center gap-2 rounded-lg border border-red-300 bg-white px-3 text-xs font-bold text-red-700" disabled={busy} onClick={() => onRemove(item)} type="button">Remove draft link</button>}</div><p className="mt-3 text-xs text-slate-500">The Core Document remains retained when this link is removed.</p></article>)}</div>}</div>;
}

function Assessment({ assessment }) {
  if (!assessment) return <div className="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">No independent review assessment has been recorded.</div>;
  const fields = [["Recommendation", value(assessment, "recommendationCode", "recommendation_code")], ["Readiness assessment", value(assessment, "readinessAssessment", "readiness_assessment")], ["Basis assessment", value(assessment, "basisAssessment", "basis_assessment")], ["Evidence assessment", value(assessment, "evidenceAssessment", "evidence_assessment")], ["Risk assessment", value(assessment, "riskAssessment", "risk_assessment")], ["Conditions or observations", value(assessment, "conditionsOrObservations", "conditions_or_observations")], ["Reviewer", value(assessment, "reviewerUserId", "reviewer_user_id")], ["Reviewed", dateLabel(value(assessment, "reviewedAt", "reviewed_at"), true)]];
  return <section className="rounded-xl border border-emerald-200 bg-emerald-50 p-4"><div className="flex items-start gap-3"><ShieldCheck className="mt-0.5 text-emerald-700" size={20} /><div><h2 className="font-bold text-slate-800">Independent review assessment</h2><p className="mt-1 text-sm text-slate-600">This assessment is immutable and is not the final disposition decision.</p></div></div><dl className="mt-4 grid gap-3 sm:grid-cols-2">{fields.map(([label, fieldValue]) => <div className="rounded-lg border border-emerald-100 bg-white/70 p-3" key={label}><dt className="text-xs font-bold uppercase text-slate-500">{label}</dt><dd className="mt-1 whitespace-pre-wrap text-sm text-slate-800">{fieldValue || "Not recorded"}</dd></div>)}</dl></section>;
}

function Decision({ decision, dispositionCode, caseContext }) {
  if (!decision) return <div className="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">No final decision has been recorded.</div>;
  const code = value(decision, "decisionCode", "decision_code");
  const approved = code === "APPROVED";
  if (approved && dispositionCode === "ACCEPTED_RISK") {
    return <section className="rounded-xl border border-amber-300 bg-amber-50 p-4"><div className="flex items-start gap-3"><CheckCircle2 className="mt-0.5 text-emerald-700" size={20} /><div><h2 className="font-bold text-slate-800">Accepted Risk approved</h2><p className="mt-1 text-sm leading-6 text-slate-700">Residual risk was formally accepted through an authorized disposition decision. This does not represent implementation or ordinary recommendation closure.</p></div></div><dl className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4"><div><dt className="text-xs font-bold uppercase text-slate-500">Decision</dt><dd className="mt-1 font-semibold text-slate-800">{code}</dd></div><div><dt className="text-xs font-bold uppercase text-slate-500">Actor</dt><dd className="mt-1 text-sm">{value(decision, "decidedBy", "decided_by") || "Not available"}</dd></div><div><dt className="text-xs font-bold uppercase text-slate-500">Date</dt><dd className="mt-1 text-sm">{dateLabel(value(decision, "decidedAt", "decided_at"), true)}</dd></div><div><dt className="text-xs font-bold uppercase text-slate-500">Resulting status</dt><dd className="mt-1 text-sm">{value(decision, "newCaseStatus", "new_case_status") || caseContext?.status || "Not available"}</dd></div></dl></section>;
  }
  return <section className={`rounded-xl border p-4 ${approved ? dispositionCode === "ACCEPTED_RISK" ? "border-amber-300 bg-amber-50" : "border-violet-300 bg-violet-50" : "border-red-200 bg-red-50"}`}><div className="flex items-start gap-3">{approved ? <CheckCircle2 className="mt-0.5 text-emerald-700" size={20} /> : <XCircle className="mt-0.5 text-red-700" size={20} />}<div><h2 className="font-bold text-slate-800">{approved ? dispositionCode === "ACCEPTED_RISK" ? "Accepted Risk approved" : "No Longer Applicable approved" : "Disposition rejected"}</h2><p className="mt-1 text-sm leading-6 text-slate-700">{approved ? dispositionCode === "ACCEPTED_RISK" ? "Residual risk was formally accepted through an authorized disposition decision. This does not represent implementation or ordinary recommendation closure." : "The recommendation was formally determined to be no longer applicable based on the documented authoritative basis." : `The disposition was not approved. The recommendation returned to ${value(decision, "newCaseStatus", "new_case_status") || caseContext?.status || "its previous implementation status"}.`}</p></div></div><dl className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4"><div><dt className="text-xs font-bold uppercase text-slate-500">Decision</dt><dd className="mt-1 font-semibold text-slate-800">{code || "Not recorded"}</dd></div><div><dt className="text-xs font-bold uppercase text-slate-500">Actor</dt><dd className="mt-1 text-slate-700">{value(decision, "decidedBy", "decided_by") || "Not available"}</dd></div><div><dt className="text-xs font-bold uppercase text-slate-500">Date</dt><dd className="mt-1 text-slate-700">{dateLabel(value(decision, "decidedAt", "decided_at"), true)}</dd></div><div><dt className="text-xs font-bold uppercase text-slate-500">Resulting status</dt><dd className="mt-1 text-sm">{value(decision, "newCaseStatus", "new_case_status") || caseContext?.status || "Not available"}</dd></div><div className="sm:col-span-2 lg:col-span-4"><dt className="text-xs font-bold uppercase text-slate-500">Decision comment</dt><dd className="mt-1 whitespace-pre-wrap text-sm text-slate-800">{value(decision, "decisionComment", "decision_comment") || "Not recorded"}</dd></div></dl></section>;
}

function VersionHistory({ request, selectedVersionId }) {
  const versions = request?.versions || [];
  if (!versions.length) return <div className="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">Version history will be shown when the backend returns historical versions. The current version remains authoritative.</div>;
  return <div className="grid gap-3">{versions.map((version) => <article className={`rounded-xl border p-4 ${version.id === selectedVersionId ? "border-sky-400 bg-sky-50" : "border-slate-200 bg-white"}`} key={version.id}><div className="flex flex-wrap items-center justify-between gap-3"><div className="flex items-center gap-2"><History size={16} className="text-slate-500" /><strong>Version {version.versionNumber}</strong><DispositionStatus status={version.statusCode || version.status} /></div><span className="text-xs text-slate-500">{version.isImmutable ? "Immutable" : "Editable draft"}</span></div><p className="mt-2 text-xs text-slate-600">Previous version: {version.previousVersionId || "None"} · Revision reason: {version.revisionReason || "Not recorded"}</p></article>)}</div>;
}

function EmptyState({ canCreate, reasons, onCreate }) {
  return <section className="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center"><ClipboardCheck className="mx-auto text-sky-600" size={30} /><h2 className="mt-3 text-lg font-bold text-slate-800">No disposition requests</h2><p className="mx-auto mt-2 max-w-2xl text-sm leading-6 text-slate-500">Accepted Risk and No Longer Applicable are controlled professional dispositions. They do not represent implementation or ordinary closure.</p>{canCreate ? <button className="mt-5 inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white" onClick={onCreate} type="button"><ClipboardCheck size={16} /> Create disposition request</button> : <div className="mx-auto mt-5 max-w-2xl rounded-lg border border-amber-200 bg-amber-50 p-3 text-left text-sm text-amber-900"><strong>Creation unavailable</strong>{reasons?.length ? <ul className="mt-2 list-disc space-y-1 pl-5">{reasons.map((reason) => <li key={reason}>{reason}</li>)}</ul> : <p className="mt-1">Your authorized scope does not currently permit creating a disposition.</p>}</div>}</section>;
}

function RequestCard({ request, recommendationId }) {
  const version = request.currentVersion;
  return <Link className="block rounded-xl border border-slate-200 bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-sky-300 hover:shadow-md focus-visible:outline-2 focus-visible:outline-sky-600" to={`/compliance-management/recommendations/${recommendationId}/dispositions/${request.id}`}><div className="flex flex-wrap items-start justify-between gap-3"><div><p className="text-xs font-bold uppercase tracking-wide text-sky-700">{request.displayCode}</p><h3 className="mt-1 font-bold text-slate-800">Request sequence {request.requestSequence}</h3><p className="mt-1 text-xs text-slate-500">Initiator: {request.initiatorTypeCode === "RESPONSIBLE_OFFICE" ? "Responsible Office" : "Compliance Monitor"}</p></div><div className="flex flex-wrap gap-2"><DispositionType code={request.dispositionCode} large /><DispositionStatus status={version?.statusCode || version?.status} /></div></div><dl className="mt-4 grid gap-3 text-xs text-slate-600 sm:grid-cols-2 lg:grid-cols-4"><div><dt className="font-bold text-slate-500">Current version</dt><dd className="mt-1">V{version?.versionNumber || "—"}</dd></div><div><dt className="font-bold text-slate-500">Previous status</dt><dd className="mt-1">{version?.previousCaseStatus || "Not available"}</dd></div><div><dt className="font-bold text-slate-500">Review recommendation</dt><dd className="mt-1">{value(version?.assessment, "recommendationCode", "recommendation_code") || "Not assessed"}</dd></div><div><dt className="font-bold text-slate-500">Final decision</dt><dd className="mt-1">{value(version?.decision, "decisionCode", "decision_code") || "Not decided"}</dd></div></dl></Link>;
}

function draftFromVersion(version) {
  return { ...(version?.narratives || {}) };
}

export default function CmsDispositionsPage() {
  const { recommendationId, dispositionId } = useParams();
  const navigate = useNavigate();
  const { user } = useAuth();
  const toast = useToast();
  const detailMode = Boolean(dispositionId);
  const canRequest = hasPermission(user, "cms.disposition.request");
  const canEvidence = hasPermission(user, "cms.disposition-evidence.upload");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [listData, setListData] = useState(null);
  const [options, setOptions] = useState(null);
  const [detail, setDetail] = useState(null);
  const [caseContext, setCaseContext] = useState(null);
  const [tab, setTab] = useState("overview");
  const [form, setForm] = useState({});
  const [dispositionCode, setDispositionCode] = useState("ACCEPTED_RISK");
  const [errors, setErrors] = useState({});
  const [busy, setBusy] = useState(false);
  const [workflow, setWorkflow] = useState("");
  const [workflowValues, setWorkflowValues] = useState({ comment: "", recommendationCode: "RECOMMEND_APPROVAL", readinessAssessment: "", basisAssessment: "", evidenceAssessment: "", riskAssessment: "", conditionsOrObservations: "", overrideReason: "", confirmed: false });
  const [conflict, setConflict] = useState("");
  const currentVersion = detail?.currentVersion || detail?.versions?.find((item) => item.isCurrent) || detail?.versions?.[0];
  const actions = currentVersion?.availableActions || detail?.availableActions || [];
  const editable = currentVersion?.status === "DRAFT" || currentVersion?.statusCode === "DRAFT";
  const readiness = options?.readiness;

  const loadList = useCallback(async () => {
    setLoading(true); setError("");
    try { const [result, optionResult] = await Promise.all([cmsApi.getDispositions(recommendationId), canRequest ? cmsApi.getDispositionOptions(recommendationId).catch(() => null) : Promise.resolve(null)]); setListData(result); setOptions(optionResult); setCaseContext(result?.caseContext || optionResult?.caseContext); }
    catch (requestError) { setError(requestError.status === 404 ? "This recommendation or its dispositions are unavailable or outside your authorized scope." : requestError.message || "The Dispositions could not be loaded."); }
    finally { setLoading(false); }
  }, [canRequest, recommendationId]);

  const loadDetail = useCallback(async () => {
    setLoading(true); setError("");
    try { const [result, optionResult] = await Promise.all([cmsApi.getDisposition(dispositionId), canRequest ? cmsApi.getDispositionOptions(recommendationId).catch(() => null) : Promise.resolve(null)]); setDetail(result?.request || null); setCaseContext(result?.caseContext || optionResult?.caseContext || null); setOptions(optionResult); setForm(draftFromVersion(result?.request?.currentVersion)); }
    catch (requestError) { setError(requestError.status === 404 ? "This Disposition Request is unavailable or outside your authorized scope." : requestError.message || "The Disposition Request could not be loaded."); }
    finally { setLoading(false); }
  }, [canRequest, dispositionId, recommendationId]);

  // The route is the synchronization boundary for the authoritative API state.
  // eslint-disable-next-line react-hooks/set-state-in-effect
  useEffect(() => { if (detailMode) loadDetail(); else loadList(); }, [detailMode, loadDetail, loadList]);

  async function refreshAfterConflict(requestError) {
    if (!(requestError instanceof ApiError) || requestError.status !== 409) return false;
    setConflict("This disposition changed elsewhere. Your local values are preserved; reload the authoritative version before retrying.");
    return true;
  }

  async function createRequest() {
    if (!options?.canCreate || busy) return;
    setBusy(true); setErrors({});
    try { const created = await cmsApi.createDisposition(recommendationId, { dispositionCode, initiatorTypeCode: options.initiatorTypes?.[0] }); toast.success("Disposition Request draft created."); navigate(`/compliance-management/recommendations/${recommendationId}/dispositions/${created.id}`); }
    catch (requestError) { setErrors(requestError.errors || {}); toast.error(requestError.message || "The Disposition Request could not be created."); }
    finally { setBusy(false); }
  }

  async function saveDraft() {
    if (!detail || !currentVersion || busy) return;
    setBusy(true); setErrors({}); setConflict("");
    try { const result = await cmsApi.updateDisposition(detail.id, currentVersion.id, { ...form, lockVersion: currentVersion.lockVersion }); const saved = result?.request || result; setDetail(saved); setCaseContext(result?.caseContext || caseContext); setForm(draftFromVersion(saved.currentVersion)); toast.success("Disposition draft saved."); }
    catch (requestError) { setErrors(requestError.errors || {}); await refreshAfterConflict(requestError); toast.error(requestError.message || "The Disposition draft could not be saved."); }
    finally { setBusy(false); }
  }

  async function runWorkflow() {
    if (!detail || !currentVersion || busy || !workflow) return;
    const values = workflowValues;
    const requiredComment = ["return", "recommend", "approve", "reject", "revise"].includes(workflow);
    if (requiredComment && !values.comment.trim()) { setErrors({ comment: ["A comment is required."] }); return; }
    if (!["return", "recommend"].includes(workflow) && !values.confirmed) { setErrors({ confirmation: ["Confirmation is required."] }); return; }
    setBusy(true); setErrors({}); setConflict("");
    try {
      const payload = { lockVersion: currentVersion.lockVersion };
      let saved;
      if (workflow === "submit") saved = await cmsApi.submitDisposition(detail.id, currentVersion.id, { ...payload, ...form });
      if (workflow === "start-review") saved = await cmsApi.startDispositionReview(detail.id, currentVersion.id, payload);
      if (workflow === "return") saved = await cmsApi.returnDisposition(detail.id, currentVersion.id, { ...payload, returnReason: values.comment.trim() });
      if (workflow === "recommend") saved = await cmsApi.recommendDisposition(detail.id, currentVersion.id, { ...payload, recommendationCode: values.recommendationCode, readinessAssessment: values.readinessAssessment, basisAssessment: values.basisAssessment, evidenceAssessment: values.evidenceAssessment, riskAssessment: values.riskAssessment, conditionsOrObservations: values.conditionsOrObservations || null });
      if (workflow === "approve") saved = await cmsApi.approveDisposition(detail.id, currentVersion.id, { ...payload, decisionComment: values.comment.trim(), overrideReason: values.overrideReason.trim() || null });
      if (workflow === "reject") saved = await cmsApi.rejectDisposition(detail.id, currentVersion.id, { ...payload, decisionComment: values.comment.trim(), overrideReason: values.overrideReason.trim() || null });
      if (workflow === "revise") saved = await cmsApi.createDispositionRevision(detail.id, currentVersion.id, { revisionReason: values.comment.trim() });
      const result = saved; const request = result?.request || result; setDetail(request); setCaseContext(result?.caseContext || caseContext); setForm(draftFromVersion(request?.currentVersion)); setWorkflow(""); setWorkflowValues({ comment: "", recommendationCode: "RECOMMEND_APPROVAL", readinessAssessment: "", basisAssessment: "", evidenceAssessment: "", riskAssessment: "", conditionsOrObservations: "", overrideReason: "", confirmed: false }); toast.success(workflow === "approve" ? "Disposition approved." : workflow === "reject" ? "Disposition rejected; prior status restored." : workflow === "revise" ? "New draft revision created." : `Disposition ${workflow} completed.`);
    } catch (requestError) { setErrors(requestError.errors || {}); await refreshAfterConflict(requestError); toast.error(requestError.message || "The Disposition workflow action could not be completed."); }
    finally { setBusy(false); }
  }

  async function linkEvidence(values, reset) { if (!detail || !currentVersion || busy) return; setBusy(true); try { const result = await cmsApi.uploadDispositionEvidence(detail.id, currentVersion.id, values); setDetail(result?.request || result); setCaseContext(result?.caseContext || caseContext); reset(); toast.success("Exact Core Document Version linked."); } catch (requestError) { await refreshAfterConflict(requestError); toast.error(requestError.message || "Evidence could not be linked."); } finally { setBusy(false); } }
  async function removeEvidence(item) { if (!window.confirm("Remove this draft evidence link? The Core Document will be retained.")) return; setBusy(true); try { const result = await cmsApi.removeDispositionEvidence(item.id, { reason: "Removed from draft by user." }); setDetail(result?.request || result); setCaseContext(result?.caseContext || caseContext); toast.success("Draft evidence link removed."); } catch (requestError) { await refreshAfterConflict(requestError); toast.error(requestError.message || "The evidence link could not be removed."); } finally { setBusy(false); } }

  if (loading && !(detailMode ? detail : listData)) return <main className="mx-auto max-w-[1500px] p-4 sm:p-6"><LoadingState /></main>;
  if (error && !(detailMode ? detail : listData)) return <main className="mx-auto max-w-[1500px] p-4 sm:p-6"><ErrorState message={error} onRetry={detailMode ? loadDetail : loadList} /></main>;

  if (!detailMode) {
    const requests = listData?.requests || [];
    return <main className="mx-auto max-w-[1500px] p-4 sm:p-6"><RegistryHeader actions={<button aria-label="Refresh dispositions" className="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700" disabled={loading} onClick={loadList} type="button"><RefreshCw className={loading ? "animate-spin" : ""} size={15} /> Refresh</button>} description="Prepare, review, and decide Accepted-Risk or No-Longer-Applicable professional dispositions." icon={ShieldAlert} title="Dispositions" /><div className="grid gap-4 lg:grid-cols-[1.2fr_1fr]"><CaseContext context={caseContext} /><ReadinessPanel readiness={readiness} /></div><section className="mt-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><div className="mb-4 flex flex-wrap items-start justify-between gap-3"><div><h2 className="text-lg font-bold text-slate-800">Disposition Request history</h2><p className="mt-1 text-sm text-slate-500">{requests.length} request famil{requests.length === 1 ? "y" : "ies"}; submitted and decided versions remain immutable.</p></div>{options?.canCreate && canRequest && <div className="flex flex-wrap gap-2"><select aria-label="Disposition type" className="h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-700" onChange={(event) => setDispositionCode(event.target.value)} value={dispositionCode}>{(options.dispositionTypes || ["ACCEPTED_RISK", "NO_LONGER_APPLICABLE"]).map((code) => <option key={code} value={code}>{code === "ACCEPTED_RISK" ? "Accepted Risk" : "No Longer Applicable"}</option>)}</select><button className="inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white" disabled={busy} onClick={createRequest} type="button"><ClipboardCheck size={16} /> Create request</button></div>}</div>{requests.length === 0 ? <EmptyState canCreate={Boolean(options?.canCreate && canRequest)} reasons={options?.reasons || []} onCreate={createRequest} /> : <div className="grid gap-3">{requests.map((request) => <RequestCard key={request.id} recommendationId={recommendationId} request={request} />)}</div>}</section></main>;
  }

  const narratives = currentVersion?.narratives || {};
  const readOnly = !editable || !actions.includes("update");
  const evidenceEditable = editable && canEvidence && actions.includes("upload-evidence");
  const type = detail?.dispositionCode;
  const activeAssessment = currentVersion?.assessment;
  const decision = currentVersion?.decision;
  const tabs = [["overview", "Overview"], ["readiness", "Readiness & Eligibility"], ["details", "Disposition Details"], ["evidence", "Supporting Evidence"], ["review", "Review Assessment"], ["decision", "Final Decision"], ["history", "Versions & History"]];
  return <main className="mx-auto max-w-[1500px] p-4 sm:p-6"><RegistryHeader actions={<><Link className="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700" to={`/compliance-management/recommendations/${recommendationId}/dispositions`}><ArrowLeft size={16} /> Dispositions</Link><button aria-label="Refresh disposition" className="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700" disabled={loading || busy} onClick={loadDetail} type="button"><RefreshCw className={loading ? "animate-spin" : ""} size={15} /> Refresh</button></>} description="A controlled professional disposition remains distinct from implementation and ordinary closure." icon={ShieldAlert} title={detail?.displayCode || "Disposition Request"} />{conflict && <div className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900"><span>{conflict}</span><button className="inline-flex h-9 items-center gap-2 rounded-lg bg-amber-700 px-3 text-xs font-bold text-white" onClick={loadDetail} type="button"><RefreshCw size={14} /> Reload latest</button></div>}{error && <div className="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">Refresh failed. Showing the last successfully loaded request.</div>}<CaseContext context={caseContext} request={detail} version={currentVersion} /><div aria-label="Disposition workspace sections" className="mt-4 flex overflow-x-auto rounded-xl border border-slate-200 bg-white p-1 shadow-sm" role="tablist">{tabs.map(([key, label]) => <button aria-selected={tab === key} className={`inline-flex min-w-max flex-1 items-center justify-center rounded-lg px-3 py-2.5 text-sm font-bold transition ${tab === key ? "bg-sky-700 text-white" : "text-slate-600 hover:bg-slate-50"}`} key={key} onClick={() => setTab(key)} role="tab" type="button">{label}</button>)}</div><section className="mt-4 min-w-0 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5" role="tabpanel">{tab === "overview" && <div className="grid gap-4"><div className="flex flex-wrap items-center justify-between gap-3"><div><h2 className="text-lg font-bold text-slate-800">Request overview</h2><p className="mt-1 text-sm text-slate-500">Current version V{currentVersion?.versionNumber || "—"} · {readOnly ? "Read-only immutable record" : "Editable draft"}.</p></div><div className="flex flex-wrap gap-2">{actions.includes("update") && <button className="inline-flex h-10 items-center gap-2 rounded-lg border border-sky-300 bg-sky-50 px-4 text-sm font-bold text-sky-800" disabled={busy} onClick={saveDraft} type="button"><Save size={16} /> Save draft</button>}{actions.includes("submit") && <button className="inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white" disabled={busy} onClick={() => setWorkflow("submit")} type="button"><Send size={16} /> Submit</button>}{actions.includes("start-review") && <button className="inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white" disabled={busy} onClick={() => setWorkflow("start-review")} type="button">Start review</button>}{actions.includes("return") && <button className="inline-flex h-10 items-center gap-2 rounded-lg border border-amber-300 bg-white px-4 text-sm font-bold text-amber-800" disabled={busy} onClick={() => setWorkflow("return")} type="button">Return</button>}{actions.includes("recommend") && <button className="inline-flex h-10 items-center gap-2 rounded-lg bg-emerald-700 px-4 text-sm font-bold text-white" disabled={busy} onClick={() => setWorkflow("recommend")} type="button"><CheckCircle2 size={16} /> Record assessment</button>}{actions.includes("approve") && <button className="inline-flex h-10 items-center gap-2 rounded-lg bg-emerald-700 px-4 text-sm font-bold text-white" disabled={busy} onClick={() => setWorkflow("approve")} type="button">Approve</button>}{actions.includes("reject") && <button className="inline-flex h-10 items-center gap-2 rounded-lg border border-red-300 bg-white px-4 text-sm font-bold text-red-700" disabled={busy} onClick={() => setWorkflow("reject")} type="button"><XCircle size={16} /> Reject</button>}{actions.includes("revise") && <button className="inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white" disabled={busy} onClick={() => setWorkflow("revise")} type="button"><FilePenLine size={16} /> Create revision</button>}</div></div><dl className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"><div><dt className="text-xs font-bold uppercase text-slate-500">Preparer</dt><dd className="mt-1 text-sm text-slate-800">{currentVersion?.preparedBy || "Not available"}</dd></div><div><dt className="text-xs font-bold uppercase text-slate-500">Submitter</dt><dd className="mt-1 text-sm text-slate-800">{currentVersion?.submittedBy || "Not submitted"}</dd></div><div><dt className="text-xs font-bold uppercase text-slate-500">Submitted</dt><dd className="mt-1 text-sm text-slate-800">{dateLabel(currentVersion?.submittedAt, true)}</dd></div><div><dt className="text-xs font-bold uppercase text-slate-500">Review started</dt><dd className="mt-1 text-sm text-slate-800">{dateLabel(currentVersion?.reviewStartedAt, true)}</dd></div></dl><div className="grid gap-3 sm:grid-cols-2">{sharedFields.map(([name, label]) => <NarrativeField disabled={readOnly} error={errors[name]} key={name} label={label} name={name} onChange={(key, fieldValue) => setForm({ ...form, [key]: fieldValue })} required value={value(narratives, name)} />)}</div></div>}{tab === "readiness" && <ReadinessPanel readiness={readiness} />}{tab === "details" && <div className="grid gap-4">{type === "ACCEPTED_RISK" && <section className="rounded-xl border border-amber-200 bg-amber-50 p-4"><h2 className="font-bold text-slate-800">Accepted-Risk details</h2><p className="mt-1 text-sm text-slate-600">Residual risk is formally accepted; this is not implementation.</p><NarrativeField disabled={readOnly} error={errors.acceptedRiskRationale} label="Accepted-Risk rationale" name="acceptedRiskRationale" onChange={(key, fieldValue) => setForm({ ...form, [key]: fieldValue })} required value={value(narratives, "acceptedRiskRationale")} /><NarrativeField disabled={readOnly} error={errors.riskTreatmentAndMonitoring} label="Risk treatment and monitoring" name="riskTreatmentAndMonitoring" onChange={(key, fieldValue) => setForm({ ...form, [key]: fieldValue })} value={value(narratives, "riskTreatmentAndMonitoring")} /><NarrativeField disabled={readOnly} error={errors.residualRiskStatement} label="Residual risk statement" name="residualRiskStatement" onChange={(key, fieldValue) => setForm({ ...form, [key]: fieldValue })} value={value(narratives, "residualRiskStatement")} /></section>}{type === "NO_LONGER_APPLICABLE" && <section className="rounded-xl border border-violet-200 bg-violet-50 p-4"><h2 className="font-bold text-slate-800">No-Longer-Applicable details</h2><p className="mt-1 text-sm text-slate-600">Use the documented change in circumstances; implementation difficulty is not a valid basis.</p><NarrativeField disabled={readOnly} error={errors.noLongerApplicableBasis} label="No-Longer-Applicable basis" name="noLongerApplicableBasis" onChange={(key, fieldValue) => setForm({ ...form, [key]: fieldValue })} required value={value(narratives, "noLongerApplicableBasis")} /><NarrativeField disabled={readOnly} error={errors.transitionOrRecordsImpact} label="Transition or records impact" name="transitionOrRecordsImpact" onChange={(key, fieldValue) => setForm({ ...form, [key]: fieldValue })} value={value(narratives, "transitionOrRecordsImpact")} /></section>}</div>}{tab === "evidence" && <EvidencePanel busy={busy} editable={evidenceEditable} onDownload={(item) => cmsApi.downloadDispositionEvidence(item.id, item.title).catch((requestError) => toast.error(requestError.message || "Evidence download failed."))} onLink={linkEvidence} onRemove={removeEvidence} version={currentVersion} />}{tab === "review" && <Assessment assessment={activeAssessment} />}{tab === "decision" && <Decision caseContext={caseContext} decision={decision} dispositionCode={type} />}{tab === "history" && <VersionHistory request={detail} selectedVersionId={currentVersion?.id} />}</section>{workflow && <WorkflowDialog action={workflow} busy={busy} errors={errors} values={workflowValues} setValues={setWorkflowValues} onClose={() => !busy && setWorkflow("")} onConfirm={runWorkflow} />}</main>;
}

function WorkflowDialog({ action, busy, errors, values, setValues, onClose, onConfirm }) {
  const requiresComment = ["return", "recommend", "approve", "reject", "revise"].includes(action);
  const title = { submit: "Submit disposition request", "start-review": "Start independent review", return: "Return for revision", recommend: "Record independent assessment", approve: "Approve disposition", reject: "Reject disposition", revise: "Create a new revision" }[action] || "Disposition action";
  const instruction = { submit: "Submission moves the case to FOR_DISPOSITION and makes this version immutable.", "start-review": "Starting review does not make a final disposition decision.", return: "The returned version remains immutable; create a new revision for corrections.", recommend: "The reviewer recommendation is not the final disposition decision.", approve: "Approval applies the exact Accepted-Risk or No-Longer-Applicable disposition selected by this request.", reject: "Rejection restores the pinned previous case status.", revise: "A new immutable version lineage will be created from this returned version." }[action];
  return <div aria-modal="true" className="fixed inset-0 z-50 grid place-items-center bg-slate-950/40 p-4" role="dialog"><section className="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-2xl bg-white p-5 shadow-2xl"><div className="flex items-start justify-between gap-3"><div><h2 className="text-xl font-bold text-slate-900">{title}</h2><p className="mt-1 text-sm leading-6 text-slate-600">{instruction}</p></div><button aria-label="Close dialog" className="rounded-lg p-2 text-slate-500 hover:bg-slate-100" onClick={onClose} type="button">×</button></div><div className="mt-5 grid gap-4">{action === "recommend" && <><FormField error={errors.recommendationCode} htmlFor="disposition-recommendation" label="Assessment recommendation" required><select className={inputClass} id="disposition-recommendation" onChange={(event) => setValues({ ...values, recommendationCode: event.target.value })} value={values.recommendationCode}><option value="RECOMMEND_APPROVAL">Recommend approval</option><option value="RECOMMEND_REJECTION">Recommend rejection</option></select></FormField>{[["readinessAssessment", "Readiness assessment"], ["basisAssessment", "Basis assessment"], ["evidenceAssessment", "Evidence assessment"], ["riskAssessment", "Risk assessment"], ["conditionsOrObservations", "Conditions or observations"]].map(([name, label]) => <NarrativeField disabled={busy} error={errors[name]} key={name} label={label} name={name} onChange={(key, fieldValue) => setValues({ ...values, [key]: fieldValue })} required={name !== "conditionsOrObservations"} value={values[name]} />)}</>}{requiresComment && <NarrativeField disabled={busy} error={errors.comment || errors.returnReason || errors.decisionComment || errors.revisionReason} label={action === "return" ? "Return reason" : action === "revise" ? "Revision reason" : action === "reject" ? "Decision comment" : action === "approve" ? "Decision comment" : "Review comment"} name="comment" onChange={(key, fieldValue) => setValues({ ...values, [key]: fieldValue })} required value={values.comment} />}{["approve", "reject"].includes(action) && <NarrativeField disabled={busy} error={errors.overrideReason} label="Override reason (when applicable)" name="overrideReason" onChange={(key, fieldValue) => setValues({ ...values, [key]: fieldValue })} value={values.overrideReason} />}{!["return", "recommend", "revise"].includes(action) && <label className="flex items-start gap-2 text-sm text-slate-700"><input checked={values.confirmed} onChange={(event) => setValues({ ...values, confirmed: event.target.checked })} type="checkbox" /><span>I confirm this action and understand the professional disposition controls.</span></label>}{errors.confirmation && <p className="text-sm font-semibold text-red-700">{Array.isArray(errors.confirmation) ? errors.confirmation[0] : errors.confirmation}</p>}{Object.entries(errors || {}).filter(([key]) => key !== "confirmation").map(([key, fieldError]) => <p className="text-sm font-semibold text-red-700" key={key}>{Array.isArray(fieldError) ? fieldError[0] : fieldError}</p>)}</div><div className="mt-6 flex flex-wrap justify-end gap-2"><button className="h-10 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700" disabled={busy} onClick={onClose} type="button">Cancel</button><button className="inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-5 text-sm font-bold text-white disabled:opacity-60" disabled={busy || (requiresComment && !values.comment.trim()) || (!["return", "recommend", "revise"].includes(action) && !values.confirmed)} onClick={onConfirm} type="button">{busy ? "Saving…" : "Confirm"}</button></div></section></div>;
}
