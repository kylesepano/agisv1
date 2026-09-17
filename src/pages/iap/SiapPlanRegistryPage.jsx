import { useCallback, useEffect, useMemo, useState } from "react";
import {
  Archive,
  CalendarRange,
  CheckCircle2,
  ClipboardCheck,
  FileClock,
  History,
  Pencil,
  Plus,
  RotateCcw,
  Search,
  ShieldCheck,
  Sparkles,
  Target,
  X,
} from "lucide-react";
import { useAuth } from "../../auth/auth-context";
import SiapPlanForm from "../../components/iap/SiapPlanForm";
import ConfirmDialog from "../../components/ui/ConfirmDialog";
import DataTable from "../../components/ui/DataTable";
import Modal from "../../components/ui/Modal";
import RegistryHeader from "../../components/ui/RegistryHeader";
import SearchableSelect from "../../components/ui/SearchableSelect";
import StatusBadge from "../../components/ui/StatusBadge";
import SummaryCard from "../../components/ui/SummaryCard";
import { hasPermission } from "../../config/navigation";
import { ApiError, auditAreaApi, siapApi, userApi } from "../../services/api";
import { useToast } from "../../ui/toast-context";
import useRecordView from "../../hooks/useRecordView";

const statusLabels = {
  DRAFT: "Draft",
  PENDING_REVIEW: "Pending Review",
  RETURNED_FOR_REVISION: "Returned for Revision",
  RESUBMITTED: "Resubmitted",
  APPROVED: "Approved",
  ACTIVE: "Active",
  COMPLETED: "Completed",
};

const statusTones = {
  DRAFT: "inactive",
  PENDING_REVIEW: "warning",
  RETURNED_FOR_REVISION: "danger",
  RESUBMITTED: "warning",
  APPROVED: "success",
  ACTIVE: "info",
  COMPLETED: "active",
};

const workflowLabels = {
  submit: "Submit for review",
  resubmit: "Resubmit",
  return: "Return for revision",
  approve: "Approve",
  activate: "Activate",
  complete: "Complete",
  revision: "Create revision",
};

function formatDateTime(value) {
  if (!value) return "—";
  return new Intl.DateTimeFormat("en-PH", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(value));
}

function availableActions(plan, user) {
  if (!plan || plan.isArchived) return [];
  const actions = [];
  if (plan.status === "DRAFT" && hasPermission(user, "iap.submit")) {
    actions.push("submit");
  }
  if (
    plan.status === "RETURNED_FOR_REVISION" &&
    hasPermission(user, "iap.submit")
  ) {
    actions.push("resubmit");
  }
  if (
    ["PENDING_REVIEW", "RESUBMITTED"].includes(plan.status) &&
    hasPermission(user, "iap.review")
  ) {
    actions.push("return");
  }
  if (
    ["PENDING_REVIEW", "RESUBMITTED"].includes(plan.status) &&
    hasPermission(user, "iap.approve")
  ) {
    actions.push("approve");
  }
  if (plan.status === "APPROVED" && hasPermission(user, "iap.activate")) {
    actions.push("activate");
  }
  if (plan.status === "ACTIVE" && hasPermission(user, "iap.complete")) {
    actions.push("complete");
  }
  if (
    ["APPROVED", "ACTIVE"].includes(plan.status) &&
    hasPermission(user, "iap.create_revision")
  ) {
    actions.push("revision");
  }
  return actions;
}

/**
 * Manages multi-year Strategic Internal Audit Plans, objectives, priorities,
 * approval transitions, revisions, and preserved approved versions.
 */
export default function SiapPlanRegistryPage() {
  const { user } = useAuth();
  const toast = useToast();
  const [plans, setPlans] = useState([]);
  const [auditAreas, setAuditAreas] = useState([]);
  const [users, setUsers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [loadError, setLoadError] = useState("");
  const [errors, setErrors] = useState({});
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState("");
  const [editorOpen, setEditorOpen] = useState(false);
  const [editing, setEditing] = useState(null);
  const [selected, setSelected] = useState(null);
  useRecordView(selected, {
    module: "IAP",
    recordType: "SIAP",
    code: (record) => record.planCode,
    label: (record) => record.title,
  });
  const [detailLoading, setDetailLoading] = useState(false);
  const [archiveTarget, setArchiveTarget] = useState(null);
  const [restoreTarget, setRestoreTarget] = useState(null);
  const [workflowAction, setWorkflowAction] = useState("");
  const [workflowComment, setWorkflowComment] = useState("");
  const [completionConfirmed, setCompletionConfirmed] = useState(false);

  const canCreate = hasPermission(user, "iap.create");
  const canUpdate = hasPermission(user, "iap.update");
  const canArchive = hasPermission(user, "iap.archive");
  const canRestore = hasPermission(user, "iap.restore");
  const maySeeArchived = canArchive || canRestore;
  const needsEditorData = canCreate || canUpdate;

  const load = useCallback(async () => {
    setLoading(true);
    setLoadError("");
    try {
      const [{ strategicPlans }, areas, planningUsers] = await Promise.all([
        siapApi.list({
          includeArchived: maySeeArchived,
          perPage: 100,
          sortBy: "start_year",
          sortDirection: "desc",
        }),
        needsEditorData ? auditAreaApi.list() : Promise.resolve([]),
        needsEditorData ? userApi.list() : Promise.resolve([]),
      ]);
      setPlans(strategicPlans);
      setAuditAreas(areas);
      setUsers(planningUsers);
    } catch (error) {
      setLoadError(
        error instanceof Error
          ? error.message
          : "Unable to load Strategic Internal Audit Plans.",
      );
    } finally {
      setLoading(false);
    }
  }, [maySeeArchived, needsEditorData]);

  useEffect(() => {
    const timer = window.setTimeout(load, 0);
    return () => window.clearTimeout(timer);
  }, [load]);

  const filteredPlans = useMemo(() => {
    const query = search.trim().toLowerCase();
    return plans.filter((plan) => {
      const matchesSearch =
        !query ||
        [
          plan.planCode,
          plan.title,
          plan.startYear,
          plan.endYear,
          plan.preparer?.name,
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
      return matchesSearch && matchesStatus;
    });
  }, [plans, search, statusFilter]);

  const stats = useMemo(
    () => ({
      total: plans.filter((plan) => !plan.isArchived).length,
      draft: plans.filter(
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

  async function openPlan(planOrId) {
    const id = typeof planOrId === "object" ? planOrId.id : planOrId;
    setDetailLoading(true);
    try {
      setSelected(await siapApi.show(id));
    } catch (error) {
      toast.error(error.message);
    } finally {
      setDetailLoading(false);
    }
  }

  async function openEditor(plan = null) {
    setErrors({});
    if (!plan) {
      setEditing(null);
      setEditorOpen(true);
      return;
    }
    setSaving(true);
    try {
      setEditing(plan.objectives ? plan : await siapApi.show(plan.id));
      setEditorOpen(true);
    } catch (error) {
      toast.error(error.message);
    } finally {
      setSaving(false);
    }
  }

  async function persist(payload) {
    setSaving(true);
    setErrors({});
    try {
      const saved = editing
        ? await siapApi.update(editing.id, payload)
        : await siapApi.create(payload);
      toast.success(
        editing
          ? "Strategic plan updated successfully."
          : "Strategic plan created successfully.",
      );
      setEditorOpen(false);
      setEditing(null);
      await load();
      await openPlan(saved.id);
    } catch (error) {
      if (error instanceof ApiError && error.status === 422) {
        setErrors(error.errors);
      }
      toast.error(error.message);
    } finally {
      setSaving(false);
    }
  }

  async function performWorkflow() {
    if (!selected || !workflowAction) return;
    setSaving(true);
    try {
      const updated =
        workflowAction === "revision"
          ? await siapApi.createRevision(selected.id, {
              lockVersion: selected.lockVersion,
              reason: workflowComment,
            })
          : await siapApi.transition(selected.id, workflowAction, {
              lockVersion: selected.lockVersion,
              comment: workflowComment.trim() || null,
              completionConfirmed,
            });
      toast.success(
        workflowAction === "revision"
          ? `${updated.planCode} was created as a new draft revision.`
          : `Strategic plan ${workflowLabels[workflowAction].toLowerCase()} completed.`,
      );
      setWorkflowAction("");
      setWorkflowComment("");
      setCompletionConfirmed(false);
      await load();
      await openPlan(updated.id);
    } catch (error) {
      toast.error(error.message);
    } finally {
      setSaving(false);
    }
  }

  async function archivePlan() {
    if (!archiveTarget) return;
    setSaving(true);
    try {
      await siapApi.archive(archiveTarget.id);
      toast.success(`${archiveTarget.planCode} was archived.`);
      setArchiveTarget(null);
      if (selected?.id === archiveTarget.id) setSelected(null);
      await load();
    } catch (error) {
      toast.error(error.message);
    } finally {
      setSaving(false);
    }
  }

  async function restorePlan() {
    if (!restoreTarget) return;
    setSaving(true);
    try {
      await siapApi.restore(restoreTarget.id);
      toast.success(`${restoreTarget.planCode} was restored.`);
      setRestoreTarget(null);
      await load();
    } catch (error) {
      toast.error(error.message);
    } finally {
      setSaving(false);
    }
  }

  const columns = [
    {
      key: "planCode",
      label: "Strategic Plan",
      render: (plan) => (
        <span>
          <strong className="block text-sm text-slate-900">{plan.title}</strong>
          <span className="mt-1 block text-xs font-semibold text-sky-700">
            {plan.planCode} · Revision {plan.revisionNumber}
            {plan.isCurrentRevision ? " · Current" : " · Superseded"}
          </span>
        </span>
      ),
    },
    {
      key: "startYear",
      label: "Planning Period",
      sortValue: (plan) => plan.startYear,
      render: (plan) => (
        <strong className="text-sm text-slate-700">
          {plan.startYear}–{plan.endYear}
        </strong>
      ),
    },
    {
      key: "objectivesCount",
      label: "Objectives",
      render: (plan) => (
        <span className="font-semibold text-slate-700">
          {plan.objectivesCount ?? plan.objectives?.length ?? 0}
        </span>
      ),
    },
    {
      key: "prioritiesCount",
      label: "Priorities",
      render: (plan) => (
        <span className="font-semibold text-slate-700">
          {plan.prioritiesCount ?? plan.priorities?.length ?? 0}
        </span>
      ),
    },
    {
      key: "status",
      label: "Status",
      render: (plan) => (
        <StatusBadge
          tone={plan.isArchived ? "inactive" : statusTones[plan.status]}
        >
          {plan.isArchived ? "Archived" : statusLabels[plan.status]}
        </StatusBadge>
      ),
    },
    {
      key: "preparedBy",
      label: "Prepared By",
      sortValue: (plan) => plan.preparer?.name,
      render: (plan) => (
        <span className="text-sm text-slate-600">
          {plan.preparer?.name ?? "—"}
        </span>
      ),
    },
    ...(canUpdate || canArchive || canRestore
      ? [
          {
            key: "actions",
            label: "Actions",
            className: "text-right",
            render: (plan) => (
              <div className="flex justify-end gap-1">
                {canUpdate &&
                  !plan.isArchived &&
                  ["DRAFT", "RETURNED_FOR_REVISION"].includes(plan.status) && (
                    <button
                      className="grid h-9 w-9 place-items-center rounded-lg text-sky-700 hover:bg-sky-100"
                      onClick={(event) => {
                        event.stopPropagation();
                        openEditor(plan);
                      }}
                      title="Edit strategic plan"
                      type="button"
                    >
                      <Pencil size={17} />
                    </button>
                  )}
                {canArchive &&
                  !plan.isArchived &&
                  ["DRAFT", "RETURNED_FOR_REVISION", "COMPLETED"].includes(
                    plan.status,
                  ) && (
                    <button
                      className="grid h-9 w-9 place-items-center rounded-lg text-red-600 hover:bg-red-100"
                      onClick={(event) => {
                        event.stopPropagation();
                        setArchiveTarget(plan);
                      }}
                      title="Archive strategic plan"
                      type="button"
                    >
                      <Archive size={17} />
                    </button>
                  )}
                {canRestore && plan.isArchived && (
                  <button
                    className="grid h-9 w-9 place-items-center rounded-lg text-emerald-700 hover:bg-emerald-100"
                    onClick={(event) => {
                      event.stopPropagation();
                      setRestoreTarget(plan);
                    }}
                    title="Restore strategic plan"
                    type="button"
                  >
                    <RotateCcw size={17} />
                  </button>
                )}
              </div>
            ),
          },
        ]
      : []),
  ];

  return (
    <div className="p-4 sm:p-6">
      <RegistryHeader
        actions={
          canCreate && (
            <button
              className="inline-flex h-11 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-sky-800"
              onClick={() => openEditor()}
              type="button"
            >
              <Plus size={18} /> Create strategic plan
            </button>
          )
        }
        description="Set multi-year internal-audit objectives, priorities, themes, expected outcomes, and audit-area coverage."
        icon={Sparkles}
        readOnly={!canCreate && !canUpdate}
        title="Strategic Internal Audit Plan"
      />

      <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <SummaryCard
          icon={CalendarRange}
          label="Strategic Plans"
          tone="sky"
          value={stats.total}
        />
        <SummaryCard
          icon={FileClock}
          label="In Preparation"
          tone="amber"
          value={stats.draft}
        />
        <SummaryCard
          icon={ClipboardCheck}
          label="Under Review"
          tone="slate"
          value={stats.review}
        />
        <SummaryCard
          icon={ShieldCheck}
          label="Approved / Active"
          tone="emerald"
          value={stats.approved}
        />
      </section>

      {loadError && (
        <div className="mt-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700">
          {loadError}
        </div>
      )}

      <section className="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div className="grid gap-2 border-b border-slate-200 p-4 lg:grid-cols-[minmax(18rem,1fr)_15rem_auto]">
          <label className="flex h-11 items-center gap-2 rounded-lg border border-slate-300 px-3 text-slate-500">
            <Search size={17} />
            <input
              className="min-w-0 flex-1 outline-none"
              onChange={(event) => setSearch(event.target.value)}
              placeholder="Search plan, period, status, or preparer..."
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
            value={statusFilter}
          />
          <button
            className="inline-flex h-11 items-center justify-center gap-2 rounded-lg border border-slate-300 px-4 text-sm font-bold text-slate-600 hover:bg-slate-50"
            onClick={() => {
              setSearch("");
              setStatusFilter("");
            }}
            type="button"
          >
            <X size={16} /> Clear filters
          </button>
        </div>
        <DataTable
          columns={columns}
          emptyMessage="No Strategic Internal Audit Plans match the current filters."
          loading={loading}
          onRowClick={openPlan}
          rows={filteredPlans}
        />
      </section>

      <Modal
        onClose={() => setSelected(null)}
        open={Boolean(selected) || detailLoading}
        size="xl"
        title={selected?.title ?? "Loading strategic plan..."}
      >
        {detailLoading && !selected ? (
          <div className="h-64 animate-pulse rounded-xl bg-slate-100" />
        ) : (
          selected && (
            <div className="grid gap-5">
              <section className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <div className="flex flex-wrap items-center gap-2">
                      <StatusBadge
                        tone={
                          selected.isArchived
                            ? "inactive"
                            : statusTones[selected.status]
                        }
                      >
                        {selected.isArchived
                          ? "Archived"
                          : statusLabels[selected.status]}
                      </StatusBadge>
                      <span className="text-xs font-bold text-sky-700">
                        {selected.planCode}
                      </span>
                    </div>
                    <p className="mt-3 text-sm font-bold text-slate-800">
                      Planning period {selected.startYear}–{selected.endYear} ·
                      Revision {selected.revisionNumber}
                    </p>
                    <p className="mt-1 text-xs text-slate-500">
                      Prepared by {selected.preparer?.name ?? "—"}
                      {selected.coordinator
                        ? ` · Coordinated by ${selected.coordinator.name}`
                        : ""}
                    </p>
                  </div>
                  {!selected.isArchived && (
                    <div className="flex flex-wrap justify-end gap-2">
                      {canUpdate &&
                        ["DRAFT", "RETURNED_FOR_REVISION"].includes(
                          selected.status,
                        ) && (
                          <button
                            className="inline-flex h-10 items-center gap-2 rounded-lg border border-sky-300 px-3 text-sm font-bold text-sky-700 hover:bg-sky-50"
                            onClick={() => openEditor(selected)}
                            type="button"
                          >
                            <Pencil size={16} /> Edit
                          </button>
                        )}
                      {availableActions(selected, user).map((action) => (
                        <button
                          className="h-10 rounded-lg bg-sky-700 px-3 text-sm font-bold text-white hover:bg-sky-800"
                          key={action}
                          onClick={() => {
                            setWorkflowComment("");
                            setCompletionConfirmed(false);
                            setWorkflowAction(action);
                          }}
                          type="button"
                        >
                          {workflowLabels[action]}
                        </button>
                      ))}
                    </div>
                  )}
                </div>
                <dl className="mt-4 grid gap-3 sm:grid-cols-2">
                  {[
                    ["Strategic context", selected.strategicContext],
                    ["Vision", selected.vision],
                    ["Mission alignment", selected.missionAlignment],
                    ["Planning methodology", selected.planningMethodology],
                    ["Overall expected outcomes", selected.expectedOutcomes],
                  ].map(([label, value]) => (
                    <div
                      className="rounded-lg border border-slate-200 bg-white p-3"
                      key={label}
                    >
                      <dt className="text-xs font-bold uppercase tracking-wide text-slate-500">
                        {label}
                      </dt>
                      <dd className="mt-2 whitespace-pre-wrap text-sm leading-6 text-slate-700">
                        {value || "Not provided"}
                      </dd>
                    </div>
                  ))}
                </dl>
              </section>

              <section>
                <h3 className="flex items-center gap-2 font-bold text-slate-800">
                  <Target className="text-sky-700" size={19} />
                  Strategic objectives ({selected.objectives.length})
                </h3>
                <div className="mt-3 grid gap-3">
                  {selected.objectives.map((objective) => (
                    <article
                      className="rounded-xl border border-slate-200 p-4"
                      key={objective.id}
                    >
                      <strong className="text-sm text-slate-900">
                        {objective.objectiveCode} — {objective.title}
                      </strong>
                      <p className="mt-2 text-sm leading-6 text-slate-600">
                        {objective.description}
                      </p>
                      <p className="mt-3 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800">
                        <strong>Expected outcome:</strong>{" "}
                        {objective.expectedOutcome}
                      </p>
                      <div className="mt-3 flex flex-wrap gap-2">
                        {objective.auditAreas.map((area) => (
                          <span
                            className="rounded-full bg-sky-100 px-2.5 py-1 text-xs font-semibold text-sky-800"
                            key={area.id}
                          >
                            {area.code} — {area.name}
                          </span>
                        ))}
                      </div>
                    </article>
                  ))}
                </div>
              </section>

              <section>
                <h3 className="flex items-center gap-2 font-bold text-slate-800">
                  <Sparkles className="text-violet-700" size={19} />
                  Audit priorities and themes ({selected.priorities.length})
                </h3>
                <div className="mt-3 grid gap-3 sm:grid-cols-2">
                  {selected.priorities.map((priority) => (
                    <article
                      className="rounded-xl border border-violet-200 bg-violet-50/40 p-4"
                      key={priority.id}
                    >
                      <span className="text-xs font-bold uppercase text-violet-700">
                        {priority.priorityCode} · {priority.theme}
                      </span>
                      <strong className="mt-2 block text-sm text-slate-900">
                        {priority.title}
                      </strong>
                      <p className="mt-2 text-sm leading-6 text-slate-600">
                        {priority.description}
                      </p>
                      <p className="mt-3 text-xs font-semibold text-emerald-700">
                        Expected: {priority.expectedOutcome}
                      </p>
                    </article>
                  ))}
                </div>
              </section>

              <section>
                <h3 className="flex items-center gap-2 font-bold text-slate-800">
                  <History className="text-sky-700" size={19} />
                  Approval and revision history
                </h3>
                <div className="mt-3 grid gap-2">
                  {[...selected.workflowEvents].reverse().map((event) => (
                    <article
                      className="flex gap-3 rounded-lg border border-slate-200 p-3"
                      key={event.id}
                    >
                      <CheckCircle2
                        className="mt-0.5 shrink-0 text-sky-600"
                        size={17}
                      />
                      <div>
                        <strong className="text-sm text-slate-800">
                          {event.action.replaceAll("_", " ")}
                        </strong>
                        <p className="mt-1 text-xs text-slate-500">
                          {event.actor?.name ?? "System"} ·{" "}
                          {formatDateTime(event.createdAt)}
                        </p>
                        {event.comment && (
                          <p className="mt-2 text-sm leading-5 text-slate-600">
                            {event.comment}
                          </p>
                        )}
                      </div>
                    </article>
                  ))}
                </div>
              </section>
            </div>
          )
        )}
      </Modal>

      <Modal
        onClose={() => !saving && setEditorOpen(false)}
        open={editorOpen}
        size="xl"
        title={editing ? `Edit ${editing.planCode}` : "Create strategic plan"}
      >
        <SiapPlanForm
          auditAreas={auditAreas}
          errors={errors}
          key={editing?.id ?? "new-siap"}
          onCancel={() => setEditorOpen(false)}
          onSubmit={persist}
          plan={editing}
          saving={saving}
          users={users}
        />
      </Modal>

      <Modal
        footer={
          <>
            <button
              className="h-10 rounded-lg border px-4 text-sm font-bold"
              onClick={() => setWorkflowAction("")}
              type="button"
            >
              Cancel
            </button>
            <button
              className="h-10 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white disabled:opacity-50"
              disabled={
                saving ||
                (["return", "complete", "revision"].includes(workflowAction) &&
                  !workflowComment.trim()) ||
                (workflowAction === "complete" && !completionConfirmed)
              }
              onClick={performWorkflow}
              type="button"
            >
              {saving ? "Processing..." : workflowLabels[workflowAction]}
            </button>
          </>
        }
        onClose={() => !saving && setWorkflowAction("")}
        open={Boolean(workflowAction)}
        title={workflowLabels[workflowAction] ?? "Update workflow"}
      >
        <div className="grid gap-4">
          <p className="text-sm leading-6 text-slate-600">
            {workflowAction === "revision"
              ? "The approved version will remain unchanged and a new editable draft revision will be created."
              : `Apply “${workflowLabels[workflowAction]}” to ${selected?.planCode}?`}
          </p>
          <label className="grid gap-1.5 text-sm font-bold text-slate-700">
            Comment
            <textarea
              className="min-h-28 rounded-lg border border-slate-300 p-3 font-normal"
              onChange={(event) => setWorkflowComment(event.target.value)}
              placeholder={
                ["return", "complete", "revision"].includes(workflowAction)
                  ? "Required"
                  : "Optional workflow note"
              }
              value={workflowComment}
            />
          </label>
          {workflowAction === "complete" && (
            <label className="flex items-start gap-3 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900">
              <input
                checked={completionConfirmed}
                className="mt-1"
                onChange={(event) =>
                  setCompletionConfirmed(event.target.checked)
                }
                type="checkbox"
              />
              I confirm that the strategic planning period and its formal
              completion requirements have been completed.
            </label>
          )}
        </div>
      </Modal>

      <ConfirmDialog
        busy={saving}
        confirmLabel="Archive plan"
        description={`${archiveTarget?.planCode ?? "This strategic plan"} will be soft-deleted and may be restored later.`}
        onCancel={() => setArchiveTarget(null)}
        onConfirm={archivePlan}
        open={Boolean(archiveTarget)}
        title="Archive strategic plan?"
        tone="danger"
      />
      <ConfirmDialog
        busy={saving}
        confirmLabel="Restore plan"
        description={`${restoreTarget?.planCode ?? "This strategic plan"} will be returned to the registry.`}
        onCancel={() => setRestoreTarget(null)}
        onConfirm={restorePlan}
        open={Boolean(restoreTarget)}
        title="Restore strategic plan?"
      />
    </div>
  );
}
