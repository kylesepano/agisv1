import {
  AlertTriangle,
  CalendarDays,
  CheckCircle2,
  ClipboardCheck,
  FilePenLine,
  Gauge,
  History,
  LockKeyhole,
  Plus,
  RefreshCw,
  RotateCcw,
  Search,
  Send,
  ShieldCheck,
  UsersRound,
  XCircle,
} from "lucide-react";
import { useCallback, useEffect, useMemo, useState } from "react";
import { useAuth } from "../../auth/auth-context";
import DataTable from "../../components/ui/DataTable";
import Modal from "../../components/ui/Modal";
import RegistryHeader from "../../components/ui/RegistryHeader";
import StatusBadge from "../../components/ui/StatusBadge";
import { hasPermission } from "../../config/navigation";
import { ApiError, aemsEngagementApi, armisApi } from "../../services/api";
import { useToast } from "../../ui/toast-context";

const emptyAssignment = {
  auditEngagementId: "",
  resourceProfileId: "",
  requirementId: "",
  assignmentRoleCode: "AUDITOR",
  assignedFrom: "",
  assignedUntil: "",
  plannedPersonDays: "",
  requiredCompetencies: [],
  notes: "",
};

const emptyActual = {
  assignmentId: "",
  periodStart: "",
  periodEnd: "",
  actualPersonDays: "",
  varianceReason: "",
  notes: "",
};

const statusTone = {
  DRAFT: "info",
  SUBMITTED: "warning",
  RETURNED: "warning",
  APPROVED: "success",
  LOCKED: "inactive",
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

function numberLabel(value) {
  if (value === null || value === undefined || value === "") return "—";
  return new Intl.NumberFormat("en-PH", { maximumFractionDigits: 2 }).format(
    Number(value),
  );
}

function inputClass(extra = "") {
  return `mt-1 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100 disabled:bg-slate-100 ${extra}`;
}

function ErrorState({ message, onRetry }) {
  return (
    <section className="rounded-2xl border border-red-200 bg-red-50 px-6 py-12 text-center">
      <XCircle className="mx-auto text-red-600" size={34} />
      <h2 className="mt-3 font-bold text-red-900">
        ARMIS assignment workspace unavailable
      </h2>
      <p className="mx-auto mt-2 max-w-xl text-sm text-red-700">{message}</p>
      <button
        className="mt-5 inline-flex h-10 items-center gap-2 rounded-lg bg-red-700 px-4 text-sm font-bold text-white hover:bg-red-800"
        onClick={onRetry}
        type="button"
      >
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
    red: "border-red-200 bg-red-50 text-red-800",
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

function AssignmentForm({ form, setForm, engagements, resources, requirements, metadata, competencyOptions, errors = {}, revisionMode = false }) {
  const set = (name, value) => setForm((current) => ({ ...current, [name]: value }));
  const selectedRequirement = requirements.find((item) => String(item.id) === String(form.requirementId));
  const engagementOptions = engagements.filter((item) => !item.isArchived && !["CANCELLED", "CLOSED"].includes(item.status));
  const eligibleRequirements = requirements.filter((item) => String(item.sourceModule || "").toUpperCase() === "AEMS" && String(item.sourceId) === String(form.auditEngagementId));

  function updateCompetency(index, key, value) {
    set("requiredCompetencies", (form.requiredCompetencies || []).map((item, itemIndex) => itemIndex === index ? { ...item, [key]: value } : item));
  }

  function addCompetency() {
    set("requiredCompetencies", [...(form.requiredCompetencies || []), { competencyId: "", minimumProficiency: "INTERMEDIATE", notes: "" }]);
  }

  function removeCompetency(index) {
    set("requiredCompetencies", (form.requiredCompetencies || []).filter((_, itemIndex) => itemIndex !== index));
  }

  return (
    <div className="grid gap-4 sm:grid-cols-2">
      <Field error={errors.auditEngagementId} htmlFor="armis-assignment-engagement" label="AEMS engagement" required>
        {engagementOptions.length ? (
          <select className={inputClass()} disabled={revisionMode} id="armis-assignment-engagement" onChange={(event) => set("auditEngagementId", event.target.value)} value={form.auditEngagementId}>
            <option value="">Select an authorized engagement</option>
            {engagementOptions.map((item) => <option key={item.id} value={item.id}>{item.engagementCode} — {item.title}</option>)}
          </select>
        ) : (
          <input className={inputClass()} disabled={revisionMode} id="armis-assignment-engagement" inputMode="numeric" onChange={(event) => set("auditEngagementId", event.target.value)} placeholder="Enter authorized engagement ID" value={form.auditEngagementId} />
        )}
        <span className="mt-1 block text-xs leading-5 text-slate-500">ARMIS links to the existing AEMS engagement; it does not replace AEMS team records.</span>
      </Field>
      <Field error={errors.resourceProfileId} htmlFor="armis-assignment-resource" label="Resource profile" required>
        <select className={inputClass()} disabled={revisionMode} id="armis-assignment-resource" onChange={(event) => set("resourceProfileId", event.target.value)} value={form.resourceProfileId}>
          <option value="">Select an active resource</option>
          {resources.filter((item) => item.status === "ACTIVE").map((item) => <option key={item.id} value={item.id}>{item.resourceCode} — {item.user?.name || "Core user unavailable"}</option>)}
        </select>
      </Field>
      <Field error={errors.assignmentRoleCode} htmlFor="armis-assignment-role" label="Assignment role" required>
        <select className={inputClass()} id="armis-assignment-role" onChange={(event) => set("assignmentRoleCode", event.target.value)} value={form.assignmentRoleCode}>
          {(metadata.assignmentRoles || []).map((item) => <option key={item.code} value={item.code}>{item.label}</option>)}
        </select>
      </Field>
      <Field error={errors.requirementId} htmlFor="armis-assignment-requirement" label="Resource requirement">
        <select className={inputClass()} id="armis-assignment-requirement" onChange={(event) => set("requirementId", event.target.value)} value={form.requirementId}>
          <option value="">No normalized requirement</option>
          {eligibleRequirements.map((item) => <option key={item.id} value={item.id}>{item.title} · {item.requiredPersonDays} days</option>)}
        </select>
        {selectedRequirement && <span className="mt-1 block text-xs text-slate-500">{selectedRequirement.competencies?.length || 0} competency requirement(s) available from the linked snapshot.</span>}
      </Field>
      <Field error={errors.assignedFrom} htmlFor="armis-assignment-start" label="Assigned from" required>
        <input className={inputClass()} id="armis-assignment-start" onChange={(event) => set("assignedFrom", event.target.value)} type="date" value={form.assignedFrom} />
      </Field>
      <Field error={errors.assignedUntil} htmlFor="armis-assignment-end" label="Assigned until" required>
        <input className={inputClass()} id="armis-assignment-end" min={form.assignedFrom || undefined} onChange={(event) => set("assignedUntil", event.target.value)} type="date" value={form.assignedUntil} />
      </Field>
      <Field error={errors.plannedPersonDays} htmlFor="armis-assignment-days" label="Planned person-days" required>
        <input className={inputClass()} id="armis-assignment-days" min="0.01" onChange={(event) => set("plannedPersonDays", event.target.value)} step="0.25" type="number" value={form.plannedPersonDays} />
      </Field>
      <div className="sm:col-span-2 rounded-xl border border-slate-200 bg-slate-50 p-4">
        <div className="flex flex-wrap items-center justify-between gap-2">
          <div><h3 className="font-bold text-slate-900">Required competencies</h3><p className="mt-1 text-xs leading-5 text-slate-500">These are copied into an immutable assignment snapshot and checked against current verified ARMIS claims during submission and review.</p></div>
          <button className="inline-flex h-9 items-center gap-2 rounded-lg border border-sky-300 bg-white px-3 text-xs font-bold text-sky-800 hover:bg-sky-50" onClick={addCompetency} type="button"><Plus size={14} /> Add competency</button>
        </div>
        <div className="mt-3 space-y-3">
          {(form.requiredCompetencies || []).map((item, index) => (
            <div className="grid gap-3 rounded-lg border border-slate-200 bg-white p-3 sm:grid-cols-[minmax(0,1fr)_10rem_minmax(0,1fr)_auto]" key={`${index}-${item.competencyId}`}>
              <select aria-label={`Required competency ${index + 1}`} className={inputClass("mt-0")} onChange={(event) => updateCompetency(index, "competencyId", event.target.value)} value={item.competencyId}>
                <option value="">Select competency</option>
                {competencyOptions.map((option) => <option key={option.id} value={option.id}>{option.label} ({option.code})</option>)}
              </select>
              <select aria-label={`Minimum proficiency ${index + 1}`} className={inputClass("mt-0")} onChange={(event) => updateCompetency(index, "minimumProficiency", event.target.value)} value={item.minimumProficiency || "INTERMEDIATE"}>
                {(metadata.proficiencyLevels || []).map((option) => <option key={option.code} value={option.code}>{option.label}</option>)}
              </select>
              <input aria-label={`Competency notes ${index + 1}`} className={inputClass("mt-0")} maxLength={2000} onChange={(event) => updateCompetency(index, "notes", event.target.value)} placeholder="Optional requirement note" value={item.notes || ""} />
              <button aria-label={`Remove competency ${index + 1}`} className="inline-flex h-10 items-center justify-center rounded-lg border border-red-200 px-3 text-xs font-bold text-red-700 hover:bg-red-50" onClick={() => removeCompetency(index)} type="button">Remove</button>
            </div>
          ))}
          {!form.requiredCompetencies?.length && <p className="rounded-lg border border-dashed border-slate-300 bg-white p-3 text-sm text-slate-500">No additional competency snapshot has been added.</p>}
        </div>
      </div>
      <Field error={errors.notes} htmlFor="armis-assignment-notes" label="Notes" wide>
        <textarea className={inputClass("min-h-24 py-3")} id="armis-assignment-notes" maxLength={5000} onChange={(event) => set("notes", event.target.value)} value={form.notes} />
      </Field>
    </div>
  );
}

function ActualForm({ form, setForm, assignments, errors = {}, revisionMode = false }) {
  const set = (name, value) => setForm((current) => ({ ...current, [name]: value }));
  const selected = assignments.find((item) => String(item.id) === String(form.assignmentId));
  return (
    <div className="grid gap-4 sm:grid-cols-2">
      <Field error={errors.assignmentId} htmlFor="armis-actual-assignment" label="Approved assignment" required wide>
        <select className={inputClass()} disabled={revisionMode} id="armis-actual-assignment" onChange={(event) => set("assignmentId", event.target.value)} value={form.assignmentId}>
          <option value="">Select an approved or locked assignment</option>
          {assignments.filter((item) => ["APPROVED", "LOCKED"].includes(item.status)).map((item) => <option key={item.id} value={item.id}>{item.engagement?.engagement_code || item.engagement?.engagementCode || `Engagement #${item.auditEngagementId}`} · {item.resourceCode} · {item.assignedFrom} to {item.assignedUntil}</option>)}
        </select>
        {selected && <span className="mt-1 block text-xs text-slate-500">Planned assignment: {numberLabel(selected.plannedPersonDays)} person-days.</span>}
      </Field>
      <Field error={errors.periodStart} htmlFor="armis-actual-start" label="Period start" required>
        <input className={inputClass()} id="armis-actual-start" onChange={(event) => set("periodStart", event.target.value)} type="date" value={form.periodStart} />
      </Field>
      <Field error={errors.periodEnd} htmlFor="armis-actual-end" label="Period end" required>
        <input className={inputClass()} id="armis-actual-end" min={form.periodStart || undefined} onChange={(event) => set("periodEnd", event.target.value)} type="date" value={form.periodEnd} />
      </Field>
      <Field error={errors.actualPersonDays} htmlFor="armis-actual-days" label="Actual person-days" required>
        <input className={inputClass()} id="armis-actual-days" min="0" onChange={(event) => set("actualPersonDays", event.target.value)} step="0.25" type="number" value={form.actualPersonDays} />
      </Field>
      <Field error={errors.varianceReason} htmlFor="armis-actual-variance" label="Variance reason">
        <textarea className={inputClass("min-h-24 py-3")} id="armis-actual-variance" maxLength={5000} onChange={(event) => set("varianceReason", event.target.value)} placeholder="Required when cumulative actuals exceed the assignment plan" value={form.varianceReason} />
      </Field>
      <Field error={errors.notes} htmlFor="armis-actual-notes" label="Notes">
        <textarea className={inputClass("min-h-24 py-3")} id="armis-actual-notes" maxLength={5000} onChange={(event) => set("notes", event.target.value)} value={form.notes} />
      </Field>
    </div>
  );
}

function actionButtonClass(tone = "sky") {
  const tones = {
    sky: "border-sky-300 bg-sky-50 text-sky-800 hover:bg-sky-100",
    green: "border-emerald-300 bg-emerald-50 text-emerald-800 hover:bg-emerald-100",
    amber: "border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100",
    slate: "border-slate-300 bg-white text-slate-700 hover:bg-slate-50",
  };
  return `inline-flex h-8 items-center gap-1.5 rounded-lg border px-2.5 text-[11px] font-bold ${tones[tone] || tones.sky}`;
}

export default function ArmisAssignmentsPage() {
  const { user } = useAuth();
  const toast = useToast();
  const canAssignmentManage = hasPermission(user, "armis.assignment.manage");
  const canAssignmentReview = hasPermission(user, "armis.assignment.review");
  const canAssignmentApprove = hasPermission(user, "armis.assignment.approve");
  const canActualRecord = hasPermission(user, "armis.actuals.record");
  const canActualReview = hasPermission(user, "armis.actuals.review");
  const canActualApprove = hasPermission(user, "armis.actuals.approve");
  const canActualRevise = hasPermission(user, "armis.actuals.revise");

  const [activeTab, setActiveTab] = useState("overview");
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState("");
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [formErrors, setFormErrors] = useState({});
  const [metadata, setMetadata] = useState({ assignmentRoles: [], proficiencyLevels: [], assignmentStatuses: [], actualStatuses: [] });
  const [resources, setResources] = useState([]);
  const [requirements, setRequirements] = useState([]);
  const [competencyOptions, setCompetencyOptions] = useState([]);
  const [engagements, setEngagements] = useState([]);
  const [assignments, setAssignments] = useState([]);
  const [actuals, setActuals] = useState([]);
  const [formOpen, setFormOpen] = useState(false);
  const [formType, setFormType] = useState("assignment");
  const [formMode, setFormMode] = useState("create");
  const [assignmentForm, setAssignmentForm] = useState(emptyAssignment);
  const [actualForm, setActualForm] = useState(emptyActual);
  const [editingRecord, setEditingRecord] = useState(null);
  const [reviewTarget, setReviewTarget] = useState(null);
  const [reviewDecision, setReviewDecision] = useState("");
  const [reviewNotes, setReviewNotes] = useState("");
  const [conflictTarget, setConflictTarget] = useState(null);
  const [conflicts, setConflicts] = useState([]);
  const [conflictsLoading, setConflictsLoading] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const [assignmentMetadata, foundation, assignmentRows, actualRows, competencyMetadata, engagementData] = await Promise.all([
        armisApi.getAssignmentMetadata(),
        armisApi.getFoundation(),
        armisApi.getAssignments(),
        armisApi.getActuals(),
        armisApi.getCompetencyMetadata().catch(() => ({ competencies: [], proficiencyLevels: [] })),
        aemsEngagementApi.list({ perPage: 100, includeArchived: false }).catch(() => ({ engagements: [] })),
      ]);
      setMetadata(assignmentMetadata || {});
      setResources(Array.isArray(foundation?.profiles) ? foundation.profiles : []);
      setRequirements(Array.isArray(foundation?.requirements) ? foundation.requirements : []);
      const catalog = Array.isArray(competencyMetadata?.competencies) ? competencyMetadata.competencies : [];
      const requirementCompetencies = (foundation?.requirements || []).flatMap((item) => item.competencies || []).map((item) => ({ id: item.competencyId, code: item.code, label: item.label }));
      const uniqueCompetencies = new Map();
      [...catalog, ...requirementCompetencies].forEach((item) => { if (item?.id && !uniqueCompetencies.has(String(item.id))) uniqueCompetencies.set(String(item.id), item); });
      setCompetencyOptions([...uniqueCompetencies.values()]);
      setAssignments(Array.isArray(assignmentRows) ? assignmentRows : []);
      setActuals(Array.isArray(actualRows) ? actualRows : []);
      setEngagements(Array.isArray(engagementData?.engagements) ? engagementData.engagements : []);
    } catch (requestError) {
      setError(requestError.message || "Unable to load ARMIS assignment and actual person-day data.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    const timer = window.setTimeout(() => load().catch(() => undefined), 0);
    return () => window.clearTimeout(timer);
  }, [load]);

  const engagementOptions = useMemo(() => {
    const byId = new Map(engagements.map((item) => [String(item.id), item]));
    assignments.forEach((item) => {
      if (item.engagement && !byId.has(String(item.engagement.id))) byId.set(String(item.engagement.id), { ...item.engagement, engagementCode: item.engagement.engagement_code, plannedStartDate: item.engagement.planned_start_date, plannedEndDate: item.engagement.planned_end_date });
    });
    return [...byId.values()];
  }, [assignments, engagements]);

  const filteredAssignments = useMemo(() => {
    const term = search.trim().toLowerCase();
    return assignments.filter((row) => {
      if (statusFilter && row.status !== statusFilter) return false;
      if (!term) return true;
      return [row.resourceCode, row.resourceUser?.name, row.office?.name, row.assignmentRoleCode, row.engagement?.engagement_code, row.engagement?.title, row.requirement?.title].some((value) => String(value ?? "").toLowerCase().includes(term));
    });
  }, [assignments, search, statusFilter]);

  const filteredActuals = useMemo(() => {
    const term = search.trim().toLowerCase();
    return actuals.filter((row) => {
      if (statusFilter && row.status !== statusFilter) return false;
      if (!term) return true;
      return [row.resourceCode, row.resourceUser?.name, row.engagement?.engagement_code, row.engagement?.title, row.periodStart, row.periodEnd].some((value) => String(value ?? "").toLowerCase().includes(term));
    });
  }, [actuals, search, statusFilter]);

  const stats = useMemo(() => ({
    assignments: assignments.length,
    approvedAssignments: assignments.filter((row) => ["APPROVED", "LOCKED"].includes(row.status)).length,
    pendingAssignments: assignments.filter((row) => row.status === "SUBMITTED").length,
    actuals: actuals.length,
    pendingActuals: actuals.filter((row) => row.status === "SUBMITTED").length,
    actualDays: actuals.reduce((total, row) => total + Number(row.actualPersonDays || 0), 0),
  }), [actuals, assignments]);

  function openCreate(type) {
    setEditingRecord(null);
    setFormType(type);
    setFormMode("create");
    setFormErrors({});
    if (type === "assignment") setAssignmentForm(emptyAssignment);
    if (type === "actual") setActualForm(emptyActual);
    setFormOpen(true);
  }

  function openEdit(type, row, mode = "edit") {
    setEditingRecord(row);
    setFormType(type);
    setFormMode(mode);
    setFormErrors({});
    if (type === "assignment") setAssignmentForm({ auditEngagementId: String(row.auditEngagementId || row.engagement?.id || ""), resourceProfileId: String(row.resourceProfileId || ""), requirementId: String(row.requirementId || ""), assignmentRoleCode: row.assignmentRoleCode || "AUDITOR", assignedFrom: row.assignedFrom || "", assignedUntil: row.assignedUntil || "", plannedPersonDays: row.plannedPersonDays ?? "", requiredCompetencies: (row.requiredCompetencies || []).map((item) => ({ competencyId: String(item.competencyId || ""), minimumProficiency: item.minimumProficiency || "INTERMEDIATE", notes: item.notes || "" })), notes: row.notes || "" });
    if (type === "actual") setActualForm({ assignmentId: String(row.assignmentId || ""), periodStart: row.periodStart || "", periodEnd: row.periodEnd || "", actualPersonDays: row.actualPersonDays ?? "", varianceReason: row.varianceReason || "", notes: row.notes || "" });
    setFormOpen(true);
  }

  function payloadFor(type) {
    if (type === "assignment") {
      const payload = { auditEngagementId: Number(assignmentForm.auditEngagementId), resourceProfileId: Number(assignmentForm.resourceProfileId), requirementId: assignmentForm.requirementId ? Number(assignmentForm.requirementId) : null, assignmentRoleCode: assignmentForm.assignmentRoleCode, assignedFrom: assignmentForm.assignedFrom || null, assignedUntil: assignmentForm.assignedUntil || null, plannedPersonDays: Number(assignmentForm.plannedPersonDays), notes: assignmentForm.notes || null };
      const requiredCompetencies = (assignmentForm.requiredCompetencies || []).filter((item) => item.competencyId).map((item) => ({ competencyId: Number(item.competencyId), minimumProficiency: item.minimumProficiency || "INTERMEDIATE", notes: item.notes || null }));
      if (requiredCompetencies.length) payload.requiredCompetencies = requiredCompetencies;
      return payload;
    }
    return { assignmentId: Number(actualForm.assignmentId), periodStart: actualForm.periodStart, periodEnd: actualForm.periodEnd, actualPersonDays: Number(actualForm.actualPersonDays), varianceReason: actualForm.varianceReason || null, notes: actualForm.notes || null };
  }

  async function saveForm() {
    setSaving(true);
    setFormErrors({});
    try {
      const payload = payloadFor(formType);
      if (formType === "assignment") {
        if (formMode === "create") await armisApi.createAssignment(payload);
        if (formMode === "edit") await armisApi.updateAssignment(editingRecord.id, { ...payload, lockVersion: editingRecord.lockVersion });
        if (formMode === "revision") await armisApi.reviseAssignment(editingRecord.id, { ...payload, lockVersion: editingRecord.lockVersion });
      } else {
        if (formMode === "create") await armisApi.createActual(payload);
        if (formMode === "edit") await armisApi.updateActual(editingRecord.id, { ...payload, lockVersion: editingRecord.lockVersion });
        if (formMode === "revision") await armisApi.reviseActual(editingRecord.id, { ...payload, lockVersion: editingRecord.lockVersion });
      }
      setFormOpen(false);
      toast.success(formMode === "revision" ? "Correction revision created as a new Draft." : `${typeLabel(formType)} draft saved.`);
      await load();
    } catch (requestError) {
      if (requestError instanceof ApiError) setFormErrors(requestError.errors || {});
      toast.error(requestError.message || "The ARMIS record could not be saved.");
    } finally {
      setSaving(false);
    }
  }

  async function submit(type, row) {
    setSaving(true);
    try {
      if (type === "assignment") await armisApi.submitAssignment(row.id, row.lockVersion);
      else await armisApi.submitActual(row.id, row.lockVersion);
      toast.success(`${typeLabel(type)} submitted for independent review.`);
      await load();
    } catch (requestError) {
      toast.error(requestError.message || "The record could not be submitted.");
    } finally {
      setSaving(false);
    }
  }

  async function lock(type, row) {
    setSaving(true);
    try {
      if (type === "assignment") await armisApi.lockAssignment(row.id, row.lockVersion);
      else await armisApi.lockActual(row.id, row.lockVersion);
      toast.success(`${typeLabel(type)} locked as an immutable record.`);
      await load();
    } catch (requestError) {
      toast.error(requestError.message || "The approved record could not be locked.");
    } finally {
      setSaving(false);
    }
  }

  function openReview(type, row, decision) {
    setReviewTarget({ type, row });
    setReviewDecision(decision);
    setReviewNotes("");
  }

  async function review() {
    if (!reviewTarget) return;
    if (reviewDecision === "RETURN" && !reviewNotes.trim()) {
      toast.error("A return explanation is required.");
      return;
    }
    setSaving(true);
    try {
      const { type, row } = reviewTarget;
      const payload = { decision: reviewDecision, lockVersion: row.lockVersion, notes: reviewNotes.trim() || null };
      if (type === "assignment") await armisApi.reviewAssignment(row.id, payload);
      else await armisApi.reviewActual(row.id, payload);
      setReviewTarget(null);
      toast.success(`${typeLabel(type)} ${reviewDecision === "APPROVE" ? "approved" : "returned"}.`);
      await load();
    } catch (requestError) {
      toast.error(requestError.message || "The independent review could not be completed.");
    } finally {
      setSaving(false);
    }
  }

  async function showConflicts(row) {
    setConflictTarget(row);
    setConflicts([]);
    setConflictsLoading(true);
    try {
      const result = await armisApi.getAssignmentConflicts(row.id);
      setConflicts(Array.isArray(result) ? result : []);
    } catch (requestError) {
      toast.error(requestError.message || "Conflicts could not be loaded.");
    } finally {
      setConflictsLoading(false);
    }
  }

  function assignmentActions(row) {
    const buttons = [];
    if (canAssignmentManage && ["DRAFT", "RETURNED"].includes(row.status)) {
      buttons.push(<button className={actionButtonClass("slate")} key="edit" onClick={() => openEdit("assignment", row)} type="button"><FilePenLine size={13} /> Edit</button>);
      buttons.push(<button className={actionButtonClass()} key="submit" onClick={() => submit("assignment", row)} type="button"><Send size={13} /> Submit</button>);
    }
    if (canAssignmentReview && row.status === "SUBMITTED") {
      buttons.push(<button className={actionButtonClass("green")} key="approve" onClick={() => openReview("assignment", row, "APPROVE")} type="button"><CheckCircle2 size={13} /> Approve</button>);
      buttons.push(<button className={actionButtonClass("amber")} key="return" onClick={() => openReview("assignment", row, "RETURN")} type="button"><RotateCcw size={13} /> Return</button>);
    }
    if (canAssignmentApprove && row.status === "APPROVED") buttons.push(<button className={actionButtonClass("slate")} key="lock" onClick={() => lock("assignment", row)} type="button"><LockKeyhole size={13} /> Lock</button>);
    if (canAssignmentManage && ["APPROVED", "LOCKED"].includes(row.status)) buttons.push(<button className={actionButtonClass("sky")} key="revision" onClick={() => openEdit("assignment", row, "revision")} type="button"><Plus size={13} /> Revision</button>);
    buttons.push(<button className={actionButtonClass("amber")} key="conflicts" onClick={() => showConflicts(row)} type="button"><AlertTriangle size={13} /> Conflicts</button>);
    return <div className="flex flex-wrap justify-end gap-1.5">{buttons}</div>;
  }

  function actualActions(row) {
    const buttons = [];
    if (canActualRecord && ["DRAFT", "RETURNED"].includes(row.status)) {
      buttons.push(<button className={actionButtonClass("slate")} key="edit" onClick={() => openEdit("actual", row)} type="button"><FilePenLine size={13} /> Edit</button>);
      buttons.push(<button className={actionButtonClass()} key="submit" onClick={() => submit("actual", row)} type="button"><Send size={13} /> Submit</button>);
    }
    if (canActualReview && row.status === "SUBMITTED") {
      buttons.push(<button className={actionButtonClass("green")} key="approve" onClick={() => openReview("actual", row, "APPROVE")} type="button"><CheckCircle2 size={13} /> Approve</button>);
      buttons.push(<button className={actionButtonClass("amber")} key="return" onClick={() => openReview("actual", row, "RETURN")} type="button"><RotateCcw size={13} /> Return</button>);
    }
    if (canActualApprove && row.status === "APPROVED") buttons.push(<button className={actionButtonClass("slate")} key="lock" onClick={() => lock("actual", row)} type="button"><LockKeyhole size={13} /> Lock</button>);
    if (canActualRevise && ["APPROVED", "LOCKED"].includes(row.status)) buttons.push(<button className={actionButtonClass("sky")} key="revision" onClick={() => openEdit("actual", row, "revision")} type="button"><Plus size={13} /> Revision</button>);
    return buttons.length ? <div className="flex flex-wrap justify-end gap-1.5">{buttons}</div> : <span className="text-xs text-slate-400">No actions</span>;
  }

  const assignmentColumns = [
    { key: "engagement", label: "Engagement", render: (row) => <div><p className="font-bold text-slate-800">{row.engagement?.engagement_code || `Engagement #${row.auditEngagementId}`}</p><p className="mt-1 max-w-[18rem] truncate text-xs text-slate-500">{row.engagement?.title || "AEMS engagement"}</p></div> },
    { key: "resourceCode", label: "Resource", render: (row) => <div><p className="font-bold text-slate-800">{row.resourceCode || "—"}</p><p className="mt-1 text-xs text-slate-500">{row.resourceUser?.name || "Core user unavailable"}</p></div> },
    { key: "assignmentRoleCode", label: "Role", render: (row) => statusLabel(row.assignmentRoleCode) },
    { key: "assignedFrom", label: "Period", render: (row) => <div><p className="font-semibold text-slate-800">{dateLabel(row.assignedFrom)}</p><p className="mt-1 text-xs text-slate-500">to {dateLabel(row.assignedUntil)}</p></div> },
    { key: "plannedPersonDays", label: "Planned days", render: (row) => numberLabel(row.plannedPersonDays), sortValue: (row) => Number(row.plannedPersonDays || 0) },
    { key: "status", label: "Status", render: (row) => <StatusBadge tone={statusTone[row.status]}>{statusLabel(row.status)}</StatusBadge> },
    { key: "versionNumber", label: "Version", render: (row) => `v${row.versionNumber}` },
    { key: "actions", label: "", sortable: false, className: "text-right", render: assignmentActions },
  ];

  const actualColumns = [
    { key: "engagement", label: "Engagement", render: (row) => <div><p className="font-bold text-slate-800">{row.engagement?.engagement_code || `Assignment #${row.assignmentId}`}</p><p className="mt-1 max-w-[15rem] truncate text-xs text-slate-500">{row.engagement?.title || "AEMS engagement"}</p></div> },
    { key: "resourceCode", label: "Resource", render: (row) => <div><p className="font-bold text-slate-800">{row.resourceCode || "—"}</p><p className="mt-1 text-xs text-slate-500">{row.resourceUser?.name || "Core user unavailable"}</p></div> },
    { key: "periodStart", label: "Period", render: (row) => <div><p className="font-semibold text-slate-800">{dateLabel(row.periodStart)}</p><p className="mt-1 text-xs text-slate-500">to {dateLabel(row.periodEnd)}</p></div> },
    { key: "actualPersonDays", label: "Actual days", render: (row) => <div><p className="font-semibold text-slate-800">{numberLabel(row.actualPersonDays)}</p><p className="mt-1 text-xs text-slate-500">of {numberLabel(row.plannedPersonDays)} planned</p></div>, sortValue: (row) => Number(row.actualPersonDays || 0) },
    { key: "status", label: "Status", render: (row) => <StatusBadge tone={statusTone[row.status]}>{statusLabel(row.status)}</StatusBadge> },
    { key: "versionNumber", label: "Version", render: (row) => `v${row.versionNumber}` },
    { key: "actions", label: "", sortable: false, className: "text-right", render: actualActions },
  ];

  const tabClass = (tab) => `inline-flex min-h-10 items-center gap-2 rounded-lg px-3 text-sm font-bold transition ${activeTab === tab ? "bg-sky-700 text-white shadow-sm" : "text-slate-600 hover:bg-sky-50 hover:text-sky-800"}`;
  const formTitle = `${formMode === "revision" ? "Create correction revision" : formMode === "edit" ? "Edit Draft" : "Create Draft"} · ${typeLabel(formType)}`;

  return (
    <main className="mx-auto min-w-0 max-w-[1500px] p-4 sm:p-6">
      <RegistryHeader actions={<div className="flex flex-wrap gap-2"><button aria-label="Refresh ARMIS assignments" className="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700 hover:bg-slate-50 disabled:opacity-60" disabled={loading || saving} onClick={load} type="button"><RefreshCw className={loading ? "animate-spin" : ""} size={16} /> Refresh</button></div>} description="Assign authorized ARMIS resources to AEMS engagements and record independently reviewed person-days." icon={ClipboardCheck} title="ARMIS Assignments & Actuals" />
      <section className="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900"><strong>Authority boundary:</strong> assignments and actual person-days are a separate ARMIS ledger. AEMS team records and the AEMS resource-planning provider are unchanged until a future authority-switch phase.</section>
      {error && <ErrorState message={error} onRetry={load} />}
      {!error && <>
        <div className="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-5"><StatCard icon={UsersRound} label="Current assignments" note="Authorized scope" value={stats.assignments} tone="sky" /><StatCard icon={CheckCircle2} label="Approved / locked" note="Ready for actuals" value={stats.approvedAssignments} tone="emerald" /><StatCard icon={Gauge} label="Assignments awaiting review" note="Independent review queue" value={stats.pendingAssignments} tone="amber" /><StatCard icon={CalendarDays} label="Actual records" note="Current revisions" value={stats.actuals} tone="slate" /><StatCard icon={History} label="Actual person-days" note={`${stats.pendingActuals} awaiting review`} value={numberLabel(stats.actualDays)} tone="sky" /></div>
        <div className="mb-5 flex flex-wrap gap-2 rounded-xl border border-slate-200 bg-white p-2 shadow-sm" role="tablist" aria-label="ARMIS assignment sections">
          <button aria-selected={activeTab === "overview"} className={tabClass("overview")} onClick={() => setActiveTab("overview")} role="tab" type="button"><Gauge size={16} /> Overview</button>
          <button aria-selected={activeTab === "assignments"} className={tabClass("assignments")} onClick={() => setActiveTab("assignments")} role="tab" type="button"><UsersRound size={16} /> Assignments <span className="rounded-full bg-white/80 px-1.5 text-[11px]">{assignments.length}</span></button>
          <button aria-selected={activeTab === "actuals"} className={tabClass("actuals")} onClick={() => setActiveTab("actuals")} role="tab" type="button"><CalendarDays size={16} /> Actual person-days <span className="rounded-full bg-white/80 px-1.5 text-[11px]">{actuals.length}</span></button>
        </div>
        <div className="mb-5 flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-end sm:justify-between">
          <div><p className="text-xs font-bold uppercase tracking-wide text-slate-500">Workspace controls</p><p className="mt-1 text-sm text-slate-600">Search current ARMIS revisions and filter the review queue.</p></div>
          {activeTab !== "overview" && <div className="flex flex-col gap-2 sm:flex-row"><label className="relative"><Search className="absolute left-3 top-3 text-slate-400" size={16} /><input aria-label="Search ARMIS assignments" className="h-10 rounded-lg border border-slate-300 pl-9 pr-3 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100 sm:w-72" onChange={(event) => setSearch(event.target.value)} placeholder="Search engagement or resource" value={search} /></label><select aria-label="Filter ARMIS status" className="h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-700" onChange={(event) => setStatusFilter(event.target.value)} value={statusFilter}><option value="">All statuses</option>{(activeTab === "actuals" ? metadata.actualStatuses : metadata.assignmentStatuses || []).map((item) => <option key={item.code} value={item.code}>{item.label}</option>)}</select></div>}
        </div>
        {activeTab === "overview" && <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div className="flex items-center gap-2"><ShieldCheck className="text-sky-700" size={19} /><h2 className="font-bold text-slate-900">Controlled assignment lifecycle</h2></div><div className="mt-4 grid gap-3 md:grid-cols-3"><div className="rounded-xl border border-slate-200 bg-slate-50 p-4"><p className="text-xs font-bold uppercase tracking-wide text-slate-500">Assignments</p><p className="mt-2 text-sm font-semibold text-slate-800">Draft → Submitted → Returned / Approved → Locked</p><p className="mt-2 text-xs leading-5 text-slate-500">Approved and locked assignments are immutable. Corrections create a new revision.</p></div><div className="rounded-xl border border-slate-200 bg-slate-50 p-4"><p className="text-xs font-bold uppercase tracking-wide text-slate-500">Actuals</p><p className="mt-2 text-sm font-semibold text-slate-800">Draft → Submitted → Returned / Approved → Locked</p><p className="mt-2 text-xs leading-5 text-slate-500">Actual periods must remain inside the approved assignment dates.</p></div><div className="rounded-xl border border-slate-200 bg-slate-50 p-4"><p className="text-xs font-bold uppercase tracking-wide text-slate-500">Professional controls</p><p className="mt-2 text-sm font-semibold text-slate-800">Capacity, availability, overlap, office, and competency rules</p><p className="mt-2 text-xs leading-5 text-slate-500">The backend remains authoritative for conflicts, separation of duties, and optimistic locks.</p></div></div></section>}
        {activeTab === "assignments" && <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div className="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between"><div><h2 className="font-bold text-slate-900">Engagement assignments</h2><p className="mt-1 text-xs text-slate-500">Current revisions only. Use Conflicts before submission or review.</p></div>{canAssignmentManage && <button className="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white hover:bg-sky-800" onClick={() => openCreate("assignment")} type="button"><Plus size={16} /> New assignment</button>}</div><DataTable columns={assignmentColumns} emptyMessage="No current ARMIS assignments found." loading={loading} rows={filteredAssignments} /></section>}
        {activeTab === "actuals" && <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div className="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between"><div><h2 className="font-bold text-slate-900">Actual person-days</h2><p className="mt-1 text-xs text-slate-500">Actuals can be recorded only against an approved or locked ARMIS assignment.</p></div>{canActualRecord && <button className="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white hover:bg-sky-800" disabled={!stats.approvedAssignments} onClick={() => openCreate("actual")} type="button"><Plus size={16} /> Record actuals</button>}</div><DataTable columns={actualColumns} emptyMessage="No current actual person-day records found." loading={loading} rows={filteredActuals} /></section>}
      </>}

      <Modal description={formMode === "revision" ? "The previous approved or locked version remains immutable. This action creates a new Draft revision." : "Save a Draft first. Submission and independent review remain separate controlled actions."} footer={<><button className="h-10 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700" disabled={saving} onClick={() => !saving && setFormOpen(false)} type="button">Cancel</button><button className="h-10 rounded-lg bg-sky-700 px-5 text-sm font-bold text-white disabled:opacity-60" disabled={saving} onClick={saveForm} type="button">{saving ? "Saving…" : formMode === "revision" ? "Create revision" : "Save Draft"}</button></>} onClose={() => !saving && setFormOpen(false)} open={formOpen} size="xl" title={formTitle}>{formType === "assignment" ? <AssignmentForm competencyOptions={competencyOptions} engagements={engagementOptions} errors={formErrors} form={assignmentForm} metadata={metadata} requirements={requirements} resources={resources} revisionMode={formMode === "revision"} setForm={setAssignmentForm} /> : <ActualForm assignments={assignments} errors={formErrors} form={actualForm} revisionMode={formMode === "revision"} setForm={setActualForm} />}</Modal>
      <Modal description={reviewDecision === "APPROVE" ? "Confirm that you independently reviewed this ARMIS record." : "Return the record for correction. A clear explanation is required."} footer={<><button className="h-10 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700" disabled={saving} onClick={() => !saving && setReviewTarget(null)} type="button">Cancel</button><button className={`h-10 rounded-lg px-5 text-sm font-bold text-white disabled:opacity-60 ${reviewDecision === "APPROVE" ? "bg-emerald-700" : "bg-amber-700"}`} disabled={saving || (reviewDecision === "RETURN" && !reviewNotes.trim())} onClick={review} type="button">{saving ? "Saving…" : reviewDecision === "APPROVE" ? "Confirm approval" : "Return for correction"}</button></>} onClose={() => !saving && setReviewTarget(null)} open={Boolean(reviewTarget)} size="md" title={`${reviewDecision === "APPROVE" ? "Approve" : "Return"} ARMIS ${typeLabel(reviewTarget?.type || "assignment")}`}><label className="text-sm font-semibold text-slate-700" htmlFor="armis-review-notes">Reviewer explanation{reviewDecision === "RETURN" ? " *" : ""}<textarea className={inputClass("min-h-28 py-3")} id="armis-review-notes" onChange={(event) => setReviewNotes(event.target.value)} placeholder={reviewDecision === "APPROVE" ? "Optional approval note" : "Explain the required correction."} value={reviewNotes} /></label></Modal>
      <Modal description="The server evaluates the current assignment against its scope, availability, capacity, overlap, and competency rules." footer={<button className="h-10 rounded-lg bg-sky-700 px-5 text-sm font-bold text-white" onClick={() => setConflictTarget(null)} type="button">Close</button>} onClose={() => setConflictTarget(null)} open={Boolean(conflictTarget)} size="lg" title={`Assignment conflicts · ${conflictTarget?.resourceCode || "Resource"}`}>{conflictsLoading ? <div className="flex items-center gap-2 text-sm text-slate-500"><RefreshCw className="animate-spin" size={16} /> Checking current conflicts…</div> : conflicts.length ? <div className="space-y-3">{conflicts.map((item, index) => <div className={`rounded-xl border p-4 ${item.severity === "error" ? "border-red-200 bg-red-50 text-red-900" : "border-amber-200 bg-amber-50 text-amber-900"}`} key={`${item.type}-${index}`}><div className="flex items-start gap-2"><AlertTriangle className="mt-0.5 shrink-0" size={17} /><div><p className="text-xs font-bold uppercase tracking-wide">{statusLabel(item.type)} · {item.severity}</p><p className="mt-1 text-sm leading-6">{item.message}</p></div></div></div>)}</div> : <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm leading-6 text-emerald-900"><CheckCircle2 className="mr-2 inline" size={17} /> No current hard conflicts were found. Final submission and approval remain server-authorized.</div>}</Modal>
    </main>
  );
}

function typeLabel(type) {
  return type === "actual" ? "actual person-days" : "assignment";
}
