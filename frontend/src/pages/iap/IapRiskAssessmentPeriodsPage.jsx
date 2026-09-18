import { useCallback, useEffect, useMemo, useState } from "react";
import {
  Archive,
  ArrowRight,
  CheckCircle2,
  ClipboardCheck,
  FileCheck2,
  FileText,
  FilterX,
  History,
  LockKeyhole,
  Pencil,
  Plus,
  RotateCcw,
  Search,
  ShieldCheck,
  SlidersHorizontal,
  Upload,
} from "lucide-react";
import { useAuth } from "../../auth/auth-context";
import ConfirmDialog from "../../components/ui/ConfirmDialog";
import DataTable from "../../components/ui/DataTable";
import Modal from "../../components/ui/Modal";
import RegistryHeader from "../../components/ui/RegistryHeader";
import SearchableSelect from "../../components/ui/SearchableSelect";
import StatusBadge from "../../components/ui/StatusBadge";
import SummaryCard from "../../components/ui/SummaryCard";
import { hasPermission } from "../../config/navigation";
import {
  ApiError,
  auditUniverseApi,
  masterListApi,
  riskPeriodApi,
} from "../../services/api";
import { useToast } from "../../ui/toast-context";
import useRecordView from "../../hooks/useRecordView";

const labels = {
  DRAFT: "Draft",
  OPEN: "Open",
  PENDING_VALIDATION: "Pending Validation",
  RETURNED_FOR_REVISION: "Returned for Revision",
  RESUBMITTED: "Resubmitted",
  VALIDATED: "Validated",
  LOCKED: "Locked",
  ARCHIVED: "Archived",
};
const tones = {
  DRAFT: "inactive",
  OPEN: "info",
  PENDING_VALIDATION: "warning",
  RETURNED_FOR_REVISION: "danger",
  RESUBMITTED: "warning",
  VALIDATED: "success",
  LOCKED: "active",
  ARCHIVED: "danger",
};
const defaultWeights = {
  FINANCIAL_MATERIALITY: 15,
  PRIOR_FINDINGS: 15,
  CONTROL_MATURITY: 15,
  LEGAL_REGULATORY: 10,
  COMPLEXITY_CHANGE: 10,
  FRAUD_INTEGRITY: 10,
  PUBLIC_SERVICE_IMPACT: 10,
  TIME_SINCE_AUDIT: 5,
  MANAGEMENT_CONCERN: 5,
  IT_DATA_DEPENDENCY: 5,
};

const inputClass =
  "min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100";
const buttonPrimary =
  "inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-sky-800 disabled:cursor-not-allowed disabled:opacity-50";
const buttonSecondary =
  "inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50";

function riskTone(level) {
  return {
    LOW: "bg-emerald-100 text-emerald-800",
    MEDIUM: "bg-amber-100 text-amber-800",
    HIGH: "bg-orange-100 text-orange-800",
    CRITICAL: "bg-red-100 text-red-800",
  }[level] ?? "bg-slate-100 text-slate-700";
}

function scoreLevel(score) {
  return score >= 4 ? "CRITICAL" : score >= 3 ? "HIGH" : score >= 2 ? "MEDIUM" : "LOW";
}

function dateTime(value) {
  if (!value) return "—";
  return new Intl.DateTimeFormat("en-PH", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(value));
}

function PeriodForm({ criteria, initial, errors, onSubmit, onCancel, saving }) {
  const [form, setForm] = useState(() => ({
    periodCode: initial?.periodCode ?? "",
    name: initial?.name ?? "",
    assessmentYear: initial?.assessmentYear ?? new Date().getFullYear(),
    startDate: initial?.startDate ?? "",
    endDate: initial?.endDate ?? "",
    instructions: initial?.instructions ?? "",
    lockVersion: initial?.lockVersion,
    criteria: (initial?.criteria?.length
      ? initial.criteria
      : criteria.map((item) => ({
          criterionId: item.id,
          code: item.code,
          label: item.label,
          weight: defaultWeights[item.code] ?? 0,
        }))).map((item) => ({ ...item })),
  }));
  const total = form.criteria.reduce((sum, item) => sum + Number(item.weight || 0), 0);

  return (
    <form
      className="space-y-5"
      onSubmit={(event) => {
        event.preventDefault();
        onSubmit({
          ...form,
          assessmentYear: Number(form.assessmentYear),
          criteria: form.criteria.map(({ criterionId, weight }) => ({
            criterionId,
            weight: Number(weight),
          })),
        });
      }}
    >
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="Period code" error={errors.periodCode}>
          <input className={inputClass} value={form.periodCode} onChange={(e) => setForm({ ...form, periodCode: e.target.value })} placeholder="RISK-2027" />
        </Field>
        <Field label="Assessment year" required error={errors.assessmentYear}>
          <input className={inputClass} type="number" min="2000" max="2200" value={form.assessmentYear} onChange={(e) => setForm({ ...form, assessmentYear: e.target.value })} />
        </Field>
      </div>
      <Field label="Period name" required error={errors.name}>
        <input className={inputClass} value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} placeholder="2027 Audit Universe Risk Assessment" />
      </Field>
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="Start date" required error={errors.startDate}>
          <input className={inputClass} type="date" value={form.startDate} onChange={(e) => setForm({ ...form, startDate: e.target.value })} />
        </Field>
        <Field label="End date" required error={errors.endDate}>
          <input className={inputClass} type="date" value={form.endDate} onChange={(e) => setForm({ ...form, endDate: e.target.value })} />
        </Field>
      </div>
      <Field label="Assessment instructions" error={errors.instructions}>
        <textarea className={`${inputClass} min-h-24 py-3`} value={form.instructions} onChange={(e) => setForm({ ...form, instructions: e.target.value })} />
      </Field>
      <section className="rounded-xl border border-slate-200">
        <header className="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-4 py-3">
          <div>
            <h3 className="font-bold text-slate-800">Frozen criterion weights</h3>
            <p className="text-xs text-slate-500">These weights are copied into the period and cannot change after it opens.</p>
          </div>
          <span className={`rounded-full px-3 py-1 text-sm font-bold ${total === 100 ? "bg-emerald-100 text-emerald-800" : "bg-red-100 text-red-800"}`}>{total}%</span>
        </header>
        <div className="divide-y divide-slate-100">
          {form.criteria.map((criterion, index) => (
            <label className="grid items-center gap-3 px-4 py-3 sm:grid-cols-[1fr_7rem]" key={criterion.criterionId}>
              <span>
                <strong className="block text-sm text-slate-800">{criterion.label ?? criterion.code}</strong>
                <small className="text-slate-500">{criterion.code}</small>
              </span>
              <span className="relative">
                <input className={`${inputClass} pr-8 text-right`} type="number" min="0.01" max="100" step="0.01" value={criterion.weight} onChange={(e) => setForm((current) => ({ ...current, criteria: current.criteria.map((item, itemIndex) => itemIndex === index ? { ...item, weight: e.target.value } : item) }))} />
                <span className="absolute right-3 top-3 text-sm text-slate-400">%</span>
              </span>
            </label>
          ))}
        </div>
      </section>
      {errors.criteria && <p className="text-sm font-semibold text-red-600">{errors.criteria[0] ?? errors.criteria}</p>}
      <div className="flex justify-end gap-2">
        <button className={buttonSecondary} onClick={onCancel} type="button">Cancel</button>
        <button className={buttonPrimary} disabled={saving || total !== 100} type="submit">{saving ? "Saving..." : initial ? "Update period" : "Create period"}</button>
      </div>
    </form>
  );
}

function AssessmentForm({ period, universe, initial, errors, saving, onCancel, onSubmit }) {
  const [files, setFiles] = useState([]);
  const [form, setForm] = useState(() => ({
    auditUniverseItemId: initial?.auditUniverseItemId ?? "",
    assessmentDate: initial?.assessmentDate ?? new Date().toISOString().slice(0, 10),
    controlEffectivenessPercent: initial?.controlEffectivenessPercent ?? 0,
    controlEffectivenessNotes: initial?.controlEffectivenessNotes ?? "",
    justification: initial?.justification ?? "",
    evidenceSummary: initial?.evidenceSummary ?? "",
    lockVersion: initial?.lockVersion,
    scores: period.criteria.map((criterion) => {
      const saved = initial?.scores?.find((score) => score.criterionId === criterion.criterionId);
      return { criterionId: criterion.criterionId, rating: saved?.rating ?? 3, comment: saved?.comment ?? "" };
    }),
  }));
  const inherent = period.criteria.reduce((sum, criterion) => {
    const score = form.scores.find((item) => item.criterionId === criterion.criterionId);
    return sum + Number(score?.rating ?? 0) * Number(criterion.weight) / 100;
  }, 0);
  const residual = inherent * (1 - Number(form.controlEffectivenessPercent || 0) / 100);

  return (
    <form className="space-y-5" onSubmit={(event) => { event.preventDefault(); onSubmit({ ...form, auditUniverseItemId: Number(form.auditUniverseItemId), controlEffectivenessPercent: Number(form.controlEffectivenessPercent), scores: form.scores.map((score) => ({ ...score, rating: Number(score.rating) })) }, files); }}>
      <Field label="Audit Universe subject" required error={errors.auditUniverseItemId}>
        <SearchableSelect
          disabled={Boolean(initial)}
          options={universe.filter((item) => item.id === initial?.auditUniverseItemId || !period.assessments.some((assessment) => !assessment.isArchived && assessment.auditUniverseItemId === item.id)).map((item) => ({
            value: item.id,
            label: `${item.subjectCode} — ${item.name}`,
            description: `${item.responsibleOffice?.code ?? ""} · ${item.primaryAuditArea?.name ?? ""}`,
          }))}
          value={form.auditUniverseItemId}
          onChange={(auditUniverseItemId) => setForm({ ...form, auditUniverseItemId })}
          placeholder="Search Audit Universe..."
        />
      </Field>
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="Assessment date" required error={errors.assessmentDate}>
          <input className={inputClass} type="date" value={form.assessmentDate} onChange={(e) => setForm({ ...form, assessmentDate: e.target.value })} />
        </Field>
        <Field label="Control effectiveness" required error={errors.controlEffectivenessPercent}>
          <div className="flex items-center gap-3">
            <input className="min-w-0 flex-1 accent-sky-700" type="range" min="0" max="100" value={form.controlEffectivenessPercent} onChange={(e) => setForm({ ...form, controlEffectivenessPercent: e.target.value })} />
            <span className="w-14 rounded-lg bg-slate-100 px-2 py-2 text-center text-sm font-bold">{form.controlEffectivenessPercent}%</span>
          </div>
        </Field>
      </div>
      <div className="grid gap-3 sm:grid-cols-2">
        <ScoreCard label="Inherent risk" score={inherent} />
        <ScoreCard label="Residual risk" score={residual} />
      </div>
      <section className="overflow-hidden rounded-xl border border-slate-200">
        <header className="border-b border-slate-200 bg-slate-50 px-4 py-3">
          <h3 className="font-bold text-slate-800">Risk criteria</h3>
          <p className="text-xs text-slate-500">Rate each criterion from 1 (Low) to 5 (Critical).</p>
        </header>
        <div className="divide-y divide-slate-100">
          {period.criteria.map((criterion, index) => {
            const score = form.scores[index];
            return (
              <div className="grid gap-3 p-4 lg:grid-cols-[1fr_9rem_1.2fr]" key={criterion.criterionId}>
                <span>
                  <strong className="block text-sm text-slate-800">{criterion.label}</strong>
                  <small className="text-slate-500">{criterion.weight}% weight</small>
                </span>
                <select className={inputClass} value={score.rating} onChange={(e) => setForm((current) => ({ ...current, scores: current.scores.map((item, itemIndex) => itemIndex === index ? { ...item, rating: e.target.value } : item) }))}>
                  <option value="1">1 — Low</option><option value="2">2 — Moderate</option><option value="3">3 — Significant</option><option value="4">4 — High</option><option value="5">5 — Critical</option>
                </select>
                <input className={inputClass} placeholder="Scoring basis or evidence..." value={score.comment} onChange={(e) => setForm((current) => ({ ...current, scores: current.scores.map((item, itemIndex) => itemIndex === index ? { ...item, comment: e.target.value } : item) }))} />
              </div>
            );
          })}
        </div>
      </section>
      <Field label="Control-effectiveness notes" required error={errors.controlEffectivenessNotes}><textarea className={`${inputClass} min-h-20 py-3`} value={form.controlEffectivenessNotes} onChange={(e) => setForm({ ...form, controlEffectivenessNotes: e.target.value })} /></Field>
      <Field label="Risk justification" required error={errors.justification}><textarea className={`${inputClass} min-h-24 py-3`} value={form.justification} onChange={(e) => setForm({ ...form, justification: e.target.value })} /></Field>
      <Field label="Evidence summary" error={errors.evidenceSummary}><textarea className={`${inputClass} min-h-20 py-3`} value={form.evidenceSummary} onChange={(e) => setForm({ ...form, evidenceSummary: e.target.value })} /></Field>
      <Field label="Supporting evidence">
        <label className="flex cursor-pointer items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-300 p-5 text-sm font-semibold text-slate-600 transition hover:border-sky-400 hover:bg-sky-50">
          <Upload size={18} /> {files.length ? `${files.length} file(s) selected` : "Choose PDF, Office, image, CSV, or text files"}
          <input className="sr-only" multiple type="file" onChange={(e) => setFiles([...e.target.files])} />
        </label>
      </Field>
      <div className="flex justify-end gap-2">
        <button className={buttonSecondary} type="button" onClick={onCancel}>Cancel</button>
        <button className={buttonPrimary} disabled={saving} type="submit">{saving ? "Saving..." : initial ? "Update assessment" : "Save assessment"}</button>
      </div>
    </form>
  );
}

function Field({ label, required, error, children }) {
  return <label className="block text-sm font-semibold text-slate-700">{label}{required && <span className="text-red-500"> *</span>}<span className="mt-1.5 block">{children}</span>{error && <small className="mt-1 block text-red-600">{Array.isArray(error) ? error[0] : error}</small>}</label>;
}

function ScoreCard({ label, score, level, suffix = "" }) {
  const displayLevel = level === null ? null : level ?? scoreLevel(score);
  return <div className="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 p-4"><span className="text-sm font-bold text-slate-700">{label}</span><span className="text-right"><strong className="block text-2xl text-slate-900">{score.toFixed(2)}{suffix}</strong>{displayLevel && <small className={`rounded-full px-2 py-0.5 font-bold ${riskTone(displayLevel)}`}>{displayLevel}</small>}</span></div>;
}

function MetricCard({ label, value }) {
  return <div className="rounded-xl border border-slate-200 bg-slate-50 p-4"><strong className="block text-2xl text-slate-900">{value}</strong><span className="mt-1 block text-xs font-bold uppercase tracking-wide text-slate-500">{label}</span></div>;
}

/**
 * Manages controlled risk-assessment cycles, scoring criteria, subject
 * assessments, evidence, validation, and baseline locking.
 */
export default function IapRiskAssessmentPeriodsPage() {
  const { user } = useAuth();
  const toast = useToast();
  const [periods, setPeriods] = useState([]);
  const [criteria, setCriteria] = useState([]);
  const [universe, setUniverse] = useState([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("");
  const [selected, setSelected] = useState(null);
  useRecordView(selected, {
    module: "IAP",
    recordType: "RISK_PERIOD",
    code: (record) => record.periodCode,
    label: (record) => record.name,
  });
  const [assessmentViewer, setAssessmentViewer] = useState(null);
  useRecordView(assessmentViewer, {
    module: "IAP",
    recordType: "IAP_RISK",
    code: (record) => record.auditUniverseItem?.subjectCode,
    label: (record) =>
      record.auditUniverseItem?.name ?? "Audit universe risk assessment",
  });
  const [comparisonId, setComparisonId] = useState("");
  const [editor, setEditor] = useState(null);
  const [assessmentEditor, setAssessmentEditor] = useState(null);
  const [errors, setErrors] = useState({});
  const [workflow, setWorkflow] = useState("");
  const [workflowComment, setWorkflowComment] = useState("");
  const [archiveTarget, setArchiveTarget] = useState(null);
  const [restoreTarget, setRestoreTarget] = useState(null);
  const canCreate = hasPermission(user, "iap.create");
  const canUpdate = hasPermission(user, "iap.update");
  const canAssess = hasPermission(user, "iap.assess_risk");
  const canArchive = hasPermission(user, "iap.archive");
  const canRestore = hasPermission(user, "iap.restore");

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const [periodResult, universeResult] = await Promise.all([
        riskPeriodApi.list({ includeArchived: canRestore ? 1 : 0, perPage: 100 }),
        auditUniverseApi.list({ perPage: 100 }),
      ]);
      setPeriods(periodResult.riskPeriods);
      setUniverse(universeResult.items);
      if (canCreate || canUpdate) {
        const lists = await masterListApi.list();
        setCriteria(lists.find((list) => list.code === "IAP_RISK_CRITERION")?.items?.filter((item) => item.isActive && !item.isArchived) ?? []);
      }
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "Unable to load risk assessment periods.");
    } finally {
      setLoading(false);
    }
  }, [canCreate, canRestore, canUpdate, toast]);

  useEffect(() => {
    const timer = window.setTimeout(load, 0);
    return () => window.clearTimeout(timer);
  }, [load]);

  const filtered = useMemo(() => periods.filter((period) => {
    const needle = search.trim().toLowerCase();
    const matchesSearch = !needle || [period.periodCode, period.name, period.assessmentYear].some((value) => String(value ?? "").toLowerCase().includes(needle));
    const displayStatus = period.isArchived ? "ARCHIVED" : period.status;
    return matchesSearch && (!status || displayStatus === status);
  }), [periods, search, status]);

  const selectedComparison = periods.find((period) => String(period.id) === String(comparisonId));
  const comparisons = useMemo(() => {
    if (!selected || !selectedComparison?.assessments) return [];
    return selected.assessments.filter((item) => !item.isArchived).map((current) => {
      const previous = selectedComparison.assessments.find((item) => !item.isArchived && item.auditUniverseItemId === current.auditUniverseItemId);
      return { current, previous, change: previous ? current.residualRiskScore - previous.residualRiskScore : null };
    });
  }, [selected, selectedComparison]);

  async function openPeriod(period) {
    try {
      setSelected(await riskPeriodApi.show(period.id));
      setComparisonId("");
    } catch (error) { toast.error(error.message); }
  }

  async function savePeriod(payload) {
    setSaving(true); setErrors({});
    try {
      const result = editor?.id ? await riskPeriodApi.update(editor.id, payload) : await riskPeriodApi.create(payload);
      toast.success(editor?.id ? "Risk assessment period updated." : "Risk assessment period created.");
      setEditor(null); setSelected(result); await load();
    } catch (error) {
      setErrors(error instanceof ApiError ? error.errors : {});
      toast.error(error.message ?? "Unable to save period.");
    } finally { setSaving(false); }
  }

  async function saveAssessment(payload, files) {
    setSaving(true); setErrors({});
    try {
      const current = assessmentEditor?.id
        ? await riskPeriodApi.updateAssessment(selected.id, assessmentEditor.id, payload)
        : await riskPeriodApi.createAssessment(selected.id, payload);
      const saved = assessmentEditor?.id
        ? current.assessments.find((item) => item.id === assessmentEditor.id)
        : current.assessments.find((item) => item.auditUniverseItemId === payload.auditUniverseItemId);
      for (const file of files) await riskPeriodApi.uploadEvidence(selected.id, saved.id, file);
      setSelected(await riskPeriodApi.show(selected.id));
      setAssessmentEditor(null);
      toast.success("Risk assessment and supporting evidence saved.");
      await load();
    } catch (error) {
      setErrors(error instanceof ApiError ? error.errors : {});
      toast.error(error.message ?? "Unable to save assessment.");
    } finally { setSaving(false); }
  }

  async function doWorkflow() {
    setSaving(true);
    try {
      const updated = await riskPeriodApi.transition(selected.id, workflow, { lockVersion: selected.lockVersion, comment: workflowComment });
      setSelected(updated); setWorkflow(""); setWorkflowComment(""); toast.success("Risk assessment workflow updated."); await load();
    } catch (error) { toast.error(error.message); } finally { setSaving(false); }
  }

  const transitionActions = selected ? [
    selected.status === "DRAFT" && hasPermission(user, "iap.update") && ["open", "Open assessment period"],
    selected.status === "OPEN" && hasPermission(user, "iap.submit") && ["submit", "Submit for validation"],
    selected.status === "RETURNED_FOR_REVISION" && hasPermission(user, "iap.submit") && ["resubmit", "Resubmit"],
    ["PENDING_VALIDATION", "RESUBMITTED"].includes(selected.status) && hasPermission(user, "iap.review") && ["return", "Return for revision"],
    ["PENDING_VALIDATION", "RESUBMITTED"].includes(selected.status) && hasPermission(user, "iap.approve") && ["validate", "Validate assessment"],
    selected.status === "VALIDATED" && hasPermission(user, "iap.activate") && ["lock", "Lock assessment period"],
  ].filter(Boolean) : [];

  const columns = [
    { key: "periodCode", label: "Period", render: (period) => <span><strong className="block text-slate-900">{period.periodCode}</strong><small className="text-slate-500">{period.assessmentYear}</small></span> },
    { key: "name", label: "Assessment period", render: (period) => <span><strong className="block text-slate-800">{period.name}</strong><small className="text-slate-500">{period.startDate} — {period.endDate}</small></span> },
    { key: "assessmentCount", label: "Assessed subjects", render: (period) => <strong>{period.assessmentCount ?? 0}</strong> },
    { key: "status", label: "Status", render: (period) => <StatusBadge tone={tones[period.isArchived ? "ARCHIVED" : period.status]}>{labels[period.isArchived ? "ARCHIVED" : period.status]}</StatusBadge> },
    { key: "actions", label: "Actions", sortable: false, render: (period) => <div className="flex gap-1" onClick={(e) => e.stopPropagation()}>
      {canUpdate && period.status === "DRAFT" && !period.isArchived && <button className="rounded-lg p-2 text-sky-700 hover:bg-sky-50" title="Edit" onClick={() => setEditor(period)}><Pencil size={17} /></button>}
      {canArchive && ["DRAFT", "LOCKED"].includes(period.status) && !period.isArchived && <button className="rounded-lg p-2 text-red-600 hover:bg-red-50" title="Archive" onClick={() => setArchiveTarget(period)}><Archive size={17} /></button>}
      {canRestore && period.isArchived && <button className="rounded-lg p-2 text-emerald-700 hover:bg-emerald-50" title="Restore" onClick={() => setRestoreTarget(period)}><RotateCcw size={17} /></button>}
    </div> },
  ];

  return (
    <main className="min-w-0 p-4 sm:p-5">
      <RegistryHeader icon={SlidersHorizontal} title="Risk Assessment" description="Open a controlled assessment cycle, score Audit Universe subjects, calculate residual risk, validate the results, and lock the approved risk baseline." readOnly={!canAssess && !canCreate} actions={canCreate && <button className={buttonPrimary} onClick={() => { setErrors({}); setEditor({}); }}><Plus size={18} /> Open new period</button>} />
      <section className="mb-5 grid grid-cols-2 gap-3 xl:grid-cols-4">
        <SummaryCard icon={ClipboardCheck} label="Assessment periods" value={periods.filter((p) => !p.isArchived).length} tone="sky" />
        <SummaryCard icon={FileCheck2} label="Open cycles" value={periods.filter((p) => ["OPEN", "RETURNED_FOR_REVISION"].includes(p.status) && !p.isArchived).length} tone="amber" />
        <SummaryCard icon={ShieldCheck} label="Validated" value={periods.filter((p) => p.status === "VALIDATED" && !p.isArchived).length} tone="emerald" />
        <SummaryCard icon={LockKeyhole} label="Locked baselines" value={periods.filter((p) => p.status === "LOCKED" && !p.isArchived).length} tone="slate" />
      </section>
      <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div className="grid gap-3 border-b border-slate-200 p-3 sm:p-4 lg:grid-cols-[minmax(16rem,1fr)_15rem_auto]">
          <label className="flex min-h-11 items-center gap-2 rounded-lg border border-slate-300 px-3"><Search size={17} className="text-slate-400" /><input className="min-w-0 flex-1 text-sm outline-none" placeholder="Search period, year, or title..." value={search} onChange={(e) => setSearch(e.target.value)} /></label>
          <select className={inputClass} value={status} onChange={(e) => setStatus(e.target.value)}><option value="">All statuses</option>{Object.entries(labels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select>
          <button className={buttonSecondary} onClick={() => { setSearch(""); setStatus(""); }}><FilterX size={17} /> Clear filters</button>
        </div>
        <DataTable columns={columns} rows={filtered} loading={loading} onRowClick={openPeriod} emptyMessage="No risk assessment periods match the filters." />
      </section>

      <Modal open={Boolean(editor)} title={editor?.id ? "Edit risk assessment period" : "Open risk assessment period"} description="Configure the assessment window and freeze criterion weights before opening the cycle." size="xl" onClose={() => setEditor(null)}>
        {editor && <PeriodForm criteria={criteria} initial={editor.id ? editor : null} errors={errors} saving={saving} onCancel={() => setEditor(null)} onSubmit={savePeriod} />}
      </Modal>

      <Modal open={Boolean(selected)} title={selected?.name ?? "Risk assessment period"} description={selected ? `${selected.periodCode} · ${selected.assessmentYear}` : ""} size="xl" onClose={() => setSelected(null)}>
        {selected && <div className="space-y-5">
          <div className="grid gap-3 md:grid-cols-[1fr_auto]">
            <div className="rounded-xl bg-slate-50 p-4"><div className="flex flex-wrap items-center gap-2"><StatusBadge tone={tones[selected.status]}>{labels[selected.status]}</StatusBadge><span className="text-sm text-slate-500">{selected.startDate} — {selected.endDate}</span></div><p className="mt-3 text-sm leading-6 text-slate-600">{selected.instructions || "No special instructions recorded."}</p></div>
            <div className="flex flex-wrap content-start justify-end gap-2">
              {transitionActions.map(([action, label]) => <button className={action === "return" ? buttonSecondary : buttonPrimary} key={action} onClick={() => setWorkflow(action)}>{label}<ArrowRight size={16} /></button>)}
              {canAssess && ["OPEN", "RETURNED_FOR_REVISION"].includes(selected.status) && <button className={buttonPrimary} onClick={() => { setErrors({}); setAssessmentEditor({}); }}><Plus size={17} /> Assess subject</button>}
            </div>
          </div>
          <div className="grid gap-3 md:grid-cols-4">
            <MetricCard label="Assessed subjects" value={selected.assessments.filter((a) => !a.isArchived).length} />
            <MetricCard label="Critical residual" value={selected.assessments.filter((a) => !a.isArchived && a.residualRiskLevel?.code === "CRITICAL").length} />
            <MetricCard label="High residual" value={selected.assessments.filter((a) => !a.isArchived && a.residualRiskLevel?.code === "HIGH").length} />
            <MetricCard label="Criterion weight" value={`${selected.criteria.reduce((sum, item) => sum + item.weight, 0)}%`} />
          </div>
          <section className="overflow-hidden rounded-xl border border-slate-200">
            <header className="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-4 py-3"><div><h3 className="font-bold text-slate-800">Subject assessments</h3><p className="text-xs text-slate-500">Click a row to edit it while the period remains open.</p></div></header>
            <DataTable
              rows={selected.assessments}
              onRowClick={setAssessmentViewer}
              columns={[
                { key: "subject", label: "Audit Universe subject", sortValue: (a) => a.auditUniverseItem?.name, render: (a) => <span><strong className="block text-slate-800">{a.auditUniverseItem?.name}</strong><small className="text-slate-500">{a.auditUniverseItem?.subjectCode} · {a.auditUniverseItem?.responsibleOffice?.code}</small></span> },
                { key: "inherentRiskScore", label: "Inherent", render: (a) => <span><strong>{a.inherentRiskScore.toFixed(2)}</strong><small className={`ml-2 rounded-full px-2 py-0.5 font-bold ${riskTone(a.inherentRiskLevel?.code)}`}>{a.inherentRiskLevel?.code}</small></span> },
                { key: "controlEffectivenessPercent", label: "Control effectiveness", render: (a) => `${a.controlEffectivenessPercent}%` },
                { key: "residualRiskScore", label: "Residual", render: (a) => <span><strong>{a.residualRiskScore.toFixed(2)}</strong><small className={`ml-2 rounded-full px-2 py-0.5 font-bold ${riskTone(a.residualRiskLevel?.code)}`}>{a.residualRiskLevel?.code}</small></span> },
                { key: "status", label: "Status", render: (a) => <StatusBadge tone={a.isArchived ? "danger" : tones[a.status] ?? "inactive"}>{a.isArchived ? "Archived" : labels[a.status] ?? a.status}</StatusBadge> },
                { key: "actions", label: "Actions", sortable: false, render: (a) => <div onClick={(e) => e.stopPropagation()}>
                  {canAssess && ["OPEN", "RETURNED_FOR_REVISION"].includes(selected.status) && !a.isArchived && <button className="rounded-lg p-2 text-sky-700 hover:bg-sky-50" title="Edit assessment" onClick={() => { setErrors({}); setAssessmentEditor(a); }}><Pencil size={17} /></button>}
                </div> },
              ]}
              emptyMessage="No Audit Universe subjects have been assessed."
            />
          </section>
          <section className="rounded-xl border border-slate-200 p-4">
            <div className="flex flex-wrap items-end justify-between gap-3">
              <div><h3 className="font-bold text-slate-800">Period comparison</h3><p className="text-xs text-slate-500">Compare matched Audit Universe subjects with a validated or locked cycle.</p></div>
              <div className="w-full sm:w-80"><SearchableSelect options={periods.filter((p) => p.id !== selected.id && ["VALIDATED", "LOCKED"].includes(p.status) && !p.isArchived).map((p) => ({ value: p.id, label: `${p.periodCode} — ${p.name}` }))} value={comparisonId} onChange={setComparisonId} placeholder="Choose comparison period..." /></div>
            </div>
            {comparisonId && <div className="mt-4"><DataTable rows={comparisons} columns={[
              { key: "subject", label: "Subject", render: (row) => row.current.auditUniverseItem?.name },
              { key: "previous", label: "Previous residual", render: (row) => row.previous?.residualRiskScore?.toFixed(2) ?? "Not assessed" },
              { key: "current", label: "Current residual", render: (row) => row.current.residualRiskScore.toFixed(2) },
              { key: "change", label: "Change", render: (row) => row.change === null ? "—" : <span className={row.change > 0 ? "font-bold text-red-600" : row.change < 0 ? "font-bold text-emerald-700" : ""}>{row.change > 0 ? "+" : ""}{row.change.toFixed(2)}</span> },
            ]} /></div>}
          </section>
          <section className="rounded-xl border border-slate-200 p-4"><h3 className="mb-3 flex items-center gap-2 font-bold text-slate-800"><History size={18} /> Workflow history</h3><div className="space-y-3">{selected.events.map((event) => <div className="flex gap-3 border-l-2 border-sky-200 pl-3" key={event.id}><CheckCircle2 className="mt-0.5 shrink-0 text-sky-600" size={17} /><span><strong className="text-sm text-slate-800">{event.action.replaceAll("_", " ")}</strong><small className="ml-2 text-slate-500">{event.actor?.name} · {dateTime(event.createdAt)}</small>{event.comment && <p className="mt-1 text-sm text-slate-600">{event.comment}</p>}</span></div>)}</div></section>
        </div>}
      </Modal>

      <Modal open={Boolean(assessmentEditor)} title={assessmentEditor?.id ? "Edit subject risk assessment" : "Assess Audit Universe subject"} description="The server calculates inherent and residual risk using the period’s frozen criterion weights." size="xl" onClose={() => setAssessmentEditor(null)}>
        {assessmentEditor && selected && <AssessmentForm period={selected} universe={universe} initial={assessmentEditor.id ? assessmentEditor : null} errors={errors} saving={saving} onCancel={() => setAssessmentEditor(null)} onSubmit={saveAssessment} />}
      </Modal>

      <Modal open={Boolean(assessmentViewer)} title={assessmentViewer?.auditUniverseItem?.name ?? "Risk assessment details"} description={assessmentViewer ? `${assessmentViewer.auditUniverseItem?.subjectCode} · Assessed ${assessmentViewer.assessmentDate}` : ""} size="xl" onClose={() => setAssessmentViewer(null)}>
        {assessmentViewer && selected && <div className="space-y-5">
          <div className="grid gap-3 sm:grid-cols-3">
            <ScoreCard label="Inherent risk" score={assessmentViewer.inherentRiskScore} level={assessmentViewer.inherentRiskLevel?.code} />
            <ScoreCard label="Control effectiveness" score={assessmentViewer.controlEffectivenessPercent} level={null} suffix="%" />
            <ScoreCard label="Residual risk" score={assessmentViewer.residualRiskScore} level={assessmentViewer.residualRiskLevel?.code} />
          </div>
          <div className="grid gap-4 md:grid-cols-2">
            <section className="rounded-xl border border-slate-200 p-4"><h3 className="font-bold text-slate-800">Risk justification</h3><p className="mt-2 whitespace-pre-wrap text-sm leading-6 text-slate-600">{assessmentViewer.justification}</p></section>
            <section className="rounded-xl border border-slate-200 p-4"><h3 className="font-bold text-slate-800">Control effectiveness</h3><p className="mt-2 whitespace-pre-wrap text-sm leading-6 text-slate-600">{assessmentViewer.controlEffectivenessNotes}</p></section>
          </div>
          <section className="overflow-hidden rounded-xl border border-slate-200">
            <header className="border-b border-slate-200 bg-slate-50 px-4 py-3"><h3 className="font-bold text-slate-800">Criterion scoring</h3></header>
            <div className="divide-y divide-slate-100">{assessmentViewer.scores.map((score) => <div className="grid gap-2 px-4 py-3 sm:grid-cols-[1fr_auto_auto]" key={score.id}><span><strong className="block text-sm text-slate-800">{score.criterion?.label}</strong><small className="text-slate-500">{score.comment || "No scoring comment."}</small></span><span className="text-sm font-semibold text-slate-600">{score.weight}% × {score.rating}</span><strong className="text-sky-700">{score.weightedScore.toFixed(2)}</strong></div>)}</div>
          </section>
          <section className="rounded-xl border border-slate-200 p-4">
            <h3 className="flex items-center gap-2 font-bold text-slate-800"><FileText size={18} /> Supporting evidence</h3>
            {assessmentViewer.evidence.length === 0 ? <p className="mt-3 text-sm text-slate-500">No supporting files uploaded.</p> : <div className="mt-3 grid gap-2">{assessmentViewer.evidence.map((evidence) => <div className="flex items-center gap-3 rounded-lg bg-slate-50 px-3 py-2" key={evidence.id}><FileText className="text-sky-700" size={18} /><span className="min-w-0 flex-1"><strong className="block truncate text-sm text-slate-800">{evidence.fileName}</strong><small className="text-slate-500">{Math.max(1, Math.round(evidence.fileSize / 1024))} KB · {evidence.uploadedBy?.name}</small></span><button className={buttonSecondary} onClick={() => riskPeriodApi.downloadEvidence(selected.id, assessmentViewer.id, evidence)}>Download</button>{canAssess && ["OPEN", "RETURNED_FOR_REVISION"].includes(selected.status) && <button className="rounded-lg p-2 text-red-600 hover:bg-red-50" title="Archive evidence" onClick={async () => { try { await riskPeriodApi.removeEvidence(selected.id, assessmentViewer.id, evidence.id); const refreshed = await riskPeriodApi.show(selected.id); setSelected(refreshed); setAssessmentViewer(refreshed.assessments.find((item) => item.id === assessmentViewer.id)); toast.success("Supporting evidence archived."); } catch (error) { toast.error(error.message); } }}><Archive size={17} /></button>}</div>)}</div>}
          </section>
        </div>}
      </Modal>

      <Modal open={Boolean(workflow)} title={transitionActions.find(([action]) => action === workflow)?.[1] ?? "Update workflow"} description="This controlled action will be recorded in the workflow history and audit trail." onClose={() => setWorkflow("")} footer={<><button className={buttonSecondary} onClick={() => setWorkflow("")}>Cancel</button><button className={buttonPrimary} disabled={saving || (workflow === "return" && !workflowComment.trim())} onClick={doWorkflow}>{saving ? "Please wait..." : "Confirm action"}</button></>}>
        <Field label={workflow === "return" ? "Return reason" : "Comment"} required={workflow === "return"}><textarea className={`${inputClass} min-h-28 py-3`} value={workflowComment} onChange={(e) => setWorkflowComment(e.target.value)} placeholder="Record the basis for this workflow decision..." /></Field>
      </Modal>

      <ConfirmDialog open={Boolean(archiveTarget)} title="Archive risk assessment period?" description="The period remains recoverable and can be restored later." confirmLabel="Archive period" tone="danger" busy={saving} onCancel={() => setArchiveTarget(null)} onConfirm={async () => { setSaving(true); try { await riskPeriodApi.archive(archiveTarget.id); toast.success("Risk assessment period archived."); setArchiveTarget(null); await load(); } catch (e) { toast.error(e.message); } finally { setSaving(false); } }} />
      <ConfirmDialog open={Boolean(restoreTarget)} title="Restore risk assessment period?" description="The archived period will become available again." confirmLabel="Restore period" busy={saving} onCancel={() => setRestoreTarget(null)} onConfirm={async () => { setSaving(true); try { await riskPeriodApi.restore(restoreTarget.id); toast.success("Risk assessment period restored."); setRestoreTarget(null); await load(); } catch (e) { toast.error(e.message); } finally { setSaving(false); } }} />
    </main>
  );
}
