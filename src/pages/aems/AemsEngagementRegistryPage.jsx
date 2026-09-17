import { useCallback, useEffect, useMemo, useState } from "react";
import {
  Archive,
  BadgeCheck,
  BriefcaseBusiness,
  ClipboardCheck,
  FileInput,
  LayoutDashboard,
  Plus,
  RotateCcw,
  Search,
  ShieldCheck,
  Sparkles,
  X,
} from "lucide-react";
import { useNavigate } from "react-router";
import { useAuth } from "../../auth/auth-context";
import AemsSpecialEngagementForm from "../../components/aems/AemsSpecialEngagementForm";
import ConfirmDialog from "../../components/ui/ConfirmDialog";
import DataTable from "../../components/ui/DataTable";
import Modal from "../../components/ui/Modal";
import RegistryHeader from "../../components/ui/RegistryHeader";
import SearchableSelect from "../../components/ui/SearchableSelect";
import StatusBadge from "../../components/ui/StatusBadge";
import SummaryCard from "../../components/ui/SummaryCard";
import { hasPermission } from "../../config/navigation";
import {
  aemsEngagementApi,
  ApiError,
  auditAreaApi,
  auditFocusApi,
  masterListApi,
  officeApi,
  userApi,
} from "../../services/api";
import { useToast } from "../../ui/toast-context";

const statusLabels = {
  DRAFT: "Draft",
  AUTHORIZATION_PREPARATION: "Authorization Preparation",
  RETURNED_FOR_REVISION: "Returned for Revision",
  AUTHORIZED: "Authorized",
  ENGAGEMENT_PLANNING: "Engagement Planning",
  ENTRY_CONFERENCE: "Entry Conference",
  FIELDWORK: "Fieldwork",
  FINDINGS_COMMUNICATION: "Findings Communication",
  EXIT_CONFERENCE: "Exit Conference",
  REPORTING: "Reporting",
  ISSUED: "Issued",
  CLOSURE_REVIEW: "Closure Review",
  COMPLETED: "Completed",
  CLOSED: "Closed",
  SUSPENDED: "Suspended",
  CANCELLED: "Cancelled",
};

const statusTones = {
  DRAFT: "inactive",
  AUTHORIZATION_PREPARATION: "warning",
  RETURNED_FOR_REVISION: "danger",
  AUTHORIZED: "success",
  ENGAGEMENT_PLANNING: "info",
  ENTRY_CONFERENCE: "warning",
  FIELDWORK: "info",
  FINDINGS_COMMUNICATION: "warning",
  EXIT_CONFERENCE: "warning",
  REPORTING: "warning",
  ISSUED: "success",
  CLOSURE_REVIEW: "warning",
  COMPLETED: "active",
  CLOSED: "active",
  SUSPENDED: "danger",
  CANCELLED: "danger",
};

const phaseLabels = {
  FOUNDATION: "Foundation",
  PLANNING: "Planning",
  EXECUTION: "Execution",
  ISSUES_AFR: "Issues & AFR",
  CONFERENCES: "Conferences",
  REPORTING: "Reporting",
  COMPLETION_TRANSFER: "Completion & Transfer",
  CLOSURE: "Closure",
};

const phaseByStatus = {
  DRAFT: "FOUNDATION",
  AUTHORIZATION_PREPARATION: "FOUNDATION",
  RETURNED_FOR_REVISION: "FOUNDATION",
  AUTHORIZED: "FOUNDATION",
  ENGAGEMENT_PLANNING: "PLANNING",
  ENTRY_CONFERENCE: "CONFERENCES",
  FIELDWORK: "EXECUTION",
  FINDINGS_COMMUNICATION: "ISSUES_AFR",
  EXIT_CONFERENCE: "CONFERENCES",
  REPORTING: "REPORTING",
  ISSUED: "REPORTING",
  CLOSURE_REVIEW: "COMPLETION_TRANSFER",
  COMPLETED: "COMPLETION_TRANSFER",
  CLOSED: "CLOSURE",
  SUSPENDED: "FOUNDATION",
  CANCELLED: "CLOSURE",
};

const administrativeLabels = {
  DRAFT: "Draft",
  ACTIVE: "Active",
  RETURNED: "Returned",
  ISSUED: "Issued",
  SUSPENDED: "Suspended",
  CANCELLED: "Cancelled",
  CLOSED: "Closed",
  ARCHIVED: "Archived",
};

function phaseFor(engagement) {
  return engagement.phase ?? phaseByStatus[engagement.status] ?? "FOUNDATION";
}

function administrativeStatusFor(engagement) {
  if (engagement.administrativeStatus) return engagement.administrativeStatus;
  if (engagement.isArchived) return "ARCHIVED";
  if (engagement.status === "DRAFT") return "DRAFT";
  if (engagement.status === "ISSUED") return "ISSUED";
  if (engagement.status === "CLOSED") return "CLOSED";
  if (engagement.status === "SUSPENDED") return "SUSPENDED";
  if (engagement.status === "CANCELLED") return "CANCELLED";
  if (engagement.status === "RETURNED_FOR_REVISION") return "RETURNED";
  return "ACTIVE";
}

function date(value) {
  if (!value) return "—";
  return new Intl.DateTimeFormat("en-PH", {
    month: "short",
    day: "numeric",
    year: "numeric",
  }).format(new Date(`${value}T00:00:00`));
}

/**
 * Functional AEMS entry point for approved-IAP imports and special/unplanned
 * engagement drafts. Detail views use their own route instead of oversized
 * modals.
 */
export default function AemsEngagementRegistryPage() {
  const { user } = useAuth();
  const toast = useToast();
  const navigate = useNavigate();
  const [engagements, setEngagements] = useState([]);
  const [summary, setSummary] = useState({});
  const [importOptions, setImportOptions] = useState([]);
  const [offices, setOffices] = useState([]);
  const [auditAreas, setAuditAreas] = useState([]);
  const [auditFocuses, setAuditFocuses] = useState([]);
  const [users, setUsers] = useState([]);
  const [masterLists, setMasterLists] = useState([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [loadError, setLoadError] = useState("");
  const [errors, setErrors] = useState({});
  const [search, setSearch] = useState("");
  const [sourceFilter, setSourceFilter] = useState("");
  const [statusFilter, setStatusFilter] = useState("");
  const [phaseFilter, setPhaseFilter] = useState("");
  const [administrativeFilter, setAdministrativeFilter] = useState("");
  const [officeFilter, setOfficeFilter] = useState("");
  const [createOpen, setCreateOpen] = useState(false);
  const [createMode, setCreateMode] = useState("iap");
  const [selectedImportId, setSelectedImportId] = useState("");
  const [archiveTarget, setArchiveTarget] = useState(null);
  const [restoreTarget, setRestoreTarget] = useState(null);

  const canCreate = hasPermission(user, "aems.engagement.create");
  const canArchive = hasPermission(user, "aems.engagement.archive");
  const canRestore = hasPermission(user, "aems.engagement.restore");
  const includeArchived = canArchive || canRestore;

  const load = useCallback(async () => {
    setLoading(true);
    setLoadError("");
    try {
      const registry = await aemsEngagementApi.list({
        includeArchived,
        perPage: 100,
        sortBy: "updated_at",
        sortDirection: "desc",
      });
      setEngagements(registry.engagements);
      setSummary(registry.summary);

      if (canCreate) {
        const [sources, officeRecords, areas, focuses, people, lists] =
          await Promise.all([
            aemsEngagementApi.importOptions(),
            officeApi.list(),
            auditAreaApi.list(),
            auditFocusApi.list(),
            userApi.list(),
            masterListApi.list(),
          ]);
        setImportOptions(sources);
        setOffices(officeRecords);
        setAuditAreas(areas);
        setAuditFocuses(focuses);
        setUsers(people);
        setMasterLists(lists);
      }
    } catch (error) {
      setLoadError(
        error instanceof Error
          ? error.message
          : "Unable to load the Audit Engagement Workspace.",
      );
    } finally {
      setLoading(false);
    }
  }, [canCreate, includeArchived]);

  useEffect(() => {
    const timer = window.setTimeout(load, 0);
    return () => window.clearTimeout(timer);
  }, [load]);

  const selectedImport = importOptions.find(
    (option) => String(option.id) === String(selectedImportId),
  );
  const officeOptions = useMemo(() => {
    const map = new Map();
    engagements.forEach((engagement) =>
      engagement.offices.forEach((office) => map.set(office.id, office)),
    );
    return [...map.values()]
      .sort((left, right) => left.name.localeCompare(right.name))
      .map((office) => ({
        value: office.id,
        label: `${office.code} — ${office.name}`,
      }));
  }, [engagements]);
  const filtered = useMemo(() => {
    const query = search.trim().toLowerCase();
    return engagements.filter((engagement) => {
      const matchesSearch =
        !query ||
        [
          engagement.engagementCode,
          engagement.title,
          engagement.specialAuthorityReference,
          ...engagement.offices.flatMap((office) => [office.code, office.name]),
          ...engagement.auditAreas.flatMap((area) => [area.code, area.name]),
        ].some((value) =>
          String(value ?? "")
            .toLowerCase()
            .includes(query),
        );
      const matchesSource =
        !sourceFilter || engagement.sourceType === sourceFilter;
      const matchesStatus =
        !statusFilter ||
        (statusFilter === "ARCHIVED"
          ? engagement.isArchived
          : !engagement.isArchived && engagement.status === statusFilter);
      const matchesPhase = !phaseFilter || phaseFor(engagement) === phaseFilter;
      const matchesAdministrativeStatus =
        !administrativeFilter ||
        administrativeStatusFor(engagement) === administrativeFilter;
      const matchesOffice =
        !officeFilter ||
        engagement.offices.some(
          (office) => String(office.id) === String(officeFilter),
        );
      return (
        matchesSearch &&
        matchesSource &&
        matchesStatus &&
        matchesPhase &&
        matchesAdministrativeStatus &&
        matchesOffice
      );
    });
  }, [
    administrativeFilter,
    engagements,
    officeFilter,
    phaseFilter,
    search,
    sourceFilter,
    statusFilter,
  ]);

  const columns = [
    {
      key: "engagementCode",
      label: "Engagement",
      render: (engagement) => (
        <span>
          <strong className="block text-sm text-slate-900">
            {engagement.engagementCode}
          </strong>
          <span className="mt-0.5 block text-xs font-semibold text-sky-700">
            {engagement.sourceType === "PLANNED"
              ? "Approved IAP"
              : "Special / Unplanned"}
          </span>
        </span>
      ),
    },
    {
      key: "title",
      label: "Audit Engagement",
      render: (engagement) => (
        <span>
          <strong className="block max-w-xl text-sm text-slate-800">
            {engagement.title}
          </strong>
          <span className="mt-1 block text-xs text-slate-500">
            {engagement.auditAreas.map((area) => area.name).join(", ") || "—"}
          </span>
        </span>
      ),
    },
    {
      key: "offices",
      label: "Auditee Office",
      sortValue: (engagement) => engagement.offices[0]?.name ?? "",
      render: (engagement) => (
        <span className="block max-w-sm text-xs leading-5 text-slate-600">
          {engagement.offices
            .map((office) => `${office.code} — ${office.name}`)
            .join(", ") || "—"}
          {engagement.officeRule?.state === "LEGACY_MULTI_OFFICE" && (
            <span className="mt-1 block font-semibold text-amber-700">
              Legacy multi-office scope
            </span>
          )}
        </span>
      ),
    },
    {
      key: "plannedStartDate",
      label: "Planned Schedule",
      render: (engagement) => (
        <span className="whitespace-nowrap text-xs">
          <strong className="block text-slate-700">
            {date(engagement.plannedStartDate)}
          </strong>
          <span className="text-slate-500">
            to {date(engagement.plannedEndDate)}
          </span>
        </span>
      ),
    },
    {
      key: "plannedPersonDays",
      label: "Person-days",
      className: "whitespace-nowrap font-semibold text-slate-700",
      sortValue: (engagement) => Number(engagement.plannedPersonDays),
    },
    {
      key: "status",
      label: "Status",
      render: (engagement) => (
        <div className="flex min-w-[8.5rem] flex-col items-start gap-1.5">
          <StatusBadge
            tone={
              engagement.isArchived
                ? "inactive"
                : (statusTones[engagement.status] ?? "info")
            }
          >
            {engagement.isArchived
              ? "Archived"
              : (statusLabels[engagement.status] ?? engagement.status)}
          </StatusBadge>
          <span className="text-[11px] font-semibold text-slate-500">
            {phaseLabels[phaseFor(engagement)] ?? phaseFor(engagement)} ·{" "}
            {administrativeLabels[administrativeStatusFor(engagement)] ??
              administrativeStatusFor(engagement)}
          </span>
        </div>
      ),
    },
    {
      key: "actions",
      label: "Actions",
      sortable: false,
      render: (engagement) => (
        <div className="flex justify-end gap-1.5">
          {!engagement.isArchived && engagement.status === "DRAFT" && (
            <button
              aria-label={`Define scope for ${engagement.engagementCode}`}
              className="rounded-lg border border-sky-200 px-2.5 py-2 text-xs font-bold text-sky-700 transition hover:bg-sky-50"
              onClick={(event) => {
                event.stopPropagation();
                navigate(`/audit-engagement-management/scope?engagementId=${engagement.id}`);
              }}
              type="button"
            >
              Scope
            </button>
          )}
          {engagement.isArchived && canRestore ? (
            <button
              aria-label={`Restore ${engagement.engagementCode}`}
              className="grid h-9 w-9 place-items-center rounded-lg border border-emerald-200 text-emerald-700 transition hover:bg-emerald-50"
              onClick={() => setRestoreTarget(engagement)}
              type="button"
            >
              <RotateCcw size={17} />
            </button>
          ) : (
            canArchive && (
              <button
                aria-label={`Archive ${engagement.engagementCode}`}
                className="grid h-9 w-9 place-items-center rounded-lg border border-red-200 text-red-600 transition hover:bg-red-50"
                onClick={() => setArchiveTarget(engagement)}
                type="button"
              >
                <Archive size={17} />
              </button>
            )
          )}
        </div>
      ),
    },
  ];

  async function importEngagement() {
    if (!selectedImportId) return;
    setSaving(true);
    setErrors({});
    try {
      const created = await aemsEngagementApi.importFromIap({
        iapPlanEngagementId: Number(selectedImportId),
      });
      toast.success(
        `${created.engagementCode} was imported with a historical IAP snapshot.`,
      );
      setCreateOpen(false);
      setSelectedImportId("");
      await load();
      navigate(`/audit-engagement-management/${created.id}`);
    } catch (error) {
      if (error instanceof ApiError) setErrors(error.errors);
      toast.error(error instanceof Error ? error.message : "Import failed.");
    } finally {
      setSaving(false);
    }
  }

  async function createSpecial(payload) {
    setSaving(true);
    setErrors({});
    try {
      const created = await aemsEngagementApi.createSpecial(payload);
      toast.success(`${created.engagementCode} was created.`);
      setCreateOpen(false);
      await load();
      navigate(`/audit-engagement-management/scope?engagementId=${created.id}`);
    } catch (error) {
      if (error instanceof ApiError) setErrors(error.errors);
      toast.error(
        error instanceof Error
          ? error.message
          : "Unable to create the special engagement.",
      );
    } finally {
      setSaving(false);
    }
  }

  async function archive() {
    if (!archiveTarget) return;
    setSaving(true);
    try {
      await aemsEngagementApi.archive(archiveTarget.id);
      toast.success(`${archiveTarget.engagementCode} was archived.`);
      setArchiveTarget(null);
      await load();
    } catch (error) {
      toast.error(error instanceof Error ? error.message : "Archive failed.");
    } finally {
      setSaving(false);
    }
  }

  async function restore() {
    if (!restoreTarget) return;
    setSaving(true);
    try {
      await aemsEngagementApi.restore(restoreTarget.id);
      toast.success(`${restoreTarget.engagementCode} was restored.`);
      setRestoreTarget(null);
      await load();
    } catch (error) {
      toast.error(error instanceof Error ? error.message : "Restore failed.");
    } finally {
      setSaving(false);
    }
  }

  const hasFilters = Boolean(
    search ||
    sourceFilter ||
    statusFilter ||
    phaseFilter ||
    administrativeFilter ||
    officeFilter,
  );

  return (
    <main className="min-w-0 p-4 sm:p-5">
      <RegistryHeader
        actions={
          <>
            <button
              className="inline-flex min-h-11 items-center gap-2 rounded-lg border border-sky-300 bg-white px-4 text-sm font-bold text-sky-700 shadow-sm transition hover:bg-sky-50"
              onClick={() => navigate("/audit-engagement-management/dashboard")}
              type="button"
            >
              <LayoutDashboard size={17} />
              AEMS Dashboard
            </button>
            {canCreate && (
              <button
                className="inline-flex min-h-11 items-center gap-2 rounded-lg bg-emerald-700 px-4 text-sm font-bold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-emerald-800 hover:shadow-lg"
                onClick={() => {
                  setErrors({});
                  setCreateMode("iap");
                  setCreateOpen(true);
                }}
                type="button"
              >
                <Plus size={18} />
                Create engagement
              </button>
            )}
          </>
        }
        description="Create and prepare Draft engagements from approved IAP items or separately authorized special audits. Complete the engagement scope in its dedicated workspace before team assignment."
        icon={BriefcaseBusiness}
        readOnly={!canCreate}
        title="Audit Engagement Workspace"
      />

      <section className="mb-5 grid grid-cols-2 gap-3 xl:grid-cols-4">
        <SummaryCard
          icon={ClipboardCheck}
          label="Active Engagements"
          tone="sky"
          value={summary.total ?? 0}
        />
        <SummaryCard
          icon={FileInput}
          label="From Approved IAP"
          tone="emerald"
          value={summary.planned ?? 0}
        />
        <SummaryCard
          icon={Sparkles}
          label="Special / Unplanned"
          tone="amber"
          value={summary.special ?? 0}
        />
        <SummaryCard
          icon={ShieldCheck}
          label="In Progress"
          tone="slate"
          value={summary.ongoing ?? 0}
        />
      </section>

      {loadError && (
        <div className="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
          {loadError}
        </div>
      )}

      <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div className="grid gap-2 border-b border-slate-200 p-4 md:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-[minmax(18rem,1fr)_13rem_15rem_14rem_13rem_13rem_auto]">
          <label className="flex min-h-11 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 focus-within:border-sky-500 focus-within:ring-2 focus-within:ring-sky-100">
            <Search className="text-slate-400" size={18} />
            <input
              className="min-w-0 flex-1 bg-transparent text-sm outline-none"
              onChange={(event) => setSearch(event.target.value)}
              placeholder="Search code, title, office, area, or authority..."
              value={search}
            />
          </label>
          <SearchableSelect
            onChange={setSourceFilter}
            options={[
              { value: "PLANNED", label: "Approved IAP" },
              { value: "SPECIAL", label: "Special / Unplanned" },
            ]}
            placeholder="Filter by source"
            value={sourceFilter}
          />
          <SearchableSelect
            onChange={setStatusFilter}
            options={[
              ...Object.entries(statusLabels).map(([value, label]) => ({
                value,
                label,
              })),
              ...(includeArchived
                ? [{ value: "ARCHIVED", label: "Archived" }]
                : []),
            ]}
            placeholder="Filter by status"
            value={statusFilter}
          />
          <SearchableSelect
            onChange={setPhaseFilter}
            options={Object.entries(phaseLabels).map(([value, label]) => ({
              value,
              label,
            }))}
            placeholder="Filter by phase"
            value={phaseFilter}
          />
          <SearchableSelect
            onChange={setAdministrativeFilter}
            options={Object.entries(administrativeLabels).map(
              ([value, label]) => ({ value, label }),
            )}
            placeholder="Filter by administrative status"
            value={administrativeFilter}
          />
          <SearchableSelect
            onChange={setOfficeFilter}
            options={officeOptions}
            placeholder="Filter by office"
            value={officeFilter}
          />
          <button
            className="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-slate-300 px-4 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 disabled:opacity-40"
            disabled={!hasFilters}
            onClick={() => {
              setSearch("");
              setSourceFilter("");
              setStatusFilter("");
              setPhaseFilter("");
              setAdministrativeFilter("");
              setOfficeFilter("");
            }}
            type="button"
          >
            <X size={17} />
            Clear
          </button>
        </div>
        <DataTable
          columns={columns}
          emptyMessage="No audit engagements match the current filters."
          loading={loading}
          onRowClick={(engagement) =>
            navigate(`/audit-engagement-management/${engagement.id}`)
          }
          pageSizeOptions={[10, 25, 50, 100]}
          rows={filtered}
        />
      </section>

      <Modal
        footer={
          <>
            <button
              className="min-h-10 rounded-lg border border-slate-300 px-4 text-sm font-bold text-slate-700 hover:bg-slate-50"
              disabled={saving}
              onClick={() => setCreateOpen(false)}
              type="button"
            >
              Cancel
            </button>
            {createMode === "iap" ? (
              <button
                className="min-h-10 rounded-lg bg-emerald-700 px-5 text-sm font-bold text-white hover:bg-emerald-800 disabled:opacity-50"
                disabled={saving || !selectedImportId}
                onClick={importEngagement}
                type="button"
              >
                {saving ? "Importing..." : "Import approved IAP item"}
              </button>
            ) : (
              <button
                className="min-h-10 rounded-lg bg-emerald-700 px-5 text-sm font-bold text-white hover:bg-emerald-800 disabled:opacity-50"
                disabled={saving}
                form="create-special-engagement-form"
                type="submit"
              >
                {saving ? "Creating..." : "Create engagement draft"}
              </button>
            )}
          </>
        }
        onClose={() => !saving && setCreateOpen(false)}
        open={createOpen}
        size="xl"
        title="Create Engagement Draft"
      >
        <div className="mb-5 grid grid-cols-2 rounded-xl bg-slate-100 p-1">
          <button
            className={`rounded-lg px-3 py-2.5 text-sm font-bold transition ${
              createMode === "iap"
                ? "bg-white text-sky-800 shadow-sm"
                : "text-slate-600"
            }`}
            onClick={() => {
              setCreateMode("iap");
              setErrors({});
            }}
            type="button"
          >
            Import from approved IAP
          </button>
          <button
            className={`rounded-lg px-3 py-2.5 text-sm font-bold transition ${
              createMode === "special"
                ? "bg-white text-amber-800 shadow-sm"
                : "text-slate-600"
            }`}
            onClick={() => {
              setCreateMode("special");
              setErrors({});
            }}
            type="button"
          >
            Special / unplanned
          </button>
        </div>

        {createMode === "iap" ? (
          <div className="space-y-4">
            <SearchableSelect
              onChange={setSelectedImportId}
              options={importOptions.map((source) => ({
                value: source.id,
                label: `${source.engagementCode} — ${source.title}`,
                description: `${source.plan.planCode} · Residual risk ${source.residualRiskScore ?? "—"} · ${source.plannedPersonDays} person-days`,
              }))}
              placeholder="Select an approved IAP engagement"
              searchPlaceholder="Search plan, engagement, or office..."
              value={selectedImportId}
            />
            {errors.iapPlanEngagementId && (
              <p className="text-xs font-semibold text-red-600">
                {errors.iapPlanEngagementId[0]}
              </p>
            )}
            {selectedImport ? (
              <section className="grid gap-3 rounded-xl border border-sky-200 bg-sky-50/60 p-4 sm:grid-cols-2">
                <div>
                  <p className="text-[11px] font-bold uppercase text-slate-500">
                    Approved source
                  </p>
                  <p className="mt-1 text-sm font-bold text-slate-800">
                    {selectedImport.plan.planCode} · FY{" "}
                    {selectedImport.plan.fiscalYear}
                  </p>
                  <p className="mt-1 text-xs text-slate-600">
                    {selectedImport.offices
                      .map((office) => office.name)
                      .join(", ")}
                  </p>
                </div>
                <div>
                  <p className="text-[11px] font-bold uppercase text-slate-500">
                    Original planning result
                  </p>
                  <p className="mt-1 text-sm font-bold text-slate-800">
                    Rank {selectedImport.finalRank ?? "—"} ·{" "}
                    {selectedImport.riskLevelCode ?? "Unrated"} · residual{" "}
                    {selectedImport.residualRiskScore ?? "—"}
                  </p>
                  <p className="mt-1 text-xs text-slate-600">
                    {date(selectedImport.plannedStartDate)} to{" "}
                    {date(selectedImport.plannedEndDate)} ·{" "}
                    {selectedImport.plannedPersonDays} person-days
                  </p>
                </div>
                <p className="sm:col-span-2 rounded-lg bg-white px-3 py-2 text-xs leading-5 text-slate-600 ring-1 ring-sky-100">
                  AGIS copies this planning record into a historical snapshot.
                  Future IAP changes will not silently alter the engagement.
                </p>
              </section>
            ) : (
              <div className="rounded-xl border border-dashed border-slate-300 px-5 py-10 text-center">
                <BadgeCheck className="mx-auto text-slate-300" size={34} />
                <p className="mt-3 text-sm font-semibold text-slate-600">
                  {importOptions.length
                    ? "Select an approved IAP item to review its source details."
                    : "No approved IAP items are currently eligible for import."}
                </p>
              </div>
            )}
          </div>
        ) : (
          <AemsSpecialEngagementForm
            auditAreas={auditAreas}
            auditFocuses={auditFocuses}
            errors={errors}
            formId="create-special-engagement-form"
            masterLists={masterLists}
            offices={offices}
            onSubmit={createSpecial}
            users={users.filter((person) => person.id !== user.id)}
          />
        )}
      </Modal>

      <ConfirmDialog
        busy={saving}
        confirmLabel="Archive engagement"
        description={`${archiveTarget?.engagementCode ?? "This engagement"} will be soft archived with its complete source snapshot and history retained.`}
        onCancel={() => setArchiveTarget(null)}
        onConfirm={archive}
        open={Boolean(archiveTarget)}
        title="Archive engagement?"
        tone="danger"
      />
      <ConfirmDialog
        busy={saving}
        confirmLabel="Restore engagement"
        description={`${restoreTarget?.engagementCode ?? "This engagement"} will be restored. Duplicate IAP sources remain prohibited.`}
        onCancel={() => setRestoreTarget(null)}
        onConfirm={restore}
        open={Boolean(restoreTarget)}
        title="Restore engagement?"
      />
    </main>
  );
}
