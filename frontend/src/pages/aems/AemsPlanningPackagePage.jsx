import { useCallback, useEffect, useMemo, useState } from "react";
import {
  BadgeCheck,
  Check,
  CheckCircle2,
  ClipboardCheck,
  FileCheck2,
  FilePenLine,
  GitCompareArrows,
  History,
  Layers3,
  Link2,
  ListChecks,
  Plus,
  RefreshCw,
  RotateCcw,
  Save,
  Send,
  ShieldAlert,
  Target,
  Table2,
  Undo2,
  Workflow,
  XCircle,
} from "lucide-react";
import { useSearchParams } from "react-router";
import { useAuth } from "../../auth/auth-context";
import Modal from "../../components/ui/Modal";
import AemsEngagementWorkspaceNav from "../../components/aems/AemsEngagementWorkspaceNav";
import AemsWorkspaceLockNotice from "../../components/aems/AemsWorkspaceLockNotice";
import { getAemsWorkspaceGate } from "../../components/aems/aemsPhaseGates";
import RegistryHeader from "../../components/ui/RegistryHeader";
import SearchableSelect from "../../components/ui/SearchableSelect";
import StatusBadge from "../../components/ui/StatusBadge";
import SummaryCard from "../../components/ui/SummaryCard";
import { hasPermission } from "../../config/navigation";
import {
  aemsEngagementApi,
  aemsPlanningPackageApi,
  ApiError,
  masterListApi,
} from "../../services/api";
import { useToast } from "../../ui/toast-context";

const statusTones = {
  DRAFT: "inactive",
  PENDING_REVIEW: "warning",
  RETURNED_FOR_REVISION: "danger",
  RESUBMITTED: "warning",
  APPROVED: "success",
  SUPERSEDED: "inactive",
};

const sections = [
  { key: "overview", label: "Overview", icon: Layers3 },
  { key: "survey", label: "Preliminary Survey", icon: FilePenLine },
  { key: "process-flows", label: "Process Flows", icon: Workflow },
  { key: "risk-matrix", label: "Risk Matrix", icon: Table2 },
  { key: "kpis", label: "KPIs & Planned WPs", icon: Target },
  { key: "traceability", label: "Traceability", icon: Link2 },
  { key: "readiness", label: "Readiness & Review", icon: ListChecks },
  { key: "versions", label: "Versions", icon: History },
];

const surveyFields = [
  [
    "purpose",
    "Purpose and audit objectives",
    "Describe why the engagement is being performed.",
  ],
  [
    "background",
    "Background and understanding",
    "Capture the office, process, system, and control context.",
  ],
  [
    "informationSources",
    "Information sources",
    "List policies, records, interviews, systems, and other sources.",
  ],
  [
    "interviews",
    "Interviews and inquiries",
    "Summarize people consulted and key information obtained.",
  ],
  [
    "walkthroughs",
    "Walkthroughs",
    "Describe walkthroughs performed and the process points observed.",
  ],
  [
    "observations",
    "Preliminary observations",
    "Record initial conditions, risks, control observations, or limitations.",
  ],
  [
    "planningImplications",
    "Planning implications",
    "Explain how the survey changes the planned work.",
  ],
];

const emptySurvey = {
  purpose: "",
  background: "",
  informationSources: "",
  interviews: "",
  walkthroughs: "",
  observations: "",
  planningImplications: "",
};

const emptyForm = {
  preliminarySurvey: emptySurvey,
  preliminarySurveyDocumentVersionId: "",
  planningAttributes: {
    methodology: "",
    criteriaFramework: "",
    evidenceRequirements: "",
    milestones: "",
    schedule: "",
  },
  objectives: [],
  processFlows: [],
  riskMatrix: {
    code: "",
    title: "",
    methodology: "",
    riskAppetite: "",
    overallConclusion: "",
    auditAreaId: "",
    auditFocusId: "",
    allAuditFocuses: false,
  },
  riskItems: [],
  riskMatrices: [],
  kpis: [],
  plannedWorkingPapers: [],
};

function label(value) {
  return String(value ?? "")
    .replaceAll("_", " ")
    .replace(/\b\w/g, (character) => character.toUpperCase());
}

function formatDate(value, withTime = false) {
  if (!value) return "—";
  const date = new Date(withTime ? value : `${value}T00:00:00`);
  if (Number.isNaN(date.getTime())) return String(value);
  return new Intl.DateTimeFormat("en-PH", {
    month: "short",
    day: "numeric",
    year: "numeric",
    ...(withTime ? { hour: "numeric", minute: "2-digit" } : {}),
  }).format(date);
}

function valueOrDash(value) {
  return value === null || value === undefined || value === "" ? "—" : value;
}

function listText(value) {
  return Array.isArray(value) ? value.join("\n") : String(value ?? "");
}

function listValue(value) {
  return String(value ?? "")
    .split("\n")
    .map((entry) => entry.trim())
    .filter(Boolean);
}

function masterListOptions(masterLists, code) {
  return (masterLists.find((list) => list.code === code)?.items ?? [])
    .filter((item) => item.isActive)
    .map((item) => ({ value: item.code, label: item.label }));
}

function masterListCode(value, options) {
  const match = options.find(
    (option) =>
      String(option.value).toLowerCase() === String(value ?? "").toLowerCase() ||
      String(option.label).toLowerCase() === String(value ?? "").toLowerCase(),
  );
  return match?.value ?? value ?? "";
}

function masterListLabel(value, options) {
  return options.find((option) => String(option.value) === String(value))?.label ?? value;
}

function makeItem(sequence = 0) {
  return {
    riskCode: `R-${String(sequence + 1).padStart(2, "0")}`,
    riskStatement: "",
    riskCategory: "",
    inherentLikelihood: "",
    inherentImpact: "",
    inherentScore: "",
    controlDescription: "",
    controlEffectiveness: "",
    residualLikelihood: "",
    residualImpact: "",
    residualScore: "",
    residualRating: "",
    riskResponse: "",
    responsibleOfficeId: "",
    sequence,
    status: "OPEN",
    objectiveCodes: [],
    procedureIds: [],
    workingPapers: [],
    auditAreaId: "",
    auditFocusId: "",
    processFlowId: "",
    processName: "",
    riskArea: "",
    plannedAuditApproach: "",
    criteria: "",
    responseRationale: "",
  };
}

function normalizeVersion(version) {
  const matrix = version?.riskMatrix ?? {};
  const matrices = (
    version?.riskMatrices?.length
      ? version.riskMatrices
      : version?.riskMatrix
        ? [version.riskMatrix]
        : []
  ).map((entry) => ({
    ...emptyForm.riskMatrix,
    code: entry.code ?? "",
    title: entry.title ?? "",
    methodology: entry.methodology ?? "",
    riskAppetite: entry.riskAppetite ?? "",
    overallConclusion: entry.overallConclusion ?? "",
    auditAreaId: entry.auditAreaId ?? "",
    auditFocusId: entry.auditFocusId ?? "",
    allAuditFocuses: Boolean(entry.allAuditFocuses),
    matrixType: entry.matrixType ?? "",
    status: entry.status ?? "DRAFT",
    riskItems: (entry.items ?? entry.riskItems ?? []).map((item, index) => ({
      ...makeItem(index),
      ...item,
    })),
  }));
  return {
    preliminarySurvey: {
      ...emptySurvey,
      ...(version?.preliminarySurvey ?? {}),
    },
    preliminarySurveyDocumentVersionId:
      version?.preliminarySurveyDocumentVersionId ?? "",
    planningAttributes: {
      ...emptyForm.planningAttributes,
      ...(version?.planningAttributes ?? {}),
    },
    objectives: (version?.objectives ?? []).map((objective, index) => ({
      code: objective.code ?? `OBJ-${index + 1}`,
      statement: objective.statement ?? "",
      sourceType: objective.sourceType ?? "AEMS",
      sourceReference: objective.sourceReference ?? "",
      sequence: objective.sequence ?? index,
    })),
    processFlows: (version?.processFlows ?? []).map((flow, index) => ({
      id: flow.id ?? "",
      code: flow.code ?? `FLOW-${index + 1}`,
      title: flow.title ?? "",
      description: flow.description ?? "",
      processOwnerOfficeId: flow.processOwnerOfficeId ?? "",
      documentVersionId: flow.documentVersionId ?? "",
      sourceType: flow.sourceType ?? "AEMS",
      sourceReference: flow.sourceReference ?? "",
      sequence: flow.sequence ?? index,
      auditAreaId: flow.auditAreaId ?? "",
      auditFocusId: flow.auditFocusId ?? "",
      scopeStatement: flow.scopeStatement ?? "",
      steps: flow.steps ?? [],
      inputs: flow.inputs ?? [],
      outputs: flow.outputs ?? [],
      recordsSystems: flow.recordsSystems ?? [],
      controls: flow.controls ?? [],
      decisionPoints: flow.decisionPoints ?? [],
      riskPoints: flow.riskPoints ?? [],
      limitations: flow.limitations ?? "",
    })),
    riskMatrix: {
      ...emptyForm.riskMatrix,
      code: matrix.code ?? "",
      title: matrix.title ?? "",
      methodology: matrix.methodology ?? "",
      riskAppetite: matrix.riskAppetite ?? "",
      overallConclusion: matrix.overallConclusion ?? "",
      auditAreaId: matrix.auditAreaId ?? "",
      auditFocusId: matrix.auditFocusId ?? "",
      allAuditFocuses: Boolean(matrix.allAuditFocuses),
    },
    riskItems: (matrix.items ?? []).map((item, index) => ({
      ...makeItem(index),
      ...item,
      objectiveCodes:
        item.objectiveCodes ??
        item.objectives?.map((objective) => objective.code) ??
        [],
      procedureIds:
        item.procedureIds ??
        item.procedures?.map((procedure) => procedure.id) ??
        [],
      workingPapers: item.workingPapers ?? [],
    })),
    riskMatrices: matrices,
    kpis: (version?.kpis ?? []).map((kpi, index) => ({
      ...kpi,
      code: kpi.code ?? `KPI-${index + 1}`,
      sequence: kpi.sequence ?? index,
    })),
    plannedWorkingPapers: (version?.plannedWorkingPapers ?? []).map(
      (paper, index) => ({
        ...paper,
        reference: paper.reference ?? `WP-${index + 1}`,
        sequence: paper.sequence ?? index,
        isRequired: paper.isRequired ?? true,
      }),
    ),
  };
}

function Field({ label: fieldLabel, hint, error, wide = false, children }) {
  return (
    <label
      className={`text-sm font-semibold text-slate-700 ${wide ? "sm:col-span-2" : ""}`}
    >
      {fieldLabel}
      {hint && (
        <span className="ml-1 text-xs font-normal text-slate-400">{hint}</span>
      )}
      <span className="mt-1.5 block">{children}</span>
      {error && <small className="mt-1 block text-red-600">{error[0]}</small>}
    </label>
  );
}

function TextInput({ className = "", ...props }) {
  return (
    <input
      className={`min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-sky-500 focus:ring-2 focus:ring-sky-100 disabled:bg-slate-100 ${className}`}
      {...props}
    />
  );
}

function TextArea({ className = "", ...props }) {
  return (
    <textarea
      className={`min-h-28 w-full resize-y rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm leading-6 text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-sky-500 focus:ring-2 focus:ring-sky-100 disabled:bg-slate-100 ${className}`}
      {...props}
    />
  );
}

function Panel({ title, description, actions, children, className = "" }) {
  return (
    <section
      className={`rounded-2xl border border-slate-200 bg-white shadow-sm ${className}`}
    >
      <header className="flex flex-col gap-3 border-b border-slate-100 px-4 py-4 sm:flex-row sm:items-start sm:justify-between sm:px-5">
        <div>
          <h3 className="text-base font-bold text-slate-800">{title}</h3>
          {description && (
            <p className="mt-1 text-sm leading-6 text-slate-500">
              {description}
            </p>
          )}
        </div>
        {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
      </header>
      <div className="px-4 py-4 sm:px-5">{children}</div>
    </section>
  );
}

function SmallButton({
  children,
  onClick,
  tone = "secondary",
  icon: Icon,
  disabled = false,
  type = "button",
}) {
  const tones = {
    primary: "bg-sky-700 text-white hover:bg-sky-800",
    success: "bg-emerald-700 text-white hover:bg-emerald-800",
    warning: "bg-amber-500 text-white hover:bg-amber-600",
    danger: "bg-red-600 text-white hover:bg-red-700",
    secondary:
      "border border-slate-300 bg-white text-slate-700 hover:bg-slate-50",
  };
  return (
    <button
      className={`inline-flex min-h-10 items-center justify-center gap-2 rounded-lg px-3.5 text-xs font-bold transition disabled:cursor-not-allowed disabled:opacity-50 sm:text-sm ${tones[tone]}`}
      disabled={disabled}
      onClick={onClick}
      type={type}
    >
      {Icon && <Icon size={16} />}
      {children}
    </button>
  );
}

function EmptyState({ title, description }) {
  return (
    <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center">
      <p className="font-semibold text-slate-700">{title}</p>
      <p className="mt-1 text-sm text-slate-500">{description}</p>
    </div>
  );
}

function ErrorSummary({ errors }) {
  const messages = Object.entries(errors ?? {}).flatMap(([key, values]) =>
    (Array.isArray(values) ? values : [values])
      .filter(Boolean)
      .map((message) => `${label(key)}: ${message}`),
  );
  if (!messages.length) return null;
  return (
    <div
      className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
      role="alert"
    >
      <p className="font-bold">Please address the following:</p>
      <ul className="mt-1 list-disc space-y-1 pl-5">
        {messages.map((message, index) => (
          <li key={`${message}-${index}`}>{message}</li>
        ))}
      </ul>
    </div>
  );
}

function ReadOnlyBadge({ children = "Read-only approved version" }) {
  return (
    <span className="inline-flex items-center gap-1 rounded-lg bg-amber-50 px-2.5 py-1.5 text-xs font-bold text-amber-800 ring-1 ring-amber-200">
      <BadgeCheck size={14} />
      {children}
    </span>
  );
}

function MetadataGrid({ values }) {
  return (
    <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      {values.map(([name, value]) => (
        <div key={name} className="min-w-0">
          <dt className="text-[11px] font-bold uppercase tracking-wide text-slate-400">
            {name}
          </dt>
          <dd className="mt-1 break-words text-sm font-semibold text-slate-700">
            {valueOrDash(value)}
          </dd>
        </div>
      ))}
    </dl>
  );
}

export default function AemsPlanningPackagePage() {
  const { user } = useAuth();
  const toast = useToast();
  const [params, setParams] = useSearchParams();
  const [engagements, setEngagements] = useState([]);
  const [selectedId, setSelectedId] = useState(
    params.get("engagementId") ?? "",
  );
  const [activeSection, setActiveSection] = useState(
    params.get("section") ?? "overview",
  );
  const [workspace, setWorkspace] = useState(null);
  const [masterLists, setMasterLists] = useState([]);
  const [form, setForm] = useState(emptyForm);
  const [flowDraft, setFlowDraft] = useState(null);
  const [itemDraft, setItemDraft] = useState(null);
  const [objectiveDraft, setObjectiveDraft] = useState(null);
  const [action, setAction] = useState(null);
  const [actionComment, setActionComment] = useState("");
  const [selectedVersionNumber, setSelectedVersionNumber] = useState("");
  const [compareFrom, setCompareFrom] = useState("");
  const [compareTo, setCompareTo] = useState("");
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [errors, setErrors] = useState({});
  const [staleLock, setStaleLock] = useState(false);

  const canCreate = hasPermission(user, "aems.planning-package.create");
  const canUpdate = hasPermission(user, "aems.planning-package.update");
  const canReview = hasPermission(user, "aems.planning-package.review");
  const canApprove = hasPermission(user, "aems.planning-package.approve");
  const canRevise = hasPermission(user, "aems.planning-package.revise");

  const selectedEngagement = engagements.find(
    (engagement) => String(engagement.id) === String(selectedId),
  );
  const packageRecord = workspace?.package;
  const currentVersion = packageRecord?.latestVersion;
  const planningGate = getAemsWorkspaceGate("planning", workspace?.engagement);
  const planningUnlocked = planningGate.unlocked;
  const controlEffectivenessOptions = useMemo(
    () => masterListOptions(masterLists, "AEMS_CONTROL_EFFECTIVENESS"),
    [masterLists],
  );
  const residualRatingOptions = useMemo(
    () => masterListOptions(masterLists, "AEMS_RESIDUAL_RATING"),
    [masterLists],
  );
  const editable = Boolean(
    packageRecord?.status && workspace?.capabilities?.canEdit && canUpdate,
  );
  const versions =
    packageRecord?.versions ?? (currentVersion ? [currentVersion] : []);
  const inspectedVersion =
    versions.find(
      (version) =>
        String(version.versionNumber) === String(selectedVersionNumber),
    ) ?? currentVersion;
  const compareLeft = versions.find(
    (version) => String(version.versionNumber) === String(compareFrom),
  );
  const compareRight = versions.find(
    (version) => String(version.versionNumber) === String(compareTo),
  );

  const updateQuery = useCallback(
    (values) => {
      const next = new URLSearchParams(params);
      Object.entries(values).forEach(([key, value]) => {
        if (value === null || value === undefined || value === "")
          next.delete(key);
        else next.set(key, String(value));
      });
      if (next.toString() !== params.toString())
        setParams(next, { replace: true });
    },
    [params, setParams],
  );

  const loadEngagements = useCallback(async () => {
    try {
      const data = await aemsEngagementApi.list({
        perPage: 100,
        sortBy: "updated_at",
        sortDirection: "desc",
      });
      setEngagements(data.engagements);
      setSelectedId(
        (current) => current || String(data.engagements[0]?.id ?? ""),
      );
    } catch (requestError) {
      setError(requestError.message);
    }
  }, []);

  const loadWorkspace = useCallback(async () => {
    if (!selectedId) {
      setWorkspace(null);
      setForm(emptyForm);
      setLoading(false);
      return;
    }
    setLoading(true);
    setError("");
    setStaleLock(false);
    try {
      const data = await aemsPlanningPackageApi.show(selectedId);
      setWorkspace(data);
      setForm(normalizeVersion(data.package?.latestVersion));
      setSelectedVersionNumber(
        String(data.package?.currentVersionNumber ?? ""),
      );
      const availableVersions = data.package?.versions ?? [];
      setCompareTo(String(data.package?.currentVersionNumber ?? ""));
      setCompareFrom(
        String(
          availableVersions.at(-2)?.versionNumber ??
            data.package?.currentVersionNumber ??
            "",
        ),
      );
    } catch (requestError) {
      setWorkspace(null);
      setForm(emptyForm);
      setError(requestError.message);
    } finally {
      setLoading(false);
    }
  }, [selectedId]);

  useEffect(() => {
    const timer = window.setTimeout(loadEngagements, 0);
    return () => window.clearTimeout(timer);
  }, [loadEngagements]);

  useEffect(() => {
    let active = true;
    masterListApi.list().then((lists) => {
      if (active) setMasterLists(lists);
    }).catch(() => {
      // The planning workspace remains usable; save validation will explain a missing list.
    });
    return () => { active = false; };
  }, []);

  useEffect(() => {
    const timer = window.setTimeout(loadWorkspace, 0);
    return () => window.clearTimeout(timer);
  }, [loadWorkspace]);

  useEffect(() => {
    if (selectedId)
      updateQuery({ engagementId: selectedId, section: activeSection });
  }, [activeSection, selectedId, updateQuery]);

  function changeSection(section) {
    setActiveSection(section);
    updateQuery({ section });
  }

  function updateForm(key, value) {
    setForm((current) => ({ ...current, [key]: value }));
  }

  function updateNested(group, key, value) {
    setForm((current) => ({
      ...current,
      [group]: { ...current[group], [key]: value },
    }));
  }

  function updateItem(index, key, value) {
    setForm((current) => ({
      ...current,
      riskItems: current.riskItems.map((item, itemIndex) =>
        itemIndex === index
          ? {
              ...item,
              [key]: value,
              ...(key === "auditAreaId" ? { auditFocusId: "" } : {}),
            }
          : item,
      ),
    }));
  }

  function updateMatrix(index, key, value) {
    setForm((current) => {
      const hasMatrixCollection = current.riskMatrices?.length > 0;
      const matrices = hasMatrixCollection
        ? current.riskMatrices
        : [current.riskMatrix];
      const nextMatrices = matrices.map((matrix, matrixIndex) =>
        matrixIndex === index
          ? {
              ...matrix,
              [key]: value,
              ...(key === "auditAreaId"
                ? { auditFocusId: "", allAuditFocuses: false }
                : {}),
            }
          : matrix,
      );
      return {
        ...current,
        ...(hasMatrixCollection
          ? { riskMatrices: nextMatrices }
          : { riskMatrix: nextMatrices[0] }),
      };
    });
  }

  function updateMatrixFocus(index, value) {
    setForm((current) => {
      const hasMatrixCollection = current.riskMatrices?.length > 0;
      const matrices = hasMatrixCollection
        ? current.riskMatrices
        : [current.riskMatrix];
      const nextMatrices = matrices.map((matrix, matrixIndex) =>
        matrixIndex === index
          ? {
              ...matrix,
              auditFocusId: value === "ALL" ? "" : value,
              allAuditFocuses: value === "ALL",
            }
          : matrix,
      );
      return {
        ...current,
        ...(hasMatrixCollection
          ? { riskMatrices: nextMatrices }
          : { riskMatrix: nextMatrices[0] }),
      };
    });
  }

  function addMatrix() {
    setForm((current) => ({
      ...current,
      riskMatrices: [
        ...(current.riskMatrices?.length
          ? current.riskMatrices
          : [current.riskMatrix]),
        {
          ...emptyForm.riskMatrix,
          code: `RM-${(current.riskMatrices?.length ?? 1) + 1}`,
          title: "",
          riskItems: [],
        },
      ],
    }));
  }

  function removeMatrix(index) {
    setForm((current) => {
      const matrices = current.riskMatrices?.length
        ? current.riskMatrices
        : [current.riskMatrix];
      const nextMatrices = matrices.filter(
        (_, matrixIndex) => matrixIndex !== index,
      );
      const firstMatrix = nextMatrices[0] ?? {
        ...emptyForm.riskMatrix,
        riskItems: [],
      };
      return {
        ...current,
        riskMatrices: nextMatrices,
        riskMatrix: firstMatrix,
        riskItems: nextMatrices.length
          ? firstMatrix.riskItems ?? current.riskItems
          : [],
      };
    });
  }

  function updateKpi(index, key, value) {
    setForm((current) => ({
      ...current,
      kpis: current.kpis.map((item, itemIndex) =>
        itemIndex === index ? { ...item, [key]: value } : item,
      ),
    }));
  }

  function addKpi() {
    setForm((current) => ({
      ...current,
      kpis: [
        ...current.kpis,
        {
          code: `KPI-${current.kpis.length + 1}`,
          name: "",
          target: "",
          measurementMethod: "",
          sourceReference: "",
          sequence: current.kpis.length,
        },
      ],
    }));
  }

  function addPlannedWorkingPaper() {
    setForm((current) => ({
      ...current,
      plannedWorkingPapers: [
        ...current.plannedWorkingPapers,
        {
          reference: `WP-${current.plannedWorkingPapers.length + 1}`,
          title: "",
          objective: "",
          requiredEvidence: "",
          procedureId: "",
          sequence: current.plannedWorkingPapers.length,
          isRequired: true,
        },
      ],
    }));
  }

  function updatePlannedWorkingPaper(index, key, value) {
    setForm((current) => ({
      ...current,
      plannedWorkingPapers: current.plannedWorkingPapers.map(
        (item, itemIndex) =>
          itemIndex === index ? { ...item, [key]: value } : item,
      ),
    }));
  }

  function isStaleLockError(requestError) {
    const lockErrors = requestError?.errors?.lockVersion;
    return Boolean(
      (Array.isArray(lockErrors) &&
        lockErrors.some((message) => /changed|refresh/i.test(message))) ||
      /changed in another session|refresh before continuing/i.test(
        requestError?.message ?? "",
      ),
    );
  }

  function handleRequestError(requestError) {
    const requestErrors =
      requestError instanceof ApiError ? requestError.errors ?? {} : {};
    setErrors(requestErrors);
    setError(
      Object.keys(requestErrors).length ? "" : requestError.message,
    );
    if (isStaleLockError(requestError)) setStaleLock(true);
    toast.error(requestError.message);
  }

  async function savePackage() {
    if (!planningUnlocked) {
      toast.error("Planning Workspace is locked until the engagement reaches Engagement Planning after AEO issuance and auditee acknowledgement.");
      return;
    }
    setSaving(true);
    setError("");
    setErrors({});
    setStaleLock(false);
    try {
      const configuredMatrices = form.riskMatrices?.length
        ? form.riskMatrices
        : [form.riskMatrix].filter(
            (matrix) => matrix?.code?.trim() || matrix?.title?.trim(),
          );
      const normalizeItems = (items = []) => items.map((item) => ({
        ...item,
        controlEffectiveness: masterListCode(
          item.controlEffectiveness,
          controlEffectivenessOptions,
        ),
        residualRating: masterListCode(item.residualRating, residualRatingOptions),
      }));
      const payload = {
        ...form,
        riskItems: normalizeItems(form.riskItems),
        riskMatrices: configuredMatrices.map((matrix, index) => ({
          ...matrix,
          riskItems: normalizeItems(
            index === 0 ? form.riskItems : (matrix.riskItems ?? []),
          ),
        })),
        preliminarySurveyDocumentVersionId:
          form.preliminarySurveyDocumentVersionId || undefined,
      };
      if (packageRecord) {
        await aemsPlanningPackageApi.update(selectedId, packageRecord.id, {
          ...payload,
          lockVersion: packageRecord.lockVersion,
        });
        toast.success("Planning Package version saved.");
      } else {
        await aemsPlanningPackageApi.create(selectedId, payload);
        toast.success("Planning Package draft created.");
      }
      await loadWorkspace();
    } catch (requestError) {
      handleRequestError(requestError);
    } finally {
      setSaving(false);
    }
  }

  async function performAction() {
    if (!packageRecord) return;
    setSaving(true);
    setErrors({});
    try {
      if (action === "REVISE") {
        await aemsPlanningPackageApi.revise(selectedId, packageRecord.id, {
          lockVersion: packageRecord.lockVersion,
          reason: actionComment,
        });
      } else {
        await aemsPlanningPackageApi.transition(selectedId, packageRecord.id, {
          action,
          lockVersion: packageRecord.lockVersion,
          comment: actionComment || undefined,
        });
      }
      toast.success(`${label(action)} completed.`);
      setAction(null);
      setActionComment("");
      await loadWorkspace();
    } catch (requestError) {
      handleRequestError(requestError);
    } finally {
      setSaving(false);
    }
  }

  const actionOptions = useMemo(() => {
    if (!packageRecord || !planningUnlocked) return [];
    const available = [];
    if (packageRecord.status === "DRAFT" && canUpdate)
      available.push(["SUBMIT", "Submit for review", Send, "primary"]);
    if (
      ["PENDING_REVIEW", "RESUBMITTED"].includes(packageRecord.status) &&
      canReview
    ) {
      available.push([
        "REVIEW",
        "Record independent review",
        FileCheck2,
        "primary",
      ]);
      available.push(["RETURN", "Return for revision", Undo2, "warning"]);
    }
    if (
      ["PENDING_REVIEW", "RESUBMITTED"].includes(packageRecord.status) &&
      canApprove
    ) {
      available.push([
        "APPROVE",
        "Approve current version",
        BadgeCheck,
        "success",
      ]);
    }
    if (packageRecord.status === "RETURNED_FOR_REVISION" && canUpdate)
      available.push(["RESUBMIT", "Resubmit for review", Send, "primary"]);
    if (packageRecord.status === "APPROVED" && canRevise)
      available.push(["REVISE", "Start formal revision", RotateCcw, "warning"]);
    return available;
  }, [canApprove, canRevise, canReview, canUpdate, packageRecord, planningUnlocked]);

  function openAction(nextAction) {
    setErrors({});
    setActionComment("");
    setAction(nextAction);
  }

  function startNewFlow() {
    setFlowDraft({
      code: `FLOW-${String(form.processFlows.length + 1).padStart(2, "0")}`,
      title: "",
      description: "",
      processOwnerOfficeId: "",
      documentVersionId: "",
      sourceReference: "",
      auditAreaId: "",
      auditFocusId: "",
      scopeStatement: "",
      steps: [],
      inputs: [],
      outputs: [],
      recordsSystems: [],
      controls: [],
      decisionPoints: [],
      riskPoints: [],
      limitations: "",
    });
  }

  function startNewObjective() {
    setObjectiveDraft({
      code: `OBJ-${String(form.objectives.length + 1).padStart(2, "0")}`,
      statement: "",
      sourceType: "AEMS",
      sourceReference: "",
      sequence: form.objectives.length,
    });
  }

  function editObjective(objective, index) {
    setObjectiveDraft({ ...objective, index });
  }

  function saveObjectiveDraft() {
    const { index, ...objective } = objectiveDraft;
    if (index === undefined) {
      updateForm("objectives", [
        ...form.objectives,
        { ...objective, sequence: form.objectives.length },
      ]);
    } else {
      updateForm(
        "objectives",
        form.objectives.map((item, itemIndex) =>
          itemIndex === index ? { ...item, ...objective } : item,
        ),
      );
    }
    setObjectiveDraft(null);
  }

  function editFlow(flow, index) {
    setFlowDraft({ ...flow, index });
  }

  function saveFlowDraft() {
    const { index, ...flow } = flowDraft;
    if (index === undefined)
      updateForm("processFlows", [
        ...form.processFlows,
        { ...flow, sequence: form.processFlows.length },
      ]);
    else
      updateForm(
        "processFlows",
        form.processFlows.map((item, itemIndex) =>
          itemIndex === index ? { ...item, ...flow } : item,
        ),
      );
    setFlowDraft(null);
  }

  function startNewItem() {
    setItemDraft({
      ...makeItem(form.riskItems.length),
      index: undefined,
      workingPaperText: "",
    });
  }

  function editItem(item, index) {
    const matchingFlow = (form.processFlows ?? []).find(
      (flow) =>
        String(flow.id) === String(item.processFlowId) ||
        (item.processName && String(flow.title) === String(item.processName)),
    );
    setItemDraft({
      ...makeItem(index),
      ...item,
      controlEffectiveness: masterListCode(
        item.controlEffectiveness,
        controlEffectivenessOptions,
      ),
      residualRating: masterListCode(item.residualRating, residualRatingOptions),
      processFlowId: matchingFlow?.id ?? item.processFlowId,
      index,
      workingPaperText: (item.workingPapers ?? [])
        .map((paper) => paper.reference)
        .join("\n"),
    });
  }

  function saveItemDraft() {
    const { index, workingPaperText, ...item } = itemDraft;
    const nextItem = {
      ...item,
      workingPapers: String(workingPaperText ?? "")
        .split("\n")
        .map((reference) => reference.trim())
        .filter(Boolean)
        .map((reference) => ({ reference })),
    };
    if (index === undefined)
      updateForm("riskItems", [
        ...form.riskItems,
        { ...nextItem, sequence: form.riskItems.length },
      ]);
    else
      updateForm(
        "riskItems",
        form.riskItems.map((current, itemIndex) =>
          itemIndex === index ? { ...current, ...nextItem } : current,
        ),
      );
    setItemDraft(null);
  }

  const engagementOptions = engagements.map((engagement) => ({
    value: engagement.id,
    label: `${engagement.engagementCode} — ${engagement.title}`,
    keywords: engagement.offices?.map((office) => office.name).join(" "),
  }));
  const scopedEngagement =
    workspace && String(workspace.engagement?.id) === String(selectedId)
      ? workspace.engagement
      : selectedEngagement;
  const officeOptions = (scopedEngagement?.offices ?? []).map((office) => ({
    value: office.id,
    label: office.name,
  }));
  const areaOptions = (scopedEngagement?.auditAreas ?? []).map((area) => ({
    value: area.id,
    label: area.code ? `${area.code} — ${area.name}` : area.name,
    keywords: area.name,
  }));
  const focusOptions = (scopedEngagement?.auditFocuses ?? []).map((focus) => ({
    value: focus.id,
    label: focus.code ? `${focus.code} — ${focus.name}` : focus.name,
    keywords: focus.name,
    areaId: focus.auditAreaId ?? focus.audit_area_id ?? null,
  }));
  const procedureOptions = (workspace?.procedures ?? []).map((procedure) => ({
    value: procedure.id,
    label: `${procedure.code} — ${procedure.objective}`,
  }));
  const objectiveOptions = form.objectives.map((objective) => ({
    value: objective.code,
    label: `${objective.code} — ${objective.statement}`,
  }));
  const packageStatus = packageRecord?.status ?? "NOT_CREATED";
  const currentIsReadOnly = Boolean(packageRecord && !editable);

  if (loading && !workspace) {
    return (
      <div className="grid min-h-[calc(100vh-8rem)] place-items-center text-sm font-semibold text-slate-500">
        Loading Planning Package workspace…
      </div>
    );
  }

  return (
    <div className="mx-auto w-full max-w-[1600px] p-4 sm:p-6 lg:p-8">
      <RegistryHeader
        icon={ClipboardCheck}
        title="Planning Workspace"
        description="Coordinate the Preliminary Survey, Process Flows, AEP and KPIs, Risk Matrix, Audit Programs, and planning readiness from one controlled workspace."
        readOnly={currentIsReadOnly && !canUpdate}
        actions={
          <>
            <div className="w-full sm:min-w-80 sm:w-80">
              <SearchableSelect
                options={engagementOptions}
                value={selectedId}
                onChange={(value) => setSelectedId(String(value))}
                placeholder="Select an engagement"
                searchPlaceholder="Search authorized engagements..."
                disabled={loading}
              />
            </div>
            {editable && (
              <SmallButton icon={Save} onClick={savePackage} disabled={saving}>
                {saving ? "Saving…" : "Save new version"}
              </SmallButton>
            )}
            {!packageRecord && canCreate && planningUnlocked && selectedId && (
              <SmallButton
                icon={Plus}
                tone="primary"
                onClick={savePackage}
                disabled={saving}
              >
                {saving ? "Creating…" : "Create draft"}
              </SmallButton>
            )}
            {packageRecord &&
              actionOptions.map(([key, text, Icon, tone]) => (
                <SmallButton
                  key={key}
                  icon={Icon}
                  tone={tone}
                  onClick={() => openAction(key)}
                  disabled={saving}
                >
                  {text}
                </SmallButton>
              ))}
          </>
        }
      />

      {error && (
        <div
          className="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
          role="alert"
        >
          {error}
        </div>
      )}
      {staleLock && (
        <div
          className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
          role="alert"
        >
          <span>
            <strong>This workspace is out of date.</strong> Another user changed
            the planning package. Reload before continuing.
          </span>
          <SmallButton icon={RefreshCw} onClick={loadWorkspace}>
            Reload workspace
          </SmallButton>
        </div>
      )}
      {workspace && !planningUnlocked && (
        <AemsWorkspaceLockNotice engagementId={selectedId} gate={planningGate} />
      )}
      <ErrorSummary errors={errors} />

      {workspace && (
        <>
          <AemsEngagementWorkspaceNav
            engagement={workspace.engagement}
            engagementId={selectedId}
          />
          <div className="mb-4 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
            <div
              className="aems-workspace-tabs-scroll flex min-w-0 max-w-full flex-nowrap gap-1 overflow-x-scroll overflow-y-hidden sm:flex-wrap sm:overflow-visible"
              role="tablist"
              aria-label="Planning Package sections"
            >
              {sections.map((section) => {
                const Icon = section.icon;
                const active = section.key === activeSection;
                return (
                  <button
                    aria-selected={active}
                    className={`scroll-mt-24 inline-flex min-h-10 items-center gap-2 rounded-lg px-3 text-xs font-bold transition sm:px-4 sm:text-sm ${active ? "bg-sky-700 text-white" : "text-slate-600 hover:bg-slate-100"}`}
                    key={section.key}
                    onClick={() => changeSection(section.key)}
                    role="tab"
                    type="button"
                  >
                    <Icon size={15} />
                    {section.label}
                  </button>
                );
              })}
            </div>
          </div>

          <div className="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <SummaryCard
              icon={ClipboardCheck}
              label="Package status"
              value={label(packageStatus)}
              tone={
                statusTones[packageStatus] === "success"
                  ? "emerald"
                  : statusTones[packageStatus] === "danger"
                    ? "red"
                    : statusTones[packageStatus] === "warning"
                      ? "amber"
                      : "slate"
              }
            />
            <SummaryCard
              icon={History}
              label="Current / approved version"
              value={`${packageRecord?.currentVersionNumber ?? "—"} / ${packageRecord?.approvedVersionNumber ?? "—"}`}
              tone="sky"
            />
            <SummaryCard
              icon={CheckCircle2}
              label="Planning conformance"
              value={workspace.readiness?.fieldworkReady ? "Ready" : "Blocked"}
              tone={workspace.readiness?.fieldworkReady ? "emerald" : "amber"}
            />
            <SummaryCard
              icon={Link2}
              label="Risk traceability items"
              value={form.riskItems.length}
              tone="slate"
            />
          </div>

          {(!packageRecord ||
            packageRecord.status !== "APPROVED" ||
            packageRecord.currentVersionNumber !==
              packageRecord.approvedVersionNumber ||
            !workspace.readiness?.fieldworkReady) && (
            <div
              className="mb-5 flex gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-900"
              data-testid="planning-fieldwork-blocker"
              role="status"
            >
              <ShieldAlert className="mt-0.5 shrink-0" size={20} />
              <div>
                <strong>Fieldwork is blocked.</strong> The aggregate engagement
                lifecycle requires an approved Planning Package whose current
                version matches the approved version and passes every planning
                conformance gate before `START_FIELDWORK` can proceed.
              </div>
            </div>
          )}

          {!packageRecord && (
            <div
              className="mb-5 rounded-2xl border border-sky-200 bg-sky-50 px-4 py-4 text-sm leading-6 text-sky-900"
              role="note"
            >
              <strong>Planning Package sequence.</strong> Create the package
              draft here first; an Audit Program is not required to create the
              draft. Before this package can be approved and fieldwork can
              start, the AEP must be approved, the Audit Program must be
              approved, and the package readiness checks must pass. If the
              Create draft action returns an error, review the validation
              details above and confirm you are in the engagement-planning stage
              with the planning-package permission.
            </div>
          )}

          {activeSection === "overview" && (
            <>
              <OverviewSection
                workspace={workspace}
                packageRecord={packageRecord}
                currentVersion={currentVersion}
              />
              <ObjectivesSection
                objectives={form.objectives}
                editable={editable}
                onAdd={startNewObjective}
                onEdit={editObjective}
                onRemove={(index) =>
                  updateForm(
                    "objectives",
                    form.objectives.filter(
                      (_, itemIndex) => itemIndex !== index,
                    ),
                  )
                }
              />
              <PlanningAttributesSection
                attributes={form.planningAttributes}
                editable={editable}
                onChange={(key, value) =>
                  updateNested("planningAttributes", key, value)
                }
              />
            </>
          )}
          {activeSection === "survey" && (
            <SurveySection
              form={form}
              editable={editable}
              errors={errors}
              updateNested={updateNested}
              updateForm={updateForm}
            />
          )}
          {activeSection === "process-flows" && (
            <ProcessFlowsSection
              form={form}
              editable={editable}
              officeOptions={officeOptions}
              areaOptions={areaOptions}
              focusOptions={focusOptions}
              onAdd={startNewFlow}
              onEdit={editFlow}
              onRemove={(index) =>
                updateForm(
                  "processFlows",
                  form.processFlows.filter(
                    (_, itemIndex) => itemIndex !== index,
                  ),
                )
              }
            />
          )}
          {activeSection === "risk-matrix" && (
            <RiskMatrixSection
              form={form}
              editable={editable}
              officeOptions={officeOptions}
              areaOptions={areaOptions}
              focusOptions={focusOptions}
              objectiveOptions={objectiveOptions}
              procedureOptions={procedureOptions}
              onChange={updateNested}
              onChangeMatrix={updateMatrix}
              onChangeFocus={updateMatrixFocus}
              onAddMatrix={addMatrix}
              onRemoveMatrix={removeMatrix}
              onAdd={startNewItem}
              onEdit={editItem}
              residualRatingOptions={residualRatingOptions}
              onRemove={(index) =>
                updateForm(
                  "riskItems",
                  form.riskItems.filter((_, itemIndex) => itemIndex !== index),
                )
              }
              updateItem={updateItem}
            />
          )}
          {activeSection === "kpis" && (
            <PlanningConformanceSection
              kpis={form.kpis}
              plannedWorkingPapers={form.plannedWorkingPapers}
              procedures={workspace.procedures ?? []}
              editable={editable}
              onAddKpi={addKpi}
              onUpdateKpi={updateKpi}
              onRemoveKpi={(index) =>
                updateForm(
                  "kpis",
                  form.kpis.filter((_, itemIndex) => itemIndex !== index),
                )
              }
              onAddWorkingPaper={addPlannedWorkingPaper}
              onUpdateWorkingPaper={updatePlannedWorkingPaper}
              onRemoveWorkingPaper={(index) =>
                updateForm(
                  "plannedWorkingPapers",
                  form.plannedWorkingPapers.filter(
                    (_, itemIndex) => itemIndex !== index,
                  ),
                )
              }
            />
          )}
          {activeSection === "traceability" && (
            <TraceabilitySection workspace={workspace} form={form} />
          )}
          {activeSection === "readiness" && (
            <ReadinessSection
              workspace={workspace}
              packageRecord={packageRecord}
              actionOptions={actionOptions}
              onAction={openAction}
            />
          )}
          {activeSection === "versions" && (
            <VersionsSection
              packageRecord={packageRecord}
              versions={versions}
              inspectedVersion={inspectedVersion}
              selectedVersionNumber={selectedVersionNumber}
              setSelectedVersionNumber={setSelectedVersionNumber}
              compareFrom={compareFrom}
              compareTo={compareTo}
              setCompareFrom={setCompareFrom}
              setCompareTo={setCompareTo}
              compareLeft={compareLeft}
              compareRight={compareRight}
            />
          )}

          {editable && activeSection !== "overview" && (
            <div className="mt-5 flex flex-wrap items-center justify-end gap-2 rounded-2xl border border-sky-100 bg-sky-50 p-3">
              <span className="mr-auto text-sm text-sky-900">
                Changes are saved as a new immutable Planning Package version.
              </span>
              <SmallButton
                icon={Save}
                tone="primary"
                onClick={savePackage}
                disabled={saving}
              >
                {saving ? "Saving…" : "Save new version"}
              </SmallButton>
            </div>
          )}
        </>
      )}

      <Modal
        open={Boolean(objectiveDraft)}
        title={
          objectiveDraft?.index === undefined
            ? "Add planning objective"
            : "Edit planning objective"
        }
        description="Planning objectives anchor the risk matrix and preserve the relationship to the approved audit plan."
        onClose={() => setObjectiveDraft(null)}
        size="md"
        footer={
          <>
            <SmallButton onClick={() => setObjectiveDraft(null)}>
              Cancel
            </SmallButton>
            <SmallButton tone="primary" onClick={saveObjectiveDraft}>
              Keep objective in draft
            </SmallButton>
          </>
        }
      >
        {objectiveDraft && (
          <ObjectiveEditor
            objective={objectiveDraft}
            setObjective={setObjectiveDraft}
          />
        )}
      </Modal>

      <Modal
        open={Boolean(flowDraft)}
        title={
          flowDraft?.index === undefined
            ? "Add process flow"
            : "Edit process flow"
        }
        description="Describe the process flow or point to the exact Core Document Version used as the controlled source."
        onClose={() => setFlowDraft(null)}
        size="lg"
        footer={
          <>
            <SmallButton onClick={() => setFlowDraft(null)}>Close</SmallButton>
            {editable && (
              <SmallButton tone="primary" onClick={saveFlowDraft}>
                Keep flow in draft
              </SmallButton>
            )}
          </>
        }
      >
        {flowDraft && (
          <FlowEditor
            flow={flowDraft}
            setFlow={setFlowDraft}
            officeOptions={officeOptions}
            areaOptions={areaOptions}
            focusOptions={focusOptions}
            editable={editable}
          />
        )}
      </Modal>

      <Modal
        open={Boolean(itemDraft)}
        title={
          itemDraft?.index === undefined
            ? "Add risk matrix item"
            : `Risk matrix item ${itemDraft?.riskCode ?? ""}`
        }
        description="Capture the risk, control context, residual rating, and exact traceability links for this planning version."
        onClose={() => setItemDraft(null)}
        size="xl"
        footer={
          <>
            <SmallButton onClick={() => setItemDraft(null)}>Close</SmallButton>
            {editable && (
              <SmallButton tone="primary" onClick={saveItemDraft}>
                Keep item in draft
              </SmallButton>
            )}
          </>
        }
      >
        {itemDraft && (
          <RiskItemEditor
            item={itemDraft}
            setItem={setItemDraft}
            objectiveOptions={objectiveOptions}
            procedureOptions={procedureOptions}
            officeOptions={officeOptions}
            areaOptions={areaOptions}
            focusOptions={focusOptions}
            processFlowOptions={(form.processFlows ?? []).filter((flow) => flow.id).map((flow) => ({
              value: flow.id,
              label: `${flow.code} — ${flow.title || "Untitled process flow"}`,
            }))}
            controlEffectivenessOptions={controlEffectivenessOptions}
            residualRatingOptions={residualRatingOptions}
            editable={editable}
          />
        )}
      </Modal>

      <Modal
        open={Boolean(action)}
        title={
          action === "REVISE"
            ? "Start formal Planning Package revision"
            : `${label(action)} Planning Package`
        }
        description={
          action === "RETURN"
            ? "Explain what must be corrected before the package can be resubmitted."
            : action === "REVISE"
              ? "The approved version remains immutable; this creates a new editable draft version."
              : "The server will validate role, scope, state, readiness, independence, and lock version before completing this action."
        }
        onClose={() => !saving && setAction(null)}
        size="md"
        footer={
          <>
            <SmallButton onClick={() => setAction(null)} disabled={saving}>
              Cancel
            </SmallButton>
            <SmallButton
              tone={
                action === "RETURN" || action === "REVISE"
                  ? "warning"
                  : action === "APPROVE"
                    ? "success"
                    : "primary"
              }
              onClick={performAction}
              disabled={
                saving ||
                ((action === "RETURN" || action === "REVISE") &&
                  actionComment.trim().length < 5)
              }
            >
              {saving ? "Working…" : label(action)}
            </SmallButton>
          </>
        }
      >
        {(action === "RETURN" ||
          action === "REVISE" ||
          action === "REVIEW" ||
          action === "APPROVE") && (
          <Field
            label={
              action === "REVISE"
                ? "Revision reason"
                : action === "RETURN"
                  ? "Return instructions"
                  : "Review comment"
            }
            hint={
              action === "RETURN" || action === "REVISE"
                ? "Required"
                : "Optional"
            }
          >
            <TextArea
              autoFocus
              value={actionComment}
              onChange={(event) => setActionComment(event.target.value)}
              placeholder={
                action === "RETURN"
                  ? "State the specific corrections required..."
                  : "Add a concise professional note..."
              }
            />
          </Field>
        )}
      </Modal>
    </div>
  );
}

function OverviewSection({ workspace, packageRecord, currentVersion }) {
  const engagement = workspace.engagement ?? {};
  return (
    <div className="grid gap-5 xl:grid-cols-[1.3fr_0.7fr]">
      <Panel
        title="Engagement and planning baseline"
        description="The Planning Package stays linked to the engagement, approved AEP, current Audit Program, and original IAP source lineage."
      >
        <MetadataGrid
          values={[
            [
              "Engagement",
              `${engagement.engagementCode ?? "—"} — ${engagement.title ?? "—"}`,
            ],
            ["Source type", label(engagement.sourceType)],
            [
              "Engagement status",
              <StatusBadge
                key="status"
                tone={statusTones[engagement.status] ?? "info"}
              >
                {label(engagement.status)}
              </StatusBadge>,
            ],
            ["Package code", packageRecord?.packageCode],
            [
              "AEP approval",
              workspace.approvedAep ? "Approved" : "Not approved",
            ],
            [
              "Audit Program approval",
              workspace.approvedProgram ? "Approved" : "Not approved",
            ],
            ["Checksum", currentVersion?.checksumSha256],
            ["Lock version", packageRecord?.lockVersion],
            [
              "Created version",
              currentVersion
                ? `Version ${currentVersion.versionNumber}`
                : "No package yet",
            ],
          ]}
        />
        <div className="mt-5 grid gap-4 border-t border-slate-100 pt-5 sm:grid-cols-2 lg:grid-cols-4">
          {[
            ["Prepared by", packageRecord?.preparedBy],
            ["Submitted by", packageRecord?.submittedBy],
            ["Submitted at", formatDate(packageRecord?.submittedAt, true)],
            ["Approved by", packageRecord?.approvedBy],
          ].map(([name, value]) => (
            <div key={name}>
              <p className="text-[11px] font-bold uppercase tracking-wide text-slate-400">
                {name}
              </p>
              <p className="mt-1 text-sm font-semibold text-slate-700">
                {typeof value === "object"
                  ? (value?.name ?? value?.employeeId ?? "—")
                  : valueOrDash(value)}
              </p>
            </div>
          ))}
        </div>
      </Panel>
      <Panel
        title="IAP lineage snapshot"
        description="This lineage is read-only and is preserved from the authorized source engagement."
      >
        <MetadataGrid
          values={[
            ["Source type", label(workspace.lineage?.sourceType)],
            ["IAP plan engagement", workspace.lineage?.iapPlanEngagementId],
            ["IAP plan", workspace.lineage?.iapPlanId],
            ["Prioritization item", workspace.lineage?.iapPrioritizationItemId],
            ["Risk assessment", workspace.lineage?.iapRiskAssessmentId],
            ["Audit Universe item", workspace.lineage?.iapAuditUniverseItemId],
            [
              "Snapshot captured",
              formatDate(workspace.lineage?.capturedAt, true),
            ],
          ]}
        />
        <div className="mt-4 rounded-xl border border-sky-100 bg-sky-50 px-3 py-3 text-sm leading-6 text-sky-900">
          IAP records remain authoritative for planning source history. This
          workspace owns the operational planning baseline and does not update
          IAP.
        </div>
      </Panel>
    </div>
  );
}

function ObjectivesSection({ objectives, editable, onAdd, onEdit, onRemove }) {
  return (
    <Panel
      className="mt-5"
      title="Planning objectives"
      description="Define the engagement-specific objectives that anchor the risk matrix and preserve their source lineage."
      actions={
        <>
          {!editable && <ReadOnlyBadge />}
          {editable && (
            <SmallButton icon={Plus} tone="primary" onClick={onAdd}>
              Add objective
            </SmallButton>
          )}
        </>
      }
    >
      {!objectives.length ? (
        <EmptyState
          title="No planning objectives recorded"
          description="Add at least one objective to satisfy the planning readiness gate."
        />
      ) : (
        <div className="space-y-3">
          {objectives.map((objective, index) => (
            <article
              className="rounded-xl border border-slate-200 p-4"
              key={`${objective.code}-${index}`}
            >
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                  <div className="flex flex-wrap items-center gap-2">
                    <Target className="text-sky-700" size={17} />
                    <strong className="text-slate-800">{objective.code}</strong>
                    <span className="text-slate-300">·</span>
                    <span className="text-sm font-semibold text-slate-700">
                      {objective.statement ||
                        "No objective statement recorded."}
                    </span>
                  </div>
                  <p className="mt-2 text-xs text-slate-500">
                    Source: {objective.sourceType || "AEMS"}
                    {objective.sourceReference
                      ? ` · ${objective.sourceReference}`
                      : ""}
                  </p>
                </div>
                {editable && (
                  <div className="flex gap-2">
                    <SmallButton
                      icon={FilePenLine}
                      onClick={() => onEdit(objective, index)}
                    >
                      Edit
                    </SmallButton>
                    <SmallButton
                      icon={XCircle}
                      tone="danger"
                      onClick={() => onRemove(index)}
                    >
                      Remove
                    </SmallButton>
                  </div>
                )}
              </div>
            </article>
          ))}
        </div>
      )}
    </Panel>
  );
}

function ObjectiveEditor({ objective, setObjective }) {
  const update = (key, value) =>
    setObjective((current) => ({ ...current, [key]: value }));
  return (
    <div className="grid gap-4 sm:grid-cols-2">
      <Field label="Objective code">
        <TextInput
          value={objective.code}
          onChange={(event) => update("code", event.target.value)}
        />
      </Field>
      <Field label="Source type">
        <TextInput
          value={objective.sourceType}
          onChange={(event) => update("sourceType", event.target.value)}
        />
      </Field>
      <Field label="Objective statement" wide>
        <TextArea
          autoFocus
          value={objective.statement}
          onChange={(event) => update("statement", event.target.value)}
        />
      </Field>
      <Field
        label="Source reference"
        wide
        hint="Optional IAP, AEP, or engagement reference"
      >
        <TextInput
          value={objective.sourceReference}
          onChange={(event) => update("sourceReference", event.target.value)}
        />
      </Field>
    </div>
  );
}

function PlanningAttributesSection({ attributes, editable, onChange }) {
  return (
    <Panel
      className="mt-5"
      title="Engagement-specific planning attributes"
      description="Capture the method, criteria, evidence expectations, milestones, and schedule used for this engagement baseline."
      actions={!editable && <ReadOnlyBadge />}
    >
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="Methodology">
          <TextInput
            disabled={!editable}
            value={attributes.methodology ?? ""}
            onChange={(event) => onChange("methodology", event.target.value)}
            placeholder="Risk-based, compliance, performance..."
          />
        </Field>
        <Field label="Criteria framework">
          <TextInput
            disabled={!editable}
            value={attributes.criteriaFramework ?? ""}
            onChange={(event) =>
              onChange("criteriaFramework", event.target.value)
            }
            placeholder="Policies, laws, standards, or approved criteria"
          />
        </Field>
        <Field label="Evidence requirements" wide>
          <TextArea
            disabled={!editable}
            value={attributes.evidenceRequirements ?? ""}
            onChange={(event) =>
              onChange("evidenceRequirements", event.target.value)
            }
          />
        </Field>
        <Field label="Milestones">
          <TextArea
            disabled={!editable}
            value={attributes.milestones ?? ""}
            onChange={(event) => onChange("milestones", event.target.value)}
          />
        </Field>
        <Field label="Schedule">
          <TextArea
            disabled={!editable}
            value={attributes.schedule ?? ""}
            onChange={(event) => onChange("schedule", event.target.value)}
          />
        </Field>
      </div>
    </Panel>
  );
}

function SurveySection({ form, editable, errors, updateNested, updateForm }) {
  return (
    <Panel
      title="Preliminary Survey"
      description="Document the initial understanding of the auditee, process, systems, controls, information flows, and planning implications."
      actions={!editable && <ReadOnlyBadge />}
    >
      <div className="grid gap-4 sm:grid-cols-2">
        {surveyFields.map(([key, fieldLabel, hint]) => (
          <Field
            key={key}
            label={fieldLabel}
            hint={hint}
            wide
            error={errors?.[`preliminarySurvey.${key}`]}
          >
            <TextArea
              disabled={!editable}
              value={form.preliminarySurvey[key] ?? ""}
              onChange={(event) =>
                updateNested("preliminarySurvey", key, event.target.value)
              }
            />
          </Field>
        ))}
        <Field
          label="Core Document Version ID"
          hint="Optional exact survey source"
          error={errors?.preliminarySurveyDocumentVersionId}
        >
          <TextInput
            disabled={!editable}
            inputMode="numeric"
            value={form.preliminarySurveyDocumentVersionId ?? ""}
            onChange={(event) =>
              updateForm(
                "preliminarySurveyDocumentVersionId",
                event.target.value,
              )
            }
            placeholder="Document Version ID"
          />
        </Field>
      </div>
    </Panel>
  );
}

function ProcessFlowsSection({
  form,
  editable,
  officeOptions,
  onAdd,
  onEdit,
  onRemove,
}) {
  return (
    <Panel
      title="Process Flow Documentation"
      description="Use a narrative, flowchart-backed document, or both. Each flow remains part of the immutable planning version."
      actions={
        <>
          {!editable && <ReadOnlyBadge />}
          {editable && (
            <SmallButton icon={Plus} tone="primary" onClick={onAdd}>
              Add process flow
            </SmallButton>
          )}
        </>
      }
    >
      {!form.processFlows.length ? (
        <EmptyState
          title="No process flows recorded"
          description="Add at least one process flow to satisfy the readiness gate."
        />
      ) : (
        <div className="space-y-3">
          {form.processFlows.map((flow, index) => (
            <article
              className="rounded-xl border border-slate-200 p-4"
              key={`${flow.code}-${index}`}
            >
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                  <div className="flex flex-wrap items-center gap-2">
                    <span className="font-bold text-slate-800">
                      {flow.code}
                    </span>
                    <span className="text-slate-300">·</span>
                    <span className="font-semibold text-slate-700">
                      {flow.title || "Untitled process flow"}
                    </span>
                  </div>
                  <p className="mt-2 whitespace-pre-wrap text-sm leading-6 text-slate-600">
                    {flow.description || "No narrative description recorded."}
                  </p>
                </div>
                {editable && (
                  <div className="flex gap-2">
                    <SmallButton
                      icon={FilePenLine}
                      onClick={() => onEdit(flow, index)}
                    >
                      Edit
                    </SmallButton>
                    <SmallButton
                      icon={XCircle}
                      tone="danger"
                      onClick={() => onRemove(index)}
                    >
                      Remove
                    </SmallButton>
                  </div>
                )}
              </div>
              <div className="mt-3 grid gap-3 border-t border-slate-100 pt-3 text-xs sm:grid-cols-3">
                <div>
                  <span className="font-bold uppercase tracking-wide text-slate-400">
                    Process owner
                  </span>
                  <p className="mt-1 text-slate-700">
                    {officeOptions.find(
                      (office) =>
                        String(office.value) ===
                        String(flow.processOwnerOfficeId),
                    )?.label ?? valueOrDash(flow.processOwnerOfficeId)}
                  </p>
                </div>
                <div>
                  <span className="font-bold uppercase tracking-wide text-slate-400">
                    Core Document Version
                  </span>
                  <p className="mt-1 text-slate-700">
                    {valueOrDash(flow.documentVersionId)}
                  </p>
                </div>
                <div>
                  <span className="font-bold uppercase tracking-wide text-slate-400">
                    Source reference
                  </span>
                  <p className="mt-1 text-slate-700">
                    {valueOrDash(flow.sourceReference)}
                  </p>
                </div>
              </div>
            </article>
          ))}
        </div>
      )}
    </Panel>
  );
}

function FlowEditor({ flow, setFlow, officeOptions, areaOptions, focusOptions, editable = true }) {
  const update = (key, value) =>
    setFlow((current) => ({ ...current, [key]: value }));
  return (
    <div className="grid gap-4 sm:grid-cols-2">
      <Field label="Flow code">
        <TextInput
          disabled={!editable}
          value={flow.code}
          onChange={(event) => update("code", event.target.value)}
        />
      </Field>
      <Field label="Title">
        <TextInput
          disabled={!editable}
          value={flow.title}
          onChange={(event) => update("title", event.target.value)}
        />
      </Field>
      <Field label="Process owner office">
        <SearchableSelect
          disabled={!editable}
          options={officeOptions}
          value={flow.processOwnerOfficeId}
          onChange={(value) => update("processOwnerOfficeId", value)}
          placeholder="Select linked office"
        />
      </Field>
      <Field label="Audit area">
        <SearchableSelect
          disabled={!editable}
          options={areaOptions}
          value={flow.auditAreaId}
          onChange={(value) => {
            update("auditAreaId", value);
            if (flow.auditFocusId && !focusOptions.some((focus) => String(focus.value) === String(flow.auditFocusId) && String(focus.areaId ?? "") === String(value))) {
              update("auditFocusId", "");
            }
          }}
          placeholder="Select scoped audit area"
          searchPlaceholder="Search scoped audit areas..."
          emptyMessage="No audit areas are included in this engagement scope."
        />
      </Field>
      <Field label="Audit focus">
        <SearchableSelect
          disabled={!editable || !flow.auditAreaId}
          options={focusOptions.filter((focus) => !focus.areaId || String(focus.areaId) === String(flow.auditAreaId))}
          value={flow.auditFocusId}
          onChange={(value) => update("auditFocusId", value)}
          placeholder={flow.auditAreaId ? "Select scoped audit focus" : "Select an audit area first"}
          searchPlaceholder="Search scoped audit focuses..."
          emptyMessage="No audit focuses are linked to this audit area."
        />
      </Field>
      <Field label="Core Document Version ID" hint="Optional exact document">
        <TextInput
          disabled={!editable}
          inputMode="numeric"
          value={flow.documentVersionId}
          onChange={(event) => update("documentVersionId", event.target.value)}
        />
      </Field>
      <Field label="Source reference" wide>
        <TextInput
          disabled={!editable}
          value={flow.sourceReference}
          onChange={(event) => update("sourceReference", event.target.value)}
          placeholder="Policy, interview, system, or other source reference"
        />
      </Field>
      <Field label="Scope statement" wide>
        <TextArea
          disabled={!editable}
          value={flow.scopeStatement}
          onChange={(event) => update("scopeStatement", event.target.value)}
          placeholder="Boundary and population covered by this flow"
        />
      </Field>
      <Field label="Description / narrative" wide>
        <TextArea
          disabled={!editable}
          value={flow.description}
          onChange={(event) => update("description", event.target.value)}
        />
      </Field>
      {[
        ["steps", "Steps"],
        ["inputs", "Inputs"],
        ["outputs", "Outputs"],
        ["recordsSystems", "Records and systems"],
        ["controls", "Controls"],
        ["decisionPoints", "Decision points"],
        ["riskPoints", "Risk points"],
      ].map(([key, title]) => (
        <Field key={key} label={title} hint="One entry per line">
          <TextArea
            disabled={!editable}
            value={listText(flow[key])}
            onChange={(event) => update(key, listValue(event.target.value))}
          />
        </Field>
      ))}
      <Field label="Limitations" wide>
        <TextArea
          disabled={!editable}
          value={flow.limitations}
          onChange={(event) => update("limitations", event.target.value)}
        />
      </Field>
    </div>
  );
}

function PlanningConformanceSection({
  kpis,
  plannedWorkingPapers,
  procedures,
  editable,
  onAddKpi,
  onUpdateKpi,
  onRemoveKpi,
  onAddWorkingPaper,
  onUpdateWorkingPaper,
  onRemoveWorkingPaper,
}) {
  return (
    <div className="space-y-5">
      <Panel
        title="Planning KPI workspace"
        description="Define measurable targets for the engagement, or record a formal not-applicable decision in the planning attributes."
        actions={
          editable && (
            <SmallButton icon={Plus} tone="primary" onClick={onAddKpi}>
              Add KPI
            </SmallButton>
          )
        }
      >
        {!kpis.length ? (
          <EmptyState
            title="No KPI records"
            description="Add at least one KPI before fieldwork, unless the backend records a justified not-applicable decision."
          />
        ) : (
          <div className="space-y-3">
            {kpis.map((kpi, index) => (
              <div
                className="grid gap-3 rounded-xl border border-slate-200 p-3 sm:grid-cols-4"
                key={`${kpi.code}-${index}`}
              >
                <Field label="Code">
                  <TextInput
                    disabled={!editable}
                    value={kpi.code ?? ""}
                    onChange={(event) =>
                      onUpdateKpi(index, "code", event.target.value)
                    }
                  />
                </Field>
                <Field label="Name">
                  <TextInput
                    disabled={!editable}
                    value={kpi.name ?? ""}
                    onChange={(event) =>
                      onUpdateKpi(index, "name", event.target.value)
                    }
                  />
                </Field>
                <Field label="Target">
                  <TextInput
                    disabled={!editable}
                    value={kpi.target ?? ""}
                    onChange={(event) =>
                      onUpdateKpi(index, "target", event.target.value)
                    }
                  />
                </Field>
                <Field label="Measurement method">
                  <TextInput
                    disabled={!editable}
                    value={kpi.measurementMethod ?? ""}
                    onChange={(event) =>
                      onUpdateKpi(
                        index,
                        "measurementMethod",
                        event.target.value,
                      )
                    }
                  />
                </Field>
                {editable && (
                  <div className="sm:col-span-4 flex justify-end">
                    <SmallButton
                      icon={XCircle}
                      tone="danger"
                      onClick={() => onRemoveKpi(index)}
                    >
                      Remove KPI
                    </SmallButton>
                  </div>
                )}
              </div>
            ))}
          </div>
        )}
      </Panel>
      <Panel
        title="Sampling and planned Working Paper traceability"
        description="Record the required work product and evidence before procedures are activated."
        actions={
          editable && (
            <SmallButton icon={Plus} tone="primary" onClick={onAddWorkingPaper}>
              Add planned Working Paper
            </SmallButton>
          )
        }
      >
        {!plannedWorkingPapers.length ? (
          <EmptyState
            title="No planned Working Paper requirements"
            description="Every required procedure and risk should have a planned Working Paper and evidence expectation."
          />
        ) : (
          <div className="space-y-3">
            {plannedWorkingPapers.map((paper, index) => (
              <div
                className="grid gap-3 rounded-xl border border-slate-200 p-3 sm:grid-cols-2"
                key={`${paper.reference}-${index}`}
              >
                <Field label="Reference">
                  <TextInput
                    disabled={!editable}
                    value={paper.reference ?? ""}
                    onChange={(event) =>
                      onUpdateWorkingPaper(
                        index,
                        "reference",
                        event.target.value,
                      )
                    }
                  />
                </Field>
                <Field label="Procedure">
                  <SearchableSelect
                    disabled={!editable}
                    options={procedures.map((procedure) => ({
                      value: procedure.id,
                      label: `${procedure.code} — ${procedure.objective}`,
                    }))}
                    value={paper.procedureId ?? ""}
                    onChange={(value) =>
                      onUpdateWorkingPaper(index, "procedureId", value)
                    }
                    placeholder="Select procedure"
                  />
                </Field>
                <Field label="Title">
                  <TextInput
                    disabled={!editable}
                    value={paper.title ?? ""}
                    onChange={(event) =>
                      onUpdateWorkingPaper(index, "title", event.target.value)
                    }
                  />
                </Field>
                <Field label="Required evidence">
                  <TextInput
                    disabled={!editable}
                    value={paper.requiredEvidence ?? ""}
                    onChange={(event) =>
                      onUpdateWorkingPaper(
                        index,
                        "requiredEvidence",
                        event.target.value,
                      )
                    }
                  />
                </Field>
                <Field label="Objective" wide>
                  <TextArea
                    disabled={!editable}
                    value={paper.objective ?? ""}
                    onChange={(event) =>
                      onUpdateWorkingPaper(
                        index,
                        "objective",
                        event.target.value,
                      )
                    }
                  />
                </Field>
                {editable && (
                  <div className="sm:col-span-2 flex justify-end">
                    <SmallButton
                      icon={XCircle}
                      tone="danger"
                      onClick={() => onRemoveWorkingPaper(index)}
                    >
                      Remove requirement
                    </SmallButton>
                  </div>
                )}
              </div>
            ))}
          </div>
        )}
      </Panel>
    </div>
  );
}

function RiskMatrixSection({
  form,
  editable,
  onChange,
  onChangeMatrix,
  onAddMatrix,
  onAdd,
  onEdit,
  onRemove,
  areaOptions,
  focusOptions,
  residualRatingOptions,
  onChangeFocus,
  onRemoveMatrix,
}) {
  return (
    <div className="space-y-5">
      <Panel
        title="Risk Matrix register"
        description="Multiple matrices can be recorded when the engagement covers authorized areas. The first matrix remains the compatibility baseline for existing traceability."
        actions={
          <>
            {!editable && <ReadOnlyBadge />}
            {editable && (
              <>
                <SmallButton icon={Plus} onClick={onAddMatrix}>
                  Add matrix
                </SmallButton>
                <SmallButton icon={Plus} tone="primary" onClick={onAdd}>
                  Add risk item
                </SmallButton>
              </>
            )}
          </>
        }
      >
        <div className="space-y-4">
          {(form.riskMatrices?.length
            ? form.riskMatrices
            : [form.riskMatrix]
          ).map((matrix, index) => (
            <div
              className="grid gap-4 rounded-xl border border-slate-200 p-4 sm:grid-cols-2"
              key={`${matrix.code}-${index}`}
            >
              <div className="flex items-center justify-between sm:col-span-2">
                <span className="text-xs font-bold uppercase tracking-wide text-slate-400">
                  Matrix {index + 1}
                </span>
                {editable && (
                  <SmallButton
                    icon={XCircle}
                    tone="danger"
                    onClick={() => onRemoveMatrix(index)}
                  >
                    Remove matrix
                  </SmallButton>
                )}
              </div>
              <Field label="Matrix code">
                <TextInput
                  disabled={!editable}
                  value={matrix.code ?? ""}
                  onChange={(event) =>
                    index === 0 && !form.riskMatrices?.length
                      ? onChange("riskMatrix", "code", event.target.value)
                      : onChangeMatrix(index, "code", event.target.value)
                  }
                />
              </Field>
              <Field label="Title">
                <TextInput
                  disabled={!editable}
                  value={matrix.title ?? ""}
                  onChange={(event) =>
                    index === 0 && !form.riskMatrices?.length
                      ? onChange("riskMatrix", "title", event.target.value)
                      : onChangeMatrix(index, "title", event.target.value)
                  }
                />
              </Field>
              <Field label="Audit area">
                <SearchableSelect
                  disabled={!editable}
                  options={areaOptions}
                  value={matrix.auditAreaId ?? ""}
                  onChange={(value) => onChangeMatrix(index, "auditAreaId", value)}
                  placeholder="Select scoped audit area"
                  searchPlaceholder="Search scoped audit areas..."
                />
              </Field>
              <Field label="Audit focus">
                <SearchableSelect
                  disabled={!editable || !matrix.auditAreaId}
                  options={[
                    {
                      value: "ALL",
                      label: "All scoped audit focuses",
                      keywords: "all focuses every focus",
                    },
                    ...focusOptions.filter(
                      (focus) =>
                        !focus.areaId ||
                        String(focus.areaId) === String(matrix.auditAreaId),
                    ),
                  ]}
                  value={
                    matrix.allAuditFocuses ? "ALL" : matrix.auditFocusId ?? ""
                  }
                  onChange={(value) => onChangeFocus(index, value)}
                  placeholder={matrix.auditAreaId ? "Select scoped audit focus" : "Select an audit area first"}
                  searchPlaceholder="Search scoped audit focuses..."
                />
              </Field>
              <Field label="Methodology" wide>
                <TextArea
                  disabled={!editable}
                  value={matrix.methodology ?? ""}
                  onChange={(event) =>
                    index === 0 && !form.riskMatrices?.length
                      ? onChange(
                          "riskMatrix",
                          "methodology",
                          event.target.value,
                        )
                      : onChangeMatrix(index, "methodology", event.target.value)
                  }
                />
              </Field>
              <Field label="Risk appetite">
                <TextInput
                  disabled={!editable}
                  value={matrix.riskAppetite ?? ""}
                  onChange={(event) =>
                    onChangeMatrix(index, "riskAppetite", event.target.value)
                  }
                />
              </Field>
              <Field label="Overall conclusion">
                <TextArea
                  disabled={!editable}
                  value={matrix.overallConclusion ?? ""}
                  onChange={(event) =>
                    onChangeMatrix(
                      index,
                      "overallConclusion",
                      event.target.value,
                    )
                  }
                />
              </Field>
            </div>
          ))}
        </div>
      </Panel>
      <Panel
        title="Risk Matrix Items"
        description="Open an item to inspect its control assessment and traceability links."
      >
        {!form.riskItems.length ? (
          <EmptyState
            title="No risk items recorded"
            description="Add risk items and link each one to an objective, approved-program procedure, and working-paper reference."
          />
        ) : (
          <div className="overflow-x-auto">
            <table className="min-w-[900px] w-full text-left text-sm">
              <thead className="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-400">
                <tr>
                  <th className="px-3 py-3">Code</th>
                  <th className="px-3 py-3">Risk statement</th>
                  <th className="px-3 py-3">Category</th>
                  <th className="px-3 py-3">Residual rating</th>
                  <th className="px-3 py-3">Traceability</th>
                  <th className="px-3 py-3 text-right">Action</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {form.riskItems.map((item, index) => (
                  <tr key={`${item.riskCode}-${index}`}>
                    <td className="px-3 py-3 font-bold text-slate-800">
                      {item.riskCode}
                    </td>
                    <td className="max-w-xs whitespace-pre-wrap px-3 py-3 text-slate-700">
                      {item.riskStatement || "—"}
                    </td>
                    <td className="px-3 py-3 text-slate-600">
                      {valueOrDash(item.riskCategory)}
                    </td>
                    <td className="px-3 py-3">
                      <StatusBadge
                        tone={
                          String(item.residualRating).toLowerCase().includes("high")
                            ? "danger"
                            : item.residualRating
                              ? "warning"
                              : "inactive"
                        }
                      >
                        {valueOrDash(
                          masterListLabel(
                            item.residualRating,
                            residualRatingOptions,
                          ),
                        )}
                      </StatusBadge>
                    </td>
                    <td className="px-3 py-3 text-xs text-slate-600">
                      {item.objectiveCodes?.length ?? 0} objectives ·{" "}
                      {item.procedureIds?.length ?? 0} procedures ·{" "}
                      {item.workingPapers?.length ?? 0} papers
                    </td>
                    <td className="px-3 py-3">
                      <div className="flex justify-end gap-2">
                        <SmallButton
                          icon={FilePenLine}
                          onClick={() => onEdit(item, index)}
                        >
                          {editable ? "Edit" : "Inspect"}
                        </SmallButton>
                        {editable && (
                          <SmallButton
                            icon={XCircle}
                            tone="danger"
                            onClick={() => onRemove(index)}
                          >
                            Remove
                          </SmallButton>
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Panel>
      <div className="text-xs leading-5 text-slate-500">
        The backend remains authoritative for risk completeness, source
        references, scope, and readiness. This page only edits and presents the
        planning package content.
      </div>
    </div>
  );
}

function RiskItemEditor({
  item,
  setItem,
  objectiveOptions,
  procedureOptions,
  officeOptions,
  areaOptions,
  focusOptions,
  processFlowOptions,
  controlEffectivenessOptions,
  residualRatingOptions,
  editable = true,
}) {
  const update = (key, value) =>
    setItem((current) => ({ ...current, [key]: value }));
  return (
    <div className="space-y-5">
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <Field label="Risk code">
          <TextInput
            disabled={!editable}
            value={item.riskCode}
            onChange={(event) => update("riskCode", event.target.value)}
          />
        </Field>
        <Field label="Risk category">
          <TextInput
            disabled={!editable}
            value={item.riskCategory}
            onChange={(event) => update("riskCategory", event.target.value)}
          />
        </Field>
        <Field label="Status">
          <TextInput
            disabled={!editable}
            value={item.status}
            onChange={(event) => update("status", event.target.value)}
          />
        </Field>
        <Field label="Risk statement" wide>
          <TextArea
            disabled={!editable}
            value={item.riskStatement}
            onChange={(event) => update("riskStatement", event.target.value)}
          />
        </Field>
        <Field label="Audit area">
          <SearchableSelect
            disabled={!editable}
            options={areaOptions}
            value={item.auditAreaId}
            onChange={(value) => update("auditAreaId", value)}
            placeholder="Select scoped audit area"
            searchPlaceholder="Search scoped audit areas..."
          />
        </Field>
        <Field label="Audit focus">
          <SearchableSelect
            disabled={!editable || !item.auditAreaId}
            options={focusOptions.filter((focus) => !focus.areaId || String(focus.areaId) === String(item.auditAreaId))}
            value={item.auditFocusId}
            onChange={(value) => update("auditFocusId", value)}
            placeholder={item.auditAreaId ? "Select scoped audit focus" : "Select an audit area first"}
            searchPlaceholder="Search scoped audit focuses..."
          />
        </Field>
        <Field label="Process flow">
          <SearchableSelect
            disabled={!editable}
            options={processFlowOptions}
            value={item.processFlowId}
            onChange={(value) => update("processFlowId", value)}
            placeholder="Select a planning process flow"
            searchPlaceholder="Search process flows..."
          />
        </Field>
        <Field label="Process name">
          <TextInput
            disabled={!editable}
            value={item.processName}
            onChange={(event) => update("processName", event.target.value)}
          />
        </Field>
        <Field label="Risk area">
          <TextInput
            disabled={!editable}
            value={item.riskArea}
            onChange={(event) => update("riskArea", event.target.value)}
          />
        </Field>
        <Field label="Responsible office">
          <SearchableSelect
            disabled={!editable}
            options={officeOptions}
            value={item.responsibleOfficeId}
            onChange={(value) => update("responsibleOfficeId", value)}
            placeholder="Select linked office"
          />
        </Field>
        <Field label="Risk response">
          <TextInput
            disabled={!editable}
            value={item.riskResponse}
            onChange={(event) => update("riskResponse", event.target.value)}
          />
        </Field>
      </div>
      <div className="grid gap-4 rounded-xl border border-slate-200 bg-slate-50 p-4 sm:grid-cols-3">
        <Field label="Inherent likelihood">
          <TextInput
            disabled={!editable}
            inputMode="decimal"
            min="0"
            step="0.01"
            type="number"
            value={item.inherentLikelihood}
            onChange={(event) =>
              update("inherentLikelihood", event.target.value)
            }
          />
        </Field>
        <Field label="Inherent impact">
          <TextInput
            disabled={!editable}
            inputMode="decimal"
            min="0"
            step="0.01"
            type="number"
            value={item.inherentImpact}
            onChange={(event) => update("inherentImpact", event.target.value)}
          />
        </Field>
        <Field label="Inherent score">
          <TextInput
            disabled={!editable}
            inputMode="decimal"
            min="0"
            step="0.01"
            type="number"
            value={item.inherentScore}
            onChange={(event) => update("inherentScore", event.target.value)}
          />
        </Field>
        <Field label="Residual likelihood">
          <TextInput
            disabled={!editable}
            inputMode="decimal"
            min="0"
            step="0.01"
            type="number"
            value={item.residualLikelihood}
            onChange={(event) =>
              update("residualLikelihood", event.target.value)
            }
          />
        </Field>
        <Field label="Residual impact">
          <TextInput
            disabled={!editable}
            inputMode="decimal"
            min="0"
            step="0.01"
            type="number"
            value={item.residualImpact}
            onChange={(event) => update("residualImpact", event.target.value)}
          />
        </Field>
        <Field label="Residual score">
          <TextInput
            disabled={!editable}
            inputMode="decimal"
            min="0"
            step="0.01"
            type="number"
            value={item.residualScore}
            onChange={(event) => update("residualScore", event.target.value)}
          />
        </Field>
        <Field label="Residual rating">
          <SearchableSelect
            disabled={!editable}
            options={residualRatingOptions}
            value={item.residualRating}
            onChange={(value) => update("residualRating", value)}
            placeholder="Select residual rating"
          />
        </Field>
        <Field label="Control effectiveness">
          <SearchableSelect
            disabled={!editable}
            options={controlEffectivenessOptions}
            value={item.controlEffectiveness}
            onChange={(value) => update("controlEffectiveness", value)}
            placeholder="Select control effectiveness"
          />
        </Field>
      </div>
      <Field label="Control description" wide>
        <TextArea
          disabled={!editable}
          value={item.controlDescription}
          onChange={(event) => update("controlDescription", event.target.value)}
        />
      </Field>
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="Planned audit approach" wide>
          <TextArea
            disabled={!editable}
            value={item.plannedAuditApproach}
            onChange={(event) =>
              update("plannedAuditApproach", event.target.value)
            }
          />
        </Field>
        <Field label="Criteria" wide>
          <TextArea
            disabled={!editable}
            value={item.criteria}
            onChange={(event) => update("criteria", event.target.value)}
          />
        </Field>
        <Field label="Response rationale" wide>
          <TextArea
            disabled={!editable}
            value={item.responseRationale}
            onChange={(event) =>
              update("responseRationale", event.target.value)
            }
          />
        </Field>
        <Field label="Linked planning objectives" hint="Select one or more">
          <SearchableSelect
            disabled={!editable}
            multiple
            multipleDisplay="summary"
            options={objectiveOptions}
            value={item.objectiveCodes}
            onChange={(value) => update("objectiveCodes", value)}
            placeholder="Select objectives"
          />
        </Field>
        <Field
          label="Linked approved-program procedures"
          hint="Select one or more"
        >
          <SearchableSelect
            disabled={!editable}
            multiple
            multipleDisplay="summary"
            options={procedureOptions}
            value={item.procedureIds}
            onChange={(value) => update("procedureIds", value)}
            placeholder="Select procedures"
          />
        </Field>
        <Field
          label="Working-paper references"
          hint="One reference per line"
          wide
        >
          <TextArea
            disabled={!editable}
            value={item.workingPaperText}
            onChange={(event) => update("workingPaperText", event.target.value)}
            placeholder="WP-01\nWP-02"
          />
        </Field>
      </div>
    </div>
  );
}

function TraceabilitySection({ workspace, form }) {
  const procedureById = new Map(
    (workspace.procedures ?? []).map((procedure) => [
      String(procedure.id),
      procedure,
    ]),
  );
  return (
    <div className="grid gap-5 xl:grid-cols-2">
      <Panel
        title="Source-to-planning lineage"
        description="Read-only references preserved from the approved IAP engagement source."
      >
        <MetadataGrid
          values={[
            ["Source type", label(workspace.lineage?.sourceType)],
            ["Plan engagement", workspace.lineage?.iapPlanEngagementId],
            ["Plan", workspace.lineage?.iapPlanId],
            ["Prioritization item", workspace.lineage?.iapPrioritizationItemId],
            ["Risk assessment", workspace.lineage?.iapRiskAssessmentId],
            ["Audit Universe item", workspace.lineage?.iapAuditUniverseItemId],
          ]}
        />
      </Panel>
      <Panel
        title="Risk-to-objective and procedure links"
        description="Every link is carried into the immutable version and checked by the backend readiness service."
      >
        {!form.riskItems.length ? (
          <EmptyState
            title="No traceability yet"
            description="Risk items will appear here after they are added."
          />
        ) : (
          <div className="space-y-3">
            {form.riskItems.map((item) => (
              <article
                className="rounded-xl border border-slate-200 p-3"
                key={item.riskCode}
              >
                <div className="flex items-center gap-2">
                  <Link2 className="text-sky-700" size={16} />
                  <strong className="text-slate-800">{item.riskCode}</strong>
                  <span className="text-sm text-slate-600">
                    {item.riskStatement || "Unstated risk"}
                  </span>
                </div>
                <div className="mt-3 grid gap-3 text-xs sm:grid-cols-3">
                  <div>
                    <p className="font-bold uppercase tracking-wide text-slate-400">
                      Objectives
                    </p>
                    <p className="mt-1 text-slate-700">
                      {item.objectiveCodes?.join(", ") || "None"}
                    </p>
                  </div>
                  <div>
                    <p className="font-bold uppercase tracking-wide text-slate-400">
                      Procedures
                    </p>
                    <p className="mt-1 text-slate-700">
                      {(item.procedureIds ?? [])
                        .map((id) => procedureById.get(String(id))?.code ?? id)
                        .join(", ") || "None"}
                    </p>
                  </div>
                  <div>
                    <p className="font-bold uppercase tracking-wide text-slate-400">
                      Working papers
                    </p>
                    <p className="mt-1 text-slate-700">
                      {(item.workingPapers ?? [])
                        .map((paper) => paper.reference)
                        .join(", ") || "None"}
                    </p>
                  </div>
                </div>
              </article>
            ))}
          </div>
        )}
      </Panel>
      <Panel
        title="Planning artifact references"
        description="Process flows and survey sources remain pinned to exact Core Document Versions when supplied."
      >
        <div className="space-y-3">
          {form.processFlows.map((flow) => (
            <div
              className="rounded-xl border border-slate-200 p-3"
              key={flow.code}
            >
              <div className="flex items-center justify-between gap-3">
                <strong className="text-slate-800">
                  {flow.code} — {flow.title}
                </strong>
                <span className="text-xs font-bold text-slate-500">
                  Document v{valueOrDash(flow.documentVersionId)}
                </span>
              </div>
              <p className="mt-1 text-sm text-slate-600">
                {flow.sourceReference || "No source reference recorded."}
              </p>
            </div>
          ))}
          {form.preliminarySurveyDocumentVersionId && (
            <div className="rounded-xl border border-slate-200 p-3 text-sm text-slate-700">
              Preliminary Survey Core Document Version:{" "}
              <strong>{form.preliminarySurveyDocumentVersionId}</strong>
            </div>
          )}
          {!form.processFlows.length &&
            !form.preliminarySurveyDocumentVersionId && (
              <EmptyState
                title="No exact document references"
                description="Optional Core Document Version references can be added while editing."
              />
            )}
        </div>
      </Panel>
    </div>
  );
}

function ReadinessSection({
  workspace,
  packageRecord,
  actionOptions,
  onAction,
}) {
  const conformanceReady = Boolean(workspace.readiness?.fieldworkReady);
  return (
    <div className="grid gap-5 xl:grid-cols-[1.1fr_0.9fr]">
      <Panel
        title="Planning readiness checklist"
        description="These checks come directly from the backend and are not recalculated in React."
      >
        <div className="space-y-2" data-testid="planning-readiness">
          {(workspace.readiness?.checks ?? []).map((check) => (
            <div
              className={`flex items-start gap-3 rounded-xl border px-3 py-3 ${check.met ? "border-emerald-200 bg-emerald-50" : "border-amber-200 bg-amber-50"}`}
              key={check.key}
            >
              {check.met ? (
                <CheckCircle2
                  className="mt-0.5 shrink-0 text-emerald-700"
                  size={18}
                />
              ) : (
                <ShieldAlert
                  className="mt-0.5 shrink-0 text-amber-700"
                  size={18}
                />
              )}
              <span className="text-sm font-semibold text-slate-700">
                {check.label}
              </span>
              <StatusBadge tone={check.met ? "success" : "warning"}>
                {check.met ? "Met" : "Open"}
              </StatusBadge>
            </div>
          ))}
        </div>
        <div
          className={`mt-4 rounded-xl px-4 py-3 text-sm font-bold ${conformanceReady ? "bg-emerald-100 text-emerald-900" : "bg-amber-100 text-amber-900"}`}
        >
          {conformanceReady
            ? "Planning conformance is complete; an approved baseline may authorize fieldwork."
            : "Planning conformance is incomplete. Fieldwork remains blocked until every structured planning gate is met."}
        </div>
      </Panel>
      <Panel
        title="Review and approval queue"
        description="Available actions are derived from backend capabilities and the signed-in user's permissions."
      >
        <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
          <p className="text-xs font-bold uppercase tracking-wide text-slate-400">
            Current state
          </p>
          <div className="mt-2 flex flex-wrap items-center gap-2">
            <StatusBadge
              tone={statusTones[packageRecord?.status] ?? "inactive"}
            >
              {label(packageRecord?.status ?? "Not created")}
            </StatusBadge>
            {packageRecord?.currentVersionNumber && (
              <span className="text-sm font-semibold text-slate-600">
                Version {packageRecord.currentVersionNumber}
              </span>
            )}
          </div>
        </div>
        <div className="mt-4 flex flex-wrap gap-2">
          {actionOptions.length ? (
            actionOptions.map(([key, text, Icon, tone]) => (
              <SmallButton
                key={key}
                icon={Icon}
                tone={tone}
                onClick={() => onAction(key)}
              >
                {text}
              </SmallButton>
            ))
          ) : (
            <p className="text-sm text-slate-500">
              No workflow action is currently available for this role and state.
            </p>
          )}
        </div>
        <div className="mt-5 border-t border-slate-100 pt-4">
          <h4 className="text-sm font-bold text-slate-700">Review history</h4>
          {!packageRecord?.reviews?.length ? (
            <p className="mt-2 text-sm text-slate-500">
              No independent review recorded for this package yet.
            </p>
          ) : (
            <div className="mt-3 space-y-3">
              {packageRecord.reviews.map((review) => (
                <div
                  className="rounded-xl border border-slate-200 p-3"
                  key={review.id}
                >
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <strong className="text-sm text-slate-700">
                      Version {review.versionNumber} ·{" "}
                      {review.reviewer?.name ?? "Reviewer"}
                    </strong>
                    <span className="text-xs text-slate-500">
                      {formatDate(review.reviewedAt, true)}
                    </span>
                  </div>
                  <p className="mt-1 text-sm leading-6 text-slate-600">
                    {review.comment || "No comment recorded."}
                  </p>
                </div>
              ))}
            </div>
          )}
        </div>
      </Panel>
    </div>
  );
}

function VersionsSection({
  packageRecord,
  versions,
  inspectedVersion,
  selectedVersionNumber,
  setSelectedVersionNumber,
  compareFrom,
  compareTo,
  setCompareFrom,
  setCompareTo,
  compareLeft,
  compareRight,
}) {
  const sectionDiffs =
    compareLeft && compareRight
      ? [
          [
            "Preliminary survey",
            JSON.stringify(compareLeft.preliminarySurvey) !==
              JSON.stringify(compareRight.preliminarySurvey),
          ],
          [
            "Planning attributes",
            JSON.stringify(compareLeft.planningAttributes) !==
              JSON.stringify(compareRight.planningAttributes),
          ],
          [
            "Objectives",
            JSON.stringify(compareLeft.objectives) !==
              JSON.stringify(compareRight.objectives),
          ],
          [
            "Process flows",
            JSON.stringify(compareLeft.processFlows) !==
              JSON.stringify(compareRight.processFlows),
          ],
          [
            "Risk matrix",
            JSON.stringify(compareLeft.riskMatrix) !==
              JSON.stringify(compareRight.riskMatrix),
          ],
        ]
      : [];
  return (
    <div className="space-y-5">
      <Panel
        title="Immutable version history"
        description="Approved versions cannot be edited. New changes are stored as new versions with their own checksum and change reason."
      >
        <div className="overflow-x-auto">
          <table className="min-w-[720px] w-full text-left text-sm">
            <thead className="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-400">
              <tr>
                <th className="px-3 py-3">Version</th>
                <th className="px-3 py-3">Created</th>
                <th className="px-3 py-3">Created by</th>
                <th className="px-3 py-3">Change reason</th>
                <th className="px-3 py-3 text-right">Inspect</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {versions.map((version) => (
                <tr key={version.id ?? version.versionNumber}>
                  <td className="px-3 py-3 font-bold text-slate-800">
                    v{version.versionNumber}
                    {version.versionNumber ===
                      packageRecord?.approvedVersionNumber && (
                      <span className="ml-2">
                        <StatusBadge tone="success">Approved</StatusBadge>
                      </span>
                    )}
                  </td>
                  <td className="px-3 py-3 text-slate-600">
                    {formatDate(version.createdAt, true)}
                  </td>
                  <td className="px-3 py-3 text-slate-600">
                    {version.createdBy?.name ?? "—"}
                  </td>
                  <td className="max-w-sm px-3 py-3 text-slate-600">
                    {version.changeReason || "Initial version"}
                  </td>
                  <td className="px-3 py-3 text-right">
                    <SmallButton
                      icon={
                        version.versionNumber === Number(selectedVersionNumber)
                          ? Check
                          : History
                      }
                      onClick={() =>
                        setSelectedVersionNumber(String(version.versionNumber))
                      }
                    >
                      {version.versionNumber === Number(selectedVersionNumber)
                        ? "Inspecting"
                        : "Inspect"}
                    </SmallButton>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Panel>
      <Panel
        title="Version comparison"
        description="Compare two immutable snapshots without changing the current working version."
      >
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="Compare from">
            <SearchableSelect
              options={versions.map((version) => ({
                value: version.versionNumber,
                label: `Version ${version.versionNumber}`,
              }))}
              value={compareFrom}
              onChange={(value) => setCompareFrom(String(value))}
              placeholder="Select version"
            />
          </Field>
          <Field label="Compare to">
            <SearchableSelect
              options={versions.map((version) => ({
                value: version.versionNumber,
                label: `Version ${version.versionNumber}`,
              }))}
              value={compareTo}
              onChange={(value) => setCompareTo(String(value))}
              placeholder="Select version"
            />
          </Field>
        </div>
        {compareLeft && compareRight ? (
          <div className="mt-4 grid gap-3 sm:grid-cols-2">
            {sectionDiffs.map(([name, changed]) => (
              <div
                className={`rounded-xl border px-3 py-3 ${changed ? "border-amber-200 bg-amber-50" : "border-emerald-200 bg-emerald-50"}`}
                key={name}
              >
                <div className="flex items-center gap-2">
                  {changed ? (
                    <GitCompareArrows className="text-amber-700" size={16} />
                  ) : (
                    <CheckCircle2 className="text-emerald-700" size={16} />
                  )}
                  <strong className="text-sm text-slate-700">{name}</strong>
                </div>
                <p className="mt-1 text-xs text-slate-600">
                  {changed
                    ? "Different between selected snapshots"
                    : "No change detected"}
                </p>
              </div>
            ))}
          </div>
        ) : (
          <p className="mt-4 text-sm text-slate-500">
            Select two versions to compare.
          </p>
        )}
      </Panel>
      {inspectedVersion && (
        <Panel
          title={`Inspecting version ${inspectedVersion.versionNumber}`}
          description="Inspection is read-only and does not change the current draft."
        >
          <div className="mb-4 flex flex-wrap items-center gap-2">
            <ReadOnlyBadge>
              {inspectedVersion.versionNumber ===
              packageRecord?.approvedVersionNumber
                ? "Approved immutable version"
                : "Immutable version snapshot"}
            </ReadOnlyBadge>
            <span className="text-xs text-slate-500">
              Checksum: {inspectedVersion.checksumSha256 ?? "—"}
            </span>
          </div>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div className="rounded-xl bg-slate-50 p-3">
              <p className="text-xs font-bold uppercase text-slate-400">
                Objectives
              </p>
              <p className="mt-1 text-lg font-bold text-slate-800">
                {inspectedVersion.objectives?.length ?? 0}
              </p>
            </div>
            <div className="rounded-xl bg-slate-50 p-3">
              <p className="text-xs font-bold uppercase text-slate-400">
                Process flows
              </p>
              <p className="mt-1 text-lg font-bold text-slate-800">
                {inspectedVersion.processFlows?.length ?? 0}
              </p>
            </div>
            <div className="rounded-xl bg-slate-50 p-3">
              <p className="text-xs font-bold uppercase text-slate-400">
                Risk items
              </p>
              <p className="mt-1 text-lg font-bold text-slate-800">
                {inspectedVersion.riskMatrix?.items?.length ?? 0}
              </p>
            </div>
            <div className="rounded-xl bg-slate-50 p-3">
              <p className="text-xs font-bold uppercase text-slate-400">
                Created
              </p>
              <p className="mt-1 text-sm font-bold text-slate-800">
                {formatDate(inspectedVersion.createdAt, true)}
              </p>
            </div>
          </div>
        </Panel>
      )}
    </div>
  );
}
