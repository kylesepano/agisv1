import { useCallback, useEffect, useMemo, useState } from "react";
import {
  AlertTriangle,
  Archive,
  ArrowRight,
  CheckCircle2,
  CopyPlus,
  History,
  LockKeyhole,
  Play,
  Plus,
  RotateCcw,
  Search,
  Send,
  Settings2,
  Trash2,
  Workflow,
  X,
} from "lucide-react";
import { useSearchParams } from "react-router";
import { useAuth } from "../../auth/auth-context";
import ConfirmDialog from "../../components/ui/ConfirmDialog";
import Modal from "../../components/ui/Modal";
import RegistryHeader from "../../components/ui/RegistryHeader";
import SearchableSelect from "../../components/ui/SearchableSelect";
import StatusBadge from "../../components/ui/StatusBadge";
import SummaryCard from "../../components/ui/SummaryCard";
import { hasPermission } from "../../config/navigation";
import { workflowApi } from "../../services/api";
import { useToast } from "../../ui/toast-context";
import useRecordView from "../../hooks/useRecordView";

const inputClass =
  "h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100 disabled:bg-slate-100";
const buttonPrimary =
  "inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white shadow-sm transition hover:bg-sky-800 disabled:cursor-not-allowed disabled:opacity-50";
const buttonSecondary =
  "inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50 disabled:opacity-50";

const blankStep = (index = 0) => ({
  code: index === 0 ? "DRAFT" : "",
  name: index === 0 ? "Draft" : "",
  stepType: index === 0 ? "START" : "INTERMEDIATE",
  responsibleRoleId: "",
  slaHours: "",
  instructions: "",
});

const blankTransition = () => ({
  code: "",
  name: "",
  fromStepCode: "",
  toStepCode: "",
  actorRoleId: "",
  requiredPermissionId: "",
  requiresComment: false,
  enforceSeparationOfDuties: false,
  isActive: true,
});

const emptyDefinition = {
  code: "",
  name: "",
  moduleCode: "CORE",
  subjectType: "",
  description: "",
  steps: [
    blankStep(0),
    {
      ...blankStep(1),
      code: "COMPLETED",
      name: "Completed",
      stepType: "END",
    },
  ],
  transitions: [
    {
      ...blankTransition(),
      code: "COMPLETE",
      name: "Complete",
      fromStepCode: "DRAFT",
      toStepCode: "COMPLETED",
    },
  ],
};

function statusTone(status) {
  return (
    {
      DRAFT: "warning",
      PUBLISHED: "success",
      RETIRED: "inactive",
      ACTIVE: "info",
      COMPLETED: "success",
      CANCELLED: "danger",
    }[status] ?? "info"
  );
}

function dateTime(value) {
  if (!value) return "—";
  return new Intl.DateTimeFormat("en-PH", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(value));
}

function Field({ label, required = false, children }) {
  return (
    <label className="block min-w-0">
      <span className="mb-1.5 block text-sm font-bold text-slate-700">
        {label} {required && <span className="text-red-500">*</span>}
      </span>
      {children}
    </label>
  );
}

/**
 * Configures reusable workflow definitions and operates workflow instances
 * through guarded, auditable transitions and permission-based approval steps.
 */
export default function WorkflowManagementPage() {
  const [searchParams] = useSearchParams();
  const { user } = useAuth();
  const toast = useToast();
  const [data, setData] = useState({
    definitions: [],
    instances: [],
    summary: {},
    options: { modules: [], roles: [], permissions: [], offices: [] },
  });
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [tab, setTab] = useState("definitions");
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("");
  const [moduleCode, setModuleCode] = useState("");
  const [includeArchived, setIncludeArchived] = useState(false);
  const [includeCompleted, setIncludeCompleted] = useState(true);
  const [selectedDefinition, setSelectedDefinition] = useState(null);
  useRecordView(selectedDefinition, {
    module: "CORE",
    recordType: "WORKFLOW_DEFINITION",
    code: (record) => record.code,
    label: (record) => record.name,
  });
  const [definitionEditor, setDefinitionEditor] = useState(null);
  const [definitionForm, setDefinitionForm] = useState(emptyDefinition);
  const [selectedInstance, setSelectedInstance] = useState(null);
  useRecordView(selectedInstance, {
    module: "CORE",
    recordType: "WORKFLOW_INSTANCE",
    code: (record) => record.subjectCode,
    label: (record) => record.subjectLabel,
  });
  const [startOpen, setStartOpen] = useState(false);
  const [startForm, setStartForm] = useState({
    workflowDefinitionId: "",
    moduleCode: "CORE",
    subjectId: "",
    subjectCode: "",
    subjectLabel: "",
    officeId: "",
  });
  const [transition, setTransition] = useState(null);
  const [comment, setComment] = useState("");
  const [confirm, setConfirm] = useState(null);

  const canCreate = hasPermission(user, "workflows.create");
  const canUpdate = hasPermission(user, "workflows.update");
  const canPublish = hasPermission(user, "workflows.publish");
  const canArchive = hasPermission(user, "workflows.archive");
  const canRestore = hasPermission(user, "workflows.restore");
  const canStart = hasPermission(user, "workflows.start");
  const canAct = hasPermission(user, "workflows.act");

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const result = await workflowApi.list({
        includeArchived,
        includeCompleted,
      });
      setData(result);
      const requestedWorkflow = Number(searchParams.get("workflow"));
      const requestedInstance = Number(searchParams.get("instance"));
      if (requestedInstance) {
        const instance = result.instances.find(
          (item) => Number(item.id) === requestedInstance,
        );
        if (instance) {
          setTab("instances");
          setSelectedInstance(instance);
        }
      } else if (requestedWorkflow) {
        const definition = result.definitions.find(
          (item) => Number(item.id) === requestedWorkflow,
        );
        if (definition) {
          setTab("definitions");
          setSelectedDefinition(definition);
        }
      }
      setSelectedDefinition((current) =>
        current
          ? (result.definitions.find((item) => item.id === current.id) ?? null)
          : null,
      );
      setSelectedInstance((current) =>
        current
          ? (result.instances.find((item) => item.id === current.id) ?? null)
          : null,
      );
    } catch (error) {
      toast.error(error.message);
    } finally {
      setLoading(false);
    }
  }, [includeArchived, includeCompleted, searchParams, toast]);

  useEffect(() => {
    const timeoutId = window.setTimeout(load, 0);
    return () => window.clearTimeout(timeoutId);
  }, [load]);

  const records = useMemo(() => {
    const source = tab === "definitions" ? data.definitions : data.instances;
    const needle = search.trim().toLowerCase();
    return source.filter((record) => {
      const text =
        tab === "definitions"
          ? `${record.code} ${record.name} ${record.description} ${record.subjectType}`
          : `${record.subjectCode} ${record.subjectLabel} ${record.definition?.name} ${record.currentStep?.name} ${record.office?.name}`;
      return (
        (!needle || text.toLowerCase().includes(needle)) &&
        (!status || record.status === status) &&
        (!moduleCode || record.moduleCode === moduleCode)
      );
    });
  }, [data.definitions, data.instances, moduleCode, search, status, tab]);

  const roleOptions = data.options.roles.map((role) => ({
    value: role.id,
    label: role.name,
    keywords: role.code,
  }));
  const permissionOptions = data.options.permissions.map((permission) => ({
    value: permission.id,
    label: permission.code,
    keywords: `${permission.name} ${permission.module}`,
  }));

  function openCreate() {
    setDefinitionEditor("create");
    setDefinitionForm(structuredClone(emptyDefinition));
  }

  function openEdit(definition) {
    setDefinitionEditor(definition.id);
    setDefinitionForm({
      code: definition.code,
      name: definition.name,
      moduleCode: definition.moduleCode,
      subjectType: definition.subjectType,
      description: definition.description ?? "",
      steps: definition.steps.map((step) => ({
        code: step.code,
        name: step.name,
        stepType: step.stepType,
        responsibleRoleId: step.responsibleRoleId ?? "",
        slaHours: step.slaHours ?? "",
        instructions: step.instructions ?? "",
      })),
      transitions: definition.transitions.map((item) => ({
        code: item.code,
        name: item.name,
        fromStepCode: item.fromStepCode,
        toStepCode: item.toStepCode,
        actorRoleId: item.actorRoleId ?? "",
        requiredPermissionId: item.requiredPermissionId ?? "",
        requiresComment: item.requiresComment,
        enforceSeparationOfDuties: item.enforceSeparationOfDuties,
        isActive: item.isActive,
      })),
    });
  }

  function updateStep(index, key, value) {
    setDefinitionForm((current) => ({
      ...current,
      steps: current.steps.map((step, position) =>
        position === index ? { ...step, [key]: value } : step,
      ),
    }));
  }

  function updateTransition(index, key, value) {
    setDefinitionForm((current) => ({
      ...current,
      transitions: current.transitions.map((item, position) =>
        position === index ? { ...item, [key]: value } : item,
      ),
    }));
  }

  async function saveDefinition() {
    setBusy(true);
    try {
      const payload = {
        ...definitionForm,
        steps: definitionForm.steps.map((step) => ({
          ...step,
          responsibleRoleId: step.responsibleRoleId
            ? Number(step.responsibleRoleId)
            : null,
          slaHours: step.slaHours ? Number(step.slaHours) : null,
        })),
        transitions: definitionForm.transitions.map((item) => ({
          ...item,
          // Transition authority comes from the required permission, not a
          // legacy actor-role label.
          actorRoleId: null,
          requiredPermissionId: item.requiredPermissionId
            ? Number(item.requiredPermissionId)
            : null,
        })),
      };
      if (definitionEditor === "create") {
        await workflowApi.create(payload);
        toast.success("Workflow draft created.");
      } else {
        await workflowApi.update(definitionEditor, payload);
        toast.success("Workflow draft updated.");
      }
      setDefinitionEditor(null);
      await load();
    } catch (error) {
      toast.error(error.message);
    } finally {
      setBusy(false);
    }
  }

  async function performConfirmed() {
    if (!confirm) return;
    setBusy(true);
    try {
      if (confirm.action === "publish") {
        await workflowApi.publish(confirm.record.id);
        toast.success("Workflow published and locked.");
      } else if (confirm.action === "revision") {
        const revision = await workflowApi.createRevision(confirm.record.id);
        toast.success(`Version ${revision.version} created as a draft.`);
      } else if (confirm.action === "archive") {
        await workflowApi.archive(confirm.record.id);
        toast.success("Workflow archived with its history retained.");
      } else if (confirm.action === "restore") {
        await workflowApi.restore(confirm.record.id);
        toast.success("Workflow restored.");
      }
      setConfirm(null);
      setSelectedDefinition(null);
      await load();
    } catch (error) {
      toast.error(error.message);
    } finally {
      setBusy(false);
    }
  }

  async function startInstance() {
    setBusy(true);
    try {
      await workflowApi.start({
        workflowDefinitionId: startForm.workflowDefinitionId
          ? Number(startForm.workflowDefinitionId)
          : null,
        moduleCode: startForm.workflowDefinitionId
          ? null
          : startForm.moduleCode,
        subjectId: startForm.subjectId ? Number(startForm.subjectId) : null,
        subjectCode: startForm.subjectCode,
        subjectLabel: startForm.subjectLabel,
        officeId: startForm.officeId ? Number(startForm.officeId) : null,
      });
      toast.success("Workflow instance started.");
      setStartOpen(false);
      setStartForm({
        workflowDefinitionId: "",
        moduleCode: "CORE",
        subjectId: "",
        subjectCode: "",
        subjectLabel: "",
        officeId: "",
      });
      setTab("instances");
      await load();
    } catch (error) {
      toast.error(error.message);
    } finally {
      setBusy(false);
    }
  }

  async function performTransition() {
    if (!selectedInstance || !transition) return;
    setBusy(true);
    try {
      const updated =
        transition.code === "CANCEL"
          ? await workflowApi.cancel(selectedInstance.id, {
              lockVersion: selectedInstance.lockVersion,
              comment,
            })
          : await workflowApi.transition(selectedInstance.id, transition.code, {
              lockVersion: selectedInstance.lockVersion,
              comment: comment || null,
            });
      setSelectedInstance(updated);
      setTransition(null);
      setComment("");
      toast.success("Workflow action recorded.");
      await load();
    } catch (error) {
      toast.error(error.message);
    } finally {
      setBusy(false);
    }
  }

  const stepOptions = definitionForm.steps
    .filter((step) => step.code)
    .map((step) => ({
      value: step.code,
      label: `${step.code} — ${step.name}`,
    }));
  const publishedOptions = data.definitions
    .filter(
      (definition) =>
        definition.status === "PUBLISHED" &&
        definition.isActive &&
        !definition.isArchived,
    )
    .map((definition) => ({
      value: definition.id,
      label: `${definition.name} · v${definition.version}`,
      keywords: `${definition.code} ${definition.moduleCode}`,
    }));

  return (
    <div className="p-4 sm:p-6">
      <RegistryHeader
        actions={
          <>
            {canStart && (
              <button
                className={buttonSecondary}
                onClick={() => setStartOpen(true)}
                type="button"
              >
                <Play size={17} /> Start instance
              </button>
            )}
            {canCreate && (
              <button
                className={buttonPrimary}
                onClick={openCreate}
                type="button"
              >
                <Plus size={17} /> Create workflow
              </button>
            )}
          </>
        }
        description="Configure versioned approval routes, permission authorities, deadlines, separation of duties, and immutable execution history."
        icon={Workflow}
        readOnly={!canCreate && !canUpdate}
        title="Workflow Management"
      />

      <div className="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <SummaryCard
          icon={Workflow}
          label="Definitions"
          tone="sky"
          value={data.summary.definitions ?? 0}
        />
        <SummaryCard
          icon={Settings2}
          label="Draft versions"
          tone="amber"
          value={data.summary.drafts ?? 0}
        />
        <SummaryCard
          icon={LockKeyhole}
          label="Published"
          tone="emerald"
          value={data.summary.published ?? 0}
        />
        <SummaryCard
          icon={Play}
          label="Active instances"
          tone="slate"
          value={data.summary.activeInstances ?? 0}
        />
        <SummaryCard
          icon={AlertTriangle}
          label="Overdue"
          tone="red"
          value={data.summary.overdueInstances ?? 0}
        />
      </div>

      <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div className="flex flex-col gap-3 border-b border-slate-200 p-4 xl:flex-row xl:items-center">
          <div className="flex rounded-lg bg-slate-100 p-1">
            {[
              ["definitions", "Definitions"],
              ["instances", "Instances"],
            ].map(([value, label]) => (
              <button
                className={`flex-1 rounded-md px-4 py-2 text-sm font-bold transition ${
                  tab === value
                    ? "bg-white text-sky-700 shadow-sm"
                    : "text-slate-500"
                }`}
                key={value}
                onClick={() => {
                  setTab(value);
                  setStatus("");
                }}
                type="button"
              >
                {label}
              </button>
            ))}
          </div>
          <label className="relative min-w-0 flex-1">
            <Search
              className="absolute left-3 top-3.5 text-slate-400"
              size={17}
            />
            <input
              className={`${inputClass} pl-10`}
              onChange={(event) => setSearch(event.target.value)}
              placeholder={
                tab === "definitions"
                  ? "Search workflow code, name, subject..."
                  : "Search subject, office, step..."
              }
              value={search}
            />
          </label>
          <select
            className={`${inputClass} xl:w-44`}
            onChange={(event) => setModuleCode(event.target.value)}
            value={moduleCode}
          >
            <option value="">All modules</option>
            {data.options.modules.map((module) => (
              <option key={module} value={module}>
                {module}
              </option>
            ))}
          </select>
          <select
            className={`${inputClass} xl:w-48`}
            onChange={(event) => setStatus(event.target.value)}
            value={status}
          >
            <option value="">All statuses</option>
            {(tab === "definitions"
              ? ["DRAFT", "PUBLISHED", "RETIRED"]
              : ["ACTIVE", "COMPLETED", "CANCELLED"]
            ).map((item) => (
              <option key={item}>{item}</option>
            ))}
          </select>
          <label className="flex h-11 items-center gap-2 whitespace-nowrap rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-600">
            <input
              checked={
                tab === "definitions" ? includeArchived : includeCompleted
              }
              onChange={(event) =>
                tab === "definitions"
                  ? setIncludeArchived(event.target.checked)
                  : setIncludeCompleted(event.target.checked)
              }
              type="checkbox"
            />
            {tab === "definitions" ? "Archived" : "Completed"}
          </label>
        </div>

        {loading ? (
          <div className="space-y-3 p-5">
            {[1, 2, 3].map((item) => (
              <div
                className="h-20 animate-pulse rounded-lg bg-slate-100"
                key={item}
              />
            ))}
          </div>
        ) : records.length === 0 ? (
          <div className="grid min-h-64 place-items-center p-6 text-center">
            <div>
              <Workflow className="mx-auto text-slate-300" size={42} />
              <p className="mt-3 font-bold text-slate-700">No records found</p>
              <p className="mt-1 text-sm text-slate-500">
                Adjust the filters or create the first workflow draft.
              </p>
            </div>
          </div>
        ) : tab === "definitions" ? (
          <div className="divide-y divide-slate-200">
            {records.map((definition) => (
              <button
                className="grid w-full gap-3 px-4 py-4 text-left transition hover:bg-sky-50/60 md:grid-cols-[minmax(0,2fr)_8rem_10rem_8rem] md:items-center"
                key={definition.id}
                onClick={() => setSelectedDefinition(definition)}
                type="button"
              >
                <span className="min-w-0">
                  <span className="flex flex-wrap items-center gap-2">
                    <strong className="text-sm text-slate-900">
                      {definition.name}
                    </strong>
                    <span className="rounded bg-slate-100 px-2 py-1 text-xs font-bold text-slate-600">
                      v{definition.version}
                    </span>
                    {definition.isArchived && (
                      <StatusBadge tone="danger">Archived</StatusBadge>
                    )}
                  </span>
                  <small className="mt-1 block text-xs text-slate-500">
                    {definition.code} · {definition.subjectType}
                  </small>
                </span>
                <strong className="text-xs text-sky-700">
                  {definition.moduleCode}
                </strong>
                <span>
                  <StatusBadge tone={statusTone(definition.status)}>
                    {definition.status.replaceAll("_", " ")}
                  </StatusBadge>
                </span>
                <span className="text-xs text-slate-500">
                  {definition.steps.length} steps ·{" "}
                  {definition.activeInstancesCount} active
                </span>
              </button>
            ))}
          </div>
        ) : (
          <div className="divide-y divide-slate-200">
            {records.map((instance) => (
              <button
                className="grid w-full gap-3 px-4 py-4 text-left transition hover:bg-sky-50/60 md:grid-cols-[minmax(0,2fr)_11rem_10rem_10rem] md:items-center"
                key={instance.id}
                onClick={() => setSelectedInstance(instance)}
                type="button"
              >
                <span className="min-w-0">
                  <strong className="block text-sm text-slate-900">
                    {instance.subjectCode} — {instance.subjectLabel}
                  </strong>
                  <small className="mt-1 block text-xs text-slate-500">
                    {instance.definition.name} · v{instance.definition.version}
                  </small>
                </span>
                <span className="text-sm font-semibold text-slate-700">
                  {instance.currentStep.name}
                </span>
                <span>
                  <StatusBadge tone={statusTone(instance.status)}>
                    {instance.status}
                  </StatusBadge>
                  {instance.isOverdue && (
                    <span className="ml-2 text-xs font-bold text-red-600">
                      Overdue
                    </span>
                  )}
                </span>
                <span className="text-xs text-slate-500">
                  Due {dateTime(instance.dueAt)}
                </span>
              </button>
            ))}
          </div>
        )}
      </section>

      <Modal
        footer={
          <>
            <button
              className={buttonSecondary}
              onClick={() => setDefinitionEditor(null)}
              type="button"
            >
              Cancel
            </button>
            <button
              className={buttonPrimary}
              disabled={busy}
              onClick={saveDefinition}
              type="button"
            >
              {busy ? "Saving..." : "Save draft"}
            </button>
          </>
        }
        onClose={() => !busy && setDefinitionEditor(null)}
        open={Boolean(definitionEditor)}
        size="xl"
        title={
          definitionEditor === "create"
            ? "Create workflow definition"
            : "Edit workflow draft"
        }
        description="Define the graph explicitly. Published versions are permanently locked."
      >
        <div className="grid gap-4 md:grid-cols-2">
          <Field label="Workflow family code" required>
            <input
              className={inputClass}
              disabled={definitionEditor !== "create"}
              onChange={(event) =>
                setDefinitionForm((current) => ({
                  ...current,
                  code: event.target.value,
                }))
              }
              placeholder="DOCUMENT_APPROVAL"
              value={definitionForm.code}
            />
          </Field>
          <Field label="Workflow name" required>
            <input
              className={inputClass}
              onChange={(event) =>
                setDefinitionForm((current) => ({
                  ...current,
                  name: event.target.value,
                }))
              }
              value={definitionForm.name}
            />
          </Field>
          <Field label="Module" required>
            <SearchableSelect
              onChange={(value) =>
                setDefinitionForm((current) => ({
                  ...current,
                  moduleCode: value,
                }))
              }
              options={data.options.modules.map((module) => ({
                value: module,
                label: module,
              }))}
              value={definitionForm.moduleCode}
            />
          </Field>
          <Field label="Subject type" required>
            <input
              className={inputClass}
              onChange={(event) =>
                setDefinitionForm((current) => ({
                  ...current,
                  subjectType: event.target.value,
                }))
              }
              placeholder="DOCUMENT"
              value={definitionForm.subjectType}
            />
          </Field>
        </div>
        <Field label="Description">
          <textarea
            className={`${inputClass} mt-4 min-h-24 py-3`}
            onChange={(event) =>
              setDefinitionForm((current) => ({
                ...current,
                description: event.target.value,
              }))
            }
            value={definitionForm.description}
          />
        </Field>

        <div className="mt-6 flex items-center justify-between">
          <div>
            <h3 className="font-bold text-slate-800">Ordered workflow steps</h3>
            <p className="text-xs text-slate-500">
              Use exactly one START and at least one END step.
            </p>
          </div>
          <button
            className={buttonSecondary}
            onClick={() =>
              setDefinitionForm((current) => ({
                ...current,
                steps: [...current.steps, blankStep(current.steps.length)],
              }))
            }
            type="button"
          >
            <Plus size={16} /> Step
          </button>
        </div>
        <div className="mt-3 space-y-3">
          {definitionForm.steps.map((step, index) => (
            <div
              className="grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 lg:grid-cols-[1fr_1.3fr_10rem_1.2fr_7rem_auto]"
              key={`${index}-${step.code}`}
            >
              <input
                className={inputClass}
                onChange={(event) =>
                  updateStep(index, "code", event.target.value)
                }
                placeholder="STEP_CODE"
                value={step.code}
              />
              <input
                className={inputClass}
                onChange={(event) =>
                  updateStep(index, "name", event.target.value)
                }
                placeholder="Step name"
                value={step.name}
              />
              <select
                className={inputClass}
                onChange={(event) =>
                  updateStep(index, "stepType", event.target.value)
                }
                value={step.stepType}
              >
                <option>START</option>
                <option>INTERMEDIATE</option>
                <option>END</option>
              </select>
              <SearchableSelect
                onChange={(value) =>
                  updateStep(index, "responsibleRoleId", value)
                }
                options={roleOptions}
                placeholder="Any responsible role"
                value={step.responsibleRoleId}
              />
              <input
                className={inputClass}
                min="1"
                onChange={(event) =>
                  updateStep(index, "slaHours", event.target.value)
                }
                placeholder="SLA hrs"
                type="number"
                value={step.slaHours}
              />
              <button
                aria-label="Remove step"
                className="grid h-11 w-11 place-items-center rounded-lg text-red-600 hover:bg-red-50"
                disabled={definitionForm.steps.length <= 2}
                onClick={() =>
                  setDefinitionForm((current) => ({
                    ...current,
                    steps: current.steps.filter(
                      (_, position) => position !== index,
                    ),
                  }))
                }
                type="button"
              >
                <Trash2 size={17} />
              </button>
            </div>
          ))}
        </div>

        <div className="mt-6 flex items-center justify-between">
          <div>
            <h3 className="font-bold text-slate-800">Allowed transitions</h3>
            <p className="text-xs text-slate-500">
              Set role, permission, comment, and separation controls per action.
            </p>
          </div>
          <button
            className={buttonSecondary}
            onClick={() =>
              setDefinitionForm((current) => ({
                ...current,
                transitions: [...current.transitions, blankTransition()],
              }))
            }
            type="button"
          >
            <Plus size={16} /> Transition
          </button>
        </div>
        <div className="mt-3 space-y-3">
          {definitionForm.transitions.map((item, index) => (
            <div
              className="rounded-xl border border-slate-200 bg-slate-50 p-3"
              key={`${index}-${item.code}`}
            >
              <div className="grid gap-3 lg:grid-cols-5">
                <input
                  className={inputClass}
                  onChange={(event) =>
                    updateTransition(index, "code", event.target.value)
                  }
                  placeholder="ACTION_CODE"
                  value={item.code}
                />
                <input
                  className={inputClass}
                  onChange={(event) =>
                    updateTransition(index, "name", event.target.value)
                  }
                  placeholder="Action label"
                  value={item.name}
                />
                <SearchableSelect
                  onChange={(value) =>
                    updateTransition(index, "fromStepCode", value)
                  }
                  options={stepOptions}
                  placeholder="From step"
                  value={item.fromStepCode}
                />
                <SearchableSelect
                  onChange={(value) =>
                    updateTransition(index, "toStepCode", value)
                  }
                  options={stepOptions}
                  placeholder="To step"
                  value={item.toStepCode}
                />
                <button
                  className="h-11 rounded-lg text-sm font-bold text-red-600 hover:bg-red-50"
                  disabled={definitionForm.transitions.length <= 1}
                  onClick={() =>
                    setDefinitionForm((current) => ({
                      ...current,
                      transitions: current.transitions.filter(
                        (_, position) => position !== index,
                      ),
                    }))
                  }
                  type="button"
                >
                  Remove
                </button>
              </div>
              <div className="mt-3 grid gap-3 lg:grid-cols-2">
                <SearchableSelect
                  onChange={(value) =>
                    updateTransition(index, "requiredPermissionId", value)
                  }
                  options={permissionOptions}
                  placeholder="Select reviewer/approver permission"
                  value={item.requiredPermissionId}
                />
              </div>
              <p className="mt-2 text-xs text-slate-500">
                The selected permission determines who may perform this
                transition. Role names are not used to authorize workflow
                actions.
              </p>
              <div className="mt-3 flex flex-wrap gap-5 text-sm font-semibold text-slate-600">
                <label className="flex items-center gap-2">
                  <input
                    checked={item.requiresComment}
                    onChange={(event) =>
                      updateTransition(
                        index,
                        "requiresComment",
                        event.target.checked,
                      )
                    }
                    type="checkbox"
                  />
                  Require comment
                </label>
                <label className="flex items-center gap-2">
                  <input
                    checked={item.enforceSeparationOfDuties}
                    onChange={(event) =>
                      updateTransition(
                        index,
                        "enforceSeparationOfDuties",
                        event.target.checked,
                      )
                    }
                    type="checkbox"
                  />
                  Initiator cannot perform action
                </label>
              </div>
            </div>
          ))}
        </div>
      </Modal>

      <Modal
        footer={
          selectedDefinition && (
            <>
              {selectedDefinition.status === "DRAFT" &&
                !selectedDefinition.isArchived &&
                canUpdate && (
                  <button
                    className={buttonSecondary}
                    onClick={() => {
                      openEdit(selectedDefinition);
                      setSelectedDefinition(null);
                    }}
                    type="button"
                  >
                    <Settings2 size={16} /> Edit draft
                  </button>
                )}
              {selectedDefinition.status === "DRAFT" &&
                !selectedDefinition.isArchived &&
                canPublish && (
                  <button
                    className={buttonPrimary}
                    onClick={() =>
                      setConfirm({
                        action: "publish",
                        record: selectedDefinition,
                      })
                    }
                    type="button"
                  >
                    <Send size={16} /> Publish
                  </button>
                )}
              {selectedDefinition.status !== "DRAFT" &&
                !selectedDefinition.isArchived &&
                canCreate && (
                  <button
                    className={buttonPrimary}
                    onClick={() =>
                      setConfirm({
                        action: "revision",
                        record: selectedDefinition,
                      })
                    }
                    type="button"
                  >
                    <CopyPlus size={16} /> New version
                  </button>
                )}
              {!selectedDefinition.isArchived && canArchive && (
                <button
                  className={buttonSecondary}
                  onClick={() =>
                    setConfirm({
                      action: "archive",
                      record: selectedDefinition,
                    })
                  }
                  type="button"
                >
                  <Archive size={16} /> Archive
                </button>
              )}
              {selectedDefinition.isArchived && canRestore && (
                <button
                  className={buttonPrimary}
                  onClick={() =>
                    setConfirm({
                      action: "restore",
                      record: selectedDefinition,
                    })
                  }
                  type="button"
                >
                  <RotateCcw size={16} /> Restore
                </button>
              )}
            </>
          )
        }
        onClose={() => setSelectedDefinition(null)}
        open={Boolean(selectedDefinition)}
        size="xl"
        title={selectedDefinition?.name ?? "Workflow definition"}
        description={
          selectedDefinition
            ? `${selectedDefinition.code} · version ${selectedDefinition.version} · ${selectedDefinition.moduleCode}`
            : ""
        }
      >
        {selectedDefinition && (
          <div>
            <div className="rounded-xl bg-slate-50 p-4">
              <div className="flex flex-wrap gap-2">
                <StatusBadge tone={statusTone(selectedDefinition.status)}>
                  {selectedDefinition.status}
                </StatusBadge>
                {selectedDefinition.isImmutable && (
                  <StatusBadge tone="info">
                    <LockKeyhole className="mr-1" size={13} /> Immutable
                  </StatusBadge>
                )}
              </div>
              <p className="mt-3 text-sm leading-6 text-slate-600">
                {selectedDefinition.description || "No description supplied."}
              </p>
              {selectedDefinition.publishedAt && (
                <p className="mt-2 text-xs text-slate-500">
                  Published {dateTime(selectedDefinition.publishedAt)} by{" "}
                  {selectedDefinition.publishedBy?.name ?? "System"}
                </p>
              )}
            </div>
            <h3 className="mt-5 font-bold text-slate-800">Workflow path</h3>
            <div className="mt-3 flex gap-2 overflow-x-auto pb-3">
              {selectedDefinition.steps.map((step, index) => (
                <div className="flex shrink-0 items-center gap-2" key={step.id}>
                  <div className="w-48 rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                    <span className="text-[10px] font-bold uppercase tracking-wide text-sky-700">
                      {step.stepType}
                    </span>
                    <strong className="mt-1 block text-sm text-slate-800">
                      {step.name}
                    </strong>
                    <small className="mt-1 block text-slate-500">
                      {step.responsibleRole?.name ?? "Any authorized role"}
                      {step.slaHours ? ` · ${step.slaHours}h SLA` : ""}
                    </small>
                  </div>
                  {index < selectedDefinition.steps.length - 1 && (
                    <ArrowRight className="text-slate-400" size={18} />
                  )}
                </div>
              ))}
            </div>
            <h3 className="mt-4 font-bold text-slate-800">
              Transition controls
            </h3>
            <div className="mt-3 grid gap-2 md:grid-cols-2">
              {selectedDefinition.transitions.map((item) => (
                <div
                  className="rounded-lg border border-slate-200 p-3"
                  key={item.id}
                >
                  <strong className="text-sm text-slate-800">
                    {item.name}
                  </strong>
                  <p className="mt-1 text-xs text-slate-500">
                    {item.fromStepCode} → {item.toStepCode}
                  </p>
                  <p className="mt-2 text-xs text-slate-500">
                    {item.authorization?.type ?? "AUTHORIZED ACTOR"} · Permission:{" "}
                    {item.requiredPermission?.code ?? "None"}
                  </p>
                  <p className="mt-1 text-xs text-slate-500">
                    Authorized users ({item.authorization?.userCount ?? 0}):{" "}
                    {item.authorization?.users?.length
                      ? item.authorization.users.map((user) => user.name).join(", ")
                      : item.authorization?.message ?? "No eligible users"}
                  </p>
                  {(item.requiresComment || item.enforceSeparationOfDuties) && (
                    <p className="mt-1 text-xs font-semibold text-amber-700">
                      {item.requiresComment ? "Comment required" : ""}
                      {item.requiresComment && item.enforceSeparationOfDuties
                        ? " · "
                        : ""}
                      {item.enforceSeparationOfDuties
                        ? "Separation of duties"
                        : ""}
                    </p>
                  )}
                </div>
              ))}
            </div>
          </div>
        )}
      </Modal>

      <Modal
        footer={
          <>
            <button
              className={buttonSecondary}
              onClick={() => setStartOpen(false)}
              type="button"
            >
              Cancel
            </button>
            <button
              className={buttonPrimary}
              disabled={
                busy ||
                (!startForm.workflowDefinitionId && !startForm.moduleCode) ||
                !startForm.subjectCode.trim() ||
                !startForm.subjectLabel.trim()
              }
              onClick={startInstance}
              type="button"
            >
              <Play size={16} /> {busy ? "Starting..." : "Start workflow"}
            </button>
          </>
        }
        onClose={() => !busy && setStartOpen(false)}
        open={startOpen}
        title="Start workflow instance"
        description="Attach a published workflow version to a module record. The selected version remains fixed for this instance."
      >
        <div className="grid gap-4">
          <Field label="Published workflow">
            <SearchableSelect
              onChange={(value) =>
                setStartForm((current) => ({
                  ...current,
                  workflowDefinitionId: value,
                }))
              }
              options={publishedOptions}
              placeholder="Use configured module default"
              value={startForm.workflowDefinitionId}
            />
          </Field>
          {!startForm.workflowDefinitionId && (
            <Field label="Module default" required>
              <SearchableSelect
                onChange={(moduleCode) =>
                  setStartForm((current) => ({ ...current, moduleCode }))
                }
                options={[
                  { value: "CORE", label: "AGIS Core default workflow" },
                  { value: "IAP", label: "IAP default workflow" },
                ]}
                value={startForm.moduleCode}
              />
            </Field>
          )}
          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="Record code" required>
              <input
                className={inputClass}
                onChange={(event) =>
                  setStartForm((current) => ({
                    ...current,
                    subjectCode: event.target.value,
                  }))
                }
                placeholder="IAP-2026-001"
                value={startForm.subjectCode}
              />
            </Field>
            <Field label="Record database ID">
              <input
                className={inputClass}
                min="1"
                onChange={(event) =>
                  setStartForm((current) => ({
                    ...current,
                    subjectId: event.target.value,
                  }))
                }
                type="number"
                value={startForm.subjectId}
              />
            </Field>
          </div>
          <Field label="Record title" required>
            <input
              className={inputClass}
              onChange={(event) =>
                setStartForm((current) => ({
                  ...current,
                  subjectLabel: event.target.value,
                }))
              }
              value={startForm.subjectLabel}
            />
          </Field>
          <Field label="Responsible office">
            <SearchableSelect
              onChange={(value) =>
                setStartForm((current) => ({
                  ...current,
                  officeId: value,
                }))
              }
              options={data.options.offices.map((office) => ({
                value: office.id,
                label: `${office.code} — ${office.name}`,
              }))}
              placeholder="Citywide / no office restriction"
              value={startForm.officeId}
            />
          </Field>
        </div>
      </Modal>

      <Modal
        footer={
          selectedInstance?.status === "ACTIVE" &&
          canAct && (
            <>
              {selectedInstance.availableTransitions.map((item) => (
                <button
                  className={buttonPrimary}
                  key={item.code}
                  onClick={() => {
                    setTransition(item);
                    setComment("");
                  }}
                  type="button"
                >
                  <ArrowRight size={16} /> {item.name}
                </button>
              ))}
              <button
                className={buttonSecondary}
                onClick={() => {
                  setTransition({
                    code: "CANCEL",
                    name: "Cancel workflow",
                    requiresComment: true,
                  });
                  setComment("");
                }}
                type="button"
              >
                <X size={16} /> Cancel instance
              </button>
            </>
          )
        }
        onClose={() => setSelectedInstance(null)}
        open={Boolean(selectedInstance)}
        size="lg"
        title={
          selectedInstance
            ? `${selectedInstance.subjectCode} — ${selectedInstance.subjectLabel}`
            : "Workflow instance"
        }
        description={
          selectedInstance
            ? `${selectedInstance.definition.name} · version ${selectedInstance.definition.version}`
            : ""
        }
      >
        {selectedInstance && (
          <div>
            <div className="grid gap-3 rounded-xl bg-slate-50 p-4 sm:grid-cols-3">
              <div>
                <small className="font-bold uppercase text-slate-400">
                  Current step
                </small>
                <strong className="mt-1 block text-slate-800">
                  {selectedInstance.currentStep.name}
                </strong>
              </div>
              <div>
                <small className="font-bold uppercase text-slate-400">
                  Status
                </small>
                <span className="mt-1 block">
                  <StatusBadge tone={statusTone(selectedInstance.status)}>
                    {selectedInstance.status}
                  </StatusBadge>
                </span>
              </div>
              <div>
                <small className="font-bold uppercase text-slate-400">
                  Due date
                </small>
                <strong
                  className={`mt-1 block text-sm ${
                    selectedInstance.isOverdue
                      ? "text-red-600"
                      : "text-slate-700"
                  }`}
                >
                  {dateTime(selectedInstance.dueAt)}
                </strong>
              </div>
            </div>
            {selectedInstance.currentStep.instructions && (
              <p className="mt-3 rounded-lg border border-sky-200 bg-sky-50 p-3 text-sm text-sky-800">
                {selectedInstance.currentStep.instructions}
              </p>
            )}
            {selectedInstance.transitionAuthorities?.length > 0 && (
              <div className="mt-5 rounded-xl border border-emerald-200 bg-emerald-50/60 p-4">
                <h3 className="font-bold text-emerald-900">
                  Permission-based reviewers and approvers
                </h3>
                <div className="mt-3 space-y-3">
                  {selectedInstance.transitionAuthorities.map((item) => (
                    <div key={item.code}>
                      <p className="text-sm font-bold text-slate-800">
                        {item.name} · {item.authorization?.type ?? "AUTHORIZED ACTOR"}
                        {!item.canAct && (
                          <span className="ml-2 text-xs font-normal text-slate-500">
                            (view only)
                          </span>
                        )}
                      </p>
                      <p className="text-xs text-slate-600">
                        Required permission: {item.authorization?.permission?.code ?? "Not configured"}
                      </p>
                      <p className="text-xs text-slate-600">
                        Eligible users ({item.authorization?.userCount ?? 0}):{" "}
                        {item.authorization?.users?.length
                          ? item.authorization.users.map((user) => user.name).join(", ")
                          : item.authorization?.message ?? "No eligible users"}
                      </p>
                    </div>
                  ))}
                </div>
              </div>
            )}
            <h3 className="mt-5 flex items-center gap-2 font-bold text-slate-800">
              <History size={17} /> Immutable history
            </h3>
            <div className="mt-3 space-y-3">
              {selectedInstance.events.map((event) => (
                <div
                  className="flex gap-3 border-l-2 border-sky-200 pl-3"
                  key={event.id}
                >
                  <CheckCircle2
                    className="mt-0.5 shrink-0 text-sky-600"
                    size={17}
                  />
                  <div>
                    <strong className="text-sm text-slate-800">
                      {event.actionCode.replaceAll("_", " ")}
                    </strong>
                    <small className="ml-2 text-slate-500">
                      {event.actor?.name ?? "System"} ·{" "}
                      {dateTime(event.createdAt)}
                    </small>
                    <p className="text-xs text-slate-500">
                      {event.fromStep
                        ? `${event.fromStep} → ${event.toStep}`
                        : `Started at ${event.toStep}`}
                    </p>
                    {event.comment && (
                      <p className="mt-1 text-sm text-slate-600">
                        {event.comment}
                      </p>
                    )}
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}
      </Modal>

      <Modal
        footer={
          <>
            <button
              className={buttonSecondary}
              onClick={() => setTransition(null)}
              type="button"
            >
              Back
            </button>
            <button
              className={buttonPrimary}
              disabled={
                busy || (transition?.requiresComment && !comment.trim())
              }
              onClick={performTransition}
              type="button"
            >
              {busy ? "Processing..." : "Confirm action"}
            </button>
          </>
        }
        onClose={() => !busy && setTransition(null)}
        open={Boolean(transition)}
        title={transition?.name ?? "Workflow action"}
        description="This action, actor, date, comment, and old/new workflow values will be permanently recorded."
      >
        {transition?.authorization && (
          <div className="mb-4 rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-800">
            <p className="font-bold">
              {transition.authorization.type} authority ·{" "}
              {transition.authorization.permission?.code ?? "Permission not configured"}
            </p>
            <p className="mt-1 text-xs">
              Eligible users: {transition.authorization.users?.length
                ? transition.authorization.users.map((user) => user.name).join(", ")
                : transition.authorization.message}
            </p>
          </div>
        )}
        <Field
          label={transition?.requiresComment ? "Required comment" : "Comment"}
          required={transition?.requiresComment}
        >
          <textarea
            className={`${inputClass} min-h-28 py-3`}
            onChange={(event) => setComment(event.target.value)}
            placeholder="Record the basis for this workflow action..."
            value={comment}
          />
        </Field>
      </Modal>

      <ConfirmDialog
        busy={busy}
        confirmLabel={
          {
            publish: "Publish and lock",
            revision: "Create new version",
            archive: "Archive workflow",
            restore: "Restore workflow",
          }[confirm?.action] ?? "Confirm"
        }
        description={
          confirm?.action === "publish"
            ? "Publishing makes this version immutable. Future changes require a formal new version."
            : confirm?.action === "revision"
              ? "The complete graph will be copied to a new editable draft version."
              : confirm?.action === "archive"
                ? "Archiving is blocked while active instances remain. No history will be deleted."
                : "The workflow will return to the registry. A superseded published version remains retired."
        }
        onCancel={() => setConfirm(null)}
        onConfirm={performConfirmed}
        open={Boolean(confirm)}
        title={
          confirm
            ? `${confirm.action[0].toUpperCase()}${confirm.action.slice(1)} ${confirm.record.name}?`
            : "Confirm workflow action"
        }
        tone={confirm?.action === "archive" ? "danger" : "warning"}
      />
    </div>
  );
}
