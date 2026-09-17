import { useCallback, useEffect, useMemo, useState } from "react";
import {
  Archive,
  ArrowDownUp,
  ArrowRight,
  CheckCircle2,
  CirclePause,
  FilterX,
  Gauge,
  History,
  ListChecks,
  LockKeyhole,
  Pencil,
  Plus,
  RotateCcw,
  Search,
  ShieldAlert,
  Target,
  XCircle,
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
import { ApiError, prioritizationApi, riskPeriodApi } from "../../services/api";
import { useToast } from "../../ui/toast-context";
import useRecordView from "../../hooks/useRecordView";

const statusLabels = {
  DRAFT: "Draft",
  PENDING_REVIEW: "Pending Review",
  RETURNED_FOR_REVISION: "Returned for Revision",
  RESUBMITTED: "Resubmitted",
  FINALIZED: "Finalized",
  ARCHIVED: "Archived",
};
const statusTones = {
  DRAFT: "inactive",
  PENDING_REVIEW: "warning",
  RETURNED_FOR_REVISION: "danger",
  RESUBMITTED: "warning",
  FINALIZED: "success",
  ARCHIVED: "danger",
};
const decisionLabels = {
  SELECTED: "Selected",
  DEFERRED: "Deferred",
  NOT_SELECTED: "Not Selected",
};
const decisionClasses = {
  SELECTED: "bg-emerald-100 text-emerald-800 ring-emerald-200",
  DEFERRED: "bg-amber-100 text-amber-800 ring-amber-200",
  NOT_SELECTED: "bg-slate-100 text-slate-700 ring-slate-200",
};
const riskClasses = {
  LOW: "bg-emerald-100 text-emerald-800",
  MEDIUM: "bg-amber-100 text-amber-800",
  HIGH: "bg-orange-100 text-orange-800",
  CRITICAL: "bg-red-100 text-red-800",
};
const inputClass =
  "min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100 disabled:bg-slate-100 disabled:text-slate-500";
const primaryButton =
  "inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-sky-800 disabled:cursor-not-allowed disabled:opacity-50";
const secondaryButton =
  "inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50";

function Field({ label, required, error, children }) {
  return (
    <label className="block text-sm font-semibold text-slate-700">
      {label}
      {required && <span className="text-red-500"> *</span>}
      <span className="mt-1.5 block">{children}</span>
      {error && (
        <small className="mt-1 block text-red-600">
          {Array.isArray(error) ? error[0] : error}
        </small>
      )}
    </label>
  );
}

function DecisionBadge({ decision }) {
  return (
    <span
      className={`inline-flex rounded-full px-2.5 py-1 text-xs font-bold ring-1 ${decisionClasses[decision] ?? decisionClasses.NOT_SELECTED}`}
    >
      {decisionLabels[decision] ?? decision}
    </span>
  );
}

function RiskBadge({ code, score }) {
  return (
    <span className="inline-flex items-center gap-2">
      <strong className="text-slate-900">{Number(score).toFixed(2)}</strong>
      <small
        className={`rounded-full px-2 py-0.5 text-[10px] font-bold ${riskClasses[code] ?? "bg-slate-100 text-slate-700"}`}
      >
        {code}
      </small>
    </span>
  );
}

function formatDateTime(value) {
  if (!value) return "—";
  return new Intl.DateTimeFormat("en-PH", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(value));
}

function RunForm({
  periods,
  existingRuns,
  initial,
  errors,
  saving,
  onCancel,
  onSubmit,
}) {
  const [form, setForm] = useState({
    runCode: initial?.runCode ?? "",
    name: initial?.name ?? "",
    riskPeriodId: initial?.riskPeriodId ?? "",
    methodology:
      initial?.methodology ??
      "Residual risk is converted to a 0–100 priority score. Subjects are ranked by residual risk, inherent risk, and subject name. High and Critical subjects are recommended for selection; Medium subjects are recommended for deferral; Low subjects are not selected.",
    lockVersion: initial?.lockVersion,
  });
  const eligible = periods.filter(
    (period) =>
      ["VALIDATED", "LOCKED"].includes(period.status) &&
      (period.id === initial?.riskPeriodId ||
        !existingRuns.some(
          (run) => !run.isArchived && run.riskPeriodId === period.id,
        )),
  );

  return (
    <form
      className="space-y-5"
      onSubmit={(event) => {
        event.preventDefault();
        onSubmit({
          ...form,
          riskPeriodId: Number(form.riskPeriodId),
        });
      }}
    >
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="Run code" error={errors.runCode}>
          <input
            className={inputClass}
            onChange={(event) =>
              setForm({ ...form, runCode: event.target.value })
            }
            placeholder="PRIO-2027"
            value={form.runCode}
          />
        </Field>
        <Field label="Source risk period" required error={errors.riskPeriodId}>
          <SearchableSelect
            disabled={Boolean(initial)}
            options={eligible.map((period) => ({
              value: period.id,
              label: `${period.periodCode} — ${period.name}`,
              description: `${period.assessmentYear} · ${statusLabels[period.status] ?? period.status}`,
            }))}
            value={form.riskPeriodId}
            onChange={(riskPeriodId) => setForm({ ...form, riskPeriodId })}
            placeholder="Select a validated or locked period..."
          />
        </Field>
      </div>
      <Field label="Prioritization name" required error={errors.name}>
        <input
          className={inputClass}
          onChange={(event) => setForm({ ...form, name: event.target.value })}
          placeholder="2027 Audit Universe Prioritization"
          value={form.name}
        />
      </Field>
      <Field label="Ranking methodology" required error={errors.methodology}>
        <textarea
          className={`${inputClass} min-h-32 py-3`}
          onChange={(event) =>
            setForm({ ...form, methodology: event.target.value })
          }
          value={form.methodology}
        />
      </Field>
      <div className="rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm leading-6 text-sky-900">
        Creating the run copies every validated assessment into an immutable
        ranking snapshot. Priority score = residual risk × 20.
      </div>
      <div className="flex justify-end gap-2">
        <button className={secondaryButton} onClick={onCancel} type="button">
          Cancel
        </button>
        <button
          className={primaryButton}
          disabled={saving || (!initial && !form.riskPeriodId)}
          type="submit"
        >
          {saving
            ? "Saving..."
            : initial
              ? "Update prioritization"
              : "Generate ranking"}
        </button>
      </div>
    </form>
  );
}

function ItemDecisionForm({
  item,
  itemCount,
  editable,
  errors,
  saving,
  onCancel,
  onSubmit,
}) {
  const [form, setForm] = useState({
    finalRank: item.finalRank,
    decision: item.decision,
    decisionReason: item.decisionReason ?? "",
    overrideReason: item.overrideReason ?? "",
    lockVersion: item.lockVersion,
  });
  const rankOverride = Number(form.finalRank) !== item.systemRank;
  const decisionOverride = form.decision !== item.recommendedDecision;
  const reasonRequired =
    decisionOverride || ["DEFERRED", "NOT_SELECTED"].includes(form.decision);

  return (
    <form
      className="space-y-5"
      onSubmit={(event) => {
        event.preventDefault();
        onSubmit({ ...form, finalRank: Number(form.finalRank) });
      }}
    >
      <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <span>
            <strong className="block text-base text-slate-900">
              {item.subjectName}
            </strong>
            <small className="text-slate-500">
              {item.subjectCode} · {item.officeCode} · {item.auditAreaName}
            </small>
          </span>
          <RiskBadge code={item.riskLevelCode} score={item.residualRiskScore} />
        </div>
      </div>
      <div className="grid gap-3 sm:grid-cols-4">
        {[
          ["Inherent risk", item.inherentRiskScore],
          ["Control effectiveness", `${item.controlEffectivenessPercent}%`],
          ["Residual risk", item.residualRiskScore],
          ["Priority score", `${item.priorityScore}/100`],
        ].map(([label, value]) => (
          <div className="rounded-xl border border-slate-200 p-3" key={label}>
            <strong className="block text-xl text-slate-900">{value}</strong>
            <small className="font-semibold uppercase tracking-wide text-slate-500">
              {label}
            </small>
          </div>
        ))}
      </div>
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="System rank">
          <input className={inputClass} disabled value={item.systemRank} />
        </Field>
        <Field label="Final rank" required error={errors.finalRank}>
          <input
            className={inputClass}
            disabled={!editable}
            max={itemCount}
            min="1"
            onChange={(event) =>
              setForm({ ...form, finalRank: event.target.value })
            }
            type="number"
            value={form.finalRank}
          />
        </Field>
      </div>
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="System recommendation">
          <div className="flex min-h-11 items-center rounded-lg border border-slate-200 bg-slate-50 px-3">
            <DecisionBadge decision={item.recommendedDecision} />
          </div>
        </Field>
        <Field label="Final decision" required error={errors.decision}>
          <select
            className={inputClass}
            disabled={!editable}
            onChange={(event) =>
              setForm({ ...form, decision: event.target.value })
            }
            value={form.decision}
          >
            {Object.entries(decisionLabels).map(([value, label]) => (
              <option key={value} value={value}>
                {label}
              </option>
            ))}
          </select>
        </Field>
      </div>
      <Field
        label="Decision reason"
        required={reasonRequired}
        error={errors.decisionReason}
      >
        <textarea
          className={`${inputClass} min-h-24 py-3`}
          disabled={!editable}
          onChange={(event) =>
            setForm({ ...form, decisionReason: event.target.value })
          }
          placeholder={
            reasonRequired
              ? "Explain the deferral, non-selection, or decision override..."
              : "Optional note supporting the selection..."
          }
          value={form.decisionReason}
        />
      </Field>
      <Field
        label="Manual ranking override reason"
        required={rankOverride}
        error={errors.overrideReason}
      >
        <textarea
          className={`${inputClass} min-h-24 py-3`}
          disabled={!editable}
          onChange={(event) =>
            setForm({ ...form, overrideReason: event.target.value })
          }
          placeholder="Required when final rank differs from system rank..."
          value={form.overrideReason}
        />
      </Field>
      {(rankOverride || decisionOverride) && (
        <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm font-semibold text-amber-900">
          This is a manual prioritization override and will be recorded in the
          permanent audit trail.
        </div>
      )}
      <div className="flex justify-end gap-2">
        <button className={secondaryButton} onClick={onCancel} type="button">
          {editable ? "Cancel" : "Close"}
        </button>
        {editable && (
          <button className={primaryButton} disabled={saving} type="submit">
            {saving ? "Saving..." : "Save decision"}
          </button>
        )}
      </div>
    </form>
  );
}

/**
 * Converts validated risk assessments into ranked, reviewable selection
 * decisions that can be imported by an Annual Internal Audit Plan.
 */
export default function IapPrioritizationPage() {
  const { user } = useAuth();
  const toast = useToast();
  const [runs, setRuns] = useState([]);
  const [riskPeriods, setRiskPeriods] = useState([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState("");
  const [selected, setSelected] = useState(null);
  useRecordView(selected, {
    module: "IAP",
    recordType: "PRIORITIZATION",
    code: (record) => record.runCode,
    label: (record) => record.name,
  });
  const [editor, setEditor] = useState(null);
  const [itemEditor, setItemEditor] = useState(null);
  const [itemSearch, setItemSearch] = useState("");
  const [riskFilter, setRiskFilter] = useState("");
  const [decisionFilter, setDecisionFilter] = useState("");
  const [workflow, setWorkflow] = useState("");
  const [workflowComment, setWorkflowComment] = useState("");
  const [archiveTarget, setArchiveTarget] = useState(null);
  const [restoreTarget, setRestoreTarget] = useState(null);
  const [errors, setErrors] = useState({});

  const canCreate = hasPermission(user, "iap.create");
  const canUpdate = hasPermission(user, "iap.update");
  const canArchive = hasPermission(user, "iap.archive");
  const canRestore = hasPermission(user, "iap.restore");
  const canWorkItem =
    canUpdate &&
    selected &&
    ["DRAFT", "RETURNED_FOR_REVISION"].includes(selected.status);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const [{ prioritizations }, periods] = await Promise.all([
        prioritizationApi.list({
          includeArchived: canRestore ? 1 : 0,
          perPage: 100,
        }),
        canCreate
          ? riskPeriodApi
              .list({ perPage: 100 })
              .then((result) => result.riskPeriods)
          : Promise.resolve([]),
      ]);
      setRuns(prioritizations);
      setRiskPeriods(periods);
    } catch (error) {
      toast.error(
        error instanceof Error
          ? error.message
          : "Unable to load Audit Prioritization.",
      );
    } finally {
      setLoading(false);
    }
  }, [canCreate, canRestore, toast]);

  useEffect(() => {
    const timer = window.setTimeout(load, 0);
    return () => window.clearTimeout(timer);
  }, [load]);

  const filteredRuns = useMemo(() => {
    const needle = search.trim().toLowerCase();
    return runs.filter((run) => {
      const displayStatus = run.isArchived ? "ARCHIVED" : run.status;
      return (
        (!statusFilter || displayStatus === statusFilter) &&
        (!needle ||
          [
            run.runCode,
            run.name,
            run.riskPeriod?.periodCode,
            run.riskPeriod?.name,
          ].some((value) =>
            String(value ?? "")
              .toLowerCase()
              .includes(needle),
          ))
      );
    });
  }, [runs, search, statusFilter]);

  const filteredItems = useMemo(() => {
    if (!selected) return [];
    const needle = itemSearch.trim().toLowerCase();
    return selected.items.filter((item) => {
      const matchesRisk =
        !riskFilter ||
        (riskFilter === "HIGH_CRITICAL"
          ? ["HIGH", "CRITICAL"].includes(item.riskLevelCode)
          : item.riskLevelCode === riskFilter);
      return (
        matchesRisk &&
        (!decisionFilter || item.decision === decisionFilter) &&
        (!needle ||
          [
            item.subjectCode,
            item.subjectName,
            item.officeCode,
            item.officeName,
            item.auditAreaCode,
            item.auditAreaName,
          ].some((value) =>
            String(value ?? "")
              .toLowerCase()
              .includes(needle),
          ))
      );
    });
  }, [decisionFilter, itemSearch, riskFilter, selected]);

  async function openRun(run) {
    try {
      setSelected(await prioritizationApi.show(run.id));
      setItemSearch("");
      setRiskFilter("");
      setDecisionFilter("");
    } catch (error) {
      toast.error(error.message);
    }
  }

  async function saveRun(payload) {
    setSaving(true);
    setErrors({});
    try {
      const result = editor?.id
        ? await prioritizationApi.update(editor.id, payload)
        : await prioritizationApi.create(payload);
      toast.success(
        editor?.id
          ? "Prioritization run updated."
          : "Ranking generated from the validated risk period.",
      );
      setEditor(null);
      setSelected(result);
      await load();
    } catch (error) {
      setErrors(error instanceof ApiError ? error.errors : {});
      toast.error(error.message ?? "Unable to save prioritization.");
    } finally {
      setSaving(false);
    }
  }

  async function saveItem(payload) {
    setSaving(true);
    setErrors({});
    try {
      const updated = await prioritizationApi.updateItem(
        selected.id,
        itemEditor.id,
        payload,
      );
      setSelected(updated);
      setItemEditor(null);
      toast.success("Prioritization decision and ranking saved.");
      await load();
    } catch (error) {
      setErrors(error instanceof ApiError ? error.errors : {});
      toast.error(error.message ?? "Unable to save prioritization decision.");
    } finally {
      setSaving(false);
    }
  }

  async function performWorkflow() {
    setSaving(true);
    try {
      const updated = await prioritizationApi.transition(
        selected.id,
        workflow,
        {
          lockVersion: selected.lockVersion,
          comment: workflowComment,
        },
      );
      setSelected(updated);
      setWorkflow("");
      setWorkflowComment("");
      toast.success("Prioritization workflow updated.");
      await load();
    } catch (error) {
      toast.error(error.message);
    } finally {
      setSaving(false);
    }
  }

  const workflowActions = selected
    ? [
        selected.status === "DRAFT" &&
          hasPermission(user, "iap.submit") && ["submit", "Submit for review"],
        selected.status === "RETURNED_FOR_REVISION" &&
          hasPermission(user, "iap.submit") && ["resubmit", "Resubmit"],
        ["PENDING_REVIEW", "RESUBMITTED"].includes(selected.status) &&
          hasPermission(user, "iap.review") && [
            "return",
            "Return for revision",
          ],
        ["PENDING_REVIEW", "RESUBMITTED"].includes(selected.status) &&
          hasPermission(user, "iap.approve") && [
            "finalize",
            "Finalize ranking",
          ],
      ].filter(Boolean)
    : [];

  const missingReasons =
    selected?.items.filter(
      (item) =>
        ((["DEFERRED", "NOT_SELECTED"].includes(item.decision) ||
          item.decision !== item.recommendedDecision) &&
          !item.decisionReason) ||
        (item.isManualOverride && !item.overrideReason),
    ).length ?? 0;

  const columns = [
    {
      key: "runCode",
      label: "Run",
      render: (run) => (
        <span>
          <strong className="block text-slate-900">{run.runCode}</strong>
          <small className="text-slate-500">
            {run.riskPeriod?.assessmentYear}
          </small>
        </span>
      ),
    },
    {
      key: "name",
      label: "Prioritization",
      render: (run) => (
        <span>
          <strong className="block text-slate-800">{run.name}</strong>
          <small className="text-slate-500">
            Source: {run.riskPeriod?.periodCode}
          </small>
        </span>
      ),
    },
    {
      key: "itemCount",
      label: "Ranked subjects",
      render: (run) => <strong>{run.itemCount ?? 0}</strong>,
    },
    {
      key: "status",
      label: "Status",
      render: (run) => {
        const displayStatus = run.isArchived ? "ARCHIVED" : run.status;
        return (
          <StatusBadge tone={statusTones[displayStatus]}>
            {statusLabels[displayStatus]}
          </StatusBadge>
        );
      },
    },
    {
      key: "actions",
      label: "Actions",
      sortable: false,
      render: (run) => (
        <div
          className="flex gap-1"
          onClick={(event) => event.stopPropagation()}
        >
          {canCreate && run.status === "DRAFT" && !run.isArchived && (
            <button
              className="rounded-lg p-2 text-sky-700 hover:bg-sky-50"
              onClick={() => {
                setErrors({});
                setEditor(run);
              }}
              title="Edit prioritization"
              type="button"
            >
              <Pencil size={17} />
            </button>
          )}
          {canArchive &&
            ["DRAFT", "FINALIZED"].includes(run.status) &&
            !run.isArchived && (
              <button
                className="rounded-lg p-2 text-red-600 hover:bg-red-50"
                onClick={() => setArchiveTarget(run)}
                title="Archive prioritization"
                type="button"
              >
                <Archive size={17} />
              </button>
            )}
          {canRestore && run.isArchived && (
            <button
              className="rounded-lg p-2 text-emerald-700 hover:bg-emerald-50"
              onClick={() => setRestoreTarget(run)}
              title="Restore prioritization"
              type="button"
            >
              <RotateCcw size={17} />
            </button>
          )}
        </div>
      ),
    },
  ];

  return (
    <main className="min-w-0 p-4 sm:p-5">
      <RegistryHeader
        actions={
          canCreate && (
            <button
              className={primaryButton}
              onClick={() => {
                setErrors({});
                setEditor({});
              }}
              type="button"
            >
              <Plus size={18} /> Generate prioritization
            </button>
          )
        }
        description="Convert validated Audit Universe risk assessments into a repeatable ranked list for annual audit-plan selection."
        icon={ListChecks}
        readOnly={!canUpdate}
        title="Audit Prioritization"
      />
      <section className="mb-5 grid grid-cols-2 gap-3 xl:grid-cols-4">
        <SummaryCard
          icon={ListChecks}
          label="Prioritization runs"
          tone="sky"
          value={runs.filter((run) => !run.isArchived).length}
        />
        <SummaryCard
          icon={Target}
          label="Draft rankings"
          tone="amber"
          value={
            runs.filter((run) => run.status === "DRAFT" && !run.isArchived)
              .length
          }
        />
        <SummaryCard
          icon={LockKeyhole}
          label="Finalized rankings"
          tone="emerald"
          value={
            runs.filter((run) => run.status === "FINALIZED" && !run.isArchived)
              .length
          }
        />
        <SummaryCard
          icon={ShieldAlert}
          label="Eligible risk periods"
          tone="slate"
          value={
            riskPeriods.filter(
              (period) =>
                ["VALIDATED", "LOCKED"].includes(period.status) &&
                !runs.some(
                  (run) => !run.isArchived && run.riskPeriodId === period.id,
                ),
            ).length
          }
        />
      </section>
      <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div className="grid gap-3 border-b border-slate-200 p-3 sm:p-4 lg:grid-cols-[minmax(16rem,1fr)_15rem_auto]">
          <label className="flex min-h-11 items-center gap-2 rounded-lg border border-slate-300 px-3">
            <Search className="text-slate-400" size={17} />
            <input
              className="min-w-0 flex-1 text-sm outline-none"
              onChange={(event) => setSearch(event.target.value)}
              placeholder="Search run, source period, or title..."
              value={search}
            />
          </label>
          <select
            className={inputClass}
            onChange={(event) => setStatusFilter(event.target.value)}
            value={statusFilter}
          >
            <option value="">All statuses</option>
            {Object.entries(statusLabels).map(([value, label]) => (
              <option key={value} value={value}>
                {label}
              </option>
            ))}
          </select>
          <button
            className={secondaryButton}
            onClick={() => {
              setSearch("");
              setStatusFilter("");
            }}
            type="button"
          >
            <FilterX size={17} /> Clear filters
          </button>
        </div>
        <DataTable
          columns={columns}
          emptyMessage="No prioritization runs match the filters."
          loading={loading}
          onRowClick={openRun}
          rows={filteredRuns}
        />
      </section>

      <Modal
        description="Select the validated risk-assessment cycle that will become the immutable source snapshot."
        onClose={() => setEditor(null)}
        open={Boolean(editor)}
        size="lg"
        title={editor?.id ? "Edit prioritization run" : "Generate ranking"}
      >
        {editor && (
          <RunForm
            errors={errors}
            existingRuns={runs}
            initial={editor.id ? editor : null}
            onCancel={() => setEditor(null)}
            onSubmit={saveRun}
            periods={riskPeriods}
            saving={saving}
          />
        )}
      </Modal>

      <Modal
        description={
          selected
            ? `${selected.runCode} · Source ${selected.riskPeriod?.periodCode}`
            : ""
        }
        onClose={() => setSelected(null)}
        open={Boolean(selected)}
        size="xl"
        title={selected?.name ?? "Audit Prioritization"}
      >
        {selected && (
          <div className="space-y-5">
            <div className="grid gap-3 lg:grid-cols-[1fr_auto]">
              <div className="rounded-xl bg-slate-50 p-4">
                <div className="flex flex-wrap items-center gap-2">
                  <StatusBadge tone={statusTones[selected.status]}>
                    {statusLabels[selected.status]}
                  </StatusBadge>
                  <span className="text-sm text-slate-500">
                    {selected.items.length} ranked Audit Universe subjects
                  </span>
                </div>
                <p className="mt-3 text-sm leading-6 text-slate-600">
                  {selected.methodology}
                </p>
              </div>
              <div className="flex flex-wrap content-start justify-end gap-2">
                {workflowActions.map(([action, label]) => (
                  <button
                    className={
                      action === "return" ? secondaryButton : primaryButton
                    }
                    key={action}
                    onClick={() => {
                      setWorkflowComment("");
                      setWorkflow(action);
                    }}
                    type="button"
                  >
                    {label} <ArrowRight size={16} />
                  </button>
                ))}
              </div>
            </div>
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
              {[
                [
                  "Selected",
                  selected.items.filter((item) => item.decision === "SELECTED")
                    .length,
                  CheckCircle2,
                  "text-emerald-700",
                ],
                [
                  "Deferred",
                  selected.items.filter((item) => item.decision === "DEFERRED")
                    .length,
                  CirclePause,
                  "text-amber-700",
                ],
                [
                  "Not selected",
                  selected.items.filter(
                    (item) => item.decision === "NOT_SELECTED",
                  ).length,
                  XCircle,
                  "text-slate-600",
                ],
                [
                  "High / Critical",
                  selected.items.filter((item) =>
                    ["HIGH", "CRITICAL"].includes(item.riskLevelCode),
                  ).length,
                  ShieldAlert,
                  "text-red-600",
                ],
                [
                  "Reasons required",
                  missingReasons,
                  Gauge,
                  missingReasons ? "text-red-600" : "text-emerald-700",
                ],
              ].map(([label, value, Icon, tone]) => (
                <div
                  className="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-3"
                  key={label}
                >
                  <Icon className={tone} size={21} />
                  <span>
                    <strong className="block text-xl text-slate-900">
                      {value}
                    </strong>
                    <small className="font-semibold text-slate-500">
                      {label}
                    </small>
                  </span>
                </div>
              ))}
            </div>
            <section className="overflow-hidden rounded-xl border border-slate-200">
              <div className="grid gap-3 border-b border-slate-200 bg-slate-50 p-3 lg:grid-cols-[1fr_13rem_13rem_auto]">
                <label className="flex min-h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3">
                  <Search className="text-slate-400" size={16} />
                  <input
                    className="min-w-0 flex-1 text-sm outline-none"
                    onChange={(event) => setItemSearch(event.target.value)}
                    placeholder="Search subject, office, or audit area..."
                    value={itemSearch}
                  />
                </label>
                <select
                  className={inputClass}
                  onChange={(event) => setRiskFilter(event.target.value)}
                  value={riskFilter}
                >
                  <option value="">All risk levels</option>
                  <option value="HIGH_CRITICAL">High & Critical</option>
                  <option value="CRITICAL">Critical only</option>
                  <option value="HIGH">High only</option>
                  <option value="MEDIUM">Medium only</option>
                  <option value="LOW">Low only</option>
                </select>
                <select
                  className={inputClass}
                  onChange={(event) => setDecisionFilter(event.target.value)}
                  value={decisionFilter}
                >
                  <option value="">All decisions</option>
                  {Object.entries(decisionLabels).map(([value, label]) => (
                    <option key={value} value={value}>
                      {label}
                    </option>
                  ))}
                </select>
                <button
                  className={secondaryButton}
                  onClick={() => {
                    setItemSearch("");
                    setRiskFilter("");
                    setDecisionFilter("");
                  }}
                  type="button"
                >
                  <FilterX size={16} /> Clear
                </button>
              </div>
              <DataTable
                columns={[
                  {
                    key: "finalRank",
                    label: "Rank",
                    render: (item) => (
                      <span className="inline-flex h-9 min-w-9 items-center justify-center rounded-full bg-sky-100 px-2 font-black text-sky-800">
                        {item.finalRank}
                      </span>
                    ),
                  },
                  {
                    key: "subjectName",
                    label: "Auditable subject",
                    render: (item) => (
                      <span>
                        <strong className="block text-slate-900">
                          {item.subjectName}
                        </strong>
                        <small className="text-slate-500">
                          {item.subjectCode} · {item.officeCode} ·{" "}
                          {item.auditAreaCode}
                        </small>
                      </span>
                    ),
                  },
                  {
                    key: "inherentRiskScore",
                    label: "Inherent",
                    render: (item) => item.inherentRiskScore.toFixed(2),
                  },
                  {
                    key: "controlEffectivenessPercent",
                    label: "Control",
                    render: (item) => `${item.controlEffectivenessPercent}%`,
                  },
                  {
                    key: "residualRiskScore",
                    label: "Residual risk",
                    render: (item) => (
                      <RiskBadge
                        code={item.riskLevelCode}
                        score={item.residualRiskScore}
                      />
                    ),
                  },
                  {
                    key: "priorityScore",
                    label: "Priority",
                    render: (item) => (
                      <strong className="text-sky-800">
                        {item.priorityScore.toFixed(1)}
                      </strong>
                    ),
                  },
                  {
                    key: "decision",
                    label: "Decision",
                    render: (item) => (
                      <span>
                        <DecisionBadge decision={item.decision} />
                        {item.isManualOverride && (
                          <small className="mt-1 block font-semibold text-amber-700">
                            Manual override
                          </small>
                        )}
                      </span>
                    ),
                  },
                ]}
                emptyMessage="No ranked subjects match the filters."
                onRowClick={(item) => {
                  setErrors({});
                  setItemEditor(item);
                }}
                rows={filteredItems}
              />
            </section>
            <section className="rounded-xl border border-slate-200 p-4">
              <h3 className="flex items-center gap-2 font-bold text-slate-800">
                <ArrowDownUp size={18} /> Ranking method
              </h3>
              <div className="mt-3 grid gap-3 md:grid-cols-3">
                <div className="rounded-lg bg-slate-50 p-3 text-sm text-slate-600">
                  <strong className="block text-slate-800">
                    1. Priority score
                  </strong>
                  Residual risk × 20 produces a normalized score from 0 to 100.
                </div>
                <div className="rounded-lg bg-slate-50 p-3 text-sm text-slate-600">
                  <strong className="block text-slate-800">
                    2. Tie breaking
                  </strong>
                  Residual risk, inherent risk, then Audit Universe subject
                  name.
                </div>
                <div className="rounded-lg bg-slate-50 p-3 text-sm text-slate-600">
                  <strong className="block text-slate-800">3. Overrides</strong>
                  Final rank or decision changes require a documented reason.
                </div>
              </div>
            </section>
            <section className="rounded-xl border border-slate-200 p-4">
              <h3 className="mb-3 flex items-center gap-2 font-bold text-slate-800">
                <History size={18} /> Workflow history
              </h3>
              <div className="space-y-3">
                {selected.events.map((event) => (
                  <div
                    className="flex gap-3 border-l-2 border-sky-200 pl-3"
                    key={event.id}
                  >
                    <CheckCircle2
                      className="mt-0.5 shrink-0 text-sky-600"
                      size={17}
                    />
                    <span>
                      <strong className="text-sm text-slate-800">
                        {event.action.replaceAll("_", " ")}
                      </strong>
                      <small className="ml-2 text-slate-500">
                        {event.actor?.name} · {formatDateTime(event.createdAt)}
                      </small>
                      {event.comment && (
                        <p className="mt-1 text-sm text-slate-600">
                          {event.comment}
                        </p>
                      )}
                    </span>
                  </div>
                ))}
              </div>
            </section>
          </div>
        )}
      </Modal>

      <Modal
        description="Compare the source risk components, system recommendation, and final planning decision."
        onClose={() => setItemEditor(null)}
        open={Boolean(itemEditor)}
        size="lg"
        title="Prioritization decision"
      >
        {itemEditor && selected && (
          <ItemDecisionForm
            editable={Boolean(canWorkItem)}
            errors={errors}
            item={itemEditor}
            itemCount={selected.items.length}
            onCancel={() => setItemEditor(null)}
            onSubmit={saveItem}
            saving={saving}
          />
        )}
      </Modal>

      <Modal
        description="This controlled action will be preserved in the prioritization workflow history and audit trail."
        footer={
          <>
            <button
              className={secondaryButton}
              onClick={() => setWorkflow("")}
              type="button"
            >
              Cancel
            </button>
            <button
              className={primaryButton}
              disabled={
                saving ||
                (workflow === "return" && !workflowComment.trim()) ||
                (["submit", "resubmit"].includes(workflow) &&
                  missingReasons > 0)
              }
              onClick={performWorkflow}
              type="button"
            >
              {saving ? "Please wait..." : "Confirm action"}
            </button>
          </>
        }
        onClose={() => setWorkflow("")}
        open={Boolean(workflow)}
        title={
          workflowActions.find(([action]) => action === workflow)?.[1] ??
          "Update workflow"
        }
      >
        {["submit", "resubmit"].includes(workflow) && missingReasons > 0 && (
          <div className="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-800">
            Resolve {missingReasons} missing decision or override reason(s)
            before submission.
          </div>
        )}
        <Field
          label={workflow === "return" ? "Return reason" : "Workflow comment"}
          required={workflow === "return"}
        >
          <textarea
            className={`${inputClass} min-h-28 py-3`}
            onChange={(event) => setWorkflowComment(event.target.value)}
            placeholder="Record the basis for this workflow decision..."
            value={workflowComment}
          />
        </Field>
      </Modal>

      <ConfirmDialog
        busy={saving}
        confirmLabel="Archive prioritization"
        description="The finalized or draft ranking remains recoverable and can be restored later."
        onCancel={() => setArchiveTarget(null)}
        onConfirm={async () => {
          setSaving(true);
          try {
            await prioritizationApi.archive(archiveTarget.id);
            setArchiveTarget(null);
            toast.success("Audit prioritization archived.");
            await load();
          } catch (error) {
            toast.error(error.message);
          } finally {
            setSaving(false);
          }
        }}
        open={Boolean(archiveTarget)}
        title="Archive audit prioritization?"
        tone="danger"
      />
      <ConfirmDialog
        busy={saving}
        confirmLabel="Restore prioritization"
        description="The archived ranking will become available again."
        onCancel={() => setRestoreTarget(null)}
        onConfirm={async () => {
          setSaving(true);
          try {
            await prioritizationApi.restore(restoreTarget.id);
            setRestoreTarget(null);
            toast.success("Audit prioritization restored.");
            await load();
          } catch (error) {
            toast.error(error.message);
          } finally {
            setSaving(false);
          }
        }}
        open={Boolean(restoreTarget)}
        title="Restore audit prioritization?"
      />
    </main>
  );
}
