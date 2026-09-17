import {
  AlertTriangle,
  CalendarRange,
  CheckCircle2,
  Clock3,
  FilePenLine,
  Gauge,
  LockKeyhole,
  Plus,
  RefreshCw,
  RotateCcw,
  Send,
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
import { ApiError, armisApi } from "../../services/api";
import { useToast } from "../../ui/toast-context";

const currentYear = new Date().getFullYear();

const emptyForms = {
  availability: {
    resourceProfileId: "",
    availabilityType: "AVAILABLE",
    startDate: "",
    endDate: "",
    personDays: "",
    notes: "",
  },
  capacity: {
    resourceProfileId: "",
    fiscalYear: String(currentYear),
    availablePersonDays: "",
    notes: "",
  },
  workload: {
    resourceProfileId: "",
    requirementId: "",
    sourceModule: "ARMIS",
    sourceType: "AUDIT_ENGAGEMENT",
    sourceId: "",
    fiscalYear: String(currentYear),
    plannedPersonDays: "",
    notes: "",
  },
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
  return `mt-1 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100 ${extra}`;
}

function ErrorState({ message, onRetry }) {
  return (
    <section className="rounded-2xl border border-red-200 bg-red-50 px-6 py-12 text-center">
      <XCircle className="mx-auto text-red-600" size={34} />
      <h2 className="mt-3 font-bold text-red-900">
        ARMIS planning workspace unavailable
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
    <section
      className={`rounded-xl border p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md ${tones[tone] || tones.sky}`}
    >
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className="text-xs font-bold uppercase tracking-wide opacity-70">
            {label}
          </p>
          <p className="mt-2 text-3xl font-bold">{value}</p>
          {note && <p className="mt-1 text-xs opacity-75">{note}</p>}
        </div>
        <span className="grid h-10 w-10 place-items-center rounded-lg bg-white/80 shadow-sm">
          <Icon size={20} />
        </span>
      </div>
    </section>
  );
}

function Field({ label, htmlFor, required = false, error, children, wide = false }) {
  return (
    <label
      className={`text-sm font-semibold text-slate-700 ${wide ? "sm:col-span-2" : ""}`}
      htmlFor={htmlFor}
    >
      {label}
      {required ? " *" : ""}
      {children}
      {error && (
        <span className="mt-1 block text-xs font-semibold text-red-600">
          {Array.isArray(error) ? error[0] : error}
        </span>
      )}
    </label>
  );
}

function PlanningForm({ type, form, setForm, resources, metadata, errors = {} }) {
  const set = (name, value) =>
    setForm((current) => ({ ...current, [name]: value }));
  const profileOptions = resources.filter((item) => item.status !== "ARCHIVED");
  const years = metadata.fiscalYears || [currentYear];

  return (
    <div className="grid gap-4 sm:grid-cols-2">
      <Field
        error={errors.resourceProfileId}
        htmlFor={`armis-${type}-resource`}
        label="Resource profile"
        required
      >
        <select
          className={inputClass()}
          id={`armis-${type}-resource`}
          onChange={(event) => set("resourceProfileId", event.target.value)}
          value={form.resourceProfileId}
        >
          <option value="">Select an active resource profile</option>
          {profileOptions.map((item) => (
            <option key={item.id} value={item.id}>
              {item.resourceCode} — {item.user?.name || "Core user unavailable"}
            </option>
          ))}
        </select>
      </Field>

      {type === "availability" && (
        <>
          <Field error={errors.availabilityType} htmlFor="armis-availability-type" label="Availability type" required>
            <select className={inputClass()} id="armis-availability-type" onChange={(event) => set("availabilityType", event.target.value)} value={form.availabilityType}>
              {(metadata.availabilityTypes || []).map((item) => <option key={item.code} value={item.code}>{item.label}</option>)}
            </select>
          </Field>
          <Field error={errors.startDate} htmlFor="armis-availability-start" label="Start date" required>
            <input className={inputClass()} id="armis-availability-start" onChange={(event) => set("startDate", event.target.value)} type="date" value={form.startDate} />
          </Field>
          <Field error={errors.endDate} htmlFor="armis-availability-end" label="End date" required>
            <input className={inputClass()} id="armis-availability-end" onChange={(event) => set("endDate", event.target.value)} type="date" value={form.endDate} />
          </Field>
          <Field error={errors.personDays} htmlFor="armis-availability-days" label="Person-days">
            <input className={inputClass()} id="armis-availability-days" min="0" onChange={(event) => set("personDays", event.target.value)} step="0.25" type="number" value={form.personDays} />
          </Field>
        </>
      )}

      {type === "capacity" && (
        <>
          <Field error={errors.fiscalYear} htmlFor="armis-capacity-year" label="Fiscal year" required>
            <select className={inputClass()} id="armis-capacity-year" onChange={(event) => set("fiscalYear", event.target.value)} value={form.fiscalYear}>
              {years.map((year) => <option key={year} value={year}>{year}</option>)}
            </select>
          </Field>
          <Field error={errors.availablePersonDays} htmlFor="armis-capacity-days" label="Available person-days" required>
            <input className={inputClass()} id="armis-capacity-days" min="0" onChange={(event) => set("availablePersonDays", event.target.value)} step="0.25" type="number" value={form.availablePersonDays} />
          </Field>
        </>
      )}

      {type === "workload" && (
        <>
          <Field error={errors.sourceType} htmlFor="armis-workload-source-type" label="Source type" required>
            <input className={inputClass()} id="armis-workload-source-type" maxLength={60} onChange={(event) => set("sourceType", event.target.value)} placeholder="e.g. AUDIT_ENGAGEMENT" value={form.sourceType} />
          </Field>
          <Field error={errors.fiscalYear} htmlFor="armis-workload-year" label="Fiscal year">
            <select className={inputClass()} id="armis-workload-year" onChange={(event) => set("fiscalYear", event.target.value)} value={form.fiscalYear}>
              <option value="">Not assigned</option>
              {years.map((year) => <option key={year} value={year}>{year}</option>)}
            </select>
          </Field>
          <Field error={errors.sourceId} htmlFor="armis-workload-source-id" label="Source record ID">
            <input className={inputClass()} id="armis-workload-source-id" inputMode="numeric" onChange={(event) => set("sourceId", event.target.value)} placeholder="Optional source record" value={form.sourceId} />
          </Field>
          <Field error={errors.plannedPersonDays} htmlFor="armis-workload-days" label="Planned person-days" required>
            <input className={inputClass()} id="armis-workload-days" min="0.01" onChange={(event) => set("plannedPersonDays", event.target.value)} step="0.25" type="number" value={form.plannedPersonDays} />
          </Field>
          <Field error={errors.requirementId} htmlFor="armis-workload-requirement" label="ARMIS requirement ID">
            <input className={inputClass()} id="armis-workload-requirement" inputMode="numeric" onChange={(event) => set("requirementId", event.target.value)} placeholder="Optional normalized requirement" value={form.requirementId} />
          </Field>
        </>
      )}

      <Field error={errors.notes} htmlFor={`armis-${type}-notes`} label="Notes" wide>
        <textarea className={inputClass("min-h-24 py-3")} id={`armis-${type}-notes`} maxLength={5000} onChange={(event) => set("notes", event.target.value)} value={form.notes} />
      </Field>
    </div>
  );
}

function AvailabilityCalendar({ rows }) {
  const grouped = useMemo(() => {
    const groups = new Map();
    rows.forEach((row) => {
      const key = row.startDate ? row.startDate.slice(0, 7) : "undated";
      if (!groups.has(key)) groups.set(key, []);
      groups.get(key).push(row);
    });
    return [...groups.entries()].slice(0, 8);
  }, [rows]);

  return (
    <section className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm" data-testid="armis-availability-calendar">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div>
          <h3 className="font-bold text-slate-900">Availability calendar</h3>
          <p className="mt-1 text-xs text-slate-500">Current approved, submitted, and draft periods in your authorized scope.</p>
        </div>
        <CalendarRange className="text-sky-700" size={19} />
      </div>
      {grouped.length === 0 ? (
        <p className="mt-5 rounded-xl bg-slate-50 p-5 text-center text-sm text-slate-500">No current availability periods recorded.</p>
      ) : (
        <div className="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          {grouped.map(([month, monthRows]) => (
            <div className="rounded-xl border border-slate-200 bg-slate-50 p-3" key={month}>
              <p className="text-xs font-bold uppercase tracking-wide text-slate-500">{month === "undated" ? "Undated" : new Intl.DateTimeFormat("en-PH", { month: "long", year: "numeric" }).format(new Date(`${month}-01T00:00:00`))}</p>
              <div className="mt-2 space-y-2">
                {monthRows.slice(0, 4).map((row) => (
                  <div className="rounded-lg border border-white bg-white p-2 shadow-sm" key={row.id}>
                    <div className="flex items-center justify-between gap-2">
                      <p className="truncate text-xs font-bold text-slate-800">{row.resourceCode || "Resource"}</p>
                      <StatusBadge tone={statusTone[row.status]}>{statusLabel(row.status)}</StatusBadge>
                    </div>
                    <p className="mt-1 text-[11px] text-slate-500">{dateLabel(row.startDate)} – {dateLabel(row.endDate)}</p>
                    <p className="mt-1 text-[11px] font-semibold text-slate-600">{statusLabel(row.availabilityType)} · {numberLabel(row.personDays)} days</p>
                  </div>
                ))}
                {monthRows.length > 4 && <p className="text-[11px] font-semibold text-slate-500">+{monthRows.length - 4} more periods</p>}
              </div>
            </div>
          ))}
        </div>
      )}
    </section>
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

export default function ArmisPlanningPage() {
  const { user } = useAuth();
  const toast = useToast();
  const canAvailabilityManage = hasPermission(user, "armis.availability.manage");
  const canAvailabilityReview = hasPermission(user, "armis.availability.review");
  const canAvailabilityApprove = hasPermission(user, "armis.availability.approve");
  const canCapacityManage = hasPermission(user, "armis.capacity.manage");
  const canCapacityReview = hasPermission(user, "armis.capacity.review");
  const canCapacityApprove = hasPermission(user, "armis.capacity.approve");
  const canWorkloadManage = hasPermission(user, "armis.workload.manage");
  const canWorkloadReview = hasPermission(user, "armis.workload.review");
  const canWorkloadApprove = hasPermission(user, "armis.workload.approve");

  const [activeTab, setActiveTab] = useState("overview");
  const [fiscalYear, setFiscalYear] = useState(String(currentYear));
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState("");
  const [resources, setResources] = useState([]);
  const [metadata, setMetadata] = useState({ statuses: [], availabilityTypes: [], fiscalYears: [] });
  const [provider, setProvider] = useState(null);
  const [availability, setAvailability] = useState([]);
  const [capacity, setCapacity] = useState([]);
  const [workload, setWorkload] = useState([]);
  const [utilization, setUtilization] = useState({ rows: [], summary: {} });
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [formErrors, setFormErrors] = useState({});
  const [formOpen, setFormOpen] = useState(false);
  const [formType, setFormType] = useState("availability");
  const [formMode, setFormMode] = useState("create");
  const [form, setForm] = useState(emptyForms.availability);
  const [editingRecord, setEditingRecord] = useState(null);
  const [reviewTarget, setReviewTarget] = useState(null);
  const [reviewDecision, setReviewDecision] = useState("");
  const [reviewNotes, setReviewNotes] = useState("");

  const load = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const [planningMetadata, resourceData, availabilityData, capacityData, workloadData, utilizationData] = await Promise.all([
        armisApi.getPlanningMetadata(),
        armisApi.getResources({ includeArchived: false }),
        armisApi.getAvailability(),
        armisApi.getCapacity({ fiscalYear }),
        armisApi.getWorkload({ fiscalYear }),
        armisApi.getUtilization(Number(fiscalYear)),
      ]);
      setMetadata(planningMetadata || { statuses: [], availabilityTypes: [], fiscalYears: [] });
      setProvider(planningMetadata?.provider || null);
      setResources(Array.isArray(resourceData) ? resourceData : []);
      setAvailability(Array.isArray(availabilityData) ? availabilityData : []);
      setCapacity(Array.isArray(capacityData) ? capacityData : []);
      setWorkload(Array.isArray(workloadData) ? workloadData : []);
      setUtilization(utilizationData || { rows: [], summary: {} });
    } catch (requestError) {
      setError(requestError.message || "Unable to load ARMIS planning data.");
    } finally {
      setLoading(false);
    }
  }, [fiscalYear]);

  useEffect(() => {
    const timer = window.setTimeout(() => load().catch(() => undefined), 0);
    return () => window.clearTimeout(timer);
  }, [load]);

  const filtered = useMemo(() => {
    const term = search.trim().toLowerCase();
    const matches = (row) => {
      if (statusFilter && row.status !== statusFilter) return false;
      if (!term) return true;
      return [row.resourceCode, row.resourceUser?.name, row.office?.name, row.sourceType, row.availabilityType, row.requirement?.title]
        .some((value) => String(value ?? "").toLowerCase().includes(term));
    };
    return {
      availability: availability.filter(matches),
      capacity: capacity.filter(matches),
      workload: workload.filter(matches),
    };
  }, [availability, capacity, search, statusFilter, workload]);

  const stats = useMemo(() => {
    const summary = utilization.summary || {};
    return {
      resources: summary.resourceCount ?? utilization.rows?.length ?? 0,
      capacity: summary.capacityPersonDays ?? 0,
      planned: summary.plannedPersonDays ?? 0,
      utilization: summary.utilizationPercent === null || summary.utilizationPercent === undefined ? "—" : `${summary.utilizationPercent}%`,
      overCapacity: summary.overCapacityCount ?? 0,
      pending: [...availability, ...capacity, ...workload].filter((row) => row.status === "SUBMITTED").length,
    };
  }, [availability, capacity, utilization, workload]);

  function openCreate(type) {
    setEditingRecord(null);
    setFormType(type);
    setFormMode("create");
    setForm({ ...emptyForms[type], fiscalYear: String(fiscalYear) });
    setFormErrors({});
    setFormOpen(true);
  }

  function openEdit(type, row) {
    setEditingRecord(row);
    setFormType(type);
    setFormMode("edit");
    setFormErrors({});
    if (type === "availability") setForm({ resourceProfileId: String(row.resourceProfileId || ""), availabilityType: row.availabilityType || "AVAILABLE", startDate: row.startDate || "", endDate: row.endDate || "", personDays: row.personDays ?? "", notes: row.notes || "" });
    if (type === "capacity") setForm({ resourceProfileId: String(row.resourceProfileId || ""), fiscalYear: String(row.fiscalYear || fiscalYear), availablePersonDays: row.availablePersonDays ?? "", notes: row.notes || "" });
    if (type === "workload") setForm({ resourceProfileId: String(row.resourceProfileId || ""), requirementId: String(row.requirementId || ""), sourceModule: row.sourceModule || "ARMIS", sourceType: row.sourceType || "AUDIT_ENGAGEMENT", sourceId: row.sourceId || "", fiscalYear: row.fiscalYear ? String(row.fiscalYear) : "", plannedPersonDays: row.plannedPersonDays ?? "", notes: row.notes || "" });
    setFormOpen(true);
  }

  function openRevision(type, row) {
    openEdit(type, row);
    setFormMode("revision");
  }

  function payloadFor(type) {
    if (type === "availability") return { resourceProfileId: Number(form.resourceProfileId), availabilityType: form.availabilityType, startDate: form.startDate, endDate: form.endDate, personDays: form.personDays === "" ? null : Number(form.personDays), notes: form.notes || null };
    if (type === "capacity") return { resourceProfileId: Number(form.resourceProfileId), fiscalYear: Number(form.fiscalYear), availablePersonDays: Number(form.availablePersonDays), notes: form.notes || null };
    return { resourceProfileId: Number(form.resourceProfileId), requirementId: form.requirementId ? Number(form.requirementId) : null, sourceModule: form.sourceModule || "ARMIS", sourceType: form.sourceType, sourceId: form.sourceId ? Number(form.sourceId) : null, fiscalYear: form.fiscalYear ? Number(form.fiscalYear) : null, plannedPersonDays: Number(form.plannedPersonDays), notes: form.notes || null };
  }

  async function saveForm() {
    setSaving(true);
    setFormErrors({});
    try {
      const payload = payloadFor(formType);
      let saved;
      if (formType === "availability") {
        saved = formMode === "revision" ? await armisApi.reviseAvailability(editingRecord.id, { ...payload, lockVersion: editingRecord.lockVersion }) : formMode === "edit" ? await armisApi.updateAvailability(editingRecord.id, { ...payload, lockVersion: editingRecord.lockVersion }) : await armisApi.createAvailability(payload);
      } else if (formType === "capacity") {
        saved = formMode === "edit" ? await armisApi.updateCapacity(editingRecord.id, { ...payload, lockVersion: editingRecord.lockVersion }) : await armisApi.createCapacity(payload);
      } else {
        saved = formMode === "edit" ? await armisApi.updateWorkload(editingRecord.id, { ...payload, lockVersion: editingRecord.lockVersion }) : await armisApi.createWorkload(payload);
      }
      setFormOpen(false);
      toast.success(formMode === "revision" ? "Planning correction revision created." : `${statusLabel(formType)} draft saved.`);
      await load();
      return saved;
    } catch (requestError) {
      if (requestError instanceof ApiError) setFormErrors(requestError.errors || {});
      toast.error(requestError.message || "The ARMIS planning record could not be saved.");
      return null;
    } finally {
      setSaving(false);
    }
  }

  async function submit(type, row) {
    setSaving(true);
    try {
      const updated = type === "availability" ? await armisApi.submitAvailability(row.id, row.lockVersion) : type === "capacity" ? await armisApi.submitCapacity(row.id, row.lockVersion) : await armisApi.submitWorkload(row.id, row.lockVersion);
      toast.success(`${statusLabel(type)} submitted for independent review.`);
      await load();
      return updated;
    } catch (requestError) {
      toast.error(requestError.message || "The planning record could not be submitted.");
      return null;
    } finally {
      setSaving(false);
    }
  }

  async function lock(type, row) {
    setSaving(true);
    try {
      if (type === "availability") await armisApi.lockAvailability(row.id, row.lockVersion);
      if (type === "capacity") await armisApi.lockCapacity(row.id, row.lockVersion);
      if (type === "workload") await armisApi.lockWorkload(row.id, row.lockVersion);
      toast.success(`${statusLabel(type)} locked as an immutable planning record.`);
      await load();
    } catch (requestError) {
      toast.error(requestError.message || "The approved planning record could not be locked.");
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
    if (!reviewTarget || !reviewDecision) return;
    if (reviewDecision === "RETURN" && !reviewNotes.trim()) {
      toast.error("A return explanation is required.");
      return;
    }
    setSaving(true);
    try {
      const { type, row } = reviewTarget;
      const payload = { decision: reviewDecision, lockVersion: row.lockVersion, notes: reviewNotes.trim() || null };
      if (type === "availability") await armisApi.reviewAvailability(row.id, payload);
      if (type === "capacity") await armisApi.reviewCapacity(row.id, payload);
      if (type === "workload") await armisApi.reviewWorkload(row.id, payload);
      setReviewTarget(null);
      setReviewDecision("");
      toast.success(`${statusLabel(type)} ${reviewDecision === "APPROVE" ? "approved" : "returned"}.`);
      await load();
    } catch (requestError) {
      toast.error(requestError.message || "The planning review could not be completed.");
    } finally {
      setSaving(false);
    }
  }

  function actions(type, row) {
    const manage = type === "availability" ? canAvailabilityManage : type === "capacity" ? canCapacityManage : canWorkloadManage;
    const reviewPermission = type === "availability" ? canAvailabilityReview : type === "capacity" ? canCapacityReview : canWorkloadReview;
    const approvePermission = type === "availability" ? canAvailabilityApprove : type === "capacity" ? canCapacityApprove : canWorkloadApprove;
    const buttons = [];
    if (manage && ["DRAFT", "RETURNED"].includes(row.status)) {
      buttons.push(<button className={actionButtonClass("slate")} key="edit" onClick={() => openEdit(type, row)} type="button"><FilePenLine size={13} /> Edit</button>);
      buttons.push(<button className={actionButtonClass()} key="submit" onClick={() => submit(type, row)} type="button"><Send size={13} /> Submit</button>);
    }
    if (reviewPermission && row.status === "SUBMITTED") {
      buttons.push(<button className={actionButtonClass("green")} key="approve" onClick={() => openReview(type, row, "APPROVE")} type="button"><CheckCircle2 size={13} /> Approve</button>);
      buttons.push(<button className={actionButtonClass("amber")} key="return" onClick={() => openReview(type, row, "RETURN")} type="button"><RotateCcw size={13} /> Return</button>);
    }
    if (approvePermission && row.status === "APPROVED") buttons.push(<button className={actionButtonClass("slate")} key="lock" onClick={() => lock(type, row)} type="button"><LockKeyhole size={13} /> Lock</button>);
    if (manage && ["APPROVED", "LOCKED"].includes(row.status)) buttons.push(<button className={actionButtonClass("sky")} key="revision" onClick={() => openRevision(type, row)} type="button"><Plus size={13} /> Revision</button>);
    return buttons.length ? <div className="flex flex-wrap justify-end gap-1.5">{buttons}</div> : <span className="text-xs text-slate-400">No actions</span>;
  }

  const availabilityColumns = [
    { key: "resourceCode", label: "Resource", render: (row) => <div><p className="font-bold text-slate-800">{row.resourceCode || "—"}</p><p className="mt-1 text-xs text-slate-500">{row.resourceUser?.name || "Core user unavailable"}</p></div> },
    { key: "availabilityType", label: "Type", render: (row) => statusLabel(row.availabilityType) },
    { key: "startDate", label: "Period", render: (row) => <div><p className="font-semibold text-slate-800">{dateLabel(row.startDate)}</p><p className="mt-1 text-xs text-slate-500">to {dateLabel(row.endDate)}</p></div> },
    { key: "personDays", label: "Days", render: (row) => numberLabel(row.personDays), sortValue: (row) => Number(row.personDays || 0) },
    { key: "status", label: "Status", render: (row) => <StatusBadge tone={statusTone[row.status]}>{statusLabel(row.status)}</StatusBadge> },
    { key: "versionNumber", label: "Version", render: (row) => `v${row.versionNumber}` },
    { key: "actions", label: "", sortable: false, className: "text-right", render: (row) => actions("availability", row) },
  ];
  const capacityColumns = [
    { key: "resourceCode", label: "Resource", render: (row) => <div><p className="font-bold text-slate-800">{row.resourceCode || "—"}</p><p className="mt-1 text-xs text-slate-500">{row.resourceUser?.name || "Core user unavailable"}</p></div> },
    { key: "fiscalYear", label: "Fiscal year" },
    { key: "availablePersonDays", label: "Capacity", render: (row) => numberLabel(row.availablePersonDays), sortValue: (row) => Number(row.availablePersonDays || 0) },
    { key: "status", label: "Status", render: (row) => <StatusBadge tone={statusTone[row.status]}>{statusLabel(row.status)}</StatusBadge> },
    { key: "versionNumber", label: "Version", render: (row) => `v${row.versionNumber}` },
    { key: "actions", label: "", sortable: false, className: "text-right", render: (row) => actions("capacity", row) },
  ];
  const workloadColumns = [
    { key: "resourceCode", label: "Resource", render: (row) => <div><p className="font-bold text-slate-800">{row.resourceCode || "—"}</p><p className="mt-1 text-xs text-slate-500">{row.resourceUser?.name || "Core user unavailable"}</p></div> },
    { key: "sourceType", label: "Source", render: (row) => <div><p className="font-semibold text-slate-800">{row.sourceType || "—"}</p><p className="mt-1 text-xs text-slate-500">{row.sourceId ? `Record ${row.sourceId}` : "No source ID"}</p></div> },
    { key: "fiscalYear", label: "Fiscal year", render: (row) => row.fiscalYear || "—" },
    { key: "plannedPersonDays", label: "Planned days", render: (row) => numberLabel(row.plannedPersonDays), sortValue: (row) => Number(row.plannedPersonDays || 0) },
    { key: "status", label: "Status", render: (row) => <StatusBadge tone={statusTone[row.status]}>{statusLabel(row.status)}</StatusBadge> },
    { key: "versionNumber", label: "Version", render: (row) => `v${row.versionNumber}` },
    { key: "actions", label: "", sortable: false, className: "text-right", render: (row) => actions("workload", row) },
  ];

  const tabClass = (tab) => `inline-flex min-h-10 items-center gap-2 rounded-lg px-3 text-sm font-bold transition ${activeTab === tab ? "bg-sky-700 text-white shadow-sm" : "text-slate-600 hover:bg-sky-50 hover:text-sky-800"}`;
  const formTitle = `${formMode === "revision" ? "Create correction revision" : formMode === "edit" ? "Edit planning draft" : "Create planning draft"}`;

  return (
    <main className="mx-auto min-w-0 max-w-[1500px] p-4 sm:p-6">
      <RegistryHeader actions={<div className="flex flex-wrap gap-2"><button aria-label="Refresh ARMIS planning" className="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700 hover:bg-slate-50 disabled:opacity-60" disabled={loading || saving} onClick={load} type="button"><RefreshCw className={loading ? "animate-spin" : ""} size={16} /> Refresh</button></div>} description="Plan resource availability, approved capacity, and workload utilization for ARMIS, the sole operational resource provider." icon={CalendarRange} title="ARMIS Planning & Utilization" />

      <section className="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm leading-6 text-emerald-900"><strong>Planning boundary:</strong> ARMIS planning records are scope-aware and independently reviewed. AEMS consumes ARMIS for operational resource decisions; IAP values are retained only as historical planning lineage.</section>
      {provider && <p className="mb-4 text-xs font-semibold text-slate-500">Current provider boundary: <span className="text-slate-700">{provider.mode || provider}</span> · ARMIS planning remains non-authoritative.</p>}
      {error && <ErrorState message={error} onRetry={load} />}

      {!error && <>
        <div className="mb-5 flex flex-wrap gap-2 rounded-xl border border-slate-200 bg-white p-2 shadow-sm" role="tablist" aria-label="ARMIS planning sections">
          <button aria-selected={activeTab === "overview"} className={tabClass("overview")} onClick={() => setActiveTab("overview")} role="tab" type="button"><Gauge size={16} /> Overview</button>
          <button aria-selected={activeTab === "availability"} className={tabClass("availability")} onClick={() => setActiveTab("availability")} role="tab" type="button"><CalendarRange size={16} /> Availability calendar <span className="rounded-full bg-white/80 px-1.5 text-[11px]">{availability.length}</span></button>
          <button aria-selected={activeTab === "capacity"} className={tabClass("capacity")} onClick={() => setActiveTab("capacity")} role="tab" type="button"><UsersRound size={16} /> Capacity <span className="rounded-full bg-white/80 px-1.5 text-[11px]">{capacity.length}</span></button>
          <button aria-selected={activeTab === "workload"} className={tabClass("workload")} onClick={() => setActiveTab("workload")} role="tab" type="button"><Clock3 size={16} /> Workload <span className="rounded-full bg-white/80 px-1.5 text-[11px]">{workload.length}</span></button>
        </div>

        <div className="mb-5 flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-end sm:justify-between">
          <div><label className="text-xs font-bold uppercase tracking-wide text-slate-500" htmlFor="armis-planning-year">Fiscal year</label><select className="mt-1 h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-700" id="armis-planning-year" onChange={(event) => setFiscalYear(event.target.value)} value={fiscalYear}>{(metadata.fiscalYears || [currentYear]).map((year) => <option key={year} value={year}>{year}</option>)}</select></div>
          {activeTab !== "overview" && <div className="flex flex-col gap-2 sm:flex-row"><input aria-label="Search ARMIS planning" className="h-10 rounded-lg border border-slate-300 px-3 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100 sm:w-72" onChange={(event) => setSearch(event.target.value)} placeholder="Search resource or source" value={search} /><select aria-label="Filter planning status" className="h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-700" onChange={(event) => setStatusFilter(event.target.value)} value={statusFilter}><option value="">All statuses</option>{(metadata.statuses || []).map((item) => <option key={item.code} value={item.code}>{item.label}</option>)}</select></div>}
        </div>

        {activeTab === "overview" && <>
          <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5"><StatCard icon={UsersRound} label="Resources" note="In authorized scope" value={stats.resources} tone="sky" /><StatCard icon={Gauge} label="Approved capacity" note={`${fiscalYear} person-days`} value={numberLabel(stats.capacity)} tone="emerald" /><StatCard icon={Clock3} label="Planned workload" note={`${fiscalYear} person-days`} value={numberLabel(stats.planned)} tone="amber" /><StatCard icon={CheckCircle2} label="Utilization" note="Approved current plans" value={stats.utilization} tone="slate" /><StatCard icon={AlertTriangle} label="Over capacity" note="Requires professional review" value={stats.overCapacity} tone={stats.overCapacity ? "red" : "emerald"} /></div>
          <section className="mt-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><div className="flex flex-wrap items-center justify-between gap-2"><div><h3 className="font-bold text-slate-900">Resource utilization</h3><p className="mt-1 text-xs text-slate-500">{stats.pending} planning record{stats.pending === 1 ? "" : "s"} awaiting review across the current scope.</p></div><Gauge className="text-sky-700" size={19} /></div><div className="mt-4 overflow-x-auto"><table className="min-w-full text-left text-sm"><thead><tr className="border-b border-slate-200 text-[11px] uppercase tracking-wide text-slate-500"><th className="px-3 py-3">Resource</th><th className="px-3 py-3">Office</th><th className="px-3 py-3">Capacity</th><th className="px-3 py-3">Planned</th><th className="px-3 py-3">Remaining</th><th className="px-3 py-3">Utilization</th><th className="px-3 py-3">Status</th></tr></thead><tbody>{(utilization.rows || []).map((row) => <tr className="border-b border-slate-100 last:border-0" key={row.resourceProfileId}><td className="px-3 py-3"><p className="font-bold text-slate-800">{row.resourceCode}</p><p className="mt-1 text-xs text-slate-500">{row.resourceUser?.name || "Core user unavailable"}</p></td><td className="px-3 py-3 text-slate-600">{row.office?.name || "—"}</td><td className="px-3 py-3 font-semibold text-slate-700">{numberLabel(row.capacityPersonDays)}</td><td className="px-3 py-3 font-semibold text-slate-700">{numberLabel(row.plannedPersonDays)}</td><td className={`px-3 py-3 font-semibold ${row.overCapacity ? "text-red-700" : "text-emerald-700"}`}>{numberLabel(row.remainingPersonDays)}</td><td className="px-3 py-3 font-semibold text-slate-700">{row.utilizationPercent === null ? "—" : `${row.utilizationPercent}%`}</td><td className="px-3 py-3">{row.overCapacity ? <StatusBadge tone="danger">Over capacity</StatusBadge> : <StatusBadge tone="success">Within capacity</StatusBadge>}</td></tr>)}{!loading && !(utilization.rows || []).length && <tr><td className="px-3 py-8 text-center text-sm text-slate-500" colSpan="7">No resource utilization records found.</td></tr>}</tbody></table></div></section>
        </>}

        {activeTab === "availability" && <div className="space-y-5"><AvailabilityCalendar rows={filtered.availability} /><section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div className="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between"><div><h3 className="font-bold text-slate-900">Availability registry</h3><p className="mt-1 text-xs text-slate-500">Current availability records · approved and locked periods are immutable.</p></div>{canAvailabilityManage && <button className="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white hover:bg-sky-800" onClick={() => openCreate("availability")} type="button"><Plus size={16} /> New availability period</button>}</div><DataTable columns={availabilityColumns} emptyMessage="No current availability periods found." loading={loading} rows={filtered.availability} /></section></div>}
        {activeTab === "capacity" && <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div className="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between"><div><h3 className="font-bold text-slate-900">Annual capacity registry</h3><p className="mt-1 text-xs text-slate-500">Current capacity submissions for fiscal year {fiscalYear}.</p></div>{canCapacityManage && <button className="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white hover:bg-sky-800" onClick={() => openCreate("capacity")} type="button"><Plus size={16} /> New capacity submission</button>}</div><DataTable columns={capacityColumns} emptyMessage="No current capacity submissions found." loading={loading} rows={filtered.capacity} /></section>}
        {activeTab === "workload" && <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div className="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between"><div><h3 className="font-bold text-slate-900">Planned workload registry</h3><p className="mt-1 text-xs text-slate-500">Approved workload cannot exceed current approved ARMIS capacity.</p></div>{canWorkloadManage && <button className="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white hover:bg-sky-800" onClick={() => openCreate("workload")} type="button"><Plus size={16} /> New workload allocation</button>}</div><DataTable columns={workloadColumns} emptyMessage="No current workload allocations found." loading={loading} rows={filtered.workload} /></section>}
      </>}

      <Modal description={formMode === "revision" ? "The prior approved or locked record remains immutable. This form creates a new Draft revision." : "Save a Draft first. Submission and independent review are separate controlled actions."} footer={<><button className="h-10 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700" disabled={saving} onClick={() => !saving && setFormOpen(false)} type="button">Cancel</button><button className="h-10 rounded-lg bg-sky-700 px-5 text-sm font-bold text-white disabled:opacity-60" disabled={saving || !form.resourceProfileId} onClick={saveForm} type="button">{saving ? "Saving..." : formTitle}</button></>} onClose={() => !saving && setFormOpen(false)} open={formOpen} size="xl" title={`${formTitle} · ${statusLabel(formType)}`}><PlanningForm errors={formErrors} form={form} metadata={metadata} resources={resources} setForm={setForm} type={formType} /></Modal>

      <Modal description={reviewDecision === "APPROVE" ? "Confirm that you independently reviewed this ARMIS planning record." : "Return the record for correction. A clear explanation is required."} footer={<><button className="h-10 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700" disabled={saving} onClick={() => !saving && setReviewTarget(null)} type="button">Cancel</button><button className={`h-10 rounded-lg px-5 text-sm font-bold text-white disabled:opacity-60 ${reviewDecision === "APPROVE" ? "bg-emerald-700" : "bg-amber-700"}`} disabled={saving || (reviewDecision === "RETURN" && !reviewNotes.trim())} onClick={review} type="button">{saving ? "Saving..." : reviewDecision === "APPROVE" ? "Confirm approval" : "Return for correction"}</button></>} onClose={() => !saving && setReviewTarget(null)} open={Boolean(reviewTarget)} size="md" title={`${reviewDecision === "APPROVE" ? "Approve" : "Return"} ARMIS ${reviewTarget?.type || "planning"} record`}><label className="text-sm font-semibold text-slate-700" htmlFor="armis-review-notes">Reviewer explanation{reviewDecision === "RETURN" ? " *" : ""}<textarea className={inputClass("min-h-28 py-3")} id="armis-review-notes" onChange={(event) => setReviewNotes(event.target.value)} placeholder={reviewDecision === "APPROVE" ? "Optional approval note" : "Explain the required correction."} value={reviewNotes} /></label></Modal>
    </main>
  );
}
