import {
  BadgeCheck,
  CheckCircle2,
  FileCheck2,
  FilePenLine,
  History,
  LockKeyhole,
  Plus,
  RefreshCw,
  RotateCcw,
  Search,
  Send,
  ShieldCheck,
  UserRound,
  XCircle,
} from "lucide-react";
import { useCallback, useEffect, useMemo, useState } from "react";
import { useNavigate, useParams } from "react-router";
import { useAuth } from "../../auth/auth-context";
import DataTable from "../../components/ui/DataTable";
import Modal from "../../components/ui/Modal";
import RegistryHeader from "../../components/ui/RegistryHeader";
import StatusBadge from "../../components/ui/StatusBadge";
import { hasPermission } from "../../config/navigation";
import { ApiError, armisApi } from "../../services/api";
import { useToast } from "../../ui/toast-context";

const emptyForm = {
  resourceProfileId: "",
  competencyId: "",
  proficiencyLevel: "INTERMEDIATE",
  credentialType: "",
  credentialReference: "",
  issuer: "",
  issuedAt: "",
  expiresAt: "",
  evidenceDocumentVersionId: "",
  notes: "",
};

const statusTone = {
  DRAFT: "info",
  RETURNED: "warning",
  PENDING_VERIFICATION: "warning",
  VERIFIED: "success",
  EXPIRED: "inactive",
  REVOKED: "danger",
};

const statusLabel = (value) =>
  String(value || "Unknown")
    .replaceAll("_", " ")
    .toLowerCase()
    .replace(/(^|\s)\S/g, (letter) => letter.toUpperCase());

function dateLabel(value) {
  if (!value) return "Not set";
  const parsed = new Date(`${value}T00:00:00`);
  if (Number.isNaN(parsed.getTime())) return "Not set";
  return new Intl.DateTimeFormat("en-PH", {
    month: "short",
    day: "numeric",
    year: "numeric",
  }).format(parsed);
}

function dateTimeLabel(value) {
  if (!value) return "Not available";
  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) return "Not available";
  return new Intl.DateTimeFormat("en-PH", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(parsed);
}

function inputClass(extra = "") {
  return `mt-1 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100 disabled:bg-slate-100 ${extra}`;
}

function ErrorState({ message, onRetry }) {
  return (
    <section className="rounded-2xl border border-red-200 bg-red-50 px-6 py-12 text-center">
      <XCircle className="mx-auto text-red-600" size={34} />
      <h2 className="mt-3 font-bold text-red-900">ARMIS competency workspace unavailable</h2>
      <p className="mx-auto mt-2 max-w-xl text-sm text-red-700">{message}</p>
      <button className="mt-5 inline-flex h-10 items-center gap-2 rounded-lg bg-red-700 px-4 text-sm font-bold text-white hover:bg-red-800" onClick={onRetry} type="button">
        <RefreshCw size={16} /> Retry
      </button>
    </section>
  );
}

function StatCard({ label, value, note, icon: Icon, tone = "sky" }) {
  const tones = {
    sky: "border-sky-200 bg-sky-50 text-sky-800",
    emerald: "border-emerald-200 bg-emerald-50 text-emerald-800",
    amber: "border-amber-200 bg-amber-50 text-amber-800",
    slate: "border-slate-200 bg-white text-slate-800",
  };

  return (
    <section className={`rounded-xl border p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md ${tones[tone] || tones.sky}`}>
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className="text-xs font-bold uppercase tracking-wide opacity-70">{label}</p>
          <p className="mt-2 text-3xl font-bold">{value}</p>
          {note && <p className="mt-1 text-xs opacity-75">{note}</p>}
        </div>
        <span className="grid h-10 w-10 place-items-center rounded-lg bg-white/80 shadow-sm"><Icon size={20} /></span>
      </div>
    </section>
  );
}

function Field({ label, htmlFor, required = false, error, children, wide = false }) {
  return (
    <label className={`text-sm font-semibold text-slate-700 ${wide ? "sm:col-span-2" : ""}`} htmlFor={htmlFor}>
      {label}{required ? " *" : ""}
      {children}
      {error && <span className="mt-1 block text-xs font-semibold text-red-600">{Array.isArray(error) ? error[0] : error}</span>}
    </label>
  );
}

function CertificationForm({ form, setForm, resources, metadata, errors = {}, revisionMode = false }) {
  const selectedResource = resources.find((item) => String(item.id) === String(form.resourceProfileId));
  const selectedCompetency = (metadata.competencies || []).find((item) => String(item.id) === String(form.competencyId));
  const set = (name, value) => setForm((current) => ({ ...current, [name]: value }));

  return (
    <div className="grid gap-4 sm:grid-cols-2">
      <Field error={errors.resourceProfileId} htmlFor="armis-cert-resource" label="Resource profile" required>
        <select className={inputClass()} disabled={revisionMode} id="armis-cert-resource" onChange={(event) => set("resourceProfileId", event.target.value)} value={form.resourceProfileId}>
          <option value="">Select an active resource profile</option>
          {resources.filter((item) => item.status !== "ARCHIVED").map((item) => (
            <option key={item.id} value={item.id}>{item.resourceCode} — {item.user?.name || "Core user unavailable"}</option>
          ))}
        </select>
        {selectedResource && <span className="mt-1 block text-xs text-slate-500">{selectedResource.office?.name || "Office not available"} · {statusLabel(selectedResource.status)}</span>}
      </Field>
      <Field error={errors.competencyId} htmlFor="armis-cert-competency" label="Core competency" required>
        <select className={inputClass()} disabled={revisionMode} id="armis-cert-competency" onChange={(event) => set("competencyId", event.target.value)} value={form.competencyId}>
          <option value="">Select a Core catalogue item</option>
          {(metadata.competencies || []).map((item) => <option key={item.id} value={item.id}>{item.label} ({item.code})</option>)}
        </select>
        {selectedCompetency?.description && <span className="mt-1 block text-xs leading-5 text-slate-500">{selectedCompetency.description}</span>}
      </Field>
      <Field error={errors.proficiencyLevel} htmlFor="armis-cert-level" label="Proficiency level" required>
        <select className={inputClass()} id="armis-cert-level" onChange={(event) => set("proficiencyLevel", event.target.value)} value={form.proficiencyLevel}>
          {(metadata.proficiencyLevels || []).map((item) => <option key={item.code} value={item.code}>{item.label}</option>)}
        </select>
      </Field>
      <Field error={errors.credentialType} htmlFor="armis-cert-type" label="Credential or certification type">
        <input className={inputClass()} id="armis-cert-type" maxLength={80} onChange={(event) => set("credentialType", event.target.value)} placeholder="e.g. CPA, CISA, training certificate" value={form.credentialType} />
      </Field>
      <Field error={errors.credentialReference} htmlFor="armis-cert-reference" label="Credential reference">
        <input className={inputClass()} id="armis-cert-reference" maxLength={120} onChange={(event) => set("credentialReference", event.target.value)} placeholder="Certificate or license number" value={form.credentialReference} />
      </Field>
      <Field error={errors.issuer} htmlFor="armis-cert-issuer" label="Issuing body">
        <input className={inputClass()} id="armis-cert-issuer" maxLength={200} onChange={(event) => set("issuer", event.target.value)} placeholder="Issuing institution" value={form.issuer} />
      </Field>
      <Field error={errors.issuedAt} htmlFor="armis-cert-issued" label="Issued date">
        <input className={inputClass()} id="armis-cert-issued" onChange={(event) => set("issuedAt", event.target.value)} type="date" value={form.issuedAt} />
      </Field>
      <Field error={errors.expiresAt} htmlFor="armis-cert-expires" label="Expiry date">
        <input className={inputClass()} id="armis-cert-expires" min={form.issuedAt || undefined} onChange={(event) => set("expiresAt", event.target.value)} type="date" value={form.expiresAt} />
      </Field>
      <Field error={errors.evidenceDocumentVersionId} htmlFor="armis-cert-evidence" label="Core Document Version ID" required wide>
        <input className={inputClass()} id="armis-cert-evidence" inputMode="numeric" onChange={(event) => set("evidenceDocumentVersionId", event.target.value)} placeholder="Exact immutable DocumentVersion ID" value={form.evidenceDocumentVersionId} />
        <span className="mt-1 block text-xs leading-5 text-slate-500">The backend validates this against an active Core Document Version. ARMIS does not copy or replace the Core file.</span>
      </Field>
      <Field error={errors.notes} htmlFor="armis-cert-notes" label="Notes" wide>
        <textarea className={inputClass("min-h-24 py-3")} id="armis-cert-notes" maxLength={5000} onChange={(event) => set("notes", event.target.value)} value={form.notes} />
      </Field>
    </div>
  );
}

function EvidencePanel({ detail }) {
  return (
    <section className="rounded-xl border border-sky-200 bg-sky-50 p-4">
      <div className="flex items-center gap-2 text-sky-900"><FileCheck2 size={18} /><h3 className="font-bold">Exact Core evidence</h3></div>
      {detail.evidenceDocumentVersionId ? (
        <dl className="mt-3 grid gap-3 text-sm sm:grid-cols-2">
          <div><dt className="text-xs font-bold uppercase tracking-wide text-sky-700">Document Version ID</dt><dd className="mt-1 font-semibold text-sky-950">{detail.evidenceDocumentVersionId}</dd></div>
          <div><dt className="text-xs font-bold uppercase tracking-wide text-sky-700">File</dt><dd className="mt-1 font-semibold text-sky-950">{detail.evidenceDocument?.original_file_name || "Metadata available in Core"}</dd></div>
          <div><dt className="text-xs font-bold uppercase tracking-wide text-sky-700">Checksum</dt><dd className="mt-1 break-all font-mono text-xs text-sky-950">{detail.evidenceDocument?.checksum_sha256 || "Preserved by Core"}</dd></div>
          <div><dt className="text-xs font-bold uppercase tracking-wide text-sky-700">Version</dt><dd className="mt-1 font-semibold text-sky-950">{detail.evidenceDocument?.version_number || "Exact version"}</dd></div>
        </dl>
      ) : <p className="mt-2 text-sm text-sky-800">No evidence is linked yet. Submission is blocked until an exact active Core Document Version is selected.</p>}
    </section>
  );
}

function DetailPanel({ detail, events, canManage, canVerify, onEdit, onSubmit, onReview, onRevise }) {
  if (!detail) return null;
  const canEdit = canManage && ["DRAFT", "RETURNED"].includes(detail.status);
  const canSubmit = canManage && ["DRAFT", "RETURNED"].includes(detail.status);
  const canRevise = canManage && detail.status === "VERIFIED" && detail.isCurrentRevision;
  const canReview = canVerify && detail.status === "PENDING_VERIFICATION";
  const canRevoke = canVerify && ["VERIFIED", "EXPIRED"].includes(detail.status);

  return (
    <section className="mt-5 rounded-2xl border border-slate-200 bg-white shadow-sm" data-testid="armis-competency-detail">
      <header className="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 p-5">
        <div className="flex min-w-0 items-start gap-3">
          <span className="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-emerald-100 text-emerald-700"><BadgeCheck size={22} /></span>
          <div className="min-w-0">
            <p className="text-xs font-bold uppercase tracking-wide text-slate-500">Competency certification</p>
            <h3 className="mt-1 text-xl font-bold text-slate-900">{detail.label || detail.code || "Core competency"}</h3>
            <p className="mt-1 text-sm text-slate-600">{detail.resourceCode || "Resource unavailable"} · {detail.resourceUser?.name || "Core user unavailable"}</p>
          </div>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <StatusBadge tone={statusTone[detail.status]}>{statusLabel(detail.status)}</StatusBadge>
          {detail.isCurrentRevision && <StatusBadge tone="info">Current revision</StatusBadge>}
          {canEdit && <button className="inline-flex h-9 items-center gap-2 rounded-lg border border-slate-300 px-3 text-xs font-bold text-slate-700 hover:bg-slate-50" onClick={onEdit} type="button"><FilePenLine size={14} /> Edit draft</button>}
        </div>
      </header>

      <div className="grid gap-4 p-5 lg:grid-cols-[minmax(0,1.35fr)_minmax(18rem,0.8fr)]">
        <div className="space-y-4">
          <div className="grid gap-3 sm:grid-cols-3">
            <div className="rounded-xl bg-slate-50 p-3"><p className="text-xs font-bold uppercase tracking-wide text-slate-500">Proficiency</p><p className="mt-1 text-sm font-semibold text-slate-800">{statusLabel(detail.proficiencyLevel)}</p></div>
            <div className="rounded-xl bg-slate-50 p-3"><p className="text-xs font-bold uppercase tracking-wide text-slate-500">Revision</p><p className="mt-1 text-sm font-semibold text-slate-800">v{detail.versionNumber}</p></div>
            <div className="rounded-xl bg-slate-50 p-3"><p className="text-xs font-bold uppercase tracking-wide text-slate-500">Expires</p><p className="mt-1 text-sm font-semibold text-slate-800">{dateLabel(detail.expiresAt)}</p></div>
          </div>
          <section className="rounded-xl border border-slate-200 p-4">
            <div className="flex items-center gap-2"><ShieldCheck className="text-emerald-700" size={18} /><h3 className="font-bold text-slate-900">Certification details</h3></div>
            <dl className="mt-3 grid gap-3 text-sm sm:grid-cols-2">
              <div><dt className="text-xs font-bold uppercase tracking-wide text-slate-500">Credential type</dt><dd className="mt-1 text-slate-800">{detail.credentialType || "Not recorded"}</dd></div>
              <div><dt className="text-xs font-bold uppercase tracking-wide text-slate-500">Reference</dt><dd className="mt-1 text-slate-800">{detail.credentialReference || "Not recorded"}</dd></div>
              <div><dt className="text-xs font-bold uppercase tracking-wide text-slate-500">Issuing body</dt><dd className="mt-1 text-slate-800">{detail.issuer || "Not recorded"}</dd></div>
              <div><dt className="text-xs font-bold uppercase tracking-wide text-slate-500">Issued</dt><dd className="mt-1 text-slate-800">{dateLabel(detail.issuedAt)}</dd></div>
              <div className="sm:col-span-2"><dt className="text-xs font-bold uppercase tracking-wide text-slate-500">Notes</dt><dd className="mt-1 whitespace-pre-wrap leading-6 text-slate-700">{detail.notes || "No notes recorded."}</dd></div>
            </dl>
          </section>
          <EvidencePanel detail={detail} />
          {(canEdit || canSubmit || canReview || canRevise || canRevoke) && (
            <section className="rounded-xl border border-slate-200 p-4">
              <h3 className="font-bold text-slate-900">Available actions</h3>
              <div className="mt-3 flex flex-wrap gap-2">
                {canSubmit && <button className="inline-flex h-9 items-center gap-2 rounded-lg bg-sky-700 px-3 text-xs font-bold text-white hover:bg-sky-800" onClick={onSubmit} type="button"><Send size={14} /> Submit for verification</button>}
                {canReview && <><button className="inline-flex h-9 items-center gap-2 rounded-lg bg-emerald-700 px-3 text-xs font-bold text-white hover:bg-emerald-800" onClick={() => onReview("VERIFY")} type="button"><CheckCircle2 size={14} /> Verify</button><button className="inline-flex h-9 items-center gap-2 rounded-lg border border-amber-300 bg-amber-50 px-3 text-xs font-bold text-amber-800 hover:bg-amber-100" onClick={() => onReview("RETURN")} type="button"><RotateCcw size={14} /> Return</button></>}
                {canRevoke && <button className="inline-flex h-9 items-center gap-2 rounded-lg border border-red-300 bg-red-50 px-3 text-xs font-bold text-red-800 hover:bg-red-100" onClick={() => onReview("REVOKE")} type="button"><LockKeyhole size={14} /> Revoke</button>}
                {canRevise && <button className="inline-flex h-9 items-center gap-2 rounded-lg border border-sky-300 bg-sky-50 px-3 text-xs font-bold text-sky-800 hover:bg-sky-100" onClick={onRevise} type="button"><FilePenLine size={14} /> Create correction revision</button>}
              </div>
              <p className="mt-3 text-xs leading-5 text-slate-500">Verified records are immutable. Corrections create a new Draft revision and preserve this exact version.</p>
            </section>
          )}
        </div>
        <aside className="rounded-xl border border-slate-200 p-4">
          <div className="flex items-center gap-2"><History className="text-sky-700" size={17} /><h3 className="font-bold text-slate-900">Certification history</h3></div>
          <div className="mt-3 space-y-3">
            {events.length === 0 && <p className="text-sm text-slate-500">No workflow events recorded.</p>}
            {events.map((event) => <div className="border-l-2 border-sky-200 pl-3" key={event.id}><div className="flex flex-wrap items-center justify-between gap-2"><p className="text-xs font-bold text-slate-800">{statusLabel(event.eventCode)}</p><p className="text-[11px] text-slate-500">{dateTimeLabel(event.createdAt)}</p></div><p className="mt-1 text-xs text-slate-500">{event.actor?.name || "System"}{event.fromStatus || event.toStatus ? ` · ${statusLabel(event.fromStatus || "New")} → ${statusLabel(event.toStatus || "")}` : ""}</p>{event.reason && <p className="mt-1 text-xs leading-5 text-slate-600">{event.reason}</p>}</div>)}
          </div>
          <div className="mt-5 rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs leading-5 text-slate-600"><strong className="text-slate-800">Review separation</strong><p className="mt-1">The submitter and resource owner cannot verify their own certification. Final verification is an independent professional decision.</p></div>
        </aside>
      </div>
    </section>
  );
}

export default function ArmisCompetencyPage() {
  const { user } = useAuth();
  const toast = useToast();
  const navigate = useNavigate();
  const { competencyId } = useParams();
  const canManage = hasPermission(user, "armis.competency.manage");
  const canVerify = hasPermission(user, "armis.competency.verify");
  const [records, setRecords] = useState([]);
  const [resources, setResources] = useState([]);
  const [metadata, setMetadata] = useState({ competencies: [], proficiencyLevels: [], statuses: [] });
  const [detail, setDetail] = useState(null);
  const [events, setEvents] = useState([]);
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("");
  const [loading, setLoading] = useState(true);
  const [detailLoading, setDetailLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [formErrors, setFormErrors] = useState({});
  const [form, setForm] = useState(emptyForm);
  const [formOpen, setFormOpen] = useState(false);
  const [revisionMode, setRevisionMode] = useState(false);
  const [reviewDecision, setReviewDecision] = useState("");
  const [reviewNotes, setReviewNotes] = useState("");

  const loadRegistry = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const [competencyData, metadataData, resourceData] = await Promise.all([
        armisApi.getCompetencies(),
        armisApi.getCompetencyMetadata(),
        armisApi.getResources({ includeArchived: false }),
      ]);
      setRecords(Array.isArray(competencyData) ? competencyData : []);
      setMetadata(metadataData || { competencies: [], proficiencyLevels: [], statuses: [] });
      setResources(Array.isArray(resourceData) ? resourceData : []);
    } catch (requestError) {
      setError(requestError.message || "Unable to load ARMIS competency records.");
    } finally {
      setLoading(false);
    }
  }, []);

  const loadDetail = useCallback(async (id) => {
    if (!id) {
      setDetail(null);
      setEvents([]);
      return;
    }
    setDetailLoading(true);
    try {
      const [record, history] = await Promise.all([armisApi.getCompetency(id), armisApi.getCompetencyEvents(id)]);
      setDetail(record);
      setEvents(Array.isArray(history) ? history : []);
    } catch (requestError) {
      setError(requestError.message || "Unable to load this ARMIS competency.");
      setDetail(null);
    } finally {
      setDetailLoading(false);
    }
  }, []);

  useEffect(() => {
    const timer = window.setTimeout(() => loadRegistry().catch(() => undefined), 0);
    return () => window.clearTimeout(timer);
  }, [loadRegistry]);

  useEffect(() => {
    const timer = window.setTimeout(() => loadDetail(competencyId), 0);
    return () => window.clearTimeout(timer);
  }, [competencyId, loadDetail]);

  const filteredRecords = useMemo(() => {
    const term = search.trim().toLowerCase();
    return records.filter((record) => {
      if (status && record.status !== status) return false;
      if (!term) return true;
      return [record.code, record.label, record.resourceCode, record.resourceUser?.name, record.resourceUser?.employee_id, record.proficiencyLevel, record.credentialReference]
        .some((value) => String(value ?? "").toLowerCase().includes(term));
    });
  }, [records, search, status]);

  const stats = useMemo(() => ({
    total: records.length,
    verified: records.filter((record) => record.status === "VERIFIED").length,
    pending: records.filter((record) => record.status === "PENDING_VERIFICATION").length,
    drafts: records.filter((record) => ["DRAFT", "RETURNED"].includes(record.status)).length,
  }), [records]);

  function openCreate() {
    setRevisionMode(false);
    setForm(emptyForm);
    setFormErrors({});
    setFormOpen(true);
  }

  function openEdit() {
    if (!detail) return;
    setRevisionMode(false);
    setForm({
      resourceProfileId: String(detail.resourceProfileId || ""),
      competencyId: String(detail.competencyId || ""),
      proficiencyLevel: detail.proficiencyLevel || "INTERMEDIATE",
      credentialType: detail.credentialType || "",
      credentialReference: detail.credentialReference || "",
      issuer: detail.issuer || "",
      issuedAt: detail.issuedAt || "",
      expiresAt: detail.expiresAt || "",
      evidenceDocumentVersionId: String(detail.evidenceDocumentVersionId || ""),
      notes: detail.notes || "",
    });
    setFormErrors({});
    setFormOpen(true);
  }

  function openRevision() {
    if (!detail) return;
    setRevisionMode(true);
    setForm({
      resourceProfileId: String(detail.resourceProfileId || ""),
      competencyId: String(detail.competencyId || ""),
      proficiencyLevel: detail.proficiencyLevel || "INTERMEDIATE",
      credentialType: detail.credentialType || "",
      credentialReference: detail.credentialReference || "",
      issuer: detail.issuer || "",
      issuedAt: detail.issuedAt || "",
      expiresAt: detail.expiresAt || "",
      evidenceDocumentVersionId: String(detail.evidenceDocumentVersionId || ""),
      notes: "",
    });
    setFormErrors({});
    setFormOpen(true);
  }

  async function save() {
    setSaving(true);
    setFormErrors({});
    try {
      const payload = {
        resourceProfileId: Number(form.resourceProfileId),
        competencyId: Number(form.competencyId),
        proficiencyLevel: form.proficiencyLevel,
        credentialType: form.credentialType || null,
        credentialReference: form.credentialReference || null,
        issuer: form.issuer || null,
        issuedAt: form.issuedAt || null,
        expiresAt: form.expiresAt || null,
        evidenceDocumentVersionId: form.evidenceDocumentVersionId ? Number(form.evidenceDocumentVersionId) : null,
        notes: form.notes || null,
      };
      const saved = revisionMode
        ? await armisApi.reviseCompetency(detail.id, { ...payload, lockVersion: detail.lockVersion })
        : detail && ["DRAFT", "RETURNED"].includes(detail.status)
          ? await armisApi.updateCompetency(detail.id, { ...payload, lockVersion: detail.lockVersion })
          : await armisApi.createCompetency(payload);
      toast.success(revisionMode ? "Correction revision created." : detail ? "Competency draft updated." : "Competency draft created.");
      setFormOpen(false);
      await loadRegistry();
      navigate(`/audit-resource-management/competencies/${saved.id}`);
    } catch (requestError) {
      if (requestError instanceof ApiError) setFormErrors(requestError.errors || {});
      toast.error(requestError.message || "The ARMIS competency could not be saved.");
    } finally {
      setSaving(false);
    }
  }

  async function submit() {
    if (!detail) return;
    setSaving(true);
    try {
      const updated = await armisApi.submitCompetency(detail.id, detail.lockVersion);
      toast.success("Competency submitted for independent verification.");
      await Promise.all([loadRegistry(), loadDetail(updated.id)]);
    } catch (requestError) {
      toast.error(requestError.message || "The competency could not be submitted.");
    } finally {
      setSaving(false);
    }
  }

  async function review() {
    if (!detail || !reviewDecision) return;
    if (["RETURN", "REVOKE"].includes(reviewDecision) && !reviewNotes.trim()) {
      toast.error("A review explanation is required for this decision.");
      return;
    }
    setSaving(true);
    try {
      const updated = await armisApi.reviewCompetency(detail.id, { decision: reviewDecision, lockVersion: detail.lockVersion, notes: reviewNotes.trim() || null });
      toast.success(`Competency ${reviewDecision === "VERIFY" ? "verified" : reviewDecision === "RETURN" ? "returned" : "revoked"}.`);
      setReviewDecision("");
      setReviewNotes("");
      await Promise.all([loadRegistry(), loadDetail(updated.id)]);
    } catch (requestError) {
      toast.error(requestError.message || "The competency review could not be completed.");
    } finally {
      setSaving(false);
    }
  }

  const columns = [
    { key: "resourceCode", label: "Resource", render: (row) => <div><p className="font-bold text-slate-800">{row.resourceCode || "—"}</p><p className="mt-1 text-xs text-slate-500">{row.resourceUser?.name || "Core user unavailable"}</p></div> },
    { key: "label", label: "Competency", render: (row) => <div><p className="font-semibold text-slate-800">{row.label || row.code || "—"}</p><p className="mt-1 text-xs text-slate-500">{row.code || "Core catalogue"}</p></div> },
    { key: "proficiencyLevel", label: "Proficiency", render: (row) => statusLabel(row.proficiencyLevel) },
    { key: "status", label: "Status", render: (row) => <StatusBadge tone={statusTone[row.status]}>{statusLabel(row.status)}</StatusBadge> },
    { key: "expiresAt", label: "Expiry", render: (row) => dateLabel(row.expiresAt) },
    { key: "versionNumber", label: "Version", render: (row) => `v${row.versionNumber}` },
    { key: "actions", label: "", sortable: false, className: "text-right", render: (row) => <button className="inline-flex h-8 items-center rounded-lg border border-slate-300 px-3 text-xs font-bold text-slate-700 hover:border-sky-300 hover:bg-sky-50" onClick={() => navigate(`/audit-resource-management/competencies/${row.id}`)} type="button">Open</button> },
  ];

  return (
    <main className="mx-auto min-w-0 max-w-[1500px] p-4 sm:p-6">
      <RegistryHeader actions={<div className="flex flex-wrap gap-2"><button aria-label="Refresh ARMIS competencies" className="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700 hover:bg-slate-50 disabled:opacity-60" disabled={loading} onClick={loadRegistry} type="button"><RefreshCw className={loading ? "animate-spin" : ""} size={16} /> Refresh</button>{canManage && !competencyId && <button className="inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white hover:bg-sky-800" onClick={openCreate} type="button"><Plus size={16} /> New certification draft</button>}</div>} description="Maintain scope-aware competency claims and certification evidence without changing the Core document record." icon={BadgeCheck} title="ARMIS Competencies & Certifications" />
      <section className="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900"><strong>Controlled certification ledger:</strong> verified competency records are immutable. Corrections create new revisions, and independent reviewers make the final verification decision.</section>
      {error && !detail && <ErrorState message={error} onRetry={loadRegistry} />}
      {detailLoading && competencyId && <div className="rounded-xl border border-slate-200 bg-white p-10 text-center text-sm font-semibold text-slate-500">Loading competency detail...</div>}
      {!detailLoading && competencyId && detail && <><button className="mb-3 inline-flex items-center gap-2 text-sm font-bold text-sky-700 hover:text-sky-900" onClick={() => navigate("/audit-resource-management/competencies")} type="button"><UserRound size={15} /> Back to competency registry</button><DetailPanel canManage={canManage} canVerify={canVerify} detail={detail} events={events} onEdit={openEdit} onReview={setReviewDecision} onRevise={openRevision} onSubmit={submit} /></>}
      {!competencyId && <><div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"><StatCard icon={BadgeCheck} label="Current claims" note="Within your authorized scope" value={stats.total} tone="sky" /><StatCard icon={CheckCircle2} label="Verified" note="Independent verification complete" value={stats.verified} tone="emerald" /><StatCard icon={ShieldCheck} label="Awaiting verification" note="Pending reviewer decision" value={stats.pending} tone="amber" /><StatCard icon={FilePenLine} label="Drafts and returned" note="Preparation or correction needed" value={stats.drafts} tone="slate" /></div><section className="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div className="flex flex-col gap-3 border-b border-slate-200 p-4 lg:flex-row lg:items-center lg:justify-between"><div><h3 className="font-bold text-slate-900">Competency registry</h3><p className="mt-1 text-xs text-slate-500">{filteredRecords.length} current certification claim{filteredRecords.length === 1 ? "" : "s"} · Core catalogue and evidence remain authoritative.</p></div><div className="flex flex-col gap-2 sm:flex-row"><label className="relative"><Search className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" size={16} /><input aria-label="Search ARMIS competencies" className="h-10 w-full rounded-lg border border-slate-300 pl-9 pr-3 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100 sm:w-72" onChange={(event) => setSearch(event.target.value)} placeholder="Search resource or competency" value={search} /></label><select aria-label="Filter competency status" className="h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-700 outline-none focus:border-sky-500" onChange={(event) => setStatus(event.target.value)} value={status}><option value="">All statuses</option>{(metadata.statuses || []).map((item) => <option key={item.code} value={item.code}>{item.label}</option>)}</select></div></div><DataTable columns={columns} emptyMessage="No current competency claims found." loading={loading} onRowClick={(row) => navigate(`/audit-resource-management/competencies/${row.id}`)} rows={filteredRecords} /></section></>}

      <Modal description={revisionMode ? "The prior verified version remains immutable. This form creates a new Draft revision in the same competency family." : "Save a Draft certification claim first. Submission and independent verification are separate controlled actions."} footer={<><button className="h-10 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700" disabled={saving} onClick={() => !saving && setFormOpen(false)} type="button">Cancel</button><button className="h-10 rounded-lg bg-sky-700 px-5 text-sm font-bold text-white disabled:opacity-60" disabled={saving || !form.resourceProfileId || !form.competencyId || !form.evidenceDocumentVersionId} onClick={save} type="button">{saving ? "Saving..." : revisionMode ? "Create correction revision" : detail ? "Save draft" : "Create draft"}</button></>} onClose={() => !saving && setFormOpen(false)} open={formOpen} size="xl" title={revisionMode ? "Create competency correction revision" : detail ? "Edit competency draft" : "Create competency certification draft"}><CertificationForm errors={formErrors} form={form} metadata={metadata} resources={resources} revisionMode={revisionMode} setForm={setForm} /></Modal>

      <Modal description={reviewDecision === "VERIFY" ? "Confirm that you independently verified the exact Core evidence and certification details." : reviewDecision === "RETURN" ? "Return the claim for correction. A clear explanation is required." : "Revoke the current professional certification decision. A clear explanation is required."} footer={<><button className="h-10 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700" disabled={saving} onClick={() => !saving && setReviewDecision("")} type="button">Cancel</button><button className={`h-10 rounded-lg px-5 text-sm font-bold text-white disabled:opacity-60 ${reviewDecision === "VERIFY" ? "bg-emerald-700" : "bg-sky-700"}`} disabled={saving || (["RETURN", "REVOKE"].includes(reviewDecision) && !reviewNotes.trim())} onClick={review} type="button">{saving ? "Saving..." : reviewDecision === "VERIFY" ? "Confirm verification" : reviewDecision === "RETURN" ? "Return claim" : "Revoke certification"}</button></>} onClose={() => !saving && setReviewDecision("")} open={Boolean(reviewDecision)} size="md" title={`${statusLabel(reviewDecision)} competency`}><label className="text-sm font-semibold text-slate-700">Reviewer explanation{reviewDecision !== "VERIFY" ? " *" : ""}<textarea className={inputClass("min-h-28 py-3")} onChange={(event) => setReviewNotes(event.target.value)} placeholder={reviewDecision === "VERIFY" ? "Optional verification note" : "Explain the required correction or revocation basis."} value={reviewNotes} /></label></Modal>
    </main>
  );
}
