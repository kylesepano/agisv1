import {
  AlertTriangle,
  ArrowLeft,
  CheckCircle2,
  FileCheck2,
  FileSpreadsheet,
  Plus,
  Save,
  Send,
  Shield,
  XCircle,
} from "lucide-react";
import { Button } from "../../../../../components/ui/button";
import { navigateTo } from "../../../../../routes";
import type { WorkingPaper } from "../working-papers-types";

type WorkingPaperControlsRailProps = {
  workingPaper: WorkingPaper;
  isValid: boolean;
  onSaveDraft: () => void;
  onSubmitWorkingPaper: () => void;
  onCreateNewWorkingPaper: () => void;
};

export function WorkingPaperControlsRail({
  workingPaper,
  isValid,
  onSaveDraft,
  onSubmitWorkingPaper,
  onCreateNewWorkingPaper,
}: WorkingPaperControlsRailProps) {
  const checks = [
    {
      label: "Linked to 1 Procedure ID (MDS-200 §7.1)",
      valid: (workingPaper.procedureId || "").trim().length > 0,
      detail: workingPaper.procedureId,
    },
    {
      label: "Risk Matrix Item mapped (SCR-221)",
      valid: (workingPaper.riskMatrixItemId || "").trim().length > 0,
      detail: workingPaper.riskMatrixItemId,
    },
    {
      label: "Work Performed documented (Rule 13, min 20 chars)",
      valid: (workingPaper.workPerformed || "").trim().length >= 20,
      detail: `${(workingPaper.workPerformed || "").length} chars`,
    },
    {
      label: "Results & Conclusion recorded (min 15 chars)",
      valid:
        (workingPaper.results || "").trim().length >= 15 &&
        (workingPaper.conclusion || "").trim().length >= 15,
      detail: "Completed",
    },
    {
      label: "Preparer & Reviewer assigned (MDS-200 §16)",
      valid:
        (workingPaper.preparer || "").trim().length > 0 &&
        (workingPaper.reviewer || "").trim().length > 0,
      detail: "Assigned",
    },
  ];

  const passedCount = checks.filter((c) => c.valid).length;

  const getStatusBadgeClass = (status: WorkingPaper["reviewStatus"]) => {
    switch (status) {
      case "Cleared":
        return "bg-emerald-50 text-emerald-700 border-emerald-200";
      case "Reviewed":
        return "bg-blue-50 text-blue-700 border-blue-200";
      case "Submitted":
        return "bg-amber-50 text-amber-800 border-amber-200";
      case "Draft":
      default:
        return "bg-slate-100 text-slate-700 border-slate-200";
    }
  };

  return (
    <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-3.5">
      {/* Header */}
      <div className="flex items-center justify-between border-b border-slate-100 pb-2">
        <div className="flex items-center gap-1.5 font-bold text-slate-800 text-xs">
          <Shield size={14} className="text-purple-700" />
          <span>Working Paper Controls (§7, §9.3, §16)</span>
        </div>
        <span className="rounded bg-purple-50 px-1.5 py-0.2 text-[10px] font-mono font-bold text-purple-700">
          SCR-228
        </span>
      </div>

      {/* Record Identity Box */}
      <div className="rounded-lg border border-slate-100 bg-slate-50/70 p-2.5 space-y-1.5 text-xs">
        <div className="flex items-center justify-between">
          <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
            Working Paper ID
          </span>
          <span className="font-mono font-bold text-purple-700 text-xs">
            {workingPaper.id}
          </span>
        </div>

        <div className="flex items-center justify-between">
          <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
            Reference No.
          </span>
          <span className="font-mono text-slate-700 text-[11px]">
            {workingPaper.referenceNumber}
          </span>
        </div>

        <div className="flex items-center justify-between">
          <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
            Lifecycle Phase
          </span>
          <span className="rounded bg-indigo-50 px-1.5 py-0.2 text-[10px] font-bold text-indigo-700 border border-indigo-200">
            Phase 4 • Execution WP
          </span>
        </div>

        <div className="flex items-center justify-between">
          <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
            Review Status
          </span>
          <span
            className={`rounded px-1.5 py-0.2 text-[10px] font-bold border ${getStatusBadgeClass(
              workingPaper.reviewStatus,
            )}`}
          >
            {workingPaper.reviewStatus}
          </span>
        </div>
      </div>

      {/* Open Gap Notice: MDS-200 §10 Status Model */}
      <div className="rounded-md border border-amber-200 bg-amber-50/80 p-2 text-[10px] text-amber-900 leading-snug space-y-1">
        <div className="flex items-center gap-1 font-bold text-amber-950">
          <AlertTriangle size={11} className="text-amber-700 shrink-0" />
          <span>Open Gap: MDS-200 §10 Status Model</span>
        </div>
        <p className="text-[10px]">
          MDS-200 §10 standardizes status values for Engagements, Findings, and Reports, but leaves Working Paper review transition milestones uncodified. Status progression (Draft &rarr; Submitted &rarr; Reviewed &rarr; Cleared) is <strong>OD-MDS-Status Provisional</strong>.
        </p>
      </div>

      {/* Controlled Signatories Box (MDS-200 §16, UID-200 §7.3) */}
      <div className="rounded-lg border border-slate-200 bg-slate-50/50 p-2.5 space-y-1.5 text-xs">
        <span className="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">
          Controlled Signatories
        </span>

        <div className="space-y-1 text-[11px]">
          <div className="flex items-center justify-between">
            <span className="text-slate-500">Preparer:</span>
            <span className="font-semibold text-slate-800 truncate max-w-[170px]">
              {workingPaper.preparer}
            </span>
          </div>
          <div className="flex items-center justify-between">
            <span className="text-slate-500">Supervising Reviewer:</span>
            <span className="font-semibold text-slate-800 truncate max-w-[170px]">
              {workingPaper.reviewer}
            </span>
          </div>
        </div>
      </div>

      {/* 5-Point Governance Checklist */}
      <div className="space-y-2">
        <div className="flex items-center justify-between text-xs">
          <span className="font-bold text-slate-800 flex items-center gap-1">
            <FileCheck2 size={12} className="text-purple-700" />
            <span>Working Paper Governance</span>
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
          onClick={onSubmitWorkingPaper}
          disabled={!isValid || workingPaper.reviewStatus === "Submitted" || workingPaper.reviewStatus === "Cleared"}
          className="h-10 w-full rounded-lg bg-purple-600 hover:bg-purple-700 text-white text-xs font-semibold shadow-sm flex items-center justify-center gap-2 transition active:scale-[0.99] disabled:opacity-50 cursor-pointer"
        >
          <Send size={14} />
          <span>
            {workingPaper.reviewStatus === "Cleared"
              ? "Working Paper Cleared"
              : workingPaper.reviewStatus === "Submitted"
                ? "Submitted for Review"
                : "Submit Working Paper"}
          </span>
        </Button>

        <Button
          type="button"
          variant="outline"
          onClick={onSaveDraft}
          className="h-9 w-full rounded-lg border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold shadow-2xs flex items-center justify-center gap-1.5 transition active:scale-[0.99] cursor-pointer"
        >
          <Save size={13} className="text-purple-600" />
          <span>Save WP Draft</span>
        </Button>

        <Button
          type="button"
          variant="outline"
          onClick={onCreateNewWorkingPaper}
          className="h-9 w-full rounded-lg border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold shadow-2xs flex items-center justify-center gap-1.5 transition active:scale-[0.99] cursor-pointer"
        >
          <Plus size={13} className="text-emerald-600" />
          <span>New Working Paper</span>
        </Button>

        <Button
          type="button"
          variant="outline"
          onClick={() =>
            navigateTo(
              `/admin/audit-engagement/${workingPaper.engagementNumber}/fieldwork-records`,
            )
          }
          className="h-9 w-full rounded-lg border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold shadow-2xs flex items-center justify-center gap-1.5 transition active:scale-[0.99] cursor-pointer"
        >
          <FileSpreadsheet size={13} className="text-blue-600" />
          <span>Return to Fieldwork Record (SCR-227)</span>
        </Button>

        <Button
          type="button"
          variant="outline"
          onClick={() =>
            navigateTo(
              `/admin/audit-engagement/${workingPaper.engagementNumber}/execution`,
            )
          }
          className="h-9 w-full rounded-lg border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold shadow-2xs flex items-center justify-center gap-1.5 transition active:scale-[0.99] cursor-pointer"
        >
          <ArrowLeft size={13} />
          <span>Return to Execution Hub (SCR-226)</span>
        </Button>
      </div>
    </div>
  );
}
