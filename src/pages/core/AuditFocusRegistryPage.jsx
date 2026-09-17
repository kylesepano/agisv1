import { useEffect, useMemo, useState } from "react";
import {
  Archive,
  CircleCheckBig,
  CirclePause,
  Pencil,
  Plus,
  RotateCcw,
  Search,
  Target,
  X,
} from "lucide-react";
import { useAuth } from "../../auth/auth-context";
import DataTable from "../../components/ui/DataTable";
import ConfirmDialog from "../../components/ui/ConfirmDialog";
import FormField from "../../components/ui/FormField";
import Modal from "../../components/ui/Modal";
import RegistryHeader from "../../components/ui/RegistryHeader";
import SearchableSelect from "../../components/ui/SearchableSelect";
import StatusBadge from "../../components/ui/StatusBadge";
import SummaryCard from "../../components/ui/SummaryCard";
import { hasPermission } from "../../config/navigation";
import { ApiError, auditAreaApi, auditFocusApi } from "../../services/api";
import { useToast } from "../../ui/toast-context";
import useRecordView from "../../hooks/useRecordView";

const emptyForm = {
  auditAreaId: "",
  code: "",
  name: "",
  description: "",
  displayOrder: 0,
  isActive: true,
};
const inputClass =
  "h-11 w-full rounded-lg border border-slate-300 px-3 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100";

/**
 * Maintains audit focuses, each of which belongs to exactly one audit area.
 * The page owns filtering, pagination, soft archival, and detail presentation.
 */
export default function AuditFocusRegistryPage() {
  const { user } = useAuth();
  const toast = useToast();
  const [focuses, setFocuses] = useState([]);
  const [areas, setAreas] = useState([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState("");
  const [areaFilter, setAreaFilter] = useState("");
  const [statusFilter, setStatusFilter] = useState("");
  const [editing, setEditing] = useState(null);
  const [form, setForm] = useState(emptyForm);
  const [open, setOpen] = useState(false);
  const [errors, setErrors] = useState({});
  const [saving, setSaving] = useState(false);
  const [saveConfirmOpen, setSaveConfirmOpen] = useState(false);
  const [archiveTarget, setArchiveTarget] = useState(null);
  const [restoreTarget, setRestoreTarget] = useState(null);
  const [selectedFocus, setSelectedFocus] = useState(null);
  useRecordView(selectedFocus, {
    module: "CORE",
    recordType: "AUDIT_FOCUS",
    code: (record) => record.code,
    label: (record) => record.name,
  });
  const canCreate = hasPermission(user, "audit_focus.create");
  const canUpdate = hasPermission(user, "audit_focus.update");
  const canDelete = hasPermission(user, "audit_focus.delete");
  const canRestore = hasPermission(user, "audit_focus.restore");

  useEffect(() => {
    let active = true;
    Promise.all([
      auditFocusApi.list({ includeArchived: true }),
      auditAreaApi.list(),
    ])
      .then(([focusRecords, areaRecords]) => {
        if (!active) return;
        setFocuses(focusRecords);
        setAreas(areaRecords);
      })
      .catch((error) => active && toast.error(error.message))
      .finally(() => active && setLoading(false));
    return () => {
      active = false;
    };
  }, [toast]);

  const filtered = useMemo(() => {
    const query = search.toLowerCase().trim();
    return focuses.filter(
      (focus) =>
        (!query ||
          [
            focus.code,
            focus.name,
            focus.auditAreaCode,
            focus.auditAreaName,
            focus.description,
          ].some((value) => value?.toLowerCase().includes(query))) &&
        (!areaFilter || String(focus.auditAreaId) === String(areaFilter)) &&
        (!statusFilter ||
          (statusFilter === "archived"
            ? focus.isArchived
            : statusFilter === "active"
              ? focus.isActive && !focus.isArchived
              : !focus.isActive && !focus.isArchived)),
    );
  }, [areaFilter, focuses, search, statusFilter]);

  const focusStats = useMemo(() => {
    const active = focuses.filter(
      (focus) => focus.isActive && !focus.isArchived,
    ).length;
    const archived = focuses.filter((focus) => focus.isArchived).length;
    const inactive = focuses.length - active - archived;

    return {
      total: focuses.length,
      active,
      inactive,
      archived,
    };
  }, [focuses]);

  const hasActiveFilters = Boolean(search || areaFilter || statusFilter);

  const areaOptions = useMemo(
    () =>
      areas.map((area) => ({
        value: area.id,
        label: `${area.code} — ${area.name}`,
        keywords: area.description,
      })),
    [areas],
  );

  function showEditor(focus = null) {
    setEditing(focus);
    setErrors({});
    setForm(
      focus
        ? {
            auditAreaId: focus.auditAreaId,
            code: focus.code,
            name: focus.name,
            description: focus.description ?? "",
            displayOrder: focus.displayOrder,
            isActive: focus.isActive,
          }
        : { ...emptyForm, auditAreaId: areas[0]?.id ?? "" },
    );
    setOpen(true);
  }

  function save(event) {
    event.preventDefault();
    setSaveConfirmOpen(true);
  }

  async function persistFocus() {
    setSaving(true);
    setErrors({});
    try {
      const payload = { ...form, auditAreaId: Number(form.auditAreaId) };
      if (editing) await auditFocusApi.update(editing.id, payload);
      else await auditFocusApi.create(payload);
      setFocuses(await auditFocusApi.list({ includeArchived: true }));
      setSaveConfirmOpen(false);
      setOpen(false);
      toast.success("Audit focus saved successfully.");
    } catch (error) {
      if (error instanceof ApiError && error.status === 422)
        setErrors(error.errors);
      toast.error(error.message);
    } finally {
      setSaving(false);
    }
  }

  async function archiveFocus() {
    if (!archiveTarget) return;
    setSaving(true);
    try {
      await auditFocusApi.remove(archiveTarget.id);
      setFocuses((current) =>
        current.map((item) =>
          item.id === archiveTarget.id
            ? { ...item, isActive: false, isArchived: true }
            : item,
        ),
      );
      setArchiveTarget(null);
      toast.success("Audit focus archived successfully.");
    } catch (error) {
      toast.error(error.message);
    } finally {
      setSaving(false);
    }
  }

  async function restoreFocus() {
    if (!restoreTarget) return;
    setSaving(true);
    try {
      const restored = await auditFocusApi.restore(restoreTarget.id);
      setFocuses((current) =>
        current.map((item) => (item.id === restored.id ? restored : item)),
      );
      setRestoreTarget(null);
      toast.success("Audit focus restored successfully.");
    } catch (error) {
      toast.error(error.message);
    } finally {
      setSaving(false);
    }
  }

  const columns = [
    {
      key: "code",
      label: "Code",
      render: (focus) => (
        <strong className="whitespace-nowrap text-slate-800">
          {focus.code}
        </strong>
      ),
    },
    {
      key: "name",
      label: "Audit Focus",
      render: (focus) => (
        <div className="min-w-72">
          <strong className="text-slate-800">{focus.name}</strong>
          <p className="mt-1 line-clamp-2 text-xs leading-5 text-slate-500">
            {focus.description}
          </p>
        </div>
      ),
    },
    { key: "auditAreaName", label: "Audit Area" },
    {
      key: "status",
      label: "Status",
      render: (focus) => (
        <StatusBadge
          tone={
            focus.isArchived
              ? "inactive"
              : focus.isActive
                ? "active"
                : "warning"
          }
        >
          {focus.isArchived
            ? "Archived"
            : focus.isActive
              ? "Active"
              : "Inactive"}
        </StatusBadge>
      ),
    },
  ];
  if (canUpdate || canDelete || canRestore) {
    columns.push({
      key: "actions",
      label: "Actions",
      className: "text-right",
      render: (focus) => (
        <div className="flex justify-end gap-1">
          {canUpdate && !focus.isArchived && (
            <button
              className="grid h-9 w-9 place-items-center rounded-lg text-blue-700 hover:bg-blue-100"
              onClick={(event) => {
                event.stopPropagation();
                showEditor(focus);
              }}
              type="button"
            >
              <Pencil size={17} />
            </button>
          )}
          {canDelete && !focus.isArchived && (
            <button
              className="grid h-9 w-9 place-items-center rounded-lg text-red-600 hover:bg-red-100"
              onClick={(event) => {
                event.stopPropagation();
                setArchiveTarget(focus);
              }}
              title="Archive audit focus"
              type="button"
            >
              <Archive size={17} />
            </button>
          )}
          {canRestore && focus.isArchived && (
            <button
              className="grid h-9 w-9 place-items-center rounded-lg text-emerald-700 hover:bg-emerald-100"
              onClick={(event) => {
                event.stopPropagation();
                setRestoreTarget(focus);
              }}
              title="Restore audit focus"
              type="button"
            >
              <RotateCcw size={17} />
            </button>
          )}
        </div>
      ),
    });
  }

  return (
    <div className="p-4 sm:p-6">
      <RegistryHeader
        actions={
          canCreate && (
            <button
              className="flex h-11 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white"
              onClick={() => showEditor()}
              type="button"
            >
              <Plus size={18} /> Add audit focus
            </button>
          )
        }
        description="Maintain focused audit subjects. Every focus belongs to exactly one audit area."
        icon={Target}
        readOnly={!canCreate && !canUpdate}
        title="Audit Focus Registry"
      />

      <section className="mb-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <SummaryCard
          icon={Target}
          label="Total audit focuses"
          tone="sky"
          value={focusStats.total}
        />
        <SummaryCard
          icon={CircleCheckBig}
          label="Active audit focuses"
          tone="emerald"
          value={focusStats.active}
        />
        <SummaryCard
          icon={CirclePause}
          label="Inactive audit focuses"
          tone="amber"
          value={focusStats.inactive}
        />
        <SummaryCard
          icon={Archive}
          label="Archived audit focuses"
          tone="red"
          value={focusStats.archived}
        />
      </section>

      <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <header className="border-b border-slate-200 bg-white p-4">
          <div className="grid w-full gap-2 md:grid-cols-[minmax(16rem,1fr)_16rem_11rem_auto]">
            <label className="flex h-11 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-slate-500 transition focus-within:border-sky-500 focus-within:ring-2 focus-within:ring-sky-100">
              <Search className="shrink-0" size={17} />
              <input
                className="min-w-0 flex-1 bg-transparent text-sm text-slate-800 outline-none placeholder:text-slate-400"
                onChange={(event) => setSearch(event.target.value)}
                placeholder="Search focus, code, audit area..."
                type="search"
                value={search}
              />
            </label>

            <SearchableSelect
              onChange={setAreaFilter}
              options={[
                { value: "", label: "All audit areas" },
                ...areaOptions,
              ]}
              placeholder="Filter by audit area"
              searchPlaceholder="Search audit areas..."
              value={areaFilter}
            />

            <SearchableSelect
              onChange={setStatusFilter}
              options={[
                { value: "", label: "All statuses" },
                { value: "active", label: "Active" },
                { value: "inactive", label: "Inactive" },
                { value: "archived", label: "Archived" },
              ]}
              placeholder="Filter by status"
              searchPlaceholder="Search statuses..."
              value={statusFilter}
            />

            <button
              className="flex h-11 items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-400"
              disabled={!hasActiveFilters}
              onClick={() => {
                setSearch("");
                setAreaFilter("");
                setStatusFilter("");
              }}
              type="button"
            >
              <X size={16} />
              Clear filters
            </button>
          </div>
        </header>

        <div className="[&_tbody_tr]:transition-colors [&_tbody_tr:hover]:bg-sky-50/60 [&_thead]:bg-slate-50/90">
          <DataTable
            columns={columns}
            emptyMessage="No audit focuses match your filters."
            key={`${search}|${areaFilter}|${statusFilter}`}
            loading={loading}
            onRowClick={setSelectedFocus}
            pageSizeOptions={[8, 10, 25, 50]}
            rows={filtered}
          />
        </div>
      </section>

      <Modal
        onClose={() => setSelectedFocus(null)}
        open={Boolean(selectedFocus)}
        size="lg"
        title={selectedFocus?.name ?? "Audit focus details"}
      >
        {selectedFocus && (
          <div className="grid gap-5">
            <div className="rounded-xl bg-slate-50 p-4">
              <div className="flex flex-wrap items-center gap-2">
                <StatusBadge>{selectedFocus.code}</StatusBadge>
                <StatusBadge
                  tone={
                    selectedFocus.isArchived
                      ? "inactive"
                      : selectedFocus.isActive
                        ? "active"
                        : "warning"
                  }
                >
                  {selectedFocus.isArchived
                    ? "Archived"
                    : selectedFocus.isActive
                      ? "Active"
                      : "Inactive"}
                </StatusBadge>
              </div>
              <p className="mt-3 text-sm leading-6 text-slate-600">
                {selectedFocus.description || "No description provided."}
              </p>
            </div>
            <div className="grid gap-3 sm:grid-cols-2">
              <div className="rounded-lg border border-slate-200 p-4">
                <span className="text-[11px] font-bold uppercase tracking-wide text-slate-400">
                  Parent audit area
                </span>
                <strong className="mt-1 block text-sm text-slate-700">
                  {selectedFocus.auditAreaCode} — {selectedFocus.auditAreaName}
                </strong>
              </div>
              <div className="rounded-lg border border-slate-200 p-4">
                <span className="text-[11px] font-bold uppercase tracking-wide text-slate-400">
                  Display order
                </span>
                <strong className="mt-1 block text-sm text-slate-700">
                  {selectedFocus.displayOrder}
                </strong>
              </div>
            </div>
          </div>
        )}
      </Modal>

      <Modal
        footer={
          <>
            <button
              className="h-10 rounded-lg border px-4 text-sm font-bold"
              onClick={() => setOpen(false)}
              type="button"
            >
              Cancel
            </button>
            <button
              className="h-10 rounded-lg bg-sky-700 px-5 text-sm font-bold text-white"
              form="focus-form"
              type="submit"
            >
              {saving ? "Saving..." : "Save focus"}
            </button>
          </>
        }
        onClose={() => !saving && setOpen(false)}
        open={open}
        title={editing ? "Edit audit focus" : "Add audit focus"}
      >
        <form className="grid gap-4" id="focus-form" onSubmit={save}>
          <FormField
            error={errors.auditAreaId?.[0]}
            htmlFor="focus-area"
            label="Audit area"
            required
          >
            <SearchableSelect
              onChange={(auditAreaId) => setForm({ ...form, auditAreaId })}
              options={areaOptions}
              placeholder="Select an audit area"
              searchPlaceholder="Search audit areas..."
              value={form.auditAreaId}
            />
          </FormField>
          <div className="grid gap-4 sm:grid-cols-2">
            <FormField
              error={errors.code?.[0]}
              htmlFor="focus-code"
              label="Code"
              required
            >
              <input
                className={inputClass}
                id="focus-code"
                onChange={(event) =>
                  setForm({ ...form, code: event.target.value })
                }
                value={form.code}
              />
            </FormField>
            <FormField
              error={errors.name?.[0]}
              htmlFor="focus-name"
              label="Name"
              required
            >
              <input
                className={inputClass}
                id="focus-name"
                onChange={(event) =>
                  setForm({ ...form, name: event.target.value })
                }
                value={form.name}
              />
            </FormField>
          </div>
          <FormField htmlFor="focus-description" label="Description">
            <textarea
              className={`${inputClass} min-h-28 py-3`}
              id="focus-description"
              onChange={(event) =>
                setForm({ ...form, description: event.target.value })
              }
              value={form.description}
            />
          </FormField>
          <label className="flex items-center gap-2 text-sm font-semibold">
            <input
              checked={form.isActive}
              onChange={(event) =>
                setForm({ ...form, isActive: event.target.checked })
              }
              type="checkbox"
            />
            Active audit focus
          </label>
        </form>
      </Modal>

      <ConfirmDialog
        busy={saving}
        confirmLabel={editing ? "Update audit focus" : "Add audit focus"}
        description={
          editing
            ? `Apply these changes to ${editing.name}?`
            : `Add ${form.name || "this audit focus"} to the registry?`
        }
        onCancel={() => setSaveConfirmOpen(false)}
        onConfirm={persistFocus}
        open={saveConfirmOpen}
        title={editing ? "Confirm audit focus update" : "Confirm audit focus"}
      />

      <ConfirmDialog
        busy={saving}
        confirmLabel="Archive audit focus"
        description={`${archiveTarget?.name ?? "This audit focus"} will be hidden from active selections but remains recoverable.`}
        onCancel={() => setArchiveTarget(null)}
        onConfirm={archiveFocus}
        open={Boolean(archiveTarget)}
        title="Archive audit focus?"
        tone="danger"
      />

      <ConfirmDialog
        busy={saving}
        confirmLabel="Restore audit focus"
        description={`${restoreTarget?.name ?? "This audit focus"} will return to the active registry.`}
        onCancel={() => setRestoreTarget(null)}
        onConfirm={restoreFocus}
        open={Boolean(restoreTarget)}
        title="Restore audit focus?"
      />
    </div>
  );
}
