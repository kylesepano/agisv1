import { useCallback, useEffect, useMemo, useState } from "react";
import {
  AlertCircle,
  Archive,
  ArrowLeft,
  CalendarDays,
  CheckCircle2,
  CircleDot,
  ClipboardCheck,
  FileClock,
  GitBranch,
  Link2,
  ListChecks,
  ListPlus,
  LockKeyhole,
  Pencil,
  Plus,
  RefreshCw,
  RotateCcw,
  Send,
  ShieldAlert,
  Target,
  UsersRound,
  XCircle,
} from "lucide-react";
import { useNavigate, useParams } from "react-router";
import { useAuth } from "../../auth/auth-context";
import useRecordView from "../../hooks/useRecordView";
import IapPlanForm from "../../components/iap/IapPlanForm";
import IapPrioritizedEngagementForm from "../../components/iap/IapPrioritizedEngagementForm";
import IapRiskAssessmentForm from "../../components/iap/IapRiskAssessmentForm";
import IapSupportingRecordsPanel from "../../components/iap/IapSupportingRecordsPanel";
import ConfirmDialog from "../../components/ui/ConfirmDialog";
import DataTable from "../../components/ui/DataTable";
import Modal from "../../components/ui/Modal";
import RegistryHeader from "../../components/ui/RegistryHeader";
import StatusBadge from "../../components/ui/StatusBadge";
import SummaryCard from "../../components/ui/SummaryCard";
import SearchableSelect from "../../components/ui/SearchableSelect";
import { hasPermission } from "../../config/navigation";
import {
  ApiError,
  iapApi,
  masterListApi,
  officeApi,
  prioritizationApi,
  userApi,
} from "../../services/api";
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

const actionDetails = {
  submit: {
    label: "Submit for review",
    title: "Submit annual plan for review?",
    description:
      "The plan will be locked while CIAS Management reviews its coverage, risks, engagements, and resources.",
    icon: Send,
    button: "bg-sky-700 hover:bg-sky-800",
    commentRequired: true,
  },
  resubmit: {
    label: "Resubmit plan",
    title: "Resubmit the revised plan?",
    description:
      "The revised plan will return to CIAS Management for another review.",
    icon: RotateCcw,
    button: "bg-sky-700 hover:bg-sky-800",
    commentRequired: true,
  },
  return: {
    label: "Return for revision",
    title: "Return this plan for revision?",
    description:
      "Explain the corrections or clarifications required from the plan preparer.",
    icon: RotateCcw,
    button: "bg-amber-600 hover:bg-amber-700",
    commentRequired: true,
  },
  approve: {
    label: "Approve plan",
    title: "Approve this annual plan?",
    description:
      "Approval locks the accepted plan content and records you as the approving official.",
    icon: CheckCircle2,
    button: "bg-emerald-700 hover:bg-emerald-800",
    commentRequired: true,
  },
  reject: {
    label: "Reject plan",
    title: "Reject this annual plan?",
    description:
      "Provide the reason for rejecting this submitted plan revision.",
    icon: XCircle,
    button: "bg-red-600 hover:bg-red-700",
    commentRequired: true,
  },
  activate: {
    label: "Activate plan",
    title: "Activate the approved plan?",
    description:
      "The approved annual plan will become authorized for implementation.",
    icon: GitBranch,
    button: "bg-sky-700 hover:bg-sky-800",
    commentRequired: true,
  },
  complete: {
    label: "Complete plan",
    title: "Mark the annual plan completed?",
    description:
      "Confirm that implementation and all planned engagement work have been completed.",
    icon: ClipboardCheck,
    button: "bg-emerald-700 hover:bg-emerald-800",
    commentRequired: true,
    confirmation: true,
  },
  revision: {
    label: "Create revision",
    title: "Create a formal plan revision?",
    description:
      "A new draft will be cloned from this plan and become the current revision for the fiscal year.",
    icon: GitBranch,
    button: "bg-violet-700 hover:bg-violet-800",
    commentRequired: true,
  },
};

function formatDate(value, withTime = false) {
  if (!value) return "—";
  const date = new Date(withTime ? value : `${value}T00:00:00`);
  return new Intl.DateTimeFormat("en-PH", {
    month: "short",
    day: "numeric",
    year: "numeric",
    ...(withTime ? { hour: "numeric", minute: "2-digit" } : {}),
  }).format(date);
}

function DetailBlock({ label, children }) {
  return (
    <div>
      <dt className="text-xs font-bold uppercase tracking-wide text-slate-400">
        {label}
      </dt>
      <dd className="mt-1 text-sm leading-6 text-slate-700">
        {children || "—"}
      </dd>
    </div>
  );
}

/**
 * Coordinates the detailed Annual Plan workspace: imported priorities,
 * engagements, workflow actions, capacity, and supporting records.
 */
export default function IapPlanWorkspacePage() {
  const { planId } = useParams();
  const { user } = useAuth();
  const navigate = useNavigate();
  const toast = useToast();
  const [plan, setPlan] = useState(null);
  useRecordView(plan, {
    module: "IAP",
    recordType: "IAP_PLAN",
    code: (record) => record.planCode,
    label: (record) => record.title,
  });
  const [completeness, setCompleteness] = useState({
    complete: false,
    errors: [],
  });
  const [masterLists, setMasterLists] = useState([]);
  const [users, setUsers] = useState([]);
  const [offices, setOffices] = useState([]);
  const [riskAssessments, setRiskAssessments] = useState([]);
  const [finalizedPrioritizations, setFinalizedPrioritizations] = useState([]);
  const [prioritizationRunId, setPrioritizationRunId] = useState("");
  const [prioritizationFilter, setPrioritizationFilter] = useState("ALL");
  const [importItem, setImportItem] = useState(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [loadError, setLoadError] = useState("");
  const [errors, setErrors] = useState({});
  const [editOpen, setEditOpen] = useState(false);
  const [riskEditorOpen, setRiskEditorOpen] = useState(false);
  const [editingRisk, setEditingRisk] = useState(null);
  const [selectedRisk, setSelectedRisk] = useState(null);
  useRecordView(selectedRisk, {
    module: "IAP",
    recordType: "IAP_RISK",
    code: (record) => record.office?.code,
    label: (record) =>
      `${record.office?.name ?? "Office"} — ${record.auditArea?.name ?? "Risk assessment"}`,
  });
  const [showArchivedRisks, setShowArchivedRisks] = useState(false);
  const [archiveRiskTarget, setArchiveRiskTarget] = useState(null);
  const [restoreRiskTarget, setRestoreRiskTarget] = useState(null);
  const [workflowAction, setWorkflowAction] = useState("");
  const [comment, setComment] = useState("");
  const [completionConfirmed, setCompletionConfirmed] = useState(false);

  const isManagement = hasPermission(user, "iap.manage_universe");
  const canAssessRisk = hasPermission(user, "iap.assess_risk");
  const canEdit =
    plan &&
    hasPermission(user, "iap.update") &&
    ["DRAFT", "RETURNED_FOR_REVISION"].includes(plan.status) &&
    (isManagement || Number(plan.preparedBy) === Number(user.id));

  const load = useCallback(async () => {
    setLoading(true);
    setLoadError("");
    try {
      const [
        record,
        check,
        lists,
        officeRecords,
        riskRecords,
        prioritizations,
      ] = await Promise.all([
        iapApi.show(planId),
        iapApi.completeness(planId),
        masterListApi.list(),
        canAssessRisk ? officeApi.list() : Promise.resolve([]),
        canAssessRisk
          ? iapApi.listRiskAssessments(planId, { includeArchived: true })
          : Promise.resolve(null),
        prioritizationApi.list({ status: "FINALIZED", perPage: 100 }),
      ]);
      setPlan(record);
      setCompleteness(check);
      setMasterLists(lists);
      setOffices(officeRecords);
      setRiskAssessments(riskRecords ?? record.riskAssessments ?? []);
      setFinalizedPrioritizations(prioritizations.prioritizations);
      setPrioritizationRunId(record.prioritizationRunId ?? "");

      if (hasPermission(user, "users.view")) {
        setUsers(await userApi.list());
      } else {
        setUsers(
          [
            {
              id: user.id,
              employeeId: user.employeeId,
              name: user.name,
              role: user.role,
              roleCode: user.roleCode,
              office: user.office,
              isActive: true,
              isArchived: false,
            },
            record.preparer && {
              ...record.preparer,
              permissions: ["iap.assign_team"],
              isActive: true,
              isArchived: false,
            },
            record.coordinator && {
              ...record.coordinator,
              permissions: ["iap.assign_team"],
              isActive: true,
              isArchived: false,
            },
          ].filter(
            (candidate, index, candidates) =>
              candidate &&
              candidates.findIndex(
                (other) => Number(other?.id) === Number(candidate.id),
              ) === index,
          ),
        );
      }
    } catch (error) {
      setLoadError(
        error instanceof Error ? error.message : "Unable to load the plan.",
      );
    } finally {
      setLoading(false);
    }
  }, [canAssessRisk, planId, user]);

  useEffect(() => {
    const timer = window.setTimeout(load, 0);
    return () => window.clearTimeout(timer);
  }, [load]);

  const availableActions = useMemo(() => {
    if (!plan) return [];
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
      isManagement
    ) {
      if (hasPermission(user, "iap.review")) {
        actions.push("return", "reject");
      }
      if (
        hasPermission(user, "iap.approve") &&
        Number(plan.submittedBy) !== Number(user.id)
      ) {
        actions.push("approve");
      }
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
  }, [isManagement, plan, user]);

  const riskCriteria = useMemo(
    () =>
      (
        masterLists.find((list) => list.code === "IAP_RISK_CRITERION")?.items ??
        []
      ).filter((item) => item.isActive && !item.isArchived),
    [masterLists],
  );
  const riskLevels = useMemo(
    () =>
      (
        masterLists.find((list) => list.code === "RISK_LEVEL")?.items ?? []
      ).filter((item) => item.isActive && !item.isArchived),
    [masterLists],
  );
  const visibleRisks = useMemo(
    () =>
      riskAssessments.filter(
        (assessment) => showArchivedRisks || !assessment.isArchived,
      ),
    [riskAssessments, showArchivedRisks],
  );

  async function updatePlan(payload) {
    setSaving(true);
    setErrors({});
    try {
      await iapApi.update(plan.id, {
        ...payload,
        lockVersion: plan.lockVersion,
      });
      toast.success(`${plan.planCode} was updated.`);
      setEditOpen(false);
      await load();
    } catch (error) {
      if (error instanceof ApiError) setErrors(error.errors);
      toast.error(
        error instanceof Error ? error.message : "Unable to update the plan.",
      );
    } finally {
      setSaving(false);
    }
  }

  async function performWorkflow() {
    if (!workflowAction) return;
    setSaving(true);
    try {
      if (workflowAction === "revision") {
        const revision = await iapApi.createRevision(plan.id, {
          lockVersion: plan.lockVersion,
          reason: comment,
        });
        toast.success(`${revision.planCode} was created as a new draft.`);
        setWorkflowAction("");
        setComment("");
        navigate(`/internal-audit-planning/${revision.id}`);
        return;
      }

      await iapApi.transition(plan.id, workflowAction, {
        lockVersion: plan.lockVersion,
        comment: comment.trim() || null,
        completionConfirmed,
      });
      toast.success(
        `${plan.planCode} was ${actionDetails[workflowAction].label.toLowerCase()}.`,
      );
      setWorkflowAction("");
      setComment("");
      setCompletionConfirmed(false);
      await load();
    } catch (error) {
      toast.error(
        error instanceof Error
          ? error.message
          : "Unable to update the plan workflow.",
      );
    } finally {
      setSaving(false);
    }
  }

  async function saveRiskAssessment(payload) {
    setSaving(true);
    setErrors({});
    try {
      const requestPayload = {
        ...payload,
        lockVersion: plan.lockVersion,
      };
      if (editingRisk) {
        await iapApi.updateRiskAssessment(
          plan.id,
          editingRisk.id,
          requestPayload,
        );
        toast.success("Risk assessment updated successfully.");
      } else {
        await iapApi.createRiskAssessment(plan.id, requestPayload);
        toast.success("Risk assessment added to the annual plan.");
      }
      setRiskEditorOpen(false);
      setEditingRisk(null);
      await load();
    } catch (error) {
      if (error instanceof ApiError) setErrors(error.errors);
      toast.error(
        error instanceof Error
          ? error.message
          : "Unable to save the risk assessment.",
      );
    } finally {
      setSaving(false);
    }
  }

  async function archiveRiskAssessment() {
    if (!archiveRiskTarget) return;
    setSaving(true);
    try {
      await iapApi.archiveRiskAssessment(plan.id, archiveRiskTarget.id);
      toast.success("Risk assessment archived successfully.");
      setArchiveRiskTarget(null);
      await load();
    } catch (error) {
      toast.error(
        error instanceof Error
          ? error.message
          : "Unable to archive the risk assessment.",
      );
    } finally {
      setSaving(false);
    }
  }

  async function restoreRiskAssessment() {
    if (!restoreRiskTarget) return;
    setSaving(true);
    try {
      await iapApi.restoreRiskAssessment(plan.id, restoreRiskTarget.id);
      toast.success("Risk assessment restored successfully.");
      setRestoreRiskTarget(null);
      await load();
    } catch (error) {
      toast.error(
        error instanceof Error
          ? error.message
          : "Unable to restore the risk assessment.",
      );
    } finally {
      setSaving(false);
    }
  }

  async function connectPrioritization() {
    if (!prioritizationRunId) return;
    setSaving(true);
    setErrors({});
    try {
      await iapApi.connectPrioritization(plan.id, {
        prioritizationRunId,
        lockVersion: plan.lockVersion,
      });
      toast.success("Finalized prioritization connected to the annual plan.");
      await load();
    } catch (error) {
      if (error instanceof ApiError) setErrors(error.errors);
      toast.error(
        error instanceof Error
          ? error.message
          : "Unable to connect the prioritization.",
      );
    } finally {
      setSaving(false);
    }
  }

  async function importPrioritizedEngagement(payload) {
    setSaving(true);
    setErrors({});
    try {
      await iapApi.createEngagement(plan.id, payload);
      toast.success(`${importItem.subjectCode} was added to the annual plan.`);
      setImportItem(null);
      await load();
    } catch (error) {
      if (error instanceof ApiError) setErrors(error.errors);
      toast.error(
        error instanceof Error
          ? error.message
          : "Unable to import the selected subject.",
      );
    } finally {
      setSaving(false);
    }
  }

  if (loading) {
    return (
      <main className="grid min-h-[calc(100vh-7rem)] place-items-center p-5">
        <span className="flex items-center gap-3 text-sm font-semibold text-slate-500">
          <RefreshCw className="animate-spin" size={20} />
          Loading annual plan...
        </span>
      </main>
    );
  }

  if (!plan || loadError) {
    return (
      <main className="p-5">
        <button
          className="mb-4 inline-flex items-center gap-2 text-sm font-bold text-sky-700 hover:text-sky-900"
          onClick={() => navigate("/internal-audit-planning")}
          type="button"
        >
          <ArrowLeft size={17} />
          Back to annual plans
        </button>
        <div className="rounded-xl border border-red-200 bg-red-50 p-5 text-sm font-semibold text-red-700">
          {loadError || "The requested annual plan could not be found."}
        </div>
      </main>
    );
  }

  const riskColumns = [
    {
      key: "office",
      label: "Office",
      render: (risk) => (
        <span>
          <strong className="text-slate-800">{risk.office?.code}</strong>
          <span className="ml-2 text-xs text-slate-500">
            {risk.office?.name}
          </span>
        </span>
      ),
    },
    {
      key: "auditArea",
      label: "Audit Area",
      render: (risk) => (
        <span>
          <strong className="block text-sm text-slate-700">
            {risk.auditArea?.name}
          </strong>
          <span className="text-xs text-slate-500">{risk.auditArea?.code}</span>
        </span>
      ),
    },
    {
      key: "totalWeightedScore",
      label: "Score",
      className: "font-bold text-slate-800",
    },
    {
      key: "finalRiskLevel",
      label: "Risk Level",
      sortValue: (risk) => risk.finalRiskLevel?.label,
      render: (risk) => (
        <StatusBadge
          tone={
            ["CRITICAL", "HIGH"].includes(risk.finalRiskLevel?.code)
              ? "danger"
              : risk.finalRiskLevel?.code === "MEDIUM"
                ? "warning"
                : "success"
          }
        >
          {risk.finalRiskLevel?.label ?? "Not rated"}
        </StatusBadge>
      ),
    },
    {
      key: "assessmentDate",
      label: "Assessed",
      render: (risk) => formatDate(risk.assessmentDate),
    },
    {
      key: "recordStatus",
      label: "Record Status",
      sortValue: (risk) => (risk.isArchived ? "Archived" : "Active"),
      render: (risk) => (
        <StatusBadge tone={risk.isArchived ? "inactive" : "active"}>
          {risk.isArchived ? "Archived" : "Active"}
        </StatusBadge>
      ),
    },
    ...(canAssessRisk && canEdit
      ? [
          {
            key: "actions",
            label: "Actions",
            sortable: false,
            render: (risk) => (
              <div className="flex justify-end gap-1.5">
                {risk.isArchived ? (
                  <button
                    aria-label="Restore risk assessment"
                    className="grid h-9 w-9 place-items-center rounded-lg border border-emerald-200 text-emerald-700 transition hover:bg-emerald-50"
                    onClick={() => setRestoreRiskTarget(risk)}
                    type="button"
                  >
                    <RotateCcw size={16} />
                  </button>
                ) : (
                  <>
                    <button
                      aria-label="Edit risk assessment"
                      className="grid h-9 w-9 place-items-center rounded-lg border border-sky-200 text-sky-700 transition hover:bg-sky-50"
                      onClick={() => {
                        setErrors({});
                        setEditingRisk(risk);
                        setRiskEditorOpen(true);
                      }}
                      type="button"
                    >
                      <Pencil size={16} />
                    </button>
                    <button
                      aria-label="Archive risk assessment"
                      className="grid h-9 w-9 place-items-center rounded-lg border border-red-200 text-red-600 transition hover:bg-red-50"
                      onClick={() => setArchiveRiskTarget(risk)}
                      type="button"
                    >
                      <Archive size={16} />
                    </button>
                  </>
                )}
              </div>
            ),
          },
        ]
      : []),
  ];

  const engagementColumns = [
    {
      key: "engagementCode",
      label: "Engagement",
      render: (engagement) => (
        <span>
          <strong className="block text-sm text-slate-800">
            {engagement.engagementCode}
          </strong>
          <span className="mt-0.5 block max-w-md text-xs text-slate-500">
            {engagement.title}
          </span>
        </span>
      ),
    },
    {
      key: "priority",
      label: "Priority",
      sortValue: (engagement) => engagement.priority?.label,
      render: (engagement) => engagement.priority?.label ?? "—",
    },
    {
      key: "riskLevel",
      label: "Risk",
      sortValue: (engagement) => engagement.riskLevel?.label,
      render: (engagement) => (
        <span>
          <StatusBadge
            tone={
              ["CRITICAL", "HIGH"].includes(engagement.riskLevel?.code)
                ? "danger"
                : engagement.riskLevel?.code === "MEDIUM"
                  ? "warning"
                  : "success"
            }
          >
            {engagement.riskLevel?.label ?? "Not rated"}
          </StatusBadge>
          {engagement.sourcePriorityScore !== null &&
            engagement.sourcePriorityScore !== undefined && (
              <span className="mt-1 block text-[11px] text-slate-500">
                Priority score {engagement.sourcePriorityScore}
              </span>
            )}
        </span>
      ),
    },
    {
      key: "plannedStartDate",
      label: "Schedule",
      render: (engagement) => (
        <span className="whitespace-nowrap text-xs">
          {formatDate(engagement.plannedStartDate)}
          <span className="mx-1 text-slate-300">→</span>
          {formatDate(engagement.plannedEndDate)}
        </span>
      ),
    },
    {
      key: "estimatedPersonDays",
      label: "Person-days",
      className: "font-bold text-slate-700",
    },
    {
      key: "targetQuarter",
      label: "Quarter",
      render: (engagement) =>
        engagement.targetQuarter ? `Q${engagement.targetQuarter}` : "—",
    },
    {
      key: "offices",
      label: "Coverage",
      sortable: false,
      render: (engagement) => (
        <span className="text-xs">
          {engagement.offices.length} office
          {engagement.offices.length === 1 ? "" : "s"} ·{" "}
          {engagement.auditAreas.length} area
          {engagement.auditAreas.length === 1 ? "" : "s"}
        </span>
      ),
    },
    {
      key: "teamMembers",
      label: "Team",
      sortable: false,
      render: (engagement) => (
        <span className="text-xs font-semibold text-slate-600">
          {engagement.teamMembers.length} assigned
        </span>
      ),
    },
  ];

  const prioritizationItems = plan.prioritizationRun?.items ?? [];
  const prioritizationCounts = prioritizationItems.reduce(
    (counts, item) => ({
      ...counts,
      [item.planningState]: (counts[item.planningState] ?? 0) + 1,
    }),
    {},
  );
  const visiblePrioritizationItems =
    prioritizationFilter === "ALL"
      ? prioritizationItems
      : prioritizationFilter === "SELECTED"
        ? prioritizationItems.filter((item) => item.decision === "SELECTED")
        : prioritizationItems.filter(
            (item) => item.planningState === prioritizationFilter,
          );
  const prioritizationColumns = [
    {
      key: "finalRank",
      label: "Rank",
      className: "font-bold text-slate-800",
    },
    {
      key: "subjectName",
      label: "Audit Universe Subject",
      render: (item) => (
        <span>
          <strong className="block text-sm text-slate-800">
            {item.subjectName}
          </strong>
          <span className="mt-0.5 block text-xs text-slate-500">
            {item.subjectCode} · {item.officeCode} — {item.officeName}
          </span>
        </span>
      ),
    },
    {
      key: "auditAreaName",
      label: "Audit Area",
      render: (item) => (
        <span>
          <strong className="block text-xs text-slate-700">
            {item.auditAreaName}
          </strong>
          <span className="text-[11px] text-slate-500">
            {item.auditAreaCode}
          </span>
        </span>
      ),
    },
    {
      key: "residualRiskScore",
      label: "Risk",
      render: (item) => (
        <span>
          <StatusBadge
            tone={
              ["CRITICAL", "HIGH"].includes(item.riskLevelCode)
                ? "danger"
                : item.riskLevelCode === "MEDIUM"
                  ? "warning"
                  : "success"
            }
          >
            {item.riskLevelLabel}
          </StatusBadge>
          <span className="mt-1 block text-[11px] text-slate-500">
            Residual {item.residualRiskScore} · Priority {item.priorityScore}
          </span>
        </span>
      ),
    },
    {
      key: "planningState",
      label: "Plan Status",
      render: (item) => (
        <StatusBadge
          tone={
            item.planningState === "PLANNED"
              ? "success"
              : item.planningState === "UNPLANNED"
                ? "warning"
                : "inactive"
          }
        >
          {item.planningState.replaceAll("_", " ")}
        </StatusBadge>
      ),
    },
    ...(canEdit && hasPermission(user, "iap.manage_engagements")
      ? [
          {
            key: "actions",
            label: "Actions",
            sortable: false,
            render: (item) =>
              item.planningState === "UNPLANNED" ? (
                <button
                  className="inline-flex min-h-9 items-center gap-2 rounded-lg bg-sky-700 px-3 text-xs font-bold text-white transition hover:-translate-y-0.5 hover:bg-sky-800 hover:shadow-md"
                  onClick={() => {
                    setErrors({});
                    setImportItem(item);
                  }}
                  type="button"
                >
                  <ListPlus size={15} />
                  Import to plan
                </button>
              ) : (
                <span className="text-xs text-slate-400">
                  {item.planningState === "PLANNED"
                    ? "Already imported"
                    : "Not eligible"}
                </span>
              ),
          },
        ]
      : []),
  ];

  const activeAction = actionDetails[workflowAction];
  const ActiveActionIcon = activeAction?.icon ?? CircleDot;

  return (
    <main className="p-3 sm:p-5">
      <button
        className="mb-4 inline-flex items-center gap-2 text-sm font-bold text-sky-700 transition hover:-translate-x-0.5 hover:text-sky-900"
        onClick={() => navigate("/internal-audit-planning")}
        type="button"
      >
        <ArrowLeft size={17} />
        Back to annual plans
      </button>

      <RegistryHeader
        actions={
          <>
            {canEdit && (
              <button
                className="inline-flex min-h-11 items-center gap-2 rounded-lg border border-sky-200 bg-white px-4 text-sm font-bold text-sky-700 transition hover:-translate-y-0.5 hover:bg-sky-50 hover:shadow-md"
                onClick={() => {
                  setErrors({});
                  setEditOpen(true);
                }}
                type="button"
              >
                <Pencil size={17} />
                Edit plan
              </button>
            )}
            {availableActions.map((action) => {
              const details = actionDetails[action];
              const ActionIcon = details.icon;
              return (
                <button
                  className={`inline-flex min-h-11 items-center gap-2 rounded-lg px-4 text-sm font-bold text-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg ${details.button}`}
                  key={action}
                  onClick={() => {
                    setComment("");
                    setCompletionConfirmed(false);
                    setWorkflowAction(action);
                  }}
                  type="button"
                >
                  <ActionIcon size={17} />
                  {details.label}
                </button>
              );
            })}
          </>
        }
        description={`${plan.planCode} · Fiscal year ${plan.fiscalYear} · Revision ${plan.revisionNumber}`}
        icon={CalendarDays}
        readOnly={!canEdit && availableActions.length === 0}
        title={plan.title}
      />

      {["APPROVED", "ACTIVE", "COMPLETED"].includes(plan.status) && (
        <div className="mb-4 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
          <LockKeyhole className="mt-0.5 shrink-0" size={19} />
          <span>
            <strong className="block">This approved version is frozen.</strong>
            Plan content, engagement requirements, and approved schedules are
            read-only. Use <em>Create revision</em> when a formal change is
            required.
          </span>
        </div>
      )}

      <section className="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <SummaryCard
          icon={ShieldAlert}
          label="Risk Assessments"
          tone="amber"
          value={
            riskAssessments.filter((assessment) => !assessment.isArchived)
              .length
          }
        />
        <SummaryCard
          icon={Target}
          label="Proposed Engagements"
          tone="sky"
          value={plan.engagements?.length ?? 0}
        />
        <SummaryCard
          icon={UsersRound}
          label="Unique Team Members"
          tone="slate"
          value={
            new Set(
              (plan.engagements ?? []).flatMap((engagement) =>
                engagement.teamMembers.map((member) => member.userId),
              ),
            ).size
          }
        />
        <SummaryCard
          icon={completeness.complete ? CheckCircle2 : AlertCircle}
          label="Submission Readiness"
          tone={completeness.complete ? "emerald" : "red"}
          value={completeness.complete ? "Ready" : "Incomplete"}
        />
      </section>

      <IapSupportingRecordsPanel planId={plan.id} />

      <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div className="grid min-w-0 gap-4">
          <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="flex flex-wrap items-center gap-3 border-b border-slate-100 pb-4">
              <h3 className="text-base font-bold text-slate-800">
                Plan overview
              </h3>
              <StatusBadge tone={statusTones[plan.status]}>
                {statusLabels[plan.status] ?? plan.status}
              </StatusBadge>
              {!plan.isCurrentRevision && (
                <StatusBadge tone="warning">Superseded revision</StatusBadge>
              )}
            </div>
            <dl className="mt-5 grid gap-5 sm:grid-cols-2">
              <DetailBlock label="Planning period">
                {formatDate(plan.planningPeriodStart)} –{" "}
                {formatDate(plan.planningPeriodEnd)}
              </DetailBlock>
              <DetailBlock label="Period type">
                {plan.planningPeriodType?.label}
              </DetailBlock>
              <DetailBlock label="Prepared by">
                {plan.preparer?.name}{" "}
                <span className="text-xs text-slate-400">
                  ({plan.preparer?.employeeId})
                </span>
              </DetailBlock>
              <DetailBlock label="Plan coordinator">
                {plan.coordinator?.name}
              </DetailBlock>
              <div className="sm:col-span-2">
                <DetailBlock label="Executive summary">
                  {plan.executiveSummary}
                </DetailBlock>
              </div>
              <div className="sm:col-span-2">
                <DetailBlock label="Planning methodology">
                  {plan.planningMethodology}
                </DetailBlock>
              </div>
              <DetailBlock label="Overall objective">
                {plan.overallObjective}
              </DetailBlock>
              <DetailBlock label="Overall scope">
                {plan.overallScope}
              </DetailBlock>
              {plan.limitations && (
                <div className="sm:col-span-2">
                  <DetailBlock label="Known limitations">
                    {plan.limitations}
                  </DetailBlock>
                </div>
              )}
            </dl>
          </section>

          <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <header className="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-5 py-4">
              <div>
                <h3 className="flex items-center gap-2 text-base font-bold text-slate-800">
                  <Link2 className="text-sky-700" size={18} />
                  Audit prioritization source
                </h3>
                <p className="mt-1 text-xs leading-5 text-slate-500">
                  Import selected Audit Universe subjects while preserving their
                  assessment, ranking, decision, office, and audit-area lineage.
                </p>
              </div>
              {plan.prioritizationRun && (
                <div className="text-right">
                  <strong className="block text-sm text-slate-800">
                    {plan.prioritizationRun.runCode}
                  </strong>
                  <span className="text-xs text-slate-500">
                    {plan.prioritizationRun.riskPeriod?.name}
                  </span>
                </div>
              )}
            </header>

            {!plan.prioritizationRun ? (
              <div className="grid gap-4 p-5">
                <div className="rounded-xl border border-amber-200 bg-amber-50 p-4">
                  <strong className="text-sm text-amber-900">
                    Connect a finalized prioritization
                  </strong>
                  <p className="mt-1 text-xs leading-5 text-amber-800">
                    Only finalized results are accepted. Once a subject is
                    imported, the source run cannot be replaced.
                  </p>
                </div>
                {canEdit && hasPermission(user, "iap.manage_engagements") ? (
                  <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div className="min-w-0 flex-1">
                      <label className="mb-1.5 block text-sm font-semibold text-slate-700">
                        Finalized prioritization run
                      </label>
                      <SearchableSelect
                        onChange={setPrioritizationRunId}
                        options={finalizedPrioritizations.map((run) => ({
                          value: run.id,
                          label: `${run.runCode} — ${run.name}`,
                          keywords: `${run.riskPeriod?.name ?? ""} ${run.riskPeriod?.assessmentYear ?? ""}`,
                        }))}
                        placeholder="Search finalized prioritizations..."
                        value={prioritizationRunId}
                      />
                      {errors.prioritizationRunId?.[0] && (
                        <p className="mt-1.5 text-xs font-medium text-red-600">
                          {errors.prioritizationRunId[0]}
                        </p>
                      )}
                    </div>
                    <button
                      className="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-sky-700 px-5 text-sm font-bold text-white transition hover:-translate-y-0.5 hover:bg-sky-800 hover:shadow-md disabled:opacity-50"
                      disabled={saving || !prioritizationRunId}
                      onClick={connectPrioritization}
                      type="button"
                    >
                      <Link2 size={17} />
                      {saving ? "Connecting..." : "Connect source"}
                    </button>
                  </div>
                ) : (
                  <p className="text-sm text-slate-500">
                    No finalized prioritization has been connected to this plan.
                  </p>
                )}
              </div>
            ) : (
              <>
                <div className="grid gap-3 border-b border-slate-100 bg-slate-50/70 p-4 sm:grid-cols-3">
                  {[
                    ["SELECTED", "Selected subjects", "emerald"],
                    ["UNPLANNED", "Unplanned selected", "amber"],
                    ["DEFERRED", "Deferred subjects", "slate"],
                  ].map(([state, label, tone]) => {
                    const value =
                      state === "SELECTED"
                        ? prioritizationItems.filter(
                            (item) => item.decision === "SELECTED",
                          ).length
                        : (prioritizationCounts[state] ?? 0);
                    return (
                      <button
                        className={`rounded-xl border p-3 text-left transition hover:-translate-y-0.5 hover:shadow-sm ${
                          prioritizationFilter === state
                            ? "border-sky-400 bg-white ring-2 ring-sky-100"
                            : "border-slate-200 bg-white"
                        }`}
                        key={state}
                        onClick={() =>
                          setPrioritizationFilter((current) =>
                            current === state ? "ALL" : state,
                          )
                        }
                        type="button"
                      >
                        <span
                          className={`text-2xl font-black ${
                            tone === "emerald"
                              ? "text-emerald-700"
                              : tone === "amber"
                                ? "text-amber-700"
                                : "text-slate-700"
                          }`}
                        >
                          {value}
                        </span>
                        <span className="ml-2 text-xs font-bold uppercase tracking-wide text-slate-500">
                          {label}
                        </span>
                      </button>
                    );
                  })}
                </div>
                <DataTable
                  columns={prioritizationColumns}
                  emptyMessage="No subjects match this planning status."
                  rows={visiblePrioritizationItems}
                />
              </>
            )}
          </section>

          <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <header className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
              <div>
                <h3 className="text-base font-bold text-slate-800">
                  Risk assessments
                </h3>
                <p className="mt-0.5 text-xs text-slate-500">
                  Office and audit-area risks supporting plan prioritization.
                </p>
              </div>
              <div className="flex flex-wrap items-center gap-2">
                {canAssessRisk && (
                  <label className="inline-flex min-h-10 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-600">
                    <input
                      checked={showArchivedRisks}
                      onChange={(event) =>
                        setShowArchivedRisks(event.target.checked)
                      }
                      type="checkbox"
                    />
                    Show archived
                  </label>
                )}
                {canAssessRisk && canEdit && (
                  <button
                    className="inline-flex min-h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-xs font-bold text-white transition hover:-translate-y-0.5 hover:bg-sky-800 hover:shadow-md"
                    onClick={() => {
                      setErrors({});
                      setEditingRisk(null);
                      setRiskEditorOpen(true);
                    }}
                    type="button"
                  >
                    <Plus size={16} />
                    Add risk assessment
                  </button>
                )}
              </div>
            </header>
            <DataTable
              columns={riskColumns}
              emptyMessage="No risk assessments have been added to this plan."
              onRowClick={setSelectedRisk}
              rows={visibleRisks}
            />
          </section>

          <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <header className="flex items-center justify-between border-b border-slate-200 px-5 py-4">
              <div>
                <h3 className="text-base font-bold text-slate-800">
                  Proposed audit engagements
                </h3>
                <p className="mt-0.5 text-xs text-slate-500">
                  Planned scope, schedule, resources, and assigned teams.
                </p>
              </div>
              {hasPermission(user, "iap.manage_engagements") && canEdit && (
                <span className="rounded-lg bg-violet-50 px-3 py-2 text-xs font-bold text-violet-700">
                  Import selected subjects from the source above
                </span>
              )}
            </header>
            <DataTable
              columns={engagementColumns}
              emptyMessage="No proposed audit engagements have been added."
              rows={plan.engagements ?? []}
            />
          </section>
        </div>

        <aside className="grid content-start gap-4">
          <section
            className={`rounded-xl border p-4 shadow-sm ${
              completeness.complete
                ? "border-emerald-200 bg-emerald-50"
                : "border-amber-200 bg-amber-50"
            }`}
          >
            <div className="flex items-start gap-3">
              {completeness.complete ? (
                <CheckCircle2 className="shrink-0 text-emerald-700" size={22} />
              ) : (
                <ListChecks className="shrink-0 text-amber-700" size={22} />
              )}
              <div>
                <h3 className="text-sm font-bold text-slate-800">
                  Submission readiness
                </h3>
                <p className="mt-1 text-xs leading-5 text-slate-600">
                  {completeness.complete
                    ? "The plan has the minimum risk, engagement, coverage, schedule, and team information required for submission."
                    : "Complete the items below before submitting the plan."}
                </p>
              </div>
            </div>
            {!completeness.complete && (
              <ul className="mt-4 grid gap-2">
                {completeness.errors.map((error) => (
                  <li
                    className="flex gap-2 rounded-lg bg-white/70 px-3 py-2 text-xs leading-5 text-amber-900"
                    key={error}
                  >
                    <AlertCircle className="mt-0.5 shrink-0" size={14} />
                    {error}
                  </li>
                ))}
              </ul>
            )}
          </section>

          <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <h3 className="flex items-center gap-2 text-sm font-bold text-slate-800">
              <FileClock className="text-sky-700" size={18} />
              Workflow history
            </h3>
            <ol className="mt-4 grid gap-0">
              {(plan.workflowEvents ?? [])
                .slice()
                .reverse()
                .map((event, index) => (
                  <li className="relative flex gap-3 pb-5" key={event.id}>
                    {index < plan.workflowEvents.length - 1 && (
                      <span className="absolute left-[7px] top-4 h-full w-px bg-slate-200" />
                    )}
                    <span className="relative z-10 mt-1 h-4 w-4 shrink-0 rounded-full border-4 border-white bg-sky-600 shadow ring-1 ring-sky-200" />
                    <span className="min-w-0">
                      <strong className="block text-xs text-slate-800">
                        {event.action.replaceAll("_", " ")}
                      </strong>
                      <span className="mt-0.5 block text-[11px] text-slate-500">
                        {event.actor?.name} ·{" "}
                        {formatDate(event.createdAt, true)}
                      </span>
                      <span className="mt-1 inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600">
                        {event.fromStatus ?? "New"} → {event.toStatus}
                      </span>
                      {event.comment && (
                        <span className="mt-1 block text-xs leading-5 text-slate-600">
                          {event.comment}
                        </span>
                      )}
                    </span>
                  </li>
                ))}
            </ol>
          </section>

          <section className="rounded-xl border border-slate-200 bg-white p-4 text-xs text-slate-500 shadow-sm">
            <div className="flex justify-between gap-3">
              <span>Record version</span>
              <strong className="text-slate-700">{plan.lockVersion}</strong>
            </div>
            <div className="mt-2 flex justify-between gap-3">
              <span>Last updated</span>
              <strong className="text-right text-slate-700">
                {formatDate(plan.updatedAt, true)}
              </strong>
            </div>
          </section>
        </aside>
      </div>

      <Modal
        footer={
          <>
            <button
              className="min-h-10 rounded-lg border border-slate-300 px-4 text-sm font-bold text-slate-700 hover:bg-slate-50"
              disabled={saving}
              onClick={() => setEditOpen(false)}
              type="button"
            >
              Cancel
            </button>
            <button
              className="min-h-10 rounded-lg bg-sky-700 px-5 text-sm font-bold text-white hover:bg-sky-800 disabled:opacity-60"
              disabled={saving}
              form="edit-iap-plan-form"
              type="submit"
            >
              {saving ? "Saving..." : "Save plan changes"}
            </button>
          </>
        }
        onClose={() => !saving && setEditOpen(false)}
        open={editOpen}
        size="xl"
        title={`Edit ${plan.planCode}`}
      >
        <IapPlanForm
          currentUserId={user.id}
          errors={errors}
          formId="edit-iap-plan-form"
          masterLists={masterLists}
          onSubmit={updatePlan}
          plan={plan}
          users={users}
        />
      </Modal>

      <Modal
        footer={
          <>
            <button
              className="min-h-10 rounded-lg border border-slate-300 px-4 text-sm font-bold text-slate-700 hover:bg-slate-50"
              disabled={saving}
              onClick={() => setImportItem(null)}
              type="button"
            >
              Cancel
            </button>
            <button
              className="min-h-10 rounded-lg bg-sky-700 px-5 text-sm font-bold text-white hover:bg-sky-800 disabled:opacity-60"
              disabled={saving}
              form="iap-prioritized-engagement-form"
              type="submit"
            >
              {saving ? "Importing..." : "Import as engagement"}
            </button>
          </>
        }
        onClose={() => !saving && setImportItem(null)}
        open={Boolean(importItem)}
        size="xl"
        title="Plan selected Audit Universe subject"
      >
        {importItem && (
          <IapPrioritizedEngagementForm
            errors={errors}
            formId="iap-prioritized-engagement-form"
            item={importItem}
            key={importItem.id}
            masterLists={masterLists}
            onSubmit={importPrioritizedEngagement}
            plan={plan}
          />
        )}
      </Modal>

      <Modal
        footer={
          <>
            <button
              className="min-h-10 rounded-lg border border-slate-300 px-4 text-sm font-bold text-slate-700 hover:bg-slate-50"
              disabled={saving}
              onClick={() => {
                setRiskEditorOpen(false);
                setEditingRisk(null);
              }}
              type="button"
            >
              Cancel
            </button>
            <button
              className="min-h-10 rounded-lg bg-sky-700 px-5 text-sm font-bold text-white hover:bg-sky-800 disabled:opacity-60"
              disabled={saving}
              form="iap-risk-assessment-form"
              type="submit"
            >
              {saving
                ? "Saving..."
                : editingRisk
                  ? "Update assessment"
                  : "Add assessment"}
            </button>
          </>
        }
        onClose={() => {
          if (!saving) {
            setRiskEditorOpen(false);
            setEditingRisk(null);
          }
        }}
        open={riskEditorOpen}
        size="xl"
        title={
          editingRisk
            ? `Edit risk assessment · ${editingRisk.office?.code} / ${editingRisk.auditArea?.code}`
            : "Add risk assessment"
        }
      >
        <IapRiskAssessmentForm
          assessment={editingRisk}
          criteria={riskCriteria}
          errors={errors}
          formId="iap-risk-assessment-form"
          key={editingRisk?.id ?? "new-risk"}
          offices={offices}
          onSubmit={saveRiskAssessment}
          riskLevels={riskLevels}
        />
      </Modal>

      <Modal
        onClose={() => setSelectedRisk(null)}
        open={Boolean(selectedRisk)}
        size="xl"
        title="Risk assessment details"
      >
        {selectedRisk && (
          <div className="grid gap-5">
            <section className="rounded-xl border border-slate-200 bg-slate-50 p-4">
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <strong className="block text-base text-slate-900">
                    {selectedRisk.office?.code} — {selectedRisk.office?.name}
                  </strong>
                  <span className="mt-1 block text-sm text-slate-600">
                    {selectedRisk.auditArea?.code} —{" "}
                    {selectedRisk.auditArea?.name}
                  </span>
                </div>
                <div className="flex flex-wrap gap-2">
                  <StatusBadge
                    tone={
                      ["CRITICAL", "HIGH"].includes(
                        selectedRisk.finalRiskLevel?.code,
                      )
                        ? "danger"
                        : selectedRisk.finalRiskLevel?.code === "MEDIUM"
                          ? "warning"
                          : "success"
                    }
                  >
                    {selectedRisk.finalRiskLevel?.label}
                  </StatusBadge>
                  {selectedRisk.isArchived && (
                    <StatusBadge tone="inactive">Archived</StatusBadge>
                  )}
                </div>
              </div>
              <div className="mt-4 grid gap-3 sm:grid-cols-3">
                <div className="rounded-lg bg-white p-3 shadow-sm">
                  <span className="text-xs font-bold uppercase text-slate-400">
                    Weighted score
                  </span>
                  <strong className="mt-1 block text-2xl text-slate-900">
                    {Number(selectedRisk.totalWeightedScore).toFixed(2)}
                  </strong>
                </div>
                <div className="rounded-lg bg-white p-3 shadow-sm">
                  <span className="text-xs font-bold uppercase text-slate-400">
                    Calculated level
                  </span>
                  <strong className="mt-2 block text-sm text-slate-800">
                    {selectedRisk.calculatedRiskLevel?.label}
                  </strong>
                </div>
                <div className="rounded-lg bg-white p-3 shadow-sm">
                  <span className="text-xs font-bold uppercase text-slate-400">
                    Assessment date
                  </span>
                  <strong className="mt-2 block text-sm text-slate-800">
                    {formatDate(selectedRisk.assessmentDate)}
                  </strong>
                </div>
              </div>
            </section>

            <section>
              <h3 className="text-sm font-bold text-slate-800">
                Criterion scores
              </h3>
              <div className="mt-2 overflow-x-auto rounded-xl border border-slate-200">
                <table className="min-w-full text-left text-sm">
                  <thead className="bg-slate-50 text-[11px] font-bold uppercase tracking-wide text-slate-500">
                    <tr>
                      <th className="px-4 py-3">Criterion</th>
                      <th className="px-4 py-3">Weight</th>
                      <th className="px-4 py-3">Rating</th>
                      <th className="px-4 py-3">Weighted</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100 bg-white">
                    {selectedRisk.scores.map((score) => (
                      <tr key={score.criterionId}>
                        <td className="px-4 py-3">
                          <strong className="block text-slate-800">
                            {score.criterion?.label}
                          </strong>
                          {score.comment && (
                            <span className="mt-1 block text-xs text-slate-500">
                              {score.comment}
                            </span>
                          )}
                        </td>
                        <td className="px-4 py-3">{score.weight}%</td>
                        <td className="px-4 py-3">{score.rating} / 5</td>
                        <td className="px-4 py-3 font-bold text-slate-700">
                          {Number(score.weightedScore).toFixed(4)}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </section>

            <dl className="grid gap-4 sm:grid-cols-2">
              <DetailBlock label="Assessment justification">
                {selectedRisk.justification}
              </DetailBlock>
              <DetailBlock label="Last audit date">
                {formatDate(selectedRisk.lastAuditDate)}
              </DetailBlock>
              <DetailBlock label="Inherent risk notes">
                {selectedRisk.inherentRiskNotes}
              </DetailBlock>
              <DetailBlock label="Control environment notes">
                {selectedRisk.controlEnvironmentNotes}
              </DetailBlock>
            </dl>

            {selectedRisk.overrideRiskLevel && (
              <section className="rounded-xl border border-violet-200 bg-violet-50 p-4">
                <h3 className="text-sm font-bold text-violet-900">
                  Management override: {selectedRisk.overrideRiskLevel.label}
                </h3>
                <p className="mt-2 text-sm leading-6 text-violet-800">
                  {selectedRisk.overrideReason}
                </p>
              </section>
            )}
          </div>
        )}
      </Modal>

      <Modal
        footer={
          <>
            <button
              className="min-h-10 rounded-lg border border-slate-300 px-4 text-sm font-bold text-slate-700 hover:bg-slate-50"
              disabled={saving}
              onClick={() => setWorkflowAction("")}
              type="button"
            >
              Cancel
            </button>
            <button
              className={`min-h-10 rounded-lg px-5 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-50 ${activeAction?.button}`}
              disabled={
                saving ||
                (activeAction?.commentRequired && !comment.trim()) ||
                (activeAction?.confirmation && !completionConfirmed)
              }
              onClick={performWorkflow}
              type="button"
            >
              {saving ? "Please wait..." : activeAction?.label}
            </button>
          </>
        }
        onClose={() => !saving && setWorkflowAction("")}
        open={Boolean(workflowAction)}
        size="sm"
        title={activeAction?.title}
      >
        {activeAction && (
          <div className="grid gap-4">
            <div className="flex items-start gap-3">
              <span className="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-sky-50 text-sky-700">
                <ActiveActionIcon size={21} />
              </span>
              <p className="text-sm leading-6 text-slate-600">
                {activeAction.description}
              </p>
            </div>
            {(activeAction.commentRequired ||
              ["approve", "submit", "resubmit"].includes(workflowAction)) && (
              <label>
                <span className="mb-1.5 block text-sm font-semibold text-slate-700">
                  {workflowAction === "revision"
                    ? "Revision reason"
                    : "Workflow comment"}
                  {activeAction.commentRequired && (
                    <span className="ml-1 text-red-500">*</span>
                  )}
                </span>
                <textarea
                  className="min-h-28 w-full rounded-lg border border-slate-300 p-3 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100"
                  onChange={(event) => setComment(event.target.value)}
                  placeholder="Enter the decision, instruction, or supporting note..."
                  value={comment}
                />
              </label>
            )}
            {activeAction.confirmation && (
              <label className="flex items-start gap-3 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900">
                <input
                  checked={completionConfirmed}
                  className="mt-1"
                  onChange={(event) =>
                    setCompletionConfirmed(event.target.checked)
                  }
                  type="checkbox"
                />
                <span>
                  I confirm that all planned engagements and required
                  implementation activities have been completed.
                </span>
              </label>
            )}
          </div>
        )}
      </Modal>

      <ConfirmDialog
        busy={saving}
        confirmLabel="Archive assessment"
        description={`${archiveRiskTarget?.office?.code ?? "This office"} / ${archiveRiskTarget?.auditArea?.code ?? "audit area"} will be removed from the active plan risk register but remain recoverable.`}
        onCancel={() => setArchiveRiskTarget(null)}
        onConfirm={archiveRiskAssessment}
        open={Boolean(archiveRiskTarget)}
        title="Archive risk assessment?"
        tone="danger"
      />
      <ConfirmDialog
        busy={saving}
        confirmLabel="Restore assessment"
        description={`${restoreRiskTarget?.office?.code ?? "This office"} / ${restoreRiskTarget?.auditArea?.code ?? "audit area"} will return to the active plan risk register.`}
        onCancel={() => setRestoreRiskTarget(null)}
        onConfirm={restoreRiskAssessment}
        open={Boolean(restoreRiskTarget)}
        title="Restore risk assessment?"
      />
    </main>
  );
}
