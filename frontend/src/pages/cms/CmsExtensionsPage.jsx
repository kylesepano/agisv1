import { useCallback, useEffect, useState } from "react";
import {
  AlertTriangle,
  ArrowLeft,
  CheckCircle2,
  Clock3,
  Download,
  FileCheck2,
  FilePenLine,
  Plus,
  RefreshCw,
  RotateCcw,
  Save,
  Send,
  Upload,
  XCircle,
} from "lucide-react";
import { Link, useNavigate, useParams } from "react-router";
import { useAuth } from "../../auth/auth-context";
import { hasPermission } from "../../config/navigation";
import { ApiError, cmsApi, documentApi } from "../../services/api";
import FormField from "../../components/ui/FormField";
import Modal from "../../components/ui/Modal";
import RegistryHeader from "../../components/ui/RegistryHeader";
import StatusBadge from "../../components/ui/StatusBadge";
import { useToast } from "../../ui/toast-context";

const inputClass = "mt-1 w-full rounded-lg border border-slate-300 bg-white p-2.5 text-sm text-slate-800 outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100 disabled:bg-slate-100";
const textareaClass = `${inputClass} min-h-28`;
const tabs = [
  ["overview", "Overview"],
  ["evidence", "Supporting Evidence"],
  ["assessment", "Assessment & Recommendation"],
  ["decision", "Decision"],
  ["history", "Versions & History"],
];

function displayDate(value, includeTime = false) {
  if (!value) return "Not available";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "Not available";
  return new Intl.DateTimeFormat("en-PH", { dateStyle: "medium", ...(includeTime ? { timeStyle: "short" } : {}) }).format(date);
}

function firstError(errors, key) {
  const value = errors?.[key];
  return Array.isArray(value) ? value[0] : value || "";
}

function labelForStatus(status) {
  return {
    DRAFT: "Draft",
    SUBMITTED: "Submitted",
    UNDER_REVIEW: "Under review",
    RETURNED: "Returned",
    FOR_APPROVAL: "For approval",
    APPROVED: "Approved",
    REJECTED: "Rejected",
  }[status] || status || "Unknown";
}

function ExtensionStatusBadge({ status }) {
  const appearance = {
    DRAFT: ["info", FilePenLine],
    SUBMITTED: ["warning", Send],
    UNDER_REVIEW: ["warning", Clock3],
    RETURNED: ["danger", RotateCcw],
    FOR_APPROVAL: ["warning", Clock3],
    APPROVED: ["success", CheckCircle2],
    REJECTED: ["danger", XCircle],
  }[status] || ["inactive", Clock3];
  const Icon = appearance[1];
  return <StatusBadge tone={appearance[0]}><Icon aria-hidden="true" className="mr-1" size={13} />{labelForStatus(status)}</StatusBadge>;
}

function DateCard({ label, value, tone = "slate", note }) {
  const tones = { slate: "border-slate-200 bg-slate-50", sky: "border-sky-200 bg-sky-50", amber: "border-amber-200 bg-amber-50", emerald: "border-emerald-200 bg-emerald-50" };
  return <div className={`rounded-xl border p-3 ${tones[tone] || tones.slate}`}><dt className="text-xs font-bold uppercase tracking-wide text-slate-500">{label}</dt><dd className="mt-1 text-sm font-bold text-slate-800">{displayDate(value)}</dd>{note && <p className="mt-1 text-xs text-slate-600">{note}</p>}</div>;
}

function ReadOnlyField({ label, value, wide = false }) {
  return <div className={`min-w-0 rounded-lg border border-slate-200 bg-slate-50 p-3 ${wide ? "sm:col-span-2" : ""}`}><dt className="text-xs font-bold uppercase tracking-wide text-slate-500">{label}</dt><dd className="mt-1 whitespace-pre-wrap break-words text-sm leading-6 text-slate-800">{value || "Not available"}</dd></div>;
}

function ContextPanel({ context, version, recommendation }) {
  return <section className="mb-4 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
    <DateCard label="Original target date" value={context?.originalTargetDate || recommendation?.originalTargetDate} />
    <DateCard label="Current effective target" value={context?.effectiveTargetDate || recommendation?.effectiveTargetDate} tone="emerald" note="Changes only after approval" />
    <DateCard label="Request baseline" value={version?.baselineEffectiveTargetDate} tone="slate" />
    <DateCard label="Requested target (pending)" value={version?.requestedTargetDate} tone={version?.status === "APPROVED" ? "emerald" : "amber"} note={version?.status === "APPROVED" ? "Approved effective date" : "Not an approved target"} />
    <div className="sm:col-span-2 lg:col-span-4 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4"><div><dt className="text-xs font-bold uppercase tracking-wide text-slate-500">Recommendation</dt><dd className="mt-1 font-semibold text-slate-800">{recommendation?.recommendationCode || context?.cmsRecommendationCode || "CMS recommendation"}</dd></div><div><dt className="text-xs font-bold uppercase tracking-wide text-slate-500">Responsible office</dt><dd className="mt-1 text-slate-700">{context?.responsibleOffice?.name || recommendation?.responsibleOffice?.name || "Not assigned"}</dd></div><div><dt className="text-xs font-bold uppercase tracking-wide text-slate-500">Case status</dt><dd className="mt-1 text-slate-700">{context?.status || recommendation?.status || "Not available"}</dd></div><div><dt className="text-xs font-bold uppercase tracking-wide text-slate-500">Compliance Monitor</dt><dd className="mt-1 text-slate-700">{context?.currentComplianceMonitor?.name || recommendation?.currentMonitor?.user?.name || "Unassigned"}</dd></div></div>
  </section>;
}

function ExtensionForm({ form, setForm, errors, disabled, onSave, onCancel, dateMin, dateMax, title = "Create target-date extension" }) {
  const field = (key, label, wide = true) => <FormField error={firstError(errors, key)} htmlFor={`extension-${key}`} label={label}><textarea className={`${textareaClass} ${wide ? "min-h-28" : ""}`} disabled={disabled} id={`extension-${key}`} onChange={(event) => setForm({ ...form, [key]: event.target.value })} value={form[key] ?? ""} /></FormField>;
  return <section className="rounded-xl border border-sky-200 bg-sky-50 p-4 sm:p-5"><div className="mb-4 flex flex-wrap items-start justify-between gap-3"><div><h2 className="text-lg font-bold text-slate-800">{title}</h2><p className="mt-1 text-sm leading-6 text-slate-600">The requested date is a proposal only. It cannot change the effective target date until formal approval.</p></div><div className="flex gap-2"><button className="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700" disabled={disabled} onClick={onCancel} type="button">Cancel</button><button className="inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white disabled:opacity-60" disabled={disabled} onClick={onSave} type="button"><Save size={16} />{disabled ? "Saving..." : "Save draft"}</button></div></div><div className="grid gap-4 md:grid-cols-2"><FormField error={firstError(errors, "requestedTargetDate")} htmlFor="extension-requestedTargetDate" label="Requested target date" required><input className={inputClass} disabled={disabled} id="extension-requestedTargetDate" max={dateMax || undefined} min={dateMin || undefined} onChange={(event) => setForm({ ...form, requestedTargetDate: event.target.value })} type="date" value={form.requestedTargetDate || ""} /></FormField><FormField error={firstError(errors, "managementProgressSummary")} htmlFor="extension-managementProgressSummary" label="Management progress summary"><textarea className={textareaClass} disabled={disabled} id="extension-managementProgressSummary" onChange={(event) => setForm({ ...form, managementProgressSummary: event.target.value })} value={form.managementProgressSummary || ""} /></FormField>{field("extensionJustification", "Extension justification")}{field("causeOfDelay", "Cause of delay")}{field("actionsAlreadyTaken", "Actions already taken")}{field("remainingActions", "Remaining actions")}{field("recoveryPlan", "Recovery plan")}{field("impactIfNotApproved", "Impact if not approved")}{field("revisedScheduleSummary", "Revised schedule summary")}{field("noEvidenceExplanation", "No-evidence explanation (only when no supporting evidence is linked)")}</div></section>;
}

function EvidencePanel({ version, busy, canUpload, confidentialityLevels, onUpload, onDownload, onRemove }) {
  const [form, setForm] = useState({ title: "", evidenceCategory: "SUPPORTING_DOCUMENT", description: "", sourceOrCustodian: "", confidentialityLevelId: confidentialityLevels[0]?.id || "", file: null });
  const active = version?.activeEvidenceLinks || version?.evidenceLinks || [];
  const editable = version?.status === "DRAFT" && canUpload;
  return <div className="grid gap-4"><div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900"><strong>Evidence rule:</strong> link at least one supporting document, or provide a no-evidence explanation in the Overview. Evidence links become immutable when the version is submitted.</div>{editable && <section className="rounded-xl border border-slate-200 bg-white p-4"><h3 className="font-bold text-slate-800">Link supporting evidence</h3><div className="mt-4 grid gap-3 md:grid-cols-2"><FormField htmlFor="extension-evidence-title" label="Title" required><input className={inputClass} id="extension-evidence-title" onChange={(event) => setForm({ ...form, title: event.target.value })} value={form.title} /></FormField><FormField htmlFor="extension-evidence-category" label="Evidence category" required><input className={inputClass} id="extension-evidence-category" onChange={(event) => setForm({ ...form, evidenceCategory: event.target.value })} value={form.evidenceCategory} /></FormField><FormField htmlFor="extension-evidence-description" label="Description"><textarea className={textareaClass} id="extension-evidence-description" onChange={(event) => setForm({ ...form, description: event.target.value })} value={form.description} /></FormField><FormField htmlFor="extension-evidence-custodian" label="Source or custodian"><input className={inputClass} id="extension-evidence-custodian" onChange={(event) => setForm({ ...form, sourceOrCustodian: event.target.value })} value={form.sourceOrCustodian} /></FormField><FormField htmlFor="extension-evidence-confidentiality" label="Confidentiality" required><select className={inputClass} id="extension-evidence-confidentiality" onChange={(event) => setForm({ ...form, confidentialityLevelId: event.target.value })} value={form.confidentialityLevelId}><option value="">Select confidentiality</option>{confidentialityLevels.map((item) => <option key={item.id} value={item.id}>{item.label || item.name || item.code}</option>)}</select></FormField><FormField htmlFor="extension-evidence-file" label="File" required><input className={`${inputClass} file:mr-3 file:rounded-md file:border-0 file:bg-sky-100 file:px-3 file:py-1 file:text-xs file:font-bold file:text-sky-800`} id="extension-evidence-file" onChange={(event) => setForm({ ...form, file: event.target.files?.[0] || null })} type="file" /></FormField></div><button className="mt-3 inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white disabled:opacity-60" disabled={busy || !form.title.trim() || !form.file || !form.confidentialityLevelId} onClick={() => onUpload(form, () => setForm({ ...form, title: "", description: "", sourceOrCustodian: "", file: null }))} type="button"><Upload size={16} /> Upload evidence</button></section>}{active.length === 0 ? <div className="rounded-xl border border-dashed border-slate-300 bg-white px-5 py-12 text-center text-sm text-slate-500">No evidence is linked to this version.</div> : <div className="grid gap-3">{active.map((item) => <article className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm" key={item.id}><div className="flex flex-wrap items-start justify-between gap-3"><div><h3 className="font-bold text-slate-800">{item.title}</h3><p className="mt-1 text-xs text-slate-500">{item.evidenceCategory} · {item.sourceOrCustodian || "Custodian not recorded"}</p></div><div className="flex flex-wrap gap-2">{item.isActive !== false && <StatusBadge tone="success">Active link</StatusBadge>}{item.removedAt && <StatusBadge tone="inactive">Removed from draft</StatusBadge>}</div></div><dl className="mt-4 grid gap-3 text-xs text-slate-600 sm:grid-cols-2 lg:grid-cols-4"><div><dt className="font-bold text-slate-500">Core document version</dt><dd className="mt-1">{item.documentVersionId || item.documentId || "Not available"}</dd></div><div><dt className="font-bold text-slate-500">Checksum</dt><dd className="mt-1 break-all font-mono">{item.checksumSha256 || "Not available"}</dd></div><div><dt className="font-bold text-slate-500">Confidentiality</dt><dd className="mt-1">{item.confidentialityCode || "Not available"}</dd></div><div><dt className="font-bold text-slate-500">Linked</dt><dd className="mt-1">{displayDate(item.linkedAt, true)}</dd></div></dl><div className="mt-4 flex flex-wrap gap-2">{item.isActive !== false && <button className="inline-flex h-9 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-700" disabled={busy} onClick={() => onDownload(item)} type="button"><Download size={14} /> Download protected file</button>}{editable && item.isActive !== false && <button className="inline-flex h-9 items-center gap-2 rounded-lg border border-red-300 bg-white px-3 text-xs font-bold text-red-700" disabled={busy} onClick={() => onRemove(item)} type="button">Remove draft link</button>}</div><p className="mt-3 text-xs text-slate-500">The Core Document is retained when this link is removed.</p></article>)}</div>}</div>;
}

function WorkflowDialog({ action, version, busy, errors, onClose, onConfirm }) {
  const [values, setValues] = useState({ comment: "", recommendationCode: "RECOMMEND_APPROVAL", overrideReason: "", confirmed: false });
  useEffect(() => {
    if (!action) return undefined;
    const timer = window.setTimeout(() => setValues({ comment: "", recommendationCode: "RECOMMEND_APPROVAL", overrideReason: "", confirmed: false }), 0);
    return () => window.clearTimeout(timer);
  }, [action]);
  if (!action) return null;
  const requiresComment = ["return", "revise", "recommend", "approve", "reject"].includes(action);
  const title = { submit: "Submit extension request", "start-review": "Start compliance review", return: "Return for revision", recommend: "Record assessment recommendation", approve: "Approve target-date extension", reject: "Reject target-date extension", revise: "Create a new revision" }[action];
  const instruction = { submit: "Submission makes this version and its evidence links immutable. The effective target date remains unchanged.", "start-review": "Starting review does not change the effective target date.", return: "The returned version remains immutable; management must create a new revision.", recommend: "This is an assessment recommendation, not the final decision.", approve: "Approval changes the current effective target date to the exact requested date. The original target remains unchanged.", reject: "Rejection resolves the request and leaves the current effective target date unchanged.", revise: "The returned version remains immutable and a new draft will be created." }[action];
  return <Modal description={instruction} onClose={onClose} open size="lg" title={title} footer={<><button className="h-10 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700" disabled={busy} onClick={onClose} type="button">Cancel</button><button className="h-10 rounded-lg bg-sky-700 px-5 text-sm font-bold text-white disabled:opacity-60" disabled={busy || (requiresComment && !values.comment.trim()) || !values.confirmed} onClick={() => onConfirm(values)} type="button">{busy ? "Saving..." : "Confirm"}</button></>}><div className="grid gap-4"><div className="grid gap-3 sm:grid-cols-3"><DateCard label="Current effective" value={version?.baselineEffectiveTargetDate} /><DateCard label="Requested" value={version?.requestedTargetDate} tone="amber" note="Pending unless approved" /><DateCard label="Extension days" value={null} tone="slate" note={`${version?.extensionDays ?? "Not available"} days`} /></div>{action === "recommend" && <FormField error={firstError(errors, "recommendationCode")} htmlFor="extension-recommendation" label="Assessment recommendation" required><select className={inputClass} id="extension-recommendation" onChange={(event) => setValues({ ...values, recommendationCode: event.target.value })} value={values.recommendationCode}><option value="RECOMMEND_APPROVAL">Recommend approval</option><option value="RECOMMEND_REJECTION">Recommend rejection</option></select></FormField>}{action === "approve" || action === "reject" ? <p className="rounded-lg border border-sky-200 bg-sky-50 p-3 text-sm text-sky-900">The approver cannot type an alternative approved date. The backend uses the exact requested target date.</p> : null}{requiresComment && <FormField error={firstError(errors, action === "return" ? "returnReason" : action === "revise" ? "revisionReason" : action === "reject" ? "rejectionReason" : action === "recommend" ? "assessmentSummary" : "decisionComment")} htmlFor="extension-workflow-comment" label={action === "reject" ? "Rejection reason" : action === "return" ? "Return reason" : action === "revise" ? "Revision reason" : action === "recommend" ? "Assessment summary" : "Decision comment"} required><textarea className={textareaClass} id="extension-workflow-comment" onChange={(event) => setValues({ ...values, comment: event.target.value })} value={values.comment} /></FormField>}{action === "recommend" && <div className="grid gap-3 sm:grid-cols-2">{[["evidenceReviewSummary", "Evidence review summary"], ["feasibilityAssessment", "Feasibility assessment"], ["riskOfDelaySummary", "Risk-of-delay summary"], ["conditionsOrObservations", "Conditions or observations"]].map(([key, label]) => <FormField error={firstError(errors, key)} htmlFor={`extension-${key}`} label={label} key={key} required={key !== "conditionsOrObservations"}><textarea className={textareaClass} id={`extension-${key}`} onChange={(event) => setValues({ ...values, [key]: event.target.value })} value={values[key] || ""} /></FormField>)}</div>}{(action === "approve" || action === "reject") && <FormField error={firstError(errors, "overrideReason")} htmlFor="extension-override" label="Override reason (required when decision differs from assessment)"><textarea className={textareaClass} id="extension-override" onChange={(event) => setValues({ ...values, overrideReason: event.target.value })} value={values.overrideReason} /></FormField>}<label className="flex items-start gap-2 text-sm text-slate-700"><input checked={values.confirmed} onChange={(event) => setValues({ ...values, confirmed: event.target.checked })} type="checkbox" /> <span>I confirm this action and understand the target-date rules.</span></label>{Object.entries(errors || {}).map(([key, value]) => <p className="text-sm font-semibold text-red-700" key={key}>{Array.isArray(value) ? value[0] : value}</p>)}</div></Modal>;
}

function extensionFormFromVersion(version) {
  return { requestedTargetDate: version?.requestedTargetDate || "", extensionJustification: version?.extensionJustification || "", causeOfDelay: version?.causeOfDelay || "", actionsAlreadyTaken: version?.actionsAlreadyTaken || "", remainingActions: version?.remainingActions || "", recoveryPlan: version?.recoveryPlan || "", impactIfNotApproved: version?.impactIfNotApproved || "", revisedScheduleSummary: version?.revisedScheduleSummary || "", managementProgressSummary: version?.managementProgressSummary || "", noEvidenceExplanation: version?.noEvidenceExplanation || "" };
}

function extensionDraftForm(options) {
  return { requestedTargetDate: options?.earliestAllowedRequestedDate || "", extensionJustification: "", causeOfDelay: "", actionsAlreadyTaken: "", remainingActions: "", recoveryPlan: "", impactIfNotApproved: "", revisedScheduleSummary: "", managementProgressSummary: "", noEvidenceExplanation: "" };
}

function Skeleton() { return <div aria-label="Loading target-date extensions" className="grid gap-4"><div className="h-24 animate-pulse rounded-xl bg-slate-200" /><div className="h-64 animate-pulse rounded-xl bg-slate-200" /><div className="h-40 animate-pulse rounded-xl bg-slate-200" /></div>; }

function ErrorState({ message, onRetry }) { return <div className="rounded-2xl border border-red-200 bg-red-50 px-6 py-14 text-center"><AlertTriangle className="mx-auto text-red-600" size={36} /><h2 className="mt-3 text-xl font-bold text-slate-800">Target-date extensions unavailable</h2><p className="mx-auto mt-2 max-w-xl text-sm leading-6 text-slate-600">{message}</p><button className="mt-5 inline-flex h-10 items-center gap-2 rounded-lg bg-red-700 px-4 text-sm font-bold text-white" onClick={onRetry} type="button"><RefreshCw size={16} /> Retry</button></div>; }

function SummaryCard({ extension, onOpen }) {
  const version = extension.currentVersion || extension.versions?.[0];
  return <button className="w-full rounded-xl border border-slate-200 bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-sky-300 hover:shadow-md focus-visible:outline-2 focus-visible:outline-sky-600" onClick={onOpen} type="button"><div className="flex flex-wrap items-start justify-between gap-3"><div><p className="text-xs font-bold uppercase tracking-wide text-sky-700">{extension.displayCode}</p><h3 className="mt-1 font-bold text-slate-800">Request sequence {extension.requestSequence}</h3></div><ExtensionStatusBadge status={version?.status} /></div><dl className="mt-4 grid gap-3 text-xs text-slate-600 sm:grid-cols-2 lg:grid-cols-4"><div><dt className="font-bold text-slate-500">Baseline effective</dt><dd className="mt-1">{displayDate(extension.baselineEffectiveTargetDate)}</dd></div><div><dt className="font-bold text-slate-500">Requested target</dt><dd className="mt-1">{displayDate(version?.requestedTargetDate)}</dd></div><div><dt className="font-bold text-slate-500">Extension days</dt><dd className="mt-1">{version?.extensionDays ?? "Not available"}</dd></div><div><dt className="font-bold text-slate-500">Evidence</dt><dd className="mt-1">{version?.activeEvidenceLinks?.length ?? version?.evidenceLinks?.length ?? 0} linked</dd></div></dl><div className="mt-4 flex flex-wrap gap-2 text-xs"><span className="rounded-full bg-slate-100 px-2.5 py-1 font-bold text-slate-700">{version?.assessment?.recommendationCode ? version.assessment.recommendationCode === "RECOMMEND_APPROVAL" ? "Recommend approval" : "Recommend rejection" : "No assessment"}</span>{version?.decision && <span className={`rounded-full px-2.5 py-1 font-bold ${version.decision.decisionCode === "APPROVED" ? "bg-emerald-100 text-emerald-700" : "bg-red-100 text-red-700"}`}>{version.decision.decisionCode === "APPROVED" ? "Approved" : "Rejected"}</span>}{extension.resolvedAt && <span className="rounded-full bg-slate-100 px-2.5 py-1 font-bold text-slate-600">Resolved {displayDate(extension.resolvedAt)}</span>}</div></button>;
}

export default function CmsExtensionsPage() {
  const { recommendationId, extensionId } = useParams();
  const navigate = useNavigate();
  const { user } = useAuth();
  const toast = useToast();
  const detailMode = Boolean(extensionId);
  const canCreate = hasPermission(user, "cms.extension.create");
  const canUploadEvidence = hasPermission(user, "cms.extension-evidence.upload");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [recommendation, setRecommendation] = useState(null);
  const [caseContext, setCaseContext] = useState(null);
  const [listData, setListData] = useState(null);
  const [options, setOptions] = useState(null);
  const [extension, setExtension] = useState(null);
  const [form, setForm] = useState(null);
  const [formErrors, setFormErrors] = useState({});
  const [saving, setSaving] = useState(false);
  const [conflict, setConflict] = useState("");
  const [createOpen, setCreateOpen] = useState(false);
  const [selectedVersionId, setSelectedVersionId] = useState(null);
  const [tab, setTab] = useState("overview");
  const [workflow, setWorkflow] = useState("");
  const [workflowErrors, setWorkflowErrors] = useState({});
  const [confidentialityLevels, setConfidentialityLevels] = useState([]);

  const selectedVersion = extension?.versions?.find((item) => item.id === selectedVersionId) || extension?.currentVersion || extension?.versions?.[0];
  const actions = selectedVersion?.availableActions || [];
  const readOnly = !selectedVersion || selectedVersion.status !== "DRAFT" || !actions.includes("update");

  const loadList = useCallback(async () => {
    setLoading(true); setError("");
    try {
      const [recommendationResult, extensionsResult, optionsResult] = await Promise.all([
        cmsApi.getRecommendation(recommendationId),
        cmsApi.getExtensions(recommendationId),
        canCreate ? cmsApi.getExtensionOptions(recommendationId).catch(() => ({ creationAllowed: false, unavailableReasons: ["Creation options are unavailable in the current authorized scope."] })) : Promise.resolve(null),
      ]);
      setRecommendation(recommendationResult); setListData({ ...extensionsResult, permittedActions: optionsResult?.creationAllowed ? extensionsResult?.permittedActions : [] }); setCaseContext(extensionsResult?.caseContext || optionsResult?.caseContext); setOptions(optionsResult);
    } catch (requestError) {
      setError(requestError.status === 404 ? "This recommendation or its target-date extensions are unavailable or outside your authorized scope." : requestError.message || "The target-date extensions could not be loaded.");
    } finally { setLoading(false); }
  }, [canCreate, recommendationId]);

  const loadDetail = useCallback(async () => {
    setLoading(true); setError("");
    try {
      const [detailResult, recommendationResult] = await Promise.all([cmsApi.getExtension(extensionId), cmsApi.getRecommendation(recommendationId)]);
      setExtension(detailResult?.extension || null); setCaseContext(detailResult?.caseContext || null); setRecommendation(recommendationResult); const version = detailResult?.extension?.currentVersion || detailResult?.extension?.versions?.[0]; setSelectedVersionId((current) => current || version?.id); setForm(extensionFormFromVersion(version));
      if (canUploadEvidence) { const documents = await documentApi.list(); setConfidentialityLevels(documents.confidentialityLevels || []); }
    } catch (requestError) { setError(requestError.status === 404 ? "This target-date extension is unavailable or outside your authorized scope." : requestError.message || "The target-date extension could not be loaded."); }
    finally { setLoading(false); }
  }, [canUploadEvidence, extensionId, recommendationId]);

  // Loading is an intentional synchronization with the route's authoritative API state.
  // eslint-disable-next-line react-hooks/set-state-in-effect
  useEffect(() => { if (detailMode) loadDetail(); else loadList(); }, [detailMode, loadDetail, loadList]);

  async function refreshAfterConflict(requestError) {
    const stale = requestError instanceof ApiError && (requestError.status === 409 || requestError.errors?.lockVersion || requestError.errors?.sourceContext);
    if (!stale) return false;
    setConflict("Another user or source record changed this request. Your local values are preserved; reload the latest authoritative version before retrying.");
    await loadDetail();
    return true;
  }

  async function createRequest() {
    if (saving || !options) return;
    setSaving(true); setFormErrors({});
    try { const created = await cmsApi.createExtension(recommendationId, { ...form, lockVersion: options.caseLockVersion }); setCreateOpen(false); toast.success("Target-date extension draft created."); navigate(`/compliance-management/recommendations/${recommendationId}/extensions/${created.id}`); }
    catch (requestError) { setFormErrors(requestError.errors || {}); toast.error(requestError.message || "The extension draft could not be created."); }
    finally { setSaving(false); }
  }

  async function saveDraft() {
    if (saving || !extension || !selectedVersion || !form) return;
    setSaving(true); setFormErrors({}); setConflict("");
    try { const saved = await cmsApi.updateExtension(extension.id, selectedVersion.id, { ...form, lockVersion: selectedVersion.lockVersion }); setExtension(saved); setSelectedVersionId(saved.currentVersion?.id || selectedVersion.id); setForm(extensionFormFromVersion(saved.currentVersion || selectedVersion)); toast.success("Extension draft saved."); }
    catch (requestError) { setFormErrors(requestError.errors || {}); await refreshAfterConflict(requestError); toast.error(requestError.message || "The extension draft could not be saved."); }
    finally { setSaving(false); }
  }

  async function confirmWorkflow(values) {
    if (saving || !selectedVersion) return;
    const local = {};
    if (["return", "revise", "recommend", "approve", "reject"].includes(workflow) && !values.comment.trim()) local[workflow === "return" ? "returnReason" : workflow === "revise" ? "revisionReason" : workflow === "recommend" ? "assessmentSummary" : workflow === "reject" ? "rejectionReason" : "decisionComment"] = ["Required."];
    if (!values.confirmed) local.confirmation = ["Confirmation is required."];
    if (Object.keys(local).length) { setWorkflowErrors(local); return; }
    setSaving(true); setWorkflowErrors({});
    try {
      const payload = { lockVersion: selectedVersion.lockVersion };
      let result;
      if (workflow === "submit") result = await cmsApi.submitExtension(extension.id, selectedVersion.id, { ...payload, confirmation: true });
      if (workflow === "start-review") result = await cmsApi.startExtensionReview(extension.id, selectedVersion.id, { ...payload, reviewComment: values.comment.trim() || null });
      if (workflow === "return") result = await cmsApi.returnExtension(extension.id, selectedVersion.id, { ...payload, returnReason: values.comment.trim() });
      if (workflow === "recommend") result = await cmsApi.recommendExtension(extension.id, selectedVersion.id, { ...payload, recommendationCode: values.recommendationCode, assessmentSummary: values.comment.trim(), evidenceReviewSummary: values.evidenceReviewSummary?.trim(), feasibilityAssessment: values.feasibilityAssessment?.trim(), riskOfDelaySummary: values.riskOfDelaySummary?.trim(), conditionsOrObservations: values.conditionsOrObservations?.trim() || null, confirmation: true });
      if (workflow === "approve") result = await cmsApi.approveExtension(extension.id, selectedVersion.id, { ...payload, decisionComment: values.comment.trim(), overrideReason: values.overrideReason?.trim() || null, confirmation: true });
      if (workflow === "reject") result = await cmsApi.rejectExtension(extension.id, selectedVersion.id, { ...payload, rejectionReason: values.comment.trim(), overrideReason: values.overrideReason?.trim() || null, confirmation: true });
      if (workflow === "revise") { result = await cmsApi.createExtensionRevision(extension.id, selectedVersion.id, { ...payload, revisionReason: values.comment.trim() }); setSelectedVersionId(result.currentVersion?.id); setForm(extensionFormFromVersion(result.currentVersion)); }
      setExtension(result); setWorkflow(""); toast.success(workflow === "approve" ? "Extension approved; effective target date updated." : workflow === "reject" ? "Extension rejected; effective target date unchanged." : "Extension workflow action completed.");
    } catch (requestError) { setWorkflowErrors(requestError.errors || {}); await refreshAfterConflict(requestError); toast.error(requestError.message || "The extension workflow action could not be completed."); }
    finally { setSaving(false); }
  }

  async function uploadEvidence(values, reset) {
    if (saving || !selectedVersion) return;
    setSaving(true);
    try { const data = new FormData(); data.set("lockVersion", String(selectedVersion.lockVersion)); data.set("title", values.title); data.set("evidenceCategory", values.evidenceCategory); data.set("description", values.description || ""); data.set("sourceOrCustodian", values.sourceOrCustodian || ""); data.set("confidentialityLevelId", String(values.confidentialityLevelId)); data.set("file", values.file); const saved = await cmsApi.uploadExtensionEvidence(extension.id, selectedVersion.id, data); setExtension(saved); reset(); toast.success("Supporting evidence linked to the draft."); }
    catch (requestError) { await refreshAfterConflict(requestError); toast.error(requestError.message || "Supporting evidence could not be uploaded."); }
    finally { setSaving(false); }
  }

  async function removeEvidence(item) {
    const reason = window.prompt("Removal reason (the Core Document will be retained):");
    if (!reason?.trim()) return;
    setSaving(true);
    try { const saved = await cmsApi.removeExtensionEvidence(item.id, { lockVersion: selectedVersion.lockVersion, reason: reason.trim() }); setExtension(saved); toast.success("Draft evidence link removed; the Core Document was retained."); }
    catch (requestError) { await refreshAfterConflict(requestError); toast.error(requestError.message || "The evidence link could not be removed."); }
    finally { setSaving(false); }
  }

  if (loading && !(detailMode ? extension : listData)) return <Skeleton />;
  if (error && !(detailMode ? extension : listData)) return <ErrorState message={error} onRetry={detailMode ? loadDetail : loadList} />;

  if (!detailMode) {
    const extensions = listData?.extensions || [];
    return <div className="min-w-0"><RegistryHeader actions={<div className="flex flex-wrap gap-2"><Link className="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700" to={`/compliance-management/recommendations/${recommendationId}`}><ArrowLeft size={16} /> Recommendation</Link><button aria-label="Refresh target-date extensions" className="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700" disabled={loading} onClick={loadList} type="button"><RefreshCw className={loading ? "animate-spin" : ""} size={16} /> Refresh</button>{canCreate && listData?.permittedActions?.includes("create") && <button className="inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white disabled:opacity-60" disabled={!options?.creationAllowed} onClick={() => { setForm(extensionDraftForm(options)); setFormErrors({}); setCreateOpen(true); }} type="button"><Plus size={16} /> Create Extension Request</button>}</div>} description="Request and approve target-date extensions without changing the original recommendation target." icon={CalendarIcon} title="Target-Date Extensions" />{error && <div className="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">Refresh failed. Showing the last successfully loaded workspace.</div>}<ContextPanel context={caseContext} recommendation={recommendation} />{options && !options.creationAllowed && options.unavailableReasons?.length > 0 && <section className="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-4"><h2 className="font-bold text-amber-900">Creation unavailable</h2><ul className="mt-2 list-disc pl-5 text-sm leading-6 text-amber-800">{options.unavailableReasons.map((reason) => <li key={reason}>{reason}</li>)}</ul></section>}{extensions.length === 0 ? <section className="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center shadow-sm"><FileCheck2 className="mx-auto text-slate-300" size={42} /><h2 className="mt-3 text-lg font-bold text-slate-800">No target-date extension requests</h2><p className="mx-auto mt-2 max-w-xl text-sm leading-6 text-slate-500">Eligible responsible-office users can prepare one request family at a time. A requested date remains pending until final approval.</p></section> : <div className="grid gap-3">{extensions.map((item) => <SummaryCard extension={item} key={item.id} onOpen={() => navigate(`/compliance-management/recommendations/${recommendationId}/extensions/${item.id}`)} />)}</div>}<Modal description="Create a draft extension request. The backend selects and validates the accepted Action Plan and latest recorded Progress Update baseline." onClose={() => !saving && setCreateOpen(false)} open={createOpen} size="xl" title="Create target-date extension"><ExtensionForm errors={formErrors} form={form || extensionDraftForm(options)} onCancel={() => setCreateOpen(false)} onSave={createRequest} setForm={setForm} disabled={saving} /></Modal></div>;
  }

  if (error && extension) return <div className="min-w-0"><ErrorState message={error} onRetry={loadDetail} /></div>;
  if (!extension || !selectedVersion || !form) return <ErrorState message="The target-date extension version is unavailable." onRetry={loadDetail} />;
  return <div className="min-w-0"><RegistryHeader actions={<div className="flex flex-wrap gap-2"><Link className="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700" to={`/compliance-management/recommendations/${recommendationId}/extensions`}><ArrowLeft size={16} /> Extensions</Link><button aria-label="Refresh target-date extension" className="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700" disabled={loading || saving} onClick={loadDetail} type="button"><RefreshCw className={loading ? "animate-spin" : ""} size={16} /> Refresh</button></div>} description="Immutable versions, exact evidence, independent assessment, and controlled target-date decisions." icon={CalendarIcon} title={extension.displayCode} />{conflict && <div className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900"><span>{conflict}</span><button className="inline-flex h-9 items-center gap-2 rounded-lg bg-amber-700 px-3 text-xs font-bold text-white" onClick={loadDetail} type="button"><RefreshCw size={14} /> Reload latest</button></div>}<ContextPanel context={caseContext} recommendation={recommendation} version={selectedVersion} /><section className="mb-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><div className="flex flex-wrap items-center justify-between gap-3"><div className="flex flex-wrap items-center gap-2"><strong className="text-slate-800">Version {selectedVersion.versionNumber}</strong><ExtensionStatusBadge status={selectedVersion.status} />{selectedVersion.status !== "APPROVED" && selectedVersion.status !== "REJECTED" && <StatusBadge tone="warning">Effective date unchanged</StatusBadge>}</div><div className="flex flex-wrap gap-2">{actions.includes("update") && <button className="inline-flex h-10 items-center gap-2 rounded-lg border border-sky-300 bg-sky-50 px-4 text-sm font-bold text-sky-800" onClick={() => setTab("overview")} type="button"><Save size={16} /> Edit draft</button>}{actions.includes("submit") && <button className="inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white" disabled={saving} onClick={() => setWorkflow("submit")} type="button"><Send size={16} /> Submit</button>}{actions.includes("start-review") && <button className="inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white" disabled={saving} onClick={() => setWorkflow("start-review")} type="button">Start review</button>}{actions.includes("return") && <button className="inline-flex h-10 items-center gap-2 rounded-lg border border-amber-300 bg-white px-4 text-sm font-bold text-amber-800" disabled={saving} onClick={() => setWorkflow("return")} type="button"><RotateCcw size={16} /> Return</button>}{actions.includes("recommend") && <button className="inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white" disabled={saving} onClick={() => setWorkflow("recommend")} type="button">Record assessment</button>}{actions.includes("approve") && <button className="inline-flex h-10 items-center gap-2 rounded-lg bg-emerald-700 px-4 text-sm font-bold text-white" disabled={saving} onClick={() => setWorkflow("approve")} type="button"><CheckCircle2 size={16} /> Approve</button>}{actions.includes("reject") && <button className="inline-flex h-10 items-center gap-2 rounded-lg border border-red-300 bg-white px-4 text-sm font-bold text-red-700" disabled={saving} onClick={() => setWorkflow("reject")} type="button"><XCircle size={16} /> Reject</button>}{actions.includes("revise") && <button className="inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white" disabled={saving} onClick={() => setWorkflow("revise")} type="button"><FilePenLine size={16} /> Create revision</button>}</div></div></section><div aria-label="Target-date extension sections" className="mb-4 flex overflow-x-auto rounded-xl border border-slate-200 bg-white p-1 shadow-sm" role="tablist">{tabs.map(([key, label]) => <button aria-selected={tab === key} className={`inline-flex min-w-max flex-1 items-center justify-center gap-2 rounded-lg px-3 py-2.5 text-sm font-bold ${tab === key ? "bg-sky-700 text-white" : "text-slate-600 hover:bg-slate-50"}`} key={key} onClick={() => setTab(key)} role="tab" type="button">{label}</button>)}</div><main className="min-w-0 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">{tab === "overview" && (readOnly ? <div className="grid gap-3"><dl className="grid gap-3 sm:grid-cols-2"><ReadOnlyField label="Extension justification" value={selectedVersion.extensionJustification} wide /><ReadOnlyField label="Cause of delay" value={selectedVersion.causeOfDelay} /><ReadOnlyField label="Actions already taken" value={selectedVersion.actionsAlreadyTaken} /><ReadOnlyField label="Remaining actions" value={selectedVersion.remainingActions} /><ReadOnlyField label="Recovery plan" value={selectedVersion.recoveryPlan} /><ReadOnlyField label="Impact if not approved" value={selectedVersion.impactIfNotApproved} /><ReadOnlyField label="Revised schedule summary" value={selectedVersion.revisedScheduleSummary} wide /><ReadOnlyField label="Management progress summary" value={selectedVersion.managementProgressSummary} wide /><ReadOnlyField label="No-evidence explanation" value={selectedVersion.noEvidenceExplanation} wide /></dl><div className="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm leading-6 text-slate-700"><strong>Prepared by:</strong> {selectedVersion.preparedBy?.name || "Not available"} · <strong>Submitted:</strong> {selectedVersion.submittedBy?.name || "Not submitted"} {selectedVersion.submittedAt ? `on ${displayDate(selectedVersion.submittedAt, true)}` : ""}{selectedVersion.returnReason && <><br /><strong>Return reason:</strong> {selectedVersion.returnReason}</>}</div></div> : <ExtensionForm disabled={saving} errors={formErrors} form={form} onCancel={() => setForm(extensionFormFromVersion(selectedVersion))} onSave={saveDraft} setForm={setForm} title="Edit extension draft" />)}{tab === "evidence" && <EvidencePanel busy={saving} canUpload={canUploadEvidence && actions.includes("upload-evidence")} confidentialityLevels={confidentialityLevels} onDownload={(item) => cmsApi.downloadExtensionEvidence(item.id, item.file?.name || item.title).catch((requestError) => toast.error(requestError.message || "Evidence download failed."))} onRemove={removeEvidence} onUpload={uploadEvidence} version={selectedVersion} />}{tab === "assessment" && <div className="grid gap-3">{selectedVersion.assessment ? <dl className="grid gap-3 sm:grid-cols-2"><ReadOnlyField label="Recommendation" value={selectedVersion.assessment.recommendationCode === "RECOMMEND_APPROVAL" ? "Recommend approval" : "Recommend rejection"} /><ReadOnlyField label="Assessor" value={`${selectedVersion.assessment.assessor?.name || "Not available"} · ${displayDate(selectedVersion.assessment.assessedAt, true)}`} /><ReadOnlyField label="Assessment summary" value={selectedVersion.assessment.assessmentSummary} wide /><ReadOnlyField label="Evidence review summary" value={selectedVersion.assessment.evidenceReviewSummary} wide /><ReadOnlyField label="Feasibility assessment" value={selectedVersion.assessment.feasibilityAssessment} /><ReadOnlyField label="Risk of delay" value={selectedVersion.assessment.riskOfDelaySummary} /><ReadOnlyField label="Conditions or observations" value={selectedVersion.assessment.conditionsOrObservations} wide /></dl> : <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-12 text-center text-sm text-slate-500">No Compliance Monitor assessment has been recorded yet.</div>}<p className="rounded-lg border border-sky-200 bg-sky-50 p-3 text-sm text-sky-900">This recommendation is advisory. Only an authorized CIAS Management decision changes the effective target date.</p></div>}{tab === "decision" && <div className="grid gap-3">{selectedVersion.decision ? <dl className="grid gap-3 sm:grid-cols-2"><ReadOnlyField label="Final decision" value={selectedVersion.decision.decisionCode === "APPROVED" ? "Approved" : "Rejected"} /><ReadOnlyField label="Decision actor/date" value={`${selectedVersion.decision.decidedBy?.name || "Not available"} · ${displayDate(selectedVersion.decision.decidedAt, true)}`} /><ReadOnlyField label="Decision comment" value={selectedVersion.decision.decisionComment} wide /><ReadOnlyField label="Override reason" value={selectedVersion.decision.overrideReason} wide /><DateCard label="Previous effective target" value={selectedVersion.decision.previousEffectiveTargetDate} /><DateCard label="Approved target" value={selectedVersion.decision.approvedTargetDate} tone="amber" /><DateCard label="New effective target" value={selectedVersion.decision.newEffectiveTargetDate} tone="emerald" /></dl> : <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-12 text-center text-sm text-slate-500">No final decision has been recorded.</div>}{selectedVersion.decision?.decisionCode === "APPROVED" && <div className="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900">Approved: the effective target date changed to the exact requested date. The original target remains unchanged.</div>}{selectedVersion.decision?.decisionCode === "REJECTED" && <div className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">Rejected: the current effective target date remained unchanged.</div>}</div>}{tab === "history" && <div className="grid gap-5"><section><h2 className="font-bold text-slate-800">Request versions</h2><div className="mt-3 grid gap-2">{(extension.versions || []).map((version) => <button className={`flex w-full flex-wrap items-center justify-between gap-3 rounded-lg border p-3 text-left ${version.id === selectedVersion.id ? "border-sky-400 bg-sky-50" : "border-slate-200 bg-white"}`} key={version.id} onClick={() => { setSelectedVersionId(version.id); setTab("overview"); setForm(extensionFormFromVersion(version)); }} type="button"><span><strong>Version {version.versionNumber}</strong> · {displayDate(version.createdAt || version.submittedAt, true)}{version.revisionReason && <span className="block text-xs text-slate-500">Revision: {version.revisionReason}</span>}</span><ExtensionStatusBadge status={version.status} /></button>)}</div></section><section><h2 className="font-bold text-slate-800">Target-date history</h2><p className="mt-1 text-sm text-slate-500">Only formally recorded dates are shown here. Legacy effective history is not an approved extension.</p><HistoryPanel recommendationId={recommendationId} /></section></div>}</main><WorkflowDialog action={workflow} busy={saving} errors={workflowErrors} onClose={() => !saving && setWorkflow("")} onConfirm={confirmWorkflow} version={selectedVersion} /></div>;
}

function HistoryPanel({ recommendationId }) {
  const [history, setHistory] = useState(null);
  useEffect(() => { let active = true; cmsApi.getExtensionHistory(recommendationId).then((result) => { if (active) setHistory(result?.history || []); }).catch(() => { if (active) setHistory([]); }); return () => { active = false; }; }, [recommendationId]);
  if (!history) return <p className="mt-3 text-sm text-slate-500">Loading target-date history...</p>;
  if (!history.length) return <p className="mt-3 text-sm text-slate-500">No target-date history entries.</p>;
  return <div className="mt-3 grid gap-2">{history.map((item) => <article className="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm" key={item.id}><div className="flex flex-wrap items-center justify-between gap-2"><strong className="text-slate-800">{item.historyCode === "EXTENSION_APPROVED" ? "Approved extension" : item.historyCode === "LEGACY_EFFECTIVE_TARGET" ? "Legacy effective target" : "Initial target"}</strong><span className="text-xs text-slate-500">{displayDate(item.occurredAt, true)}</span></div><p className="mt-1 text-slate-700">{displayDate(item.previousTargetDate)} → {displayDate(item.newTargetDate)}</p><p className="mt-1 text-xs text-slate-500">Actor: {item.actor?.name || "System"}</p></article>)}</div>;
}

function CalendarIcon(props) { return <span {...props} aria-hidden="true">▣</span>; }
