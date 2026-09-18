import { useCallback, useEffect, useMemo, useState } from "react";
import {
  Archive,
  Blocks,
  Building2,
  CircleAlert,
  CircleCheckBig,
  Clock3,
  Pencil,
  Plus,
  RotateCcw,
  Search,
  ShieldAlert,
  X,
} from "lucide-react";
import { useAuth } from "../../auth/auth-context";
import IapAuditUniverseForm from "../../components/iap/IapAuditUniverseForm";
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
  auditAreaApi,
  auditUniverseApi,
  masterListApi,
  officeApi,
} from "../../services/api";
import { useToast } from "../../ui/toast-context";
import useRecordView from "../../hooks/useRecordView";

function formatDate(value) {
  if (!value) return "Never audited";
  return new Intl.DateTimeFormat("en-PH", {
    month: "short",
    day: "numeric",
    year: "numeric",
  }).format(new Date(`${value}T00:00:00`));
}

function materialityTone(code) {
  if (["CRITICAL", "HIGH"].includes(code)) return "danger";
  if (code === "MEDIUM") return "warning";
  return "success";
}

/**
 * Maintains the inventory of auditable subjects that feeds risk assessment,
 * prioritization, and ultimately the Annual Internal Audit Plan.
 */
export default function IapAuditUniversePage() {
  const { user } = useAuth();
  const toast = useToast();
  const [items, setItems] = useState([]);
  const [offices, setOffices] = useState([]);
  const [auditAreas, setAuditAreas] = useState([]);
  const [masterLists, setMasterLists] = useState([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [loadError, setLoadError] = useState("");
  const [errors, setErrors] = useState({});
  const [search, setSearch] = useState("");
  const [officeFilter, setOfficeFilter] = useState("");
  const [areaFilter, setAreaFilter] = useState("");
  const [statusFilter, setStatusFilter] = useState("");
  const [editorOpen, setEditorOpen] = useState(false);
  const [editing, setEditing] = useState(null);
  const [selected, setSelected] = useState(null);
  useRecordView(selected, {
    module: "IAP",
    recordType: "AUDIT_UNIVERSE",
    code: (record) => record.subjectCode,
    label: (record) => record.name ?? record.title,
  });
  const [archiveTarget, setArchiveTarget] = useState(null);
  const [restoreTarget, setRestoreTarget] = useState(null);

  const canManage = hasPermission(user, "iap.manage_universe");

  const load = useCallback(async () => {
    setLoading(true);
    setLoadError("");
    try {
      const [{ items: records }, officeRecords, areas, lists] =
        await Promise.all([
          auditUniverseApi.list({
            includeArchived: canManage,
            perPage: 100,
          }),
          officeApi.list(),
          auditAreaApi.list(),
          masterListApi.list(),
        ]);
      setItems(records);
      setOffices(officeRecords);
      setAuditAreas(areas);
      setMasterLists(lists);
    } catch (error) {
      setLoadError(
        error instanceof Error
          ? error.message
          : "Unable to load the Audit Universe.",
      );
    } finally {
      setLoading(false);
    }
  }, [canManage]);

  useEffect(() => {
    const timer = window.setTimeout(load, 0);
    return () => window.clearTimeout(timer);
  }, [load]);

  const subjectTypes = useMemo(
    () =>
      (
        masterLists.find(
          (list) => list.code === "IAP_AUDIT_UNIVERSE_SUBJECT_TYPE",
        )?.items ?? []
      ).filter((item) => item.isActive),
    [masterLists],
  );
  const riskLevels = useMemo(
    () =>
      (
        masterLists.find((list) => list.code === "RISK_LEVEL")?.items ?? []
      ).filter((item) => item.isActive),
    [masterLists],
  );

  const filteredItems = useMemo(() => {
    const query = search.trim().toLowerCase();
    return items.filter((item) => {
      const matchesSearch =
        !query ||
        [
          item.subjectCode,
          item.name,
          item.description,
          item.subjectType?.label,
          item.responsibleOffice?.code,
          item.responsibleOffice?.name,
          item.primaryAuditArea?.code,
          item.primaryAuditArea?.name,
          item.materialityExposure,
          ...item.stakeholderOffices.flatMap((office) => [
            office.code,
            office.name,
          ]),
        ].some((value) =>
          String(value ?? "")
            .toLowerCase()
            .includes(query),
        );
      const matchesOffice =
        !officeFilter ||
        String(item.responsibleOfficeId) === String(officeFilter) ||
        item.stakeholderOffices.some(
          (office) => String(office.id) === String(officeFilter),
        );
      const matchesArea =
        !areaFilter || String(item.primaryAuditAreaId) === String(areaFilter);
      const matchesStatus =
        !statusFilter ||
        (statusFilter === "ARCHIVED" && item.isArchived) ||
        (statusFilter === "ACTIVE" && item.isActive && !item.isArchived) ||
        (statusFilter === "INACTIVE" && !item.isActive && !item.isArchived) ||
        (statusFilter === "NEVER_AUDITED" &&
          !item.lastAuditDate &&
          !item.isArchived);
      return matchesSearch && matchesOffice && matchesArea && matchesStatus;
    });
  }, [areaFilter, items, officeFilter, search, statusFilter]);

  const stats = useMemo(
    () => ({
      total: items.filter((item) => !item.isArchived).length,
      active: items.filter((item) => item.isActive && !item.isArchived).length,
      high: items.filter(
        (item) =>
          !item.isArchived &&
          ["HIGH", "CRITICAL"].includes(item.materialityLevel?.code),
      ).length,
      neverAudited: items.filter(
        (item) => !item.isArchived && !item.lastAuditDate,
      ).length,
    }),
    [items],
  );

  const columns = [
    {
      key: "subjectCode",
      label: "Auditable Subject",
      render: (item) => (
        <span>
          <strong className="block text-sm text-slate-900">{item.name}</strong>
          <span className="mt-0.5 block text-xs font-semibold text-sky-700">
            {item.subjectCode} · {item.subjectType?.label}
          </span>
        </span>
      ),
    },
    {
      key: "responsibleOffice",
      label: "Responsible Office",
      sortValue: (item) => item.responsibleOffice?.name,
      render: (item) => (
        <span>
          <strong className="text-slate-800">
            {item.responsibleOffice?.code}
          </strong>
          <span className="ml-2 text-xs text-slate-500">
            {item.responsibleOffice?.name}
          </span>
        </span>
      ),
    },
    {
      key: "primaryAuditArea",
      label: "Audit Area",
      sortValue: (item) => item.primaryAuditArea?.name,
      render: (item) => (
        <span>
          <strong className="block text-sm text-slate-700">
            {item.primaryAuditArea?.name}
          </strong>
          <span className="text-xs text-slate-500">
            {item.primaryAuditArea?.code}
          </span>
        </span>
      ),
    },
    {
      key: "materialityLevel",
      label: "Exposure",
      sortValue: (item) => item.materialityLevel?.label,
      render: (item) =>
        item.materialityLevel ? (
          <StatusBadge tone={materialityTone(item.materialityLevel.code)}>
            {item.materialityLevel.label}
          </StatusBadge>
        ) : (
          "Not classified"
        ),
    },
    {
      key: "lastAuditDate",
      label: "Last Audit",
      render: (item) => (
        <span
          className={
            item.lastAuditDate
              ? "text-slate-600"
              : "font-semibold text-amber-700"
          }
        >
          {formatDate(item.lastAuditDate)}
        </span>
      ),
    },
    {
      key: "stakeholders",
      label: "Stakeholders",
      sortable: false,
      render: (item) => (
        <span className="rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600">
          {item.stakeholderOffices.length} office
          {item.stakeholderOffices.length === 1 ? "" : "s"}
        </span>
      ),
    },
    {
      key: "status",
      label: "Status",
      render: (item) => (
        <StatusBadge
          tone={
            item.isArchived ? "inactive" : item.isActive ? "active" : "warning"
          }
        >
          {item.isArchived ? "Archived" : item.isActive ? "Active" : "Inactive"}
        </StatusBadge>
      ),
    },
    ...(canManage
      ? [
          {
            key: "actions",
            label: "Actions",
            sortable: false,
            render: (item) => (
              <div className="flex justify-end gap-1.5">
                {item.isArchived ? (
                  <button
                    aria-label={`Restore ${item.name}`}
                    className="grid h-9 w-9 place-items-center rounded-lg border border-emerald-200 text-emerald-700 transition hover:bg-emerald-50"
                    onClick={() => setRestoreTarget(item)}
                    type="button"
                  >
                    <RotateCcw size={16} />
                  </button>
                ) : (
                  <>
                    <button
                      aria-label={`Edit ${item.name}`}
                      className="grid h-9 w-9 place-items-center rounded-lg border border-sky-200 text-sky-700 transition hover:bg-sky-50"
                      onClick={() => {
                        setErrors({});
                        setEditing(item);
                        setEditorOpen(true);
                      }}
                      type="button"
                    >
                      <Pencil size={16} />
                    </button>
                    <button
                      aria-label={`Archive ${item.name}`}
                      className="grid h-9 w-9 place-items-center rounded-lg border border-red-200 text-red-600 transition hover:bg-red-50"
                      onClick={() => setArchiveTarget(item)}
                      type="button"
                    >
                      <Archive size={16} />
                    </button>
                  </>
                )}
              </div>
            ),
          },
        ]
      : []),
  ];

  async function saveItem(payload) {
    setSaving(true);
    setErrors({});
    try {
      if (editing) {
        await auditUniverseApi.update(editing.id, payload);
        toast.success("Auditable subject updated successfully.");
      } else {
        await auditUniverseApi.create(payload);
        toast.success("Auditable subject added to the Audit Universe.");
      }
      setEditorOpen(false);
      setEditing(null);
      await load();
    } catch (error) {
      if (error instanceof ApiError) setErrors(error.errors);
      toast.error(
        error instanceof Error
          ? error.message
          : "Unable to save the auditable subject.",
      );
    } finally {
      setSaving(false);
    }
  }

  async function archiveItem() {
    if (!archiveTarget) return;
    setSaving(true);
    try {
      await auditUniverseApi.archive(archiveTarget.id);
      toast.success(`${archiveTarget.subjectCode} was archived.`);
      setArchiveTarget(null);
      await load();
    } catch (error) {
      toast.error(
        error instanceof Error
          ? error.message
          : "Unable to archive the auditable subject.",
      );
    } finally {
      setSaving(false);
    }
  }

  async function restoreItem() {
    if (!restoreTarget) return;
    setSaving(true);
    try {
      await auditUniverseApi.restore(restoreTarget.id);
      toast.success(`${restoreTarget.subjectCode} was restored.`);
      setRestoreTarget(null);
      await load();
    } catch (error) {
      toast.error(
        error instanceof Error
          ? error.message
          : "Unable to restore the auditable subject.",
      );
    } finally {
      setSaving(false);
    }
  }

  const hasFilters = Boolean(
    search || officeFilter || areaFilter || statusFilter,
  );

  return (
    <main className="p-3 sm:p-5">
      <RegistryHeader
        actions={
          canManage && (
            <button
              className="inline-flex min-h-11 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-sky-800 hover:shadow-lg"
              onClick={() => {
                setErrors({});
                setEditing(null);
                setEditorOpen(true);
              }}
              type="button"
            >
              <Plus size={18} />
              Add auditable subject
            </button>
          )
        }
        description="Maintain the authoritative inventory of processes, programs, systems, services, projects, entities, funds, contracts, assets, and cross-office activities that may be audited."
        icon={Blocks}
        readOnly={!canManage}
        title="Audit Universe Registry"
      />

      <section className="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <SummaryCard
          icon={Blocks}
          label="Total Audit Universe"
          tone="sky"
          value={stats.total}
        />
        <SummaryCard
          icon={CircleCheckBig}
          label="Active Subjects"
          tone="emerald"
          value={stats.active}
        />
        <SummaryCard
          icon={ShieldAlert}
          label="High / Critical Exposure"
          tone="red"
          value={stats.high}
        />
        <SummaryCard
          icon={Clock3}
          label="Never Audited"
          tone="amber"
          value={stats.neverAudited}
        />
      </section>

      {loadError && (
        <div className="mb-4 flex gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700">
          <CircleAlert className="shrink-0" size={19} />
          {loadError}
        </div>
      )}

      <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div className="grid gap-2 border-b border-slate-200 p-4 xl:grid-cols-[minmax(18rem,1fr)_15rem_15rem_13rem_auto]">
          <label className="flex min-h-11 items-center gap-2 rounded-lg border border-slate-300 px-3 focus-within:border-sky-500 focus-within:ring-2 focus-within:ring-sky-100">
            <Search className="text-slate-400" size={18} />
            <input
              className="min-w-0 flex-1 bg-transparent text-sm outline-none placeholder:text-slate-400"
              onChange={(event) => setSearch(event.target.value)}
              placeholder="Search subject, office, audit area, or exposure..."
              value={search}
            />
          </label>
          <SearchableSelect
            onChange={setOfficeFilter}
            options={offices.map((office) => ({
              value: office.id,
              label: `${office.code} — ${office.name}`,
            }))}
            placeholder="Filter by office"
            searchPlaceholder="Search offices..."
            value={officeFilter}
          />
          <SearchableSelect
            onChange={setAreaFilter}
            options={auditAreas.map((area) => ({
              value: area.id,
              label: `${area.code} — ${area.name}`,
            }))}
            placeholder="Filter by audit area"
            searchPlaceholder="Search audit areas..."
            value={areaFilter}
          />
          <SearchableSelect
            onChange={setStatusFilter}
            options={[
              { value: "ACTIVE", label: "Active" },
              { value: "INACTIVE", label: "Inactive" },
              { value: "NEVER_AUDITED", label: "Never Audited" },
              ...(canManage ? [{ value: "ARCHIVED", label: "Archived" }] : []),
            ]}
            placeholder="Filter by status"
            searchPlaceholder="Search statuses..."
            value={statusFilter}
          />
          <button
            className="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-slate-300 px-4 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 disabled:opacity-40"
            disabled={!hasFilters}
            onClick={() => {
              setSearch("");
              setOfficeFilter("");
              setAreaFilter("");
              setStatusFilter("");
            }}
            type="button"
          >
            <X size={17} />
            Clear filters
          </button>
        </div>
        <DataTable
          columns={columns}
          emptyMessage="No auditable subjects match the current filters."
          loading={loading}
          onRowClick={setSelected}
          pageSizeOptions={[10, 25, 50, 100]}
          rows={filteredItems}
        />
      </section>

      <Modal
        footer={
          <>
            <button
              className="min-h-10 rounded-lg border border-slate-300 px-4 text-sm font-bold text-slate-700 hover:bg-slate-50"
              disabled={saving}
              onClick={() => {
                setEditorOpen(false);
                setEditing(null);
              }}
              type="button"
            >
              Cancel
            </button>
            <button
              className="min-h-10 rounded-lg bg-sky-700 px-5 text-sm font-bold text-white hover:bg-sky-800 disabled:opacity-60"
              disabled={saving}
              form="audit-universe-editor-form"
              type="submit"
            >
              {saving
                ? "Saving..."
                : editing
                  ? "Update subject"
                  : "Add subject"}
            </button>
          </>
        }
        onClose={() => {
          if (!saving) {
            setEditorOpen(false);
            setEditing(null);
          }
        }}
        open={editorOpen}
        size="xl"
        title={
          editing ? `Edit ${editing.subjectCode}` : "Add auditable subject"
        }
      >
        <IapAuditUniverseForm
          errors={errors}
          formId="audit-universe-editor-form"
          item={editing}
          key={editing?.id ?? "new-universe-item"}
          offices={offices}
          onSubmit={saveItem}
          riskLevels={riskLevels}
          subjectTypes={subjectTypes}
        />
      </Modal>

      <Modal
        onClose={() => setSelected(null)}
        open={Boolean(selected)}
        size="xl"
        title={selected?.name ?? "Auditable subject details"}
      >
        {selected && (
          <div className="grid gap-5">
            <section className="rounded-xl border border-slate-200 bg-slate-50 p-4">
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <span className="text-xs font-bold uppercase tracking-wide text-sky-700">
                    {selected.subjectCode} · {selected.subjectType?.label}
                  </span>
                  <p className="mt-3 text-sm leading-6 text-slate-700">
                    {selected.description}
                  </p>
                </div>
                <div className="flex gap-2">
                  {selected.materialityLevel && (
                    <StatusBadge
                      tone={materialityTone(selected.materialityLevel.code)}
                    >
                      {selected.materialityLevel.label} exposure
                    </StatusBadge>
                  )}
                  <StatusBadge
                    tone={
                      selected.isArchived
                        ? "inactive"
                        : selected.isActive
                          ? "active"
                          : "warning"
                    }
                  >
                    {selected.isArchived
                      ? "Archived"
                      : selected.isActive
                        ? "Active"
                        : "Inactive"}
                  </StatusBadge>
                </div>
              </div>
            </section>

            <section className="grid gap-3 sm:grid-cols-2">
              <article className="rounded-xl border border-slate-200 p-4">
                <h3 className="flex items-center gap-2 text-sm font-bold text-slate-800">
                  <Building2 className="text-sky-700" size={18} />
                  Responsible office
                </h3>
                <strong className="mt-3 block text-slate-900">
                  {selected.responsibleOffice?.code}
                </strong>
                <span className="text-sm text-slate-600">
                  {selected.responsibleOffice?.name}
                </span>
              </article>
              <article className="rounded-xl border border-slate-200 p-4">
                <h3 className="text-sm font-bold text-slate-800">
                  Primary audit area
                </h3>
                <strong className="mt-3 block text-slate-900">
                  {selected.primaryAuditArea?.code}
                </strong>
                <span className="text-sm text-slate-600">
                  {selected.primaryAuditArea?.name}
                </span>
              </article>
            </section>

            <section className="grid gap-4 sm:grid-cols-2">
              <div>
                <h3 className="text-sm font-bold text-slate-800">
                  Indicative audit scope
                </h3>
                <p className="mt-2 text-sm leading-6 text-slate-600">
                  {selected.auditScope || "No indicative scope recorded."}
                </p>
              </div>
              <div>
                <h3 className="text-sm font-bold text-slate-800">
                  Materiality and exposure
                </h3>
                <p className="mt-2 text-sm leading-6 text-slate-600">
                  {selected.materialityExposure ||
                    "No exposure narrative recorded."}
                </p>
              </div>
            </section>

            <section>
              <h3 className="text-sm font-bold text-slate-800">
                Stakeholder offices ({selected.stakeholderOffices.length})
              </h3>
              <div className="mt-2 grid gap-2 sm:grid-cols-2">
                {selected.stakeholderOffices.map((office) => (
                  <div
                    className="rounded-lg border border-slate-200 px-3 py-2 text-sm"
                    key={office.id}
                  >
                    <strong className="text-sky-700">{office.code}</strong>
                    <span className="ml-2 text-slate-600">{office.name}</span>
                  </div>
                ))}
                {selected.stakeholderOffices.length === 0 && (
                  <p className="text-sm text-slate-500">
                    No additional stakeholder offices assigned.
                  </p>
                )}
              </div>
            </section>

            <section>
              <div className="flex flex-wrap items-end justify-between gap-2">
                <div>
                  <h3 className="text-sm font-bold text-slate-800">
                    Historical audits ({selected.auditHistory.length})
                  </h3>
                  <p className="mt-1 text-xs text-slate-500">
                    Last audit: {formatDate(selected.lastAuditDate)}
                  </p>
                </div>
              </div>
              <div className="mt-3 grid gap-3">
                {selected.auditHistory.map((history) => (
                  <article
                    className="rounded-xl border border-slate-200 p-4"
                    key={history.id}
                  >
                    <div className="flex flex-wrap justify-between gap-2">
                      <strong className="text-sm text-slate-800">
                        {history.title}
                      </strong>
                      <span className="text-xs font-semibold text-slate-500">
                        {formatDate(history.auditedOn)}
                      </span>
                    </div>
                    <p className="mt-2 text-xs text-slate-600">
                      {history.engagementReference || "No engagement reference"}
                      {history.reportReference
                        ? ` · Report ${history.reportReference}`
                        : ""}
                    </p>
                    {history.outcome && (
                      <p className="mt-2 text-sm font-semibold text-slate-700">
                        {history.outcome}
                      </p>
                    )}
                  </article>
                ))}
                {selected.auditHistory.length === 0 && (
                  <div className="rounded-xl border border-dashed border-amber-300 bg-amber-50 p-4 text-sm text-amber-800">
                    No linked historical audit has been recorded.
                  </div>
                )}
              </div>
            </section>

            {selected.historicalAuditSummary && (
              <section className="rounded-xl bg-slate-50 p-4">
                <h3 className="text-sm font-bold text-slate-800">
                  Historical context
                </h3>
                <p className="mt-2 text-sm leading-6 text-slate-600">
                  {selected.historicalAuditSummary}
                </p>
              </section>
            )}
          </div>
        )}
      </Modal>

      <ConfirmDialog
        busy={saving}
        confirmLabel="Archive subject"
        description={`${archiveTarget?.subjectCode ?? "This subject"} will be removed from the active Audit Universe but remain recoverable with its history.`}
        onCancel={() => setArchiveTarget(null)}
        onConfirm={archiveItem}
        open={Boolean(archiveTarget)}
        title="Archive auditable subject?"
        tone="danger"
      />
      <ConfirmDialog
        busy={saving}
        confirmLabel="Restore subject"
        description={`${restoreTarget?.subjectCode ?? "This subject"} will return to the active Audit Universe.`}
        onCancel={() => setRestoreTarget(null)}
        onConfirm={restoreItem}
        open={Boolean(restoreTarget)}
        title="Restore auditable subject?"
      />
    </main>
  );
}
