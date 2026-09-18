import { useCallback, useEffect, useMemo, useState } from "react";
import {
  BadgeCheck,
  CheckCircle2,
  ClipboardList,
  FileCheck2,
  FilePenLine,
  History,
  ListChecks,
  Play,
  Plus,
  RotateCcw,
  Send,
  Trash2,
  Undo2,
  UserCheck,
} from "lucide-react";
import { Link, useLocation, useSearchParams } from "react-router";
import { useAuth } from "../../auth/auth-context";
import Modal from "../../components/ui/Modal";
import RegistryHeader from "../../components/ui/RegistryHeader";
import SearchableSelect from "../../components/ui/SearchableSelect";
import StatusBadge from "../../components/ui/StatusBadge";
import SummaryCard from "../../components/ui/SummaryCard";
import AemsWorkspaceLockNotice from "../../components/aems/AemsWorkspaceLockNotice";
import { getAemsWorkspaceGate } from "../../components/aems/aemsPhaseGates";
import { hasPermission } from "../../config/navigation";
import {
  aemsEngagementApi,
  aemsProgramApi,
  ApiError,
} from "../../services/api";
import { useToast } from "../../ui/toast-context";

const emptyProgram = {
  title: "",
  objective: "",
  auditAreaId: "",
  auditTypeId: "",
  auditPeriodStart: "",
  auditPeriodEnd: "",
  auditCriteria: "",
  riskStatementSet: [],
  samplingApproach: "",
  plannedWorkingPaperRequirements: [],
};
const emptyProcedure = {
  id: null,
  procedureCode: "",
  sequenceNumber: 1,
  objective: "",
  procedureDescription: "",
  expectedEvidence: "",
  workingPaperReference: "",
  assignedTo: "",
  targetDate: "",
  auditAreaId: "",
  auditFocusId: "",
  processFlowId: "",
  processName: "",
  auditMethod: "",
  auditCriteria: "",
  plannedPersonDays: "",
  samplingRequirement: { method: "", population: "", sampleSize: "" },
  plannedWorkingPaperRequirement: { reference: "", requiredEvidence: "" },
  riskStatementIds: [],
  lockVersion: 1,
};
const emptyProgress = {
  status: "IN_PROGRESS",
  workingPaperReference: "",
  waiverReason: "",
  comment: "",
};
const emptyReview = {
  reviewerResult: "SATISFACTORY",
  reviewerComments: "",
};

const statusTones = {
  DRAFT: "inactive",
  PENDING_REVIEW: "warning",
  RETURNED_FOR_REVISION: "danger",
  RESUBMITTED: "warning",
  APPROVED: "success",
  ACTIVE: "active",
  COMPLETED: "success",
  SUPERSEDED: "inactive",
  NOT_STARTED: "inactive",
  IN_PROGRESS: "warning",
  WAIVED: "inactive",
};

function label(value) {
  return String(value ?? "")
    .replaceAll("_", " ")
    .replace(/\b\w/g, (character) => character.toUpperCase());
}

function date(value, time = false) {
  if (!value) return "—";
  return new Intl.DateTimeFormat("en-PH", {
    month: "short",
    day: "numeric",
    year: "numeric",
    ...(time ? { hour: "numeric", minute: "2-digit" } : {}),
  }).format(new Date(time ? value : `${value}T00:00:00`));
}

function Field({ label: fieldLabel, error, children, wide = false }) {
  return (
    <label
      className={`text-sm font-semibold text-slate-700 ${wide ? "sm:col-span-2" : ""}`}
    >
      {fieldLabel}
      <span className="mt-1.5 block">{children}</span>
      {error && <small className="mt-1 block text-red-600">{error[0]}</small>}
    </label>
  );
}

/**
 * Maintains the current Audit Program revision and its assigned procedures.
 * Approved revisions are immutable baselines; fieldwork updates are limited to
 * procedure progress, working-paper references, and independent review results.
 */
export default function AemsAuditProgramPage() {
  const { user } = useAuth();
  const toast = useToast();
  const location = useLocation();
  const [params, setParams] = useSearchParams();
  const [engagements, setEngagements] = useState([]);
  const [selectedId, setSelectedId] = useState(
    params.get("engagementId") ?? "",
  );
  const [selectedProgramId, setSelectedProgramId] = useState(
    params.get("programId") ?? "",
  );
  const [workspace, setWorkspace] = useState(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [errors, setErrors] = useState({});
  const [programForm, setProgramForm] = useState(emptyProgram);
  const [programOpen, setProgramOpen] = useState(false);
  const [procedureForm, setProcedureForm] = useState(emptyProcedure);
  const [procedureOpen, setProcedureOpen] = useState(false);
  const [selectedProcedure, setSelectedProcedure] = useState(null);
  const [action, setAction] = useState("");
  const [comment, setComment] = useState("");
  const [actionOpen, setActionOpen] = useState(false);
  const [progress, setProgress] = useState(emptyProgress);
  const [progressOpen, setProgressOpen] = useState(false);
  const [review, setReview] = useState(emptyReview);
  const [reviewOpen, setReviewOpen] = useState(false);

  const canManage = hasPermission(user, "aems.program.manage");
  const canReview = hasPermission(user, "aems.program.review");
  const canApprove = hasPermission(user, "aems.program.approve");

  const loadEngagements = useCallback(async () => {
    setLoading(true);
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
    } finally {
      setLoading(false);
    }
  }, []);

  const loadWorkspace = useCallback(async () => {
    if (!selectedId) {
      setWorkspace(null);
      return;
    }
    setLoading(true);
    setError("");
    try {
      const data = await aemsProgramApi.show(selectedId);
      setWorkspace(data);
      const currentPrograms = data.programs.filter(
        (program) => program.isCurrentRevision && !program.isArchived,
      );
      setSelectedProgramId((current) => {
        const exists = data.programs.some(
          (program) => String(program.id) === String(current),
        );
        return exists ? current : String(currentPrograms[0]?.id ?? "");
      });
    } catch (requestError) {
      setWorkspace(null);
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
    const timer = window.setTimeout(loadWorkspace, 0);
    return () => window.clearTimeout(timer);
  }, [loadWorkspace]);

  useEffect(() => {
    if (!selectedId) return;
    const next = { engagementId: selectedId };
    if (selectedProgramId) next.programId = selectedProgramId;
    setParams(next, { replace: true });
  }, [selectedId, selectedProgramId, setParams]);

  const program = workspace?.programs.find(
    (item) => String(item.id) === String(selectedProgramId),
  );
  const planningGate = getAemsWorkspaceGate("planning", workspace?.engagement);
  const planningUnlocked = planningGate.unlocked;
  const currentPrograms =
    workspace?.programs.filter(
      (item) => item.isCurrentRevision && !item.isArchived,
    ) ?? [];
  const approvedCount =
    currentPrograms.filter((item) =>
      ["APPROVED", "ACTIVE", "COMPLETED"].includes(item.status),
    ).length ?? 0;
  const completedProcedures =
    program?.procedures.filter((item) =>
      ["COMPLETED", "WAIVED"].includes(item.status),
    ).length ?? 0;

  const engagementOptions = engagements.map((engagement) => ({
    value: engagement.id,
    label: `${engagement.engagementCode} — ${engagement.title}`,
  }));
  const programOptions = (workspace?.programs ?? []).map((item) => ({
    value: item.id,
    label: `${item.programCode} r${item.revisionNumber} — ${item.title}`,
    keywords: `${item.status} ${item.isCurrentRevision ? "current" : "superseded"}`,
  }));
  const auditorOptions = (workspace?.team ?? [])
    .filter((member) => ["TEAM_LEADER", "AUDITOR"].includes(member.role))
    .map((member) => ({
      value: member.user.id,
      label: `${member.user.name} — ${label(member.role)}`,
      keywords: member.user.employeeId,
    }));
  const auditAreaOptions = (workspace?.engagement?.auditAreas ?? []).map(
    (area) => ({
      value: area.id,
      label: `${area.code ? `${area.code} — ` : ""}${area.name}`,
      keywords: area.name,
    }),
  );
  const auditFocusOptions = (workspace?.engagement?.auditFocuses ?? []).map(
    (focus) => ({
      value: focus.id,
      label: `${focus.code ? `${focus.code} — ` : ""}${focus.name}`,
      keywords: focus.name,
      auditAreaId: focus.auditAreaId,
    }),
  );
  const auditTypeOptions = workspace?.engagement?.auditType
    ? [
        {
          value: workspace.engagement.auditType.id,
          label: `${workspace.engagement.auditType.code ? `${workspace.engagement.auditType.code} — ` : ""}${workspace.engagement.auditType.label}`,
          keywords: workspace.engagement.auditType.label,
        },
      ]
    : [];
  const procedureAuditFocusOptions = procedureForm.auditAreaId
    ? auditFocusOptions.filter(
        (option) =>
          String(option.auditAreaId) === String(procedureForm.auditAreaId),
      )
    : auditFocusOptions;
  const planningProcessFlowOptions = (workspace?.planningProcessFlows ?? []).map(
    (flow) => ({
      value: flow.id,
      label: `${flow.code ? `${flow.code} — ` : ""}${flow.title || "Untitled process flow"}`,
      keywords: `${flow.code ?? ""} ${flow.title ?? ""}`,
      auditAreaId: flow.auditAreaId,
      auditFocusId: flow.auditFocusId,
    }),
  );
  const procedureProcessFlowOptions = planningProcessFlowOptions.filter(
    (option) => {
      const areaMatches =
        !procedureForm.auditAreaId ||
        !option.auditAreaId ||
        String(option.auditAreaId) === String(procedureForm.auditAreaId);
      const focusMatches =
        !procedureForm.auditFocusId ||
        !option.auditFocusId ||
        String(option.auditFocusId) === String(procedureForm.auditFocusId);
      return areaMatches && focusMatches;
    },
  );

  const actions = useMemo(() => {
    if (!program || !program.isCurrentRevision) return [];
    const available = [];
    if (planningUnlocked && program.status === "DRAFT" && canManage) {
      available.push(["SUBMIT", "Submit for review", Send, "primary"]);
    }
    if (
      planningUnlocked &&
      ["PENDING_REVIEW", "RESUBMITTED"].includes(program.status) &&
      canReview
    ) {
      available.push(["REVIEW", "Record review", FileCheck2, "primary"]);
      available.push(["RETURN", "Return for revision", Undo2, "warning"]);
    }
    if (
      planningUnlocked &&
      ["PENDING_REVIEW", "RESUBMITTED"].includes(program.status) &&
      canApprove
    ) {
      available.push(["APPROVE", "Approve baseline", BadgeCheck, "success"]);
    }
    if (planningUnlocked && program.status === "RETURNED_FOR_REVISION" && canManage) {
      available.push(["RESUBMIT", "Resubmit program", Send, "primary"]);
    }
    if (program.status === "APPROVED" && canApprove) {
      available.push(["START", "Start fieldwork", Play, "success"]);
      if (planningUnlocked) {
        available.push(["REVISE", "Create revision", RotateCcw, "warning"]);
      }
    }
    if (program.status === "ACTIVE") {
      if (canManage) {
        available.push([
          "COMPLETE",
          "Complete program",
          CheckCircle2,
          "success",
        ]);
      }
      if (planningUnlocked && canApprove) {
        available.push(["REVISE", "Create revision", RotateCcw, "warning"]);
      }
    }
    return available;
  }, [canApprove, canManage, canReview, planningUnlocked, program]);

  function openProgramForm() {
    if (!planningUnlocked) return;
    setErrors({});
    const engagementAreas = workspace?.engagement?.auditAreas ?? [];
    setProgramForm(
      program &&
        program.isCurrentRevision &&
        ["DRAFT", "RETURNED_FOR_REVISION"].includes(program.status)
        ? { ...emptyProgram, ...program }
        : {
            ...emptyProgram,
            auditAreaId:
              engagementAreas.length === 1 ? engagementAreas[0].id : "",
            auditTypeId: workspace?.engagement?.auditTypeId ?? "",
          },
    );
    setProgramOpen(true);
  }

  async function saveProgram() {
    setSaving(true);
    setErrors({});
    try {
      if (
        program &&
        program.isCurrentRevision &&
        ["DRAFT", "RETURNED_FOR_REVISION"].includes(program.status)
      ) {
        await aemsProgramApi.update(selectedId, program.id, {
          ...programForm,
          lockVersion: program.lockVersion,
        });
        toast.success("Audit Program updated.");
      } else {
        const created = await aemsProgramApi.create(selectedId, programForm);
        if (created?.id) setSelectedProgramId(String(created.id));
        toast.success("Draft Audit Program created.");
      }
      setProgramOpen(false);
      await loadWorkspace();
    } catch (requestError) {
      if (requestError instanceof ApiError) setErrors(requestError.errors);
      toast.error(requestError.message);
    } finally {
      setSaving(false);
    }
  }

  function openProcedure(item = null) {
    setErrors({});
    setProcedureForm(
      item
        ? { ...emptyProcedure, ...item, assignedTo: item.assignedTo ?? "" }
        : {
            ...emptyProcedure,
            sequenceNumber: (program?.procedures.length ?? 0) + 1,
            procedureCode: `PROC-${String((program?.procedures.length ?? 0) + 1).padStart(2, "0")}`,
            auditAreaId:
              workspace?.engagement?.auditAreas?.length === 1
                ? workspace.engagement.auditAreas[0].id
                : "",
          },
    );
    setProcedureOpen(true);
  }

  async function saveProcedure() {
    setSaving(true);
    setErrors({});
    try {
      const payload = {
        ...procedureForm,
        programLockVersion: program.lockVersion,
      };
      if (procedureForm.id) {
        await aemsProgramApi.updateProcedure(
          selectedId,
          program.id,
          procedureForm.id,
          payload,
        );
        toast.success("Audit procedure updated.");
      } else {
        await aemsProgramApi.addProcedure(selectedId, program.id, payload);
        toast.success("Audit procedure added.");
      }
      setProcedureOpen(false);
      await loadWorkspace();
    } catch (requestError) {
      if (requestError instanceof ApiError) setErrors(requestError.errors);
      toast.error(requestError.message);
    } finally {
      setSaving(false);
    }
  }

  function openAction(nextAction, procedure = null) {
    setAction(nextAction);
    setSelectedProcedure(procedure);
    setComment("");
    setErrors({});
    setActionOpen(true);
  }

  async function performAction() {
    setSaving(true);
    setErrors({});
    try {
      if (action === "REVISE") {
        const revised = await aemsProgramApi.revise(selectedId, program.id, {
          lockVersion: program.lockVersion,
          reason: comment,
        });
        if (revised?.id) setSelectedProgramId(String(revised.id));
      } else if (action === "DELETE_PROCEDURE") {
        await aemsProgramApi.removeProcedure(
          selectedId,
          program.id,
          selectedProcedure.id,
          {
            programLockVersion: program.lockVersion,
            lockVersion: selectedProcedure.lockVersion,
          },
        );
      } else {
        await aemsProgramApi.transition(selectedId, program.id, {
          action,
          lockVersion: program.lockVersion,
          comment: comment || null,
        });
      }
      toast.success(
        action === "DELETE_PROCEDURE"
          ? "Audit procedure archived."
          : `${label(action)} completed.`,
      );
      setActionOpen(false);
      await loadWorkspace();
    } catch (requestError) {
      if (requestError instanceof ApiError) setErrors(requestError.errors);
      toast.error(requestError.message);
    } finally {
      setSaving(false);
    }
  }

  function openProgress(procedure) {
    setSelectedProcedure(procedure);
    setProgress({
      ...emptyProgress,
      status:
        procedure.status === "NOT_STARTED" ? "IN_PROGRESS" : procedure.status,
      workingPaperReference: procedure.workingPaperReference ?? "",
    });
    setErrors({});
    setProgressOpen(true);
  }

  async function saveProgress() {
    setSaving(true);
    setErrors({});
    try {
      await aemsProgramApi.progressProcedure(
        selectedId,
        program.id,
        selectedProcedure.id,
        {
          ...progress,
          programLockVersion: program.lockVersion,
          lockVersion: selectedProcedure.lockVersion,
        },
      );
      toast.success("Procedure progress updated.");
      setProgressOpen(false);
      await loadWorkspace();
    } catch (requestError) {
      if (requestError instanceof ApiError) setErrors(requestError.errors);
      toast.error(requestError.message);
    } finally {
      setSaving(false);
    }
  }

  function openReview(procedure) {
    setSelectedProcedure(procedure);
    setReview({
      reviewerResult: procedure.reviewerResult ?? "SATISFACTORY",
      reviewerComments: procedure.reviewerComments ?? "",
    });
    setErrors({});
    setReviewOpen(true);
  }

  async function saveReview() {
    setSaving(true);
    setErrors({});
    try {
      await aemsProgramApi.reviewProcedure(
        selectedId,
        program.id,
        selectedProcedure.id,
        {
          ...review,
          programLockVersion: program.lockVersion,
          lockVersion: selectedProcedure.lockVersion,
        },
      );
      toast.success("Reviewer result recorded.");
      setReviewOpen(false);
      await loadWorkspace();
    } catch (requestError) {
      if (requestError instanceof ApiError) setErrors(requestError.errors);
      toast.error(requestError.message);
    } finally {
      setSaving(false);
    }
  }

  const inputClass =
    "h-11 w-full rounded-lg border border-slate-300 bg-white px-3 font-normal outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100";
  const textAreaClass =
    "min-h-24 w-full rounded-lg border border-slate-300 bg-white p-3 font-normal outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100";
  const editable =
    planningUnlocked &&
    program?.isCurrentRevision &&
    ["DRAFT", "RETURNED_FOR_REVISION"].includes(program.status);
  const procedureDetailsView = location.pathname.endsWith(
    "/audit-procedure-details",
  );
  const programWorkspacePath = `/audit-engagement-management/audit-program?engagementId=${selectedId}&programId=${selectedProgramId}`;
  const procedureDetailsPath = `/audit-engagement-management/audit-procedure-details?engagementId=${selectedId}&programId=${selectedProgramId}`;

  return (
    <main className="min-w-0 p-3 sm:p-5 lg:p-6">
      <RegistryHeader
        icon={ListChecks}
        title={procedureDetailsView ? "Audit Procedure Details" : "Audit Program"}
        description={
          procedureDetailsView
            ? "Add and assign procedures, preserve risk and process lineage, link expected evidence and working papers, and record review results."
            : "Translate the approved AEP into assigned procedures, approve the fieldwork baseline, track completion, and preserve documented revisions."
        }
        readOnly={!planningUnlocked || (!canManage && !canReview && !canApprove)}
        actions={
          canManage && planningUnlocked && selectedId && !procedureDetailsView ? (
            <button
              className="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white hover:bg-sky-800 disabled:opacity-50"
              disabled={!workspace?.approvedAep}
              onClick={() => {
                setSelectedProgramId("");
                setProgramForm(emptyProgram);
                setProgramOpen(true);
              }}
              type="button"
            >
              <Plus size={17} /> Create audit program
            </button>
          ) : null
        }
      />

      <section className="mb-5 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm lg:grid-cols-2">
        <label className="text-xs font-bold uppercase tracking-wide text-slate-500">
          Engagement
          <span className="mt-2 block normal-case">
            <SearchableSelect
              options={engagementOptions}
              placeholder="Select an engagement"
              value={selectedId}
              onChange={(value) => {
                setSelectedId(String(value));
                setSelectedProgramId("");
              }}
            />
          </span>
        </label>
        <label className="text-xs font-bold uppercase tracking-wide text-slate-500">
          Program and revision
          <span className="mt-2 block normal-case">
            <SearchableSelect
              options={programOptions}
              placeholder="Select a program"
              value={selectedProgramId}
              onChange={(value) => setSelectedProgramId(String(value))}
            />
          </span>
        </label>
      </section>

      {error && (
        <div className="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700">
          {error}
        </div>
      )}
      {workspace && !planningUnlocked && (
        <AemsWorkspaceLockNotice engagementId={selectedId} gate={planningGate} />
      )}
      {workspace && !workspace.approvedAep && (
        <div className="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
          <strong>Planning prerequisite:</strong> approve the current Audit
          Engagement Plan before creating an Audit Program.
        </div>
      )}

      {workspace && (
        <section className="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          {!procedureDetailsView ? (
            <>
              <SummaryCard
                icon={ClipboardList}
                label="Current programs"
                value={currentPrograms.length}
                tone="sky"
              />
              <SummaryCard
                icon={BadgeCheck}
                label="Approved baselines"
                value={approvedCount}
                tone="emerald"
              />
            </>
          ) : (
            <>
              <SummaryCard
                icon={ClipboardList}
                label="Selected program"
                value={program ? 1 : 0}
                tone="sky"
              />
              <SummaryCard
                icon={BadgeCheck}
                label="Program status"
                value={program ? label(program.status) : "—"}
                tone="emerald"
              />
            </>
          )}
          <SummaryCard
            icon={ListChecks}
            label="Procedures"
            value={program?.procedures.length ?? 0}
            tone="amber"
          />
          <SummaryCard
            icon={CheckCircle2}
            label="Completed or waived"
            value={completedProcedures}
            tone="slate"
          />
        </section>
      )}

      {workspace && !program && !loading && (
        <section className="grid min-h-64 place-items-center rounded-xl border border-dashed border-slate-300 bg-white p-6 text-center">
          <div className="max-w-xl">
            <ListChecks className="mx-auto text-sky-600" size={42} />
            <h2 className="mt-4 text-xl font-bold text-slate-800">
              No Audit Program selected
            </h2>
            <p className="mt-2 text-sm leading-6 text-slate-500">
              {procedureDetailsView
                ? "Select an Audit Program above to add or manage its procedures."
                : "Create a program from the approved AEP or select an existing revision above."}
            </p>
          </div>
        </section>
      )}

      {program && (
        <>
          <section className="mb-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <header className="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-start sm:justify-between sm:px-5">
              <div>
                <div className="flex flex-wrap items-center gap-2">
                  <h2 className="font-bold text-slate-800">
                    {program.programCode} · Revision {program.revisionNumber}
                  </h2>
                  <StatusBadge tone={statusTones[program.status] ?? "info"}>
                    {label(program.status)}
                  </StatusBadge>
                  {!program.isCurrentRevision && (
                    <StatusBadge tone="inactive">
                      Historical baseline
                    </StatusBadge>
                  )}
                </div>
                <h3 className="mt-2 text-lg font-bold text-slate-800">
                  {program.title}
                </h3>
                <p className="mt-2 max-w-4xl whitespace-pre-wrap text-sm leading-6 text-slate-600">
                  {program.objective}
                </p>
                {program.revisionReason && (
                  <p className="mt-2 text-xs text-amber-700">
                    Revision reason: {program.revisionReason}
                  </p>
                )}
              </div>
              <div className="flex flex-wrap gap-2">
                {!procedureDetailsView && planningUnlocked && editable && canManage && (
                  <button
                    className="inline-flex h-10 items-center gap-2 rounded-lg border border-sky-300 px-3 text-xs font-bold text-sky-700 hover:bg-sky-50"
                    onClick={openProgramForm}
                    type="button"
                  >
                    <FilePenLine size={15} /> Edit program
                  </button>
                )}
                {procedureDetailsView ? (
                  <>
                    <Link
                      className="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-300 px-3 text-xs font-bold text-slate-700 hover:bg-slate-50"
                      to={programWorkspacePath}
                    >
                      <ClipboardList size={15} /> Audit Program
                    </Link>
                    {planningUnlocked && editable && canManage && (
                      <button
                        className="inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-3 text-xs font-bold text-white hover:bg-sky-800"
                        onClick={() => openProcedure()}
                        type="button"
                      >
                        <Plus size={15} /> Add procedure
                      </button>
                    )}
                  </>
                ) : (
                  <Link
                    className="inline-flex h-10 items-center gap-2 rounded-lg border border-sky-300 px-3 text-xs font-bold text-sky-700 hover:bg-sky-50"
                    to={procedureDetailsPath}
                  >
                    <ListChecks size={15} /> Procedure details
                  </Link>
                )}
              </div>
            </header>

            <div className="grid gap-3 border-b border-slate-200 bg-slate-50 p-4 text-xs sm:grid-cols-4 sm:px-5">
              <div>
                <span className="block font-bold uppercase text-slate-400">
                  Prepared by
                </span>
                <strong className="mt-1 block text-slate-700">
                  {program.preparedBy?.name ?? "—"}
                </strong>
              </div>
              <div>
                <span className="block font-bold uppercase text-slate-400">
                  Submitted
                </span>
                <strong className="mt-1 block text-slate-700">
                  {date(program.submittedAt, true)}
                </strong>
              </div>
              <div>
                <span className="block font-bold uppercase text-slate-400">
                  Approved
                </span>
                <strong className="mt-1 block text-slate-700">
                  {date(program.approvedAt, true)}
                </strong>
              </div>
              <div>
                <span className="block font-bold uppercase text-slate-400">
                  AEP source
                </span>
                <strong className="mt-1 block text-slate-700">
                  {workspace.aep?.planCode} v{workspace.aep?.versionNumber}
                </strong>
              </div>
            </div>

            {procedureDetailsView ? (
            <div className="divide-y divide-slate-200">
              {program.procedures.map((procedure) => (
                <article className="p-4 sm:p-5" key={procedure.id}>
                  <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div className="min-w-0">
                      <div className="flex flex-wrap items-center gap-2">
                        <strong className="text-sky-800">
                          {procedure.procedureCode}
                        </strong>
                        <StatusBadge
                          tone={statusTones[procedure.status] ?? "info"}
                        >
                          {label(procedure.status)}
                        </StatusBadge>
                        {procedure.reviewerResult && (
                          <StatusBadge
                            tone={
                              procedure.reviewerResult === "SATISFACTORY"
                                ? "success"
                                : "warning"
                            }
                          >
                            {label(procedure.reviewerResult)}
                          </StatusBadge>
                        )}
                      </div>
                      <h3 className="mt-2 font-bold text-slate-800">
                        {procedure.sequenceNumber}. {procedure.objective}
                      </h3>
                      <p className="mt-1 whitespace-pre-wrap text-sm leading-6 text-slate-600">
                        {procedure.procedureDescription}
                      </p>
                    </div>
                    <div className="flex shrink-0 flex-wrap gap-2">
                      {editable && canManage && (
                        <>
                          <button
                            className="grid h-9 w-9 place-items-center rounded-lg border border-slate-200 text-sky-700 hover:bg-sky-50"
                            onClick={() => openProcedure(procedure)}
                            title="Edit procedure"
                            type="button"
                          >
                            <FilePenLine size={16} />
                          </button>
                          <button
                            className="grid h-9 w-9 place-items-center rounded-lg border border-red-200 text-red-600 hover:bg-red-50"
                            onClick={() =>
                              openAction("DELETE_PROCEDURE", procedure)
                            }
                            title="Archive procedure"
                            type="button"
                          >
                            <Trash2 size={16} />
                          </button>
                        </>
                      )}
                      {program.status === "ACTIVE" && canManage && (
                        <button
                          className="inline-flex h-9 items-center gap-2 rounded-lg border border-sky-300 px-3 text-xs font-bold text-sky-700 hover:bg-sky-50"
                          onClick={() => openProgress(procedure)}
                          type="button"
                        >
                          <CheckCircle2 size={15} /> Progress
                        </button>
                      )}
                      {program.status === "ACTIVE" &&
                        procedure.status === "COMPLETED" &&
                        canReview && (
                          <button
                            className="inline-flex h-9 items-center gap-2 rounded-lg border border-emerald-300 px-3 text-xs font-bold text-emerald-700 hover:bg-emerald-50"
                            onClick={() => openReview(procedure)}
                            type="button"
                          >
                            <UserCheck size={15} /> Review
                          </button>
                        )}
                    </div>
                  </div>
                  <dl className="mt-4 grid gap-3 rounded-xl bg-slate-50 p-3 text-xs sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                      <dt className="font-bold uppercase text-slate-400">
                        Responsible auditor
                      </dt>
                      <dd className="mt-1 font-semibold text-slate-700">
                        {procedure.assignee?.name ?? "—"}
                      </dd>
                    </div>
                    <div>
                      <dt className="font-bold uppercase text-slate-400">
                        Target date
                      </dt>
                      <dd className="mt-1 font-semibold text-slate-700">
                        {date(procedure.targetDate)}
                      </dd>
                    </div>
                    <div>
                      <dt className="font-bold uppercase text-slate-400">
                        Expected evidence
                      </dt>
                      <dd className="mt-1 leading-5 text-slate-700">
                        {procedure.expectedEvidence}
                      </dd>
                    </div>
                    <div>
                      <dt className="font-bold uppercase text-slate-400">
                        Working paper
                      </dt>
                      <dd className="mt-1 font-semibold text-slate-700">
                        {procedure.workingPaperReference ?? "Not linked"}
                      </dd>
                    </div>
                  </dl>
                  {procedure.reviewerComments && (
                    <p className="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-xs leading-5 text-emerald-900">
                      <strong>Reviewer:</strong> {procedure.reviewerComments} —{" "}
                      {procedure.reviewer?.name}
                    </p>
                  )}
                  {procedure.waiverReason && (
                    <p className="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs leading-5 text-amber-900">
                      <strong>Waiver:</strong> {procedure.waiverReason}
                    </p>
                  )}
                </article>
              ))}
              {!program.procedures.length && (
                <div className="p-8 text-center text-sm text-slate-500">
                  No procedures have been added to this revision.
                </div>
              )}
            </div>
            ) : (
              <div className="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <h3 className="font-bold text-slate-800">Program definition workspace</h3>
                  <p className="mt-1 text-sm leading-6 text-slate-600">
                    Manage this program’s objectives, criteria, sampling approach, revisions, and approval workflow here. Procedures are maintained separately in Audit Procedure Details.
                  </p>
                </div>
                <Link
                  className="inline-flex min-h-10 shrink-0 items-center justify-center gap-2 rounded-lg bg-sky-700 px-4 text-xs font-bold text-white hover:bg-sky-800"
                  to={procedureDetailsPath}
                >
                  <ListChecks size={15} /> Open procedure details
                </Link>
              </div>
            )}
          </section>

          {!procedureDetailsView && !!actions.length && (
            <section className="mb-5 rounded-xl border border-sky-200 bg-sky-50 p-4">
              <h2 className="text-sm font-bold text-slate-800">
                Available workflow actions
              </h2>
              <div className="mt-3 flex flex-wrap gap-2">
                {actions.map(([code, text, Icon, tone]) => (
                  <button
                    className={`inline-flex h-10 items-center gap-2 rounded-lg px-4 text-xs font-bold text-white ${
                      tone === "success"
                        ? "bg-emerald-700 hover:bg-emerald-800"
                        : tone === "warning"
                          ? "bg-amber-600 hover:bg-amber-700"
                          : "bg-sky-700 hover:bg-sky-800"
                    }`}
                    key={code}
                    onClick={() => openAction(code)}
                    type="button"
                  >
                    <Icon size={15} /> {text}
                  </button>
                ))}
              </div>
            </section>
          )}

          {!procedureDetailsView && <div className="grid gap-5 xl:grid-cols-2">
            <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
              <header className="border-b border-slate-200 px-4 py-3 sm:px-5">
                <h2 className="flex items-center gap-2 font-bold text-slate-800">
                  <History size={17} className="text-sky-700" /> Revision family
                </h2>
              </header>
              <div className="divide-y divide-slate-100">
                {(workspace.programs ?? [])
                  .filter((item) => item.programCode === program.programCode)
                  .map((item) => (
                    <button
                      className="grid w-full gap-1 px-4 py-3 text-left text-xs hover:bg-slate-50 sm:grid-cols-[7rem_1fr_auto] sm:px-5"
                      key={item.id}
                      onClick={() => setSelectedProgramId(String(item.id))}
                      type="button"
                    >
                      <strong className="text-sky-800">
                        Revision {item.revisionNumber}
                      </strong>
                      <span className="text-slate-600">
                        {item.revisionReason || "Initial program baseline"}
                      </span>
                      <span className="text-slate-400">
                        {label(item.status)}
                      </span>
                    </button>
                  ))}
              </div>
            </section>
            <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
              <header className="border-b border-slate-200 px-4 py-3 sm:px-5">
                <h2 className="font-bold text-slate-800">Workflow history</h2>
              </header>
              <div className="max-h-80 divide-y divide-slate-100 overflow-y-auto">
                {program.events.map((event) => (
                  <div className="px-4 py-3 text-xs sm:px-5" key={event.id}>
                    <div className="flex justify-between gap-3">
                      <strong className="text-sky-800">
                        {label(event.action.replace("PROGRAM_", ""))}
                      </strong>
                      <span className="text-slate-400">
                        {date(event.createdAt, true)}
                      </span>
                    </div>
                    <p className="mt-1 text-slate-600">
                      {event.comment ||
                        `${event.fromStatus ?? "New"} → ${event.toStatus}`}
                    </p>
                    <p className="mt-1 text-slate-400">{event.actor?.name}</p>
                  </div>
                ))}
              </div>
            </section>
          </div>}
        </>
      )}

      <Modal
        open={programOpen}
        onClose={() => !saving && setProgramOpen(false)}
        title={editable ? "Edit Audit Program" : "Create Audit Program"}
        description="The program is linked to the approved AEP. Once approved, definition changes require a formal revision."
        footer={
          <>
            <button
              className="h-10 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700"
              disabled={saving}
              onClick={() => setProgramOpen(false)}
              type="button"
            >
              Cancel
            </button>
            <button
              className="h-10 rounded-lg bg-sky-700 px-5 text-sm font-bold text-white disabled:opacity-60"
              disabled={saving}
              onClick={saveProgram}
              type="button"
            >
              {saving ? "Saving..." : "Save program"}
            </button>
          </>
        }
      >
        <div className="grid gap-4">
          <Field error={errors.title} label="Program title">
            <input
              className={inputClass}
              value={programForm.title}
              onChange={(event) =>
                setProgramForm((current) => ({
                  ...current,
                  title: event.target.value,
                }))
              }
            />
          </Field>
          <Field error={errors.objective} label="Program objective">
            <textarea
              className={textAreaClass}
              value={programForm.objective}
              onChange={(event) =>
                setProgramForm((current) => ({
                  ...current,
                  objective: event.target.value,
                }))
              }
            />
          </Field>
          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="Audit area">
              <SearchableSelect
                options={auditAreaOptions}
                placeholder="Select an audit area"
                searchPlaceholder="Search audit areas..."
                emptyMessage="No audit areas are configured on this engagement. Update its scope first."
                value={programForm.auditAreaId ?? ""}
                disabled={auditAreaOptions.length === 0}
                onChange={(value) =>
                  setProgramForm((current) => ({
                    ...current,
                    auditAreaId: value,
                  }))
                }
              />
            </Field>
            <Field label="Audit type">
              <SearchableSelect
                options={auditTypeOptions}
                placeholder="Select an audit type"
                searchPlaceholder="Search audit types..."
                emptyMessage="No audit type is configured on this engagement. Update it first."
                value={programForm.auditTypeId ?? ""}
                disabled={!workspace?.engagement?.auditTypeId}
                onChange={(value) =>
                  setProgramForm((current) => ({
                    ...current,
                    auditTypeId: value,
                  }))
                }
              />
            </Field>
            <Field label="Audit period start">
              <input
                className={inputClass}
                type="date"
                value={programForm.auditPeriodStart ?? ""}
                onChange={(event) =>
                  setProgramForm((current) => ({
                    ...current,
                    auditPeriodStart: event.target.value,
                  }))
                }
              />
            </Field>
            <Field label="Audit period end">
              <input
                className={inputClass}
                type="date"
                value={programForm.auditPeriodEnd ?? ""}
                onChange={(event) =>
                  setProgramForm((current) => ({
                    ...current,
                    auditPeriodEnd: event.target.value,
                  }))
                }
              />
            </Field>
            <Field label="Audit criteria" wide>
              <textarea
                className={textAreaClass}
                value={programForm.auditCriteria ?? ""}
                onChange={(event) =>
                  setProgramForm((current) => ({
                    ...current,
                    auditCriteria: event.target.value,
                  }))
                }
              />
            </Field>
            <Field label="Sampling approach" wide>
              <textarea
                className={textAreaClass}
                value={programForm.samplingApproach ?? ""}
                onChange={(event) =>
                  setProgramForm((current) => ({
                    ...current,
                    samplingApproach: event.target.value,
                  }))
                }
                placeholder="Population, sampling method, and rationale"
              />
            </Field>
          </div>
        </div>
      </Modal>

      <Modal
        open={procedureOpen}
        onClose={() => !saving && setProcedureOpen(false)}
        size="lg"
        title={
          procedureForm.id ? "Edit audit procedure" : "Add audit procedure"
        }
        description="Every baseline procedure has a stable number, assigned auditor, target date, expected evidence, and working-paper reference."
        footer={
          <>
            <button
              className="h-10 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700"
              disabled={saving}
              onClick={() => setProcedureOpen(false)}
              type="button"
            >
              Cancel
            </button>
            <button
              className="h-10 rounded-lg bg-sky-700 px-5 text-sm font-bold text-white disabled:opacity-60"
              disabled={saving}
              onClick={saveProcedure}
              type="button"
            >
              {saving ? "Saving..." : "Save procedure"}
            </button>
          </>
        }
      >
        <div className="grid gap-4 sm:grid-cols-2">
          <Field error={errors.procedureCode} label="Procedure number">
            <input
              className={inputClass}
              value={procedureForm.procedureCode}
              onChange={(event) =>
                setProcedureForm((current) => ({
                  ...current,
                  procedureCode: event.target.value,
                }))
              }
            />
          </Field>
          <Field error={errors.sequenceNumber} label="Display sequence">
            <input
              className={inputClass}
              min="1"
              type="number"
              value={procedureForm.sequenceNumber}
              onChange={(event) =>
                setProcedureForm((current) => ({
                  ...current,
                  sequenceNumber: event.target.value,
                }))
              }
            />
          </Field>
          <Field error={errors.objective} label="Procedure objective" wide>
            <textarea
              className={textAreaClass}
              value={procedureForm.objective}
              onChange={(event) =>
                setProcedureForm((current) => ({
                  ...current,
                  objective: event.target.value,
                }))
              }
            />
          </Field>
          <Field
            error={errors.procedureDescription}
            label="Procedure description"
            wide
          >
            <textarea
              className={textAreaClass}
              value={procedureForm.procedureDescription}
              onChange={(event) =>
                setProcedureForm((current) => ({
                  ...current,
                  procedureDescription: event.target.value,
                }))
              }
            />
          </Field>
          <Field error={errors.expectedEvidence} label="Expected evidence" wide>
            <textarea
              className={textAreaClass}
              value={procedureForm.expectedEvidence}
              onChange={(event) =>
                setProcedureForm((current) => ({
                  ...current,
                  expectedEvidence: event.target.value,
                }))
              }
            />
          </Field>
          <Field label="Audit area">
            <SearchableSelect
              options={auditAreaOptions}
              placeholder="Select an audit area"
              searchPlaceholder="Search audit areas..."
              emptyMessage="No audit areas are configured on this engagement."
              value={procedureForm.auditAreaId ?? ""}
              disabled={auditAreaOptions.length === 0}
              onChange={(value) =>
                setProcedureForm((current) => ({
                  ...current,
                  auditAreaId: value,
                  auditFocusId: "",
                  processFlowId: "",
                }))
              }
            />
          </Field>
          <Field label="Audit focus">
            <SearchableSelect
              options={procedureAuditFocusOptions}
              placeholder="Select an audit focus"
              searchPlaceholder="Search audit focuses..."
              emptyMessage="No audit focuses are configured for the selected engagement area."
              value={procedureForm.auditFocusId ?? ""}
              onChange={(value) =>
                setProcedureForm((current) => ({
                  ...current,
                  auditFocusId: value,
                  processFlowId: (() => {
                    const selectedFlow = planningProcessFlowOptions.find(
                      (option) => String(option.value) === String(current.processFlowId),
                    );
                    return selectedFlow &&
                      selectedFlow.auditFocusId &&
                      String(selectedFlow.auditFocusId) !== String(value)
                      ? ""
                      : current.processFlowId;
                  })(),
                }))
              }
            />
          </Field>
          <Field label="Process name">
            <input
              className={inputClass}
              value={procedureForm.processName ?? ""}
              onChange={(event) =>
                setProcedureForm((current) => ({
                  ...current,
                  processName: event.target.value,
                }))
              }
            />
          </Field>
          <Field label="Planning Process Flow">
            <SearchableSelect
              options={procedureProcessFlowOptions}
              placeholder="Select a planning process flow"
              searchPlaceholder="Search planning process flows..."
              emptyMessage={
                workspace?.planningProcessFlows?.length
                  ? "No planning process flows match the selected audit area."
                  : "No process flows are recorded in the current Planning Package."
              }
              value={procedureForm.processFlowId ?? ""}
              disabled={!workspace?.planningProcessFlows?.length}
              onChange={(value) =>
                setProcedureForm((current) => ({
                  ...current,
                  processFlowId: value,
                }))
              }
            />
          </Field>
          <Field label="Audit method">
            <input
              className={inputClass}
              value={procedureForm.auditMethod ?? ""}
              onChange={(event) =>
                setProcedureForm((current) => ({
                  ...current,
                  auditMethod: event.target.value,
                }))
              }
              placeholder="Inspection, interview, analysis..."
            />
          </Field>
          <Field label="Planned person-days">
            <input
              className={inputClass}
              type="number"
              min="0"
              step="0.25"
              value={procedureForm.plannedPersonDays ?? ""}
              onChange={(event) =>
                setProcedureForm((current) => ({
                  ...current,
                  plannedPersonDays: event.target.value,
                }))
              }
            />
          </Field>
          <Field label="Procedure criteria" wide>
            <textarea
              className={textAreaClass}
              value={procedureForm.auditCriteria ?? ""}
              onChange={(event) =>
                setProcedureForm((current) => ({
                  ...current,
                  auditCriteria: event.target.value,
                }))
              }
            />
          </Field>
          <Field label="Sampling method">
            <input
              className={inputClass}
              value={procedureForm.samplingRequirement?.method ?? ""}
              onChange={(event) =>
                setProcedureForm((current) => ({
                  ...current,
                  samplingRequirement: {
                    ...current.samplingRequirement,
                    method: event.target.value,
                  },
                }))
              }
            />
          </Field>
          <Field label="Sample size">
            <input
              className={inputClass}
              type="number"
              min="0"
              value={procedureForm.samplingRequirement?.sampleSize ?? ""}
              onChange={(event) =>
                setProcedureForm((current) => ({
                  ...current,
                  samplingRequirement: {
                    ...current.samplingRequirement,
                    sampleSize: event.target.value,
                  },
                }))
              }
            />
          </Field>
          <Field label="Population" wide>
            <textarea
              className={textAreaClass}
              value={procedureForm.samplingRequirement?.population ?? ""}
              onChange={(event) =>
                setProcedureForm((current) => ({
                  ...current,
                  samplingRequirement: {
                    ...current.samplingRequirement,
                    population: event.target.value,
                  },
                }))
              }
            />
          </Field>
          <Field label="Planned Working Paper reference">
            <input
              className={inputClass}
              value={
                procedureForm.plannedWorkingPaperRequirement?.reference ?? ""
              }
              onChange={(event) =>
                setProcedureForm((current) => ({
                  ...current,
                  plannedWorkingPaperRequirement: {
                    ...current.plannedWorkingPaperRequirement,
                    reference: event.target.value,
                  },
                }))
              }
            />
          </Field>
          <Field label="Planned WP required evidence">
            <input
              className={inputClass}
              value={
                procedureForm.plannedWorkingPaperRequirement
                  ?.requiredEvidence ?? ""
              }
              onChange={(event) =>
                setProcedureForm((current) => ({
                  ...current,
                  plannedWorkingPaperRequirement: {
                    ...current.plannedWorkingPaperRequirement,
                    requiredEvidence: event.target.value,
                  },
                }))
              }
            />
          </Field>
          <Field error={errors.assignedTo} label="Responsible auditor">
            <SearchableSelect
              options={auditorOptions}
              placeholder="Select assigned auditor"
              value={procedureForm.assignedTo}
              onChange={(value) =>
                setProcedureForm((current) => ({
                  ...current,
                  assignedTo: value,
                }))
              }
            />
          </Field>
          <Field error={errors.targetDate} label="Target date">
            <input
              className={inputClass}
              type="date"
              value={procedureForm.targetDate}
              onChange={(event) =>
                setProcedureForm((current) => ({
                  ...current,
                  targetDate: event.target.value,
                }))
              }
            />
          </Field>
          <Field
            error={errors.workingPaperReference}
            label="Working-paper reference"
            wide
          >
            <input
              className={inputClass}
              placeholder="May be assigned during fieldwork"
              value={procedureForm.workingPaperReference}
              onChange={(event) =>
                setProcedureForm((current) => ({
                  ...current,
                  workingPaperReference: event.target.value,
                }))
              }
            />
          </Field>
        </div>
      </Modal>

      <Modal
        open={actionOpen}
        onClose={() => !saving && setActionOpen(false)}
        size="sm"
        title={
          action === "DELETE_PROCEDURE"
            ? "Archive audit procedure?"
            : `${label(action)} Audit Program`
        }
        description={
          action === "DELETE_PROCEDURE"
            ? "The procedure is soft-deleted from this editable revision."
            : "The action records the actor, timestamp, comment, status transition, and program revision."
        }
        footer={
          <>
            <button
              className="h-10 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700"
              disabled={saving}
              onClick={() => setActionOpen(false)}
              type="button"
            >
              Cancel
            </button>
            <button
              className={`h-10 rounded-lg px-5 text-sm font-bold text-white disabled:opacity-60 ${
                action === "DELETE_PROCEDURE" ? "bg-red-600" : "bg-sky-700"
              }`}
              disabled={saving}
              onClick={performAction}
              type="button"
            >
              {saving ? "Processing..." : "Confirm action"}
            </button>
          </>
        }
      >
        {action !== "DELETE_PROCEDURE" && (
          <Field
            error={errors.comment || errors.reason || errors.action}
            label={
              ["RETURN", "REVISE"].includes(action)
                ? "Required reason"
                : "Comment"
            }
          >
            <textarea
              className={textAreaClass}
              value={comment}
              onChange={(event) => setComment(event.target.value)}
            />
          </Field>
        )}
      </Modal>

      <Modal
        open={progressOpen}
        onClose={() => !saving && setProgressOpen(false)}
        title={`Update ${selectedProcedure?.procedureCode ?? "procedure"}`}
        description="Progress updates do not change the approved procedure definition."
        footer={
          <>
            <button
              className="h-10 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700"
              disabled={saving}
              onClick={() => setProgressOpen(false)}
              type="button"
            >
              Cancel
            </button>
            <button
              className="h-10 rounded-lg bg-sky-700 px-5 text-sm font-bold text-white disabled:opacity-60"
              disabled={saving}
              onClick={saveProgress}
              type="button"
            >
              {saving ? "Saving..." : "Save progress"}
            </button>
          </>
        }
      >
        <div className="grid gap-4">
          <Field error={errors.status} label="Completion status">
            <SearchableSelect
              options={[
                { value: "NOT_STARTED", label: "Not Started" },
                { value: "IN_PROGRESS", label: "In Progress" },
                { value: "COMPLETED", label: "Completed" },
                { value: "WAIVED", label: "Waived" },
              ]}
              value={progress.status}
              onChange={(value) =>
                setProgress((current) => ({ ...current, status: value }))
              }
            />
          </Field>
          <Field
            error={errors.workingPaperReference}
            label="Working-paper reference"
          >
            <input
              className={inputClass}
              value={progress.workingPaperReference}
              onChange={(event) =>
                setProgress((current) => ({
                  ...current,
                  workingPaperReference: event.target.value,
                }))
              }
            />
          </Field>
          {progress.status === "WAIVED" && (
            <Field error={errors.waiverReason} label="Required waiver reason">
              <textarea
                className={textAreaClass}
                value={progress.waiverReason}
                onChange={(event) =>
                  setProgress((current) => ({
                    ...current,
                    waiverReason: event.target.value,
                  }))
                }
              />
            </Field>
          )}
          <Field error={errors.comment} label="Progress comment">
            <textarea
              className={textAreaClass}
              value={progress.comment}
              onChange={(event) =>
                setProgress((current) => ({
                  ...current,
                  comment: event.target.value,
                }))
              }
            />
          </Field>
        </div>
      </Modal>

      <Modal
        open={reviewOpen}
        onClose={() => !saving && setReviewOpen(false)}
        title={`Review ${selectedProcedure?.procedureCode ?? "procedure"}`}
        description="The responsible auditor cannot independently review their own completed procedure."
        footer={
          <>
            <button
              className="h-10 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700"
              disabled={saving}
              onClick={() => setReviewOpen(false)}
              type="button"
            >
              Cancel
            </button>
            <button
              className="h-10 rounded-lg bg-emerald-700 px-5 text-sm font-bold text-white disabled:opacity-60"
              disabled={saving}
              onClick={saveReview}
              type="button"
            >
              {saving ? "Saving..." : "Record review"}
            </button>
          </>
        }
      >
        <div className="grid gap-4">
          <Field error={errors.reviewerResult} label="Reviewer result">
            <SearchableSelect
              options={[
                { value: "SATISFACTORY", label: "Satisfactory" },
                { value: "NEEDS_REVISION", label: "Needs Revision" },
                { value: "NOT_APPLICABLE", label: "Not Applicable" },
              ]}
              value={review.reviewerResult}
              onChange={(value) =>
                setReview((current) => ({
                  ...current,
                  reviewerResult: value,
                }))
              }
            />
          </Field>
          <Field error={errors.reviewerComments} label="Reviewer comments">
            <textarea
              className={textAreaClass}
              value={review.reviewerComments}
              onChange={(event) =>
                setReview((current) => ({
                  ...current,
                  reviewerComments: event.target.value,
                }))
              }
            />
          </Field>
        </div>
      </Modal>
    </main>
  );
}
