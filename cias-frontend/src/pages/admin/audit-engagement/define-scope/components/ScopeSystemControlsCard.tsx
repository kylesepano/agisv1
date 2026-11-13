import {
  CheckCircle2,
  Lock,
  Sparkles,
  Users,
} from "lucide-react";
import type { DefineScopeFormState, ScopeCheckItem } from "../define-scope-types";

type ScopeSystemControlsCardProps = {
  formState: DefineScopeFormState;
  isFormValid: boolean;
  isLocked?: boolean;
  aeoStatus?: string;
};

export function ScopeSystemControlsCard({
  formState,
  isFormValid,
  isLocked = false,
  aeoStatus = "Draft",
}: ScopeSystemControlsCardProps) {
  // Compute SCR-212 specific checklist items with sequential office dependency
  const hasOffice = Boolean(formState.office && formState.office.trim().length > 0);
  const checks: ScopeCheckItem[] = [
    { label: "Office Selected", completed: hasOffice },
    {
      label: "Audit Area(s) Selected",
      completed: Boolean(hasOffice && formState.selectedAuditAreaIds.length >= 1),
    },
    {
      label: "Objectives Documented",
      completed: Boolean(formState.objectives.trim().length >= 10),
    },
    {
      label: "Coverage Documented",
      completed: Boolean(
        hasOffice &&
          formState.selectedAuditAreaIds.length > 0 &&
          formState.selectedAuditAreaIds.some(
            (id) => Boolean(formState.coverageNotes[id]?.trim()),
          ),
      ),
    },
  ];

  // Conditional checklist item for source variance (Planned origin only)
  if (formState.originType === "Planned" && formState.isOfficeChangedFromIap) {
    checks.push({
      label: "Source Variance Justified",
      completed: Boolean(formState.sourceVarianceReason.trim().length >= 10),
      required: true,
    });
  }

  const completedCount = checks.filter((c) => c.completed).length;
  const totalCount = checks.length;
  const progressPercent = Math.round((completedCount / totalCount) * 100);

  return (
    <div className="space-y-2.5">
      {/* System Governance Card */}
      <div className="rounded-lg border border-slate-200 bg-white p-3 shadow-sm space-y-2.5">
        <div className="flex items-center justify-between border-b border-slate-100 pb-2">
          <div className="flex items-center gap-1.5">
            <Lock className="h-3.5 w-3.5 text-blue-600" />
            <h4 className="text-[13px] font-bold text-slate-800">
              System Controls
            </h4>
          </div>
          <span className="rounded bg-slate-100 px-1.5 py-0.2 text-[9px] font-bold text-slate-600">
            Auto
          </span>
        </div>

        <div className="space-y-2">
          {/* Engagement Code Preview */}
          <div className="rounded-md border border-slate-100 bg-slate-50/70 p-2 text-xs">
            <div className="flex items-center justify-between text-[10px] font-semibold text-slate-400 uppercase tracking-wider">
              <span>Engagement Number</span>
              <span className="text-emerald-600 font-semibold lowercase">read-only</span>
            </div>
            <div className="mt-0.5 flex items-center justify-between">
              <span className="font-mono text-[13px] font-bold text-blue-700">
                {formState.engagementNumber}
              </span>
              <span className="rounded bg-white px-1.5 py-0.2 text-[10px] font-semibold text-slate-600 border border-slate-200">
                {formState.originType}
              </span>
            </div>
          </div>

          {/* Phase: Initiation (Unchanged) */}
          <div className="rounded-md border border-slate-100 bg-slate-50/70 p-2 text-xs">
            <div className="flex items-center justify-between text-[10px] font-semibold text-slate-400 uppercase tracking-wider">
              <span>Lifecycle Phase</span>
              <span className="text-indigo-600 font-semibold text-[10px]">Phase 1 of 10</span>
            </div>
            <div className="mt-0.5 flex items-center gap-1.5">
              <span className="inline-flex items-center gap-1 rounded bg-indigo-50 px-1.5 py-0.5 text-[11px] font-semibold text-indigo-700 border border-indigo-200">
                <Sparkles className="h-3 w-3 text-indigo-500" />
                Engagement Initiation
              </span>
            </div>
          </div>

          {/* Status */}
          <div className="rounded-md border border-slate-100 bg-slate-50/70 p-2 text-xs">
            <div className="flex items-center justify-between text-[10px] font-semibold text-slate-400 uppercase tracking-wider">
              <span>Status</span>
              <span className={isLocked ? "text-emerald-600 font-semibold text-[10px]" : "text-amber-600 font-semibold text-[10px]"}>
                {isLocked ? "MDS-200 Rule 31" : "MDS-200 §10.2"}
              </span>
            </div>
            <div className="mt-0.5 flex items-center gap-1.5">
              {isLocked ? (
                <>
                  <span className="rounded bg-emerald-50 px-1.5 py-0.5 text-[11px] font-bold text-emerald-700 border border-emerald-200">
                    Locked ({aeoStatus})
                  </span>
                  <span className="text-[10px] text-slate-500 font-medium">Controlled AEO Issued</span>
                </>
              ) : (
                <>
                  <span className="rounded bg-amber-50 px-1.5 py-0.5 text-[11px] font-bold text-amber-700 border border-amber-200">
                    Draft
                  </span>
                  <span className="text-[10px] text-slate-400">Locked upon AEO Issuance</span>
                </>
              )}
            </div>
          </div>
        </div>
      </div>

      {/* Scope Definition Readiness Checklist */}
      <div className="rounded-lg border border-slate-200 bg-white p-3 shadow-sm space-y-2">
        <div className="flex items-center justify-between border-b border-slate-100 pb-1.5">
          <h4 className="text-[13px] font-bold text-slate-800">
            Scope Readiness
          </h4>
          <span className="text-[11px] font-bold text-slate-600 font-mono">
            {completedCount}/{totalCount}
          </span>
        </div>

        {/* Progress Bar */}
        <div>
          <div className="h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
            <div
              className={`h-full transition-all duration-300 ${
                isFormValid || isLocked ? "bg-emerald-500" : "bg-blue-500"
              }`}
              style={{ width: `${isLocked ? 100 : progressPercent}%` }}
            />
          </div>
        </div>

        <ul className="space-y-1 text-[11px]">
          {checks.map((c) => (
            <li
              key={c.label}
              className="flex items-center justify-between text-slate-600"
            >
              <span className={c.completed || isLocked ? "text-slate-700 font-medium" : "text-slate-400"}>
                {c.label}
                {c.required && <span className="text-amber-600 font-bold ml-1">*</span>}
              </span>
              <CheckCircle2
                size={13}
                className={c.completed || isLocked ? "text-emerald-600" : "text-slate-200"}
              />
            </li>
          ))}
        </ul>
      </div>

      {/* Next Flow Preview: SCR-213 */}
      <div className="rounded-lg border border-blue-100 bg-blue-50/50 p-2.5 text-xs">
        <div className="flex items-center gap-1.5 text-blue-900 font-bold text-[11px]">
          <Users size={13} className="text-blue-600" />
          <span>{isLocked ? "Phase 1 Baseline Locked" : "Next Transaction: SCR-213"}</span>
        </div>
        <p className="mt-0.5 text-[10px] text-blue-700/90 leading-snug">
          {isLocked
            ? "This engagement's scope parameters are locked under the Issued AEO. To amend scope, initiate a controlled change via Phase 1 AEO revision."
            : "Saving scope locks the audit boundary and advances directly to Assign Audit Team & Lead Auditor (SCR-213)."}
        </p>
      </div>
    </div>
  );
}
