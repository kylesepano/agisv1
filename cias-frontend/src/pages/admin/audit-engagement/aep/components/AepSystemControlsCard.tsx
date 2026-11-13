import {
  ArrowLeft,
  CheckCircle2,
  FileCheck2,
  Save,
  Send,
  Shield,
  XCircle,
} from "lucide-react";
import { Button } from "../../../../../components/ui/button";
import { navigateTo } from "../../../../../routes";
import type { AepFormState } from "../aep-types";

type AepSystemControlsCardProps = {
  formState: AepFormState;
  isReady: boolean;
  onSaveDraft: () => void;
  onSubmitReview: () => void;
};

export function AepSystemControlsCard({
  formState,
  isReady,
  onSaveDraft,
  onSubmitReview,
}: AepSystemControlsCardProps) {
  const checks = [
    {
      label: "Audit Objectives refined",
      valid: (formState.objectives || "").trim().length >= 10,
      detail: "Formulated",
    },
    {
      label: "Scope & Criteria Framework selected",
      valid:
        (formState.refinedScope || "").trim().length >= 10 &&
        formState.criteriaFramework.length >= 1,
      detail: `${formState.criteriaFramework.length} standards`,
    },
    {
      label: "Methodology & Evidence documented",
      valid:
        (formState.methodology || "").trim().length >= 20 &&
        formState.evidenceRequirements.length >= 1,
      detail: `${formState.evidenceRequirements.length} categories`,
    },
    {
      label: "Key Milestones schedule defined",
      valid: formState.milestones.length >= 2,
      detail: `${formState.milestones.length} milestones`,
    },
    {
      label: "Engagement KPIs established",
      valid: formState.kpis.length >= 2,
      detail: `${formState.kpis.length} metrics`,
    },
    {
      label: "Communication & Approval assigned",
      valid:
        (formState.communicationToManagement || "").trim().length >= 10 &&
        (formState.approvingAuthority || "").trim().length >= 2,
      detail: "OD-001 Provisional",
    },
  ];

  const passedCount = checks.filter((c) => c.valid).length;

  return (
    <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-3.5">
      <div className="flex items-center justify-between border-b border-slate-100 pb-2">
        <div className="flex items-center gap-1.5 font-bold text-slate-800 text-xs">
          <Shield size={14} className="text-blue-600" />
          <span>AEP Plan Controls (§7, §9.2)</span>
        </div>
        <span className="rounded bg-blue-50 px-1.5 py-0.2 text-[10px] font-mono font-bold text-blue-700">
          SCR-222
        </span>
      </div>

      {/* Engagement Identity Box */}
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
            Phase 2 of 10 • Audit Planning
          </span>
        </div>

        <div className="flex items-center justify-between">
          <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
            AEP Status
          </span>
          <span className="rounded bg-amber-50 px-1.5 py-0.2 text-[10px] font-bold text-amber-700 border border-amber-200">
            {formState.status} ({formState.version})
          </span>
        </div>
      </div>

      {/* 6-Point Readiness Checklist */}
      <div className="space-y-2">
        <div className="flex items-center justify-between text-xs">
          <span className="font-bold text-slate-800 flex items-center gap-1">
            <FileCheck2 size={12} className="text-blue-600" />
            <span>AEP Readiness Checklist</span>
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

      {/* Action Buttons */}
      <div className="space-y-2 pt-1">
        <Button
          type="button"
          onClick={onSubmitReview}
          disabled={!isReady}
          className="h-10 w-full rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold shadow-sm flex items-center justify-center gap-2 transition active:scale-[0.99] disabled:opacity-50"
        >
          <Send size={14} />
          <span>Submit AEP for Review</span>
        </Button>

        <Button
          type="button"
          variant="outline"
          onClick={onSaveDraft}
          className="h-9 w-full rounded-lg border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold shadow-2xs flex items-center justify-center gap-1.5 transition active:scale-[0.99]"
        >
          <Save size={13} className="text-blue-600" />
          <span>Save AEP Draft</span>
        </Button>

        <Button
          type="button"
          variant="outline"
          onClick={() =>
            navigateTo(`/admin/audit-engagement/${formState.engagementNumber}/planning`)
          }
          className="h-9 w-full rounded-lg border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold shadow-2xs flex items-center justify-center gap-1.5 transition active:scale-[0.99]"
        >
          <ArrowLeft size={13} />
          <span>Return to Planning Workspace (SCR-221)</span>
        </Button>
      </div>
    </div>
  );
}
