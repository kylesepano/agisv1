import {
  ArrowRight,
  CheckCircle2,
  Shield,
  Users,
  XCircle,
} from "lucide-react";
import type { TeamAssignmentFormState } from "../team-assignment-types";

type TeamSystemControlsCardProps = {
  formState: TeamAssignmentFormState;
  isLocked?: boolean;
  aeoStatus?: string;
};

export function TeamSystemControlsCard({
  formState,
  isLocked = false,
  aeoStatus = "Draft",
}: TeamSystemControlsCardProps) {
  const leadCount = formState.team.filter((m) => m.role === "Lead Auditor").length;

  const allAuditorsSelected =
    formState.team.length >= 1 && formState.team.every((m) => Boolean(m.userId));

  const allCompetenciesDocumented =
    formState.team.length > 0 &&
    formState.team.every((m) => (m.competencySummary || "").trim().length >= 20);

  const allAvailabilityConfirmed =
    formState.team.length > 0 && formState.team.every((m) => m.isAvailable);

  const allDatesValid =
    formState.team.length > 0 &&
    formState.team.every(
      (m) =>
        m.assignmentStartDate &&
        m.assignmentEndDate &&
        (!formState.periodStart || m.assignmentStartDate >= formState.periodStart) &&
        (!formState.periodEnd || m.assignmentEndDate <= formState.periodEnd) &&
        m.assignmentStartDate <= m.assignmentEndDate,
    );

  const allConflictsSafeguarded =
    formState.team.length > 0 &&
    formState.team.every(
      (m) =>
        (!m.hasConflictOfInterest ||
          ((m.disclosedImpairments || "").trim().length >= 10 &&
            (m.safeguardsDocumented || "").trim().length >= 10)) &&
        (m.safeguardsDocumented || "").trim().length >= 5,
    );

  const allAccessConfirmed =
    formState.team.length > 0 &&
    formState.team.every((m) => m.hasSystemAccessConfirmed);

  const isSufficiencyValid =
    formState.isCollectiveSufficiencyConfirmed &&
    (formState.collectiveSufficiencyJustification || "").trim().length >= 20;

  const checks = [
    {
      label: "Auditor selected for each roster row",
      valid: allAuditorsSelected,
      detail: allAuditorsSelected ? `${formState.team.length} assigned` : "Incomplete",
    },
    {
      label: "Exactly one Lead Auditor designated",
      valid: leadCount === 1,
      detail: leadCount === 1 ? "Lead designated" : leadCount === 0 ? "Missing Lead" : `${leadCount} Leads`,
    },
    {
      label: "Competency documented (min. 20 chars)",
      valid: allCompetenciesDocumented,
      detail: allCompetenciesDocumented ? "Competencies verified" : "Incomplete",
    },
    {
      label: "Availability confirmed for all members",
      valid: allAvailabilityConfirmed,
      detail: allAvailabilityConfirmed ? "Capacity confirmed" : "Pending",
    },
    {
      label: "Assignment dates within period window",
      valid: allDatesValid,
      detail: allDatesValid ? "Window aligned" : "Date mismatch",
    },
    {
      label: "Safeguards noted for all declarations",
      valid: allConflictsSafeguarded,
      detail: allConflictsSafeguarded ? "Objectivity protected" : "Safeguards required",
    },
    {
      label: "Authority & access confirmed for all",
      valid: allAccessConfirmed,
      detail: allAccessConfirmed ? "Access ready" : "Pending",
    },
    {
      label: "Team sufficiency affirmed & justified",
      valid: isSufficiencyValid,
      detail: isSufficiencyValid ? "Sufficiency confirmed" : "Pending",
    },
  ];

  const passedCount = checks.filter((c) => c.valid).length;

  return (
    <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-3.5">
      <div className="flex items-center justify-between border-b border-slate-100 pb-2">
        <div className="flex items-center gap-1.5 font-bold text-slate-800 text-xs">
          <Shield size={14} className="text-blue-600" />
          <span>System Controls & Governance (§9.1)</span>
        </div>
        <span className="rounded bg-blue-50 px-1.5 py-0.2 text-[10px] font-mono font-bold text-blue-700">
          AEM-FR-008
        </span>
      </div>

      {/* Engagement Identity & Phase Status Box */}
      <div className="rounded-lg border border-slate-100 bg-slate-50/70 p-2.5 space-y-1.5 text-xs">
        <div className="flex items-center justify-between">
          <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
            Engagement No.
          </span>
          <span className="font-mono font-bold text-blue-700 text-xs">
            {formState.engagementNumber}
          </span>
        </div>

        <div className="flex items-center justify-between">
          <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
            Lifecycle Phase
          </span>
          <span className="rounded bg-indigo-50 px-1.5 py-0.2 text-[10px] font-bold text-indigo-700 border border-indigo-200">
            Phase 1 of 10 • Initiation
          </span>
        </div>

        <div className="flex items-center justify-between">
          <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
            Admin Status
          </span>
          <span
            className={`rounded px-1.5 py-0.2 text-[10px] font-bold border ${
              isLocked
                ? "bg-emerald-50 text-emerald-700 border-emerald-200"
                : "bg-amber-50 text-amber-700 border-amber-200"
            }`}
          >
            {isLocked ? `Locked (${aeoStatus})` : "Draft"}
          </span>
        </div>
      </div>

      {/* Readiness Checklist */}
      <div className="space-y-2">
        <div className="flex items-center justify-between text-xs">
          <span className="font-bold text-slate-800 flex items-center gap-1">
            <Users size={12} className="text-blue-600" />
            <span>Team Readiness Checklist</span>
          </span>
          <span
            className={`font-mono text-[10px] font-bold px-1.5 py-0.2 rounded ${
              passedCount === checks.length
                ? "bg-emerald-50 text-emerald-700 border border-emerald-200"
                : "bg-amber-50 text-amber-700 border border-amber-200"
            }`}
          >
            {passedCount}/{checks.length} Complete
          </span>
        </div>

        <div className="space-y-1.5 divide-y divide-slate-100 text-xs">
          {checks.map((chk, idx) => (
            <div key={idx} className="flex items-start justify-between gap-2 pt-1.5">
              <div className="flex items-start gap-1.5 min-w-0">
                {chk.valid ? (
                  <CheckCircle2 size={13} className="text-emerald-600 shrink-0 mt-0.5" />
                ) : (
                  <XCircle size={13} className="text-slate-300 shrink-0 mt-0.5" />
                )}
                <span
                  className={`text-[11px] leading-tight ${
                    chk.valid ? "text-slate-700 font-medium" : "text-slate-400"
                  }`}
                >
                  {chk.label}
                </span>
              </div>
              <span className="text-[10px] text-slate-400 shrink-0 font-mono">
                {chk.detail}
              </span>
            </div>
          ))}
        </div>
      </div>

      {/* Next Step Callout Box */}
      <div className="rounded-lg border border-blue-200 bg-blue-50/50 p-2.5 space-y-1 text-xs">
        <div className="flex items-center gap-1 text-blue-900 font-bold">
          <ArrowRight size={12} className="text-blue-600 shrink-0" />
          <span>{isLocked ? "Phase 1 Baseline Locked" : "Next Step: SCR-214"}</span>
        </div>
        <p className="text-[11px] text-blue-800 leading-snug">
          {isLocked
            ? "This engagement's audit team roster is locked under the Issued AEO (MDS-200 Rule 31). To amend team members, initiate a controlled change via Phase 1 AEO revision."
            : "Validating the audit team satisfies the human-resource prerequisites to package the official Audit Engagement Order (AEO) snapshot in SCR-214."}
        </p>
      </div>
    </div>
  );
}
