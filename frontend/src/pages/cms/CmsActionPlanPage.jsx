import {
  AlertTriangle,
  ArrowLeft,
  CheckCircle2,
  ClipboardCheck,
  FilePenLine,
  Layers3,
  LoaderCircle,
  Play,
  RefreshCw,
  RotateCcw,
  Send,
} from "lucide-react";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { useNavigate, useParams } from "react-router";
import { useAuth } from "../../auth/auth-context";
import CmsActionPlanForm from "../../components/cms/CmsActionPlanForm";
import {
  CmsActionPlanStatusBadge,
  CmsStatusBadge,
} from "../../components/cms/CmsBadges";
import CmsActionPlanVersionHistory from "../../components/cms/CmsActionPlanVersionHistory";
import CmsActionPlanVersionViewer from "../../components/cms/CmsActionPlanVersionViewer";
import CmsActionPlanWorkflowDialogs from "../../components/cms/CmsActionPlanWorkflowDialogs";
import RegistryHeader from "../../components/ui/RegistryHeader";
import StatusBadge from "../../components/ui/StatusBadge";
import { hasPermission } from "../../config/navigation";
import { ApiError, cmsApi, userApi } from "../../services/api";
import { useToast } from "../../ui/toast-context";

function displayDate(value, includeTime = false) {
  if (!value) return "Not established";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "Not established";
  return new Intl.DateTimeFormat("en-PH", {
    dateStyle: "medium",
    ...(includeTime ? { timeStyle: "short" } : {}),
  }).format(date);
}

function versionToForm(version, ownerOffice) {
  return {
    planSummary: version?.planSummary ?? "",
    implementationStrategy: version?.implementationStrategy ?? "",
    expectedOutcome: version?.expectedOutcome ?? "",
    rootCauseResponse: version?.rootCauseResponse ?? "",
    resourcesRequired: version?.resourcesRequired ?? "",
    dependencies: version?.dependencies ?? "",
    risksAndConstraints: version?.risksAndConstraints ?? "",
    plannedStartDate: version?.plannedStartDate ?? "",
    plannedTargetDate: version?.plannedTargetDate ?? "",
    ownerOfficeId: version?.ownerOffice?.id ?? ownerOffice?.id ?? "",
    focalUserId: version?.focalUser?.id ?? "",
    milestones: (version?.milestones ?? []).map((milestone, index) => ({
      id: milestone.id,
      sequenceNumber: milestone.sequenceNumber ?? index + 1,
      title: milestone.title ?? "",
      description: milestone.description ?? "",
      expectedOutput: milestone.expectedOutput ?? "",
      successIndicator: milestone.successIndicator ?? "",
      verificationMethod: milestone.verificationMethod ?? "",
      responsibleOfficeId:
        milestone.responsibleOffice?.id ?? ownerOffice?.id ?? "",
      responsibleUserId: milestone.responsibleUser?.id ?? "",
      plannedStartDate: milestone.plannedStartDate ?? "",
      plannedTargetDate: milestone.plannedTargetDate ?? "",
      weightPercentage: milestone.weightPercentage ?? "",
      displayOrder: milestone.displayOrder ?? index + 1,
    })),
  };
}

function emptyForm(ownerOffice, user, effectiveTargetDate) {
  const sameOffice =
    ownerOffice?.code &&
    user?.officeCode &&
    ownerOffice.code === user.officeCode;

  return {
    planSummary: "",
    implementationStrategy: "",
    expectedOutcome: "",
    rootCauseResponse: "",
    resourcesRequired: "",
    dependencies: "",
    risksAndConstraints: "",
    plannedStartDate: "",
    plannedTargetDate: effectiveTargetDate ?? "",
    ownerOfficeId: ownerOffice?.id ?? "",
    focalUserId: sameOffice ? user.id : "",
    milestones: [],
  };
}

function draftPayload(form, lockVersion) {
  return {
    lockVersion,
    planSummary: form.planSummary || null,
    implementationStrategy: form.implementationStrategy || null,
    expectedOutcome: form.expectedOutcome || null,
    rootCauseResponse: form.rootCauseResponse || null,
    resourcesRequired: form.resourcesRequired || null,
    dependencies: form.dependencies || null,
    risksAndConstraints: form.risksAndConstraints || null,
    plannedStartDate: form.plannedStartDate || null,
    plannedTargetDate: form.plannedTargetDate || null,
    ownerOfficeId: form.ownerOfficeId ? Number(form.ownerOfficeId) : null,
    focalUserId: form.focalUserId ? Number(form.focalUserId) : null,
    milestones: form.milestones.map((milestone, index) => ({
      ...(milestone.id ? { id: Number(milestone.id) } : {}),
      sequenceNumber: index + 1,
      title: milestone.title,
      description: milestone.description || null,
      expectedOutput: milestone.expectedOutput,
      successIndicator: milestone.successIndicator || null,
      verificationMethod: milestone.verificationMethod || null,
      responsibleOfficeId: Number(
        milestone.responsibleOfficeId || form.ownerOfficeId,
      ),
      responsibleUserId: milestone.responsibleUserId
        ? Number(milestone.responsibleUserId)
        : null,
      plannedStartDate: milestone.plannedStartDate || null,
      plannedTargetDate: milestone.plannedTargetDate || null,
      weightPercentage:
        milestone.weightPercentage === "" || milestone.weightPercentage === null
          ? null
          : Number(milestone.weightPercentage),
      displayOrder: index + 1,
    })),
  };
}

function LoadingState() {
  return (
    <div aria-label="Loading Corrective Action Plan" className="grid gap-4">
      <div className="h-24 animate-pulse rounded-xl bg-slate-200" />
      <div className="h-36 animate-pulse rounded-xl bg-slate-200" />
      <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_20rem]">
        <div className="h-96 animate-pulse rounded-xl bg-slate-200" />
        <div className="h-80 animate-pulse rounded-xl bg-slate-200" />
      </div>
    </div>
  );
}

function ContextDatum({ label, value }) {
  return (
    <div className="min-w-0">
      <dt className="text-xs font-bold uppercase tracking-wide text-slate-500">
        {label}
      </dt>
      <dd className="mt-1 break-words text-sm font-semibold text-slate-800">
        {value}
      </dd>
    </div>
  );
}

function workflowGuidance(status) {
  return {
    DRAFT: "Awaiting responsible-office preparation and submission",
    SUBMITTED: "Awaiting compliance review",
    UNDER_REVIEW: "Independent compliance review in progress",
    RETURNED: "Awaiting a controlled responsible-office revision",
    ACCEPTED: "Accepted as an official monitoring baseline",
  }[status];
}

export default function CmsActionPlanPage() {
  const { recommendationId } = useParams();
  const navigate = useNavigate();
  const { user } = useAuth();
  const toast = useToast();
  const [plan, setPlan] = useState(null);
  const [caseContext, setCaseContext] = useState(null);
  const [recommendation, setRecommendation] = useState(null);
  const [noPlanActions, setNoPlanActions] = useState([]);
  const [selectedVersionId, setSelectedVersionId] = useState(null);
  const [form, setForm] = useState(null);
  const [savedForm, setSavedForm] = useState(null);
  const [candidateUsers, setCandidateUsers] = useState([]);
  const [creating, setCreating] = useState(false);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState("");
  const [saveErrors, setSaveErrors] = useState({});
  const [saveBusy, setSaveBusy] = useState(false);
  const [conflict, setConflict] = useState("");
  const [uncertain, setUncertain] = useState("");
  const [dialog, setDialog] = useState("");
  const [dialogComment, setDialogComment] = useState("");
  const [dialogConfirmed, setDialogConfirmed] = useState(false);
  const [dialogErrors, setDialogErrors] = useState({});
  const [transitionBusy, setTransitionBusy] = useState(false);
  const saveBusyRef = useRef(false);
  const transitionBusyRef = useRef(false);

  const ownerOffice =
    plan?.ownerOffice ??
    caseContext?.responsibleOffice ??
    recommendation?.responsibleOffice ??
    recommendation?.officeAccountability?.leadResponsibleOffice ??
    null;
  const effectiveTargetDate =
    plan?.caseContext?.effectiveTargetDate ??
    caseContext?.effectiveTargetDate ??
    recommendation?.effectiveTargetDate ??
    null;
  const versions = useMemo(() => plan?.versions ?? [], [plan]);
  const currentVersion = plan?.currentVersion ?? null;
  const selectedVersion =
    versions.find(
      (version) => Number(version.id) === Number(selectedVersionId),
    ) ??
    currentVersion ??
    plan?.acceptedVersion ??
    null;
  const dirty =
    Boolean(form && savedForm) &&
    JSON.stringify(form) !== JSON.stringify(savedForm);
  const editingCurrentDraft =
    selectedVersion?.status === "DRAFT" &&
    selectedVersion?.isCurrent &&
    selectedVersion.availableActions?.includes("update") &&
    hasPermission(user, "cms.action-plan.update");

  const adoptPlan = useCallback((nextPlan, preferredVersionId = null) => {
    setPlan(nextPlan);
    setCaseContext(nextPlan?.caseContext ?? null);
    const nextVersion =
      nextPlan?.versions?.find(
        (version) => Number(version.id) === Number(preferredVersionId),
      ) ??
      nextPlan?.currentVersion ??
      nextPlan?.acceptedVersion ??
      null;
    setSelectedVersionId(nextVersion?.id ?? null);
    if (nextVersion?.status === "DRAFT") {
      const nextForm = versionToForm(nextVersion, nextPlan?.ownerOffice);
      setForm(nextForm);
      setSavedForm(nextForm);
    } else {
      setForm(null);
      setSavedForm(null);
    }
    setCreating(false);
  }, []);

  const load = useCallback(
    async ({ preserveDraft = null, quiet = false } = {}) => {
      if (quiet) setRefreshing(true);
      else setLoading(true);
      setError("");
      try {
        const [workspace, recommendationRecord] = await Promise.all([
          cmsApi.getActionPlanForRecommendation(recommendationId),
          hasPermission(user, "cms.recommendation.view")
            ? cmsApi.getRecommendation(recommendationId)
            : Promise.resolve(null),
        ]);
        setRecommendation(recommendationRecord);
        setNoPlanActions(workspace?.permittedActions ?? []);
        const nextPlan = workspace?.actionPlan ?? null;
        setPlan(nextPlan);
        setCaseContext(nextPlan?.caseContext ?? workspace?.caseContext ?? null);
        const nextVersion =
          nextPlan?.currentVersion ?? nextPlan?.acceptedVersion ?? null;
        setSelectedVersionId(nextVersion?.id ?? null);
        if (nextVersion?.status === "DRAFT") {
          const authoritative = versionToForm(
            nextVersion,
            nextPlan?.ownerOffice,
          );
          setSavedForm(authoritative);
          setForm(preserveDraft ?? authoritative);
        } else {
          setForm(null);
          setSavedForm(null);
        }
        if (!nextPlan) setCreating(false);
      } catch (requestError) {
        setError(
          requestError instanceof ApiError && requestError.status === 404
            ? "This recommendation or Action Plan is unavailable within your authorized scope."
            : requestError.message ||
                "The Corrective Action Plan workspace could not be loaded.",
        );
      } finally {
        setLoading(false);
        setRefreshing(false);
      }
    },
    [recommendationId, user],
  );

  useEffect(() => {
    const timer = window.setTimeout(load, 0);
    return () => window.clearTimeout(timer);
  }, [load]);

  useEffect(() => {
    let active = true;
    const options = new Map();
    const addUser = (candidate) => {
      if (!candidate?.id) return;
      options.set(Number(candidate.id), {
        value: Number(candidate.id),
        label: candidate.employeeId
          ? `${candidate.name} (${candidate.employeeId})`
          : candidate.name,
      });
    };
    if (ownerOffice?.code === user?.officeCode && !user?.isLocked)
      addUser(user);
    for (const version of versions) {
      addUser(version.focalUser);
      for (const milestone of version.milestones ?? []) {
        addUser(milestone.responsibleUser);
      }
    }
    const candidateTimer = window.setTimeout(
      () => setCandidateUsers([...options.values()]),
      0,
    );

    if (!hasPermission(user, "users.view") || !ownerOffice?.code) {
      return () => {
        active = false;
        window.clearTimeout(candidateTimer);
      };
    }

    userApi
      .list()
      .then((users) => {
        if (!active) return;
        for (const candidate of users) {
          if (
            candidate.officeCode === ownerOffice.code &&
            !candidate.isLocked
          ) {
            addUser(candidate);
          }
        }
        setCandidateUsers([...options.values()]);
      })
      .catch(() => {
        // Existing valid identities remain usable if the optional registry fails.
      });
    return () => {
      active = false;
      window.clearTimeout(candidateTimer);
    };
  }, [ownerOffice?.code, user, versions]);

  useEffect(() => {
    if (!dirty) return undefined;
    const warn = (event) => {
      event.preventDefault();
      event.returnValue = "";
    };
    window.addEventListener("beforeunload", warn);
    return () => window.removeEventListener("beforeunload", warn);
  }, [dirty]);

  function leaveWorkspace() {
    if (
      dirty &&
      !window.confirm(
        "You have unsaved Action Plan changes. Leave this workspace and discard them?",
      )
    ) {
      return;
    }
    navigate(`/compliance-management/recommendations/${recommendationId}`);
  }

  function chooseVersion(versionId) {
    if (
      dirty &&
      !window.confirm(
        "Switching versions will discard your unsaved Action Plan changes. Continue?",
      )
    ) {
      return;
    }
    const version = versions.find(
      (candidate) => Number(candidate.id) === Number(versionId),
    );
    setSelectedVersionId(versionId);
    setSaveErrors({});
    setConflict("");
    if (version?.status === "DRAFT" && version.isCurrent) {
      const nextForm = versionToForm(version, ownerOffice);
      setForm(nextForm);
      setSavedForm(nextForm);
    } else {
      setForm(null);
      setSavedForm(null);
    }
  }

  function beginCreate() {
    const next = emptyForm(ownerOffice, user, effectiveTargetDate);
    setForm(next);
    setSavedForm(next);
    setSaveErrors({});
    setCreating(true);
  }

  function mutationError(requestError, target = "save") {
    const lockConflict =
      requestError instanceof ApiError &&
      (requestError.status === 409 ||
        Boolean(requestError.errors?.lockVersion));
    if (lockConflict) {
      setConflict(
        "Another user or process updated this Action Plan. Your local draft has been preserved; reload the latest version before deciding whether to save again.",
      );
    }
    if (requestError instanceof ApiError && requestError.status === 403) {
      toast.error(
        "The server did not authorize this Action Plan action. Your local data was not submitted.",
      );
    }
    if (requestError instanceof ApiError && requestError.status === 404) {
      setError(
        "This recommendation or Action Plan is unavailable within your authorized scope.",
      );
    }
    if (target === "save") setSaveErrors(requestError.errors ?? {});
    else setDialogErrors(requestError.errors ?? {});
    return lockConflict;
  }

  async function reconcileUncertain(localDraft) {
    setUncertain(
      "The request result was uncertain. AGIS retrieved the authoritative state before another retry. Review the latest status below.",
    );
    await load({ preserveDraft: localDraft, quiet: true });
  }

  async function saveDraft() {
    if (!form || saveBusyRef.current) return;
    saveBusyRef.current = true;
    setSaveBusy(true);
    setSaveErrors({});
    setConflict("");
    setUncertain("");
    try {
      const saved = creating
        ? await cmsApi.createActionPlan(
            recommendationId,
            draftPayload(form, recommendation?.lockVersion),
          )
        : await cmsApi.updateActionPlan(
            plan.id,
            currentVersion.id,
            draftPayload(form, currentVersion.lockVersion),
          );
      adoptPlan(saved);
      setRecommendation((current) =>
        current
          ? {
              ...current,
              status: saved.caseContext?.status ?? current.status,
              lockVersion: creating
                ? Number(current.lockVersion) + 1
                : current.lockVersion,
            }
          : current,
      );
      toast.success(
        creating
          ? "Corrective Action Plan draft created."
          : "Action Plan draft saved.",
      );
    } catch (requestError) {
      mutationError(requestError, "save");
      if (requestError instanceof ApiError && requestError.status === 0) {
        await reconcileUncertain(form);
      }
    } finally {
      saveBusyRef.current = false;
      setSaveBusy(false);
    }
  }

  function openWorkflowDialog(action) {
    setDialog(action);
    setDialogComment("");
    setDialogConfirmed(false);
    setDialogErrors({});
  }

  async function confirmWorkflow() {
    if (!selectedVersion || transitionBusyRef.current) return;
    const localErrors = {};
    if (dialog === "return" && !dialogComment.trim()) {
      localErrors.returnReason = ["Return instructions are required."];
    }
    if (dialog === "accept" && !dialogComment.trim()) {
      localErrors.acceptanceComment = ["An acceptance comment is required."];
    }
    if (dialog === "accept" && !dialogConfirmed) {
      localErrors.confirmation = ["Confirm acceptance before continuing."];
    }
    if (dialog === "revise" && !dialogComment.trim()) {
      localErrors.revisionReason = ["A revision reason is required."];
    }
    if (Object.keys(localErrors).length > 0) {
      setDialogErrors(localErrors);
      return;
    }

    transitionBusyRef.current = true;
    setTransitionBusy(true);
    setDialogErrors({});
    setConflict("");
    setUncertain("");
    try {
      let changed;
      if (dialog === "submit") {
        changed = await cmsApi.submitActionPlan(plan.id, selectedVersion.id, {
          lockVersion: selectedVersion.lockVersion,
          confirmation: true,
        });
      } else if (dialog === "start-review") {
        changed = await cmsApi.startActionPlanReview(
          plan.id,
          selectedVersion.id,
          { lockVersion: selectedVersion.lockVersion },
        );
      } else if (dialog === "return") {
        changed = await cmsApi.returnActionPlan(plan.id, selectedVersion.id, {
          lockVersion: selectedVersion.lockVersion,
          returnReason: dialogComment.trim(),
        });
      } else if (dialog === "accept") {
        changed = await cmsApi.acceptActionPlan(plan.id, selectedVersion.id, {
          lockVersion: selectedVersion.lockVersion,
          acceptanceComment: dialogComment.trim(),
          confirmation: true,
        });
      } else {
        changed = await cmsApi.reviseActionPlan(plan.id, selectedVersion.id, {
          lockVersion: selectedVersion.lockVersion,
          revisionReason: dialogComment.trim(),
        });
      }
      adoptPlan(changed);
      setDialog("");
      setRecommendation((current) =>
        current
          ? {
              ...current,
              status: changed.caseContext?.status ?? current.status,
            }
          : current,
      );
      const labels = {
        submit: "Action Plan submitted.",
        "start-review": "Action Plan review started.",
        return: "Action Plan returned with instructions.",
        accept: "Action Plan accepted as the monitoring baseline.",
        revise: "New controlled draft revision created.",
      };
      toast.success(labels[dialog]);
    } catch (requestError) {
      mutationError(requestError, "dialog");
      if (requestError instanceof ApiError && requestError.status === 0) {
        setDialog("");
        await reconcileUncertain(form);
      }
    } finally {
      transitionBusyRef.current = false;
      setTransitionBusy(false);
    }
  }

  async function reloadLatest() {
    const localDraft = form;
    setConflict("");
    setUncertain("");
    await load({ preserveDraft: localDraft, quiet: true });
  }

  async function refreshWorkspace() {
    if (
      dirty &&
      !window.confirm(
        "Reload the Action Plan and discard your unsaved local changes?",
      )
    ) {
      return;
    }
    await load({ quiet: true });
  }

  if (loading && !caseContext && !plan) return <LoadingState />;

  if (error && !caseContext && !plan) {
    return (
      <div className="rounded-2xl border border-red-200 bg-red-50 px-6 py-14 text-center">
        <AlertTriangle className="mx-auto text-red-600" size={36} />
        <h2 className="mt-3 text-xl font-bold text-slate-800">
          Action Plan unavailable
        </h2>
        <p className="mx-auto mt-2 max-w-xl text-sm text-slate-600">{error}</p>
        <div className="mt-5 flex flex-wrap justify-center gap-2">
          <button
            className="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700"
            onClick={leaveWorkspace}
            type="button"
          >
            <ArrowLeft size={16} /> Back to recommendation
          </button>
          <button
            className="inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white"
            onClick={() => load()}
            type="button"
          >
            <RefreshCw size={16} /> Retry
          </button>
        </div>
      </div>
    );
  }

  const context = plan?.caseContext ?? caseContext ?? {};
  const recommendationText =
    context.recommendation ?? recommendation?.recommendation;
  const caseCode =
    context.cmsRecommendationCode ??
    caseContext?.cmsRecommendationCode ??
    recommendation?.cmsRecommendationCode ??
    `CMS recommendation ${recommendationId}`;
  const canCreate =
    !plan &&
    noPlanActions.includes("create") &&
    hasPermission(user, "cms.action-plan.create");
  const actions = selectedVersion?.availableActions ?? [];
  const canSubmit =
    actions.includes("submit") && hasPermission(user, "cms.action-plan.submit");
  const canStartReview =
    actions.includes("start-review") &&
    hasPermission(user, "cms.action-plan.review");
  const canReturn =
    actions.includes("return") && hasPermission(user, "cms.action-plan.return");
  const canAccept =
    actions.includes("accept") && hasPermission(user, "cms.action-plan.accept");
  const canRevise =
    actions.includes("revise") && hasPermission(user, "cms.action-plan.revise");

  return (
    <div className="min-w-0">
      <RegistryHeader
        actions={
          <div className="flex flex-wrap gap-2">
            <button
              className="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700 hover:bg-slate-50"
              onClick={leaveWorkspace}
              type="button"
            >
              <ArrowLeft size={16} /> Recommendation
            </button>
            <button
              aria-label="Refresh Corrective Action Plan"
              className="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700 hover:bg-slate-50 disabled:opacity-60"
              disabled={refreshing || saveBusy || transitionBusy}
              onClick={refreshWorkspace}
              type="button"
            >
              <RefreshCw
                className={refreshing ? "animate-spin" : ""}
                size={16}
              />
              Refresh
            </button>
          </div>
        }
        description="Prepare, review, and retain controlled Corrective Action Plan versions and their accepted baseline."
        icon={ClipboardCheck}
        readOnly={!canCreate && !editingCurrentDraft && actions.length === 0}
        title="Corrective Action Plan"
      />

      {(conflict || uncertain || (error && (caseContext || plan))) && (
        <div className="mb-4 grid gap-2">
          {conflict && (
            <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
              <span>{conflict}</span>
              <button
                className="inline-flex h-9 items-center gap-2 rounded-lg bg-amber-700 px-3 text-xs font-bold text-white"
                onClick={reloadLatest}
                type="button"
              >
                <RefreshCw size={14} /> Reload latest
              </button>
            </div>
          )}
          {uncertain && (
            <div className="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
              {uncertain}
            </div>
          )}
          {error && (
            <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
              {error}
            </div>
          )}
        </div>
      )}

      <section className="mb-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div className="min-w-0">
            <p className="text-xs font-bold uppercase tracking-wide text-sky-700">
              {caseCode}
            </p>
            <p className="mt-2 max-w-4xl whitespace-pre-wrap text-sm leading-6 text-slate-800">
              {recommendationText || "Recommendation wording is unavailable."}
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            <CmsStatusBadge status={context.status ?? recommendation?.status} />
            {selectedVersion && (
              <CmsActionPlanStatusBadge status={selectedVersion.status} />
            )}
          </div>
        </div>
        <dl className="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
          <ContextDatum
            label="Responsible office"
            value={ownerOffice?.name || "Not assigned"}
          />
          <ContextDatum
            label="Effective target date"
            value={displayDate(effectiveTargetDate)}
          />
          <ContextDatum
            label="Action Plan family"
            value={plan?.displayCode || "Not created"}
          />
          <ContextDatum
            label="Current version"
            value={
              currentVersion
                ? `Version ${currentVersion.versionNumber}`
                : "None"
            }
          />
          <ContextDatum
            label="Accepted baseline"
            value={
              plan?.acceptedVersion
                ? `Version ${plan.acceptedVersion.versionNumber}`
                : "None yet"
            }
          />
        </dl>
        {!effectiveTargetDate && (
          <p className="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs leading-5 text-amber-800">
            No effective recommendation target date is established. An Action
            Plan date is a management proposal only and does not change the
            recommendation target.
          </p>
        )}
      </section>

      {!plan ? (
        creating ? (
          <CmsActionPlanForm
            busy={saveBusy}
            effectiveTargetDate={effectiveTargetDate}
            errors={saveErrors}
            form={form}
            isCreate
            onCancel={() => {
              setCreating(false);
              setForm(null);
              setSavedForm(null);
              setSaveErrors({});
            }}
            onSave={saveDraft}
            ownerOffice={ownerOffice}
            setForm={setForm}
            userOptions={candidateUsers}
          />
        ) : (
          <section className="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center shadow-sm">
            <FilePenLine className="mx-auto text-slate-300" size={42} />
            <h3 className="mt-3 text-lg font-bold text-slate-800">
              No Corrective Action Plan yet
            </h3>
            <p className="mx-auto mt-2 max-w-xl text-sm leading-6 text-slate-500">
              {canCreate
                ? "Create the first management-owned draft, define measurable milestones, and save it before submission."
                : "You may view this recommendation, but the responsible office has not created an Action Plan and your current authority does not permit creation."}
            </p>
            {canCreate && (
              <button
                className="mt-5 inline-flex h-11 items-center gap-2 rounded-lg bg-sky-700 px-5 text-sm font-bold text-white hover:bg-sky-800"
                onClick={beginCreate}
                type="button"
              >
                <FilePenLine size={17} /> Create Action Plan draft
              </button>
            )}
          </section>
        )
      ) : (
        <>
          {plan.acceptedVersion ? (
            <section className="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm">
              <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-start gap-3">
                  <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-emerald-100 text-emerald-700">
                    <CheckCircle2 size={20} />
                  </span>
                  <div>
                    <p className="font-bold text-emerald-900">
                      Accepted monitoring baseline: Version{" "}
                      {plan.acceptedVersion.versionNumber}
                    </p>
                    <p className="mt-1 text-sm text-emerald-800">
                      Accepted{" "}
                      {displayDate(plan.acceptedVersion.acceptedAt, true)}
                      {currentVersion?.id !== plan.acceptedVersion.id &&
                        " · Remains authoritative while the current revision is pending."}
                    </p>
                  </div>
                </div>
                <button
                  className="inline-flex h-9 items-center gap-2 rounded-lg border border-emerald-300 bg-white px-3 text-xs font-bold text-emerald-800"
                  onClick={() => chooseVersion(plan.acceptedVersion.id)}
                  type="button"
                >
                  <Layers3 size={14} /> View baseline
                </button>
              </div>
            </section>
          ) : (
            <section className="mb-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
              No accepted baseline exists yet. Only an independently accepted
              version becomes authoritative for monitoring.
            </section>
          )}

          <section className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div>
              <p className="text-xs font-bold uppercase tracking-wide text-slate-500">
                Viewing
              </p>
              <div className="mt-1 flex flex-wrap items-center gap-2">
                <strong className="text-slate-800">
                  Version {selectedVersion?.versionNumber}
                </strong>
                <CmsActionPlanStatusBadge status={selectedVersion?.status} />
                {selectedVersion?.isCurrent && (
                  <StatusBadge tone="info">Current version</StatusBadge>
                )}
                {selectedVersion?.isSuperseded && (
                  <StatusBadge tone="warning">Superseded baseline</StatusBadge>
                )}
              </div>
              <p className="mt-1 text-xs text-slate-500">
                {workflowGuidance(selectedVersion?.status)}
              </p>
            </div>
            <div className="flex flex-wrap gap-2">
              {canSubmit && selectedVersion?.isCurrent && (
                <button
                  className="inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white hover:bg-sky-800 disabled:opacity-50"
                  disabled={dirty || saveBusy || transitionBusy}
                  onClick={() => openWorkflowDialog("submit")}
                  title={
                    dirty ? "Save draft changes before submission." : undefined
                  }
                  type="button"
                >
                  <Send size={16} /> Submit
                </button>
              )}
              {canStartReview && (
                <button
                  className="inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white hover:bg-sky-800 disabled:opacity-50"
                  disabled={transitionBusy}
                  onClick={() => openWorkflowDialog("start-review")}
                  type="button"
                >
                  <Play size={16} /> Start review
                </button>
              )}
              {canReturn && (
                <button
                  className="inline-flex h-10 items-center gap-2 rounded-lg border border-amber-300 bg-white px-4 text-sm font-bold text-amber-800 hover:bg-amber-50 disabled:opacity-50"
                  disabled={transitionBusy}
                  onClick={() => openWorkflowDialog("return")}
                  type="button"
                >
                  <RotateCcw size={16} /> Return
                </button>
              )}
              {canAccept && (
                <button
                  className="inline-flex h-10 items-center gap-2 rounded-lg bg-emerald-700 px-4 text-sm font-bold text-white hover:bg-emerald-800 disabled:opacity-50"
                  disabled={transitionBusy}
                  onClick={() => openWorkflowDialog("accept")}
                  type="button"
                >
                  <CheckCircle2 size={16} /> Accept
                </button>
              )}
              {canRevise && (
                <button
                  className="inline-flex h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white hover:bg-sky-800 disabled:opacity-50"
                  disabled={transitionBusy}
                  onClick={() => openWorkflowDialog("revise")}
                  type="button"
                >
                  <FilePenLine size={16} /> Create revision
                </button>
              )}
              {(saveBusy || transitionBusy) && (
                <span className="inline-flex items-center gap-2 text-sm font-semibold text-slate-500">
                  <LoaderCircle className="animate-spin" size={16} />
                  Working...
                </span>
              )}
            </div>
          </section>

          <div className="grid items-start gap-4 xl:grid-cols-[minmax(0,1fr)_20rem]">
            <main className="min-w-0">
              {editingCurrentDraft && selectedVersion?.isCurrent ? (
                <CmsActionPlanForm
                  busy={saveBusy}
                  effectiveTargetDate={effectiveTargetDate}
                  errors={saveErrors}
                  form={form}
                  onSave={saveDraft}
                  ownerOffice={ownerOffice}
                  setForm={setForm}
                  userOptions={candidateUsers}
                />
              ) : (
                <CmsActionPlanVersionViewer version={selectedVersion} />
              )}
            </main>
            <aside className="min-w-0 xl:sticky xl:top-20">
              <CmsActionPlanVersionHistory
                onSelect={chooseVersion}
                selectedVersionId={selectedVersion?.id}
                versions={versions}
              />
            </aside>
          </div>
        </>
      )}

      <CmsActionPlanWorkflowDialogs
        busy={transitionBusy}
        comment={dialogComment}
        confirmed={dialogConfirmed}
        dialog={dialog}
        errors={dialogErrors}
        form={form}
        onClose={() => !transitionBusy && setDialog("")}
        onConfirm={confirmWorkflow}
        plan={plan}
        setComment={setDialogComment}
        setConfirmed={setDialogConfirmed}
        version={selectedVersion}
      />
    </div>
  );
}
