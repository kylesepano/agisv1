import { useCallback, useEffect, useMemo, useState } from "react";
import {
  BadgeCheck,
  Download,
  FileClock,
  FilePlus2,
  Files,
  History,
  Link2,
  LockKeyhole,
  Paperclip,
  RefreshCw,
  RotateCcw,
  Search,
  Send,
  ShieldCheck,
  Upload,
  XCircle,
} from "lucide-react";
import { useSearchParams } from "react-router";
import { useAuth } from "../../auth/auth-context";
import Modal from "../../components/ui/Modal";
import RegistryHeader from "../../components/ui/RegistryHeader";
import SearchableSelect from "../../components/ui/SearchableSelect";
import StatusBadge from "../../components/ui/StatusBadge";
import SummaryCard from "../../components/ui/SummaryCard";
import AemsEngagementWorkspaceNav from "../../components/aems/AemsEngagementWorkspaceNav";
import AemsWorkspaceLockNotice from "../../components/aems/AemsWorkspaceLockNotice";
import { getAemsWorkspaceGate } from "../../components/aems/aemsPhaseGates";
import { hasPermission } from "../../config/navigation";
import {
  aemsEngagementApi,
  aemsEvidenceApi,
  aemsWorkingPaperApi,
  ApiError,
} from "../../services/api";
import { useToast } from "../../ui/toast-context";

const emptyPaper = {
  procedureId: "",
  title: "",
  objective: "",
  procedurePerformed: "",
  populationDescription: "",
  sampleDescription: "",
  result: "",
  conclusion: "",
  noEvidenceReason: "",
  crossReferences: "",
  evidenceIds: [],
};

const emptyEvidence = {
  title: "",
  evidenceCategoryId: "",
  evidenceSourceTypeId: "",
  sourceDescription: "",
  dateObtained: "",
  custodianName: "",
  custodianOfficeId: "",
  confidentialityLevelId: "",
  acquisitionMethod: "",
  acquisitionForm: "",
  planningObjectiveId: "",
  riskMatrixItemId: "",
  controlReference: "",
  workingPaperIds: [],
  changeReason: "",
  file: null,
};

const statusTones = {
  DRAFT: "inactive",
  SUBMITTED: "warning",
  RETURNED_FOR_REVISION: "danger",
  RESUBMITTED: "warning",
  APPROVED: "success",
  VOIDED: "danger",
  VERIFIED: "success",
  LOCKED: "active",
};

function label(value) {
  return String(value ?? "")
    .replaceAll("_", " ")
    .replace(/\b\w/g, (character) => character.toUpperCase());
}

function date(value, withTime = false) {
  if (!value) return "—";
  return new Intl.DateTimeFormat("en-PH", {
    month: "short",
    day: "numeric",
    year: "numeric",
    ...(withTime ? { hour: "numeric", minute: "2-digit" } : {}),
  }).format(new Date(withTime ? value : `${value}T00:00:00`));
}

function bytes(value) {
  const size = Number(value || 0);
  if (size < 1024) return `${size} B`;
  if (size < 1024 ** 2) return `${(size / 1024).toFixed(1)} KB`;
  return `${(size / 1024 ** 2).toFixed(1)} MB`;
}

function Field({ label: fieldLabel, error, children, wide = false, hint }) {
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

const inputClass =
  "h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100";
const textAreaClass = `${inputClass} min-h-24 resize-y py-2.5`;

/**
 * Runs procedure-linked Working Paper review and immutable evidence governance.
 */
export default function AemsWorkingPapersPage() {
  const { user } = useAuth();
  const toast = useToast();
  const [params, setParams] = useSearchParams();
  const [engagements, setEngagements] = useState([]);
  const [engagementId, setEngagementId] = useState(
    params.get("engagementId") ?? "",
  );
  const [workspace, setWorkspace] = useState(null);
  const [tab, setTab] = useState("papers");
  const [query, setQuery] = useState("");
  const [selectedPaperId, setSelectedPaperId] = useState("");
  const [selectedEvidenceId, setSelectedEvidenceId] = useState("");
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [errors, setErrors] = useState({});
  const [paperOpen, setPaperOpen] = useState(false);
  const [paperForm, setPaperForm] = useState(emptyPaper);
  const [editingPaper, setEditingPaper] = useState(null);
  const [paperAction, setPaperAction] = useState("");
  const [actionComment, setActionComment] = useState("");
  const [actionOpen, setActionOpen] = useState(false);
  const [evidenceOpen, setEvidenceOpen] = useState(false);
  const [evidenceForm, setEvidenceForm] = useState(emptyEvidence);
  const [replacingEvidence, setReplacingEvidence] = useState(null);
  const [evidenceAction, setEvidenceAction] = useState("");
  const [evidenceReason, setEvidenceReason] = useState("");
  const [evidenceActionOpen, setEvidenceActionOpen] = useState(false);

  const canPrepare = hasPermission(user, "aems.working-paper.create");
  const canReview = hasPermission(user, "aems.working-paper.review");
  const canApprove = hasPermission(user, "aems.working-paper.approve");
  const canUploadEvidence = hasPermission(user, "aems.evidence.upload");
  const canVerifyEvidence = hasPermission(user, "aems.evidence.verify");
  const canVoidEvidence = hasPermission(user, "aems.evidence.void");

  const loadEngagements = useCallback(async () => {
    setLoading(true);
    try {
      const data = await aemsEngagementApi.list({
        perPage: 100,
        sortBy: "updated_at",
        sortDirection: "desc",
      });
      setEngagements(data.engagements);
      setEngagementId(
        (current) => current || String(data.engagements[0]?.id ?? ""),
      );
    } catch (requestError) {
      setError(requestError.message);
    } finally {
      setLoading(false);
    }
  }, []);

  const loadWorkspace = useCallback(async () => {
    if (!engagementId) {
      setWorkspace(null);
      return;
    }
    setLoading(true);
    setError("");
    try {
      const data = await aemsWorkingPaperApi.show(engagementId);
      setWorkspace(data);
      setSelectedPaperId((current) =>
        data.workingPapers.some((paper) => String(paper.id) === String(current))
          ? current
          : String(data.workingPapers[0]?.id ?? ""),
      );
      const currentEvidence = data.evidence.filter(
        (record) => record.isCurrentRevision,
      );
      setSelectedEvidenceId((current) =>
        currentEvidence.some((record) => String(record.id) === String(current))
          ? current
          : String(currentEvidence[0]?.id ?? ""),
      );
    } catch (requestError) {
      setWorkspace(null);
      setError(requestError.message);
    } finally {
      setLoading(false);
    }
  }, [engagementId]);

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
    setParams({ engagementId }, { replace: true });
  }, [engagementId, setParams]);

  const filteredPapers = useMemo(() => {
    const normalized = query.trim().toLowerCase();
    if (!normalized) return workspace?.workingPapers ?? [];
    return (workspace?.workingPapers ?? []).filter((paper) =>
      [
        paper.workingPaperCode,
        paper.title,
        paper.procedure?.procedureCode,
        paper.status,
      ].some((value) =>
        String(value ?? "")
          .toLowerCase()
          .includes(normalized),
      ),
    );
  }, [query, workspace]);

  const currentEvidence = useMemo(
    () =>
      (workspace?.evidence ?? []).filter((record) => record.isCurrentRevision),
    [workspace],
  );
  const filteredEvidence = useMemo(() => {
    const normalized = query.trim().toLowerCase();
    if (!normalized) return currentEvidence;
    return currentEvidence.filter((record) =>
      [
        record.evidenceCode,
        record.title,
        record.status,
        record.evidenceCategory?.label,
        record.fileName,
      ].some((value) =>
        String(value ?? "")
          .toLowerCase()
          .includes(normalized),
      ),
    );
  }, [currentEvidence, query]);

  const selectedPaper = (workspace?.workingPapers ?? []).find(
    (paper) => String(paper.id) === String(selectedPaperId),
  );
  const selectedEvidence = currentEvidence.find(
    (record) => String(record.id) === String(selectedEvidenceId),
  );
  const evidenceHistory = selectedEvidence
    ? (workspace?.evidence ?? []).filter(
        (record) => record.familyUuid === selectedEvidence.familyUuid,
      )
    : [];

  const engagementOptions = engagements.map((engagement) => ({
    value: engagement.id,
    label: `${engagement.engagementCode} — ${engagement.title}`,
  }));
  const procedureOptions = (workspace?.procedures ?? []).map((procedure) => ({
    value: procedure.id,
    label: `${procedure.program?.program_code ?? "AP"} / ${procedure.procedureCode}`,
    description: procedure.objective,
  }));
  const evidenceOptions = currentEvidence
    .filter((record) => ["VERIFIED", "LOCKED"].includes(record.status))
    .map((record) => ({
      value: record.id,
      label: `${record.evidenceCode} v${record.versionNumber} — ${record.title}`,
      description: `${label(record.status)} · ${record.fileName}`,
    }));
  const planningObjectiveOptions = (workspace?.planningObjectives ?? []).map(
    (objective) => ({
      value: objective.id,
      label: `${objective.code} — ${objective.statement}`,
      description: objective.sourceReference || "Current planning package objective",
    }),
  );
  const riskMatrixItemOptions = (workspace?.riskMatrixItems ?? []).map(
    (item) => ({
      value: item.id,
      label: `${item.riskCode} — ${item.riskStatement}`,
      description: [
        item.matrixCode && item.matrixTitle
          ? `${item.matrixCode} — ${item.matrixTitle}`
          : null,
        item.auditArea?.code && item.auditFocus?.code
          ? `${item.auditArea.code} / ${item.auditFocus.code}`
          : item.auditArea?.code,
      ]
        .filter(Boolean)
        .join(" · "),
    }),
  );
  const paperOptions = (workspace?.workingPapers ?? []).map((paper) => ({
    value: paper.id,
    label: `${paper.workingPaperCode} — ${paper.title}`,
  }));

  function showErrors(requestError) {
    setErrors(requestError instanceof ApiError ? requestError.errors : {});
    toast.error(requestError.message);
  }

  function openCreatePaper() {
    setEditingPaper(null);
    setPaperForm({
      ...emptyPaper,
      procedureId: workspace?.procedures?.[0]?.id ?? "",
    });
    setErrors({});
    setPaperOpen(true);
  }

  function openEditPaper(paper) {
    const version = paper.latestVersion;
    setEditingPaper(paper);
    setPaperForm({
      procedureId: paper.procedureId,
      title: paper.title,
      objective: version.objective ?? "",
      procedurePerformed: version.procedurePerformed ?? "",
      populationDescription: version.populationDescription ?? "",
      sampleDescription: version.sampleDescription ?? "",
      result: version.result ?? "",
      conclusion: version.conclusion ?? "",
      noEvidenceReason: version.noEvidenceReason ?? "",
      crossReferences: (version.crossReferences ?? []).join("\n"),
      evidenceIds: (version.evidence ?? []).map((record) => record.id),
    });
    setErrors({});
    setPaperOpen(true);
  }

  async function savePaper() {
    setSaving(true);
    setErrors({});
    try {
      const payload = {
        ...paperForm,
        crossReferences: paperForm.crossReferences
          .split("\n")
          .map((value) => value.trim())
          .filter(Boolean),
        ...(editingPaper
          ? {
              lockVersion: editingPaper.lockVersion,
              changeReason: "Updated during Working Paper preparation.",
            }
          : {}),
      };
      if (editingPaper) {
        await aemsWorkingPaperApi.update(
          engagementId,
          editingPaper.id,
          payload,
        );
      } else {
        await aemsWorkingPaperApi.create(engagementId, payload);
      }
      toast.success(
        editingPaper
          ? "New Working Paper content version saved."
          : "Draft Working Paper created.",
      );
      setPaperOpen(false);
      await loadWorkspace();
    } catch (requestError) {
      showErrors(requestError);
    } finally {
      setSaving(false);
    }
  }

  function openPaperAction(action) {
    setPaperAction(action);
    setActionComment("");
    setErrors({});
    setActionOpen(true);
  }

  async function performPaperAction() {
    if (!selectedPaper) return;
    setSaving(true);
    setErrors({});
    try {
      if (paperAction === "REVISE") {
        await aemsWorkingPaperApi.revise(engagementId, selectedPaper.id, {
          lockVersion: selectedPaper.lockVersion,
          reason: actionComment,
        });
      } else {
        await aemsWorkingPaperApi.transition(engagementId, selectedPaper.id, {
          action: paperAction,
          lockVersion: selectedPaper.lockVersion,
          comment: actionComment || undefined,
        });
      }
      toast.success(`${label(paperAction)} completed.`);
      setActionOpen(false);
      await loadWorkspace();
    } catch (requestError) {
      showErrors(requestError);
    } finally {
      setSaving(false);
    }
  }

  function openUploadEvidence() {
    setReplacingEvidence(null);
    setEvidenceForm({
      ...emptyEvidence,
      evidenceCategoryId: workspace?.evidenceCategories?.[0]?.id ?? "",
      evidenceSourceTypeId: workspace?.evidenceSourceTypes?.[0]?.id ?? "",
      confidentialityLevelId:
        workspace?.confidentialityLevels?.find(
          (level) => level.code === "INTERNAL",
        )?.id ??
        workspace?.confidentialityLevels?.[0]?.id ??
        "",
      workingPaperIds: selectedPaper ? [selectedPaper.id] : [],
    });
    setErrors({});
    setEvidenceOpen(true);
  }

  function openReplaceEvidence(record) {
    setReplacingEvidence(record);
    setEvidenceForm({
      title: record.title,
      evidenceCategoryId: record.evidenceCategoryId,
      evidenceSourceTypeId: record.evidenceSourceTypeId,
      sourceDescription: record.sourceDescription,
      dateObtained: record.dateObtained,
      custodianName: record.custodianName ?? "",
      custodianOfficeId: record.custodianOfficeId ?? "",
      confidentialityLevelId: record.confidentialityLevelId,
      acquisitionMethod: record.acquisitionMethod ?? "",
      acquisitionForm: record.acquisitionForm ?? "",
      planningObjectiveId: record.planningObjectiveId ?? "",
      riskMatrixItemId: record.riskMatrixItemId ?? "",
      controlReference: record.controlReference ?? "",
      workingPaperIds: (record.workingPapers ?? []).map((paper) => paper.id),
      changeReason: "",
      file: null,
    });
    setErrors({});
    setEvidenceOpen(true);
  }

  async function saveEvidence() {
    setSaving(true);
    setErrors({});
    try {
      if (replacingEvidence) {
        await aemsEvidenceApi.replace(engagementId, replacingEvidence.id, {
          ...evidenceForm,
          lockVersion: replacingEvidence.lockVersion,
        });
      } else {
        await aemsEvidenceApi.upload(engagementId, evidenceForm);
      }
      toast.success(
        replacingEvidence
          ? "New immutable evidence version uploaded."
          : "Evidence uploaded.",
      );
      setEvidenceOpen(false);
      await loadWorkspace();
    } catch (requestError) {
      showErrors(requestError);
    } finally {
      setSaving(false);
    }
  }

  function openEvidenceAction(action) {
    setEvidenceAction(action);
    setEvidenceReason("");
    setErrors({});
    setEvidenceActionOpen(true);
  }

  async function performEvidenceAction() {
    if (!selectedEvidence) return;
    setSaving(true);
    setErrors({});
    try {
      await aemsEvidenceApi.transition(engagementId, selectedEvidence.id, {
        action: evidenceAction,
        lockVersion: selectedEvidence.lockVersion,
        reason: evidenceReason || undefined,
      });
      toast.success(`Evidence ${label(evidenceAction).toLowerCase()}.`);
      setEvidenceActionOpen(false);
      await loadWorkspace();
    } catch (requestError) {
      showErrors(requestError);
    } finally {
      setSaving(false);
    }
  }

  async function downloadEvidence(record) {
    try {
      await aemsEvidenceApi.download(engagementId, record);
      toast.success("Evidence download started.");
    } catch (requestError) {
      toast.error(requestError.message);
    }
  }

  const summary = {
    papers: workspace?.workingPapers?.length ?? 0,
    submitted:
      workspace?.workingPapers?.filter((paper) =>
        ["SUBMITTED", "RESUBMITTED"].includes(paper.status),
      ).length ?? 0,
    approved:
      workspace?.workingPapers?.filter((paper) => paper.status === "APPROVED")
        .length ?? 0,
    evidence: currentEvidence.length,
  };
  const selectedEngagement =
    workspace?.engagement ??
    engagements.find((item) => String(item.id) === String(engagementId));
  const executionGate = getAemsWorkspaceGate("execution", selectedEngagement);

  return (
    <main className="min-w-0 p-4 sm:p-5">
      <RegistryHeader
        icon={Files}
        title="Working Papers & Audit Evidence"
        description="Document procedures performed, results, conclusions, review history, and the exact immutable evidence versions relied upon."
        readOnly={!canPrepare && !canReview && !canUploadEvidence}
        actions={
          <>
            {executionGate.unlocked && tab === "papers" && canPrepare && (
              <button
                className="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white disabled:opacity-50"
                disabled={!workspace?.fieldworkAvailable}
                onClick={openCreatePaper}
                type="button"
              >
                <FilePlus2 size={17} />
                New Working Paper
              </button>
            )}
            {executionGate.unlocked && tab === "evidence" && canUploadEvidence && (
              <button
                className="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white disabled:opacity-50"
                disabled={!workspace?.fieldworkAvailable}
                onClick={openUploadEvidence}
                type="button"
              >
                <Upload size={17} />
                Upload Evidence
              </button>
            )}
          </>
        }
      />

      {engagementId && (
        <AemsEngagementWorkspaceNav
          engagement={selectedEngagement}
          engagementId={engagementId}
        />
      )}
      {!executionGate.unlocked && (
        <AemsWorkspaceLockNotice
          engagementId={engagementId}
          gate={executionGate}
        />
      )}

      {executionGate.unlocked && (
        <>
      <div className="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <SummaryCard
          icon={FileClock}
          label="Working Papers"
          tone="sky"
          value={summary.papers}
        />
        <SummaryCard
          icon={Send}
          label="Awaiting Review"
          tone="amber"
          value={summary.submitted}
        />
        <SummaryCard
          icon={BadgeCheck}
          label="Approved & Locked"
          tone="emerald"
          value={summary.approved}
        />
        <SummaryCard
          icon={Paperclip}
          label="Evidence Families"
          tone="slate"
          value={summary.evidence}
        />
      </div>

      <div className="mb-4 grid gap-3 rounded-xl border border-slate-200 bg-white p-3 shadow-sm lg:grid-cols-[minmax(0,1fr)_minmax(15rem,24rem)]">
        <SearchableSelect
          options={engagementOptions}
          placeholder="Select engagement"
          value={engagementId}
          onChange={(value) => {
            setEngagementId(String(value));
            setSelectedPaperId("");
            setSelectedEvidenceId("");
          }}
        />
        <label className="flex h-11 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-slate-400 focus-within:border-sky-500 focus-within:ring-2 focus-within:ring-sky-100">
          <Search size={17} />
          <input
            className="min-w-0 flex-1 text-sm text-slate-800 outline-none"
            onChange={(event) => setQuery(event.target.value)}
            placeholder="Search index, title, status, or file"
            value={query}
          />
        </label>
      </div>

      <div className="mb-4 flex gap-2 border-b border-slate-200">
        {[
          ["papers", "Working Papers", FileClock],
          ["evidence", "Audit Evidence", Paperclip],
        ].map(([key, text, Icon]) => (
          <button
            className={`inline-flex items-center gap-2 border-b-2 px-4 py-3 text-sm font-bold ${
              tab === key
                ? "border-sky-700 text-sky-700"
                : "border-transparent text-slate-500 hover:text-slate-800"
            }`}
            key={key}
            onClick={() => setTab(key)}
            type="button"
          >
            <Icon size={17} />
            {text}
          </button>
        ))}
      </div>

      {error && (
        <div className="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          {error}
        </div>
      )}
      {!loading && workspace && !workspace.fieldworkAvailable && (
        <div className="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
          Activate the approved Audit Program before creating Working Papers or
          uploading fieldwork evidence.
        </div>
      )}

      {!engagementId && !loading ? (
        <section className="grid min-h-72 place-items-center rounded-xl border border-dashed border-slate-300 bg-white px-5 py-12 text-center shadow-sm">
          <div className="max-w-md">
            <Files className="mx-auto text-sky-600" size={36} />
            <h3 className="mt-4 text-base font-bold text-slate-800">
              Select an engagement to begin
            </h3>
            <p className="mt-2 text-sm leading-6 text-slate-500">
              Working Papers and their linked immutable Evidence versions will
              load together in this workspace.
            </p>
          </div>
        </section>
      ) : loading ? (
        <div className="grid min-h-64 place-items-center rounded-xl border border-slate-200 bg-white text-sm text-slate-500">
          Loading Working Paper workspace…
        </div>
      ) : tab === "papers" ? (
        <div className="grid gap-4 xl:grid-cols-[minmax(18rem,0.8fr)_minmax(0,1.5fr)]">
          <div className="space-y-2 rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
            {filteredPapers.map((paper) => (
              <button
                className={`w-full rounded-xl border p-3 text-left transition ${
                  String(selectedPaperId) === String(paper.id)
                    ? "border-sky-300 bg-sky-50 ring-1 ring-sky-200"
                    : "border-slate-200 hover:border-sky-200 hover:bg-slate-50"
                }`}
                key={paper.id}
                onClick={() => setSelectedPaperId(String(paper.id))}
                type="button"
              >
                <span className="flex items-start justify-between gap-2">
                  <span className="min-w-0">
                    <strong className="block truncate text-sm text-slate-800">
                      {paper.workingPaperCode}
                    </strong>
                    <span className="mt-1 block text-xs text-slate-500">
                      {paper.procedure?.procedureCode ?? "No procedure"}
                    </span>
                  </span>
                  <StatusBadge tone={statusTones[paper.status]}>
                    {label(paper.status)}
                  </StatusBadge>
                </span>
                <span className="mt-2 block line-clamp-2 text-sm text-slate-700">
                  {paper.title}
                </span>
              </button>
            ))}
            {filteredPapers.length === 0 && (
              <p className="px-3 py-12 text-center text-sm text-slate-500">
                No Working Papers match the current engagement and search.
              </p>
            )}
          </div>

          <div className="min-w-0 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            {selectedPaper ? (
              <>
                <div className="flex flex-col gap-3 border-b border-slate-200 pb-4 sm:flex-row sm:items-start sm:justify-between">
                  <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                      <h3 className="text-lg font-bold text-slate-900">
                        {selectedPaper.workingPaperCode}
                      </h3>
                      <StatusBadge tone={statusTones[selectedPaper.status]}>
                        {label(selectedPaper.status)}
                      </StatusBadge>
                      <span className="text-xs font-bold text-slate-400">
                        Version {selectedPaper.currentVersionNumber}
                      </span>
                    </div>
                    <p className="mt-1 text-sm text-slate-600">
                      {selectedPaper.title}
                    </p>
                  </div>
                  <div className="flex flex-wrap gap-2">
                    {canPrepare &&
                      ["DRAFT", "RETURNED_FOR_REVISION"].includes(
                        selectedPaper.status,
                      ) && (
                        <button
                          className="h-9 rounded-lg border border-slate-300 px-3 text-xs font-bold text-slate-700"
                          onClick={() => openEditPaper(selectedPaper)}
                          type="button"
                        >
                          Edit content
                        </button>
                      )}
                    {canPrepare && selectedPaper.status === "DRAFT" && (
                      <button
                        className="h-9 rounded-lg bg-sky-700 px-3 text-xs font-bold text-white"
                        onClick={() => openPaperAction("SUBMIT")}
                        type="button"
                      >
                        Submit
                      </button>
                    )}
                    {canPrepare &&
                      selectedPaper.status === "RETURNED_FOR_REVISION" && (
                        <button
                          className="h-9 rounded-lg bg-sky-700 px-3 text-xs font-bold text-white"
                          onClick={() => openPaperAction("RESUBMIT")}
                          type="button"
                        >
                          Resubmit
                        </button>
                      )}
                    {canReview &&
                      ["SUBMITTED", "RESUBMITTED"].includes(
                        selectedPaper.status,
                      ) && (
                        <button
                          className="h-9 rounded-lg border border-red-300 px-3 text-xs font-bold text-red-700"
                          onClick={() => openPaperAction("RETURN")}
                          type="button"
                        >
                          Return
                        </button>
                      )}
                    {canApprove &&
                      ["SUBMITTED", "RESUBMITTED"].includes(
                        selectedPaper.status,
                      ) && (
                        <button
                          className="h-9 rounded-lg bg-emerald-700 px-3 text-xs font-bold text-white"
                          onClick={() => openPaperAction("APPROVE")}
                          type="button"
                        >
                          Approve & lock
                        </button>
                      )}
                    {canReview &&
                      ["DRAFT", "RETURNED_FOR_REVISION"].includes(
                        selectedPaper.status,
                      ) && (
                        <button
                          className="h-9 rounded-lg border border-red-300 px-3 text-xs font-bold text-red-700"
                          onClick={() => openPaperAction("VOID")}
                          type="button"
                        >
                          Void
                        </button>
                      )}
                    {canPrepare && selectedPaper.status === "APPROVED" && (
                      <button
                        className="inline-flex h-9 items-center gap-1.5 rounded-lg border border-amber-300 px-3 text-xs font-bold text-amber-800"
                        onClick={() => openPaperAction("REVISE")}
                        type="button"
                      >
                        <RotateCcw size={14} />
                        Correct by revision
                      </button>
                    )}
                  </div>
                </div>

                <div className="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                  {[
                    ["Objective", selectedPaper.latestVersion.objective],
                    [
                      "Procedure performed",
                      selectedPaper.latestVersion.procedurePerformed,
                    ],
                    [
                      "Population",
                      selectedPaper.latestVersion.populationDescription,
                    ],
                    ["Sample", selectedPaper.latestVersion.sampleDescription],
                    ["Results", selectedPaper.latestVersion.result],
                    ["Conclusion", selectedPaper.latestVersion.conclusion],
                  ].map(([heading, content]) => (
                    <div
                      className="rounded-lg border border-slate-200 bg-slate-50 p-3"
                      key={heading}
                    >
                      <strong className="text-xs uppercase tracking-wide text-slate-500">
                        {heading}
                      </strong>
                      <p className="mt-1 whitespace-pre-wrap text-slate-700">
                        {content || "—"}
                      </p>
                    </div>
                  ))}
                </div>

                <div className="mt-4 grid gap-4 lg:grid-cols-2">
                  <div className="rounded-xl border border-slate-200 p-4">
                    <h4 className="flex items-center gap-2 text-sm font-bold text-slate-800">
                      <Link2 size={16} />
                      Cross-references and evidence
                    </h4>
                    <div className="mt-3 flex flex-wrap gap-2">
                      {(selectedPaper.latestVersion.crossReferences ?? []).map(
                        (reference) => (
                          <span
                            className="rounded-md bg-slate-100 px-2 py-1 text-xs text-slate-700"
                            key={reference}
                          >
                            {reference}
                          </span>
                        ),
                      )}
                      {(selectedPaper.latestVersion.crossReferences ?? [])
                        .length === 0 && (
                        <span className="text-sm text-slate-400">
                          No cross-references.
                        </span>
                      )}
                    </div>
                    <div className="mt-3 space-y-2">
                      {(selectedPaper.latestVersion.evidence ?? []).map(
                        (record) => (
                          <button
                            className="flex w-full items-center justify-between gap-3 rounded-lg border border-slate-200 px-3 py-2 text-left hover:bg-slate-50"
                            key={record.id}
                            onClick={() => {
                              setTab("evidence");
                              setSelectedEvidenceId(String(record.id));
                            }}
                            type="button"
                          >
                            <span className="min-w-0">
                              <strong className="block truncate text-xs text-slate-800">
                                {record.evidenceCode} v{record.versionNumber}
                              </strong>
                              <span className="block truncate text-xs text-slate-500">
                                {record.fileName}
                              </span>
                            </span>
                            <LockKeyhole
                              className={
                                record.status === "LOCKED"
                                  ? "text-emerald-600"
                                  : "text-slate-400"
                              }
                              size={16}
                            />
                          </button>
                        ),
                      )}
                      {(selectedPaper.latestVersion.evidence ?? []).length ===
                        0 && (
                        <p className="text-sm text-slate-500">
                          {selectedPaper.latestVersion.noEvidenceReason}
                        </p>
                      )}
                    </div>
                  </div>

                  <div className="rounded-xl border border-slate-200 p-4">
                    <h4 className="flex items-center gap-2 text-sm font-bold text-slate-800">
                      <History size={16} />
                      Revision and review history
                    </h4>
                    <div className="mt-3 max-h-72 space-y-3 overflow-y-auto">
                      {selectedPaper.events.map((event) => (
                        <div
                          className="border-l-2 border-sky-200 pl-3 text-xs"
                          key={event.id}
                        >
                          <strong className="text-slate-700">
                            {label(event.action)}
                          </strong>
                          <span className="ml-2 text-slate-400">
                            v{event.subjectVersion} ·{" "}
                            {date(event.createdAt, true)}
                          </span>
                          <p className="mt-0.5 text-slate-500">
                            {event.actor?.name ?? "System"}
                            {event.comment ? ` — ${event.comment}` : ""}
                          </p>
                        </div>
                      ))}
                    </div>
                  </div>
                </div>
              </>
            ) : (
              <div className="grid min-h-72 place-items-center text-center text-sm text-slate-500">
                Select a Working Paper to inspect its content and history.
              </div>
            )}
          </div>
        </div>
      ) : (
        <div className="grid gap-4 xl:grid-cols-[minmax(18rem,0.8fr)_minmax(0,1.5fr)]">
          <div className="space-y-2 rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
            {filteredEvidence.map((record) => (
              <button
                className={`w-full rounded-xl border p-3 text-left transition ${
                  String(selectedEvidenceId) === String(record.id)
                    ? "border-sky-300 bg-sky-50 ring-1 ring-sky-200"
                    : "border-slate-200 hover:border-sky-200 hover:bg-slate-50"
                }`}
                key={record.id}
                onClick={() => setSelectedEvidenceId(String(record.id))}
                type="button"
              >
                <span className="flex items-start justify-between gap-2">
                  <strong className="truncate text-sm text-slate-800">
                    {record.evidenceCode}
                  </strong>
                  <StatusBadge tone={statusTones[record.status]}>
                    {label(record.status)}
                  </StatusBadge>
                </span>
                <span className="mt-1 block truncate text-sm text-slate-700">
                  {record.title}
                </span>
                <span className="mt-2 block truncate text-xs text-slate-500">
                  {record.fileName} · v{record.versionNumber}
                </span>
              </button>
            ))}
            {filteredEvidence.length === 0 && (
              <p className="px-3 py-12 text-center text-sm text-slate-500">
                No evidence matches the current engagement and search.
              </p>
            )}
          </div>

          <div className="min-w-0 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            {selectedEvidence ? (
              <>
                <div className="flex flex-col gap-3 border-b border-slate-200 pb-4 sm:flex-row sm:items-start sm:justify-between">
                  <div>
                    <div className="flex flex-wrap items-center gap-2">
                      <h3 className="text-lg font-bold text-slate-900">
                        {selectedEvidence.evidenceCode}
                      </h3>
                      <StatusBadge tone={statusTones[selectedEvidence.status]}>
                        {label(selectedEvidence.status)}
                      </StatusBadge>
                      <span className="text-xs font-bold text-slate-400">
                        Version {selectedEvidence.versionNumber}
                      </span>
                    </div>
                    <p className="mt-1 text-sm text-slate-600">
                      {selectedEvidence.title}
                    </p>
                  </div>
                  <div className="flex flex-wrap gap-2">
                    <button
                      className="inline-flex h-9 items-center gap-1.5 rounded-lg border border-slate-300 px-3 text-xs font-bold text-slate-700"
                      onClick={() => downloadEvidence(selectedEvidence)}
                      type="button"
                    >
                      <Download size={14} />
                      Download
                    </button>
                    {canUploadEvidence &&
                      selectedEvidence.status !== "VOIDED" && (
                        <button
                          className="inline-flex h-9 items-center gap-1.5 rounded-lg border border-sky-300 px-3 text-xs font-bold text-sky-700"
                          onClick={() => openReplaceEvidence(selectedEvidence)}
                          type="button"
                        >
                          <RefreshCw size={14} />
                          New version
                        </button>
                      )}
                    {canVerifyEvidence &&
                      selectedEvidence.status === "DRAFT" && (
                        <button
                          className="inline-flex h-9 items-center gap-1.5 rounded-lg bg-emerald-700 px-3 text-xs font-bold text-white"
                          onClick={() => openEvidenceAction("VERIFY")}
                          type="button"
                        >
                          <ShieldCheck size={14} />
                          Verify
                        </button>
                      )}
                    {canVoidEvidence &&
                      ["DRAFT", "VERIFIED"].includes(
                        selectedEvidence.status,
                      ) && (
                        <button
                          className="inline-flex h-9 items-center gap-1.5 rounded-lg border border-red-300 px-3 text-xs font-bold text-red-700"
                          onClick={() => openEvidenceAction("VOID")}
                          type="button"
                        >
                          <XCircle size={14} />
                          Void
                        </button>
                      )}
                  </div>
                </div>

                <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                  {[
                    ["Evidence type", selectedEvidence.evidenceCategory?.label],
                    ["Source type", selectedEvidence.evidenceSourceType?.label],
                    ["Date obtained", date(selectedEvidence.dateObtained)],
                    ["Custodian", selectedEvidence.custodianName],
                    [
                      "Confidentiality",
                      selectedEvidence.confidentialityLevel?.label,
                    ],
                    ["File", selectedEvidence.fileName],
                    ["File size", bytes(selectedEvidence.fileSize)],
                    ["MIME type", selectedEvidence.mimeType],
                  ].map(([term, value]) => (
                    <div
                      className="rounded-lg border border-slate-200 bg-slate-50 p-3"
                      key={term}
                    >
                      <dt className="text-xs font-bold uppercase tracking-wide text-slate-500">
                        {term}
                      </dt>
                      <dd className="mt-1 break-words text-slate-700">
                        {value || "—"}
                      </dd>
                    </div>
                  ))}
                </dl>
                <div className="mt-3 rounded-lg border border-slate-200 p-3 text-sm">
                  <strong className="text-xs uppercase tracking-wide text-slate-500">
                    Source
                  </strong>
                  <p className="mt-1 whitespace-pre-wrap text-slate-700">
                    {selectedEvidence.sourceDescription}
                  </p>
                </div>
                <div className="mt-3 rounded-lg border border-slate-200 p-3">
                  <strong className="text-xs uppercase tracking-wide text-slate-500">
                    SHA-256 checksum
                  </strong>
                  <code className="mt-1 block break-all text-xs text-slate-700">
                    {selectedEvidence.checksumSha256}
                  </code>
                </div>

                <div className="mt-4 grid gap-4 lg:grid-cols-2">
                  <div className="rounded-xl border border-slate-200 p-4">
                    <h4 className="text-sm font-bold text-slate-800">
                      Linked audit records
                    </h4>
                    <div className="mt-3 space-y-2 text-sm">
                      {(selectedEvidence.workingPapers ?? []).map((paper) => (
                        <button
                          className="flex w-full justify-between rounded-lg bg-slate-50 px-3 py-2 text-left text-slate-700"
                          key={paper.id}
                          onClick={() => {
                            setTab("papers");
                            setSelectedPaperId(String(paper.id));
                          }}
                          type="button"
                        >
                          <span>{paper.workingPaperCode}</span>
                          <span className="text-xs text-slate-400">
                            {label(paper.status)}
                          </span>
                        </button>
                      ))}
                      {(selectedEvidence.findings ?? []).map((finding) => (
                        <div
                          className="flex justify-between rounded-lg bg-slate-50 px-3 py-2 text-slate-700"
                          key={finding.id}
                        >
                          <span>{finding.findingCode}</span>
                          <span className="text-xs text-slate-400">
                            {label(finding.status)}
                          </span>
                        </div>
                      ))}
                      {(selectedEvidence.workingPapers ?? []).length === 0 &&
                        (selectedEvidence.findings ?? []).length === 0 && (
                          <p className="text-slate-400">
                            Not yet linked to a Working Paper or Finding.
                          </p>
                        )}
                    </div>
                  </div>
                  <div className="rounded-xl border border-slate-200 p-4">
                    <h4 className="flex items-center gap-2 text-sm font-bold text-slate-800">
                      <History size={16} />
                      Immutable version history
                    </h4>
                    <div className="mt-3 space-y-2">
                      {evidenceHistory.map((record) => (
                        <button
                          className="flex w-full items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-left"
                          key={record.id}
                          onClick={() =>
                            record.isCurrentRevision
                              ? setSelectedEvidenceId(String(record.id))
                              : downloadEvidence(record)
                          }
                          type="button"
                        >
                          <span>
                            <strong className="block text-xs text-slate-700">
                              Version {record.versionNumber}
                            </strong>
                            <span className="text-xs text-slate-400">
                              {date(record.createdAt, true)}
                            </span>
                          </span>
                          <StatusBadge tone={statusTones[record.status]}>
                            {label(record.status)}
                          </StatusBadge>
                        </button>
                      ))}
                    </div>
                  </div>
                </div>
              </>
            ) : (
              <div className="grid min-h-72 place-items-center text-center text-sm text-slate-500">
                Select an evidence record to inspect its governance metadata.
              </div>
            )}
          </div>
        </div>
      )}

      </>
      )}

      <Modal
        open={paperOpen}
        onClose={() => !saving && setPaperOpen(false)}
        size="xl"
        title={editingPaper ? "Edit Working Paper" : "Create Working Paper"}
        description="Each save creates an immutable content version. Approved versions can only be corrected through a formal revision."
        footer={
          <>
            <button
              className="h-10 rounded-lg border border-slate-300 px-4 text-sm font-bold text-slate-700"
              disabled={saving}
              onClick={() => setPaperOpen(false)}
              type="button"
            >
              Cancel
            </button>
            <button
              className="h-10 rounded-lg bg-sky-700 px-5 text-sm font-bold text-white disabled:opacity-50"
              disabled={saving}
              onClick={savePaper}
              type="button"
            >
              {saving ? "Saving…" : "Save immutable version"}
            </button>
          </>
        }
      >
        <div className="grid gap-4 sm:grid-cols-2">
          <Field error={errors.procedureId} label="Audit procedure" wide>
            <SearchableSelect
              disabled={Boolean(editingPaper)}
              options={procedureOptions}
              value={paperForm.procedureId}
              onChange={(value) =>
                setPaperForm((current) => ({
                  ...current,
                  procedureId: value,
                }))
              }
            />
          </Field>
          <Field error={errors.title} label="Working Paper title" wide>
            <input
              className={inputClass}
              value={paperForm.title}
              onChange={(event) =>
                setPaperForm((current) => ({
                  ...current,
                  title: event.target.value,
                }))
              }
            />
          </Field>
          {[
            ["objective", "Objective"],
            ["procedurePerformed", "Procedure performed"],
            ["populationDescription", "Population"],
            ["sampleDescription", "Sample"],
            ["result", "Results"],
            ["conclusion", "Conclusion"],
          ].map(([field, text]) => (
            <Field error={errors[field]} key={field} label={text}>
              <textarea
                className={textAreaClass}
                value={paperForm[field]}
                onChange={(event) =>
                  setPaperForm((current) => ({
                    ...current,
                    [field]: event.target.value,
                  }))
                }
              />
            </Field>
          ))}
          <Field
            error={errors.evidenceIds}
            label="Verified evidence versions"
            wide
          >
            <SearchableSelect
              multiple
              multipleDisplay="summary"
              options={evidenceOptions}
              placeholder="Select exact evidence versions"
              value={paperForm.evidenceIds}
              onChange={(value) =>
                setPaperForm((current) => ({
                  ...current,
                  evidenceIds: value,
                }))
              }
            />
          </Field>
          {paperForm.evidenceIds.length === 0 && (
            <Field
              error={errors.noEvidenceReason}
              label="Reason no attachment is required"
              wide
            >
              <textarea
                className={textAreaClass}
                value={paperForm.noEvidenceReason}
                onChange={(event) =>
                  setPaperForm((current) => ({
                    ...current,
                    noEvidenceReason: event.target.value,
                  }))
                }
              />
            </Field>
          )}
          <Field
            error={errors.crossReferences}
            hint="one reference per line"
            label="Cross-references"
            wide
          >
            <textarea
              className={textAreaClass}
              value={paperForm.crossReferences}
              onChange={(event) =>
                setPaperForm((current) => ({
                  ...current,
                  crossReferences: event.target.value,
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
        title={`${label(paperAction)} Working Paper`}
        description={
          paperAction === "REVISE"
            ? "The approved version remains locked. A copied draft version will become the new correction workspace."
            : "This controlled action records the actor, date, status, version, comment, and exact evidence versions."
        }
        footer={
          <>
            <button
              className="h-10 rounded-lg border border-slate-300 px-4 text-sm font-bold text-slate-700"
              disabled={saving}
              onClick={() => setActionOpen(false)}
              type="button"
            >
              Cancel
            </button>
            <button
              className="h-10 rounded-lg bg-sky-700 px-5 text-sm font-bold text-white disabled:opacity-50"
              disabled={saving}
              onClick={performPaperAction}
              type="button"
            >
              {saving ? "Processing…" : "Confirm action"}
            </button>
          </>
        }
      >
        <Field
          error={errors.comment || errors.reason || errors.action}
          label={
            ["RETURN", "VOID", "REVISE"].includes(paperAction)
              ? "Required reason"
              : "Comment"
          }
        >
          <textarea
            className={textAreaClass}
            value={actionComment}
            onChange={(event) => setActionComment(event.target.value)}
          />
        </Field>
      </Modal>

      <Modal
        open={evidenceOpen}
        onClose={() => !saving && setEvidenceOpen(false)}
        size="xl"
        title={
          replacingEvidence
            ? "Upload Replacement Evidence Version"
            : "Upload Audit Evidence"
        }
        description="Files are stored privately. Every upload receives a SHA-256 checksum and creates an immutable document version."
        footer={
          <>
            <button
              className="h-10 rounded-lg border border-slate-300 px-4 text-sm font-bold text-slate-700"
              disabled={saving}
              onClick={() => setEvidenceOpen(false)}
              type="button"
            >
              Cancel
            </button>
            <button
              className="h-10 rounded-lg bg-sky-700 px-5 text-sm font-bold text-white disabled:opacity-50"
              disabled={saving}
              onClick={saveEvidence}
              type="button"
            >
              {saving ? "Uploading…" : "Upload immutable version"}
            </button>
          </>
        }
      >
        <div className="grid gap-4 sm:grid-cols-2">
          <Field error={errors.title} label="Evidence title" wide>
            <input
              className={inputClass}
              value={evidenceForm.title}
              onChange={(event) =>
                setEvidenceForm((current) => ({
                  ...current,
                  title: event.target.value,
                }))
              }
            />
          </Field>
          <Field error={errors.evidenceCategoryId} label="Evidence type">
            <SearchableSelect
              options={(workspace?.evidenceCategories ?? []).map((item) => ({
                value: item.id,
                label: item.label,
                description: item.description,
              }))}
              value={evidenceForm.evidenceCategoryId}
              onChange={(value) =>
                setEvidenceForm((current) => ({
                  ...current,
                  evidenceCategoryId: value,
                }))
              }
            />
          </Field>
          <Field error={errors.evidenceSourceTypeId} label="Source type">
            <SearchableSelect
              options={(workspace?.evidenceSourceTypes ?? []).map((item) => ({
                value: item.id,
                label: item.label,
                description: item.description,
              }))}
              value={evidenceForm.evidenceSourceTypeId}
              onChange={(value) =>
                setEvidenceForm((current) => ({
                  ...current,
                  evidenceSourceTypeId: value,
                }))
              }
            />
          </Field>
          <Field
            error={errors.sourceDescription}
            label="Source description"
            wide
          >
            <textarea
              className={textAreaClass}
              value={evidenceForm.sourceDescription}
              onChange={(event) =>
                setEvidenceForm((current) => ({
                  ...current,
                  sourceDescription: event.target.value,
                }))
              }
            />
          </Field>
          <Field error={errors.dateObtained} label="Date obtained">
            <input
              className={inputClass}
              type="date"
              value={evidenceForm.dateObtained}
              onChange={(event) =>
                setEvidenceForm((current) => ({
                  ...current,
                  dateObtained: event.target.value,
                }))
              }
            />
          </Field>
          <Field error={errors.confidentialityLevelId} label="Confidentiality">
            <SearchableSelect
              options={(workspace?.confidentialityLevels ?? []).map((item) => ({
                value: item.id,
                label: item.label,
              }))}
              value={evidenceForm.confidentialityLevelId}
              onChange={(value) =>
                setEvidenceForm((current) => ({
                  ...current,
                  confidentialityLevelId: value,
                }))
              }
            />
          </Field>
          <Field error={errors.acquisitionMethod} label="Acquisition method">
            <select
              className={inputClass}
              value={evidenceForm.acquisitionMethod}
              onChange={(event) =>
                setEvidenceForm((current) => ({
                  ...current,
                  acquisitionMethod: event.target.value,
                }))
              }
            >
              <option value="">Select method</option>
              <option value="REQUESTED">Requested</option>
              <option value="OBSERVED">Observed</option>
              <option value="INTERVIEW">Interview</option>
              <option value="SYSTEM_EXPORT">System export</option>
              <option value="INSPECTION">Inspection</option>
              <option value="OTHER">Other</option>
            </select>
          </Field>
          <Field error={errors.acquisitionForm} label="Acquisition form">
            <select
              className={inputClass}
              value={evidenceForm.acquisitionForm}
              onChange={(event) =>
                setEvidenceForm((current) => ({
                  ...current,
                  acquisitionForm: event.target.value,
                }))
              }
            >
              <option value="">Select form</option>
              <option value="DIGITAL">Digital</option>
              <option value="PHYSICAL">Physical</option>
              <option value="ORAL">Oral</option>
              <option value="EXTERNAL_CONFIRMATION">
                External confirmation
              </option>
            </select>
          </Field>
          <Field
            error={errors.planningObjectiveId}
            label="Planning objective"
          >
            <SearchableSelect
              options={planningObjectiveOptions}
              emptyMessage="No planning objectives are configured for this engagement."
              placeholder="Optional objective link"
              searchPlaceholder="Search planning objectives..."
              value={evidenceForm.planningObjectiveId}
              onChange={(value) =>
                setEvidenceForm((current) => ({
                  ...current,
                  planningObjectiveId: value,
                }))
              }
            />
          </Field>
          <Field error={errors.riskMatrixItemId} label="Risk matrix item">
            <SearchableSelect
              options={riskMatrixItemOptions}
              emptyMessage="No risk-matrix items are configured for this engagement."
              placeholder="Optional risk link"
              searchPlaceholder="Search risk-matrix items..."
              value={evidenceForm.riskMatrixItemId}
              onChange={(value) =>
                setEvidenceForm((current) => ({
                  ...current,
                  riskMatrixItemId: value,
                }))
              }
            />
          </Field>
          <Field error={errors.controlReference} label="Control reference">
            <input
              className={inputClass}
              value={evidenceForm.controlReference}
              onChange={(event) =>
                setEvidenceForm((current) => ({
                  ...current,
                  controlReference: event.target.value,
                }))
              }
              placeholder="Optional control ID/reference"
            />
          </Field>
          <Field error={errors.custodianName} label="Custodian name">
            <input
              className={inputClass}
              value={evidenceForm.custodianName}
              onChange={(event) =>
                setEvidenceForm((current) => ({
                  ...current,
                  custodianName: event.target.value,
                }))
              }
            />
          </Field>
          <Field error={errors.custodianOfficeId} label="Custodian office">
            <SearchableSelect
              options={(workspace?.custodianOffices ?? []).map((office) => ({
                value: office.id,
                label: `${office.code} — ${office.name}`,
              }))}
              placeholder="Optional office"
              value={evidenceForm.custodianOfficeId}
              onChange={(value) =>
                setEvidenceForm((current) => ({
                  ...current,
                  custodianOfficeId: value,
                }))
              }
            />
          </Field>
          <Field
            error={errors.workingPaperIds}
            label="Linked Working Papers"
            wide
          >
            <SearchableSelect
              multiple
              multipleDisplay="summary"
              options={paperOptions}
              placeholder="Optional family-level links"
              value={evidenceForm.workingPaperIds}
              onChange={(value) =>
                setEvidenceForm((current) => ({
                  ...current,
                  workingPaperIds: value,
                }))
              }
            />
          </Field>
          {replacingEvidence && (
            <Field
              error={errors.changeReason}
              label="Required change reason"
              wide
            >
              <textarea
                className={textAreaClass}
                value={evidenceForm.changeReason}
                onChange={(event) =>
                  setEvidenceForm((current) => ({
                    ...current,
                    changeReason: event.target.value,
                  }))
                }
              />
            </Field>
          )}
          <Field error={errors.file} label="Evidence file" wide>
            <input
              accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.jpg,.jpeg,.png"
              className="block w-full rounded-lg border border-slate-300 bg-white p-2 text-sm text-slate-700 file:mr-3 file:rounded-md file:border-0 file:bg-sky-100 file:px-3 file:py-2 file:font-bold file:text-sky-700"
              onChange={(event) =>
                setEvidenceForm((current) => ({
                  ...current,
                  file: event.target.files?.[0] ?? null,
                }))
              }
              type="file"
            />
          </Field>
        </div>
      </Modal>

      <Modal
        open={evidenceActionOpen}
        onClose={() => !saving && setEvidenceActionOpen(false)}
        size="sm"
        title={`${label(evidenceAction)} Evidence`}
        description={
          evidenceAction === "VERIFY"
            ? "Verification confirms the metadata and stored-file checksum. Approval of a cited Working Paper will lock this exact evidence version."
            : "Voiding retains the immutable file and history but excludes it from future reliance."
        }
        footer={
          <>
            <button
              className="h-10 rounded-lg border border-slate-300 px-4 text-sm font-bold text-slate-700"
              disabled={saving}
              onClick={() => setEvidenceActionOpen(false)}
              type="button"
            >
              Cancel
            </button>
            <button
              className="h-10 rounded-lg bg-sky-700 px-5 text-sm font-bold text-white disabled:opacity-50"
              disabled={saving}
              onClick={performEvidenceAction}
              type="button"
            >
              {saving ? "Processing…" : "Confirm action"}
            </button>
          </>
        }
      >
        {evidenceAction === "VOID" && (
          <Field error={errors.reason} label="Required void reason">
            <textarea
              className={textAreaClass}
              value={evidenceReason}
              onChange={(event) => setEvidenceReason(event.target.value)}
            />
          </Field>
        )}
      </Modal>
    </main>
  );
}
