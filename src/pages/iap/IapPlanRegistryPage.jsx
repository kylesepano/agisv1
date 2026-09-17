import { useCallback, useEffect, useMemo, useState } from "react";
import {
  Archive,
  CalendarClock,
  CalendarDays,
  CircleCheckBig,
  ClipboardList,
  Plus,
  RotateCcw,
  Search,
  ShieldCheck,
  X,
} from "lucide-react";
import { useNavigate } from "react-router";
import { useAuth } from "../../auth/auth-context";
import IapPlanForm from "../../components/iap/IapPlanForm";
import ConfirmDialog from "../../components/ui/ConfirmDialog";
import DataTable from "../../components/ui/DataTable";
import Modal from "../../components/ui/Modal";
import RegistryHeader from "../../components/ui/RegistryHeader";
import SearchableSelect from "../../components/ui/SearchableSelect";
import StatusBadge from "../../components/ui/StatusBadge";
import SummaryCard from "../../components/ui/SummaryCard";
import { hasPermission } from "../../config/navigation";
import { ApiError, iapApi, masterListApi, userApi } from "../../services/api";
import { useToast } from "../../ui/toast-context";

const statusLabels = {
  DRAFT: "Draft",
  PENDING_REVIEW: "Pending Review",
  RETURNED_FOR_REVISION: "Returned for Revision",
  RESUBMITTED: "Resubmitted",
  APPROVED: "Approved",
  ACTIVE: "Active",
  COMPLETED: "Completed",
  REJECTED: "Rejected",
};

const statusTones = {
  DRAFT: "inactive",
  PENDING_REVIEW: "warning",
  RETURNED_FOR_REVISION: "danger",
  RESUBMITTED: "warning",
  APPROVED: "success",
  ACTIVE: "info",
  COMPLETED: "active",
  REJECTED: "danger",
};

function formatDate(value) {
  if (!value) return "—";
  return new Intl.DateTimeFormat("en-PH", {
    month: "short",
    day: "numeric",
    year: "numeric",
  }).format(new Date(`${value}T00:00:00`));
}

/**
 * Lists Annual Internal Audit Plan revisions and provides lifecycle entry
 * points while keeping approved revisions immutable.
 */
export default function IapPlanRegistryPage() {
  const { user } = useAuth();
  const toast = useToast();
  const navigate = useNavigate();
  const [plans, setPlans] = useState([]);
  const [masterLists, setMasterLists] = useState([]);
  const [users, setUsers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [loadError, setLoadError] = useState("");
  const [errors, setErrors] = useState({});
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState("");
  const [yearFilter, setYearFilter] = useState("");
  const [createOpen, setCreateOpen] = useState(false);
  const [archiveTarget, setArchiveTarget] = useState(null);
  const [restoreTarget, setRestoreTarget] = useState(null);

  const canCreate = hasPermission(user, "iap.create");
  const canArchive = hasPermission(user, "iap.archive");
  const canRestore = hasPermission(user, "iap.restore");
  const maySeeArchived = canArchive || canRestore;

  const load = useCallback(async () => {
    setLoading(true);
    setLoadError("");
    try {
      const [{ plans: records }, lists, planningUsers] = await Promise.all([
        iapApi.list({
          includeArchived: maySeeArchived,
          perPage: 100,
          sortBy: "fiscal_year",
          sortDirection: "desc",
        }),
        canCreate ? masterListApi.list() : Promise.resolve([]),
        canCreate ? userApi.list() : Promise.resolve([]),
      ]);
      setPlans(records);
      setMasterLists(lists);
      setUsers(planningUsers);
    } catch (error) {
      setLoadError(
        error instanceof Error
          ? error.message
          : "Unable to load internal audit plans.",
      );
    } finally {
      setLoading(false);
    }
  }, [canCreate, maySeeArchived]);

  useEffect(() => {
    const timer = window.setTimeout(load, 0);
    return () => window.clearTimeout(timer);
  }, [load]);

  const years = useMemo(
    () =>
      [...new Set(plans.map((plan) => plan.fiscalYear))]
        .sort((left, right) => right - left)
        .map((year) => ({ value: year, label: String(year) })),
    [plans],
  );

  const filteredPlans = useMemo(() => {
    const query = search.trim().toLowerCase();
    return plans.filter((plan) => {
      const matchesSearch =
        !query ||
        [
          plan.planCode,
          plan.title,
          plan.preparer?.name,
          plan.fiscalYear,
          statusLabels[plan.status],
        ].some((value) =>
          String(value ?? "")
            .toLowerCase()
            .includes(query),
        );
      const matchesStatus =
        !statusFilter ||
        (statusFilter === "ARCHIVED"
          ? plan.isArchived
          : !plan.isArchived && plan.status === statusFilter);
      const matchesYear =
        !yearFilter || String(plan.fiscalYear) === String(yearFilter);
      return matchesSearch && matchesStatus && matchesYear;
    });
  }, [plans, search, statusFilter, yearFilter]);

  const stats = useMemo(
    () => ({
      total: plans.filter((plan) => !plan.isArchived).length,
      preparation: plans.filter(
        (plan) =>
          !plan.isArchived &&
          ["DRAFT", "RETURNED_FOR_REVISION"].includes(plan.status),
      ).length,
      review: plans.filter(
        (plan) =>
          !plan.isArchived &&
          ["PENDING_REVIEW", "RESUBMITTED"].includes(plan.status),
      ).length,
      approved: plans.filter(
        (plan) =>
          !plan.isArchived &&
          ["APPROVED", "ACTIVE", "COMPLETED"].includes(plan.status),
      ).length,
    }),
    [plans],
  );

  const columns = [
    {
      key: "planCode",
      label: "Plan",
      render: (plan) => (
        <span>
          <strong className="block text-sm text-slate-900">
            {plan.planCode}
          </strong>
          <span className="mt-0.5 block text-xs text-slate-500">
            Revision {plan.revisionNumber}
            {plan.isCurrentRevision ? " · Current" : " · Superseded"}
          </span>
        </span>
      ),
    },
    {
      key: "title",
      label: "Annual Plan",
      render: (plan) => (
        <span>
          <strong className="block max-w-xl text-sm text-slate-800">
            {plan.title}
          </strong>
          <span className="mt-1 block text-xs text-slate-500">
            {formatDate(plan.planningPeriodStart)} –{" "}
            {formatDate(plan.planningPeriodEnd)}
          </span>
        </span>
      ),
    },
    {
      key: "fiscalYear",
      label: "Fiscal Year",
      className: "whitespace-nowrap font-semibold text-slate-700",
    },
    {
      key: "preparer",
      label: "Prepared By",
      sortValue: (plan) => plan.preparer?.name ?? "",
      render: (plan) => (
        <span>
          <strong className="block text-sm text-slate-700">
            {plan.preparer?.name ?? "Unassigned"}
          </strong>
          <span className="text-xs text-slate-500">
            {plan.preparer?.employeeId}
          </span>
        </span>
      ),
    },
    {
      key: "coverage",
      label: "Planning Records",
      sortable: false,
      render: (plan) => (
        <span className="flex flex-wrap gap-1.5">
          <span className="rounded-md bg-sky-50 px-2 py-1 text-xs font-semibold text-sky-700">
            {plan.riskAssessmentCount ?? 0} risks
          </span>
          <span className="rounded-md bg-violet-50 px-2 py-1 text-xs font-semibold text-violet-700">
            {plan.engagementCount ?? 0} engagements
          </span>
        </span>
      ),
    },
    {
      key: "status",
      label: "Status",
      render: (plan) => (
        <StatusBadge
          tone={
            plan.isArchived ? "inactive" : (statusTones[plan.status] ?? "info")
          }
        >
          {plan.isArchived
            ? "Archived"
            : (statusLabels[plan.status] ?? plan.status)}
        </StatusBadge>
      ),
    },
    {
      key: "actions",
      label: "Actions",
      sortable: false,
      render: (plan) => (
        <div className="flex justify-end gap-1.5">
          {plan.isArchived && canRestore ? (
            <button
              aria-label={`Restore ${plan.planCode}`}
              className="grid h-9 w-9 place-items-center rounded-lg border border-emerald-200 text-emerald-700 transition hover:bg-emerald-50"
              onClick={() => setRestoreTarget(plan)}
              type="button"
            >
              <RotateCcw size={17} />
            </button>
          ) : (
            canArchive &&
            [
              "DRAFT",
              "RETURNED_FOR_REVISION",
              "REJECTED",
              "COMPLETED",
            ].includes(plan.status) && (
              <button
                aria-label={`Archive ${plan.planCode}`}
                className="grid h-9 w-9 place-items-center rounded-lg border border-red-200 text-red-600 transition hover:bg-red-50"
                onClick={() => setArchiveTarget(plan)}
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

  async function createPlan(payload) {
    setSaving(true);
    setErrors({});
    try {
      const created = await iapApi.create(payload);
      toast.success(`${created.planCode} was created as a draft.`);
      setCreateOpen(false);
      await load();
      navigate(`/internal-audit-planning/${created.id}`);
    } catch (error) {
      if (error instanceof ApiError) setErrors(error.errors);
      toast.error(
        error instanceof Error ? error.message : "Unable to create the plan.",
      );
    } finally {
      setSaving(false);
    }
  }

  async function archivePlan() {
    if (!archiveTarget) return;
    setSaving(true);
    try {
      await iapApi.archive(archiveTarget.id);
      toast.success(`${archiveTarget.planCode} was archived.`);
      setArchiveTarget(null);
      await load();
    } catch (error) {
      toast.error(
        error instanceof Error ? error.message : "Unable to archive the plan.",
      );
    } finally {
      setSaving(false);
    }
  }

  async function restorePlan() {
    if (!restoreTarget) return;
    setSaving(true);
    try {
      await iapApi.restore(restoreTarget.id);
      toast.success(`${restoreTarget.planCode} was restored.`);
      setRestoreTarget(null);
      await load();
    } catch (error) {
      toast.error(
        error instanceof Error ? error.message : "Unable to restore the plan.",
      );
    } finally {
      setSaving(false);
    }
  }

  const hasFilters = Boolean(search || statusFilter || yearFilter);

  return (
    <main className="min-w-0 p-4 sm:p-5">
      <RegistryHeader
        actions={
          canCreate && (
            <button
              className="inline-flex min-h-11 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-sky-800 hover:shadow-lg"
              onClick={() => {
                setErrors({});
                setCreateOpen(true);
              }}
              type="button"
            >
              <Plus size={18} />
              Create annual plan
            </button>
          )
        }
        description="Build, review, approve, and monitor risk-based Annual Internal Audit Plans."
        icon={CalendarDays}
        readOnly={!canCreate && !hasPermission(user, "iap.update")}
        title="Internal Audit Planning"
      />

      <section className="mb-5 grid grid-cols-2 gap-3 xl:grid-cols-4">
        <SummaryCard
          icon={ClipboardList}
          label="Active Plan Records"
          tone="sky"
          value={stats.total}
        />
        <SummaryCard
          icon={CalendarClock}
          label="In Preparation"
          tone="amber"
          value={stats.preparation}
        />
        <SummaryCard
          icon={ShieldCheck}
          label="For Review"
          tone="slate"
          value={stats.review}
        />
        <SummaryCard
          icon={CircleCheckBig}
          label="Approved or Active"
          tone="emerald"
          value={stats.approved}
        />
      </section>

      {loadError && (
        <div className="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
          {loadError}
        </div>
      )}

      <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div className="grid gap-2 border-b border-slate-200 p-4 lg:grid-cols-[minmax(18rem,1fr)_15rem_12rem_auto]">
          <label className="flex min-h-11 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 focus-within:border-sky-500 focus-within:ring-2 focus-within:ring-sky-100">
            <Search className="text-slate-400" size={18} />
            <input
              className="min-w-0 flex-1 bg-transparent text-sm outline-none placeholder:text-slate-400"
              onChange={(event) => setSearch(event.target.value)}
              placeholder="Search plan code, title, year, or preparer..."
              value={search}
            />
          </label>
          <SearchableSelect
            onChange={setStatusFilter}
            options={[
              ...Object.entries(statusLabels).map(([value, label]) => ({
                value,
                label,
              })),
              ...(maySeeArchived
                ? [{ value: "ARCHIVED", label: "Archived" }]
                : []),
            ]}
            placeholder="Filter by status"
            searchPlaceholder="Search statuses..."
            value={statusFilter}
          />
          <SearchableSelect
            onChange={setYearFilter}
            options={years}
            placeholder="Filter by year"
            searchPlaceholder="Search years..."
            value={yearFilter}
          />
          <button
            className="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-slate-300 px-4 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
            disabled={!hasFilters}
            onClick={() => {
              setSearch("");
              setStatusFilter("");
              setYearFilter("");
            }}
            type="button"
          >
            <X size={17} />
            Clear filters
          </button>
        </div>

        <DataTable
          columns={columns}
          emptyMessage="No annual internal audit plans match the current filters."
          loading={loading}
          onRowClick={(plan) =>
            !plan.isArchived && navigate(`/internal-audit-planning/${plan.id}`)
          }
          pageSizeOptions={[10, 25, 50, 100]}
          rows={filteredPlans}
        />
      </section>

      <Modal
        footer={
          <>
            <button
              className="min-h-10 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700 hover:bg-slate-50"
              disabled={saving}
              onClick={() => setCreateOpen(false)}
              type="button"
            >
              Cancel
            </button>
            <button
              className="min-h-10 rounded-lg bg-sky-700 px-5 text-sm font-bold text-white hover:bg-sky-800 disabled:opacity-60"
              disabled={saving}
              form="create-iap-plan-form"
              type="submit"
            >
              {saving ? "Creating..." : "Create draft plan"}
            </button>
          </>
        }
        onClose={() => !saving && setCreateOpen(false)}
        open={createOpen}
        size="xl"
        title="Create Annual Internal Audit Plan"
      >
        <IapPlanForm
          currentUserId={user.id}
          errors={errors}
          formId="create-iap-plan-form"
          masterLists={masterLists}
          onSubmit={createPlan}
          users={users}
        />
      </Modal>

      <ConfirmDialog
        busy={saving}
        confirmLabel="Archive plan"
        description={`${archiveTarget?.planCode ?? "This plan"} will be archived but will remain recoverable.`}
        onCancel={() => setArchiveTarget(null)}
        onConfirm={archivePlan}
        open={Boolean(archiveTarget)}
        title="Archive annual plan?"
        tone="danger"
      />
      <ConfirmDialog
        busy={saving}
        confirmLabel="Restore plan"
        description={`${restoreTarget?.planCode ?? "This plan"} will be restored. It becomes current only when no other current revision exists for the fiscal year.`}
        onCancel={() => setRestoreTarget(null)}
        onConfirm={restorePlan}
        open={Boolean(restoreTarget)}
        title="Restore annual plan?"
      />
    </main>
  );
}
