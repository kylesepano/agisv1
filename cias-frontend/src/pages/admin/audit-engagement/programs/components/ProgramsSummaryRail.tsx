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
import type { AuditProgram } from "../programs-types";
import { evaluateProgramsReadiness } from "../programs-readiness";

type ProgramsSummaryRailProps = {
  engagementNumber: string;
  programs: AuditProgram[];
  onSaveDraft: () => void;
  onSubmitReview: () => void;
};

export function ProgramsSummaryRail({
  engagementNumber,
  programs,
  onSaveDraft,
  onSubmitReview,
}: ProgramsSummaryRailProps) {
  const readiness = evaluateProgramsReadiness(programs);
  const { checks, passedCount, totalProcedures, totalManDays, isReadyForSubmit, tooltipReason } =
    readiness;

  return (
    <div className="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm space-y-3.5">
      <div className="flex items-center justify-between border-b border-slate-100 pb-2">
        <div className="flex items-center gap-1.5 font-bold text-slate-800 text-xs">
          <Shield size={14} className="text-blue-600" />
          <span>Programs Package Controls (§7, §9.2)</span>
        </div>
        <span className="rounded bg-blue-50 px-1.5 py-0.2 text-[10px] font-mono font-bold text-blue-700">
          SCR-223
        </span>
      </div>

      {/* Engagement Identity Box */}
      <div className="rounded-lg border border-slate-100 bg-slate-50/70 p-2.5 space-y-1.5 text-xs">
        <div className="flex items-center justify-between">
          <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
            Engagement No.
          </span>
          <span className="font-mono font-bold text-blue-700 text-xs">
            {engagementNumber}
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
            Programs Status
          </span>
          <span className="rounded bg-amber-50 px-1.5 py-0.2 text-[10px] font-bold text-amber-700 border border-amber-200">
            Draft (v1.0)
          </span>
        </div>
      </div>

      {/* Resource & Scope Metrics Box */}
      <div className="rounded-lg border border-slate-200 bg-slate-50/50 p-2.5 space-y-2 text-xs">
        <span className="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">
          Fieldwork Package Reconciliation
        </span>

        <div className="grid grid-cols-3 gap-1.5 text-center">
          <div className="rounded bg-white p-1.5 border border-slate-200/80">
            <span className="text-[9px] text-slate-400 font-bold block">Programs</span>
            <span className="font-mono text-xs font-bold text-blue-700">
              {programs.length}
            </span>
          </div>

          <div className="rounded bg-white p-1.5 border border-slate-200/80">
            <span className="text-[9px] text-slate-400 font-bold block">Procedures</span>
            <span className="font-mono text-xs font-bold text-emerald-700">
              {totalProcedures}
            </span>
          </div>

          <div className="rounded bg-white p-1.5 border border-slate-200/80">
            <span className="text-[9px] text-slate-400 font-bold block">Man-Days</span>
            <span className="font-mono text-xs font-bold text-indigo-700">
              {totalManDays}
            </span>
          </div>
        </div>
      </div>

      {/* 5-Point Readiness Checklist */}
      <div className="space-y-2">
        <div className="flex items-center justify-between text-xs">
          <span className="font-bold text-slate-800 flex items-center gap-1">
            <FileCheck2 size={12} className="text-blue-600" />
            <span>Programs Readiness Checklist</span>
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
          disabled={!isReadyForSubmit}
          title={tooltipReason}
          className="h-10 w-full rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold shadow-sm flex items-center justify-center gap-2 transition active:scale-[0.99] disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <Send size={14} />
          <span>Submit Programs for Review</span>
        </Button>

        <Button
          type="button"
          variant="outline"
          onClick={onSaveDraft}
          className="h-9 w-full rounded-lg border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold shadow-2xs flex items-center justify-center gap-1.5 transition active:scale-[0.99]"
        >
          <Save size={13} className="text-blue-600" />
          <span>Save Programs Draft</span>
        </Button>

        <Button
          type="button"
          variant="outline"
          onClick={() =>
            navigateTo(`/admin/audit-engagement/${engagementNumber}/planning`)
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
