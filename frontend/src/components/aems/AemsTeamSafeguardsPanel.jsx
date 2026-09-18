import { useMemo, useState } from "react";
import {
  AlertTriangle,
  CheckCircle2,
  ClipboardCheck,
  Clock3,
  Eye,
  FileCheck2,
  RefreshCw,
  ShieldAlert,
  ShieldCheck,
  UserCheck,
  UsersRound,
} from "lucide-react";
import Modal from "../ui/Modal";
import StatusBadge from "../ui/StatusBadge";
import SummaryCard from "../ui/SummaryCard";
import { hasPermission } from "../../config/navigation";
import { aemsTeamSafeguardApi, ApiError } from "../../services/api";
import { useAuth } from "../../auth/auth-context";
import { useToast } from "../../ui/toast-context";

const declarationTypes = [
  ["OBJECTIVITY", "Objectivity"],
  ["CONFLICT_OF_INTEREST", "Conflict of interest"],
  ["INDEPENDENCE", "Independence"],
];

const emptyDeclaration = {
  declarationType: "OBJECTIVITY",
  outcome: "CLEAR",
  statement: "",
  mitigationPlan: "",
  evidenceDocumentVersionId: "",
  revisionReason: "",
};

function label(value) {
  return String(value ?? "")
    .replaceAll("_", " ")
    .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function number(value) {
  return Number(value ?? 0).toLocaleString("en-PH", {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  });
}

function dateTime(value) {
  if (!value) return "Not recorded";
  return new Intl.DateTimeFormat("en-PH", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(value));
}

function toneForStatus(status) {
  if (["APPROVED", "ACCEPTED", "CLEAR"].includes(status)) return "success";
  if (["PENDING", "SUBMITTED", "DISCLOSED"].includes(status)) return "warning";
  if (["RETURNED", "CONFLICT"].includes(status)) return "danger";
  return "info";
}

function resolverFor(blocker) {
  if (["DECLARATION_MISSING", "INDEPENDENCE_CONFLICT"].includes(blocker.code)) {
    return "Assigned resource submits a declaration; an independent reviewer resolves it.";
  }
  if (
    [
      "RESOURCE_PROVIDER_UNAVAILABLE",
      "RESOURCE_AUTHORITY_NOT_ELIGIBLE",
    ].includes(blocker.code)
  ) {
    return "ARMIS resource administrator must restore the authoritative ARMIS resource ledger.";
  }
  if (
    [
      "CAPACITY_CONFLICT",
      "WORKLOAD_OVERLAP",
      "LEAVE_TRAINING_CONFLICT",
    ].includes(blocker.code)
  ) {
    return "Team lead or resource administrator must resolve the schedule or capacity conflict.";
  }
  if (blocker.code === "MANDATORY_COMPETENCY_GAP")
    return "Audit supervisor must assign a qualified specialist.";
  if (blocker.code === "REQUIRED_ROLE_MISSING")
    return "Audit supervisor must complete the required role coverage.";
  return "Audit supervisor must resolve this readiness item.";
}

function currentDeclarations(declarations) {
  return (declarations ?? []).filter((item) => item.isCurrentRevision);
}

export default function AemsTeamSafeguardsPanel({
  engagementId,
  overview,
  loading,
  onRefresh,
}) {
  const { user } = useAuth();
  const toast = useToast();
  const [declarationTarget, setDeclarationTarget] = useState(null);
  const [declarationForm, setDeclarationForm] = useState(emptyDeclaration);
  const [reviewTarget, setReviewTarget] = useState(null);
  const [viewTarget, setViewTarget] = useState(null);
  const [reviewDecision, setReviewDecision] = useState("ACCEPT");
  const [reviewNotes, setReviewNotes] = useState("");
  const [assessmentOpen, setAssessmentOpen] = useState(false);
  const [assessmentComment, setAssessmentComment] = useState("");
  const [saving, setSaving] = useState(false);

  const canDeclare = hasPermission(user, "aems.team.safeguard_declare");
  const canReview = hasPermission(user, "aems.team.safeguard_review");
  const canApprove = hasPermission(user, "aems.team.safeguard_approve");
  const canSubmitOnBehalf = hasPermission(
    user,
    "aems.team.safeguard_submit_on_behalf",
  );
  const canReviewOwn = hasPermission(user, "aems.review.own_submission");
  const canDeclareForMember = (member) =>
    canDeclare &&
    (canSubmitOnBehalf || Number(member?.user?.id) === Number(user?.id));
  const canReviewDeclaration = (declaration) =>
    canReview &&
    declaration?.status === "SUBMITTED" &&
    (canReviewOwn ||
      (Number(declaration?.userId) !== Number(user?.id) &&
        Number(declaration?.submittedBy) !== Number(user?.id)));
  const declarations = useMemo(
    () => currentDeclarations(overview?.declarations),
    [overview],
  );
  const pendingAssessment = overview?.assessments?.find(
    (assessment) =>
      assessment.isCurrentRevision && assessment.status === "PENDING",
  );
  const currentAssessment = overview?.assessments?.find(
    (assessment) => assessment.isCurrentRevision,
  );

  function openDeclaration(member, existing) {
    const existingDeclaration = existing ?? null;
    setDeclarationTarget({ member, existing: existingDeclaration });
    setDeclarationForm({
      ...emptyDeclaration,
      declarationType: existingDeclaration?.declarationType ?? "OBJECTIVITY",
      outcome: existingDeclaration?.outcome ?? "CLEAR",
      statement: existingDeclaration?.statement ?? "",
      mitigationPlan: existingDeclaration?.mitigationPlan ?? "",
      evidenceDocumentVersionId:
        existingDeclaration?.evidenceDocumentVersionId ?? "",
      revisionReason: existingDeclaration?.status === "ACCEPTED" ? "" : "",
    });
  }

  async function saveDeclaration() {
    if (!declarationTarget) return;
    setSaving(true);
    try {
      await aemsTeamSafeguardApi.submitDeclaration(
        engagementId,
        declarationTarget.member.teamMemberId,
        {
          ...declarationForm,
          evidenceDocumentVersionId: declarationForm.evidenceDocumentVersionId
            ? Number(declarationForm.evidenceDocumentVersionId)
            : null,
          mitigationPlan: declarationForm.mitigationPlan || null,
          revisionReason: declarationForm.revisionReason || null,
        },
      );
      toast.success("Safeguard declaration submitted for authorized review.");
      setDeclarationTarget(null);
      await onRefresh();
    } catch (error) {
      if (error instanceof ApiError && error.errors)
        toast.error(Object.values(error.errors).flat().join(" "));
      else toast.error(error.message);
    } finally {
      setSaving(false);
    }
  }

  async function reviewDeclaration() {
    if (!reviewTarget) return;
    setSaving(true);
    try {
      await aemsTeamSafeguardApi.reviewDeclaration(
        engagementId,
        reviewTarget.member.teamMemberId,
        reviewTarget.declaration.id,
        { decision: reviewDecision, reviewNotes: reviewNotes || null },
      );
      toast.success(
        reviewDecision === "ACCEPT"
          ? "Declaration accepted."
          : "Declaration returned for revision.",
      );
      setReviewTarget(null);
      setReviewNotes("");
      await onRefresh();
    } catch (error) {
      if (error instanceof ApiError && error.errors)
        toast.error(Object.values(error.errors).flat().join(" "));
      else toast.error(error.message);
    } finally {
      setSaving(false);
    }
  }

  async function assess() {
    setSaving(true);
    try {
      await aemsTeamSafeguardApi.assess(engagementId);
      toast.success(
        "Immutable safeguard assessment recorded and queued for approval.",
      );
      setAssessmentOpen(false);
      await onRefresh();
    } catch (error) {
      toast.error(error.message);
    } finally {
      setSaving(false);
    }
  }

  async function approve() {
    setSaving(true);
    try {
      await aemsTeamSafeguardApi.approve(engagementId, {
        comment: assessmentComment || null,
      });
      toast.success("Team safeguards approved; the decision is immutable.");
      setAssessmentOpen(false);
      setAssessmentComment("");
      await onRefresh();
    } catch (error) {
      toast.error(error.message);
    } finally {
      setSaving(false);
    }
  }

  if (!overview) return null;

  return (
    <section
      className="mt-5 space-y-5"
      data-testid="aems-team-safeguards-panel"
    >
      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <SummaryCard
          icon={ShieldCheck}
          label="Readiness"
          value={overview.approvalReady ? "Ready" : "Blocked"}
          tone={overview.approvalReady ? "emerald" : "red"}
        />
        <SummaryCard
          icon={ClipboardCheck}
          label="Declarations accepted"
          value={`${declarations.filter((item) => item.status === "ACCEPTED").length}/${(overview.team ?? []).length * declarationTypes.length}`}
          tone="sky"
        />
        <SummaryCard
          icon={Clock3}
          label="Planned person-days"
          value={number(overview.reconciliation?.planned?.team)}
          tone="amber"
        />
        <SummaryCard
          icon={RefreshCw}
          label="Actual person-days"
          value={number(overview.reconciliation?.actual?.team)}
          tone="slate"
        />
      </div>

      <section className="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div className="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-4 py-4 sm:px-5">
          <div>
            <h2 className="flex items-center gap-2 font-bold text-slate-800">
              <UsersRound size={18} className="text-sky-700" /> Team and
              resource safeguards
            </h2>
            <p className="mt-1 text-xs text-slate-500">
              Assignment readiness, competency coverage, independence, provider
              status, and approval ownership.
            </p>
          </div>
          {loading && (
            <span className="text-xs font-semibold text-slate-500">
              Refreshing...
            </span>
          )}
        </div>
        <div className="grid gap-4 p-4 sm:grid-cols-2 sm:p-5">
          <div className="rounded-lg border border-slate-200 bg-slate-50 p-4">
            <p className="text-xs font-bold uppercase tracking-wide text-slate-500">
              Resource provider
            </p>
            <div className="mt-2 flex flex-wrap items-center gap-2">
              <StatusBadge
                tone={overview.provider?.authoritative ? "success" : "warning"}
              >
                {label(overview.provider?.mode)}
              </StatusBadge>
              <span className="text-sm font-semibold text-slate-800">
                {overview.provider?.provider ??
                  overview.provider?.activeProvider ??
                  "Unknown provider"}
              </span>
            </div>
            <p className="mt-2 text-xs leading-5 text-slate-600">
              ARMIS is the sole operational resource provider for assignment
              capacity, competencies, availability, and person-days. IAP
              resource ledgers are not used for readiness.
            </p>
            <p className="mt-2 text-xs text-slate-500">
              Readiness uses current approved ARMIS records; a historical
              provider reconciliation is not required.
            </p>
          </div>
          <div className="rounded-lg border border-slate-200 bg-slate-50 p-4">
            <p className="text-xs font-bold uppercase tracking-wide text-slate-500">
              Person-day alignment
            </p>
            <div className="mt-3 grid grid-cols-3 gap-2 text-center text-xs">
              <div>
                <strong className="block text-lg text-slate-800">
                  {number(overview.reconciliation?.planned?.team)}
                </strong>
                <span className="text-slate-500">Team plan</span>
              </div>
              <div>
                <strong className="block text-lg text-slate-800">
                  {number(overview.reconciliation?.planned?.engagement)}
                </strong>
                <span className="text-slate-500">Engagement plan</span>
              </div>
              <div>
                <strong className="block text-lg text-slate-800">
                  {number(overview.reconciliation?.planned?.variance)}
                </strong>
                <span className="text-slate-500">Plan variance</span>
              </div>
            </div>
            <div className="mt-3 flex items-center gap-2 text-xs">
              {overview.reconciliation?.planned?.reconciled ? (
                <CheckCircle2 size={15} className="text-emerald-600" />
              ) : (
                <AlertTriangle size={15} className="text-red-600" />
              )}
              <span className="font-semibold text-slate-700">
                Planned effort{" "}
                {overview.reconciliation?.planned?.reconciled
                  ? "aligns"
                  : "does not align"}
                .
              </span>
            </div>
            <p className="mt-2 text-xs text-slate-500">
              Actuals: team {number(overview.reconciliation?.actual?.team)} ·
              ARMIS {number(overview.reconciliation?.actual?.provider)} ·
              engagement {number(overview.reconciliation?.actual?.engagement)}
            </p>
          </div>
        </div>
      </section>

      <section className="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <div className="border-b border-slate-200 px-4 py-4 sm:px-5">
          <h2 className="font-bold text-slate-800">
            Competency, availability, and workload matrix
          </h2>
          <p className="mt-1 text-xs text-slate-500">
            Every active assignment shows the evidence used by the readiness
            decision.
          </p>
        </div>
        <table className="min-w-[980px] w-full text-left text-xs">
          <thead className="bg-slate-50 text-[11px] uppercase tracking-wide text-slate-500">
            <tr>
              <th className="px-4 py-3">Member / role</th>
              <th className="px-4 py-3">Competencies & certifications</th>
              <th className="px-4 py-3">Planned / actual</th>
              <th className="px-4 py-3">Capacity</th>
              <th className="px-4 py-3">Availability & conflicts</th>
              <th className="px-4 py-3">Declarations</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {(overview.team ?? []).map((member) => {
              const memberDeclarations = declarations.filter(
                (item) => item.engagementTeamId === member.teamMemberId,
              );
              const maySubmit = canDeclareForMember(member);

              return (
                <tr key={member.teamMemberId} className="align-top">
                  <td className="px-4 py-4">
                    <strong className="block text-sm text-slate-800">
                      {member.user?.name ?? "Unknown"}
                    </strong>
                    <span className="text-slate-500">
                      {member.user?.employeeId} · {label(member.role)}
                    </span>
                  </td>
                  <td className="max-w-64 px-4 py-4">
                    <div className="flex flex-wrap gap-1">
                      {(member.competencies ?? []).map((skill) => (
                        <span
                          className="rounded-full bg-sky-50 px-2 py-1 text-[11px] text-sky-700"
                          key={`${member.teamMemberId}-${skill.id ?? skill.code}`}
                        >
                          {skill.label ?? skill.name}{" "}
                          {skill.proficiencyLevel
                            ? `· ${skill.proficiencyLevel}`
                            : ""}
                        </span>
                      ))}
                      {!(member.competencies ?? []).length && (
                        <span className="text-red-600">
                          No competency record
                        </span>
                      )}
                    </div>
                    {(member.certifications ?? []).length > 0 && (
                      <p className="mt-2 text-[11px] text-emerald-700">
                        Certifications:{" "}
                        {member.certifications
                          .map((item) => item.label ?? item.name)
                          .join(", ")}
                      </p>
                    )}
                  </td>
                  <td className="whitespace-nowrap px-4 py-4">
                    <strong>{number(member.plannedPersonDays)} days</strong>
                    <span className="block text-slate-500">
                      {number(member.actualPersonDays)} actual
                    </span>
                  </td>
                  <td className="whitespace-nowrap px-4 py-4">
                    <StatusBadge
                      tone={member.capacity?.met ? "success" : "danger"}
                    >
                      {member.capacity?.met
                        ? "Within capacity"
                        : "Over capacity"}
                    </StatusBadge>
                    <span className="mt-1 block text-slate-500">
                      {number(member.capacity?.allocated)} /{" "}
                      {number(member.capacity?.available)} days
                    </span>
                  </td>
                  <td className="max-w-56 px-4 py-4">
                    {member.overlap && (
                      <p className="font-semibold text-red-700">
                        Overlap: {member.overlap.engagementCode}
                      </p>
                    )}
                    {(member.unavailability ?? []).map((item) => (
                      <p
                        className="mt-1 text-red-700"
                        key={`${item.startDate}-${item.endDate}`}
                      >
                        {item.typeLabel ?? item.title}: {item.startDate}–
                        {item.endDate}
                      </p>
                    ))}
                    {!member.overlap &&
                      !(member.unavailability ?? []).length && (
                        <span className="text-emerald-700">
                          No conflicts reported
                        </span>
                      )}
                  </td>
                  <td className="min-w-56 px-4 py-4">
                    <div className="space-y-1">
                      {declarationTypes.map(([type, typeLabel]) => {
                        const declaration = memberDeclarations.find(
                          (item) => item.declarationType === type,
                        );

                        return (
                          <div
                            className="flex items-center justify-between gap-2"
                            key={type}
                          >
                            <span className="text-slate-600">{typeLabel}</span>
                            {declaration ? (
                              <button
                                className="inline-flex items-center gap-1 text-left"
                                onClick={() => {
                                  if (canReviewDeclaration(declaration)) {
                                    setReviewTarget({ member, declaration });
                                  } else if (
                                    maySubmit &&
                                    ["ACCEPTED", "RETURNED"].includes(
                                      declaration.status,
                                    )
                                  ) {
                                    openDeclaration(member, declaration);
                                  } else {
                                    setViewTarget({ member, declaration });
                                  }
                                }}
                                type="button"
                                title={
                                  canReviewDeclaration(declaration)
                                    ? "Review declaration"
                                    : "View declaration"
                                }
                              >
                                {!canReviewDeclaration(declaration) && (
                                  <Eye size={13} className="text-slate-400" />
                                )}
                                <StatusBadge
                                  tone={toneForStatus(declaration.status)}
                                >
                                  {label(declaration.status)} · v
                                  {declaration.versionNumber}
                                </StatusBadge>
                              </button>
                            ) : (
                              <StatusBadge tone="danger">Missing</StatusBadge>
                            )}
                          </div>
                        );
                      })}
                    </div>
                    <div className="mt-3 flex flex-wrap items-center gap-2">
                      {maySubmit && (
                        <button
                          className="inline-flex items-center gap-1 rounded-lg border border-sky-200 px-2.5 py-1.5 text-[11px] font-bold text-sky-700 hover:bg-sky-50"
                          onClick={() => openDeclaration(member)}
                          type="button"
                        >
                          <UserCheck size={13} />
                          {canSubmitOnBehalf &&
                          Number(member?.user?.id) !== Number(user?.id)
                            ? "Submit for member"
                            : "Submit your declaration"}
                        </button>
                      )}
                      {canDeclare && !maySubmit && (
                        <span className="text-[11px] text-slate-500">
                          Member submits their own declaration
                        </span>
                      )}
                      {canReview &&
                        memberDeclarations.some((item) =>
                          canReviewDeclaration(item),
                        ) && (
                          <span className="text-[11px] font-semibold text-amber-700">
                            Review required
                          </span>
                        )}
                    </div>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
        {!(overview.team ?? []).length && (
          <p className="p-5 text-sm text-slate-500">
            Assign at least one team member to evaluate safeguards.
          </p>
        )}
      </section>

      <section className="grid gap-5 xl:grid-cols-[1.2fr_0.8fr]">
        <div
          className={`rounded-xl border p-4 shadow-sm sm:p-5 ${overview.approvalReady ? "border-emerald-200 bg-emerald-50" : "border-red-200 bg-red-50"}`}
        >
          <div className="flex items-start gap-3">
            <span
              className={`grid h-10 w-10 shrink-0 place-items-center rounded-lg ${overview.approvalReady ? "bg-emerald-100 text-emerald-700" : "bg-red-100 text-red-700"}`}
            >
              {overview.approvalReady ? (
                <CheckCircle2 size={21} />
              ) : (
                <ShieldAlert size={21} />
              )}
            </span>
            <div>
              <h2 className="font-bold text-slate-800">
                {overview.approvalReady
                  ? "Assignment is ready for independent approval"
                  : "Assignment is blocked"}
              </h2>
              <p className="mt-1 text-xs leading-5 text-slate-600">
                {overview.approvalReady
                  ? "All mandatory competency, capacity, provider, and independence checks are met."
                  : "Resolve each blocker below. The owner is shown so the team knows who must act next."}
              </p>
            </div>
          </div>
          {!!overview.blockers?.length && (
            <ul className="mt-4 space-y-2">
              {overview.blockers.map((blocker, index) => (
                <li
                  className="rounded-lg border border-red-200 bg-white/80 p-3 text-xs"
                  key={`${blocker.code}-${index}`}
                >
                  <strong className="block text-red-800">
                    {blocker.message}
                  </strong>
                  <span className="mt-1 block text-slate-600">
                    Resolver: {resolverFor(blocker)}
                  </span>
                </li>
              ))}
            </ul>
          )}
          {!!overview.warnings?.length && (
            <div className="mt-4 space-y-2">
              {overview.warnings.map((warning, index) => (
                <p
                  className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800"
                  key={`${warning.code}-${index}`}
                >
                  {warning.message}
                </p>
              ))}
            </div>
          )}
          {!!overview.checks && (
            <div className="mt-4 grid gap-2 sm:grid-cols-2">
              {Object.entries(overview.checks).map(([key, check]) => (
                <div
                  className="flex items-start gap-2 rounded-lg bg-white/70 px-3 py-2 text-xs"
                  key={key}
                >
                  {check.met ? (
                    <CheckCircle2
                      size={15}
                      className="mt-0.5 shrink-0 text-emerald-600"
                    />
                  ) : (
                    <AlertTriangle
                      size={15}
                      className="mt-0.5 shrink-0 text-red-600"
                    />
                  )}
                  <span
                    className={
                      check.met
                        ? "text-slate-700"
                        : "font-semibold text-red-700"
                    }
                  >
                    {check.label}
                  </span>
                </div>
              ))}
            </div>
          )}
        </div>
        <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
          <div className="flex items-start justify-between gap-3">
            <div>
              <h2 className="font-bold text-slate-800">Approval panel</h2>
              <p className="mt-1 text-xs text-slate-500">
                Assessments are immutable; assessor and approver must be
                different people.
              </p>
            </div>
            <FileCheck2 size={20} className="text-sky-700" />
          </div>
          <div className="mt-4 rounded-lg bg-slate-50 p-3 text-xs">
            <p>
              Current status:{" "}
              <StatusBadge tone={toneForStatus(currentAssessment?.status)}>
                {label(currentAssessment?.status ?? "NOT ASSESSED")}
              </StatusBadge>
            </p>
            {currentAssessment && (
              <>
                <p className="mt-2 text-slate-500">
                  Version {currentAssessment.versionNumber} · assessed{" "}
                  {dateTime(currentAssessment.assessedAt)}
                </p>
                <p className="mt-1 text-slate-500">
                  Assessor: {currentAssessment.assessedBy ?? "Recorded user"}
                  {currentAssessment.approvedBy
                    ? ` · approver: ${currentAssessment.approvedBy}`
                    : ""}
                </p>
              </>
            )}
          </div>
          <div className="mt-4 flex flex-wrap gap-2">
            {canReview && !pendingAssessment && (
              <button
                className="inline-flex items-center gap-2 rounded-lg bg-sky-700 px-3 py-2 text-xs font-bold text-white hover:bg-sky-800 disabled:opacity-60"
                disabled={saving}
                onClick={() => setAssessmentOpen(true)}
                type="button"
              >
                <ClipboardCheck size={15} /> Record assessment
              </button>
            )}
            {canApprove && pendingAssessment && (
              <button
                className="inline-flex items-center gap-2 rounded-lg bg-emerald-700 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-800 disabled:opacity-60"
                disabled={saving || !overview.approvalReady}
                onClick={() => setAssessmentOpen(true)}
                type="button"
              >
                <ShieldCheck size={15} /> Approve safeguards
              </button>
            )}
            {!canReview && !canApprove && (
              <span className="text-xs text-slate-500">
                No approval action is assigned to your role.
              </span>
            )}
          </div>
          {overview.assessments?.length > 0 && (
            <div className="mt-4 border-t border-slate-200 pt-3">
              <p className="text-xs font-bold uppercase tracking-wide text-slate-500">
                Version history
              </p>
              <div className="mt-2 space-y-2">
                {overview.assessments.slice(0, 5).map((assessment) => (
                  <div
                    className="flex items-center justify-between gap-2 text-xs"
                    key={assessment.id}
                  >
                    <span className="text-slate-600">
                      v{assessment.versionNumber} ·{" "}
                      {dateTime(assessment.assessedAt)}
                    </span>
                    <StatusBadge tone={toneForStatus(assessment.status)}>
                      {label(assessment.status)}
                    </StatusBadge>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>
      </section>

      <Modal
        open={Boolean(declarationTarget)}
        onClose={() => !saving && setDeclarationTarget(null)}
        size="lg"
        title={`${declarationTarget?.existing ? "Revise" : "Submit"} safeguard declaration`}
        description="The declaration is versioned. Assigned members submit their own declarations; CIAS Management may prepare one for a member and complete the authorized review."
      >
        <div className="grid gap-4 sm:grid-cols-2">
          <label className="text-sm font-semibold text-slate-700">
            Declaration type
            <select
              className="mt-1.5 h-11 w-full rounded-lg border border-slate-300 bg-white px-3"
              value={declarationForm.declarationType}
              onChange={(event) =>
                setDeclarationForm((current) => ({
                  ...current,
                  declarationType: event.target.value,
                }))
              }
            >
              {declarationTypes.map(([value, text]) => (
                <option key={value} value={value}>
                  {text}
                </option>
              ))}
            </select>
          </label>
          <label className="text-sm font-semibold text-slate-700">
            Outcome
            <select
              className="mt-1.5 h-11 w-full rounded-lg border border-slate-300 bg-white px-3"
              value={declarationForm.outcome}
              onChange={(event) =>
                setDeclarationForm((current) => ({
                  ...current,
                  outcome: event.target.value,
                }))
              }
            >
              <option value="CLEAR">Clear</option>
              <option value="DISCLOSED">Disclosed</option>
              <option value="CONFLICT">Conflict</option>
            </select>
          </label>
          <label className="sm:col-span-2 text-sm font-semibold text-slate-700">
            Statement
            <textarea
              className="mt-1.5 min-h-28 w-full rounded-lg border border-slate-300 p-3 font-normal"
              value={declarationForm.statement}
              onChange={(event) =>
                setDeclarationForm((current) => ({
                  ...current,
                  statement: event.target.value,
                }))
              }
              placeholder="Describe the basis for this declaration..."
            />
          </label>
          <label className="sm:col-span-2 text-sm font-semibold text-slate-700">
            Mitigation plan (required for disclosed matters)
            <textarea
              className="mt-1.5 min-h-24 w-full rounded-lg border border-slate-300 p-3 font-normal"
              value={declarationForm.mitigationPlan}
              onChange={(event) =>
                setDeclarationForm((current) => ({
                  ...current,
                  mitigationPlan: event.target.value,
                }))
              }
            />
          </label>
          <label className="text-sm font-semibold text-slate-700">
            Core document version ID (optional)
            <input
              className="mt-1.5 h-11 w-full rounded-lg border border-slate-300 px-3 font-normal"
              type="number"
              value={declarationForm.evidenceDocumentVersionId}
              onChange={(event) =>
                setDeclarationForm((current) => ({
                  ...current,
                  evidenceDocumentVersionId: event.target.value,
                }))
              }
            />
          </label>
          {declarationTarget?.existing?.status === "ACCEPTED" && (
            <label className="text-sm font-semibold text-slate-700">
              Revision reason
              <input
                className="mt-1.5 h-11 w-full rounded-lg border border-slate-300 px-3 font-normal"
                value={declarationForm.revisionReason}
                onChange={(event) =>
                  setDeclarationForm((current) => ({
                    ...current,
                    revisionReason: event.target.value,
                  }))
                }
              />
            </label>
          )}
        </div>
        <div className="mt-5 flex justify-end gap-2">
          <button
            className="h-10 rounded-lg border border-slate-300 px-4 text-sm font-bold text-slate-700"
            disabled={saving}
            onClick={() => setDeclarationTarget(null)}
            type="button"
          >
            Cancel
          </button>
          <button
            className="h-10 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white disabled:opacity-60"
            disabled={saving || declarationForm.statement.trim().length < 10}
            onClick={saveDeclaration}
            type="button"
          >
            {saving ? "Submitting..." : "Submit declaration"}
          </button>
        </div>
      </Modal>

      <Modal
        open={Boolean(viewTarget)}
        onClose={() => setViewTarget(null)}
        size="md"
        title="Safeguard declaration"
        description="Submitted declarations are view-only for assigned resources. Only an authorized reviewer can accept or return them."
      >
        <div className="space-y-3 rounded-lg bg-slate-50 p-4 text-sm">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <strong>
              {viewTarget?.member?.user?.name ?? "Assigned resource"}
            </strong>
            <StatusBadge tone={toneForStatus(viewTarget?.declaration?.status)}>
              {label(viewTarget?.declaration?.status)} · v
              {viewTarget?.declaration?.versionNumber}
            </StatusBadge>
          </div>
          <p className="text-xs text-slate-600">
            {label(viewTarget?.declaration?.declarationType)} ·{" "}
            {label(viewTarget?.declaration?.outcome)}
          </p>
          <p className="whitespace-pre-wrap text-xs leading-5 text-slate-700">
            {viewTarget?.declaration?.statement}
          </p>
          {viewTarget?.declaration?.mitigationPlan && (
            <p className="text-xs text-slate-600">
              <strong>Mitigation plan:</strong>{" "}
              {viewTarget.declaration.mitigationPlan}
            </p>
          )}
          <p className="text-[11px] text-slate-500">
            Submitted {dateTime(viewTarget?.declaration?.submittedAt)} ·
            reviewed {dateTime(viewTarget?.declaration?.reviewedAt)}
          </p>
          {viewTarget?.declaration?.reviewNotes && (
            <p className="text-xs text-slate-600">
              <strong>Review notes:</strong>{" "}
              {viewTarget.declaration.reviewNotes}
            </p>
          )}
        </div>
        <div className="mt-5 flex justify-end">
          <button
            className="h-10 rounded-lg border border-slate-300 px-4 text-sm font-bold text-slate-700"
            onClick={() => setViewTarget(null)}
            type="button"
          >
            Close
          </button>
        </div>
      </Modal>

      <Modal
        open={Boolean(reviewTarget)}
        onClose={() => !saving && setReviewTarget(null)}
        size="md"
        title="Review safeguard declaration"
        description={
          canReviewOwn
            ? "Your assigned self-review permission allows this controlled review, including your own submission. Every decision remains versioned and audited."
            : "Review independently. Returning a declaration requires a clear explanation for the assigned resource."
        }
      >
        <div className="rounded-lg bg-slate-50 p-3 text-sm">
          <strong>{reviewTarget?.member.user?.name}</strong>
          <p className="mt-1 text-xs text-slate-600">
            {label(reviewTarget?.declaration.declarationType)} ·{" "}
            {label(reviewTarget?.declaration.outcome)}
          </p>
          <p className="mt-2 whitespace-pre-wrap text-xs leading-5 text-slate-700">
            {reviewTarget?.declaration.statement}
          </p>
        </div>
        <label className="mt-4 block text-sm font-semibold text-slate-700">
          Decision
          <select
            className="mt-1.5 h-11 w-full rounded-lg border border-slate-300 bg-white px-3"
            value={reviewDecision}
            onChange={(event) => setReviewDecision(event.target.value)}
          >
            <option value="ACCEPT">Accept</option>
            <option value="RETURN">Return for revision</option>
          </select>
        </label>
        <label className="mt-4 block text-sm font-semibold text-slate-700">
          Review notes
          <textarea
            className="mt-1.5 min-h-24 w-full rounded-lg border border-slate-300 p-3 font-normal"
            value={reviewNotes}
            onChange={(event) => setReviewNotes(event.target.value)}
            placeholder={
              reviewDecision === "RETURN"
                ? "Explain what must be corrected..."
                : "Optional review notes..."
            }
          />
        </label>
        <div className="mt-5 flex justify-end gap-2">
          <button
            className="h-10 rounded-lg border border-slate-300 px-4 text-sm font-bold text-slate-700"
            disabled={saving}
            onClick={() => setReviewTarget(null)}
            type="button"
          >
            Cancel
          </button>
          <button
            className="h-10 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white disabled:opacity-60"
            disabled={
              saving ||
              (reviewDecision === "RETURN" && reviewNotes.trim().length < 5)
            }
            onClick={reviewDeclaration}
            type="button"
          >
            {saving ? "Saving..." : "Save review"}
          </button>
        </div>
      </Modal>

      <Modal
        open={assessmentOpen}
        onClose={() => !saving && setAssessmentOpen(false)}
        size="md"
        title={
          pendingAssessment
            ? "Approve team safeguards"
            : "Record team safeguard assessment"
        }
        description="This action creates an immutable version and records the actor for the separation-of-duties audit trail."
      >
        {pendingAssessment ? (
          <div className="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">
            {overview.approvalReady
              ? "All current checks are met. You may record the final independent approval."
              : "Approval is disabled while readiness blockers remain."}
          </div>
        ) : (
          <div className="rounded-lg border border-sky-200 bg-sky-50 p-3 text-sm text-sky-800">
            The current provider, workload, competency, and declaration snapshot
            will be recorded as a pending assessment.
          </div>
        )}
        <label className="mt-4 block text-sm font-semibold text-slate-700">
          Decision comment (optional)
          <textarea
            className="mt-1.5 min-h-24 w-full rounded-lg border border-slate-300 p-3 font-normal"
            value={assessmentComment}
            onChange={(event) => setAssessmentComment(event.target.value)}
          />
        </label>
        <div className="mt-5 flex justify-end gap-2">
          <button
            className="h-10 rounded-lg border border-slate-300 px-4 text-sm font-bold text-slate-700"
            disabled={saving}
            onClick={() => setAssessmentOpen(false)}
            type="button"
          >
            Cancel
          </button>
          <button
            className={`h-10 rounded-lg px-4 text-sm font-bold text-white disabled:opacity-60 ${pendingAssessment ? "bg-emerald-700" : "bg-sky-700"}`}
            disabled={saving || (pendingAssessment && !overview.approvalReady)}
            onClick={pendingAssessment ? approve : assess}
            type="button"
          >
            {saving
              ? "Saving..."
              : pendingAssessment
                ? "Approve safeguards"
                : "Record assessment"}
          </button>
        </div>
      </Modal>
    </section>
  );
}
