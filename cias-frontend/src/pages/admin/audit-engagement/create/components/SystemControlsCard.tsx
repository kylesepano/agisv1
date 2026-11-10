import {
  CheckCircle2,
  Layers,
  Lock,
  Sparkles,
} from "lucide-react";
import type { CreateEngagementFormState } from "../create-engagement-types";

type SystemControlsCardProps = {
  formState: CreateEngagementFormState;
  isFormValid: boolean;
};

export function SystemControlsCard({
  formState,
  isFormValid,
}: SystemControlsCardProps) {
  // Compute SCR-211 specific completion items branching on Origin & Entry Path
  const isPlanned = formState.originType === "Planned";

  const checks = isPlanned
    ? [
        { label: "Origin & Entry Path", completed: Boolean(formState.originType) },
        { label: "IAP Source Link", completed: Boolean(formState.iapSourceId && formState.iapSourceId.trim().length > 0) },
        { label: "Date Received", completed: Boolean(formState.dateReceived && formState.dateReceived.trim().length > 0) },
        { label: "Engagement Title", completed: Boolean(formState.title.trim().length >= 3) },
        { label: "Audit Type", completed: Boolean(formState.auditType) },
        {
          label: "Period Covered",
          completed: Boolean(
            formState.startDate &&
              formState.endDate &&
              new Date(formState.startDate) <= new Date(formState.endDate),
          ),
        },
      ]
    : [
        { label: "Origin & Entry Path", completed: Boolean(formState.originType) },
        { label: "Authority Reference", completed: Boolean(formState.authorityReference.trim()) },
        { label: "Directing Authority", completed: Boolean(formState.directingAuthority.trim()) },
        { label: "Date Received", completed: Boolean(formState.dateReceived && formState.dateReceived.trim().length > 0) },
        { label: "Authorization Decision", completed: Boolean(formState.authorizationDecision && formState.authorizationDecision.trim().length > 0) },
        { label: "Engagement Title", completed: Boolean(formState.title.trim().length >= 3) },
        { label: "Audit Type", completed: Boolean(formState.auditType) },
        {
          label: "Period Covered",
          completed: Boolean(
            formState.startDate &&
              formState.endDate &&
              new Date(formState.startDate) <= new Date(formState.endDate),
          ),
        },
      ];

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
              <span className="text-emerald-600 font-semibold lowercase">generated</span>
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

          {/* Phase: Initiation */}
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

          {/* Initial Status */}
          <div className="rounded-md border border-slate-100 bg-slate-50/70 p-2 text-xs">
            <div className="flex items-center justify-between text-[10px] font-semibold text-slate-400 uppercase tracking-wider">
              <span>Initial Status</span>
              <span className="text-amber-600 font-semibold text-[10px]">MDS-200 §10.2</span>
            </div>
            <div className="mt-0.5 flex items-center gap-1.5">
              <span className="rounded bg-amber-50 px-1.5 py-0.5 text-[11px] font-bold text-amber-700 border border-amber-200">
                Draft
              </span>
              <span className="text-[10px] text-slate-400">Locked until Scope Approval</span>
            </div>
          </div>
        </div>
      </div>

      {/* Form Readiness & Quality Checklist (SCR-211 Activation Gate) */}
      <div className="rounded-lg border border-slate-200 bg-white p-3 shadow-sm space-y-2">
        <div className="flex items-center justify-between border-b border-slate-100 pb-1.5">
          <h4 className="text-[13px] font-bold text-slate-800">
            Activation Readiness
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
                isFormValid ? "bg-emerald-500" : "bg-blue-500"
              }`}
              style={{ width: `${progressPercent}%` }}
            />
          </div>
        </div>

        <ul className="space-y-1 text-[11px]">
          {checks.map((c) => (
            <li
              key={c.label}
              className="flex items-center justify-between text-slate-600"
            >
              <span className={c.completed ? "text-slate-700 font-medium" : "text-slate-400"}>
                {c.label}
              </span>
              <CheckCircle2
                size={13}
                className={c.completed ? "text-emerald-600" : "text-slate-200"}
              />
            </li>
          ))}
        </ul>
      </div>

      {/* Next Flow Preview */}
      <div className="rounded-lg border border-blue-100 bg-blue-50/50 p-2.5 text-xs">
        <div className="flex items-center gap-1.5 text-blue-900 font-bold text-[11px]">
          <Layers size={13} className="text-blue-600" />
          <span>Next Step: SCR-212</span>
        </div>
        <p className="mt-0.5 text-[10px] text-blue-700/90 leading-snug">
          Saving activates the draft engagement. <strong>Target Office, Audit Areas, Objectives, and Scope</strong> are formally captured in SCR-212.
        </p>
      </div>
    </div>
  );
}
