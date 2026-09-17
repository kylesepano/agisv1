import { useCallback, useEffect, useMemo, useState } from "react";
import {
  ArrowRight,
  CalendarClock,
  CheckCircle2,
  ClipboardList,
  FileCheck2,
  FileText,
  Flag,
  History,
  Link2,
  MessageSquareText,
  Play,
  Plus,
  RefreshCw,
  RotateCcw,
  Send,
  ShieldAlert,
  XCircle,
} from "lucide-react";
import { Link, useSearchParams } from "react-router";
import { useAuth } from "../../auth/auth-context";
import AemsEngagementWorkspaceNav from "../../components/aems/AemsEngagementWorkspaceNav";
import AemsWorkspaceLockNotice from "../../components/aems/AemsWorkspaceLockNotice";
import { getAemsWorkspaceGate } from "../../components/aems/aemsPhaseGates";
import Modal from "../../components/ui/Modal";
import RegistryHeader from "../../components/ui/RegistryHeader";
import SearchableSelect from "../../components/ui/SearchableSelect";
import StatusBadge from "../../components/ui/StatusBadge";
import SummaryCard from "../../components/ui/SummaryCard";
import { hasPermission } from "../../config/navigation";
import {
  aemsEngagementApi,
  aemsFieldworkApi,
  aemsFindingApi,
  aemsWorkingPaperApi,
  ApiError,
} from "../../services/api";
import { useToast } from "../../ui/toast-context";

const today = new Date().toISOString().slice(0, 10);
const inputClass =
  "h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100";
const textAreaClass = `${inputClass} min-h-24 resize-y py-2.5`;

const statusTones = {
  DRAFT: "inactive",
  SUBMITTED: "warning",
  RETURNED_FOR_REVISION: "danger",
  RESUBMITTED: "warning",
  FINALIZED: "success",
  SUPERSEDED: "inactive",
  NOT_STARTED: "inactive",
  IN_PROGRESS: "warning",
  COMPLETED: "success",
  WAIVED: "inactive",
};

const emptyRecord = {
  recordType: "TESTING",
  procedureId: "",
  auditAreaId: "",
  auditFocusId: "",
  performedOn: today,
  location: "",
  objective: "",
  procedurePerformed: "",
  populationDescription: "",
  sampleDescription: "",
  analysis: "",
  result: "",
  conclusion: "",
  executionStatus: "IN_PROGRESS",
  participants: [{ participantName: "", participantRole: "" }],
  workingPaperIds: [],
  evidenceIds: [],
  relatedRecords: [],
};

const emptyTask = { title: "", assignee: "", dueDate: "" };

function label(value) {
  return String(value ?? "")
    .replaceAll("_", " ")
    .replace(/\b\w/g, (character) => character.toUpperCase());
}
function date(value, withTime = false) {
  if (!value) return "-";
  return new Intl.DateTimeFormat("en-PH", {
    month: "short",
    day: "numeric",
    year: "numeric",
    ...(withTime ? { hour: "numeric", minute: "2-digit" } : {}),
  }).format(new Date(withTime ? value : `${value}T00:00:00`));
}

function parseTask(value) {
  if (!value) return null;
  try {
    const parsed = JSON.parse(value);
    if (parsed && typeof parsed === "object") {
      return {
        title: String(parsed.title ?? ""),
        assignee: String(parsed.assignee ?? ""),
        dueDate: String(parsed.dueDate ?? ""),
      };
    }
  } catch {
    // Legacy task values are plain strings.
  }
  return { ...emptyTask, title: String(value) };
}

function taskText(task) {
  return [
    task.title,
    task.assignee ? `Assigned to ${task.assignee}` : "",
    task.dueDate ? `Due ${date(task.dueDate)}` : "",
  ]
    .filter(Boolean)
    .join(" - ");
}

function Field({ name, error, children, wide = false, hint }) {
  return (
    <label
      className={`text-sm font-semibold text-slate-700 ${wide ? "sm:col-span-2" : ""}`}
    >
      {name}
      {hint && <span className="ml-1 text-xs font-normal text-slate-400">{hint}</span>}
      <span className="mt-1.5 block">{children}</span>
      {error?.[0] && <small className="mt-1 block text-red-600">{error[0]}</small>}
    </label>
  );
}

function ActionButton({ children, tone = "slate", ...props }) {
  const tones = {
    slate: "border-slate-300 bg-white text-slate-700 hover:bg-slate-50",
    blue: "border-sky-600 bg-sky-600 text-white hover:bg-sky-700",
    green: "border-emerald-600 bg-emerald-600 text-white hover:bg-emerald-700",
    amber: "border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100",
    red: "border-red-300 bg-white text-red-700 hover:bg-red-50",
  };
  return (
    <button
      className={`inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border px-3 text-sm font-bold transition disabled:cursor-not-allowed disabled:opacity-50 ${tones[tone]}`}
      type="button"
      {...props}
    >
      {children}
    </button>
  );
}

function Info({ label: infoLabel, value }) {
  return (
    <div className="min-w-0 rounded-lg bg-slate-50 px-3 py-2.5">
      <dt className="text-[10px] font-bold uppercase tracking-wide text-slate-400">{infoLabel}</dt>
      <dd className="mt-1 break-words text-sm font-semibold leading-5 text-slate-700">{value || "-"}</dd>
    </div>
  );
}

export default function AemsExecutionWorkspacePage() {
  const { user } = useAuth();
  const toast = useToast();
  const [params, setParams] = useSearchParams();
  const [engagements, setEngagements] = useState([]);
  const [engagementId, setEngagementId] = useState(params.get("engagementId") ?? "");
  const [workspace, setWorkspace] = useState(null);
  const [workingPaperWorkspace, setWorkingPaperWorkspace] = useState(null);
  const [findingWorkspace, setFindingWorkspace] = useState(null);
  const [procedureId, setProcedureId] = useState(params.get("procedureId") ?? "");
  const [recordId, setRecordId] = useState(params.get("recordId") ?? "");
  const [query, setQuery] = useState("");
  const [recordType, setRecordType] = useState("");
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [errors, setErrors] = useState({});
  const [recordOpen, setRecordOpen] = useState(false);
  const [recordForm, setRecordForm] = useState(emptyRecord);
  const [editingRecord, setEditingRecord] = useState(null);
  const [tasks, setTasks] = useState([]);
  const [newTask, setNewTask] = useState(emptyTask);
  const [action, setAction] = useState("");
  const [comment, setComment] = useState("");
  const [actionOpen, setActionOpen] = useState(false);
  const [issueOpen, setIssueOpen] = useState(false);
  const [issueForm, setIssueForm] = useState({
    title: "",
    exceptionDescription: "",
    responsibleOfficeId: "",
    riskRatingId: "",
    workingPaperVersionIds: [],
    evidenceIds: [],
  });

  const canCreateRecord = hasPermission(user, "aems.fieldwork.create");
  const canReviewRecord = hasPermission(user, "aems.fieldwork.review");
  const canFinalizeRecord = hasPermission(user, "aems.fieldwork.finalize");
  const canPaperView = hasPermission(user, "aems.working-paper.view");
  const canIssueView = hasPermission(user, "aems.issue.view");
  const canIssueCreate = hasPermission(user, "aems.issue.create");

  const loadEngagements = useCallback(async () => {
    try {
      const data = await aemsEngagementApi.list({
        perPage: 100,
        sortBy: "updated_at",
        sortDirection: "desc",
      });
      setEngagements(data.engagements);
      setEngagementId((current) => current || String(data.engagements[0]?.id ?? ""));
    } catch (requestError) {
      setError(requestError.message);
    }
  }, []);

  const loadWorkspace = useCallback(async () => {
    if (!engagementId) {
      setWorkspace(null);
      setWorkingPaperWorkspace(null);
      setFindingWorkspace(null);
      setLoading(false);
      return;
    }
    setLoading(true);
    setError("");
    try {
      const [fieldwork, papers, findings] = await Promise.all([
        aemsFieldworkApi.show(engagementId),
        canPaperView
          ? aemsWorkingPaperApi.show(engagementId).catch(() => null)
          : Promise.resolve(null),
        canIssueView
          ? aemsFindingApi.show(engagementId).catch(() => null)
          : Promise.resolve(null),
      ]);
      setWorkspace(fieldwork);
      setWorkingPaperWorkspace(papers);
      setFindingWorkspace(findings);
      setProcedureId((current) =>
        fieldwork?.procedures?.some((item) => String(item.id) === String(current))
          ? current
          : String(fieldwork?.procedures?.[0]?.id ?? ""),
      );
      setRecordId((current) =>
        fieldwork?.records?.some((item) => String(item.id) === String(current))
          ? current
          : String(fieldwork?.records?.[0]?.id ?? ""),
      );
    } catch (requestError) {
      setWorkspace(null);
      setError(requestError.message);
    } finally {
      setLoading(false);
    }
  }, [canIssueView, canPaperView, engagementId]);

  useEffect(() => {
    const timer = window.setTimeout(loadEngagements, 0);
    return () => window.clearTimeout(timer);
  }, [loadEngagements]);

  useEffect(() => {
    const timer = window.setTimeout(loadWorkspace, 0);
    return () => window.clearTimeout(timer);
  }, [loadWorkspace]);

  useEffect(() => {
    if (!engagementId) return;
    const next = { engagementId: String(engagementId) };
    if (procedureId) next.procedureId = String(procedureId);
    if (recordId) next.recordId = String(recordId);
    setParams(next, { replace: true });
  }, [engagementId, procedureId, recordId, setParams]);

  const procedures = useMemo(() => workspace?.procedures ?? [], [workspace]);
  const records = useMemo(() => workspace?.records ?? [], [workspace]);
  const selectedProcedure = procedures.find((item) => String(item.id) === String(procedureId));
  const selectedRecord = records.find((item) => String(item.id) === String(recordId));
  const latestVersion = selectedRecord?.latestVersion;
  const filteredProcedures = useMemo(() => {
    const needle = query.trim().toLowerCase();
    return procedures.filter((item) => {
      if (!needle) return true;
      return [item.procedureCode, item.objective, item.description, item.status]
        .some((value) => String(value ?? "").toLowerCase().includes(needle));
    });
  }, [procedures, query]);
  const selectedRecords = useMemo(
    () =>
      records
        .filter((item) => {
          if (String(item.procedureId) !== String(procedureId)) return false;
          if (recordType && item.recordType !== recordType) return false;
          const needle = query.trim().toLowerCase();
          if (!needle) return true;
          return [item.recordCode, item.recordType, item.status]
            .some((value) => String(value ?? "").toLowerCase().includes(needle));
        })
        .sort((a, b) => String(a.recordCode).localeCompare(String(b.recordCode))),
    [procedureId, query, recordType, records],
  );

  const overdueProcedures = procedures.filter(
    (item) =>
      item.targetDate &&
      new Date(`${item.targetDate}T23:59:59`) < new Date() &&
      !["COMPLETED", "WAIVED"].includes(item.status),
  );
  const awaitingReview = records.filter((item) => ["SUBMITTED", "RESUBMITTED"].includes(item.status));
  const completedProcedures = procedures.filter((item) => ["COMPLETED", "WAIVED"].includes(item.status));
  const traceabilityComplete = workspace?.traceability?.complete ?? true;
  const blockers = [
    ...procedures
      .filter((item) => item.status === "COMPLETED" && !item.finalizedFieldworkRecords)
      .map((item) => `${item.procedureCode} is completed without a finalized Fieldwork Record.`),
    ...records
      .filter((item) => ["DRAFT", "RETURNED_FOR_REVISION"].includes(item.status))
      .filter((item) => {
        const version = item.latestVersion;
        return version?.executionStatus !== "COMPLETED" || !version?.workingPapers?.length || !version?.evidence?.length;
      })
      .map((item) => `${item.recordCode} needs completed execution, Working Paper, and Evidence traceability before submission.`),
  ];

  const engagementOptions = engagements.map((item) => ({
    value: item.id,
    label: `${item.engagementCode} - ${item.title}`,
  }));
  const procedureOptions = procedures.map((item) => ({
    value: item.id,
    label: `${item.procedureCode} - ${item.objective}`,
  }));
  const areaOptions = (workspace?.auditAreas ?? []).map((item) => ({
    value: item.id,
    label: `${item.code} - ${item.name}`,
  }));
  const focusOptions = (workspace?.auditFocuses ?? [])
    .filter((item) => !recordForm.auditAreaId || String(item.auditAreaId) === String(recordForm.auditAreaId))
    .map((item) => ({ value: item.id, label: `${item.code} - ${item.name}` }));
  const paperOptions = (workingPaperWorkspace?.workingPapers ?? [])
    .filter((item) => ["APPROVED"].includes(item.status))
    .map((item) => ({
      value: item.id,
      label: `${item.workingPaperCode} - ${item.title}`,
      description: `${label(item.status)}; ${item.latestVersion ? `v${item.latestVersion.versionNumber}` : "no version"}`,
    }));
  const evidenceOptions = (workingPaperWorkspace?.evidence ?? [])
    .filter((item) => item.isCurrentRevision && ["VERIFIED", "LOCKED"].includes(item.status))
    .map((item) => ({
      value: item.id,
      label: `${item.evidenceCode} - ${item.title}`,
      description: `${label(item.status)}; Core version ${item.documentVersionId ?? "-"}`,
    }));
  const issueOptions = {
    offices: (findingWorkspace?.offices ?? []).map((item) => ({ value: item.id, label: `${item.code} - ${item.name}` })),
    risks: (findingWorkspace?.riskRatings ?? []).map((item) => ({ value: item.id, label: item.label })),
    papers: (findingWorkspace?.workingPaperVersions ?? []).map((item) => ({
      value: item.id,
      label: `${item.workingPaper.workingPaperCode} v${item.versionNumber} - ${item.workingPaper.title}`,
    })),
    evidence: (findingWorkspace?.evidence ?? []).map((item) => ({
      value: item.id,
      label: `${item.evidenceCode} v${item.versionNumber} - ${item.title}`,
    })),
  };

  function showErrors(requestError) {
    setErrors(requestError instanceof ApiError ? requestError.errors : {});
    toast.error(requestError.message);
  }

  function selectProcedure(nextId) {
    setProcedureId(String(nextId));
    const first = records.find((item) => String(item.procedureId) === String(nextId));
    setRecordId(first ? String(first.id) : "");
  }

  function openCreateRecord() {
    const area = workspace?.auditAreas?.[0];
    const focus = workspace?.auditFocuses?.find((item) => String(item.auditAreaId) === String(area?.id));
    setEditingRecord(null);
    setRecordForm({
      ...emptyRecord,
      procedureId: selectedProcedure?.id ?? procedures[0]?.id ?? "",
      auditAreaId: area?.id ?? "",
      auditFocusId: focus?.id ?? "",
      objective: selectedProcedure?.objective ?? "",
    });
    setTasks([]);
    setNewTask(emptyTask);
    setErrors({});
    setRecordOpen(true);
  }

  function openEditRecord(record) {
    const version = record.latestVersion ?? {};
    const participant = version.participants?.[0];
    setEditingRecord(record);
    setRecordForm({
      ...emptyRecord,
      recordType: record.recordType,
      procedureId: record.procedureId,
      auditAreaId: version.auditArea?.id ?? record.auditArea?.id ?? "",
      auditFocusId: version.auditFocus?.id ?? record.auditFocus?.id ?? "",
      performedOn: version.performedOn ?? today,
      location: version.location ?? "",
      objective: version.objective ?? "",
      procedurePerformed: version.procedurePerformed ?? "",
      populationDescription: version.populationDescription ?? "",
      sampleDescription: version.sampleDescription ?? "",
      analysis: version.analysis ?? "",
      result: version.result ?? "",
      conclusion: version.conclusion ?? "",
      executionStatus: version.executionStatus ?? "IN_PROGRESS",
      participants: [{ participantName: participant?.name ?? "", participantRole: participant?.role ?? "" }],
      workingPaperIds: (version.workingPapers ?? []).map((item) => item.id),
      evidenceIds: (version.evidence ?? []).map((item) => item.id),
      relatedRecords: version.relatedRecords ?? [],
    });
    setTasks((version.relatedTasks ?? []).map(parseTask).filter(Boolean));
    setNewTask(emptyTask);
    setErrors({});
    setRecordOpen(true);
  }

  function addTask() {
    if (!newTask.title.trim()) return;
    setTasks((current) => [...current, { ...newTask, title: newTask.title.trim() }]);
    setNewTask(emptyTask);
  }

  async function saveRecord() {
    setSaving(true);
    setErrors({});
    try {
      const payload = {
        ...recordForm,
        procedureId: Number(recordForm.procedureId),
        auditAreaId: Number(recordForm.auditAreaId),
        auditFocusId: Number(recordForm.auditFocusId),
        participants: recordForm.participants.filter((item) => item.participantName?.trim()),
        relatedTasks: tasks.map((task) => JSON.stringify(task)),
        ...(editingRecord
          ? { lockVersion: editingRecord.lockVersion, changeReason: "Updated from the Execution Workspace." }
          : {}),
      };
      if (editingRecord) {
        await aemsFieldworkApi.update(engagementId, editingRecord.id, payload);
        toast.success("A new immutable Fieldwork Record version was saved.");
      } else {
        const created = await aemsFieldworkApi.create(engagementId, payload);
        if (created?.id) setRecordId(String(created.id));
        toast.success("Draft Fieldwork Record created.");
      }
      setRecordOpen(false);
      await loadWorkspace();
    } catch (requestError) {
      showErrors(requestError);
    } finally {
      setSaving(false);
    }
  }

  function openAction(nextAction) {
    setAction(nextAction);
    setComment("");
    setErrors({});
    setActionOpen(true);
  }

  async function performAction() {
    if (!selectedRecord) return;
    setSaving(true);
    setErrors({});
    try {
      const next = await aemsFieldworkApi.transition(engagementId, selectedRecord.id, {
        action,
        lockVersion: selectedRecord.lockVersion,
        comment: comment || null,
      });
      if (next?.id) setRecordId(String(next.id));
      toast.success(`${label(action)} completed.`);
      setActionOpen(false);
      await loadWorkspace();
    } catch (requestError) {
      showErrors(requestError);
    } finally {
      setSaving(false);
    }
  }

  function openIssue() {
    if (!selectedRecord || !latestVersion) return;
    setIssueForm({
      title: `${selectedRecord.recordCode} - ${selectedProcedure?.procedureCode ?? "Fieldwork exception"}`,
      exceptionDescription: latestVersion.result ?? "",
      responsibleOfficeId: "",
      riskRatingId: "",
      workingPaperVersionIds: (latestVersion.workingPapers ?? []).map((item) => item.workingPaperVersionId).filter(Boolean),
      evidenceIds: (latestVersion.evidence ?? []).map((item) => item.id),
    });
    setErrors({});
    setIssueOpen(true);
  }

  async function createIssue() {
    setSaving(true);
    setErrors({});
    try {
      const created = await aemsFindingApi.createIssue(engagementId, issueForm);
      toast.success("Draft issue created from the Fieldwork Record.");
      setIssueOpen(false);
      if (created?.id) {
        window.location.assign(`/audit-engagement-management/issues?engagementId=${engagementId}&issueId=${created.id}`);
      }
    } catch (requestError) {
      showErrors(requestError);
    } finally {
      setSaving(false);
    }
  }

  const recordActions = useMemo(() => {
    if (!selectedRecord) return [];
    const available = [];
    if (["DRAFT", "RETURNED_FOR_REVISION"].includes(selectedRecord.status) && canCreateRecord) {
      available.push(["EDIT", "Edit draft", FileText, "slate"]);
    }
    if (selectedRecord.status === "DRAFT" && canCreateRecord) available.push(["SUBMIT", "Submit", Send, "blue"]);
    if (["SUBMITTED", "RESUBMITTED"].includes(selectedRecord.status) && canReviewRecord) {
      available.push(["REVIEW", "Record review", FileCheck2, "blue"]);
      available.push(["RETURN", "Return for revision", RotateCcw, "amber"]);
    }
    if (["SUBMITTED", "RESUBMITTED"].includes(selectedRecord.status) && canFinalizeRecord) {
      available.push(["FINALIZE", "Finalize", CheckCircle2, "green"]);
    }
    if (selectedRecord.status === "RETURNED_FOR_REVISION" && canCreateRecord) available.push(["RESUBMIT", "Resubmit", Send, "blue"]);
    if (selectedRecord.status === "FINALIZED" && canCreateRecord) available.push(["REVISE", "Start correction", RotateCcw, "amber"]);
    return available;
  }, [canCreateRecord, canFinalizeRecord, canReviewRecord, selectedRecord]);

  const editable = editingRecord && ["DRAFT", "RETURNED_FOR_REVISION"].includes(editingRecord.status);
  const selectedEngagement = workspace?.engagement;
  const executionGate = getAemsWorkspaceGate("execution", selectedEngagement);

  return (
    <main className="min-w-0 p-3 sm:p-5 lg:p-6" data-testid="aems-execution-workspace">
      <RegistryHeader
        icon={Play}
        title="Execution Workspace"
        description="Execute procedures, preserve Fieldwork Record evidence, and navigate directly to Working Papers, Evidence, and Issues."
        readOnly={!canCreateRecord && !canReviewRecord && !canFinalizeRecord}
        actions={
          <>
            <ActionButton onClick={loadWorkspace} disabled={loading}>
              <RefreshCw size={16} className={loading ? "animate-spin" : ""} /> Refresh
            </ActionButton>
            {executionGate.unlocked && canCreateRecord && engagementId && (
              <ActionButton tone="blue" onClick={openCreateRecord}>
                <Plus size={16} /> New Fieldwork Record
              </ActionButton>
            )}
          </>
        }
      />

      <section className="mb-4 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm lg:grid-cols-[minmax(0,1fr)_minmax(18rem,0.55fr)]">
        <Field name="Engagement">
          <SearchableSelect
            options={engagementOptions}
            placeholder="Select an engagement"
            value={engagementId}
            onChange={(value) => {
              setEngagementId(String(value));
              setProcedureId("");
              setRecordId("");
            }}
          />
        </Field>
        {selectedEngagement && (
          <div className="flex items-end justify-end gap-2 text-right text-xs text-slate-500">
            <div>
              <strong className="block text-sm text-slate-800">{selectedEngagement.engagementCode}</strong>
              <span>{selectedEngagement.title}</span>
            </div>
            <StatusBadge tone={statusTones[selectedEngagement.status] ?? "info"}>{label(selectedEngagement.status)}</StatusBadge>
          </div>
        )}
      </section>

      {engagementId && (
        <AemsEngagementWorkspaceNav
          engagement={selectedEngagement}
          engagementId={engagementId}
        />
      )}

      {error && <div className="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700">{error}</div>}

      {selectedEngagement && !executionGate.unlocked && (
        <AemsWorkspaceLockNotice
          engagementId={engagementId}
          gate={executionGate}
        />
      )}

      {executionGate.unlocked && workspace && (
        <>
          <section className="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <SummaryCard icon={ClipboardList} label="Active procedures" value={procedures.length} tone="sky" />
            <SummaryCard icon={FileText} label="Fieldwork records" value={records.length} tone="emerald" />
            <SummaryCard icon={FileCheck2} label="Awaiting review" value={awaitingReview.length} tone="amber" />
            <SummaryCard icon={CalendarClock} label="Overdue procedures" value={overdueProcedures.length} tone="red" />
            <SummaryCard icon={CheckCircle2} label="Completed procedures" value={completedProcedures.length} tone="slate" />
          </section>

          {(!traceabilityComplete || blockers.length > 0) && (
            <section className="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 shadow-sm" data-testid="execution-blockers">
              <div className="flex items-start gap-3">
                <ShieldAlert className="mt-0.5 shrink-0 text-amber-700" size={20} />
                <div className="min-w-0">
                  <h2 className="font-bold">Execution blockers and overdue items</h2>
                  <p className="mt-1 text-xs leading-5">Complete and finalize linked execution records before completing procedures. Server-side readiness and locking remain authoritative.</p>
                  <ul className="mt-3 grid gap-2 sm:grid-cols-2">
                    {overdueProcedures.map((item) => <li className="rounded-lg border border-amber-200 bg-white/70 px-3 py-2 text-xs" key={`overdue-${item.id}`}><strong>{item.procedureCode}</strong> is overdue since {date(item.targetDate)}.</li>)}
                    {blockers.map((item, index) => <li className="rounded-lg border border-amber-200 bg-white/70 px-3 py-2 text-xs" key={`${item}-${index}`}>{item}</li>)}
                  </ul>
                </div>
              </div>
            </section>
          )}

          <section className="grid gap-5 xl:grid-cols-[minmax(18rem,0.72fr)_minmax(0,1.5fr)]">
            <aside className="min-w-0 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
              <header className="border-b border-slate-200 p-4">
                <div className="flex items-center justify-between gap-3">
                  <div><h2 className="font-bold text-slate-800">Procedures</h2><p className="mt-1 text-xs text-slate-500">Select a procedure to inspect execution.</p></div>
                  <StatusBadge tone={traceabilityComplete ? "success" : "warning"}>{traceabilityComplete ? "Traceable" : "Needs action"}</StatusBadge>
                </div>
                <input className={`${inputClass} mt-3`} placeholder="Search procedures or records" value={query} onChange={(event) => setQuery(event.target.value)} />
              </header>
              <div className="max-h-[42rem] divide-y divide-slate-100 overflow-y-auto">
                {filteredProcedures.map((procedure) => {
                  const procedureRecords = records.filter((item) => String(item.procedureId) === String(procedure.id));
                  const active = String(procedure.id) === String(procedureId);
                  const overdue = overdueProcedures.some((item) => item.id === procedure.id);
                  return (
                    <button className={`w-full border-l-4 p-4 text-left transition ${active ? "border-sky-600 bg-sky-50" : "border-transparent hover:bg-slate-50"}`} key={procedure.id} onClick={() => selectProcedure(procedure.id)} type="button">
                      <div className="flex items-start justify-between gap-2"><strong className="text-sm text-sky-800">{procedure.procedureCode}</strong><StatusBadge tone={statusTones[procedure.status] ?? "info"}>{label(procedure.status)}</StatusBadge></div>
                      <p className="mt-1 text-sm font-semibold leading-5 text-slate-800">{procedure.objective}</p>
                      <div className="mt-2 flex flex-wrap gap-2 text-[11px] text-slate-500"><span>{procedureRecords.length} record{procedureRecords.length === 1 ? "" : "s"}</span><span>Target {date(procedure.targetDate)}</span>{overdue && <span className="font-bold text-red-600">Overdue</span>}</div>
                    </button>
                  );
                })}
                {!filteredProcedures.length && <p className="p-6 text-center text-sm text-slate-500">No procedures match the current search.</p>}
              </div>
            </aside>

            <div className="min-w-0 space-y-5">
              {selectedProcedure ? (
                <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                  <header className="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-start sm:justify-between sm:px-5">
                    <div className="min-w-0"><div className="flex flex-wrap items-center gap-2"><strong className="text-sky-800">{selectedProcedure.procedureCode}</strong><StatusBadge tone={statusTones[selectedProcedure.status] ?? "info"}>{label(selectedProcedure.status)}</StatusBadge><StatusBadge tone={statusTones[selectedProcedure.fieldworkStatus] ?? "info"}>{label(selectedProcedure.fieldworkStatus)}</StatusBadge></div><h2 className="mt-2 text-lg font-bold text-slate-800">{selectedProcedure.objective}</h2><p className="mt-1 text-sm leading-6 text-slate-600">{selectedProcedure.description}</p>{selectedProcedure.auditCriteria && <p className="mt-2 rounded-lg border border-sky-200 bg-sky-50 p-3 text-xs leading-5 text-sky-800"><strong>Criteria traceability:</strong> {selectedProcedure.auditCriteria}</p>}</div>
                    <div className="flex flex-wrap gap-2"><Link className="inline-flex min-h-9 items-center gap-2 rounded-lg border border-sky-300 px-3 text-xs font-bold text-sky-700 hover:bg-sky-50" to={`/audit-engagement-management/audit-program?engagementId=${engagementId}&procedureId=${selectedProcedure.id}`}><ClipboardList size={15} /> Audit Program</Link></div>
                  </header>
                  <dl className="grid gap-3 border-b border-slate-200 bg-slate-50 p-4 sm:grid-cols-2 lg:grid-cols-4 sm:px-5"><Info label="Responsible auditor" value={selectedProcedure.assignee?.name} /><Info label="Target date" value={date(selectedProcedure.targetDate)} /><Info label="Review state" value={label(selectedProcedure.fieldworkReviewState)} /><Info label="Finalized records" value={selectedProcedure.finalizedFieldworkRecords} /></dl>
                  <div className="grid gap-4 p-4 sm:p-5 lg:grid-cols-2"><div><h3 className="flex items-center gap-2 text-sm font-bold text-slate-800"><MessageSquareText size={16} className="text-sky-700" /> Procedure results and conclusion</h3><p className="mt-2 whitespace-pre-wrap text-sm leading-6 text-slate-600">{selectedProcedure.fieldworkResults || "No finalized result recorded."}</p><p className="mt-2 whitespace-pre-wrap text-sm leading-6 text-slate-600">{selectedProcedure.fieldworkConclusion || "No finalized conclusion recorded."}</p></div><div><h3 className="flex items-center gap-2 text-sm font-bold text-slate-800"><Flag size={16} className="text-amber-600" /> Related tasks and records</h3><ul className="mt-2 space-y-2 text-sm text-slate-600">{(selectedProcedure.relatedTasks ?? []).map((item) => <li className="rounded-lg bg-slate-50 px-3 py-2" key={item}>{taskText(parseTask(item))}</li>)}{(selectedProcedure.relatedRecords ?? []).map((item) => <li className="rounded-lg bg-slate-50 px-3 py-2" key={item}><Link2 size={14} className="mr-1 inline text-slate-400" />{item}</li>)}{!selectedProcedure.relatedTasks?.length && !selectedProcedure.relatedRecords?.length && <li className="text-slate-400">No related tasks or records captured.</li>}</ul></div></div>
                  <div className="flex flex-wrap gap-2 border-t border-slate-200 p-4 sm:px-5"><Link className="inline-flex min-h-9 items-center gap-2 rounded-lg border border-emerald-300 px-3 text-xs font-bold text-emerald-700 hover:bg-emerald-50" to={`/audit-engagement-management/working-papers?engagementId=${engagementId}&procedureId=${selectedProcedure.id}`}><FileText size={15} /> Working Papers & Evidence</Link><Link className="inline-flex min-h-9 items-center gap-2 rounded-lg border border-amber-300 px-3 text-xs font-bold text-amber-700 hover:bg-amber-50" to={`/audit-engagement-management/issues?engagementId=${engagementId}&procedureId=${selectedProcedure.id}`}><ShieldAlert size={15} /> Audit Issues</Link></div>
                </section>
              ) : <section className="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">Select an Audit Program procedure to inspect its execution.</section>}

              <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <header className="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between sm:px-5"><div><h2 className="font-bold text-slate-800">Fieldwork Records</h2><p className="mt-1 text-xs text-slate-500">Immutable execution versions linked to the selected procedure.</p></div><div className="flex items-center gap-2"><select className={`${inputClass} h-10 min-w-40`} value={recordType} onChange={(event) => setRecordType(event.target.value)}><option value="">All types</option>{(workspace.recordTypes ?? []).map((item) => <option key={item} value={item}>{label(item)}</option>)}</select>{executionGate.unlocked && canCreateRecord && <ActionButton tone="blue" onClick={openCreateRecord}><Plus size={15} /> New record</ActionButton>}</div></header>
                <div className="grid gap-4 p-4 sm:p-5 lg:grid-cols-[minmax(15rem,0.72fr)_minmax(0,1.45fr)]">
                  <div className="space-y-2" data-testid="fieldwork-record-list">{selectedRecords.map((record) => <button className={`w-full rounded-xl border p-3 text-left transition ${String(record.id) === String(recordId) ? "border-sky-300 bg-sky-50 ring-1 ring-sky-200" : "border-slate-200 hover:border-sky-200 hover:bg-slate-50"}`} key={record.id} onClick={() => setRecordId(String(record.id))} type="button"><div className="flex items-center justify-between gap-2"><strong className="text-sm text-sky-800">{record.recordCode}</strong><StatusBadge tone={statusTones[record.status] ?? "info"}>{label(record.status)}</StatusBadge></div><p className="mt-1 text-xs font-semibold text-slate-700">{label(record.recordType)}</p><p className="mt-1 text-[11px] text-slate-500">Version {record.currentVersionNumber}; {record.latestVersion ? date(record.latestVersion.performedOn) : "not performed"}</p></button>)}{!selectedRecords.length && <p className="rounded-lg bg-slate-50 p-5 text-center text-sm text-slate-500">No Fieldwork Records for this procedure.</p>}</div>
                  {selectedRecord && latestVersion ? <RecordDetail record={selectedRecord} version={latestVersion} actions={recordActions} onAction={openAction} onEdit={() => openEditRecord(selectedRecord)} onIssue={canIssueCreate && canIssueView ? openIssue : null} engagementId={engagementId} /> : <div className="grid min-h-64 place-items-center rounded-xl border border-dashed border-slate-200 p-6 text-center text-sm text-slate-500">Select a Fieldwork Record to inspect its narrative, traceability, reviewer notes, and timeline.</div>}
                </div>
              </section>
            </div>
          </section>
        </>
      )}

      {!engagementId && !loading && <section className="grid min-h-72 place-items-center rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center"><div><Play className="mx-auto text-sky-600" size={40} /><h2 className="mt-3 text-lg font-bold text-slate-800">Select an engagement to begin</h2><p className="mt-1 text-sm text-slate-500">Execution context will keep procedures, Fieldwork Records, Working Papers, Evidence, and Issues linked.</p></div></section>}
      {loading && <div className="grid min-h-64 place-items-center rounded-xl border border-slate-200 bg-white text-sm text-slate-500">Loading Execution Workspace...</div>}

      <Modal open={recordOpen} onClose={() => !saving && setRecordOpen(false)} title={editingRecord ? "Edit Fieldwork Record version" : "New Fieldwork Record"} description="Capture execution narrative, participants, Working Paper and Evidence traceability. Finalized records are locked; corrections create revisions." size="xl" footer={<><ActionButton disabled={saving} onClick={() => setRecordOpen(false)}>Cancel</ActionButton><ActionButton disabled={saving} onClick={saveRecord} tone="blue">{saving ? "Saving..." : editable ? "Save new version" : "Create draft"}</ActionButton></>}>
        <div className="grid gap-4 sm:grid-cols-2"><Field name="Record type" error={errors.recordType}><select className={inputClass} value={recordForm.recordType} onChange={(event) => setRecordForm((current) => ({ ...current, recordType: event.target.value }))}>{(workspace?.recordTypes ?? []).map((item) => <option key={item} value={item}>{label(item)}</option>)}</select></Field><Field name="Execution status" error={errors.executionStatus}><select className={inputClass} value={recordForm.executionStatus} onChange={(event) => setRecordForm((current) => ({ ...current, executionStatus: event.target.value }))}>{(workspace?.executionStatuses ?? []).map((item) => <option key={item} value={item}>{label(item)}</option>)}</select></Field><Field name="Procedure" error={errors.procedureId} wide><SearchableSelect options={procedureOptions} value={recordForm.procedureId} onChange={(value) => setRecordForm((current) => ({ ...current, procedureId: value }))} /></Field><Field name="Audit Area" error={errors.auditAreaId}><SearchableSelect options={areaOptions} value={recordForm.auditAreaId} onChange={(value) => setRecordForm((current) => ({ ...current, auditAreaId: value, auditFocusId: "" }))} /></Field><Field name="Audit Focus" error={errors.auditFocusId}><SearchableSelect options={focusOptions} value={recordForm.auditFocusId} onChange={(value) => setRecordForm((current) => ({ ...current, auditFocusId: value }))} /></Field><Field name="Performed on" error={errors.performedOn}><input className={inputClass} type="date" value={recordForm.performedOn} onChange={(event) => setRecordForm((current) => ({ ...current, performedOn: event.target.value }))} /></Field><Field name="Location"><input className={inputClass} value={recordForm.location} onChange={(event) => setRecordForm((current) => ({ ...current, location: event.target.value }))} /></Field><Field name="Objective" wide><textarea className={textAreaClass} value={recordForm.objective} onChange={(event) => setRecordForm((current) => ({ ...current, objective: event.target.value }))} /></Field><Field name="Procedure performed" error={errors.procedurePerformed} wide><textarea className={textAreaClass} value={recordForm.procedurePerformed} onChange={(event) => setRecordForm((current) => ({ ...current, procedurePerformed: event.target.value }))} /></Field><Field name="Population"><textarea className={textAreaClass} value={recordForm.populationDescription} onChange={(event) => setRecordForm((current) => ({ ...current, populationDescription: event.target.value }))} /></Field><Field name="Sample"><textarea className={textAreaClass} value={recordForm.sampleDescription} onChange={(event) => setRecordForm((current) => ({ ...current, sampleDescription: event.target.value }))} /></Field><Field name="Analysis"><textarea className={textAreaClass} value={recordForm.analysis} onChange={(event) => setRecordForm((current) => ({ ...current, analysis: event.target.value }))} /></Field><Field name="Result" error={errors.result}><textarea className={textAreaClass} value={recordForm.result} onChange={(event) => setRecordForm((current) => ({ ...current, result: event.target.value }))} /></Field><Field name="Conclusion" error={errors.conclusion}><textarea className={textAreaClass} value={recordForm.conclusion} onChange={(event) => setRecordForm((current) => ({ ...current, conclusion: event.target.value }))} /></Field><Field name="Participant name" error={errors.participants}><input className={inputClass} value={recordForm.participants[0]?.participantName ?? ""} onChange={(event) => setRecordForm((current) => ({ ...current, participants: [{ ...current.participants[0], participantName: event.target.value }] }))} /></Field><Field name="Participant role"><input className={inputClass} value={recordForm.participants[0]?.participantRole ?? ""} onChange={(event) => setRecordForm((current) => ({ ...current, participants: [{ ...current.participants[0], participantRole: event.target.value }] }))} /></Field><Field name="Working Papers" error={errors.workingPaperIds} wide hint="Approved papers only"><SearchableSelect multiple multipleDisplay="summary" options={paperOptions} value={recordForm.workingPaperIds} onChange={(value) => setRecordForm((current) => ({ ...current, workingPaperIds: value }))} /></Field><Field name="Evidence" error={errors.evidenceIds} wide hint="Verified or locked evidence only"><SearchableSelect multiple multipleDisplay="summary" options={evidenceOptions} value={recordForm.evidenceIds} onChange={(value) => setRecordForm((current) => ({ ...current, evidenceIds: value }))} /></Field><Field name="Related records" wide><textarea className={textAreaClass} placeholder="One record reference per line" value={recordForm.relatedRecords.join("\n")} onChange={(event) => setRecordForm((current) => ({ ...current, relatedRecords: event.target.value.split("\n").map((item) => item.trim()).filter(Boolean) }))} /></Field></div>
        <div className="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4"><div className="flex items-center justify-between gap-3"><div><h3 className="text-sm font-bold text-slate-800">Assigned tasks and due dates</h3><p className="mt-1 text-xs text-slate-500">Task details are preserved in the versioned related-task snapshot.</p></div><CalendarClock size={18} className="text-sky-700" /></div><div className="mt-3 grid gap-3 sm:grid-cols-[1.4fr_1fr_10rem_auto]"><input className={inputClass} placeholder="Task" value={newTask.title} onChange={(event) => setNewTask((current) => ({ ...current, title: event.target.value }))} /><input className={inputClass} placeholder="Assignee" value={newTask.assignee} onChange={(event) => setNewTask((current) => ({ ...current, assignee: event.target.value }))} /><input className={inputClass} type="date" value={newTask.dueDate} onChange={(event) => setNewTask((current) => ({ ...current, dueDate: event.target.value }))} /><ActionButton onClick={addTask}><Plus size={15} /> Add</ActionButton></div>{tasks.length > 0 && <ul className="mt-3 space-y-2">{tasks.map((task, index) => <li className="flex items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs" key={`${task.title}-${index}`}><span><strong>{task.title}</strong>{task.assignee && <span className="ml-2 text-slate-500">{task.assignee}</span>}{task.dueDate && <span className="ml-2 text-slate-500">Due {date(task.dueDate)}</span>}</span><button className="text-red-600 hover:text-red-800" onClick={() => setTasks((current) => current.filter((_, taskIndex) => taskIndex !== index))} type="button"><XCircle size={15} /></button></li>)}</ul>}</div>
      </Modal>

      <Modal open={actionOpen} onClose={() => !saving && setActionOpen(false)} title={`${label(action)} Fieldwork Record`} description="Workflow actions are scope checked, separation-of-duties protected, and optimistic-lock protected." footer={<><ActionButton disabled={saving} onClick={() => setActionOpen(false)}>Cancel</ActionButton><ActionButton disabled={saving} onClick={performAction} tone={action === "FINALIZE" ? "green" : action === "RETURN" || action === "REVISE" ? "amber" : "blue"}>{saving ? "Saving..." : `Confirm ${label(action)}`}</ActionButton></>}><label className="text-sm font-semibold text-slate-700">{["RETURN", "REVISE"].includes(action) ? "Reason" : "Reviewer notes or comment"}<textarea className={`${textAreaClass} mt-2`} value={comment} onChange={(event) => setComment(event.target.value)} placeholder="Record the context for this workflow action." />{errors.comment?.[0] && <small className="mt-1 block text-red-600">{errors.comment[0]}</small>}</label>{selectedRecord && <p className="mt-3 rounded-lg bg-slate-50 p-3 text-xs text-slate-600"><strong>{selectedRecord.recordCode}</strong> is currently {label(selectedRecord.status)} at version {selectedRecord.currentVersionNumber}.</p>}</Modal>

      <Modal open={issueOpen} onClose={() => !saving && setIssueOpen(false)} title="Create Issue from Fieldwork" description="The issue inherits the selected Fieldwork Record's Working Paper and Evidence traceability." size="lg" footer={<><ActionButton disabled={saving} onClick={() => setIssueOpen(false)}>Cancel</ActionButton><ActionButton disabled={saving} onClick={createIssue} tone="blue">{saving ? "Creating..." : "Create draft issue"}</ActionButton></>}><div className="grid gap-4 sm:grid-cols-2"><Field name="Title" error={errors.title} wide><input className={inputClass} value={issueForm.title} onChange={(event) => setIssueForm((current) => ({ ...current, title: event.target.value }))} /></Field><Field name="Exception statement" error={errors.exceptionDescription} wide><textarea className={textAreaClass} value={issueForm.exceptionDescription} onChange={(event) => setIssueForm((current) => ({ ...current, exceptionDescription: event.target.value }))} /></Field><Field name="Responsible office" error={errors.responsibleOfficeId}><SearchableSelect options={issueOptions.offices} value={issueForm.responsibleOfficeId} onChange={(value) => setIssueForm((current) => ({ ...current, responsibleOfficeId: value }))} /></Field><Field name="Risk rating" error={errors.riskRatingId}><SearchableSelect options={issueOptions.risks} value={issueForm.riskRatingId} onChange={(value) => setIssueForm((current) => ({ ...current, riskRatingId: value }))} /></Field><Field name="Working Paper versions" error={errors.workingPaperVersionIds} wide><SearchableSelect multiple multipleDisplay="summary" options={issueOptions.papers} value={issueForm.workingPaperVersionIds} onChange={(value) => setIssueForm((current) => ({ ...current, workingPaperVersionIds: value }))} /></Field><Field name="Evidence versions" error={errors.evidenceIds} wide><SearchableSelect multiple multipleDisplay="summary" options={issueOptions.evidence} value={issueForm.evidenceIds} onChange={(value) => setIssueForm((current) => ({ ...current, evidenceIds: value }))} /></Field></div><p className="mt-4 rounded-lg border border-sky-200 bg-sky-50 p-3 text-xs leading-5 text-sky-800"><Link2 size={14} className="mr-1 inline" />The linked versions are copied from the selected Fieldwork Record. The issue remains a separate workflow and must be independently validated.</p></Modal>
    </main>
  );
}

function RecordDetail({ record, version, actions, onAction, onEdit, onIssue, engagementId }) {
  return (
    <article className="min-w-0 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
      <header className="flex flex-col gap-3 border-b border-slate-200 pb-4 sm:flex-row sm:items-start sm:justify-between"><div><div className="flex flex-wrap items-center gap-2"><strong className="text-sky-800">{record.recordCode}</strong><StatusBadge tone={statusTones[record.status] ?? "info"}>{label(record.status)}</StatusBadge><span className="text-xs text-slate-500">Version {record.currentVersionNumber}</span></div><h3 className="mt-2 text-lg font-bold text-slate-800">{label(record.recordType)}</h3><p className="mt-1 text-xs text-slate-500">Performed {date(version.performedOn)} at {version.location || "location not recorded"}</p></div><div className="flex flex-wrap gap-2">{actions.map(([code, text, Icon, tone]) => code === "EDIT" ? <ActionButton key={code} onClick={onEdit}><Icon size={15} /> {text}</ActionButton> : <ActionButton key={code} tone={tone} onClick={() => onAction(code)}><Icon size={15} /> {text}</ActionButton>)}{onIssue && <ActionButton tone="amber" onClick={onIssue}><ShieldAlert size={15} /> Create issue</ActionButton>}</div></header>
      <dl className="mt-4 grid gap-3 sm:grid-cols-2"><Info label="Objective" value={version.objective} /><Info label="Execution status" value={label(version.executionStatus)} /><Info label="Procedure performed" value={version.procedurePerformed} /><Info label="Population / sample" value={[version.populationDescription, version.sampleDescription].filter(Boolean).join(" / ")} /><Info label="Result" value={version.result} /><Info label="Conclusion" value={version.conclusion} /></dl>
      <div className="mt-5 grid gap-4 lg:grid-cols-2"><Traceability title="Working Papers" icon={FileText} items={version.workingPapers} engagementId={engagementId} kind="papers" /><Traceability title="Evidence" icon={FileCheck2} items={version.evidence} engagementId={engagementId} kind="evidence" /></div>
      {record.reviewerNotes && <div className="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm leading-6 text-emerald-900"><MessageSquareText size={16} className="mr-1 inline" /><strong>Reviewer notes:</strong> {record.reviewerNotes} {record.reviewedBy?.name && <span className="text-xs">({record.reviewedBy.name})</span>}</div>}
      <div className="mt-5 border-t border-slate-200 pt-4"><h4 className="flex items-center gap-2 text-sm font-bold text-slate-800"><History size={16} className="text-sky-700" /> Fieldwork timeline</h4><div className="mt-3 space-y-3">{(record.events ?? []).map((event) => <div className="relative border-l-2 border-sky-100 pl-4" key={event.id}><span className="absolute -left-[5px] top-1 h-2 w-2 rounded-full bg-sky-600" /><div className="flex flex-wrap justify-between gap-2"><strong className="text-xs text-sky-800">{label(event.action)}</strong><time className="text-[11px] text-slate-400">{date(event.createdAt, true)}</time></div><p className="mt-1 text-xs leading-5 text-slate-600">{event.comment || `${label(event.fromStatus)} -> ${label(event.toStatus)}`}</p><p className="text-[11px] text-slate-400">{event.actor?.name || "System actor"}</p></div>)}{!record.events?.length && <p className="text-xs text-slate-400">No timeline events recorded.</p>}</div></div>
    </article>
  );
}

function Traceability({ title, icon: Icon, items, engagementId, kind }) {
  return <section className="rounded-xl border border-slate-200 bg-slate-50 p-3"><h4 className="flex items-center gap-2 text-xs font-bold uppercase tracking-wide text-slate-500"><Icon size={15} className="text-sky-700" /> {title}</h4><ul className="mt-2 space-y-2">{(items ?? []).map((item) => <li className="flex items-start justify-between gap-2 rounded-lg bg-white px-3 py-2 text-xs" key={`${kind}-${item.id}-${item.workingPaperVersionId ?? item.documentVersionId ?? ""}`}><span className="min-w-0"><strong className="block text-slate-700">{item.code || item.evidenceCode || item.title}</strong><span className="block truncate text-slate-500">{item.title || `Core version ${item.documentVersionId ?? "-"}`} </span></span><Link className="shrink-0 text-sky-700 hover:text-sky-900" to={kind === "papers" ? `/audit-engagement-management/working-papers?engagementId=${engagementId}&paperId=${item.id}` : `/audit-engagement-management/working-papers?engagementId=${engagementId}&evidenceId=${item.id}`} title={`Open ${title}`}><ArrowRight size={15} /></Link></li>)}{!items?.length && <li className="text-xs text-slate-400">No {title.toLowerCase()} linked.</li>}</ul></section>;
}
