import { useEffect, useMemo, useState } from "react";
import {
  Archive,
  Building2,
  BuildingIcon,
  CircleOff,
  History,
  Pencil,
  Plus,
  RefreshCcw,
  RotateCcw,
  Search,
  ShieldCheck,
  Users,
  X,
} from "lucide-react";
import { useAuth } from "../../auth/auth-context";
import DataTable from "../../components/ui/DataTable";
import ConfirmDialog from "../../components/ui/ConfirmDialog";
import FormField from "../../components/ui/FormField";
import Modal from "../../components/ui/Modal";
import SearchableSelect from "../../components/ui/SearchableSelect";
import StatusBadge from "../../components/ui/StatusBadge";
import SummaryCard from "../../components/ui/SummaryCard";
import { hasPermission } from "../../config/navigation";
import {
  ApiError,
  auditAreaApi,
  demoApi,
  masterListApi,
  officeApi,
} from "../../services/api";
import { useToast } from "../../ui/toast-context";
import useRecordView from "../../hooks/useRecordView";

const emptyForm = {
  code: "",
  name: "",
  acronym: "",
  officeTypeId: "",
  headUserId: "",
  sector: "",
  contactNumber: "",
  description: "",
  auditAreaIds: [],
  isActive: true,
};

const inputClassName =
  "h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-sky-500 focus:ring-2 focus:ring-sky-100 disabled:bg-slate-100";

function firstError(errors, field) {
  const error = errors?.[field];
  return Array.isArray(error) ? error[0] : error;
}

export default function OfficeRegistryPage() {
  const { user } = useAuth();
  const toast = useToast();
  const [offices, setOffices] = useState([]);
  const [auditAreas, setAuditAreas] = useState([]);
  const [sectors, setSectors] = useState([]);
  const [officeTypes, setOfficeTypes] = useState([]);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [search, setSearch] = useState("");
  const [sectorFilter, setSectorFilter] = useState("");
  const [typeFilter, setTypeFilter] = useState("");
  const [statusFilter, setStatusFilter] = useState("");
  const [selectedOffice, setSelectedOffice] = useState(null);
  useRecordView(selectedOffice, {
    module: "CORE",
    recordType: "OFFICE",
    code: (record) => record.code,
    label: (record) => record.name,
  });
  const [editorOpen, setEditorOpen] = useState(false);
  const [editingOffice, setEditingOffice] = useState(null);
  const [deleteTarget, setDeleteTarget] = useState(null);
  const [restoreTarget, setRestoreTarget] = useState(null);
  const [saveConfirmOpen, setSaveConfirmOpen] = useState(false);
  const [resetOpen, setResetOpen] = useState(false);
  const [form, setForm] = useState(emptyForm);
  const [errors, setErrors] = useState({});

  const canCreate = hasPermission(user, "offices.create");
  const canUpdate = hasPermission(user, "offices.update");
  const canDelete = hasPermission(user, "offices.delete");
  const canRestore = hasPermission(user, "offices.restore");
  const canReset = hasPermission(user, "system_configuration.manage");

  useEffect(() => {
    let active = true;

    Promise.all([
      officeApi.list({ includeArchived: true }),
      auditAreaApi.list(),
      masterListApi.list(),
    ])
      .then(([officeRecords, areaRecords, masterLists]) => {
        if (active) {
          setOffices(officeRecords);
          setAuditAreas(areaRecords);
          setSectors(
            masterLists.find((list) => list.code === "OFFICE_SECTOR")?.items ??
              [],
          );
          setOfficeTypes(
            masterLists.find((list) => list.code === "OFFICE_TYPE")?.items ??
              [],
          );
        }
      })
      .catch((error) => {
        if (active) {
          toast.error(
            error instanceof Error
              ? error.message
              : "Offices could not be loaded.",
          );
        }
      })
      .finally(() => {
        if (active) setLoading(false);
      });

    return () => {
      active = false;
    };
  }, [toast]);

  const filteredOffices = useMemo(() => {
    const query = search.trim().toLowerCase();
    return offices.filter(
      (office) =>
        (!query ||
          [
            office.code,
            office.name,
            office.acronym,
            office.officeType?.label,
            office.description,
            office.sector,
            office.headName,
            office.contactNumber,
            ...(office.users ?? []).flatMap((officeUser) => [
              officeUser.name,
              officeUser.employeeId,
              officeUser.position,
              officeUser.role,
            ]),
          ].some((value) => value?.toLowerCase().includes(query))) &&
        (!typeFilter || String(office.officeTypeId) === String(typeFilter)) &&
        (!sectorFilter || office.sector === sectorFilter) &&
        (!statusFilter ||
          (statusFilter === "archived"
            ? office.isArchived
            : statusFilter === "active"
              ? office.isActive && !office.isArchived
              : !office.isActive && !office.isArchived)),
    );
  }, [offices, search, sectorFilter, statusFilter, typeFilter]);

  const officeStats = useMemo(() => {
    const active = offices.filter(
      (office) => office.isActive && !office.isArchived,
    ).length;
    const archived = offices.filter((office) => office.isArchived).length;
    const inactive = offices.length - active - archived;

    return { total: offices.length, active, inactive, archived };
  }, [offices]);

  const hasActiveFilters = Boolean(
    search || sectorFilter || statusFilter || typeFilter,
  );

  const sectorOptions = useMemo(
    () =>
      (sectors.length
        ? sectors
            .filter((sector) => sector.isActive)
            .map((sector) => sector.label)
        : [...new Set(offices.map((office) => office.sector).filter(Boolean))]
      )
        .sort()
        .map((sector) => ({ value: sector, label: sector })),
    [offices, sectors],
  );
  const auditAreaOptions = useMemo(
    () =>
      auditAreas.map((area) => ({
        value: area.id,
        label: `${area.code} — ${area.name}`,
        keywords: area.description,
      })),
    [auditAreas],
  );
  const officeTypeOptions = useMemo(
    () =>
      officeTypes
        .filter((type) => type.isActive)
        .map((type) => ({
          value: type.id,
          label: type.label,
          keywords: `${type.code} ${type.description ?? ""}`,
        })),
    [officeTypes],
  );
  const officeHeadOptions = useMemo(
    () =>
      (editingOffice?.users ?? [])
        .filter((candidate) => candidate.isActive)
        .map((candidate) => ({
          value: candidate.id,
          label: candidate.name,
          keywords: `${candidate.employeeId ?? ""} ${candidate.position ?? ""} ${candidate.role ?? ""}`,
        })),
    [editingOffice],
  );

  function openCreate() {
    setEditingOffice(null);
    setForm(emptyForm);
    setErrors({});
    setEditorOpen(true);
  }

  function openEdit(office) {
    setEditingOffice(office);
    setForm({
      code: office.code,
      name: office.name,
      acronym: office.acronym ?? "",
      officeTypeId: office.officeTypeId ?? "",
      headUserId: office.headId ?? "",
      sector: office.sector ?? "",
      contactNumber: office.contactNumber ?? "",
      description: office.description ?? "",
      auditAreaIds: office.auditAreas?.map((area) => area.id) ?? [],
      isActive: office.isActive,
    });
    setErrors({});
    setEditorOpen(true);
  }

  function updateField(event) {
    const { name, type, checked, value } = event.target;
    setForm((current) => ({
      ...current,
      [name]: type === "checkbox" ? checked : value,
    }));
    setErrors((current) => ({ ...current, [name]: undefined }));
  }

  function submitOffice(event) {
    event.preventDefault();
    if (!form.officeTypeId) {
      setErrors((current) => ({
        ...current,
        officeTypeId: ["Select an office type."],
      }));
      return;
    }
    setSaveConfirmOpen(true);
  }

  async function saveOffice() {
    setSubmitting(true);
    setErrors({});

    try {
      const saved = editingOffice
        ? await officeApi.update(editingOffice.id, form)
        : await officeApi.create(form);

      setOffices((current) => {
        if (!editingOffice) return [...current, saved];
        return current.map((office) =>
          office.id === saved.id ? saved : office,
        );
      });
      setSaveConfirmOpen(false);
      setEditorOpen(false);
      toast.success(
        editingOffice
          ? "Office updated successfully."
          : "Office created successfully.",
      );
    } catch (error) {
      if (error instanceof ApiError && error.status === 422) {
        setErrors(error.errors);
      }
      toast.error(
        error instanceof Error
          ? error.message
          : "The office could not be saved.",
      );
    } finally {
      setSubmitting(false);
    }
  }

  async function archiveOffice() {
    if (!deleteTarget) return;
    setSubmitting(true);

    try {
      await officeApi.remove(deleteTarget.id);
      setOffices((current) =>
        current.map((office) =>
          office.id === deleteTarget.id
            ? { ...office, isActive: false, isArchived: true }
            : office,
        ),
      );
      setDeleteTarget(null);
      toast.success("Office archived successfully.");
    } catch (error) {
      toast.error(
        error instanceof Error
          ? error.message
          : "The office could not be archived.",
      );
    } finally {
      setSubmitting(false);
    }
  }

  async function restoreOffice() {
    if (!restoreTarget) return;
    setSubmitting(true);

    try {
      const restored = await officeApi.restore(restoreTarget.id);
      setOffices((current) =>
        current.map((office) =>
          office.id === restored.id ? restored : office,
        ),
      );
      setRestoreTarget(null);
      toast.success("Office restored successfully.");
    } catch (error) {
      toast.error(
        error instanceof Error
          ? error.message
          : "The office could not be restored.",
      );
    } finally {
      setSubmitting(false);
    }
  }

  async function resetDemoData() {
    setSubmitting(true);

    try {
      const data = await demoApi.reset();
      setOffices(Array.isArray(data?.offices) ? data.offices : []);
      setResetOpen(false);
      toast.success(
        "Demo offices, roles, permissions, and account credentials were restored.",
      );
    } catch (error) {
      toast.error(
        error instanceof Error
          ? error.message
          : "Demo data could not be restored.",
      );
    } finally {
      setSubmitting(false);
    }
  }

  const columns = [
    {
      key: "name",
      label: "Office",
      render: (office) => (
        <div className="flex min-w-72 items-center gap-3 py-0.5">
          <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-sky-100 text-sky-700 ring-1 ring-sky-200">
            <Building2 size={18} />
          </span>
          <span className="min-w-0">
            <strong className="block truncate text-sm font-bold text-slate-800">
              {office.name}
            </strong>
            <span className="mt-0.5 block truncate text-xs text-slate-500">
              {office.code}
              {office.acronym ? ` · ${office.acronym}` : ""}
            </span>
          </span>
        </div>
      ),
    },
    {
      key: "officeType",
      label: "Type",
      sortValue: (office) => office.officeType?.label ?? "",
      render: (office) => (
        <span className="inline-flex rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700">
          {office.officeType?.label || "Not classified"}
        </span>
      ),
    },
    {
      key: "sector",
      label: "Sector",
      render: (office) => (
        <span className="inline-flex max-w-48 items-center rounded-full border border-sky-200 bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700">
          <span className="truncate">{office.sector || "Unclassified"}</span>
        </span>
      ),
    },
    {
      key: "headName",
      label: "Office Head",
      render: (office) => (
        <div className="min-w-44">
          <span className="block text-sm font-semibold text-slate-700">
            {office.headName || "Not assigned"}
          </span>
          {office.headName && (
            <span className="mt-1 inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700">
              <ShieldCheck size={12} /> Assigned head
            </span>
          )}
        </div>
      ),
    },
    {
      key: "contactNumber",
      label: "Contact",
      render: (office) => office.contactNumber || "—",
    },
    {
      key: "auditAreas",
      label: "Audit Areas",
      sortValue: (office) => office.auditAreas?.length ?? 0,
      render: (office) => (
        <span className="inline-flex items-center rounded-full border border-violet-200 bg-violet-50 px-2.5 py-1 text-xs font-semibold text-violet-700">
          {office.auditAreas?.length ?? 0} linked
        </span>
      ),
    },
    {
      key: "status",
      label: "Status",
      render: (office) => (
        <StatusBadge
          tone={
            office.isArchived
              ? "inactive"
              : office.isActive
                ? "active"
                : "warning"
          }
        >
          {office.isArchived
            ? "Archived"
            : office.isActive
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
      headerClassName: "text-right",
      className: "text-right",
      render: (office) => (
        <div className="flex justify-end gap-1">
          {canUpdate && !office.isArchived && (
            <button
              aria-label={`Edit ${office.name}`}
              className="grid h-9 w-9 place-items-center rounded-lg border border-slate-200 bg-white text-sky-700 shadow-sm transition hover:border-sky-200 hover:bg-sky-50"
              onClick={(event) => {
                event.stopPropagation();
                openEdit(office);
              }}
              title="Edit office"
              type="button"
            >
              <Pencil size={17} />
            </button>
          )}
          {canDelete && !office.isArchived && (
            <button
              aria-label={`Archive ${office.name}`}
              className="grid h-9 w-9 place-items-center rounded-lg border border-slate-200 bg-white text-red-600 shadow-sm transition hover:border-red-200 hover:bg-red-50"
              onClick={(event) => {
                event.stopPropagation();
                setDeleteTarget(office);
              }}
              title="Archive office"
              type="button"
            >
              <Archive size={17} />
            </button>
          )}
          {canRestore && office.isArchived && (
            <button
              aria-label={`Restore ${office.name}`}
              className="grid h-9 w-9 place-items-center rounded-lg border border-slate-200 bg-white text-emerald-700 shadow-sm transition hover:border-emerald-200 hover:bg-emerald-50"
              onClick={(event) => {
                event.stopPropagation();
                setRestoreTarget(office);
              }}
              title="Restore office"
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
    <div className="min-h-full bg-sky-50/40 p-4 sm:p-5">
      <section className="mb-5 flex flex-wrap items-start justify-between gap-4">
        <div>
          <div className="flex items-center gap-3">
            <span className="grid h-11 w-11 place-items-center rounded-xl bg-sky-100 text-sky-700 ring-1 ring-sky-200">
              <Building2 size={22} />
            </span>
            <div>
              <h2 className="text-xl font-bold text-slate-800">
                Office Registry
              </h2>
              <p className="text-sm text-slate-500">
                Maintain independent offices, assigned heads, users, and audit
                coverage. Offices do not have parent offices in AGIS.
              </p>
            </div>
          </div>
          {!canCreate && (
            <p className="mt-3 inline-flex rounded-lg bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800 ring-1 ring-amber-200">
              Your role has view-only access to this registry.
            </p>
          )}
        </div>

        <div className="flex flex-wrap gap-2">
          {canReset && (
            <button
              className="flex h-11 items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700 shadow-sm transition hover:bg-slate-50"
              onClick={() => setResetOpen(true)}
              type="button"
            >
              <RefreshCcw size={17} /> Reset demo data
            </button>
          )}
          {canCreate && (
            <button
              className="flex h-11 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white shadow-sm transition hover:bg-sky-800 focus:outline-none focus:ring-2 focus:ring-sky-300"
              onClick={openCreate}
              type="button"
            >
              <Plus size={18} /> Add office
            </button>
          )}
        </div>
      </section>

      <section className="mb-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <SummaryCard
          icon={BuildingIcon}
          label="Total offices"
          tone="sky"
          value={officeStats.total}
        />
        <SummaryCard
          icon={ShieldCheck}
          label="Active offices"
          tone="emerald"
          value={officeStats.active}
        />
        <SummaryCard
          icon={CircleOff}
          label="Inactive offices"
          tone="amber"
          value={officeStats.inactive}
        />
        <SummaryCard
          icon={Archive}
          label="Archived offices"
          tone="red"
          value={officeStats.archived}
        />
      </section>

      <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <header className="border-b border-slate-200 bg-white p-4">
          <div className="grid w-full gap-2 lg:grid-cols-[minmax(16rem,1fr)_13rem_15rem_11rem_auto]">
            <label className="flex h-11 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-slate-500 transition focus-within:border-sky-500 focus-within:ring-2 focus-within:ring-sky-100">
              <Search size={17} className="shrink-0" />

              <input
                className="min-w-0 flex-1 bg-transparent text-sm text-slate-800 outline-none placeholder:text-slate-400"
                onChange={(event) => setSearch(event.target.value)}
                placeholder="Search code, office, head..."
                type="search"
                value={search}
              />
            </label>

            <SearchableSelect
              onChange={setTypeFilter}
              options={[
                { value: "", label: "All office types" },
                ...officeTypeOptions,
              ]}
              placeholder="Filter by type"
              searchPlaceholder="Search office types..."
              value={typeFilter}
            />

            <SearchableSelect
              onChange={setSectorFilter}
              options={[{ value: "", label: "All sectors" }, ...sectorOptions]}
              placeholder="Filter by sector"
              searchPlaceholder="Search sectors..."
              value={sectorFilter}
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
                setTypeFilter("");
                setSectorFilter("");
                setStatusFilter("");
              }}
              type="button"
            >
              <X size={16} />
              Clear filters
            </button>
          </div>
        </header>

        <DataTable
          columns={columns}
          emptyMessage="No offices match your search."
          loading={loading}
          onRowClick={setSelectedOffice}
          pageSizeOptions={[8, 10, 25, 50]}
          rows={filteredOffices}
        />
      </section>

      <Modal
        footer={
          <>
            <button
              className="h-10 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700 transition hover:bg-slate-100"
              disabled={submitting}
              onClick={() => setEditorOpen(false)}
              type="button"
            >
              Cancel
            </button>
            <button
              className="h-10 rounded-lg bg-sky-700 px-5 text-sm font-bold text-white transition hover:bg-sky-800 disabled:cursor-not-allowed disabled:opacity-60"
              disabled={submitting}
              form="office-form"
              type="submit"
            >
              {submitting
                ? "Saving..."
                : editingOffice
                  ? "Save changes"
                  : "Create office"}
            </button>
          </>
        }
        onClose={() => !submitting && setEditorOpen(false)}
        open={editorOpen}
        title={editingOffice ? "Edit office" : "Add office"}
      >
        <form className="grid gap-4" id="office-form" onSubmit={submitOffice}>
          <div className="grid gap-4 sm:grid-cols-2">
            <FormField
              error={firstError(errors, "code")}
              htmlFor="office-code"
              label="Office code"
              required
            >
              <input
                className={inputClassName}
                id="office-code"
                maxLength={30}
                name="code"
                onChange={updateField}
                placeholder="e.g. CIO"
                value={form.code}
              />
            </FormField>
            <FormField
              error={firstError(errors, "acronym")}
              htmlFor="office-acronym"
              label="Acronym"
            >
              <input
                className={inputClassName}
                id="office-acronym"
                maxLength={30}
                name="acronym"
                onChange={updateField}
                placeholder="e.g. CIO"
                value={form.acronym}
              />
            </FormField>
          </div>
          <div className="grid gap-4 sm:grid-cols-2">
            <FormField
              error={firstError(errors, "officeTypeId")}
              label="Office type"
              required
            >
              <SearchableSelect
                onChange={(officeTypeId) =>
                  setForm((current) => ({ ...current, officeTypeId }))
                }
                options={officeTypeOptions}
                placeholder="Select an office type"
                searchPlaceholder="Search office types..."
                value={form.officeTypeId}
              />
            </FormField>
            <FormField
              error={firstError(errors, "headUserId")}
              label="Office head"
              hint={
                editingOffice
                  ? "Only active users already assigned to this office can be selected."
                  : "Create the office first, assign users to it, then select its office head."
              }
            >
              <SearchableSelect
                disabled={!editingOffice}
                onChange={(headUserId) =>
                  setForm((current) => ({ ...current, headUserId }))
                }
                options={[
                  { value: "", label: "No assigned office head" },
                  ...officeHeadOptions,
                ]}
                placeholder="Select an office head"
                searchPlaceholder="Search office users..."
                value={form.headUserId}
              />
            </FormField>
          </div>
          <FormField
            error={firstError(errors, "name")}
            htmlFor="office-name"
            label="Office name"
            required
          >
            <input
              className={inputClassName}
              id="office-name"
              maxLength={255}
              name="name"
              onChange={updateField}
              placeholder="Enter the complete office name"
              value={form.name}
            />
          </FormField>
          <FormField error={firstError(errors, "sector")} label="Sector">
            <SearchableSelect
              onChange={(sector) =>
                setForm((current) => ({ ...current, sector }))
              }
              options={sectorOptions}
              placeholder="Select an office sector"
              searchPlaceholder="Search office sectors..."
              value={form.sector}
            />
          </FormField>
          <FormField
            error={firstError(errors, "contactNumber")}
            htmlFor="office-contact"
            label="Contact number"
          >
            <input
              className={inputClassName}
              id="office-contact"
              maxLength={255}
              name="contactNumber"
              onChange={updateField}
              placeholder="Office contact details"
              value={form.contactNumber}
            />
          </FormField>
          <FormField
            error={firstError(errors, "auditAreaIds")}
            label="Linked audit areas"
            hint="An office may be covered by multiple audit areas."
          >
            <SearchableSelect
              multiple
              multipleDisplay="summary"
              onChange={(auditAreaIds) =>
                setForm((current) => ({ ...current, auditAreaIds }))
              }
              options={auditAreaOptions}
              placeholder="Select audit areas"
              searchPlaceholder="Search audit areas..."
              value={form.auditAreaIds}
            />
            {form.auditAreaIds.length > 0 && (
              <div className="mt-2 max-h-52 space-y-2 overflow-y-auto rounded-xl border border-slate-200 bg-slate-50 p-2">
                {form.auditAreaIds.map((areaId) => {
                  const area = auditAreas.find(
                    (candidate) => String(candidate.id) === String(areaId),
                  );
                  if (!area) return null;

                  return (
                    <div
                      className="flex items-start gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2.5 shadow-sm"
                      key={area.id}
                    >
                      <span className="min-w-0 flex-1">
                        <strong className="block text-sm text-sky-800">
                          {area.code}
                        </strong>
                        <span className="block text-xs leading-5 text-slate-600">
                          {area.name}
                        </span>
                      </span>
                      <button
                        aria-label={`Remove ${area.name}`}
                        className="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-slate-500 transition hover:bg-red-50 hover:text-red-600"
                        onClick={() =>
                          setForm((current) => ({
                            ...current,
                            auditAreaIds: current.auditAreaIds.filter(
                              (selectedId) =>
                                String(selectedId) !== String(area.id),
                            ),
                          }))
                        }
                        type="button"
                      >
                        <X size={16} />
                      </button>
                    </div>
                  );
                })}
              </div>
            )}
          </FormField>
          <FormField
            error={firstError(errors, "description")}
            htmlFor="office-description"
            label="Description"
          >
            <textarea
              className={`${inputClassName} min-h-28 resize-y py-3`}
              id="office-description"
              maxLength={1000}
              name="description"
              onChange={updateField}
              placeholder="Describe this office's purpose"
              value={form.description}
            />
          </FormField>
          <label className="flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-3 text-sm font-semibold text-slate-700">
            <input
              checked={form.isActive}
              className="h-4 w-4 accent-sky-700"
              name="isActive"
              onChange={updateField}
              type="checkbox"
            />
            Active office
          </label>
        </form>
      </Modal>

      <Modal
        onClose={() => setSelectedOffice(null)}
        open={Boolean(selectedOffice)}
        size="lg"
        title={selectedOffice?.name ?? "Office details"}
      >
        {selectedOffice && (
          <div className="grid gap-5">
            <div className="grid gap-3 rounded-xl bg-slate-50 p-4 sm:grid-cols-2">
              {[
                ["Office code", selectedOffice.code],
                ["Acronym", selectedOffice.acronym || "—"],
                [
                  "Office type",
                  selectedOffice.officeType?.label || "Not classified",
                ],
                ["Sector", selectedOffice.sector || "—"],
                ["Office head", selectedOffice.headName || "Not assigned"],
                ["Contact", selectedOffice.contactNumber || "—"],
                ["Assigned users", selectedOffice.usersCount ?? 0],
                [
                  "Status",
                  selectedOffice.isArchived
                    ? "Archived"
                    : selectedOffice.isActive
                      ? "Active"
                      : "Inactive",
                ],
              ].map(([label, value]) => (
                <div key={label}>
                  <span className="block text-[11px] font-bold uppercase tracking-wide text-slate-400">
                    {label}
                  </span>
                  <strong className="mt-1 block text-sm text-slate-700">
                    {value}
                  </strong>
                </div>
              ))}
            </div>
            <div>
              <h3 className="flex items-center gap-2 text-sm font-bold text-slate-800">
                <Users size={16} className="text-sky-700" />
                Office users ({selectedOffice.users?.length ?? 0})
              </h3>
              <div className="mt-2 grid gap-2 sm:grid-cols-2">
                {selectedOffice.users?.map((officeUser) => (
                  <article
                    className="rounded-lg border border-slate-200 p-3"
                    key={officeUser.id}
                  >
                    <div className="flex items-start justify-between gap-2">
                      <strong className="text-sm text-slate-800">
                        {officeUser.name}
                      </strong>
                      {officeUser.isOfficeHead && (
                        <StatusBadge tone="active">Office head</StatusBadge>
                      )}
                    </div>
                    <p className="mt-1 text-xs leading-5 text-slate-500">
                      {officeUser.employeeId || "No employee ID"} ·{" "}
                      {officeUser.position || officeUser.role || "No position"}
                    </p>
                  </article>
                ))}
                {!selectedOffice.users?.length && (
                  <p className="text-sm text-slate-500">
                    No active users belong to this office.
                  </p>
                )}
              </div>
            </div>
            <div>
              <h3 className="text-sm font-bold text-slate-800">
                Office responsibility
              </h3>
              <p className="mt-2 text-sm leading-6 text-slate-600">
                {selectedOffice.description || "No description provided."}
              </p>
            </div>
            <div>
              <h3 className="text-sm font-bold text-slate-800">
                Linked audit areas ({selectedOffice.auditAreas?.length ?? 0})
              </h3>
              <div className="mt-2 grid gap-2 sm:grid-cols-2">
                {selectedOffice.auditAreas?.map((area) => (
                  <article
                    className="rounded-lg border border-slate-200 p-3"
                    key={area.id}
                  >
                    <strong className="text-sm text-sky-700">
                      {area.code} — {area.name}
                    </strong>
                    <p className="mt-1 line-clamp-3 text-xs leading-5 text-slate-500">
                      {area.description}
                    </p>
                  </article>
                ))}
                {!selectedOffice.auditAreas?.length && (
                  <p className="text-sm text-slate-500">
                    No audit areas are linked to this office.
                  </p>
                )}
              </div>
            </div>
            <div>
              <h3 className="flex items-center gap-2 text-sm font-bold text-slate-800">
                <History size={16} className="text-sky-700" />
                Office history
              </h3>
              <div className="mt-2 space-y-2">
                {selectedOffice.history?.map((entry) => (
                  <article
                    className="rounded-lg border border-slate-200 bg-slate-50 p-3"
                    key={entry.id}
                  >
                    <strong className="block text-sm text-slate-700">
                      {entry.action.replaceAll("_", " ")}
                    </strong>
                    <span className="mt-1 block text-xs text-slate-500">
                      {entry.actor} ·{" "}
                      {entry.createdAt
                        ? new Date(entry.createdAt).toLocaleString()
                        : "Date unavailable"}
                    </span>
                  </article>
                ))}
                {!selectedOffice.history?.length && (
                  <p className="text-sm text-slate-500">
                    No recorded changes are available yet.
                  </p>
                )}
              </div>
            </div>
          </div>
        )}
      </Modal>

      <ConfirmDialog
        busy={submitting}
        confirmLabel={editingOffice ? "Update office" : "Add office"}
        description={
          editingOffice
            ? `Apply the changes to ${editingOffice.name}?`
            : `Add ${form.name || "this office"} to the Office Registry?`
        }
        onCancel={() => setSaveConfirmOpen(false)}
        onConfirm={saveOffice}
        open={saveConfirmOpen}
        title={editingOffice ? "Confirm office update" : "Confirm new office"}
      />

      <ConfirmDialog
        busy={submitting}
        confirmLabel="Archive office"
        description={`${deleteTarget?.name ?? "This office"} will be hidden from active selections. Its data remains recoverable.`}
        onCancel={() => setDeleteTarget(null)}
        onConfirm={archiveOffice}
        open={Boolean(deleteTarget)}
        title="Archive this office?"
        tone="danger"
      />

      <ConfirmDialog
        busy={submitting}
        confirmLabel="Restore office"
        description={`${restoreTarget?.name ?? "This office"} will return to active registry records.`}
        onCancel={() => setRestoreTarget(null)}
        onConfirm={restoreOffice}
        open={Boolean(restoreTarget)}
        title="Restore this office?"
        tone="primary"
      />

      <Modal
        description="Restore the original offices, role permissions, and the three demo accounts."
        footer={
          <>
            <button
              className="h-10 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700 transition hover:bg-slate-100"
              disabled={submitting}
              onClick={() => setResetOpen(false)}
              type="button"
            >
              Cancel
            </button>
            <button
              className="h-10 rounded-lg bg-amber-600 px-5 text-sm font-bold text-white transition hover:bg-amber-700 disabled:opacity-60"
              disabled={submitting}
              onClick={resetDemoData}
              type="button"
            >
              {submitting ? "Restoring..." : "Reset demo data"}
            </button>
          </>
        }
        onClose={() => !submitting && setResetOpen(false)}
        open={resetOpen}
        size="sm"
        title="Reset to demo data?"
      >
        <div className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm leading-6 text-amber-900">
          Added offices will be removed, seeded records will be restored, and
          every demo account password will be reset to <strong>lala</strong>.
        </div>
      </Modal>
    </div>
  );
}
