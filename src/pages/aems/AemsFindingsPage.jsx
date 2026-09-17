import { useCallback, useEffect, useMemo, useState } from "react";
import {
  AlertTriangle,
  CheckCircle2,
  ClipboardCheck,
  Download,
  GitMerge,
  FileWarning,
  History,
  MessageSquareText,
  Plus,
  RotateCcw,
  RefreshCw,
  Search,
  Send,
  ShieldCheck,
  Slash,
  Trash2,
  Upload,
} from "lucide-react";
import { useNavigate, useSearchParams } from "react-router";
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
import { aemsFindingApi, ApiError } from "../../services/api";
import { useToast } from "../../ui/toast-context";

const issueBlank = {
  title: "",
  exceptionDescription: "",
  responsibleOfficeId: "",
  riskRatingId: "",
  workingPaperVersionIds: [],
  evidenceIds: [],
};
const findingBlank = {
  title: "",
  criteria: "",
  condition: "",
  cause: "",
  effect: "",
  conclusion: "",
  directAuthorityReason: "",
  directAuthorityReference: "",
  significanceClassification: "",
  effectClassification: "",
  noRecommendationReason: "",
  responsibleOfficeId: "",
  riskRatingId: "",
  workingPaperVersionIds: [],
  evidenceIds: [],
  fieldworkRecordIds: [],
  fieldworkRecordVersionIds: [],
  procedureIds: [],
};
const recommendationBlank = {
  recommendation: "",
  responsibleOfficeId: "",
  targetImplementationDate: "",
};
const responseBlank = {
  responseKind: "ORIGINAL",
  supplementalReason: "",
  agreementPosition: "AGREE",
  managementComment: "",
  proposedAction: "",
  responsibleUserId: "",
  proposedTargetDate: "",
};
const rejoinderBlank = {
  disposition: "ACCEPT",
  rejoinder: "",
};
const inputClass =
  "h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100";
const textAreaClass = `${inputClass} min-h-24 resize-y py-2.5`;

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

function Field({ name, errors, children, wide = false, hint }) {
  return (
    <label
      className={`text-sm font-semibold text-slate-700 ${wide ? "sm:col-span-2" : ""}`}
    >
      {name}
      {hint && (
        <span className="ml-1 text-xs font-normal text-slate-400">{hint}</span>
      )}
      <span className="mt-1.5 block">{children}</span>
      {errors?.[0] && (
        <small className="mt-1 block text-red-600">{errors[0]}</small>
      )}
    </label>
  );
}

function ActionButton({ children, tone = "slate", ...props }) {
  const tones = {
    slate: "border-slate-300 bg-white text-slate-700 hover:bg-slate-50",
    blue: "border-sky-600 bg-sky-600 text-white hover:bg-sky-700",
    green: "border-emerald-600 bg-emerald-600 text-white hover:bg-emerald-700",
    red: "border-red-300 bg-white text-red-700 hover:bg-red-50",
    amber: "border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100",
  };
  return (
    <button
      className={`inline-flex h-10 items-center justify-center gap-2 rounded-lg border px-3 text-sm font-bold transition disabled:cursor-not-allowed disabled:opacity-50 ${tones[tone]}`}
      type="button"
      {...props}
    >
      {children}
    </button>
  );
}

function Section({ title, children, action }) {
  return (
    <section className="rounded-xl border border-slate-200 bg-white p-4">
      <div className="mb-3 flex items-center justify-between gap-3">
        <h3 className="text-sm font-bold uppercase tracking-wide text-slate-500">
          {title}
        </h3>
        {action}
      </div>
      {children}
    </section>
  );
}

export default function AemsFindingsPage({ section = "findings" }) {
  const { user } = useAuth();
  const toast = useToast();
  const navigate = useNavigate();
  const [params, setParams] = useSearchParams();
  const [engagements, setEngagements] = useState([]);
  const [engagementId, setEngagementId] = useState(
    params.get("engagementId") ?? "",
  );
  const [workspace, setWorkspace] = useState(null);
  const [tab] = useState(section === "issues" ? "issues" : "findings");
  const [selectedIssueId, setSelectedIssueId] = useState(
    params.get("issueId") ?? "",
  );
  const [selectedFindingId, setSelectedFindingId] = useState(
    params.get("findingId") ?? "",
  );
  const [query, setQuery] = useState("");
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [errors, setErrors] = useState({});
  const [modal, setModal] = useState("");
  const [editing, setEditing] = useState(null);
  const [form, setForm] = useState({});
  const [action, setAction] = useState("");
  const [actionForm, setActionForm] = useState({
    comment: "",
    recipients: "",
    dueDate: "",
    confidentiality: "INTERNAL",
    mergedIntoIssueId: "",
    referredTo: "",
    resolutionDetails: "",
    revisionAction: "CORRECT",
  });
  const [responseContext, setResponseContext] = useState(null);
  const [attachmentContext, setAttachmentContext] = useState(null);

  const canIssueCreate = hasPermission(user, "aems.issue.create");
  const canIssueValidate = hasPermission(user, "aems.issue.validate");
  const canIssueDismiss = hasPermission(user, "aems.issue.dismiss");
  const canIssueConvert = hasPermission(user, "aems.issue.convert");
  const canIssueMerge = hasPermission(user, "aems.issue.merge");
  const canIssueResolve = hasPermission(user, "aems.issue.resolve");
  const canIssueObserve = hasPermission(user, "aems.issue.observe");
  const canIssueRefer = hasPermission(user, "aems.issue.refer");
  const canIssueClose = hasPermission(user, "aems.issue.close_without_finding");
  const canIssueWithdraw = hasPermission(user, "aems.issue.withdraw");
  const canAfrTransmit = hasPermission(user, "aems.afr.transmit");
  const canAfrDelivery = hasPermission(user, "aems.afr.delivery");
  const canAfrAcknowledge = hasPermission(user, "aems.afr.acknowledge");
  const canFindingCreate = hasPermission(user, "aems.finding.create");
  const canFindingValidate = hasPermission(user, "aems.finding.validate");
  const canFindingRevise = hasPermission(user, "aems.finding.revise");
  const canCommunicate = hasPermission(user, "aems.finding.communicate");
  const canFinalize = hasPermission(user, "aems.finding.finalize");
  const canRespond = hasPermission(user, "aems.management-response.submit");
  const canReviewResponse = hasPermission(
    user,
    "aems.management-response.request_clarification",
  );
  const canRejoin = hasPermission(user, "aems.rejoinder.create");
  const canFinalizeRejoinder = hasPermission(user, "aems.rejoinder.finalize");

  const loadEngagements = useCallback(async () => {
    setLoading(true);
    try {
      const records = await aemsFindingApi.engagements();
      setEngagements(records);
      setEngagementId((current) => current || String(records[0]?.id ?? ""));
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
      const data = await aemsFindingApi.show(engagementId);
      setWorkspace(data);
      setSelectedIssueId((current) =>
        data.issues.some((item) => String(item.id) === String(current))
          ? current
          : String(data.issues[0]?.id ?? ""),
      );
      setSelectedFindingId((current) =>
        data.findings.some((item) => String(item.id) === String(current))
          ? current
          : String(data.findings[0]?.id ?? ""),
      );
    } catch (requestError) {
      setError(requestError.message);
      setWorkspace(null);
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
    if (engagementId) {
      setParams({ engagementId: String(engagementId) }, { replace: true });
    }
    return () => window.clearTimeout(timer);
  }, [engagementId, loadWorkspace, setParams]);

  const selectedIssue = workspace?.issues.find(
    (item) => String(item.id) === String(selectedIssueId),
  );
  const selectedFinding = workspace?.findings.find(
    (item) => String(item.id) === String(selectedFindingId),
  );
  const phaseGate = getAemsWorkspaceGate(
    section === "issues" ? "issues" : "afrs",
    workspace?.engagement ?? engagements.find((item) => String(item.id) === String(engagementId)),
  );
  const filteredIssues = useMemo(() => {
    const needle = query.trim().toLowerCase();
    return (workspace?.issues ?? []).filter((item) =>
      [item.issueCode, item.title, item.status].some((value) =>
        String(value).toLowerCase().includes(needle),
      ),
    );
  }, [query, workspace]);
  const filteredFindings = useMemo(() => {
    const needle = query.trim().toLowerCase();
    return (workspace?.findings ?? []).filter(
      (item) =>
        (section !== "responses" ||
          [
            "COMMUNICATED",
            "AWAITING_MANAGEMENT_RESPONSE",
            "UNDER_DIALOGUE",
            "FINALIZED",
          ].includes(item.status)) &&
        [item.findingCode, item.title, item.status].some((value) =>
          String(value).toLowerCase().includes(needle),
        ),
    );
  }, [query, section, workspace]);
  const options = useMemo(
    () => ({
      offices: (workspace?.offices ?? []).map((item) => ({
        value: item.id,
        label: `${item.code} — ${item.name}`,
      })),
      risks: (workspace?.riskRatings ?? []).map((item) => ({
        value: item.id,
        label: item.label,
      })),
      papers: (workspace?.workingPaperVersions ?? []).map((item) => ({
        value: item.id,
        label: `${item.workingPaper.workingPaperCode} v${item.versionNumber} — ${item.workingPaper.title}`,
      })),
      issueEvidence: (workspace?.evidence ?? []).map((item) => ({
        value: item.id,
        label: `${item.evidenceCode} v${item.versionNumber} â€” ${item.title}`,
      })),
      evidence: (workspace?.evidence ?? []).filter((item) => item.eligibleForFinalizedFinding === true).map((item) => ({
        value: item.id,
        label: `${item.evidenceCode} v${item.versionNumber} — ${item.title}`,
      })),
      procedures: (workspace?.procedures ?? []).map((item) => ({
        value: item.id,
        label: `${item.procedureCode} - ${item.objective || "Procedure"}`,
      })),
    }),
    [workspace],
  );

  function closeModal() {
    setModal("");
    setEditing(null);
    setErrors({});
    setResponseContext(null);
    setAttachmentContext(null);
  }

  function openIssue(issue = null) {
    setEditing(issue);
    setForm(
      issue
        ? {
            title: issue.title,
            exceptionDescription: issue.exceptionDescription,
            responsibleOfficeId: issue.responsibleOfficeId,
            riskRatingId: issue.riskRatingId,
            workingPaperVersionIds: issue.workingPaperVersions.map(
              (item) => item.id,
            ),
            evidenceIds: issue.evidence.map((item) => item.id),
          }
        : issueBlank,
    );
    setModal("issue");
  }

  function openFinding(finding = null) {
    setEditing(finding);
    setForm(
      finding
        ? {
            title: finding.title,
            criteria: finding.criteria,
            condition: finding.condition,
            cause: finding.cause,
            effect: finding.effect,
            conclusion: finding.conclusion ?? "",
            directAuthorityReason: finding.directCreationReason ?? "",
            directAuthorityReference: finding.directCreationAuthority ?? "",
            significanceClassification: finding.significanceClassification ?? "",
            effectClassification: finding.effectClassification ?? "",
            noRecommendationReason: finding.noRecommendationReason ?? "",
            responsibleOfficeId: finding.responsibleOfficeId,
            riskRatingId: finding.riskRatingId,
            workingPaperVersionIds: finding.workingPaperVersions.map(
              (item) => item.id,
            ),
            evidenceIds: finding.evidence.map((item) => item.id),
            fieldworkRecordIds: finding.fieldworkRecords.map((item) => item.id),
            fieldworkRecordVersionIds: finding.fieldworkRecords.map(
              (item) => item.versionId,
            ),
            procedureIds: (finding.procedures ?? []).map((item) => item.id),
          }
        : findingBlank,
    );
    setModal("finding");
  }

  function openRecommendation(recommendation = null) {
    setEditing(recommendation);
    setForm(
      recommendation
        ? {
            recommendation: recommendation.recommendation,
            responsibleOfficeId: recommendation.responsibleOfficeId,
            targetImplementationDate:
              recommendation.targetImplementationDate ?? "",
          }
        : {
            ...recommendationBlank,
            responsibleOfficeId: selectedFinding?.responsibleOfficeId ?? "",
          },
    );
    setModal("recommendation");
  }

  function openAction(nextAction, context = null) {
    setAction(nextAction);
    setResponseContext(context);
    setActionForm({
      comment: "",
      recipients: "",
      dueDate: "",
      confidentiality: "INTERNAL",
      mergedIntoIssueId: "",
      referredTo: "",
    resolutionDetails: "",
    extensionDueDate: "",
    lateReason: "",
      revisionAction: "CORRECT",
    });
    setModal("action");
  }

  function openResponse(response = null, responseKind = "ORIGINAL") {
    setEditing(response);
    setForm(
      response
        ? {
            agreementPosition: response.agreementPosition,
            managementComment: response.managementComment,
            proposedAction: response.proposedAction ?? "",
            responsibleUserId: response.responsibleUserId ?? "",
            proposedTargetDate: response.proposedTargetDate ?? "",
            responseKind: response.responseKind ?? "ORIGINAL",
            supplementalReason: response.supplementalReason ?? "",
          }
        : { ...responseBlank, responseKind },
    );
    setModal("response");
  }

  function openRejoinder(response, rejoinder = null) {
    setResponseContext(response);
    setEditing(rejoinder);
    setForm(
      rejoinder
        ? {
            disposition: rejoinder.disposition,
            rejoinder: rejoinder.rejoinder,
          }
        : rejoinderBlank,
    );
    setModal("rejoinder");
  }

  function openAttachment(type, response, rejoinder = null) {
    setAttachmentContext({ type, response, rejoinder });
    setForm({ caption: "", file: null });
    setModal("attachment");
  }

  async function perform(operation, message, refresh = true) {
    setSaving(true);
    setErrors({});
    try {
      await operation();
      toast.success(message);
      closeModal();
      if (refresh) await loadWorkspace();
    } catch (requestError) {
      if (requestError instanceof ApiError) {
        setErrors(requestError.errors ?? {});
      }
      toast.error(requestError.message);
    } finally {
      setSaving(false);
    }
  }

  function submitIssue() {
    perform(
      () =>
        editing
          ? aemsFindingApi.updateIssue(engagementId, editing.id, {
              ...form,
              lockVersion: editing.lockVersion,
            })
          : aemsFindingApi.createIssue(engagementId, form),
      editing ? "Draft issue updated." : "Draft issue created.",
    );
  }

  function submitFinding() {
    perform(
      () =>
        editing
          ? aemsFindingApi.updateFinding(engagementId, editing.id, {
              ...form,
              lockVersion: editing.lockVersion,
            })
          : aemsFindingApi.createFinding(engagementId, form),
      editing ? "Draft finding updated." : "Draft finding created.",
    );
  }

  function submitRecommendation() {
    perform(
      () =>
        aemsFindingApi.saveRecommendation(
          engagementId,
          selectedFinding.id,
          editing?.id,
          {
            ...form,
            findingLockVersion: selectedFinding.lockVersion,
            ...(editing ? { lockVersion: editing.lockVersion } : {}),
          },
        ),
      editing ? "Recommendation updated." : "Recommendation added.",
    );
  }

  function submitResponse() {
    perform(
      () =>
        editing
          ? aemsFindingApi.updateResponse(
              engagementId,
              selectedFinding.id,
              editing.id,
              { ...form, lockVersion: editing.lockVersion },
            )
          : aemsFindingApi.createResponse(
              engagementId,
              selectedFinding.id,
              {
                ...form,
                findingLockVersion: selectedFinding.lockVersion,
              },
            ),
      editing ? "Management response updated." : "Draft response created.",
    );
  }

  function submitRejoinder() {
    perform(
      () =>
        aemsFindingApi.saveRejoinder(
          engagementId,
          selectedFinding.id,
          responseContext.id,
          editing?.id,
          {
            ...form,
            responseLockVersion: responseContext.lockVersion,
            ...(editing ? { lockVersion: editing.lockVersion } : {}),
          },
        ),
      editing ? "Rejoinder updated." : "Draft rejoinder created.",
    );
  }

  function submitAttachment() {
    const context = attachmentContext;
    perform(
      () =>
        context.type === "response"
          ? aemsFindingApi.uploadResponseAttachment(
              engagementId,
              selectedFinding.id,
              context.response.id,
              {
                ...form,
                lockVersion: context.response.lockVersion,
              },
            )
          : aemsFindingApi.uploadRejoinderAttachment(
              engagementId,
              selectedFinding.id,
              context.response.id,
              context.rejoinder.id,
              {
                ...form,
                lockVersion: context.rejoinder.lockVersion,
              },
            ),
      "Supporting document attached to this exchange version.",
    );
  }

  async function downloadAttachment(attachment) {
    try {
      await aemsFindingApi.downloadAttachment(
        engagementId,
        selectedFinding.id,
        attachment,
      );
    } catch (requestError) {
      toast.error(requestError.message);
    }
  }

  function submitAction() {
    const findingActions = [
      "SUBMIT_FINDING",
      "VALIDATE_FINDING",
      "COMMUNICATE",
      "REQUEST_RESPONSE",
      "RECORD_NON_RESPONSE",
      "FINALIZE_FINDING",
    ];
    const issueActions = [
      "SUBMIT_ISSUE",
      "VALIDATE_ISSUE",
      "DISMISS",
      "CONVERT",
      "MERGE",
      "RESOLVE",
      "OBSERVE",
      "REFER",
      "CLOSE_WITHOUT_FINDING",
      "WITHDRAW",
    ];
    if (action === "TRANSMIT_AFR") {
      perform(
        () => aemsFindingApi.createTransmittal(engagementId, selectedFinding.id, {
          recipients: actionForm.recipients.split(",").map((item) => item.trim()).filter(Boolean),
          dueDate: actionForm.dueDate || undefined,
          confidentiality: actionForm.confidentiality,
          transmittalMethod: "OFFICIAL_LETTER",
        }),
        "AFR transmittal recorded.",
      );
      return;
    }
    if (["DELIVER_RECIPIENT", "ACKNOWLEDGE_RECIPIENT"].includes(action)) {
      perform(
        () => aemsFindingApi.transitionTransmittalRecipient(
          engagementId,
          selectedFinding.id,
          responseContext.transmittal.id,
          responseContext.recipient.id,
          {
            action: action === "DELIVER_RECIPIENT" ? "DELIVER" : "ACKNOWLEDGE",
            lockVersion: responseContext.recipient.lockVersion,
            comment: actionForm.comment || undefined,
          },
        ),
        "AFR recipient state updated.",
      );
      return;
    }
    let operation;
    if (issueActions.includes(action)) {
      const issueAction = action.replace("_ISSUE", "");
      operation = () =>
        aemsFindingApi.transitionIssue(
          engagementId,
          selectedIssue.id,
          {
            action: issueAction,
            lockVersion: selectedIssue.lockVersion,
            comment: actionForm.comment || undefined,
            mergedIntoIssueId: actionForm.mergedIntoIssueId || undefined,
            referredTo: actionForm.referredTo || undefined,
            resolutionDetails: actionForm.resolutionDetails || undefined,
            extensionDueDate: actionForm.extensionDueDate || undefined,
            lateReason: actionForm.lateReason || undefined,
          },
        );
    } else if (findingActions.includes(action)) {
      const findingAction = action.replace("_FINDING", "");
      operation = () =>
        aemsFindingApi.transitionFinding(
          engagementId,
          selectedFinding.id,
          {
            action: findingAction,
            lockVersion: selectedFinding.lockVersion,
            comment: actionForm.comment || undefined,
            recipients:
              action === "COMMUNICATE"
                ? actionForm.recipients
                    .split(",")
                    .map((item) => item.trim())
                    .filter(Boolean)
                : undefined,
            dueDate: actionForm.dueDate || undefined,
            confidentiality: actionForm.confidentiality,
            extensionDueDate: actionForm.extensionDueDate || undefined,
            lateReason: actionForm.lateReason || undefined,
          },
        );
    } else if (action === "REVISE_RESPONSE") {
      operation = () =>
        aemsFindingApi.reviseResponse(
          engagementId,
          selectedFinding.id,
          responseContext.id,
          { lockVersion: responseContext.lockVersion },
        );
    } else if (action === "FINALIZE_REJOINDER") {
      operation = () =>
        aemsFindingApi.finalizeRejoinder(
          engagementId,
          selectedFinding.id,
          responseContext.response.id,
          responseContext.rejoinder.id,
          {
            responseLockVersion: responseContext.response.lockVersion,
            lockVersion: responseContext.rejoinder.lockVersion,
          },
        );
    } else if (action === "REVISE_FINDING") {
      operation = () =>
        aemsFindingApi.reviseFinding(
          engagementId,
          selectedFinding.id,
          {
            action: actionForm.revisionAction,
            reason: actionForm.comment,
            lockVersion: selectedFinding.lockVersion,
          },
        );
    } else {
      const responseAction = action;
      operation = () =>
        aemsFindingApi.transitionResponse(
          engagementId,
          selectedFinding.id,
          responseContext.id,
          {
            action: responseAction,
            lockVersion: responseContext.lockVersion,
            comment: actionForm.comment || undefined,
          },
        );
    }
    perform(operation, `${label(action)} completed.`);
  }

  const terminalIssue = ["DISMISSED", "CONVERTED_TO_FINDING", "WITHDRAWN"].includes(
    selectedIssue?.status,
  );
  const currentResponse = selectedFinding?.managementResponses.find(
    (item) => item.isCurrentRevision,
  );

  if (workspace && !phaseGate.unlocked) {
    return (
      <main className="min-w-0 p-4 sm:p-5">
        <RegistryHeader
          icon={ShieldCheck}
          title={section === "issues" ? "Audit Issues" : "Findings & Recommendations"}
          description="This workspace is available only after the engagement reaches its authorized lifecycle phase."
        />
        <AemsEngagementWorkspaceNav
          engagement={workspace.engagement}
          engagementId={engagementId}
        />
        <AemsWorkspaceLockNotice engagementId={engagementId} gate={phaseGate} />
      </main>
    );
  }

  return (
    <main className="min-w-0 p-4 sm:p-5">
      <RegistryHeader
        icon={ShieldCheck}
        title={
          section === "issues"
            ? "Audit Issues"
            : section === "responses"
              ? "Auditee Responses & Dialogue"
              : "Findings & Recommendations"
        }
        description={
          section === "issues"
            ? "Capture supported exceptions, submit them for independent validation, and dismiss or convert validated issues."
            : section === "responses"
              ? "Exchange versioned management responses, supporting documents, clarification, auditor dispositions, and rejoinders."
              : "Develop criteria-condition-cause-effect findings, communicate them formally, and finalize immutable recommendations."
        }
        readOnly={
          !canIssueCreate &&
          !canFindingCreate &&
          !canRespond &&
          !canFindingValidate
        }
        actions={
          <>
            {canIssueCreate && tab === "issues" && (
              <ActionButton onClick={() => openIssue()} tone="blue">
                <Plus size={16} /> New issue
              </ActionButton>
            )}
            {canFindingCreate && section === "findings" && (
              <ActionButton onClick={() => openFinding()} tone="blue">
                <Plus size={16} /> New finding
              </ActionButton>
            )}
            <ActionButton onClick={loadWorkspace}>
              <RefreshCw size={16} /> Refresh
            </ActionButton>
          </>
        }
      />

      <div className="mb-5 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm lg:grid-cols-[minmax(0,1fr)_auto]">
        <SearchableSelect
          options={engagements.map((item) => ({
            value: item.id,
            label: `${item.engagementCode} — ${item.title}`,
          }))}
          value={engagementId}
          onChange={(value) => setEngagementId(String(value ?? ""))}
          placeholder="Select an engagement"
        />
        <div className="relative min-w-0 lg:min-w-64">
          <Search
            className="pointer-events-none absolute left-3 top-3 text-slate-400"
            size={18}
          />
          <input
            className={`${inputClass} pl-10`}
            onChange={(event) => setQuery(event.target.value)}
            placeholder="Search this workspace"
            value={query}
          />
        </div>
      </div>

      {error && (
        <div className="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
          {error}
        </div>
      )}
      {!engagementId && !loading && (
        <section className="grid min-h-72 place-items-center rounded-xl border border-dashed border-slate-300 bg-white px-5 py-12 text-center shadow-sm">
          <div className="max-w-md">
            <ShieldCheck className="mx-auto text-sky-600" size={36} />
            <h3 className="mt-4 text-base font-bold text-slate-800">
              Select an engagement to begin
            </h3>
            <p className="mt-2 text-sm leading-6 text-slate-500">
              The workspace will show only the issues, findings, recommendations,
              and response dialogue available to your role and engagement scope.
            </p>
          </div>
        </section>
      )}
      {workspace && (
        <>
          <div className="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <SummaryCard
              icon={FileWarning}
              label="Open issues"
              value={workspace.issues.filter((item) => !["DISMISSED", "CONVERTED_TO_FINDING", "WITHDRAWN"].includes(item.status)).length}
              tone="amber"
            />
            <SummaryCard
              icon={ClipboardCheck}
              label="Active findings"
              value={workspace.findings.filter((item) => item.status !== "FINALIZED").length}
              tone="sky"
            />
            <SummaryCard
              icon={MessageSquareText}
              label="Awaiting response"
              value={workspace.findings.filter((item) => item.status === "AWAITING_MANAGEMENT_RESPONSE").length}
              tone="amber"
            />
            <SummaryCard
              icon={CheckCircle2}
              label="Finalized"
              value={workspace.findings.filter((item) => item.status === "FINALIZED").length}
              tone="emerald"
            />
          </div>

          <div className="grid min-h-[520px] gap-4 xl:grid-cols-[360px_minmax(0,1fr)]">
            <div className="overflow-hidden rounded-xl border border-slate-200 bg-white">
              <div className="max-h-[70vh] divide-y divide-slate-100 overflow-y-auto">
                {(tab === "issues" ? filteredIssues : filteredFindings).map(
                  (item) => {
                    const code =
                      item.issueCode ?? item.findingCode;
                    const selected =
                      tab === "issues"
                        ? String(item.id) === String(selectedIssueId)
                        : String(item.id) === String(selectedFindingId);
                    return (
                      <button
                        className={`w-full p-4 text-left transition ${
                          selected
                            ? "bg-sky-50 ring-1 ring-inset ring-sky-200"
                            : "hover:bg-slate-50"
                        }`}
                        key={item.id}
                        onClick={() =>
                          tab === "issues"
                            ? setSelectedIssueId(String(item.id))
                            : setSelectedFindingId(String(item.id))
                        }
                        type="button"
                      >
                        <div className="flex items-start justify-between gap-3">
                          <span className="text-xs font-bold text-sky-700">
                            {code}
                          </span>
                          <StatusBadge
                            label={label(item.status)}
                            tone={
                              item.status === "FINALIZED" ||
                              item.status === "VALIDATED"
                                ? "success"
                                : item.status === "DISMISSED"
                                  ? "danger"
                                  : "warning"
                            }
                          />
                        </div>
                        <p className="mt-2 text-sm font-bold leading-5 text-slate-800">
                          {item.title}
                        </p>
                        <p className="mt-1 text-xs text-slate-500">
                          {item.responsibleOffice?.name ?? "No office"}
                        </p>
                      </button>
                    );
                  },
                )}
                {!loading &&
                  (tab === "issues" ? filteredIssues : filteredFindings)
                    .length === 0 && (
                    <p className="p-8 text-center text-sm text-slate-500">
                      No matching records.
                    </p>
                  )}
              </div>
            </div>

            {tab === "issues" ? (
              <IssueDetail
                issue={selectedIssue}
                canCreate={canIssueCreate}
                canValidate={canIssueValidate}
                canDismiss={canIssueDismiss}
                canConvert={canIssueConvert}
                canMerge={canIssueMerge}
                canResolve={canIssueResolve}
                canObserve={canIssueObserve}
                canRefer={canIssueRefer}
                canClose={canIssueClose}
                canWithdraw={canIssueWithdraw}
                currentUserId={Number(user?.id ?? 0)}
                terminal={terminalIssue}
                onEdit={() => openIssue(selectedIssue)}
                onAction={openAction}
                onOpenFinding={(id) => {
                  navigate(
                    `/audit-engagement-management/findings?engagementId=${engagementId}&findingId=${id}`,
                  );
                }}
              />
            ) : (
              <FindingDetail
                finding={selectedFinding}
                canCreate={canFindingCreate}
                canValidate={canFindingValidate}
                canRevise={canFindingRevise}
                canCommunicate={canCommunicate}
                canFinalize={canFinalize}
                canRespond={canRespond}
                canReviewResponse={canReviewResponse}
                canRejoin={canRejoin}
                canFinalizeRejoinder={canFinalizeRejoinder}
                canAfrTransmit={canAfrTransmit}
                canAfrDelivery={canAfrDelivery}
                canAfrAcknowledge={canAfrAcknowledge}
                currentUserId={Number(user?.id ?? 0)}
                currentResponse={currentResponse}
                onEdit={() => openFinding(selectedFinding)}
                onRecommendation={openRecommendation}
                onRemoveRecommendation={(recommendation) =>
                  perform(
                    () =>
                      aemsFindingApi.removeRecommendation(
                        engagementId,
                        selectedFinding.id,
                        recommendation.id,
                        {
                          findingLockVersion: selectedFinding.lockVersion,
                          lockVersion: recommendation.lockVersion,
                        },
                      ),
                    "Draft recommendation removed.",
                  )
                }
                onAction={openAction}
                onResponse={openResponse}
                onRejoinder={openRejoinder}
                onAttachment={openAttachment}
                onDownloadAttachment={downloadAttachment}
                showDialogue={section === "responses"}
                dialogueOnly={section === "responses"}
              />
            )}
          </div>
        </>
      )}

      <RecordModal
        modal={modal}
        editing={editing}
        form={form}
        setForm={setForm}
        errors={errors}
        saving={saving}
        options={options}
        workspace={workspace}
        selectedIssueId={selectedIssueId}
        action={action}
        responseContext={responseContext}
        actionForm={actionForm}
        setActionForm={setActionForm}
        onClose={closeModal}
        onIssue={submitIssue}
        onFinding={submitFinding}
        onRecommendation={submitRecommendation}
        onResponse={submitResponse}
        onRejoinder={submitRejoinder}
        onAttachment={submitAttachment}
        onAction={submitAction}
      />
    </main>
  );
}

function IssueDetail({
  issue,
  canCreate,
  canValidate,
  canDismiss,
  canConvert,
  canMerge,
  canResolve,
  canObserve,
  canRefer,
  canClose,
  canWithdraw,
  currentUserId,
  terminal,
  onEdit,
  onAction,
  onOpenFinding,
}) {
  if (!issue) {
    return <EmptyDetail text="Select an issue to review its support and history." />;
  }
  const authorIsCurrentUser = currentUserId && Number(issue.raisedBy?.id) === currentUserId;
  const dispositioned = Boolean(issue.disposition);
  return (
    <div className="space-y-4">
      <Section
        title="Issue"
        action={<StatusBadge label={label(issue.status)} tone={terminal ? "inactive" : "warning"} />}
      >
        <h2 className="text-xl font-bold text-slate-900">{issue.title}</h2>
        <p className="mt-2 whitespace-pre-wrap text-sm leading-6 text-slate-600">
          {issue.exceptionDescription}
        </p>
        <div className="mt-4 flex flex-wrap gap-2">
          {canCreate && issue.status === "DRAFT" && (
            <>
              <ActionButton onClick={onEdit}>Edit</ActionButton>
              <ActionButton onClick={() => onAction("SUBMIT_ISSUE")} tone="blue">
                <Send size={15} /> Submit
              </ActionButton>
            </>
          )}
          {canValidate && issue.status === "SUBMITTED" && !authorIsCurrentUser && (
            <ActionButton onClick={() => onAction("VALIDATE_ISSUE")} tone="green">
              Validate
            </ActionButton>
          )}
          {canDismiss && issue.status === "VALIDATED" && !authorIsCurrentUser && (
            <ActionButton onClick={() => onAction("DISMISS")} tone="red">
              Dismiss
            </ActionButton>
          )}
          {canConvert && issue.status === "VALIDATED" && !authorIsCurrentUser && (
            <ActionButton onClick={() => onAction("CONVERT")} tone="blue">
              Convert to finding
            </ActionButton>
          )}
          {canMerge && issue.status === "VALIDATED" && !authorIsCurrentUser && (
            <ActionButton onClick={() => onAction("MERGE")} tone="amber">
              <GitMerge size={15} /> Merge
            </ActionButton>
          )}
          {canResolve && issue.status === "VALIDATED" && !authorIsCurrentUser && (
            <ActionButton onClick={() => onAction("RESOLVE")} tone="amber">
              Resolve during audit
            </ActionButton>
          )}
          {canObserve && issue.status === "VALIDATED" && !authorIsCurrentUser && (
            <ActionButton onClick={() => onAction("OBSERVE")}>
              Record observation
            </ActionButton>
          )}
          {canRefer && issue.status === "VALIDATED" && !authorIsCurrentUser && (
            <ActionButton onClick={() => onAction("REFER")}>
              Refer
            </ActionButton>
          )}
          {canClose && issue.status === "VALIDATED" && !authorIsCurrentUser && (
            <ActionButton onClick={() => onAction("CLOSE_WITHOUT_FINDING")} tone="red">
              Close without finding
            </ActionButton>
          )}
          {canWithdraw && !["DISMISSED", "CONVERTED_TO_FINDING", "WITHDRAWN"].includes(issue.status) && !authorIsCurrentUser && (
            <ActionButton onClick={() => onAction("WITHDRAW")} tone="red">
              Withdraw issue
            </ActionButton>
          )}
          {issue.findingId && (
            <ActionButton onClick={() => onOpenFinding(issue.findingId)}>
              Open finding
            </ActionButton>
          )}
        </div>
        {authorIsCurrentUser && issue.status === "SUBMITTED" && (
          <p className="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
            Separation of duties: the issue author cannot validate or disposition this issue.
          </p>
        )}
      </Section>
      {dispositioned && (
        <Section title="Disposition decision" action={<StatusBadge label="Immutable disposition" tone="inactive" />}>
          <dl className="grid gap-3 text-sm sm:grid-cols-2">
            <Info label="Disposition" value={label(issue.disposition)} />
            <Info label="Recorded by" value={issue.dispositionRecordedBy?.name} />
            <Info label="Recorded at" value={date(issue.dispositionRecordedAt, true)} />
            <Info label="Referral / merge target" value={issue.referredTo || (issue.mergedIntoIssueId ? `Issue #${issue.mergedIntoIssueId}` : null)} />
          </dl>
          {issue.dispositionReason && (
            <p className="mt-3 whitespace-pre-wrap rounded-lg bg-slate-50 p-3 text-sm text-slate-700">{issue.dispositionReason}</p>
          )}
          {issue.resolutionDetails && (
            <p className="mt-3 whitespace-pre-wrap rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900"><strong>Resolution details:</strong> {issue.resolutionDetails}</p>
          )}
        </Section>
      )}
      <Section title="Classification">
        <dl className="grid gap-3 text-sm sm:grid-cols-2">
          <Info label="Risk rating" value={issue.riskRating?.label} />
          <Info label="Responsible office" value={issue.responsibleOffice?.name} />
          <Info label="Raised by" value={issue.raisedBy?.name} />
          <Info label="Reviewer" value={issue.reviewer?.name} />
        </dl>
      </Section>
      <Support
        papers={issue.workingPaperVersions}
        evidence={issue.evidence}
      />
      <HistoryList history={issue.history} />
    </div>
  );
}

function FindingDetail({
  finding,
  canCreate,
  canValidate,
  canRevise,
  canCommunicate,
  canFinalize,
  canRespond,
  canReviewResponse,
  canRejoin,
  canFinalizeRejoinder,
  canAfrTransmit,
  canAfrDelivery,
  canAfrAcknowledge,
  currentUserId,
  currentResponse,
  onEdit,
  onRecommendation,
  onRemoveRecommendation,
  onAction,
  onResponse,
  onRejoinder,
  onAttachment,
  onDownloadAttachment,
  showDialogue = true,
  dialogueOnly = false,
}) {
  if (!finding) {
    return <EmptyDetail text="Select a finding to review its criteria, dialogue, and recommendations." />;
  }
  const immutable = ["FINALIZED", "WITHDRAWN", "SUPERSEDED"].includes(finding.status);
  const authorIsCurrentUser = currentUserId && Number(finding.authoredBy?.id) === currentUserId;
  const evidenceEligible = finding.evidence?.length > 0 && finding.evidence.every(
    (item) => item.eligibleForFinalizedFinding === true,
  );
  return (
    <div className="space-y-4">
      <Section
        title={`${finding.findingCode} · Revision ${finding.revisionNumber}`}
        action={<StatusBadge label={label(finding.status)} tone={finding.status === "FINALIZED" ? "success" : "warning"} />}
      >
        <h2 className="text-xl font-bold text-slate-900">{finding.title}</h2>
        <div className="mt-4 flex flex-wrap gap-2">
          {canCreate && finding.status === "DRAFT" && (
            <>
              <ActionButton onClick={onEdit}>Edit finding</ActionButton>
              <ActionButton onClick={() => onAction("SUBMIT_FINDING")} tone="blue">
                Submit for review
              </ActionButton>
            </>
          )}
          {canValidate && finding.status === "PENDING_REVIEW" && !authorIsCurrentUser && evidenceEligible && (
            <ActionButton onClick={() => onAction("VALIDATE_FINDING")} tone="green">
              Validate
            </ActionButton>
          )}
          {canCommunicate && finding.status === "VALIDATED" && !authorIsCurrentUser && (
            <ActionButton onClick={() => onAction("COMMUNICATE")} tone="blue">
              Communicate
            </ActionButton>
          )}
          {canCommunicate && finding.status === "COMMUNICATED" && !authorIsCurrentUser && (
            <ActionButton onClick={() => onAction("REQUEST_RESPONSE")} tone="blue">
              Request response
            </ActionButton>
          )}
          {canReviewResponse && !authorIsCurrentUser &&
            finding.status === "AWAITING_MANAGEMENT_RESPONSE" && (
              <ActionButton
                onClick={() => onAction("RECORD_NON_RESPONSE")}
                tone="amber"
              >
                Record non-response
              </ActionButton>
            )}
          {canFinalize && finding.status === "UNDER_DIALOGUE" && !authorIsCurrentUser && evidenceEligible && (
            <ActionButton onClick={() => onAction("FINALIZE_FINDING")} tone="green">
              Finalize finding
            </ActionButton>
          )}
          {canRevise && finding.isCurrentRevision !== false && !["WITHDRAWN", "SUPERSEDED"].includes(finding.status) && (
            <ActionButton onClick={() => onAction("REVISE_FINDING")} tone="amber">
              <RotateCcw size={15} /> Create revision
            </ActionButton>
          )}
          {immutable && (
            <span className="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm font-bold text-slate-500">
              <Slash size={15} /> Locked after {label(finding.status).toLowerCase()}
            </span>
          )}
        </div>
        {authorIsCurrentUser && finding.status !== "DRAFT" && (
          <p className="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
            Separation of duties: the finding author cannot perform review, communication, or finalization actions on this finding.
          </p>
        )}
        {!evidenceEligible && !["DRAFT", "WITHDRAWN", "SUPERSEDED"].includes(finding.status) && (
          <p className="mt-3 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
            Professional evidence gate: validation and finalization are unavailable until every linked evidence record has an accepted current assessment with no unresolved gaps or limitations.
          </p>
        )}
      </Section>
      {immutable && (
        <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
          <strong>Immutable snapshot:</strong> This finding cannot be overwritten. Any correction, amendment, supersession, or withdrawal must be recorded as a new revision with a reason.
        </div>
      )}
      {!dialogueOnly && <Section title="Finding elements">
        <div className="grid gap-4 lg:grid-cols-2">
          {[
            ["Criteria", finding.criteria],
            ["Condition", finding.condition],
            ["Cause", finding.cause],
            ["Conclusion", finding.conclusion],
            ["Effect", finding.effect],
          ].map(([title, value]) => (
            <div className="rounded-lg bg-slate-50 p-3" key={title}>
              <p className="text-xs font-bold uppercase tracking-wide text-slate-500">
                {title}
              </p>
              <p className="mt-1 whitespace-pre-wrap text-sm leading-6 text-slate-700">
                {value || "Not documented"}
              </p>
            </div>
          ))}
        </div>
        <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2">
          <Info label="Risk rating" value={finding.riskRating?.label} />
          <Info label="Significance" value={finding.significanceClassification} />
          <Info label="Effect classification" value={finding.effectClassification} />
          <Info label="Responsible office" value={finding.responsibleOffice?.name} />
          <Info label="Management response due" value={date(finding.managementResponseDueDate)} />
          <Info label="Finalized" value={date(finding.finalizedAt, true)} />
        </dl>
      </Section>}
      {!dialogueOnly && !finding.sourceIssueId && finding.directCreationReason && (
        <Section title="Direct-finding authority">
          <dl className="grid gap-3 text-sm sm:grid-cols-2">
            <Info label="Authorized reason" value={label(finding.directCreationReason)} />
            <Info label="Recorded by" value={finding.directCreatedBy?.name} />
            <Info label="Recorded at" value={date(finding.directCreatedAt, true)} />
          </dl>
          <p className="mt-3 whitespace-pre-wrap rounded-lg border border-sky-200 bg-sky-50 p-3 text-sm text-sky-900">
            <strong>Authority reference:</strong> {finding.directCreationAuthority}
          </p>
        </Section>
      )}
      {!dialogueOnly && <Section title="Fieldwork traceability">
        {finding.fieldworkRecords?.length ? (
          <ul className="grid gap-2 sm:grid-cols-2">
            {finding.fieldworkRecords.map((record) => (
              <li className="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm" key={record.versionId ?? record.id}>
                <p className="font-bold text-slate-800">{record.recordCode || `Record #${record.id}`}</p>
                <p className="mt-1 text-xs text-slate-500">Version {record.versionNumber} · {label(record.executionStatus || record.status)}</p>
              </li>
            ))}
          </ul>
        ) : <p className="text-sm text-slate-500">No fieldwork execution record is linked.</p>}
      </Section>}
      {!dialogueOnly && <Section title="Procedure and criteria traceability">
        {finding.procedures?.length ? (
          <div className="grid gap-3 lg:grid-cols-2">
            {finding.procedures.map((procedure) => (
              <article className="rounded-lg border border-sky-200 bg-sky-50/60 p-3" key={procedure.id}>
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <p className="text-xs font-bold uppercase tracking-wide text-sky-700">{procedure.procedureCode}</p>
                    <p className="mt-1 text-sm font-bold text-slate-800">{procedure.objective || "Approved audit procedure"}</p>
                  </div>
                  <StatusBadge label="Linked" tone="active" />
                </div>
                <p className="mt-3 whitespace-pre-wrap text-xs leading-5 text-slate-700"><strong>Criteria:</strong> {procedure.criteriaReference || procedure.auditCriteria || "No procedure criteria recorded."}</p>
                {procedure.traceabilityNote && <p className="mt-2 text-xs text-slate-500">{procedure.traceabilityNote}</p>}
              </article>
            ))}
          </div>
        ) : (
          <p className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">No procedure criteria link is recorded. This finding cannot pass the professional review gate until an approved-program procedure is linked.</p>
        )}
      </Section>}
      {!dialogueOnly && <Support papers={finding.workingPaperVersions} evidence={finding.evidence} />}
      {!dialogueOnly && <RevisionHistory finding={finding} />}
      {!dialogueOnly && <Section
        title="Recommendations"
        action={
          canCreate &&
          !immutable && (
            <ActionButton onClick={() => onRecommendation()}>
              <Plus size={15} /> Add
            </ActionButton>
          )
        }
      >
        <div className="space-y-3">
          {finding.recommendations.map((recommendation) => (
            <div
              className="rounded-lg border border-slate-200 p-3"
              key={recommendation.id}
            >
              <div className="flex items-start justify-between gap-3">
                <p className="text-xs font-bold text-sky-700">
                  {recommendation.recommendationCode}
                </p>
                <StatusBadge
                  label={label(recommendation.status)}
                  tone={recommendation.status === "DRAFT" ? "warning" : "success"}
                />
              </div>
              <p className="mt-2 whitespace-pre-wrap text-sm leading-6 text-slate-700">
                {recommendation.recommendation}
              </p>
              <p className="mt-2 text-xs text-slate-500">
                {recommendation.responsibleOffice?.name} · Target{" "}
                {date(recommendation.targetImplementationDate)}
              </p>
              {canCreate && recommendation.status === "DRAFT" && !immutable && (
                <div className="mt-3 flex gap-2">
                  <ActionButton onClick={() => onRecommendation(recommendation)}>
                    Edit
                  </ActionButton>
                  <ActionButton
                    onClick={() => onRemoveRecommendation(recommendation)}
                    tone="red"
                  >
                    <Trash2 size={15} /> Remove
                  </ActionButton>
                </div>
              )}
            </div>
          ))}
          {finding.recommendations.length === 0 && (
            <p className="text-sm text-slate-500">
              {finding.noRecommendationReason || "No recommendations recorded."}
            </p>
          )}
        </div>
      </Section>}
      <Section
        title="AFR transmittal and acknowledgement"
        action={canAfrTransmit && ["COMMUNICATED", "AWAITING_MANAGEMENT_RESPONSE", "UNDER_DIALOGUE"].includes(finding.status) ? (
          <ActionButton onClick={() => onAction("TRANSMIT_AFR")} tone="blue">New transmittal</ActionButton>
        ) : null}
      >
        <div className="space-y-3">
          {(finding.transmittals ?? []).map((transmittal) => (
            <div key={transmittal.id} className="rounded-lg border border-slate-200 p-3">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-xs font-bold text-sky-700">{transmittal.transmittalCode} · {label(transmittal.transmittalMethod)}</p>
                <span className="text-xs text-slate-500">Sent {date(transmittal.sentAt, true)}</span>
              </div>
              <div className="mt-2 space-y-2">
                {transmittal.recipients.map((recipient) => (
                  <div key={recipient.id} className="flex flex-wrap items-center justify-between gap-2 rounded bg-slate-50 p-2 text-sm">
                    <span><strong>{recipient.recipientName}</strong> · {label(recipient.deliveryStatus)}</span>
                    <span className="flex gap-2">
                      {canAfrDelivery && ["SENT", "PENDING"].includes(recipient.deliveryStatus) && <ActionButton onClick={() => onAction("DELIVER_RECIPIENT", { transmittal, recipient })}>Mark delivered</ActionButton>}
                      {canAfrAcknowledge && ["SENT", "DELIVERED"].includes(recipient.deliveryStatus) && <ActionButton onClick={() => onAction("ACKNOWLEDGE_RECIPIENT", { transmittal, recipient })} tone="green">Acknowledge</ActionButton>}
                    </span>
                  </div>
                ))}
              </div>
            </div>
          ))}
          {(finding.transmittals ?? []).length === 0 && <p className="text-sm text-slate-500">No AFR transmittal has been recorded.</p>}
        </div>
      </Section>
      {showDialogue && (
        <Section
          title="Management dialogue"
          action={
          canRespond &&
          ["AWAITING_MANAGEMENT_RESPONSE", "UNDER_DIALOGUE"].includes(
            finding.status,
          ) &&
          !currentResponse && (
            <ActionButton onClick={() => onResponse()} tone="blue">
              <Plus size={15} /> Draft response
            </ActionButton>
          )
          }
        >
          <div className="space-y-4">
          {canRespond && currentResponse && ["UNDER_DIALOGUE", "AWAITING_MANAGEMENT_RESPONSE"].includes(finding.status) && (
            <ActionButton onClick={() => onResponse(null, "SUPPLEMENTAL")} tone="blue">
              <Plus size={15} /> Add supplemental response
            </ActionButton>
          )}
          {finding.managementResponses.map((response) => (
            <ResponseCard
              key={response.id}
              response={response}
              canRespond={canRespond}
              canReview={canReviewResponse}
              canRejoin={canRejoin}
              canFinalizeRejoinder={canFinalizeRejoinder}
              onEdit={() => onResponse(response)}
              onAction={(nextAction) => onAction(nextAction, response)}
              onRejoinder={(rejoinder) => onRejoinder(response, rejoinder)}
              onFinalizeRejoinder={(rejoinder) =>
                onAction("FINALIZE_REJOINDER", { response, rejoinder })
              }
              onAttachment={onAttachment}
              onDownloadAttachment={onDownloadAttachment}
            />
          ))}
          {finding.managementResponses.length === 0 && (
            <p className="text-sm text-slate-500">
              No management response has been recorded.
            </p>
          )}
          </div>
        </Section>
      )}
      <HistoryList history={finding.history} />
    </div>
  );
}

function ResponseCard({
  response,
  canRespond,
  canReview,
  canRejoin,
  canFinalizeRejoinder,
  onEdit,
  onAction,
  onRejoinder,
  onFinalizeRejoinder,
  onAttachment,
  onDownloadAttachment,
}) {
  return (
    <div className="rounded-lg border border-slate-200 p-3">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p className="text-xs font-bold text-sky-700">
            {response.responseCode} · Version {response.versionNumber}
          </p>
          {response.responseKind && response.responseKind !== "ORIGINAL" && (
            <span className="mt-1 inline-flex rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[11px] font-bold text-amber-800">
              {label(response.responseKind)} response
            </span>
          )}
          <p className="mt-1 text-sm font-bold text-slate-800">
            {label(response.agreementPosition)}
          </p>
        </div>
        <StatusBadge label={label(response.status)} tone="warning" />
      </div>
      <p className="mt-3 whitespace-pre-wrap text-sm leading-6 text-slate-700">
        {response.managementComment}
      </p>
      {response.submittedLate && (
        <p className="mt-2 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
          <strong>Late response reason:</strong> {response.lateReason || "Reason recorded in the immutable dialogue history."}
        </p>
      )}
      {response.supplementalReason && (
        <p className="mt-2 rounded-lg border border-sky-200 bg-sky-50 p-3 text-sm text-sky-800">
          <strong>Supplemental response reason:</strong> {response.supplementalReason}
        </p>
      )}
      {response.proposedAction && (
        <p className="mt-2 rounded-lg bg-slate-50 p-3 text-sm text-slate-600">
          <strong>Proposed action:</strong> {response.proposedAction}
        </p>
      )}
        <div className="mt-3 flex flex-wrap gap-2">
        {canRespond && ["DRAFT", "SUBMITTED", "CLARIFICATION_REQUESTED"].includes(response.status) && (
          <>
            {response.status === "DRAFT" && <>
              <ActionButton onClick={onEdit}>Edit</ActionButton>
              <ActionButton onClick={() => onAttachment("response", response)}>
                <Upload size={15} /> Attach
              </ActionButton>
              <ActionButton onClick={() => onAction("SUBMIT")} tone="blue">
                Submit
              </ActionButton>
            </>}
            <ActionButton onClick={() => onAction("REQUEST_EXTENSION")} tone="amber">
              Request extension
            </ActionButton>
          </>
        )}
        {canRespond && response.status === "EXTENSION_REQUESTED" && (
          <span className="inline-flex items-center rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-bold text-amber-800">
            Extension awaiting independent approval
          </span>
        )}
        {canReview && response.status === "EXTENSION_REQUESTED" && (
          <>
            <ActionButton onClick={() => onAction("APPROVE_EXTENSION")} tone="green">Approve extension</ActionButton>
            <ActionButton onClick={() => onAction("REJECT_EXTENSION")} tone="red">Reject extension</ActionButton>
          </>
        )}
        {canRespond && response.status === "EXTENSION_APPROVED" && (
          <ActionButton onClick={() => onAction("SUBMIT")} tone="blue">Submit extended response</ActionButton>
        )}
        {canRespond && response.status === "CLARIFICATION_REQUESTED" && (
          <ActionButton onClick={() => onAction("REVISE_RESPONSE")} tone="blue">
            Create revision
          </ActionButton>
        )}
        {canReview && ["SUBMITTED", "RESUBMITTED"].includes(response.status) && (
          <ActionButton onClick={() => onAction("START_REVIEW")}>
            Start review
          </ActionButton>
        )}
        {canReview && response.status === "UNDER_AUDITOR_REVIEW" && (
          <ActionButton
            onClick={() => onAction("REQUEST_CLARIFICATION")}
            tone="amber"
          >
            Request clarification
          </ActionButton>
        )}
        {canRejoin &&
          response.status === "UNDER_AUDITOR_REVIEW" &&
          response.rejoinders.length === 0 && (
            <ActionButton onClick={() => onRejoinder()} tone="blue">
              Add rejoinder
            </ActionButton>
          )}
      </div>
      {response.clarificationRequest && (
        <p className="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
          {response.clarificationRequest}
        </p>
      )}
      <AttachmentList
        attachments={response.attachments}
        onDownload={onDownloadAttachment}
      />
      {response.rejoinders.map((rejoinder) => (
        <div className="mt-3 rounded-lg bg-sky-50 p-3" key={rejoinder.id}>
          <div className="flex items-center justify-between gap-3">
            <p className="text-xs font-bold text-sky-800">
              Auditor disposition: {label(rejoinder.disposition)}
            </p>
            <StatusBadge label={label(rejoinder.status)} tone="active" />
          </div>
          <p className="mt-2 text-sm leading-6 text-slate-700">
            {rejoinder.rejoinder}
          </p>
          {rejoinder.status === "DRAFT" && (
            <div className="mt-3 flex gap-2">
              {canRejoin && (
                <>
                  <ActionButton onClick={() => onRejoinder(rejoinder)}>
                    Edit
                  </ActionButton>
                  <ActionButton
                    onClick={() =>
                      onAttachment("rejoinder", response, rejoinder)
                    }
                  >
                    <Upload size={15} /> Attach
                  </ActionButton>
                </>
              )}
              {canFinalizeRejoinder && (
                <ActionButton
                  onClick={() => onFinalizeRejoinder(rejoinder)}
                  tone="green"
                >
                  Finalize dialogue
                </ActionButton>
              )}
            </div>
          )}
          <AttachmentList
            attachments={rejoinder.attachments}
            onDownload={onDownloadAttachment}
          />
        </div>
      ))}
    </div>
  );
}

function AttachmentList({ attachments = [], onDownload }) {
  if (attachments.length === 0) return null;
  return (
    <div className="mt-3 space-y-2">
      {attachments.map((attachment) => (
        <button
          className="flex w-full items-center gap-3 rounded-lg border border-slate-200 bg-white p-2.5 text-left text-sm hover:bg-slate-50"
          key={attachment.id}
          onClick={() => onDownload(attachment)}
          type="button"
        >
          <Download className="shrink-0 text-sky-600" size={16} />
          <span className="min-w-0 flex-1">
            <strong className="block truncate text-slate-700">
              {attachment.fileName}
            </strong>
            <small className="text-slate-500">
              {attachment.caption || attachment.attachmentCode} · v
              {attachment.fileVersionNumber} · {attachment.uploadedBy?.name} ·{" "}
              {date(attachment.uploadedAt, true)}
            </small>
          </span>
        </button>
      ))}
    </div>
  );
}

function Support({ papers, evidence }) {
  return (
    <Section title="Exact supporting records">
      <div className="grid gap-4 lg:grid-cols-2">
        <div>
          <p className="text-xs font-bold uppercase text-slate-400">
            Working papers
          </p>
          <ul className="mt-2 space-y-2 text-sm text-slate-700">
            {papers.map((item) => (
              <li className={`rounded-lg p-2.5 ${item.eligibleForFinalizedFinding ? "border border-emerald-200 bg-emerald-50" : "border border-red-200 bg-red-50"}`} key={item.id}>
                <StatusBadge label={item.eligibleForFinalizedFinding ? "Accepted for reporting" : "Not eligible"} tone={item.eligibleForFinalizedFinding ? "success" : "danger"} />
                {!item.eligibleForFinalizedFinding && item.eligibilityReasons?.length > 0 && (
                  <p className="mt-1 text-xs text-red-700">{item.eligibilityReasons.join(" ")}</p>
                )}
                {item.workingPaperCode} v{item.versionNumber} — {item.title}
              </li>
            ))}
            {papers.length === 0 && <li>No working paper linked.</li>}
          </ul>
        </div>
        <div>
          <p className="text-xs font-bold uppercase text-slate-400">Evidence</p>
          <ul className="mt-2 space-y-2 text-sm text-slate-700">
            {evidence.map((item) => (
              <li className="rounded-lg bg-slate-50 p-2.5" key={item.id}>
                {item.evidenceCode} v{item.versionNumber} — {item.title}
              </li>
            ))}
            {evidence.length === 0 && <li>No evidence linked.</li>}
          </ul>
        </div>
      </div>
    </Section>
  );
}

function HistoryList({ history }) {
  return (
    <Section title="Revision and workflow history">
      <ol className="space-y-3">
        {history.map((item) => (
          <li className="flex gap-3 text-sm" key={item.id}>
            <History className="mt-0.5 shrink-0 text-slate-400" size={16} />
            <div>
              <p className="font-bold text-slate-700">{label(item.action)}</p>
              <p className="text-xs text-slate-500">
                {item.actor?.name ?? "System"} · {date(item.createdAt, true)}
              </p>
              {item.comment && (
                <p className="mt-1 text-slate-600">{item.comment}</p>
              )}
            </div>
          </li>
        ))}
      </ol>
    </Section>
  );
}

function RevisionHistory({ finding }) {
  const revisions = finding.revisions ?? [];
  return (
    <Section title="Immutable revision history">
      {revisions.length === 0 ? (
        <p className="text-sm text-slate-500">This is the original finding revision.</p>
      ) : (
        <div className="space-y-2">
          <div className="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
            <strong>Current revision:</strong> v{finding.revisionNumber} · {label(finding.revisionType)}
            {finding.revisionReason && <span> · {finding.revisionReason}</span>}
          </div>
          {revisions.map((revision) => (
            <div className="flex items-center justify-between gap-3 rounded-lg border border-slate-200 p-3 text-sm" key={revision.id}>
              <div>
                <p className="font-bold text-slate-700">Revision {revision.revisionNumber} · {label(revision.revisionType)}</p>
                <p className="text-xs text-slate-500">{revision.revisionReason || "No reason recorded"} · {date(revision.createdAt, true)}</p>
              </div>
              <StatusBadge label={revision.isCurrentRevision ? "Current" : label(revision.status)} tone={revision.isCurrentRevision ? "success" : "inactive"} />
            </div>
          ))}
        </div>
      )}
    </Section>
  );
}

function Info({ label: title, value }) {
  return (
    <div>
      <dt className="text-xs font-bold uppercase tracking-wide text-slate-400">
        {title}
      </dt>
      <dd className="mt-1 font-semibold text-slate-700">{value || "—"}</dd>
    </div>
  );
}

function EmptyDetail({ text }) {
  return (
    <div className="grid min-h-96 place-items-center rounded-xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center text-sm text-slate-500">
      {text}
    </div>
  );
}

function RecordModal({
  modal,
  editing,
  form,
  setForm,
  errors,
  saving,
  options,
  workspace,
  selectedIssueId,
  action,
  responseContext,
  actionForm,
  setActionForm,
  onClose,
  onIssue,
  onFinding,
  onRecommendation,
  onResponse,
  onRejoinder,
  onAttachment,
  onAction,
}) {
  const set = (field, value) => setForm((current) => ({ ...current, [field]: value }));
  const footer = (submit, text) => (
    <>
      <ActionButton disabled={saving} onClick={onClose}>Cancel</ActionButton>
      <ActionButton disabled={saving} onClick={submit} tone="blue">
        {saving ? "Saving…" : text}
      </ActionButton>
    </>
  );
  if (modal === "issue") {
    return (
      <Modal
        open
        title={editing ? "Edit draft issue" : "New audit issue"}
        description="Link the exact working-paper and evidence versions supporting the exception."
        onClose={onClose}
        size="lg"
        footer={footer(onIssue, editing ? "Save changes" : "Create issue")}
      >
        <div className="grid gap-4 sm:grid-cols-2">
          <Field name="Title" errors={errors.title} wide>
            <input className={inputClass} value={form.title} onChange={(event) => set("title", event.target.value)} />
          </Field>
          <Field name="Exception statement" errors={errors.exceptionDescription} wide>
            <textarea className={textAreaClass} value={form.exceptionDescription} onChange={(event) => set("exceptionDescription", event.target.value)} />
          </Field>
          <Field name="Responsible office" errors={errors.responsibleOfficeId}>
            <SearchableSelect options={options.offices} value={form.responsibleOfficeId} onChange={(value) => set("responsibleOfficeId", value)} />
          </Field>
          <Field name="Preliminary risk" errors={errors.riskRatingId}>
            <SearchableSelect options={options.risks} value={form.riskRatingId} onChange={(value) => set("riskRatingId", value)} />
          </Field>
          <Field name="Approved working papers" errors={errors.workingPaperVersionIds} wide>
            <SearchableSelect multiple options={options.papers} value={form.workingPaperVersionIds} onChange={(value) => set("workingPaperVersionIds", value)} />
          </Field>
          <Field name="Evidence" errors={errors.evidenceIds} wide>
            <SearchableSelect multiple options={options.issueEvidence} value={form.evidenceIds} onChange={(value) => set("evidenceIds", value)} />
          </Field>
        </div>
      </Modal>
    );
  }
  if (modal === "finding") {
    return (
      <Modal
        open
        title={editing ? "Edit draft finding" : "New audit finding"}
        description="Document criteria, condition, cause, effect, risk, and exact support."
        onClose={onClose}
        size="xl"
        footer={footer(onFinding, editing ? "Save changes" : "Create finding")}
      >
        <div className="grid gap-4 sm:grid-cols-2">
          <Field name="Title" errors={errors.title} wide><input className={inputClass} value={form.title} onChange={(event) => set("title", event.target.value)} /></Field>
          {["criteria", "condition", "cause", "effect"].map((field) => (
            <Field key={field} name={label(field)} errors={errors[field]}>
              <textarea className={textAreaClass} value={form[field]} onChange={(event) => set(field, event.target.value)} />
            </Field>
          ))}
          <Field name="Conclusion (required)" errors={errors.conclusion} wide hint="Required before a finding can be submitted or validated">
            <textarea className={textAreaClass} value={form.conclusion} onChange={(event) => set("conclusion", event.target.value)} />
          </Field>
          {!editing && (
            <>
              <Field name="Direct-Finding authority reason" errors={errors.directAuthorityReason} wide hint="Required when this finding is not converted from a validated Issue">
                <select className={inputClass} value={form.directAuthorityReason} onChange={(event) => set("directAuthorityReason", event.target.value)}>
                  <option value="">Select an authorized reason</option>
                  <option value="URGENT_OR_MATERIAL_RISK">Urgent or material risk</option>
                  <option value="FORMAL_DIRECTIVE_OR_LEGAL_REQUIREMENT">Formal directive or legal requirement</option>
                  <option value="CROSS_CUTTING_OR_SYSTEMIC_MATTER">Cross-cutting or systemic matter</option>
                </select>
              </Field>
              <Field name="Authority reference" errors={errors.directAuthorityReference} wide hint="Record the directive, risk assessment, or documented authorization">
                <textarea className={textAreaClass} value={form.directAuthorityReference} onChange={(event) => set("directAuthorityReference", event.target.value)} />
              </Field>
            </>
          )}
          <Field name="Significance classification" errors={errors.significanceClassification}>
            <input className={inputClass} value={form.significanceClassification} onChange={(event) => set("significanceClassification", event.target.value)} placeholder="Material / significant / low" />
          </Field>
          <Field name="Effect classification" errors={errors.effectClassification}>
            <input className={inputClass} value={form.effectClassification} onChange={(event) => set("effectClassification", event.target.value)} placeholder="Financial / operational / compliance" />
          </Field>
          <Field name="Responsible office" errors={errors.responsibleOfficeId}>
            <SearchableSelect options={options.offices} value={form.responsibleOfficeId} onChange={(value) => set("responsibleOfficeId", value)} />
          </Field>
          <Field name="Risk rating" errors={errors.riskRatingId}>
            <SearchableSelect options={options.risks} value={form.riskRatingId} onChange={(value) => set("riskRatingId", value)} />
          </Field>
          <Field name="Approved working papers" errors={errors.workingPaperVersionIds} wide>
            <SearchableSelect multiple options={options.papers} value={form.workingPaperVersionIds} onChange={(value) => set("workingPaperVersionIds", value)} />
          </Field>
          <Field name="Verified evidence" errors={errors.evidenceIds} wide>
            <SearchableSelect multiple options={options.evidence} value={form.evidenceIds} onChange={(value) => set("evidenceIds", value)} />
          </Field>
          <Field name="Criteria-traceability procedures" errors={errors.procedureIds} wide hint="At least one approved-program procedure is required before review">
            <SearchableSelect multiple options={options.procedures} value={form.procedureIds ?? []} onChange={(value) => set("procedureIds", value)} placeholder="Select procedures supporting the finding" />
          </Field>
          <div className="sm:col-span-2 rounded-lg border border-sky-200 bg-sky-50 p-3 text-xs leading-5 text-sky-800">
            Procedure links are also inferred from selected Working Paper and Fieldwork Record Versions. Review the Finding detail after saving to confirm the exact criteria chain.
          </div>
          <Field name="Reason no recommendation is required" errors={errors.noRecommendationReason} wide hint="Optional when recommendations will be added separately">
            <textarea className={textAreaClass} value={form.noRecommendationReason} onChange={(event) => set("noRecommendationReason", event.target.value)} />
          </Field>
        </div>
      </Modal>
    );
  }
  if (modal === "recommendation") {
    return (
      <Modal open title={editing ? "Edit recommendation" : "Add recommendation"} onClose={onClose} footer={footer(onRecommendation, "Save recommendation")}>
        <div className="space-y-4">
          <Field name="Recommendation" errors={errors.recommendation}><textarea className={textAreaClass} value={form.recommendation} onChange={(event) => set("recommendation", event.target.value)} /></Field>
          <Field name="Responsible office" errors={errors.responsibleOfficeId}><SearchableSelect options={options.offices} value={form.responsibleOfficeId} onChange={(value) => set("responsibleOfficeId", value)} /></Field>
          <Field name="Target implementation date" errors={errors.targetImplementationDate}><input className={inputClass} type="date" value={form.targetImplementationDate} onChange={(event) => set("targetImplementationDate", event.target.value)} /></Field>
        </div>
      </Modal>
    );
  }
  if (modal === "response") {
    return (
      <Modal open title={editing ? "Edit management response" : "Draft management response"} onClose={onClose} size="lg" footer={footer(onResponse, "Save response")}>
        <div className="grid gap-4 sm:grid-cols-2">
          <Field name="Agreement position" errors={errors.agreementPosition}>
            <select className={inputClass} value={form.agreementPosition} onChange={(event) => set("agreementPosition", event.target.value)}>
              {(workspace?.agreementPositions ?? []).map((item) => <option key={item} value={item}>{label(item)}</option>)}
            </select>
          </Field>
          {!editing && (
            <Field name="Response type" errors={errors.responseKind}>
              <select className={inputClass} value={form.responseKind} onChange={(event) => set("responseKind", event.target.value)}>
                <option value="ORIGINAL">Original response</option>
                <option value="SUPPLEMENTAL">Supplemental response</option>
              </select>
            </Field>
          )}
          {!editing && form.responseKind === "SUPPLEMENTAL" && (
            <Field name="Supplemental reason" errors={errors.supplementalReason} wide>
              <textarea className={textAreaClass} value={form.supplementalReason} onChange={(event) => set("supplementalReason", event.target.value)} />
            </Field>
          )}
          <Field name="Proposed target date" errors={errors.proposedTargetDate}><input className={inputClass} type="date" value={form.proposedTargetDate} onChange={(event) => set("proposedTargetDate", event.target.value)} /></Field>
          <Field name="Management comment" errors={errors.managementComment} wide><textarea className={textAreaClass} value={form.managementComment} onChange={(event) => set("managementComment", event.target.value)} /></Field>
          <Field name="Proposed action" errors={errors.proposedAction} wide><textarea className={textAreaClass} value={form.proposedAction} onChange={(event) => set("proposedAction", event.target.value)} /></Field>
        </div>
      </Modal>
    );
  }
  if (modal === "rejoinder") {
    return (
      <Modal open title={editing ? "Edit auditor rejoinder" : "Draft auditor rejoinder"} onClose={onClose} footer={footer(onRejoinder, "Save rejoinder")}>
        <div className="space-y-4">
          <Field name="Disposition" errors={errors.disposition}>
            <select className={inputClass} value={form.disposition} onChange={(event) => set("disposition", event.target.value)}>
              {(workspace?.rejoinderDispositions ?? []).map((item) => <option key={item} value={item}>{label(item)}</option>)}
            </select>
          </Field>
          <Field name="Rejoinder" errors={errors.rejoinder}><textarea className={textAreaClass} value={form.rejoinder} onChange={(event) => set("rejoinder", event.target.value)} /></Field>
        </div>
      </Modal>
    );
  }
  if (modal === "attachment") {
    return (
      <Modal
        open
        title="Attach supporting document"
        description="The file is stored privately and pinned to this exact exchange version."
        onClose={onClose}
        footer={footer(onAttachment, "Upload document")}
      >
        <div className="space-y-4">
          <Field name="File" errors={errors.file}>
            <input
              className={`${inputClass} py-2`}
              type="file"
              onChange={(event) => set("file", event.target.files?.[0] ?? null)}
            />
          </Field>
          <Field name="Caption" errors={errors.caption}>
            <input
              className={inputClass}
              value={form.caption}
              onChange={(event) => set("caption", event.target.value)}
            />
          </Field>
        </div>
      </Modal>
    );
  }
  if (modal !== "action") return null;
  const needsComment = [
    "DISMISS",
    "MERGE",
    "RESOLVE",
    "OBSERVE",
    "REFER",
    "CLOSE_WITHOUT_FINDING",
    "RECORD_NON_RESPONSE",
    "REQUEST_CLARIFICATION",
    "REVISE_FINDING",
    "WITHDRAW",
    "REQUEST_EXTENSION",
    "APPROVE_EXTENSION",
    "REJECT_EXTENSION",
  ].includes(action);
  return (
    <Modal
      open
      title={label(action)}
      description="This workflow action is recorded in the immutable engagement event history."
      onClose={onClose}
      footer={footer(onAction, "Confirm action")}
    >
      <div className="space-y-4">
        {action === "MERGE" && (
          <Field name="Merge into issue" errors={errors.mergedIntoIssueId}>
            <SearchableSelect
              options={(workspace?.issues ?? [])
                .filter((item) => String(item.id) !== String(selectedIssueId))
                .filter((item) => !["DISMISSED", "CONVERTED_TO_FINDING", "WITHDRAWN"].includes(item.status))
                .map((item) => ({ value: item.id, label: `${item.issueCode} — ${item.title}` }))}
              value={actionForm.mergedIntoIssueId}
              onChange={(value) => setActionForm((current) => ({ ...current, mergedIntoIssueId: value }))}
              placeholder="Select an active issue"
            />
          </Field>
        )}
        {action === "REFER" && (
          <Field name="Referral destination" errors={errors.referredTo}>
            <input className={inputClass} value={actionForm.referredTo} onChange={(event) => setActionForm((current) => ({ ...current, referredTo: event.target.value }))} placeholder="Office, committee, or external authority" />
          </Field>
        )}
        {action === "RESOLVE" && (
          <Field name="Resolution details" errors={errors.resolutionDetails}>
            <textarea className={textAreaClass} value={actionForm.resolutionDetails} onChange={(event) => setActionForm((current) => ({ ...current, resolutionDetails: event.target.value }))} />
          </Field>
        )}
        {action === "REVISE_FINDING" && (
          <Field name="Revision type" errors={errors.revisionAction}>
            <select className={inputClass} value={actionForm.revisionAction} onChange={(event) => setActionForm((current) => ({ ...current, revisionAction: event.target.value }))}>
              {[
                ["CORRECT", "Correction"],
                ["AMEND", "Amendment"],
                ["SUPERSEDE", "Supersession"],
                ["WITHDRAW", "Withdrawal"],
              ].map(([value, text]) => <option key={value} value={value}>{text}</option>)}
            </select>
          </Field>
        )}
        {action === "COMMUNICATE" && (
          <>
            <Field name="Recipients" errors={errors.recipients} hint="Comma-separated names or offices">
              <input className={inputClass} value={actionForm.recipients} onChange={(event) => setActionForm((current) => ({ ...current, recipients: event.target.value }))} />
            </Field>
            <Field name="Management response due date" errors={errors.dueDate}>
              <input className={inputClass} type="date" value={actionForm.dueDate} onChange={(event) => setActionForm((current) => ({ ...current, dueDate: event.target.value }))} />
            </Field>
            <Field name="Confidentiality" errors={errors.confidentiality}>
              <select className={inputClass} value={actionForm.confidentiality} onChange={(event) => setActionForm((current) => ({ ...current, confidentiality: event.target.value }))}>
                {["PUBLIC", "INTERNAL", "CONFIDENTIAL", "RESTRICTED"].map((item) => <option key={item} value={item}>{label(item)}</option>)}
              </select>
            </Field>
          </>
        )}
        {action === "TRANSMIT_AFR" && (
          <>
            <Field name="Recipients" errors={errors.recipients} hint="Comma-separated offices or recipient names">
              <input className={inputClass} value={actionForm.recipients} onChange={(event) => setActionForm((current) => ({ ...current, recipients: event.target.value }))} />
            </Field>
            <Field name="Response due date" errors={errors.dueDate}>
              <input className={inputClass} type="date" value={actionForm.dueDate} onChange={(event) => setActionForm((current) => ({ ...current, dueDate: event.target.value }))} />
            </Field>
          </>
        )}
        {action === "REQUEST_EXTENSION" && (
          <Field name="Proposed extension due date" errors={errors.extensionDueDate}>
            <input className={inputClass} type="date" value={actionForm.extensionDueDate} onChange={(event) => setActionForm((current) => ({ ...current, extensionDueDate: event.target.value }))} />
          </Field>
        )}
        {action === "SUBMIT" && responseContext && (
          <Field name="Late response reason" errors={errors.lateReason} hint="Required only when the response is past its effective due date.">
            <textarea className={textAreaClass} value={actionForm.lateReason} onChange={(event) => setActionForm((current) => ({ ...current, lateReason: event.target.value }))} />
          </Field>
        )}
        {(needsComment || !["REVISE_RESPONSE", "FINALIZE_REJOINDER"].includes(action)) && (
          <Field name={needsComment ? "Required explanation" : "Comment"} errors={errors.comment}>
            <textarea className={textAreaClass} value={actionForm.comment} onChange={(event) => setActionForm((current) => ({ ...current, comment: event.target.value }))} />
          </Field>
        )}
        <div className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
          <AlertTriangle className="mr-2 inline" size={16} />
          Confirm that the record and its exact supporting versions are complete.
        </div>
      </div>
    </Modal>
  );
}
