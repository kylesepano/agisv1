import {
  Archive,
  BriefcaseBusiness,
  CheckCircle2,
  Clock3,
  History,
  Pencil,
  Plus,
  RefreshCw,
  RotateCcw,
  Search,
  ShieldCheck,
  UserRound,
  UsersRound,
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
  resourceCode: "",
  userId: "",
  officeId: "",
  category: "AUDIT_RESOURCE",
  effectiveFrom: "",
  effectiveTo: "",
  notes: "",
};

const statusTone = {
  DRAFT: "info",
  ACTIVE: "success",
  SUSPENDED: "warning",
  INACTIVE: "inactive",
  ARCHIVED: "danger",
};

const statusLabel = (value) =>
  String(value || "UNKNOWN")
    .replaceAll("_", " ")
    .toLowerCase()
    .replace(/(^|\s)\S/g, (letter) => letter.toUpperCase());

function dateLabel(value) {
  if (!value) return "Not set";
  const date = new Date(`${value}T00:00:00`);
  if (Number.isNaN(date.getTime())) return "Not set";
  return new Intl.DateTimeFormat("en-PH", {
    month: "short",
    day: "numeric",
    year: "numeric",
  }).format(date);
}

function dateTimeLabel(value) {
  if (!value) return "Not available";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "Not available";
  return new Intl.DateTimeFormat("en-PH", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(date);
}

function inputClass() {
  return "mt-1 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100 disabled:bg-slate-100";
}

function StatCard({ label, value, note, icon: Icon, tone = "sky" }) {
  const tones = {
    sky: "border-sky-200 bg-sky-50 text-sky-800",
    emerald: "border-emerald-200 bg-emerald-50 text-emerald-800",
    amber: "border-amber-200 bg-amber-50 text-amber-800",
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

function ErrorState({ message, onRetry }) {
  return (
    <section className="rounded-2xl border border-red-200 bg-red-50 px-6 py-12 text-center">
      <XCircle className="mx-auto text-red-600" size={34} />
      <h2 className="mt-3 font-bold text-red-900">
        ARMIS workspace unavailable
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

function ProfileForm({ form, setForm, identities, metadata, errors = {} }) {
  const selectedIdentity = identities.find(
    (identity) => String(identity.id) === String(form.userId),
  );

  function update(name, value) {
    setForm((current) => ({ ...current, [name]: value }));
  }

  function selectIdentity(value) {
    const identity = identities.find(
      (item) => String(item.id) === String(value),
    );
    setForm((current) => ({
      ...current,
      userId: value,
      officeId: identity?.officeId ? String(identity.officeId) : "",
    }));
  }

  return (
    <div className="grid gap-4 sm:grid-cols-2">
      <label className="sm:col-span-2 text-sm font-semibold text-slate-700">
        Core user
        <select
          className={inputClass()}
          onChange={(event) => selectIdentity(event.target.value)}
          value={form.userId}
        >
          <option value="">Select an active Core user</option>
          {identities.map((identity) => (
            <option key={identity.id} value={identity.id}>
              {identity.employeeId} — {identity.name} (
              {identity.office?.code || "Office"})
            </option>
          ))}
        </select>
        {selectedIdentity && (
          <span className="mt-1 block text-xs text-slate-500">
            {selectedIdentity.position || "Position not set"}
          </span>
        )}
        {errors.userId && (
          <span className="mt-1 block text-xs font-semibold text-red-600">
            {errors.userId[0]}
          </span>
        )}
      </label>
      <label className="text-sm font-semibold text-slate-700">
        Resource code (optional)
        <input
          className={inputClass()}
          maxLength={40}
          onChange={(event) =>
            update("resourceCode", event.target.value.toUpperCase())
          }
          placeholder="Generated if blank"
          value={form.resourceCode}
        />
        {errors.resourceCode && (
          <span className="mt-1 block text-xs font-semibold text-red-600">
            {errors.resourceCode[0]}
          </span>
        )}
      </label>
      <label className="text-sm font-semibold text-slate-700">
        Category
        <select
          className={inputClass()}
          onChange={(event) => update("category", event.target.value)}
          value={form.category}
        >
          {(metadata.categories || []).map((option) => (
            <option key={option.code} value={option.code}>
              {option.label}
            </option>
          ))}
        </select>
      </label>
      <label className="text-sm font-semibold text-slate-700">
        Office
        <input
          className={inputClass()}
          disabled
          value={selectedIdentity?.office?.name || "Selected from Core user"}
        />
      </label>
      <label className="text-sm font-semibold text-slate-700">
        Effective from
        <input
          className={inputClass()}
          onChange={(event) => update("effectiveFrom", event.target.value)}
          type="date"
          value={form.effectiveFrom}
        />
      </label>
      <label className="text-sm font-semibold text-slate-700">
        Effective to
        <input
          className={inputClass()}
          onChange={(event) => update("effectiveTo", event.target.value)}
          type="date"
          value={form.effectiveTo}
        />
      </label>
      <label className="text-sm font-semibold text-slate-700 sm:col-span-2">
        Notes
        <textarea
          className={`${inputClass()} min-h-24 py-3`}
          maxLength={5000}
          onChange={(event) => update("notes", event.target.value)}
          value={form.notes}
        />
      </label>
      {errors.officeId && (
        <p className="text-xs font-semibold text-red-600 sm:col-span-2">
          {errors.officeId[0]}
        </p>
      )}
      {errors.effectiveTo && (
        <p className="text-xs font-semibold text-red-600 sm:col-span-2">
          {errors.effectiveTo[0]}
        </p>
      )}
    </div>
  );
}

function DetailPanel({
  detail,
  events,
  canUpdate,
  canArchive,
  canRestore,
  onEdit,
  onTransition,
  onRestore,
}) {
  if (!detail) return null;
  const profile = detail;
  const competencies = profile.competencies || [];
  const availability = profile.availabilityPeriods || [];
  const eventItems = events || [];
  const options =
    {
      DRAFT: ["ACTIVE", "INACTIVE"],
      ACTIVE: ["SUSPENDED", "INACTIVE"],
      SUSPENDED: ["ACTIVE", "INACTIVE"],
      INACTIVE: ["ACTIVE", "ARCHIVED"],
    }[profile.status] || [];
  const visibleOptions = options.filter(
    (status) => status !== "ARCHIVED" || canArchive,
  );

  return (
    <section
      className="mt-5 rounded-2xl border border-slate-200 bg-white shadow-sm"
      data-testid="armis-resource-detail"
    >
      <header className="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 p-5">
        <div className="flex min-w-0 items-start gap-3">
          <span className="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-sky-100 text-sky-700">
            <UserRound size={22} />
          </span>
          <div className="min-w-0">
            <p className="text-xs font-bold uppercase tracking-wide text-slate-500">
              Resource profile
            </p>
            <h3 className="mt-1 truncate text-xl font-bold text-slate-900">
              {profile.resourceCode}
            </h3>
            <p className="mt-1 text-sm text-slate-600">
              {profile.user?.name || "Core user unavailable"} ·{" "}
              {profile.office?.name || "Office unavailable"}
            </p>
          </div>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <StatusBadge tone={statusTone[profile.status]}>
            {statusLabel(profile.status)}
          </StatusBadge>
          {canUpdate && profile.status !== "ARCHIVED" && (
            <button
              className="inline-flex h-9 items-center gap-2 rounded-lg border border-slate-300 px-3 text-xs font-bold text-slate-700 hover:bg-slate-50"
              onClick={onEdit}
              type="button"
            >
              <Pencil size={14} /> Edit
            </button>
          )}
          {canRestore && profile.status === "ARCHIVED" && (
            <button
              className="inline-flex h-9 items-center gap-2 rounded-lg border border-emerald-300 bg-emerald-50 px-3 text-xs font-bold text-emerald-800 hover:bg-emerald-100"
              onClick={onRestore}
              type="button"
            >
              <RotateCcw size={14} /> Restore
            </button>
          )}
        </div>
      </header>

      <div className="grid gap-4 p-5 lg:grid-cols-[minmax(0,1.4fr)_minmax(18rem,0.8fr)]">
        <div className="space-y-4">
          <div className="grid gap-3 sm:grid-cols-3">
            <div className="rounded-xl bg-slate-50 p-3">
              <p className="text-xs font-bold uppercase tracking-wide text-slate-500">
                Category
              </p>
              <p className="mt-1 text-sm font-semibold text-slate-800">
                {statusLabel(profile.category)}
              </p>
            </div>
            <div className="rounded-xl bg-slate-50 p-3">
              <p className="text-xs font-bold uppercase tracking-wide text-slate-500">
                Effective period
              </p>
              <p className="mt-1 text-sm font-semibold text-slate-800">
                {dateLabel(profile.effectiveFrom)} –{" "}
                {dateLabel(profile.effectiveTo)}
              </p>
            </div>
            <div className="rounded-xl bg-slate-50 p-3">
              <p className="text-xs font-bold uppercase tracking-wide text-slate-500">
                Lock version
              </p>
              <p className="mt-1 text-sm font-semibold text-slate-800">
                v{profile.lockVersion}
              </p>
            </div>
          </div>
          <div className="rounded-xl border border-slate-200 p-4">
            <div className="flex items-center justify-between gap-3">
              <h4 className="font-bold text-slate-900">Foundation records</h4>
              <span className="text-xs text-slate-500">ARMIS-owned ledger</span>
            </div>
            <div className="mt-3 grid gap-3 sm:grid-cols-2">
              <div className="rounded-lg bg-sky-50 p-3">
                <p className="text-xs font-bold uppercase tracking-wide text-sky-700">
                  Competencies
                </p>
                <p className="mt-1 text-2xl font-bold text-sky-900">
                  {competencies.length}
                </p>
                <p className="text-xs text-sky-700">
                  Exact Core evidence versions are preserved.
                </p>
              </div>
              <div className="rounded-lg bg-emerald-50 p-3">
                <p className="text-xs font-bold uppercase tracking-wide text-emerald-700">
                  Availability periods
                </p>
                <p className="mt-1 text-2xl font-bold text-emerald-900">
                  {availability.length}
                </p>
                <p className="text-xs text-emerald-700">
                  Review and approval actions arrive in later phases.
                </p>
              </div>
            </div>
            {profile.notes && (
              <p className="mt-3 whitespace-pre-wrap text-sm leading-6 text-slate-600">
                {profile.notes}
              </p>
            )}
          </div>
          {canUpdate && visibleOptions.length > 0 && (
            <div className="rounded-xl border border-slate-200 p-4">
              <h4 className="font-bold text-slate-900">Lifecycle actions</h4>
              <div className="mt-3 flex flex-wrap gap-2">
                {visibleOptions.map((status) => (
                  <button
                    className={`inline-flex h-9 items-center gap-2 rounded-lg border px-3 text-xs font-bold ${status === "ARCHIVED" ? "border-red-300 bg-red-50 text-red-800 hover:bg-red-100" : "border-sky-300 bg-sky-50 text-sky-800 hover:bg-sky-100"}`}
                    key={status}
                    onClick={() => onTransition(status)}
                    type="button"
                  >
                    {status === "ARCHIVED" ? (
                      <Archive size={14} />
                    ) : (
                      <CheckCircle2 size={14} />
                    )}
                    {statusLabel(status)}
                  </button>
                ))}
              </div>
            </div>
          )}
        </div>
        <aside className="rounded-xl border border-slate-200 p-4">
          <div className="flex items-center gap-2">
            <History className="text-sky-700" size={17} />
            <h4 className="font-bold text-slate-900">Profile history</h4>
          </div>
          <div className="mt-3 space-y-3">
            {eventItems.length === 0 && (
              <p className="text-sm text-slate-500">
                No workflow events recorded.
              </p>
            )}
            {eventItems.map((event) => (
              <div className="border-l-2 border-sky-200 pl-3" key={event.id}>
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <p className="text-xs font-bold text-slate-800">
                    {statusLabel(event.eventCode)}
                  </p>
                  <p className="text-[11px] text-slate-500">
                    {dateTimeLabel(event.createdAt)}
                  </p>
                </div>
                <p className="mt-1 text-xs text-slate-500">
                  {event.actor?.name || "System"}
                  {event.fromStatus || event.toStatus
                    ? ` · ${statusLabel(event.fromStatus || "NEW")} → ${statusLabel(event.toStatus || "")}`
                    : ""}
                </p>
                {event.reason && (
                  <p className="mt-1 text-xs leading-5 text-slate-600">
                    {event.reason}
                  </p>
                )}
              </div>
            ))}
          </div>
        </aside>
      </div>
    </section>
  );
}

export default function ArmisResourceRegistryPage() {
  const { user } = useAuth();
  const toast = useToast();
  const navigate = useNavigate();
  const { profileId } = useParams();
  const canCreate = hasPermission(user, "armis.resource.create");
  const canUpdate = hasPermission(user, "armis.resource.update");
  const canArchive = hasPermission(user, "armis.resource.archive");
  const canRestore = hasPermission(user, "armis.resource.restore");
  const [resources, setResources] = useState([]);
  const [metadata, setMetadata] = useState({ categories: [], provider: {} });
  const [identities, setIdentities] = useState([]);
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
  const [editing, setEditing] = useState(null);
  const [transitionTarget, setTransitionTarget] = useState(null);
  const [transitionReason, setTransitionReason] = useState("");

  const loadRegistry = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const [resourceData, metadataData, identityData] = await Promise.all([
        armisApi.getResources({ includeArchived: true }),
        armisApi.getMetadata(),
        canCreate || canUpdate ? armisApi.getIdentities() : Promise.resolve([]),
      ]);
      setResources(Array.isArray(resourceData) ? resourceData : []);
      setMetadata(metadataData || { categories: [], provider: {} });
      setIdentities(Array.isArray(identityData) ? identityData : []);
    } catch (requestError) {
      setError(
        requestError.message || "Unable to load the ARMIS resource registry.",
      );
    } finally {
      setLoading(false);
    }
  }, [canCreate, canUpdate]);

  const loadDetail = useCallback(async (id) => {
    if (!id) {
      setDetail(null);
      setEvents([]);
      return;
    }
    setDetailLoading(true);
    try {
      const [profile, history] = await Promise.all([
        armisApi.getResource(id),
        armisApi.getResourceEvents(id),
      ]);
      setDetail(profile);
      setEvents(Array.isArray(history) ? history : []);
    } catch (requestError) {
      setError(requestError.message || "Unable to load this ARMIS resource.");
      setDetail(null);
    } finally {
      setDetailLoading(false);
    }
  }, []);

  useEffect(() => {
    const timer = window.setTimeout(
      () => loadRegistry().catch(() => undefined),
      0,
    );
    return () => window.clearTimeout(timer);
  }, [loadRegistry]);

  useEffect(() => {
    const timer = window.setTimeout(() => loadDetail(profileId), 0);
    return () => window.clearTimeout(timer);
  }, [loadDetail, profileId]);

  const filteredResources = useMemo(() => {
    const term = search.trim().toLowerCase();
    return resources.filter((resource) => {
      if (status && resource.status !== status) return false;
      if (!term) return true;
      return [
        resource.resourceCode,
        resource.user?.name,
        resource.user?.employeeId,
        resource.office?.name,
        resource.office?.code,
        resource.category,
      ].some((value) =>
        String(value ?? "")
          .toLowerCase()
          .includes(term),
      );
    });
  }, [resources, search, status]);

  const stats = useMemo(
    () => ({
      total: resources.length,
      active: resources.filter((resource) => resource.status === "ACTIVE")
        .length,
      draft: resources.filter((resource) => resource.status === "DRAFT").length,
      inactive: resources.filter((resource) =>
        ["INACTIVE", "SUSPENDED"].includes(resource.status),
      ).length,
    }),
    [resources],
  );

  function openCreate() {
    setEditing(null);
    setForm(emptyForm);
    setFormErrors({});
    setFormOpen(true);
  }

  function openEdit() {
    if (!detail) return;
    setEditing(detail);
    setForm({
      resourceCode: detail.resourceCode || "",
      userId: String(detail.userId || ""),
      officeId: String(detail.officeId || ""),
      category: detail.category || "AUDIT_RESOURCE",
      effectiveFrom: detail.effectiveFrom || "",
      effectiveTo: detail.effectiveTo || "",
      notes: detail.notes || "",
    });
    setFormErrors({});
    setFormOpen(true);
  }

  async function saveProfile() {
    setSaving(true);
    setFormErrors({});
    try {
      const payload = {
        resourceCode: form.resourceCode || undefined,
        userId: Number(form.userId),
        officeId: Number(form.officeId),
        category: form.category,
        effectiveFrom: form.effectiveFrom || null,
        effectiveTo: form.effectiveTo || null,
        notes: form.notes || null,
      };
      const saved = editing
        ? await armisApi.updateResource(editing.id, {
            ...payload,
            lockVersion: editing.lockVersion,
          })
        : await armisApi.createResource(payload);
      toast.success(
        editing
          ? "ARMIS resource profile updated."
          : "ARMIS resource profile created.",
      );
      setFormOpen(false);
      await loadRegistry();
      navigate(`/audit-resource-management/resources/${saved.id}`);
    } catch (requestError) {
      if (requestError instanceof ApiError)
        setFormErrors(requestError.errors || {});
      toast.error(
        requestError.message ||
          "The ARMIS resource profile could not be saved.",
      );
    } finally {
      setSaving(false);
    }
  }

  function requestTransition(nextStatus) {
    setTransitionReason("");
    setTransitionTarget(nextStatus);
  }

  async function transition() {
    if (!detail || !transitionTarget) return;
    setSaving(true);
    try {
      const updated = await armisApi.transitionResource(detail.id, {
        status: transitionTarget,
        lockVersion: detail.lockVersion,
        reason: transitionReason || null,
      });
      toast.success(
        `Resource profile changed to ${statusLabel(transitionTarget)}.`,
      );
      setTransitionTarget(null);
      await Promise.all([loadRegistry(), loadDetail(updated.id)]);
    } catch (requestError) {
      toast.error(
        requestError.message ||
          "The ARMIS lifecycle transition could not be completed.",
      );
    } finally {
      setSaving(false);
    }
  }

  async function restore() {
    if (!detail) return;
    setSaving(true);
    try {
      const updated = await armisApi.restoreResource(
        detail.id,
        detail.lockVersion,
      );
      toast.success("ARMIS resource profile restored as inactive.");
      await Promise.all([loadRegistry(), loadDetail(updated.id)]);
    } catch (requestError) {
      toast.error(
        requestError.message ||
          "The ARMIS resource profile could not be restored.",
      );
    } finally {
      setSaving(false);
    }
  }

  const columns = [
    {
      key: "resourceCode",
      label: "Resource",
      sortValue: (row) => row.resourceCode,
      render: (row) => (
        <div>
          <p className="font-bold text-sky-800">{row.resourceCode}</p>
          <p className="mt-1 text-xs text-slate-500">
            {row.user?.employeeId || "No employee ID"}
          </p>
        </div>
      ),
    },
    {
      key: "user",
      label: "Core user",
      sortValue: (row) => row.user?.name,
      render: (row) => (
        <div>
          <p className="font-semibold text-slate-800">
            {row.user?.name || "Unavailable"}
          </p>
          <p className="mt-1 text-xs text-slate-500">
            {row.user?.position || "Position not set"}
          </p>
        </div>
      ),
    },
    {
      key: "office",
      label: "Office",
      sortValue: (row) => row.office?.name,
      render: (row) => (
        <span className="text-slate-700">
          {row.office?.code || row.office?.name || "Unavailable"}
        </span>
      ),
    },
    {
      key: "category",
      label: "Category",
      sortValue: (row) => row.category,
      render: (row) => (
        <span className="text-slate-700">{statusLabel(row.category)}</span>
      ),
    },
    {
      key: "status",
      label: "Status",
      sortValue: (row) => row.status,
      render: (row) => (
        <StatusBadge tone={statusTone[row.status]}>
          {statusLabel(row.status)}
        </StatusBadge>
      ),
    },
    {
      key: "lockVersion",
      label: "Version",
      sortValue: (row) => row.lockVersion,
      render: (row) => (
        <span className="text-slate-500">v{row.lockVersion}</span>
      ),
    },
    {
      key: "actions",
      label: "",
      sortable: false,
      render: (row) => (
        <button
          className="inline-flex h-9 items-center gap-2 rounded-lg border border-slate-300 px-3 text-xs font-bold text-slate-700 hover:bg-slate-50"
          onClick={(event) => {
            event.stopPropagation();
            navigate(`/audit-resource-management/resources/${row.id}`);
          }}
          type="button"
        >
          Open <ShieldCheck size={14} />
        </button>
      ),
    },
  ];

  if (loading && resources.length === 0) {
    return (
      <main className="grid min-h-[calc(100vh-7rem)] place-items-center p-5">
        <span className="flex items-center gap-3 text-sm font-semibold text-slate-500">
          <RefreshCw className="animate-spin" size={20} /> Loading ARMIS
          resources...
        </span>
      </main>
    );
  }

  return (
    <main className="p-3 sm:p-5">
      <RegistryHeader
        description="Maintain scope-aware ARMIS resource profiles used by AEMS as its sole operational resource provider."
        icon={UsersRound}
        title="ARMIS Resource Registry"
        actions={
          <>
            <button
              className="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-slate-300 px-4 text-sm font-bold text-slate-700 hover:bg-slate-50"
              onClick={() => loadRegistry()}
              type="button"
            >
              <RefreshCw className={loading ? "animate-spin" : ""} size={16} />{" "}
              Refresh
            </button>
            {canCreate && (
              <button
                className="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white shadow-sm hover:bg-sky-800"
                onClick={openCreate}
                type="button"
              >
                <Plus size={17} /> New resource
              </button>
            )}
          </>
        }
      />

      <section className="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900 shadow-sm">
        <div className="flex items-start gap-3">
          <Clock3 className="mt-0.5 shrink-0 text-emerald-700" size={18} />
          <div>
            <p className="font-bold">ARMIS operational provider</p>
            <p className="mt-1 leading-5">
              ARMIS records are the authoritative source for AEMS resource
              profiles, competencies, availability, and workload. IAP values
              remain historical planning lineage only.
            </p>
          </div>
        </div>
      </section>

      {error ? (
        <ErrorState
          message={error}
          onRetry={() => {
            setError("");
            loadRegistry();
          }}
        />
      ) : (
        <>
          <section className="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
              icon={UsersRound}
              label="Resource profiles"
              note="Visible in your scope"
              tone="sky"
              value={stats.total}
            />
            <StatCard
              icon={CheckCircle2}
              label="Active"
              note="Eligible for future planning"
              tone="emerald"
              value={stats.active}
            />
            <StatCard
              icon={Clock3}
              label="Draft"
              note="Awaiting activation"
              tone="amber"
              value={stats.draft}
            />
            <StatCard
              icon={BriefcaseBusiness}
              label="Suspended / inactive"
              note="Requires attention"
              tone="slate"
              value={stats.inactive}
            />
          </section>

          <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <header className="grid gap-3 border-b border-slate-200 p-4 lg:grid-cols-[minmax(18rem,1fr)_13rem_auto]">
              <label className="relative">
                <Search
                  className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"
                  size={17}
                />
                <input
                  className={`${inputClass()} pl-10`}
                  onChange={(event) => setSearch(event.target.value)}
                  placeholder="Search code, user, or office..."
                  value={search}
                />
              </label>
              <select
                className={inputClass()}
                onChange={(event) => setStatus(event.target.value)}
                value={status}
              >
                <option value="">All statuses</option>
                {(metadata.statuses || []).map((option) => (
                  <option key={option.code} value={option.code}>
                    {option.label}
                  </option>
                ))}
              </select>
              <p className="self-center text-sm text-slate-500">
                {filteredResources.length} of {resources.length} profiles
              </p>
            </header>
            <DataTable
              columns={columns}
              emptyMessage="No ARMIS resource profiles match your filters."
              loading={loading}
              onRowClick={(row) =>
                navigate(`/audit-resource-management/resources/${row.id}`)
              }
              rows={filteredResources}
            />
          </section>

          {detailLoading && (
            <div className="mt-5 rounded-xl border border-slate-200 bg-white p-6 text-center text-sm font-semibold text-slate-500">
              <RefreshCw className="mr-2 inline animate-spin" size={16} />{" "}
              Loading profile detail...
            </div>
          )}
          {!detailLoading && detail && (
            <DetailPanel
              canArchive={canArchive}
              canRestore={canRestore}
              canUpdate={canUpdate}
              detail={detail}
              events={events}
              onEdit={openEdit}
              onRestore={restore}
              onTransition={requestTransition}
            />
          )}
        </>
      )}

      <Modal
        description={
          editing
            ? "Update the profile using its current optimistic-lock version."
            : "Create a draft profile linked to an active Core user."
        }
        footer={
          <>
            <button
              className="inline-flex h-10 items-center rounded-lg border border-slate-300 px-4 text-sm font-bold text-slate-700 hover:bg-slate-50"
              onClick={() => setFormOpen(false)}
              type="button"
            >
              Cancel
            </button>
            <button
              className="inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white hover:bg-sky-800 disabled:cursor-not-allowed disabled:opacity-60"
              disabled={saving}
              onClick={saveProfile}
              type="button"
            >
              {saving && <RefreshCw className="animate-spin" size={15} />}
              {editing ? "Save profile" : "Create draft"}
            </button>
          </>
        }
        onClose={() => setFormOpen(false)}
        open={formOpen}
        size="lg"
        title={
          editing ? "Edit ARMIS resource profile" : "New ARMIS resource profile"
        }
      >
        <ProfileForm
          errors={formErrors}
          form={form}
          identities={identities}
          metadata={metadata}
          setForm={setForm}
        />
      </Modal>

      <Modal
        description={
          transitionTarget === "ARCHIVED"
            ? "Archiving is soft-deletable and requires a documented reason."
            : "The change is recorded in Activity Log, Audit Trail, and the ARMIS workflow timeline."
        }
        footer={
          <>
            <button
              className="inline-flex h-10 items-center rounded-lg border border-slate-300 px-4 text-sm font-bold text-slate-700 hover:bg-slate-50"
              onClick={() => setTransitionTarget(null)}
              type="button"
            >
              Cancel
            </button>
            <button
              className="inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white hover:bg-sky-800 disabled:opacity-60"
              disabled={
                saving ||
                (transitionTarget === "ARCHIVED" && !transitionReason.trim())
              }
              onClick={transition}
              type="button"
            >
              {saving && <RefreshCw className="animate-spin" size={15} />}{" "}
              Confirm {statusLabel(transitionTarget)}
            </button>
          </>
        }
        onClose={() => setTransitionTarget(null)}
        open={Boolean(transitionTarget)}
        title={`Change profile to ${statusLabel(transitionTarget)}`}
      >
        <label className="text-sm font-semibold text-slate-700">
          Reason {transitionTarget === "ARCHIVED" ? "(required)" : "(optional)"}
          <textarea
            className={`${inputClass()} min-h-28 py-3`}
            onChange={(event) => setTransitionReason(event.target.value)}
            placeholder="Record the business reason for this lifecycle change..."
            value={transitionReason}
          />
        </label>
      </Modal>
    </main>
  );
}
